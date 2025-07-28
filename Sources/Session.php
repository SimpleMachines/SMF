<?php

/**
 *  Implementation of PHP's session API.
 * 	What it does:
 * 	- it handles the session data in the database (more scalable.)
 * 	- it uses the databaseSession_lifetime setting for garbage collection.
 * 	- the custom session handler is set by loadSession().
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.5
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Attempt to start the session, unless it already has been.
 */
function loadSession()
{
	global $context, $modSettings, $boardurl, $sc, $smcFunc, $cache_enable;

	// Attempt to change a few PHP settings.
	@ini_set('session.use_cookies', true);
	@ini_set('session.use_only_cookies', false);
	@ini_set('url_rewriter.tags', '');
	@ini_set('session.use_trans_sid', false);
	@ini_set('arg_separator.output', '&amp;');

	// Allows mods to change/add PHP settings
	call_integration_hook('integrate_load_session');

	if (!empty($modSettings['globalCookies']))
	{
		$parsed_url = parse_iri($boardurl);

		if (preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^\.]+\.)?([^\.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			@ini_set('session.cookie_domain', '.' . $parts[1]);
	}
	// @todo Set the session cookie path?

	// If it's already been started... probably best to skip this.
	if ((ini_get('session.auto_start') == 1 && !empty($modSettings['databaseSession_enable'])) || session_id() == '')
	{
		// Attempt to end the already-started session.
		if (ini_get('session.auto_start') == 1)
			session_write_close();

		// This is here to stop people from using bad junky PHPSESSIDs.
		if (isset($_REQUEST[session_name()]) && preg_match('~^[A-Za-z0-9,-]{16,64}$~', $_REQUEST[session_name()]) == 0 && !isset($_COOKIE[session_name()]))
		{
			$session_id = md5(md5('smf_sess_' . time()) . $smcFunc['random_int']());
			$_REQUEST[session_name()] = $session_id;
			$_GET[session_name()] = $session_id;
			$_POST[session_name()] = $session_id;
		}

		// Use database sessions? (they don't work in 4.1.x!)
		if (!empty($modSettings['databaseSession_enable']))
		{
			@ini_set('session.serialize_handler', 'php_serialize');
			if (ini_get('session.serialize_handler') != 'php_serialize')
				@ini_set('session.serialize_handler', 'php');

			$context['session_handler'] = new SmfSessionHandler();
			session_set_save_handler($context['session_handler'], true);

			@ini_set('session.gc_probability', '1');
		}
		elseif (ini_get('session.gc_maxlifetime') <= 1440 && !empty($modSettings['databaseSession_lifetime']))
			@ini_set('session.gc_maxlifetime', max($modSettings['databaseSession_lifetime'], 60));

		// Use cache setting sessions?
		if (empty($modSettings['databaseSession_enable']) && !empty($cache_enable) && php_sapi_name() != 'cli')
			call_integration_hook('integrate_session_handlers');

		session_start();

		// Change it so the cache settings are a little looser than default.
		if (!empty($modSettings['databaseSession_loose']))
			header('cache-control: private');
	}

	// Set the randomly generated code.
	if (!isset($_SESSION['session_var']))
	{
		$_SESSION['session_value'] = md5(session_id() . $smcFunc['random_int']());
		$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', sha1($smcFunc['random_int']() . session_id() . $smcFunc['random_int']())), 0, $smcFunc['random_int'](7, 12));
	}
	$sc = $_SESSION['session_value'];
}

/**
 * Class SmfSessionHandler
 *
 * An implementation of the SessionHandler
 *
 * Note: To support PHP 8.x, we use the attribute ReturnTypeWillChange. When
 * 8.1 is the miniumn, this can be removed.
 *
 * Note: To support PHP 7.x, we do not use type hints as SessionHandlerInterface
 * does not have them.
 */
class SmfSessionHandler extends SessionHandler implements SessionHandlerInterface, SessionIdInterface
{
	/**
	 * Implementation of SessionHandler::open() replacing the standard open handler.
	 * It simply returns true.
	 *
	 * @param string $path The path to save the session to
	 * @param string $name The name of the session
	 * @return boolean Always returns true
	 */
	function open(/*PHP 8.0 string*/$path, /*PHP 8.0 string*/$name): bool
	{
		return true;
	}

	/**
	 * Implementation of SessionHandler::close() replacing the standard close handler.
	 * It simply returns true.
	 *
	 * @return boolean Always returns true
	 */
	public function close(): bool
	{
		return true;
	}

	/**
	 * Implementation of SessionHandler::read() replacing the standard read handler.
	 *
	 * @param string $id The session ID
	 * @return string The session data
	 */
	#[\ReturnTypeWillChange]
	public function read(/*PHP 8.0 string*/$id)/*PHP 8.0: string|false*/
	{
		global $smcFunc;

		if (!$this->isValidSessionID($id))
			return '';

		// Look for it in the database.
		$result = $smcFunc['db_query']('', '
			SELECT data
			FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}
			LIMIT 1',
			array(
				'session_id' => $id,
			)
		);
		list ($sess_data) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		return $sess_data != null ? $sess_data : '';
	}

	/**
	 * Implementation of SessionHandler::write() replacing the standard write handler.
	 *
	 * @param string $id The session ID
	 * @param string $data The data to write to the session
	 * @return boolean Whether the info was successfully written
	 */
	#[\ReturnTypeWillChange]
	public function write(/*PHP 8.0 string*/$id,/*PHP 8.0 string */ $data): bool
	{
		global $smcFunc;

		if (!$this->isValidSessionID($id))
			return false;

		// If an insert fails due to a dupe, replace the existing session...
		$session_update = $smcFunc['db_insert']('replace',
			'{db_prefix}sessions',
			array('session_id' => 'string', 'data' => 'string', 'last_update' => 'int'),
			array($id, $data, time()),
			array('session_id')
		);

		return ($smcFunc['db_affected_rows']() == 0 ? false : true);
	}

	/**
	 * Implementation of SessionHandler::destroy() replacing the standard destroy handler.
	 *
	 * @param string $session_id The session ID
	 * @return boolean Whether the session was successfully destroyed
	 */
	public function destroy(/*PHP 8.0 string*/$id): bool
	{
		global $smcFunc;

		if (!$this->isValidSessionID($id))
			return false;

		// Just delete the row...
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}',
			array(
				'session_id' => $id,
			)
		);

		return true;
	}

	/**
	 * Implementation of SessionHandler::GC() replacing the standard gc handler.
	 * Callback for garbage collection.
	 *
	 * @param int $max_lifetime The maximum lifetime (in seconds) - prevents deleting of sessions older than this
	 * @return boolean Whether the option was successful
	 */
	#[\ReturnTypeWillChange]
	public function gc(/*PHP 8.0 int*/$max_lifetime)/*PHP 8.1 : int|false*/
	{
		global $modSettings, $smcFunc;

		// Just set to the default or lower?  Ignore it for a higher value. (hopefully)
		if (!empty($modSettings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || $modSettings['databaseSession_lifetime'] > $max_lifetime))
			$max_lifetime = max($modSettings['databaseSession_lifetime'], 60);

		// Clean up after yerself ;).
		$session_update = $smcFunc['db_query']('', '
			DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			array(
				'last_update' => time() - $max_lifetime,
			)
		);

		return $smcFunc['db_affected_rows']();
	}

	/**
	 * Validates a given string conforms to our testing for a valid session id.
	 *
	 * @param string $id The session ID
	 * @return boolean Whether the string is valid format or not
	 */
	private function isValidSessionID(string $id): bool
	{
		return preg_match('~^[A-Za-z0-9,-]{16,64}$~', $id) === 1;
	}
}

?>