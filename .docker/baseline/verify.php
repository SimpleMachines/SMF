<?php

/**
 * Compares a restored database against the manifest that came with it.
 *
 *   php .docker/baseline/verify.php --version=2.1.7-1 --profile=small --engine=mysql
 *
 * A dump that loads without error is not the same as a dump that arrived
 * intact -- a truncated file, a mangled encoding or a half-applied restore can
 * all end with a clean exit code and a forum that is quietly missing half its
 * posts. Comparing every recorded row count is cheap and catches all of it.
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

$baseline_options = array('version' => '', 'profile' => '', 'engine' => '');

foreach (array_slice($argv, 1) as $arg)
{
	if (!preg_match('~^--([a-z]+)=(.*)$~', $arg, $match) || !array_key_exists($match[1], $baseline_options))
		baseline_fail('verify.php: unrecognised argument: ' . $arg);

	$baseline_options[$match[1]] = $match[2];
}

$manifest_file = $baseline_dir . '/artifacts/' . $baseline_options['version'] . '/manifest.json';

if (!is_file($manifest_file))
	baseline_fail('verify.php: no manifest at ' . $manifest_file);

$manifest = json_decode(file_get_contents($manifest_file), true);

if (
	!isset($manifest['profiles'][$baseline_options['profile']][$baseline_options['engine']]['row_counts'])
)
	baseline_fail('verify.php: the manifest has no record of ' . $baseline_options['profile'] . '/' . $baseline_options['engine']);

$expected = $manifest['profiles'][$baseline_options['profile']][$baseline_options['engine']]['row_counts'];

baseline_boot($board_dir);

global $smcFunc;

$mismatches = array();

foreach ($expected as $table => $count)
{
	$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}' . $table, array());
	list ($actual) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// log_online is the one table that legitimately drifts: SMF prunes it on
	// every page load, so by the time a restore is checked the rows the dump
	// carried may already be gone.
	if ($table === 'log_online')
		continue;

	if ((int) $actual !== (int) $count)
		$mismatches[] = sprintf('%s: expected %d, found %d', $table, $count, $actual);
}

if (!empty($mismatches))
{
	baseline_say('restored database does not match the manifest:');

	foreach ($mismatches as $mismatch)
		baseline_say('  ' . $mismatch);

	exit(1);
}

baseline_say(sprintf('verify: %d table(s) match the manifest', count($expected) - 1));

exit(0);

?>