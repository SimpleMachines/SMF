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
use SMF\Lang;
use SMF\Mail;
use SMF\Theme;
use SMF\Utils;

/**
 * This class contains code used to send out "Happy Birthday" emails.
 */
class Birthday_Notify extends ScheduledTask
{
	/**
	 * This executes the task: loads up the info, puts the email in the queue
	 * and inserts any alerts as needed.
	 *
	 * @return bool Always returns true
	 */
	public function execute()
	{
		$greeting = Config::$modSettings['birthday_email'] ?? 'happy_birthday';

		// Get the month and day of today.
		$month = date('n'); // Month without leading zeros.
		$day = date('j'); // Day without leading zeros.

		// So who are the lucky ones?  Don't include those who are banned and those who don't want them.
		$result = Db::$db->query(
			'',
			'SELECT id_member, real_name, lngfile, email_address
			FROM {db_prefix}members
			WHERE is_activated < 10
				AND MONTH(birthdate) = {int:month}
				AND DAYOFMONTH(birthdate) = {int:day}
				AND YEAR(birthdate) > {int:year}
				' . (Db::$db->title === POSTGRE_TITLE ? 'AND indexable_month_day(birthdate) = indexable_month_day({date:bdate})' : ''),
			[
				'year' => 1004,
				'month' => $month,
				'day' => $day,
				'bdate' => '1004-' . $month . '-' . $day, // a random leap year is here needed
			],
		);

		// Group them by languages.
		$birthdays = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			if (!isset($birthdays[$row['lngfile']])) {
				$birthdays[$row['lngfile']] = [];
			}

			$birthdays[$row['lngfile']][$row['id_member']] = [
				'name' => $row['real_name'],
				'email' => $row['email_address'],
			];
		}
		Db::$db->free_result($result);

		if (!empty($birthdays)) {
			Theme::loadEssential();

			// Send out the greetings!
			foreach ($birthdays as $lang => $members) {
				// We need to do some shuffling to make this work properly.
				Lang::load('EmailTemplates', $lang);
				Lang::$txt['happy_birthday_subject'] = Lang::$txtBirthdayEmails[$greeting . '_subject'];
				Lang::$txt['happy_birthday_body'] = Lang::$txtBirthdayEmails[$greeting . '_body'];

				$prefs = Notify::getNotifyPrefs(array_keys($members), ['birthday'], true);

				foreach ($members as $member_id => $member) {
					$pref = !empty($prefs[$member_id]['birthday']) ? $prefs[$member_id]['birthday'] : 0;

					// Let's load replacements ahead
					$replacements = [
						'REALNAME' => $member['name'],
					];

					if ($pref & self::RECEIVE_NOTIFY_ALERT) {
						$alertdata = Mail::loadEmailTemplate('happy_birthday', $replacements, $lang, false);

						// For the alerts, we need to replace \n line breaks with <br> line breaks.
						// For space saving sake, we'll be removing extra line breaks
						$alertdata['body'] = preg_replace("~\\s*[\r\n]+\\s*~", '<br>', $alertdata['body']);

						$alert_rows[] = [
							'alert_time' => time(),
							'id_member' => $member_id,
							'content_type' => 'birthday',
							'content_id' => 0,
							'content_action' => 'msg',
							'is_read' => 0,
							'extra' => Utils::jsonEncode(['happy_birthday' => $alertdata['body']]),
						];
					}

					if ($pref & self::RECEIVE_NOTIFY_EMAIL) {
						$emaildata = Mail::loadEmailTemplate('happy_birthday', $replacements, $lang, false);

						Mail::send($member['email'], $emaildata['subject'], $emaildata['body'], null, 'birthday', $emaildata['is_html'], 4);
					}
				}
			}

			// Flush the mail queue, just in case.
			Mail::addToQueue(true);

			// Insert the alerts if any
			if (!empty($alert_rows)) {
				Alert::createBatch($alert_rows);
			}
		}

		return true;
	}
}

?>