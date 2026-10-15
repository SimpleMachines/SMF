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

namespace SMF\Actions\Test;

use SMF\ActionRouter;
use SMF\Routable;

/**
 * Test fixture for ActionRouter.
 */
class TestActionRouter implements Routable
{
	use ActionRouter;
}

namespace SMF\Tests\Unit\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use SMF\ActionRouter;
use SMF\Actions\Test\TestActionRouter;
use SMF\Tests\TestCase\AbstractRouteTestCase;

#[CoversClass(TestActionRouter::class)]
#[CoversTrait(ActionRouter::class)]
class ActionRouterTest extends AbstractRouteTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * A set of parameters is built into an action route.
	 *
	 * Expected: each case in buildRouteProvider() makes buildRoute() return the
	 *           expected route segments and the parameters left over.
	 */
	#[DataProvider('buildRouteProvider')]
	public function testBuildsAnActionRoute(array $params, array $expected): void
	{
		$this->assertSame(
			$expected,
			TestActionRouter::buildRoute($params),
		);
	}

	/**
	 * An action route is parsed into the action, area and sub-action.
	 *
	 * Expected: each case in parseRouteProvider() makes parseRoute() return the
	 *           expected parameters.
	 */
	#[DataProvider('parseRouteProvider')]
	public function testParsesAnActionRoute(array $route, array $expected): void
	{
		$this->assertSame(
			$expected,
			TestActionRouter::parseRoute($route),
		);
	}

	/**
	 * A route that starts with an unknown action is not parsed.
	 *
	 * Expected: parseRoute(['unknown-action', 'foo']) returns an empty array.
	 */
	public function testDoesNotParseAnUnknownAction(): void
	{
		$this->assertEmpty(TestActionRouter::parseRoute(['unknown-action', 'foo']));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return iterable<string, array{
	 *     0: array<string, string>,
	 *     1: array{
	 *         route: array<int, string>,
	 *         params: array<string, string>,
	 *     },
	 * }>
	 */
	public static function buildRouteProvider(): iterable
	{
		yield 'action only' => [
			[
				'action' => 'test',
			],
			[
				'route' => ['test'],
				'params' => [],
			],
		];

		yield 'action with unused parameters' => [
			[
				'action' => 'test',
				'foo' => 'bar',
			],
			[
				'route' => ['test'],
				'params' => [
					'foo' => 'bar',
				],
			],
		];

		yield 'default area without sub-action' => [
			[
				'action' => 'test',
				'area' => 'index',
			],
			[
				'route' => ['test'],
				'params' => [],
			],
		];

		yield 'default area with sub-action' => [
			[
				'action' => 'test',
				'area' => 'index',
				'sa' => 'foo',
			],
			[
				'route' => ['test', 'index', 'foo'],
				'params' => [],
			],
		];

		yield 'non-default area' => [
			[
				'action' => 'test',
				'area' => 'settings',
			],
			[
				'route' => ['test', 'settings'],
				'params' => [],
			],
		];

		yield 'area and sub-action' => [
			[
				'action' => 'test',
				'area' => 'settings',
				'sa' => 'advanced',
			],
			[
				'route' => ['test', 'settings', 'advanced'],
				'params' => [],
			],
		];

		yield 'sub-action without area' => [
			[
				'action' => 'test',
				'sa' => 'advanced',
			],
			[
				'route' => ['test', 'advanced'],
				'params' => [],
			],
		];
	}

	/**
	 * @return iterable<string, array{
	 *     0: array<int, string>,
	 *     1: array<string, string>,
	 * }>
	 */
	public static function parseRouteProvider(): iterable
	{
		yield 'action only' => [
			['test'],
			[
				'action' => 'test',
			],
		];

		yield 'action and area' => [
			['test', 'settings'],
			[
				'action' => 'test',
				'area' => 'settings',
			],
		];

		yield 'action, area and sub-action' => [
			['test', 'settings', 'advanced'],
			[
				'action' => 'test',
				'area' => 'settings',
				'sa' => 'advanced',
			],
		];
	}

	/**
	 * Provides the action classes used by the inherited route tests.
	 *
	 * The key is registered as the action name in Forum::$actions by
	 * AbstractRouteTestCase.
	 *
	 * @return iterable<string, array{0: class-string}>
	 */
	public static function provideClassNameCases(): iterable
	{
		yield 'test' => [TestActionRouter::class];
	}

	/**
	 * Provides generic action routes used by the inherited route tests.
	 *
	 * @return iterable<string, array{
	 *     0: string,
	 *     1: array<string, string>,
	 * }>
	 */
	public static function provideRouteCases(): iterable
	{
		yield 'action only' => [
			'/test',
			[
				'action' => 'test',
			],
		];

		yield 'action and area' => [
			'/test/settings',
			[
				'action' => 'test',
				'area' => 'settings',
			],
		];

		yield 'action, area and sub-action' => [
			'/test/settings/advanced',
			[
				'action' => 'test',
				'area' => 'settings',
				'sa' => 'advanced',
			],
		];

		yield 'action with unused parameters' => [
			'/test/?foo=bar',
			[
				'action' => 'test',
				'foo' => 'bar',
			],
		];

		yield 'missing action' => [
			'/?foo=bar',
			[
				'foo' => 'bar',
			],
		];
	}
}
