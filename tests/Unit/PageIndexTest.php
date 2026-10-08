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
	 * A start is clamped to the first position of a real page.
	 *
	 * Negative starts are invalid, but are clamped to zero. The invalid state
	 * is retained so that page 1 is rendered as a link rather than as the
	 * current page.
	 *
	 * Expected: each case in pageIndexProvider() leaves both the start that was
	 *           passed in and PageIndex::$start at the expected start, sets
	 *           Utils::$context['current_page'] to the expected zero-based
	 *           page, and keeps all three after the object is cast to a string.
	 */
	#[DataProvider('pageIndexProvider')]
	public function testStartIsNormalised(int $start, int $num_items, int $num_per_page, int $expected_start, int $expected_page): void
	{
		$page_index = new PageIndex('querystring', $start, $num_items, $num_per_page);

		$this->assertSame($expected_start, $start);
		$this->assertSame($expected_start, $page_index->start);
		$this->assertSame($expected_page, Utils::$context['current_page']);

		// Force __toString() to exercise the second fixStart() call as well.
		(string) $page_index;

		$this->assertSame($expected_start, $start);
		$this->assertSame($expected_start, $page_index->start);
	}

	/**
	 * The rendered current page is one-based, while current_page is zero-based.
	 *
	 * Expected: each case in pageRenderingProvider() sets
	 *           Utils::$context['current_page'] to the expected page minus one
	 *           and renders the expected page in a
	 *           <span class="current_page"> element.
	 */
	#[DataProvider('pageRenderingProvider')]
	public function testCurrentPageIsRendered(int $start, int $num_items, int $num_per_page, int $expected_page): void
	{
		$page_index = new PageIndex('querystring', $start, $num_items, $num_per_page);

		$this->assertSame($expected_page - 1, Utils::$context['current_page']);

		$this->assertStringContainsString(\sprintf('<span class="current_page">%d</span>', $expected_page), (string) $page_index);
	}

	/**
	 * A negative start links the first page instead of marking it as current.
	 *
	 * Expected: new PageIndex() with a start of -1, 100 items and 20 per page
	 *           clamps the start to 0 and, cast to a string, links page 1 with
	 *           start=0 and contains no current_page.
	 * Guards:   __toString() clamped the start a second time, found nothing
	 *           wrong with the 0 it was now given, and overwrote the record
	 *           that the caller's start had been out of bounds. Page 1 came out
	 *           as plain text rather than a link, so the first page of every
	 *           multi-page topic on the message index could not be clicked.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/7887c1b28 Introduced by "Implements SMF\PageIndex"
	 * @link https://github.com/SimpleMachines/SMF/pull/9453
	 */
	public function testANegativeStartLinksTheFirstPageInsteadOfMarkingIt(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$page_index = (string) $page_index;

		$this->assertSame(0, $start);

		$this->assertStringContainsString('<a class="nav_page" href="https://example.com/index.php?board=1.0;start=0">1</a>', $page_index);

		$this->assertStringNotContainsString('current_page', $page_index);
	}

	/**
	 * A negative start shows neither a previous nor a next page link.
	 *
	 * Expected: new PageIndex() with a start of -1, 100 items and 20 per page
	 *           renders a string containing neither previous_page nor
	 *           next_page.
	 * Guards:   the record that the start was out of bounds was lost when
	 *           __toString() clamped the start again, so page 1 was treated as
	 *           the current page and got a next page link beside it.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/7887c1b28 Introduced by "Implements SMF\PageIndex"
	 * @link https://github.com/SimpleMachines/SMF/pull/9453
	 */
	public function testANegativeStartShowsNeitherPreviousNorNextLinks(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$page_index = (string) $page_index;

		$this->assertStringNotContainsString('previous_page', $page_index);
		$this->assertStringNotContainsString('next_page', $page_index);
	}

	/**
	 * Casting to a string twice gives the same string both times.
	 *
	 * The string is built in __toString() rather than the constructor so that
	 * it reflects any property the caller changed in between, which means it
	 * has to survive being asked more than once.
	 *
	 * Expected: (string) $page_index returns the same value on a second call.
	 */
	public function testAskingTwiceGivesTheSameAnswer(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame((string) $page_index, (string) $page_index);
	}

	/**
	 * The start value is clamped and handed back to the caller.
	 *
	 * Expected: new PageIndex() with a start of -1 sets the variable passed in
	 *           to 0.
	 */
	public function testTheStartValueIsClampedAndHandedBackToTheCaller(): void
	{
		$start = -1;

		new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame(0, $start);
	}

	/**
	 * A start that names a page marks that page as current.
	 *
	 * This is the control for the negative start tests. A start that names a
	 * real page marks that page as the current one and links the pages either
	 * side of it.
	 *
	 * Expected: new PageIndex() with a start of 40, 100 items and 20 per page
	 *           sets current_page to 2, marks page 3 as current, and renders
	 *           both previous_page and next_page.
	 */
	public function testAStartThatNamesAPageMarksThatPageAsCurrent(): void
	{
		$start = 40;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$page_index = (string) $page_index;

		$this->assertSame(2, Utils::$context['current_page']);
		$this->assertStringContainsString('<span class="current_page">3</span>', $page_index);
		$this->assertStringContainsString('previous_page', $page_index);
		$this->assertStringContainsString('next_page', $page_index);
	}

	/**
	 * A start in the middle of a page is moved to the start of that page.
	 *
	 * A start in the middle of a page belongs to that page, and the caller is
	 * told which page that turned out to be.
	 *
	 * Expected: new PageIndex() with a start of 45, 100 items and 20 per page
	 *           sets both the passed variable and PageIndex::$start to 40,
	 *           sets current_page to 2, and marks page 3 as current.
	 */
	public function testAStartInTheMiddleOfAPageIsMovedToItsStart(): void
	{
		$start = 45;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame(40, $start);
		$this->assertSame(40, $page_index->start);
		$this->assertSame(2, Utils::$context['current_page']);

		$this->assertStringContainsString('<span class="current_page">3</span>', (string) $page_index);
	}

	/**
	 * A start past the end lands on the last page.
	 *
	 * A start past the end is clamped to the last page, and that is a real
	 * page, so it is marked rather than linked.
	 *
	 * Expected: new PageIndex() with a start of 500, 100 items and 20 per page
	 *           sets both the passed variable and PageIndex::$start to 80,
	 *           sets current_page to 4, marks page 5 as current, and renders
	 *           no next_page.
	 */
	public function testAStartPastTheEndLandsOnTheLastPage(): void
	{
		$start = 500;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$this->assertSame(80, $start);
		$this->assertSame(80, $page_index->start);
		$this->assertSame(4, Utils::$context['current_page']);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('<span class="current_page">5</span>', $page_index);
		$this->assertStringNotContainsString('next_page', $page_index);
	}

	/**
	 * The short format puts the bare offset into the URL.
	 *
	 * Expected: new PageIndex('index.php?board=1.%1$d', ..., 100, 20, true)
	 *           links page 1 as index.php?board=1.0 and page 5 as
	 *           index.php?board=1.80.
	 */
	public function testShortFormatUsesOffsetInTheUrl(): void
	{
		$start = 20;
		$page_index = new PageIndex('index.php?board=1.%1$d', $start, 100, 20, true);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('href="index.php?board=1.0"', $page_index);
		$this->assertStringContainsString('href="index.php?board=1.80">5</a>', $page_index);
	}

	/**
	 * The default format appends a start parameter to the URL.
	 *
	 * Expected: new PageIndex('index.php?board=1', ..., 100, 20) links page 1
	 *           as index.php?board=1;start=0 and page 3 as
	 *           index.php?board=1;start=40.
	 */
	public function testDefaultFormatUsesStartParameterInTheUrl(): void
	{
		$start = 20;
		$page_index = new PageIndex('index.php?board=1', $start, 100, 20);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('href="index.php?board=1;start=0"', $page_index);

		$this->assertStringContainsString('href="index.php?board=1;start=40"', $page_index);
	}

	/**
	 * Previous and next page links can be switched off.
	 *
	 * Expected: new PageIndex() with show_prevnext false, on page 3 of 5,
	 *           renders neither previous_page nor next_page and still marks
	 *           page 3 as current.
	 */
	public function testPreviousAndNextLinksCanBeDisabled(): void
	{
		$start = 40;
		$page_index = new PageIndex('index.php?board=1', $start, 100, 20, false, false);

		$page_index = (string) $page_index;

		$this->assertStringNotContainsString('previous_page', $page_index);
		$this->assertStringNotContainsString('next_page', $page_index);
		$this->assertStringContainsString('<span class="current_page">3</span>', $page_index);
	}

	/**
	 * Template overrides replace the default templates.
	 *
	 * Expected: with overrides for current_page, page, previous_page and
	 *           next_page, the string contains the overridden current page,
	 *           page link, PREVIOUS and NEXT.
	 */
	public function testTemplateOverridesReplaceDefaultTemplates(): void
	{
		$start = 20;

		$page_index = new PageIndex('index.php?board=1', $start, 100, 20, false, true, [
			'current_page' => '<strong>%1$d</strong> ',
			'page' => '<span data-page="{URL}">%2$s</span> ',
			'previous_page' => 'PREVIOUS ',
			'next_page' => 'NEXT ',
		]);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('<strong>2</strong>', $page_index);
		$this->assertStringContainsString('<span data-page="index.php?board=1;start=0">1</span>', $page_index);
		$this->assertStringContainsString('PREVIOUS', $page_index);
		$this->assertStringContainsString('NEXT', $page_index);
	}

	/**
	 * An override for a template that does not exist is ignored.
	 *
	 * Expected: new PageIndex() given an override named does_not_exist renders
	 *           a string that does not contain that override's text.
	 */
	public function testUnknownTemplateOverrideIsIgnored(): void
	{
		$start = 0;

		$page_index = new PageIndex('index.php?board=1', $start, 40, 20, false, true, [
			'does_not_exist' => 'unexpected',
		]);

		$this->assertStringNotContainsString('unexpected', (string) $page_index);
	}

	/**
	 * Template overrides can be applied after construction.
	 *
	 * Expected: calling setTemplateOverrides() on an existing PageIndex makes
	 *           its string contain the overridden current page, BACK and
	 *           FORWARD.
	 */
	public function testSetTemplateOverridesCanBeAppliedAfterConstruction(): void
	{
		$start = 20;

		$page_index = new PageIndex('index.php?board=1', $start, 100, 20);

		$page_index->setTemplateOverrides([
			'current_page' => '<b>%1$d</b> ',
			'previous_page' => 'BACK ',
			'next_page' => 'FORWARD ',
		]);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('<b>2</b>', $page_index);
		$this->assertStringContainsString('BACK', $page_index);
		$this->assertStringContainsString('FORWARD', $page_index);
	}

	/**
	 * Compact pages can be disabled.
	 *
	 * Expected: with compactTopicPagesEnable at 0, a start of 40, 300 items and
	 *           20 per page renders all of pages 1 to 15 and no expandPages.
	 */
	public function testCompactPagesCanBeDisabled(): void
	{
		Config::$modSettings['compactTopicPagesEnable'] = 0;

		$start = 40;
		$page_index = new PageIndex('index.php?board=1', $start, 300, 20);

		$page_index = (string) $page_index;

		for ($page = 1; $page <= 15; $page++) {
			$this->assertMatchesRegularExpression(\sprintf('/>%d<\/(?:span|a)>/', $page), $page_index);
		}

		$this->assertStringNotContainsString('expandPages', $page_index);
	}

	/**
	 * Compact pages can be enabled.
	 *
	 * Expected: with compactTopicPagesEnable at 1 and
	 *           compactTopicPagesContiguous at 5, a start of 140, 300 items and
	 *           20 per page marks page 8 as current and renders expandPages.
	 */
	public function testCompactPagesCanBeEnabled(): void
	{
		Config::$modSettings['compactTopicPagesEnable'] = 1;
		Config::$modSettings['compactTopicPagesContiguous'] = 5;

		$start = 140;
		$page_index = new PageIndex('index.php?board=1', $start, 300, 20);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('<span class="current_page">8</span>', $page_index);

		$this->assertStringContainsString('expandPages', $page_index);
	}

	/**
	 * An odd number of contiguous compact pages is rounded down.
	 *
	 * Expected: with compactTopicPagesContiguous at 4, a start of 140, 300
	 *           items and 20 per page still marks page 8 as current.
	 */
	public function testOddCompactPageCountIsRoundedDown(): void
	{
		Config::$modSettings['compactTopicPagesEnable'] = 1;
		Config::$modSettings['compactTopicPagesContiguous'] = 4;

		$start = 140;
		$page_index = new PageIndex('index.php?board=1', $start, 300, 20);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('<span class="current_page">8</span>', $page_index);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Provides values that exercise start normalisation.
	 *
	 * @return array<string, array{int, int, int, int, int}>
	 */
	public static function pageIndexProvider(): array
	{
		return [
			'negative two' => [-2, 15, 5, 0, 0],
			'negative one' => [-1, 15, 5, 0, 0],
			'zero' => [0, 15, 5, 0, 0],

			'first page' => [1, 15, 5, 0, 0],
			'page two' => [5, 15, 5, 5, 1],
			'page two through four' => [6, 15, 5, 5, 1],
			'page three' => [10, 15, 5, 10, 2],

			'past last page' => [15, 15, 5, 10, 2],
			'far past last page' => [21, 15, 5, 10, 2],

			'large values' => [3000001, 4205, 42, 4200, 100],

			'max sixteen' => [6, 16, 5, 5, 1],
			'max seventeen' => [6, 17, 5, 5, 1],
			'max eighteen' => [6, 18, 5, 5, 1],
			'max nineteen' => [6, 19, 5, 5, 1],
		];
	}

	/**
	 * Provides starts that should identify a particular rendered page.
	 *
	 * @return array<string, array{int, int, int, int}>
	 */
	public static function pageRenderingProvider(): array
	{
		return [
			'first page' => [0, 100, 20, 1],
			'second page' => [20, 100, 20, 2],
			'middle page' => [40, 100, 20, 3],
			'last page' => [80, 100, 20, 5],
		];
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
