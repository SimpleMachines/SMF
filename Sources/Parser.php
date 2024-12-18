<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Parsers\BBcodeParser;
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
		'str_replace' => [],
		'preg_replace' => [],
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
	 * The character encoding of the strings to be parsed.
	 */
	public static string $encoding;

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

	/*****************
	 * Public methods.
	 *****************/

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
		if (!is_callable($handlers[$output_type] ?? null)) {
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

		$buffer = str_replace(["\n", "\r"], '', @highlight_string($code, true));

		error_reporting($oldlevel);

		$buffer = preg_replace_callback_array(
			[
				'~(?:' . Utils::TAB_SUBSTITUTE . ')+~u' => fn ($matches) => '<span style="white-space: pre-wrap;">' . strtr($matches[0], [Utils::TAB_SUBSTITUTE => "\t"]) . '</span>',
				'~<span style="color: #[0-9a-fA-F]{6}">(<span style="white-space: pre-wrap;">\h*</span>)</span>~' => fn ($matches) => $matches[1],
			],
			$buffer,
		);

		// PHP 8.3 changed the returned HTML.
		$buffer = preg_replace('/^(<pre>)?<code[^>]*>|<\/code>(<\/pre>)?$/', '', $buffer);

		return strtr($buffer, ['\'' => '&#039;']);
	}

	/**
	 * Microsoft uses their own character set Code Page 1252 (CP1252), which is
	 * a superset of ISO 8859-1, defining several characters between DEC 128 and
	 * 159 that are not normally displayable. This converts the popular ones
	 * that appear from a cut and paste from Windows.
	 *
	 * @todo In a Unicode-aware world, we probably should not do this any more.
	 *
	 * @param string $string The string.
	 * @return string The sanitized string.
	 */
	public static function sanitizeMSCutPaste(string $string): string
	{
		if (empty($string)) {
			return $string;
		}

		self::setStaticVars();

		// UTF-8 occurrences of MS special characters.
		$findchars_utf8 = [
			"\xe2\x80\x9a",	// single low-9 quotation mark, U+201A
			"\xe2\x80\x9e",	// double low-9 quotation mark, U+201E
			"\xe2\x80\xa6",	// horizontal ellipsis, U+2026
			"\xe2\x80\x98",	// left single curly quote, U+2018
			"\xe2\x80\x99",	// right single curly quote, U+2019
			"\xe2\x80\x9c",	// left double curly quote, U+201C
			"\xe2\x80\x9d",	// right double curly quote, U+201D
		];

		// windows 1252 / iso equivalents
		$findchars_iso = [
			chr(130),
			chr(132),
			chr(133),
			chr(145),
			chr(146),
			chr(147),
			chr(148),
		];

		// safe replacements
		$replacechars = [
			',',	// &sbquo;
			',,',	// &bdquo;
			'...',	// &hellip;
			"'",	// &lsquo;
			"'",	// &rsquo;
			'"',	// &ldquo;
			'"',	// &rdquo;
		];

		$string = str_replace(self::$encoding === 'UTF-8' ? $findchars_utf8 : $findchars_iso, $replacechars, $string);

		return $string;
	}

	/*******************
	 * Internal methods.
	 *******************/

	/**
	 * Checks whether the server's load average is too high to parse BBCode.
	 *
	 * @return bool Whether the load average is too high.
	 */
	protected function highLoadAverage(): bool
	{
		return !empty(Utils::$context['load_average']) && !empty(Config::$modSettings['bbc']) && Utils::$context['load_average'] >= Config::$modSettings['bbc'];
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

			if (in_array('color', $this->disabled)) {
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
			if (!in_array('email', $this->parse_tags)) {
				$this->disabled['email'] = true;
			}

			if (!in_array('url', $this->parse_tags)) {
				$this->disabled['url'] = true;
			}

			if (!in_array('iurl', $this->parse_tags)) {
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
	 * @param array $code A BBCode definition.
	 * @return array The disabled version of the BBCode definition.
	 */
	protected function disableCode(array $code): array
	{
		if (!isset($code['disabled_before']) && !isset($code['disabled_after']) && !isset($code['disabled_content'])) {
			$code['before'] = !empty($code['block_level']) ? '<div>' : '';
			$code['after'] = !empty($code['block_level']) ? '</div>' : '';
			$code['content'] = isset($code['type']) && $code['type'] == 'closed' ? '' : (!empty($code['block_level']) ? '<div>$1</div>' : '$1');
		} elseif (isset($code['disabled_before']) || isset($code['disabled_after'])) {
			$code['before'] = $code['disabled_before'] ?? (!empty($code['block_level']) ? '<div>' : '');
			$code['after'] = $code['disabled_after'] ?? (!empty($code['block_level']) ? '</div>' : '');
		} else {
			$code['content'] = $code['disabled_content'];
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

		self::$locale = self::$locale ?? Lang::$txt['lang_locale'] ?? '';
		self::$encoding = self::$encoding ?? (!empty(Utils::$context['utf8']) ? 'UTF-8' : (!empty(Config::$modSettings['global_character_set']) ? Config::$modSettings['global_character_set'] : (!empty(Lang::$txt['lang_character_set']) ? Lang::$txt['lang_character_set'] : 'UTF-8')));

		// Smiley settings.
		self::$custom_smileys_enabled = self::$custom_smileys_enabled ?? !empty(Config::$modSettings['smiley_enable']);
		self::$smileys_url = self::$smileys_url ?? Config::$modSettings['smileys_url'];
		self::$smiley_set = self::$smiley_set ?? (!empty(User::$me->smiley_set) ? User::$me->smiley_set : (!empty(Config::$modSettings['smiley_sets_default']) ? Config::$modSettings['smiley_sets_default'] : 'none'));
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
			$string = BBcodeParser::load(!empty($options['for_print']))->parse($string, $options['cache_id'], $options['parse_tags']);
		}

		// Parse the smileys.
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
		$cache_id = strval($options['cache_id'] ?? '') !== '' ? $options['cache_id'] : 'str' . substr(md5($string), 0, 7);

		// Use a unique identifier key for this combination of string and settings.
		return 'parse:' . $cache_id . '-' . md5(json_encode([
			$string,
			$input_types,
			$output_type,
			$options,
			// Localization settings.
			self::$encoding,
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
}

?>