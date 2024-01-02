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
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Mail;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This class contains code used to notify group moderators that a member has
 * requested to join the group.
 */
class GroupReq_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// Do we have any group moderators?
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}group_moderators
			WHERE id_group = {int:selected_group}',
			[
				'selected_group' => $this->_details['id_group'],
			],
		);
		$moderators = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$moderators[] = $row['id_member'];
		}
		Db::$db->free_result($request);

		// Make sure anyone who can moderate_membergroups gets notified as well
		$moderators = array_unique(array_merge($moderators, User::membersAllowedTo('manage_membergroups')));

		if (!empty($moderators)) {
			// Figure out who wants to be alerted/emailed about this
			$data = ['alert' => [], 'email' => []];

			$prefs = Notify::getNotifyPrefs($moderators, 'request_group', true);

			// Bitwise comparisons are fun...
			foreach ($moderators as $mod) {
				if (!empty($prefs[$mod]['request_group'])) {
					if ($prefs[$mod]['request_group'] & 0x01) {
						$data['alert'][] = $mod;
					}

					if ($prefs[$mod]['request_group'] & 0x02) {
						$data['email'][] = $mod;
					}
				}
			}

			if (!empty($data['alert'])) {
				$alert_rows = [];

				foreach ($data['alert'] as $group_mod) {
					$alert_rows[] = [
						'alert_time' => $this->_details['time'],
						'id_member' => $group_mod,
						'id_member_started' => $this->_details['id_member'],
						'member_name' => $this->_details['member_name'],
						'content_type' => 'member',
						'content_id' => 0,
						'content_action' => 'group_request',
						'is_read' => 0,
						'extra' => Utils::jsonEncode(['group_name' => $this->_details['group_name']]),
					];
				}

				Alert::createBatch($alert_rows);
			}

			if (!empty($data['email'])) {
				Theme::loadEssential();

				$request = Db::$db->query(
					'',
					'SELECT id_member, email_address, lngfile, member_name, mod_prefs
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:moderator_list})
					ORDER BY lngfile',
					[
						'moderator_list' => $moderators,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$replacements = [
						'RECPNAME' => $row['member_name'],
						'APPLYNAME' => $this->_details['member_name'],
						'GROUPNAME' => $this->_details['group_name'],
						'REASON' => $this->_details['reason'],
						'MODLINK' => Config::$scripturl . '?action=moderate;area=groups;sa=requests',
					];

					$emaildata = Mail::loadEmailTemplate('request_membership', $replacements, empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Config::$language : $row['lngfile']);
					Mail::send($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'groupreq' . $this->_details['id_group'], $emaildata['is_html'], 2);
				}
			}
		}

		return true;
	}
}

?>