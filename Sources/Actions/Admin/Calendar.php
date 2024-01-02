<?php

/**
 * This file allows you to manage the calendar.
 *
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
use SMF\Actions\Calendar as Cal;
use SMF\BackwardCompatibility;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class allows you to manage the calendar.
 */
class Calendar implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageCalendar',
			'modifyHolidays' => 'ModifyHolidays',
			'editHoliday' => 'EditHoliday',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'holidays';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'holidays' => 'holidays',
		'editholiday' => 'edit',
		'settings' => 'settings',
	];

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

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		User::$me->isAllowedTo('admin_forum');

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * The method that handles adding and deleting holiday data.
	 */
	public function holidays(): void
	{
		// Submitting something...
		if (isset($_REQUEST['delete']) && !empty($_REQUEST['holiday'])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-mc');

			foreach ($_REQUEST['holiday'] as $id => $value) {
				$_REQUEST['holiday'][$id] = (int) $id;
			}

			Cal::removeHolidays($_REQUEST['holiday']);
		}

		SecurityToken::create('admin-mc');
		$listOptions = [
			'id' => 'holiday_list',
			'title' => Lang::$txt['current_holidays'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=managecalendar;sa=holidays',
			'default_sort_col' => 'name',
			'get_items' => [
				'file' => Config::$sourcedir . '/Actions/Calendar.php',
				'function' => 'SMF\\Actions\\Calendar::list_getHolidays',
			],
			'get_count' => [
				'file' => Config::$sourcedir . '/Actions/Calendar.php',
				'function' => 'SMF\\Actions\\Calendar::list_getNumHolidays',
			],
			'no_items_label' => Lang::$txt['holidays_no_entries'],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['holidays_title'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=managecalendar;sa=editholiday;holiday=%1$d">%2$s</a>',
							'params' => [
								'id_holiday' => false,
								'title' => false,
							],
						],
					],
					'sort' => [
						'default' => 'title ASC, event_date ASC',
						'reverse' => 'title DESC, event_date ASC',
					],
				],
				'date' => [
					'header' => [
						'value' => Lang::$txt['date'],
					],
					'data' => [
						'function' => function ($rowData) {
							// Recurring every year or just a single year?
							$year = $rowData['year'] == '1004' ? sprintf('(%1$s)', Lang::$txt['every_year']) : $rowData['year'];

							// Construct the date.
							return sprintf('%1$d %2$s %3$s', $rowData['day'], Lang::$txt['months'][(int) $rowData['month']], $year);
						},
					],
					'sort' => [
						'default' => 'event_date',
						'reverse' => 'event_date DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="holiday[%1$d]">',
							'params' => [
								'id_holiday' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=managecalendar;sa=holidays',
				'token' => 'admin-mc',
			],
			'additional_rows' => [
				[
					'position' => 'above_column_headers',
					'value' => '<input type="submit" name="delete" value="' . Lang::$txt['quickmod_delete_selected'] . '" class="button">
						<a class="button" href="' . Config::$scripturl . '?action=admin;area=managecalendar;sa=editholiday">' . Lang::$txt['holidays_add'] . '</a>',
				],
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="delete" value="' . Lang::$txt['quickmod_delete_selected'] . '" class="button">
						<a class="button" href="' . Config::$scripturl . '?action=admin;area=managecalendar;sa=editholiday">' . Lang::$txt['holidays_add'] . '</a>',
				],
			],
		];

		new ItemList($listOptions);

		// loadTemplate('ManageCalendar');
		Utils::$context['page_title'] = Lang::$txt['manage_holidays'];

		// Since the list is the only thing to show, use the default list template.
		Utils::$context['default_list'] = 'holiday_list';
		Utils::$context['sub_template'] = 'show_list';
	}

	/**
	 * This method is used for adding/editing a specific holiday.
	 */
	public function edit(): void
	{
		Theme::loadTemplate('ManageCalendar');

		Utils::$context['is_new'] = !isset($_REQUEST['holiday']);
		Utils::$context['page_title'] = Utils::$context['is_new'] ? Lang::$txt['holidays_add'] : Lang::$txt['holidays_edit'];
		Utils::$context['sub_template'] = 'edit_holiday';

		// Cast this for safety...
		if (isset($_REQUEST['holiday'])) {
			$_REQUEST['holiday'] = (int) $_REQUEST['holiday'];
		}

		// Submitting?
		if (isset($_POST[Utils::$context['session_var']]) && (isset($_REQUEST['delete']) || $_REQUEST['title'] != '')) {
			User::$me->checkSession();
			SecurityToken::validate('admin-eh');

			// Not too long good sir?
			$_REQUEST['title'] = Utils::entitySubstr(Utils::normalize($_REQUEST['title']), 0, 60);
			$_REQUEST['holiday'] = isset($_REQUEST['holiday']) ? (int) $_REQUEST['holiday'] : 0;

			if (isset($_REQUEST['delete'])) {
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}calendar_holidays
					WHERE id_holiday = {int:selected_holiday}',
					[
						'selected_holiday' => $_REQUEST['holiday'],
					],
				);
			} else {
				$date = Time::strftime($_REQUEST['year'] <= 1004 ? '1004-%m-%d' : '%Y-%m-%d', mktime(0, 0, 0, $_REQUEST['month'], $_REQUEST['day'], $_REQUEST['year']));

				if (isset($_REQUEST['edit'])) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}calendar_holidays
						SET event_date = {date:holiday_date}, title = {string:holiday_title}
						WHERE id_holiday = {int:selected_holiday}',
						[
							'holiday_date' => $date,
							'selected_holiday' => $_REQUEST['holiday'],
							'holiday_title' => $_REQUEST['title'],
						],
					);
				} else {
					Db::$db->insert(
						'',
						'{db_prefix}calendar_holidays',
						[
							'event_date' => 'date', 'title' => 'string-60',
						],
						[
							$date, $_REQUEST['title'],
						],
						['id_holiday'],
					);
				}
			}

			Config::updateModSettings([
				'calendar_updated' => time(),
				'settings_updated' => time(),
			]);

			Utils::redirectexit('action=admin;area=managecalendar;sa=holidays');
		}

		SecurityToken::create('admin-eh');

		// Default states...
		if (Utils::$context['is_new']) {
			Utils::$context['holiday'] = [
				'id' => 0,
				'day' => date('d'),
				'month' => date('m'),
				'year' => '0000',
				'title' => '',
			];
		}
		// If it's not new load the data.
		else {
			$request = Db::$db->query(
				'',
				'SELECT id_holiday, YEAR(event_date) AS year, MONTH(event_date) AS month, DAYOFMONTH(event_date) AS day, title
				FROM {db_prefix}calendar_holidays
				WHERE id_holiday = {int:selected_holiday}
				LIMIT 1',
				[
					'selected_holiday' => $_REQUEST['holiday'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['holiday'] = [
					'id' => $row['id_holiday'],
					'day' => $row['day'],
					'month' => $row['month'],
					'year' => $row['year'] <= 4 ? 0 : $row['year'],
					'title' => $row['title'],
				];
			}
			Db::$db->free_result($request);
		}

		// Last day for the drop down?
		Utils::$context['holiday']['last_day'] = (int) Time::strftime('%d', mktime(0, 0, 0, Utils::$context['holiday']['month'] == 12 ? 1 : Utils::$context['holiday']['month'] + 1, 0, Utils::$context['holiday']['month'] == 12 ? Utils::$context['holiday']['year'] + 1 : Utils::$context['holiday']['year']));
	}

	/**
	 * Handles showing and changing calendar settings.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Some important context stuff
		Utils::$context['page_title'] = Lang::$txt['calendar_settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Get the final touches in place.
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=managecalendar;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['calendar_settings'];

		// Saving the settings?
		if (isset($_GET['save'])) {
			User::$me->checkSession();
			IntegrationHook::call('integrate_save_calendar_settings');
			ACP::saveDBSettings($config_vars);

			// Update the stats in case.
			Config::updateModSettings([
				'calendar_updated' => time(),
			]);

			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=managecalendar;sa=settings');
		}

		// We need this for the inline permissions
		SecurityToken::create('admin-mp');

		// Prepare the settings...
		ACP::prepareDBSettingContext($config_vars);
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
	 * @return array $config_vars for the calendar area.
	 */
	public static function getConfigVars(): array
	{
		if (!empty(Config::$modSettings['cal_enabled'])) {
			// Load the boards list.
			$boards = [''];

			$request = Db::$db->query(
				'order_by_board_order',
				'SELECT b.id_board, b.name AS board_name, c.name AS cat_name
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)',
				[
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$boards[$row['id_board']] = $row['cat_name'] . ' - ' . $row['board_name'];
			}
			Db::$db->free_result($request);

			Board::sort($boards);

			$config_vars = [
				['check', 'cal_enabled'],
				'',

				// All the permissions:
				['permissions', 'calendar_view'],
				['permissions', 'calendar_post'],
				['permissions', 'calendar_edit_own'],
				['permissions', 'calendar_edit_any'],
				'',

				// How many days to show on board index, and where to display events etc?
				['int', 'cal_days_for_index', 'help' => 'cal_maxdays_advance', 6, 'postinput' => Lang::$txt['days_word']],
				['select', 'cal_showholidays', [0 => Lang::$txt['setting_cal_show_never'], 1 => Lang::$txt['setting_cal_show_cal'], 3 => Lang::$txt['setting_cal_show_index'], 2 => Lang::$txt['setting_cal_show_all']]],
				['select', 'cal_showbdays', [0 => Lang::$txt['setting_cal_show_never'], 1 => Lang::$txt['setting_cal_show_cal'], 3 => Lang::$txt['setting_cal_show_index'], 2 => Lang::$txt['setting_cal_show_all']]],
				['select', 'cal_showevents', [0 => Lang::$txt['setting_cal_show_never'], 1 => Lang::$txt['setting_cal_show_cal'], 3 => Lang::$txt['setting_cal_show_index'], 2 => Lang::$txt['setting_cal_show_all']]],
				['check', 'cal_export'],
				'',

				// Linking events etc...
				['select', 'cal_defaultboard', $boards],
				['check', 'cal_daysaslink', 'help' => 'cal_link_postevent'],
				['check', 'cal_allow_unlinked', 'help' => 'cal_allow_unlinkedevents'],
				['check', 'cal_showInTopic'],
				'',

				// Dates of calendar...
				['int', 'cal_minyear', 'help' => 'cal_min_year'],
				['int', 'cal_maxyear', 'help' => 'cal_max_year'],
				'',

				// Calendar spanning...
				['int', 'cal_maxspan', 6, 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_for_no_limit'], 'help' => 'cal_maxevent_span'],
				'',

				// Miscellaneous layout settings...
				['check', 'cal_disable_prev_next'],
				['select', 'cal_week_links', [0 => Lang::$txt['setting_cal_week_links_none'], 1 => Lang::$txt['setting_cal_week_links_mini'], 2 => Lang::$txt['setting_cal_week_links_main'], 3 => Lang::$txt['setting_cal_week_links_both']]],
				['check', 'cal_prev_next_links'],
				['check', 'cal_short_days'],
				['check', 'cal_short_months'],
			];
		} else {
			$config_vars = [
				['check', 'cal_enabled'],
			];
		}

		IntegrationHook::call('integrate_modify_calendar_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Backward compatibility wrapper for the holidays sub-action.
	 */
	public static function modifyHolidays(): void
	{
		self::load();
		self::$obj->subaction = 'holidays';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the editholiday sub-action.
	 */
	public static function editHoliday(): void
	{
		self::load();
		self::$obj->subaction = 'editholiday';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
	 * @return void|array Returns nothing or returns $config_vars if $return_config is true
	 */
	public function modifyCalendarSettings($return_config = false)
	{
		self::load();
		self::$obj->subaction = '';
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
		// Everything's gonna need this.
		Lang::load('ManageCalendar');

		if (empty(Config::$modSettings['cal_enabled'])) {
			unset(self::$subactions['holidays'], self::$subactions['editholiday']);
			$this->subaction = 'settings';
		}

		// Set up the two tabs here...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['manage_calendar'],
			'help' => 'calendar',
			'description' => Lang::$txt['calendar_settings_desc'],
		];

		if (!empty(Config::$modSettings['cal_enabled'])) {
			Menu::$loaded['admin']->tab_data['tabs'] = [
				'holidays' => [
					'description' => Lang::$txt['manage_holidays_desc'],
				],
				'settings' => [
					'description' => Lang::$txt['calendar_settings_desc'],
				],
			];
		}

		IntegrationHook::call('integrate_manage_calendar', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Calendar::exportStatic')) {
	Calendar::exportStatic();
}

?>