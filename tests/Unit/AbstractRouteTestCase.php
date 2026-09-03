<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Forum;
use SMF\QueryString;
use SMF\Routable;

abstract class AbstractRouteTestCase extends TestCase
{
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

	abstract public static function provideClassNameCases(): iterable;

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
}
