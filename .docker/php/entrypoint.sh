#!/bin/sh
# Prepares the bind-mounted SMF checkout so the forum is ready to install/serve.
# Everything here is idempotent: it is safe to restart the container at any time.
set -eu

BOARD_DIR=/var/www/html

log() {
	echo "[smf-dev] $*"
}

# ---------------------------------------------------------------- dependencies
if [ ! -f "$BOARD_DIR/vendor/autoload.php" ]; then
	log 'vendor/ is missing, running composer install (this takes a minute the first time)'
	composer install \
		--working-dir="$BOARD_DIR" \
		--no-interaction \
		--no-progress \
		--prefer-dist \
		--ansi
fi

# ------------------------------------------------------------------- database
log "waiting for postgres at ${SMF_DB_SERVER}:${SMF_DB_PORT}"
until pg_isready -h "$SMF_DB_SERVER" -p "$SMF_DB_PORT" -U "$SMF_DB_USER" -q; do
	sleep 1
done
log 'postgres is accepting connections'

# --------------------------------------------------------------- installer bits
# Settings.php redirects to install.php whenever install.php is present, so both
# files only get placed while the forum has not been installed yet.
if [ ! -f "$BOARD_DIR/Settings.php" ]; then
	log 'generating Settings.php pre-filled for the postgres service'

	sed \
		-e "s|^\$db_type = 'mysql';|\$db_type = 'postgresql';|" \
		-e "s|^\$db_port = 0;|\$db_port = ${SMF_DB_PORT};|" \
		-e "s|^\$db_server = 'localhost';|\$db_server = '${SMF_DB_SERVER}';|" \
		-e "s|^\$db_name = 'smf';|\$db_name = '${SMF_DB_NAME}';|" \
		-e "s|^\$db_user = 'root';|\$db_user = '${SMF_DB_USER}';|" \
		-e "s|^\$db_passwd = '';|\$db_passwd = '${SMF_DB_PASSWD}';|" \
		-e "s|^\$boardurl = 'http://127.0.0.1/smf';|\$boardurl = '${SMF_BOARDURL}';|" \
		-e "s|^\$mbname = 'My Community';|\$mbname = 'SMF Dev';|" \
		"$BOARD_DIR/other/Settings.php" > "$BOARD_DIR/Settings.php"

	cp "$BOARD_DIR/other/install.php" "$BOARD_DIR/install.php"

	log "installer ready -- open ${SMF_BOARDURL}/install.php"
fi

[ -f "$BOARD_DIR/Settings_bak.php" ] || cp "$BOARD_DIR/Settings.php" "$BOARD_DIR/Settings_bak.php"

# ------------------------------------------------------------------ writability
# The installer refuses to continue unless all of these are writable, and the
# forum needs them at runtime too.
for path in \
	attachments \
	avatars \
	custom_avatar \
	cache \
	Packages \
	Smileys \
	Themes \
	Languages \
	Sources \
	Settings.php \
	Settings_bak.php \
	Languages/en_US/agreement.txt
do
	[ -e "$BOARD_DIR/$path" ] || mkdir -p "$BOARD_DIR/$path"
done

[ -f "$BOARD_DIR/cache/db_last_error.php" ] || cp "$BOARD_DIR/db_last_error.php" "$BOARD_DIR/cache/db_last_error.php" 2>/dev/null || true

# Bind mounts from the Windows host ignore chown/chmod, which is harmless. On
# Linux/macOS hosts these calls are what makes the checkout writable by Apache.
chown -R www-data:www-data \
	"$BOARD_DIR/attachments" \
	"$BOARD_DIR/avatars" \
	"$BOARD_DIR/custom_avatar" \
	"$BOARD_DIR/cache" \
	"$BOARD_DIR/Packages" \
	"$BOARD_DIR/Smileys" \
	"$BOARD_DIR/Themes" \
	"$BOARD_DIR/Languages" \
	"$BOARD_DIR/Settings.php" \
	"$BOARD_DIR/Settings_bak.php" 2>/dev/null || true

log 'ready'

exec "$@"
