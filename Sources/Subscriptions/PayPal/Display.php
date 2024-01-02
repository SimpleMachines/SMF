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
use SMF\Lang;

/**
 * Class for returning available form data for this gateway
 */
class Display
{
	/**
	 * @var string Name of this payment gateway
	 */
	public $title = 'PayPal';

	/**
	 * Return the admin settings for this gateway
	 *
	 * @return array An array of settings data
	 */
	public function getGatewaySettings()
	{
		$setting_data = [
			[
				'email', 'paypal_email',
				'subtext' => Lang::$txt['paypal_email_desc'],
				'size' => 60,
			],
			[
				'email', 'paypal_additional_emails',
				'subtext' => Lang::$txt['paypal_additional_emails_desc'],
				'size' => 60,
			],
			[
				'email', 'paypal_sandbox_email',
				'subtext' => Lang::$txt['paypal_sandbox_email_desc'],
				'size' => 60,
			],
		];

		return $setting_data;
	}

	/**
	 * Is this enabled for new payments?
	 *
	 * @return bool Whether this gateway is enabled (for PayPal, whether the PayPal email is set)
	 */
	public function gatewayEnabled()
	{
		return !empty(Config::$modSettings['paypal_email']);
	}

	/**
	 * What do we want?
	 *
	 * Called from Profile-Actions.php to return a unique set of fields for the given gateway
	 * plus all the standard ones for the subscription form
	 *
	 * @param string $unique_id The unique ID of this gateway
	 * @param array $sub_data Subscription data
	 * @param int|float $value The amount of the subscription
	 * @param string $period
	 * @param string $return_url The URL to return the user to after processing the payment
	 * @return array An array of data for the form
	 */
	public function fetchGatewayFields($unique_id, $sub_data, $value, $period, $return_url)
	{
		$return_data = [
			'form' => 'https://www.' . (!empty(Config::$modSettings['paidsubs_test']) ? 'sandbox.' : '') . 'paypal.com/cgi-bin/webscr',
			'id' => 'paypal',
			'hidden' => [],
			'title' => Lang::$txt['paypal'],
			'desc' => Lang::$txt['paid_confirm_paypal'],
			'submit' => Lang::$txt['paid_paypal_order'],
			'javascript' => '',
		];

		// All the standard bits.
		$return_data['hidden']['business'] = Config::$modSettings['paypal_email'];
		$return_data['hidden']['item_name'] = $sub_data['name'] . ' ' . Lang::$txt['subscription'];
		$return_data['hidden']['item_number'] = $unique_id;
		$return_data['hidden']['currency_code'] = strtoupper(Config::$modSettings['paid_currency_code']);
		$return_data['hidden']['no_shipping'] = 1;
		$return_data['hidden']['no_note'] = 1;
		$return_data['hidden']['amount'] = $value;
		$return_data['hidden']['cmd'] = !$sub_data['repeatable'] ? '_xclick' : '_xclick-subscriptions';
		$return_data['hidden']['return'] = $return_url;
		$return_data['hidden']['a3'] = $value;
		$return_data['hidden']['src'] = 1;
		$return_data['hidden']['notify_url'] = Config::$boardurl . '/subscriptions.php';

		// If possible let's use the language we know we need.
		$return_data['hidden']['lc'] = !empty(Lang::$txt['lang_paypal']) ? Lang::$txt['lang_paypal'] : 'US';

		// Now stuff dependant on what we're doing.
		if ($sub_data['flexible']) {
			$return_data['hidden']['p3'] = 1;
			$return_data['hidden']['t3'] = strtoupper(substr($period, 0, 1));
		} else {
			preg_match('~(\d*)(\w)~', $sub_data['real_length'], $match);
			$unit = $match[1];
			$period = $match[2];

			$return_data['hidden']['p3'] = $unit;
			$return_data['hidden']['t3'] = $period;
		}

		// If it's repeatable do some javascript to respect this idea.
		if (!empty($sub_data['repeatable'])) {
			$return_data['javascript'] = '
				document.write(\'<label for="do_paypal_recur"><input type="checkbox" name="do_paypal_recur" id="do_paypal_recur" checked onclick="switchPaypalRecur();">' . Lang::$txt['paid_make_recurring'] . '</label><br>\');

				function switchPaypalRecur()
				{
					document.getElementById("paypal_cmd").value = document.getElementById("do_paypal_recur").checked ? "_xclick-subscriptions" : "_xclick";
				}';
		}

		return $return_data;
	}
}

?>