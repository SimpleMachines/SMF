<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Fetches the list of preferences (or a single/subset of preferences) for
 * notifications for one or more users.
 *
 * @param int|array $members A user id or an array of (integer) user ids to load preferences for
 * @param string|array $prefs An empty string to load all preferences, or a string (or array) of preference name(s) to load
 * @param bool $process_default Whether to apply the default values to the members' values or not.
 * @return array An array of user ids => array (pref name -> value), with user id 0 representing the defaults
 */
function getNotifyPrefs($members, $prefs = '', $process_default = false)
{
	global $smcFunc;

	// We want this as an array whether it is or not.
	$members = is_array($members) ? $members : (array) $members;

	if (!empty($prefs))
		$prefs = is_array($prefs) ? $prefs : (array) $prefs;

	$result = array();

	// We want to now load the default, which is stored with a member id of 0.
	$members[] = 0;

	$request = $smcFunc['db_query']('', '
		SELECT id_member, alert_pref, alert_value
		FROM {db_prefix}user_alerts_prefs
		WHERE id_member IN ({array_int:members})' . (!empty($prefs) ? '
			AND alert_pref IN ({array_string:prefs})' : ''),
		array(
			'members' => $members,
			'prefs' => $prefs,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$result[$row['id_member']][$row['alert_pref']] = $row['alert_value'];

	// We may want to keep the default values separate from a given user's. Or we might not.
	if ($process_default && isset($result[0]))
	{
		foreach ($members as $member)
			if (isset($result[$member]))
				$result[$member] += $result[0];
			else
				$result[$member] = $result[0];

		unset ($result[0]);
	}

	return $result;
}

/**
 * Sets the list of preferences for a single user.
 *
 * @param int $memID The user whose preferences you are setting
 * @param array $prefs An array key of pref -> value
 */
function setNotifyPrefs($memID, $prefs = array())
{
	global $smcFunc;

	if (empty($prefs) || !is_int($memID))
		return;

	$update_rows = array();
	foreach ($prefs as $k => $v)
		$update_rows[] = array($memID, $k, $v);

	$smcFunc['db_insert']('replace',
		'{db_prefix}user_alerts_prefs',
		array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'int'),
		$update_rows,
		array('id_member', 'alert_pref')
	);
}

/**
 * Deletes notification preference
 *
 * @param int $memID The user whose preference you're setting
 * @param array $prefs The preferences to delete
 */
function deleteNotifyPrefs($memID, array $prefs)
{
	global $smcFunc;

	if (empty($prefs) || empty($memID))
		return;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}user_alerts_prefs
		WHERE id_member = {int:member}
			AND alert_pref IN ({array_string:prefs})',
		array(
			'member' => $memID,
			'prefs' => $prefs,
		)
	);
}

?>