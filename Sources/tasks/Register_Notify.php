<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Tasks;

use SMF\Config;
use SMF\User;
use SMF\Db\DatabaseApi as Db;

/**
 * This class contains code used to notify people when a new member new signs up.
 */
class Register_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// Get everyone who could be notified.
		require_once(Config::$sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('moderate_forum');

		// Having successfully figured this out, now let's get the preferences of everyone.
		require_once(Config::$sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($members, 'member_register', true);

		// So now we find out who wants what.
		$alert_bits = array(
			'alert' => self::RECEIVE_NOTIFY_ALERT,
			'email' => self::RECEIVE_NOTIFY_EMAIL,
		);
		$notifies = array();

		foreach ($prefs as $member => $pref_option)
		{
			foreach ($alert_bits as $type => $bitvalue)
				if ($pref_option['member_register'] & $bitvalue)
					$notifies[$type][] = $member;
		}

		// Firstly, anyone who wants alerts.
		if (!empty($notifies['alert']))
		{
			// Alerts are relatively easy.
			$insert_rows = array();
			foreach ($notifies['alert'] as $member)
			{
				$insert_rows[] = array(
					'alert_time' => $this->_details['time'],
					'id_member' => $member,
					'id_member_started' => $this->_details['new_member_id'],
					'member_name' => $this->_details['new_member_name'],
					'content_type' => 'member',
					'content_id' => $this->_details['new_member_id'],
					'content_action' => 'register_' . $this->_details['notify_type'],
					'is_read' => 0,
					'extra' => '',
				);
			}

			Db::$db->insert('insert',
				'{db_prefix}user_alerts',
				array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int',
					'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int',
					'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
				$insert_rows,
				array('id_alert')
			);

			// And update the count of alerts for those people.
			User::updateMemberData($notifies['alert'], array('alerts' => '+'));
		}

		// Secondly, anyone who wants emails.
		if (!empty($notifies['email']))
		{
			// Emails are a bit complicated. We have to do language stuff.
			require_once(Config::$sourcedir . '/Msg.php');
			require_once(Config::$sourcedir . '/ScheduledTasks.php');
			loadEssentialThemeData();

			// First, get everyone's language and details.
			$emails = array();
			$request = Db::$db->query('', '
				SELECT id_member, lngfile, email_address
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				array(
					'members' => $notifies['email'],
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				if (empty($row['lngfile']))
					$row['lngfile'] = Config::$language;
				$emails[$row['lngfile']][$row['id_member']] = $row['email_address'];
			}
			Db::$db->free_result($request);

			// Second, iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients)
			{
				$replacements = array(
					'USERNAME' => $this->_details['new_member_name'],
					'PROFILELINK' => Config::$scripturl . '?action=profile;u=' . $this->_details['new_member_id']
				);
				$emailtype = 'admin_notify';

				// If they need to be approved add more info...
				if ($this->_details['notify_type'] == 'approval')
				{
					$replacements['APPROVALLINK'] = Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
					$emailtype .= '_approval';
				}

				$emaildata = loadEmailTemplate($emailtype, $replacements, empty(Config::$modSettings['userLanguage']) ? Config::$language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $id_member => $email_address)
					sendmail($email_address, $emaildata['subject'], $emaildata['body'], null, 'newmember' . $this->_details['new_member_id'], $emaildata['is_html'], 0);
			}
		}

		// And now we're all done.
		return true;
	}
}

?>