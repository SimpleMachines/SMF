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
 * Represents the shadow BBCode.
 *
 * Legacy (never a good idea)
 */
class Shadow extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'shadow';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_COMMAS;

	/**
	 *
	 */
	public ?string $test = '[#0-9a-zA-Z\-]{3,12},(left|right|top|bottom|[0123]\d{0,2})\]';

	/**
	 *
	 */
	public ?string $before = '<span style="text-shadow: $1 $2">';

	/**
	 *
	 */
	public ?string $after = '</span>';

	/**
	 *
	 */
	public ?string $disabled_before = '';

	/**
	 *
	 */
	public ?string $disabled_after = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function validate(BBCodeInterface|array &$bbc, array|string &$data, array $disabled, array $params): void
	{
		if ($data[1] == 'top' || (is_numeric($data[1]) && $data[1] < 50)) {
			$data[1] = '0 -2px 1px';
		} elseif ($data[1] == 'right' || (is_numeric($data[1]) && $data[1] < 100)) {
			$data[1] = '2px 0 1px';
		} elseif ($data[1] == 'bottom' || (is_numeric($data[1]) && $data[1] < 190)) {
			$data[1] = '0 2px 1px';
		} elseif ($data[1] == 'left' || (is_numeric($data[1]) && $data[1] < 280)) {
			$data[1] = '-2px 0 1px';
		} else {
			$data[1] = '1px 1px 1px';
		}
	}
}

?>