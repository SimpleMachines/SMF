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

namespace SMF\Maintenance;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * Puts a database back the way an upgrade found it.
 *
 * Everything this needs was written down while the upgrade was running: what
 * each table looked like, the functions its indexes call, the rows themselves
 * in the backup_ tables, and what the settings in Settings.php held. This puts
 * them back in the order they depend on each other.
 *
 * What it does not do is anything outside the database. Files an upgrade
 * changed are not its business, and a forum that has been put back still has
 * the newer code on disk.
 */
class MigrationRollback
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * What was done, a line at a time, for whoever asked for this.
	 */
	public array $log = [];

	/**
	 * @var string
	 *
	 * Why it stopped, if it did.
	 */
	public string $error = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Puts the database back to the state a run found it in.
	 *
	 * @param string $run The run to undo.
	 * @return bool Whether it was undone.
	 */
	public function rollback(string $run): bool
	{
		$definitions = MigrationData::all($run, MigrationData::TYPE_DEFINITION);

		if ($definitions === []) {
			$this->error = 'that run recorded nothing to put back';

			return false;
		}

		$backed_up = MigrationData::all($run, MigrationData::TYPE_BACKUP);

		// The rows are what makes this worth doing. Without them the tables
		// would come back empty, which is worse than leaving things alone.
		$missing = array_diff(array_keys($definitions), array_keys($backed_up));

		if ($missing !== []) {
			$this->error = 'no backup was taken of ' . implode(', ', \array_slice($missing, 0, 5));

			return false;
		}

		// The recorded SQL is the database's own account of itself, and the
		// checks that keep a query from being assembled out of user input have
		// nothing to look at here: they refuse the quoting a CREATE TABLE is
		// full of, and the semicolons inside a function body. The installer and
		// the migrations turn them off around their own DDL for the same
		// reason.
		$checking = Db::$db->disableQueryCheck;
		Db::$db->disableQueryCheck = true;

		foreach ($this->routines($run) as $name => $sql) {
			$this->execute($sql);
			$this->log[] = 'function ' . $name;
		}

		foreach ($definitions as $table => $sql) {
			$this->execute($sql);
			$this->refill($table);
			$this->log[] = 'table ' . $table;
		}

		foreach ($this->added($definitions) as $table) {
			Db::$db->drop_table($table);
			$this->log[] = 'dropped ' . $table;
		}

		Db::$db->disableQueryCheck = $checking;

		$this->restoreSettings($run);

		MigrationData::recordRollback($run);

		return true;
	}

	/**
	 * The runs that could be undone, newest first.
	 *
	 * @return array Rows from the migration_runs table.
	 */
	public function candidates(): array
	{
		if (!MigrationData::exists()) {
			return [];
		}

		$runs = [];

		$request = Db::$db->query(
			'SELECT id_run, version_from, version_to, time_started, time_finished
			FROM {db_prefix}migration_runs
			WHERE time_rolled_back = {int:never}
			ORDER BY time_started DESC',
			[
				'never' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$runs[] = $row;
		}

		Db::$db->free_result($request);

		return $runs;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The functions a run recorded.
	 *
	 * @param string $run The run.
	 * @return array The SQL that creates each, keyed by its signature.
	 */
	private function routines(string $run): array
	{
		return MigrationData::all($run, MigrationData::TYPE_ROUTINE);
	}

	/**
	 * Tables that are here now and were not when the run started.
	 *
	 * The upgrader's own tables are not among them however this is counted:
	 * they are where the answer is being read from, and a rollback that took
	 * them with it could not record that it had happened.
	 *
	 * @param array $definitions What the run recorded, keyed by table name.
	 * @return array Names of the tables to drop, with the prefix on them.
	 */
	private function added(array $definitions): array
	{
		$added = [];

		foreach (Db::$db->list_tables() as $table) {
			if (
				isset($definitions[$table])
				|| str_starts_with($table, 'backup_')
				|| str_starts_with($table, MigrationData::prefix() . 'migration_')
				|| !str_starts_with($table, MigrationData::prefix())
			) {
				continue;
			}

			$added[] = $table;
		}

		return $added;
	}

	/**
	 * Puts a table's rows back from its backup.
	 *
	 * @param string $table Name of the table, with the prefix on it.
	 */
	private function refill(string $table): void
	{
		if (Db::$db->list_tables(false, 'backup_' . $table) === []) {
			return;
		}

		Db::$db->query(
			'INSERT INTO {raw:table}
			SELECT * FROM {raw:backup}',
			[
				'table' => $table,
				'backup' => 'backup_' . $table,
				'db_error_skip' => true,
			],
		);
	}

	/**
	 * Puts the settings in Settings.php back.
	 *
	 * A setting the run found missing is taken out again rather than written
	 * as an empty one, which is what the note beside it is for.
	 *
	 * @param string $run The run.
	 */
	private function restoreSettings(string $run): void
	{
		$settings = MigrationData::all($run, MigrationData::TYPE_SETTING);

		if ($settings === []) {
			return;
		}

		$put_back = [];
		$remove = [];

		foreach ($settings as $name => $noted) {
			$noted = json_decode($noted, true);

			if (!\is_array($noted)) {
				continue;
			}

			if (empty($noted['set'])) {
				$remove[] = $name;
			} else {
				$put_back[$name] = $noted['value'];
			}
		}

		if ($put_back !== []) {
			Config::updateSettingsFile($put_back);
			$this->log[] = 'settings ' . implode(', ', array_keys($put_back));
		}

		if ($remove !== []) {
			Config::updateSettingsFile(array_fill_keys($remove, null), rebuild: true);
			$this->log[] = 'removed ' . implode(', ', $remove);
		}
	}

	/**
	 * Runs SQL that may be more than one statement.
	 *
	 * A recorded definition is a small script rather than a single statement,
	 * and the database layer takes one at a time, so it is split here.
	 *
	 * @param string $sql The SQL to run.
	 */
	private function execute(string $sql): void
	{
		foreach ($this->statements($sql) as $statement) {
			// These are whole statements rather than something built around
			// values, so the checks that keep a query from being assembled out
			// of user input have nothing to look at here and reject the
			// quoting a CREATE TABLE is full of.
			Db::$db->query(
				$statement,
				[
					'security_override' => true,
					'db_error_skip' => true,
				],
			);
		}
	}

	/**
	 * Splits SQL into the statements it is made of.
	 *
	 * Quoting has to be respected while splitting: a function body is one
	 * string, and the semicolons inside it end nothing.
	 *
	 * @param string $sql The SQL.
	 * @return array The statements, without the semicolons between them.
	 */
	private function statements(string $sql): array
	{
		$statements = [];
		$current = '';
		$quote = '';
		$length = \strlen($sql);

		for ($i = 0; $i < $length; $i++) {
			$char = $sql[$i];

			if ($quote !== '') {
				// Inside a dollar quoted string, only its own tag ends it.
				if ($quote[0] === '$') {
					if (substr($sql, $i, \strlen($quote)) === $quote) {
						$current .= $quote;
						$i += \strlen($quote) - 1;
						$quote = '';

						continue;
					}
				} elseif ($char === '\\' && $i + 1 < $length) {
					$current .= $char . $sql[++$i];

					continue;
				} elseif ($char === $quote) {
					$quote = '';
				}

				$current .= $char;

				continue;
			}

			if ($char === "'" || $char === '"' || $char === '`') {
				$quote = $char;
			} elseif ($char === '$' && preg_match('~^\$[A-Za-z_]*\$~', substr($sql, $i), $matches) === 1) {
				$quote = $matches[0];
				$current .= $quote;
				$i += \strlen($quote) - 1;

				continue;
			} elseif ($char === ';') {
				if (trim($current) !== '') {
					$statements[] = trim($current);
				}

				$current = '';

				continue;
			}

			$current .= $char;
		}

		if (trim($current) !== '') {
			$statements[] = trim($current);
		}

		return $statements;
	}
}
