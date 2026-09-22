#!/usr/bin/env bash
# Builds a baseline from nothing, for one or both engines.
#
#   .docker/baseline/make-baseline.sh
#   .docker/baseline/make-baseline.sh --engine both --profile small
#   .docker/baseline/make-baseline.sh --engine mysql --profile tiny
#
# Per engine: wipe, install, populate, seed the extras, dump, and then restore
# the dump to prove it works. Nothing is committed -- the script prints the
# `git add` line and stops there.
#
# The two engines cannot interfere with each other. They keep separate volumes,
# and Settings.php pins one engine at a time, so building the MySQL baseline
# leaves an existing PostgreSQL one untouched.
#
# Runs on the host. Expect four to eight minutes per engine on the small
# profile, most of it in DatabasePopulation and the populate blocks.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINES='both'
PROFILE="$BASELINE_PROFILE"
VERSION="$BASELINE_VERSION"
SKIP_POPULATE=0
SKIP_EXTRAS=0
SKIP_VERIFY=0

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINES="$2"; shift 2 ;;
		--engine=*) ENGINES="${1#*=}"; shift ;;
		--profile) PROFILE="$2"; shift 2 ;;
		--profile=*) PROFILE="${1#*=}"; shift ;;
		--version) VERSION="$2"; shift 2 ;;
		--version=*) VERSION="${1#*=}"; shift ;;
		--skip-populate) SKIP_POPULATE=1; shift ;;
		--skip-extras) SKIP_EXTRAS=1; shift ;;
		--keep) SKIP_VERIFY=1; shift ;;
		-h|--help) sed -n '2,17p' "${BASH_SOURCE[0]}"; exit 0 ;;
		*) die "unknown argument: $1" ;;
	esac
done

baseline_profile "$PROFILE" >/dev/null || die "unknown profile: ${PROFILE} (tiny, small, medium, large)"

case "$ENGINES" in
	both) list='mysql postgres' ;;
	mysql|mysqli|mariadb) list='mysql' ;;
	postgres|postgresql|pgsql) list='postgres' ;;
	*) die "unknown engine: ${ENGINES}" ;;
esac

cd "$BOARD_DIR"

export BASELINE_VERSION="$VERSION"
export BASELINE_PROFILE="$PROFILE"

log "building baseline ${VERSION}, profile ${PROFILE}, engine(s): ${list}"

docker compose up -d --build >/dev/null

for engine in $list; do
	printf '\n'
	log "=== ${engine} ==="

	bash "$BASELINE_DIR/reset.sh" --engine "$engine"

	# The Settings.baseline.php.tpl template is written once per version, from
	# whichever engine runs first; the engine-specific values in it are
	# placeholders anyway.
	docker compose exec -T web bash .docker/baseline/install-forum.sh \
		--settings-template ".docker/baseline/artifacts/${VERSION}/Settings.baseline.php.tpl"

	if [ "$SKIP_POPULATE" -eq 0 ]; then
		docker compose exec -T web bash .docker/baseline/populate.sh --profile "$PROFILE"
	fi

	if [ "$SKIP_EXTRAS" -eq 0 ]; then
		docker compose exec -T web php .docker/baseline/run-extras.php

		# Before dumping, not after: an artifact whose data is thin is worth
		# catching while the forum that produced it still exists. Row counts
		# would not catch it -- the custom field definitions were once missing
		# on PostgreSQL while the table still had the four a stock install
		# supplies.
		docker compose exec -T web php .docker/baseline/check-coverage.php
	fi

	bash "$BASELINE_DIR/dump.sh" --engine "$engine" --profile "$PROFILE" --version "$VERSION"

	if [ "$SKIP_VERIFY" -eq 0 ]; then
		# A dump is not finished until it has been proven to restore. This wipes
		# the database that was just dumped and loads the artifact back over it,
		# so what ends up running is the artifact, not the original.
		bash "$BASELINE_DIR/restore.sh" --engine "$engine" --profile "$PROFILE" --version "$VERSION"
	fi
done

# CURRENT is what the 3.0 side reads to find the newest artifact set without
# having to be told a version number.
printf '%s\n' "$VERSION" > "${ARTIFACT_DIR}/CURRENT"

printf '\n'
log "baseline ${VERSION}/${PROFILE} built for: ${list}"
log 'to commit it:'
printf '\n    git add -f .docker/baseline/artifacts/%s\n    git commit -s -m "Adds the SMF 2.1 %s baseline (%s)"\n\n' \
	"$VERSION" "$PROFILE" "$VERSION"
