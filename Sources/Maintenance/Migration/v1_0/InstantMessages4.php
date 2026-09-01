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

class InstantMessages4 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting instant messages, part 4';

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
			DROP INDEX ID_MEMBER_FROM_2',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_FROM_3',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_FROM_4',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_FROM_5',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_TO_2',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_TO_3',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_TO_4',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX ID_MEMBER_TO_5',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX deletedBy_2',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX deletedBy_3',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX deletedBy_4',
		);

		$this->query(
			'ALTER TABLE {db_prefix}instant_messages
			DROP INDEX deletedBy_5',
		);

		$this->handleTimeout();

		return true;
	}
}
