<?php

declare(strict_types=1);

/*
 * Bootstrap for the unit test suite.
 *
 * Deliberately minimal: it defines the constants that index.php would define and
 * points the autoloader at Sources/. It does not read Settings.php and it does not
 * connect to a database, so anything reachable from here is reachable without a
 * running forum.
 *
 * Code that needs Config::$modSettings, User::$me or Db::$db is out of scope for
 * this suite and belongs in an integration suite running against a real install.
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

$loader = require TESTS_BOARDDIR . '/vendor/autoload.php';
$loader->setPsr4('SMF\\', TESTS_BOARDDIR . '/Sources');
$loader->setPsr4('SMF\\Themes\\', TESTS_BOARDDIR . '/Themes');

/*
 * Paths and the default language, which the Unicode and entity helpers need in
 * order to locate their data files. These are the only pieces of Config the suite
 * sets: no modSettings, no database credentials, nothing read from Settings.php.
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
