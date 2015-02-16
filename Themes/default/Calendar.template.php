<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

// Our main calendar template, which encapsulates weeks and months.
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

	// Are we viewing a specific week or a specific month?
	if (isset($_GET['viewweek']))
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
	echo '<br class="clear">
	</div>';
}

// Display a monthly calendar grid.
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
						<span class="floatleft xlarge_text">
							<a href="', $calendar_data['previous_calendar']['href'], '">&#171;</a>
						</span>
					';
				}

				// Arguably the most exciting part, the title!
				echo '<a href="', $scripturl, '?action=calendar;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], '">', $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'], '</a>';

				// Next Link: if we're showing prev / next and it's not a mini-calendar.
				if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'] && $is_mini === false)
				{
					echo '
						<span class="floatright xlarge_text">
							<a href="', $calendar_data['next_calendar']['href'], '">&#187;</a>
						</span>
					';
				}

				echo '
				</h3>
			</div>
		';
	}

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
					if ($is_mini === true && in_array($calendar_data['highlight']['events'], array(1,3)))
						$classes[] = 'events';
					elseif ($is_mini === false && in_array($calendar_data['highlight']['events'], array(2,3)))
						$classes[] = 'events';
				}
				if (!empty($day['holidays']) && !empty($calendar_data['highlight']['holidays']))
				{
					if ($is_mini === true && in_array($calendar_data['highlight']['holidays'], array(1,3)))
						$classes[] = 'holidays';
					elseif ($is_mini === false && in_array($calendar_data['highlight']['holidays'], array(2,3)))
						$classes[] = 'holidays';
				}
				if (!empty($day['birthdays']) && !empty($calendar_data['highlight']['birthdays']))
				{
					if ($is_mini === true && in_array($calendar_data['highlight']['birthdays'], array(1,3)))
						$classes[] = 'birthdays';
					elseif ($is_mini === false && in_array($calendar_data['highlight']['birthdays'], array(2,3)))
						$classes[] = 'birthdays';
				}
			}
			else
				$classes[] = 'disabled';

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
								<span class="birthday">', $txt['birthdays'], '</span>';

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
						echo '
							<div class="smalltext lefttext">
								<span class="event">', $txt['events'], '</span><br>';

						/* The events are made up of:
							title, href, is_last, can_edit (are they allowed to?), and modify_href. */
						foreach ($day['events'] as $event)
						{
							// If they can edit the event, show an icon they can click on....
							if ($event['can_edit'])
							{
								echo '
									<a class="modify_event" href="', $event['modify_href'], '">
										<img src="', $settings['images_url'], '/icons/calendar_modify.png" alt="*" title="', $txt['calendar_edit'], '" class="calendar_icon">
									</a>
								';
							}
							// Exporting!
							if ($event['can_export'])
							{
								echo '
									<a class="modify_event" href="', $event['export_href'], '">
										<img src="', $settings['images_url'], '/icons/calendar_export.png" alt=">" title="', $txt['calendar_export'], '" class="calendar_icon">
									</a>
								';
							}
							echo $event['is_selected'] ? '<div class="sel_event">' . $event['link'] . '</div>' : $event['link'], $event['is_last'] ? '' : '<br>';
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
					echo '<a href="', $scripturl, '?action=calendar;year=', $context['calendar_grid_prev']['current_year'], ';month=', $context['calendar_grid_prev']['current_month'], '">', $context['calendar_grid_prev']['last_of_month'] - $calendar_data['shift']-- + 1, '</a>';
				elseif (!empty($current_month_started) && !empty($context['calendar_grid_next']))
					echo '<a href="', $scripturl, '?action=calendar;year=', $context['calendar_grid_next']['current_year'], ';month=', $context['calendar_grid_next']['current_month'], '">', $current_month_started + 1 == $count ? (!empty($calendar_data['short_month_titles']) ? $txt['months_short'][$context['calendar_grid_next']['current_month']] . ' ' : $txt['months_titles'][$context['calendar_grid_next']['current_month']] . ' ') : '', $final_count++, '</a>';
			}

			// Close this day and increase var count.
			echo '</td>';
			++$count;
		}

		echo '</tr>';
	}

	// Quick Month Navigation + Post Event Link on Main Grids!
	if ($is_mini === false)
		template_calendar_base($show_week_links === true ? 8 : 7);

	// The end of our main table.
	echo '</table>';
}

// Or show a weekly one?
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
							<span class="floatleft xlarge_text">
								<a href="', $calendar_data['previous_week']['href'], '">&#171;</a>
							</span>
						';
					}

					// The Month Title + Week Number...
					if (!empty($calendar_data['week_title']))
							echo $calendar_data['week_title'];

					// Next Week Link...
					if (empty($calendar_data['next_calendar']['disabled']) && !empty($calendar_data['show_next_prev']))
					{
						echo '
							<span class="floatright xlarge_text">
								<a href="', $calendar_data['next_week']['href'], '">&#187;</a>
							</span>
						';
					}

					echo '
					</h3>
				</div>
			';
		}

		// Our actual month...
		echo '
			<div class="week_month_title">
				<a href="', $scripturl, '?action=calendar;month=', $month_data['current_month'], '">
					', $txt['months_titles'][$month_data['current_month']], '
				</a>
			</div>
		';

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
							<td class="', implode(' ', $classes), '', empty($day['events']) ? (' disabled' . ($context['can_post'] ? ' week_post' : '')) : ' events', '">';
							// Show any events...
							if (!empty($day['events']))
							{
								echo '<div class="event_cont floatleft">';
								foreach ($day['events'] as $event)
								{
									// If they can edit the event, show a star they can click on....
									if (!empty($event['can_edit']))
									{
										echo '
											<a href="', $event['modify_href'], '">
												<img src="', $settings['images_url'], '/icons/calendar_modify.png" alt="*" title="', $txt['calendar_edit'], '" class="calendar_icon">
											</a>
										';
									}
									// Can we export? Sweet.
									if (!empty($event['can_export']))
									{
										echo '
											<a class="modify_event" href="', $event['export_href'], '">
												<img src="', $settings['images_url'], '/icons/calendar_export.png" alt=">" title="', $txt['calendar_export'], '" class="calendar_icon">
											</a>
										';
									}
									echo $event['link'], $event['is_last'] ? '' : '<br>';
								}
								echo '
									</div>
									<div class="active_post_event floatright">
										<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">
											<img src="', $settings['images_url'], '/icons/plus.png" alt="*" title="', $txt['calendar_post_event'], '">
										</a>
									</div>
									<br class="clear">
								';
							}
							else
							{
								if (!empty($context['can_post']))
								{
									echo '
										<div class="week_add_event">
											<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['calendar_post_event'], '</a>
										</div>
									';
								}
							}
							echo '</td>
							<td class="', implode(' ', $classes), !empty($day['holidays']) ? ' holidays' : ' disabled', '">';
							// Show any holidays!
							if (!empty($day['holidays']))
								echo implode('<br>', $day['holidays']);

							echo '</td>
							<td class="', implode(' ', $classes), '', !empty($day['birthdays']) ? ' birthdays' : ' disabled', '">';
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
						</tr>
					';
				}

				// We'll show the lower column after our last month is shown.
				if ($iteration == $num_months)
					template_calendar_base(4);

				// Increase iteration for loop counting.
				++$iteration;

				echo '
			</table>
		';
	}
}

/*
 * Calendar Grid Base
 *
 * This function is ONLY designed for use
 * within an existing table element.
 *
 * @param int $col_span = 1
 */
function template_calendar_base($col_span = 1)
{
	global $context, $scripturl, $txt;
	echo '
		<tr>
			<td id="post_event" colspan="', $col_span, '">
				', template_button_strip($context['calendar_buttons'], 'right'), '
				<form action="', $scripturl, '?action=calendar" id="calendar_navigation" method="post" accept-charset="', $context['character_set'], '">
					<select name="month" id="input_month">';
					// Show a select box with all the months.
					foreach ($txt['months'] as $number => $month)
					{
						echo '<option value="', $number, '"', $number == $context['current_month'] ? ' selected' : '', '>', $month, '</option>';
					}
					echo '</select>
											<select name="year">';
					// Show a link for every year.....
					for ($year = $context['calendar_resources']['min_year']; $year <= $context['calendar_resources']['max_year']; $year++)
					{
						echo '<option value="', $year, '"', $year == $context['current_year'] ? ' selected' : '', '>', $year, '</option>';
					}
					echo '</select>
					<input type="submit" class="button_submit" id="view_button" value="', $txt['view'], '">
				</form>
				<br class="clear">
			</td>
		</tr>
	';
}

// Template for posting a calendar event.
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
			<div class="roundframe">
				<fieldset id="event_main">
					<legend><span', isset($context['post_error']['no_event']) ? ' class="error"' : '', '>', $txt['calendar_event_title'], '</span></legend>
					<input type="text" name="evtitle" maxlength="255" size="70" value="', $context['event']['title'], '" class="input_text">
					<div class="smalltext" style="white-space: nowrap;">
						<input type="hidden" name="calendar" value="1">', $txt['calendar_year'], '
						<select name="year" id="year" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = $context['calendar_resources']['min_year']; $year <= $context['calendar_resources']['max_year']; $year++)
		echo '
							<option value="', $year, '"', $year == $context['event']['year'] ? ' selected' : '', '>', $year, '&nbsp;</option>';

	echo '
						</select>
						', $txt['calendar_month'], '
						<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
							<option value="', $month, '"', $month == $context['event']['month'] ? ' selected' : '', '>', $txt['months'][$month], '&nbsp;</option>';

	echo '
						</select>
						', $txt['calendar_day'], '
						<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['event']['last_day']; $day++)
		echo '
							<option value="', $day, '"', $day == $context['event']['day'] ? ' selected' : '', '>', $day, '&nbsp;</option>';

	echo '
						</select>
					</div>
				</fieldset>';

	if (!empty($modSettings['cal_allowspan']) || $context['event']['new'])
		echo '
				<fieldset id="event_options">
					<legend>', $txt['calendar_event_options'], '</legend>
					<div class="event_options smalltext">
						<ul class="event_options">';

	// If events can span more than one day then allow the user to select how long it should last.
	if (!empty($modSettings['cal_allowspan']))
	{
		echo '
							<li>
								', $txt['calendar_numb_days'], '
								<select name="span">';

		for ($days = 1; $days <= $modSettings['cal_maxspan']; $days++)
			echo '
									<option value="', $days, '"', $context['event']['span'] == $days ? ' selected' : '', '>', $days, '&nbsp;</option>';

		echo '
								</select>
							</li>';
	}

	// If this is a new event let the user specify which board they want the linked post to be put into.
	if (!empty($context['event']['categories']))
	{
		echo '
							<li>
								', $txt['calendar_link_event'], '
								<input type="checkbox" style="vertical-align: middle;" class="input_check" name="link_to_board"', ($context['event']['new'] ? ' checked' : ''), ' onclick="toggleLinked(this.form);">
							</li>
							<li>
								', $txt['calendar_post_in'], '
								<select id="board" name="board" onchange="this.form.submit();"', ($context['event']['new'] ? '' : ' disabled'), '>';
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
							</li>';
	}

	if (!empty($modSettings['cal_allowspan']) || $context['event']['new'])
		echo '
						</ul>
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

		<script><!-- // --><![CDATA[
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
		// ]]></script>';
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
		</table>
	';

	echo '
	<script><!-- // --><![CDATA[
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
	// ]]></script>';
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
		</table>
	';

	echo '
	<script><!-- // --><![CDATA[
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
	// ]]></script>';
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
		</table>
	';
}

?>