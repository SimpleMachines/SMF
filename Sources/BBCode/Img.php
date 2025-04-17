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
 * Represents the img BBCode.
 */
class Img extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'img';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?array $parameters = [
		'alt' => ['optional' => true],
		'title' => ['optional' => true],
		'width' => ['optional' => true, 'value' => ' width="$1"', 'match' => '(\d+)'],
		'height' => ['optional' => true, 'value' => ' height="$1"', 'match' => '(\d+)'],
	];

	/**
	 *
	 */
	public ?string $content = '$1';

	/**
	 *
	 */
	public ?string $disabled_content = '($1)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface|array &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$url = new Url(strtr(trim($data), ['<br>' => '', ' ' => '%20']), true);
		$url->toAscii();

		if (!isset($url->scheme)) {
			$url = new Url('//' . ltrim((string) $url, ':/'));
		} else {
			$url = $url->proxied();
		}

		$alt = !empty($params['{alt}']) ? ' alt="' . $params['{alt}'] . '"' : ' alt=""';
		$title = !empty($params['{title}']) ? ' title="' . $params['{title}'] . '"' : '';

		$data = isset($disabled[$bbc->tag]) ? $url : '<img src="' . $url . '"' . $alt . $title . $params['{width}'] . $params['{height}'] . ' class="bbc_img' . (!empty($params['{width}']) || !empty($params['{height}']) ? ' resized' : '') . '" loading="lazy">';
	}
}

?>