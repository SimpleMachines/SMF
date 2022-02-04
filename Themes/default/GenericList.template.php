<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

/**
 * This template handles displaying a list
 *
 * @param string $list_id The list ID. If null, uses $context['default_list'].
 */
function template_show_list($list_id = null)
{
	global $context;

	// Get a shortcut to the current list.
	$list_id = $list_id === null ? (!empty($context['default_list']) ? $context['default_list'] : '') : $list_id;

	if (empty($list_id) || empty($context[$list_id]))
		return;

	$cur_list = &$context[$list_id];

	if (isset($cur_list['form']))
		echo '
	<form action="', $cur_list['form']['href'], '" method="post"', empty($cur_list['form']['name']) ? '' : ' name="' . $cur_list['form']['name'] . '" id="' . $cur_list['form']['name'] . '"', ' accept-charset="', $context['character_set'], '">';

	// Show the title of the table (if any).
	if (!empty($cur_list['title']))
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				', $cur_list['title'], '
			</h3>
		</div>';

	if (isset($cur_list['additional_rows']['after_title']))
	{
		echo '
		<div class="information flow_hidden">';

		template_additional_rows('after_title', $cur_list);

		echo '
		</div>';
	}

	if (isset($cur_list['additional_rows']['top_of_list']))
		template_additional_rows('top_of_list', $cur_list);

	if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['above_column_headers']))
	{
		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index']))
			echo '
		<div class="pagesection">
			<div class="pagelinks">', $cur_list['page_index'], '</div>
		</div>';

		if (isset($cur_list['additional_rows']['above_column_headers']))
			template_additional_rows('above_column_headers', $cur_list);
	}

	echo '
		<table class="table_grid" ', !empty($list_id) ? 'id="' . $list_id . '"' : '', ' ', !empty($cur_list['width']) ? ' style="width:' . $cur_list['width'] . '"' : '', '>';

	// Show the column headers.
	$header_count = count($cur_list['headers']);
	if (!($header_count < 2 && empty($cur_list['headers'][0]['label'])))
	{
		echo '
			<thead>
				<tr class="title_bar">';

		// Loop through each column and add a table header.
		foreach ($cur_list['headers'] as $col_header)
			echo '
					<th scope="col" id="header_', $list_id, '_', $col_header['id'], '" class="', $col_header['id'], empty($col_header['class']) ? '' : ' ' . $col_header['class'], '"', empty($col_header['style']) ? '' : ' style="' . $col_header['style'] . '"', empty($col_header['colspan']) ? '' : ' colspan="' . $col_header['colspan'] . '"', '>
						', empty($col_header['href']) ? '' : '<a href="' . $col_header['href'] . '" rel="nofollow">', empty($col_header['label']) ? '' : $col_header['label'], empty($col_header['href']) ? '' : (empty($col_header['sort_image']) ? '</a>' : ' <span class="main_icons sort_' . $col_header['sort_image'] . '"></span></a>'), '
					</th>';

		echo '
				</tr>
			</thead>';
	}

	echo '
			<tbody>';

	// Show a nice message informing there are no items in this list.
	if (empty($cur_list['rows']) && !empty($cur_list['no_items_label']))
		echo '
				<tr class="windowbg">
					<td colspan="', $cur_list['num_columns'], '" class="', !empty($cur_list['no_items_align']) ? $cur_list['no_items_align'] : 'centertext', '">
						', $cur_list['no_items_label'], '
					</td>
				</tr>';

	// Show the list rows.
	elseif (!empty($cur_list['rows']))
	{
		foreach ($cur_list['rows'] as $id => $row)
		{
			echo '
				<tr class="', empty($row['class']) ? 'windowbg' : $row['class'], '"', empty($row['style']) ? '' : ' style="' . $row['style'] . '"', ' id="list_', $list_id, '_', $id, '">';

			if (!empty($row['data']))
				foreach ($row['data'] as $row_id => $row_data)
					echo '
					<td class="', $row_id, empty($row_data['class']) ? '' : ' ' . $row_data['class'] . '', '"', empty($row_data['style']) ? '' : ' style="' . $row_data['style'] . '"', '>
						', $row_data['value'], '
					</td>';

			echo '
				</tr>';
		}
	}

	echo '
			</tbody>
		</table>';

	if ((!empty($cur_list['items_per_page']) && !empty($cur_list['page_index'])) || isset($cur_list['additional_rows']['below_table_data']))
	{
		echo '
		<div class="flow_auto">';

		// Show the page index (if this list doesn't intend to show all items).
		if (!empty($cur_list['items_per_page']) && !empty($cur_list['page_index']))
			echo '
			<div class="pagesection floatleft">
				<div class="pagelinks">', $cur_list['page_index'], '</div>
			</div>';


		if (isset($cur_list['additional_rows']['below_table_data']))
			template_additional_rows('below_table_data', $cur_list);
		echo '
		</div>';
	}

	if (isset($cur_list['additional_rows']['bottom_of_list']))
		template_additional_rows('bottom_of_list', $cur_list);

	if (isset($cur_list['form']))
	{
		foreach ($cur_list['form']['hidden_fields'] as $name => $value)
			echo '
		<input type="hidden" name="', $name, '" value="', $value, '">';

		if (isset($cur_list['form']['token']))
			echo '
		<input type="hidden" name="', $context[$cur_list['form']['token'] . '_token_var'], '" value="', $context[$cur_list['form']['token'] . '_token'], '">';

		echo '
	</form>';
	}

	if (isset($cur_list['javascript']))
		echo '
	<script>
		', $cur_list['javascript'], '
	</script>';
}

/**
 * This template displays additional rows above or below the list.
 *
 * @param string $row_position The position ('top', 'bottom', etc.)
 * @param array $cur_list An array with the data for the current list
 */
function template_additional_rows($row_position, $cur_list)
{
	foreach ($cur_list['additional_rows'][$row_position] as $row)
		echo '
			<div class="additional_row', empty($row['class']) ? '' : ' ' . $row['class'], '"', empty($row['style']) ? '' : ' style="' . $row['style'] . '"', '>
				', $row['value'], '
			</div>';
}

?>