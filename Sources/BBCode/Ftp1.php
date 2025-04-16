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
 * Represents the unparsed_content version of the ftp BBCode.
 *
 * Legacy (alias of [url] with an FTP URL)
 */
class Ftp1 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'ftp';

	/**
	 *
	 */
	public ?string $type = 'unparsed_content';

	/**
	 *
	 */
	public ?string $content = '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>';

	/**
	 *
	 */
	public ?string $disabled_content = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface|array &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$data = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);

		if (empty($data->scheme)) {
			$data = new Url('ftp://' . ltrim((string) $data, ':/'));
		}

		if (isset($bbc->content)) {
			$ascii_url = (clone $data)->toAscii();

			if ((string) $ascii_url !== (string) $data) {
				$bbc->content = str_replace('href="$1"', 'href="' . $ascii_url . '"', $bbc->content);
			}
		} else {
			$data->toAscii();
		}
	}
}

?>