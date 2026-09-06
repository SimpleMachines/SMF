#!/usr/bin/env bash
# Upgrades a 2.1 database to 3.0, then upgrades it again, and reports where the
# second run changed anything.
#
#   .docker/rerun-upgrade.sh --engine mysql      --baseline ../SMF-2.1/.docker/baseline/artifacts/2.1.7-1/small/mysql.sql
#   .docker/rerun-upgrade.sh --engine postgresql --baseline ../SMF-2.1/.docker/baseline/artifacts/2.1.7-1/small/postgres.sql
#
# Running the upgrader twice is not an unusual thing to do. It is what happens
# when an admin refreshes a page that timed out, when a run dies part way and is
# started again, and -- routinely -- when one 3.0 patch release is upgraded to
# the next, since VERSION_MAP keys on an upper bound and '3.0.99' selects the
# v3_0 migrations for any 3.0.x forum. Every migration therefore has to be safe
# to repeat, and nothing checks that they are.
#
# A second run that is doing its job changes nothing at all: the same tables,
# the same columns, the same indexes, the same number of rows, the same
# settings. Anything this reports is a migration whose second pass did work it
# should have skipped -- an ALTER that ran again, an INSERT with no guard on it,
# a column re-added under a different type.
#
# --baseline takes any SQL dump of a 2.1 database. The one this was written
# against is the committed baseline from the 2.1 development environment, which
# is built to hold something in every table an upgrade touches, but a dump of a
# real forum works and is a better test.
#
# The database for the chosen engine is rebuilt. Anything already installed on
# that engine is destroyed, and what is left at the end is the twice-upgraded
# forum. The other engine is not touched.
#
# Runs on the host. Expect five to ten minutes per engine.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINE=''
BASELINE=''
OUT="$DOCKER_DIR/rerun"

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--baseline) BASELINE="$2"; shift 2 ;;
		--baseline=*) BASELINE="${1#*=}"; shift ;;
		--out) OUT="$2"; shift 2 ;;
		--out=*) OUT="${1#*=}"; shift ;;
		-h|--help) sed -n '2,30p' "${BASH_SOURCE[0]}"; exit 0 ;;
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

. "$DOCKER_DIR/upgrade-readings.sh"

rerun_one() {
	local smf_type="$1" version status=0

	: > "$OUT/report-${smf_type}.txt"

	log "${smf_type}: emptying the database"
	"$DOCKER_DIR/reset.sh" --engine "$smf_type" >/dev/null

	log "${smf_type}: loading ${BASELINE##*/}"
	load_baseline "$smf_type" "$BASELINE"

	version=$(installed_version "$smf_type" || true)

	if [ -z "$version" ]; then
		warn "${smf_type}: the dump loaded but there is no forum in it -- wrong prefix, or not an SMF dump"

		return 1
	fi

	log "${smf_type}: the dump is SMF ${version}"

	log "${smf_type}: upgrading"
	run_upgrade "$smf_type" "$OUT/upgrade-first-${smf_type}.log" || return 1
	log "${smf_type}: upgraded to SMF $(smf_version)"

	snapshot "$smf_type" first "$OUT"

	# The upgrader deletes nothing and leaves no marker behind saying it has
	# run, so the second call is the same call. That is the point: this is what
	# an admin who refreshes, or who upgrades 3.0.1 to 3.0.2, actually does.
	log "${smf_type}: upgrading again"
	run_upgrade "$smf_type" "$OUT/upgrade-second-${smf_type}.log" || return 1

	snapshot "$smf_type" second "$OUT"

	echo
	log "${smf_type}: what the second run changed"

	compare_snapshots "$smf_type" first second "$OUT" || status=1

	echo

	if [ "$status" -eq 0 ]; then
		log "${smf_type}: the second upgrade changed nothing"
	else
		cat "$OUT/.compare" | tee "$OUT/report-${smf_type}.txt"
		log "${smf_type}: the second upgrade changed the database -- ${OUT}/report-${smf_type}.txt"
	fi

	return "$status"
}

failed=0

for smf_type in $ENGINES; do
	rerun_one "$smf_type" || failed=1
done

exit "$failed"
