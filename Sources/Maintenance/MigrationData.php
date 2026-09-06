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

use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\v3_0\MigrationData as MigrationDataTable;
use SMF\Db\Schema\v3_0\MigrationRuns as MigrationRunsTable;

/**
 * What a migration needs to still know later.
 *
 * A migration runs once and is gone, but some of what it knew is only
 * available before it acted: the shape a table had before it was altered, the
 * value a column held before it was dropped. Recording that in the database
 * rather than in the migration means a later step, a later run, or an admin
 * putting a forum back together can still reach it.
 *
 * Entries are grouped by the run that wrote them. An upgrade that is
 * interrupted and started again is the same run and reads back what it already
 * wrote; an upgrade to a later version months afterwards is a different one and
 * leaves the first alone. That is what keeps a retry from recording the state
 * of a database the migrations have already changed.
 */
class MigrationData
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * The definition a table had before the migrations touched it, as the SQL
	 * that would build it again.
	 */
	public const TYPE_DEFINITION = 'definition';

	/**
	 * That a table has been copied to a backup_ one by this run, so that a run
	 * which starts again does not copy over the copy it already made.
	 */
	public const TYPE_BACKUP = 'backup';

	/**
	 * A function the database held before the migrations reach it, as the SQL
	 * that would create it again. A table's definition can lean on one -- an
	 * index over an expression names the function it calls -- so the two are
	 * only useful together.
	 */
	public const TYPE_ROUTINE = 'routine';

	/**
	 * What a setting in Settings.php held before the upgrade changed it,
	 * as JSON, with whether it was there at all.
	 */
	public const TYPE_SETTING = 'setting';

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Writes one entry, replacing any the same run already holds under this
	 * type and key.
	 *
	 * @param string $run The run to record this against.
	 * @param string $migration The migration or step doing the recording.
	 * @param string $type What kind of thing this is, e.g. self::TYPE_DEFINITION.
	 * @param string $key What it is about, e.g. the name of a table.
	 * @param string $data The thing to remember.
	 * @return bool Whether it was written.
	 */
	public static function save(string $run, string $migration, string $type, string $key, string $data): bool
	{
		if (!self::exists()) {
			return false;
		}

		self::forget($run, $type, $key);

		Db::$db->insert(
			'insert',
			'{db_prefix}migration_data',
			[
				'id_run' => 'string-36',
				'migration' => 'string-255',
				'data_type' => 'string-30',
				'data_key' => 'string-255',
				'data' => 'string',
				'time_added' => 'int',
			],
			[
				[$run, $migration, $type, $key, $data, time()],
			],
			['id_entry'],
		);

		return true;
	}

	/**
	 * Reads one entry back.
	 *
	 * @param string $run The run that wrote it.
	 * @param string $type What kind of thing it is.
	 * @param string $key What it is about.
	 * @return string|null The thing, or null if this run never recorded it.
	 */
	public static function get(string $run, string $type, string $key): ?string
	{
		if (!self::exists()) {
			return null;
		}

		$request = Db::$db->query(
			'SELECT data
			FROM {db_prefix}migration_data
			WHERE id_run = {string:run}
				AND data_type = {string:type}
				AND data_key = {string:key}
			LIMIT 1',
			[
				'run' => $run,
				'type' => $type,
				'key' => $key,
			],
		);

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return $row === false || $row === null ? null : (string) $row['data'];
	}

	/**
	 * Reads back everything one run recorded of a kind.
	 *
	 * @param string $run The run that wrote them.
	 * @param string $type What kind of thing they are.
	 * @return array The things, keyed by what each is about.
	 */
	public static function all(string $run, string $type): array
	{
		if (!self::exists()) {
			return [];
		}

		$entries = [];

		$request = Db::$db->query(
			'SELECT data_key, data
			FROM {db_prefix}migration_data
			WHERE id_run = {string:run}
				AND data_type = {string:type}
			ORDER BY data_key',
			[
				'run' => $run,
				'type' => $type,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$entries[$row['data_key']] = (string) $row['data'];
		}

		Db::$db->free_result($request);

		return $entries;
	}

	/**
	 * Removes one entry.
	 *
	 * @param string $run The run that wrote it.
	 * @param string $type What kind of thing it is.
	 * @param string $key What it is about.
	 */
	public static function forget(string $run, string $type, string $key): void
	{
		if (!self::exists()) {
			return;
		}

		Db::$db->query(
			'DELETE FROM {db_prefix}migration_data
			WHERE id_run = {string:run}
				AND data_type = {string:type}
				AND data_key = {string:key}',
			[
				'run' => $run,
				'type' => $type,
				'key' => $key,
			],
		);
	}

	/**
	 * The run that is under way, if there is one.
	 *
	 * A run is under way until something says it finished, so a process that
	 * was killed leaves its row behind and the next attempt finds it. That is
	 * what makes a restart the same run rather than a new one, and it is why
	 * this is asked of the database rather than of the progress data in
	 * Settings.php, which the command line never writes.
	 *
	 * @return string The run's id, or an empty string if none is open.
	 */
	public static function currentRun(): string
	{
		if (!self::exists()) {
			return '';
		}

		$request = Db::$db->query(
			'SELECT id_run
			FROM {db_prefix}migration_runs
			WHERE time_finished = {int:unfinished}
			ORDER BY time_started DESC
			LIMIT 1',
			[
				'unfinished' => 0,
			],
		);

		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return $row === false || $row === null ? '' : (string) $row['id_run'];
	}

	/**
	 * Opens a run.
	 *
	 * @param string $run The id to give it.
	 * @param string $from The version the forum is on now.
	 * @param int $member Who started it, if that is known.
	 * @return bool Whether it was recorded.
	 */
	public static function startRun(string $run, string $from, int $member = 0): bool
	{
		if (!self::exists()) {
			return false;
		}

		Db::$db->insert(
			'ignore',
			'{db_prefix}migration_runs',
			[
				'id_run' => 'string-36',
				'version_from' => 'string-20',
				'id_member' => 'int',
				'time_started' => 'int',
				'time_updated' => 'int',
			],
			[
				[$run, $from, $member, time(), time()],
			],
			['id_run'],
		);

		return true;
	}

	/**
	 * Records how far a run has got.
	 *
	 * The upgrader keeps its place in the query string, which is gone the
	 * moment the process is. This is the copy that outlives it.
	 *
	 * @param string $run The run.
	 * @param int $step Which step it is on.
	 * @param int $substep Which substep of that step.
	 * @param int $start How far into the substep.
	 */
	public static function recordPosition(string $run, int $step, int $substep, int $start): void
	{
		if ($run === '' || !self::exists()) {
			return;
		}

		Db::$db->query(
			'UPDATE {db_prefix}migration_runs
			SET step = {int:step},
				substep = {int:substep},
				substep_start = {int:start},
				time_updated = {int:now}
			WHERE id_run = {string:run}',
			[
				'step' => $step,
				'substep' => $substep,
				'start' => $start,
				'now' => time(),
				'run' => $run,
			],
		);
	}

	/**
	 * Closes a run, so that the next one is a new one.
	 *
	 * @param string $run The run.
	 * @param string $to The version the forum is on now.
	 */
	public static function finishRun(string $run, string $to): void
	{
		if ($run === '' || !self::exists()) {
			return;
		}

		Db::$db->query(
			'UPDATE {db_prefix}migration_runs
			SET version_to = {string:to},
				time_updated = {int:now},
				time_finished = {int:now}
			WHERE id_run = {string:run}',
			[
				'to' => $to,
				'now' => time(),
				'run' => $run,
			],
		);
	}

	/**
	 * Notes that a run has been undone.
	 *
	 * The row stays where it is. What the run did is still worth knowing about
	 * after it has been put back, and a forum that was upgraded and rolled back
	 * is a different thing from one that was never upgraded.
	 *
	 * @param string $run The run that was undone.
	 */
	public static function recordRollback(string $run): void
	{
		if ($run === '' || !self::exists()) {
			return;
		}

		Db::$db->query(
			'UPDATE {db_prefix}migration_runs
			SET time_rolled_back = {int:now}
			WHERE id_run = {string:run}',
			[
				'now' => time(),
				'run' => $run,
			],
		);
	}

	/**
	 * Whether there is anywhere to record this.
	 *
	 * The table arrives with 3.0, so anything asking before it has been created
	 * has nowhere to write. A migration that cannot record something carries on
	 * rather than ending the upgrade, so this is asked rather than left to the
	 * query to discover.
	 *
	 * Only a yes is remembered. A no is the answer until the table is made, and
	 * the run that makes it is usually the one asking.
	 *
	 * @return bool Whether the table is there.
	 */
	public static function exists(): bool
	{
		static $exists = false;

		return $exists = $exists || ((new MigrationDataTable())->exists(true) && (new MigrationRunsTable())->exists(true));
	}

	/**
	 * Makes the table if it is not there yet.
	 *
	 * The upgrader reaches the backup step before it reaches the migrations
	 * that build the 3.0 schema, so anything wanting to record what a table
	 * looked like beforehand has to ask for this first.
	 *
	 * @return bool Whether there is a table to write to now.
	 */
	public static function ensure(): bool
	{
		if (self::exists()) {
			return true;
		}

		(new MigrationDataTable())->normalize();
		(new MigrationRunsTable())->normalize();

		return self::exists();
	}
}
