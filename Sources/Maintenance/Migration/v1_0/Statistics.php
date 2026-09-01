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

class Statistics extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating statistics';

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
			SELECT {literal:latestMember}, ID_MEMBER
			FROM {db_prefix}members
			ORDER BY ID_MEMBER DESC
			LIMIT 1',
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {literal:latestRealName}, COALESCE(realName, memberName)
			FROM {db_prefix}members
			ORDER BY ID_MEMBER DESC
			LIMIT 1',
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {literal:maxMsgID}, ID_MSG
			FROM {db_prefix}messages
			ORDER BY ID_MSG DESC
			LIMIT 1',
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {literal:totalMembers}, COUNT(*)
			FROM {db_prefix}members',
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {literal:unapprovedMembers}, COUNT(*)
			FROM {db_prefix}members
			WHERE is_activated = 0
				AND validation_code = {empty}',
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {literal:totalMessages}, COUNT(*)
			FROM {db_prefix}messages',
		);

		$this->query(
			'REPLACE INTO {db_prefix}settings
				(variable, value)
			SELECT {literal:totalTopics}, COUNT(*)
			FROM {db_prefix}topics',
		);

		$this->handleTimeout();

		return true;
	}
}
