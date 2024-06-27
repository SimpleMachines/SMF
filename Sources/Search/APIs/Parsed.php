<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Search\APIs;

use SMF\Autolinker;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Msg;
use SMF\Sapi;
use SMF\Search\SearchApi;
use SMF\Search\SearchApiInterface;
use SMF\SecurityToken;
use SMF\Unicode\Utf8String;
use SMF\Url;
use SMF\User;
use SMF\Utils;

use function SMF\Unicode\utf8_regex_properties;

/**
 * Search index API.
 */
class Parsed extends SearchApi implements SearchApiInterface
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var int
	 *
	 * The minimum word length. Words shorter than this will not be indexed.
	 *
	 * Note that this restriction only applies to words made out of letters,
	 * diacritical marks, and numbers. It does not apply not to emojis, etc.
	 */
	public const MIN_WORD_LENGTH = 2;

	/**
	 * @var int
	 *
	 * How many messages to process at once in self::build().
	 */
	public const BATCH_SIZE = 250;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Search terms that had a * wildcard in them.
	 */
	public array $wildcard_words = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Sub-actions to add for SMF\Actions\Admin\Search::$subactions.
	 */
	public static array $admin_subactions = [
		'build' => [
			'sa' => 'createparsed',
			'func' => __CLASS__ . '::build',
		],
		'remove' => [
			'sa' => 'removeparsed',
			'func' => __CLASS__ . '::remove',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * The size of the index.
	 */
	private int $size;

	/**
	 * @var BBCodeParser
	 *
	 * Special BBCodeParser for $this->prepareString()
	 */
	private BBCodeParser $bbcparser;

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function getStatus(): ?string
	{
		switch (Config::$modSettings['search_parsed_status'] ?? null) {
			case 'exists':
			case 'partial':
				return Config::$modSettings['search_parsed_status'];

			default:
				return 'none';
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod(string $methodName, array $query_params = []): bool
	{
		$return = false;

		switch ($methodName) {
			case 'isValid':
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
			case 'postCreated':
			case 'postModified':
			case 'postRemoved':
				$return = true;
				break;

			default:
				$return = false;
		}

		if (!$return) {
			$return = parent::supportsMethod($methodName, $query_params);
		}

		return $return;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isValid(): bool
	{
		return (Config::$modSettings['search_index'] ?? null) === 'parsed' && (Config::$modSettings['search_parsed_status'] ?? null) === 'exists';
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchSort(string $a, string $b): int
	{
		return (Utils::entityStrlen($a) - (in_array($a, $this->excludedWords) ? 1000 : 0)) <=> (Utils::entityStrlen($b) - (in_array($b, $this->excludedWords) ? 1000 : 0));
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSize(): int
	{
		if ($this->getStatus() === 'partial') {
			return 0;
		}

		if (isset($this->size)) {
			return $this->size;
		}

		$this->size = 0;

		if (Db::$db->title === POSTGRE_TITLE) {

			// Postgres will throw an error if the tables don't exist, so check first
			$search_tables = Db::$db->list_tables(Db::$db->name, Db::$db->prefix . 'log_search%');

			if (array_intersect([Db::$db->prefix . 'log_search_dictionary', Db::$db->prefix . 'log_search_parsed'], $search_tables) == []) {
				return $this->size;
			}

			$request = Db::$db->query(
				'',
				'SELECT (
					pg_total_relation_size({string:dictionary})
					+ pg_total_relation_size({string:parsed})
				) AS total_size',
				[
					'dictionary' => Db::$db->prefix . 'log_search_dictionary',
					'parsed' => Db::$db->prefix . 'log_search_parsed',
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->size = (int) $row['total_size'];
			}

			Db::$db->free_result($request);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT (data_length + index_length) AS size
				FROM information_schema.TABLES
				WHERE table_schema = {string:db_name}
				    AND table_name IN ({array_string:tables})',
				[
					'db_name' => Db::$db->name,
					'tables' => [
						Db::$db->prefix . 'log_search_dictionary',
						Db::$db->prefix . 'log_search_parsed',
					],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->size += (int) $row['size'];
			}
		}

		Db::$db->free_result($request);

		return $this->size;
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes(string $word, array &$wordsSearch, array &$wordsExclude, bool $isExcluded): void
	{
		// Find the keys where this word occurs in $wordsSearch['all_words'].
		$keys = array_keys($wordsSearch['all_words'], $word);

		// Casefold, normalize, etc.
		$word = $this->prepareString($word);

		// Is this a blacklisted word?
		if (in_array($word, $this->blacklisted_words)) {
			foreach ($keys as $key) {
				unset($wordsSearch['all_words'][$key]);
			}

			return;
		}

		// Update $wordsSearch['all_words'].
		foreach ($keys as $key) {
			$wordsSearch['all_words'][$key] = $word;
		}

		$wordsSearch['indexed_words'] = $wordsSearch['all_words'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function indexedWordQuery(array $words, array $search_data): mixed
	{
		// This will hold the matches we find.
		$found = [];

		$all_words = [];
		$phrases = [];
		$single_words = [];

		foreach ($words['all_words'] as $word) {
			$temp = $this->getWords($word);

			$temp = array_combine(
				array_map([$this, !empty($this->params['ignore_accents']) ? 'removeAccents' : 'escapeAccents'], array_keys($temp)),
				$temp,
			);

			$all_words = array_merge($all_words, array_keys($temp));

			if (count($temp) > 1) {
				$phrases[] = array_keys($temp);
			} else {
				$single_words[] = key($temp);
			}
		}

		// Find all the individual words.
		$words_like = [];
		$params = [
			'col' => !empty($this->params['ignore_accents']) ? 'sd.stripped_word' : 'sd.word',
			'words' => $all_words,
		];

		if (empty(Config::$modSettings['search_match_words'])) {
			foreach ($all_words as $key => $word) {
				$words_like[] = '{string:word_' . $key . '}';
				$params['word_' . $key] = '%' . $word . '%';
			}
		} else {
			foreach (
				array_values(array_intersect_key($this->wildcard_words, array_flip($words['all_words'])))
				as $key => $wildcard_word
			) {
				$wildcard_word = !empty($this->params['ignore_accents']) ? $this->removeAccents($wildcard_word) : $this->escapeAccents($wildcard_word);

				$words_like[] = '{string:word_' . $key . '}';
				$params['word_' . $key] = $wildcard_word;
			}
		}

		$request = Db::$db->query(
			'',
			'SELECT sd.word, si.id_msg, si.wordnums
			FROM {db_prefix}log_search_dictionary AS sd
				INNER JOIN {db_prefix}log_search_parsed AS si ON (sd.id_word = si.id_word)
			WHERE {raw:col} IN ({array_string:words})' . (empty($words_like) ? '' : '
				OR {raw:col} LIKE ' . implode('
				OR {raw:col} LIKE ', $words_like)),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$found[$row['word']][(int) $row['id_msg']] = array_merge($found[$row['word']][(int) $row['id_msg']] ?? [], array_map('intval', explode(',', $row['wordnums'])));
		}

		Db::$db->free_result($request);

		// If we have phrases, filter out matches that don't appear as part of the phrase.
		foreach ($phrases as $phrase) {
			$possible_matches = [];

			foreach ($phrase as $wordnum => $word) {
				// For the first word in the phrase, set possible matches to all occurrences of that word.
				if ($wordnum === 0) {
					$possible_matches = $found[$word];
				} elseif (!empty($possible_matches)) {
					// Walk through each subsequent word of the phrase and filter out possible matches that are not followed by that word.
					if (!isset($found[$word])) {
						$possible_matches = [];
					} else {
						foreach ($found[$word] as $msg => $wordnums) {
							if (!isset($possible_matches[$msg])) {
								break;
							}

							$possible_matches[$msg] = array_intersect(
								$possible_matches[$msg],
								array_map(fn ($wordnum) => $wordnum - 1, $wordnums),
							);

							foreach ($possible_matches[$msg] as $key => $wordnum) {
								++$possible_matches[$msg][$key];
							}
						}
					}
				}

				// If this word is only wanted when within the phrase, remove its separate entry.
				if (!in_array($word, $single_words)) {
					unset($found[$word]);
				}
			}

			if (!empty($possible_matches)) {
				$found[implode(' ', $phrase)] = $possible_matches;
			}
		}

		// Remove any excluded terms.
		foreach ([
			'excluded_words',
			'excluded_phrases',
			'excluded_index_words',
			'excluded_subject_words',
		] as $key) {
			if (!empty($search_data['params'][$key])) {
				foreach ($search_data['params'][$key] as $excluded) {
					$excluded = implode(' ', array_keys($this->getWords($this->prepareString($excluded), false)));

					if (!isset($found[$excluded])) {
						continue;
					}

					foreach ($found as $word => $msg_data) {
						if ($word !== $excluded) {
							$found[$word] = array_diff_key($msg_data, $found[$excluded]);
						}
					}

					unset($found[$excluded]);
				}
			}
		}

		// Help SearchResult::highlight() to highlight the matches we actually
		// found, not just the strings that were originally requested.
		foreach (array_keys($found) as $word) {
			$word = Utils::fixUtf8mb4(Utils::normalize(Utils::entityDecode($word, true), 'c'));

			if (!in_array($word, $this->searchArray)) {
				$this->searchArray[] = $word;
				$this->marked[$word] = '<mark class="highlight">' . $word . '</mark>';

				if (!is_array($this->params['alt_forms'] ?? '')) {
					$this->params['alt_forms'] = [];
				}

				$this->params['alt_forms'][] = $word;
			}
		}

		if (!empty($this->params['alt_forms'])) {
			unset($this->compressed_params);
			$this->params['alt_forms'] = json_encode(array_unique($this->params['alt_forms']));
			$_SESSION['search_cache']['params'] = $this->compressParams();
		}

		// Initial definitions for parts of the final query.
		$query_select = [
			'id_msg' => 'm.id_msg',
		];
		$query_where = [
			'm.id_msg IN ({array_int:msgs})',
		];

		$query_params = $search_data['params'];

		if ($query_params['id_search']) {
			$query_select['id_search'] = '{int:id_search}';
		}

		if ($query_params['user_query']) {
			$query_where[] = '{raw:user_query}';
		}

		if ($query_params['board_query']) {
			$query_where[] = 'm.id_board {raw:board_query}';
		}

		if ($query_params['topic']) {
			$query_where[] = 'm.id_topic = {int:topic}';
		}

		if ($query_params['min_msg_id']) {
			$query_where[] = 'm.id_msg >= {int:min_msg_id}';
		}

		if ($query_params['max_msg_id']) {
			$query_where[] = 'm.id_msg <= {int:max_msg_id}';
		}

		// Now get the message IDs that we want.
		$query_params['msgs'] = [];

		foreach ($found as $word => $msg_data) {
			if ($query_params['msgs'] === []) {
				$query_params['msgs'] = array_keys($msg_data);
			} else {
				// We only want the messages that match all the terms in this $orPart group.
				$query_params['msgs'] = array_intersect($query_params['msgs'], array_keys($msg_data));
			}
		}

		$query_params['msgs'] = array_unique($query_params['msgs']);

		if (empty($query_params['msgs'])) {
			$query_params['msgs'] = [0];
		}

		// Returning this query is the purpose of the method.
		return Db::$db->search_query(
			'insert_into_log_messages_parsed',
			'INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')' . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
		$word_data = [];

		foreach ($this->getWords($this->prepareString($msgOptions['body'])) as $word => $wordnums) {
			$word_data[$word][(int) $msgOptions['id']] = $wordnums;
		}

		$this->save($word_data);

		self::updateStopwordsSetting();
	}

	/**
	 * {@inheritDoc}
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
		$word_data = [];

		if (isset($msgOptions['old_body'])) {
			foreach ($this->getWords($this->prepareString($msgOptions['old_body'])) as $word => $wordnums) {
				$word_data[$word][(int) $msgOptions['id']] = null;
			}
		}

		if (isset($msgOptions['body'])) {
			foreach ($this->getWords($this->prepareString($msgOptions['body'])) as $word => $wordnums) {
				$word_data[$word][(int) $msgOptions['id']] = $wordnums;
			}
		}

		$this->save($word_data);

		self::updateStopwordsSetting();
	}

	/**
	 * {@inheritDoc}
	 */
	public function postRemoved(int $id_msg): void
	{
		// Do all the normal stuff.
		parent::postRemoved($id_msg);

		// Do stuff specific to this API.
		$msg = current(Msg::load($id_msg));

		if ($msg === false) {
			return;
		}

		foreach ($this->getWords($this->prepareString($msg->body)) as $word => $wordnums) {
			$word_data[$word][$msg->id] = null;
		}

		$this->save($word_data);

		self::updateStopwordsSetting();
	}

	/**
	 * {@inheritDoc}
	 */
	public function formContext(): void
	{
		Utils::$context['search_params']['ignore_accents'] = !empty(Utils::$context['search_params']['ignore_accents']) || (!isset(Utils::$context['search_params']['ignore_accents']) && !empty(Lang::$txt['search_ignore_accents_by_default']));


		Utils::$context['search_options']['ignore_accents'] = [
			'html' => '<input type="checkbox" name="ignore_accents" id="ignore_accents" value="1"' . (!empty(Utils::$context['search_params']['ignore_accents']) ? ' checked' : '') . '>',
			'label' => 'search_ignore_accents',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function resultsContext(): void
	{
		Utils::$context['hidden_inputs']['ignore_accents'] = '<input type="hidden" name="ignore_accents" value="' . (!empty(Utils::$context['search_params']['ignore_accents']) ? 1 : 0) . '">';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAdminSubactions(?string $type = null): array
	{
		$subactions = [
			'build' => [
				'func' => __CLASS__ . '::build',
				'sa' => 'createparsed',
				'extra_params' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
			'resume' => [
				'func' => __CLASS__ . '::build',
				'sa' => 'createparsed',
				'extra_params' => [
					'resume',
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
			'remove' => [
				'func' => __CLASS__ . '::remove',
				'sa' => 'removeparsed',
				'extra_params' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
		];

		return is_null($type) ? $subactions : (isset($subactions[$type]) ? [$subactions[$type]] : []);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Builds the parsed index from the content of the messages table, and
	 * saves it in the log_search_dictionary and log_search_parsed tables.
	 *
	 * Operates in batches to avoid running out of time.
	 *
	 * @param int $start_id The ID of the message we should start with.
	 */
	public static function build(int $start_id = 1): void
	{
		if (SMF !== 'BACKGROUND') {
			User::$me->checkSession('get');
			SecurityToken::validate('admin-msm', 'get');

			// "Resume" just means reload so that the background task continues.
			if (isset($_REQUEST['resume'], Config::$modSettings['search_parsed_status'])) {
				Utils::redirectexit('action=admin;area=managesearch;sa=method');
			}
		}

		// Already exists?
		if ((Config::$modSettings['search_parsed_status'] ?? null) === 'exists') {
			if (SMF !== 'BACKGROUND') {
				Utils::redirectexit('action=admin;area=managesearch;sa=method');
			}

			return;
		}

		if ($start_id === 1) {
			self::createTables();
		}

		Config::updateModSettings([
			'search_parsed_status' => 'partial',
			'search_index' => '',
		]);

		// The heavy work is only done via the background task.
		if (SMF === 'BACKGROUND') {
			$instance = new self();

			$memory_limit = Sapi::memoryReturnBytes(ini_get('memory_limit')) * 0.8;

			$word_data = [];

			while (microtime(true) < TIME_START + 10) {
				foreach (Msg::get(range($start_id, $start_id + self::BATCH_SIZE - 1)) as $msg) {
					$last_id = $msg->id;

					$words = $instance->getWords($instance->prepareString($msg->body), false);

					foreach ($words as $word => $wordnums) {
						$word_data[$word][$msg->id] = $wordnums;
					}

					if (memory_get_usage() >= $memory_limit) {
						$instance->save($word_data);
						$word_data = [];
					}
				}

				$start_id += self::BATCH_SIZE;

				// Are we done yet?
				if (($last_id ?? 0) >= Config::$modSettings['maxMsgID']) {
					break;
				}
			}

			// Save the last batch.
			$instance->save($word_data);
		}

		// If necessary, spawn a new background task to continue the job...
		if (($last_id ?? 0) < Config::$modSettings['maxMsgID']) {
			Db::$db->insert(
				'',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				[
					'SMF\\Tasks\\GenericTask',
					Utils::jsonEncode([
						'callable' => __METHOD__,
						'start_id' => ($last_id ?? 0) + 1,
					]),
					0,
				],
				['id_task'],
			);
		} else {
			Config::updateModSettings([
				'search_parsed_status' => 'exists',
				'search_index' => 'parsed',
			]);

			Db::$db->optimize_table('{db_prefix}log_search_dictionary');
			Db::$db->optimize_table('{db_prefix}log_search_parsed');

			self::updateStopwordsSetting();
		}

		if (SMF === 1) {
			Utils::redirectexit('action=admin;area=managesearch;sa=method');
		}
	}

	/**
	 * Deletes the log_search_dictionary and log_search_parsed tables
	 * and resets to standard search method.
	 */
	public static function remove(): void
	{
		User::$me->isAllowedTo('admin_forum');
		User::$me->checkSession('get');
		SecurityToken::validate('admin-msm', 'get');

		Db::$db->drop_table(Db::$db->prefix . 'log_search_dictionary');
		Db::$db->drop_table(Db::$db->prefix . 'log_search_parsed');

		Config::updateModSettings([
			'search_parsed_status' => null,
			'search_index' => (Config::$modSettings['search_index'] ?? 'parsed') === 'parsed' ? '' : Config::$modSettings['search_index'],
		]);

		Utils::redirectexit('action=admin;area=managesearch;sa=method');
	}

	/**
	 * Updates Config::$modSettings['search_stopwords_parsed'].
	 */
	public static function updateStopwordsSetting(): void
	{
		if (
			Config::$modSettings['search_index'] !== 'parsed'
			|| (Config::$modSettings['search_stopwords_parsed_updated'] ?? 0) > time() - 86400
		) {
			return;
		}

		$request = Db::$db->query(
			'',
			'SELECT d.word
			FROM {db_prefix}log_search_parsed AS p
				INNER JOIN {db_prefix}log_search_dictionary AS d ON (p.id_word = d.id_word)
			GROUP BY d.word
			HAVING COUNT(*) > {int:minimum_messages}
			ORDER BY d.word',
			[
				'minimum_messages' => ceil(Config::$modSettings['totalMessages'] * 0.6),
			],
		);

		$stopwords = Db::$db->fetch_all($request);

		Db::$db->free_result($request);

		$stopwords = array_map(fn ($w) => Utils::normalize(Utils::entityDecode($w, true)), $stopwords);

		Config::updateModSettings([
			'search_stopwords_parsed' => implode(',', $stopwords),
			'search_stopwords_parsed_updated' => time(),
		]);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Like SeachApi::setBlacklistedWords(), except that this doesn't blacklist
	 * BBCode tags.
	 *
	 * Why? Because this API builds its index from the parsed version of the
	 * messages rather than the unparsed version, which means that raw BBCodes
	 * are never part of the indexed strings and therefore don't need to be
	 * filtered out of the search terms.
	 */
	protected function setBlacklistedWords(): void
	{
		// Blacklist any stopwords for the current language.
		if (isset(Lang::$txt['search_stopwords'])) {
			$this->blacklisted_words = array_unique(array_merge(
				$this->blacklisted_words,
				array_map('trim', explode(',', Lang::$txt['search_stopwords'])),
			));
		}

		// Blacklist any stopwords that we found automatically.
		if (isset(Config::$modSettings['search_stopwords_parsed'])) {
			$this->blacklisted_words = array_unique(array_merge(
				$this->blacklisted_words,
				array_map('trim', explode(',', Config::$modSettings['search_stopwords_parsed'])),
			));
		}

		// Blacklist any stopwords that the admin set manually.
		if (isset(Config::$modSettings['search_stopwords_custom'])) {
			$this->blacklisted_words = array_unique(array_merge(
				$this->blacklisted_words,
				array_map('trim', explode(',', Config::$modSettings['search_stopwords_custom'])),
			));
		}

		IntegrationHook::call('integrate_search_blacklisted_words', [&$this->blacklisted_words]);

		$this->blacklisted_words = array_map(fn ($w) => $this->prepareString($w), $this->blacklisted_words);
	}

	/**
	 * Figures out the values for $this->params and related properties.
	 */
	protected function setParams(): void
	{
		// Do the normal stuff.
		parent::setParams();

		// Some extra stuff that is specific to this API.
		self::$default_params['alt_forms'] = '';
		self::$default_params['ignore_accents'] = !empty(Lang::$txt['search_ignore_accents_by_default']);

		$this->params['ignore_accents'] = !empty($this->params['ignore_accents']) || !empty($_REQUEST['ignore_accents']);
	}

	/**
	 * Populates $this->searchArray, $this->excludedWords, etc.
	 */
	protected function setSearchTerms(): void
	{
		// Parsed index allows users to override the "match whole words only"
		// setting in order to perform wildcard searches.
		if (
			!empty(Config::$modSettings['search_match_words'])
			&& (
				str_contains($this->params['search'], '*')
				|| str_contains($this->params['search'], '?')
			)
		) {
			$placeholders = [];

			// The hidden 'search_simple_fulltext' setting basically means don't do phrase searches.
			if (empty(Config::$modSettings['search_simple_fulltext'])) {
				// Protect the contents of quoted phrases.
				preg_match_all('/(?<=^|\s)-?"[^"]+"(?=$|\s)/', $this->params['search'], $matches, PREG_SET_ORDER);

				foreach ($matches as $match) {
					if (isset($match[0])) {
						$placeholders[$match[0]] = md5($match[0]);
					}
				}
			}

			// Protect wildcards from removal in $this->getWords().
			$placeholders['*'] = $md5_asterisk = md5('*');
			$placeholders['?'] = $md5_question = md5('?');

			// Make our substitutions.
			$temp = strtr($this->params['search'], $placeholders);

			// Find the individual words that have wildcards attached to them
			// and remember them in $this->wildcard_words.
			foreach (array_keys($this->getWords($this->prepareString($temp))) as $word) {
				if (
					(
						str_contains($word, $md5_asterisk)
						|| str_contains($word, $md5_question)
					)
					&& $word !== $md5_asterisk
					&& $word !== $md5_question
				) {
					$this->wildcard_words[strtr($word, [$md5_asterisk => '', $md5_question => ''])] = strtr($word, [$md5_asterisk => '%', $md5_question => '_']);
				}
			}

			// Remove the wildcards attached to individual words.
			$placeholders = array_flip($placeholders);
			$placeholders[$md5_asterisk] = '';
			$placeholders[$md5_question] = '';
			$this->params['search'] = strtr($temp, $placeholders);
		}

		// Do the normal stuff.
		parent::setSearchTerms();

		// Restore the wildcards. We do this so that compressParams()
		// will still have access to the original search query.
		if (isset($placeholders)) {
			$placeholders[$md5_asterisk] = '*';
			$placeholders[$md5_question] = '?';
			$this->params['search'] = strtr($temp, $placeholders);
		}

		// If this is a cached result and the original search found some
		// alternate forms of the searched words, add them to the array so that
		// the highlighting will recognize them.
		if (!empty($this->params['alt_forms'])) {
			$this->searchArray = array_unique(array_merge(
				$this->searchArray,
				Utils::jsonDecode($this->params['alt_forms'], true),
			));
		}
	}

	/**
	 * Saves word data to log_search_dictionary and log_search_parsed tables.
	 *
	 * @param array $word_data Data about the words.
	 */
	protected function save(array $word_data): void
	{
		$inserts = [];

		foreach ($word_data as $word => &$msg_data) {
			// Ensure this word is in our dictionary, and get its ID.
			$id_word = Db::$db->insert(
				'ignore',
				'{db_prefix}log_search_dictionary',
				[
					'word' => 'string-255',
					'stripped_word' => 'string-255',
				],
				[
					[
						$this->escapeAccents((string) $word),
						$this->removeAccents((string) $word),
					],
				],
				[
					'id_word',
				],
				1,
			);

			foreach ($msg_data as $msg => $wordnums) {
				if (!is_array($wordnums)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}log_search_parsed
						WHERE id_word = {int:word} AND id_msg = {int:msg}',
						[
							'word' => $id_word,
							'msg' => $msg,
						],
					);

					continue;
				}

				sort($wordnums);

				// If this word + message combo already exists, update it.
				Db::$db->query(
					'',
					'UPDATE {db_prefix}log_search_parsed
					SET wordnums = {string:wordnums}
					WHERE id_word = {int:word} AND id_msg = {int:msg}',
					[
						'word' => $id_word,
						'msg' => $msg,
						'wordnums' => implode(',', $wordnums),
					],
				);

				// Otherwise, create an entry for this word + message combo.
				if (Db::$db->affected_rows() === 0) {
					$inserts[] = [$id_word, $msg, implode(',', $wordnums)];
				}
			}
		}

		// New entries to create.
		if (!empty($inserts)) {
			Db::$db->insert(
				'',
				'{db_prefix}log_search_parsed',
				[
					'id_word' => 'int',
					'id_msg' => 'int',
					'wordnums' => 'string',
				],
				$inserts,
				['id_word'],
			);
		}
	}

	/**
	 * Gets rid of all the BBCode and HTML in a string, folds the case of all
	 * characters, and applies compatibility composition Unicode normalization.
	 *
	 * @param string $string A string.
	 * @return string Version of the string prepared for indexing.
	 */
	protected function prepareString(string $string): string
	{
		if (!isset($this->bbcparser)) {
			// BBCodeParser complains if User::$me is not set.
			if (!isset(User::$me)) {
				User::setMe(0);
			}

			$this->bbcparser = new BBCodeParser();

			// Leave out anything that would be skipped for printing.
			$this->bbcparser->for_print = true;
		}

		// Disable image proxy because we want the original URLs.
		$image_proxy_enabled = Config::$image_proxy_enabled ?? false;
		Config::$image_proxy_enabled = false;

		// Disable code and quote because we don't want to index
		// the boilerplate strings that are inserted for them.
		// Disable youtube because we want the raw URL.
		$disabledBBC = Config::$modSettings['disabledBBC'] ?? '';
		Config::$modSettings['disabledBBC'] = 'code,quote,youtube';

		// Being a bit paranoid here, but...
		$images = $_GET['images'] ?? null;
		unset($_GET['images']);

		// Parse the BBCode.
		$string = $this->bbcparser->parse($string, false);

		// Put stuff back the way we found it.
		Config::$image_proxy_enabled = $image_proxy_enabled;
		Config::$modSettings['disabledBBC'] = $disabledBBC;

		if (isset($images)) {
			$_GET['images'] = $images;
		}

		// Remove HTML.
		$string = strip_tags(is_string($string) ? preg_replace('/<[^>]+>/', '$0 ', $string) : '');

		// Decode 4-byte Unicode characters.
		$string = mb_decode_numericentity($string, [0x010000, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');

		// Separate emoji from regular words.
		require_once Config::$sourcedir . '/Unicode/RegularExpressions.php';
		$prop_classes = utf8_regex_properties();

		$string = preg_replace_callback(
			'/' .
				// Flag emojis
				'[' . $prop_classes['Regional_Indicator'] . ']{2}' .
				// Or
				'|' .
				// Emoji characters
				'[' . $prop_classes['Emoji'] . ']' .
				// Possibly followed by modifiers of various sorts
				'(' .
					'[' . $prop_classes['Emoji_Modifier'] . ']' .
					'|' .
					'\x{FE0F}\x{20E3}?' .
					'|' .
					'[\x{E0020}-\x{E007E}]+\x{E007F}' .
				')?' .
				// Possibly concatenated with Zero Width Joiner and more emojis
				// (e.g. the "family" emoji sequences)
				'(' .
					'\x{200D}[' . $prop_classes['Emoji'] . ']' .
					'(' .
						'[' . $prop_classes['Emoji_Modifier'] . ']' .
						'|' .
						'\x{FE0F}\x{20E3}?' .
						'|' .
						'[\x{E0020}-\x{E007E}]+\x{E007F}' .
					')?' .
				')*' .
			'/u',
			function ($matches) {
				// Skip lone ASCII characters that are not actually part of an
				// emoji sequence. This can happen because the digits 0-9 and
				// the '*' and '#' characters are the base characters for the
				// "Emoji_Keycap_Sequence" emojis.
				if (strlen($matches[0]) === 1) {
					return $matches[0];
				}

				return ' ' . $matches[0] . ' ';
			},
			$string,
		);

		// Strip out parts of URLs that are just noise in a search index.
		foreach (Autolinker::load()->detectUrls($string) as $url) {
			$substitute = (string) Url::create($url, true)->toUtf8();

			$url_parts = Url::create($url, true)->toUtf8()->parse();

			if (!isset($url_parts['scheme']) && !isset($url_parts['host'])) {
				$url_parts = Url::create('//' . $url, true)->toUtf8()->parse();
			}

			// Get rid of 'http', 'https', etc.
			if (isset($url_parts['scheme'])) {
				$substitute = ltrim(substr($substitute, strlen($url_parts['scheme'])), ':/');
			}

			if (isset($url_parts['host'])) {
				$trimmed_host = $url_parts['host'];

				// Get rid of basic TLDs.
				if (str_contains($trimmed_host, '.')) {
					$tld = substr($trimmed_host, strrpos($trimmed_host, '.') + 1);

					if (in_array($tld, Url::$basic_tlds)) {
						$trimmed_host = substr($trimmed_host, 0, strrpos($trimmed_host, '.'));
					}
				}

				// Get rid of 'www.'
				if (str_starts_with($trimmed_host, 'www.')) {
					$trimmed_host = substr($trimmed_host, 4);
				}

				$substitute = str_replace($url_parts['host'], $trimmed_host, $substitute);
			}

			// Decode URL encoding.
			$substitute = rawurldecode($substitute);

			$string = str_replace($url, $substitute, $string);
		}

		// Strip out parts of email addresses that are just noise in a search index.
		foreach (Autolinker::load()->detectEmails($string) as $email) {
			$substitute = Url::create('mailto:' . $email, true)->toUtf8()->path;

			// Get rid of basic TLDs.
			if (str_contains($substitute, '.')) {
				$tld = substr($substitute, strrpos($substitute, '.') + 1);

				if (in_array($tld, Url::$basic_tlds)) {
					$substitute = substr($substitute, 0, strrpos($substitute, '.'));
				}
			}

			// Decode URL encoding.
			$substitute = rawurldecode($substitute);

			$string = str_replace($email, $substitute, $string);
		}

		// Normalize and casefold.
		$string = Utils::normalize($string, 'kc_casefold');

		return $string;
	}

	/**
	 * Extracts words from a string and returns them along with info about where
	 * each extracted word occurred in the list of extracted words.
	 *
	 * For example, if $string were 'foo bar foo', the returned value would be
	 * ['foo' => [0, 2], 'bar' => [1]]
	 *
	 * @param string $string A string.
	 * @param bool $filter_blacklist Whether to filter out blacklisted words.
	 *    Default: true.
	 * @return array Each word and its positions in the list of extracted words.
	 */
	protected function getWords(string $string, bool $filter_blacklist = true): array
	{
		// Get the words.
		$words = Utf8String::create($string)->extractWords(2);

		// Remove any blacklisted words.
		if ($filter_blacklist) {
			$words = array_diff($words, $this->blacklisted_words);
		}

		// Build our word data array.
		$word_data = [];

		foreach ($words as $wordnum => $word) {
			// Filter short words (but not emojis).
			if (
				mb_strlen($word) <= self::MIN_WORD_LENGTH
				&& preg_match('/^[\p{L}\p{M}\p{N}_]*$/u', $word)
			) {
				continue;
			}

			$word = Utils::truncate(Utils::fixUtf8mb4($word), 255);

			$word_data[$word][] = $wordnum;
		}

		return $word_data;
	}

	/**
	 * Escapes accents in the string as HTML entities, but only if the database
	 * collation ignores accents.
	 *
	 * @param string $string The string.
	 * @return string A version of $string with (possibly) escaped accents.
	 */
	protected function escapeAccents(string $string): string
	{
		if ($this->accentSensitive()) {
			return $string;
		}

		$string = Utils::normalize($string, 'd');

		$string = preg_replace_callback(
			'/[\p{Mn}]/u',
			fn ($matches) => mb_encode_numericentity($matches[0], [0, 0xFFFFFF, 0, 0x10FFFF], 'UTF-8'),
			$string,
		);

		return Utils::normalize($string, 'c');
	}

	/**
	 * Removes accents in the string.
	 *
	 * @param string $string The string.
	 * @return string A version of $string without accents.
	 */
	protected function removeAccents(string $string): string
	{
		$string = Utils::normalize($string, 'd');

		$string = preg_replace('/[\p{Mn}]/u', '', $string);

		return Utils::normalize($string, 'c');
	}

	/**
	 * Gets whether the current collation is accent sensitive.
	 */
	protected function accentSensitive(): bool
	{
		static $accent_sensitive;

		if (isset($accent_sensitive)) {
			return $accent_sensitive;
		}

		if (Db::$db->title === POSTGRE_TITLE) {
			// PostgreSQL is easy.
			$accent_sensitive = true;
		} else {
			// MySQL needs more work...
			$request = Db::$db->query(
				'',
				'SELECT COLLATION_NAME
				FROM information_schema.columns
				WHERE TABLE_SCHEMA = {string:db_name}
					AND TABLE_NAME = {string:table_name}
					AND COLUMN_NAME = {string:column_name}',
				[
					'db_name' => Db::$db->name,
					'table_name' => Db::$db->prefix . 'log_search_dictionary',
					'column_name' => 'word',
				],
			);

			list($collation) = Db::$db->fetch_row($request);

			Db::$db->free_result($request);

			// Start by assuming false.
			$accent_sensitive = false;

			// If "_as" or "_ai" are not explicitly stated, assume the same as "_ci" or "_cs".
			if (str_contains($collation, '_cs')) {
				$accent_sensitive = true;
			}

			if (str_contains($collation, '_ci')) {
				$accent_sensitive = false;
			}

			// If "_as" or "_ai" are explicitly stated, use that.
			if (str_contains($collation, '_as')) {
				$accent_sensitive = true;
			}

			if (str_contains($collation, '_ai')) {
				$accent_sensitive = false;
			}

			// Binary collation always respects accents.
			if (str_contains($collation, '_bin')) {
				$accent_sensitive = true;
			}
		}

		return $accent_sensitive;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Creates the log_search_parsed and log_search_dictionary tables.
	 */
	protected static function createTables(): void
	{
		Db::$db->create_table(
			'{db_prefix}log_search_dictionary',
			[
				[
					'name' => 'id_word',
					'type' => 'int',
					'unsigned' => true,
					'auto' => true,
				],
				[
					'name' => 'word',
					'type' => 'varchar',
					'size' => 255,
					'not_null' => true,
					'default' => '',
				],
				[
					'name' => 'stripped_word',
					'type' => 'varchar',
					'size' => 255,
					'not_null' => true,
					'default' => '',
				],
			],
			[
				[
					'type' => 'primary',
					'columns' => ['id_word'],
				],
				[
					'type' => 'unique',
					'columns' => ['word'],
				],
			],
		);

		Db::$db->create_table(
			'{db_prefix}log_search_parsed',
			[
				[
					'name' => 'id_word',
					'type' => 'int',
					'unsigned' => true,
					'not_null' => true,
					'default' => 0,
				],
				[
					'name' => 'id_msg',
					'type' => 'int',
					'unsigned' => true,
					'not_null' => true,
					'default' => 0,
				],
				[
					'name' => 'wordnums',
					'type' => 'text',
					'not_null' => true,
				],
			],
			[
				[
					'type' => 'primary',
					'columns' => ['id_word', 'id_msg'],
				],
			],
		);
	}
}

?>