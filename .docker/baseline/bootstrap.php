<?php

/**
 * Shared setup for the PHP halves of the baseline generator.
 *
 * Anything that loads SMF from the command line needs the same three things:
 * to be standing in the board directory, to be signed in as an administrator,
 * and to have a way of recording that a one-off step has already run. This is
 * that, in one place.
 *
 * Deliberately written to PHP 7.1 syntax: SMF 2.1's CI lints every PHP file in
 * the checkout against 7.1 through 8.4.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (PHP_SAPI !== 'cli')
	die('This script may only be run from the command line.');

/**
 * Loads SMF and signs in as the administrator.
 *
 * SSI.php has to be reached from the board directory: it resolves Settings.php
 * relative to itself, and everything it loads afterwards is relative to
 * $sourcedir.
 *
 * @param string $board_dir
 */
function baseline_boot($board_dir)
{
	chdir($board_dir);

	// Member email addresses and anything else that wants a hostname are built
	// from this. Under CLI there is nothing to inherit it from, and an empty
	// value would produce addresses like 'member_1@'. example.com is reserved
	// by RFC 2606, so nothing here can ever reach a real inbox.
	if (empty($_SERVER['SERVER_NAME']))
		$_SERVER['SERVER_NAME'] = 'example.com';

	// Not set, deliberately: $_SERVER['REMOTE_ADDR']. QueryString.php treats its
	// absence as the signal that this is a CLI run, and SSI.php's session
	// handling depends on that. The cost is that createPost() records an empty
	// poster_ip, which extras/10-ips.php then fills in on purpose -- with the
	// mix of IPv4 and IPv6 the SMF 3.0 migrations have to convert.
	require_once $board_dir . '/SSI.php';

	baseline_become_admin();
}

/**
 * Gives this process the standing a logged-in administrator would have had.
 *
 * The tools being driven here were written for a browser, and lean on that in
 * ways that are fatal to a guest:
 *
 *  - createBoard() -> modifyBoard() -> getBoardTree() filters boards through
 *    {query_see_board}. A board that has just been inserted still has an empty
 *    member_groups, so a guest cannot see it and modifyBoard() stops with "The
 *    board you specified doesn't exist" on the very first board.
 *
 *  - registerMember() with 'interface' => 'admin' calls is_not_guest() and
 *    isAllowedTo('moderate_forum'), both of which a guest fails outright.
 *
 * allowedTo() short-circuits for administrators, so this covers every
 * permission check without enumerating them.
 */
function baseline_become_admin()
{
	global $smcFunc, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name
		FROM {db_prefix}members
		WHERE id_group = {int:admin_group}
		ORDER BY id_member
		LIMIT 1',
		array('admin_group' => 1)
	);

	if ($smcFunc['db_num_rows']($request) === 0)
		baseline_fail('the forum has no administrator -- was it installed?');

	list ($id_member, $member_name) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$user_info['id'] = (int) $id_member;
	$user_info['username'] = $member_name;
	$user_info['name'] = $member_name;
	$user_info['is_guest'] = false;
	$user_info['is_admin'] = true;
	$user_info['groups'] = array(1);
	$user_info['query_see_board'] = '1=1';
	$user_info['query_wanna_see_board'] = '1=1';
}

/**
 * Has this step already been applied to this forum?
 *
 * Recorded in {db_prefix}settings so the marker travels inside the dump: a
 * restored baseline knows what it already contains.
 *
 * @param string $name
 * @return bool
 */
function baseline_applied($name)
{
	global $modSettings;

	return !empty($modSettings['baseline_extras_' . $name]);
}

/**
 * @param string $name
 */
function baseline_mark_applied($name)
{
	updateSettings(array('baseline_extras_' . $name => time()));
}

/**
 * A spread of member ids to hang generated content off, so the extras land on
 * real accounts rather than all on the administrator.
 *
 * @param int $limit
 * @return array
 */
function baseline_member_ids($limit = 20)
{
	global $smcFunc;

	$ids = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		ORDER BY id_member
		LIMIT {int:limit}',
		array('limit' => (int) $limit)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ids[] = (int) $row['id_member'];

	$smcFunc['db_free_result']($request);

	return $ids;
}

/**
 * A spread of message ids, newest last.
 *
 * @param int $limit
 * @return array
 */
function baseline_message_ids($limit = 30)
{
	global $smcFunc;

	$ids = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages
		ORDER BY id_msg
		LIMIT {int:limit}',
		array('limit' => (int) $limit)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ids[] = (int) $row['id_msg'];

	$smcFunc['db_free_result']($request);

	return $ids;
}

/**
 * One of a fixed rotation of addresses: IPv4, IPv6, and none at all.
 *
 * Every address is from a documentation range (RFC 5737 / RFC 3849), so none of
 * them can ever belong to anybody. The mix is the point: SMF 3.0 has a migration
 * per IP-bearing column, and on MySQL those columns are VARBINARY(16) holding
 * raw inet_pton() output, while on PostgreSQL 2.1 already used a native inet.
 * A baseline with only IPv4 addresses, or no NULLs, would let half of that go
 * untested.
 *
 * "No address" is an empty string, not null: SMF's {inet:...} placeholder turns
 * '' into a SQL NULL, whereas a PHP null never reaches it at all -- the driver
 * guards its parameters with isset(), which null fails, and the query dies with
 * "The database value you're trying to insert does not exist".
 *
 * @param int $seed
 * @return string An address, or '' for none.
 */
function baseline_ip($seed)
{
	$pattern = $seed % 3;

	if ($pattern === 0)
		return '203.0.113.' . (($seed % 250) + 1);

	if ($pattern === 1)
		return '2001:db8:1ce::' . dechex(($seed % 250) + 1);

	return '';
}

/**
 * @param string $message
 */
function baseline_fail($message)
{
	fwrite(STDERR, '[baseline] ' . $message . "\n");
	exit(1);
}

/**
 * @param string $message
 */
function baseline_say($message)
{
	echo '[baseline] ', $message, "\n";
}

?>