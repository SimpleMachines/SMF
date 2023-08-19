<?php

/**
 * This file handles the user and privacy policy agreements.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

if (!defined('SMF'))
	die('No direct access...');

/*	The purpose of this file is to show the user an updated registration
	agreement, and get them to agree to it.

	bool prepareAgreementContext()
		// !!!

	bool canRequireAgreement()
		// !!!

	bool canRequirePrivacyPolicy()
		// !!!

	void Agreement()
		- Show the new registration agreement

	void AcceptAgreement()
		- Called when they actually accept the agreement
		- Save the date of the current agreement to the members database table
		- Redirect back to wherever they came from
*/

function prepareAgreementContext()
{
	global $boarddir, $context, $language, $modSettings, $user_info;

	// What, if anything, do they need to accept?
	$context['can_accept_agreement'] = !empty($modSettings['requireAgreement']) && canRequireAgreement();
	$context['can_accept_privacy_policy'] = !empty($modSettings['requirePolicyAgreement']) && canRequirePrivacyPolicy();
	$context['accept_doc'] = $context['can_accept_agreement'] || $context['can_accept_privacy_policy'];

	if (!$context['accept_doc'] || $context['can_accept_agreement'])
	{
		// Grab the agreement.
		// Have we got a localized one?
		if (file_exists($boarddir . '/agreement.' . $user_info['language'] . '.txt'))
			$context['agreement_file'] = $boarddir . '/agreement.' . $user_info['language'] . '.txt';
		elseif (file_exists($boarddir . '/agreement.txt'))
			$context['agreement_file'] = $boarddir . '/agreement.txt';

		if (!empty($context['agreement_file']))
		{
			$cache_id = strtr($context['agreement_file'], array($boarddir => '', '.txt' => '', '.' => '_'));
			$context['agreement'] = parse_bbc(file_get_contents($context['agreement_file']), true, $cache_id);
		}
		elseif ($context['can_accept_agreement'])
			fatal_lang_error('error_no_agreement', false);
	}

	if (!$context['accept_doc'] || $context['can_accept_privacy_policy'])
	{
		// Have we got a localized policy?
		if (!empty($modSettings['policy_' . $user_info['language']]))
			$context['privacy_policy'] = parse_bbc($modSettings['policy_' . $user_info['language']]);
		elseif (!empty($modSettings['policy_' . $language]))
			$context['privacy_policy'] = parse_bbc($modSettings['policy_' . $language]);
		// Then I guess we've got nothing
		elseif ($context['can_accept_privacy_policy'])
			fatal_lang_error('error_no_privacy_policy', false);
	}
}

function canRequireAgreement()
{
	global $boarddir, $context, $modSettings, $options, $user_info;

	// Guests can't agree
	if (!empty($user_info['is_guest']) || empty($modSettings['requireAgreement']))
		return false;

	$agreement_lang = file_exists($boarddir . '/agreement.' . $user_info['language'] . '.txt') ? $user_info['language'] : 'default';

	if (empty($modSettings['agreement_updated_' . $agreement_lang]))
		return false;

	$context['agreement_accepted_date'] = empty($options['agreement_accepted']) ? 0 : $options['agreement_accepted'];

	// A new timestamp means that there are new changes to the registration agreement and must therefore be shown.
	return empty($options['agreement_accepted']) || $modSettings['agreement_updated_' . $agreement_lang] > $options['agreement_accepted'];
}

function canRequirePrivacyPolicy()
{
	global $modSettings, $options, $user_info, $language, $context;

	if (!empty($user_info['is_guest']) || empty($modSettings['requirePolicyAgreement']))
		return false;

	$policy_lang = !empty($modSettings['policy_' . $user_info['language']]) ? $user_info['language'] : $language;

	if (empty($modSettings['policy_updated_' . $policy_lang]))
		return false;

	$context['privacy_policy_accepted_date'] = empty($options['policy_accepted']) ? 0 : $options['policy_accepted'];

	return empty($options['policy_accepted']) || $modSettings['policy_updated_' . $policy_lang] > $options['policy_accepted'];
}

// Let's tell them there's a new agreement
function Agreement()
{
	global $context, $modSettings, $scripturl, $smcFunc, $txt;

	prepareAgreementContext();

	loadLanguage('Agreement');
	loadTemplate('Agreement');

	$page_title = '';
	if (!empty($context['agreement']) && !empty($context['privacy_policy']))
		$page_title = $txt['agreement_and_privacy_policy'];
	elseif (!empty($context['agreement']))
		$page_title = $txt['agreement'];
	elseif (!empty($context['privacy_policy']))
		$page_title = $txt['privacy_policy'];

	$context['page_title'] = $page_title;
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=agreement',
		'name' => $context['page_title'],
	);

	if (isset($_SESSION['old_url']))
		$_SESSION['redirect_url'] = $_SESSION['old_url'];

}

// I solemly swear to no longer chase squirrels.
function AcceptAgreement()
{
	global $context, $modSettings, $smcFunc, $user_info;

	$can_accept_agreement = !empty($modSettings['requireAgreement']) && canRequireAgreement();
	$can_accept_privacy_policy = !empty($modSettings['requirePolicyAgreement']) && canRequirePrivacyPolicy();

	if ($can_accept_agreement || $can_accept_privacy_policy)
	{
		checkSession();

		if ($can_accept_agreement)
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
				array($user_info['id'], 1, 'agreement_accepted', time()),
				array('id_member', 'id_theme', 'variable')
			);
			logAction('agreement_accepted', array('applicator' => $user_info['id']), 'user');
		}

		if ($can_accept_privacy_policy)
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
				array($user_info['id'], 1, 'policy_accepted', time()),
				array('id_member', 'id_theme', 'variable')
			);
			logAction('policy_accepted', array('applicator' => $user_info['id']), 'user');
		}
	}

	// Redirect back to chasing those squirrels, er, viewing those memes.
	redirectexit(!empty($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '');
}

?>