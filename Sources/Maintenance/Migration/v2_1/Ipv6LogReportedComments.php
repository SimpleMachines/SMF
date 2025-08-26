<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;

class Ipv6LogReportedComments extends Ipv6Base
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Update log_reported_comments member_ip with ipv6 support';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function __construct()
	{
		if (Db::$db->title !== POSTGRE_TITLE) {
			$this->name .= ' without converting';
		}
	}

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v2_1\LogFloodcontrol();
		$existing_structure = $table->getCurrentStructure();

		if (Db::$db->title === POSTGRE_TITLE) {
			return $existing_structure['columns']['ip']['type'] !== 'inet';
		}

		return $existing_structure['columns']['ip']['type'] !== 'varbinary';
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v2_1\LogFloodcontrol();

		return $this->convertWithNoDataPreservation($table, 'ip');
	}
}
