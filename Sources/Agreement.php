<?php

/**
 * This file handles the user and privacy policy agreements.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\BBCodeParser;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

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
	// What, if anything, do they need to accept?
	Utils::$context['can_accept_agreement'] = !empty(Config::$modSettings['requireAgreement']) && canRequireAgreement();
	Utils::$context['can_accept_privacy_policy'] = !empty(Config::$modSettings['requirePolicyAgreement']) && canRequirePrivacyPolicy();
	Utils::$context['accept_doc'] = Utils::$context['can_accept_agreement'] || Utils::$context['can_accept_privacy_policy'];

	if (!Utils::$context['accept_doc'] || Utils::$context['can_accept_agreement'])
	{
		// Grab the agreement.
		// Have we got a localized one?
		if (file_exists(Config::$boarddir . '/agreement.' . User::$me->language . '.txt'))
			Utils::$context['agreement_file'] = Config::$boarddir . '/agreement.' . User::$me->language . '.txt';
		elseif (file_exists(Config::$boarddir . '/agreement.txt'))
			Utils::$context['agreement_file'] = Config::$boarddir . '/agreement.txt';

		if (!empty(Utils::$context['agreement_file']))
		{
			$cache_id = strtr(Utils::$context['agreement_file'], array(Config::$boarddir => '', '.txt' => '', '.' => '_'));
			Utils::$context['agreement'] = BBCodeParser::load()->parse(file_get_contents(Utils::$context['agreement_file']), true, $cache_id);
		}
		elseif (Utils::$context['can_accept_agreement'])
			fatal_lang_error('error_no_agreement', false);
	}

	if (!Utils::$context['accept_doc'] || Utils::$context['can_accept_privacy_policy'])
	{
		// Have we got a localized policy?
		if (!empty(Config::$modSettings['policy_' . User::$me->language]))
			Utils::$context['privacy_policy'] = BBCodeParser::load()->parse(Config::$modSettings['policy_' . User::$me->language]);
		elseif (!empty(Config::$modSettings['policy_' . Lang::$default]))
			Utils::$context['privacy_policy'] = BBCodeParser::load()->parse(Config::$modSettings['policy_' . Lang::$default]);
		// Then I guess we've got nothing
		elseif (Utils::$context['can_accept_privacy_policy'])
			fatal_lang_error('error_no_privacy_policy', false);
	}
}

function canRequireAgreement()
{
	// Guests can't agree
	if (!empty(User::$me->is_guest) || empty(Config::$modSettings['requireAgreement']))
		return false;

	$agreement_lang = file_exists(Config::$boarddir . '/agreement.' . User::$me->language . '.txt') ? User::$me->language : 'default';

	if (empty(Config::$modSettings['agreement_updated_' . $agreement_lang]))
		return false;

	Utils::$context['agreement_accepted_date'] = empty(Theme::$current->options['agreement_accepted']) ? 0 : Theme::$current->options['agreement_accepted'];

	// A new timestamp means that there are new changes to the registration agreement and must therefore be shown.
	return empty(Theme::$current->options['agreement_accepted']) || Config::$modSettings['agreement_updated_' . $agreement_lang] > Theme::$current->options['agreement_accepted'];
}

function canRequirePrivacyPolicy()
{
	if (!empty(User::$me->is_guest) || empty(Config::$modSettings['requirePolicyAgreement']))
		return false;

	$policy_lang = !empty(Config::$modSettings['policy_' . User::$me->language]) ? User::$me->language : Lang::$default;

	if (empty(Config::$modSettings['policy_updated_' . $policy_lang]))
		return false;

	Utils::$context['privacy_policy_accepted_date'] = empty(Theme::$current->options['policy_accepted']) ? 0 : Theme::$current->options['policy_accepted'];

	return empty(Theme::$current->options['policy_accepted']) || Config::$modSettings['policy_updated_' . $policy_lang] > Theme::$current->options['policy_accepted'];
}

// Let's tell them there's a new agreement
function Agreement()
{
	prepareAgreementContext();

	Lang::load('Agreement');
	Theme::loadTemplate('Agreement');

	$page_title = '';
	if (!empty(Utils::$context['agreement']) && !empty(Utils::$context['privacy_policy']))
		$page_title = Lang::$txt['agreement_and_privacy_policy'];
	elseif (!empty(Utils::$context['agreement']))
		$page_title = Lang::$txt['agreement'];
	elseif (!empty(Utils::$context['privacy_policy']))
		$page_title = Lang::$txt['privacy_policy'];

	Utils::$context['page_title'] = $page_title;
	Utils::$context['linktree'][] = array(
		'url' => Config::$scripturl . '?action=agreement',
		'name' => Utils::$context['page_title'],
	);

	if (isset($_SESSION['old_url']))
		$_SESSION['redirect_url'] = $_SESSION['old_url'];

}

// I solemly swear to no longer chase squirrels.
function AcceptAgreement()
{
	$can_accept_agreement = !empty(Config::$modSettings['requireAgreement']) && canRequireAgreement();
	$can_accept_privacy_policy = !empty(Config::$modSettings['requirePolicyAgreement']) && canRequirePrivacyPolicy();

	if ($can_accept_agreement || $can_accept_privacy_policy)
	{
		checkSession();

		if ($can_accept_agreement)
		{
			Db::$db->insert('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
				array(User::$me->id, 1, 'agreement_accepted', time()),
				array('id_member', 'id_theme', 'variable')
			);
			logAction('agreement_accepted', array('applicator' => User::$me->id), 'user');
		}

		if ($can_accept_privacy_policy)
		{
			Db::$db->insert('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
				array(User::$me->id, 1, 'policy_accepted', time()),
				array('id_member', 'id_theme', 'variable')
			);
			logAction('policy_accepted', array('applicator' => User::$me->id), 'user');
		}
	}

	// Redirect back to chasing those squirrels, er, viewing those memes.
	redirectexit(!empty($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '');
}

?>