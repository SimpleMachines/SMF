<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Alert;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Actions\Who;
use SMF\Actions\Admin\Permissions;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

// Some functions that used to be in this file have been moved.
class_exists('\\SMF\\Alert');
class_exists('\\SMF\\Actions\\Profile\\ShowAlerts');
class_exists('\\SMF\\Actions\\Profile\\ShowPosts');
class_exists('\\SMF\\Actions\\Profile\\StatPanel');
class_exists('\\SMF\\Actions\\Profile\\Summary');

/**
 * Loads up the information for the "track user" section of the profile
 *
 * @param int $memID The ID of the member
 */
function tracking($memID)
{
	$subActions = array(
		'activity' => array('trackActivity', Lang::$txt['trackActivity'], 'moderate_forum'),
		'ip' => array('TrackIP', Lang::$txt['trackIP'], 'moderate_forum'),
		'edits' => array('trackEdits', Lang::$txt['trackEdits'], 'moderate_forum'),
		'groupreq' => array('trackGroupReq', Lang::$txt['trackGroupRequests'], 'approve_group_requests'),
		'logins' => array('TrackLogins', Lang::$txt['trackLogins'], 'moderate_forum'),
	);

	foreach ($subActions as $sa => $action)
	{
		if (!allowedTo($action[2]))
			unset($subActions[$sa]);
	}

	// Create the tabs for the template.
	Menu::$loaded['profile']->tab_data = array(
		'title' => Lang::$txt['tracking'],
		'description' => Lang::$txt['tracking_description'],
		'icon_class' => 'main_icons profile_hd',
		'tabs' => array(
			'activity' => array(),
			'ip' => array(),
			'edits' => array(),
			'groupreq' => array(),
			'logins' => array(),
		),
	);

	// Moderation must be on to track edits.
	if (empty(Config::$modSettings['userlog_enabled']))
		unset(Menu::$loaded['profile']->tab_data['edits'], $subActions['edits']);

	// Group requests must be active to show it...
	if (empty(Config::$modSettings['show_group_membership']))
		unset(Menu::$loaded['profile']->tab_data['groupreq'], $subActions['groupreq']);

	if (empty($subActions))
		fatal_lang_error('no_access', false);

	$keys = array_keys($subActions);
	$default = array_shift($keys);
	Utils::$context['tracking_area'] = isset($_GET['sa']) && isset($subActions[$_GET['sa']]) ? $_GET['sa'] : $default;

	// Set a page title.
	Utils::$context['page_title'] = Lang::$txt['trackUser'] . ' - ' . $subActions[Utils::$context['tracking_area']][1] . ' - ' . User::$loaded[$memID]->name;

	// Pass on to the actual function.
	Utils::$context['sub_template'] = $subActions[Utils::$context['tracking_area']][0];
	$call = call_helper($subActions[Utils::$context['tracking_area']][0], true);

	if (!empty($call))
		call_user_func($call, $memID);
}

/**
 * Handles tracking a user's activity
 *
 * @param int $memID The ID of the member
 */
function trackActivity($memID)
{
	// Verify if the user has sufficient permissions.
	isAllowedTo('moderate_forum');

	Utils::$context['last_ip'] = User::$loaded[$memID]->ip;
	if (Utils::$context['last_ip'] != User::$loaded[$memID]->ip2)
		Utils::$context['last_ip2'] = User::$loaded[$memID]->ip2;
	Utils::$context['member']['name'] = User::$loaded[$memID]->name;

	// Set the options for the list component.
	$listOptions = array(
		'id' => 'track_user_list',
		'title' => Lang::$txt['errors_by'] . ' ' . Utils::$context['member']['name'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['no_errors_from_user'],
		'base_href' => Config::$scripturl . '?action=profile;area=tracking;sa=user;u=' . $memID,
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => Lang::$txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?action=profile;area=tracking;sa=ip;searchip=%1$s;u=' . $memID . '">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'le.ip',
					'reverse' => 'le.ip DESC',
				),
			),
			'message' => array(
				'header' => array(
					'value' => Lang::$txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => Lang::$txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => Lang::$txt['errors_desc'],
			),
		),
	);

	// Create the list for viewing.
	new ItemList($listOptions);

	// @todo cache this
	// If this is a big forum, or a large posting user, let's limit the search.
	if (Config::$modSettings['totalMessages'] > 50000 && User::$loaded[$memID]->posts > 500)
	{
		$request = Db::$db->query('', '
			SELECT MAX(id_msg)
			FROM {db_prefix}messages AS m
			WHERE m.id_member = {int:current_member}',
			array(
				'current_member' => $memID,
			)
		);
		list ($max_msg_member) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
		$min_msg_member = max(0, $max_msg_member - User::$loaded[$memID]->posts * 3);
	}

	// Default to at least the ones we know about.
	$ips = array(
		User::$loaded[$memID]->ip,
		User::$loaded[$memID]->ip2,
	);

	// @todo cache this
	// Get all IP addresses this user has used for his messages.
	$request = Db::$db->query('', '
		SELECT poster_ip
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member}
		' . (isset($min_msg_member) ? '
			AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : '') . '
		GROUP BY poster_ip',
		array(
			'current_member' => $memID,
			'min_msg_member' => !empty($min_msg_member) ? $min_msg_member : 0,
			'max_msg_member' => !empty($max_msg_member) ? $max_msg_member : 0,
		)
	);
	Utils::$context['ips'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		Utils::$context['ips'][] = '<a href="' . Config::$scripturl . '?action=profile;area=tracking;sa=ip;searchip=' . inet_dtop($row['poster_ip']) . ';u=' . $memID . '">' . inet_dtop($row['poster_ip']) . '</a>';
		$ips[] = inet_dtop($row['poster_ip']);
	}
	Db::$db->free_result($request);

	// Now also get the IP addresses from the error messages.
	$request = Db::$db->query('', '
		SELECT COUNT(*) AS error_count, ip
		FROM {db_prefix}log_errors
		WHERE id_member = {int:current_member}
		GROUP BY ip',
		array(
			'current_member' => $memID,
		)
	);
	Utils::$context['error_ips'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$row['ip'] = inet_dtop($row['ip']);
		Utils::$context['error_ips'][] = '<a href="' . Config::$scripturl . '?action=profile;area=tracking;sa=ip;searchip=' . $row['ip'] . ';u=' . $memID . '">' . $row['ip'] . '</a>';
		$ips[] = $row['ip'];
	}
	Db::$db->free_result($request);

	// Find other users that might use the same IP.
	$ips = array_unique($ips);
	Utils::$context['members_in_range'] = array();
	if (!empty($ips))
	{
		// Get member ID's which are in messages...
		$request = Db::$db->query('', '
			SELECT DISTINCT mem.id_member
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.poster_ip IN ({array_inet:ip_list})
				AND mem.id_member != {int:current_member}',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		$message_members = array();
		while ($row = Db::$db->fetch_assoc($request))
			$message_members[] = $row['id_member'];
		Db::$db->free_result($request);

		// Fetch their names, cause of the GROUP BY doesn't like giving us that normally.
		if (!empty($message_members))
		{
			$request = Db::$db->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:message_members})',
				array(
					'message_members' => $message_members,
					'ip_list' => $ips,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
				Utils::$context['members_in_range'][$row['id_member']] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			Db::$db->free_result($request);
		}

		$request = Db::$db->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member != {int:current_member}
				AND member_ip IN ({array_inet:ip_list})',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			Utils::$context['members_in_range'][$row['id_member']] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		Db::$db->free_result($request);
	}
}

/**
 * Get the number of user errors
 *
 * @param string $where A query to limit which errors are counted
 * @param array $where_vars The parameters for $where
 * @return int Number of user errors
 */
function list_getUserErrorCount($where, $where_vars = array())
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_errors
		WHERE ' . $where,
		$where_vars
	);
	list ($count) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return (int) $count;
}

/**
 * Gets all of the errors generated by a user's actions. Callback for the list in track_activity
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param string $where A query indicating how to filter the results (eg 'id_member={int:id_member}')
 * @param array $where_vars An array of parameters for $where
 * @return array An array of information about the error messages
 */
function list_getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array())
{
	// Get a list of error messages from this ip (range).
	$request = Db::$db->query('', '
		SELECT
			le.log_time, le.ip, le.url, le.message, COALESCE(mem.id_member, 0) AS id_member,
			COALESCE(mem.real_name, {string:guest_title}) AS display_name, mem.member_name
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = le.id_member)
		WHERE ' . $where . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array_merge($where_vars, array(
			'guest_title' => Lang::$txt['guest_title'],
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		))
	);
	$error_messages = array();
	while ($row = Db::$db->fetch_assoc($request))
		$error_messages[] = array(
			'ip' => inet_dtop($row['ip']),
			'member_link' => $row['id_member'] > 0 ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>' : $row['display_name'],
			'message' => strtr($row['message'], array('&lt;span class=&quot;remove&quot;&gt;' => '', '&lt;/span&gt;' => '')),
			'url' => $row['url'],
			'time' => timeformat($row['log_time']),
			'timestamp' => $row['log_time'],
		);
	Db::$db->free_result($request);

	return $error_messages;
}

/**
 * Gets the number of posts made from a particular IP
 *
 * @param string $where A query indicating which posts to count
 * @param array $where_vars The parameters for $where
 * @return int Count of messages matching the IP
 */
function list_getIPMessageCount($where, $where_vars = array())
{
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}messages AS m
		WHERE {query_see_message_board} AND ' . $where,
		$where_vars
	);
	list ($count) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return (int) $count;
}

/**
 * Gets all the posts made from a particular IP
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param string $where A query to filter which posts are returned
 * @param array $where_vars An array of parameters for $where
 * @return array An array containing information about the posts
 */
function list_getIPMessages($start, $items_per_page, $sort, $where, $where_vars = array())
{

	// Get all the messages fitting this where clause.
	$request = Db::$db->query('', '
		SELECT
			m.id_msg, m.poster_ip, COALESCE(mem.real_name, m.poster_name) AS display_name, mem.id_member,
			m.subject, m.poster_time, m.id_topic, m.id_board
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE {query_see_message_board} AND ' . $where . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array_merge($where_vars, array(
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		))
	);
	$messages = array();
	while ($row = Db::$db->fetch_assoc($request))
		$messages[] = array(
			'ip' => inet_dtop($row['poster_ip']),
			'member_link' => empty($row['id_member']) ? $row['display_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>',
			'board' => array(
				'id' => $row['id_board'],
				'href' => Config::$scripturl . '?board=' . $row['id_board']
			),
			'topic' => $row['id_topic'],
			'id' => $row['id_msg'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => $row['poster_time']
		);
	Db::$db->free_result($request);

	return $messages;
}

/**
 * Handles tracking a particular IP address
 *
 * @param int $memID The ID of a member whose IP we want to track
 */
function TrackIP($memID = 0)
{
	// Can the user do this?
	isAllowedTo('moderate_forum');

	if ($memID == 0)
	{
		Utils::$context['ip'] = ip2range(User::$me->ip);
		Theme::loadTemplate('Profile');
		Lang::load('Profile');
		Utils::$context['sub_template'] = 'trackIP';
		Utils::$context['page_title'] = Lang::$txt['profile'];
		Utils::$context['base_url'] = Config::$scripturl . '?action=trackip';
	}
	else
	{
		Utils::$context['ip'] = ip2range(User::$loaded[$memID]->ip);
		Utils::$context['base_url'] = Config::$scripturl . '?action=profile;area=tracking;sa=ip;u=' . $memID;
	}

	// Searching?
	if (isset($_REQUEST['searchip']))
		Utils::$context['ip'] = ip2range(trim($_REQUEST['searchip']));

	if (count(Utils::$context['ip']) !== 2)
		fatal_lang_error('invalid_tracking_ip', false);

	$ip_string = array('{inet:ip_address_low}', '{inet:ip_address_high}');
	$fields = array(
		'ip_address_low' => Utils::$context['ip']['low'],
		'ip_address_high' => Utils::$context['ip']['high'],
	);

	$ip_var = Utils::$context['ip'];

	if (Utils::$context['ip']['low'] !== Utils::$context['ip']['high'])
		Utils::$context['ip'] = Utils::$context['ip']['low'] . '-' . Utils::$context['ip']['high'];
	else
		Utils::$context['ip'] = Utils::$context['ip']['low'];

	if (empty(Utils::$context['tracking_area']))
		Utils::$context['page_title'] = Lang::$txt['trackIP'] . ' - ' . Utils::$context['ip'];

	$request = Db::$db->query('', '
		SELECT id_member, real_name AS display_name, member_ip
		FROM {db_prefix}members
		WHERE member_ip >= ' . $ip_string[0] . ' and member_ip <= ' . $ip_string[1],
		$fields
	);
	Utils::$context['ips'] = array();
	while ($row = Db::$db->fetch_assoc($request))
		Utils::$context['ips'][inet_dtop($row['member_ip'])][] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>';
	Db::$db->free_result($request);

	ksort(Utils::$context['ips']);

	// For messages we use the "messages per page" option
	$maxPerPage = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

	// Start with the user messages.
	$listOptions = array(
		'id' => 'track_message_list',
		'title' => Lang::$txt['messages_from_ip'] . ' ' . Utils::$context['ip'],
		'start_var_name' => 'messageStart',
		'items_per_page' => $maxPerPage,
		'no_items_label' => Lang::$txt['no_messages_from_ip'],
		'base_href' => Utils::$context['base_url'] . ';searchip=' . Utils::$context['ip'],
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getIPMessages',
			'params' => array(
				'm.poster_ip >= ' . $ip_string[0] . ' and m.poster_ip <= ' . $ip_string[1],
				$fields,
			),
		),
		'get_count' => array(
			'function' => 'list_getIPMessageCount',
			'params' => array(
				'm.poster_ip >= ' . $ip_string[0] . ' and m.poster_ip <= ' . $ip_string[1],
				$fields,
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => Lang::$txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Utils::$context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'm.poster_ip',
					'reverse' => 'm.poster_ip DESC',
				),
			),
			'poster' => array(
				'header' => array(
					'value' => Lang::$txt['poster'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'subject' => array(
				'header' => array(
					'value' => Lang::$txt['subject'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?topic=%1$s.msg%2$s#msg%2$s" rel="nofollow">%3$s</a>',
						'params' => array(
							'topic' => false,
							'id' => false,
							'subject' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => Lang::$txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'm.id_msg DESC',
					'reverse' => 'm.id_msg',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => Lang::$txt['messages_from_ip_desc'],
			),
		),
	);

	// Create the messages list.
	new ItemList($listOptions);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'track_user_list',
		'title' => Lang::$txt['errors_from_ip'] . ' ' . Utils::$context['ip'],
		'start_var_name' => 'errorStart',
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['no_errors_from_ip'],
		'base_href' => Utils::$context['base_url'] . ';searchip=' . Utils::$context['ip'],
		'default_sort_col' => 'date2',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.ip >= ' . $ip_string[0] . ' and le.ip <= ' . $ip_string[1],
				$fields,
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'ip >= ' . $ip_string[0] . ' and ip <= ' . $ip_string[1],
				$fields,
			),
		),
		'columns' => array(
			'ip_address2' => array(
				'header' => array(
					'value' => Lang::$txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Utils::$context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'le.ip',
					'reverse' => 'le.ip DESC',
				),
			),
			'display_name' => array(
				'header' => array(
					'value' => Lang::$txt['display_name'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'message' => array(
				'header' => array(
					'value' => Lang::$txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
					'class' => 'word_break',
				),
			),
			'date2' => array(
				'header' => array(
					'value' => Lang::$txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => Lang::$txt['errors_from_ip_desc'],
			),
		),
	);

	// Create the error list.
	new ItemList($listOptions);

	// Allow 3rd party integrations to add in their own lists or whatever.
	Utils::$context['additional_track_lists'] = array();
	call_integration_hook('integrate_profile_trackip', array($ip_string, $ip_var));

	Utils::$context['single_ip'] = ($ip_var['low'] === $ip_var['high']);
	if (Utils::$context['single_ip'])
	{
		Utils::$context['whois_servers'] = array(
			'apnic' => array(
				'name' => Lang::$txt['whois_apnic'],
				'url' => 'https://wq.apnic.net/apnic-bin/whois.pl?searchtext=' . Utils::$context['ip'],
			),
			'arin' => array(
				'name' => Lang::$txt['whois_arin'],
				'url' => 'https://whois.arin.net/rest/ip/' . Utils::$context['ip'],
			),
			'lacnic' => array(
				'name' => Lang::$txt['whois_lacnic'],
				'url' => 'https://lacnic.net/cgi-bin/lacnic/whois?query=' . Utils::$context['ip'],
			),
			'ripe' => array(
				'name' => Lang::$txt['whois_ripe'],
				'url' => 'https://apps.db.ripe.net/search/query.html?searchtext=' . Utils::$context['ip'],
			),
		);
	}
}

/**
 * Tracks a user's logins.
 *
 * @param int $memID The ID of the member
 */
function TrackLogins($memID = 0)
{
	if ($memID == 0)
		Utils::$context['base_url'] = Config::$scripturl . '?action=trackip';
	else
		Utils::$context['base_url'] = Config::$scripturl . '?action=profile;area=tracking;sa=ip;u=' . $memID;

	// Start with the user messages.
	$listOptions = array(
		'id' => 'track_logins_list',
		'title' => Lang::$txt['trackLogins'],
		'no_items_label' => Lang::$txt['trackLogins_none_found'],
		'base_href' => Utils::$context['base_url'],
		'get_items' => array(
			'function' => 'list_getLogins',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'get_count' => array(
			'function' => 'list_getLoginCount',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'columns' => array(
			'time' => array(
				'header' => array(
					'value' => Lang::$txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => Lang::$txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Utils::$context['base_url'] . ';searchip=%1$s">%1$s</a> (<a href="' . Utils::$context['base_url'] . ';searchip=%2$s">%2$s</a>) ',
						'params' => array(
							'ip' => false,
							'ip2' => false
						),
					),
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => Lang::$txt['trackLogins_desc'],
			),
		),
	);

	// Create the messages list.
	new ItemList($listOptions);

	Utils::$context['sub_template'] = 'show_list';
	Utils::$context['default_list'] = 'track_logins_list';
}

/**
 * Finds the total number of tracked logins for a particular user
 *
 * @param string $where A query to limit which logins are counted
 * @param array $where_vars An array of parameters for $where
 * @return int count of messages matching the IP
 */
function list_getLoginCount($where, $where_vars = array())
{
	$request = Db::$db->query('', '
		SELECT COUNT(*) AS message_count
		FROM {db_prefix}member_logins
		WHERE id_member = {int:id_member}',
		array(
			'id_member' => $where_vars['current_member'],
		)
	);
	list ($count) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return (int) $count;
}

/**
 * Callback for the list in trackLogins.
 *
 * @param int $start Which item to start with (not used here)
 * @param int $items_per_page How many items to show on each page (not used here)
 * @param string $sort A string indicating
 * @param string $where A query to filter results (not used here)
 * @param array $where_vars An array of parameters for $where. Only 'current_member' (the ID of the member) is used here
 * @return array An array of information about user logins
 */
function list_getLogins($start, $items_per_page, $sort, $where, $where_vars = array())
{
	$request = Db::$db->query('', '
		SELECT time, ip, ip2
		FROM {db_prefix}member_logins
		WHERE id_member = {int:id_member}
		ORDER BY time DESC',
		array(
			'id_member' => $where_vars['current_member'],
		)
	);
	$logins = array();
	while ($row = Db::$db->fetch_assoc($request))
		$logins[] = array(
			'time' => timeformat($row['time']),
			'ip' => inet_dtop($row['ip']),
			'ip2' => inet_dtop($row['ip2']),
		);
	Db::$db->free_result($request);

	return $logins;
}

/**
 * Tracks a user's profile edits
 *
 * @param int $memID The ID of the member
 */
function trackEdits($memID)
{
	// Get the names of any custom fields.
	$request = Db::$db->query('', '
		SELECT col_name, field_name, bbc
		FROM {db_prefix}custom_fields',
		array(
		)
	);
	Utils::$context['custom_field_titles'] = array();
	while ($row = Db::$db->fetch_assoc($request))
		Utils::$context['custom_field_titles']['customfield_' . $row['col_name']] = array(
			'title' => $row['field_name'],
			'parse_bbc' => $row['bbc'],
		);
	Db::$db->free_result($request);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'edit_list',
		'title' => Lang::$txt['trackEdits'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['trackEdit_no_edits'],
		'base_href' => Config::$scripturl . '?action=profile;area=tracking;sa=edits;u=' . $memID,
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getProfileEdits',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getProfileEditCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => Lang::$txt['trackEdit_action'],
				),
				'data' => array(
					'db' => 'action_text',
				),
			),
			'before' => array(
				'header' => array(
					'value' => Lang::$txt['trackEdit_before'],
				),
				'data' => array(
					'db' => 'before',
				),
			),
			'after' => array(
				'header' => array(
					'value' => Lang::$txt['trackEdit_after'],
				),
				'data' => array(
					'db' => 'after',
				),
			),
			'time' => array(
				'header' => array(
					'value' => Lang::$txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'id_action DESC',
					'reverse' => 'id_action',
				),
			),
			'applicator' => array(
				'header' => array(
					'value' => Lang::$txt['trackEdit_applicator'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
		),
	);

	// Create the error list.
	new ItemList($listOptions);

	Utils::$context['sub_template'] = 'show_list';
	Utils::$context['default_list'] = 'edit_list';
}

/**
 * How many edits?
 *
 * @param int $memID The ID of the member
 * @return int The number of profile edits
 */
function list_getProfileEditCount($memID)
{
	$request = Db::$db->query('', '
		SELECT COUNT(*) AS edit_count
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}',
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	list ($edit_count) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return (int) $edit_count;
}

/**
 * Loads up information about a user's profile edits. Callback for the list in trackEdits()
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about the profile edits
 */
function list_getProfileEdits($start, $items_per_page, $sort, $memID)
{
	// Get a list of error messages from this ip (range).
	$request = Db::$db->query('', '
		SELECT
			id_action, id_member, ip, log_time, action, extra
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array(
			'log_type' => 2,
			'owner' => $memID,
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		)
	);
	$edits = array();
	$members = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$extra = Utils::jsonDecode($row['extra'], true);
		if (!empty($extra['applicator']))
			$members[] = $extra['applicator'];

		// Work out what the name of the action is.
		if (isset(Lang::$txt['trackEdit_action_' . $row['action']]))
			$action_text = Lang::$txt['trackEdit_action_' . $row['action']];
		elseif (isset(Lang::$txt[$row['action']]))
			$action_text = Lang::$txt[$row['action']];
		// Custom field?
		elseif (isset(Utils::$context['custom_field_titles'][$row['action']]))
			$action_text = Utils::$context['custom_field_titles'][$row['action']]['title'];
		else
			$action_text = $row['action'];

		// Parse BBC?
		$parse_bbc = isset(Utils::$context['custom_field_titles'][$row['action']]) && Utils::$context['custom_field_titles'][$row['action']]['parse_bbc'] ? true : false;

		$edits[] = array(
			'id' => $row['id_action'],
			'ip' => inet_dtop($row['ip']),
			'id_member' => !empty($extra['applicator']) ? $extra['applicator'] : 0,
			'member_link' => Lang::$txt['trackEdit_deleted_member'],
			'action' => $row['action'],
			'action_text' => $action_text,
			'before' => !empty($extra['previous']) ? ($parse_bbc ? BBCodeParser::load()->parse($extra['previous']) : $extra['previous']) : '',
			'after' => !empty($extra['new']) ? ($parse_bbc ? BBCodeParser::load()->parse($extra['new']) : $extra['new']) : '',
			'time' => timeformat($row['log_time']),
		);
	}
	Db::$db->free_result($request);

	// Get any member names.
	if (!empty($members))
	{
		$request = Db::$db->query('', '
			SELECT
				id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => $members,
			)
		);
		$members = array();
		while ($row = Db::$db->fetch_assoc($request))
			$members[$row['id_member']] = $row['real_name'];
		Db::$db->free_result($request);

		foreach ($edits as $key => $value)
			if (isset($members[$value['id_member']]))
				$edits[$key]['member_link'] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $value['id_member'] . '">' . $members[$value['id_member']] . '</a>';
	}

	return $edits;
}

/**
 * Display the history of group requests made by the user whose profile we are viewing.
 *
 * @param int $memID The ID of the member
 */
function trackGroupReq($memID)
{
	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'request_list',
		'title' => sprintf(Lang::$txt['trackGroupRequests_title'], Utils::$context['member']['name']),
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['requested_none'],
		'base_href' => Config::$scripturl . '?action=profile;area=tracking;sa=groupreq;u=' . $memID,
		'default_sort_col' => 'time_applied',
		'get_items' => array(
			'function' => 'list_getGroupRequests',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getGroupRequestsCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'group' => array(
				'header' => array(
					'value' => Lang::$txt['requested_group'],
				),
				'data' => array(
					'db' => 'group_name',
				),
			),
			'group_reason' => array(
				'header' => array(
					'value' => Lang::$txt['requested_group_reason'],
				),
				'data' => array(
					'db' => 'group_reason',
				),
			),
			'time_applied' => array(
				'header' => array(
					'value' => Lang::$txt['requested_group_time'],
				),
				'data' => array(
					'db' => 'time_applied',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'time_applied DESC',
					'reverse' => 'time_applied',
				),
			),
			'outcome' => array(
				'header' => array(
					'value' => Lang::$txt['requested_group_outcome'],
				),
				'data' => array(
					'db' => 'outcome',
				),
			),
		),
	);

	// Create the error list.
	new ItemList($listOptions);

	Utils::$context['sub_template'] = 'show_list';
	Utils::$context['default_list'] = 'request_list';
}

/**
 * How many edits?
 *
 * @param int $memID The ID of the member
 * @return int The number of profile edits
 */
function list_getGroupRequestsCount($memID)
{
	$request = Db::$db->query('', '
		SELECT COUNT(*) AS req_count
		FROM {db_prefix}log_group_requests AS lgr
		WHERE id_member = {int:memID}
			AND ' . (User::$me->mod_cache['gq'] == '1=1' ? User::$me->mod_cache['gq'] : 'lgr.' . User::$me->mod_cache['gq']),
		array(
			'memID' => $memID,
		)
	);
	list ($report_count) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return (int) $report_count;
}

/**
 * Loads up information about a user's group requests. Callback for the list in trackGroupReq()
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about the user's group requests
 */
function list_getGroupRequests($start, $items_per_page, $sort, $memID)
{
	$groupreq = array();

	$request = Db::$db->query('', '
		SELECT
			lgr.id_group, mg.group_name, mg.online_color, lgr.time_applied, lgr.reason, lgr.status,
			ma.id_member AS id_member_acted, COALESCE(ma.member_name, lgr.member_name_acted) AS act_name, lgr.time_acted, lgr.act_reason
		FROM {db_prefix}log_group_requests AS lgr
			LEFT JOIN {db_prefix}members AS ma ON (lgr.id_member_acted = ma.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (lgr.id_group = mg.id_group)
		WHERE lgr.id_member = {int:memID}
			AND ' . (User::$me->mod_cache['gq'] == '1=1' ? User::$me->mod_cache['gq'] : 'lgr.' . User::$me->mod_cache['gq']) . '
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:max}',
		array(
			'memID' => $memID,
			'sort' => $sort,
			'start' => $start,
			'max' => $items_per_page,
		)
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		$this_req = array(
			'group_name' => empty($row['online_color']) ? $row['group_name'] : '<span style="color:' . $row['online_color'] . '">' . $row['group_name'] . '</span>',
			'group_reason' => $row['reason'],
			'time_applied' => $row['time_applied'],
		);
		switch ($row['status'])
		{
			case 0:
				$this_req['outcome'] = Lang::$txt['outcome_pending'];
				break;
			case 1:
				$member_link = empty($row['id_member_acted']) ? $row['act_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member_acted'] . '">' . $row['act_name'] . '</a>';
				$this_req['outcome'] = sprintf(Lang::$txt['outcome_approved'], $member_link, timeformat($row['time_acted']));
				break;
			case 2:
				$member_link = empty($row['id_member_acted']) ? $row['act_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member_acted'] . '">' . $row['act_name'] . '</a>';
				$this_req['outcome'] = sprintf(!empty($row['act_reason']) ? Lang::$txt['outcome_refused_reason'] : Lang::$txt['outcome_refused'], $member_link, timeformat($row['time_acted']), $row['act_reason']);
				break;
		}

		$groupreq[] = $this_req;
	}
	Db::$db->free_result($request);

	return $groupreq;
}

/**
 * View a member's warnings
 *
 * @param int $memID The ID of the member
 */
function viewWarning($memID)
{
	// Firstly, can we actually even be here?
	if (!(User::$me->is_owner && allowedTo('view_warning_own')) && !allowedTo('view_warning_any') && !allowedTo('issue_warning') && !allowedTo('moderate_forum'))
		fatal_lang_error('no_access', false);

	// Make sure things which are disabled stay disabled.
	Config::$modSettings['warning_watch'] = !empty(Config::$modSettings['warning_watch']) ? Config::$modSettings['warning_watch'] : 110;
	Config::$modSettings['warning_moderate'] = !empty(Config::$modSettings['warning_moderate']) && !empty(Config::$modSettings['postmod_active']) ? Config::$modSettings['warning_moderate'] : 110;
	Config::$modSettings['warning_mute'] = !empty(Config::$modSettings['warning_mute']) ? Config::$modSettings['warning_mute'] : 110;

	// Let's use a generic list to get all the current warnings, and use the issue warnings grab-a-granny thing.
	require_once(Config::$sourcedir . '/Profile-Actions.php');

	$listOptions = array(
		'id' => 'view_warnings',
		'title' => Lang::$txt['profile_viewwarning_previous_warnings'],
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['profile_viewwarning_no_warnings'],
		'base_href' => Config::$scripturl . '?action=profile;area=viewwarning;sa=user;u=' . $memID,
		'default_sort_col' => 'log_time',
		'get_items' => array(
			'function' => 'list_getUserWarnings',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getUserWarningCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'log_time' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_time'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'lc.log_time DESC',
					'reverse' => 'lc.log_time',
				),
			),
			'reason' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_reason'],
					'style' => 'width: 50%;',
				),
				'data' => array(
					'db' => 'reason',
				),
			),
			'level' => array(
				'header' => array(
					'value' => Lang::$txt['profile_warning_previous_level'],
				),
				'data' => array(
					'db' => 'counter',
				),
				'sort' => array(
					'default' => 'lc.counter DESC',
					'reverse' => 'lc.counter',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => Lang::$txt['profile_viewwarning_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the list for viewing.
	new ItemList($listOptions);

	// Create some common text bits for the template.
	Utils::$context['level_effects'] = array(
		0 => '',
		Config::$modSettings['warning_watch'] => Lang::$txt['profile_warning_effect_own_watched'],
		Config::$modSettings['warning_moderate'] => Lang::$txt['profile_warning_effect_own_moderated'],
		Config::$modSettings['warning_mute'] => Lang::$txt['profile_warning_effect_own_muted'],
	);
	Utils::$context['current_level'] = 0;
	foreach (Utils::$context['level_effects'] as $limit => $dummy)
		if (Utils::$context['member']['warning'] >= $limit)
			Utils::$context['current_level'] = $limit;
}

?>