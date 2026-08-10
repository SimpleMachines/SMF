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
class AuthProviders extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'auth_providers';

		$this->columns = [
			'id_provider' => new Column(
				name: 'id_provider',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			// Which kind of provider this is. Only 'oidc' means anything today;
			// the column is here so a second protocol does not need a new table.
			'provider_type' => new Column(
				name: 'provider_type',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: 'oidc',
			),
			// What the button on the login form says.
			'title' => new Column(
				name: 'title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			// The issuer URL. Everything else is discovered from it.
			'issuer' => new Column(
				name: 'issuer',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'client_id' => new Column(
				name: 'client_id',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'client_secret' => new Column(
				name: 'client_secret',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'scopes' => new Column(
				name: 'scopes',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: 'openid email profile',
			),
			'enabled' => new Column(
				name: 'enabled',
				type: 'tinyint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			// Not called 'order': that is reserved on both engines.
			'provider_order' => new Column(
				name: 'provider_order',
				type: 'smallint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			// JSON. The cached discovery document lives here, along with the
			// per provider policy switches.
			'settings' => new Column(
				name: 'settings',
				type: 'text',
				not_null: true,
			),
		];

		$this->indexes = [
			'primary' => new DbIndex(
				type: 'primary',
				columns: [
					[
						'name' => 'id_provider',
					],
				],
			),
			'idx_enabled' => new DbIndex(
				name: 'idx_enabled',
				columns: [
					[
						'name' => 'enabled',
					],
				],
			),
		];
	}
}
