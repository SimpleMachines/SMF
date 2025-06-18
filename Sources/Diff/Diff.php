<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Diff;

use SMF\Parser;
use SMF\Sapi;
use SMF\Utils;

/**
 * Base class for FullDiff and EditDiff.
 */
abstract class Diff
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var int
	 *
	 * If the total length of the changed lines exceeds this limit, then inline
	 * comparisons will not be performed.
	 *
	 * Skipping inline comparisons produces larger output, since it includes
	 * the entirety of each changed line instead of only the changed characters.
	 * But since the time required for inline comparisons grows exponentially
	 * with the line length, it can become too expensive for very large strings.
	 */
	public const INLINE_LIMIT = 20000;

	/**
	 * @var array
	 *
	 * A list of recognized HTML elements and how to prioritize them for showing
	 * changes to their content and markup in $this->formatMarkup().
	 *
	 * If we find nested markup changes when formatting HTML for display, we
	 * need to decide which markup to show as changed. For example, if we have a
	 * `<p>` element that changed one of its attributes but also had changes to
	 * its content, it is more important to highlight the content changes than
	 * it is to mark the whole paragraph as changed due to the attribute change.
	 * In contrast, if an `<a>` element changed the value of its href attribute,
	 * that is more important than any changes to the text content inside the
	 * link, and so the entire link should be marked as a change.
	 *
	 * In situations where these kind of decisions must be made, HTML elements
	 * with a value of true in this list will to prefer to show the changes to
	 * their content (like `<p>` in the example above), whereas elements with a
	 * value of false will prefer to show themselves as changed (like `<a>` in
	 * the example above).
	 */
	public const HTML_DIFF_PREFER_CONTENT = [
		'a' => false,
		'abbr' => false,
		'acronym' => false,
		'address' => true,
		'area' => false,
		'article' => true,
		'aside' => true,
		'audio' => false,
		'b' => false,
		'base' => false,
		'bdi' => false,
		'bdo' => false,
		'big' => false,
		'blockquote' => true,
		'body' => true,
		'br' => false,
		'button' => false,
		'canvas' => false,
		'caption' => true,
		'center' => true,
		'cite' => false,
		'code' => true,
		'col' => true,
		'colgroup' => true,
		'data' => false,
		'datalist' => false,
		'dd' => true,
		'del' => false,
		'details' => true,
		'dfn' => false,
		'dialog' => false,
		'dir' => true,
		'div' => true,
		'dl' => true,
		'dt' => true,
		'em' => false,
		'embed' => false,
		'fencedframe' => false,
		'fieldset' => true,
		'figcaption' => true,
		'figure' => true,
		'font' => false,
		'footer' => true,
		'form' => true,
		'frame' => false,
		'frameset' => false,
		'h1' => true,
		'h2' => true,
		'h3' => true,
		'h4' => true,
		'h5' => true,
		'h6' => true,
		'head' => true,
		'header' => true,
		'hgroup' => true,
		'hr' => false,
		'html' => false,
		'i' => false,
		'iframe' => false,
		'img' => false,
		'input' => false,
		'ins' => false,
		'kbd' => false,
		'label' => false,
		'legend' => true,
		'li' => true,
		'listing' => true,
		'link' => false,
		'main' => true,
		'map' => false,
		'mark' => false,
		'marquee' => false,
		'math' => false,
		'menu' => true,
		'meta' => false,
		'meter' => false,
		'nav' => true,
		'noembed' => false,
		'noframes' => false,
		'noscript' => false,
		'object' => false,
		'ol' => true,
		'optgroup' => true,
		'option' => true,
		'output' => false,
		'p' => true,
		'param' => false,
		'picture' => false,
		'portal' => false,
		'pre' => true,
		'progress' => false,
		'q' => false,
		'rb' => false,
		'rp' => false,
		'rt' => false,
		'rtc' => false,
		'ruby' => false,
		's' => false,
		'samp' => false,
		'script' => false,
		'search' => true,
		'section' => true,
		'select' => true,
		'slot' => false,
		'small' => true,
		'source' => false,
		'span' => false,
		'strike' => false,
		'strong' => false,
		'style' => false,
		'sub' => false,
		'summary' => true,
		'sup' => false,
		'svg' => false,
		'table' => true,
		'tbody' => true,
		'td' => true,
		'template' => false,
		'textarea' => false,
		'tfoot' => true,
		'th' => true,
		'thead' => true,
		'time' => false,
		'title' => false,
		'tr' => true,
		'track' => false,
		'tt' => false,
		'u' => false,
		'ul' => true,
		'var' => false,
		'video' => false,
		'wbr' => false,
		'xmp' => true,
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Label for the original string, if applicable.
	 *
	 * For file diffs, this is typically the file path.
	 */
	public string $label1 = 'original';

	/**
	 * @var string
	 *
	 * Timestamp for the original string, if applicable.
	 *
	 * For file diffs, this is typically the file's modification time.
	 */
	public string $time1 = '';

	/**
	 * @var string
	 *
	 * Label for the modified string, if applicable.
	 *
	 * For file diffs, this is typically the file path.
	 */
	public string $label2 = 'modified';

	/**
	 * @var string
	 *
	 * Timestamp for the modified string, if applicable.
	 *
	 * For file diffs, this is typically the file's modification time.
	 */
	public string $time2 = '';

	/**
	 * @var array
	 *
	 * The differences between $str1 and $str2.
	 */
	public array $changes = [];

	/**
	 * @var int
	 *
	 * Will be set to 1 or 2 if either $str1 or $str2 is binary content.
	 *
	 * If set to 0, $str1 and $str2 are both text.
	 * If set to 1, $str1 or $str2 are identical binary content.
	 * if set to 2, either $str1 or $str2 is binary content, and they differ.
	 */
	public int $is_binary = 0;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * Used by $this->formatHtml() to activate special handling for markup.
	 */
	private bool $protect_markup = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * If $str1 and/or $str2 are not supplied, an instance with empty data will
	 * be created. This is useful when you plan to populate the diff data using
	 * $this->import().
	 *
	 * @param ?string $str1 The original string.
	 * @param ?string $str2 The modified string.
	 * @param ?string $time1 Timestamp to use for the original string.
	 * @param ?string $time2 Timestamp to use for the modified string.
	 * @param ?string $label1 Label to use for the original string.
	 * @param ?string $label2 Label to use for the modified string.
	 * @param int $context How many context lines to keep around the changes.
	 *    - Context lines allow patching even when line numbers don't match.
	 *    - If set to zero, no context lines will be added.
	 *    - If set to a negative number, no context lines will be added and
	 *      character-level comparisons will be performed within changed lines
	 *      in order to produce the smallest possible output.
	 *    - If set to PHP_INT_MIN, some further optimizations will be made to
	 *      serve the specific needs of EditDiff.
	 *    - A negative $context value might be forced to 0 if character-level
	 *      comparisons prove to be too expensive for the given strings.
	 *    - Default: 3.
	 */
	public function __construct(
		?string $str1 = null,
		?string $str2 = null,
		?string $time1 = null,
		?string $time2 = null,
		?string $label1 = null,
		?string $label2 = null,
		int $context = 3,
	) {
		$this->label1 = $label1 ?? $this->label1;
		$this->label2 = $label2 ?? $this->label2;

		$this->time1 = (string) $time1 !== '' && ($d = date_create((is_numeric($time1) ? '@' : '') . (string) $time1)) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s.u O') : (string) $time1;

		$this->time2 = (string) $time2 !== '' && ($d = date_create((is_numeric($time2) ? '@' : '') . (string) $time2)) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s.u O') : (string) $time2;

		if (isset($str1, $str2)) {
			// Compare the strings.
			$this->changes = $this->compareStrings($str1, $str2, $context);

			if ($this->is_binary) {
				return;
			}

			// Collapse arrays of words into simple strings.
			$this->changes = $this->wordsToStrings($this->changes);

			// Trim unchanged leading and trailing characters.
			if ($context < 0) {
				$this->changes = $this->trimUnchangedChars($this->changes);
			}

			// Delete any useless changesets.
			$this->changes = array_filter($this->changes, fn($c) => !empty($c['old']) || !empty($c['new']));

			// Drop the keys for the sake of more compact JSON output.
			$this->changes = array_values($this->changes);

			// Add context lines.
			$this->changes = $this->addContext($this->changes, $str1, $context);
		}
	}

	/**
	 * Imports the diff data from an array.
	 *
	 * @param array $data An array created by $this->export().
	 * @throws \ValueError if the passed array contains unexpected content.
	 * @return static Returns this object for the sake of method chaining.
	 */
	public function import(array $data): static
	{
		if (
			!is_array($data)
			|| count($data) !== 5
			|| (!is_string($data[0]) && !is_int($data[0]) && !is_float($data[0]))
			|| !is_string($data[1])
			|| (!is_string($data[2]) && !is_int($data[2]) && !is_float($data[2]))
			|| !is_string($data[3])
			|| !is_array($data[4])
		) {
			throw new \ValueError();
		}

		$this->time1 = (string) $data[0] !== '' && ($d = date_create((is_numeric($data[0]) ? '@' : '') . (string) $data[0])) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s.u O') : (string) $data[0];

		$this->label1 = $data[1];

		$this->time2 = (string) $data[2] !== '' && ($d = date_create((is_numeric($data[2]) ? '@' : '') . (string) $data[2])) !== false ? $d->setTimezone(timezone_open('UTC'))->format('Y-m-d H:i:s.u O') : (string) $data[2];

		$this->label2 = $data[3];

		foreach ($data[4] as $change) {
			if (
				!is_array($change)
				|| count($change) < 5
				|| !is_int($change[0])
				|| !is_int($change[1])
				|| !is_int($change[2])
				|| !is_string($change[3])
				|| !is_string($change[4])
				|| (isset($change[5]) && !is_string($change[5]))
				|| (isset($change[6]) && !is_string($change[6]))
			) {
				throw new \ValueError();
			}

			$this->changes[] = array_filter(
				array_combine(
					['l1', 'l2', 'offset', 'old', 'new', 'before', 'after'],
					array_slice(array_pad($change, 7, null), 0, 7),
				),
				fn($value) => isset($value),
			);
		}

		return $this;
	}

	/**
	 * Exports the diff data as an array.
	 *
	 * @return array An array representation of this diff's data.
	 */
	public function export(): array
	{
		$changes = $this->changes;

		foreach ($changes as &$change) {
			$change = array_values($change);
		}

		$ts1 = $this->time1 !== '' && ($d = date_create((is_numeric($this->time1) ? '@' : '') . $this->time1)) !== false ? str_replace('.000000', '', $d->format('U.u')) + 0 : $this->time1;

		$ts2 = $this->time2 !== '' && ($d = date_create((is_numeric($this->time2) ? '@' : '') . $this->time2)) !== false ? str_replace('.000000', '', $d->format('U.u')) + 0 : $this->time2;

		return [
			$ts1,
			$this->label1,
			$ts2,
			$this->label2,
			$changes,
		];
	}

	/**
	 * Given the original string, constructs the modified string.
	 *
	 * The $dynamic_context parameter can be used to increase the likelihood of
	 * success when lines in the immediate context of a change have been altered
	 * unexpectedly. When this option is enabled, the matching algorithm will
	 * initially try to match the change including all surrounding context, but
	 * if that fails then it will progressively give up one line of context at
	 * a time until it finds a match or runs out of context lines. Enabling this
	 * option is especially helpful when applying patches to files that may have
	 * been altered by third-party modifications.
	 *
	 * @param string $str1 The original string.
	 * @param bool $dynamic_context Whether to allow the matching algorithm to
	 *    dynamically adjust the number of context lines it considers when
	 *    attempting to find a match for each change. Default: false.
	 * @throws \ValueError if given a string it cannot work with.
	 * @return string The modified string.
	 */
	public function apply(string $str1, bool $dynamic_context = false): string
	{
		$changes = $this->changes;

		$lines = $this->splitLines($str1);

		if ($lines === false) {
			$lines = [];
		}

		// Find the correct place to apply the change.
		// This is not applicable to EditDiffs.
		if (is_string($this->changes[0]['old'])) {
			$broken_changes = [];
			$affected_line_numbers = [];

			foreach (array_reverse($changes, true) as $c => $change) {
				$change = $this->fixL1($change, $lines, $affected_line_numbers, $dynamic_context);

				if ($change === false) {
					$broken_changes[$c] = $changes[$c];
					continue;
				}

				$changes[$c] = $change;

				$affected_line_numbers = array_merge(
					$affected_line_numbers,
					range(
						$change['l1'] ?? 0,
						($change['l1'] ?? 0) + count($this->splitLines($change['old'], PREG_SPLIT_NO_EMPTY)),
					),
				);
			}

			if (!empty($broken_changes)) {
				ksort($broken_changes);

				// Include info about the changes that couldn't be applied
				// so that the caller knows exactly what went wrong.
				throw new \ValueError(json_encode($broken_changes));
			}
		}

		// Do the job.
		$str2 = '';

		foreach (array_reverse($changes) as $change) {
			$old_length = is_int($change['old']) ? $change['old'] : mb_strlen($change['old']);

			if (isset($lines[$change['l1']])) {
				$substring = implode('', array_splice($lines, $change['l1']));

				$str2 =
					mb_substr($substring, 0, $change['offset']) .
					$change['new'] .
					mb_substr($substring, $change['offset'] + $old_length) .
					$str2;
			} else {
				$str2 =
					mb_substr($str2, 0, $change['offset']) .
					$change['new'] .
					mb_substr($str2, $change['offset'] + $old_length);
			}
		}

		return implode('', $lines) . $str2;
	}

	/**
	 * Formats the diff using HTML <del> and <ins> elements.
	 *
	 * @param string $str1 The original string.
	 * @param bool $reverse If true, switch which values are shown as deleted
	 *    vs. inserted. Default: false.
	 * @param bool $parse Whether to parse BBCode and Markdown. Default: false.
	 * @param bool $htmlspecialchars Whether to escape HTML tags. Default: false.
	 * @throws \ValueError if given a string it cannot work with.
	 * @return string HTML markup showing the changes.
	 */
	public function formatHtml(string $str1, bool $reverse = false, bool $parse = false, bool $htmlspecialchars = false): string
	{
		if ($this->is_binary) {
			return 'Binary files ' . $this->label1 . ' and ' . $this->label2 . ($this->is_binary === 1 ? ' are identical' : ' differ') . "\n";
		}

		// Get $str2. (This is where a possible \ValueError might bubble up.)
		$str2 = $this->apply($str1);

		// Parse BBCode and Markdown before doing anything further.
		$str1 = $parse ? Parser::transform($str1) : $str1;
		$str2 = $parse ? Parser::transform($str2) : $str2;

		// Clean any whitespace or <br> elements in list and table structures.
		$str1 = $this->cleanListMarkup($str1, false);
		$str2 = $this->cleanListMarkup($str2, false);

		$str1 = $this->cleanTableMarkup($str1, false);
		$str2 = $this->cleanTableMarkup($str2, false);

		// How should we handle markup?
		if ($htmlspecialchars) {
			$str1 = htmlspecialchars($str1);
			$str2 = htmlspecialchars($str2);
		} else {
			$this->protect_markup = true;
		}

		// Compare the parsed strings using inline comparisons.
		$context = -1;
		$changes = $this->compareStrings($str1, $str2, $context);

		// Unless we were forced to use whole lines, format as a word-level diff.
		if ($context === -1) {
			$words = [];

			foreach ($this->splitLines($str1) as $line) {
				foreach ($this->splitWords($line) as $word) {
					$words[] = $word;
				}
			}

			$num_words = count($words);

			foreach (array_reverse($changes, true) as $word_offset => $change) {
				array_splice(
					$words,
					$word_offset,
					count($change['old']),
					$this->formatDelIns(
						$change[$reverse ? 'new' : 'old'],
						$change[$reverse ? 'old' : 'new'],
						$word_offset + count($change['old']) === $num_words ? ($change['offset'] === 0 ? 2 : 1) : 0,
					),
				);
			}

			$formatted = implode('', $words);
		} else {
			$lines = $this->splitLines($str1);
			$num_lines = count($lines);

			foreach (array_reverse($changes, true) as $word_offset => $change) {
				array_splice(
					$lines,
					$change['l1'],
					$change['l2'] - $change['l1'] + 1,
					$this->formatDelIns(
						$change[$reverse ? 'new' : 'old'],
						$change[$reverse ? 'old' : 'new'],
						$change['l2'] + 1 === $num_lines && implode('', $change['old']) === implode('', array_slice($lines, $change['l1'])) ? 2 : 0,
					),
				);
			}

			$formatted = implode('', $lines);
		}

		// Preserve angle brackets around legitimate HTML tags, and escape the rest.
		$formatted = preg_replace(
			'~<(/?' . Utils::buildRegex(array_keys(self::HTML_DIFF_PREFER_CONTENT)) . '\b)([^>]*)>~u',
			"\u{E080}" . '$1$2' . "\u{E081}",
			$formatted,
		);

		$formatted = htmlspecialchars($formatted, ENT_NOQUOTES, 'UTF-8', false);

		$formatted = strtr($formatted, [
			"\u{E080}" => '<',
			"\u{E081}" => '>',
		]);

		// Fix any markup changes.
		$formatted = $this->formatMarkup($formatted);

		// Stop protecting markup.
		$this->protect_markup = false;

		return $formatted;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Method to consistently split strings into lines.
	 *
	 * @param string $string The string.
	 * @param int $flags Bitmask of flags for preg_split().
	 * @return array|false The lines of the string, or false on failure.
	 */
	protected function splitLines(string $string, int $flags = 0): array|false
	{
		return @preg_split($this->protect_markup ? '/(?:<br\b[^>]*>\R?|\R)\K/u' : '/\R\K/u', $string, -1, $flags);
	}

	/**
	 * A wrapper for Utils::semanticSplit() with special handling for markup.
	 *
	 * @param string $string The string.
	 * @return array The words of the string.
	 */
	protected function splitWords(string $string): array
	{
		$words = Utils::semanticSplit($string);

		if (!$this->protect_markup) {
			return $words;
		}

		$bbc_tags = array_unique(array_map(fn($bbc) => $bbc['tag'], Parser::getBBCodes()));

		foreach ($words as $key => $word) {
			if (!isset($word)) {
				continue;
			}

			$next_key = $key + 1;

			if (
				$word === '<'
				&& isset($words[$next_key])
				&& (
					in_array($words[$next_key], array_keys(self::HTML_DIFF_PREFER_CONTENT))
					|| (
						$words[$next_key] === '/'
						&& in_array($words[$next_key + 1], array_keys(self::HTML_DIFF_PREFER_CONTENT))
					)
				)
				&& array_search('>', array_slice($words, $key)) !== false
			) {
				while (isset($words[$next_key]) && $words[$next_key] !== '>') {
					$words[$key] .= $words[$next_key];
					$words[$next_key] = null;
					$next_key++;
				}

				$words[$key] .= $words[$next_key] ?? '';
				$words[$next_key] = null;
			} elseif (
				$word === '['
				&& isset($words[$next_key])
				&& (
					in_array($words[$next_key], $bbc_tags)
					|| (
						$words[$next_key] === '/'
						&& in_array($words[$next_key + 1], $bbc_tags)
					)
				)
				&& array_search(']', array_slice($words, $key)) !== false
			) {
				while (isset($words[$next_key]) && $words[$next_key] !== ']') {
					$words[$key] .= $words[$next_key];
					$words[$next_key] = null;
					$next_key++;
				}

				$words[$key] .= $words[$next_key] ?? '';
				$words[$next_key] = null;
			}
		}

		return array_values(array_filter(
			$words,
			fn($word) => isset($word),
		));
	}

	/**
	 * Compares two strings and returns the differences between them.
	 *
	 * @param string $str1 The original string.
	 * @param string $str2 The modified string.
	 * @param int &$context How many context lines to keep around the changes.
	 * @return array The changes between the two strings.
	 */
	protected function compareStrings(string $str1, string $str2, int &$context): array
	{
		$changes = [];

		$lines1 = $this->splitLines($str1);
		$lines2 = $this->splitLines($str2);

		if ($lines1 === false || $lines2 === false) {
			$this->is_binary = $lines1 === $lines2 ? 1 : 2;

			return [];
		}

		// Figure out which lines match between the two strings, and which don't.
		$pairs = $this->correlate($lines1, $lines2, 0.5);

		// For convenience, also have a simple list without any unmatched lines.
		$matching = [];

		foreach ($pairs as $pair) {
			if ($pair[0] !== false && $pair[1] !== false) {
				$matching[$pair[0]] = $pair[1];
			}
		}

		// Avoid performace lags when the changed strings are really long.
		if ($context < 0) {
			$changed_lines_length = 0;

			foreach ($matching as $l1 => $l2) {
				if ($lines1[$l1] !== $lines2[$l2]) {
					$changed_lines_length += max(mb_strlen($lines1[$l1]), mb_strlen($lines2[$l2]));
				}
			}

			if ($changed_lines_length > self::INLINE_LIMIT) {
				$context = 0;
			}
		}

		// We split lines into words rather than characters at this stage
		// because doing so helps to identify more precise runs of changes,
		// which leads to more compact output by the time we are done.
		$word_offset = 0;
		$moved = [];
		$adjust = 0;

		$default_change = [
			'l1' => null,
			'l2' => null,
			'offset' => 0,
			'old' => [],
			'new' => [],
		];

		foreach ($pairs as $p => $pair) {
			if (!isset($change)) {
				$change = $changes[$word_offset] ?? $default_change;
			}

			// Inserted line.
			if ($pair[0] === false) {
				$change['new'] = array_merge($change['new'], $this->splitWords($lines2[$pair[1]]));
				$change['l2'] = $change['l2'] ?? $pair[1];
				$not_before = min($not_before ?? INF, $pair[1]);
				$adjust--;
				continue;
			}

			$change['offset'] = 0;
			$words1 = $this->splitWords($lines1[$pair[0]]);

			// Deleted line.
			if ($pair[1] === false) {
				$change['old'] = array_merge($change['old'], $words1);
				$change['l1'] = $change['l1'] ?? $pair[0];
				$adjust++;
				continue;
			}

			if (empty($change['old']) && empty($change['new'])) {
				$change['l1'] = $pair[0];
				$change['l2'] = $pair[1];
			} else {
				$change['l1'] = $change['l1'] ?? $pair[0];
				$change['l2'] = $change['l2'] ?? $pair[1];
			}

			// Has the line moved?
			switch ($pair[0] <=> ($pair[1] + $adjust)) {
				// Line moved up in $str2.
				case 1:
					$word_offset += count($words1);
					break;

				// Line moved down in $str2.
				case -1:
					$change['old'] = array_merge($change['old'], $words1);

					$changes[$word_offset] = $change;
					unset($change);

					$moved[] = $pair[0];
					$word_offset += count($words1);
					break;

				// Line did not move.
				case 0:
					// If we have changes from inserted or deleted lines, record them now.
					foreach ($moved as $key => $m) {
						if ($m >= ($pair[1] + $adjust)) {
							continue;
						}

						if ($matching[$m] < ($not_before ?? INF)) {
							$change['new'] = array_merge(
								$this->splitWords($lines2[$matching[$m]]),
								$change['new'],
							);
						} else {
							$change['new'] = array_merge(
								$change['new'],
								$this->splitWords($lines2[$matching[$m]]),
							);
						}

						unset($moved[$key]);
					}

					unset($not_before);

					if (!empty($change['old']) || !empty($change['new'])) {
						$changes[$word_offset] = $change;

						$word_offset += count($change['old']);

						$change = $changes[$word_offset] ?? $default_change;
						$change['l1'] = $pair[0];
						$change['l2'] = $pair[1];
					}

					// Has this line's content changed?
					if ($lines1[$pair[0]] !== $lines2[$pair[1]]) {
						// Do we want to track inline changes?
						if ($context < 0) {
							foreach ($this->compareInline(
								$word_offset,
								$lines1[$pair[0]],
								$lines2[$pair[1]],
								$change['l1'],
								$change['l2'],
								// The options for $merge_threshold are based on
								// the number of JSON syntax characters produced
								// per change by $this->export(). For EditDiff,
								// the JSON will be shorter overall if we merge
								// changes that are less than 20 bytes apart,
								// whereas 10 is the threshold for FullDiff.
								$context === PHP_INT_MIN ? 20 : 10,
							) as $w => $c) {
								$changes[$w] = $c;
							}
						} else {
							$change['old'] = $words1;
							$change['new'] = $this->splitWords($lines2[$pair[1]]);
							$changes[$word_offset] = $change;

							$word_offset += count($change['old']);

							$change = $changes[$word_offset] ?? $default_change;
							$change['l1'] = $pair[0];
							$change['l2'] = $pair[1];
						}
					} else {
						$word_offset += count($words1);
					}

					unset($change);
					break;
			}
		}

		// Record any lines appended to or removed from the end.
		if (!empty($change['old']) || !empty($change['new'])) {
			if (!isset($changes[$word_offset])) {
				$change['l1'] = $change['l1'] ?? array_key_last($lines1) + 1;
				$change['l2'] = $change['l2'] ?? array_key_last($lines2) + 1;
				$changes[$word_offset] = $change;
			} else {
				$changes[$word_offset]['old'] = $change['old'];
				$changes[$word_offset]['new'] = $change['new'];
			}
		}

		$changes = $this->consolidate($changes);

		return $changes;
	}

	/**
	 * Helper for $this->compareStrings() that compares two lines and returns
	 * the differences between them.
	 *
	 * @param int &$word_offset The number of words before the start of $line1.
	 * @param string $line1 A line from the original string.
	 * @param string $line2 A line from the modified string.
	 * @param int $l1 The line number of $line1.
	 * @param int $l2 The line number of $line2.
	 * @param int $merge_threshold Merge inline changes that are separated by
	 *    no more than this many bytes.
	 * @return array The changes between the two lines.
	 */
	protected function compareInline(
		int &$word_offset,
		string $line1,
		string $line2,
		int $l1,
		int $l2,
		int $merge_threshold,
	): array {
		$changes = [];

		if ($line1 === $line2) {
			$word_offset += count($this->splitWords($line1));

			return $changes;
		}

		$init_word_offset = $word_offset;

		$words1 = $this->splitWords($line1);
		$words2 = $this->splitWords($line2);

		// Figure out which words match between the two strings, and which don't.
		$pairs = $this->correlate($words1, $words2, 0.0);

		// For convenience, also have a simple list without any unmatched words.
		$matching = [];

		foreach ($pairs as $pair) {
			if ($pair[0] !== false && $pair[1] !== false) {
				$matching[$pair[0]] = $pair[1];
			}
		}

		// The logic for comparing words is similar to the logic for comparing
		// lines, but not quite the same. :/
		$char_offset = 0;
		$moved = [];
		$adjust = 0;

		$default_change = [
			'l1' => $l1,
			'l2' => $l2,
			'offset' => 0,
			'old' => [],
			'new' => [],
		];

		foreach ($pairs as $p => $pair) {
			if (!isset($change)) {
				$change = $changes[$word_offset] ?? $default_change;
			}

			// Inserted word.
			if ($pair[0] === false) {
				$change['new'][] = $words2[$pair[1]];
				$not_before = min($not_before ?? INF, $pair[1]);
				$adjust--;
				continue;
			}

			// Deleted word.
			if ($pair[1] === false) {
				$change['old'][] = $words1[$pair[0]];
				$adjust++;
				continue;
			}

			// Has the word moved?
			switch ($pair[0] <=> ($pair[1] + $adjust)) {
				// Word moved earlier in $str2.
				case 1:
					$word_offset++;
					break;

				// Word moved later in $str2.
				case -1:
					$change['old'][] = $words1[$pair[0]];

					$change['offset'] = $char_offset;
					$changes[$word_offset] = $change;
					$char_offset += mb_strlen(implode('', $change['old']));
					$word_offset += count($change['old']);

					unset($change);

					$moved[] = $pair[0];
					break;

				// Word did not move.
				case 0:
					// If we have changes from inserted or deleted words, record them now.
					foreach ($moved as $key => $m) {
						if ($m >= ($pair[1] + $adjust)) {
							continue;
						}

						if ($matching[$m] < ($not_before ?? INF)) {
							$change['new'] = array_merge(
								[$words2[$matching[$m]]],
								$change['new'],
							);
						} else {
							$change['new'] = array_merge(
								$change['new'],
								[$words2[$matching[$m]]],
							);
						}

						unset($moved[$key]);
					}

					unset($not_before);

					if (!empty($change['old']) || !empty($change['new'])) {
						$change['offset'] = $char_offset;
						$changes[$word_offset] = $change;
						$char_offset += mb_strlen(implode('', $change['old']));
						$word_offset += count($change['old']);

						$change = $changes[$word_offset] ?? $default_change;
					}

					// Has this word itself changed?
					if ($words1[$pair[0]] !== $words2[$pair[1]]) {
						$change['old'][] = $words1[$pair[0]];
						$change['new'][] = $words2[$pair[1]];

						$change['offset'] = $char_offset;
						$changes[$word_offset] = $change;
						$char_offset += mb_strlen(implode('', $change['old']));
						$word_offset += count($change['old']);
					} else {
						$char_offset += mb_strlen($words1[$pair[0]]);
						$word_offset++;
					}

					unset($change);
					break;
			}
		}

		// Record any words appended to or removed from the end.
		if (!empty($change['old']) || !empty($change['new'])) {
			$change['offset'] = $char_offset;
			$changes[$word_offset] = $change;
			unset($change);
		}

		// Merge changes that are separated by only a few characters.
		// This makes output simpler and more compact.
		$word_offsets = array_map('intval', array_keys($changes));

		foreach ($changes as $c => $change) {
			$this_end = $c + count($change['old']);
			$next_start = next($word_offsets);

			if ($next_start <= $this_end) {
				continue;
			}

			$words_between = array_slice(
				$words1,
				$this_end - $init_word_offset,
				$next_start - $this_end,
			);

			if (strlen(implode('', $words_between)) <= $merge_threshold) {
				$changes[$c]['old'] = array_merge($changes[$c]['old'], $words_between);
				$changes[$c]['new'] = array_merge($changes[$c]['new'], $words_between);
			}
		}

		// Consolidate.
		$changes = $this->consolidate($changes);

		// Trim any unchanged words in the changesets.
		$changes = $this->trimUnchangedWords($changes);

		// Delete any useless changesets.
		$changes = array_filter($changes, fn($c) => !empty($c['old']) || !empty($c['new']));

		// Regardless of what is or isn't included in $changes, we need to
		// increase $word_offset by the total number of words in $words1.
		$word_offset = $init_word_offset + count($words1);

		return $changes;
	}

	/**
	 * Figures out which substrings from $str1 and $str2 match with each other.
	 *
	 * @param array $substrings1 The original string, split into substrings.
	 * @param array $substrings2 The modified string, split into substrings.
	 * @param float $min_similarity Percentage indicating how similar two
	 *    substrings must be in order to be considered a match. This is used
	 *    primarily to prevent inline comparison from being performed on lines
	 *    that have little in common.
	 * @return array Array of substring pairs. Unmatched substrings will be
	 *    paired with a boolean false.
	 */
	protected function correlate(
		array $substrings1,
		array $substrings2,
		float $min_similarity = 0.0,
	): array {
		$possible_matches = [];
		$matching = [];
		$unmatched = $substrings2;

		// Find the unchanged substrings.
		$possible_matches = $this->correlateUnchanged(
			$possible_matches,
			$matching, // Passed by reference
			$unmatched, // Passed by reference
			$substrings1,
			$substrings2,
		);

		$this->checkPossibleMatches(
			$possible_matches,
			$matching, // Passed by reference
			$unmatched, // Passed by reference
		);

		// Find the similar substrings.
		$possible_matches = $this->correlateSimilar(
			$possible_matches,
			$matching,
			$unmatched,
			$substrings1,
			$substrings2,
			$min_similarity,
		);

		$this->checkPossibleMatches(
			$possible_matches,
			$matching, // Passed by reference
			$unmatched, // Passed by reference
		);

		// Compile into a unified list.
		$pairs = [];

		for ($i = 0; $i < count($substrings1); $i++) {
			$pairs[] = [$i, $matching[$i] ?? false];
		}

		if (!empty($unmatched)) {
			$done = [];

			foreach ($unmatched as $u => $string) {
				$temp = [];

				foreach ($pairs as $pair) {
					if (!in_array($u, $done) && $pair[1] > $u) {
						$temp[] = [false, $u];
						$done[] = $u;
					}

					$temp[] = $pair;
				}

				if (!in_array($u, $done)) {
					$temp[] = [false, $u];
					$done[] = $u;
				}

				$pairs = $temp;
			}
		}

		return $pairs;
	}

	/**
	 * Helper for $this->correlate() that finds unchanged substrings.
	 *
	 * @param array $possible_matches Full list of possible matches.
	 * @param array &$matching Pairs of substring numbers that do indeed match.
	 * @param array &$unmatched Lines from $str2 that have not been matched.
	 * @param array $substrings1 The original string, split into substrings.
	 * @param array $substrings2 The modified string, split into substrings.
	 * @return array Updated version of $possible_matches.
	 */
	protected function correlateUnchanged(
		array $possible_matches,
		array &$matching,
		array &$unmatched,
		array $substrings1,
		array $substrings2,
	): array {
		// Params required for $this->trimEnds() and $this->bifurcate().
		$start1 = array_key_first($substrings1);
		$start2 = array_key_first($substrings2);
		$end1 = array_key_last($substrings1) + 1;
		$end2 = array_key_last($substrings2) + 1;

		// Find unchanged substrings at the start and end.
		list(, $start1, $start2, $end1, $end2) = $this->trimEnds(
			0,
			$start1,
			$start2,
			$end1,
			$end2,
			$substrings1,
			$substrings2,
		);

		for ($i = array_key_first($substrings1); $i < $start1; $i++) {
			if (isset($possible_matches[$i])) {
				continue;
			}

			$possible_matches[$i] = [$i];
		}

		for ($i = array_key_last($substrings1); $i >= $end1; $i--) {
			if (isset($possible_matches[$i])) {
				continue;
			}

			$possible_matches[$i] = [$i + ($end2 - $end1)];
		}

		// Find unchanged substrings in the middle.
		if (
			($bifurcated = $this->bifurcate(
				$start1,
				$start2,
				$end1,
				$end2,
				$substrings1,
				$substrings2,
			)) !== []
		) {
			$middle = array_combine(
				array_keys($bifurcated['middle1']),
				array_keys($bifurcated['middle2']),
			);

			$middle_str = implode('', $bifurcated['middle1']);

			// If the combined set of middle substrings is unique, then we can
			// add them to $possible_matches and $matching now, which saves us
			// time and effort later.
			if ($middle_str !== '' && substr_count(implode('', $substrings2), $middle_str) === 1) {
				foreach ($middle as $i => $u) {
					if (isset($possible_matches[$i])) {
						continue;
					}

					$possible_matches[$i] = [$u];
					$matching[$i] = $u;
					unset($unmatched[$u]);
				}

				// Recurse to try to find more matches in the prefix substrings.
				if (!empty($bifurcated['prefix1']) && !empty($bifurcated['prefix2'])) {
					foreach (
						$this->correlateUnchanged(
							$possible_matches,
							$matching,
							$unmatched,
							$bifurcated['prefix1'],
							$bifurcated['prefix2'],
						) as $i => $matches
					) {
						$possible_matches[$i] = $matches;
					}
				}

				// Recurse to try to find more matches in the suffix substrings.
				if (!empty($bifurcated['suffix1']) && !empty($bifurcated['suffix2'])) {
					foreach (
						$this->correlateUnchanged(
							$possible_matches,
							$matching,
							$unmatched,
							$bifurcated['suffix1'],
							$bifurcated['suffix2'],
						) as $i => $matches
					) {
						$possible_matches[$i] = $matches;
					}
				}
			}
		}

		// Find any other unchanged substrings.
		foreach ($substrings1 as $i => $substring) {
			if (!isset($possible_matches[$i])) {
				$possible_matches[$i] = array_keys($substrings2, $substring);
			}
		}

		ksort($possible_matches);

		return $possible_matches;
	}

	/**
	 * Helper for $this->correlate() that finds similar substrings.
	 *
	 * @param array $possible_matches Full list of possible matches.
	 * @param array $matching Pairs of substring numbers that do indeed match.
	 * @param array $unmatched Lines from $str2 that have not been matched.
	 * @param array $substrings1 The original string, split into substrings.
	 * @param array $substrings2 The modified string, split into substrings.
	 * @param float $min_similarity Percentage indicating how similar two
	 *    substrings must be in order to be considered a match. This is used
	 *    primarily to prevent inline comparison from being performed on lines
	 *    that have little in common.
	 * @return array Updated version of $possible_matches.
	 */
	protected function correlateSimilar(
		array $possible_matches,
		array $matching,
		array $unmatched,
		array $substrings1,
		array $substrings2,
		float $min_similarity,
	): array {
		$similar = [];
		$prev_i = $next_i = -1;

		// Find the similar substrings.
		foreach ($possible_matches as $i => $matches) {
			// The previous and next definite matches set the bounds for the
			// range of lines that we should bother checking for similarity.
			if (isset($matching[$i])) {
				$prev_i = $i;
				continue;
			}

			for ($next_i = $i + 1; $next_i <= count($possible_matches); $next_i++) {
				if (isset($matching[$next_i])) {
					break;
				}
			}

			foreach ($unmatched as $u => $substring) {
				// Skip possible pairs that are out of bounds.
				if ($u <= ($matching[$prev_i] ?? PHP_INT_MIN)) {
					continue;
				}

				if ($u >= ($matching[$next_i] ?? PHP_INT_MAX)) {
					break;
				}

				// Skip possible pairs that we know will not be chosen.
				foreach ($similar as $lcs => $pairs) {
					if (
						(in_array($u, $pairs) && $lcs > mb_strlen($substrings1[$i]))
						|| (isset($pairs[$i]) && $lcs > mb_strlen($substring))
					) {
						continue 2;
					}
				}

				// Find the length of the longest common subsequence of the two strings.
				$lcs = $this->lcsLength($substrings1[$i], $substring);

				if ($lcs / max(mb_strlen($substrings1[$i]), mb_strlen($substring)) < $min_similarity) {
					continue;
				}

				$similar[$lcs][$i][] = $u;

				ksort($similar[$lcs]);

				uksort(
					$similar[$lcs][$i],
					function ($a, $b) use ($i, $substrings1, $substrings2) {
						$abs_len_diff_a = abs(mb_strlen($substrings1[$i]) - mb_strlen($substrings2[$a]));
						$abs_len_diff_b = abs(mb_strlen($substrings1[$i]) - mb_strlen($substrings2[$b]));

						if ($abs_len_diff_a === $abs_len_diff_b) {
							return abs($i - $a) <=> abs($i - $b);
						}

						return $abs_len_diff_a <=> $abs_len_diff_b;
					},
				);
			}
		}

		krsort($similar);

		$done = $matching;

		do {
			$test = $done;

			foreach ($similar as $lcs => $pairs) {
				foreach ($pairs as $i => $matches) {
					foreach ($matches as $u) {
						if (in_array($u, $done)) {
							continue;
						}

						if (
							empty($done)
							|| in_array($u - 1, $possible_matches[$i - 1] ?? [])
							|| in_array($u + 1, $possible_matches[$i + 1] ?? [])
						) {
							$done[$i] = $u;
							$possible_matches[$i][] = $u;
							sort($possible_matches[$i]);
							$possible_matches[$i] = array_unique($possible_matches[$i]);

							unset($similar[$lcs][$i]);

							if (empty($similar[$lcs])) {
								unset($similar[$lcs]);
							}
						}
					}
				}
			}

			ksort($possible_matches);
		} while ($test !== $done);

		return $possible_matches;
	}

	/**
	 * Helper for $this->correlate() that checks possibly matching substrings
	 * to see whether they really do match, and juggles data around accordingly.
	 *
	 * @param array $possible_matches Sets of possibly matching substring numbers.
	 * @param array &$matching Pairs of substring numbers that do indeed match.
	 * @param array &$unmatched Unmatched substrings from $str2.
	 */
	protected function checkPossibleMatches(
		array $possible_matches,
		array &$matching,
		array &$unmatched,
	): void {
		$num_possible_matches = count($possible_matches);

		// We look for runs of possible matches, so that we can choose the runs
		// that will produce the fewest changes.
		$runs = [];

		$done = [];

		foreach ($possible_matches as $i => $matches) {
			$num_matches = count($matches);

			// Skip if we already have a match or if there are no possible matches for this substring.
			if ($num_matches === 0 || isset($matching[$i]) || in_array($i, $done)) {
				continue;
			}

			$is_unique_match = $num_matches === 1 && array_keys($possible_matches, $matches) === [$i];

			foreach ($matches as $u) {
				// How many preceding contiguous matches are there?
				$prev_u = $u;
				$prev_i = $i;

				while (
					in_array($prev_u - 1, $possible_matches[$prev_i - 1] ?? [])
					&& !isset($matching[$prev_i - 1])
					&& $prev_i > 0
				) {
					--$prev_u;
					--$prev_i;

					// When we find a continuous run between two unique matches,
					// that means there is only one possible path between them.
					// Therefore, we can discard any other possible matches for
					// the intervening lines and collapse them down to the one
					// correct match for each.
					if (
						$is_unique_match
						&& count($possible_matches[$prev_i]) === 1
						&& array_keys($possible_matches, $possible_matches[$prev_i]) === [$prev_i]
					) {
						$temp_u = $prev_u + 1;

						for ($temp_i = $prev_i + 1; $temp_i < $i; $temp_i++) {
							$possible_matches[$temp_i] = [$temp_u++];
							$done[$temp_i] = $temp_i;
						}
					}
				}

				// How many following contiguous matches are there?
				$next_u = $u;
				$next_i = $i;

				while (
					in_array($next_u + 1, $possible_matches[$next_i + 1] ?? [])
					&& !isset($matching[$next_i + 1])
					&& $next_i < $num_possible_matches
				) {
					++$next_u;
					++$next_i;

					if (
						$is_unique_match
						&& count($possible_matches[$next_i]) === 1
						&& array_keys($possible_matches, $possible_matches[$next_i]) === [$next_i]
					) {
						$temp_u = $next_u - 1;

						for ($temp_i = $next_i - 1; $temp_i > $i; $temp_i--) {
							$possible_matches[$temp_i] = [$temp_u--];
							$done[$temp_i] = $temp_i;
						}
					}
				}

				$length = $next_i - $prev_i + 1;

				$range = [
					$prev_i => $prev_u,
					$next_i => $next_u,
				];

				if (!in_array($range, $runs[$length] ?? [])) {
					$runs[$length][] = $range;
				}
			}
		}

		// Sort the runs so that the most expansive ones will be tried first.
		krsort($runs);

		foreach ($runs as $length => $ranges) {
			usort(
				$runs[$length],
				function ($a, $b) {
					if (array_key_first($a) === array_key_first($b)) {
						return abs(array_key_first($a) - reset($a)) <=> abs(array_key_first($b) - reset($b));
					}

					return array_key_first($a) <=> array_key_first($b);
				},
			);
		}

		// Now walk through the runs to find all the unambiguous matches we can.
		foreach ($runs as $length => $ranges) {
			foreach ($ranges as $range) {
				// The $str1 line numbers for this range.
				$range1 = range(array_key_first($range), array_key_last($range));
				// The $str2 line numbers for this range.
				$range2 = range(reset($range), end($range));

				// If this range partially overlaps with already matched lines,
				// try reducing the range to only the non-overlapping part.
				while (
					!empty($range2)
					&& ($already_matched = array_intersect($matching, $range2)) !== []
					&& (
						in_array(end($range2), $already_matched)
						|| in_array(reset($range2), $already_matched)
					)
				) {
					if (in_array(end($range2), $already_matched)) {
						array_pop($range1);
						array_pop($range2);
					}

					if (in_array(reset($range2), $already_matched)) {
						array_shift($range1);
						array_shift($range2);
					}
				}

				// If this range doesn't overlap with already matched lines,
				// then the lines in the range are matches.
				if (
					!empty($range1)
					&& array_intersect(array_keys($matching), $range1) === []
					&& array_intersect($matching, $range2) === []
				) {
					foreach ($range1 as $key => $i) {
						if (
							empty($matching)
							|| $this->checkMatchOrder($possible_matches, $matching, $i, $range2[$key])
						) {
							$matching[$i] = $range2[$key];
						}
					}
				}
			}
		}

		// Tidy up.
		ksort($matching);

		foreach ($matching as $i => $u) {
			unset($unmatched[$u]);
		}
	}

	/**
	 * Helper for $this->checkPossibleMatches() that checks whether a potential
	 * match can be inserted in the correct overall order within $matching.
	 *
	 * This is necessary because if the line happens to match something out of
	 * order, we should treat that as a changed line, which means not treating
	 * it as a valid match during $this->checkPossibleMatches().
	 *
	 * @param array $possible_matches Sets of possibly matching substring nums.
	 * @param array $matching Pairs of substring numbers that do indeed match.
	 * @param int $i The current key in $possible matches.
	 * @param int $match A value from $possible matches[$i].
	 * @return bool Whether this combination of $i and $match can be inserted in the
	 *    correct order for the overall $matching list.
	 */
	protected function checkMatchOrder(
		array $possible_matches,
		array $matching,
		int $i,
		int $match,
	): bool {
		$max = count($possible_matches);

		if ($max === 1) {
			return true;
		}

		// First check $matching, since it is the most definite.
		$prev_i = $i - 1;
		$next_i = $i + 1;

		while ($prev_i > -1 && !isset($matching[$prev_i])) {
			$prev_i--;
		}

		while ($next_i < $max && !isset($matching[$next_i])) {
			$next_i++;
		}

		if (isset($matching[$prev_i]) || isset($matching[$next_i])) {
			return (
				($matching[$prev_i] ?? PHP_INT_MIN) < $match
				&& ($matching[$next_i] ?? PHP_INT_MAX) > $match
			);
		}

		// Next try to figure out the correct order from $possible_matches.
		// This only works if $possible_matches[$i] contains a unique match.
		if ($possible_matches[$i] !== [$match]) {
			return false;
		}

		foreach ($possible_matches as $key => $value) {
			if ($key !== $i && in_array($match, $value)) {
				return false;
			}
		}

		// This is a unique match, so see if it is the right order.
		$prev_i = $i - 1;
		$next_i = $i + 1;

		while ($prev_i > -1 && empty($possible_matches[$prev_i])) {
			$prev_i--;
		}

		while ($next_i < $max && empty($possible_matches[$next_i])) {
			$next_i++;
		}

		return (
			array_filter($possible_matches[$prev_i] ?? [], fn($arg) => $arg < $match) !== []
			&& array_filter($possible_matches[$next_i] ?? [], fn($arg) => $arg > $match) !== []
		);
	}

	/**
	 * Returns the length of the longest common subsequence of two strings.
	 *
	 * @link http://en.wikipedia.org/wiki/Longest_common_subsequence_problem
	 *
	 * @param string $str1 The first string.
	 * @param string $str2 The second string.
	 * @return int Length of the longest common subsequence.
	 */
	protected function lcsLength(string $str1, string $str2): int
	{
		if ($str1 === $str2) {
			return mb_strlen($str1);
		}

		$chars1 = mb_str_split($str1);
		$chars2 = mb_str_split($str2);

		$end1 = count($chars1);
		$end2 = count($chars2);

		$start1 = 0;
		$start2 = 0;

		$lcs = 0;

		// Optimize by removing identical substrings from the beginning and end.
		list($lcs, $start1, $start2, $end1, $end2) = $this->trimEnds($lcs, $start1, $start2, $end1, $end2, $chars1, $chars2);

		// Optimize more by removing identical substrings from the middle and
		// then getting the LCS of the prefix and suffix substrings.
		if (
			($bifurcated = $this->bifurcate(
				$start1,
				$start2,
				$end1,
				$end2,
				$chars1,
				$chars2,
			)) !== []
		) {
			$lcs += $bifurcated['middle_len'];

			$lcs += $this->lcsLength(
				implode('', $bifurcated['prefix1']),
				implode('', $bifurcated['prefix2']),
			);

			$lcs += $this->lcsLength(
				implode('', $bifurcated['suffix1']),
				implode('', $bifurcated['suffix2']),
			);

			return $lcs;
		}

		// Once we reach the point where bifurcating doesn't help any further,
		// perform the main LCS algorithm.
		$matrix = [
			$start1 => array_fill(0, $end2 + 1, 0),
		];

		for ($c1 = $start1; $c1 < $end1; $c1++) {
			// This algorithm is the part where time usage grows exponentially
			// with the length of the strings being compared.
			if (ceil(microtime(true) - TIME_START) % 30 >= 25) {
				Sapi::setTimeLimit();
			}

			$row = [$start1 => 0];

			for ($c2 = $start1; $c2 < $end2; $c2++) {
				if ($chars1[$c1] === $chars2[$c2]) {
					$row[$c2 + 1] = $matrix[$c1][$c2] + 1;
					continue;
				}

				$row[$c2 + 1] = max($matrix[$c1][$c2 + 1], $row[$c2]);
			}

			$matrix[$c1 + 1] = $row;

			// Save memory.
			unset($matrix[$c1 - 1]);
		}

		$lcs += $matrix[$end1][$end2];

		return $lcs;
	}

	/**
	 * Helper for $this->lcsLength() that trims unchanged leading and trailing
	 * substrings.
	 *
	 * @param int $lcs Total length of the LCS.
	 * @param int $start1 Index of the first relevant substring in $str1.
	 * @param int $start2 Index of the first relevant substring in $str2.
	 * @param int $end1 Index of the last relevant substring in $str1.
	 * @param int $end2 Index of the last relevant substring in $str2.
	 * @param array $strings1 Substrings (e.g. characters) from $str1.
	 * @param array $strings2 Substrings (e.g. characters) from $str2.
	 * @return array Updated values for $lcs, $start1, $start2, $end1, $end2.
	 */
	protected function trimEnds(
		int $lcs,
		int $start1,
		int $start2,
		int $end1,
		int $end2,
		array $strings1,
		array $strings2,
	): array {
		while (
			$start1 < min($end1, $end2)
			&& $start2 < min($end1, $end2)
			&& isset($strings1[$start1], $strings2[$start2])
			&& $strings1[$start1] === $strings2[$start2]
		) {
			$start1++;
			$start2++;
			$lcs++;
		}

		$end1--;
		$end2--;

		while (
			$start1 < min($end1, $end2)
			&& $start2 < min($end1, $end2)
			&& isset($strings1[$end1], $strings2[$end2])
			&& $strings1[$end1] === $strings2[$end2]
		) {
			$end1--;
			$end2--;
			$lcs++;
		}

		$end1++;
		$end2++;

		return [$lcs, $start1, $start2, $end1, $end2];
	}

	/**
	 * Helper for $this->lcsLength() that removes unchanged substrings from the
	 * middle and updates $lcs accordingly.
	 *
	 * This divide-and-conquer approach handles runs of identical substrings
	 * much faster than the main LCS algorithm and ensures that the main
	 * algorithm only operates on the data that it absolutely must.
	 *
	 * @param int $start1 Index of the first relevant substring in $str1.
	 * @param int $start2 Index of the first relevant substring in $str2.
	 * @param int $end1 Index of the last relevant substring in $str1.
	 * @param int $end2 Index of the last relevant substring in $str2.
	 * @param array $strings1 Substrings (e.g. characters) from $str1.
	 * @param array $strings2 Substrings (e.g. characters) from $str2.
	 * @return array Array with prefix1, prefix2, middle1, middle2, suffix1,
	 *    suffix2, and middle_len elements
	 */
	protected function bifurcate(
		int $start1,
		int $start2,
		int $end1,
		int $end2,
		array $strings1,
		array $strings2,
	): array {
		if ($start1 >= $end1 || $start2 >= $end2) {
			return [];
		}

		$substr2 = implode('', array_slice($strings2, $start2, $end2));

		if ($substr2 === '') {
			return [];
		}

		$middle_start1 = intval(floor(($end1 - $start1) / 2)) + $start1;
		$middle_end1 = $middle_start1;
		$middle_len = 1;
		$middle_str = $strings1[$middle_start1];

		while (true) {
			if (!empty($no_further_forward) && !empty($no_further_back)) {
				break;
			}

			$step_back = empty($no_further_back) && ($no_further_forward ?? $middle_len % 2 === 0);

			if ($step_back) {
				if (
					isset($strings1[$middle_start1 - 1])
					&& str_contains($substr2, $strings1[$middle_start1 - 1] . $middle_str)
				) {
					$middle_str = $strings1[--$middle_start1] . $middle_str;
					$middle_len++;
				} else {
					$no_further_back = true;
				}
			} else {
				if (
					isset($strings1[$middle_end1 + 1])
					&& str_contains($substr2, $middle_str . $strings1[$middle_end1 + 1])
				) {
					$middle_str .= $strings1[++$middle_end1];
					$middle_len++;
				} else {
					$no_further_forward = true;
				}
			}
		}

		if (!str_contains($substr2, $middle_str)) {
			return [];
		}

		$middle1 = array_slice($strings1, $middle_start1 - array_key_first($strings1), $middle_len, true);

		$middle_start2 = 0;
		$middle2 = array_slice($strings2, 0, $middle_len);

		while ($middle_str !== implode('', $middle2) && $middle_start2 + $middle_len <= $end2) {
			$middle2 = array_slice($strings2, ++$middle_start2, $middle_len, true);
		}

		$middle_start2 = array_key_first($middle2);

		if (count($middle2) < $middle_len) {
			return [];
		}

		$middle_end1 = $middle_start1 + $middle_len;
		$middle_end2 = $middle_start2 + $middle_len;

		$prefix1 = array_slice(
			$strings1,
			$start1 - array_key_first($strings1),
			$middle_start1 - $start1,
			true,
		);

		$prefix2 = array_slice(
			$strings2,
			$start2 - array_key_first($strings2),
			$middle_start2 - $start2,
			true,
		);

		$suffix1 = array_slice(
			$strings1,
			$middle_end1 - array_key_first($strings1),
			$end1 - $middle_end1,
			true,
		);

		$suffix2 = array_slice(
			$strings2,
			$middle_end2 - array_key_first($strings2),
			$end2 - $middle_end2,
			true,
		);

		if (empty($prefix1) || empty($prefix2) || empty($suffix1) || empty($suffix2)) {
			return [];
		}

		return compact('prefix1', 'prefix2', 'middle1', 'middle2', 'suffix1', 'suffix2', 'middle_len');
	}

	/**
	 * Consolidates contiguous changes.
	 *
	 * @param array $changes The changes to consolidate.
	 * @return array The consolidated changes.
	 */
	protected function consolidate(array $changes): array
	{
		$prev = null;

		foreach ($changes as $c => $change) {
			if (!isset($prev)) {
				$prev = $c;
				continue;
			}

			$prev_str = implode('', $changes[$prev]['old']);

			// Consolidate contiguous changes within the same line.
			if (
				$change['l1'] === $changes[$prev]['l1']
				&& $changes[$prev]['offset'] + mb_strlen($prev_str) === $change['offset']
				// Skip initial whitespace.
				&& !preg_match('/^\h+$/u', $prev_str)
			) {
				$changes[$prev]['old'] = array_merge($changes[$prev]['old'], $change['old']);
				$changes[$prev]['new'] = array_merge($changes[$prev]['new'], $change['new']);
				unset($changes[$c]);
			}
			// Consolidate contiguous whole-line changes.
			elseif (
				$change['offset'] === 0
				&& $changes[$prev]['offset'] === 0
				&& $c === $prev + count($changes[$prev]['old'])
			) {
				$changes[$prev]['old'] = array_merge($changes[$prev]['old'], $change['old']);
				$changes[$prev]['new'] = array_merge($changes[$prev]['new'], $change['new']);
				unset($changes[$c]);
			}
			// Not a contiguous change. Move on to the next one.
			else {
				$prev = $c;
			}
		}

		return $changes;
	}

	/**
	 * Collapses the 'old' and 'new' arrays of each change into strings.
	 *
	 * @param array $changes The changes to process.
	 * @return array The processed changes.
	 */
	protected function wordsToStrings(array $changes): array
	{
		foreach ($changes as $c => $change) {
			$changes[$c]['old'] = implode('', (array) $change['old']);
			$changes[$c]['new'] = implode('', (array) $change['new']);

			if (isset($changes[$c]['before'])) {
				$changes[$c]['before'] = implode('', (array) $change['before']);
			}

			if (isset($changes[$c]['after'])) {
				$changes[$c]['after'] = implode('', (array) $change['after']);
			}
		}

		return $changes;
	}

	/**
	 * Trims unchanged leading and trailing characters in changesets.
	 *
	 * @param array $changes The changes to process.
	 * @return array The processed changes.
	 */
	protected function trimUnchangedChars(array $changes): array
	{
		foreach ($changes as $c => $change) {
			$old = [];
			$new = [];

			// Split everything into characters except markup tags.
			// Bad things can happen if we mangle the markup.
			foreach (['old', 'new'] as $var) {
				$words = $this->splitWords(implode('', (array) $changes[$c][$var]));

				foreach ($words as $word) {
					if ($this->protect_markup && preg_match('/^(<\/?\w+[^>]*>|\[\/?\w+[^\]]*\])$/u', $word)) {
						${$var}[] = $word;
					} else {
						foreach (mb_str_split($word) as $char) {
							${$var}[] = $char;
						}
					}
				}
			}

			while (count($new) > 0 && count($old) > 0 && reset($new) === reset($old)) {
				$changes[$c]['offset'] += mb_strlen(reset($old));
				array_shift($old);
				array_shift($new);
			}

			while (count($new) > 0 && count($old) > 0 && end($new) === end($old)) {
				array_pop($old);
				array_pop($new);
			}

			$changes[$c]['old'] = implode('', $old);
			$changes[$c]['new'] = implode('', $new);
		}

		return $changes;
	}

	/**
	 * Trims unchanged leading and trailing words in changesets.
	 *
	 * @param array $changes The changes to process.
	 * @return array The processed changes.
	 */
	protected function trimUnchangedWords(array $changes): array
	{
		$new_changes = [];

		foreach ($changes as $c => $change) {
			$new_c = $c;

			while (
				!empty($change['old'])
				&& !empty($change['new'])
				&& reset($change['old']) === reset($change['new'])
			) {
				if ($change['l1'] === $change['l2']) {
					$change['offset'] += mb_strlen(reset($change['old']));
					$new_c++;
				}

				array_shift($change['old']);
				array_shift($change['new']);
			}

			while (
				!empty($change['old'])
				&& !empty($change['new'])
				&& end($change['old']) === end($change['new'])
			) {
				array_pop($change['old']);
				array_pop($change['new']);
			}

			$new_changes[$new_c] = $change;
		}

		return $new_changes;
	}

	/**
	 * Adds context lines to the diff.
	 *
	 * @param array $changes The changes to process.
	 * @param string $str1 The original string.
	 * @param int $context How many context lines to keep around the changes.
	 * @return array The processed changes.
	 */
	protected function addContext(array $changes, string $str1, int $context): array
	{
		if ($context < 1) {
			return $changes;
		}

		$lines1 = $this->splitLines($str1);

		foreach ($changes as $c => $change) {
			$changes[$c]['before'] = '';
			$changes[$c]['after'] = '';

			if (!isset($lines1[$changes[$c]['l1']])) {
				break;
			}

			// Initially assume we want $context number of preceding lines.
			$before_start = max(0, $changes[$c]['l1'] - $context);

			// But make sure that we don't get duplicate lines in this change's
			// 'before' element and the previous change's 'after' element.
			if (isset($changes[$c - 1])) {
				$prev_change_end = $changes[$c - 1]['l1'] + count($this->splitLines($changes[$c - 1]['old'])) - 1;
				$prev_after_end = $prev_change_end + count($changes[$c - 1]['after']);

				while ($before_start < $prev_after_end) {
					if ($before_start === $changes[$c]['l1']) {
						array_pop($changes[$c - 1]['after']);
						$prev_after_end--;
						continue;
					}

					if ($before_start < $prev_change_end) {
						$before_start++;
						continue;
					}

					if (($before_start - $prev_after_end) % 2 === 0) {
						array_pop($changes[$c - 1]['after']);
						$prev_after_end--;
					} else {
						$before_start++;
					}
				}
			}

			// Get the context lines to show before the changes.
			$changes[$c]['before'] = array_slice(
				$lines1,
				$before_start,
				$changes[$c]['l1'] - $before_start,
			);

			// Initially assume we want $context number of following lines.
			$after_start = $changes[$c]['l1'] + count($this->splitLines($changes[$c]['old'])) - 1;
			$after_length = $context;

			if (isset($lines1[$after_start + 1])) {
				// But reduce the number of following lines to grab if they would
				// overlap with following changes.
				while (
					isset($changes[$c + 1])
					&& $after_length > 0
					&& $after_start + $after_length > $changes[$c + 1]['l1']
				) {
					$after_length--;
				}

				// Get the context lines to show after the changes.
				$changes[$c]['after'] = array_slice(
					$lines1,
					$after_start,
					$after_length,
				);
			}
		}

		$changes = $this->wordsToStrings($changes);

		return $changes;
	}

	/**
	 * Helper for $this->apply() that uses heuristics to try to find the correct
	 * line number for a change.
	 *
	 * @param array $change The change that didn't match.
	 * @param array $lines Lines of the original string.
	 * @param array $disallowed Lines numbers that cannot be chosen.
	 * @param bool $dynamic_context Whether to allow the matching algorithm to
	 *    dynamically adjust the number of context lines it considers when
	 *    attempting to find a match for each change.
	 * @return array|false An altered version of $change, or false on error.
	 */
	protected function fixL1(array $change, array $lines, array $disallowed, bool $dynamic_context): array|false
	{
		// Number to add to $l1 to get the last line number in a block of affected text.
		$last_offset = count($this->splitLines($change['old'])) - 1;

		if ($change['old'] === '') {
			$possible_matches = array_keys($lines);
		} else {
			// Split old into lines.
			$old = $this->splitLines($change['old'], PREG_SPLIT_NO_EMPTY);

			// Find possible matches.
			$possible_matches = [];

			foreach ($old as $o => $old_line) {
				$possible_matches[$o] = array_keys($lines, $old_line);
			}

			// Filter out incomplete sets of matching lines.
			if (count($possible_matches) > 1) {
				foreach ($possible_matches as $o => $matches) {
					if (isset($possible_matches[$o - 1])) {
						$possible_matches[$o] = array_intersect(
							$possible_matches[$o],
							array_map(fn($l) => $l + 1, $possible_matches[$o - 1]),
						);
					}
				}

				$possible_matches = array_reverse($possible_matches, true);

				foreach ($possible_matches as $o => $matches) {
					if (isset($possible_matches[$o - 1])) {
						$possible_matches[$o - 1] = array_intersect(
							$possible_matches[$o - 1],
							array_map(fn($l) => $l - 1, $possible_matches[$o]),
						);
					}
				}

				$possible_matches = array_reverse($possible_matches, true);
			}

			// Beyond this point we only need the matches for the first line.
			$possible_matches = $possible_matches[0];
		}

		// Filter out any matches that would cause overlap with other changes.
		foreach ($possible_matches as $m => $l1) {
			if (array_intersect($disallowed, range($l1, $l1 + $last_offset)) !== []) {
				unset($possible_matches[$m]);
			}
		}

		// No matches found.
		if (count($possible_matches) === 0) {
			return false;
		}

		// Determine how many context lines to use.
		foreach ($this->changes as $c => $change) {
			// If any changes are inline, use no context lines.
			if ($change['offset'] !== 0) {
				$max_before_context = 0;
				$max_after_context = 0;
				break;
			}

			$max_before_context = max(
				$max_before_context ?? 0,
				count($this->splitLines($change['before'] ?? '', PREG_SPLIT_NO_EMPTY)),
			);

			$max_after_context = max(
				$max_after_context ?? 0,
				count($this->splitLines($change['after'] ?? '', PREG_SPLIT_NO_EMPTY)),
			);
		}

		$total_context = $max_total_context = $max_before_context + $max_after_context;

		// If possible, use the context lines to choose the best possible match.
		do {
			$before_context = $max_before_context;
			$after_context = $max_after_context - ($max_total_context - $total_context);
			$temp_possible_matches = $possible_matches;

			for ($i = 0; $i < $total_context; $i++) {
				if (isset($change['before'])) {
					$before = $this->splitLines($change['before'], PREG_SPLIT_NO_EMPTY);
					$before = array_slice($before, $before_context * -1, $before_context);

					foreach ($temp_possible_matches as $m => $l1) {
						foreach (array_reverse($before) as $b => $before_line) {
							if (($lines[$l1 - $b - 1] ?? null) !== $before_line) {
								unset($temp_possible_matches[$m]);
								continue 2;
							}
						}
					}
				}

				if (isset($change['after'])) {
					$after = $this->splitLines($change['after'], PREG_SPLIT_NO_EMPTY);
					$after = array_slice($after, 0, $after_context);

					foreach ($temp_possible_matches as $m => $l1) {
						$l_last = $l1 + $last_offset;

						foreach ($after as $a => $after_line) {
							if (($lines[$l_last + $a] ?? null) !== $after_line) {
								unset($temp_possible_matches[$m]);
								continue 2;
							}
						}
					}
				}

				switch (count($temp_possible_matches)) {
					case 1:
						// Found it!
						$possible_matches = $temp_possible_matches;
						break 2;

					default:
						if ($before_context === 0 && $after_context === 0) {
							$possible_matches = $temp_possible_matches;
							break 2;
						}
						break;
				}

				if ($before_context === 0 || $after_context === $max_after_context) {
					break;
				}

				$before_context--;
				$after_context++;
			}
		} while (
			// If dynamic context is enabled, retry until we find some matches
			// or we have reduced the number of context lines to zero.
			$dynamic_context
			&& count($temp_possible_matches) === 0
			&& --$total_context >= 0
		);

		switch (count($possible_matches)) {
			case 0:
				return false;

			case 1:
				$change['l1'] = reset($possible_matches);

				return $change;

			default:
				// If we still have multiple matches, prefer the one closest to the expected position.
				usort(
					$possible_matches,
					fn($a, $b) => abs($change['l1'] - $a) <=> abs($change['l1'] - $b),
				);

				$change['l1'] = reset($possible_matches);

				return $change;
		}
	}

	/**
	 * Helper for $this->formatHtml().
	 *
	 * @param array $del Deletions.
	 * @param array $ins Insertions.
	 * @param int $is_end Zero if this change does not include the end of the
	 *    string, 1 if it includes the end, or 2 if it includes the end AND it
	 *    includes only complete lines.
	 * @return string The formatted changes.
	 */
	protected function formatDelIns(array $del, array $ins, int $is_end): string
	{
		$formatted = '';

		$del = implode('', $del);
		$ins = implode('', $ins);

		// Reduce the amount of markup for changes that involve only whitespace.
		while (mb_substr($del, 0, 1) === mb_substr($ins, 0, 1) && preg_match('/^\h/u', $del)) {
			$formatted .= mb_substr($del, 0, 1);
			$del = mb_substr($del, 1);
			$ins = mb_substr($ins, 1);
		}

		// Wrap blank lines in a span so that CSS can target them.
		foreach (['del', 'ins'] as $var) {
			${$var} = preg_replace(
				[
					'/^(\R|<br\b[^>]*>\R?)|\R\K\R|<br\b[^>]*>\R?\K<br\b[^>]*>\R?/u',
					'/<span class="diff_blank_line">(\R|<br\b[^>]*>\R?)<\/span>\K(\R|<br\b[^>]*>\R?)/u',
				],
				'<span class="diff_blank_line">$0</span>',
				${$var},
			);
		}

		// Add the <del> element with the deleted content.
		if ($del !== '') {
			$formatted .= '<del class="diff">' . $del . '</del>';
		}

		// Special handling when the string does not end with a line break.
		if (
			!empty($is_end)
			&& $del !== ''
			&& $ins !== ''
			&& !preg_match('/(<br\b[^>]*>|\R)$/u', $del)
			&& (
				// 2 means $del contains only whole lines, which means that it
				// must contain the entire final line.
				$is_end === 2
				// Otherwise, check if there are any line breaks, since that
				// will tell us whether $del contains the entire final line.
				|| preg_match('/(<br\b[^>]*>|\R)/u', $del)
			)
			// But don't do this if the string ends in a list.
			&& !preg_match('/<\/(ol|ul|menu)>/u', $del)
		) {
			$formatted .= '<br>';
		}

		// Add the <ins> element with the inserted content.
		if ($ins !== '') {
			$formatted .= '<ins class="diff">' . $ins . '</ins>';
		}

		return $formatted;
	}

	/**
	 * Helper for $this->formatHtml() that fixes any mangled markup changes.
	 *
	 * @param string $formatted A formatted string
	 * @return string The formatted changes.
	 */
	protected function formatMarkup(string $formatted): string
	{
		$placeholders = [];

		$space_regex = '(?' . '>\s|' . Utils::ENT_NBSP . ')';
		$hspace_regex = '(?' . '>\h|' . Utils::ENT_NBSP . ')';

		// Fix line breaks.
		$formatted = preg_replace_callback_array(
			[
				'~' .
					'</?' .
					'(?' . '>' .
						'nav' .
						'|xmp' .
						'|ol' .
						'|ul' .
						'|a(?' . '>ddress|rticle|side)' .
						'|b(?' . '>lockquote|r)' .
						'|c(?' . '>aption|enter|ol(?' . '>group|))' .
						'|d(?' . '>etails|d|i(?' . '>alog|r|v)|l|t)' .
						'|f(?' . '>i(?' . '>eldset|g(?' . '>caption|ure))|o(?' . '>oter|rm))' .
						'|h(?' . '>eader|group|[1-6]|r)' .
						'|l(?' . '>egend|i(?' . '>sting|))' .
						'|m(?' . '>ain|enu)' .
						'|p(?' . '>re|)' .
						'|s(?' . '>ummary|e(?' . '>ction|arch))' .
						'|t(?' . '>able|body|foot|head|d|r)' .
					')' .
					'>' . $hspace_regex . '*' .
					'\K' .
					'\R+' .
				'~u' => function ($matches) {
					return '';
				},

				'/\R/u' => function ($matches) {
					return '<br>';
				},
			],
			$formatted,
		);

		// Lists + ins/del = complicated.
		$formatted = $this->cleanListMarkup($formatted);

		$formatted = preg_replace_callback_array(
			[
				// Changes to the list's opening tag must be shown as whole-list changes.
				'~' .
					'<del class="diff">' . $space_regex . '*' .
					'<(ol|ul|menu)\b([^>]*)>' .
					'(\X*?)' .
					'</del>' .
					'<ins class="diff">' . $space_regex . '*' .
					'<(ol|ul|menu)\b([^>]*)>' .
					'(\X*?)' .
					'</ins>' .
				'~u' => function ($matches) {
					return implode('', [
						'<!-- <' . $matches[1] . $matches[2] . '> -->',
						'<' . $matches[4] . $matches[5] . '>',
						$matches[3] === '' ? '' : '<del class="diff">' . $matches[3] . '</del>',
						$matches[6] === '' ? '' : '<ins class="diff">' . $matches[6] . '</ins>',
					]);
				},

				// Changes to the list's closing tag must be shown as whole-list changes.
				'~' .
					'<del class="diff">' .
					'((?:[^<]|<(?!/del\b))*)' .
					'</(ol|ul|menu)>' . $space_regex . '*' .
					'</del>' .
					'<ins class="diff">' .
					'((?:[^<]|<(?!/ins\b))*)' .
					'</(ol|ul|menu)>' . $space_regex . '*' .
					'</ins>' .
				'~u' => function ($matches) {
					return implode('', [
						$matches[1] === '' ? '' : '<del class="diff">' . $matches[1] . '</del>',
						$matches[3] === '' ? '' : '<ins class="diff">' . $matches[3] . '</ins>',
						'</' . $matches[4] . '>',
						'<!-- </' . $matches[2] . '> -->',
					]);
				},

				// Replaced one or more list items.
				// Demote the del/ins to wrap around the content.
				'~' .
					'<del class="diff">' . $space_regex . '*' .
					'<li\b([^>]*)>' .
					'(\X*?)' .
					'</li>' . $space_regex . '*' .
					'</del>' . $space_regex . '*' .
					'<ins class="diff">' . $space_regex . '*' .
					'<li\b([^>]*)>' .
					'(\X*?)' .
					'</li>' . $space_regex . '*' .
					'</ins>' .
				'~u' => function ($matches) {
					return implode('', [
						'<li' . $matches[3] . '>',
						$matches[2] === '' ? '' : '<del class="diff">' . $matches[2] . '</del>',
						$matches[4] === '' ? '' : '<ins class="diff">' . $matches[4] . '</ins>',
						'</li>',
					]);
				},

				// Inserted or removed one or more list items.
				// Demote the del/ins to wrap around the content.
				'~' .
					'<(del|ins) class="diff">' . $space_regex . '*' .
					'<li\b([^>]*)>' .
					'(\X*?)' .
					'</li>' . $space_regex . '*' .
					'</\1>' .
				'~u' => function ($matches) use ($space_regex) {
					return implode('', [
						'<li' . $matches[2] . '>',
						'<' . $matches[1] . ' class="diff">',
						preg_replace(
							'/<\/li>' . $space_regex . '*<li\b[^>]*>/u',
							'</' . $matches[1] . '>$0<' . $matches[1] . ' class="diff">',
							$matches[3],
						),
						'</' . $matches[1] . '>',
						'</li>',
					]);
				},

				// Inserted or removed the tags around one or more list items.
				// Demote the del/ins to wrap around the content.
				'~' .
					'<(del|ins) class="diff">' . $space_regex . '*' .
					'<li\b([^>]*)>' . $space_regex . '*' .
					'</\1>' .
					'(\X*?)' .
					'<\1 class="diff">' . $space_regex . '*' .
					'</li>' . $space_regex . '*' .
					'</\1>' .
				'~u' => function ($matches) use ($space_regex) {
					return implode('', [
						'<li' . $matches[2] . '>',
						'<' . $matches[1] . ' class="diff">',
						preg_replace(
							'/<\/li>' . $space_regex . '*<li\b[^>]*>/u',
							'</' . $matches[1] . '>$0<' . $matches[1] . ' class="diff">',
							$matches[3],
						),
						'</' . $matches[1] . '>',
						'</li>',
					]);
				},

				// Format whole-list changes.
				'~' .
					'<!-- <(ol|ul|menu)\b([^>]*)> -->' .
					'<(ol|ul|menu)\b([^>]*)>' .
					'(\X*?)' .
					'</\3>' .
					'(<!-- </\1> -->)?' .
				'~u' => function ($matches) {
					// Walk though the list, separating out the deletions and
					// insertions to create complete old and new versions of the
					// list, and then present those complete versions as the
					// deleted and inserted content.
					$del = '<' . $matches[1] . $matches[2] . '>';
					$ins = '<' . $matches[3] . $matches[4] . '>';

					foreach (preg_split('/<\/li>\K/u', $matches[5]) as $li) {
						preg_match('/<li\b([^>]*)>(\X*?)<\/li>/u', trim($li), $li_matches);

						if (!isset($li_matches[2])) {
							continue;
						}

						$content = preg_split('/(<(?:del|ins) class="diff">|<\/(?:del|ins)>)/u', $li_matches[2], -1, PREG_SPLIT_DELIM_CAPTURE);

						$del_content = [];
						$ins_content = [];

						for ($i = 0; $i < count($content); $i++) {
							if ($content[$i] === '') {
								continue;
							}

							switch ($i % 4) {
								case 0:
									$del_content[] = $content[$i];
									$ins_content[] = $content[$i];
									break;

								case 1:
									$in = str_starts_with($content[$i], '<del') ? 'del' : 'ins';
									break;

								case 2:
									if ($in === 'del') {
										$del_content[] = $content[$i];
									} else {
										$ins_content[] = $content[$i];
									}
									break;

								case 3:
									$in = '';
									break;
							}
						}

						$del_content = implode('', $del_content);
						$ins_content = implode('', $ins_content);

						if ($del_content !== '') {
							$del .= '<li' . $li_matches[1] . '>' . $del_content . '</li>';
						}

						if ($ins_content !== '') {
							$ins .= '<li' . $li_matches[1] . '>' . $ins_content . '</li>';
						}
					 }

					 $del .= '</' . $matches[1] . '>';
					 $ins .= '</' . $matches[3] . '>';

					 return '<del class="diff">' . $del . '</del><ins class="diff">' . $ins . '</ins>';
				},
			],
			$formatted,
		);

		// Tables + ins/del = also complicated.
		$formatted = $this->cleanTableMarkup($formatted);

		$formatted = preg_replace_callback_array(
			[
				// Changes to the table element itself.
				'~' .
					'<del class="diff">' . $space_regex . '*' .
					'<table\b([^>]*)>' .
					'(\X*?)' .
					'</del>' .
					'<ins class="diff">' . $space_regex . '*' .
					'<table\b([^>]*)>' .
					'(\X*?)' .
					'</ins>' .
				'~u' => function ($matches) {
					return implode('', [
						'<!-- <table' . $matches[1] . '> -->',
						'<table' . $matches[3] . '>',
						$matches[2] === '' ? '' : '<del class="diff">' . $matches[2] . '</del>',
						$matches[4] === '' ? '' : '<ins class="diff">' . $matches[4] . '</ins>',
					]);
				},

				// Inserted or removed a thead, tbody, tfoot, or caption element.
				// Demote the del/ins to wrap around the content.
				'~' .
					'<(del|ins) class="diff">' . $space_regex . '*' .
					'<(thead|tbody|tfoot|caption)\b([^>]*)>' .
					'(\X*?)' .
					'</\2>' . $space_regex . '*' .
					'</\1>' .
				'~u' => function ($matches) {
					return implode('', [
						'<' . $matches[2] . $matches[3] . '>',
						$matches[4] === '' ? '' : '<' . $matches[1] . ' class="diff">' . $matches[4] . '</' . $matches[1] . '>',
						'</' . $matches[2] . '>',
					]);
				},

				// Inserted or removed one or more tr elements.
				// Demote the del/ins to wrap around the content.
				'~' .
					'<(del|ins) class="diff">' . $space_regex . '*' .
					'<tr\b([^>]*)>' . $space_regex . '*' .
					'(\X*?)' .
					'</tr>' . $space_regex . '*' .
					'</\1>' .
				'~u' => function ($matches) use ($space_regex) {
					return implode('', [
						'<tr' . $matches[2] . '>',
						'<' . $matches[1] . ' class="diff">',
						preg_replace(
							'/<\/tr>' . $space_regex . '*<tr\b[^>]*>/u',
							'</' . $matches[1] . '>$0<' . $matches[1] . ' class="diff">',
							$matches[3],
						),
						'</' . $matches[1] . '>',
						'</tr>',
					]);
				},

				// Inserted or removed one or more th or td elements.
				// Demote the del/ins to wrap around the content.
				'~' .
					'<(del|ins) class="diff">' . $space_regex . '*' .
					'<(td|th)\b([^>]*)>' .
					'(\X*?)' .
					'</\2>' . $space_regex . '*' .
					'</\1>' .
				'~u' => function ($matches) use ($space_regex) {
					return implode('', [
						'<' . $matches[2] . $matches[3] . '>',
						'<' . $matches[1] . ' class="diff">',
						preg_replace(
							'/<\/' . $matches[2] . '>' . $space_regex . '*<' . $matches[2] . '\b[^>]*>/u',
							'</' . $matches[1] . '>$0<' . $matches[1] . ' class="diff">',
							$matches[4],
						),
						'</' . $matches[1] . '>',
						'</' . $matches[2] . '>',
					]);
				},

				// The only way to show colgroup changes is to present the
				// entire table as having been changed.
				'~' .
					'<table\b([^>]*)>' .
					'((?:[^<]|<(?!/table>))*)' .
					'<del class="diff">' . $space_regex . '*' .
					'<(col(?:group)?)\b([^>]*)>' .
					'(\X*?)' .
					'</del>' .
					'<ins class="diff">' . $space_regex . '*' .
					'<(col(?:group)?)\b([^>]*)>' .
					'(\X*?)' .
					'</ins>' .
				'~u' => function ($matches) {
					$del = '<table' . $matches[1] . '><' . $matches[3] . $matches[4] . '>' . $matches[5];
					$ins = '<table' . $matches[1] . '><' . $matches[6] . $matches[7] . '>' . $matches[8];

					return '<!-- ' . mb_strlen($ins) . ' ' . $del . ' -->' . $ins . $matches[2];
				},

				'~' .
					'<table\b([^>]*)>' .
					'((?:[^<]|<(?!/table>))*)' .
					'<del class="diff">' . $space_regex . '*' .
					'<(col(?:group)?)\b([^>]*)>' .
					'(\X*?)' .
					'</del>' .
				'~u' => function ($matches) {
					$del = '<table' . $matches[1] . '><' . $matches[3] . $matches[4] . '>' . $matches[5];
					$ins = '<table' . $matches[1] . '>';

					return '<!-- ' . mb_strlen($ins) . ' ' . $del . ' -->' . $ins . $matches[2];
				},

				'~' .
					'<table\b([^>]*)>' .
					'((?:[^<]|<(?!/table>))*)' .
					'<ins class="diff">' . $space_regex . '*' .
					'<(col(?:group)?)\b([^>]*)>' .
					'(\X*?)' .
					'</ins>' .
				'~u' => function ($matches) {
					$del = '<table' . $matches[1] . '>';
					$ins = '<table' . $matches[1] . '><' . $matches[3] . $matches[4] . '>' . $matches[5];

					return '<!-- ' . mb_strlen($ins) . ' ' . $del . ' -->' . $ins . $matches[2];
				},

				// Format whole-table changes.
				'~' .
					'<!-- (\d+) (<table\X*?) -->' .
					'(<table\b[^>]*>)' .
					'(\X*?)' .
					'</table>' .
				'~u' => function ($matches) {
					// Walk though the table, separating out the deletions and
					// insertions to create complete old and new versions of the
					// table, and then present those complete versions as the
					// deleted and inserted content.
					$del = $matches[2];
					$ins = $matches[3] . mb_substr($matches[4], 0, (int) $matches[1] - mb_strlen($matches[3]));

					$matches[4] = mb_substr($matches[4], (int) $matches[1] - mb_strlen($matches[3]));

					foreach (preg_split('/<\/(th|td|caption)>\K/u', $matches[4]) as $cell) {
						preg_match('/(\X*?)<(th|td|caption)\b([^>]*)>(\X*?)<\/\2>$/u', trim($cell), $cell_matches);

						if (isset($cell_matches[1])) {
							$del .= $cell_matches[1];
							$ins .= $cell_matches[1];
						}

						if (!isset($cell_matches[4])) {
							continue;
						}

						$content = preg_split('/(<(?:del|ins) class="diff">|<\/(?:del|ins)>)/u', $cell_matches[4], -1, PREG_SPLIT_DELIM_CAPTURE);

						$del_content = [];
						$ins_content = [];

						for ($i = 0; $i < count($content); $i++) {
							if ($content[$i] === '') {
								continue;
							}

							switch ($i % 4) {
								case 0:
									$del_content[] = $content[$i];
									$ins_content[] = $content[$i];
									break;

								case 1:
									$in = str_starts_with($content[$i], '<del') ? 'del' : 'ins';
									break;

								case 2:
									if ($in === 'del') {
										$del_content[] = $content[$i];
									} else {
										$ins_content[] = $content[$i];
									}
									break;

								case 3:
									$in = '';
									break;
							}
						}

						$del_content = implode('', $del_content);
						$ins_content = implode('', $ins_content);

						if ($del_content !== '') {
							$del .= '<' . $cell_matches[2] . $cell_matches[3] . '>' . $del_content . '</' . $cell_matches[2] . '>';
						}

						if ($ins_content !== '') {
							$ins .= '<' . $cell_matches[2] . $cell_matches[3] . '>' . $ins_content . '</' . $cell_matches[2] . '>';
						}
					}

					$del .= '</table>';
					$ins .= '</table>';

					if (str_contains($del, '<colgroup')) {
						$del = $this->cleanTableMarkup($del);
					}

					if (str_contains($ins, '<colgroup')) {
						$ins = $this->cleanTableMarkup($ins);
					}

					return '<del class="diff">' . $del . '</del><ins class="diff">' . $ins . '</ins>';
				},
			],
			$formatted,
		);

		// Simpler stuff.
		$formatted = preg_replace_callback_array(
			[
				// Changed tags but not content.
				'~' .
					'<del class="diff">' . $space_regex . '*' .
					'<(\w+)\b([^>]*)>' . $space_regex . '*' .
					'</del>' .
					'<ins class="diff">' . $space_regex . '*' .
					'<(\w+)\b([^>]*)>' . $space_regex . '*' .
					'</ins>' .
					'(\X*?)' .
					'<del class="diff">' . $space_regex . '*' .
					'</\1>' . $space_regex . '*' .
					'</del>' .
					'<ins class="diff">' . $space_regex . '*' .
					'</\3>' . $space_regex . '*' .
					'</ins>' .
				'~u' => function ($matches) use (&$placeholders) {
					$del_content = $matches[5];
					$ins_content = $matches[5];

					// Deal with nested differences.
					if (
						str_contains($matches[5], '<del class="diff">')
						|| str_contains($matches[5], '<ins class="diff">')
					) {
						// If this element prefers showing its internal changes, do so.
						if (
							self::HTML_DIFF_PREFER_CONTENT[$matches[1]] ?? false
							|| self::HTML_DIFF_PREFER_CONTENT[$matches[3]] ?? false
						) {
							return (
								'<' . $matches[3] . $matches[4] . '>' .
								$this->formatMarkup($matches[5]) .
								'</' . $matches[3] . '>'
							);
						}

						while (str_contains($del_content, '<del class="diff">')) {
							$del_content = preg_replace(
								[
									'/<del class="diff">(\X*?)<\/del>/u',
									'/<ins class="diff">(\X*?)<\/ins>/u',
								],
								[
									'$1',
									'',
								],
								$del_content,
							);
						}

						while (str_contains($ins_content, '<ins class="diff">')) {
							$ins_content = preg_replace(
								[
									'/<del class="diff">(\X*?)<\/del>/u',
									'/<ins class="diff">(\X*?)<\/ins>/u',
								],
								[
									'',
									'$1',
								],
								$ins_content,
							);
						}
					}

					$placeholders[md5($matches[0])] =
						'<del class="diff">' .
						'<' . $matches[1] . $matches[2] . '>' .
						$del_content .
						'</' . $matches[1] . '>' .
						'</del>' .
						'<ins class="diff">' .
						'<' . $matches[3] . $matches[4] . '>' .
						$ins_content .
						'</' . $matches[3] . '>' .
						'</ins>';

					return md5($matches[0]);
				},

				// Changed attributes and possibly content.
				'~' .
					'<del class="diff">' . $space_regex . '*' .
					'<(\w+)\b([^<>]*)>(\X*?)' .
					'</del>' .
					'<ins class="diff">' . $space_regex . '*' .
					'<\1\b([^<>]*)>(\X*?)' .
					'</ins>' .
					'(\X*?)' .
					'</\1>' .
				'~u' => function ($matches) use (&$placeholders) {
					$del_content = $matches[3] . $matches[6];
					$ins_content = $matches[5] . $matches[6];

					// If we have nested differences and this element prefers
					// showing its internal changes, do so.
					if (
						self::HTML_DIFF_PREFER_CONTENT[$matches[1]] ?? false
						&& (
							str_contains($del_content, '<del class="diff">')
							|| str_contains($del_content, '<ins class="diff">')
							|| str_contains($ins_content, '<del class="diff">')
							|| str_contains($ins_content, '<ins class="diff">')
						)
					) {
						return (
							'<' . $matches[1] . $matches[4] . '>' .
							$this->formatMarkup($ins_content) .
							'</' . $matches[1] . '>'
						);
					}

					// Deal with nested differences in $del_content.
					while (
						str_contains($del_content, '<del class="diff">')
						|| str_contains($del_content, '<ins class="diff">')
					) {
						$del_content = preg_replace(
							[
								'/<del class="diff">(\X*?)<\/del>/u',
								'/<ins class="diff">(\X*?)<\/ins>/u',
							],
							[
								'$1',
								'',
							],
							$del_content,
						);
					}

					// Deal with nested differences in $ins_content.
					while (
						str_contains($ins_content, '<del class="diff">')
						|| str_contains($ins_content, '<ins class="diff">')
					) {
						$ins_content = preg_replace(
							[
								'/<del class="diff">(\X*?)<\/del>/u',
								'/<ins class="diff">(\X*?)<\/ins>/u',
							],
							[
								'',
								'$1',
							],
							$ins_content,
						);
					}

					$placeholders[md5($matches[0])] =
						'<del class="diff">' .
						'<' . $matches[1] . $matches[2] . '>' .
						$del_content .
						'</' . $matches[1] . '>' .
						'</del>' .
						'<ins class="diff">' .
						'<' . $matches[1] . $matches[4] . '>' .
						$ins_content .
						'</' . $matches[1] . '>' .
						'</ins>';

					return md5($matches[0]);
				},

				// Inserted tags.
				'~' .
					'<ins class="diff">' . $space_regex . '*' .
					'<(\w+)\b([^<>]*)>' . $space_regex . '*' .
					'</ins>' .
					'(\X*?)' .
					'<ins class="diff">' . $space_regex . '*' .
					'</\1>' . $space_regex . '*' .
					'</ins>' .
				'~u' => function ($matches) use (&$placeholders) {
					// Deal with nested differences.
					if (
						str_contains($matches[3], '<del class="diff">')
						|| str_contains($matches[3], '<ins class="diff">')
					) {
						// If this element prefers showing its internal changes, do so.
						if (self::HTML_DIFF_PREFER_CONTENT[$matches[1]] ?? false) {
							return (
								'<' . $matches[1] . $matches[2] . '>' .
								$this->formatMarkup($matches[3]) .
								'</' . $matches[1] . '>'
							);
						}

						do {
							$matches[3] = preg_replace(
								[
									'/<del class="diff">(\X*?)<\/del>/u',
									'/<ins class="diff">(\X*?)<\/ins>/u',
								],
								'$1',
								$matches[3],
							);
						} while (
							str_contains($matches[3], '<del class="diff">')
							|| str_contains($matches[3], '<ins class="diff">')
						);
					}

					$placeholders[md5($matches[0])] =
						'<del class="diff">' .
						$matches[3] .
						'</del>' .
						'<ins class="diff">' .
						'<' . $matches[1] . $matches[2] . '>' .
						$matches[3] .
						'</' . $matches[1] . '>' .
						'</ins>';

					return md5($matches[0]);
				},

				// Deleted tags.
				'~' .
					'<del class="diff">' . $space_regex . '*' .
					'<(\w+)\b([^<>]*)>' . $space_regex . '*' .
					'</del>' .
					'(\X*?)' .
					'<del class="diff">' . $space_regex . '*' .
					'</\1>' . $space_regex . '*' .
					'</del>' .
				'~u' => function ($matches) use (&$placeholders) {
					// Deal with nested differences.
					if (
						str_contains($matches[3], '<del class="diff">')
						|| str_contains($matches[3], '<ins class="diff">')
					) {
						// If this element prefers showing its internal changes, do so.
						if (self::HTML_DIFF_PREFER_CONTENT[$matches[1]] ?? false) {
							return (
								'<' . $matches[1] . $matches[2] . '>' .
								$this->formatMarkup($matches[3]) .
								'</' . $matches[1] . '>'
							);
						}

						do {
							$matches[3] = preg_replace(
								[
									'/<del class="diff">(\X*?)<\/del>/u',
									'/<ins class="diff">(\X*?)<\/ins>/u',
								],
								'$1',
								$matches[3],
							);
						} while (
							str_contains($matches[3], '<del class="diff">')
							|| str_contains($matches[3], '<ins class="diff">')
						);
					}

					$placeholders[md5($matches[0])] =
						'<del class="diff">' .
						'<' . $matches[1] . $matches[2] . '>' .
						$matches[3] .
						'</' . $matches[1] . '>' .
						'</del>' .
						'<ins class="diff">' .
						$matches[3] .
						'</ins>';

					return md5($matches[0]);
				},
			],
			$formatted,
		);

		$formatted = strtr($formatted, $placeholders);

		return $formatted;
	}

	/**
	 * Cleans up whitespace and <br> elements between list structure elements.
	 *
	 * @param string $str The string to clean.
	 * @param bool $oneline Whether to flatten the list into a single line.
	 *    Default: true.
	 * @return string The cleaned string.
	 */
	protected function cleanListMarkup(string $str, bool $oneline = true): string
	{
		if (!str_contains($str, '<ol') && !str_contains($str, '<ul') && !str_contains($str, '<menu')) {
			return $str;
		}

		$space_regex =
			'(' .
				'(\h|' . Utils::ENT_NBSP . ')*' .
				'(<span class="diff_blank_line">)?' .
				'(<br\b[^>]*>|\R)*' .
				'(</span>)?' .
				'(\h|' . Utils::ENT_NBSP . ')*' .
			')+';

		$str = preg_replace(
			[
				'~<((ol|ul|menu)\b[^>]*|/li)>\K' . $space_regex . '~u',
				'~' . $space_regex . '(?=<(li\b[^>]*|/(ol|ul|menu))>)~u',
			],
			$oneline ? '' : "\n",
			$str,
		);

		return $str;
	}

	/**
	 * Cleans up whitespace and <br> elements between table structure elements
	 * and ensures all elements are in canonical order.
	 *
	 * @param string $str The string to clean.
	 * @param bool $oneline Whether to flatten the table into a single line.
	 *    Default: true.
	 * @return string The cleaned string.
	 */
	protected function cleanTableMarkup(string $str, bool $oneline = true): string
	{
		if (!str_contains($str, '<table')) {
			return $str;
		}

		// Clean.
		$space_regex =
			'(' .
				'(\h|' . Utils::ENT_NBSP . ')*' .
				'(<span class="diff_blank_line">)?' .
				'(<br\b[^>]*>|\R)*' .
				'(</span>)?' .
				'(\h|' . Utils::ENT_NBSP . ')*' .
			')+';

		$str = preg_replace(
			[
				'~<(table|colgroup|col|tbody|thead|tfoot|tr)\b[^>]*>\K' . $space_regex . '~u',
				'~</(colgroup|col|tbody|thead|tfoot|tr)>\K' . $space_regex . '~u',
				'~</(caption|td|th)>\K' . $space_regex . '~u',
				'~' . $space_regex . '(?=</(table|colgroup|col|tbody|thead|tfoot|tr)>)~u',
				'~' . $space_regex . '(?=<(colgroup|col|tbody|thead|tfoot|tr)\b[^>]*>)~u',
				'~' . $space_regex . '(?=<(caption|td|th)\b[^>]*>)~u',
			],
			'',
			$str,
		);

		// Reconstruct.
		$parts = preg_split('/(<table\b[^>]*>\X*?<\/table>)/u', $str, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($p = 0; $p < count($parts); $p++) {
			if ($p % 2 === 1) {
				// Parse the table.
				$table = [
					'table' => [],
					'caption' => [],
					'colgroup' => [],
					'thead' => [],
					'tbody' =>  [],
					'tfoot' =>  [],
				];

				$table_parts = preg_split('/(<\/?(?:table|colgroup|col|tbody|thead|tfoot|tr|caption|td|th)\b[^>]*>)/u', $parts[$p], -1, PREG_SPLIT_DELIM_CAPTURE);

				$group = 'table';
				$buffer = [];
				$use_buffer = false;

				for ($i = 0; $i < count($table_parts); $i++) {
					if ($table_parts[$i] === '</table>') {
						continue;
					}

					if (str_starts_with($table_parts[$i], '<col') || $table_parts[$i] === '</colgroup>') {
						$group = 'colgroup';
					}

					if (preg_match('/^<(caption|thead|tbody|tfoot)\b/u', $table_parts[$i], $matches)) {
						$group = $matches[1];
					}

					if (str_starts_with($table_parts[$i], '<tr') && in_array($group, ['table', 'caption', 'colgroup'])) {
						$group = 'tbody';
					}

					if (preg_match('/^<(del|ins) class="diff">/u', $table_parts[$i])) {
						$use_buffer = true;
					}

					if ($use_buffer) {
						$buffer[] = $table_parts[$i];
					} else {
						while (!empty($buffer)) {
							$table[$group][] = array_shift($buffer);
						}

						$table[$group][] = $table_parts[$i];
					}

					if (preg_match('/^<\/(caption|colgroup|thead|tbody|tfoot)>/u', $table_parts[$i], $matches)) {
						$use_buffer = false;
					}
				}

				// Compile the table.
				$parts[$p] = implode('', $table['table']);

				if (!empty($table['caption'])) {
					$parts[$p] .= $oneline ? '' : "\n";
					$parts[$p] .= implode('', $table['caption']);
					$parts[$p] .= $oneline ? '' : "\n";
				}

				$parts[$p] .= implode('', $table['colgroup']);

				foreach (['thead', 'tbody', 'tfoot'] as $section) {
					foreach ($table[$section] as $key => $value) {
						if (
							!$oneline
							&& str_starts_with($value, '<tr')
							&& !str_ends_with($parts[$p], "\n")
						) {
							$parts[$p] .= "\n";
						}

						$parts[$p] .= $value;

						if (!$oneline && $value === '</tr>') {
							$parts[$p] .= "\n";
						}
					}
				}

				$parts[$p] .= '</table>';
			}
		}

		return implode('', $parts);
	}
}
