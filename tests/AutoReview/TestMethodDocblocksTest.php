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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that all test methods have docblocks in the format specified by AGENTS.md.
 *
 * Per PR #9756, every public test method must have a docblock containing:
 * - A one-sentence summary (first line)
 * - An "Expected:" section describing the call and assertion result
 * - For regression tests: a "Guards:" section with what went wrong, plus @link lines
 */
#[CoversNothing]
class TestMethodDocblocksTest extends TestCase
{
	use RecursiveFileFinderTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * All public test methods in a test file have docblocks with an Expected: section.
	 *
	 * Expected: for each test file in tests/, every public function test*() has a docblock
	 *           with at least an "Expected:" section, and any "Guards:" section has @link lines.
	 *
	 * @param SplFileInfo $file The test file.
	 */
	#[DataProvider('provideTestFiles')]
	public function testTestMethodsHaveProperDocblocks(\SplFileInfo $file): void
	{
		$file_path = $file->getPathname();
		$content = file_get_contents($file_path);

		$this->assertIsString($content, \sprintf('Could not read %s', $file_path));

		$this->assertMatchesRegularExpression(
			'/namespace\s+([^;]+);/',
			$content,
			\sprintf('No namespace found in %s', $file_path),
		);
		preg_match('/namespace\s+([^;]+);/', $content, $ns_match);

		// Class names always match their file names
		$full_class = $ns_match[1] . '\\' . pathinfo($file_path, PATHINFO_FILENAME);

		$this->assertTrue(
			class_exists($full_class),
			\sprintf('Class %s from %s could not be loaded', $full_class, $file_path),
		);

		$reflection = new \ReflectionClass($full_class);

		foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if (!str_starts_with($method->getName(), 'test')) {
				continue;
			}

			$name = $reflection->getName() . '::' . $method->getName() . '()';
			$doc_comment = $method->getDocComment();

			// Check that a docblock exists
			$this->assertIsString($doc_comment, \sprintf('%s has no docblock', $name));

			// Check for Expected: section
			$this->assertStringContainsString(
				'Expected:',
				$doc_comment,
				\sprintf("%s docblock is missing 'Expected:' section", $name),
			);

			// Guards: is for regression tests, which must have @link references
			if (str_contains($doc_comment, 'Guards:')) {
				$this->assertStringContainsString(
					'@link',
					$doc_comment,
					\sprintf("%s has 'Guards:' but no @link references", $name),
				);
			}
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Provides every test file in tests/, keyed by its path relative to tests/.
	 *
	 * Named without a "test" prefix so this check does not apply to it.
	 *
	 * @return \Generator<string, array{SplFileInfo}>
	 */
	public static function provideTestFiles(): \Generator
	{
		$tests_dir = __DIR__ . '/../../tests';

		if (!is_dir($tests_dir)) {
			throw new \RuntimeException('tests directory not found');
		}

		$files = self::getTestFiles(
			$tests_dir,
			fn(\SplFileInfo $file): bool => !str_contains($file->getPathname(), 'ActionRouterTest.php')
				&& !str_contains($file->getPathname(), 'ThemeCopyTest.php'),
		);

		foreach ($files as $sub_pathname => $file) {
			yield $sub_pathname => [$file];
		}
	}
}
