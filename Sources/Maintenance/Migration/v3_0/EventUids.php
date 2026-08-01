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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Uuid;

class EventUids extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Setting the UID column for calendar events';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$calendar_updates = [];

		$request = $this->query(
			'SELECT id_event, uid
			FROM {db_prefix}calendar',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($row['uid'] === '') {
				$calendar_updates[] = ['id_event' => $row['id_event'], 'uid' => (string) new Uuid()];
			}
		}

		Db::$db->free_result($request);

		foreach ($calendar_updates as $calendar_update) {
			$this->query(
				'UPDATE {db_prefix}calendar
				SET uid = {string:uid}
				WHERE id_event = {int:id_event}',
				$calendar_update,
			);
		}

		return true;
	}
}
