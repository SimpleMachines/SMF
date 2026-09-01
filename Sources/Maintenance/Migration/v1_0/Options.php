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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class Options extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting settings to options';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Format: new_setting -> old_setting_name.
		$values = [
			'calendar_start_day' => 'cal_startmonday',
			'view_newest_first' => 'viewNewestFirst',
			'view_newest_pm_first' => 'viewNewestFirst',
		];

		foreach ($values as $variable => $value) {
			if (empty(Config::$modSettings[$value[0]])) {
				continue;
			}

			Db::$db->query(
				'INSERT IGNORE INTO {db_prefix}themes
					(id_member, id_theme, variable, value)
				SELECT id_member, 1, {string:variable}, {string:value}
				FROM {db_prefix}members',
				[
					'variable' => $variable,
					'value' => Config::$modSettings[$value[0]],
					'db_error_skip' => true,
				],
			);

			Db::$db->query(
				'INSERT IGNORE INTO {db_prefix}themes
					(id_member, id_theme, variable, value)
				VALUES (-1, 1, {string:variable}, {string:value})',
				[
					'variable' => $variable,
					'value' => Config::$modSettings[$value[0]],
					'db_error_skip' => true,
				],
			);
		}

		$this->handleTimeout();

		return true;
	}
}
