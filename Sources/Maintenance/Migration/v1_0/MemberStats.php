<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Maintenance\Migration\MigrationBase;

class MemberStats extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting member statistics';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {string:var}, ID_MEMBER
			FROM {db_prefix}members
			ORDER BY ID_MEMBER DESC
			LIMIT {int:limit}',
			[
				'var' => 'latestMember',
				'limit' => 1,
			],
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {string:var}, IFNULL(realName, memberName)
			FROM {db_prefix}members
			ORDER BY ID_MEMBER DESC
			LIMIT {int:limit}',
			[
				'var' => 'latestRealName',
				'limit' => 1,
			],
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {string:var}, ID_MSG
			FROM {db_prefix}messages
			ORDER BY ID_MSG DESC
			LIMIT {int:limit}',
			[
				'var' => 'maxMsgID',
				'limit' => 1,
			],
		);

		$this->handleTimeout();

		return true;
	}
}
