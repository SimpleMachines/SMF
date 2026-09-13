#!/usr/bin/env bash
# Upgrades a 2.1 database, marks the run as one that did not finish, and then
# asks the upgrader to put the database back with --rollback.
#
#   .docker/try-rollback-offer.sh --engine postgresql --baseline ../SMF-2.1/.docker/baseline/artifacts/2.1.7-1/small/postgres.sql
#
# This exists to exercise the offer the upgrader makes when it finds a run that
# stopped part way. Killing a real upgrade at the right moment is a race, and
# what is being tested here is the offer rather than the interruption, which is
# covered by interrupt-upgrade.sh.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"
. "$(dirname -- "${BASH_SOURCE[0]}")/upgrade-readings.sh"

ENGINE=''
BASELINE=''
OUT="$DOCKER_DIR/offer"

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--baseline) BASELINE="$2"; shift 2 ;;
		--out) OUT="$2"; shift 2 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -n "$ENGINE" ] || die 'need --engine'
[ -n "$BASELINE" ] || die 'need --baseline'

cd "$BOARD_DIR"
mkdir -p "$OUT"
OUT=$(cd -- "$OUT" && pwd)

log "${ENGINE}: emptying the database"
"$DOCKER_DIR/reset.sh" --engine "$ENGINE" >/dev/null

log "${ENGINE}: loading $(basename -- "$BASELINE")"
load_baseline "$ENGINE" "$BASELINE"

log "${ENGINE}: upgrading"
UPGRADE_ARGS='--backup' run_upgrade "$ENGINE" "$OUT/upgrade-${ENGINE}.log" \
	|| die "${ENGINE}: the upgrade failed -- $OUT/upgrade-${ENGINE}.log"

log "${ENGINE}: upgraded to SMF $(installed_version "$ENGINE")"
snapshot "$ENGINE" upgraded "$OUT"

# What a killed process leaves behind is a run with no finishing time on it.
# Setting that here is the difference between testing the offer and testing
# how well a kill can be timed.
log "${ENGINE}: marking the run as one that did not finish"

if [ "$ENGINE" = 'mysql' ]; then
	docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" mysql \
		mysql -u"$DB_USER" -D "$DB_NAME" -e "UPDATE ${DB_PREFIX}migration_runs SET time_finished = 0;"
else
	docker compose exec -T postgres \
		psql -q -U "$DB_USER" -d "$DB_NAME" -c "UPDATE ${DB_PREFIX}migration_runs SET time_finished = 0;"
fi

log "${ENGINE}: asking the upgrader to put it back"
rm -f "$BOARD_DIR/install.php"
cp "$BOARD_DIR/other/upgrade.php" "$BOARD_DIR/upgrade.php"

status=0
docker compose exec -T web php upgrade.php --rollback > "$OUT/rollback-${ENGINE}.log" 2>&1 || status=$?

rm -f "$BOARD_DIR/upgrade.php"

printf '\n'
cat "$OUT/rollback-${ENGINE}.log"
printf '\n'

log "${ENGINE}: the forum is now SMF $(installed_version "$ENGINE")"

exit "$status"
