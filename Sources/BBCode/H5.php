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

namespace SMF\BBCode;

/**
 * Represents the h5 BBCode.
 *
 * For the h1-h6 tags, the element name will often change in the final
 * output, but the class will not. For example, `<h1 class="bbc_h1">`
 * might become `<h5 class="bbc_h1">` in the final output.
 */
class H5 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'h5';

	/**
	 *
	 */
	public ?string $before = '<h5 class="bbc_h5">';

	/**
	 *
	 */
	public ?string $after = '</h5>';

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
}
