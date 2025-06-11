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

namespace SMF\Maintenance\Cleanup\v1_1;

use SMF\Config;
use SMF\Maintenance\Cleanup\CleanupBase;

class RemoveTempSettings extends CleanupBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * Name of the cleanup task.
	 */
	public string $name = 'Removing temporary settings';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		Config::updateModSettings([
			'dont_repeat_smtp' => null,
			'dont_repeat_theme' => null,
		]);

		return true;
	}
}
