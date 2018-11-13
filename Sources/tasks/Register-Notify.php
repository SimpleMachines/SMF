<?php

/**
 * This task handles notifying users when someone new signs up.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 4
 */

/**
 * Class Register_Notify_Background
 */
class Register_Notify_Background extends SMF_BackgroundTask
{
	/**
     * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $modSettings, $language, $scripturl;

		// Get everyone who could be notified.
		require_once($sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('moderate_forum');

		// Having successfully figured this out, now let's get the preferences of everyone.
		require_once($sourcedir . '/Subs-Notify.php');
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
					'content_id' => 0,
					'content_action' => 'register_' . $this->_details['notify_type'],
					'is_read' => 0,
					'extra' => '',
				);
			}

			$smcFunc['db_insert']('insert',
				'{db_prefix}user_alerts',
				array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int',
					'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int',
					'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
				$insert_rows,
				array('id_alert')
			);

			// And update the count of alerts for those people.
			updateMemberData($notifies['alert'], array('alerts' => '+'));
		}

		// Secondly, anyone who wants emails.
		if (!empty($notifies['email']))
		{
			// Emails are a bit complicated. We have to do language stuff.
			require_once($sourcedir . '/Subs-Post.php');
			require_once($sourcedir . '/ScheduledTasks.php');
			loadEssentialThemeData();

			// First, get everyone's language and details.
			$emails = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_member, lngfile, email_address
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				array(
					'members' => $notifies['email'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (empty($row['lngfile']))
					$row['lngfile'] = $language;
				$emails[$row['lngfile']][$row['id_member']] = $row['email_address'];
			}
			$smcFunc['db_free_result']($request);

			// Second, iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients)
			{
				$replacements = array(
					'USERNAME' => $this->_details['new_member_name'],
					'PROFILELINK' => $scripturl . '?action=profile;u=' . $this->_details['new_member_id']
				);
				$emailtype = 'admin_notify';

				// If they need to be approved add more info...
				if ($this->_details['notify_type'] == 'approval')
				{
					$replacements['APPROVALLINK'] = $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
					$emailtype .= '_approval';
				}

				$emaildata = loadEmailTemplate($emailtype, $replacements, empty($modSettings['userLanguage']) ? $language : $this_lang);

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