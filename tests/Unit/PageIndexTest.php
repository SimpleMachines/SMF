<?php

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
	 * Negative starts are invalid, but are clamped to zero. The invalid state
	 * is retained so that page 1 is rendered as a link rather than as the
	 * current page.
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
	 * The rendered current page is one-based, while current_page in the
	 * context is zero-based.
	 */
	#[DataProvider('pageRenderingProvider')]
	public function testCurrentPageIsRendered(int $start, int $num_items, int $num_per_page, int $expected_page): void
	{
		$page_index = new PageIndex('querystring', $start, $num_items, $num_per_page);

		$this->assertSame($expected_page - 1, Utils::$context['current_page']);

		$this->assertStringContainsString(\sprintf('<span class="current_page">%d</span>', $expected_page), (string) $page_index);
	}

	public function testANegativeStartLinksTheFirstPageInsteadOfMarkingIt(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$page_index = (string) $page_index;

		$this->assertSame(0, $start);

		$this->assertStringContainsString('<a class="nav_page" href="https://example.com/index.php?board=1.0;start=0">1</a>', $page_index);

		$this->assertStringNotContainsString('current_page', $page_index);
	}

	public function testANegativeStartShowsNeitherPreviousNorNextLinks(): void
	{
		$start = -1;
		$page_index = new PageIndex('https://example.com/index.php?board=1.0', $start, 100, 20);

		$page_index = (string) $page_index;

		$this->assertStringNotContainsString('previous_page', $page_index);
		$this->assertStringNotContainsString('next_page', $page_index);
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

		$page_index = (string) $page_index;

		$this->assertSame(2, Utils::$context['current_page']);
		$this->assertStringContainsString('<span class="current_page">3</span>', $page_index);
		$this->assertStringContainsString('previous_page', $page_index);
		$this->assertStringContainsString('next_page', $page_index);
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
		$this->assertSame(40, $page_index->start);
		$this->assertSame(2, Utils::$context['current_page']);

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
		$this->assertSame(80, $page_index->start);
		$this->assertSame(4, Utils::$context['current_page']);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('<span class="current_page">5</span>', $page_index);
		$this->assertStringNotContainsString('next_page', $page_index);
	}

	public function testShortFormatUsesOffsetInTheUrl(): void
	{
		$start = 20;
		$page_index = new PageIndex('index.php?board=1.%1$d', $start, 100, 20, true);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('href="index.php?board=1.0"', $page_index);
		$this->assertStringContainsString('href="index.php?board=1.80">5</a>', $page_index);
	}

	public function testDefaultFormatUsesStartParameterInTheUrl(): void
	{
		$start = 20;
		$page_index = new PageIndex('index.php?board=1', $start, 100, 20);

		$page_index = (string) $page_index;

		$this->assertStringContainsString('href="index.php?board=1;start=0"', $page_index);

		$this->assertStringContainsString('href="index.php?board=1;start=40"', $page_index);
	}

	public function testPreviousAndNextLinksCanBeDisabled(): void
	{
		$start = 40;
		$page_index = new PageIndex('index.php?board=1', $start, 100, 20, false, false);

		$page_index = (string) $page_index;

		$this->assertStringNotContainsString('previous_page', $page_index);
		$this->assertStringNotContainsString('next_page', $page_index);
		$this->assertStringContainsString('<span class="current_page">3</span>', $page_index);
	}

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

	public function testUnknownTemplateOverrideIsIgnored(): void
	{
		$start = 0;

		$page_index = new PageIndex('index.php?board=1', $start, 40, 20, false, true, [
			'does_not_exist' => 'unexpected',
		]);

		$this->assertStringNotContainsString('unexpected', (string) $page_index);
	}

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
