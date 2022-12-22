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

/**
 * Class SearchApi
 */
abstract class SearchApi implements SearchApiInterface
{
	/**
	 * @var string
	 *
	 * The maximum SMF version that this will work with.
	 */
	public string $version_compatible = '3.0.999';

	/**
	 * @var string
	 *
	 * The minimum SMF version that this will work with.
	 */
	public string $min_smf_version = '3.0 Alpha 1';

	/**
	 * @var bool
	 *
	 * Whether or not it's supported.
	 */
	public bool $is_supported = true;

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