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
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Polls extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting polls';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_0\Polls();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'option1') !== [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Normalize the table structure.
		$table = new Schema\v1_0\Polls();

		for ($i = 1; $i <= 8; $i++) {
			$table->dropColumn('option' . $i);
			$table->dropColumn('votes' . $i);
		}

		$table->dropColumn('votedMemberIDs');
		$table->dropIndex($table->indexes['primary']);
		$table->normalize();

		// Update data about who created each poll.
		Db::$db->update_from(
			table: [
				'name' => '{db_prefix}polls',
				'alias' => 'p',
			],
			from_tables: [
				[
					'name' => '{db_prefix}topics',
					'alias' => 't',
					'condition' => 'p.ID_POLL = t.ID_POLL',
				],
			],
			set: 'p.ID_MEMBER = t.ID_MEMBER_STARTED',
			where: 'p.ID_MEMBER = 0 AND t.ID_MEMBER_STARTED != 0',
			db_values: [],
		);

		// Done.
		$this->handleTimeout();

		return true;
	}
}
