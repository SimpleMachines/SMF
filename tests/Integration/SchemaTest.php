<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema\Table;

/**
 * Compares the schema SMF declares in PHP against the one it actually built.
 *
 * The schema for 3.0 lives in Sources/Db/Schema/v3_0/ as 70-odd classes, and the
 * installer creates the database from them. Nothing checks afterwards that the
 * two still agree, and a query naming a column that is no longer there fails at
 * runtime only - inside a background task, it retries forever and takes down
 * unrelated page loads.
 *
 * Both engines get their own DDL out of the same declarations, so this is worth
 * running twice.
 */
#[CoversNothing]
class SchemaTest extends IntegrationTestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testTheSchemaDeclaresTables(): void
	{
		// Guards the two tests below: if getAll() ever returns nothing they
		// would both pass by iterating over an empty list.
		$this->assertNotEmpty(Table::getAll('v3_0'), 'the v3_0 schema declares no tables at all');
	}

	public function testEveryDeclaredTableExists(): void
	{
		$existing = array_map(
			static fn($table): string => strtolower($table),
			Db::$db->list_tables(),
		);

		$missing = [];

		foreach (Table::getAll('v3_0') as $table) {
			if (!\in_array(strtolower(Db::$db->prefix . $table->name), $existing, true)) {
				$missing[] = $table->name;
			}
		}

		$this->assertSame([], $missing, 'tables the schema declares but the database does not have');
	}

	public function testEveryDeclaredColumnExists(): void
	{
		$missing = [];

		foreach (Table::getAll('v3_0') as $table) {
			$columns = array_map(
				static fn($column): string => strtolower($column),
				Db::$db->list_columns('{db_prefix}' . $table->name),
			);

			// A table that is missing entirely is the other test's business.
			if ($columns === []) {
				continue;
			}

			foreach ($table->columns as $column) {
				if (!\in_array(strtolower($column->name), $columns, true)) {
					$missing[] = $table->name . '.' . $column->name;
				}
			}
		}

		$this->assertSame([], $missing, 'columns the schema declares but the database does not have');
	}

	/**
	 * The reverse direction, which is the one that catches a migration that
	 * dropped a column in the schema but not in the database, or a table left
	 * behind by an older version.
	 */
	public function testTheDatabaseHasNoColumnsTheSchemaDoesNotDeclare(): void
	{
		$unexpected = [];

		foreach (Table::getAll('v3_0') as $table) {
			$declared = array_map(
				static fn($column): string => strtolower($column->name),
				$table->columns,
			);

			foreach (Db::$db->list_columns('{db_prefix}' . $table->name) as $column) {
				if (!\in_array(strtolower($column), $declared, true)) {
					$unexpected[] = $table->name . '.' . $column;
				}
			}
		}

		$this->assertSame([], $unexpected, 'columns the database has that the schema does not declare');
	}
}
