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
class ImRecipients extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'im_recipients';

		$this->columns = [
			'ID_PM' => new Column(
				name: 'ID_PM',
				type: 'int',
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
			'bcc' => new Column(
				name: 'bcc',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'is_read' => new Column(
				name: 'is_read',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'deleted' => new Column(
				name: 'deleted',
				type: 'tinyint',
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
						'name' => 'ID_PM',
					],
					[
						'name' => 'ID_MEMBER',
					],
				],
			),
			'ID_MEMBER' => new DbIndex(
				name: 'ID_MEMBER',
				columns: [
					[
						'name' => 'ID_MEMBER',
					],
					[
						'name' => 'deleted',
					],
				],
			),
		];

		parent::__construct();
	}
}
