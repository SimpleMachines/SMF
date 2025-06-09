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
class Attachments extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'attachments';

		$this->columns = [
			'ID_ATTACH' => new Column(
				name: 'ID_ATTACH',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'ID_THUMB' => new Column(
				name: 'ID_THUMB',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_MSG' => new Column(
				name: 'ID_MSG',
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
			'attachmentType' => new Column(
				name: 'attachmentType',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'filename' => new Column(
				name: 'filename',
				type: 'tinytext',
				not_null: true,
			),
			'file_hash' => new Column(
				name: 'file_hash',
				type: 'varchar',
				size: 40,
				not_null: true,
				default: '',
			),
			'size' => new Column(
				name: 'size',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'downloads' => new Column(
				name: 'downloads',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'width' => new Column(
				name: 'width',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'height' => new Column(
				name: 'height',
				type: 'mediumint',
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
						'name' => 'ID_ATTACH',
					],
				],
			),
			'ID_MEMBER' => new DbIndex(
				type: 'unique',
				name: 'ID_MEMBER',
				columns: [
					[
						'name' => 'ID_MEMBER',
					],
					[
						'name' => 'ID_ATTACH',
					],
				],
			),
			'ID_MSG' => new DbIndex(
				name: 'ID_MSG',
				columns: [
					[
						'name' => 'ID_MSG',
					],
				],
			),
		];

		parent::__construct();
	}
}
