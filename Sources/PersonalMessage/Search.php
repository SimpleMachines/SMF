<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\PersonalMessage;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\PageIndex;
use SMF\Search\SearchApi;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows the search form.
 *
 * @todo Allow searching in the sent items folder.
 */
class Search
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The folder being viewed. Either 'inbox' or 'sent'.
	 */
	public string $folder = 'inbox';

	/**
	 * @var int
	 *
	 * ID number of the current label, or -1 for the main inbox folder.
	 */
	public int $current_label_id = -1;

	/**
	 * @var array
	 *
	 * The user-specified search parameters.
	 */
	public array $params = [];

	/**
	 * @var string
	 *
	 * String representation of $this->params.
	 */
	public string $compressed_params = '';

	/**
	 * @var int
	 *
	 * Offset value for pagination purposes.
	 */
	public int $start = 0;

	/**
	 * @var int
	 *
	 * Maximum number of results to list per page.
	 */
	public int $per_page = 30;

	/**
	 * @var array
	 *
	 * Sorting options.
	 *
	 * @todo Add more here?
	 */
	public array $sort_columns = [
		'pm.id_pm',
	];

	/**
	 * @var int
	 *
	 * Maximum number of members to search.
	 */
	public int $max_members_to_search = 500;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Words to highlight in search results.
	 * This is static for the sake of easy access by SearchResult.
	 */
	public static array $to_mark = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Collection of runtime parameters for performing the search.
	 */
	protected array $searchq_parameters = [];

	/**
	 * @var string
	 *
	 * SQL query fragment to search for the requested content.
	 */
	protected string $search_query = '';

	/**
	 * @var string
	 *
	 * SQL query fragment to filter the search by time.
	 */
	protected string $time_query = '';

	/**
	 * @var string
	 *
	 * SQL query fragment to filter the search by member.
	 */
	protected string $user_query = '';

	/**
	 * @var string
	 *
	 * SQL query fragment to filter the search by label.
	 */
	protected string $label_query = '';

	/**
	 * @var string
	 *
	 * SQL query fragment to join tables for filtering the search by label.
	 */
	protected string $label_join = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param bool $inbox Whether we are searching the inbox or sent items.
	 *    This param currently does nothing.
	 */
	public function __construct(int $inbox)
	{
		/*
		 * @todo For the moment force the folder to the inbox.
		 * @todo Maybe set the inbox based on a cookie or theme setting?
		 */
		// $this->folder = $inbox ? 'inbox' : 'sent';

		Label::load();

		$this->start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
		$this->per_page = Config::$modSettings['search_results_per_page'];
		$this->current_label_id = isset($_REQUEST['l']) && isset(Label::$loaded[$_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;

		Utils::$context['start'] = &$this->start;
		Utils::$context['params'] = &$this->compressed_params;

		Utils::$context['page_title'] = Lang::$txt['pm_search_title'];
		Menu::$loaded['pm']['current_area'] = 'search';
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=search',
			'name' => Lang::$txt['pm_search_bar_title'],
		];
	}

	/**
	 * Allows searching through personal messages.
	 */
	public function showForm(): void
	{
		$this->setParams();

		if (isset($_REQUEST['search'])) {
			$this->params['search'] = Utils::htmlspecialcharsDecode($_REQUEST['search']);
		}

		$this->setContextualParams();

		// Create the array of labels to be searched.
		Utils::$context['search_labels'] = [];

		$searchedLabels = isset($this->params['labels']) && $this->params['labels'] != '' ? explode(',', $this->params['labels']) : [];

		foreach (Label::$loaded as $label) {
			Utils::$context['search_labels'][] = [
				'id' => $label['id'],
				'name' => $label['name'],
				'checked' => !empty($searchedLabels) ? in_array($label['id'], $searchedLabels) : true,
			];
		}

		// Are all the labels checked?
		Utils::$context['check_all'] = empty($searchedLabels) || count(Utils::$context['search_labels']) == count($searchedLabels);

		// Load the error text strings if there were errors in the search.
		if (!empty(Utils::$context['search_errors'])) {
			Lang::load('Errors');

			Utils::$context['search_errors']['messages'] = [];

			foreach (Utils::$context['search_errors'] as $search_error => $dummy) {
				if ($search_error == 'messages') {
					continue;
				}

				Utils::$context['search_errors']['messages'][] = Lang::$txt['error_' . $search_error];
			}
		}

		Utils::$context['sub_template'] = 'search';
	}

	/**
	 * Actually does the search of personal messages, and shows the results.
	 */
	public function performSearch(): void
	{
		if (!empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_search']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_search']) {
			ErrorHandler::fatalLang('loadavg_search_disabled', false);
		}

		// Some useful general permissions.
		Utils::$context['can_send_pm'] = User::$me->allowedTo('pm_send');

		$this->setParams();

		// What are we actually searching for?
		$this->params['search'] = !empty($this->params['search']) ? $this->params['search'] : ($_REQUEST['search'] ?? '');

		// If we ain't got nothing, we should error!
		if (!isset($this->params['search']) || $this->params['search'] == '') {
			Utils::$context['search_errors']['invalid_search_string'] = true;
		}

		$this->setTimeQuery();
		$this->setUserQuery();
		$this->setLabelQuery();

		// Give the params to the theme in compressed and uncompressed forms.
		$this->setContextualParams();
		$this->compressParams();

		// Build the query to search for the requested content.
		$this->setSearchQuery();

		// If we have errors, return to the form...
		if (!empty(Utils::$context['search_errors'])) {
			$_REQUEST['params'] = $this->compressed_params;

			$this->showForm();

			return;
		}

		// Get all the matching messages... using standard search only (No caching and the like!)
		$pms = [];
		$posters = [];

		// @todo This doesn't support sent item searching yet.
		$request = Db::$db->query(
			'',
			'SELECT pm.id_pm, pm.id_member_from
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				' . $this->label_join . '
			WHERE ' . ($this->folder === 'inbox' ? '
				pmr.id_member = {int:me}
				AND pmr.deleted = {int:not_deleted}' : '
				pm.id_member_from = {int:me}
				AND pm.deleted_by_sender = {int:not_deleted}') . '
				' . $this->user_query . $this->label_query . $this->time_query . '
				AND (' . $this->search_query . ')
			ORDER BY {raw:sort} {raw:sort_dir}',
			array_merge($this->searchq_parameters, [
				'me' => User::$me->id,
				'not_deleted' => 0,
				'sort' => $this->params['sort'],
				'sort_dir' => $this->params['sort_dir'],
			]),
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$pms[] = $row['id_pm'];
			$posters[] = $row['id_member_from'];
		}
		Db::$db->free_result($request);

		Utils::$context['num_results'] = count($pms);
		Utils::$context['messages'] = array_slice($pms, $this->start, $this->per_page);
		Utils::$context['posters'] = array_slice($posters, $this->start, $this->per_page);

		// Load the users...
		User::load(Utils::$context['posters']);

		// Sort out the page index.
		Utils::$context['page_index'] = new PageIndex(
			Config::$scripturl . '?action=pm;sa=search2;params=' . $this->compressed_params,
			(int) ($_GET['start'] ?? 0),
			Utils::$context['num_results'],
			$this->per_page,
			false,
		);

		Utils::$context['sub_template'] = 'search_results';

		// If mods want access to the general context values, let them do that now.
		// MOD AUTHORS: If you need access to the messages themselves, use the
		// integrate_pm_search_result hook.
		IntegrationHook::call('integrate_search_pm_context');

		// Set up our generator so that the template can loop through the results.
		Utils::$context['personal_messages'] = SearchResult::getFormatted(Utils::$context['messages']);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets all the values in $this->params.
	 *
	 * ... Well, almost all of them. In particular, $this->params['search'] is
	 * slightly different for showForm() vs. performSearch(), so those methods
	 * each set that parameter value themselves.
	 */
	protected function setParams(): void
	{
		// Extract all the search parameters.
		if (isset($_REQUEST['params'])) {
			$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], [' ' => '+'])));

			foreach ($temp_params as $i => $data) {
				@list($k, $v) = explode('|\'|', $data);
				$this->params[$k] = $v;
			}
		}

		// Store whether simple search was used (needed if the user wants to do another query).
		if (!isset($this->params['advanced'])) {
			$this->params['advanced'] = empty($_REQUEST['advanced']) ? 0 : 1;
		}

		// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
		if (!empty($this->params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2)) {
			$this->params['searchtype'] = 2;
		}

		// Default the user name to a wildcard matching every user (*).
		if (!empty($this->params['user_spec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*')) {
			$this->params['userspec'] = $this->params['userspec'] ?? $_REQUEST['userspec'];
		}

		// Minimum age of messages. Default to zero (don't set param in that case).
		if (!empty($this->params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0)) {
			$this->params['minage'] = !empty($this->params['minage']) ? (int) $this->params['minage'] : (int) $_REQUEST['minage'];
		}

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		if (!empty($this->params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] < 9999)) {
			$this->params['maxage'] = !empty($this->params['maxage']) ? (int) $this->params['maxage'] : (int) $_REQUEST['maxage'];
		}

		$this->params['subject_only'] = !empty($this->params['subject_only']) || !empty($_REQUEST['subject_only']);

		$this->params['show_complete'] = !empty($this->params['show_complete']) || !empty($_REQUEST['show_complete']);

		if (empty($this->params['sort']) && !empty($_REQUEST['sort'])) {
			list($this->params['sort'], $this->params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
		}

		$this->params['sort'] = !empty($this->params['sort']) && in_array($this->params['sort'], $this->sort_columns) ? $this->params['sort'] : 'pm.id_pm';

		$this->params['sort_dir'] = !empty($this->params['sort_dir']) && $this->params['sort_dir'] == 'asc' ? 'asc' : 'desc';
	}

	/**
	 * Keep a record of the search params so the user can edit them.
	 */
	protected function setContextualParams(): void
	{
		Utils::$context['search_params'] = $this->params;

		foreach (['search', 'userspec'] as $key) {
			if (!isset(Utils::$context['search_params'][$key])) {
				continue;
			}

			Utils::$context['search_params'][$key] = Utils::htmlspecialchars(Utils::$context['search_params'][$key]);
		}
	}

	/**
	 * Combine the parameters together for pagination and the like...
	 *
	 * @todo Why not do this the same way as in SearchApi?
	 */
	protected function compressParams(): string
	{
		$temp = [];

		foreach ($this->params as $k => $v) {
			$temp[] = $k . '|\'|' . $v;
		}

		$this->compressed_params = base64_encode(implode('|"|', $temp));

		return $this->compressed_params;
	}

	/**
	 * Sets the value of $this->user_query.
	 */
	protected function setUserQuery(): void
	{
		// If there's no specific user, then don't mention it in the main query.
		if (empty($this->params['userspec'])) {
			$this->user_query = '';

			return;
		}

		$userString = strtr(Utils::htmlspecialchars($this->params['userspec'], ENT_QUOTES), ['&quot;' => '"']);
		$userString = strtr($userString, ['%' => '\\%', '_' => '\\_', '*' => '%', '?' => '_']);

		preg_match_all('~"([^"]+)"~', $userString, $matches);

		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++) {
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0) {
				unset($possible_users[$k]);
			}
		}

		if (empty($possible_users)) {
			$this->user_query = '';

			return;
		}

		// We need to bring this into the query and do it nice and cleanly.
		$where_params = [];
		$where_clause = [];

		foreach ($possible_users as $k => $v) {
			$where_params['name_' . $k] = $v;
			$where_clause[] = '{raw:real_name} LIKE {string:name_' . $k . '}';

			if (!isset($where_params['real_name'])) {
				$where_params['real_name'] = Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name';
			}
		}

		// Who matches those criteria?
		// @todo This doesn't support sent item searching.
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}members
			WHERE ' . implode(' OR ', $where_clause),
			$where_params,
		);

		// Simply do nothing if there're too many members matching the criteria.
		if (Db::$db->num_rows($request) > $this->max_members_to_search) {
			$this->user_query = '';
		} elseif (Db::$db->num_rows($request) == 0) {
			$this->user_query = 'AND pm.id_member_from = 0 AND ({raw:pm_from_name} LIKE {raw:guest_user_name_implode})';

			$this->searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR ' . (Db::$db->case_sensitive ? 'LOWER(pm.from_name)' : 'pm.from_name') . ' LIKE \'', $possible_users) . '\'';

			$this->searchq_parameters['pm_from_name'] = Db::$db->case_sensitive ? 'LOWER(pm.from_name)' : 'pm.from_name';
		} else {
			$memberlist = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$memberlist[] = $row['id_member'];
			}

			$this->user_query = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND ({raw:pm_from_name} LIKE {raw:guest_user_name_implode})))';

			$this->searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR ' . (Db::$db->case_sensitive ? 'LOWER(pm.from_name)' : 'pm.from_name') . ' LIKE \'', $possible_users) . '\'';

			$this->searchq_parameters['member_list'] = $memberlist;

			$this->searchq_parameters['pm_from_name'] = Db::$db->case_sensitive ? 'LOWER(pm.from_name)' : 'pm.from_name';
		}
		Db::$db->free_result($request);
	}

	/**
	 * Sort out any labels we may be searching by.
	 */
	protected function setLabelQuery(): void
	{
		// This is used by the template to tell the user where we searched.
		Utils::$context['search_in'] = [];

		if ($this->folder === 'inbox' && !empty($this->params['advanced']) && !empty(Label::$loaded)) {
			// Came here from pagination?  Put them back into $_REQUEST for sanitization.
			if (isset($this->params['labels'])) {
				$_REQUEST['searchlabel'] = explode(',', $this->params['labels']);
			}

			// Assuming we have some labels - make them all integers.
			if (!empty($_REQUEST['searchlabel']) && is_array($_REQUEST['searchlabel'])) {
				$_REQUEST['searchlabel'] = array_map('intval', $_REQUEST['searchlabel']);
			} else {
				$_REQUEST['searchlabel'] = [];
			}

			// Now that everything is cleaned up a bit, make the labels a param.
			$this->params['labels'] = implode(',', $_REQUEST['searchlabel']);

			// No labels selected? That must be an error!
			if (empty($_REQUEST['searchlabel'])) {
				Utils::$context['search_errors']['no_labels_selected'] = true;
			}
			// Otherwise prepare the query!
			elseif (count($_REQUEST['searchlabel']) != count(Label::$loaded)) {
				// Special case here... "inbox" isn't a real label...
				if (in_array(-1, $_REQUEST['searchlabel'])) {
					Utils::$context['search_in'][] = Label::$loaded[-1]['name'];

					$this->label_query = '	AND pmr.in_inbox = {int:in_inbox}';
					$this->searchq_parameters['in_inbox'] = 1;

					// Now we get rid of that...
					$temp = array_diff($_REQUEST['searchlabel'], [-1]);
					$_REQUEST['searchlabel'] = $temp;
				}

				// Still have something?
				if (!empty($_REQUEST['searchlabel'])) {
					if ($this->label_query == '') {
						// Not searching the inbox - PM must be labeled
						$this->label_query = ' AND pml.id_label IN ({array_int:labels})';
						$this->label_join = ' INNER JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)';
					} else {
						// Searching the inbox - PM doesn't have to be labeled
						$this->label_query = ' AND (' . substr($this->label_query, 5) . ' OR pml.id_label IN ({array_int:labels}))';
						$this->label_join = ' LEFT JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)';
					}

					$this->searchq_parameters['labels'] = $_REQUEST['searchlabel'];

					foreach ($_REQUEST['searchlabel'] as $label_key) {
						Utils::$context['search_in'][] = Label::$loaded[$label_key]['name'];
					}
				}
			}
		}

		if (empty(Utils::$context['search_in'])) {
			Utils::$context['search_in'][] = $this->folder;
		}
	}

	/**
	 * Age limits?
	 */
	protected function setTimeQuery(): void
	{
		$this->time_query = '';

		if (!empty($this->params['minage'])) {
			$this->time_query .= ' AND pm.msgtime < ' . (time() - $this->params['minage'] * 86400);
		}

		if (!empty($this->params['maxage'])) {
			$this->time_query .= ' AND pm.msgtime > ' . (time() - $this->params['maxage'] * 86400);
		}
	}

	/**
	 * Sets the value of $this->search_query.
	 * Also sets self::$to_mark.
	 */
	protected function setSearchQuery(): void
	{
		// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
		preg_match_all('~(?:^|\s)([-]?)"([^"]+)"(?:$|\s)~' . (Utils::$context['utf8'] ? 'u' : ''), $this->params['search'], $matches, PREG_PATTERN_ORDER);

		$searchArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$tempSearch = explode(' ', preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~' . (Utils::$context['utf8'] ? 'u' : ''), ' ', $this->params['search']));

		// A minus sign in front of a word excludes the word.... so...
		$excludedWords = [];

		// .. first, we check for things like -"some words", but not "-some words".
		foreach ($matches[1] as $index => $word) {
			if ($word == '-') {
				$word = Utils::strtolower(trim($searchArray[$index]));

				if (strlen($word) > 0) {
					$excludedWords[] = $word;
				}

				unset($searchArray[$index]);
			}
		}

		// Now we look for -test, etc.... normaller.
		foreach ($tempSearch as $index => $word) {
			if (strpos(trim($word), '-') === 0) {
				$word = substr(Utils::strtolower($word), 1);

				if (strlen($word) > 0) {
					$excludedWords[] = $word;
				}

				unset($tempSearch[$index]);
			}
		}

		$searchArray = array_merge($searchArray, $tempSearch);

		// Trim everything and make sure there are no words that are the same.
		foreach ($searchArray as $index => $value) {
			$searchArray[$index] = Utils::strtolower(trim($value));

			if ($searchArray[$index] == '') {
				unset($searchArray[$index]);
			} else {
				// Sort out entities first.
				$searchArray[$index] = Utils::htmlspecialchars($searchArray[$index]);
			}
		}

		$searchArray = array_unique($searchArray);

		// These are the words and phrases to highlight in the search results.
		self::$to_mark = $searchArray;

		// This contains *everything*
		$searchWords = array_merge($searchArray, $excludedWords);

		// Make sure at least one word is being searched for.
		if (empty($searchArray)) {
			Utils::$context['search_errors']['invalid_search_string'] = true;
		}

		// Compile the subject query part.
		$andQueryParts = [];

		foreach ($searchWords as $index => $word) {
			if ($word == '') {
				continue;
			}

			if ($this->params['subject_only']) {
				$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
			} else {
				$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';
			}

			$this->searchq_parameters['search_' . $index] = '%' . strtr($word, ['_' => '\\_', '%' => '\\%']) . '%';
		}

		$this->search_query = ' 1=1';

		if (!empty($andQueryParts)) {
			$this->search_query = implode(!empty($this->params['searchtype']) && $this->params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Search::exportStatic')) {
	Search::exportStatic();
}

?>