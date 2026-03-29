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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class PostgreSqlSequences extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Fixing sequences (PostgreSQL)';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private array $sequences = [
		[
			'key' => 'admin_info_files_seq',
			'table' => 'admin_info_files',
			'field' => 'id_file',
		],
		[
			'key' => 'attachments_seq',
			'table' => 'attachments',
			'field' => 'id_attach',
		],
		[
			'key' => 'ban_groups_seq',
			'table' => 'ban_groups',
			'field' => 'id_ban_group',
		],
		[
			'key' => 'ban_items_seq',
			'table' => 'ban_items',
			'field' => 'id_ban',
		],
		[
			'key' => 'boards_seq',
			'table' => 'boards',
			'field' => 'id_board',
		],
		[
			'key' => 'calendar_seq',
			'table' => 'calendar',
			'field' => 'id_event',
		],
		[
			'key' => 'calendar_holidays_seq',
			'table' => 'calendar_holidays',
			'field' => 'id_holiday',
		],
		[
			'key' => 'categories_seq',
			'table' => 'categories',
			'field' => 'id_cat',
		],
		[
			'key' => 'custom_fields_seq',
			'table' => 'custom_fields',
			'field' => 'id_field',
		],
		[
			'key' => 'log_actions_seq',
			'table' => 'log_actions',
			'field' => 'id_action',
		],
		[
			'key' => 'log_banned_seq',
			'table' => 'log_banned',
			'field' => 'id_ban_log',
		],
		[
			'key' => 'log_comments_seq',
			'table' => 'log_comments',
			'field' => 'id_comment',
		],
		[
			'key' => 'log_errors_seq',
			'table' => 'log_errors',
			'field' => 'id_error',
		],
		[
			'key' => 'log_group_requests_seq',
			'table' => 'log_group_requests',
			'field' => 'id_request',
		],
		[
			'key' => 'log_member_notices_seq',
			'table' => 'log_member_notices',
			'field' => 'id_notice',
		],
		[
			'key' => 'log_packages_seq',
			'table' => 'log_packages',
			'field' => 'id_install',
		],
		[
			'key' => 'log_reported_seq',
			'table' => 'log_reported',
			'field' => 'id_report',
		],
		[
			'key' => 'log_reported_comments_seq',
			'table' => 'log_reported_comments',
			'field' => 'id_comment',
		],
		[
			'key' => 'log_scheduled_tasks_seq',
			'table' => 'log_scheduled_tasks',
			'field' => 'id_log',
		],
		[
			'key' => 'log_spider_hits_seq',
			'table' => 'log_spider_hits',
			'field' => 'id_hit',
		],
		[
			'key' => 'log_subscribed_seq',
			'table' => 'log_subscribed',
			'field' => 'id_sublog',
		],
		[
			'key' => 'mail_queue_seq',
			'table' => 'mail_queue',
			'field' => 'id_mail',
		],
		[
			'key' => 'membergroups_seq',
			'table' => 'membergroups',
			'field' => 'id_group',
		],
		[
			'key' => 'members_seq',
			'table' => 'members',
			'field' => 'id_member',
		],
		[
			'key' => 'message_icons_seq',
			'table' => 'message_icons',
			'field' => 'id_icon',
		],
		[
			'key' => 'messages_seq',
			'table' => 'messages',
			'field' => 'id_msg',
		],
		[
			'key' => 'package_servers_seq',
			'table' => 'package_servers',
			'field' => 'id_server',
		],
		[
			'key' => 'permission_profiles_seq',
			'table' => 'permission_profiles',
			'field' => 'id_profile',
		],
		[
			'key' => 'personal_messages_seq',
			'table' => 'personal_messages',
			'field' => 'id_pm',
		],
		[
			'key' => 'pm_rules_seq',
			'table' => 'pm_rules',
			'field' => 'id_rule',
		],
		[
			'key' => 'polls_seq',
			'table' => 'polls',
			'field' => 'id_poll',
		],
		[
			'key' => 'scheduled_tasks_seq',
			'table' => 'scheduled_tasks',
			'field' => 'id_task',
		],
		[
			'key' => 'smileys_seq',
			'table' => 'smileys',
			'field' => 'id_smiley',
		],
		[
			'key' => 'spiders_seq',
			'table' => 'spiders',
			'field' => 'id_spider',
		],
		[
			'key' => 'subscriptions_seq',
			'table' => 'subscriptions',
			'field' => 'id_subscribe',
		],
		[
			'key' => 'topics_seq',
			'table' => 'topics',
			'field' => 'id_topic',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return Db::$db->title === POSTGRE_TITLE;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		for ($i = Maintenance::getCurrentStart(); $i < \count($this->sequences); Maintenance::setCurrentStart()) {
			$this->handleTimeout();

			$value = $this->sequences[$i];

			$this->query(
				"SELECT setval('{raw:key}', (SELECT COALESCE(MAX({raw:field}),1) FROM {raw:table}))",
				[
					'key' => Config::$db_prefix . $value['key'],
					'field' => $value['field'],
					'table' => Config::$db_prefix . $value['table'],
				],
			);
		}

		return true;
	}
}
