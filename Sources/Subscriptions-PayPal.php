<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

// This won't be dedicated without this - this must exist in each gateway!
// SMF Payment Gateway: paypal

if (!defined('SMF'))
	die('No direct access...');

/**
 * Class for returning available form data for this gateway
 */
class paypal_display
{
	/**
	 * Name of this payment gateway
	 */
	public $title = 'PayPal';

	/**
	 * Return the admin settings for this gateway
	 *
	 * @return array
	 */
	public function getGatewaySettings()
	{
		global $txt;

		$setting_data = array(
			array(
				'email', 'paypal_email',
				'subtext' => $txt['paypal_email_desc']
			),
			array(
				'email', 'paypal_sandbox_email',
				'subtext' => $txt['paypal_sandbox_email_desc']
			),
		);

		return $setting_data;
	}

	/**
	 * Is this enabled for new payments?
	 *
	 * @return boolean
	 */
	public function gatewayEnabled()
	{
		global $modSettings;

		return !empty($modSettings['paypal_email']);
	}

	/**
	 * What do we want?
	 *
	 * Called from Profile-Actions.php to return a unique set of fields for the given gateway
	 * plus all the standard ones for the subscription form
	 *
	 * @param type $unique_id
	 * @param type $sub_data
	 * @param type $value
	 * @param type $period
	 * @param type $return_url
	 * @return string
	 */
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

		// If possible let's use the language we know we need.
		$return_data['hidden']['lc'] = !empty($txt['lang_paypal']) ? $txt['lang_paypal'] : 'US';

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

		// If it's repeatable do some javascript to respect this idea.
		if (!empty($sub_data['repeatable']))
			$return_data['javascript'] = '
				document.write(\'<label for="do_paypal_recur"><input type="checkbox" name="do_paypal_recur" id="do_paypal_recur" checked onclick="switchPaypalRecur();" class="input_check">' . $txt['paid_make_recurring'] . '</label><br>\');

				function switchPaypalRecur()
				{
					document.getElementById("paypal_cmd").value = document.getElementById("do_paypal_recur").checked ? "_xclick-subscriptions" : "_xclick";
				}';

		return $return_data;
	}
}

/**
 * Class of functions to validate a IPN response and provide details of the payment
 */
class paypal_payment
{
	private $return_data;

	/**
	 * This function returns true/false for whether this gateway thinks the data is intended for it.
	 *
	 * @return boolean
	 */
	public function isValid()
	{
		global $modSettings;

		// Has the user set up an email address?
		if ((empty($modSettings['paidsubs_test']) && empty($modSettings['paypal_email'])) || (!empty($modSettings['paidsubs_test']) && empty($modSettings['paypal_sandbox_email'])))
			return false;
		// Check the correct transaction types are even here.
		if ((!isset($_POST['txn_type']) && !isset($_POST['payment_status'])) || (!isset($_POST['business']) && !isset($_POST['receiver_email'])))
			return false;
		// Correct email address?
		if (!isset($_POST['business']))
			$_POST['business'] = $_POST['receiver_email'];

		// Are we testing?
		if (!empty($modSettings['paidsubs_test']) && $modSettings['paypal_sandbox_email'] !== $_POST['business'] && (empty($modSettings['paypal_additional_emails']) || !in_array($_POST['business'], explode(',', $modSettings['paypal_additional_emails']))))
			return false;
		elseif ($modSettings['paypal_email'] !== $_POST['business'] && (empty($modSettings['paypal_additional_emails']) || !in_array($_POST['business'], explode(',', $modSettings['paypal_additional_emails']))))
			return false;
		return true;
	}

	/**
	 * Post the IPN data received back to paypal for validation
	 * Sends the complete unaltered message back to PayPal. The message must contain the same fields
	 * in the same order and be encoded in the same way as the original message
	 * PayPal will respond back with a single word, which is either VERIFIED if the message originated with PayPal or INVALID
	 *
	 * If valid returns the subscription and member IDs we are going to process if it passes
	 *
	 * @return string
	 */
	public function precheck()
	{
		global $modSettings, $txt;

		// Put this to some default value.
		if (!isset($_POST['txn_type']))
			$_POST['txn_type'] = '';

		// Build the request string - starting with the minimum requirement.
		$requestString = 'cmd=_notify-validate';

		// Now my dear, add all the posted bits in the order we got them
		foreach ($_POST as $k => $v)
			$requestString .= '&' . $k . '=' . urlencode($v);

		// Can we use curl?
		if (function_exists('curl_init') && $curl = curl_init((!empty($modSettings['paidsubs_test']) ? 'https://www.sandbox.' : 'http://www.') . 'paypal.com/cgi-bin/webscr'))
		{
			// Set the post data.
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDSIZE, 0);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $requestString);

			// Set up the headers so paypal will accept the post
			curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Host: www.' . (!empty($modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com',
				'Connection: close'
			));

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
			$header = 'POST /cgi-bin/webscr HTTP/1.1' . "\r\n";
			$header .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
			$header .= 'Host: www.' . (!empty($modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com' . "\r\n";
			$header .= 'Content-Length: ' . strlen ($requestString) . "\r\n";
			$header .= 'Connection: close' . "\r\n\r\n";

			// Open the connection.
			if (!empty($modSettings['paidsubs_test']))
				$fp = fsockopen('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
			else
				$fp = fsockopen('www.paypal.com', 80, $errno, $errstr, 30);

			// Did it work?
			if (!$fp)
				generateSubscriptionError($txt['paypal_could_not_connect']);

			// Put the data to the port.
			fputs($fp, $header . $requestString);

			// Get the data back...
			while (!feof($fp))
			{
				$this->return_data = fgets($fp, 1024);
				if (strcmp(trim($this->return_data), 'VERIFIED') === 0)
					break;
			}

			// Clean up.
			fclose($fp);
		}

		// If this isn't verified then give up...
		if (strcmp(trim($this->return_data), 'VERIFIED') !== 0)
			exit;

		// Check that this is intended for us.
		if ($modSettings['paypal_email'] !== $_POST['business'] && (empty($modSettings['paypal_additional_emails']) || !in_array($_POST['business'], explode(',', $modSettings['paypal_additional_emails']))))
			exit;

		// Is this a subscription - and if so is it a secondary payment that we need to process?
		// If so, make sure we get it in the expected format. Seems PayPal sometimes sends it without urlencoding.
		if (!empty($_POST['item_number']) && strpos($_POST['item_number'], ' ') !== false)
			$_POST['item_number'] = str_replace(' ', '+', $_POST['item_number']);
		if ($this->isSubscription() && (empty($_POST['item_number']) || strpos($_POST['item_number'], '+') === false))
			// Calculate the subscription it relates to!
			$this->_findSubscription();

		// Verify the currency!
		if (strtolower($_POST['mc_currency']) !== strtolower($modSettings['paid_currency_code']))
			exit;

		// Can't exist if it doesn't contain anything.
		if (empty($_POST['item_number']))
			exit;

		// Return the id_sub and id_member
		return explode('+', $_POST['item_number']);
	}

	/**
	 * Is this a refund?
	 *
	 * @return boolean
	 */
	public function isRefund()
	{
		if ($_POST['payment_status'] === 'Refunded' || $_POST['payment_status'] === 'Reversed' || $_POST['txn_type'] === 'Refunded' || ($_POST['txn_type'] === 'reversal' && $_POST['payment_status'] === 'Completed'))
			return true;
		else
			return false;
	}

	/**
	 * Is this a subscription?
	 *
	 * @return boolean
	 */
	public function isSubscription()
	{
		if (substr($_POST['txn_type'], 0, 14) === 'subscr_payment' && $_POST['payment_status'] === 'Completed')
			return true;
		else
			return false;
	}

	/**
	 * Is this a normal payment?
	 *
	 * @return boolean
	 */
	public function isPayment()
	{
		if ($_POST['payment_status'] === 'Completed' && $_POST['txn_type'] === 'web_accept')
			return true;
		else
			return false;
	}

	/**
	 * Is this a cancellation?
	 *
	 * @return boolean
	 */
	public function isCancellation()
	{
		// subscr_cancel is sent when the user cancels, subscr_eot is sent when the subscription reaches final payment
		// Neither require us to *do* anything as per performCancel().
		// subscr_eot, if sent, indicates an end of payments term.
		if (substr($_POST['txn_type'], 0, 13) === 'subscr_cancel' || substr($_POST['txn_type'], 0, 10) === 'subscr_eot')
			return true;
		else
			return false;
	}

	/**
	 * Things to do in the event of a cancellation
	 *
	 * @return void
	 */
	public function performCancel($subscription_id, $member_id, $subscription_info)
	{
		// PayPal doesn't require SMF to notify it every time the subscription is up for renewal.
		// A cancellation should not cause the user to be immediately dropped from their subscription, but
		// let it expire normally. Some systems require taking action in the database to deal with this, but
		// PayPal does not, so we actually just do nothing. But this is a nice prototype/example just in case.
	}

	/**
	 * How much was paid?
	 *
	 * @return float
	 */
	public function getCost()
	{
		return (isset($_POST['tax']) ? $_POST['tax'] : 0) + $_POST['mc_gross'];
	}

	/**
	 * Record the transaction reference and exit
	 *
	 */
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

	/**
	 * A private function to find out the subscription details.
	 *
	 * @return boolean
	 */
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
				if ($smcFunc['db_num_rows']($request) === 0)
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