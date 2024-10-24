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
			'id_group' => -1,
			'id_profile' => 1,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'remove_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'poll_add_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'poll_edit_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'poll_lock_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_new',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'report_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 1,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_new',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'report_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'move_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'split_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 2,
			'id_profile' => 1,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_new',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'report_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'move_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'split_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 3,
			'id_profile' => 1,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => -1,
			'id_profile' => 2,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'remove_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_new',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'report_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 2,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_new',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'report_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'move_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'split_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 2,
			'id_profile' => 2,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_new',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'report_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'move_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'split_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 3,
			'id_profile' => 2,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => -1,
			'id_profile' => 3,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'remove_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'report_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 3,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_new',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'report_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'move_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'split_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 2,
			'id_profile' => 3,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_new',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'report_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'move_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'split_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 3,
			'id_profile' => 3,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => -1,
			'id_profile' => 4,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 4,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 0,
			'id_profile' => 4,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 0,
			'id_profile' => 4,
			'permission' => 'report_any',
		],
		[
			'id_group' => 0,
			'id_profile' => 4,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_new',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'report_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'move_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'split_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 2,
			'id_profile' => 4,
			'permission' => 'view_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'moderate_board',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_new',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_draft',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_reply_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_reply_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_unapproved_topics',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_unapproved_replies_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_unapproved_replies_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_unapproved_attachments',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'poll_post',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'poll_add_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'poll_remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'poll_view',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'poll_vote',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'poll_lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'poll_edit_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'report_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'lock_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'delete_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'modify_own',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'make_sticky',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'lock_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'remove_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'move_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'merge_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'split_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'delete_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'modify_any',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'approve_posts',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'post_attachment',
		],
		[
			'id_group' => 3,
			'id_profile' => 4,
			'permission' => 'view_attachments',
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
			'id_group' => new Column(
				name: 'id_group',
				type: 'smallint',
				default: 0,
			),
			'id_profile' => new Column(
				name: 'id_profile',
				type: 'smallint',
				unsigned: true,
				default: 0,
			),
			'permission' => new Column(
				name: 'permission',
				type: 'varchar',
				size: 30,
				default: '',
			),
			'add_deny' => new Column(
				name: 'add_deny',
				type: 'tinyint',
				not_null: true,
				default: 1,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'id_group',
					'id_profile',
					'permission',
				],
			),
		];
	}
}

?>