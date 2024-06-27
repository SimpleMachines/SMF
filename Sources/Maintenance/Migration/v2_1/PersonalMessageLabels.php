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
use SMF\Db\Schema\Column;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class PersonalMessageLabels extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Upgrading PM labels';

	private int $limit = 5000;

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$MembersTable = new \SMF\Db\Schema\v3_0\Members();
		$existing_columns = Db::$db->list_columns('{db_prefix}' . $MembersTable->name);

		foreach ($MembersTable->columns as $column) {
			if ($column->name === 'message_labels') {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$PmLabelsTable = new \SMF\Db\Schema\v3_0\PmLabels();
		$PmLabeledMessagesTable = new \SMF\Db\Schema\v3_0\PmLabeledMessages();

		$tables = Db::$db->list_tables();

		if ($start <= 0) {
			if (!in_array(Config::$db_prefix . 'pm_labels', $tables)) {
				$PmLabelsTable->create();
				$this->handleTimeout(0);
			}

			if (!in_array(Config::$db_prefix . 'pm_labeled_messages', $tables)) {
				$PmLabeledMessagesTable->create();
				$this->handleTimeout(0);
			}

			$PmRecipientsTable = new \SMF\Db\Schema\v3_0\PmRecipients();
			$existing_columns = Db::$db->list_columns('{db_prefix}' . $PmRecipientsTable->name);

			foreach ($PmRecipientsTable->columns as $column) {
				// Column exists, don't need to do this.
				if ($column->name === 'in_inbox' && in_array($column->name, $existing_columns)) {
					continue;
				}

				$PmRecipientsTable->addColumn($column);
			}

			$this->handleTimeout(++$start);
		}

		$start = Maintenance::getCurrentStart();

		$request = $this->query('', 'SELECT COUNT(*) FROM {db_prefix}members');
		list($maxMembers) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		Maintenance::$total_items = (int) $maxMembers;

		if ($maxMembers > 0) {
			$is_done = false;

			while (!$is_done) {
				$this->handleTimeout($start);

				$inserts = [];

				// Pull the label info
				$get_labels = Db::$db->query(
					'',
					'SELECT id_member, message_labels
					FROM {db_prefix}members
					WHERE message_labels != {string:blank}
					ORDER BY id_member
					LIMIT {int:limit}',
					[
						'blank' => '',
						'limit' => $this->limit,
					],
				);

				$label_info = [];
				$member_list = [];

				while ($row = Db::$db->fetch_assoc($get_labels)) {
					$member_list[] = $row['id_member'];

					// Stick this in an array
					$labels = explode(',', $row['message_labels']);

					// Build some inserts
					foreach ($labels as $index => $label) {
						// Keep track of the index of this label - we'll need that in a bit...
						$label_info[$row['id_member']][$label] = $index;
					}
				}

				Db::$db->free_result($get_labels);

				foreach ($label_info as $id_member => $labels) {
					foreach ($labels as $label => $index) {
						$inserts[] = [$id_member, $label];
					}
				}

				if (!empty($inserts)) {
					Db::$db->insert('', '{db_prefix}pm_labels', ['id_member' => 'int', 'name' => 'string-30'], $inserts, []);

					// Clear this out for our next query below
					$inserts = [];
				}

				// This is the easy part - update the inbox stuff
				Db::$db->query(
					'',
					'UPDATE {db_prefix}pm_recipients
					SET in_inbox = {int:in_inbox}
					WHERE FIND_IN_SET({int:minusone}, labels)
						AND id_member IN ({array_int:member_list})',
					[
						'in_inbox' => 1,
						'minusone' => -1,
						'member_list' => $member_list,
					],
				);

				// Now we go pull the new IDs for each label
				$get_new_label_ids = Db::$db->query(
					'',
					'SELECT *
					FROM {db_prefix}pm_labels
					WHERE id_member IN ({array_int:member_list})',
					[
						'member_list' => $member_list,
					],
				);

				$label_info_2 = [];

				while ($label_row = Db::$db->fetch_assoc($get_new_label_ids)) {
					// Map the old index values to the new ID values...
					$old_index = $label_info[$label_row['id_member']][$label_row['name']];
					$label_info_2[$label_row['id_member']][$old_index] = $label_row['id_label'];
				}

				Db::$db->free_result($get_new_label_ids);

				// Pull label info from pm_recipients
				// Ignore any that are only in the inbox
				$get_pm_labels = Db::$db->query(
					'',
					'SELECT id_pm, id_member, labels
					FROM {db_prefix}pm_recipients
					WHERE deleted = {int:not_deleted}
						AND labels != {string:minus_one}
						AND id_member IN ({array_int:member_list})',
					[
						'not_deleted' => 0,
						'minus_one' => -1,
						'member_list' => $member_list,
					],
				);

				while ($row = Db::$db->fetch_assoc($get_pm_labels)) {
					$labels = explode(',', $row['labels']);

					foreach ($labels as $a_label) {
						if ($a_label == '-1') {
							continue;
						}

						$new_label_info = $label_info_2[$row['id_member']][$a_label];
						$inserts[] = [$row['id_pm'], $new_label_info];
					}
				}

				Db::$db->free_result($get_pm_labels);

				// Insert the new data
				if (!empty($inserts)) {
					Db::$db->insert('', '{db_prefix}pm_labeled_messages', ['id_pm' => 'int', 'id_label' => 'int'], $inserts, []);
				}

				// Final step of this ridiculously massive process
				$get_pm_rules = Db::$db->query(
					'',
					'SELECT id_member, id_rule, actions
					FROM {db_prefix}pm_rules
					WHERE id_member IN ({array_int:member_list})',
					[
						'member_list' => $member_list,
					],
				);

				// Go through the rules, unserialize the actions, then figure out if there's anything we can use
				while ($row = Db::$db->fetch_assoc($get_pm_rules)) {
					$updated = false;

					// Turn this into an array...
					$actions = unserialize($row['actions']);

					// Loop through the actions and see if we're applying a label anywhere
					foreach ($actions as $index => $action) {
						if ($action['t'] == 'lab') {
							// Update the value of this label...
							$actions[$index]['v'] = $label_info_2[$row['id_member']][$action['v']];
							$updated = true;
						}
					}

					if ($updated) {
						// Put this back into a string
						$actions = serialize($actions);

						Db::$db->query(
							'',
							'UPDATE {db_prefix}pm_rules
							SET actions = {string:actions}
							WHERE id_rule = {int:id_rule}',
							[
								'actions' => $actions,
								'id_rule' => $row['id_rule'],
							],
						);
					}
				}

				// Remove processed pm labels, to avoid duplicated data if upgrader is restarted.
				Db::$db->query(
					'',
					'UPDATE {db_prefix}members
					SET message_labels = {string:blank}
					WHERE id_member IN ({array_int:member_list})',
					[
						'blank' => '',
						'member_list' => $member_list,
					],
				);

				Db::$db->free_result($get_pm_rules);
				$start += $this->limit;

				if ($start >= $maxMembers) {
					$is_done = true;
				}
			}
		}

		$PmRecipientsTable = new \SMF\Db\Schema\v3_0\PmRecipients();
		$existing_columns = Db::$db->list_columns('{db_prefix}' . $PmRecipientsTable->name);

		foreach ($existing_columns as $column) {
			if ($column == 'labels') {
				$col = new Column(
					name: $column,
					type: 'varchar',
				);

				$PmRecipientsTable->dropColumn($column);
			}
		}

		$MembersTable = new \SMF\Db\Schema\v3_0\Members();
		$existing_columns = Db::$db->list_columns('{db_prefix}' . $MembersTable->name);

		foreach ($existing_columns as $column) {
			if ($column == 'message_labels') {
				$col = new Column(
					name: $column,
					type: 'varchar',
				);

				$MembersTable->dropColumn($column);
			}
		}

		return true;
	}
}

?>