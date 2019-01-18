<?php

/**
 * This file has the primary job of showing and editing people's profiles.
 * It also allows the user to change some of their or another's preferences,
 * and such things.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * The main designating function for modifying profiles. Loads up info, determins what to do, etc.
 *
 * @param array $post_errors Any errors that occurred
 */
function ModifyProfile($post_errors = array())
{
	global $txt, $scripturl, $user_info, $context, $sourcedir, $user_profile, $cur_profile;
	global $modSettings, $memberContext, $profile_vars, $post_errors, $smcFunc;

	// Don't reload this as we may have processed error strings.
	if (empty($post_errors))
		loadLanguage('Profile+Drafts');
	loadTemplate('Profile');

	require_once($sourcedir . '/Subs-Menu.php');

	// Did we get the user by name...
	if (isset($_REQUEST['user']))
		$memberResult = loadMemberData($_REQUEST['user'], true, 'profile');
	// ... or by id_member?
	elseif (!empty($_REQUEST['u']))
		$memberResult = loadMemberData((int) $_REQUEST['u'], false, 'profile');
	// If it was just ?action=profile, edit your own profile, but only if you're not a guest.
	else
	{
		// Members only...
		is_not_guest();
		$memberResult = loadMemberData($user_info['id'], false, 'profile');
	}

	// Check if loadMemberData() has returned a valid result.
	if (!$memberResult)
		fatal_lang_error('not_a_user', false, 404);

	// If all went well, we have a valid member ID!
	list ($memID) = $memberResult;
	$memID = (int) $memID;
	$context['id_member'] = $memID;
	$cur_profile = $user_profile[$memID];

	// Let's have some information about this member ready, too.
	loadMemberContext($memID);
	$context['member'] = $memberContext[$memID];

	// Is this the profile of the user himself or herself?
	$context['user']['is_owner'] = $memID == $user_info['id'];

	// Group management isn't actually a permission. But we need it to be for this, so we need a phantom permission.
	// And we care about what the current user can do, not what the user whose profile it is.
	if ($user_info['mod_cache']['gq'] != '0=1')
		$user_info['permissions'][] = 'approve_group_requests';

	// If paid subscriptions are enabled, make sure we actually have at least one subscription available...
	$context['subs_available'] = false;

	if (!empty($modSettings['paid_enabled']))
	{
		$get_active_subs = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}subscriptions
			WHERE active = {int:active}', array(
				'active' => 1,
			)
		);

		list ($num_subs) = $smcFunc['db_fetch_row']($get_active_subs);

		$context['subs_available'] = ($num_subs > 0);

		$smcFunc['db_free_result']($get_active_subs);
	}

	/* Define all the sections within the profile area!
		We start by defining the permission required - then SMF takes this and turns it into the relevant context ;)
		Possible fields:
			For Section:
				string $title:		Section title.
				array $areas:		Array of areas within this section.

			For Areas:
				string $label:		Text string that will be used to show the area in the menu.
				string $file:		Optional text string that may contain a file name that's needed for inclusion in order to display the area properly.
				string $custom_url:	Optional href for area.
				string $function:	Function to execute for this section. Can be a call to an static method: class::method
				string $class		If your function is a method, set the class field with your class's name and SMF will create a new instance for it.
				bool $enabled:		Should area be shown?
				string $sc:			Session check validation to do on save - note without this save will get unset - if set.
				bool $hidden:		Does this not actually appear on the menu?
				bool $password:		Whether to require the user's password in order to save the data in the area.
				array $subsections:	Array of subsections, in order of appearance.
				array $permission:	Array of permissions to determine who can access this area. Should contain arrays $own and $any.
	*/
	$profile_areas = array(
		'info' => array(
			'title' => $txt['profileInfo'],
			'areas' => array(
				'summary' => array(
					'label' => $txt['summary'],
					'file' => 'Profile-View.php',
					'function' => 'summary',
					'icon' => 'administration',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => 'profile_view',
					),
				),
				'popup' => array(
					'function' => 'profile_popup',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
					'select' => 'summary',
				),
				'alerts_popup' => array(
					'function' => 'alerts_popup',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
					'select' => 'summary',
				),
				'statistics' => array(
					'label' => $txt['statPanel'],
					'file' => 'Profile-View.php',
					'function' => 'statPanel',
					'icon' => 'stats',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => 'profile_view',
					),
				),
				'showposts' => array(
					'label' => $txt['showPosts'],
					'file' => 'Profile-View.php',
					'function' => 'showPosts',
					'icon' => 'posts',
					'subsections' => array(
						'messages' => array($txt['showMessages'], array('is_not_guest', 'profile_view')),
						'topics' => array($txt['showTopics'], array('is_not_guest', 'profile_view')),
						'unwatchedtopics' => array($txt['showUnwatched'], array('is_not_guest', 'profile_view'), 'enabled' => $context['user']['is_owner']),
						'attach' => array($txt['showAttachments'], array('is_not_guest', 'profile_view')),
					),
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => 'profile_view',
					),
				),
				'showdrafts' => array(
					'label' => $txt['drafts_show'],
					'file' => 'Drafts.php',
					'function' => 'showProfileDrafts',
					'icon' => 'drafts',
					'enabled' => !empty($modSettings['drafts_post_enabled']) && $context['user']['is_owner'],
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
				),
				'showalerts' => array(
					'label' => $txt['alerts_show'],
					'file' => 'Profile-View.php',
					'function' => 'showAlerts',
					'icon' => 'alerts',
					'permission' => array(
						'own' => 'is_not_guest',
						'any' => array(),
					),
				),
				'permissions' => array(
					'label' => $txt['showPermissions'],
					'file' => 'Profile-View.php',
					'function' => 'showPermissions',
					'icon' => 'permissions',
					'permission' => array(
						'own' => 'manage_permissions',
						'any' => 'manage_permissions',
					),
				),
				'tracking' => array(
					'label' => $txt['trackUser'],
					'file' => 'Profile-View.php',
					'function' => 'tracking',
					'icon' => 'logs',
					'subsections' => array(
						'activity' => array($txt['trackActivity'], 'moderate_forum'),
						'ip' => array($txt['trackIP'], 'moderate_forum'),
						'edits' => array($txt['trackEdits'], 'moderate_forum', 'enabled' => !empty($modSettings['userlog_enabled'])),
						'groupreq' => array($txt['trackGroupRequests'], 'approve_group_requests', 'enabled' => !empty($modSettings['show_group_membership'])),
						'logins' => array($txt['trackLogins'], 'moderate_forum', 'enabled' => !empty($modSettings['loginHistoryDays'])),
					),
					'permission' => array(
						'own' => array('moderate_forum', 'approve_group_requests'),
						'any' => array('moderate_forum', 'approve_group_requests'),
					),
				),
				'viewwarning' => array(
					'label' => $txt['profile_view_warnings'],
					'enabled' => $modSettings['warning_settings'][0] == 1 && $cur_profile['warning'],
					'file' => 'Profile-View.php',
					'function' => 'viewWarning',
					'icon' => 'warning',
					'permission' => array(
						'own' => array('profile_warning_own', 'profile_warning_any', 'issue_warning', 'moderate_forum'),
						'any' => array('profile_warning_any', 'issue_warning', 'moderate_forum'),
					),
				),
			),
		),
		'edit_profile' => array(
			'title' => $txt['forumprofile'],
			'areas' => array(
				'account' => array(
					'label' => $txt['account'],
					'file' => 'Profile-Modify.php',
					'function' => 'account',
					'icon' => 'maintain',
					'enabled' => $context['user']['is_admin'] || ($cur_profile['id_group'] != 1 && !in_array(1, explode(',', $cur_profile['additional_groups']))),
					'sc' => 'post',
					'token' => 'profile-ac%u',
					'password' => true,
					'permission' => array(
						'own' => array('profile_identity_any', 'profile_identity_own', 'profile_password_any', 'profile_password_own', 'manage_membergroups'),
						'any' => array('profile_identity_any', 'profile_password_any', 'manage_membergroups'),
					),
				),
				'tfasetup' => array(
					'file' => 'Profile-Modify.php',
					'function' => 'tfasetup',
					'token' => 'profile-tfa%u',
					'enabled' => !empty($modSettings['tfa_mode']),
					'permission' => array(
						'own' => array('profile_password_own'),
						'any' => array('profile_password_any'),
					),
				),
				'tfadisable' => array(
					'file' => 'Profile-Modify.php',
					'function' => 'tfadisable',
					'token' => 'profile-tfa%u',
					'sc' => 'post',
					'password' => true,
					'enabled' => !empty($modSettings['tfa_mode']),
					'hidden' => !isset($_REQUEST['area']) || $_REQUEST['area'] != 'tfadisable',
					'permission' => array(
						'own' => array('profile_password_own'),
						'any' => array('profile_password_any'),
					),
				),
				'forumprofile' => array(
					'label' => $txt['forumprofile'],
					'file' => 'Profile-Modify.php',
					'function' => 'forumProfile',
					'icon' => 'members',
					'sc' => 'post',
					'token' => 'profile-fp%u',
					'permission' => array(
						'own' => array('profile_forum_any', 'profile_forum_own'),
						'any' => array('profile_forum_any'),
					),
				),
				'theme' => array(
					'label' => $txt['theme'],
					'file' => 'Profile-Modify.php',
					'function' => 'theme',
					'icon' => 'features',
					'sc' => 'post',
					'token' => 'profile-th%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'notification' => array(
					'label' => $txt['notification'],
					'file' => 'Profile-Modify.php',
					'function' => 'notification',
					'icon' => 'mail',
					'sc' => 'post',
					//'token' => 'profile-nt%u', This is not checked here. We do it in the function itself - but if it was checked, this is what it'd be.
					'subsections' => array(
						'alerts' => array($txt['alert_prefs'], array('is_not_guest', 'profile_extra_any')),
						'topics' => array($txt['watched_topics'], array('is_not_guest', 'profile_extra_any')),
						'boards' => array($txt['watched_boards'], array('is_not_guest', 'profile_extra_any')),
					),
					'permission' => array(
						'own' => array('is_not_guest'),
						'any' => array('profile_extra_any'), // If you change this, update it in the functions themselves; we delegate all saving checks there.
					),
				),
				'ignoreboards' => array(
					'label' => $txt['ignoreboards'],
					'file' => 'Profile-Modify.php',
					'function' => 'ignoreboards',
					'icon' => 'boards',
					'enabled' => !empty($modSettings['allow_ignore_boards']),
					'sc' => 'post',
					'token' => 'profile-ib%u',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'lists' => array(
					'label' => $txt['editBuddyIgnoreLists'],
					'file' => 'Profile-Modify.php',
					'function' => 'editBuddyIgnoreLists',
					'icon' => 'frenemy',
					'enabled' => !empty($modSettings['enable_buddylist']) && $context['user']['is_owner'],
					'sc' => 'post',
					'subsections' => array(
						'buddies' => array($txt['editBuddies']),
						'ignore' => array($txt['editIgnoreList']),
					),
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array(),
					),
				),
				'groupmembership' => array(
					'label' => $txt['groupmembership'],
					'file' => 'Profile-Modify.php',
					'function' => 'groupMembership',
					'icon' => 'people',
					'enabled' => !empty($modSettings['show_group_membership']) && $context['user']['is_owner'],
					'sc' => 'request',
					'token' => 'profile-gm%u',
					'token_type' => 'request',
					'permission' => array(
						'own' => array('is_not_guest'),
						'any' => array('manage_membergroups'),
					),
				),
			),
		),
		'profile_action' => array(
			'title' => $txt['profileAction'],
			'areas' => array(
				'sendpm' => array(
					'label' => $txt['profileSendIm'],
					'custom_url' => $scripturl . '?action=pm;sa=send',
					'icon' => 'personal_message',
					'enabled' => allowedTo('profile_view'),
					'permission' => array(
						'own' => array(),
						'any' => array('pm_send'),
					),
				),
				'report' => array(
					'label' => $txt['report_profile'],
					'custom_url' => $scripturl . '?action=reporttm;' . $context['session_var'] . '=' . $context['session_id'],
					'icon' => 'warning',
					'enabled' => allowedTo('profile_view'),
					'permission' => array(
						'own' => array(),
						'any' => array('report_user'),
					),
				),
				'issuewarning' => array(
					'label' => $txt['profile_issue_warning'],
					'enabled' => $modSettings['warning_settings'][0] == 1,
					'file' => 'Profile-Actions.php',
					'function' => 'issueWarning',
					'icon' => 'warning',
					'token' => 'profile-iw%u',
					'permission' => array(
						'own' => array(),
						'any' => array('issue_warning'),
					),
				),
				'banuser' => array(
					'label' => $txt['profileBanUser'],
					'custom_url' => $scripturl . '?action=admin;area=ban;sa=add',
					'icon' => 'ban',
					'enabled' => $cur_profile['id_group'] != 1 && !in_array(1, explode(',', $cur_profile['additional_groups'])),
					'permission' => array(
						'own' => array(),
						'any' => array('manage_bans'),
					),
				),
				'subscriptions' => array(
					'label' => $txt['subscriptions'],
					'file' => 'Profile-Actions.php',
					'function' => 'subscriptions',
					'icon' => 'paid',
					'enabled' => !empty($modSettings['paid_enabled']) && $context['subs_available'],
					'permission' => array(
						'own' => array('is_not_guest'),
						'any' => array('moderate_forum'),
					),
				),
				'deleteaccount' => array(
					'label' => $txt['deleteAccount'],
					'file' => 'Profile-Actions.php',
					'function' => 'deleteAccount',
					'icon' => 'members_delete',
					'sc' => 'post',
					'token' => 'profile-da%u',
					'password' => true,
					'permission' => array(
						'own' => array('profile_remove_any', 'profile_remove_own'),
						'any' => array('profile_remove_any'),
					),
				),
				'activateaccount' => array(
					'file' => 'Profile-Actions.php',
					'function' => 'activateAccount',
					'icon' => 'regcenter',
					'sc' => 'get',
					'token' => 'profile-aa%u',
					'token_type' => 'get',
					'permission' => array(
						'own' => array(),
						'any' => array('moderate_forum'),
					),
				),
			),
		),
	);

	// Let them modify profile areas easily.
	call_integration_hook('integrate_pre_profile_areas', array(&$profile_areas));

	// Do some cleaning ready for the menu function.
	$context['password_areas'] = array();
	$current_area = isset($_REQUEST['area']) ? $_REQUEST['area'] : '';

	foreach ($profile_areas as $section_id => $section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => $area)
		{
			// If it said no permissions that meant it wasn't valid!
			if (empty($area['permission'][$context['user']['is_owner'] ? 'own' : 'any']))
				$profile_areas[$section_id]['areas'][$area_id]['enabled'] = false;
			// Otherwise pick the right set.
			else
				$profile_areas[$section_id]['areas'][$area_id]['permission'] = $area['permission'][$context['user']['is_owner'] ? 'own' : 'any'];

			// Password required in most cases
			if (!empty($area['password']))
				$context['password_areas'][] = $area_id;
		}
	}

	// Is there an updated message to show?
	if (isset($_GET['updated']))
		$context['profile_updated'] = $txt['profile_updated_own'];

	// Set a few options for the menu.
	$menuOptions = array(
		'disable_url_session_check' => true,
		'current_area' => $current_area,
		'extra_url_parameters' => array(
			'u' => $context['id_member'],
		),
	);

	// Actually create the menu!
	$profile_include_data = createMenu($profile_areas, $menuOptions);

	// No menu means no access.
	if (!$profile_include_data && (!$user_info['is_guest'] || validateSession()))
		fatal_lang_error('no_access', false);

	// Make a note of the Unique ID for this menu.
	$context['profile_menu_id'] = $context['max_menu_id'];
	$context['profile_menu_name'] = 'menu_data_' . $context['profile_menu_id'];

	// Set the selected item - now it's been validated.
	$current_area = $profile_include_data['current_area'];
	$current_sa = $profile_include_data['current_subsection'];
	$context['menu_item_selected'] = $current_area;

	// Before we go any further, let's work on the area we've said is valid. Note this is done here just in case we ever compromise the menu function in error!
	$context['completed_save'] = false;
	$context['do_preview'] = isset($_REQUEST['preview_signature']);

	$security_checks = array();
	$found_area = false;
	foreach ($profile_areas as $section_id => $section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => $area)
		{
			// Is this our area?
			if ($current_area == $area_id)
			{
				// This can't happen - but is a security check.
				if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($area['enabled']) && $area['enabled'] == false))
					fatal_lang_error('no_access', false);

				// Are we saving data in a valid area?
				if (isset($area['sc']) && (isset($_REQUEST['save']) || $context['do_preview']))
				{
					$security_checks['session'] = $area['sc'];
					$context['completed_save'] = true;
				}

				// Do we need to perform a token check?
				if (!empty($area['token']))
				{
					$security_checks[isset($_REQUEST['save']) ? 'validateToken' : 'needsToken'] = $area['token'];
					$token_name = $area['token'] !== true ? str_replace('%u', $context['id_member'], $area['token']) : 'profile-u' . $context['id_member'];

					$token_type = isset($area['token_type']) && in_array($area['token_type'], array('request', 'post', 'get')) ? $area['token_type'] : 'post';
				}

				// Does this require session validating?
				if (!empty($area['validate']) || (isset($_REQUEST['save']) && !$context['user']['is_owner']))
					$security_checks['validate'] = true;

				// Permissions for good measure.
				if (!empty($profile_include_data['permission']))
					$security_checks['permission'] = $profile_include_data['permission'];

				// Either way got something.
				$found_area = true;
			}
		}
	}

	// Oh dear, some serious security lapse is going on here... we'll put a stop to that!
	if (!$found_area)
		fatal_lang_error('no_access', false);

	// Release this now.
	unset($profile_areas);

	// Now the context is setup have we got any security checks to carry out additional to that above?
	if (isset($security_checks['session']))
		checkSession($security_checks['session']);
	if (isset($security_checks['validate']))
		validateSession();
	if (isset($security_checks['validateToken']))
		validateToken($token_name, $token_type);
	if (isset($security_checks['permission']))
		isAllowedTo($security_checks['permission']);

	// Create a token if needed.
	if (isset($security_checks['needsToken']) || isset($security_checks['validateToken']))
	{
		createToken($token_name, $token_type);
		$context['token_check'] = $token_name;
	}

	// File to include?
	if (isset($profile_include_data['file']))
		require_once($sourcedir . '/' . $profile_include_data['file']);

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : ''),
		'name' => sprintf($txt['profile_of_username'], $context['member']['name']),
	);

	if (!empty($profile_include_data['label']))
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area'],
			'name' => $profile_include_data['label'],
		);

	if (!empty($profile_include_data['current_subsection']) && $profile_include_data['subsections'][$profile_include_data['current_subsection']][0] != $profile_include_data['label'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=profile' . ($memID != $user_info['id'] ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area'] . ';sa=' . $profile_include_data['current_subsection'],
			'name' => $profile_include_data['subsections'][$profile_include_data['current_subsection']][0],
		);

	// Set the template for this area and add the profile layer.
	$context['sub_template'] = $profile_include_data['function'];
	$context['template_layers'][] = 'profile';

	// All the subactions that require a user password in order to validate.
	$check_password = $context['user']['is_owner'] && in_array($profile_include_data['current_area'], $context['password_areas']);
	$context['require_password'] = $check_password;

	loadJavaScriptFile('profile.js', array('defer' => false, 'minimize' => true), 'smf_profile');

	// These will get populated soon!
	$post_errors = array();
	$profile_vars = array();

	// Right - are we saving - if so let's save the old data first.
	if ($context['completed_save'])
	{
		// Clean up the POST variables.
		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);

		if ($check_password)
		{
			// Check to ensure we're forcing SSL for authentication
			if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
				fatal_lang_error('login_ssl_required');

			// You didn't even enter a password!
			if (trim($_POST['oldpasswrd']) == '')
				$post_errors[] = 'no_password';

			// Since the password got modified due to all the $_POST cleaning, lets undo it so we can get the correct password
			$_POST['oldpasswrd'] = un_htmlspecialchars($_POST['oldpasswrd']);

			// Does the integration want to check passwords?
			$good_password = in_array(true, call_integration_hook('integrate_verify_password', array($cur_profile['member_name'], $_POST['oldpasswrd'], false)), true);

			// Bad password!!!
			if (!$good_password && !hash_verify_password($user_profile[$memID]['member_name'], un_htmlspecialchars(stripslashes($_POST['oldpasswrd'])), $user_info['passwd']))
				$post_errors[] = 'bad_password';

			// Warn other elements not to jump the gun and do custom changes!
			if (in_array('bad_password', $post_errors))
				$context['password_auth_failed'] = true;
		}

		// Change the IP address in the database.
		if ($context['user']['is_owner'])
			$profile_vars['member_ip'] = $user_info['ip'];

		// Now call the sub-action function...
		if ($current_area == 'activateaccount')
		{
			if (empty($post_errors))
				activateAccount($memID);
		}
		elseif ($current_area == 'deleteaccount')
		{
			if (empty($post_errors))
			{
				deleteAccount2($memID);
				redirectexit();
			}
		}
		elseif ($current_area == 'tfadisable')
		{
			// Already checked the password, token, permissions, and session.
			$profile_vars += array(
				'tfa_secret' => '',
				'tfa_backup' => '',
			);
		}
		elseif ($current_area == 'groupmembership' && empty($post_errors))
		{
			$msg = groupMembership2($profile_vars, $post_errors, $memID);

			// Whatever we've done, we have nothing else to do here...
			redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $memID) . ';area=groupmembership' . (!empty($msg) ? ';msg=' . $msg : ''));
		}
		elseif (in_array($current_area, array('account', 'forumprofile', 'theme')))
			saveProfileFields();
		else
		{
			$force_redirect = true;
			// Ensure we include this.
			require_once($sourcedir . '/Profile-Modify.php');
			saveProfileChanges($profile_vars, $post_errors, $memID);
		}

		call_integration_hook('integrate_profile_save', array(&$profile_vars, &$post_errors, $memID, $cur_profile, $current_area));

		// There was a problem, let them try to re-enter.
		if (!empty($post_errors))
		{
			// Load the language file so we can give a nice explanation of the errors.
			loadLanguage('Errors');
			$context['post_errors'] = $post_errors;
		}
		elseif (!empty($profile_vars))
		{
			// If we've changed the password, notify any integration that may be listening in.
			if (isset($profile_vars['passwd']))
				call_integration_hook('integrate_reset_pass', array($cur_profile['member_name'], $cur_profile['member_name'], $_POST['passwrd2']));

			updateMemberData($memID, $profile_vars);

			// What if this is the newest member?
			if ($modSettings['latestMember'] == $memID)
				updateStats('member');
			elseif (isset($profile_vars['real_name']))
				updateSettings(array('memberlist_updated' => time()));

			// If the member changed his/her birthdate, update calendar statistics.
			if (isset($profile_vars['birthdate']) || isset($profile_vars['real_name']))
				updateSettings(array(
					'calendar_updated' => time(),
				));

			// Anything worth logging?
			if (!empty($context['log_changes']) && !empty($modSettings['modlog_enabled']))
			{
				$log_changes = array();
				require_once($sourcedir . '/Logging.php');
				foreach ($context['log_changes'] as $k => $v)
					$log_changes[] = array(
						'action' => $k,
						'log_type' => 'user',
						'extra' => array_merge($v, array(
							'applicator' => $user_info['id'],
							'member_affected' => $memID,
						)),
					);

				logActions($log_changes);
			}

			// Have we got any post save functions to execute?
			if (!empty($context['profile_execute_on_save']))
				foreach ($context['profile_execute_on_save'] as $saveFunc)
					$saveFunc();

			// Let them know it worked!
			$context['profile_updated'] = $context['user']['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']);

			// Invalidate any cached data.
			cache_put_data('member_data-profile-' . $memID, null, 0);
		}
	}

	// Have some errors for some reason?
	if (!empty($post_errors))
	{
		// Set all the errors so the template knows what went wrong.
		foreach ($post_errors as $error_type)
			$context['modify_error'][$error_type] = true;
	}
	// If it's you then we should redirect upon save.
	elseif (!empty($profile_vars) && $context['user']['is_owner'] && !$context['do_preview'])
		redirectexit('action=profile;area=' . $current_area . (!empty($current_sa) ? ';sa=' . $current_sa : '') . ';updated');
	elseif (!empty($force_redirect))
		redirectexit('action=profile' . ($context['user']['is_owner'] ? '' : ';u=' . $memID) . ';area=' . $current_area);

	// Get the right callable.
	$call = call_helper($profile_include_data['function'], true);

	// Is it valid?
	if (!empty($call))
		call_user_func($call, $memID);

	// Set the page title if it's not already set...
	if (!isset($context['page_title']))
		$context['page_title'] = $txt['profile'] . (isset($txt[$current_area]) ? ' - ' . $txt[$current_area] : '');
}

/**
 * Set up the requirements for the profile popup - the area that is shown as the popup menu for the current user.
 *
 * @param int $memID The ID of the member
 */
function profile_popup($memID)
{
	global $context, $scripturl, $txt, $db_show_debug;

	// We do not want to output debug information here.
	$db_show_debug = false;

	// We only want to output our little layer here.
	$context['template_layers'] = array();

	// This list will pull from the master list wherever possible. Hopefully it should be clear what does what.
	$profile_items = array(
		array(
			'menu' => 'info',
			'area' => 'summary',
			'title' => $txt['popup_summary'],
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'account',
		),
		array(
			'menu' => 'info',
			'area' => 'showposts',
			'title' => $txt['popup_showposts'],
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'forumprofile',
			'title' => $txt['forumprofile'],
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'notification',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'theme',
			'title' => $txt['theme'],
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'ignoreboards',
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'lists',
			'url' => $scripturl . '?action=profile;area=lists;sa=ignore',
			'title' => $txt['popup_ignore'],
		),
		array(
			'menu' => 'edit_profile',
			'area' => 'groupmembership',
		),
		array(
			'menu' => 'profile_action',
			'area' => 'subscriptions',
		),
	);

	call_integration_hook('integrate_profile_popup', array(&$profile_items));

	// Now check if these items are available
	$context['profile_items'] = array();
	$menu_context = &$context[$context['profile_menu_name']]['sections'];
	foreach ($profile_items as $item)
	{
		if (isset($menu_context[$item['menu']]['areas'][$item['area']]))
		{
			$context['profile_items'][] = $item;
		}
	}
}

/**
 * Set up the requirements for the alerts popup - the area that shows all the alerts just quickly for the current user.
 *
 * @param int $memID The ID of the member
 */
function alerts_popup($memID)
{
	global $context, $sourcedir, $db_show_debug, $cur_profile;

	// Load the Alerts language file.
	loadLanguage('Alerts');

	// We do not want to output debug information here.
	$db_show_debug = false;

	// We only want to output our little layer here.
	$context['template_layers'] = array();

	// No funny business allowed
	$fetch_all = !isset($_REQUEST['counter']);
	$_REQUEST['counter'] = isset($_REQUEST['counter']) ? max(0, (int) $_REQUEST['counter']) : 0;

	$context['unread_alerts'] = array();
	if ($fetch_all || $_REQUEST['counter'] < $cur_profile['alerts'])
	{
		// Now fetch me my unread alerts, pronto!
		require_once($sourcedir . '/Profile-View.php');
		$context['unread_alerts'] = fetch_alerts($memID, false, $fetch_all ? null : $cur_profile['alerts'] - $_REQUEST['counter']);

		// This shouldn't happen, but just in case...
		if ($fetch_all && $cur_profile['alerts'] != count($context['unread_alerts']))
			updateMemberData($memID, array('alerts' => count($context['unread_alerts'])));
	}
}

/**
 * Load any custom fields for this area... no area means load all, 'summary' loads all public ones.
 *
 * @param int $memID The ID of the member
 * @param string $area Which area to load fields for
 */
function loadCustomFields($memID, $area = 'summary')
{
	global $context, $txt, $user_profile, $smcFunc, $user_info, $settings, $scripturl;

	// Get the right restrictions in place...
	$where = 'active = 1';
	if (!allowedTo('admin_forum') && $area != 'register')
	{
		// If it's the owner they can see two types of private fields, regardless.
		if ($memID == $user_info['id'])
			$where .= $area == 'summary' ? ' AND private < 3' : ' AND (private = 0 OR private = 2)';
		else
			$where .= $area == 'summary' ? ' AND private < 2' : ' AND private = 0';
	}

	if ($area == 'register')
		$where .= ' AND show_reg != 0';
	elseif ($area != 'summary')
		$where .= ' AND show_profile = {string:area}';

	// Load all the relevant fields - and data.
	$request = $smcFunc['db_query']('', '
		SELECT
			col_name, field_name, field_desc, field_type, field_order, show_reg, field_length, field_options,
			default_value, bbc, enclose, placement
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
		ORDER BY field_order',
		array(
			'area' => $area,
		)
	);
	$context['custom_fields'] = array();
	$context['custom_fields_required'] = false;
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Shortcut.
		$exists = $memID && isset($user_profile[$memID], $user_profile[$memID]['options'][$row['col_name']]);
		$value = $exists ? $user_profile[$memID]['options'][$row['col_name']] : '';

		$currentKey = 0;
		if (!empty($row['field_options']))
		{
			$fieldOptions = explode(',', $row['field_options']);
			foreach ($fieldOptions as $k => $v)
			{
				if (empty($currentKey))
					$currentKey = $v === $value ? $k : 0;
			}
		}

		// If this was submitted already then make the value the posted version.
		if (isset($_POST['customfield']) && isset($_POST['customfield'][$row['col_name']]))
		{
			$value = $smcFunc['htmlspecialchars']($_POST['customfield'][$row['col_name']]);
			if (in_array($row['field_type'], array('select', 'radio')))
				$value = ($options = explode(',', $row['field_options'])) && isset($options[$value]) ? $options[$value] : '';
		}

		// Don't show the "disabled" option for the "gender" field if we are on the "summary" area.
		if ($area == 'summary' && $row['col_name'] == 'cust_gender' && $value == 'None')
			continue;

		// HTML for the input form.
		$output_html = $value;
		if ($row['field_type'] == 'check')
		{
			$true = (!$exists && $row['default_value']) || $value;
			$input_html = '<input type="checkbox" name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"' . ($true ? ' checked' : '') . '>';
			$output_html = $true ? $txt['yes'] : $txt['no'];
		}
		elseif ($row['field_type'] == 'select')
		{
			$input_html = '<select name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"><option value="-1"></option>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<option value="' . $k . '"' . ($true ? ' selected' : '') . '>' . $v . '</option>';
				if ($true)
					$output_html = $v;
			}

			$input_html .= '</select>';
		}
		elseif ($row['field_type'] == 'radio')
		{
			$input_html = '<fieldset>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<label for="customfield_' . $row['col_name'] . '_' . $k . '"><input type="radio" name="customfield[' . $row['col_name'] . ']" id="customfield_' . $row['col_name'] . '_' . $k . '" value="' . $k . '"' . ($true ? ' checked' : '') . '>' . $v . '</label><br>';
				if ($true)
					$output_html = $v;
			}
			$input_html .= '</fieldset>';
		}
		elseif ($row['field_type'] == 'text')
		{
			$input_html = '<input type="text" name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"' . ($row['field_length'] != 0 ? ' maxlength="' . $row['field_length'] . '"' : '') . ' size="' . ($row['field_length'] == 0 || $row['field_length'] >= 50 ? 50 : ($row['field_length'] > 30 ? 30 : ($row['field_length'] > 10 ? 20 : 10))) . '" value="' . un_htmlspecialchars($value) . '"' . ($row['show_reg'] == 2 ? ' required' : '') . '>';
		}
		else
		{
			@list ($rows, $cols) = @explode(',', $row['default_value']);
			$input_html = '<textarea name="customfield[' . $row['col_name'] . ']" id="customfield[' . $row['col_name'] . ']"' . (!empty($rows) ? ' rows="' . $rows . '"' : '') . (!empty($cols) ? ' cols="' . $cols . '"' : '') . ($row['show_reg'] == 2 ? ' required' : '') . '>' . un_htmlspecialchars($value) . '</textarea>';
		}

		// Parse BBCode
		if ($row['bbc'])
			$output_html = parse_bbc($output_html);
		elseif ($row['field_type'] == 'textarea')
			// Allow for newlines at least
			$output_html = strtr($output_html, array("\n" => '<br>'));

		// Enclosing the user input within some other text?
		if (!empty($row['enclose']) && !empty($output_html))
			$output_html = strtr($row['enclose'], array(
				'{SCRIPTURL}' => $scripturl,
				'{IMAGES_URL}' => $settings['images_url'],
				'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
				'{INPUT}' => un_htmlspecialchars($output_html),
				'{KEY}' => $currentKey
			));

		$context['custom_fields'][] = array(
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
			'type' => $row['field_type'],
			'order' => $row['field_order'],
			'input_html' => $input_html,
			'output_html' => $output_html,
			'placement' => $row['placement'],
			'colname' => $row['col_name'],
			'value' => $value,
			'show_reg' => $row['show_reg'],
		);
		$context['custom_fields_required'] = $context['custom_fields_required'] || $row['show_reg'] == 2;
	}
	$smcFunc['db_free_result']($request);

	call_integration_hook('integrate_load_custom_profile_fields', array($memID, $area));
}

?>