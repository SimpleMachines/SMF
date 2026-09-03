<?php

declare(strict_types=1);

namespace SMF\Actions\Test;

use SMF\ActionRouter;

/**
 * Test fixture for ActionRouter.
 */
class TestActionRouter
{
	use ActionRouter;
}

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Actions\Test\TestActionRouter;
use SMF\Forum;

#[CoversClass(TestActionRouter::class)]
class ActionRouterTest extends TestCase
{
	private array $originalActions;

	protected function setUp(): void
	{
		parent::setUp();

		$this->originalActions = Forum::$actions;

		Forum::$actions['test'] = [
			'Test action',
			TestActionRouter::class,
		];
	}

	protected function tearDown(): void
	{
		Forum::$actions = $this->originalActions;

		parent::tearDown();
	}

	/****************
	 * Public methods
	 ****************/

	/**
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $expected
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
	 * @param array<int, string> $route
	 * @param array<string, mixed> $expected
	 */
	#[DataProvider('parseRouteProvider')]
	public function testParsesAnActionRoute(array $route, array $expected): void
	{
		$this->assertSame(
			$expected,
			TestActionRouter::parseRoute($route),
		);
	}

	public function testParsedRouteParametersOverrideExistingParameters(): void
	{
		$this->assertSame(
			[
				'action' => 'test',
				'area' => 'route-area',
				'sa' => 'route-sa',
				'existing' => 'value',
			],
			TestActionRouter::parseRoute(
				['test', 'route-area', 'route-sa'],
				[
					'action' => 'existing-action',
					'area' => 'existing-area',
					'sa' => 'existing-sa',
					'existing' => 'value',
				],
			),
		);
	}

	/**
	 * An unknown action is left alone and produces no parsed parameters.
	 */
	public function testDoesNotParseAnUnknownAction(): void
	{
		$this->assertSame(
			[],
			TestActionRouter::parseRoute(['unknown-action', 'foo']),
		);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{
	 *     0: array<string, string>,
	 *     1: array{
	 *         route: array<int, string>,
	 *         params: array<string, string>,
	 *     },
	 * }>
	 */
	public static function buildRouteProvider(): array
	{
		return [
			'action only' => [
				[
					'action' => 'test',
				],
				[
					'route' => ['test'],
					'params' => [],
				],
			],
			'action with unused parameters' => [
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
			],
			'default area without sub-action' => [
				[
					'action' => 'test',
					'area' => 'index',
				],
				[
					'route' => ['test'],
					'params' => [],
				],
			],
			'default area with sub-action' => [
				[
					'action' => 'test',
					'area' => 'index',
					'sa' => 'foo',
				],
				[
					'route' => ['test', 'index', 'foo'],
					'params' => [],
				],
			],
			'non-default area' => [
				[
					'action' => 'test',
					'area' => 'settings',
				],
				[
					'route' => ['test', 'settings'],
					'params' => [],
				],
			],
			'area and sub-action' => [
				[
					'action' => 'test',
					'area' => 'settings',
					'sa' => 'advanced',
				],
				[
					'route' => ['test', 'settings', 'advanced'],
					'params' => [],
				],
			],
			'sub-action without area' => [
				[
					'action' => 'test',
					'sa' => 'advanced',
				],
				[
					'route' => ['test', 'advanced'],
					'params' => [],
				],
			],
		];
	}

	/**
	 * @return array<string, array{
	 *     0: array<int, string>,
	 *     1: array<string, string>,
	 * }>
	 */
	public static function parseRouteProvider(): array
	{
		return [
			'action only' => [
				['test'],
				[
					'action' => 'test',
				],
			],
			'action and area' => [
				['test', 'settings'],
				[
					'action' => 'test',
					'area' => 'settings',
				],
			],
			'action, area and sub-action' => [
				['test', 'settings', 'advanced'],
				[
					'action' => 'test',
					'area' => 'settings',
					'sa' => 'advanced',
				],
			],
		];
	}
}
