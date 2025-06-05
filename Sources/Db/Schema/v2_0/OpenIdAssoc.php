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

namespace SMF\Db\Schema\v2_0;

use SMF\Db\Schema\Column;
use SMF\Db\Schema\DbIndex;
use SMF\Db\Schema\Table;

/**
 * Defines all the properties for a database table.
 */
class OpenIdAssoc extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'openid_assoc';

		$this->columns = [
			'server_url' => new Column(
				name: 'server_url',
				type: 'text',
				not_null: true,
			),
			'handle' => new Column(
				name: 'handle',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'secret' => new Column(
				name: 'secret',
				type: 'text',
				not_null: true,
			),
			'issued' => new Column(
				name: 'issued',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'expires' => new Column(
				name: 'expires',
				type: 'int',
				not_null: true,
				default: 0,
			),
			'assoc_type' => new Column(
				name: 'assoc_type',
				type: 'varchar',
				size: 64,
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'server_url',
						'size' => 125,
					],
					[
						'name' => 'handle',
						'size' => 125,
					],
				],
			),
			'expires' => new DbIndex(
				name: 'expires',
				columns: [
					[
						'name' => 'expires',
					],
				],
			),
		];

		parent::__construct();
	}
}
