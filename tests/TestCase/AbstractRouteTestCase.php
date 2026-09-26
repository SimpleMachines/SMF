<?php

declare(strict_types=1);

namespace SMF\Tests\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Forum;
use SMF\QueryString;
use SMF\Routable;

/**
 * Base test case for classes implementing {@see Routable}.
 *
 * Subclasses provide the classes and routes to test through two data providers:
 *
 * - {@see provideClassNameCases()} supplies the Routable classes to test. The
 *   provider keys are used as action names when registering the classes in
 *   {@see Forum::$actions}.
 * - {@see provideRouteCases()} supplies the routes and their expected query
 *   parameters.
 *
 * Subclasses therefore only need to define the classes and route cases relevant
 * to their tests.  This abstract class provides tests that verify that each class
 * implements {@see Routable}, that its route result has the expected structure,
 * and that the routes build and parse correctly through {@see QueryString}.
 */
abstract class AbstractRouteTestCase extends TestCase
{
	/****************************
	 * Internal static properties
	 ****************************/

	private static array $original_actions = [];

	/****************
	 * Public methods
	 ****************/

	#[DataProvider('provideClassNameCases')]
	public function testImplementsRoutable(string $class_name): void
	{
		$this->assertTrue(is_a($class_name, Routable::class, true));
	}

	#[DataProvider('provideClassAndRouteCases')]
	public function testStructure(string $class_name, string $route, array $params): void
	{
		$actual = $class_name::buildRoute($params);

		$this->assertCount(2, $actual);
		$this->assertArrayHasKey('route', $actual);
		$this->assertIsArray($actual['route']);
		$this->assertArrayHasKey('params', $actual);
		$this->assertIsArray($actual['params']);
	}

	#[DataProvider('provideRouteCases')]
	public function testRoute(string $route, array $params): void
	{
		$this->assertSame($route, QueryString::buildRoute($params));

		$pos = strpos($route, '?');

		// The query string is split off by PHP in the normal request flow.
		if ($pos !== false) {
			$query = substr($route, $pos + 1);
			parse_str($query, $output);
			$route = substr($route, 0, $pos);
		}

		$this->assertSame($params, QueryString::parseRoute($route, $output ?? []));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Provides the Routable classes to test.
	 *
	 * The provider key is used as the corresponding action name in
	 * {@see Forum::$actions}.
	 *
	 * @return iterable<string, array{0: class-string}>
	 */
	abstract public static function provideClassNameCases(): iterable;

	/**
	 * @return iterable<string, array{
	 *     0: string,
	 *     1: array<string, string>,
	 * }>
	 */
	abstract public static function provideRouteCases(): iterable;

	final public static function provideClassAndRouteCases(): iterable
	{
		// PHPUnit annoyingly does not support multiple data providers anymore,
		// so we must manually create the cross product ourselves.
		foreach (static::provideClassNameCases() as [$class_name]) {
			foreach (static::provideRouteCases() as $name => [$route, $params]) {
				yield $name => [$class_name, $route, $params];
			}
		}
	}

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		foreach (static::provideClassNameCases() as $action => [$class_name]) {
			self::$original_actions[$action] = Forum::$actions[$action] ?? null;
			Forum::$actions[$action] = ['', $class_name];
		}
	}

	public static function tearDownAfterClass(): void
	{
		foreach (self::$original_actions as $action => $original) {
			if ($original === null) {
				unset(Forum::$actions[$action]);
			} else {
				Forum::$actions[$action] = $original;
			}
		}

		self::$original_actions = [];

		parent::tearDownAfterClass();
	}
}
