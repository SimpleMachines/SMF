#!/usr/bin/env bash
# Looks at forum accounts and fixes their passwords, so "which password did this
# forum end up with?" does not turn into a session of hand written SQL.
#
#   .docker/user.sh list
#   .docker/user.sh check admin 'password'
#   .docker/user.sh reset admin 'a new password'
#   .docker/user.sh check admin 'password' --engine postgresql
#
# check exits 0 when SMF would accept the password and 1 when it would not, so
# it is usable in a conditional as well as by eye.
#
# Everything goes through SMF's own Security class rather than writing a hash
# from here: what this puts in the table is by construction what Login2 expects
# to find there. Nothing is ever printed that would reveal an existing password;
# hashes are one way and this does not try to be clever about that.
#
# Without --engine it acts on the forum Settings.php currently points at. With
# it, it reads the copy use-engine.sh saved for that engine instead, which means
# the other forum can be inspected without switching to it.
#
# Runs on the host.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

ENGINE=''
ACTION=''
NAME=''
PASSWORD=''
POSITIONAL=()

while [ $# -gt 0 ]; do
	case "$1" in
		--engine) ENGINE="$2"; shift 2 ;;
		--engine=*) ENGINE="${1#*=}"; shift ;;
		-h|--help) sed -n '2,22p' "${BASH_SOURCE[0]}"; exit 0 ;;
		-*) die "unknown argument: $1" ;;
		*) POSITIONAL+=("$1"); shift ;;
	esac
done

[ "${#POSITIONAL[@]}" -gt 0 ] || die "need an action: list, check or reset (see --help)"

ACTION="${POSITIONAL[0]}"
NAME="${POSITIONAL[1]:-}"
PASSWORD="${POSITIONAL[2]:-}"

case "$ACTION" in
	list) ;;
	check|reset)
		[ -n "$NAME" ] || die "${ACTION}: need a member name"
		[ -n "$PASSWORD" ] || die "${ACTION}: need a password"
		;;
	*) die "unknown action: ${ACTION} (expected list, check or reset)" ;;
esac

# The settings file to read, as the container sees it. Empty means "whichever
# forum is live", which is the common case and needs no explanation in the log.
SETTINGS='/var/www/html/Settings.php'

if [ -n "$ENGINE" ]; then
	SMF_TYPE=$(engine_smf_type "$ENGINE") || die "unknown engine: $ENGINE"
	SAVED="$DOCKER_DIR/settings/Settings.${SMF_TYPE}.php"

	[ -f "$SAVED" ] || die "no saved settings for ${SMF_TYPE}; install it first with install-forum.sh --engine ${SMF_TYPE}"

	SETTINGS="/var/www/html/.docker/settings/Settings.${SMF_TYPE}.php"
fi

cd "$BOARD_DIR"

# The password goes through the environment rather than the argument list:
# arguments are visible to anything that can read the process table, and a
# password typed at a shell is quite enough exposure already.
docker compose exec -T \
	-e SMF_USER_ACTION="$ACTION" \
	-e SMF_USER_NAME="$NAME" \
	-e SMF_USER_PASSWORD="$PASSWORD" \
	-e SMF_USER_SETTINGS="$SETTINGS" \
	web php <<-'PHP'
	<?php

	/*
	 * Runs inside the web container against the installed forum. Kept to the
	 * constants Config::load() and Db::load() actually read, because anything
	 * more would be pretending this is a request.
	 */

	define('SMF', 1);
	define('SMF_SETTINGS_FILE', getenv('SMF_USER_SETTINGS'));
	define('SMF_SETTINGS_BACKUP_FILE', str_replace('Settings.php', 'Settings_bak.php', SMF_SETTINGS_FILE));

	// Config::getSettingsDefs() reads both of these while working out what a
	// Settings.php should contain. Taken from index.php rather than written out
	// here, so this cannot disagree with the version it is running against.
	$index = (string) file_get_contents('/var/www/html/index.php');

	preg_match("~define\('SMF_VERSION', '([^']+)'\);~", $index, $version);
	preg_match("~define\('SMF_SOFTWARE_YEAR', '(\d{4})'\);~", $index, $year);

	define('SMF_VERSION', $version[1] ?? '3.0');
	define('SMF_SOFTWARE_YEAR', $year[1] ?? date('Y'));

	define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);
	define('SMF_USER_AGENT', 'SMF dev tools');
	define('TIME_START', microtime(true));

	// DatabaseApi::getClass() names the engine with these when it cannot find
	// one, so they have to exist before the connection is made.
	define('POSTGRE_TITLE', 'PostgreSQL');
	define('MYSQL_TITLE', 'MySQL');

	require '/var/www/html/vendor/autoload.php';

	SMF\Config::load();
	SMF\Db\DatabaseApi::load();
	SMF\Config::reloadModSettings();

	$db = SMF\Db\DatabaseApi::$db;
	$action = (string) getenv('SMF_USER_ACTION');
	$name = (string) getenv('SMF_USER_NAME');
	$password = (string) getenv('SMF_USER_PASSWORD');

	fwrite(STDERR, '[smf-dev] ' . SMF\Config::$db_type . ' forum at ' . SMF\Config::$boardurl . "\n");

	if ($action === 'list') {
		$request = $db->query(
			'SELECT id_member, member_name, real_name, email_address, id_group, is_activated
			FROM {db_prefix}members
			ORDER BY id_member',
			[],
		);

		printf("%-5s %-20s %-28s %-7s %s\n", 'id', 'member_name', 'email', 'group', 'activated');

		while ($row = $db->fetch_assoc($request)) {
			printf(
				"%-5d %-20s %-28s %-7d %s\n",
				$row['id_member'],
				$row['member_name'],
				$row['email_address'],
				$row['id_group'],
				// 1 is the only value that can log in; the rest are awaiting
				// activation, awaiting approval, banned or deleted.
				$row['is_activated'] == 1 ? 'yes' : 'no (' . $row['is_activated'] . ')',
			);
		}

		$db->free_result($request);

		exit(0);
	}

	$request = $db->query(
		'SELECT id_member, member_name, passwd, is_activated
		FROM {db_prefix}members
		WHERE member_name = {string:name} OR email_address = {string:name}
		LIMIT 1',
		[
			'name' => $name,
		],
	);

	$member = $db->fetch_assoc($request);
	$db->free_result($request);

	if (!is_array($member)) {
		fwrite(STDERR, 'error: no member called "' . $name . '" (try: user.sh list)' . "\n");

		exit(1);
	}

	if ($action === 'check') {
		$ok = SMF\Security::hashVerifyPassword($password, $member['passwd']);

		echo $member['member_name'], ': ', $ok ? 'password is correct' : 'password is WRONG', "\n";

		// Being right about the password is not the same as being able to log
		// in, and the difference is worth saying out loud before someone spends
		// an afternoon on it.
		if ($ok && $member['is_activated'] != 1) {
			echo '  note: the account is not active (is_activated = ', $member['is_activated'], '), so it cannot log in', "\n";
		}

		exit($ok ? 0 : 1);
	}

	$db->query(
		'UPDATE {db_prefix}members
		SET passwd = {string:passwd}, passwd_flood = {string:empty}
		WHERE id_member = {int:id}',
		[
			'passwd' => SMF\Security::hashPassword($password),
			// Cleared as well: SMF locks an account out for a while after
			// enough wrong guesses, and resetting the password while leaving
			// the lockout in place looks exactly like the password not working.
			'empty' => '',
			'id' => (int) $member['id_member'],
		],
	);

	echo $member['member_name'], ': password changed', "\n";
	PHP
