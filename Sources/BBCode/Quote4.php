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
 * Represents the version of the quote BBCode with multiple parameters.
 */
class Quote4 extends BBCode
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
	public ?array $parameters = [
		'author' => ['match' => '([^<>]{1,192}?)'],
		'link' => ['match' => '(?:board=\d+;)?((?:topic|threadid)=[\dmsg#\./]{1,40}(?:;start=[\dmsg#\./]{1,40})?|msg=\d+?|action=profile;u=\d+)'],
		'date' => ['match' => '(\d+)', 'validate' => 'SMF\\Time::stringFromUnix'],
	];

	/**
	 *
	 */
	public ?string $before = '<blockquote><cite><a href="{scripturl}?{link}">{txt_quote_from}: {author} {txt_search_on} {date}</a></cite>';

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
}
