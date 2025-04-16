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
 * Represents the anchor BBCode.
 */
class Anchor extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'anchor';

	/**
	 *
	 */
	public ?string $type = 'unparsed_equals';

	/**
	 *
	 */
	public ?string $test = '[#]?([A-Za-z][A-Za-z0-9_\-]*)\]';

	/**
	 *
	 */
	public ?string $before = '<span id="post_$1">';

	/**
	 *
	 */
	public ?string $after = '</span>';

	/**
	 *
	 */
	public bool $block_level = false;

	/**
	 *
	 */
	public string $trim = 'none';
}

?>