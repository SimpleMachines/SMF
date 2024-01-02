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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\Who;
use SMF\BackwardCompatibility;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Manages the settings related to search engines.
 */
class SearchEngines implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'SearchEngines',
			'consolidateSpiderStats' => 'consolidateSpiderStats',
			'list_getSpiders' => 'list_getSpiders',
			'list_getNumSpiders' => 'list_getNumSpiders',
			'list_getSpiderLogs' => 'list_getSpiderLogs',
			'list_getNumSpiderLogs' => 'list_getNumSpiderLogs',
			'list_getSpiderStats' => 'list_getSpiderStats',
			'list_getNumSpiderStats' => 'list_getNumSpiderStats',
			'recacheSpiderNames' => 'recacheSpiderNames',
			'spiderStats' => 'SpiderStats',
			'spiderLogs' => 'SpiderLogs',
			'viewSpiders' => 'ViewSpiders',
			'manageSearchEngineSettings' => 'ManageSearchEngineSettings',
			'editSpider' => 'EditSpider',
		],
	];

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
	public string $subaction = 'stats';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'stats' => 'stats',
		'logs' => 'logs',
		'spiders' => 'view',
		'settings' => 'settings',
		'editspiders' => 'edit',
	];

	/*********************
	 * Internal properties
	 *********************/

	// code...

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/**
	 * @var string
	 *
	 * JavaScript to use on the settings page.
	 */
	protected static string $javascript_function;

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
	 * Show the spider statistics.
	 */
	public function stats(): void
	{
		// Force an update of the stats every 60 seconds.
		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60) {
			self::consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we cleaning up some old stats?
		if (!empty($_POST['delete_entries']) && isset($_POST['older'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-ss');

			$deleteTime = time() - (((int) $_POST['older']) * 24 * 60 * 60);

			// Delete the entires.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_spider_stats
				WHERE last_seen < {int:delete_period}',
				[
					'delete_period' => $deleteTime,
				],
			);
		}

		// Get the earliest and latest dates.
		$request = Db::$db->query(
			'',
			'SELECT MIN(stat_date) AS first_date, MAX(stat_date) AS last_date
			FROM {db_prefix}log_spider_stats',
			[
			],
		);

		list($min_date, $max_date) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$min_year = (int) substr($min_date, 0, 4);
		$max_year = (int) substr($max_date, 0, 4);
		$min_month = (int) substr($min_date, 5, 2);
		$max_month = (int) substr($max_date, 5, 2);

		// Prepare the dates for the drop down.
		$date_choices = [];

		for ($y = $min_year; $y <= $max_year; $y++) {
			for ($m = 1; $m <= 12; $m++) {
				// This doesn't count?
				if ($y == $min_year && $m < $min_month) {
					continue;
				}

				if ($y == $max_year && $m > $max_month) {
					break;
				}

				$date_choices[$y . $m] = Lang::$txt['months_short'][$m] . ' ' . $y;
			}
		}

		// What are we currently viewing?
		$current_date = isset($_REQUEST['new_date']) && isset($date_choices[$_REQUEST['new_date']]) ? $_REQUEST['new_date'] : $max_date;

		// Prepare the HTML.
		if (!empty($date_choices)) {
			$date_select = '
			' . Lang::$txt['spider_stats_select_month'] . ':
			<select name="new_date" onchange="document.spider_stat_list.submit();">';

			foreach ($date_choices as $id => $text) {
				$date_select .= '
				<option value="' . $id . '"' . ($current_date == $id ? ' selected' : '') . '>' . $text . '</option>';
			}

			$date_select .= '
			</select>
			<noscript>
				<input type="submit" name="go" value="' . Lang::$txt['go'] . '" class="button">
			</noscript>';
		}

		// If we manually jumped to a date work out the offset.
		if (isset($_REQUEST['new_date'])) {
			$date_query = sprintf('%04d-%02d-01', substr($current_date, 0, 4), substr($current_date, 4));

			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}log_spider_stats
				WHERE stat_date < {date:date_being_viewed}',
				[
					'date_being_viewed' => $date_query,
				],
			);
			list($_REQUEST['start']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		$listOptions = [
			'id' => 'spider_stat_list',
			'title' => Lang::$txt['spider_stats'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=sengines;sa=stats',
			'default_sort_col' => 'stat_date',
			'get_items' => [
				'function' => __CLASS__ . '::list_getSpiderStats',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumSpiderStats',
			],
			'no_items_label' => Lang::$txt['spider_stats_no_entries'],
			'columns' => [
				'stat_date' => [
					'header' => [
						'value' => Lang::$txt['date'],
					],
					'data' => [
						'db' => 'stat_date',
					],
					'sort' => [
						'default' => 'stat_date',
						'reverse' => 'stat_date DESC',
					],
				],
				'name' => [
					'header' => [
						'value' => Lang::$txt['spider_name'],
					],
					'data' => [
						'db' => 'spider_name',
					],
					'sort' => [
						'default' => 's.spider_name',
						'reverse' => 's.spider_name DESC',
					],
				],
				'page_hits' => [
					'header' => [
						'value' => Lang::$txt['spider_stats_page_hits'],
					],
					'data' => [
						'db' => 'page_hits',
					],
					'sort' => [
						'default' => 'ss.page_hits',
						'reverse' => 'ss.page_hits DESC',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=sengines;sa=stats',
				'name' => 'spider_stat_list',
			],
			'additional_rows' => empty($date_select) ? [] : [
				[
					'position' => 'below_table_data',
					'value' => $date_select,
					'style' => 'text-align: right;',
				],
			],
		];

		SecurityToken::create('admin-ss');

		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_spider_stats';
		Utils::$context['default_list'] = 'spider_stat_list';
	}

	/**
	 * See what spiders have been up to.
	 */
	public function logs(): void
	{
		// Load the template and language just incase.
		Lang::load('Search');
		Theme::loadTemplate('ManageSearch');

		// Did they want to delete some entries?
		if ((!empty($_POST['delete_entries']) && isset($_POST['older'])) || !empty($_POST['removeAll'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-sl');

			if (!empty($_POST['delete_entries']) && isset($_POST['older'])) {
				$deleteTime = time() - (((int) $_POST['older']) * 24 * 60 * 60);

				// Delete the entires.
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}log_spider_hits
					WHERE log_time < {int:delete_period}',
					[
						'delete_period' => $deleteTime,
					],
				);
			} else {
				// Deleting all of them
				Db::$db->query(
					'',
					'TRUNCATE TABLE {db_prefix}log_spider_hits',
					[],
				);
			}
		}

		$listOptions = [
			'id' => 'spider_logs',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'title' => Lang::$txt['spider_logs'],
			'no_items_label' => Lang::$txt['spider_logs_empty'],
			'base_href' => Utils::$context['admin_area'] == 'sengines' ? Config::$scripturl . '?action=admin;area=sengines;sa=logs' : Config::$scripturl . '?action=admin;area=logs;sa=spiderlog',
			'default_sort_col' => 'log_time',
			'get_items' => [
				'function' => __CLASS__ . '::list_getSpiderLogs',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumSpiderLogs',
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['spider'],
					],
					'data' => [
						'db' => 'spider_name',
					],
					'sort' => [
						'default' => 's.spider_name',
						'reverse' => 's.spider_name DESC',
					],
				],
				'log_time' => [
					'header' => [
						'value' => Lang::$txt['spider_time'],
					],
					'data' => [
						'function' => function ($rowData) {
							return Time::create('@' . $rowData['log_time'])->format();
						},
					],
					'sort' => [
						'default' => 'sl.id_hit DESC',
						'reverse' => 'sl.id_hit',
					],
				],
				'viewing' => [
					'header' => [
						'value' => Lang::$txt['spider_viewing'],
					],
					'data' => [
						'db' => 'url',
					],
				],
			],
			'form' => [
				'token' => 'admin-sl',
				'href' => Config::$scripturl . '?action=admin;area=sengines;sa=logs',
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => Lang::$txt['spider_logs_info'],
				],
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="removeAll" value="' . Lang::$txt['spider_log_empty_log'] . '" data-confirm="' . Lang::$txt['spider_log_empty_log_confirm'] . '" class="button you_sure">',
				],
			],
		];

		SecurityToken::create('admin-sl');

		new ItemList($listOptions);

		// Now determine the actions of the URLs.
		if (!empty(Utils::$context['spider_logs']['rows'])) {
			$urls = [];

			// Grab the current /url.
			foreach (Utils::$context['spider_logs']['rows'] as $k => $row) {
				// Feature disabled?
				if (empty($row['data']['viewing']['value']) && isset(Config::$modSettings['spider_mode']) && Config::$modSettings['spider_mode'] < 3) {
					Utils::$context['spider_logs']['rows'][$k]['viewing']['value'] = '<em>' . Lang::$txt['spider_disabled'] . '</em>';
				} else {
					$urls[$k] = [$row['data']['viewing']['value'], -1];
				}
			}

			// Now stick in the new URLs.
			$urls = Who::determineActions($urls, 'whospider_');

			foreach ($urls as $k => $new_url) {
				if (is_array($new_url)) {
					Utils::$context['spider_logs']['rows'][$k]['data']['viewing']['value'] = Lang::$txt[$new_url['label']];

					Utils::$context['spider_logs']['rows'][$k]['data']['viewing']['class'] = $new_url['class'];
				} else {
					Utils::$context['spider_logs']['rows'][$k]['data']['viewing']['value'] = $new_url;
				}
			}
		}

		Utils::$context['page_title'] = Lang::$txt['spider_logs'];
		Utils::$context['sub_template'] = 'show_spider_logs';
		Utils::$context['default_list'] = 'spider_logs';
	}

	/**
	 * View a list of all the spiders we know about.
	 */
	public function view(): void
	{
		if (!isset($_SESSION['spider_stat']) || $_SESSION['spider_stat'] < time() - 60) {
			self::consolidateSpiderStats();
			$_SESSION['spider_stat'] = time();
		}

		// Are we adding a new one?
		if (!empty($_POST['addSpider'])) {
			self::edit();

			return;
		}

		// User pressed the 'remove selection button'.
		if (!empty($_POST['removeSpiders']) && !empty($_POST['remove']) && is_array($_POST['remove'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-ser');

			// Make sure every entry is a proper integer.
			foreach ($_POST['remove'] as $index => $spider_id) {
				$_POST['remove'][(int) $index] = (int) $spider_id;
			}

			// Delete them all!
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}spiders
				WHERE id_spider IN ({array_int:remove_list})',
				[
					'remove_list' => $_POST['remove'],
				],
			);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_spider_hits
				WHERE id_spider IN ({array_int:remove_list})',
				[
					'remove_list' => $_POST['remove'],
				],
			);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_spider_stats
				WHERE id_spider IN ({array_int:remove_list})',
				[
					'remove_list' => $_POST['remove'],
				],
			);

			CacheApi::put('spider_search', null, 300);
			self::recacheSpiderNames();
		}

		// Get the last seens.
		Utils::$context['spider_last_seen'] = [];

		$request = Db::$db->query(
			'',
			'SELECT id_spider, MAX(last_seen) AS last_seen_time
			FROM {db_prefix}log_spider_stats
			GROUP BY id_spider',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['spider_last_seen'][$row['id_spider']] = $row['last_seen_time'];
		}
		Db::$db->free_result($request);

		SecurityToken::create('admin-ser');

		$listOptions = [
			'id' => 'spider_list',
			'title' => Lang::$txt['spiders'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=sengines;sa=spiders',
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => __CLASS__ . '::list_getSpiders',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumSpiders',
			],
			'no_items_label' => Lang::$txt['spiders_no_entries'],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['spider_name'],
					],
					'data' => [
						'function' => function ($rowData) {
							return sprintf('<a href="%1$s?action=admin;area=sengines;sa=editspiders;sid=%2$d">%3$s</a>', Config::$scripturl, $rowData['id_spider'], Utils::htmlspecialchars($rowData['spider_name']));
						},
					],
					'sort' => [
						'default' => 'spider_name DESC',
						'reverse' => 'spider_name',
					],
				],
				'last_seen' => [
					'header' => [
						'value' => Lang::$txt['spider_last_seen'],
					],
					'data' => [
						'function' => function ($rowData) {
							return isset(Utils::$context['spider_last_seen'][$rowData['id_spider']]) ? Time::create('@' . Utils::$context['spider_last_seen'][$rowData['id_spider']])->format() : Lang::$txt['spider_last_never'];
						},
					],
				],
				'user_agent' => [
					'header' => [
						'value' => Lang::$txt['spider_agent'],
					],
					'data' => [
						'db_htmlsafe' => 'user_agent',
					],
					'sort' => [
						'default' => 'user_agent',
						'reverse' => 'user_agent DESC',
					],
				],
				'ip_info' => [
					'header' => [
						'value' => Lang::$txt['spider_ip_info'],
					],
					'data' => [
						'db_htmlsafe' => 'ip_info',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'ip_info',
						'reverse' => 'ip_info DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
							'params' => [
								'id_spider' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=sengines;sa=spiders',
				'token' => 'admin-ser',
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '
						<input type="submit" name="removeSpiders" value="' . Lang::$txt['spiders_remove_selected'] . '" data-confirm="' . Lang::$txt['spider_remove_selected_confirm'] . '" class="button you_sure">
						<input type="submit" name="addSpider" value="' . Lang::$txt['spiders_add'] . '" class="button">
					',
				],
			],
		];

		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'spider_list';
	}

	/**
	 * The settings page.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Set up a message.
		Utils::$context['settings_message'] = sprintf(Lang::$txt['spider_settings_desc'], Config::$scripturl . '?action=admin;area=logs;sa=settings;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);

		// We need to load the groups for the spider group thingy.
		$request = Db::$db->query(
			'',
			'SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_group != {int:admin_group}
				AND id_group != {int:moderator_group}',
			[
				'admin_group' => 1,
				'moderator_group' => 3,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$config_vars['spider_group'][2][$row['id_group']] = $row['group_name'];
		}
		Db::$db->free_result($request);

		// Make sure it's valid - note that regular members are given id_group = 1 which is reversed in SMF\User - no admins here!
		if (isset($_POST['spider_group']) && !isset($config_vars['spider_group'][2][$_POST['spider_group']])) {
			$_POST['spider_group'] = 0;
		}

		// Setup the template.
		Utils::$context['page_title'] = Lang::$txt['settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Are we saving them - are we??
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_search_engine_settings');

			ACP::saveDBSettings($config_vars);

			self::recacheSpiderNames();

			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=sengines;sa=settings');
		}

		// Final settings...
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=sengines;save;sa=settings';

		Utils::$context['settings_title'] = Lang::$txt['settings'];

		Theme::addInlineJavaScript(self::$javascript_function, true);

		// Prepare the settings...
		ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Here we can add, and edit, spider info!
	 */
	public function edit(): void
	{
		// Some standard stuff.
		Utils::$context['id_spider'] = !empty($_GET['sid']) ? (int) $_GET['sid'] : 0;

		Utils::$context['page_title'] = Utils::$context['id_spider'] ? Lang::$txt['spiders_edit'] : Lang::$txt['spiders_add'];
		Utils::$context['sub_template'] = 'spider_edit';

		// Select the 'Spiders' tab.
		Menu::$loaded['admin']->current_subsection = 'spiders';

		// Are we saving?
		if (!empty($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-ses');

			foreach (['spider_name', 'spider_agent'] as $key) {
				$_POST[$key] = trim(Utils::normalize($_POST[$key]));
			}

			// Check the IP range is valid.
			$ips = [];

			foreach (explode(',', $_POST['spider_ip']) as $set) {
				$test = IP::ip2range(trim($set));

				if (!empty($test)) {
					$ips[] = $set;
				}
			}

			$ips = implode(',', $ips);

			// Goes in as it is...
			if (Utils::$context['id_spider']) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}spiders
					SET spider_name = {string:spider_name}, user_agent = {string:spider_agent},
						ip_info = {string:ip_info}
					WHERE id_spider = {int:current_spider}',
					[
						'current_spider' => Utils::$context['id_spider'],
						'spider_name' => $_POST['spider_name'],
						'spider_agent' => $_POST['spider_agent'],
						'ip_info' => $ips,
					],
				);
			} else {
				Db::$db->insert(
					'insert',
					'{db_prefix}spiders',
					[
						'spider_name' => 'string', 'user_agent' => 'string', 'ip_info' => 'string',
					],
					[
						$_POST['spider_name'], $_POST['spider_agent'], $ips,
					],
					['id_spider'],
				);
			}

			CacheApi::put('spider_search', null);
			self::recacheSpiderNames();

			Utils::redirectexit('action=admin;area=sengines;sa=spiders');
		}

		// The default is new.
		Utils::$context['spider'] = [
			'id' => 0,
			'name' => '',
			'agent' => '',
			'ip_info' => '',
		];

		// An edit?
		if (Utils::$context['id_spider']) {
			$request = Db::$db->query(
				'',
				'SELECT id_spider, spider_name, user_agent, ip_info
				FROM {db_prefix}spiders
				WHERE id_spider = {int:current_spider}',
				[
					'current_spider' => Utils::$context['id_spider'],
				],
			);

			if ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['spider'] = [
					'id' => $row['id_spider'],
					'name' => $row['spider_name'],
					'agent' => $row['user_agent'],
					'ip_info' => $row['ip_info'],
				];
			}
			Db::$db->free_result($request);
		}

		SecurityToken::create('admin-ses');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
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
	 * @return array $config_vars for the news area.
	 */
	public static function getConfigVars(): array
	{
		$config_vars = [
			// How much detail?
			['select', 'spider_mode', 'subtext' => Lang::$txt['spider_mode_note'], [Lang::$txt['spider_mode_off'], Lang::$txt['spider_mode_standard'], Lang::$txt['spider_mode_high'], Lang::$txt['spider_mode_vhigh']], 'onchange' => 'disableFields();'],
			'spider_group' => ['select', 'spider_group', 'subtext' => Lang::$txt['spider_group_note'], [Lang::$txt['spider_group_none'], Lang::$txt['membergroups_members']]],
			['select', 'show_spider_online', [Lang::$txt['show_spider_online_no'], Lang::$txt['show_spider_online_summary'], Lang::$txt['show_spider_online_detail'], Lang::$txt['show_spider_online_detail_admin']]],
		];

		// Do some javascript.
		self::$javascript_function = '
			function disableFields()
			{
				disabledState = document.getElementById(\'spider_mode\').value == 0;';

		foreach ($config_vars as $variable) {
			if ($variable[1] != 'spider_mode') {
				self::$javascript_function .= '
				if (document.getElementById(\'' . $variable[1] . '\'))
					document.getElementById(\'' . $variable[1] . '\').disabled = disabledState;';
			}
		}

		self::$javascript_function .= '
			}
			disableFields();';

		IntegrationHook::call('integrate_modify_search_engine_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * This function takes any unprocessed hits and turns them into stats.
	 */
	public static function consolidateSpiderStats(): void
	{
		$spider_hits = [];

		$request = Db::$db->query(
			'',
			'SELECT id_spider, MAX(log_time) AS last_seen, COUNT(*) AS num_hits
			FROM {db_prefix}log_spider_hits
			WHERE processed = {int:not_processed}
			GROUP BY id_spider, MONTH(log_time), DAYOFMONTH(log_time)',
			[
				'not_processed' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$spider_hits[] = $row;
		}
		Db::$db->free_result($request);

		if (empty($spider_hits)) {
			return;
		}

		// Attempt to update the master data.
		$stat_inserts = [];

		foreach ($spider_hits as $stat) {
			// We assume the max date is within the right day.
			$date = Time::strftime('%Y-%m-%d', $stat['last_seen']);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_spider_stats
				SET page_hits = page_hits + {int:hits},
					last_seen = CASE WHEN last_seen > {int:last_seen} THEN last_seen ELSE {int:last_seen} END
				WHERE id_spider = {int:current_spider}
					AND stat_date = {date:last_seen_date}',
				[
					'last_seen_date' => $date,
					'last_seen' => $stat['last_seen'],
					'current_spider' => $stat['id_spider'],
					'hits' => $stat['num_hits'],
				],
			);

			if (Db::$db->affected_rows() == 0) {
				$stat_inserts[] = [$date, $stat['id_spider'], $stat['num_hits'], $stat['last_seen']];
			}
		}

		// New stats?
		if (!empty($stat_inserts)) {
			Db::$db->insert(
				'ignore',
				'{db_prefix}log_spider_stats',
				['stat_date' => 'date', 'id_spider' => 'int', 'page_hits' => 'int', 'last_seen' => 'int'],
				$stat_inserts,
				['stat_date', 'id_spider'],
			);
		}

		// All processed.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}log_spider_hits
			SET processed = {int:is_processed}
			WHERE processed = {int:not_processed}',
			[
				'is_processed' => 1,
				'not_processed' => 0,
			],
		);
	}

	/**
	 * Callback function for SMF\ItemList()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of information about known spiders
	 */
	public static function list_getSpiders($start, $items_per_page, $sort): array
	{
		$spiders = [];

		$request = Db::$db->query(
			'',
			'SELECT id_spider, spider_name, user_agent, ip_info
			FROM {db_prefix}spiders
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items}',
			[
				'sort' => $sort,
				'start' => $start,
				'items' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$spiders[$row['id_spider']] = $row;
		}
		Db::$db->free_result($request);

		return $spiders;
	}

	/**
	 * Callback function for SMF\ItemList()
	 *
	 * @return int The number of known spiders
	 */
	public static function list_getNumSpiders(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*) AS num_spiders
			FROM {db_prefix}spiders',
			[
			],
		);
		list($numSpiders) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $numSpiders;
	}

	/**
	 * Callback function for SMF\ItemList()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of spider log data
	 */
	public static function list_getSpiderLogs($start, $items_per_page, $sort): array
	{
		$spider_logs = [];

		$request = Db::$db->query(
			'',
			'SELECT sl.id_spider, sl.url, sl.log_time, s.spider_name
			FROM {db_prefix}log_spider_hits AS sl
				INNER JOIN {db_prefix}spiders AS s ON (s.id_spider = sl.id_spider)
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items}',
			[
				'sort' => $sort,
				'start' => $start,
				'items' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$spider_logs[] = $row;
		}
		Db::$db->free_result($request);

		return $spider_logs;
	}

	/**
	 * Callback function for SMF\ItemList()
	 *
	 * @return int The number of spider log entries
	 */
	public static function list_getNumSpiderLogs(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*) AS num_logs
			FROM {db_prefix}log_spider_hits',
			[
			],
		);
		list($numLogs) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $numLogs;
	}

	/**
	 * Callback function for SMF\ItemList()
	 * Get a list of spider stats from the log_spider table
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of spider statistics info
	 */
	public static function list_getSpiderStats($start, $items_per_page, $sort): array
	{
		$spider_stats = [];

		$request = Db::$db->query(
			'',
			'SELECT ss.id_spider, ss.stat_date, ss.page_hits, s.spider_name
			FROM {db_prefix}log_spider_stats AS ss
				INNER JOIN {db_prefix}spiders AS s ON (s.id_spider = ss.id_spider)
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items}',
			[
				'sort' => $sort,
				'start' => $start,
				'items' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$spider_stats[] = $row;
		}
		Db::$db->free_result($request);

		return $spider_stats;
	}

	/**
	 * Callback function for SMF\ItemList()
	 * Get the number of spider stat rows from the log spider stats table
	 *
	 * @return int The number of rows in the log_spider_stats table
	 */
	public static function list_getNumSpiderStats(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*) AS num_stats
			FROM {db_prefix}log_spider_stats',
			[
			],
		);
		list($numStats) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $numStats;
	}

	/**
	 * Recache spider names?
	 */
	public static function recacheSpiderNames(): void
	{
		$spiders = [];

		$request = Db::$db->query(
			'',
			'SELECT id_spider, spider_name
			FROM {db_prefix}spiders',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$spiders[$row['id_spider']] = $row['spider_name'];
		}
		Db::$db->free_result($request);

		Config::updateModSettings(['spider_name_cache' => Utils::jsonEncode($spiders)]);
	}

	/**
	 * Backward compatibility wrapper for the stats sub-action.
	 */
	public static function spiderStats(): void
	{
		self::load();
		self::$obj->subaction = 'stats';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the logs sub-action.
	 */
	public static function spiderLogs(): void
	{
		self::load();
		self::$obj->subaction = 'logs';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the spiders sub-action.
	 */
	public static function viewSpiders(): void
	{
		self::load();
		self::$obj->subaction = 'spiders';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function manageSearchEngineSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = 'settings';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the editspiders sub-action.
	 */
	public static function editSpider(): void
	{
		self::load();
		self::$obj->subaction = 'editspiders';
		self::$obj->execute();
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

		if (empty(Config::$modSettings['spider_mode'])) {
			self::$subactions = array_intersect_key(self::$subactions, ['settings' => true]);
			$this->subaction = 'settings';
		}

		Utils::$context['page_title'] = Lang::$txt['search_engines'];

		// Tab data might already be set if this was called from Logs::execute().
		if (empty(Menu::$loaded['admin']->tab_data)) {
			// Some more tab data.
			Menu::$loaded['admin']->tab_data = [
				'title' => Lang::$txt['search_engines'],
				'description' => Lang::$txt['search_engines_description'],
			];
		}

		IntegrationHook::call('integrate_manage_search_engines', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		Utils::$context['sub_action'] = &$this->subaction;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\SearchEngines::exportStatic')) {
	SearchEngines::exportStatic();
}

?>