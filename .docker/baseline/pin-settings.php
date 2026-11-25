<?php

/**
 * Replaces the two secrets the installer randomises with fixed values, and
 * writes out the Settings.baseline.php template that ships with the artifacts.
 *
 *   php .docker/baseline/pin-settings.php <auth_secret> <image_proxy_secret> [<template path>]
 *
 * ForumSettings() generates $auth_secret and $image_proxy_secret with
 * random_bytes() at install time and stores them nowhere except Settings.php.
 * The baseline, however, is a database dump: restore it beside a freshly random
 * $auth_secret and every login cookie, session token and two-factor backup code
 * inside it stops validating. So the generator pins both, and records them in
 * the manifest.
 *
 * These are dev-only secrets for a throwaway forum. They are published in the
 * repository on purpose and must never be reused anywhere real.
 *
 * The template written alongside is Settings.php with the environment-specific
 * values swapped for {{PLACEHOLDERS}}, which restore.sh fills back in. That is
 * what lets one dump be restored into a container with different ports, a
 * different board directory, or a different engine.
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
$settings_file = $board_dir . '/Settings.php';

if (!is_file($settings_file))
	fail('Settings.php does not exist');

if (empty($argv[1]) || empty($argv[2]))
	fail('usage: pin-settings.php <auth_secret> <image_proxy_secret> [<template path>]');

$source = file_get_contents($settings_file);

foreach (array('auth_secret' => $argv[1], 'image_proxy_secret' => $argv[2]) as $name => $value)
	$source = replace_setting($source, $name, $value);

file_put_contents($settings_file, $source);
copy($settings_file, $board_dir . '/Settings_bak.php');

// Now the portable copy. Anything that describes *this* container rather than
// *this forum* becomes a placeholder.
if (!empty($argv[3]))
{
	$template = $source;

	$placeholders = array(
		'db_type' => '{{DB_TYPE}}',
		'db_server' => '{{DB_SERVER}}',
		'db_name' => '{{DB_NAME}}',
		'db_user' => '{{DB_USER}}',
		'db_passwd' => '{{DB_PASSWD}}',
		'boardurl' => '{{BOARDURL}}',
		'boarddir' => '{{BOARDDIR}}',
		'sourcedir' => '{{BOARDDIR}}/Sources',
		'cachedir' => '{{BOARDDIR}}/cache',
		'packagesdir' => '{{BOARDDIR}}/Packages',
		'tasksdir' => '{{BOARDDIR}}/Sources/tasks',
	);

	foreach ($placeholders as $name => $value)
		$template = replace_setting($template, $name, $value);

	// $db_port is an integer, so it needs the unquoted form.
	$template = preg_replace('~^\$db_port = [0-9]+;~m', '$db_port = {{DB_PORT}};', $template, 1, $count);

	if ($count !== 1)
		fail('could not find $db_port in Settings.php');

	$dir = dirname($argv[3]);

	if (!is_dir($dir) && !mkdir($dir, 0777, true))
		fail('cannot create ' . $dir);

	file_put_contents($argv[3], $template);
	echo $argv[3], "\n";
}

exit(0);

/**
 * Rewrites a single quoted scalar assignment, insisting it matched exactly once.
 *
 * @param string $source Contents of Settings.php.
 * @param string $name Variable name, without the sigil.
 * @param string $value Replacement value.
 * @return string
 */
function replace_setting($source, $name, $value)
{
	$result = preg_replace(
		'~^\$' . preg_quote($name, '~') . " = '[^']*';~m",
		'$' . $name . " = '" . $value . "';",
		$source,
		1,
		$count
	);

	if ($count !== 1)
		fail('could not find $' . $name . ' in Settings.php');

	return $result;
}

/**
 * @param string $message
 */
function fail($message)
{
	fwrite(STDERR, '[baseline] pin-settings.php: ' . $message . "\n");
	exit(1);
}

?>