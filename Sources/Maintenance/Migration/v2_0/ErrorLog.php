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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class ErrorLog extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Upgrading the error log';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 500;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT MAX(*)
			FROM {db_prefix}log_errors',
			[],
		);

		list($max) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		$is_done = false;

		while (!$is_done) {
			$start = Maintenance::getCurrentStart();
			$this->handleTimeout();

			$request = $this->query(
				'SELECT id_error, message, file, line
				FROM {db_prefix}log_errors
				WHERE id_error >= {int:start}
		 		ORDER BY id_error
		 		LIMIT {int:limit}',
				[
					'start' => $start,
					'limit' => $this->limit,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$is_done = $row['id_error'] == $max;
				Maintenance::setCurrentStart($row['id_attach'] + 1);

				preg_match('~<br />(%1\$s: )?([\w\. \\\\/\-_:]+)<br />(%2\$s: )?([\d]+)~', $row['message'], $matches);

				if (!empty($matches[2]) && !empty($matches[4]) && empty($row['file']) && empty($row['line'])) {
					$row['file'] = Db::$db->escape_string(str_replace('\\', '/', $matches[2]));
					$row['line'] = (int) $matches[4];
					$row['message'] = Db::$db->escape_string(str_replace($matches[0], '', $row['message']));

					$this->query(
						'UPDATE {db_prefix}log_errors
						SET file = SUBSTRING({string:file}, 1, 255),
							line = {string:line},
							message = SUBSTRING({string:message}, 1, 65535)
						WHERE id_error = {int:id_error}',
						$row,
					);
				}
			}

			Db::$db->free_result($request);
		}

		return true;
	}
}
