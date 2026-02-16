<?php

/**
 * This is an internal development file. It should NOT be included in
 * any SMF distribution packages.
 *
 * This file updates version numbers and copyright years in any SMF
 * files that need it in order to prepare for a new release.
 *
 * To automatically increment the version number, run the following
 * command on the CLI:
 *
 *     php -f other/update_version_numbers.php
 *
 * To manually specify a version string, do this:
 *
 *     php -f other/update_version_numbers.php 'version_string_here'
 *
 * Note: manually specifying a version string should only be needed
 * when changing from alpha to beta, from beta to release candidate, or
 * from release candidate to release version.
 *
 *
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

namespace SMF\other\Updaters;

use SMF\Config;

/**
 * Class VersionNumberUpdater
 */
class VersionNumberUpdater extends UpdaterBase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var string
	 *
	 * Regex pattern to match all standard SMF version strings.
	 */
	public const VERSION_PATTERN = '\d+\.\d+[. ]?(?:(?:(?<= )(?>RC|Beta |Alpha ))?\d+)?';

	/**
	 * @var string
	 *
	 * Regex pattern to match SMF license blocks.
	 */
	public const LICENSE_PATTERN = <<<'END'
		(\* @package SMF
		 \* @author Simple Machines https\://www\.simplemachines\.org
		 \* @copyright )\d{4}( Simple Machines and individual contributors
		 \* @license https\://www\.simplemachines\.org/about/smf/license\.php BSD
		 \*
		 \* @version )
		END . self::VERSION_PATTERN;

	/**
	 * @var string
	 *
	 * Regex pattern to match SMF language file headers.
	 */
	public const LANG_PATTERN = '// Version: \K' . self::VERSION_PATTERN;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Previous version based on the most recent Git tag.
	 *
	 * This assumes we are using proper semantic versioning in our tags.
	 */
	public string $prev_version;

	/**
	 * @var string
	 *
	 * New version.
	 */
	public string $new_version;

	/**
	 * @var string
	 *
	 * Current year.
	 */
	public string $year;

	/**
	 * @var array
	 *
	 * Files that need to be updated for every new version, even if they have
	 * not otherwise changed.
	 */
	public array $always_update = [
		'index.php',
		'cron.php',
		'proxy.php',
		'SSI.php',
		'other/Settings.php',
		'other/Settings_bak.php',
		'other/install.php',
		'other/upgrade.php',
	];


	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(?string $new_version = null): void
	{
		$this->setPrevVersion();
		$this->setNewVersion($new_version);
		$this->setYear();

		if (php_sapi_name() === 'cli') {
			echo 'Updating version numbers...', PHP_EOL;
		}

		$this->createBranch();

		$this->updateVersionAndYear();
		$this->updateLicenseBlocks();
		$this->updateLicenseFile();

		if (php_sapi_name() === 'cli') {
			echo 'Done.', !$this->hasChanged() ? ' No changes were made.' : '', PHP_EOL;
		}

		$this->removeUselessBranch();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets previous version based on the most recent Git tag.
	 *
	 * This assumes we are using proper semantic versioning in our tags.
	 */
	private function setPrevVersion(): void
	{
		$this->prev_version = ltrim(trim(shell_exec('git describe --tags --abbrev=0')), 'v');
	}

	/**
	 * Sets new version.
	 */
	private function setNewVersion(?string $new_version = null): void
	{
		// Was the new version passed as a command line argument?
		if (!empty($new_version)) {
			$new_version = trim($new_version);

			if (!preg_match('~^' . self::VERSION_PATTERN . '$~', $new_version)) {
				throw new \Exception('Provided version string is invalid: ' . $new_version);
			}
		}
		// Normal case: just increment the patch number
		else {
			$new_version = array_pad(explode('.', $this->prev_version), 3, 0);

			$new_version[2]++;

			if (!is_numeric($new_version[1])) {
				$new_version[1] = str_replace(['-', 'ALPHA', 'BETA'], [' ', 'Alpha', 'Beta'], strtoupper($new_version[1]));

				$new_version[1] .= (str_contains($new_version[1], 'RC') ? '' : ' ') . $new_version[2];

				unset($new_version[2]);
			}

			$new_version = implode('.', $new_version);
		}

		$this->new_version = $new_version;
	}

	/**
	 * Sets the current year.
	 */
	private function setYear(): void
	{
		$this->year = date_format(date_create(), 'Y');
	}

	/**
	 * Updates SMF_VERSION and SMF_SOFTWARE_YEAR in relevant files.
	 */
	private function updateVersionAndYear(): void
	{
		foreach ($this->always_update as $file) {
			$content = $original_content = file_get_contents(Config::$boarddir . "/{$file}");

			$content = preg_replace("~define\('SMF_VERSION', '" . self::VERSION_PATTERN . "'\);~", "define('SMF_VERSION', '{$this->new_version}');", $content);
			$content = preg_replace("~define\('SMF_SOFTWARE_YEAR', '\d{4}'\);~", "define('SMF_SOFTWARE_YEAR', '{$this->year}');", $content);

			if ($content !== $original_content) {
				file_put_contents(Config::$boarddir . "/{$file}", $content);
			}
		}
	}

	/**
	 * Updates SMF license blocks in relevant files.
	 */
	private function updateLicenseBlocks(): void
	{
		$files = array_unique(
			array_merge(
				$this->always_update,
				array_filter(
					preg_match('/alpha|beta|rc/', $this->prev_version) ? $this->getFiles(Config::$boarddir) : explode("\n", (string) shell_exec('git diff --name-only v' . $this->prev_version . '...HEAD')),
					fn($filename) => file_exists($filename) && str_starts_with(mime_content_type($filename), 'text/'),
				),
			),
		);

		foreach ($files as $file) {
			$content = $original_content = file_get_contents(Config::$boarddir . "/{$file}");

			if (preg_match('~' . self::LICENSE_PATTERN . '~', $content)) {
				$content = preg_replace('~' . self::LICENSE_PATTERN . '~', '${1}' . $this->year . '${2}' . $this->new_version, $content);
			} elseif (preg_match('~' . self::LANG_PATTERN . '~', $content)) {
				$content = preg_replace('~' . self::LANG_PATTERN . '~', $this->new_version, $content);
			} else {
				continue;
			}

			if ($content !== $original_content) {
				file_put_contents(Config::$boarddir . "/{$file}", $content);
			}
		}
	}

	/**
	 * Updates the LICENSE file.
	 */
	private function updateLicenseFile(): void
	{
		$content = $original_content = file_get_contents(Config::$boarddir . '/LICENSE');
		$content = preg_replace("~Copyright © \d+ Simple Machines.~", "Copyright © {$this->year} Simple Machines.", $content);
		$content = preg_replace("~http://www.simplemachines.org\b~", 'https://www.simplemachines.org', $content);

		if ($content !== $original_content) {
			file_put_contents(Config::$boarddir . '/LICENSE', $content);
		}
	}

	/**
	 * Recursively get a list of text files in the given directory and its
	 * descendents.
	 *
	 * @param string $dir The directory.
	 * @return array A list of file paths relative to Config::$boarddir.
	 */
	private function getFiles(string $dir): array
	{
		$files = [];

		foreach (scandir($dir) as $item) {
			if (str_starts_with($item, '.')) {
				continue;
			}

			$item = $dir . DIRECTORY_SEPARATOR . $item;

			if (is_file($item)) {
				$files[] = str_replace(Config::$boarddir . DIRECTORY_SEPARATOR, '', $item);
			} elseif (is_dir($item)) {
				$files = array_merge($files, $this->getFiles($item));
			}
		}

		return $files;
	}
}
