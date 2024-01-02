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

namespace SMF\Tasks;

use SMF\Db\DatabaseApi as Db;
use SMF\Theme;
use SMF\Topic;

/**
 * Deletes moved topic notices that have passed their best-by date.
 */
class RemoveTopicRedirects extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		$topics = [];

		// We will need this for language files.
		Theme::loadEssential();

		// Find all of the old moved topic notices that were set to expire.
		$request = Db::$db->query(
			'',
			'SELECT id_topic
			FROM {db_prefix}topics
			WHERE redirect_expires <= {int:redirect_expires}
				AND redirect_expires <> 0',
			[
				'redirect_expires' => time(),
			],
		);

		while ($row = Db::$db->fetch_row($request)) {
			$topics[] = $row[0];
		}
		Db::$db->free_result($request);

		// Zap, you're gone.
		if (count($topics) > 0) {
			Topic::remove($topics, false, true);
		}

		return true;
	}
}

?>