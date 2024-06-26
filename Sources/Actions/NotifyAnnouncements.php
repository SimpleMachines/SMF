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

use SMF\Lang;
use SMF\Utils;

/**
 * Toggles email notification preferences for announcements.
 */
class NotifyAnnouncements extends Notify
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The notification type that this action handles.
	 */
	public string $type = 'announcements';

	/******************
	 * Internal methods
	 ******************/

	/**
	 * This does nothing for announcements.
	 */
	protected function setId(): void
	{
	}

	/**
	 * Converts $_GET['sa'] to $_GET['mode'].
	 *
	 * sa=on/off is used for email subscribe/unsubscribe links.
	 */
	protected function saToMode(): void
	{
		if (!isset($_GET['mode']) && isset($_GET['sa'])) {
			$_GET['mode'] = $_GET['sa'] == 'on' ? 3 : 0;
			unset($_GET['sa']);
		}
	}

	/**
	 * Sets $this->mode.
	 */
	protected function setMode(): void
	{
		$this->saToMode();

		// What do we do?  Better ask if they didn't say..
		if (!isset($_GET['mode'])) {
			$this->ask();
		} else {
			$this->mode = $_GET['mode'];
		}
	}

	/**
	 * Sets any additional data needed for the ask template.
	 */
	protected function askTemplateData(): void
	{
		Utils::$context['sub_template'] = 'notify_announcements';
	}

	/**
	 * Updates the notification preference in the database.
	 */
	protected function changePref(): void
	{
		// Get the preference like normal, but then drop the alert bit.
		$this->setAlertPref();
		$this->alert_pref = $this->alert_pref & parent::PREF_EMAIL;

		// Update their announcement notification preference.
		parent::setNotifyPrefs((int) self::$member_info['id'], ['announcements' => $this->alert_pref]);

		// Show a confirmation message.
		Utils::$context['sub_template'] = 'notify_pref_changed';
		Utils::$context['notify_success_msg'] = Lang::getTxt('notify_announcements' . (!empty($this->alert_pref) ? '_subscribed' : '_unsubscribed'), self::$member_info);
	}

	/**
	 * Gets the success message to display.
	 */
	protected function getSuccessMsg(): string
	{
		return Lang::getTxt('notify_announcements' . (!empty($this->mode) ? '_subscribed' : '_unsubscribed'), self::$member_info);
	}
}

?>