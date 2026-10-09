#!/usr/bin/env bash
# Saves the HTML of every page the template snapshot suite renders, on both
# engines, plus a few responses the suite does not cover, so that two versions
# of the templates can be compared page by page.
#
#   .dev/capture-pages.sh before                  into logs/pages-before/
#   ... change the templates ...
#   .dev/capture-pages.sh after
#   .dev/compare-pages.sh before after -v
#
# The extra responses, fetched as a guest from $SMF_BOARDURL: ssi_examples.php,
# the statistics centre's XML, the keep-alive and a help popup, and
# .dev/plates/probe.php, which calls the template helpers the way a mod does.
#
# Capture both sides on the same forum, one straight after the other: the
# pages show counts and recent activity, and a forum that has been used in
# between reads differently for reasons that have nothing to do with the
# templates.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

parse_runner_args "$@"

LABEL=''

while [ $# -gt 0 ]; do
	case "$1" in
		--docker|--local) shift ;;
		-h|--help) sed -n '2,19p' "${BASH_SOURCE[0]}"; exit 0 ;;
		-*) die "unknown option: $1" ;;
		*) LABEL="$1"; shift ;;
	esac
done

[ -n "$LABEL" ] || die "name the capture, for example: $(basename -- "$0") before"

cd "$BOARD_DIR"

out="logs/pages-$LABEL"
rm -rf "$out"

SMF_SNAPSHOT_RAW="$out" "$DEV_DIR/test.sh" "--$SMF_RUNNER" --engine both --testsuite snapshot

mkdir -p "$out/extras"
cp .dev/plates/probe.php ./plates-probe.php
trap 'rm -f ./plates-probe.php' EXIT

fetch() {
	curl -sS -o "$out/extras/$1.html" "$SMF_BOARDURL/$2"
}

fetch ssi_examples 'ssi_examples.php'
fetch probe 'plates-probe.php'
fetch stats_xml 'index.php?action=stats;xml;expand=202001'
fetch stats_xml_now "index.php?action=stats;xml;expand=$(date +%Y%m)"
fetch keepalive 'index.php?action=keepalive'
fetch help_popup 'index.php?action=helpadmin;help=cal_enabled'

log "captured $(find "$out" -name '*.html' | wc -l | tr -d ' ') pages in $out"
