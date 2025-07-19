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

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Reactions extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'reactions';

        $this->columns = [
            'id_reaction' => new Column(
                name: 'id_reaction',
                type: 'smallint',
                unsigned: true,
                not_null: true,
                default: 0,
                auto: true,
            ),
            'name' => new Column(
                name: 'name',
                type: 'varchar',
                size: 255,
                not_null: true,
                default: '',
            )
        ];

        $this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_reaction',
					],
				],
			),
        ];
    }
}