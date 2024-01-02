<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Subscriptions\PayPal;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;

/**
 * Class of functions to validate a IPN response and provide details of the payment
 */
class Payment
{
	/**
	 * @var string The data to return
	 */
	private $return_data;

	/**
	 * This function returns true/false for whether this gateway thinks the data is intended for it.
	 *
	 * @return bool Whether this gateway things the data is valid
	 */
	public function isValid()
	{
		// Has the user set up an email address?
		if ((empty(Config::$modSettings['paidsubs_test']) && empty(Config::$modSettings['paypal_email'])) || (!empty(Config::$modSettings['paidsubs_test']) && empty(Config::$modSettings['paypal_sandbox_email']))) {
			return false;
		}

		// Check the correct transaction types are even here.
		if ((!isset($_POST['txn_type']) && !isset($_POST['payment_status'])) || (!isset($_POST['business']) && !isset($_POST['receiver_email']))) {
			return false;
		}

		// Correct email address?
		if (!isset($_POST['business'])) {
			$_POST['business'] = $_POST['receiver_email'];
		}

		// Are we testing?
		if (!empty(Config::$modSettings['paidsubs_test']) && strtolower(Config::$modSettings['paypal_sandbox_email']) != strtolower($_POST['business']) && (empty(Config::$modSettings['paypal_additional_emails']) || !in_array(strtolower($_POST['business']), explode(',', strtolower(Config::$modSettings['paypal_additional_emails']))))) {
			return false;
		}

		return !(strtolower(Config::$modSettings['paypal_email']) != strtolower($_POST['business']) && (empty(Config::$modSettings['paypal_additional_emails']) || !in_array(strtolower($_POST['business']), explode(',', Config::$modSettings['paypal_additional_emails']))));
	}

	/**
	 * Post the IPN data received back to paypal for validation
	 * Sends the complete unaltered message back to PayPal. The message must contain the same fields
	 * in the same order and be encoded in the same way as the original message
	 * PayPal will respond back with a single word, which is either VERIFIED if the message originated with PayPal or INVALID
	 *
	 * If valid returns the subscription and member IDs we are going to process if it passes
	 *
	 * @return string A string containing the subscription ID and member ID, separated by a +
	 */
	public function precheck()
	{
		// Put this to some default value.
		if (!isset($_POST['txn_type'])) {
			$_POST['txn_type'] = '';
		}

		// Build the request string - starting with the minimum requirement.
		$requestString = 'cmd=_notify-validate';

		// Now my dear, add all the posted bits in the order we got them
		foreach ($_POST as $k => $v) {
			$requestString .= '&' . $k . '=' . urlencode($v);
		}

		// Can we use curl?
		if (function_exists('curl_init') && $curl = curl_init((!empty(Config::$modSettings['paidsubs_test']) ? 'https://www.sandbox.' : 'https://www.') . 'paypal.com/cgi-bin/webscr')) {
			// Set the post data.
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $requestString);

			// Set up the headers so paypal will accept the post
			curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($curl, CURLOPT_HTTPHEADER, [
				'Host: www.' . (!empty(Config::$modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com',
				'Connection: close',
			]);

			// Fetch the data returned as a string.
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			// Fetch the data.
			$this->return_data = curl_exec($curl);

			// Close the session.
			curl_close($curl);
		}
		// Otherwise good old HTTP.
		else {
			// Setup the headers.
			$header = 'POST /cgi-bin/webscr HTTP/1.1' . "\r\n";
			$header .= 'content-type: application/x-www-form-urlencoded' . "\r\n";
			$header .= 'Host: www.' . (!empty(Config::$modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com' . "\r\n";
			$header .= 'content-length: ' . strlen($requestString) . "\r\n";
			$header .= 'connection: close' . "\r\n\r\n";

			// Open the connection.
			if (!empty(Config::$modSettings['paidsubs_test'])) {
				$fp = fsockopen('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
			} else {
				$fp = fsockopen('www.paypal.com', 80, $errno, $errstr, 30);
			}

			// Did it work?
			if (!$fp) {
				generateSubscriptionError(Lang::$txt['paypal_could_not_connect']);
			}

			// Put the data to the port.
			fputs($fp, $header . $requestString);

			// Get the data back...
			while (!feof($fp)) {
				$this->return_data = fgets($fp, 1024);

				if (strcmp(trim($this->return_data), 'VERIFIED') === 0) {
					break;
				}
			}

			// Clean up.
			fclose($fp);
		}

		// If this isn't verified then give up...
		if (strcmp(trim($this->return_data), 'VERIFIED') !== 0) {
			exit;
		}

		// Check that this is intended for us.
		if (strtolower(Config::$modSettings['paypal_email']) != strtolower($_POST['business']) && (empty(Config::$modSettings['paypal_additional_emails']) || !in_array(strtolower($_POST['business']), explode(',', strtolower(Config::$modSettings['paypal_additional_emails']))))) {
			exit;
		}

		// Is this a subscription - and if so is it a secondary payment that we need to process?
		// If so, make sure we get it in the expected format. Seems PayPal sometimes sends it without urlencoding.
		if (!empty($_POST['item_number']) && strpos($_POST['item_number'], ' ') !== false) {
			$_POST['item_number'] = str_replace(' ', '+', $_POST['item_number']);
		}

		if ($this->isSubscription() && (empty($_POST['item_number']) || strpos($_POST['item_number'], '+') === false)) {
			// Calculate the subscription it relates to!
			$this->_findSubscription();
		}

		// Verify the currency!
		if (strtolower($_POST['mc_currency']) !== strtolower(Config::$modSettings['paid_currency_code'])) {
			exit;
		}

		// Can't exist if it doesn't contain anything.
		if (empty($_POST['item_number'])) {
			exit;
		}

		// Return the id_sub and id_member
		return explode('+', $_POST['item_number']);
	}

	/**
	 * Is this a refund?
	 *
	 * @return bool Whether this is a refund
	 */
	public function isRefund()
	{
		return (bool) ($_POST['payment_status'] === 'Refunded' || $_POST['payment_status'] === 'Reversed' || $_POST['txn_type'] === 'Refunded' || ($_POST['txn_type'] === 'reversal' && $_POST['payment_status'] === 'Completed'));
	}

	/**
	 * Is this a subscription?
	 *
	 * @return bool Whether this is a subscription
	 */
	public function isSubscription()
	{
		return (bool) (substr($_POST['txn_type'], 0, 14) === 'subscr_payment' && $_POST['payment_status'] === 'Completed');
	}

	/**
	 * Is this a normal payment?
	 *
	 * @return bool Whether this is a normal payment
	 */
	public function isPayment()
	{
		return (bool) ($_POST['payment_status'] === 'Completed' && $_POST['txn_type'] === 'web_accept');
	}

	/**
	 * Is this a cancellation?
	 *
	 * @return bool Whether this is a cancellation
	 */
	public function isCancellation()
	{
		// subscr_cancel is sent when the user cancels, subscr_eot is sent when the subscription reaches final payment
		// Neither require us to *do* anything as per performCancel().
		// subscr_eot, if sent, indicates an end of payments term.
		return (bool) (substr($_POST['txn_type'], 0, 13) === 'subscr_cancel' || substr($_POST['txn_type'], 0, 10) === 'subscr_eot');
	}

	/**
	 * Things to do in the event of a cancellation
	 *
	 * @param string $subscription_id
	 * @param int $member_id
	 * @param array $subscription_info
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
	 * @return float The amount paid
	 */
	public function getCost()
	{
		return ($_POST['tax'] ?? 0) + $_POST['mc_gross'];
	}

	/**
	 * Record the transaction reference to finish up.
	 *
	 */
	public function close()
	{
		global $subscription_id;

		// If it's a subscription record the reference.
		if ($_POST['txn_type'] == 'subscr_payment' && !empty($_POST['subscr_id'])) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_subscribed
				SET vendor_ref = {string:vendor_ref}
				WHERE id_sublog = {int:current_subscription}',
				[
					'current_subscription' => $subscription_id,
					'vendor_ref' => $_POST['subscr_id'],
				],
			);
		}
	}

	/**
	 * A private function to find out the subscription details.
	 *
	 * @return bool|void False on failure, otherwise just sets $_POST['item_number']
	 */
	private function _findSubscription()
	{
		// Assume we have this?
		if (empty($_POST['subscr_id'])) {
			return false;
		}

		// Do we have this in the database?
		$request = Db::$db->query(
			'',
			'SELECT id_member, id_subscribe
			FROM {db_prefix}log_subscribed
			WHERE vendor_ref = {string:vendor_ref}
			LIMIT 1',
			[
				'vendor_ref' => $_POST['subscr_id'],
			],
		);

		// No joy?
		if (Db::$db->num_rows($request) == 0) {
			// Can we identify them by email?
			if (!empty($_POST['payer_email'])) {
				Db::$db->free_result($request);
				$request = Db::$db->query(
					'',
					'SELECT ls.id_member, ls.id_subscribe
					FROM {db_prefix}log_subscribed AS ls
						INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ls.id_member)
					WHERE mem.email_address = {string:payer_email}
					LIMIT 1',
					[
						'payer_email' => $_POST['payer_email'],
					],
				);

				if (Db::$db->num_rows($request) === 0) {
					return false;
				}
			} else {
				return false;
			}
		}
		list($member_id, $subscription_id) = Db::$db->fetch_row($request);
		$_POST['item_number'] = $member_id . '+' . $subscription_id;
		Db::$db->free_result($request);
	}
}

?>