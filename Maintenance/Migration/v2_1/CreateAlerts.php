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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class CreateAlerts extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for alerts';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private bool $is_done = false;

	/**
	 *
	 */
	private array $default_alert_perms = [
		[0, 'alert_timeout', 10],
		[0, 'announcements', 0],
		[0, 'birthday', 2],
		[0, 'board_notify', 1],
		[0, 'buddy_request', 1],
		[0, 'groupr_approved', 3],
		[0, 'groupr_rejected', 3],
		[0, 'member_group_request', 1],
		[0, 'member_register', 1],
		[0, 'member_report', 3],
		[0, 'member_report_reply', 3],
		[0, 'msg_auto_notify', 0],
		[0, 'msg_like', 1],
		[0, 'msg_mention', 1],
		[0, 'msg_notify_pref', 1],
		[0, 'msg_notify_type', 1],
		[0, 'msg_quote', 1],
		[0, 'msg_receive_body', 0],
		[0, 'msg_report', 1],
		[0, 'msg_report_reply', 1],
		[0, 'pm_new', 1],
		[0, 'pm_notify', 1],
		[0, 'pm_reply', 1],
		[0, 'request_group', 1],
		[0, 'topic_notify', 1],
		[0, 'unapproved_attachment', 1],
		[0, 'unapproved_reply', 3],
		[0, 'unapproved_post', 1],
		[0, 'warn_any', 1],
	];

	/**
	 *
	 */
	private int $limit = 10000;

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
		$members_table = new \SMF\Maintenance\Database\Schema\v2_1\Members();
		$user_alert_prefs_table = new \SMF\Maintenance\Database\Schema\v2_1\UserAlertsPrefs();

		$tables = Db::$db->list_tables();

		if (!in_array(Config::$db_prefix . $members_table->name, $tables)) {
			$members_table->create();
		}

		$existing_structure = $members_table->getCurrentStructure();

		foreach ($members_table->columns as $column) {
			// Column exists, don't need to do this.
			if ($column->name !== 'alerts' || isset($existing_structure['columns'][$column->name])) {
				continue;
			}

			$members_table->addColumn($column);
		}

		// We don't need to increment the start, the column will exist and it should get past this.
		$this->handleTimeout(0);

		// We don't need to increment the start, the column will exist and it should get past this.
		$this->handleTimeout(0);

		// Add our default permissions.
		Db::$db->insert(
			'ignore',
			'{db_prefix}' . $user_alert_prefs_table->name,
			['id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'int'],
			$this->default_alert_perms,
			['id_theme', 'alert_pref'],
		);

		$request = $this->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}members',
			[],
		);

		list($maxMembers) = Db::$db->fetch_row($request);
		Maintenance::$total_items = (int) $maxMembers;

		Db::$db->free_result($request);

		// First see if we still have a notify_regularity column
		$member_columns = Db::$db->list_columns('{db_prefix}members');

		if (in_array('notify_regularity', $member_columns)) {
			while (!$this->is_done) {
				$start = Maintenance::getCurrentStart();

				$this->handleTimeout($start);
				$inserts = [];

				// Skip errors here so we don't croak if the columns don't exist...
				$request = $this->query(
					'',
					'SELECT id_member, notify_regularity, notify_send_body, notify_types, notify_announcements
					FROM {db_prefix}members
					ORDER BY id_member
					LIMIT {int:start}, {int:limit}',
					[
						'db_error_skip' => true,
						'start' => $start,
						'limit' => $this->limit,
					],
				);

				if (Db::$db->num_rows($request) == 0) {
					break;
				}

				while ($row = Db::$db->fetch_assoc($request)) {
					$inserts[] = [$row['id_member'], 'msg_receive_body', !empty($row['notify_send_body']) ? 1 : 0];
					$inserts[] = [$row['id_member'], 'msg_notify_pref', intval($row['notify_regularity']) + 1];
					$inserts[] = [$row['id_member'], 'msg_notify_type', $row['notify_types']];
					$inserts[] = [$row['id_member'], 'announcements', !empty($row['notify_announcements']) ? 1 : 0];
				}

				Db::$db->free_result($request);

				Db::$db->insert(
					'ignore',
					'{db_prefix}user_alerts_prefs',
					['id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'],
					$inserts,
					['id_member', 'alert_pref'],
				);

				Maintenance::setCurrentStart($start + $this->limit);
			}
		}

		if (in_array('notify_send_body', $member_columns)) {
			Db::$db->remove_column('{db_prefix}members', 'notify_send_body');
			$this->handleTimeout();
		}

		if (in_array('notify_types', $member_columns)) {
			Db::$db->remove_column('{db_prefix}members', 'notify_types');
			$this->handleTimeout();
		}

		if (in_array('notify_regularity', $member_columns)) {
			Db::$db->remove_column('{db_prefix}members', 'notify_regularity');
			$this->handleTimeout();
		}

		if (in_array('notify_announcements', $member_columns)) {
			Db::$db->remove_column('{db_prefix}members', 'notify_announcements');
			$this->handleTimeout();
		}

		return true;
	}
}

?>