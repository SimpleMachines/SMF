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

class Members2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting members, part 2';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 512;

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
			FROM {db_prefix}members
			LIKE {literal:im_ignore_list}',
		);

		$do_it = Db::$db->num_rows($request) != 0;

		Db::$db->free_result($request);

		while ($do_it) {
			$this->handleTimeout();

			$request = $this->query(
				'SELECT ID_MEMBER, im_ignore_list
				FROM {db_prefix}members
				WHERE im_ignore_list RLIKE {string:regex}
				LIMIT {int:limit}',
				[
					'regex' => '[a-z]',
					'limit' => $this->limit,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$request2 = $this->query(
					'SELECT ID_MEMBER
					FROM {db_prefix}members
					WHERE FIND_IN_SET(memberName, {string:im_ignore_list})',
					[
						'im_ignore_list' => $row['im_ignore_list'],
					],
				);

				$im_ignore_list = '';

				while ($row2 = Db::$db->fetch_assoc($request2)) {
					$im_ignore_list .= ',' . $row2['ID_MEMBER'];
				}

				Db::$db->free_result($request2);

				$this->query(
					'UPDATE {db_prefix}members
					SET im_ignore_list = {string:im_ignore_list}
					WHERE ID_MEMBER = {int:id_member}
					LIMIT 1',
					[
						'im_ignore_list' => substr($im_ignore_list, 1),
						'id_member' => $row['ID_MEMBER'],
						'limit' => 1,
					],
				);
			}

			$num_rows = Db::$db->num_rows($request);

			Db::$db->free_result($request);

			if ($num_rows < $this->limit) {
				break;
			}
		}

		return true;
	}
}
