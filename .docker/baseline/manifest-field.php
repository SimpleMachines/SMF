<?php

/**
 * Prints one recorded checksum out of a baseline manifest.
 *
 *   php .docker/baseline/manifest-field.php --version=2.1.7-1 --profile=small \
 *       --engine=mysql --file=mysql.sql
 *
 * restore.sh needs this before it has a database to talk to, which rules out
 * db.php, and the host is not assumed to have PHP at all -- the whole point of
 * this environment is that Docker is the only requirement. So it runs in the
 * container like everything else, and prints nothing at all when there is
 * nothing recorded, so the caller can simply skip the check.
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

$wanted = array('version' => '', 'profile' => '', 'engine' => '', 'file' => '');

foreach (array_slice($argv, 1) as $arg)
{
	if (preg_match('~^--([a-z]+)=(.*)$~', $arg, $match) === 1 && array_key_exists($match[1], $wanted))
		$wanted[$match[1]] = $match[2];
}

foreach ($wanted as $value)
{
	if ($value === '')
		exit(0);
}

$file = __DIR__ . '/artifacts/' . $wanted['version'] . '/manifest.json';

if (!is_file($file))
	exit(0);

$manifest = json_decode(file_get_contents($file), true);

if (
	!isset($manifest['profiles'][$wanted['profile']][$wanted['engine']]['files'][$wanted['file']]['sha256'])
)
	exit(0);

echo $manifest['profiles'][$wanted['profile']][$wanted['engine']]['files'][$wanted['file']]['sha256'];

exit(0);
