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
 * Represents the parsed content version of the list BBCode.
 */
class List1 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'list';

	/**
	 *
	 */
	public ?string $before = '<ul class="bbc_list">';

	/**
	 *
	 */
	public ?string $after = '</ul>';

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
	public string $trim = 'inside';

	/**
	 *
	 */
	public ?array $require_children = ['li', 'list'];
}
