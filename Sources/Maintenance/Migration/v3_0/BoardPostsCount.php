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

class BoardPostsCount extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Replacing count_posts with posts_count in boards table';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v3_0\Boards();
		$existing_structure = $table->getCurrentStructure();

		return isset($existing_structure['columns']['count_posts']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v3_0\Boards();
		$table->addColumn($table->columns['posts_count']);

		$this->query(
			'UPDATE {db_prefix}boards
			SET posts_count = CASE WHEN count_posts = 0 THEN 1 ELSE 0 END',
		);

		$table->dropColumn('count_posts');

		return true;
	}
}
