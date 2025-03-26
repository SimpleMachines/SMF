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
use SMF\Lang;
use SMF\Routable;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Despite the name, which is what it is for historical reasons, this action
 * doesn't actually send anything. It just shows a message for a guest.
 */
class SendActivation implements ActionInterface, Routable
{
	use ActionRouter;
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

		Utils::$context['page_title'] = Lang::getTxt('profile', file: 'General');
		Utils::$context['sub_template'] = 'after';
		Utils::$context['title'] = Lang::getTxt('activate_changed_email_title', file: 'General');
		Utils::$context['description'] = Lang::getTxt('activate_changed_email_desc', file: 'General');

		// Aaand we're gone!
		Utils::obExit();
	}
}

?>