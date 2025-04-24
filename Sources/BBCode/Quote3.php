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
 * Represents the parsed_equals version of the quote BBCode.
 */
class Quote3 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'quote';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_PARSED_EQUALS;

	/**
	 *
	 */
	public ?string $before = '<blockquote><cite>{txt_quote_from}: $1</cite>';

	/**
	 *
	 */
	public ?string $after = '</blockquote>';

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
	public string $trim = 'both';

	/**
	 *
	 */
	public ?string $quoted = 'optional';

	/**
	 * Don't allow everything to be embedded with the author name.
	 */
	public ?array $parsed_tags_allowed = ['url', 'iurl', 'ftp'];
}

?>