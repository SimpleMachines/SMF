<?php

/**
 * This file contains background notification code
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

class Birthday_Notify_Background extends SMF_BackgroundTask
{
	public function execute()
 	{
		global $txt, $smcFunc, $txtBirthdayEmails, $smcFunc, $language, $modSettings, $scripturl;

		$greeting = isset($modSettings['birthday_email']) ? $modSettings['birthday_email'] : 'happy_birthday';

		// Get the month and day of today.
		$month = date('n'); // Month without leading zeros.
		$day = date('j'); // Day without leading zeros.

		// So who are the lucky ones?  Don't include those who are banned and those who don't want them.
		$result = $smcFunc['db_query']('', '
			SELECT id_member, real_name, lngfile, email_address
			FROM {db_prefix}members
			WHERE is_activated < 10
				AND MONTH(birthdate) = {int:month}
				AND DAYOFMONTH(birthdate) = {int:day}
				AND YEAR(birthdate) > {int:year}',
			array(
				'year' => 1,
				'month' => $month,
				'day' => $day,
			)
		);

		// Group them by languages.
		$birthdays = array();
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			if (!isset($birthdays[$row['lngfile']]))
				$birthdays[$row['lngfile']] = array();
			$birthdays[$row['lngfile']][$row['id_member']] = array(
				'name' => $row['real_name'],
				'email' => $row['email_address']
			);
		}
		$smcFunc['db_free_result']($result);

		if (!empty($birthdays))
		{
			require_once($sourcedir . '/ScheduledTasks.php');
			loadEssentialThemeData();

			// Send out the greetings!
			foreach ($birthdays as $lang => $members)
			{
				// We need to do some shuffling to make this work properly.
				loadLanguage('EmailTemplates', $lang);
				$txt['happy_birthday']['subject'] = $txtBirthdayEmails[$greeting . '_subject'];
				$txt['happy_birthday']['body'] = $txtBirthdayEmails[$greeting . '_body'];
				require_once($sourcedir . '/Subs-Notify.php');

				$prefs = getNotifyPrefs(array_keys($members), array('birthday'), true);

				foreach ($members as $member_id => $member)
				{
					$pref = !empty($prefs[$member_id]['birthday']) ? $prefs[$member_id]['birthday'] : 0;

					if ($pref & 0x01)
					{
						$alert_rows[] = array(
							'alert_time' => time(),
							'id_member' => $member_id,
							'content_type' => 'birthday',
							'content_id' => 0,
							'content_action' => 'msg',
							'is_read' => 0,
							'extra' => serialize(array('happy_birthday' => $txt['happy_birthday']['body']))),
						);
						updateMemberData($member_id, array('alerts' => '+'));
					}

					if ($pref & 0x02)
					{
						require_once($sourcedir . '/Subs-Post.php');

						$replacements = array(
							'REALNAME' => $member['name'],
						);

						$emaildata = loadEmailTemplate('happy_birthday', $replacements, $lang, false);

						sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, 'birthday', false, 4);
					}
				}
			}

			// Flush the mail queue, just in case.
			AddMailQueue(true);

			// Insert the alerts if any
			if (!empty($alert_rows))
				$smcFunc['db_insert']('',
					'{db_prefix}user_alerts',
					array(
						'alert_time' => 'int', 'id_member' => 'int', 'content_type' => 'string',
						'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string',
					),
					$alert_rows,
					array()
				);
		}

		return true;
	}
}

?>
