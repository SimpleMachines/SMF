#!/usr/bin/env bash
# Shared settings and helpers for the .docker scripts. Sourced, never run.
#
# Host-side scripts (reset.sh, install-forum.sh, use-engine.sh) source this from
# wherever the caller happens to be standing; everything below resolves paths
# for itself rather than assuming a working directory.
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
DOCKER_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
BOARD_DIR=$(cd -- "$DOCKER_DIR/.." && pwd)

# Where use-engine.sh keeps each engine's Settings.php. Gitignored: these hold
# generated secrets and a machine-specific board URL.
SETTINGS_DIR="$DOCKER_DIR/settings"

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

# Container-internal host and port for an engine. Not the host-side ports in
# compose.yaml: these are what Settings.php has to contain.
engine_server() {
	case "$(engine_smf_type "$1")" in
		mysql)      echo "${SMF_MYSQL_SERVER:-mysql}" ;;
		postgresql) echo "${SMF_POSTGRES_SERVER:-postgres}" ;;
		*) return 1 ;;
	esac
}

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
	local engine service
	engine=$(engine_smf_type "$1") || return 1
	service=$(engine_service "$1")

	if [ "$engine" = 'mysql' ]; then
		docker compose exec -T -e MYSQL_PWD="$DB_PASSWORD" "$service" \
			mysql -u"$DB_USER" -D "$DB_NAME" -N -B -e \
			"SELECT value FROM ${DB_PREFIX}settings WHERE variable = 'smfVersion';" 2>/dev/null
	else
		docker compose exec -T "$service" \
			psql -U "$DB_USER" -d "$DB_NAME" -tAX -c \
			"SELECT value FROM ${DB_PREFIX}settings WHERE variable = 'smfVersion';" 2>/dev/null
	fi
}
