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
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Attempt to start the session, unless it already has been.
 */
function loadSession()
{
	global $modSettings, $boardurl, $sc, $smcFunc, $cache_enable;

	// Attempt to change a few PHP settings.
	@ini_set('session.use_cookies', true);
	@ini_set('session.use_only_cookies', false);
	@ini_set('url_rewriter.tags', '');
	@ini_set('session.use_trans_sid', false);
	@ini_set('arg_separator.output', '&amp;');

	if (!empty($modSettings['globalCookies']))
	{
		$parsed_url = parse_url($boardurl);

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
			session_set_save_handler('sessionOpen', 'sessionClose', 'sessionRead', 'sessionWrite', 'sessionDestroy', 'sessionGC');
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
 * Implementation of sessionOpen() replacing the standard open handler.
 * It simply returns true.
 *
 * @param string $save_path The path to save the session to
 * @param string $session_name The name of the session
 * @return boolean Always returns true
 */
function sessionOpen($save_path, $session_name)
{
	return true;
}

/**
 * Implementation of sessionClose() replacing the standard close handler.
 * It simply returns true.
 *
 * @return boolean Always returns true
 */
function sessionClose()
{
	return true;
}

/**
 * Implementation of sessionRead() replacing the standard read handler.
 *
 * @param string $session_id The session ID
 * @return string The session data
 */
function sessionRead($session_id)
{
	global $smcFunc;

	if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $session_id) == 0)
		return '';

	// Look for it in the database.
	$result = $smcFunc['db_query']('', '
		SELECT data
		FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}
		LIMIT 1',
		array(
			'session_id' => $session_id,
		)
	);
	list ($sess_data) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	return $sess_data != null ? $sess_data : '';
}

/**
 * Implementation of sessionWrite() replacing the standard write handler.
 *
 * @param string $session_id The session ID
 * @param string $data The data to write to the session
 * @return boolean Whether the info was successfully written
 */
function sessionWrite($session_id, $data)
{
	global $smcFunc, $db_connection, $db_server, $db_name, $db_user, $db_passwd;
	global $db_prefix, $db_persist, $db_port, $db_mb4;

	if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $session_id) == 0)
		return false;

	// php < 7.0 need this
	if (empty($db_connection))
	{
		$db_options = array();

		// Add in the port if needed
		if (!empty($db_port))
			$db_options['port'] = $db_port;

		if (!empty($db_mb4))
			$db_options['db_mb4'] = $db_mb4;

		$options = array_merge($db_options, array('persist' => $db_persist, 'dont_select_db' => SMF == 'SSI'));

		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, $options);
	}

	// First try to update an existing row...
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}sessions
		SET data = {string:data}, last_update = {int:last_update}
		WHERE session_id = {string:session_id}',
		array(
			'last_update' => time(),
			'data' => $data,
			'session_id' => $session_id,
		)
	);

	// If that didn't work, try inserting a new one.
	if ($smcFunc['db_affected_rows']() == 0)
		$smcFunc['db_insert']('ignore',
			'{db_prefix}sessions',
			array('session_id' => 'string', 'data' => 'string', 'last_update' => 'int'),
			array($session_id, $data, time()),
			array('session_id')
		);

	return ($smcFunc['db_affected_rows']() == 0 ? false : true);
}

/**
 * Implementation of sessionDestroy() replacing the standard destroy handler.
 *
 * @param string $session_id The session ID
 * @return boolean Whether the session was successfully destroyed
 */
function sessionDestroy($session_id)
{
	global $smcFunc;

	if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $session_id) == 0)
		return false;

	// Just delete the row...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}',
		array(
			'session_id' => $session_id,
		)
	);

	return true;
}

/**
 * Implementation of sessionGC() replacing the standard gc handler.
 * Callback for garbage collection.
 *
 * @param int $max_lifetime The maximum lifetime (in seconds) - prevents deleting of sessions older than this
 * @return boolean Whether the option was successful
 */
function sessionGC($max_lifetime)
{
	global $modSettings, $smcFunc;

	// Just set to the default or lower?  Ignore it for a higher value. (hopefully)
	if (!empty($modSettings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || $modSettings['databaseSession_lifetime'] > $max_lifetime))
		$max_lifetime = max($modSettings['databaseSession_lifetime'], 60);

	// Clean up after yerself ;).
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}sessions
		WHERE last_update < {int:last_update}',
		array(
			'last_update' => time() - $max_lifetime,
		)
	);

	return ($smcFunc['db_affected_rows']() == 0 ? false : true);
}

?>