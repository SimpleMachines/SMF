<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\Config;
use SMF\Cookie;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Sapi;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Logs the user out.
 */
class Logout extends Login2
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Logs the current user out of their account.
	 *
	 * It requires that the session hash is sent as well, to prevent automatic
	 * logouts by images or javascript.
	 *
	 * It redirects back to $_SESSION['logout_url'], if it exists.
	 *
	 * It is accessed via ?action=logout;session_var=...
	 *
	 * @param bool $internal If true, it doesn't check the session
	 * @param bool $redirect Whether or not to redirect the user after they log out
	 */
	public function execute(bool $internal = false, bool $redirect = true): void
	{
		// They decided to cancel a logout?
		if (!$internal && isset($_POST['cancel'], $_GET[Utils::$context['session_var']])) {
			Utils::redirectexit(!empty($_SESSION['logout_return']) ? $_SESSION['logout_return'] : '');
		}
		// Prompt to logout?
		elseif (!$internal && !isset($_GET[Utils::$context['session_var']])) {
			Lang::load('Login');
			Theme::loadTemplate('Login');
			Utils::$context['sub_template'] = 'logout';

			// This came from a valid hashed return url.  Or something that knows our secrets...
			if (!empty($_REQUEST['return_hash']) && !empty($_REQUEST['return_to']) && hash_hmac('sha1', Utils::htmlspecialcharsDecode($_REQUEST['return_to']), Config::getAuthSecret()) == $_REQUEST['return_hash']) {
				$_SESSION['logout_url'] = Utils::htmlspecialcharsDecode($_REQUEST['return_to']);
				$_SESSION['logout_return'] = $_SESSION['logout_url'];
			}
			// Setup the return address.
			elseif (isset($_SESSION['old_url'])) {
				$_SESSION['logout_return'] = $_SESSION['old_url'];
			}

			// Don't go any further.
			return;
		}
		// Make sure they aren't being auto-logged out.
		elseif (!$internal && isset($_GET[Utils::$context['session_var']])) {
			User::$me->checkSession('get');
		}

		if (isset($_SESSION['pack_ftp'])) {
			$_SESSION['pack_ftp'] = null;
		}

		// It won't be first login anymore.
		unset($_SESSION['first_login']);

		// Just ensure they aren't a guest!
		if (!User::$me->is_guest) {
			// Pass the logout information to integrations.
			IntegrationHook::call('integrate_logout', [User::$me->username]);

			// If you log out, you aren't online anymore :P.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_online
				WHERE id_member = {int:current_member}',
				[
					'current_member' => User::$me->id,
				],
			);
		}

		$_SESSION['log_time'] = 0;

		// Empty the cookie! (set it in the past, and for id_member = 0)
		Cookie::setLoginCookie(-3600, 0);

		// And some other housekeeping while we're at it.
		$salt = bin2hex(random_bytes(16));

		if (!empty(User::$me->id)) {
			User::updateMemberData(User::$me->id, ['password_salt' => $salt]);
		}

		if (!empty(Config::$modSettings['tfa_mode']) && !empty(User::$me->id) && !empty($_COOKIE[Config::$cookiename . '_tfa'])) {
			list(, , $exp) = Utils::jsonDecode($_COOKIE[Config::$cookiename . '_tfa'], true);

			Cookie::setTFACookie((int) $exp - time(), User::$me->id, Cookie::encrypt(User::$me->tfa_backup, $salt));
		}

		session_destroy();

		// Off to the merry board index we go!
		if ($redirect) {
			if (empty($_SESSION['logout_url'])) {
				Utils::redirectexit('', Sapi::needsLoginFix());
			} elseif (!empty($_SESSION['logout_url']) && (!str_contains($_SESSION['logout_url'], 'http://') && !str_contains($_SESSION['logout_url'], 'https://'))) {
				unset($_SESSION['logout_url']);
				Utils::redirectexit();
			} else {
				$temp = $_SESSION['logout_url'];
				unset($_SESSION['logout_url']);

				Utils::redirectexit($temp, Sapi::needsLoginFix());
			}
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 *
	 * @param bool $internal If true, it doesn't check the session
	 * @param bool $redirect Whether or not to redirect the user after they log out
	 */
	public static function call(bool $internal = false, bool $redirect = true): void
	{
		self::load()->execute($internal, $redirect);
	}
}

?>