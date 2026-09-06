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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Db\DatabaseApi as Db;
use SMF\EmailAddress;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class NormalizeBannedEmailAddresses extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Normalizing email addresses in bans';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 1000;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$max = $this->getMax();

		// SMF has only ever allowed the '%' wildcard in email ban
		// patterns, so we don't need to handle '_'.
		$wildcard_replacements = [
			'\\%' => '%',
			'%' => md5('%') . '.org',
		];

		while (Maintenance::getCurrentStart() < $max) {
			$set = [
				'email_address' => [],
			];

			$params = [
				'ids' => [],
			];

			$request = $this->query(
				'SELECT id_ban, email_address
				FROM {db_prefix}ban_items
				WHERE id_ban > {int:start}
				ORDER BY id_ban ASC
				LIMIT {int:limit}',
				[
					'limit' => $this->limit,
					'start' => Maintenance::getCurrentStart(),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$params['ids'][] = (int) $row['id_ban'];

				// Can this pattern resolve to a valid email address once
				// any wildcards are replaced with real strings?
				$test = new EmailAddress(strtr($row['email_address'], $wildcard_replacements));

				// If the pattern is valid, ensure it is casefolded.
				if ($test->isValid()) {
					$set['email_address'][$row['id_ban']] = '{string:ci_' . $row['id_ban'] . '}';

					$params['ci_' . $row['id_ban']] = strtr($test->casefolded(), array_flip($wildcard_replacements));
				}
			}

			Db::$db->free_result($request);

			// Build each column's complete SET statement.
			foreach ($set as $column => $to_set) {
				$statement = $column . ' = CASE';

				foreach ($to_set as $id => $value) {
					$statement .= "\n\t\t\t\t\t\t" . 'WHEN id_ban = ' . $id . ' THEN ' . $value;
				}

				$statement .= "\n\t\t\t\t\t\t" . 'ELSE ' . $column;
				$statement .= "\n\t\t\t\t\t" . 'END';

				$set[$column] = $statement;
			}

			// Perform the updates.
			$this->query(
				'UPDATE {db_prefix}ban_items
				SET
					' . implode(",\n\t\t\t\t", $set) . '
				WHERE id_ban IN ({array_int:ids})',
				$params,
			);

			$this->handleTimeout(max($params['ids']));
		}

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the maximum value of ban_items
	 *
	 * @return int
	 */
	private function getMax(): int
	{
		$request = $this->query(
			'SELECT MAX(id_ban)
			FROM {db_prefix}ban_items',
		);

		$row = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		return (int) $row[0];
	}
}
