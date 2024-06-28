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

namespace SMF\Sources\Tasks;

use SMF\Sources\Actions\Notify;
use SMF\Sources\Alert;
use SMF\Sources\User;

/**
 * This class contains code used to notify members when they have been added to
 * other members' buddy lists.
 */
class Buddy_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		// Figure out if the user wants to be notified.
		$prefs = Notify::getNotifyPrefs((int) $this->_details['receiver_id'], 'buddy_request', true);

		if ($prefs[$this->_details['receiver_id']]['buddy_request']) {
			Alert::create([
				'alert_time' => (int) $this->_details['time'],
				'id_member' => (int) $this->_details['receiver_id'],
				'id_member_started' => (int) $this->_details['id_member'],
				'member_name' => $this->_details['member_name'],
				'content_type' => 'member',
				'content_id' => (int) $this->_details['id_member'],
				'content_action' => 'buddy_request',
				'is_read' => 0,
				'extra' => '',
			]);
		}

		return true;
	}
}

?>