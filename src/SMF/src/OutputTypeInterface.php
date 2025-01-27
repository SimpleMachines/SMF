<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF;

/**
 * Interface for all output ttypes
 */
interface OutputTypeInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Returns the MIME type associated with the output type.
	 *
	 * @return string The MIME type.
	 */
	public function getMimeType(): string;
}

?>