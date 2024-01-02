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

namespace SMF\PersonalMessage;

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * The popup menu for unread personal messages.
 */
class Popup
{
	/**
	 * Constructor.
	 */
	public function __construct()
	{
		Lang::load('PersonalMessage');

		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('PersonalMessage');
		}

		// We do not want to output debug information here.
		Config::$db_show_debug = false;

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'pm_popup';

		Utils::$context['can_send_pm'] = User::$me->allowedTo('pm_send');
		Utils::$context['can_draft'] = User::$me->allowedTo('pm_draft') && !empty(Config::$modSettings['drafts_pm_enabled']);
	}

	/**
	 * Shows the popup menu.
	 */
	public function show(): void
	{
		// So are we loading stuff?
		$pms = array_map(fn ($unread) => $unread->id, Received::loadUnread());

		if (!empty($pms)) {
			// Just quickly, it's possible that the number of PMs can get out of sync.
			$count_unread = count($pms);

			if ($count_unread != User::$me->unread_messages) {
				User::updateMemberData(User::$me->id, ['unread_messages' => $count_unread]);
				User::$me->unread_messages = count($pms);
			}

			// Now, actually fetch me some PMs.
			foreach (PM::load($pms) as $pm) {
				// Make sure we track the senders. We have some work to do for them.
				if (!empty($pm->member_from)) {
					$senders[] = $pm->member_from;
				}

				$pm->replied_to_you = $pm->id != $pm->head;
				$pm->time = Time::create('@' . $pm->msgtime)->format();
				$pm->pm_link = '<a href="' . Config::$scripturl . '?action=pm;f=inbox;pmid=' . $pm->id . '#msg' . $pm->id . '">' . $pm->subject . '</a>';

				Utils::$context['unread_pms'][$pm->id] = $pm;
			}

			User::load($senders);

			// Having loaded everyone, attach them to the PMs.
			foreach (Utils::$context['unread_pms'] as $id => $pm) {
				if (!empty(User::$loaded[$pm->member_from])) {
					Utils::$context['unread_pms'][$id]->member = User::$loaded[$pm->member_from]->format();
				}
			}
		}
	}
}

?>