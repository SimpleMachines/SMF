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

			if (!isset($log_changes[$row['id_request']]))
				$log_changes[$row['id_request']] = array(
					'id_request' => $row['id_request'],
					'status' => $this->_details['req_action'] == 'approve' ? 1 : 2, // 1 = approved, 2 = rejected
					'id_member_acted' => $this->_details['id_member'],
					'member_name_acted' => $this->_details['member_name'],
					'time_acted' => time(),
					'act_reason' => $this->_details['req_action'] != 'approve' && !empty($this->_details['reason']) && !empty($this->_details['reason'][$row['id_request']]) ? $smcFunc['htmlspecialchars']($this->_details['reason'][$row['id_request']], ENT_QUOTES) : '',
				);

			// If we are approving work out what their new group is.
			if ($this->_details['req_action'] == 'approve')
			{
				// For people with more than one request at once.
				if (isset($group_changes[$row['id_member']]))
				{
					$row['additional_groups'] = $group_changes[$row['id_member']]['add'];
					$row['primary_group'] = $group_changes[$row['id_member']]['primary'];
				}
				else
					$row['additional_groups'] = explode(',', $row['additional_groups']);

				// Don't have it already?
				if ($row['primary_group'] == $row['id_group'] || in_array($row['id_group'], $row['additional_groups']))
					continue;

				// Should it become their primary?
				if ($row['primary_group'] == 0 && $row['hidden'] == 0)
					$row['primary_group'] = $row['id_group'];
				else
					$row['additional_groups'][] = $row['id_group'];

				// Add them to the group master list.
				$group_changes[$row['id_member']] = array(
					'primary' => $row['primary_group'],
					'add' => $row['additional_groups'],
				);
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
				// Make the group changes.
				foreach ($group_changes as $id => $groups)
				{
					// Sanity check!
					foreach ($groups['add'] as $key => $value)
						if ($value == 0 || trim($value) == '')
							unset($groups['add'][$key]);

					$smcFunc['db_query']('', '
						UPDATE {db_prefix}members
						SET id_group = {int:primary_group}, additional_groups = {string:additional_groups}
						WHERE id_member = {int:selected_member}',
						array(
							'primary_group' => $groups['primary'],
							'selected_member' => $id,
							'additional_groups' => implode(',', $groups['add']),
						)
					);
				}

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

		// Some changes to log?
		if (!empty($log_changes))
		{
			foreach ($log_changes as $id_request => $details)
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_group_requests
					SET status = {int:status},
						id_member_acted = {int:id_member_acted},
						member_name_acted = {string:member_name_acted},
						time_acted = {int:time_acted},
						act_reason = {string:act_reason}
					WHERE id_request = {int:id_request}',
					$details
				);
			}
		}

		return true;
	}
}

?>
