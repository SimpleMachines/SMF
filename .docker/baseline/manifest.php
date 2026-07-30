<?php

/**
 * Describes a baseline artifact set, so that a restore can be checked rather
 * than merely attempted.
 *
 *   php .docker/baseline/manifest.php --version=2.1.7-1 --profile=small --engine=mysql
 *
 * Writes (and merges into) artifacts/<version>/manifest.json, plus a readable
 * MANIFEST.md alongside it. Row counts are the useful part: restore.sh compares
 * them against the restored database, which is what turns "the file loaded
 * without error" into "the data is all there".
 *
 * Each engine contributes its own section, because the two dumps are taken from
 * separately built forums and their row counts will not be identical -- topics
 * are a statistical by-product of messages, so the numbers land near each other
 * rather than on each other.
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
		baseline_fail('unrecognised argument: ' . $arg);

	$baseline_options[$match[1]] = $match[2];
}

foreach ($baseline_options as $name => $value)
{
	if ($value === '')
		baseline_fail('missing --' . $name);
}

baseline_boot($board_dir);

global $smcFunc, $db_prefix, $modSettings;

$artifact_dir = $baseline_dir . '/artifacts/' . $baseline_options['version'];
$profile_dir = $artifact_dir . '/' . $baseline_options['profile'];

if (!is_dir($profile_dir))
	baseline_fail('no such artifact directory: ' . $profile_dir);

// The tables worth counting: everything the baseline deliberately fills, plus
// the handful a fresh install populates on its own.
$tables = array(
	'attachments', 'ban_groups', 'ban_items', 'boards', 'calendar',
	'calendar_holidays', 'categories', 'custom_fields', 'log_actions',
	'log_banned', 'log_errors', 'log_floodcontrol', 'log_group_requests',
	'log_online', 'log_packages', 'log_polls', 'log_reported',
	'log_reported_comments', 'log_search_subjects', 'log_spider_hits',
	'mail_queue', 'membergroups', 'member_logins', 'members', 'mentions',
	'messages', 'moderators', 'moderator_groups', 'permissions',
	'personal_messages', 'pm_labels', 'pm_recipients', 'pm_rules',
	'poll_choices', 'polls', 'qanda', 'settings', 'smileys', 'themes',
	'topics', 'user_drafts', 'user_likes',
);

$counts = array();

foreach ($tables as $table)
{
	$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}' . $table, array());
	list ($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$counts[$table] = (int) $count;
}

$dump_file = $baseline_options['engine'] === 'mysql' ? 'mysql.sql' : 'postgres.sql';

$files = array();

foreach (array($dump_file, 'files-' . $baseline_options['engine'] . '.tgz') as $name)
{
	$path = $profile_dir . '/' . $name;

	if (is_file($path))
		$files[$name] = array(
			'bytes' => filesize($path),
			'sha256' => hash_file('sha256', $path),
		);
}

// Merge rather than overwrite: the two engines are dumped in separate runs, and
// the second must not erase the first.
$manifest_file = $artifact_dir . '/manifest.json';
$manifest = is_file($manifest_file) ? json_decode(file_get_contents($manifest_file), true) : array();

if (!is_array($manifest))
	$manifest = array();

$manifest['baseline_version'] = $baseline_options['version'];
$manifest['smf_version'] = SMF_VERSION;
$manifest['generated_by'] = 'SMF 2.1 baseline builder (.docker/baseline)';

$manifest['admin'] = array(
	'username' => getenv('BASELINE_ADMIN_USER') ?: 'admin',
	'password' => getenv('BASELINE_ADMIN_PASS') ?: 'baseline',
	'email' => getenv('BASELINE_ADMIN_EMAIL') ?: 'admin@example.com',
);

// Recorded so the 3.0 side does not have to recompute it. The installer derives
// it from the database name and prefix, so it is stable, but it is not obvious.
$manifest['settings'] = array(
	'db_prefix' => $db_prefix,
	'cookiename' => baseline_settings_value($board_dir, 'cookiename'),
	'auth_secret' => baseline_settings_value($board_dir, 'auth_secret'),
	'image_proxy_secret' => baseline_settings_value($board_dir, 'image_proxy_secret'),
	'language' => baseline_settings_value($board_dir, 'language'),
	'db_character_set' => baseline_settings_value($board_dir, 'db_character_set'),
);

if (!isset($manifest['profiles']))
	$manifest['profiles'] = array();

if (!isset($manifest['profiles'][$baseline_options['profile']]))
	$manifest['profiles'][$baseline_options['profile']] = array();

$manifest['profiles'][$baseline_options['profile']][$baseline_options['engine']] = array(
	'generated' => gmdate('c'),
	'php_version' => PHP_VERSION,
	'server_version' => baseline_server_version(),
	'populate_commit' => getenv('POPULATE_COMMIT') ?: null,
	'files' => $files,
	'row_counts' => $counts,
);

file_put_contents(
	$manifest_file,
	json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

baseline_write_readable($artifact_dir, $manifest);

baseline_say('manifest: ' . $baseline_options['profile'] . '/' . $baseline_options['engine'] . ', ' . count($counts) . ' table counts');

exit(0);

/**
 * Reads one value straight out of Settings.php, which is where the pinned
 * secrets live -- they are not in the database.
 *
 * @param string $board_dir
 * @param string $name
 * @return string
 */
function baseline_settings_value($board_dir, $name)
{
	$source = file_get_contents($board_dir . '/Settings.php');

	if (preg_match('~^\$' . preg_quote($name, '~') . " = '([^']*)';~m", $source, $match) !== 1)
		return '';

	return $match[1];
}

/**
 * @return string
 */
function baseline_server_version()
{
	global $smcFunc, $db_type;

	if (strtolower($db_type) === 'postgresql')
	{
		$request = $smcFunc['db_query']('', 'SELECT version()', array());
		list ($version) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return trim(explode(' on ', $version)[0]);
	}

	$request = $smcFunc['db_query']('', 'SELECT VERSION()', array());
	list ($version) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return 'MySQL ' . $version;
}

/**
 * The same information, for people rather than scripts.
 *
 * @param string $artifact_dir
 * @param array $manifest
 */
function baseline_write_readable($artifact_dir, $manifest)
{
	$out = array();
	$out[] = '# SMF ' . $manifest['smf_version'] . ' baseline ' . $manifest['baseline_version'];
	$out[] = '';
	$out[] = 'Generated by `.docker/baseline/make-baseline.sh`. Do not edit by hand:';
	$out[] = 'regenerating produces a new directory and bumps `artifacts/CURRENT`.';
	$out[] = '';
	$out[] = '## What this is';
	$out[] = '';
	$out[] = 'A fully installed SMF ' . $manifest['smf_version'] . ' forum, filled with generated content, dumped';
	$out[] = 'for both database engines. It exists to be the starting point for testing the';
	$out[] = 'SMF 2.1 -> 3.0 upgrade: restore it, point a 3.0 checkout at it, run upgrade.php.';
	$out[] = '';
	$out[] = '## Credentials';
	$out[] = '';
	$out[] = '| | |';
	$out[] = '| --- | --- |';
	$out[] = '| Administrator | `' . $manifest['admin']['username'] . '` |';
	$out[] = '| Password | `' . $manifest['admin']['password'] . '` |';
	$out[] = '| Email | `' . $manifest['admin']['email'] . '` |';
	$out[] = '';
	$out[] = 'Every generated member has the password SMF assigned at registration, which is';
	$out[] = 'to say none of them can log in. That is intentional.';
	$out[] = '';
	$out[] = '## Pinned settings';
	$out[] = '';
	$out[] = 'These are fixed rather than random, because `$auth_secret` lives in Settings.php';
	$out[] = 'and not in the database: restoring the dump beside a fresh random secret would';
	$out[] = 'invalidate every session and two-factor backup code in it. They are dev-only';
	$out[] = 'values for a throwaway forum and must never be reused.';
	$out[] = '';
	$out[] = '| Setting | Value |';
	$out[] = '| --- | --- |';

	foreach ($manifest['settings'] as $name => $value)
		$out[] = '| `$' . $name . '` | `' . $value . '` |';

	$out[] = '';
	$out[] = '## Contents';

	foreach ($manifest['profiles'] as $profile => $engines)
	{
		$out[] = '';
		$out[] = '### Profile: ' . $profile;
		$out[] = '';

		foreach ($engines as $engine => $details)
		{
			$out[] = '**' . $engine . '** -- PHP ' . $details['php_version'] . ', ' . $details['server_version'] . ', generated ' . $details['generated'];
			$out[] = '';

			foreach ($details['files'] as $name => $file)
				$out[] = '- `' . $name . '` ' . baseline_human_size($file['bytes']) . ', sha256 `' . substr($file['sha256'], 0, 16) . '...`';

			$out[] = '';
		}

		// One row-count table per profile is enough; the engines agree to within
		// the topic count.
		$first = reset($engines);

		$out[] = '<details><summary>Row counts</summary>';
		$out[] = '';
		$out[] = '| Table | Rows |';
		$out[] = '| --- | ---: |';

		foreach ($first['row_counts'] as $table => $count)
		{
			if ($count > 0)
				$out[] = '| ' . $table . ' | ' . $count . ' |';
		}

		$out[] = '';
		$out[] = '</details>';
	}

	$out[] = '';

	file_put_contents($artifact_dir . '/MANIFEST.md', implode("\n", $out) . "\n");
}

/**
 * @param int $bytes
 * @return string
 */
function baseline_human_size($bytes)
{
	if ($bytes >= 1048576)
		return round($bytes / 1048576, 1) . ' MB';

	if ($bytes >= 1024)
		return round($bytes / 1024) . ' KB';

	return $bytes . ' bytes';
}
