<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file is all about mail, how we love it so. In particular it handles the admin side of
	mail configuration, as well as reviewing the mail queue - if enabled.

	void ManageMail()
		// !!

	void BrowseMailQueue()
		// !!

	void ModifyMailSettings()
		// !!

	void ClearMailQueue()
		// !!

*/

// This function passes control through to the relevant section
function ManageMail()
{
	global $context, $txt, $scripturl, $modSettings, $sourcedir;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageMail');

	// We'll need the utility functions from here.
	require_once($sourcedir . '/ManageServer.php');

	$context['page_title'] = $txt['mailqueue_title'];
	$context['sub_template'] = 'show_settings';

	$subActions = array(
		'browse' => 'BrowseMailQueue',
		'clear' => 'ClearMailQueue',
		'settings' => 'ModifyMailSettings',
	);

	// By default we want to browse
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'browse';
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['mailqueue_title'],
		'help' => '',
		'description' => $txt['mailqueue_desc'],
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

// Display the mail queue...
function BrowseMailQueue()
{
	global $scripturl, $context, $modSettings, $txt, $smcFunc;
	global $sourcedir;

	// First, are we deleting something from the queue?
	if (isset($_REQUEST['delete']))
	{
		checkSession('post');

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}mail_queue
			WHERE id_mail IN ({array_int:mail_ids})',
			array(
				'mail_ids' => $_REQUEST['delete'],
			)
		);
	}

	// How many items do we have?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS queue_size, MIN(time_sent) AS oldest
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize, $mailOldest) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$context['oldest_mail'] = empty($mailOldest) ? $txt['mailqueue_oldest_not_available'] : time_since(time() - $mailOldest);
	$context['mail_queue_size'] = comma_format($mailQueueSize);

	$listOptions = array(
		'id' => 'mail_queue',
		'title' => $txt['mailqueue_browse'],
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=mailqueue',
		'default_sort_col' => 'age',
		'no_items_label' => $txt['mailqueue_no_items'],
		'get_items' => array(
			'function' => 'list_getMailQueue',
		),
		'get_count' => array(
			'function' => 'list_getMailQueueSize',
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['mailqueue_subject'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $smcFunc;
						return $smcFunc[\'strlen\']($rowData[\'subject\']) > 50 ? sprintf(\'%1$s...\', htmlspecialchars($smcFunc[\'substr\']($rowData[\'subject\'], 0, 47))) : htmlspecialchars($rowData[\'subject\']);
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'subject',
					'reverse' => 'subject DESC',
				),
			),
			'recipient' => array(
				'header' => array(
					'value' => $txt['mailqueue_recipient'],
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
					'value' => $txt['mailqueue_priority'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						// We probably have a text label with your priority.
						$txtKey = sprintf(\'mq_mpriority_%1$s\', $rowData[\'priority\']);

						// But if not, revert to priority 0.
						return isset($txt[$txtKey]) ? $txt[$txtKey] : $txt[\'mq_mpriority_1\'];
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'priority',
					'reverse' => 'priority DESC',
				),
			),
			'age' => array(
				'header' => array(
					'value' => $txt['mailqueue_age'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return time_since(time() - $rowData[\'time_sent\']);
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'time_sent',
					'reverse' => 'time_sent DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="checkbox" name="delete[]" value="\' . $rowData[\'id_mail\'] . \'" class="input_check" />\';
					'),
					'class' => 'smalltext',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=mailqueue',
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '[<a href="' . $scripturl . '?action=admin;area=mailqueue;sa=clear;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['mailqueue_clear_list_warning'] . '\');">' . $txt['mailqueue_clear_list'] . '</a>] <input type="submit" name="delete_redirects" value="' . $txt['delete'] . '" onclick="return confirm(\'' . $txt['quickmod_confirm'] . '\');" class="button_submit" />',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	loadTemplate('ManageMail');
	$context['sub_template'] = 'browse';
}

function list_getMailQueue($start, $items_per_page, $sort)
{
	global $smcFunc, $txt;

	$request = $smcFunc['db_query']('', '
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
		if (!empty($row['private']))
			$row['subject'] = $txt['personal_message'];

		$mails[] = $row;
	}
	$smcFunc['db_free_result']($request);

	return $mails;
}

function list_getMailQueueSize()
{
	global $smcFunc;

	// How many items do we have?
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS queue_size
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $mailQueueSize;
}

function ModifyMailSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $birthdayEmails, $modSettings;

	loadLanguage('EmailTemplates');

	$body = $birthdayEmails[empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']]['body'];
	$subject = $birthdayEmails[empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']]['subject'];

	$emails = array();
	foreach ($birthdayEmails as $index => $dummy)
		$emails[$index] = $index;

	$config_vars = array(
			// Mail queue stuff, this rocks ;)
			array('check', 'mail_queue'),
			array('int', 'mail_limit'),
			array('int', 'mail_quantity'),
		'',
			// SMTP stuff.
			array('select', 'mail_type', array($txt['mail_type_default'], 'SMTP')),
			array('text', 'smtp_host'),
			array('text', 'smtp_port'),
			array('text', 'smtp_username'),
			array('password', 'smtp_password'),
		'',
			array('select', 'birthday_email', $emails, 'value' => empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email'], 'javascript' => 'onchange="fetch_birthday_preview()"'),
			'birthday_subject' => array('var_message', 'birthday_subject', 'var_message' => $birthdayEmails[empty($modSettings['birthday_email']) ? 'happy_birthday' : $modSettings['birthday_email']]['subject'], 'disabled' => true, 'size' => strlen($subject) + 3),
			'birthday_body' => array('var_message', 'birthday_body', 'var_message' => nl2br($body), 'disabled' => true, 'size' => ceil(strlen($body) / 25)),
	);

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

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=mailqueue;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=mailqueue;save;sa=settings';
	$context['settings_title'] = $txt['mailqueue_settings'];

	prepareDBSettingContext($config_vars);

	$context['settings_insert_above'] = '
	<script type="text/javascript"><!-- // --><![CDATA[
		var bDay = {';

	$i = 0;
	foreach ($birthdayEmails as $index => $email)
	{
		$is_last = ++$i == count($birthdayEmails);
		$context['settings_insert_above'] .= '
			' . $index . ': {
				subject: ' . JavaScriptEscape($email['subject']) . ',
				body: ' . JavaScriptEscape(nl2br($email['body'])) . '
			}' . (!$is_last ? ',' : '');
	}
	$context['settings_insert_above'] .= '
		};
		function fetch_birthday_preview()
		{
			var index = document.getElementById(\'birthday_email\').value;
			document.getElementById(\'birthday_subject\').innerHTML = bDay[index].subject;
			document.getElementById(\'birthday_body\').innerHTML = bDay[index].body;
		}
	// ]]></script>';
}

// This function clears the mail queue of all emails, and at the end redirects to browse.
function ClearMailQueue()
{
	global $sourcedir, $smcFunc;

	checkSession('get');

	// This is certainly needed!
	require_once($sourcedir . '/ScheduledTasks.php');

	// If we don't yet have the total to clear, find it.
	if (!isset($_GET['te']))
	{
		// How many items do we have?
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS queue_size
			FROM {db_prefix}mail_queue',
			array(
			)
		);
		list ($_GET['te']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
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

// Used for pausing the mail queue.
function pauseMailQueueClear()
{
	global $context, $txt, $time_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if (time() - array_sum(explode(' ', $time_start)) < 5)
		return;

	$context['continue_get_data'] = '?action=admin;area=mailqueue;sa=clear;te=' . $_GET['te'] . ';sent=' . $_GET['sent'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Keep browse selected.
	$context['selected'] = 'browse';

	// What percent through are we?
	$context['continue_percent'] = round(($_GET['sent'] / $_GET['te']) * 100, 1);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}

// Little function to calculate how long ago a time was.
function time_since($time_diff)
{
	global $txt;

	if ($time_diff < 0)
		$time_diff = 0;

	// Just do a bit of an if fest...
	if ($time_diff > 86400)
	{
		$days = round($time_diff / 86400, 1);
		return sprintf($days == 1 ? $txt['mq_day'] : $txt['mq_days'], $time_diff / 86400);
	}
	// Hours?
	elseif ($time_diff > 3600)
	{
		$hours = round($time_diff / 3600, 1);
		return sprintf($hours == 1 ? $txt['mq_hour'] : $txt['mq_hours'], $hours);
	}
	// Minutes?
	elseif ($time_diff > 60)
	{
		$minutes = (int) ($time_diff / 60);
		return sprintf($minutes == 1 ? $txt['mq_minute'] : $txt['mq_minutes'], $minutes);
	}
	// Otherwise must be second
	else
		return sprintf($time_diff == 1 ? $txt['mq_second'] : $txt['mq_seconds'], $time_diff);
}

?>