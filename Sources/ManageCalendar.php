<?php

/**
 * This file allows you to manage the calendar.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * The main controlling function doesn't have much to do... yet.
 * Just check permissions and delegate to the rest.
 *
 * @uses ManageCalendar language file.
 */
function ManageCalendar()
{
	global $context, $txt, $modSettings;

	isAllowedTo('admin_forum');

	// Everything's gonna need this.
	loadLanguage('ManageCalendar');

	// Default text.
	$context['explain_text'] = $txt['calendar_desc'];

	// Little short on the ground of functions here... but things can and maybe will change...
	if (!empty($modSettings['cal_enabled']))
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

	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : $default;

	// Set up the two tabs here...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['manage_calendar'],
		'help' => 'calendar',
		'description' => $txt['calendar_settings_desc'],
	);
	if (!empty($modSettings['cal_enabled']))
		$context[$context['admin_menu_name']]['tab_data']['tabs'] = array(
			'holidays' => array(
				'description' => $txt['manage_holidays_desc'],
			),
			'settings' => array(
				'description' => $txt['calendar_settings_desc'],
			),
		);

	call_integration_hook('integrate_manage_calendar', array(&$subActions));

	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * The function that handles adding, and deleting holiday data
 */
function ModifyHolidays()
{
	global $sourcedir, $scripturl, $txt, $context, $modSettings;

	// Submitting something...
	if (isset($_REQUEST['delete']) && !empty($_REQUEST['holiday']))
	{
		checkSession();
		validateToken('admin-mc');

		foreach ($_REQUEST['holiday'] as $id => $value)
			$_REQUEST['holiday'][$id] = (int) $id;

		// Now the IDs are "safe" do the delete...
		require_once($sourcedir . '/Subs-Calendar.php');
		removeHolidays($_REQUEST['holiday']);
	}

	createToken('admin-mc');
	$listOptions = array(
		'id' => 'holiday_list',
		'title' => $txt['current_holidays'],
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=managecalendar;sa=holidays',
		'default_sort_col' => 'name',
		'get_items' => array(
			'file' => $sourcedir . '/Subs-Calendar.php',
			'function' => 'list_getHolidays',
		),
		'get_count' => array(
			'file' => $sourcedir . '/Subs-Calendar.php',
			'function' => 'list_getNumHolidays',
		),
		'no_items_label' => $txt['holidays_no_entries'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['holidays_title'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $scripturl . '?action=admin;area=managecalendar;sa=editholiday;holiday=%1$d">%2$s</a>',
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
					'value' => $txt['date'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						// Recurring every year or just a single year?
						$year = $rowData['year'] == '1004' ? sprintf('(%1$s)', $txt['every_year']) : $rowData['year'];

						// Construct the date.
						return sprintf('%1$d %2$s %3$s', $rowData['day'], $txt['months'][(int) $rowData['month']], $year);
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
			'href' => $scripturl . '?action=admin;area=managecalendar;sa=holidays',
			'token' => 'admin-mc',
		),
		'additional_rows' => array(
			array(
				'position' => 'above_column_headers',
				'value' => '<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" class="button">
					<a class="button" href="' . $scripturl . '?action=admin;area=managecalendar;sa=editholiday">' . $txt['holidays_add'] . '</a>',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="delete" value="' . $txt['quickmod_delete_selected'] . '" class="button">
					<a class="button" href="' . $scripturl . '?action=admin;area=managecalendar;sa=editholiday">' . $txt['holidays_add'] . '</a>',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	//loadTemplate('ManageCalendar');
	$context['page_title'] = $txt['manage_holidays'];

	// Since the list is the only thing to show, use the default list template.
	$context['default_list'] = 'holiday_list';
	$context['sub_template'] = 'show_list';
}

/**
 * This function is used for adding/editing a specific holiday
 */
function EditHoliday()
{
	global $txt, $context, $smcFunc;

	loadTemplate('ManageCalendar');

	$context['is_new'] = !isset($_REQUEST['holiday']);
	$context['page_title'] = $context['is_new'] ? $txt['holidays_add'] : $txt['holidays_edit'];
	$context['sub_template'] = 'edit_holiday';

	// Cast this for safety...
	if (isset($_REQUEST['holiday']))
		$_REQUEST['holiday'] = (int) $_REQUEST['holiday'];

	// Submitting?
	if (isset($_POST[$context['session_var']]) && (isset($_REQUEST['delete']) || $_REQUEST['title'] != ''))
	{
		checkSession();

		// Not too long good sir?
		$_REQUEST['title'] = $smcFunc['substr']($_REQUEST['title'], 0, 60);
		$_REQUEST['holiday'] = isset($_REQUEST['holiday']) ? (int) $_REQUEST['holiday'] : 0;

		if (isset($_REQUEST['delete']))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}calendar_holidays
				WHERE id_holiday = {int:selected_holiday}',
				array(
					'selected_holiday' => $_REQUEST['holiday'],
				)
			);
		else
		{
			$date = strftime($_REQUEST['year'] <= 1004 ? '1004-%m-%d' : '%Y-%m-%d', mktime(0, 0, 0, $_REQUEST['month'], $_REQUEST['day'], $_REQUEST['year']));
			if (isset($_REQUEST['edit']))
				$smcFunc['db_query']('', '
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
				$smcFunc['db_insert']('',
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

		updateSettings(array(
			'calendar_updated' => time(),
			'settings_updated' => time(),
		));

		redirectexit('action=admin;area=managecalendar;sa=holidays');
	}

	// Default states...
	if ($context['is_new'])
		$context['holiday'] = array(
			'id' => 0,
			'day' => date('d'),
			'month' => date('m'),
			'year' => '0000',
			'title' => ''
		);
	// If it's not new load the data.
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_holiday, YEAR(event_date) AS year, MONTH(event_date) AS month, DAYOFMONTH(event_date) AS day, title
			FROM {db_prefix}calendar_holidays
			WHERE id_holiday = {int:selected_holiday}
			LIMIT 1',
			array(
				'selected_holiday' => $_REQUEST['holiday'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['holiday'] = array(
				'id' => $row['id_holiday'],
				'day' => $row['day'],
				'month' => $row['month'],
				'year' => $row['year'] <= 4 ? 0 : $row['year'],
				'title' => $row['title']
			);
		$smcFunc['db_free_result']($request);
	}

	// Last day for the drop down?
	$context['holiday']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['holiday']['month'] == 12 ? 1 : $context['holiday']['month'] + 1, 0, $context['holiday']['month'] == 12 ? $context['holiday']['year'] + 1 : $context['holiday']['year']));
}

/**
 * Show and allow to modify calendar settings. Obviously.
 *
 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
 * @return void|array Returns nothing or returns $config_vars if $return_config is true
 */
function ModifyCalendarSettings($return_config = false)
{
	global $context, $txt, $sourcedir, $scripturl, $smcFunc, $modSettings;

	// Load the boards list.
	$boards = array('');
	$request = $smcFunc['db_query']('order_by_board_order', '
		SELECT b.id_board, b.name AS board_name, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boards[$row['id_board']] = $row['cat_name'] . ' - ' . $row['board_name'];
	$smcFunc['db_free_result']($request);

	require_once($sourcedir . '/Subs-Boards.php');
	sortBoards($boards);

	// Look, all the calendar settings - of which there are many!
	if (!empty($modSettings['cal_enabled']))
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
			array('select', 'calendar_default_view', array('viewlist' => $txt['setting_cal_viewlist'], 'viewmonth' => $txt['setting_cal_viewmonth'], 'viewweek' => $txt['setting_cal_viewweek'])),
			array('int', 'cal_days_for_index', 'help' => 'cal_maxdays_advance', 6, 'postinput' => $txt['days_word']),
			array('select', 'cal_showholidays', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('select', 'cal_showbdays', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
			array('select', 'cal_showevents', array(0 => $txt['setting_cal_show_never'], 1 => $txt['setting_cal_show_cal'], 3 => $txt['setting_cal_show_index'], 2 => $txt['setting_cal_show_all'])),
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
			array('int', 'cal_maxspan', 6, 'postinput' => $txt['days_word'], 'subtext' => $txt['zero_for_no_limit'], 'help' => 'cal_maxevent_span'),
			'',

			// A comment is like a dog marking its territory. ;)
			array('select', 'cal_highlight_events', array(0 => $txt['setting_cal_highlight_none'], 1 => $txt['setting_cal_highlight_mini'], 2 => $txt['setting_cal_highlight_main'], 3 => $txt['setting_cal_highlight_both'])),
			array('select', 'cal_highlight_holidays', array(0 => $txt['setting_cal_highlight_none'], 1 => $txt['setting_cal_highlight_mini'], 2 => $txt['setting_cal_highlight_main'], 3 => $txt['setting_cal_highlight_both'])),
			array('select', 'cal_highlight_birthdays', array(0 => $txt['setting_cal_highlight_none'], 1 => $txt['setting_cal_highlight_mini'], 2 => $txt['setting_cal_highlight_main'], 3 => $txt['setting_cal_highlight_both'])),
			'',

			// Miscellaneous layout settings...
			array('check', 'cal_disable_prev_next'),
			array('select', 'cal_display_type', array(0 => $txt['setting_cal_display_comfortable'], 1 => $txt['setting_cal_display_compact'])),
			array('select', 'cal_week_links', array(0 => $txt['setting_cal_week_links_none'], 1 => $txt['setting_cal_week_links_mini'], 2 => $txt['setting_cal_week_links_main'], 3 => $txt['setting_cal_week_links_both'])),
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
	require_once($sourcedir . '/ManageServer.php');

	// Some important context stuff
	$context['page_title'] = $txt['calendar_settings'];
	$context['sub_template'] = 'show_settings';

	// Get the final touches in place.
	$context['post_url'] = $scripturl . '?action=admin;area=managecalendar;save;sa=settings';
	$context['settings_title'] = $txt['calendar_settings'];

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession();
		call_integration_hook('integrate_save_calendar_settings');
		saveDBSettings($config_vars);

		// Update the stats in case.
		updateSettings(array(
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