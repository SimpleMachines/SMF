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
 * Represents the youtube BBCode.
 */
class YouTube extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'youtube';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?string $content = '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube.com/embed/$1?origin={hosturl}&wmode=opaque" data-youtube-id="$1" allowfullscreen loading="lazy"></iframe></div></div>';

	/**
	 *
	 */
	public ?string $disabled_content = '<a href="https://www.youtube.com/watch?v=$1" target="_blank" rel="noopener">https://www.youtube.com/watch?v=$1</a>';

	/**
	 *
	 */
	public bool $block_level = true;
}

?>