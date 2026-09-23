#!/usr/bin/env bash
# Runs the test suite against a real forum, on one engine or on both.
#
#   .dev/test.sh                          both engines, whole suite
#   .dev/test.sh --engine postgresql
#   .dev/test.sh --engine both --filter ModSettings
#   .dev/test.sh --docker --engine mysql
#
# Anything after the recognised options is handed straight to PHPUnit, so
# --filter, --testsuite and friends work as usual.
#
# Installs a forum for an engine that has not got one yet. Use
# .dev/install-forum.sh --force to start any of them over.
#
# Running on both engines is the point rather than a thoroughness exercise: the
# two disagree often enough that a suite which only ever sees one of them
# proves considerably less than it appears to. The counter regression in
# tests/Integration/ModSettingsTest.php passes on MySQL with the bug still in
# place, and fails on PostgreSQL.
#
# Runs on the host.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

parse_runner_args "$@"

ENGINE='both'
PHPUNIT_ARGS=()

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		--docker|--local) shift ;;
		-h|--help) sed -n '2,20p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) PHPUNIT_ARGS+=("$1"); shift ;;
	esac
done

ENGINES=$(engine_list "$ENGINE") || die "unknown engine: $ENGINE"

for smf_type in $ENGINES; do
	require_local_deps "$smf_type"
done

cd "$BOARD_DIR"

# Remember what was active, and put it back afterwards however this ends. A test
# run should not silently leave the forum pointed somewhere else.
ORIGINAL=''

if [ -f Settings.php ]; then
	ORIGINAL=$(sed -n "s|^\$db_type = '\([^']*\)';.*|\1|p" Settings.php | head -n 1 | tr '[:upper:]' '[:lower:]')
fi

cleanup() {
	serve_stop

	if [ -n "$ORIGINAL" ] && [ -f "$SETTINGS_DIR/Settings.$(engine_smf_type "$ORIGINAL").php" ]; then
		"$DEV_DIR/use-engine.sh" "--${SMF_RUNNER}" "$ORIGINAL" >/dev/null 2>&1 || true
	fi
}

trap cleanup EXIT

FAILED=''

for smf_type in $ENGINES; do
	if [ -z "$(installed_version "$smf_type" || true)" ]; then
		log "${smf_type}: no forum yet, installing one"
		"$DEV_DIR/install-forum.sh" "--${SMF_RUNNER}" --engine "$smf_type" >/dev/null
	fi

	"$DEV_DIR/use-engine.sh" "--${SMF_RUNNER}" "$smf_type" >/dev/null

	# One server serves both engines: it reads Settings.php per request, and
	# use-engine.sh has just rewritten it. Started after the first install rather
	# than before it, so it never has a half-built forum underneath it.
	serve_start

	log "${smf_type}: running the tests"

	# The HTTP tests sign in, so they need to be told who the administrator is.
	# These default to what install-forum.sh created; export them to point the
	# suite at a forum that was set up some other way.
	#
	# The base URL is only named locally. Under docker the suite runs inside the
	# web container, where the forum answers on port 80 rather than on the
	# published port the board URL names, and HttpClient works that out for
	# itself.
	TEST_ENV=(
		SMF_ADMIN_USER="$SMF_ADMIN_USER"
		SMF_ADMIN_PASS="$SMF_ADMIN_PASS"
	)

	if ! is_docker; then
		TEST_ENV+=(SMF_TESTS_BASE_URL="$SMF_BOARDURL")
	fi

	if run_php_env "${TEST_ENV[@]}" -- vendor/bin/phpunit \
		--no-coverage --colors=always "${PHPUNIT_ARGS[@]+"${PHPUNIT_ARGS[@]}"}"; then
		log "${smf_type}: passed"
	else
		warn "${smf_type}: FAILED"
		FAILED="${FAILED} ${smf_type}"
	fi
done

if [ -n "$FAILED" ]; then
	die "failed on:${FAILED}"
fi

log "passed on: ${ENGINES}"
