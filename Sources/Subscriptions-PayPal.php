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

// This won't be dedicated without this - this must exist in each gateway!
// SMF Payment Gateway: paypal

if (!defined('SMF'))
	die('Hacking attempt...');

class paypal_display
{
	public $title = 'PayPal';

	public function getGatewaySettings()
	{
		global $txt;

		$setting_data = array(
			array('text', 'paypal_email', 'subtext' => $txt['paypal_email_desc']),
		);

		return $setting_data;
	}

	// Is this enabled for new payments?
	public function gatewayEnabled()
	{
		global $modSettings;

		return !empty($modSettings['paypal_email']);
	}

	// What do we want?
	public function fetchGatewayFields($unique_id, $sub_data, $value, $period, $return_url)
	{
		global $modSettings, $txt, $boardurl;

		$return_data = array(
			'form' => 'https://www.' . (!empty($modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com/cgi-bin/webscr',
			'id' => 'paypal',
			'hidden' => array(),
			'title' => $txt['paypal'],
			'desc' => $txt['paid_confirm_paypal'],
			'submit' => $txt['paid_paypal_order'],
			'javascript' => '',
		);

		// All the standard bits.
		$return_data['hidden']['business'] = $modSettings['paypal_email'];
		$return_data['hidden']['item_name'] = $sub_data['name'] . ' ' . $txt['subscription'];
		$return_data['hidden']['item_number'] = $unique_id;
		$return_data['hidden']['currency_code'] = strtoupper($modSettings['paid_currency_code']);
		$return_data['hidden']['no_shipping'] = 1;
		$return_data['hidden']['no_note'] = 1;
		$return_data['hidden']['amount'] = $value;
		$return_data['hidden']['cmd'] = !$sub_data['repeatable'] ? '_xclick' : '_xclick-subscriptions';
		$return_data['hidden']['return'] = $return_url;
		$return_data['hidden']['a3'] = $value;
		$return_data['hidden']['src'] = 1;
		$return_data['hidden']['notify_url'] = $boardurl . '/subscriptions.php';

		// Now stuff dependant on what we're doing.
		if ($sub_data['flexible'])
		{
			$return_data['hidden']['p3'] = 1;
			$return_data['hidden']['t3'] = strtoupper(substr($period, 0, 1));
		}
		else
		{
			preg_match('~(\d*)(\w)~', $sub_data['real_length'], $match);
			$unit = $match[1];
			$period = $match[2];

			$return_data['hidden']['p3'] = $unit;
			$return_data['hidden']['t3'] = $period;
		}

		// If it's repeatable do soem javascript to respect this idea.
		if (!empty($sub_data['repeatable']))
			$return_data['javascript'] = '
				document.write(\'<label for="do_paypal_recur"><input type="checkbox" name="do_paypal_recur" id="do_paypal_recur" checked="checked" onclick="switchPaypalRecur();" class="input_check" />' . $txt['paid_make_recurring'] . '</label><br />\');

				function switchPaypalRecur()
				{
					document.getElementById("paypal_cmd").value = document.getElementById("do_paypal_recur").checked ? "_xclick-subscriptions" : "_xclick";
				}';

		return $return_data;
	}
}

class paypal_payment
{
	private $return_data;

	// This function returns true/false for whether this gateway thinks the data is intended for it.
	public function isValid()
	{
		global $modSettings;

		// Has the user set up an email address?
		if (empty($modSettings['paypal_email']))
			return false;
		// Check the correct transaction types are even here.
		if ((!isset($_POST['txn_type']) && !isset($_POST['payment_status'])) || (!isset($_POST['business']) && !isset($_POST['receiver_email'])))
			return false;
		// Correct email address?
		if (!isset($_POST['business']))
			$_POST['business'] = $_POST['receiver_email'];
		if ($modSettings['paypal_email'] != $_POST['business'] && (empty($modSettings['paypal_additional_emails']) || !in_array($_POST['business'], explode(',', $modSettings['paypal_additional_emails']))))
			return false;
		return true;
	}

	// Validate all the data was valid.
	public function precheck()
	{
		global $modSettings, $txt;

		// Put this to some default value.
		if (!isset($_POST['txn_type']))
			$_POST['txn_type'] = '';

		// Build the request string - starting with the minimum requirement.
		$requestString = 'cmd=_notify-validate';

		// Now my dear, add all the posted bits.
		foreach ($_POST as $k => $v)
			$requestString .= '&' . $k . '=' . urlencode($v);

		// Can we use curl?
		if (function_exists('curl_init') && $curl = curl_init('http://www.', !empty($modSettings['paidsubs_test']) ? 'sandbox.' : '', 'paypal.com/cgi-bin/webscr'))
		{
			// Set the post data.
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDSIZE, 0);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $requestString);

			// Fetch the data returned as a string.
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			// Fetch the data.
			$this->return_data = curl_exec($curl);

			// Close the session.
			curl_close($curl);
		}
		// Otherwise good old HTTP.
		else
		{
			// Setup the headers.
			$header = 'POST /cgi-bin/webscr HTTP/1.0' . "\r\n";
			$header .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
			$header .= 'Content-Length: ' . strlen ($requestString) . "\r\n\r\n";

			// Open the connection.
			$fp = fsockopen('www.' . (!empty($modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com', 80, $errno, $errstr, 30);

			// Did it work?
			if (!$fp)
				generateSubscriptionError($txt['paypal_could_not_connect']);

			// Put the data to the port.
			fputs($fp, $header . $requestString);

			// Get the data back...
			while (!feof($fp))
			{
				$this->return_data = fgets($fp, 1024);
				if (strcmp($this->return_data, 'VERIFIED') == 0)
					break;
			}

			// Clean up.
			fclose($fp);
		}

		// If this isn't verified then give up...
		// !! This contained a comment "send an email", but we don't appear to send any?
		if (strcmp($this->return_data, 'VERIFIED') != 0)
			exit;

		// Check that this is intended for us.
		if ($modSettings['paypal_email'] != $_POST['business'] && (empty($modSettings['paypal_additional_emails']) || !in_array($_POST['business'], explode(',', $modSettings['paypal_additional_emails']))))
			exit;

		// Is this a subscription - and if so it's it a secondary payment that we need to process?
		if ($this->isSubscription() && (empty($_POST['item_number']) || strpos($_POST['item_number'], '+') === false))
			// Calculate the subscription it relates to!
			$this->_findSubscription();

		// Verify the currency!
		if (strtolower($_POST['mc_currency']) != $modSettings['paid_currency_code'])
			exit;

		// Can't exist if it doesn't contain anything.
		if (empty($_POST['item_number']))
			exit;

		// Return the id_sub and id_member
		return explode('+', $_POST['item_number']);
	}

	// Is this a refund?
	public function isRefund()
	{
		if ($_POST['payment_status'] == 'Refunded' || $_POST['payment_status'] == 'Reversed' || $_POST['txn_type'] == 'Refunded' || ($_POST['txn_type'] == 'reversal' && $_POST['payment_status'] == 'Completed'))
			return true;
		else
			return false;
	}

	// Is this a subscription?
	public function isSubscription()
	{
		if (substr($_POST['txn_type'], 0, 14) == 'subscr_payment')
			return true;
		else
			return false;
	}

	// Is this a normal payment?
	public function isPayment()
	{
		if ($_POST['payment_status'] == 'Completed' && $_POST['txn_type'] == 'web_accept')
			return true;
		else
			return false;
	}

	// How much was paid?
	public function getCost()
	{
		return $_POST['tax'] + $_POST['mc_gross'];
	}

	// exit.
	public function close()
	{
		global $smcFunc, $subscription_id;

		// If it's a subscription record the reference.
		if ($_POST['txn_type'] == 'subscr_payment' && !empty($_POST['subscr_id']))
		{
			$_POST['subscr_id'] = $_POST['subscr_id'];
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_subscribed
				SET vendor_ref = {string:vendor_ref}
				WHERE id_sublog = {int:current_subscription}',
				array(
					'current_subscription' => $subscription_id,
					'vendor_ref' => $_POST['subscr_id'],
				)
			);
		}

		exit();
	}

	// A private function to find out the subscription details.
	private function _findSubscription()
	{
		global $smcFunc;

		// Assume we have this?
		if (empty($_POST['subscr_id']))
			return false;

		// Do we have this in the database?
		$request = $smcFunc['db_query']('', '
			SELECT id_member, id_subscribe
			FROM {db_prefix}log_subscribed
			WHERE vendor_ref = {string:vendor_ref}
			LIMIT 1',
			array(
				'vendor_ref' => $_POST['subscr_id'],
			)
		);
		// No joy?
		if ($smcFunc['db_num_rows']($request) == 0)
		{
			// Can we identify them by email?
			if (!empty($_POST['payer_email']))
			{
				$smcFunc['db_free_result']($request);
				$request = $smcFunc['db_query']('', '
					SELECT ls.id_member, ls.id_subscribe
					FROM {db_prefix}log_subscribed AS ls
						INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
					WHERE mem.email_address = {string:payer_email}
					LIMIT 1',
					array(
						'payer_email' => $_POST['payer_email'],
					)
				);
				if ($smcFunc['db_num_rows']($request) == 0)
					return false;
			}
			else
				return false;
		}
		list ($member_id, $subscription_id) = $smcFunc['db_fetch_row']($request);
		$_POST['item_number'] = $member_id . '+' . $subscription_id;
		$smcFunc['db_free_result']($request);
	}
}

?>