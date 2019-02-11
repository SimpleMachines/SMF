<?php

/**
 * This file contains a couple of functions for the latests posts on forum.
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
 * Get the latest posts of a forum.
 *
 * @param array $latestPostOptions
 * @return array
 */
function getLastPosts($latestPostOptions)
{
	global $scripturl, $modSettings, $smcFunc;

	// Find all the posts.  Newer ones will have higher IDs.  (assuming the last 20 * number are accessible...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $smcFunc['db_query']('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, t.id_board, b.name AS board_name,
			SUBSTRING(m.body, 1, 385) AS body, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg >= {int:likely_max_msg}' .
			(!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}' : '') . '
		ORDER BY m.id_msg DESC
		LIMIT ' . $latestPostOptions['number_posts'],
		array(
			'likely_max_msg' => max(0, $modSettings['maxMsgID'] - 50 * $latestPostOptions['number_posts']),
			'recycle_board' => $modSettings['recycle_board'],
			'is_approved' => 1,
		)
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor the subject and post for the preview ;).
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br>' => '&#10;')));
		if ($smcFunc['strlen']($row['body']) > 128)
			$row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';

		// Build the array.
		$posts[] = array(
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
			),
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => shorten_subject($row['subject'], 24),
			'preview' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>'
		);
	}
	$smcFunc['db_free_result']($request);

	return $posts;
}

/**
 * Callback-function for the cache for getLastPosts().
 *
 * @param array $latestPostOptions
 */
function cache_getLastPosts($latestPostOptions)
{
	return array(
		'data' => getLastPosts($latestPostOptions),
		'expires' => time() + 60,
		'post_retri_eval' => '
			foreach ($cache_block[\'data\'] as $k => $post)
			{
				$cache_block[\'data\'][$k][\'time\'] = timeformat($post[\'raw_timestamp\']);
				$cache_block[\'data\'][$k][\'timestamp\'] = forum_time(true, $post[\'raw_timestamp\']);
			}',
	);
}

?>