<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\BBCode;

/**
 * Represents the abbr BBCode.
 */
class Abbr extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'abbr';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_EQUALS;

	/**
	 *
	 */
	public ?string $before = '<abbr title="$1">';

	/**
	 *
	 */
	public ?string $after = '</abbr>';

	/**
	 *
	 */
	public ?string $disabled_before = '';

	/**
	 *
	 */
	public ?string $disabled_after = ' ($1)';

	/**
	 *
	 */
	public bool $block_level = false;

	/**
	 *
	 */
	public string $trim = 'none';

	/**
	 *
	 */
	public ?string $quoted = 'optional';
}
