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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\Schema;
use SMF\Db\Schema\Column;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class LogReportedCommentsEmail extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Dropping the "email_address" column from log_reported_comments';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\LogReportedComments();
		$existing_structure = $table->getCurrentStructure();

		foreach ($existing_structure['columns'] as $column) {
			if ($column['name'] === 'email_address') {
				return true;
			}
		}

		return false;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$table = new Schema\v2_1\LogReportedComments();
		$existing_structure = $table->getCurrentStructure();

		foreach ($existing_structure['columns'] as $column) {
			if ($column['name'] == 'email_address') {
				$col = new Column(
					name: $column['name'],
					type: 'varchar',
				);

				$table->dropColumn($col);
			}
		}

		return true;
	}
}
