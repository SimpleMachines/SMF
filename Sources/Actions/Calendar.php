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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionTrait;
use SMF\Board;
use SMF\BrowserDetector;
use SMF\Cache\CacheApi;
use SMF\Calendar\Birthday;
use SMF\Calendar\Event;
use SMF\Calendar\EventOccurrence;
use SMF\Calendar\Holiday;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\TimeInterval;
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
		Theme::loadJavaScriptFile('calendar.js', ['defer' => true], 'smf_calendar');

		// Did they specify an individual event ID? If so, let's splice the year/month in to what we would otherwise be doing.
		if (isset($_GET['event']) && !isset($_REQUEST['start_date'])) {
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
			$isValid = checkdate((int) $curPage['month'], (int) $curPage['day'], (int) $curPage['year']);

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
			Utils::$context['calendar_buttons']['post_event'] = [
				'text' => 'calendar_post_event',
				'url' => Config::$scripturl . '?action=calendar;sa=post;month=' . Utils::$context['current_month'] . ';year=' . Utils::$context['current_year'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			];
		}

		if (!empty(Config::$modSettings['cal_export']) && !User::$me->possibly_robot) {
			$webcal_url = Config::$scripturl . '?action=calendar;sa=ical' . (!User::$me->is_guest ? ';u=' . User::$me->id . ';token=' . $this->createToken(User::$me) : '');

			if (BrowserDetector::isBrowser('safari') || BrowserDetector::isBrowser('iphone')) {
				$webcal_url = preg_replace('/^https?/', 'webcal', $webcal_url);
			} else {
				$webcal_url = 'javascript:navigator.clipboard.writeText(' . Utils::escapeJavaScript($webcal_url) . ');alert(' . Utils::escapeJavaScript(Lang::$txt['calendar_subscribe_url_copied']) . ')';
			}

			$ics_url = Config::$scripturl . '?action=calendar;sa=ical';

			switch (Utils::$context['calendar_view']) {
				case 'viewmonth':
					$ics_url .= ';start_date=' . $start_object->format('Y-m-01');
					$ics_url .= ';duration=P1M';
					break;

				case 'viewweek':
					$s = clone $start_object;

					while (($s->format('N') % 7) > $calendarOptions['start_day']) {
						$s->modify('-1 day');
					}

					$ics_url .= ';start_date=' . $s->format('Y-m-d');
					$ics_url .= ';duration=P7D';
					break;

				default:
					$ics_url .= ';start_date=' . $start_object->format('Y-m-d');
					$ics_url .= ';duration=' . (string) TimeInterval::createFromDateInterval($start_object->diff($end_object));
					break;
			}

			Lang::$txt[''] = '';

			Utils::$context['calendar_buttons']['cal_export'] = [
				'text' => '',
				'class' => 'main_icons feed',
				'custom' => 'title="' . Lang::getTxt('calendar_subscribe') . '"',
				'url' => $ics_url,
				'sub_buttons' => [
					'subscribe' => [
						'text' => 'calendar_subscribe',
						'url' => $webcal_url,
					],
					'download' => [
						'text' => 'calendar_download',
						'url' => $ics_url,
					],
				],
			];
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
			if (!in_array($_POST['link_to'] ?? '', ['board', 'topic'])) {
				self::validateEventPost();
			}

			// If you're not allowed to edit any events, you have to be the poster.
			if ($_REQUEST['eventid'] > 0 && !User::$me->allowedTo('calendar_edit_any')) {
				User::$me->isAllowedTo('calendar_edit_' . (!empty(User::$me->id) && self::getEventPoster($_REQUEST['eventid']) == User::$me->id ? 'own' : 'any'));
			}

			// New - and directing?
			if (in_array($_POST['link_to'] ?? '', ['board', 'topic']) || empty(Config::$modSettings['cal_allow_unlinked'])) {
				$_REQUEST['calendar'] = 1;

				if (empty($_POST['topic'])) {
					unset($_POST['topic']);
				} elseif (isset($_POST['link_to']) && $_POST['link_to'] === 'topic') {
					$_REQUEST['msg'] = Topic::load((int) $_POST['topic'])->id_first_msg;
				}

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
				];
				Event::create($eventOptions);
			}
			// Deleting...
			elseif (isset($_REQUEST['deleteevent'])) {
				if (isset($_REQUEST['recurrenceid'])) {
					EventOccurrence::remove($_REQUEST['eventid'], $_REQUEST['recurrenceid'], !empty($_REQUEST['affects_future']));
				} else {
					Event::remove($_REQUEST['eventid']);
				}
			}
			// ... or just update it?
			else {
				$eventOptions = [
					'title' => Utils::entitySubstr($_REQUEST['evtitle'], 0, 100),
					'location' => Utils::entitySubstr($_REQUEST['event_location'], 0, 255),
				];

				if (!empty($_REQUEST['recurrenceid'])) {
					$eventOptions['recurrenceid'] = $_REQUEST['recurrenceid'];
				}

				if (!empty($_REQUEST['affects_future'])) {
					$eventOptions['affects_future'] = $_REQUEST['affects_future'];
				}

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
			Utils::$context['event']->selected_occurrence = Utils::$context['event']->getFirstOccurrence();
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

			if (isset($_REQUEST['recurrenceid'])) {
				$selected_occurrence = Utils::$context['event']->getOccurrence($_REQUEST['recurrenceid']);
			}

			if (empty($selected_occurrence)) {
				$selected_occurrence = Utils::$context['event']->getFirstOccurrence();
			}

			Utils::$context['event']->selected_occurrence = $selected_occurrence;
		}

		// An all day event? Set up some nice defaults in case the user wants to change that
		if (Utils::$context['event']->allday) {
			$now = Time::create('now');
			Utils::$context['event']->selected_occurrence->tz = User::getTimezone();
			Utils::$context['event']->selected_occurrence->start->modify($now->format('H:i:s'));
			Utils::$context['event']->selected_occurrence->duration = new TimeInterval('PT' . ($now->format('H') < 23 ? '1H' : (59 - $now->format('i')) . 'M'));
		}

		// Need this so the user can select a timezone for the event.
		Utils::$context['all_timezones'] = TimeZone::list(Utils::$context['event']->start_datetime);

		// If the event's timezone is not in SMF's standard list of time zones, try to fix it.
		Utils::$context['event']->selected_occurrence->fixTimezone();

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

		Theme::loadTemplate('EventEditor');
		Theme::addJavaScriptVar('monthly_byday_items', (string) (count(Utils::$context['event']->byday_items) - 1));
		Theme::loadJavaScriptFile('event.js', ['defer' => true], 'smf_event');
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

		$file = [
			'mime_type' => 'text/calendar',
			'expires' => time() + 3600,
			// Will be changed below.
			'mtime' => 0,
			// Will be changed below.
			'filename' => 'event.ics',
			// More will be added below.
			'content' => [
				'BEGIN:VCALENDAR',
				'METHOD:PUBLISH',
				'PRODID:-//SimpleMachines//' . SMF_FULL_VERSION . '//EN',
				'VERSION:2.0',
			],
		];

		if (isset($_REQUEST['eventid'])) {
			// Load up the event in question and check it is valid.
			$event = current(Event::load((int) $_REQUEST['eventid']));

			if (!($event instanceof Event)) {
				ErrorHandler::fatalLang('no_access', false);
			}

			// Was a specific occurrence requested, or the event in general?
			if (
				isset($_REQUEST['recurrenceid'])
				&& ($occurrence = $event->getOccurrence($_REQUEST['recurrenceid'])) !== false
			) {
				$file['content'][] = $occurrence->export();
			} else {
				$file['content'][] = $event->export();
			}

			$file['filename'] = $event->title . '.ics';
			$file['mtime'] = $event->modified_time;
		} else {
			$this->authenticateForExport();

			// Get all the visible events within a date range.
			if (isset($_REQUEST['start_date'])) {
				$low_date = @(new Time($_REQUEST['start_date']));
			}

			if (!isset($low_date)) {
				$low_date = new Time('now');
				$low_date->setDate((int) $low_date->format('Y'), (int) $low_date->format('m'), 1);
			}

			if (isset($_REQUEST['duration'])) {
				$duration = @(new TimeInterval($_REQUEST['duration']));
			}

			if (!isset($duration)) {
				$duration = new TimeInterval('P3M');
			}

			$high_date = (clone $low_date)->add($duration);

			$full_event_uids = [];

			foreach (Event::getOccurrencesInRange($low_date->format('Y-m-d'), $high_date->format('Y-m-d'), true) as $occurrence) {
				$event = $occurrence->getParentEvent();

				// Skip if we already exported the full event.
				if (in_array($event->uid, $full_event_uids)) {
					continue;
				}

				if (
					// If there was no requested start date, export the full event.
					!isset($_REQUEST['start_date'])
					// Or if all occurrences are visible, export the full event.
					|| (
						$event->start >= $low_date
						&& $event->getRecurrenceEnd() <= $high_date
					)
				) {
					$file['content'][] = $event->export();
					$full_event_ids[] = $event->uid;
				}
				// Otherwise, export just this occurrence.
				else {
					$file['content'][] = $occurrence->export();
				}

				$file['mtime'] = max($file['mtime'], $event->modified_time);
			}

			$file['filename'] = implode(' ', [Utils::$context['forum_name'], Lang::$txt['events'], $low_date->format('Y-m-d'), $high_date->format('Y-m-d')]) . '.ics';
		}

		$file['content'][] = 'END:VCALENDAR';

		// RFC 5545 requires "\r\n", not just "\n".
		$file['content'] = implode("\r\n", $file['content']);

		$file['size'] = strlen($file['content']);

		// Send it.
		Utils::emitFile($file);
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
	 * Get all birthdays within the given time range.
	 * finds all the birthdays in the specified range of days.
	 * works with birthdays set for no year, or any other year, and respects month and year boundaries.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @return array An array of days, each of which is an array of birthday information for the context
	 */
	public static function getBirthdayRange(string $low_date, string $high_date): array
	{
		$birthdays = [];
		$high_date = (new \DateTimeImmutable($high_date . ' +1 day'))->format('Y-m-d');

		foreach(Birthday::getOccurrencesInRange($low_date, $high_date) as $occurrence) {
			$birthdays[$occurrence->start->format('Y-m-d')][$occurrence->member] = $occurrence;
		}

		ksort($birthdays);

		// Set is_last, so the themes know when to stop placing separators.
		foreach ($birthdays as $date => $bdays) {
			ksort($birthdays[$date]);
			$birthdays[$date][array_key_last($birthdays[$date])]['is_last'] = true;
		}

		return $birthdays;
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
	public static function getEventRange(string $low_date, string $high_date, bool $use_permissions = true): array
	{
		$occurrences = [];

		$one_day = new \DateInterval('P1D');
		$tz = new \DateTimeZone(User::getTimezone());
		$high_date = (new \DateTimeImmutable($high_date . ' +1 day'))->format('Y-m-d');

		foreach (Event::getOccurrencesInRange($low_date, $high_date, $use_permissions) as $occurrence) {
			$cal_date = new Time($occurrence->start_date_local, $tz);

			while (
				$cal_date->getTimestamp() < $occurrence->end->getTimestamp()
				&& $cal_date->format('Y-m-d') < $high_date
			) {
				$occurrences[$cal_date->format('Y-m-d')][] = $occurrence;
				$cal_date->add($one_day);
			}
		}

		foreach ($occurrences as $mday => $array) {
			$occurrences[$mday][count($array) - 1]['is_last'] = true;
		}

		ksort($occurrences);

		return $occurrences;
	}

	/**
	 * Get all holidays within the given time range.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @return array An array of days, which are all arrays of holiday names.
	 */
	public static function getHolidayRange(string $low_date, string $high_date): array
	{
		$holidays = [];
		$high_date = (new \DateTimeImmutable($high_date . ' +1 day'))->format('Y-m-d');

		foreach(Holiday::getOccurrencesInRange($low_date, $high_date) as $occurrence) {
			$holidays[$occurrence->start->format('Y-m-d')][] = $occurrence;
		}

		ksort($holidays);

		return $holidays;
	}

	/**
	 * Does permission checks to see if the current user can link the current
	 * topic to a calendar event.
	 *
	 * To succeed, the following conditions must be met:
	 *
	 * 1. The calendar must be enabled.
	 *
	 * 2. If an event is passed to the $event parameter, the current user must
	 *    be able to edit that event. Otherwise, the current user must be able
	 *    to create new events.
	 *
	 * 3. There must be a current topic (i.e. Topic::$topic_id must be set).
	 *
	 * 4. The current user must be able to edit the first post in the current
	 *    topic.
	 *
	 * @param bool $trigger_error Whether to trigger an error if the user cannot
	 *    link an event to this topic. Default: true.
	 * @param ?Event $event The event that the user wants to link to the current
	 *    topic, or null if the user wants to create a new event.
	 * @return bool Whether the user can link an event to the current topic.
	 */
	public static function canLinkEvent(bool $trigger_error = true, ?Event $event = null): bool
	{
		// Is the calendar enabled?
		if (empty(Config::$modSettings['cal_enabled'])) {
			if ($trigger_error) {
				ErrorHandler::fatalLang('calendar_off', false);
			}

			return false;
		}

		// Can the user create or edit the event?
		$perm = !isset($event) ? 'calendar_post' : ($event->member === User::$me->id ? 'calendar_edit_own' : 'calendar_edit_any');

		if (!User::$me->allowedTo($perm)) {
			if ($trigger_error) {
				User::$me->isAllowedTo($perm);
			}

			return false;
		}

		// Are we in a topic?
		if (empty(Topic::$topic_id)) {
			if ($trigger_error) {
				ErrorHandler::fatalLang('missing_topic_id', false);
			}

			return false;
		}

		// Don't let guests edit the posts of other guests.
		if (User::$me->is_guest) {
			if ($trigger_error) {
				ErrorHandler::fatalLang('not_your_topic', false);
			}

			return false;
		}

		// Linking an event counts as modifying the first post.
		Topic::load();

		$perm = User::$me->started ? ['modify_own', 'modify_any'] : 'modify_any';

		if (!User::$me->allowedTo($perm, Topic::$info->id_board, true)) {
			if ($trigger_error) {
				User::$me->isAllowedTo($perm, Topic::$info->id_board, true);
			}

			return false;
		}

		return true;
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
	 * @return array A large array containing all the information needed to show a calendar grid for the given month
	 */
	public static function getCalendarGrid(string $selected_date, array $calendarOptions, bool $is_previous = false): array
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
			'iso_start_date' => $selected_object->format('Y-m-d'),
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
		$nShift = (int) $month_info['first_day']['day_of_week'];

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

		return $calendarGrid;
	}

	/**
	 * Returns the information needed to show a calendar for the given week.
	 *
	 * @param string $selected_date A date in YYYY-MM-DD format
	 * @param array $calendarOptions An array of calendar options
	 * @return array An array of information needed to display the grid for a single week on the calendar
	 */
	public static function getCalendarWeek(string $selected_date, array $calendarOptions): array
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
			'iso_start_date' => $selected_object->format('Y-m-d'),
			'show_events' => $calendarOptions['show_events'],
			'show_holidays' => $calendarOptions['show_holidays'],
			'show_birthdays' => $calendarOptions['show_birthdays'],
		];

		// Fetch the arrays for birthdays, posted events, and holidays.
		$bday = $calendarOptions['show_birthdays'] ? self::getBirthdayRange($first_day_object->format('Y-m-d'), $last_day_object->format('Y-m-d')) : [];
		$events = $calendarOptions['show_events'] ? self::getEventRange($first_day_object->format('Y-m-d'), $last_day_object->format('Y-m-d')) : [];
		$holidays = $calendarOptions['show_holidays'] ? self::getHolidayRange($first_day_object->format('Y-m-d'), $last_day_object->format('Y-m-d')) : [];

		$calendarGrid['week_title'] = Lang::getTxt('calendar_week_beginning', ['date' => $first_day_object->format(Time::getDateFormat())]);

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
	public static function getCalendarList(string $start_date, string $end_date, array $calendarOptions): array
	{
		// DateTime objects make life easier
		$start_object = new Time($start_date . ' ' . User::getTimezone());
		$end_object = new Time($end_date . ' ' . User::getTimezone());

		$calendarGrid = [
			'start_date' => $start_object->format(Time::getDateFormat()),
			'start_year' => $start_object->format('Y'),
			'start_month' => $start_object->format('m'),
			'start_day' => $start_object->format('d'),
			'iso_start_date' => $start_object->format('Y-m-d'),
			'end_date' => $end_object->format(Time::getDateFormat()),
			'end_year' => $end_object->format('Y'),
			'end_month' => $end_object->format('m'),
			'end_day' => $end_object->format('d'),
			'iso_end_date' => $end_object->format('Y-m-d'),
		];

		$calendarGrid['birthdays'] = $calendarOptions['show_birthdays'] ? self::getBirthdayRange($start_date, $end_date) : [];
		$calendarGrid['holidays'] = $calendarOptions['show_holidays'] ? self::getHolidayRange($start_date, $end_date) : [];
		$calendarGrid['events'] = $calendarOptions['show_events'] ? self::getEventRange($start_date, $end_date) : [];

		// Get rid of duplicate events.
		// This does not get rid of SEPARATE occurrences of a recurring event.
		// Instead, it gets rid of duplicates of the SAME occurrence, which can
		// happen when the event duration extends beyond midnight.
		$temp = [];

		foreach ($calendarGrid['events'] as $date => $date_events) {
			foreach ($date_events as $event_key => $event_val) {
				if (in_array($event_val['id'] . ' ' . $event_val['start']->format('c'), $temp)) {
					unset($calendarGrid['events'][$date][$event_key]);

					if (empty($calendarGrid['events'][$date])) {
						unset($calendarGrid['events'][$date]);
					}
				} else {
					$temp[] = $event_val['id'] . ' ' . $event_val['start']->format('c');
				}
			}
		}

		// Give birthdays and holidays a friendly format, without the year.
		$date_format = Time::getShortDateFormat();

		foreach (['birthdays', 'holidays'] as $type) {
			foreach ($calendarGrid[$type] as $date => $date_content) {
				// Make sure to apply no offsets
				$date_local = Time::create($date)->format($date_format);

				$calendarGrid[$type][$date]['date_local'] = $date_local;
			}
		}

		return $calendarGrid;
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
	public static function cache_getOffsetIndependentEvents(array $eventOptions): array
	{
		$days_to_index = $eventOptions['num_days_shown'];

		$low_date = Time::strftime('%Y-%m-%d', time() - 24 * 3600);
		$high_date = Time::strftime('%Y-%m-%d', (int) (time() + $days_to_index * 24 * 3600));

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
	public static function cache_getRecentEvents(array $eventOptions): array
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
					$return_data['calendar_holidays'] = array_merge($return_data['calendar_holidays'], (array) $cached_data['holidays'][Time::strftime('%Y-%m-%d', $i)]);
				}
			}
		}

		if (!empty($eventOptions['include_birthdays'])) {
			// Happy Birthday, guys and gals!
			for ($i = $now; $i < $now + $days_for_index; $i += 86400) {
				$loop_date = Time::strftime('%Y-%m-%d', $i);

				if (isset($cached_data['birthdays'][$loop_date])) {
					foreach ((array) $cached_data['birthdays'][$loop_date] as $index => $dummy) {
						$cached_data['birthdays'][Time::strftime('%Y-%m-%d', $i)][$index]['is_today'] = $loop_date === $today['date'];
					}
					$return_data['calendar_birthdays'] = array_merge($return_data['calendar_birthdays'], (array) $cached_data['birthdays'][$loop_date]);
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
				foreach ((array) $cached_data['events'][$loop_date] as $ev => $event) {
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
					$return_data['calendar_events'] = array_merge($return_data['calendar_events'], (array) $cached_data['events'][$loop_date]);
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
	public static function getEventPoster(int $event_id): int|bool
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
	 * Backward compatibility wrapper for Holiday::remove().
	 *
	 * @param array $holiday_ids An array of IDs of holidays to delete.
	 */
	public static function removeHolidays(array $holiday_ids): void
	{
		foreach ($holiday_ids as $holiday_id) {
			Holiday::remove($holiday_id);
		}
	}

	/**
	 * Helper function to convert date string to english
	 * so that date_parse can parse the date
	 *
	 * @param string $date A localized date string
	 * @return string English date string
	 */
	public static function convertDateToEnglish(string $date): string
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
		Lang::load('Calendar');

		if ($_GET['action'] === 'clock') {
			$this->subaction = 'clock';
		} elseif (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}

		if ($this->subaction === 'clock') {
			return;
		}

		// Special case for handling calendar subscriptions.
		if (
			User::$me->is_guest
			&& $this->subaction === 'ical'
			&& isset($_REQUEST['u'], $_REQUEST['token'])
		) {
			$user = current(User::load((int) $_REQUEST['u']));

			if (
				!($user instanceof User)
				|| !$user->allowedTo('calendar_view')
				|| $_REQUEST['token'] !== $this->createToken($user)
			) {
				exit;
			}
		}
		// Permissions, permissions, permissions.
		else {
			User::$me->isAllowedTo('calendar_view');
		}

		// Some global template resources.
		Utils::$context['calendar_resources'] = [
			'min_year' => Config::$modSettings['cal_minyear'],
			'max_year' => Config::$modSettings['cal_maxyear'],
		];
	}

	/**
	 * Generates an calendar subscription authentication token.
	 *
	 * @param User $user The member that this token is for.
	 * @return string The authentication token.
	 */
	protected function createToken(User $user): string
	{
		$token = hash_hmac('sha3-224', (string) $user->id, Config::getAuthSecret(), true);

		return strtr(base64_encode($token), ['+' => '_', '/' => '-', '=' => '']);
	}

	/**
	 * Validates the guest-supplided user ID and token combination, and loads
	 * the requested user if the token is valid.
	 *
	 * Does nothing if the user is already logged in.
	 */
	protected function authenticateForExport(): void
	{
		if (!User::$me->is_guest) {
			return;
		}

		if (!empty($_REQUEST['u']) && isset($_REQUEST['token'])) {
			$user = current(User::load((int) $_REQUEST['u']));

			if (($user instanceof User) && $_REQUEST['token'] === $this->createToken($user)) {
				User::setMe($user->id);
			}
		}
	}
}

?>