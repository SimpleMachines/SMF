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
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Actions\Admin;

use SMF\ActionInterface;
use SMF\Actions\BackwardCompatibility;
use SMF\ActionTrait;
use SMF\Board;
use SMF\Calendar\Event;
use SMF\Calendar\Holiday;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\TaskRunner;
use SMF\Theme;
use SMF\Time;
use SMF\TimeInterval;
use SMF\TimeZone;
use SMF\Url;
use SMF\User;
use SMF\Utils;
use SMF\WebFetch\WebFetchApi;

/**
 * This class allows you to manage the calendar.
 */
class Calendar implements ActionInterface
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
		'import' => 'import',
		'settings' => 'settings',
	];

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
				Holiday::remove((int) $id);
			}
		}

		SecurityToken::create('admin-mc');
		$listOptions = [
			'id' => 'holiday_list',
			'title' => Lang::$txt['current_holidays'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=managecalendar;sa=holidays',
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => 'SMF\\Calendar\\Holiday::list',
			],
			'get_count' => [
				'function' => 'SMF\\Calendar\\Holiday::count',
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
								'id' => false,
								'title' => false,
							],
						],
					],
					'sort' => [
						'default' => 'cal.title ASC, cal.start_date ASC',
						'reverse' => 'cal.title DESC, cal.start_date ASC',
					],
				],
				'date' => [
					'header' => [
						'value' => Lang::$txt['date'],
					],
					'data' => [
						'function' => function ($event) {
							$rrule = $event->recurrence_iterator->getRRule();

							if (
								!empty($event->special_rrule)
								|| !empty($rrule->bymonth)
								|| !empty($rrule->byweekno)
								|| !empty($rrule->byyearday)
								|| !empty($rrule->bymonthday)
								|| !empty($rrule->byday)
								|| !empty($rrule->byhour)
								|| !empty($rrule->byminute)
								|| !empty($rrule->bysecond)
								|| !empty($rrule->bysetpos)
								|| $event->recurrence_iterator->getRDates() !== []
							) {
								return Lang::$txt['holidays_date_varies'];
							}

							if (isset($rrule->count) && $rrule->count === 1) {
								return $event->start->format(Time::getDateFormat());
							}

							return $event->start->format(Time::getShortDateFormat());
						},
					],
					'sort' => [
						'default' => 'cal.start_date',
						'reverse' => 'cal.start_date DESC',
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
								'id' => false,
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
		if (isset($_POST[Utils::$context['session_var']]) && (isset($_REQUEST['delete']) || ($_REQUEST['evtitle'] ?? '') != '')) {
			User::$me->checkSession();
			SecurityToken::validate('admin-eh');

			$_REQUEST['holiday'] = isset($_REQUEST['holiday']) ? (int) $_REQUEST['holiday'] : -1;

			if ($_REQUEST['holiday'] === -1) {
				$eventOptions = [
					'title' => Utils::entitySubstr($_REQUEST['evtitle'], 0, 100),
					'location' => Utils::entitySubstr($_REQUEST['event_location'], 0, 255),
				];
				Holiday::create($eventOptions);
			} elseif (isset($_REQUEST['delete'])) {
				Holiday::remove($_REQUEST['holiday']);
			} else {
				$eventOptions = [
					'title' => Utils::entitySubstr($_REQUEST['evtitle'], 0, 100),
					'location' => Utils::entitySubstr($_REQUEST['event_location'], 0, 255),
				];
				Holiday::modify($_REQUEST['holiday'], $eventOptions);
			}

			Config::updateModSettings([
				'calendar_updated' => time(),
				'settings_updated' => time(),
			]);

			Utils::redirectexit('action=admin;area=managecalendar;sa=holidays');
		}

		SecurityToken::create('admin-eh');

		if (Utils::$context['is_new']) {
			Utils::$context['event'] = new Holiday(-1, ['rrule' => 'FREQ=YEARLY']);
		} else {
			Utils::$context['event'] = current(Holiday::load($_REQUEST['holiday']));
		}

		Utils::$context['event']->selected_occurrence = Utils::$context['event']->getFirstOccurrence();

		// An all day event? Set up some nice defaults in case the user wants to change that
		if (Utils::$context['event']->allday == true) {
			Utils::$context['event']->selected_occurrence->tz = User::getTimezone();
			Utils::$context['event']->selected_occurrence->start->modify(Time::create('now')->format('%H:%M:%S'));
			Utils::$context['event']->selected_occurrence->duration = new TimeInterval('PT1H');
		}

		// Need this so the user can select a timezone for the event.
		Utils::$context['all_timezones'] = TimeZone::list(Utils::$context['event']->start_datetime);

		// If the event's timezone is not in SMF's standard list of time zones, try to fix it.
		Utils::$context['event']->selected_occurrence->fixTimezone();

		Theme::loadTemplate('EventEditor');
		Theme::addJavaScriptVar('monthly_byday_items', count(Utils::$context['event']->byday_items) - 1);
		Theme::loadJavaScriptFile('event.js', ['defer' => true], 'smf_event');
	}

	/**
	 * Handles importing events and holidays from iCalendar files.
	 */
	public function import(): void
	{
		Theme::loadTemplate('ManageCalendar');
		Utils::$context['sub_template'] = 'import';
		Utils::$context['page_title'] = Lang::$txt['calendar_import'];

		// Submitting?
		if (isset($_POST[Utils::$context['session_var']])) {
			User::$me->checkSession();
			SecurityToken::validate('admin-calendarimport');

			if (isset($_POST['ics_url'], $_POST['type'])) {
				$ics_url = new Url($_POST['ics_url'], true);

				if ($ics_url->isValid()) {
					$ics_data = WebFetchApi::fetch($ics_url);
				}

				if (!empty($ics_data)) {
					switch ($_POST['type']) {
						case 'holiday':
							Holiday::import($ics_data);
							break;

						case 'event':
							Event::import($ics_data);
							break;
					}
				}

				// Subscribing to this calendar?
				if (isset($_POST['subscribe'])) {
					$subscribed = Utils::jsonDecode(Config::$modSettings['calendar_subscriptions'] ?? '[]', true);

					$subscribed[(string) $ics_url] = $_POST['type'] === 'holiday' ? Event::TYPE_HOLIDAY : Event::TYPE_EVENT;

					Config::updateModSettings(['calendar_subscriptions' => Utils::jsonEncode($subscribed)]);

					$request = Db::$db->query(
						'',
						'SELECT id_task
						FROM {db_prefix}scheduled_tasks
						WHERE task = {string:task}',
						[
							'task' => 'fetch_calendar_subs',
						],
					);

					$exists = Db::$db->num_rows($request) > 0;
					Db::$db->free_result($request);

					if (!$exists) {
						$id_task = Db::$db->insert(
							'',
							'{db_prefix}scheduled_tasks',
							[
								'next_time' => 'int',
								'time_offset' => 'int',
								'time_regularity' => 'int',
								'time_unit' => 'string-1',
								'disabled' => 'int',
								'task' => 'string-24',
							],
							[
								'next_time' => 0,
								'time_offset' => 0,
								'time_regularity' => 1,
								'time_unit' => 'd',
								'disabled' => 0,
								'task' => 'fetch_calendar_subs',
							],
							['id_task'],
						);

						TaskRunner::calculateNextTrigger((string) $id_task, true);
					}
				}
			}

			// Unsubscribing from some calendars?
			if (isset($_POST['unsubscribe'], $_POST['subscribed'])) {
				$subscribed = Utils::jsonDecode(Config::$modSettings['calendar_subscriptions'] ?? '[]', true);

				foreach ($subscribed as $url => $type) {
					$hashes[md5($url)] = $url;
				}

				foreach ($_POST['subscribed'] as $hash => $value) {
					unset($subscribed[$hashes[$hash]]);
				}

				Config::updateModSettings(['calendar_subscriptions' => Utils::jsonEncode($subscribed)]);
			}
		}

		SecurityToken::create('admin-calendarimport');

		// List the current calendar subscriptions.
		$subscribed = Utils::jsonDecode(Config::$modSettings['calendar_subscriptions'] ?? '[]', true);

		if (!empty($subscribed)) {
			foreach ($subscribed as $url => $type) {
				Utils::$context['calendar_subscriptions'][] = [
					'hash' => md5($url),
					'url' => $url,
					'type' => $type === Event::TYPE_HOLIDAY ? Lang::$txt['calendar_import_type_holiday'] : Lang::$txt['calendar_import_type_event'],
				];
			}

			$listOptions = [
				'id' => 'calendar_subscriptions',
				'title' => Lang::$txt['calendar_import_manage_subscriptions'],
				'items_per_page' => Config::$modSettings['defaultMaxListItems'],
				'base_href' => Config::$scripturl . '?action=admin;area=managecalendar;sa=import',
				// 'default_sort_col' => 'url',
				'get_items' => [
					'value' => Utils::$context['calendar_subscriptions'],
				],
				'get_count' => [
					'value' => count(Utils::$context['calendar_subscriptions']),
				],
				'no_items_label' => Lang::$txt['none'],
				'columns' => [
					'url' => [
						'header' => [
							'value' => Lang::$txt['url'],
						],
						'data' => [
							'sprintf' => [
								'format' => '%1$s',
								'params' => [
									'url' => true,
								],
							],
						],
						'sort' => [
							'default' => '',
							'reverse' => '',
						],
					],
					'type' => [
						'header' => [
							'value' => Lang::sentenceList(
								[
									Lang::$txt['calendar_import_type_event'],
									Lang::$txt['calendar_import_type_holiday'],
								],
								'or',
							),
						],
						'data' => [
							'sprintf' => [
								'format' => '%1$s',
								'params' => [
									'type' => false,
								],
							],
						],
						'sort' => [
							'default' => 'cal.start_date',
							'reverse' => 'cal.start_date DESC',
						],
					],
					'check' => [
						'header' => [
							'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
							'class' => 'centercol',
						],
						'data' => [
							'sprintf' => [
								'format' => '<input type="checkbox" name="subscribed[%1$s]">',
								'params' => [
									'hash' => false,
								],
							],
							'class' => 'centercol',
						],
					],
				],
				'form' => [
					'href' => Config::$scripturl . '?action=admin;area=managecalendar;sa=import',
					'token' => 'admin-calendarimport',
				],
				'additional_rows' => [
					// [
					// 	'position' => 'above_column_headers',
					// 	'value' => '<input type="submit" name="unsubscribe" value="' . Lang::$txt['calendar_import_unsubscribe'] . '" class="button">',
					// ],
					[
						'position' => 'below_table_data',
						'value' => '<input type="submit" name="unsubscribe" value="' . Lang::$txt['calendar_import_unsubscribe'] . '" class="button">',
					],
				],
			];

			new ItemList($listOptions);

			Theme::loadTemplate('GenericList');
		}
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Everything's gonna need this.
		Lang::load('Calendar+ManageCalendar');

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
				'import' => [
					'description' => Lang::$txt['calendar_import_desc'],
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

?>