<?php

/**
 * Runs one statement against a development database, for the .dev scripts.
 *
 * Under docker they reach the database through the mysql and psql clients that
 * are already inside the container. There is nothing to reach for locally, and
 * asking people to install a command line client in order to run the tests
 * would be a new dependency for something SMF can already do: mysqli and pgsql
 * are what the forum itself connects with, so any machine that can serve SMF
 * can do this.
 *
 *   php .dev/db.php --engine mysql --sql 'SELECT 1'
 *   php .dev/db.php --engine postgresql --sql 'DROP SCHEMA ...' --database ''
 *
 * Rows come back one per line, columns separated by tabs and nothing quoted,
 * which is what `mysql -N -B` and `psql -tAX` produce and what the callers
 * already parse. Anything that goes wrong is a message on stderr and a non-zero
 * exit, so an `|| true` in the caller still means what it says.
 *
 * Talks to the database directly rather than through SMF: nothing here loads
 * Settings.php or boots the forum, because the callers need this to work before
 * there is a forum to boot. The database is a throwaway development one, the
 * credentials arrive from the calling script, and the SQL is written in those
 * scripts rather than anywhere a user can reach.
 */

declare(strict_types=1);

$options = getopt('', ['engine:', 'sql:', 'server:', 'port:', 'database:', 'user:', 'password:']);

foreach (['engine', 'sql'] as $required) {
	if (!isset($options[$required])) {
		fwrite(STDERR, "db.php: --{$required} is required\n");

		exit(2);
	}
}

$engine = in_array($options['engine'], ['postgresql', 'postgres', 'pgsql'], true) ? 'postgresql' : 'mysql';
$server = (string) ($options['server'] ?? '127.0.0.1');
$port = (int) ($options['port'] ?? ($engine === 'mysql' ? 3306 : 5432));
$database = (string) ($options['database'] ?? 'smf');
$user = (string) ($options['user'] ?? 'smf');
$password = (string) ($options['password'] ?? 'smf');
$sql = (string) $options['sql'];

/**
 * Says the extension is missing in terms of what to install, then gives up.
 *
 * @param string $extension The extension that is not loaded.
 */
function missing(string $extension): never
{
	fwrite(STDERR, "db.php: the {$extension} extension is not loaded in " . PHP_BINARY . "\n");
	fwrite(STDERR, "        SMF needs it to talk to this engine at all. Install it, or\n");
	fwrite(STDERR, "        pass --docker to use the compose stack instead.\n");

	exit(3);
}

/**
 * Prints rows the way the command line clients do: tab separated, unquoted.
 *
 * @param array $rows Rows of scalar values.
 */
function emit(array $rows): void
{
	foreach ($rows as $row) {
		echo implode("\t", array_map(static fn ($value) => (string) $value, $row)), "\n";
	}
}

if ($engine === 'mysql') {
	if (!extension_loaded('mysqli')) {
		missing('mysqli');
	}

	// Off, so a failure arrives as a return value to report rather than as an
	// exception with a stack trace nobody reading a shell script wants.
	mysqli_report(MYSQLI_REPORT_OFF);

	// An empty database name is how the callers connect with no database yet,
	// which is what dropping and recreating one needs.
	$connection = @new mysqli($server, $user, $password, $database, $port);

	if ($connection->connect_errno !== 0) {
		fwrite(STDERR, "db.php: could not connect to mysql at {$server}:{$port} as {$user}: " . $connection->connect_error . "\n");

		exit(1);
	}

	// The callers send several statements at once when they reset a database,
	// so this is multi_query rather than query.
	if ($connection->multi_query($sql) === false) {
		fwrite(STDERR, 'db.php: ' . $connection->error . "\n");

		exit(1);
	}

	while (true) {
		$result = $connection->store_result();

		if ($result instanceof mysqli_result) {
			emit($result->fetch_all(MYSQLI_NUM));
			$result->free();
		}

		// An error can surface on any statement after the first, where
		// multi_query() has already said yes. It arrives two ways: as an errno
		// on the current statement, or as next_result() refusing to move on.
		// Testing only the first, or folding next_result() into a loop
		// condition, is how a batch that failed halfway reports success.
		if ($connection->errno !== 0) {
			fwrite(STDERR, 'db.php: ' . $connection->error . "\n");

			exit(1);
		}

		if (!$connection->more_results()) {
			break;
		}

		if (!$connection->next_result()) {
			fwrite(STDERR, 'db.php: ' . $connection->error . "\n");

			exit(1);
		}
	}

	exit(0);
}

if (!function_exists('pg_connect')) {
	missing('pgsql');
}

$dsn = sprintf(
	"host='%s' port=%d dbname='%s' user='%s' password='%s'",
	addslashes($server),
	$port,
	addslashes($database),
	addslashes($user),
	addslashes($password),
);

$connection = @pg_connect($dsn);

if ($connection === false) {
	fwrite(STDERR, "db.php: could not connect to postgresql at {$server}:{$port} as {$user}\n");

	exit(1);
}

$result = @pg_query($connection, $sql);

if ($result === false) {
	fwrite(STDERR, 'db.php: ' . pg_last_error($connection) . "\n");

	exit(1);
}

// pg_num_fields() is 0 for a statement that returns no rows, such as the DDL
// the reset uses, and fetch_all() on that emits a warning rather than nothing.
if (pg_num_fields($result) > 0) {
	emit(pg_fetch_all($result, PGSQL_NUM));
}

exit(0);
