<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.3
 */

// @todo
/*	This template file contains only the sub template fatal_error. It is
	shown when an error occurs, and should show at least a back button and
	$context['error_message'].
*/

/**
 * THis displays a fatal error message
 */
function template_fatal_error()
{
	global $context, $txt;

	if (!empty($context['simple_action']))
		echo '
	<strong>
		', $context['error_title'], '
	</strong><br>
	<div ', $context['error_code'], 'class="padding">
		', $context['error_message'], '
	</div>';
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
			<div ', $context['error_code'], 'class="padding">
				', $context['error_message'], '
			</div>
		</div>
	</div>';

		// Show a back button
		echo '
	<div class="centertext">
		<a class="button floatnone" href="', $context['error_link'], '">', $txt['back'], '</a>
	</div>';
	}
}

/**
 * This template handles the error log in the admin center.
 */
function template_error_log()
{
	global $scripturl, $context, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=error_log" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', $txt['help'], '"></span></a> ', $txt['errorlog'], '
				</h3>
			</div>
			<div class="information flow_hidden">
				<div class="additional_row">';

	// No errors, so just show a message and be done with it.
	if (empty($context['errors']))
	{
		echo '
					', $txt['errorlog_no_entries'], '
				</div>
			</div>
		</form>';
		return;
	}

	if ($context['has_filter'])
		echo '
				<div class="infobox">
					<strong>', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], '
				</div>';

	echo '
				<div class="floatright">
					<input type="submit" name="removeSelection" value="', $txt['remove_selection'], '" data-confirm="', $txt['remove_selection_confirm'], '" class="button you_sure">
					<input type="submit" name="delall" value="', ($context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all']), '" data-confirm="', ($context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove']), '" class="button you_sure">
					', ($context['has_filter'] ? '<a href="' . $scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : '') . '" class="button">' . $txt['clear_filter'] . '</a>' : ''), '
				</div>
				', $txt['apply_filter_of_type'], ':';

	$error_types = array();

	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<span class="main_icons right_arrow"></span> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'style="font-weight: bold;"' : 'style="font-weight: normal;"') . ' title="' . $details['description'] . '">' . ($details['error_type'] === 'critical' ? '<span class="error">' . $details['label'] . '</span>' : $details['label']) . '</a>';

	echo '
				', implode(' | ', $error_types), '
				</div>
			</div>
			<div class="pagesection">
				<div class="pagelinks">
					', $context['page_index'], '
				</div>
				<div class="floatright" style="padding: 0 12px">
					<label for="check_all"><strong>', $txt['check_all'], '</strong></label>
					<input type="checkbox" id="check_all" onclick="invertAll(this, this.form, \'delete[]\');">
				</div>
			</div>';

	// We have some errors, must be some mods installed :P
	foreach ($context['errors'] as $error)
	{
		echo '
			<div class="windowbg word_break">
				<div class="counter" style="padding: 0 10px 10px 0">', $error['id'], '</div>
				<div class="topic_details">
					<span class="floatright">
						<input type="checkbox" name="delete[]" value="', $error['id'], '">
					</span>
					<h5>
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '"><span class="main_icons sort_' . $context['sort_direction'] . '"></span></a> ', $error['time'], '
					</h5>
					<hr class="clear">
				</div>
				<div>
					<div class="half_content">
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><span class="main_icons filter"></span></a>
						<strong>', $error['member']['link'], '</strong>';

		if (!empty($error['member']['ip']))
			echo '
						<br>
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><span class="main_icons filter"></span></a>
						<strong><a href="', $scripturl, '?action=trackip;searchip=', $error['member']['ip'], '">', $error['member']['ip'], '</a></strong>';

		if (!empty($error['member']['session']))
			echo '
						<br>
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '"><span class="main_icons filter"></span></a> <a class="bbc_link" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '">', $error['member']['session'], '</a>';

		echo '
						<br>
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><span class="main_icons filter"></span></a>
						<a href="', $error['url']['html'], '" class="bbc_link word_break">', $error['url']['html'], '</a>';

		if (!empty($error['file']))
			echo '
						<br>
						<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"><span class="main_icons filter"></span></a> <a class="bbc_link" href="', $error['file']['href'], '" onclick="return reqWin(this.href, 600, 480, false);">', $error['file']['file'], '</a> (', $txt['line'], ' ', $error['file']['line'], ')';

		echo '
					</div>
					<div class="half_content">
						<strong class="floatright">
							<span class="main_icons details"></span> <a class="bbc_link" href="', $scripturl, '?action=admin;area=logs;sa=errorlog;backtrace=', $error['id'], '" onclick="return reqWin(this.href, 600, 480, false);">', $txt['backtrace_title'], '</a>
						</strong>
					</div>
				</div>
				<div class="post">
					<br class="clear">
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><span class="main_icons filter"></span></a>', $txt['error_type'], ': ', $error['error_type']['type'] === 'critical' ? '<span class="error">' . $error['error_type']['name'] . '</span>' : $error['error_type']['name'], '<br>
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"><span class="main_icons filter floatleft"></span></a>
					<div class="codeheader"><span class="code floatleft">' . $txt['error_message'] . '</span> <a class="codeoperation smf_select_text">' . $txt['code_select'] . '</a> <a class="codeoperation smf_expand_code hidden" data-shrink-txt="' . $txt['code_shrink'] . '" data-expand-txt="' . $txt['code_expand'] . '">' . $txt['code_expand'] . '</a>
					</div><code class="bbc_code" style="white-space: pre-line; overflow-y: auto">', $error['message']['html'], '</code>
				</div>
			</div>';
	}

	echo '
			<div class="pagesection">
				<div class="pagelinks">
					', $context['page_index'], '
				</div>
				<div class="floatright">
					<input type="submit" name="removeSelection" value="', $txt['remove_selection'], '" data-confirm="', $txt['remove_selection_confirm'], '" class="button you_sure">
					<input type="submit" name="delall" value="', ($context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all']), '" data-confirm="', ($context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove']), '" class="button you_sure">
				</div>
			</div>';

	if ($context['sort_direction'] == 'down')
		echo '
			<input type="hidden" name="desc" value="1">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['admin-el_token_var'], '" value="', $context['admin-el_token'], '">
		</form>';
}

/**
 * This template shows a snippet of code from a file and highlights which line caused the error.
 */
function template_show_file()
{
	global $context, $settings, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $context['file_data']['file'], '</title>
		', template_css(), '
	</head>
	<body>
		<table class="errorfile_table">';
	foreach ($context['file_data']['contents'] as $index => $line)
	{
		$line_num = $index + $context['file_data']['min'];
		$is_target = $line_num == $context['file_data']['target'];

		echo '
			<tr>
				<td class="file_line', $is_target ? ' current">==&gt;' : '">', $line_num, ':</td>
				<td ', $is_target ? 'class="current"' : '', '>', $line, '</td>
			</tr>';
	}
	echo '
		</table>
	</body>
</html>';
}

/**
 * This template handles showing attachment-related errors
 */
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
				</div>';

	if (!empty($context['back_link']))
		echo '
				<a class="button" href="', $scripturl, $context['back_link'], '">', $txt['back'], '</a>';

	echo '
				<span style="float: right; margin:.5em;"></span>
				<a class="button" href="', $scripturl, $context['redirect_link'], '">', $txt['continue'], '</a>
			</div>
		</div>
	</div>';
}

/**
 * This template shows a backtrace of the given error
 */
function template_show_backtrace()
{
	global $context, $settings, $modSettings, $txt, $scripturl;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>', $txt['backtrace_title'], '</title>';

	template_css();

	echo '
	</head>
	<body class="padding">';

	if (!empty($context['error_info']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['error'], '
				</h3>
			</div>
			<div class="windowbg" id="backtrace">
				<table class="table_grid">
					<tbody>';

		if (!empty($context['error_info']['error_type']))
			echo '
						<tr class="title_bar">
							<td><strong>', $txt['error_type'], '</strong></td>
						</tr>
						<tr class="windowbg">
							<td>', ucfirst($context['error_info']['error_type']), '</td>
						</tr>';

		if (!empty($context['error_info']['message']))
			echo '
						<tr class="title_bar">
							<td><strong>', $txt['error_message'], '</strong></td>
						</tr>
						<tr class="windowbg lefttext">
							<td><code class="bbc_code" style="white-space: pre-line; overflow-y: auto">', $context['error_info']['message'], '</code></td>
						</tr>';

		if (!empty($context['error_info']['file']))
			echo '
						<tr class="title_bar">
							<td><strong>', $txt['error_file'], '</strong></td>
						</tr>
						<tr class="windowbg">
							<td>', $context['error_info']['file'], '</td>
						</tr>';

		if (!empty($context['error_info']['line']))
			echo '
						<tr class="title_bar">
							<td><strong>', $txt['error_line'], '</strong></td>
						</tr>
						<tr class="windowbg">
							<td>', $context['error_info']['line'], '</td>
						</tr>';

		if (!empty($context['error_info']['url']))
			echo '
						<tr class="title_bar">
							<td><strong>', $txt['error_url'], '</strong></td>
						</tr>
						<tr class="windowbg word_break">
							<td>', $context['error_info']['url'], '</td>
						</tr>';

		echo '
					</tbody>
				</table>
			</div>';
	}

	if (!empty($context['error_info']['backtrace']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', $txt['backtrace_title'], '
				</h3>
			</div>
			<div class="windowbg">
				<ul class="padding">';

		foreach ($context['error_info']['backtrace'] as $key => $value)
		{
			//Check for existing
			if (!property_exists($value, 'file') || empty($value->file))
				$value->file = $txt['unknown'];

			if (!property_exists($value, 'line') || empty($value->line))
				$value->line = -1;

			echo '
					<li class="backtrace">', sprintf($txt['backtrace_info'], $key, $value->function, $value->file, $value->line, base64_encode($value->file), $scripturl), '</li>';
		}

		echo '
				</ul>
			</div>';
	}

	echo '
	</body>
</html>';
}

?>