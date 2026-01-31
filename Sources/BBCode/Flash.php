<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\BBCode;

use SMF\Url;

/**
 * Represents the flash BBCode.
 *
 * Legacy (and just a link even when not disabled)
 */
class Flash extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'flash';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_COMMAS_CONTENT;

	/**
	 *
	 */
	public ?string $test = '\d+,\d+\]';

	/**
	 *
	 */
	public ?string $content = '<a href="$1" target="_blank" rel="noopener">$1</a>';

	/**
	 *
	 */
	public ?string $disabled_content = '$1';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$data[0] = new Url(strtr(trim($data[0]), ['<br>' => '', ' ' => '%20']), true);

		if (empty($data[0]->scheme)) {
			$data[0] = new Url('//' . ltrim($data[0], ':/'));
		}

		$ascii_url = (clone $data[0])->toAscii();

		if ((string) $ascii_url !== (string) $data[0]) {
			$bbc->content = str_replace('href="$1"', 'href="' . $ascii_url . '"', $bbc->content);
		}
	}
}
