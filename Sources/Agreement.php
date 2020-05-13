<?php

/**
 * This file handles the user and privacy policy agreements.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2020 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('Hacking attempt...');

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
	global $boarddir, $context, $modSettings, $user_info, $language;

	if ($context['show_agreement'])
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

		// An agreement file must be present and its text cannot be empty.
		if (empty($context['agreement']))
		{
			if ($_REQUEST['sa'] == 'both')
				$context['show_agreement'] = false;
			else
				fatal_lang_error('no_agreement', false);
		}
	}

	if ($context['show_privacy_policy'])
	{
		// Have we got a localized policy?
		if (!empty($modSettings['policy_' . $user_info['language']]))
			$context['policy'] = parse_bbc($modSettings['policy_' . $user_info['language']]);
		elseif (!empty($modSettings['policy_' . $language]))
			$context['policy'] = parse_bbc($modSettings['policy_' . $language]);
		// Then I guess we've got nothing
		elseif ($_REQUEST['sa'] == 'both')
			$context['show_privacy_policy'] = false;
		else
			fatal_lang_error('no_privacy_policy', false);
	}

	// Nothing to show? That's no good.
	if (!$context['show_agreement'] && !$context['show_privacy_policy'])
		fatal_lang_error('no_agreement', false);
}

function canRequireAgreement()
{
	global $modSettings, $options, $user_info, $context;

	// Guests can't agree
	if ($user_info['is_guest'] || empty($modSettings['requireAgreement']))
		return false;

	$agreement_lang = strpos($context['agreement_file'], 'agreement.' . $user_info['language'] . '.txt') !== false ? $user_info['language'] : 'default';

	if (empty($modSettings['agreement_updated_' . $agreement_lang]))
		return false;

	$context['agreement_accepted_date'] = empty($options['agreement_accepted']) ? 0 : $options['agreement_accepted'];

	// A new timestamp means that there are new changes to the registration agreement and must therefore be shown.
	return empty($options['agreement_accepted']) || $modSettings['agreement_updated_' . $agreement_lang] > $options['agreement_accepted'];
}

function canRequirePrivacyPolicy()
{
	global $modSettings, $options, $user_info, $language, $context;

	if ($user_info['is_guest'] || empty($modSettings['requirePolicyAgreement']))
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
	global $context, $scripturl, $txt;

	// Keep it sanitary
	if (!isset($_REQUEST['sa']) || !in_array($_REQUEST['sa'], array('agreement', 'policy')))
		$_REQUEST['sa'] = 'both';

	// Now, what do we actually want to show?
	$context['show_agreement'] = in_array($_REQUEST['sa'], array('agreement', 'both'));
	$context['show_privacy_policy'] = in_array($_REQUEST['sa'], array('policy', 'both'));

	prepareAgreementContext();

	// What, if anything, do they need to accept?
	$context['can_accept_agreement'] = $context['show_agreement'] && canRequireAgreement();
	$context['can_accept_privacy_policy'] = $context['show_privacy_policy'] && canRequirePrivacyPolicy();

	// The form will need to tell us what they are accepting
	if ($context['can_accept_agreement'] && $context['can_accept_privacy_policy'])
		$context['accept_doc'] = 'both';
	elseif ($context['can_accept_agreement'])
		$context['accept_doc'] = 'agreement';
	elseif ($context['can_accept_privacy_policy'])
		$context['accept_doc'] = 'policy';

	loadLanguage('Agreement');
	loadTemplate('Agreement');

	if ($context['show_agreement'] && $context['show_privacy_policy'])
		$page_title = $txt['agreement_and_privacy_policy'];
	elseif ($context['show_agreement'])
		$page_title = $txt['agreement'];
	elseif ($context['show_privacy_policy'])
		$page_title = $txt['privacy_policy'];

	if (isset($_SESSION['old_url']))
		$_SESSION['redirect_url'] = $_SESSION['old_url'];
	$context['page_title'] = $page_title;
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=agreement',
		'name' => $txt['agreement_and_privacy_policy'],
	);

	if ($_REQUEST['sa'] !== 'both')
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=agreement;sa=' . $_REQUEST['sa'],
			'name' => $context['page_title'],
		);
}

// I solemly swear to no longer chase squirrels.
function AcceptAgreement()
{
	global $smcFunc, $user_info, $context;

	// No funny business allowed
	if (!isset($_REQUEST['doc']) || !in_array($_REQUEST['doc'], array('agreement', 'policy', 'both')))
		redirectexit('action=agreement');

	// Zero in on the agreement for this...
	$context['show_agreement'] = in_array($_REQUEST['doc'], array('agreement', 'both'));
	$context['show_privacy_policy'] = in_array($_REQUEST['doc'], array('policy', 'both'));

	prepareAgreementContext();

	// Can they agree to what they are trying to agree to?
	if ($context['show_agreement'] && !canRequireAgreement())
		redirectexit('action=agreement');
	if ($context['show_privacy_policy'] && !canRequirePrivacyPolicy())
		redirectexit('action=agreement');

	checkSession();

	if (in_array($_REQUEST['doc'], array('agreement', 'both')))
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			array($user_info['id'], 1, 'agreement_accepted', time()),
			array('id_member', 'id_theme', 'variable')
		);
		logAction('agreement_accepted', array('applicator' => $user_info['id']), 'user');
	}

	if (in_array($_REQUEST['doc'], array('policy', 'both')))
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			array($user_info['id'], 1, 'policy_accepted', time()),
			array('id_member', 'id_theme', 'variable')
		);
		logAction('policy_accepted', array('applicator' => $user_info['id']), 'user');
	}

	// Redirect back to chasing those squirrels, er, viewing those memes.
	redirectexit(!empty($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '');
}

?>