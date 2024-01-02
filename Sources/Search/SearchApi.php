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

declare(strict_types=1);

namespace SMF\Search;

use SMF\Actions\Search;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PackageManager\SubsPackage;
use SMF\User;
use SMF\Utils;

/**
 * Class SearchApi
 */
abstract class SearchApi implements SearchApiInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'load' => 'findSearchAPI',
			'detect' => 'loadSearchAPIs',
		],
		'prop_names' => [
			'loadedApi' => 'searchAPI',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// This is the hard coded maximum search string length.
	public const MAX_LENGTH = 100;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The maximum SMF version that this will work with.
	 */
	public string $version_compatible = '3.0.999';

	/**
	 * @var string
	 *
	 * The minimum SMF version that this will work with.
	 */
	public string $min_smf_version = '3.0 Alpha 1';

	/**
	 * @var bool
	 *
	 * Whether or not it's supported.
	 */
	public bool $is_supported = true;

	/**
	 * @var float
	 *
	 * Used to calculate relevance.
	 * Specifically, controls the weight assigned for how recent the post is.
	 */
	public float $recentPercentage = 0.30;

	/**
	 * @var int
	 *
	 * Used to calculate relevance.
	 * Specifically, caps the weight assigned to huge topics so that they do
	 * not completely overwhelm the search results.
	 */
	public int $humungousTopicPosts = 200;

	/**
	 * @var int
	 *
	 * If more than this many users match the 'userspec' param, don't bother
	 * searching by name at all.
	 */
	public int $maxMembersToSearch = 500;

	/**
	 * @var int
	 *
	 * Upper limit when performing an indexedWordQuery().
	 * Zero for no limit.
	 */
	public int $maxMessageResults = 0;

	/**
	 * @var array
	 *
	 * Unfortunately, searching for words like these would be slow, so we're
	 * blacklisting them.
	 *
	 * @todo Make this aware of languages.
	 * @todo Should blacklist all BBC.
	 * @todo Setting to add custom values?
	 * @todo Maybe only blacklist if they are the only word, or "any" is used?
	 */
	public array $blacklisted_words = [
		'img',
		'url',
		'quote',
		'www',
		'http',
		'the',
		'is',
		'it',
		'are',
		'if',
	];

	/**
	 * @var array
	 *
	 * Names of columns that results can be sorted by.
	 */
	public array $sort_columns = [
		'relevance',
		'num_replies',
		'id_msg',
	];

	/**
	 * @var array
	 *
	 * The supplied search parameters.
	 * Any unsupplied values will be set to the values in self::$default_params.
	 */
	public array $params = [];

	/**
	 * @var string
	 *
	 * URL-safe variant of a Base64 string representation of $this->params.
	 * The encoded string only includes values where $this->params differs from
	 * the defaults.
	 */
	public string $compressed_params;

	/**
	 * @var array
	 *
	 * Records errors encountered while preparing to search.
	 */
	public array $errors = [];

	/**
	 * @var array
	 *
	 * User-supplied search terms that we have chosen to ignore.
	 */
	public array $ignored = [];

	/**
	 * @var array
	 *
	 * Array of replacements for highlighting.
	 */
	public array $marked = [];

	/**
	 * @var array
	 *
	 * The list of terms to search for.
	 */
	public array $searchArray = [];

	/**
	 * @var array
	 *
	 * Structured list of search term data.
	 */
	public array $searchWords = [];

	/**
	 * @var array
	 *
	 * Terms that the user wants to exclude from the search.
	 */
	public array $excludedWords = [];

	/**
	 * @var array
	 *
	 * Terms to exclude when building a search index.
	 */
	public array $excludedIndexWords = [];

	/**
	 * @var array
	 *
	 * Terms to exclude from a subject search.
	 */
	public array $excludedSubjectWords = [];

	/**
	 * @var array
	 *
	 * Phrases to exclude from the search.
	 */
	public array $excludedPhrases = [];

	/**
	 * @var array
	 *
	 * The results of the search.
	 * Keys are message IDs, values are arrays of relevance data.
	 */
	public array $results = [];

	/**
	 * @var array
	 *
	 * Info about who participated in the search result's topic.
	 * Keys are topic IDs, values are booleans about whether the current user
	 * has posted anything in that topic.
	 */
	public array $participants = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var object
	 *
	 * The loaded search API.
	 *
	 * For backward compatibilty, also referenced as global $searchAPI.
	 */
	public static $loadedApi;

	/**
	 * @var array
	 *
	 * Default values for $this->params.
	 */
	public static array $default_params = [
		'advanced' => false,
		'brd' => [],
		'maxage' => 9999,
		'minage' => 0,
		'search' => '',
		'searchtype' => 1,
		'show_complete' => false,
		'sort' => null,
		'sort_dir' => null,
		'subject_only' => false,
		'topic' => '',
		'userspec' => '',
	];

	/**
	 * @var array
	 *
	 * Info about how to weigh different factors when searching for relevant
	 * results.
	 */
	public static array $weight_factors = [
		'frequency' => [
			'search' => 'COUNT(*) / (MAX(t.num_replies) + 1)',
			'results' => '(t.num_replies + 1)',
		],
		'age' => [
			'search' => 'CASE WHEN MAX(m.id_msg) < {int:min_msg} THEN 0 ELSE (MAX(m.id_msg) - {int:min_msg}) / {int:recent_message} END',
			'results' => 'CASE WHEN t.id_first_msg < {int:min_msg} THEN 0 ELSE (t.id_first_msg - {int:min_msg}) / {int:recent_message} END',
		],
		'length' => [
			'search' => 'CASE WHEN MAX(t.num_replies) < {int:huge_topic_posts} THEN MAX(t.num_replies) / {int:huge_topic_posts} ELSE 1 END',
			'results' => 'CASE WHEN t.num_replies < {int:huge_topic_posts} THEN t.num_replies / {int:huge_topic_posts} ELSE 1 END',
		],
		'subject' => [
			'search' => 0,
			'results' => 0,
		],
		'first_message' => [
			'search' => 'CASE WHEN MIN(m.id_msg) = MAX(t.id_first_msg) THEN 1 ELSE 0 END',
		],
		'sticky' => [
			'search' => 'MAX(t.is_sticky)',
			'results' => 't.is_sticky',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Calculated weight factors.
	 */
	protected array $weight = [];

	/**
	 * @var int
	 *
	 * Weight factor total.
	 * Used to ensure that calculated factors are given the correct percentage.
	 */
	protected int $weight_total = 0;

	/**
	 * @var int
	 *
	 * Messages with IDs less than this will be ignored in the search.
	 */
	protected int $minMsgID = 0;

	/**
	 * @var int
	 *
	 * Messages with IDs greater than this will be ignored in the search.
	 */
	protected int $maxMsgID = 0;

	/**
	 * @var int
	 *
	 * Messages with IDs less than this will get a 0 for the age weight factor.
	 */
	protected int $ageMinMsg = 0;

	/**
	 * @var int
	 *
	 * ID of the most recent message considered for the age weight factor.
	 */
	protected int $ageRecentMsg = 0;

	/**
	 * @var array
	 *
	 * IDs of members to filter our results by.
	 */
	protected array $memberlist = [];

	/**
	 * @var string
	 *
	 * SQL query string to filter results by author.
	 */
	protected string $userQuery = '';

	/**
	 * @var string
	 *
	 * SQL query string to filter results by board.
	 */
	protected string $boardQuery = '';

	/**
	 * @var string
	 *
	 * The SQL match function to use.
	 * If 'RLIKE', search will be performed using regular expressions.
	 * If 'LIKE', search will be performed using simple string matching.
	 */
	protected string $query_match_type = 'LIKE';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->maxMessageResults = empty(Config::$modSettings['search_max_results']) ? 0 : Config::$modSettings['search_max_results'] * 5;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod(string $methodName, array $query_params = []): bool
	{
		switch ($methodName) {
			case 'postRemoved':
			case 'initializeSearch':
			case 'searchQuery':
			case 'setParticipants':
			case 'compressParams':
			case 'getQueryParams':
				return true;

			// All other methods, too bad dunno you.
			default:
				return false;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isValid(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchSort(string $a, string $b): int
	{
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes(string $word, array &$wordsSearch, array &$wordsExclude, bool $isExcluded): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function indexedWordQuery(array $words, array $search_data): mixed
	{
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postRemoved(int $id_msg): void
	{
		$result = Db::$db->query(
			'',
			'SELECT DISTINCT id_search
			FROM {db_prefix}log_search_results
			WHERE id_msg = {int:id_msg}',
			[
				'id_msg' => $id_msg,
			],
		);

		$id_searchs = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			$id_searchs[] = $row['id_search'];
		}

		if (count($id_searchs) < 1) {
			return;
		}

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_search_results
			WHERE id_search in ({array_int:id_searchs})',
			[
				'id_searchs' => $id_searchs,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_search_topics
			WHERE id_search in ({array_int:id_searchs})',
			[
				'id_searchs' => $id_searchs,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_search_messages
			WHERE id_search in ({array_int:id_searchs})',
			[
				'id_searchs' => $id_searchs,
			],
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsRemoved(array $topics): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsMoved(array $topics, int $board_to): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function initializeSearch(): void
	{
		$this->calculateWeight();
		$this->setParams();
		$this->setBlacklistedWords();
		$this->setSearchTerms();
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray): void
	{
		$update_cache = empty($_SESSION['search_cache']) || ($_SESSION['search_cache']['params'] != $this->compressParams());

		// Are the results fresh?
		if (!$update_cache && !empty($_SESSION['search_cache']['id_search'])) {
			$request = Db::$db->query(
				'',
				'SELECT id_search
				FROM {db_prefix}log_search_results
				WHERE id_search = {int:search_id}
				LIMIT 1',
				[
					'search_id' => $_SESSION['search_cache']['id_search'],
				],
			);

			if (Db::$db->num_rows($request) === 0) {
				$update_cache = true;
			}
		}

		if ($update_cache) {
			// Increase the pointer...
			Config::$modSettings['search_pointer'] = empty(Config::$modSettings['search_pointer']) ? 0 : (int) Config::$modSettings['search_pointer'];

			// ...and store it right off.
			Config::updateModSettings(['search_pointer' => Config::$modSettings['search_pointer'] >= 255 ? 0 : Config::$modSettings['search_pointer'] + 1]);

			// As long as you don't change the parameters, the cache result is yours.
			$_SESSION['search_cache'] = [
				'id_search' => Config::$modSettings['search_pointer'],
				'num_results' => -1,
				'params' => $this->compressParams(),
			];

			// Clear the previous cache of the final results cache.
			Db::$db->search_query(
				'delete_log_search_results',
				'
				DELETE FROM {db_prefix}log_search_results
				WHERE id_search = {int:search_id}',
				[
					'search_id' => $_SESSION['search_cache']['id_search'],
				],
			);

			if ($this->params['subject_only']) {
				$this->searchSubjectOnly();
			} else {
				$this->searchSubjectAndMessage();
			}
		}

		$approve_query = '';

		if (!empty(Config::$modSettings['postmod_active'])) {
			// Exclude unapproved topics, but show ones they started.
			if (empty(User::$me->mod_cache['ap'])) {
				$approve_query = '
				AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member})';
			}

			// Show unapproved topics in boards they have access to.
			elseif (User::$me->mod_cache['ap'] !== [0]) {
				$approve_query = '
				AND (t.approved = {int:is_approved} OR t.id_member_started = {int:current_member} OR t.id_board IN ({array_int:approve_boards}))';
			}
		}

		// *** Retrieve the results to be shown on the page
		$request = Db::$db->search_query(
			'',
			'
			SELECT ' . (empty($this->params['topic']) ? 'lsr.id_topic' : $this->params['topic'] . ' AS id_topic') . ', lsr.id_msg, lsr.relevance, lsr.num_matches
			FROM {db_prefix}log_search_results AS lsr' . ($this->params['sort'] == 'num_replies' || !empty($approve_query) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = lsr.id_topic)' : '') . '
			WHERE lsr.id_search = {int:id_search}' . $approve_query . '
			ORDER BY {raw:sort} {raw:sort_dir}
			LIMIT {int:start}, {int:max}',
			[
				'id_search' => $_SESSION['search_cache']['id_search'],
				'sort' => $this->params['sort'],
				'sort_dir' => $this->params['sort_dir'],
				'start' => $_REQUEST['start'],
				'max' => Config::$modSettings['search_results_per_page'],
				'is_approved' => 1,
				'current_member' => User::$me->id,
				'approve_boards' => !empty(Config::$modSettings['postmod_active']) ? User::$me->mod_cache['ap'] : [],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->results[$row['id_msg']] = [
				'relevance' => round($row['relevance'] / 10, 1) . '%',
				'num_matches' => $row['num_matches'],
				'matches' => [],
			];

			// By default they didn't participate in the topic!
			$this->participants[$row['id_topic']] = false;
		}
		Db::$db->free_result($request);

		// Just for the sake of backward compatibility...
		$participants = $this->participants;
		$searchArray = $this->searchArray;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setParticipants(): void
	{
		// If we want to know who participated in what then load this now.
		if (!empty(Config::$modSettings['enableParticipation']) && !User::$me->is_guest) {
			$result = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:current_member}
				GROUP BY id_topic
				LIMIT {int:limit}',
				[
					'current_member' => User::$me->id,
					'topic_list' => array_keys($this->participants),
					'limit' => count($this->participants),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$this->participants[$row['id_topic']] = true;
			}
			Db::$db->free_result($result);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function compressParams(): string
	{
		if (isset($this->compressed_params)) {
			return $this->compressed_params;
		}

		$temp_params = $this->params;

		foreach ($temp_params as $key => $value) {
			if ($temp_params[$key] === self::$default_params[$key]) {
				unset($temp_params[$key]);
			}
		}

		if (isset($temp_params['brd'])) {
			$temp_params['brd'] = implode(',', $temp_params['brd']);
		}

		if (!empty($temp_params)) {
			foreach ($temp_params as $k => $v) {
				$temp_params[$k] = $k . '|\'|' . $v;
			}

			$temp_params = implode('|"|', $temp_params);

			// Due to old IE's 2083 character limit, we try to compress long search strings
			if (($compressed = @gzcompress($temp_params)) !== false) {
				$temp_params = $compressed;
			}

			// Base64 encode, then replace +/= with URI safe ones that can be reverted.
			$this->compressed_params = str_replace(['+', '/', '='], ['-', '_', '.'], base64_encode($temp_params));
		} else {
			$this->compressed_params = '';
		}

		return $this->compressed_params;
	}

	/**
	 * Returns a copy of $this->params with a few extra pieces of data added in.
	 *
	 * This exists only for the sake of backward compatibility; mods extending
	 * this class can already access the included data directly.
	 *
	 * This method is not part of SearchApiInterface, and sub-classes shouldn't
	 * normally need to implement it themselves.
	 *
	 * @return array Data about this search query.
	 */
	public function getQueryParams(): array
	{
		return array_merge($this->params, [
			'min_msg_id' => $this->minMsgID,
			'max_msg_id' => $this->maxMsgID,
			'memberlist' => $this->memberlist,
		]);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Creates a search API and returns the object.
	 *
	 * @return \SMF\Search\SearchApiInterface An instance of the search API interface.
	 */
	final public static function load(): object
	{
		// Load up the search API we are going to use.
		Config::$modSettings['search_index'] = empty(Config::$modSettings['search_index']) ? 'standard' : Config::$modSettings['search_index'];

		$search_class_name = __NAMESPACE__ . '\\APIs\\' . ucwords(Config::$modSettings['search_index']);

		if (!class_exists($search_class_name)) {
			ErrorHandler::fatalLang('search_api_missing');
		}

		// Create an instance of the search API and check it is valid for this version of SMF.
		self::$loadedApi = new $search_class_name();

		// An invalid Search API.
		if (
			!self::$loadedApi
			|| !(self::$loadedApi instanceof SearchApiInterface)
			|| (self::$loadedApi->supportsMethod('isValid') && !self::$loadedApi->isValid())
			|| !SubsPackage::matchPackageVersion(SMF_VERSION, self::$loadedApi->min_smf_version . '-' . self::$loadedApi->version_compatible)
		) {
			// Log the error.
			Lang::load('Errors');

			ErrorHandler::log(sprintf(Lang::$txt['search_api_not_compatible'], 'Search/APIs/' . ucwords(Config::$modSettings['search_index']) . '.php'), 'critical');

			// Fall back to standard search.
			if (Config::$modSettings['search_index'] !== 'standard') {
				Config::$modSettings['search_index'] = 'standard';
				self::load();
			}
			// This should never happen, but...
			else {
				self::$loadedApi = false;
			}
		}

		return self::$loadedApi;
	}

	/**
	 * Get the installed Search API implementations.
	 *
	 * @return array Info about the detected search APIs.
	 */
	final public static function detect(): array
	{
		$loadedApis = [];

		$api_classes = new \GlobIterator(__DIR__ . '/APIs/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($api_classes as $file_path => $file_info) {
			$class_name = $file_info->getBasename('.php');
			$index_name = strtolower($class_name);
			$fully_qualified_class_name = __NAMESPACE__ . '\\APIs\\' . $class_name;

			if (!class_exists($fully_qualified_class_name)) {
				continue;
			}

			$search_api = new $fully_qualified_class_name();

			if (!($search_api instanceof SearchApiInterface) || !($search_api instanceof SearchApi)) {
				continue;
			}

			if (!$search_api->is_supported) {
				continue;
			}

			$loadedApis[$index_name] = [
				'filename' => 'Search/APIs/' . $file_info->getBasename(),
				'setting_index' => $index_name,
				'has_template' => in_array($index_name, ['custom', 'fulltext', 'standard']),
				'label' => $index_name && isset(Lang::$txt['search_index_' . $index_name]) ? Lang::$txt['search_index_' . $index_name] : '',
				'desc' => $index_name && isset(Lang::$txt['search_index_' . $index_name . '_desc']) ? Lang::$txt['search_index_' . $index_name . '_desc'] : '',
			];
		}

		// Check for search APIs using the old SearchAPI-*.php system.
		// Kept for backward compatibility.
		$source_files = new \GlobIterator(Config::$sourcedir . '/SearchAPI-*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($source_files as $file_path => $file_info) {
			// Skip if not a file.
			if (!$file_info->isFile()) {
				continue;
			}

			// Skip if file name doesn't match.
			if (!preg_match('~^SearchAPI-([A-Za-z\d_]+)\.php$~', $file_info->getFilename(), $matches)) {
				continue;
			}

			$index_name = strtolower($matches[1]);
			$class_name = $index_name . '_search';

			// Skip if we already have an API by this name.
			if (isset($loadedApis[$index_name])) {
				continue;
			}

			// Check this is definitely a valid API.
			$fp = fopen($file_info->getPathname(), 'rb');
			$header = fread($fp, 4096);
			fclose($fp);

			if (strpos($header, '* SearchAPI-' . $matches[1] . '.php') === false) {
				continue;
			}

			require_once $file_info->getPathname();

			if (!class_exists($class_name, false)) {
				continue;
			}

			$search_api = new $class_name();

			if (!($search_api instanceof SearchApiInterface) || !($search_api instanceof SearchApi)) {
				continue;
			}

			if (!$search_api->is_supported) {
				continue;
			}

			$loadedApis[$index_name] = [
				'filename' => $file_info->getFilename(),
				'setting_index' => $index_name,
				'has_template' => in_array($index_name, ['custom', 'fulltext', 'standard']),
				'label' => $index_name && isset(Lang::$txt['search_index_' . $index_name]) ? Lang::$txt['search_index_' . $index_name] : '',
				'desc' => $index_name && isset(Lang::$txt['search_index_' . $index_name . '_desc']) ? Lang::$txt['search_index_' . $index_name . '_desc'] : '',
			];
		}

		IntegrationHook::call('integrate_load_search_apis', [&$loadedApis]);

		return $loadedApis;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Calculates the weight values to use when organizing results by relevance.
	 *
	 * This method is not part of SearchApiInterface, and sub-classes shouldn't
	 * normally need to implement it themselves.
	 */
	protected function calculateWeight(): void
	{
		IntegrationHook::call('integrate_search_weights', [&self::$weight_factors]);

		foreach (self::$weight_factors as $weight_factor => $value) {
			$this->weight[$weight_factor] = empty(Config::$modSettings['search_weight_' . $weight_factor]) ? 0 : (int) Config::$modSettings['search_weight_' . $weight_factor];
			$this->weight_total += $this->weight[$weight_factor];
		}

		// Zero weight.  Weightless :P.
		if (empty($this->weight_total)) {
			ErrorHandler::fatalLang('search_invalid_weights');
		}
	}

	/**
	 * Allows changing $this->blacklisted_words.
	 *
	 * This method is not part of SearchApiInterface, and sub-classes shouldn't
	 * normally need to implement it themselves.
	 */
	protected function setBlacklistedWords(): void
	{
		IntegrationHook::call('integrate_search_blacklisted_words', [&$this->blacklisted_words]);
	}

	/**
	 * Figures out the values for $this->params and related properties.
	 *
	 * This method is not part of SearchApiInterface, and sub-classes shouldn't
	 * normally need to implement it themselves.
	 */
	protected function setParams(): void
	{
		if (isset($_REQUEST['params'])) {
			// Due to IE's 2083 character limit, we have to compress long search strings
			$temp_params = base64_decode(str_replace(['-', '_', '.'], ['+', '/', '='], $_REQUEST['params']));

			// Test for gzuncompress failing
			$temp_params2 = @gzuncompress($temp_params);
			$temp_params = explode('|"|', (!empty($temp_params2) ? $temp_params2 : $temp_params));

			foreach ($temp_params as $i => $data) {
				@list($k, $v) = explode('|\'|', $data);
				$this->params[$k] = $v;
			}

			if (isset($this->params['brd'])) {
				$this->params['brd'] = empty($this->params['brd']) ? [] : explode(',', $this->params['brd']);
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

		// Minimum age of messages. Default to zero (don't set param in that case).
		if (!empty($this->params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0)) {
			$this->params['minage'] = !empty($this->params['minage']) ? (int) $this->params['minage'] : (int) $_REQUEST['minage'];
		}

		// Maximum age of messages. Default to infinite (9999 days: param not set).
		if (!empty($this->params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] < 9999)) {
			$this->params['maxage'] = !empty($this->params['maxage']) ? (int) $this->params['maxage'] : (int) $_REQUEST['maxage'];
		}

		// Searching a specific topic?
		if (!empty($_REQUEST['topic']) || (!empty($_REQUEST['search_selection']) && $_REQUEST['search_selection'] == 'topic')) {
			$this->params['topic'] = empty($_REQUEST['search_selection']) ? (int) $_REQUEST['topic'] : (isset($_REQUEST['sd_topic']) ? (int) $_REQUEST['sd_topic'] : '');

			$this->params['show_complete'] = true;
		} elseif (!empty($this->params['topic'])) {
			$this->params['topic'] = (int) $this->params['topic'];
		}

		$this->setMsgBounds();

		// Default the user name to a wildcard matching every user (*).
		if (!empty($this->params['userspec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*')) {
			$this->params['userspec'] = $this->params['userspec'] ?? $_REQUEST['userspec'];
		}

		$this->setUserQuery();

		$this->params['show_complete'] = !empty($this->params['show_complete']) || !empty($_REQUEST['show_complete']);

		$this->params['subject_only'] = !empty($this->params['subject_only']) || !empty($_REQUEST['subject_only']);

		$this->setBoardQuery();

		$this->setSort();

		// Determine some values needed to calculate the relevance.
		$this->ageMinMsg = (int) ((1 - $this->recentPercentage) * Config::$modSettings['maxMsgID']);
		$this->ageRecentMsg = Config::$modSettings['maxMsgID'] - $this->ageMinMsg;

		// *** Parse the search query
		IntegrationHook::call('integrate_search_params', [&$this->params]);

		// What are we searching for?
		if (empty($this->params['search'])) {
			if (isset($_GET['search'])) {
				$this->params['search'] = Utils::htmlspecialcharsDecode($_GET['search']);
			} elseif (isset($_POST['search'])) {
				$this->params['search'] = $_POST['search'];
			}
		}

		// Nothing??
		if (!isset($this->params['search']) || $this->params['search'] == '') {
			$this->errors['invalid_search_string'] = true;
		}
		// Too long?
		elseif (Utils::entityStrlen($this->params['search']) > self::MAX_LENGTH) {
			$this->errors['string_too_long'] = true;
		}

		// Populate anything that hasn't already been set.
		foreach (self::$default_params as $param => $value) {
			if (!isset($this->params[$param])) {
				$this->params[$param] = $value;
			}
		}

		// Ensure all the boards are integers.
		$this->params['brd'] = array_map('intval', (array) $this->params['brd']);
	}

	/**
	 * Populates $this->searchArray, $this->excludedWords, etc.
	 *
	 * This method is not part of SearchApiInterface, and sub-classes shouldn't
	 * normally need to implement it themselves.
	 */
	protected function setSearchTerms(): void
	{
		// Change non-word characters into spaces.
		$stripped_query = preg_replace('~(?:[\x0B\0' . (Utils::$context['utf8'] ? '\x{A0}' : '\xA0') . '\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~' . (Utils::$context['utf8'] ? 'u' : ''), ' ', $this->params['search']);

		// Make the query lower case. It's gonna be case insensitive anyway.
		$stripped_query = Utils::htmlspecialcharsDecode(Utils::strtolower($stripped_query));

		// This (hidden) setting will do fulltext searching in the most basic way.
		if (!empty(Config::$modSettings['search_simple_fulltext'])) {
			$stripped_query = strtr($stripped_query, ['"' => '']);
		}

		// Specify the function to search with. Regex is for word boundaries.
		$this->query_match_type = !empty(Config::$modSettings['search_match_words']) && !preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) ? 'RLIKE' : 'LIKE';

		// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
		preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches, PREG_PATTERN_ORDER);

		$phraseArray = $matches[2];

		// Remove the phrase parts and extract the words.
		$wordArray = preg_replace('~(?:^|\s)[-]?"[^"]+"(?:$|\s)~' . (Utils::$context['utf8'] ? 'u' : ''), ' ', $this->params['search']);

		$wordArray = explode(' ', Utils::htmlspecialchars(Utils::htmlspecialcharsDecode($wordArray), ENT_QUOTES));

		// .. first, we check for things like -"some words", but not "-some words".
		foreach ($matches[1] as $index => $word) {
			if ($word === '-') {
				if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $this->blacklisted_words)) {
					$this->excludedWords[] = $word;
				}

				unset($phraseArray[$index]);
			}
		}

		// Now we look for -test, etc.... normaller.
		foreach ($wordArray as $index => $word) {
			if (strpos(trim($word), '-') === 0) {
				if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $this->blacklisted_words)) {
					$this->excludedWords[] = $word;
				}

				unset($wordArray[$index]);
			}
		}

		// The remaining words and phrases are all included.
		$this->searchArray = array_merge($phraseArray, $wordArray);

		// Trim everything and make sure there are no words that are the same.
		foreach ($this->searchArray as $index => $value) {
			// Skip anything practically empty.
			if (($this->searchArray[$index] = trim($value, '-_\' ')) === '') {
				unset($this->searchArray[$index]);
			}
			// Skip blacklisted words. Make sure to note we skipped them in case we end up with nothing.
			elseif (in_array($this->searchArray[$index], $this->blacklisted_words)) {
				$foundBlackListedWords = true;
				unset($this->searchArray[$index]);
			}
			// Don't allow very, very short words.
			elseif (Utils::entityStrlen($value) < 2) {
				$this->ignored[] = $value;
				unset($this->searchArray[$index]);
			}
		}

		$this->searchArray = array_slice(array_unique($this->searchArray), 0, 10);

		foreach ($this->searchArray as $word) {
			$this->marked[$word] = '<mark class="highlight">' . $word . '</mark>';
		}

		// Initialize two arrays storing the words that have to be searched for.
		$orParts = [];
		$this->searchWords = [];

		// Make sure at least one word is being searched for.
		if (empty($this->searchArray)) {
			$this->errors['invalid_search_string' . (!empty($foundBlackListedWords) ? '_blacklist' : '')] = true;
		}
		// All words/sentences must match.
		elseif (empty($this->params['searchtype'])) {
			$orParts[0] = $this->searchArray;
		}
		// Any word/sentence must match.
		else {
			foreach ($this->searchArray as $index => $value) {
				$orParts[$index] = [$value];
			}
		}

		// Don't allow duplicate error messages if one string is too short.
		if (isset($this->errors['search_string_small_words'], $this->errors['invalid_search_string'])) {
			unset($this->errors['invalid_search_string']);
		}

		// Make sure the excluded words are in all or-branches.
		foreach ($orParts as $orIndex => $andParts) {
			foreach ($this->excludedWords as $word) {
				$orParts[$orIndex][] = $word;
			}
		}

		// Determine the or-branches and the fulltext search words.
		foreach ($orParts as $orIndex => $andParts) {
			$this->searchWords[$orIndex] = [
				'indexed_words' => [],
				'words' => [],
				'subject_words' => [],
				'all_words' => [],
				'complex_words' => [],
			];

			// Sort the indexed words (large words -> small words -> excluded words).
			if ($this->supportsMethod('searchSort')) {
				usort($orParts[$orIndex], [$this, 'searchSort']);
			}

			foreach ($orParts[$orIndex] as $word) {
				$is_excluded = in_array($word, $this->excludedWords);

				$this->searchWords[$orIndex]['all_words'][] = $word;

				$subjectWords = Utils::text2words($word);

				if (!$is_excluded || count($subjectWords) === 1) {
					$this->searchWords[$orIndex]['subject_words'] = array_merge($this->searchWords[$orIndex]['subject_words'], $subjectWords);

					if ($is_excluded) {
						$this->excludedSubjectWords = array_merge($this->excludedSubjectWords, $subjectWords);
					}
				} else {
					$this->excludedPhrases[] = $word;
				}

				// Have we got indexes to prepare?
				if ($this->supportsMethod('prepareIndexes')) {
					$this->prepareIndexes($word, $this->searchWords[$orIndex], $this->excludedIndexWords, $is_excluded);
				}
			}

			// Search_force_index requires all AND parts to have at least one fulltext word.
			if (!empty(Config::$modSettings['search_force_index']) && empty($this->searchWords[$orIndex]['indexed_words'])) {
				$this->errors['query_not_specific_enough'] = true;
				break;
			}

			if ($this->params['subject_only'] && empty($this->searchWords[$orIndex]['subject_words']) && empty($this->excludedSubjectWords)) {
				$this->errors['query_not_specific_enough'] = true;
				break;
			}

			// Make sure we aren't searching for too many indexed words.
			$this->searchWords[$orIndex]['indexed_words'] = array_slice($this->searchWords[$orIndex]['indexed_words'], 0, 7);
			$this->searchWords[$orIndex]['subject_words'] = array_slice($this->searchWords[$orIndex]['subject_words'], 0, 7);
			$this->searchWords[$orIndex]['words'] = array_slice($this->searchWords[$orIndex]['words'], 0, 4);
		}
	}

	/**
	 *
	 */
	protected function wordBoundaryWrapper(string $str): string
	{
		return sprintf(Db::$db->supports_pcre ? '\\b%s\\b' : '[[:<:]]%s[[:>:]]', $str);
	}

	/**
	 *
	 */
	protected function escapeSqlRegex(string $str): string
	{
		return addcslashes(preg_replace('/[\[\]$.+*?&^|{}()]/', '[$0]', $str), '\\\'');
	}

	/**
	 *
	 */
	protected function setMsgBounds(): void
	{
		if (!empty($this->params['minage']) || !empty($this->params['maxage'])) {
			$request = Db::$db->query(
				'',
				'SELECT ' . (empty($this->params['maxage']) ? '0, ' : 'COALESCE(MIN(id_msg), -1), ') . (empty($this->params['minage']) ? '0' : 'COALESCE(MAX(id_msg), -1)') . '
				FROM {db_prefix}messages
				WHERE 1=1' . (Config::$modSettings['postmod_active'] ? '
					AND approved = {int:is_approved_true}' : '') . (empty($this->params['minage']) ? '' : '
					AND poster_time <= {int:timestamp_minimum_age}') . (empty($this->params['maxage']) ? '' : '
					AND poster_time >= {int:timestamp_maximum_age}'),
				[
					'timestamp_minimum_age' => empty($this->params['minage']) ? 0 : time() - 86400 * $this->params['minage'],
					'timestamp_maximum_age' => empty($this->params['maxage']) ? 0 : time() - 86400 * $this->params['maxage'],
					'is_approved_true' => 1,
				],
			);
			list($this->minMsgID, $this->maxMsgID) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if ($this->minMsgID < 0 || $this->maxMsgID < 0) {
				$this->errors['no_messages_in_time_frame'] = true;
			}
		}
	}

	/**
	 *
	 */
	protected function setUserQuery(): void
	{
		// If there's no specific user, then don't mention it in the main query.
		if (empty($this->params['userspec'])) {
			$this->userQuery = '';
		} else {
			$userString = strtr(Utils::htmlspecialchars($this->params['userspec'], ENT_QUOTES), ['&quot;' => '"', '%' => '\\%', '_' => '\\_', '*' => '%', '?' => '_']);

			preg_match_all('~"([^"]+)"~', $userString, $matches);
			$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

			for ($k = 0, $n = count($possible_users); $k < $n; $k++) {
				$possible_users[$k] = trim($possible_users[$k]);

				if (strlen($possible_users[$k]) == 0) {
					unset($possible_users[$k]);
				}
			}

			if (empty($possible_users)) {
				$this->userQuery = '';
			} else {
				// Create a list of database-escaped search names.
				$realNameMatches = [];

				foreach ($possible_users as $possible_user) {
					$realNameMatches[] = Db::$db->quote(
						'{string:possible_user}',
						[
							'possible_user' => $possible_user,
						],
					);
				}

				// Retrieve a list of possible members.
				$request = Db::$db->query(
					'',
					'SELECT id_member
					FROM {db_prefix}members
					WHERE {raw:match_possible_users}',
					[
						'match_possible_users' => 'real_name LIKE ' . implode(' OR real_name LIKE ', $realNameMatches),
					],
				);

				// Simply do nothing if there're too many members matching the criteria.
				if (Db::$db->num_rows($request) > $this->maxMembersToSearch) {
					$this->userQuery = '';
				} elseif (Db::$db->num_rows($request) == 0) {
					$this->userQuery = Db::$db->quote(
						'm.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})',
						[
							'id_member_guest' => 0,
							'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
						],
					);
				} else {
					$this->memberlist = [];

					while ($row = Db::$db->fetch_assoc($request)) {
						$this->memberlist[] = $row['id_member'];
					}

					$this->userQuery = Db::$db->quote(
						'(m.id_member IN ({array_int:matched_members}) OR (m.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})))',
						[
							'matched_members' => $this->memberlist,
							'id_member_guest' => 0,
							'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
						],
					);
				}
				Db::$db->free_result($request);
			}
		}
	}

	/**
	 *
	 */
	protected function setBoardQuery(): void
	{
		// If the boards were passed by URL (params=), temporarily put them back in $_REQUEST.
		if (!empty($this->params['brd']) && is_array($this->params['brd'])) {
			$_REQUEST['brd'] = $this->params['brd'];
		}

		// Ensure that brd is an array.
		if ((!empty($_REQUEST['brd']) && !is_array($_REQUEST['brd'])) || (!empty($_REQUEST['search_selection']) && $_REQUEST['search_selection'] == 'board')) {
			if (!empty($_REQUEST['brd'])) {
				$_REQUEST['brd'] = strpos($_REQUEST['brd'], ',') !== false ? explode(',', $_REQUEST['brd']) : [$_REQUEST['brd']];
			} else {
				$_REQUEST['brd'] = isset($_REQUEST['sd_brd']) ? [$_REQUEST['sd_brd']] : [];
			}
		}

		// Make sure all boards are integers.
		if (!empty($_REQUEST['brd'])) {
			foreach ($_REQUEST['brd'] as $id => $brd) {
				$_REQUEST['brd'][$id] = (int) $brd;
			}
		}

		// Special case for boards: searching just one topic?
		if (!empty($this->params['topic'])) {
			$request = Db::$db->query(
				'',
				'SELECT t.id_board
				FROM {db_prefix}topics AS t
				WHERE t.id_topic = {int:search_topic_id}
					AND {query_see_topic_board}' . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved_true}' : '') . '
				LIMIT 1',
				[
					'search_topic_id' => $this->params['topic'],
					'is_approved_true' => 1,
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('topic_gone', false);
			}

			$this->params['brd'] = [];
			list($this->params['brd'][0]) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}
		// Select all boards you've selected AND are allowed to see.
		elseif (User::$me->is_admin && (!empty($this->params['advanced']) || !empty($_REQUEST['brd']))) {
			$this->params['brd'] = empty($_REQUEST['brd']) ? [] : $_REQUEST['brd'];
		} else {
			$see_board = empty($this->params['advanced']) ? 'query_wanna_see_board' : 'query_see_board';

			$request = Db::$db->query(
				'',
				'SELECT b.id_board
				FROM {db_prefix}boards AS b
				WHERE {raw:boards_allowed_to_see}
					AND redirect = {string:empty_string}' . (empty($_REQUEST['brd']) ? (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
					AND b.id_board != {int:recycle_board_id}' : '') : '
					AND b.id_board IN ({array_int:selected_search_boards})'),
				[
					'boards_allowed_to_see' => User::$me->{$see_board},
					'empty_string' => '',
					'selected_search_boards' => empty($_REQUEST['brd']) ? [] : $_REQUEST['brd'],
					'recycle_board_id' => Config::$modSettings['recycle_board'],
				],
			);

			$this->params['brd'] = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->params['brd'][] = $row['id_board'];
			}

			Db::$db->free_result($request);

			// This error should pro'bly only happen for hackers.
			if (empty($this->params['brd'])) {
				$this->errors['no_boards_selected'] = true;
			}
		}

		if (count($this->params['brd']) != 0) {
			foreach ($this->params['brd'] as $k => $v) {
				$this->params['brd'][$k] = (int) $v;
			}

			// If we've selected all boards, this parameter can be left empty.
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}boards
				WHERE redirect = {string:empty_string}',
				[
					'empty_string' => '',
				],
			);

			list($num_boards) = Db::$db->fetch_row($request);

			Db::$db->free_result($request);

			if (count($this->params['brd']) == $num_boards) {
				$this->boardQuery = '';
			} elseif (count($this->params['brd']) == $num_boards - 1 && !empty(Config::$modSettings['recycle_board']) && !in_array(Config::$modSettings['recycle_board'], $this->params['brd'])) {
				$this->boardQuery = '!= ' . Config::$modSettings['recycle_board'];
			} else {
				$this->boardQuery = 'IN (' . implode(', ', $this->params['brd']) . ')';
			}
		} else {
			$this->boardQuery = '';
		}
	}

	/**
	 * Get the sorting parameters right. Default to sort by relevance descending.
	 */
	protected function setSort(): void
	{
		IntegrationHook::call('integrate_search_sort_columns', [&$this->sort_columns]);

		if (empty($this->params['sort']) && !empty($_REQUEST['sort'])) {
			list($this->params['sort'], $this->params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
		}

		$this->params['sort'] = !empty($this->params['sort']) && in_array($this->params['sort'], $this->sort_columns) ? $this->params['sort'] : 'relevance';

		if (!empty($this->params['topic']) && $this->params['sort'] === 'num_replies') {
			$this->params['sort'] = 'id_msg';
		}

		// Sorting direction: descending unless stated otherwise.
		$this->params['sort_dir'] = !empty($this->params['sort_dir']) && $this->params['sort_dir'] == 'asc' ? 'asc' : 'desc';
	}

	/**
	 *
	 */
	protected function searchSubjectOnly(): void
	{
		// We do this to try and avoid duplicate keys on databases not supporting INSERT IGNORE.
		$inserts = [];

		foreach ($this->searchWords as $orIndex => $words) {
			$subject_query_params = [
				'not_redirected' => 0,
				'never_expires' => 0,
			];
			$subject_query = [
				'from' => '{db_prefix}topics AS t',
				'inner_join' => [],
				'left_join' => [],
				'where' => [
					't.id_redirect_topic = {int:not_redirected}',
					't.redirect_expires = {int:never_expires}',
				],
			];

			if (Config::$modSettings['postmod_active']) {
				$subject_query['where'][] = 't.approved = {int:is_approved}';
			}

			$numTables = 0;
			$prev_join = 0;
			$numSubjectResults = 0;

			foreach ($words['subject_words'] as $subjectWord) {
				$numTables++;

				if (in_array($subjectWord, $this->excludedSubjectWords)) {
					$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty(Config::$modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';

					$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
				} else {
					$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';

					$subject_query['where'][] = 'subj' . $numTables . '.word ' . (empty(Config::$modSettings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}');

					$prev_join = $numTables;
				}

				$subject_query_params['subject_words_' . $numTables] = $subjectWord;
				$subject_query_params['subject_words_' . $numTables . '_wild'] = '%' . $subjectWord . '%';
			}

			if (!empty($this->userQuery)) {
				if ($subject_query['from'] != '{db_prefix}messages AS m') {
					$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_topic = t.id_topic)';
				}

				$subject_query['where'][] = $this->userQuery;
			}

			if (!empty($this->params['topic'])) {
				$subject_query['where'][] = 't.id_topic = ' . $this->params['topic'];
			}

			if (!empty($this->minMsgID)) {
				$subject_query['where'][] = 't.id_first_msg >= ' . $this->minMsgID;
			}

			if (!empty($this->maxMsgID)) {
				$subject_query['where'][] = 't.id_last_msg <= ' . $this->maxMsgID;
			}

			if (!empty($this->boardQuery)) {
				$subject_query['where'][] = 't.id_board ' . $this->boardQuery;
			}

			if (!empty($this->excludedPhrases)) {
				if ($subject_query['from'] != '{db_prefix}messages AS m') {
					$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
				}

				$count = 0;

				foreach ($this->excludedPhrases as $phrase) {
					$subject_query['where'][] = 'm.subject NOT ' . $this->query_match_type . ' {string:excluded_phrases_' . $count . '}';

					if ($this->query_match_type === 'RLIKE') {
						$subject_query_params['excluded_phrases_' . $count++] = $this->wordBoundaryWrapper($this->escapeSqlRegex($phrase));
					} else {
						$subject_query_params['excluded_phrases_' . $count++] = '%' . Db::$db->escape_wildcard_string($phrase) . '%';
					}
				}
			}

			IntegrationHook::call('integrate_subject_only_search_query', [&$subject_query, &$subject_query_params]);

			$relevance = '1000 * (';

			foreach (self::$weight_factors as $type => $value) {
				$relevance .= $this->weight[$type];

				if (!empty($value['results'])) {
					$relevance .= ' * ' . $value['results'];
				}

				$relevance .= ' + ';
			}

			$relevance = substr($relevance, 0, -3) . ') / ' . $this->weight_total . ' AS relevance';

			$ignore_clause = !Db::$db->support_ignore ? '' : '
				INSERT IGNORE INTO {db_prefix}log_search_results
					(id_search, id_topic, relevance, id_msg, num_matches)';

			$ignoreRequest = Db::$db->search_query(
				'insert_log_search_results_subject',
				$ignore_clause . '
				SELECT
					{int:id_search},
					t.id_topic,
					' . $relevance . ',
					' . (empty($this->userQuery) ? 't.id_first_msg' : 'm.id_msg') . ',
					1
				FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
					INNER JOIN ' . implode('
					INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
					LEFT JOIN ' . implode('
					LEFT JOIN ', $subject_query['left_join'])) . '
				WHERE ' . implode('
					AND ', $subject_query['where']) . (empty(Config::$modSettings['search_max_results']) ? '' : '
				LIMIT ' . (Config::$modSettings['search_max_results'] - $numSubjectResults)),
				array_merge($subject_query_params, [
					'id_search' => $_SESSION['search_cache']['id_search'],
					'min_msg' => $this->ageMinMsg,
					'recent_message' => $this->ageMinMsg,
					'huge_topic_posts' => $this->humungousTopicPosts,
					'is_approved' => 1,
				]),
			);

			// If the database doesn't support IGNORE to make this fast we need to do some tracking.
			if (!Db::$db->support_ignore) {
				while ($row = Db::$db->fetch_row($ignoreRequest)) {
					// No duplicates!
					if (isset($inserts[$row[1]])) {
						continue;
					}

					foreach ($row as $key => $value) {
						$inserts[$row[1]][] = (int) $row[$key];
					}
				}
				Db::$db->free_result($ignoreRequest);
				$numSubjectResults = count($inserts);
			} else {
				$numSubjectResults += Db::$db->affected_rows();
			}

			if (!empty(Config::$modSettings['search_max_results']) && $numSubjectResults >= Config::$modSettings['search_max_results']) {
				break;
			}
		}

		// If there's data to be inserted for non-IGNORE databases do it here!
		if (!empty($inserts)) {
			Db::$db->insert(
				'',
				'{db_prefix}log_search_results',
				['id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'int', 'id_msg' => 'int', 'num_matches' => 'int'],
				$inserts,
				['id_search', 'id_topic'],
			);
		}

		$_SESSION['search_cache']['num_results'] = $numSubjectResults;
	}

	/**
	 *
	 */
	protected function searchSubjectAndMessage()
	{
		$main_query = [
			'select' => [
				'id_search' => $_SESSION['search_cache']['id_search'],
				'relevance' => '0',
			],
			'weights' => [],
			'from' => '{db_prefix}topics AS t',
			'inner_join' => [
				'{db_prefix}messages AS m ON (m.id_topic = t.id_topic)',
			],
			'left_join' => [],
			'where' => [
				't.id_redirect_topic = {int:not_redirected}',
				't.redirect_expires = {int:never_expires}',
			],
			'group_by' => [],
			'parameters' => [
				'min_msg' => $this->ageMinMsg,
				'recent_message' => $this->ageRecentMsg,
				'huge_topic_posts' => $this->humungousTopicPosts,
				'is_approved' => 1,
				'not_redirected' => 0,
				'never_expires' => 0,
			],
		];

		if (empty($this->params['topic']) && empty($this->params['show_complete'])) {
			$main_query['select']['id_topic'] = 't.id_topic';
			$main_query['select']['id_msg'] = 'MAX(m.id_msg) AS id_msg';
			$main_query['select']['num_matches'] = 'COUNT(*) AS num_matches';

			$main_query['weights'] = self::$weight_factors;

			$main_query['group_by'][] = 't.id_topic';
		} else {
			// This is outrageous!
			$main_query['select']['id_topic'] = 'm.id_msg AS id_topic';
			$main_query['select']['id_msg'] = 'm.id_msg';
			$main_query['select']['num_matches'] = '1 AS num_matches';

			$main_query['weights'] = [
				'age' => [
					'search' => '((m.id_msg - t.id_first_msg) / CASE WHEN t.id_last_msg = t.id_first_msg THEN 1 ELSE t.id_last_msg - t.id_first_msg END)',
				],
				'first_message' => [
					'search' => 'CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END',
				],
			];

			if (!empty($this->params['topic'])) {
				$main_query['where'][] = 't.id_topic = {int:topic}';
				$main_query['parameters']['topic'] = $this->params['topic'];
			}

			if (!empty($this->params['show_complete'])) {
				$main_query['group_by'][] = 'm.id_msg, t.id_first_msg, t.id_last_msg';
			}
		}

		// *** Get the subject results.
		$numSubjectResults = 0;

		if (empty($this->params['topic'])) {
			$inserts = [];

			// Create a temporary table to store some preliminary results in.
			Db::$db->search_query(
				'drop_tmp_log_search_topics',
				'
				DROP TABLE IF EXISTS {db_prefix}tmp_log_search_topics',
				[
				],
			);

			$createTemporary = Db::$db->search_query(
				'create_tmp_log_search_topics',
				'
				CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_topics (
					id_topic int NOT NULL default {string:string_zero},
					PRIMARY KEY (id_topic)
				) ENGINE=MEMORY',
				[
					'string_zero' => '0',
				],
			) !== false;

			// Clean up some previous cache.
			if (!$createTemporary) {
				Db::$db->search_query(
					'delete_log_search_topics',
					'
					DELETE FROM {db_prefix}log_search_topics
					WHERE id_search = {int:search_id}',
					[
						'search_id' => $_SESSION['search_cache']['id_search'],
					],
				);
			}

			foreach ($this->searchWords as $orIndex => $words) {
				$subject_query = [
					'from' => '{db_prefix}topics AS t',
					'inner_join' => [],
					'left_join' => [],
					'where' => [
						't.id_redirect_topic = {int:not_redirected}',
						't.redirect_expires = {int:never_expires}',
					],
					'params' => [
						'not_redirected' => 0,
						'never_expires' => 0,
					],
				];

				$numTables = 0;
				$prev_join = 0;
				$count = 0;
				$excluded = false;

				foreach ($words['subject_words'] as $subjectWord) {
					$numTables++;

					if (in_array($subjectWord, $this->excludedSubjectWords)) {
						if (($subject_query['from'] != '{db_prefix}messages AS m') && !$excluded) {
							$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
							$excluded = true;
						}

						$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty(Config::$modSettings['search_match_words']) ? 'LIKE {string:subject_not_' . $count . '}' : '= {string:subject_not_' . $count . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';

						$subject_query['params']['subject_not_' . $count] = empty(Config::$modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

						$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';

						$subject_query['where'][] = 'm.body NOT ' . $this->query_match_type . ' {string:body_not_' . $count . '}';

						if ($this->query_match_type === 'RLIKE') {
							$subject_query['params']['body_not_' . $count++] = $this->wordBoundaryWrapper($this->escapeSqlRegex($subjectWord));
						} else {
							$subject_query['params']['body_not_' . $count++] = '%' . Db::$db->escape_wildcard_string($subjectWord) . '%';
						}
					} else {
						$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';

						$subject_query['where'][] = 'subj' . $numTables . '.word LIKE {string:subject_like_' . $count . '}';

						$subject_query['params']['subject_like_' . $count++] = empty(Config::$modSettings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

						$prev_join = $numTables;
					}
				}

				if (!empty($this->userQuery)) {
					if ($subject_query['from'] != '{db_prefix}messages AS m') {
						$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
					}

					$subject_query['where'][] = '{raw:user_query}';
					$subject_query['params']['user_query'] = $this->userQuery;
				}

				if (!empty($this->params['topic'])) {
					$subject_query['where'][] = 't.id_topic = {int:topic}';
					$subject_query['params']['topic'] = $this->params['topic'];
				}

				if (!empty($this->minMsgID)) {
					$subject_query['where'][] = 't.id_first_msg >= {int:min_msg_id}';
					$subject_query['params']['min_msg_id'] = $this->minMsgID;
				}

				if (!empty($this->maxMsgID)) {
					$subject_query['where'][] = 't.id_last_msg <= {int:max_msg_id}';
					$subject_query['params']['max_msg_id'] = $this->maxMsgID;
				}

				if (!empty($this->boardQuery)) {
					$subject_query['where'][] = 't.id_board {raw:board_query}';
					$subject_query['params']['board_query'] = $this->boardQuery;
				}

				if (!empty($this->excludedPhrases)) {
					if ($subject_query['from'] != '{db_prefix}messages AS m') {
						$subject_query['inner_join']['m'] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';
					}

					$count = 0;

					foreach ($this->excludedPhrases as $phrase) {
						$subject_query['where'][] = 'm.subject NOT ' . $this->query_match_type . ' {string:exclude_phrase_' . $count . '}';

						$subject_query['where'][] = 'm.body NOT ' . $this->query_match_type . ' {string:exclude_phrase_' . $count . '}';

						if ($this->query_match_type === 'RLIKE') {
							$subject_query['params']['exclude_phrase_' . $count++] = $this->wordBoundaryWrapper($this->escapeSqlRegex($phrase));
						} else {
							$subject_query['params']['exclude_phrase_' . $count++] = '%' . Db::$db->escape_wildcard_string($phrase) . '%';
						}
					}
				}

				IntegrationHook::call('integrate_subject_search_query', [&$subject_query]);

				// Nothing to search for?
				if (empty($subject_query['where'])) {
					continue;
				}

				$ignore_clause = !Db::$db->support_ignore ? '' : '
					INSERT IGNORE INTO {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics
						(' . ($createTemporary ? '' : 'id_search, ') . 'id_topic)';

				$ignoreRequest = Db::$db->search_query(
					'insert_log_search_topics',
					$ignore_clause . '
					SELECT ' . ($createTemporary ? '' : $_SESSION['search_cache']['id_search'] . ', ') . 't.id_topic
					FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
						INNER JOIN ' . implode('
						INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
						LEFT JOIN ' . implode('
						LEFT JOIN ', $subject_query['left_join'])) . '
					WHERE ' . implode('
						AND ', $subject_query['where']) . (empty(Config::$modSettings['search_max_results']) ? '' : '
					LIMIT ' . (Config::$modSettings['search_max_results'] - $numSubjectResults)),
					$subject_query['params'],
				);

				// Don't do INSERT IGNORE? Manually fix this up!
				if (!Db::$db->support_ignore) {
					while ($row = Db::$db->fetch_row($ignoreRequest)) {
						$ind = $createTemporary ? 0 : 1;

						// No duplicates!
						if (isset($inserts[$row[$ind]])) {
							continue;
						}

						$inserts[$row[$ind]] = $row;
					}
					Db::$db->free_result($ignoreRequest);

					$numSubjectResults = count($inserts);
				} else {
					$numSubjectResults += Db::$db->affected_rows();
				}

				if (!empty(Config::$modSettings['search_max_results']) && $numSubjectResults >= Config::$modSettings['search_max_results']) {
					break;
				}
			}

			// Got some non-MySQL data to plonk in?
			if (!empty($inserts)) {
				Db::$db->insert(
					'',
					('{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics'),
					$createTemporary ? ['id_topic' => 'int'] : ['id_search' => 'int', 'id_topic' => 'int'],
					$inserts,
					$createTemporary ? ['id_topic'] : ['id_search', 'id_topic'],
				);
			}

			if ($numSubjectResults !== 0) {
				$main_query['weights']['subject']['search'] = 'CASE WHEN MAX(lst.id_topic) IS NULL THEN 0 ELSE 1 END';

				$main_query['left_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (' . ($createTemporary ? '' : 'lst.id_search = {int:id_search} AND ') . 'lst.id_topic = t.id_topic)';

				if (!$createTemporary) {
					$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
				}
			}
		}

		$indexedResults = 0;

		// We building an index?
		if ($this->supportsMethod('indexedWordQuery', $this->getQueryParams())) {
			$inserts = [];

			Db::$db->search_query(
				'drop_tmp_log_search_messages',
				'
				DROP TABLE IF EXISTS {db_prefix}tmp_log_search_messages',
				[
				],
			);

			$createTemporary = Db::$db->search_query(
				'create_tmp_log_search_messages',
				'
				CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_messages (
					id_msg int NOT NULL default {string:string_zero},
					PRIMARY KEY (id_msg)
				) ENGINE=MEMORY',
				[
					'string_zero' => '0',
				],
			) !== false;

			// Clear, all clear!
			if (!$createTemporary) {
				Db::$db->search_query(
					'delete_log_search_messages',
					'
					DELETE FROM {db_prefix}log_search_messages
					WHERE id_search = {int:id_search}',
					[
						'id_search' => $_SESSION['search_cache']['id_search'],
					],
				);
			}

			foreach ($this->searchWords as $orIndex => $words) {
				// Search for this word, assuming we have some words!
				if (!empty($words['indexed_words'])) {
					// Variables required for the search.
					$search_data = [
						'insert_into' => ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
						'max_results' => $this->maxMessageResults,
						'indexed_results' => $indexedResults,
						'params' => [
							'id_search' => !$createTemporary ? $_SESSION['search_cache']['id_search'] : 0,
							'excluded_words' => $this->excludedWords,
							'user_query' => !empty($this->userQuery) ? $this->userQuery : '',
							'board_query' => !empty($this->boardQuery) ? $this->boardQuery : '',
							'topic' => !empty($this->params['topic']) ? $this->params['topic'] : 0,
							'min_msg_id' => !empty($this->minMsgID) ? $this->minMsgID : 0,
							'max_msg_id' => !empty($this->maxMsgID) ? $this->maxMsgID : 0,
							'excluded_phrases' => !empty($this->excludedPhrases) ? $this->excludedPhrases : [],
							'excluded_index_words' => !empty($this->excludedIndexWords) ? $this->excludedIndexWords : [],
							'excluded_subject_words' => !empty($this->excludedSubjectWords) ? $this->excludedSubjectWords : [],
						],
					];

					$ignoreRequest = $this->indexedWordQuery($words, $search_data);

					if (!Db::$db->support_ignore) {
						while ($row = Db::$db->fetch_row($ignoreRequest)) {
							// No duplicates!
							if (isset($inserts[$row[0]])) {
								continue;
							}

							$inserts[$row[0]] = $row;
						}
						Db::$db->free_result($ignoreRequest);

						$indexedResults = count($inserts);
					} else {
						$indexedResults += Db::$db->affected_rows();
					}

					if (!empty($maxMessageResults) && $indexedResults >= $maxMessageResults) {
						break;
					}
				}
			}

			// More non-MySQL stuff needed?
			if (!empty($inserts)) {
				Db::$db->insert(
					'',
					'{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
					$createTemporary ? ['id_msg' => 'int'] : ['id_msg' => 'int', 'id_search' => 'int'],
					$inserts,
					$createTemporary ? ['id_msg'] : ['id_msg', 'id_search'],
				);
			}

			if (empty($indexedResults) && empty($numSubjectResults) && !empty(Config::$modSettings['search_force_index'])) {
				$this->errors['query_not_specific_enough'] = true;
				$_REQUEST['params'] = $this->compressParams();
				Search::call();

				return;
			}

			if (!empty($indexedResults)) {
				$main_query['inner_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages AS lsm ON (lsm.id_msg = m.id_msg)';

				if (!$createTemporary) {
					$main_query['where'][] = 'lsm.id_search = {int:id_search}';
					$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
				}
			}
		}
		// Not using an index? All conditions have to be carried over.
		else {
			$orWhere = [];
			$count = 0;

			foreach ($this->searchWords as $orIndex => $words) {
				$where = [];

				foreach ($words['all_words'] as $regularWord) {
					if (in_array($regularWord, $this->excludedWords)) {
						$where[] = 'm.subject NOT ' . $this->query_match_type . ' {string:all_word_body_' . $count . '}';
						$where[] = 'm.body NOT ' . $this->query_match_type . ' {string:all_word_body_' . $count . '}';
					} else {
						$where[] = 'm.body ' . $this->query_match_type . ' {string:all_word_body_' . $count . '}';
					}

					if ($this->query_match_type === 'RLIKE') {
						$main_query['parameters']['all_word_body_' . $count++] = $this->wordBoundaryWrapper($this->escapeSqlRegex($regularWord));
					} else {
						$main_query['parameters']['all_word_body_' . $count++] = '%' . Db::$db->escape_wildcard_string($regularWord) . '%';
					}
				}

				if (!empty($where)) {
					$orWhere[] = count($where) > 1 ? '(' . implode(' AND ', $where) . ')' : $where[0];
				}
			}

			if (!empty($orWhere)) {
				$main_query['where'][] = count($orWhere) > 1 ? '(' . implode(' OR ', $orWhere) . ')' : $orWhere[0];
			}

			if (!empty($this->userQuery)) {
				$main_query['where'][] = '{raw:user_query}';
				$main_query['parameters']['user_query'] = $this->userQuery;
			}

			if (!empty($this->params['topic'])) {
				$main_query['where'][] = 'm.id_topic = {int:topic}';
				$main_query['parameters']['topic'] = $this->params['topic'];
			}

			if (!empty($this->minMsgID)) {
				$main_query['where'][] = 'm.id_msg >= {int:min_msg_id}';
				$main_query['parameters']['min_msg_id'] = $this->minMsgID;
			}

			if (!empty($this->maxMsgID)) {
				$main_query['where'][] = 'm.id_msg <= {int:max_msg_id}';
				$main_query['parameters']['max_msg_id'] = $this->maxMsgID;
			}

			if (!empty($this->boardQuery)) {
				$main_query['where'][] = 'm.id_board {raw:board_query}';
				$main_query['parameters']['board_query'] = $this->boardQuery;
			}
		}

		IntegrationHook::call('integrate_main_search_query', [&$main_query]);

		// Did we either get some indexed results, or otherwise did not do an indexed query?
		if (!empty($indexedResults) || !$this->supportsMethod('indexedWordQuery', $this->getQueryParams())) {
			$relevance = '1000 * (';
			$new_weight_total = 0;

			foreach ($main_query['weights'] as $type => $value) {
				$relevance .= $this->weight[$type];

				if (!empty($value['search'])) {
					$relevance .= ' * ' . $value['search'];
				}

				$relevance .= ' + ';
				$new_weight_total += $this->weight[$type];
			}

			$main_query['select']['relevance'] = substr($relevance, 0, -3) . ') / ' . $new_weight_total . ' AS relevance';

			$ignore_clause = Db::$db->support_ignore ? ('
				INSERT IGNORE INTO ' . '{db_prefix}log_search_results
					(' . implode(', ', array_keys($main_query['select'])) . ')') : '';

			$ignoreRequest = Db::$db->search_query(
				'insert_log_search_results_no_index',
				$ignore_clause . '
				SELECT
					' . implode(',
					', $main_query['select']) . '
				FROM ' . $main_query['from'] . (empty($main_query['inner_join']) ? '' : '
					INNER JOIN ' . implode('
					INNER JOIN ', $main_query['inner_join'])) . (empty($main_query['left_join']) ? '' : '
					LEFT JOIN ' . implode('
					LEFT JOIN ', $main_query['left_join'])) . (!empty($main_query['where']) ? '
				WHERE ' : '') . implode('
					AND ', $main_query['where']) . (empty($main_query['group_by']) ? '' : '
				GROUP BY ' . implode(', ', $main_query['group_by'])) . (empty(Config::$modSettings['search_max_results']) ? '' : '
				LIMIT ' . Config::$modSettings['search_max_results']),
				$main_query['parameters'],
			);

			// We love to handle non-good databases that don't support our ignore!
			if (!Db::$db->support_ignore) {
				$inserts = [];

				while ($row = Db::$db->fetch_row($ignoreRequest)) {
					// No duplicates!
					if (isset($inserts[$row[2]])) {
						continue;
					}

					foreach ($row as $key => $value) {
						$inserts[$row[2]][] = (int) $row[$key];
					}
				}
				Db::$db->free_result($ignoreRequest);

				// Now put them in!
				if (!empty($inserts)) {
					$query_columns = [];

					foreach ($main_query['select'] as $k => $v) {
						$query_columns[$k] = 'int';
					}

					Db::$db->insert(
						'',
						'{db_prefix}log_search_results',
						$query_columns,
						$inserts,
						['id_search', 'id_topic'],
					);
				}

				$_SESSION['search_cache']['num_results'] += count($inserts);
			} else {
				$_SESSION['search_cache']['num_results'] = Db::$db->affected_rows();
			}
		}

		// Insert subject-only matches.
		if ($_SESSION['search_cache']['num_results'] < Config::$modSettings['search_max_results'] && $numSubjectResults !== 0) {
			$relevance = '1000 * (';

			foreach (self::$weight_factors as $type => $value) {
				if (isset($value['results'])) {
					$relevance .= $this->weight[$type];

					if (!empty($value['results'])) {
						$relevance .= ' * ' . $value['results'];
					}
					$relevance .= ' + ';
				}
			}

			$relevance = substr($relevance, 0, -3) . ') / ' . $this->weight_total . ' AS relevance';

			$usedIDs = array_flip(empty($inserts) ? [] : array_keys($inserts));

			$ignore_clause = Db::$db->support_ignore ? ('
				INSERT IGNORE INTO {db_prefix}log_search_results
					(id_search, id_topic, relevance, id_msg, num_matches)') : '';

			$ignoreRequest = Db::$db->search_query(
				'insert_log_search_results_sub_only',
				$ignore_clause . '
				SELECT
					{int:id_search},
					t.id_topic,
					' . $relevance . ',
					t.id_first_msg,
					1
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (lst.id_topic = t.id_topic)'
				. ($createTemporary ? '' : ' WHERE lst.id_search = {int:id_search}')
				. (empty(Config::$modSettings['search_max_results']) ? '' : '
				LIMIT ' . (Config::$modSettings['search_max_results'] - $_SESSION['search_cache']['num_results'])),
				[
					'id_search' => $_SESSION['search_cache']['id_search'],
					'min_msg' => $this->ageMinMsg,
					'recent_message' => $this->ageRecentMsg,
					'huge_topic_posts' => $this->humungousTopicPosts,
				],
			);

			// Once again need to do the inserts if the database don't support ignore!
			if (!Db::$db->support_ignore) {
				$inserts = [];

				while ($row = Db::$db->fetch_row($ignoreRequest)) {
					// No duplicates!
					if (isset($usedIDs[$row[1]])) {
						continue;
					}

					$usedIDs[$row[1]] = true;
					$inserts[] = $row;
				}
				Db::$db->free_result($ignoreRequest);

				// Now put them in!
				if (!empty($inserts)) {
					Db::$db->insert(
						'',
						'{db_prefix}log_search_results',
						['id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'float', 'id_msg' => 'int', 'num_matches' => 'int'],
						$inserts,
						['id_search', 'id_topic'],
					);
				}

				$_SESSION['search_cache']['num_results'] += count($inserts);
			} else {
				$_SESSION['search_cache']['num_results'] += Db::$db->affected_rows();
			}
		} elseif ($_SESSION['search_cache']['num_results'] == -1) {
			$_SESSION['search_cache']['num_results'] = 0;
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\SearchApi::exportStatic')) {
	SearchApi::exportStatic();
}

?>