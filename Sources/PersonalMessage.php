<?php

/**
 * This file is mainly meant for controlling the actions related to personal
 * messages. It allows viewing, sending, deleting, and marking personal
 * messages. For compatibility reasons, they are often called "instant messages".
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * This helps organize things...
 * @todo this should be a simple dispatcher....
 */
function MessageMain()
{
	global $txt, $scripturl, $sourcedir, $context, $user_info, $user_settings, $smcFunc, $modSettings;

	// No guests!
	is_not_guest();

	// You're not supposed to be here at all, if you can't even read PMs.
	isAllowedTo('pm_read');

	// This file contains the basic functions for sending a PM.
	require_once($sourcedir . '/Subs-Post.php');

	loadLanguage('PersonalMessage+Drafts');

	if (WIRELESS && WIRELESS_PROTOCOL == 'wap')
		fatal_lang_error('wireless_error_notyet', false);
	elseif (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_pm';
	elseif (!isset($_REQUEST['xml']))
		loadTemplate('PersonalMessage');

	// Load up the members maximum message capacity.
	if ($user_info['is_admin'])
		$context['message_limit'] = 0;
	elseif (($context['message_limit'] = cache_get_data('msgLimit:' . $user_info['id'], 360)) === null)
	{
		// @todo Why do we do this?  It seems like if they have any limit we should use it.
		$request = $smcFunc['db_query']('', '
			SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:users_groups})',
			array(
				'users_groups' => $user_info['groups'],
			)
		);
		list ($maxMessage, $minMessage) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['message_limit'] = $minMessage == 0 ? 0 : $maxMessage;

		// Save us doing it again!
		cache_put_data('msgLimit:' . $user_info['id'], $context['message_limit'], 360);
	}

	// Prepare the context for the capacity bar.
	if (!empty($context['message_limit']))
	{
		$bar = ($user_info['messages'] * 100) / $context['message_limit'];

		$context['limit_bar'] = array(
			'messages' => $user_info['messages'],
			'allowed' => $context['message_limit'],
			'percent' => $bar,
			'bar' => min(100, (int) $bar),
			'text' => sprintf($txt['pm_currently_using'], $user_info['messages'], round($bar, 1)),
		);
	}

	// a previous message was sent successfully? show a small indication.
	if (isset($_GET['done']) && ($_GET['done'] == 'sent'))
		$context['pm_sent'] = true;

	$context['labels'] = array();

	// Load the label data.
	if ($user_settings['new_pm'] || ($context['labels'] = cache_get_data('labelCounts:' . $user_info['id'], 720)) === null)
	{
		// Looks like we need to reseek!

		// Inbox "label"
		$context['labels'][-1] = array(
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		);

		// First get the inbox counts
		// The CASE WHEN here is because is_read is set to 3 when you reply to a message
		$result = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS total, SUM(is_read & 1) AS num_read
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:current_member}
				AND in_inbox = {int:in_inbox}
				AND deleted = {int:not_deleted}',
			array(
				'current_member' => $user_info['id'],
				'in_inbox' => 1,
				'not_deleted' => 0,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$context['labels'][-1]['messages'] = $row['total'];
			$context['labels'][-1]['unread_messages'] = $row['total'] - $row['num_read'];
		}

		$smcFunc['db_free_result']($result);

		// Now load info about all the other labels
		$result = $smcFunc['db_query']('', '
			SELECT l.id_label, l.name, IFNULL(SUM(pr.is_read & 1), 0) AS num_read, IFNULL(COUNT(pr.id_pm), 0) AS total
			FROM {db_prefix}pm_labels AS l
				LEFT JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_label = l.id_label)
				LEFT JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pl.id_pm)
			WHERE l.id_member = {int:current_member}
			GROUP BY l.id_label, l.name',
			array(
				'current_member' => $user_info['id'],
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$context['labels'][$row['id_label']] = array(
				'id' => $row['id_label'],
				'name' => $row['name'],
				'messages' => $row['total'],
				'unread_messages' => $row['total'] - $row['num_read']
			);
		}

		$smcFunc['db_free_result']($result);

		// Store it please!
		cache_put_data('labelCounts:' . $user_info['id'], $context['labels'], 720);
	}

	// Now we have the labels, and assuming we have unsorted mail, apply our rules!
	if ($user_settings['new_pm'])
	{
		ApplyRules();
		updateMemberData($user_info['id'], array('new_pm' => 0));
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET is_new = {int:not_new}
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'not_new' => 0,
			)
		);
	}

	// This determines if we have more labels than just the standard inbox.
	$context['currently_using_labels'] = count($context['labels']) > 1 ? 1 : 0;

	// Some stuff for the labels...
	$context['current_label_id'] = isset($_REQUEST['l']) && isset($context['labels'][$_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;
	$context['current_label'] = &$context['labels'][$context['current_label_id']]['name'];
	$context['folder'] = !isset($_REQUEST['f']) || $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent';

	// This is convenient.  Do you know how annoying it is to do this every time?!
	$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($_GET['start']) ? ';start=' . $_GET['start'] : '') . (isset($_REQUEST['l']) ? ';l=' . $_REQUEST['l'] : '');
	$context['can_issue_warning'] = allowedTo('issue_warning') && $modSettings['warning_settings'][0] == 1;

	// Are PM drafts enabled?
	$context['drafts_pm_save'] = !empty($modSettings['drafts_pm_enabled']) && allowedTo('pm_draft');
	$context['drafts_autosave'] = !empty($context['drafts_pm_save']) && !empty($modSettings['drafts_autosave_enabled']);

	// Build the linktree for all the actions...
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm',
		'name' => $txt['personal_messages']
	);

	// Preferences...
	$context['display_mode'] = WIRELESS ? 0 : $user_settings['pm_prefs'] & 3;

	$subActions = array(
		'popup' => 'MessagePopup',
		'addbuddy' => 'WirelessAddBuddy',
		'manlabels' => 'ManageLabels',
		'manrules' => 'ManageRules',
		'pmactions' => 'MessageActionsApply',
		'prune' => 'MessagePrune',
		'removeall' => 'MessageKillAllQuery',
		'removeall2' => 'MessageKillAll',
		'report' => 'ReportMessage',
		'search' => 'MessageSearch',
		'search2' => 'MessageSearch2',
		'send' => 'MessagePost',
		'send2' => 'MessagePost2',
		'settings' => 'MessageSettings',
		'showpmdrafts' => 'MessageDrafts',
	);

	if (!isset($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
	{
		$_REQUEST['sa'] = '';
		MessageFolder();
	}
	else
	{
		if (!isset($_REQUEST['xml']) && $_REQUEST['sa'] != 'popup')
			messageIndexBar($_REQUEST['sa']);
		call_helper($subActions[$_REQUEST['sa']]);
	}
}

/**
 * A menu to easily access different areas of the PM section
 *
 * @param string $area
 */
function messageIndexBar($area)
{
	global $txt, $context, $scripturl, $sourcedir, $modSettings, $user_info;

	$pm_areas = array(
		'folders' => array(
			'title' => $txt['pm_messages'],
			'areas' => array(
				'send' => array(
					'label' => $txt['new_message'],
					'custom_url' => $scripturl . '?action=pm;sa=send',
					'permission' => allowedTo('pm_send'),
				),
				'inbox' => array(
					'label' => $txt['inbox'],
					'custom_url' => $scripturl . '?action=pm',
				),
				'sent' => array(
					'label' => $txt['sent_items'],
					'custom_url' => $scripturl . '?action=pm;f=sent',
				),
				'drafts' => array(
					'label' => $txt['drafts_show'],
					'custom_url' => $scripturl . '?action=pm;sa=showpmdrafts',
					'permission' => allowedTo('pm_draft'),
					'enabled' => !empty($modSettings['drafts_pm_enabled']),
				),
			),
		),
		'labels' => array(
			'title' => $txt['pm_labels'],
			'areas' => array(),
		),
		'actions' => array(
			'title' => $txt['pm_actions'],
			'areas' => array(
				'search' => array(
					'label' => $txt['pm_search_bar_title'],
					'custom_url' => $scripturl . '?action=pm;sa=search',
				),
				'prune' => array(
					'label' => $txt['pm_prune'],
					'custom_url' => $scripturl . '?action=pm;sa=prune'
				),
			),
		),
		'pref' => array(
			'title' => $txt['pm_preferences'],
			'areas' => array(
				'manlabels' => array(
					'label' => $txt['pm_manage_labels'],
					'custom_url' => $scripturl . '?action=pm;sa=manlabels',
				),
				'manrules' => array(
					'label' => $txt['pm_manage_rules'],
					'custom_url' => $scripturl . '?action=pm;sa=manrules',
				),
				'settings' => array(
					'label' => $txt['pm_settings'],
					'custom_url' => $scripturl . '?action=pm;sa=settings',
				),
			),
		),
	);

	// Handle labels.
	if (empty($context['currently_using_labels']))
		unset($pm_areas['labels']);
	else
	{
		// Note we send labels by id as it will have less problems in the querystring.
		$unread_in_labels = 0;
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;

			// Count the amount of unread items in labels.
			$unread_in_labels += $label['unread_messages'];

			// Add the label to the menu.
			$pm_areas['labels']['areas']['label' . $label['id']] = array(
				'label' => $label['name'] . (!empty($label['unread_messages']) ? ' <span class="amt">' . $label['unread_messages'] . '</span>' : ''),
				'custom_url' => $scripturl . '?action=pm;l=' . $label['id'],
				'unread_messages' => $label['unread_messages'],
				'messages' => $label['messages'],
			);
		}

		if (!empty($unread_in_labels))
			$pm_areas['labels']['title'] .= ' <span class="amt">' . $unread_in_labels . '</span>';
	}

	$pm_areas['folders']['areas']['inbox']['unread_messages'] = &$context['labels'][-1]['unread_messages'];
	$pm_areas['folders']['areas']['inbox']['messages'] = &$context['labels'][-1]['messages'];
	if (!empty($context['labels'][-1]['unread_messages']))
	{
		$pm_areas['folders']['areas']['inbox']['label'] .= ' <span class="amt">' . $context['labels'][-1]['unread_messages'] . '</span>';
		$pm_areas['folders']['title'] .= ' <span class="amt">' . $context['labels'][-1]['unread_messages'] . '</span>';
	}

	// Do we have a limit on the amount of messages we can keep?
	if (!empty($context['message_limit']))
	{
		$bar = round(($user_info['messages'] * 100) / $context['message_limit'], 1);

		$context['limit_bar'] = array(
			'messages' => $user_info['messages'],
			'allowed' => $context['message_limit'],
			'percent' => $bar,
			'bar' => $bar > 100 ? 100 : (int) $bar,
			'text' => sprintf($txt['pm_currently_using'], $user_info['messages'], $bar)
		);
	}

	require_once($sourcedir . '/Subs-Menu.php');

	// What page is this, again?
	$current_page = $scripturl . '?action=pm' . (!empty($_REQUEST['sa']) ? ';sa=' . $_REQUEST['sa'] : '') . (!empty($context['folder']) ? ';f=' . $context['folder'] : '') . (!empty($context['current_label_id']) ? ';l=' . $context['current_label_id'] : '');

	// Set a few options for the menu.
	$menuOptions = array(
		'current_area' => $area,
		'disable_url_session_check' => true,
	);

	// Actually create the menu!
	$pm_include_data = createMenu($pm_areas, $menuOptions);
	unset($pm_areas);

	// No menu means no access.
	if (!$pm_include_data && (!$user_info['is_guest'] || validateSession()))
		fatal_lang_error('no_access', false);

	// Make a note of the Unique ID for this menu.
	$context['pm_menu_id'] = $context['max_menu_id'];
	$context['pm_menu_name'] = 'menu_data_' . $context['pm_menu_id'];

	// Set the selected item.
	$current_area = $pm_include_data['current_area'];
	$context['menu_item_selected'] = $current_area;

	// Set the template for this area and add the profile layer.
	if (!WIRELESS && !isset($_REQUEST['xml']))
		$context['template_layers'][] = 'pm';
}

/**
 * The popup for when we ask for the popup from the user.
 */
function MessagePopup()
{
	global $context, $modSettings, $smcFunc, $memberContext, $scripturl, $user_settings, $db_show_debug;

	// We do not want to output debug information here.
	$db_show_debug = false;

	// We only want to output our little layer here.
	$context['template_layers'] = array();
	$context['sub_template'] = 'pm_popup';

	$context['can_send_pm'] = allowedTo('pm_send');
	$context['can_draft'] = allowedTo('pm_draft') && !empty($modSettings['drafts_pm_enabled']);

	// So are we loading stuff?
	$request = $smcFunc['db_query']('', '
		SELECT id_pm
		FROM {db_prefix}pm_recipients AS pmr
		WHERE pmr.id_member = {int:current_member}
			AND is_read = {int:not_read}
		ORDER BY id_pm',
		array(
			'current_member' => $context['user']['id'],
			'not_read' => 0,
		)
	);
	$pms = array();
	while ($row = $smcFunc['db_fetch_row']($request))
		$pms[] = $row[0];
	$smcFunc['db_free_result']($request);

	if (!empty($pms))
	{
		// Just quickly, it's possible that the number of PMs can get out of sync.
		$count_unread = count($pms);
		if ($count_unread != $user_settings['unread_messages'])
		{
			updateMemberData($context['user']['id'], array('unread_messages' => $count_unread));
			$context['user']['unread_messages'] = count($pms);
		}

		// Now, actually fetch me some PMs. Make sure we track the senders, got some work to do for them.
		$senders = array();

		$request = $smcFunc['db_query']('', '
			SELECT pm.id_pm, pm.id_pm_head, IFNULL(mem.id_member, pm.id_member_from) AS id_member_from,
				IFNULL(mem.real_name, pm.from_name) AS member_from, pm.msgtime AS timestamp, pm.subject
			FROM {db_prefix}personal_messages AS pm
				LEFT JOIN {db_prefix}members AS mem ON (pm.id_member_from = mem.id_member)
			WHERE pm.id_pm IN ({array_int:id_pms})',
			array(
				'id_pms' => $pms,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!empty($row['id_member_from']))
				$senders[] = $row['id_member_from'];

			$row['replied_to_you'] = $row['id_pm'] != $row['id_pm_head'];
			$row['time'] = timeformat($row['timestamp']);
			$row['pm_link'] = '<a href="' . $scripturl . '?action=pm;f=inbox;pmsg=' . $row['id_pm'] . '">' . $row['subject'] . '</a>';
			$context['unread_pms'][$row['id_pm']] = $row;
		}
		$smcFunc['db_free_result']($request);

		if (!empty($senders))
		{
			$senders = loadMemberData($senders);
			foreach ($senders as $member)
				loadMemberContext($member);
		}

		// Having loaded everyone, attach them to the PMs.
		foreach ($context['unread_pms'] as $id_pm => $details)
			if (!empty($memberContext[$details['id_member_from']]))
				$context['unread_pms'][$id_pm]['member'] = &$memberContext[$details['id_member_from']];
	}
}

/**
 * A folder, ie. inbox/sent etc.
 */
function MessageFolder()
{
	global $txt, $scripturl, $modSettings, $context, $subjects_request;
	global $messages_request, $user_info, $recipients, $options, $smcFunc, $user_settings;

	// Changing view?
	if (isset($_GET['view']))
	{
		$context['display_mode'] = $context['display_mode'] > 1 ? 0 : $context['display_mode'] + 1;
		updateMemberData($user_info['id'], array('pm_prefs' => ($user_settings['pm_prefs'] & 252) | $context['display_mode']));
	}

	// Make sure the starting location is valid.
	if (isset($_GET['start']) && $_GET['start'] != 'new')
		$_GET['start'] = (int) $_GET['start'];
	elseif (!isset($_GET['start']) && !empty($options['view_newest_pm_first']))
		$_GET['start'] = 0;
	else
		$_GET['start'] = 'new';

	// Set up some basic theme stuff.
	$context['from_or_to'] = $context['folder'] != 'sent' ? 'from' : 'to';
	$context['get_pmessage'] = 'prepareMessageContext';
	$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	$labelJoin = '';
	$labelQuery = '';
	$labelQuery2 = '';

	// SMF logic: If you're viewing a label, it's still the inbox
	if ($context['folder'] == 'inbox' && $context['current_label_id'] == -1)
	{
		$labelQuery = '
			AND pmr.in_inbox = 1';
	}
	elseif ($context['folder'] != 'sent')
	{
		$labelJoin = '
			INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pmr.id_pm)';

		$labelQuery2 = '
			AND pl.id_label = ' . $context['current_label_id'];
	}

	// Set the index bar correct!
	messageIndexBar($context['current_label_id'] == -1 ? $context['folder'] : 'label' . $context['current_label_id']);

	// Sorting the folder.
	$sort_methods = array(
		'date' => 'pm.id_pm',
		'name' => 'IFNULL(mem.real_name, \'\')',
		'subject' => 'pm.subject',
	);

	// They didn't pick one, use the forum default.
	if (!isset($_GET['sort']) || !isset($sort_methods[$_GET['sort']]))
	{
		$context['sort_by'] = 'date';
		$_GET['sort'] = 'pm.id_pm';
		// An overriding setting?
		$descending = !empty($options['view_newest_pm_first']);
	}
	// Otherwise use the defaults: ascending, by date.
	else
	{
		$context['sort_by'] = $_GET['sort'];
		$_GET['sort'] = $sort_methods[$_GET['sort']];
		$descending = isset($_GET['desc']);
	}

	$context['sort_direction'] = $descending ? 'down' : 'up';

	// Set the text to resemble the current folder.
	$pmbox = $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'];
	$txt['delete_all'] = str_replace('PMBOX', $pmbox, $txt['delete_all']);

	// Now, build the link tree!
	if ($context['current_label_id'] == -1)
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;f=' . $context['folder'],
			'name' => $pmbox
		);

	// Build it further for a label.
	if ($context['current_label_id'] != -1)
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=pm;f=' . $context['folder'] . ';l=' . $context['current_label_id'],
			'name' => $txt['pm_current_label'] . ': ' . $context['current_label']
		);

	// Figure out how many messages there are.
	if ($context['folder'] == 'sent')
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
			FROM {db_prefix}personal_messages AS pm
			WHERE pm.id_member_from = {int:current_member}
				AND pm.deleted_by_sender = {int:not_deleted}',
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
			)
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
			FROM {db_prefix}pm_recipients AS pmr' . ($context['display_mode'] == 2 ? '
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' : '') . $labelJoin . '
			WHERE pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}' . $labelQuery . $labelQuery2,
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
			)
		);
	list ($max_messages) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Only show the button if there are messages to delete.
	$context['show_delete'] = $max_messages > 0;
	$maxPerPage = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) && !WIRELESS ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	// Start on the last page.
	if (!is_numeric($_GET['start']) || $_GET['start'] >= $max_messages)
		$_GET['start'] = ($max_messages - 1) - (($max_messages - 1) % $maxPerPage);
	elseif ($_GET['start'] < 0)
		$_GET['start'] = 0;

	// ... but wait - what if we want to start from a specific message?
	if (isset($_GET['pmid']))
	{
		$pmID = (int) $_GET['pmid'];

		// Make sure you have access to this PM.
		if (!isAccessiblePM($pmID, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
			fatal_lang_error('no_access', false);

		$context['current_pm'] = $pmID;

		// With only one page of PM's we're gonna want page 1.
		if ($max_messages <= $maxPerPage)
			$_GET['start'] = 0;
		// If we pass kstart we assume we're in the right place.
		elseif (!isset($_GET['kstart']))
		{
			if ($context['folder'] == 'sent')
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
					FROM {db_prefix}personal_messages
					WHERE id_member_from = {int:current_member}
						AND deleted_by_sender = {int:not_deleted}
						AND id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}',
					array(
						'current_member' => $user_info['id'],
						'not_deleted' => 0,
						'id_pm' => $pmID,
					)
				);
			else
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
					FROM {db_prefix}pm_recipients AS pmr' . ($context['display_mode'] == 2 ? '
						INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' : '') . $labelJoin . '
					WHERE pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}' . $labelQuery .  $labelQuery2 . '
						AND pmr.id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}',
					array(
						'current_member' => $user_info['id'],
						'not_deleted' => 0,
						'id_pm' => $pmID,
					)
				);

			list ($_GET['start']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// To stop the page index's being abnormal, start the page on the page the message would normally be located on...
			$_GET['start'] = $maxPerPage * (int) ($_GET['start'] / $maxPerPage);
		}
	}

	// Sanitize and validate pmsg variable if set.
	if (isset($_GET['pmsg']))
	{
		$pmsg = (int) $_GET['pmsg'];

		if (!isAccessiblePM($pmsg, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
			fatal_lang_error('no_access', false);
	}

	// Set up the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=pm;f=' . $context['folder'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $_GET['start'], $max_messages, $maxPerPage);
	$context['start'] = $_GET['start'];

	// Determine the navigation context (especially useful for the wireless template).
	$context['links'] = array(
		'first' => $_GET['start'] >= $maxPerPage ? $scripturl . '?action=pm;start=0' : '',
		'prev' => $_GET['start'] >= $maxPerPage ? $scripturl . '?action=pm;start=' . ($_GET['start'] - $maxPerPage) : '',
		'next' => $_GET['start'] + $maxPerPage < $max_messages ? $scripturl . '?action=pm;start=' . ($_GET['start'] + $maxPerPage) : '',
		'last' => $_GET['start'] + $maxPerPage < $max_messages ? $scripturl . '?action=pm;start=' . (floor(($max_messages - 1) / $maxPerPage) * $maxPerPage) : '',
		'up' => $scripturl,
	);
	$context['page_info'] = array(
		'current_page' => $_GET['start'] / $maxPerPage + 1,
		'num_pages' => floor(($max_messages - 1) / $maxPerPage) + 1
	);

	// First work out what messages we need to see - if grouped is a little trickier...
	if ($context['display_mode'] == 2)
	{
		if ($context['folder'] != 'sent' && $context['folder'] != 'inbox')
		{
			$labelJoin = '
					INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pm.id_pm)';

			$labelQuery = '';
			$labelQuery2 = '
						AND pl.id_label = ' . $context['current_label_id'];
		}

		// On a non-default sort due to PostgreSQL we have to do a harder sort.
		if ($smcFunc['db_title'] == 'PostgreSQL' && $_GET['sort'] != 'pm.id_pm')
		{
			$sub_request = $smcFunc['db_query']('', '
				SELECT MAX({raw:sort}) AS sort_param, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($context['sort_by'] == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}
						' . $labelQuery . ')') . $labelJoin . ($context['sort_by'] == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})') : '') . '
				WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:not_deleted}' : '1=1') . (empty($pmsg) ? '' : '
					AND pm.id_pm = {int:id_pm}') . $labelQuery2 . '
				GROUP BY pm.id_pm_head
				ORDER BY sort_param' . ($descending ? ' DESC' : ' ASC') . (empty($pmsg) ? '
				LIMIT ' . $_GET['start'] . ', ' . $maxPerPage : ''),
				array(
					'current_member' => $user_info['id'],
					'not_deleted' => 0,
					'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
					'id_pm' => isset($pmsg) ? $pmsg : '0',
					'sort' => $_GET['sort'],
				)
			);
			$sub_pms = array();
			while ($row = $smcFunc['db_fetch_assoc']($sub_request))
				$sub_pms[$row['id_pm_head']] = $row['sort_param'];

			$smcFunc['db_free_result']($sub_request);

			$request = $smcFunc['db_query']('', '
				SELECT pm.id_pm AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($context['sort_by'] == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}
						' . $labelQuery . ')') . $labelJoin . ($context['sort_by'] == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})') : '') . '
				WHERE ' . (empty($sub_pms) ? '0=1' : 'pm.id_pm IN ({array_int:pm_list})') . $labelQuery2 . '
				ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' && $context['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($pmsg) ? '
				LIMIT ' . $_GET['start'] . ', ' . $maxPerPage : ''),
				array(
					'current_member' => $user_info['id'],
					'pm_list' => array_keys($sub_pms),
					'not_deleted' => 0,
					'sort' => $_GET['sort'],
					'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				)
			);
		}
		else
		{
			$request = $smcFunc['db_query']('pm_conversation_list', '
				SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($context['sort_by'] == 'name' ? '
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
						AND pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:deleted_by}
						' . $labelQuery . ')') . $labelJoin . ($context['sort_by'] == 'name' ? ( '
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
				WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
					AND pm.deleted_by_sender = {int:deleted_by}' : '1=1') . (empty($pmsg) ? '' : '
					AND pm.id_pm = {int:pmsg}') . $labelQuery2 . '
				GROUP BY pm.id_pm_head
				ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' && $context['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($_GET['pmsg']) ? '
				LIMIT ' . $_GET['start'] . ', ' . $maxPerPage : ''),
				array(
					'current_member' => $user_info['id'],
					'deleted_by' => 0,
					'sort' => $_GET['sort'],
					'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
					'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
				)
			);
		}
	}
	// This is kinda simple!
	else
	{
		// @todo SLOW This query uses a filesort. (inbox only.)
		$request = $smcFunc['db_query']('', '
			SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' . ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:is_deleted}
					' . $labelQuery . ')') . $labelJoin . ($context['sort_by'] == 'name' ? ( '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
			WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {raw:current_member}
				AND pm.deleted_by_sender = {int:is_deleted}' : '1=1') . (empty($pmsg) ? '' : '
				AND pm.id_pm = {int:pmsg}') . $labelQuery2 . '
			ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' && $context['folder'] != 'sent' ? 'pmr.id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($pmsg) ? '
			LIMIT ' . $_GET['start'] . ', ' . $maxPerPage : ''),
			array(
				'current_member' => $user_info['id'],
				'is_deleted' => 0,
				'sort' => $_GET['sort'],
				'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			)
		);
	}
	// Load the id_pms and initialize recipients.
	$pms = array();
	$lastData = array();
	$posters = $context['folder'] == 'sent' ? array($user_info['id']) : array();
	$recipients = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($recipients[$row['id_pm']]))
		{
			if (isset($row['id_member_from']))
				$posters[$row['id_pm']] = $row['id_member_from'];
			$pms[$row['id_pm']] = $row['id_pm'];
			$recipients[$row['id_pm']] = array(
				'to' => array(),
				'bcc' => array()
			);
		}

		// Keep track of the last message so we know what the head is without another query!
		if ((empty($pmID) && (empty($options['view_newest_pm_first']) || !isset($lastData))) || empty($lastData) || (!empty($pmID) && $pmID == $row['id_pm']))
			$lastData = array(
				'id' => $row['id_pm'],
				'head' => $row['id_pm_head'],
			);
	}
	$smcFunc['db_free_result']($request);

	// Make sure that we have been given a correct head pm id!
	if ($context['display_mode'] == 2 && !empty($pmID) && $pmID != $lastData['id'])
		fatal_lang_error('no_access', false);

	if (!empty($pms))
	{
		// Select the correct current message.
		if (empty($pmID))
			$context['current_pm'] = $lastData['id'];

		// This is a list of the pm's that are used for "full" display.
		if ($context['display_mode'] == 0)
			$display_pms = $pms;
		else
			$display_pms = array($context['current_pm']);

		// At this point we know the main id_pm's. But - if we are looking at conversations we need the others!
		if ($context['display_mode'] == 2)
		{
			$request = $smcFunc['db_query']('', '
				SELECT pm.id_pm, pm.id_member_from, pm.deleted_by_sender, pmr.id_member, pmr.deleted
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head = {int:id_pm_head}
					AND ((pm.id_member_from = {int:current_member} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:current_member} AND pmr.deleted = {int:not_deleted}))
				ORDER BY pm.id_pm',
				array(
					'current_member' => $user_info['id'],
					'id_pm_head' => $lastData['head'],
					'not_deleted' => 0,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// This is, frankly, a joke. We will put in a workaround for people sending to themselves - yawn!
				if ($context['folder'] == 'sent' && $row['id_member_from'] == $user_info['id'] && $row['deleted_by_sender'] == 1)
					continue;
				elseif ($row['id_member'] == $user_info['id'] & $row['deleted'] == 1)
					continue;

				if (!isset($recipients[$row['id_pm']]))
					$recipients[$row['id_pm']] = array(
						'to' => array(),
						'bcc' => array()
					);
				$display_pms[] = $row['id_pm'];
				$posters[$row['id_pm']] = $row['id_member_from'];
			}
			$smcFunc['db_free_result']($request);
		}

		// This is pretty much EVERY pm!
		$all_pms = array_merge($pms, $display_pms);
		$all_pms = array_unique($all_pms);

		// Get recipients (don't include bcc-recipients for your inbox, you're not supposed to know :P).
		$request = $smcFunc['db_query']('', '
			SELECT pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc, pmr.in_inbox, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $all_pms,
			)
		);
		$context['message_labels'] = array();
		$context['message_replied'] = array();
		$context['message_unread'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$row['id_pm']][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($row['id_member_to'] == $user_info['id'] && $context['folder'] != 'sent')
			{
				$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;
				$context['message_unread'][$row['id_pm']] = $row['is_read'] == 0;

				// Get the labels for this PM
				$request2 = $smcFunc['db_query']('', '
					SELECT id_label
					FROM {db_prefix}pm_labeled_messages
					WHERE id_pm = {int:current_pm}',
					array(
						'current_pm' => $row['id_pm'],
					)
				);

				while($row2 = $smcFunc['db_fetch_assoc']($request2))
				{
					$l_id = $row2['id_label'];
					if (isset($context['labels'][$l_id]))
						$context['message_labels'][$row['id_pm']][$l_id] = array('id' => $l_id, 'name' => $context['labels'][$l_id]['name']);
				}

				$smcFunc['db_free_result']($request2);

				// Is this in the inbox as well?
				if ($row['in_inbox'] == 1)
				{
					$context['message_labels'][$row['id_pm']][-1] = array('id' => -1, 'name' => $context['labels'][-1]['name']);
				}
			}
		}
		$smcFunc['db_free_result']($request);

		// Make sure we don't load unnecessary data.
		if ($context['display_mode'] == 1)
		{
			foreach ($posters as $k => $v)
				if (!in_array($k, $display_pms))
					unset($posters[$k]);
		}

		// Load any users....
		$posters = array_unique($posters);
		if (!empty($posters))
			loadMemberData($posters);

		// If we're on grouped/restricted view get a restricted list of messages.
		if ($context['display_mode'] != 0)
		{
			// Get the order right.
			$orderBy = array();
			foreach (array_reverse($pms) as $pm)
				$orderBy[] = 'pm.id_pm = ' . $pm;

			// Seperate query for these bits!
			$subjects_request = $smcFunc['db_query']('', '
				SELECT pm.id_pm, pm.subject, IFNULL(pm.id_member_from, 0) AS id_member_from, pm.msgtime, IFNULL(mem.real_name, pm.from_name) AS from_name,
					mem.id_member
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_pm IN ({array_int:pm_list})
				ORDER BY ' . implode(', ', $orderBy) . '
				LIMIT ' . count($pms),
				array(
					'pm_list' => $pms,
				)
			);
		}

		// Execute the query!
		$messages_request = $smcFunc['db_query']('', '
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') . ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})' : '') . '
			WHERE pm.id_pm IN ({array_int:display_pms})' . ($context['folder'] == 'sent' ? '
			GROUP BY pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name' : '') . '
			ORDER BY ' . ($context['display_mode'] == 2 ? 'pm.id_pm' : $_GET['sort']) . ($descending ? ' DESC' : ' ASC') . '
			LIMIT ' . count($display_pms),
			array(
				'display_pms' => $display_pms,
				'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
			)
		);

		// Build the conversation button array.
		if ($context['display_mode'] == 2)
		{
			$context['conversation_buttons'] = array(
				'delete' => array('text' => 'delete_conversation', 'image' => 'delete.png', 'lang' => true, 'url' => $scripturl . '?action=pm;sa=pmactions;pm_actions[' . $context['current_pm'] . ']=delete;conversation;f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';' . $context['session_var'] . '=' . $context['session_id'], 'custom' => 'data-confirm="' . $txt['remove_conversation'] . '"', 'class' => 'you_sure'),
			);

			// Allow mods to add additional buttons here
			call_integration_hook('integrate_conversation_buttons');
		}
	}
	else
		$messages_request = false;

	$context['can_send_pm'] = allowedTo('pm_send');
	$context['can_send_email'] = allowedTo('moderate_forum');
	if (!WIRELESS)
		$context['sub_template'] = 'folder';
	$context['page_title'] = $txt['pm_inbox'];

	// Finally mark the relevant messages as read.
	if ($context['folder'] != 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
	{
		// If the display mode is "old sk00l" do them all...
		if ($context['display_mode'] == 0)
			markMessages(null, $context['current_label_id']);
		// Otherwise do just the current one!
		elseif (!empty($context['current_pm']))
			markMessages($display_pms, $context['current_label_id']);
	}
}

/**
 * Get a personal message for the theme.  (used to save memory.)
 *
 * @param $type
 * @param $reset
 */
function prepareMessageContext($type = 'subject', $reset = false)
{
	global $txt, $scripturl, $modSettings, $context, $messages_request, $memberContext, $recipients, $smcFunc;
	global $user_info, $subjects_request;

	// Count the current message number....
	static $counter = null;
	if ($counter === null || $reset)
		$counter = $context['start'];

	static $temp_pm_selected = null;
	if ($temp_pm_selected === null)
	{
		$temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : array();
		$_SESSION['pm_selected'] = array();
	}

	// If we're in non-boring view do something exciting!
	if ($context['display_mode'] != 0 && $subjects_request && $type == 'subject')
	{
		$subject = $smcFunc['db_fetch_assoc']($subjects_request);
		if (!$subject)
		{
			$smcFunc['db_free_result']($subjects_request);
			return false;
		}

		$subject['subject'] = $subject['subject'] == '' ? $txt['no_subject'] : $subject['subject'];
		censorText($subject['subject']);

		$output = array(
			'id' => $subject['id_pm'],
			'member' => array(
				'id' => $subject['id_member_from'],
				'name' => $subject['from_name'],
				'link' => ($subject['id_member_from'] != 0) ? '<a href="' . $scripturl . '?action=profile;u=' . $subject['id_member_from'] . '">' . $subject['from_name'] . '</a>' : $subject['from_name'],
			),
			'recipients' => &$recipients[$subject['id_pm']],
			'subject' => $subject['subject'],
			'time' => timeformat($subject['msgtime']),
			'timestamp' => forum_time(true, $subject['msgtime']),
			'number_recipients' => count($recipients[$subject['id_pm']]['to']),
			'labels' => &$context['message_labels'][$subject['id_pm']],
			'fully_labeled' => count($context['message_labels'][$subject['id_pm']]) == count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$subject['id_pm']],
			'is_unread' => &$context['message_unread'][$subject['id_pm']],
			'is_selected' => !empty($temp_pm_selected) && in_array($subject['id_pm'], $temp_pm_selected),
		);

		return $output;
	}

	// Bail if it's false, ie. no messages.
	if ($messages_request == false)
		return false;

	// Reset the data?
	if ($reset == true)
		return @$smcFunc['db_data_seek']($messages_request, 0);

	// Get the next one... bail if anything goes wrong.
	$message = $smcFunc['db_fetch_assoc']($messages_request);
	if (!$message)
	{
		if ($type != 'subject')
			$smcFunc['db_free_result']($messages_request);

		return false;
	}

	// Use '(no subject)' if none was specified.
	$message['subject'] = $message['subject'] == '' ? $txt['no_subject'] : $message['subject'];

	// Load the message's information - if it's not there, load the guest information.
	if (!loadMemberContext($message['id_member_from'], true))
	{
		$memberContext[$message['id_member_from']]['name'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['id'] = 0;

		// Sometimes the forum sends messages itself (Warnings are an example) - in this case don't label it from a guest.
		$memberContext[$message['id_member_from']]['group'] = $message['from_name'] == $context['forum_name_html_safe'] ? '' : $txt['guest_title'];
		$memberContext[$message['id_member_from']]['link'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['email'] = '';
		$memberContext[$message['id_member_from']]['show_email'] = false;
		$memberContext[$message['id_member_from']]['is_guest'] = true;
	}
	else
	{
		$memberContext[$message['id_member_from']]['can_view_profile'] = allowedTo('profile_view') || ($message['id_member_from'] == $user_info['id'] && !$user_info['is_guest']);
		$memberContext[$message['id_member_from']]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$message['id_member_from']]['warning_status'] && ($context['user']['can_mod'] || (!empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $message['id_member_from'] == $user_info['id'])));
		// Show the email if it's your own PM
		$memberContext[$message['id_member_from']]['show_email'] |= $message['id_member_from'] == $user_info['id'];
	}

	$memberContext[$message['id_member_from']]['show_profile_buttons'] = $modSettings['show_profile_buttons'] && (!empty($memberContext[$message['id_member_from']]['can_view_profile']) || (!empty($memberContext[$message['id_member_from']]['website']['url']) && !isset($context['disabled_fields']['website'])) || $memberContext[$message['id_member_from']]['show_email'] || $context['can_send_pm']);

	// Censor all the important text...
	censorText($message['body']);
	censorText($message['subject']);

	// Run UBBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], true, 'pm' . $message['id_pm']);

	// Send the array.
	$output = array(
		'id' => $message['id_pm'],
		'member' => &$memberContext[$message['id_member_from']],
		'subject' => $message['subject'],
		'time' => timeformat($message['msgtime']),
		'timestamp' => forum_time(true, $message['msgtime']),
		'counter' => $counter,
		'body' => $message['body'],
		'recipients' => &$recipients[$message['id_pm']],
		'number_recipients' => count($recipients[$message['id_pm']]['to']),
		'labels' => &$context['message_labels'][$message['id_pm']],
		'fully_labeled' => count($context['message_labels'][$message['id_pm']]) == count($context['labels']),
		'is_replied_to' => &$context['message_replied'][$message['id_pm']],
		'is_unread' => &$context['message_unread'][$message['id_pm']],
		'is_selected' => !empty($temp_pm_selected) && in_array($message['id_pm'], $temp_pm_selected),
		'is_message_author' => $message['id_member_from'] == $user_info['id'],
		'can_report' => !empty($modSettings['enableReportPM']),
		'can_see_ip' => allowedTo('moderate_forum') || ($message['id_member_from'] == $user_info['id'] && !empty($user_info['id'])),
	);

	$counter++;

	// Any custom profile fields?
	if (!empty($memberContext[$message['id_member_from']]['custom_fields']))
		foreach ($memberContext[$message['id_member_from']]['custom_fields'] as $custom)
			switch ($custom['placement'])
			{
				case 1:
					$output['custom_fields']['icons'][] = $custom;
					break;
				case 2:
					$output['custom_fields']['above_signature'][] = $custom;
					break;
				case 3:
					$output['custom_fields']['below_signature'][] = $custom;
					break;
				case 4:
					$output['custom_fields']['below_avatar'][] = $custom;
					break;
				case 5:
					$output['custom_fields']['above_member'][] = $custom;
					break;
				case 6:
					$output['custom_fields']['bottom_poster'][] = $custom;
					break;
				default:
					$output['custom_fields']['standard'][] = $custom;
			}

	return $output;
}

/**
 * Allows to search through personal messages.
 */
function MessageSearch()
{
	global $context, $txt, $scripturl, $smcFunc;

	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$context['search_params'][$k] = $v;
		}
	}
	if (isset($_REQUEST['search']))
		$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);

	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = $smcFunc['htmlspecialchars']($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = $smcFunc['htmlspecialchars']($context['search_params']['userspec']);

	if (!empty($context['search_params']['searchtype']))
		$context['search_params']['searchtype'] = 2;

	if (!empty($context['search_params']['minage']))
		$context['search_params']['minage'] = (int) $context['search_params']['minage'];

	if (!empty($context['search_params']['maxage']))
		$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

	$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);
	$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);

	// Create the array of labels to be searched.
	$context['search_labels'] = array();
	$searchedLabels = isset($context['search_params']['labels']) && $context['search_params']['labels'] != '' ? explode(',', $context['search_params']['labels']) : array();
	foreach ($context['labels'] as $label)
	{
		$context['search_labels'][] = array(
			'id' => $label['id'],
			'name' => $label['name'],
			'checked' => !empty($searchedLabels) ? in_array($label['id'], $searchedLabels) : true,
		);
	}

	// Are all the labels checked?
	$context['check_all'] = empty($searchedLabels) || count($context['search_labels']) == count($searchedLabels);

	// Load the error text strings if there were errors in the search.
	if (!empty($context['search_errors']))
	{
		loadLanguage('Errors');
		$context['search_errors']['messages'] = array();
		foreach ($context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error == 'messages')
				continue;

			$context['search_errors']['messages'][] = $txt['error_' . $search_error];
		}
	}

	$context['page_title'] = $txt['pm_search_title'];
	$context['sub_template'] = 'search';
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=search',
		'name' => $txt['pm_search_bar_title'],
	);
}

/**
 * Actually do the search of personal messages.
 */
function MessageSearch2()
{
	global $scripturl, $modSettings, $user_info, $context, $txt;
	global $memberContext, $smcFunc;

	if (!empty($context['load_average']) && !empty($modSettings['loadavg_search']) && $context['load_average'] >= $modSettings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	/**
	 * @todo For the moment force the folder to the inbox.
	 * @todo Maybe set the inbox based on a cookie or theme setting?
	 */
	$context['folder'] = 'inbox';

	// Some useful general permissions.
	$context['can_send_pm'] = allowedTo('pm_send');

	// Some hardcoded veriables that can be tweaked if required.
	$maxMembersToSearch = 500;

	// Extract all the search parameters.
	$search_params = array();
	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$search_params[$k] = $v;
		}
	}

	$context['start'] = isset($_GET['start']) ? (int) $_GET['start'] : 0;

	// Store whether simple search was used (needed if the user wants to do another query).
	if (!isset($search_params['advanced']))
		$search_params['advanced'] = empty($_REQUEST['advanced']) ? 0 : 1;

	// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
	if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
		$search_params['searchtype'] = 2;

	// Minimum age of messages. Default to zero (don't set param in that case).
	if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
		$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

	// Maximum age of messages. Default to infinite (9999 days: param not set).
	if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] != 9999))
		$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);
	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);

	// Default the user name to a wildcard matching every user (*).
	if (!empty($search_params['user_spec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

	// This will be full of all kinds of parameters!
	$searchq_parameters = array();

	// If there's no specific user, then don't mention it in the main query.
	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr($smcFunc['htmlspecialchars']($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++)
		{
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0)
				unset($possible_users[$k]);
		}

		if (!empty($possible_users))
		{
			// We need to bring this into the query and do it nice and cleanly.
			$where_params = array();
			$where_clause = array();
			foreach ($possible_users as $k => $v)
			{
				$where_params['name_' . $k] = $v;
				$where_clause[] = '{raw:real_name} LIKE {string:name_' . $k . '}';
				if (!isset($where_params['real_name']))
					$where_params['real_name'] = $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name';
			}

			// Who matches those criteria?
			// @todo This doesn't support sent item searching.
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE ' . implode(' OR ', $where_clause),
				$where_params
			);

			// Simply do nothing if there're too many members matching the criteria.
			if ($smcFunc['db_num_rows']($request) > $maxMembersToSearch)
				$userQuery = '';
			elseif ($smcFunc['db_num_rows']($request) == 0)
			{
				$userQuery = 'AND pm.id_member_from = 0 AND ({raw:pm_from_name} LIKE {raw:guest_user_name_implode})';
				$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(pm.from_name)' : 'pm.from_name') . ' LIKE \'', $possible_users) . '\'';
				$searchq_parameters['pm_from_name'] = $smcFunc['db_case_sensitive'] ? 'LOWER(pm.from_name)' : 'pm.from_name';
			}
			else
			{
				$memberlist = array();
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$memberlist[] = $row['id_member'];
				$userQuery = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND ({raw:pm_from_name} LIKE {raw:guest_user_name_implode})))';
				$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(pm.from_name)' : 'pm.from_name') . ' LIKE \'', $possible_users) . '\'';
				$searchq_parameters['member_list'] = $memberlist;
				$searchq_parameters['pm_from_name'] = $smcFunc['db_case_sensitive'] ? 'LOWER(pm.from_name)' : 'pm.from_name';
			}
			$smcFunc['db_free_result']($request);
		}
		else
			$userQuery = '';
	}

	// Setup the sorting variables...
	// @todo Add more in here!
	$sort_columns = array(
		'pm.id_pm',
	);
	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'pm.id_pm';
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

	// Sort out any labels we may be searching by.
	$labelQuery = '';
	$labelJoin = '';
	if ($context['folder'] == 'inbox' && !empty($search_params['advanced']) && $context['currently_using_labels'])
	{
		// Came here from pagination?  Put them back into $_REQUEST for sanitization.
		if (isset($search_params['labels']))
			$_REQUEST['searchlabel'] = explode(',', $search_params['labels']);

		// Assuming we have some labels - make them all integers.
		if (!empty($_REQUEST['searchlabel']) && is_array($_REQUEST['searchlabel']))
		{
			foreach ($_REQUEST['searchlabel'] as $key => $id)
				$_REQUEST['searchlabel'][$key] = (int) $id;
		}
		else
			$_REQUEST['searchlabel'] = array();

		// Now that everything is cleaned up a bit, make the labels a param.
		$search_params['labels'] = implode(',', $_REQUEST['searchlabel']);

		// No labels selected? That must be an error!
		if (empty($_REQUEST['searchlabel']))
			$context['search_errors']['no_labels_selected'] = true;
		// Otherwise prepare the query!
		elseif (count($_REQUEST['searchlabel']) != count($context['labels']))
		{
			// Special case here... "inbox" isn't a real label anymore...
			if (in_array(-1, $_REQUEST['searchlabel']))
			{
				$labelQuery = '	AND pmr.in_inbox = {int:in_inbox}';
				$searchq_parameters['in_inbox'] = 1;

				// Now we get rid of that...
				$temp = array_diff($_REQUEST['searchlabel'], array(-1));
				$_REQUEST['searchlabel'] = $temp;
			}

			// Still have something?
			if (!empty($_REQUEST['searchlabel']))
			{
				if ($labelQuery == '')
				{
					// Not searching the inbox - PM must be labeled
					$labelQuery = ' AND pml.id_label IN ({array_int:labels})';
					$labelJoin = ' INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)';
				}
				else
				{
					// Searching the inbox - PM doesn't have to be labeled
					$labelQuery = ' AND (' . substr($labelQuery, 5) . ' OR pml.id_label IN ({array_int:labels}))';
					$labelJoin = ' LEFT JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)';
				}

				$searchq_parameters['labels'] = $_REQUEST['searchlabel'];
			}
		}
	}

	// What are we actually searching for?
	$search_params['search'] = !empty($search_params['search']) ? $search_params['search'] : (isset($_REQUEST['search']) ? $_REQUEST['search'] : '');
	// If we ain't got nothing - we should error!
	if (!isset($search_params['search']) || $search_params['search'] == '')
		$context['search_errors']['invalid_search_string'] = true;

	// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
	preg_match_all('~(?:^|\s)([-]?)"([^"]+)"(?:$|\s)~' . ($context['utf8'] ? 'u' : ''), $search_params['search'], $matches, PREG_PATTERN_ORDER);
	$searchArray = $matches[2];

	// Remove the phrase parts and extract the words.
	$tempSearch = explode(' ', preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~' . ($context['utf8'] ? 'u' : ''), ' ', $search_params['search']));

	// A minus sign in front of a word excludes the word.... so...
	$excludedWords = array();

	// .. first, we check for things like -"some words", but not "-some words".
	foreach ($matches[1] as $index => $word)
		if ($word == '-')
		{
			$word = $smcFunc['strtolower'](trim($searchArray[$index]));
			if (strlen($word) > 0)
				$excludedWords[] = $word;
			unset($searchArray[$index]);
		}

	// Now we look for -test, etc.... normaller.
	foreach ($tempSearch as $index => $word)
	{
		if (strpos(trim($word), '-') === 0)
		{
			$word = substr($smcFunc['strtolower']($word), 1);
			if (strlen($word) > 0)
				$excludedWords[] = $word;
			unset($tempSearch[$index]);
		}
	}

	$searchArray = array_merge($searchArray, $tempSearch);

	// Trim everything and make sure there are no words that are the same.
	foreach ($searchArray as $index => $value)
	{
		$searchArray[$index] = $smcFunc['strtolower'](trim($value));
		if ($searchArray[$index] == '')
			unset($searchArray[$index]);
		else
		{
			// Sort out entities first.
			$searchArray[$index] = $smcFunc['htmlspecialchars']($searchArray[$index]);
		}
	}
	$searchArray = array_unique($searchArray);

	// Create an array of replacements for highlighting.
	$context['mark'] = array();
	foreach ($searchArray as $word)
		$context['mark'][$word] = '<strong class="highlight">' . $word . '</strong>';

	// This contains *everything*
	$searchWords = array_merge($searchArray, $excludedWords);

	// Make sure at least one word is being searched for.
	if (empty($searchArray))
		$context['search_errors']['invalid_search_string'] = true;

	// Sort out the search query so the user can edit it - if they want.
	$context['search_params'] = $search_params;
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = $smcFunc['htmlspecialchars']($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = $smcFunc['htmlspecialchars']($context['search_params']['userspec']);

	// Now we have all the parameters, combine them together for pagination and the like...
	$context['params'] = array();
	foreach ($search_params as $k => $v)
		$context['params'][] = $k . '|\'|' . $v;
	$context['params'] = base64_encode(implode('|"|', $context['params']));

	// Compile the subject query part.
	$andQueryParts = array();

	foreach ($searchWords as $index => $word)
	{
		if ($word == '')
			continue;

		if ($search_params['subject_only'])
			$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
		else
			$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';
		$searchq_parameters['search_' . $index] = '%' . strtr($word, array('_' => '\\_', '%' => '\\%')) . '%';
	}

	$searchQuery = ' 1=1';
	if (!empty($andQueryParts))
		$searchQuery = implode(!empty($search_params['searchtype']) && $search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);

	// Age limits?
	$timeQuery = '';
	if (!empty($search_params['minage']))
		$timeQuery .= ' AND pm.msgtime < ' . (time() - $search_params['minage'] * 86400);
	if (!empty($search_params['maxage']))
		$timeQuery .= ' AND pm.msgtime > ' . (time() - $search_params['maxage'] * 86400);

	// If we have errors - return back to the first screen...
	if (!empty($context['search_errors']))
	{
		$_REQUEST['params'] = $context['params'];
		return MessageSearch();
	}

	// Get the amount of results.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			' . $labelJoin . '
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')',
		array_merge($searchq_parameters, array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		))
	);
	list ($numResults) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Get all the matching messages... using standard search only (No caching and the like!)
	// @todo This doesn't support sent item searching yet.
	$request = $smcFunc['db_query']('', '
		SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			' . $labelJoin . '
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')
		ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
		LIMIT ' . $context['start'] . ', ' . $modSettings['search_results_per_page'],
		array_merge($searchq_parameters, array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		))
	);
	$foundMessages = array();
	$posters = array();
	$head_pms = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$foundMessages[] = $row['id_pm'];
		$posters[] = $row['id_member_from'];
		$head_pms[$row['id_pm']] = $row['id_pm_head'];
	}
	$smcFunc['db_free_result']($request);

	// Find the real head pms!
	if ($context['display_mode'] == 2 && !empty($head_pms))
	{
		$request = $smcFunc['db_query']('', '
			SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
			WHERE pm.id_pm_head IN ({array_int:head_pms})
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			GROUP BY pm.id_pm_head
			LIMIT {int:limit}',
			array(
				'head_pms' => array_unique($head_pms),
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'limit' => count($head_pms),
			)
		);
		$real_pm_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$real_pm_ids[$row['id_pm_head']] = $row['id_pm'];
		$smcFunc['db_free_result']($request);
	}

	// Load the users...
	$posters = array_unique($posters);
	if (!empty($posters))
		loadMemberData($posters);

	// Sort out the page index.
	$context['page_index'] = constructPageIndex($scripturl . '?action=pm;sa=search2;params=' . $context['params'], $_GET['start'], $numResults, $modSettings['search_results_per_page'], false);

	$context['message_labels'] = array();
	$context['message_replied'] = array();
	$context['personal_messages'] = array();

	if (!empty($foundMessages))
	{
		// Now get recipients (but don't include bcc-recipients for your inbox, you're not supposed to know :P!)
		$request = $smcFunc['db_query']('', '
			SELECT
				pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name,
				pmr.bcc, pmr.in_inbox, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:message_list})',
			array(
				'message_list' => $foundMessages,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$row['id_pm']][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($row['id_member_to'] == $user_info['id'] && $context['folder'] != 'sent')
			{
				$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;

				$row['labels'] = '';

				// Get the labels for this PM
				$request2 = $smcFunc['db_query']('', '
					SELECT id_label
					FROM {db_prefix}pm_labeled_messages
					WHERE id_pm = {int:current_pm}',
					array(
						'current_pm' => $row['id_pm'],
					)
				);

				while($row2 = $smcFunc['db_fetch_assoc']($request2))
				{
					$l_id = $row2['id_label'];
					if (isset($context['labels'][$l_id]))
						$context['message_labels'][$row['id_pm']][$l_id] = array('id' => $l_id, 'name' => $context['labels'][$l_id]['name']);

					// Here we find the first label on a message - for linking to posts in results
					if (!isset($context['first_label'][$row['id_pm']]) && $row['in_inbox'] != 1)
						$context['first_label'][$row['id_pm']] = $l_id;
				}

				$smcFunc['db_free_result']($request2);

				// Is this in the inbox as well?
				if ($row['in_inbox'] == 1)
				{
					$context['message_labels'][$row['id_pm']][-1] = array('id' => -1, 'name' => $context['labels'][-1]['name']);
				}

				$row['labels'] = $row['labels'] == '' ? array() : explode(',', $row['labels']);
			}
		}

		// Prepare the query for the callback!
		$request = $smcFunc['db_query']('', '
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm
			WHERE pm.id_pm IN ({array_int:message_list})
			ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
			LIMIT ' . count($foundMessages),
			array(
				'message_list' => $foundMessages,
			)
		);
		$counter = 0;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If there's no message subject, use the default.
			$row['subject'] = $row['subject'] == '' ? $txt['no_subject'] : $row['subject'];

			// Load this posters context info, if it ain't there then fill in the essentials...
			if (!loadMemberContext($row['id_member_from'], true))
			{
				$memberContext[$row['id_member_from']]['name'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['id'] = 0;
				$memberContext[$row['id_member_from']]['group'] = $txt['guest_title'];
				$memberContext[$row['id_member_from']]['link'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['email'] = '';
				$memberContext[$row['id_member_from']]['is_guest'] = true;
			}

			// Censor anything we don't want to see...
			censorText($row['body']);
			censorText($row['subject']);

			// Parse out any BBC...
			$row['body'] = parse_bbc($row['body'], true, 'pm' . $row['id_pm']);

			$href = $scripturl . '?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . ($context['display_mode'] == 2 && isset($real_pm_ids[$head_pms[$row['id_pm']]]) ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg' . $row['id_pm'];
			$context['personal_messages'][] = array(
				'id' => $row['id_pm'],
				'member' => &$memberContext[$row['id_member_from']],
				'subject' => $row['subject'],
				'body' => $row['body'],
				'time' => timeformat($row['msgtime']),
				'recipients' => &$recipients[$row['id_pm']],
				'labels' => &$context['message_labels'][$row['id_pm']],
				'fully_labeled' => count($context['message_labels'][$row['id_pm']]) == count($context['labels']),
				'is_replied_to' => &$context['message_replied'][$row['id_pm']],
				'href' => $href,
				'link' => '<a href="' . $href . '">' . $row['subject'] . '</a>',
				'counter' => ++$counter,
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// Finish off the context.
	$context['page_title'] = $txt['pm_search_title'];
	$context['sub_template'] = 'search_results';
	$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'search';
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=search',
		'name' => $txt['pm_search_bar_title'],
	);
}

/**
 * Send a new message?
 */
function MessagePost()
{
	global $txt, $sourcedir, $scripturl, $modSettings;
	global $context, $smcFunc, $language, $user_info;

	isAllowedTo('pm_send');

	loadLanguage('PersonalMessage');
	// Just in case it was loaded from somewhere else.
	if (!WIRELESS)
	{
		loadTemplate('PersonalMessage');
		loadJavascriptFile('PersonalMessage.js', array('default_theme' => true, 'defer' => false), 'smf_pms');
		loadJavascriptFile('suggest.js', array('default_theme' => true, 'defer' => false), 'smf_suggest');
		$context['sub_template'] = 'send';
	}

	// Extract out the spam settings - cause it's neat.
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Set the title...
	$context['page_title'] = $txt['send_message'];

	$context['reply'] = isset($_REQUEST['pmsg']) || isset($_REQUEST['quote']);

	// Check whether we've gone over the limit of messages we can send per hour.
	if (!empty($modSettings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && $user_info['mod_cache']['bq'] == '0=1' && $user_info['mod_cache']['gq'] == '0=1')
	{
		// How many messages have they sent this last hour?
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(pr.id_pm) AS post_count
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
			WHERE pm.id_member_from = {int:current_member}
				AND pm.msgtime > {int:msgtime}',
			array(
				'current_member' => $user_info['id'],
				'msgtime' => time() - 3600,
			)
		);
		list ($postCount) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($postCount) && $postCount >= $modSettings['pm_posts_per_hour'])
			fatal_lang_error('pm_too_many_per_hour', true, array($modSettings['pm_posts_per_hour']));
	}

	// Quoting/Replying to a message?
	if (!empty($_REQUEST['pmsg']))
	{
		$pmsg = (int) $_REQUEST['pmsg'];

		// Make sure this is yours.
		if (!isAccessiblePM($pmsg))
			fatal_lang_error('no_access', false);

		// Work out whether this is one you've received?
		$request = $smcFunc['db_query']('', '
			SELECT
				id_pm
			FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_pm' => $pmsg,
			)
		);
		$isReceived = $smcFunc['db_num_rows']($request) != 0;
		$smcFunc['db_free_result']($request);

		// Get the quoted message (and make sure you're allowed to see this quote!).
		$request = $smcFunc['db_query']('', '
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:id_pm_head_empty} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . (!$isReceived ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:id_pm})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}' . (!$isReceived ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_pm_head_empty' => 0,
				'id_pm' => $pmsg,
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('pm_not_yours', false);
		$row_quoted = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Censor the message.
		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);

		// Add 'Re: ' to it....
		if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
		{
			if ($language === $user_info['language'])
				$context['response_prefix'] = $txt['response_prefix'];
			else
			{
				loadLanguage('index', $language, false);
				$context['response_prefix'] = $txt['response_prefix'];
				loadLanguage('index');
			}
			cache_put_data('response_prefix', $context['response_prefix'], 600);
		}
		$form_subject = $row_quoted['subject'];
		if ($context['reply'] && trim($context['response_prefix']) != '' && $smcFunc['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
			$form_subject = $context['response_prefix'] . $form_subject;

		if (isset($_REQUEST['quote']))
		{
			// Remove any nested quotes and <br>...
			$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $row_quoted['body']);
			if (!empty($modSettings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);
			if (empty($row_quoted['id_member']))
				$form_message = '[quote author=&quot;' . $row_quoted['real_name'] . '&quot;]' . "\n" . $form_message . "\n" . '[/quote]';
			else
				$form_message = '[quote author=' . $row_quoted['real_name'] . ' link=action=profile;u=' . $row_quoted['id_member'] . ' date=' . $row_quoted['msgtime'] . ']' . "\n" . $form_message . "\n" . '[/quote]';
		}
		else
			$form_message = '';

		// Do the BBC thang on the message.
		$row_quoted['body'] = parse_bbc($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']);

		// Set up the quoted message array.
		$context['quoted_message'] = array(
			'id' => $row_quoted['id_pm'],
			'pm_head' => $row_quoted['pm_head'],
			'member' => array(
				'name' => $row_quoted['real_name'],
				'username' => $row_quoted['member_name'],
				'id' => $row_quoted['id_member'],
				'href' => !empty($row_quoted['id_member']) ? $scripturl . '?action=profile;u=' . $row_quoted['id_member'] : '',
				'link' => !empty($row_quoted['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
			),
			'subject' => $row_quoted['subject'],
			'time' => timeformat($row_quoted['msgtime']),
			'timestamp' => forum_time(true, $row_quoted['msgtime']),
			'body' => $row_quoted['body']
		);
	}
	else
	{
		$context['quoted_message'] = false;
		$form_subject = '';
		$form_message = '';
	}

	$context['recipients'] = array(
		'to' => array(),
		'bcc' => array(),
	);

	// Sending by ID?  Replying to all?  Fetch the real_name(s).
	if (isset($_REQUEST['u']))
	{
		// If the user is replying to all, get all the other members this was sent to..
		if ($_REQUEST['u'] == 'all' && isset($row_quoted))
		{
			// Firstly, to reply to all we clearly already have $row_quoted - so have the original member from.
			if ($row_quoted['id_member'] != $user_info['id'])
				$context['recipients']['to'][] = array(
					'id' => $row_quoted['id_member'],
					'name' => $smcFunc['htmlspecialchars']($row_quoted['real_name']),
				);

			// Now to get the others.
			$request = $smcFunc['db_query']('', '
				SELECT mem.id_member, mem.real_name
				FROM {db_prefix}pm_recipients AS pmr
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
				WHERE pmr.id_pm = {int:id_pm}
					AND pmr.id_member != {int:current_member}
					AND pmr.bcc = {int:not_bcc}',
				array(
					'current_member' => $user_info['id'],
					'id_pm' => $pmsg,
					'not_bcc' => 0,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$_REQUEST['u'] = explode(',', $_REQUEST['u']);
			foreach ($_REQUEST['u'] as $key => $uID)
				$_REQUEST['u'][$key] = (int) $uID;

			$_REQUEST['u'] = array_unique($_REQUEST['u']);

			$request = $smcFunc['db_query']('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT ' . count($_REQUEST['u']),
				array(
					'member_list' => $_REQUEST['u'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			$smcFunc['db_free_result']($request);
		}

		// Get a literal name list in case the user has JavaScript disabled.
		$names = array();
		foreach ($context['recipients']['to'] as $to)
			$names[] = $to['name'];
		$context['to_value'] = empty($names) ? '' : '&quot;' . implode('&quot;, &quot;', $names) . '&quot;';
	}
	else
		$context['to_value'] = '';

	// Set the defaults...
	$context['subject'] = $form_subject;
	$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);
	$context['post_error'] = array();

	// And build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=send',
		'name' => $txt['new_message']
	);

	$modSettings['disable_wysiwyg'] = !empty($modSettings['disable_wysiwyg']) || empty($modSettings['enableBBC']);

	// Generate a list of drafts that they can load in to the editor
	if (!empty($context['drafts_pm_save']))
	{
		require_once($sourcedir . '/Drafts.php');
		$pm_seed = isset($_REQUEST['pmsg']) ? $_REQUEST['pmsg'] : (isset($_REQUEST['quote']) ? $_REQUEST['quote'] : 0);
		ShowDrafts($user_info['id'], $pm_seed, 1);
	}

	// Needed for the WYSIWYG editor.
	require_once($sourcedir . '/Subs-Editor.php');

	// Now create the editor.
	$editorOptions = array(
		'id' => 'message',
		'value' => $context['message'],
		'height' => '250px',
		'width' => '100%',
		'labels' => array(
			'post_button' => $txt['send_message'],
		),
		'preview_type' => 2,
		'required' => true,
	);
	create_control_richedit($editorOptions);

	// Store the ID for old compatibility.
	$context['post_box_name'] = $editorOptions['id'];

	$context['bcc_value'] = '';

	$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
	if ($context['require_verification'])
	{
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// Register this form and get a sequence number in $context.
	checkSubmitOnce('register');
}

/**
 * This function allows the user to view their PM drafts
 */
function MessageDrafts()
{
	global $sourcedir, $user_info;

	// validate with loadMemberData()
	$memberResult = loadMemberData($user_info['id'], false);
	if (!is_array($memberResult))
		fatal_lang_error('not_a_user', false);
	list ($memID) = $memberResult;

	// drafts is where the functions reside
	require_once($sourcedir . '/Drafts.php');
	showPMDrafts($memID);
}

/**
 * An error in the message...
 *
 * @param $error_types
 * @param $named_recipients
 * @param $recipient_ids
 */
function messagePostError($error_types, $named_recipients, $recipient_ids = array())
{
	global $txt, $context, $scripturl, $modSettings;
	global $smcFunc, $user_info, $sourcedir;

	if (!isset($_REQUEST['xml']))
		$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'send';

	if (!WIRELESS && !isset($_REQUEST['xml']))
	{
		$context['sub_template'] = 'send';
		loadJavascriptFile('PersonalMessage.js', array('default_theme' => true, 'defer' => false), 'smf_pms');
		loadJavascriptFile('suggest.js', array('default_theme' => true, 'defer' => false), 'smf_suggest');
	}
	elseif (isset($_REQUEST['xml']))
		$context['sub_template'] = 'pm';

	$context['page_title'] = $txt['send_message'];

	// Got some known members?
	$context['recipients'] = array(
		'to' => array(),
		'bcc' => array(),
	);
	if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
	{
		$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $allRecipients,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$recipientType = in_array($row['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
			$context['recipients'][$recipientType][] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
			);
		}
		$smcFunc['db_free_result']($request);
	}

	// Set everything up like before....
	$context['subject'] = isset($_REQUEST['subject']) ? $smcFunc['htmlspecialchars']($_REQUEST['subject']) : '';
	$context['message'] = isset($_REQUEST['message']) ? str_replace(array('  '), array('&nbsp; '), $smcFunc['htmlspecialchars']($_REQUEST['message'])) : '';
	$context['reply'] = !empty($_REQUEST['replied_to']);

	if ($context['reply'])
	{
		$_REQUEST['replied_to'] = (int) $_REQUEST['replied_to'];

		$request = $smcFunc['db_query']('', '
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:no_id_pm_head} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:replied_to})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:replied_to}' . ($context['folder'] == 'sent' ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'no_id_pm_head' => 0,
				'replied_to' => $_REQUEST['replied_to'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			if (!isset($_REQUEST['xml']))
				fatal_lang_error('pm_not_yours', false);
			else
				$error_types[] = 'pm_not_yours';
		}
		$row_quoted = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);

		$context['quoted_message'] = array(
			'id' => $row_quoted['id_pm'],
			'pm_head' => $row_quoted['pm_head'],
			'member' => array(
				'name' => $row_quoted['real_name'],
				'username' => $row_quoted['member_name'],
				'id' => $row_quoted['id_member'],
				'href' => !empty($row_quoted['id_member']) ? $scripturl . '?action=profile;u=' . $row_quoted['id_member'] : '',
				'link' => !empty($row_quoted['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
			),
			'subject' => $row_quoted['subject'],
			'time' => timeformat($row_quoted['msgtime']),
			'timestamp' => forum_time(true, $row_quoted['msgtime']),
			'body' => parse_bbc($row_quoted['body'], true, 'pm' . $row_quoted['id_pm']),
		);
	}

	// Build the link tree....
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=send',
		'name' => $txt['new_message']
	);

	// Set each of the errors for the template.
	loadLanguage('Errors');

	$context['error_type'] = 'minor';

	$context['post_error'] = array(
		'messages' => array(),
		// @todo error handling: maybe fatal errors can be error_type => serious
		'error_type' => '',
	);

	foreach ($error_types as $error_type)
	{
		$context['post_error'][$error_type] = true;
		if (isset($txt['error_' . $error_type]))
		{
			if ($error_type == 'long_message')
				$txt['error_' . $error_type] = sprintf($txt['error_' . $error_type], $modSettings['max_messageLength']);
			$context['post_error']['messages'][] = $txt['error_' . $error_type];
		}

		// If it's not a minor error flag it as such.
		if (!in_array($error_type, array('new_reply', 'not_approved', 'new_replies', 'old_topic', 'need_qr_verification', 'no_subject')))
			$context['error_type'] = 'serious';
	}

	// We need to load the editor once more.
	require_once($sourcedir . '/Subs-Editor.php');

	// Create it...
	$editorOptions = array(
		'id' => 'message',
		'value' => $context['message'],
		'width' => '90%',
		'height' => '250px',
		'labels' => array(
			'post_button' => $txt['send_message'],
		),
		'preview_type' => 2,
	);
	create_control_richedit($editorOptions);

	// ... and store the ID again...
	$context['post_box_name'] = $editorOptions['id'];

	// Check whether we need to show the code again.
	$context['require_verification'] = !$user_info['is_admin'] && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'];
	if ($context['require_verification'] && !isset($_REQUEST['xml']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	$context['to_value'] = empty($named_recipients['to']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['to']) . '&quot;';
	$context['bcc_value'] = empty($named_recipients['bcc']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['bcc']) . '&quot;';

	// No check for the previous submission is needed.
	checkSubmitOnce('free');

	// Acquire a new form sequence number.
	checkSubmitOnce('register');
}

/**
 * Send it!
 */
function MessagePost2()
{
	global $txt, $context, $sourcedir;
	global $user_info, $modSettings, $smcFunc;

	isAllowedTo('pm_send');
	require_once($sourcedir . '/Subs-Auth.php');

	// PM Drafts enabled and needed?
	if ($context['drafts_pm_save'] && (isset($_POST['save_draft']) || isset($_POST['id_pm_draft'])))
		require_once($sourcedir . '/Drafts.php');

	loadLanguage('PersonalMessage', '', false);

	// Extract out the spam settings - it saves database space!
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Initialize the errors we're about to make.
	$post_errors = array();

	// Check whether we've gone over the limit of messages we can send per hour - fatal error if fails!
	if (!empty($modSettings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && $user_info['mod_cache']['bq'] == '0=1' && $user_info['mod_cache']['gq'] == '0=1')
	{
		// How many have they sent this last hour?
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(pr.id_pm) AS post_count
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
			WHERE pm.id_member_from = {int:current_member}
				AND pm.msgtime > {int:msgtime}',
			array(
				'current_member' => $user_info['id'],
				'msgtime' => time() - 3600,
			)
		);
		list ($postCount) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($postCount) && $postCount >= $modSettings['pm_posts_per_hour'])
		{
			if (!isset($_REQUEST['xml']))
				fatal_lang_error('pm_too_many_per_hour', true, array($modSettings['pm_posts_per_hour']));
			else
				$post_errors[] = 'pm_too_many_per_hour';
		}
	}

	// If your session timed out, show an error, but do allow to re-submit.
	if (!isset($_REQUEST['xml']) && checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';

	$_REQUEST['subject'] = isset($_REQUEST['subject']) ? trim($_REQUEST['subject']) : '';
	$_REQUEST['to'] = empty($_POST['to']) ? (empty($_GET['to']) ? '' : $_GET['to']) : $_POST['to'];
	$_REQUEST['bcc'] = empty($_POST['bcc']) ? (empty($_GET['bcc']) ? '' : $_GET['bcc']) : $_POST['bcc'];

	// Route the input from the 'u' parameter to the 'to'-list.
	if (!empty($_POST['u']))
		$_POST['recipient_to'] = explode(',', $_POST['u']);

	// Construct the list of recipients.
	$recipientList = array();
	$namedRecipientList = array();
	$namesNotFound = array();
	foreach (array('to', 'bcc') as $recipientType)
	{
		// First, let's see if there's user ID's given.
		$recipientList[$recipientType] = array();
		if (!empty($_POST['recipient_' . $recipientType]) && is_array($_POST['recipient_' . $recipientType]))
		{
			foreach ($_POST['recipient_' . $recipientType] as $recipient)
				$recipientList[$recipientType][] = (int) $recipient;
		}

		// Are there also literal names set?
		if (!empty($_REQUEST[$recipientType]))
		{
			// We're going to take out the "s anyway ;).
			$recipientString = strtr($_REQUEST[$recipientType], array('\\"' => '"'));

			preg_match_all('~"([^"]+)"~', $recipientString, $matches);
			$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

			foreach ($namedRecipientList[$recipientType] as $index => $recipient)
			{
				if (strlen(trim($recipient)) > 0)
					$namedRecipientList[$recipientType][$index] = $smcFunc['htmlspecialchars']($smcFunc['strtolower'](trim($recipient)));
				else
					unset($namedRecipientList[$recipientType][$index]);
			}

			if (!empty($namedRecipientList[$recipientType]))
			{
				$foundMembers = findMembers($namedRecipientList[$recipientType]);

				// Assume all are not found, until proven otherwise.
				$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

				foreach ($foundMembers as $member)
				{
					$testNames = array(
						$smcFunc['strtolower']($member['username']),
						$smcFunc['strtolower']($member['name']),
						$smcFunc['strtolower']($member['email']),
					);

					if (count(array_intersect($testNames, $namedRecipientList[$recipientType])) !== 0)
					{
						$recipientList[$recipientType][] = $member['id'];

						// Get rid of this username, since we found it.
						$namesNotFound[$recipientType] = array_diff($namesNotFound[$recipientType], $testNames);
					}
				}
			}
		}

		// Selected a recipient to be deleted? Remove them now.
		if (!empty($_POST['delete_recipient']))
			$recipientList[$recipientType] = array_diff($recipientList[$recipientType], array((int) $_POST['delete_recipient']));

		// Make sure we don't include the same name twice
		$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
	}

	// Are we changing the recipients some how?
	$is_recipient_change = !empty($_POST['delete_recipient']) || !empty($_POST['to_submit']) || !empty($_POST['bcc_submit']);

	// Check if there's at least one recipient.
	if (empty($recipientList['to']) && empty($recipientList['bcc']))
		$post_errors[] = 'no_to';

	// Make sure that we remove the members who did get it from the screen.
	if (!$is_recipient_change)
	{
		foreach ($recipientList as $recipientType => $dummy)
		{
			if (!empty($namesNotFound[$recipientType]))
			{
				$post_errors[] = 'bad_' . $recipientType;

				// Since we already have a post error, remove the previous one.
				$post_errors = array_diff($post_errors, array('no_to'));

				foreach ($namesNotFound[$recipientType] as $name)
					$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
			}
		}
	}

	// Did they make any mistakes?
	if ($_REQUEST['subject'] == '')
		$post_errors[] = 'no_subject';
	if (!isset($_REQUEST['message']) || $_REQUEST['message'] == '')
		$post_errors[] = 'no_message';
	elseif (!empty($modSettings['max_messageLength']) && $smcFunc['strlen']($_REQUEST['message']) > $modSettings['max_messageLength'])
		$post_errors[] = 'long_message';
	else
	{
		// Preparse the message.
		$message = $_REQUEST['message'];
		preparsecode($message);

		// Make sure there's still some content left without the tags.
		if ($smcFunc['htmltrim'](strip_tags(parse_bbc($smcFunc['htmlspecialchars']($message, ENT_QUOTES), false), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($message, '[html]') === false))
			$post_errors[] = 'no_message';
	}

	// Wrong verification code?
	if (!$user_info['is_admin'] && !isset($_REQUEST['xml']) && !empty($modSettings['pm_posts_verification']) && $user_info['posts'] < $modSettings['pm_posts_verification'])
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['require_verification']))
			$post_errors = array_merge($post_errors, $context['require_verification']);
	}

	// If they did, give a chance to make ammends.
	if (!empty($post_errors) && !$is_recipient_change && !isset($_REQUEST['preview']) && !isset($_REQUEST['xml']))
		return messagePostError($post_errors, $namedRecipientList, $recipientList);

	// Want to take a second glance before you send?
	if (isset($_REQUEST['preview']))
	{
		// Set everything up to be displayed.
		$context['preview_subject'] = $smcFunc['htmlspecialchars']($_REQUEST['subject']);
		$context['preview_message'] = $smcFunc['htmlspecialchars']($_REQUEST['message'], ENT_QUOTES);
		preparsecode($context['preview_message'], true);

		// Parse out the BBC if it is enabled.
		$context['preview_message'] = parse_bbc($context['preview_message']);

		// Censor, as always.
		censorText($context['preview_subject']);
		censorText($context['preview_message']);

		// Set a descriptive title.
		$context['page_title'] = $txt['preview'] . ' - ' . $context['preview_subject'];

		// Pretend they messed up but don't ignore if they really did :P.
		return messagePostError($post_errors, $namedRecipientList, $recipientList);
	}

	// Adding a recipient cause javascript ain't working?
	elseif ($is_recipient_change)
	{
		// Maybe we couldn't find one?
		foreach ($namesNotFound as $recipientType => $names)
		{
			$post_errors[] = 'bad_' . $recipientType;
			foreach ($names as $name)
				$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
		}

		return messagePostError(array(), $namedRecipientList, $recipientList);
	}

	// Want to save this as a draft and think about it some more?
	if ($context['drafts_pm_save'] && isset($_POST['save_draft']))
	{
		SavePMDraft($post_errors, $recipientList);
		return messagePostError($post_errors, $namedRecipientList, $recipientList);
	}

	// Before we send the PM, let's make sure we don't have an abuse of numbers.
	elseif (!empty($modSettings['max_pm_recipients']) && count($recipientList['to']) + count($recipientList['bcc']) > $modSettings['max_pm_recipients'] && !allowedTo(array('moderate_forum', 'send_mail', 'admin_forum')))
	{
		$context['send_log'] = array(
			'sent' => array(),
			'failed' => array(sprintf($txt['pm_too_many_recipients'], $modSettings['max_pm_recipients'])),
		);
		return messagePostError($post_errors, $namedRecipientList, $recipientList);
	}

	// Protect from message spamming.
	spamProtection('pm');

	// Prevent double submission of this form.
	checkSubmitOnce('check');

	// Do the actual sending of the PM.
	if (!empty($recipientList['to']) || !empty($recipientList['bcc']))
		$context['send_log'] = sendpm($recipientList, $_REQUEST['subject'], $_REQUEST['message'], true, null, !empty($_REQUEST['pm_head']) ? (int) $_REQUEST['pm_head'] : 0);
	else
		$context['send_log'] = array(
			'sent' => array(),
			'failed' => array()
		);

	// Mark the message as "replied to".
	if (!empty($context['send_log']['sent']) && !empty($_REQUEST['replied_to']) && isset($_REQUEST['f']) && $_REQUEST['f'] == 'inbox')
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET is_read = is_read | 2
			WHERE id_pm = {int:replied_to}
				AND id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'replied_to' => (int) $_REQUEST['replied_to'],
			)
		);
	}

	// If one or more of the recipient were invalid, go back to the post screen with the failed usernames.
	if (!empty($context['send_log']['failed']))
		return messagePostError($post_errors, $namesNotFound, array(
			'to' => array_intersect($recipientList['to'], $context['send_log']['failed']),
			'bcc' => array_intersect($recipientList['bcc'], $context['send_log']['failed'])
		));

	// Message sent successfully?
	if (!empty($context['send_log']) && empty($context['send_log']['failed']))
	{
		$context['current_label_redirect'] = $context['current_label_redirect'] . ';done=sent';

		// If we had a PM draft for this one, then its time to remove it since it was just sent
		if ($context['drafts_pm_save'] && !empty($_POST['id_pm_draft']))
			DeleteDraft($_POST['id_pm_draft']);
	}

	// Go back to the where they sent from, if possible...
	redirectexit($context['current_label_redirect']);
}

/**
 * This function lists all buddies for wireless protocols.
 */
function WirelessAddBuddy()
{
	global $scripturl, $txt, $user_info, $context, $smcFunc;

	isAllowedTo('pm_send');
	$context['page_title'] = $txt['wireless_pm_add_buddy'];

	$current_buddies = empty($_REQUEST['u']) ? array() : explode(',', $_REQUEST['u']);
	foreach ($current_buddies as $key => $buddy)
		$current_buddies[$key] = (int) $buddy;

	$base_url = $scripturl . '?action=pm;sa=send;u=' . (empty($current_buddies) ? '' : implode(',', $current_buddies) . ',');
	$context['pm_href'] = $scripturl . '?action=pm;sa=send' . (empty($current_buddies) ? '' : ';u=' . implode(',', $current_buddies));

	$context['buddies'] = array();
	if (!empty($user_info['buddies']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:buddy_list})
			ORDER BY real_name
			LIMIT ' . count($user_info['buddies']),
			array(
				'buddy_list' => $user_info['buddies'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['buddies'][] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'selected' => in_array($row['id_member'], $current_buddies),
				'add_href' => $base_url . $row['id_member'],
			);
		$smcFunc['db_free_result']($request);
	}
}

/**
 * This function performs all additional stuff...
 */
function MessageActionsApply()
{
	global $context, $user_info, $options, $smcFunc;

	checkSession('request');

	if (isset($_REQUEST['del_selected']))
		$_REQUEST['pm_action'] = 'delete';

	if (isset($_REQUEST['pm_action']) && $_REQUEST['pm_action'] != '' && !empty($_REQUEST['pms']) && is_array($_REQUEST['pms']))
	{
		foreach ($_REQUEST['pms'] as $pm)
			$_REQUEST['pm_actions'][(int) $pm] = $_REQUEST['pm_action'];
	}

	if (empty($_REQUEST['pm_actions']))
		redirectexit($context['current_label_redirect']);

	// If we are in conversation, we may need to apply this to every message in the conversation.
	if ($context['display_mode'] == 2 && isset($_REQUEST['conversation']))
	{
		$id_pms = array();
		foreach ($_REQUEST['pm_actions'] as $pm => $dummy)
			$id_pms[] = (int) $pm;

		$request = $smcFunc['db_query']('', '
			SELECT id_pm_head, id_pm
			FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:id_pms})',
			array(
				'id_pms' => $id_pms,
			)
		);
		$pm_heads = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$pm_heads[$row['id_pm_head']] = $row['id_pm'];
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_pm, id_pm_head
			FROM {db_prefix}personal_messages
			WHERE id_pm_head IN ({array_int:pm_heads})',
			array(
				'pm_heads' => array_keys($pm_heads),
			)
		);
		// Copy the action from the single to PM to the others.
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (isset($pm_heads[$row['id_pm_head']]) && isset($_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]]))
				$_REQUEST['pm_actions'][$row['id_pm']] = $_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]];
		}
		$smcFunc['db_free_result']($request);
	}

	$to_delete = array();
	$to_label = array();
	$to_unlabel = array();
	$label_type = array();
	$labels = array();
	foreach ($_REQUEST['pm_actions'] as $pm => $action)
	{
		if ($action === 'delete')
			$to_delete[] = (int) $pm;
		else
		{
			if (substr($action, 0, 4) == 'add_')
			{
				$type = 'add';
				$action = substr($action, 4);
			}
			elseif (substr($action, 0, 4) == 'rem_')
			{
				$type = 'rem';
				$action = substr($action, 4);
			}
			else
				$type = 'unk';

			if ($action == '-1' || (int) $action > 0)
			{
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type;
			}
		}
	}

	// Deleting, it looks like?
	if (!empty($to_delete))
		deleteMessages($to_delete, $context['display_mode'] == 2 ? null : $context['folder']);

	// Are we labeling anything?
	if (!empty($to_label) && $context['folder'] == 'inbox')
	{
		$updateErrors = 0;

		// Are we dealing with conversation view? If so, get all the messages in each conversation
		if ($context['display_mode'] == 2)
		{
			$get_pms = $smcFunc['db_query']('', '
				SELECT pm.id_pm_head, pm.id_pm
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head IN ({array_int:head_pms})
					AND pm.id_pm NOT IN ({array_int:head_pms})
					AND pmr.id_member = {int:current_member}',
				array(
					'head_pms' => array_keys($to_label),
					'current_member' => $user_info['id'],
				)
			);

			while ($other_pms = $smcFunc['db_query']($get_pms))
			{
				$to_label[$other_pms['id_pm']] = $to_label[$other_pms['id_pm_head']];
			}

			$smcFunc['db_free_result']($get_pms);
		}

		// Get information about each message...
		$request = $smcFunc['db_query']('', '
			SELECT id_pm, in_inbox
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:current_member}
				AND id_pm IN ({array_int:to_label})
			LIMIT ' . count($to_label),
			array(
				'current_member' => $user_info['id'],
				'to_label' => array_keys($to_label),
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Get the labels as well, but only if we're not dealing with the inbox
			if ($to_label[$row['id_pm']] != '-1')
			{
				// The JOIN here ensures we only get labels that this user has applied to this PM
				$request2 = $smcFunc['db_query']('', '
					SELECT l.id_label, pml.id_pm
					FROM {db_prefix}pm_labels AS l
						INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
					WHERE l.id_member = {int:current_member}
						AND pml.id_pm = {int:current_pm}',
					array(
						'current_member' => $user_info['id'],
						'current_pm' => $row['id_pm'],
					)
				);

				while ($row2 = $smcFunc['db_fetch_assoc']($request2))
				{
					$labels[$row2['id_label']] = $row2['id_label'];
				}

				$smcFunc['db_free_result']($request2);
			}
			elseif ($type == 'rem')
			{
				// If we're removing from the inbox, see if we have at least one other label.
				// This query is faster than the one above
				$request2 = $smcFunc['db_query']('', '
					SELECT COUNT(l.id_label)
					FROM {db_prefix}pm_labels AS l
						INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
					WHERE l.id_member = {int:current_member}
						AND pml.id_pm = {int:current_pm}',
					array(
						'current_member' => $user_info['id'],
						'current_pm' => $row['id_pm'],
					)
				);

				// How many labels do you have?
				list ($num_labels) = $smcFunc['db_fetch_assoc']($request2);

				if ($num_labels > 0);
					$context['can_remove_inbox'] = true;

				$smcFunc['db_free_result']($request2);
			}

			// Use this to determine what to do later on...
			$original_labels = $labels;

			// Ignore inbox for now - we'll deal with it later
			if ($to_label[$row['id_pm']] != '-1')
			{
				// If this label is in the list and we're not adding it, remove it
				if (array_key_exists($to_label[$row['id_pm']], $labels) && $type !== 'add')
					unset($labels[$to_label[$row['id_pm']]]);
				else if ($type !== 'rem')
					$labels[$to_label[$row['id_pm']]] = $to_label[$row['id_pm']];
			}

			// Removing all labels or just removing the inbox label
			if ($type == 'rem' && empty($labels))
				$in_inbox = (empty($context['can_remove_inbox']) ? 1 : 0);
			// Adding new labels, but removing inbox and applying new ones
			elseif ($type == 'add' && !empty($options['pm_remove_inbox_label']) && !empty($labels))
				$in_inbox = 0;
			// Just adding it to the inbox
			else
				$in_inbox = 1;

			// Are we adding it to or removing it from the inbox?
			if ($in_inbox != $row['in_inbox'])
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}pm_recipients
					SET in_inbox = {int:in_inbox}
					WHERE id_pm = {int:id_pm}
						AND id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'id_pm' => $row['id_pm'],
						'in_inbox' => $in_inbox,
					)
				);
			}

			// Which labels do we not want now?
			$labels_to_remove = array_diff($original_labels, $labels);

			// Don't apply it if it's already applied
			$labels_to_apply = array_diff($labels, $original_labels);

			// Remove labels
			if (!empty($labels_to_remove))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}pm_labeled_messages
					WHERE id_pm = {int:current_pm}
						AND id_label IN ({array_int:labels_to_remove})',
					array(
						'current_pm' => $row['id_pm'],
						'labels_to_remove' => $labels_to_remove,
					)
				);
			}

			// Add new ones
			if (!empty($labels_to_apply))
			{
				$inserts = array();
				foreach ($labels_to_apply as $label)
					$inserts[] = array($row['id_pm'], $label);

				$smcFunc['db_insert']('',
					'{db_prefix}pm_labeled_messages',
					array('id_pm' => 'int', 'id_label' => 'int'),
					$inserts,
					array()
				);
			}
		}
		$smcFunc['db_free_result']($request);
	}

	// Back to the folder.
	$_SESSION['pm_selected'] = array_keys($to_label);
	redirectexit($context['current_label_redirect'] . (count($to_label) == 1 ? '#msg' . $_SESSION['pm_selected'][0] : ''), count($to_label) == 1 && isBrowser('ie'));
}

/**
 * Are you sure you want to PERMANENTLY (mostly) delete ALL your messages?
 */
function MessageKillAllQuery()
{
	global $txt, $context;

	// Only have to set up the template....
	$context['sub_template'] = 'ask_delete';
	$context['page_title'] = $txt['delete_all'];
	$context['delete_all'] = $_REQUEST['f'] == 'all';

	// And set the folder name...
	$txt['delete_all'] = str_replace('PMBOX', $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'], $txt['delete_all']);
}

/**
 * Delete ALL the messages!
 */
function MessageKillAll()
{
	global $context;

	checkSession('get');

	// If all then delete all messages the user has.
	if ($_REQUEST['f'] == 'all')
		deleteMessages(null, null);
	// Otherwise just the selected folder.
	else
		deleteMessages(null, $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent');

	// Done... all gone.
	redirectexit($context['current_label_redirect']);
}

/**
 * This function allows the user to delete all messages older than so many days.
 */
function MessagePrune()
{
	global $txt, $context, $user_info, $scripturl, $smcFunc;

	// Actually delete the messages.
	if (isset($_REQUEST['age']))
	{
		checkSession();

		// Calculate the time to delete before.
		$deleteTime = max(0, time() - (86400 * (int) $_REQUEST['age']));

		// Array to store the IDs in.
		$toDelete = array();

		// Select all the messages they have sent older than $deleteTime.
		$request = $smcFunc['db_query']('', '
			SELECT id_pm
			FROM {db_prefix}personal_messages
			WHERE deleted_by_sender = {int:not_deleted}
				AND id_member_from = {int:current_member}
				AND msgtime < {int:msgtime}',
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$toDelete[] = $row[0];
		$smcFunc['db_free_result']($request);

		// Select all messages in their inbox older than $deleteTime.
		$request = $smcFunc['db_query']('', '
			SELECT pmr.id_pm
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			WHERE pmr.deleted = {int:not_deleted}
				AND pmr.id_member = {int:current_member}
				AND pm.msgtime < {int:msgtime}',
			array(
				'current_member' => $user_info['id'],
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$toDelete[] = $row['id_pm'];
		$smcFunc['db_free_result']($request);

		// Delete the actual messages.
		deleteMessages($toDelete);

		// Go back to their inbox.
		redirectexit($context['current_label_redirect']);
	}

	// Build the link tree elements.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=prune',
		'name' => $txt['pm_prune']
	);

	$context['sub_template'] = 'prune';
	$context['page_title'] = $txt['pm_prune'];
}

/**
 * Delete the specified personal messages.
 *
 * @param array $personal_messages array of pm ids
 * @param string $folder = null
 * @param int $owner = null
 */
function deleteMessages($personal_messages, $folder = null, $owner = null)
{
	global $user_info, $smcFunc;

	if ($owner === null)
		$owner = array($user_info['id']);
	elseif (empty($owner))
		return;
	elseif (!is_array($owner))
		$owner = array($owner);

	if ($personal_messages !== null)
	{
		if (empty($personal_messages) || !is_array($personal_messages))
			return;

		foreach ($personal_messages as $index => $delete_id)
			$personal_messages[$index] = (int) $delete_id;

		$where = '
				AND id_pm IN ({array_int:pm_list})';
	}
	else
		$where = '';

	if ($folder == 'sent' || $folder === null)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}personal_messages
			SET deleted_by_sender = {int:is_deleted}
			WHERE id_member_from IN ({array_int:member_list})
				AND deleted_by_sender = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
	}
	if ($folder != 'sent' || $folder === null)
	{
		// Calculate the number of messages each member's gonna lose...
		$request = $smcFunc['db_query']('', '
			SELECT id_member, COUNT(*) AS num_deleted_messages, CASE WHEN is_read & 1 >= 1 THEN 1 ELSE 0 END AS is_read
			FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where . '
			GROUP BY id_member, is_read',
			array(
				'member_list' => $owner,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
		// ...And update the statistics accordingly - now including unread messages!.
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['is_read'])
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages']));
			else
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages'], 'unread_messages' => $where == '' ? 0 : 'unread_messages - ' . $row['num_deleted_messages']));

			// If this is the current member we need to make their message count correct.
			if ($user_info['id'] == $row['id_member'])
			{
				$user_info['messages'] -= $row['num_deleted_messages'];
				if (!($row['is_read']))
					$user_info['unread_messages'] -= $row['num_deleted_messages'];
			}
		}
		$smcFunc['db_free_result']($request);

		// Do the actual deletion.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET deleted = {int:is_deleted}
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);

		$labels = array();

		// Get any labels that the owner may have applied to this PM
		// The join is here to ensure we only get labels applied by the specified member(s)
		$get_labels = $smcFunc['db_query']('', '
			SELECT pml.id_label
			FROM {db_prefix}pm_labels AS l
  				INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
			WHERE l.id_member IN ({array_int:member_list})' . $where,
			array(
				'member_list' => $owner,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($get_labels))
		{
			$labels[] = $row['id_label'];
		}

		$smcFunc['db_free_result']($get_labels);

		if (!empty($labels))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}pm_labeled_messages
				WHERE label_id IN ({array_int:labels})' . $where,
				array(
					'labels' => $labels,
					'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
				)
			);
		}
	}

	// If sender and recipients all have deleted their message, it can be removed.
	$request = $smcFunc['db_query']('', '
		SELECT pm.id_pm AS sender, pmr.id_pm
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.deleted = {int:not_deleted})
		WHERE pm.deleted_by_sender = {int:is_deleted}
			' . str_replace('id_pm', 'pm.id_pm', $where) . '
		GROUP BY sender, pmr.id_pm
		HAVING pmr.id_pm IS null',
		array(
			'not_deleted' => 0,
			'is_deleted' => 1,
			'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
		)
	);
	$remove_pms = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$remove_pms[] = $row['sender'];
	$smcFunc['db_free_result']($request);

	if (!empty($remove_pms))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}pm_labeled_messages
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);
	}

	// Any cached numbers may be wrong now.
	cache_put_data('labelCounts:' . $user_info['id'], null, 720);
}

/**
 * Mark the specified personal messages read.
 *
 * @param array $personal_messages = null, array of pm ids
 * @param string $label = null, if label is set, only marks messages with that label
 * @param int $owner = null, if owner is set, marks messages owned by that member id
 */
function markMessages($personal_messages = null, $label = null, $owner = null)
{
	global $user_info, $context, $smcFunc;

	if ($owner === null)
		$owner = $user_info['id'];

	$in_inbox = '';

	// Marking all messages with a specific label as read?
	// If we know which PMs we're marking read, then we don't need label info
	if ($personal_messages === null && $label !== null && $label != '-1')
	{
		$personal_messages = array();
		$get_messages = $smcFunc['db_query']('', '
			SELECT id_pm
			FROM {db_prefix}pm_labeled_messages
			WHERE id_label = {int:current_label}',
			array(
				'current_label' => $label,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($get_messages))
		{
			$personal_messages[] = $row['id_pm'];
		}

		$smcFunc['db_free_result']($get_messages);
	}
	elseif ($label = '-1')
	{
		// Marking all PMs in your inbox read
		$in_inbox = '
			AND in_inbox = {int:in_inbox}';
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 1
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)' . ($personal_messages !== null ? '
			AND id_pm IN ({array_int:personal_messages})' : '') . $in_inbox,
		array(
			'personal_messages' => $personal_messages,
			'id_member' => $owner,
			'in_inbox' => 1,
		)
	);

	// If something wasn't marked as read, get the number of unread messages remaining.
	if ($smcFunc['db_affected_rows']() > 0)
	{
		if ($owner == $user_info['id'])
		{
			foreach ($context['labels'] as $label)
				$context['labels'][(int) $label['id']]['unread_messages'] = 0;
		}

		$result = $smcFunc['db_query']('', '
			SELECT id_pm, in_inbox, COUNT(*) AS num
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:id_member}
				AND NOT (is_read & 1 >= 1)
				AND deleted = {int:is_not_deleted}',
			array(
				'id_member' => $owner,
				'is_not_deleted' => 0,
			)
		);
		$total_unread = 0;
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$total_unread += $row['num'];

			if ($owner != $user_info['id'] || empty($row['id_pm']))
				continue;

			$this_labels = array();

			// Get all the labels
			$result2 = $smcFunc['db_query']('', '
				SELECT pml.id_label
				FROM {db_prefix}pm_labels AS l
					INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_label = l.id_label)
				WHERE l.id_member = {int:id_member}
					AND pml.id_pm = {int:current_pm}',
				array(
					'id_member' => $owner,
					'current_pm' => $row['id_pm'],
				)
			);

			while ($row2 = $smcFunc['db_fetch_assoc']($result2))
			{
				$this_labels[] = $row2['id_label'];
			}

			$smcFunc['db_free_result']($result2);

			foreach ($this_labels as $this_label)
				$context['labels'][$this_label]['unread_messages'] += $row['num'];

			if ($row['in_inbox'] == 1)
				$context['labels'][-1]['unread_messages'] += $row['num'];
		}
		$smcFunc['db_free_result']($result);

		// Need to store all this.
		cache_put_data('labelCounts:' . $owner, $context['labels'], 720);
		updateMemberData($owner, array('unread_messages' => $total_unread));

		// If it was for the current member, reflect this in the $user_info array too.
		if ($owner == $user_info['id'])
			$user_info['unread_messages'] = $total_unread;
	}
}

/**
 * This function handles adding, deleting and editing labels on messages.
 */
function ManageLabels()
{
	global $txt, $context, $user_info, $scripturl, $smcFunc;

	// Build the link tree elements...
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=manlabels',
		'name' => $txt['pm_manage_labels']
	);

	$context['page_title'] = $txt['pm_manage_labels'];
	$context['sub_template'] = 'labels';

	$the_labels = array();
	$labels_to_add = array();
	$labels_to_remove = array();
	$label_updates = array();

	// Add all existing labels to the array to save, slashing them as necessary...
	foreach ($context['labels'] as $label)
	{
		if ($label['id'] != -1)
			$the_labels[$label['id']] = $label['name'];
	}

	if (isset($_POST[$context['session_var']]))
	{
		checkSession();

		// This will be for updating messages.
		$message_changes = array();
		$rule_changes = array();

		// Will most likely need this.
		LoadRules();

		// Adding a new label?
		if (isset($_POST['add']))
		{
			$_POST['label'] = strtr($smcFunc['htmlspecialchars'](trim($_POST['label'])), array(',' => '&#044;'));

			if ($smcFunc['strlen']($_POST['label']) > 30)
				$_POST['label'] = $smcFunc['substr']($_POST['label'], 0, 30);
			if ($_POST['label'] != '')
			{
				$the_labels[] = $_POST['label'];
				$labels_to_add[] = $_POST['label'];
			}
		}
		// Deleting an existing label?
		elseif (isset($_POST['delete'], $_POST['delete_label']))
		{
			foreach ($_POST['delete_label'] AS $label => $dummy)
			{
				unset($the_labels[$label]);
				$labels_to_remove[] = $label;
			}
		}
		// The hardest one to deal with... changes.
		elseif (isset($_POST['save']) && !empty($_POST['label_name']))
		{
			foreach ($the_labels as $id => $name)
			{
				if ($id == -1)
					continue;
				elseif (isset($_POST['label_name'][$id]))
				{
					$_POST['label_name'][$id] = trim(strtr($smcFunc['htmlspecialchars']($_POST['label_name'][$id]), array(',' => '&#044;')));

					if ($smcFunc['strlen']($_POST['label_name'][$id]) > 30)
						$_POST['label_name'][$id] = $smcFunc['substr']($_POST['label_name'][$id], 0, 30);
					if ($_POST['label_name'][$id] != '')
					{
						// Changing the name of this label?
						if ($the_labels[$id] != $_POST['label_name'][$id])
							$label_updates[$id] = $_POST['label_name'][$id];

						$the_labels[(int) $id] = $_POST['label_name'][$id];

					}
					else
					{
						unset($the_labels[(int) $id]);
						$labels_to_remove[] = $id;
						$message_changes[(int) $id] = true;
					}
				}
			}
		}

		// Save any new labels
		if (!empty($labels_to_add))
		{
			$inserts = array();
			foreach ($labels_to_add AS $label)
				$inserts[] = array($user_info['id'], $label);

			$smcFunc['db_insert']('', '{db_prefix}pm_labels', array('id_member' => 'int', 'name' => 'string-30'), $inserts, array());
		}

		// Update existing labels as needed
		if (!empty($label_upates))
		{
			foreach ($label_updates AS $id => $name)
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}labels
					SET name = {string:name}
					WHERE id_label = {int:id_label}',
					array(
						'name' => $name,
						'id' => $id
					)
				);
			}
		}

		// Now the fun part... Deleting labels.
		if (!empty($labels_to_remove))
		{
			// First delete the labels
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}pm_labels
				WHERE id_label IN ({array_int:labels_to_delete})',
				array(
					'labels_to_delete' => $labels_to_remove,
				)
			);

			// Now remove the now-deleted labels from any PMs...
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}pm_labeled_messages
				WHERE id_label IN ({array_int:labels_to_delete})',
				array(
					'labels_to_delete' => $labels_to_remove,
				)
			);

			// Get any PMs with no labels which aren't in the inbox
			$get_stranded_pms = $smcFunc['db_query']('', '
				SELECT pmr.id_pm
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)
				WHERE pml.id_label IS NULL
					AND pmr.in_inbox = {int:not_in_inbox}
					AND pmr.deleted = {int:not_deleted}
					AND pmr.id_member = {int:current_member}',
				array(
					'not_in_inbox' => 0,
					'not_deleted' => 0,
					'current_member' => $user_info['id'],
				)
			);

			$stranded_messages = array();
			while ($row = $smcFunc['db_fetch_assoc']($get_stranded_pms))
			{
				$stranded_messages[] = $row['id_pm'];
			}

			$smcFunc['db_free_result']($get_stranded_pms);

			// Move these back to the inbox if necessary
			if (!empty($stranded_messages))
			{
				// We now have more messages in the inbox
				$context['labels'][-1]['messages'] += count($stranded_messages);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}pm_recipients
					SET in_inbox = {int:in_inbox}
					WHERE id_pm IN ({array_int:stranded_messages})
						AND id_member = {int:current_member}',
					array(
						'stranded_messages' => $stranded_messages,
						'in_inbox' => 1,
					)
				);
			}

			// Now do the same the rules - check through each rule.
			foreach ($context['rules'] as $k => $rule)
			{
				// Each action...
				foreach ($rule['actions'] as $k2 => $action)
				{
					if ($action['t'] != 'lab' || !in_array($action['v'], $labels_to_remove))
						continue;

					$rule_changes[] = $rule['id'];

					// Can't apply this label anymore if it doesn't exist
					unset($context['rules'][$k]['actions'][$k2]);
				}
			}
		}

		// If we have rules to change do so now.
		if (!empty($rule_changes))
		{
			$rule_changes = array_unique($rule_changes);
			// Update/delete as appropriate.
			foreach ($rule_changes as $k => $id)
				if (!empty($context['rules'][$id]['actions']))
				{
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}pm_rules
						SET actions = {string:actions}
						WHERE id_rule = {int:id_rule}
							AND id_member = {int:current_member}',
						array(
							'current_member' => $user_info['id'],
							'id_rule' => $id,
							'actions' => serialize($context['rules'][$id]['actions']),
						)
					);
					unset($rule_changes[$k]);
				}

			// Anything left here means it's lost all actions...
			if (!empty($rule_changes))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}pm_rules
					WHERE id_rule IN ({array_int:rule_list})
							AND id_member = {int:current_member}',
					array(
						'current_member' => $user_info['id'],
						'rule_list' => $rule_changes,
					)
				);
		}

		// Make sure we're not caching this!
		cache_put_data('labelCounts:' . $user_info['id'], null, 720);

		// To make the changes appear right away, redirect.
		redirectexit('action=pm;sa=manlabels');
	}
}

/**
 * Allows to edit Personal Message Settings.
 *
 * @uses Profile.php
 * @uses Profile-Modify.php
 * @uses Profile template.
 * @uses Profile language file.
 */
function MessageSettings()
{
	global $txt, $user_info, $context, $sourcedir;
	global $scripturl, $profile_vars, $cur_profile, $user_profile;

	// Need this for the display.
	require_once($sourcedir . '/Profile.php');
	require_once($sourcedir . '/Profile-Modify.php');

	// We want them to submit back to here.
	$context['profile_custom_submit_url'] = $scripturl . '?action=pm;sa=settings;save';

	loadMemberData($user_info['id'], false, 'profile');
	$cur_profile = $user_profile[$user_info['id']];

	loadLanguage('Profile');
	loadTemplate('Profile');

	// Since this is internally handled with the profile code because that's how it was done ages ago
	// we have to set everything up for handling this...
	$context['page_title'] = $txt['pm_settings'];
	$context['user']['is_owner'] = true;
	$context['id_member'] = $user_info['id'];
	$context['require_password'] = false;
	$context['menu_item_selected'] = 'settings';
	$context['submit_button_text'] = $txt['pm_settings'];
	$context['profile_header_text'] = $txt['personal_messages'];
	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['pm_settings_desc'];

	loadThemeOptions($user_info['id']);
	loadCustomFields($user_info['id'], 'pmprefs');

	// Add our position to the linktree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=settings',
		'name' => $txt['pm_settings']
	);

	// Are they saving?
	if (isset($_REQUEST['save']))
	{
		checkSession();

		// Mimic what profile would do.
		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);

		// Save the fields.
		saveProfileFields();

		if (!empty($profile_vars))
			updateMemberData($user_info['id'], $profile_vars);
	}

	setupProfileContext(
		array(
			'pm_prefs',
		)
	);
}

/**
 * Allows the user to report a personal message to an administrator.
 *
 * - In the first instance requires that the ID of the message to report is passed through $_GET.
 * - It allows the user to report to either a particular administrator - or the whole admin team.
 * - It will forward on a copy of the original message without allowing the reporter to make changes.
 *
 * @uses report_message sub-template.
 */
function ReportMessage()
{
	global $txt, $context, $scripturl;
	global $user_info, $language, $modSettings, $smcFunc;

	// Check that this feature is even enabled!
	if (empty($modSettings['enableReportPM']) || empty($_REQUEST['pmsg']))
		fatal_lang_error('no_access', false);

	$pmsg = (int) $_REQUEST['pmsg'];

	if (!isAccessiblePM($pmsg, 'inbox'))
		fatal_lang_error('no_access', false);

	$context['pm_id'] = $pmsg;
	$context['page_title'] = $txt['pm_report_title'];

	// If we're here, just send the user to the template, with a few useful context bits.
	if (!isset($_POST['report']))
	{
		$context['sub_template'] = 'report_message';

		// @todo I don't like being able to pick who to send it to.  Favoritism, etc. sucks.
		// Now, get all the administrators.
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
			ORDER BY real_name',
			array(
				'admin_group' => 1,
			)
		);
		$context['admins'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['admins'][$row['id_member']] = $row['real_name'];
		$smcFunc['db_free_result']($request);

		// How many admins in total?
		$context['admin_count'] = count($context['admins']);
	}
	// Otherwise, let's get down to the sending stuff.
	else
	{
		// Check the session before proceeding any further!
		checkSession();

		// First, pull out the message contents, and verify it actually went to them!
		$request = $smcFunc['db_query']('', '
			SELECT pm.subject, pm.body, pm.msgtime, pm.id_member_from, IFNULL(m.real_name, pm.from_name) AS sender_name
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_pm' => $context['pm_id'],
				'not_deleted' => 0,
			)
		);
		// Can only be a hacker here!
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_access', false);
		list ($subject, $body, $time, $memberFromID, $memberFromName) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Remove the line breaks...
		$body = preg_replace('~<br ?/?' . '>~i', "\n", $body);

		// Get any other recipients of the email.
		$request = $smcFunc['db_query']('', '
			SELECT mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm = {int:id_pm}
				AND pmr.id_member != {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'id_pm' => $context['pm_id'],
			)
		);
		$recipients = array();
		$hidden_recipients = 0;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// If it's hidden still don't reveal their names - privacy after all ;)
			if ($row['bcc'])
				$hidden_recipients++;
			else
				$recipients[] = '[url=' . $scripturl . '?action=profile;u=' . $row['id_member_to'] . ']' . $row['to_name'] . '[/url]';
		}
		$smcFunc['db_free_result']($request);

		if ($hidden_recipients)
			$recipients[] = sprintf($txt['pm_report_pm_hidden'], $hidden_recipients);

		// Now let's get out and loop through the admins.
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, lngfile
			FROM {db_prefix}members
			WHERE (id_group = {int:admin_id} OR FIND_IN_SET({int:admin_id}, additional_groups) != 0)
				' . (empty($_POST['id_admin']) ? '' : 'AND id_member = {int:specific_admin}') . '
			ORDER BY lngfile',
			array(
				'admin_id' => 1,
				'specific_admin' => isset($_POST['id_admin']) ? (int) $_POST['id_admin'] : 0,
			)
		);

		// Maybe we shouldn't advertise this?
		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_access', false);

		$memberFromName = un_htmlspecialchars($memberFromName);

		// Prepare the message storage array.
		$messagesToSend = array();
		// Loop through each admin, and add them to the right language pile...
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Need to send in the correct language!
			$cur_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

			if (!isset($messagesToSend[$cur_language]))
			{
				loadLanguage('PersonalMessage', $cur_language, false);

				// Make the body.
				$report_body = str_replace(array('{REPORTER}', '{SENDER}'), array(un_htmlspecialchars($user_info['name']), $memberFromName), $txt['pm_report_pm_user_sent']);
				$report_body .= "\n" . '[b]' . $_POST['reason'] . '[/b]' . "\n\n";
				if (!empty($recipients))
					$report_body .= $txt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
				$report_body .= $txt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($memberFromID) ? '&quot;' . $memberFromName . '&quot;' : $memberFromName . ' link=action=profile;u=' . $memberFromID . ' date=' . $time) . ']' . "\n" . un_htmlspecialchars($body) . '[/quote]';

				// Plonk it in the array ;)
				$messagesToSend[$cur_language] = array(
					'subject' => ($smcFunc['strpos']($subject, $txt['pm_report_pm_subject']) === false ? $txt['pm_report_pm_subject'] : '') . un_htmlspecialchars($subject),
					'body' => $report_body,
					'recipients' => array(
						'to' => array(),
						'bcc' => array()
					),
				);
			}

			// Add them to the list.
			$messagesToSend[$cur_language]['recipients']['to'][$row['id_member']] = $row['id_member'];
		}
		$smcFunc['db_free_result']($request);

		// Send a different email for each language.
		foreach ($messagesToSend as $lang => $message)
			sendpm($message['recipients'], $message['subject'], $message['body']);

		// Give the user their own language back!
		if (!empty($modSettings['userLanguage']))
			loadLanguage('PersonalMessage', '', false);

		// Leave them with a template.
		$context['sub_template'] = 'report_message_complete';
	}
}

/**
 * List all rules, and allow adding/entering etc...
 */
function ManageRules()
{
	global $txt, $context, $user_info, $scripturl, $smcFunc;

	// The link tree - gotta have this :o
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=pm;sa=manrules',
		'name' => $txt['pm_manage_rules']
	);

	$context['page_title'] = $txt['pm_manage_rules'];
	$context['sub_template'] = 'rules';

	// Load them... load them!!
	LoadRules();

	// Likely to need all the groups!
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, IFNULL(gm.id_member, 0) AS can_moderate, mg.hidden
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:moderator_group}
			AND mg.hidden = {int:not_hidden}
		ORDER BY mg.group_name',
		array(
			'current_member' => $user_info['id'],
			'min_posts' => -1,
			'moderator_group' => 3,
			'not_hidden' => 0,
		)
	);
	$context['groups'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Hide hidden groups!
		if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
			continue;

		$context['groups'][$row['id_group']] = $row['group_name'];
	}
	$smcFunc['db_free_result']($request);

	// Applying all rules?
	if (isset($_GET['apply']))
	{
		checkSession('get');

		ApplyRules(true);
		redirectexit('action=pm;sa=manrules');
	}
	// Editing a specific one?
	if (isset($_GET['add']))
	{
		$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']])? (int) $_GET['rid'] : 0;
		$context['sub_template'] = 'add_rule';

		// Current rule information...
		if ($context['rid'])
		{
			$context['rule'] = $context['rules'][$context['rid']];
			$members = array();
			// Need to get member names!
			foreach ($context['rule']['criteria'] as $k => $criteria)
				if ($criteria['t'] == 'mid' && !empty($criteria['v']))
					$members[(int) $criteria['v']] = $k;

			if (!empty($members))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member, member_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:member_list})',
					array(
						'member_list' => array_keys($members),
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
				$smcFunc['db_free_result']($request);
			}
		}
		else
			$context['rule'] = array(
				'id' => '',
				'name' => '',
				'criteria' => array(),
				'actions' => array(),
				'logic' => 'and',
			);
	}
	// Saving?
	elseif (isset($_GET['save']))
	{
		checkSession();
		$context['rid'] = isset($_GET['rid']) && isset($context['rules'][$_GET['rid']])? (int) $_GET['rid'] : 0;

		// Name is easy!
		$ruleName = $smcFunc['htmlspecialchars'](trim($_POST['rule_name']));
		if (empty($ruleName))
			fatal_lang_error('pm_rule_no_name', false);

		// Sanity check...
		if (empty($_POST['ruletype']) || empty($_POST['acttype']))
			fatal_lang_error('pm_rule_no_criteria', false);

		// Let's do the criteria first - it's also hardest!
		$criteria = array();
		foreach ($_POST['ruletype'] as $ind => $type)
		{
			// Check everything is here...
			if ($type == 'gid' && (!isset($_POST['ruledefgroup'][$ind]) || !isset($context['groups'][$_POST['ruledefgroup'][$ind]])))
				continue;
			elseif ($type != 'bud' && !isset($_POST['ruledef'][$ind]))
				continue;

			// Members need to be found.
			if ($type == 'mid')
			{
				$name = trim($_POST['ruledef'][$ind]);
				$request = $smcFunc['db_query']('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE real_name = {string:member_name}
						OR member_name = {string:member_name}',
					array(
						'member_name' => $name,
					)
				);
				if ($smcFunc['db_num_rows']($request) == 0)
				{
					loadLanguage('Errors');
					fatal_lang_error('invalid_username', false);
				}
				list ($memID) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				$criteria[] = array('t' => 'mid', 'v' => $memID);
			}
			elseif ($type == 'bud')
				$criteria[] = array('t' => 'bud', 'v' => 1);
			elseif ($type == 'gid')
				$criteria[] = array('t' => 'gid', 'v' => (int) $_POST['ruledefgroup'][$ind]);
			elseif (in_array($type, array('sub', 'msg')) && trim($_POST['ruledef'][$ind]) != '')
				$criteria[] = array('t' => $type, 'v' => $smcFunc['htmlspecialchars'](trim($_POST['ruledef'][$ind])));
		}

		// Also do the actions!
		$actions = array();
		$doDelete = 0;
		$isOr = $_POST['rule_logic'] == 'or' ? 1 : 0;
		foreach ($_POST['acttype'] as $ind => $type)
		{
			// Picking a valid label?
			if ($type == 'lab' && (!isset($_POST['labdef'][$ind]) || !isset($context['labels'][$_POST['labdef'][$ind]])))
				continue;

			// Record what we're doing.
			if ($type == 'del')
				$doDelete = 1;
			elseif ($type == 'lab')
				$actions[] = array('t' => 'lab', 'v' => (int) $_POST['labdef'][$ind]);
		}

		if (empty($criteria) || (empty($actions) && !$doDelete))
			fatal_lang_error('pm_rule_no_criteria', false);

		// What are we storing?
		$criteria = serialize($criteria);
		$actions = serialize($actions);

		// Create the rule?
		if (empty($context['rid']))
			$smcFunc['db_insert']('',
				'{db_prefix}pm_rules',
				array(
					'id_member' => 'int', 'rule_name' => 'string', 'criteria' => 'string', 'actions' => 'string',
					'delete_pm' => 'int', 'is_or' => 'int',
				),
				array(
					$user_info['id'], $ruleName, $criteria, $actions, $doDelete, $isOr,
				),
				array('id_rule')
			);
		else
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}pm_rules
				SET rule_name = {string:rule_name}, criteria = {string:criteria}, actions = {string:actions},
					delete_pm = {int:delete_pm}, is_or = {int:is_or}
				WHERE id_rule = {int:id_rule}
					AND id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
					'delete_pm' => $doDelete,
					'is_or' => $isOr,
					'id_rule' => $context['rid'],
					'rule_name' => $ruleName,
					'criteria' => $criteria,
					'actions' => $actions,
				)
			);

		redirectexit('action=pm;sa=manrules');
	}
	// Deleting?
	elseif (isset($_POST['delselected']) && !empty($_POST['delrule']))
	{
		checkSession();
		$toDelete = array();
		foreach ($_POST['delrule'] as $k => $v)
			$toDelete[] = (int) $k;

		if (!empty($toDelete))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}pm_rules
				WHERE id_rule IN ({array_int:delete_list})
					AND id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
					'delete_list' => $toDelete,
				)
			);

		redirectexit('action=pm;sa=manrules');
	}
}

/**
 * This will apply rules to all unread messages. If all_messages is set will, clearly, do it to all!
 *
 * @param bool $all_messages = false
 */
function ApplyRules($all_messages = false)
{
	global $user_info, $smcFunc, $context, $options;

	// Want this - duh!
	loadRules();

	// No rules?
	if (empty($context['rules']))
		return;

	// Just unread ones?
	$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';

	// @todo Apply all should have timeout protection!
	// Get all the messages that match this.
	$request = $smcFunc['db_query']('', '
		SELECT
			pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
		WHERE pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}
			' . $ruleQuery,
		array(
			'current_member' => $user_info['id'],
			'not_deleted' => 0,
		)
	);
	$actions = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		foreach ($context['rules'] as $rule)
		{
			$match = false;
			// Loop through all the criteria hoping to make a match.
			foreach ($rule['criteria'] as $criterium)
			{
				if (($criterium['t'] == 'mid' && $criterium['v'] == $row['id_member_from']) || ($criterium['t'] == 'gid' && $criterium['v'] == $row['id_group']) || ($criterium['t'] == 'sub' && strpos($row['subject'], $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($row['body'], $criterium['v']) !== false))
					$match = true;
				// If we're adding and one criteria don't match then we stop!
				elseif ($rule['logic'] == 'and')
				{
					$match = false;
					break;
				}
			}

			// If we have a match the rule must be true - act!
			if ($match)
			{
				if ($rule['delete'])
					$actions['deletes'][] = $row['id_pm'];
				else
				{
					foreach ($rule['actions'] as $ruleAction)
					{
						if ($ruleAction['t'] == 'lab')
						{
							// Get a basic pot started!
							if (!isset($actions['labels'][$row['id_pm']]))
								$actions['labels'][$row['id_pm']] = array();
							$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
						}
					}
				}
			}
		}
	}
	$smcFunc['db_free_result']($request);

	// Deletes are easy!
	if (!empty($actions['deletes']))
		deleteMessages($actions['deletes']);

	// Relabel?
	if (!empty($actions['labels']))
	{
		foreach ($actions['labels'] as $pm => $labels)
		{
			// Quickly check each label is valid!
			$realLabels = array();
			foreach ($context['labels'] as $label)
			{
				if (in_array($label['id'], $labels) && $label['id'] != -1 || empty($options['pm_remove_inbox_label']))
				{
					// Make sure this stays in the inbox
					if ($label['id'] == '-1')
					{
						$smcFunc['db_query']('', '
							UPDATE {db_prefix}pm_recipients
							SET in_inbox = {int:in_inbox}
							WHERE id_pm = {int:id_pm}
								AND id_member = {int:current_member}',
							array(
								'in_inbox' => 1,
								'id_pm' => $pm,
								'current_member' => $user_info['id'],
							)
						);
					}
					else
					{
						$realLabels[] = $label['id'];
					}
				}
			}

			$inserts = array();
			// Now we insert the label info
			foreach ($realLabels as $a_label)
				$inserts[] = array($pm, $a_label);

			$smcFunc['db_insert']('ignore',
				'{db_prefix}pm_labeled_messages',
				array('id_pm' => 'int', 'id_label' => 'int'),
				$inserts,
				array()
			);
		}
	}
}

/**
 * Load up all the rules for the current user.
 *
 * @param bool $reload = false
 */
function LoadRules($reload = false)
{
	global $user_info, $context, $smcFunc;

	if (isset($context['rules']) && !$reload)
		return;

	$request = $smcFunc['db_query']('', '
		SELECT
			id_rule, rule_name, criteria, actions, delete_pm, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $user_info['id'],
		)
	);
	$context['rules'] = array();
	// Simply fill in the data!
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['rules'][$row['id_rule']] = array(
			'id' => $row['id_rule'],
			'name' => $row['rule_name'],
			'criteria' => unserialize($row['criteria']),
			'actions' => unserialize($row['actions']),
			'delete' => $row['delete_pm'],
			'logic' => $row['is_or'] ? 'or' : 'and',
		);

		if ($row['delete_pm'])
			$context['rules'][$row['id_rule']]['actions'][] = array('t' => 'del', 'v' => 1);
	}
	$smcFunc['db_free_result']($request);
}

/**
 * Check if the PM is available to the current user.
 *
 * @param int $pmID
 * @param $validFor
 * @return boolean
 */
function isAccessiblePM($pmID, $validFor = 'in_or_outbox')
{
	global $user_info, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT
			pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted} AS valid_for_outbox,
			pmr.id_pm IS NOT NULL AS valid_for_inbox
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.id_member = {int:id_current_member} AND pmr.deleted = {int:not_deleted})
		WHERE pm.id_pm = {int:id_pm}
			AND ((pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted}) OR pmr.id_pm IS NOT NULL)',
		array(
			'id_pm' => $pmID,
			'id_current_member' => $user_info['id'],
			'not_deleted' => 0,
		)
	);

	if ($smcFunc['db_num_rows']($request) === 0)
	{
		$smcFunc['db_free_result']($request);
		return false;
	}

	$validationResult = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	switch ($validFor)
	{
		case 'inbox':
			return !empty($validationResult['valid_for_inbox']);
		break;

		case 'outbox':
			return !empty($validationResult['valid_for_outbox']);
		break;

		case 'in_or_outbox':
			return !empty($validationResult['valid_for_inbox']) || !empty($validationResult['valid_for_outbox']);
		break;

		default:
			trigger_error('Undefined validation type given', E_USER_ERROR);
		break;
	}
}

?>