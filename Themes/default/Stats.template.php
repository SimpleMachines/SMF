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

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * The stats page.
 */
function template_main()
{
	echo '
	<div id="statistics" class="main_section">
		<div class="cat_bar">
			<h3 class="catbg">', Utils::$context['page_title'], '</h3>
		</div>
		<div class="roundframe">
			<div class="title_bar">
				<h4 class="titlebg">
					<span class="main_icons general"></span> ', Lang::$txt['general_stats'], '
				</h4>
			</div>
			<dl class="stats half_content">
				<dt>', Lang::$txt['total_members'], ':</dt>
				<dd>', Utils::$context['show_member_list'] ? '<a href="' . Config::$scripturl . '?action=mlist">' . Utils::$context['num_members'] . '</a>' : Utils::$context['num_members'], '</dd>
				<dt>', Lang::$txt['total_posts'], ':</dt>
				<dd>', Utils::$context['num_posts'], '</dd>
				<dt>', Lang::$txt['total_topics'], ':</dt>
				<dd>', Utils::$context['num_topics'], '</dd>
				<dt>', Lang::$txt['total_cats'], ':</dt>
				<dd>', Utils::$context['num_categories'], '</dd>
				<dt>', Lang::$txt['users_online'], ':</dt>
				<dd>', Utils::$context['users_online'], '</dd>
				<dt>', Lang::$txt['most_online'], ':</dt>
				<dd>', Utils::$context['most_members_online']['number'], ' - ', Utils::$context['most_members_online']['date'], '</dd>
				<dt>', Lang::$txt['users_online_today'], ':</dt>
				<dd>', Utils::$context['online_today'], '</dd>';

	if (!empty(Config::$modSettings['hitStats']))
		echo '
				<dt>', Lang::$txt['num_hits'], ':</dt>
				<dd>', Utils::$context['num_hits'], '</dd>';

	echo '
			</dl>
			<dl class="stats half_content">
				<dt>', Lang::$txt['average_members'], ':</dt>
				<dd>', Utils::$context['average_members'], '</dd>
				<dt>', Lang::$txt['average_posts'], ':</dt>
				<dd>', Utils::$context['average_posts'], '</dd>
				<dt>', Lang::$txt['average_topics'], ':</dt>
				<dd>', Utils::$context['average_topics'], '</dd>
				<dt>', Lang::$txt['total_boards'], ':</dt>
				<dd>', Utils::$context['num_boards'], '</dd>
				<dt>', Lang::$txt['latest_member'], ':</dt>
				<dd>', Utils::$context['common_stats']['latest_member']['link'], '</dd>
				<dt>', Lang::$txt['average_online'], ':</dt>
				<dd>', Utils::$context['average_online'], '</dd>';

	if (!empty(Utils::$context['gender']))
	{
		echo '
				<dt>', Lang::$txt['gender_stats'], ':</dt>
				<dd>';

		foreach (Utils::$context['gender'] as $g => $n)
			echo Lang::tokenTxtReplace($g), ': ', $n, '<br>';

		echo '
				</dd>';
	}

	if (!empty(Config::$modSettings['hitStats']))
		echo '
				<dt>', Lang::$txt['average_hits'], ':</dt>
				<dd>', Utils::$context['average_hits'], '</dd>';

	echo '
			</dl>';

	foreach (Utils::$context['stats_blocks'] as $name => $block)
	{
		echo '
			<div class="half_content">
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="main_icons ', $name, '"></span> ', Lang::$txt['top_' . $name], '
					</h4>
				</div>
				<dl class="stats">';

		foreach ($block as $item)
		{
			echo '
					<dt>
						', $item['link'], '
					</dt>
					<dd class="statsbar generic_bar righttext">';

			if (!empty($item['percent']))
				echo '
						<div class="bar" style="width: ', $item['percent'], '%;"></div>';
			else
				echo '
						<div class="bar empty"></div>';

			echo '
						<span>', $item['num'], '</span>
					</dd>';
		}

		echo '
				</dl>
			</div><!-- .half_content -->';
	}

	echo '
		</div><!-- .roundframe -->
		<br class="clear">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons history"></span>', Lang::$txt['forum_history'], '
			</h3>
		</div>';

	if (!empty(Utils::$context['yearly']))
	{
		echo '
		<table id="stats" class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext">', Lang::$txt['yearly_summary'], '</th>
					<th>', Lang::$txt['stats_new_topics'], '</th>
					<th>', Lang::$txt['stats_new_posts'], '</th>
					<th>', Lang::$txt['stats_new_members'], '</th>
					<th>', Lang::$txt['most_online'], '</th>';

		if (!empty(Config::$modSettings['hitStats']))
			echo '
					<th>', Lang::$txt['page_views'], '</th>';

		echo '
				</tr>
			</thead>
			<tbody>';

		foreach (Utils::$context['yearly'] as $id => $year)
		{
			echo '
				<tr class="windowbg" id="year_', $id, '">
					<th class="lefttext">
						<img id="year_img_', $id, '" src="', Theme::$current->settings['images_url'], '/selected_open.png" alt="*"> <a href="#year_', $id, '" id="year_link_', $id, '">', $year['year'], '</a>
					</th>
					<th>', $year['new_topics'], '</th>
					<th>', $year['new_posts'], '</th>
					<th>', $year['new_members'], '</th>
					<th>', $year['most_members_online'], '</th>';

			if (!empty(Config::$modSettings['hitStats']))
				echo '
					<th>', $year['hits'], '</th>';

			echo '
				</tr>';

			foreach ($year['months'] as $month)
			{
				echo '
				<tr class="windowbg" id="tr_month_', $month['id'], '">
					<th class="stats_month">
						<img src="', Theme::$current->settings['images_url'], '/', $month['expanded'] ? 'selected_open.png' : 'selected.png', '" alt="" id="img_', $month['id'], '"> <a id="m', $month['id'], '" href="', $month['href'], '" onclick="return doingExpandCollapse;">', $month['month'], ' ', $month['year'], '</a>
					</th>
					<th>', $month['new_topics'], '</th>
					<th>', $month['new_posts'], '</th>
					<th>', $month['new_members'], '</th>
					<th>', $month['most_members_online'], '</th>';

				if (!empty(Config::$modSettings['hitStats']))
					echo '
					<th>', $month['hits'], '</th>';

				echo '
				</tr>';

				if ($month['expanded'])
				{
					foreach ($month['days'] as $day)
					{
						echo '
				<tr class="windowbg" id="tr_day_', $day['year'], '-', $day['month'], '-', $day['day'], '">
					<td class="stats_day">', $day['year'], '-', $day['month'], '-', $day['day'], '</td>
					<td>', $day['new_topics'], '</td>
					<td>', $day['new_posts'], '</td>
					<td>', $day['new_members'], '</td>
					<td>', $day['most_members_online'], '</td>';

						if (!empty(Config::$modSettings['hitStats']))
							echo '
					<td>', $day['hits'], '</td>';

						echo '
				</tr>';
					}
				}
			}
		}

		echo '
			</tbody>
		</table>
	</div><!-- #statistics -->
	<script>
		var oStatsCenter = new smf_StatsCenter({
			sTableId: \'stats\',

			reYearPattern: /year_(\d+)/,
			sYearImageCollapsed: \'selected.png\',
			sYearImageExpanded: \'selected_open.png\',
			sYearImageIdPrefix: \'year_img_\',
			sYearLinkIdPrefix: \'year_link_\',

			reMonthPattern: /tr_month_(\d+)/,
			sMonthImageCollapsed: \'selected.png\',
			sMonthImageExpanded: \'selected_open.png\',
			sMonthImageIdPrefix: \'img_\',
			sMonthLinkIdPrefix: \'m\',

			reDayPattern: /tr_day_(\d+-\d+-\d+)/,
			sDayRowClassname: \'windowbg\',
			sDayRowIdPrefix: \'tr_day_\',

			aCollapsedYears: [';

		foreach (Utils::$context['collapsed_years'] as $id => $year)
		{
			echo '
				\'', $year, '\'', $id != count(Utils::$context['collapsed_years']) - 1 ? ',' : '';
		}

		echo '
			],

			aDataCells: [
				\'date\',
				\'new_topics\',
				\'new_posts\',
				\'new_members\',
				\'most_members_online\'', empty(Config::$modSettings['hitStats']) ? '' : ',
				\'hits\'', '
			]
		});
	</script>';
	}
}

?>