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
 * Represents the bdo BBCode.
 *
 * Legacy (equivalent to [ltr] or [rtl])
 */
class Bdo extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'bdo';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_EQUALS;

	/**
	 *
	 */
	public ?string $test = '(rtl|ltr)\]';

	/**
	 *
	 */
	public ?string $before = '<bdo dir="$1">';

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
