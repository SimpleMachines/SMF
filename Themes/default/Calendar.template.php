<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

/**
 * Our main calendar template, which encapsulates weeks and months.
 */
function template_main()
{
	global $context;

	// The main calendar wrapper.
	echo '<div id="calendar">';

	// Show the mini-blocks if they're enabled.
	if (empty($context['blocks_disabled']))
	{
		echo '
			<div id="month_grid">
				', template_show_month_grid('prev', true), '
				', template_show_month_grid('current', true), '
				', template_show_month_grid('next', true), '
			</div>
		';
	}

	// What view are we showing?
	if ($context['calendar_view'] == 'view_list')
	{
		echo '
			<div id="main_grid">
				', template_show_upcoming_list('main'), '
			</div>
		';
	}
	elseif ($context['calendar_view'] == 'view_week')
	{
		echo '
			<div id="main_grid">
				', template_show_week_grid('main'), '
			</div>
		';
	}
	else
	{
		echo '
			<div id="main_grid">
				', template_show_month_grid('main'), '
			</div>
		';
	}

	// Close our wrapper.
	echo '
	</div>';
}


/**
 * Display a list of upcoming events, birthdays, and holidays.
 *
 * @param string $grid_name The grid name
 * @return void|bool Returns false if the grid doesn't exist.
 */
function template_show_upcoming_list($grid_name)
{
	global $context, $scripturl, $txt, $modSettings;

	// Bail out if we have nothing to work with
	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	// Protect programmer sanity
	$calendar_data = &$context['calendar_grid_' . $grid_name];

	// Do we want a title?
	if (empty($calendar_data['disable_title']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext largetext">
					<a href="', $scripturl, '?action=calendar;viewlist;year=', $calendar_data['start_year'], ';month=', $calendar_data['start_month'], ';day=', $calendar_data['start_day'], '">', $txt['calendar_upcoming'], '</a>
				</h3>
			</div>';
	}

	// Give the user some controls to work with
	template_calendar_top($calendar_data);

	// First, list any events
	if (!empty($calendar_data['events']))
	{
		echo '
			<div>
				<div class="title_bar">
					<h3 class="titlebg">', str_replace(':', '', $txt['events']), '</h3>
				</div>
				<ul>';

		foreach ($calendar_data['events'] as $date => $date_events)
		{
			foreach ($date_events as $event)
			{
				echo '
					<li class="windowbg">
						<b class="event_title">', $event['link'], '</b>';

				if ($event['can_edit'])
					echo ' <a href="' . $event['modify_href'] . '"><span class="generic_icons calendar_modify" title="', $txt['calendar_edit'], '"></span></a>';

				if ($event['can_export'])
					echo ' <a href="' . $event['export_href'] . '"><span class="generic_icons calendar_export" title="', $txt['calendar_export'], '"></span></a>';

				echo '
						<br>';

				if (!empty($event['allday']))
				{
					echo '<time datetime="' . $event['start_iso_gmdate'] . '">', trim($event['start_date_local']), '</time>', ($event['start_date'] != $event['end_date']) ? ' &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">' . trim($event['end_date_local']) . '</time>' : '';
				}
				else
				{
					// Display event info relative to user's local timezone
					echo '<time datetime="' . $event['start_iso_gmdate'] . '">', trim($event['start_date_local']), ', ', trim($event['start_time_local']), '</time> &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">';

					if ($event['start_date_local'] != $event['end_date_local'])
						echo trim($event['end_date_local']) . ', ';

					echo trim($event['end_time_local']);

					// Display event info relative to original timezone
					if ($event['start_date_local'] . $event['start_time_local'] != $event['start_date_orig'] . $event['start_time_orig'])
					{
						echo '</time> (<time datetime="' . $event['start_iso_gmdate'] . '">';

						if ($event['start_date_orig'] != $event['start_date_local'] || $event['end_date_orig'] != $event['end_date_local'] || $event['start_date_orig'] != $event['end_date_orig'])
							echo trim($event['start_date_orig']), ', ';

						echo trim($event['start_time_orig']), '</time> &ndash; <time datetime="' . $event['end_iso_gmdate'] . '">';

						if ($event['start_date_orig'] != $event['end_date_orig'])
							echo trim($event['end_date_orig']) . ', ';

						echo trim($event['end_time_orig']), ' ', $event['tz_abbrev'], '</time>)';
					}
					// Event is scheduled in the user's own timezone? Let 'em know, just to avoid confusion
					else
						echo ' ', $event['tz_abbrev'], '</time>';
				}

				if (!empty($event['location']))
					echo '<br>', $event['location'];

				echo '
					</li>';
			}
		}

		echo '
				</ul>
			</div>';
	}

	// Next, list any birthdays
	if (!empty($calendar_data['birthdays']))
	{
		echo '
			<div>
				<div class="title_bar">
					<h3 class="titlebg">', str_replace(':', '', $txt['birthdays']), '</h3>
				</div>
				<div class="windowbg">';

		foreach ($calendar_data['birthdays'] as $date)
		{
			echo '
					<p class="inline">
						<b>', $date['date_local'], '</b>: ';

			unset($date['date_local']);

			$birthdays = array();

			foreach ($date as $member)
				$birthdays[] = '<a href="' . $scripturl . '?action=profile;u=' . $member['id'] . '">' . $member['name'] . (isset($member['age']) ? ' (' . $member['age'] . ')' : '') . '</a>';

			echo implode(', ', $birthdays);

			echo '
					</p>';
		}

		echo '
				</div>
			</div>';
	}

	// Finally, list any holidays
	if (!empty($calendar_data['holidays']))
	{
		echo '
			<div>
				<div class="title_bar">
					<h3 class="titlebg">', str_replace(':', '', $txt['calendar_prompt']), '</h3>
				</div>
				<div class="windowbg">
					<p class="inline holidays">';

		$holidays = array();

		foreach ($calendar_data['holidays'] as $date)
		{
			$date_local = $date['date_local'];
			unset($date['date_local']);

			foreach ($date as $holiday)
				$holidays[] = $holiday . ' (' . $date_local . ')';
		}

		echo implode(', ', $holidays);

		echo '</p>
				</div>
			</div>';
	}
}

/**
 * Display a monthly calendar grid.
 *
 * @param string $grid_name The grid name
 * @param bool $is_mini Is this a mini grid?
 * @return void|bool Returns false if the grid doesn't exist.
 */
function template_show_month_grid($grid_name, $is_mini = false)
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// If the grid doesn't exist, no point in proceeding.
	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	// A handy little pointer variable.
	$calendar_data = &$context['calendar_grid_' . $grid_name];

	// Some conditions for whether or not we should show the week links *here*.
	if (isset($calendar_data['show_week_links']) && ($calendar_data['show_week_links'] == 3 || (($calendar_data['show_week_links'] == 1 && $is_mini === true) || $calendar_data['show_week_links'] == 2 && $is_mini === false)))
		$show_week_links = true;
	else
		$show_week_links = false;

	// Assuming that we've not disabled it, show the title block!
	if (empty($calendar_data['disable_title']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext largetext">';

				// Previous Link: If we're showing prev / next and it's not a mini-calendar.
				if (empty($calendar_data['previous_calendar']['disabled']) && $calendar_data['show_next_prev'] && $is_mini === false)
				{
					echo '
						<span class="floatleft">
							<a href="', $calendar_data['previous_calendar']['href'], '">&#171;</a>
						</span>
					';
				}

				// Next Link: if we're showing prev / next and it's not a mini-calendar.
				if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'] && $is_mini === false)
				{
					echo '
						<span class="floatright">
							<a href="', $calendar_data['next_calendar']['href'], '">&#187;</a>
						</span>
					';
				}

				// Arguably the most exciting part, the title!
				echo '<a href="', $scripturl, '?action=calendar;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], '">', $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'], '</a>';

				echo '
				</h3>
			</div>
		';
	}

	// Show the controls on main grids
	if ($is_mini === false)
		template_calendar_top($calendar_data);

	// Finally, the main calendar table.
	echo '<table class="calendar_table">';

	// Show each day of the week.
	if (empty($calendar_data['disable_day_titles']))
	{
		echo '<tr>';

		// If we're showing week links, there's an extra column ahead of the week links, so let's think ahead and be prepared!
		if ($show_week_links === true)
			echo '<th>&nbsp;</th>';

		// Now, loop through each actual day of the week.
		foreach ($calendar_data['week_days'] as $day)
		{
			echo '<th class="days" scope="col">', !empty($calendar_data['short_day_titles']) || $is_mini === true ? $txt['days_short'][$day] : $txt['days'][$day], '</th>';
		}

		echo '</tr>';
	}

	// Our looping begins on a per-week basis.
	foreach ($calendar_data['weeks'] as $week)
	{

		// Some useful looping variables.
		$current_month_started = false;
		$count = 1;
		$final_count = 1;

		echo '<tr class="days_wrapper">';

		// This is where we add the actual week link, if enabled on this location.
		if ($show_week_links === true)
		{
			echo '
				<td class="windowbg2 weeks">
					<a href="', $scripturl, '?action=calendar;viewweek;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $week['days'][0]['day'], '" title="', $txt['calendar_view_week'], '">&#187;</a>
				</td>
			';
		}

		// Now loop through each day in the week we're on.
		foreach ($week['days'] as $day)
		{
			// What classes should each day inherit? Day is default.
			$classes = array('days');
			if (!empty($day['day']))
			{

				// Default Classes (either compact or comfortable and either calendar_today or windowbg).
				$classes[] = !empty($calendar_data['size']) && $calendar_data['size'] == 'small' ? 'compact' : 'comfortable';
				$classes[] = !empty($day['is_today']) ? 'calendar_today' : 'windowbg';

				// Additional classes are given for events, holidays, and birthdays.
				if (!empty($day['events']) && !empty($calendar_data['highlight']['events']))
				{
					if ($is_mini === true && in_array($calendar_data['highlight']['events'], array(1, 3)))
						$classes[] = 'events';
					elseif ($is_mini === false && in_array($calendar_data['highlight']['events'], array(2, 3)))
						$classes[] = 'events';
				}
				if (!empty($day['holidays']) && !empty($calendar_data['highlight']['holidays']))
				{
					if ($is_mini === true && in_array($calendar_data['highlight']['holidays'], array(1, 3)))
						$classes[] = 'holidays';
					elseif ($is_mini === false && in_array($calendar_data['highlight']['holidays'], array(2, 3)))
						$classes[] = 'holidays';
				}
				if (!empty($day['birthdays']) && !empty($calendar_data['highlight']['birthdays']))
				{
					if ($is_mini === true && in_array($calendar_data['highlight']['birthdays'], array(1, 3)))
						$classes[] = 'birthdays';
					elseif ($is_mini === false && in_array($calendar_data['highlight']['birthdays'], array(2, 3)))
						$classes[] = 'birthdays';
				}
			}
			else
			{
				// Default Classes (either compact or comfortable and disabled).
				$classes[] = !empty($calendar_data['size']) && $calendar_data['size'] == 'small' ? 'compact' : 'comfortable';
				$classes[] = 'disabled';
			}

			// Now, implode the classes for each day.
			echo '<td class="', implode(' ', $classes), '">';

			// If it's within this current month, go ahead and begin.
			if (!empty($day['day']))
			{

				// If it's the first day of this month and not a mini-calendar, we'll add the month title - whether short or full.
				$title_prefix = !empty($day['is_first_of_month']) && $context['current_month'] == $calendar_data['current_month'] && $is_mini === false ? (!empty($calendar_data['short_month_titles']) ? $txt['months_short'][$calendar_data['current_month']] . ' ' : $txt['months_titles'][$calendar_data['current_month']] . ' ') : '';

				// The actual day number - be it a link, or just plain old text!
				if (!empty($modSettings['cal_daysaslink']) && $context['can_post'])
					echo '<a href="', $scripturl, '?action=calendar;sa=post;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="day_text">', $title_prefix, $day['day'], '</span></a>';
				else
					echo '<span class="day_text">', $title_prefix, $day['day'], '</span>';

				// A lot of stuff, we're not showing on mini-calendars to conserve space.
				if ($is_mini === false)
				{
					// Holidays are always fun, let's show them!
					if (!empty($day['holidays']))
						echo '<div class="smalltext holiday"><span>', $txt['calendar_prompt'], '</span> ', implode(', ', $day['holidays']), '</div>';

					// Happy Birthday Dear, Member!
					if (!empty($day['birthdays']))
					{
						echo '
							<div class="smalltext">
								<span class="birthday">', $txt['birthdays'], '</span> ';

						/* Each of the birthdays has:
							id, name (person), age (if they have one set?), and is_last. (last in list?) */
						$use_js_hide = empty($context['show_all_birthdays']) && count($day['birthdays']) > 15;
						$birthday_count = 0;
						foreach ($day['birthdays'] as $member)
						{
							echo '<a href="', $scripturl, '?action=profile;u=', $member['id'], '"><span class="fix_rtl_names">', $member['name'], '</span>', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] || ($count == 10 && $use_js_hide) ? '' : ', ';

							// 9...10! Let's stop there.
							if ($birthday_count == 10 && $use_js_hide)
								// !!TODO - Inline CSS and JavaScript should be moved.
								echo '<span class="hidelink" id="bdhidelink_', $day['day'], '">...<br><a href="', $scripturl, '?action=calendar;month=', $calendar_data['current_month'], ';year=', $calendar_data['current_year'], ';showbd" onclick="document.getElementById(\'bdhide_', $day['day'], '\').style.display = \'\'; document.getElementById(\'bdhidelink_', $day['day'], '\').style.display = \'none\'; return false;">(', sprintf($txt['calendar_click_all'], count($day['birthdays'])), ')</a></span><span id="bdhide_', $day['day'], '" style="display: none;">, ';

							++$birthday_count;
						}
						if ($use_js_hide)
							echo '</span>';

						echo '</div>';
					}

					// Any special posted events?
					if (!empty($day['events']))
					{
						// Sort events by start time (all day events will be listed first)
						uasort($day['events'], function($a, $b) {
						    if ($a['start_timestamp'] == $b['start_timestamp'])
						        return 0;
						    return ($a['start_timestamp'] < $b['start_timestamp']) ? -1 : 1;
						});

						echo '
							<div class="smalltext lefttext">
								<span class="event">', $txt['events'], '</span><br>';

						/* The events are made up of:
							title, href, is_last, can_edit (are they allowed to?), and modify_href. */
						foreach ($day['events'] as $event)
						{
							$event_icons_needed = ($event['can_edit'] || $event['can_export']) ? true : false;

							echo '<div class="event_wrapper', $event['starts_today'] == true ? ' event_starts_today' : '', $event['ends_today'] == true ? ' event_ends_today' : '', $event['allday'] == true ? ' allday' : '', $event['is_selected'] ? ' sel_event' : '', '">', $event['link'], '<br><span class="event_time', empty($event_icons_needed) ? ' floatright' : '', '">';

							if (!empty($event['start_time_local']) && $event['starts_today'] == true)
								echo trim(str_replace(':00 ', ' ', $event['start_time_local']));
							elseif (!empty($event['end_time_local']) && $event['ends_today'] == true)
								echo strtolower($txt['ends']), ' ', trim(str_replace(':00 ', ' ', $event['end_time_local']));
							elseif (!empty($event['allday']))
								echo $txt['calendar_allday'];

							echo '</span>';

							if (!empty($event['location']))
								echo '<br><span class="event_location', empty($event_icons_needed) ? ' floatright' : '', '">' . $event['location'] . '</span>';

							if ($event['can_edit'] || $event['can_export'])
							{
								echo ' <span class="modify_event_links">';

								// If they can edit the event, show an icon they can click on....
								if ($event['can_edit'])
								{
									echo '
										<a class="modify_event" href="', $event['modify_href'], '">
											<span class="generic_icons calendar_modify" title="', $txt['calendar_edit'], '"></span>
										</a>';
								}
								// Exporting!
								if ($event['can_export'])
								{
									echo '
										<a class="modify_event" href="', $event['export_href'], '">
											<span class="generic_icons calendar_export" title="', $txt['calendar_export'], '"></span>
										</a>';
								}

								echo '</span><br class="clear">';
							}

							echo '</div>';
						}

						echo '</div>';
					}
				}
				$current_month_started = $count;
			}
			// Otherwise, assuming it's not a mini-calendar, we can show previous / next month days!
			elseif ($is_mini === false)
			{
				if (empty($current_month_started) && !empty($context['calendar_grid_prev']))
					echo '<a href="', $scripturl, '?action=calendar;year=', $context['calendar_grid_prev']['current_year'], ';month=', $context['calendar_grid_prev']['current_month'], '">', $context['calendar_grid_prev']['last_of_month'] - $calendar_data['shift']-- +1, '</a>';
				elseif (!empty($current_month_started) && !empty($context['calendar_grid_next']))
					echo '<a href="', $scripturl, '?action=calendar;year=', $context['calendar_grid_next']['current_year'], ';month=', $context['calendar_grid_next']['current_month'], '">', $current_month_started + 1 == $count ? (!empty($calendar_data['short_month_titles']) ? $txt['months_short'][$context['calendar_grid_next']['current_month']] . ' ' : $txt['months_titles'][$context['calendar_grid_next']['current_month']] . ' ') : '', $final_count++, '</a>';
			}

			// Close this day and increase var count.
			echo '</td>';
			++$count;
		}

		echo '</tr>';
	}

	// The end of our main table.
	echo '</table>';
}

/**
 * Shows a weekly grid
 *
 * @param string $grid_name The name of the grid
 * @return void|bool Returns false if the grid doesn't exist
 */
function template_show_week_grid($grid_name)
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	// We might have no reason to proceed, if the variable isn't there.
	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	// Handy pointer.
	$calendar_data = &$context['calendar_grid_' . $grid_name];

	// At the very least, we have one month. Possibly two, though.
	$iteration = 1;
	$num_months = count($calendar_data['months']);
	foreach ($calendar_data['months'] as $month_data)
	{
		// For our first iteration, we'll add a nice header!
		if ($iteration == 1)
		{
			echo '
				<div class="cat_bar">
					<h3 class="catbg centertext largetext">';
					// Previous Week Link...
					if (empty($calendar_data['previous_calendar']['disabled']) && !empty($calendar_data['show_next_prev']))
					{
						echo '
							<span class="floatleft">
								<a href="', $calendar_data['previous_week']['href'], '">&#171;</a>
							</span>
						';
					}

					// Next Week Link...
					if (empty($calendar_data['next_calendar']['disabled']) && !empty($calendar_data['show_next_prev']))
					{
						echo '
							<span class="floatright">
								<a href="', $calendar_data['next_week']['href'], '">&#187;</a>
							</span>';
					}

					// The Month Title + Week Number...
					if (!empty($calendar_data['week_title']))
							echo $calendar_data['week_title'];

					echo '
					</h3>
				</div>';

			// Show the controls
			template_calendar_top($calendar_data);
		}

		// Our actual month...
		echo '
			<div class="week_month_title">
				<a href="', $scripturl, '?action=calendar;month=', $month_data['current_month'], '">
					', $txt['months_titles'][$month_data['current_month']], '
				</a>
			</div>';

		// The main table grid for $this week.
		echo '
			<table class="table_grid calendar_week">
				<tr>
					<th class="days" scope="col">', $txt['calendar_day'], '</th>
					<th class="days" scope="col">', $txt['events'], '</th>
					<th class="days" scope="col">', $txt['calendar_prompt'], '</th>
					<th class="days" scope="col">', $txt['birthdays'], '</th>
				</tr>';
				// Each day of the week.
				foreach ($month_data['days'] as $day)
				{
					// How should we be highlighted or otherwise not...?
					$classes = array('days');
					$classes[] = !empty($calendar_data['size']) && $calendar_data['size'] == 'small' ? 'compact' : 'comfortable';
					$classes[] = !empty($day['is_today']) ? 'calendar_today' : 'windowbg';

					echo '
						<tr class="days_wrapper">
							<td class="', implode(' ', $classes), ' act_day">';
							// Should the day number be a link?
							if (!empty($modSettings['cal_daysaslink']) && $context['can_post'])
								echo '<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['days'][$day['day_of_week']], ' - ', $day['day'], '</a>';
							else
								echo $txt['days'][$day['day_of_week']], ' - ', $day['day'];

							echo '</td>
							<td class="', implode(' ', $classes), '', empty($day['events']) ? (' disabled' . ($context['can_post'] ? ' week_post' : '')) : ' events', ' event_col" data-css-prefix="' . $txt['events'] . ' ', (empty($day['events']) && empty($context['can_post'])) ? $txt['none'] : '', '">';
							// Show any events...
							if (!empty($day['events']))
							{
								// Sort events by start time (all day events will be listed first)
								uasort($day['events'], function($a, $b) {
								    if ($a['start_timestamp'] == $b['start_timestamp'])
								        return 0;
								    return ($a['start_timestamp'] < $b['start_timestamp']) ? -1 : 1;
								});

								foreach ($day['events'] as $event)
								{
									echo '<div class="event_wrapper">';

									$event_icons_needed = ($event['can_edit'] || $event['can_export']) ? true : false;

									echo $event['link'], '<br><span class="event_time', empty($event_icons_needed) ? ' floatright' : '', '">';

									if (!empty($event['start_time_local']))
										echo trim($event['start_time_local']), !empty($event['end_time_local']) ? ' &ndash; ' . trim($event['end_time_local']) : '';
									else
										echo $txt['calendar_allday'];

									echo '</span>';

									if (!empty($event['location']))
										echo '<br><span class="event_location', empty($event_icons_needed) ? ' floatright' : '', '">' . $event['location'] . '</span>';

									if (!empty($event_icons_needed))
									{
										echo ' <span class="modify_event_links">';

										// If they can edit the event, show a star they can click on....
										if (!empty($event['can_edit']))
										{
											echo '
												<a class="modify_event" href="', $event['modify_href'], '">
													<span class="generic_icons calendar_modify" title="', $txt['calendar_edit'], '"></span>
												</a>';
										}
										// Can we export? Sweet.
										if (!empty($event['can_export']))
										{
											echo '
												<a class="modify_event" href="', $event['export_href'], '">
													<span class="generic_icons calendar_export" title="', $txt['calendar_export'], '"></span>
												</a>';
										}

										echo '</span><br class="clear">';
									}

									echo '
									</div>';
								}
								if (!empty($context['can_post']))
								{
									echo '
									<div class="week_add_event">
										<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['calendar_post_event'], '</a>
									</div>
									<br class="clear">';
								}
							}
							else
							{
								if (!empty($context['can_post']))
								{
									echo '
										<div class="week_add_event">
											<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['calendar_post_event'], '</a>
										</div>';
								}
							}
							echo '</td>
							<td class="', implode(' ', $classes), !empty($day['holidays']) ? ' holidays' : ' disabled', ' holiday_col" data-css-prefix="' . $txt['calendar_prompt'] . ' ">';
							// Show any holidays!
							if (!empty($day['holidays']))
								echo implode('<br>', $day['holidays']);

							echo '</td>
							<td class="', implode(' ', $classes), '', !empty($day['birthdays']) ? ' birthdays' : ' disabled', ' birthday_col" data-css-prefix="' . $txt['birthdays'] . ' ">';
							// Show any birthdays...
							if (!empty($day['birthdays']))
							{
								foreach ($day['birthdays'] as $member)
								{
									echo '
										<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['name'], '</a>
										', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '
									', $member['is_last'] ? '' : '<br>';
								}
							}
							echo '</td>
						</tr>';
				}

				// Increase iteration for loop counting.
				++$iteration;

				echo '
			</table>';
	}
}

/**
 * Calendar controls under the title
 *
 * Creates the view selector (list, month, week), the date selector (either a
 * select menu or a date range chooser, depending on the circumstances), and the
 * "Post Event" button.
 *
 * @param array $calendar_data The data for the calendar grid that this is for
 */
function template_calendar_top($calendar_data)
{
	global $context, $scripturl, $txt;

	echo '
		<div class="calendar_top roundframe', empty($calendar_data['disable_title']) ? ' noup' : '', '">
			<div id="calendar_viewselector" class="buttonrow floatleft">
				<a href="', $scripturl, '?action=calendar;viewlist;year=', $context['current_year'], ';month=', $context['current_month'], ';day=', $context['current_day'], '" class="button', $context['calendar_view'] == 'view_list' ? ' active' : '', '">', $txt['calendar_list'], '</a>
				<a href="', $scripturl, '?action=calendar;viewmonth;year=', $context['current_year'], ';month=', $context['current_month'], '" class="button', $context['calendar_view'] == 'view_month' ? ' active' : '', '">', $txt['calendar_month'], '</a>
				<a href="', $scripturl, '?action=calendar;viewweek;year=', $context['current_year'], ';month=', $context['current_month'], ';day=', $context['current_day'], '" class="button', $context['calendar_view'] == 'view_week' ? ' active' : '', '">', $txt['calendar_week'], '</a>
			</div>
			', template_button_strip($context['calendar_buttons'], 'right');

	if ($context['calendar_view'] == 'view_list')
	{
		echo '
			<form action="', $scripturl, '?action=calendar;viewlist" id="calendar_range" method="post" accept-charset="', $context['character_set'], '">
				<input type="text" name="start_date" id="start_date" maxlength="10" value="', $calendar_data['start_date'], '" tabindex="', $context['tabindex']++, '" class="input_text date_input start" data-type="date">
				<span>', strtolower($txt['to']), '</span>
				<input type="text" name="end_date" id="end_date" maxlength="10" value="', $calendar_data['end_date'], '" tabindex="', $context['tabindex']++, '" class="input_text date_input end" data-type="date">
				<input type="submit" class="button_submit" style="float:none" id="view_button" value="', $txt['view'], '">
			</form>';
	}
	else
	{
		echo'
			<form action="', $scripturl, '?action=calendar" id="calendar_navigation" method="post" accept-charset="', $context['character_set'], '">
				<select name="month" id="input_month">';

				// Show a select box with all the months.
				foreach ($txt['months_short'] as $number => $month)
				{
					echo '
					<option value="', $number, '"', $number == $context['current_month'] ? ' selected' : '', '>', $month, '</option>';
				}

				echo '
				</select>
				<select name="year">';

				// Show a link for every year.....
				for ($year = $context['calendar_resources']['min_year']; $year <= $context['calendar_resources']['max_year']; $year++)
				{
					echo '<option value="', $year, '"', $year == $context['current_year'] ? ' selected' : '', '>', $year, '</option>';
				}

				echo '</select>
				<input type="submit" class="button_submit" id="view_button" value="', $txt['view'], '">
			</form>';
	}

	echo'
		</div>';
}

/**
 * Template for posting a calendar event.
 */
function template_event_post()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
		<form action="', $scripturl, '?action=calendar;sa=post" method="post" name="postevent" accept-charset="', $context['character_set'], '" onsubmit="submitonce(this);smc_saveEntities(\'postevent\', [\'evtitle\']);" style="margin: 0;">';

	if (!empty($context['event']['new']))
		echo '<input type="hidden" name="eventid" value="', $context['event']['eventid'], '">';

	// Start the main table.
	echo '
		<div id="post_event">
			<div class="cat_bar">
				<h3 class="catbg">
					', $context['page_title'], '
				</h3>
			</div>';

	if (!empty($context['post_error']['messages']))
	{
		echo '
			<div class="errorbox">
				<dl class="event_error">
					<dt>
						', $context['error_type'] == 'serious' ? '<strong>' . $txt['error_while_submitting'] . '</strong>' : '', '
					</dt>
					<dt class="error">
						', implode('<br>', $context['post_error']['messages']), '
					</dt>
				</dl>
			</div>';
	}

	echo '
			<div class="roundframe noup">
				<fieldset id="event_main">
					<legend><span', isset($context['post_error']['no_event']) ? ' class="error"' : '', '>', $txt['calendar_event_title'], '</span></legend>
					<input type="hidden" name="calendar" value="1">
					<div class="event_options_left" id="event_title">
						<div>
							<input type="text" id="evtitle" name="evtitle" maxlength="255" size="55" value="', $context['event']['title'], '" tabindex="', $context['tabindex']++, '" class="input_text">
						</div>
					</div>';

	// If this is a new event let the user specify which board they want the linked post to be put into.
	if ($context['event']['new'] && !empty($context['event']['categories']))
	{
		echo '
					<div class="event_options_right" id="event_board">
						<div>
							<span class="label">', $txt['calendar_post_in'], '</span>
							<input type="checkbox" style="vertical-align: middle;" class="input_check" name="link_to_board"', (!empty($context['event']['board']) ? ' checked' : ''), ' onclick="toggleLinked(this.form);">
							<select name="board"', empty($context['event']['board']) ? ' disabled' : '', '>';
		foreach ($context['event']['categories'] as $category)
		{
			echo '
								<optgroup label="', $category['name'], '">';
			foreach ($category['boards'] as $board)
				echo '
									<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '&nbsp;</option>';
			echo '
								</optgroup>';
		}
		echo '
							</select>
						</div>
					</div>';
	}

	// Note to theme writers: The JavaScripts expect the input fields for the start and end dates & times to be contained in a wrapper element with the id "event_time_input"
	echo '
				</fieldset>
				<fieldset id="event_options">
					<legend>', $txt['calendar_event_options'], '</legend>
					<div class="event_options_left" id="event_time_input">
						<div>
							<span class="label">', $txt['start'], '</span>
							<input type="text" name="start_date" id="start_date" maxlength="10" value="', $context['event']['start_date'], '" tabindex="', $context['tabindex']++, '" class="input_text date_input start" data-type="date">
							<input type="text" name="start_time" id="start_time" maxlength="11" value="', $context['event']['start_time_local'], '" tabindex="', $context['tabindex']++, '" class="input_text time_input start" data-type="time"', !empty($context['event']['allday']) ? ' disabled' : '', '>
						</div>
						<div>
							<span class="label">', $txt['end'], '</span>
							<input type="text" name="end_date" id="end_date" maxlength="10" value="', $context['event']['end_date'], '" tabindex="', $context['tabindex']++, '" class="input_text date_input end" data-type="date"', $modSettings['cal_maxspan'] == 1 ? ' disabled' : '', '>
							<input type="text" name="end_time" id="end_time" maxlength="11" value="', $context['event']['end_time_local'], '" tabindex="', $context['tabindex']++, '" class="input_text time_input end" data-type="time"', !empty($context['event']['allday']) ? ' disabled' : '', '>
						</div>
					</div>
					<div class="event_options_right" id="event_time_options">
						<div id="event_allday">
							<label for="allday"><span class="label">', $txt['calendar_allday'], '</span></label>
							<input type="checkbox" name="allday" id="allday"', !empty($context['event']['allday']) ? ' checked' : '', ' tabindex="', $context['tabindex']++, '">
						</div>
						<div id="event_timezone">
							<span class="label">', $txt['calendar_timezone'], '</span>
							<select name="tz" id="tz"', !empty($context['event']['allday']) ? ' disabled' : '', '>';

	foreach ($context['all_timezones'] as $tz => $tzname)
		echo '
								<option value="', $tz, '"', $tz == $context['event']['tz'] ? ' selected' : '', '>', $tzname, '</option>';

	echo '
							</select>
						</div>
					</div>
					<div>
						<span class="label">', $txt['location'], '</span>
						<input type="text" name="event_location" id="event_location" maxlength="255" value="', !empty($context['event']['location']) ? $context['event']['location'] : '', '" tabindex="', $context['tabindex']++, '" class="input_text">
					</div>
				</fieldset>';

	echo '
				<input type="submit" value="', empty($context['event']['new']) ? $txt['save'] : $txt['post'], '" class="button_submit">';
	// Delete button?
	if (empty($context['event']['new']))
		echo '
				<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" data-confirm="', $txt['calendar_confirm_delete'], '" class="button_submit you_sure">';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="eventid" value="', $context['event']['eventid'], '">

			</div>
		</div>
		</form>';
}

function template_bcd()
{
	global $context, $scripturl;
	$alt = false;
	echo '
		<table class="table_grid" style="margin: 0 auto 0 auto; border: 1px solid #ccc;">
			<tr>
				<th class="windowbg2" style="font-weight: bold; text-align: center; border-bottom: 1px solid #ccc;" colspan="6">BCD Clock</th>
			</tr>
			<tr class="windowbg">';
			foreach ($context['clockicons'] as $t => $v)
			{
				echo '<td style="padding-', $alt ? 'right' : 'left', ': 1.5em;">';
				foreach ($v as $i)
				{
					echo '<img src="', $context['offimg'], '" alt="" id="', $t, '_', $i, '"><br>';
				}
				echo '</td>';
				$alt = !$alt;
			}
			echo '</tr>
			<tr class="', $alt ? 'windowbg2' : 'windowbg', '" style="border-top: 1px solid #ccc; text-align: center;">
				<td colspan="6">
					<a href="', $scripturl, '?action=clock;rb">Are you hardcore?</a>
				</td>
			</tr>
		</table>

		<script>
		var icons = new Object();';

		foreach ($context['clockicons'] as $t => $v)
		{
			foreach ($v as $i)
				echo '
			icons[\'', $t, '_', $i, '\'] = document.getElementById(\'', $t, '_', $i, '\');';
		}

		echo '
		function update()
		{
			// Get the current time
			var time = new Date();
			var hour = time.getHours();
			var min = time.getMinutes();
			var sec = time.getSeconds();

			// Break it up into individual digits
			var h1 = parseInt(hour / 10);
			var h2 = hour % 10;
			var m1 = parseInt(min / 10);
			var m2 = min % 10;
			var s1 = parseInt(sec / 10);
			var s2 = sec % 10;

			// For each digit figure out which ones to turn off and which ones to turn on
			var turnon = new Array();';

		foreach ($context['clockicons'] as $t => $v)
		{
			foreach ($v as $i)
				echo '
			if (', $t, ' >= ', $i, ')
			{
				turnon.push("', $t, '_', $i, '");
				', $t, ' -= ', $i, ';
			}';
		}

		echo '
			for (var i in icons)
				if (!in_array(i, turnon))
					icons[i].src = "', $context['offimg'], '";
				else
					icons[i].src = "', $context['onimg'], '";

			window.setTimeout("update();", 500);
		}
		// Checks for variable in theArray.
		function in_array(variable, theArray)
		{
			for (var i = 0; i < theArray.length; i++)
			{
				if (theArray[i] == variable)
					return true;
			}
			return false;
		}

		update();
		</script>';
}

function template_hms()
{
	global $context, $scripturl;
	$alt = false;
	echo '
		<table class="table_grid" style="margin: 0 auto 0 auto; border: 1px solid #ccc;">
			<tr>
				<th class="windowbg2" style="font-weight: bold; text-align: center; border-bottom: 1px solid #ccc;">Binary Clock</th>
			</tr>';
			foreach ($context['clockicons'] as $t => $v)
			{
				echo '
					<tr class="', $alt ? 'windowbg2' : 'windowbg', '">
						<td>';
						foreach ($v as $i)
						{
							echo '<img src="', $context['offimg'], '" alt="" id="', $t, '_', $i, '" style="padding: 2px;">';
						}
						echo '</td>
					</tr>
				';
				$alt = !$alt;
			}
			echo '</tr>
			<tr class="', $alt ? 'windowbg2' : 'windowbg', '" style="border-top: 1px solid #ccc; text-align: center;">
				<td>
					<a href="', $scripturl, '?action=clock">Too tough for you?</a>
				</td>
			</tr>
		</table>';

	echo '
	<script>
	var icons = new Object();';

	foreach ($context['clockicons'] as $t => $v)
	{
		foreach ($v as $i)
			echo '
		icons[\'', $t, '_', $i, '\'] = document.getElementById(\'', $t, '_', $i, '\');';
	}

	echo '
	function update()
	{
		// Get the current time
		var time = new Date();
		var h = time.getHours();
		var m = time.getMinutes();
		var s = time.getSeconds();

		// For each digit figure out which ones to turn off and which ones to turn on
		var turnon = new Array();';

	foreach ($context['clockicons'] as $t => $v)
	{
		foreach ($v as $i)
			echo '
		if (', $t, ' >= ', $i, ')
		{
			turnon.push("', $t, '_', $i, '");
			', $t, ' -= ', $i, ';
		}';
	}

	echo '
		for (var i in icons)
			if (!in_array(i, turnon))
				icons[i].src = "', $context['offimg'], '";
			else
				icons[i].src = "', $context['onimg'], '";

		window.setTimeout("update();", 500);
	}
	// Checks for variable in theArray.
	function in_array(variable, theArray)
	{
		for (var i = 0; i < theArray.length; i++)
		{
			if (theArray[i] == variable)
				return true;
		}
		return false;
	}

	update();
	</script>';
}

function template_omfg()
{
	global $context;
	$alt = false;
	echo '
		<table class="table_grid" style="margin: 0 auto 0 auto; border: 1px solid #ccc;">
			<tr>
				<th class="windowbg2" style="font-weight: bold; text-align: center; border-bottom: 1px solid #ccc;">OMFG Binary Clock</th>
			</tr>';
			foreach ($context['clockicons'] as $t => $v)
			{
				echo '
					<tr class="', $alt ? 'windowbg2' : 'windowbg', '">
						<td>';
						foreach ($v as $i)
						{
							echo '<img src="', $context['offimg'], '" alt="" id="', $t, '_', $i, '" style="padding: 2px;">';
						}
						echo '</td>
					</tr>
				';
				$alt = !$alt;
			}
		echo '</tr>
		</table>';

	echo '
	<script>
	var icons = new Object();';

	foreach ($context['clockicons'] as $t => $v)
	{
		foreach ($v as $i)
			echo '
		icons[\'', $t, '_', $i, '\'] = document.getElementById(\'', $t, '_', $i, '\');';
	}

	echo '
	function update()
	{
		// Get the current time
		var time = new Date();
		var month = time.getMonth() + 1;
		var day = time.getDate();
		var year = time.getFullYear();
		year = year % 100;
		var hour = time.getHours();
		var min = time.getMinutes();
		var sec = time.getSeconds();

		// For each digit figure out which ones to turn off and which ones to turn on
		var turnon = new Array();';

	foreach ($context['clockicons'] as $t => $v)
	{
		foreach ($v as $i)
		echo '
		if (', $t, ' >= ', $i, ')
		{
			turnon.push("', $t, '_', $i, '");
			', $t, ' -= ', $i, ';
		}';
	}

	echo '
		for (var i in icons)
			if (!in_array(i, turnon))
				icons[i].src = "', $context['offimg'], '";
			else
				icons[i].src = "', $context['onimg'], '";

		window.setTimeout("update();", 500);
	}
	// Checks for variable in theArray.
	function in_array(variable, theArray)
	{
		for (var i = 0; i < theArray.length; i++)
		{
			if (theArray[i] == variable)
				return true;
		}
		return false;
	}

	update();
	</script>';
}

function template_thetime()
{
	global $context;
	$alt = false;
	echo '
		<table class="table_grid" style="margin: 0 auto 0 auto; border: 1px solid #ccc;">
			<tr>
				<th class="windowbg2" style="font-weight: bold; text-align: center; border-bottom: 1px solid #ccc;">The time you requested</th>
			</tr>';
			foreach ($context['clockicons'] as $v)
			{
				echo '
					<tr class="', $alt ? 'windowbg2' : 'windowbg', '">
						<td>';
						foreach ($v as $i)
						{
							echo '<img src="', $i ? $context['onimg'] : $context['offimg'], '" alt="" style="padding: 2px;">';
						}
						echo '</td>
					</tr>
				';
				$alt = !$alt;
			}
			echo '
		</table>';
}

?>