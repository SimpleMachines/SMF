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

/**
 * Provides recursive file discovery for test classes.
 */
trait RecursiveFileFinderTrait
{
	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Recursively finds files under a directory.
	 *
	 * @param string $dirname Directory to scan.
	 * @param callable(SplFileInfo): bool|null $filter Called for each file (never for directories).
	 *        Return true to keep the file. If null, all files are kept.
	 * @return \Generator<string, SplFileInfo> Matching files, keyed by path relative to $dirname.
	 */
	protected static function getFileRecursive(string $dirname, ?callable $filter = null): \Generator
	{
		$prefix_length = \strlen(rtrim($dirname, '/\\')) + 1;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator(
					$dirname,
					\FilesystemIterator::UNIX_PATHS | \FilesystemIterator::SKIP_DOTS,
				),
				function (\SplFileInfo $file_info, string $current_file, \RecursiveDirectoryIterator $iterator) use ($filter): bool {
					// Allow recursion
					if ($iterator->hasChildren()) {
						return true;
					}

					return $filter === null || (bool) $filter($file_info);
				},
			),
		);

		foreach ($iterator as $pathname => $file_info) {
			yield substr($pathname, $prefix_length) => $file_info;
		}
	}

	/**
	 * Recursively finds all test files (files whose names end in "Test.php") under a directory.
	 *
	 * @param string $dirname Directory to scan.
	 * @param callable(SplFileInfo): bool|null $filter Optional extra filter applied to each test file.
	 *        Return true to keep the file.
	 * @return \Generator<string, SplFileInfo> Matching test files, keyed by path relative to $dirname.
	 */
	protected static function getTestFiles(string $dirname, ?callable $filter = null): \Generator
	{
		yield from self::getFileRecursive(
			$dirname,
			fn(\SplFileInfo $file_info): bool => str_ends_with($file_info->getFilename(), 'Test.php')
				&& ($filter === null || (bool) $filter($file_info)),
		);
	}
}
