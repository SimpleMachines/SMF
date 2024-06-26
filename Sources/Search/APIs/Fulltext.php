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
use SMF\Search\SearchApi;
use SMF\Search\SearchApiInterface;
use SMF\SecurityToken;
use SMF\User;
use SMF\Utils;

/**
 * Class Fulltext
 * Used for fulltext index searching
 */
class Fulltext extends SearchApi implements SearchApiInterface
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
			'sa' => 'createfulltext',
			'func' => __CLASS__ . '::build',
		],
		'remove' => [
			'sa' => 'removefulltext',
			'func' => __CLASS__ . '::remove',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The minimum word length
	 */
	protected $min_word_length = 4;

	/**
	 * @var array Which databases support this method?
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
	 * The constructor function
	 */
	public function __construct()
	{
		// Is this database supported?
		if (!in_array(Config::$db_type, $this->supported_databases)) {
			$this->is_supported = false;

			return;
		}

		$this->min_word_length = $this->_getMinWordLength();

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod(string $methodName, array $query_params = []): bool
	{
		$return = false;

		switch ($methodName) {
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
				$return = true;
				break;

			// All other methods, too bad dunno you.
			default:
				$return = false;
				break;
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
	public function getSize(): int
	{
		if (
			!isset($this->size)
			&& isset(Utils::$context['table_info']['index_length'])
			&& is_int(Utils::$context['table_info']['index_length'])
		) {
			$this->size = Utils::$context['table_info']['index_length'];
		}

		if (isset($this->size)) {
			return $this->size;
		}

		// In theory, the rest of this will never be called, but just in case...
		$this->size = 0;

		if (Db::$db->title === POSTGRE_TITLE) {
			$request = Db::$db->query(
				'',
				'SELECT
					pg_indexes_size({string:tablename}) AS index_size',
				[
					'tablename' => Db::$db->prefix . 'messages',
				],
			);

			if ($request !== false && Db::$db->num_rows($request) > 0) {
				$row = Db::$db->fetch_assoc($request);
				$this->size = (int) $row['index_size'];
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
						'table_name' => str_replace('_', '\\_', $match[2]) . 'messages',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'messages',
					],
				);
			}

			if ($request !== false && Db::$db->num_rows($request) > 0) {
				$row = Db::$db->fetch_assoc($request);
				$this->size = (int) $row['Index_length'];
			}
		}

		Db::$db->free_result($request);

		return $this->size;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStatus(): ?string
	{
		if (!isset(Utils::$context['fulltext_index'])) {
			self::detectIndex();
		}

		return !empty(Utils::$context['fulltext_index']) ? 'exists' : 'none';
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchSort(string $a, string $b): int
	{
		$x = Utils::entityStrlen($a) - (in_array($a, $this->excludedWords) ? 1000 : 0);
		$y = Utils::entityStrlen($b) - (in_array($b, $this->excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes(string $word, array &$wordsSearch, array &$wordsExclude, bool $isExcluded): void
	{
		$subwords = Utils::extractWords($word, 2);

		if (empty(Config::$modSettings['search_force_index'])) {
			// A boolean capable search engine and not forced to only use an index, we may use a non indexed search
			// this is harder on the server so we are restrictive here
			if (count($subwords) > 1 && preg_match('~[.:@$]~', $word)) {
				// using special characters that a full index would ignore and the remaining words are short which would also be ignored
				if ((Utils::entityStrlen(current($subwords)) < $this->min_word_length) && (Utils::entityStrlen(next($subwords)) < $this->min_word_length)) {
					$wordsSearch['words'][] = trim($word, '/*- ');
					$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
				}
			} elseif (Utils::entityStrlen(trim($word, '/*- ')) < $this->min_word_length) {
				// short words have feelings too
				$wordsSearch['words'][] = trim($word, '/*- ');
				$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
			}
		}

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;

		if ($isExcluded) {
			$wordsExclude[] = $fulltextWord;
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
		$query_where = [];
		$query_params = $search_data['params'];

		if (Db::$db->title === POSTGRE_TITLE) {
			Config::$modSettings['search_simple_fulltext'] = true;
		}

		if ($query_params['id_search']) {
			$query_select['id_search'] = '{int:id_search}';
		}

		$count = 0;

		if (empty(Config::$modSettings['search_simple_fulltext'])) {
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
			foreach ($query_params['excluded_phrases'] as $phrase) {
				$query_where[] = 'subject NOT ' . $this->query_match_type . ' {string:exclude_subject_phrase_' . $count . '}';

				if ($this->query_match_type === 'RLIKE') {
					$query_params['exclude_subject_phrase_' . $count++] = self::wordBoundaryWrapper(self::escapeSqlRegex($phrase));
				} else {
					$query_params['exclude_subject_phrase_' . $count++] = '%' . Db::$db->escape_wildcard_string($phrase) . '%';
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

		if (!empty(Config::$modSettings['search_simple_fulltext'])) {
			if (Db::$db->title === POSTGRE_TITLE) {
				$language_ftx = Db::$db->search_language();

				$query_where[] = 'to_tsvector({string:language_ftx},body) @@ plainto_tsquery({string:language_ftx},{string:body_match})';
				$query_params['language_ftx'] = $language_ftx;
			} else {
				$query_where[] = 'MATCH (body) AGAINST ({string:body_match})';
			}
			$query_params['body_match'] = implode(' ', array_diff($words['indexed_words'], $query_params['excluded_index_words']));
		} else {
			$query_params['boolean_match'] = '';

			// remove any indexed words that are used in the complex body search terms
			$words['indexed_words'] = array_diff($words['indexed_words'], $words['complex_words']);

			if (Db::$db->title === POSTGRE_TITLE) {
				$row = 0;

				foreach ($words['indexed_words'] as $fulltextWord) {
					$query_params['boolean_match'] .= ($row != 0 ? '&' : '');
					$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words']) ? '!' : '') . $fulltextWord . ' ';
					$row++;
				}
			} else {
				foreach ($words['indexed_words'] as $fulltextWord) {
					$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words']) ? '-' : '+') . $fulltextWord . ' ';
				}
			}

			$query_params['boolean_match'] = substr($query_params['boolean_match'], 0, -1);

			// if we have bool terms to search, add them in
			if ($query_params['boolean_match']) {
				if (Db::$db->title === POSTGRE_TITLE) {
					$language_ftx = Db::$db->search_language();

					$query_where[] = 'to_tsvector({string:language_ftx},body) @@ plainto_tsquery({string:language_ftx},{string:boolean_match})';
					$query_params['language_ftx'] = $language_ftx;
				} else {
					$query_where[] = 'MATCH (body) AGAINST ({string:boolean_match} IN BOOLEAN MODE)';
				}
			}
		}

		$ignoreRequest = Db::$db->search_query(
			'insert_into_log_messages_fulltext',
			(Db::$db->support_ignore ? ('
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')') : '') . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m
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
	public function getAdminSubactions(): array
	{
		$subactions = [
			'build' => [
				'func' => __CLASS__ . '::build',
				'sa' => 'createfulltext',
				'extra_params' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
			'remove' => [
				'func' => __CLASS__ . '::remove',
				'sa' => 'removefulltext',
				'extra_params' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					Utils::$context['admin-msm_token_var'] => Utils::$context['admin-msm_token'],
				],
			],
		];

		if ($this->getStatus() === 'none' && !empty(Utils::$context['cannot_create_fulltext'])) {
			unset($subactions['build']);
		}

		return $subactions;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string
	{
		if ($this->getStatus() === 'none' && !empty(Utils::$context['cannot_create_fulltext'])) {
			return 'search_method_fulltext_cannot_create';
		}

		return parent::getDescription();
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Builds the fulltext index.
	 *
	 * Called by ?action=admin;area=managesearch;sa=createfulltext.
	 */
	public static function build(): void
	{
		User::$me->checkSession('get');
		SecurityToken::validate('admin-msm', 'get');

		if (Config::$db_type == 'postgresql') {
			Db::$db->query(
				'',
				'DROP INDEX IF EXISTS {db_prefix}messages_ftx',
				[
					'db_error_skip' => true,
				],
			);

			$language_ftx = Db::$db->search_language();

			Db::$db->query(
				'',
				'CREATE INDEX {db_prefix}messages_ftx ON {db_prefix}messages
				USING gin(to_tsvector({string:language},body))',
				[
					'language' => $language_ftx,
				],
			);
		} else {
			// Make sure it's gone before creating it.
			Db::$db->query(
				'',
				'ALTER TABLE {db_prefix}messages
				DROP INDEX body',
				[
					'db_error_skip' => true,
				],
			);

			Db::$db->query(
				'',
				'ALTER TABLE {db_prefix}messages
				ADD FULLTEXT body (body)',
				[
				],
			);
		}

		Utils::redirectexit('action=admin;area=managesearch;sa=method');
	}

	/**
	 * Removes the fulltext index.
	 *
	 * Called by ?action=admin;area=managesearch;sa=removefulltext.
	 */
	public static function remove(): void
	{
		User::$me->checkSession('get');
		SecurityToken::validate('admin-msm', 'get');

		self::detectIndex();

		if (Config::$db_type == 'postgresql') {
			Db::$db->query(
				'',
				'DROP INDEX IF EXISTS {db_prefix}messages_ftx',
				[
					'db_error_skip' => true,
				],
			);
		} else {
			Db::$db->query(
				'',
				'ALTER TABLE {db_prefix}messages
				DROP INDEX ' . implode(',
				DROP INDEX ', Utils::$context['fulltext_index']),
				[
					'db_error_skip' => true,
				],
			);
		}

		// Go back to the default search method.
		if (!empty(Config::$modSettings['search_index']) && Config::$modSettings['search_index'] == 'fulltext') {
			Config::updateModSettings([
				'search_index' => '',
			]);
		}

		Utils::redirectexit('action=admin;area=managesearch;sa=method');
	}

	/**
	 * Checks if the message table already has a fulltext index.
	 *
	 * Names of detected indexes are added to Utils::$context['fulltext_index'].
	 *
	 * If the database is incapable of creating a fulltext index, sets
	 * Utils::$context['cannot_create_fulltext'] to true;
	 */
	public static function detectIndex(): void
	{
		if (Db::$db->title === POSTGRE_TITLE) {
			$request = Db::$db->query(
				'',
				'SELECT
					indexname
				FROM pg_tables t
					LEFT OUTER JOIN
						(SELECT c.relname AS ctablename, ipg.relname AS indexname, indexrelname FROM pg_index x
							JOIN pg_class c ON c.oid = x.indrelid
							JOIN pg_class ipg ON ipg.oid = x.indexrelid
							JOIN pg_stat_all_indexes psai ON x.indexrelid = psai.indexrelid)
						AS foo
						ON t.tablename = foo.ctablename
				WHERE t.schemaname= {string:schema} and indexname = {string:messages_ftx}',
				[
					'schema' => 'public',
					'messages_ftx' => Db::$db->prefix . 'messages_ftx',
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['fulltext_index'][] = $row['indexname'];
			}
			Db::$db->free_result($request);
		} else {
			Utils::$context['fulltext_index'] = [];

			$request = Db::$db->query(
				'',
				'SHOW INDEX
				FROM {db_prefix}messages',
				[
				],
			);

			if ($request !== false || Db::$db->num_rows($request) != 0) {
				while ($row = Db::$db->fetch_assoc($request)) {
					if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT')) {
						Utils::$context['fulltext_index'][] = $row['Key_name'];
					}
				}
				Db::$db->free_result($request);

				if (is_array(Utils::$context['fulltext_index'])) {
					Utils::$context['fulltext_index'] = array_unique(Utils::$context['fulltext_index']);
				}
			}

			if (preg_match('~^`(.+?)`\.(.+?)$~', Db::$db->prefix, $match) !== 0) {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					[
						'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
						'table_name' => str_replace('_', '\\_', $match[2]) . 'messages',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'messages',
					],
				);
			}

			if ($request !== false) {
				while ($row = Db::$db->fetch_assoc($request)) {
					if (
						isset($row['Engine'])
						&& strtolower($row['Engine']) != 'myisam'
						&& !(strtolower($row['Engine']) == 'innodb' && version_compare(Db::$db->get_version(), '5.6.4', '>='))
					) {
						Utils::$context['cannot_create_fulltext'] = true;
					}
				}

				Db::$db->free_result($request);
			}
		}
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * SMF\Search\APIs\Fulltext::_getMinWordLength()
	 *
	 * What is the minimum word length full text supports?
	 *
	 * @return int The minimum word length
	 */
	protected function _getMinWordLength(): int
	{
		if (Config::$db_type == 'postgresql') {
			return 0;
		}

		// Try to determine the minimum number of letters for a fulltext search.
		$request = Db::$db->search_query(
			'max_fulltext_length',
			'
			SHOW VARIABLES
			LIKE {string:fulltext_minimum_word_length}',
			[
				'fulltext_minimum_word_length' => 'ft_min_word_len',
			],
		);

		if ($request !== false && Db::$db->num_rows($request) == 1) {
			list(, $min_word_length) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}
		// 4 is the MySQL default...
		else {
			$min_word_length = 4;
		}

		return (int) $min_word_length;
	}
}

?>