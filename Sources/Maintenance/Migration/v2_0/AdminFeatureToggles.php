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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Config;
use SMF\Maintenance\Migration\MigrationBase;

class AdminFeatureToggles extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Implementing admin feature toggles';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return !isset(Config::$modSettings['admin_features']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Work out what they used to have enabled.
		$enabled_features = ['rg'];

		if (!empty(Config::$modSettings['cal_enabled'])) {
			$enabled_features[] = 'cd';
		}

		if (!empty(Config::$modSettings['karmaMode'])) {
			$enabled_features[] = 'k';
		}

		if (!empty(Config::$modSettings['modlog_enabled'])) {
			$enabled_features[] = 'ml';
		}

		if (!empty(Config::$modSettings['paid_enabled'])) {
			$enabled_features[] = 'ps';
		}

		Config::updateModSettings([
			'admin_features' => implode(',', $enabled_features),
		]);

		$this->handleTimeout();

		return true;
	}
}
