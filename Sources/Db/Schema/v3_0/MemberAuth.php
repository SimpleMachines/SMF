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
class MemberAuth extends Table
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->name = 'member_auth';

		$this->columns = [
			'id_auth' => new Column(
				name: 'id_auth',
				type: 'int',
				unsigned: true,
				not_null: true,
				auto: true,
			),
			'id_member' => new Column(
				name: 'id_member',
				type: 'mediumint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			// What kind of credential this is, e.g. the name of the mod that
			// owns it. Whoever writes the row decides, and is the only thing
			// that should read it back.
			'type' => new Column(
				name: 'type',
				type: 'varchar',
				size: 20,
				not_null: true,
				default: '',
			),
			// Which configured provider this belongs to, for credential types
			// that can have more than one. 0 when the type has no such concept.
			'id_provider' => new Column(
				name: 'id_provider',
				type: 'int',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			// Whatever identifies this credential to the thing that issued it.
			// Unique per type and provider, so it is what a lookup matches on.
			// Note that MySQL indexes only the first 191 characters of this, so
			// do not store something whose meaning lives beyond that; hash it
			// down to something shorter first if it might.
			'identifier' => new Column(
				name: 'identifier',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			// Anything else the owner needs to keep, as it sees fit.
			'secret_data' => new Column(
				name: 'secret_data',
				type: 'text',
				not_null: true,
			),
			// What the member calls this credential, when they can name it.
			'title' => new Column(
				name: 'title',
				type: 'varchar',
				size: 255,
				not_null: true,
				default: '',
			),
			'date_created' => new Column(
				name: 'date_created',
				type: 'bigint',
				unsigned: true,
				not_null: true,
				default: 0,
			),
			'date_last_used' => new Column(
				name: 'date_last_used',
				type: 'bigint',
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
						'name' => 'id_auth',
					],
				],
			),
			// One credential cannot belong to two members.
			'idx_credential' => new DbIndex(
				name: 'idx_credential',
				type: 'unique',
				columns: [
					[
						'name' => 'type',
					],
					[
						'name' => 'id_provider',
					],
					[
						'name' => 'identifier',
					],
				],
			),
			'idx_id_member' => new DbIndex(
				name: 'idx_id_member',
				columns: [
					[
						'name' => 'id_member',
					],
				],
			),
		];
	}
}
