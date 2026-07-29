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
# SMF_DB_TYPE picks the engine. Both are running; only the one the forum is
# pointed at gets waited for and written into Settings.php.
case "${SMF_DB_TYPE:-mysql}" in
	mysql|mysqli|mariadb)
		DB_TYPE=mysql
		DB_SERVER="${SMF_MYSQL_SERVER:-mysql}"
		DB_PORT="${SMF_MYSQL_PORT:-3306}"
		;;

	postgresql|postgres|pgsql)
		DB_TYPE=postgresql
		DB_SERVER="${SMF_POSTGRES_SERVER:-postgres}"
		DB_PORT="${SMF_POSTGRES_PORT:-5432}"
		;;

	*)
		log "SMF_DB_TYPE='${SMF_DB_TYPE}' is not a type SMF supports (mysql, postgresql)"
		exit 1
		;;
esac

log "waiting for ${DB_TYPE} at ${DB_SERVER}:${DB_PORT}"

if [ "$DB_TYPE" = 'postgresql' ]; then
	until pg_isready -h "$DB_SERVER" -p "$DB_PORT" -U "$SMF_DB_USER" -q; do
		sleep 1
	done
else
	# Any answer at all means the server is listening; this deliberately does
	# not authenticate, so it works before the init scripts have finished.
	until mysqladmin ping -h "$DB_SERVER" -P "$DB_PORT" --silent >/dev/null 2>&1; do
		sleep 1
	done
fi

log "${DB_TYPE} is accepting connections"

# --------------------------------------------------------------- installer bits
# Settings.php redirects to install.php whenever install.php is present, so both
# files only get placed while the forum has not been installed yet.
if [ ! -f "$BOARD_DIR/Settings.php" ]; then
	log "generating Settings.php pre-filled for the ${DB_TYPE} service"

	sed \
		-e "s|^\$db_type = 'mysql';|\$db_type = '${DB_TYPE}';|" \
		-e "s|^\$db_port = 0;|\$db_port = ${DB_PORT};|" \
		-e "s|^\$db_server = 'localhost';|\$db_server = '${DB_SERVER}';|" \
		-e "s|^\$db_name = 'smf';|\$db_name = '${SMF_DB_NAME}';|" \
		-e "s|^\$db_user = 'root';|\$db_user = '${SMF_DB_USER}';|" \
		-e "s|^\$db_passwd = '';|\$db_passwd = '${SMF_DB_PASSWD}';|" \
		-e "s|^\$boardurl = 'http://127.0.0.1/smf';|\$boardurl = '${SMF_BOARDURL}';|" \
		-e "s|^\$mbname = 'My Community';|\$mbname = 'SMF Dev';|" \
		"$BOARD_DIR/other/Settings.php" > "$BOARD_DIR/Settings.php"

	cp "$BOARD_DIR/other/install.php" "$BOARD_DIR/install.php"

	log "installer ready -- open ${SMF_BOARDURL}/install.php"
else
	# Already installed, and Settings.php wins over SMF_DB_TYPE. Say so rather
	# than leaving someone wondering why switching the variable did nothing.
	# The installer writes this back with its own capitalisation ('PostgreSQL'),
	# so compare case-insensitively or the note fires on every restart.
	installed_type=$(sed -n "s|^\\\$db_type = '\\([^']*\\)';.*|\\1|p" "$BOARD_DIR/Settings.php" | head -n 1 | tr '[:upper:]' '[:lower:]')

	if [ -n "$installed_type" ] && [ "$installed_type" != "$DB_TYPE" ]; then
		log "note: Settings.php is installed against ${installed_type}, not ${DB_TYPE}"
		log '      to move, delete Settings.php and Settings_bak.php, then restart'
	fi
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
