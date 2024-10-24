<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Cleanup;

/**
 * Base class for cleanup tasks.
 */
abstract class CleanupBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the cleanup task.
	 */
	public string $name;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Cleanup task we will execute.
	 *
	 * @return bool True if successful (or skipped), false otherwise.
	 */
	public function execute(): bool
	{
		return true;
	}
}

?>