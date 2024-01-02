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
 * This class contains code used to notify moderators when there are posts that
 * need to be approved.
 */
class ApprovePost_Notify extends BackgroundTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		$msgOptions = $this->_details['msgOptions'];
		$topicOptions = $this->_details['topicOptions'];
		$posterOptions = $this->_details['posterOptions'];
		$type = $this->_details['type'];

		$members = [];
		$alert_rows = [];

		// We need to know who can approve this post.
		$modMembers = User::membersAllowedTo('approve_posts', $topicOptions['board']);

		$request = Db::$db->query(
			'',
			'SELECT id_member, email_address, lngfile
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $modMembers,
			],
		);

		$watched = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[] = $row['id_member'];
			$watched[$row['id_member']] = $row;
		}
		Db::$db->free_result($request);

		if (empty($members)) {
			return true;
		}

		$members = array_unique($members);
		$prefs = Notify::getNotifyPrefs($members, 'unapproved_post', true);

		foreach ($watched as $member => $data) {
			$pref = !empty($prefs[$member]['unapproved_post']) ? $prefs[$member]['unapproved_post'] : 0;

			if ($pref & self::RECEIVE_NOTIFY_EMAIL) {
				// Emails are a bit complicated. We have to do language stuff.
				Theme::loadEssential();

				$replacements = [
					'SUBJECT' => $msgOptions['subject'],
					'LINK' => Config::$scripturl . '?topic=' . $topicOptions['id'] . '.new#new',
				];

				$emaildata = Mail::loadEmailTemplate('alert_unapproved_post', $replacements, empty($data['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Config::$language : $data['lngfile']);
				Mail::send($data['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicOptions['id'], $emaildata['is_html']);
			}

			if ($pref & self::RECEIVE_NOTIFY_ALERT) {
				$alert_rows[] = [
					'alert_time' => time(),
					'id_member' => $member,
					'id_member_started' => $posterOptions['id'],
					'member_name' => $posterOptions['name'],
					'content_type' => $type == 'topic' ? 'topic' : 'msg',
					'content_id' => $type == 'topic' ? $topicOptions['id'] : $msgOptions['id'],
					'content_action' => 'unapproved_' . $type,
					'is_read' => 0,
					'extra' => Utils::jsonEncode([
						'topic' => $topicOptions['id'],
						'board' => $topicOptions['board'],
						'content_subject' => $msgOptions['subject'],
						'content_link' => Config::$scripturl . '?topic=' . $topicOptions['id'] . '.msg' . $msgOptions['id'] . '#msg' . $msgOptions['id'],
					]),
				];
			}
		}

		// Insert the alerts if any
		if (!empty($alert_rows)) {
			Alert::createBatch($alert_rows);
		}

		return true;
	}
}

?>