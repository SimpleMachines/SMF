<?php

/**
 * Reads the shape of an SMF database, and compares two of those readings.
 *
 * The question it answers is "did the upgrader leave the database looking like
 * a fresh install of the same version?", which nothing else here can answer.
 * The installer builds the schema from Sources/Db/Schema/v3_0/ in one go; the
 * upgrader arrives at it through a hundred-odd migrations applied to whatever
 * 2.1 left behind. They are meant to converge. Where they do not, the forum
 * that upgraded is running on a schema no one has ever tested against.
 *
 * Two modes, and they are separate on purpose: only one engine can be live at
 * a time, so the two readings cannot be taken in one process.
 *
 *   php .docker/schema-tool.php dump --engine mysql --db smf > fresh.json
 *   php .docker/schema-tool.php diff fresh.json upgraded.json
 *
 * Runs inside the web container, and talks to the database directly rather
 * than through SMF. Nothing here loads Settings.php or boots the forum: the
 * database being examined is frequently one that SMF would refuse to run on,
 * which is the whole point of looking at it.
 *
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

if (PHP_SAPI !== 'cli') {
	exit("This is a command line script.\n");
}

$argv = $_SERVER['argv'];
$mode = $argv[1] ?? '';

exit(match ($mode) {
	'dump' => cmd_dump(parse_options(array_slice($argv, 2))),
	'diff' => cmd_diff(array_slice($argv, 2)),
	default => usage(),
});

/**
 * @return int
 */
function usage(): int
{
	fwrite(STDERR, <<<'TEXT'
		Reads the shape of an SMF database, and compares two of those readings.

		  php .docker/schema-tool.php dump --engine mysql --db smf > fresh.json
		  php .docker/schema-tool.php diff fresh.json upgraded.json

		dump options, with the defaults compose.yaml gives:

		  --engine   mysql or postgresql          (required)
		  --db       database name                (smf)
		  --prefix   table prefix                 (smf_)
		  --host     database host                (mysql / postgres)
		  --port     database port                (3306 / 5432)
		  --user     database user                (smf)
		  --pass     database password            (smf)
		  --label    what to call this reading in a diff  (the database name)

		diff exits 1 when the two disagree about the schema, 0 when they do not.
		Settings and column order are reported but do not decide the exit code:
		they are differences a forum can live with, and a real forum always has
		some.

		TEXT);

	return 2;
}

/**
 * Turns --name=value and --name value into an array. Unknown names are an
 * error rather than a shrug: a misspelled --prefix would otherwise read the
 * wrong tables and report every one of them as missing.
 *
 * @param array $args
 * @return array
 */
function parse_options(array $args): array
{
	$known = ['engine', 'db', 'prefix', 'host', 'port', 'user', 'pass', 'label'];
	$options = [];

	while ($args !== []) {
		$arg = array_shift($args);

		if (!str_starts_with($arg, '--')) {
			fail('unexpected argument: ' . $arg);
		}

		$name = substr($arg, 2);
		$value = null;

		if (str_contains($name, '=')) {
			[$name, $value] = explode('=', $name, 2);
		}

		if (!in_array($name, $known, true)) {
			fail('unknown option: --' . $name);
		}

		$options[$name] = $value ?? (string) array_shift($args);
	}

	return $options;
}

/**
 * @param string $message
 * @return never
 */
function fail(string $message): never
{
	fwrite(STDERR, 'schema-tool: ' . $message . "\n");

	exit(2);
}

/**
 * @param array $options
 * @return int
 */
function cmd_dump(array $options): int
{
	$engine = match ($options['engine'] ?? '') {
		'mysql', 'mysqli', 'mariadb' => 'mysql',
		'postgresql', 'postgres', 'pgsql' => 'postgresql',
		default => fail('need --engine mysql|postgresql'),
	};

	$db = $options['db'] ?? 'smf';

	$schema = [
		'engine' => $engine,
		'label' => $options['label'] ?? $db,
		'prefix' => $options['prefix'] ?? 'smf_',
		'database' => $db,
		'read_at' => gmdate('c'),
	];

	$read = $engine === 'mysql' ? read_mysql(...) : read_postgresql(...);

	$schema += $read(
		$options['host'] ?? ($engine === 'mysql' ? 'mysql' : 'postgres'),
		(int) ($options['port'] ?? ($engine === 'mysql' ? 3306 : 5432)),
		$db,
		$options['user'] ?? 'smf',
		$options['pass'] ?? 'smf',
		$schema['prefix'],
	);

	echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";

	return 0;
}

/**
 * Everything is keyed by name with the prefix taken off, so that two databases
 * using different prefixes still line up, and so that the report reads in the
 * names people use rather than the ones the database stores.
 *
 * @param string $host
 * @param int $port
 * @param string $db
 * @param string $user
 * @param string $pass
 * @param string $prefix
 * @return array
 */
function read_mysql(string $host, int $port, string $db, string $user, string $pass, string $prefix): array
{
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

	try {
		$link = new mysqli($host, $user, $pass, $db, $port);
	} catch (mysqli_sql_exception $e) {
		fail('cannot connect to ' . $db . ' on ' . $host . ': ' . $e->getMessage());
	}

	$tables = [];

	// ROW_FORMAT is here because it is not decoration: COMPACT caps an index
	// key at 767 bytes where DYNAMIC allows 3072, so a table left in the older
	// format is a table where half of SMF's indexes cannot be created at their
	// full width. AUTO_INCREMENT is deliberately not here -- it counts rows,
	// and differs between any two databases that have been used differently.
	$rows = query_mysql($link, '
		SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, ROW_FORMAT
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'', [$db]);

	foreach ($rows as $row) {
		$tables[unprefix($row['TABLE_NAME'], $prefix)] = [
			'attributes' => [
				'engine' => (string) $row['ENGINE'],
				'collation' => (string) $row['TABLE_COLLATION'],
				'row_format' => (string) $row['ROW_FORMAT'],
			],
			'columns' => [],
			'indexes' => [],
		];
	}

	// GENERATION_EXPRESSION because EXTRA says only that a column is generated,
	// never what from. messages.modified_time and its two neighbours are STORED
	// columns read out of the edit_history JSON, and a wrong path in one of
	// them produces a column that is the right type and quietly the wrong
	// value -- which is the failure this is least able to afford missing.
	$rows = query_mysql($link, '
		SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE,
			IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME,
			GENERATION_EXPRESSION
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = ?
		ORDER BY TABLE_NAME, ORDINAL_POSITION', [$db]);

	foreach ($rows as $row) {
		$table = unprefix($row['TABLE_NAME'], $prefix);

		if (!isset($tables[$table])) {
			continue;
		}

		$tables[$table]['columns'][$row['COLUMN_NAME']] = [
			'type' => (string) $row['COLUMN_TYPE'],
			'nullable' => $row['IS_NULLABLE'] === 'YES',
			// A NULL default and no default at all are the same string here,
			// so nullability above is what tells them apart.
			'default' => $row['COLUMN_DEFAULT'],
			// auto_increment lives here, and so does ON UPDATE.
			'extra' => (string) $row['EXTRA'],
			'collation' => $row['COLLATION_NAME'],
			'generated' => (string) $row['GENERATION_EXPRESSION'],
			'position' => (int) $row['ORDINAL_POSITION'],
		];
	}

	// SUB_PART is the length of a prefix index, which SMF uses on a few of the
	// longer text columns. A missing one is a real difference, so it is part of
	// the column's name in the key rather than being dropped.
	$rows = query_mysql($link, '
		SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
		FROM information_schema.STATISTICS
		WHERE TABLE_SCHEMA = ?
		ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX', [$db]);

	foreach ($rows as $row) {
		$table = unprefix($row['TABLE_NAME'], $prefix);

		if (!isset($tables[$table])) {
			continue;
		}

		$index = $row['INDEX_NAME'];

		$tables[$table]['indexes'][$index] ??= [
			'primary' => $index === 'PRIMARY',
			'unique' => (int) $row['NON_UNIQUE'] === 0,
			'columns' => [],
		];

		$tables[$table]['indexes'][$index]['columns'][] = $row['COLUMN_NAME']
			. ($row['SUB_PART'] === null ? '' : '(' . $row['SUB_PART'] . ')');
	}

	$settings = [];

	if (isset($tables['settings'])) {
		foreach (query_mysql($link, 'SELECT variable FROM `' . $prefix . 'settings`', []) as $row) {
			$settings[] = $row['variable'];
		}
	}

	$link->close();

	return finish($tables, $settings, [], []);
}

/**
 * @param string $host
 * @param int $port
 * @param string $db
 * @param string $user
 * @param string $pass
 * @param string $prefix
 * @return array
 */
function read_postgresql(string $host, int $port, string $db, string $user, string $pass, string $prefix): array
{
	$link = @pg_connect(sprintf(
		'host=%s port=%d dbname=%s user=%s password=%s',
		$host,
		$port,
		$db,
		$user,
		$pass,
	));

	if ($link === false) {
		fail('cannot connect to ' . $db . ' on ' . $host);
	}

	$tables = [];

	$rows = query_postgresql($link, '
		SELECT table_name
		FROM information_schema.tables
		WHERE table_schema = \'public\' AND table_type = \'BASE TABLE\'', []);

	foreach ($rows as $row) {
		$tables[unprefix($row['table_name'], $prefix)] = [
			// PostgreSQL has no per-table storage engine or collation, so the
			// MySQL side of this stays empty rather than inventing one.
			'attributes' => [],
			'columns' => [],
			'indexes' => [],
		];
	}

	$rows = query_postgresql($link, '
		SELECT table_name, column_name, ordinal_position, data_type,
			character_maximum_length, numeric_precision, numeric_scale,
			is_nullable, column_default, generation_expression
		FROM information_schema.columns
		WHERE table_schema = \'public\'
		ORDER BY table_name, ordinal_position', []);

	foreach ($rows as $row) {
		$table = unprefix($row['table_name'], $prefix);

		if (!isset($tables[$table])) {
			continue;
		}

		$default = $row['column_default'];

		$tables[$table]['columns'][$row['column_name']] = [
			'type' => postgresql_type($row),
			'nullable' => $row['is_nullable'] === 'YES',
			'default' => $default,
			// The nearest thing PostgreSQL has to MySQL's auto_increment, so
			// that a column that lost its sequence reads the same way on both.
			'extra' => $default !== null && str_starts_with($default, 'nextval(') ? 'auto_increment' : '',
			'collation' => null,
			// PostgreSQL has generated columns too, and SMF uses none of them
			// here; reading the column keeps both engines the same shape.
			'generated' => (string) ($row['generation_expression'] ?? ''),
			'position' => (int) $row['ordinal_position'],
		];
	}

	// unnest() with ordinality is what keeps a composite index in its own
	// order; indkey is an int2vector, and reading it any other way sorts the
	// columns alphabetically, which would make (id_group, id_board) and
	// (id_board, id_group) look like the same index. They are not.
	//
	// pg_get_indexdef() per key rather than a join to pg_attribute, because an
	// index on an expression stores 0 in indkey and has no pg_attribute row to
	// join to. Joining loses those keys, and an index every one of whose keys
	// is an expression disappears entirely -- which is three of them on a
	// stock install, idx_member_name_low and idx_real_name_low among them.
	$rows = query_postgresql($link, '
		SELECT c.relname AS table_name, i.relname AS index_name,
			ix.indisunique, ix.indisprimary,
			pg_get_indexdef(ix.indexrelid, k.ord::int, true) AS keydef
		FROM pg_index AS ix
			INNER JOIN pg_class AS c ON (c.oid = ix.indrelid)
			INNER JOIN pg_class AS i ON (i.oid = ix.indexrelid)
			INNER JOIN pg_namespace AS n ON (n.oid = c.relnamespace)
			CROSS JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS k(attnum, ord)
		WHERE n.nspname = \'public\'
		ORDER BY c.relname, i.relname, k.ord', []);

	foreach ($rows as $row) {
		$table = unprefix($row['table_name'], $prefix);

		if (!isset($tables[$table])) {
			continue;
		}

		$index = unprefix($row['index_name'], $prefix);

		$tables[$table]['indexes'][$index] ??= [
			'primary' => $row['indisprimary'] === 't',
			'unique' => $row['indisunique'] === 't',
			'columns' => [],
		];

		$tables[$table]['indexes'][$index]['columns'][] = $row['keydef'];
	}

	// SMF's compatibility layer: find_in_set(), instr(), from_unixtime() and
	// the rest of the MySQL shims, plus the group_concat aggregate. A query
	// naming one of these fails outright if the install never created it, so
	// they belong in the reading as much as the tables do.
	$routines = [];

	foreach (query_postgresql($link, '
		SELECT p.proname || \'(\' || pg_get_function_arguments(p.oid) || \')\' AS signature
		FROM pg_proc AS p
			INNER JOIN pg_namespace AS n ON (n.oid = p.pronamespace)
		WHERE n.nspname = \'public\'
		ORDER BY signature', []) as $row) {
		$routines[] = $row['signature'];
	}

	$sequences = [];

	foreach (query_postgresql($link, '
		SELECT sequence_name
		FROM information_schema.sequences
		WHERE sequence_schema = \'public\'
		ORDER BY sequence_name', []) as $row) {
		$sequences[] = unprefix($row['sequence_name'], $prefix);
	}

	$settings = [];

	if (isset($tables['settings'])) {
		foreach (query_postgresql($link, 'SELECT variable FROM "' . $prefix . 'settings"', []) as $row) {
			$settings[] = $row['variable'];
		}
	}

	pg_close($link);

	return finish($tables, $settings, $sequences, $routines);
}

/**
 * Rebuilds the type as it was written, since information_schema takes it
 * apart. varchar(255) arrives as 'character varying' with the length in a
 * separate column, and a bare 'character varying' beside it means something
 * else entirely.
 *
 * @param array $row
 * @return string
 */
function postgresql_type(array $row): string
{
	$type = (string) $row['data_type'];

	if ($row['character_maximum_length'] !== null) {
		return $type . '(' . $row['character_maximum_length'] . ')';
	}

	// Scale is null for integers, where the precision is the width in bits and
	// says nothing anyone wants in a report.
	if ($type === 'numeric' && $row['numeric_precision'] !== null && $row['numeric_scale'] !== null) {
		return $type . '(' . $row['numeric_precision'] . ',' . $row['numeric_scale'] . ')';
	}

	return $type;
}

/**
 * @param array $tables
 * @param array $settings
 * @param array $sequences
 * @return array
 */
function finish(array $tables, array $settings, array $sequences, array $routines): array
{
	ksort($tables);
	sort($settings);

	foreach ($tables as &$table) {
		ksort($table['columns']);
		ksort($table['indexes']);
	}

	return [
		'tables' => $tables,
		'sequences' => $sequences,
		'routines' => $routines,
		'settings' => $settings,
	];
}

/**
 * @param mysqli $link
 * @param string $sql
 * @param array $params
 * @return array
 */
function query_mysql(mysqli $link, string $sql, array $params): array
{
	$statement = $link->prepare($sql);

	if ($params !== []) {
		$statement->bind_param(str_repeat('s', count($params)), ...$params);
	}

	$statement->execute();
	$rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
	$statement->close();

	return $rows;
}

/**
 * @param \PgSql\Connection $link
 * @param string $sql
 * @param array $params
 * @return array
 */
function query_postgresql(\PgSql\Connection $link, string $sql, array $params): array
{
	$result = @pg_query_params($link, $sql, $params);

	if ($result === false) {
		fail('query failed: ' . pg_last_error($link));
	}

	return pg_fetch_all($result, PGSQL_ASSOC);
}

/**
 * @param string $name
 * @param string $prefix
 * @return string
 */
function unprefix(string $name, string $prefix): string
{
	return $prefix !== '' && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;
}

/**
 * @param array $files
 * @return int
 */
function cmd_diff(array $files): int
{
	if (count($files) !== 2) {
		fail('diff needs two files: the reading to measure against, then the one to judge');
	}

	[$a, $b] = array_map(read_dump(...), $files);

	if ($a['engine'] !== $b['engine']) {
		fail('these are different engines (' . $a['engine'] . ' and ' . $b['engine'] . '), and their types do not correspond');
	}

	$left = $a['label'];
	$right = $b['label'];

	echo 'Comparing ', $right, ' against ', $left, ' on ', $a['engine'], ".\n\n";

	if ($a['prefix'] !== $b['prefix']) {
		echo "Note: the prefixes differ, so anything naming a table inside a\n",
			"default or a sequence differs with it.\n\n";
	}

	// Two reports rather than one. Everything in the first is a difference in
	// the schema itself; everything in the second is a difference a forum can
	// live with, and a real forum always has some, so only the first decides
	// the exit code.
	$schema = [];
	$aside = [];

	compare_tables($a, $b, $left, $right, $schema);

	if ($a['engine'] === 'postgresql') {
		compare_lists('Sequences', $a['sequences'], $b['sequences'], $right, $schema);
		compare_lists('Functions', $a['routines'] ?? [], $b['routines'] ?? [], $right, $schema);
	}

	compare_lists('Settings', $a['settings'], $b['settings'], $right, $aside);
	compare_column_order($a, $b, $aside);

	if (render($schema) + render($aside) === 0) {
		echo "No differences.\n";

		return 0;
	}

	echo "\n";

	if ($schema !== []) {
		echo count_entries($schema), " difference(s) in the schema.\n";
	}

	if ($aside !== []) {
		echo count_entries($aside), " difference(s) outside it, which do not decide the exit code.\n";
	}

	return $schema === [] ? 0 : 1;
}

/**
 * @param string $file
 * @return array
 */
function read_dump(string $file): array
{
	$raw = @file_get_contents($file);

	if ($raw === false) {
		fail('cannot read ' . $file);
	}

	$dump = json_decode($raw, true);

	if (!is_array($dump) || !isset($dump['tables'], $dump['engine'])) {
		fail($file . ' is not something dump wrote');
	}

	return $dump;
}

/**
 * Adds one difference to a report. Grouped by section and then by whatever it
 * is about, so that a table with eight changed columns is one heading and
 * eight lines under it rather than eight headings.
 *
 * @param array $report
 * @param string $section
 * @param string $subject
 * @param string $message
 * @param array $details
 */
function note(array &$report, string $section, string $subject, string $message, array $details = []): void
{
	$report[$section][$subject][] = ['message' => $message, 'details' => $details];
}

/**
 * @param array $report
 * @return int
 */
function count_entries(array $report): int
{
	$total = 0;

	foreach ($report as $subjects) {
		foreach ($subjects as $entries) {
			$total += count($entries);
		}
	}

	return $total;
}

/**
 * @param array $report
 * @return int
 */
function render(array $report): int
{
	foreach ($report as $section => $subjects) {
		echo $section, "\n", str_repeat('-', strlen($section)), "\n";

		foreach ($subjects as $subject => $entries) {
			echo '  ', $subject, "\n";

			foreach ($entries as $entry) {
				echo '    ', $entry['message'], "\n";

				foreach ($entry['details'] as $detail) {
					echo '      ', $detail, "\n";
				}
			}
		}

		echo "\n";
	}

	return count_entries($report);
}

/**
 * @param array $a
 * @param array $b
 * @param string $left
 * @param string $right
 * @param array $report
 */
function compare_tables(array $a, array $b, string $left, string $right, array &$report): void
{
	foreach (array_diff(array_keys($a['tables']), array_keys($b['tables'])) as $table) {
		note($report, 'Tables', $table, 'missing from ' . $right);
	}

	foreach (array_diff(array_keys($b['tables']), array_keys($a['tables'])) as $table) {
		note($report, 'Tables', $table, 'only in ' . $right);
	}

	foreach (array_intersect_key($a['tables'], $b['tables']) as $name => $table) {
		$other = $b['tables'][$name];

		foreach ($table['attributes'] as $key => $value) {
			if (($other['attributes'][$key] ?? null) !== $value) {
				note($report, 'Tables', $name, $key . ': ' . $value . ' in ' . $left . ', ' . ($other['attributes'][$key] ?? 'nothing') . ' in ' . $right);
			}
		}

		compare_parts($report, $name, 'column', $table['columns'], $other['columns'], describe_column(...), $left, $right);
		compare_parts($report, $name, 'index', $table['indexes'], $other['indexes'], describe_index(...), $left, $right);
	}
}

/**
 * The columns of a table and the indexes of a table are compared the same way:
 * what is only on one side, and what is on both but described differently.
 *
 * @param array $report
 * @param string $table
 * @param string $noun
 * @param array $mine
 * @param array $theirs
 * @param callable $describe
 * @param string $left
 * @param string $right
 */
function compare_parts(array &$report, string $table, string $noun, array $mine, array $theirs, callable $describe, string $left, string $right): void
{
	foreach (array_diff(array_keys($mine), array_keys($theirs)) as $name) {
		note($report, 'Tables', $table, $noun . ' ' . $name . ' is missing from ' . $right . ' (' . $describe($mine[$name]) . ')');
	}

	foreach (array_diff(array_keys($theirs), array_keys($mine)) as $name) {
		note($report, 'Tables', $table, $noun . ' ' . $name . ' is only in ' . $right . ' (' . $describe($theirs[$name]) . ')');
	}

	foreach (array_intersect_key($mine, $theirs) as $name => $definition) {
		$one = $describe($definition);
		$two = $describe($theirs[$name]);

		if ($one !== $two) {
			note($report, 'Tables', $table, $noun . ' ' . $name, [
				$left . ':  ' . $one,
				$right . ':  ' . $two,
			]);
		}
	}
}

/**
 * @param array $column
 * @return string
 */
function describe_column(array $column): string
{
	return implode(' ', array_filter([
		$column['type'],
		$column['nullable'] ? 'NULL' : 'NOT NULL',
		$column['default'] === null ? '' : 'DEFAULT ' . $column['default'],
		$column['extra'],
		empty($column['generated']) ? '' : 'AS (' . $column['generated'] . ')',
		$column['collation'] === null ? '' : 'COLLATE ' . $column['collation'],
	]));
}

/**
 * @param array $index
 * @return string
 */
function describe_index(array $index): string
{
	$kind = $index['primary'] ? 'primary' : ($index['unique'] ? 'unique' : 'index');

	return $kind . ' (' . implode(', ', $index['columns']) . ')';
}

/**
 * @param string $title
 * @param array $a
 * @param array $b
 * @param string $right
 * @param array $report
 */
function compare_lists(string $title, array $a, array $b, string $right, array &$report): void
{
	foreach (array_diff($a, $b) as $name) {
		note($report, $title, $name, 'missing from ' . $right);
	}

	foreach (array_diff($b, $a) as $name) {
		note($report, $title, $name, 'only in ' . $right);
	}
}

/**
 * Column order is not part of the schema in any sense that matters to a query,
 * but it is the one difference that is guaranteed: the upgrader appends, so
 * every column any migration added sits at the end of the table rather than
 * where the schema class puts it. Worth saying once per table, and never worth
 * failing over.
 *
 * @param array $a
 * @param array $b
 * @param array $report
 */
function compare_column_order(array $a, array $b, array &$report): void
{
	foreach (array_intersect_key($a['tables'], $b['tables']) as $name => $table) {
		$mine = order_of($table['columns']);
		$theirs = order_of($b['tables'][$name]['columns']);

		// Only where both hold the same columns. A table that is missing one
		// has been reported already, and would report here as well.
		if ($mine !== $theirs && sort_copy($mine) === sort_copy($theirs)) {
			note($report, 'Column order', $name, 'the columns are in a different order');
		}
	}
}

/**
 * @param array $columns
 * @return array
 */
function order_of(array $columns): array
{
	uasort($columns, fn($a, $b) => $a['position'] <=> $b['position']);

	return array_keys($columns);
}

/**
 * @param array $list
 * @return array
 */
function sort_copy(array $list): array
{
	sort($list);

	return $list;
}
