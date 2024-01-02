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

namespace SMF\Tasks;

use SMF\User;

/**
 * Base class for all background tasks.
 */
abstract class BackgroundTask
{
	/**
	 * Constants for notification types.
	 */
	public const RECEIVE_NOTIFY_EMAIL = 0x02;
	public const RECEIVE_NOTIFY_ALERT = 0x01;

	/**
	 * @var array Holds the details for the task
	 */
	protected $_details;

	/**
	 * The constructor.
	 *
	 * @param array $details The details for the task
	 */
	public function __construct($details)
	{
		$this->_details = $details;
	}

	/**
	 * The function to actually execute a task
	 *
	 * @return mixed
	 */
	abstract public function execute();

	/**
	 * Loads minimal info for the previously loaded user ids
	 *
	 * @param array $user_ids
	 * @throws Exception
	 * @return array
	 */
	public function getMinUserInfo($user_ids = [])
	{
		$loaded_ids = array_map(fn ($member) => $member->id, User::load($user_ids, User::LOAD_BY_ID, 'minimal'));

		return array_intersect_key(User::$profiles, array_flip($loaded_ids));
	}
}

?>