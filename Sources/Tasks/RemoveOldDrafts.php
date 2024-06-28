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

namespace SMF\Sources\Tasks;

use SMF\Sources\Config;
use SMF\Sources\Db\DatabaseApi as Db;
use SMF\Sources\Draft;
use SMF\Sources\Theme;

/**
 * Check for old drafts and remove them.
 */
class RemoveOldDrafts extends ScheduledTask
{
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		if (empty(Config::$modSettings['drafts_keep_days'])) {
			$this->should_log = false;

			return true;
		}

		$drafts = [];

		// We need this for language items.
		Theme::loadEssential();

		// Find all of the old drafts.
		$request = Db::$db->query(
			'',
			'SELECT id_draft
			FROM {db_prefix}user_drafts
			WHERE poster_time <= {int:poster_time_old}',
			[
				'poster_time_old' => time() - (86400 * Config::$modSettings['drafts_keep_days']),
			],
		);

		while ($row = Db::$db->fetch_row($request)) {
			$drafts[] = (int) $row[0];
		}
		Db::$db->free_result($request);

		// If we have old ones, remove them.
		if (count($drafts) > 0) {
			Draft::delete($drafts, false);
		}

		return true;
	}
}

?>