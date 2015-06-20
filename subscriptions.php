<?php

/**
 * This file is the file which all subscription gateways should call
 * when a payment has been received - it sorts out the user status.
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

// Start things rolling by getting SMF alive...
$ssi_guest_access = true;
if (!file_exists(dirname(__FILE__) . '/SSI.php'))
	die('Cannot find SSI.php');

require_once(dirname(__FILE__) . '/SSI.php');
require_once($sourcedir . '/ManagePaid.php');

// For any admin emailing.
require_once($sourcedir . '/Subs-Admin.php');

loadLanguage('ManagePaid');

// If there's literally nothing coming in, let's take flight!
if (empty($_POST))
{
	header('Content-Type: text/html; charset=' . (empty($modSettings['global_character_set']) ? (empty($txt['lang_character_set']) ? 'ISO-8859-1' : $txt['lang_character_set']) : $modSettings['global_character_set']));
	die($txt['paid_no_data']);
}

// I assume we're even active?
if (empty($modSettings['paid_enabled']))
	exit;

// If we have some custom people who find out about problems load them here.
$notify_users = array();
if (!empty($modSettings['paid_email_to']))
{
	foreach (explode(',', $modSettings['paid_email_to']) as $email)
		$notify_users[] = array(
			'email' => $email,
			'name' => $txt['who_member'],
			'id' => 0,
		);
}

// We need to see whether we can find the correct payment gateway,
// we'll going to go through all our gateway scripts and find out
// if they are happy with what we have.
$txnType = '';
$gatewayHandles = loadPaymentGateways();
foreach ($gatewayHandles as $gateway)
{
	$gatewayClass = new $gateway['payment_class']();
	if ($gatewayClass->isValid())
	{
		$txnType = $gateway['code'];
		break;
	}
}

if (empty($txnType))
	generateSubscriptionError($txt['paid_unknown_transaction_type']);

// Get the subscription and member ID amoungst others...
@list($subscription_id, $member_id) = $gatewayClass->precheck();

// Integer these just in case.
$subscription_id = (int) $subscription_id;
$member_id = (int) $member_id;

// This would be bad...
if (empty($member_id))
	generateSubscriptionError($txt['paid_empty_member']);

// Verify the member.
$request = $smcFunc['db_query']('', '
	SELECT id_member, member_name, real_name, email_address
	FROM {db_prefix}members
	WHERE id_member = {int:current_member}',
	array(
		'current_member' => $member_id,
	)
);
// Didn't find them?
if ($smcFunc['db_num_rows']($request) === 0)
	generateSubscriptionError(sprintf($txt['paid_could_not_find_member'], $member_id));
$member_info = $smcFunc['db_fetch_assoc']($request);
$smcFunc['db_free_result']($request);

// Get the subscription details.
$request = $smcFunc['db_query']('', '
	SELECT cost, length, name
	FROM {db_prefix}subscriptions
	WHERE id_subscribe = {int:current_subscription}',
	array(
		'current_subscription' => $subscription_id,
	)
);

// Didn't find it?
if ($smcFunc['db_num_rows']($request) === 0)
	generateSubscriptionError(sprintf($txt['paid_count_not_find_subscription'], $member_id, $subscription_id));

$subscription_info = $smcFunc['db_fetch_assoc']($request);
$smcFunc['db_free_result']($request);

// We wish to check the pending payments to make sure we are expecting this.
$request = $smcFunc['db_query']('', '
	SELECT id_sublog, payments_pending, pending_details, end_time
	FROM {db_prefix}log_subscribed
	WHERE id_subscribe = {int:current_subscription}
		AND id_member = {int:current_member}
	LIMIT 1',
	array(
		'current_subscription' => $subscription_id,
		'current_member' => $member_id,
	)
);
if ($smcFunc['db_num_rows']($request) === 0)
	generateSubscriptionError(sprintf($txt['paid_count_not_find_subscription_log'], $member_id, $subscription_id));
$subscription_info += $smcFunc['db_fetch_assoc']($request);
$smcFunc['db_free_result']($request);

// Is this a refund etc?
if ($gatewayClass->isRefund())
{
	// If the end time subtracted by current time, is not greater
	// than the duration (ie length of subscription), then we close it.
	if ($subscription_info['end_time'] - time() < $subscription_info['length'])
	{
		// Delete user subscription.
		removeSubscription($subscription_id, $member_id);
		$subscription_act = time();
		$status = 0;
	}
	else
	{
		loadSubscriptions();
		$subscription_act = $subscription_info['end_time'] - $context['subscriptions'][$subscription_id]['num_length'];
		$status = 1;
	}

	// Mark it as complete so we have a record.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_subscribed
		SET end_time = {int:current_time}
		WHERE id_subscribe = {int:current_subscription}
			AND id_member = {int:current_member}
			AND status = {int:status}',
		array(
			'current_time' => $subscription_act,
			'current_subscription' => $subscription_id,
			'current_member' => $member_id,
			'status' => $status,
		)
	);

	// Receipt?
	if (!empty($modSettings['paid_email']) && $modSettings['paid_email'] == 2)
	{
		$replacements = array(
			'NAME' => $subscription_info['name'],
			'REFUNDNAME' => $member_info['member_name'],
			'REFUNDUSER' => $member_info['real_name'],
			'PROFILELINK' => $scripturl . '?action=profile;u=' . $member_id,
			'DATE' => timeformat(time(), false),
		);

		emailAdmins('paid_subscription_refund', $replacements, $notify_users);
	}

}
// Otherwise is it what we want, a purchase?
elseif ($gatewayClass->isPayment() || $gatewayClass->isSubscription())
{
	$cost = unserialize($subscription_info['cost']);
	$total_cost = $gatewayClass->getCost();
	$notify = false;

	// For one off's we want to only capture them once!
	if (!$gatewayClass->isSubscription())
	{
		$real_details = @unserialize($subscription_info['pending_details']);
		if (empty($real_details))
			generateSubscriptionError(sprintf($txt['paid_count_not_find_outstanding_payment'], $member_id, $subscription_id));

		// Now we just try to find anything pending.
		// We don't really care which it is as security happens later.
		foreach ($real_details as $id => $detail)
		{
			unset($real_details[$id]);
			if ($detail[3] == 'payback' && $subscription_info['payments_pending'])
				$subscription_info['payments_pending']--;
			break;
		}

		$subscription_info['pending_details'] = empty($real_details) ? '' : serialize($real_details);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_subscribed
			SET payments_pending = {int:payments_pending}, pending_details = {string:pending_details}
			WHERE id_sublog = {int:current_subscription_item}',
			array(
				'payments_pending' => $subscription_info['payments_pending'],
				'current_subscription_item' => $subscription_info['id_sublog'],
				'pending_details' => $subscription_info['pending_details'],
			)
		);
	}

	// Is this flexible?
	if ($subscription_info['length'] == 'F')
	{
		$found_duration = 0;

		// This is a little harder, can we find the right duration?
		foreach ($cost as $duration => $value)
		{
			if ($duration == 'fixed')
				continue;
			elseif ((float) $value == (float) $total_cost)
				$found_duration = strtoupper(substr($duration, 0, 1));
		}

		// If we have the duration then we're done.
		if ($found_duration !== 0)
		{
			$notify = true;
			addSubscription($subscription_id, $member_id, $found_duration);
		}
	}
	else
	{
		$actual_cost = $cost['fixed'];

		// It must be at least the right amount.
		if ($total_cost != 0 && $total_cost >= $actual_cost)
		{
			// Add the subscription.
			$notify = true;
			addSubscription($subscription_id, $member_id);
		}
	}

	// Send a receipt?
	if (!empty($modSettings['paid_email']) && $modSettings['paid_email'] == 2 && $notify)
	{
		$replacements = array(
			'NAME' => $subscription_info['name'],
			'SUBNAME' => $member_info['member_name'],
			'SUBUSER' => $member_info['real_name'],
			'SUBEMAIL' => $member_info['email_address'],
			'PRICE' => sprintf($modSettings['paid_currency_symbol'], $total_cost),
			'PROFILELINK' => $scripturl . '?action=profile;u=' . $member_id,
			'DATE' => timeformat(time(), false),
		);

		emailAdmins('paid_subscription_new', $replacements, $notify_users);
	}
}
// Maybe they're cancelling. Some subscriptions may require actively doing something, but PayPal doesn't, for example.
elseif ($gatewayClass->isCancellation())
{
	if (method_exists($gatewayClass, 'performCancel'))
		$gatewayClass->performCancel($subscription_id, $member_id, $subscription_info);
}
else
{
	// Some other "valid" transaction such as:
	//
	// subscr_signup: This IPN response (txn_type) is sent only the first time the user signs up for a subscription.
	// It then does not fire in any event later. This response is received somewhere before or after the first payment of
	// subscription is received (txn_type=subscr_payment) which is what we do process
	//
	// Should we log any of these ...
}

// In case we have anything specific to do.
$gatewayClass->close();

/**
 * Log an error then exit
 *
 * @param string $text
 */
function generateSubscriptionError($text)
{
	global $modSettings, $notify_users, $smcFunc;

	// Send an email?
	if (!empty($modSettings['paid_email']))
	{
		$replacements = array(
			'ERROR' => $text,
		);

		emailAdmins('paid_subscription_error', $replacements, $notify_users);
	}

	// Maybe we can try to give them the post data?
	if (!empty($_POST))
	{
		foreach ($_POST as $key => $val)
			$text .= '<br>' . $smcFunc['htmlspecialchars']($key) . ': ' . $smcFunc['htmlspecialchars']($val);
	}

	// Then just log and die.
	log_error($text, 'paidsubs');

	exit;
}

?>