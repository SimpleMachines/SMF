<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

/*
 * Bootstrap for the unit test suite.
 *
 * Deliberately minimal: it defines the constants that index.php would define and
 * points the autoloader at Sources/. It does not read Settings.php and it does not
 * connect to a database, so anything reachable from here is reachable without a
 * running forum.
 *
 * Code that needs User::$me or Db::$db is out of scope for this suite and belongs
 * in an integration suite running against a real install.
 */

const TESTS_BOARDDIR = __DIR__ . '/..';

if (!defined('SMF')) {
	define('SMF', 1);
}

if (!defined('SMF_VERSION')) {
	// Read from index.php so the tests never disagree with the release being built.
	preg_match(
		'~define\(\'SMF_VERSION\', \'([^\']+)\'\);~',
		(string) file_get_contents(TESTS_BOARDDIR . '/index.php'),
		$version,
	);

	preg_match(
		'~define\(\'SMF_SOFTWARE_YEAR\', \'(\d{4})\'\);~',
		(string) file_get_contents(TESTS_BOARDDIR . '/index.php'),
		$year,
	);

	define('SMF_VERSION', $version[1] ?? '3.0');
	define('SMF_SOFTWARE_YEAR', $year[1] ?? date('Y'));
}

define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);
define('SMF_USER_AGENT', 'SMF test suite');
define('JQUERY_VERSION', '3.6.3');
define('FONTAWESOME_VERSION', '7.1.0');
define('POSTGRE_TITLE', 'PostgreSQL');
define('MYSQL_TITLE', 'MySQL');
define('TIME_START', microtime(true));
define('SMF_SETTINGS_FILE', TESTS_BOARDDIR . '/Settings.php');
define('SMF_SETTINGS_BACKUP_FILE', TESTS_BOARDDIR . '/Settings_bak.php');

SMF\Config::$loader = require TESTS_BOARDDIR . '/vendor/autoload.php';
SMF\Config::$loader->setPsr4('SMF\\', TESTS_BOARDDIR . '/Sources');
SMF\Config::$loader->setPsr4('SMF\\Themes\\', TESTS_BOARDDIR . '/Themes');
SMF\Config::$loader->setPsr4('SMF\\Tests\\', TESTS_BOARDDIR . '/tests');

/*
 * Paths and the default language, which the Unicode and entity helpers need in
 * order to locate their data files. These are the only pieces of Config the suite
 * sets: no database credentials and nothing read from Settings.php.
 * A test that needs more than this is an integration test.
 */
SMF\Config::$boarddir = (string) realpath(TESTS_BOARDDIR);
SMF\Config::$sourcedir = SMF\Config::$boarddir . '/Sources';
SMF\Config::$packagesdir = SMF\Config::$boarddir . '/Packages';
SMF\Config::$languagesdir = SMF\Config::$boarddir . '/Languages';
SMF\Config::$cachedir = SMF\Config::$boarddir . '/cache';
SMF\Config::$language = 'en_US';
SMF\Config::$boardurl = 'test.local';
SMF\Config::$scripturl = 'test.local/index.php';

// Populate SMF\Config::$modSettings with default values.
SMF\Lang::load('General+Maintenance', SMF\Config::$language);

$replacements = [
	'{$db_prefix}' => 'smf_',
	'{$attachdir}' => json_encode([1 => addslashes(SMF\Config::$boarddir . '/attachments')]),
	'{$boarddir}' => addslashes(SMF\Config::$boarddir),
	'{$boardurl}' => SMF\Config::$boardurl,
	'{$enableCompressedOutput}' => 0,
	'{$databaseSession_enable}' => 0,
	'{$smf_version}' => SMF_VERSION,
	'{$current_time}' => time(),
	'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
	'{$registration_method}' => 0,
];

foreach (SMF\Lang::$txt as $key => $value) {
	if (substr($key, 0, 8) == 'default_') {
		$replacements['{$' . $key . '}'] = addslashes($value);
	}
}

$replacements['{$default_reserved_names}'] = strtr($replacements['{$default_reserved_names}'], ['\\\\n' => "\n", '\\n' => "\n"]);

foreach ((new \ReflectionClass(SMF\Db\Schema\v3_0\Settings::class))->getDefaultProperties()['initial_data'] as $setting) {
	SMF\Config::$modSettings[$setting['variable']] = is_numeric($setting['value']) ? $setting['value'] + 0 : strtr($setting['value'], $replacements);
}
