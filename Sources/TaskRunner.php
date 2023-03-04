<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Db\DatabaseApi as Db;

/**
 * Runs background tasks (a.k.a. cron jobs).
 *
 * This class is not used during SMF's conventional running. Instead, it is
 * used by cron.php to run background tasks. It completely ignores anything
 * supplied via command line arguments, or via $_GET, $_POST, $_COOKIE, etc.,
 * because those things should never affect the running of this script.
 */
class TaskRunner
{
	/***********
	 * Constants
	 ***********/

	/**
	 * This setting is worth bearing in mind. If you are running this from
	 * proper cron, make sure you don't run this file any more frequently than
	 * indicated here. It might turn ugly if you do. But on proper cron you can
	 * always increase this value provided you don't go beyond max_limit.
	 */
	const MAX_CRON_TIME = 10;

	/**
	 * If a task fails for whatever reason it will still be marked as claimed.
	 * This is the threshold by which if a task has not completed in this time,
	 * the task should become available again.
	 */
	const MAX_CLAIM_THRESHOLD = 300;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor
	 */
	public function __construct()
	{
		define('FROM_CLI', empty($_SERVER['REQUEST_METHOD']));

		// For backward compatibility.
		if (!defined('MAX_CRON_TIME'))
			define('MAX_CRON_TIME', self::MAX_CRON_TIME);

		if (!defined('MAX_CLAIM_THRESHOLD'))
			define('MAX_CLAIM_THRESHOLD', self::MAX_CLAIM_THRESHOLD);

		// Don't do john didley if the forum's been shut down completely.
		if (!empty(Config::$maintenance) &&  2 === Config::$maintenance)
			display_maintenance_message();

		// Have we already turned this off? If so, exist gracefully.
		// @todo Remove this? It's a bad idea to ever disable background tasks.
		if (file_exists(Config::$cachedir . '/cron.lock'))
			$this->obExit();

		// Before we go any further, if this is not a CLI request, we need to do some checking.
		if (!FROM_CLI)
		{
			// When using sub-domains with SSI and ssi_themes set, browsers will
			// receive a "Access-Control-Allow-Origin" error. '*' is not ideal,
			// but the best method to preventing this from occurring.
			header('Access-Control-Allow-Origin: *');

			// We will clean up $_GET shortly. But we want to this ASAP.
			$ts = isset($_GET['ts']) ? (int) $_GET['ts'] : 0;

			if ($ts <= 0 || $ts % 15 != 0 || time() - $ts < 0 || time() - $ts > 20)
				$this->obExit();
		}
		else
		{
			$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
		}

		Config::$db_show_debug = null;

		Db::load();

		Config::reloadModSettings();

		// Just in case there's a problem...
		set_error_handler(__CLASS__ . '::handleError');

		User::$sc = '';

		$_SERVER['QUERY_STRING'] = '';
		$_SERVER['REQUEST_URL'] = FROM_CLI ? 'CLI cron.php' : Config::$boardurl . '/cron.php';

		// Now 'clean the request' (or more accurately, ignore everything we're not going to use)
		$this->cleanRequest();

		// Load the basic Lang::$txt strings.
		Lang::load('index+Modifications');
	}

	/**
	 * This is the one that gets stuff done.
	 *
	 * Internally, this calls $this->fetchTask() to get the details of a
	 * background task, then passes those details to $this->performTask(),
	 * then calls $this->obExit() to end execution.
	 */
	public function execute(): void
	{
		while ($task_details = $this->fetchTask())
		{
			$result = $this->performTask($task_details);

			if ($result)
			{
				Db::$db->query('', '
					DELETE FROM {db_prefix}background_tasks
					WHERE id_task = {int:task}',
					array(
						'task' => $task_details['id_task'],
					)
				);
			}
		}

		// If we have time, check the scheduled tasks.
		if (time() - TIME_START < ceil(self::MAX_CRON_TIME / 2))
		{
			require_once(Config::$sourcedir . '/ScheduledTasks.php');

			if (empty(Config::$modSettings['next_task_time']) || Config::$modSettings['next_task_time'] < time())
			{
				AutoTask();
			}
			elseif (!empty(Config::$modSettings['mail_next_send']) && Config::$modSettings['mail_next_send'] < time())
			{
				Mail::reduceQueue();
			}
		}

		$this->obExit();
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The error handling function
	 *
	 * @param int $error_level One of the PHP error level constants (see )
	 * @param string $error_string The error message
	 * @param string $file The file where the error occurred
	 * @param int $line What line of the specified file the error occurred on
	 * @return void
	 */
	public static function handleError($error_level, $error_string, $file, $line): void
	{
		// Ignore errors that should not be logged.
		if (error_reporting() == 0)
			return;

		$error_type = 'cron';

		log_error($error_level . ': ' . $error_string, $error_type, $file, $line);

		// If this is an E_ERROR or E_USER_ERROR.... die.  Violently so.
		if ($error_level % 255 == E_ERROR)
			die('No direct access...');
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * The heart of this cron handler...
	 *
	 * @return bool|array False if there's nothing to do or an array of info
	 *    about the task.
	 */
	protected function fetchTask(): bool|array
	{
		// Check we haven't run over our time limit.
		if (microtime(true) - TIME_START > self::MAX_CRON_TIME)
			return false;

		// Try to find a task. Specifically, try to find one that hasn't been
		// claimed previously, or failing that, a task that was claimed but
		// failed for whatever reason and failed long enough ago. We should not
		// care what task it is, merely that it is one in the queue; the order
		// is irrelevant.
		$request = Db::$db->query('', '
			SELECT id_task, task_file, task_class, task_data, claimed_time
			FROM {db_prefix}background_tasks
			WHERE claimed_time < {int:claim_limit}
			LIMIT 1',
			array(
				'claim_limit' => time() - self::MAX_CLAIM_THRESHOLD,
			)
		);
		if ($row = Db::$db->fetch_assoc($request))
		{
			// We found one. Let's try and claim it immediately.
			Db::$db->free_result($request);
			Db::$db->query('', '
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
			if (Db::$db->affected_rows() != 0)
			{
				// Update the time and go back.
				$row['claimed_time'] = time();
				return $row;
			}
			else
			{
				// Uh oh, we just missed it. Try to claim another one, and let
				// it fall through if there aren't any.
				return $this->fetchTask();
			}
		}
		else
		{
			// No dice. Clean up and go home.
			Db::$db->free_result($request);
			return false;
		}
	}

	/**
	 * This actually handles the task.
	 *
	 * @param array $task_details An array of info about the task.
	 * @return bool Whether the task should be cleared from the queue.
	 */
	protected function performTask($task_details): bool
	{
		// This indicates the file to load.
		// Only needed for tasks that don't use the SMF\Tasks\ namespace.
		if (!empty($task_details['task_file']))
		{
			$include = strtr(trim($task_details['task_file']), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir));

			if (file_exists($include))
				require_once($include);
		}

		if (empty($task_details['task_class']))
		{
			// This would be nice to translate but the language files aren't
			// loaded for any specific language.
			log_error('Invalid background task specified (no class, ' . (empty($task_details['task_file']) ? ' no file' : ' to load ' . $task_details['task_file']) . ')');

			// So we clear it from the queue.
			return true;
		}
		// All background tasks need to be classes.
		elseif (class_exists($task_details['task_class']) && is_subclass_of($task_details['task_class'], 'SMF\\Tasks\\BackgroundTask'))
		{
			$details = empty($task_details['task_data']) ? array() : Utils::jsonDecode($task_details['task_data'], true);

			$bgtask = new $task_details['task_class']($details);

			return $bgtask->execute();
		}
		// Backward compatibility for tasks listed in global namespace.
		elseif (class_exists('SMF\Tasks\\' . $task_details['task_class']) && is_subclass_of('SMF\Tasks\\' . $task_details['task_class'], 'SMF\\Tasks\\BackgroundTask'))
		{
			$details = empty($task_details['task_data']) ? array() : Utils::jsonDecode($task_details['task_data'], true);

			$task_class = 'SMF\Tasks\\' . $task_details['task_class'];

			$bgtask = new $task_class($details);

			return $bgtask->execute();
		}
		else
		{
			log_error('Invalid background task specified: (class: ' . $task_details['task_class'] . ', ' . (empty($task_details['task_file']) ? ' no file' : ' to load ' . $task_details['task_file']) . ')');

			// So we clear it from the queue.
			return true;
		}
	}

	/* Helper functions that resemble their big brother counterparts. */

	/**
	 * Cleans up the request variables.
	 *
	 * @return void
	 */
	protected function cleanRequest()
	{
		// These keys shouldn't be set...ever.
		if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
			die('Invalid request variable.');

		// Save some memory.. (since we don't use these anyway.)
		unset(
			$GLOBALS['HTTP_POST_VARS'],
			$GLOBALS['HTTP_POST_VARS'],
			$GLOBALS['HTTP_POST_FILES'],
			$GLOBALS['HTTP_POST_FILES'],
			$GLOBALS['_GET'],
			$GLOBALS['_POST'],
			$GLOBALS['_REQUEST'],
			$GLOBALS['_COOKIE'],
			$GLOBALS['_FILES']
		);
	}

	/**
	 * The exit function.
	 */
	protected function obExit()
	{
		if (FROM_CLI)
		{
			die(0);
		}
		else
		{
			header('content-type: image/gif');
			die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
		}
	}
}

?>