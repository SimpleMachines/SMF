#!/usr/bin/env bash
# Returns the stack to "installable": no forum, an empty database for the chosen
# engine, and a Settings.php regenerated for it.
#
#   .docker/baseline/reset.sh --engine mysql
#   .docker/baseline/reset.sh --engine postgres
#
# This is also how you move an existing install between engines. Settings.php
# pins one engine and wins over SMF_DB_TYPE, so switching means throwing it away
# and letting the entrypoint write a new one.
#
# Only the chosen engine's database is touched. The two engines keep separate
# volumes, so a MySQL reset can never disturb a PostgreSQL baseline or vice
# versa.
#
# Runs on the host.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINE=''
KEEP_FILES=0

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--keep-files) KEEP_FILES=1; shift ;;
		-h|--help) sed -n '2,17p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -n "$ENGINE" ] || die 'need --engine mysql|postgres'
SERVICE=$(engine_service "$ENGINE") || die "unknown engine: $ENGINE"
SMF_TYPE=$(engine_smf_type "$ENGINE")

cd "$BOARD_DIR"

log "resetting for ${SMF_TYPE}"

# ------------------------------------------------------------------ the forum
# Stop the web container first: Apache holding a half-installed forum open while
# its database vanishes underneath produces confusing errors in the log.
docker compose stop web >/dev/null 2>&1 || true

rm -f Settings.php Settings_bak.php Settings_org.php
rm -f install.php install_2-1_mysql.sql install_2-1_postgresql.sql

# SMF's cache holds a serialised copy of $modSettings, which would otherwise
# outlive the database it describes.
find cache -type f ! -name 'index.php' ! -name '.htaccess' -delete 2>/dev/null || true

if [ "$KEEP_FILES" -eq 0 ]; then
	for dir in attachments custom_avatar; do
		find "$dir" -type f ! -name 'index.php' ! -name '.htaccess' ! -name 'blank.png' -delete 2>/dev/null || true
	done
	rm -f Packages/installed.list
fi

# --------------------------------------------------------------- the database
docker compose up -d "$SERVICE" >/dev/null

if [ "$SMF_TYPE" = 'mysql' ]; then
	# As root: the smf user has rights on the smf database but cannot drop and
	# recreate it.
	docker compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$SERVICE" mysql -uroot -e "
		DROP DATABASE IF EXISTS \`${DB_NAME}\`;
		CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci;
		GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
	"
else
	# The database itself cannot be dropped while we are connected to it, and
	# dropping the schema is enough: it takes the tables, sequences, functions
	# and operators with it. smf owns the database, so it may recreate public.
	docker compose exec -T "$SERVICE" psql -v ON_ERROR_STOP=1 -q -U "$DB_USER" -d "$DB_NAME" -c '
		DROP SCHEMA IF EXISTS public CASCADE;
		CREATE SCHEMA public;
	' >/dev/null
fi

log "${SMF_TYPE} database ${DB_NAME} is empty"

# Bring web back up so the entrypoint regenerates Settings.php for this engine
# and stages the installer.
SMF_DB_TYPE="$SMF_TYPE" docker compose up -d web >/dev/null

# The entrypoint waits for the database before it writes anything, so give it a
# moment to get there rather than racing whatever runs next.
for _ in $(seq 1 60); do
	if docker compose exec -T web test -f install.php 2>/dev/null; then
		log 'installer staged, ready to install'
		exit 0
	fi
	sleep 1
done

die 'timed out waiting for the entrypoint to stage install.php (docker compose logs web)'
