<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Search\APIs;

use SMF\Config;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;
use SMF\Search\SearchApi;

/**
 * Used for the "custom search index" option
 * Class Custom
 */
class Custom extends SearchApi
{
	/**
	 * @var array Index settings
	 */
	protected $indexSettings = array();

	/**
	 * @var array An array of banned words
	 */
	protected $bannedWords = array();

	/**
	 * @var int|null Minimum word length (null for no minimum)
	 */
	protected $min_word_length = null;

	/**
	 * @var array Which databases support this method
	 */
	protected $supported_databases = array('mysql', 'postgresql');

	/**
	 * Constructor function
	 */
	public function __construct()
	{
		// Is this database supported?
		if (!in_array(Config::$db_type, $this->supported_databases))
		{
			$this->is_supported = false;
			return;
		}

		if (empty(Config::$modSettings['search_custom_index_config']))
			return;

		$this->indexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

		$this->bannedWords = empty(Config::$modSettings['search_stopwords']) ? array() : explode(',', Config::$modSettings['search_stopwords']);
		$this->min_word_length = $this->indexSettings['bytes_per_word'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod($methodName, $query_params = null): bool
	{
		$return = false;
		switch ($methodName)
		{
			case 'isValid':
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
			case 'postCreated':
			case 'postModified':
				$return = true;
				break;

			// All other methods, too bad dunno you.
			default:
				$return = false;
		}

		// Maybe parent got support
		if (!$return)
			$return = parent::supportsMethod($methodName, $query_params);

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
	public function searchSort($a, $b): int
	{
		global $excludedWords;

		$x = strlen($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = strlen($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $y < $x ? 1 : ($y > $x ? -1 : 0);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded): void
	{
		$subwords = text2words($word, $this->min_word_length, true);

		if (empty(Config::$modSettings['search_force_index']))
			$wordsSearch['words'][] = $word;

		// Excluded phrases don't benefit from being split into subwords.
		if (count($subwords) > 1 && $isExcluded)
			return;
		else
		{
			foreach ($subwords as $subword)
			{
				if (Utils::entityStrlen($subword) >= $this->min_word_length && !in_array($subword, $this->bannedWords))
				{
					$wordsSearch['indexed_words'][] = $subword;

					if ($isExcluded)
						$wordsExclude[] = $subword;
				}
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function indexedWordQuery(array $words, array $search_data)
	{
		// Specify the function to search with. Regex is for word boundaries.
		$is_search_regex = !empty(Config::$modSettings['search_match_words']) && !$search_data['no_regexp'];
		$query_match_type = $is_search_regex ? 'RLIKE' : 'LIKE';
		$word_boundary_wrapper = function(string $str): string
		{
			return sprintf(Db::$db->supports_pcre ? '\\b%s\\b' : '[[:<:]]%s[[:>:]]', $str);
		};
		$escape_sql_regex = function(string $str): string
		{
			return addcslashes(preg_replace('/[\[\]$.+*?&^|{}()]/', '[$0]', $str), '\\\'');
		};

		$query_select = array(
			'id_msg' => 'm.id_msg',
		);
		$query_inner_join = array();
		$query_left_join = array();
		$query_where = array();
		$query_params = $search_data['params'];

		if ($query_params['id_search'])
			$query_select['id_search'] = '{int:id_search}';

		$count = 0;
		foreach ($words['words'] as $regularWord)
		{
			if (in_array($regularWord, $query_params['excluded_words']))
				$query_where[] = 'm.body NOT ' . $query_match_type . ' {string:complex_body_' . $count . '}';
			else
				$query_where[] = 'm.body ' . $query_match_type . ' {string:complex_body_' . $count . '}';

			if ($is_search_regex)
				$query_params['complex_body_' . $count++] = $word_boundary_wrapper($escape_sql_regex($regularWord));
			else
				$query_params['complex_body_' . $count++] = '%' . Db::$db->escape_wildcard_string($regularWord) . '%';
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
		if (!empty($query_params['excluded_phrases']) && empty(Config::$modSettings['search_force_index']))
			foreach ($query_params['excluded_phrases'] as $phrase)
			{
				$query_where[] = 'subject NOT ' . $query_match_type . ' {string:exclude_subject_words_' . $count . '}';

				if ($is_search_regex)
					$query_params['exclude_subject_words_' . $count++] = $word_boundary_wrapper($escape_sql_regex($excludedWord));
				else
					$query_params['exclude_subject_words_' . $count++] = '%' . Db::$db->escape_wildcard_string($excludedWord) . '%';
			}
		$count = 0;
		if (!empty($query_params['excluded_subject_words']) && empty(Config::$modSettings['search_force_index']))
			foreach ($query_params['excluded_subject_words'] as $excludedWord)
			{
				$query_where[] = 'subject NOT ' . $query_match_type . ' {string:exclude_subject_words_' . $count . '}';

				if ($is_search_regex)
					$query_params['exclude_subject_words_' . $count++] = $word_boundary_wrapper($escape_sql_regex($excludedWord));
				else
					$query_params['exclude_subject_words_' . $count++] = '%' . Db::$db->escape_wildcard_string($excludedWord) . '%';
			}

		$numTables = 0;
		$prev_join = 0;
		foreach ($words['indexed_words'] as $indexedWord)
		{
			$numTables++;
			if (in_array($indexedWord, $query_params['excluded_index_words']))
			{
				$query_left_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_word = ' . $indexedWord . ' AND lsw' . $numTables . '.id_msg = m.id_msg)';
				$query_where[] = '(lsw' . $numTables . '.id_word IS NULL)';
			}
			else
			{
				$query_inner_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_msg = ' . ($prev_join === 0 ? 'm' : 'lsw' . $prev_join) . '.id_msg)';
				$query_where[] = 'lsw' . $numTables . '.id_word = ' . $indexedWord;
				$prev_join = $numTables;
			}
		}

		$ignoreRequest = Db::$db->search_query('insert_into_log_messages_fulltext', (Db::$db->support_ignore ? ('
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
			$query_params
		);

		return $ignoreRequest;
	}

	/**
	 * {@inheritDoc}
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
		$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);

		$inserts = array();
		foreach (text2words($msgOptions['body'], $customIndexSettings['bytes_per_word'], true) as $word)
			$inserts[] = array($word, $msgOptions['id']);

		if (!empty($inserts))
			Db::$db->insert('ignore',
				'{db_prefix}log_search_words',
				array('id_word' => 'int', 'id_msg' => 'int'),
				$inserts,
				array('id_word', 'id_msg')
			);
	}

	/**
	 * {@inheritDoc}
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
		if (isset($msgOptions['body']))
		{
			$customIndexSettings = Utils::jsonDecode(Config::$modSettings['search_custom_index_config'], true);
			$stopwords = empty(Config::$modSettings['search_stopwords']) ? array() : explode(',', Config::$modSettings['search_stopwords']);
			$old_body = isset($msgOptions['old_body']) ? $msgOptions['old_body'] : '';

			// create thew new and old index
			$old_index = text2words($old_body, $customIndexSettings['bytes_per_word'], true);
			$new_index = text2words($msgOptions['body'], $customIndexSettings['bytes_per_word'], true);

			// Calculate the words to be added and removed from the index.
			$removed_words = array_diff(array_diff($old_index, $new_index), $stopwords);
			$inserted_words = array_diff(array_diff($new_index, $old_index), $stopwords);

			// Delete the removed words AND the added ones to avoid key constraints.
			if (!empty($removed_words))
			{
				$removed_words = array_merge($removed_words, $inserted_words);
				Db::$db->query('', '
					DELETE FROM {db_prefix}log_search_words
					WHERE id_msg = {int:id_msg}
						AND id_word IN ({array_int:removed_words})',
					array(
						'removed_words' => $removed_words,
						'id_msg' => $msgOptions['id'],
					)
				);
			}

			// Add the new words to be indexed.
			if (!empty($inserted_words))
			{
				$inserts = array();
				foreach ($inserted_words as $word)
					$inserts[] = array($word, $msgOptions['id']);
				Db::$db->insert('insert',
					'{db_prefix}log_search_words',
					array('id_word' => 'string', 'id_msg' => 'int'),
					$inserts,
					array('id_word', 'id_msg')
				);
			}
		}
	}
}

?>