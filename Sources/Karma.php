<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file contains one humble function, which applauds or smites a user.

	void ModifyKarma()
		- gives or takes karma from a user.
		- redirects back to the referrer afterward, whether by javascript or
		  the passed parameters.
		- requires the karma_edit permission, and that the user isn't a guest.
		- depends on the karmaMode, karmaWaitTime, and karmaTimeRestrictAdmins
		  settings.
		- is accessed via ?action=modifykarma.
*/

// Modify a user's karma.
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
	// !!! Should this be dropped in favor of post group permissions?  Should this apply to the member you are smiting/applauding?
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
			fatal_lang_error('karma_wait_time', false, array($modSettings['karmaWaitTime'], $txt['hours']));

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
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
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

// What's this?  I dunno, what are you talking about?  Never seen this before, nope.  No siree.
function BookOfUnknown()
{
	global $context;

	if (strpos($_GET['action'], 'mozilla') !== false && !$context['browser']['is_gecko'])
		redirectexit('http://www.getfirefox.com/');
	elseif (strpos($_GET['action'], 'mozilla') !== false)
		redirectexit('about:mozilla');

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>The Book of Unknown, ', @$_GET['verse'] == '2:18' ? '2:18' : '4:16', '</title>
		<style type="text/css">
			em
			{
				font-size: 1.3em;
				line-height: 0;
			}
		</style>
	</head>
	<body style="background-color: #444455; color: white; font-style: italic; font-family: serif;">
		<div style="margin-top: 12%; font-size: 1.1em; line-height: 1.4; text-align: center;">';
	if (@$_GET['verse'] == '2:18')
		echo '
			Woe, it was that his name wasn\'t <em>known</em>, that he came in mystery, and was recognized by none.&nbsp;And it became to be in those days <em>something</em>.&nbsp; Something not yet <em id="unknown" name="[Unknown]">unknown</em> to mankind.&nbsp; And thus what was to be known the <em>secret project</em> began into its existence.&nbsp; Henceforth the opposition was only <em>weary</em> and <em>fearful</em>, for now their match was at arms against them.';
	else
		echo '
			And it came to pass that the <em>unbelievers</em> dwindled in number and saw rise of many <em>proselytizers</em>, and the opposition found fear in the face of the <em>x</em> and the <em>j</em> while those who stood with the <em>something</em> grew stronger and came together.&nbsp; Still, this was only the <em>beginning</em>, and what lay in the future was <em id="unknown" name="[Unknown]">unknown</em> to all, even those on the right side.';
	echo '
		</div>
		<div style="margin-top: 2ex; font-size: 2em; text-align: right;">';
	if (@$_GET['verse'] == '2:18')
		echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="http://www.unknownbrackets.com/about:unknown" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 2:18</span>';
	else
		echo '
			from <span style="font-family: Georgia, serif;"><strong><a href="http://www.unknownbrackets.com/about:unknown" style="color: white; text-decoration: none; cursor: text;">The Book of Unknown</a></strong>, 4:16</span>';
	echo '
		</div>
	</body>
</html>';

	obExit(false);
}

?>