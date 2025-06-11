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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class PersonalMessages extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating personal message functionality';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$existing_tables = Db::$db->list_tables();

		// Rename the existing tables.
		if (\in_array(Config::$db_prefix . 'instant_messages', $existing_tables)) {
			$this->query(
				'RENAME TABLE {db_prefix}instant_messages
				TO {db_prefix}personal_messages',
			);
		}

		if (\in_array(Config::$db_prefix . 'im_recipients', $existing_tables)) {
			$this->query(
				'RENAME TABLE {db_prefix}im_recipients
				TO {db_prefix}pm_recipients',
			);
		}

		// Update columns and indexes on pm_recipients table.
		$table = new Schema\v1_1\PmRecipients();
		$table->normalize();

		// Data updates.
		$this->query(
			'UPDATE {db_prefix}pm_recipients
			SET labels = {string:neg_one}
			WHERE labels NOT RLIKE {string:regex} OR labels = {string:empty}',
			[
				'neg_one' => '-1',
				'regex' => '[0-9,\-]',
				'empty' => '',
			],
		);

		// Rename columns in members table
		$table = new Schema\v1_1\Members();
		$existing_structure = $table->getCurrentStructure();

		if (isset($existing_structure['columns']['im_ignore_list'])) {
			$table->alterColumn($table->columns['pm_ignore_list'], 'im_ignore_list');
		}

		if (isset($existing_structure['columns']['im_email_notify'])) {
			$table->alterColumn($table->columns['pm_email_notify'], 'im_email_notify');
		}

		$table->normalize();

		$this->handleTimeout();

		return true;
	}
}
