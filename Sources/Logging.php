<?php

/**
 * This file concerns itself with logging, whether in the database or files.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Put this user in the online log.
 *
 * @param bool $force = false
 */
function writeLog($force = false)
{
	global $user_info, $user_settings, $context, $modSettings, $settings, $topic, $board, $smcFunc, $sourcedir;

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
	if (!empty($user_info['possibly_robot']) && !empty($modSettings['spider_mode']) && $modSettings['spider_mode'] > 1)
	{
		require_once($sourcedir . '/ManageSearchEngines.php');
		logSpider();
	}

	// Don't mark them as online more than every so often.
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= (time() - 8) && !$force)
		return;

	if (!empty($modSettings['who_enabled']))
	{
		$serialized = $_GET + array('USER_AGENT' => $_SERVER['HTTP_USER_AGENT']);

		// In the case of a dlattach action, session_var may not be set.
		if (!isset($context['session_var']))
			$context['session_var'] = $_SESSION['session_var'];

		unset($serialized['sesc'], $serialized[$context['session_var']]);
		$serialized = serialize($serialized);
	}
	else
		$serialized = '';

	// Guests use 0, members use their session ID.
	$session_id = $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id();

	// Grab the last all-of-SMF-specific log_online deletion time.
	$do_delete = cache_get_data('log_online-update', 30) < time() - 30;

	// If the last click wasn't a long time ago, and there was a last click...
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - $modSettings['lastActive'] * 20)
	{
		if ($do_delete)
		{
			$smcFunc['db_query']('delete_log_online_interval', '
				DELETE FROM {db_prefix}log_online
				WHERE log_time < {int:log_time}
					AND session != {string:session}',
				array(
					'log_time' => time() - $modSettings['lastActive'] * 60,
					'session' => $session_id,
				)
			);

			// Cache when we did it last.
			cache_put_data('log_online-update', time(), 30);
		}

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_online
			SET log_time = {int:log_time}, ip = IFNULL(INET_ATON({string:ip}), 0), url = {string:url}
			WHERE session = {string:session}',
			array(
				'log_time' => time(),
				'ip' => $user_info['ip'],
				'url' => $serialized,
				'session' => $session_id,
			)
		);

		// Guess it got deleted.
		if ($smcFunc['db_affected_rows']() == 0)
			$_SESSION['log_time'] = 0;
	}
	else
		$_SESSION['log_time'] = 0;

	// Otherwise, we have to delete and insert.
	if (empty($_SESSION['log_time']))
	{
		if ($do_delete || !empty($user_info['id']))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_online
				WHERE ' . ($do_delete ? 'log_time < {int:log_time}' : '') . ($do_delete && !empty($user_info['id']) ? ' OR ' : '') . (empty($user_info['id']) ? '' : 'id_member = {int:current_member}'),
				array(
					'current_member' => $user_info['id'],
					'log_time' => time() - $modSettings['lastActive'] * 60,
				)
			);

		$smcFunc['db_insert']($do_delete ? 'ignore' : 'replace',
			'{db_prefix}log_online',
			array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'raw', 'url' => 'string'),
			array($session_id, $user_info['id'], empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), 'IFNULL(INET_ATON(\'' . $user_info['ip'] . '\'), 0)', $serialized),
			array('session')
		);
	}

	// Mark your session as being logged.
	$_SESSION['log_time'] = time();

	// Well, they are online now.
	if (empty($_SESSION['timeOnlineUpdated']))
		$_SESSION['timeOnlineUpdated'] = time();

	// Set their login time, if not already done within the last minute.
	if (SMF != 'SSI' && !empty($user_info['last_login']) && $user_info['last_login'] < time() - 60)
	{
		// Don't count longer than 15 minutes.
		if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15)
			$_SESSION['timeOnlineUpdated'] = time();

		$user_settings['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP'], 'total_time_logged_in' => $user_settings['total_time_logged_in']));

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('user_settings-' . $user_info['id'], $user_settings, 60);

		$user_info['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		$_SESSION['timeOnlineUpdated'] = time();
	}
}

/**
 * Logs the last database error into a file.
 * Attempts to use the backup file first, to store the last database error
 * and only update Settings.php if the first was successful.
 */
function logLastDatabaseError()
{
	global $boarddir;

	// Find out this way if we can even write things on this filesystem.
	// In addition, store things first in the backup file

	$last_settings_change = @filemtime($boarddir . '/Settings.php');

	// Make sure the backup file is there...
	$file = $boarddir . '/Settings_bak.php';
	if ((!file_exists($file) || filesize($file) == 0) && !copy($boarddir . '/Settings.php', $file))
			return false;

	// ...and writable!
	if (!is_writable($file))
	{
		chmod($file, 0755);
		if (!is_writable($file))
		{
			chmod($file, 0775);
			if (!is_writable($file))
			{
				chmod($file, 0777);
				if (!is_writable($file))
						return false;
			}
		}
	}

	// Put the new timestamp.
	$data = file_get_contents($file);
	$data = preg_replace('~\$db_last_error = \d+;~', '$db_last_error = ' . time() . ';', $data);

	// Open the backup file for writing
	if ($fp = @fopen($file, 'w'))
	{
		// Reset the file buffer.
		set_file_buffer($fp, 0);

		// Update the file.
		$t = flock($fp, LOCK_EX);
		$bytes = fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);

		// Was it a success?
		// ...only relevant if we're still dealing with the same good ole' settings file.
		clearstatcache();
		if (($bytes == strlen($data)) && (filemtime($boarddir . '/Settings.php') === $last_settings_change))
		{
			// This is our new Settings file...
			// At least this one is an atomic operation
			@copy($file, $boarddir . '/Settings.php');
			return true;
		}
		else
		{
			// Oops. Someone might have been faster
			// or we have no more disk space left, troubles, troubles...
			// Copy the file back and run for your life!
			@copy($boarddir . '/Settings.php', $file);
		}
	}

	return false;
}

/**
 * This function shows the debug information tracked when $db_show_debug = true
 * in Settings.php
 */
function displayDebug()
{
	global $context, $scripturl, $boarddir, $modSettings, $boarddir;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits, $txt;

	// Add to Settings.php if you want to show the debugging information.
	if (!isset($db_show_debug) || $db_show_debug !== true || (isset($_GET['action']) && $_GET['action'] == 'viewquery') || WIRELESS)
		return;

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;
	if (empty($context['debug']['language_files']))
		$context['debug']['language_files'] = array();
	if (empty($context['debug']['sheets']))
		$context['debug']['sheets'] = array();

	$files = get_included_files();
	$total_size = 0;
	for ($i = 0, $n = count($files); $i < $n; $i++)
	{
		if (file_exists($files[$i]))
			$total_size += filesize($files[$i]);
		$files[$i] = strtr($files[$i], array($boarddir => '.'));
	}

	$warnings = 0;
	if (!empty($db_cache))
	{
		foreach ($db_cache as $q => $qq)
		{
			if (!empty($qq['w']))
				$warnings += count($qq['w']);
		}

		$_SESSION['debug'] = &$db_cache;
	}

	// Gotta have valid HTML ;).
	$temp = ob_get_contents();
	ob_clean();

	echo preg_replace('~</body>\s*</html>~', '', $temp), '
<div class="smalltext" style="text-align: left; margin: 1ex;">
	', $txt['debug_templates'], count($context['debug']['templates']), ': <em>', implode('</em>, <em>', $context['debug']['templates']), '</em>.<br />
	', $txt['debug_subtemplates'], count($context['debug']['sub_templates']), ': <em>', implode('</em>, <em>', $context['debug']['sub_templates']), '</em>.<br />
	', $txt['debug_language_files'], count($context['debug']['language_files']), ': <em>', implode('</em>, <em>', $context['debug']['language_files']), '</em>.<br />
	', $txt['debug_stylesheets'], count($context['debug']['sheets']), ': <em>', implode('</em>, <em>', $context['debug']['sheets']), '</em>.<br />
	', $txt['debug_files_included'], count($files), ' - ', round($total_size / 1024), $txt['debug_kb'], ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_include_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_include_info" style="display: none;"><em>', implode('</em>, <em>', $files), '</em></span>)<br />';

	if (!empty($modSettings['cache_enable']) && !empty($cache_hits))
	{
		$entries = array();
		$total_t = 0;
		$total_s = 0;
		foreach ($cache_hits as $cache_hit)
		{
			$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . sprintf($txt['debug_cache_seconds_bytes'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
			$total_t += $cache_hit['t'];
			$total_s += $cache_hit['s'];
		}

		echo '
	', $txt['debug_cache_hits'], $cache_count, ': ', sprintf($txt['debug_cache_seconds_bytes_total'], comma_format($total_t, 5), comma_format($total_s)), ' (<a href="javascript:void(0);" onclick="document.getElementById(\'debug_cache_info\').style.display = \'inline\'; this.style.display = \'none\'; return false;">', $txt['debug_show'], '</a><span id="debug_cache_info" style="display: none;"><em>', implode('</em>, <em>', $entries), '</em></span>)<br />';
	}

	echo '
	<a href="', $scripturl, '?action=viewquery" target="_blank" class="new_win">', $warnings == 0 ? sprintf($txt['debug_queries_used'], (int) $db_count) : sprintf($txt['debug_queries_used_and_warnings'], (int) $db_count, $warnings), '</a><br />
	<br />';

	if ($_SESSION['view_queries'] == 1 && !empty($db_cache))
		foreach ($db_cache as $q => $qq)
		{
			$is_select = strpos(trim($qq['q']), 'SELECT') === 0 || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($qq['q'])) != 0;
			// Temporary tables created in earlier queries are not explainable.
			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($qq['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}
			// But actual creation of the temporary tables are.
			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($qq['q'])) != 0)
				$is_select = true;

			// Make the filenames look a bit better.
			if (isset($qq['f']))
				$qq['f'] = preg_replace('~^' . preg_quote($boarddir, '~') . '~', '...', $qq['f']);

			echo '
	<strong>', $is_select ? '<a href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win" style="text-decoration: none;">' : '', nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r")))) . ($is_select ? '</a></strong>' : '</strong>') . '<br />
	&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				echo sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t']) && isset($txt['debug_query_which_took_at']))
				echo sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br />';
			elseif (isset($qq['t']))
				echo sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br />';
			echo '
	<br />';
		}

	echo '
	<a href="' . $scripturl . '?action=viewquery;sa=hide">', $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'], '</a>
</div></body></html>';
}

/**
 * Track Statistics.
 * Caches statistics changes, and flushes them if you pass nothing.
 * If '+' is used as a value, it will be incremented.
 * It does not actually commit the changes until the end of the page view.
 * It depends on the trackStats setting.
 *
 * @param array $stats = array()
 * @return bool|array
 */
function trackStats($stats = array())
{
	global $modSettings, $smcFunc;
	static $cache_stats = array();

	if (empty($modSettings['trackStats']))
		return false;
	if (!empty($stats))
		return $cache_stats = array_merge($cache_stats, $stats);
	elseif (empty($cache_stats))
		return false;

	$setStringUpdate = '';
	$insert_keys = array();
	$date = strftime('%Y-%m-%d', forum_time(false));
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

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_activity
		SET' . substr($setStringUpdate, 0, -1) . '
		WHERE date = {date:current_date}',
		$update_parameters
	);
	if ($smcFunc['db_affected_rows']() == 0)
	{
		$smcFunc['db_insert']('ignore',
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
 * This function logs an action in the respective log. (database log)
 * @example logAction('remove', array('starter' => $id_member_started));
 *
 * @param string $action
 * @param array $extra = array()
 * @param string $log_type, options 'moderate', 'admin', ...etc.
 */
function logAction($action, $extra = array(), $log_type = 'moderate')
{
	global $modSettings, $user_info, $smcFunc, $sourcedir;

	$log_types = array(
		'moderate' => 1,
		'user' => 2,
		'admin' => 3,
	);

	if (!is_array($extra))
		trigger_error('logAction(): data is not an array with action \'' . $action . '\'', E_USER_NOTICE);

	// Pull out the parts we want to store separately, but also make sure that the data is proper
	if (isset($extra['topic']))
	{
		if (!is_numeric($extra['topic']))
			trigger_error('logAction(): data\'s topic is not a number', E_USER_NOTICE);
		$topic_id = empty($extra['topic']) ? '0' : (int)$extra['topic'];
		unset($extra['topic']);
	}
	else
		$topic_id = '0';

	if (isset($extra['message']))
	{
		if (!is_numeric($extra['message']))
			trigger_error('logAction(): data\'s message is not a number', E_USER_NOTICE);
		$msg_id = empty($extra['message']) ? '0' : (int)$extra['message'];
		unset($extra['message']);
	}
	else
		$msg_id = '0';

	// @todo cache this?
	// Is there an associated report on this?
	if (in_array($action, array('move', 'remove', 'split', 'merge')))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_report
			FROM {db_prefix}log_reported
			WHERE {raw:column_name} = {int:reported}
			LIMIT 1',
			array(
				'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
				'reported' => !empty($msg_id) ? $msg_id : $topic_id,
		));

		// Alright, if we get any result back, update open reports.
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			require_once($sourcedir . '/ModerationCenter.php');
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports();
		}
		$smcFunc['db_free_result']($request);
	}

	// No point in doing anything else, if the log isn't even enabled.
	if (empty($modSettings['modlog_enabled']) || !isset($log_types[$log_type]))
		return false;

	if (isset($extra['member']) && !is_numeric($extra['member']))
		trigger_error('logAction(): data\'s member is not a number', E_USER_NOTICE);

	if (isset($extra['board']))
	{
		if (!is_numeric($extra['board']))
			trigger_error('logAction(): data\'s board is not a number', E_USER_NOTICE);
		$board_id = empty($extra['board']) ? '0' : (int)$extra['board'];
		unset($extra['board']);
	}
	else
		$board_id = '0';

	if (isset($extra['board_to']))
	{
		if (!is_numeric($extra['board_to']))
			trigger_error('logAction(): data\'s board_to is not a number', E_USER_NOTICE);
		if (empty($board_id))
		{
			$board_id = empty($extra['board_to']) ? '0' : (int)$extra['board_to'];
			unset($extra['board_to']);
		}
	}

	$smcFunc['db_insert']('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		array(
			time(), $log_types[$log_type], $user_info['id'], $user_info['ip'], $action,
			$board_id, $topic_id, $msg_id, serialize($extra),
		),
		array('id_action')
	);

	return $smcFunc['db_insert_id']('{db_prefix}log_actions', 'id_action');
}

?>