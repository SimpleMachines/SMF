<?php

/**
 * This file handles actions made on a user's profile.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Activate an account.
 *
 * @param int $memID The ID of the member whose account we're activating
 */
function activateAccount($memID)
{
	global $sourcedir, $context, $user_profile, $modSettings;

	isAllowedTo('moderate_forum');

	if (isset($_REQUEST['save']) && isset($user_profile[$memID]['is_activated']) && $user_profile[$memID]['is_activated'] != 1)
	{
		// If we are approving the deletion of an account, we do something special ;)
		if ($user_profile[$memID]['is_activated'] == 4)
		{
			require_once($sourcedir . '/Subs-Members.php');
			deleteMembers($context['id_member']);
			redirectexit();
		}

		// Let the integrations know of the activation.
		call_integration_hook('integrate_activate', array($user_profile[$memID]['member_name']));

		// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
		updateMemberData($context['id_member'], array('is_activated' => $user_profile[$memID]['is_activated'] >= 10 ? 11 : 1, 'validation_code' => ''));

		// Log what we did?
		require_once($sourcedir . '/Logging.php');
		logAction('approve_member', array('member' => $memID), 'admin');

		// If we are doing approval, update the stats for the member just in case.
		if (in_array($user_profile[$memID]['is_activated'], array(3, 4, 5, 13, 14, 15)))
			updateSettings(array('unapprovedMembers' => ($modSettings['unapprovedMembers'] > 1 ? $modSettings['unapprovedMembers'] - 1 : 0)));

		// Make sure we update the stats too.
		updateStats('member', false);
	}

	// Leave it be...
	redirectexit('action=profile;u=' . $memID . ';area=summary');
}

/**
 * Issue/manage an user's warning status.
 *
 * @param int $memID The ID of the user
 */
function issueWarning($memID)
{
	global $txt, $scripturl, $modSettings, $user_info, $mbname;
	global $context, $cur_profile, $smcFunc, $sourcedir;

	// Get all the actual settings.
	list ($modSettings['warning_enable'], $modSettings['user_limit']) = explode(',', $modSettings['warning_settings']);

	// This stores any legitimate errors.
	$issueErrors = array();

	// Doesn't hurt to be overly cautious.
	if (empty($modSettings['warning_enable']) || ($context['user']['is_owner'] && !$cur_profile['warning']) || !allowedTo('issue_warning'))
		fatal_lang_error('no_access', false);

	// Get the base (errors related) stuff done.
	loadLanguage('Errors');
	$context['custom_error_title'] = $txt['profile_warning_errors_occured'];

	// Make sure things which are disabled stay disabled.
	$modSettings['warning_watch'] = !empty($modSettings['warning_watch']) ? $modSettings['warning_watch'] : 110;
	$modSettings['warning_moderate'] = !empty($modSettings['warning_moderate']) && !empty($modSettings['postmod_active']) ? $modSettings['warning_moderate'] : 110;
	$modSettings['warning_mute'] = !empty($modSettings['warning_mute']) ? $modSettings['warning_mute'] : 110;

	$context['warning_limit'] = allowedTo('admin_forum') ? 0 : $modSettings['user_limit'];
	$context['member']['warning'] = $cur_profile['warning'];
	$context['member']['name'] = $cur_profile['real_name'];

	// What are the limits we can apply?
	$context['min_allowed'] = 0;
	$context['max_allowed'] = 100;
	if ($context['warning_limit'] > 0)
	{
		// Make sure we cannot go outside of our limit for the day.
		$request = $smcFunc['db_query']('', '
			SELECT SUM(counter)
			FROM {db_prefix}log_comments
			WHERE id_recipient = {int:selected_member}
				AND id_member = {int:current_member}
				AND comment_type = {string:warning}
				AND log_time > {int:day_time_period}',
			array(
				'current_member' => $user_info['id'],
				'selected_member' => $memID,
				'day_time_period' => time() - 86400,
				'warning' => 'warning',
			)
		);
		list ($current_applied) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['min_allowed'] = max(0, $cur_profile['warning'] - $current_applied - $context['warning_limit']);
		$context['max_allowed'] = min(100, $cur_profile['warning'] - $current_applied + $context['warning_limit']);
	}

	// Defaults.
	$context['warning_data'] = array(
		'reason' => '',
		'notify' => '',
		'notify_subject' => '',
		'notify_body' => '',
	);

	// Are we saving?
	if (isset($_POST['save']))
	{
		// Security is good here.
		checkSession();

		// This cannot be empty!
		$_POST['warn_reason'] = isset($_POST['warn_reason']) ? trim($_POST['warn_reason']) : '';
		if ($_POST['warn_reason'] == '' && !$context['user']['is_owner'])
			$issueErrors[] = 'warning_no_reason';
		$_POST['warn_reason'] = $smcFunc['htmlspecialchars']($_POST['warn_reason']);

		$_POST['warning_level'] = (int) $_POST['warning_level'];
		$_POST['warning_level'] = max(0, min(100, $_POST['warning_level']));
		if ($_POST['warning_level'] < $context['min_allowed'])
			$_POST['warning_level'] = $context['min_allowed'];
		elseif ($_POST['warning_level'] > $context['max_allowed'])
			$_POST['warning_level'] = $context['max_allowed'];

		// Do we actually have to issue them with a PM?
		$id_notice = 0;
		if (!empty($_POST['warn_notify']) && empty($issueErrors))
		{
			$_POST['warn_sub'] = trim($_POST['warn_sub']);
			$_POST['warn_body'] = trim($_POST['warn_body']);
			if (empty($_POST['warn_sub']) || empty($_POST['warn_body']))
				$issueErrors[] = 'warning_notify_blank';
			// Send the PM?
			else
			{
				require_once($sourcedir . '/Subs-Post.php');
				$from = array(
					'id' => 0,
					'name' => $context['forum_name_html_safe'],
					'username' => $context['forum_name_html_safe'],
				);
				sendpm(array('to' => array($memID), 'bcc' => array()), $_POST['warn_sub'], $_POST['warn_body'], false, $from);

				// Log the notice!
				$id_notice = $smcFunc['db_insert']('',
					'{db_prefix}log_member_notices',
					array(
						'subject' => 'string-255', 'body' => 'string-65534',
					),
					array(
						$smcFunc['htmlspecialchars']($_POST['warn_sub']), $smcFunc['htmlspecialchars']($_POST['warn_body']),
					),
					array('id_notice'),
					1
				);
			}
		}

		// Just in case - make sure notice is valid!
		$id_notice = (int) $id_notice;

		// What have we changed?
		$level_change = $_POST['warning_level'] - $cur_profile['warning'];

		// No errors? Proceed! Only log if you're not the owner.
		if (empty($issueErrors))
		{
			// Log what we've done!
			if (!$context['user']['is_owner'])
				$smcFunc['db_insert']('',
					'{db_prefix}log_comments',
					array(
						'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int', 'recipient_name' => 'string-255',
						'log_time' => 'int', 'id_notice' => 'int', 'counter' => 'int', 'body' => 'string-65534',
					),
					array(
						$user_info['id'], $user_info['name'], 'warning', $memID, $cur_profile['real_name'],
						time(), $id_notice, $level_change, $_POST['warn_reason'],
					),
					array('id_comment')
				);

			// Make the change.
			updateMemberData($memID, array('warning' => $_POST['warning_level']));

			// Leave a lovely message.
			$context['profile_updated'] = $context['user']['is_owner'] ? $txt['profile_updated_own'] : $txt['profile_warning_success'];
		}
		else
		{
			// Try to remember some bits.
			$context['warning_data'] = array(
				'reason' => $_POST['warn_reason'],
				'notify' => !empty($_POST['warn_notify']),
				'notify_subject' => isset($_POST['warn_sub']) ? $_POST['warn_sub'] : '',
				'notify_body' => isset($_POST['warn_body']) ? $_POST['warn_body'] : '',
			);
		}

		// Show the new improved warning level.
		$context['member']['warning'] = $_POST['warning_level'];
	}

	if (isset($_POST['preview']))
	{
		$warning_body = !empty($_POST['warn_body']) ? trim(censorText($_POST['warn_body'])) : '';
		$context['preview_subject'] = !empty($_POST['warn_sub']) ? trim($smcFunc['htmlspecialchars']($_POST['warn_sub'])) : '';
		if (empty($_POST['warn_sub']) || empty($_POST['warn_body']))
			$issueErrors[] = 'warning_notify_blank';

		if (!empty($_POST['warn_body']))
		{
			require_once($sourcedir . '/Subs-Post.php');

			preparsecode($warning_body);
			$warning_body = parse_bbc($warning_body, true);
		}

		// Try to remember some bits.
		$context['warning_data'] = array(
			'reason' => $_POST['warn_reason'],
			'notify' => !empty($_POST['warn_notify']),
			'notify_subject' => isset($_POST['warn_sub']) ? $_POST['warn_sub'] : '',
			'notify_body' => isset($_POST['warn_body']) ? $_POST['warn_body'] : '',
			'body_preview' => $warning_body,
		);
	}

	if (!empty($issueErrors))
	{
		// Fill in the suite of errors.
		$context['post_errors'] = array();
		foreach ($issueErrors as $error)
			$context['post_errors'][] = $txt[$error];
	}

	$context['page_title'] = $txt['profile_issue_warning'];

	// Let's use a generic list to get all the current warnings
	require_once($sourcedir . '/Subs-List.php');

	// Work our the various levels.
	$context['level_effects'] = array(
		0 => $txt['profile_warning_effect_none'],
		$modSettings['warning_watch'] => $txt['profile_warning_effect_watch'],
		$modSettings['warning_moderate'] => $txt['profile_warning_effect_moderation'],
		$modSettings['warning_mute'] => $txt['profile_warning_effect_mute'],
	);
	$context['current_level'] = 0;
	foreach ($context['level_effects'] as $limit => $dummy)
		if ($context['member']['warning'] >= $limit)
			$context['current_level'] = $limit;

	$listOptions = array(
		'id' => 'view_warnings',
		'title' => $txt['profile_viewwarning_previous_warnings'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'no_items_label' => $txt['profile_viewwarning_no_warnings'],
		'base_href' => $scripturl . '?action=profile;area=issuewarning;sa=user;u=' . $memID,
		'default_sort_col' => 'log_time',
		'get_items' => array(
			'function' => 'list_getUserWarnings',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getUserWarningCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'issued_by' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_issued'],
					'style' => 'width: 20%;',
				),
				'data' => array(
					'function' => function($warning)
					{
						return $warning['issuer']['link'];
					},
				),
				'sort' => array(
					'default' => 'lc.member_name DESC',
					'reverse' => 'lc.member_name',
				),
			),
			'log_time' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_time'],
					'style' => 'width: 30%;',
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_reason'],
				),
				'data' => array(
					'function' => function($warning) use ($scripturl, $txt)
					{
						$ret = '
						<div class="floatleft">
							' . $warning['reason'] . '
						</div>';

						if (!empty($warning['id_notice']))
							$ret .= '
						<div class="floatright">
							<a href="' . $scripturl . '?action=moderate;area=notice;nid=' . $warning['id_notice'] . '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=400,height=250\');return false;" target="_blank" rel="noopener" title="' . $txt['profile_warning_previous_notice'] . '"><span class="main_icons filter centericon"></span></a>
						</div>';

						return $ret;
					},
				),
			),
			'level' => array(
				'header' => array(
					'value' => $txt['profile_warning_previous_level'],
					'style' => 'width: 6%;',
				),
				'data' => array(
					'db' => 'counter',
				),
				'sort' => array(
					'default' => 'lc.counter DESC',
					'reverse' => 'lc.counter',
				),
			),
		),
	);

	// Create the list for viewing.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// Are they warning because of a message?
	if (isset($_REQUEST['msg']) && 0 < (int) $_REQUEST['msg'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT m.subject
			FROM {db_prefix}messages AS m
			WHERE m.id_msg = {int:message}
				AND {query_see_message_board}
			LIMIT 1',
			array(
				'message' => (int) $_REQUEST['msg'],
			)
		);
		if ($smcFunc['db_num_rows']($request) != 0)
		{
			$context['warning_for_message'] = (int) $_REQUEST['msg'];
			list ($context['warned_message_subject']) = $smcFunc['db_fetch_row']($request);
		}
		$smcFunc['db_free_result']($request);
	}

	// Didn't find the message?
	if (empty($context['warning_for_message']))
	{
		$context['warning_for_message'] = 0;
		$context['warned_message_subject'] = '';
	}

	// Any custom templates?
	$context['notification_templates'] = array();

	$request = $smcFunc['db_query']('', '
		SELECT recipient_name AS template_title, body
		FROM {db_prefix}log_comments
		WHERE comment_type = {literal:warntpl}
			AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
		array(
			'generic' => 0,
			'current_member' => $user_info['id'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If we're not warning for a message skip any that are.
		if (!$context['warning_for_message'] && strpos($row['body'], '{MESSAGE}') !== false)
			continue;

		$context['notification_templates'][] = array(
			'title' => $row['template_title'],
			'body' => $row['body'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Setup the "default" templates.
	foreach (array('spamming', 'offence', 'insulting') as $type)
		$context['notification_templates'][] = array(
			'title' => $txt['profile_warning_notify_title_' . $type],
			'body' => sprintf($txt['profile_warning_notify_template_outline' . (!empty($context['warning_for_message']) ? '_post' : '')], $txt['profile_warning_notify_for_' . $type]),
		);

	// Replace all the common variables in the templates.
	foreach ($context['notification_templates'] as $k => $name)
		$context['notification_templates'][$k]['body'] = strtr($name['body'], array('{MEMBER}' => un_htmlspecialchars($context['member']['name']), '{MESSAGE}' => '[url=' . $scripturl . '?msg=' . $context['warning_for_message'] . ']' . un_htmlspecialchars($context['warned_message_subject']) . '[/url]', '{SCRIPTURL}' => $scripturl, '{FORUMNAME}' => $mbname, '{REGARDS}' => $txt['regards_team']));
}

/**
 * Get the number of warnings a user has. Callback for $listOptions['get_count'] in issueWarning()
 *
 * @param int $memID The ID of the user
 * @return int Total number of warnings for the user
 */
function list_getUserWarningCount($memID)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE id_recipient = {int:selected_member}
			AND comment_type = {literal:warning}',
		array(
			'selected_member' => $memID,
		)
	);
	list ($total_warnings) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $total_warnings;
}

/**
 * Get the data about a user's warnings. Callback function for the list in issueWarning()
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The member ID
 * @return array An array of information about the user's warnings
 */
function list_getUserWarnings($start, $items_per_page, $sort, $memID)
{
	global $smcFunc, $scripturl;

	$request = $smcFunc['db_query']('', '
		SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
			lc.log_time, lc.body, lc.counter, lc.id_notice
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.id_recipient = {int:selected_member}
			AND lc.comment_type = {literal:warning}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array(
			'selected_member' => $memID,
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		)
	);
	$previous_warnings = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$previous_warnings[] = array(
			'issuer' => array(
				'id' => $row['id_member'],
				'link' => $row['id_member'] ? ('<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name'] . '</a>') : $row['member_name'],
			),
			'time' => timeformat($row['log_time']),
			'reason' => $row['body'],
			'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
			'id_notice' => $row['id_notice'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $previous_warnings;
}

/**
 * Present a screen to make sure the user wants to be deleted
 *
 * @param int $memID The member ID
 */
function deleteAccount($memID)
{
	global $txt, $context, $modSettings, $cur_profile;

	if (!$context['user']['is_owner'])
		isAllowedTo('profile_remove_any');
	elseif (!allowedTo('profile_remove_any'))
		isAllowedTo('profile_remove_own');

	// Permissions for removing stuff...
	$context['can_delete_posts'] = !$context['user']['is_owner'] && allowedTo('moderate_forum');

	// Show an extra option if recycling is enabled...
	$context['show_perma_delete'] = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']);

	// Can they do this, or will they need approval?
	$context['needs_approval'] = $context['user']['is_owner'] && !empty($modSettings['approveAccountDeletion']) && !allowedTo('moderate_forum');
	$context['page_title'] = $txt['deleteAccount'] . ': ' . $cur_profile['real_name'];
}

/**
 * Actually delete an account.
 *
 * @param int $memID The member ID
 */
function deleteAccount2($memID)
{
	global $user_info, $sourcedir, $context, $cur_profile, $modSettings, $smcFunc;

	// Try get more time...
	@set_time_limit(600);

	// @todo Add a way to delete pms as well?

	if (!$context['user']['is_owner'])
		isAllowedTo('profile_remove_any');
	elseif (!allowedTo('profile_remove_any'))
		isAllowedTo('profile_remove_own');

	checkSession();

	$old_profile = &$cur_profile;

	// Too often, people remove/delete their own only account.
	if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
	{
		// Are you allowed to administrate the forum, as they are?
		isAllowedTo('admin_forum');

		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
				AND id_member != {int:selected_member}
			LIMIT 1',
			array(
				'admin_group' => 1,
				'selected_member' => $memID,
			)
		);
		list ($another) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (empty($another))
			fatal_lang_error('at_least_one_admin', 'critical');
	}

	// This file is needed for the deleteMembers function.
	require_once($sourcedir . '/Subs-Members.php');

	// Do you have permission to delete others profiles, or is that your profile you wanna delete?
	if ($memID != $user_info['id'])
	{
		isAllowedTo('profile_remove_any');

		// Before we go any further, handle possible poll vote deletion as well
		if (!empty($_POST['deleteVotes']) && allowedTo('moderate_forum'))
		{
			// First we find any polls that this user has voted in...
			$get_voted_polls = $smcFunc['db_query']('', '
				SELECT DISTINCT id_poll
				FROM {db_prefix}log_polls
				WHERE id_member = {int:selected_member}',
				array(
					'selected_member' => $memID,
				)
			);

			$polls_to_update = array();

			while ($row = $smcFunc['db_fetch_assoc']($get_voted_polls))
			{
				$polls_to_update[] = $row['id_poll'];
			}

			$smcFunc['db_free_result']($get_voted_polls);

			// Now we delete the votes and update the polls
			if (!empty($polls_to_update))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}log_polls
					WHERE id_member = {int:selected_member}',
					array(
						'selected_member' => $memID,
					)
				);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}polls
					SET votes = votes - 1
					WHERE id_poll IN {array_int:polls_to_update}',
					array(
						'polls_to_update' => $polls_to_update
					)
				);
			}
		}

		// Now, have you been naughty and need your posts deleting?
		// @todo Should this check board permissions?
		if (!empty($_POST['deletePosts']) && in_array($_POST['remove_type'], array('posts', 'topics')) && allowedTo('moderate_forum'))
		{
			// Include RemoveTopics - essential for this type of work!
			require_once($sourcedir . '/RemoveTopic.php');

			$extra = empty($_POST['perma_delete']) ? ' AND t.id_board != {int:recycle_board}' : '';
			$recycle_board = empty($modSettings['recycle_board']) ? 0 : $modSettings['recycle_board'];

			// First off we delete any topics the member has started - if they wanted topics being done.
			if ($_POST['remove_type'] == 'topics')
			{
				// Fetch all topics started by this user within the time period.
				$request = $smcFunc['db_query']('', '
					SELECT t.id_topic
					FROM {db_prefix}topics AS t
					WHERE t.id_member_started = {int:selected_member}' . $extra,
					array(
						'selected_member' => $memID,
						'recycle_board' => $recycle_board,
					)
				);
				$topicIDs = array();
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$topicIDs[] = $row['id_topic'];
				$smcFunc['db_free_result']($request);

				// Actually remove the topics. Ignore recycling if we want to perma-delete things...
				// @todo This needs to check permissions, but we'll let it slide for now because of moderate_forum already being had.
				removeTopics($topicIDs, true, !empty($extra));
			}

			// Now delete the remaining messages.
			$request = $smcFunc['db_query']('', '
				SELECT m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic
						AND t.id_first_msg != m.id_msg)
				WHERE m.id_member = {int:selected_member}' . $extra,
				array(
					'selected_member' => $memID,
					'recycle_board' => $recycle_board,
				)
			);
			// This could take a while... but ya know it's gonna be worth it in the end.
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				removeMessage($row['id_msg']);
			}
			$smcFunc['db_free_result']($request);
		}

		// Only delete this poor members account if they are actually being booted out of camp.
		if (isset($_POST['deleteAccount']))
			deleteMembers($memID);
	}
	// Do they need approval to delete?
	elseif (!empty($modSettings['approveAccountDeletion']) && !allowedTo('moderate_forum'))
	{
		// Setup their account for deletion ;)
		updateMemberData($memID, array('is_activated' => 4));
		// Another account needs approval...
		updateSettings(array('unapprovedMembers' => true), true);
	}
	// Also check if you typed your password correctly.
	else
	{
		deleteMembers($memID);

		require_once($sourcedir . '/LogInOut.php');
		LogOut(true);

		redirectexit();
	}
}

/**
 * Function for doing all the paid subscription stuff - kinda.
 *
 * @param int $memID The ID of the user whose subscriptions we're viewing
 */
function subscriptions($memID)
{
	global $context, $txt, $sourcedir, $modSettings, $smcFunc, $scripturl;

	// Load the paid template anyway.
	loadTemplate('ManagePaid');
	loadLanguage('ManagePaid');

	// Load all of the subscriptions.
	require_once($sourcedir . '/ManagePaid.php');
	loadSubscriptions();
	$context['member']['id'] = $memID;

	// Remove any invalid ones.
	foreach ($context['subscriptions'] as $id => $sub)
	{
		// Work out the costs.
		$costs = $smcFunc['json_decode']($sub['real_cost'], true);

		$cost_array = array();
		if ($sub['real_length'] == 'F')
		{
			foreach ($costs as $duration => $cost)
			{
				if ($cost != 0)
					$cost_array[$duration] = $cost;
			}
		}
		else
		{
			$cost_array['fixed'] = $costs['fixed'];
		}

		if (empty($cost_array))
			unset($context['subscriptions'][$id]);
		else
		{
			$context['subscriptions'][$id]['member'] = 0;
			$context['subscriptions'][$id]['subscribed'] = false;
			$context['subscriptions'][$id]['costs'] = $cost_array;
		}
	}

	// Work out what gateways are enabled.
	$gateways = loadPaymentGateways();
	foreach ($gateways as $id => $gateway)
	{
		$gateways[$id] = new $gateway['display_class']();
		if (!$gateways[$id]->gatewayEnabled())
			unset($gateways[$id]);
	}

	// No gateways yet?
	if (empty($gateways))
		fatal_error($txt['paid_admin_not_setup_gateway']);

	// Get the current subscriptions.
	$request = $smcFunc['db_query']('', '
		SELECT id_sublog, id_subscribe, start_time, end_time, status, payments_pending, pending_details
		FROM {db_prefix}log_subscribed
		WHERE id_member = {int:selected_member}',
		array(
			'selected_member' => $memID,
		)
	);
	$context['current'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// The subscription must exist!
		if (!isset($context['subscriptions'][$row['id_subscribe']]))
			continue;

		$context['current'][$row['id_subscribe']] = array(
			'id' => $row['id_sublog'],
			'sub_id' => $row['id_subscribe'],
			'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
			'name' => $context['subscriptions'][$row['id_subscribe']]['name'],
			'start' => timeformat($row['start_time'], false),
			'end' => $row['end_time'] == 0 ? $txt['not_applicable'] : timeformat($row['end_time'], false),
			'pending_details' => $row['pending_details'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? $txt['paid_pending'] : $txt['paid_finished']) : $txt['paid_active'],
		);

		if ($row['status'] == 1)
			$context['subscriptions'][$row['id_subscribe']]['subscribed'] = true;
	}
	$smcFunc['db_free_result']($request);

	// Simple "done"?
	if (isset($_GET['done']))
	{
		$_GET['sub_id'] = (int) $_GET['sub_id'];

		// Must exist but let's be sure...
		if (isset($context['current'][$_GET['sub_id']]))
		{
			// What are the details like?
			$current_pending = $smcFunc['json_decode']($context['current'][$_GET['sub_id']]['pending_details'], true);
			if (!empty($current_pending))
			{
				$current_pending = array_reverse($current_pending);
				foreach ($current_pending as $id => $sub)
				{
					// Just find one and change it.
					if ($sub[0] == $_GET['sub_id'] && $sub[3] == 'prepay')
					{
						$current_pending[$id][3] = 'payback';
						break;
					}
				}

				// Save the details back.
				$pending_details = $smcFunc['json_encode']($current_pending);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = payments_pending + 1, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_id}
						AND id_member = {int:selected_member}',
					array(
						'current_subscription_id' => $context['current'][$_GET['sub_id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}
		}

		$context['sub_template'] = 'paid_done';
		return;
	}
	// If this is confirmation then it's simpler...
	if (isset($_GET['confirm']) && isset($_POST['sub_id']) && is_array($_POST['sub_id']))
	{
		// Hopefully just one.
		foreach ($_POST['sub_id'] as $k => $v)
			$ID_SUB = (int) $k;

		if (!isset($context['subscriptions'][$ID_SUB]) || $context['subscriptions'][$ID_SUB]['active'] == 0)
			fatal_lang_error('paid_sub_not_active');

		// Simplify...
		$context['sub'] = $context['subscriptions'][$ID_SUB];
		$period = 'xx';
		if ($context['sub']['flexible'])
			$period = isset($_POST['cur'][$ID_SUB]) && isset($context['sub']['costs'][$_POST['cur'][$ID_SUB]]) ? $_POST['cur'][$ID_SUB] : 'xx';

		// Check we have a valid cost.
		if ($context['sub']['flexible'] && $period == 'xx')
			fatal_lang_error('paid_sub_not_active');

		// Sort out the cost/currency.
		$context['currency'] = $modSettings['paid_currency_code'];
		$context['recur'] = $context['sub']['repeatable'];

		if ($context['sub']['flexible'])
		{
			// Real cost...
			$context['value'] = $context['sub']['costs'][$_POST['cur'][$ID_SUB]];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']) . '/' . $txt[$_POST['cur'][$ID_SUB]];
			// The period value for paypal.
			$context['paypal_period'] = strtoupper(substr($_POST['cur'][$ID_SUB], 0, 1));
		}
		else
		{
			// Real cost...
			$context['value'] = $context['sub']['costs']['fixed'];
			$context['cost'] = sprintf($modSettings['paid_currency_symbol'], $context['value']);

			// Recur?
			preg_match('~(\d*)(\w)~', $context['sub']['real_length'], $match);
			$context['paypal_unit'] = $match[1];
			$context['paypal_period'] = $match[2];
		}

		// Setup the gateway context.
		$context['gateways'] = array();
		foreach ($gateways as $id => $gateway)
		{
			$fields = $gateways[$id]->fetchGatewayFields($context['sub']['id'] . '+' . $memID, $context['sub'], $context['value'], $period, $scripturl . '?action=profile&u=' . $memID . '&area=subscriptions&sub_id=' . $context['sub']['id'] . '&done');
			if (!empty($fields['form']))
				$context['gateways'][] = $fields;
		}

		// Bugger?!
		if (empty($context['gateways']))
			fatal_error($txt['paid_admin_not_setup_gateway']);

		// Now we are going to assume they want to take this out ;)
		$new_data = array($context['sub']['id'], $context['value'], $period, 'prepay');
		if (isset($context['current'][$context['sub']['id']]))
		{
			// What are the details like?
			$current_pending = array();
			if ($context['current'][$context['sub']['id']]['pending_details'] != '')
				$current_pending = $smcFunc['json_decode']($context['current'][$context['sub']['id']]['pending_details'], true);
			// Don't get silly.
			if (count($current_pending) > 9)
				$current_pending = array();
			$pending_count = 0;
			// Only record real pending payments as will otherwise confuse the admin!
			foreach ($current_pending as $pending)
				if ($pending[3] == 'payback')
					$pending_count++;

			if (!in_array($new_data, $current_pending))
			{
				$current_pending[] = $new_data;
				$pending_details = $smcFunc['json_encode']($current_pending);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = {int:pending_count}, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_item}
						AND id_member = {int:selected_member}',
					array(
						'pending_count' => $pending_count,
						'current_subscription_item' => $context['current'][$context['sub']['id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}
		}
		// Never had this before, lovely.
		else
		{
			$pending_details = $smcFunc['json_encode'](array($new_data));
			$smcFunc['db_insert']('',
				'{db_prefix}log_subscribed',
				array(
					'id_subscribe' => 'int', 'id_member' => 'int', 'status' => 'int', 'payments_pending' => 'int', 'pending_details' => 'string-65534',
					'start_time' => 'int', 'vendor_ref' => 'string-255',
				),
				array(
					$context['sub']['id'], $memID, 0, 0, $pending_details,
					time(), '',
				),
				array('id_sublog')
			);
		}

		// Change the template.
		$context['sub_template'] = 'choose_payment';

		// Quit.
		return;
	}
	else
		$context['sub_template'] = 'user_subscription';
}

/**
 * Exports a member's profile, posts, and personal messages to a file.
 *
 * @todo Add HTML, CSV, JSON as other possible export formats besides XML?
 *
 * @param int $memID The ID of the member whose data we're exporting.
 */
function export_profile_data($memID)
{
	global $context, $smcFunc, $txt, $modSettings, $sourcedir, $scripturl;
	global $query_this_board;

	if (!isset($context['token_check']))
		$context['token_check'] = 'profile-ex' . $memID;

	$context['export_formats'] = get_export_formats();

	// This lists the types of data we can export and info for doing so.
	$context['export_datatypes'] = array(
		'profile' => array(
			'label' => $txt['export_include_profile'],
			'total' => 1,
			'latest' => 1,
			// Instructions to pass to ExportProfileData background task:
			'XML' => array(
				'func' => 'getXmlProfile',
				'langfile' => 'Profile',
			),
			'HTML' => array(
				'func' => 'getXmlProfile',
				'langfile' => 'Profile',
			),
			// 'CSV' => array(),
			// 'JSON' => array(),
		),
		'posts' => array(
			'label' => $txt['export_include_posts'],
			'total' => $context['member']['real_posts'],
			'latest' => function($memID)
			{
				global $smcFunc, $modSettings;

				static $latest_post;

				if (isset($latest_post))
					return $latest_post;

				$query_this_board = !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? 'b.id_board != ' . $modSettings['recycle_board'] : '1=1';

				$request = $smcFunc['db_query']('', '
					SELECT m.id_msg
					FROM {db_prefix}messages as m
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					WHERE id_member = {int:uid}
						AND ' . $query_this_board . '
					ORDER BY id_msg DESC
					LIMIT {int:limit}',
					array(
						'limit' => 1,
						'uid' => $memID,
					)
				);
				list($latest_post) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				return $latest_post;
			},
			// Instructions to pass to ExportProfileData background task:
			'XML' => array(
				'func' => 'getXmlPosts',
				'langfile' => 'Post',
			),
			'HTML' => array(
				'func' => 'getXmlPosts',
				'langfile' => 'Post',
			),
			// 'CSV' => array(),
			// 'JSON' => array(),
		),
		'personal_messages' => array(
			'label' => $txt['export_include_personal_messages'],
			'total' => function($memID)
			{
				global $smcFunc;

				static $total_pms;

				if (isset($total_pms))
					return $total_pms;

				$request = $smcFunc['db_query']('', '
					SELECT COUNT(*)
					FROM {db_prefix}personal_messages AS pm
						INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)
					WHERE (pm.id_member_from = {int:uid} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})',
					array(
						'uid' => $memID,
						'not_deleted' => 0,
					)
				);
				list($total_pms) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				return $total_pms;
			},
			'latest' => function($memID)
			{
				global $smcFunc;

				static $latest_pm;

				if (isset($latest_pm))
					return $latest_pm;

				$request = $smcFunc['db_query']('', '
					SELECT pm.id_pm
					FROM {db_prefix}personal_messages AS pm
						INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)
					WHERE (pm.id_member_from = {int:uid} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})
					ORDER BY pm.id_pm DESC
					LIMIT {int:limit}',
					array(
						'limit' => 1,
						'uid' => $memID,
						'not_deleted' => 0,
					)
				);
				list($latest_pm) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				return $latest_pm;
			},
			// Instructions to pass to ExportProfileData background task:
			'XML' => array(
				'func' => 'getXmlPMs',
				'langfile' => 'PersonalMessage',
			),
			'HTML' => array(
				'func' => 'getXmlPMs',
				'langfile' => 'PersonalMessage',
			),
			// 'CSV' => array(),
			// 'JSON' => array(),
		),
	);

	if (empty($modSettings['export_dir']) || !file_exists($modSettings['export_dir']))
		create_export_dir();

	$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;

	$idhash = hash_hmac('sha1', $memID, get_auth_secret());
	$dltoken = hash_hmac('sha1', $idhash, get_auth_secret());

	$query_this_board = !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? 'b.id_board != ' . $modSettings['recycle_board'] : '1=1';

	$context['completed_exports'] = array();
	$context['active_exports'] = array();
	$latest = array();

	foreach ($context['export_formats'] as $format => $format_settings)
	{
		$done = null;

		$idhash_ext = $idhash . '.' . $format_settings['extension'];

		// $realfile needs to be the highest numbered one, or 1_*** if none exist.
		$filenum = 1;
		$realfile = $export_dir_slash . $filenum . '_' . $idhash_ext;
		while (file_exists($export_dir_slash . ($filenum + 1) . '_' . $idhash_ext))
			$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;

		$tempfile = $export_dir_slash . $idhash_ext . '.tmp';
		$progressfile = $export_dir_slash . $idhash_ext . '.progress.json';

		// If requested by the user, delete any existing export files and background tasks.
		if (isset($_POST['delete']) && isset($_POST['format']) && $_POST['format'] === $format && isset($_POST['t']) && $_POST['t'] === $dltoken)
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}background_tasks
				WHERE task_class = {string:class}
					AND task_data LIKE {string:details}',
				array(
					'class' => 'ExportProfileData_Background',
					'details' => substr($smcFunc['json_encode'](array('format' => $format, 'uid' => $memID)), 0, -1) . ',%',
				)
			);

			foreach (glob($export_dir_slash . '*' . $idhash_ext . '*') as $fpath)
				@unlink($fpath);

			if (empty($_POST['export_begin']))
				redirectexit('action=profile;area=getprofiledata;u=' . $memID);
		}

		$progress = file_exists($progressfile) ? $smcFunc['json_decode'](file_get_contents($progressfile), true) : array();

		if (!empty($progress))
			$included = array_keys($progress);
		else
			$included = array_intersect(array_keys($context['export_datatypes']), array_keys($_POST));

		// If we're starting a new export in this format, we're done here.
		if (!empty($_POST['export_begin']) && $_POST['format'] === $format)
			break;

		// The rest of this loop deals with current exports, if any.

		$included_desc = array();
		foreach ($included as $datatype)
			$included_desc[] = $txt[$datatype];

		$dlfilename = array_merge(array($context['forum_name'], $context['member']['username']), $included_desc);
		$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', un_htmlspecialchars(strip_tags(implode('_', $dlfilename)))));

		if (file_exists($tempfile) && file_exists($progressfile))
		{
			$done = false;
		}
		elseif (file_exists($realfile))
		{
			// It looks like we're done.
			$done = true;

			// But let's check whether any recently created content should be added.
			foreach ($context['export_datatypes'] as $datatype => $datatype_settings)
			{
				if (!isset($progress[$datatype]))
					continue;

				if (!isset($latest[$datatype]))
					$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest']($memID) : $datatype_settings['latest'];

				if ($latest[$datatype] > $progress[$datatype])
				{
					$done = false;
					$_POST[$datatype] = true;
					$start[$datatype] = $progress[$datatype];

					if (!isset($current_datatype))
						$current_datatype = $datatype;
				}
			}
			if ($done === false)
			{
				$_POST['export_begin'] = true;
				$_POST['format'] = $format;
				createToken('profile-ex' . $memID, 'post');

				// For plain XML format, we can try appending directly to the existing file.
				if ($format === 'XML')
				{
					@unlink($tempfile);
					rename($realfile, $tempfile);
				}
				// For HTML format, things are a bit more complicated...
				elseif ($format === 'HTML')
				{
					// If we have a backup of the last version of the XML, use it.
					if (file_exists($tempfile . '.bak'))
					{
						@unlink($realfile);
						rename($tempfile . '.bak', $tempfile);
					}
					// Otherwise, we have no choice but to make a supplementary file.
					else
						$realfile = $export_dir_slash . ++$filenum . '_' . $idhash_ext;
				}
				// elseif ($format === 'CSV')
				// {
				// }
				// elseif ($format === 'JSON')
				// {
				// }
			}
		}

		if ($done === true)
		{
			$exportfilepaths = glob($export_dir_slash . '*_' . $idhash_ext);

			foreach ($exportfilepaths as $exportfilepath)
			{
				$exportbasename = basename($exportfilepath);

				$part = substr($exportbasename, 0, strcspn($exportbasename, '_'));
				$suffix = count($exportfilepaths) == 1 ? '' : '_' . $part;

				$size = filesize($exportfilepath) / 1024;
				$units = array('KB', 'MB', 'GB', 'TB');
				$unitkey = 0;
				while ($size > 1024)
				{
					$size = $size / 1024;
					$unitkey++;
				}
				$size = round($size, 2) . $units[$unitkey];

				$context['completed_exports'][$idhash_ext][$part] = array(
					'realname' => $exportbasename,
					'dlbasename' => $dlfilename . $suffix . '.' . $format_settings['extension'],
					'dltoken' => $dltoken,
					'included' => sentence_list($included_desc),
					'format' => $format,
					'mtime' => timeformat(filemtime($exportfilepath)),
					'size' => $size,
				);
			}

			ksort($context['completed_exports'][$idhash_ext], SORT_NUMERIC);
		}
		elseif ($done === false)
		{
			$context['active_exports'][$idhash_ext] = array(
				'dltoken' => $dltoken,
				'included' => sentence_list($included_desc),
				'format' => $format,
			);
		}
	}

	if (!empty($_POST['export_begin']))
	{
		checkSession();
		validateToken($context['token_check'], 'post');

		$format = isset($context['export_formats'][$_POST['format']]) ? $_POST['format'] : 'XML';

		$included = array();
		foreach ($context['export_datatypes'] as $datatype => $datatype_settings)
		{
			if (!empty($_POST[$datatype]))
			{
				$included[$datatype] = $datatype_settings[$format];
				$start[$datatype] = !empty($start[$datatype]) ? $start[$datatype] : 0;

				if (!isset($latest[$datatype]))
					$latest[$datatype] = is_callable($datatype_settings['latest']) ? $datatype_settings['latest']($memID) : $datatype_settings['latest'];
			}
		}

		if (empty($included))
			unset($context['active_exports'][$idhash . '.' . $context['export_formats'][$format]['extension']]);
		else
		{
			$data = $smcFunc['json_encode'](array(
				'format' => $format,
				'uid' => $memID,
				'lang' => $context['member']['language'],
				'included' => $included,
				'start' => $start,
				'latest' => $latest,
				'datatype' => isset($current_datatype) ? $current_datatype : key($included),
				'format_settings' => $context['export_formats'][$format],
			));

			$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/ExportProfileData.php', 'ExportProfileData_Background', $data, 0),
				array()
			);

			// So the user can see that we've started.
			if (!file_exists($realfile) && !file_exists($tempfile) && !file_exists($progressfile))
			{
				require_once($sourcedir . '/News.php');

				$desc = array();
				foreach (array_keys($included) as $datatype)
					$desc[] = $txt[$datatype];

				$feed_meta = array(
					'title' => sprintf($txt['profile_of_username'], $context['member']['username']),
					'desc' => sentence_list($desc),
					'author' => $context['forum_name'],
					'source' => $scripturl . '?action=profile;u=' . $memID,
					'self' => $scripturl . '?action=profile;area=download;u=' . $memID . ';t=' . $dltoken,
				);

				buildXmlFeed('smf', array(), $feed_meta, 'profile');
				file_put_contents($tempfile, implode('', array($context['feed']['header'], $context['feed']['footer'])));

				file_put_contents($progressfile, $smcFunc['json_encode'](array_fill_keys(array_keys($included), 0)));
			}

			redirectexit('action=profile;area=getprofiledata;u=' . $memID);
		}
	}

	createToken($context['token_check'], 'post');

	$context['page_title'] = $txt['export_profile_data'];

	if (empty($modSettings['export_expiry']))
		unset($txt['export_profile_data_desc_list']['expiry']);
	else
		$txt['export_profile_data_desc_list']['expiry'] = sprintf($txt['export_profile_data_desc_list']['expiry'], $modSettings['export_expiry']);

	$context['export_profile_data_desc'] = sprintf($txt['export_profile_data_desc'], '<li>' . implode('</li><li>', $txt['export_profile_data_desc_list']) . '</li>');
}

/**
 * Downloads exported profile data file.
 *
 * @param int $memID The ID of the member whose data we're exporting.
 */
function download_export_file($memID)
{
	global $modSettings, $maintenance, $context, $txt, $smcFunc;

	$export_formats = get_export_formats();

	// This is done to clear any output that was made before now.
	ob_end_clean();

	if (!empty($modSettings['enableCompressedOutput']) && !headers_sent() && ob_get_length() == 0)
	{
		if (@ini_get('zlib.output_compression') == '1' || @ini_get('output_handler') == 'ob_gzhandler')
			$modSettings['enableCompressedOutput'] = 0;

		else
			ob_start('ob_gzhandler');
	}

	if (empty($modSettings['enableCompressedOutput']))
	{
		ob_start();
		header('content-encoding: none');
	}

	// No access in strict maintenance mode.
	if (!empty($maintenance) && $maintenance == 2)
	{
		send_http_status(404);
		exit;
	}

	// We can't give them anything without these.
	if (empty($_GET['t']) || empty($_GET['format']) || !isset($export_formats[$_GET['format']]))
	{
		send_http_status(400);
		exit;
	}

	$export_dir_slash = $modSettings['export_dir'] . DIRECTORY_SEPARATOR;

	$idhash = hash_hmac('sha1', $memID, get_auth_secret());
	$part = isset($_GET['part']) ? (int) $_GET['part'] : 1;
	$extension = $export_formats[$_GET['format']]['extension'];

	$filepath = $export_dir_slash . $part . '_' . $idhash . '.' . $extension;
	$progressfile = $export_dir_slash . $idhash . '.' . $extension . '.progress.json';

	// Make sure they gave the correct authentication token.
	// We use these tokens so the user can download without logging in, as required by the GDPR.
	$dltoken = hash_hmac('sha1', $idhash, get_auth_secret());
	if ($_GET['t'] !== $dltoken)
	{
		send_http_status(403);
		exit;
	}

	// Obviously we can't give what we don't have.
	if (empty($modSettings['export_dir']) || !file_exists($filepath))
	{
		send_http_status(404);
		exit;
	}

	// Figure out the filename we'll tell the browser.
	$datatypes = file_exists($progressfile) ? array_keys($smcFunc['json_decode'](file_get_contents($progressfile), true)) : array('profile');
	$included_desc = array_map(function ($datatype) use ($txt) { return $txt[$datatype]; }, $datatypes);

	$dlfilename = array_merge(array($context['forum_name'], $context['member']['username']), $included_desc);
	$dlfilename = preg_replace('/[^\p{L}\p{M}\p{N}_]+/u', '-', str_replace('"', '', un_htmlspecialchars(strip_tags(implode('_', $dlfilename)))));

	$suffix = ($part > 1 || file_exists($export_dir_slash . '2_' . $idhash . '.' . $extension)) ? '_' . $part : '';

	$dlbasename = $dlfilename . $suffix . '.' . $extension;

	$mtime = filemtime($filepath);
	$size = filesize($filepath);

	// If it hasn't been modified since the last time it was retrieved, there's no need to serve it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= $mtime)
		{
			ob_end_clean();

			// Answer the question - no, it hasn't been modified ;).
			send_http_status(304);
			exit;
		}
	}

	// Check whether the ETag was sent back, and cache based on that...
	$eTag = md5(implode(' ', array($dlbasename, $size, $mtime)));
	if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
	{
		ob_end_clean();

		send_http_status(304);
		exit;
	}

	// If this is a partial download, we need to determine what data range to send
	$range = 0;
	if (isset($_SERVER['HTTP_RANGE']))
	{
		list($a, $range) = explode("=", $_SERVER['HTTP_RANGE'], 2);
		list($range) = explode(",", $range, 2);
		list($range, $range_end) = explode("-", $range);
		$range = intval($range);
		$range_end = !$range_end ? $size - 1 : intval($range_end);
		$new_length = $range_end - $range + 1;
	}

	header('pragma: ');

	if (!isBrowser('gecko'))
		header('content-transfer-encoding: binary');

	header('expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('last-modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
	header('accept-ranges: bytes');
	header('connection: close');
	header('etag: ' . $eTag);
	header('content-type: ' . $export_formats[$_GET['format']]['mime']);

	// Convert the file to UTF-8, cuz most browsers dig that.
	$utf8name = !$context['utf8'] && function_exists('iconv') ? iconv($context['character_set'], 'UTF-8', $dlbasename) : (!$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($dlbasename, 'UTF-8', $context['character_set']) : $dlbasename);

	// Different browsers like different standards...
	if (isBrowser('firefox'))
		header('content-disposition: attachment; filename*=UTF-8\'\'' . rawurlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)));

	elseif (isBrowser('opera'))
		header('content-disposition: attachment; filename="' . preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name) . '"');

	elseif (isBrowser('ie'))
		header('content-disposition: attachment; filename="' . urlencode(preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $utf8name)) . '"');

	else
		header('content-disposition: attachment; filename="' . $utf8name . '"');

	header('cache-control: max-age=' . (525600 * 60) . ', private');

	// Multipart and resuming support
	if (isset($_SERVER['HTTP_RANGE']))
	{
		send_http_status(206);
		header("content-length: $new_length");
		header("content-range: bytes $range-$range_end/$size");
	}
	else
		header("content-length: $size");

	// Try to buy some time...
	@set_time_limit(600);

	// For multipart/resumable downloads, send the requested chunk(s) of the file
	if (isset($_SERVER['HTTP_RANGE']))
	{
		while (@ob_get_level() > 0)
			@ob_end_clean();

		// 40 kilobytes is a good-ish amount
		$chunksize = 40 * 1024;
		$bytes_sent = 0;

		$fp = fopen($filepath, 'rb');

		fseek($fp, $range);

		while (!feof($fp) && (!connection_aborted()) && ($bytes_sent < $new_length))
		{
			$buffer = fread($fp, $chunksize);
			echo($buffer);
			flush();
			$bytes_sent += strlen($buffer);
		}
		fclose($fp);
	}

	// Since we don't do output compression for files this large...
	elseif ($size > 4194304)
	{
		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

		$fp = fopen($filepath, 'rb');
		while (!feof($fp))
		{
			echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}

	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (@readfile($filepath) === null)
		echo file_get_contents($filepath);

	exit;
}

/**
 * Allows a member to export their attachments.
 * Mostly just a wrapper for showAttachment() but with a few tweaks.
 *
 * @param int $memID The ID of the member whose data we're exporting.
 */
function export_attachment($memID)
{
	global $sourcedir, $context, $smcFunc;

	$idhash = hash_hmac('sha1', $memID, get_auth_secret());
	$dltoken = hash_hmac('sha1', $idhash, get_auth_secret());
	if (!isset($_GET['t']) || $_GET['t'] !== $dltoken)
	{
		send_http_status(403);
		exit;
	}

	$attachId = isset($_REQUEST['attach']) ? (int) $_REQUEST['attach'] : 0;
	if (empty($attachId))
	{
		send_http_status(404, 'File Not Found');
		die('404 File Not Found');
	}

	// Does this attachment belong to this member?
	$request = $smcFunc['db_query']('', '
		SELECT m.id_topic
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}attachments AS a ON (m.id_msg = a.id_msg)
		WHERE m.id_member = {int:uid}
			AND a.id_attach = {int:attachId}',
		array(
			'uid' => $memID,
			'attachId' => $attachId,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		$smcFunc['db_free_result']($request);
		send_http_status(403);
		exit;
	}

	// We need the topic.
	list ($_REQUEST['topic']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// This doesn't count as a normal download.
	$context['skip_downloads_increment'] = true;

	// Try to avoid collisons when attachment names are not unique.
	$context['prepend_attachment_id'] = true;

	// We should now have what we need to serve the file.
	require_once($sourcedir . '/ShowAttachments.php');
	showAttachment();
}

/**
 * Helper function that defines data export formats in a single location.
 *
 * @return array Information about supported data formats for profile exports.
 */
function get_export_formats()
{
	$export_formats = array(
		'HTML' => array('extension' => 'html', 'mime' => 'text/html'),
		'XML' => array('extension' => 'xml', 'mime' => 'application/xml'),
		// 'CSV' => array('extension' => 'csv', 'mime' => 'text/csv'),
		// 'JSON' => array('extension' => 'json', 'mime' => 'application/json'),
	);

	// If these are missing, the "HTML" file will actually be XML with embedded XSLT.
	if (!class_exists('DOMDocument') || !class_exists('XSLTProcessor'))
		$export_formats['HTML'] = array('extension' => 'html.xml', 'mime' => 'application/xml');

	return $export_formats;
}

/**
 * Returns the path to a secure directory for storing exported profile data.
 *
 * The directory is created if it does not yet exist, and is secured using the
 * same method that we use to secure attachment directories. Files in this
 * directory can only be downloaded via the download_export_file() function.
 *
 * @return string|bool The path to the directory, or false on error.
 */
function create_export_dir($fallback = '')
{
	global $boarddir, $modSettings;

	// No supplied fallback, so use the default location.
	if (empty($fallback))
		$fallback = $boarddir . DIRECTORY_SEPARATOR . 'exports';

	// Automatically set it to the fallback if it is missing.
	if (empty($modSettings['export_dir']))
		updateSettings(array('export_dir' => $fallback));

	// Make sure the directory exists.
	if (!file_exists($modSettings['export_dir']))
		@mkdir($modSettings['export_dir'], null, true);

	// Make sure the directory has the correct permissions.
	if (!is_dir($modSettings['export_dir']) || !smf_chmod($modSettings['export_dir']))
	{
		loadLanguage('Errors');

		// Try again at the fallback location.
		if ($modSettings['export_dir'] != $fallback)
		{
			log_error($txt['export_dir_forced_change'], $modSettings['export_dir'], $fallback);
			updateSettings(array('export_dir' => $fallback));

			// Secondary fallback will be the default location, so no parameter this time.
			create_export_dir();
		}
		// Uh-oh. Even the default location failed.
		else
		{
			log_error($txt['export_dir_not_writable']);
			return false;
		}
	}

	return secureDirectory(array($modSettings['export_dir']), true);
}

?>