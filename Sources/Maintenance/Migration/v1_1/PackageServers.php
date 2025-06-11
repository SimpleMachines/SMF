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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class PackageServers extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding package servers';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v1_1\PackageServers();
		$table->normalize();

		Db::$db->insert(
			method: 'ignore',
			table: '{db_prefix}package_servers',
			columns: [
				'id_server' => 'int',
				'name' => 'string-255',
				'url' => 'string-255',
			],
			data: [
				[
					1,
					'Simple Machines Third-party Mod Site',
					'http://mods.simplemachines.org',
				],
			],
			keys: ['id_server'],
		);

		$this->handleTimeout();

		return true;
	}
}
