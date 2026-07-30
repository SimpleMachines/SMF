#!/usr/bin/env bash
# Shared settings and helpers for the baseline scripts. Sourced, never run.
#
# Host-side scripts (make-baseline.sh, reset.sh, dump.sh, restore.sh) source
# this from the repository root. In-container scripts (install-forum.sh,
# populate.sh) source it too, and only use the parts that do not touch docker.
#
# shellcheck disable=SC2034
# Everything here is consumed by the scripts that source this file, which
# shellcheck cannot see from inside it.

# Git Bash on Windows rewrites anything that looks like a Unix path before
# handing it to a program -- so a container-side path like /artifacts/... is
# silently turned into C:/Program Files/Git/artifacts/..., and mysqldump writes
# its output somewhere that does not exist. These two variables switch that off.
# They mean nothing on Linux and macOS.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'

# Repository root, regardless of where the caller was standing.
BASELINE_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
BOARD_DIR=$(cd -- "$BASELINE_DIR/../.." && pwd)
ARTIFACT_DIR="$BASELINE_DIR/artifacts"

# --------------------------------------------------------------------- naming
# Bumped by hand whenever a regeneration should produce a new artifact set
# rather than overwrite the old one.
BASELINE_VERSION="${BASELINE_VERSION:-2.1.7-1}"
BASELINE_PROFILE="${BASELINE_PROFILE:-small}"

# ---------------------------------------------------------------- credentials
# These end up inside the committed dumps, so changing them means regenerating.
BASELINE_ADMIN_USER="${BASELINE_ADMIN_USER:-admin}"
BASELINE_ADMIN_PASS="${BASELINE_ADMIN_PASS:-baseline}"
# example.com is reserved by RFC 2606, so this can never reach a real inbox even
# if a restored baseline is pointed at a live mail server by accident. SMF's
# validator rejects dotless domains, so 'admin@localhost' is not an option.
BASELINE_ADMIN_EMAIL="${BASELINE_ADMIN_EMAIL:-admin@example.com}"

DB_NAME="${DB_NAME:-smf}"
DB_USER="${DB_USER:-smf}"
DB_PASSWORD="${DB_PASSWORD:-smf}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-smf}"
DB_PREFIX="${DB_PREFIX:-smf_}"

# ------------------------------------------------------------- pinned secrets
# ForumSettings() generates both of these with random_bytes() at install time,
# and neither is stored in the database. A restored dump paired with freshly
# random secrets would invalidate every login cookie, session token and TFA
# backup code in it, so the baseline pins them to fixed values. Dev-only
# credentials for a throwaway forum: never reuse them anywhere real.
BASELINE_AUTH_SECRET='0b6e5f3c1a94d27e8f5b0c3a76d1e94f2b8c5a03e7d146f9b2c8a501d3e7f4c69'
BASELINE_IMAGE_PROXY_SECRET='7f2a9c4e0b6d18a35c92'

# ------------------------------------------------------------- size profiles
# Targets handed to Populate.php, whose own defaults (270k members, 3M
# messages) are far too large for an artifact that lives in git.
#
# Fields, in order: categories boards membergroups members topics messages blockSize
#
# These are absolute targets, not amounts to add: Populate.php counts the rows
# already present (including the ones a fresh install creates) and tops them up.
#
# topics is the one approximate figure. Populate does not build topics directly:
# each message starts a new one with probability topics/messages, so the result
# lands near the target rather than exactly on it. It still controls the shape
# of the forum -- the ratio is what decides how long the average thread is.
#
# membergroups must be >= 8. Populate.php assigns each member to
# `rand(8, <membergroups max>)`, and a fresh 2.1 install already occupies group
# ids 1-8, so a smaller number means rand()'s min exceeds its max -- a ValueError
# on PHP 8.
#
# tiny and small are committed. medium and large are generated on demand and
# stay out of git (see .gitignore).
baseline_profile() {
	case "$1" in
		tiny)   echo '3 8 8 50 150 600 500' ;;
		small)  echo '6 24 24 400 1200 6000 1000' ;;
		medium) echo '10 60 40 2000 8000 40000 2000' ;;
		large)  echo '10 100 60 20000 40000 250000 5000' ;;
		*)      return 1 ;;
	esac
}

baseline_profile_field() {
	local profile="$1" index="$2" values
	values=$(baseline_profile "$profile") || return 1
	echo "$values" | cut -d' ' -f"$index"
}

# ------------------------------------------------------------------- upstream
# https://github.com/SimpleMachines/tools -- Populate.php generates the forum
# content. It is fetched at this exact commit and checksummed rather than
# vendored: it is third-party licensed (MPL 1.1, with a BSD LoremIpsumGenerator)
# and does not belong in an SMF release branch.
#
# To bump: change the SHA, run `.docker/baseline/populate.sh --fetch-only`, and
# paste the sha256 it reports into POPULATE_SHA256.
POPULATE_COMMIT="${POPULATE_COMMIT:-ea05203b6b015792d10cdecaaa789c08e6a176f7}"
POPULATE_SHA256="${POPULATE_SHA256:-cc37b6fd44a6e00caa41fc9be64f37f8047adf60b92623cbd19a7631aa62c00f}"
POPULATE_URL_BASE='https://raw.githubusercontent.com/SimpleMachines/tools'

# --------------------------------------------------------------------- output
log()  { printf '[baseline] %s\n' "$*"; }
warn() { printf '[baseline] %s\n' "$*" >&2; }
die()  { printf '[baseline] error: %s\n' "$*" >&2; exit 1; }

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
