<?php

/**
 * This taks handles notifying someone that a user has
 * requested to join a group they moderate.
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
 * Class GroupAct_Notify_Background
 */
class GroupAct_Notify_Background extends SMF_BackgroundTask
{
    /**
     * This executes the task - loads up the information, puts the email in the queue and inserts alerts as needed.
     * @return bool Always returns true
     */
	public function execute()
	{
		global $sourcedir, $smcFunc, $language, $modSettings;

		// Get the details of all the members concerned...
		$request = $smcFunc['db_query']('', '
			SELECT lgr.id_request, lgr.id_member, lgr.id_group, mem.email_address,
				mem.lngfile, mem.member_name,  mg.group_name
			FROM {db_prefix}log_group_requests AS lgr
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
			WHERE lgr.id_request IN ({array_int:request_list})
			ORDER BY mem.lngfile',
			array(
				'request_list' => $this->_details['request_list'],
			)
		);
		$affected_users = array();
		$members = array();
		$alert_rows = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$members[] = $row['id_member'];
			$row['lngfile'] = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

			// If we are approving, add them!
			if ($this->_details['status'] == 'approve')
			{
				// Hack in blank permissions so that allowedTo() will fail.
				require_once($sourcedir . '/Security.php');
				$user_info['permissions'] = array();

				// For the moddlog
				$user_info['id'] = $this->_details['member_id'];
				$user_info['ip'] = $this->_details['member_ip'];

				require_once($sourcedir . '/Subs-Membergroups.php');
				addMembersToGroup($row['id_member'], $row['id_group'], 'auto', true);
			}

			// Build the required information array
			$affected_users[$row['id_member']] = array(
				'rid' => $row['id_request'],
				'member_id' => $row['id_member'],
				'member_name' => $row['member_name'],
				'group_id' => $row['id_group'],
				'group_name' => $row['group_name'],
				'email' => $row['email_address'],
				'language' => $row['lngfile'],
			);
		}
		$smcFunc['db_free_result']($request);

		// Ensure everyone who is online gets their changes right away.
		updateSettings(array('settings_updated' => time()));

		if (!empty($affected_users))
		{
			require_once($sourcedir . '/Subs-Notify.php');
			$prefs = getNotifyPrefs($members, array('groupr_approved', 'groupr_rejected'), true);

			// They are being approved?
			if ($this->_details['status'] == 'approve')
			{
				$pref_name = 'approved';
				$email_template_name = 'mc_group_approve';
				$email_message_id_prefix = 'grpapp';
			}
			// Otherwise, they are getting rejected (With or without a reason).
			else
			{
				$pref_name = 'rejected';
				$email_template_name = empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason';
				$email_message_id_prefix = 'grprej';
			}

			// Same as for approving, kind of.
			foreach ($affected_users as $user)
			{
				$pref = !empty($prefs[$user['member_id']]['groupr_' . $pref_name]) ? $prefs[$user['member_id']]['groupr_' . $pref_name] : 0;
				$custom_reason = isset($this->_details['reason']) && isset($this->_details['reason'][$user['rid']]) ? $this->_details['reason'][$user['rid']] : '';

				if ($pref & self::RECEIVE_NOTIFY_ALERT)
				{
					$alert_rows[] = array(
						'alert_time' => time(),
						'id_member' => $user['member_id'],
						'content_type' => 'groupr',
						'content_id' => 0,
						'content_action' => $pref_name,
						'is_read' => 0,
						'extra' => $smcFunc['json_encode'](array('group_name' => $user['group_name'], 'reason' => !empty($custom_reason) ? '<br><br>' . $custom_reason : '')),
					);
				}

				if ($pref & self::RECEIVE_NOTIFY_EMAIL)
				{
					// Emails are a bit complicated. We have to do language stuff.
					require_once($sourcedir . '/Subs-Post.php');
					require_once($sourcedir . '/ScheduledTasks.php');
					loadEssentialThemeData();

					$replacements = array(
						'USERNAME' => $user['member_name'],
						'GROUPNAME' => $user['group_name'],
					);

					if (!empty($custom_reason))
						$replacements['REASON'] = $custom_reason;

					$emaildata = loadEmailTemplate($email_template_name, $replacements, $user['language']);

					sendmail($user['email'], $emaildata['subject'], $emaildata['body'], null, $email_message_id_prefix . $user['rid'], $emaildata['is_html'], 2);
				}
			}

			// Insert the alerts if any
			if (!empty($alert_rows))
			{
				$smcFunc['db_insert']('',
					'{db_prefix}user_alerts',
					array(
						'alert_time' => 'int', 'id_member' => 'int', 'content_type' => 'string',
						'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string',
					),
					$alert_rows,
					array()
				);

				updateMemberData(array_keys($affected_users), array('alerts' => '+'));
			}
		}

		return true;
	}
}

?>