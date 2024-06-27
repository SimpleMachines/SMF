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
use SMF\Lang;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Handles the account section of the profile.
 */
class Account implements ActionInterface
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
		Profile::$member->loadThemeOptions();

		if (User::$me->allowedTo(['profile_identity_own', 'profile_identity_any', 'profile_password_own', 'profile_password_any'])) {
			Profile::$member->loadCustomFields('account');
		}

		Utils::$context['page_desc'] = Lang::$txt['account_info'];

		Profile::$member->setupContext(
			[
				'member_name',
				'real_name',
				'date_registered',
				'posts',
				'lngfile',
				'hr',
				'id_group',
				'hr',
				'email_address',
				'show_online',
				'hr',
				'tfa',
				'hr',
				'passwrd1',
				'passwrd2',
				'hr',
				'secret_question',
				'secret_answer',
			],
		);
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