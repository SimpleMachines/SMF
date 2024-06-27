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

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Menu;
use SMF\Sapi;
use SMF\Search\SearchApi;
use SMF\Search\SearchApiInterface;
use SMF\SecurityToken;
use SMF\User;
use SMF\Utils;

/**
 * Used for the "custom search index" option
 *
 * @deprecated 3.0
 */
class Custom extends SearchApi implements SearchApiInterface
{
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
			'sa' => 'createmsgindex',
			'func' => __CLASS__ . '::build',
		],
		'remove' => [
			'sa' => 'removecustom',
			'func' => __CLASS__ . '::remove',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Index settings
	 */
	protected $indexSettings = [];

	/**
	 * @var int|null Minimum word length (null for no minimum)
	 */
	protected $min_word_length = null;

	/**
	 * @var array Which databases support this method
	 */
	protected $supported_databases = ['mysql', 'postgresql'];

	/**
	 * @var int
	 *
	 * Size of the index, in bytes.
	 */
	private int $size;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor function
	 */
	public function __construct()
	{
		// Is this database supported?
		if (!in_array(Config::$db_type, $this->supported_databases)) {
			$this->is_supported = false;

			return;
		}

		if (empty(Config::$modSettings['search_custom_index_config'])) {
			return;
		}

		$this->indexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

		$this->min_word_length = $this->indexSettings['bytes_per_word'];

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStatus(): ?string
	{
		if (!empty(Config::$modSettings['search_custom_index_config'])) {
			return 'exists';
		}

		if (!empty(Config::$modSettings['search_custom_index_resume'])) {
			return 'partial';
		}

		return !empty(Config::$db_show_debug) ? 'none' : 'hidden';
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
			case 'topicsRemoved':
				$return = true;
				break;

			// All other methods, too bad dunno you.
			default:
				$return = false;
		}

		// Maybe parent got support
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
		return !empty(Config::$modSettings['search_custom_index_config']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSize(): int
	{
		if (isset($this->size)) {
			return $this->size;
		}

		$this->size = 0;

		if (Db::$db->title === POSTGRE_TITLE) {
			$request = Db::$db->query(
				'',
				'SELECT
					pg_total_relation_size({string:tablename}) AS total_size',
				[
					'tablename' => Db::$db->prefix . 'log_search_words',
				],
			);

			if ($request !== false && Db::$db->num_rows($request) > 0) {
				$row = Db::$db->fetch_assoc($request);
				$this->size = (int) $row['total_size'];
			}
		} else {
			if (preg_match('~^`(.+?)`\.(.+?)$~', Db::$db->prefix, $match) !== 0) {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					[
						'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
						'table_name' => str_replace('_', '\\_', $match[2]) . 'log_search_words',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'log_search_words',
					],
				);
			}

			if ($request !== false && Db::$db->num_rows($request) == 1) {
				$row = Db::$db->fetch_assoc($request);
				$this->size = (int) $row['Data_length'] + (int) $row['Index_length'];
				Db::$db->free_result($request);
			}
		}

		return $this->size;
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchSort(string $a, string $b): int
	{
		$x = strlen($a) - (in_array($a, $this->excludedWords) ? 1000 : 0);
		$y = strlen($b) - (in_array($b, $this->excludedWords) ? 1000 : 0);

		return $y < $x ? 1 : ($y > $x ? -1 : 0);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes(string $word, array &$wordsSearch, array &$wordsExclude, bool $isExcluded): void
	{
		$subwords = self::getWordNumbers($word, $this->min_word_length);

		if (empty(Config::$modSettings['search_force_index'])) {
			$wordsSearch['words'][] = $word;
		}

		// Excluded phrases don't benefit from being split into subwords.
		if (count($subwords) > 1 && $isExcluded) {
			return;
		}

		foreach ($subwords as $subword) {
			if (Utils::entityStrlen((string) $subword) >= $this->min_word_length && !in_array($subword, $this->blacklisted_words)) {
				$wordsSearch['indexed_words'][] = $subword;

				if ($isExcluded) {
					$wordsExclude[] = $subword;
				}
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function indexedWordQuery(array $words, array $search_data): mixed
	{
		$query_select = [
			'id_msg' => 'm.id_msg',
		];
		$query_inner_join = [];
		$query_left_join = [];
		$query_where = [];
		$query_params = $search_data['params'];

		if ($query_params['id_search']) {
			$query_select['id_search'] = '{int:id_search}';
		}

		$count = 0;

		foreach ($words['words'] as $regularWord) {
			if (in_array($regularWord, $query_params['excluded_words'])) {
				$query_where[] = 'm.body NOT ' . $this->query_match_type . ' {string:complex_body_' . $count . '}';
			} else {
				$query_where[] = 'm.body ' . $this->query_match_type . ' {string:complex_body_' . $count . '}';
			}

			if ($this->query_match_type === 'RLIKE') {
				$query_params['complex_body_' . $count++] = self::wordBoundaryWrapper(self::escapeSqlRegex($regularWord));
			} else {
				$query_params['complex_body_' . $count++] = '%' . Db::$db->escape_wildcard_string($regularWord) . '%';
			}
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

		$count = 0;

		if (!empty($query_params['excluded_phrases']) && empty(Config::$modSettings['search_force_index'])) {
			foreach ($query_params['excluded_phrases'] as $excludedWord) {
				$query_where[] = 'subject NOT ' . $this->query_match_type . ' {string:exclude_subject_words_' . $count . '}';

				if ($this->query_match_type === 'RLIKE') {
					$query_params['exclude_subject_words_' . $count++] = self::wordBoundaryWrapper(self::escapeSqlRegex($excludedWord));
				} else {
					$query_params['exclude_subject_words_' . $count++] = '%' . Db::$db->escape_wildcard_string($excludedWord) . '%';
				}
			}
		}
		$count = 0;

		if (!empty($query_params['excluded_subject_words']) && empty(Config::$modSettings['search_force_index'])) {
			foreach ($query_params['excluded_subject_words'] as $excludedWord) {
				$query_where[] = 'subject NOT ' . $this->query_match_type . ' {string:exclude_subject_words_' . $count . '}';

				if ($this->query_match_type === 'RLIKE') {
					$query_params['exclude_subject_words_' . $count++] = self::wordBoundaryWrapper(self::escapeSqlRegex($excludedWord));
				} else {
					$query_params['exclude_subject_words_' . $count++] = '%' . Db::$db->escape_wildcard_string($excludedWord) . '%';
				}
			}
		}

		$numTables = 0;
		$prev_join = 0;

		foreach ($words['indexed_words'] as $indexedWord) {
			$numTables++;

			if (in_array($indexedWord, $query_params['excluded_index_words'])) {
				$query_left_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_word = ' . $indexedWord . ' AND lsw' . $numTables . '.id_msg = m.id_msg)';
				$query_where[] = '(lsw' . $numTables . '.id_word IS NULL)';
			} else {
				$query_inner_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_msg = ' . ($prev_join === 0 ? 'm' : 'lsw' . $prev_join) . '.id_msg)';
				$query_where[] = 'lsw' . $numTables . '.id_word = ' . $indexedWord;
				$prev_join = $numTables;
			}
		}

		$ignoreRequest = Db::$db->search_query(
			'insert_into_log_messages_fulltext',
			(Db::$db->support_ignore ? ('
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')') : '') . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m' . (empty($query_inner_join) ? '' : '
				INNER JOIN ' . implode('
				INNER JOIN ', $query_inner_join)) . (empty($query_left_join) ? '' : '
				LEFT JOIN ' . implode('
				LEFT JOIN ', $query_left_join)) . '
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params,
		);

		return $ignoreRequest;
	}

	/**
	 * {@inheritDoc}
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
		$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

		$inserts = [];

		foreach (self::getWordNumbers($msgOptions['body'], $customIndexSettings['bytes_per_word']) as $word) {
			$inserts[] = [$word, $msgOptions['id']];
		}

		if (!empty($inserts)) {
			Db::$db->insert(
				'ignore',
				'{db_prefix}log_search_words',
				['id_word' => 'int', 'id_msg' => 'int'],
				$inserts,
				['id_word', 'id_msg'],
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
		if (isset($msgOptions['body'])) {
			$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);
			$stopwords = empty(Config::$modSettings['search_stopwords']) ? [] : explode(',', Config::$modSettings['search_stopwords']);
			$old_body = $msgOptions['old_body'] ?? '';

			// create thew new and old index
			$old_index = self::getWordNumbers($old_body, $customIndexSettings['bytes_per_word']);
			$new_index = self::getWordNumbers($msgOptions['body'], $customIndexSettings['bytes_per_word']);

			// Calculate the words to be added and removed from the index.
			$removed_words = array_diff(array_diff($old_index, $new_index), $stopwords);
			$inserted_words = array_diff(array_diff($new_index, $old_index), $stopwords);

			// Delete the removed words AND the added ones to avoid key constraints.
			if (!empty($removed_words)) {
				$removed_words = array_merge($removed_words, $inserted_words);
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_search_words
					WHERE id_msg = {int:id_msg}
						AND id_word IN ({array_int:removed_words})',
					[
						'removed_words' => $removed_words,
						'id_msg' => $msgOptions['id'],
					],
				);
			}

			// Add the new words to be indexed.
			if (!empty($inserted_words)) {
				$inserts = [];

				foreach ($inserted_words as $word) {
					$inserts[] = [$word, $msgOptions['id']];
				}
				Db::$db->insert(
					'insert',
					'{db_prefix}log_search_words',
					['id_word' => 'string', 'id_msg' => 'int'],
					$inserts,
					['id_word', 'id_msg'],
				);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function postRemoved(int $id_msg): void
	{
		$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

		$words = self::getWordNumbers($row['body'], $customIndexSettings['bytes_per_word']);

		if (!empty($words)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_search_words
				WHERE id_word IN ({array_int:word_list})
					AND id_msg = {int:id_msg}',
				[
					'word_list' => $words,
					'id_msg' => $message,
				],
			);
		}

		parent::postRemoved($id_msg);
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsRemoved(array $topics): void
	{
		$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

		$words = [];
		$messages = [];
		$request = Db::$db->query(
			'',
			'SELECT id_msg, body
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Sapi::resetTimeout();

			$words = array_merge($words, self::getWordNumbers($row['body'], $customIndexSettings['bytes_per_word']));
			$messages[] = $row['id_msg'];
		}
		Db::$db->free_result($request);
		$words = array_unique($words);

		if (!empty($words) && !empty($messages)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_search_words
				WHERE id_word IN ({array_int:word_list})
					AND id_msg IN ({array_int:message_list})',
				[
					'word_list' => $words,
					'message_list' => $messages,
				],
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAdminSubactions(): array
	{
		return [
			'build' => [
				'func' => __CLASS__ . '::build',
				'sa' => 'createmsgindex',
				'extra_params' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
			'resume' => [
				'func' => __CLASS__ . '::build',
				'sa' => 'createmsgindex',
				'extra_params' => [
					'resume',
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
			'remove' => [
				'func' => __CLASS__ . '::remove',
				'sa' => 'removecustom',
				'extra_params' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
		];
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Create a custom search index for the messages table.
	 *
	 * Called by ?action=admin;area=managesearch;sa=createmsgindex.
	 * Linked from the method screen.
	 * Requires the admin_forum permission.
	 * Depending on the size of the message table, the process is divided in steps.
	 */
	public static function build(): void
	{
		User::$me->isAllowedTo('admin_forum');

		// Scotty, we need more time...
		Sapi::setTimeLimit(600);
		Sapi::resetTimeout();

		Menu::$loaded['admin']['current_subsection'] = 'method';
		Utils::$context['page_title'] = Lang::$txt['search_index_custom'];

		$messages_per_batch = 50;

		$index_properties = [
			2 => [
				'column_definition' => 'small',
				'step_size' => 1000000,
			],
			4 => [
				'column_definition' => 'medium',
				'step_size' => 1000000,
				'max_size' => 16777215,
			],
			5 => [
				'column_definition' => 'large',
				'step_size' => 100000000,
				'max_size' => 2000000000,
			],
		];

		if (isset($_REQUEST['resume']) && !empty(Config::$modSettings['search_custom_index_resume'])) {
			Utils::$context['index_settings'] = Utils::jsonDecode(Config::$modSettings['search_custom_index_resume'], true);
			Utils::$context['start'] = (int) Utils::$context['index_settings']['resume_at'];

			unset(Utils::$context['index_settings']['resume_at']);

			Utils::$context['step'] = 1;
		} else {
			Utils::$context['index_settings'] = [
				'bytes_per_word' => isset($_REQUEST['bytes_per_word']) && isset($index_properties[$_REQUEST['bytes_per_word']]) ? (int) $_REQUEST['bytes_per_word'] : 2,
			];

			Utils::$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
			Utils::$context['step'] = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;

			// admin timeouts are painful when building these long indexes - but only if we actually have such things enabled
			if (empty(Config::$modSettings['securityDisable']) && $_SESSION['admin_time'] + 3300 < time() && Utils::$context['step'] >= 1) {
				$_SESSION['admin_time'] = time();
			}
		}

		if (Utils::$context['step'] !== 0) {
			User::$me->checkSession('request');
		}

		// Step 0: let the user determine how they like their index.
		if (Utils::$context['step'] === 0) {
			Utils::$context['sub_template'] = 'create_index';
		}

		// Step 1: insert all the words.
		if (Utils::$context['step'] === 1) {
			Utils::$context['sub_template'] = 'create_index_progress';

			if (Utils::$context['start'] === 0) {
				$tables = Db::$db->list_tables(false, Db::$db->prefix . 'log_search_words');

				if (!empty($tables)) {
					Db::$db->search_query(
						'drop_words_table',
						'
						DROP TABLE {db_prefix}log_search_words',
						[
						],
					);
				}

				Db::$db->create_word_search($index_properties[Utils::$context['index_settings']['bytes_per_word']]['column_definition']);

				// Temporarily switch back to not using a search index.
				if (!empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'custom') {
					Config::updateModSettings(['search_index' => '']);
				}

				// Don't let simultaneous processes be updating the search index.
				if (!empty(Config::$modSettings['search_custom_index_config'])) {
					Config::updateModSettings(['search_custom_index_config' => '']);
				}
			}

			$num_messages = [
				'done' => 0,
				'todo' => 0,
			];

			$request = Db::$db->query(
				'',
				'SELECT id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
				FROM {db_prefix}messages
				GROUP BY todo',
				[
					'starting_id' => Utils::$context['start'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];
			}
			Db::$db->free_result($request);

			if (empty($num_messages['todo'])) {
				Utils::$context['step'] = 2;
				Utils::$context['percentage'] = 80;
				Utils::$context['start'] = 0;
			} else {
				// Number of seconds before the next step.
				$stop = time() + 3;

				while (time() < $stop) {
					$inserts = [];
					$forced_break = false;
					$number_processed = 0;

					$request = Db::$db->query(
						'',
						'SELECT id_msg, body
						FROM {db_prefix}messages
						WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
						LIMIT {int:limit}',
						[
							'starting_id' => Utils::$context['start'],
							'ending_id' => Utils::$context['start'] + $messages_per_batch - 1,
							'limit' => $messages_per_batch,
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						// In theory it's possible for one of these to take friggin ages so add more timeout protection.
						if ($stop < time()) {
							$forced_break = true;
							break;
						}

						$number_processed++;

						foreach (self::getWordNumbers($row['body'], Utils::$context['index_settings']['bytes_per_word']) as $id_word) {
							$inserts[] = [$id_word, $row['id_msg']];
						}
					}
					$num_messages['done'] += $number_processed;
					$num_messages['todo'] -= $number_processed;
					Db::$db->free_result($request);

					Utils::$context['start'] += $forced_break ? $number_processed : $messages_per_batch;

					if (!empty($inserts)) {
						Db::$db->insert(
							'ignore',
							'{db_prefix}log_search_words',
							['id_word' => 'int', 'id_msg' => 'int'],
							$inserts,
							['id_word', 'id_msg'],
						);
					}

					if ($num_messages['todo'] === 0) {
						Utils::$context['step'] = 2;
						Utils::$context['start'] = 0;
						break;
					}

					Config::updateModSettings(['search_custom_index_resume' => Utils::jsonEncode(array_merge(Utils::$context['index_settings'], ['resume_at' => Utils::$context['start']]))]);
				}

				// Since there are still two steps to go, 80% is the maximum here.
				Utils::$context['percentage'] = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 3) * 80;
			}
		}
		// Step 2: removing the words that occur too often and are of no use.
		elseif (Utils::$context['step'] === 2) {
			if (Utils::$context['index_settings']['bytes_per_word'] < 4) {
				Utils::$context['step'] = 3;
			} else {
				$stop_words = Utils::$context['start'] === 0 || empty(Config::$modSettings['search_stopwords']) ? [] : explode(',', Config::$modSettings['search_stopwords']);

				$stop = time() + 3;

				Utils::$context['sub_template'] = 'create_index_progress';

				$max_messages = ceil(60 * Config::$modSettings['totalMessages'] / 100);

				while (time() < $stop) {
					$request = Db::$db->query(
						'',
						'SELECT id_word, COUNT(id_word) AS num_words
						FROM {db_prefix}log_search_words
						WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
						GROUP BY id_word
						HAVING COUNT(id_word) > {int:minimum_messages}',
						[
							'starting_id' => Utils::$context['start'],
							'ending_id' => Utils::$context['start'] + $index_properties[Utils::$context['index_settings']['bytes_per_word']]['step_size'] - 1,
							'minimum_messages' => $max_messages,
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						$stop_words[] = $row['id_word'];
					}
					Db::$db->free_result($request);

					Config::updateModSettings(['search_stopwords' => implode(',', $stop_words)]);

					if (!empty($stop_words)) {
						Db::$db->query(
							'',
							'DELETE FROM {db_prefix}log_search_words
							WHERE id_word in ({array_int:stop_words})',
							[
								'stop_words' => $stop_words,
							],
						);
					}

					Utils::$context['start'] += $index_properties[Utils::$context['index_settings']['bytes_per_word']]['step_size'];

					if (Utils::$context['start'] > $index_properties[Utils::$context['index_settings']['bytes_per_word']]['max_size']) {
						Utils::$context['step'] = 3;
						break;
					}
				}

				Utils::$context['percentage'] = 80 + round(Utils::$context['start'] / $index_properties[Utils::$context['index_settings']['bytes_per_word']]['max_size'], 3) * 20;
			}
		}

		// Step 3: remove words not distinctive enough.
		if (Utils::$context['step'] === 3) {
			Utils::$context['sub_template'] = 'create_index_done';

			Config::updateModSettings(['search_index' => 'custom', 'search_custom_index_config' => Utils::jsonEncode(Utils::$context['index_settings'])]);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}settings
				WHERE variable = {string:search_custom_index_resume}',
				[
					'search_custom_index_resume' => 'search_custom_index_resume',
				],
			);
		}

		SecurityToken::create('admin-msmpost');
		SecurityToken::create('admin-msm', 'get');
	}
	/**
	 * Removes the custom index.
	 *
	 * Called by ?action=admin;area=managesearch;sa=removecustom.
	 */
	public static function remove(): void
	{
		User::$me->checkSession('get');
		SecurityToken::validate('admin-msm', 'get');

		$tables = Db::$db->list_tables(false, Db::$db->prefix . 'log_search_words');

		if (!empty($tables)) {
			Db::$db->search_query(
				'drop_words_table',
				'
				DROP TABLE {db_prefix}log_search_words',
				[
				],
			);
		}

		Config::updateModSettings([
			'search_custom_index_config' => '',
			'search_custom_index_resume' => '',
		]);

		// Go back to the default search method.
		if (!empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'custom') {
			Config::updateModSettings([
				'search_index' => '',
			]);
		}

		Utils::redirectexit('action=admin;area=managesearch;sa=method');
	}

	/**
	 * Gets a series of integers to identify each unique word in a string.
	 *
	 * Repeated words in the string will only be represented once in the
	 * returned integer array.
	 *
	 * @param string $string A string.
	 * @param int $bytes_per_word Byte-length of the returned integers.
	 *    Defaults to custom search index's 'bytes_per_word' value, or 4 if that
	 *    is not set. Allowed values range between 1 and PHP_INT_SIZE.
	 * @return array Unique integers for each word in $string.
	 */
	public static function getWordNumbers(string $string, ?int $bytes_per_word): array
	{
		if (!isset($bytes_per_word)) {
			if (!empty(Config::$modSettings['search_custom_index_config'])) {
				$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);
				$bytes_per_word = $customIndexSettings['bytes_per_word'];
			} else {
				$bytes_per_word = 4;
			}
		}

		$bytes_per_word = min(max($bytes_per_word, 1), PHP_INT_SIZE);

		$returned_ints = [];

		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));

		foreach (Utils::extractWords($string, 2) as $word) {
			$word = trim($word, '-_\'');

			if ($word === '' || isset($returned_ints[$word])) {
				continue;
			}

			$encrypted = substr(crypt($word, 'uk'), 2, $bytes_per_word);

			$total = 0;

			for ($i = 0; $i < $bytes_per_word; $i++) {
				$total += $possible_chars[ord($encrypted[$i])] * pow(63, $i);
			}

			$returned_ints[] = $bytes_per_word == 4 ? min($total, 16777215) : $total;
		}

		return array_unique(array_values($returned_ints));
	}
}

?>