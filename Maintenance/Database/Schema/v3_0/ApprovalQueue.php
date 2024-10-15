<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Database\Schema\v3_0;

use SMF\Maintenance\Database\Schema\Column;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class ApprovalQueue extends Table
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Data used to populate the table during install.
	 */
	public array $initial_data = [];

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

?>