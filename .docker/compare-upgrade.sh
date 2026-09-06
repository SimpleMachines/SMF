#!/usr/bin/env bash
# Upgrades a 2.1 database to 3.0, installs 3.0 from scratch, and reports where
# the two schemas disagree.
#
#   .docker/compare-upgrade.sh --engine mysql      --baseline ../SMF-2.1/.docker/baseline/artifacts/2.1.7-1/small/mysql.sql
#   .docker/compare-upgrade.sh --engine postgresql --baseline ../SMF-2.1/.docker/baseline/artifacts/2.1.7-1/small/postgres.sql
#
# The installer builds the schema from Sources/Db/Schema/v3_0/ in one go. The
# upgrader arrives at the same place through a hundred-odd migrations applied
# to whatever 2.1 left behind. Nothing checks that those two agree, and where
# they do not, the forum that upgraded is running on a schema that has never
# been tested against -- a column of the wrong type, an index that was never
# created, a primary key quietly dropped.
#
# --baseline takes any SQL dump of a 2.1 database. The one this was written
# against is the committed baseline from the 2.1 development environment, which
# is built to hold something in every table an upgrade touches, but a dump of a
# real forum works and is a better test.
#
# Both databases for the chosen engine are rebuilt, twice. Anything already
# installed on that engine is destroyed, and what remains at the end is the
# fresh install. The other engine is not touched.
#
# Runs on the host. Expect five to ten minutes per engine.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINE=''
BASELINE=''
OUT="$DOCKER_DIR/compare"

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--baseline) BASELINE="$2"; shift 2 ;;
		--baseline=*) BASELINE="${1#*=}"; shift ;;
		--out) OUT="$2"; shift 2 ;;
		--out=*) OUT="${1#*=}"; shift ;;
		-h|--help) sed -n '2,25p' "${BASH_SOURCE[0]}"; exit 0 ;;
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

# The tool that reads the two files runs in the container, which sees the
# repository and nothing else, so the output has to live somewhere inside it.
case "$OUT" in
	"$BOARD_DIR"/*) OUT_REL="${OUT#"$BOARD_DIR"/}" ;;
	*) die "--out has to be somewhere inside the repository, since the container cannot see anywhere else" ;;
esac

# Reads the shape of the database the given engine is pointed at. The tool runs
# in the container, so it uses the container-internal host and port rather than
# the ones published in compose.yaml.
snapshot() {
	local smf_type="$1" label="$2" file="$3"

	docker compose exec -T web php .docker/schema-tool.php dump \
		--engine "$smf_type" \
		--db "$DB_NAME" \
		--prefix "$DB_PREFIX" \
		--host "$(engine_server "$smf_type")" \
		--port "$(engine_port "$smf_type")" \
		--user "$DB_USER" \
		--pass "$DB_PASSWORD" \
		--label "$label" > "$file"

	[ -s "$file" ] || die "${smf_type}: the ${label} reading came back empty"
}

# The version this checkout is, which is what a finished upgrade has to leave
# in the database.
smf_version() {
	sed -n "s/.*define('SMF_VERSION', '\([^']*\)').*/\1/p" index.php | head -1
}

load_baseline() {
	local smf_type="$1" service
	service=$(engine_service "$smf_type")

	if [ "$smf_type" = 'mysql' ]; then
		docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
			mysql -u"$DB_USER" -D "$DB_NAME" < "$BASELINE"
	else
		# ON_ERROR_STOP so that a dump taken from a database that is not empty,
		# or one taken with a different owner, stops here rather than producing
		# a half-loaded forum that then fails somewhere in the upgrader and
		# looks like a migration bug.
		docker compose exec -T "$service" \
			psql -v ON_ERROR_STOP=1 -q -U "$DB_USER" -d "$DB_NAME" < "$BASELINE" >/dev/null
	fi
}

compare_one() {
	local smf_type="$1" version

	# ---------------------------------------------------------- the upgrade
	log "${smf_type}: emptying the database"
	"$DOCKER_DIR/reset.sh" --engine "$smf_type" >/dev/null

	log "${smf_type}: loading ${BASELINE##*/}"
	load_baseline "$smf_type"

	version=$(installed_version "$smf_type" || true)

	if [ -z "$version" ]; then
		warn "${smf_type}: the dump loaded but there is no forum in it -- wrong prefix, or not an SMF dump"

		return 1
	fi

	log "${smf_type}: the dump is SMF ${version}"

	# The upgrader ships in other/ and expects to be run from the board
	# directory, the same way install.php does. Nobody upgrading a real forum
	# has install.php sitting there, so neither does this.
	rm -f install.php
	cp other/upgrade.php upgrade.php

	log "${smf_type}: upgrading"

	local upgraded=0
	docker compose exec -T web php upgrade.php > "$OUT/upgrade-${smf_type}.log" 2>&1 || upgraded=$?

	rm -f upgrade.php

	if [ "$upgraded" -ne 0 ]; then
		warn "${smf_type}: the upgrader exited ${upgraded} -- ${OUT}/upgrade-${smf_type}.log"

		return 1
	fi

	# The upgrader reports a failed migration and stops, but still exits 0, so
	# the version in the database is the only thing that says whether it got to
	# the end. Checking it matters more than it looks: a half-upgraded database
	# differs from a fresh install in hundreds of places, all of them the honest
	# consequence of the migrations that never ran, and none of them the kind of
	# difference this script exists to find.
	version=$(installed_version "$smf_type" || true)

	if [ "$version" != "$(smf_version)" ]; then
		warn "${smf_type}: the upgrade stopped at SMF ${version:-nothing}, expected $(smf_version)"
		warn "${smf_type}: the last thing it said is at the end of ${OUT}/upgrade-${smf_type}.log"

		return 1
	fi

	log "${smf_type}: upgraded to SMF ${version}"

	snapshot "$smf_type" upgraded "$OUT/upgraded-${smf_type}.json"

	# ----------------------------------------------------- the fresh install
	# --force because there is an installed forum now, and install-forum.sh
	# leaves one alone unless told otherwise.
	log "${smf_type}: installing from scratch"
	"$DOCKER_DIR/install-forum.sh" --engine "$smf_type" --force >/dev/null

	snapshot "$smf_type" fresh "$OUT/fresh-${smf_type}.json"

	# ------------------------------------------------------------ the report
	local status=0

	docker compose exec -T web php .docker/schema-tool.php diff \
		"${OUT_REL}/fresh-${smf_type}.json" \
		"${OUT_REL}/upgraded-${smf_type}.json" \
		> "$OUT/report-${smf_type}.txt" || status=$?

	echo
	cat "$OUT/report-${smf_type}.txt"
	echo

	if [ "$status" -eq 0 ]; then
		log "${smf_type}: the upgraded schema matches a fresh install"
	else
		log "${smf_type}: the schemas differ -- ${OUT}/report-${smf_type}.txt"
	fi

	return "$status"
}

failed=0

for smf_type in $ENGINES; do
	compare_one "$smf_type" || failed=1
done

exit "$failed"
