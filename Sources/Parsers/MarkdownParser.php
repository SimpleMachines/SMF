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

declare(strict_types=1);

namespace SMF\Parsers;

use SMF\Autolinker;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Parser;
use SMF\Utils;

/**
 * Converts Markdown to BBCode or HTML.
 *
 * Follows the CommonMark specification, with the following changes:
 *
 * 1. Supports the GitHub Flavoured Markdown tables extension.
 *
 * 2. Supports the GitHub Flavoured Markdown strikethough extension.
 *
 * 3. Is more accurate (and thus restrictive) when identifying absolute URIs.
 *    For example, CommonMark will accept obviously invalid URIs like these:
 *
 *        a+b+c:d
 *        made-up-scheme://foo,bar
 *        http://../
 *
 *    SMF is capable of much more robust URI validation, so we use it.
 */
class MarkdownParser extends Parser
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var int
	 *
	 * Possible value for $this->output_type.
	 *
	 * Used to set the output to HTML rendered the same way that the reference
	 * implementation of CommonMark would.
	 */
	public const OUTPUT_HTML_STRICT = 3;

	/**
	 * @var int
	 *
	 * Possible value for $this->hard_breaks.
	 *
	 * Using this option, line breaks will be converted to <br> elements when
	 * the line breaks create blank lines. This can be used to preserve blank
	 * lines in the input while still parsing paragraph content normally.
	 */
	public const BR_LINES = 0b01;

	/**
	 * @var int
	 *
	 * Possible value for $this->hard_breaks.
	 *
	 * Using this option, line breaks will be converted to <br> elements inside
	 * paragraphs, etc.
	 */
	public const BR_IN_PARAGRAPHS = 0b10;

	/**
	 * @var array
	 *
	 * Characters that can be escaped with a backslash.
	 */
	public const ESCAPEABLE = [
		'!', '"', '#', '$', '%', '&', '\'', '\\',
		'(', ')', '*', '+', ',', '-', '.', '/',
		':', ';', '<', '=', '>', '?', '@', '|',
		'[', ']', '^', '_', '`', '{', '}', '~',
	];

	/**
	 * @var string
	 *
	 * Regex to match HTML tags, including opening tags, closing tags, comments,
	 * processing instructions, declarations, and CDATA sections. Matches both
	 * standard HTML5 tag names and custom tag names.
	 */
	public const REGEX_HTML_TAG =
		'(' .
			'(?P>opening_tag)' .
			'|' .
			'(?P>closing_tag)' .
			'|' .
			'(?P>comment)' .
			'|' .
			'(?P>processing_instruction)' .
			'|' .
			'(?P>declaration)' .
			'|' .
			'(?P>cdata)' .
		')' .
		'(?(DEFINE)' .
			'(?<tag_name>[a-zA-Z][a-zA-Z0-9\-]*)' .
			'(?<attribute_value_unquoted>[^\s"\'=<>`])' .
			'(?<attribute_value_single_quoted>\'[^\']*\')' .
			'(?<attribute_value_double_quoted>"[^"]*")' .
			'(?<attribute_value>(?P>attribute_value_unquoted)|(?P>attribute_value_single_quoted)|(?P>attribute_value_double_quoted))' .
			'(?<attribute_value_specification>\s*=\s*(?P>attribute_value))' .
			'(?<attribute_name>[a-zA-Z_:][a-zA-Z0-9_.:\-]*)' .
			'(?<attribute>\s+(?P>attribute_name)(?P>attribute_value_specification)?)' .
			'(?<opening_tag><(?P>tag_name)(?P>attribute)*\s*/?>)' .
			'(?<closing_tag></(?P>tag_name)\s*>)' .
			'(?<comment><!--(?!-?>)\X*?-->)' .
			'(?<processing_instruction><' . '\?\X*?\?' . '>)' .
			'(?<declaration><![A-Z]+\s+[^>]+>)' .
			'(?<cdata><!\[CDATA\[\X*?\]\]>)' .
		')';

	/**
	 * @var string
	 *
	 * Regular expression to match link text.
	 */
	public const REGEX_LINK_TEXT =
		'(?P<text>' .
			// Opening bracket.
			'\[' .
			// Any number of...
			'(?' . '>' .
				// characters that are...
				'(?' . '>' .
					// not square brackets...
					'[^\[\]]' .
					// or
					'|' .
					// escaped square brackets...
					'\\\\[\[\]]' .
				')' .
				// or
				'|' .
				// balanced square brackets.
				'(?P>text)' .
			')*' .
			// Closing bracket.
			'\]' .
		')';

	/**
	 * @var string
	 *
	 * Regular expression to match link labels.
	 */
	public const REGEX_LINK_LABEL =
		'(?P<label>' .
			// Opening bracket.
			'\[' .
			// Must contain at least one non-whitespace character.
			'(?=\s*\S)' .
			// Any number of other characters or escaped closing brackets.
			'(?:' .
				'\X(?!\])' .
				'|' .
				'\\\\(?=\])' .
			')*' .
			'\X?' .
			// Closing bracket.
			'\]' .
		')';

	/**
	 * @var string
	 *
	 * Regular expression to match link destinations.
	 */
	public const REGEX_LINK_DESTINATION =
		'(?P<destination>' .
			'(?:' .
				// Angle brackets that contain no vertical whitespace.
				'<[^\v>]*>' .
				// or
				'|' .
				// Non-space, non-control characters.
				'[^\s\p{Cc}]+?' .
			')' .
		')';

	/**
	 * @var string
	 *
	 * Regular expression to match link titles.
	 */
	public const REGEX_LINK_TITLE =
		'(?P<title>' .
			// Opening quotation mark.
			'(?P<quote>["\'])' .
			// Any number of other characters or escaped quotation marks.
			'(?:' .
				'\X(?!(?P>quote))' .
				'|' .
				'\\\\(?=(?P>quote))' .
			')*' .
			'\X?' .
			// Closing quotation mark.
			'(?P>quote)' .

			// or
			'|' .

			// Opening parenthesis.
			'\(' .
			// Any number of other characters or escaped closing parentheses.
			'(?:' .
				'\X(?!\))' .
				'|' .
				'\\\\(?=\))' .
			')*' .
			'\X?' .
			// Closing parenthesis.
			'\)' .
		')';

	/**
	 * @var string
	 *
	 * Regular expression to match link reference definitions.
	 */
	public const REGEX_LINK_REF_DEF =
		// Max indentation of three spaces.
		'^\h{0,3}' .
		// Link label.
		self::REGEX_LINK_LABEL .
		// Colon.
		':' .
		// Optional whitespace, with up to one line ending.
		'\h*\n?\h*' .
		// Link destination.
		self::REGEX_LINK_DESTINATION .
		// Optional link title.
		'(?:' .
			// Whitespace, with up to one line ending.
			'(?:\h+\n?|\n)\h*' .
			// Link title itself.
			self::REGEX_LINK_TITLE .
		')?' .
		// Trailing whitespace and end of line.
		'\h*(?:\n|$)';

	/**
	 * @var string
	 *
	 * Regular expression to match link reference definitions.
	 */
	public const REGEX_LINK_INLINE =
		// Link text.
		self::REGEX_LINK_TEXT .
		// Opening parenthesis.
		'\(' .
		// Optional whitespace.
		'\s*' .
		// Optional link destination.
		'(?:' .
			self::REGEX_LINK_DESTINATION .
		')?' .
		// Optional link title.
		'(?:' .
			'\s+' .
			self::REGEX_LINK_TITLE .
		')?' .
		// Optional whitespace.
		'\s*' .
		// Closing parenthesis.
		'\)';

	/**
	 * @var string
	 *
	 * Regular expression to match full link references.
	 */
	public const REGEX_LINK_REF_FULL = self::REGEX_LINK_TEXT . self::REGEX_LINK_LABEL;

	/**
	 * @var string
	 *
	 * Regular expression to match collapsed link references.
	 */
	public const REGEX_LINK_REF_COLLAPSED = self::REGEX_LINK_LABEL . '\[\]';

	/**
	 * @var string
	 *
	 * Regular expression to match shortcut link references.
	 */
	public const REGEX_LINK_REF_SHORTCUT = self::REGEX_LINK_LABEL . '(?!\[)';

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The type of output to generate.
	 *
	 * Value must be one of this class's OUTPUT_* constants.
	 */
	public int $output_type = self::OUTPUT_HTML;

	/**
	 * @var int
	 *
	 * How to render line breaks.
	 *
	 * Value should be a bitmask of this class's BR_* constants.
	 */
	public int $hard_breaks = 0;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Defines all the recognized block level element types.
	 *
	 * The order of items in this array matters. Don't change it.
	 */
	protected array $block_types = [
		'blank' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => false,
			'opener_test' => 'testIsBlank',
			'continue_test' => 'testIsBlank',
			'closer_test' => '!testIsBlank',
			'add' => null,
			'append' => 'appendBlank',
			'close' => null,
		],
		'root' => [
			'is_container' => true,
			'interrupts_p' => false,
			'marker_pattern' => false,
			'opener_test' => false,
			'continue_test' => false,
			'closer_test' => false,
			'add' => null,
			'append' => null,
			'close' => null,
		],
		'fenced_code' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^([`~]){3,}(?!\h+\1)/u',
			'opener_test' => 'testIsFencedCode',
			'continue_test' => true,
			'closer_test' => 'testIsCodeFence',
			'add' => 'addFencedCode',
			'append' => 'appendFencedCode',
			'close' => 'closeCodeBlock',
		],
		'blockquote' => [
			'is_container' => true,
			'interrupts_p' => true,
			'marker_pattern' => '/^>\h?/u',
			'opener_test' => 'testOpensQuote',
			'continue_test' => 'testOpensQuote',
			'closer_test' => 'testClosesQuote',
			'add' => 'addBlock',
			'append' => null,
			'close' => 'closeBlock',
		],
		'list' => [
			'is_container' => true,
			'interrupts_p' => true,
			'marker_pattern' => false,
			'opener_test' => false,
			'continue_test' => false,
			'closer_test' => false,
			'add' => null,
			'append' => null,
			'close' => null,
		],
		'list_item' => [
			'is_container' => true,
			'interrupts_p' => true,
			'marker_pattern' => '/^((?P<bullet>[*+-])|(?P<number>\d+)(?P<num_punct>[.)]))\h+/u',
			'opener_test' => 'testOpensListItem',
			'continue_test' => false,
			'closer_test' => 'testClosesListItem',
			'add' => 'addListItem',
			'append' => null,
			'close' => 'closeBlock',
		],
		'indented_code' => [
			'is_container' => false,
			'interrupts_p' => false,
			'marker_pattern' => '/^\h{4}/u',
			'opener_test' => 'testIsIndentedCode',
			'continue_test' => 'testIsIndentedCode',
			'closer_test' => '!testIsIndentedCode',
			'add' => 'addIndentedCode',
			'append' => 'appendIndentedCode',
			'close' => 'closeCodeBlock',
		],
		'hr' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^[*-_]/u',
			'opener_test' => 'testIsHr',
			'continue_test' => false,
			'closer_test' => true,
			'add' => 'addBlock',
			'append' => null,
			'close' => 'closeBlock',
		],
		'atx_heading' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^#{1,6}/u',
			'opener_test' => 'testIsAtxHeading',
			'continue_test' => false,
			'closer_test' => true,
			'add' => 'addAtxHeading',
			'append' => null,
			'close' => 'closeBlock',
		],
		'setext_heading' => [
			'is_container' => false,
			'interrupts_p' => false,
			'marker_pattern' => '/^[=-]/u',
			'opener_test' => 'testIsSetextHeading',
			'continue_test' => false,
			'closer_test' => true,
			'add' => 'addSetextHeading',
			'append' => null,
			'close' => 'closeBlock',
		],
		'html_1' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^<(script|pre|style)(?=\h|>|$)/ui',
			'opener_test' => 'testOpensHtml1',
			'continue_test' => '!testClosesHtml1',
			'closer_test' => 'testClosesHtml1',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeBlock',
		],
		'html_2' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^<!--/',
			'opener_test' => 'testOpensHtml2',
			'continue_test' => '!testClosesHtml2',
			'closer_test' => 'testClosesHtml2',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeBlock',
		],
		'html_3' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^<[?]/',
			'opener_test' => 'testOpensHtml3',
			'continue_test' => '!testClosesHtml3',
			'closer_test' => 'testClosesHtml3',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeBlock',
		],
		'html_4' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^<![A-Z]/',
			'opener_test' => 'testOpensHtml4',
			'continue_test' => '!testClosesHtml4',
			'closer_test' => 'testClosesHtml4',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeBlock',
		],
		'html_5' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => '/^<!\[CDATA\[/',
			'opener_test' => 'testOpensHtml5',
			'continue_test' => '!testClosesHtml5',
			'closer_test' => 'testClosesHtml5',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeBlock',
		],
		'html_6' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' =>
				'~' .
					// Start of line.
					'^' .
					// `<` or `</`
					'</?' .
					// A subset of possible HTML element names to match.
					// (Basically, block level elements plus some other stuff.)
					'(?' . '>iframe|ul|a(?' . '>ddress|rticle|side)|b(?' . '>lockquote|ase(?' . '>font|)|ody)|c(?' . '>aption|enter|ol(?' . '>group|))|d(?' . '>etails|d|i(?' . '>alog|r|v)|l|t)|f(?' . '>rame(?' . '>set|)|i(?' . '>eldset|g(?' . '>caption|ure))|o(?' . '>oter|rm))|h(?' . '>ead(?' . '>er|)|tml|1|2|3|4|5|6|r)|l(?' . '>egend|i(?' . '>nk|))|m(?' . '>ain|enu(?' . '>item|))|n(?' . '>oframes|av)|o(?' . '>pt(?' . '>group|ion)|l)|p(?' . '>aram|)|s(?' . '>ection|ummary|ource)|t(?' . '>able|body|foot|itle|d|h(?' . '>ead|)|r(?' . '>ack|)))' .
					// Followed by whitespace, `>`, `/>`, or the end of the line.
					'(?=\h|/?' . '>|$)' .
				'~ui',
			'opener_test' => 'testOpensHtml6',
			'continue_test' => '!testClosesHtml6',
			'closer_test' => 'testClosesHtml6',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeBlock',
		],
		'html_7' => [
			'is_container' => false,
			'interrupts_p' => false,
			'marker_pattern' => '/^<\/?(?!script|pre|style)[^>]+>(?=\h|$)/ui',
			'opener_test' => 'testOpensHtml7',
			'continue_test' => '!testClosesHtml7',
			'closer_test' => 'testClosesHtml7',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeBlock',
		],
		'table' => [
			'is_container' => false,
			'interrupts_p' => true,
			'marker_pattern' => false,
			'opener_test' => 'testIsTable',
			'continue_test' => 'testIsTable',
			'closer_test' => '!testIsTable',
			'add' => 'addTable',
			'append' => 'appendTableRow',
			'close' => 'closeTable',
		],
		'p' => [
			'is_container' => false,
			'interrupts_p' => false,
			'marker_pattern' => false,
			'opener_test' => 'testIsParagraph',
			'continue_test' => 'testIsParagraph',
			'closer_test' => '!testIsParagraph',
			'add' => 'addBlock',
			'append' => 'appendContent',
			'close' => 'closeParagraph',
		],
	];

	/**
	 * @var array
	 *
	 * Defines the methods used to render different element types.
	 *
	 * This info is separate from $this->block_types because it includes info
	 * about how to render both block elements and inline elements.
	 */
	protected array $render_methods = [
		'fenced_code' => 'renderCodeBlock',
		'indented_code' => 'renderCodeBlock',
		'blockquote' => 'renderBlockquote',
		'list' => 'renderList',
		'list_item' => 'renderListItem',
		'hr' => 'renderHr',
		'atx_heading' => 'renderHeading',
		'setext_heading' => 'renderHeading',
		'html_1' => 'renderHtmlBlock',
		'html_2' => 'renderHtmlBlock',
		'html_3' => 'renderHtmlBlock',
		'html_4' => 'renderHtmlBlock',
		'html_5' => 'renderHtmlBlock',
		'html_6' => 'renderHtmlBlock',
		'html_7' => 'renderHtmlBlock',
		'table' => 'renderTable',
		'td' => 'renderTableCell',
		'p' => 'renderParagraph',
		'html' => 'renderHtmlInline',
		'link' => 'renderLink',
		'image' => 'renderImage',
		'code' => 'renderInlineCode',
		'i' => 'renderEm',
		'b' => 'renderStrong',
		's' => 'renderStrikethrough',
	];

	/**
	 * @var array
	 *
	 * Information about the line that is currently being parsed.
	 */
	private array $line_info = [];

	/**
	 * @var array
	 *
	 * Holds the parsed document structure.
	 */
	private array $structure = [
		'type' => 'root',
		'open' => true,
		'properties' => [],
		'content' => [],
	];

	/**
	 * @var array
	 *
	 * Holds references to the elements of $this->structure that are currently
	 * open.
	 */
	private array $open = [];

	/**
	 * @var ?array
	 *
	 * A reference to the last block element of $this->structure.
	 */
	private ?array $last_block;

	/**
	 * @var array
	 *
	 * Holds any link reference definitions that are found in the document.
	 */
	private array $link_reference_definitions = [];

	/**
	 * @var int
	 *
	 * Tracks whether a code block is currently open.
	 */
	private int $in_code = 0;

	/**
	 * @var ?int
	 *
	 * For fenced code blocks, tracks the line number of the opening code fence.
	 */
	private ?int $opening_fence_linenum = null;

	/**
	 * @var string
	 *
	 * For fenced code blocks, the string of the opening code fence.
	 */
	private string $opening_fence = '';

	/**
	 * @var string
	 *
	 * For fenced code blocks, the info string that was given with the fence.
	 */
	private string $info_string = '';

	/**
	 * @var int
	 *
	 * Tracks whether an HTML block is currently open.
	 */
	private int $in_html = 0;

	/**
	 * @var array
	 *
	 * While in a table, holds the alignment settings for the table's columns.
	 */
	private array $table_align = [];

	/**
	 * @var array
	 *
	 * Holds placeholders for protected strings.
	 *
	 * Used to prevent parsing inside pre-existing HTML code blocks.
	 */
	private array $placeholders = [];

	/**
	 * @var string
	 *
	 * The rendered output.
	 */
	private string $rendered = '';

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * References to existing, reusable instances of this class.
	 */
	private static array $parsers = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $output_type The type of output to generate.
	 *    Value must be one of this class's or its parent class's OUTPUT_*
	 *    constants. Default: self::OUTPUT_HTML.
	 * @throws \ValueError if $output_type is invalid.
	 */
	public function __construct(int $output_type = self::OUTPUT_HTML)
	{
		if ($output_type === self::OUTPUT_TEXT) {
			$output_type === self::OUTPUT_HTML;
		}

		if (!in_array($output_type, [self::OUTPUT_BBC, self::OUTPUT_HTML, self::OUTPUT_HTML_STRICT])) {
			throw new \ValueError();
		}

		parent::__construct();

		$this->output_type = $output_type;

		// Maybe a mod wants to add a Markdown extension or something?
		IntegrationHook::call('integrate_markdown', [&$this->block_types, &$this->render_methods]);
	}

	/**
	 * Parses Markdown in the supplied string.
	 *
	 * Getting Markdown and BBCode to play nicely with each other requires some
	 * extra handling, so $from_bbcode_parser should always be set to true if
	 * the string was already processed by the SMF\Parsers\BBCodeParser class.
	 *
	 * @param string $string The string to parse.
	 * @param string $from_bbcode_parser Whether the string was the output from
	 *    the SMF\Parsers\BBCodeParser class.
	 * @param array $options Parser options. Recognized options are:
	 *    - 'hard_breaks':
	 *         How to handle line breaks in HTML output.
	 *         Value should be a bitmask of this class's BR_* constants.
	 *         If not set, defaults to Config::$modSettings['markdown_brs'].
	 *         Ignored when output is BBCode.
	 *    - 'parse_tags':
	 *         When not empty, only Markdown that is equivalent to one of these
	 *         BBCode tags will be rendered.
	 * @return string The result of parsing the string.
	 */
	public function parse(string $string, bool $from_bbcode_parser = false, array $options = []): string
	{
		$this->resetRuntimeProperties();

		$this->hard_breaks = (int) ($options['hard_breaks'] ?? Config::$modSettings['markdown_brs'] ?? 0);

		$this->parse_tags = $options['parse_tags'] ?? [];
		$this->setDisabled();

		// If the load average is too high, don't parse the Markdown.
		if ($this->highLoadAverage()) {
			return $this->message;
		}

		if ($from_bbcode_parser) {
			$input_replacements = [
				// Protect the content of certain tags.
				'/(<code\b[^>]*>)((?>\X(?!<code\b[^>]*>)|(?R))*)(<\/code>)/ui' => fn ($m) => $m[1] . ($this->placeholders[$m[2]] = md5($m[2])) . $m[3],

				'/(<pre\b[^>]*>)((?>\X(?!<pre\b[^>]*>)|(?R))*)(<\/pre>)/ui' => fn ($m) => $m[1] . ($this->placeholders[$m[2]] = md5($m[2])) . $m[3],

				'/(<div\b[^>]*\bclass="[^"]*\bbbc_html\b[^>]*>)((?>\X(?!<div\b[^>]*\bclass="[^"]*\bbbc_html\b[^>]*>)|(?R))*)(<\/div>)/ui' => fn ($m) => $m[1] . ($this->placeholders[$m[2]] = md5($m[2])) . $m[3],

				'/<div\b[^>]*\bclass="[^"]*\bmeaction\b[^>]*>\h*\K\*/ui' => fn ($m) => '&#42;',

				// Restore tabs.
				'/' . preg_quote(Utils::TAB_SUBSTITUTE, '/') . '/ui' => fn ($m) => "\t",

				// Decode &nbsp; entities to regular spaces.
				'/&(nbsp|#(x0*A0|0*160));/ui' => fn ($m) => ' ',

				// Restore line breaks.
				'/<br\s*\/?>/ui' => fn ($m) => "\n",

				// Decode &gt; entities at the start of lines.
				'/^\h*\K&(gt|#(x0*3C|0*60));/uim' => fn ($m) => '>',

				// Decode square bracket entities.
				'/^&#(x0*5B|0*91);/ui' => fn ($m) => '[',
				'/^&#(x0*5D|0*93);/ui' => fn ($m) => ']',

				// Replace EN QUAD chars with canonically equivalent EN SPACE chars.
				// We do this because we use EN QUAD for special purposes.
				'/\x{2000}/u' => fn ($m) => "\u{2002}",

				// Insert two line breaks after certain tags, if they are not followed by another tag.
				'/<((?>div class="(?>inline-block|justifytext|centertext|bbc_float|righttext|lefttext)|blockquote|\/cite))\b[^>]*>(?!(?:\n\n|\s*(?:<|$)))/uim' => fn ($m) => $m[0] . "\n\n",

				// Insert two line breaks after certain other tags, always.
				'/<((?>marquee|\/h[1-6]|li|t(?>d|h)))\b[^>]*>(?!\n\n)/uim' => fn ($m) => $m[0] . "\n\n",

				// Insert one line break before certain tags.
				'/(^|[^>])\h*\K<\/((?>blockquote|marquee|div|h[1-6]|li|t(?>d|h)))>/uim' => fn ($m) => "\n" . $m[0],
			];
		} else {
			$input_replacements = [
				// Restore tabs.
				'/' . preg_quote(Utils::TAB_SUBSTITUTE, '/') . '/ui' => fn ($m) => "\t",

				// Decode &nbsp; entities to regular spaces.
				'/&(nbsp|#(x0*A0|0*160));/ui' => fn ($m) => ' ',

				// Restore line breaks.
				'/<br\s*\/?>/ui' => fn ($m) => "\n",

				// Decode &gt; entities at the start of lines.
				'/^\h*\K&(gt|#(x0*3C|0*60));/uim' => fn ($m) => '>',

				// Replace EN QUAD chars with canonically equivalent EN SPACE chars.
				// We do this because we use EN QUAD for special purposes.
				'/\x{2000}/u' => fn ($m) => "\u{2002}",
			];
		}

		$string = preg_replace_callback_array($input_replacements, $string);

		// Build a structured series of block elements.
		$this->parseBlocks($string);

		// Build a structured series of inline elements within each block.
		$this->parseInlines($this->structure);

		// Render.
		$this->render($this->structure);

		// Fix up some final stuff in the rendered output.
		if ($this->output_type === self::OUTPUT_HTML) {
			// Align output with the expected output of the BBCodeParser.
			$output_replacements = [
				'/\x{2000}{1,4}/u' => fn ($m) => Utils::TAB_SUBSTITUTE,
				'/  /' => fn ($m) => " \u{A0}",
			];
		} else {
			// Restore tabs.
			$output_replacements = [
				'/\x{2000}{1,4}/u' => fn ($m) => "\t",
			];
		}

		// If the supplied string came from the BBCodeParser, we could end up
		// with paragraph elements inside heading elements. Fix them.
		if ($from_bbcode_parser && $this->output_type !== self::OUTPUT_BBC) {
			$output_replacements['/<(h[1-6])([^>]*)>\s*<p>(\X*?)<\/p>\s*<\/\1>/u'] = fn ($m) => '<' . $m[1] . $m[2] . '>' . strtr($m[3], ['<p>' => '', '</p>' => '<br><br>']) . '</' . $m[1] . '>';
		}

		$this->rendered = preg_replace_callback_array($output_replacements, $this->rendered);

		// Restore any substrings that we had protected from processing.
		if (!empty($this->placeholders)) {
			$this->rendered = strtr($this->rendered, array_flip($this->placeholders));
		}

		return $this->rendered;
	}

	/************************
	 * Public static methods.
	 ************************/

	/**
	 * Returns a reusable instance of this class.
	 *
	 * Using this method to get a MarkdownParser instance saves memory by avoiding
	 * creating redundant instances.
	 *
	 * @param int $output_type The type of output to generate.
	 *    Value must be one of this class's OUTPUT_* constants.
	 *    Default: self::OUTPUT_HTML.
	 * @param ?int $hard_breaks How to handle line breaks in HTML output.
	 *    Value should be bitmask of this class's BR_* constants.
	 *    If null, uses the value of Config::$modSettings['markdown_brs'].
	 *    Ignored when output is BBCode. Default: null.
	 * @throws \ValueError if $output_type is invalid.
	 * @return object An instance of this class.
	 */
	public static function load(int $output_type = self::OUTPUT_HTML, ?int $hard_breaks = null): self
	{
		$hard_breaks = $hard_breaks ?? (int) (Config::$modSettings['markdown_brs'] ?? 0);

		if (!isset(self::$parsers[$output_type][$hard_breaks])) {
			self::$parsers[$output_type][$hard_breaks] = new self($output_type, $hard_breaks);
		}

		return self::$parsers[$output_type][$hard_breaks];
	}

	/******************
	 * Internal methods
	 ******************/

	/*
	 * Part 1: Parsing block structure.
	 */

	/**
	 * Parses the string to build a structured series of block elements.
	 *
	 * The result is stored as $this->structured.
	 *
	 * @param string $string The string to parse.
	 */
	protected function parseBlocks(string $string): void
	{
		$lines = explode("\n", $string);

		for ($linenum = 0; $linenum < count($lines); $linenum++) {
			$line_info = [
				'linenum' => $linenum,
				'string' => '',
				'indent' => 0,
				'recursion_level' => 0,
				'content' => '',
				'possible_types' => [],
			];

			// Treat tabs as four character tab stops.
			foreach (mb_str_split($lines[$linenum]) as $char) {
				if ($char === "\t") {
					do {
						// Use "EN QUAD" characters rather than normal spaces
						// so that we can restore any surviving tabs at the end.
						$line_info['string'] .= "\u{2000}";
					} while (mb_strlen($line_info['string']) % 4 !== 0);
				} else {
					$line_info['string'] .= $char;
				}
			}

			// Analyze the line. This also calls $this->findOpen().
			$this->line_info[$linenum] = $line_info = $this->analyzeLine($line_info);

			// Add whatever new block elements we need to the structure.
			do {
				// Keep track of whether the string changes.
				$string = $line_info['string'];

				$should_append = false;
				$last_container = null;

				foreach ($this->open as $o => &$open_block) {
					// Keep track of the closest ancestor container block.
					if (
						$this->block_types[$open_block['type']]['is_container']
						&& $open_block['type'] !== $line_info['type']
					) {
						$last_container = $o;
					}

					if ($line_info['type'] === 'blank') {
						$should_append = true;
					}

					// Can the current line continue the current open block?
					if (
						!$should_append
						&& $open_block['type'] === $line_info['type']
						&& $this->block_types[$open_block['type']]['continue_test'] !== false
					) {
						$continue_test = $this->getMethod($this->block_types[$open_block['type']]['continue_test'] ?? false);

						if (
							is_callable($continue_test)
							&& $continue_test($line_info, $last_container, $o)
						) {
							$should_append = true;
							break;
						}
					}
				}

				if ($should_append) {
					$append = $this->getMethod($this->block_types[$line_info['type']]['append'] ?? false);

					if (is_callable($append)) {
						$append($line_info, $last_container, $o);
					}
				} else {
					$add = $this->getMethod($this->block_types[$line_info['type']]['add'] ?? null);

					if (is_callable($add)) {
						$add($line_info, $last_container, $o);
					}

					foreach ($this->open as $o => $dummy) {
						if ($o > $last_container) {
							$close = $this->getMethod($this->block_types[$this->open[$o]['type']]['close'] ?? false);

							if (is_callable($close)) {
								$close($o);
							}
						}
					}
				}

				if ($this->block_types[$line_info['type']]['is_container']) {
					$line_info['string'] = str_repeat(' ', $line_info['indent']);
					$line_info['string'] .= preg_replace_callback(
						$this->block_types[$line_info['type']]['marker_pattern'],
						fn ($matches) => str_repeat(' ', mb_strlen($matches[0])),
						$line_info['content'],
					);

					$line_info['recursion_level']++;

					$line_info = $this->analyzeLine($line_info);
				}
			} while ($string !== $line_info['string']);

			for ($o = max(array_keys($this->open)); $o > 0; $o--) {
				$closer_test = $this->getMethod($this->block_types[$this->open[$o]['type']]['closer_test'] ?? false);

				if (
					!is_callable($closer_test)
					|| $closer_test($line_info, $last_container, $o)
				) {
					$close = $this->getMethod($this->block_types[$this->open[$o]['type']]['close'] ?? false);

					if (is_callable($close)) {
						$close($o);
					}
				}
			}
		}

		// Finally, close the last open blocks.
		$this->findOpen();

		foreach ($this->open as $o => $dummy) {
			$this->getMethod($this->block_types[$this->open[$o]['type']]['close'] ?? 'closeBlock')($o);
		}

		$this->in_code = 0;
		$this->info_string = '';
		$this->in_html = 0;
	}

	/*
	 * Part 1.a: Input analysis.
	 */

	/**
	 * Analyzes a line to build a complete set of information about it.
	 *
	 * @param array $line_info Basic line info that we already know.
	 * @return array Complete info about the current line.
	 */
	protected function analyzeLine(array $line_info): array
	{
		$this->findOpen();

		$line_info['indent'] = strspn($line_info['string'], ' ');
		$line_info['content'] = substr($line_info['string'], $line_info['indent']);

		// Figure out which block types this line could open.
		$line_info['possible_types'] = [];
		$line_info['tested_types'] = [];
		unset($line_info['type']);

		foreach ($this->block_types as $type => $def) {
			$line_info['tested_types'][] = $type;

			$opener_test = $this->getMethod($def['opener_test'] ?? false);

			if (is_callable($opener_test) && $opener_test($line_info)) {
				$line_info['possible_types'][] = $type;
			}
		}

		// Figure out which block type this line actually does open.
		// Always prefer the type that matches the most distant ancestor block.
		$line_info['possible_types'] = array_unique(array_merge(
			array_intersect(
				array_map(fn ($open_block) => $open_block['type'], $this->open),
				$line_info['possible_types'],
			),
			$line_info['possible_types'],
		));

		$line_info['type'] = reset($line_info['possible_types']);

		return $line_info;
	}

	/**
	 * Finds the elements of $this->structure that are currently open.
	 *
	 * The found items are stored in $this->open.
	 *
	 * @param ?array &$block An element of $this->structure. If null, will be
	 *    set to $this->structure itself.
	 */
	protected function findOpen(?array &$block = null): void
	{
		if (!isset($block)) {
			$this->open = [];
			$block = &$this->structure;
		}

		$this->last_block = &$block;

		if (!empty($block['open'])) {
			$this->open[] = &$block;
		}

		if (!empty($block['content'])) {
			$last_child = &$block['content'][array_key_last($block['content'])];

			if (is_array($last_child)) {
				$this->findOpen($last_child);
			}
		}
	}

	/**
	 * Tests whether a line is blank.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is blank.
	 */
	protected function testIsBlank(array $line_info): bool
	{
		if (in_array('blank', $line_info['possible_types'])) {
			return true;
		}

		return Utils::htmlTrim($line_info['string']) === '';
	}

	/**
	 * Tests whether a line is a thematic break.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is a thematic break.
	 */
	protected function testIsHr(array $line_info): bool
	{
		if (in_array('hr', $line_info['possible_types'])) {
			return true;
		}

		$first_char = mb_substr($line_info['content'], 0, 1);

		if (!in_array($first_char, ['*', '-', '_'])) {
			return false;
		}

		if (!preg_match('/^(' . preg_quote($first_char) . '\h*){3,}$/u', $line_info['content'])) {
			return false;
		}

		return !($first_char === '-' && $this->testIsSetextHeading($line_info));
	}

	/**
	 * Tests whether a line is part of an ATX heading.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is part of an ATX heading.
	 */
	protected function testIsAtxHeading(array $line_info): bool
	{
		if (in_array('atx_heading', $line_info['possible_types'])) {
			return true;
		}

		if (mb_substr($line_info['content'], 0, 1) !== '#') {
			return false;
		}

		return (bool) preg_match('/^#{1,6}(\h|$)/u', $line_info['content']);
	}

	/**
	 * Tests whether a line is part of a setext heading.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is part of a setext heading.
	 */
	protected function testIsSetextHeading(array $line_info): bool
	{
		if (in_array('setext_heading', $line_info['possible_types'])) {
			return true;
		}

		$first_char = mb_substr($line_info['content'], 0, 1);

		if (!in_array($first_char, ['=', '-'])) {
			return false;
		}

		if (($this->open[array_key_last($this->open)]['type'] ?? '') !== 'p') {
			return false;
		}

		return (bool) preg_match('/^' . preg_quote($first_char) . '*\h*$/u', $line_info['content']);
	}

	/**
	 * Tests whether a line is part of an indented code block.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $o Key of the current block in $this->open.
	 * @return bool Whether this line is part of an indented code block.
	 */
	protected function testIsIndentedCode(array $line_info): bool
	{
		if (in_array('indented_code', $line_info['possible_types'])) {
			return true;
		}

		if ($this->in_code === 2) {
			return false;
		}

		if ($this->testIsBlank($line_info) && $this->in_code === 1) {
			return true;
		}

		if ($line_info['indent'] < 4) {
			$this->in_code = 0;

			return false;
		}

		if (!empty($this->open)) {
			$open_block = $this->open[array_key_last($this->open)];

			if (
				// Indented code cannot interrupt paragraphs.
				$open_block['type'] === 'p'
				// Ignore the indentation needed to continue a list item.
				|| (
					$open_block['type'] === 'list_item'
					&& $open_block['properties']['indent'] >= $line_info['indent']
				)
			) {
				$this->in_code = 0;

				return false;
			}
		}

		$this->in_code = 1;

		return true;
	}

	/**
	 * Tests whether a line is part of a fenced code block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is part of a fenced code block.
	 */
	protected function testIsFencedCode(array $line_info): bool
	{
		if (
			$this->in_code !== 1
			&& $this->opening_fence_linenum !== $line_info['linenum']
			&& $this->testIsCodeFence($line_info)
		) {
			// Toggle the mode.
			$this->in_code = empty($this->in_code) ? 2 : 0;

			// Remember which line and fence string opened this code block.
			if ($this->in_code === 2) {
				$this->opening_fence_linenum = $line_info['linenum'];
				$this->opening_fence = substr($line_info['content'], 0, strspn($line_info['content'], substr($line_info['content'], 0, 1)));
				$this->info_string = Utils::htmlTrim(substr($line_info['content'], strlen($this->opening_fence)));
			} else {
				$this->opening_fence_linenum = null;
				$this->opening_fence = '';
				$this->info_string = '';
			}

			// The fence itself is always part of the code block.
			return true;
		}

		return $this->in_code === 2;
	}

	/**
	 * Helper for testIsFencedCode(). Tests whether a line is a code fence.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is a code fence.
	 */
	protected function testIsCodeFence(array $line_info): bool
	{
		if ($line_info['indent'] > 3) {
			return false;
		}

		// If we're already in a fenced code block, the closing fence must use
		// the same characters and be at least as long as the opening fence.
		if ($this->in_code === 2 && $line_info['linenum'] > $this->opening_fence_linenum) {
			return (
				str_starts_with($line_info['content'], $this->opening_fence)
				&& strspn(Utils::htmlTrimRight($line_info['content']), substr($line_info['content'], 0, 1)) === strlen(Utils::htmlTrimRight($line_info['content']))
			);
		}

		$is_fence = (bool) preg_match($this->block_types['fenced_code']['marker_pattern'], $line_info['content'], $matches);

		// Info strings for backtick fences cannot contain backticks, because
		// they would otherwise be confusable with inline code spans.
		if (
			$is_fence
			&& $matches[1] === '`'
			&& strrpos($line_info['content'], '`') > strlen($matches[0])
		) {
			$is_fence = false;
		}

		return $is_fence;
	}

	/**
	 * Tests whether a line opens a blockquote.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a blockquote.
	 */
	protected function testOpensQuote(array $line_info): bool
	{
		if (in_array('blockquote', $line_info['possible_types'])) {
			return true;
		}

		return (bool) (mb_substr($line_info['content'], 0, 1) === '>');
	}

	/**
	 * Tests whether a line closes a blockquote.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a blockquote.
	 */
	protected function testClosesQuote(array $line_info, int $last_container, int $o): bool
	{
		return (
			$line_info['string'] === $this->line_info[$line_info['linenum']]['string']
			&& !$this->testOpensQuote($line_info, $last_container, $o)
			&& !in_array('p', $line_info['possible_types'])
		);
	}

	/**
	 * Tests whether a line opens a list item.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is opens a list item.
	 */
	protected function testOpensListItem(array $line_info): bool
	{
		if (in_array('list_item', $line_info['possible_types'])) {
			return true;
		}

		if ($this->testIsHr($line_info)) {
			return false;
		}

		if (!preg_match($this->block_types['list_item']['marker_pattern'], $line_info['content'], $matches)) {
			return false;
		}

		// Bullet lists can always interrupt paragraphs, but numbered lists can
		// interrupt only if the number is '1'.
		return (
			// Bullet lists can interrupt paragraphs.
			$matches['bullet'] !== ''
			// In a list already, so we aren't interrupting anything.
			|| ($this->open[array_key_last($this->open) - 1]['type'] ?? null) === 'list_item'
			// Nearest open block is not a paragraph, so we aren't interrupting anything.
			|| ($this->open[array_key_last($this->open)]['type'] ?? null) !== 'p'
			// Previous line was blank, so we aren't interrupting anything.
			|| Utils::htmlTrim($this->line_info[$line_info['linenum'] - 1]['string'] ?? '') === ''
			// The number 1 can interrupt paragraphs.
			|| $matches['number'] === '1'
		);
	}

	/**
	 * Tests whether a line closes a list item.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a list item.
	 */
	protected function testClosesListItem(array $line_info, int $last_container, int $o): bool
	{
		if ($line_info['type'] === 'p' || $line_info['type'] === 'blank') {
			return false;
		}

		return (bool) ($line_info['indent'] < $this->open[$o]['properties']['indent']);
	}

	/**
	 * Tests whether a line opens a type 1 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a type 1 HTML block.
	 */
	protected function testOpensHtml1(array $line_info): bool
	{
		return $this->testOpensHtml(1, $line_info);
	}

	/**
	 * Tests whether a line closes a type 1 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a type 1 HTML block.
	 */
	protected function testClosesHtml1(array $line_info): bool
	{
		return $this->testClosesHtml(1, $line_info);
	}

	/**
	 * Tests whether a line opens a type 2 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a type 2 HTML block.
	 */
	protected function testOpensHtml2(array $line_info): bool
	{
		return $this->testOpensHtml(2, $line_info);
	}

	/**
	 * Tests whether a line closes a type 2 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a type 2 HTML block.
	 */
	protected function testClosesHtml2(array $line_info): bool
	{
		return $this->testClosesHtml(2, $line_info);
	}

	/**
	 * Tests whether a line opens a type 3 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a type 3 HTML block.
	 */
	protected function testOpensHtml3(array $line_info): bool
	{
		return $this->testOpensHtml(3, $line_info);
	}

	/**
	 * Tests whether a line closes a type 3 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a type 3 HTML block.
	 */
	protected function testClosesHtml3(array $line_info): bool
	{
		return $this->testClosesHtml(3, $line_info);
	}

	/**
	 * Tests whether a line opens a type 4 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a type 4 HTML block.
	 */
	protected function testOpensHtml4(array $line_info): bool
	{
		return $this->testOpensHtml(4, $line_info);
	}

	/**
	 * Tests whether a line closes a type 4 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a type 4 HTML block.
	 */
	protected function testClosesHtml4(array $line_info): bool
	{
		return $this->testClosesHtml(4, $line_info);
	}

	/**
	 * Tests whether a line opens a type 5 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a type 5 HTML block.
	 */
	protected function testOpensHtml5(array $line_info): bool
	{
		return $this->testOpensHtml(5, $line_info);
	}

	/**
	 * Tests whether a line closes a type 5 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a type 5 HTML block.
	 */
	protected function testClosesHtml5(array $line_info): bool
	{
		return $this->testClosesHtml(5, $line_info);
	}

	/**
	 * Tests whether a line opens a type 6 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a type 6 HTML block.
	 */
	protected function testOpensHtml6(array $line_info): bool
	{
		return $this->testOpensHtml(6, $line_info);
	}

	/**
	 * Tests whether a line closes a type 6 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a type 6 HTML block.
	 */
	protected function testClosesHtml6(array $line_info): bool
	{
		return $this->testClosesHtml(6, $line_info);
	}

	/**
	 * Tests whether a line opens a type 7 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens a type 7 HTML block.
	 */
	protected function testOpensHtml7(array $line_info): bool
	{
		return $this->testOpensHtml(7, $line_info);
	}

	/**
	 * Tests whether a line closes a type 7 HTML block.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes a type 7 HTML block.
	 */
	protected function testClosesHtml7(array $line_info): bool
	{
		return $this->testClosesHtml(7, $line_info);
	}

	/**
	 * Helper for the various testOpensHtml* methods that performs the tests.
	 *
	 * @param int $type The type of HTML block to test for.
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line opens an HTML block of the given type.
	 */
	protected function testOpensHtml(int $type, array $line_info): bool
	{
		if (in_array('html_' . $type, $line_info['possible_types'])) {
			return true;
		}

		// Already in some other type of HTML block.
		if (!empty($this->in_html) && $this->in_html !== $type) {
			return false;
		}

		// A previous line opened the block, and this line doesn't close it.
		if ($this->in_html === $type && !$this->testClosesHtml($type, $line_info)) {
			return true;
		}

		// Indent must be less than four.
		if ($line_info['indent'] > 3) {
			return false;
		}

		if (
			!$this->block_types['html_' . $type]['interrupts_p']
			&& ($this->line_info[$line_info['linenum'] - 1]['type'] ?? '') === 'p'
		) {
			return false;
		}

		if (preg_match($this->block_types['html_' . $type]['marker_pattern'], $line_info['content'])) {
			$this->in_html = $type;
		}

		return $this->in_html === $type;
	}

	/**
	 * Helper for the various testClosesHtml* methods that performs the tests.
	 *
	 * @param int $type The type of HTML block to test for.
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line closes an HTML block of the given type.
	 */
	protected function testClosesHtml(int $type, array $line_info): bool
	{
		if ($this->in_html !== $type) {
			return false;
		}

		$prev_line_info = $this->line_info[$line_info['linenum'] - 1] ?? ['content' => ''];

		switch ($type) {
			case 1:
				$closes = preg_match('/<\/?(script|pre|style)>/ui', $prev_line_info['content']);
				break;

			case 2:
				$closes = str_contains($prev_line_info['content'], '-->');
				break;

			case 3:
				$closes = str_contains($prev_line_info['content'], '?' . '>');
				break;

			case 4:
				$closes = str_contains($prev_line_info['content'], '>');
				break;

			case 5:
				$closes = str_contains($prev_line_info['content'], ']]>');
				break;

			case 6:
			case 7:
				$closes = $this->testIsBlank($line_info);
				break;
		}

		if ($closes) {
			$this->in_html = 0;
		}

		return (bool) $closes;
	}

	/**
	 * Tests whether a line is part of a table.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line is part of a table.
	 */
	protected function testIsTable(array $line_info): bool
	{
		if (in_array('table', $line_info['possible_types'])) {
			return true;
		}

		if ($line_info['indent'] > 3) {
			$this->table_align = [];

			return false;
		}

		// Already in a table.
		if ($this->table_align !== []) {
			// Any other block element breaks the table.
			if (!$this->testIsParagraph($line_info)) {
				$this->table_align = [];
			}

			return $this->table_align !== [];
		}

		// At this point, we're checking if this is a new table.
		$last_open = $this->open[array_key_last($this->open)];

		// A valid header row will initially be parsed as a paragraph
		if ($this->open[array_key_last($this->open)]['type'] !== 'p') {
			return false;
		}

		// Must have exactly one header row.
		if (count($this->open[array_key_last($this->open)]['content']) !== 1) {
			return false;
		}

		$thead_cells = array_map('\SMF\Utils::htmlTrim', preg_split('/(?<!\\\\)\|/u', $last_open['content'][0], -1, PREG_SPLIT_NO_EMPTY));
		$delim_cells = array_map('\SMF\Utils::htmlTrim', preg_split('/(?<!\\\\)\|/u', $line_info['content'], -1, PREG_SPLIT_NO_EMPTY));

		if (count($thead_cells) !== count($delim_cells)) {
			return false;
		}

		foreach ($delim_cells as $d => $delim_cell) {
			if (!preg_match('/^(?P<left>:?)-+(?P<right>:?)$/u', $delim_cell, $matches)) {
				return false;
			}

			$align = !empty($matches['left']) ? 'left' : null;
			$align = !empty($matches['right']) ? (isset($align) ? 'center' : 'right') : $align;

			$delim_cells[$d] = $align ?? 'none';
		}

		// The table delimiter line doesn't count if it was just '-----'.
		// It must have either a '|' or a ':' in it somewhere.
		if (
			count($delim_cells) === 1
			&& $delim_cells[0] === 'none'
			&& !preg_match('/(?<!\\\\)\|/u', $line_info['content'])
		) {
			return false;
		}

		$this->table_align = $delim_cells;

		return true;
	}

	/**
	 * Tests whether a line can be part of a paragraph.
	 *
	 * @param array $line_info Info about the current line.
	 * @return bool Whether this line can be part of a paragraph.
	 */
	protected function testIsParagraph(array $line_info): bool
	{
		foreach (array_diff(array_keys($this->block_types), $line_info['tested_types']) as $untested) {
			$opener_test = $this->getMethod($def['opener_test'] ?? false);

			if (is_callable($opener_test) && $opener_test($line_info)) {
				return false;
			}
		}

		return array_diff($line_info['possible_types'], ['p']) === [];
	}

	/*
	 * Part 1.b: Building block structure.
	 */

	/**
	 * The default method for adding a new block element to the structure.
	 * This is used for any block type that doesn't have its own special method.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function addBlock(array $line_info, int $last_container, int $o): void
	{
		while (
			$last_container > 0
			&& $line_info['type'] !== 'blank'
			&& $this->block_types[$this->open[$last_container - 1]['type']]['is_container']
			&& $this->open[$last_container]['properties']['indent'] > $line_info['indent']
		) {
			$last_container--;
		}

		$properties = $line_info['properties'] ?? [];

		if (!isset($properties['indent'])) {
			$properties['indent'] = $line_info['indent'];
		}

		$this->open[$last_container]['content'][] = [
			'type' => $line_info['type'],
			'open' => $this->block_types[$line_info['type']]['continue_test'] !== false,
			'properties' => $properties,
			'content' => $this->block_types[$line_info['type']]['is_container'] || !isset($line_info['content']) ? [] : [$line_info['content']],
		];
	}

	/**
	 * Adds a list item to the structure.
	 *
	 * If necessary, first adds a list to the structure to hold the list item.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function addListItem(array $line_info, int $last_container, int $o): void
	{
		preg_match($this->block_types['list_item']['marker_pattern'], $line_info['content'], $matches);

		// This should never happen, but just in case...
		if (empty($matches)) {
			$this->addBlock($line_info, $last_container, $o);

			return;
		}

		$marker = $matches[0];
		$bullet = ($matches['bullet'] ?? '') !== '' ? $matches['bullet'] : null;
		$number = ($matches['number'] ?? '') !== '' ? $matches['number'] : null;
		$num_punct = ($matches['num_punct'] ?? '') !== '' ? $matches['num_punct'] : null;

		$indent = $line_info['indent'] + mb_strlen($marker) + strspn($line_info['content'], ' ', strlen($marker));

		// Check for nested lists.
		if (
			$this->open[$last_container]['type'] === 'list'
			&& $line_info['indent'] >= $this->open[$last_container]['properties']['indent']
		) {
			// Close the open paragraph (or whatever) inside the open list item.
			while ($this->open[$o]['type'] !== 'list_item') {
				$this->getMethod($this->block_types[$this->open[$o]['type']]['close'] ?? 'closeBlock')($o);
				$o--;
			}

			// Consider the open list item to be our container.
			$last_container = $o;
		}

		// If this list item doesn't match the existing list's type,
		// exit the existing list so we can start a new one.
		if (
			$this->open[$last_container]['type'] === 'list'
			&& (
				isset($number) !== $this->open[$last_container]['properties']['ordered']
				|| (
					isset($bullet)
					&& $bullet !== $this->open[$last_container]['properties']['marker_char']
				)
				|| (
					isset($num_punct)
					&& $num_punct !== $this->open[$last_container]['properties']['marker_char']
				)
			)
		) {
			do {
				$last_container--;
			} while ($last_container > 0 && !$this->block_types[$this->open[$last_container]['type']]['is_container']);
		}

		// Do we need to start a new list?
		if ($this->open[$last_container]['type'] !== 'list') {
			$this->open[$last_container]['content'][] = [
				'type' => 'list',
				'open' => true,
				'properties' => [
					'ordered' => isset($number),
					'start' => (int) ($number ?? 0),
					'marker_char' => $bullet ?? $num_punct ?? '',
					'indent' => $indent,
				],
				'content' => [
					[
						'type' => 'list_item',
						'open' => true,
						'properties' => [
							'indent' => $indent,
						],
						'content' => [],
					],
				],
			];
		}
		// Just add an item to the existing list.
		else {
			$this->open[$last_container]['content'][] = [
				'type' => 'list_item',
				'open' => true,
				'properties' => [
					'indent' => $indent,
				],
				'content' => [],
			];
		}
	}

	/**
	 * Adds an ATX heading to the structure.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function addAtxHeading(array $line_info, int $last_container, int $o): void
	{
		$line_info['properties']['level'] = strspn($line_info['content'], '#');

		$line_info['content'] = strtr(preg_replace('/^\h*#+\h*|(\h+#+)?\h*$/u', '', strtr($line_info['content'], ['\\#' => "\u{E000}"])), ["\u{E000}" => '\\#']);

		$this->addBlock($line_info, $last_container, $o);
	}

	/**
	 * Adds a setext heading to the structure.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function addSetextHeading(array $line_info, int $last_container, int $o): void
	{
		$this->open[$o]['type'] = 'setext_heading';
		$this->open[$o]['properties']['level'] = str_starts_with($line_info['content'], '=') ? 1 : 2;
	}

	/**
	 * Adds an indented code block to the structure.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function addIndentedCode(array $line_info, int $last_container, int $o): void
	{
		$indent = 4;

		if (
			isset($this->open[$last_container])
			&& $this->open[$last_container]['type'] !== 'indented_code'
		) {
			$indent += $this->open[$last_container]['properties']['indent'] ?? 0;
		}

		$this->open[$last_container]['content'][] = [
			'type' => $line_info['type'],
			'open' => $this->block_types[$line_info['type']]['continue_test'] !== false,
			'properties' => [
				'indent' => $indent,
			],
			'content' => [
				($line_info['indent'] > $indent ? str_repeat(' ', $line_info['indent'] - $indent) : '') . $line_info['content'],
			],
		];
	}

	/**
	 * Adds a fenced code block to the structure.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function addFencedCode(array $line_info, int $last_container, int $o): void
	{
		// Opening code fence.
		if ($this->testIsCodeFence($line_info)) {
			$line_info['properties']['indent'] = $line_info['indent'];
			$line_info['properties']['info_string'] = $this->info_string;
			unset($line_info['content']);

			$this->addBlock($line_info, $last_container, $o);

			return;
		}

		// Otherwise, adding is really just appending.
		$this->appendContent($line_info, $last_container, $o);
	}

	/**
	 * Adds a table to the structure.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function addTable(array $line_info, int $last_container, int $o): void
	{
		if ($this->open[$o]['type'] !== 'p' || count($this->open[$o]['content']) !== 1) {
			return;
		}

		$cells = array_map('\SMF\Utils::htmlTrim', preg_split('/(?<!\\\\)\|/u', $this->open[$o]['content'][0], -1, PREG_SPLIT_NO_EMPTY));

		$tr = [];

		foreach ($cells as $th) {
			$tr[] = $th;
		}

		$this->open[$o]['type'] = 'table';
		$this->open[$o]['properties']['align'] = $this->table_align;
		$this->open[$o]['content'] = [$tr];
	}

	/**
	 * The default method for appending content to an existing block element.
	 * This is used for any block type that doesn't have its own special method.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function appendContent(array $line_info, int $last_container, int $o): void
	{
		if (
			!empty($this->open[$o]['properties']['blank_after'])
			&& (
				$this->hard_breaks & self::BR_IN_PARAGRAPHS
				|| (
					$this->hard_breaks & self::BR_LINES
					&& $this->open[$o]['properties']['blank_after'] > 1
				)
			)
		) {
			for ($i = 0; $i < $this->open[$o]['properties']['blank_after']; $i++) {
				$this->open[$o]['content'][] = "\n";
			}
		}

		$this->open[$o]['properties']['blank_after'] = 0;
		$this->open[$o]['content'][] = $line_info['content'];
	}

	/**
	 * Appends a blank line to the currently open block.
	 *
	 * Blank lines get special handling compared to other appended content.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function appendBlank(array $line_info, int $last_container, int $o): void
	{
		if (!isset($this->last_block['properties'])) {
			return;
		}

		if (!isset($this->last_block['properties']['blank_after'])) {
			$this->last_block['properties']['blank_after'] = 0;
		}

		$this->last_block['properties']['blank_after']++;
	}

	/**
	 * Appends a line to a currently open fenced code block.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function appendFencedCode(array $line_info, int $last_container, int $o): void
	{
		// If this line is the closing code fence, just turn off $this->in_code.
		if ($this->testIsCodeFence($line_info)) {
			$this->in_code = 0;

			return;
		}

		$indent = $this->open[$o]['properties']['indent'] ?? 0;

		$this->open[$o]['content'][] = ($line_info['indent'] > $indent ? str_repeat(' ', $line_info['indent'] - $indent) : '') . Utils::htmlTrimLeft($line_info['content']);
	}

	/**
	 * Appends a line to a currently open indented code block.
	 *
	 * @param array $line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function appendIndentedCode(array $line_info, int $last_container, int $o): void
	{
		$indent = $this->open[$o]['properties']['indent'] ?? 0;

		$this->open[$o]['content'][] = ($line_info['indent'] > $indent ? str_repeat(' ', $line_info['indent'] - $indent) : '') . $line_info['content'];
	}

	/**
	 * Appends a line to a currently open table as a new table row.
	 *
	 * @param array &$line_info Info about the current line.
	 * @param int $last_container Key of the last container block in $this->open.
	 * @param int $o Key of the current block in $this->open.
	 */
	protected function appendTableRow(array &$line_info, int $last_container, int $o): void
	{
		$cells = array_map('\SMF\Utils::htmlTrim', preg_split('/(?<!\\\\)\|/u', $line_info['content'], -1, PREG_SPLIT_NO_EMPTY));

		$tr = [];

		foreach ($cells as $th) {
			$tr[] = $th;
		}

		$this->open[$o]['content'][] = $tr;
	}

	/**
	 * The default method for closing an open block element.
	 * This is used for any block type that doesn't have its own special method.
	 *
	 * @param int $o Key of the block in $this->open.
	 */
	protected function closeBlock(int $o): void
	{
		$this->open[$o]['open'] = false;

		// Closing a parent block automatically closes its children, too.
		if ($o < array_key_last($this->open)) {
			$this->closeBlock($o + 1);
		}
	}

	/**
	 * Closes a code block.
	 *
	 * @param int $o Key of the block in $this->open.
	 */
	protected function closeCodeBlock(int $o): void
	{
		// Remove unwanted blank lines from the end of code blocks.
		$last_content = array_key_last($this->open[$o]['content']);

		while (
			isset($this->open[$o]['content'][$last_content])
			&& Utils::htmlTrim($this->open[$o]['content'][$last_content]) === ''
		) {
			unset($this->open[$o]['content'][$last_content--]);
		}

		// Toggle the state of $this->in_code.
		if ($this->in_code === ($this->open[$o]['type'] === 'fenced_code' ? 2 : 1)) {
			$this->in_code = 0;
		}

		$this->closeBlock($o);
	}

	/**
	 * Closes an open table.
	 *
	 * @param int $o Key of the block in $this->open.
	 */
	protected function closeTable(int $o): void
	{
		if ($this->table_align === []) {
			$this->closeBlock($o);
		}
	}

	/**
	 * Closes a paragraph.
	 *
	 * @param int $o Key of the block in $this->open.
	 */
	protected function closeParagraph(int $o): void
	{
		// Check paragraphs for link reference definitions.
		$content = implode("\n", $this->open[$o]['content']);

		while (preg_match('~' . self::REGEX_LINK_REF_DEF . '~u', $content, $matches)) {
			$text = $this->extractLinkLabel($matches['label']);
			$url = $this->extractLinkUrl($matches['destination'] ?? '');
			$title = $this->extractLinkTitle($matches['title'] ?? '');

			// Title must not contain blank lines.
			if (preg_match('/\n\h*\n/u', $title)) {
				break;
			}

			// If a link reference for this label has not yet been set, do so.
			if (!isset($this->link_reference_definitions[$text])) {
				$this->link_reference_definitions[$text] = [
					'url' => $url,
					'title' => $title,
				];
			}

			// Remove the link reference definition.
			$content = substr($content, strlen($matches[0]));
		}

		$this->open[$o]['content'] = array_filter(explode("\n", $content), 'strlen');

		$this->closeBlock($o);
	}

	/*
	 * Part 2: Parsing inline structure.
	 */

	/**
	 * Parses inline markup inside a block element and all of its children.
	 *
	 * @param array &$block A block element in which to parse inline content.
	 */
	protected function parseInlines(array &$block): void
	{
		// Tables are special...
		if ($block['type'] === 'table') {
			foreach ($block['content'] as &$row) {
				foreach ($row as &$cell) {
					$cell = [
						'type' => 'td',
						'content' => [$cell],
					];

					$this->parseInlines($cell);
				}
			}

			return;
		}

		if (array_filter($block['content'], 'is_string') === []) {
			// Recurse down to the leaf nodes.
			foreach ($block['content'] as &$value) {
				$this->parseInlines($value);
			}
		} elseif (in_array($block['type'], ['atx_heading', 'setext_heading', 'p', 'td'])) {
			$content = $this->parseInlineFirstPass($block['content']);
			$content = $this->parseInlineSecondPass($content);

			$block['content'] = $content;
		}
	}

	/**
	 * Parses inline code spans, HTML tags, and `<http://example.com>` links.
	 *
	 * @param array $content The content of a paragraph or heading block.
	 */
	protected function parseInlineFirstPass(array $content): array
	{
		$new_content = [];

		$string = implode("\n", $content);
		$chars = mb_str_split($string);

		$escaped = false;
		$in_code = false;
		$code_delim = '';
		$string_part = '';

		for ($i = 0; $i < count($chars); $i++) {
			$char = $chars[$i];

			switch ($char) {
				case '\\':
					$escaped = true;
					break;

				// Starting or ending an inline code span.
				case '`':
					if ($escaped) {
						$string_part .= $char;
						$escaped = false;
						break;
					}

					if (!$in_code) {
						$code_delim .= '`';

						while (isset($chars[$i + 1]) && $chars[$i + 1] === '`') {
							$code_delim .= '`';
							$i++;
						}

						$in_code = true;
						$new_content[] = $string_part;
						$string_part = '';
					} else {
						$temp = '`';
						$temp_i = $i;

						while (isset($chars[$temp_i + 1]) && $chars[++$temp_i] === '`') {
							$temp .= '`';
						}

						if ($temp === $code_delim) {
							$i = $temp_i - 1;
							$in_code = false;

							if ($string_part !== '') {
								$new_content[] = [
									'type' => 'code',
									'properties' => [],
									'content' => [$string_part],
								];
							}

							$string_part = '';
						}
					}
					break;

				// Possibly starting an inline "autolink" or HTML tag.
				case '<':
					if ($escaped) {
						$string_part .= $char;
						$escaped = false;
						break;
					}

					$new_content[] = $string_part;
					$string_part = '';

					$temp_string = '';

					while (isset($chars[$i + 1]) && $chars[$i + 1] !== '>') {
						$temp_string .= $chars[++$i];
					}

					// Is this a URI?
					$urls = Autolinker::load()->detectUrls($temp_string);

					if ($urls === [$temp_string]) {
						$new_content[] = [
							'type' => 'link',
							'properties' => [
								'url' => $temp_string,
							],
							'content' => [$temp_string],
						];

						$i++;

						break;
					}

					// Is this an HTML tag?
					if (preg_match('~' . self::REGEX_HTML_TAG . '~u', '<' . $temp_string . '>')) {
						$new_content[] = [
							'type' => 'html',
							'properties' => [],
							'content' => ['<' . $temp_string . '>'],
						];

						$i++;

						break;
					}

					// If we get here, it's just a regular string.
					$string_part .= '<' . $temp_string;
					break;

				default:
					if ($escaped) {
						if (!in_array($char, self::ESCAPEABLE)) {
							$string_part .= '\\';
						}

						$escaped = false;
					}

					$string_part .= $char;
					break;
			}
		}

		if ($string_part !== '') {
			$new_content[] = $string_part;
		}

		// Amalgamate contiguous plain strings.
		$this->amalgamateStrings($new_content);

		// Filter out empty strings and return.
		return array_values(array_filter($new_content, fn ($arg) => $arg !== ''));
	}

	/**
	 * Parses all other types of inline Markdown syntax.
	 *
	 * @param array $content The content of a paragraph or heading block.
	 */
	protected function parseInlineSecondPass(array $content): array
	{
		$new_content = [];

		$last_c = array_key_last($content);

		foreach ($content as $c => $string) {
			if (!is_string($string)) {
				$new_content[] = $string;
				continue;
			}

			$chars = mb_str_split($string);
			$last_i = array_key_last($chars);

			$escaped = false;
			$string_part = '';

			for ($i = 0; $i < count($chars); $i++) {
				$char = $chars[$i];

				switch ($char) {
					case '\\':
						$escaped = true;
						break;

					case "\n":
						// Don't create hard line breaks at the end of a block.
						if ($c === $last_c && $i === $last_i) {
							$string_part .= $char;
						}
						// Hard line break via an escaped newline.
						elseif ($escaped) {
							if ($string_part !== '') {
								$new_content[] = $string_part;
								$string_part = '';
							}

							$new_content[] = [
								'type' => 'html',
								'properties' => [],
								'content' => ['<br>'],
							];

							$escaped = false;

							while (isset($chars[$i + 1]) && $chars[$i + 1] === ' ') {
								$i++;
							}
						}
						// Hard line break via two or more spaces and then a newline.
						elseif ($i > 2 && $chars[$i - 1] === ' ' && $chars[$i - 2] === ' ') {
							if ($string_part !== '') {
								$new_content[] = Utils::htmlTrimRight($string_part);
								$string_part = '';
							}

							$new_content[] = [
								'type' => 'html',
								'properties' => [],
								'content' => ['<br>'],
							];

							while (isset($chars[$i + 1]) && $chars[$i + 1] === ' ') {
								$i++;
							}
						}
						// Nothing special.
						else {
							$string_part .= $char;
						}
						break;

					case '*':
					case '_':
					case '~':
						if ($escaped) {
							$string_part .= $char;
							$escaped = false;
							break;
						}

						if ($string_part !== '') {
							$new_content[] = $string_part;
						}

						$string_part = $char;

						$start = $i;

						while (isset($chars[$i + 1]) && $chars[$i + 1] === $char) {
							$string_part .= $char;
							$i++;
						}

						// We need more info to make decisions about this run of delimiter chars.
						if (isset($chars[$start - 1])) {
							$prev_char = $chars[$start - 1];
						} elseif (!isset($content[$c - 1])) {
							$prev_char = ' ';
						} else {
							$temp = $content[$c - 1];

							while (isset($temp[array_key_last($temp)]['content'])) {
								$temp = $temp[array_key_last($temp)]['content'];
							}

							if (is_string(end($temp['content']))) {
								$prev_char = mb_substr(end($temp['content']), -1);
							} else {
								$prev_char = ' ';
							}
						}

						if (isset($chars[$i + 1])) {
							$next_char = $chars[$i + 1];
						} elseif (!isset($content[$c + 1])) {
							$next_char = ' ';
						} else {
							$temp = $content[$c + 1];

							while (isset($temp[0]['content'])) {
								$temp = $temp[0]['content'];
							}

							if (is_string(reset($temp['content']))) {
								$next_char = mb_substr(reset($temp['content']), 0, 1);
							} else {
								$next_char = ' ';
							}
						}

						$prev_is_space = preg_match('/\s/u', $prev_char);
						$prev_is_punct = $prev_is_space ? false : preg_match('/\pP/u', $prev_char);

						$next_is_space = preg_match('/\s/u', $next_char);
						$next_is_punct = $next_is_space ? false : preg_match('/\pP/u', $next_char);

						$left_flanking = !$next_is_space && (!$next_is_punct || $prev_is_space || $prev_is_punct);
						$right_flanking = !$prev_is_space && (!$prev_is_punct || $next_is_space || $next_is_punct);

						$can_open = $left_flanking && ($char === '*' || !$right_flanking || $prev_is_punct);
						$can_close = $right_flanking && ($char === '*' || !$left_flanking || $next_is_punct);

						$length = strlen($string_part);

						// Max length of strikethrough delimiter is two chars.
						if ($char === '~' && $length > 2) {
							$new_content[array_key_last($new_content)] .= $string_part;
							$string_part = '';
							break;
						}

						// Add a node for this delimiter run.
						$new_content[] = [
							'type' => $char,
							'properties' => [
								'length' => $length,
								'active' => true,
								'can_open' => $can_open,
								'can_close' => $can_close,
								'position' => $i,
							],
							'content' => $string_part,
						];

						$string_part = '';

						break;

					case '!':
						if ($escaped || ($chars[$i + 1] ?? '') !== '[') {
							$string_part .= $char;
							$escaped = false;
							break;
						}

						$i++;

						if ($string_part !== '') {
							$new_content[] = $string_part;
							$string_part = '';
						}

						$new_content[] = [
							'type' => '![',
							'properties' => [
								'length' => 2,
								'active' => true,
								'can_open' => true,
								'can_close' => false,
								'position' => $i,
							],
							'content' => '![',
						];

						break;

					case '[':
						if ($escaped) {
							$string_part .= '[';
							$escaped = false;
							break;
						}

						if ($string_part !== '') {
							$new_content[] = $string_part;
							$string_part = '';
						}

						$new_content[] = [
							'type' => '[',
							'properties' => [
								'length' => 1,
								'active' => true,
								'can_open' => true,
								'can_close' => false,
								'position' => $i,
							],
							'content' => '[',
						];

						break;

					case ']':
						if ($escaped) {
							$string_part .= ']';
							$escaped = false;
							break;
						}

						if ($string_part !== '') {
							$new_content[] = $string_part;
							$string_part = '';
						}

						$this->parseLink($chars, $i, $new_content);

						break;

					default:
						if ($escaped) {
							if (!in_array($char, self::ESCAPEABLE)) {
								$string_part .= '\\';
							}

							$escaped = false;
						}

						$string_part .= $char;
						break;
				}
			}

			if ($string_part !== '') {
				$new_content[] = $string_part;
			}
		}

		// Change any remaining link/image delimiters into plain text.
		foreach ($new_content as $key => $value) {
			if (is_array($value) && in_array($value['type'], ['[', '!['])) {
				$new_content[$key] = $value['content'];
			}
		}

		// Amalgamate contiguous plain strings.
		$this->amalgamateStrings($new_content);

		// Clean up the keys.
		$new_content = array_values($new_content);

		// Process the emphasis and strikethrough delimiters.
		$this->parseEmphasis($new_content);

		// Clean up the keys again.
		$new_content = array_values($new_content);

		// Trim whitespace from the beginning of the paragraph.
		if (is_string($new_content[0])) {
			$new_content[0] = Utils::htmlTrimLeft($new_content[0]);
		}

		// Trim whitespace from the end of the paragraph.
		if (is_string($new_content[array_key_last($new_content)])) {
			$new_content[array_key_last($new_content)] = Utils::htmlTrimRight($new_content[array_key_last($new_content)]);
		}

		return $new_content;
	}

	/**
	 * Parses Markdown links.
	 *
	 * @param array $chars The characters of the line in which the link occurs.
	 * @param int &$i The index of the current character within $chars.
	 * @param array &$content The 'content' array of the block in which the link
	 *     occurs.
	 */
	protected function parseLink(array $chars, int &$i, array &$content): void
	{
		for ($c = array_key_last($content); $c >= 0; $c--) {
			if (
				!isset($content[$c])
				|| !is_array($content[$c])
				|| !in_array($content[$c]['type'], ['[', '!['])
			) {
				continue;
			}

			$delim = &$content[$c];

			$str = implode('', array_slice($chars, $delim['properties']['position'], $i - $delim['properties']['position'])) . ']' . mb_substr(implode('', $chars), $i + 1);

			// Inline link/image?
			if (preg_match('~^' . self::REGEX_LINK_INLINE . '~u', $str, $matches)) {
				$this->parseEmphasis($content, $c);

				$text = array_slice($content, $c + 1);
				$this->amalgamateStrings($text);

				$url = $this->extractLinkUrl($matches['destination'] ?? '');
				$title = $this->extractLinkTitle($matches['title'] ?? '');

				$content = array_slice($content, 0, $c);
				$content[] = [
					'type' => $delim['type'] === '![' ? 'image' : 'link',
					'properties' => [
						'url' => $url,
						'title' => $title,
					],
					'content' => $text,
				];

				$i += mb_strlen($matches[0]) - mb_strlen($matches['text']);

				return;
			}

			// Reference link/image? Check for all three possible forms.
			foreach ([
				self::REGEX_LINK_REF_FULL,
				self::REGEX_LINK_REF_COLLAPSED,
				self::REGEX_LINK_REF_SHORTCUT,
			] as $regex) {
				if (preg_match('~' . $regex . '~u', $str, $matches)) {
					break;
				}
			}

			// If none of the forms matched, move on.
			if (empty($matches)) {
				$delim = $delim['content'];
				$content[] = ']';

				return;
			}

			$label_content = $this->extractLinkLabel($matches['label']);

			if (isset($this->link_reference_definitions[$label_content])) {
				$this->parseEmphasis($content, $c);

				$text = array_slice($content, $c + 1);
				$this->amalgamateStrings($text);

				$url = $this->link_reference_definitions[$label_content]['url'];
				$title = $this->link_reference_definitions[$label_content]['title'];

				$content = array_slice($content, 0, $c);
				$content[] = [
					'type' => $delim['type'] === '![' ? 'image' : 'link',
					'properties' => [
						'url' => $url,
						'title' => $title,
					],
					'content' => $text,
				];

				$i += mb_strlen($matches[0]) - mb_strlen($matches['text'] ?? $matches['label']);
			} else {
				$delim = $delim['content'];
				$content[] = ']';
			}

			return;
		}

		// If we get here, we didn't find a valid opening delimiter.
		$content[] = ']';
	}

	/**
	 * Parses Markdown emphasis and strikethrough text.
	 *
	 * @param array &$content The 'content' array of the block in which the
	 *    emphasis occurs.
	 * @param int $start_after Parsing will only happen in elements of $content
	 *    whose keys are greater than this. Used to avoid parsing in parts of a
	 *    string that have already been parsed. Default: -1.
	 */
	protected function parseEmphasis(array &$content, int $start_after = -1): void
	{
		foreach ([false, true] as $allow_partial) {
			$content = array_values($content);

			foreach ($content as $key => $node) {
				if (is_array($node) && in_array($node['type'], ['*', '_', '~'])) {
					$content[$key]['properties']['active'] = true;
				}
			}

			// Walk forward in the list until we reach the first item past $start_after.
			reset($content);

			while (key($content) !== null && key($content) <= $start_after) {
				next($content);
			}

			while (key($content) !== null) {
				unset($closer, $closer_key, $opener, $opener_key);

				// Walk forward until we find a potential closing delimiter run.
				while (key($content) !== null) {
					$node = current($content);

					if (
						is_array($node)
						&& in_array($node['type'], ['*', '_', '~'])
						&& $node['properties']['can_close']
						&& $node['properties']['active']
					) {
						break;
					}

					next($content);
				}

				if (
					is_array($node)
					&& in_array($node['type'], ['*', '_', '~'])
					&& $node['properties']['can_close']
					&& $node['properties']['active']
				) {
					$closer_key = key($content);
					$closer = &$content[$closer_key];
				} else {
					continue;
				}

				// Walk back until we find a matching opening delimiter run.
				do {
					$node = prev($content);

					if (key($content) === null || key($content) === $start_after) {
						$closer['properties']['active'] = false;

						reset($content);

						while (
							key($content) <= $closer_key
							&& key($content) < array_key_last($content)
						) {
							next($content);
						}

						continue 2;
					}
				} while (
					key($content) !== null
					&& key($content) > $start_after
					&& !(
						is_array($node)
						&& $node['type'] === $closer['type']
						&& $node['properties']['can_open']
						&& (
							$allow_partial
							|| $this->checkRuleOfThree($node, $closer)
						)
					)
				);

				if (is_array($node) && key($content) > $start_after) {
					$opener_key = key($content);
					$opener = &$content[$opener_key];
				} else {
					continue;
				}

				// Build the new version of the content.
				$max_length = min($opener['properties']['length'], $closer['properties']['length'], 2);
				$enclosed = array_slice($content, $opener_key + 1, $closer_key - $opener_key - 1);

				$temp = array_slice($content, 0, $opener_key);

				if ($opener['properties']['length'] > $max_length) {
					$new_opener = $opener;

					$new_opener['properties']['length'] -= $max_length;
					$new_opener['content'] = substr($new_opener['content'], $max_length);

					$temp[] = $new_opener;
				}

				foreach ($enclosed as $k => $n) {
					if (is_array($n) && in_array($n['type'], ['*', '_', '~'])) {
						$enclosed[$k] = $n['content'];
					}
				}

				$temp[] = [
					'type' => $opener['type'] === '~' ? 's' : ($max_length === 1 ? 'i' : 'b'),
					'properties' => [],
					'content' => $enclosed,
				];

				if ($closer['properties']['length'] > $max_length) {
					$new_closer = $closer;

					$new_closer['properties']['length'] -= $max_length;
					$new_closer['content'] = substr($new_closer['content'], $max_length);

					$temp[] = $new_closer;
				}

				$content = array_values(array_merge($temp, array_slice($content, $closer_key + 1)));

				// Move the internal pointer to position just after the closer.
				reset($content);

				while (key($content) < $closer_key) {
					next($content);

					if (key($content) === null) {
						break;
					}
				}
			}
		}

		foreach ($content as $key => $node) {
			if (is_array($node) && in_array($node['type'], ['*', '_', '~'])) {
				$content[$key] = $node['content'];
			}
		}

		$content = array_values(array_filter($content, fn ($arg) => $arg !== ''));
	}

	/**
	 * Helper method for $this->parseEmphasis().
	 *
	 * https://github.github.com/gfm/#emphasis-and-strong-emphasis:
	 *
	 * "If one of the delimiters can both open and close emphasis, then the sum
	 * of the lengths of the delimiter runs containing the opening and closing
	 * delimiters must not be a multiple of 3 unless both lengths are multiples
	 * of 3."
	 *
	 * This rule is used to ensure that, e.g., `*foo**bar**baz*` becomes
	 * `<i>foo<b>bar</b>baz</i>` rather than `<i>foo</i><i>bar</i><i>baz</i>`.
	 *
	 * @param array $opener Info about opening run of delimiter characters.
	 * @param array $closer Info about closing run of delimiter characters.
	 * @return bool Whether this combination of opener and closer is allowed.
	 */
	protected function checkRuleOfThree(array $opener, array $closer): bool
	{
		// This rule doesn't apply to strikethrough text.
		if ($opener['type'] === '~') {
			return true;
		}

		if (($opener['properties']['length'] + $closer['properties']['length']) % 3 !== 0) {
			return true;
		}

		return $opener['properties']['length'] % 3 === 0 && $closer['properties']['length'] % 3 === 0;
	}

	/**
	 * Extracts and normalizes the text for a link label.
	 *
	 * Note that link labels and link text might look identical a first glance,
	 * but are not quite the same thing.
	 *
	 * A link label is the part between square brackets in a link reference.
	 * Because link labels are used to match link references to link reference
	 * definitions, they are normalized for both case and white space so that
	 * the matching can happen while ignoring case and white space.
	 *
	 * Link text is the part between square brackets in an inline link. Because
	 * link text never needs to match with anything else, it does not need to be
	 * normalized for case or white space.
	 *
	 * @param string $label The link's label component, including syntax chars.
	 * @return string The normalized text of the link's label.
	 */
	protected function extractLinkLabel(string $label = ''): string
	{
		return Utils::convertCase(preg_replace('/\s+/u', ' ', Utils::htmlTrim(substr($label, 1, -1))), 'fold');
	}

	/**
	 * Extracts the URL for a link.
	 *
	 * @param string $destination The link's URL component, including syntax chars.
	 * @return string The text of the link's URL.
	 */
	protected function extractLinkUrl(string $destination = ''): string
	{
		return Utils::htmlTrim(trim($destination, '<>'));
	}

	/**
	 * Extracts the title for a link.
	 *
	 * @param string $title The link's title component, including syntax chars.
	 * @return string The text of the link's title.
	 */
	protected function extractLinkTitle(string $title = ''): string
	{
		if (strlen($title) < 2) {
			return $title;
		}

		return substr(Utils::htmlTrim($title), 1, -1);
	}

	/**
	 * Combines contiguous strings within a block's content.
	 *
	 * @param array &$content The 'content' array of the block in which the
	 *    strings occur.
	 */
	protected function amalgamateStrings(array &$content): void
	{
		for ($i = 0; $i < count($content); $i++) {
			if (!isset($content[$i]) || !is_string($content[$i])) {
				continue;
			}

			$c = $i;

			while (isset($content[$i + 1]) && is_string($content[$i + 1])) {
				$content[$c] .= $content[++$i];
				unset($content[$i]);
			}
		}

		$content = array_values($content);
	}

	/*
	 * Part 3: Rendering.
	 */

	/**
	 * Transforms a block or string into its final output form and appends it
	 * to $this->rendered.
	 *
	 * If $element is a block from $this->structure (or is $this->structure
	 * itself), this method will recurse into $element's children.
	 *
	 * @param array|string $element A block or string to render.
	 */
	protected function render(array|string $element): void
	{
		// Is it a string?
		if (is_string($element)) {
			if ($this->output_type !== self::OUTPUT_BBC) {
				$element = htmlspecialchars($element, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, Utils::$context['character_set'], false);

				if ($this->hard_breaks & self::BR_IN_PARAGRAPHS) {
					$element = nl2br($element, false);
				}
			} elseif (!($this->hard_breaks & self::BR_IN_PARAGRAPHS)) {
				$element = strtr($element, ["\n" => ' ']);
			}

			$this->rendered .= $element;

			return;
		}

		// Do we have a rendering method for this element?
		if (isset($this->render_methods[$element['type']])) {
			$render_method = method_exists($this, $this->render_methods[$element['type']]) ? [$this, $this->render_methods[$element['type']]] : Utils::getCallable($this->render_methods[$element['type']]);

			if (is_callable($render_method)) {
				$render_method($element);

				if ($this->output_type === self::OUTPUT_BBC) {
					$this->rendered .= str_repeat("\n", $element['properties']['blank_after'] ?? 0);
				} elseif ($this->hard_breaks & self::BR_LINES) {
					$this->rendered .= str_repeat("<br>\n", max(0, ($element['properties']['blank_after'] ?? 1) - 1));
				}

				return;
			}
		}

		// No rendering method for the element itself, so recurse into the content.
		foreach ($element['content'] as $content_element) {
			$this->render($content_element);
		}
	}

	/**
	 * Renders code blocks.
	 *
	 * @param array $element A code block to render.
	 */
	protected function renderCodeBlock(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if ($element['content'] === []) {
					return;
				}

				$this->rendered .= '[code]';
				break;

			case self::OUTPUT_HTML:
				if ($element['content'] === []) {
					return;
				}

				$bbc_type = !empty($element['properties']['info_string']) ? 'unparsed_equals_content' : 'unparsed_content';

				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'code' && $code['type'] === $bbc_type) {
						list($before, $after) = preg_split('/\$1/', $code['content']);
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= strtr(
					$before,
					[
						'{txt_code}' => Lang::getTxt('code'),
						'{txt_code_select}' => Lang::getTxt('code_select'),
						'{txt_code_shrink}' => Lang::getTxt('code_shrink'),
						'{txt_code_expand}' => Lang::getTxt('quote_expand'),
						'$2' => htmlspecialchars($element['properties']['info_string'] ?? ''),
					],
				);
				break;

			default:
				$info_string_words = Utils::extractWords($element['properties']['info_string'] ?? '');

				$this->rendered .= '<pre><code' . (!empty($element['properties']['info_string']) ? ' class="language-' . htmlspecialchars(Utils::strtolower(reset($info_string_words))) . '"' : '') . '>';
				break;
		}

		$code_start = mb_strlen($this->rendered);

		foreach ($element['content'] as $c => $content_element) {
			$this->render($content_element);

			if ($c !== array_key_last($element['content'])) {
				$this->rendered .= "\n";
			}
		}

		// Why not do some syntax highlighting?
		if (
			$this->output_type === self::OUTPUT_HTML
			&& strtoupper($element['properties']['info_string'] ?? '') === 'PHP'
		) {
			$code = mb_substr($this->rendered, $code_start);

			$add_begin = !str_starts_with(Utils::htmlTrim($code), '&lt;?');

			$code = BBCodeParser::highlightPhpCode($add_begin ? '&lt;?php ' . $code . '?&gt;' : $code);

			if ($add_begin) {
				$code = preg_replace(['/^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)/u', '/\?&gt;((?:\s*<\/(font|span)>)*)$/um'], '$1', $code, 2);
			}

			$this->rendered = mb_substr($this->rendered, 0, $code_start) . $code;
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/code]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $after;
				break;

			default:
				$this->rendered .= "\n";
				$this->rendered .= '</code></pre>';
				break;
		}

		$this->rendered .= "\n";
	}

	/**
	 * Renders blockquotes.
	 *
	 * @param array $element A blockquote to render.
	 */
	protected function renderBlockquote(array $element): void
	{
		static $nesting_level = 0;

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if ($element['content'] === []) {
					return;
				}

				$this->rendered .= '[quote]';
				break;

			case self::OUTPUT_HTML:
				if ($element['content'] === []) {
					return;
				}

				foreach (BBCodeParser::getCodes() as $code) {
					if (
						$code['tag'] === 'quote'
						&& !isset($code['type'])
						&& !isset($code['parameters'])
					) {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				// Add a class to the quote to style nested blockquotes.
				$code['before'] = strtr($code['before'], ['<blockquote>' => '<blockquote class="bbc_' . ($nesting_level % 2 === 1 ? 'alternate' : 'standard') . '_quote">']);

				$this->rendered .= strtr($code['before'], ['{txt_quote}' => Lang::getTxt('quote')]);
				break;

			default:
				$this->rendered .= '<blockquote>';
				break;
		}

		$this->rendered .= "\n";

		// Don't bother with single paragraphs inside blockquotes.
		if (
			count($element['content']) === 1
			&& is_array($element['content'][0])
			&& $element['content'][0]['type'] === 'p'
		) {
			$element['content'] = $element['content'][0]['content'];
		} elseif ($this->output_type !== self::OUTPUT_BBC) {
			$this->rendered .= "\n";
		}

		foreach ($element['content'] as $c => $content_element) {
			$nesting_level++;
			$this->render($content_element);
			$nesting_level--;

			if (
				$this->rendered === Utils::htmlTrimRight($this->rendered)
				&& $c !== array_key_last($element['content'])
				&& is_string($element['content'][$c])
				&& is_string($element['content'][$c + 1])
				&& $element['content'][$c + 1] === Utils::htmlTrimLeft($element['content'][$c + 1])
			) {
				$this->rendered .= ' ';
			}
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/quote]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				break;

			default:
				$this->rendered .= '</blockquote>';
				break;
		}

		$this->rendered .= "\n";
	}

	/**
	 * Renders lists.
	 *
	 * @param array $element A list to render.
	 */
	protected function renderList(array $element): void
	{
		static $nesting_level = 0;

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if ($element['content'] === []) {
					return;
				}

				$this->rendered .= '[list' . ($element['properties']['ordered'] ? ' type=decimal' : '') . ']';
				break;

			case self::OUTPUT_HTML:
				if ($element['content'] === []) {
					return;
				}

				$ordered_styles = ['decimal', 'lower-roman', 'lower-alpha'];
				$unordered_styles = ['disc', 'circle', 'square'];

				$style_type = $element['properties']['ordered'] ? $ordered_styles[$nesting_level % 3] : $unordered_styles[$nesting_level % 3];

				foreach (BBCodeParser::getCodes() as $code) {
					if (
						$code['tag'] === 'list'
						&& isset($code['parameters']['type'])
						&& str_contains($code['parameters']['type']['match'], $style_type)
					) {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= strtr($code['before'], ['{type}' => $style_type]);
				break;

			default:
				$this->rendered .= $element['properties']['ordered'] ? '<ol>' : '<ul>';
				break;
		}

		$this->rendered .= "\n";

		foreach ($element['content'] as $content_element) {
			$nesting_level++;
			$this->render($content_element);
			$nesting_level--;
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/list]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				break;

			default:
				$this->rendered .= $element['properties']['ordered'] ? '</ol>' : '</ul>';
				break;
		}

		$this->rendered .= "\n";
	}

	/**
	 * Renders list items.
	 *
	 * @param array $element A list item to render.
	 */
	protected function renderListItem(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if ($element['content'] === []) {
					return;
				}

				$this->rendered .= '[li]';
				break;

			case self::OUTPUT_HTML:
				if ($element['content'] === []) {
					return;
				}

				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'li') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= $code['before'];
				break;

			default:
				$this->rendered .= '<li>';
				break;
		}

		// Don't bother with single paragraphs inside list items.
		if (
			count($element['content']) === 1
			&& is_array($element['content'][0])
			&& $element['content'][0]['type'] === 'p'
		) {
			$element['content'] = $element['content'][0]['content'];
		} elseif ($this->output_type !== self::OUTPUT_BBC) {
			$this->rendered .= "\n";
		}

		foreach ($element['content'] as $c => $content_element) {
			$this->render($content_element);

			if ($c === array_key_last($element['content'])) {
				if ($this->output_type === self::OUTPUT_BBC) {
					$this->rendered = Utils::htmlTrimRight($this->rendered);
				}
			} elseif (
				$this->rendered === Utils::htmlTrimRight($this->rendered)
				&& is_string($element['content'][$c])
				&& is_string($element['content'][$c + 1])
				&& $element['content'][$c + 1] === Utils::htmlTrimLeft($element['content'][$c + 1])
			) {
				$this->rendered .= ' ';
			}
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/li]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				break;

			default:
				$this->rendered .= '</li>';
				break;
		}

		$this->rendered .= "\n";
	}

	/**
	 * Renders thematic breaks.
	 *
	 * @param array $element A thematic break to render.
	 */
	protected function renderHr(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[hr]';
				break;

			case self::OUTPUT_HTML:
				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'hr') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= $code['content'];
				break;

			default:
				$this->rendered .= '<hr>';
				break;
		}

		$this->rendered .= "\n";
	}

	/**
	 * Renders headings.
	 *
	 * @param array $element A heading to render.
	 */
	protected function renderHeading(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if ($element['content'] === []) {
					return;
				}

				$this->rendered .= '[h' . $element['properties']['level'] . ']';
				break;

			case self::OUTPUT_HTML:
				if ($element['content'] === []) {
					return;
				}

				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'h' . $element['properties']['level']) {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= $code['before'];
				break;

			default:
				$this->rendered .= '<h' . $element['properties']['level'] . '>';
				break;
		}

		// Don't bother with paragraphs inside headings.
		if (
			count($element['content']) === 1
			&& is_array($element['content'][0])
			&& $element['content'][0]['type'] === 'p'
		) {
			$element['content'] = $element['content'][0]['content'];
		}

		foreach ($element['content'] as $c => $content_element) {
			$this->render($content_element);

			if (
				$this->rendered === Utils::htmlTrimRight($this->rendered)
				&& $c !== array_key_last($element['content'])
				&& is_string($element['content'][$c])
				&& is_string($element['content'][$c + 1])
				&& $element['content'][$c + 1] === Utils::htmlTrimLeft($element['content'][$c + 1])
			) {
				$this->rendered .= ' ';
			}
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/h' . $element['properties']['level'] . ']';
				break;

			case self::OUTPUT_HTML:
				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'h' . $element['properties']['level']) {
						break;
					}
				}

				$this->rendered .= $code['after'];
				$this->rendered .= "\n";
				break;

			default:
				$this->rendered .= '</h' . $element['properties']['level'] . '>';
				break;
		}

		$this->rendered .= "\n";
	}

	/**
	 * Renders HTML blocks.
	 *
	 * @param array $element An HTML block to render.
	 */
	protected function renderHtmlBlock(array $element): void
	{
		$this->rendered .= implode("\n", $element['content']) . "\n";
	}

	/**
	 * Renders inline HTML.
	 *
	 * @param array $element Some inline HTML to render.
	 */
	protected function renderHtmlInline(array $element): void
	{
		$this->rendered .= implode("\n", $element['content']);
	}

	/**
	 * Renders tables.
	 *
	 * @param array $element A table to render.
	 */
	protected function renderTable(array $element): void
	{
		$is_disabled = false;

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if ($element['content'] === []) {
					return;
				}

				$this->rendered .= '[table]';
				break;

			case self::OUTPUT_HTML:
				if ($element['content'] === []) {
					return;
				}

				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'table') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
					$is_disabled = true;
				}

				$this->rendered .= $code['before'];
				break;

			default:
				$this->rendered .= '<table>';
				break;
		}

		$this->rendered .= "\n";

		foreach ($element['content'] as $key => $row) {
			if ($this->output_type !== self::OUTPUT_BBC && $key < 2 && !$is_disabled) {
				$this->rendered .= $key === 0 ? '<thead>' : '<tbody>';
				$this->rendered .= "\n";
			}

			if (!$is_disabled) {
				$this->rendered .= $this->output_type === self::OUTPUT_BBC ? '[tr]' : '<tr>';
				$this->rendered .= "\n";
			}

			foreach ($row as $col => $cell) {
				$cell['properties']['th'] = $this->output_type !== self::OUTPUT_BBC && $key === 0;
				$cell['properties']['align'] = $element['properties']['align'][$col] ?? 'none';

				$this->render($cell);
			}

			if (!$is_disabled) {
				$this->rendered .= $this->output_type === self::OUTPUT_BBC ? '[/tr]' : '</tr>';
				$this->rendered .= "\n";
			}

			if ($this->output_type !== self::OUTPUT_BBC && !$is_disabled) {
				$this->rendered .= $key === 0 ? '</thead>' . "\n" : '';
			}
		}

		if ($this->output_type !== self::OUTPUT_BBC && !$is_disabled) {
			$this->rendered .= '</tbody>';
			$this->rendered .= "\n";
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[table]';
				$this->rendered .= "\n";
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				$this->rendered .= "\n";
				break;

			default:
				$this->rendered .= '<table>';
				$this->rendered .= "\n";
				break;
		}
	}

	/**
	 * Renders table cells.
	 *
	 * @param array $element A table cell to render.
	 */
	protected function renderTableCell(array $element): void
	{
		$is_disabled = $this->output_type === self::OUTPUT_HTML && isset($this->disabled['td']);

		$tag = !empty($element['properties']['th']) ? 'th' : 'td';
		$align = $element['properties']['align'] === 'none' ? null : $element['properties']['align'];

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[td]';

				if (in_array($element['properties']['align'], ['left', 'right', 'center'])) {
					$this->rendered .= '[' . $element['properties']['align'] . 'text]';
				}
				break;

			case self::OUTPUT_HTML:
				if ($is_disabled) {
					break;
				}
				// no break

			default:
				$this->rendered .= '<' . $tag;

				if (in_array($element['properties']['align'], ['left', 'right', 'center'])) {
					$this->rendered .= ' style="text-align: ' . $element['properties']['align'] . '"';
				}

				$this->rendered .= '>';
				break;
		}

		foreach ($element['content'] as $c => $content_element) {
			$this->render($content_element);

			if (
				$this->rendered === Utils::htmlTrimRight($this->rendered)
				&& $c !== array_key_last($element['content'])
				&& is_string($element['content'][$c])
				&& is_string($element['content'][$c + 1])
				&& $element['content'][$c + 1] === Utils::htmlTrimLeft($element['content'][$c + 1])
			) {
				$this->rendered .= ' ';
			}
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if (in_array($element['properties']['align'], ['left', 'right', 'center'])) {
					$this->rendered .= '[/' . $element['properties']['align'] . 'text]';
				}

				$this->rendered .= '[/td]';
				break;

			case self::OUTPUT_HTML:
				if ($is_disabled) {
					break;
				}
				// no break

			default:
				$this->rendered .= '</' . $tag . '>';
				break;
		}

		$this->rendered .= "\n";
	}

	/**
	 * Renders paragraphs.
	 *
	 * @param array $element A paragraph to render.
	 */
	protected function renderParagraph(array $element): void
	{
		if ($element['content'] === []) {
			return;
		}

		$this->rendered .= $this->output_type === self::OUTPUT_BBC ? '' : '<p>';

		foreach ($element['content'] as $c => $content_element) {
			$this->render($content_element);

			if (
				$this->rendered === Utils::htmlTrimRight($this->rendered)
				&& $c !== array_key_last($element['content'])
				&& is_string($element['content'][$c])
				&& is_string($element['content'][$c + 1])
				&& $element['content'][$c + 1] === Utils::htmlTrimLeft($element['content'][$c + 1])
			) {
				$this->rendered .= ' ';
			}
		}

		$this->rendered = Utils::htmlTrimRight($this->rendered);
		$this->rendered .= $this->output_type === self::OUTPUT_BBC ? '' : '</p>';
		$this->rendered .= "\n";
	}

	/**
	 * Renders links.
	 *
	 * @param array $element A link to render.
	 */
	protected function renderLink(array $element): void
	{
		$bbc = str_starts_with($element['properties']['url'], Config::$boardurl) ? 'iurl' : 'url';

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if (empty($element['properties']['url']) || empty($element['content'])) {
					return;
				}

				$this->rendered .= '[' . $bbc . '="' . $element['properties']['url'] . '"]';
				break;

			case self::OUTPUT_HTML:
				if (empty($element['properties']['url']) || empty($element['content'])) {
					return;
				}

				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === $bbc && $code['type'] === 'unparsed_equals') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= strtr($code['before'], ['$1' => $element['properties']['url']]);
				break;

			default:
				$this->rendered .= '<a href="' . ($element['properties']['url'] ?? '') . '"' . (strlen($element['properties']['title'] ?? '') > 0 ? ' title="' . $element['properties']['title'] . '"' : '') . '>';
				break;
		}

		foreach ($element['content'] as $content_element) {
			$this->render($content_element);
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/' . $bbc . ']';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= strtr($code['after'], ['$1' => $element['properties']['url']]);
				break;

			default:
				$this->rendered .= '</a>';
				break;
		}
	}

	/**
	 * Renders images.
	 *
	 * @param array $element An image to render.
	 */
	protected function renderImage(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				if (empty($element['properties']['url'])) {
					return;
				}

				$this->rendered .= '[img]' . $element['properties']['url'] . '[/img]';
				break;

			case self::OUTPUT_HTML:
				if (empty($element['properties']['url'])) {
					return;
				}

				$this->rendered .= BBCodeParser::load()->parse('[img]' . $element['properties']['url'] . '[/img]');
				break;

			default:
				$this->rendered .= '<img src="' . ($element['properties']['url'] ?? '') . '"' . (strlen($element['properties']['title'] ?? '') > 0 ? ' title="' . $element['properties']['title'] . '"' : '') . '>';
				break;
		}
	}

	/**
	 * Renders inline code.
	 *
	 * @param array $element A span of inline code to render.
	 */
	protected function renderInlineCode(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[tt]';
				break;

			case self::OUTPUT_HTML:
				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'tt') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= $code['before'];
				break;

			default:
				$this->rendered .= '<code>';
				break;
		}

		foreach ($element['content'] as $content_element) {
			$this->render($content_element);
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/tt]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				break;

			default:
				$this->rendered .= '</code>';
				break;
		}
	}

	/**
	 * Renders emphasis.
	 *
	 * @param array $element A span of emphasized text to render.
	 */
	protected function renderEm(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[i]';
				break;

			case self::OUTPUT_HTML:
				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'i') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= $code['before'];
				break;

			default:
				$this->rendered .= '<em>';
				break;
		}

		foreach ($element['content'] as $content_element) {
			$this->render($content_element);
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/i]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				break;

			default:
				$this->rendered .= '</em>';
				break;
		}
	}

	/**
	 * Renders strong emphasis.
	 *
	 * @param array $element A span of strongly emphasized text to render.
	 */
	protected function renderStrong(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[b]';
				break;

			case self::OUTPUT_HTML:
				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 'b') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= $code['before'];
				break;

			default:
				$this->rendered .= '<strong>';
				break;
		}

		foreach ($element['content'] as $content_element) {
			$this->render($content_element);
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/b]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				break;

			default:
				$this->rendered .= '</strong>';
				break;
		}
	}

	/**
	 * Renders strikethrough text.
	 *
	 * @param array $element A span of strikethrough text to render.
	 */
	protected function renderStrikethrough(array $element): void
	{
		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[s]';
				break;

			case self::OUTPUT_HTML:
				foreach (BBCodeParser::getCodes() as $code) {
					if ($code['tag'] === 's') {
						break;
					}
				}

				if (isset($this->disabled[$code['tag']])) {
					$code = $this->disableCode($code);
				}

				$this->rendered .= $code['before'];
				break;

			default:
				$this->rendered .= '<del>';
				break;
		}

		foreach ($element['content'] as $content_element) {
			$this->render($content_element);
		}

		switch ($this->output_type) {
			case self::OUTPUT_BBC:
				$this->rendered .= '[/s]';
				break;

			case self::OUTPUT_HTML:
				$this->rendered .= $code['after'];
				break;

			default:
				$this->rendered .= '</del>';
				break;
		}
	}

	/*
	 * Miscellaneous.
	 */

	/**
	 * Helper method that returns the requested callable.
	 *
	 * This is used to allow $this->block_types to define the necessary methods
	 * for working with various block types.
	 *
	 * @param string|bool|null $method Either (a) a boolean to return, (b) null
	 *     to do nothing, or (c) the name of a method, possibly prepended by '!'
	 *     if the boolean inverse of the method's results are desired.
	 * @return A callable, a boolean, or null.
	 */
	protected function getMethod(string|bool|null $method): mixed
	{
		if (is_null($method)) {
			return null;
		}

		if (is_bool($method)) {
			return function (...$args) use ($method) {
				return $method;
			};
		}

		$not = str_starts_with($method, '!');

		$method = ltrim($method, '!');

		if (!method_exists($this, $method)) {
			return false;
		}

		if ($not) {
			return function (...$args) use ($method) {
				return !($this->$method(...$args));
			};
		} else {
			return [$this, $method];
		}
	}

	/**
	 * Resets runtime properties to their default values.
	 */
	protected function resetRuntimeProperties(): void
	{
		// Reset these properties.
		$to_reset = [
			'line_info',
			'structure',
			'open',
			'last_block',
			'link_reference_definitions',
			'in_code',
			'opening_fence_linenum',
			'opening_fence',
			'info_string',
			'in_html',
			'table_align',
			'placeholders',
			'rendered',
		];

		$class_vars = get_class_vars(__CLASS__);

		foreach ($to_reset as $var) {
			unset($this->{$var});
			$this->{$var} = $class_vars[$var];
		}

		// Ensure p is always the last element in $this->block_types.
		if (array_key_last($this->block_types) !== 'p') {
			$p = $this->block_types['p'];
			unset($this->block_types['p']);
			$this->block_types['p'] = $p;
		}
	}
}

?>