<?php

/**
 * This file contains just the functions that turn on and off notifications
 * to topics or boards.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Turn off/on notification for a particular board.
 * Must be called with a board specified in the URL.
 * Only uses the template if no sub action is used. (on/off)
 * Redirects the user back to the board after it is done.
 * Accessed via ?action=notifyboard.
 *
 * @uses Notify template, notify_board sub-template.
 */
function BoardNotify()
{
	global $board, $user_info, $context, $smcFunc, $sourcedir;

	// Permissions are an important part of anything ;).
	is_not_guest();

	// You have to specify a board to turn notifications on!
	if (empty($board))
		fatal_lang_error('no_board', false);

	// No subaction: find out what to do.
	if (isset($_GET['mode']))
	{
		checkSession('get');

		$mode = (int) $_GET['mode'];
		$alertPref = $mode <= 1 ? 0 : ($mode == 2 ? 1 : 3);

		require_once($sourcedir . '/Subs-Notify.php');
		setNotifyPrefs($user_info['id'], array('board_notify_' . $board => $alertPref));

		if ($mode > 1)
			// Turn notification on.  (note this just blows smoke if it's already on.)
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_notify',
				array('id_member' => 'int', 'id_board' => 'int'),
				array($user_info['id'], $board),
				array('id_member', 'id_board')
			);
		else
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_notify
				WHERE id_member = {int:current_member}
					AND id_board = {int:current_board}',
				array(
					'current_board' => $board,
					'current_member' => $user_info['id'],
				)
			);
	}

	// Back to the board!
	if (isset($_GET['xml']))
	{
		$context['xml_data']['errors'] = array(
			'identifier' => 'error',
			'children' => array(
				array(
					'value' => 0,
				),
			),
		);
		$context['sub_template'] = 'generic_xml';
	}
	else
		redirectexit('board=' . $board . '.' . $_REQUEST['start']);
}

/**
 * Turn off/on unread replies subscription for a topic as well as sets individual topic's alert preferences
 * Must be called with a topic specified in the URL.
 * The mode can be from 0 to 3
 * 0 => unwatched, 1 => no alerts/emails, 2 => alerts, 3 => emails/alerts
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=unwatchtopic.
 */
function TopicNotify()
{
	global $smcFunc, $user_info, $topic, $sourcedir, $context;

	// Let's do something only if the function is enabled
	if (!$user_info['is_guest'])
	{
		checkSession('get');

		if (isset($_GET['mode']))
		{
			$mode = (int) $_GET['mode'];
			$alertPref = $mode <= 1 ? 0 : ($mode == 2 ? 1 : 3);

			$request = $smcFunc['db_query']('', '
				SELECT id_member, id_topic, id_msg, unwatched
				FROM {db_prefix}log_topics
				WHERE id_member = {int:current_user}
					AND id_topic = {int:current_topic}',
				array(
					'current_user' => $user_info['id'],
					'current_topic' => $topic,
				)
			);
			$log = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
			if (empty($log))
			{
				$insert = true;
				$log = array(
					'id_member' => $user_info['id'],
					'id_topic' => $topic,
					'id_msg' => 0,
					'unwatched' => empty($mode) ? 1 : 0,
				);
			}
			else
			{
				$insert = false;
				$log['unwatched'] = empty($mode) ? 1 : 0;
			}

			$smcFunc['db_insert']($insert ? 'insert' : 'replace',
				'{db_prefix}log_topics',
				array(
					'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
				),
				$log,
				array('id_member', 'id_topic')
			);

			require_once($sourcedir . '/Subs-Notify.php');
			setNotifyPrefs($user_info['id'], array('topic_notify_' . $log['id_topic'] => $alertPref));

			if ($mode > 1)
			{
				// Turn notification on.  (note this just blows smoke if it's already on.)
				$smcFunc['db_insert']('ignore',
					'{db_prefix}log_notify',
					array('id_member' => 'int', 'id_topic' => 'int'),
					array($user_info['id'], $log['id_topic']),
					array('id_member', 'id_board')
				);
			}
			else
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}log_notify
					WHERE id_topic = {int:topic}
						AND id_member = {int:member}',
					array(
						'topic' => $log['id_topic'],
						'member' => $user_info['id'],
					)
				);
		}
	}

	// Back to the topic.
	if (isset($_GET['xml']))
	{
		$context['xml_data']['errors'] = array(
			'identifier' => 'error',
			'children' => array(
				array(
					'value' => 0,
				),
			),
		);
		$context['sub_template'] = 'generic_xml';
	}
	else
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

?>