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
 * Represents the acronym BBCode.
 *
 * Legacy (and just an alias for [abbr] even when enabled).
 */
class Acronym extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'acronym';

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

?>