<?php

/**
 * This file concerns itself with logging, whether in the database or files.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Put this user in the online log.
 *
 * @param bool $force Whether to force logging the data
 */
function writeLog($force = false)
{
	global $settings, $topic, $board;

	// If we are showing who is viewing a topic, let's see if we are, and force an update if so - to make it accurate.
	if (!empty($settings['display_who_viewing']) && ($topic || $board))
	{
		// Take the opposite approach!
		$force = true;
		// Don't update for every page - this isn't wholly accurate but who cares.
		if ($topic)
		{
			if (isset($_SESSION['last_topic_id']) && $_SESSION['last_topic_id'] == $topic)
				$force = false;
			$_SESSION['last_topic_id'] = $topic;
		}
	}

	// Are they a spider we should be tracking? Mode = 1 gets tracked on its spider check...
	if (!empty(User::$me->possibly_robot) && !empty(Config::$modSettings['spider_mode']) && Config::$modSettings['spider_mode'] > 1)
	{
		require_once(Config::$sourcedir . '/ManageSearchEngines.php');
		logSpider();
	}

	// Don't mark them as online more than every so often.
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= (time() - 8) && !$force)
		return;

	if (!empty(Config::$modSettings['who_enabled']))
	{
		$encoded_get = truncate_array($_GET) + array('USER_AGENT' => mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 128));

		// In the case of a dlattach action, session_var may not be set.
		if (!isset(Utils::$context['session_var']))
			Utils::$context['session_var'] = $_SESSION['session_var'];

		unset($encoded_get['sesc'], $encoded_get[Utils::$context['session_var']]);
		$encoded_get = Utils::jsonEncode($encoded_get);

		// Sometimes folks mess with USER_AGENT & $_GET data, so one last check to avoid 'data too long' errors
		if (mb_strlen($encoded_get) > 2048)
			$encoded_get = '';
	}
	else
		$encoded_get = '';

	// Guests use their IP address, members use their session ID.
	$session_id = User::$me->is_guest ? 'ip' . User::$me->ip : session_id();

	// Grab the last all-of-SMF-specific log_online deletion time.
	$do_delete = CacheApi::get('log_online-update', 30) < time() - 30;

	// If the last click wasn't a long time ago, and there was a last click...
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - Config::$modSettings['lastActive'] * 20)
	{
		if ($do_delete)
		{
			Db::$db->query('delete_log_online_interval', '
				DELETE FROM {db_prefix}log_online
				WHERE log_time < {int:log_time}
					AND session != {string:session}',
				array(
					'log_time' => time() - Config::$modSettings['lastActive'] * 60,
					'session' => $session_id,
				)
			);

			// Cache when we did it last.
			CacheApi::put('log_online-update', time(), 30);
		}

		Db::$db->query('', '
			UPDATE {db_prefix}log_online
			SET log_time = {int:log_time}, ip = {inet:ip}, url = {string:url}
			WHERE session = {string:session}',
			array(
				'log_time' => time(),
				'ip' => User::$me->ip,
				'url' => $encoded_get,
				'session' => $session_id,
			)
		);

		// Guess it got deleted.
		if (Db::$db->affected_rows() == 0)
			$_SESSION['log_time'] = 0;
	}
	else
		$_SESSION['log_time'] = 0;

	// Otherwise, we have to delete and insert.
	if (empty($_SESSION['log_time']))
	{
		if ($do_delete || !empty(User::$me->id))
			Db::$db->query('', '
				DELETE FROM {db_prefix}log_online
				WHERE ' . ($do_delete ? 'log_time < {int:log_time}' : '') . ($do_delete && !empty(User::$me->id) ? ' OR ' : '') . (empty(User::$me->id) ? '' : 'id_member = {int:current_member}'),
				array(
					'current_member' => User::$me->id,
					'log_time' => time() - Config::$modSettings['lastActive'] * 60,
				)
			);

		Db::$db->insert($do_delete ? 'ignore' : 'replace',
			'{db_prefix}log_online',
			array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'inet', 'url' => 'string'),
			array($session_id, User::$me->id, empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), User::$me->ip, $encoded_get),
			array('session')
		);
	}

	// Mark your session as being logged.
	$_SESSION['log_time'] = time();

	// Well, they are online now.
	if (empty($_SESSION['timeOnlineUpdated']))
		$_SESSION['timeOnlineUpdated'] = time();

	// Set their login time, if not already done within the last minute.
	if (SMF != 'SSI' && !empty(User::$me->last_login) && User::$me->last_login < time() - 60 && (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], array('.xml', 'login2', 'logintfa'))))
	{
		// Don't count longer than 15 minutes.
		if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15)
			$_SESSION['timeOnlineUpdated'] = time();

		User::$me->total_time_logged_in += (time() - $_SESSION['timeOnlineUpdated']);

		User::updateMemberData(User::$me->id, array('last_login' => time(), 'member_ip' => User::$me->ip, 'member_ip2' => $_SERVER['BAN_CHECK_IP'], 'total_time_logged_in' => User::$me->total_time_logged_in));

		if (!empty(CacheApi::$enable) && CacheApi::$enable >= 2)
			CacheApi::put('user_settings-' . User::$me->id, User::$profiles[User::$me->id], 60);

		$_SESSION['timeOnlineUpdated'] = time();
	}
}

/**
 * Logs the last database error into a file.
 * Attempts to use the backup file first, to store the last database error
 * and only update db_last_error.php if the first was successful.
 */
function logLastDatabaseError()
{
	// Make a note of the last modified time in case someone does this before us
	$last_db_error_change = @filemtime(Config::$cachedir . '/db_last_error.php');

	// save the old file before we do anything
	$file = Config::$cachedir . '/db_last_error.php';
	$dberror_backup_fail = !@is_writable(Config::$cachedir . '/db_last_error_bak.php') || !@copy($file, Config::$cachedir . '/db_last_error_bak.php');
	$dberror_backup_fail = !$dberror_backup_fail ? (!file_exists(Config::$cachedir . '/db_last_error_bak.php') || filesize(Config::$cachedir . '/db_last_error_bak.php') === 0) : $dberror_backup_fail;

	clearstatcache();
	if (filemtime(Config::$cachedir . '/db_last_error.php') === $last_db_error_change)
	{
		// Write the change
		$write_db_change = '<' . '?' . "php\n" . '$db_last_error = ' . time() . ';' . "\n" . '?' . '>';
		$written_bytes = file_put_contents(Config::$cachedir . '/db_last_error.php', $write_db_change, LOCK_EX);

		// survey says ...
		if ($written_bytes !== strlen($write_db_change) && !$dberror_backup_fail)
		{
			// Oops. maybe we have no more disk space left, or some other troubles, troubles...
			// Copy the file back and run for your life!
			@copy(Config::$cachedir . '/db_last_error_bak.php', Config::$cachedir . '/db_last_error.php');
		}
		else
		{
			@touch(SMF_SETTINGS_FILE);
			return true;
		}
	}

	return false;
}

/**
 * This function shows the debug information tracked when Config::$db_show_debug = true
 * in Settings.php
 */
function displayDebug()
{
	global $settings;

	// Add to Settings.php if you want to show the debugging information.
	if (!isset(Config::$db_show_debug) || Config::$db_show_debug !== true || (isset($_GET['action']) && $_GET['action'] == 'viewquery'))
		return;

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;
	if (empty(Utils::$context['debug']['language_files']))
		Utils::$context['debug']['language_files'] = array();
	if (empty(Utils::$context['debug']['sheets']))
		Utils::$context['debug']['sheets'] = array();

	$files = get_included_files();
	$total_size = 0;
	for ($i = 0, $n = count($files); $i < $n; $i++)
	{
		if (file_exists($files[$i]))
			$total_size += filesize($files[$i]);
		$files[$i] = strtr($files[$i], array(Config::$boarddir => '.', Config::$sourcedir => '(Sources)', Config::$cachedir => '(Cache)', $settings['actual_theme_dir'] => '(Current Theme)'));
	}

	$warnings = 0;
	if (!empty(Db::$cache))
	{
		foreach (Db::$cache as $q => $query_data)
		{
			if (!empty($query_data['w']))
				$warnings += count($query_data['w']);
		}

		$_SESSION['debug'] = &Db::$cache;
	}

	// Gotta have valid HTML ;).
	$temp = ob_get_contents();
	ob_clean();

	echo preg_replace('~</body>\s*</html>~', '', $temp), '
<div class="smalltext" style="text-align: left; margin: 1ex;">
	', Lang::$txt['debug_browser'], Utils::$context['browser_body_id'], ' <em>(', implode('</em>, <em>', array_reverse(array_keys(Utils::$context['browser'], true))), ')</em><br>
	', Lang::$txt['debug_templates'], count(Utils::$context['debug']['templates']), ': <em>', implode('</em>, <em>', Utils::$context['debug']['templates']), '</em>.<br>
	', Lang::$txt['debug_subtemplates'], count(Utils::$context['debug']['sub_templates']), ': <em>', implode('</em>, <em>', Utils::$context['debug']['sub_templates']), '</em>.<br>
	', Lang::$txt['debug_language_files'], count(Utils::$context['debug']['language_files']), ': <em>', implode('</em>, <em>', Utils::$context['debug']['language_files']), '</em>.<br>
	', Lang::$txt['debug_stylesheets'], count(Utils::$context['debug']['sheets']), ': <em>', implode('</em>, <em>', Utils::$context['debug']['sheets']), '</em>.<br>
	', Lang::$txt['debug_hooks'], empty(Utils::$context['debug']['hooks']) ? 0 : count(Utils::$context['debug']['hooks']) . ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_hooks\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', Lang::$txt['debug_show'], '</a><span id="debug_hooks" style="display: none;"><em>' . implode('</em>, <em>', Utils::$context['debug']['hooks']), '</em></span>)', '<br>
	', (isset(Utils::$context['debug']['instances']) ? (Lang::$txt['debug_instances'] . (empty(Utils::$context['debug']['instances']) ? 0 : count(Utils::$context['debug']['instances'])) . ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_instances\').style.display = \'inline\'; this.style.display = \'none\'; return false;">' . Lang::$txt['debug_show'] . '</a><span id="debug_instances" style="display: none;"><em>' . implode('</em>, <em>', array_keys(Utils::$context['debug']['instances'])) . '</em></span>)' . '<br>') : ''), '
	', Lang::$txt['debug_files_included'], count($files), ' - ', round($total_size / 1024), Lang::$txt['debug_kb'], ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_include_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', Lang::$txt['debug_show'], '</a><span id="debug_include_info" style="display: none;"><em>', implode('</em>, <em>', $files), '</em></span>)<br>';

	if (function_exists('memory_get_peak_usage'))
		echo Lang::$txt['debug_memory_use'], ceil(memory_get_peak_usage() / 1024), Lang::$txt['debug_kb'], '<br>';

	// What tokens are active?
	if (isset($_SESSION['token']))
		echo Lang::$txt['debug_tokens'] . '<em>' . implode(',</em> <em>', array_keys($_SESSION['token'])), '</em>.<br>';

	if (!empty(CacheApi::$enable) && !empty(CacheApi::$hits))
	{
		$missed_entries = array();
		$entries = array();
		$total_t = 0;
		$total_s = 0;
		foreach (CacheApi::$hits as $cache_hit)
		{
			$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . sprintf(Lang::$txt['debug_cache_seconds_bytes'], Lang::numberFormat($cache_hit['t'], 5), $cache_hit['s']);
			$total_t += $cache_hit['t'];
			$total_s += $cache_hit['s'];
		}
		if (!isset(CacheApi::$misses))
			CacheApi::$misses = array();
		foreach (CacheApi::$misses as $missed)
			$missed_entries[] = $missed['d'] . ' ' . $missed['k'];

		echo '
	', Lang::$txt['debug_cache_hits'], CacheApi::$count_hits, ': ', sprintf(Lang::$txt['debug_cache_seconds_bytes_total'], Lang::numberFormat($total_t, 5), Lang::numberFormat($total_s)), ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_cache_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', Lang::$txt['debug_show'], '</a><span id="debug_cache_info" style="display: none;"><em>', implode('</em>, <em>', $entries), '</em></span>)<br>
	', Lang::$txt['debug_cache_misses'], CacheApi::$count_misses, ': (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_cache_misses_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', Lang::$txt['debug_show'], '</a><span id="debug_cache_misses_info" style="display: none;"><em>', implode('</em>, <em>', $missed_entries), '</em></span>)<br>';
	}

	echo '
	<a href="', Config::$scripturl, '?action=viewquery" target="_blank" rel="noopener">', $warnings == 0 ? sprintf(Lang::$txt['debug_queries_used'], (int) Db::$count) : sprintf(Lang::$txt['debug_queries_used_and_warnings'], (int) Db::$count, $warnings), '</a><br>
	<br>';

	if ($_SESSION['view_queries'] == 1 && !empty(Db::$cache))
		foreach (Db::$cache as $q => $query_data)
		{
			$is_select = strpos(trim($query_data['q']), 'SELECT') === 0 || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($query_data['q'])) != 0 || strpos(trim($query_data['q']), 'WITH') === 0;
			// Temporary tables created in earlier queries are not explainable.
			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($query_data['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}
			// But actual creation of the temporary tables are.
			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($query_data['q'])) != 0)
				$is_select = true;

			// Make the filenames look a bit better.
			if (isset($query_data['f']))
				$query_data['f'] = preg_replace('~^' . preg_quote(Config::$boarddir, '~') . '~', '...', $query_data['f']);

			echo '
	<strong>', $is_select ? '<a href="' . Config::$scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" rel="noopener" style="text-decoration: none;">' : '', nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', Utils::htmlspecialchars(ltrim($query_data['q'], "\n\r")))) . ($is_select ? '</a></strong>' : '</strong>') . '<br>
	&nbsp;&nbsp;&nbsp;';
			if (!empty($query_data['f']) && !empty($query_data['l']))
				echo sprintf(Lang::$txt['debug_query_in_line'], $query_data['f'], $query_data['l']);

			if (isset($query_data['s'], $query_data['t']) && isset(Lang::$txt['debug_query_which_took_at']))
				echo sprintf(Lang::$txt['debug_query_which_took_at'], round($query_data['t'], 8), round($query_data['s'], 8)) . '<br>';
			elseif (isset($query_data['t']))
				echo sprintf(Lang::$txt['debug_query_which_took'], round($query_data['t'], 8)) . '<br>';
			echo '
	<br>';
		}

	echo '
	<a href="' . Config::$scripturl . '?action=viewquery;sa=hide">', Lang::$txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'], '</a>
</div></body></html>';
}

/**
 * Track Statistics.
 * Caches statistics changes, and flushes them if you pass nothing.
 * If '+' is used as a value, it will be incremented.
 * It does not actually commit the changes until the end of the page view.
 * It depends on the trackStats setting.
 *
 * @param array $stats An array of data
 * @return bool Whether or not the info was updated successfully
 */
function trackStats($stats = array())
{
	static $cache_stats = array();

	if (empty(Config::$modSettings['trackStats']))
		return false;
	if (!empty($stats))
		return $cache_stats = array_merge($cache_stats, $stats);
	elseif (empty($cache_stats))
		return false;

	$setStringUpdate = '';
	$insert_keys = array();
	$date = smf_strftime('%Y-%m-%d', time());
	$update_parameters = array(
		'current_date' => $date,
	);
	foreach ($cache_stats as $field => $change)
	{
		$setStringUpdate .= '
			' . $field . ' = ' . ($change === '+' ? $field . ' + 1' : '{int:' . $field . '}') . ',';

		if ($change === '+')
			$cache_stats[$field] = 1;
		else
			$update_parameters[$field] = $change;
		$insert_keys[$field] = 'int';
	}

	Db::$db->query('', '
		UPDATE {db_prefix}log_activity
		SET' . substr($setStringUpdate, 0, -1) . '
		WHERE date = {date:current_date}',
		$update_parameters
	);
	if (Db::$db->affected_rows() == 0)
	{
		Db::$db->insert('ignore',
			'{db_prefix}log_activity',
			array_merge($insert_keys, array('date' => 'date')),
			array_merge($cache_stats, array($date)),
			array('date')
		);
	}

	// Don't do this again.
	$cache_stats = array();

	return true;
}

/**
 * This function logs an action to the database. It is a
 * thin wrapper around {@link logActions()}.
 *
 * @example logAction('remove', array('starter' => $id_member_started));
 *
 * @param string $action A code for the report; a list of such strings
 * can be found in Modlog.{language}.php (modlog_ac_ strings)
 * @param array $extra An associated array of parameters for the
 * item being logged. Typically this will include 'topic' for the topic's id.
 * @param string $log_type A string reflecting the type of log.
 *
 * @return int The ID of the row containing the logged data
 */
function logAction($action, array $extra = array(), $log_type = 'moderate')
{
	return logActions(array(array(
		'action' => $action,
		'log_type' => $log_type,
		'extra' => $extra,
	)));
}

/**
 * Log changes to the forum, such as moderation events or administrative
 * changes. This behaves just like {@link logAction()} in SMF 2.0, except
 * that it is designed to log multiple actions at once.
 *
 * SMF uses three log types:
 *
 * - `user` for actions executed that aren't related to
 *    moderation (e.g. signature or other changes from the profile);
 * - `moderate` for moderation actions (e.g. topic changes);
 * - `admin` for administrative actions.
 *
 * @param array $logs An array of log data
 *
 * @return int The last logged ID
 */
function logActions(array $logs)
{
	$inserts = array();
	$log_types = array(
		'moderate' => 1,
		'user' => 2,
		'admin' => 3,
	);
	$always_log = array('agreement_accepted', 'policy_accepted', 'agreement_updated', 'policy_updated');

	call_integration_hook('integrate_log_types', array(&$log_types, &$always_log));

	foreach ($logs as $log)
	{
		if (!isset($log_types[$log['log_type']]) && (empty(Config::$modSettings[$log['log_type'] . 'log_enabled']) || !in_array($log['action'], $always_log)))
			continue;

		if (!is_array($log['extra']))
		{
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['logActions_not_array'], $log['action']), E_USER_NOTICE);
		}

		// Pull out the parts we want to store separately, but also make sure that the data is proper
		if (isset($log['extra']['topic']))
		{
			if (!is_numeric($log['extra']['topic']))
			{
				Lang::load('Errors');
				trigger_error(Lang::$txt['logActions_topic_not_numeric'], E_USER_NOTICE);
			}
			$topic_id = empty($log['extra']['topic']) ? 0 : (int) $log['extra']['topic'];
			unset($log['extra']['topic']);
		}
		else
			$topic_id = 0;

		if (isset($log['extra']['message']))
		{
			if (!is_numeric($log['extra']['message']))
			{
				Lang::load('Errors');
				trigger_error(Lang::$txt['logActions_message_not_numeric'], E_USER_NOTICE);
			}
			$msg_id = empty($log['extra']['message']) ? 0 : (int) $log['extra']['message'];
			unset($log['extra']['message']);
		}
		else
			$msg_id = 0;

		// @todo cache this?
		// Is there an associated report on this?
		if (in_array($log['action'], array('move', 'remove', 'split', 'merge')))
		{
			$request = Db::$db->query('', '
				SELECT id_report
				FROM {db_prefix}log_reported
				WHERE {raw:column_name} = {int:reported}
				LIMIT 1',
				array(
					'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
					'reported' => !empty($msg_id) ? $msg_id : $topic_id,
				)
			);

			// Alright, if we get any result back, update open reports.
			if (Db::$db->num_rows($request) > 0)
			{
				require_once(Config::$sourcedir . '/Subs-ReportedContent.php');
				Config::updateModSettings(array('last_mod_report_action' => time()));
				recountOpenReports('posts');
			}
			Db::$db->free_result($request);
		}

		if (isset($log['extra']['member']) && !is_numeric($log['extra']['member']))
		{
			Lang::load('Errors');
			trigger_error(Lang::$txt['logActions_member_not_numeric'], E_USER_NOTICE);
		}

		if (isset($log['extra']['board']))
		{
			if (!is_numeric($log['extra']['board']))
			{
				Lang::load('Errors');
				trigger_error(Lang::$txt['logActions_board_not_numeric'], E_USER_NOTICE);
			}
			$board_id = empty($log['extra']['board']) ? 0 : (int) $log['extra']['board'];
			unset($log['extra']['board']);
		}
		else
			$board_id = 0;

		if (isset($log['extra']['board_to']))
		{
			if (!is_numeric($log['extra']['board_to']))
			{
				Lang::load('Errors');
				trigger_error(Lang::$txt['logActions_board_to_not_numeric'], E_USER_NOTICE);
			}
			if (empty($board_id))
			{
				$board_id = empty($log['extra']['board_to']) ? 0 : (int) $log['extra']['board_to'];
				unset($log['extra']['board_to']);
			}
		}

		if (isset($log['extra']['member_affected']))
			$memID = $log['extra']['member_affected'];
		else
			$memID = User::$me->id ?? $log['extra']['member'] ?? 0;

		if (isset(User::$me->ip))
			$memIP = User::$me->ip;
		else
			$memIP = 'null';

		$inserts[] = array(
			time(), $log_types[$log['log_type']], $memID, $memIP, $log['action'],
			$board_id, $topic_id, $msg_id, Utils::jsonEncode($log['extra']),
		);
	}

	$id_action = Db::$db->insert('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'inet', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		$inserts,
		array('id_action'),
		1
	);

	return $id_action;
}

?>