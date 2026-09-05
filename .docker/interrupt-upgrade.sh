#!/usr/bin/env bash
# Kills an upgrade part way through, starts it again, and reports whether the
# forum it ends up with is the one an uninterrupted upgrade would have built.
#
#   .docker/interrupt-upgrade.sh --engine mysql --baseline ../SMF-2.1/.docker/baseline/artifacts/2.1.7-1/small/mysql.sql
#   .docker/interrupt-upgrade.sh --engine mysql --baseline ... --at 40
#   .docker/interrupt-upgrade.sh --engine mysql --baseline ... --points 10,25,50,75,90
#   .docker/interrupt-upgrade.sh --engine mysql --baseline ... --backup
#
# --backup asks the upgrader for the backup step, which the command line skips
# unless something does. It is worth turning on precisely because a retry is
# what endangers it: backup_table() drops the backup table before writing it, so
# a run that is killed after the migrations have started and then started again
# takes its backup a second time, over a database that is no longer the one the
# admin wanted a copy of. The reading of the backup_ tables' columns is what
# reports that.
#
# An upgrade that is interrupted is the ordinary case, not the exotic one. A
# browser is closed, a request is cut off, a host runs out of memory, somebody
# presses ctrl-c. What the upgrader keeps in order to survive that is a good
# deal less than it looks: the step, substep and start position live in $_GET
# and nowhere else, and the maintenance_tool_progress blob in Settings.php --
# the only thing written to disk -- holds the version the run started from, who
# started it and what was skipped, but not where it had got to. It is written
# by preExit(), which a killed process never reaches.
#
# So recovery here means starting again from the top, over a database that is
# now in neither the old shape nor the new one. Every migration has to cope
# with that, which is a stronger requirement than being safe to repeat over a
# finished forum: an isCandidate() guard written against the 2.1 shape is being
# asked about a database that is half way to 3.0.
#
# The reference is an uninterrupted upgrade of the same baseline. Anything this
# reports is a place where being interrupted left the forum different from one
# that was not -- a migration that half ran, a guard that skipped work that had
# not actually been done, a table left in an intermediate shape.
#
# The database for the chosen engine is rebuilt, once per kill point plus once
# for the reference. The other engine is not touched.
#
# Runs on the host. Expect five to ten minutes per run, so a sweep of five kill
# points is the better part of an hour.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINE=''
BASELINE=''
OUT="$DOCKER_DIR/interrupt"
POINTS='10,25,50,75,90'
AT=''
# How many times the upgrader may be started again after a kill before we call
# it stuck. A healthy recovery takes one; more than that means each run is
# dying somewhere too, which is worth reporting rather than looping on.
MAX_ROUNDS=4
# Passed through to upgrade.php. Empty by default, which is what an admin
# upgrading from the command line gets.
UPGRADE_ARGS=''

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--baseline) BASELINE="$2"; shift 2 ;;
		--baseline=*) BASELINE="${1#*=}"; shift ;;
		--out) OUT="$2"; shift 2 ;;
		--out=*) OUT="${1#*=}"; shift ;;
		--at) AT="$2"; shift 2 ;;
		--at=*) AT="${1#*=}"; shift ;;
		--points) POINTS="$2"; shift 2 ;;
		--points=*) POINTS="${1#*=}"; shift ;;
		--backup) UPGRADE_ARGS="$UPGRADE_ARGS --backup"; shift ;;
		-h|--help) sed -n '2,41p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -n "$ENGINE" ] || die 'need --engine mysql|postgresql|both'
ENGINES=$(engine_list "$ENGINE") || die "unknown engine: $ENGINE"
[ -n "$BASELINE" ] || die 'need --baseline <a 2.1 SQL dump>'
[ -f "$BASELINE" ] || die "no such file: $BASELINE"

cd "$BOARD_DIR"
mkdir -p "$OUT"
OUT=$(cd -- "$OUT" && pwd)

# The upgrader writes its log into logs/ in the board directory, which is bind
# mounted, so the host can watch a run in progress line by line. That is what
# makes the kill point a substep rather than a number of seconds: the same
# migration is interrupted every time, and a report names it.
UPGRADE_LOG="$BOARD_DIR/logs/upgrade.log"

# Where the log of a run that has ended is. finalizeLog() renames it to carry a
# UTC timestamp once the tool exits normally, so the live name only survives a
# run that was killed -- which is the one case where it is the file we want.
finished_upgrade_log() {
	if [ -f "$UPGRADE_LOG" ]; then
		echo "$UPGRADE_LOG"

		return
	fi

	ls -1t "$BOARD_DIR"/logs/upgrade_*.log 2>/dev/null | head -1
}

# Everything the readings and the run share with rerun-upgrade.sh lives here
# rather than being written twice.
. "$DOCKER_DIR/upgrade-readings.sh"

# How many substeps the log has seen. Each one is announced before it runs, so
# this counts substeps started, not substeps finished -- which is what we want,
# since killing during a substep is the interesting case.
substeps_logged() {
	local n

	# grep -c prints its count and *then* exits 1 when the count is zero, so a
	# trailing `|| echo 0` appends a second line and every numeric test on the
	# result fails. Take the count and correct it separately.
	n=$(grep -c '^ +++ ' "$UPGRADE_LOG" 2>/dev/null) || n=0

	echo "${n:-0}"
}

# The name of the last substep the log announced, for the report.
last_substep() {
	[ -f "$UPGRADE_LOG" ] || return 0

	grep '^ +++ ' "$UPGRADE_LOG" 2>/dev/null | tail -1 | sed -e 's/^ +++ //' -e 's/\.\{3,\}.*$//'
}

# Starts an upgrade and kills it once the log says it has begun its Nth substep.
# Returns 0 if it was killed as intended, 1 if it finished or died on its own
# first -- either of which means the kill point was past the end of the run.
run_and_kill() {
	local smf_type="$1" want="$2" log="$3" waited=0

	rm -f install.php
	cp other/upgrade.php upgrade.php

	# A run that was killed leaves its log behind under the live name, and the
	# next one does not truncate it until it reaches step 0. Counting during
	# that window would see the previous run's substeps and kill this one
	# immediately, so the file goes first.
	rm -f "$UPGRADE_LOG"

	# shellcheck disable=SC2086 -- deliberately word split; these are flags.
	docker compose exec -T web php upgrade.php ${UPGRADE_ARGS:-} > "$log" 2>&1 &
	local runner=$!

	# Polling the log rather than the process: the point of interest is what
	# the upgrader has reached, and only the log says that.
	while kill -0 "$runner" 2>/dev/null; do
		if [ "$(substeps_logged)" -ge "$want" ]; then
			KILLED_AFTER=$(last_substep)

			# The process this shell started is the docker client. Killing it
			# closes the connection but leaves php running in the container, so
			# the container's copy is the one that has to be signalled.
			docker compose exec -T web pkill -9 -f 'php upgrade.php' >/dev/null 2>&1 || true
			wait "$runner" 2>/dev/null || true
			rm -f upgrade.php

			return 0
		fi

		# A tenth of a second is short enough that the kill lands inside the
		# substep that tripped it rather than several substeps later.
		sleep 0.1
		waited=$((waited + 1))

		[ "$waited" -lt 6000 ] || die "${smf_type}: the upgrade ran for ten minutes without reaching substep ${want}"
	done

	wait "$runner" 2>/dev/null || true
	rm -f upgrade.php

	return 1
}

interrupt_one_point() {
	local smf_type="$1" want="$2" round=0 version

	log "${smf_type}: emptying the database"
	"$DOCKER_DIR/reset.sh" --engine "$smf_type" >/dev/null
	load_baseline "$smf_type" "$BASELINE"

	KILLED_AFTER=''

	if ! run_and_kill "$smf_type" "$want" "$OUT/killed-${smf_type}-${want}.log"; then
		warn "${smf_type}: the run finished before substep ${want}, so there was nothing to interrupt"

		return 0
	fi

	log "${smf_type}: killed during '${KILLED_AFTER}' (substep ${want})"

	# The upgrader truncates its log at step 0, so the record of the run that
	# was killed is gone the moment the next one starts. Keep a copy.
	cp "$UPGRADE_LOG" "$OUT/killed-${smf_type}-${want}.upgrade.log" 2>/dev/null || true

	version=$(installed_version "$smf_type" || true)
	log "${smf_type}: the database says SMF ${version:-nothing}"

	# What the backup step left behind before anything was retried. Read here
	# rather than only at the end because the question is not whether a backup
	# exists afterwards -- one will -- but whether it is still the one taken of
	# the forum as it was.
	snapshot_backups "$smf_type" "$OUT/killed-${smf_type}-${want}.backups.tsv"

	# ------------------------------------------------------------ recovery
	while [ "$round" -lt "$MAX_ROUNDS" ]; do
		round=$((round + 1))

		log "${smf_type}: restarting the upgrade (round ${round})"

		if run_upgrade "$smf_type" "$OUT/recover-${smf_type}-${want}-${round}.log"; then
			log "${smf_type}: recovered in ${round} $([ "$round" = 1 ] && echo run || echo runs)"

			break
		fi

		if [ "$round" -ge "$MAX_ROUNDS" ]; then
			warn "${smf_type}: still not upgraded after ${MAX_ROUNDS} attempts -- it cannot recover from being killed during '${KILLED_AFTER}'"
			printf 'killed during: %s (substep %s)\n  could not recover in %s attempts\n\n' \
				"$KILLED_AFTER" "$want" "$MAX_ROUNDS" >> "$OUT/report-${smf_type}.txt"

			return 1
		fi
	done

	snapshot "$smf_type" "resumed-${want}" "$OUT"

	# ------------------------------------------------------------- the report
	local status=0

	log "${smf_type}: what being interrupted left behind"

	compare_snapshots "$smf_type" clean "resumed-${want}" "$OUT" || status=1

	# The backup the killed run had made, against the backup that is there now.
	# A difference means the retry took its own backup over the top of it, and
	# the copy the admin would restore from is of a half-migrated forum.
	if ! diff -u "$OUT/killed-${smf_type}-${want}.backups.tsv" 		"$OUT/resumed-${want}-${smf_type}.backups.tsv" > "$OUT/.backupdiff" 2>&1; then
		printf '  %-10s REPLACED BY THE RETRY
' 'backup'
		{
			printf '  the backup tables the killed run had made were overwritten:
'
			sed -e 's/^/    /' "$OUT/.backupdiff"
			printf '
'
		} >> "$OUT/.compare"
		status=1
	elif [ -s "$OUT/killed-${smf_type}-${want}.backups.tsv" ]; then
		printf '  %-10s intact
' 'backup'
	fi

	rm -f "$OUT/.backupdiff"

	if [ "$status" -ne 0 ]; then
		{
			printf 'killed during: %s (substep %s), recovered in %s round(s)\n' "$KILLED_AFTER" "$want" "$round"
			cat "$OUT/.compare"
			printf '\n'
		} >> "$OUT/report-${smf_type}.txt"
	fi

	return "$status"
}

interrupt_one_engine() {
	local smf_type="$1" total point failed=0
	local -a want

	: > "$OUT/report-${smf_type}.txt"

	# ------------------------------------------------------- the reference
	log "${smf_type}: emptying the database"
	"$DOCKER_DIR/reset.sh" --engine "$smf_type" >/dev/null
	load_baseline "$smf_type" "$BASELINE"

	[ -n "$(installed_version "$smf_type" || true)" ] \
		|| die "${smf_type}: the dump loaded but there is no forum in it -- wrong prefix, or not an SMF dump"

	log "${smf_type}: upgrading once, uninterrupted, for the reference"
	run_upgrade "$smf_type" "$OUT/clean-${smf_type}.log" \
		|| die "${smf_type}: the uninterrupted upgrade failed, so there is nothing to compare against -- $OUT/clean-${smf_type}.log"

	snapshot "$smf_type" clean "$OUT"
	cp "$(finished_upgrade_log)" "$OUT/clean-${smf_type}.upgrade.log" 2>/dev/null || true

	total=$(grep -c '^ +++ ' "$OUT/clean-${smf_type}.upgrade.log" 2>/dev/null) || total=0

	# Without this the kill points all come out as zero, every one of them is
	# skipped for being out of range, and the script reports that nothing went
	# wrong -- which would be a lie told by an empty loop.
	[ "$total" -gt 0 ] 		|| die "${smf_type}: could not read the reference run's log, so there is no way to say where to interrupt -- looked for ${UPGRADE_LOG} and ${BOARD_DIR}/logs/upgrade_*.log"

	log "${smf_type}: an uninterrupted upgrade runs ${total} substeps"

	# Kill points are given as a percentage of that, so the same set of numbers
	# means the same places in the run whatever the baseline is and however many
	# migrations the version has grown since.
	if [ -n "$AT" ]; then
		want=("$AT")
	else
		want=()

		for point in ${POINTS//,/ }; do
			want+=( $(( total * point / 100 )) )
		done
	fi

	for point in "${want[@]}"; do
		[ "$point" -gt 0 ] || continue

		echo
		interrupt_one_point "$smf_type" "$point" || failed=1
	done

	echo

	if [ "$failed" -eq 0 ]; then
		log "${smf_type}: every interrupted upgrade recovered into the same forum as an uninterrupted one"
	else
		cat "$OUT/report-${smf_type}.txt"
		log "${smf_type}: being interrupted changed the outcome -- ${OUT}/report-${smf_type}.txt"
	fi

	return "$failed"
}

failed=0

for smf_type in $ENGINES; do
	interrupt_one_engine "$smf_type" || failed=1
done

exit "$failed"
