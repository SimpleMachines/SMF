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
use SMF\Maintenance\Database\Schema\DbIndex;
use SMF\Maintenance\Database\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class UserLikes extends Table
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
		$this->name = 'user_likes';

		$this->columns = [
		'id_member' => new Column(
				name: :'id_member',
				type: 'mediumint',
				unsigned: true,
				default: 0,
			),
		'content_type' => new Column(
				name: :'content_type',
				type: 'char',
				size: 6,
				default: '',
			),
		'content_id' => new Column(
				name: :'content_id',
				type: 'int',
				unsigned: true,
				default: 0,
			),
		'like_time' => new Column(
				name: :'like_time',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					'content_id',
					'content_type',
					'id_member',
				],
			),
		'content' => new DbIndex(
				name: 'content',
				columns: [
					'content_id',
					'content_type',
				],
			),
		'liker' => new DbIndex(
				name: 'liker',
				columns: [
					'id_member',
				],
			),
		];
	}
}

?>