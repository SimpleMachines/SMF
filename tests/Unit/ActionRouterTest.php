<?php

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

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\ActionRouter;
use SMF\Actions\Test\TestActionRouter;
use SMF\Forum;
use SMF\QueryString;

#[CoversClass(TestActionRouter::class)]
#[CoversTrait(ActionRouter::class)]
class ActionRouterTest extends AbstractRouteTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	private array $originalActions;

	/****************
	 * Public methods
	 ****************/

	#[DataProvider('buildRouteProvider')]
	public function testBuildsAnActionRoute(array $params, array $expected): void
	{
		$this->assertSame(
			$expected,
			TestActionRouter::buildRoute($params),
		);
	}

	#[DataProvider('parseRouteProvider')]
	public function testParsesAnActionRoute(array $route, array $expected): void
	{
		$this->assertSame(
			$expected,
			TestActionRouter::parseRoute($route),
		);
	}

	public function testDoesNotParseAnUnknownAction(): void
	{
		$this->assertEmpty(TestActionRouter::parseRoute(['unknown-action', 'foo']));
	}

	/***********************
	 * Public static methods
	 ***********************/

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

	public static function provideClassNameCases(): iterable
	{
		yield [TestActionRouter::class];
	}

	public static function provideRouteCases(): array
	{
		return [
			'action only' => [
				'/test',
				[
					'action' => 'test',
				],
			],
			'action and area' => [
				'/test/settings',
				[
					'action' => 'test',
					'area' => 'settings',
				],
			],
			'action, area and sub-action' => [
				'/test/settings/advanced',
				[
					'action' => 'test',
					'area' => 'settings',
					'sa' => 'advanced',
				],
			],
			'action with unused parameters' => [
				'/test/?foo=bar',
				[
					'action' => 'test',
					'foo' => 'bar',
				],
			],
			'missing action' => [
				'/?foo=bar',
				[
					'foo' => 'bar',
				],
			],
		];
	}

	/******************
	 * Internal methods
	 ******************/

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
}
