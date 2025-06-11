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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Utils;

class SearchSubjects extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Indexing topic subjects';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 250;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v1_1\LogSearchSubjects();
		$table->normalize();

		$request = $this->query(
			'SELECT COUNT(*)
			FROM {db_prefix}topics',
			[],
		);

		list($max) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		$is_done = false;

		while (!$is_done) {
			$this->handleTimeout();

			$inserts = [];

			$request = $this->query(
				'SELECT t.ID_TOPIC, m.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.ID_MSG = t.ID_FIRST_MSG)
		 		ORDER BY t.ID_TOPIC
		 		LIMIT {int:limit}
		 		OFFSET {int:start}',
				[
					'start' => Maintenance::getCurrentStart(),
					'limit' => $this->limit,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Maintenance::setCurrentStart();
				$is_done = Maintenance::getCurrentStart() >= $max;

				foreach (Utils::extractWords($row['subject'], 2) as $word) {
					$word = Db::$db->escape_string($word);
					$inserts[$word . ' ' . $row['ID_TOPIC']] = [$word, $row['ID_TOPIC']];
				}
			}

			Db::$db->free_result($request);

			if (!empty($inserts)) {
				Db::$db->insert(
					method: '',
					table: '{db_prefix}log_search_subjects',
					columns: [
						'word' => 'string',
						'ID_TOPIC' => 'int',
					],
					data: $inserts,
					keys: [],
				);
			}
		}

		return true;
	}
}
