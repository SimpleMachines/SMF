#!/usr/bin/env bash
# Returns things to "installable": no forum, an empty database for the chosen
# engine, and a Settings.php regenerated for it.
#
#   .dev/reset.sh --engine mysql
#   .dev/reset.sh --engine postgresql
#   .dev/reset.sh --docker --engine mysql
#
# This is also how you move an install between engines. Settings.php pins one
# engine and wins over SMF_DB_TYPE, so switching means throwing it away and
# writing a new one. To keep an install rather than discard it, use
# use-engine.sh instead.
#
# Only the chosen engine's database is touched, so a MySQL reset can never
# disturb a PostgreSQL install or vice versa.
#
# Runs on the host.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

parse_runner_args "$@"

ENGINE=''
KEEP_FILES=0

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--keep-files) KEEP_FILES=1; shift ;;
		--docker|--local) shift ;;
		-h|--help) sed -n '2,17p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

[ -n "$ENGINE" ] || die 'need --engine mysql|postgresql'
SERVICE=$(engine_service "$ENGINE") || die "unknown engine: $ENGINE"
SMF_TYPE=$(engine_smf_type "$ENGINE")

require_local_deps "$SMF_TYPE"

cd "$BOARD_DIR"

log "resetting for ${SMF_TYPE}"

# ------------------------------------------------------------------ the forum
# Stop the web container first: Apache holding a half-installed forum open while
# its database vanishes underneath produces confusing errors in the log. The
# local server belongs to whoever started it and is left alone.
if is_docker; then
	docker compose stop web >/dev/null 2>&1 || true
fi

rm -f Settings.php Settings_bak.php install.php upgrade.php

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
if is_docker; then
	docker compose up -d "$SERVICE" >/dev/null
fi

db_empty "$SMF_TYPE"

log "${SMF_TYPE} database ${DB_NAME} is empty"

# ------------------------------------------------------------------ the installer
stage_installer "$SMF_TYPE"

log 'installer staged, ready to install'
