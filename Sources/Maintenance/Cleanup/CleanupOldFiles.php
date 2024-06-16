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

declare(strict_types=1);

namespace SMF\Maintenance\Cleanup;

use SMF\Config;

class CleanupOldFiles extends CleanupBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Cleanup old files';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * List of source files removed in SMF 2.1.
	 */
	private array $smf21SourceFiles = [
		'DumpDatabase.php',
		'LockTopic.php',
	];

	/**
	 * @var array
	 *
	 * List of source files removed in SMF 2.0.
	 */
	private array $smf20SourceFiles = [
		'ModSettings.php',
	];

	/**
	 * @var array
	 *
	 * List of theme files removed in SMF 1.1.
	 */
	private array $smf11ThemeFiles = [
		'Combat.template.php',
		'Modlog.template.php',
		'fader.js',
		'script.js',
		'spellcheck.js',
		'xml_board.js',
		'xml_topic.js',
	];

	/****************
	 * Public methods
	 ****************/

	public function execute(): bool
	{
		$success = true;

		foreach ($this->smf11ThemeFiles as $file) {
			// This is hard coded, but was originally wrote this way.
			if (!$this->deleteFile(basename(SMF_SETTINGS_FILE) . '/Themes/default/' . $file)) {
				$success = false;
			}
		}

		foreach ($this->smf20SourceFiles as $file) {
			// This is hard coded, but was originally wrote this way.
			if (!$this->deleteFile(Config::$sourcedir . '/' . $file)) {
				$success = false;
			}
		}

		foreach ($this->smf21SourceFiles as $file) {
			// This is hard coded, but was originally wrote this way.
			if (!$this->deleteFile(Config::$sourcedir . '/' . $file)) {
				$success = false;
			}
		}

		return $success;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Delete a file.  Check permissions first, just in case.
	 *
	 * @param string $file
	 */
	private function deleteFile(string $file): bool
	{
		if (!file_exists($file)) {
			return true;
		}

		// NOW do the writable check...
		if (!is_writable($file)) {
			@chmod($file, 0755);
		}

		if (!is_writable($file)) {
			// Try 755 and 775 first since 777 doesn't always work and could be a risk...
			$chmod_values = [0755, 0775, 0777];

			foreach ($chmod_values as $val) {
				@chmod($file, $val);

				// If it's writable, break out of the loop
				if (is_writable($file)) {
					break;
				}
			}

			@unlink($file);
		}

		return !file_exists($file);
	}
}

?>