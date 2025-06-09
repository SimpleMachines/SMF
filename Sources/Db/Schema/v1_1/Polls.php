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

namespace SMF\Db\Schema\v1_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Polls extends Table
{
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
			'ID_POLL' => new Column(
				name: 'ID_POLL',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'question' => new Column(
				name: 'question',
				type: 'tinytext',
				not_null: true,
			),
			'votingLocked' => new Column(
				name: 'votingLocked',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'maxVotes' => new Column(
				name: 'maxVotes',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'expireTime' => new Column(
				name: 'expireTime',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'hideResults' => new Column(
				name: 'hideResults',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'changeVote' => new Column(
				name: 'changeVote',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_MEMBER' => new Column(
				name: 'ID_MEMBER',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'posterName' => new Column(
				name: 'posterName',
				type: 'tinytext',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_POLL',
					],
				],
			),
		];

		parent::__construct();
	}
}
