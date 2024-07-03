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
class Polls extends Table
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
		$this->name = 'polls';

		$this->columns = [
			new Column(
				name: 'id_poll',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'question',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'voting_locked',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'max_votes',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'expire_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'hide_results',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'change_vote',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'guest_vote',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'num_guest_voters',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'reset_poll',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'poster_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_poll',
				],
			),
		];
	}
}

?>