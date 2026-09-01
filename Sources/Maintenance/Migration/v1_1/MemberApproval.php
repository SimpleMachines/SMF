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

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Config;
use SMF\Maintenance\Migration\MigrationBase;

class MemberApproval extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating member approval';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return version_compare(
			str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
			'1.1',
			'<',
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'UPDATE {db_prefix}members
			SET is_activated = 3
			WHERE validation_code = {string:empty}
				AND is_activated = 0',
			[
				'empty' => '',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
