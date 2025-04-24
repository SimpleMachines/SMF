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
 * Represents the left BBCode.
 */
class Left extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'left';

	/**
	 *
	 */
	public ?string $before = '<div class="lefttext"><div class="inline-block">';

	/**
	 *
	 */
	public ?string $after = '</div></div>';

	/**
	 *
	 */
	public ?string $disabled_before = '<div>';

	/**
	 *
	 */
	public ?string $disabled_after = '<div>';

	/**
	 *
	 */
	public bool $block_level = true;
}

?>