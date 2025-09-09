<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class PollChoices extends Table
{
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
			'id_poll' => new Column(
				name: 'id_poll',
				type: 'mediumint',
				unsigned: true,
				default: 0,
			),
			'id_choice' => new Column(
				name: 'id_choice',
				type: 'tinyint',
				unsigned: true,
				default: 0,
			),
			'label' => new Column(
				name: 'label',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'votes' => new Column(
				name: 'votes',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_poll',
					],
					[
						'name' => 'id_choice',
					],
				],
			),
		];

		parent::__construct();
	}
}
