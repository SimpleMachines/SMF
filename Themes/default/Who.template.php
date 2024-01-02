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
 * This handles the Who's Online page
 */
function template_main()
{
	// Display the table header and linktree.
	echo '
	<div class="main_section" id="whos_online">
		<form action="', Config::$scripturl, '?action=who" method="post" id="whoFilter" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['who_title'], '</h3>
			</div>
			<div id="mlist">
				<div class="pagesection">
					<div class="pagelinks floatleft">', Utils::$context['page_index'], '</div>
					<div class="selectbox floatright" id="upper_show">
						', Lang::$txt['who_show'], '
						<select name="show_top" onchange="document.forms.whoFilter.show.value = this.value; document.forms.whoFilter.submit();">';

	foreach (Utils::$context['show_methods'] as $value => $label)
		echo '
							<option value="', $value, '" ', $value == Utils::$context['show_by'] ? ' selected' : '', '>', $label, '</option>';
	echo '
						</select>
						<noscript>
							<input type="submit" name="submit_top" value="', Lang::$txt['go'], '" class="button">
						</noscript>
					</div>
				</div>
				<table class="table_grid">
					<thead>
						<tr class="title_bar">
							<th scope="col" class="lefttext" style="width: 40%;"><a href="', Config::$scripturl, '?action=who;start=', Utils::$context['start'], ';show=', Utils::$context['show_by'], ';sort=user', Utils::$context['sort_direction'] != 'down' && Utils::$context['sort_by'] == 'user' ? '' : ';asc', '" rel="nofollow">', Lang::$txt['who_user'], Utils::$context['sort_by'] == 'user' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a></th>
							<th scope="col" class="lefttext time" style="width: 10%;"><a href="', Config::$scripturl, '?action=who;start=', Utils::$context['start'], ';show=', Utils::$context['show_by'], ';sort=time', Utils::$context['sort_direction'] == 'down' && Utils::$context['sort_by'] == 'time' ? ';asc' : '', '" rel="nofollow">', Lang::$txt['who_time'], Utils::$context['sort_by'] == 'time' ? '<span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '', '</a></th>
							<th scope="col" class="lefttext half_table">', Lang::$txt['who_action'], '</th>
						</tr>
					</thead>
					<tbody>';

	foreach (Utils::$context['members'] as $member)
	{
		echo '
						<tr class="windowbg">
							<td>';

		// Guests can't be messaged.
		if (!$member['is_guest'])
			echo '
								<span class="contact_info floatright">
									', Utils::$context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . Lang::$txt['pm_online'] . '">' : '', Theme::$current->settings['use_image_buttons'] ? '<span class="main_icons im_' . ($member['online']['is_online'] == 1 ? 'on' : 'off') . '" title="' . Lang::$txt['pm_online'] . '"></span>' : $member['online']['label'], Utils::$context['can_send_pm'] ? '</a>' : '', '
								</span>';

		echo '
								<span class="member', $member['is_hidden'] ? ' hidden' : '', '">
									', $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $member['name']) . '"' . (empty($member['color']) ? '' : ' style="color: ' . $member['color'] . ';"') . '>' . $member['name'] . '</a>', '
								</span>';

		if (!empty($member['ip']))
			echo '
								(<a href="' . Config::$scripturl . '?action=', ($member['is_guest'] ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $member['id']), ';searchip=' . $member['ip'] . '">' . str_replace(':', ':&ZeroWidthSpace;', $member['ip']) . '</a>)';

		echo '
							</td>
							<td class="time">', $member['time'], '</td>
							<td>';

		if (is_array($member['action']))
		{
			$tag = !empty($member['action']['tag']) ? $member['action']['tag'] : 'span';

			echo '
								<', $tag, !empty($member['action']['class']) ? ' class="' . $member['action']['class'] . '"' : '', '>
									', Lang::$txt[$member['action']['label']], (!empty($member['action']['error_message']) ? $member['action']['error_message'] : ''), '
								</', $tag, '>';
		}
		else
			echo $member['action'];

		echo '
							</td>
						</tr>';
	}

	// No members?
	if (empty(Utils::$context['members']))
		echo '
						<tr class="windowbg">
							<td colspan="3">
							', Lang::$txt['who_no_online_' . (Utils::$context['show_by'] == 'guests' || Utils::$context['show_by'] == 'spiders' ? Utils::$context['show_by'] : 'members')], '
							</td>
						</tr>';

	echo '
					</tbody>
				</table>
				<div class="pagesection" id="lower_pagesection">
					<div class="pagelinks floatleft" id="lower_pagelinks">', Utils::$context['page_index'], '</div>
					<div class="selectbox floatright">
						', Lang::$txt['who_show'], '
						<select name="show" onchange="document.forms.whoFilter.submit();">';

	foreach (Utils::$context['show_methods'] as $value => $label)
		echo '
							<option value="', $value, '" ', $value == Utils::$context['show_by'] ? ' selected' : '', '>', $label, '</option>';
	echo '
						</select>
						<noscript>
							<input type="submit" value="', Lang::$txt['go'], '" class="button">
						</noscript>
					</div>
				</div><!-- #lower_pagesection -->
			</div><!-- #mlist -->
		</form>
	</div><!-- #whos_online -->';
}

/**
 * This displays a nice credits page
 */
function template_credits()
{
	// The most important part - the credits :P.
	echo '
	<div class="main_section" id="credits">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['credits'], '</h3>
		</div>';

	foreach (Utils::$context['credits'] as $section)
	{
		if (isset($section['pretext']))
			echo '
		<div class="windowbg">
			<p>', $section['pretext'], '</p>
		</div>';

		if (isset($section['title']))
			echo '
		<div class="cat_bar">
			<h3 class="catbg">', $section['title'], '</h3>
		</div>';

		echo '
		<div class="windowbg">
			<dl>';

		foreach ($section['groups'] as $group)
		{
			echo '
				<dt>
					', isset($group['title']) ? '<strong>' . $group['title'] . '</strong>' : '', '
				</dt>
				<dd>';

			$names = Lang::sentenceList($group['members']);
			echo sprintf(Lang::$txt['credits_list'], $names);

			echo '
				</dd>';
		}

		echo '
			</dl>';

		if (isset($section['posttext']))
			echo '
				<p class="posttext">', $section['posttext'], '</p>';

		echo '
		</div>';
	}

	// Other software and graphics
	if (!empty(Utils::$context['credits_software_graphics']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['credits_software_graphics'], '</h3>
		</div>
		<div class="windowbg">';

		if (!empty(Utils::$context['credits_software_graphics']['graphics']))
			echo '
			<dl>
				<dt><strong>', Lang::$txt['credits_graphics'], '</strong></dt>
				<dd>', implode('</dd><dd>', Utils::$context['credits_software_graphics']['graphics']), '</dd>
			</dl>';

		if (!empty(Utils::$context['credits_software_graphics']['software']))
			echo '
			<dl>
				<dt><strong>', Lang::$txt['credits_software'], '</strong></dt>
				<dd>', implode('</dd><dd>', Utils::$context['credits_software_graphics']['software']), '</dd>
			</dl>';

		if (!empty(Utils::$context['credits_software_graphics']['fonts']))
			echo '
			<dl>
				<dt><strong>', Lang::$txt['credits_fonts'], '</strong></dt>
				<dd>', implode('</dd><dd>', Utils::$context['credits_software_graphics']['fonts']), '</dd>
			</dl>';
		echo '
		</div>';
	}

	// How about Modifications, we all love em
	if (!empty(Utils::$context['credits_modifications']) || !empty(Utils::$context['copyrights']['mods']))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['credits_modifications'], '</h3>
		</div>
		<div class="windowbg">
			<ul>';

		// Display the credits.
		if (!empty(Utils::$context['credits_modifications']))
			echo '
				<li>', implode('</li><li>', Utils::$context['credits_modifications']), '</li>';

		// Legacy.
		if (!empty(Utils::$context['copyrights']['mods']))
			echo '
				<li>', implode('</li><li>', Utils::$context['copyrights']['mods']), '</li>';

		echo '
			</ul>
		</div>';
	}

	// SMF itself
	echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['credits_forum'], ' ', Lang::$txt['credits_copyright'], '</h3>
		</div>
		<div class="windowbg">
			', Utils::$context['copyrights']['smf'], '
		</div>
	</div><!-- #credits -->';
}

?>