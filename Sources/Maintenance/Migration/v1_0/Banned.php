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
use SMF\Db\Schema;
use SMF\IP;
use SMF\Maintenance\Migration\MigrationBase;

class Banned extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting bans';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		$table = new Schema\v1_0\Topics();
		$structure = $table->getCurrentStructure();

		return array_filter($structure['columns'], fn($c) => $c['name'] === 'ip_low1') === [];
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$inserts = [];

		// IP bans.
		$request = $this->query(
			'SELECT type, value
			FROM {db_prefix}banned
			WHERE type = {literal:ip}',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!preg_match('~^\d{1,3}\.(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)$~', $row['value'])) {
				continue;
			}

			$ip_parts = [
				0 => ['low' => 0, 'high' => 0],
				1 => ['low' => 0, 'high' => 0],
				2 => ['low' => 0, 'high' => 0],
				3 => ['low' => 0, 'high' => 0],
			];

			foreach (IP::ip2range($row['value']) as $low_or_high => $ip) {
				foreach (explode('.', (string) $ip) as $octet_num => $octet) {
					$ip_parts[$octet_num][$low_or_high] = $octet;
				}
			}

			$inserts[] = [
				'ip_ban',
				$ip_parts[0]['low'],
				$ip_parts[0]['high'],
				$ip_parts[1]['low'],
				$ip_parts[1]['high'],
				$ip_parts[2]['low'],
				$ip_parts[2]['high'],
				$ip_parts[3]['low'],
				$ip_parts[3]['high'],
				'',
				'',
				0,
				time(),
				null,
				'full_ban',
				'',
				'Imported from YaBB SE',
			];
		}

		Db::$db->free_result($request);

		// Email bans.
		$request = $this->query(
			'SELECT type, value
			FROM {db_prefix}banned
			WHERE type = {literal:email}',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$inserts[] = [
				'email_ban',
				0,
				0,
				0,
				0,
				0,
				0,
				0,
				0,
				'',
				$row['value'],
				0,
				time(),
				null,
				'full_ban',
				'',
				'Imported from YaBB SE',
			];
		}

		Db::$db->free_result($request);

		// Member bans.
		$request = $this->query(
			'SELECT b.type, b.value, m.ID_MEMBER
			FROM {db_prefix}banned AS b
				INNER JOIN {db_prefix}members AS m ON (b.value = m.memberName)
			WHERE b.type = {literal:username}',
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$inserts[] = [
				'user_ban',
				0,
				0,
				0,
				0,
				0,
				0,
				0,
				0,
				'',
				'',
				$row['ID_MEMBER'],
				time(),
				null,
				'full_ban',
				'',
				'Imported from YaBB SE',
			];
		}

		Db::$db->free_result($request);

		// Drop the old table.
		Db::$db->drop_table(Config::$db_prefix . 'banned');

		// Create the new table.
		$table = new Schema\v1_0\Banned();
		$table->create();

		// Insert the data.
		if (!empty($inserts)) {
			$columns = [];

			foreach ($table->columns as $column) {
				if ($column->name === 'ID_BAN') {
					continue;
				}

				$columns[$column->name] = str_contains($column->type, 'char') || str_contains($column->type, 'text') ? 'string' : 'int';
			}

			Db::$db->insert(
				method: '',
				table: '{db_prefix}banned',
				columns: $columns,
				data: $inserts,
				keys: [],
			);
		}

		return true;
	}
}
