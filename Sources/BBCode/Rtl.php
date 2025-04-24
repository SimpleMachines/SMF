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
 * Represents the rtl BBCode.
 */
class Rtl extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'rtl';

	/**
	 *
	 */
	public ?string $before = '<bdo dir="rtl">';

	/**
	 *
	 */
	public ?string $after = '</bdo>';

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

?>