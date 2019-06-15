<?php

/**
 * This file contains some useful functions for members and membergroups.
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
 * Delete one or more members.
 * Requires profile_remove_own or profile_remove_any permission for
 * respectively removing your own account or any account.
 * Non-admins cannot delete admins.
 * The function:
 *   - changes author of messages, topics and polls to guest authors.
 *   - removes all log entries concerning the deleted members, except the
 * error logs, ban logs and moderation logs.
 *   - removes these members' personal messages (only the inbox), avatars,
 * ban entries, theme settings, moderator positions, poll and votes.
 *   - updates member statistics afterwards.
 *
 * @param int|array $users The ID of a user or an array of user IDs
 * @param bool $check_not_admin Whether to verify that the users aren't admins
 */
function deleteMembers($users, $check_not_admin = false)
{
	global $sourcedir, $modSettings, $user_info, $smcFunc, $cache_enable;

	// Try give us a while to sort this out...
	@set_time_limit(600);

	// Try to get some more memory.
	setMemoryLimit('128M');

	// If it's not an array, make it so!
	if (!is_array($users))
		$users = array($users);
	else
		$users = array_unique($users);

	// Make sure there's no void user in here.
	$users = array_diff($users, array(0));

	// How many are they deleting?
	if (empty($users))
		return;
	elseif (count($users) == 1)
	{
		list ($user) = $users;

		if ($user == $user_info['id'])
			isAllowedTo('profile_remove_own');
		else
			isAllowedTo('profile_remove_any');
	}
	else
	{
		foreach ($users as $k => $v)
			$users[$k] = (int) $v;

		// Deleting more than one?  You can't have more than one account...
		isAllowedTo('profile_remove_any');
	}

	// Get their names for logging purposes.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:user_list})
		LIMIT {int:limit}',
		array(
			'user_list' => $users,
			'admin_group' => 1,
			'limit' => count($users),
		)
	);
	$admins = array();
	$user_log_details = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['is_admin'])
			$admins[] = $row['id_member'];
		$user_log_details[$row['id_member']] = array($row['id_member'], $row['member_name']);
	}
	$smcFunc['db_free_result']($request);

	if (empty($user_log_details))
		return;

	// Make sure they aren't trying to delete administrators if they aren't one.  But don't bother checking if it's just themself.
	if (!empty($admins) && ($check_not_admin || (!allowedTo('admin_forum') && (count($users) != 1 || $users[0] != $user_info['id']))))
	{
		$users = array_diff($users, $admins);
		foreach ($admins as $id)
			unset($user_log_details[$id]);
	}

	// No one left?
	if (empty($users))
		return;

	// Log the action - regardless of who is deleting it.
	$log_changes = array();
	foreach ($user_log_details as $user)
	{
		$log_changes[] = array(
			'action' => 'delete_member',
			'log_type' => 'admin',
			'extra' => array(
				'member' => $user[0],
				'name' => $user[1],
				'member_acted' => $user_info['name'],
			),
		);

		// Remove any cached data if enabled.
		if (!empty($cache_enable) && $cache_enable >= 2)
			cache_put_data('user_settings-' . $user[0], null, 60);
	}

	// Make these peoples' posts guest posts.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:guest_id}' . (!empty($modSettings['deleteMembersRemovesEmail']) ? ',
			poster_email = {string:blank_email}' : '') . '
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'blank_email' => '',
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}polls
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Make these peoples' posts guest first posts and last posts.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_started = {int:guest_id}
		WHERE id_member_started IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_updated = {int:guest_id}
		WHERE id_member_updated IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_actions
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_banned
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_errors
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Delete the member.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}members
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete any drafts...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}user_drafts
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete anything they liked.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}user_likes
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete their mentions
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}mentions
		WHERE id_member IN ({array_int:members})',
		array(
			'members' => $users,
		)
	);

	// Delete the logs...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member IN ({array_int:users})',
		array(
			'log_type' => 2,
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_boards
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_comments
		WHERE id_recipient IN ({array_int:users})
			AND comment_type = {string:warntpl}',
		array(
			'users' => $users,
			'warntpl' => 'warntpl',
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_mark_read
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_subscribed
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Make their votes appear as guest votes - at least it keeps the totals right.
	// @todo Consider adding back in cookie protection.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_polls
		SET id_member = {int:guest_id}
		WHERE id_member IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// Delete personal messages.
	require_once($sourcedir . '/PersonalMessage.php');
	deleteMessages(null, null, $users);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}personal_messages
		SET id_member_from = {int:guest_id}
		WHERE id_member_from IN ({array_int:users})',
		array(
			'guest_id' => 0,
			'users' => $users,
		)
	);

	// They no longer exist, so we don't know who it was sent to.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}pm_recipients
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Delete avatar.
	require_once($sourcedir . '/ManageAttachments.php');
	removeAttachments(array('id_member' => $users));

	// It's over, no more moderation for you.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}moderators
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// If you don't exist we can't ban you.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ban_items
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// Remove individual theme settings.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_member IN ({array_int:users})',
		array(
			'users' => $users,
		)
	);

	// These users are nobody's buddy nomore.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, pm_ignore_list, buddy_list
		FROM {db_prefix}members
		WHERE FIND_IN_SET({raw:pm_ignore_list}, pm_ignore_list) != 0 OR FIND_IN_SET({raw:buddy_list}, buddy_list) != 0',
		array(
			'pm_ignore_list' => implode(', pm_ignore_list) != 0 OR FIND_IN_SET(', $users),
			'buddy_list' => implode(', buddy_list) != 0 OR FIND_IN_SET(', $users),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				pm_ignore_list = {string:pm_ignore_list},
				buddy_list = {string:buddy_list}
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $row['id_member'],
				'pm_ignore_list' => implode(',', array_diff(explode(',', $row['pm_ignore_list']), $users)),
				'buddy_list' => implode(',', array_diff(explode(',', $row['buddy_list']), $users)),
			)
		);
	$smcFunc['db_free_result']($request);

	// Make sure no member's birthday is still sticking in the calendar...
	updateSettings(array(
		'calendar_updated' => time(),
	));

	// Integration rocks!
	call_integration_hook('integrate_delete_members', array($users));

	updateStats('member');

	require_once($sourcedir . '/Logging.php');
	logActions($log_changes);
}

/**
 * Registers a member to the forum.
 * Allows two types of interface: 'guest' and 'admin'. The first
 * includes hammering protection, the latter can perform the
 * registration silently.
 * The strings used in the options array are assumed to be escaped.
 * Allows to perform several checks on the input, e.g. reserved names.
 * The function will adjust member statistics.
 * If an error is detected will fatal error on all errors unless return_errors is true.
 *
 * @param array $regOptions An array of registration options
 * @param bool $return_errors Whether to return the errors
 * @return int|array The ID of the newly registered user or an array of error info if $return_errors is true
 */
function registerMember(&$regOptions, $return_errors = false)
{
	global $scripturl, $txt, $modSettings, $context, $sourcedir;
	global $user_info, $smcFunc;

	loadLanguage('Login');

	// We'll need some external functions.
	require_once($sourcedir . '/Subs-Auth.php');
	require_once($sourcedir . '/Subs-Post.php');

	// Put any errors in here.
	$reg_errors = array();

	// Registration from the admin center, let them sweat a little more.
	if ($regOptions['interface'] == 'admin')
	{
		is_not_guest();
		isAllowedTo('moderate_forum');
	}
	// If you're an admin, you're special ;).
	elseif ($regOptions['interface'] == 'guest')
	{
		// You cannot register twice...
		if (empty($user_info['is_guest']))
			redirectexit();

		// Make sure they didn't just register with this session.
		if (!empty($_SESSION['just_registered']) && empty($modSettings['disableRegisterCheck']))
			fatal_lang_error('register_only_once', false);
	}

	// Spaces and other odd characters are evil...
	$regOptions['username'] = trim(preg_replace('~[\t\n\r \x0B\0' . ($context['utf8'] ? '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : '\x00-\x08\x0B\x0C\x0E-\x19\xA0') . ']+~' . ($context['utf8'] ? 'u' : ''), ' ', $regOptions['username']));

	// Convert character encoding for non-utf8mb4 database
	$regOptions['username'] = $smcFunc['htmlspecialchars']($regOptions['username']);

	// @todo Separate the sprintf?
	if (empty($regOptions['email']) || !filter_var($regOptions['email'], FILTER_VALIDATE_EMAIL) || strlen($regOptions['email']) > 255)
		$reg_errors[] = array('lang', 'profile_error_bad_email');

	$username_validation_errors = validateUsername(0, $regOptions['username'], true, !empty($regOptions['check_reserved_name']));
	if (!empty($username_validation_errors))
		$reg_errors = array_merge($reg_errors, $username_validation_errors);

	// Generate a validation code if it's supposed to be emailed.
	$validation_code = '';
	if ($regOptions['require'] == 'activation')
		$validation_code = generateValidationCode();

	// If you haven't put in a password generate one.
	if ($regOptions['interface'] == 'admin' && $regOptions['password'] == '')
	{
		mt_srand(time() + 1277);
		$regOptions['password'] = generateValidationCode();
		$regOptions['password_check'] = $regOptions['password'];
	}
	// Does the first password match the second?
	elseif ($regOptions['password'] != $regOptions['password_check'])
		$reg_errors[] = array('lang', 'passwords_dont_match');

	// That's kind of easy to guess...
	if ($regOptions['password'] == '')
	{
		$reg_errors[] = array('lang', 'no_password');
	}

	// Now perform hard password validation as required.
	if (!empty($regOptions['check_password_strength']) && $regOptions['password'] != '')
	{
		$passwordError = validatePassword($regOptions['password'], $regOptions['username'], array($regOptions['email']));

		// Password isn't legal?
		if ($passwordError != null)
			$reg_errors[] = array('lang', 'profile_error_password_' . $passwordError);
	}

	// You may not be allowed to register this email.
	if (!empty($regOptions['check_email_ban']))
		isBannedEmail($regOptions['email'], 'cannot_register', $txt['ban_register_prohibited']);

	// Check if the email address is in use.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE email_address = {string:email_address}
			OR email_address = {string:username}
		LIMIT 1',
		array(
			'email_address' => $regOptions['email'],
			'username' => $regOptions['username'],
		)
	);
	// @todo Separate the sprintf?
	if ($smcFunc['db_num_rows']($request) != 0)
		$reg_errors[] = array('lang', 'email_in_use', false, array($smcFunc['htmlspecialchars']($regOptions['email'])));

	$smcFunc['db_free_result']($request);

	// Perhaps someone else wants to check this user.
	call_integration_hook('integrate_register_check', array(&$regOptions, &$reg_errors));

	// If we found any errors we need to do something about it right away!
	foreach ($reg_errors as $key => $error)
	{
		/* Note for each error:
			0 = 'lang' if it's an index, 'done' if it's clear text.
			1 = The text/index.
			2 = Whether to log.
			3 = sprintf data if necessary. */
		if ($error[0] == 'lang')
			loadLanguage('Errors');
		$message = $error[0] == 'lang' ? (empty($error[3]) ? $txt[$error[1]] : vsprintf($txt[$error[1]], $error[3])) : $error[1];

		// What to do, what to do, what to do.
		if ($return_errors)
		{
			if (!empty($error[2]))
				log_error($message, $error[2]);
			$reg_errors[$key] = $message;
		}
		else
			fatal_error($message, empty($error[2]) ? false : $error[2]);
	}

	// If there's any errors left return them at once!
	if (!empty($reg_errors))
		return $reg_errors;

	$reservedVars = array(
		'actual_theme_url',
		'actual_images_url',
		'base_theme_dir',
		'base_theme_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'number_recent_posts',
		'smiley_sets_default',
		'theme_dir',
		'theme_id',
		'theme_layers',
		'theme_templates',
		'theme_url',
	);

	// Can't change reserved vars.
	if (isset($regOptions['theme_vars']) && count(array_intersect(array_keys($regOptions['theme_vars']), $reservedVars)) != 0)
		fatal_lang_error('no_theme');

	// Some of these might be overwritten. (the lower ones that are in the arrays below.)
	$regOptions['register_vars'] = array(
		'member_name' => $regOptions['username'],
		'email_address' => $regOptions['email'],
		'passwd' => hash_password($regOptions['username'], $regOptions['password']),
		'password_salt' => substr(md5($smcFunc['random_int']()), 0, 4),
		'posts' => 0,
		'date_registered' => time(),
		'member_ip' => $regOptions['interface'] == 'admin' ? '127.0.0.1' : $user_info['ip'],
		'member_ip2' => $regOptions['interface'] == 'admin' ? '127.0.0.1' : $_SERVER['BAN_CHECK_IP'],
		'validation_code' => $validation_code,
		'real_name' => $regOptions['username'],
		'personal_text' => $modSettings['default_personal_text'],
		'id_theme' => 0,
		'id_post_group' => 4,
		'lngfile' => '',
		'buddy_list' => '',
		'pm_ignore_list' => '',
		'website_title' => '',
		'website_url' => '',
		'time_format' => '',
		'signature' => '',
		'avatar' => '',
		'usertitle' => '',
		'secret_question' => '',
		'secret_answer' => '',
		'additional_groups' => '',
		'ignore_boards' => '',
		'smiley_set' => '',
		'timezone' => !empty($regOptions['timezone']) ? $regOptions['timezone'] : 'UTC',
	);

	// Setup the activation status on this new account so it is correct - firstly is it an under age account?
	if ($regOptions['require'] == 'coppa')
	{
		$regOptions['register_vars']['is_activated'] = 5;
		// @todo This should be changed.  To what should be it be changed??
		$regOptions['register_vars']['validation_code'] = '';
	}
	// Maybe it can be activated right away?
	elseif ($regOptions['require'] == 'nothing')
		$regOptions['register_vars']['is_activated'] = 1;
	// Maybe it must be activated by email?
	elseif ($regOptions['require'] == 'activation')
		$regOptions['register_vars']['is_activated'] = 0;
	// Otherwise it must be awaiting approval!
	else
		$regOptions['register_vars']['is_activated'] = 3;

	if (isset($regOptions['memberGroup']))
	{
		// Make sure the id_group will be valid, if this is an administrator.
		$regOptions['register_vars']['id_group'] = $regOptions['memberGroup'] == 1 && !allowedTo('admin_forum') ? 0 : $regOptions['memberGroup'];

		// Check if this group is assignable.
		$unassignableGroups = array(-1, 3);
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE min_posts != {int:min_posts}' . (allowedTo('admin_forum') ? '' : '
				OR group_type = {int:is_protected}'),
			array(
				'min_posts' => -1,
				'is_protected' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$unassignableGroups[] = $row['id_group'];
		$smcFunc['db_free_result']($request);

		if (in_array($regOptions['register_vars']['id_group'], $unassignableGroups))
			$regOptions['register_vars']['id_group'] = 0;
	}

	// Integrate optional member settings to be set.
	if (!empty($regOptions['extra_register_vars']))
		foreach ($regOptions['extra_register_vars'] as $var => $value)
			$regOptions['register_vars'][$var] = $value;

	// Integrate optional user theme options to be set.
	$theme_vars = array();
	if (!empty($regOptions['theme_vars']))
		foreach ($regOptions['theme_vars'] as $var => $value)
			$theme_vars[$var] = $value;

	// Right, now let's prepare for insertion.
	$knownInts = array(
		'date_registered', 'posts', 'id_group', 'last_login', 'instant_messages', 'unread_messages',
		'new_pm', 'pm_prefs', 'show_online',
		'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning',
	);
	$knownFloats = array(
		'time_offset',
	);
	$knownInets = array(
		'member_ip', 'member_ip2',
	);

	// Call an optional function to validate the users' input.
	call_integration_hook('integrate_register', array(&$regOptions, &$theme_vars, &$knownInts, &$knownFloats));

	$column_names = array();
	$values = array();
	foreach ($regOptions['register_vars'] as $var => $val)
	{
		$type = 'string';
		if (in_array($var, $knownInts))
			$type = 'int';
		elseif (in_array($var, $knownFloats))
			$type = 'float';
		elseif (in_array($var, $knownInets))
			$type = 'inet';
		elseif ($var == 'birthdate')
			$type = 'date';

		$column_names[$var] = $type;
		$values[$var] = $val;
	}

	// Register them into the database.
	$memberID = $smcFunc['db_insert']('',
		'{db_prefix}members',
		$column_names,
		$values,
		array('id_member'),
		1
	);

	// Call an optional function as notification of registration.
	call_integration_hook('integrate_post_register', array(&$regOptions, &$theme_vars, &$memberID));

	// Update the number of members and latest member's info - and pass the name, but remove the 's.
	if ($regOptions['register_vars']['is_activated'] == 1)
		updateStats('member', $memberID, $regOptions['register_vars']['real_name']);
	else
		updateStats('member');

	// Theme variables too?
	if (!empty($theme_vars))
	{
		$inserts = array();
		foreach ($theme_vars as $var => $val)
			$inserts[] = array($memberID, $var, $val);
		$smcFunc['db_insert']('insert',
			'{db_prefix}themes',
			array('id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			$inserts,
			array('id_member', 'variable')
		);
	}

	// If it's enabled, increase the registrations for today.
	trackStats(array('registers' => '+'));

	// Administrative registrations are a bit different...
	if ($regOptions['interface'] == 'admin')
	{
		if ($regOptions['require'] == 'activation')
			$email_message = 'admin_register_activate';
		elseif (!empty($regOptions['send_welcome_email']))
			$email_message = 'admin_register_immediate';

		if (isset($email_message))
		{
			$replacements = array(
				'REALNAME' => $regOptions['register_vars']['real_name'],
				'USERNAME' => $regOptions['username'],
				'PASSWORD' => $regOptions['password'],
				'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
				'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $memberID . ';code=' . $validation_code,
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $memberID,
				'ACTIVATIONCODE' => $validation_code,
			);

			$emaildata = loadEmailTemplate($email_message, $replacements);

			sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, $email_message . $memberID, $emaildata['is_html'], 0);
		}

		// All admins are finished here.
		return $memberID;
	}

	// Can post straight away - welcome them to your fantastic community...
	if ($regOptions['require'] == 'nothing')
	{
		if (!empty($regOptions['send_welcome_email']))
		{
			$replacements = array(
				'REALNAME' => $regOptions['register_vars']['real_name'],
				'USERNAME' => $regOptions['username'],
				'PASSWORD' => $regOptions['password'],
				'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
			);
			$emaildata = loadEmailTemplate('register_immediate', $replacements);
			sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, 'register', $emaildata['is_html'], 0);
		}

		// Send admin their notification.
		adminNotify('standard', $memberID, $regOptions['username']);
	}
	// Need to activate their account - or fall under COPPA.
	elseif ($regOptions['require'] == 'activation' || $regOptions['require'] == 'coppa')
	{
		$replacements = array(
			'REALNAME' => $regOptions['register_vars']['real_name'],
			'USERNAME' => $regOptions['username'],
			'PASSWORD' => $regOptions['password'],
			'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
		);

		if ($regOptions['require'] == 'activation')
			$replacements += array(
				'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $memberID . ';code=' . $validation_code,
				'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $memberID,
				'ACTIVATIONCODE' => $validation_code,
			);
		else
			$replacements += array(
				'COPPALINK' => $scripturl . '?action=coppa;u=' . $memberID,
			);

		$emaildata = loadEmailTemplate('register_' . ($regOptions['require'] == 'activation' ? 'activate' : 'coppa'), $replacements);

		sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, 'reg_' . $regOptions['require'] . $memberID, $emaildata['is_html'], 0);
	}
	// Must be awaiting approval.
	else
	{
		$replacements = array(
			'REALNAME' => $regOptions['register_vars']['real_name'],
			'USERNAME' => $regOptions['username'],
			'PASSWORD' => $regOptions['password'],
			'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
		);

		$emaildata = loadEmailTemplate('register_pending', $replacements);

		sendmail($regOptions['email'], $emaildata['subject'], $emaildata['body'], null, 'reg_pending', $emaildata['is_html'], 0);

		// Admin gets informed here...
		adminNotify('approval', $memberID, $regOptions['username']);
	}

	// Okay, they're for sure registered... make sure the session is aware of this for security. (Just married :P!)
	$_SESSION['just_registered'] = 1;

	// If they are for sure registered, let other people to know about it
	call_integration_hook('integrate_register_after', array($regOptions, $memberID));

	return $memberID;
}

/**
 * Check if a name is in the reserved words list.
 * (name, current member id, name/username?.)
 * - checks if name is a reserved name or username.
 * - if is_name is false, the name is assumed to be a username.
 * - the id_member variable is used to ignore duplicate matches with the
 * current member.
 *
 * @param string $name The name to check
 * @param int $current_ID_MEMBER The ID of the current member (to avoid false positives with the current member)
 * @param bool $is_name Whether we're checking against reserved names or just usernames
 * @param bool $fatal Whether to die with a fatal error if the name is reserved
 * @return bool|void False if name is not reserved, otherwise true if $fatal is false or dies with a fatal_lang_error if $fatal is true
 */
function isReservedName($name, $current_ID_MEMBER = 0, $is_name = true, $fatal = true)
{
	global $modSettings, $smcFunc;

	$name = preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'replaceEntities__callback', $name);
	$checkName = $smcFunc['strtolower']($name);

	// Administrators are never restricted ;).
	if (!allowedTo('moderate_forum') && ((!empty($modSettings['reserveName']) && $is_name) || !empty($modSettings['reserveUser']) && !$is_name))
	{
		$reservedNames = explode("\n", $modSettings['reserveNames']);
		// Case sensitive check?
		$checkMe = empty($modSettings['reserveCase']) ? $checkName : $name;

		// Check each name in the list...
		foreach ($reservedNames as $reserved)
		{
			if ($reserved == '')
				continue;

			// The admin might've used entities too, level the playing field.
			$reservedCheck = preg_replace('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'replaceEntities__callback', $reserved);

			// Case sensitive name?
			if (empty($modSettings['reserveCase']))
				$reservedCheck = $smcFunc['strtolower']($reservedCheck);

			// If it's not just entire word, check for it in there somewhere...
			if ($checkMe == $reservedCheck || ($smcFunc['strpos']($checkMe, $reservedCheck) !== false && empty($modSettings['reserveWord'])))
				if ($fatal)
					fatal_lang_error('username_reserved', 'password', array($reserved));
				else
					return true;
		}

		$censor_name = $name;
		if (censorText($censor_name) != $name)
			if ($fatal)
				fatal_lang_error('name_censored', 'password', array($name));
			else
				return true;
	}

	// Characters we just shouldn't allow, regardless.
	foreach (array('*') as $char)
		if (strpos($checkName, $char) !== false)
			if ($fatal)
				fatal_lang_error('username_reserved', 'password', array($char));
			else
				return true;

	// Get rid of any SQL parts of the reserved name...
	$checkName = strtr($name, array('_' => '\\_', '%' => '\\%'));

	//when we got no wildcard we can use equal -> fast
	$operator = (strpos($checkName, '%') || strpos($checkName, '_') ? 'LIKE' : '=');

	// Make sure they don't want someone else's name.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . (empty($current_ID_MEMBER) ? '' : 'id_member != {int:current_member}
			AND ') . '({raw:real_name} {raw:operator} LOWER({string:check_name}) OR {raw:member_name} {raw:operator} LOWER({string:check_name}))
		LIMIT 1',
		array(
			'real_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(real_name)' : 'real_name',
			'member_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(member_name)' : 'member_name',
			'current_member' => $current_ID_MEMBER,
			'check_name' => $checkName,
			'operator' => $operator,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$smcFunc['db_free_result']($request);
		return true;
	}

	// Does name case insensitive match a member group name?
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}membergroups
		WHERE {raw:group_name} LIKE {string:check_name}
		LIMIT 1',
		array(
			'group_name' => $smcFunc['db_case_sensitive'] ? 'LOWER(group_name)' : 'group_name',
			'check_name' => $checkName,
		)
	);
	if ($smcFunc['db_num_rows']($request) > 0)
	{
		$smcFunc['db_free_result']($request);
		return true;
	}

	// Okay, they passed.
	$is_reserved = false;

	// Maybe a mod wants to perform further checks?
	call_integration_hook('integrate_check_name', array($checkName, &$is_reserved, $current_ID_MEMBER, $is_name));

	return $is_reserved;
}

// Get a list of groups that have a given permission (on a given board).
/**
 * Retrieves a list of membergroups that are allowed to do the given
 * permission. (on the given board)
 * If board_id is not null, a board permission is assumed.
 * The function takes different permission settings into account.
 *
 * @param string $permission The permission to check
 * @param int $board_id = null If set, checks permissions for the specified board
 * @return array An array containing two arrays - 'allowed', which has which groups are allowed to do it and 'denied' which has the groups that are denied
 */
function groupsAllowedTo($permission, $board_id = null)
{
	global $board_info, $smcFunc;

	// Admins are allowed to do anything.
	$member_groups = array(
		'allowed' => array(1),
		'denied' => array(),
	);

	// Assume we're dealing with regular permissions (like profile_view).
	if ($board_id === null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group, add_deny
			FROM {db_prefix}permissions
			WHERE permission = {string:permission}',
			array(
				'permission' => $permission,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		$smcFunc['db_free_result']($request);
	}

	// Otherwise it's time to look at the board.
	else
	{
		// First get the profile of the given board.
		if (isset($board_info['id']) && $board_info['id'] == $board_id)
			$profile_id = $board_info['profile'];
		elseif ($board_id !== 0)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_profile
				FROM {db_prefix}boards
				WHERE id_board = {int:id_board}
				LIMIT 1',
				array(
					'id_board' => $board_id,
				)
			);
			if ($smcFunc['db_num_rows']($request) == 0)
				fatal_lang_error('no_board');
			list ($profile_id) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
			$profile_id = 1;

		$request = $smcFunc['db_query']('', '
			SELECT bp.id_group, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
			WHERE bp.permission = {string:permission}
				AND bp.id_profile = {int:profile_id}',
			array(
				'profile_id' => $profile_id,
				'permission' => $permission,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
		$smcFunc['db_free_result']($request);

		$moderator_groups = array();

		// "Inherit" any moderator permissions as needed
		if (isset($board_info['moderator_groups']))
		{
			$moderator_groups = array_keys($board_info['moderator_groups']);
		}
		elseif ($board_id !== 0)
		{
			// Get the groups that can moderate this board
			$request = $smcFunc['db_query']('', '
				SELECT id_group
				FROM {db_prefix}moderator_groups
				WHERE id_board = {int:board_id}',
				array(
					'board_id' => $board_id,
				)
			);

			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$moderator_groups[] = $row['id_group'];
			}

			$smcFunc['db_free_result']($request);
		}

		// "Inherit" any additional permissions from the "Moderators" group
		foreach ($moderator_groups as $mod_group)
		{
			// If they're not specifically allowed, but the moderator group is, then allow it
			if (in_array(3, $member_groups['allowed']) && !in_array($mod_group, $member_groups['allowed']))
			{
				$member_groups['allowed'][] = $mod_group;
			}

			// They're not denied, but the moderator group is, so deny it
			if (in_array(3, $member_groups['denied']) && !in_array($mod_group, $member_groups['denied']))
			{
				$member_groups['denied'][] = $mod_group;
			}
		}
	}

	// Denied is never allowed.
	$member_groups['allowed'] = array_diff($member_groups['allowed'], $member_groups['denied']);

	return $member_groups;
}

/**
 * Retrieves a list of members that have a given permission
 * (on a given board).
 * If board_id is not null, a board permission is assumed.
 * Takes different permission settings into account.
 * Takes possible moderators (on board 'board_id') into account.
 *
 * @param string $permission The permission to check
 * @param int $board_id If set, checks permission for that specific board
 * @return array An array containing the IDs of the members having that permission
 */
function membersAllowedTo($permission, $board_id = null)
{
	global $smcFunc;

	$member_groups = groupsAllowedTo($permission, $board_id);

	$all_groups = array_merge($member_groups['allowed'], $member_groups['denied']);

	$include_moderators = in_array(3, $member_groups['allowed']) && $board_id !== null;
	$member_groups['allowed'] = array_diff($member_groups['allowed'], array(3));

	$exclude_moderators = in_array(3, $member_groups['denied']) && $board_id !== null;
	$member_groups['denied'] = array_diff($member_groups['denied'], array(3));

	$request = $smcFunc['db_query']('', '
		SELECT mem.id_member
		FROM {db_prefix}members AS mem' . ($include_moderators || $exclude_moderators ? '
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member AND mods.id_board = {int:board_id})
			LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_group IN ({array_int:all_member_groups}) AND modgs.id_board = {int:board_id})' : '') . '
		WHERE (' . ($include_moderators ? 'mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_allowed}) OR FIND_IN_SET({raw:member_group_allowed_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_allowed}))' . (empty($member_groups['denied']) ? '' : '
			AND NOT (' . ($exclude_moderators ? 'mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR ' : '') . 'mem.id_group IN ({array_int:member_groups_denied}) OR FIND_IN_SET({raw:member_group_denied_implode}, mem.additional_groups) != 0 OR mem.id_post_group IN ({array_int:member_groups_denied}))'),
		array(
			'member_groups_allowed' => $member_groups['allowed'],
			'member_groups_denied' => $member_groups['denied'],
			'all_member_groups' => $all_groups,
			'board_id' => $board_id,
			'member_group_allowed_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['allowed']),
			'member_group_denied_implode' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $member_groups['denied']),
		)
	);
	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$members[] = $row['id_member'];
	$smcFunc['db_free_result']($request);

	return $members;
}

/**
 * This function is used to reassociate members with relevant posts.
 * Reattribute guest posts to a specified member.
 * Does not check for any permissions.
 * If add_to_post_count is set, the member's post count is increased.
 *
 * @param int $memID The ID of the original poster
 * @param bool|string $email If set, should be the email of the poster
 * @param bool|string $membername If set, the membername of the poster
 * @param bool $post_count Whether to adjust post counts
 * @return array An array containing the number of messages, topics and reports updated
 */
function reattributePosts($memID, $email = false, $membername = false, $post_count = false)
{
	global $smcFunc, $modSettings;

	$updated = array(
		'messages' => 0,
		'topics' => 0,
		'reports' => 0,
	);

	// Firstly, if email and username aren't passed find out the members email address and name.
	if ($email === false && $membername === false)
	{
		$request = $smcFunc['db_query']('', '
			SELECT email_address, member_name
			FROM {db_prefix}members
			WHERE id_member = {int:memID}
			LIMIT 1',
			array(
				'memID' => $memID,
			)
		);
		list ($email, $membername) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// If they want the post count restored then we need to do some research.
	if ($post_count)
	{
		$recycle_board = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? (int) $modSettings['recycle_board'] : 0;
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND b.count_posts = {int:count_posts})
			WHERE m.id_member = {int:guest_id}
				AND m.approved = {int:is_approved}' . (!empty($recycle_board) ? '
				AND m.id_board != {int:recycled_board}' : '') . (empty($email) ? '' : '
				AND m.poster_email = {string:email_address}') . (empty($membername) ? '' : '
				AND m.poster_name = {string:member_name}'),
			array(
				'count_posts' => 0,
				'guest_id' => 0,
				'email_address' => $email,
				'member_name' => $membername,
				'is_approved' => 1,
				'recycled_board' => $recycle_board,
			)
		);
		list ($messageCount) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		updateMemberData($memID, array('posts' => 'posts + ' . $messageCount));
	}

	$query_parts = array();
	if (!empty($email))
		$query_parts[] = 'poster_email = {string:email_address}';
	if (!empty($membername))
		$query_parts[] = 'poster_name = {string:member_name}';
	$query = implode(' AND ', $query_parts);

	// Finally, update the posts themselves!
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:memID}
		WHERE ' . $query,
		array(
			'memID' => $memID,
			'email_address' => $email,
			'member_name' => $membername,
		)
	);
	$updated['messages'] = $smcFunc['db_affected_rows']();

	// Did we update any messages?
	if ($updated['messages'] > 0)
	{
		// First, check for updated topics.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics as t, {db_prefix}messages as m
			SET t.id_member_started = {int:memID}
			WHERE m.id_member = {int:memID}
				AND t.id_first_msg = m.id_msg',
			array(
				'memID' => $memID,
			)
		);
		$updated['topics'] = $smcFunc['db_affected_rows']();

		// Second, check for updated reports.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_reported AS lr, {db_prefix}messages AS m
			SET lr.id_member = {int:memID}
			WHERE lr.id_msg = m.id_msg
				AND m.id_member = {int:memID}',
			array(
				'memID' => $memID,
			)
		);
		$updated['reports'] = $smcFunc['db_affected_rows']();
	}

	// Allow mods with their own post tables to reattribute posts as well :)
	call_integration_hook('integrate_reattribute_posts', array($memID, $email, $membername, $post_count, &$updated));

	return $updated;
}

/**
 * This simple function adds/removes the passed user from the current users buddy list.
 * Requires profile_identity_own permission.
 * Called by ?action=buddy;u=x;session_id=y.
 * Redirects to ?action=profile;u=x.
 */
function BuddyListToggle()
{
	global $user_info, $smcFunc;

	checkSession('get');

	isAllowedTo('profile_extra_own');
	is_not_guest();

	$userReceiver = (int) !empty($_REQUEST['u']) ? $_REQUEST['u'] : 0;

	if (empty($userReceiver))
		fatal_lang_error('no_access', false);

	// Remove if it's already there...
	if (in_array($userReceiver, $user_info['buddies']))
		$user_info['buddies'] = array_diff($user_info['buddies'], array($userReceiver));

	// ...or add if it's not and if it's not you.
	elseif ($user_info['id'] != $userReceiver)
	{
		$user_info['buddies'][] = $userReceiver;

		// And add a nice alert. Don't abuse though!
		if ((cache_get_data('Buddy-sent-' . $user_info['id'] . '-' . $userReceiver, 86400)) == null)
		{
			$smcFunc['db_insert']('insert',
				'{db_prefix}background_tasks',
				array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/Buddy-Notify.php', 'Buddy_Notify_Background', $smcFunc['json_encode'](array(
					'receiver_id' => $userReceiver,
					'id_member' => $user_info['id'],
					'member_name' => $user_info['username'],
					'time' => time(),
				)), 0),
				array('id_task')
			);

			// Store this in a cache entry to avoid creating multiple alerts. Give it a long life cycle.
			cache_put_data('Buddy-sent-' . $user_info['id'] . '-' . $userReceiver, '1', 86400);
		}
	}

	// Update the settings.
	updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

	// Redirect back to the profile
	redirectexit('action=profile;u=' . $userReceiver);
}

/**
 * Callback for createList().
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show per page
 * @param string $sort An SQL query indicating how to sort the results
 * @param string $where An SQL query used to filter the results
 * @param array $where_params An array of parameters for $where
 * @param bool $get_duplicates Whether to get duplicates (used for the admin member list)
 * @return array An array of information for displaying the list of members
 */
function list_getMembers($start, $items_per_page, $sort, $where, $where_params = array(), $get_duplicates = false)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.member_ip, mem.member_ip2, mem.last_login,
			mem.posts, mem.is_activated, mem.date_registered, mem.id_group, mem.additional_groups, mg.group_name
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
		WHERE ' . ($where == '1' ? '1=1' : $where) . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array_merge($where_params, array(
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
		))
	);

	$members = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['member_ip'] = inet_dtop($row['member_ip']);
		$row['member_ip2'] = inet_dtop($row['member_ip2']);
		$members[] = $row;
	}
	$smcFunc['db_free_result']($request);

	// If we want duplicates pass the members array off.
	if ($get_duplicates)
		populateDuplicateMembers($members);

	return $members;
}

/**
 * Callback for createList().
 *
 * @param string $where An SQL query to filter the results
 * @param array $where_params An array of parameters for $where
 * @return int The number of members matching the given situation
 */
function list_getNumMembers($where, $where_params = array())
{
	global $smcFunc, $modSettings;

	// We know how many members there are in total.
	if (empty($where) || $where == '1=1')
		$num_members = $modSettings['totalMembers'];

	// The database knows the amount when there are extra conditions.
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}members AS mem
			WHERE ' . $where,
			array_merge($where_params, array(
			))
		);
		list ($num_members) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	return $num_members;
}

/**
 * Find potential duplicate registration members based on the same IP address
 *
 * @param array $members An array of members
 */
function populateDuplicateMembers(&$members)
{
	global $smcFunc;

	// This will hold all the ip addresses.
	$ips = array();
	foreach ($members as $key => $member)
	{
		// Create the duplicate_members element.
		$members[$key]['duplicate_members'] = array();

		// Store the IPs.
		if (!empty($member['member_ip']))
			$ips[] = $member['member_ip'];
		if (!empty($member['member_ip2']))
			$ips[] = $member['member_ip2'];
	}

	$ips = array_unique($ips);

	if (empty($ips))
		return false;

	// Fetch all members with this IP address, we'll filter out the current ones in a sec.
	$request = $smcFunc['db_query']('', '
		SELECT
			id_member, member_name, email_address, member_ip, member_ip2, is_activated
		FROM {db_prefix}members
		WHERE member_ip IN ({array_inet:ips})
			OR member_ip2 IN ({array_inet:ips})',
		array(
			'ips' => $ips,
		)
	);
	$duplicate_members = array();
	$duplicate_ids = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		//$duplicate_ids[] = $row['id_member'];
		$row['member_ip'] = inet_dtop($row['member_ip']);
		$row['member_ip2'] = inet_dtop($row['member_ip2']);

		$member_context = array(
			'id' => $row['id_member'],
			'name' => $row['member_name'],
			'email' => $row['email_address'],
			'is_banned' => $row['is_activated'] > 10,
			'ip' => $row['member_ip'],
			'ip2' => $row['member_ip2'],
		);

		if (in_array($row['member_ip'], $ips))
			$duplicate_members[$row['member_ip']][] = $member_context;
		if ($row['member_ip'] != $row['member_ip2'] && in_array($row['member_ip2'], $ips))
			$duplicate_members[$row['member_ip2']][] = $member_context;
	}
	$smcFunc['db_free_result']($request);

	// Also try to get a list of messages using these ips.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.poster_ip, mem.id_member, mem.member_name, mem.email_address, mem.is_activated
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_member != 0
			' . (!empty($duplicate_ids) ? 'AND m.id_member NOT IN ({array_int:duplicate_ids})' : '') . '
			AND m.poster_ip IN ({array_inet:ips})',
		array(
			'duplicate_ids' => $duplicate_ids,
			'ips' => $ips,
		)
	);

	$had_ips = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['poster_ip'] = inet_dtop($row['poster_ip']);

		// Don't collect lots of the same.
		if (isset($had_ips[$row['poster_ip']]) && in_array($row['id_member'], $had_ips[$row['poster_ip']]))
			continue;
		$had_ips[$row['poster_ip']][] = $row['id_member'];

		$duplicate_members[$row['poster_ip']][] = array(
			'id' => $row['id_member'],
			'name' => $row['member_name'],
			'email' => $row['email_address'],
			'is_banned' => $row['is_activated'] > 10,
			'ip' => $row['poster_ip'],
			'ip2' => $row['poster_ip'],
		);
	}
	$smcFunc['db_free_result']($request);

	// Now we have all the duplicate members, stick them with their respective member in the list.
	if (!empty($duplicate_members))
		foreach ($members as $key => $member)
		{
			if (isset($duplicate_members[$member['member_ip']]))
				$members[$key]['duplicate_members'] = $duplicate_members[$member['member_ip']];
			if ($member['member_ip'] != $member['member_ip2'] && isset($duplicate_members[$member['member_ip2']]))
				$members[$key]['duplicate_members'] = array_merge($member['duplicate_members'], $duplicate_members[$member['member_ip2']]);

			// Check we don't have lots of the same member.
			$member_track = array($member['id_member']);
			foreach ($members[$key]['duplicate_members'] as $duplicate_id_member => $duplicate_member)
			{
				if (in_array($duplicate_member['id'], $member_track))
				{
					unset($members[$key]['duplicate_members'][$duplicate_id_member]);
					continue;
				}

				$member_track[] = $duplicate_member['id'];
			}
		}
}

/**
 * Generate a random validation code.
 *
 * @todo Err. Whatcha doin' here.
 *
 * @return string A random validation code
 */
function generateValidationCode()
{
	global $smcFunc, $modSettings;

	$request = $smcFunc['db_query']('get_random_number', '
		SELECT RAND()',
		array(
		)
	);

	list ($dbRand) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return substr(preg_replace('/\W/', '', sha1(microtime() . $smcFunc['random_int']() . $dbRand . $modSettings['rand_seed'])), 0, 10);
}

?>