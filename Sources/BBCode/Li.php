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
 * Represents the li BBCode.
 */
class Li extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'li';

	/**
	 *
	 */
	public ?string $before = '<li>';

	/**
	 *
	 */
	public ?string $after = '</li>';

	/**
	 *
	 */
	public ?string $disabled_before = '';

	/**
	 *
	 */
	public ?string $disabled_after = '<br>';

	/**
	 *
	 */
	public bool $block_level = true;

	/**
	 *
	 */
	public string $trim = 'outside';

	/**
	 *
	 */
	public ?array $require_parents = ['list'];
}

?>