<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

// @todo
/*	This template file contains only the sub template fatal_error. It is
	shown when an error occurs, and should show at least a back button and
	$context['error_message'].
*/

// Show an error message.....
function template_fatal_error()
{
	global $context, $txt;

	if (SIMPLE_ACTION)
		echo '
		<strong>
			', $context['error_title'], '
		</strong><br>
		<div ', $context['error_code'], 'class="padding">', $context['error_message'], '</div>';
	else
	{
		echo '
	<div id="fatal_error">
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['error_title'], '
			</h3>
		</div>
		<div class="windowbg">
			<div ', $context['error_code'], 'class="padding">', $context['error_message'], '</div>
		</div>
	</div>';

		// Show a back button (using javascript.)
		echo '
	<div class="centertext">
		<a class="button_link" style="float:none" href="javascript:document.location=document.referrer">', $txt['back'], '</a>
	</div>';
	}
}

function template_error_log()
{
	global $context, $settings, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="', $context['character_set'], '">';

	echo '
			<div class="cat_bar clear_right">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=error_log" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a> ', $txt['errlog'], '
				</h3>
			</div>
			<div class="pagesection">
				<div class="floatleft">
					', $context['page_index'], '
				</div>
				<div class="floatright" style="margin-top: 1ex">
					<input type="submit" name="removeSelection" value="', $txt['remove_selection'] ,'" data-confirm="', $txt['remove_selection_confirm'] ,'" class="button_submit you_sure">
					<input type="submit" name="delall" value="', ($context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all']) ,'" data-confirm="', ($context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove']) ,'" class="button_submit you_sure">
				</div>
			</div>
			<table class="table_grid" id="error_log">
				<tr class="title_bar">
					<td colspan="3">
						&nbsp;&nbsp;', $txt['apply_filter_of_type'], ':';

	$error_types = array();
	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<img src="' . $settings['images_url'] . '/selected.png" alt=""> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'style="font-weight: bold;"' : '') . ' title="' . $details['description'] . '">' . $details['label'] . '</a>';

	echo '
						', implode('&nbsp;|&nbsp;', $error_types), '
					</td>
				</tr>';

	if ($context['has_filter'])
		echo '
				<tr>
					<td colspan="3" class="windowbg">
						<strong>&nbsp;&nbsp;', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], '&nbsp;&nbsp;[<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>]
					</td>
				</tr>';

	echo '
				<tr>
					<td colspan="3" class="righttext" style="padding: 4px 8px;">
						<label for="check_all1"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all1" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all2.checked = this.checked;" class="input_check">
					</td>
				</tr>';

	// No errors, then show a message
	if (count($context['errors']) == 0)
		echo '
				<tr class="windowbg">
					<td class="centertext" colspan="2">', $txt['errlog_no_entries'], '</td>
				</tr>';

	// we have some errors, must be some mods installed :P
	foreach ($context['errors'] as $error)
	{
		echo '
				<tr class="windowbg">
					<td>

						<div style="float: left; width: 50%; line-height: 1.8em; padding: 0 4px 4px 4px; vertical-align: bottom;">
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><span class="generic_icons filter centericon"></span></a>
							<strong>', $error['member']['link'], '</strong><br>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '"><span class="generic_icons sort_' . $context['sort_direction'] . '"></span></a>
							', $error['time'], '<br>';

		if (!empty($error['member']['ip']))
			echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><span class="generic_icons filter centericon"></span></a>
							<strong><a href="', $scripturl, '?action=trackip;searchip=', $error['member']['ip'], '">', $error['member']['ip'], '</a></strong>&nbsp;&nbsp;<br>';

		echo '
						</div>

						<div style="float: left; width: 50%; line-height: 1.8em; padding: 0 4px;">';

		if ($error['member']['session'] != '')
			echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '"><span class="generic_icons filter centericon"></span></a>
							', $error['member']['session'], '<br>';

		echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><span class="generic_icons filter centericon"></span></a>
							', $txt['error_type'], ': ', $error['error_type']['name'], '<br>
							<a style="display: table-cell; padding: 4px 0; width: 20px; vertical-align: top;" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"><span class="generic_icons filter"></span></a>
							<span style="display: table-cell;">', $error['message']['html'], '</span>
						</div>

						<div style="float: left; width: 100%; padding: 4px 0; line-height: 1.6em; border-top: 1px solid #e3e3e3;">
							<a style="display: table-cell; padding: 4px; width: 20px; vertical-align: top;" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><span class="generic_icons filter"></span></a>
							<a style="display: table-cell;" href="', $error['url']['html'], '">', $error['url']['html'], '</a>
						</div>';

		if (!empty($error['file']))
			echo '
						<div style="float: left; width: 100%; padding: 4px 0; line-height: 1.6em; border-top: 1px solid #e3e3e3;">
							<a style="display: table-cell; padding: 4px; width: 20px; vertical-align: top;" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"><span class="generic_icons filter"></span></a>
							<div>
								', $txt['file'], ': ', $error['file']['link'], '<br>
								', $txt['line'], ': ', $error['file']['line'], '
							</div>
						</div>';

		echo '
					</td>
					<td class="checkbox_column">
						<input type="checkbox" name="delete[]" value="', $error['id'], '" class="input_check">
					</td>
				</tr>';
	}

	echo '
				<tr>
					<td colspan="3" class="righttext" style="padding-right: 1.2ex">
						<label for="check_all2"><strong>', $txt['check_all'], '</strong></label>&nbsp;
						<input type="checkbox" id="check_all2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all1.checked = this.checked;" class="input_check">
					</td>
				</tr>
			</table>
			<div class="pagesection floatleft">
				&nbsp;&nbsp;', $context['page_index'], '
			</div>';

	echo '
			<div class="floatright" style="margin-top: 1ex">
				<input type="submit" name="removeSelection" value="', $txt['remove_selection'] ,'" data-confirm="', $txt['remove_selection_confirm'] ,'" class="button_submit you_sure">
				<input type="submit" name="delall" value="', ($context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all']) ,'" data-confirm="', ($context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove']) ,'" class="button_submit you_sure">
			</div>
			<br>';

	if ($context['sort_direction'] == 'down')
		echo '
			<input type="hidden" name="desc" value="1">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-el_token_var'], '" value="', $context['admin-el_token'], '">
		</form>';
}

function template_show_file()
{
	global $context, $settings, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $context['file_data']['file'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'] ,'">
	</head>
	<body>
		<table class="errorfile_table">';
	foreach ($context['file_data']['contents'] as $index => $line)
	{
		$line_num = $index + $context['file_data']['min'];
		$is_target = $line_num == $context['file_data']['target'];
		echo '
			<tr>
				<td align="right"', $is_target ? ' class="current">==&gt;' : '>', $line_num , ':</td>
				<td style="white-space: nowrap;', $is_target ? ' border: 1px solid black;border-width: 1px 1px 1px 0;':'','">', $line, '</td>
			</tr>';
	}
	echo '
		</table>
	</body>
</html>';
}

function template_attachment_errors()
{
	global $context, $scripturl, $txt;

	echo '
	<div>
		<div class="cat_bar">
			<h3 class="catbg">
				', $context['error_title'], '
			</h3>
		</div>
		<div class="windowbg">
			<div class="padding">
				<div class="noticebox">',
					$context['error_message'], '
				</div>',
				!empty($context['back_link']) ? ('<a class="button_link" href="' . $scripturl . $context['back_link'] . '">' . $txt['back'] . '</a>') : '',
				'<span style="float: right; margin:.5em;"></span>
				<a class="button_link" href="', $scripturl, $context['redirect_link'], '">', $txt['continue'], '</a>
			</div>
		</div>
	</div>';
}

?>