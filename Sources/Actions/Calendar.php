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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Board;
use SMF\BrowserDetector;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Event;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\TimeZone;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * This class has only one real task, showing the calendar.
 * Original module by Aaron O'Neil - aaron@mud-master.com
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
			'call' => 'CalendarMain',
			'iCalDownload' => 'iCalDownload',
			'CalendarPost' => 'CalendarPost',
			'getBirthdayRange' => 'getBirthdayRange',
			'getEventRange' => 'getEventRange',
			'getHolidayRange' => 'getHolidayRange',
			'canLinkEvent' => 'canLinkEvent',
			'getTodayInfo' => 'getTodayInfo',
			'getCalendarGrid' => 'getCalendarGrid',
			'getCalendarWeek' => 'getCalendarWeek',
			'getCalendarList' => 'getCalendarList',
			'loadDatePicker' => 'loadDatePicker',
			'loadTimePicker' => 'loadTimePicker',
			'loadDatePair' => 'loadDatePair',
			'cache_getOffsetIndependentEvents' => 'cache_getOffsetIndependentEvents',
			'cache_getRecentEvents' => 'cache_getRecentEvents',
			'validateEventPost' => 'validateEventPost',
			'getEventPoster' => 'getEventPoster',
			'list_getHolidays' => 'list_getHolidays',
			'list_getNumHolidays' => 'list_getNumHolidays',
			'removeHolidays' => 'removeHolidays',
			'convertDateToEnglish' => 'convertDateToEnglish',
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
	public string $subaction = 'show';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions of this action.
	 */
	public static array $subactions = [
		'show' => 'show',
		'ical' => 'export',
		'post' => 'post',
		'clock' => 'clock',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var object
	 *
	 * An instance of the class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static $obj;

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
	 * Shows the calendar.
	 *
	 * It loads the specified month's events, holidays, and birthdays.
	 * It requires the calendar_view permission.
	 * It depends on the cal_enabled setting, and many of the other cal_ settings.
	 * It uses the calendar_start_day theme option. (Monday/Sunday)
	 * It uses the main sub template in the Calendar template.
	 * It goes to the month and year passed in 'month' and 'year' by get or post.
	 * It is accessed through ?action=calendar.
	 *
	 */
	public function show(): void
	{
		// You can't do anything if the calendar is off.
		if (empty(Config::$modSettings['cal_enabled'])) {
			ErrorHandler::fatalLang('calendar_off', false);
		}

		// This is gonna be needed...
		Theme::loadTemplate('Calendar');
		Theme::loadCSSFile('calendar.css', ['force_current' => false, 'validate' => true, 'rtl' => 'calendar.rtl.css'], 'smf_calendar');

		// Did the specify an individual event ID? If so, let's splice the year/month in to what we would otherwise be doing.
		if (isset($_GET['event'])) {
			$evid = (int) $_GET['event'];

			if ($evid > 0) {
				$request = Db::$db->query(
					'',
					'SELECT start_date
					FROM {db_prefix}calendar
					WHERE id_event = {int:event_id}',
					[
						'event_id' => $evid,
					],
				);

				if ($row = Db::$db->fetch_assoc($request)) {
					$_REQUEST['start_date'] = $row['start_date'];

					// We might use this later.
					Utils::$context['selected_event'] = $evid;
				}
				Db::$db->free_result($request);
			}
			unset($_GET['event']);
		}

		// Set the page title to mention the calendar ;).
		Utils::$context['page_title'] = Lang::$txt['calendar'];

		// Ensure a default view is defined
		if (empty(Theme::$current->options['calendar_default_view'])) {
			Theme::$current->options['calendar_default_view'] = 'viewlist';
		}

		// What view do we want?
		if (isset($_GET['viewweek'])) {
			Utils::$context['calendar_view'] = 'viewweek';
		} elseif (isset($_GET['viewmonth'])) {
			Utils::$context['calendar_view'] = 'viewmonth';
		} elseif (isset($_GET['viewlist'])) {
			Utils::$context['calendar_view'] = 'viewlist';
		} else {
			Utils::$context['calendar_view'] = Theme::$current->options['calendar_default_view'];
		}

		// Don't let search engines index the non-default calendar pages
		if (Utils::$context['calendar_view'] !== Theme::$current->options['calendar_default_view']) {
			Utils::$context['robot_no_index'] = true;
		}

		// Get the current day of month...
		$today = self::getTodayInfo();

		// Need a start date for all views
		if (!empty($_REQUEST['start_date'])) {
			$start_parsed = date_parse(str_replace(',', '', self::convertDateToEnglish($_REQUEST['start_date'])));

			if (empty($start_parsed['error_count']) && empty($start_parsed['warning_count'])) {
				$_REQUEST['year'] = $start_parsed['year'];
				$_REQUEST['month'] = $start_parsed['month'];
				$_REQUEST['day'] = $start_parsed['day'];
			}
		}
		$year = !empty($_REQUEST['year']) ? (int) $_REQUEST['year'] : $today['year'];
		$month = !empty($_REQUEST['month']) ? (int) $_REQUEST['month'] : $today['month'];
		$day = !empty($_REQUEST['day']) ? (int) $_REQUEST['day'] : (!empty($_REQUEST['month']) ? 1 : $today['day']);

		$start_object = checkdate($month, $day, $year) === true ? new Time(implode('-', [$year, $month, $day]) . ' ' . User::getTimezone()) : new Time(implode('-', [$today['year'], $today['month'], $today['day']]) . ' ' . User::getTimezone());

		// Need an end date for the list view
		if (!empty($_REQUEST['end_date'])) {
			$end_parsed = date_parse(str_replace(',', '', self::convertDateToEnglish($_REQUEST['end_date'])));

			if (empty($end_parsed['error_count']) && empty($end_parsed['warning_count'])) {
				$_REQUEST['end_year'] = $end_parsed['year'];
				$_REQUEST['end_month'] = $end_parsed['month'];
				$_REQUEST['end_day'] = $end_parsed['day'];
			}
		}
		$end_year = !empty($_REQUEST['end_year']) ? (int) $_REQUEST['end_year'] : null;
		$end_month = !empty($_REQUEST['end_month']) ? (int) $_REQUEST['end_month'] : null;
		$end_day = !empty($_REQUEST['end_day']) ? (int) $_REQUEST['end_day'] : null;

		$end_object = null;

		if (isset($end_month, $end_day, $end_year) && checkdate($end_month, $end_day, $end_year)) {
			$end_object = new Time(implode('-', [$end_year, $end_month, $end_day]) . ' ' . User::getTimezone());
		}

		if (empty($end_object) || $start_object >= $end_object) {
			$num_days_shown = empty(Config::$modSettings['cal_days_for_index']) || Config::$modSettings['cal_days_for_index'] < 1 ? 1 : Config::$modSettings['cal_days_for_index'];

			$end_object = new Time($start_object->format('Y-m-d') . ' ' . User::getTimezone());

			date_add($end_object, date_interval_create_from_date_string($num_days_shown . ' days'));
		}

		$curPage = [
			'year' => $start_object->format('Y'),
			'month' => $start_object->format('n'),
			'day' => $start_object->format('j'),
			'start_date' => $start_object->format('Y-m-d'),
			'end_year' => $end_object->format('Y'),
			'end_month' => $end_object->format('n'),
			'end_day' => $end_object->format('j'),
			'end_date' => $end_object->format('Y-m-d'),
		];

		// Make sure the year and month are in valid ranges.
		if ($curPage['month'] < 1 || $curPage['month'] > 12) {
			ErrorHandler::fatalLang('invalid_month', false);
		}

		if ($curPage['year'] < Config::$modSettings['cal_minyear'] || $curPage['year'] > Config::$modSettings['cal_maxyear']) {
			ErrorHandler::fatalLang('invalid_year', false);
		}

		// If we have a day clean that too.
		if (Utils::$context['calendar_view'] != 'viewmonth') {
			$isValid = checkdate($curPage['month'], $curPage['day'], $curPage['year']);

			if (!$isValid) {
				ErrorHandler::fatalLang('invalid_day', false);
			}
		}

		// Load all the context information needed to show the calendar grid.
		$calendarOptions = [
			'start_day' => !empty(Theme::$current->options['calendar_start_day']) ? Theme::$current->options['calendar_start_day'] : 0,
			'show_birthdays' => in_array(Config::$modSettings['cal_showbdays'], [1, 2]),
			'show_events' => in_array(Config::$modSettings['cal_showevents'], [1, 2]),
			'show_holidays' => in_array(Config::$modSettings['cal_showholidays'], [1, 2]),
			'show_week_num' => true,
			'short_day_titles' => !empty(Config::$modSettings['cal_short_days']),
			'short_month_titles' => !empty(Config::$modSettings['cal_short_months']),
			'show_next_prev' => !empty(Config::$modSettings['cal_prev_next_links']),
			'show_week_links' => Config::$modSettings['cal_week_links'] ?? 0,
		];

		// Load up the main view.
		if (Utils::$context['calendar_view'] == 'viewlist') {
			Utils::$context['calendar_grid_main'] = self::getCalendarList($curPage['start_date'], $curPage['end_date'], $calendarOptions);
		} elseif (Utils::$context['calendar_view'] == 'viewweek') {
			Utils::$context['calendar_grid_main'] = self::getCalendarWeek($curPage['start_date'], $calendarOptions);
		} else {
			Utils::$context['calendar_grid_main'] = self::getCalendarGrid($curPage['start_date'], $calendarOptions);
		}

		// Load up the previous and next months.
		Utils::$context['calendar_grid_current'] = self::getCalendarGrid($curPage['start_date'], $calendarOptions, false, false);

		// Only show previous month if it isn't pre-January of the min-year
		if (Utils::$context['calendar_grid_current']['previous_calendar']['year'] > Config::$modSettings['cal_minyear'] || $curPage['month'] != 1) {
			Utils::$context['calendar_grid_prev'] = self::getCalendarGrid(Utils::$context['calendar_grid_current']['previous_calendar']['start_date'], $calendarOptions, true, false);
		}

		// Only show next month if it isn't post-December of the max-year
		if (Utils::$context['calendar_grid_current']['next_calendar']['year'] < Config::$modSettings['cal_maxyear'] || $curPage['month'] != 12) {
			Utils::$context['calendar_grid_next'] = self::getCalendarGrid(Utils::$context['calendar_grid_current']['next_calendar']['start_date'], $calendarOptions, false, false);
		}

		// Basic template stuff.
		Utils::$context['allow_calendar_event'] = User::$me->allowedTo('calendar_post');

		// If you don't allow events not linked to posts and you're not an admin, we have more work to do...
		if (Utils::$context['allow_calendar_event'] && empty(Config::$modSettings['cal_allow_unlinked']) && !User::$me->is_admin) {
			$boards_can_post = User::$me->boardsAllowedTo('post_new');
			Utils::$context['allow_calendar_event'] &= !empty($boards_can_post);
		}

		Utils::$context['can_post'] = Utils::$context['allow_calendar_event'];
		Utils::$context['current_day'] = $curPage['day'];
		Utils::$context['current_month'] = $curPage['month'];
		Utils::$context['current_year'] = $curPage['year'];
		Utils::$context['show_all_birthdays'] = isset($_GET['showbd']);
		Utils::$context['blocks_disabled'] = !empty(Config::$modSettings['cal_disable_prev_next']) ? 1 : 0;

		// Set the page title to mention the month or week, too
		if (Utils::$context['calendar_view'] != 'viewlist') {
			Utils::$context['page_title'] .= ' - ' . (Utils::$context['calendar_view'] == 'viewweek' ? Utils::$context['calendar_grid_main']['week_title'] : Lang::$txt['months_titles'][Utils::$context['current_month']] . ' ' . Utils::$context['current_year']);
		}

		// Load up the linktree!
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=calendar',
			'name' => Lang::$txt['calendar'],
		];
		// Add the current month to the linktree.
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=calendar;year=' . Utils::$context['current_year'] . ';month=' . Utils::$context['current_month'],
			'name' => Lang::$txt['months_titles'][Utils::$context['current_month']] . ' ' . Utils::$context['current_year'],
		];

		// If applicable, add the current week to the linktree.
		if (Utils::$context['calendar_view'] == 'viewweek') {
			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . '?action=calendar;viewweek;year=' . Utils::$context['current_year'] . ';month=' . Utils::$context['current_month'] . ';day=' . Utils::$context['current_day'],
				'name' => Utils::$context['calendar_grid_main']['week_title'],
			];
		}

		// Build the calendar button array.
		Utils::$context['calendar_buttons'] = [];

		if (Utils::$context['can_post']) {
			Utils::$context['calendar_buttons']['post_event'] = ['text' => 'calendar_post_event', 'image' => 'calendarpe.png', 'url' => Config::$scripturl . '?action=calendar;sa=post;month=' . Utils::$context['current_month'] . ';year=' . Utils::$context['current_year'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']];
		}

		// Allow mods to add additional buttons here
		IntegrationHook::call('integrate_calendar_buttons');
	}

	/**
	 * This method processes posting/editing/deleting a calendar event.
	 *
	 * Calls {@link Post.php|Post() Post()} function if event is linked to a post.
	 *
	 * It requires the calendar_post permission to use.
	 * It uses the event_post sub template in the Calendar template.
	 * It is accessed with ?action=calendar;sa=post.
	 */
	public function post(): void
	{
		// Well - can they?
		User::$me->isAllowedTo('calendar_post');

		// Cast this for safety...
		if (isset($_REQUEST['eventid'])) {
			$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];
		}

		// Submitting?
		if (isset($_POST[Utils::$context['session_var']], $_REQUEST['eventid'])) {
			User::$me->checkSession();

			// Validate the post...
			if (!isset($_POST['link_to_board'])) {
				self::validateEventPost();
			}

			// If you're not allowed to edit any events, you have to be the poster.
			if ($_REQUEST['eventid'] > 0 && !User::$me->allowedTo('calendar_edit_any')) {
				User::$me->isAllowedTo('calendar_edit_' . (!empty(User::$me->id) && self::getEventPoster($_REQUEST['eventid']) == User::$me->id ? 'own' : 'any'));
			}

			// New - and directing?
			if (isset($_POST['link_to_board']) || empty(Config::$modSettings['cal_allow_unlinked'])) {
				$_REQUEST['calendar'] = 1;
				Post::call();

				return;
			}

			// New...
			if ($_REQUEST['eventid'] == -1) {
				$eventOptions = [
					'board' => 0,
					'topic' => 0,
					'title' => Utils::entitySubstr($_REQUEST['evtitle'], 0, 100),
					'location' => Utils::entitySubstr($_REQUEST['event_location'], 0, 255),
					'member' => User::$me->id,
				];
				Event::create($eventOptions);
			}
			// Deleting...
			elseif (isset($_REQUEST['deleteevent'])) {
				Event::remove($_REQUEST['eventid']);
			}
			// ... or just update it?
			else {
				$eventOptions = [
					'title' => Utils::entitySubstr($_REQUEST['evtitle'], 0, 100),
					'location' => Utils::entitySubstr($_REQUEST['event_location'], 0, 255),
				];
				Event::modify($_REQUEST['eventid'], $eventOptions);
			}

			Config::updateModSettings([
				'calendar_updated' => time(),
			]);

			// No point hanging around here now...
			if (isset($_POST['start_date'])) {
				$d = date_parse($_POST['start_date']);
				$year = $d['year'];
				$month = $d['month'];
				$day = $d['day'];
			} elseif (isset($_POST['start_datetime'])) {
				$d = date_parse($_POST['start_datetime']);
				$year = $d['year'];
				$month = $d['month'];
				$day = $d['day'];
			} else {
				$today = getdate();
				$year = $_POST['year'] ?? $today['year'];
				$month = $_POST['month'] ?? $today['mon'];
				$day = $_POST['day'] ?? $today['mday'];
			}

			Utils::redirectexit(Config::$scripturl . '?action=calendar;month=' . $month . ';year=' . $year . ';day=' . $day);
		}

		// If we are not enabled... we are not enabled.
		if (empty(Config::$modSettings['cal_allow_unlinked']) && empty($_REQUEST['eventid'])) {
			$_REQUEST['calendar'] = 1;
			Post::call();

			return;
		}

		// New?
		if (!isset($_REQUEST['eventid'])) {
			Utils::$context['event'] = new Event(-1);
		} else {
			list(Utils::$context['event']) = Event::load($_REQUEST['eventid']);

			if (empty(Utils::$context['event'])) {
				ErrorHandler::fatalLang('no_access', false);
			}

			// If it has a board, then they should be editing it within the topic.
			if (!empty(Utils::$context['event']->topic) && !empty(Utils::$context['event']->msg)) {
				// We load the board up, for a check on the board access rights...
				Topic::$topic_id = Utils::$context['event']->topic;
				Board::load();
			}

			// Make sure the user is allowed to edit this event.
			if (Utils::$context['event']->member != User::$me->id) {
				User::$me->isAllowedTo('calendar_edit_any');
			} elseif (!User::$me->allowedTo('calendar_edit_any')) {
				User::$me->isAllowedTo('calendar_edit_own');
			}
		}

		// An all day event? Set up some nice defaults in case the user wants to change that
		if (Utils::$context['event']->allday == true) {
			Utils::$context['event']->tz = User::getTimezone();
			Utils::$context['event']->start->modify(Time::create('now')->format('%H:%M:%S'));
			Utils::$context['event']->end->modify(Time::create('now + 1 hour')->format('%H:%M:%S'));
		}

		// Need this so the user can select a timezone for the event.
		Utils::$context['all_timezones'] = TimeZone::list(Utils::$context['event']->start_date);

		// If the event's timezone is not in SMF's standard list of time zones, try to fix it.
		Utils::$context['event']->fixTimezone();

		// Get list of boards that can be posted in.
		$boards = User::$me->boardsAllowedTo('post_new');

		if (empty($boards)) {
			// You can post new events but can't link them to anything...
			Utils::$context['event']->categories = [];
		} else {
			// Load the list of boards and categories in the context.
			$boardListOptions = [
				'included_boards' => in_array(0, $boards) ? null : $boards,
				'not_redirection' => true,
				'use_permissions' => true,
				'selected_board' => Config::$modSettings['cal_defaultboard'],
			];
			Utils::$context['event']->categories = MessageIndex::getBoardList($boardListOptions);
		}

		// Template, sub template, etc.
		Theme::loadTemplate('Calendar');
		Utils::$context['sub_template'] = 'event_post';

		Utils::$context['page_title'] = isset($_REQUEST['eventid']) ? Lang::$txt['calendar_edit'] : Lang::$txt['calendar_post_event'];
		Utils::$context['linktree'][] = [
			'name' => Utils::$context['page_title'],
		];

		self::loadDatePicker('#event_time_input .date_input');
		self::loadTimePicker('#event_time_input .time_input', Time::getShortTimeFormat());
		self::loadDatePair('#event_time_input', 'date_input', 'time_input');
		Theme::addInlineJavaScript('
		$("#allday").click(function(){
			$("#start_time").attr("disabled", this.checked);
			$("#end_time").attr("disabled", this.checked);
			$("#tz").attr("disabled", this.checked);
		});', true);
	}

	/**
	 * This function offers up a download of an event in iCal 2.0 format.
	 *
	 * Follows the conventions in {@link https://tools.ietf.org/html/rfc5546 RFC5546}
	 *
	 * @todo .... allow for week or month export files as well?
	 */
	public function export(): void
	{
		// You can't export if the calendar export feature is off.
		if (empty(Config::$modSettings['cal_export'])) {
			ErrorHandler::fatalLang('calendar_export_off', false);
		}

		// Goes without saying that this is required.
		if (!isset($_REQUEST['eventid'])) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Load up the event in question and check it is valid.
		list($event) = Event::load($_REQUEST['eventid']);

		if (!($event instanceof Event)) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// This is what we will be sending later.
		$filecontents = [];
		$filecontents[] = 'BEGIN:VCALENDAR';
		$filecontents[] = 'METHOD:PUBLISH';
		$filecontents[] = 'PRODID:-//SimpleMachines//' . SMF_FULL_VERSION . '//EN';
		$filecontents[] = 'VERSION:2.0';

		$filecontents[] = $event->getVEvent();

		$filecontents[] = 'END:VCALENDAR';

		// Send some standard headers.
		ob_end_clean();

		if (!empty(Config::$modSettings['enableCompressedOutput'])) {
			@ob_start('ob_gzhandler');
		} else {
			ob_start();
		}

		// Send the file headers
		header('pragma: ');
		header('cache-control: no-cache');

		if (!BrowserDetector::isBrowser('gecko')) {
			header('content-transfer-encoding: binary');
		}

		header('expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
		header('last-modified: ' . gmdate('D, d M Y H:i:s', $event->modified_time) . ' GMT');
		header('accept-ranges: bytes');
		header('connection: close');
		header('content-disposition: attachment; filename="' . $event->title . '.ics"');

		if (empty(Config::$modSettings['enableCompressedOutput'])) {
			header('content-length: ' . Utils::entityStrlen($filecontents));
		}

		// This is a calendar item!
		header('content-type: text/calendar');

		// Chuck out the card.
		echo implode("\n", $filecontents);

		// Off we pop - lovely!
		Utils::obExit(false);
	}

	/**
	 * Nothing to see here. Move along.
	 */
	public function clock(): void
	{
		Utils::$context['onimg'] = Theme::$current->settings['images_url'] . '/bbc/bbc_hoverbg.png';
		Utils::$context['offimg'] = Theme::$current->settings['images_url'] . '/bbc/bbc_bg.png';

		Utils::$context['page_title'] = 'Anyone know what time it is?';
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=calendar',
			'name' => Lang::$txt['calendar'],
		];
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=calendar;sa=clock',
			'name' => 'Clock',
		];
		Utils::$context['robot_no_index'] = true;

		$omfg = isset($_REQUEST['omfg']);
		$bcd = !isset($_REQUEST['rb']) && !isset($_REQUEST['omfg']) && !isset($_REQUEST['time']);

		Theme::loadTemplate('Calendar');

		if ($bcd) {
			Utils::$context['sub_template'] = 'bcd';
			Utils::$context['linktree'][] = ['url' => Config::$scripturl . '?action=calendar;sa=clock;bcd', 'name' => 'BCD'];
			Utils::$context['clockicons'] = Utils::jsonDecode(base64_decode('eyJoMSI6WzIsMV0sImgyIjpbOCw0LDIsMV0sIm0xIjpbNCwyLDFdLCJtMiI6WzgsNCwyLDFdLCJzMSI6WzQsMiwxXSwiczIiOls4LDQsMiwxXX0='), true);
		} elseif (!$omfg && !isset($_REQUEST['time'])) {
			Utils::$context['sub_template'] = 'hms';
			Utils::$context['linktree'][] = ['url' => Config::$scripturl . '?action=calendar;sa=clock', 'name' => 'Binary'];
			Utils::$context['clockicons'] = Utils::jsonDecode(base64_decode('eyJoIjpbMTYsOCw0LDIsMV0sIm0iOlszMiwxNiw4LDQsMiwxXSwicyI6WzMyLDE2LDgsNCwyLDFdfQ'), true);
		} elseif ($omfg) {
			Utils::$context['sub_template'] = 'omfg';
			Utils::$context['linktree'][] = ['url' => Config::$scripturl . '?action=calendar;sa=clock;omfg', 'name' => 'OMFG'];
			Utils::$context['clockicons'] = Utils::jsonDecode(base64_decode('eyJ5ZWFyIjpbNjQsMzIsMTYsOCw0LDIsMV0sIm1vbnRoIjpbOCw0LDIsMV0sImRheSI6WzE2LDgsNCwyLDFdLCJob3VyIjpbMTYsOCw0LDIsMV0sIm1pbiI6WzMyLDE2LDgsNCwyLDFdLCJzZWMiOlszMiwxNiw4LDQsMiwxXX0='), true);
		} elseif (isset($_REQUEST['time'])) {
			Utils::$context['sub_template'] = 'thetime';
			$_REQUEST['time'] = $_REQUEST['time'] == '' ? 'now' : $_REQUEST['time'];
			$time = getdate($_REQUEST['time'] == 'now' ? time() : (int) $_REQUEST['time']);
			Utils::$context['linktree'][] = ['url' => Config::$scripturl . '?action=calendar;sa=clock;time=' . $_REQUEST['time'], 'name' => 'Requested Time'];
			Utils::$context['clockicons'] = [
				'year' => array_fill_keys(array_map(fn ($p) => 2 ** $p, range(6, 0)), false),
				'mon' => array_fill_keys(array_map(fn ($p) => 2 ** $p, range(3, 0)), false),
				'mday' => array_fill_keys(array_map(fn ($p) => 2 ** $p, range(4, 0)), false),
				'hours' => array_fill_keys(array_map(fn ($p) => 2 ** $p, range(5, 0)), false),
				'minutes' => array_fill_keys(array_map(fn ($p) => 2 ** $p, range(5, 0)), false),
				'seconds' => array_fill_keys(array_map(fn ($p) => 2 ** $p, range(5, 0)), false),
			];

			foreach (Utils::$context['clockicons'] as $t => $vs) {
				foreach ($vs as $v => $dumb) {
					if ($time[$t] >= $v) {
						$time[$t] -= $v;
						Utils::$context['clockicons'][$t][$v] = true;
					}
				}
			}
		}
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
	 * Backward compatibility wrapper for ical sub-action.
	 */
	public static function iCalDownload(): void
	{
		self::load();
		self::$obj->subaction = 'ical';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for post sub-action.
	 */
	public static function CalendarPost(): void
	{
		self::load();
		self::$obj->subaction = 'post';
		self::$obj->execute();
	}

	/**
	 * Get all birthdays within the given time range.
	 * finds all the birthdays in the specified range of days.
	 * works with birthdays set for no year, or any other year, and respects month and year boundaries.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @return array An array of days, each of which is an array of birthday information for the context
	 */
	public static function getBirthdayRange($low_date, $high_date): array
	{
		// We need to search for any birthday in this range, and whatever year that birthday is on.
		$year_low = (int) substr($low_date, 0, 4);
		$year_high = (int) substr($high_date, 0, 4);

		if (Db::$db->title !== POSTGRE_TITLE) {
			// Collect all of the birthdays for this month.  I know, it's a painful query.
			$result = Db::$db->query(
				'',
				'SELECT id_member, real_name, YEAR(birthdate) AS birth_year, birthdate
				FROM {db_prefix}members
				WHERE birthdate != {date:no_birthdate}
					AND (
						DATE_FORMAT(birthdate, {string:year_low}) BETWEEN {date:low_date} AND {date:high_date}' . ($year_low == $year_high ? '' : '
						OR DATE_FORMAT(birthdate, {string:year_high}) BETWEEN {date:low_date} AND {date:high_date}') . '
					)
					AND is_activated = {int:is_activated}',
				[
					'is_activated' => 1,
					'no_birthdate' => '1004-01-01',
					'year_low' => $year_low . '-%m-%d',
					'year_high' => $year_high . '-%m-%d',
					'low_date' => $low_date,
					'high_date' => $high_date,
				],
			);
		} else {
			$result = Db::$db->query(
				'',
				'SELECT id_member, real_name, YEAR(birthdate) AS birth_year, birthdate
				FROM {db_prefix}members
				WHERE birthdate != {date:no_birthdate}
					AND (
						indexable_month_day(birthdate) BETWEEN indexable_month_day({date:year_low_low_date}) AND indexable_month_day({date:year_low_high_date})' . ($year_low == $year_high ? '' : '
						OR indexable_month_day(birthdate) BETWEEN indexable_month_day({date:year_high_low_date}) AND indexable_month_day({date:year_high_high_date})') . '
					)
					AND is_activated = {int:is_activated}',
				[
					'is_activated' => 1,
					'no_birthdate' => '1004-01-01',
					'year_low_low_date' => $low_date,
					'year_low_high_date' => $year_low == $year_high ? $high_date : $year_low . '-12-31',
					'year_high_low_date' => $year_low == $year_high ? $low_date : $year_high . '-01-01',
					'year_high_high_date' => $high_date,
				],
			);
		}
		$bday = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			if ($year_low != $year_high) {
				$age_year = substr($row['birthdate'], 5) <= substr($high_date, 5) ? $year_high : $year_low;
			} else {
				$age_year = $year_low;
			}

			$bday[$age_year . substr($row['birthdate'], 4)][] = [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'age' => $row['birth_year'] > 1004 && $row['birth_year'] <= $age_year ? $age_year - $row['birth_year'] : null,
				'is_last' => false,
			];
		}
		Db::$db->free_result($result);

		ksort($bday);

		// Set is_last, so the themes know when to stop placing separators.
		foreach ($bday as $mday => $array) {
			$bday[$mday][count($array) - 1]['is_last'] = true;
		}

		return $bday;
	}

	/**
	 * Get all calendar events within the given time range.
	 *
	 * - Finds all the posted calendar events within a date range.
	 * - Both the earliest_date and latest_date should be in the standard YYYY-MM-DD format.
	 * - Censors the posted event titles.
	 * - Uses the current user's permissions if use_permissions is true, otherwise it does nothing "permission specific"
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @param bool $use_permissions Whether to use permissions
	 * @return array The loaded events.
	 */
	public static function getEventRange($low_date, $high_date, $use_permissions = true): array
	{
		$events = [];

		$one_day = date_interval_create_from_date_string('1 day');
		$tz = timezone_open(User::getTimezone());

		foreach (Event::loadRange($low_date, $high_date, $use_permissions) as $event) {
			$cal_date = new Time($event->start_date_local, $tz);

			while ($cal_date->getTimestamp() <= $event->end->getTimestamp() && $cal_date->format('Y-m-d') <= $high_date) {
				$events[$cal_date->format('Y-m-d')][] = $event;
				date_add($cal_date, $one_day);
			}
		}

		foreach ($events as $mday => $array) {
			$events[$mday][count($array) - 1]['is_last'] = true;
		}

		ksort($events);

		return $events;
	}

	/**
	 * Get all holidays within the given time range.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @return array An array of days, which are all arrays of holiday names.
	 */
	public static function getHolidayRange($low_date, $high_date): array
	{
		// Get the lowest and highest dates for "all years".
		if (substr($low_date, 0, 4) != substr($high_date, 0, 4)) {
			$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_dec}
				OR event_date BETWEEN {date:all_year_jan} AND {date:all_year_high}';
		} else {
			$allyear_part = 'event_date BETWEEN {date:all_year_low} AND {date:all_year_high}';
		}

		// Find some holidays... ;).
		$result = Db::$db->query(
			'',
			'SELECT event_date, YEAR(event_date) AS year, title
			FROM {db_prefix}calendar_holidays
			WHERE event_date BETWEEN {date:low_date} AND {date:high_date}
				OR ' . $allyear_part,
			[
				'low_date' => $low_date,
				'high_date' => $high_date,
				'all_year_low' => '1004' . substr($low_date, 4),
				'all_year_high' => '1004' . substr($high_date, 4),
				'all_year_jan' => '1004-01-01',
				'all_year_dec' => '1004-12-31',
			],
		);
		$holidays = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			if (substr($low_date, 0, 4) != substr($high_date, 0, 4)) {
				$event_year = substr($row['event_date'], 5) < substr($high_date, 5) ? substr($high_date, 0, 4) : substr($low_date, 0, 4);
			} else {
				$event_year = substr($low_date, 0, 4);
			}

			$holidays[$event_year . substr($row['event_date'], 4)][] = $row['title'];
		}
		Db::$db->free_result($result);

		ksort($holidays);

		return $holidays;
	}

	/**
	 * Does permission checks to see if an event can be linked to a board/topic.
	 *
	 * Checks if the current user can link the current topic to the calendar, permissions et al.
	 * This requires the calendar_post permission, a forum moderator, or a topic starter.
	 * Expects the Topic::$topic_id and Board::$info->id variables to be set.
	 * If the user doesn't have proper permissions, an error will be shown.
	 */
	public static function canLinkEvent(): void
	{
		// If you can't post, you can't link.
		User::$me->isAllowedTo('calendar_post');

		// No board?  No topic?!?
		if (empty(Board::$info->id)) {
			ErrorHandler::fatalLang('missing_board_id', false);
		}

		if (empty(Topic::$topic_id)) {
			ErrorHandler::fatalLang('missing_topic_id', false);
		}

		// Administrator, Moderator, or owner.  Period.
		if (!User::$me->allowedTo('admin_forum') && !User::$me->allowedTo('moderate_board')) {
			// Not admin or a moderator of this board. You better be the owner - or else.
			$result = Db::$db->query(
				'',
				'SELECT id_member_started
				FROM {db_prefix}topics
				WHERE id_topic = {int:current_topic}
				LIMIT 1',
				[
					'current_topic' => Topic::$topic_id,
				],
			);

			if ($row = Db::$db->fetch_assoc($result)) {
				// Not the owner of the topic.
				if ($row['id_member_started'] != User::$me->id) {
					ErrorHandler::fatalLang('not_your_topic', 'user');
				}
			}
			// Topic/Board doesn't exist.....
			else {
				ErrorHandler::fatalLang('calendar_no_topic', 'general');
			}
			Db::$db->free_result($result);
		}
	}

	/**
	 * Returns date information about 'today' relative to the users time offset.
	 * returns an array with the current date, day, month, and year.
	 * takes the users time offset into account.
	 *
	 * @return array An array of info about today, based on forum time. Has 'day', 'month', 'year' and 'date' (in YYYY-MM-DD format)
	 */
	public static function getTodayInfo(): array
	{
		return [
			'day' => (int) Time::strftime('%d', time(), User::getTimezone()),
			'month' => (int) Time::strftime('%m', time(), User::getTimezone()),
			'year' => (int) Time::strftime('%Y', time(), User::getTimezone()),
			'date' => Time::strftime('%Y-%m-%d', time(), User::getTimezone()),
		];
	}

	/**
	 * Provides information (link, month, year) about the previous and next month.
	 *
	 * @param string $selected_date A date in YYYY-MM-DD format
	 * @param array $calendarOptions An array of calendar options
	 * @param bool $is_previous Whether this is the previous month
	 * @param bool $has_picker Whether to add javascript to handle a date picker
	 * @return array A large array containing all the information needed to show a calendar grid for the given month
	 */
	public static function getCalendarGrid($selected_date, $calendarOptions, $is_previous = false, $has_picker = true): array
	{
		$selected_object = new Time($selected_date . ' ' . User::getTimezone());

		$next_object = new Time($selected_date . ' ' . User::getTimezone());
		$next_object->modify('first day of next month');

		$prev_object = new Time($selected_date . ' ' . User::getTimezone());
		$prev_object->modify('first day of previous month');

		// Eventually this is what we'll be returning.
		$calendarGrid = [
			'week_days' => [],
			'weeks' => [],
			'short_day_titles' => !empty($calendarOptions['short_day_titles']),
			'short_month_titles' => !empty($calendarOptions['short_month_titles']),
			'current_month' => $selected_object->format('n'),
			'current_year' => $selected_object->format('Y'),
			'current_day' => $selected_object->format('d'),
			'show_next_prev' => !empty($calendarOptions['show_next_prev']),
			'show_week_links' => $calendarOptions['show_week_links'] ?? 0,
			'previous_calendar' => [
				'year' => $prev_object->format('Y'),
				'month' => $prev_object->format('n'),
				'day' => $prev_object->format('d'),
				'start_date' => $prev_object->format('Y-m-d'),
				'disabled' => Config::$modSettings['cal_minyear'] > $prev_object->format('Y'),
			],
			'next_calendar' => [
				'year' => $next_object->format('Y'),
				'month' => $next_object->format('n'),
				'day' => $next_object->format('d'),
				'start_date' => $next_object->format('Y-m-d'),
				'disabled' => Config::$modSettings['cal_maxyear'] < $next_object->format('Y'),
			],
			'start_date' => $selected_object->format(Time::getDateFormat()),
		];

		// Get today's date.
		$today = self::getTodayInfo();

		$first_day_object = new Time($selected_object->format('Y-m-01') . ' ' . User::getTimezone());
		$last_day_object = new Time($selected_object->format('Y-m-t') . ' ' . User::getTimezone());

		// Get information about this month.
		$month_info = [
			'first_day' => [
				'day_of_week' => $first_day_object->format('w'),
				'week_num' => $first_day_object->format('W'),
				'date' => $first_day_object->format('Y-m-d'),
			],
			'last_day' => [
				'day_of_month' => $last_day_object->format('t'),
				'date' => $last_day_object->format('Y-m-d'),
			],
			'first_day_of_year' => Time::create($selected_object->format('Y-01-01') . ' ' . User::getTimezone())->format('w'),
			'first_day_of_next_year' => Time::create(($selected_object->format('Y') + 1) . '-01-01' . ' ' . User::getTimezone())->format('w'),
		];

		// The number of days the first row is shifted to the right for the starting day.
		$nShift = $month_info['first_day']['day_of_week'];

		$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];

		// Starting any day other than Sunday means a shift...
		if (!empty($calendarOptions['start_day'])) {
			$nShift -= $calendarOptions['start_day'];

			if ($nShift < 0) {
				$nShift = 7 + $nShift;
			}
		}

		// Number of rows required to fit the month.
		$nRows = floor(($month_info['last_day']['day_of_month'] + $nShift) / 7);

		if (($month_info['last_day']['day_of_month'] + $nShift) % 7) {
			$nRows++;
		}

		// Fetch the arrays for birthdays, posted events, and holidays.
		$bday = $calendarOptions['show_birthdays'] ? self::getBirthdayRange($month_info['first_day']['date'], $month_info['last_day']['date']) : [];
		$events = $calendarOptions['show_events'] ? self::getEventRange($month_info['first_day']['date'], $month_info['last_day']['date']) : [];
		$holidays = $calendarOptions['show_holidays'] ? self::getHolidayRange($month_info['first_day']['date'], $month_info['last_day']['date']) : [];

		// Days of the week taking into consideration that they may want it to start on any day.
		$count = $calendarOptions['start_day'];

		for ($i = 0; $i < 7; $i++) {
			$calendarGrid['week_days'][] = $count;
			$count++;

			if ($count == 7) {
				$count = 0;
			}
		}

		// Iterate through each week.
		$calendarGrid['weeks'] = [];

		for ($nRow = 0; $nRow < $nRows; $nRow++) {
			// Start off the week - and don't let it go above 52, since that's the number of weeks in a year.
			$calendarGrid['weeks'][$nRow] = [
				'days' => [],
			];

			// And figure out all the days.
			for ($nCol = 0; $nCol < 7; $nCol++) {
				$nDay = ($nRow * 7) + $nCol - $nShift + 1;

				if ($nDay < 1 || $nDay > $month_info['last_day']['day_of_month']) {
					$nDay = 0;
				}

				$date = $selected_object->format('Y-m-') . sprintf('%02d', $nDay);

				$calendarGrid['weeks'][$nRow]['days'][$nCol] = [
					'day' => $nDay,
					'date' => $date,
					'is_today' => $date == $today['date'],
					'is_first_day' => !empty($calendarOptions['show_week_num']) && (($month_info['first_day']['day_of_week'] + $nDay - 1) % 7 == $calendarOptions['start_day']),
					'is_first_of_month' => $nDay === 1,
					'holidays' => !empty($holidays[$date]) ? $holidays[$date] : [],
					'events' => !empty($events[$date]) ? $events[$date] : [],
					'birthdays' => !empty($bday[$date]) ? $bday[$date] : [],
				];
			}
		}

		// What is the last day of the month?
		if ($is_previous === true) {
			$calendarGrid['last_of_month'] = $month_info['last_day']['day_of_month'];
		}

		// We'll use the shift in the template.
		$calendarGrid['shift'] = $nShift;

		// Set the previous and the next month's links.
		$calendarGrid['previous_calendar']['href'] = Config::$scripturl . '?action=calendar;viewmonth;year=' . $calendarGrid['previous_calendar']['year'] . ';month=' . $calendarGrid['previous_calendar']['month'] . ';day=' . $calendarGrid['previous_calendar']['day'];

		$calendarGrid['next_calendar']['href'] = Config::$scripturl . '?action=calendar;viewmonth;year=' . $calendarGrid['next_calendar']['year'] . ';month=' . $calendarGrid['next_calendar']['month'] . ';day=' . $calendarGrid['previous_calendar']['day'];

		if ($has_picker) {
			self::loadDatePicker('#calendar_navigation .date_input');
			self::loadDatePair('#calendar_navigation', 'date_input');
		}

		return $calendarGrid;
	}

	/**
	 * Returns the information needed to show a calendar for the given week.
	 *
	 * @param string $selected_date A date in YYYY-MM-DD format
	 * @param array $calendarOptions An array of calendar options
	 * @return array An array of information needed to display the grid for a single week on the calendar
	 */
	public static function getCalendarWeek($selected_date, $calendarOptions): array
	{
		$selected_object = new Time($selected_date . ' ' . User::getTimezone());

		// Get today's date.
		$today = self::getTodayInfo();

		// What is the actual "start date" for the passed day.
		$calendarOptions['start_day'] = empty($calendarOptions['start_day']) ? 0 : (int) $calendarOptions['start_day'];

		$day_of_week = $selected_object->format('w');
		$first_day_object = new Time($selected_date . ' ' . User::getTimezone());

		if ($day_of_week != $calendarOptions['start_day']) {
			// Here we offset accordingly to get things to the real start of a week.
			$date_diff = $day_of_week - $calendarOptions['start_day'];

			if ($date_diff < 0) {
				$date_diff += 7;
			}

			date_sub($first_day_object, date_interval_create_from_date_string($date_diff . ' days'));
		}

		$last_day_object = new Time($first_day_object->format('Y-m-d') . ' ' . User::getTimezone());
		date_add($last_day_object, date_interval_create_from_date_string('1 week'));

		$month = $first_day_object->format('n');
		$year = $first_day_object->format('Y');
		$day = $first_day_object->format('d');

		$next_object = new Time($selected_date . ' ' . User::getTimezone());
		date_add($next_object, date_interval_create_from_date_string('1 week'));

		$prev_object = new Time($selected_date . ' ' . User::getTimezone());
		date_sub($prev_object, date_interval_create_from_date_string('1 week'));

		// Now start filling in the calendar grid.
		$calendarGrid = [
			'show_next_prev' => !empty($calendarOptions['show_next_prev']),
			'previous_week' => [
				'year' => $prev_object->format('Y'),
				'month' => $prev_object->format('n'),
				'day' => $prev_object->format('d'),
				'start_date' => $prev_object->format('Y-m-d'),
				'disabled' => Config::$modSettings['cal_minyear'] > $prev_object->format('Y'),
			],
			'next_week' => [
				'year' => $next_object->format('Y'),
				'month' => $next_object->format('n'),
				'day' => $next_object->format('d'),
				'start_date' => $next_object->format('Y-m-d'),
				'disabled' => Config::$modSettings['cal_maxyear'] < $next_object->format('Y'),
			],
			'start_date' => $selected_object->format(Time::getDateFormat()),
			'show_events' => $calendarOptions['show_events'],
			'show_holidays' => $calendarOptions['show_holidays'],
			'show_birthdays' => $calendarOptions['show_birthdays'],
		];

		// Fetch the arrays for birthdays, posted events, and holidays.
		$bday = $calendarOptions['show_birthdays'] ? self::getBirthdayRange($first_day_object->format('Y-m-d'), $last_day_object->format('Y-m-d')) : [];
		$events = $calendarOptions['show_events'] ? self::getEventRange($first_day_object->format('Y-m-d'), $last_day_object->format('Y-m-d')) : [];
		$holidays = $calendarOptions['show_holidays'] ? self::getHolidayRange($first_day_object->format('Y-m-d'), $last_day_object->format('Y-m-d')) : [];

		$calendarGrid['week_title'] = sprintf(Lang::$txt['calendar_week_beginning'], Lang::$txt['months'][$first_day_object->format('n')], $first_day_object->format('j'), $first_day_object->format('Y'));

		// This holds all the main data - there is at least one month!
		$calendarGrid['months'] = [];

		$current_day_object = new Time($first_day_object->format('Y-m-d') . ' ' . User::getTimezone());

		for ($i = 0; $i < 7; $i++) {
			$current_month = $current_day_object->format('n');
			$current_day = $current_day_object->format('j');
			$current_date = $current_day_object->format('Y-m-d');

			if (!isset($calendarGrid['months'][$current_month])) {
				$calendarGrid['months'][$current_month] = [
					'current_month' => $current_month,
					'current_year' => $current_day_object->format('Y'),
					'days' => [],
				];
			}

			$calendarGrid['months'][$current_month]['days'][$current_day] = [
				'day' => $current_day,
				'day_of_week' => ($current_day_object->format('w') + 7) % 7,
				'date' => $current_date,
				'is_today' => $current_date == $today['date'],
				'holidays' => !empty($holidays[$current_date]) ? $holidays[$current_date] : [],
				'events' => !empty($events[$current_date]) ? $events[$current_date] : [],
				'birthdays' => !empty($bday[$current_date]) ? $bday[$current_date] : [],
			];

			date_add($current_day_object, date_interval_create_from_date_string('1 day'));
		}

		// Set the previous and the next week's links.
		$calendarGrid['previous_week']['href'] = Config::$scripturl . '?action=calendar;viewweek;year=' . $calendarGrid['previous_week']['year'] . ';month=' . $calendarGrid['previous_week']['month'] . ';day=' . $calendarGrid['previous_week']['day'];

		$calendarGrid['next_week']['href'] = Config::$scripturl . '?action=calendar;viewweek;year=' . $calendarGrid['next_week']['year'] . ';month=' . $calendarGrid['next_week']['month'] . ';day=' . $calendarGrid['next_week']['day'];

		self::loadDatePicker('#calendar_navigation .date_input');
		self::loadDatePair('#calendar_navigation', 'date_input', '');

		return $calendarGrid;
	}

	/**
	 * Returns the information needed to show a list of upcoming events, birthdays, and holidays on the calendar.
	 *
	 * @param string $start_date The start of a date range in YYYY-MM-DD format
	 * @param string $end_date The end of a date range in YYYY-MM-DD format
	 * @param array $calendarOptions An array of calendar options
	 * @return array An array of information needed to display a list of upcoming events, etc., on the calendar
	 */
	public static function getCalendarList($start_date, $end_date, $calendarOptions): array
	{
		// DateTime objects make life easier
		$start_object = new Time($start_date . ' ' . User::getTimezone());
		$end_object = new Time($end_date . ' ' . User::getTimezone());

		$calendarGrid = [
			'start_date' => $start_object->format(Time::getDateFormat()),
			'start_year' => $start_object->format('Y'),
			'start_month' => $start_object->format('m'),
			'start_day' => $start_object->format('d'),
			'end_date' => $end_object->format(Time::getDateFormat()),
			'end_year' => $end_object->format('Y'),
			'end_month' => $end_object->format('m'),
			'end_day' => $end_object->format('d'),
		];

		$calendarGrid['birthdays'] = $calendarOptions['show_birthdays'] ? self::getBirthdayRange($start_date, $end_date) : [];
		$calendarGrid['holidays'] = $calendarOptions['show_holidays'] ? self::getHolidayRange($start_date, $end_date) : [];
		$calendarGrid['events'] = $calendarOptions['show_events'] ? self::getEventRange($start_date, $end_date) : [];

		// Get rid of duplicate events
		$temp = [];

		foreach ($calendarGrid['events'] as $date => $date_events) {
			foreach ($date_events as $event_key => $event_val) {
				if (in_array($event_val['id'], $temp)) {
					unset($calendarGrid['events'][$date][$event_key]);

					if (empty($calendarGrid['events'][$date])) {
						unset($calendarGrid['events'][$date]);
					}
				} else {
					$temp[] = $event_val['id'];
				}
			}
		}

		// Give birthdays and holidays a friendly format, without the year.
		$date_format = Time::getShortDateFormat();

		foreach (['birthdays', 'holidays'] as $type) {
			foreach ($calendarGrid[$type] as $date => $date_content) {
				// Make sure to apply no offsets
				$date_local = preg_replace('~(?<=\s)0+(\d)~', '$1', trim(Time::create($date)->format($date_format), " \t\n\r\0\x0B,./;:<>()[]{}\\|-_=+"));

				$calendarGrid[$type][$date]['date_local'] = $date_local;
			}
		}

		self::loadDatePicker('#calendar_range .date_input');
		self::loadDatePair('#calendar_range', 'date_input', '');

		return $calendarGrid;
	}

	/**
	 * Loads the necessary JavaScript and CSS to create a datepicker.
	 *
	 * @param string $selector A CSS selector for the input field(s) that the datepicker should be attached to.
	 * @param string $date_format The date format to use, in strftime() format.
	 */
	public static function loadDatePicker($selector = 'input.date_input', $date_format = ''): void
	{
		if (empty($date_format)) {
			$date_format = Time::getDateFormat();
		}

		// Convert to format used by datepicker
		$date_format = strtr($date_format, [
			// Day
			'%a' => 'D', '%A' => 'DD', '%e' => 'd', '%d' => 'dd', '%j' => 'oo', '%u' => '', '%w' => '',
			// Week
			'%U' => '', '%V' => '', '%W' => '',
			// Month
			'%b' => 'M', '%B' => 'MM', '%h' => 'M', '%m' => 'mm',
			// Year
			'%C' => '', '%g' => 'y', '%G' => 'yy', '%y' => 'y', '%Y' => 'yy',
			// Time (we remove all of these)
			'%H' => '', '%k' => '', '%I' => '', '%l' => '', '%M' => '', '%p' => '', '%P' => '',
			'%r' => '', '%R' => '', '%S' => '', '%T' => '', '%X' => '', '%z' => '', '%Z' => '',
			// Time and Date Stamps
			'%c' => 'D, d M yy', '%D' => 'mm/dd/y', '%F' => 'yy-mm-dd', '%s' => '@', '%x' => 'D, d M yy',
			// Miscellaneous
			'%n' => ' ', '%t' => ' ', '%%' => '%',
		]);

		Theme::loadCSSFile('jquery-ui.datepicker.css', [], 'smf_datepicker');
		Theme::loadJavaScriptFile('jquery-ui.datepicker.min.js', ['defer' => true], 'smf_datepicker');
		Theme::addInlineJavaScript('
		$("' . $selector . '").datepicker({
			dateFormat: "' . $date_format . '",
			autoSize: true,
			isRTL: ' . (Utils::$context['right_to_left'] ? 'true' : 'false') . ',
			constrainInput: true,
			showAnim: "",
			showButtonPanel: false,
			yearRange: "' . Config::$modSettings['cal_minyear'] . ':' . Config::$modSettings['cal_maxyear'] . '",
			hideIfNoPrevNext: true,
			monthNames: ["' . implode('", "', Lang::$txt['months_titles']) . '"],
			monthNamesShort: ["' . implode('", "', Lang::$txt['months_short']) . '"],
			dayNames: ["' . implode('", "', Lang::$txt['days']) . '"],
			dayNamesShort: ["' . implode('", "', Lang::$txt['days_short']) . '"],
			dayNamesMin: ["' . implode('", "', Lang::$txt['days_short']) . '"],
			prevText: "' . Lang::$txt['prev_month'] . '",
			nextText: "' . Lang::$txt['next_month'] . '",
			firstDay: ' . (!empty(Theme::$current->options['calendar_start_day']) ? Theme::$current->options['calendar_start_day'] : 0) . ',
		});', true);
	}

	/**
	 * Loads the necessary JavaScript and CSS to create a timepicker.
	 *
	 * @param string $selector A CSS selector for the input field(s) that the timepicker should be attached to.
	 * @param string $time_format A time format in strftime format
	 */
	public static function loadTimePicker($selector = 'input.time_input', $time_format = ''): void
	{
		if (empty($time_format)) {
			$time_format = Time::getTimeFormat();
		}

		// Format used for timepicker
		$time_format = strtr($time_format, [
			'%H' => 'H',
			'%k' => 'G',
			'%I' => 'h',
			'%l' => 'g',
			'%M' => 'i',
			'%p' => 'A',
			'%P' => 'a',
			'%r' => 'h:i:s A',
			'%R' => 'H:i',
			'%S' => 's',
			'%T' => 'H:i:s',
			'%X' => 'H:i:s',
		]);

		Theme::loadCSSFile('jquery.timepicker.css', [], 'smf_timepicker');
		Theme::loadJavaScriptFile('jquery.timepicker.min.js', ['defer' => true], 'smf_timepicker');
		Theme::addInlineJavaScript('
		$("' . $selector . '").timepicker({
			timeFormat: "' . $time_format . '",
			showDuration: true,
			maxTime: "23:59:59",
			lang: {
				am: "' . strtolower(Lang::$txt['time_am']) . '",
				pm: "' . strtolower(Lang::$txt['time_pm']) . '",
				AM: "' . strtoupper(Lang::$txt['time_am']) . '",
				PM: "' . strtoupper(Lang::$txt['time_pm']) . '",
				decimal: "' . Lang::$txt['decimal_sign'] . '",
				mins: "' . Lang::$txt['minutes_short'] . '",
				hr: "' . Lang::$txt['hour_short'] . '",
				hrs: "' . Lang::$txt['hours_short'] . '",
			}
		});', true);
	}

	/**
	 * Loads the necessary JavaScript for Datepair.js.
	 *
	 * Datepair.js helps to keep date ranges sane in the UI.
	 *
	 * @param string $container CSS selector for the containing element of the date/time inputs to be paired.
	 * @param string $date_class The CSS class of the date inputs to be paired.
	 * @param string $time_class The CSS class of the time inputs to be paired.
	 */
	public static function loadDatePair($container, $date_class = '', $time_class = ''): void
	{
		$container = (string) $container;
		$date_class = (string) $date_class;
		$time_class = (string) $time_class;

		if ($container == '') {
			return;
		}

		Theme::loadJavaScriptFile('jquery.datepair.min.js', ['defer' => true], 'smf_datepair');

		$datepair_options = '';

		// If we're not using a date input, we might as well disable these.
		if ($date_class == '') {
			$datepair_options .= '
			parseDate: function (el) {},
			updateDate: function (el, v) {},';
		} else {
			$datepair_options .= '
			dateClass: "' . $date_class . '",';

			// Customize Datepair to work with jQuery UI's datepicker.
			$datepair_options .= '
			parseDate: function (el) {
				var val = $(el).datepicker("getDate");
				if (!val) {
					return null;
				}
				var utc = new Date(val);
				return utc && new Date(utc.getTime() + (utc.getTimezoneOffset() * 60000));
			},
			updateDate: function (el, v) {
				$(el).datepicker("setDate", new Date(v.getTime() - (v.getTimezoneOffset() * 60000)));
			},';
		}

		// If not using a time input, disable time functions.
		if ($time_class == '') {
			$datepair_options .= '
			parseTime: function(input){},
			updateTime: function(input, dateObj){},
			setMinTime: function(input, dateObj){},';
		} else {
			$datepair_options .= '
			timeClass: "' . $time_class . '",';
		}

		Theme::addInlineJavaScript('
		$("' . $container . '").datepair({' . $datepair_options . "\n\t});", true);
	}

	/**
	 * Retrieve all events for the given days, independently of the users offset.
	 * cache callback function used to retrieve the birthdays, holidays, and events between now and now + days_to_index.
	 * widens the search range by an extra 24 hours to support time offset shifts.
	 * used by the self::cache_getRecentEvents function to get the information needed to calculate the events taking the users time offset into account.
	 *
	 * @param array $eventOptions With the keys 'num_days_shown', 'include_holidays', 'include_birthdays' and 'include_events'
	 * @return array An array containing the data that was cached as well as an expression to calculate whether the data should be refreshed and when it expires
	 */
	public static function cache_getOffsetIndependentEvents($eventOptions): array
	{
		$days_to_index = $eventOptions['num_days_shown'];

		$low_date = Time::strftime('%Y-%m-%d', time() - 24 * 3600);
		$high_date = Time::strftime('%Y-%m-%d', time() + $days_to_index * 24 * 3600);

		return [
			'data' => [
				'holidays' => (!empty($eventOptions['include_holidays']) ? self::getHolidayRange($low_date, $high_date) : []),
				'birthdays' => (!empty($eventOptions['include_birthdays']) ? self::getBirthdayRange($low_date, $high_date) : []),
				'events' => (!empty($eventOptions['include_events']) ? self::getEventRange($low_date, $high_date, false) : []),
			],
			'refresh_eval' => 'return \'' . Time::strftime('%Y%m%d', time()) . '\' != \\SMF\\Time::strftime(\'%Y%m%d\', time()) || (!empty(\\SMF\\Config::$modSettings[\'calendar_updated\']) && ' . time() . ' < \\SMF\\Config::$modSettings[\'calendar_updated\']);',
			'expires' => time() + 3600,
		];
	}

	/**
	 * cache callback function used to retrieve the upcoming birthdays, holidays, and events within the given period, taking into account the users time offset.
	 * Called from the BoardIndex to display the current day's events on the board index
	 * used by the board index and SSI to show the upcoming events.
	 *
	 * @param array $eventOptions An array of event options.
	 * @return array An array containing the info that was cached as well as a few other relevant things
	 */
	public static function cache_getRecentEvents($eventOptions): array
	{
		// With the 'static' cached data we can calculate the user-specific data.
		$cached_data = CacheApi::quickGet('calendar_index', 'Actions/Calendar.php', 'SMF\\Actions\\Calendar::cache_getOffsetIndependentEvents', [$eventOptions]);

		// Get the information about today (from user perspective).
		$today = self::getTodayInfo();

		$return_data = [
			'calendar_holidays' => [],
			'calendar_birthdays' => [],
			'calendar_events' => [],
		];

		// Set the event span to be shown in seconds.
		$days_for_index = $eventOptions['num_days_shown'] * 86400;

		// Get the current member time/date.
		$now = time();

		if (!empty($eventOptions['include_holidays'])) {
			// Holidays between now and now + days.
			for ($i = $now; $i < $now + $days_for_index; $i += 86400) {
				if (isset($cached_data['holidays'][Time::strftime('%Y-%m-%d', $i)])) {
					$return_data['calendar_holidays'] = array_merge($return_data['calendar_holidays'], $cached_data['holidays'][Time::strftime('%Y-%m-%d', $i)]);
				}
			}
		}

		if (!empty($eventOptions['include_birthdays'])) {
			// Happy Birthday, guys and gals!
			for ($i = $now; $i < $now + $days_for_index; $i += 86400) {
				$loop_date = Time::strftime('%Y-%m-%d', $i);

				if (isset($cached_data['birthdays'][$loop_date])) {
					foreach ($cached_data['birthdays'][$loop_date] as $index => $dummy) {
						$cached_data['birthdays'][Time::strftime('%Y-%m-%d', $i)][$index]['is_today'] = $loop_date === $today['date'];
					}
					$return_data['calendar_birthdays'] = array_merge($return_data['calendar_birthdays'], $cached_data['birthdays'][$loop_date]);
				}
			}
		}

		if (!empty($eventOptions['include_events'])) {
			$duplicates = [];

			for ($i = $now; $i < $now + $days_for_index; $i += 86400) {
				// Determine the date of the current loop step.
				$loop_date = Time::strftime('%Y-%m-%d', $i);

				// No events today? Check the next day.
				if (empty($cached_data['events'][$loop_date])) {
					continue;
				}

				// Loop through all events to add a few last-minute values.
				foreach ($cached_data['events'][$loop_date] as $ev => $event) {
					// Create a shortcut variable for easier access.
					$this_event = &$cached_data['events'][$loop_date][$ev];

					// Skip duplicates.
					if (isset($duplicates[$this_event['topic'] . $this_event['title']])) {
						unset($cached_data['events'][$loop_date][$ev]);

						continue;
					}

					$duplicates[$this_event['topic'] . $this_event['title']] = true;

					// Might be set to true afterwards, depending on the permissions.
					$this_event['can_edit'] = false;
					$this_event['is_today'] = $loop_date === $today['date'];
					$this_event['date'] = $loop_date;
				}

				if (!empty($cached_data['events'][$loop_date])) {
					$return_data['calendar_events'] = array_merge($return_data['calendar_events'], $cached_data['events'][$loop_date]);
				}
			}
		}

		// Mark the last item so that a list separator can be used in the template.
		for ($i = 0, $n = count($return_data['calendar_birthdays']); $i < $n; $i++) {
			$return_data['calendar_birthdays'][$i]['is_last'] = !isset($return_data['calendar_birthdays'][$i + 1]);
		}

		for ($i = 0, $n = count($return_data['calendar_events']); $i < $n; $i++) {
			$return_data['calendar_events'][$i]['is_last'] = !isset($return_data['calendar_events'][$i + 1]);
		}

		return [
			'data' => $return_data,
			'expires' => time() + 3600,
			'refresh_eval' => 'return \'' . Time::strftime('%Y%m%d', time()) . '\' != \\SMF\\Time::strftime(\'%Y%m%d\', time()) || (!empty(\\SMF\\Config::$modSettings[\'calendar_updated\']) && ' . time() . ' < \\SMF\\Config::$modSettings[\'calendar_updated\']);',
			'post_retri_eval' => '

				foreach ($cache_block[\'data\'][\'calendar_events\'] as $k => $event)
				{
					// Remove events that the user may not see or wants to ignore.
					if ((count(array_intersect(\\SMF\\User::$me->groups, $event[\'allowed_groups\'])) === 0 && !\\SMF\\User::$me->allowedTo(\'admin_forum\') && !empty($event[\'id_board\'])) || in_array($event[\'id_board\'], \\SMF\\User::$me->ignoreboards))
						unset($cache_block[\'data\'][\'calendar_events\'][$k]);
					else
					{
						// Whether the event can be edited depends on the permissions.
						$cache_block[\'data\'][\'calendar_events\'][$k][\'can_edit\'] = \\SMF\\User::$me->allowedTo(\'calendar_edit_any\') || ($event[\'poster\'] == \\SMF\\User::$me->id && \\SMF\\User::$me->allowedTo(\'calendar_edit_own\'));

						// The added session code makes this URL not cachable.
						$cache_block[\'data\'][\'calendar_events\'][$k][\'modify_href\'] = \\SMF\\Config::$scripturl . \'?action=\' . ($event[\'topic\'] == 0 ? \'calendar;sa=post;\' : \'post;msg=\' . $event[\'msg\'] . \';topic=\' . $event[\'topic\'] . \'.0;calendar;\') . \'eventid=\' . $event[\'id\'] . \';\' . \\SMF\\Utils::$context[\'session_var\'] . \'=\' . \\SMF\\Utils::$context[\'session_id\'];
					}
				}

				if (empty($params[0][\'include_holidays\']))
					$cache_block[\'data\'][\'calendar_holidays\'] = array();
				if (empty($params[0][\'include_birthdays\']))
					$cache_block[\'data\'][\'calendar_birthdays\'] = array();
				if (empty($params[0][\'include_events\']))
					$cache_block[\'data\'][\'calendar_events\'] = array();

				$cache_block[\'data\'][\'show_calendar\'] = !empty($cache_block[\'data\'][\'calendar_holidays\']) || !empty($cache_block[\'data\'][\'calendar_birthdays\']) || !empty($cache_block[\'data\'][\'calendar_events\']);',
		];
	}

	/**
	 * Makes sure the calendar post is valid.
	 */
	public static function validateEventPost(): void
	{
		if (!isset($_POST['deleteevent'])) {
			// The 2.1 way
			if (isset($_POST['start_date'])) {
				$d = date_parse(str_replace(',', '', self::convertDateToEnglish($_POST['start_date'])));

				if (!empty($d['error_count']) || !empty($d['warning_count'])) {
					ErrorHandler::fatalLang('invalid_date', false);
				}

				if (empty($d['year'])) {
					ErrorHandler::fatalLang('event_year_missing', false);
				}

				if (empty($d['month'])) {
					ErrorHandler::fatalLang('event_month_missing', false);
				}
			} elseif (isset($_POST['start_datetime'])) {
				$d = date_parse(str_replace(',', '', self::convertDateToEnglish($_POST['start_datetime'])));

				if (!empty($d['error_count']) || !empty($d['warning_count'])) {
					ErrorHandler::fatalLang('invalid_date', false);
				}

				if (empty($d['year'])) {
					ErrorHandler::fatalLang('event_year_missing', false);
				}

				if (empty($d['month'])) {
					ErrorHandler::fatalLang('event_month_missing', false);
				}
			}
			// The 2.0 way
			else {
				// No month?  No year?
				if (!isset($_POST['month'])) {
					ErrorHandler::fatalLang('event_month_missing', false);
				}

				if (!isset($_POST['year'])) {
					ErrorHandler::fatalLang('event_year_missing', false);
				}

				// Check the month and year...
				if ($_POST['month'] < 1 || $_POST['month'] > 12) {
					ErrorHandler::fatalLang('invalid_month', false);
				}

				if ($_POST['year'] < Config::$modSettings['cal_minyear'] || $_POST['year'] > Config::$modSettings['cal_maxyear']) {
					ErrorHandler::fatalLang('invalid_year', false);
				}
			}
		}

		// Make sure they're allowed to post...
		User::$me->isAllowedTo('calendar_post');

		// If they want to us to calculate an end date, make sure it will fit in an acceptable range.
		if (isset($_POST['span']) && (($_POST['span'] < 1) || (!empty(Config::$modSettings['cal_maxspan']) && $_POST['span'] > Config::$modSettings['cal_maxspan']))) {
			ErrorHandler::fatalLang('invalid_days_numb', false);
		}

		// There is no need to validate the following values if we are just deleting the event.
		if (!isset($_POST['deleteevent'])) {
			// If we're doing things the 2.0 way, check the day
			if (empty($_POST['start_date']) && empty($_POST['start_datetime'])) {
				// No day?
				if (!isset($_POST['day'])) {
					ErrorHandler::fatalLang('event_day_missing', false);
				}

				// Bad day?
				if (!checkdate($_POST['month'], $_POST['day'], $_POST['year'])) {
					ErrorHandler::fatalLang('invalid_date', false);
				}
			}

			if (!isset($_POST['evtitle'])) {
				if (!isset($_POST['subject'])) {
					ErrorHandler::fatalLang('event_title_missing', false);
				}

				$_POST['evtitle'] = $_POST['subject'];
			}

			// No title?
			if (Utils::htmlTrim($_POST['evtitle']) === '') {
				ErrorHandler::fatalLang('event_title_missing', false);
			}

			if (Utils::entityStrlen($_POST['evtitle']) > 100) {
				$_POST['evtitle'] = Utils::entitySubstr($_POST['evtitle'], 0, 100);
			}

			$_POST['evtitle'] = str_replace(';', '', $_POST['evtitle']);
		}
	}

	/**
	 * Get the event's poster.
	 *
	 * @param int $event_id The ID of the event
	 * @return int|bool The ID of the poster or false if the event was not found
	 */
	public static function getEventPoster($event_id): int|bool
	{
		// A simple database query, how hard can that be?
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}calendar
			WHERE id_event = {int:id_event}
			LIMIT 1',
			[
				'id_event' => $event_id,
			],
		);

		// No results, return false.
		if (Db::$db->num_rows($request) === 0) {
			return false;
		}

		// Grab the results and return.
		list($poster) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $poster;
	}

	/**
	 * Gets all of the holidays for the listing
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of holidays, each of which is an array containing the id, year, month, day and title of the holiday
	 */
	public static function list_getHolidays($start, $items_per_page, $sort): array
	{
		$request = Db::$db->query(
			'',
			'SELECT id_holiday, YEAR(event_date) AS year, MONTH(event_date) AS month, DAYOFMONTH(event_date) AS day, title
			FROM {db_prefix}calendar_holidays
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			[
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			],
		);
		$holidays = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$holidays[] = $row;
		}
		Db::$db->free_result($request);

		return $holidays;
	}

	/**
	 * Helper function to get the total number of holidays
	 *
	 * @return int The total number of holidays
	 */
	public static function list_getNumHolidays(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}calendar_holidays',
			[
			],
		);
		list($num_items) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $num_items;
	}

	/**
	 * Remove a holiday from the calendar
	 *
	 * @param array $holiday_ids An array of IDs of holidays to delete
	 */
	public static function removeHolidays($holiday_ids): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}calendar_holidays
			WHERE id_holiday IN ({array_int:id_holiday})',
			[
				'id_holiday' => $holiday_ids,
			],
		);

		Config::updateModSettings([
			'calendar_updated' => time(),
		]);
	}

	/**
	 * Helper function to convert date string to english
	 * so that date_parse can parse the date
	 *
	 * @param string $date A localized date string
	 * @return string English date string
	 */
	public static function convertDateToEnglish($date): string
	{
		if (User::$me->language == 'english') {
			return $date;
		}

		$replacements = array_combine(array_map('strtolower', Lang::$txt['months_titles']), [
			'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December',
		]);
		$replacements += array_combine(array_map('strtolower', Lang::$txt['months_short']), [
			'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
			'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
		]);
		$replacements += array_combine(array_map('strtolower', Lang::$txt['days']), [
			'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
		]);
		$replacements += array_combine(array_map('strtolower', Lang::$txt['days_short']), [
			'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat',
		]);
		// Find all possible variants of AM and PM for this language.
		$replacements[strtolower(Lang::$txt['time_am'])] = 'AM';
		$replacements[strtolower(Lang::$txt['time_pm'])] = 'PM';

		if (($am = Time::strftime('%p', strtotime('01:00:00'))) !== 'p' && $am !== false) {
			$replacements[strtolower($am)] = 'AM';
			$replacements[strtolower(Time::strftime('%p', strtotime('23:00:00')))] = 'PM';
		}

		if (($am = Time::strftime('%P', strtotime('01:00:00'))) !== 'P' && $am !== false) {
			$replacements[strtolower($am)] = 'AM';
			$replacements[strtolower(Time::strftime('%P', strtotime('23:00:00')))] = 'PM';
		}

		return strtr(strtolower($date), $replacements);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if ($_GET['action'] === 'clock') {
			$this->subaction = 'clock';
		} elseif (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}

		if ($this->subaction === 'clock') {
			return;
		}

		// Permissions, permissions, permissions.
		User::$me->isAllowedTo('calendar_view');

		// Some global template resources.
		Utils::$context['calendar_resources'] = [
			'min_year' => Config::$modSettings['cal_minyear'],
			'max_year' => Config::$modSettings['cal_maxyear'],
		];
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Calendar::exportStatic')) {
	Calendar::exportStatic();
}

?>