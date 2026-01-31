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

/**
 * Interface for all BBCode classes.
 */
interface BBCodeInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Validates a particular occurrence of the BBCode in the text that is
	 * being parsed.
	 *
	 * @param BBCodeInterface &$bbc This BBCode definition.
	 *    Passed in as an argument for historical reasons.
	 * @param array|string &$data The data extracted from the particular
	 *    occurrence of the BBCode in the text.
	 * @param array $disabled List of tag names of disabled BBCodes.
	 * @param array $params Parameters extracted from the particular
	 *    occurence of the BBCode in the text.
	 */
	public function validate(BBCodeInterface &$bbc, array|string &$data, array $disabled, array $params): void;
}
