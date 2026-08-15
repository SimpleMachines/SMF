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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class AlertsObsolete extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating obsolete alerts from before RC3';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 10000;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'UPDATE {db_prefix}user_alerts
			SET content_type = {literal:member}, content_id = id_member_started
			WHERE content_type = {literal:buddy}',
		);

		$this->handleTimeout();

		$this->query(
			'UPDATE {db_prefix}user_alerts
			SET content_type = {literal:member}
			WHERE content_type = {literal:profile}',
		);

		$this->handleTimeout();

		$this->query(
			'UPDATE {db_prefix}user_alerts
			SET content_id = id_member_started
			WHERE content_type = {literal:member}
				AND content_action LIKE {string:content_action}',
			['content_action' => 'register_%'],
		);

		$this->handleTimeout();

		$this->query(
			'UPDATE {db_prefix}user_alerts
			SET content_type = {literal:topic},
				content_action = {literal:unapproved_topic}
			WHERE content_type = {literal:unapproved}
				AND content_action = {literal:topic}',
		);

		$this->handleTimeout();

		$this->query(
			'UPDATE {db_prefix}user_alerts
			SET content_type = {literal:topic},
				content_action = {literal:unapproved_reply}
			WHERE content_type = {literal:unapproved}
				AND content_action = {literal:reply}',
		);

		$this->handleTimeout();

		$this->query(
			'UPDATE {db_prefix}user_alerts
			SET content_type = {literal:topic},
				content_action = {literal:unapproved_post}
			WHERE content_type = {literal:unapproved}
				AND content_action = {literal:post}',
		);

		$this->handleTimeout();

		Db::$db->update_from(
			table: [
				'name' => '{db_prefix}user_alerts',
				'alias' => 'a',
			],
			from_tables: [
				[
					'name' => '{db_prefix}attachments',
					'alias' => 'f',
					'condition' => 'f.id_attach = a.content_id',
				],
			],
			set: implode(', ', [
				'a.content_type = {string:new_type}',
				'a.content_action = {string:new_action}',
				'a.content_id = f.id_msg',
			]),
			where: implode(' AND ', [
				'content_type = {string:old_type}',
				'content_action = {string:old_action}',
			]),
			db_values: [
				'new_type' => 'msg',
				'new_action' => 'unapproved_attachment',
				'old_type' => 'unapproved',
				'old_action' => 'attachment',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
