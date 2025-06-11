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
use SMF\Maintenance\Migration\MigrationBase;

class SearchFullText extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Rebuilding fulltext index';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		foreach (Db::$db->list_indexes('{db_prefix}messages', true) as $index) {
			if ($index['name'] === 'subject' && \in_array('subject', $index['columns'])) {
				return true;
			}
		}
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v1_1\Messages();
		$table->dropIndex('subject');
		$table->dropIndex('body');

		$this->query(
			'ALTER TABLE {db_prefix}messages
			ADD FULLTEXT body (body)',
		);

		$this->handleTimeout();

		return true;
	}
}
