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

namespace SMF\Db\Schema\v3_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class UserLikes extends Table
{
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
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'content_type' => new Column(
				name: 'content_type',
				type: 'char',
				size: 6,
				not_null: true,
				default: '',
			),
			'content_id' => new Column(
				name: 'content_id',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'like_time' => new Column(
				name: 'like_time',
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
					[
						'name' => 'content_id',
					],
					[
						'name' => 'content_type',
					],
					[
						'name' => 'id_member',
					],
				],
			),
			'content' => new DbIndex(
				name: 'content',
				columns: [
					[
						'name' => 'content_id',
					],
					[
						'name' => 'content_type',
					],
				],
			),
			'liker' => new DbIndex(
				name: 'liker',
				columns: [
					[
						'name' => 'id_member',
					],
				],
			),
		];

		parent::__construct();
	}
}
