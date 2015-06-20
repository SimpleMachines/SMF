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

class GroupReq_Notify_Background extends SMF_BackgroundTask
{
	public function execute()
 	{
 		global $sourcedir, $smcFunc, $language, $modSettings, $scripturl;

		// Do we have any group moderators?
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}group_moderators
			WHERE id_group = {int:selected_group}',
			array(
				'selected_group' => $this->_details['id_group'],
			)
		);
		$moderators = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$moderators[] = $row['id_member'];
		$smcFunc['db_free_result']($request);

		require_once($sourcedir . '/Subs-Members.php');

		// Make sure anyone who can moderate_membergroups gets notified as well
		$moderators = array_unique(array_merge($moderators, membersAllowedTo('manage_membergroups')));

		if (!empty($moderators))
		{
			// Figure out who wants to be alerted/emailed about this
			$data = array('alert' => array(), 'email' => array());

			require_once($sourcedir . '/Subs-Notify.php');
			$prefs = getNotifyPrefs($moderators, 'request_group', true);

			// Bitwise comparisons are fun...
			foreach ($moderators as $mod)
			{
				if (!empty($prefs[$mod]['request_group']))
				{
					if ($prefs[$mod]['request_group'] & 0x01)
						$data['alert'][] = $mod;

					if ($prefs[$mod]['request_group'] & 0x02)
						$data['email'][] = $mod;
				}
			}

			if (!empty($data['alert']))
			{
				$alert_rows = array();

				foreach ($data['alert'] as $group_mod)
				{
					$alert_rows[] = array(
						'alert_time' => $this->_details['time'],
						'id_member' => $group_mod,
						'id_member_started' => $this->_details['id_member'],
						'member_name' => $this->_details['member_name'],
						'content_type' => 'member',
						'content_id' => 0,
						'content_action' => 'group_request',
						'is_read' => 0,
						'extra' => serialize(array('group_name' => $this->_details['group_name'])),
					);
				}

				$smcFunc['db_insert']('insert', '{db_prefix}user_alerts',
					array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
					'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
					$alert_rows, array()
				);

				updateMemberData($data['alert'], array('alerts' => '+'));
			}

			if (!empty($data['email']))
			{
				require_once($sourcedir . '/ScheduledTasks.php');
				require_once($sourcedir . '/Subs-Post.php');
				loadEssentialThemeData();

				$request = $smcFunc['db_query']('', '
					SELECT id_member, email_address, lngfile, member_name, mod_prefs
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:moderator_list})
					ORDER BY lngfile',
					array(
						'moderator_list' => $moderators,
					)
				);

				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$replacements = array(
						'RECPNAME' => $row['member_name'],
						'APPYNAME' => $this->_details['member_name'],
						'GROUPNAME' => $this->_details['group_name'],
						'REASON' => $this->_details['reason'],
						'MODLINK' => $scripturl . '?action=moderate;area=groups;sa=requests',
					);

					$emaildata = loadEmailTemplate('request_membership', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);
					sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'groupreq' . $this->_details['id_group'], false, 2);
				}
			}
		}

		return true;
	}
}

?>