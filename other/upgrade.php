<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

// Version information...
define('SMF_VERSION', '2.1 RC2');
define('SMF_FULL_VERSION', 'SMF ' . SMF_VERSION);
define('SMF_SOFTWARE_YEAR', '2019');
define('SMF_LANG_VERSION', '2.1 RC2');
define('SMF_INSTALLING', 1);

/**
 * The minimum required PHP version.
 *
 * @var string
 */
$GLOBALS['required_php_version'] = '5.4.0';

/**
 * A list of supported database systems.
 *
 * @var array
 */
$databases = array(
	'mysql' => array(
		'name' => 'MySQL',
		'version' => '5.0.22',
		'version_check' => 'global $db_connection; return min(mysqli_get_server_info($db_connection), mysqli_get_client_info());',
		'utf8_support' => true,
		'utf8_version' => '5.0.22',
		'utf8_version_check' => 'global $db_connection; return mysqli_get_server_info($db_connection);',
		'alter_support' => true,
	),
	'postgresql' => array(
		'name' => 'PostgreSQL',
		'version' => '9.4',
		'version_check' => '$version = pg_version(); return $version[\'client\'];',
		'always_has_db' => true,
	),
);

/**
 * The maximum time a single substep may take, in seconds.
 *
 * @var int
 */
$timeLimitThreshold = 3;

/**
 * The current path to the upgrade.php file.
 *
 * @var string
 */
$upgrade_path = dirname(__FILE__);

/**
 * The URL of the current page.
 *
 * @var string
 */
$upgradeurl = $_SERVER['PHP_SELF'];

/**
 * Flag to disable the required administrator login.
 *
 * @var bool
 */
$disable_security = false;

/**
 * The amount of seconds allowed between logins.
 * If the first user to login is inactive for this amount of seconds, a second login is allowed.
 *
 * @var int
 */
$upcontext['inactive_timeout'] = 10;

global $txt;

// All the steps in detail.
// Number,Name,Function,Progress Weight.
$upcontext['steps'] = array(
	0 => array(1, 'upgrade_step_login', 'WelcomeLogin', 2),
	1 => array(2, 'upgrade_step_options', 'UpgradeOptions', 2),
	2 => array(3, 'upgrade_step_backup', 'BackupDatabase', 10),
	3 => array(4, 'upgrade_step_database', 'DatabaseChanges', 50),
	4 => array(5, 'upgrade_step_convertjson', 'serialize_to_json', 10),
	5 => array(6, 'upgrade_step_convertutf', 'ConvertUtf8', 20),
	6 => array(7, 'upgrade_step_delete', 'DeleteUpgrade', 1),
);
// Just to remember which one has files in it.
$upcontext['database_step'] = 3;
@set_time_limit(600);
if (!ini_get('safe_mode'))
{
	ini_set('mysql.connect_timeout', -1);
	ini_set('default_socket_timeout', 900);
}
// Clean the upgrade path if this is from the client.
if (!empty($_SERVER['argv']) && php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']))
	for ($i = 1; $i < $_SERVER['argc']; $i++)
	{
		if (preg_match('~^--path=(.+)$~', $_SERVER['argv'][$i], $match) != 0)
			$upgrade_path = substr($match[1], -1) == '/' ? substr($match[1], 0, -1) : $match[1];
	}

// Are we from the client?
if (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']))
{
	$command_line = true;
	$disable_security = true;
}
else
	$command_line = false;

// We can't do anything without these files.
foreach (array('upgrade-helper.php', 'Settings.php') as $required_file)
{
	if (!file_exists($upgrade_path . '/' . $required_file))
		die($required_file . ' was not found where it was expected: ' . $upgrade_path . '/' . $required_file . '! Make sure you have uploaded ALL files from the upgrade package to your forum\'s root directory. The upgrader cannot continue.');

	require_once($upgrade_path . '/' . $required_file);
}

// We don't use "-utf8" anymore...  Tweak the entry that may have been loaded by Settings.php
if (isset($language))
	$language = str_ireplace('-utf8', '', basename($language, '.lng'));

// Figure out a valid language request (if any)
// Can't use $_GET until it's been cleaned, so do this manually and VERY restrictively! This even strips off those '-utf8' bits that we don't want.
if (isset($_SERVER['QUERY_STRING']) && preg_match('~\blang=(\w+)~', $_SERVER['QUERY_STRING'], $matches))
	$upcontext['lang'] = $matches[1];

// Are we logged in?
if (isset($upgradeData))
{
	$upcontext['user'] = json_decode(base64_decode($upgradeData), true);

	// Check for sensible values.
	if (empty($upcontext['user']['started']) || $upcontext['user']['started'] < time() - 86400)
		$upcontext['user']['started'] = time();
	if (empty($upcontext['user']['updated']) || $upcontext['user']['updated'] < time() - 86400)
		$upcontext['user']['updated'] = 0;

	$upcontext['started'] = $upcontext['user']['started'];
	$upcontext['updated'] = $upcontext['user']['updated'];

	$is_debug = !empty($upcontext['user']['debug']) ? true : false;

	$upcontext['skip_db_substeps'] = !empty($upcontext['user']['skip_db_substeps']);
}

// Nothing sensible?
if (empty($upcontext['updated']))
{
	$upcontext['started'] = time();
	$upcontext['updated'] = 0;
	$upcontext['skip_db_substeps'] = false;
	$upcontext['user'] = array(
		'id' => 0,
		'name' => 'Guest',
		'pass' => 0,
		'started' => $upcontext['started'],
		'updated' => $upcontext['updated'],
	);
}

// Try to load the language file... or at least define a few necessary strings for now.
load_lang_file();

// Load up some essential data...
loadEssentialData();

// Are we going to be mimic'ing SSI at this point?
if (isset($_GET['ssi']))
{
	require_once($sourcedir . '/Errors.php');
	require_once($sourcedir . '/Logging.php');
	require_once($sourcedir . '/Load.php');
	require_once($sourcedir . '/Security.php');
	require_once($sourcedir . '/Subs-Package.php');

	// SMF isn't started up properly, but loadUserSettings calls our cookies.
	if (!isset($smcFunc['json_encode']))
	{
		$smcFunc['json_encode'] = 'json_encode';
		$smcFunc['json_decode'] = 'smf_json_decode';
	}

	loadUserSettings();
	loadPermissions();
}

// Include our helper functions.
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/LogInOut.php');

// This only exists if we're on SMF ;)
if (isset($modSettings['smfVersion']))
{
	$request = $smcFunc['db_query']('', '
		SELECT variable, value
		FROM {db_prefix}themes
		WHERE id_theme = {int:id_theme}
			AND variable IN ({string:theme_url}, {string:theme_dir}, {string:images_url})',
		array(
			'id_theme' => 1,
			'theme_url' => 'theme_url',
			'theme_dir' => 'theme_dir',
			'images_url' => 'images_url',
			'db_error_skip' => true,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$modSettings[$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);
}

if (!isset($modSettings['theme_url']))
{
	$modSettings['theme_dir'] = $boarddir . '/Themes/default';
	$modSettings['theme_url'] = 'Themes/default';
	$modSettings['images_url'] = 'Themes/default/images';
}
if (!isset($settings['default_theme_url']))
	$settings['default_theme_url'] = $modSettings['theme_url'];
if (!isset($settings['default_theme_dir']))
	$settings['default_theme_dir'] = $modSettings['theme_dir'];

// This is needed in case someone invokes the upgrader using https when upgrading an http forum
if (httpsOn())
	$settings['default_theme_url'] = strtr($settings['default_theme_url'], array('http://' => 'https://'));

$upcontext['is_large_forum'] = (empty($modSettings['smfVersion']) || $modSettings['smfVersion'] <= '1.1 RC1') && !empty($modSettings['totalMessages']) && $modSettings['totalMessages'] > 75000;

// Have we got tracking data - if so use it (It will be clean!)
if (isset($_GET['data']))
{
	global $is_debug;

	$upcontext['upgrade_status'] = json_decode(base64_decode($_GET['data']), true);
	$upcontext['current_step'] = $upcontext['upgrade_status']['curstep'];
	$upcontext['language'] = $upcontext['upgrade_status']['lang'];
	$upcontext['rid'] = $upcontext['upgrade_status']['rid'];
	$support_js = $upcontext['upgrade_status']['js'];

	// Only set this if the upgrader status says so.
	if (empty($is_debug))
		$is_debug = $upcontext['upgrade_status']['debug'];
}
// Set the defaults.
else
{
	$upcontext['current_step'] = 0;
	$upcontext['rid'] = mt_rand(0, 5000);
	$upcontext['upgrade_status'] = array(
		'curstep' => 0,
		'lang' => isset($upcontext['lang']) ? $upcontext['lang'] : basename($language, '.lng'),
		'rid' => $upcontext['rid'],
		'pass' => 0,
		'debug' => 0,
		'js' => 0,
	);
	$upcontext['language'] = $upcontext['upgrade_status']['lang'];
}

// Now that we have the necessary info, make sure we loaded the right language file.
load_lang_file();

// Default title...
$upcontext['page_title'] = $txt['updating_smf_installation'];

// If this isn't the first stage see whether they are logging in and resuming.
if ($upcontext['current_step'] != 0 || !empty($upcontext['user']['step']))
	checkLogin();

if ($command_line)
	cmdStep0();

// Don't error if we're using xml.
if (isset($_GET['xml']))
	$upcontext['return_error'] = true;

// Loop through all the steps doing each one as required.
$upcontext['overall_percent'] = 0;
foreach ($upcontext['steps'] as $num => $step)
{
	if ($num >= $upcontext['current_step'])
	{
		// The current weight of this step in terms of overall progress.
		$upcontext['step_weight'] = $step[3];
		// Make sure we reset the skip button.
		$upcontext['skip'] = false;

		// We cannot proceed if we're not logged in.
		if ($num != 0 && !$disable_security && $upcontext['user']['pass'] != $upcontext['upgrade_status']['pass'])
		{
			$upcontext['steps'][0][2]();
			break;
		}

		// Call the step and if it returns false that means pause!
		if (function_exists($step[2]) && $step[2]() === false)
			break;
		elseif (function_exists($step[2]))
		{
			//Start each new step with this unset, so the 'normal' template is called first
			unset($_GET['xml']);
			//Clear out warnings at the start of each step
			unset($upcontext['custom_warning']);
			$_GET['substep'] = 0;
			$upcontext['current_step']++;
		}
	}
	$upcontext['overall_percent'] += $step[3];
}

upgradeExit();

// Exit the upgrade script.
function upgradeExit($fallThrough = false)
{
	global $upcontext, $upgradeurl, $sourcedir, $command_line, $is_debug, $txt;

	// Save where we are...
	if (!empty($upcontext['current_step']) && !empty($upcontext['user']['id']))
	{
		$upcontext['user']['step'] = $upcontext['current_step'];
		$upcontext['user']['substep'] = $_GET['substep'];
		$upcontext['user']['updated'] = time();
		$upcontext['user']['skip_db_substeps'] = !empty($upcontext['skip_db_substeps']);
		$upcontext['debug'] = $is_debug;
		$upgradeData = base64_encode(json_encode($upcontext['user']));
		require_once($sourcedir . '/Subs-Admin.php');
		updateSettingsFile(array('upgradeData' => '"' . $upgradeData . '"'));
		updateDbLastError(0);
	}

	// Handle the progress of the step, if any.
	if (!empty($upcontext['step_progress']) && isset($upcontext['steps'][$upcontext['current_step']]))
	{
		$upcontext['step_progress'] = round($upcontext['step_progress'], 1);
		$upcontext['overall_percent'] += $upcontext['step_progress'] * ($upcontext['steps'][$upcontext['current_step']][3] / 100);
	}
	$upcontext['overall_percent'] = (int) $upcontext['overall_percent'];

	// We usually dump our templates out.
	if (!$fallThrough)
	{
		// This should not happen my dear... HELP ME DEVELOPERS!!
		if (!empty($command_line))
		{
			if (function_exists('debug_print_backtrace'))
				debug_print_backtrace();

			printf($txt['error_unexpected_template_call'], isset($upcontext['sub_template']) ? $upcontext['sub_template'] : '');
			flush();
			die();
		}

		if (!isset($_GET['xml']))
			template_upgrade_above();
		else
		{
			header('content-type: text/xml; charset=UTF-8');
			// Sadly we need to retain the $_GET data thanks to the old upgrade scripts.
			$upcontext['get_data'] = array();
			foreach ($_GET as $k => $v)
			{
				if (substr($k, 0, 3) != 'amp' && !in_array($k, array('xml', 'substep', 'lang', 'data', 'step', 'filecount')))
				{
					$upcontext['get_data'][$k] = $v;
				}
			}
			template_xml_above();
		}

		// Call the template.
		if (isset($upcontext['sub_template']))
		{
			$upcontext['upgrade_status']['curstep'] = $upcontext['current_step'];
			$upcontext['form_url'] = $upgradeurl . '?step=' . $upcontext['current_step'] . '&amp;substep=' . $_GET['substep'] . '&amp;data=' . base64_encode(json_encode($upcontext['upgrade_status']));

			// Custom stuff to pass back?
			if (!empty($upcontext['query_string']))
				$upcontext['form_url'] .= $upcontext['query_string'];

			// Call the appropriate subtemplate
			if (is_callable('template_' . $upcontext['sub_template']))
				call_user_func('template_' . $upcontext['sub_template']);
			else
				die(sprintf($txt['error_invalid_template'], $upcontext['sub_template']));
		}

		// Was there an error?
		if (!empty($upcontext['forced_error_message']))
			echo $upcontext['forced_error_message'];

		// Show the footer.
		if (!isset($_GET['xml']))
			template_upgrade_below();
		else
			template_xml_below();
	}

	// Show the upgrade time for CLI when we are completely done, if in debug mode.
	if (!empty($command_line) && $is_debug)
	{
		$active = time() - $upcontext['started'];
		$hours = floor($active / 3600);
		$minutes = intval(($active / 60) % 60);
		$seconds = intval($active % 60);

		if ($hours > 0)
			echo "\n" . '', sprintf($txt['upgrade_completed_time_hms'], $hours, $minutes, $seconds), '' . "\n";
		elseif ($minutes > 0)
			echo "\n" . '', sprintf($txt['upgrade_completed_time_ms'], $minutes, $seconds), '' . "\n";
		elseif ($seconds > 0)
			echo "\n" . '', sprintf($txt['upgrade_completed_time_s'], $seconds), '' . "\n";
	}

	// Bang - gone!
	die();
}

// Load the list of language files, and the current language file.
function load_lang_file()
{
	global $txt, $upcontext, $language, $modSettings;

	static $lang_dir = '', $detected_languages = array(), $loaded_langfile = '';

	// Do we know where to look for the language files, or shall we just guess for now?
	$temp = isset($modSettings['theme_dir']) ? $modSettings['theme_dir'] . '/languages' : dirname(__FILE__) . '/Themes/default/languages';

	if ($lang_dir != $temp)
	{
		$lang_dir = $temp;
		$detected_languages = array();
	}

	// Override the language file?
	if (isset($upcontext['language']))
		$_SESSION['upgrader_langfile'] = 'Install.' . $upcontext['language'] . '.php';
	elseif (isset($upcontext['lang']))
		$_SESSION['upgrader_langfile'] = 'Install.' . $upcontext['lang'] . '.php';
	elseif (isset($language))
		$_SESSION['upgrader_langfile'] = 'Install.' . $language . '.php';

	// Avoid pointless repetition
	if (isset($_SESSION['upgrader_langfile']) && $loaded_langfile == $lang_dir . '/' . $_SESSION['upgrader_langfile'])
		return;

	// Now try to find the language files
	if (empty($detected_languages))
	{
		// Make sure the languages directory actually exists.
		if (file_exists($lang_dir))
		{
			// Find all the "Install" language files in the directory.
			$dir = dir($lang_dir);
			while ($entry = $dir->read())
			{
				// Skip any old '-utf8' language files that might be lying around
				if (strpos($entry, '-utf8') !== false)
					continue;

				if (substr($entry, 0, 8) == 'Install.' && substr($entry, -4) == '.php')
					$detected_languages[$entry] = ucfirst(substr($entry, 8, strlen($entry) - 12));
			}
			$dir->close();
		}
		// Our guess was wrong, but that's fine. We'll try again after $modSettings['theme_dir'] is defined.
		elseif (!isset($modSettings['theme_dir']))
		{
			// Define a few essential strings for now.
			$txt['error_db_connect_settings'] = 'Cannot connect to the database server.<br><br>Please check that the database info variables are correct in Settings.php.';
			$txt['error_sourcefile_missing'] = 'Unable to find the Sources/%1$s file. Please make sure it was uploaded properly, and then try again.';

			$txt['warning_lang_old'] = 'The language files for your selected language, %1$s, have not been updated to the latest version. Upgrade will continue with the forum default, %2$s.';
			$txt['warning_lang_missing'] = 'The upgrader could not find the &quot;Install&quot; language file for your selected language, %1$s. Upgrade will continue with the forum default, %2$s.';

			return;
		}
	}

	// Didn't find any, show an error message!
	if (empty($detected_languages))
	{
		$from = explode('/', $_SERVER['PHP_SELF']);
		$to = explode('/', $lang_dir);
		$relPath = $to;

		foreach($from as $depth => $dir)
		{
			if ($dir === $to[$depth])
				array_shift($relPath);
			else
			{
				$remaining = count($from) - $depth;
				if ($remaining > 1)
				{
					$padLength = (count($relPath) + $remaining - 1) * -1;
					$relPath = array_pad($relPath, $padLength, '..');
					break;
				}
				else
					$relPath[0] = './' . $relPath[0];
			}
		}
		$relPath = implode(DIRECTORY_SEPARATOR, $relPath);

		// Let's not cache this message, eh?
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		echo '<!DOCTYPE html>
			<html>
				<head>
					<title>SMF Upgrader: Error!</title>
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
						<p>This upgrader was unable to find the upgrader\'s language file or files.  They should be found under:</p>
						<div class="directory">', $relPath, '</div>
						<p>In some cases, FTP clients do not properly upload files with this many folders. Please double check to make sure you <strong>have uploaded all the files in the distribution</strong>.</p>
						<p>If that doesn\'t help, please make sure this upgrade.php file is in the same place as the Themes folder.</p>
						<p>If you continue to get this error message, feel free to <a href="https://support.simplemachines.org/">look to us for support</a>.</p>
				</body>
			</html>';
		die;
	}

	// Make sure it exists. If it doesn't, reset it.
	if (!isset($_SESSION['upgrader_langfile']) || preg_match('~[^\w.-]~', $_SESSION['upgrader_langfile']) === 1 || !file_exists($lang_dir . '/' . $_SESSION['upgrader_langfile']))
	{
		// Use the first one...
		list ($_SESSION['upgrader_langfile']) = array_keys($detected_languages);

		// If we have English and some other language, use the other language.
		if ($_SESSION['upgrader_langfile'] == 'Install.english.php' && count($detected_languages) > 1)
			list (, $_SESSION['upgrader_langfile']) = array_keys($detected_languages);
	}

	// For backup we load English at first, then the second language will overwrite it.
	if ($_SESSION['upgrader_langfile'] != 'Install.english.php')
		require_once($lang_dir . '/Install.english.php');

	// And now include the actual language file itself.
	require_once($lang_dir . '/' . $_SESSION['upgrader_langfile']);

	// Remember what we've done
	$loaded_langfile = $lang_dir . '/' . $_SESSION['upgrader_langfile'];
}

// Used to direct the user to another location.
function redirectLocation($location, $addForm = true)
{
	global $upgradeurl, $upcontext, $command_line;

	// Command line users can't be redirected.
	if ($command_line)
		upgradeExit(true);

	// Are we providing the core info?
	if ($addForm)
	{
		$upcontext['upgrade_status']['curstep'] = $upcontext['current_step'];
		$location = $upgradeurl . '?step=' . $upcontext['current_step'] . '&substep=' . $_GET['substep'] . '&data=' . base64_encode(json_encode($upcontext['upgrade_status'])) . $location;
	}

	while (@ob_end_clean())
		header('location: ' . strtr($location, array('&amp;' => '&')));

	// Exit - saving status as we go.
	upgradeExit(true);
}

// Load all essential data and connect to the DB as this is pre SSI.php
function loadEssentialData()
{
	global $db_server, $db_user, $db_passwd, $db_name, $db_connection;
	global $db_prefix, $db_character_set, $db_type, $db_port;
	global $db_mb4, $modSettings, $sourcedir, $smcFunc, $txt;

	error_reporting(E_ALL);
	define('SMF', 1);

	// Start the session.
	if (@ini_get('session.save_handler') == 'user')
		@ini_set('session.save_handler', 'files');
	@session_start();

	if (empty($smcFunc))
		$smcFunc = array();

	$smcFunc['random_int'] = function($min = 0, $max = PHP_INT_MAX)
	{
		global $sourcedir;

		// Oh, wouldn't it be great if I *was* crazy? Then the world would be okay.
		if (!is_callable('random_int'))
			require_once($sourcedir . '/random_compat/random.php');

		return random_int($min, $max);
	};

	// We need this for authentication and some upgrade code
	require_once($sourcedir . '/Subs-Auth.php');
	require_once($sourcedir . '/Class-Package.php');

	$smcFunc['strtolower'] = 'smf_strtolower';

	// Initialize everything...
	initialize_inputs();

	// Get the database going!
	if (empty($db_type) || $db_type == 'mysqli')
	{
		$db_type = 'mysql';
		// If overriding $db_type, need to set its settings.php entry too
		$changes = array();
		$changes['db_type'] = '\'mysql\'';
		require_once($sourcedir . '/Subs-Admin.php');
		updateSettingsFile($changes);
	}

	if (file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php'))
	{
		require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');

		// Make the connection...
		if (empty($db_connection))
		{
			$options = array('non_fatal' => true);
			// Add in the port if needed
			if (!empty($db_port))
				$options['port'] = $db_port;

			if (!empty($db_mb4))
				$options['db_mb4'] = $db_mb4;

			$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $options);
		}
		else
			// If we've returned here, ping/reconnect to be safe
			$smcFunc['db_ping']($db_connection);

		// Oh dear god!!
		if ($db_connection === null)
			die($txt['error_db_connect_settings']);

		if ($db_type == 'mysql' && isset($db_character_set) && preg_match('~^\w+$~', $db_character_set) === 1)
			$smcFunc['db_query']('', '
				SET NAMES {string:db_character_set}',
				array(
					'db_error_skip' => true,
					'db_character_set' => $db_character_set,
				)
			);

		// Load the modSettings data...
		$request = $smcFunc['db_query']('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
				'db_error_skip' => true,
			)
		);
		$modSettings = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$modSettings[$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);
	}
	else
		return throw_error(sprintf($txt['error_sourcefile_missing'], 'Subs-Db-' . $db_type . '.php'));

	require_once($sourcedir . '/Subs.php');

	// If they don't have the file, they're going to get a warning anyway so we won't need to clean request vars.
	if (file_exists($sourcedir . '/QueryString.php') && php_version_check())
	{
		require_once($sourcedir . '/QueryString.php');
		cleanRequest();
	}

	if (!isset($_GET['substep']))
		$_GET['substep'] = 0;
}

function initialize_inputs()
{
	global $start_time, $db_type;

	$start_time = time();

	umask(0);

	ob_start();

	// Better to upgrade cleanly and fall apart than to screw everything up if things take too long.
	ignore_user_abort(true);

	// This is really quite simple; if ?delete is on the URL, delete the upgrader...
	if (isset($_GET['delete']))
	{
		@unlink(__FILE__);

		// And the extra little files ;).
		@unlink(dirname(__FILE__) . '/upgrade_1-0.sql');
		@unlink(dirname(__FILE__) . '/upgrade_1-1.sql');
		@unlink(dirname(__FILE__) . '/upgrade_2-0_' . $db_type . '.sql');
		@unlink(dirname(__FILE__) . '/upgrade_2-1_' . $db_type . '.sql');
		@unlink(dirname(__FILE__) . '/upgrade-helper.php');

		$dh = opendir(dirname(__FILE__));
		while ($file = readdir($dh))
		{
			if (preg_match('~upgrade_\d-\d_([A-Za-z])+\.sql~i', $file, $matches) && isset($matches[1]))
				@unlink(dirname(__FILE__) . '/' . $file);
		}
		closedir($dh);

		// Legacy files while we're at it. NOTE: We only touch files we KNOW shouldn't be there.
		// 1.1 Sources files not in 2.0+
		@unlink(dirname(__FILE__) . '/Sources/ModSettings.php');
		// 1.1 Templates that don't exist any more (e.g. renamed)
		@unlink(dirname(__FILE__) . '/Themes/default/Combat.template.php');
		@unlink(dirname(__FILE__) . '/Themes/default/Modlog.template.php');
		// 1.1 JS files were stored in the main theme folder, but in 2.0+ are in the scripts/ folder
		@unlink(dirname(__FILE__) . '/Themes/default/fader.js');
		@unlink(dirname(__FILE__) . '/Themes/default/script.js');
		@unlink(dirname(__FILE__) . '/Themes/default/spellcheck.js');
		@unlink(dirname(__FILE__) . '/Themes/default/xml_board.js');
		@unlink(dirname(__FILE__) . '/Themes/default/xml_topic.js');

		// 2.0 Sources files not in 2.1+
		@unlink(dirname(__FILE__) . '/Sources/DumpDatabase.php');
		@unlink(dirname(__FILE__) . '/Sources/LockTopic.php');

		header('location: http://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/Themes/default/images/blank.png');
		exit;
	}

	// Something is causing this to happen, and it's annoying.  Stop it.
	$temp = 'upgrade_php?step';
	while (strlen($temp) > 4)
	{
		if (isset($_GET[$temp]))
			unset($_GET[$temp]);
		$temp = substr($temp, 1);
	}

	// Force a step, defaulting to 0.
	$_GET['step'] = (int) @$_GET['step'];
	$_GET['substep'] = (int) @$_GET['substep'];
}

// Step 0 - Let's welcome them in and ask them to login!
function WelcomeLogin()
{
	global $boarddir, $sourcedir, $modSettings, $cachedir, $upgradeurl, $upcontext;
	global $smcFunc, $db_type, $databases, $boardurl;

	// We global $txt here so that the language files can add to them. This variable is NOT unused.
	global $txt;

	$upcontext['sub_template'] = 'welcome_message';

	// Check for some key files - one template, one language, and a new and an old source file.
	$check = @file_exists($modSettings['theme_dir'] . '/index.template.php')
		&& @file_exists($sourcedir . '/QueryString.php')
		&& @file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php')
		&& @file_exists(dirname(__FILE__) . '/upgrade_2-1_' . $db_type . '.sql');

	// Need legacy scripts?
	if (!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] < 2.1)
		$check &= @file_exists(dirname(__FILE__) . '/upgrade_2-0_' . $db_type . '.sql');
	if (!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] < 2.0)
		$check &= @file_exists(dirname(__FILE__) . '/upgrade_1-1.sql');
	if (!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] < 1.1)
		$check &= @file_exists(dirname(__FILE__) . '/upgrade_1-0.sql');

	// We don't need "-utf8" files anymore...
	$upcontext['language'] = str_ireplace('-utf8', '', $upcontext['language']);

	if (!$check)
		// Don't tell them what files exactly because it's a spot check - just like teachers don't tell which problems they are spot checking, that's dumb.
		return throw_error($txt['error_upgrade_files_missing']);

	// Do they meet the install requirements?
	if (!php_version_check())
		return throw_error($txt['error_php_too_low']);

	if (!db_version_check())
		return throw_error(sprintf($txt['error_db_too_low'], $databases[$db_type]['name']));

	// Do some checks to make sure they have proper privileges
	db_extend('packages');

	// CREATE
	$create = $smcFunc['db_create_table']('{db_prefix}priv_check', array(array('name' => 'id_test', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true)), array(array('columns' => array('id_test'), 'type' => 'primary')), array(), 'overwrite');

	// ALTER
	$alter = $smcFunc['db_add_column']('{db_prefix}priv_check', array('name' => 'txt', 'type' => 'varchar', 'size' => 4, 'null' => false, 'default' => ''));

	// DROP
	$drop = $smcFunc['db_drop_table']('{db_prefix}priv_check');

	// Sorry... we need CREATE, ALTER and DROP
	if (!$create || !$alter || !$drop)
		return throw_error(sprintf($txt['error_db_privileges'], $databases[$db_type]['name']));

	// Do a quick version spot check.
	$temp = substr(@implode('', @file($boarddir . '/index.php')), 0, 4096);
	preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $temp, $match);
	if (empty($match[1]) || (trim($match[1]) != SMF_VERSION))
		return throw_error($txt['error_upgrade_old_files']);

	// What absolutely needs to be writable?
	$writable_files = array(
		$boarddir . '/Settings.php',
		$boarddir . '/Settings_bak.php',
	);

	// Only check for minified writable files if we have it enabled or not set.
	if (!empty($modSettings['minimize_files']) || !isset($modSettings['minimize_files']))
		$writable_files += array(
			$modSettings['theme_dir'] . '/css/minified.css',
			$modSettings['theme_dir'] . '/scripts/minified.js',
			$modSettings['theme_dir'] . '/scripts/minified_deferred.js',
		);

	// Do we need to add this setting?
	$need_settings_update = empty($modSettings['custom_avatar_dir']);

	$custom_av_dir = !empty($modSettings['custom_avatar_dir']) ? $modSettings['custom_avatar_dir'] : $GLOBALS['boarddir'] . '/custom_avatar';
	$custom_av_url = !empty($modSettings['custom_avatar_url']) ? $modSettings['custom_avatar_url'] : $boardurl . '/custom_avatar';

	// This little fellow has to cooperate...
	quickFileWritable($custom_av_dir);

	// Are we good now?
	if (!is_writable($custom_av_dir))
		return throw_error(sprintf($txt['error_dir_not_writable'], $custom_av_dir));
	elseif ($need_settings_update)
	{
		if (!function_exists('cache_put_data'))
			require_once($sourcedir . '/Load.php');

		updateSettings(array('custom_avatar_dir' => $custom_av_dir));
		updateSettings(array('custom_avatar_url' => $custom_av_url));
	}

	require_once($sourcedir . '/Security.php');

	// Check the cache directory.
	$cachedir_temp = empty($cachedir) ? $boarddir . '/cache' : $cachedir;
	if (!file_exists($cachedir_temp))
		@mkdir($cachedir_temp);

	if (!file_exists($cachedir_temp))
		return throw_error($txt['error_cache_not_found']);

	if (!file_exists($modSettings['theme_dir'] . '/languages/index.' . $upcontext['language'] . '.php'))
		return throw_error(sprintf($txt['error_lang_index_missing'], $upcontext['language'], $upgradeurl));
	elseif (!isset($_GET['skiplang']))
	{
		$temp = substr(@implode('', @file($modSettings['theme_dir'] . '/languages/index.' . $upcontext['language'] . '.php')), 0, 4096);
		preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*index(?:[\s]{2}|\*/)~i', $temp, $match);

		if (empty($match[1]) || $match[1] != SMF_LANG_VERSION)
			return throw_error(sprintf($txt['error_upgrade_old_lang_files'], $upcontext['language'], $upgradeurl));
	}

	if (!makeFilesWritable($writable_files))
		return false;

	// Check agreement.txt. (it may not exist, in which case $boarddir must be writable.)
	if (isset($modSettings['agreement']) && (!is_writable($boarddir) || file_exists($boarddir . '/agreement.txt')) && !is_writable($boarddir . '/agreement.txt'))
		return throw_error($txt['error_agreement_not_writable']);

	// Upgrade the agreement.
	elseif (isset($modSettings['agreement']))
	{
		$fp = fopen($boarddir . '/agreement.txt', 'w');
		fwrite($fp, $modSettings['agreement']);
		fclose($fp);
	}

	// We're going to check that their board dir setting is right in case they've been moving stuff around.
	if (strtr($boarddir, array('/' => '', '\\' => '')) != strtr(dirname(__FILE__), array('/' => '', '\\' => '')))
		$upcontext['warning'] = '
			' . sprintf($txt['upgrade_boarddir_settings'], $boarddir, dirname(__FILE__)) . '<br>
			<ul>
				<li>' . $txt['upgrade_boarddir'] . '  ' . $boarddir . '</li>
				<li>' . $txt['upgrade_sourcedir'] . '  ' . $boarddir . '</li>
				<li>' . $txt['upgrade_cachedir'] . '  ' . $cachedir_temp . '</li>
			</ul>
			' . $txt['upgrade_incorrect_settings'] . '';

	// Confirm mbstring is loaded...
	if (!extension_loaded('mbstring'))
		return throw_error($txt['install_no_mbstring']);

	// Check for https stream support.
	$supported_streams = stream_get_wrappers();
	if (!in_array('https', $supported_streams))
		$upcontext['custom_warning'] = $txt['install_no_https'];

	// Either we're logged in or we're going to present the login.
	if (checkLogin())
		return true;

	$upcontext += createToken('login');

	return false;
}

// Step 0.5: Does the login work?
function checkLogin()
{
	global $modSettings, $upcontext, $disable_security;
	global $smcFunc, $db_type, $support_js, $sourcedir, $txt;

	// Don't bother if the security is disabled.
	if ($disable_security)
		return true;

	// Are we trying to login?
	if (isset($_POST['contbutt']) && (!empty($_POST['user'])))
	{
		// If we've disabled security pick a suitable name!
		if (empty($_POST['user']))
			$_POST['user'] = 'Administrator';

		// Before 2.0 these column names were different!
		$oldDB = false;
		if (empty($db_type) || $db_type == 'mysql')
		{
			$request = $smcFunc['db_query']('', '
				SHOW COLUMNS
				FROM {db_prefix}members
				LIKE {string:member_name}',
				array(
					'member_name' => 'memberName',
					'db_error_skip' => true,
				)
			);
			if ($smcFunc['db_num_rows']($request) != 0)
				$oldDB = true;
			$smcFunc['db_free_result']($request);
		}

		// Get what we believe to be their details.
		if (!$disable_security)
		{
			if ($oldDB)
				$request = $smcFunc['db_query']('', '
					SELECT id_member, memberName AS member_name, passwd, id_group,
						additionalGroups AS additional_groups, lngfile
					FROM {db_prefix}members
					WHERE memberName = {string:member_name}',
					array(
						'member_name' => $_POST['user'],
						'db_error_skip' => true,
					)
				);
			else
				$request = $smcFunc['db_query']('', '
					SELECT id_member, member_name, passwd, id_group, additional_groups, lngfile
					FROM {db_prefix}members
					WHERE member_name = {string:member_name}',
					array(
						'member_name' => $_POST['user'],
						'db_error_skip' => true,
					)
				);
			if ($smcFunc['db_num_rows']($request) != 0)
			{
				list ($id_member, $name, $password, $id_group, $addGroups, $user_language) = $smcFunc['db_fetch_row']($request);

				$groups = explode(',', $addGroups);
				$groups[] = $id_group;

				foreach ($groups as $k => $v)
					$groups[$k] = (int) $v;

				$sha_passwd = sha1(strtolower($name) . un_htmlspecialchars($_REQUEST['passwrd']));

				// We don't use "-utf8" anymore...
				$user_language = str_ireplace('-utf8', '', $user_language);
			}
			else
				$upcontext['username_incorrect'] = true;

			$smcFunc['db_free_result']($request);
		}
		$upcontext['username'] = $_POST['user'];

		// Track whether javascript works!
		if (!empty($_POST['js_works']))
		{
			$upcontext['upgrade_status']['js'] = 1;
			$support_js = 1;
		}
		else
			$support_js = 0;

		// Note down the version we are coming from.
		if (!empty($modSettings['smfVersion']) && empty($upcontext['user']['version']))
			$upcontext['user']['version'] = $modSettings['smfVersion'];

		// Didn't get anywhere?
		if (!$disable_security && (empty($sha_passwd) || (!empty($password) ? $password : '') != $sha_passwd) && !hash_verify_password((!empty($name) ? $name : ''), $_REQUEST['passwrd'], (!empty($password) ? $password : '')) && empty($upcontext['username_incorrect']))
		{
			// MD5?
			$md5pass = md5_hmac($_REQUEST['passwrd'], strtolower($_POST['user']));
			if ($md5pass != $password)
			{
				$upcontext['password_failed'] = true;
				// Disable the hashing this time.
				$upcontext['disable_login_hashing'] = true;
			}
		}

		if ((empty($upcontext['password_failed']) && !empty($name)) || $disable_security)
		{
			// Set the password.
			if (!$disable_security)
			{
				// Do we actually have permission?
				if (!in_array(1, $groups))
				{
					$request = $smcFunc['db_query']('', '
						SELECT permission
						FROM {db_prefix}permissions
						WHERE id_group IN ({array_int:groups})
							AND permission = {string:admin_forum}',
						array(
							'groups' => $groups,
							'admin_forum' => 'admin_forum',
							'db_error_skip' => true,
						)
					);
					if ($smcFunc['db_num_rows']($request) == 0)
						return throw_error($txt['error_not_admin']);
					$smcFunc['db_free_result']($request);
				}

				$upcontext['user']['id'] = $id_member;
				$upcontext['user']['name'] = $name;
			}
			else
			{
				$upcontext['user']['id'] = 1;
				$upcontext['user']['name'] = 'Administrator';
			}

			if (!is_callable('random_int'))
				require_once('Sources/random_compat/random.php');

			$upcontext['user']['pass'] = random_int(0, 60000);
			// This basically is used to match the GET variables to Settings.php.
			$upcontext['upgrade_status']['pass'] = $upcontext['user']['pass'];

			// Set the language to that of the user?
			if (isset($user_language) && $user_language != $upcontext['language'] && file_exists($modSettings['theme_dir'] . '/languages/index.' . basename($user_language, '.lng') . '.php'))
			{
				$user_language = basename($user_language, '.lng');
				$temp = substr(@implode('', @file($modSettings['theme_dir'] . '/languages/index.' . $user_language . '.php')), 0, 4096);
				preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*index(?:[\s]{2}|\*/)~i', $temp, $match);

				if (empty($match[1]) || $match[1] != SMF_LANG_VERSION)
					$upcontext['upgrade_options_warning'] = sprintf($txt['warning_lang_old'], $user_language, $upcontext['language']);
				elseif (!file_exists($modSettings['theme_dir'] . '/languages/Install.' . $user_language . '.php'))
					$upcontext['upgrade_options_warning'] = sprintf($txt['warning_lang_missing'], $user_language, $upcontext['language']);
				else
				{
					// Set this as the new language.
					$upcontext['language'] = $user_language;
					$upcontext['upgrade_status']['lang'] = $upcontext['language'];

					// Include the file.
					load_lang_file();
				}
			}

			// If we're resuming set the step and substep to be correct.
			if (isset($_POST['cont']))
			{
				$upcontext['current_step'] = $upcontext['user']['step'];
				$_GET['substep'] = $upcontext['user']['substep'];
			}

			return true;
		}
	}

	return false;
}

// Step 1: Do the maintenance and backup.
function UpgradeOptions()
{
	global $db_prefix, $command_line, $modSettings, $is_debug, $smcFunc, $packagesdir, $tasksdir, $language, $txt, $db_port;
	global $boarddir, $boardurl, $sourcedir, $maintenance, $cachedir, $upcontext, $db_type, $db_server, $image_proxy_enabled;

	$upcontext['sub_template'] = 'upgrade_options';
	$upcontext['page_title'] = $txt['upgrade_options'];

	db_extend('packages');
	$upcontext['karma_installed'] = array('good' => false, 'bad' => false);
	$member_columns = $smcFunc['db_list_columns']('{db_prefix}members');

	$upcontext['karma_installed']['good'] = in_array('karma_good', $member_columns);
	$upcontext['karma_installed']['bad'] = in_array('karma_bad', $member_columns);

	unset($member_columns);

	// If these options are missing, we may need to migrate to a new Settings.php
	$upcontext['migrateSettingsNeeded'] = detectSettingsFileMigrationNeeded();

	// If we've not submitted then we're done.
	if (empty($_POST['upcont']))
		return false;

	// Firstly, if they're enabling SM stat collection just do it.
	if (!empty($_POST['stats']) && substr($boardurl, 0, 16) != 'http://localhost' && empty($modSettings['allow_sm_stats']) && empty($modSettings['enable_sm_stats']))
	{
		$upcontext['allow_sm_stats'] = true;

		// Don't register if we still have a key.
		if (empty($modSettings['sm_stats_key']))
		{
			// Attempt to register the site etc.
			$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
			if ($fp)
			{
				$out = 'GET /smf/stats/register_stats.php?site=' . base64_encode($boardurl) . ' HTTP/1.1' . "\r\n";
				$out .= 'Host: www.simplemachines.org' . "\r\n";
				$out .= 'Connection: Close' . "\r\n\r\n";
				fwrite($fp, $out);

				$return_data = '';
				while (!feof($fp))
					$return_data .= fgets($fp, 128);

				fclose($fp);

				// Get the unique site ID.
				preg_match('~SITE-ID:\s(\w{10})~', $return_data, $ID);

				if (!empty($ID[1]))
					$smcFunc['db_insert']('replace',
						$db_prefix . 'settings',
						array('variable' => 'string', 'value' => 'string'),
						array(
							array('sm_stats_key', $ID[1]),
							array('enable_sm_stats', 1),
						),
						array('variable')
					);
			}
		}
		else
		{
			$smcFunc['db_insert']('replace',
				$db_prefix . 'settings',
				array('variable' => 'string', 'value' => 'string'),
				array('enable_sm_stats', 1),
				array('variable')
			);
		}
	}
	// Don't remove stat collection unless we unchecked the box for real, not from the loop.
	elseif (empty($_POST['stats']) && empty($upcontext['allow_sm_stats']))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable = {string:enable_sm_stats}',
			array(
				'enable_sm_stats' => 'enable_sm_stats',
				'db_error_skip' => true,
			)
		);

	// Deleting old karma stuff?
	if (!empty($_POST['delete_karma']))
	{
		// Delete old settings vars.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable IN ({array_string:karma_vars})',
			array(
				'karma_vars' => array('karmaMode', 'karmaTimeRestrictAdmins', 'karmaWaitTime', 'karmaMinPosts', 'karmaLabel', 'karmaSmiteLabel', 'karmaApplaudLabel'),
			)
		);

		// Cleaning up old karma member settings.
		if ($upcontext['karma_installed']['good'])
			$smcFunc['db_query']('', '
				ALTER TABLE {db_prefix}members
				DROP karma_good',
				array()
			);

		// Does karma bad was enable?
		if ($upcontext['karma_installed']['bad'])
			$smcFunc['db_query']('', '
				ALTER TABLE {db_prefix}members
				DROP karma_bad',
				array()
			);

		// Cleaning up old karma permissions.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}permissions
			WHERE permission = {string:karma_vars}',
			array(
				'karma_vars' => 'karma_edit',
			)
		);
		// Cleaning up old log_karma table
		$smcFunc['db_query']('', '
			DROP TABLE IF EXISTS {db_prefix}log_karma',
			array()
		);
	}

	// Emptying the error log?
	if (!empty($_POST['empty_error']))
		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_errors',
			array(
			)
		);

	$changes = array();

	// Add proxy settings.
	if (!isset($GLOBALS['image_proxy_maxsize']))
		$changes += array(
			'image_proxy_secret' => '\'' . substr(sha1(mt_rand()), 0, 20) . '\'',
			'image_proxy_maxsize' => 5190,
			'image_proxy_enabled' => 0,
		);

	// If $boardurl reflects https, set force_ssl
	if (!function_exists('cache_put_data'))
		require_once($sourcedir . '/Load.php');
	if (stripos($boardurl, 'https://') !== false)
		updateSettings(array('force_ssl' => '1'));

	// If we're overriding the language follow it through.
	if (isset($upcontext['lang']) && file_exists($modSettings['theme_dir'] . '/languages/index.' . $upcontext['lang'] . '.php'))
		$changes['language'] = '\'' . $upcontext['lang'] . '\'';

	if (!empty($_POST['maint']))
	{
		$changes['maintenance'] = '2';
		// Remember what it was...
		$upcontext['user']['main'] = $maintenance;

		if (!empty($_POST['maintitle']))
		{
			$changes['mtitle'] = '\'' . addslashes($_POST['maintitle']) . '\'';
			$changes['mmessage'] = '\'' . addslashes($_POST['mainmessage']) . '\'';
		}
		else
		{
			$changes['mtitle'] = '\'' . addslashes($txt['mtitle']) . '\'';
			$changes['mmessage'] = '\'' . addslashes($txt['mmessage']) . '\'';
		}
	}

	if ($command_line)
		echo ' * Updating Settings.php...';

	// Fix some old paths.
	if (substr($boarddir, 0, 1) == '.')
		$changes['boarddir'] = '\'' . fixRelativePath($boarddir) . '\'';

	if (substr($sourcedir, 0, 1) == '.')
		$changes['sourcedir'] = '\'' . fixRelativePath($sourcedir) . '\'';

	if (empty($cachedir) || substr($cachedir, 0, 1) == '.')
		$changes['cachedir'] = '\'' . fixRelativePath($boarddir) . '/cache\'';

	// Migrate cache settings.
	// Accelerator setting didn't exist previously; use 'smf' file based caching as default if caching had been enabled.
	if (!isset($GLOBALS['cache_enable']))
		$changes += array(
			'cache_accelerator' => !empty($modSettings['cache_enable']) ? '\'smf\'' : '\'\'',
			'cache_enable' => !empty($modSettings['cache_enable']) ? $modSettings['cache_enable'] : 0,
			'cache_memcached' => !empty($modSettings['cache_memcached']) ? '\'' . $modSettings['cache_memcached'] . '\'' : '\'\'',
		);

	// If they have a "host:port" setup for the host, split that into separate values
	// You should never have a : in the hostname if you're not on MySQL, but better safe than sorry
	if (strpos($db_server, ':') !== false && $db_type == 'mysql')
	{
		list ($db_server, $db_port) = explode(':', $db_server);

		$changes['db_server'] = '\'' . $db_server . '\'';

		// Only set this if we're not using the default port
		if ($db_port != ini_get('mysqli.default_port'))
			$changes['db_port'] = (int) $db_port;
	}

	// If db_port is set and is the same as the default, set it to 0.
	if (!empty($db_port))
	{
		if ($db_type == 'mysql' && $db_port == ini_get('mysqli.default_port'))
			$changes['db_port'] = 0;
		elseif ($db_type == 'postgresql' && $db_port == 5432)
			$changes['db_port'] = 0;
	}

	// Maybe we haven't had this option yet?
	if (empty($packagesdir))
		$changes['packagesdir'] = '\'' . fixRelativePath($boarddir) . '/Packages\'';

	// Add support for $tasksdir var.
	if (empty($tasksdir))
		$changes['tasksdir'] = '\'' . fixRelativePath($sourcedir) . '/tasks\'';

	// Make sure we fix the language as well.
	if (stristr($language, '-utf8'))
		$changes['language'] = '\'' . str_ireplace('-utf8', '', $language) . '\'';

	// @todo Maybe change the cookie name if going to 1.1, too?

	// If we are migrating the settings, get them ready.
	if (!empty($_POST['migrateSettings']))
	{
		// Ensure this doesn't get lost in translation.
		$changes['upgradeData'] = '"' . base64_encode(json_encode($upcontext['user'])) . '"';

		migrateSettingsFile($changes);
	}
	else
	{
		// Update Settings.php with the new settings.
		require_once($sourcedir . '/Subs-Admin.php');
		updateSettingsFile($changes);

		// Tell Settings.php to store db_last_error.php in the cache
		move_db_last_error_to_cachedir();
	}

	if ($command_line)
		echo ' Successful.' . "\n";

	// Are we doing debug?
	if (isset($_POST['debug']))
	{
		$upcontext['upgrade_status']['debug'] = true;
		$is_debug = true;
	}

	// If we're not backing up then jump one.
	if (empty($_POST['backup']))
		$upcontext['current_step']++;

	// If we've got here then let's proceed to the next step!
	return true;
}

// Backup the database - why not...
function BackupDatabase()
{
	global $upcontext, $db_prefix, $command_line, $support_js, $file_steps, $smcFunc, $txt;

	$upcontext['sub_template'] = isset($_GET['xml']) ? 'backup_xml' : 'backup_database';
	$upcontext['page_title'] = $txt['backup_database'];

	// Done it already - js wise?
	if (!empty($_POST['backup_done']))
		return true;

	// Some useful stuff here.
	db_extend();

	// Might need this as well
	db_extend('packages');

	// Get all the table names.
	$filter = str_replace('_', '\_', preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) != 0 ? $match[2] : $db_prefix) . '%';
	$db = preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) != 0 ? strtr($match[1], array('`' => '')) : false;
	$tables = $smcFunc['db_list_tables']($db, $filter);

	$table_names = array();
	foreach ($tables as $table)
		if (substr($table, 0, 7) !== 'backup_')
			$table_names[] = $table;

	$upcontext['table_count'] = count($table_names);
	$upcontext['cur_table_num'] = $_GET['substep'];
	$upcontext['cur_table_name'] = str_replace($db_prefix, '', isset($table_names[$_GET['substep']]) ? $table_names[$_GET['substep']] : $table_names[0]);
	$upcontext['step_progress'] = (int) (($upcontext['cur_table_num'] / $upcontext['table_count']) * 100);
	// For non-java auto submit...
	$file_steps = $upcontext['table_count'];

	// What ones have we already done?
	foreach ($table_names as $id => $table)
		if ($id < $_GET['substep'])
			$upcontext['previous_tables'][] = $table;

	if ($command_line)
		echo 'Backing Up Tables.';

	// If we don't support javascript we backup here.
	if (!$support_js || isset($_GET['xml']))
	{
		// Backup each table!
		for ($substep = $_GET['substep'], $n = count($table_names); $substep < $n; $substep++)
		{
			$upcontext['cur_table_name'] = str_replace($db_prefix, '', (isset($table_names[$substep + 1]) ? $table_names[$substep + 1] : $table_names[$substep]));
			$upcontext['cur_table_num'] = $substep + 1;

			$upcontext['step_progress'] = (int) (($upcontext['cur_table_num'] / $upcontext['table_count']) * 100);

			// Do we need to pause?
			nextSubstep($substep);

			backupTable($table_names[$substep]);

			// If this is XML to keep it nice for the user do one table at a time anyway!
			if (isset($_GET['xml']))
				return upgradeExit();
		}

		if ($command_line)
		{
			echo "\n" . ' Successful.\'' . "\n";
			flush();
		}
		$upcontext['step_progress'] = 100;

		$_GET['substep'] = 0;
		// Make sure we move on!
		return true;
	}

	// Either way next place to post will be database changes!
	$_GET['substep'] = 0;
	return false;
}

// Backup one table...
function backupTable($table)
{
	global $command_line, $db_prefix, $smcFunc;

	if ($command_line)
	{
		echo "\n" . ' +++ Backing up \"' . str_replace($db_prefix, '', $table) . '"...';
		flush();
	}

	$smcFunc['db_backup_table']($table, 'backup_' . $table);

	if ($command_line)
		echo ' done.';
}

// Step 2: Everything.
function DatabaseChanges()
{
	global $db_prefix, $modSettings, $smcFunc, $txt;
	global $upcontext, $support_js, $db_type;

	// Have we just completed this?
	if (!empty($_POST['database_done']))
		return true;

	$upcontext['sub_template'] = isset($_GET['xml']) ? 'database_xml' : 'database_changes';
	$upcontext['page_title'] = $txt['database_changes'];

	// All possible files.
	// Name, < version, insert_on_complete
	$files = array(
		array('upgrade_1-0.sql', '1.1', '1.1 RC0'),
		array('upgrade_1-1.sql', '2.0', '2.0 a'),
		array('upgrade_2-0_' . $db_type . '.sql', '2.1', '2.1 dev0'),
		array('upgrade_2-1_' . $db_type . '.sql', '3.0', SMF_VERSION),
	);

	// How many files are there in total?
	if (isset($_GET['filecount']))
		$upcontext['file_count'] = (int) $_GET['filecount'];
	else
	{
		$upcontext['file_count'] = 0;
		foreach ($files as $file)
		{
			if (!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] < $file[1])
				$upcontext['file_count']++;
		}
	}

	// Do each file!
	$did_not_do = count($files) - $upcontext['file_count'];
	$upcontext['step_progress'] = 0;
	$upcontext['cur_file_num'] = 0;
	foreach ($files as $file)
	{
		if ($did_not_do)
			$did_not_do--;
		else
		{
			$upcontext['cur_file_num']++;
			$upcontext['cur_file_name'] = $file[0];
			// Do we actually need to do this still?
			if (!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] < $file[1])
			{
				$nextFile = parse_sql(dirname(__FILE__) . '/' . $file[0]);
				if ($nextFile)
				{
					// Only update the version of this if complete.
					$smcFunc['db_insert']('replace',
						$db_prefix . 'settings',
						array('variable' => 'string', 'value' => 'string'),
						array('smfVersion', $file[2]),
						array('variable')
					);

					$modSettings['smfVersion'] = $file[2];
				}

				// If this is XML we only do this stuff once.
				if (isset($_GET['xml']))
				{
					// Flag to move on to the next.
					$upcontext['completed_step'] = true;
					// Did we complete the whole file?
					if ($nextFile)
						$upcontext['current_debug_item_num'] = -1;
					return upgradeExit();
				}
				elseif ($support_js)
					break;
			}
			// Set the progress bar to be right as if we had - even if we hadn't...
			$upcontext['step_progress'] = ($upcontext['cur_file_num'] / $upcontext['file_count']) * 100;
		}
	}

	$_GET['substep'] = 0;
	// So the template knows we're done.
	if (!$support_js)
	{
		$upcontext['changes_complete'] = true;

		return true;
	}
	return false;
}

// Delete the damn thing!
function DeleteUpgrade()
{
	global $command_line, $language, $upcontext, $sourcedir;
	global $user_info, $maintenance, $smcFunc, $db_type, $txt, $settings;

	// Now it's nice to have some of the basic SMF source files.
	if (!isset($_GET['ssi']) && !$command_line)
		redirectLocation('&ssi=1');

	$upcontext['sub_template'] = 'upgrade_complete';
	$upcontext['page_title'] = $txt['upgrade_complete'];

	$endl = $command_line ? "\n" : '<br>' . "\n";

	$changes = array(
		'language' => '\'' . (substr($language, -4) == '.lng' ? substr($language, 0, -4) : $language) . '\'',
		'db_error_send' => '1',
		'upgradeData' => '\'\'',
	);

	// Are we in maintenance mode?
	if (isset($upcontext['user']['main']))
	{
		if ($command_line)
			echo ' * ';
		$upcontext['removed_maintenance'] = true;
		$changes['maintenance'] = $upcontext['user']['main'];
	}
	// Otherwise if somehow we are in 2 let's go to 1.
	elseif (!empty($maintenance) && $maintenance == 2)
		$changes['maintenance'] = 1;

	// Wipe this out...
	$upcontext['user'] = array();

	require_once($sourcedir . '/Subs-Admin.php');
	updateSettingsFile($changes);

	// Clean any old cache files away.
	upgrade_clean_cache();

	// Can we delete the file?
	$upcontext['can_delete_script'] = is_writable(dirname(__FILE__)) || is_writable(__FILE__);

	// Now is the perfect time to fetch the SM files.
	if ($command_line)
		cli_scheduled_fetchSMfiles();
	else
	{
		require_once($sourcedir . '/ScheduledTasks.php');
		scheduled_fetchSMfiles(); // Now go get those files!
		// This is needed in case someone invokes the upgrader using https when upgrading an http forum
		if (httpsOn())
			$settings['default_theme_url'] = strtr($settings['default_theme_url'], array('http://' => 'https://'));
	}

	// Log what we've done.
	if (empty($user_info['id']))
		$user_info['id'] = !empty($upcontext['user']['id']) ? $upcontext['user']['id'] : 0;

	// Log the action manually, so CLI still works.
	$smcFunc['db_insert']('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'inet', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		array(
			time(), 3, $user_info['id'], $command_line ? '127.0.0.1' : $user_info['ip'], 'upgrade',
			0, 0, 0, json_encode(array('version' => SMF_FULL_VERSION, 'member' => $user_info['id'])),
		),
		array('id_action')
	);
	$user_info['id'] = 0;

	if ($command_line)
	{
		echo $endl;
		echo 'Upgrade Complete!', $endl;
		echo 'Please delete this file as soon as possible for security reasons.', $endl;
		exit;
	}

	// Make sure it says we're done.
	$upcontext['overall_percent'] = 100;
	if (isset($upcontext['step_progress']))
		unset($upcontext['step_progress']);

	$_GET['substep'] = 0;
	return false;
}

// Just like the built in one, but setup for CLI to not use themes.
function cli_scheduled_fetchSMfiles()
{
	global $sourcedir, $language, $modSettings, $smcFunc;

	if (empty($modSettings['time_format']))
		$modSettings['time_format'] = '%B %d, %Y, %I:%M:%S %p';

	// What files do we want to get
	$request = $smcFunc['db_query']('', '
		SELECT id_file, filename, path, parameters
		FROM {db_prefix}admin_info_files',
		array(
		)
	);

	$js_files = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$js_files[$row['id_file']] = array(
			'filename' => $row['filename'],
			'path' => $row['path'],
			'parameters' => sprintf($row['parameters'], $language, urlencode($modSettings['time_format']), urlencode(SMF_FULL_VERSION)),
		);
	}
	$smcFunc['db_free_result']($request);

	// We're gonna need fetch_web_data() to pull this off.
	require_once($sourcedir . '/Subs.php');

	foreach ($js_files as $ID_FILE => $file)
	{
		// Create the url
		$server = empty($file['path']) || substr($file['path'], 0, 7) != 'http://' ? 'https://www.simplemachines.org' : '';
		$url = $server . (!empty($file['path']) ? $file['path'] : $file['path']) . $file['filename'] . (!empty($file['parameters']) ? '?' . $file['parameters'] : '');

		// Get the file
		$file_data = fetch_web_data($url);

		// If we got an error - give up - the site might be down.
		if ($file_data === false)
			return throw_error(sprintf('Could not retrieve the file %1$s.', $url));

		// Save the file to the database.
		$smcFunc['db_query']('substring', '
			UPDATE {db_prefix}admin_info_files
			SET data = SUBSTRING({string:file_data}, 1, 65534)
			WHERE id_file = {int:id_file}',
			array(
				'id_file' => $ID_FILE,
				'file_data' => $file_data,
			)
		);
	}
	return true;
}

function convertSettingsToTheme()
{
	global $db_prefix, $modSettings, $smcFunc;

	$values = array(
		'show_latest_member' => @$GLOBALS['showlatestmember'],
		'show_bbc' => isset($GLOBALS['showyabbcbutt']) ? $GLOBALS['showyabbcbutt'] : @$GLOBALS['showbbcbutt'],
		'show_modify' => @$GLOBALS['showmodify'],
		'show_user_images' => @$GLOBALS['showuserpic'],
		'show_blurb' => @$GLOBALS['showusertext'],
		'show_gender' => @$GLOBALS['showgenderimage'],
		'show_newsfader' => @$GLOBALS['shownewsfader'],
		'display_recent_bar' => @$GLOBALS['Show_RecentBar'],
		'show_member_bar' => @$GLOBALS['Show_MemberBar'],
		'linktree_link' => @$GLOBALS['curposlinks'],
		'show_profile_buttons' => @$GLOBALS['profilebutton'],
		'show_mark_read' => @$GLOBALS['showmarkread'],
		'newsfader_time' => @$GLOBALS['fadertime'],
		'use_image_buttons' => empty($GLOBALS['MenuType']) ? 1 : 0,
		'enable_news' => @$GLOBALS['enable_news'],
		'return_to_post' => @$modSettings['returnToPost'],
	);

	$themeData = array();
	foreach ($values as $variable => $value)
	{
		if (!isset($value) || $value === null)
			$value = 0;

		$themeData[] = array(0, 1, $variable, $value);
	}
	if (!empty($themeData))
	{
		$smcFunc['db_insert']('ignore',
			$db_prefix . 'themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			$themeData,
			array('id_member', 'id_theme', 'variable')
		);
	}
}

// This function only works with MySQL but that's fine as it is only used for v1.0.
function convertSettingstoOptions()
{
	global $modSettings, $smcFunc;

	// Format: new_setting -> old_setting_name.
	$values = array(
		'calendar_start_day' => 'cal_startmonday',
		'view_newest_first' => 'viewNewestFirst',
		'view_newest_pm_first' => 'viewNewestFirst',
	);

	foreach ($values as $variable => $value)
	{
		if (empty($modSettings[$value[0]]))
			continue;

		$smcFunc['db_query']('', '
			INSERT IGNORE INTO {db_prefix}themes
				(id_member, id_theme, variable, value)
			SELECT id_member, 1, {string:variable}, {string:value}
			FROM {db_prefix}members',
			array(
				'variable' => $variable,
				'value' => $modSettings[$value[0]],
				'db_error_skip' => true,
			)
		);

		$smcFunc['db_query']('', '
			INSERT IGNORE INTO {db_prefix}themes
				(id_member, id_theme, variable, value)
			VALUES (-1, 1, {string:variable}, {string:value})',
			array(
				'variable' => $variable,
				'value' => $modSettings[$value[0]],
				'db_error_skip' => true,
			)
		);
	}
}

function php_version_check()
{
	return version_compare(PHP_VERSION, $GLOBALS['required_php_version'], '>=');
}

function db_version_check()
{
	global $db_type, $databases;

	$curver = eval($databases[$db_type]['version_check']);
	$curver = preg_replace('~\-.+?$~', '', $curver);

	return version_compare($databases[$db_type]['version'], $curver, '<=');
}

function fixRelativePath($path)
{
	global $install_path;

	// Fix the . at the start, clear any duplicate slashes, and fix any trailing slash...
	return addslashes(preg_replace(array('~^\.([/\\\]|$)~', '~[/]+~', '~[\\\]+~', '~[/\\\]$~'), array($install_path . '$1', '/', '\\', ''), $path));
}

function parse_sql($filename)
{
	global $db_prefix, $db_collation, $boarddir, $boardurl, $command_line, $file_steps, $step_progress, $custom_warning;
	global $upcontext, $support_js, $is_debug, $db_type, $db_character_set;

/*
	Failure allowed on:
		- INSERT INTO but not INSERT IGNORE INTO.
		- UPDATE IGNORE but not UPDATE.
		- ALTER TABLE and ALTER IGNORE TABLE.
		- DROP TABLE.
	Yes, I realize that this is a bit confusing... maybe it should be done differently?

	If a comment...
		- begins with --- it is to be output, with a break only in debug mode. (and say successful\n\n if there was one before.)
		- begins with ---# it is a debugging statement, no break - only shown at all in debug.
		- is only ---#, it is "done." and then a break - only shown in debug.
		- begins with ---{ it is a code block terminating at ---}.

	Every block of between "--- ..."s is a step.  Every "---#" section represents a substep.

	Replaces the following variables:
		- {$boarddir}
		- {$boardurl}
		- {$db_prefix}
		- {$db_collation}
*/

	// May want to use extended functionality.
	db_extend();
	db_extend('packages');

	// Our custom error handler - does nothing but does stop public errors from XML!
	set_error_handler(
		function($errno, $errstr, $errfile, $errline) use ($support_js)
		{
			if ($support_js)
				return true;
			else
				echo 'Error: ' . $errstr . ' File: ' . $errfile . ' Line: ' . $errline;
		}
	);

	// If we're on MySQL, set {db_collation}; this approach is used throughout upgrade_2-0_mysql.php to set new tables to utf8
	// Note it is expected to be in the format: ENGINE=MyISAM{$db_collation};
	if ($db_type == 'mysql')
		$db_collation = ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';
	else
		$db_collation = '';

	$endl = $command_line ? "\n" : '<br>' . "\n";

	$lines = file($filename);

	$current_type = 'sql';
	$current_data = '';
	$substep = 0;
	$last_step = '';

	// Make sure all newly created tables will have the proper characters set; this approach is used throughout upgrade_2-1_mysql.php
	if (isset($db_character_set) && $db_character_set === 'utf8')
		$lines = str_replace(') ENGINE=MyISAM;', ') ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;', $lines);

	// Count the total number of steps within this file - for progress.
	$file_steps = substr_count(implode('', $lines), '---#');
	$upcontext['total_items'] = substr_count(implode('', $lines), '--- ');
	$upcontext['debug_items'] = $file_steps;
	$upcontext['current_item_num'] = 0;
	$upcontext['current_item_name'] = '';
	$upcontext['current_debug_item_num'] = 0;
	$upcontext['current_debug_item_name'] = '';
	// This array keeps a record of what we've done in case java is dead...
	$upcontext['actioned_items'] = array();

	$done_something = false;

	foreach ($lines as $line_number => $line)
	{
		$do_current = $substep >= $_GET['substep'];

		// Get rid of any comments in the beginning of the line...
		if (substr(trim($line), 0, 2) === '/*')
			$line = preg_replace('~/\*.+?\*/~', '', $line);

		// Always flush.  Flush, flush, flush.  Flush, flush, flush, flush!  FLUSH!
		if ($is_debug && !$support_js && $command_line)
			flush();

		if (trim($line) === '')
			continue;

		if (trim(substr($line, 0, 3)) === '---')
		{
			$type = substr($line, 3, 1);

			// An error??
			if (trim($current_data) != '' && $type !== '}')
			{
				$upcontext['error_message'] = 'Error in upgrade script - line ' . $line_number . '!' . $endl;
				if ($command_line)
					echo $upcontext['error_message'];
			}

			if ($type == ' ')
			{
				if (!$support_js && $do_current && $_GET['substep'] != 0 && $command_line)
				{
					echo ' Successful.', $endl;
					flush();
				}

				$last_step = htmlspecialchars(rtrim(substr($line, 4)));
				$upcontext['current_item_num']++;
				$upcontext['current_item_name'] = $last_step;

				if ($do_current)
				{
					$upcontext['actioned_items'][] = $last_step;
					if ($command_line)
						echo ' * ';

					// Starting a new main step in our DB changes, so it's time to reset this.
					$upcontext['skip_db_substeps'] = false;
				}
			}
			elseif ($type == '#')
			{
				$upcontext['step_progress'] += (100 / $upcontext['file_count']) / $file_steps;

				$upcontext['current_debug_item_num']++;
				if (trim($line) != '---#')
					$upcontext['current_debug_item_name'] = htmlspecialchars(rtrim(substr($line, 4)));

				// Have we already done something?
				if (isset($_GET['xml']) && $done_something)
				{
					restore_error_handler();
					return $upcontext['current_debug_item_num'] >= $upcontext['debug_items'] ? true : false;
				}

				if ($do_current)
				{
					if (trim($line) == '---#' && $command_line)
						echo ' done.', $endl;
					elseif ($command_line)
						echo ' +++ ', rtrim(substr($line, 4));
					elseif (trim($line) != '---#')
					{
						if ($is_debug)
							$upcontext['actioned_items'][] = $upcontext['current_debug_item_name'];
					}
				}

				if ($substep < $_GET['substep'] && $substep + 1 >= $_GET['substep'])
				{
					if ($command_line)
						echo ' * ';
					else
						$upcontext['actioned_items'][] = $last_step;
				}

				// Small step - only if we're actually doing stuff.
				if ($do_current)
					nextSubstep(++$substep);
				else
					$substep++;
			}
			elseif ($type == '{')
				$current_type = 'code';
			elseif ($type == '}')
			{
				$current_type = 'sql';

				if (!$do_current || !empty($upcontext['skip_db_substeps']))
				{
					$current_data = '';

					// Avoid confusion when skipping something we normally would have done
					if ($do_current)
						$done_something = true;

					continue;
				}

				// @todo Update this to a try/catch for PHP 7+, because eval() now throws an exception for parse errors instead of returning false
				if (eval('global $db_prefix, $modSettings, $smcFunc, $txt, $upcontext, $db_name; ' . $current_data) === false)
				{
					$upcontext['error_message'] = 'Error in upgrade script ' . basename($filename) . ' on line ' . $line_number . '!' . $endl;
					if ($command_line)
						echo $upcontext['error_message'];
				}

				// Done with code!
				$current_data = '';
				$done_something = true;
			}

			continue;
		}

		$current_data .= $line;
		if (substr(rtrim($current_data), -1) === ';' && $current_type === 'sql')
		{
			if ((!$support_js || isset($_GET['xml'])))
			{
				if (!$do_current || !empty($upcontext['skip_db_substeps']))
				{
					$current_data = '';

					if ($do_current)
						$done_something = true;

					continue;
				}

				$current_data = strtr(substr(rtrim($current_data), 0, -1), array('{$db_prefix}' => $db_prefix, '{$boarddir}' => $boarddir, '{$sboarddir}' => addslashes($boarddir), '{$boardurl}' => $boardurl, '{$db_collation}' => $db_collation));

				upgrade_query($current_data);

				// @todo This will be how it kinda does it once mysql all stripped out - needed for postgre (etc).
				/*
				$result = $smcFunc['db_query']('', $current_data, false, false);
				// Went wrong?
				if (!$result)
				{
					// Bit of a bodge - do we want the error?
					if (!empty($upcontext['return_error']))
					{
						$upcontext['error_message'] = $smcFunc['db_error']($db_connection);
						return false;
					}
				}*/
				$done_something = true;
			}
			$current_data = '';
		}
		// If this is xml based and we're just getting the item name then that's grand.
		elseif ($support_js && !isset($_GET['xml']) && $upcontext['current_debug_item_name'] != '' && $do_current)
		{
			restore_error_handler();
			return false;
		}

		// Clean up by cleaning any step info.
		$step_progress = array();
		$custom_warning = '';
	}

	// Put back the error handler.
	restore_error_handler();

	if ($command_line)
	{
		echo ' Successful.' . "\n";
		flush();
	}

	$_GET['substep'] = 0;
	return true;
}

function upgrade_query($string, $unbuffered = false)
{
	global $db_connection, $db_server, $db_user, $db_passwd, $db_type;
	global $command_line, $upcontext, $upgradeurl, $modSettings;
	global $db_name, $db_unbuffered, $smcFunc, $txt;

	// Get the query result - working around some SMF specific security - just this once!
	$modSettings['disableQueryCheck'] = true;
	$db_unbuffered = $unbuffered;
	$ignore_insert_error = false;

	// If we got an old pg version and use a insert ignore query
	if ($db_type == 'postgresql' && !$smcFunc['db_native_replace']() && strpos($string, 'ON CONFLICT DO NOTHING') !== false)
	{
		$ignore_insert_error = true;
		$string = str_replace('ON CONFLICT DO NOTHING', '', $string);
	}
	$result = $smcFunc['db_query']('', $string, array('security_override' => true, 'db_error_skip' => true));
	$db_unbuffered = false;

	// Failure?!
	if ($result !== false)
		return $result;

	$db_error_message = $smcFunc['db_error']($db_connection);
	// If MySQL we do something more clever.
	if ($db_type == 'mysql')
	{
		$mysqli_errno = mysqli_errno($db_connection);
		$error_query = in_array(substr(trim($string), 0, 11), array('INSERT INTO', 'UPDATE IGNO', 'ALTER TABLE', 'DROP TABLE ', 'ALTER IGNOR'));

		// Error numbers:
		//    1016: Can't open file '....MYI'
		//    1050: Table already exists.
		//    1054: Unknown column name.
		//    1060: Duplicate column name.
		//    1061: Duplicate key name.
		//    1062: Duplicate entry for unique key.
		//    1068: Multiple primary keys.
		//    1072: Key column '%s' doesn't exist in table.
		//    1091: Can't drop key, doesn't exist.
		//    1146: Table doesn't exist.
		//    2013: Lost connection to server during query.

		if ($mysqli_errno == 1016)
		{
			if (preg_match('~\'([^\.\']+)~', $db_error_message, $match) != 0 && !empty($match[1]))
			{
				mysqli_query($db_connection, 'REPAIR TABLE `' . $match[1] . '`');
				$result = mysqli_query($db_connection, $string);
				if ($result !== false)
					return $result;
			}
		}
		elseif ($mysqli_errno == 2013)
		{
			$db_connection = mysqli_connect($db_server, $db_user, $db_passwd);
			mysqli_select_db($db_connection, $db_name);
			if ($db_connection)
			{
				$result = mysqli_query($db_connection, $string);
				if ($result !== false)
					return $result;
			}
		}
		// Duplicate column name... should be okay ;).
		elseif (in_array($mysqli_errno, array(1060, 1061, 1068, 1091)))
			return false;
		// Duplicate insert... make sure it's the proper type of query ;).
		elseif (in_array($mysqli_errno, array(1054, 1062, 1146)) && $error_query)
			return false;
		// Creating an index on a non-existent column.
		elseif ($mysqli_errno == 1072)
			return false;
		elseif ($mysqli_errno == 1050 && substr(trim($string), 0, 12) == 'RENAME TABLE')
			return false;
	}
	// If a table already exists don't go potty.
	else
	{
		if (in_array(substr(trim($string), 0, 8), array('CREATE T', 'CREATE S', 'DROP TABL', 'ALTER TA', 'CREATE I', 'CREATE U')))
		{
			if (strpos($db_error_message, 'exist') !== false)
				return true;
		}
		elseif (strpos(trim($string), 'INSERT ') !== false)
		{
			if (strpos($db_error_message, 'duplicate') !== false || $ignore_insert_error)
				return true;
		}
	}

	// Get the query string so we pass everything.
	$query_string = '';
	foreach ($_GET as $k => $v)
		$query_string .= ';' . $k . '=' . $v;
	if (strlen($query_string) != 0)
		$query_string = '?' . substr($query_string, 1);

	if ($command_line)
	{
		echo 'Unsuccessful!  Database error message:', "\n", $db_error_message, "\n";
		die;
	}

	// Bit of a bodge - do we want the error?
	if (!empty($upcontext['return_error']))
	{
		$upcontext['error_message'] = $db_error_message;
		$upcontext['error_string'] = $string;
		return false;
	}

	// Otherwise we have to display this somewhere appropriate if possible.
	$upcontext['forced_error_message'] = '
			<strong>' . $txt['upgrade_unsuccessful'] . '</strong><br>

			<div style="margin: 2ex;">
				' . $txt['upgrade_thisquery'] . '
				<blockquote><pre>' . nl2br(htmlspecialchars(trim($string))) . ';</pre></blockquote>

				' . $txt['upgrade_causerror'] . '
				<blockquote>' . nl2br(htmlspecialchars($db_error_message)) . '</blockquote>
			</div>

			<form action="' . $upgradeurl . $query_string . '" method="post">
				<input type="submit" value="' . $txt['upgrade_respondtime_clickhere'] . '" class="button">
			</form>
		</div>';

	upgradeExit();
}

// This performs a table alter, but does it unbuffered so the script can time out professionally.
function protected_alter($change, $substep, $is_test = false)
{
	global $db_prefix, $smcFunc;

	db_extend('packages');

	// Firstly, check whether the current index/column exists.
	$found = false;
	if ($change['type'] === 'column')
	{
		$columns = $smcFunc['db_list_columns']('{db_prefix}' . $change['table'], true);
		foreach ($columns as $column)
		{
			// Found it?
			if ($column['name'] === $change['name'])
			{
				$found |= true;
				// Do some checks on the data if we have it set.
				if (isset($change['col_type']))
					$found &= $change['col_type'] === $column['type'];
				if (isset($change['null_allowed']))
					$found &= $column['null'] == $change['null_allowed'];
				if (isset($change['default']))
					$found &= $change['default'] === $column['default'];
			}
		}
	}
	elseif ($change['type'] === 'index')
	{
		$request = upgrade_query('
			SHOW INDEX
			FROM ' . $db_prefix . $change['table']);
		if ($request !== false)
		{
			$cur_index = array();

			while ($row = $smcFunc['db_fetch_assoc']($request))
				if ($row['Key_name'] === $change['name'])
					$cur_index[(int) $row['Seq_in_index']] = $row['Column_name'];

			ksort($cur_index, SORT_NUMERIC);
			$found = array_values($cur_index) === $change['target_columns'];

			$smcFunc['db_free_result']($request);
		}
	}

	// If we're trying to add and it's added, we're done.
	if ($found && in_array($change['method'], array('add', 'change')))
		return true;
	// Otherwise if we're removing and it wasn't found we're also done.
	elseif (!$found && in_array($change['method'], array('remove', 'change_remove')))
		return true;
	// Otherwise is it just a test?
	elseif ($is_test)
		return false;

	// Not found it yet? Bummer! How about we see if we're currently doing it?
	$running = false;
	$found = false;
	while (1 == 1)
	{
		$request = upgrade_query('
			SHOW FULL PROCESSLIST');
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (strpos($row['Info'], 'ALTER TABLE ' . $db_prefix . $change['table']) !== false && strpos($row['Info'], $change['text']) !== false)
				$found = true;
		}

		// Can't find it? Then we need to run it fools!
		if (!$found && !$running)
		{
			$smcFunc['db_free_result']($request);

			$success = upgrade_query('
				ALTER TABLE ' . $db_prefix . $change['table'] . '
				' . $change['text'], true) !== false;

			if (!$success)
				return false;

			// Return
			$running = true;
		}
		// What if we've not found it, but we'd ran it already? Must of completed.
		elseif (!$found)
		{
			$smcFunc['db_free_result']($request);
			return true;
		}

		// Pause execution for a sec or three.
		sleep(3);

		// Can never be too well protected.
		nextSubstep($substep);
	}

	// Protect it.
	nextSubstep($substep);
}

/**
 * Alter a text column definition preserving its character set.
 *
 * @param array $change
 * @param int $substep
 */
function textfield_alter($change, $substep)
{
	global $db_prefix, $smcFunc;

	$request = $smcFunc['db_query']('', '
		SHOW FULL COLUMNS
		FROM {db_prefix}' . $change['table'] . '
		LIKE {string:column}',
		array(
			'column' => $change['column'],
			'db_error_skip' => true,
		)
	);
	if ($smcFunc['db_num_rows']($request) === 0)
		die('Unable to find column ' . $change['column'] . ' inside table ' . $db_prefix . $change['table']);
	$table_row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// If something of the current column definition is different, fix it.
	$column_fix = $table_row['Type'] !== $change['type'] || (strtolower($table_row['Null']) === 'yes') !== $change['null_allowed'] || ($table_row['Default'] === null) !== !isset($change['default']) || (isset($change['default']) && $change['default'] !== $table_row['Default']);

	// Columns that previously allowed null, need to be converted first.
	$null_fix = strtolower($table_row['Null']) === 'yes' && !$change['null_allowed'];

	// Get the character set that goes with the collation of the column.
	if ($column_fix && !empty($table_row['Collation']))
	{
		$request = $smcFunc['db_query']('', '
			SHOW COLLATION
			LIKE {string:collation}',
			array(
				'collation' => $table_row['Collation'],
				'db_error_skip' => true,
			)
		);
		// No results? Just forget it all together.
		if ($smcFunc['db_num_rows']($request) === 0)
			unset($table_row['Collation']);
		else
			$collation_info = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	if ($column_fix)
	{
		// Make sure there are no NULL's left.
		if ($null_fix)
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}' . $change['table'] . '
				SET ' . $change['column'] . ' = {string:default}
				WHERE ' . $change['column'] . ' IS NULL',
				array(
					'default' => isset($change['default']) ? $change['default'] : '',
					'db_error_skip' => true,
				)
			);

		// Do the actual alteration.
		$smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}' . $change['table'] . '
			CHANGE COLUMN ' . $change['column'] . ' ' . $change['column'] . ' ' . $change['type'] . (isset($collation_info['Charset']) ? ' CHARACTER SET ' . $collation_info['Charset'] . ' COLLATE ' . $collation_info['Collation'] : '') . ($change['null_allowed'] ? '' : ' NOT NULL') . (isset($change['default']) ? ' default {string:default}' : ''),
			array(
				'default' => isset($change['default']) ? $change['default'] : '',
				'db_error_skip' => true,
			)
		);
	}
	nextSubstep($substep);
}

// Check if we need to alter this query.
function checkChange(&$change)
{
	global $smcFunc, $db_type, $databases;
	static $database_version, $where_field_support;

	// Attempt to find a database_version.
	if (empty($database_version))
	{
		$database_version = $databases[$db_type]['version_check'];
		$where_field_support = $db_type == 'mysql' && version_compare('5.0', $database_version, '<=');
	}

	// Not a column we need to check on?
	if (!in_array($change['name'], array('memberGroups', 'passwordSalt')))
		return;

	// Break it up you (six|seven).
	$temp = explode(' ', str_replace('NOT NULL', 'NOT_NULL', $change['text']));

	// Can we support a shortcut method?
	if ($where_field_support)
	{
		// Get the details about this change.
		$request = $smcFunc['db_query']('', '
			SHOW FIELDS
			FROM {db_prefix}{raw:table}
			WHERE Field = {string:old_name} OR Field = {string:new_name}',
			array(
				'table' => $change['table'],
				'old_name' => $temp[1],
				'new_name' => $temp[2],
			)
		);
		// !!! This doesn't technically work because we don't pass request into it, but it hasn't broke anything yet.
		if ($smcFunc['db_num_rows'] != 1)
			return;

		list (, $current_type) = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}
	else
	{
		// Do this the old fashion, sure method way.
		$request = $smcFunc['db_query']('', '
			SHOW FIELDS
			FROM {db_prefix}{raw:table}',
			array(
				'table' => $change['table'],
			)
		);
		// Mayday!
		// !!! This doesn't technically work because we don't pass request into it, but it hasn't broke anything yet.
		if ($smcFunc['db_num_rows'] == 0)
			return;

		// Oh where, oh where has my little field gone. Oh where can it be...
		while ($row = $smcFunc['db_query']($request))
			if ($row['Field'] == $temp[1] || $row['Field'] == $temp[2])
			{
				$current_type = $row['Type'];
				break;
			}
	}

	// If this doesn't match, the column may of been altered for a reason.
	if (trim($current_type) != trim($temp[3]))
		$temp[3] = $current_type;

	// Piece this back together.
	$change['text'] = str_replace('NOT_NULL', 'NOT NULL', implode(' ', $temp));
}

// The next substep.
function nextSubstep($substep)
{
	global $start_time, $timeLimitThreshold, $command_line, $custom_warning;
	global $step_progress, $is_debug, $upcontext;

	if ($_GET['substep'] < $substep)
		$_GET['substep'] = $substep;

	if ($command_line)
	{
		if (time() - $start_time > 1 && empty($is_debug))
		{
			echo '.';
			$start_time = time();
		}
		return;
	}

	@set_time_limit(300);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	if (time() - $start_time <= $timeLimitThreshold)
		return;

	// Do we have some custom step progress stuff?
	if (!empty($step_progress))
	{
		$upcontext['substep_progress'] = 0;
		$upcontext['substep_progress_name'] = $step_progress['name'];
		if ($step_progress['current'] > $step_progress['total'])
			$upcontext['substep_progress'] = 99.9;
		else
			$upcontext['substep_progress'] = ($step_progress['current'] / $step_progress['total']) * 100;

		// Make it nicely rounded.
		$upcontext['substep_progress'] = round($upcontext['substep_progress'], 1);
	}

	// If this is XML we just exit right away!
	if (isset($_GET['xml']))
		return upgradeExit();

	// We're going to pause after this!
	$upcontext['pause'] = true;

	$upcontext['query_string'] = '';
	foreach ($_GET as $k => $v)
	{
		if ($k != 'data' && $k != 'substep' && $k != 'step')
			$upcontext['query_string'] .= ';' . $k . '=' . $v;
	}

	// Custom warning?
	if (!empty($custom_warning))
		$upcontext['custom_warning'] = $custom_warning;

	upgradeExit();
}

function cmdStep0()
{
	global $boarddir, $sourcedir, $modSettings, $start_time, $cachedir, $databases, $db_type, $smcFunc, $upcontext;
	global $is_debug;
	$start_time = time();

	ob_end_clean();
	ob_implicit_flush(1);
	@set_time_limit(600);

	if (!isset($_SERVER['argv']))
		$_SERVER['argv'] = array();
	$_GET['maint'] = 1;

	foreach ($_SERVER['argv'] as $i => $arg)
	{
		if (preg_match('~^--language=(.+)$~', $arg, $match) != 0)
			$upcontext['lang'] = $match[1];
		elseif (preg_match('~^--path=(.+)$~', $arg) != 0)
			continue;
		elseif ($arg == '--no-maintenance')
			$_GET['maint'] = 0;
		elseif ($arg == '--debug')
			$is_debug = true;
		elseif ($arg == '--backup')
			$_POST['backup'] = 1;
		elseif ($arg == '--template' && (file_exists($boarddir . '/template.php') || file_exists($boarddir . '/template.html') && !file_exists($modSettings['theme_dir'] . '/converted')))
			$_GET['conv'] = 1;
		elseif ($i != 0)
		{
			echo 'SMF Command-line Upgrader
Usage: /path/to/php -f ' . basename(__FILE__) . ' -- [OPTION]...

	--language=LANG         Reset the forum\'s language to LANG.
	--no-maintenance        Don\'t put the forum into maintenance mode.
	--debug                 Output debugging information.
	--backup                Create backups of tables with "backup_" prefix.';
			echo "\n";
			exit;
		}
	}

	if (!php_version_check())
		print_error('Error: PHP ' . PHP_VERSION . ' does not match version requirements.', true);
	if (!db_version_check())
		print_error('Error: ' . $databases[$db_type]['name'] . ' ' . $databases[$db_type]['version'] . ' does not match minimum requirements.', true);

	// Do some checks to make sure they have proper privileges
	db_extend('packages');

	// CREATE
	$create = $smcFunc['db_create_table']('{db_prefix}priv_check', array(array('name' => 'id_test', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true)), array(array('columns' => array('id_test'), 'primary' => true)), array(), 'overwrite');

	// ALTER
	$alter = $smcFunc['db_add_column']('{db_prefix}priv_check', array('name' => 'txt', 'type' => 'varchar', 'size' => 4, 'null' => false, 'default' => ''));

	// DROP
	$drop = $smcFunc['db_drop_table']('{db_prefix}priv_check');

	// Sorry... we need CREATE, ALTER and DROP
	if (!$create || !$alter || !$drop)
		print_error("The " . $databases[$db_type]['name'] . " user you have set in Settings.php does not have proper privileges.\n\nPlease ask your host to give this user the ALTER, CREATE, and DROP privileges.", true);

	$check = @file_exists($modSettings['theme_dir'] . '/index.template.php')
		&& @file_exists($sourcedir . '/QueryString.php')
		&& @file_exists($sourcedir . '/ManageBoards.php');
	if (!$check && !isset($modSettings['smfVersion']))
		print_error('Error: Some files are missing or out-of-date.', true);

	// Do a quick version spot check.
	$temp = substr(@implode('', @file($boarddir . '/index.php')), 0, 4096);
	preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $temp, $match);
	if (empty($match[1]) || (trim($match[1]) != SMF_VERSION))
		print_error('Error: Some files have not yet been updated properly.');

	// Make sure Settings.php is writable.
	quickFileWritable($boarddir . '/Settings.php');
	if (!is_writable($boarddir . '/Settings.php'))
		print_error('Error: Unable to obtain write access to "Settings.php".', true);

	// Make sure Settings_bak.php is writable.
	quickFileWritable($boarddir . '/Settings_bak.php');
	if (!is_writable($boarddir . '/Settings_bak.php'))
		print_error('Error: Unable to obtain write access to "Settings_bak.php".');

	if (isset($modSettings['agreement']) && (!is_writable($boarddir) || file_exists($boarddir . '/agreement.txt')) && !is_writable($boarddir . '/agreement.txt'))
		print_error('Error: Unable to obtain write access to "agreement.txt".');
	elseif (isset($modSettings['agreement']))
	{
		$fp = fopen($boarddir . '/agreement.txt', 'w');
		fwrite($fp, $modSettings['agreement']);
		fclose($fp);
	}

	// Make sure Themes is writable.
	quickFileWritable($modSettings['theme_dir']);

	if (!is_writable($modSettings['theme_dir']) && !isset($modSettings['smfVersion']))
		print_error('Error: Unable to obtain write access to "Themes".');

	// Make sure cache directory exists and is writable!
	$cachedir_temp = empty($cachedir) ? $boarddir . '/cache' : $cachedir;
	if (!file_exists($cachedir_temp))
		@mkdir($cachedir_temp);

	// Make sure the cache temp dir is writable.
	quickFileWritable($cachedir_temp);

	if (!is_writable($cachedir_temp))
		print_error('Error: Unable to obtain write access to "cache".', true);

	// Make sure db_last_error.php is writable.
	quickFileWritable($cachedir_temp . '/db_last_error.php');
	if (!is_writable($cachedir_temp . '/db_last_error.php'))
		print_error('Error: Unable to obtain write access to "db_last_error.php".');

	if (!file_exists($modSettings['theme_dir'] . '/languages/index.' . $upcontext['language'] . '.php'))
		print_error('Error: Unable to find language files!', true);
	else
	{
		$temp = substr(@implode('', @file($modSettings['theme_dir'] . '/languages/index.' . $upcontext['language'] . '.php')), 0, 4096);
		preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*index(?:[\s]{2}|\*/)~i', $temp, $match);

		if (empty($match[1]) || $match[1] != SMF_LANG_VERSION)
			print_error('Error: Language files out of date.', true);
		if (!file_exists($modSettings['theme_dir'] . '/languages/Install.' . $upcontext['language'] . '.php'))
			print_error('Error: Install language is missing for selected language.', true);

		// Otherwise include it!
		require_once($modSettings['theme_dir'] . '/languages/Install.' . $upcontext['language'] . '.php');
	}

	// Make sure we skip the HTML for login.
	$_POST['upcont'] = true;
	$upcontext['current_step'] = 1;
}

/**
 * Handles converting your database to UTF-8
 */
function ConvertUtf8()
{
	global $upcontext, $db_character_set, $sourcedir, $smcFunc, $modSettings, $language;
	global $db_prefix, $db_type, $command_line, $support_js, $txt;

	// Done it already?
	if (!empty($_POST['utf8_done']))
		return true;

	// First make sure they aren't already on UTF-8 before we go anywhere...
	if ($db_type == 'postgresql' || ($db_character_set === 'utf8' && !empty($modSettings['global_character_set']) && $modSettings['global_character_set'] === 'UTF-8'))
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array(array('global_character_set', 'UTF-8')),
			array('variable')
		);

		return true;
	}
	else
	{
		$upcontext['page_title'] = $txt['converting_utf8'];
		$upcontext['sub_template'] = isset($_GET['xml']) ? 'convert_xml' : 'convert_utf8';

		// The character sets used in SMF's language files with their db equivalent.
		$charsets = array(
			// Armenian
			'armscii8' => 'armscii8',
			// Chinese-traditional.
			'big5' => 'big5',
			// Chinese-simplified.
			'gbk' => 'gbk',
			// West European.
			'ISO-8859-1' => 'latin1',
			// Romanian.
			'ISO-8859-2' => 'latin2',
			// Turkish.
			'ISO-8859-9' => 'latin5',
			// Latvian
			'ISO-8859-13' => 'latin7',
			// West European with Euro sign.
			'ISO-8859-15' => 'latin9',
			// Thai.
			'tis-620' => 'tis620',
			// Persian, Chinese, etc.
			'UTF-8' => 'utf8',
			// Russian.
			'windows-1251' => 'cp1251',
			// Greek.
			'windows-1253' => 'utf8',
			// Hebrew.
			'windows-1255' => 'utf8',
			// Arabic.
			'windows-1256' => 'cp1256',
		);

		// Get a list of character sets supported by your MySQL server.
		$request = $smcFunc['db_query']('', '
			SHOW CHARACTER SET',
			array(
			)
		);
		$db_charsets = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$db_charsets[] = $row['Charset'];

		$smcFunc['db_free_result']($request);

		// Character sets supported by both MySQL and SMF's language files.
		$charsets = array_intersect($charsets, $db_charsets);

		// Use the messages.body column as indicator for the database charset.
		$request = $smcFunc['db_query']('', '
			SHOW FULL COLUMNS
			FROM {db_prefix}messages
			LIKE {string:body_like}',
			array(
				'body_like' => 'body',
			)
		);
		$column_info = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// A collation looks like latin1_swedish. We only need the character set.
		list($upcontext['database_charset']) = explode('_', $column_info['Collation']);
		$upcontext['database_charset'] = in_array($upcontext['database_charset'], $charsets) ? array_search($upcontext['database_charset'], $charsets) : $upcontext['database_charset'];

		// Detect whether a fulltext index is set.
		$request = $smcFunc['db_query']('', '
			SHOW INDEX
			FROM {db_prefix}messages',
			array(
			)
		);

		$upcontext['dropping_index'] = false;

		// If there's a fulltext index, we need to drop it first...
		if ($request !== false || $smcFunc['db_num_rows']($request) != 0)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
				if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT'))
					$upcontext['fulltext_index'][] = $row['Key_name'];
			$smcFunc['db_free_result']($request);

			if (isset($upcontext['fulltext_index']))
				$upcontext['fulltext_index'] = array_unique($upcontext['fulltext_index']);
		}

		// Drop it and make a note...
		if (!empty($upcontext['fulltext_index']))
		{
			$upcontext['dropping_index'] = true;

			$smcFunc['db_query']('', '
				ALTER TABLE {db_prefix}messages
				DROP INDEX ' . implode(',
				DROP INDEX ', $upcontext['fulltext_index']),
				array(
					'db_error_skip' => true,
				)
			);

			// Update the settings table
			$smcFunc['db_insert']('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				array('db_search_index', ''),
				array('variable')
			);
		}

		// Figure out what charset we should be converting from...
		$lang_charsets = array(
			'arabic' => 'windows-1256',
			'armenian_east' => 'armscii-8',
			'armenian_west' => 'armscii-8',
			'azerbaijani_latin' => 'ISO-8859-9',
			'bangla' => 'UTF-8',
			'belarusian' => 'ISO-8859-5',
			'bulgarian' => 'windows-1251',
			'cambodian' => 'UTF-8',
			'chinese_simplified' => 'gbk',
			'chinese_traditional' => 'big5',
			'croation' => 'ISO-8859-2',
			'czech' => 'ISO-8859-2',
			'czech_informal' => 'ISO-8859-2',
			'english_pirate' => 'UTF-8',
			'esperanto' => 'ISO-8859-3',
			'estonian' => 'ISO-8859-15',
			'filipino_tagalog' => 'UTF-8',
			'filipino_vasayan' => 'UTF-8',
			'georgian' => 'UTF-8',
			'greek' => 'ISO-8859-3',
			'hebrew' => 'windows-1255',
			'hungarian' => 'ISO-8859-2',
			'irish' => 'UTF-8',
			'japanese' => 'UTF-8',
			'khmer' => 'UTF-8',
			'korean' => 'UTF-8',
			'kurdish_kurmanji' => 'ISO-8859-9',
			'kurdish_sorani' => 'windows-1256',
			'lao' => 'tis-620',
			'latvian' => 'ISO-8859-13',
			'lithuanian' => 'ISO-8859-4',
			'macedonian' => 'UTF-8',
			'malayalam' => 'UTF-8',
			'mongolian' => 'UTF-8',
			'nepali' => 'UTF-8',
			'persian' => 'UTF-8',
			'polish' => 'ISO-8859-2',
			'romanian' => 'ISO-8859-2',
			'russian' => 'windows-1252',
			'sakha' => 'UTF-8',
			'serbian_cyrillic' => 'ISO-8859-5',
			'serbian_latin' => 'ISO-8859-2',
			'sinhala' => 'UTF-8',
			'slovak' => 'ISO-8859-2',
			'slovenian' => 'ISO-8859-2',
			'telugu' => 'UTF-8',
			'thai' => 'tis-620',
			'turkish' => 'ISO-8859-9',
			'turkmen' => 'ISO-8859-9',
			'ukranian' => 'windows-1251',
			'urdu' => 'UTF-8',
			'uzbek_cyrillic' => 'ISO-8859-5',
			'uzbek_latin' => 'ISO-8859-5',
			'vietnamese' => 'UTF-8',
			'yoruba' => 'UTF-8'
		);

		// Default to ISO-8859-1 unless we detected another supported charset
		$upcontext['charset_detected'] = (isset($lang_charsets[$language]) && isset($charsets[strtr(strtolower($upcontext['charset_detected']), array('utf' => 'UTF', 'iso' => 'ISO'))])) ? $lang_charsets[$language] : 'ISO-8859-1';

		$upcontext['charset_list'] = array_keys($charsets);

		// Translation table for the character sets not native for MySQL.
		$translation_tables = array(
			'windows-1255' => array(
				'0x81' => '\'\'',		'0x8A' => '\'\'',		'0x8C' => '\'\'',
				'0x8D' => '\'\'',		'0x8E' => '\'\'',		'0x8F' => '\'\'',
				'0x90' => '\'\'',		'0x9A' => '\'\'',		'0x9C' => '\'\'',
				'0x9D' => '\'\'',		'0x9E' => '\'\'',		'0x9F' => '\'\'',
				'0xCA' => '\'\'',		'0xD9' => '\'\'',		'0xDA' => '\'\'',
				'0xDB' => '\'\'',		'0xDC' => '\'\'',		'0xDD' => '\'\'',
				'0xDE' => '\'\'',		'0xDF' => '\'\'',		'0xFB' => '0xD792',
				'0xFC' => '0xE282AC',		'0xFF' => '0xD6B2',		'0xC2' => '0xFF',
				'0x80' => '0xFC',		'0xE2' => '0xFB',		'0xA0' => '0xC2A0',
				'0xA1' => '0xC2A1',		'0xA2' => '0xC2A2',		'0xA3' => '0xC2A3',
				'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
				'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
				'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
				'0xAF' => '0xC2AF',		'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',
				'0xB2' => '0xC2B2',		'0xB3' => '0xC2B3',		'0xB4' => '0xC2B4',
				'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
				'0xB8' => '0xC2B8',		'0xB9' => '0xC2B9',		'0xBB' => '0xC2BB',
				'0xBC' => '0xC2BC',		'0xBD' => '0xC2BD',		'0xBE' => '0xC2BE',
				'0xBF' => '0xC2BF',		'0xD7' => '0xD7B3',		'0xD1' => '0xD781',
				'0xD4' => '0xD7B0',		'0xD5' => '0xD7B1',		'0xD6' => '0xD7B2',
				'0xE0' => '0xD790',		'0xEA' => '0xD79A',		'0xEC' => '0xD79C',
				'0xED' => '0xD79D',		'0xEE' => '0xD79E',		'0xEF' => '0xD79F',
				'0xF0' => '0xD7A0',		'0xF1' => '0xD7A1',		'0xF2' => '0xD7A2',
				'0xF3' => '0xD7A3',		'0xF5' => '0xD7A5',		'0xF6' => '0xD7A6',
				'0xF7' => '0xD7A7',		'0xF8' => '0xD7A8',		'0xF9' => '0xD7A9',
				'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
				'0x86' => '0xE280A0',	'0x87' => '0xE280A1',	'0x89' => '0xE280B0',
				'0x8B' => '0xE280B9',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
				'0x95' => '0xE280A2',	'0x97' => '0xE28094',	'0x99' => '0xE284A2',
				'0xC0' => '0xD6B0',		'0xC1' => '0xD6B1',		'0xC3' => '0xD6B3',
				'0xC4' => '0xD6B4',		'0xC5' => '0xD6B5',		'0xC6' => '0xD6B6',
				'0xC7' => '0xD6B7',		'0xC8' => '0xD6B8',		'0xC9' => '0xD6B9',
				'0xCB' => '0xD6BB',		'0xCC' => '0xD6BC',		'0xCD' => '0xD6BD',
				'0xCE' => '0xD6BE',		'0xCF' => '0xD6BF',		'0xD0' => '0xD780',
				'0xD2' => '0xD782',		'0xE3' => '0xD793',		'0xE4' => '0xD794',
				'0xE5' => '0xD795',		'0xE7' => '0xD797',		'0xE9' => '0xD799',
				'0xFD' => '0xE2808E',	'0xFE' => '0xE2808F',	'0x92' => '0xE28099',
				'0x83' => '0xC692',		'0xD3' => '0xD783',		'0x88' => '0xCB86',
				'0x98' => '0xCB9C',		'0x91' => '0xE28098',	'0x96' => '0xE28093',
				'0xBA' => '0xC3B7',		'0x9B' => '0xE280BA',	'0xAA' => '0xC397',
				'0xA4' => '0xE282AA',	'0xE1' => '0xD791',		'0xE6' => '0xD796',
				'0xE8' => '0xD798',		'0xEB' => '0xD79B',		'0xF4' => '0xD7A4',
				'0xFA' => '0xD7AA',
			),
			'windows-1253' => array(
				'0x81' => '\'\'',			'0x88' => '\'\'',			'0x8A' => '\'\'',
				'0x8C' => '\'\'',			'0x8D' => '\'\'',			'0x8E' => '\'\'',
				'0x8F' => '\'\'',			'0x90' => '\'\'',			'0x98' => '\'\'',
				'0x9A' => '\'\'',			'0x9C' => '\'\'',			'0x9D' => '\'\'',
				'0x9E' => '\'\'',			'0x9F' => '\'\'',			'0xAA' => '\'\'',
				'0xD2' => '0xE282AC',			'0xFF' => '0xCE92',			'0xCE' => '0xCE9E',
				'0xB8' => '0xCE88',		'0xBA' => '0xCE8A',		'0xBC' => '0xCE8C',
				'0xBE' => '0xCE8E',		'0xBF' => '0xCE8F',		'0xC0' => '0xCE90',
				'0xC8' => '0xCE98',		'0xCA' => '0xCE9A',		'0xCC' => '0xCE9C',
				'0xCD' => '0xCE9D',		'0xCF' => '0xCE9F',		'0xDA' => '0xCEAA',
				'0xE8' => '0xCEB8',		'0xEA' => '0xCEBA',		'0xEC' => '0xCEBC',
				'0xEE' => '0xCEBE',		'0xEF' => '0xCEBF',		'0xC2' => '0xFF',
				'0xBD' => '0xC2BD',		'0xED' => '0xCEBD',		'0xB2' => '0xC2B2',
				'0xA0' => '0xC2A0',		'0xA3' => '0xC2A3',		'0xA4' => '0xC2A4',
				'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
				'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
				'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
				'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',		'0xB3' => '0xC2B3',
				'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
				'0xBB' => '0xC2BB',		'0xE2' => '0xCEB2',		'0x80' => '0xD2',
				'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
				'0x86' => '0xE280A0',	'0xA1' => '0xCE85',		'0xA2' => '0xCE86',
				'0x87' => '0xE280A1',	'0x89' => '0xE280B0',	'0xB9' => '0xCE89',
				'0x8B' => '0xE280B9',	'0x91' => '0xE28098',	'0x99' => '0xE284A2',
				'0x92' => '0xE28099',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
				'0x95' => '0xE280A2',	'0x96' => '0xE28093',	'0x97' => '0xE28094',
				'0x9B' => '0xE280BA',	'0xAF' => '0xE28095',	'0xB4' => '0xCE84',
				'0xC1' => '0xCE91',		'0xC3' => '0xCE93',		'0xC4' => '0xCE94',
				'0xC5' => '0xCE95',		'0xC6' => '0xCE96',		'0x83' => '0xC692',
				'0xC7' => '0xCE97',		'0xC9' => '0xCE99',		'0xCB' => '0xCE9B',
				'0xD0' => '0xCEA0',		'0xD1' => '0xCEA1',		'0xD3' => '0xCEA3',
				'0xD4' => '0xCEA4',		'0xD5' => '0xCEA5',		'0xD6' => '0xCEA6',
				'0xD7' => '0xCEA7',		'0xD8' => '0xCEA8',		'0xD9' => '0xCEA9',
				'0xDB' => '0xCEAB',		'0xDC' => '0xCEAC',		'0xDD' => '0xCEAD',
				'0xDE' => '0xCEAE',		'0xDF' => '0xCEAF',		'0xE0' => '0xCEB0',
				'0xE1' => '0xCEB1',		'0xE3' => '0xCEB3',		'0xE4' => '0xCEB4',
				'0xE5' => '0xCEB5',		'0xE6' => '0xCEB6',		'0xE7' => '0xCEB7',
				'0xE9' => '0xCEB9',		'0xEB' => '0xCEBB',		'0xF0' => '0xCF80',
				'0xF1' => '0xCF81',		'0xF2' => '0xCF82',		'0xF3' => '0xCF83',
				'0xF4' => '0xCF84',		'0xF5' => '0xCF85',		'0xF6' => '0xCF86',
				'0xF7' => '0xCF87',		'0xF8' => '0xCF88',		'0xF9' => '0xCF89',
				'0xFA' => '0xCF8A',		'0xFB' => '0xCF8B',		'0xFC' => '0xCF8C',
				'0xFD' => '0xCF8D',		'0xFE' => '0xCF8E',
			),
		);

		// Make some preparations.
		if (isset($translation_tables[$upcontext['charset_detected']]))
		{
			$replace = '%field%';

			// Build a huge REPLACE statement...
			foreach ($translation_tables[$upcontext['charset_detected']] as $from => $to)
				$replace = 'REPLACE(' . $replace . ', ' . $from . ', ' . $to . ')';
		}

		// Get a list of table names ahead of time... This makes it easier to set our substep and such
		db_extend();
		$queryTables = $smcFunc['db_list_tables'](false, $db_prefix . '%');

		$upcontext['table_count'] = count($queryTables);

		// What ones have we already done?
		foreach ($queryTables as $id => $table)
			if ($id < $_GET['substep'])
				$upcontext['previous_tables'][] = $table;

		$upcontext['cur_table_num'] = $_GET['substep'];
		$upcontext['cur_table_name'] = str_replace($db_prefix, '', $queryTables[$_GET['substep']]);
		$upcontext['step_progress'] = (int) (($upcontext['cur_table_num'] / $upcontext['table_count']) * 100);

		// Make sure we're ready & have painted the template before proceeding
		if ($support_js && !isset($_GET['xml']))
		{
			$_GET['substep'] = 0;
			return false;
		}

		// We want to start at the first table.
		for ($substep = $_GET['substep'], $n = count($queryTables); $substep < $n; $substep++)
		{
			$table = $queryTables[$substep];

			$getTableStatus = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $table)
				)
			);

			// Only one row so we can just fetch_assoc and free the result...
			$table_info = $smcFunc['db_fetch_assoc']($getTableStatus);
			$smcFunc['db_free_result']($getTableStatus);

			$upcontext['cur_table_name'] = str_replace($db_prefix, '', (isset($queryTables[$substep + 1]) ? $queryTables[$substep + 1] : $queryTables[$substep]));
			$upcontext['cur_table_num'] = $substep + 1;
			$upcontext['step_progress'] = (int) (($upcontext['cur_table_num'] / $upcontext['table_count']) * 100);

			// Do we need to pause?
			nextSubstep($substep);

			// Just to make sure it doesn't time out.
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$table_charsets = array();

			// Loop through each column.
			$queryColumns = $smcFunc['db_query']('', '
				SHOW FULL COLUMNS
				FROM ' . $table_info['Name'],
				array(
				)
			);
			while ($column_info = $smcFunc['db_fetch_assoc']($queryColumns))
			{
				// Only text'ish columns have a character set and need converting.
				if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false)
				{
					$collation = empty($column_info['Collation']) || $column_info['Collation'] === 'NULL' ? $table_info['Collation'] : $column_info['Collation'];
					if (!empty($collation) && $collation !== 'NULL')
					{
						list($charset) = explode('_', $collation);

						// Build structure of columns to operate on organized by charset; only operate on columns not yet utf8
						if ($charset != 'utf8')
						{
							if (!isset($table_charsets[$charset]))
								$table_charsets[$charset] = array();

							$table_charsets[$charset][] = $column_info;
						}
					}
				}
			}
			$smcFunc['db_free_result']($queryColumns);

			// Only change the non-utf8 columns identified above
			if (count($table_charsets) > 0)
			{
				$updates_blob = '';
				$updates_text = '';
				foreach ($table_charsets as $charset => $columns)
				{
					if ($charset !== $charsets[$upcontext['charset_detected']])
					{
						foreach ($columns as $column)
						{
							$updates_blob .= '
								CHANGE COLUMN `' . $column['Field'] . '` `' . $column['Field'] . '` ' . strtr($column['Type'], array('text' => 'blob', 'char' => 'binary')) . ($column['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
							$updates_text .= '
								CHANGE COLUMN `' . $column['Field'] . '` `' . $column['Field'] . '` ' . $column['Type'] . ' CHARACTER SET ' . $charsets[$upcontext['charset_detected']] . ($column['Null'] === 'YES' ? '' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
						}
					}
				}

				// Change the columns to binary form.
				$smcFunc['db_query']('', '
					ALTER TABLE {raw:table_name}{raw:updates_blob}',
					array(
						'table_name' => $table_info['Name'],
						'updates_blob' => substr($updates_blob, 0, -1),
					)
				);

				// Convert the character set if MySQL has no native support for it.
				if (isset($translation_tables[$upcontext['charset_detected']]))
				{
					$update = '';
					foreach ($table_charsets as $charset => $columns)
						foreach ($columns as $column)
							$update .= '
								' . $column['Field'] . ' = ' . strtr($replace, array('%field%' => $column['Field'])) . ',';

					$smcFunc['db_query']('', '
						UPDATE {raw:table_name}
						SET {raw:updates}',
						array(
							'table_name' => $table_info['Name'],
							'updates' => substr($update, 0, -1),
						)
					);
				}

				// Change the columns back, but with the proper character set.
				$smcFunc['db_query']('', '
					ALTER TABLE {raw:table_name}{raw:updates_text}',
					array(
						'table_name' => $table_info['Name'],
						'updates_text' => substr($updates_text, 0, -1),
					)
				);
			}

			// Now do the actual conversion (if still needed).
			if ($charsets[$upcontext['charset_detected']] !== 'utf8')
			{
				if ($command_line)
					echo 'Converting table ' . $table_info['Name'] . ' to UTF-8...';

				$smcFunc['db_query']('', '
					ALTER TABLE {raw:table_name}
					CONVERT TO CHARACTER SET utf8',
					array(
						'table_name' => $table_info['Name'],
					)
				);

				if ($command_line)
					echo " done.\n";
			}
			// If this is XML to keep it nice for the user do one table at a time anyway!
			if (isset($_GET['xml']) && $upcontext['cur_table_num'] < $upcontext['table_count'])
				return upgradeExit();
		}

		$prev_charset = empty($translation_tables[$upcontext['charset_detected']]) ? $charsets[$upcontext['charset_detected']] : $translation_tables[$upcontext['charset_detected']];

		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array(array('global_character_set', 'UTF-8'), array('previousCharacterSet', $prev_charset)),
			array('variable')
		);

		// Store it in Settings.php too because it's needed before db connection.
		// Hopefully this works...
		require_once($sourcedir . '/Subs-Admin.php');
		updateSettingsFile(array('db_character_set' => '\'utf8\''));

		// The conversion might have messed up some serialized strings. Fix them!
		$request = $smcFunc['db_query']('', '
			SELECT id_action, extra
			FROM {db_prefix}log_actions
			WHERE action IN ({string:remove}, {string:delete})',
			array(
				'remove' => 'remove',
				'delete' => 'delete',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (@safe_unserialize($row['extra']) === false && preg_match('~^(a:3:{s:5:"topic";i:\d+;s:7:"subject";s:)(\d+):"(.+)"(;s:6:"member";s:5:"\d+";})$~', $row['extra'], $matches) === 1)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_actions
					SET extra = {string:extra}
					WHERE id_action = {int:current_action}',
					array(
						'current_action' => $row['id_action'],
						'extra' => $matches[1] . strlen($matches[3]) . ':"' . $matches[3] . '"' . $matches[4],
					)
				);
		}
		$smcFunc['db_free_result']($request);

		if ($upcontext['dropping_index'] && $command_line)
		{
			echo "\n" . '', $txt['upgrade_fulltext_error'], '';
			flush();
		}
	}
	$_GET['substep'] = 0;
	return false;
}

/**
 * Attempts to repair corrupted serialized data strings
 *
 * @param string $string Serialized data that has been corrupted
 * @return string|bool A working version of the serialized data, or the original if the repair failed
 */
function fix_serialized_data($string)
{
	// If its not broken, don't fix it.
	if (!is_string($string) || !preg_match('/^[bidsa]:/', $string) || @safe_unserialize($string) !== false)
		return $string;

	// This bit fixes incorrect string lengths, which can happen if the character encoding was changed (e.g. conversion to UTF-8)
	$new_string = preg_replace_callback('~\bs:(\d+):"(.*?)";(?=$|[bidsa]:|[{}]|N;)~s', function ($matches) {return 's:' . strlen($matches[2]) . ':"' . $matches[2] . '";';}, $string);

	// @todo Add more possible fixes here. For example, fix incorrect array lengths, try to handle truncated strings gracefully, etc.

	// Did it work?
	if (@safe_unserialize($new_string) !== false)
		return $new_string;
	else
		return $string;
}

function serialize_to_json()
{
	global $command_line, $smcFunc, $modSettings, $sourcedir, $upcontext, $support_js, $txt;

	$upcontext['sub_template'] = isset($_GET['xml']) ? 'serialize_json_xml' : 'serialize_json';
	// First thing's first - did we already do this?
	if (!empty($modSettings['json_done']))
	{
		if ($command_line)
			return ConvertUtf8();
		else
			return true;
	}

	// Done it already - js wise?
	if (!empty($_POST['json_done']))
		return true;

	// List of tables affected by this function
	// name => array('key', col1[,col2|true[,col3]])
	// If 3rd item in array is true, it indicates that col1 could be empty...
	$tables = array(
		'background_tasks' => array('id_task', 'task_data'),
		'log_actions' => array('id_action', 'extra'),
		'log_online' => array('session', 'url'),
		'log_packages' => array('id_install', 'db_changes', 'failed_steps', 'credits'),
		'log_spider_hits' => array('id_hit', 'url'),
		'log_subscribed' => array('id_sublog', 'pending_details'),
		'pm_rules' => array('id_rule', 'criteria', 'actions'),
		'qanda' => array('id_question', 'answers'),
		'subscriptions' => array('id_subscribe', 'cost'),
		'user_alerts' => array('id_alert', 'extra', true),
		'user_drafts' => array('id_draft', 'to_list', true),
		// These last two are a bit different - we'll handle those separately
		'settings' => array(),
		'themes' => array()
	);

	// Set up some context stuff...
	// Because we're not using numeric indices, we need this to figure out the current table name...
	$keys = array_keys($tables);

	$upcontext['page_title'] = $txt['converting_json'];
	$upcontext['table_count'] = count($keys);
	$upcontext['cur_table_num'] = $_GET['substep'];
	$upcontext['cur_table_name'] = isset($keys[$_GET['substep']]) ? $keys[$_GET['substep']] : $keys[0];
	$upcontext['step_progress'] = (int) (($upcontext['cur_table_num'] / $upcontext['table_count']) * 100);

	foreach ($keys as $id => $table)
		if ($id < $_GET['substep'])
			$upcontext['previous_tables'][] = $table;

	if ($command_line)
		echo 'Converting data from serialize() to json_encode().';

	if (!$support_js || isset($_GET['xml']))
	{
		// Fix the data in each table
		for ($substep = $_GET['substep']; $substep < $upcontext['table_count']; $substep++)
		{
			$upcontext['cur_table_name'] = isset($keys[$substep + 1]) ? $keys[$substep + 1] : $keys[$substep];
			$upcontext['cur_table_num'] = $substep + 1;

			$upcontext['step_progress'] = (int) (($upcontext['cur_table_num'] / $upcontext['table_count']) * 100);

			// Do we need to pause?
			nextSubstep($substep);

			// Initialize a few things...
			$where = '';
			$vars = array();
			$table = $keys[$substep];
			$info = $tables[$table];

			// Now the fun - build our queries and all that fun stuff
			if ($table == 'settings')
			{
				// Now a few settings...
				$serialized_settings = array(
					'attachment_basedirectories',
					'attachmentUploadDir',
					'cal_today_birthday',
					'cal_today_event',
					'cal_today_holiday',
					'displayFields',
					'last_attachments_directory',
					'memberlist_cache',
					'search_custom_index_config',
					'spider_name_cache'
				);

				// Loop through and fix these...
				$new_settings = array();
				if ($command_line)
					echo "\n" . 'Fixing some settings...';

				foreach ($serialized_settings as $var)
				{
					if (isset($modSettings[$var]))
					{
						// Attempt to unserialize the setting
						$temp = @safe_unserialize($modSettings[$var]);
						// Maybe conversion to UTF-8 corrupted it
						if ($temp === false)
							$temp = @safe_unserialize(fix_serialized_data($modSettings[$var]));

						if (!$temp && $command_line)
							echo "\n - Failed to unserialize the '" . $var . "' setting. Skipping.";
						elseif ($temp !== false)
							$new_settings[$var] = json_encode($temp);
					}
				}

				// Update everything at once
				if (!function_exists('cache_put_data'))
					require_once($sourcedir . '/Load.php');
				updateSettings($new_settings, true);

				if ($command_line)
					echo ' done.';
			}
			elseif ($table == 'themes')
			{
				// Finally, fix the admin prefs. Unfortunately this is stored per theme, but hopefully they only have one theme installed at this point...
				$query = $smcFunc['db_query']('', '
					SELECT id_member, id_theme, value FROM {db_prefix}themes
					WHERE variable = {string:admin_prefs}',
					array(
						'admin_prefs' => 'admin_preferences'
					)
				);

				if ($smcFunc['db_num_rows']($query) != 0)
				{
					while ($row = $smcFunc['db_fetch_assoc']($query))
					{
						$temp = @safe_unserialize($row['value']);
						if ($temp === false)
							$temp = @safe_unserialize(fix_serialized_data($row['value']));

						if ($command_line)
						{
							if ($temp === false)
								echo "\n" . 'Unserialize of admin_preferences for user ' . $row['id_member'] . ' failed. Skipping.';
							else
								echo "\n" . 'Fixing admin preferences...';
						}

						if ($temp !== false)
						{
							$row['value'] = json_encode($temp);

							// Even though we have all values from the table, UPDATE is still faster than REPLACE
							$smcFunc['db_query']('', '
								UPDATE {db_prefix}themes
								SET value = {string:prefs}
								WHERE id_theme = {int:theme}
									AND id_member = {int:member}
									AND variable = {string:admin_prefs}',
								array(
									'prefs' => $row['value'],
									'theme' => $row['id_theme'],
									'member' => $row['id_member'],
									'admin_prefs' => 'admin_preferences'
								)
							);

							if ($command_line)
								echo ' done.';
						}
					}

					$smcFunc['db_free_result']($query);
				}
			}
			else
			{
				// First item is always the key...
				$key = $info[0];
				unset($info[0]);

				// Now we know what columns we have and such...
				if (count($info) == 2 && $info[2] === true)
				{
					$col_select = $info[1];
					$where = ' WHERE ' . $info[1] . ' != {empty}';
				}
				else
				{
					$col_select = implode(', ', $info);
				}

				$query = $smcFunc['db_query']('', '
					SELECT ' . $key . ', ' . $col_select . '
					FROM {db_prefix}' . $table . $where,
					array()
				);

				if ($smcFunc['db_num_rows']($query) != 0)
				{
					if ($command_line)
					{
						echo "\n" . ' +++ Fixing the "' . $table . '" table...';
						flush();
					}

					while ($row = $smcFunc['db_fetch_assoc']($query))
					{
						$update = '';

						// We already know what our key is...
						foreach ($info as $col)
						{
							if ($col !== true && $row[$col] != '')
							{
								$temp = @safe_unserialize($row[$col]);

								// Maybe we can fix the data?
								if ($temp === false)
									$temp = @safe_unserialize(fix_serialized_data($row[$col]));

								// Maybe the data is already JSON?
								if ($temp === false)
									$temp = smf_json_decode($row[$col], true, false);

								// Oh well...
								if ($temp === null)
								{
									$temp = array();

									if ($command_line)
										echo "\nFailed to unserialize " . $row[$col] . ". Setting to empty value.\n";
								}

								$row[$col] = json_encode($temp);

								// Build our SET string and variables array
								$update .= (empty($update) ? '' : ', ') . $col . ' = {string:' . $col . '}';
								$vars[$col] = $row[$col];
							}
						}

						$vars[$key] = $row[$key];

						// In a few cases, we might have empty data, so don't try to update in those situations...
						if (!empty($update))
						{
							$smcFunc['db_query']('', '
								UPDATE {db_prefix}' . $table . '
								SET ' . $update . '
								WHERE ' . $key . ' = {' . ($key == 'session' ? 'string' : 'int') . ':' . $key . '}',
								$vars
							);
						}
					}

					if ($command_line)
						echo ' done.';

					// Free up some memory...
					$smcFunc['db_free_result']($query);
				}
			}
			// If this is XML to keep it nice for the user do one table at a time anyway!
			if (isset($_GET['xml']))
				return upgradeExit();
		}

		if ($command_line)
		{
			echo "\n" . 'Successful.' . "\n";
			flush();
		}
		$upcontext['step_progress'] = 100;

		// Last but not least, insert a dummy setting so we don't have to do this again in the future...
		updateSettings(array('json_done' => true));

		$_GET['substep'] = 0;
		// Make sure we move on!
		if ($command_line)
			return ConvertUtf8();

		return true;
	}

	// If this fails we just move on to deleting the upgrade anyway...
	$_GET['substep'] = 0;
	return false;
}

/**
 * As of 2.1, we want to store db_last_error.php in the cache
 * To make that happen, Settings.php needs to ensure the $cachedir path is correct before trying to write to db_last_error.php
 */
function move_db_last_error_to_cachedir()
{
	$settings = file_get_contents(dirname(__FILE__) . '/Settings.php');

	$regex = <<<'EOT'
(\s*#\s*Make\s+sure\s+the\s+paths\s+are\s+correct\.\.\.\s+at\s+least\s+try\s+to\s+fix\s+them\.\s+)?if\s*\(\!file_exists\(\$boarddir\)\s+&&\s+file_exists\(dirname\(__FILE__\)\s+\.\s+'/agreement\.txt'\)\)\s+\$boarddir\s*\=\s*dirname\(__FILE__\);\s+if\s*\(\!file_exists\(\$sourcedir\)\s+&&\s+file_exists\(\$boarddir\s*\.\s*'/Sources'\)\)\s+\$sourcedir\s*\=\s*\$boarddir\s*\.\s*'/Sources';\s+if\s*\(\!file_exists\(\$cachedir\)\s+&&\s+file_exists\(\$boarddir\s*\.\s*'/cache'\)\)\s+\$cachedir\s*\=\s*\$boarddir\s*\.\s*'/cache';
EOT;

	$replacement = <<<'EOT'
# Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . '/agreement.txt'))
	$boarddir = dirname(__FILE__);
if (!file_exists($sourcedir) && file_exists($boarddir . '/Sources'))
	$sourcedir = $boarddir . '/Sources';
if (!file_exists($cachedir) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';


EOT;

	if (preg_match('~' . $regex . '~', $settings) && preg_match('~(#+\s*Error-Catching\s*#+)~', $settings))
	{
		$settings = preg_replace('~' . $regex . '~', '', $settings);
		$settings = preg_replace('~(#+\s*Error-Catching\s*#+)~', $replacement . '$1', $settings);
		$settings = preg_replace('~dirname(__FILE__) . \'/db_last_error.php\'~', '(isset($cachedir) ? $cachedir : dirname(__FILE__)) . \'/db_last_error.php\'', $settings);

		// Blank out the file - done to fix a oddity with some servers.
		file_put_contents(dirname(__FILE__) . '/Settings.php', '');

		file_put_contents(dirname(__FILE__) . '/Settings.php', $settings);
	}
}

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
						Templates are below this point
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

// This is what is displayed if there's any chmod to be done. If not it returns nothing...
function template_chmod()
{
	global $upcontext, $txt, $settings;

	// Don't call me twice!
	if (!empty($upcontext['chmod_called']))
		return;

	$upcontext['chmod_called'] = true;

	// Nothing?
	if (empty($upcontext['chmod']['files']) && empty($upcontext['chmod']['ftp_error']))
		return;

	// Was it a problem with Windows?
	if (!empty($upcontext['chmod']['ftp_error']) && $upcontext['chmod']['ftp_error'] == 'total_mess')
	{
		echo '
		<div class="error">
			<p>', $txt['upgrade_writable_files'], '</p>
			<ul class="error_content">
				<li>' . implode('</li>
				<li>', $upcontext['chmod']['files']) . '</li>
			</ul>
		</div>';

		return false;
	}

	echo '
		<div class="panel">
			<h2>', $txt['upgrade_ftp_login'], '</h2>
			<h3>', $txt['upgrade_ftp_perms'], '</h3>
			<script>
				function warning_popup()
				{
					popup = window.open(\'\',\'popup\',\'height=150,width=400,scrollbars=yes\');
					var content = popup.document;
					content.write(\'<!DOCTYPE html>\n\');
					content.write(\'<html', $txt['lang_rtl'] == true ? ' dir="rtl"' : '', '>\n\t<head>\n\t\t<meta name="robots" content="noindex">\n\t\t\');
					content.write(\'<title>', $txt['upgrade_ftp_warning'], '</title>\n\t\t<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/index.css">\n\t</head>\n\t<body id="popup">\n\t\t\');
					content.write(\'<div class="windowbg description">\n\t\t\t<h4>', $txt['upgrade_ftp_files'], '</h4>\n\t\t\t\');
					content.write(\'<p>', implode('<br>\n\t\t\t', $upcontext['chmod']['files']), '</p>\n\t\t\t\');';

	if (isset($upcontext['systemos']) && $upcontext['systemos'] == 'linux')
		echo '
					content.write(\'<hr>\n\t\t\t\');
					content.write(\'<p>', $txt['upgrade_ftp_shell'], '</p>\n\t\t\t\');
					content.write(\'<tt># chmod a+w ', implode(' ', $upcontext['chmod']['files']), '</tt>\n\t\t\t\');';

	echo '
					content.write(\'<a href="javascript:self.close();">close</a>\n\t\t</div>\n\t</body>\n</html>\');
					content.close();
				}
			</script>';

	if (!empty($upcontext['chmod']['ftp_error']))
		echo '
			<div class="error">
				<p>', $txt['upgrade_ftp_error'], '<p>
				<code>', $upcontext['chmod']['ftp_error'], '</code>
			</div>';

	if (empty($upcontext['chmod_in_form']))
		echo '
			<form action="', $upcontext['form_url'], '" method="post">';

	echo '
				<dl class="settings">
					<dt>
						<label for="ftp_server">', $txt['ftp_server'], ':</label>
					</dt>
					<dd>
						<div class="floatright">
							<label for="ftp_port" class="textbox"><strong>', $txt['ftp_port'], ':</strong></label>
							<input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($upcontext['chmod']['port']) ? $upcontext['chmod']['port'] : '21', '">
						</div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($upcontext['chmod']['server']) ? $upcontext['chmod']['server'] : 'localhost', '">
						<div class="smalltext">', $txt['ftp_server_info'], '</div>
					</dd>
					<dt>
						<label for="ftp_username">', $txt['ftp_username'], ':</label>
					</dt>
					<dd>
						<input type="text" size="30" name="ftp_username" id="ftp_username" value="', isset($upcontext['chmod']['username']) ? $upcontext['chmod']['username'] : '', '">
						<div class="smalltext">', $txt['ftp_username_info'], '</div>
					</dd>
					<dt>
						<label for="ftp_password">', $txt['ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="30" name="ftp_password" id="ftp_password">
						<div class="smalltext">', $txt['ftp_password_info'], '</div>
					</dd>
					<dt>
						<label for="ftp_path">', $txt['ftp_path'], ':</label>
					</dt>
					<dd>
						<input type="text" size="30" name="ftp_path" id="ftp_path" value="', isset($upcontext['chmod']['path']) ? $upcontext['chmod']['path'] : '', '">
						<div class="smalltext">', !empty($upcontext['chmod']['path']) ? $txt['ftp_path_found_info'] : $txt['ftp_path_info'], '</div>
					</dd>
				</dl>

				<div class="righttext buttons">
					<input type="submit" value="', $txt['ftp_connect'], '" class="button">
				</div>';

	if (empty($upcontext['chmod_in_form']))
		echo '
			</form>';

	echo '
		</div><!-- .panel -->';
}

function template_upgrade_above()
{
	global $modSettings, $txt, $settings, $upcontext, $upgradeurl;

	echo '<!DOCTYPE html>
<html', $txt['lang_rtl'] == true ? ' dir="rtl"' : '', '>
<head>
	<meta charset="', isset($txt['lang_character_set']) ? $txt['lang_character_set'] : 'UTF-8', '">
	<meta name="robots" content="noindex">
	<title>', $txt['upgrade_upgrade_utility'], '</title>
	<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/index.css">
	<link rel="stylesheet" href="', $settings['default_theme_url'], '/css/install.css">
	', $txt['lang_rtl'] == true ? '<link rel="stylesheet" href="' . $settings['default_theme_url'] . '/css/rtl.css">' : '', '
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
	<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
	<script>
		var smf_scripturl = \'', $upgradeurl, '\';
		var smf_charset = \'', (empty($modSettings['global_character_set']) ? (empty($txt['lang_character_set']) ? 'UTF-8' : $txt['lang_character_set']) : $modSettings['global_character_set']), '\';
		var startPercent = ', $upcontext['overall_percent'], ';

		// This function dynamically updates the step progress bar - and overall one as required.
		function updateStepProgress(current, max, overall_weight)
		{
			// What out the actual percent.
			var width = parseInt((current / max) * 100);
			if (document.getElementById(\'step_progress\'))
			{
				document.getElementById(\'step_progress\').style.width = width + "%";
				setInnerHTML(document.getElementById(\'step_text\'), width + "%");
			}
			if (overall_weight && document.getElementById(\'overall_progress\'))
			{
				overall_width = parseInt(startPercent + width * (overall_weight / 100));
				document.getElementById(\'overall_progress\').style.width = overall_width + "%";
				setInnerHTML(document.getElementById(\'overall_text\'), overall_width + "%");
			}
		}
	</script>
</head>
<body>
	<div id="footerfix">
	<div id="header">
		<h1 class="forumtitle">', $txt['upgrade_upgrade_utility'], '</h1>
		<img id="smflogo" src="', $settings['default_theme_url'], '/images/smflogo.svg" alt="Simple Machines Forum" title="Simple Machines Forum">
	</div>
	<div id="wrapper">
		<div id="content_section">
			<div id="main_content_section">
				<div id="main_steps">
					<h2>', $txt['upgrade_progress'], '</h2>
					<ul class="steps_list">';

	foreach ($upcontext['steps'] as $num => $step)
		echo '
						<li', $num == $upcontext['current_step'] ? ' class="stepcurrent"' : '', '>
							', $txt['upgrade_step'], ' ', $step[0], ': ', $txt[$step[1]], '
						</li>';

	echo '
					</ul>
				</div><!-- #main_steps -->

				<div id="install_progress">
					<div id="progress_bar" class="progress_bar progress_green">
						<h3>', $txt['upgrade_overall_progress'], '</h3>
						<div id="overall_progress" class="bar" style="width: ', $upcontext['overall_percent'], '%;"></div>
						<span id="overall_text">', $upcontext['overall_percent'], '%</span>
					</div>';

	if (isset($upcontext['step_progress']))
		echo '
					<div id="progress_bar_step" class="progress_bar progress_yellow">
						<h3>', $txt['upgrade_step_progress'], '</h3>
						<div id="step_progress" class="bar" style="width: ', $upcontext['step_progress'], '%;"></div>
						<span id="step_text">', $upcontext['step_progress'], '%</span>
					</div>';

	echo '
					<div id="substep_bar_div" class="progress_bar ', isset($upcontext['substep_progress']) ? '' : 'hidden', '">
						<h3 id="substep_name">', isset($upcontext['substep_progress_name']) ? trim(strtr($upcontext['substep_progress_name'], array('.' => ''))) : '', '</h3>
						<div id="substep_progress" class="bar" style="width: ', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : 0, '%;"></div>
						<span id="substep_text">', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : 0, '%</span>
					</div>';

	// How long have we been running this?
	$elapsed = time() - $upcontext['started'];
	$mins = (int) ($elapsed / 60);
	$seconds = $elapsed - $mins * 60;
	echo '
					<div class="smalltext time_elapsed">
						', $txt['upgrade_time_elapsed'], ':
						<span id="mins_elapsed">', $mins, '</span> ', $txt['upgrade_time_mins'], ', <span id="secs_elapsed">', $seconds, '</span> ', $txt['upgrade_time_secs'], '.
					</div>';
	echo '
				</div><!-- #install_progress -->
				<div id="main_screen" class="clear">
					<h2>', $upcontext['page_title'], '</h2>
					<div class="panel">';
}

function template_upgrade_below()
{
	global $upcontext, $txt;

	if (!empty($upcontext['pause']))
		echo '
							<em>', $txt['upgrade_incomplete'], '.</em><br>

							<h2 style="margin-top: 2ex;">', $txt['upgrade_not_quite_done'], '</h2>
							<h3>
								', $txt['upgrade_paused_overload'], '
							</h3>';

	if (!empty($upcontext['custom_warning']))
		echo '
							<div class="errorbox">
								<h3>', $txt['upgrade_note'], '</h3>
								', $upcontext['custom_warning'], '
							</div>';

	echo '
							<div class="righttext buttons">';

	if (!empty($upcontext['continue']))
		echo '
								<input type="submit" id="contbutt" name="contbutt" value="', $txt['upgrade_continue'], '"', $upcontext['continue'] == 2 ? ' disabled' : '', ' class="button">';
	if (!empty($upcontext['skip']))
		echo '
								<input type="submit" id="skip" name="skip" value="', $txt['upgrade_skip'], '" onclick="dontSubmit = true; document.getElementById(\'contbutt\').disabled = \'disabled\'; return true;" class="button">';

	echo '
							</div>
						</form>
					</div><!-- .panel -->
				</div><!-- #main_screen -->
			</div><!-- #main_content_section -->
		</div><!-- #content_section -->
	</div><!-- #wrapper -->
	</div><!-- #footerfix -->
	<div id="footer">
		<ul>
			<li class="copyright"><a href="https://www.simplemachines.org/" title="Simple Machines Forum" target="_blank" rel="noopener">SMF &copy; ' . SMF_SOFTWARE_YEAR . ', Simple Machines</a></li>
		</ul>
	</div>';

	// Are we on a pause?
	if (!empty($upcontext['pause']))
	{
		echo '
	<script>
		window.onload = doAutoSubmit;
		var countdown = 3;
		var dontSubmit = false;

		function doAutoSubmit()
		{
			if (countdown == 0 && !dontSubmit)
				document.upform.submit();
			else if (countdown == -1)
				return;

			document.getElementById(\'contbutt\').value = "', $txt['upgrade_continue'], ' (" + countdown + ")";
			countdown--;

			setTimeout("doAutoSubmit();", 1000);
		}
	</script>';
	}

	echo '
</body>
</html>';
}

function template_xml_above()
{
	global $upcontext;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
	<smf>';

	if (!empty($upcontext['get_data']))
		foreach ($upcontext['get_data'] as $k => $v)
			echo '
		<get key="', $k, '">', $v, '</get>';
}

function template_xml_below()
{
	echo '
	</smf>';
}

function template_error_message()
{
	global $upcontext;

	echo '
	<div class="error">
		', $upcontext['error_msg'], '
		<br>
		<a href="', $_SERVER['PHP_SELF'], '">Click here to try again.</a>
	</div>';
}

function template_welcome_message()
{
	global $upcontext, $disable_security, $settings, $txt;

	echo '
				<script src="https://www.simplemachines.org/smf/current-version.js?version=' . SMF_VERSION . '"></script>

				<h3>', sprintf($txt['upgrade_ready_proceed'], SMF_VERSION), '</h3>
				<form action="', $upcontext['form_url'], '" method="post" name="upform" id="upform">
					<input type="hidden" name="', $upcontext['login_token_var'], '" value="', $upcontext['login_token'], '">

					<div id="version_warning" class="noticebox hidden">
						<h3>', $txt['upgrade_warning'], '</h3>
						', sprintf($txt['upgrade_warning_out_of_date'], SMF_VERSION, 'https://www.simplemachines.org'), '
					</div>';

	$upcontext['chmod_in_form'] = true;
	template_chmod();

	// For large, pre 1.1 RC2 forums give them a warning about the possible impact of this upgrade!
	if ($upcontext['is_large_forum'])
		echo '
					<div class="errorbox">
						<h3>', $txt['upgrade_warning'], '</h3>
						', $txt['upgrade_warning_lots_data'], '
					</div>';

	// A warning message?
	if (!empty($upcontext['warning']))
		echo '
					<div class="errorbox">
						<h3>', $txt['upgrade_warning'], '</h3>
						', $upcontext['warning'], '
					</div>';

	// Paths are incorrect?
	echo '
					<div class="errorbox', (file_exists($settings['default_theme_dir'] . '/scripts/script.js') ? ' hidden' : ''), '" id="js_script_missing_error">
						<h3>', $txt['upgrade_critical_error'], '</h3>
						', sprintf($txt['upgrade_error_script_js'], 'https://www.simplemachines.org'), '
					</div>';

	// Is there someone already doing this?
	if (!empty($upcontext['user']['id']) && (time() - $upcontext['started'] < 72600 || time() - $upcontext['updated'] < 3600))
	{
		$ago = time() - $upcontext['started'];
		$ago_hours = floor($ago / 3600);
		$ago_minutes = intval(($ago / 60) % 60);
		$ago_seconds = intval($ago % 60);
		$agoTxt = $ago < 60 ? 'upgrade_time_ago_s' : ($ago < 3600 ? 'upgrade_time_ago_ms' : 'upgrade_time_ago_hms');

		$updated = time() - $upcontext['updated'];
		$updated_hours = floor($updated / 3600);
		$updated_minutes = intval(($updated / 60) % 60);
		$updated_seconds = intval($updated % 60);
		$updatedTxt = $updated < 60 ? 'upgrade_time_updated_s' : ($updated < 3600 ? 'upgrade_time_updated_hm' : 'upgrade_time_updated_hms');

		echo '
					<div class="errorbox">
						<h3>', $txt['upgrade_warning'], '</h3>
						<p>', sprintf($txt['upgrade_time_user'], $upcontext['user']['name']), '</p>
						<p>', sprintf($txt[$agoTxt], $ago_seconds, $ago_minutes, $ago_hours), '</p>
						<p>', sprintf($txt[$updatedTxt], $updated_seconds, $updated_minutes, $updated_hours), '</p>';

		if ($updated < 600)
			echo '
						<p>', $txt['upgrade_run_script'], ' ', $upcontext['user']['name'], ' ', $txt['upgrade_run_script2'], '</p>';

		if ($updated > $upcontext['inactive_timeout'])
			echo '
						<p>', $txt['upgrade_run'], '</p>';
		else
			echo '
						<p>', $txt['upgrade_script_timeout'], ' ', $upcontext['user']['name'], ' ', $txt['upgrade_script_timeout2'], ' ', ($upcontext['inactive_timeout'] > 120 ? round($upcontext['inactive_timeout'] / 60, 1) . ' minutes!' : $upcontext['inactive_timeout'] . ' seconds!'), '</p>';

		echo '
					</div>';
	}

	echo '
					<strong>', $txt['upgrade_admin_login'], ' ', $disable_security ? '(DISABLED)' : '', '</strong>
					<h3>', $txt['upgrade_sec_login'], '</h3>
					<dl class="settings adminlogin">
						<dt>
							<label for="user"', $disable_security ? ' disabled' : '', '>', $txt['upgrade_username'], '</label>
						</dt>
						<dd>
							<input type="text" name="user" value="', !empty($upcontext['username']) ? $upcontext['username'] : '', '"', $disable_security ? ' disabled' : '', '>';

	if (!empty($upcontext['username_incorrect']))
		echo '
							<div class="smalltext red">', $txt['upgrade_wrong_username'], '</div>';

	echo '
						</dd>
						<dt>
							<label for="passwrd"', $disable_security ? ' disabled' : '', '>', $txt['upgrade_password'], '</label>
						</dt>
						<dd>
							<input type="password" name="passwrd" value=""', $disable_security ? ' disabled' : '', '>
							<input type="hidden" name="hash_passwrd" value="">';

	if (!empty($upcontext['password_failed']))
		echo '
							<div class="smalltext red">', $txt['upgrade_wrong_password'], '</div>';

	echo '
						</dd>';

	// Can they continue?
	if (!empty($upcontext['user']['id']) && time() - $upcontext['user']['updated'] >= $upcontext['inactive_timeout'] && $upcontext['user']['step'] > 1)
	{
		echo '
						<dd>
							<label for="cont"><input type="checkbox" id="cont" name="cont" checked>', $txt['upgrade_continue_step'], '</label>
						</dd>';
	}

	echo '
					</dl>
					<span class="smalltext">
						', $txt['upgrade_bypass'], '
					</span>
					<input type="hidden" name="login_attempt" id="login_attempt" value="1">
					<input type="hidden" name="js_works" id="js_works" value="0">';

	// Say we want the continue button!
	$upcontext['continue'] = !empty($upcontext['user']['id']) && time() - $upcontext['user']['updated'] < $upcontext['inactive_timeout'] ? 2 : 1;

	// This defines whether javascript is going to work elsewhere :D
	echo '
					<script>
						if (\'XMLHttpRequest\' in window && document.getElementById(\'js_works\'))
							document.getElementById(\'js_works\').value = 1;

						// Latest version?
						function smfCurrentVersion()
						{
							var smfVer, yourVer;

							if (!(\'smfVersion\' in window))
								return;

							window.smfVersion = window.smfVersion.replace(/SMF\s?/g, \'\');

							smfVer = document.getElementById(\'smfVersion\');
							yourVer = document.getElementById(\'yourVersion\');

							setInnerHTML(smfVer, window.smfVersion);

							var currentVersion = getInnerHTML(yourVer);
							if (currentVersion < window.smfVersion)
								document.getElementById(\'version_warning\').classList.remove(\'hidden\');
						}
						addLoadEvent(smfCurrentVersion);

						// This checks that the script file even exists!
						if (typeof(smfSelectText) == \'undefined\')
							document.getElementById(\'js_script_missing_error\').classList.remove(\'hidden\');

					</script>';
}

function template_upgrade_options()
{
	global $upcontext, $modSettings, $db_prefix, $mmessage, $mtitle, $txt;

	echo '
				<h3>', $txt['upgrade_areyouready'], '</h3>
				<form action="', $upcontext['form_url'], '" method="post" name="upform" id="upform">';

	// Warning message?
	if (!empty($upcontext['upgrade_options_warning']))
		echo '
				<div class="errorbox">
					<h3>', $txt['upgrade_warning'], '</h3>
					', $upcontext['upgrade_options_warning'], '
				</div>';

	echo '
				<ul class="upgrade_settings">
					<li>
						<input type="checkbox" name="backup" id="backup" value="1">
						<label for="backup">', $txt['upgrade_backup_table'], ' &quot;backup_' . $db_prefix . '&quot;.</label>
						(', $txt['upgrade_recommended'], ')
					</li>
					<li>
						<input type="checkbox" name="maint" id="maint" value="1" checked>
						<label for="maint">', $txt['upgrade_maintenance'], '</label>
						<span class="smalltext">(<a href="javascript:void(0)" onclick="document.getElementById(\'mainmess\').classList.toggle(\'hidden\')">', $txt['upgrade_customize'], '</a>)</span>
						<div id="mainmess" class="hidden">
							<strong class="smalltext">', $txt['upgrade_maintenance_title'], ' </strong><br>
							<input type="text" name="maintitle" size="30" value="', htmlspecialchars($mtitle), '"><br>
							<strong class="smalltext">', $txt['upgrade_maintenance_message'], ' </strong><br>
							<textarea name="mainmessage" rows="3" cols="50">', htmlspecialchars($mmessage), '</textarea>
						</div>
					</li>
					<li>
						<input type="checkbox" name="debug" id="debug" value="1">
						<label for="debug">'.$txt['upgrade_debug_info'], '</label>
					</li>
					<li>
						<input type="checkbox" name="empty_error" id="empty_error" value="1">
						<label for="empty_error">', $txt['upgrade_empty_errorlog'], '</label>
					</li>';

	if (!empty($upcontext['karma_installed']['good']) || !empty($upcontext['karma_installed']['bad']))
		echo '
					<li>
						<input type="checkbox" name="delete_karma" id="delete_karma" value="1">
						<label for="delete_karma">', $txt['upgrade_delete_karma'], '</label>
					</li>';

	echo '
					<li>
						<input type="checkbox" name="stats" id="stats" value="1"', empty($modSettings['allow_sm_stats']) && empty($modSettings['enable_sm_stats']) ? '' : ' checked="checked"', '>
						<label for="stat">
							', $txt['upgrade_stats_collection'], '<br>
							<span class="smalltext">', sprintf($txt['upgrade_stats_info'], 'https://www.simplemachines.org/about/stats.php'), '</a></span>
						</label>
					</li>
					<li>
						<input type="checkbox" name="migrateSettings" id="migrateSettings" value="1"', empty($upcontext['migrateSettingsNeeded']) ? '' : ' checked="checked"', '>
						<label for="migrateSettings">
							', $txt['upgrade_migrate_settings_file'], '
						</label>
					</li>
				</ul>
				<input type="hidden" name="upcont" value="1">';

	// We need a normal continue button here!
	$upcontext['continue'] = 1;
}

// Template for the database backup tool/
function template_backup_database()
{
	global $upcontext, $support_js, $is_debug, $txt;

	echo '
				<h3>', $txt['upgrade_wait'], '</h3>';

	echo '
				<form action="', $upcontext['form_url'], '" name="upform" id="upform" method="post">
					<input type="hidden" name="backup_done" id="backup_done" value="0">
					<strong>', sprintf($txt['upgrade_completedtables_outof'], $upcontext['cur_table_num'], $upcontext['table_count']), '</strong>
					<div id="debug_section">
						<span id="debuginfo"></span>
					</div>';

	// Dont any tables so far?
	if (!empty($upcontext['previous_tables']))
		foreach ($upcontext['previous_tables'] as $table)
			echo '
					<br>', $txt['upgrade_completed_table'], ' &quot;', $table, '&quot;.';

	echo '
					<h3 id="current_tab">
						', $txt['upgrade_current_table'], ' &quot;<span id="current_table">', $upcontext['cur_table_name'], '</span>&quot;
					</h3>
					<p id="commess" class="', $upcontext['cur_table_num'] == $upcontext['table_count'] ? 'inline_block' : 'hidden', '">Backup Complete! Click Continue to Proceed.</p>';

	// Continue please!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
					<script>
						var lastTable = ', $upcontext['cur_table_num'], ';
						function getNextTables()
						{
							getXMLDocument(\'', $upcontext['form_url'], '&xml&substep=\' + lastTable, onBackupUpdate);
						}

						// Got an update!
						function onBackupUpdate(oXMLDoc)
						{
							var sCurrentTableName = "";
							var iTableNum = 0;
							var sCompletedTableName = getInnerHTML(document.getElementById(\'current_table\'));
							for (var i = 0; i < oXMLDoc.getElementsByTagName("table")[0].childNodes.length; i++)
								sCurrentTableName += oXMLDoc.getElementsByTagName("table")[0].childNodes[i].nodeValue;
							iTableNum = oXMLDoc.getElementsByTagName("table")[0].getAttribute("num");

							// Update the page.
							setInnerHTML(document.getElementById(\'tab_done\'), iTableNum);
							setInnerHTML(document.getElementById(\'current_table\'), sCurrentTableName);
							lastTable = iTableNum;
							updateStepProgress(iTableNum, ', $upcontext['table_count'], ', ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');';

		// If debug flood the screen.
		if ($is_debug)
			echo '
							setOuterHTML(document.getElementById(\'debuginfo\'), \'<br>Completed Table: &quot;\' + sCompletedTableName + \'&quot;.<span id="debuginfo"><\' + \'/span>\');

							if (document.getElementById(\'debug_section\').scrollHeight)
								document.getElementById(\'debug_section\').scrollTop = document.getElementById(\'debug_section\').scrollHeight';

		echo '
							// Get the next update...
							if (iTableNum == ', $upcontext['table_count'], ')
							{
								document.getElementById(\'commess\').classList.remove("hidden");
								document.getElementById(\'current_tab\').classList.add("hidden");
								document.getElementById(\'contbutt\').disabled = 0;
								document.getElementById(\'backup_done\').value = 1;
							}
							else
								getNextTables();
						}
						getNextTables();
					//# sourceURL=dynamicScript-bkup.js
					</script>';
	}
}

function template_backup_xml()
{
	global $upcontext;

	echo '
		<table num="', $upcontext['cur_table_num'], '">', $upcontext['cur_table_name'], '</table>';
}

// Here is the actual "make the changes" template!
function template_database_changes()
{
	global $upcontext, $support_js, $is_debug, $timeLimitThreshold, $txt;

	if (empty($is_debug) && !empty($upcontext['upgrade_status']['debug']))
		$is_debug = true;

	echo '
				<h3>', $txt['upgrade_db_changes'], '</h3>
				<h4><em>', $txt['upgrade_db_patient'], '</em></h4>';

	echo '
				<form action="', $upcontext['form_url'], '&amp;filecount=', $upcontext['file_count'], '" name="upform" id="upform" method="post">
					<input type="hidden" name="database_done" id="database_done" value="0">';

	// No javascript looks rubbish!
	if (!$support_js)
	{
		foreach ($upcontext['actioned_items'] as $num => $item)
		{
			if ($num != 0)
				echo ' Successful!';
			echo '<br>' . $item;
		}

		// Only tell deubbers how much time they wasted waiting for the upgrade because they don't have javascript.
		if (!empty($upcontext['changes_complete']))
		{
			if ($is_debug)
			{
				$active = time() - $upcontext['started'];
				$hours = floor($active / 3600);
				$minutes = intval(($active / 60) % 60);
				$seconds = intval($active % 60);

				echo '', sprintf($txt['upgrade_success_time_db'], $seconds, $minutes, $hours), '<br>';
			}
			else
				echo '', $txt['upgrade_success'], '<br>';

			echo '
					<p id="commess">', $txt['upgrade_db_complete'], '</p>';
		}
	}
	else
	{
		// Tell them how many files we have in total.
		if ($upcontext['file_count'] > 1)
			echo '
					<strong id="info1">', $txt['upgrade_script'], ' <span id="file_done">', $upcontext['cur_file_num'], '</span> of ', $upcontext['file_count'], '.</strong>';

		echo '
					<h3 id="info2">
						<strong>', $txt['upgrade_executing'], '</strong> &quot;<span id="cur_item_name">', $upcontext['current_item_name'], '</span>&quot; (<span id="item_num">', $upcontext['current_item_num'], '</span> ', $txt['upgrade_of'], ' <span id="total_items"><span id="item_count">', $upcontext['total_items'], '</span>', $upcontext['file_count'] > 1 ? ' - of this script' : '', ')</span>
					</h3>
					<p id="commess" class="', !empty($upcontext['changes_complete']) || $upcontext['current_debug_item_num'] == $upcontext['debug_items'] ? 'inline_block' : 'hidden', '">', $txt['upgrade_db_complete2'], '</p>';

		if ($is_debug)
		{
			// Let our debuggers know how much time was spent, but not wasted since JS handled refreshing the page!
			if ($upcontext['current_debug_item_num'] == $upcontext['debug_items'])
			{
				$active = time() - $upcontext['started'];
				$hours = floor($active / 3600);
				$minutes = intval(($active / 60) % 60);
				$seconds = intval($active % 60);

				echo '
					<p id="upgradeCompleted">', sprintf($txt['upgrade_success_time_db'], $seconds, $minutes, $hours), '</p>';
			}
			else
				echo '
					<p id="upgradeCompleted"></p>';

			echo '
					<div id="debug_section">
						<span id="debuginfo"></span>
					</div>';
		}
	}

	// Place for the XML error message.
	echo '
					<div id="error_block" class="errorbox', empty($upcontext['error_message']) ? ' hidden' : '', '">
						<h3>', $txt['upgrade_error'], '</h3>
						<div id="error_message">', isset($upcontext['error_message']) ? $upcontext['error_message'] : $txt['upgrade_unknown_error'], '</div>
					</div>';

	// We want to continue at some point!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
					<script>
						var lastItem = ', $upcontext['current_debug_item_num'], ';
						var sLastString = "', strtr($upcontext['current_debug_item_name'], array('"' => '&quot;')), '";
						var iLastSubStepProgress = -1;
						var curFile = ', $upcontext['cur_file_num'], ';
						var totalItems = 0;
						var prevFile = 0;
						var retryCount = 0;
						var testvar = 0;
						var timeOutID = 0;
						var getData = "";
						var debugItems = ', $upcontext['debug_items'], ';';

		if ($is_debug)
			echo '
						var upgradeStartTime = ' . $upcontext['started'] . ';';

		echo '
						function getNextItem()
						{
							// We want to track this...
							if (timeOutID)
								clearTimeout(timeOutID);
							timeOutID = window.setTimeout("retTimeout()", ', (10 * $timeLimitThreshold), '000);

							getXMLDocument(\'', $upcontext['form_url'], '&xml&filecount=', $upcontext['file_count'], '&substep=\' + lastItem + getData, onItemUpdate);
						}

						// Got an update!
						function onItemUpdate(oXMLDoc)
						{
							var sItemName = "";
							var sDebugName = "";
							var iItemNum = 0;
							var iSubStepProgress = -1;
							var iDebugNum = 0;
							var bIsComplete = 0;
							var bSkipped = 0;
							getData = "";

							// We\'ve got something - so reset the timeout!
							if (timeOutID)
								clearTimeout(timeOutID);

							// Assume no error at this time...
							document.getElementById("error_block").classList.add("hidden");

							// Are we getting some duff info?
							if (!oXMLDoc.getElementsByTagName("item")[0])
							{
								// Too many errors?
								if (retryCount > 15)
								{
									document.getElementById("error_block").classList.remove("hidden");
									setInnerHTML(document.getElementById("error_message"), "Error retrieving information on step: " + (sDebugName == "" ? sLastString : sDebugName));';

		if ($is_debug)
			echo '
									setOuterHTML(document.getElementById(\'debuginfo\'), \'<span class="red">failed<\' + \'/span><span id="debuginfo"><\' + \'/span>\');';

		echo '
								}
								else
								{
									retryCount++;
									getNextItem();
								}
								return false;
							}

							// Never allow loops.
							if (curFile == prevFile)
							{
								retryCount++;
								if (retryCount > 10)
								{
									document.getElementById("error_block").classList.remove("hidden");
									setInnerHTML(document.getElementById("error_message"), "', $txt['upgrade_loop'], '" + sDebugName);';

		if ($is_debug)
			echo '
									setOuterHTML(document.getElementById(\'debuginfo\'), \'<span class="red">failed<\' + \'/span><span id="debuginfo"><\' + \'/span>\');';

		echo '
								}
							}
							retryCount = 0;

							for (var i = 0; i < oXMLDoc.getElementsByTagName("item")[0].childNodes.length; i++)
								sItemName += oXMLDoc.getElementsByTagName("item")[0].childNodes[i].nodeValue;
							for (var i = 0; i < oXMLDoc.getElementsByTagName("debug")[0].childNodes.length; i++)
								sDebugName += oXMLDoc.getElementsByTagName("debug")[0].childNodes[i].nodeValue;
							for (var i = 0; i < oXMLDoc.getElementsByTagName("get").length; i++)
							{
								getData += "&" + oXMLDoc.getElementsByTagName("get")[i].getAttribute("key") + "=";
								for (var j = 0; j < oXMLDoc.getElementsByTagName("get")[i].childNodes.length; j++)
								{
									getData += oXMLDoc.getElementsByTagName("get")[i].childNodes[j].nodeValue;
								}
							}

							iItemNum = oXMLDoc.getElementsByTagName("item")[0].getAttribute("num");
							iDebugNum = parseInt(oXMLDoc.getElementsByTagName("debug")[0].getAttribute("num"));
							bIsComplete = parseInt(oXMLDoc.getElementsByTagName("debug")[0].getAttribute("complete"));
							bSkipped = parseInt(oXMLDoc.getElementsByTagName("debug")[0].getAttribute("skipped"));
							iSubStepProgress = parseFloat(oXMLDoc.getElementsByTagName("debug")[0].getAttribute("percent"));
							sLastString = sDebugName + " (Item: " + iDebugNum + ")";

							curFile = parseInt(oXMLDoc.getElementsByTagName("file")[0].getAttribute("num"));
							debugItems = parseInt(oXMLDoc.getElementsByTagName("file")[0].getAttribute("debug_items"));
							totalItems = parseInt(oXMLDoc.getElementsByTagName("file")[0].getAttribute("items"));

							// If we have an error we haven\'t completed!
							if (oXMLDoc.getElementsByTagName("error")[0] && bIsComplete)
								iDebugNum = lastItem;

							// Do we have the additional progress bar?
							if (iSubStepProgress != -1)
							{
								document.getElementById("substep_bar_div").classList.remove("hidden");
								document.getElementById("substep_progress").style.width = iSubStepProgress + "%";
								setInnerHTML(document.getElementById("substep_text"), iSubStepProgress + "%");
								setInnerHTML(document.getElementById("substep_name"), sDebugName.replace(/\./g, ""));
							}
							else
							{
								document.getElementById("substep_bar_div").classList.add("hidden");
							}

							// Move onto the next item?
							if (bIsComplete)
								lastItem = iDebugNum;
							else
								lastItem = iDebugNum - 1;

							// Are we finished?
							if (bIsComplete && iDebugNum == -1 && curFile >= ', $upcontext['file_count'], ')
							{';

		// Database Changes, tell us how much time we spen to do this.  If this gets updated via JS.
		if ($is_debug)
			echo '
								document.getElementById(\'debug_section\').classList.add("hidden");

								var upgradeFinishedTime = parseInt(oXMLDoc.getElementsByTagName("curtime")[0].childNodes[0].nodeValue);
								var diffTime = upgradeFinishedTime - upgradeStartTime;
								var diffHours = Math.floor(diffTime / 3600);
								var diffMinutes = parseInt((diffTime / 60) % 60);
								var diffSeconds = parseInt(diffTime % 60);

								var completedTxt = "', $txt['upgrade_success_time_db'], '";
console.log(completedTxt, upgradeFinishedTime, diffTime, diffHours, diffMinutes, diffSeconds);

								completedTxt = completedTxt.replace("%1$d", diffSeconds).replace("%2$d", diffMinutes).replace("%3$d", diffHours);
console.log(completedTxt, upgradeFinishedTime, diffTime, diffHours, diffMinutes, diffSeconds);
								setInnerHTML(document.getElementById("upgradeCompleted"), completedTxt);';

		echo '

								document.getElementById(\'commess\').classList.remove("hidden");
								document.getElementById(\'contbutt\').disabled = 0;
								document.getElementById(\'database_done\').value = 1;';

		if ($upcontext['file_count'] > 1)
			echo '
								document.getElementById(\'info1\').classList.add(\'hidden\');';

		echo '
								document.getElementById(\'info2\').classList.add(\'hidden\');
								updateStepProgress(100, 100, ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');
								return true;
							}
							// Was it the last step in the file?
							else if (bIsComplete && iDebugNum == -1)
							{
								lastItem = 0;
								prevFile = curFile;';

		if ($is_debug)
			echo '
								setOuterHTML(document.getElementById(\'debuginfo\'), \'Moving to next script file...done<br><span id="debuginfo"><\' + \'/span>\');';

		echo '
								getNextItem();
								return true;
							}';

		// If debug scroll the screen.
		if ($is_debug)
			echo '
							if (iLastSubStepProgress == -1)
							{
								// Give it consistent dots.
								dots = sDebugName.match(/\./g);
								numDots = dots ? dots.length : 0;
								for (var i = numDots; i < 3; i++)
									sDebugName += ".";
								setOuterHTML(document.getElementById(\'debuginfo\'), sDebugName + \'<span id="debuginfo"><\' + \'/span>\');
							}
							iLastSubStepProgress = iSubStepProgress;

							if (bIsComplete && bSkipped)
								setOuterHTML(document.getElementById(\'debuginfo\'), \'skipped<br><span id="debuginfo"><\' + \'/span>\');
							else if (bIsComplete)
								setOuterHTML(document.getElementById(\'debuginfo\'), \'done<br><span id="debuginfo"><\' + \'/span>\');
							else
								setOuterHTML(document.getElementById(\'debuginfo\'), \'...<span id="debuginfo"><\' + \'/span>\');

							if (document.getElementById(\'debug_section\').scrollHeight)
								document.getElementById(\'debug_section\').scrollTop = document.getElementById(\'debug_section\').scrollHeight';

		echo '
							// Update the page.
							setInnerHTML(document.getElementById(\'item_num\'), iItemNum);
							setInnerHTML(document.getElementById(\'cur_item_name\'), sItemName);';

		if ($upcontext['file_count'] > 1)
		{
			echo '
							setInnerHTML(document.getElementById(\'file_done\'), curFile);
							setInnerHTML(document.getElementById(\'item_count\'), totalItems);';
		}

		echo '
							// Is there an error?
							if (oXMLDoc.getElementsByTagName("error")[0])
							{
								var sErrorMsg = "";
								for (var i = 0; i < oXMLDoc.getElementsByTagName("error")[0].childNodes.length; i++)
									sErrorMsg += oXMLDoc.getElementsByTagName("error")[0].childNodes[i].nodeValue;
								document.getElementById("error_block").classList.remove("hidden");
								setInnerHTML(document.getElementById("error_message"), sErrorMsg);
								return false;
							}

							// Get the progress bar right.
							barTotal = debugItems * ', $upcontext['file_count'], ';
							barDone = (debugItems * (curFile - 1)) + lastItem;

							updateStepProgress(barDone, barTotal, ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');

							// Finally - update the time here as it shows the server is responding!
							curTime = new Date();
							iElapsed = (curTime.getTime() / 1000 - ', $upcontext['started'], ');
							mins = parseInt(iElapsed / 60);
							secs = parseInt(iElapsed - mins * 60);
							setInnerHTML(document.getElementById("mins_elapsed"), mins);
							setInnerHTML(document.getElementById("secs_elapsed"), secs);

							getNextItem();
							return true;
						}

						// What if we timeout?!
						function retTimeout(attemptAgain)
						{
							// Oh noes...
							if (!attemptAgain)
							{
								document.getElementById("error_block").classList.remove("hidden");
								setInnerHTML(document.getElementById("error_message"), "', sprintf($txt['upgrade_repondtime'], ($timeLimitThreshold * 10)), '" + "<a href=\"#\" onclick=\"retTimeout(true); return false;\">', $txt['upgrade_respondtime_clickhere'], '</a>");
							}
							else
							{
								document.getElementById("error_block").classList.add("hidden");
								getNextItem();
							}
						}';

		// Start things off assuming we've not errored.
		if (empty($upcontext['error_message']))
			echo '
						getNextItem();';

		echo '
					//# sourceURL=dynamicScript-dbch.js
					</script>';
	}
	return;
}

function template_database_xml()
{
	global $is_debug, $upcontext;

	echo '
	<file num="', $upcontext['cur_file_num'], '" items="', $upcontext['total_items'], '" debug_items="', $upcontext['debug_items'], '">', $upcontext['cur_file_name'], '</file>
	<item num="', $upcontext['current_item_num'], '">', $upcontext['current_item_name'], '</item>
	<debug num="', $upcontext['current_debug_item_num'], '" percent="', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : '-1', '" complete="', empty($upcontext['completed_step']) ? 0 : 1, '" skipped="', empty($upcontext['skip_db_substeps']) ? 0 : 1, '">', $upcontext['current_debug_item_name'], '</debug>';

	if (!empty($upcontext['error_message']))
		echo '
	<error>', $upcontext['error_message'], '</error>';

	if (!empty($upcontext['error_string']))
		echo '
	<sql>', $upcontext['error_string'], '</sql>';

	if ($is_debug)
		echo '
	<curtime>', time(), '</curtime>';
}

// Template for the UTF-8 conversion step. Basically a copy of the backup stuff with slight modifications....
function template_convert_utf8()
{
	global $upcontext, $support_js, $is_debug, $txt;

	echo '
				<h3>', $txt['upgrade_wait2'], '</h3>
				<form action="', $upcontext['form_url'], '" name="upform" id="upform" method="post">
					<input type="hidden" name="utf8_done" id="utf8_done" value="0">
					<strong>', $txt['upgrade_completed'], ' <span id="tab_done">', $upcontext['cur_table_num'], '</span> ', $txt['upgrade_outof'], ' ', $upcontext['table_count'], ' ', $txt['upgrade_tables'], '</strong>
					<div id="debug_section">
						<span id="debuginfo"></span>
					</div>';

	// Done any tables so far?
	if (!empty($upcontext['previous_tables']))
		foreach ($upcontext['previous_tables'] as $table)
			echo '
					<br>', $txt['upgrade_completed_table'], ' &quot;', $table, '&quot;.';

	echo '
					<h3 id="current_tab">
						', $txt['upgrade_current_table'], ' &quot;<span id="current_table">', $upcontext['cur_table_name'], '</span>&quot;
					</h3>';

	// If we dropped their index, let's let them know
	if ($upcontext['dropping_index'])
		echo '
					<p id="indexmsg" class="', $upcontext['cur_table_num'] == $upcontext['table_count'] ? 'inline_block' : 'hidden', '>', $txt['upgrade_fulltext'], '</p>';

	// Completion notification
	echo '
					<p id="commess" class="', $upcontext['cur_table_num'] == $upcontext['table_count'] ? 'inline_block' : 'hidden', '">', $txt['upgrade_conversion_proceed'], '</p>';

	// Continue please!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
					<script>
						var lastTable = ', $upcontext['cur_table_num'], ';
						function getNextTables()
						{
							getXMLDocument(\'', $upcontext['form_url'], '&xml&substep=\' + lastTable, onConversionUpdate);
						}

						// Got an update!
						function onConversionUpdate(oXMLDoc)
						{
							var sCurrentTableName = "";
							var iTableNum = 0;
							var sCompletedTableName = getInnerHTML(document.getElementById(\'current_table\'));
							for (var i = 0; i < oXMLDoc.getElementsByTagName("table")[0].childNodes.length; i++)
								sCurrentTableName += oXMLDoc.getElementsByTagName("table")[0].childNodes[i].nodeValue;
							iTableNum = oXMLDoc.getElementsByTagName("table")[0].getAttribute("num");

							// Update the page.
							setInnerHTML(document.getElementById(\'tab_done\'), iTableNum);
							setInnerHTML(document.getElementById(\'current_table\'), sCurrentTableName);
							lastTable = iTableNum;
							updateStepProgress(iTableNum, ', $upcontext['table_count'], ', ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');';

		// If debug flood the screen.
		if ($is_debug)
			echo '
						setOuterHTML(document.getElementById(\'debuginfo\'), \'<br>Completed Table: &quot;\' + sCompletedTableName + \'&quot;.<span id="debuginfo"><\' + \'/span>\');

						if (document.getElementById(\'debug_section\').scrollHeight)
							document.getElementById(\'debug_section\').scrollTop = document.getElementById(\'debug_section\').scrollHeight';

		echo '
						// Get the next update...
						if (iTableNum == ', $upcontext['table_count'], ')
						{
							document.getElementById(\'commess\').classList.remove(\'hidden\');
							if (document.getElementById(\'indexmsg\') != null) {
								document.getElementById(\'indexmsg\').classList.remove(\'hidden\');
							}
							document.getElementById(\'current_tab\').classList.add(\'hidden\');
							document.getElementById(\'contbutt\').disabled = 0;
							document.getElementById(\'utf8_done\').value = 1;
						}
						else
							getNextTables();
					}
					getNextTables();
				//# sourceURL=dynamicScript-conv.js
				</script>';
	}
}

function template_convert_xml()
{
	global $upcontext;

	echo '
	<table num="', $upcontext['cur_table_num'], '">', $upcontext['cur_table_name'], '</table>';
}

// Template for the database backup tool/
function template_serialize_json()
{
	global $upcontext, $support_js, $is_debug, $txt;

	echo '
				<h3>', $txt['upgrade_convert_datajson'], '</h3>
				<form action="', $upcontext['form_url'], '" name="upform" id="upform" method="post">
					<input type="hidden" name="json_done" id="json_done" value="0">
					<strong>', $txt['upgrade_completed'], ' <span id="tab_done">', $upcontext['cur_table_num'], '</span> ', $txt['upgrade_outof'], ' ', $upcontext['table_count'], ' ', $txt['upgrade_tables'], '</strong>
					<div id="debug_section">
						<span id="debuginfo"></span>
					</div>';

	// Dont any tables so far?
	if (!empty($upcontext['previous_tables']))
		foreach ($upcontext['previous_tables'] as $table)
			echo '
					<br>', $txt['upgrade_completed_table'], ' &quot;', $table, '&quot;.';

	echo '
					<h3 id="current_tab">
						', $txt['upgrade_current_table'], ' &quot;<span id="current_table">', $upcontext['cur_table_name'], '</span>&quot;
					</h3>
					<p id="commess" class="', $upcontext['cur_table_num'] == $upcontext['table_count'] ? 'inline_block' : 'hidden', '">', $txt['upgrade_json_completed'], '</p>';

	// Try to make sure substep was reset.
	if ($upcontext['cur_table_num'] == $upcontext['table_count'])
		echo '
					<input type="hidden" name="substep" id="substep" value="0">';

	// Continue please!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
					<script>
						var lastTable = ', $upcontext['cur_table_num'], ';
						function getNextTables()
						{
							getXMLDocument(\'', $upcontext['form_url'], '&xml&substep=\' + lastTable, onBackupUpdate);
						}

						// Got an update!
						function onBackupUpdate(oXMLDoc)
						{
							var sCurrentTableName = "";
							var iTableNum = 0;
							var sCompletedTableName = getInnerHTML(document.getElementById(\'current_table\'));
							for (var i = 0; i < oXMLDoc.getElementsByTagName("table")[0].childNodes.length; i++)
								sCurrentTableName += oXMLDoc.getElementsByTagName("table")[0].childNodes[i].nodeValue;
							iTableNum = oXMLDoc.getElementsByTagName("table")[0].getAttribute("num");

							// Update the page.
							setInnerHTML(document.getElementById(\'tab_done\'), iTableNum);
							setInnerHTML(document.getElementById(\'current_table\'), sCurrentTableName);
							lastTable = iTableNum;
							updateStepProgress(iTableNum, ', $upcontext['table_count'], ', ', $upcontext['step_weight'] * ((100 - $upcontext['step_progress']) / 100), ');';

		// If debug flood the screen.
		if ($is_debug)
			echo '
							setOuterHTML(document.getElementById(\'debuginfo\'), \'<br>', $txt['upgrade_completed_table'], ' &quot;\' + sCompletedTableName + \'&quot;.<span id="debuginfo"><\' + \'/span>\');

							if (document.getElementById(\'debug_section\').scrollHeight)
								document.getElementById(\'debug_section\').scrollTop = document.getElementById(\'debug_section\').scrollHeight';

		echo '
							// Get the next update...
							if (iTableNum == ', $upcontext['table_count'], ')
							{
								document.getElementById(\'commess\').classList.remove("hidden");
								document.getElementById(\'current_tab\').classList.add("hidden");
								document.getElementById(\'contbutt\').disabled = 0;
								document.getElementById(\'json_done\').value = 1;
							}
							else
								getNextTables();
						}
						getNextTables();
					//# sourceURL=dynamicScript-json.js
					</script>';
	}
}

function template_serialize_json_xml()
{
	global $upcontext;

	echo '
	<table num="', $upcontext['cur_table_num'], '">', $upcontext['cur_table_name'], '</table>';
}

function template_upgrade_complete()
{
	global $upcontext, $upgradeurl, $settings, $boardurl, $is_debug, $txt;

	echo '
				<h3>', $txt['upgrade_done'], ' <a href="', $boardurl, '/index.php">', $txt['upgrade_done2'], '</a>.  ', $txt['upgrade_done3'], '</h3>
				<form action="', $boardurl, '/index.php">';

	if (!empty($upcontext['can_delete_script']))
		echo '
					<label>
						<input type="checkbox" id="delete_self" onclick="doTheDelete(this);"> ', $txt['upgrade_delete_now'], '
					</label>
					<em>', $txt['upgrade_delete_server'], '</em>
					<script>
						function doTheDelete(theCheck)
						{
							var theImage = document.getElementById ? document.getElementById("delete_upgrader") : document.all.delete_upgrader;
							theImage.src = "', $upgradeurl, '?delete=1&ts_" + (new Date().getTime());
							theCheck.disabled = true;
						}
					</script>
					<img src="', $settings['default_theme_url'], '/images/blank.png" alt="" id="delete_upgrader"><br>';

	// Show Upgrade time in debug mode when we completed the upgrade process totally
	if ($is_debug)
	{
		$active = time() - $upcontext['started'];
		$hours = floor($active / 3600);
		$minutes = intval(($active / 60) % 60);
		$seconds = intval($active % 60);

		if ($hours > 0)
			echo '', sprintf($txt['upgrade_completed_time_hms'], $seconds, $minutes, $hours), '';
		elseif ($minutes > 0)
			echo '', sprintf($txt['upgrade_completed_time_ms'], $seconds, $minutes), '';
		elseif ($seconds > 0)
			echo '', sprintf($txt['upgrade_completed_time_s'], $seconds), '';
	}

	echo '
					<p>
						', sprintf($txt['upgrade_problems'], 'http://simplemachines.org'), '
						<br>
						', $txt['upgrade_luck'], '<br>
						Simple Machines
					</p>';
}

/**
 * Convert MySQL (var)char ip col to binary
 *
 * @param string $targetTable The table to perform the operation on
 * @param string $oldCol The old column to gather data from
 * @param string $newCol The new column to put data in
 * @param int $limit The amount of entries to handle at once.
 * @param int $setSize The amount of entries after which to update the database.
 *
 * newCol needs to be a varbinary(16) null able field
 * @return bool
 */
function MySQLConvertOldIp($targetTable, $oldCol, $newCol, $limit = 50000, $setSize = 100)
{
	global $smcFunc, $step_progress;

	$current_substep = $_GET['substep'];

	if (empty($_GET['a']))
		$_GET['a'] = 0;
	$step_progress['name'] = 'Converting ips';
	$step_progress['current'] = $_GET['a'];

	// Skip this if we don't have the column
	$request = $smcFunc['db_query']('', '
		SHOW FIELDS
		FROM {db_prefix}{raw:table}
		WHERE Field = {string:name}',
		array(
			'table' => $targetTable,
			'name' => $oldCol,
		)
	);
	if ($smcFunc['db_num_rows']($request) !== 1)
	{
		$smcFunc['db_free_result']($request);
		return;
	}
	$smcFunc['db_free_result']($request);

	$is_done = false;
	while (!$is_done)
	{
		// Keep looping at the current step.
		nextSubstep($current_substep);

		// mysql default max length is 1mb https://dev.mysql.com/doc/refman/5.1/en/packet-too-large.html
		$arIp = array();

		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT {raw:old_col}
			FROM {db_prefix}{raw:table_name}
			WHERE {raw:new_col} IS NULL AND
				{raw:old_col} != {string:unknown} AND
				{raw:old_col} != {string:empty}
			LIMIT {int:limit}',
			array(
				'old_col' => $oldCol,
				'new_col' => $newCol,
				'table_name' => $targetTable,
				'empty' => '',
				'limit' => $limit,
				'unknown' => 'unknown',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$arIp[] = $row[$oldCol];

		$smcFunc['db_free_result']($request);

		// Special case, null ip could keep us in a loop.
		if (is_null($arIp[0]))
			unset($arIp[0]);

		if (empty($arIp))
			$is_done = true;

		$updates = array();
		$cases = array();
		$count = count($arIp);
		for ($i = 0; $i < $count; $i++)
		{
			$arIp[$i] = trim($arIp[$i]);

			if (empty($arIp[$i]))
				continue;

			$updates['ip' . $i] = $arIp[$i];
			$cases[$arIp[$i]] = 'WHEN ' . $oldCol . ' = {string:ip' . $i . '} THEN {inet:ip' . $i . '}';

			if ($setSize > 0 && $i % $setSize === 0)
			{
				if (count($updates) == 1)
					continue;

				$updates['whereSet'] = array_values($updates);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}' . $targetTable . '
					SET ' . $newCol . ' = CASE ' .
					implode('
						', $cases) . '
						ELSE NULL
					END
					WHERE ' . $oldCol . ' IN ({array_string:whereSet})',
					$updates
				);

				$updates = array();
				$cases = array();
			}
		}

		// Incase some extras made it through.
		if (!empty($updates))
		{
			if (count($updates) == 1)
			{
				foreach ($updates as $key => $ip)
				{
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}' . $targetTable . '
						SET ' . $newCol . ' = {inet:ip}
						WHERE ' . $oldCol . ' = {string:ip}',
						array(
							'ip' => $ip
						)
					);
				}
			}
			else
			{
				$updates['whereSet'] = array_values($updates);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}' . $targetTable . '
					SET ' . $newCol . ' = CASE ' .
					implode('
						', $cases) . '
						ELSE NULL
					END
					WHERE ' . $oldCol . ' IN ({array_string:whereSet})',
					$updates
				);
			}
		}
		else
			$is_done = true;

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];
	}

	unset($_GET['a']);
}

/**
 * Get the column info. This is basically the same as smf_db_list_columns but we get 1 column, force detail and other checks.
 *
 * @param string $targetTable The table to perform the operation on
 * @param string $column The column we are looking for.
 *
 * @return array Info on the table.
 */
function upgradeGetColumnInfo($targetTable, $column)
{
	global $smcFunc;

	// This should already be here, but be safe.
	db_extend('packages');

	$columns = $smcFunc['db_list_columns']($targetTable, true);

	if (isset($columns[$column]))
		return $columns[$column];
	else
		return null;
}

/**
 * Takes the changes to be made during the upgradeOptions step, grabs all known Settings data from Settings.php, then runs
 * through a process to rebuild onto a brand new Settings template.  This should only be done if detection believes the
 * settings file isn't using any advanced configuration setups in the Settings.php file.  A copy is made as Settings_org.php
 * to preserve all changes prior to migration.
 *
 * @param array $config_vars An array of one or more variables to update
 *
 * @return void We either successfully update the Settings file, or throw a error here.
 */
function migrateSettingsFile($changes)
{
	global $boarddir, $cachedir, $txt;

	// Try to find all of these settings.
	$settingsVars = array(
		'maintenance' => 'int',
		'mtitle' => 'string',
		'mmessage' => 'string',
		'mbname' => 'string',
		'language' => 'string',
		'boardurl' => 'string',
		'webmaster_email' => 'string',
		'cookiename' => 'string',
		'db_type' => 'string',
		'db_port' => 'int',
		'db_server' => 'string_fatal',
		'db_name' => 'string_fatal',
		'db_user' => 'string_fatal',
		'db_passwd' => 'string_fatal',
		'ssi_db_user' => 'string',
		'ssi_db_passwd' => 'string',
		'db_prefix' => 'string_fatal',
		'db_persist' => 'int',
		'db_error_send' => 'int',
		'db_mb4' => 'null',
		'cache_accelerator' => 'string',
		'cache_enable' => 'int',
		'cache_memcached' => 'string',
		'cachedir' => 'string',
		'image_proxy_enabled' => 'bool',
		'image_proxy_secret' => 'string',
		'image_proxy_maxsize' => 'int',
		'boarddir' => 'string',
		'sourcedir' => 'string',
		'packagesdir' => 'string',
		'tasksdir' => 'string',
		'db_character_set' => 'string',
	);

	// The Settings file, in an array as if it was handled by updateSettingsFile
	$settingsArray = array(
		'<' . '?' . 'php',
		'',
		'/**',
		' * The settings file contains all of the basic settings that need to be present when a database/cache is not available.',
		' *',
		' * Simple Machines Forum (SMF)',
		' *',
		' * @package SMF',
		' * @author Simple Machines http://www.simplemachines.org',
		' * @copyright ' . SMF_SOFTWARE_YEAR . ' Simple Machines and individual contributors',
		' * @license http://www.simplemachines.org/about/smf/license.php BSD',
		' *',
		' * @version ' . SMF_VERSION,
		' */',
		'',
		'########## Maintenance ##########',
		'/**',
		' * The maintenance "mode"',
		' * Set to 1 to enable Maintenance Mode, 2 to make the forum untouchable. (you\'ll have to make it 0 again manually!)',
		' * 0 is default and disables maintenance mode.',
		' * @var int 0, 1, 2',
		' * @global int $maintenance',
		' */',
		'$maintenance = 0;',
		'/**',
		' * Title for the Maintenance Mode message.',
		' * @var string',
		' * @global int $mtitle',
		' */',
		'$mtitle = \'Maintenance Mode\';',
		'/**',
		' * Description of why the forum is in maintenance mode.',
		' * @var string',
		' * @global string $mmessage',
		' */',
		'$mmessage = \'Okay faithful users...we\\\'re attempting to restore an older backup of the database...news will be posted once we\\\'re back!\';',
		'',
		'########## Forum Info ##########',
		'/**',
		' * The name of your forum.',
		' * @var string',
		' */',
		'$mbname = \'My Community\';',
		'/**',
		' * The default language file set for the forum.',
		' * @var string',
		' */',
		'$language = \'english\';',
		'/**',
		' * URL to your forum\'s folder. (without the trailing /!)',
		' * @var string',
		' */',
		'$boardurl = \'http://127.0.0.1/smf\';',
		'/**',
		' * Email address to send emails from. (like noreply@yourdomain.com.)',
		' * @var string',
		' */',
		'$webmaster_email = \'noreply@myserver.com\';',
		'/**',
		' * Name of the cookie to set for authentication.',
		' * @var string',
		' */',
		'$cookiename = \'SMFCookie21\';',
		'',
		'########## Database Info ##########',
		'/**',
		' * The database type',
		' * Default options: mysql, postgresql',
		' * @var string',
		' */',
		'$db_type = \'mysql\';',
		'/**',
		' * The database port',
		' * 0 to use default port for the database type',
		' * @var int',
		' */',
		'$db_port = 0;',
		'/**',
		' * The server to connect to (or a Unix socket)',
		' * @var string',
		' */',
		'$db_server = \'localhost\';',
		'/**',
		' * The database name',
		' * @var string',
		' */',
		'$db_name = \'smf\';',
		'/**',
		' * Database username',
		' * @var string',
		' */',
		'$db_user = \'root\';',
		'/**',
		' * Database password',
		' * @var string',
		' */',
		'$db_passwd = \'\';',
		'/**',
		' * Database user for when connecting with SSI',
		' * @var string',
		' */',
		'$ssi_db_user = \'\';',
		'/**',
		' * Database password for when connecting with SSI',
		' * @var string',
		' */',
		'$ssi_db_passwd = \'\';',
		'/**',
		' * A prefix to put in front of your table names.',
		' * This helps to prevent conflicts',
		' * @var string',
		' */',
		'$db_prefix = \'smf_\';',
		'/**',
		' * Use a persistent database connection',
		' * @var int|bool',
		' */',
		'$db_persist = 0;',
		'/**',
		' *',
		' * @var int|bool',
		' */',
		'$db_error_send = 0;',
		'/**',
		' * Override the default behavior of the database layer for mb4 handling',
		' * null keep the default behavior untouched',
		' * @var null|bool',
		' */',
		'$db_mb4 = null;',
		'',
		'########## Cache Info ##########',
		'/**',
		' * Select a cache system. You want to leave this up to the cache area of the admin panel for',
		' * proper detection of apc, memcached, output_cache, smf, or xcache',
		' * (you can add more with a mod).',
		' * @var string',
		' */',
		'$cache_accelerator = \'\';',
		'/**',
		' * The level at which you would like to cache. Between 0 (off) through 3 (cache a lot).',
		' * @var int',
		' */',
		'$cache_enable = 0;',
		'/**',
		' * This is only used for memcache / memcached. Should be a string of \'server:port,server:port\'',
		' * @var array',
		' */',
		'$cache_memcached = \'\';',
		'/**',
		' * This is only for the \'smf\' file cache system. It is the path to the cache directory.',
		' * It is also recommended that you place this in /tmp/ if you are going to use this.',
		' * @var string',
		' */',
		'$cachedir = dirname(__FILE__) . \'/cache\';',
		'',
		'########## Image Proxy ##########',
		'# This is done entirely in Settings.php to avoid loading the DB while serving the images',
		'/**',
		' * Whether the proxy is enabled or not',
		' * @var bool',
		' */',
		'$image_proxy_enabled = true;',
		'',
		'/**',
		' * Secret key to be used by the proxy',
		' * @var string',
		' */',
		'$image_proxy_secret = \'smfisawesome\';',
		'',
		'/**',
		' * Maximum file size (in KB) for individual files',
		' * @var int',
		' */',
		'$image_proxy_maxsize = 5192;',
		'',
		'########## Directories/Files ##########',
		'# Note: These directories do not have to be changed unless you move things.',
		'/**',
		' * The absolute path to the forum\'s folder. (not just \'.\'!)',
		' * @var string',
		' */',
		'$boarddir = dirname(__FILE__);',
		'/**',
		' * Path to the Sources directory.',
		' * @var string',
		' */',
		'$sourcedir = dirname(__FILE__) . \'/Sources\';',
		'/**',
		' * Path to the Packages directory.',
		' * @var string',
		' */',
		'$packagesdir = dirname(__FILE__) . \'/Packages\';',
		'/**',
		' * Path to the tasks directory.',
		' * @var string',
		' */',
		'$tasksdir = $sourcedir . \'/tasks\';',
		'',
		'# Make sure the paths are correct... at least try to fix them.',
		'if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . \'/agreement.txt\'))',
		'	$boarddir = dirname(__FILE__);',
		'if (!file_exists($sourcedir) && file_exists($boarddir . \'/Sources\'))',
		'	$sourcedir = $boarddir . \'/Sources\';',
		'if (!file_exists($cachedir) && file_exists($boarddir . \'/cache\'))',
		'	$cachedir = $boarddir . \'/cache\';',
		'',
		'######### Legacy Settings #########',
		'# UTF-8 is now the only character set supported in 2.1.',
		'$db_character_set = \'utf8\';',
		'',
		'########## Error-Catching ##########',
		'# Note: You shouldn\'t touch these settings.',
		'if (file_exists((isset($cachedir) ? $cachedir : dirname(__FILE__)) . \'/db_last_error.php\'))',
		'	include((isset($cachedir) ? $cachedir : dirname(__FILE__)) . \'/db_last_error.php\');',
		'',
		'if (!isset($db_last_error))',
		'{',
		'	// File does not exist so lets try to create it',
		'	file_put_contents((isset($cachedir) ? $cachedir : dirname(__FILE__)) . \'/db_last_error.php\', \'<\' . \'?\' . "php\n" . \'$db_last_error = 0;\' . "\n" . \'?\' . \'>\');',
		'	$db_last_error = 0;',
		'}',
		'',
		'?' . '>',
	);

	// Now, find all of the original settings.  Mark those for the "change".
	$original = array();
	foreach ($settingsVars as $setVar => $setType)
	{
		global $$setVar;

		// Find the setting.
		if ($setType == 'string' || $setType == 'string_fatal')
			$original[$setVar] = isset($$setVar) ? '\'' . addcslashes($$setVar, '\'\\') . '\'' : (strpos('fatal', $setType) ? null : '\'\'');
		elseif ($setType == 'int' || $setType == 'int_fatal')
			$original[$setVar] = isset($$setVar) ? (int) $$setVar : (strpos('fatal', $setType) ? null : 0);
		elseif ($setType == 'bool' || $setType == 'bool_fatal')
			$original[$setVar] = isset($$setVar) && in_array($$setVar, array(1, true)) ? 'true' : (strpos('fatal', $setType) ? null : 'false');
		elseif ($setType == 'null' || $setType == 'null_fatal')
			$original[$setVar] = isset($$setVar) && in_array($$setVar, array(1, true)) ? 'true' : (strpos('fatal', $setType) ? null : 'null');

		// Well this isn't good.  Do we fix it or bail?
		if (is_null($original) && $setType != 'null' && strpos('fatal', $setType) > -1)
			return throw_error(sprintf($txt['error_settings_migration_no_var'], $setVar));
	}

	// Finally, merge the changes with the new ones.
	$config_vars = $original;
	foreach ($changes as $setVar => $value)
	{
		// Nothing needed here.
		if ($setVar != 'upgradeData' && $config_vars[$setVar] == $changes[$setVar])
			continue;

		$config_vars[$setVar] = $value;
	}

	/*
		It would be nice to call updateSettingsFile and be done with this. However the function doesn't support passing in the entire file. We also want to backup with a different name, just incase.
	*/

	// When was Settings.php last changed?
	$last_settings_change = filemtime($boarddir . '/Settings.php');

	// remove any \r's that made their way in here
	foreach ($settingsArray as $k => $dummy)
		$settingsArray[$k] = strtr($dummy, array("\r" => '')) . "\n";

	// go line by line and see whats changing
	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		// Don't trim or bother with it if it's not a variable.
		if (substr($settingsArray[$i], 0, 1) != '$')
			continue;

		$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

		// Look through the variables to set....
		foreach ($config_vars as $var => $val)
		{
			// be sure someone is not updating db_last_error this with a group
			if ($var === 'db_last_error')
				unset($config_vars[$var]);
			elseif (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
			{
				$comment = strstr(substr($settingsArray[$i], strpos($settingsArray[$i], ';')), '#');
				$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment == '' ? '' : "\t\t" . rtrim($comment)) . "\n";

				// This one's been 'used', so to speak.
				unset($config_vars[$var]);
			}
		}

		// End of the file ... maybe
		if (substr(trim($settingsArray[$i]), 0, 2) == '?' . '>')
			$end = $i;
	}

	// This should never happen, but apparently it is happening.
	if (empty($end) || $end < 10)
		$end = count($settingsArray) - 1;

	// Still more variables to go?  Then lets add them at the end.
	if (!empty($config_vars))
	{
		if (trim($settingsArray[$end]) == '?' . '>')
			$settingsArray[$end++] = '';
		else
			$end++;

		// Add in any newly defined vars that were passed
		foreach ($config_vars as $var => $val)
			$settingsArray[$end++] = '$' . $var . ' = ' . $val . ';' . "\n";

		$settingsArray[$end] = '?' . '>';
	}
	else
		$settingsArray[$end] = trim($settingsArray[$end]);

	// Sanity error checking: the file needs to be at least 12 lines.
	if (count($settingsArray) < 12)
		return throw_error($txt['error_settings_migration_too_short']);

	// Try to avoid a few pitfalls:
	//  - like a possible race condition,
	//  - or a failure to write at low diskspace
	//
	// Check before you act: if cache is enabled, we can do a simple write test
	// to validate that we even write things on this filesystem.
	if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
		$cachedir = $boarddir . '/cache';

	$test_fp = @fopen($cachedir . '/settings_update.tmp', "w+");
	if ($test_fp !== false)
	{
		fclose($test_fp);
		$written_bytes = file_put_contents($cachedir . '/settings_update.tmp', 'test', LOCK_EX);
		@unlink($cachedir . '/settings_update.tmp');

		// Oops. Low disk space, perhaps. Don't mess with Settings.php then.
		// No means no. :P
		if ($written_bytes !== 4)
			return throw_error($txt['error_settings_migration_write_failed']);
	}

	// Protect me from what I want! :P
	clearstatcache();
	if (filemtime($boarddir . '/Settings.php') === $last_settings_change)
	{
		// save the old before we do anything
		$settings_backup_fail = !@is_writable($boarddir . '/Settings_org.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_org.php');
		$settings_backup_fail = !$settings_backup_fail ? (!file_exists($boarddir . '/Settings_org.php') || filesize($boarddir . '/Settings_org.php') === 0) : $settings_backup_fail;

		// write out the new
		$write_settings = implode('', $settingsArray);
		$written_bytes = file_put_contents($boarddir . '/Settings.php', $write_settings, LOCK_EX);

		// survey says ...
		if ($written_bytes !== strlen($write_settings) && !$settings_backup_fail)
		{
			if (file_exists($boarddir . '/Settings_bak.php'))
				@copy($boarddir . '/Settings_bak.php', $boarddir . '/Settings.php');

			return throw_error($txt['error_settings_migration_general']);
		}
	}

	// Even though on normal installations the filemtime should prevent this being used by the installer incorrectly
	// it seems that there are times it might not. So let's MAKE it dump the cache.
	if (function_exists('opcache_invalidate'))
		opcache_invalidate($boarddir . '/Settings.php', true);
}

/**
 * Determine if we should auto select the migrate Settings file.  This is determined by a variety of missing settings.
 * Prior to checking these settings, we look for advanced setups such as integrations or if variables have been moved
 * to another file.  If these are detected, we abort.
 *
 * @param array $config_vars An array of one or more variables to update
 *
 * @return bool We either successfully update the Settings file, or throw a error here.
 */
function detectSettingsFileMigrationNeeded()
{
	global $boarddir, $packagesdir, $tasksdir, $db_server, $db_type, $image_proxy_enabled, $db_show_debug;

	// We should not migrate if db_show_debug is in there, some dev stuff going on here.
	if (isset($db_show_debug))
		return false;

	$file_contents = file_get_contents($boarddir . '/Settings.php');

	// Is there a include statement somewhere in there? Some advanced handling of the variables elsewhere?
	// Try our best to stay away from the cachedir match.
	if (preg_match('~\sinclude\((?:(?!\(isset\(\$cachedir))~im', $file_contents))
		return false;

	// If we find a mention of $GLOBALS, there may be a integration going on.
	if (preg_match('~\$GLOBALS\[~im', $file_contents))
		return false;

	// If these are not set, it makes us a candidate to migrate.
	if (!isset($packagesdir, $tasksdir, $db_server, $db_type, $image_proxy_enabled))
		return true;

	return false;
}

?>