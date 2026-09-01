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

class LogPolls extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting poll voter logs';

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
			FROM {db_prefix}log_polls',
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
		$inserts = [];

		$request = $this->query(
			'SELECT ID_POLL, votedMemberIDs
			FROM {db_prefix}polls',
		);

		while ($row = Db::$db->fetch_assoc($query)) {
			$members = explode(',', $row['votedMemberIDs']);

			foreach ($members as $member) {
				if (is_numeric($member) && !empty($member)) {
					$inserts[] = [
						(int) $row['ID_POLL'],
						(int) $member,
						0,
					];
				}
			}
		}

		Db::$db->free_result($request);

		if (!empty($inserts)) {
			Db::$db->insert(
				method: 'ignore',
				table: '{db_prefix}log_polls',
				columns: [
					'ID_POLL' => 'int',
					'ID_MEMBER' => 'int',
					'ID_CHOICE' => 'int',
				],
				data: $inserts,
				keys: [],
			);
		}

		$this->handleTimeout();

		return true;
	}
}
