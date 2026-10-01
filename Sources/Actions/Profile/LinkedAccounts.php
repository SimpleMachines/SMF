<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Authentication\Credential;
use SMF\Authentication\Provider;
use SMF\Authentication\StepUp;
use SMF\Lang;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Shows a member which identity providers they can sign in with.
 */
class LinkedAccounts implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Builds the list of what this member has linked.
	 */
	public function execute(): void
	{
		$member = Profile::$member;

		/*
		 * Linking a provider adds a way into the account that outlives the
		 * session it was added from, and a password change does not revoke it.
		 * So this page asks who you are again before it will do anything, the
		 * same way turning a second factor off does.
		 */
		User::$me->validateSession(StepUp::FOR_CREDENTIALS);

		Utils::$context['sub_template'] = 'linked_accounts';
		Utils::$context['page_title'] = Lang::getTxt('linked_accounts', file: 'Profile');

		// Only they can manage their own, even though an admin can look.
		Utils::$context['can_manage'] = $member->is_me;
		Utils::$context['has_password'] = $member->hasUsablePassword();

		$providers = Provider::loadAll();
		Utils::$context['linked_accounts'] = [];

		foreach (Credential::listFor($member->id, Credential::TYPE_OIDC) as $id_auth => $credential) {
			$provider = $providers[(int) $credential['id_provider']] ?? null;

			Utils::$context['linked_accounts'][$id_auth] = [
				'id' => $id_auth,
				'provider' => $provider === null
					? Lang::getTxt('linked_accounts_unknown_provider', file: 'Profile')
					: $provider->title,
				'title' => $credential['title'],
				'date_created' => (int) $credential['date_created'],
				'date_last_used' => (int) $credential['date_last_used'],
			];
		}

		// What they could still add. Anything already linked is left out, since
		// one provider account cannot be attached twice.
		$linked_providers = array_map(
			fn($credential) => (int) $credential['id_provider'],
			Credential::listFor($member->id, Credential::TYPE_OIDC),
		);

		Utils::$context['available_providers'] = [];

		foreach ($providers as $provider) {
			if (!$provider->enabled || !$provider->isUsable() || \in_array($provider->id, $linked_providers, true)) {
				continue;
			}

			Utils::$context['available_providers'][$provider->id] = $provider;
		}

		// So the template can explain why the last one will not come off.
		Utils::$context['is_only_way_in'] = !Utils::$context['has_password']
			&& \count(Utils::$context['linked_accounts']) < 2;
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
}
