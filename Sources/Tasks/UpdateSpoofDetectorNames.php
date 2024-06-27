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

namespace SMF\Tasks;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Sapi;
use SMF\Unicode\SpoofDetector;
use SMF\User;
use SMF\Utils;

/**
 * Updates the values of the spoofdetector_name column in the members table.
 */
class UpdateSpoofDetectorNames extends BackgroundTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true
	 */
	public function execute(): bool
	{
		Sapi::setTimeLimit(MAX_CLAIM_THRESHOLD);

		if (empty($this->_details['last_member_id']) || !is_int($this->_details['last_member_id'])) {
			$this->_details['last_member_id'] = 0;
		}

		// Just in case the column is missing for some reason...
		if (
			$this->_details['last_member_id'] === 0
			&& !in_array('spoofdetector_name', Db::$db->list_columns('{db_prefix}members'))
		) {
			Db::$db->add_column(
				'{db_prefix}messages',
				[
					'name' => 'spoofdetector_name',
					'type' => 'varchar',
					'size' => 255,
					'null' => false,
					'default' => '',
				],
				[],
				'ignore',
			);
			Db::$db->add_index(
				'{db_prefix}messages',
				[
					'name' => 'idx_spoofdetector_name',
					'columns' => ['spoofdetector_name'],
				],
				[],
				'ignore',
			);
			Db::$db->add_index(
				'{db_prefix}messages',
				[
					'name' => 'idx_spoofdetector_name_id',
					'columns' => ['spoofdetector_name', 'id_member'],
				],
				[],
				'ignore',
			);
		}

		$updates = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name, spoofdetector_name
			FROM {db_prefix}members
			WHERE id_member > {int:id_member}
			ORDER BY id_member
			LIMIT {int:limit}',
			[
				'id_member' => $this->_details['last_member_id'],
				'limit' => MAX_CLAIM_THRESHOLD,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->_details['last_member_id'] = $row['id_member'];

			$skeleton = Utils::htmlspecialchars(SpoofDetector::getSkeletonString(html_entity_decode($row['real_name'], ENT_QUOTES)));

			// Don't bother updating if there's been no change.
			if ($row['spoofdetector_name'] === $skeleton) {
				continue;
			}

			$updates[$row['id_member']] = ['spoofdetector_name' => $skeleton];
		}
		Db::$db->free_result($request);

		foreach ($updates as $id_member => $data) {
			User::updateMemberData($id_member, $data);
		}

		if ($this->_details['last_member_id'] < Config::$modSettings['latestMember']) {
			$this->respawn();
		}

		return true;
	}

	/**
	 * Adds a new instance of this task to the task list.
	 */
	private function respawn(): void
	{
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				get_class($this),
				json_encode($this->_details),
				0,
			],
			[],
		);
	}
}

?>