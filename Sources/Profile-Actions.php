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
use SMF\Actions\Admin\Subscriptions;
use SMF\Db\DatabaseApi as Db;
use SMF\PersonalMessage\PM;

if (!defined('SMF'))
	die('No direct access...');

class_exists('\\SMF\\Actions\\Profile\\IssueWarning');
class_exists('\\SMF\\Actions\\Profile\\PaidSubs');

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

?>