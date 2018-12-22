<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

/**
 * Interface search_api_interface
 */
interface search_api_interface
{
	/**
	 * Check whether the specific search operation can be performed by this API.
	 * The operations are the functions listed in the interface, if not supported
	 * they need not be declared
	 *
	 * @access public
	 * @param string $methodName The method
	 * @param array $query_params Any parameters for the query
	 * @return boolean Whether or not the specified method is supported
	 */
	public function supportsMethod($methodName, $query_params = array());

	/**
	 * Whether this method is valid for implementation or not
	 *
	 * @access public
	 * @return bool Whether or not this method is valid
	 */
	public function isValid();

	/**
	 * Callback function for usort used to sort the fulltext results.
	 * the order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @access public
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int An integer indicating how the words should be sorted
	 */
	public function searchSort($a, $b);

	/**
	 * Callback while preparing indexes for searching
	 *
	 * @access public
	 * @param string $word A word to index
	 * @param array $wordsSearch Search words
	 * @param array $wordsExclude Words to exclude
	 * @param bool $isExcluded Whether the specfied word should be excluded
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded);

	/**
	 * Search for indexed words.
	 *
	 * @access public
	 * @param array $words An array of words
	 * @param array $search_data An array of search data
	 * @return mixed
	 */
	public function indexedWordQuery(array $words, array $search_data);

	/**
	 * Callback when a post is created
	 *
	 * @see createPost()
	 *
	 * @access public
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions);

	/**
	 * Callback when a post is modified
	 *
	 * @see modifyPost()
	 *
	 * @access public
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions);

	/**
	 * Callback when a post is removed
	 *
	 * @access public
	 * @param int $id_msg The ID of the post that was removed
	 * @return void
	 */
	public function postRemoved($id_msg);

	/**
	 * Callback when a topic is removed
	 *
	 * @access public
	 * @param array $topics The ID(s) of the removed topic(s)
	 * @return void
	 */
	public function topicsRemoved(array $topics);

	/**
	 * Callback when a topic is moved
	 *
	 * @access public
	 * @param array $topics The ID(s) of the moved topic(s)
	 * @param int $board_to The board that the topics were moved to
	 * @return void
	 */
	public function topicsMoved(array $topics, $board_to);

	/**
	 * Callback for actually performing the search query
	 *
	 * @access public
	 * @param array $query_params An array of parameters for the query
	 * @param array $searchWords The words that were searched for
	 * @param array $excludedIndexWords Indexed words that should be excluded
	 * @param array $participants
	 * @param array $searchArray
	 * @return mixed
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray);
}

/**
 * Class search_api
 */
abstract class search_api implements search_api_interface
{
	/**
	 * @var string The last version of SMF that this was tested on. Helps protect against API changes.
	 */
	public $version_compatible = 'SMF 2.1 RC1';

	/**
	 * @var string The minimum SMF version that this will work with
	 */
	public $min_smf_version = 'SMF 2.1 RC1';

	/**
	 * @var bool Whether or not it's supported
	 */
	public $is_supported = true;

	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod($methodName, $query_params = null)
	{
		switch ($methodName)
		{
			case 'postRemoved':
				return true;
				break;

			// All other methods, too bad dunno you.
			default:
				return false;
				break;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function isValid()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchSort($a, $b)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function indexedWordQuery(array $words, array $search_data)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postRemoved($id_msg)
	{

		global $smcFunc;

		$result = $smcFunc['db_query']('', '
			SELECT DISTINCT id_search
			FROM {db_prefix}log_search_results
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => $id_msg,
			)
		);

		$id_searchs = array();
		while ($row = $smcFunc['db_fetch_assoc']($result))
			$id_searchs[] = $row['id_search'];

		if (count($id_searchs) < 1)
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_search_results
			WHERE id_search in ({array_int:id_searchs})',
			array(
				'id_searchs' => $id_searchs,
			)
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_search_topics
			WHERE id_search in ({array_int:id_searchs})',
			array(
				'id_searchs' => $id_searchs,
			)
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_search_messages
			WHERE id_search in ({array_int:id_searchs})',
			array(
				'id_searchs' => $id_searchs,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsRemoved(array $topics)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsMoved(array $topics, $board_to)
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray)
	{
	}
}

?>