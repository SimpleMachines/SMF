<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class PostgreSQLSequences extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Fixing sequences (PostgreSQL)';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private array $sequences = [
		'admin_info_files_seq' => [
			'table' => 'admin_info_files',
			'field' => 'id_file',
		],
		'attachments_seq' => [
			'table' => 'attachments',
			'field' => 'id_attach',
		],
		'ban_groups_seq' => [
			'table' => 'ban_groups',
			'field' => 'id_ban_group',
		],
		'ban_items_seq' => [
			'table' => 'ban_items',
			'field' => 'id_ban',
		],
		'boards_seq' => [
			'table' => 'boards',
			'field' => 'id_board',
		],
		'calendar_seq' => [
			'table' => 'calendar',
			'field' => 'id_event',
		],
		'calendar_holidays_seq' => [
			'table' => 'calendar_holidays',
			'field' => 'id_holiday',
		],
		'categories_seq' => [
			'table' => 'categories',
			'field' => 'id_cat',
		],
		'custom_fields_seq' => [
			'table' => 'custom_fields',
			'field' => 'id_field',
		],
		'log_actions_seq' => [
			'table' => 'log_actions',
			'field' => 'id_action',
		],
		'log_banned_seq' => [
			'table' => 'log_banned',
			'field' => 'id_ban_log',
		],
		'log_comments_seq' => [
			'table' => 'log_comments',
			'field' => 'id_comment',
		],
		'log_errors_seq' => [
			'table' => 'log_errors',
			'field' => 'id_error',
		],
		'log_group_requests_seq' => [
			'table' => 'log_group_requests',
			'field' => 'id_request',
		],
		'log_member_notices_seq' => [
			'table' => 'log_member_notices',
			'field' => 'id_notice',
		],
		'log_packages_seq' => [
			'table' => 'log_packages',
			'field' => 'id_install',
		],
		'log_reported_seq' => [
			'table' => 'log_reported',
			'field' => 'id_report',
		],
		'log_reported_comments_seq' => [
			'table' => 'log_reported_comments',
			'field' => 'id_comment',
		],
		'log_scheduled_tasks_seq' => [
			'table' => 'log_scheduled_tasks',
			'field' => 'id_log',
		],
		'log_spider_hits_seq' => [
			'table' => 'log_spider_hits',
			'field' => 'id_hit',
		],
		'log_subscribed_seq' => [
			'table' => 'log_subscribed',
			'field' => 'id_sublog',
		],
		'mail_queue_seq' => [
			'table' => 'mail_queue',
			'field' => 'id_mail',
		],
		'membergroups_seq' => [
			'table' => 'membergroups',
			'field' => 'id_group',
		],
		'members_seq' => [
			'table' => 'members',
			'field' => 'id_member',
		],
		'message_icons_seq' => [
			'table' => 'message_icons',
			'field' => 'id_icon',
		],
		'messages_seq' => [
			'table' => 'messages',
			'field' => 'id_msg',
		],
		'package_servers_seq' => [
			'table' => 'package_servers',
			'field' => 'id_server',
		],
		'permission_profiles_seq' => [
			'table' => 'permission_profiles',
			'field' => 'id_profile',
		],
		'personal_messages_seq' => [
			'table' => 'personal_messages',
			'field' => 'id_pm',
		],
		'pm_rules_seq' => [
			'table' => 'pm_rules',
			'field' => 'id_rule',
		],
		'polls_seq' => [
			'table' => 'polls',
			'field' => 'id_poll',
		],
		'scheduled_tasks_seq' => [
			'table' => 'scheduled_tasks',
			'field' => 'id_task',
		],
		'smileys_seq' => [
			'table' => 'smileys',
			'field' => 'id_smiley',
		],
		'spiders_seq' => [
			'table' => 'spiders',
			'field' => 'id_spider',
		],
		'subscriptions_seq' => [
			'table' => 'subscriptions',
			'field' => 'id_subscribe',
		],
		'topics_seq' => [
			'table' => 'topics',
			'field' => 'id_topic',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Config::$db_type == POSTGRE_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		for ($key = Maintenance::getCurrentStart(); $key < count($this->sequences); Maintenance::setCurrentStart()) {
			$this->handleTimeout();

			$value = $this->sequences[$key];

			$this->query(
				'',
				"SELECT setval('{raw:key}', (SELECT COALESCE(MAX({raw:field}),1) FROM {raw:table}))",
				[
					'key' => Config::$db_prefix . $key,
					'field' => $value['field'],
					'table' => $value['table'],
				],
			);
		}

		return true;
	}
}

?>