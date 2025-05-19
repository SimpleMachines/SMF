<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Cleanup\v3_0;

use SMF\Config;
use SMF\Maintenance\Cleanup\CleanupBase;

class TasksDirCase extends CleanupBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Ensure name of Tasks directory is in correct case';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Check if the task should be performed or not.
	 *
	 * @return bool True if this task needs to be run, false otherwise.
	 */
	public function isCandidate(): bool
	{
		clearstatcache();
		$current_settings = Config::getCurrentSettings(filemtime(SMF_SETTINGS_FILE));

		return (
			isset($current_settings['tasksdir'])
			&& is_dir($current_settings['tasksdir'])
			&& basename($current_settings['tasksdir']) !== 'Tasks'
			&& is_writable($current_settings['tasksdir'])
			&& is_writable($current_settings['sourcedir'])
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Do 'tasks' and 'Tasks' both exist?
		if (
			!empty(fileinode(realpath($current_settings['sourcedir'] . '/tasks')))
			&& !empty(fileinode(realpath($current_settings['sourcedir'] . '/Tasks')))
			&& fileinode(realpath($current_settings['tasksdir'])) !== fileinode(realpath($current_settings['sourcedir'] . '/Tasks'))
		) {
			// Move everything in 'Tasks' to 'tasks'.
			foreach (
				glob(realpath($current_settings['sourcedir'] . '/Tasks') . DIRECTORY_SEPARATOR . '*') as $path
			) {
				rename($path, realpath($current_settings['tasksdir']) . DIRECTORY_SEPARATOR . basename($path));
			}

			// Now delete 'Tasks'.
			rmdir(realpath($current_settings['sourcedir'] . '/Tasks'));
		}

		// Rename 'tasks' to 'Tasks'.
		// Do this in two steps to make sure it works on case insensitive file systems.
		rename($current_settings['tasksdir'], $current_settings['sourcedir'] . DIRECTORY_SEPARATOR . 'Tasks_temp');
		rename($current_settings['sourcedir'] . DIRECTORY_SEPARATOR . 'Tasks_temp', $current_settings['sourcedir'] . DIRECTORY_SEPARATOR . 'Tasks');

		return true;
	}
}
