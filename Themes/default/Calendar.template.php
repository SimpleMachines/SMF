<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

// The main calendar - January, for example.
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;
	echo '<div id="calendar">';
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
	echo '
			<div id="', !empty($_GET['viewweek']) ? 'week_grid' : 'main_grid', '"', !empty($context['blocks_disabled']) ? ' style="width: 100%;"' : '', '>
				', $context['view_week'] ? template_show_week_grid('main') : template_show_month_grid('main'), '
				<div id="footer_cont">
					', template_button_strip($context['calendar_buttons'], 'right'), '
					<form action="', $scripturl, '?action=calendar" id="calendar_navigation" method="post" accept-charset="', $context['character_set'], '">
						<select name="month" id="input_month">';
						// Show a select box with all the months.
						foreach ($txt['months'] as $number => $month)
						{
							echo '<option value="', $number, '"', $number == $context['current_month'] ? ' selected="selected"' : '', '>', $month, '</option>';
						}
						echo '</select>
						<select name="year">';
						// Show a link for every year.....
						for ($year = $modSettings['cal_minyear']; $year <= $modSettings['cal_maxyear']; $year++)
						{
							echo '<option value="', $year, '"', $year == $context['current_year'] ? ' selected="selected"' : '', '>', $year, '</option>';
						}
						echo '</select>
						<input type="submit" class="button_submit" value="', $txt['view'], '" />
					</form>
				</div>
			</div>
			<br class="clear" />
		</div>
	';
}

// Template for posting a calendar event.
function template_event_post()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Start the javascript for drop down boxes...
	echo '
		<script><!-- // --><![CDATA[
			var monthLength;
			monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
			function generateDays()
			{
				var days, selected, dayElement;
				
				days = 0, selected = 0;
				dayElement = document.getElementById("day"), yearElement = document.getElementById("year"), monthElement = document.getElementById("month");

				monthLength[1] = 28;
				if (yearElement.options[yearElement.selectedIndex].value % 4 == 0)
					monthLength[1] = 29;

				selected = dayElement.selectedIndex;
				while (dayElement.options.length)
					dayElement.options[0] = null;

				days = monthLength[monthElement.value - 1];

				for (i = 1; i <= days; i++)
					dayElement.options[dayElement.length] = new Option(i, i);

				if (selected < days)
					dayElement.selectedIndex = selected;
			}
		// ]]></script>

		<form action="', $scripturl, '?action=calendar;sa=post" method="post" name="postevent" accept-charset="', $context['character_set'], '" onsubmit="submitonce(this);smc_saveEntities(\'postevent\', [\'evtitle\']);" style="margin: 0;">';

	if (!empty($context['event']['new']))
		echo '
			<input type="hidden" name="eventid" value="', $context['event']['eventid'], '" />';

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
						', implode('<br />', $context['post_error']['messages']), '
					</dt>
				</dl>
			</div>';
	}

	echo '
			<div class="roundframe">
				<fieldset id="event_main">
					<legend><span', isset($context['post_error']['no_event']) ? ' class="error"' : '', '>', $txt['calendar_event_title'], '</span></legend>
					<input type="text" name="evtitle" maxlength="255" size="70" value="', $context['event']['title'], '" class="input_text" />
					<div class="smalltext" style="white-space: nowrap;">
						<input type="hidden" name="calendar" value="1" />', $txt['calendar_year'], '
						<select name="year" id="year" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = $modSettings['cal_minyear']; $year <= $modSettings['cal_maxyear']; $year++)
		echo '
							<option value="', $year, '"', $year == $context['event']['year'] ? ' selected="selected"' : '', '>', $year, '&nbsp;</option>';

	echo '
						</select>
						', $txt['calendar_month'], '
						<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
							<option value="', $month, '"', $month == $context['event']['month'] ? ' selected="selected"' : '', '>', $txt['months'][$month], '&nbsp;</option>';

	echo '
						</select>
						', $txt['calendar_day'], '
						<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['event']['last_day']; $day++)
		echo '
							<option value="', $day, '"', $day == $context['event']['day'] ? ' selected="selected"' : '', '>', $day, '&nbsp;</option>';

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
									<option value="', $days, '"', $context['event']['span'] == $days ? ' selected="selected"' : '', '>', $days, '&nbsp;</option>';

		echo '
								</select>
							</li>';
	}

	// If this is a new event let the user specify which board they want the linked post to be put into.
	if ($context['event']['new'])
	{
		echo '
							<li>
								', $txt['calendar_link_event'], '
								<input type="checkbox" style="vertical-align: middle;" class="input_check" name="link_to_board" checked="checked" onclick="toggleLinked(this.form);" />
							</li>
							<li>
								', $txt['calendar_post_in'], '
								<select id="board" name="board" onchange="this.form.submit();">';
		foreach ($context['event']['categories'] as $category)
		{
			echo '
									<optgroup label="', $category['name'], '">';
			foreach ($category['boards'] as $board)
				echo '
										<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '&nbsp;</option>';
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
				<input type="submit" value="', empty($context['event']['new']) ? $txt['save'] : $txt['post'], '" class="button_submit" />';
	// Delete button?
	if (empty($context['event']['new']))
		echo '
				<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" onclick="return confirm(\'', $txt['calendar_confirm_delete'], '\');" class="button_submit" />';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="eventid" value="', $context['event']['eventid'], '" />

			</div>
		</div>
		</form>';
}

// Display a monthly calendar grid.
function template_show_month_grid($grid_name, $is_mini = false)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $smcFunc;

	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	$calendar_data = &$context['calendar_grid_' . $grid_name];
	$colspan = !empty($calendar_data['show_week_links']) ? 8 : 7;

	if (empty($calendar_data['disable_title']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg centertext" style="font-size: large;">';

		if (empty($calendar_data['previous_calendar']['disabled']) && $calendar_data['show_next_prev'] && $is_mini === false)
		{
			echo '
				<span class="floatleft">
					<a href="', $calendar_data['previous_calendar']['href'], '">&#171;</a>
				</span>
			';
		}

		if ($calendar_data['show_next_prev'])
			echo $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'];
		else
			echo '<a href="', $scripturl, '?action=calendar;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], '">', $txt['months_titles'][$calendar_data['current_month']], ' ', $calendar_data['current_year'], '</a>';

		if (empty($calendar_data['next_calendar']['disabled']) && $calendar_data['show_next_prev'] && $is_mini === false)
		{
			echo '
				<span class="floatright">
					<a href="', $calendar_data['next_calendar']['href'], '">&#187;</a>
				</span>
			';
		}

		echo '
				</h3>
			</div>';
	}

	echo '
				<table class="calendar_table">';

	if (isset($calendar_data['show_week_links']) && ($calendar_data['show_week_links'] == 3 || (($calendar_data['show_week_links'] == 1 && $is_mini === true) || $calendar_data['show_week_links'] == 2 && $is_mini === false)))
		$show_week_links = true;
	else
		$show_week_links = false;

	// Show each day of the week.
	if (empty($calendar_data['disable_day_titles']))
	{
		echo '
					<tr>';

		if ($show_week_links === true)
			echo '
						<th>&nbsp;</th>';

		foreach ($calendar_data['week_days'] as $day)
		{
			echo '
						<th class="days" scope="col">', !empty($calendar_data['short_day_titles']) || $is_mini === true ? $txt['days_short'][$day] : $txt['days'][$day], '</th>';
		}
		echo '
					</tr>';
	}

	/* Each week in weeks contains the following:
		days (a list of days), number (week # in the year.) */
	foreach ($calendar_data['weeks'] as $week)
	{
		echo '
					<tr class="days_wrapper">';

		if ($show_week_links === true)
			echo '
						<td class="windowbg2 weeks">
							<a href="', $scripturl, '?action=calendar;viewweek;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $week['days'][0]['day'], '">&#187;</a>
						</td>';

		/* Every day has the following:
			day (# in month), is_today (is this day *today*?), is_first_day (first day of the week?),
			holidays, events, birthdays. (last three are lists.) */
		$current_month_started = false;
		$count = 1;
		$final_count = 1;
		$shift = $calendar_data['shift'];
		foreach ($week['days'] as $day)
		{

			// How should we be higlighted or otherwise not...?
			$classes = array('days');
			if (!empty($day['day']))
			{

				$classes[] = $calendar_data['size'] == 'small' ? 'compact' : 'comfortable';
				$classes[] = !empty($day['is_today']) ? 'calendar_today' : 'windowbg';

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

			// If this is today, make it a different color and show a border.
			echo '
						<td class="', implode(' ', $classes), '">';

			// Skip it if it should be blank - it's not a day if it has no number.
			if (!empty($day['day']))
			{
				$title_prefix = !empty($day['is_first_of_month']) && $context['current_month'] == $calendar_data['current_month'] && $is_mini === false ? (!empty($calendar_data['short_month_titles']) ? $txt['months_short'][$calendar_data['current_month']] . ' ' : $txt['months_titles'][$calendar_data['current_month']] . ' ') : '';

				// Should the day number be a link?
				if (!empty($modSettings['cal_daysaslink']) && $context['can_post'])
					echo '
							<a href="', $scripturl, '?action=calendar;sa=post;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '"><span class="day_text">', $title_prefix, $day['day'], '</span></a>';
				else
					echo '<span class="day_text">', $title_prefix, $day['day'], '</span>';

				// Is this the first day of the week? (and are we showing week numbers?)
				if ($day['is_first_day'] && $is_mini === false)
					echo '<span class="smalltext"> - <a href="', $scripturl, '?action=calendar;viewweek;year=', $calendar_data['current_year'], ';month=', $calendar_data['current_month'], ';day=', $day['day'], '">', $txt['calendar_week'], ' ', $week['number'], '</a></span>';

				// Are there any holidays?
				if (!empty($day['holidays']) && $is_mini === false)
					echo '
							<div class="smalltext holiday"><span>', $txt['calendar_prompt'], '</span> ', implode(', ', $day['holidays']), '</div>';

				// Show any birthdays...
				if (!empty($day['birthdays']) && $is_mini === false)
				{
					echo '
							<div class="smalltext">
								<span class="birthday">', $txt['birthdays'], '</span>';

					/* Each of the birthdays has:
						id, name (person), age (if they have one set?), and is_last. (last in list?) */
					$use_js_hide = empty($context['show_all_birthdays']) && count($day['birthdays']) > 15;
					$count = 0;
					foreach ($day['birthdays'] as $member)
					{
						echo '
									<a href="', $scripturl, '?action=profile;u=', $member['id'], '"><span class="fix_rtl_names">', $member['name'], '</span>', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] || ($count == 10 && $use_js_hide)? '' : ', ';

						// Stop at ten?
						if ($count == 10 && $use_js_hide)
							echo '<span class="hidelink" id="bdhidelink_', $day['day'], '">...<br /><a href="', $scripturl, '?action=calendar;month=', $calendar_data['current_month'], ';year=', $calendar_data['current_year'], ';showbd" onclick="document.getElementById(\'bdhide_', $day['day'], '\').style.display = \'\'; document.getElementById(\'bdhidelink_', $day['day'], '\').style.display = \'none\'; return false;">(', sprintf($txt['calendar_click_all'], count($day['birthdays'])), ')</a></span><span id="bdhide_', $day['day'], '" style="display: none;">, ';

						$count++;
					}
					if ($use_js_hide)
						echo '
								</span>';

					echo '
							</div>';
				}

				// Any special posted events?
				if (!empty($day['events']) && $is_mini === false)
				{
					echo '
							<div class="smalltext lefttext">
								<span class="event">', $txt['events'], '</span><br />';

					/* The events are made up of:
						title, href, is_last, can_edit (are they allowed to?), and modify_href. */
					foreach ($day['events'] as $event)
					{
						// If they can edit the event, show an icon they can click on....
						if ($event['can_edit'])
							echo '
								<a class="modify_event" href="', $event['modify_href'], '"><img src="' . $settings['images_url'] . '/icons/calendar_modify.png" alt="*" title="' . $txt['modify'] . '" /></a>';

						if ($event['can_export'])
							echo '
								<a class="modify_event" href="', $event['export_href'], '"><img src="' . $settings['images_url'] . '/icons/calendar_export.png" alt=">" title="' . $txt['save'] . '"/></a>';


						echo '
								', $event['link'], $event['is_last'] ? '' : '<br />';
					}

					echo '
							</div>';
				}
				$current_month_started = $count;
			}
			elseif ($is_mini === false)
			{
				if ($current_month_started === false)
					echo '<a href="', $scripturl, '?action=calendar;year=', $context['calendar_grid_prev']['current_year'], ';month=', $context['calendar_grid_prev']['current_month'], '">', $context['calendar_grid_prev']['last_of_month'] - $calendar_data['shift']-- + 1, '</a>';
				else
					echo '<a href="', $scripturl, '?action=calendar;year=', $context['calendar_grid_next']['current_year'], ';month=', $context['calendar_grid_next']['current_month'], '">', $current_month_started + 1 == $count ? (!empty($calendar_data['short_month_titles']) ? $txt['months_short'][$context['calendar_grid_next']['current_month']] . ' ' : $txt['months_titles'][$context['calendar_grid_next']['current_month']] . ' ') : '', $final_count++, '</a>';
			}

			echo '
						</td>';
			$count++;
		}

		echo '
					</tr>';
	}

	echo '
				</table>';
}

// Or show a weekly one?
function template_show_week_grid($grid_name)
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	if (!isset($context['calendar_grid_' . $grid_name]))
		return false;

	$calendar_data = &$context['calendar_grid_' . $grid_name];

	// Loop through each month (At least one) and print out each day.
	foreach ($calendar_data['months'] as $month_data)
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">';
				if (empty($done_title))
				{
					// Previous Week Link...
					if (empty($calendar_data['previous_calendar']['disabled']) && !empty($calendar_data['show_next_prev']))
					{
						echo '
							<span class="floatleft">
								<a href="', $calendar_data['previous_week']['href'], '">&#171;</a>
							</span>
						';
					}

					// The Month Title + Week Number...
					echo '<a href="', $scripturl, '?action=calendar;month=', $month_data['current_month'], ';year=', $month_data['current_year'], '">', $txt['months_titles'][$month_data['current_month']], ' ', $month_data['current_year'], '</a>', empty($done_title) && !empty($calendar_data['week_number']) ? (' - ' . $txt['calendar_week'] . ' ' . $calendar_data['week_number']) : '';

					// Next Week Link...
					if (empty($calendar_data['next_calendar']['disabled']) && !empty($calendar_data['show_next_prev']))
					{
						echo '
							<span class="floatright">
								<a href="', $calendar_data['next_week']['href'], '">&#187;</a>
							</span>
						';
					}

					// If there's more than one month, which there "never is", let's not do this again...
					$done_title = true;
				}
				echo '</h3>
			</div>
			<table class="table_grid" id="calendar_week">';
			foreach ($month_data['days'] as $day)
			{
				// How should we be higlighted or otherwise not...?
				$classes = array('days');
				if (!empty($day['day']))
				{
					$classes[] = $calendar_data['size'] == 'small' ? 'compact' : 'comfortable';
					$classes[] = !empty($day['is_today']) ? 'calendar_today' : 'windowbg';
				}
				else
					$classes[] = 'disabled';
				echo '
					<tr class="days_wrapper">
						<td class="', implode(' ', $classes), '">';
						// Should the day number be a link?
						if (!empty($modSettings['cal_daysaslink']) && !empty($context['can_post']))
							echo '<a href="', $scripturl, '?action=calendar;sa=post;month=', $month_data['current_month'], ';year=', $month_data['current_year'], ';day=', $day['day'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['days'][$day['day_of_week']], ' - ', $day['day'], '</a>';
						else
							echo $txt['days'][$day['day_of_week']], ' - ', $day['day'];

						// Are there any holidays?
						if (!empty($day['holidays']))
							echo '<span>', $txt['calendar_prompt'], '</span> ', implode(', ', $day['holidays']);

						// Show any birthdays...
						if (!empty($day['birthdays']))
						{
							echo '<span class="birthday">', $txt['birthdays'], '</span>';

							/* Each of the birthdays has:
								id, name (person), age (if they have one set?), and is_last. (last in list?) */
							foreach ($day['birthdays'] as $member)
							{
								echo '
									<a href="', $scripturl, '?action=profile;u=', $member['id'], '">
										<span class="fix_rtl_names">', $member['name'], '</span>', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '
									</a>
								', $member['is_last'] ? '' : ', ';
							}
						}

						// Any special posted events?
						if (!empty($day['events']))
						{
							echo '<span class="event">', $txt['events'], '</span>';

							/* The events are made up of:
								title, href, is_last, can_edit (are they allowed to?), and modify_href. */
							foreach ($day['events'] as $event)
							{
								// If they can edit the event, show a star they can click on....
								if (!empty($event['can_edit']))
								{
									echo '
										<a href="', $event['modify_href'], '">
											<img src="' . $settings['images_url'] . '/icons/calendar_modify.png" alt="*" />
										</a>
									';
								}
								echo $event['link'], $event['is_last'] ? '' : ', ';
							}
						}

						echo '</td>
					</tr>
				';
			}
			echo '</table>
		';
	}
}

function template_bcd()
{
	global $context, $scripturl;

	echo '
	<table cellpadding="0" cellspacing="1" align="center">
		<caption class="titlebg">BCD Clock</caption>
		<tr class="windowbg">';

	$alt = false;
	foreach ($context['clockicons'] as $t => $v)
	{
		echo '
			<td style="padding-', $alt ? 'right' : 'left', ': 1.5em;" valign="bottom">';

		foreach ($v as $i)
			echo '
				<img src="', $context['offimg'], '" alt="" id="', $t, '_', $i, '" /><br />';

		echo '
			</td>';

		$alt = !$alt;
	}

	echo '
		</tr>
	</table>
	<p align="center"><a href="', $scripturl, '?action=clock;rb">Are you hardcore?</a></p>

		<script type="text/javascript"><!-- // --><![CDATA[
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

	echo '
<table cellpadding="0" cellspacing="1" align="center">
	<caption class="titlebg">Binary Clock</caption>';
	$alt = false;
	foreach ($context['clockicons'] as $t => $v)
	{
		echo '
	<tr class="', $alt ? 'windowbg2' : 'windowbg', '">
		<td align="right">';
		foreach ($v as $i)
			echo '
			<img src="', $context['offimg'], '" alt="" id="', $t, '_', $i, '" />';
		echo '
		</td>';
		$alt = !$alt;
	}

	echo '
	</tr>
	<tr class="', $alt ? 'windowbg2' : 'windowbg', '"><td colspan="6" align="center"><a href="', $scripturl, '?action=clock">Too tough for you?</a></td></tr>
</table>';

	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
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
	global $context, $scripturl;

	echo '
<table cellpadding="0" cellspacing="1" align="center">
	<caption class="titlebg">OMFG Binary Clock</caption>';
	$alt = false;
	foreach ($context['clockicons'] as $t => $v)
	{
		echo '
	<tr class="', $alt ? 'windowbg2' : 'windowbg', '">
		<td align="right">';
		foreach ($v as $i)
			echo '
			<img src="', $context['offimg'], '" alt="" id="', $t, '_', $i, '" />';
		echo '
		</td>';
		$alt = !$alt;
	}

	echo '
	</tr>
</table>';

	echo '
	<script type="text/javascript"><!-- // --><![CDATA[
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
	global $context, $scripturl;

	echo '
<table cellpadding="0" cellspacing="0" border="1" align="center">
	<caption>The time you requested</caption>';
	$alt = false;
	foreach ($context['clockicons'] as $t => $v)
	{
		echo '
	<tr class="', $alt ? 'windowbg2' : 'windowbg', '">
		<td align="right">';
		foreach ($v as $i)
			echo '
			<img src="', $i ? $context['onimg'] : $context['offimg'], '" alt="" />';
		echo '
		</td>';
		$alt = !$alt;
	}

	echo '
	</tr>
</table>';

}

?>