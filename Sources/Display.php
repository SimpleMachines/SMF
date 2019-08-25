<?php

/**
 * This is perhaps the most important and probably most accessed file in all
 * of SMF.  This file controls topic, message, and attachment display.
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
 * The central part of the board - topic display.
 * This function loads the posts in a topic up so they can be displayed.
 * It uses the main sub template of the Display template.
 * It requires a topic, and can go to the previous or next topic from it.
 * It jumps to the correct post depending on a number/time/IS_MSG passed.
 * It depends on the messages_per_page, defaultMaxMessages and enableAllMessages settings.
 * It is accessed by ?topic=id_topic.START.
 *
 * @return void
 */
function Display()
{
	global $scripturl, $txt, $modSettings, $context, $settings;
	global $options, $sourcedir, $user_info, $board_info, $topic, $board;
	global $messages_request, $language, $smcFunc;

	// What are you gonna display if these are empty?!
	if (empty($topic))
		fatal_lang_error('no_board', false);

	// Load the proper template.
	loadTemplate('Display');

	// Not only does a prefetch make things slower for the server, but it makes it impossible to know if they read it.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		send_http_status(403, 'Prefetch Forbidden');
		die;
	}

	// How much are we sticking on each page?
	$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	// Let's do some work on what to search index.
	if (count($_GET) > 2)
		foreach ($_GET as $k => $v)
		{
			if (!in_array($k, array('topic', 'board', 'start', session_name())))
				$context['robot_no_index'] = true;
		}

	if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
		$context['robot_no_index'] = true;

	// Find the previous or next topic.  Make a fuss if there are no more.
	if (isset($_REQUEST['prev_next']) && ($_REQUEST['prev_next'] == 'prev' || $_REQUEST['prev_next'] == 'next'))
	{
		// No use in calculating the next topic if there's only one.
		if ($board_info['num_topics'] > 1)
		{
			// Just prepare some variables that are used in the query.
			$gt_lt = $_REQUEST['prev_next'] == 'prev' ? '>' : '<';
			$order = $_REQUEST['prev_next'] == 'prev' ? '' : ' DESC';

			$request = $smcFunc['db_query']('', '
				SELECT t2.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}topics AS t2 ON (
					(t2.id_last_msg ' . $gt_lt . ' t.id_last_msg AND t2.is_sticky ' . $gt_lt . '= t.is_sticky) OR t2.is_sticky ' . $gt_lt . ' t.is_sticky)
				WHERE t.id_topic = {int:current_topic}
					AND t2.id_board = {int:current_board}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND (t2.approved = {int:is_approved} OR (t2.id_member_started != {int:id_member_started} AND t2.id_member_started = {int:current_member}))') . '
				ORDER BY t2.is_sticky' . $order . ', t2.id_last_msg' . $order . '
				LIMIT 1',
				array(
					'current_board' => $board,
					'current_member' => $user_info['id'],
					'current_topic' => $topic,
					'is_approved' => 1,
					'id_member_started' => 0,
				)
			);

			// No more left.
			if ($smcFunc['db_num_rows']($request) == 0)
			{
				$smcFunc['db_free_result']($request);

				// Roll over - if we're going prev, get the last - otherwise the first.
				$request = $smcFunc['db_query']('', '
					SELECT id_topic
					FROM {db_prefix}topics
					WHERE id_board = {int:current_board}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
						AND (approved = {int:is_approved} OR (id_member_started != {int:id_member_started} AND id_member_started = {int:current_member}))') . '
					ORDER BY is_sticky' . $order . ', id_last_msg' . $order . '
					LIMIT 1',
					array(
						'current_board' => $board,
						'current_member' => $user_info['id'],
						'is_approved' => 1,
						'id_member_started' => 0,
					)
				);
			}

			// Now you can be sure $topic is the id_topic to view.
			list ($topic) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$context['current_topic'] = $topic;
		}

		// Go to the newest message on this topic.
		$_REQUEST['start'] = 'new';
	}

	// Add 1 to the number of views of this topic (except for robots).
	if (!$user_info['possibly_robot'] && (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != $topic))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET num_views = num_views + 1
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
			)
		);

		$_SESSION['last_read_topic'] = $topic;
	}

	$topic_parameters = array(
		'current_member' => $user_info['id'],
		'current_topic' => $topic,
		'current_board' => $board,
	);
	$topic_selects = array();
	$topic_tables = array();
	$context['topicinfo'] = array();
	call_integration_hook('integrate_display_topic', array(&$topic_selects, &$topic_tables, &$topic_parameters));

	// @todo Why isn't this cached?
	// @todo if we get id_board in this query and cache it, we can save a query on posting
	// Get all the important topic info.
	$request = $smcFunc['db_query']('', '
		SELECT
			t.num_replies, t.num_views, t.locked, ms.subject, t.is_sticky, t.id_poll,
			t.id_member_started, t.id_first_msg, t.id_last_msg, t.approved, t.unapproved_posts, t.id_redirect_topic,
			COALESCE(mem.real_name, ms.poster_name) AS topic_started_name, ms.poster_time AS topic_started_time,
			' . ($user_info['is_guest'] ? 't.id_last_msg + 1' : 'COALESCE(lt.id_msg, lmr.id_msg, -1) + 1') . ' AS new_from
			' . (!empty($board_info['recycle']) ? ', id_previous_board, id_previous_topic' : '') . '
			' . (!empty($topic_selects) ? (', ' . implode(', ', $topic_selects)) : '') . '
			' . (!$user_info['is_guest'] ? ', COALESCE(lt.unwatched, 0) as unwatched' : '') . '
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem on (mem.id_member = t.id_member_started)' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})') . '
			' . (!empty($topic_tables) ? implode("\n\t", $topic_tables) : '') . '
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		$topic_parameters
	);

	if ($smcFunc['db_num_rows']($request) == 0)
		fatal_lang_error('not_a_topic', false, 404);
	$context['topicinfo'] = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Is this a moved or merged topic that we are redirecting to?
	if (!empty($context['topicinfo']['id_redirect_topic']))
	{
		// Mark this as read...
		if (!$user_info['is_guest'] && $context['topicinfo']['new_from'] != $context['topicinfo']['id_first_msg'])
		{
			// Mark this as read first
			$smcFunc['db_insert']($context['topicinfo']['new_from'] == 0 ? 'ignore' : 'replace',
				'{db_prefix}log_topics',
				array(
					'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
				),
				array(
					$user_info['id'], $topic, $context['topicinfo']['id_first_msg'], $context['topicinfo']['unwatched'],
				),
				array('id_member', 'id_topic')
			);
		}
		redirectexit('topic=' . $context['topicinfo']['id_redirect_topic'] . '.0', false, true);
	}

	$can_approve_posts = allowedTo('approve_posts');

	$context['real_num_replies'] = $context['num_replies'] = $context['topicinfo']['num_replies'];
	$context['topic_started_time'] = timeformat($context['topicinfo']['topic_started_time']);
	$context['topic_started_timestamp'] = $context['topicinfo']['topic_started_time'];
	$context['topic_poster_name'] = $context['topicinfo']['topic_started_name'];
	$context['topic_first_message'] = $context['topicinfo']['id_first_msg'];
	$context['topic_last_message'] = $context['topicinfo']['id_last_msg'];
	$context['topic_unwatched'] = isset($context['topicinfo']['unwatched']) ? $context['topicinfo']['unwatched'] : 0;

	// Add up unapproved replies to get real number of replies...
	if ($modSettings['postmod_active'] && $can_approve_posts)
		$context['real_num_replies'] += $context['topicinfo']['unapproved_posts'] - ($context['topicinfo']['approved'] ? 0 : 1);

	// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
	if ($modSettings['postmod_active'] && $context['topicinfo']['unapproved_posts'] && !$user_info['is_guest'] && !$can_approve_posts)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(id_member) AS my_unapproved_posts
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_member = {int:current_member}
				AND approved = 0',
			array(
				'current_topic' => $topic,
				'current_member' => $user_info['id'],
			)
		);
		list ($myUnapprovedPosts) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['total_visible_posts'] = $context['num_replies'] + $myUnapprovedPosts + ($context['topicinfo']['approved'] ? 1 : 0);
	}
	elseif ($user_info['is_guest'])
		$context['total_visible_posts'] = $context['num_replies'] + ($context['topicinfo']['approved'] ? 1 : 0);
	else
		$context['total_visible_posts'] = $context['num_replies'] + $context['topicinfo']['unapproved_posts'] + ($context['topicinfo']['approved'] ? 1 : 0);

	// The start isn't a number; it's information about what to do, where to go.
	if (!is_numeric($_REQUEST['start']))
	{
		// Redirect to the page and post with new messages, originally by Omar Bazavilvazo.
		if ($_REQUEST['start'] == 'new')
		{
			// Guests automatically go to the last post.
			if ($user_info['is_guest'])
			{
				$context['start_from'] = $context['total_visible_posts'] - 1;
				$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : 0;
			}
			else
			{
				// Find the earliest unread message in the topic. (the use of topics here is just for both tables.)
				$request = $smcFunc['db_query']('', '
					SELECT COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})
					WHERE t.id_topic = {int:current_topic}
					LIMIT 1',
					array(
						'current_board' => $board,
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
					)
				);
				list ($new_from) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// Fall through to the next if statement.
				$_REQUEST['start'] = 'msg' . $new_from;
			}
		}

		// Start from a certain time index, not a message.
		if (substr($_REQUEST['start'], 0, 4) == 'from')
		{
			$timestamp = (int) substr($_REQUEST['start'], 4);
			if ($timestamp === 0)
				$_REQUEST['start'] = 0;
			else
			{
				// Find the number of messages posted before said time...
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(*)
					FROM {db_prefix}messages
					WHERE poster_time < {int:timestamp}
						AND id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && $context['topicinfo']['unapproved_posts'] && !allowedTo('approve_posts') ? '
						AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
					array(
						'current_topic' => $topic,
						'current_member' => $user_info['id'],
						'is_approved' => 1,
						'timestamp' => $timestamp,
					)
				);
				list ($context['start_from']) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				// Handle view_newest_first options, and get the correct start value.
				$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : $context['total_visible_posts'] - $context['start_from'] - 1;
			}
		}

		// Link to a message...
		elseif (substr($_REQUEST['start'], 0, 3) == 'msg')
		{
			$virtual_msg = (int) substr($_REQUEST['start'], 3);
			if (!$context['topicinfo']['unapproved_posts'] && $virtual_msg >= $context['topicinfo']['id_last_msg'])
				$context['start_from'] = $context['total_visible_posts'] - 1;
			elseif (!$context['topicinfo']['unapproved_posts'] && $virtual_msg <= $context['topicinfo']['id_first_msg'])
				$context['start_from'] = 0;
			else
			{
				// Find the start value for that message......
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(*)
					FROM {db_prefix}messages
					WHERE id_msg < {int:virtual_msg}
						AND id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && $context['topicinfo']['unapproved_posts'] && !allowedTo('approve_posts') ? '
						AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
					array(
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
						'virtual_msg' => $virtual_msg,
						'is_approved' => 1,
						'no_member' => 0,
					)
				);
				list ($context['start_from']) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
			}

			// We need to reverse the start as well in this case.
			$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : $context['total_visible_posts'] - $context['start_from'] - 1;
		}
	}

	// Create a previous next string if the selected theme has it as a selected option.
	$context['previous_next'] = $modSettings['enablePreviousNext'] ? '<a href="' . $scripturl . '?topic=' . $topic . '.0;prev_next=prev#new">' . $txt['previous_next_back'] . '</a> - <a href="' . $scripturl . '?topic=' . $topic . '.0;prev_next=next#new">' . $txt['previous_next_forward'] . '</a>' : '';

	// Check if spellchecking is both enabled and actually working. (for quick reply.)
	$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && (function_exists('pspell_new') || (function_exists('enchant_broker_init') && ($txt['lang_character_set'] == 'UTF-8' || function_exists('iconv'))));

	// Do we need to show the visual verification image?
	$context['require_verification'] = !$user_info['is_mod'] && !$user_info['is_admin'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1));
	if ($context['require_verification'])
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// Are we showing signatures - or disabled fields?
	$context['signature_enabled'] = substr($modSettings['signature_settings'], 0, 1) == 1;
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	// Prevent signature images from going outside the box.
	if ($context['signature_enabled'])
	{
		list ($sig_limits, $sig_bbc) = explode(':', $modSettings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);

		if (!empty($sig_limits[5]) || !empty($sig_limits[6]))
			addInlineCss('
	.signature img { ' . (!empty($sig_limits[5]) ? 'max-width: ' . (int) $sig_limits[5] . 'px; ' : '') . (!empty($sig_limits[6]) ? 'max-height: ' . (int) $sig_limits[6] . 'px; ' : '') . '}');
	}

	// Censor the title...
	censorText($context['topicinfo']['subject']);
	$context['page_title'] = $context['topicinfo']['subject'];

	// Default this topic to not marked for notifications... of course...
	$context['is_marked_notify'] = false;

	// Did we report a post to a moderator just now?
	$context['report_sent'] = isset($_GET['reportsent']);

	// Let's get nosey, who is viewing this topic?
	if (!empty($settings['display_who_viewing']))
	{
		// Start out with no one at all viewing it.
		$context['view_members'] = array();
		$context['view_members_list'] = array();
		$context['view_num_hidden'] = 0;

		// Search for members who have this topic set in their GET data.
		$request = $smcFunc['db_query']('', '
			SELECT
				lo.id_member, lo.log_time, mem.real_name, mem.member_name, mem.show_online,
				mg.online_color, mg.id_group, mg.group_name
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lo.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_id_group} THEN mem.id_post_group ELSE mem.id_group END)
			WHERE INSTR(lo.url, {string:in_url_string}) > 0 OR lo.session = {string:session}',
			array(
				'reg_id_group' => 0,
				'in_url_string' => '"topic":' . $topic,
				'session' => $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id(),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['id_member']))
				continue;

			if (!empty($row['online_color']))
				$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" style="color: ' . $row['online_color'] . ';">' . $row['real_name'] . '</a>';
			else
				$link = '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';

			$is_buddy = in_array($row['id_member'], $user_info['buddies']);
			if ($is_buddy)
				$link = '<strong>' . $link . '</strong>';

			// Add them both to the list and to the more detailed list.
			if (!empty($row['show_online']) || allowedTo('moderate_forum'))
				$context['view_members_list'][$row['log_time'] . $row['member_name']] = empty($row['show_online']) ? '<em>' . $link . '</em>' : $link;
			$context['view_members'][$row['log_time'] . $row['member_name']] = array(
				'id' => $row['id_member'],
				'username' => $row['member_name'],
				'name' => $row['real_name'],
				'group' => $row['id_group'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => $link,
				'is_buddy' => $is_buddy,
				'hidden' => empty($row['show_online']),
			);

			if (empty($row['show_online']))
				$context['view_num_hidden']++;
		}

		// The number of guests is equal to the rows minus the ones we actually used ;).
		$context['view_num_guests'] = $smcFunc['db_num_rows']($request) - count($context['view_members']);
		$smcFunc['db_free_result']($request);

		// Sort the list.
		krsort($context['view_members']);
		krsort($context['view_members_list']);
	}

	// If all is set, but not allowed... just unset it.
	$can_show_all = !empty($modSettings['enableAllMessages']) && $context['total_visible_posts'] > $context['messages_per_page'] && $context['total_visible_posts'] < $modSettings['enableAllMessages'];
	if (isset($_REQUEST['all']) && !$can_show_all)
		unset($_REQUEST['all']);
	// Otherwise, it must be allowed... so pretend start was -1.
	elseif (isset($_REQUEST['all']))
		$_REQUEST['start'] = -1;

	// Construct the page index, allowing for the .START method...
	$context['page_index'] = constructPageIndex($scripturl . '?topic=' . $topic . '.%1$d', $_REQUEST['start'], $context['total_visible_posts'], $context['messages_per_page'], true);
	$context['start'] = $_REQUEST['start'];

	// This is information about which page is current, and which page we're on - in case you don't like the constructed page index. (again, wireles..)
	$context['page_info'] = array(
		'current_page' => $_REQUEST['start'] / $context['messages_per_page'] + 1,
		'num_pages' => floor(($context['total_visible_posts'] - 1) / $context['messages_per_page']) + 1,
	);

	// Figure out all the link to the next/prev/first/last/etc.
	if (!($can_show_all && isset($_REQUEST['all'])))
	{
		$context['links'] = array(
			'first' => $_REQUEST['start'] >= $context['messages_per_page'] ? $scripturl . '?topic=' . $topic . '.0' : '',
			'prev' => $_REQUEST['start'] >= $context['messages_per_page'] ? $scripturl . '?topic=' . $topic . '.' . ($_REQUEST['start'] - $context['messages_per_page']) : '',
			'next' => $_REQUEST['start'] + $context['messages_per_page'] < $context['total_visible_posts'] ? $scripturl . '?topic=' . $topic . '.' . ($_REQUEST['start'] + $context['messages_per_page']) : '',
			'last' => $_REQUEST['start'] + $context['messages_per_page'] < $context['total_visible_posts'] ? $scripturl . '?topic=' . $topic . '.' . (floor($context['total_visible_posts'] / $context['messages_per_page']) * $context['messages_per_page']) : '',
			'up' => $scripturl . '?board=' . $board . '.0'
		);
	}

	// If they are viewing all the posts, show all the posts, otherwise limit the number.
	if ($can_show_all)
	{
		if (isset($_REQUEST['all']))
		{
			// No limit! (actually, there is a limit, but...)
			$context['messages_per_page'] = -1;
			$context['page_index'] .= empty($modSettings['compactTopicPagesEnable']) ? '<strong>' . $txt['all'] . '</strong> ' : '[<strong>' . $txt['all'] . '</strong>] ';

			// Set start back to 0...
			$_REQUEST['start'] = 0;
		}
		// They aren't using it, but the *option* is there, at least.
		else
			$context['page_index'] .= '&nbsp;<a href="' . $scripturl . '?topic=' . $topic . '.0;all">' . $txt['all'] . '</a> ';
	}

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?topic=' . $topic . '.0',
		'name' => $context['topicinfo']['subject'],
	);

	// Build a list of this board's moderators.
	$context['moderators'] = &$board_info['moderators'];
	$context['moderator_groups'] = &$board_info['moderator_groups'];
	$context['link_moderators'] = array();
	if (!empty($board_info['moderators']))
	{
		// Add a link for each moderator...
		foreach ($board_info['moderators'] as $mod)
			$context['link_moderators'][] = '<a href="' . $scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';
	}
	if (!empty($board_info['moderator_groups']))
	{
		// Add a link for each moderator group as well...
		foreach ($board_info['moderator_groups'] as $mod_group)
			$context['link_moderators'][] = '<a href="' . $scripturl . '?action=groups;sa=viewmemberes;group=' . $mod_group['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod_group['name'] . '</a>';
	}

	if (!empty($context['link_moderators']))
	{
		// And show it after the board's name.
		$context['linktree'][count($context['linktree']) - 2]['extra_after'] = '<span class="board_moderators">(' . (count($context['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators']) . ': ' . implode(', ', $context['link_moderators']) . ')</span>';
	}

	// Information about the current topic...
	$context['is_locked'] = $context['topicinfo']['locked'];
	$context['is_sticky'] = $context['topicinfo']['is_sticky'];
	$context['is_approved'] = $context['topicinfo']['approved'];
	$context['is_poll'] = $context['topicinfo']['id_poll'] > 0 && $modSettings['pollMode'] == '1' && allowedTo('poll_view');

	// Did this user start the topic or not?
	$context['user']['started'] = $user_info['id'] == $context['topicinfo']['id_member_started'] && !$user_info['is_guest'];
	$context['topic_starter_id'] = $context['topicinfo']['id_member_started'];

	// Set the topic's information for the template.
	$context['subject'] = $context['topicinfo']['subject'];
	$context['num_views'] = comma_format($context['topicinfo']['num_views']);
	$context['num_views_text'] = $context['num_views'] == 1 ? $txt['read_one_time'] : sprintf($txt['read_many_times'], $context['num_views']);
	$context['mark_unread_time'] = !empty($virtual_msg) ? $virtual_msg : $context['topicinfo']['new_from'];

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?topic=' . $topic . '.' . ($can_show_all ? '0;all' : $context['start']);

	// For quick reply we need a response prefix in the default forum language.
	if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix', 600)))
	{
		if ($language === $user_info['language'])
			$context['response_prefix'] = $txt['response_prefix'];
		else
		{
			loadLanguage('index', $language, false);
			$context['response_prefix'] = $txt['response_prefix'];
			loadLanguage('index');
		}
		cache_put_data('response_prefix', $context['response_prefix'], 600);
	}

	// If we want to show event information in the topic, prepare the data.
	if (allowedTo('calendar_view') && !empty($modSettings['cal_showInTopic']) && !empty($modSettings['cal_enabled']))
	{
		require_once($sourcedir . '/Subs-Calendar.php');

		// Any calendar information for this topic?
		$request = $smcFunc['db_query']('', '
			SELECT cal.id_event, cal.start_date, cal.end_date, cal.title, cal.id_member, mem.real_name, cal.start_time, cal.end_time, cal.timezone, cal.location
			FROM {db_prefix}calendar AS cal
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = cal.id_member)
			WHERE cal.id_topic = {int:current_topic}
			ORDER BY start_date',
			array(
				'current_topic' => $topic,
			)
		);
		$context['linked_calendar_events'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Get the various time and date properties for this event
			list($start, $end, $allday, $span, $tz, $tz_abbrev) = buildEventDatetimes($row);

			// Sanity check
			if (!empty($start['error_count']) || !empty($start['warning_count']) || !empty($end['error_count']) || !empty($end['warning_count']))
				continue;

			$linked_calendar_event = array(
				'id' => $row['id_event'],
				'title' => $row['title'],
				'can_edit' => allowedTo('calendar_edit_any') || ($row['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
				'modify_href' => $scripturl . '?action=post;msg=' . $context['topicinfo']['id_first_msg'] . ';topic=' . $topic . '.0;calendar;eventid=' . $row['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'can_export' => allowedTo('calendar_edit_any') || ($row['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
				'export_href' => $scripturl . '?action=calendar;sa=ical;eventid=' . $row['id_event'] . ';' . $context['session_var'] . '=' . $context['session_id'],
				'year' => $start['year'],
				'month' => $start['month'],
				'day' => $start['day'],
				'hour' => !$allday ? $start['hour'] : null,
				'minute' => !$allday ? $start['minute'] : null,
				'second' => !$allday ? $start['second'] : null,
				'start_date' => $row['start_date'],
				'start_date_local' => $start['date_local'],
				'start_date_orig' => $start['date_orig'],
				'start_time' => !$allday ? $row['start_time'] : null,
				'start_time_local' => !$allday ? $start['time_local'] : null,
				'start_time_orig' => !$allday ? $start['time_orig'] : null,
				'start_timestamp' => $start['timestamp'],
				'start_iso_gmdate' => $start['iso_gmdate'],
				'end_year' => $end['year'],
				'end_month' => $end['month'],
				'end_day' => $end['day'],
				'end_hour' => !$allday ? $end['hour'] : null,
				'end_minute' => !$allday ? $end['minute'] : null,
				'end_second' => !$allday ? $end['second'] : null,
				'end_date' => $row['end_date'],
				'end_date_local' => $end['date_local'],
				'end_date_orig' => $end['date_orig'],
				'end_time' => !$allday ? $row['end_time'] : null,
				'end_time_local' => !$allday ? $end['time_local'] : null,
				'end_time_orig' => !$allday ? $end['time_orig'] : null,
				'end_timestamp' => $end['timestamp'],
				'end_iso_gmdate' => $end['iso_gmdate'],
				'allday' => $allday,
				'tz' => !$allday ? $tz : null,
				'tz_abbrev' => !$allday ? $tz_abbrev : null,
				'span' => $span,
				'location' => $row['location'],
				'is_last' => false
			);

			$context['linked_calendar_events'][] = $linked_calendar_event;
		}
		$smcFunc['db_free_result']($request);

		if (!empty($context['linked_calendar_events']))
			$context['linked_calendar_events'][count($context['linked_calendar_events']) - 1]['is_last'] = true;
	}

	// Create the poll info if it exists.
	if ($context['is_poll'])
	{
		// Get the question and if it's locked.
		$request = $smcFunc['db_query']('', '
			SELECT
				p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
				p.guest_vote, p.id_member, COALESCE(mem.real_name, p.poster_name) AS poster_name, p.num_guest_voters, p.reset_poll
			FROM {db_prefix}polls AS p
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = p.id_member)
			WHERE p.id_poll = {int:id_poll}
			LIMIT 1',
			array(
				'id_poll' => $context['topicinfo']['id_poll'],
			)
		);
		$pollinfo = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
	}

	// Create the poll info if it exists and is valid.
	if ($context['is_poll'] && empty($pollinfo))
		$context['is_poll'] = false;
	elseif ($context['is_poll'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(DISTINCT id_member) AS total
			FROM {db_prefix}log_polls
			WHERE id_poll = {int:id_poll}
				AND id_member != {int:not_guest}',
			array(
				'id_poll' => $context['topicinfo']['id_poll'],
				'not_guest' => 0,
			)
		);
		list ($pollinfo['total']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Total voters needs to include guest voters
		$pollinfo['total'] += $pollinfo['num_guest_voters'];

		// Get all the options, and calculate the total votes.
		$request = $smcFunc['db_query']('', '
			SELECT pc.id_choice, pc.label, pc.votes, COALESCE(lp.id_choice, -1) AS voted_this
			FROM {db_prefix}poll_choices AS pc
				LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_choice = pc.id_choice AND lp.id_poll = {int:id_poll} AND lp.id_member = {int:current_member} AND lp.id_member != {int:not_guest})
			WHERE pc.id_poll = {int:id_poll}',
			array(
				'current_member' => $user_info['id'],
				'id_poll' => $context['topicinfo']['id_poll'],
				'not_guest' => 0,
			)
		);
		$pollOptions = array();
		$realtotal = 0;
		$pollinfo['has_voted'] = false;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			censorText($row['label']);
			$pollOptions[$row['id_choice']] = $row;
			$realtotal += $row['votes'];
			$pollinfo['has_voted'] |= $row['voted_this'] != -1;
		}
		$smcFunc['db_free_result']($request);

		// Got we multi choice?
		if ($pollinfo['max_votes'] > 1)
			$realtotal = $pollinfo['total'];

		// If this is a guest we need to do our best to work out if they have voted, and what they voted for.
		if ($user_info['is_guest'] && $pollinfo['guest_vote'] && allowedTo('poll_vote'))
		{
			if (!empty($_COOKIE['guest_poll_vote']) && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote']) && strpos($_COOKIE['guest_poll_vote'], ';' . $context['topicinfo']['id_poll'] . ',') !== false)
			{
				// ;id,timestamp,[vote,vote...]; etc
				$guestinfo = explode(';', $_COOKIE['guest_poll_vote']);
				// Find the poll we're after.
				foreach ($guestinfo as $i => $guestvoted)
				{
					$guestvoted = explode(',', $guestvoted);
					if ($guestvoted[0] == $context['topicinfo']['id_poll'])
						break;
				}
				// Has the poll been reset since guest voted?
				if ($pollinfo['reset_poll'] > $guestvoted[1])
				{
					// Remove the poll info from the cookie to allow guest to vote again
					unset($guestinfo[$i]);
					if (!empty($guestinfo))
						$_COOKIE['guest_poll_vote'] = ';' . implode(';', $guestinfo);
					else
						unset($_COOKIE['guest_poll_vote']);
				}
				else
				{
					// What did they vote for?
					unset($guestvoted[0], $guestvoted[1]);
					foreach ($pollOptions as $choice => $details)
					{
						$pollOptions[$choice]['voted_this'] = in_array($choice, $guestvoted) ? 1 : -1;
						$pollinfo['has_voted'] |= $pollOptions[$choice]['voted_this'] != -1;
					}
					unset($choice, $details, $guestvoted);
				}
				unset($guestinfo, $guestvoted, $i);
			}
		}

		// Set up the basic poll information.
		$context['poll'] = array(
			'id' => $context['topicinfo']['id_poll'],
			'image' => 'normal_' . (empty($pollinfo['voting_locked']) ? 'poll' : 'locked_poll'),
			'question' => parse_bbc($pollinfo['question']),
			'total_votes' => $pollinfo['total'],
			'change_vote' => !empty($pollinfo['change_vote']),
			'is_locked' => !empty($pollinfo['voting_locked']),
			'options' => array(),
			'lock' => allowedTo('poll_lock_any') || ($context['user']['started'] && allowedTo('poll_lock_own')),
			'edit' => allowedTo('poll_edit_any') || ($context['user']['started'] && allowedTo('poll_edit_own')),
			'remove' => allowedTo('poll_remove_any') || ($context['user']['started'] && allowedTo('poll_remove_own')),
			'allowed_warning' => $pollinfo['max_votes'] > 1 ? sprintf($txt['poll_options_limit'], min(count($pollOptions), $pollinfo['max_votes'])) : '',
			'is_expired' => !empty($pollinfo['expire_time']) && $pollinfo['expire_time'] < time(),
			'expire_time' => !empty($pollinfo['expire_time']) ? timeformat($pollinfo['expire_time']) : 0,
			'has_voted' => !empty($pollinfo['has_voted']),
			'starter' => array(
				'id' => $pollinfo['id_member'],
				'name' => $row['poster_name'],
				'href' => $pollinfo['id_member'] == 0 ? '' : $scripturl . '?action=profile;u=' . $pollinfo['id_member'],
				'link' => $pollinfo['id_member'] == 0 ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $pollinfo['id_member'] . '">' . $row['poster_name'] . '</a>'
			)
		);

		// Make the lock, edit and remove permissions defined above more directly accessible.
		$context['allow_lock_poll'] = $context['poll']['lock'];
		$context['allow_edit_poll'] = $context['poll']['edit'];
		$context['can_remove_poll'] = $context['poll']['remove'];

		// You're allowed to vote if:
		// 1. the poll did not expire, and
		// 2. you're either not a guest OR guest voting is enabled... and
		// 3. you're not trying to view the results, and
		// 4. the poll is not locked, and
		// 5. you have the proper permissions, and
		// 6. you haven't already voted before.
		$context['allow_vote'] = !$context['poll']['is_expired'] && (!$user_info['is_guest'] || ($pollinfo['guest_vote'] && allowedTo('poll_vote'))) && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && !$context['poll']['has_voted'];

		// You're allowed to view the results if:
		// 1. you're just a super-nice-guy, or
		// 2. anyone can see them (hide_results == 0), or
		// 3. you can see them after you voted (hide_results == 1), or
		// 4. you've waited long enough for the poll to expire. (whether hide_results is 1 or 2.)
		$context['allow_results_view'] = allowedTo('moderate_board') || $pollinfo['hide_results'] == 0 || ($pollinfo['hide_results'] == 1 && $context['poll']['has_voted']) || $context['poll']['is_expired'];

		// Show the results if:
		// 1. You're allowed to see them (see above), and
		// 2. $_REQUEST['viewresults'] or $_REQUEST['viewResults'] is set
		$context['poll']['show_results'] = $context['allow_results_view'] && (isset($_REQUEST['viewresults']) || isset($_REQUEST['viewResults']));

		// Show the button if:
		// 1. You can vote in the poll (see above), and
		// 2. Results are visible to everyone (hidden = 0), and
		// 3. You aren't already viewing the results
		$context['show_view_results_button'] = $context['allow_vote'] && $context['allow_results_view'] && !$context['poll']['show_results'];

		// You're allowed to change your vote if:
		// 1. the poll did not expire, and
		// 2. you're not a guest... and
		// 3. the poll is not locked, and
		// 4. you have the proper permissions, and
		// 5. you have already voted, and
		// 6. the poll creator has said you can!
		$context['allow_change_vote'] = !$context['poll']['is_expired'] && !$user_info['is_guest'] && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && $context['poll']['has_voted'] && $context['poll']['change_vote'];

		// You're allowed to return to voting options if:
		// 1. you are (still) allowed to vote.
		// 2. you are currently seeing the results.
		$context['allow_return_vote'] = $context['allow_vote'] && $context['poll']['show_results'];

		// Calculate the percentages and bar lengths...
		$divisor = $realtotal == 0 ? 1 : $realtotal;

		// Determine if a decimal point is needed in order for the options to add to 100%.
		$precision = $realtotal == 100 ? 0 : 1;

		// Now look through each option, and...
		foreach ($pollOptions as $i => $option)
		{
			// First calculate the percentage, and then the width of the bar...
			$bar = round(($option['votes'] * 100) / $divisor, $precision);
			$barWide = $bar == 0 ? 1 : floor(($bar * 8) / 3);

			// Now add it to the poll's contextual theme data.
			$context['poll']['options'][$i] = array(
				'id' => 'options-' . $i,
				'percent' => $bar,
				'votes' => $option['votes'],
				'voted_this' => $option['voted_this'] != -1,
				'bar_ndt' => $bar > 0 ? '<div class="bar" style="width: ' . $bar . '%;"></div>' : '',
				'bar_width' => $barWide,
				'option' => parse_bbc($option['label']),
				'vote_button' => '<input type="' . ($pollinfo['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" id="options-' . $i . '" value="' . $i . '">'
			);
		}

		// Build the poll moderation button array.
		$context['poll_buttons'] = array();

		if ($context['allow_return_vote'])
			$context['poll_buttons']['vote'] = array('text' => 'poll_return_vote', 'image' => 'poll_options.png', 'url' => $scripturl . '?topic=' . $context['current_topic'] . '.' . $context['start']);

		if ($context['show_view_results_button'])
			$context['poll_buttons']['results'] = array('text' => 'poll_results', 'image' => 'poll_results.png', 'url' => $scripturl . '?topic=' . $context['current_topic'] . '.' . $context['start'] . ';viewresults');

		if ($context['allow_change_vote'])
			$context['poll_buttons']['change_vote'] = array('text' => 'poll_change_vote', 'image' => 'poll_change_vote.png', 'url' => $scripturl . '?action=vote;topic=' . $context['current_topic'] . '.' . $context['start'] . ';poll=' . $context['poll']['id'] . ';' . $context['session_var'] . '=' . $context['session_id']);

		if ($context['allow_lock_poll'])
			$context['poll_buttons']['lock'] = array('text' => (!$context['poll']['is_locked'] ? 'poll_lock' : 'poll_unlock'), 'image' => 'poll_lock.png', 'url' => $scripturl . '?action=lockvoting;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']);

		if ($context['allow_edit_poll'])
			$context['poll_buttons']['edit'] = array('text' => 'poll_edit', 'image' => 'poll_edit.png', 'url' => $scripturl . '?action=editpoll;topic=' . $context['current_topic'] . '.' . $context['start']);

		if ($context['can_remove_poll'])
			$context['poll_buttons']['remove_poll'] = array('text' => 'poll_remove', 'image' => 'admin_remove_poll.png', 'custom' => 'data-confirm="' . $txt['poll_remove_warn'] . '"', 'class' => 'you_sure', 'url' => $scripturl . '?action=removepoll;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']);

		// Allow mods to add additional buttons here
		call_integration_hook('integrate_poll_buttons');
	}

	$start = $_REQUEST['start'];
	$ascending = empty($options['view_newest_first']);

	// Check if we can use the seek method to speed things up
	if (isset($_SESSION['page_topic']) && $_SESSION['page_topic'] == $topic && $_SESSION['page_ascending'] == $ascending)
	{
		// User moved to the next page
		if (isset($_SESSION['page_next_start']) && $_SESSION['page_next_start'] == $start)
		{
			$start_char = 'M';
			$page_id = $_SESSION['page_last_id'];
		}
		// User moved to the previous page
		elseif (isset($_SESSION['page_before_start']) && $_SESSION['page_before_start'] == $start)
		{
			$start_char = 'L';
			$page_id = $_SESSION['page_first_id'];
		}
		// User refreshed the current page
		elseif (isset($_SESSION['page_current_start']) && $_SESSION['page_current_start'] == $start)
		{
			$start_char = 'C';
			$page_id = $_SESSION['page_first_id'];
		}
	}
	// Special case start page
	elseif ($start == 0)
	{
		$start_char = 'C';
		$page_id = $ascending ? $context['topicinfo']['id_first_msg'] : $context['topicinfo']['id_last_msg'];
	}
	else
		$start_char = null;

	$limit = $context['messages_per_page'];

	$messages = array();
	$all_posters = array();
	$firstIndex = 0;

	if (isset($start_char))
	{
		if ($start_char === 'M' || $start_char === 'C')
		{
			$DBascending = $ascending;
			$page_operator = $ascending ? '>=' : '<=';
		}
		else
		{
			$DBascending = !$ascending;
			$page_operator = $ascending ? '<=' : '>=';
		}

		if ($start_char === 'C')
			$limit_seek = $limit;
		else
			$limit_seek = $limit + 1;

		$request = $smcFunc['db_query']('', '
			SELECT id_msg, id_member, approved
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg ' . $page_operator . ' {int:page_id}' . (!$modSettings['postmod_active'] || $can_approve_posts ? '' : '
				AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')') . '
			ORDER BY id_msg ' . ($DBascending ? '' : 'DESC') . ($context['messages_per_page'] == -1 ? '' : '
			LIMIT {int:limit}'),
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
				'is_approved' => 1,
				'blank_id_member' => 0,
				'limit' => $limit_seek,
				'page_id' => $page_id,
			)
		);

		$found_msg = false;

		// Fallback
		if ($smcFunc['db_num_rows']($request) < 1)
			unset($start_char);
		else
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Check if the start msg is in our result
				if ($row['id_msg'] == $page_id)
					$found_msg = true;

				// Skip the the start msg if we not in mode C
				if ($start_char === 'C' || $row['id_msg'] != $page_id)
				{
					if (!empty($row['id_member']))
						$all_posters[$row['id_msg']] = $row['id_member'];

					$messages[] = $row['id_msg'];
				}
			}

			// page_id not found? -> fallback
			if (!$found_msg)
			{
				$messages = array();
				$all_posters = array();
				unset($start_char);
			}
		}

		// Before Page bring in the right order
		if (!empty($start_char) && $start_char === 'L')
			krsort($messages);
	}

	// Jump to page
	if (empty($start_char))
	{
		// Calculate the fastest way to get the messages!
		if ($start >= $context['total_visible_posts'] / 2 && $context['messages_per_page'] != -1)
		{
			$DBascending = !$ascending;
			$limit = $context['total_visible_posts'] <= $start + $limit ? $context['total_visible_posts'] - $start : $limit;
			$start = $context['total_visible_posts'] <= $start + $limit ? 0 : $context['total_visible_posts'] - $start - $limit;
			$firstIndex = empty($options['view_newest_first']) ? $start - 1 : $limit - 1;
		}
		else
			$DBascending = $ascending;

		// Get each post and poster in this topic.
		$request = $smcFunc['db_query']('', '
			SELECT id_msg, id_member, approved
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}' . (!$modSettings['postmod_active'] || $can_approve_posts ? '' : '
				AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')') . '
			ORDER BY id_msg ' . ($DBascending ? '' : 'DESC') . ($context['messages_per_page'] == -1 ? '' : '
			LIMIT {int:start}, {int:max}'),
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
				'is_approved' => 1,
				'blank_id_member' => 0,
				'start' => $start,
				'max' => $limit,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!empty($row['id_member']))
				$all_posters[$row['id_msg']] = $row['id_member'];
			$messages[] = $row['id_msg'];
		}

		// Sort the messages into the correct display order
		if (!$DBascending)
			sort($messages);
	}

	// Remember the paging data for next time
	$_SESSION['page_first_id'] = $ascending ? reset($messages) : end($messages);
	$_SESSION['page_before_start'] = $_REQUEST['start'] - $limit;
	$_SESSION['page_last_id'] = $ascending ? end($messages) : reset($messages);
	$_SESSION['page_next_start'] = $_REQUEST['start'] + $limit;
	$_SESSION['page_current_start'] = $_REQUEST['start'];
	$_SESSION['page_topic'] = $topic;
	$_SESSION['page_ascending'] = $ascending;

	$smcFunc['db_free_result']($request);
	$posters = array_unique($all_posters);

	call_integration_hook('integrate_display_message_list', array(&$messages, &$posters));

	// Guests can't mark topics read or for notifications, just can't sorry.
	if (!$user_info['is_guest'] && !empty($messages))
	{
		$mark_at_msg = max($messages);
		if ($mark_at_msg >= $context['topicinfo']['id_last_msg'])
			$mark_at_msg = $modSettings['maxMsgID'];
		if ($mark_at_msg >= $context['topicinfo']['new_from'])
		{
			$smcFunc['db_insert']($context['topicinfo']['new_from'] == 0 ? 'ignore' : 'replace',
				'{db_prefix}log_topics',
				array(
					'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int',
				),
				array(
					$user_info['id'], $topic, $mark_at_msg, $context['topicinfo']['unwatched'],
				),
				array('id_member', 'id_topic')
			);
		}

		// Check for notifications on this topic OR board.
		$request = $smcFunc['db_query']('', '
			SELECT sent, id_topic
			FROM {db_prefix}log_notify
			WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
				AND id_member = {int:current_member}
			LIMIT 2',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);
		$do_once = true;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			// Find if this topic is marked for notification...
			if (!empty($row['id_topic']))
				$context['is_marked_notify'] = true;

			// Only do this once, but mark the notifications as "not sent yet" for next time.
			if (!empty($row['sent']) && $do_once)
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_notify
					SET sent = {int:is_not_sent}
					WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
						AND id_member = {int:current_member}',
					array(
						'current_board' => $board,
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
						'is_not_sent' => 0,
					)
				);
				$do_once = false;
			}
		}

		// Have we recently cached the number of new topics in this board, and it's still a lot?
		if (isset($_REQUEST['topicseen']) && isset($_SESSION['topicseen_cache'][$board]) && $_SESSION['topicseen_cache'][$board] > 5)
			$_SESSION['topicseen_cache'][$board]--;
		// Mark board as seen if this is the only new topic.
		elseif (isset($_REQUEST['topicseen']))
		{
			// Use the mark read tables... and the last visit to figure out if this should be read or not.
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = {int:current_board} AND lb.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				WHERE t.id_board = {int:current_board}
					AND t.id_last_msg > COALESCE(lb.id_msg, 0)
					AND t.id_last_msg > COALESCE(lt.id_msg, 0)' . (empty($_SESSION['id_msg_last_visit']) ? '' : '
					AND t.id_last_msg > {int:id_msg_last_visit}'),
				array(
					'current_board' => $board,
					'current_member' => $user_info['id'],
					'id_msg_last_visit' => (int) $_SESSION['id_msg_last_visit'],
				)
			);
			list ($numNewTopics) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			// If there're no real new topics in this board, mark the board as seen.
			if (empty($numNewTopics))
				$_REQUEST['boardseen'] = true;
			else
				$_SESSION['topicseen_cache'][$board] = $numNewTopics;
		}
		// Probably one less topic - maybe not, but even if we decrease this too fast it will only make us look more often.
		elseif (isset($_SESSION['topicseen_cache'][$board]))
			$_SESSION['topicseen_cache'][$board]--;

		// Mark board as seen if we came using last post link from BoardIndex. (or other places...)
		if (isset($_REQUEST['boardseen']))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}log_boards',
				array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
				array($modSettings['maxMsgID'], $user_info['id'], $board),
				array('id_member', 'id_board')
			);
		}
	}

	// Get notification preferences
	$context['topicinfo']['notify_prefs'] = array();
	if (!empty($user_info['id']))
	{
		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs($user_info['id'], array('topic_notify', 'topic_notify_' . $context['current_topic']), true);
		$pref = !empty($prefs[$user_info['id']]) && $context['is_marked_notify'] ? $prefs[$user_info['id']] : array();
		$context['topicinfo']['notify_prefs'] = array(
			'is_custom' => isset($pref['topic_notify_' . $topic]),
			'pref' => isset($pref['topic_notify_' . $context['current_topic']]) ? $pref['topic_notify_' . $context['current_topic']] : (!empty($pref['topic_notify']) ? $pref['topic_notify'] : 0),
		);
	}

	$context['topic_notification'] = !empty($user_info['id']) ? $context['topicinfo']['notify_prefs'] : array();
	// 0 => unwatched, 1 => normal, 2 => receive alerts, 3 => receive emails
	$context['topic_notification_mode'] = !$user_info['is_guest'] ? ($context['topic_unwatched'] ? 0 : ($context['topicinfo']['notify_prefs']['pref'] & 0x02 ? 3 : ($context['topicinfo']['notify_prefs']['pref'] & 0x01 ? 2 : 1))) : 0;

	$context['loaded_attachments'] = array();

	// If there _are_ messages here... (probably an error otherwise :!)
	if (!empty($messages))
	{
		// Fetch attachments.
		if (!empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
		{
			require_once($sourcedir . '/Subs-Attachments.php');
			prepareAttachsByMsg($messages);
		}

		$msg_parameters = array(
			'message_list' => $messages,
			'new_from' => $context['topicinfo']['new_from'],
		);
		$msg_selects = array();
		$msg_tables = array();
		call_integration_hook('integrate_query_message', array(&$msg_selects, &$msg_tables, &$msg_parameters));

		// What?  It's not like it *couldn't* be only guests in this topic...
		loadMemberData($posters);
		$messages_request = $smcFunc['db_query']('', '
			SELECT
				id_msg, icon, subject, poster_time, poster_ip, id_member, modified_time, modified_name, modified_reason, body,
				smileys_enabled, poster_name, poster_email, approved, likes,
				id_msg_modified < {int:new_from} AS is_read
				' . (!empty($msg_selects) ? (', ' . implode(', ', $msg_selects)) : '') . '
			FROM {db_prefix}messages
				' . (!empty($msg_tables) ? implode("\n\t", $msg_tables) : '') . '
			WHERE id_msg IN ({array_int:message_list})
			ORDER BY id_msg' . (empty($options['view_newest_first']) ? '' : ' DESC'),
			$msg_parameters
		);

		// And the likes
		if (!empty($modSettings['enable_likes']))
			$context['my_likes'] = $context['user']['is_guest'] ? array() : prepareLikesContext($topic);

		// Go to the last message if the given time is beyond the time of the last message.
		if (isset($context['start_from']) && $context['start_from'] >= $context['topicinfo']['num_replies'])
			$context['start_from'] = $context['topicinfo']['num_replies'];

		// Since the anchor information is needed on the top of the page we load these variables beforehand.
		$context['first_message'] = isset($messages[$firstIndex]) ? $messages[$firstIndex] : $messages[0];
		if (empty($options['view_newest_first']))
			$context['first_new_message'] = isset($context['start_from']) && $_REQUEST['start'] == $context['start_from'];
		else
			$context['first_new_message'] = isset($context['start_from']) && $_REQUEST['start'] == $context['topicinfo']['num_replies'] - $context['start_from'];
	}
	else
	{
		$messages_request = false;
		$context['first_message'] = 0;
		$context['first_new_message'] = false;

		$context['likes'] = array();
	}

	$context['jump_to'] = array(
		'label' => addslashes(un_htmlspecialchars($txt['jump_to'])),
		'board_name' => $smcFunc['htmlspecialchars'](strtr(strip_tags($board_info['name']), array('&amp;' => '&'))),
		'child_level' => $board_info['child_level'],
	);

	// Set the callback.  (do you REALIZE how much memory all the messages would take?!?)
	// This will be called from the template.
	$context['get_message'] = 'prepareDisplayContext';

	// Now set all the wonderful, wonderful permissions... like moderation ones...
	$common_permissions = array(
		'can_approve' => 'approve_posts',
		'can_ban' => 'manage_bans',
		'can_sticky' => 'make_sticky',
		'can_merge' => 'merge_any',
		'can_split' => 'split_any',
		'calendar_post' => 'calendar_post',
		'can_send_pm' => 'pm_send',
		'can_report_moderator' => 'report_any',
		'can_moderate_forum' => 'moderate_forum',
		'can_issue_warning' => 'issue_warning',
		'can_restore_topic' => 'move_any',
		'can_restore_msg' => 'move_any',
		'can_like' => 'likes_like',
	);
	foreach ($common_permissions as $contextual => $perm)
		$context[$contextual] = allowedTo($perm);

	// Permissions with _any/_own versions.  $context[YYY] => ZZZ_any/_own.
	$anyown_permissions = array(
		'can_move' => 'move',
		'can_lock' => 'lock',
		'can_delete' => 'remove',
		'can_add_poll' => 'poll_add',
		'can_remove_poll' => 'poll_remove',
		'can_reply' => 'post_reply',
		'can_reply_unapproved' => 'post_unapproved_replies',
		'can_view_warning' => 'profile_warning',
	);
	foreach ($anyown_permissions as $contextual => $perm)
		$context[$contextual] = allowedTo($perm . '_any') || ($context['user']['started'] && allowedTo($perm . '_own'));

	if (!$user_info['is_admin'] && $context['can_move'] && !$modSettings['topic_move_any'])
	{
		// We'll use this in a minute
		$boards_allowed = array_diff(boardsAllowedTo('post_new'), array($board));

		/* You can't move this unless you have permission
			to start new topics on at least one other board */
		$context['can_move'] = count($boards_allowed) > 1;
	}

	// If a topic is locked, you can't remove it unless it's yours and you locked it or you can lock_any
	if ($context['topicinfo']['locked'])
	{
		$context['can_delete'] &= (($context['topicinfo']['locked'] == 1 && $context['user']['started']) || allowedTo('lock_any'));
	}

	// Cleanup all the permissions with extra stuff...
	$context['can_mark_notify'] = !$context['user']['is_guest'];
	$context['calendar_post'] &= !empty($modSettings['cal_enabled']);
	$context['can_add_poll'] &= $modSettings['pollMode'] == '1' && $context['topicinfo']['id_poll'] <= 0;
	$context['can_remove_poll'] &= $modSettings['pollMode'] == '1' && $context['topicinfo']['id_poll'] > 0;
	$context['can_reply'] &= empty($context['topicinfo']['locked']) || allowedTo('moderate_board');
	$context['can_reply_unapproved'] &= $modSettings['postmod_active'] && (empty($context['topicinfo']['locked']) || allowedTo('moderate_board'));
	$context['can_issue_warning'] &= $modSettings['warning_settings'][0] == 1;
	// Handle approval flags...
	$context['can_reply_approved'] = $context['can_reply'];
	$context['can_reply'] |= $context['can_reply_unapproved'];
	$context['can_quote'] = $context['can_reply'] && (empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC'])));
	$context['can_mark_unread'] = !$user_info['is_guest'];
	$context['can_unwatch'] = !$user_info['is_guest'];
	$context['can_set_notify'] = !$user_info['is_guest'];

	$context['can_print'] = empty($modSettings['disable_print_topic']);

	// Start this off for quick moderation - it will be or'd for each post.
	$context['can_remove_post'] = allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']);

	// Can restore topic?  That's if the topic is in the recycle board and has a previous restore state.
	$context['can_restore_topic'] &= !empty($board_info['recycle']) && !empty($context['topicinfo']['id_previous_board']);
	$context['can_restore_msg'] &= !empty($board_info['recycle']) && !empty($context['topicinfo']['id_previous_topic']);

	// Check if the draft functions are enabled and that they have permission to use them (for quick reply.)
	$context['drafts_save'] = !empty($modSettings['drafts_post_enabled']) && allowedTo('post_draft') && $context['can_reply'];
	$context['drafts_autosave'] = !empty($context['drafts_save']) && !empty($modSettings['drafts_autosave_enabled']);
	if (!empty($context['drafts_save']))
		loadLanguage('Drafts');

	// When was the last time this topic was replied to?  Should we warn them about it?
	if (!empty($modSettings['oldTopicDays']) && ($context['can_reply'] || $context['can_reply_unapproved']) && empty($context['topicinfo']['is_sticky']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT poster_time
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_last_msg}
			LIMIT 1',
			array(
				'id_last_msg' => $context['topicinfo']['id_last_msg'],
			)
		);

		list ($lastPostTime) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$context['oldTopicError'] = $lastPostTime + $modSettings['oldTopicDays'] * 86400 < time();
	}

	// You can't link an existing topic to the calendar unless you can modify the first post...
	$context['calendar_post'] &= allowedTo('modify_any') || (allowedTo('modify_own') && $context['user']['started']);

	// Load up the "double post" sequencing magic.
	checkSubmitOnce('register');
	$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
	$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
	// Needed for the editor and message icons.
	require_once($sourcedir . '/Subs-Editor.php');

	// Now create the editor.
	$editorOptions = array(
		'id' => 'quickReply',
		'value' => '',
		'disable_smiley_box' => empty($options['use_editor_quick_reply']),
		'labels' => array(
			'post_button' => $txt['post'],
		),
		// add height and width for the editor
		'height' => '250px',
		'width' => '100%',
		// We do HTML preview here.
		'preview_type' => 1,
		// This is required
		'required' => true,
	);
	create_control_richedit($editorOptions);

	// Store the ID.
	$context['post_box_name'] = $editorOptions['id'];

	// Set a flag so the sub template knows what to do...
	$context['show_bbc'] = !empty($options['use_editor_quick_reply']);
	$modSettings['disable_wysiwyg'] = !empty($options['use_editor_quick_reply']);
	$context['attached'] = '';
	$context['make_poll'] = isset($_REQUEST['poll']);

	// Message icons - customized icons are off?
	$context['icons'] = getMessageIcons($board);

	if (!empty($context['icons']))
		$context['icons'][count($context['icons']) - 1]['is_last'] = true;

	// Build the normal button array.
	$context['normal_buttons'] = array();

	if ($context['can_reply'])
		$context['normal_buttons']['reply'] = array('text' => 'reply', 'url' => $scripturl . '?action=post;topic=' . $context['current_topic'] . '.' . $context['start'] . ';last_msg=' . $context['topic_last_message'], 'active' => true);

	if ($context['can_add_poll'])
		$context['normal_buttons']['add_poll'] = array('text' => 'add_poll', 'url' => $scripturl . '?action=editpoll;add;topic=' . $context['current_topic'] . '.' . $context['start']);

	if ($context['can_mark_unread'])
		$context['normal_buttons']['mark_unread'] = array('text' => 'mark_unread', 'url' => $scripturl . '?action=markasread;sa=topic;t=' . $context['mark_unread_time'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id']);

	if ($context['can_print'])
		$context['normal_buttons']['print'] = array('text' => 'print', 'custom' => 'rel="nofollow"', 'url' => $scripturl . '?action=printpage;topic=' . $context['current_topic'] . '.0');

	if ($context['can_set_notify'])
		$context['normal_buttons']['notify'] = array(
			'text' => 'notify_topic_' . $context['topic_notification_mode'],
			'sub_buttons' => array(
				array(
					'test' => 'can_unwatch',
					'text' => 'notify_topic_0',
					'url' => $scripturl . '?action=notifytopic;topic=' . $context['current_topic'] . ';mode=0;' . $context['session_var'] . '=' . $context['session_id'],
				),
				array(
					'text' => 'notify_topic_1',
					'url' => $scripturl . '?action=notifytopic;topic=' . $context['current_topic'] . ';mode=1;' . $context['session_var'] . '=' . $context['session_id'],
				),
				array(
					'text' => 'notify_topic_2',
					'url' => $scripturl . '?action=notifytopic;topic=' . $context['current_topic'] . ';mode=2;' . $context['session_var'] . '=' . $context['session_id'],
				),
				array(
					'text' => 'notify_topic_3',
					'url' => $scripturl . '?action=notifytopic;topic=' . $context['current_topic'] . ';mode=3;' . $context['session_var'] . '=' . $context['session_id'],
				),
			),
		);

	// Build the mod button array
	$context['mod_buttons'] = array();

	if ($context['can_move'])
		$context['mod_buttons']['move'] = array('text' => 'move_topic', 'url' => $scripturl . '?action=movetopic;current_board=' . $context['current_board'] . ';topic=' . $context['current_topic'] . '.0');

	if ($context['can_delete'])
		$context['mod_buttons']['delete'] = array('text' => 'remove_topic', 'custom' => 'data-confirm="' . $txt['are_sure_remove_topic'] . '"', 'class' => 'you_sure', 'url' => $scripturl . '?action=removetopic2;topic=' . $context['current_topic'] . '.0;' . $context['session_var'] . '=' . $context['session_id']);

	if ($context['can_lock'])
		$context['mod_buttons']['lock'] = array('text' => empty($context['is_locked']) ? 'set_lock' : 'set_unlock', 'url' => $scripturl . '?action=lock;topic=' . $context['current_topic'] . '.' . $context['start'] . ';sa=' . ($context['is_locked'] ? 'unlock' : 'lock') . ';' . $context['session_var'] . '=' . $context['session_id']);

	if ($context['can_sticky'])
		$context['mod_buttons']['sticky'] = array('text' => empty($context['is_sticky']) ? 'set_sticky' : 'set_nonsticky', 'url' => $scripturl . '?action=sticky;topic=' . $context['current_topic'] . '.' . $context['start'] . ';sa=' . ($context['is_sticky'] ? 'nonsticky' : 'sticky') . ';' . $context['session_var'] . '=' . $context['session_id']);

	if ($context['can_merge'])
		$context['mod_buttons']['merge'] = array('text' => 'merge', 'url' => $scripturl . '?action=mergetopics;board=' . $context['current_board'] . '.0;from=' . $context['current_topic']);

	if ($context['calendar_post'])
		$context['mod_buttons']['calendar'] = array('text' => 'calendar_link', 'url' => $scripturl . '?action=post;calendar;msg=' . $context['topic_first_message'] . ';topic=' . $context['current_topic'] . '.0');

	// Restore topic. eh?  No monkey business.
	if ($context['can_restore_topic'])
		$context['mod_buttons']['restore_topic'] = array('text' => 'restore_topic', 'url' => $scripturl . '?action=restoretopic;topics=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id']);

	// Show a message in case a recently posted message became unapproved.
	$context['becomesUnapproved'] = !empty($_SESSION['becomesUnapproved']);
	unset($_SESSION['becomesUnapproved']);

	// Allow adding new mod buttons easily.
	// Note: $context['normal_buttons'] and $context['mod_buttons'] are added for backward compatibility with 2.0, but are deprecated and should not be used
	call_integration_hook('integrate_display_buttons', array(&$context['normal_buttons']));
	// Note: integrate_mod_buttons is no more necessary and deprecated, but is kept for backward compatibility with 2.0
	call_integration_hook('integrate_mod_buttons', array(&$context['mod_buttons']));

	// Load the drafts js file
	if ($context['drafts_autosave'])
		loadJavaScriptFile('drafts.js', array('defer' => false, 'minimize' => true), 'smf_drafts');

	// Spellcheck
	if ($context['show_spellchecking'])
		loadJavaScriptFile('spellcheck.js', array('defer' => false, 'minimize' => true), 'smf_spellcheck');

	// topic.js
	loadJavaScriptFile('topic.js', array('defer' => false, 'minimize' => true), 'smf_topic');

	// quotedText.js
	loadJavaScriptFile('quotedText.js', array('defer' => true, 'minimize' => true), 'smf_quotedText');

	// Mentions
	if (!empty($modSettings['enable_mentions']) && allowedTo('mention'))
	{
		loadJavaScriptFile('jquery.atwho.min.js', array('defer' => true), 'smf_atwho');
		loadJavaScriptFile('jquery.caret.min.js', array('defer' => true), 'smf_caret');
		loadJavaScriptFile('mentions.js', array('defer' => true, 'minimize' => true), 'smf_mentions');
	}
}

/**
 * Callback for the message display.
 * It actually gets and prepares the message context.
 * This function will start over from the beginning if reset is set to true, which is
 * useful for showing an index before or after the posts.
 *
 * @param bool $reset Whether or not to reset the db seek pointer
 * @return array A large array of contextual data for the posts
 */
function prepareDisplayContext($reset = false)
{
	global $settings, $txt, $modSettings, $scripturl, $options, $user_info, $smcFunc;
	global $memberContext, $context, $messages_request, $topic, $board_info, $sourcedir;

	static $counter = null;

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// Remember which message this is.  (ie. reply #83)
	if ($counter === null || $reset)
		$counter = empty($options['view_newest_first']) ? $context['start'] : $context['total_visible_posts'] - $context['start'];

	// Start from the beginning...
	if ($reset)
		return @$smcFunc['db_data_seek']($messages_request, 0);

	// Attempt to get the next message.
	$message = $smcFunc['db_fetch_assoc']($messages_request);
	if (!$message)
	{
		$smcFunc['db_free_result']($messages_request);
		return false;
	}

	// $context['icon_sources'] says where each icon should come from - here we set up the ones which will always exist!
	if (empty($context['icon_sources']))
	{
		$context['icon_sources'] = array();
		foreach ($context['stable_icons'] as $icon)
			$context['icon_sources'][$icon] = 'images_url';
	}

	// Message Icon Management... check the images exist.
	if (empty($modSettings['messageIconChecks_disable']))
	{
		// If the current icon isn't known, then we need to do something...
		if (!isset($context['icon_sources'][$message['icon']]))
			$context['icon_sources'][$message['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['icon'] . '.png') ? 'images_url' : 'default_images_url';
	}
	elseif (!isset($context['icon_sources'][$message['icon']]))
		$context['icon_sources'][$message['icon']] = 'images_url';

	// If you're a lazy bum, you probably didn't give a subject...
	$message['subject'] = $message['subject'] != '' ? $message['subject'] : $txt['no_subject'];

	// Are you allowed to remove at least a single reply?
	$context['can_remove_post'] |= allowedTo('delete_own') && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()) && $message['id_member'] == $user_info['id'];

	// If the topic is locked, you might not be able to delete the post...
	if ($context['is_locked'])
	{
		$context['can_remove_post'] &= ($context['user']['started'] && $context['is_locked'] == 1) || allowedTo('lock_any');
	}

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (!loadMemberContext($message['id_member'], true))
	{
		// Notice this information isn't used anywhere else....
		$memberContext[$message['id_member']]['name'] = $message['poster_name'];
		$memberContext[$message['id_member']]['id'] = 0;
		$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
		$memberContext[$message['id_member']]['link'] = $message['poster_name'];
		$memberContext[$message['id_member']]['email'] = $message['poster_email'];
		$memberContext[$message['id_member']]['show_email'] = allowedTo('moderate_forum');
		$memberContext[$message['id_member']]['is_guest'] = true;
	}
	else
	{
		// Define this here to make things a bit more readable
		$can_view_warning = allowedTo('moderate_forum') || allowedTo('view_warning_any') || ($message['id_member'] == $user_info['id'] && allowedTo('view_warning_own'));

		$memberContext[$message['id_member']]['can_view_profile'] = allowedTo('profile_view') || ($message['id_member'] == $user_info['id'] && !$user_info['is_guest']);
		$memberContext[$message['id_member']]['is_topic_starter'] = $message['id_member'] == $context['topic_starter_id'];
		$memberContext[$message['id_member']]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$message['id_member']]['warning_status'] && $can_view_warning;
		// Show the email if it's your post...
		$memberContext[$message['id_member']]['show_email'] |= ($message['id_member'] == $user_info['id']);
	}

	$memberContext[$message['id_member']]['ip'] = inet_dtop($message['poster_ip']);
	$memberContext[$message['id_member']]['show_profile_buttons'] = !empty($modSettings['show_profile_buttons']) && (!empty($memberContext[$message['id_member']]['can_view_profile']) || (!empty($memberContext[$message['id_member']]['website']['url']) && !isset($context['disabled_fields']['website'])) || $memberContext[$message['id_member']]['show_email'] || $context['can_send_pm']);

	// Do the censor thang.
	censorText($message['body']);
	censorText($message['subject']);

	// Run BBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], $message['smileys_enabled'], $message['id_msg']);

	// If it's in the recycle bin we need to override whatever icon we did have.
	if (!empty($board_info['recycle']))
		$message['icon'] = 'recycled';

	require_once($sourcedir . '/Subs-Attachments.php');

	// Compose the memory eat- I mean message array.
	$output = array(
		'attachment' => loadAttachmentContext($message['id_msg'], $context['loaded_attachments']),
		'id' => $message['id_msg'],
		'href' => $scripturl . '?msg=' . $message['id_msg'],
		'link' => '<a href="' . $scripturl . '?msg=' . $message['id_msg'] . '" rel="nofollow">' . $message['subject'] . '</a>',
		'member' => &$memberContext[$message['id_member']],
		'icon' => $message['icon'],
		'icon_url' => $settings[$context['icon_sources'][$message['icon']]] . '/post/' . $message['icon'] . '.png',
		'subject' => $message['subject'],
		'time' => timeformat($message['poster_time']),
		'timestamp' => forum_time(true, $message['poster_time']),
		'counter' => $counter,
		'modified' => array(
			'time' => timeformat($message['modified_time']),
			'timestamp' => forum_time(true, $message['modified_time']),
			'name' => $message['modified_name'],
			'reason' => $message['modified_reason']
		),
		'body' => $message['body'],
		'new' => empty($message['is_read']),
		'approved' => $message['approved'],
		'first_new' => isset($context['start_from']) && $context['start_from'] == $counter,
		'is_ignored' => !empty($modSettings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($message['id_member'], $context['user']['ignoreusers']),
		'can_approve' => !$message['approved'] && $context['can_approve'],
		'can_unapprove' => !empty($modSettings['postmod_active']) && $context['can_approve'] && $message['approved'],
		'can_modify' => (!$context['is_locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $context['user']['started']) || (allowedTo('modify_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || !$message['approved'] || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time()))),
		'can_remove' => allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']) || (allowedTo('delete_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())),
		'can_see_ip' => allowedTo('moderate_forum') || ($message['id_member'] == $user_info['id'] && !empty($user_info['id'])),
		'css_class' => $message['approved'] ? 'windowbg' : 'approvebg',
	);

	// Does the file contains any attachments? if so, change the icon.
	if (!empty($output['attachment']))
	{
		$output['icon'] = 'clip';
		$output['icon_url'] = $settings[$context['icon_sources'][$output['icon']]] . '/post/' . $output['icon'] . '.png';
	}

	// Are likes enable?
	if (!empty($modSettings['enable_likes']))
		$output['likes'] = array(
			'count' => $message['likes'],
			'you' => in_array($message['id_msg'], $context['my_likes']),
			'can_like' => !$context['user']['is_guest'] && $message['id_member'] != $context['user']['id'] && !empty($context['can_like']),
		);

	// Is this user the message author?
	$output['is_message_author'] = $message['id_member'] == $user_info['id'];
	if (!empty($output['modified']['name']))
		$output['modified']['last_edit_text'] = sprintf($txt['last_edit_by'], $output['modified']['time'], $output['modified']['name']);

	// Did they give a reason for editing?
	if (!empty($output['modified']['name']) && !empty($output['modified']['reason']))
		$output['modified']['last_edit_text'] .= '&nbsp;' . sprintf($txt['last_edit_reason'], $output['modified']['reason']);

	// Any custom profile fields?
	if (!empty($memberContext[$message['id_member']]['custom_fields']))
		foreach ($memberContext[$message['id_member']]['custom_fields'] as $custom)
			$output['custom_fields'][$context['cust_profile_fields_placement'][$custom['placement']]][] = $custom;

	$output['quickbuttons'] = array(
		'quote' => array(
			'label' => $txt['quote_action'],
			'href' => $scripturl.'?action=post;quote='.$output['id'].';topic='.$context['current_topic'], '.'.$context['start'].';last_msg='.$context['topic_last_message'],
			'javascript' => 'onclick="return oQuickReply.quote('.$output['id'].');"',
			'icon' => 'quote',
			'show' => $context['can_quote']
		),
		'quote_selected' => array(
			'label' => $txt['quote_selected_action'],
			'id' => 'quoteSelected_'. $output['id'],
			'href' => 'javascript:void(0)',
			'custom' => 'style="display:none"',
			'icon' => 'quote_selected',
			'show' => $context['can_quote']
		),
		'quick_edit' => array(
			'label' => $txt['quick_edit'],
			'class' => 'quick_edit',
			'id' =>' modify_button_'. $output['id'],
			'custom' => 'onclick="oQuickModify.modifyMsg(\''.$output['id'].'\', \''.!empty($modSettings['toggle_subject']).'\')"',
			'icon' => 'quick_edit_button',
			'show' => $output['can_modify']
		),
		'more' => array(
			'modify' => array(
				'label' => $txt['modify'],
				'href' => $scripturl.'?action=post;msg='.$output['id'].';topic='.$context['current_topic'].'.'.$context['start'],
				'icon' => 'modify_button',
				'show' => $output['can_modify']
			),
			'remove_topic' => array(
				'label' => $txt['remove_topic'],
				'href' => $scripturl.'?action=removetopic2;topic='.$context['current_topic'].'.'.$context['start'].';'.$context['session_var'].'='.$context['session_id'],
				'javascript' => 'data-confirm="'.$txt['are_sure_remove_topic'].'" class="you_sure"',
				'icon' => 'remove_button',
				'show' => $context['can_delete'] && ($context['topic_first_message'] == $output['id'])
			),
			'remove' => array(
				'label' => $txt['remove'],
				'href' => $scripturl.'?action=deletemsg;topic='.$context['current_topic'].'.'.$context['start'].';msg='.$output['id'].';'.$context['session_var'].'='.$context['session_id'],
				'javascript' => 'data-confirm="'.$txt['remove_message_question'].'" class="you_sure"',
				'icon' => 'remove_button',
				'show' => $output['can_remove'] && ($context['topic_first_message'] != $output['id'])
			),
			'split' => array(
				'label' => $txt['split'],
				'href' => $scripturl.'?action=splittopics;topic='.$context['current_topic'].'.0;at='.$output['id'],
				'icon' => 'split_button',
				'show' => $context['can_split'] && !empty($context['real_num_replies'])
			),
			'report' => array(
				'label' => $txt['report_to_mod'],
				'href' => $scripturl.'?action=reporttm;topic='.$context['current_topic'].'.'.$output['counter'].';msg='.$output['id'],
				'icon' => 'error',
				'show' => $context['can_report_moderator']
			),
			'warn' => array(
				'label' => $txt['issue_warning'],
				'href' => $scripturl.'?action=profile;area=issuewarning;u='.$output['member']['id'].';msg='.$output['id'],
				'icon' => 'warn_button',
				'show' => $context['can_issue_warning'] && !$output['is_message_author'] && !$output['member']['is_guest']
			),
			'restore' => array(
				'label' => $txt['restore_message'],
				'href' => $scripturl.'?action=restoretopic;msgs='.$output['id'].';'.$context['session_var'].'='.$context['session_id'],
				'icon' => 'restore_button',
				'show' => $context['can_restore_msg']
			),
			'approve' => array(
				'label' => $txt['approve'],
				'href' => $scripturl.'?action=moderate;area=postmod;sa=approve;topic='.$context['current_topic'].'.'.$context['start'].';msg='.$output['id'].';'.$context['session_var'].'='.$context['session_id'],
				'icon' => 'approve_button',
				'show' => $output['can_approve']
			),
			'unapprove' => array(
				'label' => $txt['unapprove'],
				'href' => $scripturl.'?action=moderate;area=postmod;sa=approve;topic='.$context['current_topic'].'.'.$context['start'].';msg='.$output['id'].';'.$context['session_var'].'='.$context['session_id'],
				'icon' => 'unapprove_button',
				'show' => $output['can_unapprove']
			),
		),
		'quickmod' => array(
			'id' => 'in_topic_mod_check_'. $output['id'],
			'custom' => 'style="display: none;"',
			'content' => '',
			'show' => !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $output['can_remove'],
		)
	);

	if (empty($options['view_newest_first']))
		$counter++;

	else
		$counter--;

	call_integration_hook('integrate_prepare_display_context', array(&$output, &$message, $counter));

	return $output;
}

/**
 * Once upon a time, this function handled downloading attachments.
 * Now it's just an alias retained for the sake of backwards compatibility.
 */
function Download()
{
	global $sourcedir;
	require_once($sourcedir . '/ShowAttachments.php');
	showAttachment();
}

/**
 * In-topic quick moderation.
 */
function QuickInTopicModeration()
{
	global $sourcedir, $topic, $board, $user_info, $smcFunc, $modSettings, $context;

	// Check the session = get or post.
	checkSession('request');

	require_once($sourcedir . '/RemoveTopic.php');

	if (empty($_REQUEST['msgs']))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);

	$messages = array();
	foreach ($_REQUEST['msgs'] as $dummy)
		$messages[] = (int) $dummy;

	// We are restoring messages. We handle this in another place.
	if (isset($_REQUEST['restore_selected']))
		redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . $context['session_var'] . '=' . $context['session_id']);
	if (isset($_REQUEST['split_selection']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT subject
			FROM {db_prefix}messages
			WHERE id_msg = {int:message}
			LIMIT 1',
			array(
				'message' => min($messages),
			)
		);
		list($subname) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$_SESSION['split_selection'][$topic] = $messages;
		redirectexit('action=splittopics;sa=selectTopics;topic=' . $topic . '.0;subname_enc=' . urlencode($subname) . ';' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Allowed to delete any message?
	if (allowedTo('delete_any'))
		$allowed_all = true;
	// Allowed to delete replies to their messages?
	elseif (allowedTo('delete_replies'))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member_started
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		list ($starter) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$allowed_all = $starter == $user_info['id'];
	}
	else
		$allowed_all = false;

	// Make sure they're allowed to delete their own messages, if not any.
	if (!$allowed_all)
		isAllowedTo('delete_own');

	// Allowed to remove which messages?
	$request = $smcFunc['db_query']('', '
		SELECT id_msg, subject, id_member, poster_time
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:message_list})
			AND id_topic = {int:current_topic}' . (!$allowed_all ? '
			AND id_member = {int:current_member}' : '') . '
		LIMIT {int:limit}',
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'message_list' => $messages,
			'limit' => count($messages),
		)
	);
	$messages = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!$allowed_all && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
			continue;

		$messages[$row['id_msg']] = array($row['subject'], $row['id_member']);
	}
	$smcFunc['db_free_result']($request);

	// Get the first message in the topic - because you can't delete that!
	$request = $smcFunc['db_query']('', '
		SELECT id_first_msg, id_last_msg
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($first_message, $last_message) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Delete all the messages we know they can delete. ($messages)
	foreach ($messages as $message => $info)
	{
		// Just skip the first message - if it's not the last.
		if ($message == $first_message && $message != $last_message)
			continue;
		// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
		elseif ($message == $first_message)
			$topicGone = true;

		removeMessage($message);

		// Log this moderation action ;).
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != $user_info['id']))
			logAction('delete', array('topic' => $topic, 'subject' => $info[0], 'member' => $info[1], 'board' => $board));
	}

	redirectexit(!empty($topicGone) ? 'board=' . $board : 'topic=' . $topic . '.' . $_REQUEST['start']);
}

?>