<?php

/**
 * This file handles actions made on a user's profile.
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

use SMF\BBCodeParser;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Msg;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Logout;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Activate an account.
 *
 * @param int $memID The ID of the member whose account we're activating
 */
function activateAccount($memID)
{
	isAllowedTo('moderate_forum');

	if (isset($_REQUEST['save']) && isset(User::$loaded[$memID]->is_activated) && User::$loaded[$memID]->is_activated != 1)
	{
		// If we are approving the deletion of an account, we do something special ;)
		if (User::$loaded[$memID]->is_activated == 4)
		{
			User::delete(Utils::$context['id_member']);
			redirectexit();
		}

		// Let the integrations know of the activation.
		call_integration_hook('integrate_activate', array(User::$loaded[$memID]->username));

		// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
		User::updateMemberData(Utils::$context['id_member'], array('is_activated' => User::$loaded[$memID]->is_activated >= 10 ? 11 : 1, 'validation_code' => ''));

		// Log what we did?
		require_once(Config::$sourcedir . '/Logging.php');
		logAction('approve_member', array('member' => $memID), 'admin');

		// If we are doing approval, update the stats for the member just in case.
		if (in_array(User::$loaded[$memID]->is_activated, array(3, 4, 5, 13, 14, 15)))
			Config::updateModSettings(array('unapprovedMembers' => (Config::$modSettings['unapprovedMembers'] > 1 ? Config::$modSettings['unapprovedMembers'] - 1 : 0)));

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
	// Get all the actual settings.
	list (Config::$modSettings['warning_enable'], Config::$modSettings['user_limit']) = explode(',', Config::$modSettings['warning_settings']);

	// This stores any legitimate errors.
	$issueErrors = array();

	// Doesn't hurt to be overly cautious.
	if (empty(Config::$modSettings['warning_enable']) || (User::$me->is_owner && !User::$profiles[$memID]['warning']) || !allowedTo('issue_warning'))
		fatal_lang_error('no_access', false);

	// Get the base (errors related) stuff done.
	Lang::load('Errors');
	Utils::$context['custom_error_title'] = Lang::$txt['profile_warning_errors_occured'];

	// Make sure things which are disabled stay disabled.
	Config::$modSettings['warning_watch'] = !empty(Config::$modSettings['warning_watch']) ? Config::$modSettings['warning_watch'] : 110;
	Config::$modSettings['warning_moderate'] = !empty(Config::$modSettings['warning_moderate']) && !empty(Config::$modSettings['postmod_active']) ? Config::$modSettings['warning_moderate'] : 110;
	Config::$modSettings['warning_mute'] = !empty(Config::$modSettings['warning_mute']) ? Config::$modSettings['warning_mute'] : 110;

	Utils::$context['warning_limit'] = allowedTo('admin_forum') ? 0 : Config::$modSettings['user_limit'];
	Utils::$context['member']['warning'] = User::$profiles[$memID]['warning'];
	Utils::$context['member']['name'] = User::$profiles[$memID]['real_name'];

	// What are the limits we can apply?
	Utils::$context['min_allowed'] = 0;
	Utils::$context['max_allowed'] = 100;
	if (Utils::$context['warning_limit'] > 0)
	{
		// Make sure we cannot go outside of our limit for the day.
		$request = Db::$db->query('', '
			SELECT SUM(counter)
			FROM {db_prefix}log_comments
			WHERE id_recipient = {int:selected_member}
				AND id_member = {int:current_member}
				AND comment_type = {string:warning}
				AND log_time > {int:day_time_period}',
			array(
				'current_member' => User::$me->id,
				'selected_member' => $memID,
				'day_time_period' => time() - 86400,
				'warning' => 'warning',
			)
		);
		list ($current_applied) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Utils::$context['min_allowed'] = max(0, User::$profiles[$memID]['warning'] - $current_applied - Utils::$context['warning_limit']);
		Utils::$context['max_allowed'] = min(100, User::$profiles[$memID]['warning'] - $current_applied + Utils::$context['warning_limit']);
	}

	// Defaults.
	Utils::$context['warning_data'] = array(
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
		if ($_POST['warn_reason'] == '' && !User::$me->is_owner)
			$issueErrors[] = 'warning_no_reason';
		$_POST['warn_reason'] = Utils::htmlspecialchars($_POST['warn_reason']);

		$_POST['warning_level'] = (int) $_POST['warning_level'];
		$_POST['warning_level'] = max(0, min(100, $_POST['warning_level']));
		if ($_POST['warning_level'] < Utils::$context['min_allowed'])
			$_POST['warning_level'] = Utils::$context['min_allowed'];
		elseif ($_POST['warning_level'] > Utils::$context['max_allowed'])
			$_POST['warning_level'] = Utils::$context['max_allowed'];

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
				$from = array(
					'id' => 0,
					'name' => Utils::$context['forum_name_html_safe'],
					'username' => Utils::$context['forum_name_html_safe'],
				);
				Msg::sendpm(array('to' => array($memID), 'bcc' => array()), $_POST['warn_sub'], $_POST['warn_body'], false, $from);

				// Log the notice!
				$id_notice = Db::$db->insert('',
					'{db_prefix}log_member_notices',
					array(
						'subject' => 'string-255', 'body' => 'string-65534',
					),
					array(
						Utils::htmlspecialchars($_POST['warn_sub']), Utils::htmlspecialchars($_POST['warn_body']),
					),
					array('id_notice'),
					1
				);
			}
		}

		// Just in case - make sure notice is valid!
		$id_notice = (int) $id_notice;

		// What have we changed?
		$level_change = $_POST['warning_level'] - User::$profiles[$memID]['warning'];

		// No errors? Proceed! Only log if you're not the owner.
		if (empty($issueErrors))
		{
			// Log what we've done!
			if (!User::$me->is_owner)
				Db::$db->insert('',
					'{db_prefix}log_comments',
					array(
						'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int', 'recipient_name' => 'string-255',
						'log_time' => 'int', 'id_notice' => 'int', 'counter' => 'int', 'body' => 'string-65534',
					),
					array(
						User::$me->id, User::$me->name, 'warning', $memID, User::$profiles[$memID]['real_name'],
						time(), $id_notice, $level_change, $_POST['warn_reason'],
					),
					array('id_comment')
				);

			// Make the change.
			User::updateMemberData($memID, array('warning' => $_POST['warning_level']));

			// Leave a lovely message.
			Utils::$context['profile_updated'] = User::$me->is_owner ? Lang::$txt['profile_updated_own'] : Lang::$txt['profile_warning_success'];
		}
		else
		{
			// Try to remember some bits.
			Utils::$context['warning_data'] = array(
				'reason' => $_POST['warn_reason'],
				'notify' => !empty($_POST['warn_notify']),
				'notify_subject' => isset($_POST['warn_sub']) ? $_POST['warn_sub'] : '',
				'notify_body' => isset($_POST['warn_body']) ? $_POST['warn_body'] : '',
			);
		}

		// Show the new improved warning level.
		Utils::$context['member']['warning'] = $_POST['warning_level'];
	}

	if (isset($_POST['preview']))
	{
		$warning_body = !empty($_POST['warn_body']) ? trim(Lang::censorText($_POST['warn_body'])) : '';
		Utils::$context['preview_subject'] = !empty($_POST['warn_sub']) ? trim(Utils::htmlspecialchars($_POST['warn_sub'])) : '';
		if (empty($_POST['warn_sub']) || empty($_POST['warn_body']))
			$issueErrors[] = 'warning_notify_blank';

		if (!empty($_POST['warn_body']))
		{
			Msg::preparsecode($warning_body);
			$warning_body = BBCodeParser::load()->parse($warning_body);
		}

		// Try to remember some bits.
		Utils::$context['warning_data'] = array(
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
		Utils::$context['post_errors'] = array();
		foreach ($issueErrors as $error)
			Utils::$context['post_errors'][] = Lang::$txt[$error];
	}

	Utils::$context['page_title'] = Lang::$txt['profile_issue_warning'];

	// Work our the various levels.
	Utils::$context['level_effects'] = array(
		0 => Lang::$txt['profile_warning_effect_none'],
		Config::$modSettings['warning_watch'] => Lang::$txt['profile_warning_effect_watch'],
		Config::$modSettings['warning_moderate'] => Lang::$txt['profile_warning_effect_moderation'],
		Config::$modSettings['warning_mute'] => Lang::$txt['profile_warning_effect_mute'],
	);
	Utils::$context['current_level'] = 0;
	foreach (Utils::$context['level_effects'] as $limit => $dummy)
		if (Utils::$context['member']['warning'] >= $limit)
			Utils::$context['current_level'] = $limit;

	$listOptions = array(
		'id' => 'view_warnings',
		'title' => Lang::$txt['profile_viewwarning_previous_warnings'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['profile_viewwarning_no_warnings'],
		'base_href' => Config::$scripturl . '?action=profile;area=issuewarning;sa=user;u=' . $memID,
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
					'value' => Lang::$txt['profile_warning_previous_issued'],
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
					'value' => Lang::$txt['profile_warning_previous_time'],
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
					'value' => Lang::$txt['profile_warning_previous_reason'],
				),
				'data' => array(
					'function' => function($warning)
					{
						$ret = '
						<div class="floatleft">
							' . $warning['reason'] . '
						</div>';

						if (!empty($warning['id_notice']))
							$ret .= '
						<div class="floatright">
							<a href="' . Config::$scripturl . '?action=moderate;area=notice;nid=' . $warning['id_notice'] . '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=400,height=250\');return false;" target="_blank" rel="noopener" title="' . Lang::$txt['profile_warning_previous_notice'] . '"><span class="main_icons filter centericon"></span></a>
						</div>';

						return $ret;
					},
				),
			),
			'level' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_level'],
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
	new ItemList($listOptions);

	// Are they warning because of a message?
	if (isset($_REQUEST['msg']) && 0 < (int) $_REQUEST['msg'])
	{
		$request = Db::$db->query('', '
			SELECT m.subject
			FROM {db_prefix}messages AS m
			WHERE m.id_msg = {int:message}
				AND {query_see_message_board}
			LIMIT 1',
			array(
				'message' => (int) $_REQUEST['msg'],
			)
		);
		if (Db::$db->num_rows($request) != 0)
		{
			Utils::$context['warning_for_message'] = (int) $_REQUEST['msg'];
			list (Utils::$context['warned_message_subject']) = Db::$db->fetch_row($request);
		}
		Db::$db->free_result($request);
	}

	// Didn't find the message?
	if (empty(Utils::$context['warning_for_message']))
	{
		Utils::$context['warning_for_message'] = 0;
		Utils::$context['warned_message_subject'] = '';
	}

	// Any custom templates?
	Utils::$context['notification_templates'] = array();

	$request = Db::$db->query('', '
		SELECT recipient_name AS template_title, body
		FROM {db_prefix}log_comments
		WHERE comment_type = {literal:warntpl}
			AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
		array(
			'generic' => 0,
			'current_member' => User::$me->id,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		// If we're not warning for a message skip any that are.
		if (!Utils::$context['warning_for_message'] && strpos($row['body'], '{MESSAGE}') !== false)
			continue;

		Utils::$context['notification_templates'][] = array(
			'title' => $row['template_title'],
			'body' => $row['body'],
		);
	}
	Db::$db->free_result($request);

	// Setup the "default" templates.
	foreach (array('spamming', 'offence', 'insulting') as $type)
		Utils::$context['notification_templates'][] = array(
			'title' => Lang::$txt['profile_warning_notify_title_' . $type],
			'body' => sprintf(Lang::$txt['profile_warning_notify_template_outline' . (!empty(Utils::$context['warning_for_message']) ? '_post' : '')], Lang::$txt['profile_warning_notify_for_' . $type]),
		);

	// Replace all the common variables in the templates.
	foreach (Utils::$context['notification_templates'] as $k => $name)
		Utils::$context['notification_templates'][$k]['body'] = strtr($name['body'], array('{MEMBER}' => un_htmlspecialchars(Utils::$context['member']['name']), '{MESSAGE}' => '[url=' . Config::$scripturl . '?msg=' . Utils::$context['warning_for_message'] . ']' . un_htmlspecialchars(Utils::$context['warned_message_subject']) . '[/url]', '{SCRIPTURL}' => Config::$scripturl, '{FORUMNAME}' => Config::$mbname, '{REGARDS}' => sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name'])));
}

/**
 * Get the number of warnings a user has. Callback for $listOptions['get_count'] in issueWarning()
 *
 * @param int $memID The ID of the user
 * @return int Total number of warnings for the user
 */
function list_getUserWarningCount($memID)
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_comments
		WHERE id_recipient = {int:selected_member}
			AND comment_type = {literal:warning}',
		array(
			'selected_member' => $memID,
		)
	);
	list ($total_warnings) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

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
	$request = Db::$db->query('', '
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
	while ($row = Db::$db->fetch_assoc($request))
	{
		$previous_warnings[] = array(
			'issuer' => array(
				'id' => $row['id_member'],
				'link' => $row['id_member'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name'] . '</a>') : $row['member_name'],
			),
			'time' => timeformat($row['log_time']),
			'reason' => $row['body'],
			'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
			'id_notice' => $row['id_notice'],
		);
	}
	Db::$db->free_result($request);

	return $previous_warnings;
}

/**
 * Present a screen to make sure the user wants to be deleted
 *
 * @param int $memID The member ID
 */
function deleteAccount($memID)
{
	if (!User::$me->is_owner)
		isAllowedTo('profile_remove_any');
	elseif (!allowedTo('profile_remove_any'))
		isAllowedTo('profile_remove_own');

	// Permissions for removing stuff...
	Utils::$context['can_delete_posts'] = !User::$me->is_owner && allowedTo('moderate_forum');

	// Show an extra option if recycling is enabled...
	Utils::$context['show_perma_delete'] = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']);

	// Can they do this, or will they need approval?
	Utils::$context['needs_approval'] = User::$me->is_owner && !empty(Config::$modSettings['approveAccountDeletion']) && !allowedTo('moderate_forum');
	Utils::$context['page_title'] = Lang::$txt['deleteAccount'] . ': ' . User::$profiles[$memID]['real_name'];
}

/**
 * Actually delete an account.
 *
 * @param int $memID The member ID
 */
function deleteAccount2($memID)
{
	// Try get more time...
	@set_time_limit(600);

	// @todo Add a way to delete pms as well?

	if (!User::$me->is_owner)
		isAllowedTo('profile_remove_any');
	elseif (!allowedTo('profile_remove_any'))
		isAllowedTo('profile_remove_own');

	checkSession();

	// Too often, people remove/delete their own only account.
	if (in_array(1, User::$loaded[$memID]->groups))
	{
		// Are you allowed to administrate the forum, as they are?
		isAllowedTo('admin_forum');

		$request = Db::$db->query('', '
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
		list ($another) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (empty($another))
			fatal_lang_error('at_least_one_admin', 'critical');
	}

	// Do you have permission to delete others profiles, or is that your profile you wanna delete?
	if ($memID != User::$me->id)
	{
		isAllowedTo('profile_remove_any');

		// Before we go any further, handle possible poll vote deletion as well
		if (!empty($_POST['deleteVotes']) && allowedTo('moderate_forum'))
		{
			// First we find any polls that this user has voted in...
			$get_voted_polls = Db::$db->query('', '
				SELECT DISTINCT id_poll
				FROM {db_prefix}log_polls
				WHERE id_member = {int:selected_member}',
				array(
					'selected_member' => $memID,
				)
			);

			$polls_to_update = array();

			while ($row = Db::$db->fetch_assoc($get_voted_polls))
			{
				$polls_to_update[] = $row['id_poll'];
			}

			Db::$db->free_result($get_voted_polls);

			// Now we delete the votes and update the polls
			if (!empty($polls_to_update))
			{
				Db::$db->query('', '
					DELETE FROM {db_prefix}log_polls
					WHERE id_member = {int:selected_member}',
					array(
						'selected_member' => $memID,
					)
				);

				Db::$db->query('', '
					UPDATE {db_prefix}polls
					SET votes = votes - 1
					WHERE id_poll IN ({array_int:polls_to_update})',
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
			$extra = empty($_POST['perma_delete']) ? ' AND t.id_board != {int:recycle_board}' : '';
			$recycle_board = empty(Config::$modSettings['recycle_board']) ? 0 : Config::$modSettings['recycle_board'];

			// First off we delete any topics the member has started - if they wanted topics being done.
			if ($_POST['remove_type'] == 'topics')
			{
				// Fetch all topics started by this user within the time period.
				$request = Db::$db->query('', '
					SELECT t.id_topic
					FROM {db_prefix}topics AS t
					WHERE t.id_member_started = {int:selected_member}' . $extra,
					array(
						'selected_member' => $memID,
						'recycle_board' => $recycle_board,
					)
				);
				$topicIDs = array();
				while ($row = Db::$db->fetch_assoc($request))
					$topicIDs[] = $row['id_topic'];
				Db::$db->free_result($request);

				// Actually remove the topics. Ignore recycling if we want to perma-delete things...
				// @todo This needs to check permissions, but we'll let it slide for now because of moderate_forum already being had.
				Topic::remove($topicIDs, true, !empty($extra));
			}

			// Now delete the remaining messages.
			$request = Db::$db->query('', '
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
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				Msg::remove($row['id_msg']);
			}
			Db::$db->free_result($request);
		}

		// Only delete this poor members account if they are actually being booted out of camp.
		if (isset($_POST['deleteAccount']))
			User::delete($memID);
	}
	// Do they need approval to delete?
	elseif (!empty(Config::$modSettings['approveAccountDeletion']) && !allowedTo('moderate_forum'))
	{
		// Setup their account for deletion ;)
		User::updateMemberData($memID, array('is_activated' => 4));
		// Another account needs approval...
		Config::updateModSettings(array('unapprovedMembers' => true), true);
	}
	// Also check if you typed your password correctly.
	else
	{
		User::delete($memID);

		Logout::call(true);

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
	// Load the paid template anyway.
	Theme::loadTemplate('ManagePaid');
	Lang::load('ManagePaid');

	// Load all of the subscriptions.
	require_once(Config::$sourcedir . '/ManagePaid.php');
	loadSubscriptions();
	Utils::$context['member']['id'] = $memID;

	// Remove any invalid ones.
	foreach (Utils::$context['subscriptions'] as $id => $sub)
	{
		// Work out the costs.
		$costs = Utils::jsonDecode($sub['real_cost'], true);

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
			unset(Utils::$context['subscriptions'][$id]);
		else
		{
			Utils::$context['subscriptions'][$id]['member'] = 0;
			Utils::$context['subscriptions'][$id]['subscribed'] = false;
			Utils::$context['subscriptions'][$id]['costs'] = $cost_array;
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
		fatal_error(Lang::$txt['paid_admin_not_setup_gateway']);

	// Get the current subscriptions.
	$request = Db::$db->query('', '
		SELECT id_sublog, id_subscribe, start_time, end_time, status, payments_pending, pending_details
		FROM {db_prefix}log_subscribed
		WHERE id_member = {int:selected_member}',
		array(
			'selected_member' => $memID,
		)
	);
	Utils::$context['current'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		// The subscription must exist!
		if (!isset(Utils::$context['subscriptions'][$row['id_subscribe']]))
			continue;

		Utils::$context['current'][$row['id_subscribe']] = array(
			'id' => $row['id_sublog'],
			'sub_id' => $row['id_subscribe'],
			'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
			'name' => Utils::$context['subscriptions'][$row['id_subscribe']]['name'],
			'start' => timeformat($row['start_time'], false),
			'end' => $row['end_time'] == 0 ? Lang::$txt['not_applicable'] : timeformat($row['end_time'], false),
			'pending_details' => $row['pending_details'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? Lang::$txt['paid_pending'] : Lang::$txt['paid_finished']) : Lang::$txt['paid_active'],
		);

		if ($row['status'] == 1)
			Utils::$context['subscriptions'][$row['id_subscribe']]['subscribed'] = true;
	}
	Db::$db->free_result($request);

	// Simple "done"?
	if (isset($_GET['done']))
	{
		$_GET['sub_id'] = (int) $_GET['sub_id'];

		// Must exist but let's be sure...
		if (isset(Utils::$context['current'][$_GET['sub_id']]))
		{
			// What are the details like?
			$current_pending = Utils::jsonDecode(Utils::$context['current'][$_GET['sub_id']]['pending_details'], true);
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
				$pending_details = Utils::jsonEncode($current_pending);

				Db::$db->query('', '
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = payments_pending + 1, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_id}
						AND id_member = {int:selected_member}',
					array(
						'current_subscription_id' => Utils::$context['current'][$_GET['sub_id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}
		}

		Utils::$context['sub_template'] = 'paid_done';
		return;
	}
	// If this is confirmation then it's simpler...
	if (isset($_GET['confirm']) && isset($_POST['sub_id']) && is_array($_POST['sub_id']))
	{
		// Hopefully just one.
		foreach ($_POST['sub_id'] as $k => $v)
			$ID_SUB = (int) $k;

		if (!isset(Utils::$context['subscriptions'][$ID_SUB]) || Utils::$context['subscriptions'][$ID_SUB]['active'] == 0)
			fatal_lang_error('paid_sub_not_active');

		// Simplify...
		Utils::$context['sub'] = Utils::$context['subscriptions'][$ID_SUB];
		$period = 'xx';
		if (Utils::$context['sub']['flexible'])
			$period = isset($_POST['cur'][$ID_SUB]) && isset(Utils::$context['sub']['costs'][$_POST['cur'][$ID_SUB]]) ? $_POST['cur'][$ID_SUB] : 'xx';

		// Check we have a valid cost.
		if (Utils::$context['sub']['flexible'] && $period == 'xx')
			fatal_lang_error('paid_sub_not_active');

		// Sort out the cost/currency.
		Utils::$context['currency'] = Config::$modSettings['paid_currency_code'];
		Utils::$context['recur'] = Utils::$context['sub']['repeatable'];

		if (Utils::$context['sub']['flexible'])
		{
			// Real cost...
			Utils::$context['value'] = Utils::$context['sub']['costs'][$_POST['cur'][$ID_SUB]];
			Utils::$context['cost'] = sprintf(Config::$modSettings['paid_currency_symbol'], Utils::$context['value']) . '/' . Lang::$txt[$_POST['cur'][$ID_SUB]];
			// The period value for paypal.
			Utils::$context['paypal_period'] = strtoupper(substr($_POST['cur'][$ID_SUB], 0, 1));
		}
		else
		{
			// Real cost...
			Utils::$context['value'] = Utils::$context['sub']['costs']['fixed'];
			Utils::$context['cost'] = sprintf(Config::$modSettings['paid_currency_symbol'], Utils::$context['value']);

			// Recur?
			preg_match('~(\d*)(\w)~', Utils::$context['sub']['real_length'], $match);
			Utils::$context['paypal_unit'] = $match[1];
			Utils::$context['paypal_period'] = $match[2];
		}

		// Setup the gateway context.
		Utils::$context['gateways'] = array();
		foreach ($gateways as $id => $gateway)
		{
			$fields = $gateways[$id]->fetchGatewayFields(Utils::$context['sub']['id'] . '+' . $memID, Utils::$context['sub'], Utils::$context['value'], $period, Config::$scripturl . '?action=profile&u=' . $memID . '&area=subscriptions&sub_id=' . Utils::$context['sub']['id'] . '&done');
			if (!empty($fields['form']))
				Utils::$context['gateways'][] = $fields;
		}

		// Bugger?!
		if (empty(Utils::$context['gateways']))
			fatal_error(Lang::$txt['paid_admin_not_setup_gateway']);

		// Now we are going to assume they want to take this out ;)
		$new_data = array(Utils::$context['sub']['id'], Utils::$context['value'], $period, 'prepay');
		if (isset(Utils::$context['current'][Utils::$context['sub']['id']]))
		{
			// What are the details like?
			$current_pending = array();
			if (Utils::$context['current'][Utils::$context['sub']['id']]['pending_details'] != '')
				$current_pending = Utils::jsonDecode(Utils::$context['current'][Utils::$context['sub']['id']]['pending_details'], true);
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
				$pending_details = Utils::jsonEncode($current_pending);

				Db::$db->query('', '
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = {int:pending_count}, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_item}
						AND id_member = {int:selected_member}',
					array(
						'pending_count' => $pending_count,
						'current_subscription_item' => Utils::$context['current'][Utils::$context['sub']['id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}
		}
		// Never had this before, lovely.
		else
		{
			$pending_details = Utils::jsonEncode(array($new_data));
			Db::$db->insert('',
				'{db_prefix}log_subscribed',
				array(
					'id_subscribe' => 'int', 'id_member' => 'int', 'status' => 'int', 'payments_pending' => 'int', 'pending_details' => 'string-65534',
					'start_time' => 'int', 'vendor_ref' => 'string-255',
				),
				array(
					Utils::$context['sub']['id'], $memID, 0, 0, $pending_details,
					time(), '',
				),
				array('id_sublog')
			);
		}

		// Change the template.
		Utils::$context['sub_template'] = 'choose_payment';

		// Quit.
		return;
	}
	else
		Utils::$context['sub_template'] = 'user_subscription';
}

?>