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

namespace SMF\Maintenance\Database\Schema\v2_1;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

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
			'id_field' => new Column(
				name: 'id_field',
				type: 'smallint',
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
			'field_order' => new Column(
				name: 'field_order',
				type: 'smallint',
				not_null: true,
				default: 0,
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
			'show_mlist' => new Column(
				name: 'show_mlist',
				type: 'smallint',
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
					'id_field',
				],
			),
			'idx_col_name' => new DbIndex(
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