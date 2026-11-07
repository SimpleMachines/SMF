#!/usr/bin/env bash
# Fills the forum with categories, boards, membergroups, members, topics and
# messages, using the upstream Populate.php tool.
#
#   docker compose exec -T web bash .docker/baseline/populate.sh --profile small
#   docker compose exec -T web bash .docker/baseline/populate.sh --fetch-only
#
# Populate.php lives in https://github.com/SimpleMachines/tools. It is fetched
# at a pinned commit and checksummed rather than committed here: it is
# third-party licensed (MPL 1.1, with a BSD lorem ipsum generator) and has no
# business in an SMF release branch. The download and its patched copy land in
# .docker/baseline/cache/, which is gitignored.
#
# Runs inside the web container. Needs network access the first time only.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

PROFILE="$BASELINE_PROFILE"
FETCH_ONLY=0

while [ $# -gt 0 ]; do
	case "$1" in
		--profile) PROFILE="$2"; shift 2 ;;
		--profile=*) PROFILE="${1#*=}"; shift ;;
		--fetch-only) FETCH_ONLY=1; shift ;;
		-h|--help) sed -n '2,14p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

baseline_profile "$PROFILE" >/dev/null || die "unknown profile: ${PROFILE} (tiny, small, medium, large)"

CACHE_DIR="$BASELINE_DIR/cache"
RAW="$CACHE_DIR/Populate.php"
PATCHED="$CACHE_DIR/Populate.patched.php"

mkdir -p "$CACHE_DIR"

# ---------------------------------------------------------------------- fetch
if [ ! -f "$RAW" ]; then
	url="${POPULATE_URL_BASE}/${POPULATE_COMMIT}/Populate.php"
	log "fetching Populate.php from ${POPULATE_COMMIT:0:12}"

	curl -sSfL --max-time 120 -o "$RAW" "$url" \
		|| die "could not download ${url}"
fi

actual=$(sha256sum "$RAW" | cut -d' ' -f1)

if [ "$actual" != "$POPULATE_SHA256" ]; then
	if [ "$FETCH_ONLY" -eq 1 ]; then
		# Bumping the pin: report what was downloaded so it can be pasted into
		# lib.sh, rather than refusing to say.
		warn 'checksum does not match the pin in lib.sh'
		warn "  expected: ${POPULATE_SHA256}"
		warn "  actual:   ${actual}"
		exit 1
	fi

	rm -f "$RAW"
	die "Populate.php checksum mismatch (got ${actual}). The pinned commit's content changed, which should be impossible -- investigate before re-running."
fi

log "Populate.php verified (${actual:0:12})"

[ "$FETCH_ONLY" -eq 0 ] || exit 0

# ---------------------------------------------------------------------- patch
php "$BASELINE_DIR/patch-populate.php" "$RAW" "$PATCHED"

# ----------------------------------------------------------------------- run
read -r categories boards membergroups members topics messages block_size \
	<<<"$(baseline_profile "$PROFILE")"

log "profile ${PROFILE}"

php "$BASELINE_DIR/run-populate.php" \
	"--categories=${categories}" \
	"--boards=${boards}" \
	"--membergroups=${membergroups}" \
	"--members=${members}" \
	"--topics=${topics}" \
	"--messages=${messages}" \
	"--block-size=${block_size}"
