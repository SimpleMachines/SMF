<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Logging;
use SMF\PackageManager\FtpConnection;
use SMF\Security;
use SMF\TaskRunner;
use SMF\Time;
use SMF\Url;
use SMF\User;
use SMF\Utils;

define('SMF_VERSION', '3.0 Alpha 1');
define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);
define('SMF_SOFTWARE_YEAR', '2024');
define('DB_SCRIPT_VERSION', '3-0');
define('SMF_INSTALLING', 1);

define('JQUERY_VERSION', '3.6.3');
define('POSTGRE_TITLE', 'PostgreSQL');
define('MYSQL_TITLE', 'MySQL');
define('SMF_USER_AGENT', 'Mozilla/5.0 (' . php_uname('s') . ' ' . php_uname('m') . ') AppleWebKit/605.1.15 (KHTML, like Gecko)  SMF/' . strtr(SMF_VERSION, ' ', '.'));

if (!defined('TIME_START')) {
	define('TIME_START', microtime(true));
}

define('SMF_SETTINGS_FILE', __DIR__ . '/Settings.php');
define('SMF_SETTINGS_BACKUP_FILE', __DIR__ . '/Settings_bak.php');

$GLOBALS['required_php_version'] = '8.0.0';

// Don't have PHP support, do you?
// ><html dir="ltr"><head><title>Error!</title></head><body>Sorry, this installer requires PHP!<div style="display: none;">

if (!defined('SMF')) {
	define('SMF', 1);
}

// Let's pull in useful classes
require_once 'Sources/Autoloader.php';

// Get the current settings, without affecting global namespace.
Config::$backward_compatibility = false;
Config::load();
Config::$backward_compatibility = true;

Utils::load();

require_once Config::$sourcedir . '/Subs-Compat.php';

// Database info.
$databases = [
	'mysql' => [
		'name' => 'MySQL',
		'version' => '8.0.35',
		'version_check' => function () {
			if (!function_exists('mysqli_fetch_row')) {
				return false;
			}

			return mysqli_fetch_row(mysqli_query(Db::$db->connection, 'SELECT VERSION();'))[0];
		},
		'supported' => function_exists('mysqli_connect'),
		'default_user' => 'mysql.default_user',
		'default_password' => 'mysql.default_password',
		'default_host' => 'mysql.default_host',
		'default_port' => 'mysql.default_port',
		'utf8_support' => function () {
			return true;
		},
		'utf8_version' => '5.0.22',
		'utf8_version_check' => function () {
			return mysqli_get_server_info(Db::$db->connection);
		},
		'alter_support' => true,
		'validate_prefix' => function (&$value) {
			$value = preg_replace('~[^A-Za-z0-9_\$]~', '', $value);

			return true;
		},
	],
	'postgresql' => [
		'name' => 'PostgreSQL',
		'version' => '12.17',
		'version_check' => function () {
			$request = pg_query(Db::$db->connection, 'SELECT version()');
			list($version) = pg_fetch_row($request);
			list($pgl, $version) = explode(' ', $version);

			return $version;
		},
		'supported' => function_exists('pg_connect'),
		'always_has_db' => true,
		'utf8_support' => function () {
			$request = pg_query(Db::$db->connection, 'SHOW SERVER_ENCODING');

			list($charcode) = pg_fetch_row($request);

			return (bool) ($charcode == 'UTF8');
		},
		'utf8_version' => '8.0',
		'utf8_version_check' => function () {
			$request = pg_query(Db::$db->connection, 'SELECT version()');
			list($version) = pg_fetch_row($request);
			list($pgl, $version) = explode(' ', $version);

			return $version;
		},
		'validate_prefix' => function (&$value) {
			$value = preg_replace('~[^A-Za-z0-9_\$]~', '', $value);

			// Is it reserved?
			if ($value == 'pg_') {
				return Lang::$txt['error_db_prefix_reserved'];
			}

			// Is the prefix numeric?
			if (preg_match('~^\d~', $value)) {
				return Lang::$txt['error_db_prefix_numeric'];
			}

			return true;
		},
	],
];

// Initialize everything and load the language files.
initialize_inputs();
load_lang_file();

// This is what we are.
$installurl = $_SERVER['PHP_SELF'];

// All the steps in detail.
// Number,Name,Function,Progress Weight.
$incontext['steps'] = [
	0 => [1, Lang::$txt['install_step_welcome'], 'Welcome', 0],
	1 => [2, Lang::$txt['install_step_writable'], 'CheckFilesWritable', 10],
	2 => [3, Lang::$txt['install_step_databaseset'], 'DatabaseSettings', 15],
	3 => [4, Lang::$txt['install_step_forum'], 'ForumSettings', 40],
	4 => [5, Lang::$txt['install_step_databasechange'], 'DatabasePopulation', 15],
	5 => [6, Lang::$txt['install_step_admin'], 'AdminAccount', 20],
	6 => [7, Lang::$txt['install_step_delete'], 'DeleteInstall', 0],
];

// Default title...
$incontext['page_title'] = Lang::$txt['smf_installer'];

// What step are we on?
$incontext['current_step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;

// Loop through all the steps doing each one as required.
$incontext['overall_percent'] = 0;

foreach ($incontext['steps'] as $num => $step) {
	if ($num >= $incontext['current_step']) {
		// The current weight of this step in terms of overall progress.
		$incontext['step_weight'] = $step[3];
		// Make sure we reset the skip button.
		$incontext['skip'] = false;

		// Call the step and if it returns false that means pause!
		if (function_exists($step[2]) && $step[2]() === false) {
			break;
		}

		if (function_exists($step[2])) {
			$incontext['current_step']++;
		}

		// No warnings pass on.
		$incontext['warning'] = '';
	}
	$incontext['overall_percent'] += $step[3];
}

// Actually do the template stuff.
installExit();

function initialize_inputs()
{
	global $databases;

	// Just so people using older versions of PHP aren't left in the cold.
	if (!isset($_SERVER['PHP_SELF'])) {
		$_SERVER['PHP_SELF'] = $GLOBALS['HTTP_SERVER_VARS']['PHP_SELF'] ?? 'install.php';
	}

	// In pre-release versions, report all errors.
	if (strspn(SMF_VERSION, '1234567890.') !== strlen(SMF_VERSION)) {
		error_reporting(E_ALL);
	}
	// Otherwise, report all errors except for deprecation notices.
	else {
		error_reporting(E_ALL & ~E_DEPRECATED);
	}

	// Fun.  Low PHP version...
	if (!isset($_GET)) {
		$GLOBALS['_GET']['step'] = 0;

		return;
	}

	if (!isset($_GET['obgz'])) {
		ob_start();

		if (ini_get('session.save_handler') == 'user') {
			@ini_set('session.save_handler', 'files');
		}

		if (function_exists('session_start')) {
			@session_start();
		}
	} else {
		ob_start('ob_gzhandler');

		if (ini_get('session.save_handler') == 'user') {
			@ini_set('session.save_handler', 'files');
		}
		session_start();

		if (!headers_sent()) {
			echo '<!DOCTYPE html>
<html>
	<head>
		<title>', htmlspecialchars($_GET['pass_string']), '</title>
	</head>
	<body style="background-color: #d4d4d4; margin-top: 16%; text-align: center; font-size: 16pt;">
		<strong>', htmlspecialchars($_GET['pass_string']), '</strong>
	</body>
</html>';
		}

		exit;
	}

	// This is really quite simple; if ?delete is on the URL, delete the installer...
	if (isset($_GET['delete'])) {
		if (isset($_SESSION['installer_temp_ftp'])) {
			$ftp = new FtpConnection($_SESSION['installer_temp_ftp']['server'], $_SESSION['installer_temp_ftp']['port'], $_SESSION['installer_temp_ftp']['username'], $_SESSION['installer_temp_ftp']['password']);
			$ftp->chdir($_SESSION['installer_temp_ftp']['path']);

			$ftp->unlink('install.php');

			foreach ($databases as $key => $dummy) {
				$type = ($key == 'mysqli') ? 'mysql' : $key;
				$ftp->unlink('install_' . DB_SCRIPT_VERSION . '_' . Db::getClass($type) . '.sql');
			}

			$ftp->close();

			unset($_SESSION['installer_temp_ftp']);
		} else {
			@unlink(__FILE__);

			foreach ($databases as $key => $dummy) {
				$type = ($key == 'mysqli') ? 'mysql' : $key;
				@unlink(Config::$boarddir . '/install_' . DB_SCRIPT_VERSION . '_' . Db::getClass($type) . '.sql');
			}
		}

		// Now just redirect to a blank.png...
		$secure = false;

		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			$secure = true;
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
			$secure = true;
		}

		header('location: http' . ($secure ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/Themes/default/images/blank.png');

		exit;
	}

	// PHP 5 might cry if we don't do this now.
	if (function_exists('date_default_timezone_set')) {
		// Get PHP's default timezone, if set
		$ini_tz = ini_get('date.timezone');

		if (!empty($ini_tz)) {
			$timezone_id = $ini_tz;
		} else {
			$timezone_id = '';
		}

		// If date.timezone is unset, invalid, or just plain weird, make a best guess
		if (!in_array($timezone_id, timezone_identifiers_list())) {
			$server_offset = @mktime(0, 0, 0, 1, 1, 1970) * -1;
			$timezone_id = timezone_name_from_abbr('', $server_offset, 0);

			if (empty($timezone_id)) {
				$timezone_id = 'UTC';
			}
		}

		date_default_timezone_set($timezone_id);
	}
	header('X-Frame-Options: SAMEORIGIN');
	header('X-XSS-Protection: 1');
	header('X-Content-Type-Options: nosniff');

	// Force an integer step, defaulting to 0.
	$_GET['step'] = (int) @$_GET['step'];
}

// Load the list of language files, and the current language file.
function load_lang_file()
{
	global $incontext;

	$incontext['detected_languages'] = [];

	// Make sure the languages directory actually exists.
	if (file_exists(Config::$boarddir . '/Themes/default/languages')) {
		// Find all the "Install" language files in the directory.
		$dir = dir(Config::$boarddir . '/Themes/default/languages');

		while ($entry = $dir->read()) {
			if (substr($entry, 0, 8) == 'Install.' && substr($entry, -4) == '.php') {
				$incontext['detected_languages'][$entry] = ucfirst(substr($entry, 8, strlen($entry) - 12));
			}
		}
		$dir->close();
	}

	// Didn't find any, show an error message!
	if (empty($incontext['detected_languages'])) {
		// Let's not cache this message, eh?
		header('expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('last-modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('cache-control: no-cache');

		echo '<!DOCTYPE html>
<html>
	<head>
		<title>SMF Installer: Error!</title>
		<style>
			body {
				font-family: sans-serif;
				max-width: 700px; }

			h1 {
				font-size: 14pt; }

			.directory {
				margin: 0.3em;
				font-family: monospace;
				font-weight: bold; }
		</style>
	</head>
	<body>
		<h1>A critical error has occurred.</h1>

		<p>This installer was unable to find the installer\'s language file or files. They should be found under:</p>

		<div class="directory">', dirname($_SERVER['PHP_SELF']) != '/' ? dirname($_SERVER['PHP_SELF']) : '', '/Themes/default/languages</div>

		<p>In some cases, FTP clients do not properly upload files with this many folders. Please double check to make sure you <strong>have uploaded all the files in the distribution</strong>.</p>
		<p>If that doesn\'t help, please make sure this install.php file is in the same place as the Themes folder.</p>
		<p>If you continue to get this error message, feel free to <a href="https://support.simplemachines.org/">look to us for support</a>.</p>
	</div></body>
</html>';

		die;
	}

	// Override the language file?
	if (isset($_GET['lang_file'])) {
		$_SESSION['installer_temp_lang'] = $_GET['lang_file'];
	} elseif (isset($GLOBALS['HTTP_GET_VARS']['lang_file'])) {
		$_SESSION['installer_temp_lang'] = $GLOBALS['HTTP_GET_VARS']['lang_file'];
	}

	// Make sure it exists, if it doesn't reset it.
	if (!isset($_SESSION['installer_temp_lang']) || preg_match('~[^\\w_\\-.]~', $_SESSION['installer_temp_lang']) === 1 || !file_exists(Config::$boarddir . '/Themes/default/languages/' . $_SESSION['installer_temp_lang'])) {
		// Use the first one...
		list($_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);

		// If we have english and some other language, use the other language.  We Americans hate english :P.
		if ($_SESSION['installer_temp_lang'] == 'Install.english.php' && count($incontext['detected_languages']) > 1) {
			list(, $_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);
		}
	}

	// Which language are we loading? Assume that the admin likes that language.
	Config::$language = preg_replace('~^Install\.|(-utf8)?\.php$~', '', $_SESSION['installer_temp_lang']);

	// Ensure SMF\Lang knows the path to the language directory.
	Lang::addDirs(Config::$boarddir . '/Themes/default/languages');

	// And now load the language file.
	Lang::load('Install');
}

// This handy function loads some settings and the like.
function load_database()
{
	Config::$modSettings['disableQueryCheck'] = true;

	// Connect the database.
	if (empty(Db::$db->connection)) {
		Db::load();
	}
}

// This is called upon exiting the installer, for template etc.
function installExit($fallThrough = false)
{
	global $incontext, $installurl;

	// Send character set.
	header('content-type: text/html; charset=' . (Lang::$txt['lang_character_set'] ?? 'UTF-8'));

	// We usually dump our templates out.
	if (!$fallThrough) {
		// The top install bit.
		template_install_above();

		// Call the template.
		if (isset($incontext['sub_template'])) {
			$incontext['form_url'] = $installurl . '?step=' . $incontext['current_step'];

			call_user_func('template_' . $incontext['sub_template']);
		}
		// @todo REMOVE THIS!!
		else {
			if (function_exists('doStep' . $_GET['step'])) {
				call_user_func('doStep' . $_GET['step']);
			}
		}
		// Show the footer.
		template_install_below();
	}

	// Bang - gone!
	die();
}

function Welcome()
{
	global $incontext, $databases, $installurl;

	$incontext['page_title'] = Lang::$txt['install_welcome'];
	$incontext['sub_template'] = 'welcome_message';

	// Done the submission?
	if (isset($_POST['contbutt'])) {
		return true;
	}

	// See if we think they have already installed it?
	$probably_installed = 0;

	$settingsDefs = Config::getSettingsDefs();

	foreach (['db_passwd', 'boardurl'] as $var) {
		if (!empty(Config::${$var}) && Config::${$var} != $settingsDefs[$var]['default']) {
			$probably_installed++;
		}
	}

	if ($probably_installed == 2) {
		$incontext['warning'] = Lang::$txt['error_already_installed'];
	}

	// Is some database support even compiled in?
	$incontext['supported_databases'] = [];

	foreach ($databases as $key => $db) {
		if ($db['supported']) {
			$type = ($key == 'mysqli') ? 'mysql' : $key;

			if (!file_exists(Config::$boarddir . '/install_' . DB_SCRIPT_VERSION . '_' . Db::getClass($type) . '.sql')) {
				$databases[$key]['supported'] = false;
				$notFoundSQLFile = true;
				Lang::$txt['error_db_script_missing'] = sprintf(Lang::$txt['error_db_script_missing'], 'install_' . DB_SCRIPT_VERSION . '_' . Db::getClass($type) . '.sql');
			} else {
				$incontext['supported_databases'][] = $db;
			}
		}
	}

	// Check the PHP version.
	if ((!function_exists('version_compare') || version_compare($GLOBALS['required_php_version'], PHP_VERSION, '>='))) {
		$error = 'error_php_too_low';
	}
	// Make sure we have a supported database
	elseif (empty($incontext['supported_databases'])) {
		$error = empty($notFoundSQLFile) ? 'error_db_missing' : 'error_db_script_missing';
	}
	// How about session support?  Some crazy sysadmin remove it?
	elseif (!function_exists('session_start')) {
		$error = 'error_session_missing';
	}
	// Make sure they uploaded all the files.
	elseif (!file_exists(Config::$boarddir . '/index.php')) {
		$error = 'error_missing_files';
	}
	// Very simple check on the session.save_path for Windows.
	// @todo Move this down later if they don't use database-driven sessions?
	elseif (@ini_get('session.save_path') == '/tmp' && substr(__FILE__, 1, 2) == ':\\') {
		$error = 'error_session_save_path';
	}

	// Since each of the three messages would look the same, anyway...
	if (isset($error)) {
		$incontext['error'] = Lang::$txt[$error];
	}

	// Mod_security blocks everything that smells funny. Let SMF handle security.
	if (!fixModSecurity() && !isset($_GET['overmodsecurity'])) {
		$incontext['error'] = Lang::$txt['error_mod_security'] . '<br><br><a href="' . $installurl . '?overmodsecurity=true">' . Lang::$txt['error_message_click'] . '</a> ' . Lang::$txt['error_message_bad_try_again'];
	}

	// Confirm mbstring is loaded...
	if (!extension_loaded('mbstring')) {
		$incontext['error'] = Lang::$txt['install_no_mbstring'];
	}

	// Confirm fileinfo is loaded...
	if (!extension_loaded('fileinfo')) {
		$incontext['error'] = Lang::$txt['install_no_fileinfo'];
	}

	// Check for https stream support.
	$supported_streams = stream_get_wrappers();

	if (!in_array('https', $supported_streams)) {
		$incontext['warning'] = Lang::$txt['install_no_https'];
	}

	return false;
}

function CheckFilesWritable()
{
	global $incontext;

	$incontext['page_title'] = Lang::$txt['ftp_checking_writable'];
	$incontext['sub_template'] = 'chmod_files';

	$writable_files = [
		'attachments',
		'avatars',
		'custom_avatar',
		'cache',
		'Packages',
		'Smileys',
		'Themes',
		'agreement.txt',
		'Settings.php',
		'Settings_bak.php',
		'cache/db_last_error.php',
	];

	foreach ($incontext['detected_languages'] as $lang => $temp) {
		$extra_files[] = 'Themes/default/languages/' . $lang;
	}

	// With mod_security installed, we could attempt to fix it with .htaccess.
	if (function_exists('apache_get_modules') && in_array('mod_security', apache_get_modules())) {
		$writable_files[] = file_exists(Config::$boarddir . '/.htaccess') ? '.htaccess' : '.';
	}

	$failed_files = [];

	// On linux, it's easy - just use is_writable!
	if (substr(__FILE__, 1, 2) != ':\\') {
		$incontext['systemos'] = 'linux';

		foreach ($writable_files as $file) {
			// Some files won't exist, try to address up front
			if (!file_exists(Config::$boarddir . '/' . $file)) {
				@touch(Config::$boarddir . '/' . $file);
			}

			// NOW do the writable check...
			if (!is_writable(Config::$boarddir . '/' . $file)) {
				@chmod(Config::$boarddir . '/' . $file, 0755);

				// Well, 755 hopefully worked... if not, try 777.
				if (!is_writable(Config::$boarddir . '/' . $file) && !@chmod(Config::$boarddir . '/' . $file, 0777)) {
					$failed_files[] = $file;
				}
			}
		}

		foreach ($extra_files as $file) {
			@chmod(Config::$boarddir . (empty($file) ? '' : '/' . $file), 0777);
		}
	}
	// Windows is trickier.  Let's try opening for r+...
	else {
		$incontext['systemos'] = 'windows';

		foreach ($writable_files as $file) {
			// Folders can't be opened for write... but the index.php in them can ;)
			if (is_dir(Config::$boarddir . '/' . $file)) {
				$file .= '/index.php';
			}

			// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
			@chmod(Config::$boarddir . '/' . $file, 0777);
			$fp = @fopen(Config::$boarddir . '/' . $file, 'r+');

			// Hmm, okay, try just for write in that case...
			if (!is_resource($fp)) {
				$fp = @fopen(Config::$boarddir . '/' . $file, 'w');
			}

			if (!is_resource($fp)) {
				$failed_files[] = $file;
			}

			@fclose($fp);
		}

		foreach ($extra_files as $file) {
			@chmod(Config::$boarddir . (empty($file) ? '' : '/' . $file), 0777);
		}
	}

	$failure = count($failed_files) >= 1;

	if (!isset($_SERVER)) {
		return !$failure;
	}

	// Put the list into context.
	$incontext['failed_files'] = $failed_files;

	// It's not going to be possible to use FTP on windows to solve the problem...
	if ($failure && substr(__FILE__, 1, 2) == ':\\') {
		$incontext['error'] = Lang::$txt['error_windows_chmod'] . '
					<ul class="error_content">
						<li>' . implode('</li>
						<li>', $failed_files) . '</li>
					</ul>';

		return false;
	}

	// We're going to have to use... FTP!
	if ($failure) {
		// Load any session data we might have...
		if (!isset($_POST['ftp_username']) && isset($_SESSION['installer_temp_ftp'])) {
			$_POST['ftp_server'] = $_SESSION['installer_temp_ftp']['server'];
			$_POST['ftp_port'] = $_SESSION['installer_temp_ftp']['port'];
			$_POST['ftp_username'] = $_SESSION['installer_temp_ftp']['username'];
			$_POST['ftp_password'] = $_SESSION['installer_temp_ftp']['password'];
			$_POST['ftp_path'] = $_SESSION['installer_temp_ftp']['path'];
		}

		$incontext['ftp_errors'] = [];

		if (isset($_POST['ftp_username'])) {
			$ftp = new FtpConnection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

			if ($ftp->error === false) {
				// Try it without /home/abc just in case they messed up.
				if (!$ftp->chdir($_POST['ftp_path'])) {
					$incontext['ftp_errors'][] = $ftp->last_message;
					$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
				}
			}
		}

		if (!isset($ftp) || $ftp->error !== false) {
			if (!isset($ftp)) {
				$ftp = new FtpConnection(null);
			}
			// Save the error so we can mess with listing...
			elseif ($ftp->error !== false && empty($incontext['ftp_errors']) && !empty($ftp->last_message)) {
				$incontext['ftp_errors'][] = $ftp->last_message;
			}

			list($username, $detect_path, $found_path) = $ftp->detect_path(Config::$boarddir);

			if (empty($_POST['ftp_path']) && $found_path) {
				$_POST['ftp_path'] = $detect_path;
			}

			if (!isset($_POST['ftp_username'])) {
				$_POST['ftp_username'] = $username;
			}

			// Set the username etc, into context.
			$incontext['ftp'] = [
				'server' => $_POST['ftp_server'] ?? 'localhost',
				'port' => $_POST['ftp_port'] ?? '21',
				'username' => $_POST['ftp_username'] ?? '',
				'path' => $_POST['ftp_path'] ?? '/',
				'path_msg' => !empty($found_path) ? Lang::$txt['ftp_path_found_info'] : Lang::$txt['ftp_path_info'],
			];

			return false;
		}


			$_SESSION['installer_temp_ftp'] = [
				'server' => $_POST['ftp_server'],
				'port' => $_POST['ftp_port'],
				'username' => $_POST['ftp_username'],
				'password' => $_POST['ftp_password'],
				'path' => $_POST['ftp_path'],
			];

			$failed_files_updated = [];

			foreach ($failed_files as $file) {
				if (!is_writable(Config::$boarddir . '/' . $file)) {
					$ftp->chmod($file, 0755);
				}

				if (!is_writable(Config::$boarddir . '/' . $file)) {
					$ftp->chmod($file, 0777);
				}

				if (!is_writable(Config::$boarddir . '/' . $file)) {
					$failed_files_updated[] = $file;
					$incontext['ftp_errors'][] = rtrim($ftp->last_message) . ' -> ' . $file . "\n";
				}
			}

			$ftp->close();

			// Are there any errors left?
			if (count($failed_files_updated) >= 1) {
				// Guess there are...
				$incontext['failed_files'] = $failed_files_updated;

				// Set the username etc, into context.
				$incontext['ftp'] = $_SESSION['installer_temp_ftp'] += [
					'path_msg' => Lang::$txt['ftp_path_info'],
				];

				return false;
			}

	}

	return true;
}

function DatabaseSettings()
{
	global $databases, $incontext;

	$incontext['sub_template'] = 'database_settings';
	$incontext['page_title'] = Lang::$txt['db_settings'];
	$incontext['continue'] = 1;

	// Set up the defaults.
	$incontext['db']['server'] = 'localhost';
	$incontext['db']['user'] = '';
	$incontext['db']['name'] = '';
	$incontext['db']['pass'] = '';
	$incontext['db']['type'] = '';
	$incontext['supported_databases'] = [];

	$foundOne = false;

	foreach ($databases as $key => $db) {
		// Override with the defaults for this DB if appropriate.
		if ($db['supported']) {
			$incontext['supported_databases'][$key] = $db;

			if (!$foundOne) {
				if (isset($db['default_host'])) {
					$incontext['db']['server'] = ini_get($db['default_host']) or $incontext['db']['server'] = 'localhost';
				}

				if (isset($db['default_user'])) {
					$incontext['db']['user'] = ini_get($db['default_user']);
					$incontext['db']['name'] = ini_get($db['default_user']);
				}

				if (isset($db['default_password'])) {
					$incontext['db']['pass'] = ini_get($db['default_password']);
				}

				// For simplicity and less confusion, leave the port blank by default
				$incontext['db']['port'] = '';

				$incontext['db']['type'] = $key;
				$foundOne = true;
			}
		}
	}

	// Override for repost.
	if (isset($_POST['db_user'])) {
		$incontext['db']['user'] = $_POST['db_user'];
		$incontext['db']['name'] = $_POST['db_name'];
		$incontext['db']['server'] = $_POST['db_server'];
		$incontext['db']['prefix'] = $_POST['db_prefix'];

		if (!empty($_POST['db_port'])) {
			$incontext['db']['port'] = $_POST['db_port'];
		}
	} else {
		$incontext['db']['prefix'] = 'smf_';
	}

	// Are we submitting?
	if (isset($_POST['db_type'])) {
		// What type are they trying?
		$db_type = preg_replace('~[^A-Za-z0-9]~', '', $_POST['db_type']);
		$db_prefix = $_POST['db_prefix'];
		// Validate the prefix.
		$valid_prefix = $databases[$db_type]['validate_prefix']($db_prefix);

		if ($valid_prefix !== true) {
			$incontext['error'] = $valid_prefix;

			return false;
		}

		// Take care of these variables...
		$vars = [
			'db_type' => $db_type,
			'db_name' => $_POST['db_name'],
			'db_user' => $_POST['db_user'],
			'db_passwd' => $_POST['db_passwd'] ?? '',
			'db_server' => $_POST['db_server'],
			'db_prefix' => $db_prefix,
			// The cookiename is special; we want it to be the same if it ever needs to be reinstalled with the same info.
			'cookiename' => 'SMFCookie' . abs(crc32($_POST['db_name'] . preg_replace('~[^A-Za-z0-9_$]~', '', $_POST['db_prefix'])) % 1000),
		];

		// Only set the port if we're not using the default
		if (!empty($_POST['db_port'])) {
			// For MySQL, we can get the "default port" from PHP. PostgreSQL has no such option though.
			if (($db_type == 'mysql' || $db_type == 'mysqli') && $_POST['db_port'] != ini_get($db_type . '.default_port')) {
				$vars['db_port'] = (int) $_POST['db_port'];
			} elseif ($db_type == 'postgresql' && $_POST['db_port'] != 5432) {
				$vars['db_port'] = (int) $_POST['db_port'];
			}
		}

		// God I hope it saved!
		if (!installer_updateSettingsFile($vars)) {
			$incontext['error'] = Lang::$txt['settings_error'];

			return false;
		}

		// Update SMF\Config with the changes we just saved.
		Config::load();

		// Better find the database file!
		if (!file_exists(Config::$sourcedir . '/Db/APIs/' . Db::getClass(Config::$db_type) . '.php')) {
			$incontext['error'] = sprintf(Lang::$txt['error_db_file'], 'Db/APIs/' . Db::getClass(Config::$db_type) . '.php');

			return false;
		}

		Config::$modSettings['disableQueryCheck'] = true;

		// Attempt a connection.
		$needsDB = !empty($databases[Config::$db_type]['always_has_db']);

		Db::load(['non_fatal' => true, 'dont_select_db' => !$needsDB]);

		// Still no connection?  Big fat error message :P.
		if (!Db::$db->connection) {
			// Get error info...  Recast just in case we get false or 0...
			$error_message = Db::$db->connect_error();

			if (empty($error_message)) {
				$error_message = '';
			}
			$error_number = Db::$db->connect_errno();

			if (empty($error_number)) {
				$error_number = '';
			}
			$db_error = (!empty($error_number) ? $error_number . ': ' : '') . $error_message;

			$incontext['error'] = Lang::$txt['error_db_connect'] . '<div class="error_content"><strong>' . $db_error . '</strong></div>';

			return false;
		}

		// Do they meet the install requirements?
		// @todo Old client, new server?
		if (version_compare($databases[Config::$db_type]['version'], preg_replace('~^\D*|\-.+?$~', '', $databases[Config::$db_type]['version_check']())) > 0) {
			$incontext['error'] = Lang::$txt['error_db_too_low'];

			return false;
		}

		// Let's try that database on for size... assuming we haven't already lost the opportunity.
		if (Db::$db->name != '' && !$needsDB) {
			Db::$db->query(
				'',
				'CREATE DATABASE IF NOT EXISTS `' . Db::$db->name . '`',
				[
					'security_override' => true,
					'db_error_skip' => true,
				],
				Db::$db->connection,
			);

			// Okay, let's try the prefix if it didn't work...
			if (!Db::$db->select(Db::$db->name, Db::$db->connection) && Db::$db->name != '') {
				Db::$db->query(
					'',
					'CREATE DATABASE IF NOT EXISTS `' . Db::$db->prefix . Db::$db->name . '`',
					[
						'security_override' => true,
						'db_error_skip' => true,
					],
					Db::$db->connection,
				);

				if (Db::$db->select(Db::$db->prefix . Db::$db->name, Db::$db->connection)) {
					Db::$db->name = Db::$db->prefix . Db::$db->name;
					installer_updateSettingsFile(['db_name' => Db::$db->name]);
				}
			}

			// Okay, now let's try to connect...
			if (!Db::$db->select(Db::$db->name, Db::$db->connection)) {
				$incontext['error'] = sprintf(Lang::$txt['error_db_database'], Db::$db->name);

				return false;
			}
		}

		return true;
	}

	return false;
}

// Let's start with basic forum type settings.
function ForumSettings()
{
	global $incontext, $databases;

	$incontext['sub_template'] = 'forum_settings';
	$incontext['page_title'] = Lang::$txt['install_settings'];

	// Let's see if we got the database type correct.
	if (isset($_POST['db_type'], $databases[$_POST['db_type']])) {
		Config::$db_type = $_POST['db_type'];
	}

	// Else we'd better be able to get the connection.
	else {
		load_database();
	}

	Config::$db_type = $_POST['db_type'] ?? Config::$db_type;

	// What host and port are we on?
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];

	$secure = false;

	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
		$secure = true;
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
		$secure = true;
	}

	// Now, to put what we've learned together... and add a path.
	$incontext['detected_url'] = 'http' . ($secure ? 's' : '') . '://' . $host . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));

	// Check if the database sessions will even work.
	$incontext['test_dbsession'] = (ini_get('session.auto_start') != 1);

	$incontext['continue'] = 1;

	// Check Postgres setting
	if (Config::$db_type === 'postgresql') {
		load_database();
		$result = Db::$db->query(
			'',
			'show standard_conforming_strings',
			[
				'db_error_skip' => true,
			],
		);

		if ($result !== false) {
			$row = Db::$db->fetch_assoc($result);

			if ($row['standard_conforming_strings'] !== 'on') {
					$incontext['continue'] = 0;
					$incontext['error'] = Lang::$txt['error_pg_scs'];
				}
			Db::$db->free_result($result);
		}
	}

	// Setup the SSL checkbox...
	$incontext['ssl_chkbx_protected'] = false;
	$incontext['ssl_chkbx_checked'] = false;

	// If redirect in effect, force SSL ON.
	$url = new Url($incontext['detected_url']);

	if ($url->redirectsToHttps()) {
		$incontext['ssl_chkbx_protected'] = true;
		$incontext['ssl_chkbx_checked'] = true;
		$_POST['force_ssl'] = true;
	}

	// If no cert, make sure SSL stays OFF.
	if (!$url->hasSSL()) {
		$incontext['ssl_chkbx_protected'] = true;
		$incontext['ssl_chkbx_checked'] = false;
	}

	// Submitting?
	if (isset($_POST['boardurl'])) {
		if (substr($_POST['boardurl'], -10) == '/index.php') {
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
		} elseif (substr($_POST['boardurl'], -1) == '/') {
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
		}

		if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://') {
			$_POST['boardurl'] = 'http://' . $_POST['boardurl'];
		}

		// Make sure boardurl is aligned with ssl setting
		if (empty($_POST['force_ssl'])) {
			$_POST['boardurl'] = strtr($_POST['boardurl'], ['https://' => 'http://']);
		} else {
			$_POST['boardurl'] = strtr($_POST['boardurl'], ['http://' => 'https://']);
		}

		// Make sure international domain names are normalized correctly.
		if (Lang::$txt['lang_character_set'] == 'UTF-8') {
			$_POST['boardurl'] = (string) new Url($_POST['boardurl'], true);
		}

		// Deal with different operating systems' directory structure...
		$path = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', __DIR__), '/');

		// Save these variables.
		$vars = [
			'boardurl' => $_POST['boardurl'],
			'boarddir' => $path,
			'sourcedir' => $path . '/Sources',
			'cachedir' => $path . '/cache',
			'packagesdir' => $path . '/Packages',
			'tasksdir' => $path . '/Sources/Tasks',
			'mbname' => strtr($_POST['mbname'], ['\"' => '"']),
			'language' => substr($_SESSION['installer_temp_lang'], 8, -4),
			'image_proxy_secret' => bin2hex(random_bytes(10)),
			'image_proxy_enabled' => !empty($_POST['force_ssl']),
			'auth_secret' => bin2hex(random_bytes(32)),
		];

		// Must save!
		if (!installer_updateSettingsFile($vars)) {
			$incontext['error'] = Lang::$txt['settings_error'];

			return false;
		}

		// Update SMF\Config with the changes we just saved.
		Config::load();

		// UTF-8 requires a setting to override the language charset.
		if (!$databases[Config::$db_type]['utf8_support']()) {
			$incontext['error'] = sprintf(Lang::$txt['error_utf8_support']);

			return false;
		}

		if (!empty($databases[Config::$db_type]['utf8_version_check']) && version_compare($databases[Config::$db_type]['utf8_version'], preg_replace('~\-.+?$~', '', $databases[Config::$db_type]['utf8_version_check']()), '>')) {
			$incontext['error'] = sprintf(Lang::$txt['error_utf8_version'], $databases[Config::$db_type]['utf8_version']);

			return false;
		}

		// Set the character set here.
		installer_updateSettingsFile(['db_character_set' => 'utf8'], true);

		// Good, skip on.
		return true;
	}

	return false;
}

// Step one: Do the SQL thang.
function DatabasePopulation()
{
	global $databases, $incontext;

	$incontext['sub_template'] = 'populate_database';
	$incontext['page_title'] = Lang::$txt['db_populate'];
	$incontext['continue'] = 1;

	// Already done?
	if (isset($_POST['pop_done'])) {
		return true;
	}

	// Reload settings.
	Config::load();
	load_database();

	// Before running any of the queries, let's make sure another version isn't already installed.
	$result = Db::$db->query(
		'',
		'SELECT variable, value
		FROM {db_prefix}settings',
		[
			'db_error_skip' => true,
		],
	);
	$newSettings = [];

	if ($result !== false) {
		while ($row = Db::$db->fetch_assoc($result)) {
			Config::$modSettings[$row['variable']] = $row['value'];
		}

		Db::$db->free_result($result);

		// Do they match?  If so, this is just a refresh so charge on!
		if (!isset(Config::$modSettings['smfVersion']) || Config::$modSettings['smfVersion'] != SMF_VERSION) {
			$incontext['error'] = Lang::$txt['error_versions_do_not_match'];

			return false;
		}
	}
	Config::$modSettings['disableQueryCheck'] = true;

	// If doing UTF8, select it. PostgreSQL requires passing it as a string...
	Db::$db->query(
		'',
		'SET NAMES {string:utf8}',
		[
			'db_error_skip' => true,
			'utf8' => 'utf8',
		],
	);

	// Windows likes to leave the trailing slash, which yields to C:\path\to\SMF\/attachments...
	if (substr(__DIR__, -1) == '\\') {
		$attachdir = __DIR__ . 'attachments';
	} else {
		$attachdir = __DIR__ . '/attachments';
	}

	$replaces = [
		'{$db_prefix}' => Db::$db->prefix,
		'{$attachdir}' => json_encode([1 => Db::$db->escape_string($attachdir)]),
		'{$boarddir}' => Db::$db->escape_string(Config::$boarddir),
		'{$boardurl}' => Config::$boardurl,
		'{$enableCompressedOutput}' => isset($_POST['compress']) ? '1' : '0',
		'{$databaseSession_enable}' => isset($_POST['dbsession']) ? '1' : '0',
		'{$smf_version}' => SMF_VERSION,
		'{$current_time}' => time(),
		'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
		'{$registration_method}' => $_POST['reg_mode'] ?? 0,
	];

	foreach (Lang::$txt as $key => $value) {
		if (substr($key, 0, 8) == 'default_') {
			$replaces['{$' . $key . '}'] = Db::$db->escape_string($value);
		}
	}
	$replaces['{$default_reserved_names}'] = strtr($replaces['{$default_reserved_names}'], ['\\\\n' => '\\n']);

	// MySQL-specific stuff - storage engine and UTF8 handling
	if (substr(Config::$db_type, 0, 5) == 'mysql') {
		// Just in case the query fails for some reason...
		$engines = [];

		// Figure out storage engines - what do we have, etc.
		$get_engines = Db::$db->query('', 'SHOW ENGINES', []);

		while ($row = Db::$db->fetch_assoc($get_engines)) {
			if ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT') {
				$engines[] = $row['Engine'];
			}
		}

		// Done with this now
		Db::$db->free_result($get_engines);

		// InnoDB is better, so use it if possible...
		$has_innodb = in_array('InnoDB', $engines);
		$replaces['{$engine}'] = $has_innodb ? 'InnoDB' : 'MyISAM';
		$replaces['{$memory}'] = (!$has_innodb && in_array('MEMORY', $engines)) ? 'MEMORY' : $replaces['{$engine}'];

		// UTF-8 is required.
		$replaces['{$engine}'] .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
		$replaces['{$memory}'] .= ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

		// One last thing - if we don't have InnoDB, we can't do transactions...
		if (!$has_innodb) {
			$replaces['START TRANSACTION;'] = '';
			$replaces['COMMIT;'] = '';
		}
	} else {
		$has_innodb = false;
	}

	// Read in the SQL.  Turn this on and that off... internationalize... etc.
	$type = (Config::$db_type == 'mysqli' ? 'mysql' : Config::$db_type);
	$sql_lines = explode("\n", strtr(implode(' ', file(Config::$boarddir . '/install_' . DB_SCRIPT_VERSION . '_' . Db::getClass($type) . '.sql')), $replaces));

	// Execute the SQL.
	$current_statement = '';
	$exists = [];
	$incontext['failures'] = [];
	$incontext['sql_results'] = [
		'tables' => 0,
		'inserts' => 0,
		'table_dups' => 0,
		'insert_dups' => 0,
	];

	foreach ($sql_lines as $count => $line) {
		// No comments allowed!
		if (substr(trim($line), 0, 1) != '#') {
			$current_statement .= "\n" . rtrim($line);
		}

		// Is this the end of the query string?
		if (empty($current_statement) || (preg_match('~;[\s]*$~s', $line) == 0 && $count != count($sql_lines))) {
			continue;
		}

		// Does this table already exist?  If so, don't insert more data into it!
		if (preg_match('~^\s*INSERT INTO ([^\s\n\r]+?)~', $current_statement, $match) != 0 && in_array($match[1], $exists)) {
			preg_match_all('~\)[,;]~', $current_statement, $matches);

			if (!empty($matches[0])) {
				$incontext['sql_results']['insert_dups'] += count($matches[0]);
			} else {
				$incontext['sql_results']['insert_dups']++;
			}

			$current_statement = '';

			continue;
		}

		if (Db::$db->query('', $current_statement, ['security_override' => true, 'db_error_skip' => true], Db::$db->connection) === false) {
			// Error 1050: Table already exists!
			// @todo Needs to be made better!
			if (((Config::$db_type != 'mysql' && Config::$db_type != 'mysqli') || mysqli_errno(Db::$db->connection) == 1050) && preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1) {
				$exists[] = $match[1];
				$incontext['sql_results']['table_dups']++;
			}
			// Don't error on duplicate indexes (or duplicate operators in PostgreSQL.)
			elseif (!preg_match('~^\s*CREATE( UNIQUE)? INDEX ([^\n\r]+?)~', $current_statement, $match) && !(Config::$db_type == 'postgresql' && preg_match('~^\s*CREATE OPERATOR (^\n\r]+?)~', $current_statement, $match))) {
				// MySQLi requires a connection object. It's optional with MySQL and Postgres
				$incontext['failures'][$count] = Db::$db->error(Db::$db->connection);
			}
		} else {
			if (preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1) {
				$incontext['sql_results']['tables']++;
			} elseif (preg_match('~^\s*INSERT INTO ([^\s\n\r]+?)~', $current_statement, $match) == 1) {
				preg_match_all('~\)[,;]~', $current_statement, $matches);

				if (!empty($matches[0])) {
					$incontext['sql_results']['inserts'] += count($matches[0]);
				} else {
					$incontext['sql_results']['inserts']++;
				}
			}
		}

		$current_statement = '';

		// Wait, wait, I'm still working here!
		@set_time_limit(60);
	}

	// Sort out the context for the SQL.
	foreach ($incontext['sql_results'] as $key => $number) {
		if ($number == 0) {
			unset($incontext['sql_results'][$key]);
		} else {
			$incontext['sql_results'][$key] = sprintf(Lang::$txt['db_populate_' . $key], $number);
		}
	}

	// Make sure UTF will be used globally.
	$newSettings[] = ['global_character_set', 'UTF-8'];

	// Are we allowing stat collection?
	if (!empty($_POST['stats']) && substr(Config::$boardurl, 0, 16) != 'http://localhost' && empty(Config::$modSettings['allow_sm_stats']) && empty(Config::$modSettings['enable_sm_stats'])) {
		$incontext['allow_sm_stats'] = true;

		// Attempt to register the site etc.
		$fp = @fsockopen('www.simplemachines.org', 443, $errno, $errstr);

		if (!$fp) {
			$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
		}

		if ($fp) {
			$out = 'GET /smf/stats/register_stats.php?site=' . base64_encode(Config::$boardurl) . ' HTTP/1.1' . "\r\n";
			$out .= 'Host: www.simplemachines.org' . "\r\n";
			$out .= 'Connection: Close' . "\r\n\r\n";
			fwrite($fp, $out);

			$return_data = '';

			while (!feof($fp)) {
				$return_data .= fgets($fp, 128);
			}

			fclose($fp);

			// Get the unique site ID.
			preg_match('~SITE-ID:\s(\w{10})~', $return_data, $ID);

			if (!empty($ID[1])) {
				Db::$db->insert(
					'replace',
					Db::$db->prefix . 'settings',
					['variable' => 'string', 'value' => 'string'],
					[
						['sm_stats_key', $ID[1]],
						['enable_sm_stats', 1],
					],
					['variable'],
				);
			}
		}
	}
	// Don't remove stat collection unless we unchecked the box for real, not from the loop.
	elseif (empty($_POST['stats']) && empty($incontext['allow_sm_stats'])) {
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}settings
			WHERE variable = {string:enable_sm_stats}',
			[
				'enable_sm_stats' => 'enable_sm_stats',
				'db_error_skip' => true,
			],
		);
	}

	// Are we enabling SSL?
	if (!empty($_POST['force_ssl'])) {
		$newSettings[] = ['force_ssl', 1];
	}

	// Setting a timezone is required.
	if (!isset(Config::$modSettings['default_timezone']) && function_exists('date_default_timezone_set')) {
		// Get PHP's default timezone, if set
		$ini_tz = ini_get('date.timezone');

		if (!empty($ini_tz)) {
			$timezone_id = $ini_tz;
		} else {
			$timezone_id = '';
		}

		// If date.timezone is unset, invalid, or just plain weird, make a best guess
		if (!in_array($timezone_id, timezone_identifiers_list())) {
			$server_offset = @mktime(0, 0, 0, 1, 1, 1970) * -1;
			$timezone_id = timezone_name_from_abbr('', $server_offset, 0);

			if (empty($timezone_id)) {
				$timezone_id = 'UTC';
			}
		}

		if (date_default_timezone_set($timezone_id)) {
			$newSettings[] = ['default_timezone', $timezone_id];
		}
	}

	if (!empty($newSettings)) {
		Db::$db->insert(
			'replace',
			'{db_prefix}settings',
			['variable' => 'string-255', 'value' => 'string-65534'],
			$newSettings,
			['variable'],
		);
	}

	// Populate the smiley_files table.
	// Can't just dump this data in the SQL file because we need to know the id for each smiley.
	$smiley_filenames = [
		':)' => 'smiley',
		';)' => 'wink',
		':D' => 'cheesy',
		';D' => 'grin',
		'>:(' => 'angry',
		':(' => 'sad',
		':o' => 'shocked',
		'8)' => 'cool',
		'???' => 'huh',
		'::)' => 'rolleyes',
		':P' => 'tongue',
		':-[' => 'embarrassed',
		':-X' => 'lipsrsealed',
		':-\\' => 'undecided',
		':-*' => 'kiss',
		':\'(' => 'cry',
		'>:D' => 'evil',
		'^-^' => 'azn',
		'O0' => 'afro',
		':))' => 'laugh',
		'C:-)' => 'police',
		'O:-)' => 'angel',
	];
	$smiley_set_extensions = ['fugue' => '.png', 'alienine' => '.png'];

	$smiley_inserts = [];
	$request = Db::$db->query(
		'',
		'SELECT id_smiley, code
		FROM {db_prefix}smileys',
		[],
	);

	while ($row = Db::$db->fetch_assoc($request)) {
		foreach ($smiley_set_extensions as $set => $ext) {
			$smiley_inserts[] = [$row['id_smiley'], $set, $smiley_filenames[$row['code']] . $ext];
		}
	}
	Db::$db->free_result($request);

	Db::$db->insert(
		'ignore',
		'{db_prefix}smiley_files',
		['id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48'],
		$smiley_inserts,
		['id_smiley', 'smiley_set'],
	);

	// Let's optimize those new tables, but not on InnoDB, ok?
	if (!$has_innodb) {
		$tables = Db::$db->list_tables(Db::$db->name, Db::$db->prefix . '%');

		foreach ($tables as $table) {
			Db::$db->optimize_table($table) != -1 or $db_messed = true;

			if (!empty($db_messed)) {
				$incontext['failures'][-1] = Db::$db->error();
				break;
			}
		}
	}

	// MySQL specific stuff
	if (substr(Config::$db_type, 0, 5) != 'mysql') {
		return false;
	}

	// Find database user privileges.
	$privs = [];
	$get_privs = Db::$db->query('', 'SHOW PRIVILEGES', []);

	while ($row = Db::$db->fetch_assoc($get_privs)) {
		if ($row['Privilege'] == 'Alter') {
			$privs[] = $row['Privilege'];
		}
	}
	Db::$db->free_result($get_privs);

	// Check for the ALTER privilege.
	if (!empty($databases[Config::$db_type]['alter_support']) && !in_array('Alter', $privs)) {
		$incontext['error'] = Lang::$txt['error_db_alter_priv'];

		return false;
	}

	if (!empty($exists)) {
		$incontext['page_title'] = Lang::$txt['user_refresh_install'];
		$incontext['was_refresh'] = true;
	}

	return false;
}

// Ask for the administrator login information.
function AdminAccount()
{
	global $incontext;

	$incontext['sub_template'] = 'admin_account';
	$incontext['page_title'] = Lang::$txt['user_settings'];
	$incontext['continue'] = 1;

	// Skipping?
	if (!empty($_POST['skip'])) {
		return true;
	}

	// Need this to check whether we need the database password.
	Config::load();
	load_database();

	$settingsDefs = Config::getSettingsDefs();

	// Reload $modSettings.
	Config::reloadModSettings();

	if (!isset($_POST['username'])) {
		$_POST['username'] = '';
	}

	if (!isset($_POST['email'])) {
		$_POST['email'] = '';
	}

	if (!isset($_POST['server_email'])) {
		$_POST['server_email'] = '';
	}

	$incontext['username'] = htmlspecialchars($_POST['username']);
	$incontext['email'] = htmlspecialchars($_POST['email']);
	$incontext['server_email'] = htmlspecialchars($_POST['server_email']);

	$incontext['require_db_confirm'] = empty(Config::$db_type);

	// Only allow skipping if we think they already have an account setup.
	$request = Db::$db->query(
		'',
		'SELECT id_member
		FROM {db_prefix}members
		WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
		LIMIT 1',
		[
			'db_error_skip' => true,
			'admin_group' => 1,
		],
	);

	if (Db::$db->num_rows($request) != 0) {
		$incontext['skip'] = 1;
	}
	Db::$db->free_result($request);

	// Trying to create an account?
	if (isset($_POST['password1']) && !empty($_POST['contbutt'])) {
		// Wrong password?
		if ($incontext['require_db_confirm'] && $_POST['password3'] != Config::$db_passwd) {
			$incontext['error'] = Lang::$txt['error_db_connect'];

			return false;
		}

		// Not matching passwords?
		if ($_POST['password1'] != $_POST['password2']) {
			$incontext['error'] = Lang::$txt['error_user_settings_again_match'];

			return false;
		}

		// No password?
		if (strlen($_POST['password1']) < 4) {
			$incontext['error'] = Lang::$txt['error_user_settings_no_password'];

			return false;
		}

		if (!file_exists(Config::$sourcedir . '/Utils.php')) {
			$incontext['error'] = sprintf(Lang::$txt['error_sourcefile_missing'], 'Utils.php');

			return false;
		}

		// Update the webmaster's email?
		if (!empty($_POST['server_email']) && (empty(Config::$webmaster_email) || Config::$webmaster_email == $settingsDefs['webmaster_email']['default'])) {
			installer_updateSettingsFile(['webmaster_email' => $_POST['server_email']]);
		}

		// Work out whether we're going to have dodgy characters and remove them.
		$invalid_characters = preg_match('~[<>&"\'=\\\]~', $_POST['username']) != 0;
		$_POST['username'] = preg_replace('~[<>&"\'=\\\]~', '', $_POST['username']);

		$result = Db::$db->query(
			'',
			'SELECT id_member, password_salt
			FROM {db_prefix}members
			WHERE member_name = {string:username} OR email_address = {string:email}
			LIMIT 1',
			[
				'username' => $_POST['username'],
				'email' => $_POST['email'],
				'db_error_skip' => true,
			],
		);

		if (Db::$db->num_rows($result) != 0) {
			list($incontext['member_id'], $incontext['member_salt']) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			$incontext['account_existed'] = Lang::$txt['error_user_settings_taken'];
		} elseif ($_POST['username'] == '' || strlen($_POST['username']) > 25) {
			// Try the previous step again.
			$incontext['error'] = $_POST['username'] == '' ? Lang::$txt['error_username_left_empty'] : Lang::$txt['error_username_too_long'];

			return false;
		} elseif ($invalid_characters || $_POST['username'] == '_' || $_POST['username'] == '|' || strpos($_POST['username'], '[code') !== false || strpos($_POST['username'], '[/code') !== false) {
			// Try the previous step again.
			$incontext['error'] = Lang::$txt['error_invalid_characters_username'];

			return false;
		} elseif (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) || strlen($_POST['email']) > 255) {
			// One step back, this time fill out a proper admin email address.
			$incontext['error'] = sprintf(Lang::$txt['error_valid_admin_email_needed'], $_POST['username']);

			return false;
		} elseif (empty($_POST['server_email']) || !filter_var($_POST['server_email'], FILTER_VALIDATE_EMAIL) || strlen($_POST['server_email']) > 255) {
			// One step back, this time fill out a proper admin email address.
			$incontext['error'] = Lang::$txt['error_valid_server_email_needed'];

			return false;
		} elseif ($_POST['username'] != '') {
			$incontext['member_salt'] = bin2hex(random_bytes(16));

			// Format the username properly.
			$_POST['username'] = preg_replace('~[\t\n\r\x0B\0\xA0]+~', ' ', $_POST['username']);
			$ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 255) : '';

			$_POST['password1'] = Security::hashPassword($_POST['username'], $_POST['password1']);

			$incontext['member_id'] = Db::$db->insert(
				'',
				Db::$db->prefix . 'members',
				[
					'member_name' => 'string-25',
					'real_name' => 'string-25',
					'passwd' => 'string',
					'email_address' => 'string',
					'id_group' => 'int',
					'posts' => 'int',
					'date_registered' => 'int',
					'password_salt' => 'string',
					'lngfile' => 'string',
					'personal_text' => 'string',
					'avatar' => 'string',
					'member_ip' => 'inet',
					'member_ip2' => 'inet',
					'buddy_list' => 'string',
					'pm_ignore_list' => 'string',
					'website_title' => 'string',
					'website_url' => 'string',
					'signature' => 'string',
					'usertitle' => 'string',
					'secret_question' => 'string',
					'additional_groups' => 'string',
					'ignore_boards' => 'string',
				],
				[
					$_POST['username'],
					$_POST['username'],
					$_POST['password1'],
					$_POST['email'],
					1,
					0,
					time(),
					$incontext['member_salt'],
					'',
					'',
					'',
					$ip,
					$ip,
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					'',
					'',
				],
				['id_member'],
				1,
			);
		}

		// If we're here we're good.
		return true;
	}

	return false;
}

// Final step, clean up and a complete message!
function DeleteInstall()
{
	global $incontext;
	global $databases;

	$incontext['page_title'] = Lang::$txt['congratulations'];
	$incontext['sub_template'] = 'delete_install';
	$incontext['continue'] = 0;

	Config::load();
	load_database();

	chdir(Config::$boarddir);

	// Reload $modSettings.
	Config::reloadModSettings();

	// Bring a warning over.
	if (!empty($incontext['account_existed'])) {
		$incontext['warning'] = $incontext['account_existed'];
	}

	Db::$db->query(
		'',
		'SET NAMES {string:db_character_set}',
		[
			'db_character_set' => Config::$db_character_set,
			'db_error_skip' => true,
		],
	);

	// As track stats is by default enabled let's add some activity.
	Db::$db->insert(
		'ignore',
		'{db_prefix}log_activity',
		['date' => 'date', 'topics' => 'int', 'posts' => 'int', 'registers' => 'int'],
		[Time::strftime('%Y-%m-%d', time()), 1, 1, (!empty($incontext['member_id']) ? 1 : 0)],
		['date'],
	);

	// We're going to want our lovely Config::$modSettings now.
	$request = Db::$db->query(
		'',
		'SELECT variable, value
		FROM {db_prefix}settings',
		[
			'db_error_skip' => true,
		],
	);

	// Only proceed if we can load the data.
	if ($request) {
		while ($row = Db::$db->fetch_row($request)) {
			Config::$modSettings[$row[0]] = $row[1];
		}
		Db::$db->free_result($request);
	}

	// Automatically log them in ;)
	if (isset($incontext['member_id'], $incontext['member_salt'])) {
		Cookie::setLoginCookie(3153600 * 60, $incontext['member_id'], Cookie::encrypt($_POST['password1'], $incontext['member_salt']));
	}

	$result = Db::$db->query(
		'',
		'SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:db_sessions}',
		[
			'db_sessions' => 'databaseSession_enable',
			'db_error_skip' => true,
		],
	);

	if (Db::$db->num_rows($result) != 0) {
		list($db_sessions) = Db::$db->fetch_row($result);
	}
	Db::$db->free_result($result);

	if (empty($db_sessions)) {
		$_SESSION['admin_time'] = time();
	} else {
		$_SERVER['HTTP_USER_AGENT'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 211);

		Db::$db->insert(
			'replace',
			'{db_prefix}sessions',
			[
				'session_id' => 'string', 'last_update' => 'int', 'data' => 'string',
			],
			[
				session_id(), time(), 'USER_AGENT|s:' . strlen($_SERVER['HTTP_USER_AGENT']) . ':"' . $_SERVER['HTTP_USER_AGENT'] . '";admin_time|i:' . time() . ';',
			],
			['session_id'],
		);
	}

	Logging::updateStats('member');
	Logging::updateStats('message');
	Logging::updateStats('topic');

	$request = Db::$db->query(
		'',
		'SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_msg = 1
			AND modified_time = 0
		LIMIT 1',
		[
			'db_error_skip' => true,
		],
	);
	Utils::$context['utf8'] = true;

	if (Db::$db->num_rows($request) > 0) {
		Logging::updateStats('subject', 1, htmlspecialchars(Lang::$txt['default_topic_subject']));
	}
	Db::$db->free_result($request);

	// Now is the perfect time to fetch the SM files.
	// Sanity check that they loaded earlier!
	if (isset(Config::$modSettings['recycle_board'])) {
		(new TaskRunner())->runScheduledTasks(['fetchSMfiles']); // Now go get those files!

		// We've just installed!
		if (isset($incontext['member_id'])) {
			User::setMe($incontext['member_id']);
		} else {
			User::load();
		}

		User::$me->ip = $_SERVER['REMOTE_ADDR'];

		Logging::logAction('install', ['version' => SMF_FULL_VERSION], 'admin');
	}

	// Disable the legacy BBC by default for new installs
	Config::updateModSettings([
		'disabledBBC' => implode(',', Utils::$context['legacy_bbc']),
	]);

	// Some final context for the template.
	$incontext['dir_still_writable'] = is_writable(Config::$boarddir) && substr(__FILE__, 1, 2) != ':\\';
	$incontext['probably_delete_install'] = isset($_SESSION['installer_temp_ftp']) || is_writable(Config::$boarddir) || is_writable(__FILE__);

	// Update hash's cost to an appropriate setting
	Config::updateModSettings([
		'bcrypt_hash_cost' => Security::hashBenchmark(),
	]);

	return false;
}

function installer_updateSettingsFile($vars, $rebuild = false)
{
	if (!is_writable(SMF_SETTINGS_FILE)) {
		@chmod(SMF_SETTINGS_FILE, 0777);

		if (!is_writable(SMF_SETTINGS_FILE)) {
			return false;
		}
	}

	return Config::updateSettingsFile($vars, false, $rebuild);
}

// Create an .htaccess file to prevent mod_security. SMF has filtering built-in.
function fixModSecurity()
{
	$htaccess_addition = '
<IfModule mod_security.c>
	# Turn off mod_security filtering.  SMF is a big boy, it doesn\'t need its hands held.
	SecFilterEngine Off

	# The below probably isn\'t needed, but better safe than sorry.
	SecFilterScanPOST Off
</IfModule>';

	if (!function_exists('apache_get_modules') || !in_array('mod_security', apache_get_modules())) {
		return true;
	}

	if (file_exists(Config::$boarddir . '/.htaccess') && is_writable(Config::$boarddir . '/.htaccess')) {
		$current_htaccess = implode('', file(Config::$boarddir . '/.htaccess'));

		// Only change something if mod_security hasn't been addressed yet.
		if (strpos($current_htaccess, '<IfModule mod_security.c>') === false) {
			if ($ht_handle = fopen(Config::$boarddir . '/.htaccess', 'a')) {
				fwrite($ht_handle, $htaccess_addition);
				fclose($ht_handle);

				return true;
			}

				return false;
		}

			return true;
	} elseif (file_exists(Config::$boarddir . '/.htaccess')) {
		return strpos(implode('', file(Config::$boarddir . '/.htaccess')), '<IfModule mod_security.c>') !== false;
	} elseif (is_writable(Config::$boarddir)) {
		if ($ht_handle = fopen(Config::$boarddir . '/.htaccess', 'w')) {
			fwrite($ht_handle, $htaccess_addition);
			fclose($ht_handle);

			return true;
		}

			return false;
	}

		return false;
}

function template_install_above()
{
	global $incontext, $installurl;

	echo '<!DOCTYPE html>
<html', Lang::$txt['lang_rtl'] == '1' ? ' dir="rtl"' : '', '>
<head>
	<meta charset="', Lang::$txt['lang_character_set'] ?? 'UTF-8', '">
	<meta name="robots" content="noindex">
	<title>', Lang::$txt['smf_installer'], '</title>
	<link rel="stylesheet" href="Themes/default/css/index.css">
	<link rel="stylesheet" href="Themes/default/css/install.css">
	', Lang::$txt['lang_rtl'] == '1' ? '<link rel="stylesheet" href="Themes/default/css/rtl.css">' : '', '

	<script src="Themes/default/scripts/jquery-' . JQUERY_VERSION . '.min.js"></script>
	<script src="Themes/default/scripts/script.js"></script>
</head>
<body>
	<div id="footerfix">
	<div id="header">
		<h1 class="forumtitle">', Lang::$txt['smf_installer'], '</h1>
		<img id="smflogo" src="Themes/default/images/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">
	</div>
	<div id="wrapper">';

	// Have we got a language drop down - if so do it on the first step only.
	if (!empty($incontext['detected_languages']) && count($incontext['detected_languages']) > 1 && $incontext['current_step'] == 0) {
		echo '
		<div id="upper_section">
			<div id="inner_section">
				<div id="inner_wrap">
					<div class="news">
						<form action="', $installurl, '" method="get">
							<label for="installer_language">', Lang::$txt['installer_language'], ':</label>
							<select id="installer_language" name="lang_file" onchange="location.href = \'', $installurl, '?lang_file=\' + this.options[this.selectedIndex].value;">';

		foreach ($incontext['detected_languages'] as $lang => $name) {
			echo '
								<option', isset($_SESSION['installer_temp_lang']) && $_SESSION['installer_temp_lang'] == $lang ? ' selected' : '', ' value="', $lang, '">', $name, '</option>';
		}

		echo '
							</select>
							<noscript><input type="submit" value="', Lang::$txt['installer_language_set'], '" class="button"></noscript>
						</form>
					</div><!-- .news -->
					<hr class="clear">
				</div><!-- #inner_wrap -->
			</div><!-- #inner_section -->
		</div><!-- #upper_section -->';
	}

	echo '
		<div id="content_section">
			<div id="main_content_section">
				<div id="main_steps">
					<h2>', Lang::$txt['upgrade_progress'], '</h2>
					<ul class="steps_list">';

	foreach ($incontext['steps'] as $num => $step) {
		echo '
						<li', $num == $incontext['current_step'] ? ' class="stepcurrent"' : '', '>
							', Lang::$txt['upgrade_step'], ' ', $step[0], ': ', $step[1], '
						</li>';
	}

	echo '
					</ul>
				</div>
				<div id="install_progress">
					<div id="progress_bar" class="progress_bar progress_green">
						<h3>' . Lang::$txt['upgrade_overall_progress'], '</h3>
						<span id="overall_text">', $incontext['overall_percent'], '%</span>
						<div id="overall_progress" class="bar" style="width: ', $incontext['overall_percent'], '%;"></div>
					</div>
				</div>
				<div id="main_screen" class="clear">
					<h2>', $incontext['page_title'], '</h2>
					<div class="panel">';
}

function template_install_below()
{
	global $incontext;

	if (!empty($incontext['continue']) || !empty($incontext['skip'])) {
		echo '
							<div class="floatright">';

		if (!empty($incontext['continue'])) {
			echo '
								<input type="submit" id="contbutt" name="contbutt" value="', Lang::$txt['upgrade_continue'], '" onclick="return submitThisOnce(this);" class="button">';
		}

		if (!empty($incontext['skip'])) {
			echo '
								<input type="submit" id="skip" name="skip" value="', Lang::$txt['upgrade_skip'], '" onclick="return submitThisOnce(this);" class="button">';
		}
		echo '
							</div>';
	}

	// Show the closing form tag and other data only if not in the last step
	if (count($incontext['steps']) - 1 !== (int) $incontext['current_step']) {
		echo '
						</form>';
	}

	echo '
					</div><!-- .panel -->
				</div><!-- #main_screen -->
			</div><!-- #main_content_section -->
		</div><!-- #content_section -->
	</div><!-- #wrapper -->
	</div><!-- #footerfix -->
	<div id="footer">
		<ul>
			<li class="copyright"><a href="https://www.simplemachines.org/" title="Simple Machines Forum" target="_blank" rel="noopener">' . SMF_FULL_VERSION . ' &copy; ' . SMF_SOFTWARE_YEAR . ', Simple Machines</a></li>
		</ul>
	</div>
</body>
</html>';
}

// Welcome them to the wonderful world of SMF!
function template_welcome_message()
{
	global $incontext;

	echo '
	<script src="https://www.simplemachines.org/smf/current-version.js?version=' . urlencode(SMF_VERSION) . '"></script>
	<form action="', $incontext['form_url'], '" method="post">
		<p>', sprintf(Lang::$txt['install_welcome_desc'], SMF_VERSION), '</p>
		<div id="version_warning" class="noticebox hidden">
			<h3>', Lang::$txt['error_warning_notice'], '</h3>
			', sprintf(Lang::$txt['error_script_outdated'], '<em id="smfVersion" style="white-space: nowrap;">??</em>', '<em id="yourVersion" style="white-space: nowrap;">' . SMF_VERSION . '</em>'), '
		</div>';

	// Show the warnings, or not.
	if (template_warning_divs()) {
		echo '
		<h3>', Lang::$txt['install_all_lovely'], '</h3>';
	}

	// Say we want the continue button!
	if (empty($incontext['error'])) {
		$incontext['continue'] = 1;
	}

	// For the latest version stuff.
	echo '
		<script>
			// Latest version?
			function smfCurrentVersion()
			{
				var smfVer, yourVer;

				if (!(\'smfVersion\' in window))
					return;

				window.smfVersion = window.smfVersion.replace(/SMF\s?/g, \'\');

				smfVer = document.getElementById("smfVersion");
				yourVer = document.getElementById("yourVersion");

				setInnerHTML(smfVer, window.smfVersion);

				var currentVersion = getInnerHTML(yourVer);
				if (currentVersion < window.smfVersion)
					document.getElementById(\'version_warning\').classList.remove(\'hidden\');
			}
			addLoadEvent(smfCurrentVersion);
		</script>';
}

// A shortcut for any warning stuff.
function template_warning_divs()
{
	global $incontext;

	// Errors are very serious..
	if (!empty($incontext['error'])) {
		echo '
		<div class="errorbox">
			<h3>', Lang::$txt['upgrade_critical_error'], '</h3>
			', $incontext['error'], '
		</div>';
	}
	// A warning message?
	elseif (!empty($incontext['warning'])) {
		echo '
		<div class="errorbox">
			<h3>', Lang::$txt['upgrade_warning'], '</h3>
			', $incontext['warning'], '
		</div>';
	}

	return empty($incontext['error']) && empty($incontext['warning']);
}

function template_chmod_files()
{
	global $incontext;

	echo '
		<p>', Lang::$txt['ftp_setup_why_info'], '</p>
		<ul class="error_content">
			<li>', implode('</li>
			<li>', $incontext['failed_files']), '</li>
		</ul>';

	if (isset($incontext['systemos'], $incontext['detected_path']) && $incontext['systemos'] == 'linux') {
		echo '
		<hr>
		<p>', Lang::$txt['chmod_linux_info'], '</p>
		<samp># chmod a+w ', implode(' ' . $incontext['detected_path'] . '/', $incontext['failed_files']), '</samp>';
	}

	// This is serious!
	if (!template_warning_divs()) {
		return;
	}

	echo '
		<hr>
		<p>', Lang::$txt['ftp_setup_info'], '</p>';

	if (!empty($incontext['ftp_errors'])) {
		echo '
		<div class="error_message">
			', Lang::$txt['error_ftp_no_connect'], '<br><br>
			<code>', implode('<br>', $incontext['ftp_errors']), '</code>
		</div>';
	}

	echo '
		<form action="', $incontext['form_url'], '" method="post">
			<dl class="settings">
				<dt>
					<label for="ftp_server">', Lang::$txt['ftp_server'], ':</label>
				</dt>
				<dd>
					<div class="floatright">
						<label for="ftp_port" class="textbox"><strong>', Lang::$txt['ftp_port'], ':&nbsp;</strong></label>
						<input type="text" size="3" name="ftp_port" id="ftp_port" value="', $incontext['ftp']['port'], '">
					</div>
					<input type="text" size="30" name="ftp_server" id="ftp_server" value="', $incontext['ftp']['server'], '">
					<div class="smalltext block">', Lang::$txt['ftp_server_info'], '</div>
				</dd>
				<dt>
					<label for="ftp_username">', Lang::$txt['ftp_username'], ':</label>
				</dt>
				<dd>
					<input type="text" size="30" name="ftp_username" id="ftp_username" value="', $incontext['ftp']['username'], '">
					<div class="smalltext block">', Lang::$txt['ftp_username_info'], '</div>
				</dd>
				<dt>
					<label for="ftp_password">', Lang::$txt['ftp_password'], ':</label>
				</dt>
				<dd>
					<input type="password" size="30" name="ftp_password" id="ftp_password">
					<div class="smalltext block">', Lang::$txt['ftp_password_info'], '</div>
				</dd>
				<dt>
					<label for="ftp_path">', Lang::$txt['ftp_path'], ':</label>
				</dt>
				<dd>
					<input type="text" size="30" name="ftp_path" id="ftp_path" value="', $incontext['ftp']['path'], '">
					<div class="smalltext block">', $incontext['ftp']['path_msg'], '</div>
				</dd>
			</dl>
			<div class="righttext buttons">
				<input type="submit" value="', Lang::$txt['ftp_connect'], '" onclick="return submitThisOnce(this);" class="button">
			</div>
		</form>
		<a href="', $incontext['form_url'], '">', Lang::$txt['error_message_click'], '</a> ', Lang::$txt['ftp_setup_again'];
}

// Get the database settings prepared.
function template_database_settings()
{
	global $incontext;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', Lang::$txt['db_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<dl class="settings">';

	// More than one database type?
	if (count($incontext['supported_databases']) > 1) {
		echo '
			<dt>
				<label for="db_type_input">', Lang::$txt['db_settings_type'], ':</label>
			</dt>
			<dd>
				<select name="db_type" id="db_type_input" onchange="toggleDBInput();">';

		foreach ($incontext['supported_databases'] as $key => $db) {
			echo '
					<option value="', $key, '"', isset($_POST['db_type']) && $_POST['db_type'] == $key ? ' selected' : '', '>', $db['name'], '</option>';
		}

		echo '
				</select>
				<div class="smalltext">', Lang::$txt['db_settings_type_info'], '</div>
			</dd>';
	} else {
		echo '
			<dd>
				<input type="hidden" name="db_type" value="', $incontext['db']['type'], '">
			</dd>';
	}

	echo '
			<dt>
				<label for="db_server_input">', Lang::$txt['db_settings_server'], ':</label>
			</dt>
			<dd>
				<input type="text" name="db_server" id="db_server_input" value="', $incontext['db']['server'], '" size="30">
				<div class="smalltext">', Lang::$txt['db_settings_server_info'], '</div>
			</dd>
			<dt>
				<label for="db_port_input">', Lang::$txt['db_settings_port'], ':</label>
			</dt>
			<dd>
				<input type="text" name="db_port" id="db_port_input" value="', $incontext['db']['port'], '">
				<div class="smalltext">', Lang::$txt['db_settings_port_info'], '</div>
			</dd>
			<dt>
				<label for="db_user_input">', Lang::$txt['db_settings_username'], ':</label>
			</dt>
			<dd>
				<input type="text" name="db_user" id="db_user_input" value="', $incontext['db']['user'], '" size="30">
				<div class="smalltext">', Lang::$txt['db_settings_username_info'], '</div>
			</dd>
			<dt>
				<label for="db_passwd_input">', Lang::$txt['db_settings_password'], ':</label>
			</dt>
			<dd>
				<input type="password" name="db_passwd" id="db_passwd_input" value="', $incontext['db']['pass'], '" size="30">
				<div class="smalltext">', Lang::$txt['db_settings_password_info'], '</div>
			</dd>
			<dt>
				<label for="db_name_input">', Lang::$txt['db_settings_database'], ':</label>
			</dt>
			<dd>
				<input type="text" name="db_name" id="db_name_input" value="', empty($incontext['db']['name']) ? 'smf' : $incontext['db']['name'], '" size="30">
				<div class="smalltext">
					', Lang::$txt['db_settings_database_info'], '
					<span id="db_name_info_warning">', Lang::$txt['db_settings_database_info_note'], '</span>
				</div>
			</dd>
			<dt>
				<label for="db_prefix_input">', Lang::$txt['db_settings_prefix'], ':</label>
			</dt>
			<dd>
				<input type="text" name="db_prefix" id="db_prefix_input" value="', $incontext['db']['prefix'], '" size="30">
				<div class="smalltext">', Lang::$txt['db_settings_prefix_info'], '</div>
			</dd>
		</dl>';

	// Toggles a warning related to db names in PostgreSQL
	echo '
		<script>
			function toggleDBInput()
			{
				if (document.getElementById(\'db_type_input\').value == \'postgresql\')
					document.getElementById(\'db_name_info_warning\').classList.add(\'hidden\');
				else
					document.getElementById(\'db_name_info_warning\').classList.remove(\'hidden\');
			}
			toggleDBInput();
		</script>';
}

// Stick in their forum settings.
function template_forum_settings()
{
	global $incontext;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<h3>', Lang::$txt['install_settings_info'], '</h3>';

	template_warning_divs();

	echo '
		<dl class="settings">
			<dt>
				<label for="mbname_input">', Lang::$txt['install_settings_name'], ':</label>
			</dt>
			<dd>
				<input type="text" name="mbname" id="mbname_input" value="', Lang::$txt['install_settings_name_default'], '" size="65">
				<div class="smalltext">', Lang::$txt['install_settings_name_info'], '</div>
			</dd>
			<dt>
				<label for="boardurl_input">', Lang::$txt['install_settings_url'], ':</label>
			</dt>
			<dd>
				<input type="text" name="boardurl" id="boardurl_input" value="', $incontext['detected_url'], '" size="65">
				<div class="smalltext">', Lang::$txt['install_settings_url_info'], '</div>
			</dd>
			<dt>
				<label for="reg_mode">', Lang::$txt['install_settings_reg_mode'], ':</label>
			</dt>
			<dd>
				<select name="reg_mode" id="reg_mode">
					<optgroup label="', Lang::$txt['install_settings_reg_modes'], ':">
						<option value="0" selected>', Lang::$txt['install_settings_reg_immediate'], '</option>
						<option value="1">', Lang::$txt['install_settings_reg_email'], '</option>
						<option value="2">', Lang::$txt['install_settings_reg_admin'], '</option>
						<option value="3">', Lang::$txt['install_settings_reg_disabled'], '</option>
					</optgroup>
				</select>
				<div class="smalltext">', Lang::$txt['install_settings_reg_mode_info'], '</div>
			</dd>
			<dt>', Lang::$txt['install_settings_compress'], ':</dt>
			<dd>
				<input type="checkbox" name="compress" id="compress_check" checked>
				<label for="compress_check">', Lang::$txt['install_settings_compress_title'], '</label>
				<div class="smalltext">', Lang::$txt['install_settings_compress_info'], '</div>
			</dd>
			<dt>', Lang::$txt['install_settings_dbsession'], ':</dt>
			<dd>
				<input type="checkbox" name="dbsession" id="dbsession_check" checked>
				<label for="dbsession_check">', Lang::$txt['install_settings_dbsession_title'], '</label>
				<div class="smalltext">', $incontext['test_dbsession'] ? Lang::$txt['install_settings_dbsession_info1'] : Lang::$txt['install_settings_dbsession_info2'], '</div>
			</dd>
			<dt>', Lang::$txt['install_settings_stats'], ':</dt>
			<dd>
				<input type="checkbox" name="stats" id="stats_check" checked="checked">
				<label for="stats_check">', Lang::$txt['install_settings_stats_title'], '</label>
				<div class="smalltext">', Lang::$txt['install_settings_stats_info'], '</div>
			</dd>
			<dt>', Lang::$txt['force_ssl'], ':</dt>
			<dd>
				<input type="checkbox" name="force_ssl" id="force_ssl"', $incontext['ssl_chkbx_checked'] ? ' checked' : '',
					$incontext['ssl_chkbx_protected'] ? ' disabled' : '', '>
				<label for="force_ssl">', Lang::$txt['force_ssl_label'], '</label>
				<div class="smalltext"><strong>', Lang::$txt['force_ssl_info'], '</strong></div>
			</dd>
		</dl>';
}

// Show results of the database population.
function template_populate_database()
{
	global $incontext;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', !empty($incontext['was_refresh']) ? Lang::$txt['user_refresh_install_desc'] : Lang::$txt['db_populate_info'], '</p>';

	if (!empty($incontext['sql_results'])) {
		echo '
		<ul>
			<li>', implode('</li><li>', $incontext['sql_results']), '</li>
		</ul>';
	}

	if (!empty($incontext['failures'])) {
		echo '
		<div class="red">', Lang::$txt['error_db_queries'], '</div>
		<ul>';

		foreach ($incontext['failures'] as $line => $fail) {
			echo '
			<li><strong>', Lang::$txt['error_db_queries_line'], $line + 1, ':</strong> ', nl2br(htmlspecialchars($fail)), '</li>';
		}

		echo '
		</ul>';
	}

	echo '
		<p>', Lang::$txt['db_populate_info2'], '</p>';

	template_warning_divs();

	echo '
		<input type="hidden" name="pop_done" value="1">';
}

// Create the admin account.
function template_admin_account()
{
	global $incontext;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', Lang::$txt['user_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<dl class="settings">
			<dt>
				<label for="username">', Lang::$txt['user_settings_username'], ':</label>
			</dt>
			<dd>
				<input type="text" name="username" id="username" value="', $incontext['username'], '" size="40">
				<div class="smalltext">', Lang::$txt['user_settings_username_info'], '</div>
			</dd>
			<dt>
				<label for="password1">', Lang::$txt['user_settings_password'], ':</label>
			</dt>
			<dd>
				<input type="password" name="password1" id="password1" size="40">
				<div class="smalltext">', Lang::$txt['user_settings_password_info'], '</div>
			</dd>
			<dt>
				<label for="password2">', Lang::$txt['user_settings_again'], ':</label>
			</dt>
			<dd>
				<input type="password" name="password2" id="password2" size="40">
				<div class="smalltext">', Lang::$txt['user_settings_again_info'], '</div>
			</dd>
			<dt>
				<label for="email">', Lang::$txt['user_settings_admin_email'], ':</label>
			</dt>
			<dd>
				<input type="email" name="email" id="email" value="', $incontext['email'], '" size="40">
				<div class="smalltext">', Lang::$txt['user_settings_admin_email_info'], '</div>
			</dd>
			<dt>
				<label for="server_email">', Lang::$txt['user_settings_server_email'], ':</label>
			</dt>
			<dd>
				<input type="text" name="server_email" id="server_email" value="', $incontext['server_email'], '" size="40">
				<div class="smalltext">', Lang::$txt['user_settings_server_email_info'], '</div>
			</dd>
		</dl>';

	if ($incontext['require_db_confirm']) {
		echo '
		<h2>', Lang::$txt['user_settings_database'], '</h2>
		<p>', Lang::$txt['user_settings_database_info'], '</p>

		<div class="lefttext">
			<input type="password" name="password3" size="30">
		</div>';
	}
}

// Tell them it's done, and to delete.
function template_delete_install()
{
	global $incontext, $installurl;

	echo '
		<p>', Lang::$txt['congratulations_help'], '</p>';

	template_warning_divs();

	// Install directory still writable?
	if ($incontext['dir_still_writable']) {
		echo '
		<p><em>', Lang::$txt['still_writable'], '</em></p>';
	}

	// Don't show the box if it's like 99% sure it won't work :P.
	if ($incontext['probably_delete_install']) {
		echo '
		<label>
			<input type="checkbox" id="delete_self" onclick="doTheDelete();">
			<strong>', Lang::$txt['delete_installer'], !isset($_SESSION['installer_temp_ftp']) ? ' ' . Lang::$txt['delete_installer_maybe'] : '', '</strong>
		</label>
		<script>
			function doTheDelete()
			{
				var theCheck = document.getElementById ? document.getElementById("delete_self") : document.all.delete_self;
				var tempImage = new Image();

				tempImage.src = "', $installurl, '?delete=1&ts_" + (new Date().getTime());
				tempImage.width = 0;
				theCheck.disabled = true;
			}
		</script>';
	}

	echo '
		<p>', sprintf(Lang::$txt['go_to_your_forum'], Config::$boardurl . '/index.php'), '</p>
		<br>
		', Lang::$txt['good_luck'];
}

?>