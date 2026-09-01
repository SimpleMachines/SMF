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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Config;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class PersonalMessages1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding new personal messaging functionality';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return version_compare(
			str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
			'2.0',
			'<',
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the tables are structured correctly.
		$table = new Schema\v2_0\PersonalMessages();
		$table->normalize();

		$table = new Schema\v2_0\Members();
		$table->normalize();

		$table = new Schema\v2_0\PmRecipients();
		$table->normalize();

		// Set all unread messages as new.
		$this->query(
			'UPDATE {db_prefix}pm_recipients
			SET is_new = 1
			WHERE is_read = 0',
			[],
		);

		// Also set members to have a new pm if they have any unread.
		$this->query(
			'UPDATE {db_prefix}members
			SET new_pm = 1
			WHERE unread_messages > 0',
			[],
		);

		// Set the correct value of id_pm_head.
		$this->query(
			'UPDATE {db_prefix}personal_messages
			SET id_pm_head = id_pm
			WHERE id_pm_head = 0',
			[],
		);

		$this->handleTimeout();

		return true;
	}
}
