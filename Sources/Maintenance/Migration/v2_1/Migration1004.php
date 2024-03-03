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
use SMF\Maintenance\Migration;

class Migration0001 extends Migration
{
	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Converting collapsed categories';

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$tables = Db::$db->list_tables();

		return in_array(Config::$db_prefix . 'collapsed_categories', $tables);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$request = Db::$db->query('', '
			SELECT id_member, id_cat
			FROM {db_prefix}collapsed_categories');

		$inserts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$inserts[] = [$row['id_member'], 1, 'collapse_category_' . $row['id_cat'], $row['id_cat']];
		}
		Db::$db->free_result($request);

		$result = false;

		if (!empty($inserts)) {
			$result = Db::$db->insert(
				'replace',
				'{db_prefix}themes',
				['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'],
				$inserts,
				['id_theme', 'id_member', 'variable'],
			);
		}

		if ($result !== false) {
			Db::$db->drop_table('{db_prefix}collapsed_categories');
		}

		return true;
	}
}

?>