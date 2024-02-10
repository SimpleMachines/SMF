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
use SMF\Db\Schema\Indices;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Members extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Initial columns for inserts.
	 */
	public array $initial_columns = [];

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
		$this->name = 'members';

		$this->columns = [
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'member_name',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'date_registered',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'posts',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'lngfile',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'last_login',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'real_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'instant_messages',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'unread_messages',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'new_pm',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'alerts',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'buddy_list',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'pm_ignore_list',
				type: 'text',
			),
			new Column(
				name: 'pm_prefs',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'mod_prefs',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'passwd',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'email_address',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'personal_text',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'birthdate',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			new Column(
				name: 'website_title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'website_url',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'show_online',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'time_format',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'signature',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'time_offset',
				type: 'float',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'avatar',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'usertitle',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'member_ip',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'member_ip2',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'secret_question',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'secret_answer',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_theme',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'is_activated',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'validation_code',
				type: 'varchar',
				size: 10,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_msg_last_visit',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'additional_groups',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'smiley_set',
				type: 'varchar',
				size: 48,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'id_post_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'total_time_logged_in',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'password_salt',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'ignore_boards',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'warning',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'passwd_flood',
				type: 'varchar',
				size: 12,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'pm_receive_from',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'timezone',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'tfa_secret',
				type: 'varchar',
				size: 24,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'tfa_backup',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'id_member',
				],
			),
			new Indices(
				name: 'idx_member_name',
				columns: [
					'member_name',
				],
			),
			new Indices(
				name: 'idx_real_name',
				columns: [
					'real_name',
				],
			),
			new Indices(
				name: 'idx_email_address',
				columns: [
					'email_address',
				],
			),
			new Indices(
				name: 'idx_date_registered',
				columns: [
					'date_registered',
				],
			),
			new Indices(
				name: 'idx_id_group',
				columns: [
					'id_group',
				],
			),
			new Indices(
				name: 'idx_birthdate',
				columns: [
					'birthdate',
				],
			),
			new Indices(
				name: 'idx_posts',
				columns: [
					'posts',
				],
			),
			new Indices(
				name: 'idx_last_login',
				columns: [
					'last_login',
				],
			),
			new Indices(
				name: 'idx_lngfile',
				columns: [
					'lngfile(30)',
				],
			),
			new Indices(
				name: 'idx_id_post_group',
				columns: [
					'id_post_group',
				],
			),
			new Indices(
				name: 'idx_warning',
				columns: [
					'warning',
				],
			),
			new Indices(
				name: 'idx_total_time_logged_in',
				columns: [
					'total_time_logged_in',
				],
			),
			new Indices(
				name: 'idx_id_theme',
				columns: [
					'id_theme',
				],
			),
			new Indices(
				name: 'idx_active_real_name',
				columns: [
					'is_activated',
					'real_name',
				],
			),
		];
	}
}

?>