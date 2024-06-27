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
	 * @param string $methodName The method
	 * @param array $query_params Any parameters for the query
	 * @return bool Whether or not the specified method is supported
	 */
	public function supportsMethod(string $methodName, array $query_params = []): bool;

	/**
	 * Whether this method is valid for implementation or not.
	 *
	 * @return bool Whether or not this method is valid
	 */
	public function isValid(): bool;

	/**
	 * Gets the size, in bytes, of this API's search index.
	 *
	 * @return int Size of the index.
	 */
	public function getSize(): int;

	/**
	 * Gets whether the index for this API exists.
	 *
	 * @return string Either 'exists', 'partial', 'none', or null for APIs that
	 *    don't use an index.
	 */
	public function getStatus(): ?string;

	/**
	 * Callback function for usort used to sort the fulltext results.
	 * the order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int An integer indicating how the words should be sorted
	 */
	public function searchSort(string $a, string $b): int;

	/**
	 * Callback while preparing indexes for searching.
	 *
	 * @param string $word A word to index
	 * @param array $wordsSearch Search words
	 * @param array $wordsExclude Words to exclude
	 * @param bool $isExcluded Whether the specified word should be excluded
	 */
	public function prepareIndexes(string $word, array &$wordsSearch, array &$wordsExclude, bool $isExcluded): void;

	/**
	 * Search for indexed words.
	 *
	 * @param array $words An array of words
	 * @param array $search_data An array of search data
	 * @return mixed
	 */
	public function indexedWordQuery(array $words, array $search_data): mixed;

	/**
	 * Callback when a post is created.
	 *
	 * @see Msg::create()
	 *
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 */
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions): void;

	/**
	 * Callback when a post is modified.
	 *
	 * @see Msg::modify()
	 *
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions): void;

	/**
	 * Callback when a post is removed.
	 *
	 * @param int $id_msg The ID of the post that was removed
	 */
	public function postRemoved(int $id_msg): void;

	/**
	 * Callback when a topic is removed.
	 *
	 * @param array $topics The ID(s) of the removed topic(s)
	 */
	public function topicsRemoved(array $topics): void;

	/**
	 * Callback when a topic is merged.
	 *
	 * @param int $id_topic The ID of the topic that messages where merged into
	 * @param array $topics The ID(s) of the merged topic(s)
	 * @param array $msgs The ID(s) of the merged messages(s)
	 * @param ?string Optional rename all subjects for all messages.
	 */
	public function topicMerge(int $id_topic, array $topics, array $affected_msgs, ?string $subject): void;

	/**
	 * Callback when a topic is merged.
	 *
	 * @param int $id_topic The ID of the topic that messages where merged into
	 * @param array $msgs The ID(s) of the merged messages(s)
	 */
	public function topicSplit(int $id_topic, array $affected_msgs): void;

	/**
	 * Callback when a topic is moved.
	 *
	 * @param array $topics The ID(s) of the moved topic(s)
	 * @param int $board_to The board that the topics were moved to
	 */
	public function topicsMoved(array $topics, int $board_to): void;

	/**
	 * Lets APIs interact with Utils::$context when setting up the search form.
	 */
	public function formContext(): void;

	/**
	 * Lets APIs interact with Utils::$context when setting up the results page.
	 */
	public function resultsContext(): void;

	/**
	 * Sets whatever properties are necessary in order to perform the search.
	 *
	 * This is separate from the constructor because there are a number of other
	 * places where the search API will be loaded for other purposes.
	 *
	 */
	public function initializeSearch(): void;

	/**
	 * Callback for actually performing the search query.
	 *
	 * All of the arguments for this method are deprecated as of SMF 3.0.
	 * The relevant data is directly accessible in the properties of SearchApi.
	 *
	 * @param array $query_params An array of parameters for the query
	 * @param array $searchWords The words that were searched for
	 * @param array $excludedIndexWords Indexed words that should be excluded
	 * @param array $participants
	 * @param array $searchArray
	 * @return mixed
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray): void;

	/**
	 * Figures out which search result topics the user participated in.
	 *
	 */
	public function setParticipants(): void;

	/**
	 * Compresses $this->params to a string for use as an URL parameter.
	 *
	 * @return string URL-safe variant of a Base64 string.
	 */
	public function compressParams(): string;

	/**
	 * Gets info about sub-actions to support in the admin panel for this API.
	 *
	 * Keys of the returned array are action types (e.g. build, resume, remove).
	 *
	 * Values are sub-arrays with 'func', 'sa', and 'extra_params' elements:
	 *
	 *  - The 'func' element is the callable to execute for the sub-action.
	 *  - The 'sa' element is the value to use for the 'sa' URL parameter.
	 *  - The 'extra_params' element contains extra URL parameters to add to the
	 *    URL for the sub-action.
	 *
	 * @return array Info about sub-actions.
	 */
	public function getAdminSubactions(): array;

	/**
	 * Returns the expected Lang::$txt key for this API's localized label.
	 *
	 * @return string Localized label for this API.
	 */
	public function getLabel(): string;

	/**
	 * Returns the expected Lang::$txt key for this API's localized description.
	 *
	 * @return string Localized description for this API.
	 */
	public function getDescription(): string;
}

?>