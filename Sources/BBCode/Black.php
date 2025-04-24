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
 * Represents the black BBCode.
 *
 * Legacy (alias of [color=black])
 */
class Black extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'black';

	/**
	 *
	 */
	public ?string $before = '<span style="color: black;" class="bbc_color">';

	/**
	 *
	 */
	public ?string $after = '</span>';

	/**
	 *
	 */
	public ?string $disabled_before = '';

	/**
	 *
	 */
	public ?string $disabled_after = '';
}

?>