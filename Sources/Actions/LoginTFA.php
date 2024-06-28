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

namespace SMF\Sources\Actions;

use SMF\Sources\Config;
use SMF\Sources\Cookie;
use SMF\Sources\ErrorHandler;
use SMF\Sources\Lang;
use SMF\Sources\Sapi;
use SMF\Sources\Security;
use SMF\Sources\Theme;
use SMF\Sources\TOTP\Auth as Tfa;
use SMF\Sources\User;
use SMF\Sources\Utils;

/**
 * Handles Two-Factor Authentication credentials.
 */
class LoginTFA extends Login2
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Allows the user to enter their Two-Factor Authentication code.
	 */
	public function execute(): void
	{
		if (!User::$me->is_guest || empty(Utils::$context['tfa_member']) || empty(Config::$modSettings['tfa_mode'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		Lang::load('Profile');

		$member = Utils::$context['tfa_member'];

		// Prevent replay attacks by limiting at least 2 minutes before they can log in again via 2FA
		if (time() - $member['last_login'] < 120) {
			ErrorHandler::fatalLang('tfa_wait', false);
		}

		$totp = new Tfa($member['tfa_secret']);
		$totp->setRange(1);

		parent::checkAjax();

		if (!empty($_POST['tfa_code']) && empty($_POST['tfa_backup'])) {
			// Check to ensure we're forcing SSL for authentication
			if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Sapi::httpsOn()) {
				ErrorHandler::fatalLang('login_ssl_required', false);
			}

			$code = $_POST['tfa_code'];

			if (strlen($code) == $totp->getCodeLength() && $totp->validateCode($code)) {
				User::updateMemberData($member['id_member'], ['last_login' => time()]);

				Cookie::setTFACookie(3153600, $member['id_member'], Cookie::encrypt($member['tfa_backup'], $member['password_salt']));

				Utils::redirectexit();
			} else {
				parent::validatePasswordFlood($member['id_member'], $member['member_name'], $member['passwd_flood'], false, true);

				Utils::$context['tfa_error'] = true;
				Utils::$context['tfa_value'] = $_POST['tfa_code'];
			}
		} elseif (!empty($_POST['tfa_backup'])) {
			// Check to ensure we're forcing SSL for authentication
			if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Sapi::httpsOn()) {
				ErrorHandler::fatalLang('login_ssl_required', false);
			}

			$backup = $_POST['tfa_backup'];

			if (Security::hashVerifyPassword($member['member_name'], $backup, $member['tfa_backup'])) {
				// Get rid of their current TFA settings
				User::updateMemberData($member['id_member'], [
					'tfa_secret' => '',
					'tfa_backup' => '',
					'last_login' => time(),
				]);

				Cookie::setTFACookie(3153600, $member['id_member'], Cookie::encrypt($member['tfa_backup'], $member['password_salt']));

				Utils::redirectexit('action=profile;area=tfasetup;backup');
			} else {
				parent::validatePasswordFlood($member['id_member'], $member['member_name'], $member['passwd_flood'], false, true);

				Utils::$context['tfa_backup_error'] = true;
				Utils::$context['tfa_value'] = $_POST['tfa_code'];
				Utils::$context['tfa_backup_value'] = $_POST['tfa_backup'];
			}
		}

		Theme::loadTemplate('Login');
		Utils::$context['sub_template'] = 'login_tfa';
		Utils::$context['page_title'] = Lang::$txt['login'];
		Utils::$context['tfa_url'] = Config::$scripturl . '?action=logintfa';
	}
}

?>