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
use SMF\Alert;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Lang;
use SMF\User;
use SMF\Utils;

/**
 * Shows the popup for the current user's alerts.
 */
class AlertsPopup implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'alerts_popup',
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
		// We do not want to output debug information here.
		Config::$db_show_debug = false;

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = [];

		// No funny business allowed
		$counter = isset($_REQUEST['counter']) ? max(0, (int) $_REQUEST['counter']) : 0;

		$limit = !empty(Config::$modSettings['alerts_per_page']) && (int) Config::$modSettings['alerts_per_page'] < 1000 ? min((int) Config::$modSettings['alerts_per_page'], 1000) : 25;

		Utils::$context['unread_alerts'] = [];

		if ($counter < User::$me->alerts) {
			// Now fetch me my unread alerts, pronto!
			Utils::$context['unread_alerts'] = Alert::fetch(User::$me->id, false, !empty($counter) ? User::$me->alerts - $counter : $limit, 0, !isset($_REQUEST['counter']));
		}
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
		// Load the Alerts language file.
		Lang::load('Alerts');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\AlertsPopup::exportStatic')) {
	AlertsPopup::exportStatic();
}

?>