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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Despite the name, which is what it is for historical reasons, this action
 * doesn't actually send anything. It just shows a message for a guest.
 */
class SendActivation implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Do the job.
	 */
	public function execute(): void
	{
		User::$me->is_guest = true;

		// Send them to the done-with-registration-login screen.
		Theme::loadTemplate('Register');

		Utils::$context['page_title'] = Lang::$txt['profile'];
		Utils::$context['sub_template'] = 'after';
		Utils::$context['title'] = Lang::$txt['activate_changed_email_title'];
		Utils::$context['description'] = Lang::$txt['activate_changed_email_desc'];

		// Aaand we're gone!
		Utils::obExit();
	}
}

?>