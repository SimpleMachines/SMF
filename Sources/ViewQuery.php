<?php

/**
 * Functions concerned with viewing queries, and is used for debugging.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Show the database queries for debugging
 * What this does:
 * - Toggles the session variable 'view_queries'.
 * - Views a list of queries and analyzes them.
 * - Requires the admin_forum permission.
 * - Is accessed via ?action=viewquery.
 * - Strings in this function have not been internationalized.
 */
function ViewQuery()
{
	global $scripturl, $settings, $context, $db_connection, $boarddir, $smcFunc, $txt, $db_show_debug;

	// We should have debug mode enabled, as well as something to display!
	if (!isset($db_show_debug) || $db_show_debug !== true || !isset($_SESSION['debug']))
		fatal_lang_error('no_access', false);

	// Don't allow except for administrators.
	isAllowedTo('admin_forum');

	// If we're just hiding/showing, do it now.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'hide')
	{
		$_SESSION['view_queries'] = $_SESSION['view_queries'] == 1 ? 0 : 1;

		if (strpos($_SESSION['old_url'], 'action=viewquery') !== false)
			redirectexit();
		else
			redirectexit($_SESSION['old_url']);
	}

	call_integration_hook('integrate_egg_nog');

	$query_id = isset($_REQUEST['qq']) ? (int) $_REQUEST['qq'] - 1 : -1;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $context['forum_name_html_safe'], '</title>
		<link rel="stylesheet" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?alp21">
		<style>
			body
			{
				margin: 1ex;
			}
			body, td, th, .normaltext
			{
				font-size: x-small;
			}
			.smalltext
			{
				font-size: xx-small;
			}
		</style>
	</head>
	<body id="help_popup">
		<div class="tborder windowbg description">';

	foreach ($_SESSION['debug'] as $q => $query_data)
	{
		// Fix the indentation....
		$query_data['q'] = ltrim(str_replace("\r", '', $query_data['q']), "\n");
		$query = explode("\n", $query_data['q']);
		$min_indent = 0;
		foreach ($query as $line)
		{
			preg_match('/^(\t*)/', $line, $temp);
			if (strlen($temp[0]) < $min_indent || $min_indent == 0)
				$min_indent = strlen($temp[0]);
		}
		foreach ($query as $l => $dummy)
			$query[$l] = substr($dummy, $min_indent);
		$query_data['q'] = implode("\n", $query);

		// Make the filenames look a bit better.
		if (isset($query_data['f']))
			$query_data['f'] = preg_replace('~^' . preg_quote($boarddir, '~') . '~', '...', $query_data['f']);

		$is_select_query = substr(trim($query_data['q']), 0, 6) == 'SELECT';
		if ($is_select_query)
			$select = $query_data['q'];
		elseif (preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+(SELECT .+)$~s', trim($query_data['q']), $matches) != 0)
		{
			$is_select_query = true;
			$select = $matches[1];
		}
		elseif (preg_match('~^CREATE TEMPORARY TABLE .+?(SELECT .+)$~s', trim($query_data['q']), $matches) != 0)
		{
			$is_select_query = true;
			$select = $matches[1];
		}
		// Temporary tables created in earlier queries are not explainable.
		if ($is_select_query)
		{
			foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
				if (strpos($select, $tmp) !== false)
				{
					$is_select_query = false;
					break;
				}
		}

		echo '
		<div id="qq', $q, '" style="margin-bottom: 2ex;">
			<a', $is_select_query ? ' href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '"' : '', ' style="font-weight: bold; text-decoration: none;">
				', nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', $smcFunc['htmlspecialchars']($query_data['q']))), '
			</a><br>';

		if (!empty($query_data['f']) && !empty($query_data['l']))
			echo sprintf($txt['debug_query_in_line'], $query_data['f'], $query_data['l']);

		if (isset($query_data['s'], $query_data['t']) && isset($txt['debug_query_which_took_at']))
			echo sprintf($txt['debug_query_which_took_at'], round($query_data['t'], 8), round($query_data['s'], 8));
		else
			echo sprintf($txt['debug_query_which_took'], round($query_data['t'], 8));

		echo '
		</div>';

		// Explain the query.
		if ($query_id == $q && $is_select_query)
		{
			$result = $smcFunc['db_query']('', '
				EXPLAIN ' . ($smcFunc['db_title'] == 'PostgreSQL' ? 'ANALYZE ' : '') . $select,
				array(
				)
			);
			if ($result === false)
			{
				echo '
		<table>
			<tr><td>', $smcFunc['db_error']($db_connection), '</td></tr>
		</table>';
				continue;
			}

			echo '
		<table>';

			$row = $smcFunc['db_fetch_assoc']($result);

			echo '
			<tr>
				<th>' . implode('</th>
				<th>', array_keys($row)) . '</th>
			</tr>';

			$smcFunc['db_data_seek']($result, 0);
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				echo '
			<tr>
				<td>' . implode('</td>
				<td>', $row) . '</td>
			</tr>';
			}
			$smcFunc['db_free_result']($result);

			echo '
		</table>';
		}
	}

	echo '
		</div>
	</body>
</html>';

	obExit(false);
}

?>