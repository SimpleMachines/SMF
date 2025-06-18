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

class ReservedNames extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting "reserved_names"';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return \count(Db::$db->list_tables(false, Config::$db_prefix . 'reserved_names')) > 0;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT setting, value
			FROM {db_prefix}reserved_names',
		);

		$words = [];
		$match_settings = [
			'matchword' => 0,
			'matchcase' => 0,
			'matchuser' => 0,
			'matchname' => 0,
		];

		while ($row = Db::$db->fetch_assoc($request)) {
			if (substr($row['setting'], 0, 5) == 'match') {
				$match_settings[$row['setting']] = (int) $row['value'];
			} else {
				$words[] = $row['value'];
			}
		}

		Db::$db->free_result($request);

		Config::updateModSettings([
			'reserveWord' => $match_settings['matchword'],
			'reserveCase' => $match_settings['matchcase'],
			'reserveUser' => $match_settings['matchuser'],
			'reserveName' => $match_settings['matchname'],
			'reserveNames' => implode("\n", $words),
		]);

		Db::$db->drop_table('{db_prefix}reserved_names');

		$this->handleTimeout();

		return true;
	}
}
