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
	/****************
	 * Public methods
	 ****************/

	/**
	 * I solemnly swear to no longer chase squirrels.
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
					[
						'id_member' => 'int',
						'id_theme' => 'int',
						'variable' => 'string',
						'value' => 'string',
					],
					[
						[
							User::$me->id,
							1,
							'agreement_accepted',
							time(),
						],
					],
					['id_member', 'id_theme', 'variable'],
				);

				Logging::logAction('agreement_accepted', ['applicator' => User::$me->id], 'user');
			}

			if ($can_accept_privacy_policy) {
				Db::$db->insert(
					'replace',
					'{db_prefix}themes',
					[
						'id_member' => 'int',
						'id_theme' => 'int',
						'variable' => 'string',
						'value' => 'string',
					],
					[
						[
							User::$me->id,
							1,
							'policy_accepted',
							time(),
						],
					],
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
		$route[0] = 'accepttermsofservice';

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
		$params = array_merge($params, self::parseActionRoute($route));

		// Change 'termsofservice' back to 'agreement'.
		$params['action'] = 'acceptagreement';

		return $params;
	}
}
