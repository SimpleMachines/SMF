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
		<table border="0" width="100%" cellspacing="1" cellpadding="4" class="bordercolor">
			<tr class="titlebg">
				<td align="center" colspan="4">', $context['page_title'], '</td>
			</tr>
			<tr>
				<td class="catbg" colspan="4"><strong>', $txt['general_stats'], '</strong></td>
			</tr><tr>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_info.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">
						<tr>
							<td nowrap="nowrap">', $txt['total_members'], ':</td>
							<td align="right">', $context['show_member_list'] ? '<a href="' . $scripturl . '?action=mlist">' . $context['num_members'] . '</a>' : $context['num_members'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['total_posts'], ':</td>
							<td align="right">', $context['num_posts'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['total_topics'], ':</td>
							<td align="right">', $context['num_topics'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['total_cats'], ':</td>
							<td align="right">', $context['num_categories'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['users_online'], ':</td>
							<td align="right">', $context['users_online'], '</td>
						</tr><tr>
							<td nowrap="nowrap" valign="top">', $txt['most_online'], ':</td>
							<td align="right">', $context['most_members_online']['number'], ' - ', $context['most_members_online']['date'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['users_online_today'], ':</td>
							<td align="right">', $context['online_today'], '</td>';
	if (!empty($modSettings['hitStats']))
		echo '
						</tr><tr>
							<td nowrap="nowrap">', $txt['num_hits'], ':</td>
							<td align="right">', $context['num_hits'], '</td>';
	echo '
						</tr>
					</table>
				</td>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_info.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">
						<tr>
							<td nowrap="nowrap">', $txt['average_members'], ':</td>
							<td align="right">', $context['average_members'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['average_posts'], ':</td>
							<td align="right">', $context['average_posts'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['average_topics'], ':</td>
							<td align="right">', $context['average_topics'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['total_boards'], ':</td>
							<td align="right">', $context['num_boards'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['latest_member'], ':</td>
							<td align="right">', $context['common_stats']['latest_member']['link'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['average_online'], ':</td>
							<td align="right">', $context['average_online'], '</td>
						</tr><tr>
							<td nowrap="nowrap">', $txt['gender_ratio'], ':</td>
							<td align="right">', $context['gender']['ratio'], '</td>';
	if (!empty($modSettings['hitStats']))
		echo '
						</tr><tr>
							<td nowrap="nowrap">', $txt['average_hits'], ':</td>
							<td align="right">', $context['average_hits'], '</td>';
	echo '
						</tr>
					</table>
				</td>
			</tr><tr>
				<td class="catbg" colspan="2" width="50%"><strong>', $txt['top_posters'], '</strong></td>
				<td class="catbg" colspan="2" width="50%"><strong>', $txt['top_boards'], '</strong></td>
			</tr><tr>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_posters.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" width="50%" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">';
	foreach ($context['top_posters'] as $poster)
		echo '
						<tr>
							<td width="60%" valign="top">', $poster['link'], '</td>
							<td width="20%" align="', $context['right_to_left'] ? 'right' : 'left', '" valign="top">', $poster['num_posts'] > 0 ? '<img src="' . $settings['images_url'] . '/bar.gif" width="' . $poster['post_percent'] . '" height="15" alt="" />' : '&nbsp;', '</td>
							<td width="20%" align="right" valign="top">', $poster['num_posts'], '</td>
						</tr>';
	echo '
					</table>
				</td>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_board.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" width="50%" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">';
	foreach ($context['top_boards'] as $board)
		echo '
						<tr>
							<td width="60%" valign="top">', $board['link'], '</td>
							<td width="20%" align="', $context['right_to_left'] ? 'right' : 'left', '" valign="top">', $board['num_posts'] > 0 ? '<img src="' . $settings['images_url'] . '/bar.gif" width="' . $board['post_percent'] . '" height="15" alt="" />' : '&nbsp;', '</td>
							<td width="20%" align="right" valign="top">', $board['num_posts'], '</td>
						</tr>';
	echo '
					</table>
				</td>
			</tr><tr>
				<td class="catbg" colspan="2" width="50%"><strong>', $txt['top_topics_replies'], '</strong></td>
				<td class="catbg" colspan="2" width="50%"><strong>', $txt['top_topics_views'], '</strong></td>
			</tr><tr>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_replies.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" width="50%" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">';
	foreach ($context['top_topics_replies'] as $topic)
		echo '
						<tr>
							<td width="60%" valign="top">', $topic['link'], '</td>
							<td width="20%" align="', $context['right_to_left'] ? 'right' : 'left', '" valign="top">', $topic['num_replies'] > 0 ? '<img src="' . $settings['images_url'] . '/bar.gif" width="' . $topic['post_percent'] . '" height="15" alt="" />' : '&nbsp;', '</td>
							<td width="20%" align="right" valign="top">', $topic['num_replies'], '</td>
						</tr>';
	echo '
					</table>
				</td>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_views.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" width="50%" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">';
	foreach ($context['top_topics_views'] as $topic)
		echo '
						<tr>
							<td width="60%" valign="top">', $topic['link'], '</td>
							<td width="20%" align="', $context['right_to_left'] ? 'right' : 'left', '" valign="top">', $topic['num_views'] > 0 ? '<img src="' . $settings['images_url'] . '/bar.gif" width="' . $topic['post_percent'] . '" height="15" alt="" />' : '&nbsp;', '</td>
							<td width="20%" align="right" valign="top">', $topic['num_views'], '</td>
						</tr>';
	echo '
					</table>
				</td>
			</tr><tr>
				<td class="catbg" colspan="2" width="50%"><strong>', $txt['top_starters'], '</strong></td>
				<td class="catbg" colspan="2" width="50%"><strong>', $txt['most_time_online'], '</strong></td>
			</tr><tr>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_replies.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" width="50%" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">';
	foreach ($context['top_starters'] as $poster)
		echo '
						<tr>
							<td width="60%" valign="top">', $poster['link'], '</td>
							<td width="20%" align="', $context['right_to_left'] ? 'right' : 'left', '" valign="top">', $poster['num_topics'] > 0 ? '<img src="' . $settings['images_url'] . '/bar.gif" width="' . $poster['post_percent'] . '" height="15" alt="" />' : '&nbsp;', '</td>
							<td width="20%" align="right" valign="top">', $poster['num_topics'], '</td>
						</tr>';
	echo '
					</table>
				</td>
				<td class="windowbg" width="20" valign="middle" align="center" nowrap="nowrap"><img src="', $settings['images_url'], '/stats_views.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" width="50%" valign="top">
					<table border="0" cellpadding="1" cellspacing="0" width="100%">';
	foreach ($context['top_time_online'] as $poster)
		echo '
						<tr>
							<td width="60%" valign="top">', $poster['link'], '</td>
							<td width="20%" align="', $context['right_to_left'] ? 'right' : 'left', '" valign="top">', $poster['time_online'] > 0 ? '<img src="' . $settings['images_url'] . '/bar.gif" width="' . $poster['time_percent'] . '" height="15" alt="" />' : '&nbsp;', '</td>
							<td width="20%" align="right" valign="top" nowrap="nowrap">', $poster['time_online'], '</td>
						</tr>';
	echo '
					</table>
				</td>
			</tr><tr>
				<td class="catbg" colspan="4"><strong>', $txt['forum_history'], '</strong></td>
			</tr><tr>
				<td class="windowbg" width="20" valign="middle" align="center"><img src="', $settings['images_url'], '/stats_history.gif" width="20" height="20" alt="" /></td>
				<td class="windowbg2" colspan="4">';

	if (!empty($context['yearly']))
	{
			echo '
					<table border="0" width="100%" cellspacing="1" cellpadding="4" class="tborder" style="margin-bottom: 1ex;" id="stats">
						<tr class="titlebg" valign="middle" align="center">
							<td width="25%">', $txt['yearly_summary'], '</td>
							<td width="15%">', $txt['stats_new_topics'], '</td>
							<td width="15%">', $txt['stats_new_posts'], '</td>
							<td width="15%">', $txt['stats_new_members'], '</td>
							<td width="15%">', $txt['smf_stats_14'], '</td>';

		if (!empty($modSettings['hitStats']))
			echo '
							<td>', $txt['page_views'], '</td>';
		echo '
						</tr>';

		foreach ($context['yearly'] as $id => $year)
		{
			echo '
						<tr class="windowbg2" valign="middle" id="year_', $id, '">
							<th class="lefttext" width="25%">
								<img id="year_img_', $id, '" src="', $settings['images_url'], '/collapse.gif" alt="*" /> <a href="#year_', $id, '" id="year_link_', $id, '">', $year['year'], '</a>
							</th>
							<th align="center" width="15%">', $year['new_topics'], '</th>
							<th align="center" width="15%">', $year['new_posts'], '</th>
							<th align="center" width="15%">', $year['new_members'], '</th>
							<th align="center" width="15%">', $year['most_members_online'], '</th>';
			if (!empty($modSettings['hitStats']))
				echo '
							<th align="center">', $year['hits'], '</th>';
			echo '
						</tr>';

			foreach ($year['months'] as $month)
			{
				echo '
							<tr class="windowbg2" valign="middle" id="tr_month_', $month['id'], '">
								<th class="lefttext" width="25%" style="padding-', ($context['right_to_left'] ? 'right' : 'left'), ': 3ex;">
									<img src="', $settings['images_url'], '/', $month['expanded'] ? 'collapse.gif' : 'expand.gif', '" alt="" id="img_', $month['id'], '" /> <a id="m', $month['id'], '" href="', $month['href'], '" onclick="return doingExpandCollapse;">', $month['month'], ' ', $month['year'], '</a>
								</th>
								<th align="center" width="15%">', $month['new_topics'], '</th>
								<th align="center" width="15%">', $month['new_posts'], '</th>
								<th align="center" width="15%">', $month['new_members'], '</th>
								<th align="center" width="15%">', $month['most_members_online'], '</th>';
				if (!empty($modSettings['hitStats']))
					echo '
								<th align="center">', $month['hits'], '</th>';
				echo '
							</tr>';

				if ($month['expanded'])
				{
					foreach ($month['days'] as $day)
					{
						echo '
							<tr class="windowbg2" valign="middle" align="left" id="tr_day_', $day['year'], '-', $day['month'], '-', $day['day'], '">
								<td class="lefttext" style="padding-', ($context['right_to_left'] ? 'right' : 'left'), ': 6ex;">', $day['year'], '-', $day['month'], '-', $day['day'], '</td>
								<td align="center">', $day['new_topics'], '</td>
								<td align="center">', $day['new_posts'], '</td>
								<td align="center">', $day['new_members'], '</td>
								<td align="center">', $day['most_members_online'], '</td>';
						if (!empty($modSettings['hitStats']))
							echo '
								<td align="center">', $day['hits'], '</td>';
						echo '
							</tr>';
					}
				}
			}
		}

		echo '
					</table>
				</td></tr>
			</table>
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
				\'most_members_online\'', empty($modSettings['hitStats']) ? '' :',
				\'hits\'', '
			]
		});
	// ]]></script>';
	}
}

?>