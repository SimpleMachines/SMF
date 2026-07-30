<?php

/**
 * Tiny read-only database probe for the baseline scripts.
 *
 * The shell scripts need to assert things about the forum's database from
 * outside PHP -- what version is installed, how many rows a table has. They
 * cannot go through SSI.php for that: half of these checks run before the forum
 * exists, when loading SMF would be fatal rather than merely unhelpful. So this
 * connects with the raw driver, using nothing but Settings.php.
 *
 *   php .docker/baseline/db.php setting <name>       one row from {prefix}settings
 *   php .docker/baseline/db.php count <table>        row count, without the prefix
 *   php .docker/baseline/db.php counts <t> <t> ...   one count per line
 *   php .docker/baseline/db.php connected            exit 0 if the database answers
 *
 * Prints the answer and nothing else, so callers can use it in $(...).
 * Exits non-zero when the database cannot be reached or the query fails, and
 * prints -1 for a count so a caller comparing numbers still sees something
 * obviously wrong.
 *
 * Deliberately written to PHP 7.1 syntax: SMF 2.1's CI lints every PHP file in
 * the checkout against 7.1 through 8.4.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (PHP_SAPI !== 'cli')
	die('This script may only be run from the command line.');

$board_dir = dirname(dirname(__DIR__));

if (!is_file($board_dir . '/Settings.php'))
	fail('Settings.php does not exist -- the forum is not configured yet');

require $board_dir . '/Settings.php';

$command = isset($argv[1]) ? $argv[1] : '';
$args = array_slice($argv, 2);

$connection = baseline_connect($db_type, $db_server, $db_port, $db_name, $db_user, $db_passwd);

switch ($command)
{
	case 'connected':
		exit(0);

	case 'setting':
		if (empty($args[0]))
			fail('usage: db.php setting <name>');

		echo baseline_setting($connection, $db_type, $db_prefix, $args[0]);
		break;

	case 'count':
		if (empty($args[0]))
			fail('usage: db.php count <table>');

		echo baseline_count($connection, $db_type, $db_prefix, $args[0]);
		break;

	case 'counts':
		if (empty($args))
			fail('usage: db.php counts <table> [<table> ...]');

		foreach ($args as $table)
			echo $table, ' ', baseline_count($connection, $db_type, $db_prefix, $table), "\n";
		break;

	default:
		fail('unknown command: ' . $command);
}

exit(0);

/**
 * Complains on stderr and stops. Nothing here is recoverable.
 *
 * @param string $message
 */
function fail($message)
{
	fwrite(STDERR, '[baseline] db.php: ' . $message . "\n");
	exit(1);
}

/**
 * Opens a connection with whichever driver this install was built against.
 *
 * @return resource|mysqli
 */
function baseline_connect($type, $server, $port, $name, $user, $passwd)
{
	// The installer writes this back capitalised as 'PostgreSQL'.
	if (strtolower($type) === 'postgresql')
	{
		$dsn = 'host=' . $server . ' dbname=' . $name . ' user=' . $user . ' password=' . $passwd;

		if (!empty($port))
			$dsn .= ' port=' . (int) $port;

		$connection = @pg_connect($dsn);
	}
	else
		$connection = @mysqli_connect($server, $user, $passwd, $name, !empty($port) ? (int) $port : 3306);

	if (!$connection)
		fail('cannot connect to the ' . $type . ' database ' . $name . ' on ' . $server);

	return $connection;
}

/**
 * @return string The setting's value, or '' when it is not set.
 */
function baseline_setting($connection, $type, $prefix, $name)
{
	if (strtolower($type) === 'postgresql')
	{
		$result = @pg_query_params($connection, 'SELECT value FROM ' . $prefix . 'settings WHERE variable = $1', array($name));

		if ($result === false)
			return '';

		$row = pg_fetch_row($result);

		return $row === false ? '' : $row[0];
	}

	$escaped = mysqli_real_escape_string($connection, $name);
	$result = @mysqli_query($connection, 'SELECT value FROM ' . $prefix . 'settings WHERE variable = "' . $escaped . '"');

	if ($result === false)
		return '';

	$row = mysqli_fetch_row($result);

	return $row === null || $row === false ? '' : $row[0];
}

/**
 * @return int The row count, or -1 when the table cannot be read.
 */
function baseline_count($connection, $type, $prefix, $table)
{
	// Table names cannot be parameterised, so only ever accept a bare name.
	if (!preg_match('~^[A-Za-z0-9_]+$~', $table))
		fail('not a table name: ' . $table);

	$sql = 'SELECT COUNT(*) FROM ' . $prefix . $table;

	if (strtolower($type) === 'postgresql')
	{
		$result = @pg_query($connection, $sql);

		return $result === false ? -1 : (int) pg_fetch_row($result)[0];
	}

	$result = @mysqli_query($connection, $sql);

	return $result === false ? -1 : (int) mysqli_fetch_row($result)[0];
}

?>