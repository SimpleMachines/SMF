#!/usr/bin/env bash
# Installs the forum without a browser.
#
#   .docker/install-forum.sh --engine mysql
#   .docker/install-forum.sh --engine postgresql
#   .docker/install-forum.sh --engine both
#
# SMF 3.0's installer is CLI-native: Maintenance::parseCliArguments() turns
# --name=value into $_POST, and Maintenance::execute() then runs every step in
# one process, stopping at the first that still needs input. So unlike 2.1,
# which needs a five-request curl driver, this is two invocations:
#
#   pass 1  Welcome -> Writable -> Database settings -> Forum settings
#           -> Database population, which builds the schema and then stops
#   pass 2  the same again, plus --pop_done, which walks straight past the
#           population report into the admin account and finalise
#
# databasePopulation() always stops the first time even though it succeeded: it
# pauses so a human can read its "N duplicate tables ignored" report, and the
# form's pop_done field is the short-circuit that skips it. Passing pop_done on
# pass 1 would skip building the schema altogether, which is why this is two
# passes and not one.
#
# Every step re-runs on pass 2. They are all idempotent given the same input --
# the settings steps rewrite the same values, and adminAccount() stops if an
# administrator already exists.
#
# Runs on the host.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINE=''
PIN_SECRETS=0
FORCE=0

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--pin-secrets) PIN_SECRETS=1; shift ;;
		--force) FORCE=1; shift ;;
		-h|--help) sed -n '2,27p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -n "$ENGINE" ] || die 'need --engine mysql|postgresql|both'
ENGINES=$(engine_list "$ENGINE") || die "unknown engine: $ENGINE"

cd "$BOARD_DIR"

# The installer's own name for each engine, which is the key of the array it
# builds from the drivers it found. These are capitalised, and a lowercase
# db_type is rejected outright -- so they are spelled exactly as the installer
# spells them rather than reusing the SMF type.
installer_db_type() {
	case "$1" in
		mysql)      echo 'MySQL' ;;
		postgresql) echo 'PostgreSQL' ;;
		*) return 1 ;;
	esac
}

install_one() {
	local smf_type="$1" db_type server port args

	db_type=$(installer_db_type "$smf_type")
	server=$(engine_server "$smf_type")
	port=$(engine_port "$smf_type")

	if [ "$FORCE" -eq 0 ] && [ -n "$(installed_version "$smf_type" || true)" ]; then
		log "${smf_type}: already installed (SMF $(installed_version "$smf_type")), nothing to do"

		return 0
	fi

	log "${smf_type}: resetting"
	"$DOCKER_DIR/reset.sh" --engine "$smf_type" >/dev/null

	args=(
		--contbutt=1
		--db_type="$db_type"
		--db_server="$server"
		--db_port="$port"
		--db_name="$DB_NAME"
		--db_user="$DB_USER"
		--db_passwd="$DB_PASSWORD"
		--db_prefix="$DB_PREFIX"
		--boardurl="$SMF_BOARDURL"
		--mbname="$SMF_MBNAME"
		--username="$SMF_ADMIN_USER"
		--email="$SMF_ADMIN_EMAIL"
		--server_email="$SMF_ADMIN_EMAIL"
		--password1="$SMF_ADMIN_PASS"
		--password2="$SMF_ADMIN_PASS"
	)

	# reset.sh does not return until the entrypoint has staged this, so its
	# absence means something went wrong there rather than here. Worth saying so:
	# without it php reports "Could not open input file: install.php", which reads
	# like a broken script rather than a forum that was never made installable.
	docker compose exec -T web test -f install.php \
		|| die "${smf_type}: install.php is not staged, so there is nothing to run (docker compose logs web)"

	log "${smf_type}: building the schema"
	docker compose exec -T web php install.php "${args[@]}" >/dev/null

	log "${smf_type}: creating the administrator and finalising"
	docker compose exec -T web php install.php "${args[@]}" --pop_done=1 >/dev/null

	local version
	version=$(installed_version "$smf_type" || true)

	[ -n "$version" ] || die "${smf_type}: the installer finished but the forum is not installed"

	# The installer tells you to delete this and cannot do it itself: its ?delete
	# link is a GET, and command line arguments only ever reach $_POST. Leaving it
	# is not cosmetic - Settings.php redirects every request back into the
	# installer while it is there, and SMF puts a "MAJOR SECURITY RISK: you have
	# not removed install.php" box on every page it shows an administrator.
	#
	# Safe to delete even though a reinstall needs it again: install_one() always
	# calls reset.sh first, and reset.sh clears Settings.php and waits for the
	# entrypoint to put a fresh copy back before returning.
	rm -f install.php

	log "${smf_type}: installed SMF ${version}"

	if [ "$PIN_SECRETS" -eq 1 ]; then
		pin_secrets
	fi

	save_settings "$smf_type"
}

# ForumSettings() generates auth_secret and image_proxy_secret with
# random_bytes() and stores them nowhere but Settings.php, so the two engines
# end up with different ones and a login cookie stops being valid the moment
# use-engine.sh switches. Pinning them leaves the database as the only thing
# that differs between the two installs.
#
# The cookie name needs no such help: createCookieName() is a crc32 of the
# database name and prefix, which are the same on both.
#
# Dev-only values for a throwaway forum, published here deliberately. Never
# reuse them anywhere real.
pin_secrets() {
	log 'pinning auth_secret and image_proxy_secret'

	# The values have to be handed over with -e. Exporting them on the host does
	# nothing: docker compose exec starts a fresh environment, so getenv() came
	# back empty and this wrote two empty secrets over the generated ones.
	docker compose exec -T \
		-e PIN_AUTH_SECRET="$PIN_AUTH_SECRET" \
		-e PIN_IMAGE_PROXY_SECRET="$PIN_IMAGE_PROXY_SECRET" \
		web php -r '
		define("SMF", 1);
		define("SMF_SETTINGS_FILE", "/var/www/html/Settings.php");
		define("SMF_SETTINGS_BACKUP_FILE", "/var/www/html/Settings_bak.php");
		require_once "/var/www/html/index.php";

		$auth = (string) getenv("PIN_AUTH_SECRET");
		$proxy = (string) getenv("PIN_IMAGE_PROXY_SECRET");

		if ($auth === "" || $proxy === "") {
			fwrite(STDERR, "pin-secrets: the secrets did not reach the container\n");
			exit(1);
		}

		exit(SMF\Config::updateSettingsFile([
			"auth_secret" => $auth,
			"image_proxy_secret" => $proxy,
		]) ? 0 : 1);
	' >/dev/null
}

# Keep each engine's Settings.php so use-engine.sh can put it back without a
# reinstall. Gitignored: generated secrets and a machine-specific board URL.
save_settings() {
	local smf_type="$1"

	mkdir -p "$SETTINGS_DIR"
	cp Settings.php "$SETTINGS_DIR/Settings.${smf_type}.php"
	cp Settings_bak.php "$SETTINGS_DIR/Settings_bak.${smf_type}.php"

	log "${smf_type}: settings saved to .docker/settings/"
}

PIN_AUTH_SECRET="${PIN_AUTH_SECRET:-0b6e5f3c1a94d27e8f5b0c3a76d1e94f2b8c5a03e7d146f9b2c8a501d3e7f4c69}"
PIN_IMAGE_PROXY_SECRET="${PIN_IMAGE_PROXY_SECRET:-7f2a9c4e0b6d18a35c92}"

# Sequential on purpose. Settings.php pins one $db_type and Db::load() returns
# the connection it already made, so only one engine can be live at a time --
# "both" is a chain, never two connections.
for smf_type in $ENGINES; do
	install_one "$smf_type"
done

# Leave the first engine of a "both" run active rather than whichever happened
# to go last, so the result does not depend on the order.
FIRST_ENGINE="${ENGINES%% *}"
"$DOCKER_DIR/use-engine.sh" "$FIRST_ENGINE" >/dev/null

log "active engine: ${FIRST_ENGINE} -- ${SMF_BOARDURL} (${SMF_ADMIN_USER} / ${SMF_ADMIN_PASS})"
