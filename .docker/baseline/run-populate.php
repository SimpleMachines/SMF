<?php

/**
 * Drives the patched Populate.php to completion from the command line.
 *
 *   php .docker/baseline/run-populate.php --categories=6 --boards=24 \
 *       --membergroups=24 --members=400 --topics=1200 --messages=6000 \
 *       --block-size=1000
 *
 * Populate.php does one block of work per construction and then stops, because
 * in a browser it is relying on a page refresh to come back for more. It keeps
 * no state between blocks: every construction re-counts the six tables and
 * carries on from whatever it finds. That makes it safe to simply construct it
 * again in a loop -- and to interrupt this script at any point and re-run it.
 *
 * The counters passed here are absolute targets, including the rows a fresh
 * install already created, not amounts to add.
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
$populate_file = $baseline_dir . '/cache/Populate.patched.php';

require_once $baseline_dir . '/bootstrap.php';

if (!is_file($populate_file))
	baseline_fail('run .docker/baseline/populate.sh -- it fetches and patches Populate.php first');

// Order matters. Populate's constructor walks its counters in insertion order
// and works on the first one that is short, so this is also the order the
// content gets built in.
$targets = array(
	'categories' => 0,
	'boards' => 0,
	'membergroups' => 0,
	'members' => 0,
	'topics' => 0,
	'messages' => 0,
);

$block_size = 500;
$max_blocks = 10000;

foreach (array_slice($argv, 1) as $arg)
{
	if (!preg_match('~^--([a-z-]+)=([0-9]+)$~', $arg, $match))
		baseline_fail('unrecognised argument: ' . $arg);

	if ($match[1] === 'block-size')
		$block_size = (int) $match[2];
	elseif ($match[1] === 'max-blocks')
		$max_blocks = (int) $match[2];
	elseif (array_key_exists($match[1], $targets))
		$targets[$match[1]] = (int) $match[2];
	else
		baseline_fail('unrecognised argument: ' . $arg);
}

foreach ($targets as $name => $value)
{
	if ($value < 1)
		baseline_fail('missing or zero --' . $name);
}

// Populate.php assigns every member to rand(8, <membergroups target>), and a
// fresh 2.1 install already occupies group ids 1 to 8. Anything smaller makes
// rand()'s minimum exceed its maximum, which is fatal on PHP 8.
if ($targets['membergroups'] < 8)
	baseline_fail('--membergroups must be at least 8');

baseline_boot($board_dir);

require_once $populate_file;

$counters = array();

foreach ($targets as $name => $max)
	$counters[$name] = array('max' => $max, 'current' => 0);

$order = array_keys($targets);
$previous = null;
$blocks = 0;

echo '[baseline] populating: ';

foreach ($targets as $name => $max)
	echo $name, '=', $max, ' ';

echo "\n";

$current = baseline_counts($order);

while ($blocks < $max_blocks)
{
	$blocks++;

	/*
	 * Clamp the block to what is actually left to do.
	 *
	 * makeCategories(), makeBoards(), makeMembergroups() and makeMembers() all
	 * increment their own counter as they go, so they stop on the target by
	 * themselves. makeMessages() does not -- it only ever learns the message
	 * count from the database at construction time -- so an unclamped block
	 * always runs to its full size and overshoots by up to blockSize-1. On the
	 * tiny profile that is the difference between 600 messages and 1001.
	 */
	$size = min($block_size, baseline_remaining($current, $targets, $order));

	// Each construction re-reads the row counts and does one block of work.
	// Output is buffered away because Populate.php reports progress as HTML.
	ob_start();
	new Populate(array('counters' => $counters, 'blockSize' => $size));
	ob_end_clean();

	$current = baseline_counts($order);

	printf(
		"[baseline] block %-4d %s\n",
		$blocks,
		baseline_format($current, $targets)
	);

	if (baseline_done($current, $targets))
	{
		/*
		 * createPost() records the board on the topic but leaves the message's
		 * own id_board at 0; Populate's fixupTopicsBoards() copies it across
		 * once everything is complete. Without it every board would report
		 * itself empty, so this is worth an explicit check rather than a
		 * comment saying it should have happened.
		 */
		$orphans = baseline_orphan_messages();

		if ($orphans > 0)
			baseline_fail($orphans . ' message(s) still have id_board = 0 -- fixupTopicsBoards() did not run');

		echo '[baseline] populate complete after ', $blocks, " block(s)\n";
		exit(0);
	}

	// Nothing moved: another pass would not move anything either.
	if ($previous !== null && $previous === $current)
		baseline_fail(
			"populate stalled at " . baseline_format($current, $targets) . "\n" .
			'           Nothing changed between two blocks. Check docker compose logs web for the real error.'
		);

	$previous = $current;
}

baseline_fail('gave up after ' . $max_blocks . ' blocks without reaching the targets');

/**
 * Reads the live row counts straight from the database, which is the only place
 * Populate.php keeps its progress.
 *
 * @param array $tables
 * @return array
 */
function baseline_counts($tables)
{
	global $smcFunc;

	$counts = array();

	foreach ($tables as $table)
	{
		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}' . $table, array());
		list ($counts[$table]) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$counts[$table] = (int) $counts[$table];
	}

	return $counts;
}

/**
 * @return int Messages that were never linked back to a board.
 */
function baseline_orphan_messages()
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages
		WHERE id_board = {int:no_board}',
		array('no_board' => 0)
	);
	list ($orphans) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return (int) $orphans;
}

/**
 * How many rows the phase Populate is about to work on still needs.
 *
 * Populate picks its phase by walking the counters in order and taking the
 * first that is short, skipping topics (those are a by-product of messages).
 * Mirroring that here is what lets the block be clamped to the right number.
 *
 * @param array $current
 * @param array $targets
 * @param array $order
 * @return int At least 1, so a block is never asked to do nothing.
 */
function baseline_remaining($current, $targets, $order)
{
	foreach ($order as $name)
	{
		if ($name === 'topics')
			continue;

		if ($current[$name] < $targets[$name])
			return max(1, $targets[$name] - $current[$name]);
	}

	return 1;
}

/**
 * @param array $current
 * @param array $targets
 * @return bool
 */
function baseline_done($current, $targets)
{
	foreach ($targets as $name => $max)
	{
		// Topics are a by-product, not something Populate builds directly: each
		// message starts a new topic with probability topics/messages, so the
		// final count lands near the target rather than on it. Waiting for an
		// exact match would wait forever, because once the messages are done
		// nothing creates topics any more.
		if ($name === 'topics')
			continue;

		if ($current[$name] < $max)
			return false;
	}

	return true;
}

/**
 * @param array $current
 * @param array $targets
 * @return string
 */
function baseline_format($current, $targets)
{
	$parts = array();

	foreach ($targets as $name => $max)
		$parts[] = substr($name, 0, 3) . ' ' . $current[$name] . '/' . $max;

	return implode('  ', $parts);
}
