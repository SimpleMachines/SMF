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
 * Represents the me BBCode.
 */
class Me extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'me';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_EQUALS;

	/**
	 *
	 */
	public ?string $before = '<div class="meaction">* $1 ';

	/**
	 *
	 */
	public ?string $after = '</div>';

	/**
	 *
	 */
	public ?string $disabled_before = '/me ';

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
	public ?string $quoted = 'optional';
}
