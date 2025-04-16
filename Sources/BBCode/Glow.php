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
 * Represents the glow BBCode.
 *
 * Legacy (one of those things that should not be done)
 */
class Glow extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'glow';

	/**
	 *
	 */
	public ?string $type = 'unparsed_commas';

	/**
	 *
	 */
	public ?string $test = '[#0-9a-zA-Z\-]{3,12},([012]\d{1,2}|\d{1,2})(,[^]]+)?\]';

	/**
	 *
	 */
	public ?string $before = '<span style="text-shadow: $1 1px 1px 1px">';

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