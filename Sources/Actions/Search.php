<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Routable;
use SMF\Sapi;
use SMF\Search\SearchApi;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Verifier;

/**
 * Shows the search form.
 */
class Search implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Ask the user what they want to search for.
	 *
	 * What it does:
	 * - shows the screen to search forum posts (action=search)
	 * - uses the main sub template of the Search template.
	 * - uses the Search language file.
	 * - requires the search_posts permission.
	 * - decodes and loads search parameters given in the URL (if any).
	 * - the form submits to index.php?action=search2.
	 */
	public function execute(): void
	{
		// Is the load average too high to allow searching just now?
		if (Sapi::isOverloaded(Config::$modSettings['loadavg_search'] ?? null)) {
			ErrorHandler::fatalLang('loadavg_search_disabled', false);
		}

		// You cannot search with cookies disabled when captcha is required for guest searches
		if (empty($_COOKIE) && !empty(Config::$modSettings['search_enable_captcha'])) {
			ErrorHandler::fatalLang('func_cookie_error', false);
		}

		// Don't load this in XML mode.
		if (!isset($_REQUEST['xml'])) {
			Theme::loadTemplate('Search');
			Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
		}

		// Check the user's permissions.
		User::$me->isAllowedTo('search_posts');

		// Link tree....
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=search',
			'name' => Lang::getTxt('search', file: 'General'),
		];

		Utils::$context['robot_no_index'] = true;

		Utils::$context['search_string_limit'] = SearchApi::MAX_LENGTH;

		Utils::$context['require_verification'] = User::$me->is_guest && !empty(Config::$modSettings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);

		if (Utils::$context['require_verification']) {
			$verifier = new Verifier(['id' => 'search']);
		}

		// If you got back from search2 by using the linktree, you get your original search parameters back.
		if (isset($_REQUEST['params'])) {
			// Due to IE's 2083 character limit, we have to compress long search strings
			$temp_params = base64_decode(str_replace(['-', '_', '.'], ['+', '/', '='], $_REQUEST['params']));
			// Test for gzuncompress failing
			$temp_params2 = @gzuncompress($temp_params);
			$temp_params = explode('|"|', !empty($temp_params2) ? $temp_params2 : $temp_params);

			Utils::$context['search_params'] = [];

			foreach ($temp_params as $i => $data) {
				@list($k, $v) = explode('|\'|', $data);
				Utils::$context['search_params'][$k] = $v;
			}

			if (isset(Utils::$context['search_params']['brd'])) {
				Utils::$context['search_params']['brd'] = Utils::$context['search_params']['brd'] == '' ? [] : explode(',', Utils::$context['search_params']['brd']);
			}
		}

		if (isset($_REQUEST['search'])) {
			Utils::$context['search_params']['search'] = Utils::htmlspecialcharsDecode($_REQUEST['search']);
		}

		if (isset(Utils::$context['search_params']['search'])) {
			Utils::$context['search_params']['search'] = Utils::htmlspecialchars(Utils::$context['search_params']['search']);
		}

		if (isset(Utils::$context['search_params']['userspec'])) {
			Utils::$context['search_params']['userspec'] = Utils::htmlspecialchars(Utils::$context['search_params']['userspec']);
		}

		if (!empty(Utils::$context['search_params']['searchtype'])) {
			Utils::$context['search_params']['searchtype'] = 2;
		}

		if (!empty(Utils::$context['search_params']['minage'])) {
			Utils::$context['search_params']['minage'] = (int) Utils::$context['search_params']['minage'];
		}

		if (!empty(Utils::$context['search_params']['maxage'])) {
			Utils::$context['search_params']['maxage'] = (int) Utils::$context['search_params']['maxage'];
		}

		Utils::$context['search_params']['show_complete'] = !empty(Utils::$context['search_params']['show_complete']);

		Utils::$context['search_params']['subject_only'] = !empty(Utils::$context['search_params']['subject_only']);

		// Define the inputs in the "options" section of the search form.
		Utils::$context['search_options'] = [
			'show_complete' => [
				'label' => 'search_show_complete_messages',
				'html' => '<input type="checkbox" name="show_complete" id="show_complete" value="1"' . (!empty(Utils::$context['search_params']['show_complete']) ? ' checked' : '') . '>',
			],
			'subject_only' => [
				'label' => 'search_subject_only',
				'html' => '<input type="checkbox" name="subject_only" id="subject_only" value="1"' . (!empty(Utils::$context['search_params']['subject_only']) ? ' checked' : '') . '>',
			],
		];

		SearchApi::load()->formContext();

		// Load the error text strings if there were errors in the search.
		if (!empty(Utils::$context['search_errors'])) {
			Utils::$context['search_errors']['messages'] = [];

			foreach (Utils::$context['search_errors'] as $search_error => $dummy) {
				if ($search_error === 'messages') {
					continue;
				}

				if ($search_error == 'string_too_long') {
					Lang::setTxt(
						'error_string_too_long',
						Lang::getTxt('error_string_too_long', [SearchApi::MAX_LENGTH], file: 'Errors'),
					);
				}

				Utils::$context['search_errors']['messages'][] = Lang::getTxt('error_' . $search_error, file: 'Errors');
			}
		}

		// Find all the boards this user is allowed to see.
		Category::getTree();

		Utils::$context['num_boards'] = 0;
		Utils::$context['boards_check_all'] = true;
		Utils::$context['categories'] = [];

		foreach (Category::$loaded as $category) {
			// Clone it so that we can edit it without touching the real data.
			$cat = clone $category;

			// Remove all redirect boards from the its children.
			$cat->children = array_filter(
				$cat->children,
				fn($board) => empty($board->redirect),
			);

			// Skip empty categories.
			if (empty($cat->children)) {
				continue;
			}

			// Add the category to the list.
			Utils::$context['categories'][$cat->id] = $cat;

			// Figure out which boards to mark as selected.
			foreach ($cat->children as $key => $board) {
				Utils::$context['num_boards']++;

				// If user selected some particular boards, is this one of them?
				if (!empty(Utils::$context['search_params']['brd'])) {
					$board->selected = \in_array($board->id, Utils::$context['search_params']['brd']);
				}
				// User didn't select any boards, so select all except ignored and recycle boards.
				else {
					$board->selected = !$board->recycle && (empty(Config::$modSettings['allow_ignore_boards']) || !\in_array($board->id, User::$me->ignoreboards));
				}

				if (!$board->selected && !$board->recycle) {
					Utils::$context['boards_check_all'] = false;
				}
			}
		}

		// Searching in a topic?
		if (!empty($_REQUEST['topic'])) {
			Utils::$context['search_params']['topic'] = (int) $_REQUEST['topic'];
			Utils::$context['search_params']['show_complete'] = true;
		}

		if (!empty(Utils::$context['search_params']['topic'])) {
			Utils::$context['search_params']['topic'] = (int) Utils::$context['search_params']['topic'];

			Utils::$context['search_topic'] = [
				'id' => Utils::$context['search_params']['topic'],
				'href' => Config::$scripturl . '?topic=' . Utils::$context['search_params']['topic'] . '.0',
			];

			$request = Db::$db->query(
				'SELECT subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_topic = {int:search_topic_id}
					AND {query_see_message_board} ' . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved_true}' : '') . '
				LIMIT 1',
				[
					'is_approved_true' => 1,
					'search_topic_id' => Utils::$context['search_params']['topic'],
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('topic_gone', false);
			}

			list(Utils::$context['search_topic']['subject']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			Utils::$context['search_topic']['link'] = '<a href="' . Utils::$context['search_topic']['href'] . '">' . Utils::$context['search_topic']['subject'] . '</a>';
		}

		Utils::$context['page_title'] = Lang::getTxt('set_parameters', file: 'Search');

		IntegrationHook::call('integrate_search');
	}
}
