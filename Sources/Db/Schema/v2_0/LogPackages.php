<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogPackages extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'log_packages';

		$this->columns = [
			'id_install' => new Column(
				name: 'id_install',
				type: 'int',
				not_null: true,
				auto: true,
			),
			'filename' => new Column(
				name: 'filename',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'package_id' => new Column(
				name: 'package_id',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'name' => new Column(
				name: 'name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'version' => new Column(
				name: 'version',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'id_member_installed' => new Column(
				name: 'id_member_installed',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'member_installed' => new Column(
				name: 'member_installed',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'time_installed' => new Column(
				name: 'time_installed',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'id_member_removed' => new Column(
				name: 'id_member_removed',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'member_removed' => new Column(
				name: 'member_removed',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'time_removed' => new Column(
				name: 'time_removed',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'install_state' => new Column(
				name: 'install_state',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'failed_steps' => new Column(
				name: 'failed_steps',
				type: 'text',
				not_null: true,
			),
			'themes_installed' => new Column(
				name: 'themes_installed',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'db_changes' => new Column(
				name: 'db_changes',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_install',
					],
				],
			),
			'filename' => new DbIndex(
				name: 'filename',
				columns: [
					[
						'name' => 'filename',
						'size' => 15,
					],
				],
			),
		];

		parent::__construct();
	}
}
