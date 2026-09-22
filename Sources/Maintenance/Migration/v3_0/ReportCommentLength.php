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

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class ReportCommentLength extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Widening the report comment to fit its entity encoded form';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v3_0\LogReportedComments();
		$existing_structure = $table->getCurrentStructure();

		foreach ($existing_structure['columns'] as $column) {
			if ($column['name'] === 'comment') {
				return $column['type'] !== $table->columns['comment']->type;
			}
		}

		return false;
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\LogReportedComments();

		foreach ($table->columns as $column) {
			if ($column->name === 'comment') {
				// A text column takes no default, so the varchar one has to go.
				$column->drop_default = true;

				$table->alterColumn($column);
			}
		}

		return true;
	}
}
