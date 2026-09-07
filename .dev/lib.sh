#!/usr/bin/env bash
# Shared settings and helpers for the .dev scripts. Sourced, never run.
#
# Host-side scripts (reset.sh, install-forum.sh, use-engine.sh) source this from
# wherever the caller happens to be standing; everything below resolves paths
# for itself rather than assuming a working directory.
#
# Every script that sources this can work two ways, and $SMF_RUNNER decides
# which. See "the runner" below.
#
# Everything defined here is consumed by the scripts that source this file, and
# a linter reading it on its own cannot see any of those uses -- hence the
# blanket disable below. Keep it on its own, with nothing after it that starts
# with the linter's name, or the following line gets parsed as a directive too.
#
# shellcheck disable=SC2034

# Git Bash on Windows rewrites anything that looks like a Unix path before
# handing it to a program, so a container-side path like /var/www/html/... is
# silently turned into C:/Program Files/Git/var/www/html/... and the command
# fails with "Could not open input file". These two switch that off. They mean
# nothing on Linux and macOS.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

# Repository root, regardless of where the caller was standing.
DEV_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
BOARD_DIR=$(cd -- "$DEV_DIR/.." && pwd)

# Where use-engine.sh keeps each engine's Settings.php. Gitignored: these hold
# generated secrets and a machine-specific board URL.
SETTINGS_DIR="$DEV_DIR/settings"

# ------------------------------------------------------------------ the runner
# Where the forum actually runs. 'local' uses the PHP on this machine and a
# database it can already reach; 'docker' uses the compose stack in .docker/.
#
# Local is the default because it is the one that needs nothing installed beyond
# what SMF itself requires, and because it is the only one available on a
# machine without Docker. Pass --docker, or set this in .env, for the stack.
SMF_RUNNER="${SMF_RUNNER:-local}"

# Reads the --docker/--local flags every script accepts. Call it with "$@"
# before the script's own option loop, which then ignores them.
parse_runner_args() {
	local arg

	for arg in "$@"; do
		case "$arg" in
			--docker) SMF_RUNNER='docker' ;;
			--local)  SMF_RUNNER='local' ;;
		esac
	done

	case "$SMF_RUNNER" in
		docker|local) ;;
		*) die "SMF_RUNNER must be 'local' or 'docker', not '${SMF_RUNNER}'" ;;
	esac
}

# Strips those flags out of a script's arguments, so an option loop that does
# not know about them does not have to.
without_runner_args() {
	local arg

	for arg in "$@"; do
		case "$arg" in
			--docker|--local) ;;
			*) echo "$arg" ;;
		esac
	done
}

is_docker() { [ "$SMF_RUNNER" = 'docker' ]; }

# For the scripts that orchestrate containers and cannot mean anything else.
# They set SMF_RUNNER=docker themselves, so this only fires if someone overrode
# it in the environment.
require_docker() {
	is_docker || die "$(basename -- "$0") only works against the compose stack; unset SMF_RUNNER or set it to 'docker'"
}

# ---------------------------------------------------------------- credentials
# These match compose.yaml's defaults. Override them in the environment if you
# changed them in .env.
DB_NAME="${DB_NAME:-smf}"
DB_USER="${DB_USER:-smf}"
DB_PASSWORD="${DB_PASSWORD:-smf}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-smf}"
DB_PREFIX="${DB_PREFIX:-smf_}"

WEB_PORT="${WEB_PORT:-8080}"
SMF_BOARDURL="${SMF_BOARDURL:-http://localhost:${WEB_PORT}}"
SMF_MBNAME="${SMF_MBNAME:-SMF Dev}"

# The administrator the installer creates. Dev-only values for a throwaway
# forum; never reuse them anywhere real.
SMF_ADMIN_USER="${SMF_ADMIN_USER:-admin}"
SMF_ADMIN_PASS="${SMF_ADMIN_PASS:-password}"
# example.com is reserved by RFC 2606, so this can never reach a real inbox.
# SMF's validator rejects dotless domains, so 'admin@localhost' is not an option.
SMF_ADMIN_EMAIL="${SMF_ADMIN_EMAIL:-admin@example.com}"

# --------------------------------------------------------------------- output
log()  { printf '[smf-dev] %s\n' "$*"; }
warn() { printf '[smf-dev] %s\n' "$*" >&2; }
die()  { printf '[smf-dev] error: %s\n' "$*" >&2; exit 1; }

# Engine name normalisation. Everything downstream uses either the SMF type
# ('mysql' / 'postgresql') or the compose service name ('mysql' / 'postgres'),
# and mixing them up is an easy way to waste an afternoon.
engine_smf_type() {
	case "$1" in
		mysql|mysqli|mariadb)      echo 'mysql' ;;
		postgres|postgresql|pgsql) echo 'postgresql' ;;
		*) return 1 ;;
	esac
}

engine_service() {
	case "$1" in
		mysql|mysqli|mariadb)      echo 'mysql' ;;
		postgres|postgresql|pgsql) echo 'postgres' ;;
		*) return 1 ;;
	esac
}

# Where an engine answers, as Settings.php has to spell it. Under docker that is
# the compose service on the container network, not the host-side ports in
# compose.yaml; locally it is the loopback address. Both are overridable, which
# is how a database somewhere else gets named.
engine_server() {
	local fallback_mysql='127.0.0.1' fallback_postgres='127.0.0.1'

	if is_docker; then
		fallback_mysql='mysql'
		fallback_postgres='postgres'
	fi

	case "$(engine_smf_type "$1")" in
		mysql)      echo "${SMF_MYSQL_SERVER:-$fallback_mysql}" ;;
		postgresql) echo "${SMF_POSTGRES_SERVER:-$fallback_postgres}" ;;
		*) return 1 ;;
	esac
}

# The same either way: the container network and a stock local install both use
# the default ports. The published 3307 and 5433 in compose.yaml are only how
# the host reaches the containers, which is not what goes in Settings.php.
engine_port() {
	case "$(engine_smf_type "$1")" in
		mysql)      echo "${SMF_MYSQL_PORT:-3306}" ;;
		postgresql) echo "${SMF_POSTGRES_PORT:-5432}" ;;
		*) return 1 ;;
	esac
}

# Expands "both" into the engines to act on, in the order they run. Only one
# engine can be live at a time -- Settings.php pins $db_type and Db::load()
# early-returns once the connection exists -- so "both" is a sequential chain,
# never two connections.
engine_list() {
	case "$1" in
		both|all) echo 'mysql postgresql' ;;
		*) engine_smf_type "$1" ;;
	esac
}

# The installed version for one engine, empty if the forum is not installed.
# Asks the database directly rather than trusting the presence of a file:
# Settings.php exists from the moment the entrypoint writes it, long before
# there is a forum behind it.
installed_version() {
	db_sql "$1" "SELECT value FROM ${DB_PREFIX}settings WHERE variable = 'smfVersion';" 2>/dev/null
}

# ----------------------------------------------------------------- php and sql
# Which PHP to run things with locally. Not used under docker, where it is
# whatever the image ships.
PHP_BIN="${PHP_BIN:-php}"

# Runs php with some environment set, as `run_php_env VAR=value -- script args`.
#
# The variables have to be handed over rather than exported: docker compose exec
# starts a fresh environment, so an exported value never reaches the container
# and the script on the other end reads an empty string.
run_php_env() {
	local -a envs=() flags=()
	local entry

	while [ $# -gt 0 ] && [ "$1" != '--' ]; do
		envs+=("$1")
		shift
	done

	shift

	if is_docker; then
		for entry in ${envs[@]+"${envs[@]}"}; do
			flags+=(-e "$entry")
		done

		docker compose exec -T ${flags[@]+"${flags[@]}"} web php "$@"

		return
	fi

	# Every script here is written against the board directory, and the local
	# runner has no working directory of its own to inherit.
	( cd "$BOARD_DIR" && env ${envs[@]+"${envs[@]}"} "$PHP_BIN" "$@" )
}

run_php() {
	run_php_env -- "$@"
}

# Runs an arbitrary command where the forum lives, rather than only php.
#
# A leading `php` becomes $PHP_BIN locally, so a call site reads the same in
# both modes. It also means the vendor binaries can be invoked as
# `run_cmd php vendor/bin/whatever`, which works on Windows, where their
# shebang line does not.
run_cmd() {
	if is_docker; then
		docker compose exec -T web "$@"

		return
	fi

	if [ "${1:-}" = 'php' ]; then
		shift

		( cd "$BOARD_DIR" && "$PHP_BIN" "$@" )

		return
	fi

	( cd "$BOARD_DIR" && "$@" )
}

# The board directory as the PHP started by run_php sees it, which is not
# $BOARD_DIR under docker. Anything handing an absolute path to that PHP has to
# go through this.
run_board_dir() {
	if is_docker; then
		echo '/var/www/html'
	else
		echo "$BOARD_DIR"
	fi
}

# Runs SQL as the forum's own database user and prints any rows, tab separated.
#
# Under docker that is the client inside the database container. Locally it is
# .dev/db.php, which goes through mysqli or pgsql: those are what SMF itself
# connects with, so a machine that can run the forum can already do this, while
# the command line clients would be a dependency nothing else here needs.
db_sql() {
	local engine service sql
	engine=$(engine_smf_type "$1") || return 1
	service=$(engine_service "$1")
	sql="$2"

	if is_docker; then
		if [ "$engine" = 'mysql' ]; then
			docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
				mysql -u"$DB_USER" -D "$DB_NAME" -N -B -e "$sql"
		else
			docker compose exec -T "$service" \
				psql -U "$DB_USER" -d "$DB_NAME" -tAX -c "$sql"
		fi

		return
	fi

	( cd "$BOARD_DIR" && "$PHP_BIN" .dev/db.php \
		--engine "$engine" \
		--server "$(engine_server "$engine")" \
		--port "$(engine_port "$engine")" \
		--database "$DB_NAME" \
		--user "$DB_USER" \
		--password "$DB_PASSWORD" \
		--sql "$sql" )
}

# Empties an engine's database, which is the one thing the two runners cannot do
# the same way.
#
# Under docker the database is ours to destroy, so MySQL gets a real DROP
# DATABASE as root, which restores the character set along with everything else.
# Locally there is no reason to assume a root account exists or that the forum's
# user may create databases, so the tables go and the database itself stays.
# PostgreSQL has always worked the second way: dropping the schema takes the
# tables, sequences and functions with it, and smf owns it.
db_empty() {
	local engine service
	engine=$(engine_smf_type "$1") || return 1
	service=$(engine_service "$1")

	if [ "$engine" = 'postgresql' ]; then
		if is_docker; then
			docker compose exec -T "$service" psql -v ON_ERROR_STOP=1 -q -U "$DB_USER" -d "$DB_NAME" -c '
				DROP SCHEMA IF EXISTS public CASCADE;
				CREATE SCHEMA public;
			' >/dev/null
		else
			db_sql "$engine" 'DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;' >/dev/null
		fi

		return
	fi

	if is_docker; then
		# As root: the smf user has rights on the smf database but cannot drop
		# and recreate it. utf8mb4 matches what compose.yaml asks the server for
		# and what SMF's own DDL emits.
		docker compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$service" mysql -uroot -e "
			DROP DATABASE IF EXISTS \`${DB_NAME}\`;
			CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4;
			GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
		"

		return
	fi

	# The names are collected first and the DROP is built from them here, rather
	# than in SQL: doing it in one statement means GROUP_CONCAT, whose result is
	# cut off at group_concat_max_len -- 1024 bytes by default, which SMF's 70-odd
	# tables pass comfortably. That truncation is silent, so it produces a reset
	# that drops nothing and reports success.
	local tables
	tables=$(db_sql "$engine" "
		SELECT CONCAT('\`', table_name, '\`') FROM information_schema.tables
		WHERE table_schema = DATABASE();
	" | tr -d '\r' | paste -sd, -)

	[ -n "$tables" ] || return 0

	# Foreign keys are off for the duration: the order the tables come back in is
	# not an order they can be dropped in.
	db_sql "$engine" "
		SET FOREIGN_KEY_CHECKS = 0;
		DROP TABLE ${tables};
		SET FOREIGN_KEY_CHECKS = 1;
	" >/dev/null
}

# ------------------------------------------------------- the local environment
# Says what is missing before something else fails in a way that reads like a
# broken script rather than a machine that is not set up for this.
#
# The engine is optional: without one this only checks that there is a php to
# run, which is all a caller that has not chosen an engine yet can know.
require_local_deps() {
	local engine extension

	is_docker && return 0

	command -v "$PHP_BIN" >/dev/null 2>&1 \
		|| die "no php on PATH. Set PHP_BIN to one, or pass --docker to use the compose stack"

	[ -n "${1:-}" ] || return 0

	engine=$(engine_smf_type "$1") || return 1

	if [ "$engine" = 'mysql' ]; then
		extension='mysqli'
	else
		extension='pgsql'
	fi

	"$PHP_BIN" -r "exit(extension_loaded('${extension}') ? 0 : 1);" \
		|| die "${PHP_BIN} has no ${extension} extension, which SMF needs for ${engine}. Enable it, or pass --docker to use the compose stack"
}

# Puts a Settings.php and an install.php in place, so the installer has somewhere
# to write and something to run.
#
# Under docker the entrypoint does this on boot, so the job is to restart the web
# container pointed at the right engine and wait for it. Locally there is no
# entrypoint, so the same work happens here. Keep the two in step:
# .docker/php/entrypoint.sh, under "installer bits".
stage_installer() {
	local smf_type path waited

	smf_type=$(engine_smf_type "$1") || return 1

	if is_docker; then
		SMF_DB_TYPE="$smf_type" docker compose up -d web >/dev/null

		# The entrypoint waits for the database before it writes anything, so
		# give it a moment to get there rather than racing whatever runs next.
		for waited in $(seq 1 60); do
			if docker compose exec -T web test -f install.php 2>/dev/null; then
				return 0
			fi

			sleep 1
		done

		die 'timed out waiting for the entrypoint to stage install.php (docker compose logs web)'
	fi

	sed \
		-e "s|^\$db_type = 'mysql';|\$db_type = '${smf_type}';|" \
		-e "s|^\$db_port = 0;|\$db_port = $(engine_port "$smf_type");|" \
		-e "s|^\$db_server = 'localhost';|\$db_server = '$(engine_server "$smf_type")';|" \
		-e "s|^\$db_name = 'smf';|\$db_name = '${DB_NAME}';|" \
		-e "s|^\$db_user = 'root';|\$db_user = '${DB_USER}';|" \
		-e "s|^\$db_passwd = '';|\$db_passwd = '${DB_PASSWORD}';|" \
		-e "s|^\$boardurl = 'http://127.0.0.1/smf';|\$boardurl = '${SMF_BOARDURL}';|" \
		"$BOARD_DIR/other/Settings.php" > "$BOARD_DIR/Settings.php"

	cp "$BOARD_DIR/other/Settings_bak.php" "$BOARD_DIR/Settings_bak.php"
	cp "$BOARD_DIR/other/install.php" "$BOARD_DIR/install.php"

	# The installer refuses to continue unless all of these are writable, and the
	# forum needs them at runtime too. They are all in the checkout already,
	# except the cache's copy of db_last_error.php.
	for path in attachments avatars custom_avatar cache Packages Smileys Themes Languages; do
		[ -e "$BOARD_DIR/$path" ] || mkdir -p "$BOARD_DIR/$path"
	done

	[ -f "$BOARD_DIR/cache/db_last_error.php" ] || cp "$BOARD_DIR/db_last_error.php" "$BOARD_DIR/cache/db_last_error.php" 2>/dev/null || true
}

# ----------------------------------------------------------------- the server
# Under docker Apache is already serving the checkout. Locally the HTTP tests
# need something answering on the board URL, and PHP's own server is enough: SMF
# routes on the query string and on PATH_INFO, and there is no .htaccess at the
# root, so nothing here wants mod_rewrite. It is not Apache, though, which is
# why the compose stack stays the closer thing to production.
SERVE_PID=''

serve_start() {
	local host port waited

	is_docker && return 0

	# Idempotent, so a caller looping over both engines can ask on each pass
	# without having to remember whether it already has one.
	[ -z "$SERVE_PID" ] || return 0

	host=$(printf '%s' "$SMF_BOARDURL" | sed -E 's|^[a-z]+://||; s|[:/].*$||')
	port=$(printf '%s' "$SMF_BOARDURL" | sed -nE 's|^[a-z]+://[^:/]+:([0-9]+).*$|\1|p')
	port="${port:-80}"

	# A single-threaded server deadlocks on a page that asks itself for
	# something. Nothing in the suite does that, but the cost of not finding out
	# the hard way is one variable. Windows has no forking and ignores it.
	PHP_CLI_SERVER_WORKERS=4 "$PHP_BIN" -d memory_limit=512M \
		-S "${host}:${port}" -t "$BOARD_DIR" >/dev/null 2>&1 &

	SERVE_PID=$!

	for waited in $(seq 1 30); do
		# Asked through PHP rather than curl, which is not a binary every machine
		# that can run the forum has.
		if "$PHP_BIN" -r "exit(@fsockopen('${host}', ${port}, \$e, \$s, 2) ? 0 : 1);" 2>/dev/null; then
			log "serving ${BOARD_DIR} at ${SMF_BOARDURL} (pid ${SERVE_PID})"

			return 0
		fi

		# Nothing is going to start listening if the process has already gone.
		kill -0 "$SERVE_PID" 2>/dev/null \
			|| die "the built-in server exited immediately; is ${host}:${port} already in use?"

		sleep 1
	done

	die "the built-in server did not answer on ${SMF_BOARDURL} after ${waited}s"
}

serve_stop() {
	[ -n "$SERVE_PID" ] || return 0

	kill "$SERVE_PID" 2>/dev/null || true
	wait "$SERVE_PID" 2>/dev/null || true

	SERVE_PID=''
}
