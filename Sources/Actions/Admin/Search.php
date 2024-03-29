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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\BackwardCompatibility;
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
	use BackwardCompatibility;

	/*****************
	 * Class constants
	 *****************/

	// code...

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

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var self
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent multiple instantiations.
	 */
	protected static Search $obj;

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
				'search_index' => empty($_POST['search_index']) || (!in_array($_POST['search_index'], ['fulltext', 'custom']) && !isset(Utils::$context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
				'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
				'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
			]);

			Utils::redirectexit('action=admin;area=managesearch;sa=method');
		}

		Utils::$context['table_info'] = [
			'data_length' => 0,
			'index_length' => 0,
			'fulltext_length' => 0,
			'custom_index_length' => 0,
		];

		// Get some info about the messages table, to show its size and index size.
		if (Config::$db_type == 'mysql') {
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

			if ($request !== false && Db::$db->num_rows($request) == 1) {
				// Only do this if the user has permission to execute this query.
				$row = Db::$db->fetch_assoc($request);
				Utils::$context['table_info']['data_length'] = $row['Data_length'];
				Utils::$context['table_info']['index_length'] = $row['Index_length'];
				Utils::$context['table_info']['fulltext_length'] = $row['Index_length'];
				Db::$db->free_result($request);
			}

			// Now check the custom index table, if it exists at all.
			if (preg_match('~^`(.+?)`\.(.+?)$~', Db::$db->prefix, $match) !== 0) {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					[
						'database_name' => '`' . strtr($match[1], ['`' => '']) . '`',
						'table_name' => str_replace('_', '\\_', $match[2]) . 'log_search_words',
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW TABLE STATUS
					LIKE {string:table_name}',
					[
						'table_name' => str_replace('_', '\\_', Db::$db->prefix) . 'log_search_words',
					],
				);
			}

			if ($request !== false && Db::$db->num_rows($request) == 1) {
				// Only do this if the user has permission to execute this query.
				$row = Db::$db->fetch_assoc($request);
				Utils::$context['table_info']['index_length'] += $row['Data_length'] + $row['Index_length'];
				Utils::$context['table_info']['custom_index_length'] = $row['Data_length'] + $row['Index_length'];
				Db::$db->free_result($request);
			}
		} elseif (Config::$db_type == 'postgresql') {
			// In order to report the sizes correctly we need to perform vacuum (optimize) on the tables we will be using.
			// $temp_tables = Db::$db->list_tables();
			// foreach ($temp_tables as $table)
			//	if ($table == Db::$db->prefix. 'messages' || $table == Db::$db->prefix. 'log_search_words')
			//		Db::$db->optimize_table($table);

			// PostGreSql has some hidden sizes.
			$request = Db::$db->query(
				'',
				'SELECT
					indexname,
					pg_relation_size(quote_ident(t.tablename)::text) AS table_size,
					pg_relation_size(quote_ident(indexrelname)::text) AS index_size
				FROM pg_tables t
					LEFT OUTER JOIN pg_class c ON t.tablename=c.relname
					LEFT OUTER JOIN
						(SELECT c.relname AS ctablename, ipg.relname AS indexname, indexrelname FROM pg_index x
							JOIN pg_class c ON c.oid = x.indrelid
							JOIN pg_class ipg ON ipg.oid = x.indexrelid
							JOIN pg_stat_all_indexes psai ON x.indexrelid = psai.indexrelid)
						AS foo
						ON t.tablename = foo.ctablename
				WHERE t.schemaname= {string:schema} and (
					indexname = {string:messages_ftx} OR indexname = {string:log_search_words} )',
				[
					'messages_ftx' => Db::$db->prefix . 'messages_ftx',
					'log_search_words' => Db::$db->prefix . 'log_search_words',
					'schema' => 'public',
				],
			);

			if ($request !== false && Db::$db->num_rows($request) > 0) {
				while ($row = Db::$db->fetch_assoc($request)) {
					if ($row['indexname'] == Db::$db->prefix . 'messages_ftx') {
						Utils::$context['table_info']['data_length'] = (int) $row['table_size'];
						Utils::$context['table_info']['index_length'] = (int) $row['index_size'];
						Utils::$context['table_info']['fulltext_length'] = (int) $row['index_size'];
					} elseif ($row['indexname'] == Db::$db->prefix . 'log_search_words') {
						Utils::$context['table_info']['index_length'] = (int) $row['index_size'];
						Utils::$context['table_info']['custom_index_length'] = (int) $row['index_size'];
					}
				}
				Db::$db->free_result($request);
			} else {
				// Didn't work for some reason...
				Utils::$context['table_info'] = [
					'data_length' => Lang::$txt['not_applicable'],
					'index_length' => Lang::$txt['not_applicable'],
					'fulltext_length' => Lang::$txt['not_applicable'],
					'custom_index_length' => Lang::$txt['not_applicable'],
				];
			}
		} else {
			Utils::$context['table_info'] = [
				'data_length' => Lang::$txt['not_applicable'],
				'index_length' => Lang::$txt['not_applicable'],
				'fulltext_length' => Lang::$txt['not_applicable'],
				'custom_index_length' => Lang::$txt['not_applicable'],
			];
		}

		// Format the data and index length in kilobytes.
		foreach (Utils::$context['table_info'] as $type => $size) {
			// If it's not numeric then just break.  This database engine doesn't support size.
			if (!is_numeric($size)) {
				break;
			}

			Utils::$context['table_info'][$type] = Lang::numberFormat(Utils::$context['table_info'][$type] / 1024) . ' ' . Lang::$txt['search_method_kilobytes'];
		}

		Utils::$context['custom_index'] = !empty(Config::$modSettings['search_custom_index_config']);
		Utils::$context['partial_custom_index'] = !empty(Config::$modSettings['search_custom_index_resume']) && empty(Config::$modSettings['search_custom_index_config']);
		Utils::$context['double_index'] = !empty(Utils::$context['fulltext_index']) && Utils::$context['custom_index'];

		SecurityToken::create('admin-msmpost');
		SecurityToken::create('admin-msm', 'get');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return self An instance of this class.
	 */
	public static function load(): self
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the search area.
	 */
	public static function getConfigVars(): array
	{
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
		];

		IntegrationHook::call('integrate_modify_search_settings', [&$config_vars]);

		// Perhaps the search method wants to add some settings?
		$searchAPI = SearchApi::load();

		if (is_callable([$searchAPI, 'searchSettings'])) {
			call_user_func_array([$searchAPI, 'searchSettings'], [&$config_vars]);
		}

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
					self::$subactions = array_merge(self::$subactions, $class_vars['admin_subactions']);
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