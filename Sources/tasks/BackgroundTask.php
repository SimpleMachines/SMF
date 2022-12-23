<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Tasks;

/**
 * Base class for all background tasks.
 */
abstract class BackgroundTask
{
	/**
	 * Constants for notification types.
	*/
	const RECEIVE_NOTIFY_EMAIL = 0x02;
	const RECEIVE_NOTIFY_ALERT = 0x01;

	/**
	 * @var array Holds the details for the task
	 */
	protected $_details;

	/**
	 * @var array Temp property to hold the current user info while tasks make use of $user_info
	 */
	private $current_user_info = array();

	/**
	 * The constructor.
	 *
	 * @param array $details The details for the task
	 */
	public function __construct($details)
	{
		global $user_info;

		$this->_details = $details;

		$this->current_user_info = $user_info;
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
	 * @return array
	 * @throws Exception
	 */
	public function getMinUserInfo($user_ids = array())
	{
		return loadMinUserInfo($user_ids);
	}

	public function __destruct()
	{
		global $user_info;

		$user_info = $this->current_user_info;
	}
}

?>