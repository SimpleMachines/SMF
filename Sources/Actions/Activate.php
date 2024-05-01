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

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\ProvidesSubActionInterface;
use SMF\ProvidesSubActionTrait;
use SMF\Security;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Activates a user's account.
 */
class Activate implements ActionInterface, ProvidesSubActionInterface
{
	use ActionTrait;
	use ProvidesSubActionTrait;

	/*********************
	 * Internal properties
	 *********************/

	private bool $email_change;
	private array $row;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Activates a user's account.
	 *
	 * Checks for mail changes, resends password if needed.
	 */
	public function execute(): void
	{
		if (empty($_REQUEST['u']) && empty($_POST['user'])) {
			if (empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] == '3') {
				ErrorHandler::fatalLang('no_access', false);
			}

			Utils::$context['member_id'] = 0;
			Utils::$context['sub_template'] = 'resend';
			Utils::$context['page_title'] = Lang::$txt['invalid_activation_resend'];
			Utils::$context['can_activate'] = empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] == '1';
			Utils::$context['default_username'] = $_GET['user'] ?? '';

			return;
		}

		// Get the code from the database...
		$request = Db::$db->query(
			'',
			'SELECT id_member, validation_code, member_name, real_name, email_address, is_activated, passwd, lngfile
			FROM {db_prefix}members' . (empty($_REQUEST['u']) ? '
			WHERE member_name = {string:email_address} OR email_address = {string:email_address}' : '
			WHERE id_member = {int:id_member}') . '
			LIMIT 1',
			[
				'id_member' => isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0,
				'email_address' => $_POST['user'] ?? '',
			],
		);

		// Does this user exist at all?
		if (Db::$db->num_rows($request) == 0) {
			Utils::$context['sub_template'] = 'retry_activate';
			Utils::$context['page_title'] = Lang::$txt['invalid_userid'];
			Utils::$context['member_id'] = 0;

			return;
		}

		$this->row = Db::$db->fetch_assoc($request);
		$this->row['id_member'] = (int) $this->row['id_member'];
		$this->row['is_activated'] = (int) $this->row['is_activated'];
		Db::$db->free_result($request);

		// Change their email address? (they probably tried a fake one first :P.)
		if (
			!empty($_POST['new_email'])
			&& !empty($_REQUEST['passwd'])
			&& Security::hashVerifyPassword($this->row['member_name'], $_REQUEST['passwd'], $this->row['passwd'])
			&& (
				$this->row['is_activated'] == 0
				|| $this->row['is_activated'] == 2
			)
		) {
			if (empty(Config::$modSettings['registration_method']) || Config::$modSettings['registration_method'] == 3) {
				ErrorHandler::fatalLang('no_access', false);
			}

			if (!filter_var($_POST['new_email'], FILTER_VALIDATE_EMAIL)) {
				ErrorHandler::fatal(Lang::getTxt('valid_email_needed', ['email' => Utils::htmlspecialchars($_POST['new_email'])]), false);
			}

			// Make sure their email isn't banned.
			User::isBannedEmail($_POST['new_email'], 'cannot_register', Lang::$txt['ban_register_prohibited']);

			// Ummm... don't even dare try to take someone else's email!!
			$request = Db::$db->query(
				'',
				'SELECT id_member
				FROM {db_prefix}members
				WHERE email_address = {string:email_address}
				LIMIT 1',
				[
					'email_address' => $_POST['new_email'],
				],
			);

			if (Db::$db->num_rows($request) != 0) {
				ErrorHandler::fatalLang('email_in_use', false, [Utils::htmlspecialchars($_POST['new_email'])]);
			}
			Db::$db->free_result($request);

			User::updateMemberData($this->row['id_member'], ['email_address' => $_POST['new_email']]);
			$this->row['email_address'] = $_POST['new_email'];

			$this->email_change = true;
		}

		$this->callSubAction($_REQUEST['sa'] ?? null);
	}

	public function activate(): void
	{
		// Quit if this code is not right.
		if (empty($_REQUEST['code']) || $this->row['validation_code'] != $_REQUEST['code']) {
			if (!empty($this->row['is_activated'])) {
				ErrorHandler::fatalLang('already_activated', false);
			} elseif ($this->row['validation_code'] == '') {
				Lang::load('Profile');
				ErrorHandler::fatal(Lang::getTxt('registration_not_approved', ['url' => Config::$scripturl . '?action=activate;user=' . $this->row['member_name']]), false);
			}

			Utils::$context['sub_template'] = 'retry_activate';
			Utils::$context['page_title'] = Lang::$txt['invalid_activation_code'];
			Utils::$context['member_id'] = $this->row['id_member'];

			return;
		}

		// Let the integration know that they've been activated!
		IntegrationHook::call('integrate_activate', [$this->row['member_name']]);

		// Validation complete - update the database!
		User::updateMemberData($this->row['id_member'], ['is_activated' => 1, 'validation_code' => '']);

		// Also do a proper member stat re-evaluation.
		Logging::updateStats('member', false);

		// Notify the admin about new activations, but not re-activations.
		if (empty($this->row['is_activated'])) {
			Mail::adminNotify('activation', $this->row['id_member'], $this->row['member_name']);
		}

		Utils::$context += [
			'page_title' => Lang::$txt['registration_successful'],
			'sub_template' => 'login',
			'default_username' => $this->row['member_name'],
			'default_password' => '',
			'never_expire' => false,
			'description' => Lang::$txt['activate_success'],
		];
	}

	public function resend(): void
	{
		// Resend the password, but only if the account wasn't activated yet.
		if (
			in_array($this->row['is_activated'], [0, 2])
			&& (!isset($_REQUEST['code']) || $_REQUEST['code'] == '')
		) {
			$replacements = [
				'REALNAME' => $this->row['real_name'],
				'USERNAME' => $this->row['member_name'],
				'ACTIVATIONLINK' => Config::$scripturl . '?action=activate;u=' . $this->row['id_member'] . ';code=' . $this->row['validation_code'],
				'ACTIVATIONLINKWITHOUTCODE' => Config::$scripturl . '?action=activate;u=' . $this->row['id_member'],
				'ACTIVATIONCODE' => $this->row['validation_code'],
				'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
			];

			$emaildata = Mail::loadEmailTemplate('resend_activate_message', $replacements, empty($this->row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $this->row['lngfile']);

			Mail::send($this->row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'resendact', $emaildata['is_html'], 0);

			Utils::$context['page_title'] = Lang::$txt['invalid_activation_resend'];

			// This will ensure we don't actually get an error message if it works!
			Utils::$context['error_title'] = Lang::$txt['invalid_activation_resend'];

			ErrorHandler::fatalLang($this->email_change ? 'change_email_success' : 'resend_email_success', false, []);
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		$this->addSubAction('activate', [$this, 'activate']);
		$this->addSubAction('resend', [$this, 'resend']);

		// Logged in users should not bother to activate their accounts
		if (!empty(User::$me->id)) {
			Utils::redirectexit();
		}

		Lang::load('Login');
		Theme::loadTemplate('Login');
	}
}

?>