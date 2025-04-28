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
 * Represents the unparsed_content version of the url BBCode.
 */
class Url1 extends BBCode
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
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?string $content = '<a href="$1" class="bbc_link" target="_blank" rel="noopener">$1</a>';

	/**
	 *
	 */
	public ?string $disabled_content = '$1';

	/**
	 * @var string
	 *
	 * Default URL scheme.
	 */
	public string $default_scheme = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$data = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);

		if (empty($data->scheme)) {
			$data = new Url((!empty($default_scheme) ? rtrim($default_scheme, ':') . ':' : '') . '//' . ltrim((string) $data, ':/'));
		}

		$ascii_url = (clone $data)->toAscii();

		if ((string) $ascii_url !== (string) $data) {
			$bbc->content = str_replace('href="$1"', 'href="' . $ascii_url . '"', $bbc->content);
		}
	}
}
