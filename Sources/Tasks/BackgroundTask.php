<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Tasks;

use SMF\TaskRunner;
use SMF\User;

/**
 * Base class for all background tasks.
 */
abstract class BackgroundTask
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Constants for notification types.
	 */
	public const RECEIVE_NOTIFY_EMAIL = 0x02;
	public const RECEIVE_NOTIFY_ALERT = 0x01;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Holds the details for the task
	 */
	protected array $_details;

	/****************
	 * Public methods
	 ****************/

	/**
	 * The constructor.
	 *
	 * @param array $details The details for the task
	 */
	public function __construct(array $details)
	{
		$this->_details = $details;
	}

	/**
	 * The function to actually execute a task.
	 *
	 * @return mixed
	 */
	abstract public function execute();

	/**
	 * Loads minimal info for the previously loaded user ids.
	 *
	 * @param array $user_ids
	 * @throws \Exception
	 * @return array
	 */
	public function getMinUserInfo(array $user_ids = []): array
	{
		$loaded_ids = array_map(fn($member) => $member->id, User::load($user_ids, User::LOAD_BY_ID, 'minimal'));

		return array_intersect_key(User::$profiles, array_flip($loaded_ids));
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Adds a new instance of this task to the task list.
	 *
	 * @param array $details The $details array for the new task instance.
	 *    This should typically be an updated copy of $this->_details.
	 * @param int $time Unix timestamp indicating when the new task instance
	 *    should be executed. In practice, the execution will almost always
	 *    happen sometime later this, but is guaranteed not to happen earlier.
	 *    If this is set to a value in the past, such as 0, the new task will
	 *    be executed at the first available opportunity.
	 *    Default: 0.
	 */
	protected function respawn(array $details, int $time = 0): void
	{
		Db::$db->insert(
			method: 'insert',
			table: '{db_prefix}background_tasks',
			columns: [
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			data: [
				[
					get_class($this),
					json_encode($details),
					// Subtract TaskRunner::MAX_CLAIM_THRESHOLD because TaskRunner
					// won't try to re-run a task until that many seconds after
					// the task's claimed_time value.
					max(0, $time - TaskRunner::MAX_CLAIM_THRESHOLD),
				],
			],
			keys: ['id_task'],
		);
	}
}
