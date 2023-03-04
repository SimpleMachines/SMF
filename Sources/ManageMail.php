<?php

/**
 * This file is all about mail, how we love it so. In particular it handles the admin side of
 * mail configuration, as well as reviewing the mail queue - if enabled.
 *
 * @todo refactor as controller-model.
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
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Main dispatcher. This function checks permissions and passes control through to the relevant section.
 */
function ManageMail()
{
	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	Lang::load('Help');
	Lang::load('ManageMail');

	// We'll need the utility functions from here.
	require_once(Config::$sourcedir . '/ManageServer.php');

	Utils::$context['page_title'] = Lang::$txt['mailqueue_title'];
	Utils::$context['sub_template'] = 'show_settings';

	$subActions = array(
		'browse' => 'BrowseMailQueue',
		'clear' => 'ClearMailQueue',
		'settings' => 'ModifyMailSettings',
		'test' => 'TestMailSend',
	);

	call_integration_hook('integrate_manage_mail', array(&$subActions));

	// By default we want to browse
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'browse';
	Utils::$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	Utils::$context[Utils::$context['admin_menu_name']]['tab_data'] = array(
		'title' => Lang::$txt['mailqueue_title'],
		'help' => '',
		'description' => Lang::$txt['mailqueue_desc'],
	);

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Display the mail queue...
 */
function BrowseMailQueue()
{
	// First, are we deleting something from the queue?
	if (isset($_REQUEST['delete']))
	{
		checkSession();

		Db::$db->query('', '
			DELETE FROM {db_prefix}mail_queue
			WHERE id_mail IN ({array_int:mail_ids})',
			array(
				'mail_ids' => $_REQUEST['delete'],
			)
		);
	}

	// How many items do we have?
	$request = Db::$db->query('', '
		SELECT COUNT(*) AS queue_size, MIN(time_sent) AS oldest
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize, $mailOldest) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	Utils::$context['oldest_mail'] = empty($mailOldest) ? Lang::$txt['mailqueue_oldest_not_available'] : time_since(time() - $mailOldest);
	Utils::$context['mail_queue_size'] = Lang::numberFormat($mailQueueSize);

	$listOptions = array(
		'id' => 'mail_queue',
		'title' => Lang::$txt['mailqueue_browse'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'base_href' => Config::$scripturl . '?action=admin;area=mailqueue',
		'default_sort_col' => 'age',
		'no_items_label' => Lang::$txt['mailqueue_no_items'],
		'get_items' => array(
			'function' => 'list_getMailQueue',
		),
		'get_count' => array(
			'function' => 'list_getMailQueueSize',
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => Lang::$txt['mailqueue_subject'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						return Utils::entityStrlen($rowData['subject']) > 50 ? sprintf('%1$s...', Utils::htmlspecialchars(Utils::entitySubstr($rowData['subject'], 0, 47))) : Utils::htmlspecialchars($rowData['subject']);
					},
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'subject',
					'reverse' => 'subject DESC',
				),
			),
			'recipient' => array(
				'header' => array(
					'value' => Lang::$txt['mailqueue_recipient'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => array(
							'recipient' => true,
						),
					),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'recipient',
					'reverse' => 'recipient DESC',
				),
			),
			'priority' => array(
				'header' => array(
					'value' => Lang::$txt['mailqueue_priority'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						// We probably have a text label with your priority.
						$txtKey = sprintf('mq_mpriority_%1$s', $rowData['priority']);

						// But if not, revert to priority 0.
						return isset(Lang::$txt[$txtKey]) ? Lang::$txt[$txtKey] : Lang::$txt['mq_mpriority_1'];
					},
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'priority',
					'reverse' => 'priority DESC',
				),
			),
			'age' => array(
				'header' => array(
					'value' => Lang::$txt['mailqueue_age'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						return time_since(time() - $rowData['time_sent']);
					},
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'time_sent',
					'reverse' => 'time_sent DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="checkbox" name="delete[]" value="' . $rowData['id_mail'] . '">';
					},
					'class' => 'smalltext',
				),
			),
		),
		'form' => array(
			'href' => Config::$scripturl . '?action=admin;area=mailqueue',
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'top_of_list',
				'value' => '<input type="submit" name="delete_redirects" value="' . Lang::$txt['quickmod_delete_selected'] . '" data-confirm="' . Lang::$txt['quickmod_confirm'] . '" class="button you_sure"><a class="button you_sure" href="' . Config::$scripturl . '?action=admin;area=mailqueue;sa=clear;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" data-confirm="' . Lang::$txt['mailqueue_clear_list_warning'] . '">' . Lang::$txt['mailqueue_clear_list'] . '</a> ',
			),
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="delete_redirects" value="' . Lang::$txt['quickmod_delete_selected'] . '" data-confirm="' . Lang::$txt['quickmod_confirm'] . '" class="button you_sure"><a class="button you_sure" href="' . Config::$scripturl . '?action=admin;area=mailqueue;sa=clear;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" data-confirm="' . Lang::$txt['mailqueue_clear_list_warning'] . '">' . Lang::$txt['mailqueue_clear_list'] . '</a> ',
			),
		),
	);

	require_once(Config::$sourcedir . '/Subs-List.php');
	createList($listOptions);

	loadTemplate('ManageMail');
	Utils::$context['sub_template'] = 'browse';
}

/**
 * This function grabs the mail queue items from the database, according to the params given.
 * Callback for $listOptions['get_items'] in BrowseMailQueue()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @return array An array with info about the mail queue items
 */
function list_getMailQueue($start, $items_per_page, $sort)
{
	$request = Db::$db->query('', '
		SELECT
			id_mail, time_sent, recipient, priority, private, subject
		FROM {db_prefix}mail_queue
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'start' => $start,
			'sort' => $sort,
			'items_per_page' => $items_per_page,
		)
	);
	$mails = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
		if (!empty($row['private']))
			$row['subject'] = Lang::$txt['personal_message'];
		else
			$row['subject'] = mb_decode_mimeheader($row['subject']);

		$mails[] = $row;
	}
	Db::$db->free_result($request);

	return $mails;
}

/**
 * Returns the total count of items in the mail queue.
 * Callback for $listOptions['get_count'] in BrowseMailQueue
 *
 * @return int The total number of mail queue items
 */
function list_getMailQueueSize()
{
	// How many items do we have?
	$request = Db::$db->query('', '
		SELECT COUNT(*) AS queue_size
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return $mailQueueSize;
}

/**
 * Allows to view and modify the mail settings.
 *
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns the $config_vars array if $return_config is true
 */
function ModifyMailSettings($return_config = false)
{
	Lang::load('EmailTemplates');

	$body = Lang::$txtBirthdayEmails[(empty(Config::$modSettings['birthday_email']) ? 'happy_birthday' : Config::$modSettings['birthday_email']) . '_body'];
	$subject = Lang::$txtBirthdayEmails[(empty(Config::$modSettings['birthday_email']) ? 'happy_birthday' : Config::$modSettings['birthday_email']) . '_subject'];

	$emails = array();
	$processedBirthdayEmails = array();
	foreach (Lang::$txtBirthdayEmails as $key => $value)
	{
		$index = substr($key, 0, strrpos($key, '_'));
		$element = substr($key, strrpos($key, '_') + 1);
		$processedBirthdayEmails[$index][$element] = $value;
	}
	foreach ($processedBirthdayEmails as $index => $dummy)
		$emails[$index] = $index;

	$config_vars = array(
		// Mail queue stuff, this rocks ;)
		array('int', 'mail_limit', 'subtext' => Lang::$txt['zero_to_disable']),
		array('int', 'mail_quantity'),
		'',

		// SMTP stuff.
		array('select', 'mail_type', array(Lang::$txt['mail_type_default'], 'SMTP', 'SMTP - STARTTLS')),
		array('text', 'smtp_host'),
		array('text', 'smtp_port'),
		array('text', 'smtp_username'),
		array('password', 'smtp_password'),
		'',

		array('select', 'birthday_email', $emails, 'value' => array('subject' => $subject, 'body' => $body), 'javascript' => 'onchange="fetch_birthday_preview()"'),
		'birthday_subject' => array('var_message', 'birthday_subject', 'var_message' => $processedBirthdayEmails[empty(Config::$modSettings['birthday_email']) ? 'happy_birthday' : Config::$modSettings['birthday_email']]['subject'], 'disabled' => true, 'size' => strlen($subject) + 3),
		'birthday_body' => array('var_message', 'birthday_body', 'var_message' => nl2br($body), 'disabled' => true, 'size' => ceil(strlen($body) / 25)),
	);

	call_integration_hook('integrate_modify_mail_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		// Make the SMTP password a little harder to see in a backup etc.
		if (!empty($_POST['smtp_password'][1]))
		{
			$_POST['smtp_password'][0] = base64_encode($_POST['smtp_password'][0]);
			$_POST['smtp_password'][1] = base64_encode($_POST['smtp_password'][1]);
		}
		checkSession();

		// We don't want to save the subject and body previews.
		unset($config_vars['birthday_subject'], $config_vars['birthday_body']);
		call_integration_hook('integrate_save_mail_settings');

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=mailqueue;sa=settings');
	}

	Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=mailqueue;save;sa=settings';
	Utils::$context['settings_title'] = Lang::$txt['mailqueue_settings'];

	prepareDBSettingContext($config_vars);

	Utils::$context['settings_insert_above'] = '
	<script>
		var bDay = {';

	$i = 0;
	foreach ($processedBirthdayEmails as $index => $email)
	{
		$is_last = ++$i == count($processedBirthdayEmails);
		Utils::$context['settings_insert_above'] .= '
			' . $index . ': {
				subject: ' . JavaScriptEscape($email['subject']) . ',
				body: ' . JavaScriptEscape(nl2br($email['body'])) . '
			}' . (!$is_last ? ',' : '');
	}
	Utils::$context['settings_insert_above'] .= '
		};
		function fetch_birthday_preview()
		{
			var index = document.getElementById(\'birthday_email\').value;
			document.getElementById(\'birthday_subject\').innerHTML = bDay[index].subject;
			document.getElementById(\'birthday_body\').innerHTML = bDay[index].body;
		}
	</script>';
}

/**
 * This function clears the mail queue of all emails, and at the end redirects to browse.
 */
function ClearMailQueue()
{
	checkSession('get');

	// This is certainly needed!
	require_once(Config::$sourcedir . '/ScheduledTasks.php');

	// If we don't yet have the total to clear, find it.
	if (!isset($_GET['te']))
	{
		// How many items do we have?
		$request = Db::$db->query('', '
			SELECT COUNT(*) AS queue_size
			FROM {db_prefix}mail_queue',
			array(
			)
		);
		list ($_GET['te']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
	}
	else
		$_GET['te'] = (int) $_GET['te'];

	$_GET['sent'] = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;

	// Send 50 at a time, then go for a break...
	while (ReduceMailQueue(50, true, true) === true)
	{
		// Sent another 50.
		$_GET['sent'] += 50;
		pauseMailQueueClear();
	}

	return BrowseMailQueue();
}

/**
 * Used for pausing the mail queue.
 */
function pauseMailQueueClear()
{
	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if ((time() - TIME_START) < 5)
		return;

	Utils::$context['continue_get_data'] = '?action=admin;area=mailqueue;sa=clear;te=' . $_GET['te'] . ';sent=' . $_GET['sent'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
	Utils::$context['page_title'] = Lang::$txt['not_done_title'];
	Utils::$context['continue_post_data'] = '';
	Utils::$context['continue_countdown'] = '2';
	Utils::$context['sub_template'] = 'not_done';

	// Keep browse selected.
	Utils::$context['selected'] = 'browse';

	// What percent through are we?
	Utils::$context['continue_percent'] = round(($_GET['sent'] / $_GET['te']) * 100, 1);

	// Never more than 100%!
	Utils::$context['continue_percent'] = min(Utils::$context['continue_percent'], 100);

	obExit();
}

/**
 * Test mail sending ability.
 *
 */
function TestMailSend()
{
	Lang::load('ManageMail');
	loadTemplate('ManageMail');
	Utils::$context['sub_template'] = 'mailtest';
	Utils::$context['base_url'] = Config::$scripturl . '?action=admin;area=mailqueue;sa=test';
	Utils::$context['post_url'] = Utils::$context['base_url'] . ';save';

	// Sending the test message now.
	if (isset($_GET['save']))
	{
		require_once(Config::$sourcedir . '/Msg.php');

		// Send to the current user, no options.
		$to = User::$me->email;
		$subject = Utils::htmlspecialchars($_POST['subject']);
		$message = Utils::htmlspecialchars($_POST['message']);

		$result = sendmail($to, $subject, $message, null, null, false, 0);
		redirectexit(Utils::$context['base_url'] . ';result=' . ($result ? 'success' : 'failure'));
	}

	// The result.
	if (isset($_GET['result']))
		Utils::$context['result'] = ($_GET['result'] == 'success' ? 'success' : 'failure');
}

/**
 * Little utility function to calculate how long ago a time was.
 *
 * @param int $time_diff The time difference, in seconds
 * @return string A string indicating how many days, hours, minutes or seconds (depending on $time_diff)
 */
function time_since($time_diff)
{
	if ($time_diff < 0)
		$time_diff = 0;

	// Just do a bit of an if fest...
	if ($time_diff > 86400)
	{
		$days = round($time_diff / 86400, 1);
		return sprintf($days == 1 ? Lang::$txt['mq_day'] : Lang::$txt['mq_days'], $time_diff / 86400);
	}
	// Hours?
	elseif ($time_diff > 3600)
	{
		$hours = round($time_diff / 3600, 1);
		return sprintf($hours == 1 ? Lang::$txt['mq_hour'] : Lang::$txt['mq_hours'], $hours);
	}
	// Minutes?
	elseif ($time_diff > 60)
	{
		$minutes = (int) ($time_diff / 60);
		return sprintf($minutes == 1 ? Lang::$txt['mq_minute'] : Lang::$txt['mq_minutes'], $minutes);
	}
	// Otherwise must be second
	else
		return sprintf($time_diff == 1 ? Lang::$txt['mq_second'] : Lang::$txt['mq_seconds'], $time_diff);
}

?>