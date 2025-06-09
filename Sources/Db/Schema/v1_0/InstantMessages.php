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

namespace SMF\Db\Schema\v1_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class InstantMessages extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'instant_messages';

		$this->columns = [
			'ID_PM' => new Column(
				name: 'ID_PM',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'ID_MEMBER_FROM' => new Column(
				name: 'ID_MEMBER_FROM',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'deletedBySender' => new Column(
				name: 'deletedBySender',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'fromName' => new Column(
				name: 'fromName',
				type: 'tinytext',
				not_null: true,
			),
			'msgtime' => new Column(
				name: 'msgtime',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'subject' => new Column(
				name: 'subject',
				type: 'tinytext',
				not_null: true,
			),
			'body' => new Column(
				name: 'body',
				type: 'text',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_PM',
					],
				],
			),
			'ID_MEMBER' => new DbIndex(
				name: 'ID_MEMBER',
				columns: [
					[
						'name' => 'ID_MEMBER_FROM',
					],
					[
						'name' => 'deletedBySender',
					],
				],
			),
			'msgtime' => new DbIndex(
				name: 'msgtime',
				columns: [
					[
						'name' => 'msgtime',
					],
				],
			),
		];

		parent::__construct();
	}
}
