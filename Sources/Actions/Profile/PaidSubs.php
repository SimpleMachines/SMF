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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\Actions\Admin\Subscriptions;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Profile;
use SMF\Theme;
use SMF\Time;
use SMF\Utils;

/**
 * Class for doing all the paid subscription stuff - kinda.
 */
class PaidSubs implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'subscriptions',
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// Load the paid template anyway.
		Theme::loadTemplate('ManagePaid');
		Lang::load('ManagePaid');

		// Load all of the subscriptions.
		Subscriptions::getSubs();

		// Remove any invalid ones.
		foreach (Subscriptions::$all as $id => $sub) {
			// Work out the costs.
			$costs = Utils::jsonDecode($sub['real_cost'], true);

			$cost_array = [];

			if ($sub['real_length'] == 'F') {
				foreach ($costs as $duration => $cost) {
					if ($cost != 0) {
						$cost_array[$duration] = $cost;
					}
				}
			} else {
				$cost_array['fixed'] = $costs['fixed'];
			}

			if (empty($cost_array)) {
				unset(Subscriptions::$all[$id]);
			} else {
				Subscriptions::$all[$id]['member'] = 0;
				Subscriptions::$all[$id]['subscribed'] = false;
				Subscriptions::$all[$id]['costs'] = $cost_array;
			}
		}

		// Work out what gateways are enabled.
		$gateways = Subscriptions::loadPaymentGateways();

		foreach ($gateways as $id => $gateway) {
			$gateways[$id] = new $gateway['display_class']();

			if (!$gateways[$id]->gatewayEnabled()) {
				unset($gateways[$id]);
			}
		}

		// No gateways yet?
		if (empty($gateways)) {
			ErrorHandler::fatal(Lang::$txt['paid_admin_not_setup_gateway']);
		}

		// Get the current subscriptions.
		Utils::$context['current'] = [];

		$request = Db::$db->query(
			'',
			'SELECT id_sublog, id_subscribe, start_time, end_time, status, payments_pending, pending_details
			FROM {db_prefix}log_subscribed
			WHERE id_member = {int:selected_member}',
			[
				'selected_member' => Profile::$member->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// The subscription must exist!
			if (!isset(Subscriptions::$all[$row['id_subscribe']])) {
				continue;
			}

			Utils::$context['current'][$row['id_subscribe']] = [
				'id' => $row['id_sublog'],
				'sub_id' => $row['id_subscribe'],
				'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
				'name' => Subscriptions::$all[$row['id_subscribe']]['name'],
				'start' => Time::create('@' . $row['start_time'])->format(null, false),
				'end' => $row['end_time'] == 0 ? Lang::$txt['not_applicable'] : Time::create('@' . $row['end_time'])->format(null, false),
				'pending_details' => $row['pending_details'],
				'status' => $row['status'],
				'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? Lang::$txt['paid_pending'] : Lang::$txt['paid_finished']) : Lang::$txt['paid_active'],
			];

			if ($row['status'] == 1) {
				Subscriptions::$all[$row['id_subscribe']]['subscribed'] = true;
			}
		}
		Db::$db->free_result($request);

		// Simple "done"?
		if (isset($_GET['done'])) {
			$_GET['sub_id'] = (int) $_GET['sub_id'];

			// Must exist but let's be sure...
			if (isset(Utils::$context['current'][$_GET['sub_id']])) {
				// What are the details like?
				$current_pending = Utils::jsonDecode(Utils::$context['current'][$_GET['sub_id']]['pending_details'], true);

				if (!empty($current_pending)) {
					$current_pending = array_reverse($current_pending);

					foreach ($current_pending as $id => $sub) {
						// Just find one and change it.
						if ($sub[0] == $_GET['sub_id'] && $sub[3] == 'prepay') {
							$current_pending[$id][3] = 'payback';
							break;
						}
					}

					// Save the details back.
					$pending_details = Utils::jsonEncode($current_pending);

					Db::$db->query(
						'',
						'UPDATE {db_prefix}log_subscribed
						SET payments_pending = payments_pending + 1, pending_details = {string:pending_details}
						WHERE id_sublog = {int:current_subscription_id}
							AND id_member = {int:selected_member}',
						[
							'current_subscription_id' => Utils::$context['current'][$_GET['sub_id']]['id'],
							'selected_member' => Profile::$member->id,
							'pending_details' => $pending_details,
						],
					);
				}
			}

			Utils::$context['sub_template'] = 'paid_done';

			return;
		}

		// If this is confirmation then it's simpler...
		if (isset($_GET['confirm'], $_POST['sub_id'])   && is_array($_POST['sub_id'])) {
			// Hopefully just one.
			foreach ($_POST['sub_id'] as $k => $v) {
				$id_sub = (int) $k;
			}

			if (!isset(Subscriptions::$all[$id_sub]) || Subscriptions::$all[$id_sub]['active'] == 0) {
				ErrorHandler::fatalLang('paid_sub_not_active');
			}

			// Simplify...
			Utils::$context['sub'] = Subscriptions::$all[$id_sub];

			$period = 'xx';

			if (Utils::$context['sub']['flexible']) {
				$period = isset($_POST['cur'][$id_sub]) && isset(Utils::$context['sub']['costs'][$_POST['cur'][$id_sub]]) ? $_POST['cur'][$id_sub] : 'xx';
			}

			// Check we have a valid cost.
			if (Utils::$context['sub']['flexible'] && $period == 'xx') {
				ErrorHandler::fatalLang('paid_sub_not_active');
			}

			// Sort out the cost/currency.
			Utils::$context['currency'] = Config::$modSettings['paid_currency_code'];
			Utils::$context['recur'] = Utils::$context['sub']['repeatable'];

			if (Utils::$context['sub']['flexible']) {
				// Real cost...
				Utils::$context['value'] = Utils::$context['sub']['costs'][$_POST['cur'][$id_sub]];
				Utils::$context['cost'] = sprintf(Config::$modSettings['paid_currency_symbol'], Utils::$context['value']) . '/' . Lang::$txt[$_POST['cur'][$id_sub]];

				// The period value for paypal.
				Utils::$context['paypal_period'] = strtoupper(substr($_POST['cur'][$id_sub], 0, 1));
			} else {
				// Real cost...
				Utils::$context['value'] = Utils::$context['sub']['costs']['fixed'];
				Utils::$context['cost'] = sprintf(Config::$modSettings['paid_currency_symbol'], Utils::$context['value']);

				// Recurring?
				preg_match('~(\d*)(\w)~', Utils::$context['sub']['real_length'], $match);
				Utils::$context['paypal_unit'] = $match[1];
				Utils::$context['paypal_period'] = $match[2];
			}

			// Setup the gateway context.
			Utils::$context['gateways'] = [];

			foreach ($gateways as $id => $gateway) {
				$fields = $gateways[$id]->fetchGatewayFields(
					Utils::$context['sub']['id'] . '+' . Profile::$member->id,
					Utils::$context['sub'],
					Utils::$context['value'],
					$period,
					Config::$scripturl . '?action=profile&u=' . Profile::$member->id . '&area=subscriptions&sub_id=' . Utils::$context['sub']['id'] . '&done',
				);

				if (!empty($fields['form'])) {
					Utils::$context['gateways'][] = $fields;
				}
			}

			// Bugger?!
			if (empty(Utils::$context['gateways'])) {
				ErrorHandler::fatal(Lang::$txt['paid_admin_not_setup_gateway']);
			}

			// Now we are going to assume they want to take this out ;)
			$new_data = [Utils::$context['sub']['id'], Utils::$context['value'], $period, 'prepay'];

			if (isset(Utils::$context['current'][Utils::$context['sub']['id']])) {
				// What are the details like?
				$current_pending = [];

				if (Utils::$context['current'][Utils::$context['sub']['id']]['pending_details'] != '') {
					$current_pending = Utils::jsonDecode(Utils::$context['current'][Utils::$context['sub']['id']]['pending_details'], true);
				}

				// Don't get silly.
				if (count($current_pending) > 9) {
					$current_pending = [];
				}

				// Only record real pending payments as will otherwise confuse the admin!
				$pending_count = 0;

				foreach ($current_pending as $pending) {
					if ($pending[3] == 'payback') {
						$pending_count++;
					}
				}

				if (!in_array($new_data, $current_pending)) {
					$current_pending[] = $new_data;

					Db::$db->query(
						'',
						'UPDATE {db_prefix}log_subscribed
						SET payments_pending = {int:pending_count}, pending_details = {string:pending_details}
						WHERE id_sublog = {int:current_subscription_item}
							AND id_member = {int:selected_member}',
						[
							'pending_count' => $pending_count,
							'current_subscription_item' => Utils::$context['current'][Utils::$context['sub']['id']]['id'],
							'selected_member' => Profile::$member->id,
							'pending_details' => Utils::jsonEncode($current_pending),
						],
					);
				}
			}
			// Never had this before, lovely.
			else {
				Db::$db->insert(
					'',
					'{db_prefix}log_subscribed',
					[
						'id_subscribe' => 'int',
						'id_member' => 'int',
						'status' => 'int',
						'payments_pending' => 'int',
						'pending_details' => 'string-65534',
						'start_time' => 'int',
						'vendor_ref' => 'string-255',
					],
					[
						Utils::$context['sub']['id'],
						Profile::$member->id,
						0,
						0,
						Utils::jsonEncode([$new_data]),
						time(),
						'',
					],
					['id_sublog'],
				);
			}

			// Change the template.
			Utils::$context['sub_template'] = 'choose_payment';

			// Quit.
			return;
		}

		Utils::$context['sub_template'] = 'user_subscription';
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\PaidSubs::exportStatic')) {
	PaidSubs::exportStatic();
}

?>