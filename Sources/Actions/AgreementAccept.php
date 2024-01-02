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
use SMF\Db\DatabaseApi as Db;
use SMF\Logging;
use SMF\User;
use SMF\Utils;

/**
 * Records when the user accepted the registration agreement and privacy policy.
 */
class AgreementAccept extends Agreement
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'AcceptAgreement',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var object
	 *
	 * An instance of the class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * I solemly swear to no longer chase squirrels.
	 *
	 * Called when they actually accept the agreement and privacy policy.
	 * Saves the date when they accepted to the members database table.
	 * Redirects back to wherever they came from.
	 */
	public function execute(): void
	{
		$can_accept_agreement = !empty(Config::$modSettings['requireAgreement']) && parent::canRequireAgreement();
		$can_accept_privacy_policy = !empty(Config::$modSettings['requirePolicyAgreement']) && parent::canRequirePrivacyPolicy();

		if ($can_accept_agreement || $can_accept_privacy_policy) {
			User::$me->checkSession();

			if ($can_accept_agreement) {
				Db::$db->insert(
					'replace',
					'{db_prefix}themes',
					['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'],
					[User::$me->id, 1, 'agreement_accepted', time()],
					['id_member', 'id_theme', 'variable'],
				);

				Logging::logAction('agreement_accepted', ['applicator' => User::$me->id], 'user');
			}

			if ($can_accept_privacy_policy) {
				Db::$db->insert(
					'replace',
					'{db_prefix}themes',
					['id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'],
					[User::$me->id, 1, 'policy_accepted', time()],
					['id_member', 'id_theme', 'variable'],
				);

				Logging::logAction('policy_accepted', ['applicator' => User::$me->id], 'user');
			}
		}

		// Redirect back to chasing those squirrels, er, viewing those memes.
		Utils::redirectexit(!empty($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '');
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
if (is_callable(__NAMESPACE__ . '\\AgreementAccept::exportStatic')) {
	AgreementAccept::exportStatic();
}

?>