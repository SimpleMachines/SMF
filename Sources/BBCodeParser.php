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
use SMF\Db\DatabaseApi as Db;

/**
 * Parses Bulletin Board Code in a string and converts it to HTML.
 *
 * The recommended way to use this class to parse BBCode in a string is:
 *
 *     $parsed_string = BBCodeParser::load()->parse($unparsed_string);
 *
 * Calling the load() method like this will save on memory by reusing a single
 * instance of the BBCodeParser class. However, if you need more control over
 * the parser, you can always instantiate a new one.
 *
 * The recommended way to get a list of supported BBCodes is:
 *
 *     $codes = BBCodeParser::getCodes();
 *
 * Calling the getCodes() method is better than reading BBCodeParser::$codes
 * directly, because the results of the method will include any BBC added by
 * mods, whereas BBCodeParser::$codes will not.
 *
 * The following integration hooks are called during object construction:
 *
 *     integrate_bbc_codes            (Used to add or modify BBC)
 *     integrate_smileys              (Used for alternative smiley handling)
 *
 * The following integration hooks are called during parsing:
 *
 *     integrate_pre_parsebbc         (Allows adjustments before parsing)
 *     integrate_post_parsebbc        (Gives access to results of parsing)
 *     integrate_attach_bbc_validate  (Adjusts HTML produced by the attach BBC)
 *     integrate_bbc_print            (For BBC that need special handling in
 *                                        print mode)
 */
class BBCodeParser
{
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
	 * @var bool
	 *
	 * Whether BBCode should be parsed.
	 */
	public bool $enable_bbc;

	/**
	 * @var bool
	 *
	 * Whether to allow certain basic HTML tags in the input.
	 */
	public bool $enable_post_html;

	/**
	 * @var array
	 *
	 * List of disabled BBCode tags.
	 */
	public array $disabled = [];

	/**
	 * @var bool
	 *
	 * Whether smileys should be parsed.
	 */
	public bool $smileys = true;

	/**
	 * @var string
	 *
	 * The smiley set to use when parsing smileys.
	 */
	public string $smiley_set;

	/**
	 * @var bool
	 *
	 * Whether custom smileys are enabled.
	 */
	public bool $custom_smileys_enabled;

	/**
	 * @var string
	 *
	 * URL of the base smileys directory.
	 */
	public string $smileys_url;

	/**
	 * @var string
	 *
	 * The character encoding of the strings to be parsed.
	 */
	public string $encoding = 'UTF-8';

	/**
	 * @var bool
	 *
	 * Shorthand check for whether character encoding is UTF-8.
	 */
	public bool $utf8 = true;

	/**
	 * @var string
	 *
	 * Language locale to use.
	 */
	public string $locale = 'en_US';

	/**
	 * @var int
	 *
	 * User's time offset from UTC.
	 */
	public int $time_offset;

	/**
	 * @var string
	 *
	 * User's strftime format.
	 */
	public string $time_format;

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
	 * Definitions of supported BBCodes.
	 *
	 * NOTE: Although BBCodeParser::$codes is public in order to allow maximum
	 * flexibility, you should call the BBCodeParser::getCodes() method if you
	 * want to read this list. Calling the method will ensure that any BBCodes
	 * added by modifications are included in the returned array.
	 *
	 * The BBCode definitions are formatted as an array, with keys as follows:
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
	 *	  $1 is replaced with the content of the tag.  Parameters
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
	 *	  when the tag is closed.
	 *
	 *	disabled_content: Used in place of content when the tag is
	 *	  disabled.  For closed, default is '', otherwise it is '$1' if
	 *	  block_level is false, '<div>$1</div>' elsewise.
	 *
	 *	disabled_before: Used in place of before when disabled.  Defaults
	 *	  to '<div>' if block_level, '' if not.
	 *
	 *	disabled_after: Used in place of after when disabled.  Defaults
	 *	  to '</div>' if block_level, '' if not.
	 *
	 *	block_level: Set to true the tag is a "block level" tag, similar
	 *	  to HTML.  Block level tags cannot be nested inside tags that are
	 *	  not block level, and will not be implicitly closed as easily.
	 *	  One break following a block level tag may also be removed.
	 *
	 *	trim: If set to 'inside', whitespace after the begin tag will be
	 *	  removed.  If set to 'outside', whitespace after the end tag will
	 *	  meet the same fate.
	 *
	 *	validate: A callback to validate the data as $data. Four arguments
	 *    will be passed to the callback: &$tag, &$data, $disabled, $params.
	 *    Depending on the tag's type, $data may be a string or an array of
	 *    strings (corresponding to the replacement.)
	 *
	 *	quoted: When type is 'unparsed_equals' or 'parsed_equals' only,
	 *	  may be not set, 'optional', or 'required' corresponding to if
	 *	  the content may be quoted. This allows the parser to read
	 *	  [tag="abc]def[esdf]"] properly.
	 *
	 *	require_parents: An array of tag names, or not set.  If set, the
	 *	  enclosing tag *must* be one of the listed tags, or parsing won't
	 *	  occur.
	 *
	 *	require_children: Similar to require_parents, if set children
	 *	  won't be parsed if they are not in the list.
	 *
	 *	disallow_children: Similar to, but very different from,
	 *	  require_children, if it is set the listed tags will not be
	 *	  parsed inside the tag.
	 *
	 *	parsed_tags_allowed: An array restricting what BBC can be in the
	 *	  parsed_equals parameter, if desired.
	 */
	public static array $codes = [
		[
			'tag' => 'abbr',
			'type' => 'unparsed_equals',
			'before' => '<abbr title="$1">',
			'after' => '</abbr>',
			'quoted' => 'optional',
			'disabled_after' => ' ($1)',
		],
		// Legacy (and just an alias for [abbr] even when enabled)
		[
			'tag' => 'acronym',
			'type' => 'unparsed_equals',
			'before' => '<abbr title="$1">',
			'after' => '</abbr>',
			'quoted' => 'optional',
			'disabled_after' => ' ($1)',
		],
		[
			'tag' => 'anchor',
			'type' => 'unparsed_equals',
			'test' => '[#]?([A-Za-z][A-Za-z0-9_\-]*)\]',
			'before' => '<span id="post_$1">',
			'after' => '</span>',
		],
		[
			'tag' => 'attach',
			'type' => 'unparsed_content',
			'parameters' => [
				'id' => ['match' => '(\d+)'],
				'alt' => ['optional' => true],
				'width' => ['optional' => true, 'match' => '(\d+)'],
				'height' => ['optional' => true, 'match' => '(\d+)'],
				'display' => ['optional' => true, 'match' => '(link|embed)'],
			],
			'content' => '$1',
			'validate' => __CLASS__ . '::attachValidate',
		],
		[
			'tag' => 'b',
			'before' => '<b>',
			'after' => '</b>',
		],
		// Legacy (equivalent to [ltr] or [rtl])
		[
			'tag' => 'bdo',
			'type' => 'unparsed_equals',
			'before' => '<bdo dir="$1">',
			'after' => '</bdo>',
			'test' => '(rtl|ltr)\]',
			'block_level' => true,
		],
		// Legacy (alias of [color=black])
		[
			'tag' => 'black',
			'before' => '<span style="color: black;" class="bbc_color">',
			'after' => '</span>',
		],
		// Legacy (alias of [color=blue])
		[
			'tag' => 'blue',
			'before' => '<span style="color: blue;" class="bbc_color">',
			'after' => '</span>',
		],
		[
			'tag' => 'br',
			'type' => 'closed',
			'content' => '<br>',
		],
		[
			'tag' => 'center',
			'before' => '<div class="centertext"><div class="inline-block">',
			'after' => '</div></div>',
			'block_level' => true,
		],
		[
			'tag' => 'code',
			'type' => 'unparsed_content',
			'content' => '<div class="codeheader"><span class="code">{txt_code}</span> <a class="codeoperation smf_select_text">{txt_code_select}</a> <a class="codeoperation smf_expand_code hidden" data-shrink-txt="{txt_code_shrink}" data-expand-txt="{txt_code_expand}">{txt_code_expand}</a></div><code class="bbc_code">$1</code>',
			'validate' => __CLASS__ . '::codeValidate',
			'block_level' => true,
		],
		[
			'tag' => 'code',
			'type' => 'unparsed_equals_content',
			'content' => '<div class="codeheader"><span class="code">{txt_code}</span> ($2) <a class="codeoperation smf_select_text">{txt_code_select}</a> <a class="codeoperation smf_expand_code hidden" data-shrink-txt="{txt_code_shrink}" data-expand-txt="{txt_code_expand}">{txt_code_expand}</a></div><code class="bbc_code">$1</code>',
			'validate' => __CLASS__ . '::codeValidate',
			'block_level' => true,
		],
		[
			'tag' => 'color',
			'type' => 'unparsed_equals',
			'test' => '(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[A-Za-z]{1,20}|rgb\((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\s?,\s?){2}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\))\]',
			'before' => '<span style="color: $1;" class="bbc_color">',
			'after' => '</span>',
		],
		[
			'tag' => 'email',
			'type' => 'unparsed_content',
			'content' => '<a href="mailto:$1" class="bbc_email">$1</a>',
			'validate' => __CLASS__ . '::emailValidate',
		],
		[
			'tag' => 'email',
			'type' => 'unparsed_equals',
			'before' => '<a href="mailto:$1" class="bbc_email">',
			'after' => '</a>',
			'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
			'disabled_after' => ' ($1)',
		],
		// Legacy (and just a link even when not disabled)
		[
			'tag' => 'flash',
			'type' => 'unparsed_commas_content',
			'test' => '\d+,\d+\]',
			'content' => '<a href="$1" target="_blank" rel="noopener">$1</a>',
			'validate' => __CLASS__ . '::flashValidate',
		],
		[
			'tag' => 'float',
			'type' => 'unparsed_equals',
			'test' => '(left|right)(\s+max=\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)?\]',
			'before' => '<div $1>',
			'after' => '</div>',
			'validate' => __CLASS__ . '::floatValidate',
			'trim' => 'outside',
			'block_level' => true,
		],
		// Legacy (alias of [url] with an FTP URL)
		[
			'tag' => 'ftp',
			'type' => 'unparsed_content',
			'content' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>',
			'validate' => __CLASS__ . '::ftpValidate',
		],
		// Legacy (alias of [url] with an FTP URL)
		[
			'tag' => 'ftp',
			'type' => 'unparsed_equals',
			'before' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">',
			'after' => '</a>',
			'validate' => __CLASS__ . '::ftpValidate',
			'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
			'disabled_after' => ' ($1)',
		],
		[
			'tag' => 'font',
			'type' => 'unparsed_equals',
			'test' => '[A-Za-z0-9_,\-\s]+?\]',
			'before' => '<span style="font-family: $1;" class="bbc_font">',
			'after' => '</span>',
		],
		// Legacy (one of those things that should not be done)
		[
			'tag' => 'glow',
			'type' => 'unparsed_commas',
			'test' => '[#0-9a-zA-Z\-]{3,12},([012]\d{1,2}|\d{1,2})(,[^]]+)?\]',
			'before' => '<span style="text-shadow: $1 1px 1px 1px">',
			'after' => '</span>',
		],
		// Legacy (alias of [color=green])
		[
			'tag' => 'green',
			'before' => '<span style="color: green;" class="bbc_color">',
			'after' => '</span>',
		],
		[
			'tag' => 'html',
			'type' => 'unparsed_content',
			'content' => '<div>$1</div>',
			'block_level' => true,
			'disabled_content' => '$1',
		],
		[
			'tag' => 'hr',
			'type' => 'closed',
			'content' => '<hr>',
			'block_level' => true,
		],
		[
			'tag' => 'i',
			'before' => '<i>',
			'after' => '</i>',
		],
		[
			'tag' => 'img',
			'type' => 'unparsed_content',
			'parameters' => [
				'alt' => ['optional' => true],
				'title' => ['optional' => true],
				'width' => ['optional' => true, 'value' => ' width="$1"', 'match' => '(\d+)'],
				'height' => ['optional' => true, 'value' => ' height="$1"', 'match' => '(\d+)'],
			],
			'content' => '$1',
			'validate' => __CLASS__ . '::imgValidate',
			'disabled_content' => '($1)',
		],
		[
			'tag' => 'iurl',
			'type' => 'unparsed_content',
			'content' => '<a href="$1" class="bbc_link">$1</a>',
			'validate' => __CLASS__ . '::urlValidate',
		],
		[
			'tag' => 'iurl',
			'type' => 'unparsed_equals',
			'quoted' => 'optional',
			'before' => '<a href="$1" class="bbc_link">',
			'after' => '</a>',
			'validate' => __CLASS__ . '::urlValidate',
			'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
			'disabled_after' => ' ($1)',
		],
		[
			'tag' => 'justify',
			'before' => '<div class="justifytext">',
			'after' => '</div>',
			'block_level' => true,
		],
		[
			'tag' => 'left',
			'before' => '<div class="lefttext">',
			'after' => '</div>',
			'block_level' => true,
		],
		[
			'tag' => 'li',
			'before' => '<li>',
			'after' => '</li>',
			'trim' => 'outside',
			'require_parents' => ['list'],
			'block_level' => true,
			'disabled_before' => '',
			'disabled_after' => '<br>',
		],
		[
			'tag' => 'list',
			'before' => '<ul class="bbc_list">',
			'after' => '</ul>',
			'trim' => 'inside',
			'require_children' => ['li', 'list'],
			'block_level' => true,
		],
		[
			'tag' => 'list',
			'parameters' => [
				'type' => ['match' => '(none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|upper-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha)'],
			],
			'before' => '<ul class="bbc_list" style="list-style-type: {type};">',
			'after' => '</ul>',
			'trim' => 'inside',
			'require_children' => ['li'],
			'block_level' => true,
		],
		[
			'tag' => 'ltr',
			'before' => '<bdo dir="ltr">',
			'after' => '</bdo>',
			'block_level' => true,
		],
		[
			'tag' => 'me',
			'type' => 'unparsed_equals',
			'before' => '<div class="meaction">* $1 ',
			'after' => '</div>',
			'quoted' => 'optional',
			'block_level' => true,
			'disabled_before' => '/me ',
			'disabled_after' => '<br>',
		],
		[
			'tag' => 'member',
			'type' => 'unparsed_equals',
			'before' => '<a href="{scripturl}?action=profile;u=$1" class="mention" data-mention="$1">@',
			'after' => '</a>',
		],
		// Legacy (horrible memories of the 1990s)
		[
			'tag' => 'move',
			'before' => '<marquee>',
			'after' => '</marquee>',
			'block_level' => true,
			'disallow_children' => ['move'],
		],
		[
			'tag' => 'nobbc',
			'type' => 'unparsed_content',
			'content' => '$1',
		],
		// This one only exists to prevent autolinking in its content.
		[
			'tag' => 'nolink',
			'before' => '',
			'after' => '',
		],
		[
			'tag' => 'php',
			'type' => 'unparsed_content',
			'content' => '<span class="phpcode">$1</span>',
			'validate' => __CLASS__ . '::phpValidate',
			'block_level' => false,
			'disabled_content' => '$1',
		],
		[
			'tag' => 'pre',
			'before' => '<pre>',
			'after' => '</pre>',
		],
		[
			'tag' => 'quote',
			'before' => '<blockquote><cite>{txt_quote}</cite>',
			'after' => '</blockquote>',
			'trim' => 'both',
			'block_level' => true,
		],
		[
			'tag' => 'quote',
			'parameters' => [
				'author' => ['match' => '(.{1,192}?)', 'quoted' => true],
			],
			'before' => '<blockquote><cite>{txt_quote_from}: {author}</cite>',
			'after' => '</blockquote>',
			'trim' => 'both',
			'block_level' => true,
		],
		[
			'tag' => 'quote',
			'type' => 'parsed_equals',
			'before' => '<blockquote><cite>{txt_quote_from}: $1</cite>',
			'after' => '</blockquote>',
			'trim' => 'both',
			'quoted' => 'optional',
			// Don't allow everything to be embedded with the author name.
			'parsed_tags_allowed' => ['url', 'iurl', 'ftp'],
			'block_level' => true,
		],
		[
			'tag' => 'quote',
			'parameters' => [
				'author' => ['match' => '([^<>]{1,192}?)'],
				'link' => ['match' => '(?:board=\d+;)?((?:topic|threadid)=[\dmsg#\./]{1,40}(?:;start=[\dmsg#\./]{1,40})?|msg=\d+?|action=profile;u=\d+)'],
				'date' => ['match' => '(\d+)', 'validate' => 'SMF\\Time::timeformat'],
			],
			'before' => '<blockquote><cite><a href="{scripturl}?{link}">{txt_quote_from}: {author} {txt_search_on} {date}</a></cite>',
			'after' => '</blockquote>',
			'trim' => 'both',
			'block_level' => true,
		],
		[
			'tag' => 'quote',
			'parameters' => [
				'author' => ['match' => '(.{1,192}?)'],
			],
			'before' => '<blockquote><cite>{txt_quote_from}: {author}</cite>',
			'after' => '</blockquote>',
			'trim' => 'both',
			'block_level' => true,
		],
		// Legacy (alias of [color=red])
		[
			'tag' => 'red',
			'before' => '<span style="color: red;" class="bbc_color">',
			'after' => '</span>',
		],
		[
			'tag' => 'right',
			'before' => '<div class="righttext"><div class="inline-block">',
			'after' => '</div></div>',
			'block_level' => true,
		],
		[
			'tag' => 'rtl',
			'before' => '<bdo dir="rtl">',
			'after' => '</bdo>',
			'block_level' => true,
		],
		[
			'tag' => 's',
			'before' => '<s>',
			'after' => '</s>',
		],
		// Legacy (never a good idea)
		[
			'tag' => 'shadow',
			'type' => 'unparsed_commas',
			'test' => '[#0-9a-zA-Z\-]{3,12},(left|right|top|bottom|[0123]\d{0,2})\]',
			'before' => '<span style="text-shadow: $1 $2">',
			'after' => '</span>',
			'validate' => __CLASS__ . '::shadowValidate',
		],
		[
			'tag' => 'size',
			'type' => 'unparsed_equals',
			'test' => '([1-9][\d]?p[xt]|small(?:er)?|large[r]?|x[x]?-(?:small|large)|medium|(0\.[1-9]|[1-9](\.[\d][\d]?)?)?em)\]',
			'before' => '<span style="font-size: $1;" class="bbc_size">',
			'after' => '</span>',
		],
		[
			'tag' => 'size',
			'type' => 'unparsed_equals',
			'test' => '[1-7]\]',
			'before' => '<span style="font-size: $1;" class="bbc_size">',
			'after' => '</span>',
			'validate' => __CLASS__ . '::sizeValidate',
		],
		[
			'tag' => 'sub',
			'before' => '<sub>',
			'after' => '</sub>',
		],
		[
			'tag' => 'sup',
			'before' => '<sup>',
			'after' => '</sup>',
		],
		[
			'tag' => 'table',
			'before' => '<table class="bbc_table">',
			'after' => '</table>',
			'trim' => 'inside',
			'require_children' => ['tr'],
			'block_level' => true,
		],
		[
			'tag' => 'td',
			'before' => '<td>',
			'after' => '</td>',
			'require_parents' => ['tr'],
			'trim' => 'outside',
			'block_level' => true,
			'disabled_before' => '',
			'disabled_after' => '',
		],
		[
			'tag' => 'time',
			'type' => 'unparsed_content',
			'content' => '$1',
			'validate' => __CLASS__ . '::timeValidate',
		],
		[
			'tag' => 'tr',
			'before' => '<tr>',
			'after' => '</tr>',
			'require_parents' => ['table'],
			'require_children' => ['td'],
			'trim' => 'both',
			'block_level' => true,
			'disabled_before' => '',
			'disabled_after' => '',
		],
		// Legacy (the <tt> element is dead)
		[
			'tag' => 'tt',
			'before' => '<span class="monospace">',
			'after' => '</span>',
		],
		[
			'tag' => 'u',
			'before' => '<u>',
			'after' => '</u>',
		],
		[
			'tag' => 'url',
			'type' => 'unparsed_content',
			'content' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>',
			'validate' => __CLASS__ . '::urlValidate',
		],
		[
			'tag' => 'url',
			'type' => 'unparsed_equals',
			'quoted' => 'optional',
			'before' => '<a href="$1" class="bbc_link" target="_blank" rel="noopener">',
			'after' => '</a>',
			'validate' => __CLASS__ . '::urlValidate',
			'disallow_children' => ['email', 'ftp', 'url', 'iurl'],
			'disabled_after' => ' ($1)',
		],
		// Legacy (alias of [color=white])
		[
			'tag' => 'white',
			'before' => '<span style="color: white;" class="bbc_color">',
			'after' => '</span>',
		],
		[
			'tag' => 'youtube',
			'type' => 'unparsed_content',
			'content' => '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube.com/embed/$1?origin={hosturl}&wmode=opaque" data-youtube-id="$1" allowfullscreen loading="lazy"></iframe></div></div>',
			'disabled_content' => '<a href="https://www.youtube.com/watch?v=$1" target="_blank" rel="noopener">https://www.youtube.com/watch?v=$1</a>',
			'block_level' => true,
		],
	];

	/**
	 * @var array
	 *
	 * Itemcodes are an alternative syntax for creating lists.
	 */
	public static array $itemcodes = [
		'*' => 'disc',
		'@' => 'disc',
		'+' => 'square',
		'x' => 'square',
		'#' => 'square',
		'o' => 'circle',
		'O' => 'circle',
		'0' => 'circle',
	];

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
	 * @var ?string
	 *
	 * Regular expression to match smileys.
	 */
	protected ?string $smiley_preg_search = null;

	/**
	 * @var array
	 *
	 * Replacement values for smileys.
	 */
	protected array $smiley_preg_replacements = [];

	/**
	 * @var array
	 *
	 * Holds any extra info that should be used in the cache_key.
	 *
	 * Data can be added to this variable using the integrate_pre_parsebbc hook.
	 *
	 * This is important if your mod can cause the same input string to produce
	 * different output strings in different situations. For example, if your
	 * mod adds a BBCode that shows different output to guests vs. members, then
	 * you need to add information to this variable in order to distinguish the
	 * guest version vs. the member version of the output.
	 */
	private array $cache_key_extras = [];

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
	 * URL of this host/domain. Needed for the YouTube BBCode.
	 */
	private string $hosturl;

	/**
	 * @var string
	 *
	 * The string in which to parse BBCode.
	 */
	private string $message = '';

	/**
	 * @var array
	 *
	 * BBCode tags that are currently open at any given step of processing
	 * $this->message.
	 */
	private array $open_tags = [];

	/**
	 * @var ?array
	 *
	 * The last item of $this->open_tags.
	 */
	private ?array $inside = null;

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

	/**
	 * @var array
	 *
	 * Holds parsed messages.
	 */
	private array $results = [];

	/**
	 * @var bool
	 *
	 * Tracks whether the integration_bbc_codes hook was called.
	 */
	private static bool $integrate_bbc_codes_done = false;

	/**
	 * @var self
	 *
	 * A reference to an existing, reusable instance of this class.
	 */
	private static self $parser;

	/*****************
	 * Public methods.
	 *****************/

	/**
	 * Constructor.
	 *
	 * @return object A reference to this object for method chaining.
	 * @suppress PHP0436
	 */
	public function __construct()
	{
		/**********************
		 * Set up localization.
		 **********************/
		if (!empty(Utils::$context['utf8'])) {
			$this->utf8 = true;
			$this->encoding = 'UTF-8';
		} else {
			$this->encoding = !empty(Config::$modSettings['global_character_set']) ? Config::$modSettings['global_character_set'] : (!empty(Lang::$txt['lang_character_set']) ? Lang::$txt['lang_character_set'] : $this->encoding);

			$this->utf8 = $this->encoding === 'UTF-8';
		}

		if (!empty(Lang::$txt['lang_locale'])) {
			$this->locale = Lang::$txt['lang_locale'];
		}

		$this->time_offset = User::$me->time_offset ?? 0;
		$this->time_format = User::$me->time_format ?? Time::getTimeFormat();

		/************************
		 * Set up BBCode parsing.
		 ************************/
		$this->enable_bbc = !empty(Config::$modSettings['enableBBC']);
		$this->enable_post_html = !empty(Config::$modSettings['enablePostHTML']);

		// Let mods add new BBC without hassle.
		self::integrateBBC();

		usort(
			self::$codes,
			function ($a, $b) {
				return strcmp($a['tag'], $b['tag']);
			},
		);

		/*************************
		 * Set up smileys parsing.
		 *************************/
		$this->custom_smileys_enabled = !empty(Config::$modSettings['smiley_enable']);
		$this->smileys_url = Config::$modSettings['smileys_url'];
		$this->smiley_set = !empty(User::$me->smiley_set) ? User::$me->smiley_set : (!empty(Config::$modSettings['smiley_sets_default']) ? Config::$modSettings['smiley_sets_default'] : 'none');

		// Maybe a mod wants to implement an alternative method for smileys
		// (e.g. emojis instead of images)
		if ($this->smiley_set !== 'none') {
			IntegrationHook::call('integrate_smileys', [&$this->smiley_preg_search, &$this->smiley_preg_replacements]);
		}

		/************************
		 * Allow method chaining.
		 ************************/
		return $this;
	}

	/**
	 * Parse bulletin board code in a string, as well as smileys optionally.
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

		// Clean up any cut/paste issues we may have
		$this->message = self::sanitizeMSCutPaste($this->message);

		// If the load average is too high, don't parse the BBC.
		if ($this->highLoadAverage()) {
			return $this->message;
		}

		if (!$this->enable_bbc) {
			if ($this->smileys === true) {
				$this->message = $this->parseSmileys($this->message);
			}

			return $this->message;
		}

		// Allow mods access before entering $this->parseMessage.
		IntegrationHook::call('integrate_pre_parsebbc', [&$this->message, &$this->smileys, &$cache_id, &$this->parse_tags, &$this->cache_key_extras]);

		// If no cache id was given, make a generic one.
		$cache_id = strval($cache_id) !== '' ? $cache_id : 'str' . substr(md5($this->message), 0, 7);

		// Use a unique identifier key for this combination of string and settings.
		$cache_key = 'parse:' . $cache_id . '-' . md5(json_encode([
			$this->message,
			// Localization settings.
			$this->encoding,
			$this->locale,
			$this->time_offset,
			$this->time_format,
			// BBCode settings.
			$this->bbc_codes,
			$this->disabled,
			$this->parse_tags,
			$this->enable_post_html,
			$this->for_print,
			// Smiley settings.
			$this->smileys,
			$this->smiley_set,
			$this->smiley_preg_search,
			$this->smiley_preg_replacements,
			// Additional stuff that might affect output.
			$this->cache_key_extras,
		]));

		// Have we already parsed this string?
		if (isset($this->results[$cache_key])) {
			return $this->results[$cache_key];
		}

		// Or maybe we cached the results recently?
		if (($this->results[$cache_key] = CacheApi::get($cache_key, 240)) != null) {
			return $this->results[$cache_key];
		}

		// Keep track of how long this takes.
		$cache_t = microtime(true);

		// Do the job.
		$this->parseMessage();

		// Allow mods access to what $this->parseMessage created.
		IntegrationHook::call('integrate_post_parsebbc', [&$this->message, &$this->smileys, &$cache_id, &$this->parse_tags]);

		// Cache the output if it took some time...
		if (!empty(CacheApi::$enable) && microtime(true) - $cache_t > pow(50, -CacheApi::$enable)) {
			CacheApi::put($cache_key, $this->message, 240);
		}

		// Remember for later.
		$this->results[$cache_key] = $this->message;

		return $this->results[$cache_key];
	}

	/**
	 * Parse smileys in the passed message.
	 *
	 * The smiley parsing function which makes pretty faces appear :).
	 * If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
	 * These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
	 * Caches the smileys from the database or array in memory.
	 *
	 * @param string $message The message to parse smileys in.
	 * @return string The message with smiley images inserted.
	 */
	public function parseSmileys(string $message): string
	{
		if ($this->smiley_set == 'none' || trim($message) == '') {
			return $message;
		}

		// If smileyPregSearch hasn't been set, do it now.
		if (empty($this->smiley_preg_search)) {
			// Cache for longer when customized smiley codes aren't enabled
			$cache_time = !$this->custom_smileys_enabled ? 7200 : 480;

			// Load the smileys in reverse order by length so they don't get parsed incorrectly.
			if (($temp = CacheApi::get('parsing_smileys_' . $this->smiley_set, $cache_time)) == null) {
				$smileysfrom = [];
				$smileysto = [];
				$smileysdescs = [];

				$result = Db::$db->query(
					'',
					'SELECT s.code, f.filename, s.description
					FROM {db_prefix}smileys AS s
						JOIN {db_prefix}smiley_files AS f ON (s.id_smiley = f.id_smiley)
					WHERE f.smiley_set = {string:smiley_set}' . (!$this->custom_smileys_enabled ? '
						AND s.code IN ({array_string:default_codes})' : '') . '
					ORDER BY LENGTH(s.code) DESC',
					[
						'default_codes' => ['>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:-)'],
						'smiley_set' => $this->smiley_set,
					],
				);

				while ($row = Db::$db->fetch_assoc($result)) {
					$smileysfrom[] = $row['code'];
					$smileysto[] = Utils::htmlspecialchars($row['filename']);
					$smileysdescs[] = !empty(Lang::$txt['icon_' . strtolower($row['description'])]) ? Lang::$txt['icon_' . strtolower($row['description'])] : $row['description'];
				}
				Db::$db->free_result($result);

				CacheApi::put('parsing_smileys_' . $this->smiley_set, [$smileysfrom, $smileysto, $smileysdescs], $cache_time);
			} else {
				list($smileysfrom, $smileysto, $smileysdescs) = $temp;
			}

			// The non-breaking-space is a complex thing...
			$non_breaking_space = $this->utf8 ? '\x{A0}' : '\xA0';

			// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
			$this->smiley_preg_replacements = [];
			$search_parts = [];
			$smileys_path = Utils::htmlspecialchars($this->smileys_url . '/' . rawurlencode($this->smiley_set) . '/');

			for ($i = 0, $n = count($smileysfrom); $i < $n; $i++) {
				$special_chars = Utils::htmlspecialchars($smileysfrom[$i], ENT_QUOTES);

				$smiley_code = '<img src="' . $smileys_path . $smileysto[$i] . '" alt="' . strtr($special_chars, [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']) . '" title="' . strtr(Utils::htmlspecialchars($smileysdescs[$i]), [':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;']) . '" class="smiley">';

				$this->smiley_preg_replacements[$smileysfrom[$i]] = $smiley_code;

				$search_parts[] = $smileysfrom[$i];

				if ($smileysfrom[$i] != $special_chars) {
					$this->smiley_preg_replacements[$special_chars] = $smiley_code;
					$search_parts[] = $special_chars;

					// Some 2.0 hex htmlchars are in there as 3 digits; allow for finding leading 0 or not
					$special_chars2 = preg_replace('/&#(\d{2});/', '&#0$1;', $special_chars);

					if ($special_chars2 != $special_chars) {
						$this->smiley_preg_replacements[$special_chars2] = $smiley_code;
						$search_parts[] = $special_chars2;
					}
				}
			}

			$this->smiley_preg_search = '~(?<=[>:\?\.\s' . $non_breaking_space . '[\]()*\\\;]|(?<![a-zA-Z0-9])\(|^)(' . Utils::buildRegex($search_parts, '~') . ')(?=[^[:alpha:]0-9]|$)~' . ($this->utf8 ? 'u' : '');
		}

		// If there are no smileys defined, no need to replace anything
		if (empty($this->smiley_preg_replacements)) {
			return $message;
		}

		// Replace away!
		return preg_replace_callback(
			$this->smiley_preg_search,
			function ($matches) {
				return $this->smiley_preg_replacements[$matches[1]];
			},
			$message,
		);
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
				// Value of 2 means we're inside the tag.
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

		// Do the smileys ultra first!
		preg_match_all('~<img\b[^>]+alt="([^"]+)"[^>]+class="smiley"[^>]*>(?:\s)?~i', $string, $matches);

		if (!empty($matches[0])) {
			// Get all our smiley codes
			$request = Db::$db->query(
				'',
				'SELECT code
				FROM {db_prefix}smileys
				ORDER BY LENGTH(code) DESC',
				[],
			);
			$smiley_codes = Db::$db->fetch_all($request);
			Db::$db->free_result($request);

			foreach ($matches[1] as $k => $possible_code) {
				$possible_code = Utils::htmlspecialcharsDecode($possible_code);

				if (in_array($possible_code, $smiley_codes)) {
					$matches[1][$k] = '-[]-smf_smily_start#|#' . $possible_code . '-[]-smf_smily_end#|#';
				} else {
					$matches[1][$k] = $matches[0][$k];
				}
			}

			// Replace the tags!
			$string = str_replace($matches[0], $matches[1], $string);

			// Now sort out spaces
			$string = str_replace(['-[]-smf_smily_end#|#-[]-smf_smily_start#|#', '-[]-smf_smily_end#|#', '-[]-smf_smily_start#|#'], ' ', $string);
		}

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
	 * @param bool $init If true, reinitializes the reusable BBCodeParser.
	 * @return object An instance of this class.
	 */
	public static function load(bool $init = false): object
	{
		if (!isset(self::$parser) || !empty($init)) {
			self::$parser = new self();
		}

		return self::$parser;
	}

	/**
	 * Get the list of supported BBCodes, including any added by modifications.
	 *
	 * @return array List of supported BBCodes
	 */
	public static function getCodes(): array
	{
		self::integrateBBC();

		return self::$codes;
	}

	/**
	 * Returns an array of BBC tags that are allowed in signatures.
	 *
	 * @return array An array containing allowed tags for signatures, or an empty array if all tags are allowed.
	 */
	public static function getSigTags(): array
	{
		list($sig_limits, $sig_bbc) = explode(':', Config::$modSettings['signature_settings']);

		if (empty($sig_bbc)) {
			return [];
		}

		$disabled_tags = explode(',', $sig_bbc);

		// Get all available bbc tags
		$temp = self::getCodes();
		$allowed_tags = [];

		foreach ($temp as $tag) {
			if (!in_array($tag['tag'], $disabled_tags)) {
				$allowed_tags[] = $tag['tag'];
			}
		}

		$allowed_tags = array_unique($allowed_tags);

		if (empty($allowed_tags)) {
			// An empty array means that all bbc tags are allowed. So if all tags are disabled we need to add a dummy tag.
			$allowed_tags[] = 'nonexisting';
		}

		return $allowed_tags;
	}

	/**
	 * Highlight any code.
	 *
	 * Uses PHP's highlight_string() to highlight PHP syntax
	 * does special handling to keep the tabs in the code available.
	 * used to parse PHP code from inside [code] and [php] tags.
	 *
	 * @param string $code The code.
	 * @return string The code with highlighted HTML.
	 */
	public static function highlightPhpCode(string $code): string
	{
		// Remove special characters.
		$code = Utils::htmlspecialcharsDecode(strtr($code, ['<br />' => "\n", '<br>' => "\n", "\t" => 'SMF_TAB();', '&#91;' => '[']));

		$oldlevel = error_reporting(0);

		$buffer = str_replace(["\n", "\r"], '', @highlight_string($code, true));

		error_reporting($oldlevel);

		// Yes, I know this is kludging it, but this is the best way to preserve tabs from PHP :P.
		$buffer = preg_replace('~SMF_TAB(?:</(?:font|span)><(?:font color|span style)="[^"]*?">)?\(\);~', '<span style="white-space: pre-wrap;">' . "\t" . '</span>', $buffer);

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
	 * @param string $string The string.
	 * @return string The sanitized string.
	 */
	public static function sanitizeMSCutPaste(string $string): string
	{
		if (empty($string)) {
			return $string;
		}

		self::load();

		// UTF-8 occurrences of MS special characters.
		$findchars_utf8 = [
			"\xe2\x80\x9a",	// single low-9 quotation mark
			"\xe2\x80\x9e",	// double low-9 quotation mark
			"\xe2\x80\xa6",	// horizontal ellipsis
			"\xe2\x80\x98",	// left single curly quote
			"\xe2\x80\x99",	// right single curly quote
			"\xe2\x80\x9c",	// left double curly quote
			"\xe2\x80\x9d",	// right double curly quote
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

		$string = str_replace(Utils::$context['utf8'] ? $findchars_utf8 : $findchars_iso, $replacechars, $string);

		return $string;
	}

	/**
	 * Backward compatibility wrapper for parse() and/or getCodes().
	 *
	 * @param string|bool $message The message.
	 *		When an empty string, nothing is done.
	 *		When false we provide a list of BBC codes available.
	 *		When a string, the message is parsed and bbc handled.
	 * @param bool $smileys Whether to parse smileys as well.
	 * @param string $cache_id The cache ID.
	 * @param array $parse_tags If set, only parses these tags rather than all of them.
	 * @return string|array The parsed message or the list of BBCodes.
	 */
	public static function backcompatParseBbc(string|bool $message, bool $smileys = true, string $cache_id = '', array $parse_tags = []): string|array
	{
		if ($message === false) {
			return self::getCodes();
		}

		self::load();

		$cache_id = (is_string($cache_id) || is_int($cache_id)) && strlen($cache_id) === strspn($cache_id, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_') ? (string) $cache_id : '';

		$for_print = self::$parser->for_print;
		self::$parser->for_print = $smileys === 'print';

		$message = self::$parser->parse((string) $message, !empty($smileys), $cache_id, (array) $parse_tags);

		self::$parser->for_print = $for_print;

		return $message;
	}

	/**
	 * Backward compatibility wrapper for parseSmileys().
	 * Doesn't return anything, but rather modifies $message directly.
	 *
	 * @param string &$message The message to parse smileys in.
	 */
	public static function backcompatParseSmileys(string &$message)
	{
		$message = self::load()->parseSmileys($message);
	}

	/**
	 * Backward compatibility wrapper for unparse().
	 *
	 * @param string $string Text containing HTML
	 * @return string The string with html converted to bbc
	 */
	public function htmlToBbc(string $string): string
	{
		return self::load()->unparse($string);
	}

	/**
	 * Wrapper for the integrate_bbc_codes hook.
	 * Prevents duplication in self::$codes.
	 */
	public static function integrateBBC(): void
	{
		// Only do this once.
		if (self::$integrate_bbc_codes_done !== true) {
			IntegrationHook::call('integrate_bbc_codes', [&self::$codes, &Autolinker::$no_autolink_tags]);

			// Prevent duplicates.
			$temp = [];

			// Reverse order because mods typically append to the array.
			for ($i = count(self::$codes) - 1; $i >= 0; $i--) {
				$value = self::$codes[$i];

				// Since validation functions may be closures, and
				// closures cannot be serialized, leave that part out.
				unset($value['validate']);

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

	/****************************
	 * BBCode validation methods.
	 ****************************/

	/**
	 * Validation method for the attach BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function attachValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		$return_context = '';

		// BBC or the entire attachments feature is disabled
		if (empty(Config::$modSettings['attachmentEnable']) || !empty($disabled['attach'])) {
			return;
		}

		// Save the attach ID.
		$attach_id = $params['{id}'];

		$current_attachment = Attachment::parseAttachBBC($attach_id);

		// parseAttachBBC will return a string (Lang::$txt key) rather than dying with a fatal_error. Up to you to decide what to do.
		if (is_string($current_attachment)) {
			$data = '<span style="display:inline-block" class="errorbox">' . (!empty(Lang::$txt[$current_attachment]) ? Lang::$txt[$current_attachment] : $current_attachment) . '</span>';

			return;
		}

		// We need a display mode.
		if (empty($params['{display}'])) {
			// Images, video, and audio are embedded by default.
			if (!empty($current_attachment['is_image']) || str_starts_with($current_attachment['mime_type'], 'video/') || str_starts_with($current_attachment['mime_type'], 'audio/')) {
				$params['{display}'] = 'embed';
			}
			// Anything else shows a link by default.
			else {
				$params['{display}'] = 'link';
			}
		}

		// Embedded file.
		if ($params['{display}'] == 'embed') {
			$alt = ' alt="' . (!empty($params['{alt}']) ? $params['{alt}'] : $current_attachment['name']) . '"';
			$title = !empty($data) ? ' title="' . Utils::htmlspecialchars($data) . '"' : '';

			// Image.
			if (!empty($current_attachment['is_image'])) {
				// Just viewing the page shouldn't increase the download count for embedded images.
				$current_attachment['href'] .= ';preview';

				if (empty($params['{width}']) && empty($params['{height}'])) {
					$return_context .= '<img src="' . $current_attachment['href'] . '"' . $alt . $title . ' class="bbc_img">';
				} else {
					$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
					$height = !empty($params['{height}']) ? 'height="' . $params['{height}'] . '"' : '';
					$return_context .= '<img src="' . $current_attachment['href'] . ';image"' . $alt . $title . $width . $height . ' class="bbc_img resized"/>';
				}
			}
			// Video.
			elseif (str_starts_with($current_attachment['mime_type'], 'video/')) {
				$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
				$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

				$return_context .= '<div class="videocontainer"><video controls preload="metadata" src="' . $current_attachment['href'] . '" playsinline' . $width . $height . '><a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a></video></div>' . (!empty($data) && $data != $current_attachment['name'] ? '<div class="smalltext">' . $data . '</div>' : '');
			}
			// Audio.
			elseif (str_starts_with($current_attachment['mime_type'], 'audio/')) {
				$width = 'max-width:100%; width: ' . (!empty($params['{width}']) ? $params['{width}'] : '400') . 'px;';
				$height = !empty($params['{height}']) ? 'height: ' . $params['{height}'] . 'px;' : '';

				$return_context .= (!empty($data) && $data != $current_attachment['name'] ? $data . ' ' : '') . '<audio controls preload="none" src="' . $current_attachment['href'] . '" class="bbc_audio" style="vertical-align:middle;' . $width . $height . '"><a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a></audio>';
			}
			// Anything else.
			else {
				$width = !empty($params['{width}']) ? ' width="' . $params['{width}'] . '"' : '';
				$height = !empty($params['{height}']) ? ' height="' . $params['{height}'] . '"' : '';

				$return_context .= '<object type="' . $current_attachment['mime_type'] . '" data="' . $current_attachment['href'] . '"' . $width . $height . ' typemustmatch><a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a></object>';
			}
		}
		// No image. Show a link.
		else {
			$return_context .= '<a href="' . $current_attachment['href'] . '" class="bbc_link">' . Utils::htmlspecialchars(!empty($data) ? $data : $current_attachment['name']) . '</a>';
		}

		// Use this hook to adjust the HTML output of the attach BBCode.
		// If you want to work with the attachment data itself, use one of these:
		// - integrate_pre_parseAttachBBC
		// - integrate_post_parseAttachBBC
		IntegrationHook::call('integrate_attach_bbc_validate', [&$return_context, $current_attachment, $tag, $data, $disabled, $params]);

		// Gotta append what we just did.
		$data = $return_context;
	}

	/**
	 * Validation method for the code BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function codeValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		if (!isset($disabled['code'])) {
			$code = is_array($data) ? $data[0] : $data;

			$add_begin = (
				is_array($data)
				&& isset($data[1])
				&& strtoupper($data[1]) === 'PHP'
				&& !str_contains($code, '&lt;?php')
			);

			if ($add_begin) {
				$code = '&lt;?php ' . $code . '?&gt;';
				$data[1] = 'PHP';
			}

			$php_parts = preg_split('~(&lt;\?php|\?&gt;)~', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

			for ($php_i = 0, $php_n = count($php_parts); $php_i < $php_n; $php_i++) {
				// Do PHP code coloring?
				if ($php_parts[$php_i] != '&lt;?php') {
					continue;
				}

				$php_string = '';

				while ($php_i + 1 < count($php_parts) && $php_parts[$php_i] != '?&gt;') {
					$php_string .= $php_parts[$php_i];
					$php_parts[$php_i++] = '';
				}

				$php_parts[$php_i] = self::highlightPhpCode($php_string . $php_parts[$php_i]);

				if (is_array($data) && empty($data[1])) {
					$data[1] = 'PHP';
				}
			}

			// Fix the PHP code stuff...
			$code = str_replace("<pre style=\"display: inline;\">\t</pre>", "\t", implode('', $php_parts));

			$code = str_replace("\t", "<span style=\"white-space: pre-wrap;\">\t</span>", $code);

			if ($add_begin) {
				$code = preg_replace(['/^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)/', '/\?&gt;((?:\s*<\/(font|span)>)*)$/m'], '$1', $code, 2);
			}

			if (is_array($data)) {
				$data[0] = $code;
			} else {
				$data = $code;
			}
		}
	}

	/**
	 * Validation method for the email BBCode.
	 * @todo Should this respect guest_hideContacts?
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function emailValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		$data = strtr($data, ['<br>' => '']);
	}

	/**
	 * Validation method for the flash BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function flashValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		$data[0] = new Url(strtr(trim($data[0]), ['<br>' => '', ' ' => '%20']), true);

		if (empty($data[0]->scheme)) {
			$data[0] = new Url('//' . ltrim($data[0], ':/'));
		}

		$ascii_url = (clone $data[0])->toAscii();

		if ((string) $ascii_url !== (string) $data[0]) {
			$tag['content'] = str_replace('href="$1"', 'href="' . $ascii_url . '"', $tag['content']);
		}
	}

	/**
	 * Validation method for the float BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function floatValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		$class = 'class="bbc_float float' . (str_starts_with($data, 'left') ? 'left' : 'right') . '"';

		if (preg_match('~\bmax=(\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)~', $data, $matches)) {
			$css = ' style="max-width:' . $matches[1] . (is_numeric($matches[1]) ? 'px' : '') . '"';
		} else {
			$css = '';
		}

		$data = $class . $css;
	}

	/**
	 * Validation method for the ftp BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function ftpValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		$data = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);

		if (empty($data->scheme)) {
			$data = new Url('ftp://' . ltrim((string) $data, ':/'));
		}

		if (isset($tag['content'])) {
			$ascii_url = (clone $data)->toAscii();

			if ((string) $ascii_url !== (string) $data) {
				$tag['content'] = str_replace('href="$1"', 'href="' . $ascii_url . '"', $tag['content']);
			}
		} else {
			$data->toAscii();
		}
	}

	/**
	 * Validation method for the img BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function imgValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		$url = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);
		$url->toAscii();

		if (!isset($url->scheme)) {
			$url = new Url('//' . ltrim((string) $url, ':/'));
		} else {
			$url = $url->proxied();
		}

		$alt = !empty($params['{alt}']) ? ' alt="' . $params['{alt}'] . '"' : ' alt=""';
		$title = !empty($params['{title}']) ? ' title="' . $params['{title}'] . '"' : '';

		$data = isset($disabled[$tag['tag']]) ? $url : '<img src="' . $url . '"' . $alt . $title . $params['{width}'] . $params['{height}'] . ' class="bbc_img' . (!empty($params['{width}']) || !empty($params['{height}']) ? ' resized' : '') . '" loading="lazy">';
	}

	/**
	 * Validation method for the url and iurl BBCodes.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function urlValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		if ($tag['type'] === 'unparsed_content') {
			$data = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);

			if (empty($data->scheme)) {
				$data = new Url('//' . ltrim((string) $data, ':/'));
			}

			$ascii_url = (clone $data)->toAscii();

			if ((string) $ascii_url !== (string) $data) {
				$tag['content'] = str_replace('href="$1"', 'href="' . $ascii_url . '"', $tag['content']);
			}
		} else {
			if (str_starts_with($data, '#')) {
				$data = '#post_' . substr($data, 1);
			} else {
				$data = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);
				$data->toAscii();

				if (empty($data->scheme)) {
					$data = '//' . ltrim((string) $data, ':/');
				}
			}
		}
	}

	/**
	 * Validation method for the php BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function phpValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		if (!isset($disabled['php'])) {
			$add_begin = !str_starts_with(trim($data), '&lt;?');

			$data = self::highlightPhpCode($add_begin ? '&lt;?php ' . $data . '?&gt;' : $data);

			if ($add_begin) {
				$data = preg_replace(['/^(.+?)&lt;\?.{0,40}?php(?:&nbsp;|\s)/', '/\?&gt;((?:\s*<\/(font|span)>)*)$/m'], '$1', $data, 2);
			}
		}
	}

	/**
	 * Validation method for the shadow BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function shadowValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		if ($data[1] == 'top' || (is_numeric($data[1]) && $data[1] < 50)) {
			$data[1] = '0 -2px 1px';
		} elseif ($data[1] == 'right' || (is_numeric($data[1]) && $data[1] < 100)) {
			$data[1] = '2px 0 1px';
		} elseif ($data[1] == 'bottom' || (is_numeric($data[1]) && $data[1] < 190)) {
			$data[1] = '0 2px 1px';
		} elseif ($data[1] == 'left' || (is_numeric($data[1]) && $data[1] < 280)) {
			$data[1] = '-2px 0 1px';
		} else {
			$data[1] = '1px 1px 1px';
		}
	}

	/**
	 * Validation method for the size BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function sizeValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		$sizes = [1 => 0.7, 2 => 1.0, 3 => 1.35, 4 => 1.45, 5 => 2.0, 6 => 2.65, 7 => 3.95];
		$data = $sizes[$data] . 'em';
	}

	/**
	 * Validation method for the time BBCode.
	 *
	 * @param array &$tag A copy of this tag's definition.
	 * @param array|string &$data The data in this particular BBCode instance.
	 * @param array $disabled List of disabled BBCodes.
	 * @param array $params Parameters supplied in this BBCode instance.
	 */
	public static function timeValidate(array &$tag, array|string &$data, array $disabled, array $params): void
	{
		if (is_numeric($data)) {
			$data = Time::create('@' . $data)->format();
		}

		$tag['content'] = '<span class="bbc_time">$1</span>';
	}

	/*******************
	 * Internal methods.
	 *******************/

	/**
	 * The method that actually parses the BBCode in $this->message.
	 */
	protected function parseMessage(): void
	{
		$this->open_tags = [];
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

				$data = strtr($data, ["\t" => '&nbsp;&nbsp;&nbsp;']);

				// If it wasn't changed, no copying or other boring stuff has to happen!
				if ($data != substr($this->message, $this->last_pos, $this->pos - $this->last_pos)) {
					$this->message = substr($this->message, 0, $this->last_pos) . $data . substr($this->message, $this->pos);

					// Since we changed it, look again in case we added or removed a tag.  But we don't want to skip any.
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

			if ($tag_character == '/' && !empty($this->open_tags)) {
				$this->closeTags();

				continue;
			}

			// No tags for this character, so just keep going (fastest possible course.)
			if (!isset($this->bbc_codes[$tag_character])) {
				continue;
			}

			$this->inside = empty($this->open_tags) ? null : $this->open_tags[count($this->open_tags) - 1];

			// What tag do we have?
			list($tag, $params) = $this->detectTag($tag_character);

			if ($tag === null) {
				// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
				if (isset(self::$itemcodes[$this->message[$this->pos + 1]], $this->message[$this->pos + 2]) && $this->message[$this->pos + 2] == ']' && !isset($this->disabled['list']) && !isset($this->disabled['li'])) {
					$this->parseItemCode();
				}
				// Implicitly close lists and tables if something other than what's required is in them.  This is needed for itemcode.
				elseif ($this->inside !== null && !empty($this->inside['require_children'])) {
					array_pop($this->open_tags);

					$this->message = substr($this->message, 0, $this->pos) . "\n" . $this->inside['after'] . "\n" . substr($this->message, $this->pos);
					$this->pos += strlen($this->inside['after']) - 1 + 2;
				}

				continue;
			}

			// Propagate the list to the child (so wrapping the disallowed tag won't work either.)
			if (isset($this->inside['disallow_children'])) {
				$tag['disallow_children'] = isset($tag['disallow_children']) ? array_unique(array_merge($tag['disallow_children'], $this->inside['disallow_children'])) : $this->inside['disallow_children'];
			}

			// Is this tag disabled?
			if (isset($this->disabled[$tag['tag']])) {
				$tag = $this->useDisabledTag($tag);
			}

			// The only special case is 'html', which doesn't need to close things.
			if (!empty($tag['block_level']) && $tag['tag'] != 'html' && empty($this->inside['block_level'])) {
				$this->closeInlineTags();
			}

			// Can't read past the end of the message
			$this->pos1 = min(strlen($this->message), $this->pos1);

			$this->transformToHtml($tag, $params);
		}

		// Close any remaining tags.
		while ($tag = array_pop($this->open_tags)) {
			$this->message .= "\n" . $tag['after'] . "\n";
		}

		// Parse the smileys within the parts where it can be done safely.
		if ($this->smileys === true) {
			$message_parts = explode("\n", $this->message);

			for ($i = 0, $n = count($message_parts); $i < $n; $i += 2) {
				$message_parts[$i] = $this->parseSmileys($message_parts[$i]);
			}

			$this->message = implode('', $message_parts);
		}
		// No smileys, just get rid of the markers.
		else {
			$this->message = strtr($this->message, ["\n" => '']);
		}

		if ($this->message !== '' && $this->message[0] === ' ') {
			$this->message = '&nbsp;' . substr($this->message, 1);
		}

		// Cleanup whitespace.
		$this->message = strtr($this->message, ['  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"]);
	}

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
	 * Sets $this->bbc_codes.
	 */
	protected function setBbcCodes(): void
	{
		// If we already have a version of the BBCodes for the current language, use that.
		$locale_key = $this->locale . '|' . implode(',', $this->disabled);

		if (!empty($this->bbc_lang_locales[$locale_key])) {
			$this->bbc_codes = $this->bbc_lang_locales[$locale_key];
		}

		// If we are not doing every tag then we don't cache this run.
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
			$codes[] = [
				'tag' => 'chrissy',
				'before' => '<span style="color: #cc0099;">',
				'after' => ' :-*</span>',
			];
			$codes[] = [
				'tag' => 'kissy',
				'before' => '<span style="color: #cc0099;">',
				'after' => ' :-*</span>',
			];
		}
		$codes[] = [
			'tag' => 'cowsay',
			'parameters' => [
				'e' => ['optional' => true, 'quoted' => true, 'match' => '(.*?)', 'default' => 'oo', 'validate' => function ($eyes) {
					return Utils::entitySubstr($eyes . 'oo', 0, 2);
				},
				],
				't' => ['optional' => true, 'quoted' => true, 'match' => '(.*?)', 'default' => '  ', 'validate' => function ($tongue) {
					return Utils::entitySubstr($tongue . '  ', 0, 2);
				},
				],
			],
			'before' => '<pre data-e="{e}" data-t="{t}"><div>',
			'after' => '</div></pre>',
			'block_level' => true,
			'validate' => function (&$tag, &$data, $disabled, $params) {
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
		];

		foreach ($codes as $code) {
			// Make it easier to process parameters later
			if (!empty($code['parameters'])) {
				ksort($code['parameters'], SORT_STRING);
			}

			// If we are not doing every tag only do ones we are interested in.
			if (empty($this->parse_tags) || in_array($code['tag'], $this->parse_tags)) {
				$this->bbc_codes[substr($code['tag'], 0, 1)][] = $code;
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
			foreach ($section as $code) {
				$alltags[] = $code['tag'];
			}
		}

		$this->alltags_regex = '(?' . '>\b' . Utils::buildRegex(array_unique($alltags)) . '\b|' . Utils::buildRegex(array_keys(self::$itemcodes)) . ')';
	}

	/**
	 * Replaces {txt_*} tokens with Lang::$txt strings.
	 *
	 * @param string $data A string that might contain {txt_*} tokens.
	 * @return string The string with Lang::$txt string values.
	 */
	protected function insertTxt(string $string): string
	{
		return preg_replace_callback(
			'/{(.*?)}/',
			function ($matches) {
				if ($matches[0] === '{scripturl}') {
					return Config::$scripturl;
				}

				if ($matches[0] === '{hosturl}') {
					if (!isset($this->hosturl)) {
						$our_url = new Url(Config::$scripturl);
						$this->hosturl = $our_url->scheme . '://' . $our_url->host;
					}

					return $this->hosturl;
				}

				if (str_starts_with($matches[1], 'txt_') && isset(Lang::$txt[substr($matches[1], 4)])) {
					return Lang::$txt[substr($matches[1], 4)];
				}

				return $matches[0];
			},
			$string,
		);
	}

	/**
	 * Fixes up any raw HTML in a BBCode string.
	 *
	 * @param string $data A string that might contain HTML.
	 * @return string The fixed version of the string.
	 */
	protected function fixHtml(string $data): string
	{
		if (empty($this->enable_post_html) || !str_contains($data, '&lt;')) {
			return $data;
		}

		$data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|ftps?://|mailto:|tel:)\S+?)\1&gt;(.*?)&lt;/a&gt;~i', '[url=&quot;$2&quot;]$3[/url]', $data);

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
		if (!in_array($look_for, array_map(function ($tag) { return $tag['tag']; }, $this->open_tags))) {
			return;
		}

		$to_close = [];
		$block_level = null;

		do {
			$tag = array_pop($this->open_tags);

			if (!$tag) {
				break;
			}

			if (!empty($tag['block_level'])) {
				// Only find out if we need to.
				if ($block_level === false) {
					array_push($this->open_tags, $tag);
					break;
				}

				// The idea is, if we are LOOKING for a block level tag, we can close them on the way.
				if (strlen($look_for) > 0 && isset($this->bbc_codes[$look_for[0]])) {
					foreach ($this->bbc_codes[$look_for[0]] as $temp) {
						if ($temp['tag'] == $look_for) {
							$block_level = !empty($temp['block_level']);
							break;
						}
					}
				}

				if ($block_level !== true) {
					$block_level = false;
					array_push($this->open_tags, $tag);
					break;
				}
			}

			$to_close[] = $tag;
		} while ($tag['tag'] != $look_for);

		// Did we just eat through everything and not find it?
		if ((empty($this->open_tags) && (empty($tag) || $tag['tag'] != $look_for))) {
			$this->open_tags = $to_close;

			return;
		}

		if (!empty($to_close) && $tag['tag'] != $look_for) {
			if ($block_level === null && isset($look_for[0], $this->bbc_codes[$look_for[0]])) {
				foreach ($this->bbc_codes[$look_for[0]] as $temp) {
					if ($temp['tag'] == $look_for) {
						$block_level = !empty($temp['block_level']);
						break;
					}
				}
			}

			// We're not looking for a block level tag (or maybe even a tag that exists...)
			if (!$block_level) {
				foreach ($to_close as $tag) {
					array_push($this->open_tags, $tag);
				}

				return;
			}
		}

		foreach ($to_close as $tag) {
			$this->message = substr($this->message, 0, $this->pos) . "\n" . $tag['after'] . "\n" . substr($this->message, $pos2 + 1);
			$this->pos += strlen($tag['after']) + 2;
			$pos2 = $this->pos - 1;

			// See the comment at the end of the big loop - just eating whitespace ;).
			$whitespace_regex = '';

			if (!empty($tag['block_level'])) {
				$whitespace_regex .= '(&nbsp;|\s)*(<br\s*/?' . '>)?';
			}

			// Trim one line of whitespace after unnested tags, but all of it after nested ones
			if (!empty($tag['trim']) && $tag['trim'] != 'inside') {
				$whitespace_regex .= empty($tag['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';
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
	 * @return array The tag's definition and the parameter values to use.
	 */
	protected function detectTag(string $tag_character): array
	{
		$tag = null;
		$params = [];

		foreach ($this->bbc_codes[$tag_character] as $possible) {
			$pt_strlen = strlen($possible['tag']);

			// Not a match?
			if (strtolower(substr($this->message, $this->pos + 1, $pt_strlen)) != $possible['tag']) {
				continue;
			}

			$next_c = $this->message[$this->pos + 1 + $pt_strlen] ?? '';

			// A tag is the last char maybe
			if ($next_c == '') {
				break;
			}

			// A test validation?
			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($this->message, $this->pos + 1 + $pt_strlen + 1)) === 0) {
				continue;
			}

			// Do we want parameters?
			if (!empty($possible['parameters'])) {
				// Are all the parameters optional?
				$param_required = false;

				foreach ($possible['parameters'] as $param) {
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
			elseif (isset($possible['type'])) {
				// Do we need an equal sign?
				if (in_array($possible['type'], ['unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals']) && $next_c != '=') {
					continue;
				}

				// Maybe we just want a /...
				if ($possible['type'] == 'closed' && $next_c != ']' && substr($this->message, $this->pos + 1 + $pt_strlen, 2) != '/]' && substr($this->message, $this->pos + 1 + $pt_strlen, 3) != ' /]') {
					continue;
				}

				// An immediate ]?
				if ($possible['type'] == 'unparsed_content' && $next_c != ']') {
					continue;
				}
			}
			// No type means 'parsed_content', which demands an immediate ] without parameters!
			elseif ($next_c != ']') {
				continue;
			}

			// Check allowed tree?
			if (isset($possible['require_parents']) && ($this->inside === null || !in_array($this->inside['tag'], $possible['require_parents']))) {
				continue;
			}

			if (isset($this->inside['require_children']) && !in_array($possible['tag'], (array) $this->inside['require_children'])) {
				continue;
			}

			// If this is in the list of disallowed child tags, don't parse it.
			if (isset($this->inside['disallow_children']) && in_array($possible['tag'], (array) $this->inside['disallow_children'])) {
				continue;
			}

			$this->pos1 = $this->pos + 1 + $pt_strlen + 1;

			// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
			if ($possible['tag'] == 'quote') {
				// Start with standard
				$quote_alt = false;

				foreach ($this->open_tags as $open_quote) {
					// Every parent quote this quote has flips the styling
					if ($open_quote['tag'] == 'quote') {
						$quote_alt = !$quote_alt;
					}
				}

				// Add a class to the quote to style alternating blockquotes
				$possible['before'] = strtr($possible['before'], ['<blockquote>' => '<blockquote class="bbc_' . ($quote_alt ? 'alternate' : 'standard') . '_quote">']);
			}

			// This is long, but it makes things much easier and cleaner.
			if (!empty($possible['parameters'])) {
				// Build a regular expression for each parameter for the current tag.
				$regex_key = json_encode($possible['parameters']);

				if (!isset($params_regexes[$regex_key])) {
					$params_regexes[$regex_key] = '';

					foreach ($possible['parameters'] as $p => $info) {
						$params_regexes[$regex_key] .= '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . ($info['match'] ?? '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . '\s*)' . (empty($info['optional']) ? '' : '?');
					}
				}

				// Extract the string that potentially holds our parameters.
				$blob = preg_split('~\[/?(?:' . $this->alltags_regex . ')~i', substr($this->message, $this->pos));
				$blobs = preg_split('~\]~i', $blob[1]);

				$splitters = implode('=|', array_keys($possible['parameters'])) . '=';

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

					if (isset($possible['parameters'][$key]['value'])) {
						$params['{' . $key . '}'] = strtr($possible['parameters'][$key]['value'], ['$1' => $matches[$i + 1]]);
					} elseif (isset($possible['parameters'][$key]['validate'])) {
						$params['{' . $key . '}'] = $possible['parameters'][$key]['validate']($matches[$i + 1]);
					} else {
						$params['{' . $key . '}'] = $matches[$i + 1];
					}

					// Just to make sure: replace any $ or { so they can't interpolate wrongly.
					$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], ['$' => '&#036;', '{' => '&#123;']);
				}

				foreach ($possible['parameters'] as $p => $info) {
					if (!isset($params['{' . $p . '}'])) {
						if (!isset($info['default'])) {
							$params['{' . $p . '}'] = '';
						} elseif (isset($possible['parameters'][$p]['value'])) {
							$params['{' . $p . '}'] = strtr($possible['parameters'][$p]['value'], ['$1' => $info['default']]);
						} elseif (isset($possible['parameters'][$p]['validate'])) {
							$params['{' . $p . '}'] = $possible['parameters'][$p]['validate']($info['default']);
						} else {
							$params['{' . $p . '}'] = $info['default'];
						}
					}
				}

				$tag = $possible;

				// Put the parameters into the string.
				if (isset($tag['before'])) {
					$tag['before'] = strtr($tag['before'], $params);
				}

				if (isset($tag['after'])) {
					$tag['after'] = strtr($tag['after'], $params);
				}

				if (isset($tag['content'])) {
					$tag['content'] = strtr($tag['content'], $params);
				}

				$this->pos1 += strlen($given_param_string);
			} else {
				$tag = $possible;
				$params = [];
			}
			break;
		}

		return [$tag, $params];
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
		if ($this->inside === null || ($this->inside['tag'] != 'list' && $this->inside['tag'] != 'li')) {
			$this->open_tags[] = [
				'tag' => 'list',
				'after' => '</ul>',
				'block_level' => true,
				'require_children' => ['li'],
				'disallow_children' => $this->inside['disallow_children'] ?? null,
			];

			$html = '<ul class="bbc_list">';
		}
		// We're in a list item already: another itemcode?  Close it first.
		elseif ($this->inside['tag'] == 'li') {
			array_pop($this->open_tags);
			$html = '</li>';
		} else {
			$html = '';
		}

		// Now we open a new tag.
		$this->open_tags[] = [
			'tag' => 'li',
			'after' => '</li>',
			'trim' => 'outside',
			'block_level' => true,
			'disallow_children' => $this->inside['disallow_children'] ?? null,
		];

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

			$this->open_tags[count($this->open_tags) - 2]['after'] = '</ul>';
		}
		// Tell the [list] that it needs to close specially.
		else {
			// Move the li over, because we're not sure what we'll hit.
			$this->open_tags[count($this->open_tags) - 1]['after'] = '';
			$this->open_tags[count($this->open_tags) - 2]['after'] = '</li></ul>';
		}
	}

	/**
	 * Adjusts a tag definition so that it uses its disabled version for output.
	 *
	 * @param array $tag A tag definition.
	 * @return array The disabled version of the tag definition.
	 */
	protected function useDisabledTag(array $tag): array
	{
		if (!isset($tag['disabled_before']) && !isset($tag['disabled_after']) && !isset($tag['disabled_content'])) {
			$tag['before'] = !empty($tag['block_level']) ? '<div>' : '';
			$tag['after'] = !empty($tag['block_level']) ? '</div>' : '';
			$tag['content'] = isset($tag['type']) && $tag['type'] == 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
		} elseif (isset($tag['disabled_before']) || isset($tag['disabled_after'])) {
			$tag['before'] = $tag['disabled_before'] ?? (!empty($tag['block_level']) ? '<div>' : '');
			$tag['after'] = $tag['disabled_after'] ?? (!empty($tag['block_level']) ? '</div>' : '');
		} else {
			$tag['content'] = $tag['disabled_content'];
		}

		return $tag;
	}

	/**
	 * Similar to $this->closeTags(), but only for inline tags.
	 * Operates directly on $this->message.
	 */
	protected function closeInlineTags(): void
	{
		$n = count($this->open_tags) - 1;

		while (empty($this->open_tags[$n]['block_level']) && $n >= 0) {
			$n--;
		}

		// Close all the non block level tags so this tag isn't surrounded by them.
		for ($i = count($this->open_tags) - 1; $i > $n; $i--) {
			$this->message = substr($this->message, 0, $this->pos) . "\n" . $this->open_tags[$i]['after'] . "\n" . substr($this->message, $this->pos);

			$ot_strlen = strlen($this->open_tags[$i]['after']);
			$this->pos += $ot_strlen + 2;
			$this->pos1 += $ot_strlen + 2;

			// Trim or eat trailing stuff...
			$whitespace_regex = '';

			if (!empty($this->open_tags[$i]['block_level'])) {
				$whitespace_regex .= '(&nbsp;|\s)*(<br>)?';
			}

			if (!empty($this->open_tags[$i]['trim']) && $this->open_tags[$i]['trim'] != 'inside') {
				$whitespace_regex .= empty($this->open_tags[$i]['require_parents']) ? '(&nbsp;|\s)*' : '(<br>|&nbsp;|\s)*';
			}

			if (!empty($whitespace_regex) && preg_match('~' . $whitespace_regex . '~', substr($this->message, $this->pos), $matches) != 0) {
				$this->message = substr($this->message, 0, $this->pos) . substr($this->message, $this->pos + strlen($matches[0]));
			}

			array_pop($this->open_tags);
		}
	}

	/**
	 * Transforms a BBCode tag into HTML.
	 * Operates directly on $this->message.
	 *
	 * @param array $tag The tag definition.
	 * @param array $params Parameter values to use.
	 */
	protected function transformToHtml(array $tag, array $params): void
	{
		// Insert Lang::$txt strings into the HTML output.
		foreach (['content', 'before', 'after'] as $key) {
			if (isset($tag[$key])) {
				$tag[$key] = $this->insertTxt($tag[$key]);
			}

			if (isset($tag['disabled_' . $key])) {
				$tag['disabled_' . $key] = $this->insertTxt($tag['disabled_' . $key]);
			}
		}

		// We use this a lot.
		$tag_strlen = strlen($tag['tag']);

		// Set the validation method to something we can call.
		if (isset($tag['validate']) && is_string($tag['validate'])) {
			$tag['validate'] = Utils::getCallable($tag['validate']);
		}

		// No type means 'parsed_content'.
		if (!isset($tag['type'])) {
			$this->open_tags[] = $tag;

			// There's no data to change, but maybe do something based on params?
			$data = null;

			if (isset($tag['validate'])) {
				call_user_func_array($tag['validate'], [&$tag, &$data, $this->disabled, $params]);
			}

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $tag['before'] . "\n" . substr($this->message, $this->pos1);

			$this->pos += strlen($tag['before']) - 1 + 2;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] == 'unparsed_content') {
			$pos2 = stripos($this->message, '[/' . substr($this->message, $this->pos + 1, $tag_strlen) . ']', $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$data = substr($this->message, $this->pos1, $pos2 - $this->pos1);

			if (!empty($tag['block_level']) && str_starts_with($data, '<br>')) {
				$data = substr($data, 4);
			}

			if (isset($tag['validate'])) {
				call_user_func_array($tag['validate'], [&$tag, &$data, $this->disabled, $params]);
			}

			$html = strtr($tag['content'], ['$1' => $data]);

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos2 + 3 + $tag_strlen);

			$this->pos += strlen($html) - 1 + 2;
			$this->last_pos = $this->pos + 1;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] == 'unparsed_equals_content') {
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted'])) {
				// Anything passed through the preparser will use &quot;,
				// but we need to handle raw quotation marks too.
				$quot = substr($this->message, $this->pos1, 1) === '"' ? '"' : '&quot;';

				$quoted = substr($this->message, $this->pos1, strlen($quot)) == $quot;

				if ($tag['quoted'] != 'optional' && !$quoted) {
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

			if (!empty($tag['block_level']) && str_starts_with($data[0], '<br>')) {
				$data[0] = substr($data[0], 4);
			}

			// Validation for my parking, please!
			if (isset($tag['validate'])) {
				call_user_func_array($tag['validate'], [&$tag, &$data, $this->disabled, $params]);
			}

			$html = strtr($tag['content'], ['$1' => $data[0], '$2' => $data[1]]);

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos3 + 3 + $tag_strlen);

			$this->pos += strlen($html) - 1 + 2;
		}
		// A closed tag, with no content or value.
		elseif ($tag['type'] == 'closed') {
			$pos2 = strpos($this->message, ']', $this->pos);

			// Maybe a custom BBC wants to do something special?
			$data = null;

			if (isset($tag['validate'])) {
				call_user_func_array($tag['validate'], [&$tag, &$data, $this->disabled, $params]);
			}

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $tag['content'] . "\n" . substr($this->message, $pos2 + 1);

			$this->pos += strlen($tag['content']) - 1 + 2;
		}
		// This one is sorta ugly... :/.  Unfortunately, it's needed for flash.
		elseif ($tag['type'] == 'unparsed_commas_content') {
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

			if (isset($tag['validate'])) {
				call_user_func_array($tag['validate'], [&$tag, &$data, $this->disabled, $params]);
			}

			$html = $tag['content'];

			foreach ($data as $k => $d) {
				$html = strtr($html, ['$' . ($k + 1) => trim($d)]);
			}

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos3 + 3 + $tag_strlen);

			$this->pos += strlen($html) - 1 + 2;
		}
		// This has parsed content, and a csv value which is unparsed.
		elseif ($tag['type'] == 'unparsed_commas') {
			$pos2 = strpos($this->message, ']', $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$data = explode(',', substr($this->message, $this->pos1, $pos2 - $this->pos1));

			if (isset($tag['validate'])) {
				call_user_func_array($tag['validate'], [&$tag, &$data, $this->disabled, $params]);
			}

			// Fix after, for disabled code mainly.
			foreach ($data as $k => $d) {
				$tag['after'] = strtr($tag['after'], ['$' . ($k + 1) => trim($d)]);
			}

			$this->open_tags[] = $tag;

			// Replace them out, $1, $2, $3, $4, etc.
			$html = $tag['before'];

			foreach ($data as $k => $d) {
				$html = strtr($html, ['$' . ($k + 1) => trim($d)]);
			}

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos2 + 1);

			$this->pos += strlen($html) - 1 + 2;
		}
		// A tag set to a value, parsed or not.
		elseif ($tag['type'] == 'unparsed_equals' || $tag['type'] == 'parsed_equals') {
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted'])) {
				// Will normally be '&quot;' but might be '"'.
				$quot = substr($this->message, $this->pos1, 1) === '"' ? '"' : '&quot;';

				$quoted = substr($this->message, $this->pos1, strlen($quot)) == $quot;

				if ($tag['quoted'] != 'optional' && !$quoted) {
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
					// Nested tag with quoted value detected, use next end tag
					$nested_tag_pos = strpos($this->message, $quoted == false ? ']' : $quot . ']', $this->pos1) + strlen($quot);
				}
			}

			$pos2 = strpos($this->message, $quoted == false ? ']' : $quot . ']', $nested_tag_pos ?? $this->pos1);

			if ($pos2 === false) {
				return;
			}

			$data = substr($this->message, $this->pos1, $pos2 - $this->pos1);

			// Validation for my parking, please!
			if (isset($tag['validate'])) {
				call_user_func_array($tag['validate'], [&$tag, &$data, $this->disabled, $params]);
			}

			// For parsed content, we must recurse to avoid security problems.
			if ($tag['type'] != 'unparsed_equals') {
				$smileys = $this->smileys;
				$parse_tags = $this->parse_tags;

				$this->smileys = empty($tag['parsed_tags_allowed']);
				$this->parse_tags = !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : [];

				$data = $this->parse($data);

				$this->smileys = $smileys;
				$this->parse_tags = $parse_tags;
			}

			$tag['after'] = strtr($tag['after'], ['$1' => $data]);

			$this->open_tags[] = $tag;

			$html = strtr($tag['before'], ['$1' => $data]);

			$this->message = substr($this->message, 0, $this->pos) . "\n" . $html . "\n" . substr($this->message, $pos2 + ($quoted == false ? 1 : 1 + strlen($quot)));

			$this->pos += strlen($html) - 1 + 2;
		}

		// If this is block level, eat any breaks after it.
		if (!empty($tag['block_level']) && substr($this->message, $this->pos + 1, 4) == '<br>') {
			$this->message = substr($this->message, 0, $this->pos + 1) . substr($this->message, $this->pos + 5);
		}

		// Are we trimming outside this tag?
		if (!empty($tag['trim']) && $tag['trim'] != 'outside' && preg_match('~(<br>|&nbsp;|\s)*~', substr($this->message, $this->pos + 1), $matches) != 0) {
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

		// A list of tags that's disabled by the admin.
		$disabled = empty(Config::$modSettings['disabledBBC']) ? [] : array_flip(explode(',', strtolower(Config::$modSettings['disabledBBC'])));

		// Get a list of all the tags that are not disabled.
		$all_tags = self::getCodes();
		$valid_tags = [];
		$self_closing_tags = [];

		foreach ($all_tags as $tag) {
			if (!isset($disabled[$tag['tag']])) {
				$valid_tags[$tag['tag']] = !empty($tag['block_level']);
			}

			if (isset($tag['type']) && $tag['type'] == 'closed') {
				$self_closing_tags[] = $tag['tag'];
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

		// Need to sort the tags by name length.
		uksort(
			$valid_tags,
			function ($a, $b) {
				return strlen($a) < strlen($b) ? 1 : -1;
			},
		);

		// These inline tags can compete with each other regarding style.
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

						// Reopen tags that were closed before the code block.
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
							// Close all the inline tags, a block tag is coming...
							$parts[$i] .= '[/' . implode('][/', array_reverse($inline_elements)) . ']';

							// Now open them again, we're inside the block tag now.
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
								// Find the tags with the same tagname
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

			// Close the code tags.
			if ($in_code) {
				$parts[$i] .= '[/code]';
			}
			// The same for nobbc tags.
			elseif ($in_nobbc) {
				$parts[$i] .= '[/nobbc]';
			}
			// Still inline tags left unclosed? Close them now, better late than never.
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
			'smileys',
			'parse_tags',
			'open_tags',
			'inside',
			'pos',
			'last_pos',
			'placeholders',
			'placeholders_counter',
			'cache_key_extras',
		];

		$class_vars = get_class_vars(__CLASS__);

		foreach ($to_reset as $var) {
			$this->{$var} = $class_vars[$var];
		}
	}
}

?>