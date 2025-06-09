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

namespace SMF\Db\Schema\v1_0;

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
			'ID_MSG' => 1,
			'ID_TOPIC' => 1,
			'ID_BOARD' => 1,
			'posterTime' => '{$current_time}',
			'subject' => '{$default_topic_subject}',
			'posterName' => 'Simple Machines',
			'posterEmail' => 'info@simplemachines.org',
			'posterIP' => '127.0.0.1',
			'body' => '{$default_topic_message}',
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
			'ID_MSG' => new Column(
				name: 'ID_MSG',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'ID_TOPIC' => new Column(
				name: 'ID_TOPIC',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'ID_BOARD' => new Column(
				name: 'ID_BOARD',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'posterTime' => new Column(
				name: 'posterTime',
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
			'subject' => new Column(
				name: 'subject',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'posterName' => new Column(
				name: 'posterName',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'posterEmail' => new Column(
				name: 'posterEmail',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'posterIP' => new Column(
				name: 'posterIP',
				type: 'tinytext',
				not_null: true,
				default: '',
			),
			'smileysEnabled' => new Column(
				name: 'smileysEnabled',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
			'modifiedTime' => new Column(
				name: 'modifiedTime',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'modifiedName' => new Column(
				name: 'modifiedName',
				type: 'tinytext',
			),
			'body' => new Column(
				name: 'body',
				type: 'text',
			),
			'icon' => new Column(
				name: 'icon',
				type: 'varchar',
				size: 16,
				not_null: true,
				default: 'xx',
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'ID_MSG',
					],
				],
			),
			'topic' => new DbIndex(
				type: 'unique',
				name: 'topic',
				columns: [
					[
						'name' => 'ID_TOPIC',
					],
					[
						'name' => 'ID_MSG',
					],
				],
			),
			'ID_TOPIC' => new DbIndex(
				name: 'ID_TOPIC',
				columns: [
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
			'ID_BOARD' => new DbIndex(
				name: 'ID_BOARD',
				columns: [
					[
						'name' => 'ID_BOARD',
					],
				],
			),
			'ID_MEMBER' => new DbIndex(
				name: 'ID_MEMBER',
				columns: [
					[
						'name' => 'ID_MEMBER',
					],
				],
			),
			'posterTime' => new DbIndex(
				name: 'posterTime',
				columns: [
					[
						'name' => 'posterTime',
					],
				],
			),
			'ipIndex' => new DbIndex(
				name: 'ipIndex',
				columns: [
					[
						'name' => 'posterIP',
						'size' => 15,
					],
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
			'participation' => new DbIndex(
				name: 'participation',
				columns: [
					[
						'name' => 'ID_MEMBER',
					],
					[
						'name' => 'ID_TOPIC',
					],
				],
			),
		];

		parent::__construct();
	}
}
