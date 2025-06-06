<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class RenameColumns extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Changing column names';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private array $name_changes = [
		Schema\v2_0\AdminInfoFiles::class => [
			'ID_FILE' => 'id_file',
		],
		Schema\v2_0\ApprovalQueue::class => [
			'ID_MSG' => 'id_msg',
			'ID_ATTACH' => 'id_attach',
			'ID_EVENT' => 'id_event',
			'attachmentType' => 'attachment_type',
		],
		Schema\v2_0\Attachments::class => [
			'ID_ATTACH' => 'id_attach',
			'ID_THUMB' => 'id_thumb',
			'ID_MSG' => 'id_msg',
			'ID_MEMBER' => 'id_member',
			'attachmentType' => 'attachment_type',
		],
		Schema\v2_0\BanGroups::class => [
			'ID_BAN_GROUP' => 'id_ban_group',
		],
		Schema\v2_0\BanItems::class => [
			'ID_BAN' => 'id_ban',
			'ID_BAN_GROUP' => 'id_ban_group',
			'ID_MEMBER' => 'id_member',
		],
		Schema\v2_0\BoardPermissions::class => [
			'ID_GROUP' => 'id_group',
			'ID_PROFILE' => 'id_profile',
			'addDeny' => 'add_deny',
		],
		Schema\v2_0\Boards::class => [
			'ID_BOARD' => 'id_board',
			'ID_CAT' => 'id_cat',
			'childLevel' => 'child_level',
			'ID_PARENT' => 'id_parent',
			'boardOrder' => 'board_order',
			'ID_LAST_MSG' => 'id_last_msg',
			'ID_MSG_UPDATED' => 'id_msg_updated',
			'memberGroups' => 'member_groups',
			'ID_PROFILE' => 'id_profile',
			'numTopics' => 'num_topics',
			'numPosts' => 'num_posts',
			'countPosts' => 'count_posts',
			'ID_THEME' => 'id_theme',
			'unapprovedPosts' => 'unapproved_posts',
			'unapprovedTopics' => 'unapproved_topics',
		],
		Schema\v2_0\Calendar::class => [
			'ID_EVENT' => 'id_event',
			'ID_MEMBER' => 'id_member',
			'ID_BOARD' => 'id_board',
			'ID_TOPIC' => 'id_topic',
			'startDate' => 'start_date',
			'endDate' => 'end_date',
		],
		Schema\v2_0\CalendarHolidays::class => [
			'ID_HOLIDAY' => 'id_holiday',
			'eventDate' => 'event_date',
		],
		Schema\v2_0\Categories::class => [
			'ID_CAT' => 'id_cat',
			'catOrder' => 'cat_order',
			'canCollapse' => 'can_collapse',
		],
		Schema\v2_0\CustomFields::class => [
			'ID_FIELD' => 'id_field',
			'colName' => 'col_name',
			'fieldName' => 'field_name',
			'fieldDesc' => 'field_desc',
			'fieldType' => 'field_type',
			'fieldLength' => 'field_length',
			'fieldOptions' => 'field_options',
			'showReg' => 'show_reg',
			'showDisplay' => 'show_display',
			'showProfile' => 'show_profile',
			'defaultValue' => 'default_value',
		],
		Schema\v2_0\GroupModerators::class => [
			'ID_MEMBER' => 'id_member',
			'ID_GROUP' => 'id_group',
		],
		Schema\v2_0\LogActions::class => [
			'ID_ACTION' => 'id_action',
			'ID_MEMBER' => 'id_member',
			'logTime' => 'log_time',
			'ID_MSG' => 'id_msg',
			'ID_TOPIC' => 'id_topic',
			'ID_BOARD' => 'id_board',
		],
		Schema\v2_0\LogActivity::class => [
			'mostOn' => 'most_on',
		],
		Schema\v2_0\LogBanned::class => [
			'ID_BAN_LOG' => 'id_ban_log',
			'ID_MEMBER' => 'id_member',
			'logTime' => 'log_time',
		],
		Schema\v2_0\LogBoards::class => [
			'ID_MEMBER' => 'id_member',
			'ID_MSG' => 'id_msg',
			'ID_BOARD' => 'id_board',
		],
		Schema\v2_0\LogDigest::class => [
			'ID_TOPIC' => 'id_topic',
			'ID_MSG' => 'id_msg',
		],
		Schema\v2_0\LogErrors::class => [
			'ID_ERROR' => 'id_error',
			'logTime' => 'log_time',
			'ID_MEMBER' => 'id_member',
			'errorType' => 'error_type',
		],
		Schema\v2_0\LogFloodcontrol::class => [
			'logTime' => 'log_time',
		],
		Schema\v2_0\LogGroupRequests::class => [
			'ID_REQUEST' => 'id_request',
			'ID_MEMBER' => 'id_member',
			'ID_GROUP' => 'id_group',
		],
		Schema\v2_0\LogKarma::class => [
			'ID_TARGET' => 'id_target',
			'ID_EXECUTOR' => 'id_executor',
			'logTime' => 'log_time',
		],
		Schema\v2_0\LogMarkRead::class => [
			'ID_MEMBER' => 'id_member',
			'ID_MSG' => 'id_msg',
			'ID_BOARD' => 'id_board',
		],
		Schema\v2_0\LogNotify::class => [
			'ID_MEMBER' => 'id_member',
			'ID_TOPIC' => 'id_topic',
			'ID_BOARD' => 'id_board',
		],
		Schema\v2_0\LogPackages::class => [
			'ID_INSTALL' => 'id_install',
			'ID_MEMBER_INSTALLED' => 'id_member_installed',
			'ID_MEMBER_REMOVED' => 'id_member_removed',
		],
		Schema\v2_0\LogPolls::class => [
			'ID_MEMBER' => 'id_member',
			'ID_CHOICE' => 'id_choice',
			'ID_POLL' => 'id_poll',
		],
		Schema\v2_0\LogReported::class => [
			'ID_REPORT' => 'id_report',
			'ID_MEMBER' => 'id_member',
			'ID_MSG' => 'id_msg',
			'ID_TOPIC' => 'id_topic',
			'ID_BOARD' => 'id_board',
		],
		Schema\v2_0\LogReportedComments::class => [
			'ID_COMMENT' => 'id_comment',
			'ID_REPORT' => 'id_report',
			'ID_MEMBER' => 'id_member',
		],
		Schema\v2_0\LogScheduledTasks::class => [
			'ID_LOG' => 'id_log',
			'ID_TASK' => 'id_task',
			'timeRun' => 'time_run',
			'timeTaken' => 'time_taken',
		],
		Schema\v2_0\LogSearchMessages::class => [
			'ID_SEARCH' => 'id_search',
			'ID_MSG' => 'id_msg',
		],
		Schema\v2_0\LogSearchResults::class => [
			'ID_TOPIC' => 'id_topic',
			'ID_MSG' => 'id_msg',
			'ID_SEARCH' => 'id_search',
		],
		Schema\v2_0\LogSearchSubjects::class => [
			'ID_TOPIC' => 'id_topic',
		],
		Schema\v2_0\LogSearchTopics::class => [
			'ID_SEARCH' => 'id_search',
			'ID_TOPIC' => 'id_topic',
		],
		Schema\v2_0\LogSubscribed::class => [
			'ID_SUBLOG' => 'id_sublog',
			'ID_SUBSCRIBE' => 'id_subscribe',
			'OLD_ID_GROUP' => 'old_id_group',
			'startTime' => 'start_time',
			'endTime' => 'end_time',
		],
		Schema\v2_0\LogTopics::class => [
			'ID_MEMBER' => 'id_member',
			'ID_MSG' => 'id_msg',
			'ID_TOPIC' => 'id_topic',
		],
		Schema\v2_0\MailQueue::class => [
			'ID_MAIL' => 'id_mail',
		],
		Schema\v2_0\Members::class => [
			'ID_MEMBER' => 'id_member',
			'memberName' => 'member_name',
			'dateRegistered' => 'date_registered',
			'ID_GROUP' => 'id_group',
			'lastLogin' => 'last_login',
			'realName' => 'real_name',
			'instantMessages' => 'instant_messages',
			'unreadMessages' => 'unread_messages',
			'messageLabels' => 'message_labels',
			'emailAddress' => 'email_address',
			'personalText' => 'personal_text',
			'websiteTitle' => 'website_title',
			'websiteUrl' => 'website_url',
			'ICQ' => 'icq',
			'AIM' => 'aim',
			'YIM' => 'yim',
			'MSN' => 'msn',
			'hideEmail' => 'hide_email',
			'showOnline' => 'show_online',
			'timeFormat' => 'time_format',
			'timeOffset' => 'time_offset',
			'karmaBad' => 'karma_bad',
			'karmaGood' => 'karma_good',
			'notifyAnnouncements' => 'notify_announcements',
			'notifyRegularity' => 'notify_regularity',
			'notifySendBody' => 'notify_send_body',
			'notifyTypes' => 'notify_types',
			'memberIP' => 'member_ip',
			'secretQuestion' => 'secret_question',
			'secretAnswer' => 'secret_answer',
			'ID_THEME' => 'id_theme',
			'ID_MSG_LAST_VISIT' => 'id_msg_last_visit',
			'additionalGroups' => 'additional_groups',
			'smileySet' => 'smiley_set',
			'ID_POST_GROUP' => 'id_post_group',
			'totalTimeLoggedIn' => 'total_time_logged_in',
			'passwordSalt' => 'password_salt',
			'ignoreBoards' => 'ignore_boards',
			'memberIP2' => 'member_ip2',
		],
		Schema\v2_0\Messages::class => [
			'ID_MSG' => 'id_msg',
			'ID_TOPIC' => 'id_topic',
			'ID_BOARD' => 'id_board',
			'posterTime' => 'poster_time',
			'ID_MEMBER' => 'id_member',
			'ID_MSG_MODIFIED' => 'id_msg_modified',
			'posterName' => 'poster_name',
			'posterEmail' => 'poster_email',
			'posterIP' => 'poster_ip',
			'smileysEnabled' => 'smileys_enabled',
			'modifiedTime' => 'modified_time',
			'modifiedName' => 'modified_name',
		],
		Schema\v2_0\Membergroups::class => [
			'ID_GROUP' => 'id_group',
			'ID_PARENT' => 'id_parent',
			'groupName' => 'group_name',
			'onlineColor' => 'online_color',
			'minPosts' => 'min_posts',
			'maxMessages' => 'max_messages',
			'groupType' => 'group_type',
		],
		Schema\v2_0\MessageIcons::class => [
			'ID_ICON' => 'id_icon',
			'iconOrder' => 'icon_order',
			'ID_BOARD' => 'id_board',
		],
		Schema\v2_0\Moderators::class => [
			'ID_MEMBER' => 'id_member',
			'ID_BOARD' => 'id_board',
		],
		Schema\v2_0\PackageServers::class => [
			'ID_SERVER' => 'id_server',
		],
		Schema\v2_0\PersonalMessages::class => [
			'ID_PM' => 'id_pm',
			'ID_MEMBER_FROM' => 'id_member_from',
			'deletedBySender' => 'deleted_by_sender',
			'fromName' => 'from_name',
		],
		Schema\v2_0\PermissionProfiles::class => [
			'ID_PROFILE' => 'id_profile',
		],
		Schema\v2_0\Permissions::class => [
			'ID_GROUP' => 'id_group',
			'addDeny' => 'add_deny',
		],
		Schema\v2_0\PmRecipients::class => [
			'ID_PM' => 'id_pm',
			'ID_MEMBER' => 'id_member',
		],
		Schema\v2_0\Polls::class => [
			'ID_POLL' => 'id_poll',
			'ID_MEMBER' => 'id_member',
			'votingLocked' => 'voting_locked',
			'maxVotes' => 'max_votes',
			'expireTime' => 'expire_time',
			'hideResults' => 'hide_results',
			'changeVote' => 'change_vote',
			'posterName' => 'poster_name',
		],
		Schema\v2_0\PollChoices::class => [
			'ID_CHOICE' => 'id_choice',
			'ID_POLL' => 'id_poll',
		],
		Schema\v2_0\ScheduledTasks::class => [
			'ID_TASK' => 'id_task',
			'nextTime' => 'next_time',
			'timeRegularity' => 'time_regularity',
			'timeOffset' => 'time_offset',
			'timeUnit' => 'time_unit',
		],
		Schema\v2_0\Smileys::class => [
			'ID_SMILEY' => 'id_smiley',
			'smileyRow' => 'smiley_row',
			'smileyOrder' => 'smiley_order',
		],
		Schema\v2_0\Subscriptions::class => [
			'ID_SUBSCRIBE' => 'id_subscribe',
			'ID_GROUP' => 'id_group',
			'addGroups' => 'add_groups',
			'allowPartial' => 'allow_partial',
		],
		Schema\v2_0\Themes::class => [
			'ID_MEMBER' => 'id_member',
			'ID_THEME' => 'id_theme',
		],
		Schema\v2_0\Topics::class => [
			'ID_TOPIC' => 'id_topic',
			'isSticky' => 'is_sticky',
			'ID_BOARD' => 'id_board',
			'ID_FIRST_MSG' => 'id_first_msg',
			'ID_LAST_MSG' => 'id_last_msg',
			'ID_MEMBER_STARTED' => 'id_member_started',
			'ID_MEMBER_UPDATED' => 'id_member_updated',
			'ID_POLL' => 'id_poll',
			'numReplies' => 'num_replies',
			'numViews' => 'num_views',
			'unapprovedPosts' => 'unapproved_posts',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Renaming table columns
		foreach ($this->name_changes as $table_class => $column_changes) {
			$table = new $table_class();
			$existing_structure = $table->getCurrentStructure();

			foreach ($column_changes as $old => $new) {
				if (
					!isset($existing_structure['columns'][$old])
					|| !isset($table->columns[$new])
				) {
					continue;
				}

				$table->alterColumn(
					$table->columns[$new],
					$old,
				);
			}
		}

		// Converting "log_online"
		$table = new Schema\v2_0\LogOnline();
		$table->drop();
		$table->create();

		$this->handleTimeout();

		return true;
	}
}
