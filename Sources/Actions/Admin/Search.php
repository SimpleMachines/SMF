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

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\Actions\BackwardCompatibility;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Search\SearchApi;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * The admin screen to change the search settings.
 */
class Search implements ActionInterface
{
	use ActionTrait;

	use BackwardCompatibility;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'weights';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'settings' => 'settings',
		'weights' => 'weights',
		'method' => 'method',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Edit some general settings related to the search function.
	 * Called by ?action=admin;area=managesearch;sa=settings.
	 * Requires the admin_forum permission.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		Utils::$context['page_title'] = Lang::$txt['search_settings_title'];
		Utils::$context['sub_template'] = 'show_settings';

		// A form was submitted.
		if (isset($_REQUEST['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_search_settings');

			if (empty($_POST['search_results_per_page'])) {
				$_POST['search_results_per_page'] = !empty(Config::$modSettings['search_results_per_page']) ? Config::$modSettings['search_results_per_page'] : Config::$modSettings['defaultMaxMessages'];
			}

			if (isset($_POST['search_stopwords_custom'])) {
				$_POST['search_stopwords_custom'] = array_diff(
					preg_split('/[\s,]+/u', $_POST['search_stopwords_custom']),
					explode(',', Lang::$txt['search_stopwords'] ?? ''),
					explode(',', Config::$modSettings['search_stopwords_parsed'] ?? ''),
				);

				sort($_POST['search_stopwords_custom']);

				$_POST['search_stopwords_custom'] = implode(',', array_map([Utils::class, 'htmlspecialchars'], $_POST['search_stopwords_custom']));
			}

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;

			Utils::redirectexit('action=admin;area=managesearch;sa=settings;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Prep the template!
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=managesearch;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['search_settings_title'];

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Edit the relative weight of the search factors.
	 * Called by ?action=admin;area=managesearch;sa=weights.
	 * Requires the admin_forum permission.
	 */
	public function weights(): void
	{
		Utils::$context['page_title'] = Lang::$txt['search_weights_title'];
		Utils::$context['sub_template'] = 'modify_weights';

		$factors = [
			'search_weight_frequency',
			'search_weight_age',
			'search_weight_length',
			'search_weight_subject',
			'search_weight_first_message',
			'search_weight_sticky',
		];

		IntegrationHook::call('integrate_modify_search_weights', [&$factors]);

		// A form was submitted.
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-msw');

			IntegrationHook::call('integrate_save_search_weights');

			$changes = [];

			foreach ($factors as $factor) {
				$changes[$factor] = (int) $_POST[$factor];
			}

			Config::updateModSettings($changes);
		}

		Utils::$context['relative_weights'] = ['total' => 0];

		foreach ($factors as $factor) {
			Utils::$context['relative_weights']['total'] += Config::$modSettings[$factor] ?? 0;
		}

		foreach ($factors as $factor) {
			Utils::$context['relative_weights'][$factor] = round(100 * (Config::$modSettings[$factor] ?? 0) / Utils::$context['relative_weights']['total'], 1);
		}

		SecurityToken::create('admin-msw');
	}

	/**
	 * Edit the search method and search index used.
	 * Calculates the size of the current search indexes in use.
	 * Allows to create and delete a fulltext index on the messages table.
	 * Allows to delete a custom index (that createmsgindex() created).
	 * Called by ?action=admin;area=managesearch;sa=method.
	 * Requires the admin_forum permission.
	 */
	public function method(): void
	{
		Utils::$context['page_title'] = Lang::$txt['search_method_title'];
		Utils::$context['sub_template'] = 'select_search_method';
		Utils::$context['supports_fulltext'] = Db::$db->search_support('fulltext');

		// Detect whether a fulltext index is set.
		if (Utils::$context['supports_fulltext']) {
			\SMF\Search\APIs\Fulltext::detectIndex();
		}

		// Saving?
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-msmpost');

			Config::updateModSettings([
				'search_index' => empty($_POST['search_index']) || (!isset(Utils::$context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
				'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
				'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
			]);

			Utils::redirectexit('action=admin;area=managesearch;sa=method');
		}

		Utils::$context['table_info'] = [
			'data_length' => 0,
			'index_length' => 0,
		];

		// Get some info about the messages table, to show its size and index size.
		if (Db::$db->title === POSTGRE_TITLE) {
			$request = Db::$db->query(
				'',
				'SELECT
					pg_table_size({string:tablename}) AS table_size,
					pg_indexes_size({string:tablename}) AS index_size',
				[
					'tablename' => Db::$db->prefix . 'messages',
				],
			);

			if ($request !== false && Db::$db->num_rows($request) > 0) {
				$row = Db::$db->fetch_assoc($request);
				Utils::$context['table_info']['data_length'] = (int) $row['table_size'];
				Utils::$context['table_info']['index_length'] = (int) $row['index_size'];
			}

			Db::$db->free_result($request);
		} else {
			if (preg_match('~^`(.+?)`\.(.+?)$~', Db::$db->prefix, $match) !== 0) {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					[
						'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
						'table_name' => str_replace('_', '\\_', $match[2]) . 'messages',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'messages',
					],
				);
			}

			if ($request !== false && Db::$db->num_rows($request) > 0) {
				$row = Db::$db->fetch_assoc($request);
				Utils::$context['table_info']['data_length'] = $row['Data_length'];
				Utils::$context['table_info']['index_length'] = $row['Index_length'];
			}

			Db::$db->free_result($request);
		}

		// Step through our APIs and get the size and status of each one's index.
		$existing_indexes = 0;

		foreach (Utils::$context['search_apis'] as $api) {
			Utils::$context['table_info'][$api['setting_index'] . ($api['setting_index'] === 'custom' ? '_index' : '') . '_length'] = $api['instance']->getSize();

			if ($api['instance']->getStatus() === 'exists') {
				++$existing_indexes;
			}
		}

		// Format the data and index length in kilobytes.
		foreach (Utils::$context['table_info'] as $type => $size) {
			// If it's not numeric then just break.  This database engine doesn't support size.
			if (!is_numeric($size)) {
				break;
			}

			Utils::$context['table_info'][$type] = Lang::getTxt('size_kilobyte', [Utils::$context['table_info'][$type] / 1024]);
		}

		Utils::$context['custom_index'] = !empty(Config::$modSettings['search_custom_index_config']);
		Utils::$context['partial_custom_index'] = !empty(Config::$modSettings['search_custom_index_resume']) && empty(Config::$modSettings['search_custom_index_config']);
		Utils::$context['double_index'] = $existing_indexes > 1;

		SecurityToken::create('admin-msmpost');
		SecurityToken::create('admin-msm', 'get');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the search area.
	 */
	public static function getConfigVars(): array
	{
		$permanent_stopwords = array_unique(array_merge(
			explode(',', Lang::$txt['search_stopwords'] ?? ''),
			explode(',', Config::$modSettings['search_stopwords_parsed'] ?? ''),
		));

		sort($permanent_stopwords);

		// What are we editing anyway?
		$config_vars = [
			// Permission...
			['permissions', 'search_posts'],
			// Some simple settings.
			['int', 'search_results_per_page'],
			['int', 'search_max_results', 'subtext' => Lang::$txt['search_max_results_disable']],
			'',

			// Some limitations.
			['int', 'search_floodcontrol_time', 'subtext' => Lang::$txt['search_floodcontrol_time_desc'], 6, 'postinput' => Lang::$txt['seconds']],
			'',

			// Allow the admin to set stopwords.
			['large_text', 'search_stopwords_custom', 'rows' => 8, 'subtext' => '<span class="infobox block">' . Lang::getTxt('search_stopwords_permanent', ['list' => implode(', ', $permanent_stopwords)]) . '</span>'],
		];

		// Do any mods want access?
		IntegrationHook::call('integrate_modify_search_settings', [&$config_vars]);

		// Perhaps the search method wants to add some settings?
		$searchAPI = SearchApi::load();

		if (is_callable([$searchAPI, 'searchSettings'])) {
			call_user_func_array([$searchAPI, 'searchSettings'], [&$config_vars]);
		}

		// Let the admin set custom stopwords.
		Config::$modSettings['search_stopwords_custom'] = implode("\n", explode(',', Config::$modSettings['search_stopwords_custom'] ?? ''));

		return $config_vars;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		User::$me->isAllowedTo('admin_forum');

		Lang::load('Search');
		Theme::loadTemplate('ManageSearch');

		// Create the tabs for the template.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['manage_search'],
			'help' => 'search',
			'description' => Lang::$txt['search_settings_desc'],
			'tabs' => [
				'weights' => [
					'description' => Lang::$txt['search_weights_desc'],
				],
				'method' => [
					'description' => Lang::$txt['search_method_desc'],
				],
				'settings' => [
					'description' => Lang::$txt['search_settings_desc'],
				],
			],
		];

		// Load any apis.
		Utils::$context['search_apis'] = SearchApi::detect();

		foreach (Utils::$context['search_apis'] as $api) {
			if (isset($api['class'])) {
				$class_vars = get_class_vars($api['class']);

				if (isset($class_vars['admin_subactions'])) {
					foreach ($class_vars['admin_subactions'] as $type => $subaction) {
						self::$subactions[$subaction['sa']] = $subaction['func'];
					}
				}
			}
		}

		IntegrationHook::call('integrate_manage_search', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		Utils::$context['sub_action'] = $this->subaction;
	}
}

?>