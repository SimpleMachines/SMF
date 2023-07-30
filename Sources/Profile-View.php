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
class_exists('\\SMF\\Actions\\Profile\\StatPanel');
class_exists('\\SMF\\Actions\\Profile\\Summary');

/**
 * Show all posts by a member
 *
 * @todo This function needs to be split up properly.
 *
 * @param int $memID The ID of the member
 */
function showPosts($memID)
{
	// Some initial context.
	Utils::$context['start'] = (int) $_REQUEST['start'];
	Utils::$context['current_member'] = $memID;

	// Create the tabs for the template.
	Menu::$loaded['profile']->tab_data = array(
		'title' => Lang::$txt['showPosts'],
		'description' => Lang::$txt['showPosts_help'],
		'icon_class' => 'main_icons profile_hd',
		'tabs' => array(
			'messages' => array(
			),
			'topics' => array(
			),
			'unwatchedtopics' => array(
			),
			'attach' => array(
			),
		),
	);

	// Shortcut used to determine which Lang::$txt['show*'] string to use for the title, based on the SA
	$title = array(
		'attach' => 'Attachments',
		'topics' => 'Topics'
	);

	if (User::$me->is_owner)
		$title['unwatchedtopics'] = 'Unwatched';

	// Set the page title
	if (isset($_GET['sa']) && array_key_exists($_GET['sa'], $title))
		Utils::$context['page_title'] = Lang::$txt['show' . $title[$_GET['sa']]];
	else
		Utils::$context['page_title'] = Lang::$txt['showPosts'];

	Utils::$context['page_title'] .= ' - ' . User::$loaded[$memID]->name;

	// Is the load average too high to allow searching just now?
	if (!empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_show_posts']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_show_posts'])
		fatal_lang_error('loadavg_show_posts_disabled', false);

	// If we're specifically dealing with attachments use that function!
	if (isset($_GET['sa']) && $_GET['sa'] == 'attach')
		return showAttachments($memID);
	// Instead, if we're dealing with unwatched topics (and the feature is enabled) use that other function.
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'unwatchedtopics' && User::$me->is_owner)
		return showUnwatched($memID);

	// Are we just viewing topics?
	Utils::$context['is_topics'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? true : false;

	// If just deleting a message, do it and then redirect back.
	if (isset($_GET['delete']) && !Utils::$context['is_topics'])
	{
		checkSession('get');

		// We need msg info for logging.
		$request = Db::$db->query('', '
			SELECT subject, id_member, id_topic, id_board
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => (int) $_GET['delete'],
			)
		);
		$info = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Trying to remove a message that doesn't exist.
		if (empty($info))
			redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);

		// We can be lazy, since Msg::remove() will check the permissions for us.
		Msg::remove((int) $_GET['delete']);

		// Add it to the mod log.
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != User::$me->id))
			logAction('delete', array('topic' => $info[2], 'subject' => $info[0], 'member' => $info[1], 'board' => $info[3]));

		// Back to... where we are now ;).
		redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);
	}

	// Default to 10.
	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = '10';

	if (Utils::$context['is_topics'])
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t' . '
			WHERE {query_see_topic_board}
				AND t.id_member_started = {int:current_member}' . (!empty(Board::$info->id) ? '
				AND t.id_board = {int:board}' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
				AND t.approved = {int:is_approved}'),
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => Board::$info->id,
			)
		);
	else
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}messages AS m' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
			WHERE {query_see_message_board} AND m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
				AND m.id_board = {int:board}' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}'),
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => Board::$info->id,
			)
		);
	list ($msgCount) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	$request = Db::$db->query('', '
		SELECT MIN(id_msg), MAX(id_msg)
		FROM {db_prefix}messages AS m' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
		WHERE m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
			AND m.id_board = {int:board}' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
			AND m.approved = {int:is_approved}
			AND t.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => Board::$info->id,
		)
	);
	list ($min_msg_member, $max_msg_member) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	$range_limit = '';

	if (Utils::$context['is_topics'])
		$maxPerPage = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['topics_per_page']) ? Theme::$current->options['topics_per_page'] : Config::$modSettings['defaultMaxTopics'];
	else
		$maxPerPage = empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['messages_per_page']) ? Theme::$current->options['messages_per_page'] : Config::$modSettings['defaultMaxMessages'];

	$maxIndex = $maxPerPage;

	// Make sure the starting place makes sense and construct our friend the page index.
	Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=profile;u=' . $memID . ';area=showposts' . (Utils::$context['is_topics'] ? ';sa=topics' : '') . (!empty(Board::$info->id) ? ';board=' . Board::$info->id : ''), Utils::$context['start'], $msgCount, $maxIndex);
	Utils::$context['current_page'] = Utils::$context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = Utils::$context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < Utils::$context['start'] + $maxPerPage + 1 && $msgCount > Utils::$context['start'] ? $msgCount - Utils::$context['start'] : $maxPerPage;
		$start = $msgCount < Utils::$context['start'] + $maxPerPage + 1 || $msgCount < Utils::$context['start'] + $maxPerPage ? 0 : $msgCount - Utils::$context['start'] - $maxPerPage;
	}

	// Guess the range of messages to be shown.
	if ($msgCount > 1000)
	{
		$margin = floor(($max_msg_member - $min_msg_member) * (($start + $maxPerPage) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
		// Make a bigger margin for topics only.
		if (Utils::$context['is_topics'])
		{
			$margin *= 5;
			$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
		}
		else
			$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
	}

	// Find this user's posts.  The left join on categories somehow makes this faster, weird as it looks.
	$looped = false;
	while (true)
	{
		if (Utils::$context['is_topics'])
		{
			$request = Db::$db->query('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, t.id_member_started, t.id_first_msg, t.id_last_msg,
					t.approved, m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_member_started = {int:current_member}' . (!empty(Board::$info->id) ? '
					AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT {int:start}, {int:max}',
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => Board::$info->id,
					'start' => $start,
					'max' => $maxIndex,
				)
			);
		}
		else
		{
			$request = Db::$db->query('', '
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
					t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
					m.subject, m.poster_time, m.approved
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
					AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . '
					AND {query_see_board}' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner ? '' : '
					AND t.approved = {int:is_approved} AND m.approved = {int:is_approved}') . '
				ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT {int:start}, {int:max}',
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => Board::$info->id,
					'start' => $start,
					'max' => $maxIndex,
				)
			);
		}

		// Make sure we quit this loop.
		if (Db::$db->num_rows($request) === $maxIndex || $looped || $range_limit == '')
			break;
		$looped = true;
		$range_limit = '';
	}

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? Utils::$context['start'] + $maxIndex + 1 : Utils::$context['start'];
	Utils::$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = Db::$db->fetch_assoc($request))
	{
		// Censor....
		Lang::censorText($row['body']);
		Lang::censorText($row['subject']);

		// Do the code.
		$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// And the array...
		Utils::$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'body' => $row['body'],
			'counter' => $counter,
			'category' => array(
				'name' => $row['cname'],
				'id' => $row['id_cat']
			),
			'board' => array(
				'name' => $row['bname'],
				'id' => $row['id_board']
			),
			'topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'start' => 'msg' . $row['id_msg'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => $row['poster_time'],
			'id' => $row['id_msg'],
			'can_reply' => false,
			'can_mark_notify' => !User::$me->is_guest,
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty(Config::$modSettings['edit_disable_time']) || $row['poster_time'] + Config::$modSettings['edit_disable_time'] * 60 >= time()),
			'approved' => $row['approved'],
			'css_class' => $row['approved'] ? 'windowbg' : 'approvebg',
		);

		if (User::$me->id == $row['id_member_started'])
			$board_ids['own'][$row['id_board']][] = $counter;
		$board_ids['any'][$row['id_board']][] = $counter;
	}
	Db::$db->free_result($request);

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		Utils::$context['posts'] = array_reverse(Utils::$context['posts'], true);

	// These are all the permissions that are different from board to board..
	if (Utils::$context['is_topics'])
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
			)
		);
	else
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'delete_any' => 'can_delete',
			)
		);

	// Create an array for the permissions.
	$boards_can = boardsAllowedTo(array_keys(iterator_to_array(
		new RecursiveIteratorIterator(new RecursiveArrayIterator($permissions)))
	), true, false);

	// For every permission in the own/any lists...
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// Get the boards they can do this on...
			$boards = $boards_can[$permission];

			// Hmm, they can do it on all boards, can they?
			if (!empty($boards) && $boards[0] == 0)
				$boards = array_keys($board_ids[$type]);

			// Now go through each board they can do the permission on.
			foreach ($boards as $board_id)
			{
				// There aren't any posts displayed from this board.
				if (!isset($board_ids[$type][$board_id]))
					continue;

				// Set the permission to true ;).
				foreach ($board_ids[$type][$board_id] as $counter)
					Utils::$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	// Clean up after posts that cannot be deleted and quoted.
	$quote_enabled = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));
	foreach (Utils::$context['posts'] as $counter => $dummy)
	{
		Utils::$context['posts'][$counter]['can_delete'] &= Utils::$context['posts'][$counter]['delete_possible'];
		Utils::$context['posts'][$counter]['can_quote'] = Utils::$context['posts'][$counter]['can_reply'] && $quote_enabled;
	}

	// Allow last minute changes.
	call_integration_hook('integrate_profile_showPosts');

	foreach (Utils::$context['posts'] as $key => $post)
	{
		Utils::$context['posts'][$key]['quickbuttons'] = array(
			'reply' => array(
				'label' => Lang::$txt['reply'],
				'href' => Config::$scripturl.'?action=post;topic='.$post['topic'].'.'.$post['start'],
				'icon' => 'reply_button',
				'show' => $post['can_reply']
			),
			'quote' => array(
				'label' => Lang::$txt['quote_action'],
				'href' => Config::$scripturl.'?action=post;topic='.$post['topic'].'.'.$post['start'].';quote='.$post['id'],
				'icon' => 'quote',
				'show' => $post['can_quote']
			),
			'remove' => array(
				'label' => Lang::$txt['remove'],
				'href' => Config::$scripturl.'?action=deletemsg;msg='.$post['id'].';topic='.$post['topic'].';profile;u='.Utils::$context['member']['id'].';start='.Utils::$context['start'].';'.Utils::$context['session_var'].'='.Utils::$context['session_id'],
				'javascript' => 'data-confirm="'.Lang::$txt['remove_message'].'"',
				'class' => 'you_sure',
				'icon' => 'remove_button',
				'show' => $post['can_delete']
			)
		);
	}
}

/**
 * Show all the attachments belonging to a member.
 *
 * @param int $memID The ID of the member
 */
function showAttachments($memID)
{
	// OBEY permissions!
	$boardsAllowed = boardsAllowedTo('view_attachments');

	// Make sure we can't actually see anything...
	if (empty($boardsAllowed))
		$boardsAllowed = array(-1);

	// This is all the information required to list attachments.
	$listOptions = array(
		'id' => 'attachments',
		'width' => '100%',
		'items_per_page' => Config::$modSettings['defaultMaxListItems'],
		'no_items_label' => Lang::$txt['show_attachments_none'],
		'base_href' => Config::$scripturl . '?action=profile;area=showposts;sa=attach;u=' . $memID,
		'default_sort_col' => 'filename',
		'get_items' => array(
			'function' => 'list_getAttachments',
			'params' => array(
				$boardsAllowed,
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getNumAttachments',
			'params' => array(
				$boardsAllowed,
				$memID,
			),
		),
		'data_check' => array(
			'class' => function($data)
			{
				return $data['approved'] ? '' : 'approvebg';
			}
		),
		'columns' => array(
			'filename' => array(
				'header' => array(
					'value' => Lang::$txt['show_attach_filename'],
					'class' => 'lefttext',
					'style' => 'width: 25%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?action=dlattach;topic=%1$d.0;attach=%2$d">%3$s</a>%4$s',
						'params' => array(
							'topic' => true,
							'id' => true,
							'filename' => false,
							'awaiting_approval' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'a.filename',
					'reverse' => 'a.filename DESC',
				),
			),
			'downloads' => array(
				'header' => array(
					'value' => Lang::$txt['show_attach_downloads'],
					'style' => 'width: 12%;',
				),
				'data' => array(
					'db' => 'downloads',
					'comma_format' => true,
				),
				'sort' => array(
					'default' => 'a.downloads',
					'reverse' => 'a.downloads DESC',
				),
			),
			'subject' => array(
				'header' => array(
					'value' => Lang::$txt['message'],
					'class' => 'lefttext',
					'style' => 'width: 30%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?msg=%1$d">%2$s</a>',
						'params' => array(
							'msg' => true,
							'subject' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'm.subject',
					'reverse' => 'm.subject DESC',
				),
			),
			'posted' => array(
				'header' => array(
					'value' => Lang::$txt['show_attach_posted'],
					'class' => 'lefttext',
				),
				'data' => array(
					'db' => 'posted',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'm.poster_time',
					'reverse' => 'm.poster_time DESC',
				),
			),
		),
	);

	// Create the request list.
	new ItemList($listOptions);
}

/**
 * Get a list of attachments for a member. Callback for the list in showAttachments()
 *
 * @param int $start Which item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param array $boardsAllowed An array containing the IDs of the boards they can see
 * @param int $memID The ID of the member
 * @return array An array of information about the attachments
 */
function list_getAttachments($start, $items_per_page, $sort, $boardsAllowed, $memID)
{
	// Retrieve some attachments.
	$request = Db::$db->query('', '
		SELECT a.id_attach, a.id_msg, a.filename, a.downloads, a.approved, m.id_msg, m.id_topic,
			m.id_board, m.poster_time, m.subject, b.name
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!Config::$modSettings['postmod_active'] || allowedTo('approve_posts') || User::$me->is_owner ? '' : '
			AND a.approved = {int:is_approved}') . '
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => Board::$info->id,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
	$attachments = array();
	while ($row = Db::$db->fetch_assoc($request))
		$attachments[] = array(
			'id' => $row['id_attach'],
			'filename' => $row['filename'],
			'downloads' => $row['downloads'],
			'subject' => Lang::censorText($row['subject']),
			'posted' => $row['poster_time'],
			'msg' => $row['id_msg'],
			'topic' => $row['id_topic'],
			'board' => $row['id_board'],
			'board_name' => $row['name'],
			'approved' => $row['approved'],
			'awaiting_approval' => (empty($row['approved']) ? ' <em>(' . Lang::$txt['awaiting_approval'] . ')</em>' : ''),
		);

	Db::$db->free_result($request);

	return $attachments;
}

/**
 * Gets the total number of attachments for a member
 *
 * @param array $boardsAllowed An array of the IDs of the boards they can see
 * @param int $memID The ID of the member
 * @return int The number of attachments
 */
function list_getNumAttachments($boardsAllowed, $memID)
{
	// Get the total number of attachments they have posted.
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})' . (!Config::$modSettings['postmod_active'] || User::$me->is_owner || allowedTo('approve_posts') ? '' : '
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)') . '
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!Config::$modSettings['postmod_active'] || User::$me->is_owner || allowedTo('approve_posts') ? '' : '
			AND m.approved = {int:is_approved}
			AND t.approved = {int:is_approved}'),
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => Board::$info->id,
		)
	);
	list ($attachCount) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return $attachCount;
}

/**
 * Show all the unwatched topics.
 *
 * @param int $memID The ID of the member
 */
function showUnwatched($memID)
{
	// Only the owner can see the list (if the function is enabled of course)
	if (User::$me->id != $memID)
		return;

	// And here they are: the topics you don't like
	$listOptions = array(
		'id' => 'unwatched_topics',
		'width' => '100%',
		'items_per_page' => (empty(Config::$modSettings['disableCustomPerPage']) && !empty(Theme::$current->options['topics_per_page'])) ? Theme::$current->options['topics_per_page'] : Config::$modSettings['defaultMaxTopics'],
		'no_items_label' => Lang::$txt['unwatched_topics_none'],
		'base_href' => Config::$scripturl . '?action=profile;area=showposts;sa=unwatchedtopics;u=' . $memID,
		'default_sort_col' => 'started_on',
		'get_items' => array(
			'function' => 'list_getUnwatched',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getNumUnwatched',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => Lang::$txt['subject'],
					'class' => 'lefttext',
					'style' => 'width: 30%;',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . Config::$scripturl . '?topic=%1$d.0">%2$s</a>',
						'params' => array(
							'id_topic' => false,
							'subject' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'm.subject',
					'reverse' => 'm.subject DESC',
				),
			),
			'started_by' => array(
				'header' => array(
					'value' => Lang::$txt['started_by'],
					'style' => 'width: 15%;',
				),
				'data' => array(
					'db' => 'started_by',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'started_on' => array(
				'header' => array(
					'value' => Lang::$txt['on'],
					'class' => 'lefttext',
					'style' => 'width: 20%;',
				),
				'data' => array(
					'db' => 'started_on',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'm.poster_time',
					'reverse' => 'm.poster_time DESC',
				),
			),
			'last_post_by' => array(
				'header' => array(
					'value' => Lang::$txt['last_post'],
					'style' => 'width: 15%;',
				),
				'data' => array(
					'db' => 'last_post_by',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'last_post_on' => array(
				'header' => array(
					'value' => Lang::$txt['on'],
					'class' => 'lefttext',
					'style' => 'width: 20%;',
				),
				'data' => array(
					'db' => 'last_post_on',
					'timeformat' => true,
				),
				'sort' => array(
					'default' => 'm.poster_time',
					'reverse' => 'm.poster_time DESC',
				),
			),
		),
	);

	// Create the request list.
	new ItemList($listOptions);

	Utils::$context['sub_template'] = 'show_list';
	Utils::$context['default_list'] = 'unwatched_topics';
}

/**
 * Gets information about unwatched (disregarded) topics. Callback for the list in show_unwatched
 *
 * @param int $start The item to start with (for pagination purposes)
 * @param int $items_per_page How many items to show on each page
 * @param string $sort A string indicating how to sort the results
 * @param int $memID The ID of the member
 * @return array An array of information about the unwatched topics
 */
function list_getUnwatched($start, $items_per_page, $sort, $memID)
{
	// Get the list of topics we can see
	$request = Db::$db->query('', '
		SELECT lt.id_topic
		FROM {db_prefix}log_topics as lt
			LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
			LEFT JOIN {db_prefix}messages as m ON (t.id_first_msg = m.id_msg)' . (in_array($sort, array('mem.real_name', 'mem.real_name DESC', 'mem.poster_time', 'mem.poster_time DESC')) ? '
			LEFT JOIN {db_prefix}members as mem ON (m.id_member = mem.id_member)' : '') . '
		WHERE lt.id_member = {int:current_member}
			AND unwatched = 1
			AND {query_see_message_board}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'current_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);

	$topics = array();
	while ($row = Db::$db->fetch_assoc($request))
		$topics[] = $row['id_topic'];

	Db::$db->free_result($request);

	// Any topics found?
	$topicsInfo = array();
	if (!empty($topics))
	{
		$request = Db::$db->query('', '
			SELECT mf.subject, mf.poster_time as started_on, COALESCE(memf.real_name, mf.poster_name) as started_by, ml.poster_time as last_post_on, COALESCE(meml.real_name, ml.poster_name) as last_post_by, t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
			WHERE t.id_topic IN ({array_int:topics})',
			array(
				'topics' => $topics,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$topicsInfo[] = $row;
		Db::$db->free_result($request);
	}

	return $topicsInfo;
}

/**
 * Count the number of topics in the unwatched list
 *
 * @param int $memID The ID of the member
 * @return int The number of unwatched topics
 */
function list_getNumUnwatched($memID)
{
	// Get the total number of attachments they have posted.
	$request = Db::$db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_topics as lt
		LEFT JOIN {db_prefix}topics as t ON (lt.id_topic = t.id_topic)
		WHERE lt.id_member = {int:current_member}
			AND lt.unwatched = 1
			AND {query_see_topic_board}',
		array(
			'current_member' => $memID,
		)
	);
	list ($unwatchedCount) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	return $unwatchedCount;
}

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
 * Shows which permissions a user has
 *
 * @param int $memID The ID of the member
 */
function showPermissions($memID)
{
	// Verify if the user has sufficient permissions.
	isAllowedTo('manage_permissions');

	Lang::load('ManagePermissions');
	Lang::load('Admin');
	Theme::loadTemplate('ManageMembers');

	// Load all the permission profiles.
	Permissions::loadPermissionProfiles();

	Utils::$context['member']['id'] = $memID;
	Utils::$context['member']['name'] = User::$loaded[$memID]->name;

	Utils::$context['page_title'] = Lang::$txt['showPermissions'];
	Board::$info->id = empty(Board::$info->id) ? 0 : (int) Board::$info->id;
	Utils::$context['board'] = Board::$info->id;

	// Determine which groups this user is in.
	$curGroups = User::$loaded[$memID]->groups;

	// Load a list of boards for the jump box - except the defaults.
	$request = Db::$db->query('order_by_board_order', '
		SELECT b.id_board, b.name, b.id_profile, b.member_groups, COALESCE(mods.id_member, modgs.id_group, 0) AS is_mod
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:current_groups}))
		WHERE {query_see_board}',
		array(
			'current_member' => $memID,
			'current_groups' => $curGroups,
		)
	);
	Utils::$context['boards'] = array();
	Utils::$context['no_access_boards'] = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		if ((count(array_intersect($curGroups, explode(',', $row['member_groups']))) === 0) && !$row['is_mod']
		&& (!empty(Config::$modSettings['board_manager_groups']) && count(array_intersect($curGroups, explode(',', Config::$modSettings['board_manager_groups']))) === 0))
			Utils::$context['no_access_boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'is_last' => false,
			);
		elseif ($row['id_profile'] != 1 || $row['is_mod'])
			Utils::$context['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'selected' => Board::$info->id == $row['id_board'],
				'profile' => $row['id_profile'],
				'profile_name' => Utils::$context['profiles'][$row['id_profile']]['name'],
			);
	}
	Db::$db->free_result($request);

	Board::sort(Utils::$context['boards']);

	if (!empty(Utils::$context['no_access_boards']))
		Utils::$context['no_access_boards'][count(Utils::$context['no_access_boards']) - 1]['is_last'] = true;

	Utils::$context['member']['permissions'] = array(
		'general' => array(),
		'board' => array()
	);

	// If you're an admin we know you can do everything, we might as well leave.
	Utils::$context['member']['has_all_permissions'] = in_array(1, $curGroups);
	if (Utils::$context['member']['has_all_permissions'])
		return;

	$denied = array();

	// Get all general permissions.
	$result = Db::$db->query('', '
		SELECT p.permission, p.add_deny, mg.group_name, p.id_group
		FROM {db_prefix}permissions AS p
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
		WHERE p.id_group IN ({array_int:group_list})
		ORDER BY p.add_deny DESC, p.permission, mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'group_list' => $curGroups,
			'newbie_group' => 4,
		)
	);
	while ($row = Db::$db->fetch_assoc($result))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset(Lang::$txt['permissionname_' . $row['permission']]))
			continue;

		if (empty($row['add_deny']))
			$denied[] = $row['permission'];

		// Permissions that end with _own or _any consist of two parts.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset(Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . Lang::$txt['permissionname_' . $row['permission']];
		else
			$name = Lang::$txt['permissionname_' . $row['permission']];

		// Add this permission if it doesn't exist yet.
		if (!isset(Utils::$context['member']['permissions']['general'][$row['permission']]))
			Utils::$context['member']['permissions']['general'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => true,
			);

		// Add the membergroup to either the denied or the allowed groups.
		Utils::$context['member']['permissions']['general'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['id_group'] == 0 ? Lang::$txt['membergroups_members'] : $row['group_name'];

		// Once denied is always denied.
		Utils::$context['member']['permissions']['general'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	Db::$db->free_result($result);

	$request = Db::$db->query('', '
		SELECT
			bp.add_deny, bp.permission, bp.id_group, mg.group_name' . (empty(Board::$info->id) ? '' : ',
			b.id_profile, CASE WHEN (mods.id_member IS NULL AND modgs.id_group IS NULL) THEN 0 ELSE 1 END AS is_moderator') . '
		FROM {db_prefix}board_permissions AS bp' . (empty(Board::$info->id) ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = {int:current_board})
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:group_list}))') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bp.id_group)
		WHERE bp.id_profile = {raw:current_profile}
			AND bp.id_group IN ({array_int:group_list}' . (empty(Board::$info->id) ? ')' : ', {int:moderator_group})
			AND (mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR bp.id_group != {int:moderator_group})'),
		array(
			'current_board' => Board::$info->id,
			'group_list' => $curGroups,
			'current_member' => $memID,
			'current_profile' => empty(Board::$info->id) ? '1' : 'b.id_profile',
			'moderator_group' => 3,
		)
	);

	while ($row = Db::$db->fetch_assoc($request))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset(Lang::$txt['permissionname_' . $row['permission']]))
			continue;

		// The name of the permission using the format 'permission name' - 'own/any topic/event/etc.'.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset(Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . Lang::$txt['permissionname_' . $row['permission']];
		else
			$name = Lang::$txt['permissionname_' . $row['permission']];

		// Create the structure for this permission.
		if (!isset(Utils::$context['member']['permissions']['board'][$row['permission']]))
			Utils::$context['member']['permissions']['board'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => empty(Board::$info->id),
			);

		Utils::$context['member']['permissions']['board'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][$row['id_group']] = $row['id_group'] == 0 ? Lang::$txt['membergroups_members'] : $row['group_name'];

		Utils::$context['member']['permissions']['board'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	Db::$db->free_result($request);
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