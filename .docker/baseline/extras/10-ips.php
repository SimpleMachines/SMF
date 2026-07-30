<?php

/**
 * Puts IP addresses on the content Populate.php created.
 *
 * run-populate.php runs without a REMOTE_ADDR on purpose -- that is how
 * QueryString.php recognises a CLI run -- so createPost() and registerMember()
 * record empty IPs. Left alone, the whole baseline would have no addresses in
 * it, and SMF 3.0's dozen IPv6 conversion migrations would run against nothing.
 *
 * The addresses come from the documentation ranges reserved by RFC 5737 and
 * RFC 3849, so none of them belongs to anybody. The mix is deliberate: a third
 * IPv4, a third IPv6, a third absent. On MySQL, 2.1 stores these as
 * VARBINARY(16) holding raw inet_pton() output and the migrations have real
 * work to do; on PostgreSQL 2.1 already uses a native inet and they should
 * correctly skip. Building the baseline on both engines is what proves both
 * halves.
 *
 * Exercises: Ipv6Messages, Ipv6MembersIP, Ipv6MembersIP2, Ipv6MemberLogins,
 * Ipv6Converter, PostgreSqlIPv6Helper, CreateMemberLogins.
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

if (!defined('SMF'))
	die('No direct access...');

$baseline_name = '10-ips';

if (baseline_applied($baseline_name) && empty($baseline_force))
{
	baseline_say($baseline_name . ': skipped');
}
else
{
	global $smcFunc;

	$touched = array('messages' => 0, 'members' => 0, 'logins' => 0);

	// {inet:...} is the placeholder that hides the difference between the two
	// engines: SMF's MySQL driver turns it into unhex(bin2hex(inet_pton(...)))
	// and its PostgreSQL driver passes it through as an inet. Writing the
	// conversion by hand here would mean writing it twice, and getting the
	// MySQL half subtly wrong.
	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages
		ORDER BY id_msg',
		array()
	);

	$messages = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$messages[] = (int) $row['id_msg'];

	$smcFunc['db_free_result']($request);

	foreach ($messages as $id_msg)
	{
		$ip = baseline_ip($id_msg);

		// A third of them keep no address at all. NULL is a real state in this
		// column and the migrations have to survive it.
		if ($ip === '')
			continue;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET poster_ip = {inet:ip}
			WHERE id_msg = {int:id_msg}',
			array(
				'ip' => $ip,
				'id_msg' => $id_msg,
			)
		);

		$touched['messages']++;
	}

	// Members get two: the address they registered from and the one they were
	// last seen on. SMF 3.0 migrates them separately, so they must differ.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		ORDER BY id_member',
		array()
	);

	$members = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[] = (int) $row['id_member'];

	$smcFunc['db_free_result']($request);

	foreach ($members as $id_member)
	{
		$ip = baseline_ip($id_member);
		$ip2 = baseline_ip($id_member + 1);

		if ($ip === '' && $ip2 === '')
			continue;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				member_ip = {inet:ip},
				member_ip2 = {inet:ip2}
			WHERE id_member = {int:id_member}',
			array(
				'ip' => $ip,
				'ip2' => $ip2,
				'id_member' => $id_member,
			)
		);

		$touched['members']++;
	}

	// A handful of login records. 2.1 created this table late in its own life,
	// so it is empty on a fresh install and stays empty unless somebody logs in.
	$logins = array();
	$now = time();

	foreach (array_slice($members, 0, 10) as $index => $id_member)
	{
		$logins[] = array(
			$id_member,
			$now - (86400 * ($index + 1)),
			baseline_ip($index),
			baseline_ip($index + 2),
		);
	}

	if (!empty($logins))
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}member_logins',
			array('id_member' => 'int', 'time' => 'int', 'ip' => 'inet', 'ip2' => 'inet'),
			$logins,
			array('id_login')
		);

		$touched['logins'] = count($logins);
	}

	baseline_say(sprintf(
		'%s: %d message(s), %d member(s), %d login(s)',
		$baseline_name,
		$touched['messages'],
		$touched['members'],
		$touched['logins']
	));

	baseline_mark_applied($baseline_name);
}
