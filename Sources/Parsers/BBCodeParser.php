<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Parsers;

use SMF\Autolinker;
use SMF\BBCode\{
	BBCodeInterface,
	GenericBBCode,
	Li,
	List1,
};
use SMF\BrowserDetector;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Parser;
use SMF\Sapi;
use SMF\Theme;
use SMF\Time;
use SMF\Url;
use SMF\Utils;

/**
 * Parses Bulletin Board Code in a string and converts it to HTML.
 */
class BBCodeParser extends Parser
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var ?string
	 *
	 * Regular expression to match all BBCode tags.
	 */
	protected ?string $alltags_regex = null;

	/**
	 * @var bool
	 *
	 * Whether smileys should be parsed while we are parsing BBCode.
	 */
	protected bool $smileys = true;

	/**
	 * @var array
	 *
	 * Version of self::$codes used for internal processing.
	 */
	private array $bbc_codes = [];

	/**
	 * @var array
	 *
	 * Copies of $this->bbc_codes for different locales.
	 */
	private array $bbc_lang_locales = [];

	/**
	 * @var string
	 *
	 * The string in which to parse BBCode.
	 */
	private string $message = '';

	/**
	 * @var array
	 *
	 * BBCodes that are currently open at any given step of processing
	 * $this->message.
	 */
	private array $open_bbc = [];

	/**
	 * @var ?BBCodeInterface
	 *
	 * The last item of $this->open_bbc.
	 */
	private ?BBCodeInterface $inside = null;

	/**
	 * @var int|bool
	 *
	 * Current position in $this->message.
	 */
	private int|bool $pos = -1;

	/**
	 * @var ?int
	 *
	 * Position where current BBCode tag ends.
	 */
	private ?int $pos1 = null;

	/**
	 * @var int
	 *
	 * Previous value of $this->pos.
	 */
	private ?int $last_pos = null;

	/**
	 * @var array
	 *
	 * Placeholders used to protect certain strings from processing.
	 */
	private array $placeholders = [];

	/**
	 * @var int
	 *
	 * How many placeholders we have created.
	 */
	private int $placeholders_counter = 0;

	/**
	 * @var string
	 *
	 * The sprintf format used to create placeholders.
	 * Uses private use Unicode characters to prevent conflicts.
	 */
	private string $placeholder_template = "\u{E03C}" . '%1$s' . "\u{E03E}";

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Definitions of supported BBCodes.
	 *
	 * In SMF 3.0+, the preferred way to define a BBCode is to create a class
	 * that extends the SMF\BBCode\BBCode class. When this approach is taken,
	 * the definition in this list for the BBCode is an array with a single key,
	 * 'class', whose value is the appropriate class name.
	 *
	 * However, for the sake of backward compatibility it is still possible to
	 * to define a BBCode the way it was done in previous versions of SMF by
	 * using an array of information with keys as follows:
	 *
	 * 	tag: The tag's name - should be lowercase!
	 *
	 *	type: One of...
	 *		- (missing): [tag]parsed content[/tag]
	 *		- unparsed_equals: [tag=xyz]parsed content[/tag]
	 *		- parsed_equals: [tag=parsed data]parsed content[/tag]
	 *		- unparsed_content: [tag]unparsed content[/tag]
	 *		- closed: [tag], [tag/], [tag /]
	 *		- unparsed_commas: [tag=1,2,3]parsed content[/tag]
	 *		- unparsed_commas_content: [tag=1,2,3]unparsed content[/tag]
	 *		- unparsed_equals_content: [tag=...]unparsed content[/tag]
	 *
	 *	parameters: An optional array of parameters, for the form
	 *	  [tag abc=123]content[/tag].  The array is an associative array
	 *	  where the keys are the parameter names, and the values are an
	 *	  array which may contain the following:
	 *		- match: a regular expression to validate and match the value.
	 *		- quoted: true if the value should be quoted.
	 *		- validate: callback to evaluate on the data, which is $data.
	 *		- value: a string in which to replace $1 with the data.
	 *			Either value or validate may be used, not both.
	 *		- optional: true if the parameter is optional.
	 *		- default: a default value for missing optional parameters.
	 *
	 *	test: A regular expression to test immediately after the tag's
	 *	  '=', ' ' or ']'.  Typically, should have a \] at the end.
	 *	  Optional.
	 *
	 *	content: Only available for unparsed_content, closed,
	 *	  unparsed_commas_content, and unparsed_equals_content.
	 *	  $1 is replaced with the content of the BBCode.  Parameters
	 *	  are replaced in the form {param}.  For unparsed_commas_content,
	 *	  $2, $3, ..., $n are replaced. The form {txt_*} can be used to
	 *    insert Lang::$txt strings, e.g. {txt_code} will be replaced with
	 *    the value of Lang::$txt['code'].
	 *
	 *	before: Only when content is not used, to go before any
	 *	  content.  For unparsed_equals, $1 is replaced with the value.
	 *	  For unparsed_commas, $1, $2, ..., $n are replaced.
	 *
	 *	after: Similar to before in every way, except that it is used
	 *	  when the BBCode is closed.
	 *
	 *	disabled_content: Used in place of content when the BBCode is
	 *	  disabled.  For closed, default is '', otherwise it is '$1' if
	 *	  block_level is false, '<div>$1</div>' elsewise.
	 *
	 *	disabled_before: Used in place of before when disabled.  Defaults
	 *	  to '<div>' if block_level, '' if not.
	 *
	 *	disabled_after: Used in place of after when disabled.  Defaults
	 *	  to '</div>' if block_level, '' if not.
	 *
	 *	block_level: Set to true the BBCode is a "block level" BBCode, similar
	 *	  to HTML. Block level BBCode cannot be nested inside BBCode that are
	 *	  not block level, and will not be implicitly closed as easily.
	 *	  One break following a block level BBCode may also be removed.
	 *
	 *	trim: If set to 'inside', whitespace after the begin tag will be
	 *	  removed.  If set to 'outside', whitespace after the end tag will
	 *	  meet the same fate.
	 *
	 *	validate: A callback to validate the data as $data. Four arguments
	 *    will be passed to the callback: &$bbc, &$data, $disabled, $params.
	 *    Depending on the BBCode's type, $data may be a string or an array of
	 *    strings (corresponding to the replacement.)
	 *
	 *	quoted: When type is 'unparsed_equals' or 'parsed_equals' only,
	 *	  may be not set, 'optional', or 'required' corresponding to if
	 *	  the content may be quoted. This allows the parser to read
	 *	  [tag="abc]def[esdf]"] properly.
	 *
	 *	require_parents: An array of tag names, or not set.  If set, the
	 *	  enclosing BBCode *must* be one of the listed tags, or parsing won't
	 *	  occur.
	 *
	 *	require_children: Similar to require_parents, if set children
	 *	  won't be parsed if they are not in the list.
	 *
	 *	disallow_children: Similar to, but very different from,
	 *	  require_children, if it is set the listed BBCodes will not be
	 *	  parsed inside the BBCode.
	 *
	 *	parsed_tags_allowed: An array restricting what BBC can be in the
	 *	  parsed_equals parameter, if desired.
	 */
	protected static array $codes = [
		['class' => 'SMF\BBCode\Abbr'],
		['class' => 'SMF\BBCode\Acronym'],
		['class' => 'SMF\BBCode\Anchor'],
		['class' => 'SMF\BBCode\Attach'],
		['class' => 'SMF\BBCode\B'],
		['class' => 'SMF\BBCode\Bdo'],
		['class' => 'SMF\BBCode\Black'],
		['class' => 'SMF\BBCode\Blue'],
		['class' => 'SMF\BBCode\Br'],
		['class' => 'SMF\BBCode\Center'],
		['class' => 'SMF\BBCode\Code1'],
		['class' => 'SMF\BBCode\Code2'],
		['class' => 'SMF\BBCode\Color'],
		['class' => 'SMF\BBCode\Email1'],
		['class' => 'SMF\BBCode\Email2'],
		['class' => 'SMF\BBCode\Flash'],
		['class' => 'SMF\BBCode\FloatDiv'],
		['class' => 'SMF\BBCode\Ftp1'],
		['class' => 'SMF\BBCode\Ftp2'],
		['class' => 'SMF\BBCode\Font'],
		['class' => 'SMF\BBCode\Glow'],
		['class' => 'SMF\BBCode\Green'],
		// For the h1-h6 tags, the element name will often change in the final
		// output, but the class will not. For example, `<h1 class="bbc_h1">`
		// might become `<h5 class="bbc_h1">` in the final output.
		['class' => 'SMF\BBCode\H1'],
		['class' => 'SMF\BBCode\H2'],
		['class' => 'SMF\BBCode\H3'],
		['class' => 'SMF\BBCode\H4'],
		['class' => 'SMF\BBCode\H5'],
		['class' => 'SMF\BBCode\H6'],
		['class' => 'SMF\BBCode\Html'],
		['class' => 'SMF\BBCode\Hr'],
		['class' => 'SMF\BBCode\I'],
		['class' => 'SMF\BBCode\Img'],
		['class' => 'SMF\BBCode\Iurl1'],
		['class' => 'SMF\BBCode\Iurl2'],
		['class' => 'SMF\BBCode\Justify'],
		['class' => 'SMF\BBCode\Right'],
		['class' => 'SMF\BBCode\Li'],
		['class' => 'SMF\BBCode\List1'],
		['class' => 'SMF\BBCode\List2'],
		['class' => 'SMF\BBCode\List3'],
		['class' => 'SMF\BBCode\Ltr'],
		['class' => 'SMF\BBCode\Me'],
		['class' => 'SMF\BBCode\Member'],
		['class' => 'SMF\BBCode\Move'],
		['class' => 'SMF\BBCode\NoBBC'],
		['class' => 'SMF\BBCode\Nolink'],
		['class' => 'SMF\BBCode\Php'],
		['class' => 'SMF\BBCode\Pre'],
		['class' => 'SMF\BBCode\Quote1'],
		['class' => 'SMF\BBCode\Quote2'],
		['class' => 'SMF\BBCode\Quote3'],
		['class' => 'SMF\BBCode\Quote4'],
		['class' => 'SMF\BBCode\Red'],
		['class' => 'SMF\BBCode\Right'],
		['class' => 'SMF\BBCode\Rtl'],
		['class' => 'SMF\BBCode\S'],
		['class' => 'SMF\BBCode\Shadow'],
		['class' => 'SMF\BBCode\Size1'],
		['class' => 'SMF\BBCode\Size2'],
		['class' => 'SMF\BBCode\Sub'],
		['class' => 'SMF\BBCode\Sup'],
		['class' => 'SMF\BBCode\Table'],
		['class' => 'SMF\BBCode\Td'],
		['class' => 'SMF\BBCode\Time1'],
		['class' => 'SMF\BBCode\Time2'],
		['class' => 'SMF\BBCode\Tr'],
		['class' => 'SMF\BBCode\Tt'],
		['class' => 'SMF\BBCode\U'],
		['class' => 'SMF\BBCode\Url1'],
		['class' => 'SMF\BBCode\Url2'],
		['class' => 'SMF\BBCode\White'],
		['class' => 'SMF\BBCode\YouTube'],
	];

	/**
	 * @var array
	 *
	 * Itemcodes are an alternative syntax for creating lists.
	 */
	protected static array $itemcodes = [
		'*' => 'disc',
		'@' => 'disc',
		'+' => 'square',
		'x' => 'square',
		'#' => 'square',
		'o' => 'circle',
		'O' => 'circle',
		'0' => 'circle',
	];

	/**
	 * @var string
	 *
	 * URL of this host/domain. Needed for the YouTube BBCode.
	 */
	private static string $hosturl;

	/**
	 * @var bool
	 *
	 * Tracks whether the integration_bbc_codes hook was called.
	 */
	private static bool $integrate_bbc_codes_done = false;

	/**
	 * @var array
	 *
	 * Reusable instances of this class.
	 */
	private static array $parsers = [];

	/*****************
	 * Public methods.
	 *****************/

	/**
	 * Constructor.
	 */
	public function __construct(bool $for_print = false)
	{
		$this->for_print = $for_print;

		self::loadBBCodeClasses();

		parent::__construct();

		self::integrateBBC();

		usort(
			self::$codes,
			fn($a, $b) => $a->tag <=> $b->tag,
		);
	}

	/**
	 * Parse bulletin board code in a string.
	 *
	 * @param string|bool $message The string to parse.
	 * @param bool $smileys Whether to parse smileys. Default: true.
	 * @param string|int $cache_id The cache ID.
	 *    If $cache_id is left empty, an ID will be generated automatically.
	 *    Manually specifying a ID is helpful in cases when an integration hook
	 *    wants to identify particular strings to act upon, but is otherwise
	 *    unnecessary.
	 * @param array $parse_tags If set, only parses these tags rather than all of them.
	 * @return string The parsed string.
	 */
	public function parse(string $message, bool $smileys = true, string|int $cache_id = '', array $parse_tags = []): string
	{
		// Don't waste cycles
		if (strval($message) === '') {
			return '';
		}

		// Ensure we start with a clean slate.
		$this->resetRuntimeProperties();

		$this->message = $message;
		$this->smileys = $smileys;
		$this->parse_tags = $parse_tags;

		$this->setDisabled();
		$this->setBbcCodes();

		// If the load average is too high, don't parse the BBC.
		if ($this->highLoadAverage()) {
			return $this->message;
		}

		if (!self::$enable_bbc) {
			if ($this->smileys === true) {
				$this->message = SmileyParser::load()->parse($this->message);
			}

			$this->message = $this->fixHtml($this->message);

			return $this->message;
		}

		// Do the job.
		$this->parseMessage();

		return $this->message;
	}

	/**
	 * Converts HTML to BBC.
	 *
	 * Only used by ManageBoards.php (and possibly mods).
	 *
	 * @param string $string Text containing HTML.
	 * @return string The string with HTML converted to BBC.
	 */
	public function unparse(string $string): string
	{
		// Replace newlines with spaces, as that's how browsers usually interpret them.
		$string = preg_replace('~\s*[\r\n]+\s*~', ' ', $string);

		// Though some of us love paragraphs, the parser will do better with breaks.
		$string = preg_replace('~</p>\s*?<p~i', '</p><br><p', $string);
		$string = preg_replace('~</p>\s*(?!<)~i', '</p><br>', $string);

		// Safari/webkit wraps lines in Wysiwyg in <div>'s.
		if (BrowserDetector::isBrowser('webkit')) {
			$string = preg_replace(['~<div(?:\s(?:[^<>]*?))?' . '>~i', '</div>'], ['<br>', ''], $string);
		}

		// If there's a trailing break get rid of it - Firefox tends to add one.
		$string = preg_replace('~<br\s?/?' . '>$~i', '', $string);

		// Remove any formatting within code tags.
		if (str_contains($string, '[code')) {
			$string = preg_replace('~<br\s?/?' . '>~i', '#smf_br_spec_grudge_cool!#', $string);
			$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $string, -1, PREG_SPLIT_DELIM_CAPTURE);

			// Only mess with stuff outside [code] tags.
			for ($i = 0, $n = count($parts); $i < $n; $i++) {
				// Value of 2 means we're inside the BBCode.
				if ($i % 4 == 2) {
					$parts[$i] = strip_tags($parts[$i]);
				}
			}

			$string = strtr(implode('', $parts), ['#smf_br_spec_grudge_cool!#' => '<br>']);
		}

		// Remove scripts, style and comment blocks.
		$string = preg_replace('~<script[^>]*[^/]?' . '>.*?</script>~i', '', $string);
		$string = preg_replace('~<style[^>]*[^/]?' . '>.*?</style>~i', '', $string);
		$string = preg_replace('~\\<\\!--.*?-->~i', '', $string);
		$string = preg_replace('~\\<\\!\\[CDATA\\[.*?\\]\\]\\>~i', '', $string);

		// Only try to buy more time if the client didn't quit.
		if (connection_aborted()) {
			Sapi::resetTimeout();
		}

		$parts = preg_split('~(<[A-Za-z]+\s*[^<>]*?style="?[^<>"]+"?[^<>]*?(?:/?)>|</[A-Za-z]+>)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		$replacement = '';
		$stack = [];

		foreach ($parts as $part) {
			// Opening tag.
			if (preg_match('~(<([A-Za-z]+)\s*[^<>]*?)style="?([^<>"]+)"?([^<>]*?(/?)>)~', $part, $matches) === 1) {
				// If it's being closed instantly, we can't deal with it...yet.
				if ($matches[5] === '/') {
					continue;
				}

				// Get an array of styles that apply to this element. (The strtr is there to combat HTML generated by Word.)
				$styles = explode(';', strtr((string) $matches[3], ['&quot;' => '']));
				$curElement = $matches[2];
				$precedingStyle = $matches[1];
				$afterStyle = $matches[4];
				$curCloseTags = '';
				$extra_attr = '';

				foreach ($styles as $type_value_pair) {
					// Remove spaces and convert uppercase letters.
					$clean_type_value_pair = strtolower(strtr(trim($type_value_pair), '=', ':'));

					// Something like 'font-weight: bold' is expected here.
					if (!str_contains($clean_type_value_pair, ':')) {
						continue;
					}

					// Capture the elements of a single style item (e.g. 'font-weight' and 'bold').
					list($style_type, $style_value) = explode(':', $type_value_pair);

					$style_value = trim($style_value);

					switch (trim($style_type)) {
						case 'font-weight':
							if ($style_value === 'bold') {
								$curCloseTags .= '[/b]';
								$replacement .= '[b]';
							}
							break;

						case 'text-decoration':
							if ($style_value == 'underline') {
								$curCloseTags .= '[/u]';
								$replacement .= '[u]';
							} elseif ($style_value == 'line-through') {
								$curCloseTags .= '[/s]';
								$replacement .= '[s]';
							}
							break;

						case 'text-align':
							if ($style_value == 'left') {
								$curCloseTags .= '[/left]';
								$replacement .= '[left]';
							} elseif ($style_value == 'center') {
								$curCloseTags .= '[/center]';
								$replacement .= '[center]';
							} elseif ($style_value == 'right') {
								$curCloseTags .= '[/right]';
								$replacement .= '[right]';
							}
							break;

						case 'font-style':
							if ($style_value == 'italic') {
								$curCloseTags .= '[/i]';
								$replacement .= '[i]';
							}
							break;

						case 'color':
							$curCloseTags .= '[/color]';
							$replacement .= '[color=' . $style_value . ']';
							break;

						case 'font-size':
							// Sometimes people put decimals where decimals should not be.
							if (preg_match('~(\d)+\.\d+(p[xt])~i', $style_value, $dec_matches) === 1) {
								$style_value = $dec_matches[1] . $dec_matches[2];
							}

							$curCloseTags .= '[/size]';
							$replacement .= '[size=' . $style_value . ']';
							break;

						case 'font-family':
							// Only get the first freaking font if there's a list!
							if (str_contains($style_value, ',')) {
								$style_value = substr($style_value, 0, strpos($style_value, ','));
							}

							$curCloseTags .= '[/font]';
							$replacement .= '[font=' . strtr($style_value, ["'" => '']) . ']';
							break;

						// This is a hack for images with dimensions embedded.
						case 'width':
						case 'height':
							if (preg_match('~[1-9]\d*~i', $style_value, $dimension) === 1) {
								$extra_attr .= ' ' . $style_type . '="' . $dimension[0] . '"';
							}
							break;

						case 'list-style-type':
							if (preg_match('~none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha~i', $style_value, $listType) === 1) {
								$extra_attr .= ' listtype="' . $listType[0] . '"';
							}
							break;
					}
				}

				// Preserve some tags stripping the styling.
				if (in_array($matches[2], ['a', 'font', 'td'])) {
					$replacement .= $precedingStyle . $afterStyle;
					$curCloseTags = '</' . $matches[2] . '>' . $curCloseTags;
				}

				// If there's something that still needs closing, push it to the stack.
				if (!empty($curCloseTags)) {
					array_push(
						$stack,
						[
							'element' => strtolower((string) $curElement),
							'closeTags' => $curCloseTags,
						],
					);
				} elseif (!empty($extra_attr)) {
					$replacement .= $precedingStyle . $extra_attr . $afterStyle;
				}
			}
			// Closing tag.
			elseif (preg_match('~</([A-Za-z]+)>~', $part, $matches) === 1) {
				// Is this the element that we've been waiting for to be closed?
				if (!empty($stack) && strtolower((string) $matches[1]) === $stack[count($stack) - 1]['element']) {
					$byebyeTag = array_pop($stack);
					$replacement .= $byebyeTag['closeTags'];
				}
				// Must've been something else.
				else {
					$replacement .= $part;
				}
			}
			// In all other cases, just add the part to the replacement.
			else {
				$replacement .= $part;
			}
		}

		// Now put back the replacement in the text.
		$string = $replacement;

		// We are not finished yet, request more time.
		if (connection_aborted()) {
			Sapi::resetTimeout();
		}

		// Let's pull out any legacy alignments.
		while (preg_match('~<([A-Za-z]+)\s+[^<>]*?(align="*(left|center|right)"*)[^<>]*?(/?)>~i', $string, $matches) === 1) {
			// Find the position in the text of this tag over again.
			$start_pos = strpos($string, (string) $matches[0]);

			if ($start_pos === false) {
				break;
			}

			// End tag?
			if ($matches[4] != '/' && strpos($string, '</' . $matches[1] . '>', $start_pos) !== false) {
				$end_pos = strpos($string, '</' . $matches[1] . '>', $start_pos);

				// Remove the align from that tag so it's never checked again.
				$tag = substr($string, $start_pos, strlen((string) $matches[0]));
				$content = substr($string, $start_pos + strlen((string) $matches[0]), $end_pos - $start_pos - strlen((string) $matches[0]));
				$tag = str_replace($matches[2], '', $tag);

				// Put the tags back into the body.
				$string = substr($string, 0, $start_pos) . $tag . '[' . $matches[3] . ']' . $content . '[/' . $matches[3] . ']' . substr($string, $end_pos);
			} else {
				// Just get rid of this evil tag.
				$string = substr($string, 0, $start_pos) . substr($string, $start_pos + strlen((string) $matches[0]));
			}
		}

		// Let's do some special stuff for fonts - cause we all love fonts.
		while (preg_match('~<font\s+([^<>]*)>~i', $string, $matches) === 1) {
			// Find the position of this again.
			$start_pos = strpos($string, (string) $matches[0]);
			$end_pos = false;

			if ($start_pos === false) {
				break;
			}

			// This must have an end tag - and we must find the right one.
			$lower_text = strtolower($string);

			// How many starting tags must we find closing ones for first?
			$start_pos_test = $start_pos + 4;
			$start_font_tag_stack = 0;

			while ($start_pos_test < strlen($string)) {
				// Where is the next starting font?
				$next_start_pos = strpos($lower_text, '<font', $start_pos_test);
				$next_end_pos = strpos($lower_text, '</font>', $start_pos_test);

				// Did we past another starting tag before an end one?
				if ($next_start_pos !== false && $next_start_pos < $next_end_pos) {
					$start_font_tag_stack++;
					$start_pos_test = $next_start_pos + 4;
				}
				// Otherwise we have an end tag but not the right one?
				elseif ($start_font_tag_stack) {
					$start_font_tag_stack--;
					$start_pos_test = $next_end_pos + 4;
				}
				// Otherwise we're there!
				else {
					$end_pos = $next_end_pos;
					break;
				}
			}

			if ($end_pos === false) {
				break;
			}

			// Now work out what the attributes are.
			$attribs = self::fetchTagAttributes((string) $matches[1]);
			$tags = [];
			$sizes_equivalence = [1 => '8pt', '10pt', '12pt', '14pt', '18pt', '24pt', '36pt'];

			foreach ($attribs as $s => $v) {
				if ($s == 'size') {
					// Cast before empty check because casting a string results in a 0 and we don't have zeros in the array! ;)
					$v = (int) trim($v);
					$v = empty($v) ? 1 : $v;
					$tags[] = ['[size=' . $sizes_equivalence[$v] . ']', '[/size]'];
				} elseif ($s == 'face') {
					$tags[] = ['[font=' . trim(strtolower($v)) . ']', '[/font]'];
				} elseif ($s == 'color') {
					$tags[] = ['[color=' . trim(strtolower($v)) . ']', '[/color]'];
				}
			}

			// As before add in our tags.
			$before = $after = '';

			foreach ($tags as $tag) {
				$before .= $tag[0];

				if (isset($tag[1])) {
					$after = $tag[1] . $after;
				}
			}

			// Remove the tag so it's never checked again.
			$content = substr($string, $start_pos + strlen((string) $matches[0]), $end_pos - $start_pos - strlen((string) $matches[0]));

			// Put the tags back into the body.
			$string = substr($string, 0, $start_pos) . $before . $content . $after . substr($string, $end_pos + 7);
		}

		// Almost there, just a little more time.
		if (connection_aborted()) {
			Sapi::resetTimeout();
		}

		if (count($parts = preg_split('~<(/?)(li|ol|ul)([^>]*)>~i', $string, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1) {
			// A toggle that determines whether we're directly under a <ol> or <ul>.
			$inList = false;

			// Keep track of the number of nested list levels.
			$listDepth = 0;

			// Map what we can expect from the HTML to what is supported by SMF.
			$listTypeMapping = [
				'1' => 'decimal',
				'A' => 'upper-alpha',
				'a' => 'lower-alpha',
				'I' => 'upper-roman',
				'i' => 'lower-roman',
				'disc' => 'disc',
				'square' => 'square',
				'circle' => 'circle',
			];

			// $i: text, $i + 1: '/', $i + 2: tag, $i + 3: tail.
			for ($i = 0, $numParts = count($parts) - 1; $i < $numParts; $i += 4) {
				$tag = strtolower($parts[$i + 2]);
				$is_opening_tag = $parts[$i + 1] === '';

				if ($is_opening_tag) {
					switch ($tag) {
						case 'ol':
						case 'ul':
							// We have a problem, we're already in a list.
							if ($inList) {
								// Inject a list opener, we'll deal with the ol/ul next loop.
								array_splice($parts, $i, 0, [
									'',
									'',
									str_repeat("\t", $listDepth) . '[li]',
									'',
								]);
								$numParts = count($parts) - 1;

								// The inlist status changes a bit.
								$inList = false;
							}

							// Just starting a new list.
							else {
								$inList = true;

								if ($tag === 'ol') {
									$listType = 'decimal';
								} elseif (preg_match('~type="?(' . implode('|', array_keys($listTypeMapping)) . ')"?~', $parts[$i + 3], $match) === 1) {
									$listType = $listTypeMapping[$match[1]];
								} else {
									$listType = null;
								}

								$listDepth++;

								$parts[$i + 2] = '[list' . ($listType === null ? '' : ' type=' . $listType) . ']' . "\n";
								$parts[$i + 3] = '';
							}
							break;

						case 'li':
							// This is how it should be: a list item inside the list.
							if ($inList) {
								$parts[$i + 2] = str_repeat("\t", $listDepth) . '[li]';
								$parts[$i + 3] = '';

								// Within a list item, it's almost as if you're outside.
								$inList = false;
							}

							// The li is no direct child of a list.
							else {
								// We are apparently in a list item.
								if ($listDepth > 0) {
									$parts[$i + 2] = '[/li]' . "\n" . str_repeat("\t", $listDepth) . '[li]';
									$parts[$i + 3] = '';
								}

								// We're not even near a list.
								else {
									// Quickly create a list with an item.
									$listDepth++;

									$parts[$i + 2] = '[list]' . "\n\t" . '[li]';
									$parts[$i + 3] = '';
								}
							}
							break;
					}
				}

				// Handle all the closing tags.
				else {
					switch ($tag) {
						case 'ol':
						case 'ul':
							// As we expected it, closing the list while we're in it.
							if ($inList) {
								$inList = false;

								$listDepth--;

								$parts[$i + 1] = '';
								$parts[$i + 2] = str_repeat("\t", $listDepth) . '[/list]';
								$parts[$i + 3] = '';
							} else {
								// We're in a list item.
								if ($listDepth > 0) {
									// Inject closure for this list item first.
									// The content of $parts[$i] is left as is!
									array_splice($parts, $i + 1, 0, [
										'', // $i + 1
										'[/li]' . "\n", // $i + 2
										'', // $i + 3
										'', // $i + 4
									]);
									$numParts = count($parts) - 1;

									// Now that we've closed the li, we're in list space.
									$inList = true;
								}
								// We're not even in a list, ignore
								else {
									$parts[$i + 1] = '';
									$parts[$i + 2] = '';
									$parts[$i + 3] = '';
								}
							}
							break;

						case 'li':
							if ($inList) {
								// There's no use for a </li> after <ol> or <ul>, ignore.
								$parts[$i + 1] = '';
								$parts[$i + 2] = '';
								$parts[$i + 3] = '';
							} else {
								// Remove the trailing breaks from the list item.
								$parts[$i] = preg_replace('~\s*<br\s*' . '/?' . '>\s*$~', '', $parts[$i]);
								$parts[$i + 1] = '';
								$parts[$i + 2] = '[/li]' . "\n";
								$parts[$i + 3] = '';

								// And we're back in the [list] space.
								$inList = true;
							}
							break;
					}
				}

				// If we're in the [list] space, no content is allowed.
				if ($inList && trim(preg_replace('~\s*<br\s*' . '/?' . '>\s*~', '', $parts[$i + 4])) !== '') {
					// Fix it by injecting an extra list item.
					array_splice($parts, $i + 4, 0, [
						'', // No content.
						'', // Opening tag.
						'li', // It's a <li>.
						'', // No tail.
					]);

					$numParts = count($parts) - 1;
				}
			}

			$string = implode('', $parts);

			if ($inList) {
				$listDepth--;
				$string .= str_repeat("\t", $listDepth) . '[/list]';
			}

			for ($i = $listDepth; $i > 0; $i--) {
				$string .= '[/li]' . "\n" . str_repeat("\t", $i - 1) . '[/list]';
			}
		}

		// I love my own image...
		while (preg_match('~<img\s+([^<>]*)/*>~i', $string, $matches) === 1) {
			// Find the position of the image.
			$start_pos = strpos($string, (string) $matches[0]);

			if ($start_pos === false) {
				break;
			}

			$end_pos = $start_pos + strlen((string) $matches[0]);

			$params = '';
			$src = '';

			$attrs = self::fetchTagAttributes((string) $matches[1]);

			foreach ($attrs as $attrib => $value) {
				if (in_array($attrib, ['width', 'height'])) {
					$params .= ' ' . $attrib . '=' . (int) $value;
				} elseif ($attrib == 'alt' && trim($value) != '') {
					$params .= ' alt=' . trim($value);
				} elseif ($attrib == 'src') {
					$src = trim($value);
				}
			}

			$tag = '';

			if (!empty($src)) {
				$src = new Url($src);

				// Attempt to fix the path in case it's not present.
				if (in_array($src->scheme, ['http', 'https']) && isset($src->host)) {
					$base_url = ($src->scheme ?? 'http') . '://' . $src->host . (empty($src->port) ? '' : ':' . $src->port);

					if (str_starts_with((string) $src, '/')) {
						$src = $base_url . $src;
					} else {
						$src = $base_url . (empty($src->path) ? '/' : preg_replace('~/(?:index\.php)?$~', '', $src->path)) . '/' . $src;
					}
				}

				$tag = '[img' . $params . ']' . $src . '[/img]';
			}

			// Replace the tag
			$string = substr($string, 0, $start_pos) . $tag . substr($string, $end_pos);
		}

		// The final bits are the easy ones - tags which map to tags which map to tags - etc etc.
		$tags = [
			'~<b(\s(.)*?)*?' . '>~i' => function () {
				return '[b]';
			},
			'~</b>~i' => function () {
				return '[/b]';
			},
			'~<i(\s(.)*?)*?' . '>~i' => function () {
				return '[i]';
			},
			'~</i>~i' => function () {
				return '[/i]';
			},
			'~<u(\s(.)*?)*?' . '>~i' => function () {
				return '[u]';
			},
			'~</u>~i' => function () {
				return '[/u]';
			},
			'~<strong(\s(.)*?)*?' . '>~i' => function () {
				return '[b]';
			},
			'~</strong>~i' => function () {
				return '[/b]';
			},
			'~<em(\s(.)*?)*?' . '>~i' => function () {
				return '[i]';
			},
			'~</em>~i' => function () {
				return '[i]';
			},
			'~<s(\s(.)*?)*?' . '>~i' => function () {
				return '[s]';
			},
			'~</s>~i' => function () {
				return '[/s]';
			},
			'~<strike(\s(.)*?)*?' . '>~i' => function () {
				return '[s]';
			},
			'~</strike>~i' => function () {
				return '[/s]';
			},
			'~<del(\s(.)*?)*?' . '>~i' => function () {
				return '[s]';
			},
			'~</del>~i' => function () {
				return '[/s]';
			},
			'~<center(\s(.)*?)*?' . '>~i' => function () {
				return '[center]';
			},
			'~</center>~i' => function () {
				return '[/center]';
			},
			'~<pre(\s(.)*?)*?' . '>~i' => function () {
				return '[pre]';
			},
			'~</pre>~i' => function () {
				return '[/pre]';
			},
			'~<sub(\s(.)*?)*?' . '>~i' => function () {
				return '[sub]';
			},
			'~</sub>~i' => function () {
				return '[/sub]';
			},
			'~<sup(\s(.)*?)*?' . '>~i' => function () {
				return '[sup]';
			},
			'~</sup>~i' => function () {
				return '[/sup]';
			},
			'~<tt(\s(.)*?)*?' . '>~i' => function () {
				return '[tt]';
			},
			'~</tt>~i' => function () {
				return '[/tt]';
			},
			'~<table(\s(.)*?)*?' . '>~i' => function () {
				return '[table]';
			},
			'~</table>~i' => function () {
				return '[/table]';
			},
			'~<tr(\s(.)*?)*?' . '>~i' => function () {
				return '[tr]';
			},
			'~</tr>~i' => function () {
				return '[/tr]';
			},
			'~<(td|th)\s[^<>]*?colspan="?(\d{1,2})"?.*?' . '>~i' => function ($matches) {
				return str_repeat('[td][/td]', $matches[2] - 1) . '[td]';
			},
			'~<(td|th)(\s(.)*?)*?' . '>~i' => function () {
				return '[td]';
			},
			'~</(td|th)>~i' => function () {
				return '[/td]';
			},
			'~<br(?:\s[^<>]*?)?' . '>~i' => function () {
				return "\n";
			},
			'~<hr[^<>]*>(\n)?~i' => function ($matches) {
				return "[hr]\n" . $matches[0];
			},
			'~(\n)?\[hr\]~i' => function () {
				return "\n[hr]";
			},
			'~^\n\[hr\]~i' => function () {
				return '[hr]';
			},
			'~<blockquote(\s(.)*?)*?' . '>~i' => function () {
				return '&lt;blockquote&gt;';
			},
			'~</blockquote>~i' => function () {
				return '&lt;/blockquote&gt;';
			},
			'~<ins(\s(.)*?)*?' . '>~i' => function () {
				return '&lt;ins&gt;';
			},
			'~</ins>~i' => function () {
				return '&lt;/ins&gt;';
			},
		];

		foreach ($tags as $tag => $replace) {
			$string = preg_replace_callback($tag, $replace, $string);
		}

		// Please give us just a little more time.
		if (connection_aborted()) {
			Sapi::resetTimeout();
		}

		// What about URL's - the pain in the ass of the tag world.
		while (preg_match('~<a\s+([^<>]*)>([^<>]*)</a>~i', $string, $matches) === 1) {
			// Find the position of the URL.
			$start_pos = strpos($string, (string) $matches[0]);

			if ($start_pos === false) {
				break;
			}

			$end_pos = $start_pos + strlen((string) $matches[0]);

			$tag_type = 'url';
			$href = '';

			$attrs = self::fetchTagAttributes((string) $matches[1]);

			foreach ($attrs as $attrib => $value) {
				if ($attrib == 'href') {
					$href = new Url(trim($value));
					$our_url = new Url(Config::$boardurl);

					// Are we dealing with an FTP link?
					if (in_array($href->scheme, ['ftp', 'ftps'])) {
						$tag_type = 'ftp';
					}
					// Or is this a link to an email address?
					elseif ($href->scheme == 'mailto') {
						$tag_type = 'email';
						$href = $href->path;
					}
					// No http(s), so attempt to fix this potential relative URL.
					elseif (!in_array($href->scheme, ['http', 'https']) && isset($our_url->host)) {
						$base_url = ($our_url->scheme ?? 'http') . '://' . $our_url->host . (empty($our_url->port) ? '' : ':' . $our_url->port);

						if (str_starts_with((string) $href, '/')) {
							$href = $base_url . $href;
						} else {
							$href = $base_url . '/' . trim($our_url->path, '/') . '/' . $href;
						}
					}
				}

				// External URL?
				if ($attrib == 'target' && $tag_type == 'url') {
					if (trim($value) == '_blank') {
						$tag_type == 'iurl';
					}
				}
			}

			$tag = '';

			if ($href != '') {
				if ($matches[2] == $href) {
					$tag = '[' . $tag_type . ']' . $href . '[/' . $tag_type . ']';
				} else {
					$tag = '[' . $tag_type . '=' . $href . ']' . $matches[2] . '[/' . $tag_type . ']';
				}
			}

			// Replace the tag
			$string = substr($string, 0, $start_pos) . $tag . substr($string, $end_pos);
		}

		$string = strip_tags($string);

		// Some tags often end up as just dummy tags - remove those.
		$string = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $string);

		// Fix up entities.
		$string = preg_replace('~&#0*38;~i', '&#38;#38;', $string);

		$string = self::legalise($string);

		return $string;
	}

	/**
	 * Gets a regular expression to match all known BBC tags.
	 *
	 * @return string A copy of $this->alltags_regex.
	 */
	public function getAllTagsRegex(): string
	{
		if (!isset($this->alltags_regex)) {
			$this->setAllTagsRegex();
		}

		return $this->alltags_regex;
	}

	/************************
	 * Public static methods.
	 ************************/

	/**
	 * Returns a reusable instance of this class.
	 *
	 * Using this method to get a BBCodeParser instance saves memory by avoiding
	 * creating redundant instances.
	 *
	 * @param bool $for_print If true, adjusts output for print media.
	 * @return object An instance of this class.
	 */
	public static function load(bool $for_print = false): object
	{
		if (!isset(self::$parsers[(int) $for_print])) {
			self::$parsers[(int) $for_print] = new self($for_print);
		}

		return self::$parsers[(int) $for_print];
	}

	/**
	 * Get the list of supported BBCodes, including any added by modifications.
	 *
	 * @return array List of supported BBCodes.
	 */
	public static function getCodes(): array
	{
		self::loadBBCodeClasses();

		self::integrateBBC();

		return self::$codes;
	}

	/**
	 * Returns an array of BBCodes tags that are allowed in signatures.
	 *
	 * @return array An array containing allowed tags for signatures, or an
	 *    empty array if all tags are allowed.
	 */
	public static function getSigTags(): array
	{
		list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);

		if (empty($sig_bbc)) {
			return [];
		}

		$disabled_tags = explode(',', $sig_bbc);

		// Get all available BBCode tags.
		$temp = self::getCodes();
		$allowed_tags = [];

		foreach ($temp as $bbc) {
			if (!in_array($bbc->tag, $disabled_tags)) {
				$allowed_tags[] = $bbc->tag;
			}
		}

		$allowed_tags = array_unique($allowed_tags);

		if (empty($allowed_tags)) {
			// An empty array means that all BBCode tags are allowed.
			// So if all tags are disabled we need to add a dummy tag.
			$allowed_tags[] = 'nonexisting';
		}

		return $allowed_tags;
	}

	/**
	 * Replaces {txt_*} tokens with Lang::$txt strings.
	 *
	 * @param string $data A string that might contain {txt_*} tokens.
	 * @return string The string with Lang::$txt string values.
	 */
	public static function insertTxt(string $string): string
	{
		return preg_replace_callback(
			'/{(.*?)}/',
			function ($matches) {
				if ($matches[0] === '{scripturl}') {
					return Config::$scripturl;
				}

				if ($matches[0] === '{hosturl}') {
					if (!isset(self::$hosturl)) {
						$our_url = new Url(Config::$scripturl);
						self::$hosturl = $our_url->scheme . '://' . $our_url->host;
					}

					return self::$hosturl;
				}

				foreach (['txt', 'editortxt'] as $var) {
					if (
						str_starts_with($matches[1], $var . '_')
						&& Lang::txtExists(
							substr($matches[1], strlen($var) + 1),
							var: $var,
							lang: self::$locale,
						)
					) {
						return Lang::getTxt(
							substr($matches[1], strlen($var) + 1),
							var: $var,
							lang: self::$locale,
						);
					}
				}

				return $matches[0];
			},
			$string,
		);
	}

	/*******************
	 * Internal methods.
	 *******************/

	/**
	 * The method that actually parses the BBCode in $this->message.
	 */
	protected function parseMessage(): void
	{
		$this->open_bbc = [];
		$this->message = strtr($this->message, ["\n" => '<br>']);

		$this->setAllTagsRegex();

		while ($this->pos !== false) {
			$this->last_pos = isset($this->last_pos) ? max($this->pos, $this->last_pos) : $this->pos;

			preg_match('~\[/?(?=' . $this->alltags_regex . ')~i', $this->message, $matches, PREG_OFFSET_CAPTURE, $this->pos + 1);

			$this->pos = $matches[0][1] ?? false;

			// Failsafe.
			if ($this->pos === false || $this->last_pos > $this->pos) {
				$this->pos = strlen($this->message) + 1;
			}

			// Can't have a one letter smiley, URL, or email! (Sorry.)
			if ($this->last_pos < $this->pos - 1) {
				// Make sure the $this->last_pos is not negative.
				$this->last_pos = max($this->last_pos, 0);

				// Pick a block of data to do some raw fixing on.
				$data = substr($this->message, $this->last_pos, $this->pos - $this->last_pos);

				$data = $this->fixHtml($data);

				// Restore any placeholders
				$data = strtr($data, $this->placeholders);

				$data = strtr($data, ["\t" => Utils::TAB_SUBSTITUTE]);

				// If it wasn't changed, no copying or other boring stuff has to happen!
				if ($data != substr($this->message, $this->last_pos, $this->pos - $this->last_pos)) {
					$this->message = substr($this->message, 0, $this->last_pos) . $data . substr($this->message, $this->pos);

					// Since we changed it, look again in case we added or removed a BBCode.  But we don't want to skip any.
					$old_pos = strlen($data) + $this->last_pos;
					$this->pos = strpos($this->message, '[', $this->last_pos);
					$this->pos = $this->pos === false ? $old_pos : min($this->pos, $old_pos);
				}
			}

			// Are we there yet?  Are we there yet?
			if ($this->pos >= strlen($this->message) - 1) {
				break;
			}

			$tag_character = strtolower($this->message[$this->pos + 1]);

			if ($tag_character == '/' && !empty($this->open_bbc)) {
				$this->closeTags();

				continue;
			}

			// No BBCodes for this character, so just keep going (fastest possible course.)
			if (!isset($this->bbc_codes[$tag_character])) {
				continue;
			}

			$this->inside = empty($this->open_bbc) ? null : $this->open_bbc[count($this->open_bbc) - 1];

			// What BBCode do we have?
			list($bbc, $params) = $this->detectBBCode($tag_character);

			if ($bbc === null) {
				// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
				if (isset(self::$itemcodes[$this->message[$this->pos + 1]], $this->message[$this->pos + 2]) && $this->message[$this->pos + 2] == ']' && !isset($this->disabled['list']) && !isset($this->disabled['li'])) {
					$this->parseItemCode();
				}
				// Implicitly close lists and tables if something other than what's required is in them.  This is needed for itemcode.
				elseif ($this->inside !== null && !empty($this->inside->require_children)) {
					array_pop($this->open_bbc);

					$this->message = substr($this->message, 0, $this->pos) . "\n" . $this->inside->after . "\n" . substr($this->message, $this->pos);
					$this->pos += strlen($this->inside->after) - 1 + 2;
				}

				continue;
			}

			// Propagate the list to the child (so wrapping the disallowed BBCode won't work either.)
			if (isset($this->inside->disallow_children)) {
				$bbc->disallow_children = isset($bbc->disallow_children) ? array_unique(array_merge($bbc->disallow_children, $this->inside->disallow_children)) : $this->inside->disallow_children;
			}

			// Is this BBCode disabled?
			if (isset($this->disabled[$bbc->tag])) {
				$bbc = $this->disableCode($bbc);
			}

			// The only special case is 'html', which doesn't need to close things.
			if (!empty($bbc->block_level) && $bbc->tag != 'html' && empty($this->inside->block_level)) {
				$this->closeInlineTags();
			}

			// Can't read past the end of the message
			$this->pos1 = min(strlen($this->message), $this->pos1);

			$this->transformToHtml($bbc, $params);
		}

		// Close any remaining BBCodes.
		while ($bbc = array_pop($this->open_bbc)) {
			$this->message .= "\n" . $bbc->after . "\n";
		}

		// Parse the smileys within the parts where it can be done safely.
		if ($this->smileys === true) {
			$message_parts = explode("\n", $this->message);

			for ($i = 0, $n = count($message_parts); $i < $n; $i += 2) {
				$message_parts[$i] = SmileyParser::load()->parse($message_parts[$i]);
			}

			$this->message = implode('', $message_parts);
		}
		// No smileys, just get rid of the markers.
		else {
			$this->message = strtr($this->message, ["\n" => '']);
		}

		// Transform the first table row into a table header and wrap the rest
		// in table body tags.
		$this->message = preg_replace_callback(
			'/<table class="bbc_table"><tr>(\X*?)<\/tr>(\X*?)<\/table>/u',
			fn($matches) => '<table class="bbc_table"><thead><tr>' . preg_replace('~(</?)td(>)~', '$1th$2', $matches[1]) . '</tr></thead><tbody>' . $matches[2] . '</tbody></table>',
			$this->message,
		);

		if ($this->message !== '' && $this->message[0] === ' ') {
			$this->message = '&nbsp;' . substr($this->message, 1);
		}

		// Cleanup whitespace.
		$this->message = strtr($this->message, ['  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"]);
	}

	/**
	 * Sets $this->bbc_codes.
	 */
	protected function setBbcCodes(): void
	{
		// If we already have a version of the BBCodes for the current language, use that.
		$locale_key = self::$locale . '|' . implode(',', $this->disabled);

		if (!empty($this->bbc_lang_locales[$locale_key])) {
			$this->bbc_codes = $this->bbc_lang_locales[$locale_key];
		}

		// If we are not doing every BBCode then we don't cache this run.
		if (!empty($this->parse_tags)) {
			$this->bbc_codes = [];
		}

		// Avoid unnecessary repetition.
		if (!empty($this->bbc_codes)) {
			return;
		}

		// Add itemcodes to the array.
		if (!isset($this->disabled['li']) && !isset($this->disabled['list'])) {
			foreach (self::$itemcodes as $c => $dummy) {
				$this->bbc_codes[$c] = [];
			}
		}

		$codes = self::$codes;

		// Shhhh!
		if (!isset($this->disabled['color'])) {
			$codes[] = new GenericBBCode([
				'tag' => 'chrissy',
				'before' => '<span style="color: #cc0099;">',
				'after' => ' :-*</span>',
			]);
			$codes[] = new GenericBBCode([
				'tag' => 'kissy',
				'before' => '<span style="color: #cc0099;">',
				'after' => ' :-*</span>',
			]);
		}
		$codes[] = new GenericBBCode([
			'tag' => 'cowsay',
			'parameters' => [
				'e' => ['optional' => true, 'quoted' => true, 'match' => '(.*?)', 'default' => 'oo', 'validate' => fn($eyes) => Utils::entitySubstr($eyes . 'oo', 0, 2),
				],
				't' => ['optional' => true, 'quoted' => true, 'match' => '(.*?)', 'default' => '  ', 'validate' => fn($tongue) => Utils::entitySubstr($tongue . '  ', 0, 2),
				],
			],
			'before' => '<pre data-e="{e}" data-t="{t}"><div>',
			'after' => '</div></pre>',
			'block_level' => true,
			'validate' => function (&$bbc, &$data, $disabled, $params) {
				static $moo = true;

				if ($moo) {
					Theme::addInlineJavaScript("\n\t" . base64_decode(
						'aWYoZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoImJvdmluZV9vcmFjbGU
						iKT09PW51bGwpe2xldCBzdHlsZU5vZGU9ZG9jdW1lbnQuY3JlYXRlRWx
						lbWVudCgic3R5bGUiKTtzdHlsZU5vZGUuaWQ9ImJvdmluZV9vcmFjbGU
						iO3N0eWxlTm9kZS5pbm5lckhUTUw9J3ByZVtkYXRhLWVdW2RhdGEtdF1
						7d2hpdGUtc3BhY2U6cHJlLXdyYXA7bGluZS1oZWlnaHQ6aW5pdGlhbDt
						9cHJlW2RhdGEtZV1bZGF0YS10XSA+IGRpdntkaXNwbGF5OnRhYmxlO2J
						vcmRlcjoxcHggc29saWQ7Ym9yZGVyLXJhZGl1czowLjVlbTtwYWRkaW5
						nOjFjaDttYXgtd2lkdGg6ODBjaDttaW4td2lkdGg6MTJjaDt9cHJlW2R
						hdGEtZV1bZGF0YS10XTo6YWZ0ZXJ7ZGlzcGxheTppbmxpbmUtYmxvY2s
						7bWFyZ2luLWxlZnQ6OGNoO21pbi13aWR0aDoyMGNoO2RpcmVjdGlvbjp
						sdHI7Y29udGVudDpcJ1xcNUMgXCdcJyBcJ1wnIF5fX15cXEEgXCdcJyB
						cXDVDIFwnXCcgKFwnIGF0dHIoZGF0YS1lKSBcJylcXDVDX19fX19fX1x
						cQSBcJ1wnIFwnXCcgXCdcJyAoX18pXFw1QyBcJ1wnIFwnXCcgXCdcJyB
						cJ1wnIFwnXCcgXCdcJyBcJ1wnIClcXDVDL1xcNUNcXEEgXCdcJyBcJ1w
						nIFwnXCcgXCdcJyBcJyBhdHRyKGRhdGEtdCkgXCcgfHwtLS0tdyB8XFx
						BIFwnXCcgXCdcJyBcJ1wnIFwnXCcgXCdcJyBcJ1wnIFwnXCcgfHwgXCd
						cJyBcJ1wnIFwnXCcgXCdcJyB8fFwnO30nO2RvY3VtZW50LmdldEVsZW1
						lbnRzQnlUYWdOYW1lKCJoZWFkIilbMF0uYXBwZW5kQ2hpbGQoc3R5bGV
						Ob2RlKTt9',
					), true);

					$moo = false;
				}
			},
		]);

		foreach ($codes as $bbc) {
			// Make it easier to process parameters later
			if (!empty($bbc->parameters)) {
				ksort($bbc->parameters, SORT_STRING);
			}

			// If we are not doing every BBCode only do ones we are interested in.
			if (empty($this->parse_tags) || in_array($bbc->tag, $this->parse_tags)) {
				$this->bbc_codes[substr($bbc->tag, 0, 1)][] = $bbc;
			}
		}

		if (empty($this->parse_tags)) {
			$this->bbc_lang_locales[$locale_key] = $this->bbc_codes;
		}
	}

	/**
	 * Sets $this->alltags_regex.
	 */
	protected function setAllTagsRegex(): void
	{
		$alltags = [];

		foreach ($this->bbc_codes as $section) {
			foreach ($section as $bbc) {
				$alltags[] = $bbc->tag;
			}
		}

		$this->alltags_regex = '(?' . '>\b' . Utils::buildRegex(array_unique($alltags)) . '\b|' . Utils::buildRegex(array_keys(self::$itemcodes)) . ')';
	}

	/**
	 * Fixes up any raw HTML in a BBCode string.
	 *
	 * @param string $data A string that might contain HTML.
	 * @return string The fixed version of the string.
	 */
	protected function fixHtml(string $data): string
	{
		if (empty(self::$enable_post_html) || !str_contains($data, '&lt;')) {
			return $data;
		}

		$data = preg_replace_callback(
			'~&lt;a\b\X+?href=((?:&quot;|")?)(\X*?)\1\X*?&gt;(\X*?)&lt;/a&gt;~ui',
			function ($matches) {
				if ([$matches[2]] !== Autolinker::load()->detectUrls($matches[2])) {
					return $matches[0];
				}

				if (str_starts_with($matches[2], Config::$boardurl)) {
					return self::$enable_bbc ? '[iurl=&quot;' . $matches[2] . '&quot;]' . $matches[3] . '[/iurl]' : '<a href="' . $matches[2] . '" class="bbc_link">' . $matches[3] . '</a>';
				}

				return self::$enable_bbc ? '[url=&quot;' . $matches[2] . '&quot;]' . $matches[3] . '[/url]' : '<a href="' . $matches[2] . '" class="bbc_link" target="_blank" rel="noopener">' . $matches[3] . '</a>';
			},
			$data,
		);

		// <br> should be empty.
		$empty_tags = ['br', 'hr'];

		foreach ($empty_tags as $tag) {
			$data = str_replace(['&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'], '<' . $tag . '>', $data);
		}

		// b, u, i, s, pre... basic tags.
		$closable_tags = ['b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote', 'strong'];

		foreach ($closable_tags as $tag) {
			$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');

			$data = strtr($data, ['&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>']);

			if ($diff > 0) {
				$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
			}
		}

		// Do <img ...> - with security... action= -> action-.
		preg_match_all('~&lt;img\s+src=((?:&quot;)?)((?:https?://|ftps?://)\S+?)\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s?/)?&gt;~i', $data, $matches, PREG_PATTERN_ORDER);

		if (!empty($matches[0])) {
			$replaces = [];

			foreach ($matches[2] as $match => $imgtag) {
				$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

				// Remove action= from the URL - no funny business, now.
				$imgtag = preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $imgtag);

				$placeholder = sprintf($this->placeholder_template, ++$this->placeholders_counter);
				$this->placeholders[$placeholder] = '[img' . $alt . ']' . $imgtag . '[/img]';

				$replaces[$matches[0][$match]] = $placeholder;
			}

			$data = strtr($data, $replaces);
		}

		return $data;
	}

	/**
	 * Ensures BBCode markup is well-formed by auto-closing nested tags in the
	 * correct order.
	 * Operates directly on $this->message.
	 */
	protected function closeTags(): void
	{
		$pos2 = strpos($this->message, ']', $this->pos + 1);

		if ($pos2 == $this->pos + 2) {
			return;
		}

		$look_for = strtolower(substr($this->message, $this->pos + 2, $pos2 - $this->pos - 2));

		// A closing tag that doesn't match any open tags? Skip it.
		if (!in_array($look_for, array_map(function ($bbc) { return $bbc->tag; }, $this->open_bbc))) {
			return;
		}

		$to_close = [];
		$block_level = null;

		do {
			$bbc = array_pop($this->open_bbc);

			if (!$bbc) {
				break;
			}

			if (!empty($bbc->block_level)) {
				// Only find out if we need to.
				if ($block_level === false) {
					array_push($this->open_bbc, $bbc);
					break;
				}

				// The idea is, if we are LOOKING for a block level BBCode, we can close them on the way.
				if (strlen($look_for) > 0 && isset($this->bbc_codes[$look_for[0]])) {
					foreach ($this->bbc_codes[$look_for[0]] as $temp) {
						if ($temp->tag == $look_for) {
							$block_level = !empty($temp->block_level);
							break;
						}
					}
				}

				if ($block_level !== true) {
					$block_level = false;
					array_push($this->open_bbc, $bbc);
					break;
				}
			}

			$to_close[] = $bbc;
		} while ($bbc->tag != $look_for);

		// Did we just eat through everything and not find it?
		if ((empty($this->open_bbc) && (empty($bbc) || $bbc->tag != $look_for))) {
			$this->open_bbc = $to_close;

			return;
		}

		if (!empty($to_close) && $bbc->tag != $look_for) {
			if ($block_level === null && isset($look_for[0], $this->bbc_codes[$look_for[0]])) {
				foreach ($this->bbc_codes[$look_for[0]] as $temp) {
					if ($temp->tag == $look_for) {
						$block_level = !empty($temp->block_level);
						break;
					}
				}
			}

			// We're not looking for a block level BBCode (or maybe even a BBCode that exists...)
			if (!$block_level) {
				foreach ($to_close as $bbc) {
					array_push($this->open_bbc, $bbc);
				}

				return;
			}
		}

		foreach ($to_close as $bbc) {
			$this->message = substr($this->message, 0, $this->pos) . "\n" . $bbc->after . "\n" . substr($this->message, $pos2 + 1);
			$this->pos += strlen($bbc->after) + 2;
			$pos2 = $this->pos - 1;

			// See the comment at the end of the big loop - just eating whitespace ;).
			$whitespace_regex = '';

			if (!empty($bbc->block_level)) {
				$whitespace_regex .= '(&nbsp;|\s)*(<br\s*/?' . '>)?';
			}

			// Trim one line of whitespace after unnested tags, but all of it after nested ones
			if (!empty($bbc->trim) && $bbc->trim != 'inside') {
				$whitespace_regex .= empty($bbc->require_parents) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';
			}

			if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($this->message, $this->pos), $matches) != 0) {
				$this->message = substr($this->message, 0, $this->pos) . substr($this->message, $this->pos + strlen($matches[0]));
			}
		}

		if (!empty($to_close)) {
			$to_close = [];
			$this->pos--;
		}
	}

	/**
	 * Figures out which BBCode the current tag is.
	 *
	 * @param string $tag_character The first character of this (possible) tag.
	 * @return array The BBCode definition and the parameter values to use.
	 */
	protected function detectBBCode(string $tag_character): array
	{
		$bbc = null;
		$params = [];

		foreach ($this->bbc_codes[$tag_character] as $possible) {
			$pt_strlen = strlen($possible->tag);

			// Not a match?
			if (strtolower(substr($this->message, $this->pos + 1, $pt_strlen)) != $possible->tag) {
				continue;
			}

			$next_c = $this->message[$this->pos + 1 + $pt_strlen] ?? '';

			// A tag is the last char maybe
			if ($next_c == '') {
				break;
			}

			// A test validation?
			if (isset($possible->test) && preg_match('~^' . $possible->test . '~', substr($this->message, $this->pos + 1 + $pt_strlen + 1)) === 0) {
				continue;
			}

			// Do we want parameters?
			if (!empty($possible->parameters)) {
				// Are all the parameters optional?
				$param_required = false;

				foreach ($possible->parameters as $param) {
					if (empty($param['optional'])) {
						$param_required = true;
						break;
					}
				}

				if ($param_required && $next_c != ' ') {
					continue;
				}
			}
			// No parameters, so does the next character match what we expect?
			elseif (isset($possible->type)) {
				// Do we need an equal sign?
				if (in_array($possible->type, ['unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals']) && $next_c != '=') {
					continue;
				}

				// Maybe we just want a /...
				if ($possible->type == 'closed' && $next_c != ']' && substr($this->message, $this->pos + 1 + $pt_strlen, 2) != '/]' && substr($this->message, $this->pos + 1 + $pt_strlen, 3) != ' /]') {
					continue;
				}

				// An immediate ]?
				if ($possible->type == 'unparsed_content' && $next_c != ']') {
					continue;
				}
			}
			// No type means 'parsed_content', which demands an immediate ] without parameters!
			elseif ($next_c != ']') {
				continue;
			}

			// Check allowed tree?
			if (isset($possible->require_parents) && ($this->inside === null || !in_array($this->inside->tag, $possible->require_parents))) {
				continue;
			}

			if (isset($this->inside->require_children) && !in_array($possible->tag, (array) $this->inside->require_children)) {
				continue;
			}

			// If this is in the list of disallowed child tags, don't parse it.
			if (isset($this->inside->disallow_children) && in_array($possible->tag, (array) $this->inside->disallow_children)) {
				continue;
			}

			$this->pos1 = $this->pos + 1 + $pt_strlen + 1;

			// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
			if ($possible->tag == 'quote') {
				// Start with standard
				$quote_alt = false;

				foreach ($this->open_bbc as $open_quote) {
					// Every parent quote this quote has flips the styling
					if ($open_quote->tag == 'quote') {
						$quote_alt = !$quote_alt;
					}
				}

				// Add a class to the quote to style alternating blockquotes
				$possible->before = strtr($possible->before, ['<blockquote>' => '<blockquote class="bbc_' . ($quote_alt ? 'alternate' : 'standard') . '_quote">']);
			}

			// This is long, but it makes things much easier and cleaner.
			if (!empty($possible->parameters)) {
				// Build a regular expression for each parameter for the current BBCode.
				$regex_key = json_encode($possible->parameters);

				if (!isset($params_regexes[$regex_key])) {
					$params_regexes[$regex_key] = '';

					foreach ($possible->parameters as $p => $info) {
						$params_regexes[$regex_key] .= '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '(?:&quot;)' . ($info['quoted'] === 'optional' ? '?' : '')) . ($info['match'] ?? '(.+?)') . (empty($info['quoted']) ? '' : '(?:&quot;)' . ($info['quoted'] === 'optional' ? '?' : '')) . '\s*)' . (empty($info['optional']) ? '' : '?');
					}
				}

				// Extract the string that potentially holds our parameters.
				$blob = preg_split('~\[/?(?:' . $this->alltags_regex . ')~i', substr($this->message, $this->pos));
				$blobs = preg_split('~\]~i', $blob[1]);

				$splitters = implode('=|', array_keys($possible->parameters)) . '=';

				// Progressively append more blobs until we find our parameters or run out of blobs
				$blob_counter = 1;

				while ($blob_counter <= count($blobs)) {
					$given_param_string = implode(']', array_slice($blobs, 0, $blob_counter++));

					$given_params = preg_split('~\s(?=(' . $splitters . '))~i', $given_param_string);
					sort($given_params, SORT_STRING);

					$match = preg_match('~^' . $params_regexes[$regex_key] . '$~i', implode(' ', $given_params), $matches) !== 0;

					if ($match) {
						break;
					}
				}

				// Didn't match our parameter list, try the next possible.
				if (!$match) {
					continue;
				}

				$params = [];

				for ($i = 1, $n = count($matches); $i < $n; $i += 2) {
					$key = strtok(ltrim($matches[$i]), '=');

					if ($key === false) {
						continue;
					}

					if (isset($possible->parameters[$key]['value'])) {
						$params['{' . $key . '}'] = strtr($possible->parameters[$key]['value'], ['$1' => $matches[$i + 1]]);
					} elseif (isset($possible->parameters[$key]['validate'])) {
						$params['{' . $key . '}'] = $possible->parameters[$key]['validate']($matches[$i + 1]);
					} else {
						$params['{' . $key . '}'] = $matches[$i + 1];
					}

					// Just to make sure: replace any $ or { so they can't interpolate wrongly.
					$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], ['$' => '&#036;', '{' => '&#123;']);
				}

				foreach ($possible->parameters as $p => $info) {
					if (!isset($params['{' . $p . '}'])) {
						if (!isset($info['default'])) {
							$params['{' . $p . '}'] = '';
						} elseif (isset($possible->parameters[$p]['value'])) {
							$params['{' . $p . '}'] = strtr($possible->parameters[$p]['value'], ['$1' => $info['default']]);
						} elseif (isset($possible->parameters[$p]['validate'])) {
							$params['{' . $p . '}'] = $possible->parameters[$p]['validate']($info['default']);
						} else {
							$params['{' . $p . '}'] = $info['default'];
						}
					}
				}

				$bbc = clone $possible;

				// Put the parameters into the string.
				if (isset($bbc->before)) {
					$bbc->before = strtr($bbc->before, $params);
				}

				if (isset($bbc->after)) {
					$bbc->after = strtr($bbc->after, $params);
				}

				if (isset($bbc->content)) {
					$bbc->content = strtr($bbc->content, $params);
				}

				$this->pos1 += strlen($given_param_string);
			} else {
				$bbc = clone $possible;
				$params = [];
			}
			break;
		}

		return [$bbc, $params];
	}

	/**
	 * Parses itemcodes into normal list items.
	 * Operates directly on $this->message.
	 */
	protected function parseItemCode(): void
	{
		if ($this->message[$this->pos + 1] == '0' && !in_array($this->message[$this->pos - 1], [';', ' ', "\t", "\n", '>'])) {
			return;
		}

		$type = self::$itemcodes[$this->message[$this->pos + 1]];

		// First let's set up the tree: it needs to be in a list, or after an li.
		if ($this->inside === null || ($this->inside->tag != 'list' && $this->inside->tag != 'li')) {
			$list = new List1();
			$list->disallow_children = $this->inside->disallow_children ?? null;

			$this->open_bbc[] = $list;

			$html = '<ul class="bbc_list">';
		}
		// We're in a list item already: another itemcode?  Close it first.
		elseif ($this->inside->tag == 'li') {
			array_pop($this->open_bbc);
			$html = '</li>';
		} else {
			$html = '';
		}

		// Now we open a new list item BBCode.
		$li = new Li();
		$li->disallow_children = $this->inside->disallow_children ?? null;

		$this->open_bbc[] = $li;

		// First, open the tag...
		$html .= '<li' . ($type == '' ? '' : ' type="' . $type . '"') . '>';

		$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $this->pos + 3);

		$this->pos += strlen($html) - 1 + 2;

		// Next, find the next break (if any.)  If there's more itemcode after it, keep it going - otherwise close!
		$pos2 = strpos($this->message, '<br>', $this->pos);
		$pos3 = strpos($this->message, '[/', $this->pos);

		if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false)) {
			preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($this->message, $pos2 + 4), $matches);

			$this->message = substr($this->message, 0, $pos2) . (!empty($matches[0]) && str_ends_with($matches[0], '[') ? '[/li]' : '[/li][/list]') . substr($this->message, $pos2);

			$this->open_bbc[count($this->open_bbc) - 2]['after'] = '</ul>';
		}
		// Tell the [list] that it needs to close specially.
		else {
			// Move the li over, because we're not sure what we'll hit.
			$this->open_bbc[count($this->open_bbc) - 1]['after'] = '';
			$this->open_bbc[count($this->open_bbc) - 2]['after'] = '</li></ul>';
		}
	}

	/**
	 * Similar to $this->closeTags(), but only for inline tags.
	 * Operates directly on $this->message.
	 */
	protected function closeInlineTags(): void
	{
		$n = count($this->open_bbc) - 1;

		while (empty($this->open_bbc[$n]['block_level']) && $n >= 0) {
			$n--;
		}

		// Close all the non block level BBCodes so this BBCode isn't surrounded by them.
		for ($i = count($this->open_bbc) - 1; $i > $n; $i--) {
			$this->message = substr($this->message, 0, $this->pos) . "\n" . $this->open_bbc[$i]['after'] . "\n" . substr($this->message, $this->pos);

			$ot_strlen = strlen($this->open_bbc[$i]['after']);
			$this->pos += $ot_strlen + 2;
			$this->pos1 += $ot_strlen + 2;

			// Trim or eat trailing stuff...
			$whitespace_regex = '';

			if (!empty($this->open_bbc[$i]['block_level'])) {
				$whitespace_regex .= '(&nbsp;|\s)*(<br>)?';
			}

			if (!empty($this->open_bbc[$i]['trim']) && $this->open_bbc[$i]['trim'] != 'inside') {
				$whitespace_regex .= empty($this->open_bbc[$i]['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';
			}

			if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($this->message, $this->pos), $matches) != 0) {
				$this->message = substr($this->message, 0, $this->pos) . substr($this->message, $this->pos + strlen($matches[0]));
			}

			array_pop($this->open_bbc);
		}
	}

	/**
	 * Transforms a BBCode tag into HTML.
	 *
	 * Operates directly on $this->message.
	 *
	 * @param BBCode $bbc A cloned instance of the BBCode definition.
	 * @param array $params Parameter values to use.
	 */
	protected function transformToHtml(BBCodeInterface $bbc, array $params): void
	{
		// Insert Lang::$txt strings into the HTML output.
		foreach (['content', 'before', 'after'] as $key) {
			if (isset($bbc->{$key})) {
				$bbc->{$key} = self::insertTxt($bbc->{$key});
			}

			if (isset($bbc->{'disabled_' . $key})) {
				$bbc->{'disabled_' . $key} = self::insertTxt($bbc->{'disabled_' . $key});
			}
		}

		// We use this a lot.
		$tag_strlen = strlen($bbc->tag);

		// No type means 'parsed_content'.
		if (!isset($bbc->type)) {
			// There's no data to change, but maybe do something based on params?
			$data = [];

			$bbc->validate($bbc, $data, $this->disabled, $params);

			$this->open_bbc[] = $bbc;

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $bbc->before . "\n" . substr($this->message, $this->pos1);

			$this->pos += strlen($bbc->before) - 1 + 2;
		}
		// Don't parse the content, just skip it.
		elseif ($bbc->type == 'unparsed_content') {
			$pos2 = stripos($this->message, '[/' . substr($this->message, $this->pos + 1, $tag_strlen) . ']', $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$data = substr($this->message, $this->pos1, $pos2 - $this->pos1);

			if (!empty($bbc->block_level) && str_starts_with($data, '<br>')) {
				$data = substr($data, 4);
			}

			$bbc->validate($bbc, $data, $this->disabled, $params);

			$html = strtr($bbc->content, ['$1' => $data]);

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos2 + 3 + $tag_strlen);

			$this->pos += strlen($html) - 1 + 2;
			$this->last_pos = $this->pos + 1;
		}
		// Don't parse the content, just skip it.
		elseif ($bbc->type == 'unparsed_equals_content') {
			// The value may be quoted for some BBCodes - check.
			if (isset($bbc->quoted)) {
				// Anything passed through the preparser will use &quot;,
				// but we need to handle raw quotation marks too.
				$quot = substr($this->message, $this->pos1, 1) === '"' ? '"' : '&quot;';

				$quoted = substr($this->message, $this->pos1, strlen($quot)) == $quot;

				if ($bbc->quoted != 'optional' && !$quoted) {
					return;
				}

				if ($quoted) {
					$this->pos1 += strlen($quot);
				}
			} else {
				$quoted = false;
			}

			$pos2 = strpos($this->message, $quoted == false ? ']' : $quot . ']', $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$pos3 = stripos($this->message, '[/' . substr($this->message, $this->pos + 1, $tag_strlen) . ']', $pos2);

			if ($pos3 === false) {
				return;
			}

			$data = [
				substr($this->message, $pos2 + ($quoted == false ? 1 : 1 + strlen($quot)), $pos3 - ($pos2 + ($quoted == false ? 1 : 1 + strlen($quot)))),
				substr($this->message, $this->pos1, $pos2 - $this->pos1),
			];

			if (!empty($bbc->block_level) && str_starts_with($data[0], '<br>')) {
				$data[0] = substr($data[0], 4);
			}

			// Validation for my parking, please!
			$bbc->validate($bbc, $data, $this->disabled, $params);

			$html = strtr($bbc->content, ['$1' => $data[0], '$2' => $data[1]]);

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos3 + 3 + $tag_strlen);

			$this->pos += strlen($html) - 1 + 2;
		}
		// A closed BBCode, with no content or value.
		elseif ($bbc->type == 'closed') {
			$pos2 = strpos($this->message, ']', $this->pos);

			// Maybe a custom BBC wants to do something special?
			$data = [];

			$bbc->validate($bbc, $data, $this->disabled, $params);

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $bbc->content . "\n" . substr($this->message, $pos2 + 1);

			$this->pos += strlen($bbc->content) - 1 + 2;
		}
		// This one is sorta ugly... :/.  Unfortunately, it's needed for flash.
		elseif ($bbc->type == 'unparsed_commas_content') {
			$pos2 = strpos($this->message, ']', $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$pos3 = stripos($this->message, '[/' . substr($this->message, $this->pos + 1, $tag_strlen) . ']', $pos2);

			if ($pos3 === false) {
				return;
			}

			// We want $1 to be the content, and the rest to be csv.
			$data = explode(',', ',' . substr($this->message, $this->pos1, $pos2 - $this->pos1));
			$data[0] = substr($this->message, $pos2 + 1, $pos3 - $pos2 - 1);

			$bbc->validate($bbc, $data, $this->disabled, $params);

			$html = $bbc->content;

			foreach ($data as $k => $d) {
				$html = strtr($html, ['$' . ($k + 1) => trim($d)]);
			}

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos3 + 3 + $tag_strlen);

			$this->pos += strlen($html) - 1 + 2;
		}
		// This has parsed content, and a csv value which is unparsed.
		elseif ($bbc->type == 'unparsed_commas') {
			$pos2 = strpos($this->message, ']', $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$data = explode(',', substr($this->message, $this->pos1, $pos2 - $this->pos1));

			$bbc->validate($bbc, $data, $this->disabled, $params);

			// Fix after, for disabled code mainly.
			foreach ($data as $k => $d) {
				$bbc->after = strtr($bbc->after, ['$' . ($k + 1) => trim($d)]);
			}

			$this->open_bbc[] = $bbc;

			// Replace them out, $1, $2, $3, $4, etc.
			$html = $bbc->before;

			foreach ($data as $k => $d) {
				$html = strtr($html, ['$' . ($k + 1) => trim($d)]);
			}

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos2 + 1);

			$this->pos += strlen($html) - 1 + 2;
		}
		// A BBCode set to a value, parsed or not.
		elseif ($bbc->type == 'unparsed_equals' || $bbc->type == 'parsed_equals') {
			// The value may be quoted for some BBCodes - check.
			if (isset($bbc->quoted)) {
				// Will normally be '&quot;' but might be '"'.
				$quot = substr($this->message, $this->pos1, 1) === '"' ? '"' : '&quot;';

				$quoted = substr($this->message, $this->pos1, strlen($quot)) == $quot;

				if ($bbc->quoted != 'optional' && !$quoted) {
					return;
				}

				if ($quoted) {
					$this->pos1 += strlen($quot);
				}
			} else {
				$quoted = false;
			}

			if ($quoted) {
				$end_of_value = strpos($this->message, $quot . ']', $this->pos1);
				$nested_tag = strpos($this->message, '=' . $quot, $this->pos1);

				// Check so this is not just an quoted url ending with a =
				if ($nested_tag && substr($this->message, $nested_tag, 2 + strlen($quot)) == '=' . $quot . ']') {
					$nested_tag = false;
				}

				if ($nested_tag && $nested_tag < $end_of_value) {
					// Nested BBCode with quoted value detected, use next end tag
					$nested_tag_pos = strpos($this->message, $quoted == false ? ']' : $quot . ']', $this->pos1) + strlen($quot);
				}
			}

			$pos2 = strpos($this->message, $quoted == false ? ']' : $quot . ']', $nested_tag_pos ?? $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$data = substr($this->message, $this->pos1, $pos2 - $this->pos1);

			// Validation for my parking, please!
			$bbc->validate($bbc, $data, $this->disabled, $params);

			// For parsed content, we must recurse to avoid security problems.
			if ($bbc->type != 'unparsed_equals') {
				$smileys = $this->smileys;
				$parse_tags = $this->parse_tags;

				$this->smileys = empty($bbc->parsed_tags_allowed);
				$this->parse_tags = !empty($bbc->parsed_tags_allowed) ? $bbc->parsed_tags_allowed : [];

				$data = $this->parse($data);

				$this->smileys = $smileys;
				$this->parse_tags = $parse_tags;
			}

			$bbc->after = strtr($bbc->after, ['$1' => $data]);

			$this->open_bbc[] = $bbc;

			$html = strtr($bbc->before, ['$1' => $data]);

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos2 + ($quoted == false ? 1 : 1 + strlen($quot)));

			$this->pos += strlen($html) - 1 + 2;
		}

		// If this is block level, eat any breaks after it.
		if (!empty($bbc->block_level) && substr($this->message, $this->pos + 1, 4) == '<br>') {
			$this->message = substr($this->message, 0, $this->pos + 1) . substr($this->message, $this->pos + 5);
		}

		// Are we trimming outside this BBCode?
		if (!empty($bbc->trim) && $bbc->trim != 'outside' && preg_match('~(<br>|&nbsp;|\s)*~', substr($this->message, $this->pos + 1), $matches) != 0) {
			$this->message = substr($this->message, 0, $this->pos + 1) . substr($this->message, $this->pos + 1 + strlen($matches[0]));
		}
	}

	/**
	 * Helper for unparse().
	 *
	 * Returns an array of attributes associated with a tag.
	 *
	 * @param string $string A tag
	 * @return array An array of attributes
	 */
	protected function fetchTagAttributes(string $string): array
	{
		$attribs = [];
		$key = $value = '';
		$tag_state = 0; // 0 = key, 1 = attribute with no string, 2 = attribute with string

		for ($i = 0; $i < strlen($string); $i++) {
			// We're either moving from the key to the attribute or we're in a string and this is fine.
			if ($string[$i] == '=') {
				if ($tag_state == 0) {
					$tag_state = 1;
				} elseif ($tag_state == 2) {
					$value .= '=';
				}
			}
			// A space is either moving from an attribute back to a potential key or in a string is fine.
			elseif ($string[$i] == ' ') {
				if ($tag_state == 2) {
					$value .= ' ';
				} elseif ($tag_state == 1) {
					$attribs[$key] = $value;
					$key = $value = '';
					$tag_state = 0;
				}
			}
			// A quote?
			elseif ($string[$i] == '"') {
				// Must be either going into or out of a string.
				if ($tag_state == 1) {
					$tag_state = 2;
				} else {
					$tag_state = 1;
				}
			}
			// Otherwise it's fine.
			else {
				if ($tag_state == 0) {
					$key .= $string[$i];
				} else {
					$value .= $string[$i];
				}
			}
		}

		// Anything left?
		if ($key != '' && $value != '') {
			$attribs[$key] = $value;
		}

		return $attribs;
	}

	/**
	 * Helper for unparse().
	 *
	 * Attempt to clean up illegal BBC caused by browsers like Opera that don't
	 * obey the rules.
	 *
	 * @param string $string Text
	 * @return string Cleaned up text
	 */
	protected function legalise(string $string): string
	{
		// Don't care about the texts that are too short.
		if (strlen($string) < 3) {
			return $string;
		}

		// A list of BBCodes that have been disabled by the admin.
		$disabled = empty(Config::$modSettings['disabledBBC']) ? [] : array_flip(explode(',', strtolower(Config::$modSettings['disabledBBC'])));

		// Get a list of all the BBCodes that are not disabled.
		$all_bbc = self::getCodes();
		$valid_tags = [];
		$self_closing_tags = [];

		foreach ($all_bbc as $bbc) {
			if (!isset($disabled[$bbc->tag])) {
				$valid_tags[$bbc->tag] = !empty($bbc->block_level);
			}

			if (isset($bbc->type) && $bbc->type == 'closed') {
				$self_closing_tags[] = $bbc->tag;
			}
		}

		// Right - we're going to start by going through the whole lot to make sure we don't have align stuff crossed as this happens load and is stupid!
		$align_tags = ['left', 'center', 'right', 'pre'];

		// Remove those align tags that are not valid.
		$align_tags = array_intersect($align_tags, array_keys($valid_tags));

		// These keep track of where we are!
		if (!empty($align_tags) && count($matches = preg_split('~(\[/?(?:' . implode('|', $align_tags) . ')\])~', $string, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1) {
			// The first one is never a tag.
			$is_tag = false;

			// By default we're not inside a tag too.
			$inside_tag = null;

			foreach ($matches as $i => $match) {
				// We're only interested in tags, not text.
				if ($is_tag) {
					$is_closing_tag = substr($match, 1, 1) === '/';
					$tag_name = substr($match, $is_closing_tag ? 2 : 1, -1);

					// We're closing the exact same tag that we opened.
					if ($is_closing_tag && $inside_tag === $tag_name) {
						$inside_tag = null;
					}
					// We're opening a tag and we're not yet inside one either
					elseif (!$is_closing_tag && $inside_tag === null) {
						$inside_tag = $tag_name;
					}
					// In all other cases, this tag must be invalid
					else {
						unset($matches[$i]);
					}
				}

				// The next one is gonna be the other one.
				$is_tag = !$is_tag;
			}

			// We're still inside a tag and had no chance for closure?
			if ($inside_tag !== null) {
				$matches[] = '[/' . $inside_tag . ']';
			}

			// And a complete text string again.
			$string = implode('', $matches);
		}

		// Quickly remove any tags which are back to back.
		$back_to_back_pattern = '~\[(' . implode('|', array_diff(array_keys($valid_tags), ['td', 'anchor'])) . ')[^<>\[\]]*\]\s*\[/\1\]~';

		$lastlen = 0;

		while (strlen($string) !== $lastlen) {
			$lastlen = strlen($string = preg_replace($back_to_back_pattern, '', $string));
		}

		// Need to sort the BBCodes by name length.
		uksort(
			$valid_tags,
			fn($a, $b) => strlen($a) <=> strlen($b),
		);

		// These inline BBCodes can compete with each other regarding style.
		$competing_tags = [
			'color',
			'size',
		];

		// These keep track of where we are!
		if (count($parts = preg_split(sprintf('~(\[)(/?)(%1$s)((?:[\s=][^\]\[]*)?\])~', implode('|', array_keys($valid_tags))), $string, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1) {
			// Start outside [nobbc] or [code] blocks.
			$in_code = false;
			$in_nobbc = false;

			// A buffer containing all opened inline elements.
			$inline_elements = [];

			// A buffer containing all opened block elements.
			$block_elements = [];

			// A buffer containing the opened inline elements that might compete.
			$competing_elements = [];

			// $i: text, $i + 1: '[', $i + 2: '/', $i + 3: tag, $i + 4: tag tail.
			for ($i = 0, $n = count($parts) - 1; $i < $n; $i += 5) {
				$tag = $parts[$i + 3];
				$is_opening_tag = $parts[$i + 2] === '';
				$is_closing_tag = $parts[$i + 2] === '/';
				$is_block_level_tag = isset($valid_tags[$tag]) && $valid_tags[$tag] && !in_array($tag, $self_closing_tags);
				$is_competing_tag = in_array($tag, $competing_tags);

				// Check if this might be one of those cleaned out tags.
				if ($tag === '') {
					continue;
				}

				// Special case: inside [code] blocks any code is left untouched.
				if ($tag === 'code') {
					// We're inside a code block and closing it.
					if ($in_code && $is_closing_tag) {
						$in_code = false;

						// Reopen BBCodes that were closed before the code block.
						if (!empty($inline_elements)) {
							$parts[$i + 4] .= '[' . implode('][', array_keys($inline_elements)) . ']';
						}
					}
					// We're outside a coding and nobbc block and opening it.
					elseif (!$in_code && !$in_nobbc && $is_opening_tag) {
						// If there are still inline elements left open, close them now.
						if (!empty($inline_elements)) {
							$parts[$i] .= '[/' . implode('][/', array_reverse($inline_elements)) . ']';
						}

						$in_code = true;
					}

					// Nothing further to do.
					continue;
				}

				// Special case: inside [nobbc] blocks any BBC is left untouched.
				if ($tag === 'nobbc') {
					// We're inside a nobbc block and closing it.
					if ($in_nobbc && $is_closing_tag) {
						$in_nobbc = false;

						// Some inline elements might've been closed that need reopening.
						if (!empty($inline_elements)) {
							$parts[$i + 4] .= '[' . implode('][', array_keys($inline_elements)) . ']';
						}
					}
					// We're outside a nobbc and coding block and opening it.
					elseif (!$in_nobbc && !$in_code && $is_opening_tag) {
						// Can't have inline elements still opened.
						if (!empty($inline_elements)) {
							$parts[$i] .= '[/' . implode('][/', array_reverse($inline_elements)) . ']';
						}

						$in_nobbc = true;
					}

					continue;
				}

				// So, we're inside one of the special blocks: ignore any tag.
				if ($in_code || $in_nobbc) {
					continue;
				}

				// We're dealing with an opening tag.
				if ($is_opening_tag) {
					// Everything inside the square brackets of the opening tag.
					$element_content = $parts[$i + 3] . substr($parts[$i + 4], 0, -1);

					// A block level opening tag.
					if ($is_block_level_tag) {
						// Are there inline elements still open?
						if (!empty($inline_elements)) {
							// Close all the inline BBCodes, a block BBCode is coming...
							$parts[$i] .= '[/' . implode('][/', array_reverse($inline_elements)) . ']';

							// Now open them again, we're inside the block BBCode now.
							$parts[$i + 5] = '[' . implode('][', array_keys($inline_elements)) . ']' . $parts[$i + 5];
						}

						$block_elements[] = $tag;
					}
					// Inline opening tag.
					elseif (!in_array($tag, $self_closing_tags)) {
						// Can't have two opening elements with the same contents!
						if (isset($inline_elements[$element_content])) {
							// Get rid of this tag.
							$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';

							// Now try to find the corresponding closing tag.
							$cur_level = 1;

							for ($j = $i + 5, $m = count($parts) - 1; $j < $m; $j += 5) {
								// Find the BBCodes with the same tag
								if ($parts[$j + 3] === $tag) {
									// If it's an opening tag, increase the level.
									if ($parts[$j + 2] === '') {
										$cur_level++;
									}
									// A closing tag, decrease the level.
									else {
										$cur_level--;

										// Gotcha! Clean out this closing tag gone rogue.
										if ($cur_level === 0) {
											$parts[$j + 1] = $parts[$j + 2] = $parts[$j + 3] = $parts[$j + 4] = '';
											break;
										}
									}
								}
							}
						}
						// Otherwise, add this one to the list.
						else {
							if ($is_competing_tag) {
								if (!isset($competing_elements[$tag])) {
									$competing_elements[$tag] = [];
								}

								$competing_elements[$tag][] = $parts[$i + 4];

								if (count($competing_elements[$tag]) > 1) {
									$parts[$i] .= '[/' . $tag . ']';
								}
							}

							$inline_elements[$element_content] = $tag;
						}
					}
				}
				// Closing tag.
				else {
					// Closing the block tag.
					if ($is_block_level_tag) {
						// Close the elements that should've been closed by closing this tag.
						if (!empty($block_elements)) {
							$add_closing_tags = [];

							while ($element = array_pop($block_elements)) {
								if ($element === $tag) {
									break;
								}

								// Still a block tag was open not equal to this tag.
								$add_closing_tags[] = $element['type'];
							}

							if (!empty($add_closing_tags)) {
								$parts[$i + 1] = '[/' . implode('][/', array_reverse($add_closing_tags)) . ']' . $parts[$i + 1];
							}

							// Apparently the closing tag was not found on the stack.
							if (!is_string($element) || $element !== $tag) {
								// Get rid of this particular closing tag, it was never opened.
								$parts[$i + 1] = substr($parts[$i + 1], 0, -1);
								$parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';

								continue;
							}
						} else {
							// Get rid of this closing tag!
							$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';

							continue;
						}

						// Inline elements are still left opened?
						if (!empty($inline_elements)) {
							// Close them first..
							$parts[$i] .= '[/' . implode('][/', array_reverse($inline_elements)) . ']';

							// Then reopen them.
							$parts[$i + 5] = '[' . implode('][', array_keys($inline_elements)) . ']' . $parts[$i + 5];
						}
					}
					// Inline tag.
					else {
						// Are we expecting this tag to end?
						if (in_array($tag, $inline_elements)) {
							foreach (array_reverse($inline_elements, true) as $tag_content_to_be_closed => $tag_to_be_closed) {
								// Closing it one way or the other.
								unset($inline_elements[$tag_content_to_be_closed]);

								// Was this the tag we were looking for?
								if ($tag_to_be_closed === $tag) {
									break;
								}

								// Nope, close it and look further!
								$parts[$i] .= '[/' . $tag_to_be_closed . ']';
							}

							if ($is_competing_tag && !empty($competing_elements[$tag])) {
								array_pop($competing_elements[$tag]);

								if (count($competing_elements[$tag]) > 0) {
									$parts[$i + 5] = '[' . $tag . $competing_elements[$tag][count($competing_elements[$tag]) - 1] . $parts[$i + 5];
								}
							}
						}
						// Unexpected closing tag, ex-ter-mi-nate.
						else {
							$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
						}
					}
				}
			}

			// Close the code BBCodes.
			if ($in_code) {
				$parts[$i] .= '[/code]';
			}
			// The same for nobbc BBCodes.
			elseif ($in_nobbc) {
				$parts[$i] .= '[/nobbc]';
			}
			// Still inline BBCodes left unclosed? Close them now, better late than never.
			elseif (!empty($inline_elements)) {
				$parts[$i] .= '[/' . implode('][/', array_reverse($inline_elements)) . ']';
			}

			// Now close the block elements.
			if (!empty($block_elements)) {
				$parts[$i] .= '[/' . implode('][/', array_reverse($block_elements)) . ']';
			}

			$string = implode('', $parts);
		}

		// Final clean up of back-to-back tags.
		$lastlen = 0;

		while (strlen($string) !== $lastlen) {
			$lastlen = strlen($string = preg_replace($back_to_back_pattern, '', $string));
		}

		return $string;
	}

	/**
	 * Resets certain runtime properties to their default values.
	 */
	protected function resetRuntimeProperties(): void
	{
		// Reset these properties.
		$to_reset = [
			'message',
			'bbc_codes',
			'parse_tags',
			'open_bbc',
			'inside',
			'pos',
			'last_pos',
			'placeholders',
			'placeholders_counter',
		];

		$class_vars = get_class_vars(__CLASS__);

		foreach ($to_reset as $var) {
			unset($this->{$var});
			$this->{$var} = $class_vars[$var];
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Replaces all items in self::$codes with BBCodeInterface instances.
	 */
	protected static function loadBBCodeClasses(): void
	{
		foreach (self::$codes as $key => $code) {
			if ($code instanceof BBCodeInterface) {
				continue;
			}

			if (!isset($code['class'])) {
				self::$codes[$key] = new GenericBBCode($code);
			} elseif (class_exists($code['class'])) {
				self::$codes[$key] = new $code['class']();
			}
		}
	}

	/**
	 * Wrapper for the integrate_bbc_codes hook.
	 * Prevents duplication in self::$codes.
	 */
	private static function integrateBBC(): void
	{
		// Only do this once.
		if (self::$integrate_bbc_codes_done !== true) {
			IntegrationHook::call('integrate_bbc_codes', [&self::$codes, &Autolinker::$no_autolink_tags]);

			// Load the classes for any newly added BBCode definitions.
			self::loadBBCodeClasses();

			// Prevent duplicates.
			$temp = [];

			// Reverse order because mods typically append to the array.
			foreach (array_reverse(array_keys(self::$codes)) as $i) {
				$value = self::$codes[$i];

				// Closures cannot be serialized, but they can be reflected.
				if (($value->validate ?? null) instanceof \Closure) {
					$value->validate = (string) new \ReflectionFunction($value->validate);
				}

				$serialized = serialize($value);

				if (!in_array($serialized, $temp)) {
					$temp[] = $serialized;
				} else {
					unset(self::$codes[$i]);
				}
			}

			self::$integrate_bbc_codes_done = true;
		}
	}
}

?>