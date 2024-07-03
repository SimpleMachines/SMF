<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogPackages extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [];

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
			new Column(
				name: 'id_install',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'filename',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'package_id',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'version',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_member_installed',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'member_installed',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'time_installed',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member_removed',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'member_removed',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'time_removed',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'install_state',
				type: 'mediumint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'failed_steps',
				type: 'text',
				not_null: true,
				default: false,
			),
			new Column(
				name: 'themes_installed',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'db_changes',
				type: 'text',
				not_null: true,
				default: false,
			),
			new Column(
				name: 'credits',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'sha256_hash',
				type: 'text',
				not_null: false,
				default: null,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_install',
				],
			),
			new DbIndex(
				name: 'filename',
				columns: [
					'filename',
				],
			),
			new DbIndex(
				name: 'id_hash',
				columns: [
					'id_hash',
				],
			),
		];
	}
}

?>