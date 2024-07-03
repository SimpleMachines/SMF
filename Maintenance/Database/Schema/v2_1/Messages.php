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
class Messages extends Table
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
			'id_msg' => 1,
			'id_msg_modified' => 1,
			'id_topic' => 1,
			'id_board' => 1,
			'poster_time' => '{$current_time}',
			'subject' => '{$default_topic_subject}',
			'poster_name' => 'Simple Machines',
			'poster_email' => 'info@simplemachines.org',
			'modified_name' => '',
			'body' => '{$default_topic_message}',
			'icon' => 'xx',
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
		$this->name = 'messages';

		$this->columns = [
			new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				auto: true,
			),
			new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'poster_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'id_msg_modified',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'poster_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'poster_email',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'poster_ip',
				type: 'inet',
				size: 16,
			),
			new Column(
				name: 'smileys_enabled',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'modified_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			new Column(
				name: 'modified_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'modified_reason',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			new Column(
				name: 'body',
				type: 'text',
				not_null: true,
			),
			new Column(
				name: 'icon',
				type: 'varchar',
				size: 16,
				not_null: true,
				default: 'xx',
			),
			new Column(
				name: 'approved',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			new Column(
				name: 'likes',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			new DbIndex(
				type: 'primary',
				columns: [
					'id_msg',
				],
			),
			new DbIndex(
				name: 'idx_id_board',
				type: 'unique',
				columns: [
					'id_board',
					'id_msg',
					'approved',
				],
			),
			new DbIndex(
				name: 'idx_id_member',
				type: 'unique',
				columns: [
					'id_member',
					'id_msg',
				],
			),
			new DbIndex(
				name: 'idx_ip_index',
				columns: [
					'poster_ip',
					'id_topic',
				],
			),
			new DbIndex(
				name: 'idx_participation',
				columns: [
					'id_member',
					'id_topic',
				],
			),
			new DbIndex(
				name: 'idx_show_posts',
				columns: [
					'id_member',
					'id_board',
				],
			),
			new DbIndex(
				name: 'idx_id_member_msg',
				columns: [
					'id_member',
					'approved',
					'id_msg',
				],
			),
			new DbIndex(
				name: 'idx_current_topic',
				columns: [
					'id_topic',
					'id_msg',
					'id_member',
					'approved',
				],
			),
			new DbIndex(
				name: 'idx_related_ip',
				columns: [
					'id_member',
					'poster_ip',
					'id_msg',
				],
			),
			new DbIndex(
				name: 'idx_likes',
				columns: [
					'likes',
				],
			),
		];
	}
}

?>