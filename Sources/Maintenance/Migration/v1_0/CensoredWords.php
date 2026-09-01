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

class CensoredWords extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting censored words';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return !isset(Config::$modSettings['censor_vulgar']) || !isset(Config::$modSettings['censor_proper']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Can't make changes without the table.
		if (\count(Db::$db->list_tables(false, Config::$db_prefix . 'censor')) === 0) {
			Config::updateModSettings([
				'censor_vulgar' => Config::$modSettings['censor_vulgar'] ?? '',
				'censor_proper' => Config::$modSettings['censor_proper'] ?? '',
			]);

			$this->handleTimeout();

			return true;
		}

		$request = $this->query(
			'SELECT vulgar, proper
			FROM {db_prefix}censor',
		);

		$censor_vulgar = [];
		$censor_proper = [];

		while ($row = Db::$db->fetch_row($request)) {
			$censor_vulgar[] = trim($row[0]);
			$censor_proper[] = trim($row[1]);
		}

		Db::$db->free_result($request);

		Config::updateModSettings([
			'censor_vulgar' => implode("\n", $censor_vulgar),
			'censor_proper' => implode("\n", $censor_proper),
		]);

		Db::$db->drop_table('{db_prefix}censor');

		$this->handleTimeout();

		return true;
	}
}
