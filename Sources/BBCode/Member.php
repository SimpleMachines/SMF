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
 * Represents the member BBCode.
 */
class Member extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'member';

	/**
	 *
	 */
	public ?string $type = 'unparsed_equals';

	/**
	 *
	 */
	public ?string $before = '<a href="{scripturl}?action=profile;u=$1" class="mention" data-mention="$1">@';

	/**
	 *
	 */
	public ?string $after = '</a>';

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