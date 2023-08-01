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
	Subscriptions::getSubs();
	Utils::$context['member']['id'] = $memID;

	// Remove any invalid ones.
	foreach (Subscriptions::$all as $id => $sub)
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
			unset(Subscriptions::$all[$id]);
		else
		{
			Subscriptions::$all[$id]['member'] = 0;
			Subscriptions::$all[$id]['subscribed'] = false;
			Subscriptions::$all[$id]['costs'] = $cost_array;
		}
	}

	// Work out what gateways are enabled.
	$gateways = Subscriptions::loadPaymentGateways();
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
		if (!isset(Subscriptions::$all[$row['id_subscribe']]))
			continue;

		Utils::$context['current'][$row['id_subscribe']] = array(
			'id' => $row['id_sublog'],
			'sub_id' => $row['id_subscribe'],
			'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
			'name' => Subscriptions::$all[$row['id_subscribe']]['name'],
			'start' => timeformat($row['start_time'], false),
			'end' => $row['end_time'] == 0 ? Lang::$txt['not_applicable'] : timeformat($row['end_time'], false),
			'pending_details' => $row['pending_details'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? Lang::$txt['paid_pending'] : Lang::$txt['paid_finished']) : Lang::$txt['paid_active'],
		);

		if ($row['status'] == 1)
			Subscriptions::$all[$row['id_subscribe']]['subscribed'] = true;
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

		if (!isset(Subscriptions::$all[$ID_SUB]) || Subscriptions::$all[$ID_SUB]['active'] == 0)
			fatal_lang_error('paid_sub_not_active');

		// Simplify...
		Utils::$context['sub'] = Subscriptions::$all[$ID_SUB];
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