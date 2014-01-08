<?php

/**
 * This file contains one humble function, which applauds or smites a user.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Modify a user's karma.
 * It redirects back to the referrer afterward, whether by javascript or the passed parameters.
 * Requires the karma_edit permission, and that the user isn't a guest.
 * It depends on the karmaMode, karmaWaitTime, and karmaTimeRestrictAdmins settings.
 * It is accessed via ?action=modifykarma.
 */
function ModifyKarma()
{
	global $modSettings, $txt, $user_info, $topic, $smcFunc, $context;

	// If the mod is disabled, show an error.
	if (empty($modSettings['karmaMode']))
		fatal_lang_error('feature_disabled', true);

	// If you're a guest or can't do this, blow you off...
	is_not_guest();
	isAllowedTo('karma_edit');

	checkSession('get');

	// If you don't have enough posts, tough luck.
	// @todo Should this be dropped in favor of post group permissions?
	// Should this apply to the member you are smiting/applauding?
	if (!$user_info['is_admin'] && $user_info['posts'] < $modSettings['karmaMinPosts'])
		fatal_lang_error('not_enough_posts_karma', true, array($modSettings['karmaMinPosts']));

	// And you can't modify your own, punk! (use the profile if you need to.)
	if (empty($_REQUEST['uid']) || (int) $_REQUEST['uid'] == $user_info['id'])
		fatal_lang_error('cant_change_own_karma', false);

	// The user ID _must_ be a number, no matter what.
	$_REQUEST['uid'] = (int) $_REQUEST['uid'];

	// Applauding or smiting?
	$dir = $_REQUEST['sa'] != 'applaud' ? -1 : 1;

	// Delete any older items from the log. (karmaWaitTime is by hour.)
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_karma
		WHERE {int:current_time} - log_time > {int:wait_time}',
		array(
			'wait_time' => (int) ($modSettings['karmaWaitTime'] * 3600),
			'current_time' => time(),
		)
	);

	// Start off with no change in karma.
	$action = 0;

	// Not an administrator... or one who is restricted as well.
	if (!empty($modSettings['karmaTimeRestrictAdmins']) || !allowedTo('moderate_forum'))
	{
		// Find out if this user has done this recently...
		$request = $smcFunc['db_query']('', '
			SELECT action
			FROM {db_prefix}log_karma
			WHERE id_target = {int:id_target}
				AND id_executor = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_target' => $_REQUEST['uid'],
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
			list ($action) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// They haven't, not before now, anyhow.
	if (empty($action) || empty($modSettings['karmaWaitTime']))
	{
		// Put it in the log.
		$smcFunc['db_insert']('replace',
				'{db_prefix}log_karma',
				array('action' => 'int', 'id_target' => 'int', 'id_executor' => 'int', 'log_time' => 'int'),
				array($dir, $_REQUEST['uid'], $user_info['id'], time()),
				array('id_target', 'id_executor')
			);

		// Change by one.
		updateMemberData($_REQUEST['uid'], array($dir == 1 ? 'karma_good' : 'karma_bad' => '+'));
	}
	else
	{
		// If you are gonna try to repeat.... don't allow it.
		if ($action == $dir)
			fatal_lang_error('karma_wait_time', false, array($modSettings['karmaWaitTime'], ($modSettings['karmaWaitTime'] == 1 ? strtolower($txt['hour']) : $txt['hours'])));

		// You decided to go back on your previous choice?
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_karma
			SET action = {int:action}, log_time = {int:current_time}
			WHERE id_target = {int:id_target}
				AND id_executor = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'action' => $dir,
				'current_time' => time(),
				'id_target' => $_REQUEST['uid'],
			)
		);

		// It was recently changed the OTHER way... so... reverse it!
		if ($dir == 1)
			updateMemberData($_REQUEST['uid'], array('karma_good' => '+', 'karma_bad' => '-'));
		else
			updateMemberData($_REQUEST['uid'], array('karma_bad' => '+', 'karma_good' => '-'));
	}

	// Figure out where to go back to.... the topic?
	if (!empty($topic))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . '#msg' . (int) $_REQUEST['m']);
	// Hrm... maybe a personal message?
	elseif (isset($_REQUEST['f']))
		redirectexit('action=pm;f=' . $_REQUEST['f'] . ';start=' . $_REQUEST['start'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . (isset($_REQUEST['pm']) ? '#' . (int) $_REQUEST['pm'] : ''));
	// JavaScript as a last resort.
	else
	{
		echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>...</title>
		<script type="text/javascript"><!-- // --><![CDATA[
			history.go(-1);
		// ]]></script>
	</head>
	<body>&laquo;</body>
</html>';

		obExit(false);
	}
}

?>