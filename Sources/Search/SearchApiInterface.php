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

namespace SMF\Search;

use SMF\Msg;

/**
 * Interface SearchApiInterface
 */
interface SearchApiInterface
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
	public function supportsMethod($methodName, $query_params = array()): bool;

	/**
	 * Whether this method is valid for implementation or not.
	 *
	 * @access public
	 * @return bool Whether or not this method is valid
	 */
	public function isValid(): bool;

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
	public function searchSort($a, $b): int;

	/**
	 * Callback while preparing indexes for searching.
	 *
	 * @access public
	 * @param string $word A word to index
	 * @param array $wordsSearch Search words
	 * @param array $wordsExclude Words to exclude
	 * @param bool $isExcluded Whether the specified word should be excluded
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded): void;

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
	 * Callback when a post is created.
	 *
	 * @see Msg::create()
	 *
	 * @access public
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions): void;

	/**
	 * Callback when a post is modified.
	 *
	 * @see Msg::modify()
	 *
	 * @access public
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions): void;

	/**
	 * Callback when a post is removed.
	 *
	 * @access public
	 * @param int $id_msg The ID of the post that was removed
	 * @return void
	 */
	public function postRemoved($id_msg): void;

	/**
	 * Callback when a topic is removed.
	 *
	 * @access public
	 * @param array $topics The ID(s) of the removed topic(s)
	 * @return void
	 */
	public function topicsRemoved(array $topics): void;

	/**
	 * Callback when a topic is moved.
	 *
	 * @access public
	 * @param array $topics The ID(s) of the moved topic(s)
	 * @param int $board_to The board that the topics were moved to
	 * @return void
	 */
	public function topicsMoved(array $topics, $board_to): void;

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

?>