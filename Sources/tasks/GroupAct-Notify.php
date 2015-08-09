<?php

/**
 * This taks handles notifying someone that a user has
 * requeted to join a group they moderate.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

class GroupAct_Notify_Background extends SMF_BackgroundTask
{
	public function execute()
 	{
 		global $sourcedir, $smcFunc, $language, $modSettings, $scripturl;

		// Get the details of all the members concerned...
		$request = $smcFunc['db_query']('', '
			SELECT lgr.id_request, lgr.id_member, lgr.id_group, mem.email_address, mem.id_group AS primary_group,
				mem.additional_groups AS additional_groups, mem.lngfile, mem.member_name, mem.notify_announcements,
				mg.hidden, mg.group_name
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
		$group_changes = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['lngfile'] = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

			// If we are approving,  add them!
			if ($this->_details['req_action'] == 'approve')
			{
				require_once($sourcedir . '/Subs-Membergroups.php');
				addMembersToGroup($row['id_member'], $row['id_group'], 'auto', true);
			}

			// Build the required information array
			$affected_users[] = array(
				'rid' => $row['id_request'],
				'member_id' => $row['id_member'],
				'member_name' => $row['member_name'],
				'group_id' => $row['id_group'],
				'group_name' => $row['group_name'],
				'email' => $row['email_address'],
				'language' => $row['lngfile'],
				'receive_email' => $row['notify_announcements'],
			);
		}
		$smcFunc['db_free_result']($request);

		// Ensure everyone who is online gets their changes right away.
		updateSettings(array('settings_updated' => time()));

		if (!empty($affected_users))
		{
			require_once($sourcedir . '/Subs-Post.php');

			// They are being approved?
			if ($this->_details['req_action'] == 'approve')
			{

				foreach ($affected_users as $user)
				{
					//Did the user chose to not receive important notifications via email? If so... no congratulations email
					if (empty($user['receive_email']))
						continue;

					$replacements = array(
						'USERNAME' => $user['member_name'],
						'GROUPNAME' => $user['group_name'],
					);

					$emaildata = loadEmailTemplate('mc_group_approve', $replacements, $user['language']);

					sendmail($user['email'], $emaildata['subject'], $emaildata['body'], null, 'grpapp' . $user['rid'], false, 2);
				}
			}
			// Otherwise, they are getting rejected (With or without a reason).
			else
			{
				// Same as for approving, kind of.
				foreach ($affected_users as $user)
				{
					//Again, did the user chose to not receive important notifications via email?
					if (empty($user['receive_email']))
						continue;

					$custom_reason = isset($this->_details['reason']) && isset($this->_details['reason'][$user['rid']]) ? $this->_details['reason'][$user['rid']] : '';

					$replacements = array(
						'USERNAME' => $user['member_name'],
						'GROUPNAME' => $user['group_name'],
					);

					if (!empty($custom_reason))
						$replacements['REASON'] = $custom_reason;

					$emaildata = loadEmailTemplate(empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason', $replacements, $user['language']);

					sendmail($user['email'], $emaildata['subject'], $emaildata['body'], null, 'grprej' . $user['rid'], false, 2);
				}
			}
		}

		return true;
	}
}

?>
