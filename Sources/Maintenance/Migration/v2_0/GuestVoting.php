<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class GuestVoting extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding guest voting';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return !\in_array('guest_vote', Db::$db->list_columns(Config::$db_prefix . 'log_polls'));
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the tables are structured correctly.
		$table = new Schema\v2_0\Polls();
		$table->normalize();

		$table = new Schema\v2_0\LogPolls();
		$table->normalize();

		$this->query(
			'DELETE FROM {db_prefix}log_polls
			WHERE id_member < 0',
			[],
		);

		$request = $this->query(
			'SELECT p.id_poll, count(lp.id_member) as guest_voters
			FROM {db_prefix}polls AS p
				LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member = 0)
			WHERE lp.id_member = 0
				AND p.num_guest_voters = 0
			GROUP BY p.id_poll',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->query(
				'UPDATE {db_prefix}polls
				SET num_guest_voters = {int:guest_voters}
				WHERE id_poll = {int:id_poll}
					AND num_guest_voters = 0',
				$row,
			);
		}

		Db::$db->free_result($request);

		$this->handleTimeout();

		return true;
	}
}
