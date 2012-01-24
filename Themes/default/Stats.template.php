<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<div id="statistics" class="main_section">
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="title_bar">
			<h4 class="titlebg">
				<span class="ie6_header floatleft">
					<img src="', $settings['images_url'], '/stats_info.gif" class="icon" alt="" /> ', $txt['general_stats'], '
				</span>
			</h4>
		</div>
		<div class="flow_hidden">
			<div id="stats_left">
				<div class="windowbg2">
					<span class="topslice"><span></span></span>
					<div class="content top_row">
						<dl class="stats">
							<dt>', $txt['total_members'], ':</dt>
							<dd>', $context['show_member_list'] ? '<a href="' . $scripturl . '?action=mlist">' . $context['num_members'] . '</a>' : $context['num_members'], '</dd>
							<dt>', $txt['total_posts'], ':</dt>
							<dd>', $context['num_posts'], '</dd>
							<dt>', $txt['total_topics'], ':</dt>
							<dd>', $context['num_topics'], '</dd>
							<dt>', $txt['total_cats'], ':</dt>
							<dd>', $context['num_categories'], '</dd>
							<dt>', $txt['users_online'], ':</dt>
							<dd>', $context['users_online'], '</dd>
							<dt>', $txt['most_online'], ':</dt>
							<dd>', $context['most_members_online']['number'], ' - ', $context['most_members_online']['date'], '</dd>
							<dt>', $txt['users_online_today'], ':</dt>
							<dd>', $context['online_today'], '</dd>';

	if (!empty($modSettings['hitStats']))
		echo '
							<dt>', $txt['num_hits'], ':</dt>
							<dd>', $context['num_hits'], '</dd>';

	echo '
						</dl>
						<div class="clear"></div>
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>
			<div id="stats_right">
				<div class="windowbg2">
					<span class="topslice"><span></span></span>
					<div class="content top_row">
						<dl class="stats">
							<dt>', $txt['average_members'], ':</dt>
							<dd>', $context['average_members'], '</dd>
							<dt>', $txt['average_posts'], ':</dt>
							<dd>', $context['average_posts'], '</dd>
							<dt>', $txt['average_topics'], ':</dt>
							<dd>', $context['average_topics'], '</dd>
							<dt>', $txt['total_boards'], ':</dt>
							<dd>', $context['num_boards'], '</dd>
							<dt>', $txt['latest_member'], ':</dt>
							<dd>', $context['common_stats']['latest_member']['link'], '</dd>
							<dt>', $txt['average_online'], ':</dt>
							<dd>', $context['average_online'], '</dd>
							<dt>', $txt['gender_ratio'], ':</dt>
							<dd>', $context['gender']['ratio'], '</dd>';

	if (!empty($modSettings['hitStats']))
		echo '
							<dt>', $txt['average_hits'], ':</dt>
							<dd>', $context['average_hits'], '</dd>';

	echo '
						</dl>
						<div class="clear"></div>
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>
		</div>
		<div class="flow_hidden">
			<div id="top_posters">
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="ie6_header floatleft">
							<img src="', $settings['images_url'], '/stats_posters.gif" class="icon" alt="" /> ', $txt['top_posters'], '
						</span>
					</h4>
				</div>
					<div class="windowbg2">
						<span class="topslice"><span></span></span>
						<div class="content">
							<dl class="stats">';

	foreach ($context['top_posters'] as $poster)
	{
		echo '
								<dt>
									', $poster['link'], '
								</dt>
								<dd class="statsbar">';

		if (!empty($poster['post_percent']))
			echo '
									<div class="bar" style="width: ', $poster['post_percent'] + 4, 'px;">
										<div style="width: ', $poster['post_percent'], 'px;"></div>
									</div>';

		echo '
									<span class="righttext">', $poster['num_posts'], '</span>
								</dd>';
	}

	echo '
							</dl>
							<div class="clear"></div>
						</div>
						<span class="botslice"><span></span></span>
					</div>
			</div>
			<div id="top_boards">
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="ie6_header floatleft">
							<img src="', $settings['images_url'], '/stats_board.gif" class="icon" alt="" /> ', $txt['top_boards'], '
						</span>
					</h4>
				</div>
					<div class="windowbg2">
						<span class="topslice"><span></span></span>
						<div class="content">
							<dl class="stats">';

	foreach ($context['top_boards'] as $board)
	{
		echo '
								<dt>
									', $board['link'], '
								</dt>
								<dd class="statsbar">';

		if (!empty($board['post_percent']))
			echo '
									<div class="bar" style="width: ', $board['post_percent'] + 4, 'px;">
										<div style="width: ', $board['post_percent'], 'px;"></div>
									</div>';
		echo '
									<span class="righttext">', $board['num_posts'], '</span>
								</dd>';
	}

	echo '
							</dl>
							<div class="clear"></div>
						</div>
						<span class="botslice"><span></span></span>
					</div>
			</div>
		</div>
		<div class="flow_hidden">
			<div id="top_topics_replies">
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="ie6_header floatleft">
							<img src="', $settings['images_url'], '/stats_replies.gif" class="icon" alt="" /> ', $txt['top_topics_replies'], '
						</span>
					</h4>
				</div>
					<div class="windowbg2">
						<span class="topslice"><span></span></span>
						<div class="content">
							<dl class="stats">';

	foreach ($context['top_topics_replies'] as $topic)
	{
		echo '
								<dt>
									', $topic['link'], '
								</dt>
								<dd class="statsbar">';
		if (!empty($topic['post_percent']))
			echo '
									<div class="bar" style="width: ', $topic['post_percent'] + 4, 'px;">
										<div style="width: ', $topic['post_percent'], 'px;"></div>
									</div>';

		echo '
									<span class="righttext">' . $topic['num_replies'] . '</span>
								</dd>';
	}
	echo '
							</dl>
							<div class="clear"></div>
						</div>
						<span class="botslice"><span></span></span>
					</div>
			</div>

			<div id="top_topics_views">
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="ie6_header floatleft">
							<img src="', $settings['images_url'], '/stats_views.gif" class="icon" alt="" /> ', $txt['top_topics_views'], '
						</span>
					</h4>
				</div>
				<div class="windowbg2">
					<span class="topslice"><span></span></span>
					<div class="content">
						<dl class="stats">';

	foreach ($context['top_topics_views'] as $topic)
	{
		echo '
							<dt>', $topic['link'], '</dt>
							<dd class="statsbar">';

		if (!empty($topic['post_percent']))
			echo '
								<div class="bar" style="width: ', $topic['post_percent'] + 4, 'px;">
									<div style="width: ', $topic['post_percent'], 'px;"></div>
								</div>';

		echo '
								<span class="righttext">' . $topic['num_views'] . '</span>
							</dd>';
	}

	echo '
						</dl>
						<div class="clear"></div>
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>
		</div>
		<div class="flow_hidden">
			<div id="top_topics_starter">
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="ie6_header floatleft">
							<img src="', $settings['images_url'], '/stats_replies.gif" class="icon" alt="" /> ', $txt['top_starters'], '
						</span>
					</h4>
				</div>
				<div class="windowbg2">
					<span class="topslice"><span></span></span>
					<div class="content">
						<dl class="stats">';

	foreach ($context['top_starters'] as $poster)
	{
		echo '
							<dt>
								', $poster['link'], '
							</dt>
							<dd class="statsbar">';

		if (!empty($poster['post_percent']))
			echo '
								<div class="bar" style="width: ', $poster['post_percent'] + 4, 'px;">
									<div style="width: ', $poster['post_percent'], 'px;"></div>
								</div>';

		echo '
								<span class="righttext">', $poster['num_topics'], '</span>
							</dd>';
	}

	echo '
						</dl>
						<div class="clear"></div>
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>
			<div id="most_online">
				<div class="title_bar">
					<h4 class="titlebg">
						<span class="ie6_header floatleft">
							<img src="', $settings['images_url'], '/stats_views.gif" class="icon" alt="" /> ', $txt['most_time_online'], '
						</span>
					</h4>
				</div>
				<div class="windowbg2">
					<span class="topslice"><span></span></span>
					<div class="content">
						<dl class="stats">';

	foreach ($context['top_time_online'] as $poster)
	{
		echo '
							<dt>
								', $poster['link'], '
							</dt>
							<dd class="statsbar">';

		if (!empty($poster['time_percent']))
			echo '
								<div class="bar" style="width: ', $poster['time_percent'] + 4, 'px;">
									<div style="width: ', $poster['time_percent'], 'px;"></div>
								</div>';

		echo '
								<span>', $poster['time_online'], '</span>
							</dd>';
	}

	echo '
						</dl>
						<div class="clear"></div>
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>
		</div>
		<br class="clear" />
		<div class="flow_hidden">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="ie6_header floatleft">
						<img src="', $settings['images_url'], '/stats_history.gif" class="icon" alt="" /> ', $txt['forum_history'], '
					</span>
				</h3>
			</div>';

	if (!empty($context['yearly']))
	{
		echo '
		<table border="0" width="100%" cellspacing="1" cellpadding="4" class="table_grid" id="stats">
			<thead>
				<tr class="titlebg" valign="middle" align="center">
					<th class="first_th lefttext" width="25%">', $txt['yearly_summary'], '</th>
					<th width="15%">', $txt['stats_new_topics'], '</th>
					<th width="15%">', $txt['stats_new_posts'], '</th>
					<th width="15%">', $txt['stats_new_members'], '</th>
					<th', empty($modSettings['hitStats']) ? ' class="last_th"' : '', ' width="15%">', $txt['smf_stats_14'], '</th>';

		if (!empty($modSettings['hitStats']))
			echo '
					<th class="last_th">', $txt['page_views'], '</th>';

		echo '
				</tr>
			</thead>
			<tbody>';

		foreach ($context['yearly'] as $id => $year)
		{
			echo '
				<tr class="windowbg2" valign="middle" align="center" id="year_', $id, '">
					<th class="lefttext" width="25%">
						<img id="year_img_', $id, '" src="', $settings['images_url'], '/collapse.gif" alt="*" /> <a href="#year_', $id, '" id="year_link_', $id, '">', $year['year'], '</a>
					</th>
					<th width="15%">', $year['new_topics'], '</th>
					<th width="15%">', $year['new_posts'], '</th>
					<th width="15%">', $year['new_members'], '</th>
					<th width="15%">', $year['most_members_online'], '</th>';

			if (!empty($modSettings['hitStats']))
				echo '
					<th>', $year['hits'], '</th>';

			echo '
				</tr>';

			foreach ($year['months'] as $month)
			{
				echo '
				<tr class="windowbg2" valign="middle" align="center" id="tr_month_', $month['id'], '">
					<th class="stats_month">
						<img src="', $settings['images_url'], '/', $month['expanded'] ? 'collapse.gif' : 'expand.gif', '" alt="" id="img_', $month['id'], '" /> <a id="m', $month['id'], '" href="', $month['href'], '" onclick="return doingExpandCollapse;">', $month['month'], ' ', $month['year'], '</a>
					</th>
					<th width="15%">', $month['new_topics'], '</th>
					<th width="15%">', $month['new_posts'], '</th>
					<th width="15%">', $month['new_members'], '</th>
					<th width="15%">', $month['most_members_online'], '</th>';

				if (!empty($modSettings['hitStats']))
					echo '
					<th>', $month['hits'], '</th>';

				echo '
				</tr>';

				if ($month['expanded'])
				{
					foreach ($month['days'] as $day)
					{
						echo '
				<tr class="windowbg2" valign="middle" align="center" id="tr_day_', $day['year'], '-', $day['month'], '-', $day['day'], '">
					<td class="stats_day">', $day['year'], '-', $day['month'], '-', $day['day'], '</td>
					<td>', $day['new_topics'], '</td>
					<td>', $day['new_posts'], '</td>
					<td>', $day['new_members'], '</td>
					<td>', $day['most_members_online'], '</td>';

						if (!empty($modSettings['hitStats']))
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
		</div>
	</div>
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/stats.js"></script>
	<script type="text/javascript"><!-- // --><![CDATA[
		var oStatsCenter = new smf_StatsCenter({
			sTableId: \'stats\',

			reYearPattern: /year_(\d+)/,
			sYearImageCollapsed: \'expand.gif\',
			sYearImageExpanded: \'collapse.gif\',
			sYearImageIdPrefix: \'year_img_\',
			sYearLinkIdPrefix: \'year_link_\',

			reMonthPattern: /tr_month_(\d+)/,
			sMonthImageCollapsed: \'expand.gif\',
			sMonthImageExpanded: \'collapse.gif\',
			sMonthImageIdPrefix: \'img_\',
			sMonthLinkIdPrefix: \'m\',

			reDayPattern: /tr_day_(\d+-\d+-\d+)/,
			sDayRowClassname: \'windowbg2\',
			sDayRowIdPrefix: \'tr_day_\',

			aCollapsedYears: [';

		foreach ($context['collapsed_years'] as $id => $year)
		{
			echo '
				\'', $year, '\'', $id != count($context['collapsed_years']) - 1 ? ',' : '';
		}

		echo '
			],

			aDataCells: [
				\'date\',
				\'new_topics\',
				\'new_posts\',
				\'new_members\',
				\'most_members_online\'', empty($modSettings['hitStats']) ? '' : ',
				\'hits\'', '
			]
		});
	// ]]></script>';
	}
}

?>