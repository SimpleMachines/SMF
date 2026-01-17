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
use SMF\Db\Schema\GeneratedColumn;
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
				type: 'inet',
				size: 16,
			),
			'smileys_enabled' => new Column(
				name: 'smileys_enabled',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'edit_history' => new Column(
				name: 'edit_history',
				// Type will automatically be changed to jsonb for PostrgreSQL.
				type: 'json',
				default: null,
			),
			'modified_time' => new GeneratedColumn(
				name: 'modified_time',
				type: 'int',
				unsigned: true,
				generation_expression: Db::$db->title === POSTGRE_TITLE ? 'COALESCE((edit_history #>> \'{0,0}\'::text[])::integer, 0)' : 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(edit_history, "$[0][0]")), 0)',
				stored: true,
			),
			'modified_name' => new GeneratedColumn(
				name: 'modified_name',
				type: 'varchar',
				size: 255,
				generation_expression: Db::$db->title === POSTGRE_TITLE ? 'COALESCE(edit_history #>> \'{0,6}\'::text[], \'\'::text)' : 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(edit_history, "$[0][6]")), "")',
				stored: true,
			),
			'modified_reason' => new GeneratedColumn(
				name: 'modified_reason',
				type: 'varchar',
				size: 255,
				generation_expression: Db::$db->title === POSTGRE_TITLE ? 'COALESCE(edit_history #>> \'{0,7}\'::text[], \'\'::text)' : 'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(edit_history, "$[0][7]")), "")',
				stored: true,
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
			'reactions' => new Column(
				name: 'reactions',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'version' => new Column(
				name: 'version',
				type: 'varchar',
				size: 5,
				not_null: true,
				default: '',
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
			'idx_id_board' => new DbIndex(
				name: 'idx_id_board',
				type: 'unique',
				columns: [
					[
						'name' => 'id_board',
					],
					[
						'name' => 'id_msg',
					],
					[
						'name' => 'approved',
					],
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				type: 'unique',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'id_msg',
					],
				],
			),
			'idx_ip_index' => new DbIndex(
				name: 'idx_ip_index',
				columns: [
					[
						'name' => 'poster_ip',
					],
					[
						'name' => 'id_topic',
					],
				],
			),
			'idx_participation' => new DbIndex(
				name: 'idx_participation',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'id_topic',
					],
				],
			),
			'idx_show_posts' => new DbIndex(
				name: 'idx_show_posts',
				columns: [
					[
						'name' => 'id_member',
					],
					[
						'name' => 'id_board',
					],
				],
			),
			'idx_id_member_msg' => new DbIndex(
				name: 'idx_id_member_msg',
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
			'idx_current_topic' => new DbIndex(
				name: 'idx_current_topic',
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
			'idx_related_ip' => new DbIndex(
				name: 'idx_related_ip',
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
			'idx_likes' => new DbIndex(
				name: 'idx_likes',
				columns: [
					[
						'name' => 'likes',
					],
				],
			),
		];

		parent::__construct();
	}
}
