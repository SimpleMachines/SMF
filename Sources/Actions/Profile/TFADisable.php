<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions\Profile;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Profile;
use SMF\Sapi;
use SMF\User;
use SMF\Utils;

/**
 * Provides interface to disable two-factor authentication in SMF.
 */
class TFADisable implements ActionInterface
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
		if (empty(User::$me->tfa_secret)) {
			Utils::redirectexit('action=profile;area=account;u=' . Profile::$member->id);
		}

		// Bail if we're forcing SSL for authentication and the network connection isn't secure.
		if (!empty(Config::$modSettings['force_ssl']) && !Sapi::httpsOn()) {
			ErrorHandler::fatalLang('login_ssl_required', false);
		}

		// The admin giveth...
		if (Config::$modSettings['tfa_mode'] == 3 && User::$me->is_owner) {
			ErrorHandler::fatalLang('cannot_disable_tfa', false);
		}

		if (Config::$modSettings['tfa_mode'] == 2 && User::$me->is_owner) {
			$request = Db::$db->query(
				'',
				'SELECT id_group
				FROM {db_prefix}membergroups
				WHERE tfa_required = {int:tfa_required}
					AND id_group IN ({array_int:groups})',
				[
					'tfa_required' => 1,
					'groups' => array_diff(User::$me->groups, [User::$me->post_group_id]),
				],
			);
			$tfa_required_groups = Db::$db->num_rows($request);
			Db::$db->free_result($request);

			// They belong to a membergroup that requires tfa.
			if (!empty($tfa_required_groups)) {
				ErrorHandler::fatalLang('cannot_disable_tfa2', false);
			}
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