<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\Indices;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class Mentions extends Table
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
		$this->name = 'mentions';

		$this->columns = [
			new Column(
				name: 'content_id',
				type: 'int',
				default: 0,
			),
			new Column(
				name: 'content_type',
				type: 'varchar',
				size: 10,
				default: '',
			),
			new Column(
				name: 'id_mentioned',
				type: 'int',
			),
			new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
			),
			new Column(
				name: 'time',
				type: 'int',
				not_null: true,
			),
		];

		$this->indexes = [
			new Indices(
				type: 'primary',
				columns: [
					'content_id',
					'content_type',
					'id_mentioned',
				],
			),
			new Indices(
				name: 'content',
				columns: [
					'content_id',
					'content_type',
				],
			),
			new Indices(
				name: 'mentionee',
				columns: [
					'id_member',
				],
			),
		];
	}
}

?>