<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance;

/**
 * Interface for substeps, such as migration tasks or cleanup tasks.
 */
interface SubStepInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Checks if the task should be performed or not.
	 *
	 * @return bool True if this task needs to be run, false otherwise.
	 */
	public function isCandidate(): bool;

	/**
	 * Runs the task.
	 *
	 * @return bool True if successful (or skipped), false otherwise.
	 */
	public function execute(): bool;
}
