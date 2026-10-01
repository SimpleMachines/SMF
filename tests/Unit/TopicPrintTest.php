<?php

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

	public function testItFallsBackToTheDefaultWhenAllIsNeverShown(): void
	{
		// A print page used to hold an entire topic no matter how long it was,
		// which let a single request parse every post in it at once.
		$this->assertSame(TopicPrint::DEFAULT_MAX_POSTS, $this->getPostsPerPage());
	}

	// Note: the section banner above must not be the first thing in this group when
	// the first member carries an attribute. The SMF/section_comments fixer inserts
	// the banner between the attribute and its method.
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
