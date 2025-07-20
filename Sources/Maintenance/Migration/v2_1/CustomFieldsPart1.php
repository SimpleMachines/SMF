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

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class CustomFieldsPart1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrade Custom Fields (Preparing)';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		if ($start <= 0) {
			$table = new Schema\v2_1\CustomFields();
			$table->normalize();
			$this->handleTimeout(++$start);
		}

		if ($start <= 1) {
			$table->populate();
			$this->handleTimeout(++$start);
		}

		if ($start <= 2) {
			// Add an order value to each existing cust profile field.
			$ocf = $this->query(
				'SELECT id_field
				FROM {db_prefix}custom_fields
				WHERE field_order = 0',
			);

			// We start counting from 5 because we already have the first 5 fields.
			$fields_count = 5;

			while ($row = Db::$db->fetch_assoc($ocf)) {
				++$fields_count;

				$this->query(
					'UPDATE {db_prefix}custom_fields
					SET field_order = {int:field_count}
					WHERE id_field = {int:id_field}',
					[
						'field_count' => $fields_count,
						'id_field' => $row['id_field'],
					],
				);
			}
			Db::$db->free_result($ocf);

			$this->handleTimeout(++$start);
		}

		return true;
	}
}
