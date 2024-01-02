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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Cookie;
use SMF\ErrorHandler;
use SMF\Profile;
use SMF\Security;
use SMF\Theme;
use SMF\TOTP\Auth as Tfa;
use SMF\User;
use SMF\Utils;

/**
 * Provides interface to set up two-factor authentication in SMF.
 */
class TFASetup implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'tfasetup',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var object
	 *
	 * An instance of the SMF\TOTP\Auth class.
	 */
	protected object $totp;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		// Users have to do this for themselves, and can't do it again if they already did.
		if (!User::$me->is_owner || !empty(User::$me->tfa_secret)) {
			Utils::redirectexit('action=profile;area=account;u=' . Profile::$member->id);
		}

		// Load JS lib for QR
		Theme::loadJavaScriptFile('qrcode.js', ['force_current' => false, 'validate' => true]);

		// Check to ensure we're forcing SSL for authentication.
		if (!empty(Config::$modSettings['force_ssl']) && empty(Config::$maintenance) && !Config::httpsOn()) {
			ErrorHandler::fatalLang('login_ssl_required', false);
		}

		// In some cases (forced 2FA or backup code) they would be forced to be redirected here,
		// we do not want too much AJAX to confuse them.
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' && !isset($_REQUEST['backup']) && !isset($_REQUEST['forced'])) {
			Utils::$context['from_ajax'] = true;
			Utils::$context['template_layers'] = [];
		}

		// When the code is being sent, verify to make sure the user got it right
		if (!empty($_REQUEST['save']) && !empty($_SESSION['tfa_secret'])) {
			$this->validateAndSave();
		} else {
			$this->generate();
		}

		Utils::$context['tfa_qr_url'] = $this->totp->getQrCodeUrl(Utils::$context['forum_name'] . ':' . User::$me->name, Utils::$context['tfa_secret']);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}

	/**
	 * Validates the submitted TFA secret and (if valid) saves it.
	 * Shows an error message if validation failed.
	 */
	protected function validateAndSave(): void
	{
		$code = $_POST['tfa_code'];
		$this->totp = new Tfa($_SESSION['tfa_secret']);
		$this->totp->setRange(1);
		$valid_code = strlen($code) == $this->totp->getCodeLength() && $this->totp->validateCode($code);

		if (empty(Utils::$context['password_auth_failed']) && $valid_code) {
			$backup = bin2hex(random_bytes(8));
			$backup_encrypted = Security::hashPassword(User::$me->username, $backup);

			User::updateMemberData(Profile::$member->id, [
				'tfa_secret' => $_SESSION['tfa_secret'],
				'tfa_backup' => $backup_encrypted,
			]);

			Cookie::setTFACookie(3153600, Profile::$member->id, Cookie::encrypt($backup_encrypted, User::$me->password_salt));

			unset($_SESSION['tfa_secret']);

			Utils::$context['tfa_backup'] = $backup;
			Utils::$context['sub_template'] = 'tfasetup_backup';
		} else {
			Utils::$context['tfa_secret'] = $_SESSION['tfa_secret'];
			Utils::$context['tfa_error'] = !$valid_code;
			Utils::$context['tfa_pass_value'] = $_POST['oldpasswrd'];
			Utils::$context['tfa_value'] = $_POST['tfa_code'];
		}
	}

	/**
	 * Generates a new TFA secret.
	 */
	protected function generate(): void
	{
		$this->totp = new Tfa();
		$secret = $this->totp->generateCode();
		$_SESSION['tfa_secret'] = $secret;
		Utils::$context['tfa_secret'] = $secret;
		Utils::$context['tfa_backup'] = isset($_REQUEST['backup']);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TFASetup::exportStatic')) {
	TFASetup::exportStatic();
}

?>