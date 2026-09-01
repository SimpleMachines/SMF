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

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class RemoveIndexes extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Removing obsolete table indexes';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	private array $to_drop = [
		Schema\v1_1\Boards::class => [
			'catOrder',
		],
		Schema\v1_1\Categories::class => [
			'boardOrder',
			'children',
		],
		Schema\v1_1\LogOnline::class => [
			'online',
		],
		Schema\v1_1\Messages::class => [
			'ID_MEMBER',
		],
		Schema\v1_1\Smileys::class => [
			'smileyOrder',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Drop the ones we no longer want.
		foreach ($this->to_drop as $table_class => $indexes) {
			$table = new $table_class();

			foreach ($indexes as $index) {
				$table->dropIndex($index);
			}
		}

		$this->handleTimeout();

		return true;
	}
}
