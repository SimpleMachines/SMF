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
use SMF\BBCodeParser;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * The purpose of this class is to show the user the registration agreement
 * and privacy policy, and to ask the user to accept them if they haven't
 * already done so.
 */
class Agreement implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Agreement',
			'canRequireAgreement' => 'canRequireAgreement',
			'canRequirePrivacyPolicy' => 'canRequirePrivacyPolicy',
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
	 * Shows the registration agreement and privacy policy.
	 *
	 * If the user hasn't yet accepted one or both of them, also shows the
	 * button to do so.
	 */
	public function execute(): void
	{
		$this->prepareAgreementContext();

		Lang::load('Agreement');
		Theme::loadTemplate('Agreement');

		$page_title = '';

		if (!empty(Utils::$context['agreement']) && !empty(Utils::$context['privacy_policy'])) {
			$page_title = Lang::$txt['agreement_and_privacy_policy'];
		} elseif (!empty(Utils::$context['agreement'])) {
			$page_title = Lang::$txt['agreement'];
		} elseif (!empty(Utils::$context['privacy_policy'])) {
			$page_title = Lang::$txt['privacy_policy'];
		}

		Utils::$context['page_title'] = $page_title;
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=agreement',
			'name' => Utils::$context['page_title'],
		];

		if (isset($_SESSION['old_url'])) {
			$_SESSION['redirect_url'] = $_SESSION['old_url'];
		}
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

	/**
	 * Checks whether this user needs to accept the registration agreement.
	 *
	 * @return bool Whether they need to accept the agreement.
	 */
	public static function canRequireAgreement(): bool
	{
		// Guests can't agree
		if (!empty(User::$me->is_guest) || empty(Config::$modSettings['requireAgreement'])) {
			return false;
		}

		$agreement_lang = file_exists(Config::$boarddir . '/agreement.' . User::$me->language . '.txt') ? User::$me->language : 'default';

		if (empty(Config::$modSettings['agreement_updated_' . $agreement_lang])) {
			return false;
		}

		Utils::$context['agreement_accepted_date'] = empty(Theme::$current->options['agreement_accepted']) ? 0 : Theme::$current->options['agreement_accepted'];

		// A new timestamp means that there are new changes to the registration agreement and must therefore be shown.
		return empty(Theme::$current->options['agreement_accepted']) || Config::$modSettings['agreement_updated_' . $agreement_lang] > Theme::$current->options['agreement_accepted'];
	}

	/**
	 * Checks whether this user needs to accept the privacy policy.
	 *
	 * @return bool Whether they need to accept the policy.
	 */
	public static function canRequirePrivacyPolicy(): bool
	{
		if (!empty(User::$me->is_guest) || empty(Config::$modSettings['requirePolicyAgreement'])) {
			return false;
		}

		$policy_lang = !empty(Config::$modSettings['policy_' . User::$me->language]) ? User::$me->language : Lang::$default;

		if (empty(Config::$modSettings['policy_updated_' . $policy_lang])) {
			return false;
		}

		Utils::$context['privacy_policy_accepted_date'] = empty(Theme::$current->options['policy_accepted']) ? 0 : Theme::$current->options['policy_accepted'];

		return empty(Theme::$current->options['policy_accepted']) || Config::$modSettings['policy_updated_' . $policy_lang] > Theme::$current->options['policy_accepted'];
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

	/**
	 * Loads the registration agreement and privacy policy into Utils::$context
	 * for display.
	 */
	protected function prepareAgreementContext(): void
	{
		// What, if anything, do they need to accept?
		Utils::$context['can_accept_agreement'] = !empty(Config::$modSettings['requireAgreement']) && self::canRequireAgreement();
		Utils::$context['can_accept_privacy_policy'] = !empty(Config::$modSettings['requirePolicyAgreement']) && self::canRequirePrivacyPolicy();
		Utils::$context['accept_doc'] = Utils::$context['can_accept_agreement'] || Utils::$context['can_accept_privacy_policy'];

		if (!Utils::$context['accept_doc'] || Utils::$context['can_accept_agreement']) {
			// Grab the agreement.
			// Have we got a localized one?
			if (file_exists(Config::$boarddir . '/agreement.' . User::$me->language . '.txt')) {
				Utils::$context['agreement_file'] = Config::$boarddir . '/agreement.' . User::$me->language . '.txt';
			} elseif (file_exists(Config::$boarddir . '/agreement.txt')) {
				Utils::$context['agreement_file'] = Config::$boarddir . '/agreement.txt';
			}

			if (!empty(Utils::$context['agreement_file'])) {
				$cache_id = strtr(Utils::$context['agreement_file'], [Config::$boarddir => '', '.txt' => '', '.' => '_']);
				Utils::$context['agreement'] = BBCodeParser::load()->parse(file_get_contents(Utils::$context['agreement_file']), true, $cache_id);
			} elseif (Utils::$context['can_accept_agreement']) {
				ErrorHandler::fatalLang('error_no_agreement', false);
			}
		}

		if (!Utils::$context['accept_doc'] || Utils::$context['can_accept_privacy_policy']) {
			// Have we got a localized policy?
			if (!empty(Config::$modSettings['policy_' . User::$me->language])) {
				Utils::$context['privacy_policy'] = BBCodeParser::load()->parse(Config::$modSettings['policy_' . User::$me->language]);
			} elseif (!empty(Config::$modSettings['policy_' . Lang::$default])) {
				Utils::$context['privacy_policy'] = BBCodeParser::load()->parse(Config::$modSettings['policy_' . Lang::$default]);
			}
			// Then I guess we've got nothing
			elseif (Utils::$context['can_accept_privacy_policy']) {
				ErrorHandler::fatalLang('error_no_privacy_policy', false);
			}
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Agreement::exportStatic')) {
	Agreement::exportStatic();
}

?>