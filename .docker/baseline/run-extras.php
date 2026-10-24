<?php

/**
 * Adds the data Populate.php does not.
 *
 *   docker compose exec -T web php .docker/baseline/run-extras.php
 *   docker compose exec -T web php .docker/baseline/run-extras.php --only 30-calendar
 *
 * Populate.php builds categories, boards, membergroups, members, topics and
 * messages. That is a forum, but it is not a forum an upgrade would find
 * interesting: SMF 3.0 runs 87 v2_1 migrations and 17 v3_0 migrations, and
 * almost none of them touch those six tables. They convert IP columns, unpick
 * custom profile fields, turn holidays into recurring events, rewrite the
 * package log, drop settings that no longer exist, and so on -- all of which
 * would run against empty tables and prove nothing.
 *
 * So each script in extras/ seeds one area, and says in its header which
 * migrations it is there to exercise. Scripts run in filename order and each is
 * idempotent: the marker goes into {db_prefix}settings, which means it travels
 * inside the dump, so a restored baseline knows what it already has.
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

$baseline_dir = __DIR__;
$board_dir = dirname(dirname($baseline_dir));

require_once $baseline_dir . '/bootstrap.php';

$only = '';
$force = false;
$args = array_slice($argv, 1);

while ($args)
{
	$arg = array_shift($args);

	if ($arg === '--force')
		$force = true;
	elseif (strpos($arg, '--only=') === 0)
		$only = substr($arg, 7);
	elseif ($arg === '--only')
		$only = (string) array_shift($args);
	else
		baseline_fail('unknown argument: ' . $arg);
}

baseline_boot($board_dir);

$scripts = glob($baseline_dir . '/extras/*.php');

if (empty($scripts))
	baseline_fail('no extras found in ' . $baseline_dir . '/extras/');

sort($scripts);

$ran = 0;

foreach ($scripts as $script)
{
	$name = basename($script, '.php');

	if ($only !== '' && $only !== $name)
		continue;

	// Each script checks its own marker and skips itself, so re-running this
	// after the last script has finished is free. A script that failed *part
	// way* is a different matter -- its marker was never written, so a re-run
	// would insert its rows a second time. Recover from that with reset.sh and
	// a fresh build, not by running this again.
	$baseline_force = $force;

	require $script;

	$ran++;
}

if ($ran === 0)
	baseline_fail('nothing matched --only ' . $only);

baseline_say('extras complete');

?>