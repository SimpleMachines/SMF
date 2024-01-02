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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Lang;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows the login form.
 */
class Login extends Login2
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Login',
		],
	];

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
	 * Asks the user for their login information.
	 *
	 * Shows a page for the user to type in their username and password.
	 * It caches the referring URL in $_SESSION['login_url'].
	 * It is accessed from ?action=login.
	 *
	 * Uses Login template and language file with the login sub-template.
	 */
	public function execute(): void
	{
		// You are already logged in, go take a tour of the boards
		if (!empty(User::$me->id)) {
			// This came from a valid hashed return url.  Or something that knows our secrets...
			if (!empty($_REQUEST['return_hash']) && !empty($_REQUEST['return_to']) && hash_hmac('sha1', Utils::htmlspecialcharsDecode($_REQUEST['return_to']), Config::getAuthSecret()) == $_REQUEST['return_hash']) {
				Utils::redirectexit(Utils::htmlspecialcharsDecode($_REQUEST['return_to']));
			} else {
				Utils::redirectexit();
			}
		}

		// We need to load the Login template/language file.
		Lang::load('Login');
		Theme::loadTemplate('Login');

		Utils::$context['sub_template'] = 'login';

		parent::checkAjax();

		// Get the template ready.... not really much else to do.
		Utils::$context['page_title'] = Lang::$txt['login'];
		Utils::$context['default_username'] = &$_REQUEST['u'];
		Utils::$context['default_password'] = '';
		Utils::$context['never_expire'] = false;

		// Add the login chain to the link tree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=login',
			'name' => Lang::$txt['login'],
		];

		// Set the login URL - will be used when the login process is done (but careful not to send us to an attachment).
		if (isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0) {
			$_SESSION['login_url'] = $_SESSION['old_url'];
		}
		// This came from a valid hashed return url.  Or something that knows our secrets...
		elseif (!empty($_REQUEST['return_hash']) && !empty($_REQUEST['return_to']) && hash_hmac('sha1', Utils::htmlspecialcharsDecode($_REQUEST['return_to']), Config::getAuthSecret()) == $_REQUEST['return_hash']) {
			$_SESSION['login_url'] = Utils::htmlspecialcharsDecode($_REQUEST['return_to']);
		} elseif (isset($_SESSION['login_url']) && strpos($_SESSION['login_url'], 'dlattach') !== false) {
			unset($_SESSION['login_url']);
		}

		// Create a one time token.
		SecurityToken::create('login');
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Login::exportStatic')) {
	Login::exportStatic();
}

?>