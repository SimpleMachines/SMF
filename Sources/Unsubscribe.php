<?php

/**
 * This file has the important job of taking care of help messages and the help center.
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

if (!defined('SMF'))
	die('No direct access...');

/**
 * Redirect to the user help ;).
 * It loads information needed for the help section.
 * It is accessed by ?action=help.
 *
 * @uses Help template and Manual language file.
 */
function DialogUnsubscribe()
{
	global $context;
	
	$context['unsubscribe_token_req'] = !empty($_REQUEST['token']) ? $_REQUEST['token'] : '';
	loadTemplate('Unsubscribe');
	loadLanguage('Unsubscribe');
	
	$subActions = array(
		'index' => 'DialogUnsubscribeIndex',
	);

	// CRUD $subActions as needed.
	call_integration_hook('integrate_unsubscribe', array(&$subActions));

	createToken('unsubscribe');

	$sa = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'index';
	call_helper($subActions[$sa]);
}

/**
 * Redirect to the user help ;).
 * It loads information needed for the help section.
 * It is accessed by ?action=help.
 *
 * @uses Help template and Manual language file.
 */
function DialogUnsubscribeIndex()
{
	global $txt, $context, $smcFunc, $sourcedir, $scripturl, $modSettings;

	if (empty($_GET['token']))
		return;

	$token = $_GET['token'];

	$memID = null;

	$request = $smcFunc['db_query']('','
		SELECT id_member
		FROM {db_prefix}themes
		WHERE variable = {string:token_name}
			AND value = {string:token}',
		array(
			'token_name' => 'unsubscribe_token',
			'token' => $token,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
			$memID = $row['id_member'];

	if (empty($memID))
		return;

	call_integration_hook('integrate_unsubscribe_index', array(&$subActions));
}

/**
 * Deletes notification preference
 *
 * @param int $memID The user whose preference you're setting
 * @param array $prefs The preferences to delete
 */
function unsubscribeMail($memID)
{
	global $smcFunc;

	if (empty($memID))
		return;
	
	$skipPref = array('alert_timeout');

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}user_alerts_prefs
		SET alert_value - 2
		WHERE id_member = {int:member}
			AND alert_pref NOT IN ({array_string:skipPref})
			AND (2 & alert_value) = 2',
		array(
			'member' => $memID,
			'skipPref' => $skipPref,
		)
	);
}

?>