#!/usr/bin/env bash
# Runs what CI runs, before you push instead of after.
#
#   .docker/ci.sh              every check
#   .docker/ci.sh --full       style check over the whole tree, not just changes
#   .docker/ci.sh --fix        apply the style fixes rather than reporting them
#
# The workflows this mirrors are php.yml (sign-off, the file integrity checks,
# phplint) and php-cs-fixer.yml. phpunit.yml is included when the branch has a
# test suite on it.
#
# Every check runs even after one fails, because finding out about the second
# problem on the next push is the thing this script exists to stop.
#
# One difference worth knowing: CI lints and tests on PHP 8.4 *and* 8.5, and the
# web container is whichever PHP_VERSION built it (8.4 by default). To cover the
# other one, rebuild against it:
#
#   PHP_VERSION=8.5 docker compose up -d --build web
#
# Runs on the host.
set -uo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

FULL=0
FIX=0

while [ $# -gt 0 ]; do
	case "$1" in
		--full) FULL=1; shift ;;
		--fix) FIX=1; shift ;;
		-h|--help) sed -n '2,21p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

# Unlike the other scripts here this one does not set -e, so that a failing
# check does not stop the ones after it. That means cd has to be checked.
cd "$BOARD_DIR" || die "cannot enter $BOARD_DIR"

docker compose ps --status running --services 2>/dev/null | grep -qx web \
	|| die 'the web container is not running -- docker compose up -d'

FAILED=''

# $1 label, rest: the command to run in the web container.
check() {
	local label="$1"
	shift

	printf '\n[smf-dev] --- %s ---\n' "$label"

	if docker compose exec -T web "$@"; then
		return 0
	fi

	FAILED="${FAILED}\n  - ${label}"

	return 1
}

# ------------------------------------------------------------------- php.yml
check 'sign-off (DCO)' php ./vendor/simplemachines/build-tools/check-signed-off.php

check 'file integrity' sh -c '
	set -e
	php ./vendor/simplemachines/build-tools/check-smf-license.php
	php ./vendor/simplemachines/build-tools/check-smf-languages.php
	php ./vendor/simplemachines/build-tools/check-smf-index.php
	php ./vendor/simplemachines/build-tools/check-version.php
	echo "all four integrity checks passed"
'

check "syntax ($(docker compose exec -T web php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null))" \
	vendor/bin/phplint --no-progress --exclude .git --exclude vendor .

# ------------------------------------------------------------ php-cs-fixer.yml
# CI checks only the files a pull request changed, and switches to the whole
# tree when composer.lock or the fixer config is part of the diff. --full asks
# for that second behaviour, which is worth doing before touching a dependency:
# it surfaces anything already non-compliant on release-3.0.
FIXER_ARGS=(--config .php-cs-fixer.dist.php --allow-risky=yes --using-cache=no --show-progress=none)

if [ "$FIX" -eq 1 ]; then
	FIXER_MODE='fix'
else
	FIXER_MODE='check'
	FIXER_ARGS+=(--diff)
fi

if [ "$FULL" -eq 1 ]; then
	check 'code style (whole tree)' vendor/bin/php-cs-fixer "$FIXER_MODE" "${FIXER_ARGS[@]}"
else
	# Same intersection CI builds, from the files this branch actually touches:
	# committed since release-3.0, staged, unstaged, and - the one CI never has
	# to think about - new files that are not in the index yet.
	CHANGED=$(
		{
			git diff --name-only --diff-filter=d release-3.0...HEAD -- '*.php'
			git diff --name-only --diff-filter=d HEAD -- '*.php'
			git ls-files --others --exclude-standard -- '*.php'
		} 2>/dev/null | sort -u | grep -v '^$'
	)

	if [ -z "$CHANGED" ]; then
		printf '\n[smf-dev] --- code style --- no changed PHP files\n'
	else
		# shellcheck disable=SC2086
		check 'code style (changed files)' vendor/bin/php-cs-fixer "$FIXER_MODE" "${FIXER_ARGS[@]}" --path-mode=intersection $CHANGED
	fi
fi

# ---------------------------------------------------------------- phpunit.yml
if [ -f phpunit.xml.dist ]; then
	check 'tests' vendor/bin/phpunit --no-coverage --colors=always
else
	printf '\n[smf-dev] --- tests --- no phpunit.xml.dist on this branch, skipping\n'
fi

# ---------------------------------------------------------------------- result
printf '\n'

if [ -n "$FAILED" ]; then
	# shellcheck disable=SC2059
	printf "[smf-dev] failed:${FAILED}\n" >&2
	exit 1
fi

log 'everything CI checks passes'
