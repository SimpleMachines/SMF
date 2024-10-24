<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Maintenance\Migration\MigrationBase;

class Ipv6LogAction extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Update log_action ip with ipv6 support';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function __construct()
	{

		if (Config::$db_type !== POSTGRE_TITLE) {
			$this->name .= ' without converting';
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogActions();
		$existing_structure = $table->getCurrentStructure();

		if (Config::$db_type === POSTGRE_TITLE) {
			return $existing_structure['columns']['ip']['type'] !== 'inet';
		}

		return $existing_structure['columns']['ip']['type'] !== 'varbinary';
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$table = new \SMF\Maintenance\Database\Schema\v2_1\LogActions();
		$existing_structure = $table->getCurrentStructure();

		if (Config::$db_type === POSTGRE_TITLE) {
			$this->query('', '
			ALTER TABLE {db_prefix}log_actions
				ALTER ip DROP not null,
				ALTER ip DROP default,
				ALTER ip TYPE inet USING migrate_inet(ip);
			');
		} else {
			foreach ($table->columns as $column) {
				if ($column->name === 'ip' && $existing_structure['columns'][$column->name]['type'] !== 'varbinary') {
					$table->dropColumn($column);
					$table->addColumn($column);
					continue;
				}
			}
		}

		return true;
	}
}

?>