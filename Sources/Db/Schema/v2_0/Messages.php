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
			'poster_ip' => '127.0.0.1',
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
			'id_msg' => new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_topic' => new Column(
				name: 'id_topic',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_board' => new Column(
				name: 'id_board',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'poster_time' => new Column(
				name: 'poster_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_msg_modified' => new Column(
				name: 'id_msg_modified',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'subject' => new Column(
				name: 'subject',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'poster_name' => new Column(
				name: 'poster_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'poster_email' => new Column(
				name: 'poster_email',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'poster_ip' => new Column(
				name: 'poster_ip',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'smileys_enabled' => new Column(
				name: 'smileys_enabled',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'modified_time' => new Column(
				name: 'modified_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'modified_name' => new Column(
				name: 'modified_name',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'body' => new Column(
				name: 'body',
				type: 'text',
				not_null: true,
			),
			'icon' => new Column(
				name: 'icon',
				type: 'varchar',
				size: 16,
				not_null: true,
				default: 'xx',
			),
			'approved' => new Column(
				name: 'approved',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_msg',
					],
				],
			),
			'topic' => new DbIndex(
				type: 'unique',
				name: 'topic',
				columns: [
					[
						'name' => 'id_topic',
					],
					[
						'name' => 'id_msg',
					],
				],
			),
			'id_board' => new DbIndex(
				type: 'unique',
				name: 'id_board',
				columns: [
					[
						'name' => 'id_board',
					],
					[
						'name' => 'id_msg',
					],
				],
			),
			'id_member' => new DbIndex(
				type: 'unique',
				name: 'id_member',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'id_msg',
					],
				],
			),
			'approved' => new DbIndex(
				name: 'approved',
				columns: [
					[
						'name' => 'approved',
					],
				],
			),
			'ip_index' => new DbIndex(
				name: 'ip_index',
				columns: [
					[
						'name' => 'poster_ip',
						'size' => 15,
					],
					[
						'name' => 'id_topic',
					],
				],
			),
			'participation' => new DbIndex(
				name: 'participation',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'id_topic',
					],
				],
			),
			'show_posts' => new DbIndex(
				name: 'show_posts',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'id_board',
					],
				],
			),
			'id_topic' => new DbIndex(
				name: 'id_topic',
				columns: [
					[
						'name' => 'id_topic',
					],
				],
			),
			'id_member_msg' => new DbIndex(
				name: 'id_member_msg',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'approved',
					],
					[
						'name' => 'id_msg',
					],
				],
			),
			'current_topic' => new DbIndex(
				name: 'current_topic',
				columns: [
					[
						'name' => 'id_topic',
					],
					[
						'name' => 'id_msg',
					],
					[
						'name' => 'id_member',
					],
					[
						'name' => 'approved',
					],
				],
			),
			'related_ip' => new DbIndex(
				name: 'related_ip',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'poster_ip',
					],
					[
						'name' => 'id_msg',
					],
				],
			),
		];

		parent::__construct();
	}
}
