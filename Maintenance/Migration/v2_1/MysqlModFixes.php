<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class MysqlModFixes extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Fix mods columns';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Config::$db_type === MYSQL_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		// make members mod col nullable
		if ($start <= 0) {
			$request = $this->query(
				'',
				'
				SELECT COLUMN_NAME, COLUMN_TYPE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = {string:db_name} AND  TABLE_NAME = {string:table_name} AND
					COLUMN_DEFAULT IS NULL AND COLUMN_KEY <> {literal:PRI} AND IS_NULLABLE = {literal:NO} AND
					COLUMN_NAME NOT IN ({array_string:ignore_cols})
			',
				[
					'db_name' => Config::$db_name,
					'table_name' => Config::$db_prefix . 'members',
					'ignore_cols' => ['buddy_list', 'signature', 'ignore_boards'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
					$this->query(
						'',
						'
						ALTER TABLE {db_prefix}members
						MODIFY {raw:col_name} {raw:col_type} NULL',
						[
							'col_name' => $row['COLUMN_NAME'],
							'col_type' => $row['COLUMN_TYPE'],
						],
					);
			}

			$this->handleTimeout(++$start);
		}

		// make boards mod col nullable
		if ($start <= 1) {
			$request = $this->query(
				'',
				'
				SELECT COLUMN_NAME, COLUMN_TYPE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = {string:db_name} AND  TABLE_NAME = {string:table_name} AND
					COLUMN_DEFAULT IS NULL AND COLUMN_KEY <> {literal:PRI} AND IS_NULLABLE = {literal:NO} AND
					COLUMN_NAME NOT IN ({array_string:ignore_cols})
			',
				[
					'db_name' => Config::$db_name,
					'table_name' => Config::$db_prefix . 'boards',
					'ignore_cols' => ['description'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
					$this->query(
						'',
						'
						ALTER TABLE {db_prefix}boards
						MODIFY {raw:col_name} {raw:col_type} NULL',
						[
							'col_name' => $row['COLUMN_NAME'],
							'col_type' => $row['COLUMN_TYPE'],
						],
					);
			}

			$this->handleTimeout(++$start);
		}

		// make topics mod col nullable
		if ($start <= 1) {
			$request = $this->query(
				'',
				'
				SELECT COLUMN_NAME, COLUMN_TYPE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = {string:db_name} AND  TABLE_NAME = {string:table_name} AND
					COLUMN_DEFAULT IS NULL AND COLUMN_KEY <> {literal:PRI} AND IS_NULLABLE = {literal:NO} AND
			',
				[
					'db_name' => Config::$db_name,
					'table_name' => Config::$db_prefix . 'topics',
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
					$this->query(
						'',
						'
						ALTER TABLE {db_prefix}topics
						MODIFY {raw:col_name} {raw:col_type} NULL',
						[
							'col_name' => $row['COLUMN_NAME'],
							'col_type' => $row['COLUMN_TYPE'],
						],
					);
			}

			$this->handleTimeout(++$start);
		}

		return true;
	}
}

?>