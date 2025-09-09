<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\BBCode;

use SMF\ArrayAccessHelper;
use SMF\Parsers\BBCodeParser;

/**
 * Base class for BBCodes.
 */
abstract class BBCode implements \ArrayAccess, BBCodeInterface
{
	use ArrayAccessHelper;

	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var null
	 *
	 * Used for the form `[tag]parsed content[/tag]`
	 */
	public const TYPE_PARSED_CONTENT = null;

	/**
	 * @var string
	 *
	 * Used for the form `[tag=parsed data]parsed content[/tag]`
	 */
	public const TYPE_PARSED_EQUALS = 'parsed_equals';

	/**
	 * @var string
	 *
	 * Used for the form `[tag=...]parsed content[/tag]`
	 */
	public const TYPE_UNPARSED_EQUALS = 'unparsed_equals';

	/**
	 * @var string
	 *
	 * Used for the form `[tag=1,2,3]parsed content[/tag]`
	 */
	public const TYPE_UNPARSED_COMMAS = 'unparsed_commas';

	/**
	 * @var string
	 *
	 * Used for the forms `[tag]`, `[tag/]`, or `[tag /]`
	 */
	public const TYPE_CLOSED = 'closed';

	/**
	 * @var string
	 *
	 * Used for the form `[tag]unparsed content[/tag]`
	 */
	public const TYPE_UNPARSED_CONTENT = 'unparsed_content';

	/**
	 * @var string
	 *
	 * Used for the form `[tag=1,2,3]unparsed content[/tag]`
	 */
	public const TYPE_UNPARSED_COMMAS_CONTENT = 'unparsed_commas_content';

	/**
	 * @var string
	 *
	 * Used for the form `[tag=...]unparsed content[/tag]`
	 */
	public const TYPE_UNPARSED_EQUALS_CONTENT = 'unparsed_equals_content';

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The tag's name - should be lowercase!
	 */
	public string $tag;

	/**
	 * @var ?string
	 *
	 * The BBCode type.
	 *
	 * Must be one of this class's TYPE_* constants.
	 */
	public ?string $type = self::TYPE_PARSED_CONTENT;

	/**
	 * @var ?array
	 *
	 * An optional array of parameters, for the form [tag abc=123]content[/tag].
	 *
	 * The array is an associative array where the keys are the parameter names,
	 * and the values are an array which may contain the following:
	 *
	 *  - match: a regular expression to validate and match the value.
	 *  - quoted: true if the value should be quoted.
	 *  - validate: callback to evaluate on the data, which is $data.
	 *  - value: a string in which to replace $1 with the data.
	 *  - optional: true if the parameter is optional.
	 *  - default: a default value for missing optional parameters.
	 *
	 * Either value or validate may be used, not both.
	 */
	public ?array $parameters;

	/**
	 * @var string|null
	 *
	 * A regular expression to test immediately after the tag's '=', ' ' or ']'.
	 *
	 * Typically, should have a \] at the end. Optional.
	 */
	public ?string $test;

	/**
	 * @var string|null
	 *
	 * Content to be inserted for this BBCode.
	 *
	 * Only available for the unparsed_content, closed, unparsed_commas_content,
	 * and unparsed_equals_content types.
	 *
	 *  - $1 is replaced with the content of the tag.
	 *  - For unparsed_commas_content, $2, $3, ..., $n are replaced.
	 *  - Parameters are replaced in the form {param}.
	 *  - The form {txt_*} can be used to insert Lang::$txt strings.
	 *    For example, `{txt_code}` will be replaced with the value of
	 *    Lang::$txt['code'].
	 *  - The form {editortxt_*} can be used to insert Lang::$editortxt strings.
	 *    For example, `{editortxt_code}` will be replaced with the value of
	 *    Lang::$editortxt['code'].
	 */
	public ?string $content;

	/**
	 * @var string|null
	 *
	 * HTML (or whatever) to insert before the BBCode's content.
	 *
	 * Only used when the $content property is not used.
	 *
	 *  - For unparsed_equals, $1 is replaced with the value after the '='.
	 *  - For unparsed_commas, $1, $2, ..., $n are replaced.
	 *  - Parameters are replaced in the form {param}.
	 *  - The form {txt_*} can be used to insert Lang::$txt strings.
	 *    For example, `{txt_code}` will be replaced with the value of
	 *    Lang::$txt['code'].
	 *  - The form {editortxt_*} can be used to insert Lang::$editortxt strings.
	 *    For example, `{editortxt_code}` will be replaced with the value of
	 *    Lang::$editortxt['code'].
	 */
	public ?string $before;

	/**
	 * @var string|null
	 *
	 * Similar to $before in every way, except that it is used when the tag is
	 * closed.
	 */
	public ?string $after;

	/**
	 * @var string|null
	 *
	 * Used in place of $content when the tag is disabled.
	 *
	 * For the closed type, default is ''. For other types, the default is '$1'
	 * if $block_level is false, or '<div>$1</div>' if $block_level is true.
	 */
	public ?string $disabled_content;

	/**
	 * @var string|null
	 *
	 * Used in place of $before when the tag is disabled.
	 *
	 * The default is '' if $block_level is false, or '<div>' if $block_level
	 * is true.
	 */
	public ?string $disabled_before;

	/**
	 * @var string|null
	 *
	 * Used in place of $after when the tag is disabled.
	 *
	 * The default is '' if $block_level is false, or '</div>' if $block_level
	 * is true.
	 */
	public ?string $disabled_after;

	/**
	 * @var bool
	 *
	 * Whether the tag is a "block level" tag, similar to HTML.
	 *
	 * Block level tags cannot be nested inside tags that are not block level.
	 * Block level tags will not be implicitly closed as easily as inline tags,
	 * One break following a block level tag may also be removed.
	 */
	public bool $block_level = false;

	/**
	 * @var string
	 *
	 * Controls where to trim whitespace.
	 *
	 * If set to 'inside', whitespace after the opening tag will be removed.
	 * If set to 'outside', whitespace after the closing tag will meet the same
	 * fate.
	 * If set to 'both', both will occur.
	 * If set to '', no trimming will occur.
	 */
	public string $trim = '';

	/**
	 * @var ?string
	 *
	 * When type is 'unparsed_equals' or 'parsed_equals' only,
	 * may be not set, 'optional', or 'required' corresponding to if
	 * the content may be quoted. This allows the parser to read
	 * [tag="abc]def[esdf]"] properly.
	 */
	public ?string $quoted;

	/**
	 * @var ?array
	 *
	 * An array of tag names, or not set.
	 *
	 * If set, the enclosing tag *must* be one of the listed tags, or parsing
	 * won't occur.
	 */
	public ?array $require_parents;

	/**
	 * @var ?array
	 *
	 * An array of tag names, or not set.
	 *
	 * If this is set, child tags inside the content of this tag will be parsed
	 * only if their tag names are in this list.
	 */
	public ?array $require_children;

	/**
	 * @var ?array
	 *
	 * An array of tag names, or not set.
	 *
	 * The inverse of $require_children. If this is set, the listed tags will
	 * not be parsed inside the content of this tag.
	 */
	public ?array $disallow_children;

	/**
	 * @var ?array
	 *
	 * An array of tag names restricting which BBCode can legally occur inside
	 * the value after '='.
	 *
	 * Only applicable to BBCodes with the parsed_equals type.
	 */
	public ?array $parsed_tags_allowed;

	/**
	 * @var BBCodeParser
	 *
	 * The BBCodeParser instance that owns this object.
	 */
	public BBCodeParser $parser;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void {}
}
