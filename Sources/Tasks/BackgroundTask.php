<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Tasks;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Sapi;
use SMF\TaskRunner;
use SMF\User;
use SMF\UserDataset;

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
	 * @var bool
	 *
	 * Whether multiple instances of this task can safely run simultaneously.
	 *
	 * By default this operates at the class level, meaning that setting this
	 * property to false will prevent multiple instances of this class from
	 * executing concurrently at all ever.
	 *
	 * However, child classes can narrow this behaviour by including more
	 * specific information in the md5 hash portion of the lock file's name.
	 * For example, if both the class name and a user's ID number were included
	 * in the hashed value, then setting $this->allow_concurrent to false would
	 * prevent multiple instances of the task from working on the same user's
	 * data at the same time; concurrent instances working on other users' data
	 * would still be allowed.
	 */
	protected bool $allow_concurrent = true;

	/**
	 * @var string
	 *
	 * Path to the lock file for this task.
	 */
	protected string $lockfile;

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

		$this->lockfile = Sapi::getTempDir() . DIRECTORY_SEPARATOR . Config::$modSettings['forum_uuid'] . '-' . md5(\get_class($this)) . '.lock';
	}

	/**
	 * The destructor.
	 */
	public function __destruct()
	{
		$this->unlock();
	}

	/**
	 * The function to actually execute a task.
	 *
	 * @return mixed
	 */
	abstract public function execute();

	/**
	 * Determines whether it is safe to execute this task.
	 *
	 * @return bool Whether it is safe to execute the task.
	 */
	public function canExecute(): bool
	{
		// Assume yes until proven otherwise.
		$can_execute = true;

		// Concurrency protection.
		if (!$this->allow_concurrent) {
			$can_execute = $this->lock();
		}

		return $can_execute;
	}

	/**
	 * Loads the specified users with (at least) their minimal data.
	 *
	 * @deprecated 3.0 Only exists for backward compatibility reasons.
	 *
	 * @param array $user_ids
	 * @throws \Exception
	 * @return array
	 */
	public function getMinUserInfo(array $user_ids = []): array
	{
		return User::load($user_ids, User::LOAD_BY_ID, UserDataset::Minimal);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Checks whether another instance of this task has already created a lock.
	 *
	 * @return bool True if we are locked out, false if we are not.
	 */
	protected function alreadyLocked(): bool
	{
		// This shouldn't happen, but just in case...
		if (is_dir($this->lockfile)) {
			return true;
		}

		return (
			is_file($this->lockfile)
			&& file_get_contents($this->lockfile) !== (string) getmypid()
			// Ignore stale lock files.
			&& filemtime($this->lockfile) > (time() - TaskRunner::MAX_CLAIM_THRESHOLD * 2)
		);
	}

	/**
	 * Attempts to lock task execution so that no concurrent instances of the
	 * task can be executed.
	 *
	 * @return bool Whether the operation was successful.
	 */
	protected function lock(): bool
	{
		if ($this->alreadyLocked()) {
			return false;
		}

		try {
			return @file_put_contents($this->lockfile, (string) getmypid(), LOCK_EX) !== false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Attempts to unlock task execution so that other instances of the current
	 * task can execute.
	 *
	 * @return bool Whether the operation was successful.
	 */
	protected function unlock(): bool
	{
		if ($this->alreadyLocked()) {
			return false;
		}

		try {
			return !is_file($this->lockfile) || @unlink($this->lockfile);
		} catch (\Throwable $e) {
			return false;
		}
	}

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
					\get_class($this),
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
