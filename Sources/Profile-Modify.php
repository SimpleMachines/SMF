<?php

/**
 * This file has the primary job of showing and editing people's profiles.
 * 	It also allows the user to change some of their or another's preferences,
 * 	and such things
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 4
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * This defines every profile field known to man.
 *
 * @param bool $force_reload Whether to reload the data
 */
function loadProfileFields($force_reload = false)
{
	global $context, $profile_fields, $txt, $scripturl, $modSettings, $user_info, $smcFunc, $cur_profile, $language;
	global $sourcedir, $profile_vars;

	// Don't load this twice!
	if (!empty($profile_fields) && !$force_reload)
		return;

	/* This horrific array defines all the profile fields in the whole world!
		In general each "field" has one array - the key of which is the database column name associated with said field. Each item
		can have the following attributes:

				string $type:			The type of field this is - valid types are:
					- callback:		This is a field which has its own callback mechanism for templating.
					- check:		A simple checkbox.
					- hidden:		This doesn't have any visual aspects but may have some validity.
					- password:		A password box.
					- select:		A select box.
					- text:			A string of some description.

				string $label:			The label for this item - default will be $txt[$key] if this isn't set.
				string $subtext:		The subtext (Small label) for this item.
				int $size:			Optional size for a text area.
				array $input_attr:		An array of text strings to be added to the input box for this item.
				string $value:			The value of the item. If not set $cur_profile[$key] is assumed.
				string $permission:		Permission required for this item (Excluded _any/_own subfix which is applied automatically).
				function $input_validate:	A runtime function which validates the element before going to the database. It is passed
								the relevant $_POST element if it exists and should be treated like a reference.

								Return types:
					- true:			Element can be stored.
					- false:		Skip this element.
					- a text string:	An error occured - this is the error message.

				function $preload:		A function that is used to load data required for this element to be displayed. Must return
								true to be displayed at all.

				string $cast_type:		If set casts the element to a certain type. Valid types (bool, int, float).
				string $save_key:		If the index of this element isn't the database column name it can be overriden
								with this string.
				bool $is_dummy:			If set then nothing is acted upon for this element.
				bool $enabled:			A test to determine whether this is even available - if not is unset.
				string $link_with:		Key which links this field to an overall set.

		Note that all elements that have a custom input_validate must ensure they set the value of $cur_profile correct to enable
		the changes to be displayed correctly on submit of the form.

	*/

	$profile_fields = array(
		'avatar_choice' => array(
			'type' => 'callback',
			'callback_func' => 'avatar_select',
			// This handles the permissions too.
			'preload' => 'profileLoadAvatarData',
			'input_validate' => 'profileSaveAvatarData',
			'save_key' => 'avatar',
		),
		'bday1' => array(
			'type' => 'callback',
			'callback_func' => 'birthdate',
			'permission' => 'profile_extra',
			'preload' => function() use ($cur_profile, &$context)
			{
				// Split up the birthdate....
				list ($uyear, $umonth, $uday) = explode('-', empty($cur_profile['birthdate']) || $cur_profile['birthdate'] === '1004-01-01' ? '--' : $cur_profile['birthdate']);
				$context['member']['birth_date'] = array(
					'year' => $uyear,
					'month' => $umonth,
					'day' => $uday,
				);

				return true;
			},
			'input_validate' => function(&$value) use (&$cur_profile, &$profile_vars)
			{
				if (isset($_POST['bday2'], $_POST['bday3']) && $value > 0 && $_POST['bday2'] > 0)
				{
					// Set to blank?
					if ((int) $_POST['bday3'] == 1 && (int) $_POST['bday2'] == 1 && (int) $value == 1)
						$value = '1004-01-01';
					else
						$value = checkdate($value, $_POST['bday2'], $_POST['bday3'] < 1004 ? 1004 : $_POST['bday3']) ? sprintf('%04d-%02d-%02d', $_POST['bday3'] < 1004 ? 1004 : $_POST['bday3'], $_POST['bday1'], $_POST['bday2']) : '1004-01-01';
				}
				else
					$value = '1004-01-01';

				$profile_vars['birthdate'] = $value;
				$cur_profile['birthdate'] = $value;
				return false;
			},
		),
		// Setting the birthdate the old style way?
		'birthdate' => array(
			'type' => 'hidden',
			'permission' => 'profile_extra',
			'input_validate' => function(&$value) use ($cur_profile)
			{
				// @todo Should we check for this year and tell them they made a mistake :P? (based on coppa at least?)
				if (preg_match('/(\d{4})[\-\., ](\d{2})[\-\., ](\d{2})/', $value, $dates) === 1)
				{
					$value = checkdate($dates[2], $dates[3], $dates[1] < 4 ? 4 : $dates[1]) ? sprintf('%04d-%02d-%02d', $dates[1] < 4 ? 4 : $dates[1], $dates[2], $dates[3]) : '1004-01-01';
					return true;
				}
				else
				{
					$value = empty($cur_profile['birthdate']) ? '1004-01-01' : $cur_profile['birthdate'];
					return false;
				}
			},
		),
		'date_registered' => array(
			'type' => 'date',
			'value' => empty($cur_profile['date_registered']) ? $txt['not_applicable'] : strftime('%Y-%m-%d', $cur_profile['date_registered'] + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600),
			'label' => $txt['date_registered'],
			'log_change' => true,
			'permission' => 'moderate_forum',
			'input_validate' => function(&$value) use ($txt, $user_info, $modSettings, $cur_profile, $context)
			{
				// Bad date!  Go try again - please?
				if (($value = strtotime($value)) === -1)
				{
					$value = $cur_profile['date_registered'];
					return $txt['invalid_registration'] . ' ' . strftime('%d %b %Y ' . (strpos($user_info['time_format'], '%H') !== false ? '%I:%M:%S %p' : '%H:%M:%S'), forum_time(false));
				}
				// As long as it doesn't equal "N/A"...
				elseif ($value != $txt['not_applicable'] && $value != strtotime(strftime('%Y-%m-%d', $cur_profile['date_registered'] + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600)))
					$value = $value - ($user_info['time_offset'] + $modSettings['time_offset']) * 3600;
				else
					$value = $cur_profile['date_registered'];

				return true;
			},
		),
		'email_address' => array(
			'type' => 'email',
			'label' => $txt['user_email_address'],
			'subtext' => $txt['valid_email'],
			'log_change' => true,
			'permission' => 'profile_password',
			'js_submit' => !empty($modSettings['send_validation_onChange']) ? '
	form_handle.addEventListener(\'submit\', function(event)
	{
		if (this.email_address.value != "'. (!empty($cur_profile['email_address']) ? $cur_profile['email_address'] : '') . '")
		{
			alert('. JavaScriptEscape($txt['email_change_logout']) . ');
			return true;
		}
	}, false);' : '',
			'input_validate' => function(&$value)
			{
				global $context, $old_profile, $profile_vars, $sourcedir, $modSettings;

				if (strtolower($value) == strtolower($old_profile['email_address']))
					return false;

				$isValid = profileValidateEmail($value, $context['id_member']);

				// Do they need to revalidate? If so schedule the function!
				if ($isValid === true && !empty($modSettings['send_validation_onChange']) && !allowedTo('moderate_forum'))
				{
					require_once($sourcedir . '/Subs-Members.php');
					$profile_vars['validation_code'] = generateValidationCode();
					$profile_vars['is_activated'] = 2;
					$context['profile_execute_on_save'][] = 'profileSendActivation';
					unset($context['profile_execute_on_save']['reload_user']);
				}

				return $isValid;
			},
		),
		// Selecting group membership is a complicated one so we treat it separate!
		'id_group' => array(
			'type' => 'callback',
			'callback_func' => 'group_manage',
			'permission' => 'manage_membergroups',
			'preload' => 'profileLoadGroups',
			'log_change' => true,
			'input_validate' => 'profileSaveGroups',
		),
		'id_theme' => array(
			'type' => 'callback',
			'callback_func' => 'theme_pick',
			'permission' => 'profile_extra',
			'enabled' => $modSettings['theme_allow'] || allowedTo('admin_forum'),
			'preload' => function() use ($smcFunc, &$context, $cur_profile, $txt)
			{
				$request = $smcFunc['db_query']('', '
					SELECT value
					FROM {db_prefix}themes
					WHERE id_theme = {int:id_theme}
						AND variable = {string:variable}
					LIMIT 1', array(
						'id_theme' => $cur_profile['id_theme'],
						'variable' => 'name',
					)
				);
				list ($name) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				$context['member']['theme'] = array(
					'id' => $cur_profile['id_theme'],
					'name' => empty($cur_profile['id_theme']) ? $txt['theme_forum_default'] : $name
				);
				return true;
			},
			'input_validate' => function(&$value)
			{
				$value = (int) $value;
				return true;
			},
		),
		'lngfile' => array(
			'type' => 'select',
			'options' => function() use (&$context)
			{
				return $context['profile_languages'];
			},
			'label' => $txt['preferred_language'],
			'permission' => 'profile_identity',
			'preload' => 'profileLoadLanguages',
			'enabled' => !empty($modSettings['userLanguage']),
			'value' => empty($cur_profile['lngfile']) ? $language : $cur_profile['lngfile'],
			'input_validate' => function(&$value) use (&$context, $cur_profile)
			{
				// Load the languages.
				profileLoadLanguages();

				if (isset($context['profile_languages'][$value]))
				{
					if ($context['user']['is_owner'] && empty($context['password_auth_failed']))
						$_SESSION['language'] = $value;
					return true;
				}
				else
				{
					$value = $cur_profile['lngfile'];
					return false;
				}
			},
		),
		// The username is not always editable - so adjust it as such.
		'member_name' => array(
			'type' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? 'text' : 'label',
			'label' => $txt['username'],
			'subtext' => allowedTo('admin_forum') && !isset($_GET['changeusername']) ? '[<a href="' . $scripturl . '?action=profile;u=' . $context['id_member'] . ';area=account;changeusername" style="font-style: italic;">' . $txt['username_change'] . '</a>]' : '',
			'log_change' => true,
			'permission' => 'profile_identity',
			'prehtml' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? '<div class="alert">' . $txt['username_warning'] . '</div>' : '',
			'input_validate' => function(&$value) use ($sourcedir, $context, $user_info, $cur_profile)
			{
				if (allowedTo('admin_forum'))
				{
					// We'll need this...
					require_once($sourcedir . '/Subs-Auth.php');

					// Maybe they are trying to change their password as well?
					$resetPassword = true;
					if (isset($_POST['passwrd1']) && $_POST['passwrd1'] != '' && isset($_POST['passwrd2']) && $_POST['passwrd1'] == $_POST['passwrd2'] && validatePassword($_POST['passwrd1'], $value, array($cur_profile['real_name'], $user_info['username'], $user_info['name'], $user_info['email'])) == null)
						$resetPassword = false;

					// Do the reset... this will send them an email too.
					if ($resetPassword)
						resetPassword($context['id_member'], $value);
					elseif ($value !== null)
					{
						validateUsername($context['id_member'], trim(preg_replace('~[\t\n\r \x0B\0' . ($context['utf8'] ? '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : '\x00-\x08\x0B\x0C\x0E-\x19\xA0') . ']+~' . ($context['utf8'] ? 'u' : ''), ' ', $value)));
						updateMemberData($context['id_member'], array('member_name' => $value));

						// Call this here so any integrated systems will know about the name change (resetPassword() takes care of this if we're letting SMF generate the password)
						call_integration_hook('integrate_reset_pass', array($cur_profile['member_name'], $value, $_POST['passwrd1']));
					}
				}
				return false;
			},
		),
		'passwrd1' => array(
			'type' => 'password',
			'label' => ucwords($txt['choose_pass']),
			'subtext' => $txt['password_strength'],
			'size' => 20,
			'value' => '',
			'permission' => 'profile_password',
			'save_key' => 'passwd',
			// Note this will only work if passwrd2 also exists!
			'input_validate' => function(&$value) use ($sourcedir, $user_info, $smcFunc, $cur_profile)
			{
				// If we didn't try it then ignore it!
				if ($value == '')
					return false;

				// Do the two entries for the password even match?
				if (!isset($_POST['passwrd2']) || $value != $_POST['passwrd2'])
					return 'bad_new_password';

				// Let's get the validation function into play...
				require_once($sourcedir . '/Subs-Auth.php');
				$passwordErrors = validatePassword($value, $cur_profile['member_name'], array($cur_profile['real_name'], $user_info['username'], $user_info['name'], $user_info['email']));

				// Were there errors?
				if ($passwordErrors != null)
					return 'password_' . $passwordErrors;

				// Set up the new password variable... ready for storage.
				$value = hash_password($cur_profile['member_name'], un_htmlspecialchars($value));

				return true;
			},
		),
		'passwrd2' => array(
			'type' => 'password',
			'label' => ucwords($txt['verify_pass']),
			'size' => 20,
			'value' => '',
			'permission' => 'profile_password',
			'is_dummy' => true,
		),
		'personal_text' => array(
			'type' => 'text',
			'label' => $txt['personal_text'],
			'log_change' => true,
			'input_attr' => array('maxlength="50"'),
			'size' => 50,
			'permission' => 'profile_blurb',
			'input_validate' => function(&$value) use ($smcFunc)
			{
				if ($smcFunc['strlen']($value) > 50)
					return 'personal_text_too_long';

				return true;
			},
		),
		// This does ALL the pm settings
		'pm_prefs' => array(
			'type' => 'callback',
			'callback_func' => 'pm_settings',
			'permission' => 'pm_read',
			'preload' => function() use (&$context, $cur_profile)
			{
				$context['display_mode'] = $cur_profile['pm_prefs'] & 3;
				$context['receive_from'] = !empty($cur_profile['pm_receive_from']) ? $cur_profile['pm_receive_from'] : 0;

				return true;
			},
			'input_validate' => function(&$value) use (&$cur_profile, &$profile_vars)
			{
				// Simple validate and apply the two "sub settings"
				$value = max(min($value, 2), 0);

				$cur_profile['pm_receive_from'] = $profile_vars['pm_receive_from'] = max(min((int) $_POST['pm_receive_from'], 4), 0);

				return true;
			},
		),
		'posts' => array(
			'type' => 'int',
			'label' => $txt['profile_posts'],
			'log_change' => true,
			'size' => 7,
			'permission' => 'moderate_forum',
			'input_validate' => function(&$value)
			{
				if (!is_numeric($value))
					return 'digits_only';
				else
					$value = $value != '' ? strtr($value, array(',' => '', '.' => '', ' ' => '')) : 0;
				return true;
			},
		),
		'real_name' => array(
			'type' => allowedTo('profile_displayed_name_own') || allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum') ? 'text' : 'label',
			'label' => $txt['name'],
			'subtext' => $txt['display_name_desc'],
			'log_change' => true,
			'input_attr' => array('maxlength="60"'),
			'permission' => 'profile_displayed_name',
			'enabled' => allowedTo('profile_displayed_name_own') || allowedTo('profile_displayed_name_any') || allowedTo('moderate_forum'),
			'input_validate' => function(&$value) use ($context, $smcFunc, $sourcedir, $cur_profile)
			{
				$value = trim(preg_replace('~[\t\n\r \x0B\0' . ($context['utf8'] ? '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : '\x00-\x08\x0B\x0C\x0E-\x19\xA0') . ']+~' . ($context['utf8'] ? 'u' : ''), ' ', $value));

				if (trim($value) == '')
					return 'no_name';
				elseif ($smcFunc['strlen']($value) > 60)
					return 'name_too_long';
				elseif ($cur_profile['real_name'] != $value)
				{
					require_once($sourcedir . '/Subs-Members.php');
					if (isReservedName($value, $context['id_member']))
						return 'name_taken';
				}
				return true;
			},
		),
		'secret_question' => array(
			'type' => 'text',
			'label' => $txt['secret_question'],
			'subtext' => $txt['secret_desc'],
			'size' => 50,
			'permission' => 'profile_password',
		),
		'secret_answer' => array(
			'type' => 'text',
			'label' => $txt['secret_answer'],
			'subtext' => $txt['secret_desc2'],
			'size' => 20,
			'postinput' => '<span class="smalltext"><a href="' . $scripturl . '?action=helpadmin;help=secret_why_blank" onclick="return reqOverlayDiv(this.href);"><span class="generic_icons help"></span> ' . $txt['secret_why_blank'] . '</a></span>',
			'value' => '',
			'permission' => 'profile_password',
			'input_validate' => function(&$value) use ($cur_profile)
			{
				$value = $value != '' ? hash_password($cur_profile['member_name'], $value) : '';
				return true;
			},
		),
		'signature' => array(
			'type' => 'callback',
			'callback_func' => 'signature_modify',
			'permission' => 'profile_signature',
			'enabled' => substr($modSettings['signature_settings'], 0, 1) == 1,
			'preload' => 'profileLoadSignatureData',
			'input_validate' => 'profileValidateSignature',
		),
		'show_online' => array(
			'type' => 'check',
			'label' => $txt['show_online'],
			'permission' => 'profile_identity',
			'enabled' => !empty($modSettings['allow_hideOnline']) || allowedTo('moderate_forum'),
		),
		'smiley_set' => array(
			'type' => 'callback',
			'callback_func' => 'smiley_pick',
			'enabled' => !empty($modSettings['smiley_sets_enable']),
			'permission' => 'profile_extra',
			'preload' => function() use ($modSettings, &$context, $txt, $cur_profile, $smcFunc)
			{
				$context['member']['smiley_set']['id'] = empty($cur_profile['smiley_set']) ? '' : $cur_profile['smiley_set'];
				$context['smiley_sets'] = explode(',', 'none,,' . $modSettings['smiley_sets_known']);
				$set_names = explode("\n", $txt['smileys_none'] . "\n" . $txt['smileys_forum_board_default'] . "\n" . $modSettings['smiley_sets_names']);
				foreach ($context['smiley_sets'] as $i => $set)
				{
					$context['smiley_sets'][$i] = array(
						'id' => $smcFunc['htmlspecialchars']($set),
						'name' => $smcFunc['htmlspecialchars']($set_names[$i]),
						'selected' => $set == $context['member']['smiley_set']['id']
					);

					if ($context['smiley_sets'][$i]['selected'])
						$context['member']['smiley_set']['name'] = $set_names[$i];
				}
				return true;
			},
			'input_validate' => function(&$value)
			{
				global $modSettings;

				$smiley_sets = explode(',', $modSettings['smiley_sets_known']);
				if (!in_array($value, $smiley_sets) && $value != 'none')
					$value = '';
				return true;
			},
		),
		// Pretty much a dummy entry - it populates all the theme settings.
		'theme_settings' => array(
			'type' => 'callback',
			'callback_func' => 'theme_settings',
			'permission' => 'profile_extra',
			'is_dummy' => true,
			'preload' => function() use (&$context, $user_info, $modSettings)
			{
				loadLanguage('Settings');

				$context['allow_no_censored'] = false;
				if ($user_info['is_admin'] || $context['user']['is_owner'])
					$context['allow_no_censored'] = !empty($modSettings['allow_no_censored']);

				return true;
			},
		),
		'tfa' => array(
			'type' => 'callback',
			'callback_func' => 'tfa',
			'permission' => 'profile_password',
			'enabled' => !empty($modSettings['tfa_mode']),
			'preload' => function() use (&$context, $cur_profile)
			{
				$context['tfa_enabled'] = !empty($cur_profile['tfa_secret']);

				return true;
			},
		),
		'time_format' => array(
			'type' => 'callback',
			'callback_func' => 'timeformat_modify',
			'permission' => 'profile_extra',
			'preload' => function() use (&$context, $user_info, $txt, $cur_profile, $modSettings)
			{
				$context['easy_timeformats'] = array(
					array('format' => '', 'title' => $txt['timeformat_default']),
					array('format' => '%B %d, %Y, %I:%M:%S %p', 'title' => $txt['timeformat_easy1']),
					array('format' => '%B %d, %Y, %H:%M:%S', 'title' => $txt['timeformat_easy2']),
					array('format' => '%Y-%m-%d, %H:%M:%S', 'title' => $txt['timeformat_easy3']),
					array('format' => '%d %B %Y, %H:%M:%S', 'title' => $txt['timeformat_easy4']),
					array('format' => '%d-%m-%Y, %H:%M:%S', 'title' => $txt['timeformat_easy5'])
				);

				$context['member']['time_format'] = $cur_profile['time_format'];
				$context['current_forum_time'] = timeformat(time() - $user_info['time_offset'] * 3600, false);
				$context['current_forum_time_js'] = strftime('%Y,' . ((int) strftime('%m', time() + $modSettings['time_offset'] * 3600) - 1) . ',%d,%H,%M,%S', time() + $modSettings['time_offset'] * 3600);
				$context['current_forum_time_hour'] = (int) strftime('%H', forum_time(false));
				return true;
			},
		),
		'timezone' => array(
			'type' => 'select',
			'options' => smf_list_timezones(),
			'disabled_options' => array_filter(array_keys(smf_list_timezones()), 'is_int'),
			'permission' => 'profile_extra',
			'label' => $txt['timezone'],
			'input_validate' => function($value)
			{
				$tz = smf_list_timezones();
				if (!isset($tz[$value]))
					return 'bad_timezone';

				return true;
			},
		),
		'usertitle' => array(
			'type' => 'text',
			'label' => $txt['custom_title'],
			'log_change' => true,
			'input_attr' => array('maxlength="50"'),
			'size' => 50,
			'permission' => 'profile_title',
			'enabled' => !empty($modSettings['titlesEnable']),
			'input_validate' => function(&$value) use ($smcFunc)
			{
				if ($smcFunc['strlen']($value) > 50)
					return 'user_title_too_long';

				return true;
			},
		),
		'website_title' => array(
			'type' => 'text',
			'label' => $txt['website_title'],
			'subtext' => $txt['include_website_url'],
			'size' => 50,
			'permission' => 'profile_website',
			'link_with' => 'website',
		),
		'website_url' => array(
			'type' => 'url',
			'label' => $txt['website_url'],
			'subtext' => $txt['complete_url'],
			'size' => 50,
			'permission' => 'profile_website',
			// Fix the URL...
			'input_validate' => function(&$value)
			{
				if (strlen(trim($value)) > 0 && strpos($value, '://') === false)
					$value = 'http://' . $value;
				if (strlen($value) < 8 || (substr($value, 0, 7) !== 'http://' && substr($value, 0, 8) !== 'https://'))
					$value = '';
				$value = (string) validate_iri(sanitize_iri($value));
				return true;
			},
			'link_with' => 'website',
		),
	);

	call_integration_hook('integrate_load_profile_fields', array(&$profile_fields));

	$disabled_fields = !empty($modSettings['disabled_profile_fields']) ? explode(',', $modSettings['disabled_profile_fields']) : array();
	// For each of the above let's take out the bits which don't apply - to save memory and security!
	foreach ($profile_fields as $key => $field)
	{
		// Do we have permission to do this?
		if (isset($field['permission']) && !allowedTo(($context['user']['is_owner'] ? array($field['permission'] . '_own', $field['permission'] . '_any') : $field['permission'] . '_any')) && !allowedTo($field['permission']))
			unset($profile_fields[$key]);

		// Is it enabled?
		if (isset($field['enabled']) && !$field['enabled'])
			unset($profile_fields[$key]);

		// Is it specifically disabled?
		if (in_array($key, $disabled_fields) || (isset($field['link_with']) && in_array($field['link_with'], $disabled_fields)))
			unset($profile_fields[$key]);
	}
}

/**
 * Setup the context for a page load!
 *
 * @param array $fields The profile fields to display. Each item should correspond to an item in the $profile_fields array generated by loadProfileFields
 */
function setupProfileContext($fields)
{
	global $profile_fields, $context, $cur_profile, $txt;

	// Some default bits.
	$context['profile_prehtml'] = '';
	$context['profile_posthtml'] = '';
	$context['profile_javascript'] = '';
	$context['profile_onsubmit_javascript'] = '';

	call_integration_hook('integrate_setup_profile_context', array(&$fields));

	// Make sure we have this!
	loadProfileFields(true);

	// First check for any linked sets.
	foreach ($profile_fields as $key => $field)
		if (isset($field['link_with']) && in_array($field['link_with'], $fields))
			$fields[] = $key;

	$i = 0;
	$last_type = '';
	foreach ($fields as $key => $field)
	{
		if (isset($profile_fields[$field]))
		{
			// Shortcut.
			$cur_field = &$profile_fields[$field];

			// Does it have a preload and does that preload succeed?
			if (isset($cur_field['preload']) && !$cur_field['preload']())
				continue;

			// If this is anything but complex we need to do more cleaning!
			if ($cur_field['type'] != 'callback' && $cur_field['type'] != 'hidden')
			{
				if (!isset($cur_field['label']))
					$cur_field['label'] = isset($txt[$field]) ? $txt[$field] : $field;

				// Everything has a value!
				if (!isset($cur_field['value']))
					$cur_field['value'] = isset($cur_profile[$field]) ? $cur_profile[$field] : '';

				// Any input attributes?
				$cur_field['input_attr'] = !empty($cur_field['input_attr']) ? implode(',', $cur_field['input_attr']) : '';
			}

			// Was there an error with this field on posting?
			if (isset($context['profile_errors'][$field]))
				$cur_field['is_error'] = true;

			// Any javascript stuff?
			if (!empty($cur_field['js_submit']))
				$context['profile_onsubmit_javascript'] .= $cur_field['js_submit'];
			if (!empty($cur_field['js']))
				$context['profile_javascript'] .= $cur_field['js'];

			// Any template stuff?
			if (!empty($cur_field['prehtml']))
				$context['profile_prehtml'] .= $cur_field['prehtml'];
			if (!empty($cur_field['posthtml']))
				$context['profile_posthtml'] .= $cur_field['posthtml'];

			// Finally put it into context?
			if ($cur_field['type'] != 'hidden')
			{
				$last_type = $cur_field['type'];
				$context['profile_fields'][$field] = &$profile_fields[$field];
			}
		}
		// Bodge in a line break - without doing two in a row ;)
		elseif ($field == 'hr' && $last_type != 'hr' && $last_type != '')
		{
			$last_type = 'hr';
			$context['profile_fields'][$i++]['type'] = 'hr';
		}
	}

	// Some spicy JS.
	addInlineJavaScript('
	var form_handle = document.forms.creator;
	createEventListener(form_handle);
	'. (!empty($context['require_password']) ? '
	form_handle.addEventListener(\'submit\', function(event)
	{
		if (this.oldpasswrd.value == "")
		{
			event.preventDefault();
			alert('. (JavaScriptEscape($txt['required_security_reasons'])) . ');
			return false;
		}
	}, false);' : ''), true);

	// Any onsubmit javascript?
	if (!empty($context['profile_onsubmit_javascript']))
		addInlineJavaScript($context['profile_onsubmit_javascript'], true);

	// Any totally custom stuff?
	if (!empty($context['profile_javascript']))
		addInlineJavaScript($context['profile_javascript'], true);

	// Free up some memory.
	unset($profile_fields);
}

/**
 * Save the profile changes.
 */
function saveProfileFields()
{
	global $profile_fields, $profile_vars, $context, $old_profile, $post_errors, $cur_profile;

	// Load them up.
	loadProfileFields();

	// This makes things easier...
	$old_profile = $cur_profile;

	// This allows variables to call activities when they save - by default just to reload their settings
	$context['profile_execute_on_save'] = array();
	if ($context['user']['is_owner'])
		$context['profile_execute_on_save']['reload_user'] = 'profileReloadUser';

	// Assume we log nothing.
	$context['log_changes'] = array();

	// Cycle through the profile fields working out what to do!
	foreach ($profile_fields as $key => $field)
	{
		if (!isset($_POST[$key]) || !empty($field['is_dummy']) || (isset($_POST['preview_signature']) && $key == 'signature'))
			continue;

		// What gets updated?
		$db_key = isset($field['save_key']) ? $field['save_key'] : $key;

		// Right - we have something that is enabled, we can act upon and has a value posted to it. Does it have a validation function?
		if (isset($field['input_validate']))
		{
			$is_valid = $field['input_validate']($_POST[$key]);
			// An error occurred - set it as such!
			if ($is_valid !== true)
			{
				// Is this an actual error?
				if ($is_valid !== false)
				{
					$post_errors[$key] = $is_valid;
					$profile_fields[$key]['is_error'] = $is_valid;
				}
				// Retain the old value.
				$cur_profile[$key] = $_POST[$key];
				continue;
			}
		}

		// Are we doing a cast?
		$field['cast_type'] = empty($field['cast_type']) ? $field['type'] : $field['cast_type'];

		// Finally, clean up certain types.
		if ($field['cast_type'] == 'int')
			$_POST[$key] = (int) $_POST[$key];
		elseif ($field['cast_type'] == 'float')
			$_POST[$key] = (float) $_POST[$key];
		elseif ($field['cast_type'] == 'check')
			$_POST[$key] = !empty($_POST[$key]) ? 1 : 0;

		// If we got here we're doing OK.
		if ($field['type'] != 'hidden' && (!isset($old_profile[$key]) || $_POST[$key] != $old_profile[$key]))
		{
			// Set the save variable.
			$profile_vars[$db_key] = $_POST[$key];
			// And update the user profile.
			$cur_profile[$key] = $_POST[$key];

			// Are we logging it?
			if (!empty($field['log_change']) && isset($old_profile[$key]))
				$context['log_changes'][$key] = array(
					'previous' => $old_profile[$key],
					'new' => $_POST[$key],
				);
		}

		// Logging group changes are a bit different...
		if ($key == 'id_group' && $field['log_change'])
		{
			profileLoadGroups();

			// Any changes to primary group?
			if ($_POST['id_group'] != $old_profile['id_group'])
			{
				$context['log_changes']['id_group'] = array(
					'previous' => !empty($old_profile[$key]) && isset($context['member_groups'][$old_profile[$key]]) ? $context['member_groups'][$old_profile[$key]]['name'] : '',
					'new' => !empty($_POST[$key]) && isset($context['member_groups'][$_POST[$key]]) ? $context['member_groups'][$_POST[$key]]['name'] : '',
				);
			}

			// Prepare additional groups for comparison.
			$additional_groups = array(
				'previous' => !empty($old_profile['additional_groups']) ? explode(',', $old_profile['additional_groups']) : array(),
				'new' => !empty($_POST['additional_groups']) ? array_diff($_POST['additional_groups'], array(0)) : array(),
			);

			sort($additional_groups['previous']);
			sort($additional_groups['new']);

			// What about additional groups?
			if ($additional_groups['previous'] != $additional_groups['new'])
			{
				foreach ($additional_groups as $type => $groups)
				{
					foreach ($groups as $id => $group)
					{
						if (isset($context['member_groups'][$group]))
							$additional_groups[$type][$id] = $context['member_groups'][$group]['name'];
						else
							unset($additional_groups[$type][$id]);
					}
					$additional_groups[$type] = implode(', ', $additional_groups[$type]);
				}

				$context['log_changes']['additional_groups'] = $additional_groups;
			}
		}
	}

	// @todo Temporary
	if ($context['user']['is_owner'])
		$changeOther = allowedTo(array('profile_extra_any', 'profile_extra_own'));
	else
		$changeOther = allowedTo('profile_extra_any');
	if ($changeOther && empty($post_errors))
	{
		makeThemeChanges($context['id_member'], isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_profile['id_theme']);
		if (!empty($_REQUEST['sa']))
		{
			$custom_fields_errors = makeCustomFieldChanges($context['id_member'], $_REQUEST['sa'], false, true);

			if (!empty($custom_fields_errors))
				$post_errors = array_merge($post_errors, $custom_fields_errors);
		}
	}

	// Free memory!
	unset($profile_fields);
}

/**
 * Save the profile changes
 *
 * @param array &$profile_vars The items to save
 * @param array &$post_errors An array of information about any errors that occurred
 * @param int $memID The ID of the member whose profile we're saving
 */
function saveProfileChanges(&$profile_vars, &$post_errors, $memID)
{
	global $user_profile, $context;

	// These make life easier....
	$old_profile = &$user_profile[$memID];

	// Permissions...
	if ($context['user']['is_owner'])
	{
		$changeOther = allowedTo(array('profile_extra_any', 'profile_extra_own', 'profile_website_any', 'profile_website_own', 'profile_signature_any', 'profile_signature_own'));
	}
	else
		$changeOther = allowedTo(array('profile_extra_any', 'profile_website_any', 'profile_signature_any'));

	// Arrays of all the changes - makes things easier.
	$profile_bools = array();
	$profile_ints = array();
	$profile_floats = array();
	$profile_strings = array(
		'buddy_list',
		'ignore_boards',
	);

	if (isset($_POST['sa']) && $_POST['sa'] == 'ignoreboards' && empty($_POST['ignore_brd']))
		$_POST['ignore_brd'] = array();

	unset($_POST['ignore_boards']); // Whatever it is set to is a dirty filthy thing.  Kinda like our minds.
	if (isset($_POST['ignore_brd']))
	{
		if (!is_array($_POST['ignore_brd']))
			$_POST['ignore_brd'] = array($_POST['ignore_brd']);

		foreach ($_POST['ignore_brd'] as $k => $d)
		{
			$d = (int) $d;
			if ($d != 0)
				$_POST['ignore_brd'][$k] = $d;
			else
				unset($_POST['ignore_brd'][$k]);
		}
		$_POST['ignore_boards'] = implode(',', $_POST['ignore_brd']);
		unset($_POST['ignore_brd']);
	}

	// Here's where we sort out all the 'other' values...
	if ($changeOther)
	{
		makeThemeChanges($memID, isset($_POST['id_theme']) ? (int) $_POST['id_theme'] : $old_profile['id_theme']);
		//makeAvatarChanges($memID, $post_errors);

		if (!empty($_REQUEST['sa']))
			makeCustomFieldChanges($memID, $_REQUEST['sa'], false);

		foreach ($profile_bools as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = empty($_POST[$var]) ? '0' : '1';
		foreach ($profile_ints as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var] != '' ? (int) $_POST[$var] : '';
		foreach ($profile_floats as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = (float) $_POST[$var];
		foreach ($profile_strings as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var];
	}
}

/**
 * Make any theme changes that are sent with the profile.
 *
 * @param int $memID The ID of the user
 * @param int $id_theme The ID of the theme
 */
function makeThemeChanges($memID, $id_theme)
{
	global $modSettings, $smcFunc, $context, $user_info;

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
	if ((isset($_POST['options']) && count(array_intersect(array_keys($_POST['options']), $reservedVars)) != 0) || (isset($_POST['default_options']) && count(array_intersect(array_keys($_POST['default_options']), $reservedVars)) != 0))
		fatal_lang_error('no_access', false);

	// Don't allow any overriding of custom fields with default or non-default options.
	$request = $smcFunc['db_query']('', '
		SELECT col_name
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}',
		array(
			'is_active' => 1,
		)
	);
	$custom_fields = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$custom_fields[] = $row['col_name'];
	$smcFunc['db_free_result']($request);

	// These are the theme changes...
	$themeSetArray = array();
	if (isset($_POST['options']) && is_array($_POST['options']))
	{
		foreach ($_POST['options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
				$val = max(0, min($val, 50));
			// We don't set this per theme anymore.
			elseif ($opt == 'allow_no_censored')
				continue;

			$themeSetArray[] = array($memID, $id_theme, $opt, is_array($val) ? implode(',', $val) : $val);
		}
	}

	$erase_options = array();
	if (isset($_POST['default_options']) && is_array($_POST['default_options']))
		foreach ($_POST['default_options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
				$val = max(0, min($val, 50));
			// Only let admins and owners change the censor.
			elseif ($opt == 'allow_no_censored' && !$user_info['is_admin'] && !$context['user']['is_owner'])
					continue;

			$themeSetArray[] = array($memID, 1, $opt, is_array($val) ? implode(',', $val) : $val);
			$erase_options[] = $opt;
		}

	// If themeSetArray isn't still empty, send it to the database.
	if (empty($context['password_auth_failed']))
	{
		if (!empty($themeSetArray))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$themeSetArray,
				array('id_member', 'id_theme', 'variable')
			);
		}

		if (!empty($erase_options))
		{
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}themes
				WHERE id_theme != {int:id_theme}
					AND variable IN ({array_string:erase_variables})
					AND id_member = {int:id_member}',
				array(
					'id_theme' => 1,
					'id_member' => $memID,
					'erase_variables' => $erase_options
				)
			);
		}

		// Admins can choose any theme, even if it's not enabled...
		$themes = allowedTo('admin_forum') ? explode(',', $modSettings['knownThemes']) : explode(',', $modSettings['enableThemes']);
		foreach ($themes as $t)
			cache_put_data('theme_settings-' . $t . ':' . $memID, null, 60);
	}
}

/**
 * Make any notification changes that need to be made.
 *
 * @param int $memID The ID of the member
 */
function makeNotificationChanges($memID)
{
	global $smcFunc, $sourcedir;

	require_once($sourcedir . '/Subs-Notify.php');

	// Update the boards they are being notified on.
	if (isset($_POST['edit_notify_boards']) && !empty($_POST['notify_boards']))
	{
		// Make sure only integers are deleted.
		foreach ($_POST['notify_boards'] as $index => $id)
			$_POST['notify_boards'][$index] = (int) $id;

		// id_board = 0 is reserved for topic notifications.
		$_POST['notify_boards'] = array_diff($_POST['notify_boards'], array(0));

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:selected_member}',
			array(
				'board_list' => $_POST['notify_boards'],
				'selected_member' => $memID,
			)
		);
	}

	// We are editing topic notifications......
	elseif (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics']))
	{
		foreach ($_POST['notify_topics'] as $index => $id)
			$_POST['notify_topics'][$index] = (int) $id;

		// Make sure there are no zeros left.
		$_POST['notify_topics'] = array_diff($_POST['notify_topics'], array(0));

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:selected_member}',
			array(
				'topic_list' => $_POST['notify_topics'],
				'selected_member' => $memID,
			)
		);
		foreach ($_POST['notify_topics'] as $topic)
			setNotifyPrefs($memID, array('topic_notify_' . $topic => 0));
	}

	// We are removing topic preferences
	elseif (isset($_POST['remove_notify_topics']) && !empty($_POST['notify_topics']))
	{
		$prefs = array();
		foreach ($_POST['notify_topics'] as $topic)
			$prefs[] = 'topic_notify_' . $topic;
		deleteNotifyPrefs($memID, $prefs);
	}

	// We are removing board preferences
	elseif (isset($_POST['remove_notify_board']) && !empty($_POST['notify_boards']))
	{
		$prefs = array();
		foreach ($_POST['notify_boards'] as $board)
			$prefs[] = 'board_notify_' . $board;
		deleteNotifyPrefs($memID, $prefs);
	}
}

/**
 * Save any changes to the custom profile fields
 *
 * @param int $memID The ID of the member
 * @param string $area The area of the profile these fields are in
 * @param bool $sanitize = true Whether or not to sanitize the data
 * @param bool $returnErrors Whether or not to return any error information
 * @return void|array Returns nothing or returns an array of error info if $returnErrors is true
 */
function makeCustomFieldChanges($memID, $area, $sanitize = true, $returnErrors = false)
{
	global $context, $smcFunc, $user_profile, $user_info, $modSettings;
	global $sourcedir;

	$errors = array();

	if ($sanitize && isset($_POST['customfield']))
		$_POST['customfield'] = htmlspecialchars__recursive($_POST['customfield']);

	$where = $area == 'register' ? 'show_reg != 0' : 'show_profile = {string:area}';

	// Load the fields we are saving too - make sure we save valid data (etc).
	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_desc, field_type, field_length, field_options, default_value, show_reg, mask, private
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
			AND active = {int:is_active}',
		array(
			'is_active' => 1,
			'area' => $area,
		)
	);
	$changes = array();
	$deletes = array();
	$log_changes = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		/* This means don't save if:
			- The user is NOT an admin.
			- The data is not freely viewable and editable by users.
			- The data is not invisible to users but editable by the owner (or if it is the user is not the owner)
			- The area isn't registration, and if it is that the field is not supposed to be shown there.
		*/
		if ($row['private'] != 0 && !allowedTo('admin_forum') && ($memID != $user_info['id'] || $row['private'] != 2) && ($area != 'register' || $row['show_reg'] == 0))
			continue;

		// Validate the user data.
		if ($row['field_type'] == 'check')
			$value = isset($_POST['customfield'][$row['col_name']]) ? 1 : 0;
		elseif ($row['field_type'] == 'select' || $row['field_type'] == 'radio')
		{
			$value = $row['default_value'];
			foreach (explode(',', $row['field_options']) as $k => $v)
				if (isset($_POST['customfield'][$row['col_name']]) && $_POST['customfield'][$row['col_name']] == $k)
					$value = $v;
		}
		// Otherwise some form of text!
		else
		{
			$value = isset($_POST['customfield'][$row['col_name']]) ? $_POST['customfield'][$row['col_name']] : '';

			if ($row['field_length'])
				$value = $smcFunc['substr']($value, 0, $row['field_length']);

			// Any masks?
			if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
			{
				$value = $smcFunc['htmltrim']($value);
				$valueReference = un_htmlspecialchars($value);

				// Try and avoid some checks. '0' could be a valid non-empty value.
				if (empty($value) && !is_numeric($value))
					$value = '';

				if ($row['mask'] == 'nohtml' && ($valueReference != strip_tags($valueReference) || $value != filter_var($value, FILTER_SANITIZE_STRING) || preg_match('/<(.+?)[\s]*\/?[\s]*>/si', $valueReference)))
				{
					if ($returnErrors)
						$errors[] = 'custom_field_nohtml_fail';

					else
						$value = '';
				}
				elseif ($row['mask'] == 'email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 255))
				{
					if ($returnErrors)
						$errors[] = 'custom_field_mail_fail';

					else
						$value = '';
				}
				elseif ($row['mask'] == 'number')
				{
					$value = (int) $value;
				}
				elseif (substr($row['mask'], 0, 5) == 'regex' && trim($value) != '' && preg_match(substr($row['mask'], 5), $value) === 0)
				{
					if ($returnErrors)
						$errors[] = 'custom_field_regex_fail';

					else
						$value = '';
				}

				unset($valueReference);
			}
		}

		// Did it change?
		if (!isset($user_profile[$memID]['options'][$row['col_name']]) || $user_profile[$memID]['options'][$row['col_name']] !== $value)
		{
			$log_changes[] = array(
				'action' => 'customfield_' . $row['col_name'],
				'log_type' => 'user',
				'extra' => array(
					'previous' => !empty($user_profile[$memID]['options'][$row['col_name']]) ? $user_profile[$memID]['options'][$row['col_name']] : '',
					'new' => $value,
					'applicator' => $user_info['id'],
					'member_affected' => $memID,
				),
			);
			if (empty($value))
			{
				$deletes = array('id_theme' => 1 , 'variable' => $row['col_name'], 'id_member' => $memID);
				unset($user_profile[$memID]['options'][$row['col_name']]);
			}
			else
			{
				$changes[] = array(1, $row['col_name'], $value, $memID);
				$user_profile[$memID]['options'][$row['col_name']] = $value;
			}
		}
	}
	$smcFunc['db_free_result']($request);

	$hook_errors = call_integration_hook('integrate_save_custom_profile_fields', array(&$changes, &$log_changes, &$errors, $returnErrors, $memID, $area, $sanitize, &$deletes));

	if (!empty($hook_errors) && is_array($hook_errors))
		$errors = array_merge($errors, $hook_errors);

	// Make those changes!
	if ((!empty($changes) || !empty($deletes)) && empty($context['password_auth_failed']) && empty($errors))
	{
		if (!empty($changes))
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534', 'id_member' => 'int'),
				$changes,
				array('id_theme', 'variable', 'id_member')
			);
		if (!empty($deletes))
			$smcFunc['db_query']('','
				DELETE FROM {db_prefix}themes
				WHERE id_theme = {int:id_theme} AND
						variable = {string:variable} AND
						id_member = {int:id_member}',
				$deletes
				);
		if (!empty($log_changes) && !empty($modSettings['modlog_enabled']))
		{
			require_once($sourcedir . '/Logging.php');
			logActions($log_changes);
		}
	}

	if ($returnErrors)
		return $errors;
}

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 * @param int $memID The ID of the member
 */
function editBuddyIgnoreLists($memID)
{
	global $context, $txt, $modSettings;

	// Do a quick check to ensure people aren't getting here illegally!
	if (!$context['user']['is_owner'] || empty($modSettings['enable_buddylist']))
		fatal_lang_error('no_access', false);

	// Can we email the user direct?
	$context['can_moderate_forum'] = allowedTo('moderate_forum');
	$context['can_send_email'] = allowedTo('moderate_forum');

	$subActions = array(
		'buddies' => array('editBuddies', $txt['editBuddies']),
		'ignore' => array('editIgnoreList', $txt['editIgnoreList']),
	);

	$context['list_area'] = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : 'buddies';

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['editBuddyIgnoreLists'],
		'description' => $txt['buddy_ignore_desc'],
		'icon' => 'profile_hd.png',
		'tabs' => array(
			'buddies' => array(),
			'ignore' => array(),
		),
	);

	loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');

	// Pass on to the actual function.
	$context['sub_template'] = $subActions[$context['list_area']][0];
	$call = call_helper($subActions[$context['list_area']][0], true);

	if (!empty($call))
		call_user_func($call, $memID);
}

/**
 * Show all the users buddies, as well as a add/delete interface.
 *
 * @param int $memID The ID of the member
 */
function editBuddies($memID)
{
	global $txt, $scripturl, $settings;
	global $context, $user_profile, $memberContext, $smcFunc;

	// For making changes!
	$buddiesArray = explode(',', $user_profile[$memID]['buddy_list']);
	foreach ($buddiesArray as $k => $dummy)
		if ($dummy == '')
			unset($buddiesArray[$k]);

	// Removing a buddy?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		call_integration_hook('integrate_remove_buddy', array($memID));

		$_SESSION['prf-save'] = $txt['could_not_remove_person'];

		// Heh, I'm lazy, do it the easy way...
		foreach ($buddiesArray as $key => $buddy)
			if ($buddy == (int) $_GET['remove'])
			{
				unset($buddiesArray[$key]);
				$_SESSION['prf-save'] = true;
			}

		// Make the changes.
		$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
		updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
	}
	elseif (isset($_POST['new_buddy']))
	{
		checkSession();

		// Prepare the string for extraction...
		$_POST['new_buddy'] = strtr($smcFunc['htmlspecialchars']($_POST['new_buddy'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_buddy'], $matches);
		$new_buddies = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_buddy']))));

		foreach ($new_buddies as $k => $dummy)
		{
			$new_buddies[$k] = strtr(trim($new_buddies[$k]), array('\'' => '&#039;'));

			if (strlen($new_buddies[$k]) == 0 || in_array($new_buddies[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
				unset($new_buddies[$k]);
		}

		call_integration_hook('integrate_add_buddies', array($memID, &$new_buddies));

		$_SESSION['prf-save'] = $txt['could_not_add_person'];
		if (!empty($new_buddies))
		{
			// Now find out the id_member of the buddy.
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_buddies}) OR real_name IN ({array_string:new_buddies})
				LIMIT {int:count_new_buddies}',
				array(
					'new_buddies' => $new_buddies,
					'count_new_buddies' => count($new_buddies),
				)
			);

			if ($smcFunc['db_num_rows']($request) != 0)
				$_SESSION['prf-save'] = true;

			// Add the new member to the buddies array.
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (in_array($row['id_member'], $buddiesArray))
					continue;
				else
					$buddiesArray[] = (int) $row['id_member'];
			}
			$smcFunc['db_free_result']($request);

			// Now update the current users buddy list.
			$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
			updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));
		}

		// Back to the buddy list!
		redirectexit('action=profile;area=lists;sa=buddies;u=' . $memID);
	}

	// Get all the users "buddies"...
	$buddies = array();

	// Gotta load the custom profile fields names.
	$request = $smcFunc['db_query']('', '
		SELECT col_name, field_name, field_desc, field_type, bbc, enclose
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			AND private < {int:private_level}',
		array(
			'active' => 1,
			'private_level' => 2,
		)
	);

	$context['custom_pf'] = array();
	$disabled_fields = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		if (!isset($disabled_fields[$row['col_name']]))
			$context['custom_pf'][$row['col_name']] = array(
				'label' => $row['field_name'],
				'type' => $row['field_type'],
				'bbc' => !empty($row['bbc']),
				'enclose' => $row['enclose'],
			);

	// Gotta disable the gender option.
	if (isset($context['custom_pf']['cust_gender']) && $context['custom_pf']['cust_gender'] == 'None')
		unset($context['custom_pf']['cust_gender']);

	$smcFunc['db_free_result']($request);

	if (!empty($buddiesArray))
	{
		$result = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:buddy_list})
			ORDER BY real_name
			LIMIT {int:buddy_list_count}',
			array(
				'buddy_list' => $buddiesArray,
				'buddy_list_count' => substr_count($user_profile[$memID]['buddy_list'], ',') + 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
			$buddies[] = $row['id_member'];
		$smcFunc['db_free_result']($result);
	}

	$context['buddy_count'] = count($buddies);

	// Load all the members up.
	loadMemberData($buddies, false, 'profile');

	// Setup the context for each buddy.
	$context['buddies'] = array();
	foreach ($buddies as $buddy)
	{
		loadMemberContext($buddy);
		$context['buddies'][$buddy] = $memberContext[$buddy];

		// Make sure to load the appropriate fields for each user
		if (!empty($context['custom_pf']))
		{
			foreach ($context['custom_pf'] as $key => $column)
			{
				// Don't show anything if there isn't anything to show.
				if (!isset($context['buddies'][$buddy]['options'][$key]))
				{
					$context['buddies'][$buddy]['options'][$key] = '';
					continue;
				}

				if ($column['bbc'] && !empty($context['buddies'][$buddy]['options'][$key]))
					$context['buddies'][$buddy]['options'][$key] = strip_tags(parse_bbc($context['buddies'][$buddy]['options'][$key]));

				elseif ($column['type'] == 'check')
					$context['buddies'][$buddy]['options'][$key] = $context['buddies'][$buddy]['options'][$key] == 0 ? $txt['no'] : $txt['yes'];

				// Enclosing the user input within some other text?
				if (!empty($column['enclose']) && !empty($context['buddies'][$buddy]['options'][$key]))
					$context['buddies'][$buddy]['options'][$key] = strtr($column['enclose'], array(
						'{SCRIPTURL}' => $scripturl,
						'{IMAGES_URL}' => $settings['images_url'],
						'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
						'{INPUT}' => $context['buddies'][$buddy]['options'][$key],
					));
			}
		}
	}

	if (isset($_SESSION['prf-save']))
	{
		if ($_SESSION['prf-save'] === true)
			$context['saved_successful'] = true;
		else
			$context['saved_failed'] = $_SESSION['prf-save'];

		unset($_SESSION['prf-save']);
	}

	call_integration_hook('integrate_view_buddies', array($memID));
}

/**
 * Allows the user to view their ignore list, as well as the option to manage members on it.
 *
 * @param int $memID The ID of the member
 */
function editIgnoreList($memID)
{
	global $txt;
	global $context, $user_profile, $memberContext, $smcFunc;

	// For making changes!
	$ignoreArray = explode(',', $user_profile[$memID]['pm_ignore_list']);
	foreach ($ignoreArray as $k => $dummy)
		if ($dummy == '')
			unset($ignoreArray[$k]);

	// Removing a member from the ignore list?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		$_SESSION['prf-save'] = $txt['could_not_remove_person'];

		// Heh, I'm lazy, do it the easy way...
		foreach ($ignoreArray as $key => $id_remove)
			if ($id_remove == (int) $_GET['remove'])
			{
				unset($ignoreArray[$key]);
				$_SESSION['prf-save'] = true;
			}

		// Make the changes.
		$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
		updateMemberData($memID, array('pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']));

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
	}
	elseif (isset($_POST['new_ignore']))
	{
		checkSession();
		// Prepare the string for extraction...
		$_POST['new_ignore'] = strtr($smcFunc['htmlspecialchars']($_POST['new_ignore'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_ignore'], $matches);
		$new_entries = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_ignore']))));

		foreach ($new_entries as $k => $dummy)
		{
			$new_entries[$k] = strtr(trim($new_entries[$k]), array('\'' => '&#039;'));

			if (strlen($new_entries[$k]) == 0 || in_array($new_entries[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
				unset($new_entries[$k]);
		}

		$_SESSION['prf-save'] = $txt['could_not_add_person'];
		if (!empty($new_entries))
		{
			// Now find out the id_member for the members in question.
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_entries}) OR real_name IN ({array_string:new_entries})
				LIMIT {int:count_new_entries}',
				array(
					'new_entries' => $new_entries,
					'count_new_entries' => count($new_entries),
				)
			);

			if ($smcFunc['db_num_rows']($request) != 0)
				$_SESSION['prf-save'] = true;

			// Add the new member to the buddies array.
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (in_array($row['id_member'], $ignoreArray))
					continue;
				else
					$ignoreArray[] = (int) $row['id_member'];
			}
			$smcFunc['db_free_result']($request);

			// Now update the current users buddy list.
			$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
			updateMemberData($memID, array('pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']));
		}

		// Back to the list of pityful people!
		redirectexit('action=profile;area=lists;sa=ignore;u=' . $memID);
	}

	// Initialise the list of members we're ignoring.
	$ignored = array();

	if (!empty($ignoreArray))
	{
		$result = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:ignore_list})
			ORDER BY real_name
			LIMIT {int:ignore_list_count}',
			array(
				'ignore_list' => $ignoreArray,
				'ignore_list_count' => substr_count($user_profile[$memID]['pm_ignore_list'], ',') + 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
			$ignored[] = $row['id_member'];
		$smcFunc['db_free_result']($result);
	}

	$context['ignore_count'] = count($ignored);

	// Load all the members up.
	loadMemberData($ignored, false, 'profile');

	// Setup the context for each buddy.
	$context['ignore_list'] = array();
	foreach ($ignored as $ignore_member)
	{
		loadMemberContext($ignore_member);
		$context['ignore_list'][$ignore_member] = $memberContext[$ignore_member];
	}

	if (isset($_SESSION['prf-save']))
	{
		if ($_SESSION['prf-save'] === true)
			$context['saved_successful'] = true;
		else
			$context['saved_failed'] = $_SESSION['prf-save'];

		unset($_SESSION['prf-save']);
	}
}

/**
 * Handles the account section of the profile
 *
 * @param int $memID The ID of the member
 */
function account($memID)
{
	global $context, $txt;

	loadThemeOptions($memID);
	if (allowedTo(array('profile_identity_own', 'profile_identity_any', 'profile_password_own', 'profile_password_any')))
		loadCustomFields($memID, 'account');

	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['account_info'];

	setupProfileContext(
		array(
			'member_name', 'real_name', 'date_registered', 'posts', 'lngfile', 'hr',
			'id_group', 'hr',
			'email_address', 'show_online', 'hr',
			'tfa', 'hr',
			'passwrd1', 'passwrd2', 'hr',
			'secret_question', 'secret_answer',
		)
	);
}

/**
 * Handles the main "Forum Profile" section of the profile
 *
 * @param int $memID The ID of the member
 */
function forumProfile($memID)
{
	global $context, $txt;

	loadThemeOptions($memID);
	if (allowedTo(array('profile_forum_own', 'profile_forum_any')))
		loadCustomFields($memID, 'forumprofile');

	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['forumProfile_info'];
	$context['show_preview_button'] = true;

	setupProfileContext(
		array(
			'avatar_choice', 'hr', 'personal_text', 'hr',
			'bday1', 'usertitle', 'signature', 'hr',
			'website_title', 'website_url',
		)
	);
}

/**
 * Recursive function to retrieve server-stored avatar files
 *
 * @param string $directory The directory to look for files in
 * @param int $level How many levels we should go in the directory
 * @return array An array of information about the files and directories found
 */
function getAvatars($directory, $level)
{
	global $context, $txt, $modSettings, $smcFunc;

	$result = array();

	// Open the directory..
	$dir = dir($modSettings['avatar_directory'] . (!empty($directory) ? '/' : '') . $directory);
	$dirs = array();
	$files = array();

	if (!$dir)
		return array();

	while ($line = $dir->read())
	{
		if (in_array($line, array('.', '..', 'blank.png', 'index.php')))
			continue;

		if (is_dir($modSettings['avatar_directory'] . '/' . $directory . (!empty($directory) ? '/' : '') . $line))
			$dirs[] = $line;
		else
			$files[] = $line;
	}
	$dir->close();

	// Sort the results...
	natcasesort($dirs);
	natcasesort($files);

	if ($level == 0)
	{
		$result[] = array(
			'filename' => 'blank.png',
			'checked' => in_array($context['member']['avatar']['server_pic'], array('', 'blank.png')),
			'name' => $txt['no_pic'],
			'is_dir' => false
		);
	}

	foreach ($dirs as $line)
	{
		$tmp = getAvatars($directory . (!empty($directory) ? '/' : '') . $line, $level + 1);
		if (!empty($tmp))
			$result[] = array(
				'filename' => $smcFunc['htmlspecialchars']($line),
				'checked' => strpos($context['member']['avatar']['server_pic'], $line . '/') !== false,
				'name' => '[' . $smcFunc['htmlspecialchars'](str_replace('_', ' ', $line)) . ']',
				'is_dir' => true,
				'files' => $tmp
		);
		unset($tmp);
	}

	foreach ($files as $line)
	{
		$filename = substr($line, 0, (strlen($line) - strlen(strrchr($line, '.'))));
		$extension = substr(strrchr($line, '.'), 1);

		// Make sure it is an image.
		if (strcasecmp($extension, 'gif') != 0 && strcasecmp($extension, 'jpg') != 0 && strcasecmp($extension, 'jpeg') != 0 && strcasecmp($extension, 'png') != 0 && strcasecmp($extension, 'bmp') != 0)
			continue;

		$result[] = array(
			'filename' => $smcFunc['htmlspecialchars']($line),
			'checked' => $line == $context['member']['avatar']['server_pic'],
			'name' => $smcFunc['htmlspecialchars'](str_replace('_', ' ', $filename)),
			'is_dir' => false
		);
		if ($level == 1)
			$context['avatar_list'][] = $directory . '/' . $line;
	}

	return $result;
}

/**
 * Handles the "Look and Layout" section of the profile
 *
 * @param int $memID The ID of the member
 */
function theme($memID)
{
	global $txt, $context;

	loadTemplate('Settings');
	loadSubTemplate('options');

	// Let mods hook into the theme options.
	call_integration_hook('integrate_theme_options');

	loadThemeOptions($memID);
	if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
		loadCustomFields($memID, 'theme');

	$context['sub_template'] = 'edit_options';
	$context['page_desc'] = $txt['theme_info'];

	setupProfileContext(
		array(
			'id_theme', 'smiley_set', 'hr',
			'time_format', 'timezone', 'hr',
			'theme_settings',
		)
	);
}

/**
 * Display the notifications and settings for changes.
 *
 * @param int $memID The ID of the member
 */
function notification($memID)
{
	global $txt, $context;

	// Going to want this for consistency.
	loadCSSFile('admin.css', array(), 'smf_admin');

	// This is just a bootstrap for everything else.
	$sa = array(
		'alerts' => 'alert_configuration',
		'markread' => 'alert_markread',
		'topics' => 'alert_notifications_topics',
		'boards' => 'alert_notifications_boards',
	);

	$subAction = !empty($_GET['sa']) && isset($sa[$_GET['sa']]) ? $_GET['sa'] : 'alerts';

	$context['sub_template'] = $sa[$subAction];
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['notification'],
		'help' => '',
		'description' => $txt['notification_info'],
	);
	$sa[$subAction]($memID);
}

/**
 * Handles configuration of alert preferences
 *
 * @param int $memID The ID of the member
 */
function alert_configuration($memID)
{
	global $txt, $context, $modSettings, $smcFunc, $sourcedir;

	if (!isset($context['token_check']))
		$context['token_check'] = 'profile-nt' . $memID;

	is_not_guest();
	if (!$context['user']['is_owner'])
		isAllowedTo('profile_extra_any');

	// Set the post action if we're coming from the profile...
	if (!isset($context['action']))
		$context['action'] = 'action=profile;area=notification;sa=alerts;u=' . $memID;

	// What options are set
	loadThemeOptions($memID);
	loadJavaScriptFile('alertSettings.js', array('minimize' => true), 'smf_alertSettings');

	// Now load all the values for this user.
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memID, '', $memID != 0);

	$context['alert_prefs'] = !empty($prefs[$memID]) ? $prefs[$memID] : array();

	$context['member'] += array(
		'alert_timeout' => isset($context['alert_prefs']['alert_timeout']) ? $context['alert_prefs']['alert_timeout'] : 10,
		'notify_announcements' => isset($context['alert_prefs']['announcements']) ? $context['alert_prefs']['announcements'] : 0,
	);

	// Now for the exciting stuff.
	// We have groups of items, each item has both an alert and an email key as well as an optional help string.
	// Valid values for these keys are 'always', 'yes', 'never'; if using always or never you should add a help string.
	$alert_types = array(
		'board' => array(
			'topic_notify' => array('alert' => 'yes', 'email' => 'yes'),
			'board_notify' => array('alert' => 'yes', 'email' => 'yes'),
		),
		'msg' => array(
			'msg_mention' => array('alert' => 'yes', 'email' => 'yes'),
			'msg_quote' => array('alert' => 'yes', 'email' => 'yes'),
			'msg_like' => array('alert' => 'yes', 'email' => 'never'),
			'unapproved_reply' => array('alert' => 'yes', 'email' => 'yes'),
		),
		'pm' => array(
			'pm_new' => array('alert' => 'never', 'email' => 'yes', 'help' => 'alert_pm_new', 'permission' => array('name' => 'pm_read', 'is_board' => false)),
			'pm_reply' => array('alert' => 'never', 'email' => 'yes', 'help' => 'alert_pm_new', 'permission' => array('name' => 'pm_send', 'is_board' => false)),
		),
		'groupr' => array(
			'groupr_approved' => array('alert' => 'always', 'email' => 'yes'),
			'groupr_rejected' => array('alert' => 'always', 'email' => 'yes'),
		),
		'moderation' => array(
			'unapproved_attachment' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'approve_posts', 'is_board' => true)),
			'unapproved_post' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'approve_posts', 'is_board' => true)),
			'msg_report' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_board', 'is_board' => true)),
			'msg_report_reply' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_board', 'is_board' => true)),
			'member_report' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_forum', 'is_board' => false)),
			'member_report_reply' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_forum', 'is_board' => false)),
		),
		'members' => array(
			'member_register' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'moderate_forum', 'is_board' => false)),
			'request_group' => array('alert' => 'yes', 'email' => 'yes'),
			'warn_any' => array('alert' => 'yes', 'email' => 'yes', 'permission' => array('name' => 'issue_warning', 'is_board' => false)),
			'buddy_request'  => array('alert' => 'yes', 'email' => 'never'),
			'birthday'  => array('alert' => 'yes', 'email' => 'yes'),
		),
		'calendar' => array(
			'event_new' => array('alert' => 'yes', 'email' => 'yes', 'help' => 'alert_event_new'),
		),
		'paidsubs' => array(
			'paidsubs_expiring' => array('alert' => 'yes', 'email' => 'yes'),
		),
	);
	$group_options = array(
		'board' => array(
			array('check', 'msg_auto_notify', 'label' => 'after'),
			array('check', 'msg_receive_body', 'label' => 'after'),
			array('select', 'msg_notify_pref', 'label' => 'before', 'opts' => array(
				0 => $txt['alert_opt_msg_notify_pref_nothing'],
				1 => $txt['alert_opt_msg_notify_pref_instant'],
				2 => $txt['alert_opt_msg_notify_pref_first'],
				3 => $txt['alert_opt_msg_notify_pref_daily'],
				4 => $txt['alert_opt_msg_notify_pref_weekly'],
			)),
			array('select', 'msg_notify_type', 'label' => 'before', 'opts' => array(
				1 => $txt['notify_send_type_everything'],
				2 => $txt['notify_send_type_everything_own'],
				3 => $txt['notify_send_type_only_replies'],
				4 => $txt['notify_send_type_nothing'],
			)),
		),
		'pm' => array(
			array('select', 'pm_notify', 'label' => 'before', 'opts' => array(
				1 => $txt['email_notify_all'],
				2 => $txt['email_notify_buddies'],
			)),
		),
	);

	// There are certain things that are disabled at the group level.
	if (empty($modSettings['cal_enabled']))
		unset($alert_types['calendar']);

	// Disable paid subscriptions at group level if they're disabled
	if (empty($modSettings['paid_enabled']))
		unset($alert_types['paidsubs']);

	// Disable membergroup requests at group level if they're disabled
	if (empty($modSettings['show_group_membership']))
		unset($alert_types['groupr'], $alert_types['members']['request_group']);

	// Disable mentions if they're disabled
	if (empty($modSettings['enable_mentions']))
		unset($alert_types['msg']['msg_mention']);

	// Disable likes if they're disabled
	if (empty($modSettings['enable_likes']))
		unset($alert_types['msg']['msg_like']);

	// Disable buddy requests if they're disabled
	if (empty($modSettings['enable_buddylist']))
		unset($alert_types['members']['buddy_request']);

	// Now, now, we could pass this through global but we should really get into the habit of
	// passing content to hooks, not expecting hooks to splatter everything everywhere.
	call_integration_hook('integrate_alert_types', array(&$alert_types, &$group_options));

	// Now we have to do some permissions testing - but only if we're not loading this from the admin center
	if (!empty($memID))
	{
		require_once($sourcedir . '/Subs-Members.php');
		$perms_cache = array();
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}group_moderators
			WHERE id_member = {int:memID}',
			array(
				'memID' => $memID,
			)
		);

		list ($can_mod) = $smcFunc['db_fetch_row']($request);

		if (!isset($perms_cache['manage_membergroups']))
		{
			$members = membersAllowedTo('manage_membergroups');
			$perms_cache['manage_membergroups'] = in_array($memID, $members);
		}

		if (!($perms_cache['manage_membergroups'] || $can_mod != 0))
			unset($alert_types['members']['request_group']);

		foreach ($alert_types as $group => $items)
		{
			foreach ($items as $alert_key => $alert_value)
			{
				if (!isset($alert_value['permission']))
					continue;
				if (!isset($perms_cache[$alert_value['permission']['name']]))
				{
					$in_board = !empty($alert_value['permission']['is_board']) ? 0 : null;
					$members = membersAllowedTo($alert_value['permission']['name'], $in_board);
					$perms_cache[$alert_value['permission']['name']] = in_array($memID, $members);
				}

				if (!$perms_cache[$alert_value['permission']['name']])
					unset ($alert_types[$group][$alert_key]);
			}

			if (empty($alert_types[$group]))
				unset ($alert_types[$group]);
		}
	}

	// And finally, exporting it to be useful later.
	$context['alert_types'] = $alert_types;
	$context['alert_group_options'] = $group_options;

	$context['alert_bits'] = array(
		'alert' => 0x01,
		'email' => 0x02,
	);

	if (isset($_POST['notify_submit']))
	{
		checkSession();
		validateToken($context['token_check'], 'post');

		// We need to step through the list of valid settings and figure out what the user has set.
		$update_prefs = array();

		// Now the group level options
		foreach ($context['alert_group_options'] as $opt_group => $group)
		{
			foreach ($group as $this_option)
			{
				switch ($this_option[0])
				{
					case 'check':
						$update_prefs[$this_option[1]] = !empty($_POST['opt_' . $this_option[1]]) ? 1 : 0;
						break;
					case 'select':
						if (isset($_POST['opt_' . $this_option[1]], $this_option['opts'][$_POST['opt_' . $this_option[1]]]))
							$update_prefs[$this_option[1]] = $_POST['opt_' . $this_option[1]];
						else
						{
							// We didn't have a sane value. Let's grab the first item from the possibles.
							$keys = array_keys($this_option['opts']);
							$first = array_shift($keys);
							$update_prefs[$this_option[1]] = $first;
						}
						break;
				}
			}
		}

		// Now the individual options
		foreach ($context['alert_types'] as $alert_group => $items)
		{
			foreach ($items as $item_key => $this_options)
			{
				$this_value = 0;
				foreach ($context['alert_bits'] as $type => $bitvalue)
				{
					if ($this_options[$type] == 'yes' && !empty($_POST[$type . '_' . $item_key]) || $this_options[$type] == 'always')
						$this_value |= $bitvalue;
				}
				if (!isset($context['alert_prefs'][$item_key]) || $context['alert_prefs'][$item_key] != $this_value)
					$update_prefs[$item_key] = $this_value;
			}
		}

		if (!empty($_POST['opt_alert_timeout']))
			$update_prefs['alert_timeout'] = $context['member']['alert_timeout'] = (int) $_POST['opt_alert_timeout'];

		if (!empty($_POST['notify_announcements']))
			$update_prefs['announcements'] = $context['member']['notify_announcements'] = (int) $_POST['notify_announcements'];

		setNotifyPrefs((int) $memID, $update_prefs);
		foreach ($update_prefs as $pref => $value)
			$context['alert_prefs'][$pref] = $value;

		makeNotificationChanges($memID);

		$context['profile_updated'] = $txt['profile_updated_own'];
	}

	createToken($context['token_check'], 'post');
}

/**
 * Marks all alerts as read for the specified user
 *
 * @param int $memID The ID of the member
 */
function alert_markread($memID)
{
	global $context, $db_show_debug, $smcFunc;

	// We do not want to output debug information here.
	$db_show_debug = false;

	// We only want to output our little layer here.
	$context['template_layers'] = array();
	$context['sub_template'] = 'alerts_all_read';

	loadLanguage('Alerts');

	// Now we're all set up.
	is_not_guest();
	if (!$context['user']['is_owner'])
		fatal_error('no_access');

	checkSession('get');

	// Assuming we're here, mark everything as read and head back.
	// We only spit back the little layer because this should be called AJAXively.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}user_alerts
		SET is_read = {int:now}
		WHERE id_member = {int:current_member}
			AND is_read = 0',
		array(
			'now' => time(),
			'current_member' => $memID,
		)
	);

	updateMemberData($memID, array('alerts' => 0));
}

/**
 * Marks a group of alerts as un/read
 *
 * @param int $memID The user ID.
 * @param array|integer $toMark The ID of a single alert or an array of IDs. The function will convert single integers to arrays for better handling.
 * @param integer $read To mark as read or unread, 1 for read, 0 or any other value different than 1 for unread.
 * @return integer How many alerts remain unread
 */
function alert_mark($memID, $toMark, $read = 0)
{
	global $smcFunc;

	if (empty($toMark) || empty($memID))
		return false;

	$toMark = (array) $toMark;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}user_alerts
		SET is_read = {int:read}
		WHERE id_alert IN({array_int:toMark})',
		array(
			'read' => $read == 1 ? time() : 0,
			'toMark' => $toMark,
		)
	);

	// Gotta know how many unread alerts are left.
	$count = alert_count($memID, true);

	updateMemberData($memID, array('alerts' => $count));

	// Might want to know this.
	return $count;
}

/**
 * Deletes a single or a group of alerts by ID
 *
 * @param int|array The ID of a single alert to delete or an array containing the IDs of multiple alerts. The function will convert integers into an array for better handling.
 * @param bool|int $memID The user ID. Used to update the user unread alerts count.
 * @return void|int If the $memID param is set, returns the new amount of unread alerts.
 */
function alert_delete($toDelete, $memID = false)
{
	global $smcFunc;

	if (empty($toDelete))
		return false;

	$toDelete = (array) $toDelete;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}user_alerts
		WHERE id_alert IN({array_int:toDelete})',
		array(
			'toDelete' => $toDelete,
		)
	);

	// Gotta know how many unread alerts are left.
	if ($memID)
	{
		$count = alert_count($memID, true);

		updateMemberData($memID, array('alerts' => $count));

		// Might want to know this.
		return $count;
	}
}

/**
 * Counts how many alerts a user has - either unread or all depending on $unread
 * We can't use db_num_rows here, as we have to determine what boards the user can see
 * Possibly in future versions as database support for json is mainstream, we can simplify this.
 *
 * @param int $memID The user ID.
 * @param bool $unread Whether to only count unread alerts.
 * @return int The number of requested alerts
 */
function alert_count($memID, $unread = false)
{
	global $smcFunc, $user_info;

	if (empty($memID))
		return false;

	// We have to do this the slow way as to iterate over all possible boards the user can see.
	$request = $smcFunc['db_query']('', '
		SELECT id_alert, extra
		FROM {db_prefix}user_alerts
		WHERE id_member = {int:id_member}
			'.($unread ? '
			AND is_read = 0' : ''),
		array(
			'id_member' => $memID,
		)
	);

	// First we dump alerts and possible boards information out.
	$alerts = array();
	$boards = array();
	$possible_boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$alerts[$row['id_alert']] = !empty($row['extra']) ? $smcFunc['json_decode']($row['extra'], true) : array();

		// Only add to possible boards ones that are not empty and that we haven't set before.
		if (!empty($alerts[$row['id_alert']]['board']) && !isset($possible_boards[$alerts[$row['id_alert']]['board']]))
			$possible_boards[$alerts[$row['id_alert']]['board']] = $alerts[$row['id_alert']]['board'];
	}
	$smcFunc['db_free_result']($request);

	// If this isn't the current user, get their boards.
	if (isset($user_info) && $user_info['id'] != $memID)
	{
		$query_see_board = build_query_board($memID);
		$query_see_board = $query_see_board['query_see_board'];
	}

	// Find only the boards they can see.
	if (!empty($possible_boards))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_board
			FROM {db_prefix}boards AS b
			WHERE ' . (!empty($query_see_board) ? '{raw:query_see_board}' : '{query_see_board}') . '
				AND id_board IN ({array_int:boards})',
			array(
				'boards' => array_keys($possible_boards),
				'query_see_board' => $query_see_board
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[$row['id_board']] = $row['id_board'];
	}
	unset($possible_boards);

	// Now check alerts again and remove any they can't see.
	foreach ($alerts as $id_alert => $extra)
		if (!isset($boards[$extra['board']]))
			unset($alerts[$id_alert]);		

	return count($alerts);
}

/**
 * Handles alerts related to topics and posts
 *
 * @param int $memID The ID of the member
 */
function alert_notifications_topics($memID)
{
	global $txt, $scripturl, $context, $modSettings, $sourcedir;

	// Because of the way this stuff works, we want to do this ourselves.
	if (isset($_POST['edit_notify_topics']) || isset($_POST['remove_notify_topics']))
	{
		checkSession();
		validateToken(str_replace('%u', $memID, 'profile-nt%u'), 'post');

		makeNotificationChanges($memID);
		$context['profile_updated'] = $txt['profile_updated_own'];
	}

	// Now set up for the token check.
	$context['token_check'] = str_replace('%u', $memID, 'profile-nt%u');
	createToken($context['token_check'], 'post');

	// Gonna want this for the list.
	require_once($sourcedir . '/Subs-List.php');

	// Do the topic notifications.
	$listOptions = array(
		'id' => 'topic_notification_list',
		'width' => '100%',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'no_items_label' => $txt['notifications_topics_none'] . '<br><br>' . $txt['notifications_topics_howto'],
		'no_items_align' => 'left',
		'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=notification;sa=topics',
		'default_sort_col' => 'last_post',
		'get_items' => array(
			'function' => 'list_getTopicNotifications',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getTopicNotificationCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['notifications_topics'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($topic) use ($txt)
					{
						$link = $topic['link'];

						if ($topic['new'])
							$link .= ' <a href="' . $topic['new_href'] . '" class="new_posts">' . $txt['new'] . '</a>';

						$link .= '<br><span class="smalltext"><em>' . $txt['in'] . ' ' . $topic['board_link'] . '</em></span>';

						return $link;
					},
				),
				'sort' => array(
					'default' => 'ms.subject',
					'reverse' => 'ms.subject DESC',
				),
			),
			'started_by' => array(
				'header' => array(
					'value' => $txt['started_by'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'poster_link',
				),
				'sort' => array(
					'default' => 'real_name_col',
					'reverse' => 'real_name_col DESC',
				),
			),
			'last_post' => array(
				'header' => array(
					'value' => $txt['last_post'],
					'class' => 'lefttext',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<span class="smalltext">%1$s<br>' . $txt['by'] . ' %2$s</span>',
						'params' => array(
							'updated' => false,
							'poster_updated_link' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'ml.id_msg DESC',
					'reverse' => 'ml.id_msg',
				),
			),
			'alert' => array(
				'header' => array(
					'value' => $txt['notify_what_how'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($topic) use ($txt)
					{
						$pref = $topic['notify_pref'];
						$mode = !empty($topic['unwatched']) ? 0 : ($pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1));
						return $txt['notify_topic_' . $mode];
					},
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="notify_topics[]" value="%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=profile;area=notification;sa=topics',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				'u' => $memID,
				'sa' => $context['menu_item_selected'],
				$context['session_var'] => $context['session_id'],
			),
			'token' => $context['token_check'],
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_topics" value="' . $txt['notifications_update'] . '" class="button" />
							<input type="submit" name="remove_notify_topics" value="' . $txt['notification_remove_pref'] . '" class="button" />',
				'class' => 'floatright',
			),
		),
	);

	// Create the notification list.
	createList($listOptions);
}

/**
 * Handles preferences related to board-level notifications
 *
 * @param int $memID The ID of the member
 */
function alert_notifications_boards($memID)
{
	global $txt, $scripturl, $context, $sourcedir;

	// Because of the way this stuff works, we want to do this ourselves.
	if (isset($_POST['edit_notify_boards']) || isset($_POSt['remove_notify_boards']))
	{
		checkSession();
		validateToken(str_replace('%u', $memID, 'profile-nt%u'), 'post');

		makeNotificationChanges($memID);
		$context['profile_updated'] = $txt['profile_updated_own'];
	}

	// Now set up for the token check.
	$context['token_check'] = str_replace('%u', $memID, 'profile-nt%u');
	createToken($context['token_check'], 'post');

	// Gonna want this for the list.
	require_once($sourcedir . '/Subs-List.php');

	// Fine, start with the board list.
	$listOptions = array(
		'id' => 'board_notification_list',
		'width' => '100%',
		'no_items_label' => $txt['notifications_boards_none'] . '<br><br>' . $txt['notifications_boards_howto'],
		'no_items_align' => 'left',
		'base_href' => $scripturl . '?action=profile;u=' . $memID . ';area=notification;sa=boards',
		'default_sort_col' => 'board_name',
		'get_items' => array(
			'function' => 'list_getBoardNotifications',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'board_name' => array(
				'header' => array(
					'value' => $txt['notifications_boards'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($board) use ($txt)
					{
						$link = $board['link'];

						if ($board['new'])
							$link .= ' <a href="' . $board['href'] . '" class="new_posts">' . $txt['new'] . '</a>';

						return $link;
					},
				),
				'sort' => array(
					'default' => 'name',
					'reverse' => 'name DESC',
				),
			),
			'alert' => array(
				'header' => array(
					'value' => $txt['notify_what_how'],
					'class' => 'lefttext',
				),
				'data' => array(
					'function' => function($board) use ($txt)
					{
						$pref = $board['notify_pref'];
						$mode = $pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1);
						return $txt['notify_board_' . $mode];
					},
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=profile;area=notification;sa=boards',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				'u' => $memID,
				'sa' => $context['menu_item_selected'],
				$context['session_var'] => $context['session_id'],
			),
			'token' => $context['token_check'],
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_boards" value="' . $txt['notifications_update'] . '" class="button">
							<input type="submit" name="remove_notify_boards" value="' . $txt['notification_remove_pref'] . '" class="button" />',
				'class' => 'floatright',
			),
		),
	);

	// Create the board notification list.
	createList($listOptions);
}

/**
 * Determins how many topics a user has requested notifications for
 *
 * @param int $memID The ID of the member
 * @return int The number of topic notifications for this user
 */
function list_getTopicNotificationCount($memID)
{
	global $smcFunc, $user_info, $modSettings;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_notify AS ln' . (!$modSettings['postmod_active'] && $user_info['query_see_board'] === '1=1' ? '' : '
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)') . ($user_info['query_see_board'] === '1=1' ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)') . '
		WHERE ln.id_member = {int:selected_member}' . ($user_info['query_see_board'] === '1=1' ? '' : '
			AND {query_see_board}') . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : ''),
		array(
			'selected_member' => $memID,
			'is_approved' => 1,
		)
	);
	list ($totalNotifications) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return (int) $totalNotifications;
}

/**
 * Gets information about all the topics a user has requested notifications for. Callback for the list in alert_notifications_topics
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to display on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about the topics a user has subscribed to
 */
function list_getTopicNotifications($start, $items_per_page, $sort, $memID)
{
	global $smcFunc, $scripturl, $user_info, $modSettings, $sourcedir;

	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memID);
	$prefs = isset($prefs[$memID]) ? $prefs[$memID] : array();

	// All the topics with notification on...
	$request = $smcFunc['db_query']('', '
		SELECT
			COALESCE(lt.id_msg, COALESCE(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name,
			t.id_topic, ms.subject, ms.id_member, COALESCE(mem.real_name, ms.poster_name) AS real_name_col,
			ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
			COALESCE(mem2.real_name, ml.poster_name) AS last_real_name,
			lt.unwatched
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic' . ($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:items_per_page}',
		array(
			'current_member' => $user_info['id'],
			'is_approved' => 1,
			'selected_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'items_per_page' => $items_per_page,
		)
	);
	$notification_topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		censorText($row['subject']);

		$notification_topics[] = array(
			'id' => $row['id_topic'],
			'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
			'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
			'subject' => $row['subject'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'updated' => timeformat($row['poster_time']),
			'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
			'board_link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'notify_pref' => isset($prefs['topic_notify_' . $row['id_topic']]) ? $prefs['topic_notify_' . $row['id_topic']] : (!empty($prefs['topic_notify']) ? $prefs['topic_notify'] : 0),
			'unwatched' => $row['unwatched'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $notification_topics;
}

/**
 * Gets information about all the boards a user has requested notifications for. Callback for the list in alert_notifications_boards
 *
 * @param int $start Which item to start with (not used here)
 * @param int $items_per_page How many items to show on each page (not used here)
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about all the boards a user is subscribed to
 */
function list_getBoardNotifications($start, $items_per_page, $sort, $memID)
{
	global $smcFunc, $scripturl, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($memID);
	$prefs = isset($prefs[$memID]) ? $prefs[$memID] : array();

	$request = $smcFunc['db_query']('', '
		SELECT b.id_board, b.name, COALESCE(lb.id_msg, 0) AS board_read, b.id_msg_updated
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}
		ORDER BY {raw:sort}',
		array(
			'current_member' => $user_info['id'],
			'selected_member' => $memID,
			'sort' => $sort,
		)
	);
	$notification_boards = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$notification_boards[] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
			'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'new' => $row['board_read'] < $row['id_msg_updated'],
			'notify_pref' => isset($prefs['board_notify_' . $row['id_board']]) ? $prefs['board_notify_' . $row['id_board']] : (!empty($prefs['board_notify']) ? $prefs['board_notify'] : 0),
		);
	$smcFunc['db_free_result']($request);

	return $notification_boards;
}

/**
 * Loads the theme options for a user
 *
 * @param int $memID The ID of the member
 */
function loadThemeOptions($memID)
{
	global $context, $options, $cur_profile, $smcFunc;

	if (isset($_POST['default_options']))
		$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];

	if ($context['user']['is_owner'])
	{
		$context['member']['options'] = $options;
		if (isset($_POST['options']) && is_array($_POST['options']))
			foreach ($_POST['options'] as $k => $v)
				$context['member']['options'][$k] = $v;
	}
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member, variable, value
			FROM {db_prefix}themes
			WHERE id_theme IN (1, {int:member_theme})
				AND id_member IN (-1, {int:selected_member})',
			array(
				'member_theme' => (int) $cur_profile['id_theme'],
				'selected_member' => $memID,
			)
		);
		$temp = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['id_member'] == -1)
			{
				$temp[$row['variable']] = $row['value'];
				continue;
			}

			if (isset($_POST['options'][$row['variable']]))
				$row['value'] = $_POST['options'][$row['variable']];
			$context['member']['options'][$row['variable']] = $row['value'];
		}
		$smcFunc['db_free_result']($request);

		// Load up the default theme options for any missing.
		foreach ($temp as $k => $v)
		{
			if (!isset($context['member']['options'][$k]))
				$context['member']['options'][$k] = $v;
		}
	}
}

/**
 * Handles the "ignored boards" section of the profile (if enabled)
 *
 * @param int $memID The ID of the member
 */
function ignoreboards($memID)
{
	global $context, $modSettings, $smcFunc, $cur_profile, $sourcedir;

	// Have the admins enabled this option?
	if (empty($modSettings['allow_ignore_boards']))
		fatal_lang_error('ignoreboards_disallowed', 'user');

	// Find all the boards this user is allowed to see.
	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
			'. (!empty($cur_profile['ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty_string}',
		array(
			'ignore_boards' => !empty($cur_profile['ignore_boards']) ? explode(',', $cur_profile['ignore_boards']) : array(),
			'empty_string' => '',
		)
	);
	$context['num_boards'] = $smcFunc['db_num_rows']($request);
	$context['categories'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// This category hasn't been set up yet..
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => array()
			);

		// Set this board up, and let the template know when it's a child.  (indent them..)
		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'selected' => $row['is_ignored'],
		);
	}
	$smcFunc['db_free_result']($request);

	require_once($sourcedir . '/Subs-Boards.php');
	sortCategories($context['categories']);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = array();
	foreach ($context['categories'] as $category)
	{
		// Include a list of boards per category for easy toggling.
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		$temp_boards[] = array(
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	// Now, alternate them so they can be shown left and right ;).
	$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++)
	{
		$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			$context['board_columns'][] = array();
	}

	loadThemeOptions($memID);
}

/**
 * Load all the languages for the profile
 * .
 * @return bool Whether or not the forum has multiple languages installed
 */
function profileLoadLanguages()
{
	global $context;

	$context['profile_languages'] = array();

	// Get our languages!
	getLanguages();

	// Setup our languages.
	foreach ($context['languages'] as $lang)
	{
		$context['profile_languages'][$lang['filename']] = strtr($lang['name'], array('-utf8' => ''));
	}
	ksort($context['profile_languages']);

	// Return whether we should proceed with this.
	return count($context['profile_languages']) > 1 ? true : false;
}

/**
 * Handles the "manage groups" section of the profile
 *
 * @return true Always returns true
 */
function profileLoadGroups()
{
	global $cur_profile, $txt, $context, $smcFunc, $user_settings;

	$context['member_groups'] = array(
		0 => array(
			'id' => 0,
			'name' => $txt['no_primary_membergroup'],
			'is_primary' => $cur_profile['id_group'] == 0,
			'can_be_additional' => false,
			'can_be_primary' => true,
		)
	);
	$curGroups = explode(',', $cur_profile['additional_groups']);

	// Load membergroups, but only those groups the user can assign.
	$request = $smcFunc['db_query']('', '
		SELECT group_name, id_group, hidden
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
			AND min_posts = {int:min_posts}' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'moderator_group' => 3,
			'min_posts' => -1,
			'is_protected' => 1,
			'newbie_group' => 4,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We should skip the administrator group if they don't have the admin_forum permission!
		if ($row['id_group'] == 1 && !allowedTo('admin_forum'))
			continue;

		$context['member_groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'is_primary' => $cur_profile['id_group'] == $row['id_group'],
			'is_additional' => in_array($row['id_group'], $curGroups),
			'can_be_additional' => true,
			'can_be_primary' => $row['hidden'] != 2,
		);
	}
	$smcFunc['db_free_result']($request);

	$context['member']['group_id'] = $user_settings['id_group'];

	return true;
}

/**
 * Load key signature context data.
 *
 * @return true Always returns true
 */
function profileLoadSignatureData()
{
	global $modSettings, $context, $txt, $cur_profile, $memberContext;

	// Signature limits.
	list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);

	$context['signature_enabled'] = isset($sig_limits[0]) ? $sig_limits[0] : 0;
	$context['signature_limits'] = array(
		'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
		'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
		'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
		'max_smileys' => isset($sig_limits[4]) ? $sig_limits[4] : 0,
		'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
		'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
		'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
		'bbc' => !empty($sig_bbc) ? explode(',', $sig_bbc) : array(),
	);
	// Kept this line in for backwards compatibility!
	$context['max_signature_length'] = $context['signature_limits']['max_length'];
	// Warning message for signature image limits?
	$context['signature_warning'] = '';
	if ($context['signature_limits']['max_image_width'] && $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_size'], $context['signature_limits']['max_image_width'], $context['signature_limits']['max_image_height']);
	elseif ($context['signature_limits']['max_image_width'] || $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_' . ($context['signature_limits']['max_image_width'] ? 'width' : 'height')], $context['signature_limits'][$context['signature_limits']['max_image_width'] ? 'max_image_width' : 'max_image_height']);

	$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && (function_exists('pspell_new') || (function_exists('enchant_broker_init') && ($txt['lang_character_set'] == 'UTF-8' || function_exists('iconv'))));

	if (empty($context['do_preview']))
		$context['member']['signature'] = empty($cur_profile['signature']) ? '' : str_replace(array('<br>', '<', '>', '"', '\''), array("\n", '&lt;', '&gt;', '&quot;', '&#039;'), $cur_profile['signature']);
	else
	{
		$signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
		$validation = profileValidateSignature($signature);
		if (empty($context['post_errors']))
		{
			loadLanguage('Errors');
			$context['post_errors'] = array();
		}
		$context['post_errors'][] = 'signature_not_yet_saved';
		if ($validation !== true && $validation !== false)
			$context['post_errors'][] = $validation;

		censorText($context['member']['signature']);
		$context['member']['current_signature'] = $context['member']['signature'];
		censorText($signature);
		$context['member']['signature_preview'] = parse_bbc($signature, true, 'sig' . $memberContext[$context['id_member']]);
		$context['member']['signature'] = $_POST['signature'];
	}

	// Load the spell checker?
	if ($context['show_spellchecking'])
		loadJavaScriptFile('spellcheck.js', array('defer' => false, 'minimize' => true), 'smf_spellcheck');

	return true;
}

/**
 * Load avatar context data.
 *
 * @return true Always returns true
 */
function profileLoadAvatarData()
{
	global $context, $cur_profile, $modSettings, $scripturl;

	$context['avatar_url'] = $modSettings['avatar_url'];

	// Default context.
	$context['member']['avatar'] += array(
		'custom' => stristr($cur_profile['avatar'], 'http://') || stristr($cur_profile['avatar'], 'https://') ? $cur_profile['avatar'] : 'http://',
		'selection' => $cur_profile['avatar'] == '' || (stristr($cur_profile['avatar'], 'http://') || stristr($cur_profile['avatar'], 'https://')) ? '' : $cur_profile['avatar'],
		'allow_server_stored' => (empty($modSettings['gravatarEnabled']) || empty($modSettings['gravatarOverride'])) && (allowedTo('profile_server_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any'))),
		'allow_upload' => (empty($modSettings['gravatarEnabled']) || empty($modSettings['gravatarOverride'])) && (allowedTo('profile_upload_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any'))),
		'allow_external' => (empty($modSettings['gravatarEnabled']) || empty($modSettings['gravatarOverride'])) && (allowedTo('profile_remote_avatar') || (!$context['user']['is_owner'] && allowedTo('profile_extra_any'))),
		'allow_gravatar' => !empty($modSettings['gravatarEnabled']) || !empty($modSettings['gravatarOverride']),
	);

	if ($context['member']['avatar']['allow_gravatar'] && (stristr($cur_profile['avatar'], 'gravatar://') || !empty($modSettings['gravatarOverride'])))
	{
		$context['member']['avatar'] += array(
			'choice' => 'gravatar',
			'server_pic' => 'blank.png',
			'external' => $cur_profile['avatar'] == 'gravatar://' || empty($modSettings['gravatarAllowExtraEmail']) || !empty($modSettings['gravatarOverride']) ? $cur_profile['email_address'] : substr($cur_profile['avatar'], 11)
		);
		$context['member']['avatar']['href'] = get_gravatar_url($context['member']['avatar']['external']);
	}
	elseif ($cur_profile['avatar'] == '' && $cur_profile['id_attach'] > 0 && $context['member']['avatar']['allow_upload'])
	{
		$context['member']['avatar'] += array(
			'choice' => 'upload',
			'server_pic' => 'blank.png',
			'external' => 'http://'
		);
		$context['member']['avatar']['href'] = empty($cur_profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $cur_profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $cur_profile['filename'];
	}
	// Use "avatar_original" here so we show what the user entered even if the image proxy is enabled
	elseif ((stristr($cur_profile['avatar'], 'http://') || stristr($cur_profile['avatar'], 'https://')) && $context['member']['avatar']['allow_external'])
		$context['member']['avatar'] += array(
			'choice' => 'external',
			'server_pic' => 'blank.png',
			'external' => $cur_profile['avatar_original']
		);
	elseif ($cur_profile['avatar'] != '' && file_exists($modSettings['avatar_directory'] . '/' . $cur_profile['avatar']) && $context['member']['avatar']['allow_server_stored'])
		$context['member']['avatar'] += array(
			'choice' => 'server_stored',
			'server_pic' => $cur_profile['avatar'] == '' ? 'blank.png' : $cur_profile['avatar'],
			'external' => 'http://'
		);
	else
		$context['member']['avatar'] += array(
			'choice' => 'none',
			'server_pic' => 'blank.png',
			'external' => 'http://'
		);

	// Get a list of all the avatars.
	if ($context['member']['avatar']['allow_server_stored'])
	{
		$context['avatar_list'] = array();
		$context['avatars'] = is_dir($modSettings['avatar_directory']) ? getAvatars('', 0) : array();
	}
	else
		$context['avatars'] = array();

	// Second level selected avatar...
	$context['avatar_selected'] = substr(strrchr($context['member']['avatar']['server_pic'], '/'), 1);
	return !empty($context['member']['avatar']['allow_server_stored']) || !empty($context['member']['avatar']['allow_external']) || !empty($context['member']['avatar']['allow_upload']) || !empty($context['member']['avatar']['allow_gravatar']);
}

/**
 * Save a members group.
 *
 * @param int &$value The ID of the (new) primary group
 * @return true Always returns true
 */
function profileSaveGroups(&$value)
{
	global $profile_vars, $old_profile, $context, $smcFunc, $cur_profile;

	// Do we need to protect some groups?
	if (!allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		$protected_groups = array(1);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$protected_groups[] = $row['id_group'];
		$smcFunc['db_free_result']($request);

		$protected_groups = array_unique($protected_groups);
	}

	// The account page allows the change of your id_group - but not to a protected group!
	if (empty($protected_groups) || count(array_intersect(array((int) $value, $old_profile['id_group']), $protected_groups)) == 0)
		$value = (int) $value;
	// ... otherwise it's the old group sir.
	else
		$value = $old_profile['id_group'];

	// Find the additional membergroups (if any)
	if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
	{
		$additional_groups = array();
		foreach ($_POST['additional_groups'] as $group_id)
		{
			$group_id = (int) $group_id;
			if (!empty($group_id) && (empty($protected_groups) || !in_array($group_id, $protected_groups)))
				$additional_groups[] = $group_id;
		}

		// Put the protected groups back in there if you don't have permission to take them away.
		$old_additional_groups = explode(',', $old_profile['additional_groups']);
		foreach ($old_additional_groups as $group_id)
		{
			if (!empty($protected_groups) && in_array($group_id, $protected_groups))
				$additional_groups[] = $group_id;
		}

		if (implode(',', $additional_groups) !== $old_profile['additional_groups'])
		{
			$profile_vars['additional_groups'] = implode(',', $additional_groups);
			$cur_profile['additional_groups'] = implode(',', $additional_groups);
		}
	}

	// Too often, people remove delete their own account, or something.
	if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
	{
		$stillAdmin = $value == 1 || (isset($additional_groups) && in_array(1, $additional_groups));

		// If they would no longer be an admin, look for any other...
		if (!$stillAdmin)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND id_member != {int:selected_member}
				LIMIT 1',
				array(
					'admin_group' => 1,
					'selected_member' => $context['id_member'],
				)
			);
			list ($another) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (empty($another))
				fatal_lang_error('at_least_one_admin', 'critical');
		}
	}

	// If we are changing group status, update permission cache as necessary.
	if ($value != $old_profile['id_group'] || isset($profile_vars['additional_groups']))
	{
		if ($context['user']['is_owner'])
			$_SESSION['mc']['time'] = 0;
		else
			updateSettings(array('settings_updated' => time()));
	}

	// Announce to any hooks that we have changed groups, but don't allow them to change it.
	call_integration_hook('integrate_profile_profileSaveGroups', array($value, $additional_groups));

	return true;
}

/**
 * The avatar is incredibly complicated, what with the options... and what not.
 * @todo argh, the avatar here. Take this out of here!
 *
 * @param string &$value What kind of avatar we're expecting. Can be 'none', 'server_stored', 'gravatar', 'external' or 'upload'
 * @return bool|string False if success (or if memID is empty and password authentication failed), otherwise a string indicating what error occurred
 */
function profileSaveAvatarData(&$value)
{
	global $modSettings, $sourcedir, $smcFunc, $profile_vars, $cur_profile, $context;

	$memID = $context['id_member'];
	if (empty($memID) && !empty($context['password_auth_failed']))
		return false;

	require_once($sourcedir . '/ManageAttachments.php');

	// We're going to put this on a nice custom dir.
	$uploadDir = $modSettings['custom_avatar_dir'];
	$id_folder = 1;

	$downloadedExternalAvatar = false;
	if ($value == 'external' && allowedTo('profile_remote_avatar') && (stripos($_POST['userpicpersonal'], 'http://') === 0 || stripos($_POST['userpicpersonal'], 'https://') === 0) && strlen($_POST['userpicpersonal']) > 7 && !empty($modSettings['avatar_download_external']))
	{
		if (!is_writable($uploadDir))
			fatal_lang_error('attachments_no_write', 'critical');

		$url = parse_url($_POST['userpicpersonal']);
		$contents = fetch_web_data($url['scheme'] . '://' . $url['host'] . (empty($url['port']) ? '' : ':' . $url['port']) . str_replace(' ', '%20', trim($url['path'])));

		$new_filename = $uploadDir . '/' . getAttachmentFilename('avatar_tmp_' . $memID, false, null, true);
		if ($contents != false && $tmpAvatar = fopen($new_filename, 'wb'))
		{
			fwrite($tmpAvatar, $contents);
			fclose($tmpAvatar);

			$downloadedExternalAvatar = true;
			$_FILES['attachment']['tmp_name'] = $new_filename;
		}
	}

	// Removes whatever attachment there was before updating
	if ($value == 'none')
	{
		$profile_vars['avatar'] = '';

		// Reset the attach ID.
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		removeAttachments(array('id_member' => $memID));
	}

	// An avatar from the server-stored galleries.
	elseif ($value == 'server_stored' && allowedTo('profile_server_avatar'))
	{
		$profile_vars['avatar'] = strtr(empty($_POST['file']) ? (empty($_POST['cat']) ? '' : $_POST['cat']) : $_POST['file'], array('&amp;' => '&'));
		$profile_vars['avatar'] = preg_match('~^([\w _!@%*=\-#()\[\]&.,]+/)?[\w _!@%*=\-#()\[\]&.,]+$~', $profile_vars['avatar']) != 0 && preg_match('/\.\./', $profile_vars['avatar']) == 0 && file_exists($modSettings['avatar_directory'] . '/' . $profile_vars['avatar']) ? ($profile_vars['avatar'] == 'blank.png' ? '' : $profile_vars['avatar']) : '';

		// Clear current profile...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Get rid of their old avatar. (if uploaded.)
		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value == 'gravatar' && !empty($modSettings['gravatarEnabled']))
	{
		// One wasn't specified, or it's not allowed to use extra email addresses, or it's not a valid one, reset to default Gravatar.
		if (empty($_POST['gravatarEmail']) || empty($modSettings['gravatarAllowExtraEmail']) || !filter_var($_POST['gravatarEmail'], FILTER_VALIDATE_EMAIL))
			$profile_vars['avatar'] = 'gravatar://';
		else
			$profile_vars['avatar'] = 'gravatar://' . ($_POST['gravatarEmail'] != $cur_profile['email_address'] ? $_POST['gravatarEmail'] : '');

		// Get rid of their old avatar. (if uploaded.)
		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value == 'external' && allowedTo('profile_remote_avatar') && (stripos($_POST['userpicpersonal'], 'http://') === 0 || stripos($_POST['userpicpersonal'], 'https://') === 0) && empty($modSettings['avatar_download_external']))
	{
		// We need these clean...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Remove any attached avatar...
		removeAttachments(array('id_member' => $memID));

		$profile_vars['avatar'] = str_replace(' ', '%20', preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $_POST['userpicpersonal']));

		if ($profile_vars['avatar'] == 'http://' || $profile_vars['avatar'] == 'http:///')
			$profile_vars['avatar'] = '';
		// Trying to make us do something we'll regret?
		elseif (substr($profile_vars['avatar'], 0, 7) != 'http://' && substr($profile_vars['avatar'], 0, 8) != 'https://')
			return 'bad_avatar_invalid_url';
		// Should we check dimensions?
		elseif (!empty($modSettings['avatar_max_height_external']) || !empty($modSettings['avatar_max_width_external']))
		{
			// Now let's validate the avatar.
			$sizes = url_image_size($profile_vars['avatar']);

			if (is_array($sizes) && (($sizes[0] > $modSettings['avatar_max_width_external'] && !empty($modSettings['avatar_max_width_external'])) || ($sizes[1] > $modSettings['avatar_max_height_external'] && !empty($modSettings['avatar_max_height_external']))))
			{
				// Houston, we have a problem. The avatar is too large!!
				if ($modSettings['avatar_action_too_large'] == 'option_refuse')
					return 'bad_avatar_too_large';
				elseif ($modSettings['avatar_action_too_large'] == 'option_download_and_resize')
				{
					// @todo remove this if appropriate
					require_once($sourcedir . '/Subs-Graphics.php');
					if (downloadAvatar($profile_vars['avatar'], $memID, $modSettings['avatar_max_width_external'], $modSettings['avatar_max_height_external']))
					{
						$profile_vars['avatar'] = '';
						$cur_profile['id_attach'] = $modSettings['new_avatar_data']['id'];
						$cur_profile['filename'] = $modSettings['new_avatar_data']['filename'];
						$cur_profile['attachment_type'] = $modSettings['new_avatar_data']['type'];
					}
					else
						return 'bad_avatar';
				}
			}
		}
	}
	elseif (($value == 'upload' && allowedTo('profile_upload_avatar')) || $downloadedExternalAvatar)
	{
		if ((isset($_FILES['attachment']['name']) && $_FILES['attachment']['name'] != '') || $downloadedExternalAvatar)
		{
			// Get the dimensions of the image.
			if (!$downloadedExternalAvatar)
			{
				if (!is_writable($uploadDir))
					fatal_lang_error('attachments_no_write', 'critical');

				$new_filename = $uploadDir . '/' . getAttachmentFilename('avatar_tmp_' . $memID, false, null, true);
				if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $new_filename))
					fatal_lang_error('attach_timeout', 'critical');

				$_FILES['attachment']['tmp_name'] = $new_filename;
			}

			$sizes = @getimagesize($_FILES['attachment']['tmp_name']);

			// No size, then it's probably not a valid pic.
			if ($sizes === false)
			{
				@unlink($_FILES['attachment']['tmp_name']);
				return 'bad_avatar';
			}
			// Check whether the image is too large.
			elseif ((!empty($modSettings['avatar_max_width_upload']) && $sizes[0] > $modSettings['avatar_max_width_upload']) || (!empty($modSettings['avatar_max_height_upload']) && $sizes[1] > $modSettings['avatar_max_height_upload']))
			{
				if (!empty($modSettings['avatar_resize_upload']))
				{
					// Attempt to chmod it.
					smf_chmod($_FILES['attachment']['tmp_name'], 0644);

					// @todo remove this require when appropriate
					require_once($sourcedir . '/Subs-Graphics.php');
					if (!downloadAvatar($_FILES['attachment']['tmp_name'], $memID, $modSettings['avatar_max_width_upload'], $modSettings['avatar_max_height_upload']))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}

					// Reset attachment avatar data.
					$cur_profile['id_attach'] = $modSettings['new_avatar_data']['id'];
					$cur_profile['filename'] = $modSettings['new_avatar_data']['filename'];
					$cur_profile['attachment_type'] = $modSettings['new_avatar_data']['type'];
				}

				// Admin doesn't want to resize large avatars, can't do much about it but to tell you to use a different one :(
				else
				{
					@unlink($_FILES['attachment']['tmp_name']);
					return 'bad_avatar_too_large';
				}
			}

			// So far, so good, checks lies ahead!
			elseif (is_array($sizes))
			{
				// Now try to find an infection.
				require_once($sourcedir . '/Subs-Graphics.php');
				if (!checkImageContents($_FILES['attachment']['tmp_name'], !empty($modSettings['avatar_paranoid'])))
				{
					// It's bad. Try to re-encode the contents?
					if (empty($modSettings['avatar_reencode']) || (!reencodeImage($_FILES['attachment']['tmp_name'], $sizes[2])))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar_fail_reencode';
					}
					// We were successful. However, at what price?
					$sizes = @getimagesize($_FILES['attachment']['tmp_name']);
					// Hard to believe this would happen, but can you bet?
					if ($sizes === false)
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}
				}

				$extensions = array(
					'1' => 'gif',
					'2' => 'jpg',
					'3' => 'png',
					'6' => 'bmp'
				);

				$extension = isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';
				$mime_type = 'image/' . ($extension === 'jpg' ? 'jpeg' : ($extension === 'bmp' ? 'x-ms-bmp' : $extension));
				$destName = 'avatar_' . $memID . '_' . time() . '.' . $extension;
				list ($width, $height) = getimagesize($_FILES['attachment']['tmp_name']);
				$file_hash = '';

				// Remove previous attachments this member might have had.
				removeAttachments(array('id_member' => $memID));

				$cur_profile['id_attach'] = $smcFunc['db_insert']('',
					'{db_prefix}attachments',
					array(
						'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'fileext' => 'string', 'size' => 'int',
						'width' => 'int', 'height' => 'int', 'mime_type' => 'string', 'id_folder' => 'int',
					),
					array(
						$memID, 1, $destName, $file_hash, $extension, filesize($_FILES['attachment']['tmp_name']),
						(int) $width, (int) $height, $mime_type, $id_folder,
					),
					array('id_attach'),
					1
				);

				$cur_profile['filename'] = $destName;
				$cur_profile['attachment_type'] = 1;

				$destinationPath = $uploadDir . '/' . (empty($file_hash) ? $destName : $cur_profile['id_attach'] . '_' . $file_hash . '.dat');
				if (!rename($_FILES['attachment']['tmp_name'], $destinationPath))
				{
					// I guess a man can try.
					removeAttachments(array('id_member' => $memID));
					fatal_lang_error('attach_timeout', 'critical');
				}

				// Attempt to chmod it.
				smf_chmod($uploadDir . '/' . $destinationPath, 0644);
			}
			$profile_vars['avatar'] = '';

			// Delete any temporary file.
			if (file_exists($_FILES['attachment']['tmp_name']))
				@unlink($_FILES['attachment']['tmp_name']);
		}
		// Selected the upload avatar option and had one already uploaded before or didn't upload one.
		else
			$profile_vars['avatar'] = '';
	}
	elseif ($value == 'gravatar' && allowedTo('profile_gravatar_avatar'))
		$profile_vars['avatar'] = 'gravatar://www.gravatar.com/avatar/' . md5(strtolower(trim($cur_profile['email_address'])));
	else
		$profile_vars['avatar'] = '';

	// Setup the profile variables so it shows things right on display!
	$cur_profile['avatar'] = $profile_vars['avatar'];

	return false;
}

/**
 * Validate the signature
 *
 * @param string &$value The new signature
 * @return bool|string True if the signature passes the checks, otherwise a string indicating what the problem is
 */
function profileValidateSignature(&$value)
{
	global $sourcedir, $modSettings, $smcFunc, $txt;

	require_once($sourcedir . '/Subs-Post.php');

	// Admins can do whatever they hell they want!
	if (!allowedTo('admin_forum'))
	{
		// Load all the signature limits.
		list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

		$unparsed_signature = strtr(un_htmlspecialchars($value), array("\r" => '', '&#039' => '\''));

		// Too many lines?
		if (!empty($sig_limits[2]) && substr_count($unparsed_signature, "\n") >= $sig_limits[2])
		{
			$txt['profile_error_signature_max_lines'] = sprintf($txt['profile_error_signature_max_lines'], $sig_limits[2]);
			return 'signature_max_lines';
		}

		// Too many images?!
		if (!empty($sig_limits[3]) && (substr_count(strtolower($unparsed_signature), '[img') + substr_count(strtolower($unparsed_signature), '<img')) > $sig_limits[3])
		{
			$txt['profile_error_signature_max_image_count'] = sprintf($txt['profile_error_signature_max_image_count'], $sig_limits[3]);
			return 'signature_max_image_count';
		}

		// What about too many smileys!
		$smiley_parsed = $unparsed_signature;
		parsesmileys($smiley_parsed);
		$smiley_count = substr_count(strtolower($smiley_parsed), '<img') - substr_count(strtolower($unparsed_signature), '<img');
		if (!empty($sig_limits[4]) && $sig_limits[4] == -1 && $smiley_count > 0)
			return 'signature_allow_smileys';
		elseif (!empty($sig_limits[4]) && $sig_limits[4] > 0 && $smiley_count > $sig_limits[4])
		{
			$txt['profile_error_signature_max_smileys'] = sprintf($txt['profile_error_signature_max_smileys'], $sig_limits[4]);
			return 'signature_max_smileys';
		}

		// Maybe we are abusing font sizes?
		if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d\.]+)?(px|pt|em|x-large|larger)~i', $unparsed_signature, $matches) !== false && isset($matches[2]))
		{
			foreach ($matches[1] as $ind => $size)
			{
				$limit_broke = 0;
				// Attempt to allow all sizes of abuse, so to speak.
				if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
					$limit_broke = $sig_limits[7] . 'px';
				elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
					$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
				elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
					$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
				elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
					$limit_broke = 'large';

				if ($limit_broke)
				{
					$txt['profile_error_signature_max_font_size'] = sprintf($txt['profile_error_signature_max_font_size'], $limit_broke);
					return 'signature_max_font_size';
				}
			}
		}

		// The difficult one - image sizes! Don't error on this - just fix it.
		if ((!empty($sig_limits[5]) || !empty($sig_limits[6])))
		{
			// Get all BBC tags...
			preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $unparsed_signature, $matches);
			// ... and all HTML ones.
			preg_match_all('~<img\s+src=(?:")?((?:http://|ftp://|https://|ftps://).+?)(?:")?(?:\s+alt=(?:")?(.*?)(?:")?)?(?:\s?/)?' . '>~i', $unparsed_signature, $matches2, PREG_PATTERN_ORDER);
			// And stick the HTML in the BBC.
			if (!empty($matches2))
			{
				foreach ($matches2[0] as $ind => $dummy)
				{
					$matches[0][] = $matches2[0][$ind];
					$matches[1][] = '';
					$matches[2][] = '';
					$matches[3][] = '';
					$matches[4][] = '';
					$matches[5][] = '';
					$matches[6][] = '';
					$matches[7][] = $matches2[1][$ind];
				}
			}

			$replaces = array();
			// Try to find all the images!
			if (!empty($matches))
			{
				foreach ($matches[0] as $key => $image)
				{
					$width = -1; $height = -1;

					// Does it have predefined restraints? Width first.
					if ($matches[6][$key])
						$matches[2][$key] = $matches[6][$key];
					if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
					{
						$width = $sig_limits[5];
						$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
					}
					elseif ($matches[2][$key])
						$width = $matches[2][$key];
					// ... and height.
					if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
					{
						$height = $sig_limits[6];
						if ($width != -1)
							$width = $width * ($height / $matches[4][$key]);
					}
					elseif ($matches[4][$key])
						$height = $matches[4][$key];

					// If the dimensions are still not fixed - we need to check the actual image.
					if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
					{
						$sizes = url_image_size($matches[7][$key]);
						if (is_array($sizes))
						{
							// Too wide?
							if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
							{
								$width = $sig_limits[5];
								$sizes[1] = $sizes[1] * ($width / $sizes[0]);
							}
							// Too high?
							if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
							{
								$height = $sig_limits[6];
								if ($width == -1)
									$width = $sizes[0];
								$width = $width * ($height / $sizes[1]);
							}
							elseif ($width != -1)
								$height = $sizes[1];
						}
					}

					// Did we come up with some changes? If so remake the string.
					if ($width != -1 || $height != -1)
						$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
				}
				if (!empty($replaces))
					$value = str_replace(array_keys($replaces), array_values($replaces), $value);
			}
		}

		// Any disabled BBC?
		$disabledSigBBC = implode('|', $disabledTags);
		if (!empty($disabledSigBBC))
		{
			if (preg_match('~\[(' . $disabledSigBBC . '[ =\]/])~i', $unparsed_signature, $matches) !== false && isset($matches[1]))
			{
				$disabledTags = array_unique($disabledTags);
				$txt['profile_error_signature_disabled_bbc'] = sprintf($txt['profile_error_signature_disabled_bbc'], implode(', ', $disabledTags));
				return 'signature_disabled_bbc';
			}
		}
	}

	preparsecode($value);

	// Too long?
	if (!allowedTo('admin_forum') && !empty($sig_limits[1]) && $smcFunc['strlen'](str_replace('<br>', "\n", $value)) > $sig_limits[1])
	{
		$_POST['signature'] = trim($smcFunc['htmlspecialchars'](str_replace('<br>', "\n", $value), ENT_QUOTES));
		$txt['profile_error_signature_max_length'] = sprintf($txt['profile_error_signature_max_length'], $sig_limits[1]);
		return 'signature_max_length';
	}

	return true;
}

/**
 * Validate an email address.
 *
 * @param string $email The email address to validate
 * @param int $memID The ID of the member (used to prevent false positives from the current user)
 * @return bool|string True if the email is valid, otherwise a string indicating what the problem is
 */
function profileValidateEmail($email, $memID = 0)
{
	global $smcFunc;

	$email = strtr($email, array('&#039;' => '\''));

	// Check the name and email for validity.
	if (trim($email) == '')
		return 'no_email';
	if (!filter_var($email, FILTER_VALIDATE_EMAIL))
		return 'bad_email';

	// Email addresses should be and stay unique.
	$request = $smcFunc['db_query']('', '
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . ($memID != 0 ? 'id_member != {int:selected_member} AND ' : '') . '
			email_address = {string:email_address}
		LIMIT 1',
		array(
			'selected_member' => $memID,
			'email_address' => $email,
		)
	);

	if ($smcFunc['db_num_rows']($request) > 0)
		return 'email_taken';
	$smcFunc['db_free_result']($request);

	return true;
}

/**
 * Reload a user's settings.
 */
function profileReloadUser()
{
	global $modSettings, $context, $cur_profile;

	if (isset($_POST['passwrd2']) && $_POST['passwrd2'] != '')
		setLoginCookie(60 * $modSettings['cookieTime'], $context['id_member'], hash_salt($_POST['passwrd1'], $cur_profile['password_salt']));

	loadUserSettings();
	writeLog();
}

/**
 * Send the user a new activation email if they need to reactivate!
 */
function profileSendActivation()
{
	global $sourcedir, $profile_vars, $context, $scripturl, $smcFunc, $cookiename, $cur_profile, $language, $modSettings;

	require_once($sourcedir . '/Subs-Post.php');

	// Shouldn't happen but just in case.
	if (empty($profile_vars['email_address']))
		return;

	$replacements = array(
		'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $context['id_member'] . ';code=' . $profile_vars['validation_code'],
		'ACTIVATIONCODE' => $profile_vars['validation_code'],
		'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $context['id_member'],
	);

	// Send off the email.
	$emaildata = loadEmailTemplate('activate_reactivate', $replacements, empty($cur_profile['lngfile']) || empty($modSettings['userLanguage']) ? $language : $cur_profile['lngfile']);
	sendmail($profile_vars['email_address'], $emaildata['subject'], $emaildata['body'], null, 'reactivate', $emaildata['is_html'], 0);

	// Log the user out.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member = {int:selected_member}',
		array(
			'selected_member' => $context['id_member'],
		)
	);
	$_SESSION['log_time'] = 0;
	$_SESSION['login_' . $cookiename] = $smcFunc['json_encode'](array(0, '', 0));

	if (isset($_COOKIE[$cookiename]))
		$_COOKIE[$cookiename] = '';

	loadUserSettings();

	$context['user']['is_logged'] = false;
	$context['user']['is_guest'] = true;

	redirectexit('action=sendactivation');
}

/**
 * Function to allow the user to choose group membership etc...
 *
 * @param int $memID The ID of the member
 */
function groupMembership($memID)
{
	global $txt, $user_profile, $context, $smcFunc;

	$curMember = $user_profile[$memID];
	$context['primary_group'] = $curMember['id_group'];

	// Can they manage groups?
	$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	$context['can_manage_protected'] = allowedTo('admin_forum');
	$context['can_edit_primary'] = $context['can_manage_protected'];
	$context['update_message'] = isset($_GET['msg']) && isset($txt['group_membership_msg_' . $_GET['msg']]) ? $txt['group_membership_msg_' . $_GET['msg']] : '';

	// Get all the groups this user is a member of.
	$groups = explode(',', $curMember['additional_groups']);
	$groups[] = $curMember['id_group'];

	// Ensure the query doesn't croak!
	if (empty($groups))
		$groups = array(0);
	// Just to be sure...
	foreach ($groups as $k => $v)
		$groups[$k] = (int) $v;

	// Get all the membergroups they can join.
	$request = $smcFunc['db_query']('', '
		SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
			COALESCE(lgr.id_member, 0) AS pending
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}log_group_requests AS lgr ON (lgr.id_member = {int:selected_member} AND lgr.id_group = mg.id_group AND lgr.status = {int:status_open})
		WHERE (mg.id_group IN ({array_int:group_list})
			OR mg.group_type > {int:nonjoin_group_id})
			AND mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:moderator_group}
		ORDER BY group_name',
		array(
			'group_list' => $groups,
			'selected_member' => $memID,
			'status_open' => 0,
			'nonjoin_group_id' => 1,
			'min_posts' => -1,
			'moderator_group' => 3,
		)
	);
	// This beast will be our group holder.
	$context['groups'] = array(
		'member' => array(),
		'available' => array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Can they edit their primary group?
		if (($row['id_group'] == $context['primary_group'] && $row['group_type'] > 1) || ($row['hidden'] != 2 && $context['primary_group'] == 0 && in_array($row['id_group'], $groups)))
			$context['can_edit_primary'] = true;

		// If they can't manage (protected) groups, and it's not publically joinable or already assigned, they can't see it.
		if (((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0)) && $row['id_group'] != $context['primary_group'])
			continue;

		$context['groups'][in_array($row['id_group'], $groups) ? 'member' : 'available'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'desc' => $row['description'],
			'color' => $row['online_color'],
			'type' => $row['group_type'],
			'pending' => $row['pending'],
			'is_primary' => $row['id_group'] == $context['primary_group'],
			'can_be_primary' => $row['hidden'] != 2,
			// Anything more than this needs to be done through account settings for security.
			'can_leave' => $row['id_group'] != 1 && $row['group_type'] > 1 ? true : false,
		);
	}
	$smcFunc['db_free_result']($request);

	// Add registered members on the end.
	$context['groups']['member'][0] = array(
		'id' => 0,
		'name' => $txt['regular_members'],
		'desc' => $txt['regular_members_desc'],
		'type' => 0,
		'is_primary' => $context['primary_group'] == 0 ? true : false,
		'can_be_primary' => true,
		'can_leave' => 0,
	);

	// No changing primary one unless you have enough groups!
	if (count($context['groups']['member']) < 2)
		$context['can_edit_primary'] = false;

	// In the special case that someone is requesting membership of a group, setup some special context vars.
	if (isset($_REQUEST['request']) && isset($context['groups']['available'][(int) $_REQUEST['request']]) && $context['groups']['available'][(int) $_REQUEST['request']]['type'] == 2)
		$context['group_request'] = $context['groups']['available'][(int) $_REQUEST['request']];
}

/**
 * This function actually makes all the group changes
 *
 * @param array $profile_vars The profile variables
 * @param array $post_errors Any errors that have occurred
 * @param int $memID The ID of the member
 * @return string What type of change this is - 'primary' if changing the primary group, 'request' if requesting to join a group or 'free' if it's an open group
 */
function groupMembership2($profile_vars, $post_errors, $memID)
{
	global $user_info, $context, $user_profile, $modSettings, $smcFunc;

	// Let's be extra cautious...
	if (!$context['user']['is_owner'] || empty($modSettings['show_group_membership']))
		isAllowedTo('manage_membergroups');
	if (!isset($_REQUEST['gid']) && !isset($_POST['primary']))
		fatal_lang_error('no_access', false);

	checkSession(isset($_GET['gid']) ? 'get' : 'post');

	$old_profile = &$user_profile[$memID];
	$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	$context['can_manage_protected'] = allowedTo('admin_forum');

	// By default the new primary is the old one.
	$newPrimary = $old_profile['id_group'];
	$addGroups = array_flip(explode(',', $old_profile['additional_groups']));
	$canChangePrimary = $old_profile['id_group'] == 0 ? 1 : 0;
	$changeType = isset($_POST['primary']) ? 'primary' : (isset($_POST['req']) ? 'request' : 'free');

	// One way or another, we have a target group in mind...
	$group_id = isset($_REQUEST['gid']) ? (int) $_REQUEST['gid'] : (int) $_POST['primary'];
	$foundTarget = $changeType == 'primary' && $group_id == 0 ? true : false;

	// Sanity check!!
	if ($group_id == 1)
		isAllowedTo('admin_forum');
	// Protected groups too!
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			array(
				'current_group' => $group_id,
				'limit' => 1,
			)
		);
		list ($is_protected) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if ($is_protected == 1)
			isAllowedTo('admin_forum');
	}

	// What ever we are doing, we need to determine if changing primary is possible!
	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_type, hidden, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({int:group_list}, {int:current_group})',
		array(
			'group_list' => $group_id,
			'current_group' => $old_profile['id_group'],
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Is this the new group?
		if ($row['id_group'] == $group_id)
		{
			$foundTarget = true;
			$group_name = $row['group_name'];

			// Does the group type match what we're doing - are we trying to request a non-requestable group?
			if ($changeType == 'request' && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);
			// What about leaving a requestable group we are not a member of?
			elseif ($changeType == 'free' && $row['group_type'] == 2 && $old_profile['id_group'] != $row['id_group'] && !isset($addGroups[$row['id_group']]))
				fatal_lang_error('no_access', false);
			elseif ($changeType == 'free' && $row['group_type'] != 3 && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);

			// We can't change the primary group if this is hidden!
			if ($row['hidden'] == 2)
				$canChangePrimary = false;
		}

		// If this is their old primary, can we change it?
		if ($row['id_group'] == $old_profile['id_group'] && ($row['group_type'] > 1 || $context['can_manage_membergroups']) && $canChangePrimary !== false)
			$canChangePrimary = 1;

		// If we are not doing a force primary move, don't do it automatically if current primary is not 0.
		if ($changeType != 'primary' && $old_profile['id_group'] != 0)
			$canChangePrimary = false;

		// If this is the one we are acting on, can we even act?
		if ((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0))
			$canChangePrimary = false;
	}
	$smcFunc['db_free_result']($request);

	// Didn't find the target?
	if (!$foundTarget)
		fatal_lang_error('no_access', false);

	// Final security check, don't allow users to promote themselves to admin.
	if ($context['can_manage_membergroups'] && !allowedTo('admin_forum'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(permission)
			FROM {db_prefix}permissions
			WHERE id_group = {int:selected_group}
				AND permission = {string:admin_forum}
				AND add_deny = {int:not_denied}',
			array(
				'selected_group' => $group_id,
				'not_denied' => 1,
				'admin_forum' => 'admin_forum',
			)
		);
		list ($disallow) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if ($disallow)
			isAllowedTo('admin_forum');
	}

	// If we're requesting, add the note then return.
	if ($changeType == 'request')
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}log_group_requests
			WHERE id_member = {int:selected_member}
				AND id_group = {int:selected_group}
				AND status = {int:status_open}',
			array(
				'selected_member' => $memID,
				'selected_group' => $group_id,
				'status_open' => 0,
			)
		);
		if ($smcFunc['db_num_rows']($request) != 0)
			fatal_lang_error('profile_error_already_requested_group');
		$smcFunc['db_free_result']($request);

		// Log the request.
		$smcFunc['db_insert']('',
			'{db_prefix}log_group_requests',
			array(
				'id_member' => 'int', 'id_group' => 'int', 'time_applied' => 'int', 'reason' => 'string-65534',
				'status' => 'int', 'id_member_acted' => 'int', 'member_name_acted' => 'string', 'time_acted' => 'int', 'act_reason' => 'string',
			),
			array(
				$memID, $group_id, time(), $_POST['reason'],
				0, 0, '', 0, '',
			),
			array('id_request')
		);

		// Set up some data for our background task...
		$data = $smcFunc['json_encode'](array('id_member' => $memID, 'member_name' => $user_info['name'], 'id_group' => $group_id, 'group_name' => $group_name, 'reason' => $_POST['reason'], 'time' => time()));

		// Add a background task to handle notifying people of this request
		$smcFunc['db_insert']('insert', '{db_prefix}background_tasks',
			array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/GroupReq-Notify.php', 'GroupReq_Notify_Background', $data, 0), array()
		);

		return $changeType;
	}
	// Otherwise we are leaving/joining a group.
	elseif ($changeType == 'free')
	{
		// Are we leaving?
		if ($old_profile['id_group'] == $group_id || isset($addGroups[$group_id]))
		{
			if ($old_profile['id_group'] == $group_id)
				$newPrimary = 0;
			else
				unset($addGroups[$group_id]);
		}
		// ... if not, must be joining.
		else
		{
			// Can we change the primary, and do we want to?
			if ($canChangePrimary)
			{
				if ($old_profile['id_group'] != 0)
					$addGroups[$old_profile['id_group']] = -1;
				$newPrimary = $group_id;
			}
			// Otherwise it's an additional group...
			else
				$addGroups[$group_id] = -1;
		}
	}
	// Finally, we must be setting the primary.
	elseif ($canChangePrimary)
	{
		if ($old_profile['id_group'] != 0)
			$addGroups[$old_profile['id_group']] = -1;
		if (isset($addGroups[$group_id]))
			unset($addGroups[$group_id]);
		$newPrimary = $group_id;
	}

	// Finally, we can make the changes!
	foreach ($addGroups as $id => $dummy)
		if (empty($id))
			unset($addGroups[$id]);
	$addGroups = implode(',', array_flip($addGroups));

	// Ensure that we don't cache permissions if the group is changing.
	if ($context['user']['is_owner'])
		$_SESSION['mc']['time'] = 0;
	else
		updateSettings(array('settings_updated' => time()));

	updateMemberData($memID, array('id_group' => $newPrimary, 'additional_groups' => $addGroups));

	return $changeType;
}

/**
 * Provides interface to setup Two Factor Auth in SMF
 *
 * @param int $memID The ID of the member
 */
function tfasetup($memID)
{
	global $user_info, $context, $user_settings, $sourcedir, $modSettings, $smcFunc;

	require_once($sourcedir . '/Class-TOTP.php');
	require_once($sourcedir . '/Subs-Auth.php');

	// load JS lib for QR
	loadJavaScriptFile('qrcode.js', array('force_current' => false, 'validate' => true));

	// If TFA has not been setup, allow them to set it up
	if (empty($user_settings['tfa_secret']) && $context['user']['is_owner'])
	{
		// Check to ensure we're forcing SSL for authentication
		if (!empty($modSettings['force_ssl']) && empty($maintenance) && !httpsOn())
			fatal_lang_error('login_ssl_required');

		// In some cases (forced 2FA or backup code) they would be forced to be redirected here,
		// we do not want too much AJAX to confuse them.
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' && !isset($_REQUEST['backup']) && !isset($_REQUEST['forced']))
		{
			$context['from_ajax'] = true;
			$context['template_layers'] = array();
		}

		// When the code is being sent, verify to make sure the user got it right
		if (!empty($_REQUEST['save']) && !empty($_SESSION['tfa_secret']))
		{
			$code = $_POST['tfa_code'];
			$totp = new \TOTP\Auth($_SESSION['tfa_secret']);
			$totp->setRange(1);
			$valid_password = hash_verify_password($user_settings['member_name'], trim($_POST['passwd']), $user_settings['passwd']);
			$valid_code = strlen($code) == $totp->getCodeLength() && $totp->validateCode($code);

			if ($valid_password && $valid_code)
			{
				$backup = substr(sha1($smcFunc['random_int']()), 0, 16);
				$backup_encrypted = hash_password($user_settings['member_name'], $backup);

				updateMemberData($memID, array(
					'tfa_secret' => $_SESSION['tfa_secret'],
					'tfa_backup' => $backup_encrypted,
				));

				setTFACookie(3153600, $memID, hash_salt($backup_encrypted, $user_settings['password_salt']));

				unset($_SESSION['tfa_secret']);

				$context['tfa_backup'] = $backup;
				$context['sub_template'] = 'tfasetup_backup';

				return;
			}
			else
			{
				$context['tfa_secret'] = $_SESSION['tfa_secret'];
				$context['tfa_error'] = !$valid_code;
				$context['tfa_pass_error'] = !$valid_password;
				$context['tfa_pass_value'] = $_POST['passwd'];
				$context['tfa_value'] = $_POST['tfa_code'];
			}
		}
		else
		{
			$totp = new \TOTP\Auth();
			$secret = $totp->generateCode();
			$_SESSION['tfa_secret'] = $secret;
			$context['tfa_secret'] = $secret;
			$context['tfa_backup'] = isset($_REQUEST['backup']);
		}

		$context['tfa_qr_url'] = $totp->getQrCodeUrl($context['forum_name'] . ':' . $user_info['name'], $context['tfa_secret']);
	}
	else
		redirectexit('action=profile;area=account;u=' . $memID);
}

/**
 * Provides interface to disable two-factor authentication in SMF
 *
 * @param int $memID The ID of the member
 */
function tfadisable($memID)
{
	global $context, $modSettings, $smcFunc, $user_settings;

	if (!empty($user_settings['tfa_secret']))
	{
		// Bail if we're forcing SSL for authentication and the network connection isn't secure.
		if (!empty($modSettings['force_ssl']) && !httpsOn())
			fatal_lang_error('login_ssl_required', false);

		// The admin giveth...
		elseif ($modSettings['tfa_mode'] == 3 && $context['user']['is_owner'])
			fatal_lang_error('cannot_disable_tfa', false);
		elseif ($modSettings['tfa_mode'] == 2 && $context['user']['is_owner'])
		{
			$groups = array($user_settings['id_group']);
			if (!empty($user_settings['additional_groups']))
				$groups = array_unique(array_merge($groups, explode(',', $user_settings['additional_groups'])));

			$request = $smcFunc['db_query']('', '
				SELECT id_group
				FROM {db_prefix}membergroups
				WHERE tfa_required = {int:tfa_required}
					AND id_group IN ({array_int:groups})',
				array(
					'tfa_required' => 1,
					'groups' => $groups,
				)
			);
			// They belong to a membergroup that requires tfa.
			if (!empty($smcFunc['db_num_rows']($request)))
				fatal_lang_error('cannot_disable_tfa2', false);
			$smcFunc['db_free_result']($request);
		}
	}
	else
		redirectexit('action=profile;area=account;u=' . $memID);
}

?>