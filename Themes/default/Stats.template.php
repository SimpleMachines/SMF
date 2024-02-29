<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
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
		<div class="roundframe csscolumn">
			<div class="csscolumn columnspan">
			<dl class="stats">
				<dt>', Lang::getTxt('total_members', file: 'General'), '</dt>
				<dd>', Utils::$context['show_member_list'] ? '<a href="' . Config::$scripturl . '?action=mlist">' . Utils::$context['num_members'] . '</a>' : Utils::$context['num_members'], '</dd>
				<dt>', Lang::getTxt('total_posts', file: 'General'), '</dt>
				<dd>', Utils::$context['num_posts'], '</dd>
				<dt>', Lang::getTxt('total_topics', file: 'General'), '</dt>
				<dd>', Utils::$context['num_topics'], '</dd>
				<dt>', Lang::getTxt('total_cats', file: 'General'), '</dt>
				<dd>', Utils::$context['num_categories'], '</dd>
				<dt>', Lang::getTxt('users_online', file: 'Stats'), '</dt>
				<dd>', Utils::$context['users_online'], '</dd>
				<dt>', Lang::getTxt('most_online', file: 'Stats'), '</dt>
				<dd>', Lang::getTxt('most_online_number_date', Utils::$context['most_members_online'], file: 'Stats'), '</dd>
				<dt>', Lang::getTxt('users_online_today', file: 'Stats'), '</dt>
				<dd>', Utils::$context['online_today'], '</dd>';

	if (!empty(Config::$modSettings['hitStats']))
		echo '
				<dt>', Lang::getTxt('num_hits', file: 'Stats'), '</dt>
				<dd>', Utils::$context['num_hits'], '</dd>';

	echo '
			</dl>
			<dl class="stats">
				<dt>', Lang::getTxt('average_members', file: 'Stats'), '</dt>
				<dd>', Utils::$context['average_members'], '</dd>
				<dt>', Lang::getTxt('average_posts', file: 'Stats'), '</dt>
				<dd>', Utils::$context['average_posts'], '</dd>
				<dt>', Lang::getTxt('average_topics', file: 'Stats'), '</dt>
				<dd>', Utils::$context['average_topics'], '</dd>
				<dt>', Lang::getTxt('total_boards', file: 'General'), '</dt>
				<dd>', Utils::$context['num_boards'], '</dd>
				<dt>', Lang::getTxt('latest_member', file: 'General'), '</dt>
				<dd>', Utils::$context['common_stats']['latest_member']['link'], '</dd>
				<dt>', Lang::getTxt('average_online', file: 'Stats'), '</dt>
				<dd>', Utils::$context['average_online'], '</dd>';

	if (!empty(Utils::$context['gender']))
	{
		echo '
				<dt>', Lang::getTxt('gender_stats', file: 'Stats'), '</dt>
				<dd>';

		foreach (Utils::$context['gender'] as $g => $n)
			echo Lang::getTxt('gender_stats_number', ['gender' => Lang::tokenTxtReplace($g), 'number' => $n], file: 'Stats'), '<br>';

		echo '
				</dd>';
	}

	if (!empty(Config::$modSettings['hitStats']))
		echo '
				<dt>', Lang::getTxt('average_hits', file: 'Stats'), '</dt>
				<dd>', Utils::$context['average_hits'], '</dd>';

	echo '
			</dl>
				</div>';

	foreach (Utils::$context['stats_blocks'] as $name => $block)
	{
		echo '
			<div>
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="main_icons ', $name, '"></span> ', Lang::getTxt('top_' . $name, file: 'Stats'), '
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
				</div>';
	}

	echo '
		</div><!-- .roundframe -->
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons history"></span>', Lang::getTxt('forum_history', file: 'Stats'), '
			</h3>
		</div>';

	if (!empty(Utils::$context['yearly']))
	{
		echo '
		<table id="stats" class="table_grid">
			<thead>
				<tr class="title_bar">
					<th class="lefttext">', Lang::getTxt('yearly_summary', file: 'Stats'), '</th>
					<th>', Lang::getTxt('stats_new_topics', file: 'Stats'), '</th>
					<th>', Lang::getTxt('stats_new_posts', file: 'Stats'), '</th>
					<th>', Lang::getTxt('stats_new_members', file: 'Stats'), '</th>
					<th>', Lang::getTxt('most_online', file: 'Stats'), '</th>';

		if (!empty(Config::$modSettings['hitStats']))
			echo '
					<th>', Lang::getTxt('page_views', file: 'Stats'), '</th>';

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