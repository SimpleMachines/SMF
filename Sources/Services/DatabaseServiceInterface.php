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

namespace SMF\Services;

use SMF\Db\DatabaseApi;

/**
 * Interface for the Database Service.
 */
interface DatabaseServiceInterface
{
	/**
	 * Get the underlying DatabaseApi instance.
	 *
	 * @return DatabaseApi
	 */
	public function getApi(): DatabaseApi;
}
