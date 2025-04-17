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
 * Represents the unparsed_content version of the email BBCode.
 */
class Email1 extends BBCode
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $tag = 'email';

	/**
	 *
	 */
	public ?string $type = BBCode::TYPE_UNPARSED_CONTENT;

	/**
	 *
	 */
	public ?string $content = '<a href="mailto:$1" class="bbc_email">$1</a>';

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
	public function validate(BBCodeInterface|array &$bbc, array|string &$data, array $disabled, array $params): void
	{
		$data = strtr($data, ['<br>' => '']);
	}
}

?>