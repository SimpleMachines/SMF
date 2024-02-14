<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance;

/**
 * Migration container for a maintenance task.
 */
class Migration
{
	/**
	 * @var string
	 *
	 * Name of the migration tasks.
	 */
	public string $name;

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