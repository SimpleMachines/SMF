<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2018 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 4
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
		<div class="windowbg noup">
			<div ', $context['error_code'], 'class="padding">
				', $context['error_message'], '
			</div>
		</div>
	</div>';

		// Show a back button (using javascript.)
		echo '
	<div class="centertext">
		<a class="button" href="javascript:document.location=document.referrer">', $txt['back'], '</a>
	</div>';
	}
}

/**
 * This template handles the error log in the admin center.
 */
function template_error_log()
{
	global $context, $settings, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="', $scripturl, '?action=helpadmin;help=error_log" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'], '"></span></a> ', $txt['errlog'], '
				</h3>
			</div>
			<div class="pagesection">
				<div class="floatleft">
					', $context['page_index'], '
				</div>
				<div class="floatright">
					<input type="submit" name="removeSelection" value="', $txt['remove_selection'], '" data-confirm="', $txt['remove_selection_confirm'], '" class="button you_sure">
					<input type="submit" name="delall" value="', ($context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all']), '" data-confirm="', ($context['has_filter'] ? $txt['remove_filtered_results_confirm'] : $txt['sure_about_errorlog_remove']), '" class="button you_sure">
				</div>
			</div>
			<table class="table_grid" id="error_log">
				<tr class="title_bar">
					<td colspan="3">
						', $txt['apply_filter_of_type'], ':';

	$error_types = array();

	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<img src="' . $settings['images_url'] . '/selected.png" alt=""> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'style="font-weight: bold;"' : '') . ' title="' . $details['description'] . '">' . $details['label'] . '</a>';

	echo '
						', implode(' | ', $error_types), '
					</td>
				</tr>';

	if ($context['has_filter'])
		echo '
				<tr>
					<td colspan="3" class="windowbg">
						<strong>', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], ' [<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>]
					</td>
				</tr>';

	echo '
				<tr>
					<td colspan="3" class="righttext">
						<label for="check_all1"><strong>', $txt['check_all'], '</strong></label>
						<input type="checkbox" id="check_all1" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all2.checked = this.checked;">
					</td>
				</tr>';

	// No errors, then show a message
	if (count($context['errors']) == 0)
		echo '
				<tr class="windowbg">
					<td class="centertext" colspan="2">', $txt['errlog_no_entries'], '</td>
				</tr>';

	// We have some errors, must be some mods installed :P
	foreach ($context['errors'] as $error)
	{
		echo '
				<tr class="windowbg">
					<td colspan="2">
						<div class="error_info">
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><span class="generic_icons filter centericon"></span></a>
							<strong>', $error['member']['link'], '</strong><br>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '"><span class="generic_icons sort_' . $context['sort_direction'] . '"></span></a>
							', $error['time'], '<br>';

		if (!empty($error['member']['ip']))
			echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><span class="generic_icons filter centericon"></span></a>
							<strong><a href="', $scripturl, '?action=trackip;searchip=', $error['member']['ip'], '">', $error['member']['ip'], '</a></strong>';
		
		if ($error['member']['session'] != '')
			echo '
							<br>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=session;value=', $error['member']['session'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_session'], '"><span class="generic_icons filter centericon"></span></a>
							', $error['member']['session'], '<br>';

		echo '
						</div>
						<div class="error_info">';

		echo '
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><span class="generic_icons filter centericon"></span></a>
							', $txt['error_type'], ': ', $error['error_type']['name'], ' <a href ="', $scripturl, '?action=admin;area=logs;sa=errorlog;backtrace=', $error['id'], '" onclick="return reqWin(this.href, 600, 480, false);"><span class="generic_icons details"></span></a><br>
							<a class="error_message" href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"><span class="generic_icons filter"></span></a>
							<span class="error_message">', $error['message']['html'], '</span>
							<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><span class="generic_icons filter"></span></a>
							<a href="', $error['url']['html'], '">', $error['url']['html'], '</a>
';

		if (!empty($error['file']))
			echo '
							<div>
								<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '">'
				. '					<span class="generic_icons filter"></span></a> ', $error['file']['link'], ' (', $txt['line'], ' ', $error['file']['line'], ')
							</div>';

		echo '
						</div>
					</td>
					<td class="checkbox_column">
						<input type="checkbox" name="delete[]" value="', $error['id'], '">
					</td>
				</tr>';
	}

	echo '
				<tr>
					<td colspan="3" class="righttext">
						<label for="check_all2"><strong>', $txt['check_all'], '</strong></label>
						<input type="checkbox" id="check_all2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all1.checked = this.checked;">
					</td>
				</tr>
			</table>
			<div class="pagesection">
				<div class="floatleft">
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
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'], '">
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
				</div>',
				!empty($context['back_link']) ? ('<a class="button" href="' . $scripturl . $context['back_link'] . '">' . $txt['back'] . '</a>') : '',
				'<span style="float: right; margin:.5em;"></span>
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
	global $context, $settings, $modSettings;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', $context['character_set'], '">
		<title>Backtrace</title>
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css', $modSettings['browser_cache'], '">
	</head>
	<body>
		<pre class="backtrace">
		', print_r(!empty($context['error_backtrace']) ? $context['error_backtrace'] : ''),'
		</pre>
	</body>
</html>';
}

?>