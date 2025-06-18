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

use SMF\Maintenance\Migration\MigrationBase;

class InstantMessages7 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting instant messages, part 7';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_TO',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX deletedBy',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX readBy',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP COLUMN ID_MEMBER_TO,
			DROP COLUMN deletedBy,
			DROP COLUMN toName,
			DROP COLUMN readBy',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			ADD INDEX ID_MEMBER (ID_MEMBER_FROM, deletedBySender)',
		);

		$this->handleTimeout();

		return true;
	}
}
