<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

/**
 * Constructs a page list.
 *
 * E.g.: 1 ... 6 7 [8] 9 10 ... 15.
 */
class PageIndex implements \Stringable
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'load' => 'constructPageIndex',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The base URL for all the page index links.
	 */
	public string $base_url;

	/**
	 * @var int
	 *
	 * The value we are starting at.
	 *
	 * Should be the number of a specific item in the overall paginated list.
	 */
	public int $start;

	/**
	 * @var int
	 *
	 * The total number of items in the overall paginated list.
	 */
	public int $max_value;

	/**
	 * @var int
	 *
	 * How many items to show per page.
	 */
	public int $num_per_page;

	/**
	 * @var bool
	 *
	 * Whether to use "url.offset" format instead of "url;start=offset" format.
	 */
	public bool $short_format = false;

	/**
	 * @var bool
	 *
	 * Set this to false to hide the previous page and next page arrow links.
	 */
	public bool $show_prevnext = true;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Template string for the bit to show before the page links begin.
	 */
	private string $extra_before = '<span class="pages">{txt_pages}</span>';

	/**
	 * @var string
	 *
	 * Template string for previous page arrow link.
	 */
	private string $previous_page = '<span class="main_icons previous_page"></span>';

	/**
	 * @var string
	 *
	 * Template string for the current page.
	 */
	private string $current_page = '<span class="current_page">%1$d</span> ';

	/**
	 * @var string
	 *
	 * Template string for links to numbered pages.
	 */
	private string $page = '<a class="nav_page" href="{URL}">%2$s</a> ';

	/**
	 * @var string
	 *
	 * Template string for the "..." bit in long page indexes.
	 */
	private string $expand_pages = '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>';

	/**
	 * @var string
	 *
	 * Template string for next page arrow link.
	 */
	private string $next_page = '<span class="main_icons next_page"></span>';

	/**
	 * @var string
	 *
	 * Template string for the bit to show after the page links end.
	 */
	private string $extra_after = '';

	/**
	 * @var string
	 *
	 * Template string used to create links to specific pages.
	 *
	 * This is built by inserting $this->base_url into $this->page.
	 */
	private string $base_link;

	/**
	 * @var int
	 *
	 * Item number of the first item on the last page.
	 */
	private int $last_page_value;

	/**
	 * @var int
	 *
	 * The number of the page that the user is currently viewing.
	 */
	private int $current_page_num;

	/**
	 * @var int
	 *
	 * The number of the last page.
	 */
	private int $last_page_num;

	/**
	 * @var int
	 *
	 * How many contiguous pages to show in the middle when using the compact
	 * page index.
	 *
	 * For example:
	 *    3 to display: 1 ... 7 [8] 9 ... 15
	 *    5 to display: 1 ... 6 7 [8] 9 10 ... 15
	 */
	private int $page_contiguous;

	/**
	 * @var bool
	 *
	 * Tracks whether the requested $start value was out of bounds.
	 */
	private bool $start_invalid;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * - short_format causes it to use "url.page" instead of "url;start=page".
	 *
	 * - very importantly, cleans up the start value passed, and forces it to
	 *   be a multiple of num_per_page.
	 *
	 * - checks that start is not more than max_value.
	 *
	 * - base_url should be the URL without any start parameter on it.
	 *
	 * - uses the compactTopicPagesEnable and compactTopicPagesContiguous
	 *   settings to decide how to display the menu.
	 *
	 * @param string $base_url The basic URL to be used for each link.
	 * @param int &$start The start position, by reference. If this is not a
	 *    multiple of the number of items per page, it is sanitized to be so and
	 *    the value will persist upon the function's return.
	 * @param int $max_value The total number of items you are paginating for.
	 * @param int $num_per_page The number of items to be displayed on a given
	 *    page. $start will be forced to be a multiple of this value.
	 * @param bool $short_format Whether to use "url.offset" instead of
	 *    "url;start=offset". Default: false.
	 * @param bool $show_prevnext Whether the Previous and Next links should be
	 *    shown. Default: true.
	 */
	public function __construct(string $base_url, int &$start, int $max_value, int $num_per_page, bool $short_format = false, bool $show_prevnext = true)
	{
		$this->base_url = $base_url;
		$this->max_value = $max_value;
		$this->num_per_page = $num_per_page;
		$this->short_format = $short_format;
		$this->show_prevnext = $show_prevnext;
		$this->start = $start = $this->fixStart($start);

		$this->extra_before = str_replace('{txt_pages}', Lang::$txt['pages'], $this->extra_before);

		if (isset(Theme::$current->settings['page_index'])) {
			foreach (Theme::$current->settings['page_index'] as $key => $value) {
				if (property_exists($this, $key)) {
					$this->{$key} = $value;
				}
			}
		}

		if (!isset(Utils::$context['current_page'])) {
			Utils::$context['current_page'] = $this->start / $this->num_per_page;
		}
	}

	/**
	 * Finalizes and returns the page index links.
	 */
	public function __toString()
	{
		// Why do all this work here rather than in the constructor, you ask?
		// Because the string should always reflect the object's current
		// property values, which may have changed after initial constuction.

		// Ensure $this->start is still good, just in case someone changed it.
		$this->start = $this->fixStart($this->start);

		// Set some other internal values we'll need below.
		$this->last_page_value = $this->max_value - $this->max_value % $this->num_per_page;
		$this->current_page_num = $this->start / $this->num_per_page + 1;
		$this->last_page_num = $this->last_page_value / $this->num_per_page + 1;
		$this->base_link = strtr($this->page, ['{URL}' => $this->short_format ? $this->base_url : strtr($this->base_url, ['%' => '%%']) . ';start=%1$d']);

		// If they didn't enter an odd value, pretend they did.
		$this->page_contiguous = (int) ((Config::$modSettings['compactTopicPagesContiguous'] ?? 5) - ((Config::$modSettings['compactTopicPagesContiguous'] ?? 5) % 2)) / 2;

		// Now build the page index string.
		$pageindex = $this->extra_before;
		$pageindex .= $this->prevPage();

		// Compact pages is off or on?
		if (empty(Config::$modSettings['compactTopicPagesEnable'])) {
			$pageindex .= $this->pageRange(1, $this->last_page_num);
		} else {
			$pageindex .= $this->firstPage();
			$pageindex .= $this->expandPages();
			$pageindex .= $this->pageRange($this->current_page_num - $this->page_contiguous, $this->current_page_num + $this->page_contiguous);
			$pageindex .= $this->expandPages(true);
			$pageindex .= $this->lastPage();
		}

		$pageindex .= $this->nextPage();
		$pageindex .= $this->extra_after;

		return $pageindex;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(string $base_url, int &$start, int $max_value, int $num_per_page, bool $short_format = false, bool $show_prevnext = true): object
	{
		return new self($base_url, $start, $max_value, $num_per_page, $short_format, $show_prevnext);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Fixes $start if it is out of bounds or not a multiple of
	 * $this->num_per_page.
	 *
	 * @param int $start The start value.
	 * @return int The fixed start value.
	 */
	protected function fixStart(int $start): int
	{
		// Save whether $start was less than 0 or not.
		$this->start_invalid = $start < 0;

		// $start must be within bounds and be a multiple of $this->num_per_page.
		$start = min(max($start, 0), $this->max_value);
		$start -= ($start % $this->num_per_page);

		return $start;
	}

	/**
	 * Show the "prev page" link.
	 * (>prev page< 1 ... 6 7 [8] 9 10 ... 15 next page)
	 */
	protected function prevPage(): string
	{
		if ($this->start != 0 && !$this->start_invalid && $this->show_prevnext) {
			return sprintf($this->base_link, $this->start - $this->num_per_page, $this->previous_page);
		}

		return '';
	}

	/**
	 * Show the "next page" link.
	 * (prev page 1 ... 6 7 [8] 9 10 ... 15 >next page<)
	 */
	protected function nextPage(): string
	{
		if ($this->start != $this->last_page_value && !$this->start_invalid && $this->show_prevnext) {
			return sprintf($this->base_link, $this->start + $this->num_per_page, $this->next_page);
		}

		return '';
	}

	/**
	 * Show the first page in the list.
	 * (prev page >1< ... 6 7 [8] 9 10 ... 15)
	 */
	protected function firstPage(): string
	{
		if ($this->current_page_num - $this->page_contiguous > 1) {
			return sprintf($this->base_link, 0, '1');
		}

		return '';
	}

	/**
	 * Show the last page in the list.
	 * (prev page 1 ... 6 7 [8] 9 10 ... >15<  next page)
	 */
	protected function lastPage(): string
	{
		if ($this->current_page_num + $this->page_contiguous < $this->last_page_num) {
			return sprintf($this->base_link, $this->last_page_value, $this->last_page_num);
		}

		return '';
	}

	/**
	 * Shows a range of pages.
	 *
	 * @param int $min The lowest page number to show.
	 * @param int $max The highest page number to show.
	 */
	protected function pageRange(int $min, int $max): string
	{
		$page_range = '';

		$min = max(1, $min);
		$max = min($this->last_page_num, $max);

		for ($counter = $min; $counter <= $max; $counter++) {
			// Show the current page. (prev page 1 ... 6 7 >[8]< 9 10 ... 15 next page)
			if ($counter == $this->current_page_num) {
				// If start was invalid, show page number as a link to the proper start value.
				if ($this->start_invalid) {
					$page_range .= sprintf($this->base_link, $this->start, $this->current_page_num);
				}
				// Show page number as plain text.
				else {
					$page_range .= sprintf($this->current_page, $this->current_page_num);
				}
			}
			// Show other pages.
			else {
				$page_range .= sprintf($this->base_link, ($counter - 1) * $this->num_per_page, $counter);
			}
		}

		return $page_range;
	}

	/**
	 * Show the ... for the hidden pages.
	 *
	 * Used when compact pages are turned on.
	 *
	 * If $after is false, does the one near the start:
	 * (prev page 1 >...< 6 7 [8] 9 10 ... 15 next page)
	 *
	 * If $after is true, does the one near the end:
	 * (prev page 1 ... 6 7 [8] 9 10 >...< 15 next page)
	 *
	 * @param bool $after If true, do the one near the end.
	 */
	protected function expandPages(bool $after = false): string
	{
		if (empty(Config::$modSettings['compactTopicPagesEnable'])) {
			return '';
		}

		if (!$after) {
			$should_show = $this->start > $this->num_per_page * ($this->page_contiguous + 1);
			$first_page = $this->num_per_page;
			$last_page = $this->start - $this->num_per_page * $this->page_contiguous;
		} else {
			$should_show = $this->start + $this->num_per_page * ($this->page_contiguous + 1) < $this->last_page_value;
			$first_page = $this->start + $this->num_per_page * ($this->page_contiguous + 1);
			$last_page = $this->last_page_value;
		}

		if (!$should_show) {
			return '';
		}

		return strtr($this->expand_pages, [
			'{LINK}' => Utils::JavaScriptEscape(Utils::htmlspecialchars($this->base_link)),
			'{FIRST_PAGE}' => $first_page,
			'{LAST_PAGE}' => $last_page,
			'{PER_PAGE}' => $this->num_per_page,
		]);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\PageIndex::exportStatic')) {
	PageIndex::exportStatic();
}

?>