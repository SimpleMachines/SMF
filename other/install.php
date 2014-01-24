<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

$GLOBALS['current_smf_version'] = '2.1 Alpha 1';
$GLOBALS['db_script_version'] = '2-1';

$GLOBALS['required_php_version'] = '5.1.0';

// Don't have PHP support, do you?
// ><html dir="ltr"><head><title>Error!</title></head><body>Sorry, this installer requires PHP!<div style="display: none;">

// Database info.
$databases = array(
	'mysqli' => array(
		'name' => 'MySQLi',
		'version' => '5.0.3',
		'version_check' => 'return min(mysqli_get_server_info($db_connection), mysqli_get_client_info());',
		'supported' => function_exists('mysqli_connect'),
		'default_user' => 'mysql.default_user',
		'default_password' => 'mysql.default_password',
		'default_host' => 'mysql.default_host',
		'default_port' => 'mysql.default_port',
		'utf8_support' => true,
		'utf8_version' => '5.0.3',
		'utf8_version_check' => 'return mysqli_get_server_info($db_connection);',
		'utf8_default' => true,
		'utf8_required' => true,
		'alter_support' => true,
		'validate_prefix' => create_function('&$value', '
			$value = preg_replace(\'~[^A-Za-z0-9_\$]~\', \'\', $value);
			return true;
		'),
	),
	'mysql' => array(
		'name' => 'MySQL',
		'version' => '5.0.3',
		'version_check' => 'return min(mysql_get_server_info(), mysql_get_client_info());',
		'supported' => function_exists('mysql_connect'),
		'default_user' => 'mysql.default_user',
		'default_password' => 'mysql.default_password',
		'default_host' => 'mysql.default_host',
		'default_port' => 'mysql.default_port',
		'utf8_support' => true,
		'utf8_version' => '5.0.3',
		'utf8_version_check' => 'return mysql_get_server_info();',
		'utf8_default' => true,
		'utf8_required' => true,
		'alter_support' => true,
		'validate_prefix' => create_function('&$value', '
			$value = preg_replace(\'~[^A-Za-z0-9_\$]~\', \'\', $value);
			return true;
		'),
	),
	'postgresql' => array(
		'name' => 'PostgreSQL',
		'version' => '8.0',
		'function_check' => 'pg_connect',
		'version_check' => '$request = pg_query(\'SELECT version()\'); list ($version) = pg_fetch_row($request); list($pgl, $version) = explode(" ", $version); return $version;',
		'supported' => function_exists('pg_connect'),
		'always_has_db' => true,
		'utf8_default' => true,
		'utf8_required' => true,
		'utf8_support' => true,
		'utf8_version' => '8.0',
		'utf8_version_check' => '$request = pg_query(\'SELECT version()\'); list ($version) = pg_fetch_row($request); list($pgl, $version) = explode(" ", $version); return $version;',
		'validate_prefix' => create_function('&$value', '
			$value = preg_replace(\'~[^A-Za-z0-9_\$]~\', \'\', $value);

			// Is it reserved?
			if ($value == \'pg_\')
				return $txt[\'error_db_prefix_reserved\'];

			// Is the prefix numeric?
			if (preg_match(\'~^\d~\', $value))
				return $txt[\'error_db_prefix_numeric\'];

			return true;
		'),
	),
	'sqlite' => array(
		'name' => 'SQLite',
		'version' => '1',
		'function_check' => 'sqlite_open',
		'version_check' => 'return 1;',
		'supported' => function_exists('sqlite_open'),
		'always_has_db' => true,
		'utf8_default' => false,
		'utf8_required' => false,
		'utf8_support' => false,
		'validate_prefix' => create_function('&$value', '
			global $incontext, $txt;

			$value = preg_replace(\'~[^A-Za-z0-9_\$]~\', \'\', $value);

			// Is it reserved?
			if ($value == \'sqlite_\')
				return $txt[\'error_db_prefix_reserved\'];

			// Is the prefix numeric?
			if (preg_match(\'~^\d~\', $value))
				return $txt[\'error_db_prefix_numeric\'];

			return true;
		'),
	),
	'sqlite3' => array(
		'name' => 'SQLite3',
		'version' => '1',
		'function_check' => 'SQLite3::version',
		'version_check' => 'return 1;',
		'supported' => is_callable(array('sqlite3', 'version')),
		'always_has_db' => true,
		'utf8_default' => true,
		'utf8_required' => true,
		'utf8_support' => true,
		'validate_prefix' => create_function('&$value', '
			global $incontext, $txt;

			$value = preg_replace(\'~[^A-Za-z0-9_\$]~\', \'\', $value);

			// Is it reserved?
			if ($value == \'sqlite_\')
				return $txt[\'error_db_prefix_reserved\'];

			// Is the prefix numeric?
			if (preg_match(\'~^\d~\', $value))
				return $txt[\'error_db_prefix_numeric\'];

			return true;
		'),
	),
);

// Initialize everything and load the language files.
initialize_inputs();
load_lang_file();

// This is what we are.
$installurl = $_SERVER['PHP_SELF'];
// This is where SMF is.
$smfsite = 'http://www.simplemachines.org/smf';

// All the steps in detail.
// Number,Name,Function,Progress Weight.
$incontext['steps'] = array(
	0 => array(1, $txt['install_step_welcome'], 'Welcome', 0),
	1 => array(2, $txt['install_step_writable'], 'CheckFilesWritable', 10),
	2 => array(3, $txt['install_step_databaseset'], 'DatabaseSettings', 15),
	3 => array(4, $txt['install_step_forum'], 'ForumSettings', 40),
	4 => array(5, $txt['install_step_databasechange'], 'DatabasePopulation', 15),
	5 => array(6, $txt['install_step_admin'], 'AdminAccount', 20),
	6 => array(7, $txt['install_step_delete'], 'DeleteInstall', 0),
);

// Default title...
$incontext['page_title'] = $txt['smf_installer'];

// What step are we on?
$incontext['current_step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;

// Loop through all the steps doing each one as required.
$incontext['overall_percent'] = 0;
foreach ($incontext['steps'] as $num => $step)
{
	if ($num >= $incontext['current_step'])
	{
		// The current weight of this step in terms of overall progress.
		$incontext['step_weight'] = $step[3];
		// Make sure we reset the skip button.
		$incontext['skip'] = false;

		// Call the step and if it returns false that means pause!
		if (function_exists($step[2]) && $step[2]() === false)
			break;
		elseif (function_exists($step[2]))
			$incontext['current_step']++;

		// No warnings pass on.
		$incontext['warning'] = '';
	}
	$incontext['overall_percent'] += $step[3];
}

// Actually do the template stuff.
installExit();

function initialize_inputs()
{
	global $databases, $incontext;

	// Just so people using older versions of PHP aren't left in the cold.
	if (!isset($_SERVER['PHP_SELF']))
		$_SERVER['PHP_SELF'] = isset($GLOBALS['HTTP_SERVER_VARS']['PHP_SELF']) ? $GLOBALS['HTTP_SERVER_VARS']['PHP_SELF'] : 'install.php';

	// Turn off magic quotes runtime and enable error reporting.
	if (function_exists('set_magic_quotes_runtime'))
		@set_magic_quotes_runtime(0);
	error_reporting(E_ALL);

	// Fun.  Low PHP version...
	if (!isset($_GET))
	{
		$GLOBALS['_GET']['step'] = 0;
		return;
	}

	if (!isset($_GET['obgz']))
	{
		ob_start();

		if (ini_get('session.save_handler') == 'user')
			@ini_set('session.save_handler', 'files');
		if (function_exists('session_start'))
			@session_start();
	}
	else
	{
		ob_start('ob_gzhandler');

		if (ini_get('session.save_handler') == 'user')
			@ini_set('session.save_handler', 'files');
		session_start();

		if (!headers_sent())
			echo '<!DOCTYPE html>
<html>
	<head>
		<title>', htmlspecialchars($_GET['pass_string']), '</title>
	</head>
	<body style="background-color: #d4d4d4; margin-top: 16%; text-align: center; font-size: 16pt;">
		<strong>', htmlspecialchars($_GET['pass_string']), '</strong>
	</body>
</html>';
		exit;
	}

	// Are we calling the backup css file?
	if (isset($_GET['infile_css']))
	{
		header('Content-Type: text/css');
		template_css();
		exit;
	}

	// Anybody home?
	if (!isset($_GET['xml']))
	{
		$incontext['remote_files_available'] = false;
		$test = @fsockopen('www.simplemachines.org', 80, $errno, $errstr, 1);
		if ($test)
			$incontext['remote_files_available'] = true;
		@fclose($test);
	}

	// Add slashes, as long as they aren't already being added.
	if (!function_exists('get_magic_quotes_gpc') || @get_magic_quotes_gpc() == 0)
		foreach ($_POST as $k => $v)
			if (strpos($k, 'password') === false)
				$_POST[$k] = addslashes($v);

	// This is really quite simple; if ?delete is on the URL, delete the installer...
	if (isset($_GET['delete']))
	{
		if (isset($_SESSION['installer_temp_ftp']))
		{
			$ftp = new ftp_connection($_SESSION['installer_temp_ftp']['server'], $_SESSION['installer_temp_ftp']['port'], $_SESSION['installer_temp_ftp']['username'], $_SESSION['installer_temp_ftp']['password']);
			$ftp->chdir($_SESSION['installer_temp_ftp']['path']);

			$ftp->unlink('install.php');
			$ftp->unlink('webinstall.php');

			foreach ($databases as $key => $dummy)
			{
				$type = ($key == 'mysqli') ? 'mysql' : $key;
				$ftp->unlink('install_' . $GLOBALS['db_script_version'] . '_' . $type . '.sql');
			}

			$ftp->close();

			unset($_SESSION['installer_temp_ftp']);
		}
		else
		{
			@unlink(__FILE__);
			@unlink(dirname(__FILE__) . '/webinstall.php');

			foreach ($databases as $key => $dummy)
			{
				$type = ($key == 'mysqli') ? 'mysql' : $key;
				@unlink(dirname(__FILE__) . '/install_' . $GLOBALS['db_script_version'] . '_' . $type . '.sql');
			}
		}

		// Now just redirect to a blank.png...
		header('Location: http://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/Themes/default/images/blank.png');
		exit;
	}

	// PHP 5 might cry if we don't do this now.
	if (function_exists('date_default_timezone_set'))
	{
		$server_offset = @mktime(0, 0, 0, 1, 1, 1970);
		date_default_timezone_set('Etc/GMT' . ($server_offset > 0 ? '+' : '') . ($server_offset / 3600));
	}

	// Force an integer step, defaulting to 0.
	$_GET['step'] = (int) @$_GET['step'];
}

// Load the list of language files, and the current language file.
function load_lang_file()
{
	global $txt, $incontext;

	$incontext['detected_languages'] = array();

	// Make sure the languages directory actually exists.
	if (file_exists(dirname(__FILE__) . '/Themes/default/languages'))
	{
		// Find all the "Install" language files in the directory.
		$dir = dir(dirname(__FILE__) . '/Themes/default/languages');
		while ($entry = $dir->read())
		{
			if (substr($entry, 0, 8) == 'Install.' && substr($entry, -4) == '.php')
				$incontext['detected_languages'][$entry] = ucfirst(substr($entry, 8, strlen($entry) - 12));
		}
		$dir->close();
	}

	// Didn't find any, show an error message!
	if (empty($incontext['detected_languages']))
	{
		// Let's not cache this message, eh?
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		echo '<!DOCTYPE html>
<html>
	<head>
		<title>SMF Installer: Error!</title>
	</head>
	<body style="font-family: sans-serif;"><div style="width: 600px;">
		<h1 style="font-size: 14pt;">A critical error has occurred.</h1>

		<p>This installer was unable to find the installer\'s language file or files.  They should be found under:</p>

		<div style="margin: 1ex; font-family: monospace; font-weight: bold;">', dirname($_SERVER['PHP_SELF']) != '/' ? dirname($_SERVER['PHP_SELF']) : '', '/Themes/default/languages</div>

		<p>In some cases, FTP clients do not properly upload files with this many folders.  Please double check to make sure you <span style="font-weight: 600;">have uploaded all the files in the distribution</span>.</p>
		<p>If that doesn\'t help, please make sure this install.php file is in the same place as the Themes folder.</p>

		<p>If you continue to get this error message, feel free to <a href="http://support.simplemachines.org/">look to us for support</a>.</p>
	</div></body>
</html>';
		die;
	}

	// Override the language file?
	if (isset($_GET['lang_file']))
		$_SESSION['installer_temp_lang'] = $_GET['lang_file'];
	elseif (isset($GLOBALS['HTTP_GET_VARS']['lang_file']))
		$_SESSION['installer_temp_lang'] = $GLOBALS['HTTP_GET_VARS']['lang_file'];

	// Make sure it exists, if it doesn't reset it.
	if (!isset($_SESSION['installer_temp_lang']) || preg_match('~[^\\w_\\-.]~', $_SESSION['installer_temp_lang']) === 1 || !file_exists(dirname(__FILE__) . '/Themes/default/languages/' . $_SESSION['installer_temp_lang']))
	{
		// Use the first one...
		list ($_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);

		// If we have english and some other language, use the other language.  We Americans hate english :P.
		if ($_SESSION['installer_temp_lang'] == 'Install.english.php' && count($incontext['detected_languages']) > 1)
			list (, $_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);
	}

	// And now include the actual language file itself.
	require_once(dirname(__FILE__) . '/Themes/default/languages/' . $_SESSION['installer_temp_lang']);
}

// This handy function loads some settings and the like.
function load_database()
{
	global $db_prefix, $db_connection, $db_character_set, $sourcedir, $language;
	global $smcFunc, $mbname, $scripturl, $boardurl, $modSettings, $db_type, $db_name, $db_user, $db_persist;

	if (empty($sourcedir))
		$sourcedir = dirname(__FILE__) . '/Sources';

	// Need this to check whether we need the database password.
	require(dirname(__FILE__) . '/Settings.php');
	if (!defined('SMF'))
		define('SMF', 1);
	if (empty($smcFunc))
		$smcFunc = array();

	$modSettings['disableQueryCheck'] = true;

	// Connect the database.
	if (!$db_connection)
	{
		require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');
		if (version_compare(PHP_VERSION, '5', '<'))
			require_once($sourcedir . '/Subs-Compat.php');

		if (!$db_connection)
			$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist));
	}
}

// This is called upon exiting the installer, for template etc.
function installExit($fallThrough = false)
{
	global $incontext, $installurl, $txt;

	// Send character set.
	header('Content-Type: text/html; charset=' . (isset($txt['lang_character_set']) ? $txt['lang_character_set'] : 'ISO-8859-1'));

	// We usually dump our templates out.
	if (!$fallThrough)
	{
		// The top install bit.
		template_install_above();

		// Call the template.
		if (isset($incontext['sub_template']))
		{
			$incontext['form_url'] = $installurl . '?step=' . $incontext['current_step'];

			call_user_func('template_' . $incontext['sub_template']);
		}
		// @todo REMOVE THIS!!
		else
		{
			if (function_exists('doStep' . $_GET['step']))
				call_user_func('doStep' . $_GET['step']);
		}
		// Show the footer.
		template_install_below();
	}

	// Bang - gone!
	die();
}

function Welcome()
{
	global $incontext, $txt, $databases, $installurl;

	$incontext['page_title'] = $txt['install_welcome'];
	$incontext['sub_template'] = 'welcome_message';

	// Done the submission?
	if (isset($_POST['contbutt']))
		return true;

	// Check the PHP version.
	if ((!function_exists('version_compare') || version_compare($GLOBALS['required_php_version'], PHP_VERSION, '>')))
	{
		$incontext['warning'] = $txt['error_php_too_low'];
	}

	// See if we think they have already installed it?
	if (is_readable(dirname(__FILE__) . '/Settings.php'))
	{
		$probably_installed = 0;
		foreach (file(dirname(__FILE__) . '/Settings.php') as $line)
		{
			if (preg_match('~^\$db_passwd\s=\s\'([^\']+)\';$~', $line))
				$probably_installed++;
			if (preg_match('~^\$boardurl\s=\s\'([^\']+)\';~', $line) && !preg_match('~^\$boardurl\s=\s\'http://127\.0\.0\.1/smf\';~', $line))
				$probably_installed++;
		}

		if ($probably_installed == 2)
			$incontext['warning'] = $txt['error_already_installed'];
	}

	// Is some database support even compiled in?
	$incontext['supported_databases'] = array();
	foreach ($databases as $key => $db)
	{
		if ($db['supported'])
		{
			$type = ($key == 'mysqli') ? 'mysql' : $key;
			if (!file_exists(dirname(__FILE__) . '/install_' . $GLOBALS['db_script_version'] . '_' . $type . '.sql'))
			{
				$databases[$key]['supported'] = false;
				$notFoundSQLFile = true;
				$txt['error_db_script_missing'] = sprintf($txt['error_db_script_missing'], 'install_' . $GLOBALS['db_script_version'] . '_' . $type . '.sql');
			}
			else
			{
				$db_type = $key;
				$incontext['supported_databases'][] = $db;
			}
		}
	}

	if (empty($incontext['supported_databases']))
		$error = empty($notFoundSQLFile) ? 'error_db_missing' : 'error_db_script_missing';
	// How about session support?  Some crazy sysadmin remove it?
	elseif (!function_exists('session_start'))
		$error = 'error_session_missing';
	// Make sure they uploaded all the files.
	elseif (!file_exists(dirname(__FILE__) . '/index.php'))
		$error = 'error_missing_files';
	// Very simple check on the session.save_path for Windows.
	// @todo Move this down later if they don't use database-driven sessions?
	elseif (@ini_get('session.save_path') == '/tmp' && substr(__FILE__, 1, 2) == ':\\')
		$error = 'error_session_save_path';

	// Since each of the three messages would look the same, anyway...
	if (isset($error))
		$incontext['error'] = $txt[$error];

	// Mod_security blocks everything that smells funny. Let SMF handle security.
	if (!fixModSecurity() && !isset($_GET['overmodsecurity']))
		$incontext['error'] = $txt['error_mod_security'] . '<br><br><a href="' . $installurl . '?overmodsecurity=true">' . $txt['error_message_click'] . '</a> ' . $txt['error_message_bad_try_again'];

	return false;
}

function CheckFilesWritable()
{
	global $txt, $incontext;

	$incontext['page_title'] = $txt['ftp_checking_writable'];
	$incontext['sub_template'] = 'chmod_files';

	$writable_files = array(
		'attachments',
		'avatars',
		'custom_avatar',
		'cache',
		'Packages',
		'Smileys',
		'Themes',
		'agreement.txt',
		'Settings.php',
		'Settings_bak.php'
	);
	foreach ($incontext['detected_languages'] as $lang => $temp)
		$extra_files[] = 'Themes/default/languages/' . $lang;

	// With mod_security installed, we could attempt to fix it with .htaccess.
	if (function_exists('apache_get_modules') && in_array('mod_security', apache_get_modules()))
		$writable_files[] = file_exists(dirname(__FILE__) . '/.htaccess') ? '.htaccess' : '.';

	$failed_files = array();

	// On linux, it's easy - just use is_writable!
	if (substr(__FILE__, 1, 2) != ':\\')
	{
		foreach ($writable_files as $file)
		{
			if (!is_writable(dirname(__FILE__) . '/' . $file))
			{
				@chmod(dirname(__FILE__) . '/' . $file, 0755);

				// Well, 755 hopefully worked... if not, try 777.
				if (!is_writable(dirname(__FILE__) . '/' . $file) && !@chmod(dirname(__FILE__) . '/' . $file, 0777))
					$failed_files[] = $file;
			}
		}
		foreach ($extra_files as $file)
			@chmod(dirname(__FILE__) . (empty($file) ? '' : '/' . $file), 0777);
	}
	// Windows is trickier.  Let's try opening for r+...
	else
	{
		foreach ($writable_files as $file)
		{
			// Folders can't be opened for write... but the index.php in them can ;)
			if (is_dir(dirname(__FILE__) . '/' . $file))
				$file .= '/index.php';

			// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
			@chmod(dirname(__FILE__) . '/' . $file, 0777);
			$fp = @fopen(dirname(__FILE__) . '/' . $file, 'r+');

			// Hmm, okay, try just for write in that case...
			if (!is_resource($fp))
				$fp = @fopen(dirname(__FILE__) . '/' . $file, 'w');

			if (!is_resource($fp))
				$failed_files[] = $file;

			@fclose($fp);
		}
		foreach ($extra_files as $file)
			@chmod(dirname(__FILE__) . (empty($file) ? '' : '/' . $file), 0777);
	}

	$failure = count($failed_files) >= 1;

	if (!isset($_SERVER))
		return !$failure;

	// Put the list into context.
	$incontext['failed_files'] = $failed_files;

	// It's not going to be possible to use FTP on windows to solve the problem...
	if ($failure && substr(__FILE__, 1, 2) == ':\\')
	{
		$incontext['error'] = $txt['error_windows_chmod'] . '
					<ul style="margin: 2.5ex; font-family: monospace;">
						<li>' . implode('</li>
						<li>', $failed_files) . '</li>
					</ul>';

		return false;
	}
	// We're going to have to use... FTP!
	elseif ($failure)
	{
		// Load any session data we might have...
		if (!isset($_POST['ftp_username']) && isset($_SESSION['installer_temp_ftp']))
		{
			$_POST['ftp_server'] = $_SESSION['installer_temp_ftp']['server'];
			$_POST['ftp_port'] = $_SESSION['installer_temp_ftp']['port'];
			$_POST['ftp_username'] = $_SESSION['installer_temp_ftp']['username'];
			$_POST['ftp_password'] = $_SESSION['installer_temp_ftp']['password'];
			$_POST['ftp_path'] = $_SESSION['installer_temp_ftp']['path'];
		}

		$incontext['ftp_errors'] = array();

		if (isset($_POST['ftp_username']))
		{
			$ftp = new ftp_connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

			if ($ftp->error === false)
			{
				// Try it without /home/abc just in case they messed up.
				if (!$ftp->chdir($_POST['ftp_path']))
				{
					$incontext['ftp_errors'][] = $ftp->last_message;
					$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
				}
			}
		}

		if (!isset($ftp) || $ftp->error !== false)
		{
			if (!isset($ftp))
				$ftp = new ftp_connection(null);
			// Save the error so we can mess with listing...
			elseif ($ftp->error !== false && empty($incontext['ftp_errors']) && !empty($ftp->last_message))
				$incontext['ftp_errors'][] = $ftp->last_message;

			list ($username, $detect_path, $found_path) = $ftp->detect_path(dirname(__FILE__));

			if (empty($_POST['ftp_path']) && $found_path)
				$_POST['ftp_path'] = $detect_path;

			if (!isset($_POST['ftp_username']))
				$_POST['ftp_username'] = $username;

			// Set the username etc, into context.
			$incontext['ftp'] = array(
				'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : 'localhost',
				'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : '21',
				'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : '',
				'path' => isset($_POST['ftp_path']) ? $_POST['ftp_path'] : '/',
				'path_msg' => !empty($found_path) ? $txt['ftp_path_found_info'] : $txt['ftp_path_info'],
			);

			return false;
		}
		else
		{
			$_SESSION['installer_temp_ftp'] = array(
				'server' => $_POST['ftp_server'],
				'port' => $_POST['ftp_port'],
				'username' => $_POST['ftp_username'],
				'password' => $_POST['ftp_password'],
				'path' => $_POST['ftp_path']
			);

			$failed_files_updated = array();

			foreach ($failed_files as $file)
			{
				if (!is_writable(dirname(__FILE__) . '/' . $file))
					$ftp->chmod($file, 0755);
				if (!is_writable(dirname(__FILE__) . '/' . $file))
					$ftp->chmod($file, 0777);
				if (!is_writable(dirname(__FILE__) . '/' . $file))
				{
					$failed_files_updated[] = $file;
					$incontext['ftp_errors'][] = rtrim($ftp->last_message) . ' -> ' . $file . "\n";
				}
			}

			$ftp->close();

			// Are there any errors left?
			if (count($failed_files_updated) >= 1)
			{
				// Guess there are...
				$incontext['failed_files'] = $failed_files_updated;

				// Set the username etc, into context.
				$incontext['ftp'] = $_SESSION['installer_temp_ftp'] += array(
					'path_msg' => $txt['ftp_path_info'],
				);

				return false;
			}
		}
	}

	return true;
}

function DatabaseSettings()
{
	global $txt, $databases, $incontext, $smcFunc, $sourcedir;

	$incontext['sub_template'] = 'database_settings';
	$incontext['page_title'] = $txt['db_settings'];
	$incontext['continue'] = 1;

	// Set up the defaults.
	$incontext['db']['server'] = 'localhost';
	$incontext['db']['user'] = '';
	$incontext['db']['name'] = '';
	$incontext['db']['pass'] = '';
	$incontext['db']['type'] = '';
	$incontext['supported_databases'] = array();

	$foundOne = false;
	foreach ($databases as $key => $db)
	{
		// Override with the defaults for this DB if appropriate.
		if ($db['supported'])
		{
			$incontext['supported_databases'][$key] = $db;

			if (!$foundOne)
			{
				if (isset($db['default_host']))
					$incontext['db']['server'] = ini_get($db['default_host']) or $incontext['db']['server'] = 'localhost';
				if (isset($db['default_user']))
				{
					$incontext['db']['user'] = ini_get($db['default_user']);
					$incontext['db']['name'] = ini_get($db['default_user']);
				}
				if (isset($db['default_password']))
					$incontext['db']['pass'] = ini_get($db['default_password']);
				if (isset($db['default_port']))
					$db_port = ini_get($db['default_port']);

				$incontext['db']['type'] = $key;
				$foundOne = true;
			}
		}
	}

	// Override for repost.
	if (isset($_POST['db_user']))
	{
		$incontext['db']['user'] = $_POST['db_user'];
		$incontext['db']['name'] = ($_POST['db_type'] == 'sqlite' || $_POST['db_type'] == 'sqlite3') && isset($_POST['db_filename']) ? $_POST['db_filename'] : $_POST['db_name'];
		$incontext['db']['server'] = $_POST['db_server'];
		$incontext['db']['prefix'] = $_POST['db_prefix'];
	}
	else
	{
		$incontext['db']['prefix'] = 'smf_';

		// Should we use a non standard port?
		if (!empty($db_port))
			$incontext['db']['server'] .= ':' . $db_port;
	}

	// Are we submitting?
	if (isset($_POST['db_type']))
	{
		if (isset($_POST['db_filename']))
		{
			// You better enter enter a database name for SQLite.
			if (trim($_POST['db_filename']) == '')
			{
				$incontext['error'] = $txt['error_db_filename'];
				return false;
			}
			// Duplicate name in the same dir?  Can't do that with SQLite.  Weird things happen.
			if (file_exists($_POST['db_filename'] . (substr($_POST['db_filename'], -3) != '.db' ? '.db' : '')))
			{
				$incontext['error'] = $txt['error_db_filename_exists'];
				return false;
			}
		}

		// What type are they trying?
		$db_type = preg_replace('~[^A-Za-z0-9]~', '', $_POST['db_type']);
		$db_prefix = $_POST['db_prefix'];
		// Validate the prefix.
		$valid_prefix = $databases[$db_type]['validate_prefix']($db_prefix);

		if ($valid_prefix !== true)
		{
			$incontext['error'] = $valid_prefix;
			return false;
		}

		// Take care of these variables...
		$vars = array(
			'db_type' => $db_type,
			'db_name' => ($_POST['db_type'] == 'sqlite' || $_POST['db_type'] == 'sqlite3') && isset($_POST['db_filename']) ? $_POST['db_filename'] : $_POST['db_name'],
			'db_user' => $_POST['db_user'],
			'db_passwd' => isset($_POST['db_passwd']) ? $_POST['db_passwd'] : '',
			'db_server' => $_POST['db_server'],
			'db_prefix' => $db_prefix,
			// The cookiename is special; we want it to be the same if it ever needs to be reinstalled with the same info.
			'cookiename' => 'SMFCookie' . abs(crc32($_POST['db_name'] . preg_replace('~[^A-Za-z0-9_$]~', '', $_POST['db_prefix'])) % 1000),
		);

		// God I hope it saved!
		if (!updateSettingsFile($vars) && substr(__FILE__, 1, 2) == ':\\')
		{
			$incontext['error'] = $txt['error_windows_chmod'];
			return false;
		}

		// Make sure it works.
		require(dirname(__FILE__) . '/Settings.php');

		if (empty($sourcedir))
			$sourcedir = dirname(__FILE__) . '/Sources';

		// Better find the database file!
		if (!file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php'))
		{
			$incontext['error'] = sprintf($txt['error_db_file'], 'Subs-Db-' . $db_type . '.php');
			return false;
		}

		// Now include it for database functions!
		define('SMF', 1);
		$modSettings['disableQueryCheck'] = true;
		if (empty($smcFunc))
			$smcFunc = array();

			require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');

		// What - running PHP4? The shame!
		if (version_compare(PHP_VERSION, '5', '<'))
			require_once($sourcedir . '/Subs-Compat.php');

		// Attempt a connection.
		$needsDB = !empty($databases[$db_type]['always_has_db']);
		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('non_fatal' => true, 'dont_select_db' => !$needsDB));

		// No dice?  Let's try adding the prefix they specified, just in case they misread the instructions ;)
		if ($db_connection == null)
		{
			$db_error = @$smcFunc['db_error']();

			$db_connection = smf_db_initiate($db_server, $db_name, $_POST['db_prefix'] . $db_user, $db_passwd, $db_prefix, array('non_fatal' => true, 'dont_select_db' => !$needsDB));
			if ($db_connection != null)
			{
				$db_user = $_POST['db_prefix'] . $db_user;
				updateSettingsFile(array('db_user' => $db_user));
			}
		}

		// Still no connection?  Big fat error message :P.
		if (!$db_connection)
		{
			$incontext['error'] = $txt['error_db_connect'] . '<div style="margin: 2.5ex; font-family: monospace;"><strong>' . $db_error . '</strong></div>';
			return false;
		}

		// Do they meet the install requirements?
		// @todo Old client, new server?
		if (version_compare($databases[$db_type]['version'], preg_replace('~^\D*|\-.+?$~', '', eval($databases[$db_type]['version_check']))) > 0)
		{
			$incontext['error'] = $txt['error_db_too_low'];
			return false;
		}

		// Let's try that database on for size... assuming we haven't already lost the opportunity.
		if ($db_name != '' && !$needsDB)
		{
			$smcFunc['db_query']('', "
				CREATE DATABASE IF NOT EXISTS `$db_name`",
				array(
					'security_override' => true,
					'db_error_skip' => true,
				),
				$db_connection
			);

			// Okay, let's try the prefix if it didn't work...
			if (!$smcFunc['db_select_db']($db_name, $db_connection) && $db_name != '')
			{
				$smcFunc['db_query']('', "
					CREATE DATABASE IF NOT EXISTS `$_POST[db_prefix]$db_name`",
					array(
						'security_override' => true,
						'db_error_skip' => true,
					),
					$db_connection
				);

				if ($smcFunc['db_select_db']($_POST['db_prefix'] . $db_name, $db_connection))
				{
					$db_name = $_POST['db_prefix'] . $db_name;
					updateSettingsFile(array('db_name' => $db_name));
				}
			}

			// Okay, now let's try to connect...
			if (!$smcFunc['db_select_db']($db_name, $db_connection))
			{
				$incontext['error'] = sprintf($txt['error_db_database'], $db_name);
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
	global $txt, $incontext, $databases, $smcFunc, $db_connection, $db_type, $boardurl;

	$incontext['sub_template'] = 'forum_settings';
	$incontext['page_title'] = $txt['install_settings'];

	// Let's see if we got the database type correct.
	if (isset($_POST['db_type'], $databases[$_POST['db_type']]))
		$db_type = $_POST['db_type'];

	// Else we'd better be able to get the connection.
	else
		load_database();

	$db_type = isset($_POST['db_type']) ? $_POST['db_type'] : $db_type;

	// What host and port are we on?
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];

	// Now, to put what we've learned together... and add a path.
	$incontext['detected_url'] = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 's' : '') . '://' . $host . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));

	// Check if the database sessions will even work.
	$incontext['test_dbsession'] = (ini_get('session.auto_start') != 1);
	$incontext['utf8_default'] = $databases[$db_type]['utf8_default'];
	$incontext['utf8_required'] = $databases[$db_type]['utf8_required'];

	$incontext['continue'] = 1;

	// Submitting?
	if (isset($_POST['boardurl']))
	{
		if (substr($_POST['boardurl'], -10) == '/index.php')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
		elseif (substr($_POST['boardurl'], -1) == '/')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
		if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
			$_POST['boardurl'] = 'http://' . $_POST['boardurl'];

		// Save these variables.
		$vars = array(
			'boardurl' => $_POST['boardurl'],
			'boarddir' => addslashes(dirname(__FILE__)),
			'sourcedir' => addslashes(dirname(__FILE__)) . '/Sources',
			'cachedir' => addslashes(dirname(__FILE__)) . '/cache',
			'mbname' => strtr($_POST['mbname'], array('\"' => '"')),
			'language' => substr($_SESSION['installer_temp_lang'], 8, -4),
		);

		// Must save!
		if (!updateSettingsFile($vars) && substr(__FILE__, 1, 2) == ':\\')
		{
			$incontext['error'] = $txt['error_windows_chmod'];
			return false;
		}

		// Make sure it works.
		require(dirname(__FILE__) . '/Settings.php');

		// UTF-8 requires a setting to override the language charset.
		if ((!empty($databases[$db_type]['utf8_support']) && !empty($databases[$db_type]['utf8_required'])) || (empty($databases[$db_type]['utf8_required']) && !empty($databases[$db_type]['utf8_support']) && isset($_POST['utf8'])))
		{
			if (!empty($databases[$db_type]['utf8_version_check']) && version_compare($databases[$db_type]['utf8_version'], preg_replace('~\-.+?$~', '', eval($databases[$db_type]['utf8_version_check'])), '>'))
			{
				$incontext['error'] = sprintf($txt['error_utf8_version'], $databases[$db_type]['utf8_version']);
				return false;
			}
			else
				// Set the character set here.
				updateSettingsFile(array('db_character_set' => 'utf8'));
		}

		// Good, skip on.
		return true;
	}

	return false;
}

// Step one: Do the SQL thang.
function DatabasePopulation()
{
	global $db_character_set, $txt, $db_connection, $smcFunc, $databases, $modSettings, $db_type, $sourcedir, $db_prefix, $incontext, $db_name, $boardurl;

	$incontext['sub_template'] = 'populate_database';
	$incontext['page_title'] = $txt['db_populate'];
	$incontext['continue'] = 1;

	// Already done?
	if (isset($_POST['pop_done']))
		return true;

	// Reload settings.
	require(dirname(__FILE__) . '/Settings.php');
	load_database();

	// Before running any of the queries, let's make sure another version isn't already installed.
	$result = $smcFunc['db_query']('', '
		SELECT variable, value
		FROM {db_prefix}settings',
		array(
			'db_error_skip' => true,
		)
	);
	$modSettings = array();
	if ($result !== false)
	{
		while ($row = $smcFunc['db_fetch_assoc']($result))
			$modSettings[$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($result);

		// Do they match?  If so, this is just a refresh so charge on!
		if (!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] != $GLOBALS['current_smf_version'])
		{
			$incontext['error'] = $txt['error_versions_do_not_match'];
			return false;
		}
	}
	$modSettings['disableQueryCheck'] = true;

	// If doing UTF8, select it. PostgreSQL requires passing it as a string...
	if (!empty($db_character_set) && $db_character_set == 'utf8' && !empty($databases[$db_type]['utf8_support']))
		$smcFunc['db_query']('', '
			SET NAMES {'. ($db_type == 'postgresql' ? 'string' : 'raw') . ':utf8}',
			array(
				'db_error_skip' => true,
				'utf8' => 'utf8',
			)
		);

	$replaces = array(
		'{$db_prefix}' => $db_prefix,
		'{$boarddir}' => $smcFunc['db_escape_string'](dirname(__FILE__)),
		'{$boardurl}' => $boardurl,
		'{$enableCompressedOutput}' => isset($_POST['compress']) ? '1' : '0',
		'{$databaseSession_enable}' => isset($_POST['dbsession']) ? '1' : '0',
		'{$smf_version}' => $GLOBALS['current_smf_version'],
		'{$current_time}' => time(),
		'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
		'{$registration_method}' => isset($_POST['reg_mode']) ? $_POST['reg_mode'] : 0,
	);

	foreach ($txt as $key => $value)
	{
		if (substr($key, 0, 8) == 'default_')
			$replaces['{$' . $key . '}'] = $smcFunc['db_escape_string']($value);
	}
	$replaces['{$default_reserved_names}'] = strtr($replaces['{$default_reserved_names}'], array('\\\\n' => '\\n'));

	// If the UTF-8 setting was enabled, add it to the table definitions.
	if (!empty($databases[$db_type]['utf8_support']) && (!empty($databases[$db_type]['utf8_required']) || isset($_POST['utf8'])))
		$replaces[') ENGINE=MyISAM;'] = ') ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;';

	// Read in the SQL.  Turn this on and that off... internationalize... etc.
	$type = ($db_type == 'mysqli') ? 'mysql' : $db_type;
	$sql_lines = explode("\n", strtr(implode(' ', file(dirname(__FILE__) . '/install_' . $GLOBALS['db_script_version'] . '_' . $type . '.sql')), $replaces));

	// Execute the SQL.
	$current_statement = '';
	$exists = array();
	$incontext['failures'] = array();
	$incontext['sql_results'] = array(
		'tables' => 0,
		'inserts' => 0,
		'table_dups' => 0,
		'insert_dups' => 0,
	);
	foreach ($sql_lines as $count => $line)
	{
		// No comments allowed!
		if (substr(trim($line), 0, 1) != '#')
			$current_statement .= "\n" . rtrim($line);

		// Is this the end of the query string?
		if (empty($current_statement) || (preg_match('~;[\s]*$~s', $line) == 0 && $count != count($sql_lines)))
			continue;

		// Does this table already exist?  If so, don't insert more data into it!
		if (preg_match('~^\s*INSERT INTO ([^\s\n\r]+?)~', $current_statement, $match) != 0 && in_array($match[1], $exists))
		{
			$incontext['sql_results']['insert_dups']++;
			$current_statement = '';
			continue;
		}

		if ($smcFunc['db_query']('', $current_statement, array('security_override' => true, 'db_error_skip' => true), $db_connection) === false)
		{
			// Use the appropriate function based on the DB type
			if ($db_type == 'mysql' || $db_type =='mysqli')
				$db_errorno = $db_type . '_errno';

			// Error 1050: Table already exists!
			// @todo Needs to be made better!
			if ((($db_type != 'mysql' && $db_type != 'mysqli') || $db_errorno($db_connection) == 1050) && preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1)
			{
				$exists[] = $match[1];
				$incontext['sql_results']['table_dups']++;
			}
			// Don't error on duplicate indexes (or duplicate operators in PostgreSQL.)
			elseif (!preg_match('~^\s*CREATE( UNIQUE)? INDEX ([^\n\r]+?)~', $current_statement, $match) && !($db_type == 'postgresql' && preg_match('~^\s*CREATE OPERATOR (^\n\r]+?)~', $current_statement, $match)))
			{
				// MySQLi requires a connection object. It's optional with MySQL, Postgres and sqlite
				$incontext['failures'][$count] = $smcFunc['db_error']($db_connection);
			}
		}
		else
		{
			if (preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1)
				$incontext['sql_results']['tables']++;
			else
			{
				preg_match_all('~\)[,;]~', $current_statement, $matches);
				if (!empty($matches[0]))
					$incontext['sql_results']['inserts'] += count($matches[0]);
				else
					$incontext['sql_results']['inserts']++;
			}
		}

		$current_statement = '';
	}

	// Sort out the context for the SQL.
	foreach ($incontext['sql_results'] as $key => $number)
	{
		if ($number == 0)
			unset($incontext['sql_results'][$key]);
		else
			$incontext['sql_results'][$key] = sprintf($txt['db_populate_' . $key], $number);
	}

	// Make sure UTF will be used globally.
	if ((!empty($databases[$db_type]['utf8_support']) && !empty($databases[$db_type]['utf8_required'])) || (empty($databases[$db_type]['utf8_required']) && !empty($databases[$db_type]['utf8_support']) && isset($_POST['utf8'])))
		$smcFunc['db_insert']('replace',
			$db_prefix . 'settings',
			array(
				'variable' => 'string-255', 'value' => 'string-65534',
			),
			array(
				'global_character_set', 'UTF-8',
			),
			array('variable')
		);

	// Maybe we can auto-detect better cookie settings?
	preg_match('~^http[s]?://([^\.]+?)([^/]*?)(/.*)?$~', $boardurl, $matches);
	if (!empty($matches))
	{
		// Default = both off.
		$localCookies = false;
		$globalCookies = false;

		// Okay... let's see.  Using a subdomain other than www.? (not a perfect check.)
		if ($matches[2] != '' && (strpos(substr($matches[2], 1), '.') === false || in_array($matches[1], array('forum', 'board', 'community', 'forums', 'support', 'chat', 'help', 'talk', 'boards', 'www'))))
			$globalCookies = true;
		// If there's a / in the middle of the path, or it starts with ~... we want local.
		if (isset($matches[3]) && strlen($matches[3]) > 3 && (substr($matches[3], 0, 2) == '/~' || strpos(substr($matches[3], 1), '/') !== false))
			$localCookies = true;

		if ($globalCookies)
			$rows[] = array('globalCookies', '1');
		if ($localCookies)
			$rows[] = array('localCookies', '1');

		if (!empty($rows))
		{
			$smcFunc['db_insert']('replace',
				$db_prefix . 'settings',
				array('variable' => 'string-255', 'value' => 'string-65534'),
				$rows,
				array('variable')
			);
		}
	}

	// Are we allowing stat collection?
	if (isset($_POST['stats']) && strpos($_POST['boardurl'], 'http://localhost') !== 0)
	{
		// Attempt to register the site etc.
		$fp = @fsockopen("www.simplemachines.org", 80, $errno, $errstr);
		if ($fp)
		{
			$out = "GET /smf/stats/register_stats.php?site=" . base64_encode($_POST['boardurl']) . " HTTP/1.1\r\n";
			$out .= "Host: www.simplemachines.org\r\n";
			$out .= "Connection: Close\r\n\r\n";
			fwrite($fp, $out);

			$return_data = '';
			while (!feof($fp))
				$return_data .= fgets($fp, 128);

			fclose($fp);

			// Get the unique site ID.
			preg_match('~SITE-ID:\s(\w{10})~', $return_data, $ID);

			if (!empty($ID[1]))
				$smcFunc['db_insert']('',
					$db_prefix . 'settings',
					array(
						'variable' => 'string-255', 'value' => 'string-65534',
					),
					array(
						'allow_sm_stats', $ID[1],
					),
					array('variable')
				);
		}
	}

	// As of PHP 5.1, setting a timezone is required.
	if (!isset($modSettings['default_timezone']) && function_exists('date_default_timezone_set'))
	{
		$server_offset = mktime(0, 0, 0, 1, 1, 1970);
		$timezone_id = 'Etc/GMT' . ($server_offset > 0 ? '+' : '') . ($server_offset / 3600);
		if (date_default_timezone_set($timezone_id))
			$smcFunc['db_insert']('',
				$db_prefix . 'settings',
				array(
					'variable' => 'string-255', 'value' => 'string-65534',
				),
				array(
					'default_timezone', $timezone_id,
				),
				array('variable')
			);
	}

	// Let's optimize those new tables.
	db_extend();
	$tables = $smcFunc['db_list_tables']($db_name, $db_prefix . '%');
	foreach ($tables as $table)
	{
		$smcFunc['db_optimize_table']($table) != -1 or $db_messed = true;

		// Optimizing one sqlite table, optimizes them all
		if ($db_type == 'sqlite' || $db_type == 'sqlite3')
			break;

		if (!empty($db_messed))
		{
			$incontext['failures'][-1] = $smcFunc['db_error']();
			break;
		}
	}

	// Check for the ALTER privilege.
	if (!empty($databases[$db_type]['alter_support']) && $smcFunc['db_query']('', "ALTER TABLE {$db_prefix}boards ORDER BY id_board", array('security_override' => true, 'db_error_skip' => true)) === false)
	{
		$incontext['error'] = $txt['error_db_alter_priv'];
		return false;
	}

	if (!empty($exists))
	{
		$incontext['page_title'] = $txt['user_refresh_install'];
		$incontext['was_refresh'] = true;
	}

	return false;
}

// Ask for the administrator login information.
function AdminAccount()
{
	global $txt, $db_type, $db_connection, $databases, $smcFunc, $incontext, $db_prefix, $db_passwd, $sourcedir;

	$incontext['sub_template'] = 'admin_account';
	$incontext['page_title'] = $txt['user_settings'];
	$incontext['continue'] = 1;

	// Skipping?
	if (!empty($_POST['skip']))
		return true;

	// Need this to check whether we need the database password.
	require(dirname(__FILE__) . '/Settings.php');
	load_database();

	// Define the sha1 function, if it doesn't exist.
	if (!function_exists('sha1') || version_compare(PHP_VERSION, '5', '<'))
		require_once($sourcedir . '/Subs-Compat.php');

	if (!isset($_POST['username']))
		$_POST['username'] = '';
	if (!isset($_POST['email']))
		$_POST['email'] = '';

	$incontext['username'] = htmlspecialchars(stripslashes($_POST['username']));
	$incontext['email'] = htmlspecialchars(stripslashes($_POST['email']));

	$incontext['require_db_confirm'] = empty($db_type) || ($db_type != 'sqlite' && $db_type != 'sqlite3');

	// Only allow skipping if we think they already have an account setup.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
		LIMIT 1',
		array(
			'db_error_skip' => true,
			'admin_group' => 1,
		)
	);
	if ($smcFunc['db_num_rows']($request) != 0)
		$incontext['skip'] = 1;
	$smcFunc['db_free_result']($request);

	// Trying to create an account?
	if (isset($_POST['password1']) && !empty($_POST['contbutt']))
	{
		// Wrong password?
		if ($incontext['require_db_confirm'] && $_POST['password3'] != $db_passwd)
		{
			$incontext['error'] = $txt['error_db_connect'];
			return false;
		}
		// Not matching passwords?
		if ($_POST['password1'] != $_POST['password2'])
		{
			$incontext['error'] = $txt['error_user_settings_again_match'];
			return false;
		}
		// No password?
		if (strlen($_POST['password1']) < 4)
		{
			$incontext['error'] = $txt['error_user_settings_no_password'];
			return false;
		}
		if (!file_exists($sourcedir . '/Subs.php'))
		{
			$incontext['error'] = $txt['error_subs_missing'];
			return false;
		}

		// Update the main contact email?
		if (!empty($_POST['email']) && (empty($webmaster_email) || $webmaster_email == 'noreply@myserver.com'))
			updateSettingsFile(array('webmaster_email' => $_POST['email']));

		// Work out whether we're going to have dodgy characters and remove them.
		$invalid_characters = preg_match('~[<>&"\'=\\\]~', $_POST['username']) != 0;
		$_POST['username'] = preg_replace('~[<>&"\'=\\\]~', '', $_POST['username']);

		$result = $smcFunc['db_query']('', '
			SELECT id_member, password_salt
			FROM {db_prefix}members
			WHERE member_name = {string:username} OR email_address = {string:email}
			LIMIT 1',
			array(
				'username' => stripslashes($_POST['username']),
				'email' => stripslashes($_POST['email']),
				'db_error_skip' => true,
			)
		);
		if ($smcFunc['db_num_rows']($result) != 0)
		{
			list ($incontext['member_id'], $incontext['member_salt']) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			$incontext['account_existed'] = $txt['error_user_settings_taken'];
		}
		elseif ($_POST['username'] == '' || strlen($_POST['username']) > 25)
		{
			// Try the previous step again.
			$incontext['error'] = $_POST['username'] == '' ? $txt['error_username_left_empty'] : $txt['error_username_too_long'];
			return false;
		}
		elseif ($invalid_characters || $_POST['username'] == '_' || $_POST['username'] == '|' || strpos($_POST['username'], '[code') !== false || strpos($_POST['username'], '[/code') !== false)
		{
			// Try the previous step again.
			$incontext['error'] = $txt['error_invalid_characters_username'];
			return false;
		}
		elseif (empty($_POST['email']) || preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', stripslashes($_POST['email'])) === 0 || strlen(stripslashes($_POST['email'])) > 255)
		{
			// One step back, this time fill out a proper email address.
			$incontext['error'] = sprintf($txt['error_valid_email_needed'], $_POST['username']);
			return false;
		}
		elseif ($_POST['username'] != '')
		{
			$incontext['member_salt'] = substr(md5(mt_rand()), 0, 4);

			// Format the username properly.
			$_POST['username'] = preg_replace('~[\t\n\r\x0B\0\xA0]+~', ' ', $_POST['username']);
			$ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 255) : '';

			$request = $smcFunc['db_insert']('',
				$db_prefix . 'members',
				array(
					'member_name' => 'string-25', 'real_name' => 'string-25', 'passwd' => 'string', 'email_address' => 'string',
					'id_group' => 'int', 'posts' => 'int', 'date_registered' => 'int', 'hide_email' => 'int',
					'password_salt' => 'string', 'lngfile' => 'string', 'personal_text' => 'string', 'avatar' => 'string',
					'member_ip' => 'string', 'member_ip2' => 'string', 'buddy_list' => 'string', 'pm_ignore_list' => 'string',
					'website_title' => 'string', 'website_url' => 'string', 'location' => 'string',
					'aim' => 'string', 'icq' => 'string', 'skype' => 'string', 'signature' => 'string', 'usertitle' => 'string', 'secret_question' => 'string',
					'additional_groups' => 'string', 'ignore_boards' => 'string', 'openid_uri' => 'string',
				),
				array(
					stripslashes($_POST['username']), stripslashes($_POST['username']), sha1(strtolower(stripslashes($_POST['username'])) . stripslashes($_POST['password1'])), stripslashes($_POST['email']),
					1, 0, time(), 0,
					$incontext['member_salt'], '', '', '',
					$ip, $ip, '', '',
					'', '', '',
					'', '', '', '', '', '',
					'', '', '',
				),
				array('id_member')
			);

			// Awww, crud!
			if ($request === false)
			{
				$incontext['error'] = $txt['error_user_settings_query'] . '<br>
				<div style="margin: 2ex;">' . nl2br(htmlspecialchars($smcFunc['db_error']($db_connection))) . '</div>';
				return false;
			}

			$incontext['member_id'] = $smcFunc['db_insert_id']("{$db_prefix}members", 'id_member');
		}

		// If we're here we're good.
		return true;
	}

	return false;
}

// Final step, clean up and a complete message!
function DeleteInstall()
{
	global $txt, $db_prefix, $db_connection, $HTTP_SESSION_VARS, $cookiename, $incontext;
	global $smcFunc, $db_character_set, $mbname, $context, $scripturl, $boardurl;
	global $current_smf_version, $databases, $sourcedir, $forum_version, $modSettings, $user_info, $language, $db_type;

	$incontext['page_title'] = $txt['congratulations'];
	$incontext['sub_template'] = 'delete_install';
	$incontext['continue'] = 0;

	require(dirname(__FILE__) . '/Settings.php');
	load_database();

	chdir(dirname(__FILE__));

	require_once($sourcedir . '/Errors.php');
	require_once($sourcedir . '/Logging.php');
	require_once($sourcedir . '/Subs.php');
	require_once($sourcedir . '/Load.php');
	require_once($sourcedir . '/Security.php');
	require_once($sourcedir . '/Subs-Auth.php');

	// Bring a warning over.
	if (!empty($incontext['account_existed']))
		$incontext['warning'] = $incontext['account_existed'];

	if (!empty($db_character_set) && !empty($databases[$db_type]['utf8_support']))
		$smcFunc['db_query']('', '
			SET NAMES {raw:db_character_set}',
			array(
				'db_character_set' => $db_character_set,
				'db_error_skip' => true,
			)
		);

	// As track stats is by default enabled let's add some activity.
	$smcFunc['db_insert']('ignore',
		'{db_prefix}log_activity',
		array('date' => 'date', 'topics' => 'int', 'posts' => 'int', 'registers' => 'int'),
		array(strftime('%Y-%m-%d', time()), 1, 1, (!empty($incontext['member_id']) ? 1 : 0)),
		array('date')
	);

	// We're going to want our lovely $modSettings now.
	$request = $smcFunc['db_query']('', '
		SELECT variable, value
		FROM {db_prefix}settings',
		array(
			'db_error_skip' => true,
		)
	);
	// Only proceed if we can load the data.
	if ($request)
	{
		while ($row = $smcFunc['db_fetch_row']($request))
			$modSettings[$row[0]] = $row[1];
		$smcFunc['db_free_result']($request);
	}

	// Automatically log them in ;)
	if (isset($incontext['member_id']) && isset($incontext['member_salt']))
		setLoginCookie(3153600 * 60, $incontext['member_id'], sha1(sha1(strtolower($_POST['username']) . $_POST['password1']) . $incontext['member_salt']));

	$result = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:db_sessions}',
		array(
			'db_sessions' => 'databaseSession_enable',
			'db_error_skip' => true,
		)
	);
	if ($smcFunc['db_num_rows']($result) != 0)
		list ($db_sessions) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if (empty($db_sessions))
		$_SESSION['admin_time'] = time();
	else
	{
		$_SERVER['HTTP_USER_AGENT'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 211);

		$smcFunc['db_insert']('replace',
			'{db_prefix}sessions',
			array(
				'session_id' => 'string', 'last_update' => 'int', 'data' => 'string',
			),
			array(
				session_id(), time(), 'USER_AGENT|s:' . strlen($_SERVER['HTTP_USER_AGENT']) . ':"' . $_SERVER['HTTP_USER_AGENT'] . '";admin_time|i:' . time() . ';',
			),
			array('session_id')
		);
	}

	updateStats('member');
	updateStats('message');
	updateStats('topic');

	// This function is needed to do the updateStats('subject') call.
	$smcFunc['strtolower'] = $db_character_set === 'utf8' || $txt['lang_character_set'] === 'UTF-8' ? create_function('$string', '
		return $string;') : 'strtolower';

	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_msg = 1
			AND modified_time = 0
		LIMIT 1',
		array(
			'db_error_skip' => true,
		)
	);
	$context['utf8'] = $db_character_set === 'utf8' || $txt['lang_character_set'] === 'UTF-8';
	if ($smcFunc['db_num_rows']($request) > 0)
		updateStats('subject', 1, htmlspecialchars($txt['default_topic_subject']));
	$smcFunc['db_free_result']($request);

	// Now is the perfect time to fetch the SM files.
	require_once($sourcedir . '/ScheduledTasks.php');
	// Sanity check that they loaded earlier!
	if (isset($modSettings['recycle_board']))
	{
		$forum_version = $current_smf_version;  // The variable is usually defined in index.php so lets just use our variable to do it for us.
		scheduled_fetchSMfiles(); // Now go get those files!

		// We've just installed!
		$user_info['ip'] = $_SERVER['REMOTE_ADDR'];
		$user_info['id'] = isset($incontext['member_id']) ? $incontext['member_id'] : 0;
		logAction('install', array('version' => $forum_version), 'admin');
	}

	// Check if we need some stupid MySQL fix.
	$server_version = $smcFunc['db_server_info']();
	if (($db_type == 'mysql' || $db_type == 'mysqli') && in_array(substr($server_version, 0, 6), array('5.0.50', '5.0.51')))
		updateSettings(array('db_mysql_group_by_fix' => '1'));

	// Some final context for the template.
	$incontext['dir_still_writable'] = is_writable(dirname(__FILE__)) && substr(__FILE__, 1, 2) != ':\\';
	$incontext['probably_delete_install'] = isset($_SESSION['installer_temp_ftp']) || is_writable(dirname(__FILE__)) || is_writable(__FILE__);

	return false;
}

// http://www.faqs.org/rfcs/rfc959.html
class ftp_connection
{
	var $connection = 'no_connection', $error = false, $last_message, $pasv = array();

	// Create a new FTP connection...
	function ftp_connection($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@simplemachines.org')
	{
		if ($ftp_server !== null)
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
	}

	function connect($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@simplemachines.org')
	{
		if (substr($ftp_server, 0, 6) == 'ftp://')
			$ftp_server = substr($ftp_server, 6);
		elseif (substr($ftp_server, 0, 7) == 'ftps://')
			$ftp_server = 'ssl://' . substr($ftp_server, 7);
		if (substr($ftp_server, 0, 7) == 'http://')
			$ftp_server = substr($ftp_server, 7);
		$ftp_server = strtr($ftp_server, array('/' => '', ':' => '', '@' => ''));

		// Connect to the FTP server.
		$this->connection = @fsockopen($ftp_server, $ftp_port, $err, $err, 5);
		if (!$this->connection)
		{
			$this->error = 'bad_server';
			return;
		}

		// Get the welcome message...
		if (!$this->check_response(220))
		{
			$this->error = 'bad_response';
			return;
		}

		// Send the username, it should ask for a password.
		fwrite($this->connection, 'USER ' . $ftp_user . "\r\n");
		if (!$this->check_response(331))
		{
			$this->error = 'bad_username';
			return;
		}

		// Now send the password... and hope it goes okay.
		fwrite($this->connection, 'PASS ' . $ftp_pass . "\r\n");
		if (!$this->check_response(230))
		{
			$this->error = 'bad_password';
			return;
		}
	}

	function chdir($ftp_path)
	{
		if (!is_resource($this->connection))
			return false;

		// No slash on the end, please...
		if (substr($ftp_path, -1) == '/')
			$ftp_path = substr($ftp_path, 0, -1);

		fwrite($this->connection, 'CWD ' . $ftp_path . "\r\n");
		if (!$this->check_response(250))
		{
			$this->error = 'bad_path';
			return false;
		}

		return true;
	}

	function chmod($ftp_file, $chmod)
	{
		if (!is_resource($this->connection))
			return false;

		// Convert the chmod value from octal (0777) to text ("777")
		fwrite($this->connection, 'SITE CHMOD ' . decoct($chmod) . ' ' . $ftp_file . "\r\n");
		if (!$this->check_response(200))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	function unlink($ftp_file)
	{
		// We are actually connected, right?
		if (!is_resource($this->connection))
			return false;

		// Delete file X.
		fwrite($this->connection, 'DELE ' . $ftp_file . "\r\n");
		if (!$this->check_response(250))
		{
			fwrite($this->connection, 'RMD ' . $ftp_file . "\r\n");

			// Still no love?
			if (!$this->check_response(250))
			{
				$this->error = 'bad_file';
				return false;
			}
		}

		return true;
	}

	function check_response($desired)
	{
		// Wait for a response that isn't continued with -, but don't wait too long.
		$time = time();
		do
			$this->last_message = fgets($this->connection, 1024);
		while (substr($this->last_message, 3, 1) != ' ' && time() - $time < 5);

		// Was the desired response returned?
		return is_array($desired) ? in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}

	function passive()
	{
		// We can't create a passive data connection without a primary one first being there.
		if (!is_resource($this->connection))
			return false;

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@fwrite($this->connection, "PASV\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while (substr($response, 3, 1) != ' ' && time() - $time < 5);

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if (substr($response, 0, 4) != '227 ')
		{
			$this->error = 'bad_response';
			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $response, $match) == 0)
		{
			$this->error = 'bad_response';
			return false;
		}

		// This is pretty simple - store it for later use ;)
		$this->pasv = array('ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6]);

		return true;
	}

	function create_file($ftp_file)
	{
		// First, we have to be connected... very important.
		if (!is_resource($this->connection))
			return false;

		// I'd like one passive mode, please!
		if (!$this->passive())
			return false;

		// Seems logical enough, so far...
		fwrite($this->connection, 'STOR ' . $ftp_file . "\r\n");

		// Okay, now we connect to the data port.  If it doesn't work out, it's probably "file already exists", etc.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_file';
			@fclose($fp);
			return false;
		}

		// This may look strange, but we're just closing it to indicate a zero-byte upload.
		fclose($fp);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return true;
	}

	function list_dir($ftp_path = '', $search = false)
	{
		// Are we even connected...?
		if (!is_resource($this->connection))
			return false;

		// Passive... non-agressive...
		if (!$this->passive())
			return false;

		// Get the listing!
		fwrite($this->connection, 'LIST -1' . ($search ? 'R' : '') . ($ftp_path == '' ? '' : ' ' . $ftp_path) . "\r\n");

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(array(150, 125)))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			return false;
		}

		// Read in the file listing.
		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return $data;
	}

	function locate($file, $listing = null)
	{
		if ($listing === null)
			$listing = $this->list_dir('', true);
		$listing = explode("\n", $listing);

		@fwrite($this->connection, "PWD\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while (substr($response, 3, 1) != ' ' && time() - $time < 5);

		// Check for 257!
		if (preg_match('~^257 "(.+?)" ~', $response, $match) != 0)
			$current_dir = strtr($match[1], array('""' => '"'));
		else
			$current_dir = '';

		for ($i = 0, $n = count($listing); $i < $n; $i++)
		{
			if (trim($listing[$i]) == '' && isset($listing[$i + 1]))
			{
				$current_dir = substr(trim($listing[++$i]), 0, -1);
				$i++;
			}

			// Okay, this file's name is:
			$listing[$i] = $current_dir . '/' . trim(strlen($listing[$i]) > 30 ? strrchr($listing[$i], ' ') : $listing[$i]);

			if (substr($file, 0, 1) == '*' && substr($listing[$i], -(strlen($file) - 1)) == substr($file, 1))
				return $listing[$i];
			if (substr($file, -1) == '*' && substr($listing[$i], 0, strlen($file) - 1) == substr($file, 0, -1))
				return $listing[$i];
			if (basename($listing[$i]) == $file || $listing[$i] == $file)
				return $listing[$i];
		}

		return false;
	}

	function create_dir($ftp_dir)
	{
		// We must be connected to the server to do something.
		if (!is_resource($this->connection))
			return false;

		// Make this new beautiful directory!
		fwrite($this->connection, 'MKD ' . $ftp_dir . "\r\n");
		if (!$this->check_response(257))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	function detect_path($filesystem_path, $lookup_file = null)
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT']))
		{
			if (preg_match('~^/home[2]?/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match))
			{
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], array('/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => ''));

				if (substr($path, -1) == '/')
					$path = substr($path, 0, -1);

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1)
					$path .= dirname($_SERVER['PHP_SELF']);
			}
			elseif (substr($filesystem_path, 0, 9) == '/var/www/')
				$path = substr($filesystem_path, 8);
			else
				$path = strtr(strtr($filesystem_path, array('\\' => '/')), array($_SERVER['DOCUMENT_ROOT'] => ''));
		}
		else
			$path = '';

		if (is_resource($this->connection) && $this->list_dir($path) == '')
		{
			$data = $this->list_dir('', true);

			if ($lookup_file === null)
				$lookup_file = $_SERVER['PHP_SELF'];

			$found_path = dirname($this->locate('*' . basename(dirname($lookup_file)) . '/' . basename($lookup_file), $data));
			if ($found_path == false)
				$found_path = dirname($this->locate(basename($lookup_file)));
			if ($found_path != false)
				$path = $found_path;
		}
		elseif (is_resource($this->connection))
			$found_path = true;

		return array($username, $path, isset($found_path));
	}

	function close()
	{
		// Goodbye!
		fwrite($this->connection, "QUIT\r\n");
		fclose($this->connection);

		return true;
	}
}

function updateSettingsFile($vars)
{
	// Modify Settings.php.
	$settingsArray = file(dirname(__FILE__) . '/Settings.php');

	// @todo Do we just want to read the file in clean, and split it this way always?
	if (count($settingsArray) == 1)
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);

	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		// Remove the redirect...
		if (trim($settingsArray[$i]) == 'if (file_exists(dirname(__FILE__) . \'/install.php\'))' && trim($settingsArray[$i + 1]) == '{' && trim($settingsArray[$i + 3]) == '}')
		{
			// Get the four lines to nothing.
			$settingsArray[$i] = '';
			$settingsArray[++$i] = '';
			$settingsArray[++$i] = '';
			$settingsArray[++$i] = '';
			continue;
		}

		if (trim($settingsArray[$i]) == '?' . '>')
			$settingsArray[$i] = '';

		// Don't trim or bother with it if it's not a variable.
		if (substr($settingsArray[$i], 0, 1) != '$')
			continue;

		$settingsArray[$i] = rtrim($settingsArray[$i]) . "\n";

		foreach ($vars as $var => $val)
			if (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
			{
				$comment = strstr($settingsArray[$i], '#');
				$settingsArray[$i] = '$' . $var . ' = \'' . $val . '\';' . ($comment != '' ? "\t\t" . $comment : "\n");
				unset($vars[$var]);
			}
	}

	// Uh oh... the file wasn't empty... was it?
	if (!empty($vars))
	{
		$settingsArray[$i++] = '';
		foreach ($vars as $var => $val)
			$settingsArray[$i++] = '$' . $var . ' = \'' . $val . '\';' . "\n";
	}

	// Blank out the file - done to fix a oddity with some servers.
	$fp = @fopen(dirname(__FILE__) . '/Settings.php', 'w');
	if (!$fp)
		return false;
	fclose($fp);

	$fp = fopen(dirname(__FILE__) . '/Settings.php', 'r+');

	// Gotta have one of these ;)
	if (trim($settingsArray[0]) != '<?php')
		fwrite($fp, "<?php\n");

	$lines = count($settingsArray);
	for ($i = 0; $i < $lines - 1; $i++)
	{
		// Don't just write a bunch of blank lines.
		if ($settingsArray[$i] != '' || @$settingsArray[$i - 1] != '')
			fwrite($fp, strtr($settingsArray[$i], "\r", ''));
	}
	fwrite($fp, $settingsArray[$i] . '?' . '>');
	fclose($fp);

	// Even though on normal installations the filemtime should prevent this being used by the installer incorrectly
	// it seems that there are times it might not. So let's MAKE it dump the cache.
	if (function_exists('opcache_invalidate'))
		opcache_invalidate(dirname(__FILE__) . '/Settings.php', true);

	return true;
}

function updateDbLastError()
{
	// Write out the db_last_error file with the error timestamp
	file_put_contents(dirname(__FILE__) . '/db_last_error.php', '<' . '?' . "php\n" . '$db_last_error = 0;' . "\n" . '?' . '>');

	return true;
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

	if (!function_exists('apache_get_modules') || !in_array('mod_security', apache_get_modules()))
		return true;
	elseif (file_exists(dirname(__FILE__) . '/.htaccess') && is_writable(dirname(__FILE__) . '/.htaccess'))
	{
		$current_htaccess = implode('', file(dirname(__FILE__) . '/.htaccess'));

		// Only change something if mod_security hasn't been addressed yet.
		if (strpos($current_htaccess, '<IfModule mod_security.c>') === false)
		{
			if ($ht_handle = fopen(dirname(__FILE__) . '/.htaccess', 'a'))
			{
				fwrite($ht_handle, $htaccess_addition);
				fclose($ht_handle);
				return true;
			}
			else
				return false;
		}
		else
			return true;
	}
	elseif (file_exists(dirname(__FILE__) . '/.htaccess'))
		return strpos(implode('', file(dirname(__FILE__) . '/.htaccess')), '<IfModule mod_security.c>') !== false;
	elseif (is_writable(dirname(__FILE__)))
	{
		if ($ht_handle = fopen(dirname(__FILE__) . '/.htaccess', 'w'))
		{
			fwrite($ht_handle, $htaccess_addition);
			fclose($ht_handle);
			return true;
		}
		else
			return false;
	}
	else
		return false;
}

function template_install_above()
{
	global $incontext, $txt, $smfsite, $installurl;

	echo '<!DOCTYPE html>
<html', !empty($txt['lang_rtl']) ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', isset($txt['lang_character_set']) ? $txt['lang_character_set'] : 'ISO-8859-1', '">
		<meta name="robots" content="noindex">
		<title>', $txt['smf_installer'], '</title>
		<link rel="stylesheet" type="text/css" href="Themes/default/css/index.css?alp21">
		<link rel="stylesheet" type="text/css" href="Themes/default/css/install.css?alp21">
		<script src="Themes/default/scripts/script.js"></script>
	</head>
	<body>
		<div id="header">
			<div class="frame">
				<h1 class="forumtitle">', $txt['smf_installer'], '</h1>
				<img id="smflogo" src="Themes/default/images/smflogo.png" alt="Simple Machines Forum" title="Simple Machines Forum">
			</div>

		<div id="wrapper">
			<div id="upper_section">
				<div id="inner_section">
					<div id="inner_wrap">';

	// Have we got a language drop down - if so do it on the first step only.
	if (!empty($incontext['detected_languages']) && count($incontext['detected_languages']) > 1 && $incontext['current_step'] == 0)
	{
		echo '
						<div class="news">
							<form action="', $installurl, '" method="get">
								<label for="installer_language">', $txt['installer_language'], ':</label>
								<select id="installer_language" name="lang_file" onchange="location.href = \'', $installurl, '?lang_file=\' + this.options[this.selectedIndex].value;">';

		foreach ($incontext['detected_languages'] as $lang => $name)
			echo '
									<option', isset($_SESSION['installer_temp_lang']) && $_SESSION['installer_temp_lang'] == $lang ? ' selected' : '', ' value="', $lang, '">', $name, '</option>';

		echo '
								</select>
								<noscript><input type="submit" value="', $txt['installer_language_set'], '" class="button_submit" /></noscript>
							</form>
						</div>
						<hr class="clear" />';
	}

	echo '
					</div>
				</div>
			</div>
			<div id="content_section">
				<div id="main_content_section">
					<div id="main_steps">
						<h2>', $txt['upgrade_progress'], '</h2>
						<ul>';

	foreach ($incontext['steps'] as $num => $step)
		echo '
							<li class="', $num < $incontext['current_step'] ? 'stepdone' : ($num == $incontext['current_step'] ? 'stepcurrent' : 'stepwaiting'), '">', $txt['upgrade_step'], ' ', $step[0], ': ', $step[1], '</li>';

	echo '
						</ul>
					</div>
					<div id="progress_bar">
						<div id="overall_text">', $incontext['overall_percent'], '%</div>
						<div id="overall_progress" style="width: ', $incontext['overall_percent'], '%;">&nbsp;</div>
						<div class="overall_progress">', $txt['upgrade_overall_progress'], '</div>
					</div>
					<div id="main_screen" class="clear">
						<h2>', $incontext['page_title'], '</h2>
						<div class="panel">';
}

function template_install_below()
{
	global $incontext, $txt;

	if (!empty($incontext['continue']) || !empty($incontext['skip']))
	{
		echo '
								<div>';

		if (!empty($incontext['continue']))
			echo '
									<input type="submit" id="contbutt" name="contbutt" value="', $txt['upgrade_continue'], '" onclick="return submitThisOnce(this);" class="button_submit" />';
		if (!empty($incontext['skip']))
			echo '
									<input type="submit" id="skip" name="skip" value="', $txt['upgrade_skip'], '" onclick="return submitThisOnce(this);" class="button_submit" />';
		echo '
								</div>';
	}

	// Show the closing form tag and other data only if not in the last step
	if (count($incontext['steps']) - 1 !== (int) $incontext['current_step'])
		echo '
							</form>';

	echo '
						</div>
					</div>
				</div>
			</div>
		</div>
		<div id="footer_section">
			<div class="frame">
				<ul class="reset">
					<li class="copyright"><a href="http://www.simplemachines.org/" title="Simple Machines Forum" target="_blank" class="new_win">SMF &copy; 2014, Simple Machines</a></li>
				</ul>
			</div>
		</div>
	</body>
</html>';
}

// Welcome them to the wonderful world of SMF!
function template_welcome_message()
{
	global $incontext, $installurl, $txt;

	echo '
	<script src="http://www.simplemachines.org/smf/current-version.js?version=' . $GLOBALS['current_smf_version'] . '"></script>
	<form action="', $incontext['form_url'], '" method="post">
		<p>', sprintf($txt['install_welcome_desc'], $GLOBALS['current_smf_version']), '</p>
		<div id="version_warning" style="margin: 2ex; padding: 2ex; border: 2px dashed #a92174; color: black; background-color: #fbbbe2; display: none;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">', $txt['error_warning_notice'], '</strong><br />
			<div style="padding-left: 6ex;">
				', sprintf($txt['error_script_outdated'], '<em id="smfVersion" style="white-space: nowrap;">??</em>', '<em id="yourVersion" style="white-space: nowrap;">' . $GLOBALS['current_smf_version'] . '</em>'), '
			</div>
		</div>';

	// Show the warnings, or not.
	if (template_warning_divs())
		echo '
		<h3>', $txt['install_all_lovely'], '</h3>';

	// Say we want the continue button!
	if (empty($incontext['error']))
		$incontext['continue'] = 1;

	// For the latest version stuff.
	echo '
		<script><!-- // --><![CDATA[
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
					document.getElementById(\'version_warning\').style.display = \'\';
			}
			addLoadEvent(smfCurrentVersion);
		// ]]></script>';
}

// A shortcut for any warning stuff.
function template_warning_divs()
{
	global $txt, $incontext;

	// Errors are very serious..
	if (!empty($incontext['error']))
		echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">', $txt['upgrade_critical_error'], '</strong><br />
			<div style="padding-left: 6ex;">
				', $incontext['error'], '
			</div>
		</div>';
	// A warning message?
	elseif (!empty($incontext['warning']))
		echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong><br />
			<div style="padding-left: 6ex;">
				', $incontext['warning'], '
			</div>
		</div>';

	return empty($incontext['error']) && empty($incontext['warning']);
}

function template_chmod_files()
{
	global $txt, $incontext;

	echo '
		<p>', $txt['ftp_setup_why_info'], '</p>
		<ul style="margin: 2.5ex; font-family: monospace;">
			<li>', implode('</li>
			<li>', $incontext['failed_files']), '</li>
		</ul>';

	// This is serious!
	if (!template_warning_divs())
		return;

	echo '
		<hr />
		<p>', $txt['ftp_setup_info'], '</p>';

	if (!empty($incontext['ftp_errors']))
		echo '
		<div class="error_message">
			<div style="color: red;">
				', $txt['error_ftp_no_connect'], '<br />
				<br />
				<code>', implode('<br />', $incontext['ftp_errors']), '</code>
			</div>
		</div>
		<br />';

	echo '
		<form action="', $incontext['form_url'], '" method="post">
			<table width="520" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 1em 0;">
				<tr>
					<td width="26%" valign="top" class="textbox"><label for="ftp_server">', $txt['ftp_server'], ':</label></td>
					<td>
						<div style="float: ', empty($txt['lang_rtl']) ? 'right' : 'left', '; margin-', empty($txt['lang_rtl']) ? 'right' : 'left', ': 1px;"><label for="ftp_port" class="textbox"><strong>', $txt['ftp_port'], ':&nbsp;</strong></label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', $incontext['ftp']['port'], '" class="input_text" /></div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', $incontext['ftp']['server'], '" style="width: 70%;" class="input_text" />
						<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['ftp_server_info'], '</div>
					</td>
				</tr><tr>
					<td width="26%" valign="top" class="textbox"><label for="ftp_username">', $txt['ftp_username'], ':</label></td>
					<td>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $incontext['ftp']['username'], '" style="width: 99%;" class="input_text" />
						<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['ftp_username_info'], '</div>
					</td>
				</tr><tr>
					<td width="26%" valign="top" class="textbox"><label for="ftp_password">', $txt['ftp_password'], ':</label></td>
					<td>
						<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_password" />
						<div style="font-size: smaller; margin-bottom: 3ex;">', $txt['ftp_password_info'], '</div>
					</td>
				</tr><tr>
					<td width="26%" valign="top" class="textbox"><label for="ftp_path">', $txt['ftp_path'], ':</label></td>
					<td style="padding-bottom: 1ex;">
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $incontext['ftp']['path'], '" style="width: 99%;" class="input_text" />
						<div style="font-size: smaller; margin-bottom: 2ex;">', $incontext['ftp']['path_msg'], '</div>
					</td>
				</tr>
			</table>
			<div style="margin: 1ex; margin-top: 1ex; text-align: ', empty($txt['lang_rtl']) ? 'right' : 'left', ';"><input type="submit" value="', $txt['ftp_connect'], '" onclick="return submitThisOnce(this);" class="button_submit" /></div>
		</form>
		<a href="', $incontext['form_url'], '">', $txt['error_message_click'], '</a> ', $txt['ftp_setup_again'];
}

// Get the database settings prepared.
function template_database_settings()
{
	global $incontext, $installurl, $txt;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', $txt['db_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 1em 0;">';

	// More than one database type?
	if (count($incontext['supported_databases']) > 1)
	{
		echo '
			<tr>
				<td width="20%" valign="top" class="textbox"><label for="db_type_input">', $txt['db_settings_type'], ':</label></td>
				<td>
					<select name="db_type" id="db_type_input" onchange="toggleDBInput();">';

	foreach ($incontext['supported_databases'] as $key => $db)
			echo '
						<option value="', $key, '"', isset($_POST['db_type']) && $_POST['db_type'] == $key ? ' selected' : '', '>', $db['name'], '</option>';

	echo '
					</select><div id="db_sqlite_warning" style="color: blue; display: none;" class="smalltext">', $txt['db_sqlite_warning'], '</div>
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['db_settings_type_info'], '</div>
				</td>
			</tr>';
	}
	else
	{
		echo '
			<tr style="display: none;">
				<td>
					<input type="hidden" name="db_type" value="', $incontext['db']['type'], '" />
				</td>
			</tr>';
	}

	echo '
			<tr id="db_server_contain">
				<td width="20%" valign="top" class="textbox"><label for="db_server_input">', $txt['db_settings_server'], ':</label></td>
				<td>
					<input type="text" name="db_server" id="db_server_input" value="', $incontext['db']['server'], '" size="30" class="input_text" /><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['db_settings_server_info'], '</div>
				</td>
			</tr><tr id="db_user_contain">
				<td valign="top" class="textbox"><label for="db_user_input">', $txt['db_settings_username'], ':</label></td>
				<td>
					<input type="text" name="db_user" id="db_user_input" value="', $incontext['db']['user'], '" size="30" class="input_text" /><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['db_settings_username_info'], '</div>
				</td>
			</tr><tr id="db_passwd_contain">
				<td valign="top" class="textbox"><label for="db_passwd_input">', $txt['db_settings_password'], ':</label></td>
				<td>
					<input type="password" name="db_passwd" id="db_passwd_input" value="', $incontext['db']['pass'], '" size="30" class="input_password" /><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['db_settings_password_info'], '</div>
				</td>
			</tr><tr id="db_name_contain">
				<td valign="top" class="textbox"><label for="db_name_input">', $txt['db_settings_database'], ':</label></td>
				<td>
					<input type="text" name="db_name" id="db_name_input" value="', empty($incontext['db']['name']) ? 'smf' : $incontext['db']['name'], '" size="30" class="input_text" /><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['db_settings_database_info'], '
					<span id="db_name_info_warning">', $txt['db_settings_database_info_note'], '</span></div>
				</td>
			</tr><tr id="db_filename_contain" style="display: none;">
				<td valign="top" class="textbox"><label for="db_filename_input">', $txt['db_settings_database_file'], ':</label></td>
				<td>
					<input type="text" name="db_filename" id="db_filename_input" value="', empty($incontext['db']['name']) ? dirname(__FILE__) . '/smf_' . substr(md5(microtime()), 0, 10) : stripslashes($incontext['db']['name']), '" size="30" class="input_text" /><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['db_settings_database_file_info'], '</div>
				</td>
			</tr><tr>
				<td valign="top" class="textbox"><label for="db_prefix_input">', $txt['db_settings_prefix'], ':</label></td>
				<td>
					<input type="text" name="db_prefix" id="db_prefix_input" value="', $incontext['db']['prefix'], '" size="30" class="input_text" /><br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['db_settings_prefix_info'], '</div>
				</td>
			</tr>
		</table>';

	// Allow the toggling of input boxes for SQLite etc.
	echo '
	<script><!-- // --><![CDATA[
		function toggleDBInput()
		{
			// What state is it?';

	if (!isset($incontext['supported_databases']['sqlite']) && !isset($incontext['supported_databases']['sqlite3']))
		echo '
			var showAll = true;';
	elseif (count($incontext['supported_databases']) < 2)
		echo '
			var showAll = false;';
	// If we have more than one DB including SQLite, what should we be doing?
	else
		echo '
			var showAll = (document.getElementById(\'db_type_input\').value == \'sqlite\' || document.getElementById(\'db_type_input\').value == \'sqlite3\') ? false : true;';

	echo '
			document.getElementById(\'db_passwd_contain\').style.display = showAll ? \'\' : \'none\';
			document.getElementById(\'db_server_contain\').style.display = showAll ? \'\' : \'none\';
			document.getElementById(\'db_user_contain\').style.display = showAll ? \'\' : \'none\';
			document.getElementById(\'db_name_contain\').style.display = showAll ? \'\' : \'none\';
			document.getElementById(\'db_filename_contain\').style.display = !showAll ? \'\' : \'none\';
			document.getElementById(\'db_sqlite_warning\').style.display = !showAll ? \'\' : \'none\';
			if (document.getElementById(\'db_type_input\').value == \'postgresql\')
				document.getElementById(\'db_name_info_warning\').style.display = \'none\';
			else
				document.getElementById(\'db_name_info_warning\').style.display = \'\';
		}
		toggleDBInput();
	// ]]></script>';
}

// Stick in their forum settings.
function template_forum_settings()
{
	global $incontext, $installurl, $txt;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<h3>', $txt['install_settings_info'], '</h3>';

	template_warning_divs();

	echo '
		<table style="width: 100%; margin: 1em 0;">
			<tr>
				<td class="textbox" style="width: 20%; vertical-align: top;">
					<label for="mbname_input">', $txt['install_settings_name'], ':</label>
				</td>
				<td>
					<input type="text" name="mbname" id="mbname_input" value="', $txt['install_settings_name_default'], '" size="65" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_name_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox" style="vertical-align: top;">
					<label for="boardurl_input">', $txt['install_settings_url'], ':</label>
				</td>
				<td>
					<input type="text" name="boardurl" id="boardurl_input" value="', $incontext['detected_url'], '" size="65" class="input_text" />
					<br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_url_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox" style="vertical-align: top;">
					<label for="reg_mode">', $txt['install_settings_reg_mode'], ':</label>
				</td>
				<td>
					<select name="reg_mode" id="reg_mode">
						<optgroup label="', $txt['install_settings_reg_modes'], ':">
							<option value="0" selected>', $txt['install_settings_reg_immediate'], '</option>
							<option value="1">', $txt['install_settings_reg_email'], '</option>
							<option value="2">', $txt['install_settings_reg_admin'], '</option>
							<option value="3">', $txt['install_settings_reg_disabled'], '</option>
						</optgroup>
					</select>
					<br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_reg_mode_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox" style="vertical-align: top;">', $txt['install_settings_compress'], ':</td>
				<td>
					<input type="checkbox" name="compress" id="compress_check" checked class="input_check" />&nbsp;
					<label for="compress_check">', $txt['install_settings_compress_title'], '</label>
					<br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_compress_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox" style="vertical-align: top;">', $txt['install_settings_dbsession'], ':</td>
				<td>
					<input type="checkbox" name="dbsession" id="dbsession_check" checked class="input_check" />&nbsp;
					<label for="dbsession_check">', $txt['install_settings_dbsession_title'], '</label>
					<br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $incontext['test_dbsession'] ? $txt['install_settings_dbsession_info1'] : $txt['install_settings_dbsession_info2'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox" style="vertical-align: top;">', $txt['install_settings_utf8'], ':</td>
				<td>
					<input type="checkbox" name="utf8" id="utf8_check"', $incontext['utf8_default'] ? ' checked' : '', ' class="input_check"', $incontext['utf8_required'] ? ' disabled' : '', ' />&nbsp;
					<label for="utf8_check">', $txt['install_settings_utf8_title'], '</label>
					<br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_utf8_info'], '</div>
				</td>
			</tr>
			<tr>
				<td class="textbox" style="vertical-align: top;">', $txt['install_settings_stats'], ':</td>
				<td>
					<input type="checkbox" name="stats" id="stats_check" class="input_check" />&nbsp;
					<label for="stats_check">', $txt['install_settings_stats_title'], '</label>
					<br />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['install_settings_stats_info'], '</div>
				</td>
			</tr>
		</table>
	';
}

// Show results of the database population.
function template_populate_database()
{
	global $incontext, $installurl, $txt;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', !empty($incontext['was_refresh']) ? $txt['user_refresh_install_desc'] : $txt['db_populate_info'], '</p>';

	if (!empty($incontext['sql_results']))
	{
		echo '
		<ul>
			<li>', implode('</li><li>', $incontext['sql_results']), '</li>
		</ul>';
	}

	if (!empty($incontext['failures']))
	{
		echo '
				<div style="color: red;">', $txt['error_db_queries'], '</div>
				<ul>';

		foreach ($incontext['failures'] as $line => $fail)
			echo '
						<li><strong>', $txt['error_db_queries_line'], $line + 1, ':</strong> ', nl2br(htmlspecialchars($fail)), '</li>';

		echo '
				</ul>';
	}

	echo '
		<p>', $txt['db_populate_info2'], '</p>';

	template_warning_divs();

	echo '
	<input type="hidden" name="pop_done" value="1" />';
}

// Create the admin account.
function template_admin_account()
{
	global $incontext, $installurl, $txt;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', $txt['user_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<table width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 2em 0;">
			<tr>
				<td width="18%" valign="top" class="textbox"><label for="username">', $txt['user_settings_username'], ':</label></td>
				<td>
					<input type="text" name="username" id="username" value="', $incontext['username'], '" size="40" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['user_settings_username_info'], '</div>
				</td>
			</tr><tr>
				<td valign="top" class="textbox"><label for="password1">', $txt['user_settings_password'], ':</label></td>
				<td>
					<input type="password" name="password1" id="password1" size="40" class="input_password" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['user_settings_password_info'], '</div>
				</td>
			</tr><tr>
				<td valign="top" class="textbox"><label for="password2">', $txt['user_settings_again'], ':</label></td>
				<td>
					<input type="password" name="password2" id="password2" size="40" class="input_password" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['user_settings_again_info'], '</div>
				</td>
			</tr><tr>
				<td valign="top" class="textbox"><label for="email">', $txt['user_settings_email'], ':</label></td>
				<td>
					<input type="text" name="email" id="email" value="', $incontext['email'], '" size="40" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['user_settings_email_info'], '</div>
				</td>
			</tr>
		</table>';

	if ($incontext['require_db_confirm'])
		echo '
		<h2>', $txt['user_settings_database'], '</h2>
		<p>', $txt['user_settings_database_info'], '</p>

		<div style="margin-bottom: 2ex; padding-', empty($txt['lang_rtl']) ? 'left' : 'right', ': 50px;">
			<input type="password" name="password3" size="30" class="input_password" />
		</div>';
}

// Tell them it's done, and to delete.
function template_delete_install()
{
	global $incontext, $installurl, $txt, $boardurl;

	echo '
		<p>', $txt['congratulations_help'], '</p>';

	template_warning_divs();

	// Install directory still writable?
	if ($incontext['dir_still_writable'])
		echo '
		<em>', $txt['still_writable'], '</em><br />
		<br />';

	// Don't show the box if it's like 99% sure it won't work :P.
	if ($incontext['probably_delete_install'])
		echo '
		<div style="margin: 1ex; font-weight: bold;">
			<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete();" class="input_check" /> ', $txt['delete_installer'], !isset($_SESSION['installer_temp_ftp']) ? ' ' . $txt['delete_installer_maybe'] : '', '</label>
		</div>
		<script><!-- // --><![CDATA[
			function doTheDelete()
			{
				var theCheck = document.getElementById ? document.getElementById("delete_self") : document.all.delete_self;
				var tempImage = new Image();

				tempImage.src = "', $installurl, '?delete=1&ts_" + (new Date().getTime());
				tempImage.width = 0;
				theCheck.disabled = true;
			}
		// ]]></script>
		<br />';

	echo '
		', sprintf($txt['go_to_your_forum'], $boardurl . '/index.php'), '<br />
		<br />
		', $txt['good_luck'];
}

?>