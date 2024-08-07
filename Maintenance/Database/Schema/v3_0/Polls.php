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
			'id_poll' => new Column(
				name: 'id_poll',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			'question' => new Column(
				name: 'question',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'voting_locked' => new Column(
				name: 'voting_locked',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'max_votes' => new Column(
				name: 'max_votes',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'expire_time' => new Column(
				name: 'expire_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'hide_results' => new Column(
				name: 'hide_results',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'change_vote' => new Column(
				name: 'change_vote',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'guest_vote' => new Column(
				name: 'guest_vote',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'num_guest_voters' => new Column(
				name: 'num_guest_voters',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'reset_poll' => new Column(
				name: 'reset_poll',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'poster_name' => new Column(
				name: 'poster_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_poll',
				],
			),
		];
	}
}

?>