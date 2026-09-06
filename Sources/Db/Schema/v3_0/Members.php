<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Members extends Table
{
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
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'member_name' => new Column(
				name: 'member_name',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'date_registered' => new Column(
				name: 'date_registered',
				type: 'bigint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'posts' => new Column(
				name: 'posts',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_group' => new Column(
				name: 'id_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'lngfile' => new Column(
				name: 'lngfile',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'last_login' => new Column(
				name: 'last_login',
				type: 'bigint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'real_name' => new Column(
				name: 'real_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'instant_messages' => new Column(
				name: 'instant_messages',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'unread_messages' => new Column(
				name: 'unread_messages',
				type: 'smallint',
				not_null: true,
				default: 0,
			),
			'new_pm' => new Column(
				name: 'new_pm',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'alerts' => new Column(
				name: 'alerts',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'buddy_list' => new Column(
				name: 'buddy_list',
				type: 'text',
				not_null: true,
			),
			'pm_ignore_list' => new Column(
				name: 'pm_ignore_list',
				type: 'text',
			),
			'pm_prefs' => new Column(
				name: 'pm_prefs',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'passwd' => new Column(
				name: 'passwd',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			'email_address' => new Column(
				name: 'email_address',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'personal_text' => new Column(
				name: 'personal_text',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'birthdate' => new Column(
				name: 'birthdate',
				type: 'date',
				not_null: true,
				default: '1004-01-01',
			),
			'website_title' => new Column(
				name: 'website_title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'website_url' => new Column(
				name: 'website_url',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'show_online' => new Column(
				name: 'show_online',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'time_format' => new Column(
				name: 'time_format',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'signature' => new Column(
				name: 'signature',
				type: 'text',
				not_null: true,
			),
			'avatar' => new Column(
				name: 'avatar',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'usertitle' => new Column(
				name: 'usertitle',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'member_ip' => new Column(
				name: 'member_ip',
				type: 'inet',
				size: 16,
				default: null,
			),
			'member_ip2' => new Column(
				name: 'member_ip2',
				type: 'inet',
				size: 16,
				default: null,
			),
			'secret_question' => new Column(
				name: 'secret_question',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'secret_answer' => new Column(
				name: 'secret_answer',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			'id_theme' => new Column(
				name: 'id_theme',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'is_activated' => new Column(
				name: 'is_activated',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'validation_code' => new Column(
				name: 'validation_code',
				type: 'varchar',
				size: 32,
				not_null: true,
				default: '',
			),
			'id_msg_last_visit' => new Column(
				name: 'id_msg_last_visit',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'additional_groups' => new Column(
				name: 'additional_groups',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'smiley_set' => new Column(
				name: 'smiley_set',
				type: 'varchar',
				size: 48,
				not_null: true,
				default: '',
			),
			'id_post_group' => new Column(
				name: 'id_post_group',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'total_time_logged_in' => new Column(
				name: 'total_time_logged_in',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'password_salt' => new Column(
				name: 'password_salt',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'ignore_boards' => new Column(
				name: 'ignore_boards',
				type: 'text',
				not_null: true,
			),
			'warning' => new Column(
				name: 'warning',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'passwd_flood' => new Column(
				name: 'passwd_flood',
				type: 'varchar',
				size: 12,
				not_null: true,
				default: '',
			),
			'pm_receive_from' => new Column(
				name: 'pm_receive_from',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 1,
			),
			'timezone' => new Column(
				name: 'timezone',
				type: 'varchar',
				size: 80,
				not_null: true,
				default: '',
			),
			'tfa_secret' => new Column(
				name: 'tfa_secret',
				type: 'varchar',
				size: 24,
				not_null: true,
				default: '',
			),
			'tfa_backup' => new Column(
				name: 'tfa_backup',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			'spoofdetector_name' => new Column(
				name: 'spoofdetector_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'email_address_ci' => new Column(
				name: 'email_address_ci',
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
					[
						'name' => 'id_member',
					],
				],
			),
			'idx_member_name' => new DbIndex(
				name: 'idx_member_name',
				columns: [
					[
						'name' => 'member_name',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			),
			'idx_real_name' => new DbIndex(
				name: 'idx_real_name',
				columns: [
					[
						'name' => 'real_name',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			),
			'idx_email_address' => new DbIndex(
				name: 'idx_email_address',
				columns: [
					[
						'name' => 'email_address',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			),
			'idx_date_registered' => new DbIndex(
				name: 'idx_date_registered',
				columns: [
					[
						'name' => 'date_registered',
					],
				],
			),
			'idx_id_group' => new DbIndex(
				name: 'idx_id_group',
				columns: [
					[
						'name' => 'id_group',
					],
				],
			),
			'idx_birthdate' => new DbIndex(
				name: 'idx_birthdate',
				columns: [
					[
						'name' => 'birthdate',
					],
				],
			),
			'idx_posts' => new DbIndex(
				name: 'idx_posts',
				columns: [
					[
						'name' => 'posts',
					],
				],
			),
			'idx_last_login' => new DbIndex(
				name: 'idx_last_login',
				columns: [
					[
						'name' => 'last_login',
					],
				],
			),
			'idx_lngfile' => new DbIndex(
				name: 'idx_lngfile',
				columns: [
					[
						'name' => 'lngfile',
						'size' => 30,
						'opclass' => 'varchar_pattern_ops',
					],
				],
			),
			'idx_id_post_group' => new DbIndex(
				name: 'idx_id_post_group',
				columns: [
					[
						'name' => 'id_post_group',
					],
				],
			),
			'idx_warning' => new DbIndex(
				name: 'idx_warning',
				columns: [
					[
						'name' => 'warning',
					],
				],
			),
			'idx_total_time_logged_in' => new DbIndex(
				name: 'idx_total_time_logged_in',
				columns: [
					[
						'name' => 'total_time_logged_in',
					],
				],
			),
			'idx_id_theme' => new DbIndex(
				name: 'idx_id_theme',
				columns: [
					[
						'name' => 'id_theme',
					],
				],
			),
			'idx_active_real_name' => new DbIndex(
				name: 'idx_active_real_name',
				columns: [
					[
						'name' => 'is_activated',
					],
					[
						'name' => 'real_name',
					],
				],
			),
			'idx_spoofdetector_name' => new DbIndex(
				name: 'idx_spoofdetector_name',
				columns: [
					[
						'name' => 'spoofdetector_name',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			),
			'idx_spoofdetector_name_id' => new DbIndex(
				name: 'idx_spoofdetector_name_id',
				columns: [
					[
						'name' => 'spoofdetector_name',
						'opclass' => 'varchar_pattern_ops',
					],
					[
						'name' => 'id_member',
					],
				],
			),
			'idx_email_address_ci' => new DbIndex(
				name: 'idx_email_address_ci',
				columns: [
					[
						'name' => 'email_address_ci',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			),
		];

		if (Db::$db->title === POSTGRE_TITLE) {
			$this->indexes['idx_birthdate2'] = new DbIndex(
				name: 'idx_birthdate2',
				columns: [
					[
						'name' => 'indexable_month_day(birthdate)',
					],
				],
			);
			$this->indexes['idx_member_name_low'] = new DbIndex(
				name: 'idx_member_name_low',
				columns: [
					[
						'name' => 'LOWER(member_name)',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			);
			$this->indexes['idx_real_name_low'] = new DbIndex(
				name: 'idx_real_name_low',
				columns: [
					[
						'name' => 'LOWER(real_name)',
						'opclass' => 'varchar_pattern_ops',
					],
				],
			);
		}
	}
}
