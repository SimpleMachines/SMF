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

// !!!
/*	This template file contains only the sub template fatal_error. It is
	shown when an error occurs, and should show at least a back button and
	$context['error_message'].
*/

// Show an error message.....
function template_fatal_error()
{
	global $context, $settings, $options, $txt;

	echo '
	<div id="fatal_error">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['error_title'], '
			</h3>
		</div>
		<div class="windowbg">
			<span class="topslice"><span></span></span>
			<div class="padding">', $context['error_message'], '</div>
			<span class="botslice"><span></span></span>
		</div>
	</div>';

	// Show a back button (using javascript.)
	echo '
	<div class="centertext"><a href="javascript:history.go(-1)">', $txt['back'], '</a></div>';
}

function template_error_log()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
		<form action="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="', $context['character_set'], '" onsubmit="if (lastClicked == \'remove_all\' &amp;&amp; !confirm(\'', $txt['sure_about_errorlog_remove'], '\')) return false; else return true;">
			<script type="text/javascript"><!-- // --><![CDATA[
				var lastClicked = "";
			// ]]></script>
			<div class="title_bar clear_right">
				<h3 class="titlebg">
					<span class="ie6_header floatleft">
						<a href="', $scripturl, '?action=helpadmin;help=error_log" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" align="top" /></a> ', $txt['errlog'], '
					</span>
				</h3>
			</div>
			<table border="0" cellspacing="1" class="table_grid" id="error_log">
				<tr>
					<td colspan="3" class="windowbg">
						&nbsp;&nbsp;', $txt['apply_filter_of_type'], ':';

	$error_types = array();
	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="" /> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'style="font-weight: bold;"' : '') . ' title="' . $details['description'] . '">' . $details['label'] . '</a>';

	echo '
						', implode('&nbsp;|&nbsp;', $error_types), '
					</td>
				</tr>
				<tr>
					<td colspan="3" class="windowbg">
						&nbsp;&nbsp;', $txt['pages'], ': ', $context['page_index'], '
					</td>
				</tr>';

	if ($context['has_filter'])
		echo '
				<tr>
					<td colspan="3" class="windowbg">
						<strong>', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], ' (<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>)
					</td>
				</tr>';

	if (!empty($context['errors']))
		echo '
				<tr class="titlebg">
					<td colspan="3" class="lefttext">
						<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" onclick="lastClicked = \'remove_selection\';" class="button_submit" /> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="lastClicked = \'remove_all\';" class="button_submit" /></div>
						<label for="check_all1"><input type="checkbox" id="check_all1" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all2.checked = this.checked;" class="input_check" /><strong>', $txt['check_all'], '</strong></label>
					</td>
				</tr>';

	foreach ($context['errors'] as $error)
	{
		echo '
				<tr class="windowbg', $error['alternate'] ? '2' : '', '">
					<td rowspan="2" class="checkbox_column">
						<input type="checkbox" name="delete[]" value="', $error['id'], '" class="input_check" />
					</td>
					<td class="half_width">
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><img src="', $settings['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '" /></a>
						<strong>', $error['member']['link'], '</strong><br />
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><img src="', $settings['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '" /></a>
						<strong><a href="', $scripturl, '?action=trackip;searchip=', $error['member']['ip'], '">', $error['member']['ip'], '</a></strong>&nbsp;&nbsp;
						<br />&nbsp;
					</td>
					<td class="half_width">
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '"><img src="', $settings['images_url'], '/sort_', $context['sort_direction'], '.gif" alt="', $txt['reverse_direction'], '" /></a>
						', $error['time'], '
						<br />';

		if ($error['member']['session'] != '')
			echo '
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '"><img src="', $settings['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '" /></a>
						', $error['member']['session'], '
						<br />';

		echo '
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><img src="', $settings['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '" /></a>
						', $txt['error_type'], ': ', $error['error_type']['name'], '
					</td>
				</tr>
				<tr class="windowbg', $error['alternate'] ? '2' : '', '">
					<td colspan="2">
						<div class="clear_left floatleft"><a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><img src="', $settings['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '" /></a></div>
						<div class="floatleft marginleft"><a href="', $error['url']['html'], '">', $error['url']['html'], '</a></div>
						<div class="clear_left floatleft"><a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"><img src="', $settings['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '" /></a></div>
						<div class="floatleft marginleft">', $error['message']['html'], '</div>';

		if (!empty($error['file']))
			echo '
						<div class="clear_left floatleft"><a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"><img src="', $settings['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '" /></a></div>
						<div class="floatleft marginleft">
							', $txt['file'], ': ', $error['file']['link'], '<br />
							', $txt['line'], ': ', $error['file']['line'], '
						</div>';

		echo '
					</td>
				</tr>';
	}

	if (!empty($context['errors']))
		echo '
				<tr class="titlebg">
					<td colspan="3" class="lefttext">
						<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" onclick="lastClicked = \'remove_selection\';" class="button_submit" /> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="lastClicked = \'remove_all\';" class="button_submit" /></div>
						&nbsp;<label for="check_all2"><input type="checkbox" id="check_all2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all1.checked = this.checked;" class="input_check" /><strong>', $txt['check_all'], '</strong></label>
					</td>
				</tr>';
	else
		echo '
				<tr>
					<td colspan="3" class="windowbg2">', $txt['errlog_no_entries'], '</td>
				</tr>';

	echo '
				<tr>
					<td colspan="3" class="windowbg">
						&nbsp;&nbsp;', $txt['pages'], ': ', $context['page_index'], '
					</td>
				</tr>
			</table><br />';
	if ($context['sort_direction'] == 'down')
		echo '
			<input type="hidden" name="desc" value="1" />';
	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>';
}

function template_show_file()
{
	global $context, $settings;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $context['file_data']['file'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index.css" />
	</head>
	<body>
		<table border="0" cellpadding="0" cellspacing="3">';
	foreach ($context['file_data']['contents'] as $index => $line)
	{
		$line_num = $index+$context['file_data']['min'];
		$is_target = $line_num == $context['file_data']['target'];
		echo '
			<tr>
				<td align="right"', $is_target ? ' style="font-weight: bold; border: 1px solid black;border-width: 1px 0 1px 1px;">==&gt;' : '>', $line_num , ':</td>
				<td style="white-space: nowrap;', $is_target ? ' border: 1px solid black;border-width: 1px 1px 1px 0;':'','">', $line, '</td>
			</tr>';
	}
	echo '
		</table>
	</body>
</html>';
}

?>