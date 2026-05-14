<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class ApprovalQueue extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'approval_queue';

		$this->columns = [
			'id_msg' => new Column(
				name: 'id_msg',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_attach' => new Column(
				name: 'id_attach',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'id_event' => new Column(
				name: 'id_event',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];
	}
}
