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

// Choose which type of report to run?
function template_report_type()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=reports" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['generate_reports'], '</h3>
			</div>
			<div class="information">
				', $txt['generate_reports_desc'], '
			</div>
			<div class="cat_bar">
				<h3 class="catbg">', $txt['generate_reports_type'], '</h3>
			</div>
			<div class="windowbg">
				<span class="topslice"><span></span></span>
				<div class="content">
					<dl class="generate_report">';

	// Go through each type of report they can run.
	foreach ($context['report_types'] as $type)
	{
		echo '
						<dt>
							<input type="radio" id="rt_', $type['id'], '" name="rt" value="', $type['id'], '"', $type['is_first'] ? ' checked="checked"' : '', ' class="input_radio" />
							<strong><label for="rt_', $type['id'], '">', $type['title'], '</label></strong>
						</dt>';
		if (isset($type['description']))
			echo '
						<dd>', $type['description'], '</dd>';
	}
		echo '
					</dl>
					<div class="righttext">
						<input type="submit" name="continue" value="', $txt['generate_reports_continue'], '" class="button_submit" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					</div>
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</form>
	</div>
	<br class="clear" />';
}

// This is the standard template for showing reports in.
function template_main()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Build the reports button array.
	$report_buttons = array(
			'generate_reports' => array('text' => 'generate_reports', 'image' => 'print.gif', 'lang' => true, 'url' => $scripturl . '?action=admin;area=reports', 'active' => true),
			'print' => array('text' => 'print', 'image' => 'print.gif', 'lang' => true, 'url' => $scripturl . '?action=admin;area=reports;rt=' . $context['report_type']. ';st=print', 'custom' => 'target="_blank"'),
		);

	echo '
	<div id="admincenter">
		<div class="title_bar">
			<h3 class="titlebg">', $txt['results'], '</h3>
		</div>
		<div id="report_buttons">';

	if (!empty($report_buttons) && !empty($settings['use_tabs']))
		template_button_strip($report_buttons, 'right');

	echo '
		</div>';

	// Go through each table!
	foreach ($context['tables'] as $table)
	{
		echo '
		<table class="table_grid" width="100%">';

		if (!empty($table['title']))
			echo '
			<thead>
				<tr class="catbg">
					<th scope="col" colspan="', $table['column_count'], '">', $table['title'], '</th>
				</tr>
			</thead>
			<tbody>';

		// Now do each row!
		$row_number = 0;
		$alternate = false;
		foreach ($table['data'] as $row)
		{
			if ($row_number == 0 && !empty($table['shading']['top']))
				echo '
				<tr class="windowbg table_caption">';
			else
				echo '
				<tr class="', !empty($row[0]['separator']) ? 'catbg' : ($alternate ? 'windowbg' : 'windowbg2'), '" valign="top">';

			// Now do each column.
			$column_number = 0;

			foreach ($row as $key => $data)
			{
				// If this is a special separator, skip over!
				if (!empty($data['separator']) && $column_number == 0)
				{
					echo '
					<td colspan="', $table['column_count'], '" class="smalltext">
						', $data['v'], ':
					</td>';
					break;
				}

				// Shaded?
				if ($column_number == 0 && !empty($table['shading']['left']))
					echo '
					<td align="', $table['align']['shaded'], '" class="table_caption"', $table['width']['shaded'] != 'auto' ? ' width="' . $table['width']['shaded'] . '"' : '', '>
						', $data['v'] == $table['default_value'] ? '' : ($data['v'] . (empty($data['v']) ? '' : ':')), '
					</td>';
				else
					echo '
					<td class="smalltext" align="', $table['align']['normal'], '"', $table['width']['normal'] != 'auto' ? ' width="' . $table['width']['normal'] . '"' : '', !empty($data['style']) ? ' style="' . $data['style'] . '"' : '', '>
						', $data['v'], '
					</td>';

				$column_number++;
			}

			echo '
				</tr>';

			$row_number++;
			$alternate = !$alternate;
		}
		echo '
			</tbody>
		</table>';
	}
	echo '
	</div>
	<br class="clear" />';
}

// Header of the print page!
function template_print_above()
{
	global $context, $settings, $options, $txt;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['default_theme_url'], '/css/report.css" />
	</head>
	<body>';
}

function template_print()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Go through each table!
	foreach ($context['tables'] as $table)
	{
		echo '
		<div style="overflow: visible;', $table['max_width'] != 'auto' ? ' width: ' . $table['max_width'] . 'px;' : '', '">
			<table border="0" cellspacing="1" cellpadding="4" width="100%" class="bordercolor">';

		if (!empty($table['title']))
			echo '
				<tr class="catbg">
					<td colspan="', $table['column_count'], '">
						', $table['title'], '
					</td>
				</tr>';

		// Now do each row!
		$alternate = false;
		$row_number = 0;
		foreach ($table['data'] as $row)
		{
			if ($row_number == 0 && !empty($table['shading']['top']))
				echo '
				<tr class="titlebg" valign="top">';
			else
				echo '
				<tr class="', $alternate ? 'windowbg' : 'windowbg2', '" valign="top">';

			// Now do each column!!
			$column_number = 0;
			foreach ($row as $key => $data)
			{
				// If this is a special separator, skip over!
				if (!empty($data['separator']) && $column_number == 0)
				{
					echo '
					<td colspan="', $table['column_count'], '" class="catbg">
						<strong>', $data['v'], ':</strong>
					</td>';
					break;
				}

				// Shaded?
				if ($column_number == 0 && !empty($table['shading']['left']))
					echo '
					<td align="', $table['align']['shaded'], '" class="titlebg"', $table['width']['shaded'] != 'auto' ? ' width="' . $table['width']['shaded'] . '"' : '', '>
						', $data['v'] == $table['default_value'] ? '' : ($data['v'] . (empty($data['v']) ? '' : ':')), '
					</td>';
				else
					echo '
					<td align="', $table['align']['normal'], '"', $table['width']['normal'] != 'auto' ? ' width="' . $table['width']['normal'] . '"' : '', !empty($data['style']) ? ' style="' . $data['style'] . '"' : '', '>
						', $data['v'], '
					</td>';

				$column_number++;
			}

			echo '
				</tr>';

			$row_number++;
			$alternate = !$alternate;
		}
		echo '
			</table>
		</div><br />';
	}
}

// Footer of the print page.
function template_print_below()
{
	global $context, $settings, $options;

	echo '
		<div class="copyright">', theme_copyright(), '</div>
	</body>
</html>';
}

?>