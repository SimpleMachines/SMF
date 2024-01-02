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

// @todo
/*	This template file contains only the sub template fatal_error. It is
	shown when an error occurs, and should show at least a back button and
	Utils::$context['error_message'].
*/

/**
 * THis displays a fatal error message
 */
function template_fatal_error()
{
	if (!empty(Utils::$context['simple_action']))
		echo '
	<strong>
		', Utils::$context['error_title'], '
	</strong><br>
	<div ', Utils::$context['error_code'], 'class="padding">
		', Utils::$context['error_message'], '
	</div>';
	else
	{
		echo '
	<div id="fatal_error">
		<div class="cat_bar">
			<h3 class="catbg">
				', Utils::$context['error_title'], '
			</h3>
		</div>
		<div class="windowbg">
			<div ', Utils::$context['error_code'], 'class="padding">
				', Utils::$context['error_message'], '
			</div>
		</div>
	</div>';

		// Show a back button
		echo '
	<div class="centertext">
		<a class="button floatnone" href="', Utils::$context['error_link'], '">', Lang::$txt['back'], '</a>
	</div>';
	}
}

/**
 * This template handles the error log in the admin center.
 */
function template_error_log()
{
	echo '
		<form action="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';start=', Utils::$context['start'], Utils::$context['has_filter'] ? Utils::$context['filter']['href'] : '', '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', Config::$scripturl, '?action=helpadmin;help=error_log" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="', Lang::$txt['help'], '"></span></a> ', Lang::$txt['errorlog'], '
				</h3>
			</div>
			<div class="information flow_hidden">
				<div class="additional_row">';

	// No errors, so just show a message and be done with it.
	if (empty(Utils::$context['errors']))
	{
		echo '
					', Lang::$txt['errorlog_no_entries'], '
				</div>
			</div>
		</form>';
		return;
	}

	if (Utils::$context['has_filter'])
		echo '
				<div class="infobox">
					<strong>', Lang::$txt['applying_filter'], ':</strong> ', Utils::$context['filter']['entity'], ' ', Utils::$context['filter']['value']['html'], '
				</div>';

	echo '
				<div class="floatright">
					<input type="submit" name="removeSelection" value="', Lang::$txt['remove_selection'], '" data-confirm="', Lang::$txt['remove_selection_confirm'], '" class="button you_sure">
					<input type="submit" name="delall" value="', (Utils::$context['has_filter'] ? Lang::$txt['remove_filtered_results'] : Lang::$txt['remove_all']), '" data-confirm="', (Utils::$context['has_filter'] ? Lang::$txt['remove_filtered_results_confirm'] : Lang::$txt['sure_about_errorlog_remove']), '" class="button you_sure">
					', (Utils::$context['has_filter'] ? '<a href="' . Config::$scripturl . '?action=admin;area=logs;sa=errorlog' . (Utils::$context['sort_direction'] == 'down' ? ';desc' : '') . '" class="button">' . Lang::$txt['clear_filter'] . '</a>' : ''), '
				</div>
				', Lang::$txt['apply_filter_of_type'], ':';

	$error_types = array();

	foreach (Utils::$context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<span class="main_icons right_arrow"></span> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'style="font-weight: bold;"' : 'style="font-weight: normal;"') . ' title="' . $details['description'] . '">' . ($details['error_type'] === 'critical' ? '<span class="error">' . $details['label'] . '</span>' : $details['label']) . '</a>';

	echo '
				', implode(' | ', $error_types), '
				</div>
			</div>
			<div class="pagesection">
				<div class="pagelinks">
					', Utils::$context['page_index'], '
				</div>
				<div class="floatright" style="padding: 0 12px">
					<label for="check_all"><strong>', Lang::$txt['check_all'], '</strong></label>
					<input type="checkbox" id="check_all" onclick="invertAll(this, this.form, \'delete[]\');">
				</div>
			</div>';

	// We have some errors, must be some mods installed :P
	foreach (Utils::$context['errors'] as $error)
	{
		echo '
			<div class="windowbg word_break">
				<div class="counter" style="padding: 0 10px 10px 0">', $error['id'], '</div>
				<div class="topic_details">
					<span class="floatright">
						<input type="checkbox" name="delete[]" value="', $error['id'], '">
					</span>
					<h5>
						<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? '' : ';desc', Utils::$context['has_filter'] ? Utils::$context['filter']['href'] : '', '" title="', Lang::$txt['reverse_direction'], '"><span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span></a> ', $error['time'], '
					</h5>
					<hr class="clear">
				</div>
				<div>
					<div class="half_content">
						<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_member'], '"><span class="main_icons filter"></span></a>
						<strong>', $error['member']['link'], '</strong>';

		if (!empty($error['member']['ip']))
			echo '
						<br>
						<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_ip'], '"><span class="main_icons filter"></span></a>
						<strong><a href="', Config::$scripturl, '?action=trackip;searchip=', $error['member']['ip'], '">', $error['member']['ip'], '</a></strong>';

		if (!empty($error['member']['session']))
			echo '
						<br>
						<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_session'], '"><span class="main_icons filter"></span></a> <a class="bbc_link" href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_session'], '">', $error['member']['session'], '</a>';

		echo '
						<br>
						<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_url'], '"><span class="main_icons filter"></span></a>
						<a href="', $error['url']['html'], '" class="bbc_link word_break">', $error['url']['html'], '</a>';

		if (!empty($error['file']))
			echo '
						<br>
						<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_file'], '"><span class="main_icons filter"></span></a> <a class="bbc_link" href="', $error['file']['href'], '" onclick="return reqWin(this.href, 600, 480, false);">', $error['file']['file'], '</a> (', Lang::$txt['line'], ' ', $error['file']['line'], ')';

		echo '
					</div>
					<div class="half_content">
						<strong class="floatright">
							<span class="main_icons details"></span> <a class="bbc_link" href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog;backtrace=', $error['id'], '" onclick="return reqWin(this.href, 600, 480, false);">', Lang::$txt['backtrace_title'], '</a>
						</strong>
					</div>
				</div>
				<div class="post">
					<br class="clear">
					<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_type'], '"><span class="main_icons filter"></span></a>', Lang::$txt['error_type'], ': ', $error['error_type']['type'] === 'critical' ? '<span class="error">' . $error['error_type']['name'] . '</span>' : $error['error_type']['name'], '<br>
					<a href="', Config::$scripturl, '?action=admin;area=logs;sa=errorlog', Utils::$context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', Lang::$txt['apply_filter'], ': ', Lang::$txt['filter_only_message'], '"><span class="main_icons filter floatleft"></span></a>
					<div class="codeheader"><span class="code floatleft">' . Lang::$txt['error_message'] . '</span> <a class="codeoperation smf_select_text">' . Lang::$txt['code_select'] . '</a> <a class="codeoperation smf_expand_code hidden" data-shrink-txt="' . Lang::$txt['code_shrink'] . '" data-expand-txt="' . Lang::$txt['code_expand'] . '">' . Lang::$txt['code_expand'] . '</a>
					</div><code class="bbc_code" style="white-space: pre-line; overflow-y: auto">', $error['message']['html'], '</code>
				</div>
			</div>';
	}

	echo '
			<div class="pagesection">
				<div class="pagelinks">
					', Utils::$context['page_index'], '
				</div>
				<div class="floatright">
					<input type="submit" name="removeSelection" value="', Lang::$txt['remove_selection'], '" data-confirm="', Lang::$txt['remove_selection_confirm'], '" class="button you_sure">
					<input type="submit" name="delall" value="', (Utils::$context['has_filter'] ? Lang::$txt['remove_filtered_results'] : Lang::$txt['remove_all']), '" data-confirm="', (Utils::$context['has_filter'] ? Lang::$txt['remove_filtered_results_confirm'] : Lang::$txt['sure_about_errorlog_remove']), '" class="button you_sure">
				</div>
			</div>';

	if (Utils::$context['sort_direction'] == 'down')
		echo '
			<input type="hidden" name="desc" value="1">';

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="', Utils::$context['admin-el_token_var'], '" value="', Utils::$context['admin-el_token'], '">
		</form>';
}

/**
 * This template shows a snippet of code from a file and highlights which line caused the error.
 */
function template_show_file()
{
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Utils::$context['character_set'], '">
		<title>', Utils::$context['file_data']['file'], '</title>
		', Theme::template_css(), '
	</head>
	<body>
		<table class="errorfile_table">';
	foreach (Utils::$context['file_data']['contents'] as $index => $line)
	{
		$line_num = $index + Utils::$context['file_data']['min'];
		$is_target = $line_num == Utils::$context['file_data']['target'];

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
	echo '
	<div>
		<div class="cat_bar">
			<h3 class="catbg">
				', Utils::$context['error_title'], '
			</h3>
		</div>
		<div class="windowbg">
			<div class="padding">
				<div class="noticebox">',
					Utils::$context['error_message'], '
				</div>';

	if (!empty(Utils::$context['back_link']))
		echo '
				<a class="button" href="', Config::$scripturl, Utils::$context['back_link'], '">', Lang::$txt['back'], '</a>';

	echo '
				<span style="float: right; margin:.5em;"></span>
				<a class="button" href="', Config::$scripturl, Utils::$context['redirect_link'], '">', Lang::$txt['continue'], '</a>
			</div>
		</div>
	</div>';
}

/**
 * This template shows a backtrace of the given error
 */
function template_show_backtrace()
{
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Utils::$context['character_set'], '">
		<title>', Lang::$txt['backtrace_title'], '</title>';

	Theme::template_css();

	echo '
	</head>
	<body class="padding">';

	if (!empty(Utils::$context['error_info']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::$txt['error'], '
				</h3>
			</div>
			<div class="windowbg" id="backtrace">
				<table class="table_grid">
					<tbody>';

		if (!empty(Utils::$context['error_info']['error_type']))
			echo '
						<tr class="title_bar">
							<td><strong>', Lang::$txt['error_type'], '</strong></td>
						</tr>
						<tr class="windowbg">
							<td>', ucfirst(Utils::$context['error_info']['error_type']), '</td>
						</tr>';

		if (!empty(Utils::$context['error_info']['message']))
			echo '
						<tr class="title_bar">
							<td><strong>', Lang::$txt['error_message'], '</strong></td>
						</tr>
						<tr class="windowbg lefttext">
							<td><code class="bbc_code" style="white-space: pre-line; overflow-y: auto">', Utils::$context['error_info']['message'], '</code></td>
						</tr>';

		if (!empty(Utils::$context['error_info']['file']))
			echo '
						<tr class="title_bar">
							<td><strong>', Lang::$txt['error_file'], '</strong></td>
						</tr>
						<tr class="windowbg">
							<td>', Utils::$context['error_info']['file'], '</td>
						</tr>';

		if (!empty(Utils::$context['error_info']['line']))
			echo '
						<tr class="title_bar">
							<td><strong>', Lang::$txt['error_line'], '</strong></td>
						</tr>
						<tr class="windowbg">
							<td>', Utils::$context['error_info']['line'], '</td>
						</tr>';

		if (!empty(Utils::$context['error_info']['url']))
			echo '
						<tr class="title_bar">
							<td><strong>', Lang::$txt['error_url'], '</strong></td>
						</tr>
						<tr class="windowbg word_break">
							<td>', Utils::$context['error_info']['url'], '</td>
						</tr>';

		echo '
					</tbody>
				</table>
			</div>';
	}

	if (!empty(Utils::$context['error_info']['backtrace']))
	{
		echo '
			<div class="cat_bar">
				<h3 class="catbg">
					', Lang::$txt['backtrace_title'], '
				</h3>
			</div>
			<div class="windowbg">
				<ul class="padding">';

		foreach (Utils::$context['error_info']['backtrace'] as $key => $value)
		{
			//Check for existing
			if (!property_exists($value, 'file') || empty($value->file))
				$value->file = Lang::$txt['unknown'];

			if (!property_exists($value, 'line') || empty($value->line))
				$value->line = -1;

			echo '
					<li class="backtrace">', sprintf(Lang::$txt['backtrace_info' . ($value->file == Lang::$txt['unknown'] && $value->line == -1 ? '_internal_function' : '')], $key, (!empty($value->class) ? $value->class . $value->type : '') . $value->function, $value->file, $value->line, base64_encode($value->file), Config::$scripturl), '</li>';
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