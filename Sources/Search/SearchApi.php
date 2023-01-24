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

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;
use SMF\PackageManager\SubsPackage;

/**
 * Class SearchApi
 */
abstract class SearchApi implements SearchApiInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => 'findSearchAPI',
			'detect' => 'loadSearchAPIs',
		),
		'prop_names' => array(
			'loadedApi' => 'searchAPI',
		),
	);

	/*******************
	 * Public properties
	 *******************/

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

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var object
	 *
	 * The loaded search API.
	 *
	 * For backward compatibilty, also referenced as global $searchAPI.
	 */
	public static $loadedApi;

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function supportsMethod($methodName, $query_params = array()): bool
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
	public function isValid(): bool
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchSort($a, $b): int
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded): void
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
	public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function postRemoved($id_msg): void
	{
		$result = Db::$db->query('', '
			SELECT DISTINCT id_search
			FROM {db_prefix}log_search_results
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => $id_msg,
			)
		);

		$id_searchs = array();
		while ($row = Db::$db->fetch_assoc($result))
			$id_searchs[] = $row['id_search'];

		if (count($id_searchs) < 1)
			return;

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_search_results
			WHERE id_search in ({array_int:id_searchs})',
			array(
				'id_searchs' => $id_searchs,
			)
		);

		Db::$db->query('', '
			DELETE FROM {db_prefix}log_search_topics
			WHERE id_search in ({array_int:id_searchs})',
			array(
				'id_searchs' => $id_searchs,
			)
		);

		Db::$db->query('', '
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
	public function topicsRemoved(array $topics): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function topicsMoved(array $topics, $board_to): void
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray)
	{
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Creates a search API and returns the object.
	 *
	 * @return SMF\Search\SearchApiInterface An instance of the search API interface.
	 */
	final public static function load()
	{
		global $txt;

		// Load up the search API we are going to use.
		Config::$modSettings['search_index'] = empty(Config::$modSettings['search_index']) ? 'standard' : Config::$modSettings['search_index'];

		$search_class_name = __NAMESPACE__ . '\\APIs\\' . ucwords(Config::$modSettings['search_index']);

		if (!class_exists($search_class_name))
			fatal_lang_error('search_api_missing');

		// Create an instance of the search API and check it is valid for this version of SMF.
		self::$loadedApi = new $search_class_name();

		// An invalid Search API.
		if (!self::$loadedApi || !(self::$loadedApi instanceof SearchApiInterface) || (self::$loadedApi->supportsMethod('isValid') && !self::$loadedApi->isValid()) || !SubsPackage::matchPackageVersion(SMF_VERSION, self::$loadedApi->min_smf_version . '-' . self::$loadedApi->version_compatible))
		{
			// Log the error.
			loadLanguage('Errors');
			log_error(sprintf($txt['search_api_not_compatible'], 'Search/APIs/' . ucwords(Config::$modSettings['search_index']) . '.php'), 'critical');

			// Fall back to standard search.
			if (Config::$modSettings['search_index'] !== 'standard')
			{
				Config::$modSettings['search_index'] = 'standard';
				self::load();
			}
			// This should never happen, but...
			else
				self::$loadedApi = false;
		}

		return self::$loadedApi;
	}

	/**
	 * Get the installed Search API implementations.
	 *
	 * @return array Info about the detected search APIs.
	 */
	final public static function detect(): array
	{
		$loadedApis = array();

		$api_classes = new \GlobIterator(__DIR__ . '/APIs/*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($api_classes as $file_path => $file_info)
		{
			$class_name = $file_info->getBasename('.php');
			$index_name = strtolower($class_name);
			$fully_qualified_class_name = __NAMESPACE__ . '\\APIs\\' . $class_name;

			if (!class_exists($fully_qualified_class_name))
				continue;

			$search_api = new $fully_qualified_class_name();

			if (!($search_api instanceof SearchApiInterface) || !($search_api instanceof SearchApi))
				continue;

			if (!$search_api->is_supported)
				continue;

			$loadedApis[$index_name] = array(
				'filename' => 'Search/APIs/' . $file_info->getBasename(),
				'setting_index' => $index_name,
				'has_template' => in_array($index_name, array('custom', 'fulltext', 'standard')),
				'label' => $index_name && isset($txt['search_index_' . $index_name]) ? $txt['search_index_' . $index_name] : '',
				'desc' => $index_name && isset($txt['search_index_' . $index_name . '_desc']) ? $txt['search_index_' . $index_name . '_desc'] : '',
			);
		}

		// Check for search APIs using the old SearchAPI-*.php system.
		// Kept for backward compatibility.
		$source_files = new \GlobIterator(Config::$sourcedir . '/SearchAPI-*.php', \FilesystemIterator::NEW_CURRENT_AND_KEY);

		foreach ($source_files as $file_path => $file_info)
		{
			// Skip if not a file.
			if (!$file_info->isFile())
				continue;

			// Skip if file name doesn't match.
			if (!preg_match('~^SearchAPI-([A-Za-z\d_]+)\.php$~', $file_info->getFilename(), $matches))
				continue;

			$index_name = strtolower($matches[1]);
			$class_name = $index_name . '_search';

			// Skip if we already have an API by this name.
			if (isset($loadedApis[$index_name]))
				continue;

			// Check this is definitely a valid API.
			$fp = fopen($file_info->getPathname(), 'rb');
			$header = fread($fp, 4096);
			fclose($fp);

			if (strpos($header, '* SearchAPI-' . $matches[1] . '.php') === false)
				continue;

			require_once($file_info->getPathname());

			if (!class_exists($class_name, false))
				continue;

			$search_api = new $class_name();

			if (!($search_api instanceof SearchApiInterface) || !($search_api instanceof SearchApi))
				continue;

			if (!$search_api->is_supported)
				continue;

			$loadedApis[$index_name] = array(
				'filename' => $file_info->getFilename(),
				'setting_index' => $index_name,
				'has_template' => in_array($index_name, array('custom', 'fulltext', 'standard')),
				'label' => $index_name && isset($txt['search_index_' . $index_name]) ? $txt['search_index_' . $index_name] : '',
				'desc' => $index_name && isset($txt['search_index_' . $index_name . '_desc']) ? $txt['search_index_' . $index_name . '_desc'] : '',
			);
		}

		call_integration_hook('integrate_load_search_apis', array(&$loadedApis));

		return $loadedApis;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\SearchApi::exportStatic'))
	SearchApi::exportStatic();

?>