<?php

/**
 * Checks that the baseline's data is actually *populated*, not merely present.
 *
 *   docker compose exec -T web php .docker/baseline/check-coverage.php
 *
 * verify.php compares row counts against the manifest, which proves a dump
 * restored intact. It cannot prove the rows were worth dumping. This can: every
 * check here asserts a column that some SMF 3.0 migration reads is genuinely
 * filled in.
 *
 * It exists because it was needed. The custom field *definitions* were silently
 * missing on PostgreSQL for a while -- db_insert() built an `ON CONFLICT ()`
 * clause, PostgreSQL rejected it, SMF logged the error instead of raising it,
 * and the seeding script cheerfully reported three fields inserted. Row counts
 * looked fine, because the stock install supplies four fields of its own. Only
 * reading the data showed it.
 *
 * Runs on either engine. Anything engine-specific is marked as such.
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

$baseline_dir = __DIR__;
$board_dir = dirname(dirname($baseline_dir));

require_once $baseline_dir . '/bootstrap.php';

baseline_boot($board_dir);

global $smcFunc, $db_type;

$is_postgres = strtolower($db_type) === 'postgresql';
$failures = 0;
$checks = 0;

// ---------------------------------------------------------------- 05 boards
baseline_check(
	'boards are visible to somebody',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}boards WHERE member_groups = {string:empty}', array('empty' => '')),
	0,
	'boards with no member_groups at all'
);

baseline_check(
	'board permissions view is populated',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}board_permissions_view'),
	'>0'
);

// ------------------------------------------------------------------- 10 ips
$ips = baseline_column('SELECT poster_ip FROM {db_prefix}messages WHERE poster_ip IS NOT NULL');
$families = baseline_ip_families($ips);

baseline_check('messages carry IPv4 addresses', $families['v4'], '>0');
baseline_check('messages carry IPv6 addresses', $families['v6'], '>0');
baseline_check(
	'some messages have no address at all',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}messages WHERE poster_ip IS NULL'),
	'>0'
);

$member_ips = baseline_ip_families(baseline_column('SELECT member_ip FROM {db_prefix}members WHERE member_ip IS NOT NULL'));
baseline_check('members carry IPv4 addresses', $member_ips['v4'], '>0');
baseline_check('members carry IPv6 addresses', $member_ips['v6'], '>0');

baseline_check(
	'member_ip2 differs from member_ip',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}members WHERE member_ip IS NOT NULL AND member_ip2 IS NOT NULL AND member_ip <> member_ip2'),
	'>0'
);

baseline_check(
	'member_logins have both addresses',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}member_logins WHERE ip IS NOT NULL AND ip2 IS NOT NULL'),
	'>0'
);

// -------------------------------------------------------- 20 profile fields
baseline_check(
	'baseline custom field definitions exist',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}custom_fields WHERE col_name LIKE {string:like}', array('like' => 'bl\_%')),
	3
);

baseline_check(
	'every custom field value points at a real field',
	baseline_scalar('
		SELECT COUNT(*)
		FROM {db_prefix}themes AS t
		WHERE t.variable LIKE {string:like}
			AND NOT EXISTS (
				SELECT 1 FROM {db_prefix}custom_fields AS c
				WHERE {raw:concat} = t.variable
			)',
		array(
			'like' => 'cust\_bl\_%',
			'concat' => $is_postgres ? "'cust_' || c.col_name" : "CONCAT('cust_', c.col_name)",
		)
	),
	0,
	'orphaned cust_ values'
);

baseline_check(
	'custom field values are non-empty',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}themes WHERE variable LIKE {string:like} AND value <> {string:empty}', array('like' => 'cust\_bl\_%', 'empty' => '')),
	'>0'
);

baseline_check(
	'categories have descriptions',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}categories WHERE description <> {string:empty}', array('empty' => '')),
	'>0'
);

// ----------------------------------------------------------------- 30 content
baseline_check(
	'polls have questions and choices',
	baseline_scalar('
		SELECT COUNT(*)
		FROM {db_prefix}polls AS p
		WHERE p.question <> {string:empty}
			AND EXISTS (SELECT 1 FROM {db_prefix}poll_choices AS c WHERE c.id_poll = p.id_poll)',
		array('empty' => '')
	),
	2
);

baseline_check(
	'one poll has expired and one has not',
	baseline_scalar('SELECT COUNT(DISTINCT CASE WHEN expire_time = 0 THEN 0 ELSE 1 END) FROM {db_prefix}polls'),
	2
);

baseline_check('poll votes were recorded', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_polls'), '>0');
baseline_check(
	'poll choices show their votes',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}poll_choices WHERE votes > 0'),
	'>0'
);

baseline_check(
	'topics are linked to their polls',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}topics WHERE id_poll > 0'),
	2
);

baseline_check(
	'personal messages have a subject and a body',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}personal_messages WHERE subject <> {string:empty} AND body <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'personal messages have recipients',
	baseline_scalar('
		SELECT COUNT(*)
		FROM {db_prefix}personal_messages AS pm
		WHERE NOT EXISTS (SELECT 1 FROM {db_prefix}pm_recipients AS r WHERE r.id_pm = pm.id_pm)'),
	0,
	'messages with nobody to receive them'
);

baseline_check('a personal message label exists', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}pm_labels'), '>0');
baseline_check('messages are filed under it', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}pm_labeled_messages'), '>0');

baseline_check(
	'the pm rule has criteria and actions',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}pm_rules WHERE criteria <> {string:empty} AND actions <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'drafts have bodies, of both kinds',
	baseline_scalar('SELECT COUNT(DISTINCT type) FROM {db_prefix}user_drafts WHERE body <> {string:empty}', array('empty' => '')),
	2
);

baseline_check('likes exist', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}user_likes'), '>0');
baseline_check('mentions exist', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}mentions'), '>0');

baseline_check(
	'edited messages carry all three edit columns',
	baseline_scalar('
		SELECT COUNT(*)
		FROM {db_prefix}messages
		WHERE modified_time > 0
			AND modified_name <> {string:empty}
			AND modified_reason <> {string:empty}',
		array('empty' => '')
	),
	'>0'
);

baseline_check(
	'moved topics are marked as such',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}messages WHERE subject LIKE {string:like}', array('like' => 'MOVED:%')),
	'>0'
);

baseline_check(
	'confusable display names exist',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}members WHERE member_name LIKE {string:like}', array('like' => 'spoof\_%')),
	3
);

// ------------------------------------------------------------ 35 attachments
baseline_check(
	'attachments have a size and a hash',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}attachments WHERE attachment_type = 0 AND size > 0 AND file_hash <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'image attachments have dimensions',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}attachments WHERE mime_type = {string:png} AND width > 0 AND height > 0', array('png' => 'image/png')),
	'>0'
);

baseline_check(
	'a thumbnail is linked to its parent',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}attachments WHERE id_thumb > 0'),
	'>0'
);

baseline_check(
	'an avatar attachment exists',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}attachments WHERE attachment_type = 1'),
	'>0'
);

// The rows are only half of it: SMF resolves an attachment to a file on disk,
// and a row pointing at nothing is a broken forum, not a seeded one.
$missing = 0;
$request = $smcFunc['db_query']('', '
	SELECT id_attach, filename, file_hash, attachment_type
	FROM {db_prefix}attachments',
	array()
);

while ($row = $smcFunc['db_fetch_assoc']($request))
{
	$path = $row['attachment_type'] == 1
		? $board_dir . '/custom_avatar/' . $row['filename']
		: $board_dir . '/attachments/' . $row['id_attach'] . '_' . $row['file_hash'] . '.dat';

	if (!is_file($path))
		$missing++;
}

$smcFunc['db_free_result']($request);

baseline_check('every attachment row has its file', $missing, 0, 'rows with no file on disk');

// --------------------------------------------------------------- 40 calendar
baseline_check(
	'events have titles and dates',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}calendar WHERE title <> {string:empty}', array('empty' => '')),
	4
);

baseline_check(
	'one event is all-day (no times)',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}calendar WHERE start_time IS NULL AND end_time IS NULL'),
	1
);

baseline_check(
	'one event spans several days',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}calendar WHERE end_date > start_date'),
	'>0'
);

baseline_check(
	'one event is attached to a topic',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}calendar WHERE id_topic > 0'),
	'>0'
);

// The sentinel year is the whole point: 1004 means "every year", and anything
// else is a holiday that happens once. Getting this wrong is silent -- SMF just
// never shows the holiday, and the upgrade carries it across as a one-off.
baseline_check(
	'recurring holidays use the 1004 sentinel',
	baseline_scalar("SELECT COUNT(*) FROM {db_prefix}calendar_holidays WHERE title LIKE {string:like} AND {raw:year} = 1004", array('like' => 'Baseline%', 'year' => $is_postgres ? 'EXTRACT(YEAR FROM event_date)' : 'YEAR(event_date)')),
	2
);

baseline_check(
	'one holiday happens only once',
	baseline_scalar("SELECT COUNT(*) FROM {db_prefix}calendar_holidays WHERE title LIKE {string:like} AND {raw:year} <> 1004", array('like' => 'Baseline%', 'year' => $is_postgres ? 'EXTRACT(YEAR FROM event_date)' : 'YEAR(event_date)')),
	1
);

baseline_check('the calendar is switched on', baseline_setting('cal_enabled'), '1');

// ------------------------------------------------------------------ 50 logs
baseline_check(
	'error log entries have a message and a type',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_errors WHERE message <> {string:empty} AND error_type <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'some errors carry a backtrace and a session',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_errors WHERE backtrace <> {string:empty} AND session <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'some errors carry neither',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_errors WHERE backtrace = {string:empty} AND session = {string:empty}', array('empty' => '')),
	'>0'
);

$error_ips = baseline_ip_families(baseline_column('SELECT ip FROM {db_prefix}log_errors WHERE ip IS NOT NULL'));
baseline_check('the error log has IPv4 and IPv6', min($error_ips['v4'], $error_ips['v6']), '>0');

baseline_check(
	'action log entries have an action and extra data',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_actions WHERE action <> {string:empty} AND extra <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'both the moderation and admin logs are used',
	baseline_scalar('SELECT COUNT(DISTINCT id_log) FROM {db_prefix}log_actions'),
	'>1'
);

baseline_check(
	'online entries have a url',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_online WHERE url <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'reports have a subject, a body and comments',
	baseline_scalar('
		SELECT COUNT(*)
		FROM {db_prefix}log_reported AS r
		WHERE r.subject <> {string:empty}
			AND r.body <> {string:empty}
			AND EXISTS (SELECT 1 FROM {db_prefix}log_reported_comments AS c WHERE c.id_report = r.id_report)',
		array('empty' => '')
	),
	'>0'
);

baseline_check(
	'report comments carry an address',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_reported_comments WHERE member_ip IS NOT NULL'),
	'>0'
);

baseline_check('flood control entries exist', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_floodcontrol'), '>0');
baseline_check(
	'spider hits have a url',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_spider_hits WHERE url <> {string:empty}', array('empty' => '')),
	'>0'
);
baseline_check('the search log has rows', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_search_subjects'), '>0');

// ----------------------------------------------------------------- 60 admin
baseline_check(
	'the ban group has a reason',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}ban_groups WHERE reason <> {string:empty}', array('empty' => '')),
	'>0'
);

$ban_low = baseline_ip_families(baseline_column('SELECT ip_low FROM {db_prefix}ban_items WHERE ip_low IS NOT NULL'));
baseline_check('there is an IPv4 ban range', $ban_low['v4'], '>0');
baseline_check('there is an IPv6 ban range', $ban_low['v6'], '>0');

baseline_check(
	'there is an email ban',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}ban_items WHERE email_address <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'there is a member ban',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}ban_items WHERE id_member > 0'),
	'>0'
);

baseline_check(
	'ban hits were logged, with addresses',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_banned WHERE ip IS NOT NULL'),
	'>0'
);

baseline_check(
	'the installed package has a name and an id',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_packages WHERE name <> {string:empty} AND package_id <> {string:empty}', array('empty' => '')),
	'>0'
);

foreach (array('karmaMode' => '1', 'mail_type' => '1', 'cookieTime' => '3153600', 'enable_mod_prefs' => '1', 'time_offset' => '2') as $variable => $expected)
	baseline_check('legacy setting ' . $variable, baseline_setting($variable), $expected);

baseline_check(
	'smtp settings accompany mail_type',
	baseline_setting('smtp_host'),
	'mailpit'
);

baseline_check(
	'members have non-zero time offsets, both signs',
	baseline_scalar('SELECT COUNT(DISTINCT CASE WHEN time_offset > 0 THEN 1 ELSE 2 END) FROM {db_prefix}members WHERE time_offset <> 0'),
	2
);

baseline_check(
	'some members have no timezone',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}members WHERE timezone = {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'two-factor secrets are set',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}members WHERE tfa_secret <> {string:empty} AND tfa_backup <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'a group requires two-factor',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}membergroups WHERE tfa_required = 1'),
	'>0'
);

baseline_check(
	'group requests have reasons',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}log_group_requests WHERE reason <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'anti-spam questions have answers',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}qanda WHERE question <> {string:empty} AND answers <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check(
	'queued mail has a body and headers',
	baseline_scalar('SELECT COUNT(*) FROM {db_prefix}mail_queue WHERE body <> {string:empty} AND headers <> {string:empty}', array('empty' => '')),
	'>0'
);

baseline_check('boards have moderators', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}moderators'), '>0');
baseline_check('a board has a moderator group', baseline_scalar('SELECT COUNT(*) FROM {db_prefix}moderator_groups'), '>0');

// --------------------------------------------------------- 70 engine quirks
if ($is_postgres)
{
	baseline_check(
		'the install created its SQL functions',
		baseline_scalar('SELECT COUNT(*) FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = {string:schema}', array('schema' => 'public')),
		'>9'
	);

	baseline_check(
		'the install created its custom operators',
		baseline_scalar('SELECT COUNT(*) FROM pg_operator o JOIN pg_namespace n ON n.oid = o.oprnamespace WHERE n.nspname = {string:schema}', array('schema' => 'public')),
		2
	);

	baseline_check(
		'the install created its sequences',
		baseline_scalar('SELECT COUNT(*) FROM information_schema.sequences WHERE sequence_schema = {string:schema}', array('schema' => 'public')),
		'>34'
	);
}
else
{
	// Named explicitly rather than via DATABASE(): SMF's MySQL driver leaves the
	// connection with no default schema selected, so DATABASE() comes back empty
	// and every information_schema check silently matches nothing. That looks
	// exactly like a real failure, which is how this was found.
	global $db_name;

	baseline_check(
		'some tables are MyISAM, for ConvertToInnoDb to convert',
		baseline_scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = {string:db} AND engine = {string:engine}', array('db' => $db_name, 'engine' => 'MyISAM')),
		'>0'
	);

	baseline_check(
		'the database is utf8mb3, not utf8mb4',
		baseline_scalar('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = {string:db} AND default_character_set_name = {string:charset}', array('db' => $db_name, 'charset' => 'utf8mb3')),
		1
	);

	baseline_check(
		'the tables are utf8mb3 too',
		baseline_scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = {string:db} AND table_collation NOT LIKE {string:like}', array('db' => $db_name, 'like' => 'utf8mb3%')),
		0,
		'tables on some other collation'
	);
}

// ------------------------------------------------------------------ verdict
printf("\n[baseline] coverage: %d check(s), %d failure(s)\n", $checks, $failures);

exit($failures === 0 ? 0 : 1);

/**
 * Asserts one thing, and says so either way.
 *
 * @param string $label
 * @param mixed $actual
 * @param mixed $expected An exact value, or '>N' for a lower bound.
 * @param string $noun What a non-zero $actual would mean, when expecting 0.
 */
function baseline_check($label, $actual, $expected, $noun = '')
{
	global $checks, $failures;

	$checks++;

	if (is_string($expected) && strpos($expected, '>') === 0)
		$ok = (int) $actual > (int) substr($expected, 1);
	else
		$ok = (string) $actual === (string) $expected;

	if ($ok)
	{
		printf("  ok    %-52s %s\n", $label, $actual);

		return;
	}

	$failures++;

	printf(
		"  FAIL  %-52s got %s, expected %s%s\n",
		$label,
		var_export($actual, true),
		$expected,
		$noun === '' ? '' : ' (' . $noun . ')'
	);
}

/**
 * @param string $sql
 * @param array $params
 * @return string
 */
function baseline_scalar($sql, $params = array())
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', $sql, $params);

	if ($request === false)
		return 'query failed';

	list ($value) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $value;
}

/**
 * @param string $sql
 * @return array
 */
function baseline_column($sql)
{
	global $smcFunc;

	$values = array();
	$request = $smcFunc['db_query']('', $sql, array());

	while ($row = $smcFunc['db_fetch_row']($request))
		$values[] = $row[0];

	$smcFunc['db_free_result']($request);

	return $values;
}

/**
 * Sorts addresses into families, whichever way the engine hands them over.
 *
 * PostgreSQL returns a printable address from its inet columns. MySQL returns
 * the raw 4 or 16 bytes that inet_pton() produced, so it has to be converted
 * back before it means anything.
 *
 * @param array $values
 * @return array
 */
function baseline_ip_families($values)
{
	$families = array('v4' => 0, 'v6' => 0, 'bad' => 0);

	foreach ($values as $value)
	{
		if (filter_var($value, FILTER_VALIDATE_IP) !== false)
			$address = $value;
		else
			$address = @inet_ntop($value);

		if ($address === false || $address === null)
			$families['bad']++;
		elseif (strpos($address, ':') !== false)
			$families['v6']++;
		else
			$families['v4']++;
	}

	return $families;
}

/**
 * @param string $variable
 * @return string
 */
function baseline_setting($variable)
{
	return baseline_scalar(
		'SELECT value FROM {db_prefix}settings WHERE variable = {string:variable}',
		array('variable' => $variable)
	);
}
