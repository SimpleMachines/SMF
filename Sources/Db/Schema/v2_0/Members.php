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
				type: 'int',
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
				type: 'int',
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
			'buddy_list' => new Column(
				name: 'buddy_list',
				type: 'text',
				not_null: true,
			),
			'pm_ignore_list' => new Column(
				name: 'pm_ignore_list',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'pm_prefs' => new Column(
				name: 'pm_prefs',
				type: 'mediumint',
				not_null: true,
				default: 0,
			),
			'mod_prefs' => new Column(
				name: 'mod_prefs',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			'message_labels' => new Column(
				name: 'message_labels',
				type: 'text',
				not_null: true,
			),
			'passwd' => new Column(
				name: 'passwd',
				type: 'varchar',
				size: 64,
				not_null: true,
				default: '',
			),
			'openid_uri' => new Column(
				name: 'openid_uri',
				type: 'text',
				not_null: true,
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
			'gender' => new Column(
				name: 'gender',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'birthdate' => new Column(
				name: 'birthdate',
				type: 'date',
				not_null: true,
				default: '0001-01-01',
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
			'location' => new Column(
				name: 'location',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'icq' => new Column(
				name: 'icq',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'aim' => new Column(
				name: 'aim',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'yim' => new Column(
				name: 'yim',
				type: 'varchar',
				size: 32,
				not_null: true,
				default: '',
			),
			'msn' => new Column(
				name: 'msn',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'hide_email' => new Column(
				name: 'hide_email',
				type: 'tinyint',
				not_null: true,
				default: 0,
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
			'time_offset' => new Column(
				name: 'time_offset',
				type: 'float',
				not_null: true,
				default: 0,
			),
			'avatar' => new Column(
				name: 'avatar',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'pm_email_notify' => new Column(
				name: 'pm_email_notify',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'karma_bad' => new Column(
				name: 'karma_bad',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'karma_good' => new Column(
				name: 'karma_good',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'usertitle' => new Column(
				name: 'usertitle',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'notify_announcements' => new Column(
				name: 'notify_announcements',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'notify_regularity' => new Column(
				name: 'notify_regularity',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'notify_send_body' => new Column(
				name: 'notify_send_body',
				type: 'tinyint',
				not_null: true,
				default: 0,
			),
			'notify_types' => new Column(
				name: 'notify_types',
				type: 'tinyint',
				not_null: true,
				default: 2,
			),
			'member_ip' => new Column(
				name: 'member_ip',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'member_ip2' => new Column(
				name: 'member_ip2',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
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
				size: 10,
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
			'member_name' => new DbIndex(
				name: 'member_name',
				columns: [
					[
						'name' => 'member_name',
					],
				],
			),
			'real_name' => new DbIndex(
				name: 'real_name',
				columns: [
					[
						'name' => 'real_name',
					],
				],
			),
			'date_registered' => new DbIndex(
				name: 'date_registered',
				columns: [
					[
						'name' => 'date_registered',
					],
				],
			),
			'id_group' => new DbIndex(
				name: 'id_group',
				columns: [
					[
						'name' => 'id_group',
					],
				],
			),
			'birthdate' => new DbIndex(
				name: 'birthdate',
				columns: [
					[
						'name' => 'birthdate',
					],
				],
			),
			'posts' => new DbIndex(
				name: 'posts',
				columns: [
					[
						'name' => 'posts',
					],
				],
			),
			'last_login' => new DbIndex(
				name: 'last_login',
				columns: [
					[
						'name' => 'last_login',
					],
				],
			),
			'lngfile' => new DbIndex(
				name: 'lngfile',
				columns: [
					[
						'name' => 'lngfile',
						'size' => 30,
					],
				],
			),
			'id_post_group' => new DbIndex(
				name: 'id_post_group',
				columns: [
					[
						'name' => 'id_post_group',
					],
				],
			),
			'warning' => new DbIndex(
				name: 'warning',
				columns: [
					[
						'name' => 'warning',
					],
				],
			),
			'total_time_logged_in' => new DbIndex(
				name: 'total_time_logged_in',
				columns: [
					[
						'name' => 'total_time_logged_in',
					],
				],
			),
			'id_theme' => new DbIndex(
				name: 'id_theme',
				columns: [
					[
						'name' => 'id_theme',
					],
				],
			),
		];

		parent::__construct();
	}
}
