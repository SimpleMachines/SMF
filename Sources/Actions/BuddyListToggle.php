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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\User;
use SMF\Utils;

/**
 * This simple action adds/removes the passed user from the current user's buddy list.
 * Requires profile_identity_own permission.
 * Called by ?action=buddy;u=x;session_id=y.
 * Redirects to ?action=profile;u=x.
 */
class BuddyListToggle implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'BuddyListToggle',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * ID of the user being added or removed from the buddy list.
	 */
	public string $userReceiver;

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
		User::$me->checkSession('get');

		User::$me->isAllowedTo('profile_extra_own');
		User::$me->kickIfGuest();

		if (empty($this->userReceiver)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Remove if it's already there...
		if (in_array($this->userReceiver, User::$me->buddies)) {
			User::$me->buddies = array_diff(User::$me->buddies, [$this->userReceiver]);
		}
		// ...or add if it's not and if it's not you.
		elseif (User::$me->id != $this->userReceiver) {
			User::$me->buddies[] = $this->userReceiver;

			// And add a nice alert. Don't abuse though!
			if ((CacheApi::get('Buddy-sent-' . User::$me->id . '-' . $this->userReceiver, 86400)) == null) {
				Db::$db->insert(
					'insert',
					'{db_prefix}background_tasks',
					[
						'task_class' => 'string',
						'task_data' => 'string',
						'claimed_time' => 'int',
					],
					[
						'SMF\\Tasks\\Buddy_Notify',
						Utils::jsonEncode([
							'receiver_id' => $this->userReceiver,
							'id_member' => User::$me->id,
							'member_name' => User::$me->username,
							'time' => time(),
						]),
						0,
					],
					['id_task'],
				);

				// Store this in a cache entry to avoid creating multiple alerts. Give it a long life cycle.
				CacheApi::put('Buddy-sent-' . User::$me->id . '-' . $this->userReceiver, '1', 86400);
			}
		}

		// Update the settings.
		User::updateMemberData(User::$me->id, ['buddy_list' => implode(',', User::$me->buddies)]);

		// Redirect back to the profile
		Utils::redirectexit('action=profile;u=' . $this->userReceiver);
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
		$this->userReceiver = (int) !empty($_REQUEST['u']) ? $_REQUEST['u'] : 0;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\BuddyListToggle::exportStatic')) {
	BuddyListToggle::exportStatic();
}

?>