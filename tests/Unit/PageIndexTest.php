<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\PageIndex;
use SMF\Utils;

/**
 * Covers SMF\PageIndex.
 *
 * It builds a string out of its own properties, a couple of modSettings keys
 * and one language string, and it takes the theme's page_index settings only
 * if a theme has been loaded. Nothing here needs a database.
 */
#[CoversClass(PageIndex::class)]
class PageIndexTest extends TestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array The settings this class reads, as they were before the test.
	 */
	private array $backup = [];

	/**
	 * @var bool Whether Utils::$context already had a current_page.
	 */
	private bool $had_current_page = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * A negative start is how a caller says "no particular page was asked
	 * for". The message index passes one for a topic whose first page is being
	 * linked, and the page index answers by clamping the start to zero and
	 * linking page 1 rather than marking it as the page you are on.
	 *
	 * fixStart() records that verdict as a side effect of clamping, and
	 * __toString() calls it again on a start that has already been clamped, so
	 * the second answer is always "valid" and the first was thrown away. Page 1
	 * came out as plain text with no link on it, and a "next page" link
	 * appeared beside it pointing at page 2.
	 */
	public function testANegativeStartLinksTheFirstPageInsteadOfMarkingIt(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertStringContainsString(
			'<a class="nav_page" href="https://example.com/index.php?board=1.0;start=0">1</a>',
			(string) $page_index,
		);

		$this->assertStringNotContainsString('current_page', (string) $page_index);
	}

	/**
	 * Nothing was navigated away from, so there is nowhere to go back to and
	 * nothing to go on to.
	 */
	public function testANegativeStartShowsNeitherPreviousNorNextLinks(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertStringNotContainsString('previous_page', (string) $page_index);
		$this->assertStringNotContainsString('next_page', (string) $page_index);
	}

	/**
	 * The string is built in __toString() rather than the constructor so that
	 * it reflects any property the caller changed in between, which means it
	 * has to survive being asked more than once.
	 */
	public function testAskingTwiceGivesTheSameAnswer(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame((string) $page_index, (string) $page_index);
	}

	public function testTheStartValueIsClampedAndHandedBackToTheCaller(): void
	{
		$start = -1;
		new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame(0, $start);
	}

	/**
	 * The control. A start that names a real page marks that page as the
	 * current one and links the pages either side of it.
	 */
	public function testAStartThatNamesAPageMarksThatPageAsCurrent(): void
	{
		$start = 40;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertStringContainsString('<span class="current_page">3</span>', (string) $page_index);
		$this->assertStringContainsString('previous_page', (string) $page_index);
		$this->assertStringContainsString('next_page', (string) $page_index);
	}

	/**
	 * A start in the middle of a page belongs to that page, and the caller is
	 * told which page that turned out to be.
	 */
	public function testAStartInTheMiddleOfAPageIsMovedToItsStart(): void
	{
		$start = 45;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame(40, $start);
		$this->assertStringContainsString('<span class="current_page">3</span>', (string) $page_index);
	}

	/**
	 * A start past the end is clamped to the last page, and that is a real
	 * page, so it is marked rather than linked.
	 */
	public function testAStartPastTheEndLandsOnTheLastPage(): void
	{
		$start = 500;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame(80, $start);
		$this->assertStringContainsString('<span class="current_page">5</span>', (string) $page_index);
		$this->assertStringNotContainsString('next_page', (string) $page_index);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		foreach (['compactTopicPagesEnable', 'compactTopicPagesContiguous'] as $key) {
			if (isset(Config::$modSettings[$key])) {
				$this->backup[$key] = Config::$modSettings[$key];
			}

			unset(Config::$modSettings[$key]);
		}

		$this->had_current_page = isset(Utils::$context['current_page']);
	}

	/**
	 * PHPUnit does not reset SMF's statics between tests, and the constructor
	 * fills in Utils::$context['current_page'] when nothing else has, so both
	 * that and the settings have to go back the way they were.
	 */
	protected function tearDown(): void
	{
		foreach (['compactTopicPagesEnable', 'compactTopicPagesContiguous'] as $key) {
			unset(Config::$modSettings[$key]);

			if (isset($this->backup[$key])) {
				Config::$modSettings[$key] = $this->backup[$key];
			}
		}

		if (!$this->had_current_page) {
			unset(Utils::$context['current_page']);
		}

		$this->backup = [];
	}
}
