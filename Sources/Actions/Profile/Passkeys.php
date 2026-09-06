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
use SMF\Lang;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Lets a member add and remove the passkeys they sign in with.
 */
class Passkeys implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Shows the member's passkeys, and takes one away if asked to.
	 */
	public function execute(): void
	{
		$member = Profile::$member;

		Utils::$context['sub_template'] = 'passkeys';
		Utils::$context['page_title'] = Lang::getTxt('passkeys', file: 'Profile');

		// Only they can manage their own, even though an admin can look. An
		// admin cannot add one for somebody else in any case: making a passkey
		// needs the device it will live on.
		Utils::$context['can_manage'] = $member->is_me;
		Utils::$context['has_password'] = $member->hasUsablePassword();
		Utils::$context['passkey_added'] = isset($_GET['added']);
		Utils::$context['passkey_error'] = '';

		if (Utils::$context['can_manage'] && isset($_GET['delete'])) {
			$this->delete($member);
		}

		Utils::$context['passkeys'] = [];

		foreach (Credential::listFor($member->id, Credential::TYPE_WEBAUTHN) as $id_auth => $credential) {
			Utils::$context['passkeys'][$id_auth] = [
				'id' => $id_auth,
				'title' => $credential['title'],
				'date_created' => (int) $credential['date_created'],
				'date_last_used' => (int) $credential['date_last_used'],
			];
		}

		// So the template can explain why the last one will not come off.
		Utils::$context['is_only_way_in'] = !Utils::$context['has_password']
			&& \count(Credential::listFor($member->id)) < 2;

		if (Utils::$context['can_manage']) {
			Theme::loadJavaScriptFile('webauthn.js', ['defer' => true, 'minimize' => true], 'smf_webauthn');
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
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}

	/**
	 * Takes one of the member's passkeys away.
	 *
	 * Done from a link with a session check on it, the same way an identity
	 * provider is unlinked. The profile area's own save machinery is no use
	 * here: it redirects before the area's function is reached, so nothing this
	 * class did would ever run.
	 *
	 * @param \SMF\User $member Whose passkey it is.
	 */
	protected function delete(User $member): void
	{
		User::$me->checkSession('get');

		$removed = Credential::remove(
			(int) $_GET['delete'],
			$member->id,
			$member->hasUsablePassword(),
		);

		/*
		 * Refusing is not an error the member did anything to cause: it means
		 * this passkey is the only thing that can still get them in, so it says
		 * so rather than looking like a failure.
		 */
		if (!$removed) {
			Utils::$context['passkey_error'] = Lang::getTxt('passkey_last_one', file: 'Profile');
		}
	}
}
