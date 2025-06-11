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

class MessageIcons extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding custom message icons';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Update columns and indexes on pm_recipients table.
		$table = new Schema\v1_1\MessageIcons();
		$existing_structure = $table->getCurrentStructure();

		if (isset($existing_structure['columns']['Name'])) {
			$table->alterColumn($table->columns['filename'], 'Name');
			$table->alterColumn($table->columns['title'], 'Description');
			$table->dropIndex('id_icon');
			$table->normalize();
		} else {
			$table->normalize();
		}

		// We do not want to do this twice!
		if (
			version_compare(
				str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
				'1.1',
				'<',
			)
		) {
			Db::$db->insert(
				method: '',
				table: '{db_prefix}message_icons',
				columns: [
					'filename' => 'string-80',
					'title' => 'string-80',
					'iconOrder' => 'int',
				],
				data: [
					['xx', 'Standard', 0],
					['thumbup', 'Thumb Up', 1],
					['thumbdown', 'Thumb Down', 2],
					['exclamation', 'Exclamation point', 3],
					['question', 'Question mark', 4],
					['lamp', 'Lamp', 5],
					['smiley', 'Smiley', 6],
					['angry', 'Angry', 7],
					['cheesy', 'Cheesy', 8],
					['grin', 'Grin', 9],
					['sad', 'Sad', 10],
					['wink', 'Wink', 11],
				],
				keys: ['ID_ICON'],
			);
		}

		$this->handleTimeout();

		return true;
	}
}
