<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Class fulltext_search
 * Used for fulltext index searching
 */
class fulltext_search extends search_api
{
	/**
	 * @var array Which words are banned
	 */
	protected $bannedWords = array();

	/**
	 * @var int The minimum word length
	 */
	protected $min_word_length = 4;

	/**
	 * @var array Which databases support this method?
	 */
	protected $supported_databases = array('mysql', 'postgresql');

	/**
	 * The constructor function
	 */
	public function __construct()
	{
		global $modSettings, $db_type;

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
	 * {@inheritDoc}
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		$return = false;
		switch ($methodName)
		{
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
			case 'postRemoved':
				$return = true;
				break;

			// All other methods, too bad dunno you.
			default:
				$return = false;
				break;
		}

		// Maybe parent got support
		if (!$return)
			$return = parent::supportsMethod($methodName, $query_params);

		return $return;
	}

	/**
	 * fulltext_search::_getMinWordLength()
	 *
	 * What is the minimum word length full text supports?
	 *
	 * @return int The minimum word length
	 */
	protected function _getMinWordLength()
	{
		global $smcFunc, $db_type;

		if ($db_type == 'postgresql')
			return 0;
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
	 * {@inheritDoc}
	 */
	public function searchSort($a, $b)
	{
		global $excludedWords, $smcFunc;

		$x = $smcFunc['strlen']($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = $smcFunc['strlen']($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded)
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
	 * {@inheritDoc}
	 */
	public function indexedWordQuery(array $words, array $search_data)
	{
		global $modSettings, $smcFunc;

		$query_select = array(
			'id_msg' => 'm.id_msg',
		);
		$query_where = array();
		$query_params = $search_data['params'];

		if ($smcFunc['db_title'] == "PostgreSQL")
			$modSettings['search_simple_fulltext'] = true;

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
			if ($smcFunc['db_title'] == "PostgreSQL")
			{
				$language_ftx = $smcFunc['db_search_language']();

				$query_where[] = 'to_tsvector({string:language_ftx},body) @@ plainto_tsquery({string:language_ftx},{string:body_match})';
				$query_params['language_ftx'] = $language_ftx;
			}
			else
				$query_where[] = 'MATCH (body) AGAINST ({string:body_match})';
			$query_params['body_match'] = implode(' ', array_diff($words['indexed_words'], $query_params['excluded_index_words']));
		}
		else
		{
			$query_params['boolean_match'] = '';

			// remove any indexed words that are used in the complex body search terms
			$words['indexed_words'] = array_diff($words['indexed_words'], $words['complex_words']);

			if ($smcFunc['db_title'] == "PostgreSQL")
			{
				$row = 0;
				foreach ($words['indexed_words'] as $fulltextWord)
				{
					$query_params['boolean_match'] .= ($row <> 0 ? '&' : '');
					$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words']) ? '!' : '') . $fulltextWord . ' ';
					$row++;
				}
			}
			else
				foreach ($words['indexed_words'] as $fulltextWord)
					$query_params['boolean_match'] .= (in_array($fulltextWord, $query_params['excluded_index_words']) ? '-' : '+') . $fulltextWord . ' ';

			$query_params['boolean_match'] = substr($query_params['boolean_match'], 0, -1);

			// if we have bool terms to search, add them in
			if ($query_params['boolean_match'])
			{
				if ($smcFunc['db_title'] == "PostgreSQL")
				{
					$language_ftx = $smcFunc['db_search_language']();

					$query_where[] = 'to_tsvector({string:language_ftx},body) @@ plainto_tsquery({string:language_ftx},{string:boolean_match})';
					$query_params['language_ftx'] = $language_ftx;
				}
				else
					$query_where[] = 'MATCH (body) AGAINST ({string:boolean_match} IN BOOLEAN MODE)';
			}
		}

		$ignoreRequest = $smcFunc['db_search_query']('insert_into_log_messages_fulltext', ($smcFunc['db_support_ignore'] ? ('
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