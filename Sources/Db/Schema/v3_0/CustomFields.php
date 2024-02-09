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
use SMF\Db\Schema\Index;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class CustomFields extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [
		'col_name' => 'string',
		'field_name' => 'string',
		'field_desc' => 'string',
		'field_type' => 'string',
		'field_length' => 'int',
		'field_options' => 'string',
		'field_order' => 'int',
		'mask' => 'string',
		'show_reg' => 'int',
		'show_display' => 'int',
		'show_mlist' => 'int',
		'show_profile' => 'string',
		'private' => 'int',
		'active' =>  'int',
		'bbc' => 'int',
		'can_search' => 'int',
		'default_value' => 'string',
		'enclose' => ' string',
		'placement' => 'int',
	];

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [
		[
			'col_name' => 'cust_icq',
			'field_name' => '{icq}',
			'field_desc' => '{icq_desc}',
			'field_type' => 'text',
			'field_length' => 12,
			'field_options' => '',
			'field_order' => 1,
			'mask' => 'regex~[1-9][0-9]{4,9}~i',
			'show_reg' => 0,
			'show_display' => 1,
			'show_mlist' => 0,
			'show_profile' => 'forumprofile',
			'private' => 0,
			'active' => 1,
			'bbc' => 0,
			'can_search' => 0,
			'default_value' => '',
			'enclose' => '<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" rel="noopener" title="ICQ - {INPUT}"><img src="{DEFAULT_IMAGES_URL}/icq.png" alt="ICQ - {INPUT}"></a>',
			'placement' => 1,
		],
		[
			'col_name' => 'cust_skype',
			'field_name' => '{skype}',
			'field_desc' => '{skype_desc}',
			'field_type' => 'text',
			'field_length' => 32,
			'field_options' => '',
			'field_order' => 2,
			'mask' => 'nohtml',
			'show_reg' => 0,
			'show_display' => 1,
			'show_mlist' => 0,
			'show_profile' => 'forumprofile',
			'private' => 0,
			'active' => 1,
			'bbc' => 0,
			'can_search' => 0,
			'default_value' => '',
			'enclose' => '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ',
			'placement' => 1,
		],
		[
			'col_name' => 'cust_loca',
			'field_name' => '{location}',
			'field_desc' => '{location_desc}',
			'field_type' => 'text',
			'field_length' => 50,
			'field_options' => '',
			'field_order' => 4,
			'mask' => 'nohtml',
			'show_reg' => 0,
			'show_display' => 1,
			'show_mlist' => 0,
			'show_profile' => 'forumprofile',
			'private' => 0,
			'active' => 1,
			'bbc' => 0,
			'can_search' => 0,
			'default_value' => '',
			'enclose' => '',
			'placement' => 0,
		],
		[
			'col_name' => 'cust_gender',
			'field_name' => '{gender}',
			'field_desc' => '{gender_desc}',
			'field_type' => 'radio',
			'field_length' => 255,
			'field_options' => '{gender_0},{gender_1},{gender_2}',
			'field_order' => 5,
			'mask' => 'nohtml',
			'show_reg' => 1,
			'show_display' => 1,
			'show_mlist' => 0,
			'show_profile' => 'forumprofile',
			'private' => 0,
			'active' => 1,
			'bbc' => 0,
			'can_search' => 0,
			'default_value' => '{gender_0}',
			'enclose' => '<span class=" main_icons gender_{KEY}" title="{INPUT}"></span>',
			'placement' => 1,
		],
	];

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
			new Column(
				name: 'id_field',
				type: 'smallint',
				auto: true,
			),
			new Column(
				name: 'col_name',
				type: 'varchar',
				size: 12,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'field_name',
				type: 'varchar',
				size: 40,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'field_desc',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'field_type',
				type: 'varchar',
				size: 8,
				not_null: true,
				default: 'text',
			),
			new Column(
				name: 'field_length',
				type: 'smallint',
				not_null: true,
				default: 255,
			),
			new Column(
				name: 'field_options',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'field_order',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'mask',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'show_reg',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'show_display',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'show_mlist',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'show_profile',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: 'forumprofile',
			),
			new Column(
				name: 'private',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'active',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'bbc',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'can_search',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'default_value',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'enclose',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'placement',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
		];

		$this->indices = [
			new Index(
				type: 'primary',
				columns: [
					'id_field',
				],
			),
			new Index(
				name: 'idx_col_name',
				type: 'unique',
				columns: [
					'col_name',
				],
			),
		];
	}
}

?>