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
use SMF\Maintenance;

class Migration1022 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Creating alert prefs for watched boards';

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
		$request = Db::$db->query('', 'SELECT COUNT(*) FROM {db_prefix}log_notify WHERE id_member <> 0 AND id_board <> 0');
		list($maxBoards) = Db::$db->fetch_row($request);
		Maintenance::$total_items = (int) $maxBoards;
		Db::$db->free_result($request);

		$start = Maintenance::getCurrentStart();

		while (!$this->is_done) {
			$start = Maintenance::getCurrentStart();
			$this->handleTimeout($start);
			$inserts = [];

			// This setting is stored over in the themes table in 2.0...
			$request = Db::$db->query(
				'',
				'
				SELECT SELECT id_member, ({literal:board_notify_} || id_topic) as alert_pref, 1 as alert_value
				FROM {db_prefix}log_notify
				WHERE id_member <> 0 AND id_board <> 0
				LIMIT {int:start}, {int:limit}',
				[
					'start' => $start,
					'limit' => $this->limit,
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				break;
			}

			while ($row = Db::$db->fetch_assoc($request)) {
				$inserts[] = [$row['id_member'], 'msg_auto_notify', !empty($row['value']) ? 1 : 0];
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

		return true;
	}
}

?>