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

namespace SMF\Tasks;

use SMF\Actions\Notify;
use SMF\Alert;
use SMF\User;
use SMF\Utils;

/**
 * This class contains code used to notify people that a new event has been
 * added to the calendar - but only when no topic has been created.
 */
class EventNew_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		// Get everyone who could be notified - those are the people who can see the calendar.
		$members = User::membersAllowedTo('calendar_view');

		// Don't alert the event creator
		if (!empty($this->_details['sender_id'])) {
			$members = array_diff($members, [$this->_details['sender_id']]);
		}

		// Having successfully figured this out, now let's get the preferences of everyone.
		$prefs = Notify::getNotifyPrefs($members, 'event_new', true);

		// Just before we go any further, we may not have the sender's name. Let's just quickly fix that.
		// If a guest creates the event, we wouldn't be capturing a username or anything.
		if (!empty($this->_details['sender_id']) && empty($this->_details['sender_name'])) {
			User::load($this->_details['sender_id'], User::LOAD_BY_ID, 'minimal');

			if (!empty(User::$loaded[$this->_details['sender_id']])) {
				$this->_details['sender_name'] = User::$loaded[$this->_details['sender_id']]->name;
			} else {
				$this->_details['sender_id'] = 0;
			}
		}

		// So now we find out who wants what.
		$alert_bits = [
			'alert' => self::RECEIVE_NOTIFY_ALERT,
			'email' => self::RECEIVE_NOTIFY_EMAIL,
		];
		$notifies = [];

		foreach ($prefs as $member => $pref_option) {
			foreach ($alert_bits as $type => $bitvalue) {
				if ($pref_option['event_new'] & $bitvalue) {
					$notifies[$type][] = $member;
				}
			}
		}

		// Firstly, anyone who wants alerts.
		if (!empty($notifies['alert'])) {
			// Alerts are relatively easy.
			$insert_rows = [];

			foreach ($notifies['alert'] as $member) {
				$insert_rows[] = [
					'alert_time' => $this->_details['time'],
					'id_member' => $member,
					'id_member_started' => $this->_details['sender_id'],
					'member_name' => $this->_details['sender_name'],
					'content_type' => 'event',
					'content_id' => $this->_details['event_id'],
					'content_action' => empty($this->_details['sender_id']) ? 'new_guest' : 'new',
					'is_read' => 0,
					'extra' => Utils::jsonEncode(
						[
							'event_id' => $this->_details['event_id'],
							'event_title' => $this->_details['event_title'],
						],
					),
				];
			}

			Alert::createBatch($insert_rows);
		}

		return true;
	}
}

?>