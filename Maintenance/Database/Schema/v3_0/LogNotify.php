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
class LogNotify extends Table
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
		$this->name = 'log_notify';

		$this->columns = [
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				default: 0,
			),
			new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				default: 0,
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				default: 0,
			),
			new Column(
				name: 'sent',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_member',
					'id_topic',
					'id_board',
				],
			),
			new DbIndex(
				name: 'idx_id_topic',
				columns: [
					'id_topic',
					'id_member',
				],
			),
			new DbIndex(
				name: 'id_board',
				columns: [
					'id_board',
				],
			),
		];
	}
}

?>