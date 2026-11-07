<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SMF\Config;
use SMF\Db\APIs\MySQL;
use SMF\Db\APIs\PostgreSQL;

#[CoversClass(MySQL::class)]
#[CoversClass(PostgreSQL::class)]
class BoardsCountPostsBackcompatTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * While installing or upgrading, queries naming count_posts are not
	 * rewritten.
	 *
	 * The upgrader meets the boards table while it still has count_posts, and
	 * its queries mean that column.
	 *
	 * Its own process, because SMF_INSTALLING cannot be undefined again.
	 *
	 * Expected: with SMF_INSTALLING defined, on both engines,
	 *           backcompatQuoteFixes() returns each query in upgraderQueries()
	 *           unchanged, and backcompatInsertFixes() returns a row naming
	 *           count_posts unchanged.
	 * Guards:   rewriting the upgrader's queries to name posts_count made the
	 *           backup step fail with "Unknown column 'posts_count' in 'field
	 *           list'", and would have made the migration read the value it was
	 *           in the middle of writing.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/59b22b12e Introduced by "Replace count_posts (where 0 = true) with posts_count (where 1 = true)"
	 * @link https://github.com/SimpleMachines/SMF/commit/a4aec80eb Introduced by "Implements backcompatInsertFixes() in database APIs"
	 * @link https://github.com/SimpleMachines/SMF/pull/9677
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testTheUpgraderGetsTheQueriesItWrote(): void
	{
		\define('SMF_INSTALLING', 1);

		foreach (self::engineProvider() as $engine => [$class]) {
			foreach (self::upgraderQueries() as $label => $query) {
				$this->assertSame($query, $this->quoteFixes($class, $query), $engine . ', ' . $label);
			}

			[$columns, $data, $keys] = $this->insertFixes($class);

			$this->assertSame(['id_board' => 'int', 'count_posts' => 'int'], $columns, $engine);
			$this->assertSame([[1, 0]], $data, $engine);
			$this->assertSame(['count_posts'], $keys, $engine);
		}
	}

	/**
	 * A legacy query is still rewritten for a mod that sends one.
	 *
	 * Expected: each case in legacyQueryProvider() has its count_posts
	 *           reference rewritten by backcompatQuoteFixes() to the new
	 *           posts_count column and its inverted logic.
	 */
	#[DataProvider('legacyQueryProvider')]
	public function testALegacyQueryIsStillRewrittenForAModThatSendsOne(string $class, string $query, string $expected): void
	{
		$this->assertSame($expected, $this->quoteFixes($class, $query));
	}

	/**
	 * A legacy insert is still rewritten for a mod that sends one.
	 *
	 * Expected: for each engine in engineProvider(), backcompatInsertFixes()
	 *           turns the column count_posts, its value and its key into
	 *           posts_count with the value inverted.
	 */
	#[DataProvider('engineProvider')]
	public function testALegacyInsertIsStillRewrittenForAModThatSendsOne(string $class): void
	{
		[$columns, $data, $keys] = $this->insertFixes($class);

		$this->assertSame(['id_board' => 'int', 'posts_count' => 'int'], $columns);
		$this->assertSame([[1, 1]], $data);
		$this->assertSame(['posts_count'], $keys);
	}

	/**
	 * Nothing is rewritten when backward compatibility is off.
	 *
	 * Expected: for each engine in engineProvider(), backcompatQuoteFixes()
	 *           returns 'SELECT id_board, count_posts FROM smf_boards'
	 *           unchanged.
	 */
	#[DataProvider('engineProvider')]
	public function testNothingIsRewrittenWithoutBackwardCompatibility(string $class): void
	{
		Config::$backward_compatibility = 0;

		$query = 'SELECT id_board, count_posts FROM smf_boards';

		$this->assertSame($query, $this->quoteFixes($class, $query));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string}>
	 */
	public static function engineProvider(): array
	{
		return [
			'MySQL' => [MySQL::class],
			'PostgreSQL' => [PostgreSQL::class],
		];
	}

	/**
	 * Queries an SMF 2.1 mod sends to a forum that has finished upgrading.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function legacyQueryProvider(): array
	{
		$queries = [
			'a selected column comes back under its old name and logic' => [
				'SELECT id_board, count_posts FROM smf_boards',
				'SELECT id_board, posts_count, (1 - posts_count) AS count_posts FROM smf_boards',
			],
			'a comparison is inverted along with the column' => [
				'SELECT id_board FROM smf_boards WHERE count_posts = 0',
				'SELECT id_board FROM smf_boards WHERE posts_count = 1',
			],
			'an update names the new column' => [
				'UPDATE smf_boards SET count_posts = 1',
				'UPDATE smf_boards SET posts_count = 0',
			],
		];

		$cases = [];

		foreach (self::engineProvider() as $engine => [$class]) {
			foreach ($queries as $label => [$query, $expected]) {
				$cases[$engine . ', ' . $label] = [$class, $query, $expected];
			}
		}

		return $cases;
	}

	/**
	 * Queries the upgrader sends while the old column is still in the table.
	 *
	 * @return array<string, string>
	 */
	public static function upgraderQueries(): array
	{
		return [
			'the backup step copying the table' => 'INSERT INTO backup_smf_boards (id_board, count_posts) SELECT id_board, count_posts FROM smf_boards',
			'the migration filling in the new column' => 'UPDATE smf_boards SET posts_count = CASE WHEN count_posts = 0 THEN 1 ELSE 0 END',
		];
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		// Both of the fixes under test read these directly.
		Config::$db_prefix = 'smf_';
		Config::$backward_compatibility = 1;
	}

	protected function tearDown(): void
	{
		// A typed static cannot be put back to its uninitialised state, and
		// every reader of this one asks empty() of it, so zero is as good.
		Config::$backward_compatibility = 0;
	}

	/**
	 * Calls the protected query fixer.
	 */
	private function quoteFixes(string $class, string $query): string
	{
		return (new \ReflectionMethod($class, 'backcompatQuoteFixes'))
			->invoke($this->api($class), $query);
	}

	/**
	 * Calls the protected insert fixer with a row naming the old column.
	 *
	 * @return array{array<string, string>, array<int, array<int, int>>, array<int, string>}
	 */
	private function insertFixes(string $class): array
	{
		return (new \ReflectionMethod($class, 'backcompatInsertFixes'))
			->invoke(
				$this->api($class),
				'smf_boards',
				['id_board' => 'int', 'count_posts' => 'int'],
				[[1, 0]],
				['count_posts'],
			);
	}

	/**
	 * A database API object with no connection behind it, since the fixes only
	 * need the table prefix.
	 */
	private function api(string $class): MySQL|PostgreSQL
	{
		$db = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
		$db->prefix = 'smf_';

		return $db;
	}
}
