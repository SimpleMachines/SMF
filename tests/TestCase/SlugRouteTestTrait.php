<?php

declare(strict_types=1);

namespace SMF\Tests\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use SMF\IntegrationHook;
use SMF\Slug;

/**
 * Provides common tests for routes that use slugs.
 *
 * Subclasses provide the slug fixtures and route cases required by their
 * tests. The trait verifies slug initialization, correct slug routes, and
 * incorrect slug routes that require canonical redirects.
 */
trait SlugRouteTestTrait
{
	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Provides the slugs required by the test case.
	 *
	 * @return iterable<string, array{string, int, string}>
	 */
	abstract public static function provideSlugCases(): iterable;

	/**
	 * Provides incorrect slug route cases.
	 *
	 * @return iterable<string, array{
	 *     array<int, string>,
	 *     array<string, string>,
	 *     string,
	 * }>
	 */
	abstract public static function provideIncorrectSlugRouteCases(): iterable;

	/**
	 * Provides correct slug route cases.
	 *
	 * @return iterable<string, array{
	 *     array<int, string>,
	 *     array<string, string>
	 * }>
	 */
	abstract public static function provideCorrectSlugRouteCases(): iterable;

	/****************
	 * Public methods
	 ****************/

	#[DataProvider('provideIncorrectSlugRouteCases')]
	public function testIncorrectSlugRoute(array $route, array $params, string $expected_slug): void
	{
		foreach (self::provideClassNameCases() as [$class_name]) {
			$result = $this->runWithRedirectTrap(
				fn() => $class_name::parseRoute($route),
			);

			$this->assertStringContainsString(
				http_build_query($params, '', ';'),
				$result['redirect'],
			);

			$this->assertSame($params, $result['result']);
			$this->assertSame($expected_slug, (string) Slug::$requested);
		}
	}

	#[DataProvider('provideCorrectSlugRouteCases')]
	public function testCorrectSlugRoute(array $route, array $params): void
	{
		foreach (self::provideClassNameCases() as [$class_name]) {
			foreach (self::provideSlugCases() as [$type, $id, $slug]) {
				$this->setKnownSlug($type, $id, $slug);
			}

			$known_slugs = Slug::$known;
			$this->assertSame($params, $class_name::parseRoute($route));
			Slug::$known = $known_slugs;

			$this->assertSame(
				['route' => $route, 'params' => []],
				$class_name::buildRoute($params),
			);
		}
	}

	#[DataProvider('provideSlugCases')]
	public function testSlug(string $type, int $id, string $slug): void
	{
		$this->assertArrayHasKey($type, Slug::$known);
		$this->assertArrayHasKey($id, Slug::$known[$type]);
		$this->assertInstanceOf(Slug::class, Slug::$known[$type][$id]);
		$this->assertEmpty((string) Slug::$known[$type][$id]);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		// Create a known slug before each test so buildRoute() can use it
		// without attempting to load the associated object from the database.
		foreach (self::provideSlugCases() as [$type, $id, $slug]) {
			$this->setKnownSlug($type, $id, '');
		}
	}

	protected function tearDown(): void
	{
		// Reset the static slug state so one test cannot affect another.
		Slug::$known = [
			'board' => [],
			'topic' => [],
			'member' => [],
			'group' => [],
		];
		Slug::$requested = new Slug('', '', 0);

		parent::tearDown();
	}

	/**
	 * Creates a known slug for use by buildRoute().
	 *
	 * @param string $type Slug type.
	 * @param int $id Associated object ID.
	 * @param string $slug Slug string.
	 */
	protected function setKnownSlug(string $type, int $id, string $slug): void
	{
		$known_slug = new Slug('', $type, $id);

		$reflection = new \ReflectionProperty(Slug::class, 'slug');
		$reflection->setValue($known_slug, $slug);
	}

	/**
	 * Intercepts redirects while executing a route operation.
	 *
	 * @param callable(): array $callback Route operation to execute.
	 * @return array{result: array, redirect: string}
	 */
	protected function runWithRedirectTrap(callable $callback): array
	{
		IntegrationHook::add(
			'integrate_redirect',
			'my_integrated_redirect',
			false,
			'$boarddir/tests/fixtures/integrationhooks.php',
		);

		$result = [];
		$redirect = '';

		try {
			$result = $callback();

			// Build the canonical member slug after parsing the requested alias.
			// This is what the normal request flow does after the requested slug
			// has been recorded by Profile::parseRoute().
			foreach (self::provideSlugCases() as [$type, $id, $slug]) {
				$this->setKnownSlug($type, $id, '');
			}
		} catch (\Exception $ex) {
			// The integration hook throws the redirect target instead of
			// allowing Utils::redirectexit() to terminate the test process.
			$redirect = $ex->getMessage();
		} finally {
			// Remove the temporary hook so it cannot affect subsequent tests.
			IntegrationHook::remove(
				'integrate_redirect',
				'my_integrated_redirect',
				false,
				'$boarddir/tests/fixtures/integrationhooks.php',
			);
		}

		return [
			'result' => $result,
			'redirect' => $redirect,
		];
	}
}
