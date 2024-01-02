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

namespace SMF\Search\APIs;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Search\SearchApi;
use SMF\Search\SearchApiInterface;
use SMF\Utils;

/**
 * Class Fulltext
 * Used for fulltext index searching
 */
class Fulltext extends SearchApi implements SearchApiInterface
{
	/**
	 * @var array Which words are banned
	 */
	protected $bannedWords = [];

	/**
	 * @var int The minimum word length
	 */
	protected $min_word_length = 4;

	/**
	 * @var array Which databases support this method?
	 */
	protected $supported_databases = ['mysql', 'postgresql'];

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

		$this->bannedWords = empty(Config::$modSettings['search_banned_words']) ? [] : explode(',', Config::$modSettings['search_banned_words']);
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

	/**
	 * {@inheritDoc}
	 */
	public function searchSort(string $a, string $b): int
	{
		global $excludedWords;

		$x = Utils::entityStrlen($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = Utils::entityStrlen($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes(string $word, array &$wordsSearch, array &$wordsExclude, bool $isExcluded): void
	{
		$subwords = Utils::text2words($word, PHP_INT_MAX, false);

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
}

?>