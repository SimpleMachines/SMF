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

// Displays a sortable listing of all members registered on the forum.
function template_main()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Build the memberlist button array.
	$memberlist_buttons = array(
			'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist' . ';sa=all', 'active'=> true),
			'mlist_search' => array('text' => 'mlist_search', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist' . ';sa=search'),
		);

	echo '
	<div class="main_section" id="memberlist">
		<div class="cat_bar">
			<h4 class="catbg">
				<span class="floatleft">', $txt['members_list'], '</span>';
		if (!isset($context['old_search']))
				echo '
				<span class="floatright">', $context['letter_links'], '</span>';
		echo '
			</h4>
		</div>
		<div class="pagesection">
			', template_button_strip($memberlist_buttons, 'right'), '
			<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], '</div>
		</div>';

	echo '
		<div id="mlist" class="tborder topic_table">
			<table class="table_grid" cellspacing="0" width="100%">
			<thead>
				<tr class="catbg">';

	// Display each of the column headers of the table.
	foreach ($context['columns'] as $column)
	{
		// We're not able (through the template) to sort the search results right now...
		if (isset($context['old_search']))
			echo '
					<th scope="col" class="', isset($column['class']) ? ' ' . $column['class'] : '', '"', isset($column['width']) ? ' width="' . $column['width'] . '"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>
						', $column['label'], '</th>';
		// This is a selected column, so underline it or some such.
		elseif ($column['selected'])
			echo '
					<th scope="col" class="', isset($column['class']) ? ' ' . $column['class'] : '', '" style="width: auto;"' . (isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '') . ' nowrap="nowrap">
						<a href="' . $column['href'] . '" rel="nofollow">' . $column['label'] . ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" /></a></th>';
		// This is just some column... show the link and be done with it.
		else
			echo '
					<th scope="col" class="', isset($column['class']) ? ' ' . $column['class'] : '', '"', isset($column['width']) ? ' width="' . $column['width'] . '"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>
						', $column['link'], '</th>';
	}
	echo '
				</tr>
			</thead>
			<tbody>';

	// Assuming there are members loop through each one displaying their data.
	if (!empty($context['members']))
	{
		foreach ($context['members'] as $member)
		{
			echo '
				<tr ', empty($member['sort_letter']) ? '' : ' id="letter' . $member['sort_letter'] . '"', '>
					<td class="windowbg2">
						', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '" align="middle" />' : $member['online']['label'], $context['can_send_pm'] ? '</a>' : '', '
					</td>
					<td class="windowbg lefttext">', $member['link'], '</td>
					<td class="windowbg2">', $member['show_email'] == 'no' ? '' : '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . $settings['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '" /></a>', '</td>';

		if (!isset($context['disabled_fields']['website']))
			echo '
					<td class="windowbg">', $member['website']['url'] != '' ? '<a href="' . $member['website']['url'] . '" target="_blank" class="new_win"><img src="' . $settings['images_url'] . '/www.gif" alt="' . $member['website']['title'] . '" title="' . $member['website']['title'] . '" /></a>' : '', '</td>';

		// ICQ?
		if (!isset($context['disabled_fields']['icq']))
			echo '
					<td class="windowbg2">', $member['icq']['link'], '</td>';

		// AIM?
		if (!isset($context['disabled_fields']['aim']))
			echo '
					<td class="windowbg2">', $member['aim']['link'], '</td>';

		// YIM?
		if (!isset($context['disabled_fields']['yim']))
			echo '
					<td class="windowbg2">', $member['yim']['link'], '</td>';

		// MSN?
		if (!isset($context['disabled_fields']['msn']))
			echo '
					<td class="windowbg2">', $member['msn']['link'], '</td>';

		// Group and date.
		echo '
					<td class="windowbg lefttext">', empty($member['group']) ? $member['post_group'] : $member['group'], '</td>
					<td class="windowbg lefttext">', $member['registered_date'], '</td>';

		if (!isset($context['disabled_fields']['posts']))
		{
			echo '
					<td class="windowbg2" style="white-space: nowrap" width="15">', $member['posts'], '</td>
					<td class="windowbg statsbar" width="120">';

			if (!empty($member['post_percent']))
				echo '
						<div class="bar" style="width: ', $member['post_percent'] + 4, 'px;">
							<div style="width: ', $member['post_percent'], 'px;"></div>
						</div>';

			echo '
					</td>';
		}

		echo '
				</tr>';
		}
	}
	// No members?
	else
		echo '
				<tr>
					<td colspan="', $context['colspan'], '" class="windowbg">', $txt['search_no_results'], '</td>
				</tr>';

	// Show the page numbers again. (makes 'em easier to find!)
	echo '
			</tbody>
			</table>
		</div>';

	echo '
		<div class="pagesection">
			<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], '</div>';

	// If it is displaying the result of a search show a "search again" link to edit their criteria.
	if (isset($context['old_search']))
		echo '
			<div class="floatright">
				<a href="', $scripturl, '?action=mlist;sa=search;search=', $context['old_search_value'], '">', $txt['mlist_search_again'], '</a>
			</div>';
	echo '
		</div>
	</div>';

}

// A page allowing people to search the member list.
function template_search()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Build the memberlist button array.
	$memberlist_buttons = array(
			'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist' . ';sa=all'),
			'mlist_search' => array('text' => 'mlist_search', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist' . ';sa=search', 'active' => true),
		);

	// Start the submission form for the search!
	echo '
	<form action="', $scripturl, '?action=mlist;sa=search" method="post" accept-charset="', $context['character_set'], '">
		<div id="memberlist">
			<div class="cat_bar">
				<h3 class="catbg mlist">
					<span class="ie6_header floatleft">', !empty($settings['use_buttons']) ? '<img src="' . $settings['images_url'] . '/buttons/search.gif" alt="" class="icon" />' : '', $txt['mlist_search'], '</span>
				</h3>
			</div>
			<div class="pagesection">
				', template_button_strip($memberlist_buttons, 'right'), '
			</div>';
	// Display the input boxes for the form.
	echo '	<div id="memberlist_search" class="clear">
				<span class="upperframe"><span></span></span>
				<div class="roundframe">
					<div id="mlist_search" class="flow_hidden">
						<div id="search_term_input"><br />
							<strong>', $txt['search_for'], ':</strong>
							<input type="text" name="search" value="', $context['old_search'], '" size="35" class="input_text" /> <input type="submit" name="submit" value="' . $txt['search'] . '" class="button_submit" />
						</div>
						<span class="floatleft">';

	$count = 0;
	foreach ($context['search_fields'] as $id => $title)
	{
		echo '
							<label for="fields-', $id, '"><input type="checkbox" name="fields[]" id="fields-', $id, '" value="', $id, '" ', in_array($id, $context['search_defaults']) ? 'checked="checked"' : '', ' class="input_check" />', $title, '</label><br />';
	// Half way through?
		if (round(count($context['search_fields']) / 2) == ++$count)
			echo '
						</span>
						<span class="floatleft">';
	}
		echo '
						</span>
					</div>
				</div>
				<span class="lowerframe"><span></span></span>
			</div>
		</div>
	</form>';
}

?>