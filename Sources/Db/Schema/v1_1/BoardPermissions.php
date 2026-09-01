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

namespace SMF\Db\Schema\v1_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class BoardPermissions extends Table
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
			'ID_GROUP' => -1,
			'ID_BOARD' => 0,
			'permission' => 'poll_view',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'remove_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'lock_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'mark_any_notify',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'mark_notify',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'modify_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'poll_add_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'poll_edit_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'poll_lock_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'poll_post',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'poll_view',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'poll_vote',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'post_attachment',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'post_new',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'post_reply_any',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'post_reply_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'delete_own',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'report_any',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'send_topic',
		],
		[
			'ID_GROUP' => 0,
			'ID_BOARD' => 0,
			'permission' => 'view_attachments',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'moderate_board',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'post_new',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'post_reply_own',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'post_reply_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'poll_post',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'poll_add_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'poll_remove_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'poll_view',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'poll_vote',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'poll_edit_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'report_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'lock_own',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'send_topic',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'mark_any_notify',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'mark_notify',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'delete_own',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'modify_own',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'make_sticky',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'lock_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'remove_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'move_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'merge_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'split_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'delete_any',
		],
		[
			'ID_GROUP' => 2,
			'ID_BOARD' => 0,
			'permission' => 'modify_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'moderate_board',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'post_new',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'post_reply_own',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'post_reply_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'poll_post',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'poll_add_own',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'poll_remove_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'poll_view',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'poll_vote',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'report_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'lock_own',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'send_topic',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'mark_any_notify',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'mark_notify',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'delete_own',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'modify_own',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'make_sticky',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'lock_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'remove_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'move_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'merge_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'split_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'delete_any',
		],
		[
			'ID_GROUP' => 3,
			'ID_BOARD' => 0,
			'permission' => 'modify_any',
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
		$this->name = 'board_permissions';

		$this->columns = [
			'ID_GROUP' => new Column(
				name: 'ID_GROUP',
				type: 'smallint',
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
			'permission' => new Column(
				name: 'permission',
				type: 'varchar',
				size: 30,
				not_null: true,
				default: '',
			),
			'addDeny' => new Column(
				name: 'addDeny',
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
						'name' => 'ID_GROUP',
					],
					[
						'name' => 'ID_BOARD',
					],
					[
						'name' => 'permission',
					],
				],
			),
		];

		parent::__construct();
	}
}
