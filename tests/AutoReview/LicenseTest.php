<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Tests\AutoReview;

use PHPUnit\Framework\TestCase;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;
use SplFileInfo;

/**
 * Tests the license headers used by SMF PHP files.
 */
class LicenseTest extends TestCase
{
	/*****************
	 * Class constants
	 *****************/

	private const IGNORE_FILES = [
		// Index files in subdirectories.
		'\./(?:\w+/)+\bindex\.php',

		// Language files are ignored as they don't use the license format.
		'./Themes/default/languages/',
		'\./Languages/',

		// Cache and miscellaneous.
		'\./cache/',
		'\./tests/',
		'\./vendor/',

		// Everything in other except install.php, upgrade.php, Settings.php and Settings_bak.php.
		'\./other/(?!install|upgrade|Settings)\w+\.php',

		// We will ignore Settings.php if this is a live dev site.
		'\./Settings\.php',
		'\./Settings_bak\.php',
		'\./db_last_error\.php',
	];
	private const VERSION_AND_YEAR_FILES = [
		'\./index\.php',
		'\./SSI\.php',
		'\./cron\.php',
		'\./proxy\.php',
		'\./other/.*\.php',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Every PHP file that is not explicitly ignored has a valid SMF license
	 * header.
	 *
	 * Expected: the contents of each file from getPhpFiles() match
	 *           getLicensePattern().
	 */
	public function testFilesHaveValidLicense(): void
	{
		$invalid_files = [];

		foreach ($this->getPhpFiles() as $file) {
			$contents = file_get_contents($file);

			$this->assertNotFalse($contents, \sprintf(
				'Unable to open file %s',
				$file,
			));

			if (!preg_match($this->getLicensePattern(), $contents)) {
				$invalid_files[] = $file;
			}
		}

		if ($invalid_files !== []) {
			$this->fail(
				\sprintf(
					'License File is invalid or not found in: %s',
					implode(', ', $invalid_files),
				),
			);
		}
	}

	/**
	 * Files whose headers carry the software version and year use the current
	 * ones.
	 *
	 * The current values are the ones defined by index.php.
	 *
	 * Expected: each file matching VERSION_AND_YEAR_FILES matches
	 *           getLicensePattern() with SMF_SOFTWARE_YEAR and SMF_VERSION from
	 *           index.php.
	 */
	public function testFilesHaveCurrentVersionAndYear(): void
	{
		$index_contents = file_get_contents('./index.php');

		$this->assertNotFalse($index_contents, 'Unable to open file ./index.php');

		preg_match(
			'~define\(\'SMF_VERSION\', \'([^\']+)\'\);~i',
			substr($index_contents, 0, 1500),
			$version_matches,
		);

		$this->assertNotEmpty($version_matches, 'Could not locate SMF_VERSION');

		preg_match(
			'~define\(\'SMF_SOFTWARE_YEAR\', \'(\d{4})\'\);~i',
			substr($index_contents, 0, 1500),
			$year_matches,
		);

		$this->assertNotEmpty($year_matches, 'Could not locate SMF_SOFTWARE_YEAR');

		$current_version = $version_matches[1];
		$current_year = (int) $year_matches[1];

		$invalid_files = [];

		foreach ($this->getPhpFiles() as $file) {
			if (!$this->shouldCheckVersionAndYear($file)) {
				continue;
			}

			$contents = file_get_contents($file);

			$this->assertNotFalse($contents, \sprintf(
				'Unable to open file %s',
				$file,
			));

			if (preg_match($this->getLicensePattern($current_year, $current_version), $contents)) {
				continue;
			}

			preg_match($this->getLicensePattern(), $contents, $matches);

			$actual = $matches[0] ?? '';
			$expected = implode(
				"\n",
				$this->getLicenseDocblock($current_year, $current_version),
			);
			$file_a = $file;
			$file_a[0] = 'a';
			$file_b = $file;
			$file_b[0] = 'b';
			$differ = new Differ(
				new DiffOnlyOutputBuilder(
					\sprintf("--- %s (Original)\n+++ %s (New)\n", $file_a, $file_b),
				),
			);

			$invalid_files[] = rtrim($differ->diff($actual . "\n", $expected . "\n"));
		}

		if ($invalid_files !== []) {
			$this->fail(
				\sprintf(
					"The software year or version is incorrect\n\n%s",
					implode("\n\n", $invalid_files),
				),
			);
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets all PHP files in the repository that are not ignored.
	 *
	 * @return list<string>
	 */
	private function getPhpFiles(): array
	{
		$files = [];

		$path = TESTS_BOARDDIR . '/.';
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::UNIX_PATHS));

		foreach ($iterator as $current_file => $file_info) {
			$curr_file = str_replace(TESTS_BOARDDIR . '/', '', $current_file);

			/** @var SplFileInfo $file_info */
			if ($curr_file[2] === '.' || $file_info->getExtension() !== 'php') {
				continue;
			}

			if ($this->isIgnored($curr_file)) {
				continue;
			}

			$files[] = $curr_file;
		}

		return $files;
	}

	/**
	 * Determines whether a path should be excluded from license validation.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	private function isIgnored(string $path): bool
	{
		foreach (self::IGNORE_FILES as $pattern) {
			if (preg_match('~' . $pattern . '~i', $path)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines whether a path requires version and software year validation.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	private function shouldCheckVersionAndYear(string $path): bool
	{
		foreach (self::VERSION_AND_YEAR_FILES as $pattern) {
			if (preg_match('~' . $pattern . '~i', $path)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds the regular expression used to validate an SMF license header.
	 *
	 * @param int|null $year Expected copyright year, or null to accept any year.
	 * @param string|null $version Expected SMF version, or null to accept any version.
	 *
	 * @return string
	 */
	private function getLicensePattern(?int $year = null, ?string $version = null): string
	{
		$match = [
			' \* Simple Machines Forum \(SMF\)',
			' \*',
			' \* @package SMF',
			' \* @author Simple Machines https?://www.simplemachines.org',
			' \* @copyright ' . ($year ?? '\d{4}') . ' Simple Machines and individual contributors',
			' \* @license https?://www.simplemachines.org/about/smf/license.php BSD',
			' \*',
			' \* @version' . ($version !== null ? ' ' . preg_quote($version, '~') : '\V+'),
		];

		return '~(' . implode('[\r]?\n', $match) . ')~i';
	}

	/**
	 * Builds the expected SMF license docblock.
	 *
	 * @param int|null $year Expected copyright year, or null to omit the year.
	 * @param string|null $version Expected SMF version, or null to omit the version.
	 *
	 * @return list<string>
	 */
	private function getLicenseDocblock(?int $year = null, ?string $version = null): array
	{
		return [
			' * Simple Machines Forum (SMF)',
			' *',
			' * @package SMF',
			' * @author Simple Machines https://www.simplemachines.org',
			' * @copyright ' . ($year ?? '\d{4}') . ' Simple Machines and individual contributors',
			' * @license https://www.simplemachines.org/about/smf/license.php BSD',
			' *',
			' * @version' . ($version !== null ? ' ' . $version : ''),
		];
	}
}
