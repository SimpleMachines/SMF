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

use SMF\Url;

/**
 * Represents the unparsed_equals version of the url BBCode.
 */
class Url2 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'url';

	/**
	 *
	 */
	public ?string $type = 'unparsed_equals';

	/**
	 *
	 */
	public ?string $before = '<a href="$1" class="bbc_link" target="_blank" rel="noopener">';

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
	public ?string $disabled_after = ' ($1)';

	/**
	 *
	 */
	public ?string $quoted = 'optional';

	/**
	 *
	 */
	public ?array $disallow_children = ['email', 'ftp', 'url', 'iurl'];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface|array &$bbc, array|string &$data, array $disabled, array $params): void
	{
		if (str_starts_with($data, '#')) {
			$data = '#post_' . substr($data, 1);
		} else {
			$data = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);
			$data->toAscii();

			if (empty($data->scheme)) {
				$data = '//' . ltrim((string) $data, ':/');
			}
		}
	}
}

?>