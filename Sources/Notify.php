<?php

/**
 * This file contains just the functions that turn on and off notifications
 * to topics or boards.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Turn off/on notification for a particular board.
 * Must be called with a board specified in the URL.
 * Only uses the template if no mode (or subaction) was given.
 * Redirects the user back to the board after it is done.
 * Accessed via ?action=notifyboard.
 *
 * @uses template_notify_board()
 */
function BoardNotify()
{
	global $board, $user_info, $context, $smcFunc, $sourcedir, $scripturl, $txt;

	require_once($sourcedir . '/Subs-Notify.php');

	// Subscribing or unsubscribing with a token.
	if (isset($_REQUEST['u']) && isset($_REQUEST['token']))
	{
		$member_info = getMemberWithToken('board');
		$skipCheckSession = true;
	}
	// No token, so try with the current user.
	else
	{
		// Permissions are an important part of anything ;).
		is_not_guest();
		$member_info = $user_info;
	}

	// You have to specify a board to turn notifications on!
	if (empty($board))
		fatal_lang_error('no_board', false);

	// sa=on/off is used for email subscribe/unsubscribe links
	if (!isset($_GET['mode']) && isset($_GET['sa']))
	{
		$_GET['mode'] = $_GET['sa'] == 'on' ? 3 : -1;
		unset($_GET['sa']);
	}

	// No mode: find out what to do.
	if (!isset($_GET['mode']) && !isset($_GET['xml']))
	{
		// We're gonna need the notify template...
		loadTemplate('Notify');

		// Find out if they have notification set for this board already.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}
			LIMIT 1',
			array(
				'current_board' => $board,
				'current_member' => $member_info['id'],
			)
		);
		$context['notification_set'] = $smcFunc['db_num_rows']($request) != 0;
		$smcFunc['db_free_result']($request);

		if ($member_info['id'] !== $user_info['id'])
			$context['notify_info'] = array(
				'u' => $member_info['id'],
				'token' => $_REQUEST['token'],
			);

		// Set the template variables...
		$context['board_href'] = $scripturl . '?board=' . $board . '.' . $_REQUEST['start'];
		$context['start'] = $_REQUEST['start'];
		$context['page_title'] = $txt['notification'];
		$context['sub_template'] = 'notify_board';

		return;
	}
	elseif (isset($_GET['mode']))
	{
		if (empty($skipCheckSession))
			checkSession('get');

		$mode = (int) $_GET['mode'];

		// -1 is used to turn off email notifications while leaving the alert pref unchanged.
		if ($mode == -1)
			$mode = min(2, getNotifyPrefs($member_info['id'], array('board_notify_' . $board), true));

		$alertPref = $mode <= 1 ? 0 : ($mode == 2 ? 1 : 3);

		setNotifyPrefs((int) $member_info['id'], array('board_notify_' . $board => $alertPref));

		if ($mode > 1)
			// Turn notification on.  (note this just blows smoke if it's already on.)
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_notify',
				array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'),
				array($user_info['id'], 0, $board),
				array('id_member', 'id_topic', 'id_board')
			);
		else
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_notify
				WHERE id_member = {int:current_member}
					AND id_board = {int:current_board}',
				array(
					'current_board' => $board,
					'current_member' => $member_info['id'],
				)
			);
	}

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
	// Probably followed an unsubscribe link, so just show a confirmation message.
	elseif (!empty($skipCheckSession) && isset($mode))
	{
		loadTemplate('Notify');
		$context['page_title'] = $txt['notification'];
		$context['sub_template'] = 'notify_pref_changed';
		$context['notify_success_msg'] = sprintf($txt['notify_board' . ($mode == 3 ? '_subscribed' : '_unsubscribed')], $member_info['email']);
		return;
	}
	// Back to the board!
	else
		redirectexit('board=' . $board . '.' . $_REQUEST['start']);
}

/**
 * Turn off/on unread replies subscription for a topic as well as sets individual topic's alert preferences
 * Must be called with a topic specified in the URL.
 * The mode can be from 0 to 3
 * 0 => unwatched, 1 => no alerts/emails, 2 => alerts, 3 => emails/alerts
 * Upon successful completion of action will direct user back to topic.
 * Accessed via ?action=notifytopic.
 */
function TopicNotify()
{
	global $smcFunc, $user_info, $topic, $sourcedir, $context, $scripturl, $txt;

	require_once($sourcedir . '/Subs-Notify.php');

	if (isset($_REQUEST['u']) && isset($_REQUEST['token']))
	{
		$member_info = getMemberWithToken('topic');
		$skipCheckSession = true;
	}
	else
	{
		is_not_guest();
		$member_info = $user_info;
	}

	// Make sure the topic has been specified.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	// sa=on/off is used to toggle email notifications
	if (!isset($_GET['mode']) && isset($_GET['sa']))
	{
		$_GET['mode'] = $_GET['sa'] == 'on' ? 3 : -1;
		unset($_GET['sa']);
	}

	// What do we do?  Better ask if they didn't say..
	if (!isset($_GET['mode']) && !isset($_GET['xml']))
	{
		// Load the template, but only if it is needed.
		loadTemplate('Notify');

		// Find out if they have notification set for this topic already.
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_member' => $member_info['id'],
				'current_topic' => $topic,
			)
		);
		$context['notification_set'] = $smcFunc['db_num_rows']($request) != 0;
		$smcFunc['db_free_result']($request);

		if ($member_info['id'] !== $user_info['id'])
			$context['notify_info'] = array(
				'u' => $member_info['id'],
				'token' => $_REQUEST['token'],
			);

		// Set the template variables...
		$context['topic_href'] = $scripturl . '?topic=' . $topic . '.' . $_REQUEST['start'];
		$context['start'] = $_REQUEST['start'];
		$context['page_title'] = $txt['notification'];

		return;
	}
	elseif (isset($_GET['mode']))
	{
		if (empty($skipCheckSession))
			checkSession('get');

		$mode = (int) $_GET['mode'];

		// Turn off email notifications while leaving the alert pref alone.
		if ($mode == -1)
			$mode = min(2, getNotifyPrefs($member_info['id'], array('topic_notify_' . $topic), true));

		$alertPref = $mode <= 1 ? 0 : ($mode == 2 ? 1 : 3);

		$request = $smcFunc['db_query']('', '
			SELECT id_member, id_topic, id_msg, unwatched
			FROM {db_prefix}log_topics
			WHERE id_member = {int:current_user}
				AND id_topic = {int:current_topic}',
			array(
				'current_user' => $member_info['id'],
				'current_topic' => $topic,
			)
		);
		$log = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
		if (empty($log))
		{
			$insert = true;
			$log = array(
				'id_member' => $member_info['id'],
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

		setNotifyPrefs((int) $member_info['id'], array('topic_notify_' . $log['id_topic'] => $alertPref));

		if ($mode > 1)
		{
			// Turn notification on.  (note this just blows smoke if it's already on.)
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_notify',
				array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'),
				array($user_info['id'], $log['id_topic'], 0),
				array('id_member','id_topic', 'id_board')
			);
		}
		else
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}log_notify
				WHERE id_topic = {int:topic}
					AND id_member = {int:member}',
				array(
					'topic' => $log['id_topic'],
					'member' => $member_info['id'],
				)
			);
	}

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
	// Probably followed an unsubscribe link, so just show a confirmation message.
	elseif (!empty($skipCheckSession) && isset($mode))
	{
		loadTemplate('Notify');
		$context['page_title'] = $txt['notification'];
		$context['sub_template'] = 'notify_pref_changed';
		$context['notify_success_msg'] = sprintf($txt['notify_topic' . ($mode == 3 ? '_subscribed' : '_unsubscribed')], $member_info['email']);
		return;
	}
	// Back to the topic.
	else
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

/**
 * Turn off/on notifications for announcements.
 * Only uses the template if no mode was given.
 * Accessed via ?action=notifyannouncements.
 */
function AnnouncementsNotify()
{
	global $scripturl, $txt, $board, $user_info, $context, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Notify.php');

	if (isset($_REQUEST['u']) && $_REQUEST['u'] > 0 && isset($_REQUEST['token']))
	{
		$member_info = getMemberWithToken('announcements');
		$skipCheckSession = true;
	}
	else
	{
		is_not_guest();
		$member_info = $user_info;
	}

	loadTemplate('Notify');
	$context['page_title'] = $txt['notification'];

	// Backward compatibility.
	if (!isset($_GET['mode']) && isset($_GET['sa']))
	{
		$_GET['mode'] = $_GET['sa'] == 'on' ? 1 : 0;
		unset($_GET['sa']);
	}

	// Ask what they want to do.
	if (!isset($_GET['mode']))
	{
		$context['sub_template'] = 'notify_announcements';

		if ($member_info['id'] !== $user_info['id'])
			$context['notify_info'] = array(
				'u' => $member_info['id'],
				'token' => $_REQUEST['token'],
			);

		return;
	}

	// We don't tolerate imposters around here.
	if (empty($skipCheckSession))
		checkSession('get');

	$mode = (int) !empty($_GET['mode']);

	// Update their announcement notification preference.
	setNotifyPrefs((int) $member_info['id'], array('announcements' => $mode));

	// Show a confirmation message.
	$context['sub_template'] = 'notify_pref_changed';
	$context['notify_success_msg'] = sprintf($txt['notify_announcements' . (!empty($mode) ? '_subscribed' : '_unsubscribed')], $member_info['email']);
}

/**
 * Disable an account if they unsubscribe/delete account.
 * Accessed via ?action=unsubscribe.
 */
function Unsubscribe()
{
	global $scripturl, $txt, $user_info, $context, $smcFunc, $sourcedir, $modSettings;

	require_once($sourcedir . '/Subs-Notify.php');

	// Logged in? Just delete your account.
	if (!$user_info['is_guest'])
		redirectexit('action=profile;area=notification');

	loadTemplate('Notify');
	$context['page_title'] = $txt['unsubscribe'];

	if (isset($_REQUEST['u']) && $_REQUEST['u'] > 0 && isset($_REQUEST['token']))
	{
		$member_info = getMemberWithToken('unsubscribe');

		if (isset($_POST['notify']))
		{
			validateToken('unsubscribe');

			// Get all notifications preferences.
			$notifyPrefs = getNotifyPrefs($member_info['id'], '', true);

			if (!empty($notifyPrefs[$member_info['id']]))
			{
				// 0 = None, 1 = Alert, 2 = Email, 3 = Alert + Email
				foreach ($notifyPrefs[$member_info['id']] as $pref_key => &$pref_value)
					if ($pref_key != 'alert_timeout' && $pref_value > 0)
						$pref_value = $pref_value == 2 ? 0 : 1;

				setNotifyPrefs($member_info['id'], $notifyPrefs[$member_info['id']]); 
			}

			$context['sub_template'] = 'unsubscribe_success';
			$context['success_msg'] = $txt['unsubscribe_success_done'];
		}
		else
		{
			createToken('unsubscribe');
			$context['sub_template'] = 'unsubscribe_confirm';
			$context['token'] = $smcFunc['htmlspecialchars']($_REQUEST['token']);
			$context['user_id'] = $member_info['id'];
		}

		return;
	}

	// A request has been received, lookup the member and send a request.
	if (isset($_POST['email']))
	{
		checkSession('post');
		validateToken('unsubscribe');

		// Lookup the member.
		$request = $smcFunc['db_query']('', '
			SELECT id_member, email_address
			FROM {db_prefix}members
			WHERE email_address = {string:email}',
			array(
				'email' => $_POST['email']
		));

		// Regardless, we pretend it was succesful, so we don't leak member data.
		if ($smcFunc['db_num_rows']($request) > 0)
		{
			$user_settings = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			$token = createUnsubscribeToken($user_settings['id_member'], $user_settings['email_address'], 'unsubscribe');

			require_once($sourcedir . '/Subs-Post.php');

			// Send them a email (yes) with a token to unsubscribe.
			sendmail(
				$user_settings['email_address'],
				$txt['unsubscribe_email_subject'],
				sprintf($txt['unsubscribe_email_body'], $scripturl . '?action=unsubscribe;u=' . $user_settings['id_member'] . ';token=' . $token) . "\n\n" . sprintf($txt['regards_team'], $context['forum_name'])
			);
		}

		$context['sub_template'] = 'unsubscribe_success';
		$context['success_msg'] = $txt['unsubscribe_success_request'];		
	}
	else
	{
		// Default email address if provided with one.
		$context['email_address'] = isset($_GET['email']) ? $smcFunc['htmlspecialchars'](urldecode($_GET['email'])) : '';
		$context['sub_template'] = 'unsubscribe_request';
		createToken('unsubscribe');
	}
}

?>