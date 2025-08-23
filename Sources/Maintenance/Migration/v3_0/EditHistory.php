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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class EditHistory extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding support for edit history';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Add edit_history column to messages table, if it doesn't exist.
		$table = new Schema\v3_0\Messages();
		$existing_structure = $table->getCurrentStructure();

		if (!isset($existing_structure['columns']['edit_history'])) {
			$table->addColumn($table->columns['edit_history'], 'ignore');
		}

		// Populate edit_history.
		$request = Db::$db->query(
			'SELECT id_msg, body, modified_time, modified_name, modified_reason, edit_history
			FROM {db_prefix}messages
			WHERE id_msg > {int:start}',
			[
				'start' => Maintenance::getCurrentStart(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!empty($row['edit_history']) || (int) $row['modified_time'] === 0) {
				$this->handleTimeout((int) $row['id_msg']);
				continue;
			}

			$row['edit_history'] = json_encode([[
				(int) $row['modified_time'],
				hash('crc32c', $row['body']),
				'',
				'',
				[],
				0,
				$row['modified_name'],
				$row['modified_reason'],
			]]);

			Db::$db->query(
				'UPDATE {db_prefix}messages
				SET edit_history = {string:edit_history}
				WHERE id_msg = {int:id_msg}',
				$row,
			);

			$this->handleTimeout((int) $row['id_msg']);
		}

		Db::$db->free_result($request);

		// Convert modified_time, modified_name, and modified_reason to generated columns.
		foreach (['modified_time', 'modified_time', 'modified_reason'] as $col_name) {
			$table->alterColumn($table->columns[$col_name]);
			$this->handleTimeout();
		}

		return true;
	}
}
