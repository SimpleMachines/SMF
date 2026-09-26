<?php

declare(strict_types=1);

namespace SMF\Tests\Unit\Route;

use SMF\Actions\Profile\Main as Profile;
use SMF\Tests\TestCase\AbstractRouteTestCase;
use SMF\Tests\TestCase\SlugRouteTestTrait;

class ProfileRouteTest extends AbstractRouteTestCase
{
	use SlugRouteTestTrait;

	/***********************
	 * Public static methods
	 ***********************/

	public static function provideIncorrectSlugRouteCases(): iterable
	{
		yield 'incorrect name' => [
			['members', 'completely-wrong-name-42'],
			[
				'action' => 'profile',
				'u' => '42',
			],
			'completely-wrong-name',
		];

		yield 'different name' => [
			['members', 'another-name-42'],
			[
				'action' => 'profile',
				'u' => '42',
			],
			'another-name',
		];
	}

	public static function provideCorrectSlugRouteCases(): iterable
	{
		yield 'correct name' => [
			['members', 'john-doe-42'],
			[
				'action' => 'profile',
				'u' => '42',
			],
		];
	}

	public static function provideSlugCases(): iterable
	{
		yield 'member 42' => ['member', 42, 'john-doe'];
	}

	public static function provideClassNameCases(): iterable
	{
		yield 'profile' => [Profile::class];
	}

	public static function provideRouteCases(): iterable
	{
		yield 'profile area' => [
			'/members/42/info',
			[
				'action' => 'profile',
				'u' => '42',
				'area' => 'info',
			],
		];

		yield 'profile' => [
			'/members/42',
			[
				'action' => 'profile',
				'u' => '42',
			],
		];


		yield 'profile subaction' => [
			'/members/42/info/details',
			[
				'action' => 'profile',
				'u' => '42',
				'area' => 'info',
				'sa' => 'details',
			],
		];
	}
}
