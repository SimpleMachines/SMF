<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
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
	 * {@inheritDoc}
	 */
	public string $name = 'Updating obsolete alerts from before RC3';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private int $limit = 10000;

	/**
	 *
	 */
	private bool $is_done = false;

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		Db::$db->query('', '
		UPDATE {$db_prefix}user_alerts
		SET content_type = {literal:member}, content_id = id_member_started
		WHERE content_type = {literal:buddy}');

		$this->handleTimeout();

		Db::$db->query('', '
		UPDATE {$db_prefix}user_alerts
		SET content_type = {literal:member}
		WHERE content_type = {literal:profile}');

		$this->handleTimeout();

		Db::$db->query('', '
		UPDATE {$db_prefix}user_alerts
		SET content_id = id_member_started
		WHERE content_type = {literal:member}
			AND content_action LIKE {string:content_action', ['content_action' => 'register_%']);

		$this->handleTimeout();

		Db::$db->query('', '
		UPDATE {$db_prefix}user_alerts
		SET content_id = {literal:topic},
			content_action = {literal:unapproved_topic}
		WHERE content_type = {literal:unapproved}
			AND content_action = {string:content_action', ['content_action' => 'topic']);

		$this->handleTimeout();

		Db::$db->query('', '
		UPDATE {$db_prefix}user_alerts
		SET content_id = {literal:topic},
			content_action = {literal:unapproved_reply}
		WHERE content_type = {literal:unapproved}
			AND content_action = {string:content_action', ['content_action' => 'reply']);

		$this->handleTimeout();

		Db::$db->query('', '
		UPDATE {$db_prefix}user_alerts
		SET content_id = {literal:topic},
			content_action = {literal:unapproved_post}
		WHERE content_type = {literal:unapproved}
			AND content_action = {string:content_action', ['content_action' => 'post']);

		$this->handleTimeout();

		Db::$db->query('', '
		UPDATE {$db_prefix}user_alerts AS a
			JOIN {$db_prefix}attachments AS f
				ON (f.id_attach = a.content_id)
		SET
			a.content_type = {literal:msg},
			a.content_action = {literal:unapproved_attachment},
			a.content_id = f.id_msg
		WHERE content_type = {literal:unapproved}
			AND content_action = {literal:attachment}');

		$this->handleTimeout();

		return true;
	}
}

?>