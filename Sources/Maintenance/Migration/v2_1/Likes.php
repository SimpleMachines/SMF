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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class Likes extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding support for likes';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\UserLikes();

		return !$table->exists();
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		if ($start <= 0) {
			$likes_table = new Schema\v2_1\UserLikes();
			$likes_table->create();
			$this->handleTimeout(++$start);
		}

		// Adding likes column to the messages table. (May take a while)
		if ($start <= 1) {
			$messages_table = new Schema\v2_1\Messages();
			$existing_structure = $messages_table->getCurrentStructure();

			if (!isset($existing_structure['columns']['likes'])) {
				$messages_table->addColumn($messages_table->columns['likes']);
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
