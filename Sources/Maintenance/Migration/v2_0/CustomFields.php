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
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class CustomFields extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding custom profile fields';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table exists and is structured correctly.
		$table = new Schema\v2_0\CustomFields();
		$table->normalize();

		if (isset(Config::$modSettings['smfVersion'])) {
			$smfVersion = str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion']));

			// Enhance the privacy settings for custom fields.
			if (version_compare($smfVersion, '2.0.beta.1', '<=')) {
				$this->query(
					'UPDATE {db_prefix}custom_fields
					SET private = 2
					WHERE private = 1',
					[],
				);
			} elseif (version_compare($smfVersion, '2.0.beta.4', '<')) {
				$this->query(
					'UPDATE {db_prefix}custom_fields
					SET private = 3
					WHERE private = 2',
					[],
				);
			}

			// Ensure that the display fields are set up correctly.
			if (
				version_compare($smfVersion, '2.0.beta.1', '<=')
				&& isset(Config::$modSettings['displayFields'])
				&& @unserialize(Config::$modSettings['displayFields']) == false
			) {
				$request = $this->query(
					'SELECT col_name, field_name, bbc
					FROM {db_prefix}custom_fields
					WHERE show_display = 1
						AND active = 1
						AND private != 2',
					[],
				);

				$fields = [];

				while ($row = Db::$db->fetch_assoc($request)) {
					$fields[] = [
						'c' => strtr($row['col_name'], ['|' => '', ';' => '']),
						'f' => strtr($row['field_name'], ['|' => '', ';' => '']),
						'b' => ($row['bbc'] ? '1' : '0'),
					];
				}

				Db::$db->free_result($request);

				Config::updateModSettings([
					'displayFields' => Db::$db->escape_string(serialize($fields)),
				]);
			}
		}

		$this->handleTimeout();

		return true;
	}
}
