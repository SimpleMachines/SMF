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
 * Represents the hr BBCode.
 */
class Hr extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'hr';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_CLOSED;

	/**
	 *
	 */
	public ?string $content = '<hr class="bbc_hr">';

	/**
	 *
	 */
	public ?string $disabled_content = '<div></div>';

	/**
	 *
	 */
	public bool $block_level = true;
}
