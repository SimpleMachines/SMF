<?php

/**
 * This file allows you to manage the calendar.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Board;
use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\Actions\Calendar;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * The main controlling function doesn't have much to do... yet.
 * Just check permissions and delegate to the rest.
 *
 * Uses ManageCalendar language file.
 */
function ManageCalendar()
{
	isAllowedTo('admin_forum');

	// Everything's gonna need this.
	Lang::load('ManageCalendar');

	// Little short on the ground of functions here... but things can and maybe will change...
	if (!empty(Config::$modSettings['cal_enabled']))
	{
		$subActions = array(
			'editholiday' => 'EditHoliday',
			'holidays' => 'ModifyHolidays',
			'settings' => 'ModifyCalendarSettings'
		);
		$default = 'holidays';
	}
	else
	{
		$subActions = array(
			'settings' => 'ModifyCalendarSettings'
		);
		$default = 'settings';
	}

	// Set up the two tabs here...
	Utils::$context[Utils::$context['admin_menu_name']]['tab_data'] = array(
		'title' => Lang::$txt['manage_calendar'],
		'help' => 'calendar',
		'description' => Lang::$txt['calendar_settings_desc'],
	);
	if (!empty(Config::$modSettings['cal_enabled']))
		Utils::$context[Utils::$context['admin_menu_name']]['tab_data']['tabs'] = array(
			'holidays' => array(
				'description' => Lang::$txt['manage_holidays_desc'],
			),
			'settings' => array(
				'description' => Lang::$txt['calendar_settings_desc'],
			),
		);

	call_integration_hook('integrate_manage_calendar', array(&$subActions));

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : $default;

	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * The function that handles adding, and deleting holiday data
 */
function ModifyHolidays()
{
	// Submitting something...
	if (isset($_REQUEST['delete']) && !empty($_REQUEST['holiday']))
	{
		checkSession();
		validateToken('admin-mc');

		foreach ($_REQUEST['holiday'] as $id => $value)
			$_REQUEST['holiday'][$id] = (int) $id;

		// Now the IDs are "safe" do the delete...
		Calendar::removeHolidays($_REQUEST['holiday']);
	}

	createToken('admin-mc');
	$listOptions = array(
		'id' => 'holiday_list',
		'title' => Lang::$txt['current_holidays'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'base_href' => Config::$scripturl . '?action=admin;area=managecalendar;sa=holidays',
		'default_sort_col' => 'name',
		'get_items' => array(
			'file' => Config::$sourcedir . '/Actions/Calendar.php',
			'function' => 'SMF\\Actions\\Calendar::list_getHolidays',
		),
		'get_count' => array(
			'file' => Config::$sourcedir . '/Actions/Calendar.php',
			'function' => 'SMF\\Actions\\Calendar::list_getNumHolidays',
		),
		'no_items_label' => Lang::$txt['holidays_no_entries'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => Lang::$txt['holidays_title'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?action=admin;area=managecalendar;sa=editholiday;holiday=%1$d">%2$s</a>',
						'params' => array(
							'id_holiday' => false,
							'title' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'title ASC, event_date ASC',
					'reverse' => 'title DESC, event_date ASC',
				)
			),
			'date' => array(
				'header' => array(
					'value' => Lang::$txt['date'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						// Recurring every year or just a single year?
						$year = $rowData['year'] == '1004' ? sprintf('(%1$s)', Lang::$txt['every_year']) : $rowData['year'];

						// Construct the date.
						return sprintf('%1$d %2$s %3$s', $rowData['day'], Lang::$txt['months'][(int) $rowData['month']], $year);
					},
				),
				'sort' => array(
					'default' => 'event_date',
					'reverse' => 'event_date DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="holiday[%1$d]">',
						'params' => array(
							'id_holiday' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => Config::$scripturl . '?action=admin;area=managecalendar;sa=holidays',
			'token' => 'admin-mc',
		),
		'additional_rows' => array(
			array(
				'position' => 'above_column_headers',
				'value' => '<input type="submit" name="delete" value="' . Lang::$txt['quickmod_delete_selected'] . '" class="button">
					<a class="button" href="' . Config::$scripturl . '?action=admin;area=managecalendar;sa=editholiday">' . Lang::$txt['holidays_add'] . '</a>',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="delete" value="' . Lang::$txt['quickmod_delete_selected'] . '" class="button">
					<a class="button" href="' . Config::$scripturl . '?action=admin;area=managecalendar;sa=editholiday">' . Lang::$txt['holidays_add'] . '</a>',
			),
		),
	);

	require_once(Config::$sourcedir . '/Subs-List.php');
	createList($listOptions);

	//loadTemplate('ManageCalendar');
	Utils::$context['page_title'] = Lang::$txt['manage_holidays'];

	// Since the list is the only thing to show, use the default list template.
	Utils::$context['default_list'] = 'holiday_list';
	Utils::$context['sub_template'] = 'show_list';
}

/**
 * This function is used for adding/editing a specific holiday
 */
function EditHoliday()
{
	Theme::loadTemplate('ManageCalendar');

	Utils::$context['is_new'] = !isset($_REQUEST['holiday']);
	Utils::$context['page_title'] = Utils::$context['is_new'] ? Lang::$txt['holidays_add'] : Lang::$txt['holidays_edit'];
	Utils::$context['sub_template'] = 'edit_holiday';

	// Cast this for safety...
	if (isset($_REQUEST['holiday']))
		$_REQUEST['holiday'] = (int) $_REQUEST['holiday'];

	// Submitting?
	if (isset($_POST[Utils::$context['session_var']]) && (isset($_REQUEST['delete']) || $_REQUEST['title'] != ''))
	{
		checkSession();
		validateToken('admin-eh');

		// Not too long good sir?
		$_REQUEST['title'] = Utils::entitySubstr(Utils::normalize($_REQUEST['title']), 0, 60);
		$_REQUEST['holiday'] = isset($_REQUEST['holiday']) ? (int) $_REQUEST['holiday'] : 0;

		if (isset($_REQUEST['delete']))
			Db::$db->query('', '
				DELETE FROM {db_prefix}calendar_holidays
				WHERE id_holiday = {int:selected_holiday}',
				array(
					'selected_holiday' => $_REQUEST['holiday'],
				)
			);
		else
		{
			$date = smf_strftime($_REQUEST['year'] <= 1004 ? '1004-%m-%d' : '%Y-%m-%d', mktime(0, 0, 0, $_REQUEST['month'], $_REQUEST['day'], $_REQUEST['year']));
			if (isset($_REQUEST['edit']))
				Db::$db->query('', '
					UPDATE {db_prefix}calendar_holidays
					SET event_date = {date:holiday_date}, title = {string:holiday_title}
					WHERE id_holiday = {int:selected_holiday}',
					array(
						'holiday_date' => $date,
						'selected_holiday' => $_REQUEST['holiday'],
						'holiday_title' => $_REQUEST['title'],
					)
				);
			else
				Db::$db->insert('',
					'{db_prefix}calendar_holidays',
					array(
						'event_date' => 'date', 'title' => 'string-60',
					),
					array(
						$date, $_REQUEST['title'],
					),
					array('id_holiday')
				);
		}

		Config::updateModSettings(array(
			'calendar_updated' => time(),
			'settings_updated' => time(),
		));

		redirectexit('action=admin;area=managecalendar;sa=holidays');
	}

	createToken('admin-eh');

	// Default states...
	if (Utils::$context['is_new'])
		Utils::$context['holiday'] = array(
			'id' => 0,
			'day' => date('d'),
			'month' => date('m'),
			'year' => '0000',
			'title' => ''
		);
	// If it's not new load the data.
	else
	{
		$request = Db::$db->query('', '
			SELECT id_holiday, YEAR(event_date) AS year, MONTH(event_date) AS month, DAYOFMONTH(event_date) AS day, title
			FROM {db_prefix}calendar_holidays
			WHERE id_holiday = {int:selected_holiday}
			LIMIT 1',
			array(
				'selected_holiday' => $_REQUEST['holiday'],
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			Utils::$context['holiday'] = array(
				'id' => $row['id_holiday'],
				'day' => $row['day'],
				'month' => $row['month'],
				'year' => $row['year'] <= 4 ? 0 : $row['year'],
				'title' => $row['title']
			);
		Db::$db->free_result($request);
	}

	// Last day for the drop down?
	Utils::$context['holiday']['last_day'] = (int) smf_strftime('%d', mktime(0, 0, 0, Utils::$context['holiday']['month'] == 12 ? 1 : Utils::$context['holiday']['month'] + 1, 0, Utils::$context['holiday']['month'] == 12 ? Utils::$context['holiday']['year'] + 1 : Utils::$context['holiday']['year']));
}

/**
 * Show and allow to modify calendar settings. Obviously.
 *
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns $config_vars if $return_config is true
 */
function ModifyCalendarSettings($return_config = false)
{
	// Load the boards list.
	$boards = array('');
	$request = Db::$db->query('order_by_board_order', '
		SELECT b.id_board, b.name AS board_name, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)',
		array(
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
		$boards[$row['id_board']] = $row['cat_name'] . ' - ' . $row['board_name'];
	Db::$db->free_result($request);

	Board::sort($boards);

	// Look, all the calendar settings - of which there are many!
	if (!empty(Config::$modSettings['cal_enabled']))
		$config_vars = array(
			array('check', 'cal_enabled'),
			'',

			// All the permissions:
			array('permissions', 'calendar_view'),
			array('permissions', 'calendar_post'),
			array('permissions', 'calendar_edit_own'),
			array('permissions', 'calendar_edit_any'),
			'',

			// How many days to show on board index, and where to display events etc?
			array('int', 'cal_days_for_index', 'help' => 'cal_maxdays_advance', 6, 'postinput' => Lang::$txt['days_word']),
			array('select', 'cal_showholidays', array(0 => Lang::$txt['setting_cal_show_never'], 1 => Lang::$txt['setting_cal_show_cal'], 3 => Lang::$txt['setting_cal_show_index'], 2 => Lang::$txt['setting_cal_show_all'])),
			array('select', 'cal_showbdays', array(0 => Lang::$txt['setting_cal_show_never'], 1 => Lang::$txt['setting_cal_show_cal'], 3 => Lang::$txt['setting_cal_show_index'], 2 => Lang::$txt['setting_cal_show_all'])),
			array('select', 'cal_showevents', array(0 => Lang::$txt['setting_cal_show_never'], 1 => Lang::$txt['setting_cal_show_cal'], 3 => Lang::$txt['setting_cal_show_index'], 2 => Lang::$txt['setting_cal_show_all'])),
			array('check', 'cal_export'),
			'',

			// Linking events etc...
			array('select', 'cal_defaultboard', $boards),
			array('check', 'cal_daysaslink', 'help' => 'cal_link_postevent'),
			array('check', 'cal_allow_unlinked', 'help' => 'cal_allow_unlinkedevents'),
			array('check', 'cal_showInTopic'),
			'',

			// Dates of calendar...
			array('int', 'cal_minyear', 'help' => 'cal_min_year'),
			array('int', 'cal_maxyear', 'help' => 'cal_max_year'),
			'',

			// Calendar spanning...
			array('int', 'cal_maxspan', 6, 'postinput' => Lang::$txt['days_word'], 'subtext' => Lang::$txt['zero_for_no_limit'], 'help' => 'cal_maxevent_span'),
			'',

			// Miscellaneous layout settings...
			array('check', 'cal_disable_prev_next'),
			array('select', 'cal_week_links', array(0 => Lang::$txt['setting_cal_week_links_none'], 1 => Lang::$txt['setting_cal_week_links_mini'], 2 => Lang::$txt['setting_cal_week_links_main'], 3 => Lang::$txt['setting_cal_week_links_both'])),
			array('check', 'cal_prev_next_links'),
			array('check', 'cal_short_days'),
			array('check', 'cal_short_months'),
		);
	else
		$config_vars = array(
			array('check', 'cal_enabled'),
		);

	call_integration_hook('integrate_modify_calendar_settings', array(&$config_vars));
	if ($return_config)
		return $config_vars;

	// Get the settings template fired up.
	require_once(Config::$sourcedir . '/ManageServer.php');

	// Some important context stuff
	Utils::$context['page_title'] = Lang::$txt['calendar_settings'];
	Utils::$context['sub_template'] = 'show_settings';

	// Get the final touches in place.
	Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=managecalendar;save;sa=settings';
	Utils::$context['settings_title'] = Lang::$txt['calendar_settings'];

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();
		call_integration_hook('integrate_save_calendar_settings');
		saveDBSettings($config_vars);

		// Update the stats in case.
		Config::updateModSettings(array(
			'calendar_updated' => time(),
		));

		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=managecalendar;sa=settings');
	}

	// We need this for the inline permissions
	createToken('admin-mp');

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}

?>