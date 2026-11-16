#!/usr/bin/env bash
# Loads a committed baseline back into the stack, and checks that it arrived.
#
#   .docker/baseline/restore.sh --engine mysql --profile small
#
# This is what makes the artifacts trustworthy rather than merely present: a
# dump that has never been restored is a guess. make-baseline.sh runs it
# immediately after every dump for exactly that reason.
#
# It is also how the later 2.1 -> 3.0 migration work will start: restore, point
# a 3.0 checkout at the same database, run upgrade.php.
#
# Runs on the host.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINE=''
PROFILE="$BASELINE_PROFILE"
VERSION="$BASELINE_VERSION"

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--profile) PROFILE="$2"; shift 2 ;;
		--profile=*) PROFILE="${1#*=}"; shift ;;
		--version) VERSION="$2"; shift 2 ;;
		--version=*) VERSION="${1#*=}"; shift ;;
		-h|--help) sed -n '2,12p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -n "$ENGINE" ] || die 'need --engine mysql|postgres'
SERVICE=$(engine_service "$ENGINE") || die "unknown engine: $ENGINE"
SMF_TYPE=$(engine_smf_type "$ENGINE")

cd "$BOARD_DIR"

HOST_DIR="${ARTIFACT_DIR}/${VERSION}/${PROFILE}"
CONTAINER_DIR="/artifacts/${VERSION}/${PROFILE}"
DUMP_NAME=$([ "$SMF_TYPE" = 'mysql' ] && echo 'mysql.sql' || echo 'postgres.sql')

[ -f "${HOST_DIR}/${DUMP_NAME}" ] || die "no dump at ${HOST_DIR}/${DUMP_NAME}"

log "restoring ${VERSION}/${PROFILE} (${SMF_TYPE})"

# ------------------------------------------------------------------ checksums
if [ -f "${ARTIFACT_DIR}/${VERSION}/manifest.json" ]; then
	# Read through the container rather than a host php: the whole point of this
	# environment is that the host needs nothing but Docker. --entrypoint skips
	# the image's usual startup, which would otherwise wait for a database and
	# write a Settings.php we are about to replace.
	expected=$(docker compose run --rm --no-deps -T --entrypoint php web \
		.docker/baseline/manifest-field.php \
			"--version=${VERSION}" "--profile=${PROFILE}" \
			"--engine=${SMF_TYPE}" "--file=${DUMP_NAME}" 2>/dev/null | tr -d '\r\n')

	if [ -n "$expected" ]; then
		actual=$(sha256sum "${HOST_DIR}/${DUMP_NAME}" | cut -d' ' -f1)

		[ "$actual" = "$expected" ] \
			|| die "${DUMP_NAME} does not match the manifest (${actual} != ${expected})"

		log 'checksum ok'
	fi
fi

# ----------------------------------------------------------- empty the target
docker compose stop web >/dev/null 2>&1 || true
docker compose up -d "$SERVICE" >/dev/null

if [ "$SMF_TYPE" = 'mysql' ]; then
	# The dump carries its own DROP DATABASE / CREATE DATABASE / USE, so it only
	# needs a connection with the rights to run them.
	docker compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$SERVICE" sh -c \
		"mysql -uroot < '${CONTAINER_DIR}/mysql.sql'"

	# --databases restores the grants' target but not the grants themselves.
	docker compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$SERVICE" mysql -uroot \
		-e "GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%'; FLUSH PRIVILEGES;"
else
	# pg_dump --format=plain has no DROP: clearing the schema is our job. smf
	# owns the database, so it is allowed to recreate public even on PostgreSQL
	# 15 and later, where public is no longer writable by everyone.
	docker compose exec -T "$SERVICE" psql -v ON_ERROR_STOP=1 -q -U "$DB_USER" -d "$DB_NAME" -c '
		DROP SCHEMA IF EXISTS public CASCADE;
		CREATE SCHEMA public;
	' >/dev/null

	docker compose exec -T "$SERVICE" psql -v ON_ERROR_STOP=1 -q \
		-U "$DB_USER" -d "$DB_NAME" -f "${CONTAINER_DIR}/postgres.sql" >/dev/null
fi

log 'database loaded'

# ------------------------------------------------------------------- the files
find cache -type f ! -name 'index.php' ! -name '.htaccess' -delete 2>/dev/null || true

FILES_NAME="files-${SMF_TYPE}.tgz"

if [ -f "${HOST_DIR}/${FILES_NAME}" ]; then
	docker compose run --rm --no-deps -T --entrypoint tar -w /var/www/html web \
		xzf ".docker/baseline/artifacts/${VERSION}/${PROFILE}/${FILES_NAME}" >/dev/null
	log 'files unpacked'
fi

# --------------------------------------------------------------- Settings.php
# Rendered from the template rather than kept as-is, so one artifact can be
# restored into a stack with different ports, a different board directory, or
# the other engine.
TEMPLATE="${ARTIFACT_DIR}/${VERSION}/Settings.baseline.php.tpl"

if [ -f "$TEMPLATE" ]; then
	if [ "$SMF_TYPE" = 'mysql' ]; then
		db_server='mysql'
		db_port='3306'
	else
		db_server='postgres'
		db_port='5432'
	fi

	sed \
		-e "s|{{DB_TYPE}}|${SMF_TYPE}|g" \
		-e "s|{{DB_SERVER}}|${db_server}|g" \
		-e "s|{{DB_PORT}}|${db_port}|g" \
		-e "s|{{DB_NAME}}|${DB_NAME}|g" \
		-e "s|{{DB_USER}}|${DB_USER}|g" \
		-e "s|{{DB_PASSWD}}|${DB_PASSWORD}|g" \
		-e "s|{{BOARDURL}}|http://localhost:${WEB_PORT:-8180}|g" \
		-e "s|{{BOARDDIR}}|/var/www/html|g" \
		"$TEMPLATE" > Settings.php

	cp Settings.php Settings_bak.php
	log 'Settings.php rendered'
fi

docker compose up -d web >/dev/null

# ----------------------------------------------------------------- assertions
for _ in $(seq 1 60); do
	if docker compose exec -T web php .docker/baseline/db.php connected >/dev/null 2>&1; then
		break
	fi
	sleep 1
done

version=$(docker compose exec -T web php .docker/baseline/db.php setting smfVersion 2>/dev/null || true)
[ "$version" = '2.1.7' ] || die "restored database reports smfVersion '${version}', expected 2.1.7"

if [ -f "${ARTIFACT_DIR}/${VERSION}/manifest.json" ]; then
	docker compose exec -T web php .docker/baseline/verify.php \
		--version="$VERSION" \
		--profile="$PROFILE" \
		--engine="$SMF_TYPE"
fi

log "restored ${VERSION}/${PROFILE} (${SMF_TYPE}) -- http://localhost:${WEB_PORT:-8180}"
