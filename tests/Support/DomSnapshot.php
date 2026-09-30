<?php

declare(strict_types=1);

namespace SMF\Tests\Support;

/**
 * Turns a rendered page into a stable text description of what the templates
 * produced, for comparing against a copy taken earlier.
 *
 * The aim is to notice any change in what the templates emit while ignoring
 * everything that changes between two renders of the same templates. Each of the
 * rules below exists because one of those things would otherwise show up as a
 * difference:
 *
 *  - Whitespace, comments, attribute order and class order are not recorded.
 *    Rewriting a template in another engine reflows all of them, and none of
 *    them change what the page is.
 *  - The board URL, the session, security tokens and hashes are replaced by
 *    placeholders, since they differ per forum and per visit.
 *  - Dates and times are replaced by {date}, lengths of time by {duration},
 *    and every other run of digits by #. Counts, ids and timestamps are the
 *    forum's data, not the template's.
 *  - The word after a count is written the same whether it is singular or
 *    plural - "# reply(s)" - since the count decides which one appears.
 *  - The text of a link to a profile, a board or a topic is replaced by
 *    {member}, {board} or {subject}, and so is that same text wherever else it
 *    appears on the page. Names and subjects are data too.
 *  - A sibling, or a group of siblings, that repeats the one right before it
 *    is left out. After the masking above, ten rows of a list look the same,
 *    so a list reads the same whether it holds two rows or two hundred.
 *
 * What is left is the structure of the page, the attributes on it and the text
 * the templates and language files put there, which is what a template
 * conversion has to keep.
 *
 * The price of the last rule is that an element emitted twice in a row where it
 * used to be emitted once is not noticed. Everything else about a list - a column
 * lost, a class misspelt, a string gone missing - still is.
 */
final class DomSnapshot
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Elements whose text is code rather than prose, and is recorded verbatim
	 * apart from the masking.
	 */
	private const RAW_TEXT = ['script', 'style'];

	/**
	 * The longest group of siblings recognised as repeating. See
	 * withoutRepeats().
	 */
	private const MAX_PERIOD = 4;

	/**
	 * Attributes that hold URLs. Names are never substituted into these, since a
	 * member called "admin" would otherwise turn every ?action=admin link into
	 * ?action={member}.
	 */
	private const URL_ATTRIBUTES = ['href', 'src', 'action', 'srcset', 'formaction', 'data-src', 'content'];

	/**
	 * Month names and abbreviations, for recognising a formatted date.
	 */
	private const MONTHS = 'January|February|March|April|May|June|July|August|September|October|November|December'
		. '|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Literal strings to replace, longest first, string => placeholder.
	 */
	private array $literals = [];

	/**
	 * @var array Names and subjects found on the page, longest first,
	 *     string => placeholder.
	 */
	private array $vocabulary = [];

	/**
	 * @var \SplObjectStorage Elements whose children are compared sorted.
	 *
	 * Not a WeakMap. PHP makes a new object for a DOM node whenever nothing is
	 * holding the last one, so a map that does not hold them itself finds none
	 * of them again by the time the tree is walked.
	 */
	private \SplObjectStorage $unordered;

	/****************
	 * Public methods
	 ****************/

	/**
	 * @param array $literals Strings known in advance to vary, such as the
	 *     board URL, as string => placeholder.
	 */
	public function __construct(array $literals = [])
	{
		foreach ($literals as $literal => $placeholder) {
			if ((string) $literal !== '') {
				$this->literals[(string) $literal] = $placeholder;
			}
		}
	}

	/**
	 * Describes the parts of a page picked out by the given selectors.
	 *
	 * @param string $html The page.
	 * @param array $roots CSS selectors for what to describe. Every match of
	 *     every selector is described, in document order within a selector.
	 * @param array $ignore CSS selectors for what to leave out. Each match is
	 *     replaced with a marker, so that it is visible in the snapshot that
	 *     something was there.
	 * @param array $masks CSS selectors for elements whose text is the forum's
	 *     data rather than anything a template wrote, such as a member's post
	 *     group. The element is kept and its text becomes {text}.
	 * @param array $only For lists that show everything on the forum, such as
	 *     a picker of every board: CSS selector => text. An element matching
	 *     the selector is left out unless its text, or its label, contains the
	 *     given text, or always when the text is null. Nothing marks where it
	 *     was, since how many there were, or whether there was one at all, is
	 *     the forum's business.
	 * @param array $unordered CSS selectors for elements whose children come in
	 *     whatever order the database returned them, which is not the same on
	 *     MySQL as on PostgreSQL. Their children are compared sorted.
	 * @return string The description, one node per line.
	 */
	public function describe(string $html, array $roots, array $ignore = [], array $masks = [], array $only = [], array $unordered = []): string
	{
		// A date from today or yesterday is written "<strong>Today</strong> at
		// 10:00", and any other as "Jan 15, 2020, 10:00". Which one a page gets
		// depends on when it is looked at, so the markup is taken out of the
		// first for the date rule to treat both alike.
		$html = (string) preg_replace('~<strong>(Today|Yesterday)</strong>~', '$1', $html);

		$document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);

		$this->collectLiterals($document);
		$this->collectVocabulary($document);

		foreach ($ignore as $selector) {
			foreach ($document->querySelectorAll($selector) as $node) {
				$marker = $document->createElement('snapshot-ignored');
				$marker->setAttribute('selector', $selector);
				$node->replaceWith($marker);
			}
		}

		foreach ($masks as $selector) {
			foreach ($document->querySelectorAll($selector) as $node) {
				$node->textContent = '{text}';
			}
		}

		foreach ($only as $selector => $needle) {
			foreach ($document->querySelectorAll($selector) as $node) {
				if ($needle === null || !str_contains($node->textContent . ' ' . $node->getAttribute('label'), $needle)) {
					$node->remove();
				}
			}
		}

		$this->unordered = new \SplObjectStorage();

		foreach ($unordered as $selector) {
			foreach ($document->querySelectorAll($selector) as $node) {
				$this->unordered->attach($node);
			}
		}

		$out = [];

		foreach ($roots as $selector) {
			$found = $document->querySelectorAll($selector);

			if ($found->length === 0) {
				$out[] = '(nothing matches ' . $selector . ')';

				continue;
			}

			foreach ($found as $node) {
				// Masked like anything else, since a selector may name a fixture
				// by its id, and the ids differ from forum to forum.
				$out[] = '@ ' . $this->mask($selector, true);
				array_push($out, ...$this->node($node, 0));
			}
		}

		return implode("\n", $out) . "\n";
	}

	/**
	 * Applies the masking rules to a string on its own.
	 *
	 * @param string $value The text or attribute value.
	 * @param bool $is_url Whether it is a URL, where names are left alone.
	 * @return string The masked value.
	 */
	public function mask(string $value, bool $is_url = false): string
	{
		$value = strtr($value, $this->literals);

		// An address is always somebody's data: a member's, or the one the
		// forum was installed with.
		$value = (string) preg_replace('~[\w.+-]+@[\w-]+(?:\.[\w-]+)+~u', '{email}', $value);

		// A search is carried from page to page encoded in one parameter, which
		// holds the ids of the boards it was narrowed to.
		$value = (string) preg_replace('~(?<=[?;&])params=[^;&#"\s]+~', 'params={params}', $value);

		if (!$is_url && $this->vocabulary !== []) {
			foreach ($this->vocabulary as $word => $placeholder) {
				$value = (string) preg_replace('~(?<![\w{])' . preg_quote((string) $word, '~') . '(?![\w}])~u', $placeholder, $value);
			}
		}

		// Formatted dates, in the forms the default time formats produce, and
		// the relative forms used for recent ones.
		$value = (string) preg_replace(self::datePatterns(), '{date}', $value);

		// Hashes: session ids, security tokens, minified file names, cache
		// busters. Anything long and hexadecimal is not something a template
		// chose.
		// Bounded by what is not hexadecimal rather than by \b, since the name of
		// a minified file puts an underscore in front of it, and an underscore
		// is a word character.
		$value = (string) preg_replace('~(?<![a-f0-9])[a-f0-9]{32,}(?![a-f0-9])~i', '{hash}', $value);

		// Added to a link to the latest post when its board has not been seen,
		// which is the viewer's business.
		$value = str_replace(';boardseen', '', $value);

		// A token in a query string: a random name paired with a hash.
		$value = (string) preg_replace('~\b[a-z0-9]{5,16}=\{hash\}~i', '{token}', $value);

		// Everything else numeric. An average reads 3 on one forum and 2.5 on
		// another, and a total 999 on one and 1,000 on another, so the
		// separators go with the digits.
		$value = (string) preg_replace('~\d+(?:[.,]\d+)*~', '#', $value);

		// Lengths of time, which grow a unit as they grow: "5 minutes", then
		// "1 hour and 5 minutes".
		$value = (string) preg_replace(
			'~#\s(?:second|minute|hour|day|week|month|year)s?(?:(?:,\s|,?\sand\s)#\s(?:second|minute|hour|day|week|month|year)s?)*~u',
			'{duration}',
			$value,
		);

		// A count decides between "1 reply" and "2 replies", and the count is
		// data, so the word that follows it is recorded in a form that reads
		// the same either way.
		return (string) preg_replace_callback(
			'~(#\s)([A-Za-z]{2,}?)(ies|y|es|s)?\b~',
			static function (array $match): string {
				[, $count, $stem] = $match;

				$singular = match ($match[3] ?? '') {
					'ies', 'y' => $stem . 'y',
					'es' => preg_match('~(?:s|x|z|ch|sh)$~', $stem) ? $stem : $stem . 'e',
					default => $stem,
				};

				return $count . $singular . '(s)';
			},
			$value,
		);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Describes one node and everything under it.
	 *
	 * @param \Dom\Node $node The node.
	 * @param int $depth How far to indent.
	 * @return array The lines.
	 */
	private function node(\Dom\Node $node, int $depth): array
	{
		if ($node instanceof \Dom\Text) {
			$text = trim((string) preg_replace('~\s+~u', ' ', (string) $node->data));

			return $text === '' ? [] : [str_repeat('  ', $depth) . json_encode($this->mask($text), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
		}

		if (!$node instanceof \Dom\Element) {
			return [];
		}

		$lines = [str_repeat('  ', $depth) . $this->label($node)];

		if (\in_array($node->localName, self::RAW_TEXT, true)) {
			$code = trim((string) preg_replace('~\s+~u', ' ', (string) $node->textContent));

			if ($code !== '') {
				$lines[] = str_repeat('  ', $depth + 1) . '| ' . $this->mask($code, true);
			}

			return $lines;
		}

		// Text split across several nodes - by a comment, say - reads the same
		// as text that is not, so adjacent text is joined before it is written.
		$children = [];
		$pending = '';

		foreach ($node->childNodes as $child) {
			if ($child instanceof \Dom\Comment) {
				continue;
			}

			if ($child instanceof \Dom\Text) {
				$pending .= $child->data;

				continue;
			}

			if ($pending !== '') {
				$children[] = ['text', $this->textLines($pending, $depth + 1)];
				$pending = '';
			}

			$children[] = ['element', $this->node($child, $depth + 1)];
		}

		if ($pending !== '') {
			$children[] = ['text', $this->textLines($pending, $depth + 1)];
		}

		$keys = [];

		foreach ($children as [, $child_lines]) {
			if ($child_lines !== []) {
				$keys[] = implode("\n", $child_lines);
			}
		}

		if ($this->unordered->contains($node)) {
			sort($keys);
		}

		// Repeated siblings are recorded once. See the class description.
		foreach (self::withoutRepeats($keys) as $key) {
			$lines[] = $key;
		}

		return $lines;
	}

	/**
	 * Describes a run of text.
	 *
	 * @param string $text The text.
	 * @param int $depth How far to indent.
	 * @return array Nothing, or one line.
	 */
	private function textLines(string $text, int $depth): array
	{
		$text = trim((string) preg_replace('~\s+~u', ' ', $text));

		return $text === '' ? [] : [str_repeat('  ', $depth) . json_encode($this->mask($text), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
	}

	/**
	 * The one line that stands for an element: tag, id, classes and attributes.
	 *
	 * @param \Dom\Element $element The element.
	 * @return string The line, without indentation.
	 */
	private function label(\Dom\Element $element): string
	{
		$label = $element->localName;

		$id = $element->getAttribute('id');

		if ($id !== null && $id !== '') {
			$label .= '#' . $this->mask($id, true);
		}

		$classes = preg_split('~\s+~', trim((string) $element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		sort($classes);

		foreach ($classes as $class) {
			$label .= '.' . $class;
		}

		$attributes = [];

		foreach ($element->attributes as $attribute) {
			$name = $attribute->name;

			if ($name === 'id' || $name === 'class') {
				continue;
			}

			$attributes[$name] = $attribute->value === ''
				? $name
				: $name . '=' . json_encode($this->mask($attribute->value, \in_array($name, self::URL_ATTRIBUTES, true)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		ksort($attributes);

		return $attributes === [] ? $label : $label . ' [' . implode(' ', $attributes) . ']';
	}

	/**
	 * Finds the session, which is on every page, so that it can be replaced as
	 * a literal wherever it appears - in a URL, a hidden field or a script.
	 *
	 * @param \Dom\HTMLDocument $document The page.
	 */
	private function collectLiterals(\Dom\HTMLDocument $document): void
	{
		foreach ($document->querySelectorAll('script') as $script) {
			if (preg_match('~smf_session_var\s*=\s*["\']([^"\']+)~', (string) $script->textContent, $var)) {
				$this->literals[$var[1]] = '{session_var}';
			}

			if (preg_match('~smf_session_id\s*=\s*["\']([^"\']+)~', (string) $script->textContent, $id)) {
				$this->literals[$id[1]] = '{session_id}';
			}
		}

		// A hidden field whose value is a hash has a random name as well: the
		// session check, or a security token.
		foreach ($document->querySelectorAll('input[type="hidden"]') as $input) {
			$value = (string) $input->getAttribute('value');
			$name = (string) $input->getAttribute('name');

			if ($name !== '' && preg_match('~^[a-f0-9]{32,}$~i', $value) && preg_match('~^[a-z0-9]{5,16}$~i', $name)) {
				$this->literals[$name] ??= '{token_var}';
			}
		}

		uksort($this->literals, static fn($a, $b) => \strlen((string) $b) <=> \strlen((string) $a));
	}

	/**
	 * Collects the names of members, boards and topics that the page links to.
	 *
	 * @param \Dom\HTMLDocument $document The page.
	 */
	private function collectVocabulary(\Dom\HTMLDocument $document): void
	{
		$patterns = [
			'{member}' => '~[?;&]action=profile(?:;area=summary)?;u=\d+$~',
			'{board}' => '~[?;&]board=\d+\.\d+$~',
			'{subject}' => '~[?;&](?:topic=\d+\.(?:\d+|msg\d+|new)(?:;boardseen)?(?:#\w+)?|msg=\d+)$~',
		];

		foreach ($document->querySelectorAll('a[href]') as $link) {
			$href = (string) $link->getAttribute('href');

			foreach ($patterns as $placeholder => $pattern) {
				// A board or topic link is one with nothing else in its query
				// string. The print button ends in ;topic=1.0 as well, and its
				// text is "Print", not a subject.
				if ($placeholder !== '{member}' && str_contains($href, 'action=')) {
					continue;
				}

				if (!preg_match($pattern, $href)) {
					continue;
				}

				// Long subjects are shortened in the link text and given in full
				// in the title, so both are data. Other links' titles are
				// sentences such as "New Posts", which are not.
				$candidates = [(string) $link->textContent];

				if ($placeholder === '{subject}' && str_contains($href, 'topic=')) {
					$candidates[] = (string) $link->getAttribute('title');
				}

				foreach ($candidates as $text) {
					$text = trim((string) preg_replace('~\s+~u', ' ', $text));

					// A post's own link is often its time, which the date rule
					// deals with, and which must not become a {subject}.
					if (mb_strlen($text) < 2 || preg_replace(self::datePatterns(), '', $text) !== $text) {
						continue;
					}

					$this->vocabulary[$text] ??= $placeholder;

					// "Re: Subject" and "Subject" are the same data.
					if ($placeholder === '{subject}' && str_starts_with($text, 'Re: ')) {
						$this->vocabulary[substr($text, 4)] ??= $placeholder;
					}
				}

				break;
			}
		}

		uksort($this->vocabulary, static fn($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Drops a sibling, or a group of siblings, that repeats the one before it.
	 *
	 * Rows of a table repeat one at a time; a definition list repeats as dt and
	 * dd; a list of links repeats as the link and the ", " after it. Looking for
	 * groups of up to MAX_PERIOD siblings covers all of those, while two dd
	 * elements that happen to read the same but belong to different dt elements
	 * are not a repeat and are both kept.
	 *
	 * @param array $keys The siblings, each described as a string.
	 * @return array The same, with repeats removed.
	 */
	private static function withoutRepeats(array $keys): array
	{
		$kept = [];
		$count = \count($keys);
		$i = 0;

		while ($i < $count) {
			for ($period = 1; $period <= self::MAX_PERIOD; $period++) {
				if (
					$i >= $period
					&& $i + $period <= $count
					&& \array_slice($keys, $i, $period) === \array_slice($keys, $i - $period, $period)
				) {
					$i += $period;

					continue 2;
				}
			}

			$kept[] = $keys[$i++];
		}

		return $kept;
	}

	/**
	 * Formatted dates, in the forms the default time formats produce, and the
	 * relative forms used for recent ones.
	 *
	 * @return array Regular expressions.
	 */
	private static function datePatterns(): array
	{
		$time = '\d{1,2}:\d{2}(?::\d{2})?(?: ?[AaPp][Mm])?';

		return [
			'~\b(?:' . self::MONTHS . ')\.? \d{1,2}(?:st|nd|rd|th)?,? \d{4}(?:,? (?:at )?' . $time . ')?~u',
			'~\b\d{1,2} (?:' . self::MONTHS . ')\.? \d{4}(?:,? ' . $time . ')?~u',
			'~\b(?:' . self::MONTHS . ') \d{4}\b~u',
			'~\b(?:Today|Yesterday)(?: at)? ' . $time . '~u',
			'~\b\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?~',
			'~\b\d{1,2}:\d{2}(?::\d{2})? ?[AaPp][Mm]\b~',
		];
	}
}
