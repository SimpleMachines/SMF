<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

interface search_api_interface
{
	/**
	 * Check whether the specific search operation can be performed by this API.
	 * The operations are the functions listed in the interface, if not supported
	 * they need not be declared
	 *
	 * @access public
	 * @param string $methodName
	 * @param array $query_params
	 * @return boolean
	 */
	public function supportsMethod($methodName, $query_params = array());

	/**
	 * Whether this method is valid for implementation or not
	 *
	 * @access public
	 * @return bool
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
	 * @return int
	 */
	public function searchSort($a, $b);

	/**
	 * Callback while preparing indexes for searching
	 *
	 * @access public
	 * @param string $word
	 * @param array $wordsSearch
	 * @param array $wordsExclude
	 * @param bool $isExcluded
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded);

	/**
	 * Search for indexed words.
	 *
	 * @access public
	 * @param array $words
	 * @param array $search_data
	 * @return mixed
	 */
	public function indexedWordQuery(array $words, array $search_data);

	/**
	 * Callback when a post is created
	 * {@see createPost()}
	 *
	 * @access public
	 * @param array $msgOptions
	 * @param array $topicOptions
	 * @param array $posterOptions
	 * @return void
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions);

	/**
	 * Callback when a post is modified
	 * {@see modifyPost()}
	 *
	 * @access public
	 * @param array $msgOptions
	 * @param array $topicOptions
	 * @param array $posterOptions
	 * @return void
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions);

	/**
	 * Callback when a post is removed
	 *
	 * @access public
	 * @param int $id_msg
	 * @return void
	 */
	public function postRemoved($id_msg);

	/**
	 * Callback when a topic is removed
	 *
	 * @access public
	 * @param array $topics
	 * @return void
	 */
	public function topicsRemoved(array $topics);

	/**
	 * Callback when a topic is moved
	 *
	 * @access public
	 * @param array $topics
	 * @param int $board_to
	 * @return void
	 */
	public function topicsMoved(array $topics, $board_to);

	/**
	 * Callback for actually performing the search query
	 *
	 * @access public
	 * @param array $query_params
	 * @param array $searchWords
	 * @param array $excludedIndexWords
	 * @param array $participants
	 * @param array $searchArray
	 * @return mixed
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array $participants, array $searchArray);
}

abstract class search_api implements search_api_interface
{
	/**
	 * This is the last version of SMF that this was tested on, to protect against API changes.
	 * @var type
	 */
	public $version_compatible = 'SMF 2.1 Beta 2';

	/**
	 * This won't work with versions of SMF less than this.
	 * @var type
	 */
	public $min_smf_version = 'SMF 2.1 Beta 2';

	/**
	 * Is it supported?
	 * @var type
	 */
	public $is_supported = true;

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
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array $participants, array $searchArray)
	{
	}
}

?>