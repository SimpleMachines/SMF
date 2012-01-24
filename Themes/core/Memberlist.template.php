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
		'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist' . ';sa=all', 'active' => true),
		'mlist_search' => array('text' => 'mlist_search', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist;sa=search'),
	);

	echo '
	<div class="main_section clearfix" id="memberlist">
		<div id="modbuttons_top" class="modbuttons clearfix margintop">
			<div class="floatleft middletext">
				', $txt['pages'], ': ', $context['page_index'], '
			</div>
			', template_button_strip($memberlist_buttons, 'bottom'), '
		</div>';

	echo '
		<div id="mlist" class="tborder topic_table">
			<h4 class="catbg headerpadding clearfix">
				<span class="floatleft">', $txt['members_list'], '</span>';
	if (!isset($context['old_search']))
		echo '
				<span class="floatright">', $context['letter_links'], '</span>';
	echo '
			</h4>
			<table class="bordercolor boardsframe" cellspacing="1" cellpadding="4" width="100%">
			<thead>
				<tr class="titlebg">';

	// Display each of the column headers of the table.
	foreach ($context['columns'] as $column)
	{
		// We're not able (through the template) to sort the search results right now...
		if (isset($context['old_search']))
			echo '
					<th class="headerpadding" scope="col" ', isset($column['width']) ? ' width="' . $column['width'] . '"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>
						', $column['label'], '</th>';
		// This is a selected column, so underline it or some such.
		elseif ($column['selected'])
			echo '
					<th class="headerpadding" scope="col" style="width: auto;"' . (isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '') . ' nowrap="nowrap">
						<a href="' . $column['href'] . '" rel="nofollow">' . $column['label'] . ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" /></a></th>';
		// This is just some column... show the link and be done with it.
		else
			echo '
					<th class="headerpadding" scope="col" ', isset($column['width']) ? ' width="' . $column['width'] . '"' : '', isset($column['colspan']) ? ' colspan="' . $column['colspan'] . '"' : '', '>
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
					<td align="center" class="windowbg2">
						', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['text'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '" align="middle" />' : $member['online']['label'], $context['can_send_pm'] ? '</a>' : '', '
					</td>
					<td class="windowbg" align="', $context['right_to_left'] ? 'right' : 'left', '">', $member['link'], '</td>
					<td class="windowbg2" align="center">', $member['show_email'] == 'no' ? '' : '<a href="' . $scripturl . '?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . $settings['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '" /></a>', '</td>';

		if (!isset($context['disabled_fields']['website']))
			echo '
					<td align="center" class="windowbg">', $member['website']['url'] != '' ? '<a href="' . $member['website']['url'] . '" target="_blank" class="new_win"><img src="' . $settings['images_url'] . '/www.gif" alt="' . $member['website']['title'] . '" title="' . $member['website']['title'] . '" /></a>' : '', '</td>';

		// ICQ?
		if (!isset($context['disabled_fields']['icq']))
			echo '
					<td align="center" class="windowbg2">', $member['icq']['link'], '</td>';

		// AIM?
		if (!isset($context['disabled_fields']['aim']))
			echo '
					<td align="center" class="windowbg2">', $member['aim']['link'], '</td>';

		// YIM?
		if (!isset($context['disabled_fields']['yim']))
			echo '
					<td align="center" class="windowbg2">', $member['yim']['link'], '</td>';

		// MSN?
		if (!isset($context['disabled_fields']['msn']))
			echo '
					<td align="center" class="windowbg2">', $member['msn']['link'], '</td>';

		// Group and date.
		echo '
					<td class="windowbg" align="', $context['right_to_left'] ? 'right' : 'left', '">', empty($member['group']) ? $member['post_group'] : $member['group'], '</td>
					<td align="center" class="windowbg">', $member['registered_date'], '</td>';

		if (!isset($context['disabled_fields']['posts']))
			echo '
					<td class="windowbg2" align="', $context['right_to_left'] ? 'left' : 'right', '" width="15">', $member['posts'], '</td>
					<td class="windowbg" width="100" align="', $context['right_to_left'] ? 'right' : 'left', '">
						', $member['posts'] > 0 ? '<img src="' . $settings['images_url'] . '/bar.gif" width="' . $member['post_percent'] . '" height="15" alt="" />' : '', '
					</td>';

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
		<div class="middletext clearfix">
			<div class="floatleft">', $txt['pages'], ': ', $context['page_index'], '</div>';

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
	$membersearch_buttons = array(
			'view_all_members' => array('text' => 'view_all_members', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist;sa=all'),
			'mlist_search' => array('text' => 'mlist_search', 'image' => 'mlist.gif', 'lang' => true, 'url' => $scripturl . '?action=mlist;sa=search', 'active' => true),
	);

	// Start the submission form for the search!
	echo '
	<form action="', $scripturl, '?action=mlist;sa=search" method="post" accept-charset="', $context['character_set'], '">
		<div id="memberlist">
			<div id="modbuttons_top" class="modbuttons clearfix margintop">
				', template_button_strip($membersearch_buttons, 'right'), '
			</div>
			<div class="tborder">
				<h3 class="titlebg headerpadding clearfix">
					', !empty($settings['use_buttons']) ? '<img src="' . $settings['images_url'] . '/buttons/search.gif" alt="" />' : '', $txt['mlist_search'], '
				</h3>';

	// Display the input boxes for the form.
	echo '
				<div class="windowbg2">
					<span id="mlist_search" class="windowbg2 largepadding clearfix">
						<span class="enhanced">
							<strong>', $txt['search_for'], ':</strong> <input type="text" name="search" value="', $context['old_search'], '" size="35" class="input_text" /> <input type="submit" name="submit" value="' . $txt['search'] . '" style="margin-left: 20px;" class="button_submit" />
						</span>
						<span class="floatleft">';

	$count = 0;
	foreach ($context['search_fields'] as $id => $title)
	{
		echo '
							<label for="fields-', $id, '"><input type="checkbox" name="fields[]" id="fields-', $id, '" value="', $id, '" ', in_array($id, $context['search_defaults']) ? 'checked="checked"' : '', ' class="input_check" /> ', $title, '</label><br />';
		// Halfway through?
		if (round(count($context['search_fields']) / 2) == ++$count)
			echo '
						</span>
						<span class="floatright">';
	}
		echo '
						</span>
					</span>
				</div>
			</div>
		</div>
	</form>';
}

?>