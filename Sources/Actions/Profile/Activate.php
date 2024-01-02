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
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Logging;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Activates an account.
 */
class Activate implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'activateAccount',
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
		User::$me->isAllowedTo('moderate_forum');

		if (isset($_REQUEST['save'], Profile::$member->is_activated)   && Profile::$member->is_activated != 1) {
			// If we are approving the deletion of an account, we do something special ;)
			if (Profile::$member->is_activated == 4) {
				User::delete(Utils::$context['id_member']);
				Utils::redirectexit();
			}

			$prev_is_activated = Profile::$member->is_activated;

			// Let the integrations know of the activation.
			IntegrationHook::call('integrate_activate', [Profile::$member->username]);

			// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
			User::updateMemberData(Utils::$context['id_member'], ['is_activated' => Profile::$member->is_activated >= 10 ? 11 : 1, 'validation_code' => '']);

			// Log what we did?
			Logging::logAction('approve_member', ['member' => Profile::$member->id], 'admin');

			// If we are doing approval, update the stats for the member just in case.
			if (in_array($prev_is_activated, [3, 4, 5, 13, 14, 15])) {
				Config::updateModSettings(['unapprovedMembers' => max(0, Config::$modSettings['unapprovedMembers'] - 1)]);
			}

			// Make sure we update the stats too.
			Logging::updateStats('member', false);
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
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Activate::exportStatic')) {
	Activate::exportStatic();
}

?>