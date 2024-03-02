<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\Indices;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class PollChoices extends Table
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
		$this->name = 'poll_choices';

		$this->columns = [
			new Column(
				name: 'id_poll',
				type: 'mediumint',
				unsigned: true,
				default: 0,
			),
			new Column(
				name: 'id_choice',
				type: 'tinyint',
				unsigned: true,
				default: 0,
			),
			new Column(
				name: 'label',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'votes',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'id_poll',
					'id_choice',
				],
			),
		];
	}
}

?>