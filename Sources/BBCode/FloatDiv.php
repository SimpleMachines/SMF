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

namespace SMF\BBCode;

/**
 * Represents the float BBCode.
 */
class FloatDiv extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'float';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_EQUALS;

	/**
	 *
	 */
	public ?string $test = '(left|right)(\s+max=\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)?\]';

	/**
	 *
	 */
	public ?string $before = '<div $1>';

	/**
	 *
	 */
	public ?string $after = '</div>';

	/**
	 *
	 */
	public ?string $disabled_before = '<div>';

	/**
	 *
	 */
	public ?string $disabled_after = '</div>';

	/**
	 *
	 */
	public bool $block_level = true;

	/**
	 *
	 */
	public string $trim = 'outside';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$class = 'class="bbc_float float' . (str_starts_with($data, 'left') ? 'left' : 'right') . '"';

		if (preg_match('~\bmax=(\d+(?:%|px|em|rem|ex|pt|pc|ch|vw|vh|vmin|vmax|cm|mm|in)?)~', $data, $matches)) {
			$css = ' style="max-width:' . $matches[1] . (is_numeric($matches[1]) ? 'px' : '') . '"';
		} else {
			$css = '';
		}

		$data = $class . $css;
	}
}
