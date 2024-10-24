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

namespace SMF\Maintenance\Database\Schema\v2_1;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class LogDigest extends Table
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
		$this->name = 'log_digest';

		$this->columns = [
			'id_topic' => new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_msg' => new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'note_type' => new Column(
				name: 'note_type',
				type: 'varchar',
				size: 10,
				not_null: true,
				default: 'post',
			),
			'daily' => new Column(
				name: 'daily',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'exclude' => new Column(
				name: 'exclude',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];
	}
}

?>