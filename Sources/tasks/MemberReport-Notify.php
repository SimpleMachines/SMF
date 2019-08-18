<?php

/**
 * This task handles notifying users when another member's profile gets reported.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * Class MemberReport_Notify_Background
 */
class MemberReport_Notify_Background extends SMF_BackgroundTask
{
	/**
     * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		global $smcFunc, $sourcedir, $modSettings, $language, $scripturl;

		// Anyone with moderate_forum can see this report
		require_once($sourcedir . '/Subs-Members.php');
		$members = membersAllowedTo('moderate_forum');

		// And don't send it to them if they're the one who reported it.
		$members = array_diff($members, array($this->_details['sender_id']));

		// Having successfully figured this out, now let's get the preferences of everyone.
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($members, 'member_report', true);

		// So now we find out who wants what.
		$alert_bits = array(
			'alert' => self::RECEIVE_NOTIFY_ALERT,
			'email' => self::RECEIVE_NOTIFY_EMAIL,
		);
		$notifies = array();

		foreach ($prefs as $member => $pref_option)
		{
			foreach ($alert_bits as $type => $bitvalue)
				if ($pref_option['member_report'] & $bitvalue)
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
					'id_member_started' => $this->_details['sender_id'],
					'member_name' => $this->_details['sender_name'],
					'content_type' => 'member',
					'content_id' => $this->_details['user_id'],
					'content_action' => 'report',
					'is_read' => 0,
					'extra' => $smcFunc['json_encode'](
						array(
							'report_link' => '?action=moderate;area=reportedmembers;sa=details;rid=' . $this->_details['report_id'], // We don't put $scripturl in these!
							'user_name' => $this->_details['user_name'],
						)
					),
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

			// Iterate through each language, load the relevant templates and set up sending.
			foreach ($emails as $this_lang => $recipients)
			{
				$replacements = array(
					'MEMBERNAME' => $this->_details['user_name'],
					'REPORTERNAME' => $this->_details['sender_name'],
					'PROFILELINK' => $scripturl . '?action=profile;u=' . $this->_details['user_id'],
					'REPORTLINK' => $scripturl . '?action=moderate;area=reportedmembers;sa=details;rid=' . $this->_details['report_id'],
					'COMMENT' => $this->_details['comment'],
				);

				$emaildata = loadEmailTemplate('report_member_profile', $replacements, empty($modSettings['userLanguage']) ? $language : $this_lang);

				// And do the actual sending...
				foreach ($recipients as $id_member => $email_address)
					sendmail($email_address, $emaildata['subject'], $emaildata['body'], null, 'ureport' . $this->_details['report_id'], $emaildata['is_html'], 2);
			}
		}

		// And now we're all done.
		return true;
	}
}

?>