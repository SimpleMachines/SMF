<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

// Version information...
define('SMF_VERSION', '2.1 Alpha 1');
define('SMF_LANG_VERSION', '2.1');

$GLOBALS['required_php_version'] = '5.1.0';
$GLOBALS['required_mysql_version'] = '4.0.18';

$databases = array(
	'mysql' => array(
		'name' => 'MySQL',
		'version' => '4.0.18',
		'version_check' => 'return min(mysql_get_server_info(), mysql_get_client_info());',
		'utf8_support' => true,
		'utf8_version' => '4.1.0',
		'utf8_version_check' => 'return mysql_get_server_info();',
		'alter_support' => true,
	),
	'postgresql' => array(
		'name' => 'PostgreSQL',
		'version' => '8.0',
		'version_check' => '$version = pg_version(); return $version[\'client\'];',
		'always_has_db' => true,
	),
	'sqlite' => array(
		'name' => 'SQLite',
		'version' => '1',
		'version_check' => 'return 1;',
		'always_has_db' => true,
	),
);

// General options for the script.
$timeLimitThreshold = 3;
$upgrade_path = dirname(__FILE__);
$upgradeurl = $_SERVER['PHP_SELF'];
// Where the SMF images etc are kept.
$smfsite = 'http://www.simplemachines.org/smf';
// Disable the need for admins to login?
$disable_security = false;
// How long, in seconds, must admin be inactive to allow someone else to run?
$upcontext['inactive_timeout'] = 10;

// All the steps in detail.
// Number,Name,Function,Progress Weight.
$upcontext['steps'] = array(
	0 => array(1, 'Login', 'WelcomeLogin', 2),
	1 => array(2, 'Upgrade Options', 'UpgradeOptions', 2),
	2 => array(3, 'Backup', 'BackupDatabase', 10),
	3 => array(4, 'Database Changes', 'DatabaseChanges', 70),
	// This is removed as it doesn't really work right at the moment.
	//4 => array(5, 'Cleanup Mods', 'CleanupMods', 10),
	4 => array(5, 'Delete Upgrade', 'DeleteUpgrade', 1),
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
	$disable_security = 1;
}
else
	$command_line = false;

// Load this now just because we can.
require_once($upgrade_path . '/Settings.php');

// Are we logged in?
if (isset($upgradeData))
{
	$upcontext['user'] = unserialize(base64_decode($upgradeData));

	// Check for sensible values.
	if (empty($upcontext['user']['started']) || $upcontext['user']['started'] < time() - 86400)
		$upcontext['user']['started'] = time();
	if (empty($upcontext['user']['updated']) || $upcontext['user']['updated'] < time() - 86400)
		$upcontext['user']['updated'] = 0;

	$upcontext['started'] = $upcontext['user']['started'];
	$upcontext['updated'] = $upcontext['user']['updated'];
}

// Nothing sensible?
if (empty($upcontext['updated']))
{
	$upcontext['started'] = time();
	$upcontext['updated'] = 0;
	$upcontext['user'] = array(
		'id' => 0,
		'name' => 'Guest',
		'pass' => 0,
		'started' => $upcontext['started'],
		'updated' => $upcontext['updated'],
	);
}

// Load up some essential data...
loadEssentialData();

// Are we going to be mimic'ing SSI at this point?
if (isset($_GET['ssi']))
{
	require_once($sourcedir . '/Subs.php');
	require_once($sourcedir . '/Errors.php');
	require_once($sourcedir . '/Logging.php');
	require_once($sourcedir . '/Load.php');
	require_once($sourcedir . '/Security.php');
	require_once($sourcedir . '/Subs-Package.php');

	loadUserSettings();
	loadPermissions();
}

// All the non-SSI stuff.
if (!function_exists('ip2range'))
	require_once($sourcedir . '/Subs.php');

if (!function_exists('un_htmlspecialchars'))
{
	function un_htmlspecialchars($string)
	{
		return strtr($string, array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES)) + array('&#039;' => '\'', '&nbsp;' => ' '));
	}
}

if (!function_exists('text2words'))
{
	function text2words($text)
	{
		global $smcFunc;

		// Step 1: Remove entities/things we don't consider words:
		$words = preg_replace('~(?:[\x0B\0\xA0\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~', ' ', $text);

		// Step 2: Entities we left to letters, where applicable, lowercase.
		$words = preg_replace('~([^&\d]|^)[#;]~', '$1 ', un_htmlspecialchars(strtolower($words)));

		// Step 3: Ready to split apart and index!
		$words = explode(' ', $words);
		$returned_words = array();
		foreach ($words as $word)
		{
			$word = trim($word, '-_\'');

			if ($word != '')
				$returned_words[] = substr($word, 0, 20);
		}

		return array_unique($returned_words);
	}
}

if (!function_exists('clean_cache'))
{
	// Empty out the cache folder.
	function clean_cache($type = '')
	{
		global $cachedir, $sourcedir;

		// No directory = no game.
		if (!is_dir($cachedir))
			return;

		// Remove the files in SMF's own disk cache, if any
		$dh = opendir($cachedir);
		while ($file = readdir($dh))
		{
			if ($file != '.' && $file != '..' && $file != 'index.php' && $file != '.htaccess' && (!$type || substr($file, 0, strlen($type)) == $type))
				@unlink($cachedir . '/' . $file);
		}
		closedir($dh);

		// Invalidate cache, to be sure!
		// ... as long as Load.php can be modified, anyway.
		@touch($sourcedir . '/' . 'Load.php');
		clearstatcache();
	}
}

// MD5 Encryption.
if (!function_exists('md5_hmac'))
{
	function md5_hmac($data, $key)
	{
		if (strlen($key) > 64)
			$key = pack('H*', md5($key));
		$key = str_pad($key, 64, chr(0x00));

		$k_ipad = $key ^ str_repeat(chr(0x36), 64);
		$k_opad = $key ^ str_repeat(chr(0x5c), 64);

		return md5($k_opad . pack('H*', md5($k_ipad . $data)));
	}
}

// http://www.faqs.org/rfcs/rfc959.html
if (!class_exists('ftp_connection'))
{
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
			if (substr($ftp_path, -1) == '/' && $ftp_path !== '/')
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

			// Convert the chmod value from octal (0777) to text ("777").
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
			@fwrite($this->connection, 'PASV' . "\r\n");
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

			// This is pretty simple - store it for later use ;).
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

			@fwrite($this->connection, 'PWD' . "\r\n");
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
			fwrite($this->connection, 'QUIT' . "\r\n");
			fclose($this->connection);

			return true;
		}
	}
}

// Don't do security check if on Yabbse
if (!isset($modSettings['smfVersion']))
	$disable_security = true;

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

$upcontext['is_large_forum'] = (empty($modSettings['smfVersion']) || $modSettings['smfVersion'] <= '1.1 RC1') && !empty($modSettings['totalMessages']) && $modSettings['totalMessages'] > 75000;
// Default title...
$upcontext['page_title'] = isset($modSettings['smfVersion']) ? 'Updating Your SMF Install!' : 'Upgrading from YaBB SE!';

$upcontext['right_to_left'] = isset($txt['lang_rtl']) ? $txt['lang_rtl'] : false;

// Have we got tracking data - if so use it (It will be clean!)
if (isset($_GET['data']))
{
	$upcontext['upgrade_status'] = unserialize(base64_decode($_GET['data']));
	$upcontext['current_step'] = $upcontext['upgrade_status']['curstep'];
	$upcontext['language'] = $upcontext['upgrade_status']['lang'];
	$upcontext['rid'] = $upcontext['upgrade_status']['rid'];
	$is_debug = $upcontext['upgrade_status']['debug'];
	$support_js = $upcontext['upgrade_status']['js'];

	// Load the language.
	if (file_exists($modSettings['theme_dir'] . '/languages/Install.' . $upcontext['language'] . '.php'))
		require_once($modSettings['theme_dir'] . '/languages/Install.' . $upcontext['language'] . '.php');
}
// Set the defaults.
else
{
	$upcontext['current_step'] = 0;
	$upcontext['rid'] = mt_rand(0, 5000);
	$upcontext['upgrade_status'] = array(
		'curstep' => 0,
		'lang' => isset($_GET['lang']) ? $_GET['lang'] : basename($language, '.lng'),
		'rid' => $upcontext['rid'],
		'pass' => 0,
		'debug' => 0,
		'js' => 0,
	);
	$upcontext['language'] = $upcontext['upgrade_status']['lang'];
}

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
			$upcontext['current_step']++;
	}
	$upcontext['overall_percent'] += $step[3];
}

upgradeExit();

// Exit the upgrade script.
function upgradeExit($fallThrough = false)
{
	global $upcontext, $upgradeurl, $boarddir, $command_line;

	// Save where we are...
	if (!empty($upcontext['current_step']) && !empty($upcontext['user']['id']))
	{
		$upcontext['user']['step'] = $upcontext['current_step'];
		$upcontext['user']['substep'] = $_GET['substep'];
		$upcontext['user']['updated'] = time();
		$upgradeData = base64_encode(serialize($upcontext['user']));
		copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');
		changeSettings(array('upgradeData' => '"' . $upgradeData . '"'));
		updateLastError();
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

			echo "\n" . 'Error: Unexpected call to use the ' . (isset($upcontext['sub_template']) ? $upcontext['sub_template'] : '') . ' template. Please copy and paste all the text above and visit the SMF support forum to tell the Developers that they\'ve made a boo boo; they\'ll get you up and running again.';
			flush();
			die();
		}

		if (!isset($_GET['xml']))
			template_upgrade_above();
		else
		{
			header('Content-Type: text/xml; charset=ISO-8859-1');
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
			$upcontext['form_url'] = $upgradeurl . '?step=' . $upcontext['current_step'] . '&amp;substep=' . $_GET['substep'] . '&amp;data=' . base64_encode(serialize($upcontext['upgrade_status']));

			// Custom stuff to pass back?
			if (!empty($upcontext['query_string']))
				$upcontext['form_url'] .= $upcontext['query_string'];

			call_user_func('template_' . $upcontext['sub_template']);
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

	// Bang - gone!
	die();
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
		$location = $upgradeurl . '?step=' . $upcontext['current_step'] . '&substep=' . $_GET['substep'] . '&data=' . base64_encode(serialize($upcontext['upgrade_status'])) . $location;
	}

	while (@ob_end_clean());
	header('Location: ' . strtr($location, array('&amp;' => '&')));

	// Exit - saving status as we go.
	upgradeExit(true);
}

// Load all essential data and connect to the DB as this is pre SSI.php
function loadEssentialData()
{
	global $db_server, $db_user, $db_passwd, $db_name, $db_connection, $db_prefix, $db_character_set, $db_type;
	global $modSettings, $sourcedir, $smcFunc, $upcontext;

	// Do the non-SSI stuff...
	@set_magic_quotes_runtime(0);
	error_reporting(E_ALL);
	define('SMF', 1);

	// Start the session.
	if (@ini_get('session.save_handler') == 'user')
		@ini_set('session.save_handler', 'files');
	@session_start();

	if (empty($smcFunc))
		$smcFunc = array();

	// Check we don't need some compatibility.
	if (@version_compare(PHP_VERSION, '5.1', '<='))
		require_once($sourcedir . '/Subs-Compat.php');

	// Initialize everything...
	initialize_inputs();

	// Get the database going!
	if (empty($db_type))
		$db_type = 'mysql';
	if (file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php'))
	{
		require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');

		// Make the connection...
		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('non_fatal' => true));

		// Oh dear god!!
		if ($db_connection === null)
			die('Unable to connect to database - please check username and password are correct in Settings.php');

		if ($db_type == 'mysql' && isset($db_character_set) && preg_match('~^\w+$~', $db_character_set) === 1)
			$smcFunc['db_query']('', '
			SET NAMES ' . $db_character_set,
			array(
				'db_error_skip' => true,
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
	{
		return throw_error('Cannot find ' . $sourcedir . '/Subs-Db-' . $db_type . '.php' . '. Please check you have uploaded all source files and have the correct paths set.');
	}

	// If they don't have the file, they're going to get a warning anyway so we won't need to clean request vars.
	if (file_exists($sourcedir . '/QueryString.php'))
	{
		require_once($sourcedir . '/QueryString.php');
		cleanRequest();
	}

	if (!isset($_GET['substep']))
		$_GET['substep'] = 0;
}

function initialize_inputs()
{
	global $sourcedir, $start_time, $upcontext, $db_type;

	$start_time = time();

	umask(0);

	// Fun.  Low PHP version...
	if (!isset($_GET))
	{
		$GLOBALS['_GET']['step'] = 0;
		return;
	}

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
		@unlink(dirname(__FILE__) . '/webinstall.php');

		$dh = opendir(dirname(__FILE__));
		while ($file = readdir($dh))
		{
			if (preg_match('~upgrade_\d-\d_([A-Za-z])+\.sql~i', $file, $matches) && isset($matches[1]))
				@unlink(dirname(__FILE__) . '/' . $file);
		}
		closedir($dh);

		header('Location: http://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/Themes/default/images/blank.png');
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
		$upcontext['remote_files_available'] = false;
		$test = @fsockopen('www.simplemachines.org', 80, $errno, $errstr, 1);
		if ($test)
			$upcontext['remote_files_available'] = true;
		@fclose($test);
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
	global $boarddir, $sourcedir, $db_prefix, $language, $modSettings, $cachedir, $upgradeurl, $upcontext, $disable_security;
	global $smcFunc, $db_type, $databases, $txt;

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

	if (!$check)
		// Don't tell them what files exactly because it's a spot check - just like teachers don't tell which problems they are spot checking, that's dumb.
		return throw_error('The upgrader was unable to find some crucial files.<br /><br />Please make sure you uploaded all of the files included in the package, including the Themes, Sources, and other directories.');

	// Do they meet the install requirements?
	if (!php_version_check())
		return throw_error('Warning!  You do not appear to have a version of PHP installed on your webserver that meets SMF\'s minimum installations requirements.<br /><br />Please ask your host to upgrade.');

	if (!db_version_check())
		return throw_error('Your ' . $databases[$db_type]['name'] . ' version does not meet the minimum requirements of SMF.<br /><br />Please ask your host to upgrade.');

	// Do they have ALTER privileges?
	if (!empty($databases[$db_type]['alter_support']) && $smcFunc['db_query']('alter_boards', 'ALTER TABLE {db_prefix}boards ORDER BY id_board', array()) === false)
		return throw_error('The ' . $databases[$db_type]['name'] . ' user you have set in Settings.php does not have proper privileges.<br /><br />Please ask your host to give this user the ALTER, CREATE, and DROP privileges.');

	// Do a quick version spot check.
	$temp = substr(@implode('', @file($boarddir . '/index.php')), 0, 4096);
	preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $temp, $match);
	if (empty($match[1]) || $match[1] != SMF_VERSION)
		return throw_error('The upgrader found some old or outdated files.<br /><br />Please make certain you uploaded the new versions of all the files included in the package.');

	// What absolutely needs to be writable?
	$writable_files = array(
		$boarddir . '/Settings.php',
		$boarddir . '/Settings_bak.php',
	);

	require_once($sourcedir . '/Security.php');
	$upcontext += createToken('login');

	// Check the cache directory.
	$cachedir_temp = empty($cachedir) ? $boarddir . '/cache' : $cachedir;
	if (!file_exists($cachedir_temp))
		@mkdir($cachedir_temp);
	if (!file_exists($cachedir_temp))
		return throw_error('The cache directory could not be found.<br /><br />Please make sure you have a directory called &quot;cache&quot; in your forum directory before continuing.');

	if (!file_exists($modSettings['theme_dir'] . '/languages/index.' . $upcontext['language'] . '.php') && !isset($modSettings['smfVersion']) && !isset($_GET['lang']))
		return throw_error('The upgrader was unable to find language files for the language specified in Settings.php.<br />SMF will not work without the primary language files installed.<br /><br />Please either install them, or <a href="' . $upgradeurl . '?step=0;lang=english">use english instead</a>.');
	elseif (!isset($_GET['skiplang']))
	{
		$temp = substr(@implode('', @file($modSettings['theme_dir'] . '/languages/index.' . $upcontext['language'] . '.php')), 0, 4096);
		preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*index(?:[\s]{2}|\*/)~i', $temp, $match);

		if (empty($match[1]) || $match[1] != SMF_LANG_VERSION)
			return throw_error('The upgrader found some old or outdated language files, for the forum default language, ' . $upcontext['language'] . '.<br /><br />Please make certain you uploaded the new versions of all the files included in the package, even the theme and language files for the default theme.<br />&nbsp;&nbsp;&nbsp;[<a href="' . $upgradeurl . '?skiplang">SKIP</a>] [<a href="' . $upgradeurl . '?lang=english">Try English</a>]');
	}

	// This needs to exist!
	if (!file_exists($modSettings['theme_dir'] . '/languages/Install.' . $upcontext['language'] . '.php'))
		return throw_error('The upgrader could not find the &quot;Install&quot; language file for the forum default language, ' . $upcontext['language'] . '.<br /><br />Please make certain you uploaded all the files included in the package, even the theme and language files for the default theme.<br />&nbsp;&nbsp;&nbsp;[<a href="' . $upgradeurl . '?lang=english">Try English</a>]');
	else
		require_once($modSettings['theme_dir'] . '/languages/Install.' . $upcontext['language'] . '.php');

	if (!makeFilesWritable($writable_files))
		return false;

	// Check agreement.txt. (it may not exist, in which case $boarddir must be writable.)
	if (isset($modSettings['agreement']) && (!is_writable($boarddir) || file_exists($boarddir . '/agreement.txt')) && !is_writable($boarddir . '/agreement.txt'))
		return throw_error('The upgrader was unable to obtain write access to agreement.txt.<br /><br />If you are using a linux or unix based server, please ensure that the file is chmod\'d to 777, or if it does not exist that the directory this upgrader is in is 777.<br />If your server is running Windows, please ensure that the internet guest account has the proper permissions on it or its folder.');

	// Upgrade the agreement.
	elseif (isset($modSettings['agreement']))
	{
		$fp = fopen($boarddir . '/agreement.txt', 'w');
		fwrite($fp, $modSettings['agreement']);
		fclose($fp);
	}

	// We're going to check that their board dir setting is right incase they've been moving stuff around.
	if (strtr($boarddir, array('/' => '', '\\' => '')) != strtr(dirname(__FILE__), array('/' => '', '\\' => '')))
		$upcontext['warning'] = '
			It looks as if your board directory settings <em>might</em> be incorrect. Your board directory is currently set to &quot;' . $boarddir . '&quot; but should probably be &quot;' . dirname(__FILE__) . '&quot;. Settings.php currently lists your paths as:<br />
			<ul>
				<li>Board Directory: ' . $boarddir . '</li>
				<li>Source Directory: ' . $boarddir . '</li>
				<li>Cache Directory: ' . $cachedir_temp . '</li>
			</ul>
			If these seem incorrect please open Settings.php in a text editor before proceeding with this upgrade. If they are incorrect due to you moving your forum to a new location please download and execute the <a href="http://download.simplemachines.org/?tools">Repair Settings</a> tool from the Simple Machines website before continuing.';

	// Either we're logged in or we're going to present the login.
	if (checkLogin())
		return true;

	return false;
}

// Step 0.5: Does the login work?
function checkLogin()
{
	global $boarddir, $sourcedir, $db_prefix, $language, $modSettings, $cachedir, $upgradeurl, $upcontext, $disable_security;
	global $smcFunc, $db_type, $databases, $support_js, $txt;

	// Are we trying to login?
	if (isset($_POST['contbutt']) && (!empty($_POST['user']) || $disable_security))
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

				// Figure out the password using SMF's encryption - if what they typed is right.
				if (isset($_REQUEST['hash_passwrd']) && strlen($_REQUEST['hash_passwrd']) == 40)
				{
					// Challenge passed.
					if ($_REQUEST['hash_passwrd'] == sha1($password . $upcontext['rid']))
						$sha_passwd = $password;
				}
				else
					$sha_passwd = sha1(strtolower($name) . un_htmlspecialchars($_REQUEST['passwrd']));
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
		if ((empty($sha_passwd) || $password != $sha_passwd) && empty($upcontext['username_incorrect']) && !$disable_security)
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
						return throw_error('You need to be an admin to perform an upgrade!');
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
			$upcontext['user']['pass'] = mt_rand(0,60000);
			// This basically is used to match the GET variables to Settings.php.
			$upcontext['upgrade_status']['pass'] = $upcontext['user']['pass'];

			// Set the language to that of the user?
			if (isset($user_language) && $user_language != $upcontext['language'] && file_exists($modSettings['theme_dir'] . '/languages/index.' . basename($user_language, '.lng') . '.php'))
			{
				$user_language = basename($user_language, '.lng');
				$temp = substr(@implode('', @file($modSettings['theme_dir'] . '/languages/index.' . $user_language . '.php')), 0, 4096);
				preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*index(?:[\s]{2}|\*/)~i', $temp, $match);

				if (empty($match[1]) || $match[1] != SMF_LANG_VERSION)
					$upcontext['upgrade_options_warning'] = 'The language files for your selected language, ' . $user_language . ', have not been updated to the latest version. Upgrade will continue with the forum default, ' . $upcontext['language'] . '.';
				elseif (!file_exists($modSettings['theme_dir'] . '/languages/Install.' . basename($user_language, '.lng') . '.php'))
					$upcontext['upgrade_options_warning'] = 'The language files for your selected language, ' . $user_language . ', have not been uploaded/updated as the &quot;Install&quot; language file is missing. Upgrade will continue with the forum default, ' . $upcontext['language'] . '.';
				else
				{
					// Set this as the new language.
					$upcontext['language'] = $user_language;
					$upcontext['upgrade_status']['lang'] = $upcontext['language'];

					// Include the file.
					require_once($modSettings['theme_dir'] . '/languages/Install.' . $user_language . '.php');
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
	global $db_prefix, $command_line, $modSettings, $is_debug, $smcFunc;
	global $boarddir, $boardurl, $sourcedir, $maintenance, $mmessage, $cachedir, $upcontext, $db_type;

	$upcontext['sub_template'] = 'upgrade_options';
	$upcontext['page_title'] = 'Upgrade Options';

	// If we've not submitted then we're done.
	if (empty($_POST['upcont']))
		return false;

	// Firstly, if they're enabling SM stat collection just do it.
	if (!empty($_POST['stats']) && substr($boardurl, 0, 16) != 'http://localhost' && empty($modSettings['allow_sm_stats']))
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
					array('allow_sm_stats', $ID[1]),
					array('variable')
				);
		}
	}
	else
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable = {string:allow_sm_stats}',
			array(
				'allow_sm_stats' => 'allow_sm_stats',
				'db_error_skip' => true,
			)
		);

	// Emptying the error log?
	if (!empty($_POST['empty_error']))
		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_errors',
			array(
			)
		);

	$changes = array();

	// If we're overriding the language follow it through.
	if (isset($_GET['lang']) && file_exists($modSettings['theme_dir'] . '/languages/index.' . $_GET['lang'] . '.php'))
		$changes['language'] = '\'' . $_GET['lang'] . '\'';

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
			$changes['mtitle'] = '\'Upgrading the forum...\'';
			$changes['mmessage'] = '\'Don\\\'t worry, we will be back shortly with an updated forum.  It will only be a minute ;).\'';
		}
	}

	if ($command_line)
		echo ' * Updating Settings.php...';

	// Backup the current one first.
	copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	// Fix some old paths.
	if (substr($boarddir, 0, 1) == '.')
		$changes['boarddir'] = '\'' . fixRelativePath($boarddir) . '\'';

	if (substr($sourcedir, 0, 1) == '.')
		$changes['sourcedir'] = '\'' . fixRelativePath($sourcedir) . '\'';

	if (empty($cachedir) || substr($cachedir, 0, 1) == '.')
		$changes['cachedir'] = '\'' . fixRelativePath($boarddir) . '/cache\'';

	// Not had the database type added before?
	if (empty($db_type))
		$changes['db_type'] = 'mysql';

	// @todo Maybe change the cookie name if going to 1.1, too?

	// Update Settings.php with the new settings.
	changeSettings($changes);

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
	global $upcontext, $db_prefix, $command_line, $is_debug, $support_js, $file_steps, $smcFunc;

	$upcontext['sub_template'] = isset($_GET['xml']) ? 'backup_xml' : 'backup_database';
	$upcontext['page_title'] = 'Backup Database';

	// Done it already - js wise?
	if (!empty($_POST['backup_done']))
		return true;

	// Some useful stuff here.
	db_extend();

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

		if ($is_debug && $command_line)
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
	global $is_debug, $command_line, $db_prefix, $smcFunc;

	if ($is_debug && $command_line)
	{
		echo "\n" . ' +++ Backing up \"' . str_replace($db_prefix, '', $table) . '"...';
		flush();
	}

	$smcFunc['db_backup_table']($table, 'backup_' . $table);

	if ($is_debug && $command_line)
		echo ' done.';
}

// Step 2: Everything.
function DatabaseChanges()
{
	global $db_prefix, $modSettings, $command_line, $smcFunc;
	global $language, $boardurl, $sourcedir, $boarddir, $upcontext, $support_js, $db_type;

	// Have we just completed this?
	if (!empty($_POST['database_done']))
		return true;

	$upcontext['sub_template'] = isset($_GET['xml']) ? 'database_xml' : 'database_changes';
	$upcontext['page_title'] = 'Database Changes';

	// All possible files.
	// Name, <version, insert_on_complete
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

		// If this is the command line we can't do any more.
		if ($command_line)
			return DeleteUpgrade();

		return true;
	}
	return false;
}

// Clean up any mods installed...
function CleanupMods()
{
	global $db_prefix, $modSettings, $upcontext, $boarddir, $sourcedir, $settings, $smcFunc, $command_line;

	// Sorry. Not supported for command line users.
	if ($command_line)
		return true;

	// Skipping first?
	if (!empty($_POST['skip']))
	{
		unset($_POST['skip']);
		return true;
	}

	// If we get here withOUT SSI we need to redirect to ensure we get it!
	if (!isset($_GET['ssi']) || !function_exists('mktree'))
		redirectLocation('&ssi=1');

	$upcontext['sub_template'] = 'clean_mods';
	$upcontext['page_title'] = 'Cleanup Modifications';

	// This can be skipped.
	$upcontext['skip'] = true;

	// If we're on the second redirect continue...
	if (isset($_POST['cleandone2']))
		return true;

	// Do we already know about some writable files?
	if (isset($_POST['writable_files']))
	{
		$writable_files = unserialize(base64_decode($_POST['writable_files']));
		if (!makeFilesWritable($writable_files))
		{
			// What have we left?
			$upcontext['writable_files'] = $writable_files;
			return false;
		}
	}

	// Load all theme paths....
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_member = {int:id_member}
			AND variable IN ({string:theme_dir}, {string:images_url})',
		array(
			'id_member' => 0,
			'theme_dir' => 'theme_dir',
			'images_url' => 'images_url',
			'db_error_skip' => true,
		)
	);
	$theme_paths = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['id_theme'] == 1)
			$settings['default_' . $row['variable']] = $row['value'];
		elseif ($row['variable'] == 'theme_dir')
			$theme_paths[$row['id_theme']][$row['variable']] = $row['value'];
	}
	$smcFunc['db_free_result']($request);

	// Are there are mods installed that may need uninstalling?
	$request = $smcFunc['db_query']('', '
		SELECT id_install, filename, name, themes_installed, version
		FROM {db_prefix}log_packages
		WHERE install_state = {int:installed}
		ORDER BY time_installed DESC',
		array(
			'installed' => 1,
			'db_error_skip' => true,
		)
	);
	$upcontext['packages'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Work out the status.
		if (!file_exists($boarddir . '/Packages/' . $row['filename']))
		{
			$status = 'Missing';
			$status_color = 'red';
			$result = 'Removed';
		}
		else
		{
			$status = 'Installed';
			$status_color = 'green';
			$result = 'No Action Needed';
		}

		$upcontext['packages'][$row['id_install']] = array(
			'id' => $row['id_install'],
			'themes' => explode(',', $row['themes_installed']),
			'name' => $row['name'],
			'filename' => $row['filename'],
			'missing_file' => file_exists($boarddir . '/Packages/' . $row['filename']) ? 0 : 1,
			'files' => array(),
			'file_count' => 0,
			'status' => $status,
			'result' => $result,
			'color' => $status_color,
			'version' => $row['version'],
			'needs_removing' => false,
		);
	}
	$smcFunc['db_free_result']($request);

	// Don't carry on if there are none.
	if (empty($upcontext['packages']))
		return true;

	// Setup some basics.
	if (!empty($upcontext['user']['version']))
		$_SESSION['version_emulate'] = $upcontext['user']['version'];

	// Before we get started, don't report notice errors.
	$oldErrorReporting = error_reporting(E_ALL ^ E_NOTICE);

	if (!mktree($boarddir . '/Packages/temp', 0755))
	{
		deltree($boarddir . '/Packages/temp', false);
		if (!mktree($boarddir . '/Packages/temp', 0777))
		{
			deltree($boarddir . '/Packages/temp', false);
			// @todo Error here - plus chmod!
		}
	}

	// Anything which reinstalled should not have its entry removed.
	$reinstall_worked = array();

	// We're gonna be doing some removin'
	$test = isset($_POST['cleandone']) ? false : true;
	foreach ($upcontext['packages'] as $id => $package)
	{
		// Can't do anything about this....
		if ($package['missing_file'])
			continue;

		// Not testing *and* this wasn't checked?
		if (!$test && (!isset($_POST['remove']) || !isset($_POST['remove'][$id])))
			continue;

		// What are the themes this was installed into?
		$cur_theme_paths = array();
		foreach ($theme_paths as $tid => $data)
			if ($tid != 1 && in_array($tid, $package['themes']))
				$cur_theme_paths[$tid] = $data;

		// Get the modifications data if applicable.
		$filename = $package['filename'];
		$packageInfo = getPackageInfo($filename);
		if (!is_array($packageInfo))
			continue;

		$info = parsePackageInfo($packageInfo['xml'], $test, 'uninstall');
		// Also get the reinstall details...
		if (isset($_POST['remove']))
			$infoInstall = parsePackageInfo($packageInfo['xml'], true);

		if (is_file($boarddir . '/Packages/' . $filename))
			read_tgz_file($boarddir . '/Packages/' . $filename, $boarddir . '/Packages/temp');
		else
			copytree($boarddir . '/Packages/' . $filename, $boarddir . '/Packages/temp');

		// Work out how we uninstall...
		$files = array();
		foreach ($info as $change)
		{
			// Work out two things:
			// 1) Whether it's installed at the moment - and if so whether its fully installed, and:
			// 2) Whether it could be installed on the new version.
			if ($change['type'] == 'modification')
			{
				$contents = @file_get_contents($boarddir . '/Packages/temp/' . $upcontext['base_path'] . $change['filename']);
				if ($change['boardmod'])
					$results = parseBoardMod($contents, $test, $change['reverse'], $cur_theme_paths);
				else
					$results = parseModification($contents, $test, $change['reverse'], $cur_theme_paths);

				foreach ($results as $action)
				{
					// Something we can remove? Probably means it existed!
					if (($action['type'] == 'replace' || $action['type'] == 'append' || (!empty($action['filename']) && $action['type'] == 'failure')) && !in_array($action['filename'], $files))
						$files[] = $action['filename'];
					if ($action['type'] == 'failure')
					{
						$upcontext['packages'][$id]['needs_removing'] = true;
						$upcontext['packages'][$id]['status'] = 'Reinstall Required';
						$upcontext['packages'][$id]['color'] = '#FD6435';
					}
				}
			}
		}

		// Store this info for the template as appropriate.
		$upcontext['packages'][$id]['files'] = $files;
		$upcontext['packages'][$id]['file_count'] = count($files);

		// If we've done something save the changes!
		if (!$test)
			package_flush_cache();

		// Are we attempting to reinstall this thing?
		if (isset($_POST['remove']) && !$test && isset($infoInstall))
		{
			// Need to extract again I'm afraid.
			if (is_file($boarddir . '/Packages/' . $filename))
				read_tgz_file($boarddir . '/Packages/' . $filename, $boarddir . '/Packages/temp');
			else
				copytree($boarddir . '/Packages/' . $filename, $boarddir . '/Packages/temp');

			$errors = false;
			$upcontext['packages'][$id]['result'] = 'Removed';
			foreach ($infoInstall as $change)
			{
				if ($change['type'] == 'modification')
				{
					$contents = @file_get_contents($boarddir . '/Packages/temp/' . $upcontext['base_path'] . $change['filename']);
					if ($change['boardmod'])
						$results = parseBoardMod($contents, true, $change['reverse'], $cur_theme_paths);
					else
						$results = parseModification($contents, true, $change['reverse'], $cur_theme_paths);

					// Are there any errors?
					foreach ($results as $action)
						if ($action['type'] == 'failure')
							$errors = true;
				}
			}
			if (!$errors)
			{
				$reinstall_worked[] = $id;
				$upcontext['packages'][$id]['result'] = 'Reinstalled';
				$upcontext['packages'][$id]['color'] = 'green';
				foreach ($infoInstall as $change)
				{
					if ($change['type'] == 'modification')
					{
						$contents = @file_get_contents($boarddir . '/Packages/temp/' . $upcontext['base_path'] . $change['filename']);
						if ($change['boardmod'])
							$results = parseBoardMod($contents, false, $change['reverse'], $cur_theme_paths);
						else
							$results = parseModification($contents, false, $change['reverse'], $cur_theme_paths);
					}
				}

				// Save the changes.
				package_flush_cache();
			}
		}
	}

	// Put errors back on a sec.
	error_reporting($oldErrorReporting);

	// Check everything is writable.
	if ($test && !empty($upcontext['packages']))
	{
		$writable_files = array();
		foreach ($upcontext['packages'] as $package)
		{
			if (!empty($package['files']))
				foreach ($package['files'] as $file)
					$writable_files[] = $file;
		}

		if (!empty($writable_files))
		{
			$writable_files = array_unique($writable_files);
			$upcontext['writable_files'] = $writable_files;

			if (!makeFilesWritable($writable_files))
				return false;
		}
	}

	if (file_exists($boarddir . '/Packages/temp'))
		deltree($boarddir . '/Packages/temp');

	// Removing/Reinstalling any packages?
	if (isset($_POST['remove']))
	{
		$deletes = array();
		foreach ($_POST['remove'] as $id => $dummy)
		{
			if (!in_array((int) $id, $reinstall_worked))
				$deletes[] = (int) $id;
		}

		if (!empty($deletes))
			upgrade_query( '
				UPDATE ' . $db_prefix . 'log_packages
				SET install_state = 0
				WHERE id_install IN (' . implode(',', $deletes) . ')');

		// Ensure we don't lose our changes!
		package_put_contents($boarddir . '/Packages/installed.list', time());

		$upcontext['sub_template'] = 'cleanup_done';
		return false;
	}
	else
	{
		$allgood = true;
		// Is there actually anything that needs our attention?
		foreach ($upcontext['packages'] as $package)
			if ($package['color'] != 'green')
				$allgood = false;

		if ($allgood)
			return true;
	}

	$_GET['substep'] = 0;
	return isset($_POST['cleandone']) ? true : false;
}


// Delete the damn thing!
function DeleteUpgrade()
{
	global $command_line, $language, $upcontext, $boarddir, $sourcedir, $forum_version, $user_info, $maintenance, $smcFunc, $db_type;

	// Now it's nice to have some of the basic SMF source files.
	if (!isset($_GET['ssi']) && !$command_line)
		redirectLocation('&ssi=1');

	$upcontext['sub_template'] = 'upgrade_complete';
	$upcontext['page_title'] = 'Upgrade Complete';

	$endl = $command_line ? "\n" : '<br />' . "\n";

	$changes = array(
		'language' => '\'' . (substr($language, -4) == '.lng' ? substr($language, 0, -4) : $language) . '\'',
		'db_error_send' => '1',
		'upgradeData' => '#remove#',
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

	// Make a backup of Settings.php first as otherwise earlier changes are lost.
	copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');
	changeSettings($changes);

	// Clean any old cache files away.
	clean_cache();

	// Can we delete the file?
	$upcontext['can_delete_script'] = is_writable(dirname(__FILE__)) || is_writable(__FILE__);

	// Now is the perfect time to fetch the SM files.
	if ($command_line)
		cli_scheduled_fetchSMfiles();
	else
	{
		require_once($sourcedir . '/ScheduledTasks.php');
		$forum_version = SMF_VERSION;  // The variable is usually defined in index.php so lets just use the constant to do it for us.
		scheduled_fetchSMfiles(); // Now go get those files!
	}

	// Log what we've done.
	if (empty($user_info['id']))
		$user_info['id'] = !empty($upcontext['user']['id']) ? $upcontext['user']['id'] : 0;

	// Log the action manually, so CLI still works.
	$smcFunc['db_insert']('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		array(
			time(), 3, $user_info['id'], $command_line ? '127.0.0.1' : $user_info['ip'], 'upgrade',
			0, 0, 0, serialize(array('version' => $forum_version, 'member' => $user_info['id'])),
		),
		array('id_action')
	);
	$user_info['id'] = 0;

	// Save the current database version.
	$server_version = $smcFunc['db_server_info']();
	if ($db_type == 'mysql' && in_array(substr($server_version, 0, 6), array('5.0.50', '5.0.51')))
		updateSettings(array('db_mysql_group_by_fix' => '1'));

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
	global $sourcedir, $txt, $language, $settings, $forum_version, $modSettings, $smcFunc;

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
			'parameters' => sprintf($row['parameters'], $language, urlencode($modSettings['time_format']), urlencode($forum_version)),
		);
	}
	$smcFunc['db_free_result']($request);

	// We're gonna need fetch_web_data() to pull this off.
	require_once($sourcedir . '/Subs-Package.php');

	foreach ($js_files as $ID_FILE => $file)
	{
		// Create the url
		$server = empty($file['path']) || substr($file['path'], 0, 7) != 'http://' ? 'http://www.simplemachines.org' : '';
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
		'show_board_desc' => @$GLOBALS['ShowBDescrip'],
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
	global $db_prefix, $modSettings, $smcFunc;

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

function changeSettings($config_vars)
{
	global $boarddir;

	$settingsArray = file($boarddir . '/Settings_bak.php');

	if (count($settingsArray) == 1)
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);

	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		// Don't trim or bother with it if it's not a variable.
		if (substr($settingsArray[$i], 0, 1) == '$')
		{
			$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

			foreach ($config_vars as $var => $val)
			{
				if (isset($settingsArray[$i]) && strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
				{
					if ($val == '#remove#')
						unset($settingsArray[$i]);
					else
					{
						$comment = strstr(substr($settingsArray[$i], strpos($settingsArray[$i], ';')), '#');
						$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment != '' ? "\t\t" . $comment : "\n");
					}

					unset($config_vars[$var]);
				}
			}
		}
		if (isset($settingsArray[$i]))
		{
			if (trim(substr($settingsArray[$i], 0, 2)) == '?' . '>')
				$end = $i;
		}
	}

	// Assume end-of-file if the end wasn't found.
	if (empty($end) || $end < 10)
		$end = count($settingsArray);

	if (!empty($config_vars))
	{
		$settingsArray[$end++] = '';
		foreach ($config_vars as $var => $val)
		{
			if ($val != '#remove#')
				$settingsArray[$end++] = '$' . $var . ' = ' . $val . ';' . "\n";
		}
	}
	// This should be the last line and even last bytes of the file.
	$settingsArray[$end] = '?' . '>';

	// Blank out the file - done to fix a oddity with some servers.
	$fp = fopen($boarddir . '/Settings.php', 'w');
	fclose($fp);

	$fp = fopen($boarddir . '/Settings.php', 'r+');
	for ($i = 0; $i < $end; $i++)
	{
		if (isset($settingsArray[$i]))
			fwrite($fp, strtr($settingsArray[$i], "\r", ''));
	}
	fwrite($fp, rtrim($settingsArray[$i]));
	fclose($fp);
}
function updateLastError()
{
	// clear out the db_last_error file
	file_put_contents(dirname(__FILE__) . '/db_last_error.php', '<' . '?' . "php\n" . '$db_last_error = 0;' . "\n" . '?' . '>');
}

function php_version_check()
{
	$minver = explode('.', $GLOBALS['required_php_version']);
	$curver = explode('.', PHP_VERSION);

	return !(($curver[0] <= $minver[0]) && ($curver[1] <= $minver[1]) && ($curver[1] <= $minver[1]) && ($curver[2][0] < $minver[2][0]));
}

function db_version_check()
{
	global $db_type, $databases;

	$curver = eval($databases[$db_type]['version_check']);
	$curver = preg_replace('~\-.+?$~', '', $curver);

	return version_compare($databases[$db_type]['version'], $curver, '<=');
}

function getMemberGroups()
{
	global $db_prefix, $smcFunc;
	static $member_groups = array();

	if (!empty($member_groups))
		return $member_groups;

	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group
		FROM {db_prefix}membergroups
		WHERE id_group = {int:admin_group} OR id_group > {int:old_group}',
		array(
			'admin_group' => 1,
			'old_group' => 7,
			'db_error_skip' => true,
		)
	);
	if ($request === false)
	{
		$request = $smcFunc['db_query']('', '
			SELECT membergroup, id_group
			FROM {db_prefix}membergroups
			WHERE id_group = {int:admin_group} OR id_group > {int:old_group}',
			array(
				'admin_group' => 1,
				'old_group' => 7,
				'db_error_skip' => true,
			)
		);
	}
	while ($row = $smcFunc['db_fetch_row']($request))
		$member_groups[trim($row[0])] = $row[1];
	$smcFunc['db_free_result']($request);

	return $member_groups;
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
	global $upcontext, $support_js, $is_debug, $smcFunc, $db_connection, $databases, $db_type, $db_character_set;

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
	if (!function_exists('sql_error_handler'))
	{
		function sql_error_handler($errno, $errstr, $errfile, $errline)
		{
			global $support_js;

			if ($support_js)
				return true;
			else
				echo 'Error: ' . $errstr . ' File: ' . $errfile . ' Line: ' . $errline;
		}
	}

	// Make our own error handler.
	set_error_handler('sql_error_handler');

	// If we're on MySQL supporting collations then let's find out what the members table uses and put it in a global var - to allow upgrade script to match collations!
	if (!empty($databases[$db_type]['utf8_support']) && version_compare($databases[$db_type]['utf8_version'], eval($databases[$db_type]['utf8_version_check']), '>'))
	{
		$request = $smcFunc['db_query']('', '
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => "{$db_prefix}members",
				'db_error_skip' => true,
			)
		);
		if ($smcFunc['db_num_rows']($request) === 0)
			die('Unable to find members table!');
		$table_status = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($table_status['Collation']))
		{
			$request = $smcFunc['db_query']('', '
				SHOW COLLATION
				LIKE {string:collation}',
				array(
					'collation' => $table_status['Collation'],
					'db_error_skip' => true,
				)
			);
			// Got something?
			if ($smcFunc['db_num_rows']($request) !== 0)
				$collation_info = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			// Excellent!
			if (!empty($collation_info['Collation']) && !empty($collation_info['Charset']))
				$db_collation = ' CHARACTER SET ' . $collation_info['Charset'] . ' COLLATE ' . $collation_info['Collation'];
		}
	}
	if (empty($db_collation))
		$db_collation = '';

	$endl = $command_line ? "\n" : '<br />' . "\n";

	$lines = file($filename);

	$current_type = 'sql';
	$current_data = '';
	$substep = 0;
	$last_step = '';

	// Make sure all newly created tables will have the proper characters set.
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
							$upcontext['actioned_items'][] = htmlspecialchars(rtrim(substr($line, 4)));
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

				if (!$do_current)
				{
					$current_data = '';
					continue;
				}

				if (eval('global $db_prefix, $modSettings, $smcFunc; ' . $current_data) === false)
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
				if (!$do_current)
				{
					$current_data = '';
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
	global $db_connection, $db_server, $db_user, $db_passwd, $db_type, $command_line, $upcontext, $upgradeurl, $modSettings;
	global $db_name, $db_unbuffered, $smcFunc;

	// Get the query result - working around some SMF specific security - just this once!
	$modSettings['disableQueryCheck'] = true;
	$db_unbuffered = $unbuffered;
	$result = $smcFunc['db_query']('', $string, 'security_override');
	$db_unbuffered = false;

	// Failure?!
	if ($result !== false)
		return $result;

	$db_error_message = $smcFunc['db_error']($db_connection);
	// If MySQL we do something more clever.
	if ($db_type == 'mysql')
	{
		$mysql_errno = mysql_errno($db_connection);
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

		if ($mysql_errno == 1016)
		{
			if (preg_match('~\'([^\.\']+)~', $db_error_message, $match) != 0 && !empty($match[1]))
				mysql_query( '
					REPAIR TABLE `' . $match[1] . '`');

			$result = mysql_query($string);
			if ($result !== false)
				return $result;
		}
		elseif ($mysql_errno == 2013)
		{
			$db_connection = mysql_connect($db_server, $db_user, $db_passwd);
			mysql_select_db($db_name, $db_connection);

			if ($db_connection)
			{
				$result = mysql_query($string);

				if ($result !== false)
					return $result;
			}
		}
		// Duplicate column name... should be okay ;).
		elseif (in_array($mysql_errno, array(1060, 1061, 1068, 1091)))
			return false;
		// Duplicate insert... make sure it's the proper type of query ;).
		elseif (in_array($mysql_errno, array(1054, 1062, 1146)) && $error_query)
			return false;
		// Creating an index on a non-existent column.
		elseif ($mysql_errno == 1072)
			return false;
		elseif ($mysql_errno == 1050 && substr(trim($string), 0, 12) == 'RENAME TABLE')
			return false;
	}
	// If a table already exists don't go potty.
	else
	{
		if (in_array(substr(trim($string), 0, 8), array('CREATE T', 'CREATE S', 'DROP TABL', 'ALTER TA', 'CREATE I')))
		{
			if (strpos($db_error_message, 'exist') !== false)
				return true;
			// SQLite
			if (strpos($db_error_message, 'missing') !== false)
				return true;
		}
		elseif (strpos(trim($string), 'INSERT ') !== false)
		{
			if (strpos($db_error_message, 'duplicate') !== false)
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
		return false;
	}

	// Otherwise we have to display this somewhere appropriate if possible.
	$upcontext['forced_error_message'] = '
			<strong>Unsuccessful!</strong><br />

			<div style="margin: 2ex;">
				This query:
				<blockquote><tt>' . nl2br(htmlspecialchars(trim($string))) . ';</tt></blockquote>

				Caused the error:
				<blockquote>' . nl2br(htmlspecialchars($db_error_message)) . '</blockquote>
			</div>

			<form action="' . $upgradeurl . $query_string . '" method="post">
				<input type="submit" value="Try again" class="button_submit" />
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
				$found |= 1;
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
		$request = upgrade_query( '
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

// Alter a text column definition preserving its character set.
function textfield_alter($change, $substep)
{
	global $db_prefix, $databases, $db_type, $smcFunc;

	// Versions of MySQL < 4.1 wouldn't benefit from character set detection.
	if (empty($databases[$db_type]['utf8_support']) || version_compare($databases[$db_type]['utf8_version'], eval($databases[$db_type]['utf8_version_check']), '>'))
	{
		$column_fix = true;
		$null_fix = !$change['null_allowed'];
	}
	else
	{
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
		));
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
		));
		// Mayday!
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
	global $start_time, $timeLimitThreshold, $command_line, $file_steps, $modSettings, $custom_warning;
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
	global $boarddir, $sourcedir, $db_prefix, $language, $modSettings, $start_time, $cachedir, $databases, $db_type, $smcFunc, $upcontext;
	global $language, $is_debug, $txt;
	$start_time = time();

	ob_end_clean();
	ob_implicit_flush(true);
	@set_time_limit(600);

	if (!isset($_SERVER['argv']))
		$_SERVER['argv'] = array();
	$_GET['maint'] = 1;

	foreach ($_SERVER['argv'] as $i => $arg)
	{
		if (preg_match('~^--language=(.+)$~', $arg, $match) != 0)
			$_GET['lang'] = $match[1];
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

	if (!empty($databases[$db_type]['alter_support']) && $smcFunc['db_query']('alter_boards', 'ALTER TABLE {db_prefix}boards ORDER BY id_board', array()) === false)
		print_error('Error: The ' . $databases[$db_type]['name'] . ' account in Settings.php does not have sufficient privileges.', true);

	$check = @file_exists($modSettings['theme_dir'] . '/index.template.php')
		&& @file_exists($sourcedir . '/QueryString.php')
		&& @file_exists($sourcedir . '/ManageBoards.php');
	if (!$check && !isset($modSettings['smfVersion']))
		print_error('Error: Some files are missing or out-of-date.', true);

	// Do a quick version spot check.
	$temp = substr(@implode('', @file($boarddir . '/index.php')), 0, 4096);
	preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $temp, $match);
	if (empty($match[1]) || $match[1] != SMF_VERSION)
		print_error('Error: Some files have not yet been updated properly.');

	// Make sure Settings.php is writable.
	if (!is_writable($boarddir . '/Settings.php'))
		@chmod($boarddir . '/Settings.php', 0777);
	if (!is_writable($boarddir . '/Settings.php'))
		print_error('Error: Unable to obtain write access to "Settings.php".', true);

	// Make sure Settings.php is writable.
	if (!is_writable($boarddir . '/Settings_bak.php'))
		@chmod($boarddir . '/Settings_bak.php', 0777);
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
	if (!is_writable($modSettings['theme_dir']))
		@chmod($modSettings['theme_dir'], 0777);

	if (!is_writable($modSettings['theme_dir']) && !isset($modSettings['smfVersion']))
		print_error('Error: Unable to obtain write access to "Themes".');

	// Make sure cache directory exists and is writable!
	$cachedir_temp = empty($cachedir) ? $boarddir . '/cache' : $cachedir;
	if (!file_exists($cachedir_temp))
		@mkdir($cachedir_temp);

	if (!is_writable($cachedir_temp))
		@chmod($cachedir_temp, 0777);

	if (!is_writable($cachedir_temp))
		print_error('Error: Unable to obtain write access to "cache".', true);

	if (!file_exists($modSettings['theme_dir'] . '/languages/index.' . $upcontext['language'] . '.php') && !isset($modSettings['smfVersion']) && !isset($_GET['lang']))
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

function print_error($message, $fatal = false)
{
	static $fp = null;

	if ($fp === null)
		$fp = fopen('php://stderr', 'wb');

	fwrite($fp, $message . "\n");

	if ($fatal)
		exit;
}

function throw_error($message)
{
	global $upcontext;

	$upcontext['error_msg'] = $message;
	$upcontext['sub_template'] = 'error_message';

	return false;
}

// Check files are writable - make them writable if necessary...
function makeFilesWritable(&$files)
{
	global $upcontext, $boarddir;

	if (empty($files))
		return true;

	$failure = false;
	// On linux, it's easy - just use is_writable!
	if (substr(__FILE__, 1, 2) != ':\\')
	{
		foreach ($files as $k => $file)
		{
			if (!is_writable($file))
			{
				@chmod($file, 0755);

				// Well, 755 hopefully worked... if not, try 777.
				if (!is_writable($file) && !@chmod($file, 0777))
					$failure = true;
				// Otherwise remove it as it's good!
				else
					unset($files[$k]);
			}
			else
				unset($files[$k]);
		}
	}
	// Windows is trickier.  Let's try opening for r+...
	else
	{
		foreach ($files as $k => $file)
		{
			// Folders can't be opened for write... but the index.php in them can ;).
			if (is_dir($file))
				$file .= '/index.php';

			// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
			@chmod($file, 0777);
			$fp = @fopen($file, 'r+');

			// Hmm, okay, try just for write in that case...
			if (!$fp)
				$fp = @fopen($file, 'w');

			if (!$fp)
				$failure = true;
			else
				unset($files[$k]);
			@fclose($fp);
		}
	}

	if (empty($files))
		return true;

	if (!isset($_SERVER))
		return !$failure;

	// What still needs to be done?
	$upcontext['chmod']['files'] = $files;

	// If it's windows it's a mess...
	if ($failure && substr(__FILE__, 1, 2) == ':\\')
	{
		$upcontext['chmod']['ftp_error'] = 'total_mess';

		return false;
	}
	// We're going to have to use... FTP!
	elseif ($failure)
	{
		// Load any session data we might have...
		if (!isset($_POST['ftp_username']) && isset($_SESSION['installer_temp_ftp']))
		{
			$upcontext['chmod']['server'] = $_SESSION['installer_temp_ftp']['server'];
			$upcontext['chmod']['port'] = $_SESSION['installer_temp_ftp']['port'];
			$upcontext['chmod']['username'] = $_SESSION['installer_temp_ftp']['username'];
			$upcontext['chmod']['password'] = $_SESSION['installer_temp_ftp']['password'];
			$upcontext['chmod']['path'] = $_SESSION['installer_temp_ftp']['path'];
		}
		// Or have we submitted?
		elseif (isset($_POST['ftp_username']))
		{
			$upcontext['chmod']['server'] = $_POST['ftp_server'];
			$upcontext['chmod']['port'] = $_POST['ftp_port'];
			$upcontext['chmod']['username'] = $_POST['ftp_username'];
			$upcontext['chmod']['password'] = $_POST['ftp_password'];
			$upcontext['chmod']['path'] = $_POST['ftp_path'];
		}

		if (isset($upcontext['chmod']['username']))
		{
			$ftp = new ftp_connection($upcontext['chmod']['server'], $upcontext['chmod']['port'], $upcontext['chmod']['username'], $upcontext['chmod']['password']);

			if ($ftp->error === false)
			{
				// Try it without /home/abc just in case they messed up.
				if (!$ftp->chdir($upcontext['chmod']['path']))
				{
					$upcontext['chmod']['ftp_error'] = $ftp->last_message;
					$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $upcontext['chmod']['path']));
				}
			}
		}

		if (!isset($ftp) || $ftp->error !== false)
		{
			if (!isset($ftp))
				$ftp = new ftp_connection(null);
			// Save the error so we can mess with listing...
			elseif ($ftp->error !== false && !isset($upcontext['chmod']['ftp_error']))
				$upcontext['chmod']['ftp_error'] = $ftp->last_message === null ? '' : $ftp->last_message;

			list ($username, $detect_path, $found_path) = $ftp->detect_path(dirname(__FILE__));

			if ($found_path || !isset($upcontext['chmod']['path']))
				$upcontext['chmod']['path'] = $detect_path;

			if (!isset($upcontext['chmod']['username']))
				$upcontext['chmod']['username'] = $username;

			return false;
		}
		else
		{
			// We want to do a relative path for FTP.
			if (!in_array($upcontext['chmod']['path'], array('', '/')))
			{
				$ftp_root = strtr($boarddir, array($upcontext['chmod']['path'] => ''));
				if (substr($ftp_root, -1) == '/' && ($upcontext['chmod']['path'] == '' || $upcontext['chmod']['path'][0] === '/'))
				$ftp_root = substr($ftp_root, 0, -1);
			}
			else
				$ftp_root = $boarddir;

			// Save the info for next time!
			$_SESSION['installer_temp_ftp'] = array(
				'server' => $upcontext['chmod']['server'],
				'port' => $upcontext['chmod']['port'],
				'username' => $upcontext['chmod']['username'],
				'password' => $upcontext['chmod']['password'],
				'path' => $upcontext['chmod']['path'],
				'root' => $ftp_root,
			);

			foreach ($files as $k => $file)
			{
				if (!is_writable($file))
					$ftp->chmod($file, 0755);
				if (!is_writable($file))
					$ftp->chmod($file, 0777);

				// Assuming that didn't work calculate the path without the boarddir.
				if (!is_writable($file))
				{
					if (strpos($file, $boarddir) === 0)
					{
						$ftp_file = strtr($file, array($_SESSION['installer_temp_ftp']['root'] => ''));
						$ftp->chmod($ftp_file, 0755);
						if (!is_writable($file))
							$ftp->chmod($ftp_file, 0777);
						// Sometimes an extra slash can help...
						$ftp_file = '/' . $ftp_file;
						if (!is_writable($file))
							$ftp->chmod($ftp_file, 0755);
						if (!is_writable($file))
							$ftp->chmod($ftp_file, 0777);
					}
				}

				if (is_writable($file))
					unset($files[$k]);
			}

			$ftp->close();
		}
	}

	// What remains?
	$upcontext['chmod']['files'] = $files;

	if (empty($files))
		return true;

	return false;
}

/******************************************************************************
******************* Templates are below this point ****************************
******************************************************************************/

// This is what is displayed if there's any chmod to be done. If not it returns nothing...
function template_chmod()
{
	global $upcontext, $upgradeurl, $settings;

	// Don't call me twice!
	if (!empty($upcontext['chmod_called']))
		return;

	$upcontext['chmod_called'] = true;

	// Nothing?
	if (empty($upcontext['chmod']['files']) && empty($upcontext['chmod']['ftp_error']))
		return;

	// @todo Temporary!
	$txt['error_ftp_no_connect'] = 'Unable to connect to FTP server with this combination of details.';
	$txt['ftp_login'] = 'Your FTP connection information';
	$txt['ftp_login_info'] = 'This web installer needs your FTP information in order to automate the installation for you.  Please note that none of this information is saved in your installation, it is just used to setup SMF.';
	$txt['ftp_server'] = 'Server';
	$txt['ftp_server_info'] = 'The address (often localhost) and port for your FTP server.';
	$txt['ftp_port'] = 'Port';
	$txt['ftp_username'] = 'Username';
	$txt['ftp_username_info'] = 'The username to login with. <em>This will not be saved anywhere.</em>';
	$txt['ftp_password'] = 'Password';
	$txt['ftp_password_info'] = 'The password to login with. <em>This will not be saved anywhere.</em>';
	$txt['ftp_path'] = 'Install Path';
	$txt['ftp_path_info'] = 'This is the <em>relative</em> path you use in your FTP client <a href="' . $_SERVER['PHP_SELF'] . '?ftphelp" onclick="window.open(this.href, \'\', \'width=450,height=250\');return false;" target="_blank">(more help)</a>.';
	$txt['ftp_path_found_info'] = 'The path in the box above was automatically detected.';
	$txt['ftp_path_help'] = 'Your FTP path is the path you see when you log in to your FTP client.  It commonly starts with &quot;<tt>www</tt>&quot;, &quot;<tt>public_html</tt>&quot;, or &quot;<tt>httpdocs</tt>&quot; - but it should include the directory SMF is in too, such as &quot;/public_html/forum&quot;.  It is different from your URL and full path.<br /><br />Files in this path may be overwritten, so make sure it\'s correct.';
	$txt['ftp_path_help_close'] = 'Close';
	$txt['ftp_connect'] = 'Connect';

	// Was it a problem with Windows?
	if (!empty($upcontext['chmod']['ftp_error']) && $upcontext['chmod']['ftp_error'] == 'total_mess')
	{
		echo '
			<div class="error_message">
				<div style="color: red;">The following files need to be writable to continue the upgrade. Please ensure the Windows permissions are correctly set to allow this:</div>
				<ul style="margin: 2.5ex; font-family: monospace;">
				<li>' . implode('</li>
				<li>', $upcontext['chmod']['files']). '</li>
			</ul>
			</div>';

		return false;
	}

	echo '
		<div class="panel">
			<h2>Your FTP connection information</h2>
			<h3>The upgrader can fix any issues with file permissions to make upgrading as simple as possible. Simply enter your connection information below or alternatively click <a href="#" onclick="warning_popup();">here</a> for a list of files which need to be changed.</h3>
			<script type="text/javascript"><!-- // --><![CDATA[
				function warning_popup()
				{
					popup = window.open(\'\',\'popup\',\'height=150,width=400,scrollbars=yes\');
					var content = popup.document;
					content.write(\'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">\n\');
					content.write(\'<html xmlns="http://www.w3.org/1999/xhtml"', $upcontext['right_to_left'] ? ' dir="rtl"' : '', '>\n\t<head>\n\t\t<meta name="robots" content="noindex" />\n\t\t\');
					content.write(\'<title>Warning</title>\n\t\t<link rel="stylesheet" type="text/css" href="', $settings['default_theme_url'], '/css/index.css" />\n\t</head>\n\t<body id="popup">\n\t\t\');
					content.write(\'<div class="windowbg description">\n\t\t\t<h4>The following files needs to be made writable to continue:</h4>\n\t\t\t\');
					content.write(\'<p>', implode('<br />\n\t\t\t', $upcontext['chmod']['files']), '</p>\n\t\t\t\');
					content.write(\'<a href="javascript:self.close();">close</a>\n\t\t</div>\n\t</body>\n</html>\');
					content.close();
				}
		// ]]></script>';

	if (!empty($upcontext['chmod']['ftp_error']))
		echo '
			<div class="error_message">
				<div style="color: red;">
					The following error was encountered when trying to connect:<br />
					<br />
					<code>', $upcontext['chmod']['ftp_error'], '</code>
				</div>
			</div>
			<br />';

	if (empty($upcontext['chmod_in_form']))
		echo '
	<form action="', $upcontext['form_url'], '" method="post">';

	echo '
		<table width="520" cellspacing="0" cellpadding="0" border="0" align="center" style="margin-bottom: 1ex;">
			<tr>
				<td width="26%" valign="top" class="textbox"><label for="ftp_server">', $txt['ftp_server'], ':</label></td>
				<td>
					<div style="float: right; margin-right: 1px;"><label for="ftp_port" class="textbox"><strong>', $txt['ftp_port'], ':&nbsp;</strong></label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($upcontext['chmod']['port']) ? $upcontext['chmod']['port'] : '21', '" class="input_text" /></div>
					<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($upcontext['chmod']['server']) ? $upcontext['chmod']['server'] : 'localhost', '" style="width: 70%;" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', $txt['ftp_server_info'], '</div>
				</td>
			</tr><tr>
				<td width="26%" valign="top" class="textbox"><label for="ftp_username">', $txt['ftp_username'], ':</label></td>
				<td>
					<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset($upcontext['chmod']['username']) ? $upcontext['chmod']['username'] : '', '" style="width: 99%;" class="input_text" />
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
					<input type="text" size="50" name="ftp_path" id="ftp_path" value="', isset($upcontext['chmod']['path']) ? $upcontext['chmod']['path'] : '', '" style="width: 99%;" class="input_text" />
					<div style="font-size: smaller; margin-bottom: 2ex;">', !empty($upcontext['chmod']['path']) ? $txt['ftp_path_found_info'] : $txt['ftp_path_info'], '</div>
				</td>
			</tr>
		</table>

		<div class="righttext" style="margin: 1ex;"><input type="submit" value="', $txt['ftp_connect'], '" class="button_submit" /></div>
	</div>';

	if (empty($upcontext['chmod_in_form']))
		echo '
	</form>';
}

function template_upgrade_above()
{
	global $modSettings, $txt, $smfsite, $settings, $upcontext, $upgradeurl;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $upcontext['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', isset($txt['lang_character_set']) ? $txt['lang_character_set'] : 'ISO-8859-1', '" />
		<meta name="robots" content="noindex" />
		<title>', $txt['upgrade_upgrade_utility'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['default_theme_url'], '/css/index.css?alp21" />
		<link rel="stylesheet" type="text/css" href="', $settings['default_theme_url'], '/css/install.css?alp21" />
				<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			var smf_scripturl = \'', $upgradeurl, '\';
			var smf_charset = \'', (empty($modSettings['global_character_set']) ? (empty($txt['lang_character_set']) ? 'ISO-8859-1' : $txt['lang_character_set']) : $modSettings['global_character_set']), '\';
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
		// ]]></script>
	</head>
	<body>
	<div id="header"><div class="frame">
		<div id="top_section">
			<h1 class="forumtitle">', $txt['upgrade_upgrade_utility'], '</h1>
			<img id="smflogo" src="', $settings['default_theme_url'], '/images/smflogo.png" alt="Simple Machines Forum" title="Simple Machines Forum" />
		</div>
		<div id="upper_section" class="middletext flow_hidden">
			<div class="user"></div>
			<div class="news normaltext">
			</div>
		</div>
	</div></div>
	<div id="content_section"><div class="frame">
		<div id="main_content_section">
			<div id="main-steps">
				<h2>', $txt['upgrade_progress'], '</h2>
				<ul>';

	foreach ($upcontext['steps'] as $num => $step)
		echo '
						<li class="', $num < $upcontext['current_step'] ? 'stepdone' : ($num == $upcontext['current_step'] ? 'stepcurrent' : 'stepwaiting'), '">', $txt['upgrade_step'], ' ', $step[0], ': ', $step[1], '</li>';

	echo '
					</ul>
			</div>
			<div style="float: left; width: 40%;">
				<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; width: 50%; margin: auto;">
					<div id="overall_text" style="color: #000; position: absolute; margin-left: -5em;">', $upcontext['overall_percent'], '%</div>
					<div id="overall_progress" style="width: ', $upcontext['overall_percent'], '%; height: 12pt; z-index: 1; background-color: lime;">&nbsp;</div>
					<div class="progress">', $txt['upgrade_overall_progress'], '</div>
				</div>
				';

	if (isset($upcontext['step_progress']))
		echo '
				<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; width: 50%; margin: 5px auto; ">
					<div id="step_text" style="color: #000; position: absolute; margin-left: -5em;">', $upcontext['step_progress'], '%</div>
					<div id="step_progress" style="width: ', $upcontext['step_progress'], '%; height: 12pt; z-index: 1; background-color: #ffd000;">&nbsp;</div>
					<div class="progress">', $txt['upgrade_step_progress'], '</div>
				</div>
				';

	echo '
				<div id="substep_bar_div" class="smalltext" style="display: ', isset($upcontext['substep_progress']) ? '' : 'none', ';">', isset($upcontext['substep_progress_name']) ? trim(strtr($upcontext['substep_progress_name'], array('.' => ''))) : '', ':</div>
				<div id="substep_bar_div2" style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; width: 50%; margin: 5px auto; display: ', isset($upcontext['substep_progress']) ? '' : 'none', ';">
					<div id="substep_text" style="color: #000; position: absolute; margin-left: -5em;">', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : '', '%</div>
				<div id="substep_progress" style="width: ', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : 0, '%; height: 12pt; z-index: 1; background-color: #eebaf4;">&nbsp;</div>
								</div>';

	// How long have we been running this?
	$elapsed = time() - $upcontext['started'];
	$mins = (int) ($elapsed / 60);
	$seconds = $elapsed - $mins * 60;
	echo '
								<div class="smalltext" style="padding: 5px; text-align: center;">', $txt['upgrade_time_elapsed'], ':
									<span id="mins_elapsed">', $mins, '</span> ', $txt['upgrade_time_mins'], ', <span id="secs_elapsed">', $seconds, '</span> ', $txt['upgrade_time_secs'], '.
								</div>';
	echo '
			</div>
			<div id="main_screen" class="clear">
				<h2>', $upcontext['page_title'], '</h2>
				<div class="panel">
					<div style="max-height: 360px; overflow: auto;">';
}

function template_upgrade_below()
{
	global $upcontext, $txt;

	if (!empty($upcontext['pause']))
		echo '
								<em>', $txt['upgrade_incomplete'], '.</em><br />

								<h2 style="margin-top: 2ex;">', $txt['upgrade_not_quite_done'], '</h2>
								<h3>
									', $txt['upgrade_paused_overload'], '
								</h3>';

	if (!empty($upcontext['custom_warning']))
		echo '
								<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
									<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
									<strong style="text-decoration: underline;">', $txt['upgrade_note'], '</strong><br />
									<div style="padding-left: 6ex;">', $upcontext['custom_warning'], '</div>
								</div>';

	echo '
								<div class="righttext" style="margin: 1ex;">';

	if (!empty($upcontext['continue']))
		echo '
									<input type="submit" id="contbutt" name="contbutt" value="', $txt['upgrade_continue'], '"', $upcontext['continue'] == 2 ? ' disabled="disabled"' : '', ' class="button_submit" />';
	if (!empty($upcontext['skip']))
		echo '
									<input type="submit" id="skip" name="skip" value="', $txt['upgrade_skip'], '" onclick="dontSubmit = true; document.getElementById(\'contbutt\').disabled = \'disabled\'; return true;" class="button_submit" />';

	echo '
								</div>
							</form>
						</div>
				</div>
			</div>
		</div>
	</div></div>
	<div id="footer_section"><div class="frame" style="height: 40px;">
		<div class="smalltext"><a href="http://www.simplemachines.org/" title="Simple Machines Forum" target="_blank" class="new_win">SMF &copy;2011, Simple Machines</a></div>
	</div></div>
	</body>
</html>';

	// Are we on a pause?
	if (!empty($upcontext['pause']))
	{
		echo '
		<script type="text/javascript"><!-- // --><![CDATA[
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
		// ]]></script>';
	}
}

function template_xml_above()
{
	global $upcontext;

	echo '<', '?xml version="1.0" encoding="ISO-8859-1"?', '>
	<smf>';

	if (!empty($upcontext['get_data']))
		foreach ($upcontext['get_data'] as $k => $v)
			echo '
		<get key="', $k, '">', $v, '</get>';
}

function template_xml_below()
{
	global $upcontext;

	echo '
		</smf>';
}

function template_error_message()
{
	global $upcontext;

	echo '
	<div class="error_message">
		<div style="color: red;">
			', $upcontext['error_msg'], '
		</div>
		<br />
		<a href="', $_SERVER['PHP_SELF'], '">Click here to try again.</a>
	</div>';
}

function template_welcome_message()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $txt;

	echo '
		<script type="text/javascript" src="http://www.simplemachines.org/smf/current-version.js?version=' . SMF_VERSION . '"></script>
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
			<h3>', sprintf($txt['upgrade_ready_proceed'], SMF_VERSION), '</h3>
	<form action="', $upcontext['form_url'], '" method="post" name="upform" id="upform" ', empty($upcontext['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $upcontext['rid'] . '\', \'' . (!empty($upcontext['login_token']) ? $upcontext['login_token'] : '') . '\');"' : '', '>
		<input type="hidden" name="', $upcontext['login_token_var'], '" value="', $upcontext['login_token'], '" />
		<div id="version_warning" style="margin: 2ex; padding: 2ex; border: 2px dashed #a92174; color: black; background-color: #fbbbe2; display: none;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong><br />
			<div style="padding-left: 6ex;">
				', sprintf($txt['upgrade_warning_out_of_date'], SMF_VERSION), '
			</div>
		</div>';

	$upcontext['chmod_in_form'] = true;
	template_chmod();

	// For large, pre 1.1 RC2 forums give them a warning about the possible impact of this upgrade!
	if ($upcontext['is_large_forum'])
		echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong><br />
			<div style="padding-left: 6ex;">
				', $txt['upgrade_warning_lots_data'], '
			</div>
		</div>';

	// A warning message?
	if (!empty($upcontext['warning']))
		echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong><br />
			<div style="padding-left: 6ex;">
				', $upcontext['warning'], '
			</div>
		</div>';

	// Paths are incorrect?
	echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #804840; color: black; background-color: #fe5a44; ', (file_exists($settings['default_theme_dir'] . '/scripts/script.js') ? 'display: none;' : ''), '" id="js_script_missing_error">
			<div style="float: left; width: 2ex; font-size: 2em; color: black;">!!</div>
			<strong style="text-decoration: underline;">', $txt['upgrade_critical_error'], '</strong><br />
			<div style="padding-left: 6ex;">
				', $txt['upgrade_error_script_js'], '
			</div>
		</div>';

	// Is there someone already doing this?
	if (!empty($upcontext['user']['id']) && (time() - $upcontext['started'] < 72600 || time() - $upcontext['updated'] < 3600))
	{
		$ago = time() - $upcontext['started'];
		if ($ago < 60)
			$ago = $ago . ' seconds';
		elseif ($ago < 3600)
			$ago = (int) ($ago / 60) . ' minutes';
		else
			$ago = (int) ($ago / 3600) . ' hours';

		$active = time() - $upcontext['updated'];
		if ($active < 60)
			$updated = $active . ' seconds';
		elseif ($active < 3600)
			$updated = (int) ($active / 60) . ' minutes';
		else
			$updated = (int) ($active / 3600) . ' hours';

		echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">', $txt['upgrade_warning'], '</strong><br />
			<div style="padding-left: 6ex;">
				&quot;', $upcontext['user']['name'], '&quot; has been running the upgrade script for the last ', $ago, ' - and was last active ', $updated, ' ago.';

		if ($active < 600)
			echo '
				We recommend that you do not run this script unless you are sure that ', $upcontext['user']['name'], ' has completed their upgrade.';

		if ($active > $upcontext['inactive_timeout'])
			echo '
				<br /><br />You can choose to either run the upgrade again from the beginning - or alternatively continue from the last step reached during the last upgrade.';
		else
			echo '
				<br /><br />This upgrade script cannot be run until ', $upcontext['user']['name'], ' has been inactive for at least ', ($upcontext['inactive_timeout'] > 120 ? round($upcontext['inactive_timeout'] / 60, 1) . ' minutes!' : $upcontext['inactive_timeout'] . ' seconds!');

		echo '
			</div>
		</div>';
	}

	echo '
			<strong>Admin Login: ', $disable_security ? '(DISABLED)' : '', '</strong>
			<h3>For security purposes please login with your admin account to proceed with the upgrade.</h3>
			<table>
				<tr valign="top">
					<td><strong ', $disable_security ? 'style="color: gray;"' : '', '>Username:</strong></td>
					<td>
						<input type="text" name="user" value="', !empty($upcontext['username']) ? $upcontext['username'] : '', '" ', $disable_security ? 'disabled="disabled"' : '', ' class="input_text" />';

	if (!empty($upcontext['username_incorrect']))
		echo '
						<div class="smalltext" style="color: red;">Username Incorrect</div>';

	echo '
					</td>
				</tr>
				<tr valign="top">
					<td><strong ', $disable_security ? 'style="color: gray;"' : '', '>Password:</strong></td>
					<td>
						<input type="password" name="passwrd" value=""', $disable_security ? ' disabled="disabled"' : '', ' class="input_password" />
						<input type="hidden" name="hash_passwrd" value="" />';

	if (!empty($upcontext['password_failed']))
		echo '
						<div class="smalltext" style="color: red;">Password Incorrect</div>';

	echo '
					</td>
				</tr>';

	// Can they continue?
	if (!empty($upcontext['user']['id']) && time() - $upcontext['user']['updated'] >= $upcontext['inactive_timeout'] && $upcontext['user']['step'] > 1)
	{
		echo '
				<tr>
					<td colspan="2">
						<label for="cont"><input type="checkbox" id="cont" name="cont" checked="checked" class="input_check" />Continue from step reached during last execution of upgrade script.</label>
					</td>
				</tr>';
	}

	echo '
			</table><br />
			<span class="smalltext">
				<strong>Note:</strong> If necessary the above security check can be bypassed for users who may administrate a server but not have admin rights on the forum. In order to bypass the above check simply open &quot;upgrade.php&quot; in a text editor and replace &quot;$disable_security = 0;&quot; with &quot;$disable_security = 1;&quot; and refresh this page.
			</span>
			<input type="hidden" name="login_attempt" id="login_attempt" value="1" />
			<input type="hidden" name="js_works" id="js_works" value="0" />';

	// Say we want the continue button!
	$upcontext['continue'] = !empty($upcontext['user']['id']) && time() - $upcontext['user']['updated'] < $upcontext['inactive_timeout'] ? 2 : 1;

	// This defines whether javascript is going to work elsewhere :D
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
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
					document.getElementById(\'version_warning\').style.display = \'\';
			}
			addLoadEvent(smfCurrentVersion);

			// This checks that the script file even exists!
			if (typeof(smfSelectText) == \'undefined\')
				document.getElementById(\'js_script_missing_error\').style.display = \'\';

		// ]]></script>';
}

function template_upgrade_options()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $boarddir, $db_prefix, $mmessage, $mtitle, $db_type;

	echo '
			<h3>Before the upgrade gets underway please review the options below - and hit continue when you\'re ready to begin.</h3>
			<form action="', $upcontext['form_url'], '" method="post" name="upform" id="upform">';

	// Warning message?
	if (!empty($upcontext['upgrade_options_warning']))
		echo '
		<div style="margin: 1ex; padding: 1ex; border: 1px dashed #cc3344; color: black; background-color: #ffe4e9;">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">Warning!</strong><br />
			<div style="padding-left: 4ex;">
				', $upcontext['upgrade_options_warning'], '
			</div>
		</div>';

	echo '
				<table cellpadding="1" cellspacing="0">
					<tr valign="top">
						<td width="2%">
							<input type="checkbox" name="backup" id="backup" value="1"', $db_type != 'mysql' && $db_type != 'postgresql' ? ' disabled="disabled"' : '', ' class="input_check" />
						</td>
						<td width="100%">
							<label for="backup">Backup tables in your database with the prefix &quot;backup_' . $db_prefix . '&quot;.</label>', isset($modSettings['smfVersion']) ? '' : ' (recommended!)', '
						</td>
					</tr>
					<tr valign="top">
						<td width="2%">
							<input type="checkbox" name="maint" id="maint" value="1" checked="checked" class="input_check" />
						</td>
						<td width="100%">
							<label for="maint">Put the forum into maintenance mode during upgrade.</label> <span class="smalltext">(<a href="#" onclick="document.getElementById(\'mainmess\').style.display = document.getElementById(\'mainmess\').style.display == \'\' ? \'none\' : \'\'">Customize</a>)</span>
							<div id="mainmess" style="display: none;">
								<strong class="smalltext">Maintenance Title: </strong><br />
								<input type="text" name="maintitle" size="30" value="', htmlspecialchars($mtitle), '" class="input_text" /><br />
								<strong class="smalltext">Maintenance Message: </strong><br />
								<textarea name="mainmessage" rows="3" cols="50">', htmlspecialchars($mmessage), '</textarea>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<td width="2%">
							<input type="checkbox" name="debug" id="debug" value="1" class="input_check" />
						</td>
						<td width="100%">
							<label for="debug">Output extra debugging information</label>
						</td>
					</tr>
					<tr valign="top">
						<td width="2%">
							<input type="checkbox" name="empty_error" id="empty_error" value="1" class="input_check" />
						</td>
						<td width="100%">
							<label for="empty_error">Empty error log before upgrading</label>
						</td>
					</tr>
					<tr valign="top">
						<td width="2%">
							<input type="checkbox" name="stats" id="stats" value="1"', empty($modSettings['allow_sm_stats']) ? '' : ' checked="checked"', ' class="input_check" />
						</td>
						<td width="100%">
							<label for="stats">
								Allow Simple Machines to Collect Basic Stats Monthly.<br />
								<span class="smalltext">If enabled, this will allow Simple Machines to visit your site once a month to collect basic statistics. This will help us make decisions as to which configurations to optimise the software for. For more information please visit our <a href="http://www.simplemachines.org/about/stats.php" target="_blank">info page</a>.</span>
							</label>
						</td>
					</tr>
				</table>
				<input type="hidden" name="upcont" value="1" />';

	// We need a normal continue button here!
	$upcontext['continue'] = 1;
}

// Template for the database backup tool/
function template_backup_database()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $support_js, $is_debug;

	echo '
			<h3>Please wait while a backup is created. For large forums this may take some time!</h3>';

	echo '
			<form action="', $upcontext['form_url'], '" name="upform" id="upform" method="post">
			<input type="hidden" name="backup_done" id="backup_done" value="0" />
			<strong>Completed <span id="tab_done">', $upcontext['cur_table_num'], '</span> out of ', $upcontext['table_count'], ' tables.</strong>
			<span id="debuginfo"></span>';

	// Dont any tables so far?
	if (!empty($upcontext['previous_tables']))
		foreach ($upcontext['previous_tables'] as $table)
			echo '
			<br />Completed Table: &quot;', $table, '&quot;.';

	echo '
			<h3 id="current_tab_div">Current Table: &quot;<span id="current_table">', $upcontext['cur_table_name'], '</span>&quot;</h3>
			<br /><span id="commess" style="font-weight: bold; display: ', $upcontext['cur_table_num'] == $upcontext['table_count'] ? 'inline' : 'none', ';">Backup Complete! Click Continue to Proceed.</span>';

	// Continue please!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
		<script type="text/javascript"><!-- // --><![CDATA[
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
				setOuterHTML(document.getElementById(\'debuginfo\'), \'<br />Completed Table: &quot;\' + sCompletedTableName + \'&quot;.<span id="debuginfo"><\' + \'/span>\');';

		echo '
				// Get the next update...
				if (iTableNum == ', $upcontext['table_count'], ')
				{
					document.getElementById(\'commess\').style.display = "";
					document.getElementById(\'current_tab_div\').style.display = "none";
					document.getElementById(\'contbutt\').disabled = 0;
					document.getElementById(\'backup_done\').value = 1;
				}
				else
					getNextTables();
			}
			getNextTables();
		// ]]></script>';
	}
}

function template_backup_xml()
{
	global $upcontext, $settings, $options, $txt;

	echo '
	<table num="', $upcontext['cur_table_num'], '">', $upcontext['cur_table_name'], '</table>';
}

// Here is the actual "make the changes" template!
function template_database_changes()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $support_js, $is_debug, $timeLimitThreshold;

	echo '
		<h3>Executing database changes</h3>
		<h4 style="font-style: italic;">Please be patient - this may take some time on large forums. The time elapsed increments from the server to show progress is being made!</h4>';

	echo '
		<form action="', $upcontext['form_url'], '&amp;filecount=', $upcontext['file_count'], '" name="upform" id="upform" method="post">
		<input type="hidden" name="database_done" id="database_done" value="0" />';

	// No javascript looks rubbish!
	if (!$support_js)
	{
		foreach ($upcontext['actioned_items'] as $num => $item)
		{
			if ($num != 0)
				echo ' Successful!';
			echo '<br />' . $item;
		}
		if (!empty($upcontext['changes_complete']))
			echo ' Successful!<br /><br /><span id="commess" style="font-weight: bold;">Database Updates Complete! Click Continue to Proceed.</span><br />';
	}
	else
	{
		// Tell them how many files we have in total.
		if ($upcontext['file_count'] > 1)
			echo '
		<strong id="info1">Executing upgrade script <span id="file_done">', $upcontext['cur_file_num'], '</span> of ', $upcontext['file_count'], '.</strong>';

		echo '
		<h3 id="info2"><strong>Executing:</strong> &quot;<span id="cur_item_name">', $upcontext['current_item_name'], '</span>&quot; (<span id="item_num">', $upcontext['current_item_num'], '</span> of <span id="total_items"><span id="item_count">', $upcontext['total_items'], '</span>', $upcontext['file_count'] > 1 ? ' - of this script' : '', ')</span></h3>
		<br /><span id="commess" style="font-weight: bold; display: ', !empty($upcontext['changes_complete']) || $upcontext['current_debug_item_num'] == $upcontext['debug_items'] ? 'inline' : 'none', ';">Database Updates Complete! Click Continue to Proceed.</span>';

		if ($is_debug)
		{
			echo '
			<div id="debug_section" style="height: 200px; overflow: auto;">
			<span id="debuginfo"></span>
			</div>';
		}
	}

	// Place for the XML error message.
	echo '
		<div id="error_block" style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9; display: ', empty($upcontext['error_message']) ? 'none' : '', ';">
			<div style="float: left; width: 2ex; font-size: 2em; color: red;">!!</div>
			<strong style="text-decoration: underline;">Error!</strong><br />
			<div style="padding-left: 6ex;" id="error_message">', isset($upcontext['error_message']) ? $upcontext['error_message'] : 'Unknown Error!', '</div>
		</div>';

	// We want to continue at some point!
	$upcontext['continue'] = $support_js ? 2 : 1;

	// If javascript allows we want to do this using XML.
	if ($support_js)
	{
		echo '
		<script type="text/javascript"><!-- // --><![CDATA[
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
			var debugItems = ', $upcontext['debug_items'], ';
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
				getData = "";

				// We\'ve got something - so reset the timeout!
				if (timeOutID)
					clearTimeout(timeOutID);

				// Assume no error at this time...
				document.getElementById("error_block").style.display = "none";

				// Are we getting some duff info?
				if (!oXMLDoc.getElementsByTagName("item")[0])
				{
					// Too many errors?
					if (retryCount > 15)
					{
						document.getElementById("error_block").style.display = "";
						setInnerHTML(document.getElementById("error_message"), "Error retrieving information on step: " + (sDebugName == "" ? sLastString : sDebugName));';

	if ($is_debug)
		echo '
						setOuterHTML(document.getElementById(\'debuginfo\'), \'<span style="color: red;">failed<\' + \'/span><span id="debuginfo"><\' + \'/span>\');';

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
						document.getElementById("error_block").style.display = "";
						setInnerHTML(document.getElementById("error_message"), "Upgrade script appears to be going into a loop - step: " + sDebugName);';

	if ($is_debug)
		echo '
						setOuterHTML(document.getElementById(\'debuginfo\'), \'<span style="color: red;">failed<\' + \'/span><span id="debuginfo"><\' + \'/span>\');';

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
					document.getElementById("substep_bar_div").style.display = "";
					document.getElementById("substep_bar_div2").style.display = "";
					document.getElementById("substep_progress").style.width = iSubStepProgress + "%";
					setInnerHTML(document.getElementById("substep_text"), iSubStepProgress + "%");
					setInnerHTML(document.getElementById("substep_bar_div"), sDebugName.replace(/\./g, "") + ":");
				}
				else
				{
					document.getElementById("substep_bar_div").style.display = "none";
					document.getElementById("substep_bar_div2").style.display = "none";
				}

				// Move onto the next item?
				if (bIsComplete)
					lastItem = iDebugNum;
				else
					lastItem = iDebugNum - 1;

				// Are we finished?
				if (bIsComplete && iDebugNum == -1 && curFile >= ', $upcontext['file_count'], ')
				{';

		if ($is_debug)
			echo '
					document.getElementById(\'debug_section\').style.display = "none";';

		echo '

					document.getElementById(\'commess\').style.display = "";
					document.getElementById(\'contbutt\').disabled = 0;
					document.getElementById(\'database_done\').value = 1;';

		if ($upcontext['file_count'] > 1)
			echo '
					document.getElementById(\'info1\').style.display = "none";';

		echo '
					document.getElementById(\'info2\').style.display = "none";
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
					setOuterHTML(document.getElementById(\'debuginfo\'), \'Moving to next script file...done<br /><span id="debuginfo"><\' + \'/span>\');';

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

				if (bIsComplete)
					setOuterHTML(document.getElementById(\'debuginfo\'), \'done<br /><span id="debuginfo"><\' + \'/span>\');
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
					document.getElementById("error_block").style.display = "";
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
					document.getElementById("error_block").style.display = "";
					setInnerHTML(document.getElementById("error_message"), "Server has not responded for ', ($timeLimitThreshold * 10), ' seconds. It may be worth waiting a little longer or otherwise please click <a href=\"#\" onclick=\"retTimeout(true); return false;\">here<" + "/a> to try this step again");
				}
				else
				{
					document.getElementById("error_block").style.display = "none";
					getNextItem();
				}
			}';

		// Start things off assuming we've not errored.
		if (empty($upcontext['error_message']))
			echo '
			getNextItem();';

		echo '
		// ]]></script>';
	}
	return;
}

function template_database_xml()
{
	global $upcontext, $settings, $options, $txt;

	echo '
	<file num="', $upcontext['cur_file_num'], '" items="', $upcontext['total_items'], '" debug_items="', $upcontext['debug_items'], '">', $upcontext['cur_file_name'], '</file>
	<item num="', $upcontext['current_item_num'], '">', $upcontext['current_item_name'], '</item>
	<debug num="', $upcontext['current_debug_item_num'], '" percent="', isset($upcontext['substep_progress']) ? $upcontext['substep_progress'] : '-1', '" complete="', empty($upcontext['completed_step']) ? 0 : 1, '">', $upcontext['current_debug_item_name'], '</debug>';

	if (!empty($upcontext['error_message']))
		echo '
	<error>', $upcontext['error_message'], '</error>';
}

function template_clean_mods()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $boarddir, $db_prefix, $boardurl;

	$upcontext['chmod_in_form'] = true;

	echo '
	<h3>SMF has detected some packages which were installed but not fully removed prior to upgrade. We recommend you remove the following mods and reinstall upon completion of the upgrade.</h3>
	<form action="', $upcontext['form_url'], '&amp;ssi=1" name="upform" id="upform" method="post">';

	// In case it's required.
	template_chmod();

	echo '
		<table width="90%" align="center" cellspacing="1" cellpadding="2" style="background-color: black;">
			<tr style="background-color: #eeeeee;">
				<td width="40%"><strong>Modification Name</strong></td>
				<td width="10%" align="center"><strong>Version</strong></td>
				<td width="15%"><strong>Files Affected</strong></td>
				<td width="20%"><strong>Status</strong></td>
				<td width="5%" align="center"><strong>Fix?</strong></td>
			</tr>';

	foreach ($upcontext['packages'] as $package)
	{
		echo '
			<tr style="background-color: #cccccc;">
				<td width="40%">', $package['name'], '</td>
				<td width="10%">', $package['version'], '</td>
				<td width="15%">', $package['file_count'], ' <span class="smalltext">[<a href="#" onclick="alert(\'The following files are affected by this modification:\\n\\n', strtr(implode('<br />', $package['files']), array('\\' => '\\\\', '<br />' => '\\n')), '\'); return false;">details</a>]</td>
				<td width="20%"><span style="font-weight: bold; color: ', $package['color'], '">', $package['status'], '</span></td>
				<td width="5%" align="center">
					<input type="hidden" name="remove[', $package['id'], ']" value="0" />
					<input type="checkbox" name="remove[', $package['id'], ']"', $package['color'] == 'green' ? ' disabled="disabled"' : '', ' class="input_check" />
				</td>
			</tr>';
	}
	echo '
		</table>
		<input type="hidden" name="cleandone" value="1" />';

	// Files to make writable?
	if (!empty($upcontext['writable_files']))
		echo '
		<input type="hidden" name="writable_files" value="', base64_encode(serialize($upcontext['writable_files'])), '" />';

	// We'll want a continue button...
	if (empty($upcontext['chmod']['files']))
		$upcontext['continue'] = 1;
}

// Finished with the mods - let them know what we've done.
function template_cleanup_done()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $boarddir, $db_prefix, $boardurl;

	echo '
	<h3>SMF has attempted to fix and reinstall mods as required. We recommend you visit the package manager upon completing upgrade to check the status of your modifications.</h3>
	<form action="', $upcontext['form_url'], '&amp;ssi=1" name="upform" id="upform" method="post">
		<table width="90%" align="center" cellspacing="1" cellpadding="2" style="background-color: black;">
			<tr style="background-color: #eeeeee;">
				<td width="100%"><strong>Actions Completed:</strong></td>
			</tr>';

	foreach ($upcontext['packages'] as $package)
	{
		echo '
			<tr style="background-color: #cccccc;">
				<td>', $package['name'], '... <span style="font-weight: bold; color: ', $package['color'], ';">', $package['result'], '</span></td>
			</tr>';
	}
	echo '
		</table>
		<input type="hidden" name="cleandone2" value="1" />';

	// We'll want a continue button...
	$upcontext['continue'] = 1;
}

// Do they want to upgrade their templates?
function template_upgrade_templates()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $boarddir, $db_prefix, $boardurl;

	echo '
	<h3>There have been numerous language and template changes since the previous version of SMF. On this step the upgrader can attempt to automatically make these changes in your templates to save you from doing so manually.</h3>
	<form action="', $upcontext['form_url'], '&amp;ssi=1', $upcontext['is_test'] ? '' : ';forreal=1', '" name="upform" id="upform" method="post">';

	// Any files need to be writable?
	$upcontext['chmod_in_form'] = true;
	template_chmod();

	// Language/Template files need an update?
	if ($upcontext['temp_progress'] == 0 && !$upcontext['is_test'] && (!empty($upcontext['languages']) || !empty($upcontext['themes'])))
	{
		echo '
		The following template files will be updated to ensure they are compatible with this version of SMF. Note that this can only fix a limited number of compatibility issues and in general you should seek out the latest version of these themes/language files.
		<table width="90%" align="center" cellspacing="1" cellpadding="2" style="background-color: black;">
			<tr style="background-color: #eeeeee;">
				<td width="80%"><strong>Area</strong></td>
				<td width="20%" align="center"><strong>Changes Required</strong></td>
			</tr>';

		foreach ($upcontext['languages'] as $language)
		{
			echo '
				<tr style="background-color: #cccccc;">
					<td width="80%">
						&quot;', $language['name'], '&quot; Language Pack
						<div class="smalltext">(';

			foreach ($language['files'] as $k => $file)
				echo $file['name'], $k + 1 != count($language['files']) ? ', ' : ')';

			echo '
						</div>
					</td>
					<td width="20%" align="center">', $language['edit_count'] == 0 ? 1 : $language['edit_count'], '</td>
				</tr>';
		}

		foreach ($upcontext['themes'] as $theme)
		{
			echo '
				<tr style="background-color: #CCCCCC;">
					<td width="80%">
						&quot;', $theme['name'], '&quot; Theme
						<div class="smalltext">(';

			foreach ($theme['files'] as $k => $file)
				echo $file['name'], $k + 1 != count($theme['files']) ? ', ' : ')';

			echo '
						</div>
					</td>
					<td width="20%" align="center">', $theme['edit_count'] == 0 ? 1 : $theme['edit_count'], '</td>
				</tr>';
		}

		echo '
		</table>';
	}
	else
	{
		$langFiles = 0;
		$themeFiles = 0;
		if (!empty($upcontext['languages']))
			foreach ($upcontext['languages'] as $lang)
				$langFiles += count($lang['files']);
		if (!empty($upcontext['themes']))
			foreach ($upcontext['themes'] as $theme)
				$themeFiles += count($theme['files']);
		echo sprintf('Found <strong>%d</strong> language files and <strong>%d</strong> templates requiring an update so far.', $langFiles, $themeFiles) . '<br />';

		// What we're currently doing?
		if (!empty($upcontext['current_message']))
			echo '
				', $upcontext['current_message'];
	}

	echo '
		<input type="hidden" name="uptempdone" value="1" />';

	if (!empty($upcontext['languages']))
		echo '
		<input type="hidden" name="languages" value="', base64_encode(serialize($upcontext['languages'])), '" />';
	if (!empty($upcontext['themes']))
		echo '
		<input type="hidden" name="themes" value="', base64_encode(serialize($upcontext['themes'])), '" />';
	if (!empty($upcontext['writable_files']))
		echo '
		<input type="hidden" name="writable_files" value="', base64_encode(serialize($upcontext['writable_files'])), '" />';

	// Offer them the option to upgrade from YaBB SE?
	if (!empty($upcontext['can_upgrade_yabbse']))
		echo '
		<br /><label for="conv"><input type="checkbox" name="conv" id="conv" value="1" class="input_check" /> Convert the existing YaBB SE template and set it as default.</label><br />';

	// We'll want a continue button... assuming chmod is OK (Otherwise let them use connect!)
	if (empty($upcontext['chmod']['files']) || $upcontext['is_test'])
		$upcontext['continue'] = 1;
}

function template_upgrade_complete()
{
	global $upcontext, $modSettings, $upgradeurl, $disable_security, $settings, $boarddir, $db_prefix, $boardurl;

	echo '
	<h3>That wasn\'t so hard, was it?  Now you are ready to use <a href="', $boardurl, '/index.php">your installation of SMF</a>.  Hope you like it!</h3>
	<form action="', $boardurl, '/index.php">';

	if (!empty($upcontext['can_delete_script']))
		echo '
			<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete(this);" class="input_check" /> Delete this upgrade.php and its data files now.</label> <em>(doesn\'t work on all servers.)</em>
			<script type="text/javascript"><!-- // --><![CDATA[
				function doTheDelete(theCheck)
				{
					var theImage = document.getElementById ? document.getElementById("delete_upgrader") : document.all.delete_upgrader;

					theImage.src = "', $upgradeurl, '?delete=1&ts_" + (new Date().getTime());
					theCheck.disabled = true;
				}
			// ]]></script>
			<img src="', $settings['default_theme_url'], '/images/blank.png" alt="" id="delete_upgrader" /><br />';

	echo '<br />
			If you had any problems with this upgrade, or have any problems using SMF, please don\'t hesitate to <a href="http://www.simplemachines.org/community/index.php">look to us for assistance</a>.<br />
			<br />
			Best of luck,<br />
			Simple Machines';
}

?>