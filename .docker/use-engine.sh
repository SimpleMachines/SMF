#!/usr/bin/env bash
# Switches which installed forum is live, without reinstalling either.
#
#   .docker/use-engine.sh mysql
#   .docker/use-engine.sh postgresql
#
# Both database services always run, on separate volumes, so each keeps its own
# forum. What decides which one you get is Settings.php: it pins $db_type, and
# it wins over SMF_DB_TYPE. install-forum.sh files a copy per engine, and this
# puts one of them back.
#
# No container restart is needed. The entrypoint only writes Settings.php when
# there is not one, so it leaves whatever is in place alone.
#
# To throw an install away and start over, use reset.sh instead.
#
# Runs on the host.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

[ $# -eq 1 ] || die 'usage: use-engine.sh mysql|postgresql'

case "$1" in
	-h|--help) sed -n '2,16p' "${BASH_SOURCE[0]}"; exit 0 ;;
esac

SMF_TYPE=$(engine_smf_type "$1") || die "unknown engine: $1"
SAVED="$SETTINGS_DIR/Settings.${SMF_TYPE}.php"

cd "$BOARD_DIR"

[ -f "$SAVED" ] || die "no saved settings for ${SMF_TYPE} -- run .docker/install-forum.sh --engine ${SMF_TYPE}"

cp "$SAVED" Settings.php
cp "$SETTINGS_DIR/Settings_bak.${SMF_TYPE}.php" Settings_bak.php

# SMF's cache holds a serialised copy of $modSettings, which describes the
# database we are switching away from. $cache_enable defaults to 0, so there is
# usually nothing there -- but the directory also holds db_last_error.php and
# the generated CSS and JS, and clearing it costs nothing.
find cache -type f ! -name 'index.php' ! -name '.htaccess' -delete 2>/dev/null || true

VERSION=$(installed_version "$SMF_TYPE" || true)

[ -n "$VERSION" ] || warn "${SMF_TYPE} has no forum installed -- Settings.php now points at an empty database"

log "active engine: ${SMF_TYPE}${VERSION:+ (SMF ${VERSION})} -- ${SMF_BOARDURL}"
