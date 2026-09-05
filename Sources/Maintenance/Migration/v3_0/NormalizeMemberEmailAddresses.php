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

class NormalizeMemberEmailAddresses extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Normalizing email addresses';

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

		while (Maintenance::getCurrentStart() < $max) {
			$set = [
				'email_address' => [],
				'email_address_ci' => [],
			];

			$params = [
				'members' => [],
			];

			$request = $this->query(
				'SELECT id_member, email_address
				FROM {db_prefix}members
				WHERE id_member > {int:start}
				ORDER BY id_member ASC
				LIMIT {int:limit}',
				[
					'limit' => $this->limit,
					'start' => Maintenance::getCurrentStart(),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$params['members'][] = (int) $row['id_member'];

				$email = new EmailAddress($row['email_address'], true);

				if ($email->isValid()) {
					// Normalize the domain.
					$set['email_address'][$row['id_member']] = '{string:cs_' . $row['id_member'] . '}';
					$params['cs_' . $row['id_member']] = (string) $email;

					// Get a casefolded version for case-insensitive matching.
					$set['email_address_ci'][$row['id_member']] = '{string:ci_' . $row['id_member'] . '}';
					$params['ci_' . $row['id_member']] = $email->casefolded();
				}
			}

			Db::$db->free_result($request);

			// Build each column's complete SET statement.
			foreach ($set as $column => $to_set) {
				$statement = $column . ' = CASE';

				foreach ($to_set as $id => $value) {
					$statement .= "\n\t\t\t\t\t\t" . 'WHEN id_member = ' . $id . ' THEN ' . $value;
				}

				$statement .= "\n\t\t\t\t\t\t" . 'ELSE ' . $column;
				$statement .= "\n\t\t\t\t\t" . 'END';

				$set[$column] = $statement;
			}

			// Perform the updates.
			$this->query(
				'UPDATE {db_prefix}members
				SET
					' . implode(",\n\t\t\t\t", $set) . '
				WHERE id_member IN ({array_int:members})',
				$params,
			);

			$this->handleTimeout(max($params['members']));
		}

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the maximum value of id_member
	 *
	 * @return int
	 */
	private function getMax(): int
	{
		$request = $this->query(
			'SELECT MAX(id_member)
			FROM {db_prefix}members',
		);

		$row = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		return (int) $row[0];
	}
}
