<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v2_1;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class PmLabeledMessages extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'pm_labeled_messages';

		$this->columns = [
			'id_label' => new Column(
				name: 'id_label',
				type: 'int',
				unsigned: true,
				default: 0,
			),
			'id_pm' => new Column(
				name: 'id_pm',
				type: 'int',
				unsigned: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_label',
					],
					[
						'name' => 'id_pm',
					],
				],
			),
		];

		parent::__construct();
	}
}
