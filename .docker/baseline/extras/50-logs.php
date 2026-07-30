<?php

/**
 * The log tables.
 *
 * Almost every log table in SMF 2.1 has an IP column, and SMF 3.0 has a
 * separate migration for each one. On MySQL those columns are VARBINARY(16)
 * holding raw inet_pton() output and the conversion is real work; on PostgreSQL
 * they are already inet and the migrations should skip. Either way, a table
 * with no rows in it proves nothing, and a fresh install leaves every one of
 * these empty.
 *
 * Two other columns are worth seeding while we are here: log_errors.backtrace
 * and log_errors.session, both of which SMF 3.0 changes, and both of which have
 * a meaningful empty case that ought to appear alongside the populated one.
 *
 * Exercises: Ipv6LogErrors, Ipv6LogAction, Ipv6LogOnline, Ipv6LogFloodControl,
 * Ipv6LogReportedComments, LogErrorsBacktrace, LogOnlineURL,
 * LogReportedCommentsEmail, LogSpiderHitsURL, IdxLogActivity, IdxLogComments,
 * v3_0\ErrorLogSession, v3_0\SearchResultsPrimaryKey.
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

$baseline_name = '50-logs';

if (baseline_applied($baseline_name) && empty($baseline_force))
{
	baseline_say($baseline_name . ': skipped');
}
else
{
	global $smcFunc;

	$now = time();
	$members = baseline_member_ids(20);
	$made = array();

	// ------------------------------------------------------------ error log
	$error_types = array('general', 'critical', 'database', 'undefined_vars', 'user');
	$errors = array();

	for ($i = 0; $i < 12; $i++)
	{
		$has_context = $i % 4 !== 0;

		$errors[] = array(
			$now - ($i * 3600),
			$i % 3 === 0 ? 0 : $members[$i % count($members)],
			baseline_ip($i),
			'http://localhost/index.php?action=baseline;error=' . $i,
			'Baseline error number ' . $i,
			// A quarter of them carry no session and no backtrace. Both columns
			// change in 3.0, and the empty case is the one likely to be missed.
			$has_context ? substr(md5('baseline session ' . $i), 0, 32) : '',
			$error_types[$i % count($error_types)],
			$has_context ? 'Sources/Baseline.php' : '',
			$has_context ? (100 + $i) : 0,
			$has_context ? json_encode(array(array('file' => 'Sources/Baseline.php', 'line' => 100 + $i, 'function' => 'baseline_example'))) : '',
		);
	}

	$smcFunc['db_insert']('insert',
		'{db_prefix}log_errors',
		array(
			'log_time' => 'int', 'id_member' => 'int', 'ip' => 'inet', 'url' => 'string-65534',
			'message' => 'string-65534', 'session' => 'string-128', 'error_type' => 'string',
			'file' => 'string-255', 'line' => 'int', 'backtrace' => 'string-65534',
		),
		$errors,
		array('id_error')
	);

	$made['errors'] = count($errors);

	// ----------------------------------------------------------- action log
	// id_log 1 is the moderation log, 3 the administration log.
	$actions = array();

	for ($i = 0; $i < 20; $i++)
	{
		$actions[] = array(
			$i % 2 === 0 ? 1 : 3,
			$now - ($i * 1800),
			$members[$i % count($members)],
			baseline_ip($i + 1),
			$i % 2 === 0 ? 'remove' : 'change_settings',
			0, 0, 0,
			json_encode(array('baseline' => true, 'sequence' => $i)),
		);
	}

	$smcFunc['db_insert']('insert',
		'{db_prefix}log_actions',
		array(
			'id_log' => 'int', 'log_time' => 'int', 'id_member' => 'int', 'ip' => 'inet',
			'action' => 'string-30', 'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int',
			'extra' => 'string-65534',
		),
		$actions,
		array('id_action')
	);

	$made['actions'] = count($actions);

	// ------------------------------------------------------------ who's online
	$online = array();

	for ($i = 0; $i < 8; $i++)
	{
		$online[] = array(
			substr(md5('baseline online ' . $i), 0, 32),
			$now - ($i * 60),
			$i % 4 === 0 ? 0 : $members[$i % count($members)],
			0,
			baseline_ip($i + 2),
			json_encode(array('action' => 'baseline', 'page' => $i)),
		);
	}

	$smcFunc['db_insert']('replace',
		'{db_prefix}log_online',
		array(
			'session' => 'string-128', 'log_time' => 'int', 'id_member' => 'int',
			'id_spider' => 'int', 'ip' => 'inet', 'url' => 'string-2048',
		),
		$online,
		array('session')
	);

	$made['online'] = count($online);

	// --------------------------------------------------------- reported posts
	$reports = array();
	$messages = baseline_message_ids(3);

	$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.id_topic, m.id_board, m.id_member, m.subject, m.body
		FROM {db_prefix}messages AS m
		WHERE m.id_msg IN ({array_int:messages})',
		array('messages' => $messages)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$reports[] = $row;

	$smcFunc['db_free_result']($request);

	$made['reports'] = 0;
	$made['comments'] = 0;

	foreach ($reports as $index => $message)
	{
		$id_report = $smcFunc['db_insert']('',
			'{db_prefix}log_reported',
			array(
				'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int',
				'membername' => 'string-255', 'subject' => 'string-255', 'body' => 'string-65534',
				'time_started' => 'int', 'time_updated' => 'int', 'num_reports' => 'int',
				'closed' => 'int', 'ignore_all' => 'int',
			),
			array(
				(int) $message['id_msg'], (int) $message['id_topic'], (int) $message['id_board'],
				(int) $message['id_member'], 'Member ' . $message['id_member'],
				$message['subject'], substr($message['body'], 0, 500),
				$now - 86400, $now - 3600, 2,
				$index === 2 ? 1 : 0, 0,
			),
			array('id_report'),
			1
		);

		$made['reports']++;

		$comments = array();

		for ($i = 0; $i < 2; $i++)
		{
			$comments[] = array(
				$id_report,
				$members[($index + $i) % count($members)],
				'Member ' . $members[($index + $i) % count($members)],
				baseline_ip($index + $i),
				'This post looks like generated lorem ipsum to me.',
				$now - (3600 * ($i + 1)),
			);
		}

		$smcFunc['db_insert']('insert',
			'{db_prefix}log_reported_comments',
			array(
				'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string-255',
				'member_ip' => 'inet', 'comment' => 'string-65534', 'time_sent' => 'int',
			),
			$comments,
			array('id_comment')
		);

		$made['comments'] += count($comments);
	}

	// -------------------------------------------------------- flood control
	$flood = array();

	for ($i = 0; $i < 6; $i++)
	{
		$ip = baseline_ip($i);

		// The primary key is the address itself, so a row with no address is
		// not a thing this table can hold.
		if ($ip === '')
			continue;

		$flood[] = array($ip, $now - $i, $i % 2 === 0 ? 'post' : 'register');
	}

	$smcFunc['db_insert']('replace',
		'{db_prefix}log_floodcontrol',
		array('ip' => 'inet', 'log_time' => 'int', 'log_type' => 'string-30'),
		$flood,
		array('ip', 'log_type')
	);

	$made['flood'] = count($flood);

	// --------------------------------------------------------- spider hits
	$spiders = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_spider
		FROM {db_prefix}spiders
		ORDER BY id_spider
		LIMIT {int:limit}',
		array('limit' => 1)
	);

	$spider = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$made['spider_hits'] = 0;

	if (!empty($spider))
	{
		$hits = array();

		for ($i = 0; $i < 5; $i++)
			$hits[] = array((int) $spider['id_spider'], $now - ($i * 900), 'index.php?board=' . ($i + 1) . '.0', 0);

		$smcFunc['db_insert']('insert',
			'{db_prefix}log_spider_hits',
			array('id_spider' => 'int', 'log_time' => 'int', 'url' => 'string-255', 'processed' => 'int'),
			$hits,
			array('id_hit')
		);

		$made['spider_hits'] = count($hits);
	}

	// ------------------------------------------------------------ search log
	// SMF 3.0 gives log_search_results a primary key, which is a schema change
	// that runs either way -- but rebuilding a table that has rows in it is the
	// case worth testing.
	$subjects = array();
	$search_topics = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_topic
		FROM {db_prefix}topics
		ORDER BY id_topic
		LIMIT {int:limit}',
		array('limit' => 10)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$subjects[] = array('lorem', (int) $row['id_topic']);
		$search_topics[] = array(1, (int) $row['id_topic']);
	}

	$smcFunc['db_free_result']($request);

	$smcFunc['db_insert']('ignore',
		'{db_prefix}log_search_subjects',
		array('word' => 'string-20', 'id_topic' => 'int'),
		$subjects,
		array('word', 'id_topic')
	);

	$smcFunc['db_insert']('ignore',
		'{db_prefix}log_search_topics',
		array('id_search' => 'int', 'id_topic' => 'int'),
		$search_topics,
		array('id_search', 'id_topic')
	);

	$made['search'] = count($subjects);

	$summary = array();

	foreach ($made as $what => $count)
		$summary[] = $count . ' ' . $what;

	baseline_say($baseline_name . ': ' . implode(', ', $summary));

	baseline_mark_applied($baseline_name);
}
