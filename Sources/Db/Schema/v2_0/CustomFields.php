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

namespace SMF\Db\Schema\v2_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class CustomFields extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'custom_fields';

		$this->columns = [
			'id_field' => new Column(
				name: 'id_field',
				type: 'smallint',
				not_null: true,
				auto: true,
			),
			'col_name' => new Column(
				name: 'col_name',
				type: 'varchar',
				size: 12,
				not_null: true,
				default: '',
			),
			'field_name' => new Column(
				name: 'field_name',
				type: 'varchar',
				size: 40,
				not_null: true,
				default: '',
			),
			'field_desc' => new Column(
				name: 'field_desc',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'field_type' => new Column(
				name: 'field_type',
				type: 'varchar',
				size: 8,
				not_null: true,
				default: 'text',
			),
			'field_length' => new Column(
				name: 'field_length',
				type: 'smallint',
				not_null: true,
				default: 255,
			),
			'field_options' => new Column(
				name: 'field_options',
				type: 'text',
				not_null: true,
			),
			'mask' => new Column(
				name: 'mask',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'show_reg' => new Column(
				name: 'show_reg',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'show_display' => new Column(
				name: 'show_display',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'show_profile' => new Column(
				name: 'show_profile',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: 'forumprofile',
			),
			'private' => new Column(
				name: 'private',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'active' => new Column(
				name: 'active',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'bbc' => new Column(
				name: 'bbc',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'can_search' => new Column(
				name: 'can_search',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'default_value' => new Column(
				name: 'default_value',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'enclose' => new Column(
				name: 'enclose',
				type: 'text',
				not_null: true,
			),
			'placement' => new Column(
				name: 'placement',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_field',
					],
				],
			),
			'col_name' => new DbIndex(
				type: 'unique',
				name: 'col_name',
				columns: [
					[
						'name' => 'col_name',
					],
				],
			),
		];

		parent::__construct();
	}
}
