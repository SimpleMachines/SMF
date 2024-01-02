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

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 *  Implementation of PHP's session API.
 *
 * 	What it does:
 * 	- it handles the session data in the database (more scalable.)
 * 	- it uses the databaseSession_lifetime setting for garbage collection.
 * 	- the custom session handler is set by Session::load().
 */
class Session implements \SessionHandlerInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'load' => 'loadSession',
		],
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Initializes the session.
	 *
	 * @param string $path The path to save the session to.
	 * @param string $name The name of the session.
	 * @return bool Always returns true.
	 */
	public function open(string $path, string $name): bool
	{
		return true;
	}

	/**
	 * Closes the session.
	 *
	 * @return bool Always returns true.
	 */
	public function close(): bool
	{
		return true;
	}

	/**
	 * Read session data.
	 *
	 * Note: The PHP manual says to return false if no record was found, but
	 * doing so causes errors on some versions of PHP when the user logs out.
	 * Returning an empty string works for all versions.
	 *
	 * @param string $session_id The session ID.
	 * @return string The session data.
	 */
	public function read(string $session_id): string
	{
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $session_id) == 0) {
			return '';
		}

		// Look for it in the database.
		$result = Db::$db->query(
			'',
			'SELECT data
			FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}
			LIMIT 1',
			[
				'session_id' => $session_id,
			],
		);
		list($sess_data) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		return $sess_data != null ? $sess_data : '';
	}

	/**
	 * Writes session data.
	 *
	 * @param string $session_id The session ID.
	 * @param string $data The data to write to the session.
	 * @return bool Whether the info was successfully written.
	 */
	public function write(string $session_id, string $data): bool
	{
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $session_id) == 0) {
			return false;
		}

		if (empty(Db::$db_connection)) {
			Db::load();
		}

		// If an insert fails due to a duplicate, replace the existing session...
		Db::$db->insert(
			'replace',
			'{db_prefix}sessions',
			[
				'session_id' => 'string',
				'data' => 'string',
				'last_update' => 'int',
			],
			[
				$session_id,
				$data,
				time(),
			],
			['session_id'],
		);

		return (Db::$db->affected_rows() == 0 ? false : true);
	}

	/**
	 * Destroys a session.
	 *
	 * @param string $session_id The session ID.
	 * @return bool Whether the session was successfully destroyed.
	 */
	public function destroy(string $session_id): bool
	{
		if (preg_match('~^[A-Za-z0-9,-]{16,64}$~', $session_id) == 0) {
			return false;
		}

		// Just delete the row...
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}sessions
			WHERE session_id = {string:session_id}',
			[
				'session_id' => $session_id,
			],
		);

		return true;
	}

	/**
	 * Cleans up old sessions.
	 *
	 * @param int $max_lifetime Sessions that have not updated for the last
	 *    $max_lifetime seconds will be removed.
	 * @return int|false The number of deleted sessions, or false on failure.
	 */
	public function gc(int $max_lifetime): int|false
	{
		// Just set to the default or lower?  Ignore it for a higher value. (hopefully)
		if (!empty(Config::$modSettings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || Config::$modSettings['databaseSession_lifetime'] > $max_lifetime)) {
			$max_lifetime = max(Config::$modSettings['databaseSession_lifetime'], 60);
		}

		// Clean up after yerself ;).
		$session_update = Db::$db->query(
			'',
			'DELETE FROM {db_prefix}sessions
			WHERE last_update < {int:last_update}',
			[
				'last_update' => time() - $max_lifetime,
			],
		);

		$num_deleted = Db::$db->affected_rows();

		return $num_deleted == 0 ? false : $num_deleted;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Attempt to start the session, unless it already has been.
	 */
	public static function load(): void
	{
		// Attempt to change a few PHP settings.
		@ini_set('session.use_cookies', true);
		@ini_set('session.use_only_cookies', false);
		@ini_set('url_rewriter.tags', '');
		@ini_set('session.use_trans_sid', false);
		@ini_set('arg_separator.output', '&amp;');

		// Allows mods to change/add PHP settings
		IntegrationHook::call('integrate_load_session');

		if (!empty(Config::$modSettings['globalCookies'])) {
			$url = new Url(Config::$boardurl);

			if (preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $url->host) == 0 && preg_match('~(?:[^.]+\.)?([^.]{2,}\..+)\z~i', $url->host, $parts) == 1) {
				@ini_set('session.cookie_domain', '.' . $parts[1]);
			}
		}
		// @todo Set the session cookie path?

		// If it's already been started... probably best to skip this.
		if ((ini_get('session.auto_start') == 1 && !empty(Config::$modSettings['databaseSession_enable'])) || session_id() == '') {
			// Attempt to end the already-started session.
			if (ini_get('session.auto_start') == 1) {
				session_write_close();
			}

			// This is here to stop people from using bad junky PHPSESSIDs.
			if (isset($_REQUEST[session_name()]) && preg_match('~^[A-Za-z0-9,-]{16,64}$~', $_REQUEST[session_name()]) == 0 && !isset($_COOKIE[session_name()])) {
				$session_id = bin2hex(random_bytes(16));
				$_REQUEST[session_name()] = $session_id;
				$_GET[session_name()] = $session_id;
				$_POST[session_name()] = $session_id;
			}

			// Use database sessions? (they don't work in 4.1.x!)
			if (!empty(Config::$modSettings['databaseSession_enable'])) {
				@ini_set('session.serialize_handler', 'php_serialize');

				if (ini_get('session.serialize_handler') != 'php_serialize') {
					@ini_set('session.serialize_handler', 'php');
				}

				session_set_save_handler(new self(), true);

				@ini_set('session.gc_probability', '1');
			} elseif (ini_get('session.gc_maxlifetime') <= 1440 && !empty(Config::$modSettings['databaseSession_lifetime'])) {
				@ini_set('session.gc_maxlifetime', max(Config::$modSettings['databaseSession_lifetime'], 60));
			}

			// Use cache setting sessions?
			if (empty(Config::$modSettings['databaseSession_enable']) && !empty(CacheApi::$enable) && php_sapi_name() != 'cli') {
				IntegrationHook::call('integrate_session_handlers');
			}

			session_start();

			// Change it so the cache settings are a little looser than default.
			if (!empty(Config::$modSettings['databaseSession_loose'])) {
				header('cache-control: private');
			}
		}

		// Set the randomly generated code.
		if (!isset($_SESSION['session_var'])) {
			// Ensure session_var always starts with a letter.
			$_SESSION['session_var'] = dechex(random_int(0xA000000000, 0xFFFFFFFFFF));
			$_SESSION['session_value'] = bin2hex(random_bytes(16));
		}

		User::$sc = $_SESSION['session_value'];
	}

	/**
	 * Backward compatibility wrapper for the open method.
	 */
	public static function sessionOpen(string $path, string $name): bool
	{
		return (new self())->open($path, $name);
	}

	/**
	 * Backward compatibility wrapper for the close method.
	 */
	public static function sessionClose(): bool
	{
		return (new self())->close();
	}

	/**
	 * Backward compatibility wrapper for the read method.
	 */
	public static function sessionRead(string $session_id): string
	{
		return (string) (new self())->read($session_id);
	}

	/**
	 * Backward compatibility wrapper for the write method.
	 */
	public static function sessionWrite(string $session_id, string $data): bool
	{
		return (new self())->write($session_id, $data);
	}

	/**
	 * Backward compatibility wrapper for the destroy method.
	 */
	public static function sessionDestroy(string $session_id): bool
	{
		return (new self())->destroy($session_id);
	}

	/**
	 * Backward compatibility wrapper for the gc method.
	 */
	public static function sessionGC(int $max_lifetime): int|false
	{
		return (new self())->gc($max_lifetime);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Session::exportStatic')) {
	Session::exportStatic();
}

?>