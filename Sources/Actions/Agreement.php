<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Diff\EditDiff;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Parser;
use SMF\Routable;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * The purpose of this class is to show the user the registration agreement
 * and privacy policy, and to ask the user to accept them if they haven't
 * already done so.
 */
class Agreement implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'show';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'show' => 'show',
		'history' => 'history',
	];

	/****************
	 * Public methods
	 ****************/

	public function isAgreementAction(): bool
	{
		return true;
	}

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			\call_user_func($call);
		}
	}

	/**
	 * Shows the registration agreement and privacy policy.
	 *
	 * If the user hasn't yet accepted one or both of them, also shows the
	 * button to do so.
	 */
	public function show(): void
	{
		$this->prepareAgreementContext();

		Theme::loadTemplate('Agreement');

		$page_title = '';

		if (!empty(Utils::$context['agreement']) && !empty(Utils::$context['privacy_policy'])) {
			$page_title = Lang::getTxt('agreement_and_privacy_policy', file: 'Agreement');
		} elseif (!empty(Utils::$context['agreement'])) {
			$page_title = Lang::getTxt('registration_agreement', file: 'General');
		} elseif (!empty(Utils::$context['privacy_policy'])) {
			$page_title = Lang::getTxt('privacy_policy', file: 'General');
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

	/**
	 * Shows a specific snapshot from the edit history of the registration
	 * agreement or privacy policy.
	 */
	public function history(): void
	{
		// We want template_diff() in the EditHistory template file.
		Theme::loadTemplate('EditHistory');

		// We do not want to output debug information here.
		Config::$db_show_debug = false;

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'diff';

		Utils::$context['diff'] = '';

		if (!isset($_GET['doc'])) {
			return;
		}

		if (!empty($_GET['doc'])) {
			$doc = 'policy';
			$text = Config::$modSettings[$doc . '_' . $_GET['lang']] ?? '';
		} else {
			$doc = 'agreement';
			$agreement_file = Config::$languagesdir . '/' . ($_GET['lang'] ?? 'en_US') . '/agreement.txt';
			$text = is_file($agreement_file) ? file_get_contents($agreement_file) : '';
		}

		$hash = hash('crc32c', $text);

		// Find the requested diff in the edit history.
		$edit_history = (array) Utils::jsonDecode(Config::$modSettings[$doc . '_history_' . $_GET['lang']] ?? '[]', true);

		for ($i = 0; $i < \count($edit_history); $i++) {
			$diff = new EditDiff();

			try {
				$diff->import($edit_history[$i]);
			} catch (\Throwable $e) {
				break;
			}

			// Uh-oh. The edit history got corrupted somehow.
			if ($hash !== $diff->label1) {
				Utils::$context['diff'] = '<div style="white-space:pre-wrap">' . Parser::transform($text) . '</div>';
				Utils::$context['page_title'] = isset($time) ? strip_tags((new Time($time))->setTimezone(User::getTimezone())->format()) : ($doc === 'policy' ? Lang::getTxt('privacy_policy', file: 'General') : Lang::getTxt('registration_agreement', file: 'General'));

				break;
			}

			// Found it.
			if ($diff->label1 === $_GET['hash']) {
				Utils::$context['diff'] = '<div style="white-space:pre-wrap">' . $diff->formatHtml($text, true, true) . '</div>';
				Utils::$context['page_title'] = strip_tags((new Time($diff->time1))->setTimezone(User::getTimezone())->format());

				return;
			}

			$text = $diff->apply($text);
			$hash = hash('crc32c', $text);
			$time = $diff->time1;
		}

		// Something went wrong.
		Utils::$context['diff'] = '';
	}

	/***********************
	 * Public static methods
	 ***********************/

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

		$agreement_lang = file_exists(Config::$languagesdir . '/' . User::$me->language . '/agreement.txt') ? User::$me->language : 'default';

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

	/**
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array $params URL query parameters.
	 * @return array Contains two elements: ['route' => [], 'params' => []].
	 *    The 'route' element contains the routing path. The 'params' element
	 *    contains any $params that weren't incorporated into the route.
	 */
	public static function buildRoute(array $params): array
	{
		$route = self::buildActionRoute($params);

		// Rename the action to avoid a naming conflict with the agreement.txt file.
		$route[0] = 'termsofservice';

		return ['route' => $route, 'params' => $params];
	}

	/**
	 * Parses a route to get URL query parameters.
	 *
	 * @param array $route Array of routing path components.
	 * @param array $params Any existing URL query parameters.
	 * @return array URL query parameters
	 */
	public static function parseRoute(array $route, array $params = []): array
	{
		// Change 'termsofservice' back to 'agreement'.
		if (($route[0] ?? null) === 'termsofservice') {
			$route[0] = 'agreement';
		}

		$params = array_merge($params, self::parseActionRoute($route));

		return $params;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
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
			if (file_exists(Config::$languagesdir . '/' . User::$me->language . '/agreement.txt')) {
				Utils::$context['agreement_file'] = Config::$languagesdir . '/' . User::$me->language . '/agreement.txt';
			} elseif (file_exists(Config::$languagesdir . '/en_US/agreement.txt')) {
				Utils::$context['agreement_file'] = Config::$languagesdir . '/en_US/agreement.txt';
			}

			if (!empty(Utils::$context['agreement_file'])) {
				$cache_id = strtr(Utils::$context['agreement_file'], [Config::$languagesdir => '', '.txt' => '', '.' => '_']);
				Utils::$context['agreement'] = Parser::transform(
					string: file_get_contents(Utils::$context['agreement_file']),
					options: ['cache_id' => $cache_id, 'hard_breaks' => 0],
				);
			} elseif (Utils::$context['can_accept_agreement']) {
				ErrorHandler::fatalLang('error_no_agreement', false);
			}
		}

		if (!Utils::$context['accept_doc'] || Utils::$context['can_accept_privacy_policy']) {
			// Have we got a localized policy?
			if (!empty(Config::$modSettings['policy_' . User::$me->language])) {
				Utils::$context['privacy_policy'] = Parser::transform(
					string: Config::$modSettings['policy_' . User::$me->language],
					options: ['hard_breaks' => 0],
				);
			} elseif (!empty(Config::$modSettings['policy_' . Lang::$default])) {
				Utils::$context['privacy_policy'] = Parser::transform(
					string: Config::$modSettings['policy_' . Lang::$default],
					options: ['hard_breaks' => 0],
				);
			}
			// Then I guess we've got nothing
			elseif (Utils::$context['can_accept_privacy_policy']) {
				ErrorHandler::fatalLang('error_no_privacy_policy', false);
			}
		}

		// Allow user to see what has changed in the agreement and/or privacy policy.
		foreach (['agreement' => 'agreement', 'privacy_policy' => 'policy'] as $long => $short) {
			if (!Utils::$context['can_accept_' . $long]) {
				continue;
			}

			if (!empty(Config::$modSettings[$short . '_history_' . User::$me->language])) {
				$text = Config::$modSettings[$short . '_' . User::$me->language] ?? '';
				$edit_history = (array) Utils::jsonDecode(Config::$modSettings[$short . '_history_' . User::$me->language] ?? '[]', true);
			} elseif (!empty(Config::$modSettings[$short . '_history_' . Lang::$default])) {
				$text = Config::$modSettings[$short . '_' . Lang::$default] ?? '';
				$edit_history = (array) Utils::jsonDecode(Config::$modSettings[$short . '_history_' . Lang::$default] ?? '[]', true);
			} else {
				$text = '';
				$edit_history = [];
			}

			usort($edit_history, fn($a, $b) => $b[0] <=> $a[0]);

			$hash = hash('crc32c', $text);

			// Find the version of the text from the last time they accepted.
			foreach ($edit_history as $key => $diff_data) {
				$diff = new EditDiff();

				try {
					$diff->import($diff_data);
				} catch (\Throwable $e) {
					break;
				}

				// Uh-oh. The edit history got corrupted somehow.
				if ($hash !== $diff->label1) {
					break;
				}

				if (
					// Found it.
					date_create($diff->time1)->format('U') <= Utils::$context[$long . '_accepted_date']
					// Or there is no further back we can go.
					|| $key === array_key_last($edit_history)
				) {
					$text = Parser::transform($text);

					Utils::$context['document_has_visible_changes'] = Utils::$context['document_has_visible_changes'] ?? trim($text) !== trim(Utils::$context[$long]);

					// If they asked to see the differences, show them.
					if (isset($_GET['diff']) && Utils::$context['document_has_visible_changes']) {
						Utils::$context[$long] = (new EditDiff(Utils::$context[$long], $text))->formatHtml(Utils::$context[$long], true, true);
					}

					return;
				}

				$text = $diff->apply($text);
				$hash = hash('crc32c', $text);
			}
		}
	}
}
