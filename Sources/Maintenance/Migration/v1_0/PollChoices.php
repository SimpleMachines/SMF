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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class PollChoices extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting poll choices';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$request = $this->query(
			'SELECT COUNT(*)
			FROM {db_prefix}poll_choices',
		);

		list($num_rows) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		return empty($num_rows);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		for ($i = 0; $i < 8; $i++) {
			$this->query(
				'INSERT INTO {db_prefix}poll_choices
					(ID_POLL, ID_CHOICE, label, votes)
				SELECT ID_POLL, {int:id_choice}, {raw:option}, {raw:votes}
				FROM {db_prefix}polls',
				[
					'id_choice' => $i,
					'option' => 'option' . ($i + 1),
					'votes' => 'votes' . ($i + 1),
				],
			);

		}

		$this->handleTimeout();

		return true;
	}
}
