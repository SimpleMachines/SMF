<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Suggests members, membergroups, or SMF versions in reply to AJAX requests.
 */
class AutoSuggest implements ActionInterface
{
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested type of suggestion.
	 * This should be set by the constructor.
	 */
	public string $suggest_type;

	/**
	 * @var string
	 *
	 * The string to search for when finding suggestions.
	 * This should be set by the constructor.
	 */
	public string $search;

	/**
	 * @var array
	 *
	 * Optional search parameters.
	 * This should be set by the constructor.
	 */
	public array $search_param = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available suggestion types.
	 */
	public static array $suggest_types = [
		'member' => 'member',
		'membergroups' => 'membergroups',
		'versions' => 'versions',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever method is necessary.
	 */
	public function execute(): void
	{
		if (!isset($this->suggest_type, $this->search, self::$suggest_types[$this->suggest_type])) {
			return;
		}

		User::$me->checkSession('get');

		Theme::loadTemplate('Xml');
		Utils::$context['sub_template'] = 'generic_xml';

		if (method_exists($this, self::$suggest_types[$this->suggest_type])) {
			Utils::$context['xml_data'] = call_user_func([$this, self::$suggest_types[$this->suggest_type]]);
		} elseif (function_exists('AutoSuggest_Search_' . self::$suggest_types[$this->suggest_type])) {
			Utils::$context['xml_data'] = call_user_func('AutoSuggest_Search_' . self::$suggest_types[$this->suggest_type]);
		} elseif (function_exists('AutoSuggest_Search_' . $this->suggest_type)) {
			Utils::$context['xml_data'] = call_user_func('AutoSuggest_Search_' . $this->suggest_type);
		}
	}

	/**
	 * Search for a member by real_name.
	 *
	 * @return array An array of information for displaying the suggestions.
	 */
	public function member(): array
	{
		$this->sanitizeSearch();

		$xml_data = [
			'items' => [
				'identifier' => 'item',
				'children' => [],
			],
		];

		// Find the member.
		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE {raw:real_name} LIKE {string:search}' . (!empty($this->search_param['buddies']) ? '
				AND id_member IN ({array_int:buddy_list})' : '') . '
				AND is_activated IN (1, 11)
			LIMIT ' . (Utils::entityStrlen($this->search) <= 2 ? '100' : '800'),
			[
				'real_name' => Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name',
				'buddy_list' => User::$me->buddies,
				'search' => $this->search,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['real_name'] = strtr($row['real_name'], ['&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;']);

			$xml_data['items']['children'][] = [
				'attributes' => [
					'id' => $row['id_member'],
				],
				'value' => $row['real_name'],
			];
		}
		Db::$db->free_result($request);

		return $xml_data;
	}

	/**
	 * Search for a membergroup by name.
	 *
	 * @return array An array of information for displaying the suggestions.
	 */
	public function membergroups(): array
	{
		$this->sanitizeSearch();

		$xml_data = [
			'items' => [
				'identifier' => 'item',
				'children' => [],
			],
		];

		// Find the group.
		// Only return groups which are not post-based and not "Hidden",
		// but not the "Administrators" or "Moderators" groups.
		$request = Db::$db->query(
			'',
			'SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE {raw:group_name} LIKE {string:search}
				AND min_posts = {int:min_posts}
				AND id_group NOT IN ({array_int:invalid_groups})
				AND hidden != {int:hidden}',
			[
				'group_name' => Db::$db->case_sensitive ? 'LOWER(group_name)' : 'group_name',
				'min_posts' => -1,
				'invalid_groups' => [1, 3],
				'hidden' => 2,
				'search' => $this->search,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['group_name'] = strtr($row['group_name'], ['&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;']);

			$xml_data['items']['children'][] = [
				'attributes' => [
					'id' => $row['id_group'],
				],
				'value' => $row['group_name'],
			];
		}
		Db::$db->free_result($request);

		return $xml_data;
	}

	/**
	 * Provides a list of possible SMF versions to use in emulation.
	 *
	 * @return array An array of data for displaying the suggestions.
	 */
	public function versions(): array
	{
		$xml_data = [
			'items' => [
				'identifier' => 'item',
				'children' => [],
			],
		];

		// First try to get it from the database.
		$versions = [];
		$request = Db::$db->query(
			'',
			'SELECT data
			FROM {db_prefix}admin_info_files
			WHERE filename = {string:latest_versions}
				AND path = {string:path}',
			[
				'latest_versions' => 'latest-versions.txt',
				'path' => '/smf/',
			],
		);

		if (Db::$db->num_rows($request)) {
			$versions = [];
		}

		$row = Db::$db->fetch_assoc($request);

		if (!empty($row['data'])) {
			// The file can have either Windows or Linux line endings, but let's
			// ensure we clean it as best we can.
			$possible_versions = explode("\n", $row['data']);

			foreach ($possible_versions as $ver) {
				$ver = trim($ver);

				if (strpos($ver, 'SMF') === 0) {
					$versions[] = $ver;
				}
			}
		}

		Db::$db->free_result($request);

		// Just in case we don't have anything.
		if (empty($versions)) {
			$versions = [SMF_FULL_VERSION];
		}

		foreach ($versions as $id => $version) {
			if (strpos(strtoupper($version), strtoupper($this->search)) !== false) {
				$xml_data['items']['children'][] = [
					'attributes' => [
						'id' => $id,
					],
					'value' => $version,
				];
			}
		}

		return $xml_data;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Checks whether the given suggestion type is supported.
	 *
	 * @param string $suggest_type The suggestion type we are interested in.
	 */
	public static function checkRegistered(string $suggest_type): bool
	{
		IntegrationHook::call('integrate_autosuggest', [&self::$suggest_types]);

		return isset(self::$suggest_types[$suggest_type]) && (method_exists(__CLASS__, $suggest_type) || function_exists('AutoSuggest_Search_' . self::$suggest_types[$suggest_type]) || function_exists('AutoSuggest_Search_' . $suggest_type));
	}

	/**
	 * Backward compatibility provider
	 * @param null|string $suggest_type
	 * @param bool $callHandler
	 */
	public static function backCompatProvider(?string $suggest_type = null): void
	{
		$obj = self::load();
		$obj->suggest_type = $suggest_type;
		$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		IntegrationHook::call('integrate_autosuggest', [&self::$suggest_types]);

		if (!empty($_REQUEST['suggest_type']) && isset(self::$suggest_types[$_REQUEST['suggest_type']])) {
			$this->suggest_type = $_REQUEST['suggest_type'];
		}

		// Any parameters?
		if (isset($_REQUEST['search_param'])) {
			$this->search_param = Utils::jsonDecode(base64_decode($_REQUEST['search_param']), true);
		}

		if (isset($_REQUEST['search'])) {
			$this->search = $_REQUEST['search'];
		}
	}

	/**
	 * Sanitizes the search string.
	 */
	protected function sanitizeSearch(): void
	{
		$this->search = trim(Utils::strtolower($this->search)) . '*';

		$this->search = strtr($this->search, ['%' => '\\%', '_' => '\\_', '*' => '%', '?' => '_', '&#038;' => '&amp;']);
	}
}

?>