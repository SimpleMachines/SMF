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

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class PostgreSQLUnlogged extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'UNLOGGED Table PG 9.1+';

	private array $tables = [
		'log_online',
		'log_floodcontrol',
		'sessions',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return Db::$db->title === POSTGRE_TITLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		$result = $this->query('', 'SHOW server_version_num');

		if ($result !== false) {
			while ($row = Db::$db->fetch_assoc($result)) {
				$pg_version = $row['server_version_num'];
			}
			Db::$db->free_result($result);
		}

		if (!isset($pg_version)) {
			return true;
		}

		foreach($this->tables as $table) {
			if($pg_version >= 90500) {
				$this->query(
					'',
					'
				ALTER TABLE {db_prefix}{raw:table} SET UNLOGGED;',
					[
						'table' => $table,
					],
				);
			} else {
				$this->query(
					'',
					'
				ALTER TABLE {db_prefix}{raw:table} rename to old_{db_prefix}{raw:table};
	
				do
				$$
				declare r record;
				begin
					for r in select * from pg_constraint where conrelid={string:old_table_conrelid}::regclass loop
						execute format({raw:alter_table}, r.conname, {literal:old_} || r.conname);
					end loop;
					for r in select * from pg_indexes where tablename={string:old_table_name} and indexname !~ {string:regex_old} loop
						execute format({string:alter_inex}, r.indexname, {literal:old_} || r.indexname);
					end loop;
				end;
				$$;
	
				create unlogged table {db_prefix}{raw:table} (like old_{db_prefix}{raw:table} including all);
	
				insert into {db_prefix}{raw:table} select * from old_{db_prefix}{raw:table};
	
				drop table old_{db_prefix}{raw:table};',
					[
						'table' => $table,
						'old_table_conrelid' => 'old_' . Db::$db->prefix . $table,
						'old_table_name' => 'old_' . Db::$db->prefix . $table,
						'alter_table' => 'alter table old_' . Db::$db->prefix . $table . ' rename constraint %I to %I',
						'regex_old' => '^old_',
						'alter_inex' => 'alter index %I rename to %I',
					],
				);
			}
		}

		return true;
	}
}

?>