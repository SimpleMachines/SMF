#!/usr/bin/env bash
# Compares two captures made by .dev/capture-pages.sh and lists the pages that
# differ. Exits non-zero if any do.
#
#   .dev/compare-pages.sh before after              byte for byte, after masking
#   .dev/compare-pages.sh before after --rendered   as a browser shows them
#   .dev/compare-pages.sh before after -v           and where each page differs
#
# What is masked, and what --rendered lets through, is described at the top of
# .dev/plates/compare.php.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

parse_runner_args "$@"

LABELS=()
FLAGS=()

while [ $# -gt 0 ]; do
	case "$1" in
		--docker|--local) shift ;;
		-h|--help) sed -n '2,11p' "${BASH_SOURCE[0]}"; exit 0 ;;
		-*) FLAGS+=("$1"); shift ;;
		*) LABELS+=("$1"); shift ;;
	esac
done

[ ${#LABELS[@]} -eq 2 ] || die "name two captures, for example: $(basename -- "$0") before after"

run_php .dev/plates/compare.php "logs/pages-${LABELS[0]}" "logs/pages-${LABELS[1]}" ${FLAGS[@]+"${FLAGS[@]}"}
