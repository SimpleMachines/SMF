<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Column;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class CustomFieldsPart3 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Upgrade Custom Fields (Cleanup)';

	private array $possible_columns = ['icq', 'msn', 'location', 'gender'];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		if ($start <= 0) {
			$table = new \SMF\Db\Schema\v3_0\CustomFields();
			$existing_structure = $table->getCurrentStructure();

			foreach ($existing_structure['columns'] as $column['name']) {
				if (in_array($column['name'], $this->possible_columns)) {
					$col = new Column(
						name: $column['name'],
						type: 'varchar',
					);

					$table->dropColumn($col);
				}
			}

			$this->handleTimeout(++$start);
		}

		if ($start <= 1 && empty(Config::$modSettings['displayFields'])) {
			$request = $this->query(
				'',
				'SELECT col_name, field_name, field_type, field_order, bbc, enclose, placement, show_mlist
				FROM {db_prefix}custom_fields',
				[],
			);

			$fields = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$fields[] = [
					'col_name' => strtr($row['col_name'], ['|' => '', ';' => '']),
					'title' => strtr($row['field_name'], ['|' => '', ';' => '']),
					'type' => $row['field_type'],
					'order' => $row['field_order'],
					'bbc' => $row['bbc'] ? '1' : '0',
					'placement' => !empty($row['placement']) ? $row['placement'] : '0',
					'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
					'mlist' => $row['show_mlist'],
				];
			}
			Db::$db->free_result($request);

			Config::updateModSettings([
				'displayFields' => json_encode($fields),
			]);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>