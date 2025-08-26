<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Cleanup\v3_0;

use SMF\Config;
use SMF\Maintenance\Cleanup\CleanupBase;
use SMF\Utils;

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
		list($sourcedir, $tasksdir) = $this->getDirs();

		return (
			isset($tasksdir)
			&& is_dir($tasksdir)
			&& basename($tasksdir) !== 'Tasks'
			&& Utils::makeWritable($tasksdir)
			&& Utils::makeWritable($sourcedir)
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		list($sourcedir, $tasksdir) = $this->getDirs();

		// Do 'tasks' and 'Tasks' both exist?
		// (This can only happen on case sensitive file systems.)
		if (
			is_dir($tasksdir)
			&& is_dir($sourcedir . DIRECTORY_SEPARATOR . 'Tasks')
			&& !empty(fileinode(realpath($tasksdir)))
			&& !empty(fileinode(realpath($sourcedir . DIRECTORY_SEPARATOR . 'Tasks')))
			&& fileinode(realpath($tasksdir)) !== fileinode(realpath($sourcedir . DIRECTORY_SEPARATOR . 'Tasks'))
		) {
			// Move everything in 'Tasks' to 'tasks'.
			foreach (
				glob($sourcedir . DIRECTORY_SEPARATOR . 'Tasks' . DIRECTORY_SEPARATOR . '*') as $path
			) {
				$new_path = $tasksdir . DIRECTORY_SEPARATOR . basename($path);

				// Does a file already exist at the new path?
				if (file_exists($new_path)) {
					Utils::makeWritable($new_path);

					// Remove the conflicting file.
					if (!@unlink($new_path)) {
						// Unlinking failed? Try renaming it.
						if (!@rename($new_path, $new_path . '_' . date_create()->format('YmdHis'))) {
							// Last ditch effort.
							if (file_put_contents($new_path, file_get_contents($path)) === false) {
								return false;
							}

							if (!@unlink($path)) {
								return false;
							}

							continue;
						}
					}
				}

				rename($path, $new_path);
			}

			// Now delete 'Tasks'.
			if (!@rmdir($sourcedir . DIRECTORY_SEPARATOR . 'Tasks')) {
				// If 'Tasks' couldn't be deleted, try renaming it.
				if (
					!@rename(
						$sourcedir . DIRECTORY_SEPARATOR . 'Tasks',
						$sourcedir . DIRECTORY_SEPARATOR . 'DELETE_ME',
					)
				) {
					// Cannot continue.
					return false;
				}
			}
		}

		// Rename 'tasks' to 'Tasks'.
		// Do this in two steps to make sure it works on case insensitive file systems.
		rename(
			$tasksdir,
			$sourcedir . DIRECTORY_SEPARATOR . 'Tasks_temp',
		);
		rename(
			$sourcedir . DIRECTORY_SEPARATOR . 'Tasks_temp',
			$sourcedir . DIRECTORY_SEPARATOR . 'Tasks',
		);

		// Remove the tasksdir setting. SMF 3.0 does not use it.
		Config::updateSettingsFile(['tasksdir' => '']);

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the $sourcedir and $tasksdir paths.
	 *
	 * @return array The values for $sourcedir and $tasksdir.
	 */
	private function getDirs(): array
	{
		clearstatcache();
		$current_settings = Config::getCurrentSettings(filemtime(SMF_SETTINGS_FILE));

		// If the tasksdir setting does not exist but the existing directory's
		// real name does in fact use the wrong case, we still want to fix it.
		if (!isset($current_settings['tasksdir'])) {
			foreach (glob($current_settings['sourcedir'] . DIRECTORY_SEPARATOR . '*') as $path) {
				if (is_dir($path) && basename($path) === 'tasks') {
					$current_settings['tasksdir'] = $current_settings['sourcedir'] . DIRECTORY_SEPARATOR . 'tasks';
				}
			}
		}

		return [$current_settings['sourcedir'], $current_settings['tasksdir'] ?? null];
	}
}
