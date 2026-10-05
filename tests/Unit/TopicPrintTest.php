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

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Actions\TopicPrint;
use SMF\Config;

#[CoversClass(TopicPrint::class)]
class TopicPrintTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * A forum that never shows "All" still gets a limit on a print page.
	 *
	 * Without one, a single request would parse every post in a topic at once.
	 *
	 * Expected: getPostsPerPage() with no enableAllMessages setting returns
	 *           TopicPrint::DEFAULT_MAX_POSTS.
	 */
	public function testItFallsBackToTheDefaultWhenAllIsNeverShown(): void
	{
		$this->assertSame(TopicPrint::DEFAULT_MAX_POSTS, $this->getPostsPerPage());
	}

	// Note: the section banner above must not be the first thing in this group when
	// the first member carries an attribute. The SMF/section_comments fixer inserts
	// the banner between the attribute and its method.
	/**
	 * A print page holds as many posts as the "All" view may show.
	 *
	 * Expected: getPostsPerPage() returns enableAllMessages when it is a
	 *           positive number, and TopicPrint::DEFAULT_MAX_POSTS when it is
	 *           zero, negative or not a number.
	 */
	#[DataProvider('maxTopicSizeProvider')]
	public function testGetPostsPerPage(mixed $enable_all_messages, int $expected): void
	{
		Config::$modSettings['enableAllMessages'] = $enable_all_messages;

		$this->assertSame($expected, $this->getPostsPerPage());
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{mixed, int}>
	 */
	public static function maxTopicSizeProvider(): array
	{
		return [
			'the admin sets the size' => ['50', 50],
			'an integer is accepted too' => [50, 50],
			'zero means the admin never shows all' => ['0', TopicPrint::DEFAULT_MAX_POSTS],
			'a negative size is meaningless' => ['-50', TopicPrint::DEFAULT_MAX_POSTS],
			'so is a non-numeric one' => ['lots', TopicPrint::DEFAULT_MAX_POSTS],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		unset(Config::$modSettings['enableAllMessages']);
	}

	/**
	 * Calls the protected helper under test.
	 */
	private function getPostsPerPage(): int
	{
		$action = (new \ReflectionClass(TopicPrint::class))->newInstanceWithoutConstructor();

		return (new \ReflectionMethod(TopicPrint::class, 'getPostsPerPage'))->invoke($action);
	}
}
