<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

use SMF\BBCode\BBCode;
use SMF\Cache\CacheApi;
use SMF\Parsers\BBCodeParser;
use SMF\Parsers\MarkdownParser;
use SMF\Parsers\SmileyParser;

/**
 * Class Parser
 */
abstract class Parser
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var int
	 *
	 * Indicates that BBCode should be parsed in the input string.
	 */
	public const INPUT_BBC = 0b001;

	/**
	 * @var int
	 *
	 * Indicates that Markdown should be parsed in the input string.
	 */
	public const INPUT_MARKDOWN = 0b010;

	/**
	 * @var int
	 *
	 * Indicates that smileys should be parsed in the input string.
	 *
	 * When the output type is HTML, this controls whether smiley text will
	 * be transformed into <img> tags pointing to smiley images.
	 *
	 * When the output type is plain text, this controls whether <img> tags for
	 * smiley images will be transformed into smiley text or removed.
	 *
	 * When the output type is BBCode, this controls whether <img> tags for
	 * smiley images will be transformed into smiley text or [img] BBCodes.
	 */
	public const INPUT_SMILEYS = 0b100;

	/**
	 * @var int
	 *
	 * Used to set the output to HTML.
	 *
	 * This is the default output type.
	 */
	public const OUTPUT_HTML = 0;

	/**
	 * @var int
	 *
	 * Used to set the output to plain text.
	 *
	 * When this is used, the input will be parsed into HTML and then the HTML
	 * tags will be stripped.
	 */
	public const OUTPUT_TEXT = 1;

	/**
	 * @var int
	 *
	 * Used to set the output to BBCode.
	 *
	 * When this is used, HTML and Markdown in the input will be transformed
	 * into the equivalent BBCode. Unsupported HTML tags will be removed.
	 */
	public const OUTPUT_BBC = 2;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * If not empty, only these BBCode tags will be parsed.
	 */
	public array $parse_tags = [];

	/**
	 * @var array
	 *
	 * List of disabled BBCode tags.
	 */
	public array $disabled = [];

	/**
	 * @var bool
	 *
	 * Enables special handling if output is meant for paper printing.
	 */
	public bool $for_print = false;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Default options for the various parsers.
	 *
	 * - cache_id:
	 *     ID string to identify the string for caching purposes.
	 *     If empty, an ID will be generated automatically.
	 *     Default: ''
	 *
	 * - parse_tags:
	 *     A list of specific BBC tags to parse. If empty, all BBC are parsed.
	 *     Default: []
	 *
	 * - for_print:
	 *     Whether the output is intended for a non-interactive medium, such
	 *     as being printed on paper.
	 *     Default: false
	 *
	 * - hard_breaks:
	 *     Controls how line breaks are handled by MarkdownParser. For more
	 *     info, see the documentation for MarkdownParser::__construct().
	 *     Default: null
	 *
	 * - str_replace:
	 *     String replacements to apply when converting to plain text.
	 *     Keys are the strings to find, and values are the replacements.
	 *     These replacements are applied after the input has been transformed
	 *     into HTML and before the HTML tags are stripped out.
	 *     Default: []
	 *
	 * - preg_replace:
	 *     Similar to str_replace, except that the keys are regular expressions.
	 *     Default: []
	 *
	 * Mods implementing custom parsers can add values to this array using the
	 * integrate_parser_options hook.
	 */
	public static array $defalt_options = [
		'cache_id' => '',
		'parse_tags' => [],
		'for_print' => false,
		'hard_breaks' => null,
		'no_paragraphs' => false,
		'str_replace' => [],
		'preg_replace' => [],
	];

	/**
	 * @var array
	 *
	 * Language files that should be loaded in order to populate any Lang::$txt
	 * strings that are used by BBCodes.
	 *
	 * Mods implementing custom BBCodes can add values to this array using the
	 * integrate_parser_static_vars hook.
	 */
	public static array $lang_files = [
		'General',
		'Modifications',
	];

	/**
	 * @var bool
	 *
	 * Whether BBCode should be parsed.
	 */
	public static bool $enable_bbc;

	/**
	 * @var bool
	 *
	 * Whether to allow certain basic HTML tags in the input.
	 */
	public static bool $enable_post_html;

	/**
	 * @var bool
	 *
	 * Whether Markdown should be parsed.
	 */
	public static bool $enable_markdown;

	/**
	 * @var string
	 *
	 * The smiley set to use when parsing smileys.
	 */
	public static string $smiley_set;

	/**
	 * @var bool
	 *
	 * Whether custom smileys are enabled.
	 */
	public static bool $custom_smileys_enabled;

	/**
	 * @var string
	 *
	 * URL of the base smileys directory.
	 */
	public static string $smileys_url;

	/**
	 * @var string
	 *
	 * Language locale to use.
	 */
	public static string $locale;

	/**
	 * @var int
	 *
	 * User's time offset from UTC.
	 */
	public static int $time_offset;

	/**
	 * @var string
	 *
	 * User's time format string.
	 */
	public static string $time_format;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Holds parsed messages.
	 */
	private static array $results = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		self::setStaticVars();
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Transforms one type of markup into another.
	 *
	 * Supported input markup types are BBCode, Markdown, and smileys.
	 * Supported output markup types are HTML, BBCode, and plain text.
	 *
	 * @param string $string The string in which to transform markup.
	 * @param int $input_types Bitmask of this class's INPUT_* constants.
	 *    Only the indicated types of markup will be parsed in the input string.
	 *    Default: self::INPUT_BBC | self::INPUT_MARKDOWN | self::INPUT_SMILEYS
	 * @param int $output_type One of this class's INPUT_* constants.
	 *    Default: self::OUTPUT_HTML
	 * @param array $options Various parser options. See self::$default_options.
	 * @return string The transformed string.
	 */
	public static function transform(
		string $string,
		int $input_types = self::INPUT_BBC | self::INPUT_MARKDOWN | self::INPUT_SMILEYS,
		int $output_type = self::OUTPUT_HTML,
		array $options = [],
	): string {
		self::setStaticVars();

		// Fill in any missing options.
		$options = self::setOptions($options);

		// Map output types to handlers.
		$handlers = [
			self::OUTPUT_HTML => __CLASS__ . '::toHTML',
			self::OUTPUT_TEXT => __CLASS__ . '::toText',
			self::OUTPUT_BBC => __CLASS__ . '::toBBC',
		];

		// Allow mods to add their own handlers.
		IntegrationHook::call('integrate_parser_output_handlers', [&$handlers]);

		// If BBCode or Markdown are disabled, respect that.
		if (!self::$enable_bbc /* && !self::$enable_post_html */) {
			$input_types = $input_types & ~self::INPUT_BBC;
		}

		if (!self::$enable_markdown) {
			$input_types = $input_types & ~self::INPUT_MARKDOWN;
		}

		// Do nothing if the requested output type is invalid.
		if (!\is_callable($handlers[$output_type] ?? null)) {
			return $string;
		}

		// Have we already parsed this string?
		// Or maybe we cached the results recently?
		$cache_key = self::getCacheKey($string, $input_types, $output_type, $options);

		if ((self::$results[$cache_key] = CacheApi::get($cache_key, 240)) != null) {
			return self::$results[$cache_key];
		}

		// Keep track of how long this takes.
		$cache_t = microtime(true);

		// Do the job.
		self::$results[$cache_key] = $handlers[$output_type]($string, $input_types, $options);

		// Change paragraphs back to breaks if that option was given.
		if ($options['no_paragraphs']) {
			self::$results[$cache_key] = preg_replace(['~<p>(.*?)</p>~u', '~<br>$~u'], ['$1<br>', ''], self::$results[$cache_key]);
		}

		// Cache the output if it took some time...
		if (!empty(CacheApi::$enable) && microtime(true) - $cache_t > pow(50, -CacheApi::$enable)) {
			CacheApi::put($cache_key, self::$results[$cache_key], 240);
		}

		return self::$results[$cache_key];
	}

	/**
	 * Get the list of supported BBCodes, including any added by modifications.
	 *
	 * @return array List of supported BBCodes.
	 */
	public static function getBBCodes(): array
	{
		return BBcodeParser::getCodes();
	}

	/**
	 * Returns an array of BBCodes tags that are allowed in signatures.
	 *
	 * @return array An array containing allowed tags for signatures, or an
	 *    empty array if all tags are allowed.
	 */
	public static function getSigTags(): array
	{
		return BBcodeParser::getSigTags();
	}

	/**
	 * Gets a regular expression to match all known BBC tags.
	 *
	 * @return string Regular expression to match all BBCode tags.
	 */
	public static function getBBCodeTagsRegex(): string
	{
		return BBcodeParser::load()->getAllTagsRegex();
	}

	/**
	 * Highlight any code.
	 *
	 * Uses PHP's highlight_string() to highlight PHP syntax.
	 * Does special handling to keep the tabs in the code available.
	 * Used to parse PHP code from inside [code] and [php] tags.
	 *
	 * @param string $code The code.
	 * @return string The code with highlighted HTML.
	 */
	public static function highlightPhpCode(string $code): string
	{
		// Remove special characters.
		$code = Utils::htmlspecialcharsDecode(strtr($code, ['<br />' => "\n", '<br>' => "\n", "\t" => Utils::TAB_SUBSTITUTE, '&#91;' => '[']));

		$oldlevel = error_reporting(0);

		$buffer = @highlight_string($code, true);

		error_reporting($oldlevel);

		return preg_replace_callback_array(
			[
				'~(?:' . Utils::TAB_SUBSTITUTE . ')+~u' => fn($matches) => '<span style="white-space: pre;">' . strtr($matches[0], [Utils::TAB_SUBSTITUTE => "\t"]) . '</span>',
				'~<span style="color: #[0-9a-fA-F]{6}">(<span style="white-space: pre;">\h*</span>)</span>~' => fn($matches) => $matches[1],
				'~\R~' => fn($matches) => '<br />',
				'/\'/' => fn($matches) => '&#039;',
				// PHP 8.3 changed the returned HTML.
				'/^(<pre>)?<code[^>]*>|<\/code>(<\/pre>)?$/' => fn($matches) => '',
			],
			$buffer,
		);
	}

	/**
	 * Cleans and repairs BBCode in a string of user input.
	 *
	 * Formerly known as preparsecode().
	 *
	 * @param string $message The message.
	 * @param bool $previewing Whether we're previewing. Default: false.
	 * @param bool $autolink Whether to autolink plain-text URLs. Default: false.
	 * @return string Cleaned version of $message.
	 */
	public static function sanitize(string $message, bool $previewing = false, bool $autolink = false): string
	{
		static $tags_regex, $disallowed_tags_regex;

		// Convert control characters (except \t, \r, and \n) to harmless Unicode symbols
		$message = strtr(
			$message,
			[
				"\x00" => '&#x2400;', "\x01" => '&#x2401;', "\x02" => '&#x2402;',
				"\x03" => '&#x2403;', "\x04" => '&#x2404;', "\x05" => '&#x2405;',
				"\x06" => '&#x2406;', "\x07" => '&#x2407;', "\x08" => '&#x2408;',
				"\x0b" => '&#x240b;', "\x0c" => '&#x240c;', "\x0e" => '&#x240e;',
				"\x0f" => '&#x240f;', "\x10" => '&#x2410;', "\x11" => '&#x2411;',
				"\x12" => '&#x2412;', "\x13" => '&#x2413;', "\x14" => '&#x2414;',
				"\x15" => '&#x2415;', "\x16" => '&#x2416;', "\x17" => '&#x2417;',
				"\x18" => '&#x2418;', "\x19" => '&#x2419;', "\x1a" => '&#x241a;',
				"\x1b" => '&#x241b;', "\x1c" => '&#x241c;', "\x1d" => '&#x241d;',
				"\x1e" => '&#x241e;', "\x1f" => '&#x241f;',
			],
		);

		// Normalize Unicode characters for storage efficiency, better searching, etc.
		$message = Utils::normalize($message);

		// Clean out any other funky stuff.
		$message = Utils::sanitizeChars($message, 0);

		// Clean up after nobbc ;).
		$message = preg_replace_callback(
			'~\[nobbc[^\]]*\](.+?)\[/nobbc\]~is',
			fn($matches) => '[nobbc]' . strtr($matches[1], ['[' => '&#91;', ']' => '&#93;', ':' => '&#58;', '@' => '&#64;']) . '[/nobbc]',
			$message,
		);

		// Remove \r's... they're evil!
		$message = strtr($message, ["\r\n" => "\n", "\r" => "\n"]);

		// You won't believe this - but too many periods upsets apache it seems!
		// @todo You're right, I don't believe this. Verify or remove.
		$message = preg_replace('~\.{100,}~', '...', $message);

		// Trim off trailing quotes - these often happen by accident.
		while (str_ends_with($message, '[quote]')) {
			$message = substr($message, 0, -7);
		}

		while (str_starts_with($message, '[/quote]')) {
			$message = substr($message, 8);
		}

		if (str_contains($message, '[cowsay') && !User::$me->allowedTo('bbc_cowsay')) {
			$message = preg_replace('~\[(/?)cowsay[^\]]*\]~iu', '[$1pre]', $message);
		}

		// Find all code blocks, work out whether we'd be parsing them, then ensure they are all closed.
		$in_tag = false;
		$had_tag = false;
		$codeopen = 0;

		if (preg_match_all('~(\[(/)*code(?:=[^\]]+)?\])~is', $message, $matches)) {
			foreach ($matches[0] as $index => $dummy) {
				// Closing?
				if (!empty($matches[2][$index])) {
					// If it's closing and we're not in a tag we need to open it...
					if (!$in_tag) {
						$codeopen = true;
					}

					// Either way we ain't in one any more.
					$in_tag = false;
				}
				// Opening tag...
				else {
					$had_tag = true;

					// If we're in a tag don't do nought!
					if (!$in_tag) {
						$in_tag = true;
					}
				}
			}
		}

		// If we have an open tag, close it.
		if ($in_tag) {
			$message .= '[/code]';
		}

		// Open any ones that need to be open, only if we've never had a tag.
		if ($codeopen && !$had_tag) {
			$message = '[code]' . $message;
		}

		// Replace code BBC with placeholders. We'll restore them at the end.
		$parts = preg_split('/(\[code(?:=[^\]]+)?\](?:[^\[]|\[(?!\/code\])|(?R))*\[\/code])/i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $n = \count($parts); $i < $n; $i++) {
			if ($i % 2 == 1) {
				$substitute = md5($parts[$i]);
				$code_tags[$substitute] = $parts[$i];
				$parts[$i] = $substitute;
			}
		}

		$message = implode('', $parts);

		// Autolink any plain-text URLs.
		if (!empty($autolink)) {
			$message = Autolinker::load()->makeLinks($message);
		}

		// Now let's fix the img and url tags.
		$message = Autolinker::load()->fixUrlsInBBC($message);
		$message = self::fixTags($message);

		// Replace /me.+?\n with [me=name]dsf[/me]\n.
		if (
			str_contains(User::$me->name, '[')
			|| str_contains(User::$me->name, ']')
			|| str_contains(User::$me->name, '\'')
			|| str_contains(User::$me->name, '"')
		) {
			$message = preg_replace(
				'~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i',
				'$1[me=&quot;' . User::$me->name . '&quot;]$2[/me]',
				$message,
			);
		} else {
			$message = preg_replace(
				'~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i',
				'$1[me=' . User::$me->name . ']$2[/me]',
				$message,
			);
		}

		// Clean up the [html] tags and content.
		if (!$previewing && str_contains($message, '[html]')) {
			if (User::$me->allowedTo('bbc_html')) {
				$message = preg_replace_callback(
					'~\[html\](.+?)\[/html\]~is',
					fn($matches) => '[html]' . strtr(Utils::htmlspecialcharsDecode($matches[1]), ["\n" => '&#13;', '  ' => ' &#32;', '[' => '&#91;', ']' => '&#93;']) . '[/html]',
					$message,
				);
			}
			// We should edit them out, or else if an admin edits the message they will get shown...
			else {
				while (str_contains($message, '[html]')) {
					$message = preg_replace('~\[/?html\]~i', '', $message);
				}
			}
		}

		// Let's look at the time tags...
		$message = preg_replace_callback(
			'~\[time(?:=([^\\]]*))?\](.+?)\[/time\]~i',
			function ($matches) {
				return preg_replace(
					[
						'~^<time[^>]*\bdatetime="([^"]+)"[^>]*>(.*)</time>$~',
						'~^<span[^>]*>.*</span>$~',
					],
					[
						// If it parsed successfully, insert the resolved datetime value.
						// This ensures that "[time]today[/time]" ends up resolving to
						// the date the post was written, not the date it is being read.
						'[time=$1]$2[/time]',
						// If it didn't parse successfully, remove the BBC entirely.
						$matches[2],
					],
					self::transform($matches[0], self::INPUT_BBC),
				);
			},
			$message,
		);

		// Change the color specific tags to [color=the color].
		// First do the opening tags.
		$message = preg_replace('~\[(black|blue|green|red|white)\]~', '[color=$1]', $message);

		// And now do the closing tags
		$message = preg_replace('~\[/(black|blue|green|red|white)\]~', '[/color]', $message);

		// Neutralize any BBC tags this member isn't permitted to use.
		if (empty($disallowed_tags_regex)) {
			// Legacy BBC are only retained for historical reasons.
			// They're not for use in new posts.
			$disallowed_bbc = Utils::$context['legacy_bbc'];

			// Some BBC require permissions.
			foreach (Utils::$context['restricted_bbc'] as $bbc) {
				// Skip html, since we handled it separately above.
				if ($bbc === 'html') {
					continue;
				}

				if (!User::$me->allowedTo('bbc_' . $bbc)) {
					$disallowed_bbc[] = $bbc;
				}
			}

			$disallowed_tags_regex = Utils::buildRegex(array_unique($disallowed_bbc), '~');
		}

		if (!empty($disallowed_tags_regex)) {
			$message = preg_replace('~\[(?=/?' . $disallowed_tags_regex . '\b)~i', '&#91;', $message);
		}

		// Make sure all tags are lowercase.
		$message = preg_replace_callback(
			'~\[(/?)(list|li|table|tr|td)\b([^\]]*)\]~i',
			fn($m) => '[' . $m[1] . strtolower($m[2]) . $m[3] . ']',
			$message,
		);

		$list_open = substr_count($message, '[list]') + substr_count($message, '[list ');
		$list_close = substr_count($message, '[/list]');

		if ($list_close - $list_open > 0) {
			$message = str_repeat('[list]', $list_close - $list_open) . $message;
		}

		if ($list_open - $list_close > 0) {
			$message = $message . str_repeat('[/list]', $list_open - $list_close);
		}

		$mistake_fixes = [
			// Find [table]s not followed by [tr].
			'~\[table\](?![\s\x{A0}]*\[tr\])~su' => '[table][tr]',
			// Find [tr]s not followed by [td].
			'~\[tr\](?![\s\x{A0}]*\[td\])~su' => '[tr][td]',
			// Find [/td]s not followed by something valid.
			'~\[/td\](?![\s\x{A0}]*(?:\[td\]|\[/tr\]|\[/table\]))~su' => '[/td][/tr]',
			// Find [/tr]s not followed by something valid.
			'~\[/tr\](?![\s\x{A0}]*(?:\[tr\]|\[/table\]))~su' => '[/tr][/table]',
			// Find [/td]s incorrectly followed by [/table].
			'~\[/td\][\s\x{A0}]*\[/table\]~su' => '[/td][/tr][/table]',
			// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
			'~\[(table|tr|/td)\]([\s\x{A0}]*)\[td\]~su' => '[$1]$2[_td_]',
			// Now, any [td]s left should have a [tr] before them.
			'~\[td\]~s' => '[tr][td]',
			// Look for [tr]s which are correctly placed.
			'~\[(table|/tr)\]([\s\x{A0}]*)\[tr\]~su' => '[$1]$2[_tr_]',
			// Any remaining [tr]s should have a [table] before them.
			'~\[tr\]~s' => '[table][tr]',
			// Look for [/td]s followed by [/tr].
			'~\[/td\]([\s\x{A0}]*)\[/tr\]~su' => '[/td]$1[_/tr_]',
			// Any remaining [/tr]s should have a [/td].
			'~\[/tr\]~s' => '[/td][/tr]',
			// Look for properly opened [li]s which aren't closed.
			'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
			'~\[li\]([^\[\]]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
			'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
			// Lists - find correctly closed items/lists.
			'~\[/li\]([\s\x{A0}]*)\[/list\]~su' => '[_/li_]$1[/list]',
			// Find list items closed and then opened.
			'~\[/li\]([\s\x{A0}]*)\[li\]~su' => '[_/li_]$1[_li_]',
			// Now, find any [list]s or [/li]s followed by [li].
			'~\[(list(?: [^\]]*?)?|/li)\]([\s\x{A0}]*)\[li\]~su' => '[$1]$2[_li_]',
			// Allow for sub lists.
			'~\[/li\]([\s\x{A0}]*)\[list\]~u' => '[_/li_]$1[list]',
			'~\[/list\]([\s\x{A0}]*)\[li\]~u' => '[/list]$1[_li_]',
			// Any remaining [li]s weren't inside a [list].
			'~\[li\]~' => '[list][li]',
			// Any remaining [/li]s weren't before a [/list].
			'~\[/li\]~' => '[/li][/list]',
			// Put the correct ones back how we found them.
			'~\[_(li|/li|td|tr|/tr)_\]~' => '[$1]',
			// Images with no real url.
			'~\[img\]https?://.{0,7}\[/img\]~' => '',
		];

		// Fix up some use of tables without [tr]s, etc. (it has to be done more than once to catch it all.)
		for ($j = 0; $j < 3; $j++) {
			$message = preg_replace(array_keys($mistake_fixes), $mistake_fixes, $message);
		}

		// Remove empty bbc from the sections outside the code tags
		if (empty($tags_regex)) {
			$allowed_empty = ['anchor', 'td'];

			$tags = [];

			foreach (self::getBBCodes() as $code) {
				if (!\in_array($code['tag'], $allowed_empty)) {
					$tags[] = $code['tag'];
				}
			}

			$tags_regex = Utils::buildRegex($tags, '~');
		}

		while (preg_match('~\[(' . $tags_regex . ')\b[^\]]*\]\s*\[/\1\]\s?~i', $message)) {
			$message = preg_replace('~\[(' . $tags_regex . ')[^\]]*\]\s*\[/\1\]\s?~i', '', $message);
		}

		// Restore code blocks
		if (!empty($code_tags)) {
			$message = str_replace(array_keys($code_tags), array_values($code_tags), $message);
		}

		// Restore white space entities
		if (!$previewing) {
			$message = strtr($message, ['  ' => '&nbsp; ', "\n" => '<br>', "\u{A0}" => '&nbsp;']);
		} else {
			$message = strtr($message, ['  ' => '&nbsp; ', "\u{A0}" => '&nbsp;']);
		}

		// Now let's quickly clean up things that will slow our parser (which are common in posted code.)
		$message = strtr($message, ['[]' => '&#91;]', '[&#039;' => '&#91;&#039;']);

		// Any hooks want to work here?
		IntegrationHook::call('integrate_preparsecode', [&$message, $previewing]);

		return $message;
	}

	/**
	 * Given a string that has previously been cleaned by Parser::sanitize(),
	 * this returns a version suitable for editing in an HTML form.
	 *
	 * This mostly involves changing preserved white-space characters back into
	 * raw characters, making the contents of [html]...[/html] editable, and a
	 * few other minor things.
	 *
	 * Formerly known as un_preparsecode().
	 *
	 * @param string $message The message.
	 * @return string A string suitable for editing in an HTML form.
	 */
	public static function getEditableString(string $message): string
	{
		// Any hooks want to work here?
		IntegrationHook::call('integrate_unpreparsecode', [&$message]);

		// We're going to unparse only the stuff outside [code]...
		$parts = preg_split('/(\[code(?:=[^\]]+)?\](?:[^\[]|\[(?!\/code\])|(?R))*\[\/code])/i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $n = \count($parts); $i < $n; $i++) {
			if ($i % 2 == 1) {
				$substitute = md5($parts[$i]);
				$code_tags[$substitute] = $parts[$i];
				$parts[$i] = $substitute;
			}
		}

		$message = implode('', $parts);

		$message = preg_replace_callback(
			'~\[html\](.+?)\[/html\]~i',
			function ($matches) {
				return '[html]' . strtr(Utils::htmlspecialchars($matches[1], ENT_QUOTES), ['\\&quot;' => '&quot;', '&amp;#13;' => '<br>', '&amp;#32;' => ' ', '&amp;#91;' => '[', '&amp;#93;' => ']']) . '[/html]';
			},
			$message,
		);

		if (str_contains($message, '[cowsay') && !User::$me->allowedTo('bbc_cowsay')) {
			$message = preg_replace('~\[(/?)cowsay[^\]]*\]~iu', '[$1pre]', $message);
		}

		// Attempt to un-parse the time to something less awful.
		// This form will never be created by Parser::sanitize() in SMF 3.0+
		// but it might be present in old data.
		$message = preg_replace_callback(
			'~\[time\](\d{0,10})\[/time\]~i',
			function ($matches) {
				$time = Time::create('@' . $matches[1]);

				return '[time=' . $time->format('Y-m-d\TH:i:sP') . ']' . $time->format(null, false) . '[/time]';
			},
			$message,
		);

		if (!empty($code_tags)) {
			$message = strtr($message, $code_tags);
		}

		// Change breaks back to \n's and &nsbp; back to spaces.
		return preg_replace('~<br\s*/?' . '>~', "\n", str_replace('&nbsp;', ' ', $message));
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Checks whether the server's load average is too high to parse BBCode/Markdown.
	 *
	 * @return bool Whether the load average is too high.
	 */
	protected function highLoadAverage(): bool
	{
		return Sapi::isOverloaded(Config::$modSettings['bbc'] ?? null);
	}

	/**
	 * Sets $this->disabled.
	 */
	protected function setDisabled(): void
	{
		$this->disabled = [];

		if (!empty(Config::$modSettings['disabledBBC'])) {
			$temp = explode(',', strtolower(Config::$modSettings['disabledBBC']));

			foreach ($temp as $tag) {
				$this->disabled[trim($tag)] = true;
			}

			if (\in_array('color', $this->disabled)) {
				$this->disabled = array_merge(
					$this->disabled,
					[
						'black' => true,
						'white' => true,
						'red' => true,
						'green' => true,
						'blue' => true,
					],
				);
			}
		}

		if (!empty($this->parse_tags)) {
			if (!\in_array('email', $this->parse_tags)) {
				$this->disabled['email'] = true;
			}

			if (!\in_array('url', $this->parse_tags)) {
				$this->disabled['url'] = true;
			}

			if (!\in_array('iurl', $this->parse_tags)) {
				$this->disabled['iurl'] = true;
			}
		}

		if ($this->for_print) {
			// [glow], [shadow], and [move] can't really be printed.
			$this->disabled['glow'] = true;
			$this->disabled['shadow'] = true;
			$this->disabled['move'] = true;

			// Colors can't well be displayed... supposed to be black and white.
			$this->disabled['color'] = true;
			$this->disabled['black'] = true;
			$this->disabled['blue'] = true;
			$this->disabled['white'] = true;
			$this->disabled['red'] = true;
			$this->disabled['green'] = true;
			$this->disabled['me'] = true;

			// Color coding doesn't make sense.
			$this->disabled['php'] = true;

			// Links are useless on paper... just show the link.
			$this->disabled['ftp'] = true;
			$this->disabled['url'] = true;
			$this->disabled['iurl'] = true;
			$this->disabled['email'] = true;
			$this->disabled['flash'] = true;
			$this->disabled['youtube'] = true;

			// @todo Change maybe?
			if (!isset($_GET['images'])) {
				$this->disabled['img'] = true;
				$this->disabled['attach'] = true;
			}

			// Maybe some custom BBC need to be disabled for printing.
			IntegrationHook::call('integrate_bbc_print', [&$this->disabled]);
		}
	}

	/**
	 * Adjusts a BBCode definition so that it outputs its disabled version.
	 *
	 * @param BBCode $code_def A BBCode definition.
	 * @return BBCode The disabled version of the BBCode definition.
	 */
	protected function disableCode(BBCode $code_def): BBCode
	{
		$code = clone $code_def;

		if (
			!isset($code->disabled_before)
			&& !isset($code->disabled_after)
			&& !isset($code->disabled_content)
		) {
			$code->before = !empty($code->block_level) ? '<div>' : '';
			$code->after = !empty($code->block_level) ? '</div>' : '';
			$code->content = isset($code->type) && $code->type == BBCode::TYPE_CLOSED ? '' : (!empty($code->block_level) ? '<div>$1</div>' : '$1');
		} elseif (
			isset($code->disabled_before)
			|| isset($code->disabled_after)
		) {
			$code->before = $code->disabled_before ?? (!empty($code->block_level) ? '<div>' : '');
			$code->after = $code->disabled_after ?? (!empty($code->block_level) ? '</div>' : '');
		} else {
			$code->content = $code->disabled_content;
		}

		return $code;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Sets the values of this class's static variables.
	 *
	 * If a variable already has a value, the existing value is not changed.
	 * This ensures that custom values set by external code are respected.
	 */
	protected static function setStaticVars(): void
	{
		// Is anything disabled?
		self::$enable_bbc = self::$enable_bbc ?? !empty(Config::$modSettings['enableBBC']);
		self::$enable_post_html = self::$enable_post_html ?? !empty(Config::$modSettings['enablePostHTML']);
		self::$enable_markdown = self::$enable_markdown ?? !empty(Config::$modSettings['enableMarkdown']);
		self::$custom_smileys_enabled = self::$custom_smileys_enabled ?? !empty(Config::$modSettings['smiley_enable']);

		// Set up localization.
		if (!isset(User::$me)) {
			User::setMe(0);
		}

		self::$time_offset = self::$time_offset ?? User::$me->time_offset ?? 0;
		self::$time_format = self::$time_format ?? User::$me->time_format ?? Time::getTimeFormat();

		self::$locale = self::$locale ?? Lang::getTxt('lang_locale', file: 'General') ?? '';

		// Smiley settings.
		self::$custom_smileys_enabled = self::$custom_smileys_enabled ?? !empty(Config::$modSettings['smiley_enable']);
		self::$smileys_url = self::$smileys_url ?? Config::$modSettings['smileys_url'];
		self::$smiley_set = self::$smiley_set ?? (!empty(User::$me->smiley_set) ? User::$me->smiley_set : (!empty(Config::$modSettings['smiley_sets_default']) ? Config::$modSettings['smiley_sets_default'] : 'none'));

		// Give mods a chance to make any changes they need.
		IntegrationHook::call('integrate_parser_static_vars');
	}

	/**
	 * Fills in any missing elements of $options with the default values.
	 *
	 * @param array $options An array of parser options.
	 * @return array An updated copy of $options.
	 */
	protected static function setOptions(array $options): array
	{
		IntegrationHook::call('integrate_parser_options', [&$options]);

		return array_merge(self::$defalt_options, $options);
	}

	/**
	 * Transforms the input string into HTML.
	 *
	 * @param string $string The string in which to transform markup.
	 * @param int $input_types Bitmask of this class's INPUT_* constants.
	 *    Only the indicated types of markup will be parsed in the input string.
	 * @param array $options An array of parser options.
	 * @return string The transformed string.
	 */
	protected static function toHTML(string $string, int $input_types, array $options): string
	{
		// Allow mods access before parsing.
		$smileys = !empty($input_types & self::INPUT_SMILEYS);

		IntegrationHook::call('integrate_pre_parsebbc', [&$string, &$smileys, &$options['cache_id'], &$options['parse_tags']]);

		$input_types = $input_types | ($smileys ? self::INPUT_SMILEYS : 0);

		// Parse the BBCode.
		if ($input_types & self::INPUT_BBC) {
			$string = BBcodeParser::load(!empty($options['for_print']))->parse($string, !empty($input_types & self::INPUT_SMILEYS), $options['cache_id'], $options['parse_tags']);

			// BBCodeParser calls the SmileyParser internally; don't repeat.
			$input_types &= ~self::INPUT_SMILEYS;
		}

		// Parse the smileys, if we haven't already.
		if ($input_types & self::INPUT_SMILEYS) {
			$string = SmileyParser::load()->parse($string);
		}

		// Parse the Markdown.
		if ($input_types & self::INPUT_MARKDOWN) {
			$string = MarkdownParser::load(self::OUTPUT_HTML)->parse($string, true, $options);
		}

		// Allow mods access to the parsed value.
		IntegrationHook::call('integrate_post_parsebbc', [&$string, $smileys, $options['cache_id'], $options['parse_tags']]);

		return $string;
	}

	/**
	 * Transforms the input string into plain text (i.e. removes all markup).
	 *
	 * @param string $string The string in which to remove markup.
	 * @param int $input_types Bitmask of this class's INPUT_* constants.
	 *    Only the indicated types of markup will be parsed in the input string.
	 * @param array $options An array of parser options.
	 * @return string The transformed string.
	 */
	protected static function toText(string $string, int $input_types, array $options): string
	{
		// When transforming Markdown to plain text, the best results are
		// obtained by transforming it into BBC as an intermediate stage.
		if ($input_types & self::INPUT_MARKDOWN) {
			$string = MarkdownParser::load(self::OUTPUT_BBC)->parse($string, false, $options);
			$input_types &= ~self::INPUT_MARKDOWN;
		}

		// Transform smiley images into smiley text.
		if ($input_types & self::INPUT_SMILEYS) {
			$string = SmileyParser::load()->unparse($string);
			$input_types &= ~self::INPUT_SMILEYS;
		}

		// Ironically enough, the next step is to transform the BBC into HTML.
		$string = self::toHTML($string, $input_types, $options);

		// Do we have any replacements to make?
		if (!empty($options['preg_replace'])) {
			$string = preg_replace_callback_array($options['preg_replace'], $string);
		}

		if (!empty($options['str_replace'])) {
			$string = strtr($string, $options['str_replace']);
		}

		// Strip out the HTML tags and return the result.
		return strip_tags($string);
	}

	/**
	 * Transforms the input string into BBCode.
	 *
	 * - Markdown is transformed to the equivalent BBCode.
	 * - HTML img tags for smileys are transformed to smiley text.
	 * - Other HTML is transformed to the equivalent BBCode where possible.
	 * - HTML tags that cannot be transformed are removed.
	 *
	 * @param string $string The string in which to remove markup.
	 * @param int $input_types Bitmask of this class's INPUT_* constants.
	 *    Only the indicated types of markup will be parsed in the input string.
	 * @param array $options An array of parser options.
	 * @return string The transformed string.
	 */
	protected static function toBBC(string $string, int $input_types, array $options): string
	{
		if ($input_types & self::INPUT_MARKDOWN) {
			$string = MarkdownParser::load(self::OUTPUT_BBC)->parse($string, false, $options);
		}

		if ($input_types & self::INPUT_SMILEYS) {
			$string = SmileyParser::load()->unparse($string);
		}

		$string = BBcodeParser::load()->unparse($string);

		return $string;
	}

	/**
	 * Generates a unique cache key for the combination of string, parameters,
	 * settings, etc., that apply to this particular call to self::transform().
	 *
	 * @param string $string The string in which to transform markup.
	 * @param int $input_types Bitmask of this class's INPUT_* constants.
	 * @param int $output_type One of this class's INPUT_* constants.
	 * @param array $options An array of parser options.
	 * @return string A unique cache key.
	 */
	protected static function getCacheKey(string $string, int $input_types, int $output_type, array $options): string
	{
		// Allow mods to add stuff to $cache_key_extras.
		$cache_key_extras = [];

		IntegrationHook::call('integrate_parser_cache', [&$cache_key_extras, $input_types, $output_type, $options]);

		// If no cache id was given, make a generic one.
		$cache_id = \strval($options['cache_id'] ?? '') !== '' ? $options['cache_id'] : 'str' . substr(md5($string), 0, 7);

		// Use a unique identifier key for this combination of string and settings.
		return 'parse:' . $cache_id . '-' . md5(json_encode([
			$string,
			$input_types,
			$output_type,
			$options,
			// Localization settings.
			self::$locale,
			self::$time_offset,
			self::$time_format,
			// BBCode settings.
			self::getBBCodes(),
			Config::$modSettings['disabledBBC'] ?? '',
			self::$enable_post_html,
			// Smiley settings.
			SmileyParser::loadData(self::$smiley_set),
			// Additional stuff that might affect output.
			$cache_key_extras,
		]));
	}

	/**
	 * Helper for Parser::sanitize() that fixes URIs and BBCode markup
	 * in various BBCode tags.
	 *
	 * Ensures URIs are valid, ensures BBCode markup is well-formed, and ensures
	 * certain security problems are prevented.
	 *
	 * @param string $message The message.
	 * @return string Fixed version of $message.
	 */
	protected static function fixTags(string $message): string
	{
		// WARNING: Editing the below can cause large security holes in your forum.
		// Edit only if you are sure you know what you are doing.

		$fixes = [
			// [img]http://...[/img] or [img width=1]http://...[/img]
			[
				'tag' => 'img',
				'protocols' => ['http', 'https'],
				'embedded_url' => false,
				'has_equal_sign' => false,
				'has_extra' => true,
			],
			// [url]http://...[/url]
			[
				'tag' => 'url',
				'protocols' => ['http', 'https'],
				'embedded_url' => false,
				'has_equal_sign' => false,
				'has_extra' => false,
			],
			// [url=http://...]name[/url]
			[
				'tag' => 'url',
				'protocols' => ['http', 'https'],
				'embedded_url' => true,
				'has_equal_sign' => true,
				'has_extra' => false,
			],
			// [iurl]http://...[/iurl]
			[
				'tag' => 'iurl',
				'protocols' => ['http', 'https'],
				'embedded_url' => false,
				'has_equal_sign' => false,
				'has_extra' => false,
			],
			// [iurl=http://...]name[/iurl]
			[
				'tag' => 'iurl',
				'protocols' => ['http', 'https'],
				'embedded_url' => true,
				'has_equal_sign' => true,
				'has_extra' => false,
			],
			// The rest of these are deprecated.
			// [ftp]ftp://...[/ftp]
			[
				'tag' => 'ftp',
				'protocols' => ['ftp', 'ftps', 'sftp'],
				'embedded_url' => false,
				'has_equal_sign' => false,
				'has_extra' => false,
			],
			// [ftp=ftp://...]name[/ftp]
			[
				'tag' => 'ftp',
				'protocols' => ['ftp', 'ftps', 'sftp'],
				'embedded_url' => true,
				'has_equal_sign' => true,
				'has_extra' => false,
			],
			// [flash]http://...[/flash]
			[
				'tag' => 'flash',
				'protocols' => ['http', 'https'],
				'embedded_url' => false,
				'has_equal_sign' => false,
				'has_extra' => true,
			],
		];

		// Fix each type of tag.
		foreach ($fixes as $fix_info) {
			$message = self::fixTag($message, ...$fix_info);
		}

		// Now fix possible security problems with images loading links automatically...
		$message = preg_replace_callback(
			'~(\[img.*?\])(.+?)\[/img\]~is',
			function ($m) {
				return $m[1] . preg_replace('~action(=|%3d)(?!dlattach)~i', 'action-', $m[2]) . '[/img]';
			},
			$message,
		);

		return $message;
	}

	/**
	 * Helper for Parser::fixTags() that fixes URIs and BBCode markup for a
	 * specific type of BBCode tag.
	 *
	 * Ensures URIs are valid, ensures BBCode markup is well-formed, and ensures
	 * certain security problems are prevented.
	 *
	 * @param string $message The message
	 * @param string $tag The tag
	 * @param array $protocols The protocols
	 * @param bool $embedded_url Whether it *can* be set to something
	 * @param bool $has_equal_sign Whether it *is* set to something
	 * @param bool $has_extra Whether it can have extra cruft after the begin tag.
	 * @return string Fixed version of $message.
	 */
	protected static function fixTag(string $message, string $tag, array $protocols, bool $embedded_url, bool $has_equal_sign, bool $has_extra): string
	{
		$forbidden_protocols = [
			// Poses security risks.
			'javascript',
			// Allows file data to be embedded, bypassing our attachment system.
			'data',
		];

		if (preg_match('~^([^:]+://[^/]+)~', Config::$boardurl, $match) != 0) {
			$domain_url = $match[1];
		} else {
			$domain_url = Config::$boardurl . '/';
		}

		$replaces = [];

		if ($has_equal_sign && $embedded_url) {
			$quoted = preg_match('~\[(' . $tag . ')=&quot;~', $message);

			preg_match_all('~\[(' . $tag . ')=' . ($quoted ? '&quot;(.*?)&quot;' : '([^\]]*?)') . '\](?:(.+?)\[/(' . $tag . ')\])?~is', $message, $matches);
		} elseif ($has_equal_sign) {
			preg_match_all('~\[(' . $tag . ')=([^\]]*?)\](?:(.+?)\[/(' . $tag . ')\])?~is', $message, $matches);
		} else {
			preg_match_all('~\[(' . $tag . ($has_extra ? '(?:[^\]]*?)' : '') . ')\](.+?)\[/(' . $tag . ')\]~is', $message, $matches);
		}

		foreach ($matches[0] as $k => $dummy) {
			// Remove all leading and trailing whitespace.
			$replace = trim($matches[2][$k]);
			$this_tag = $matches[1][$k];
			$this_close = $has_equal_sign ? (empty($matches[4][$k]) ? '' : $matches[4][$k]) : $matches[3][$k];

			$found = false;

			foreach ($protocols as $protocol) {
				$found = strncasecmp($replace, $protocol . '://', \strlen($protocol) + 3) === 0;

				if ($found) {
					break;
				}
			}

			$current_protocol = strtolower(Url::create($replace)->scheme ?? '');

			if (\in_array($current_protocol, $forbidden_protocols)) {
				$replace = 'about:invalid';
			} elseif (!$found && $protocols[0] == 'http') {
				// A path
				if (str_starts_with($replace, '/') && !str_starts_with($replace, '//')) {
					$replace = $domain_url . $replace;
				}
				// A query
				elseif (str_starts_with($replace, '?')) {
					$replace = Config::$scripturl . $replace;
				}
				// A fragment
				elseif (str_starts_with($replace, '#') && $embedded_url) {
					$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
					$this_tag = 'iurl';
					$this_close = 'iurl';
				} elseif (!str_starts_with($replace, '//') && empty($current_protocol)) {
					$replace = $protocols[0] . '://' . $replace;
				}
			} elseif (!$found && $protocols[0] == 'ftp') {
				$replace = $protocols[0] . '://' . preg_replace('~^(?!ftps?)[^:]+://~', '', $replace);
			} elseif (!$found && empty($current_protocol)) {
				$replace = $protocols[0] . '://' . $replace;
			}

			if ($has_equal_sign && $embedded_url) {
				$replaces[$matches[0][$k]] = '[' . $this_tag . '=&quot;' . $replace . '&quot;]' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
			} elseif ($has_equal_sign) {
				$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
			} elseif ($embedded_url) {
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']' . $matches[2][$k] . '[/' . $this_close . ']';
			} else {
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . ']' . $replace . '[/' . $this_close . ']';
			}
		}

		foreach ($replaces as $k => $v) {
			if ($k == $v) {
				unset($replaces[$k]);
			}
		}

		if (!empty($replaces)) {
			$message = strtr($message, $replaces);
		}

		return $message;
	}
}
