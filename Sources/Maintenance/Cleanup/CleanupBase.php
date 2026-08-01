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

namespace SMF\Maintenance\Cleanup;

use SMF\Maintenance\SubStepInterface;

/**
 * Base class for cleanup tasks.
 */
abstract class CleanupBase implements SubStepInterface
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
	 *
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		return true;
	}
}
