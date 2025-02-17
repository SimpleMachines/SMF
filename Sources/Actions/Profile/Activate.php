<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Logging;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Activates an account.
 */
class Activate implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Does the job.
	 */
	public function execute(): void
	{
		User::$me->isAllowedTo('moderate_forum');

		if (
			isset($_REQUEST['save'], Profile::$member->is_activated)
			&& Profile::$member->is_activated != User::ACTIVATED
		) {
			// If we are approving the deletion of an account, we do something special ;)
			if (
				Profile::$member->is_activated == User::REQUESTED_DELETE
				|| Profile::$member->is_activated == User::REQUESTED_DELETE_ANONYMIZE
				|| Profile::$member->is_activated == User::REQUESTED_DELETE_BANNED
				|| Profile::$member->is_activated == User::REQUESTED_DELETE_ANONYMIZE_BANNED
			) {
				User::delete(Utils::$context['id_member']);
				Utils::redirectexit();
			}

			$prev_is_activated = Profile::$member->is_activated;

			// Let the integrations know of the activation.
			IntegrationHook::call('integrate_activate', [Profile::$member->username]);

			// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
			User::updateMemberData(
				Profile::$member->id,
				[
					'is_activated' => Profile::$member->is_activated >= User::BANNED ? User::ACTIVATED_BANNED : User::ACTIVATED,
					'validation_code' => '',
				],
			);

			// Log what we did?
			Logging::logAction('approve_member', ['member' => Profile::$member->id], 'admin');

			// If we are doing approval, update the stats for the member just in case.
			if (
				in_array(
					$prev_is_activated,
					[
						User::UNAPPROVED,
						User::REQUESTED_DELETE,
						User::REQUESTED_DELETE_ANONYMIZE,
						User::NEED_COPPA,
						User::UNAPPROVED_BANNED,
						User::REQUESTED_DELETE_BANNED,
						User::REQUESTED_DELETE_ANONYMIZE_BANNED,
						User::NEED_COPPA_BANNED,
					],
				)
			) {
				Config::updateModSettings([
					'unapprovedMembers' => max(0, Config::$modSettings['unapprovedMembers'] - 1),
				]);
			}

			// Inform the user that their account has been approved.
			if (in_array($prev_is_activated, [User::UNAPPROVED, User::NEED_COPPA])) {
				$replacements = [
					'NAME' => Profile::$member->name,
					'USERNAME' => Profile::$member->username,
					'PROFILELINK' => Config::$scripturl . '?action=profile;u=' . Profile::$member->id,
					'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
				];

				$emaildata = Mail::loadEmailTemplate('admin_approve_accept', $replacements, Profile::$member->language);

				Mail::send(Profile::$member->email, $emaildata['subject'], $emaildata['body'], null, 'accapp' . Profile::$member->id, $emaildata['is_html'], 0);
			}

			// Make sure we update the stats too.
			Logging::updateStats('member', false);
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
}

?>