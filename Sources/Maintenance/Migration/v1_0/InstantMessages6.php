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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class InstantMessages6 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting instant messages, part 6';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SHOW COLUMNS
			FROM {db_prefix}instant_messages
			LIKE {literal:readBy}',
		);

		$do_it = $request !== false;

		if ($do_it) {
			$adv_im = Db::$db->num_rows($request) == 0;
			Db::$db->free_result($request);

			$this->query(
				'INSERT IGNORE INTO {db_prefix}im_recipients
					(ID_PM, ID_MEMBER, bcc, is_read, deleted)
				SELECT ID_PM, ID_MEMBER_TO, 0, IF({raw:col} != 0, 1, 0), IF(deletedBy = {literal:1}, 1, 0)
				FROM {db_prefix}instant_messages',
				[
					'col' => !$adv_im ? 'readBy' : 'alerted',
				],
			);
		}

		$this->query(
			'UPDATE IGNORE {db_prefix}instant_messages
			SET deletedBySender = 1
			WHERE deletedBy = 0',
		);

		$this->handleTimeout();

		return true;
	}
}
