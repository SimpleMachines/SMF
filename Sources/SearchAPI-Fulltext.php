<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class fulltext_search
{
	// This is the last version of SMF that this was tested on, to protect against API changes.
	public $version_compatible = 'SMF 2.1 Alpha 1';
	// This won't work with versions of SMF less than this.
	public $min_smf_version = 'SMF 2.1 Alpha 1';
	// Is it supported?
	public $is_supported = true;

	// Can we do a boolean search - tested on construct.
	protected $canDoBooleanSearch = false;
	// What words are banned?
	protected $bannedWords = array();
	// What is the minimum word length?
	protected $min_word_length = 4;
	// What databases support the fulltext index?
	protected $supported_databases = array('mysql');

	public function __construct()
	{
		global $smcFunc, $db_connection, $modSettings, $db_type;

		// Is this database supported?
		if (!in_array($db_type, $this->supported_databases))
		{
			$this->is_supported = false;
			return;
		}

		// Some MySQL versions are superior to others :P.
		$this->canDoBooleanSearch = version_compare($smcFunc['db_server_info']($db_connection), '4.0.1', '>=');

		$this->bannedWords = empty($modSettings['search_banned_words']) ? array() : explode(',', $modSettings['search_banned_words']);
		$this->min_word_length = $this->_getMinWordLength();
	}

	// Check whether the method can be performed by this API.
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

	// What is the minimum word length full text supports?
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
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int
	 */
	public function searchSort($a, $b)
	{
		global $modSettings, $excludedWords;

		$x = strlen($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = strlen($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $x < $y ? 1 : ($x > $y ? -1 : 0);
	}

	// Do we have to do some work with the words we are searching for to prepare them?
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
		global $modSettings, $smcFunc;

		$subwords = text2words($word, null, false);

		if (!$this->canDoBooleanSearch && count($subwords) > 1 && empty($modSettings['search_force_index']))
			$wordsSearch['words'][] = $word;
		// boolean capable search engine but perhaps using special characters or a short word and not forced to only use an index, Regex it is then
		elseif (empty($modSettings['search_force_index']) && $this->canDoBooleanSearch)
		{
			if ((count($subwords) > 1 && preg_match('~[.:@]~', $word)) || ($smcFunc['strlen'](trim($word, "/*- ")) < $this->min_word_length))
			{
				// this will be used in a LIKE or RLIKE term, we will remove it (later) from our indexed_words array
				$wordsSearch['words'][] = trim($word, "/*- ");
				$wordsSearch['complex_words'][] = count($subwords) === 1 ? $word : '"' . $word . '"';
			}
		}
		
		if ($this->canDoBooleanSearch)
		{
			$fulltextWord = count($subwords) === 1 ? $word : '"' . $word . '"';
			$wordsSearch['indexed_words'][] = $fulltextWord;
			if ($isExcluded)
				$wordsExclude[] = $fulltextWord;
		}
		// Excluded phrases don't benefit from being split into subwords.
		elseif (count($subwords) > 1 && $isExcluded)
			return;
		else
		{
			$relyOnIndex = true;
			foreach ($subwords as $subword)
			{
				if (($smcFunc['strlen']($subword) >= $this->min_word_length) && !in_array($subword, $this->bannedWords))
				{
					$wordsSearch['indexed_words'][] = $subword;
					if ($isExcluded)
						$wordsExclude[] = $subword;
				}
				elseif (!in_array($subword, $this->bannedWords))
					$relyOnIndex = false;
			}

			if ($this->canDoBooleanSearch && !$relyOnIndex && empty($modSettings['search_force_index']))
				$wordsSearch['words'][] = $word;
		}
	}

	// Search for indexed words.
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
		elseif ($this->canDoBooleanSearch)
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
		else
		{
			$count = 0;
			foreach ($words['indexed_words'] as $fulltextWord)
			{
				$query_where[] = (in_array($fulltextWord, $query_params['excluded_index_words']) ? 'NOT ' : '') . 'MATCH (body) AGAINST ({string:fulltext_match_' . $count . '})';
				$query_params['fulltext_match_' . $count++] = $fulltextWord;
			}
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