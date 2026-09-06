<?php

declare(strict_types=1);

namespace SMF\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SMF\Maintenance\MigrationRollback;

#[CoversClass(MigrationRollback::class)]
class MigrationRollbackTest extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	public function testAFunctionBodyIsOneStatement(): void
	{
		// PostgreSQL function bodies are dollar quoted and full of semicolons.
		// Splitting on those would hand the database a fragment that parses as
		// nothing, and the index leaning on the function would never come back.
		$sql = <<<'SQL'
			CREATE FUNCTION indexable_month_day(date) RETURNS date AS $$
				SELECT make_date(1004, EXTRACT(MONTH FROM $1)::int, EXTRACT(DAY FROM $1)::int);
			$$ LANGUAGE SQL IMMUTABLE RETURNS NULL ON NULL INPUT;
			CREATE INDEX idx_birthdate ON smf_members (indexable_month_day(birthdate));
			SQL;

		$statements = $this->statements($sql);

		$this->assertCount(2, $statements);
		$this->assertStringContainsString('EXTRACT(DAY FROM $1)', $statements[0]);
		$this->assertStringStartsWith('CREATE INDEX', $statements[1]);
	}

	// Note: the section banner above must not be the first thing in this group when
	// the first member carries an attribute. The SMF/section_comments fixer inserts
	// the banner between the attribute and its method, which is why the data provider
	// case is second rather than first.
	#[DataProvider('sqlProvider')]
	public function testItSplitsOnlyTheSemicolonsThatEndAStatement(string $sql, array $expected): void
	{
		$this->assertSame($expected, $this->statements($sql));
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * @return array<string, array{string, array<int, string>}>
	 */
	public static function sqlProvider(): array
	{
		return [
			'nothing at all' => ['', []],
			'semicolons alone' => [';;;', []],
			'one statement without a semicolon' => [
				'CREATE TABLE smf_x (id int)',
				['CREATE TABLE smf_x (id int)'],
			],
			'a trailing semicolon adds no empty statement' => [
				'CREATE TABLE smf_x (id int);',
				['CREATE TABLE smf_x (id int)'],
			],
			'whitespace between statements is dropped' => [
				"CREATE TABLE smf_x (id int);\n\n  CREATE TABLE smf_y (id int);\n",
				['CREATE TABLE smf_x (id int)', 'CREATE TABLE smf_y (id int)'],
			],
			'a semicolon inside a single quoted default' => [
				"ALTER TABLE smf_x ALTER c SET DEFAULT 'a;b';ALTER TABLE smf_x ALTER d SET DEFAULT 'c'",
				[
					"ALTER TABLE smf_x ALTER c SET DEFAULT 'a;b'",
					"ALTER TABLE smf_x ALTER d SET DEFAULT 'c'",
				],
			],
			'a semicolon inside a double quoted identifier' => [
				'CREATE TABLE "we;ird" (id int);CREATE TABLE smf_y (id int)',
				['CREATE TABLE "we;ird" (id int)', 'CREATE TABLE smf_y (id int)'],
			],
			'a semicolon inside a backquoted identifier' => [
				'CREATE TABLE `we;ird` (id int);CREATE TABLE smf_y (id int)',
				['CREATE TABLE `we;ird` (id int)', 'CREATE TABLE smf_y (id int)'],
			],
			'an escaped quote does not end the string' => [
				"INSERT INTO smf_x VALUES ('it\\'s; fine');SELECT 1",
				["INSERT INTO smf_x VALUES ('it\\'s; fine')", 'SELECT 1'],
			],
			'a tagged dollar quote is closed by its own tag' => [
				'CREATE FUNCTION f() RETURNS int AS $body$ SELECT 1; $body$ LANGUAGE SQL;SELECT 2',
				[
					'CREATE FUNCTION f() RETURNS int AS $body$ SELECT 1; $body$ LANGUAGE SQL',
					'SELECT 2',
				],
			],
			'a positional parameter is not a dollar quote' => [
				'SELECT $1; SELECT $2',
				['SELECT $1', 'SELECT $2'],
			],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Calls the private helper under test.
	 *
	 * @param string $sql The SQL to split.
	 * @return array<int, string> The statements it is made of.
	 */
	private function statements(string $sql): array
	{
		$method = new \ReflectionMethod(MigrationRollback::class, 'statements');

		return $method->invoke(new MigrationRollback(), $sql);
	}
}
