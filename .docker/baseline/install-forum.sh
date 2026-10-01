#!/usr/bin/env bash
# Installs the forum without a browser.
#
#   docker compose exec -T web bash .docker/baseline/install-forum.sh
#
# SMF 2.1's installer is a plain sequence of forms with no CSRF token and no
# server-side step state: it reads the step number from ?step=, then runs steps
# in order within that one request, stopping at the first one that returns false
# (i.e. that still needs input). Five POSTs therefore carry it end to end:
#
#   step=0  Welcome -> CheckFilesWritable -> DatabaseSettings   (asks for db_*)
#   step=2  DatabaseSettings -> ForumSettings                   (asks for boardurl)
#   step=3  ForumSettings -> DatabasePopulation                 (asks for pop_done)
#   step=4  DatabasePopulation -> AdminAccount                  (asks for password1)
#   step=5  AdminAccount -> DeleteInstall                       (done)
#
# DatabasePopulation is the one step that always returns false even when it
# succeeds: it builds the schema and then pauses so a human can read its "N
# duplicate tables ignored" report. Its form posts pop_done, which is the
# short-circuit that lets the next request walk straight past it.
#
# Runs inside the web container.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

cd "$BOARD_DIR"

BASE='http://localhost/install.php'
JAR=$(mktemp)
BODY=$(mktemp)
trap 'rm -f "$JAR" "$BODY"' EXIT

# initialize_inputs() scans Themes/default/languages for Install.*.php and, when
# it finds more than one, deliberately prefers the second. release-2.1 ships
# only english, but a translator's checkout would otherwise silently produce a
# baseline in another language.
LANG_FILE='Install.english.php'

SETTINGS_TEMPLATE=''

while [ $# -gt 0 ]; do
	case "$1" in
		--settings-template) SETTINGS_TEMPLATE="$2"; shift 2 ;;
		--settings-template=*) SETTINGS_TEMPLATE="${1#*=}"; shift ;;
		-h|--help) sed -n '2,21p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -f Settings.php ] || die 'Settings.php is missing -- run .docker/baseline/reset.sh first'

DB_TYPE=$(sed -n "s|^\$db_type = '\([^']*\)';.*|\1|p" Settings.php | head -n 1)
DB_SERVER=$(sed -n "s|^\$db_server = '\([^']*\)';.*|\1|p" Settings.php | head -n 1)
BOARDURL="${SMF_BOARDURL:-http://localhost:8180}"
MB_NAME="${SMF_MBNAME:-SMF 2.1 Baseline}"

probe() { php "$BASELINE_DIR/db.php" "$@" 2>/dev/null; }

# ---------------------------------------------------------------- idempotency
if [ ! -e install.php ] && [ -n "$(probe setting smfVersion)" ]; then
	log "already installed (SMF $(probe setting smfVersion)), nothing to do"
	exit 0
fi

[ -f install.php ] || die 'install.php is not staged -- run .docker/baseline/reset.sh first'

# ------------------------------------------------------------------- stepping
# $1 step number, $2 marker that must appear in the response, rest: form fields.
step() {
	local number="$1" marker="$2" status field
	shift 2

	local args=()
	for field in "$@"; do
		args+=(--data-urlencode "$field")
	done

	status=$(curl -sS -o "$BODY" -w '%{http_code}' \
		-b "$JAR" -c "$JAR" \
		--max-time 900 \
		"${args[@]}" \
		"${BASE}?step=${number}&lang_file=${LANG_FILE}") || die "step ${number}: curl failed"

	[ "$status" = '200' ] || die "step ${number}: HTTP ${status}"

	# template_warning_divs() renders $incontext['error'] into this exact class,
	# and an errorbox is the installer's only way of saying no.
	if grep -q 'class="errorbox"' "$BODY"; then
		warn "step ${number}: the installer reported an error --"
		sed -n '/class="errorbox"/,/<\/div>/p' "$BODY" \
			| sed -e 's/<[^>]*>//g' -e 's/^[[:space:]]*//' \
			| grep -v '^$' >&2 || true
		exit 1
	fi

	if [ -n "$marker" ] && ! grep -q "$marker" "$BODY"; then
		die "step ${number}: expected the next form to ask for ${marker}, it did not"
	fi

	log "step ${number} ok"
}

log "installing against ${DB_TYPE} at ${DB_SERVER}"

# Welcome. CheckFilesWritable and DatabaseSettings run in the same request; the
# latter stops to ask which engine to use.
step 0 'name="db_type"' \
	'contbutt=1'

# Database settings. db_port is deliberately left out: install.php only writes a
# port to Settings.php when it differs from the engine default, and inside the
# compose network the ports are exactly 3306 and 5432.
step 2 'name="boardurl"' \
	"db_type=${DB_TYPE}" \
	"db_name=${DB_NAME}" \
	"db_user=${DB_USER}" \
	"db_passwd=${DB_PASSWORD}" \
	"db_server=${DB_SERVER}" \
	"db_prefix=${DB_PREFIX}" \
	'contbutt=1'

# Forum settings. dbsession and reg_mode have to travel in this request, not a
# later one: ForumSettings returning true falls straight through into
# DatabasePopulation, which reads them from the same $_POST.
#
# Not sent, on purpose:
#   compress   - leaves enableCompressedOutput off, so the restored baseline's
#                HTML can be read without gunzipping it
#   force_ssl  - there is no certificate here
#   stats      - that field registers the site with simplemachines.org. A
#                throwaway container has no business phoning home.
step 3 'name="pop_done"' \
	"boardurl=${BOARDURL}" \
	"mbname=${MB_NAME}" \
	'dbsession=1' \
	'reg_mode=0' \
	'contbutt=1'

# Acknowledge the population report. This is the only thing this request does;
# DatabasePopulation returns true immediately on seeing pop_done, and control
# passes to AdminAccount.
step 4 'name="password1"' \
	'pop_done=1' \
	'contbutt=1'

# Administrator account, immediately followed by DeleteInstall in the same
# request.
step 5 '' \
	"username=${BASELINE_ADMIN_USER}" \
	"password1=${BASELINE_ADMIN_PASS}" \
	"password2=${BASELINE_ADMIN_PASS}" \
	"email=${BASELINE_ADMIN_EMAIL}" \
	"server_email=${BASELINE_ADMIN_EMAIL}" \
	'contbutt=1'

# DeleteInstall() renders the congratulations page but deletes nothing: the
# checkbox on that page points a browser at ?delete, and that request is what
# removes install.php and the two schema files. It answers with a redirect to a
# blank image, so there is no form marker to assert on -- the file checks below
# are the real test.
curl -sS -o /dev/null -b "$JAR" -c "$JAR" --max-time 120 "${BASE}?delete" \
	|| die 'the ?delete request failed'

log 'installer deleted'

# ----------------------------------------------------------------- assertions
# Language-independent, so they hold whatever the installer rendered.
for leftover in install.php install_2-1_mysql.sql install_2-1_postgresql.sql; do
	[ ! -e "$leftover" ] || die "DeleteInstall did not remove ${leftover}"
done

version=$(probe setting smfVersion)
[ "$version" = '2.1.7' ] || die "expected smfVersion 2.1.7 in the database, found '${version}'"

members=$(probe count members)
[ "$members" = '1' ] || die "expected exactly one member after install, found ${members}"

boards=$(probe count boards)
[ "$boards" -ge 1 ] || die 'the install created no boards'

# -------------------------------------------------------------- pinned secrets
if [ -n "$SETTINGS_TEMPLATE" ]; then
	php "$BASELINE_DIR/pin-settings.php" "$BASELINE_AUTH_SECRET" "$BASELINE_IMAGE_PROXY_SECRET" "$SETTINGS_TEMPLATE" >/dev/null
else
	php "$BASELINE_DIR/pin-settings.php" "$BASELINE_AUTH_SECRET" "$BASELINE_IMAGE_PROXY_SECRET"
fi

log "installed: SMF ${version}, ${boards} board(s), admin '${BASELINE_ADMIN_USER}'"
