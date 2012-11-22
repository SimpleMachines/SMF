<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Fulltext API, used when an SQL fulltext index is used
 */
class fulltext_search
{
	/**
	 * This is the last version of SMF that this was tested on, to protect against API changes.
	 * @var type
	 */
	public $version_compatible = 'SMF 2.1 Alpha 1';

	/**
	 * This won't work with versions of SMF less than this.
	 * @var type
	 */
	public $min_smf_version = 'SMF 2.1 Alpha 1';

	/**
	 * Is it supported?
	 *
	 * @var type
	 */
	public $is_supported = true;

	/**
	 * What words are banned?
	 * @var type
	 */
	protected $bannedWords = array();

	/**
	 * What is the minimum word length?
	 * @var type
	 */
	protected $min_word_length = 4;

	/**
	 * What databases support the fulltext index?
	 * @var type
	 */
	protected $supported_databases = array('mysql');

	/**
	 * fulltext_search::__construct()
	 *
	 */
	public function __construct()
	{
		global $smcFunc, $db_connection, $modSettings, $db_type;

		// Is this database supported?
		if (!in_array($db_type, $this->supported_databases))
		{
			$this->is_supported = false;
			return;
		}

		$this->bannedWords = empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']);
		$this->min_word_length = $this->_getMinWordLength();
	}

	/**
	 * fulltext_search::supportsMethod()
	 *
	 * Check whether the method can be performed by this API.
	 *
	 * @param mixed $methodName
	 * @param mixed $query_params
	 * @return
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		switch ($methodName)
		{
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
				return true;
			break;

			// All other methods, too bad dunno you.
			default:
				return false;
			break;
		}
	}

	/**
	 * fulltext_search::_getMinWordLength()
	 *
	 * What is the minimum word length full text supports?
	 *
	 * @return
	 */
	protected function _getMinWordLength()
	{
		global $smcFunc;

		// Try to determine the minimum number of letters for a fulltext search.
		$request = $smcFunc['db_search_query']('max_fulltext_length', '
			SHOW VARIABLES
			LIKE {string:fulltext_minimum_word_length}',
			array(
				'fulltext_minimum_word_length' => 'ft_min_word_len',
			)
		);
		if ($request !== false && $smcFunc['db_num_rows']($request) == 1)
		{
			list (, $min_word_length) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		// 4 is the MySQL default...
		else
			$min_word_length = 4;

		return $min_word_length;
	}

	/**
	 * callback function for usort used to sort the fulltext results.
	 * the order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int
	 */
	public function searchSort($a, $b)
	{
		global $modSettings, $excludedWords, $smcFunc;

		$x = $smcFunc['strlen']($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = $smcFunc['strlen']($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * fulltext_search::prepareIndexes()
	 *
	 * Do we have to do some work with the words we are searching for to prepare them?
	 *
	 * @param mixed $word
	 * @param mixed $wordsSearch
	 * @param mixed $wordsExclude
	 * @param mixed $isExcluded
	 * @return
	 */
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
		global $modSettings, $smcFunc;

		$subwords = text2words($word, null, false);

		if (empty($modSettings['search_force_index']))
		{
			// A boolean capable search engine and not forced to only use an index, we may use a non indexed search
			// this is harder on the server so we are restrictive here
			if (count($subwords) > 1 && preg_match('~[.:@$]~', $word))
			{
				// using special characters that a full index would ignore and the remaining words are short which would also be ignored
				if (($smcFunc['strlen'](current($subwords)) < $this->min_word_length) && ($smcFunc['strlen'](next($subwords)) < $this->min_word_length))
				{
					$wordsSearch['words'][] = trim($word, "/*- ");
					$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
				}
			}
			elseif ($smcFunc['strlen'](trim($word, "/*- ")) < $this->min_word_length)
			{
				// short words have feelings too
				$wordsSearch['words'][] = trim($word, "/*- ");
				$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
			}
		}

		$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
		$wordsSearch['indexed_words'][] = $fulltextWord;
		if ($isExcluded)
			$wordsExclude[] = $fulltextWord;
	}

	/**
	 * fulltext_search::indexedWordQuery()
	 *
	 * Search for indexed words.
	 *
	 * @param mixed $words
	 * @param mixed $search_data
	 * @return
	 */
	public function indexedWordQuery($words, $search_data)
	{
		global $modSettings, $smcFunc;

		$query_select = array(
			'id_msg' => 'm.id_msg',
		);
		$query_where = array();
		$query_params = $search_data['params'];

		if ($query_params['id_search'])
			$query_select['id_search'] = '{int:id_search}';

		$count = 0;
		if (empty($modSettings['search_simple_fulltext']))
			foreach ($words['words'] as $regularWord)
			{
				$query_where[] = 'm.body' . (in_array($regularWord, $query_params['excluded_words']) ? ' NOT' : '') . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:complex_body_' . $count . '}';
				$query_params['complex_body_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($regularWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $regularWord), '\\\'') . '[[:>:]]';
			}

		if ($query_params['user_query'])
			$query_where[] = '{raw:user_query}';
		if ($query_params['board_query'])
			$query_where[] = 'm.id_board {raw:board_query}';

		if ($query_params['topic'])
			$query_where[] = 'm.id_topic = {int:topic}';
		if ($query_params['min_msg_id'])
			$query_where[] = 'm.id_msg >= {int:min_msg_id}';
		if ($query_params['max_msg_id'])
			$query_where[] = 'm.id_msg <= {int:max_msg_id}';

		$count = 0;
		if (!empty($query_params['excluded_phrases']) && empty($modSettings['search_force_index']))
			foreach ($query_params['excluded_phrases'] as $phrase)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:exclude_subject_phrase_' . $count . '}';
				$query_params['exclude_subject_phrase_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
			}
		$count = 0;
		if (!empty($query_params['excluded_subject_words']) && empty($modSettings['search_force_index']))
			foreach ($query_params['excluded_subject_words'] as $excludedWord)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : 'RLIKE') . '{string:exclude_subject_words_' . $count . '}';
				$query_params['exclude_subject_words_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($excludedWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $excludedWord), '\\\'') . '[[:>:]]';
			}

		if (!empty($modSettings['search_simple_fulltext']))
		{
			$query_where[] = 'MATCH (body) AGAINST ({string:body_match})';
			$query_params['body_match'] = implode(' ', array_diff($words['indexed_words'], $query_params['excluded_index_words']));
		}
		else
		{
			$query_params['boolean_match'] = '';

			// remove any indexed words that are used in the complex body search terms
			$words['indexed_words'] = array_diff($words['indexed_words'], $words['complex_words']);

			foreach ($words['indexed_words'] as $fulltextWord)
				$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words']) ? '-' : '+') . $fulltextWord . ' ';
			$query_params['boolean_match'] = substr($query_params['boolean_match'], 0, -1);

			// if we have bool terms to search, add them in
			if ($query_params['boolean_match'])
				$query_where[] = 'MATCH (body) AGAINST ({string:boolean_match} IN BOOLEAN MODE)';
		}

		$ignoreRequest = $smcFunc['db_search_query']('insert_into_log_messages_fulltext', ($smcFunc['db_support_ignore'] ? ( '
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')') : '') . '
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params
		);

		return $ignoreRequest;
	}
}

?>