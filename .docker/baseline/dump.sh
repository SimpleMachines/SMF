#!/usr/bin/env bash
# Exports the installed forum as a committable artifact.
#
#   .docker/baseline/dump.sh --engine mysql --profile small
#
# Writes into .docker/baseline/artifacts/<version>/<profile>/:
#
#   mysql.sql | postgres.sql   the database
#   files-<engine>.tgz         attachments, custom avatars, the package list
#   ../manifest.json           row counts, checksums, versions, credentials
#   ../Settings.baseline.php.tpl   Settings.php with the container-specific bits
#                              replaced by placeholders
#
# The dump is taken *inside the database container*, not from the web one. Two
# reasons, both of which produce a broken artifact if ignored:
#
#   - the web image is Debian, whose pg_dump is version 15, and pg_dump refuses
#     to dump a server newer than itself
#   - writing to /artifacts (a bind mount) keeps the dump off stdout. Streaming
#     it back through `docker compose exec` without -T gets it a pseudo-TTY,
#     which turns every \n into \r\n and quietly corrupts the file
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
		-h|--help) sed -n '2,21p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -n "$ENGINE" ] || die 'need --engine mysql|postgres'
SERVICE=$(engine_service "$ENGINE") || die "unknown engine: $ENGINE"
SMF_TYPE=$(engine_smf_type "$ENGINE")
baseline_profile "$PROFILE" >/dev/null || die "unknown profile: ${PROFILE}"

cd "$BOARD_DIR"

# The path as the database container sees it. compose mounts
# .docker/baseline/artifacts at /artifacts on both database services.
CONTAINER_DIR="/artifacts/${VERSION}/${PROFILE}"
HOST_DIR="${ARTIFACT_DIR}/${VERSION}/${PROFILE}"

mkdir -p "$HOST_DIR"

installed=$(docker compose exec -T web php .docker/baseline/db.php setting smfVersion 2>/dev/null || true)
[ -n "$installed" ] || die 'no forum found -- install and populate before dumping'

log "dumping ${SMF_TYPE} (SMF ${installed}, profile ${PROFILE}) to ${VERSION}/${PROFILE}"

if [ "$SMF_TYPE" = 'mysql' ]; then
	# As root, not smf: the smf user has no rights to drop or create a database,
	# and --add-drop-database is what makes the artifact self-contained.
	#
	# --hex-blob is not optional. poster_ip, member_ip, member_ip2,
	# ban_items.ip_low/ip_high and every log table's ip column are VARBINARY(16)
	# holding raw inet_pton() output. Dumped as text they embed NUL bytes and
	# stray carriage returns, and the result neither restores nor survives git.
	# The password travels in the environment rather than on the command line, so
	# mysqldump does not print its "using a password is insecure" warning -- and
	# so this script never has to silence stderr to keep the output readable.
	docker compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$SERVICE" mysqldump \
		--user=root \
		--databases "$DB_NAME" \
		--add-drop-database \
		--single-transaction \
		--quick \
		--hex-blob \
		--no-tablespaces \
		--skip-dump-date \
		--default-character-set=utf8mb4 \
		--result-file="${CONTAINER_DIR}/mysql.sql"

	DUMP="${HOST_DIR}/mysql.sql"
else
	# --format=plain rather than custom: plain text is what git can compress and
	# diff-suppress, and it restores with nothing but psql -- no pg_restore, and
	# so no version-skew between the client that wrote it and the one that reads
	# it. --no-owner and --no-privileges let it restore as whatever role the
	# consuming stack happens to connect as.
	docker compose exec -T "$SERVICE" pg_dump \
		--username="$DB_USER" \
		--dbname="$DB_NAME" \
		--format=plain \
		--no-owner \
		--no-privileges \
		--encoding=UTF8 \
		--quote-all-identifiers \
		--file="${CONTAINER_DIR}/postgres.sql"

	DUMP="${HOST_DIR}/postgres.sql"
fi

[ -s "$DUMP" ] || die "the dump at ${DUMP} is empty"

# A carriage return in a dump means it came back over a TTY. Catch it here
# rather than at restore time on somebody else's machine.
#
# Counted with tr rather than matched with grep: a grep pattern written as
# $'\r' is quoted differently by different shells, and one that silently
# becomes the empty pattern matches every line -- a check that always fires is
# no more useful than one that never does.
if [ "$(tr -dc '\r' < "$DUMP" | wc -c)" -gt 0 ]; then
	die "${DUMP} contains carriage returns -- it was streamed through a TTY, not written to the bind mount"
fi

log "database: $(du -h "$DUMP" | cut -f1)"

# ------------------------------------------------------------------- the files
# One bundle per engine, not one per profile: the two forums are built
# separately, so their attachment sets and installed.list differ, and a shared
# file would leave whichever engine was dumped first with a checksum in the
# manifest that no longer matches what is on disk.
#
# avatars/ is deliberately absent: it is tracked, shipped content, identical in
# 2.1 and 3.0, so carrying it would only make the artifact bigger.
FILES_NAME="files-${SMF_TYPE}.tgz"

paths='attachments custom_avatar'

if docker compose exec -T web test -f Packages/installed.list; then
	paths="${paths} Packages/installed.list"
fi

docker compose exec -T -w /var/www/html web \
	tar czf ".docker/baseline/artifacts/${VERSION}/${PROFILE}/${FILES_NAME}" $paths

log "files:    $(du -h "${HOST_DIR}/${FILES_NAME}" | cut -f1)"

# ---------------------------------------------------------------- the manifest
docker compose exec -T web php .docker/baseline/manifest.php \
	--version="$VERSION" \
	--profile="$PROFILE" \
	--engine="$SMF_TYPE"

log "wrote ${VERSION}/${PROFILE}"
