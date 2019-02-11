<?php

/**
 * The main purpose of this file is to show a list of all errors that were
 * logged on the forum, and allow filtering and deleting them.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * View the forum's error log.
 * This function sets all the context up to show the error log for maintenance.
 * It requires the maintain_forum permission.
 * It is accessed from ?action=admin;area=logs;sa=errorlog.
 *
 * @uses the Errors template and error_log sub template.
 */
function ViewErrorLog()
{
	global $scripturl, $txt, $context, $modSettings, $user_profile, $filter, $smcFunc;

	// Viewing contents of a file?
	if (isset($_GET['file']))
		return ViewFile();

	// Viewing contents of a backtrace?
	if (isset($_GET['backtrace']))
		return ViewBacktrace();

	// Check for the administrative permission to do this.
	isAllowedTo('admin_forum');

	// Templates, etc...
	loadLanguage('ManageMaintenance');
	loadTemplate('Errors');

	// You can filter by any of the following columns:
	$filters = array(
		'id_member' => array(
			'txt' => $txt['username'],
			'operator' => '=',
			'datatype' => 'int',
		),
		'ip' => array(
			'txt' => $txt['ip_address'],
			'operator' => '=',
			'datatype' => 'inet',
		),
		'session' => array(
			'txt' => $txt['session'],
			'operator' => 'LIKE',
			'datatype' => 'string',
		),
		'url' => array(
			'txt' => $txt['error_url'],
			'operator' => 'LIKE',
			'datatype' => 'string',
		),
		'message' => array(
			'txt' => $txt['error_message'],
			'operator' => 'LIKE',
			'datatype' => 'string',
		),
		'error_type' => array(
			'txt' => $txt['error_type'],
			'operator' => 'LIKE',
			'datatype' => 'string',
		),
		'file' => array(
			'txt' => $txt['file'],
			'operator' => 'LIKE',
			'datatype' => 'string',
		),
		'line' => array(
			'txt' => $txt['line'],
			'operator' => '=',
			'datatype' => 'int',
		),
	);

	// Set up the filtering...
	if (isset($_GET['value'], $_GET['filter']) && isset($filters[$_GET['filter']]))
		$filter = array(
			'variable' => $_GET['filter'],
			'value' => array(
				'sql' => in_array($_GET['filter'], array('message', 'url', 'file')) ? base64_decode(strtr($_GET['value'], array(' ' => '+'))) : $smcFunc['db_escape_wildcard_string']($_GET['value']),
			),
			'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
			'entity' => $filters[$_GET['filter']]['txt']
		);

	// Deleting, are we?
	if (isset($_POST['delall']) || isset($_POST['delete']))
		deleteErrors();

	// Just how many errors are there?
	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_errors' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' ' . $filters[$_GET['filter']]['operator'] . ' {' . $filters[$_GET['filter']]['datatype'] . ':filter}' : ''),
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);
	list ($num_errors) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// If this filter is empty...
	if ($num_errors == 0 && isset($filter))
		redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));

	// Clean up start.
	if (!isset($_GET['start']) || $_GET['start'] < 0)
		$_GET['start'] = 0;

	// Do we want to reverse error listing?
	$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';

	// Set the page listing up.
	$context['page_index'] = constructPageIndex($scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : '') . (isset($filter) ? $filter['href'] : ''), $_GET['start'], $num_errors, $modSettings['defaultMaxListItems']);
	$context['start'] = $_GET['start'];

	// Update the error count
	if (!isset($filter))
		$context['num_errors'] = $num_errors;
	else
	{
		// We want all errors, not just the number of filtered messages...
		$query = $smcFunc['db_query']('', '
			SELECT COUNT(id_error)
			FROM {db_prefix}log_errors',
			array()
		);

		list($context['num_errors']) = $smcFunc['db_fetch_row']($query);
		$smcFunc['db_free_result']($query);
	}

	// Find and sort out the errors.
	$request = $smcFunc['db_query']('', '
		SELECT id_error, id_member, ip, url, log_time, message, session, error_type, file, line
		FROM {db_prefix}log_errors' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' ' . $filters[$_GET['filter']]['operator'] . ' {' . $filters[$_GET['filter']]['datatype'] . ':filter}' : '') . '
		ORDER BY id_error ' . ($context['sort_direction'] == 'down' ? 'DESC' : '') . '
		LIMIT {int:start}, {int:max}',
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
			'start' => $_GET['start'],
			'max' => $modSettings['defaultMaxListItems'],
		)
	);
	$context['errors'] = array();
	$members = array();

	for ($i = 0; $row = $smcFunc['db_fetch_assoc']($request); $i++)
	{
		$search_message = preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '%', $smcFunc['db_escape_wildcard_string']($row['message']));
		if ($search_message == $filter['value']['sql'])
			$search_message = $smcFunc['db_escape_wildcard_string']($row['message']);
		$show_message = strtr(strtr(preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '$1', $row['message']), array("\r" => '', '<br>' => "\n", '<' => '&lt;', '>' => '&gt;', '"' => '&quot;')), array("\n" => '<br>'));

		$context['errors'][$row['id_error']] = array(
			'member' => array(
				'id' => $row['id_member'],
				'ip' => inet_dtop($row['ip']),
				'session' => $row['session']
			),
			'time' => timeformat($row['log_time']),
			'timestamp' => $row['log_time'],
			'url' => array(
				'html' => $smcFunc['htmlspecialchars'](strpos($row['url'], 'cron.php') === false ? (substr($row['url'], 0, 1) == '?' ? $scripturl : '') . $row['url'] : $row['url']),
				'href' => base64_encode($smcFunc['db_escape_wildcard_string']($row['url']))
			),
			'message' => array(
				'html' => $show_message,
				'href' => base64_encode($search_message)
			),
			'id' => $row['id_error'],
			'error_type' => array(
				'type' => $row['error_type'],
				'name' => isset($txt['errortype_' . $row['error_type']]) ? $txt['errortype_' . $row['error_type']] : $row['error_type'],
			),
			'file' => array(),
		);
		if (!empty($row['file']) && !empty($row['line']))
		{
			// Eval'd files rarely point to the right location and cause havoc for linking, so don't link them.
			$linkfile = strpos($row['file'], 'eval') === false || strpos($row['file'], '?') === false; // De Morgan's Law.  Want this true unless both are present.

			$context['errors'][$row['id_error']]['file'] = array(
				'file' => $row['file'],
				'line' => $row['line'],
				'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'],
				'link' => $linkfile ? '<a href="' . $scripturl . '?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'] . '" onclick="return reqWin(this.href, 600, 480, false);">' . $row['file'] . '</a>' : $row['file'],
				'search' => base64_encode($row['file']),
			);
		}

		// Make a list of members to load later.
		$members[$row['id_member']] = $row['id_member'];
	}
	$smcFunc['db_free_result']($request);

	// Load the member data.
	if (!empty($members))
	{
		// Get some additional member info...
		$request = $smcFunc['db_query']('', '
			SELECT id_member, member_name, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})
			LIMIT {int:members}',
			array(
				'member_list' => $members,
				'members' => count($members),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$members[$row['id_member']] = $row;
		$smcFunc['db_free_result']($request);

		// This is a guest...
		$members[0] = array(
			'id_member' => 0,
			'member_name' => '',
			'real_name' => $txt['guest_title']
		);

		// Go through each error and tack the data on.
		foreach ($context['errors'] as $id => $dummy)
		{
			$memID = $context['errors'][$id]['member']['id'];
			$context['errors'][$id]['member']['username'] = $members[$memID]['member_name'];
			$context['errors'][$id]['member']['name'] = $members[$memID]['real_name'];
			$context['errors'][$id]['member']['href'] = empty($memID) ? '' : $scripturl . '?action=profile;u=' . $memID;
			$context['errors'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="' . $scripturl . '?action=profile;u=' . $memID . '">' . $context['errors'][$id]['member']['name'] . '</a>';
		}
	}

	// Filtering anything?
	if (isset($filter))
	{
		$context['filter'] = &$filter;

		// Set the filtering context.
		if ($filter['variable'] == 'id_member')
		{
			$id = $filter['value']['sql'];
			loadMemberData($id, false, 'minimal');
			$context['filter']['value']['html'] = '<a href="' . $scripturl . '?action=profile;u=' . $id . '">' . $user_profile[$id]['real_name'] . '</a>';
		}
		elseif ($filter['variable'] == 'url')
			$context['filter']['value']['html'] = '\'' . strtr($smcFunc['htmlspecialchars']((substr($filter['value']['sql'], 0, 1) == '?' ? $scripturl : '') . $filter['value']['sql']), array('\_' => '_')) . '\'';
		elseif ($filter['variable'] == 'message')
		{
			$context['filter']['value']['html'] = '\'' . strtr($smcFunc['htmlspecialchars']($filter['value']['sql']), array("\n" => '<br>', '&lt;br /&gt;' => '<br>', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
			$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', $context['filter']['value']['html']);
		}
		elseif ($filter['variable'] == 'error_type')
		{
			$context['filter']['value']['html'] = '\'' . strtr($smcFunc['htmlspecialchars']($filter['value']['sql']), array("\n" => '<br>', '&lt;br /&gt;' => '<br>', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '\'';
		}
		else
			$context['filter']['value']['html'] = &$filter['value']['sql'];
	}

	$context['error_types'] = array();

	$context['error_types']['all'] = array(
		'label' => $txt['errortype_all'],
		'description' => isset($txt['errortype_all_desc']) ? $txt['errortype_all_desc'] : '',
		'url' => $scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : ''),
		'is_selected' => empty($filter),
	);

	$sum = 0;
	// What type of errors do we have and how many do we have?
	$request = $smcFunc['db_query']('', '
		SELECT error_type, COUNT(*) AS num_errors
		FROM {db_prefix}log_errors
		GROUP BY error_type
		ORDER BY error_type = {string:critical_type} DESC, error_type ASC',
		array(
			'critical_type' => 'critical',
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Total errors so far?
		$sum += $row['num_errors'];

		$context['error_types'][$sum] = array(
			'label' => (isset($txt['errortype_' . $row['error_type']]) ? $txt['errortype_' . $row['error_type']] : $row['error_type']) . ' (' . $row['num_errors'] . ')',
			'description' => isset($txt['errortype_' . $row['error_type'] . '_desc']) ? $txt['errortype_' . $row['error_type'] . '_desc'] : '',
			'url' => $scripturl . '?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'down' ? ';desc' : '') . ';filter=error_type;value=' . $row['error_type'],
			'is_selected' => isset($filter) && $filter['value']['sql'] == $smcFunc['db_escape_wildcard_string']($row['error_type']),
		);
	}
	$smcFunc['db_free_result']($request);

	// Update the all errors tab with the total number of errors
	$context['error_types']['all']['label'] .= ' (' . $sum . ')';

	// Finally, work out what is the last tab!
	if (isset($context['error_types'][$sum]))
		$context['error_types'][$sum]['is_last'] = true;
	else
		$context['error_types']['all']['is_last'] = true;

	// And this is pretty basic ;).
	$context['page_title'] = $txt['errorlog'];
	$context['has_filter'] = isset($filter);
	$context['sub_template'] = 'error_log';

	createToken('admin-el');
}

/**
 * Delete all or some of the errors in the error log.
 * It applies any necessary filters to deletion.
 * This should only be called by ViewErrorLog().
 * It attempts to TRUNCATE the table to reset the auto_increment.
 * Redirects back to the error log when done.
 */
function deleteErrors()
{
	global $filter, $smcFunc;

	// Make sure the session exists and is correct; otherwise, might be a hacker.
	checkSession();
	validateToken('admin-el');

	// Delete all or just some?
	if (isset($_POST['delall']) && !isset($filter))
		$smcFunc['db_query']('truncate_table', '
			TRUNCATE {db_prefix}log_errors',
			array(
			)
		);
	// Deleting all with a filter?
	elseif (isset($_POST['delall']) && isset($filter))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_errors
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
			array(
				'filter' => $filter['value']['sql'],
			)
		);
	// Just specific errors?
	elseif (!empty($_POST['delete']))
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_errors
			WHERE id_error IN ({array_int:error_list})',
			array(
				'error_list' => array_unique($_POST['delete']),
			)
		);

		// Go back to where we were.
		redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : '') . ';start=' . $_GET['start'] . (isset($filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));
	}

	// Back to the error log!
	redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['desc']) ? ';desc' : ''));
}

/**
 * View a file specified in $_REQUEST['file'], with php highlighting on it
 * Preconditions:
 *  - file must be readable,
 *  - full file path must be base64 encoded,
 *  - user must have admin_forum permission.
 * The line number number is specified by $_REQUEST['line']...
 * The function will try to get the 20 lines before and after the specified line.
 */
function ViewFile()
{
	global $context, $boarddir, $sourcedir, $cachedir, $smcFunc;

	// Check for the administrative permission to do this.
	isAllowedTo('admin_forum');

	// Decode the file and get the line
	$file = realpath(base64_decode($_REQUEST['file']));
	$real_board = realpath($boarddir);
	$real_source = realpath($sourcedir);
	$real_cache = realpath($cachedir);
	$basename = strtolower(basename($file));
	$ext = strrchr($basename, '.');
	$line = isset($_REQUEST['line']) ? (int) $_REQUEST['line'] : 0;

	// Make sure the file we are looking for is one they are allowed to look at
	if ($ext != '.php' || (strpos($file, $real_board) === false && strpos($file, $real_source) === false) || ($basename == 'settings.php' || $basename == 'settings_bak.php') || strpos($file, $real_cache) !== false || !is_readable($file))
		fatal_lang_error('error_bad_file', true, array($smcFunc['htmlspecialchars']($file)));

	// get the min and max lines
	$min = $line - 20 <= 0 ? 1 : $line - 20;
	$max = $line + 21; // One additional line to make everything work out correctly

	if ($max <= 0 || $min >= $max)
		fatal_lang_error('error_bad_line');

	$file_data = explode('<br />', highlight_php_code($smcFunc['htmlspecialchars'](implode('', file($file)))));

	// We don't want to slice off too many so lets make sure we stop at the last one
	$max = min($max, max(array_keys($file_data)));

	$file_data = array_slice($file_data, $min - 1, $max - $min);

	$context['file_data'] = array(
		'contents' => $file_data,
		'min' => $min,
		'target' => $line,
		'file' => strtr($file, array('"' => '\\"')),
	);

	loadTemplate('Errors');
	$context['template_layers'] = array();
	$context['sub_template'] = 'show_file';

}

/**
 * View a backtrace specified in $_REQUEST['backtrace'], with php highlighting on it
 * Preconditions:
 *  - user must have admin_forum permission.
 */
function ViewBacktrace()
{
	global $context, $smcFunc, $scripturl;

	// Check for the administrative permission to do this.
	isAllowedTo('admin_forum');

	$id_error = (int) $_REQUEST['backtrace'];
	$request = $smcFunc['db_query']('', '
		SELECT backtrace, error_type, message, file, line, url
		FROM {db_prefix}log_errors
		WHERE id_error = {int:id_error}',
		array(
			'id_error' => $id_error,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['error_info'] = $row;
		$context['error_info']['url'] = $scripturl . $row['url'];
		$context['error_info']['backtrace'] = $smcFunc['json_decode']($row['backtrace']);
	}
	loadTemplate('Errors');
	loadLanguage('ManageMaintenance');
	$context['template_layers'] = array();
	$context['sub_template'] = 'show_backtrace';

}

?>