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

namespace SMF\Maintenance\Cleanup;

use SMF\Config;
use SMF\Utils;

/**
 * Base class for cleanup tasks that delete files that have been removed in a
 * new version of SMF.
 */
abstract class OldFilesBase extends CleanupBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Cleanup old files';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * List of files removed in the relevant version of SMF.
	 */
	protected array $removed = [
		// Files in the Themes directory.
		'themedir' => [],
		// Files in the Sources directory.
		'sourcedir' => [],
		// Files in the Smileys directory.
		'smileysdir' => [],
		// Files in the avatars directory.
		'avatardir' => [],
		// Files in the forum's root directory.
		'boarddir' => [],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		foreach ($this->removed as $dir => $files) {
			foreach ($files as $file) {
				if (is_file($this->getDirPath($dir) . '/' . $file)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$success = true;

		foreach ($this->removed as $dir => $files) {
			foreach ($files as $file) {
				if (!is_file($this->getDirPath($dir) . '/' . $file)) {
					continue;
				}

				if (!$this->deletePath($this->getDirPath($dir) . '/' . $file)) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the correct directory path for a key in $this->removed.
	 *
	 * @param string $dir A key from $this->removed.
	 * @throws \Exception if $dir is unrecognized.
	 * @return string A directory path.
	 */
	protected function getDirPath(string $dir): string
	{
		switch ($dir) {
			case 'sourcedir':
				return Config::$sourcedir;

			case 'themedir':
				return Config::$boarddir . '/Themes';

			case 'smileysdir':
				return Config::$boarddir . '/Smileys';

			case 'avatardir':
				return Config::$boarddir . '/avatars';

			case 'boarddir':
				return Config::$boarddir;

			default:
				throw new \Exception();
		}
	}

	/**
	 * Deletes a file or directory.
	 *
	 * Checks permissions first, just in case.
	 *
	 * @param string Path to a file or directory
	 */
	protected function deletePath(string $path): bool
	{
		if (!file_exists($path)) {
			return true;
		}

		if (!Utils::makeWritable($path)) {
			return false;
		}

		if (!is_dir($path)) {
			@unlink($pathname);
		} else {
			$dir = new \DirectoryIterator($path);

			$to_delete = [];

			foreach ($dir as $fileinfo) {
				if (!$fileinfo->isDot()) {
					$this->deletePath($fileinfo->getPathname());
				}
			}

			@rmdir($path);
		}

		return !file_exists($file);
	}
}
