<?php

/**
 * This is a slightly strange file. It is not designed to ever be run directly from within SMF's
 * conventional running, but called externally to facilitate background tasks. It can be called
 * either directly or via cron, and in either case will completely ignore anything supplied
 * via command line, or $_GET, $_POST, $_COOKIE etc. because those things should never affect the
 * running of this script.
 *
 * Because of the way this runs, etc. we do need some of SMF but not everything to try to keep this
 * running a little bit faster.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

define('SMF', 'BACKGROUND');
define('FROM_CLI', empty($_SERVER['REQUEST_METHOD']));

// This one setting is worth bearing in mind. If you are running this from proper cron, make sure you
// don't run this file any more frequently than indicated here. It might turn ugly if you do.
// But on proper cron you can always increase this value provided you don't go beyond max_limit.
define('MAX_CRON_TIME', 10);
// If a task fails for whatever reason it will still be marked as claimed. This is the threshold
// by which if a task has not completed in this time, the task should become available again.
define('MAX_CLAIM_THRESHOLD', 300);

// We're going to want a few globals... these are all set later.
global $time_start, $maintenance, $msubject, $mmessage, $mbname, $language;
global $boardurl, $boarddir, $sourcedir, $webmaster_email;
global $db_server, $db_name, $db_user, $db_prefix, $db_persist, $db_error_send, $db_last_error;
global $db_connection, $modSettings, $context, $sc, $user_info, $txt;
global $smcFunc, $ssi_db_user, $scripturl, $db_passwd, $cachedir;

define('TIME_START', microtime(true));

// Just being safe...
foreach (array('db_character_set', 'cachedir') as $variable)
	if (isset($GLOBALS[$variable]))
		unset($GLOBALS[$variable]);

// Get the forum's settings for database and file paths.
require_once(dirname(__FILE__) . '/Settings.php');

// Make absolutely sure the cache directory is defined.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

// Don't do john didley if the forum's been shut down completely.
if ($maintenance == 2)
	die($mmessage);

// Fix for using the current directory as a path.
if (substr($sourcedir, 0, 1) == '.' && substr($sourcedir, 1, 1) != '.')
	$sourcedir = dirname(__FILE__) . substr($sourcedir, 1);

// Have we already turned this off? If so, exist gracefully.
if (file_exists($cachedir . '/cron.lock'))
	obExit_cron();

// Before we go any further, if this is not a CLI request, we need to do some checking.
if (!FROM_CLI)
{
	// We will clean up $_GET shortly. But we want to this ASAP.
	$ts = isset($_GET['ts']) ? (int) $_GET['ts'] : 0;
	if ($ts <= 0 || $ts % 15 != 0 || time() - $ts < 0 || time() - $ts > 20)
		obExit_cron();
}

// Load the most important includes. In general, a background should be loading its own dependencies.
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Subs.php');

// Create a variable to store some SMF specific functions in.
$smcFunc = array();

// This is our general bootstrap, a la SSI.php but with a few differences.
unset ($db_show_debug);
loadDatabase();
reloadSettings();

// Just in case there's a problem...
set_error_handler('smf_error_handler_cron');
$sc = '';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['REQUEST_URL'] = FROM_CLI ? 'CLI cron.php' : $boardurl . '/cron.php';

// Now 'clean the request' (or more accurately, ignore everything we're not going to use)
cleanRequest_cron();

// At this point we could reseed the RNG but I don't think we need to risk it being seeded *even more*.
// Meanwhile, time we got on with the real business here.
while ($task_details = fetch_task())
{
	$result = perform_task($task_details);
	if ($result)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}background_tasks
			WHERE id_task = {int:task}',
			array(
				'task' => $task_details['id_task'],
			)
		);
	}
}
obExit_cron();
exit;

/**
 * The heart of this cron handler...
 *
 * @return bool|array False if there's nothing to do or an array of info about the task
 */
function fetch_task()
{
	global $smcFunc;

	// Check we haven't run over our time limit.
	if (microtime(true) - TIME_START > MAX_CRON_TIME)
		return false;

	// Try to find a task. Specifically, try to find one that hasn't been claimed previously, or failing that,
	// a task that was claimed but failed for whatever reason and failed long enough ago. We should not care
	// what task it is, merely that it is one in the queue, the order is irrelevant.
	$request = $smcFunc['db_query']('', '
		SELECT id_task, task_file, task_class, task_data, claimed_time
		FROM {db_prefix}background_tasks
		WHERE claimed_time < {int:claim_limit}
		LIMIT 1',
		array(
			'claim_limit' => time() - MAX_CLAIM_THRESHOLD,
		)
	);
	if ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We found one. Let's try and claim it immediately.
		$smcFunc['db_free_result']($request);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}background_tasks
			SET claimed_time = {int:new_claimed}
			WHERE id_task = {int:task}
				AND claimed_time = {int:old_claimed}',
			array(
				'new_claimed' => time(),
				'task' => $row['id_task'],
				'old_claimed' => $row['claimed_time'],
			)
		);
		// Could we claim it? If so, return it back.
		if ($smcFunc['db_affected_rows']() != 0)
		{
			// Update the time and go back.
			$row['claimed_time'] = time();
			return $row;
		}
		else
		{
			// Uh oh, we just missed it. Try to claim another one, and let it fall through if there aren't any.
			return fetch_task();
		}
	}
	else
	{
		// No dice. Clean up and go home.
		$smcFunc['db_free_result']($request);
		return false;
	}
}

/**
 * This actually handles the task
 *
 * @param array $task_details An array of info about the task
 * @return bool|void True if the task is invalid; otherwise calls the function to execute the task
 */
function perform_task($task_details)
{
	global $smcFunc, $sourcedir, $boarddir;

	// This indicates the file to load.
	if (!empty($task_details['task_file']))
	{
		$include = strtr(trim($task_details['task_file']), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir));
		if (file_exists($include))
			require_once($include);
	}

	if (empty($task_details['task_class']))
	{
		// This would be nice to translate but the language files aren't loaded for any specific language.
		log_error('Invalid background task specified (no class, ' . (empty($task_details['task_file']) ? ' no file' : ' to load ' . $task_details['task_file']) . ')');
		return true; // So we clear it from the queue.
	}

	// All background tasks need to be classes.
	elseif (class_exists($task_details['task_class']) && is_subclass_of($task_details['task_class'], 'SMF_BackgroundTask'))
	{
		$details = empty($task_details['task_data']) ? array() : $smcFunc['json_decode']($task_details['task_data'], true);
		$bgtask = new $task_details['task_class']($details);
		return $bgtask->execute();
	}
	else
	{
		log_error('Invalid background task specified: (class: ' . $task_details['task_class'] . ', ' . (empty($task_details['task_file']) ? ' no file' : ' to load ' . $task_details['task_file']) . ')');
		return true; // So we clear it from the queue.
	}
}

// These are all our helper functions that resemble their big brother counterparts. These are not so important.
/**
 * Cleans up the request variables
 *
 * @return void
 */
function cleanRequest_cron()
{
	global $scripturl, $boardurl;

	$scripturl = $boardurl . '/index.php';

	// These keys shouldn't be set...ever.
	if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
		die('Invalid request variable.');

	// Save some memory.. (since we don't use these anyway.)
	unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_VARS']);
	unset($GLOBALS['HTTP_POST_FILES'], $GLOBALS['HTTP_POST_FILES']);
	unset($GLOBALS['_GET'], $GLOBALS['_POST'], $GLOBALS['_REQUEST'], $GLOBALS['_COOKIE'], $GLOBALS['_FILES']);
}

/**
 * The error handling function
 *
 * @param int $error_level One of the PHP error level constants (see )
 * @param string $error_string The error message
 * @param string $file The file where the error occurred
 * @param int $line What line of the specified file the error occurred on
 * @return void
 */
function smf_error_handler_cron($error_level, $error_string, $file, $line)
{
	global $modSettings;

	// Ignore errors if we're ignoring them or they are strict notices from PHP 5
	if (error_reporting() == 0)
		return;

	$error_type = 'cron';

	log_error($error_level . ': ' . $error_string, $error_type, $file, $line);

	// If this is an E_ERROR or E_USER_ERROR.... die.  Violently so.
	if ($error_level % 255 == E_ERROR)
		die('No direct access...');
}

/**
 * The exit function
 */
function obExit_cron()
{
	if (FROM_CLI)
		die(0);
	else
	{
		header('content-type: image/gif');
		die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}
}

// We would like this to be defined, but we don't want to have to load more stuff than necessary.
// Thus we declare it here, and any legitimate background task must implement this.
/**
 * Class SMF_BackgroundTask
 */
abstract class SMF_BackgroundTask
{
	/**
	 * Constants for notfication types.
	*/
	const RECEIVE_NOTIFY_EMAIL = 0x02;
	const RECEIVE_NOTIFY_ALERT = 0x01;

	/**
	 * @var array Holds the details for the task
	 */
	protected $_details;

	/**
	 * The constructor.
	 *
	 * @param array $details The details for the task
	 */
	public function __construct($details)
	{
		$this->_details = $details;
	}

	/**
	 * The function to actually execute a task
	 *
	 * @return mixed
	 */
	abstract public function execute();
}

?>