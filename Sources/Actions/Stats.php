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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Provides a display for forum statistics.
 */
class Stats implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'DisplayStats',
		],
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Display some useful/interesting board statistics.
	 *
	 * gets all the statistics in order and puts them in.
	 * uses the Stats template and language file. (and main sub template.)
	 * requires the view_stats permission.
	 * accessed from ?action=stats.
	 */
	public function execute(): void
	{
		User::$me->isAllowedTo('view_stats');

		// Page disabled - redirect them out
		if (empty(Config::$modSettings['trackStats'])) {
			ErrorHandler::fatalLang('feature_disabled', true);
		}

		if (!empty($_REQUEST['expand'])) {
			Utils::$context['robot_no_index'] = true;

			$month = (int) substr($_REQUEST['expand'], 4);
			$year = (int) substr($_REQUEST['expand'], 0, 4);

			if ($year > 1900 && $year < 2200 && $month >= 1 && $month <= 12) {
				$_SESSION['expanded_stats'][$year][] = $month;
			}
		} elseif (!empty($_REQUEST['collapse'])) {
			Utils::$context['robot_no_index'] = true;

			$month = (int) substr($_REQUEST['collapse'], 4);
			$year = (int) substr($_REQUEST['collapse'], 0, 4);

			if (!empty($_SESSION['expanded_stats'][$year])) {
				$_SESSION['expanded_stats'][$year] = array_diff($_SESSION['expanded_stats'][$year], [$month]);
			}
		}

		// Handle the XMLHttpRequest.
		if (isset($_REQUEST['xml'])) {
			// Collapsing stats only needs adjustments of the session variables.
			if (!empty($_REQUEST['collapse'])) {
				Utils::obExit(false);
			}

			Utils::$context['sub_template'] = 'stats';
			Utils::$context['yearly'] = [];

			if (empty($month) || empty($year)) {
				return;
			}

			$this->getDailyStats('YEAR(date) = {int:year} AND MONTH(date) = {int:month}', ['year' => $year, 'month' => $month]);

			Utils::$context['yearly'][$year]['months'][$month]['date'] = [
				'month' => sprintf('%02d', $month),
				'year' => $year,
			];

			return;
		}

		Lang::load('Stats');
		Theme::loadTemplate('Stats');
		Theme::loadJavaScriptFile('stats.js', ['default_theme' => true, 'defer' => false, 'minimize' => true], 'smf_stats');

		// Build the link tree......
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=stats',
			'name' => Lang::$txt['stats_center'],
		];
		Utils::$context['page_title'] = Utils::$context['forum_name'] . ' - ' . Lang::$txt['stats_center'];

		Utils::$context['show_member_list'] = User::$me->allowedTo('view_mlist');

		// Get averages...
		$result = Db::$db->query(
			'',
			'SELECT
				SUM(posts) AS posts, SUM(topics) AS topics, SUM(registers) AS registers,
				SUM(most_on) AS most_on, MIN(date) AS date, SUM(hits) AS hits
			FROM {db_prefix}log_activity',
			[
			],
		);
		$row = Db::$db->fetch_assoc($result);
		Db::$db->free_result($result);

		// This would be the amount of time the forum has been up... in days...
		$total_days_up = ceil((time() - strtotime($row['date'])) / (60 * 60 * 24));

		Utils::$context['average_posts'] = Lang::numberFormat(round($row['posts'] / $total_days_up, 2));
		Utils::$context['average_topics'] = Lang::numberFormat(round($row['topics'] / $total_days_up, 2));
		Utils::$context['average_members'] = Lang::numberFormat(round($row['registers'] / $total_days_up, 2));
		Utils::$context['average_online'] = Lang::numberFormat(round($row['most_on'] / $total_days_up, 2));
		Utils::$context['average_hits'] = Lang::numberFormat(round($row['hits'] / $total_days_up, 2));

		Utils::$context['num_hits'] = Lang::numberFormat($row['hits'], 0);

		// How many users are online now.
		$result = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_online',
			[
			],
		);
		list(Utils::$context['users_online']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// Statistics such as number of boards, categories, etc.
		$result = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}boards AS b
			WHERE b.redirect = {string:blank_redirect}',
			[
				'blank_redirect' => '',
			],
		);
		list(Utils::$context['num_boards']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		$result = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}categories AS c',
			[
			],
		);
		list(Utils::$context['num_categories']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// Format the numbers nicely.
		Utils::$context['users_online'] = Lang::numberFormat(Utils::$context['users_online']);
		Utils::$context['num_boards'] = Lang::numberFormat(Utils::$context['num_boards']);
		Utils::$context['num_categories'] = Lang::numberFormat(Utils::$context['num_categories']);

		Utils::$context['num_members'] = Lang::numberFormat(Config::$modSettings['totalMembers']);
		Utils::$context['num_posts'] = Lang::numberFormat(Config::$modSettings['totalMessages']);
		Utils::$context['num_topics'] = Lang::numberFormat(Config::$modSettings['totalTopics']);
		Utils::$context['most_members_online'] = [
			'number' => Lang::numberFormat(Config::$modSettings['mostOnline']),
			'date' => Time::create('@' . Config::$modSettings['mostDate'])->format(),
		];
		Utils::$context['latest_member'] = &Utils::$context['common_stats']['latest_member'];

		// Let's calculate gender stats only every four minutes.
		$disabled_fields = isset(Config::$modSettings['disabled_profile_fields']) ? explode(',', Config::$modSettings['disabled_profile_fields']) : [];

		if (!in_array('gender', $disabled_fields)) {
			if ((Utils::$context['gender'] = CacheApi::get('stats_gender', 240)) == null) {
				$result = Db::$db->query(
					'',
					'SELECT default_value
					FROM {db_prefix}custom_fields
					WHERE col_name= {string:gender_var}',
					[
						'gender_var' => 'cust_gender',
					],
				);
				$row = Db::$db->fetch_assoc($result);
				$default_gender = !empty($row['default_value']) ? $row['default_value'] : '{gender_0}';
				Db::$db->free_result($result);

				$result = Db::$db->query(
					'',
					'SELECT COUNT(*) AS total_members, value AS gender
					FROM {db_prefix}members AS mem
					INNER JOIN {db_prefix}themes AS t ON (
						t.id_member = mem.id_member
						AND t.variable = {string:gender_var}
						AND t.id_theme = {int:default_theme}
					)
					WHERE is_activated = {int:is_activated}
					GROUP BY value',
					[
						'gender_var' => 'cust_gender',
						'default_theme' => 1,
						'is_activated' => 1,
						'default_gender' => $default_gender,
					],
				);
				Utils::$context['gender'] = [$default_gender => 0];

				while ($row = Db::$db->fetch_assoc($result)) {
					Utils::$context['gender'][$row['gender']] = $row['total_members'];
				}
				Db::$db->free_result($result);

				Utils::$context['gender'][$default_gender] += Config::$modSettings['totalMembers'] - array_sum(Utils::$context['gender']);

				CacheApi::put('stats_gender', Utils::$context['gender'], 240);
			}
		}

		$date = Time::strftime('%Y-%m-%d', time());

		// Members online so far today.
		$result = Db::$db->query(
			'',
			'SELECT most_on
			FROM {db_prefix}log_activity
			WHERE date = {date:today_date}
			LIMIT 1',
			[
				'today_date' => $date,
			],
		);
		list(Utils::$context['online_today']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		Utils::$context['online_today'] = Lang::numberFormat((int) Utils::$context['online_today']);

		// Poster top 10.
		$members_result = Db::$db->query(
			'',
			'SELECT id_member, real_name, posts
			FROM {db_prefix}members
			WHERE posts > {int:no_posts}
			ORDER BY posts DESC
			LIMIT 10',
			[
				'no_posts' => 0,
			],
		);
		Utils::$context['stats_blocks']['posters'] = [];
		$max_num_posts = 1;

		while ($row_members = Db::$db->fetch_assoc($members_result)) {
			Utils::$context['stats_blocks']['posters'][] = [
				'name' => $row_members['real_name'],
				'id' => $row_members['id_member'],
				'num' => $row_members['posts'],
				'href' => Config::$scripturl . '?action=profile;u=' . $row_members['id_member'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>',
			];

			if ($max_num_posts < $row_members['posts']) {
				$max_num_posts = $row_members['posts'];
			}
		}
		Db::$db->free_result($members_result);

		foreach (Utils::$context['stats_blocks']['posters'] as $i => $poster) {
			Utils::$context['stats_blocks']['posters'][$i]['percent'] = round(($poster['num'] * 100) / $max_num_posts);
			Utils::$context['stats_blocks']['posters'][$i]['num'] = Lang::numberFormat(Utils::$context['stats_blocks']['posters'][$i]['num']);
		}

		// Board top 10.
		$boards_result = Db::$db->query(
			'',
			'SELECT id_board, name, num_posts
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : '') . '
				AND b.redirect = {string:blank_redirect}
			ORDER BY num_posts DESC
			LIMIT 10',
			[
				'recycle_board' => Config::$modSettings['recycle_board'],
				'blank_redirect' => '',
			],
		);
		Utils::$context['stats_blocks']['boards'] = [];
		$max_num_posts = 1;

		while ($row_board = Db::$db->fetch_assoc($boards_result)) {
			Utils::$context['stats_blocks']['boards'][] = [
				'id' => $row_board['id_board'],
				'name' => $row_board['name'],
				'num' => $row_board['num_posts'],
				'href' => Config::$scripturl . '?board=' . $row_board['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row_board['id_board'] . '.0">' . $row_board['name'] . '</a>',
			];

			if ($max_num_posts < $row_board['num_posts']) {
				$max_num_posts = $row_board['num_posts'];
			}
		}
		Db::$db->free_result($boards_result);

		foreach (Utils::$context['stats_blocks']['boards'] as $i => $board) {
			Utils::$context['stats_blocks']['boards'][$i]['percent'] = round(($board['num'] * 100) / $max_num_posts);
			Utils::$context['stats_blocks']['boards'][$i]['num'] = Lang::numberFormat(Utils::$context['stats_blocks']['boards'][$i]['num']);
		}

		// Are you on a larger forum?  If so, let's try to limit the number of topics we search through.
		if (Config::$modSettings['totalMessages'] > 100000) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}topics
				WHERE num_replies != {int:no_replies}' . (Config::$modSettings['postmod_active'] ? '
					AND approved = {int:is_approved}' : '') . '
				ORDER BY num_replies DESC
				LIMIT 100',
				[
					'no_replies' => 0,
					'is_approved' => 1,
				],
			);
			$topic_ids = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$topic_ids[] = $row['id_topic'];
			}
			Db::$db->free_result($request);
		} else {
			$topic_ids = [];
		}

		// Topic replies top 10.
		$topic_reply_result = Db::$db->query(
			'',
			'SELECT m.subject, t.num_replies, t.id_board, t.id_topic, b.name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : '') . ')
			WHERE {query_see_board}' . (!empty($topic_ids) ? '
				AND t.id_topic IN ({array_int:topic_list})' : (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '')) . '
			ORDER BY t.num_replies DESC
			LIMIT 10',
			[
				'topic_list' => $topic_ids,
				'recycle_board' => Config::$modSettings['recycle_board'],
				'is_approved' => 1,
			],
		);
		Utils::$context['stats_blocks']['topics_replies'] = [];
		$max_num_replies = 1;

		while ($row_topic_reply = Db::$db->fetch_assoc($topic_reply_result)) {
			Lang::censorText($row_topic_reply['subject']);

			Utils::$context['stats_blocks']['topics_replies'][] = [
				'id' => $row_topic_reply['id_topic'],
				'board' => [
					'id' => $row_topic_reply['id_board'],
					'name' => $row_topic_reply['name'],
					'href' => Config::$scripturl . '?board=' . $row_topic_reply['id_board'] . '.0',
					'link' => '<a href="' . Config::$scripturl . '?board=' . $row_topic_reply['id_board'] . '.0">' . $row_topic_reply['name'] . '</a>',
				],
				'subject' => $row_topic_reply['subject'],
				'num' => $row_topic_reply['num_replies'],
				'href' => Config::$scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row_topic_reply['id_topic'] . '.0">' . $row_topic_reply['subject'] . '</a>',
			];

			if ($max_num_replies < $row_topic_reply['num_replies']) {
				$max_num_replies = $row_topic_reply['num_replies'];
			}
		}
		Db::$db->free_result($topic_reply_result);

		foreach (Utils::$context['stats_blocks']['topics_replies'] as $i => $topic) {
			Utils::$context['stats_blocks']['topics_replies'][$i]['percent'] = round(($topic['num'] * 100) / $max_num_replies);
			Utils::$context['stats_blocks']['topics_replies'][$i]['num'] = Lang::numberFormat(Utils::$context['stats_blocks']['topics_replies'][$i]['num']);
		}

		// Large forums may need a bit more prodding...
		if (Config::$modSettings['totalMessages'] > 100000) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}topics
				WHERE num_views != {int:no_views}
				ORDER BY num_views DESC
				LIMIT 100',
				[
					'no_views' => 0,
				],
			);
			$topic_ids = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$topic_ids[] = $row['id_topic'];
			}
			Db::$db->free_result($request);
		} else {
			$topic_ids = [];
		}

		// Topic views top 10.
		$topic_view_result = Db::$db->query(
			'',
			'SELECT m.subject, t.num_views, t.id_board, t.id_topic, b.name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : '') . ')
			WHERE {query_see_board}' . (!empty($topic_ids) ? '
				AND t.id_topic IN ({array_int:topic_list})' : (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '')) . '
			ORDER BY t.num_views DESC
			LIMIT 10',
			[
				'topic_list' => $topic_ids,
				'recycle_board' => Config::$modSettings['recycle_board'],
				'is_approved' => 1,
			],
		);
		Utils::$context['stats_blocks']['topics_views'] = [];
		$max_num = 1;

		while ($row_topic_views = Db::$db->fetch_assoc($topic_view_result)) {
			Lang::censorText($row_topic_views['subject']);

			Utils::$context['stats_blocks']['topics_views'][] = [
				'id' => $row_topic_views['id_topic'],
				'board' => [
					'id' => $row_topic_views['id_board'],
					'name' => $row_topic_views['name'],
					'href' => Config::$scripturl . '?board=' . $row_topic_views['id_board'] . '.0',
					'link' => '<a href="' . Config::$scripturl . '?board=' . $row_topic_views['id_board'] . '.0">' . $row_topic_views['name'] . '</a>',
				],
				'subject' => $row_topic_views['subject'],
				'num' => $row_topic_views['num_views'],
				'href' => Config::$scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row_topic_views['id_topic'] . '.0">' . $row_topic_views['subject'] . '</a>',
			];

			if ($max_num < $row_topic_views['num_views']) {
				$max_num = $row_topic_views['num_views'];
			}
		}
		Db::$db->free_result($topic_view_result);

		foreach (Utils::$context['stats_blocks']['topics_views'] as $i => $topic) {
			Utils::$context['stats_blocks']['topics_views'][$i]['percent'] = round(($topic['num'] * 100) / $max_num);
			Utils::$context['stats_blocks']['topics_views'][$i]['num'] = Lang::numberFormat(Utils::$context['stats_blocks']['topics_views'][$i]['num']);
		}

		// Try to cache this when possible, because it's a little unavoidably slow.
		if (($members = CacheApi::get('stats_top_starters', 360)) == null) {
			$request = Db::$db->query(
				'',
				'SELECT id_member_started, COUNT(*) AS hits
				FROM {db_prefix}topics' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				WHERE id_board != {int:recycle_board}' : '') . '
				GROUP BY id_member_started
				ORDER BY hits DESC
				LIMIT 20',
				[
					'recycle_board' => Config::$modSettings['recycle_board'],
				],
			);
			$members = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$members[$row['id_member_started']] = $row['hits'];
			}
			Db::$db->free_result($request);

			CacheApi::put('stats_top_starters', $members, 360);
		}

		if (empty($members)) {
			$members = [0 => 0];
		}

		// Topic poster top 10.
		$members_result = Db::$db->query(
			'',
			'SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})',
			[
				'member_list' => array_keys($members),
			],
		);
		Utils::$context['stats_blocks']['starters'] = [];
		$max_num = 1;

		while ($row_members = Db::$db->fetch_assoc($members_result)) {
			$i = array_search($row_members['id_member'], array_keys($members));

			// skip all not top 10
			if ($i > 10) {
				continue;
			}

			Utils::$context['stats_blocks']['starters'][$i] = [
				'name' => $row_members['real_name'],
				'id' => $row_members['id_member'],
				'num' => $members[$row_members['id_member']],
				'href' => Config::$scripturl . '?action=profile;u=' . $row_members['id_member'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>',
			];

			if ($max_num < $members[$row_members['id_member']]) {
				$max_num = $members[$row_members['id_member']];
			}
		}
		ksort(Utils::$context['stats_blocks']['starters']);
		Db::$db->free_result($members_result);

		foreach (Utils::$context['stats_blocks']['starters'] as $i => $topic) {
			Utils::$context['stats_blocks']['starters'][$i]['percent'] = round(($topic['num'] * 100) / $max_num);
			Utils::$context['stats_blocks']['starters'][$i]['num'] = Lang::numberFormat(Utils::$context['stats_blocks']['starters'][$i]['num']);
		}

		// Time online top 10.
		$temp = CacheApi::get('stats_total_time_members', 600);
		$members_result = Db::$db->query(
			'',
			'SELECT id_member, real_name, total_time_logged_in
			FROM {db_prefix}members
			WHERE is_activated = {int:is_activated}' .
			(!empty($temp) ? ' AND id_member IN ({array_int:member_list_cached})' : '') . '
			ORDER BY total_time_logged_in DESC
			LIMIT 20',
			[
				'member_list_cached' => $temp,
				'is_activated' => 1,
			],
		);
		Utils::$context['stats_blocks']['time_online'] = [];
		$temp2 = [];
		$max_time_online = 1;

		while ($row_members = Db::$db->fetch_assoc($members_result)) {
			$temp2[] = (int) $row_members['id_member'];

			if (count(Utils::$context['stats_blocks']['time_online']) >= 10) {
				continue;
			}

			// Figure out the days, hours and minutes.
			$timeDays = floor($row_members['total_time_logged_in'] / 86400);
			$timeHours = floor(($row_members['total_time_logged_in'] % 86400) / 3600);

			// Figure out which things to show... (days, hours, minutes, etc.)
			$timelogged = '';

			if ($timeDays > 0) {
				$timelogged .= $timeDays . Lang::$txt['total_time_logged_d'];
			}

			if ($timeHours > 0) {
				$timelogged .= $timeHours . Lang::$txt['total_time_logged_h'];
			}
			$timelogged .= floor(($row_members['total_time_logged_in'] % 3600) / 60) . Lang::$txt['total_time_logged_m'];

			Utils::$context['stats_blocks']['time_online'][] = [
				'id' => $row_members['id_member'],
				'name' => $row_members['real_name'],
				'num' => $timelogged,
				'seconds_online' => $row_members['total_time_logged_in'],
				'href' => Config::$scripturl . '?action=profile;u=' . $row_members['id_member'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>',
			];

			if ($max_time_online < $row_members['total_time_logged_in']) {
				$max_time_online = $row_members['total_time_logged_in'];
			}
		}
		Db::$db->free_result($members_result);

		foreach (Utils::$context['stats_blocks']['time_online'] as $i => $member) {
			Utils::$context['stats_blocks']['time_online'][$i]['percent'] = round(($member['seconds_online'] * 100) / $max_time_online);
		}

		// Cache the ones we found for a bit, just so we don't have to look again.
		if ($temp !== $temp2) {
			CacheApi::put('stats_total_time_members', $temp2, 480);
		}

		// Likes.
		if (!empty(Config::$modSettings['enable_likes'])) {
			// Liked messages top 10.
			Utils::$context['stats_blocks']['liked_messages'] = [];
			$max_liked_message = 1;
			$liked_messages = Db::$db->query(
				'',
				'SELECT m.id_msg, m.subject, m.likes, m.id_board, m.id_topic, t.approved
				FROM (
					SELECT n.id_msg, n.subject, n.likes, n.id_board, n.id_topic
					FROM {db_prefix}messages as n
					ORDER BY n.likes DESC
					LIMIT 1000
				) AS m
					INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
						AND b.id_board != {int:recycle_board}' : '') . ')
				WHERE m.likes > 0 AND {query_see_board}' . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . '
				ORDER BY m.likes DESC
				LIMIT 10',
				[
					'recycle_board' => Config::$modSettings['recycle_board'],
					'is_approved' => 1,
				],
			);

			while ($row_liked_message = Db::$db->fetch_assoc($liked_messages)) {
				Lang::censorText($row_liked_message['subject']);

				Utils::$context['stats_blocks']['liked_messages'][] = [
					'id' => $row_liked_message['id_topic'],
					'subject' => $row_liked_message['subject'],
					'num' => $row_liked_message['likes'],
					'href' => Config::$scripturl . '?msg=' . $row_liked_message['id_msg'],
					'link' => '<a href="' . Config::$scripturl . '?msg=' . $row_liked_message['id_msg'] . '">' . $row_liked_message['subject'] . '</a>',
				];

				if ($max_liked_message < $row_liked_message['likes']) {
					$max_liked_message = $row_liked_message['likes'];
				}
			}
			Db::$db->free_result($liked_messages);

			foreach (Utils::$context['stats_blocks']['liked_messages'] as $i => $liked_messages) {
				Utils::$context['stats_blocks']['liked_messages'][$i]['percent'] = round(($liked_messages['num'] * 100) / $max_liked_message);
			}

			// Liked users top 10.
			Utils::$context['stats_blocks']['liked_users'] = [];
			$max_liked_users = 1;
			$liked_users = Db::$db->query(
				'',
				'SELECT m.id_member AS liked_user, COUNT(l.content_id) AS count, mem.real_name
				FROM {db_prefix}user_likes AS l
					INNER JOIN {db_prefix}messages AS m ON (l.content_id = m.id_msg)
					INNER JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
				WHERE content_type = {literal:msg}
					AND m.id_member > {int:zero}
				GROUP BY m.id_member, mem.real_name
				ORDER BY count DESC
				LIMIT 10',
				[
					'no_posts' => 0,
					'zero' => 0,
				],
			);

			while ($row_liked_users = Db::$db->fetch_assoc($liked_users)) {
				Utils::$context['stats_blocks']['liked_users'][] = [
					'id' => $row_liked_users['liked_user'],
					'num' => $row_liked_users['count'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row_liked_users['liked_user'],
					'name' => $row_liked_users['real_name'],
					'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row_liked_users['liked_user'] . '">' . $row_liked_users['real_name'] . '</a>',
				];

				if ($max_liked_users < $row_liked_users['count']) {
					$max_liked_users = $row_liked_users['count'];
				}
			}

			Db::$db->free_result($liked_users);

			foreach (Utils::$context['stats_blocks']['liked_users'] as $i => $liked_users) {
				Utils::$context['stats_blocks']['liked_users'][$i]['percent'] = round(($liked_users['num'] * 100) / $max_liked_users);
			}
		}

		// Activity by month.
		$months_result = Db::$db->query(
			'',
			'SELECT
				YEAR(date) AS stats_year, MONTH(date) AS stats_month, SUM(hits) AS hits, SUM(registers) AS registers, SUM(topics) AS topics, SUM(posts) AS posts, MAX(most_on) AS most_on, COUNT(*) AS num_days
			FROM {db_prefix}log_activity
			GROUP BY stats_year, stats_month',
			[],
		);

		Utils::$context['yearly'] = [];

		while ($row_months = Db::$db->fetch_assoc($months_result)) {
			$ID_MONTH = $row_months['stats_year'] . sprintf('%02d', $row_months['stats_month']);
			$expanded = !empty($_SESSION['expanded_stats'][$row_months['stats_year']]) && in_array($row_months['stats_month'], $_SESSION['expanded_stats'][$row_months['stats_year']]);

			if (!isset(Utils::$context['yearly'][$row_months['stats_year']])) {
				Utils::$context['yearly'][$row_months['stats_year']] = [
					'year' => $row_months['stats_year'],
					'new_topics' => 0,
					'new_posts' => 0,
					'new_members' => 0,
					'most_members_online' => 0,
					'hits' => 0,
					'num_months' => 0,
					'months' => [],
					'expanded' => false,
					'current_year' => $row_months['stats_year'] == date('Y'),
				];
			}

			Utils::$context['yearly'][$row_months['stats_year']]['months'][(int) $row_months['stats_month']] = [
				'id' => $ID_MONTH,
				'date' => [
					'month' => sprintf('%02d', $row_months['stats_month']),
					'year' => $row_months['stats_year'],
				],
				'href' => Config::$scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $ID_MONTH . '#m' . $ID_MONTH,
				'link' => '<a href="' . Config::$scripturl . '?action=stats;' . ($expanded ? 'collapse' : 'expand') . '=' . $ID_MONTH . '#m' . $ID_MONTH . '">' . Lang::$txt['months_titles'][(int) $row_months['stats_month']] . ' ' . $row_months['stats_year'] . '</a>',
				'month' => Lang::$txt['months_titles'][(int) $row_months['stats_month']],
				'year' => $row_months['stats_year'],
				'new_topics' => Lang::numberFormat($row_months['topics']),
				'new_posts' => Lang::numberFormat($row_months['posts']),
				'new_members' => Lang::numberFormat($row_months['registers']),
				'most_members_online' => Lang::numberFormat($row_months['most_on']),
				'hits' => Lang::numberFormat($row_months['hits']),
				'num_days' => $row_months['num_days'],
				'days' => [],
				'expanded' => $expanded,
			];

			Utils::$context['yearly'][$row_months['stats_year']]['new_topics'] += $row_months['topics'];
			Utils::$context['yearly'][$row_months['stats_year']]['new_posts'] += $row_months['posts'];
			Utils::$context['yearly'][$row_months['stats_year']]['new_members'] += $row_months['registers'];
			Utils::$context['yearly'][$row_months['stats_year']]['hits'] += $row_months['hits'];
			Utils::$context['yearly'][$row_months['stats_year']]['num_months']++;
			Utils::$context['yearly'][$row_months['stats_year']]['expanded'] |= $expanded;
			Utils::$context['yearly'][$row_months['stats_year']]['most_members_online'] = max(Utils::$context['yearly'][$row_months['stats_year']]['most_members_online'], $row_months['most_on']);
		}

		krsort(Utils::$context['yearly']);

		Utils::$context['collapsed_years'] = [];

		foreach (Utils::$context['yearly'] as $year => $data) {
			// This gets rid of the filesort on the query ;).
			krsort(Utils::$context['yearly'][$year]['months']);

			Utils::$context['yearly'][$year]['new_topics'] = Lang::numberFormat($data['new_topics']);
			Utils::$context['yearly'][$year]['new_posts'] = Lang::numberFormat($data['new_posts']);
			Utils::$context['yearly'][$year]['new_members'] = Lang::numberFormat($data['new_members']);
			Utils::$context['yearly'][$year]['most_members_online'] = Lang::numberFormat($data['most_members_online']);
			Utils::$context['yearly'][$year]['hits'] = Lang::numberFormat($data['hits']);

			// Keep a list of collapsed years.
			if (!$data['expanded'] && !$data['current_year']) {
				Utils::$context['collapsed_years'][] = $year;
			}
		}

		// Custom stats (just add a template_layer to add it to the template!)
		IntegrationHook::call('integrate_forum_stats');

		if (empty($_SESSION['expanded_stats'])) {
			return;
		}

		$condition_text = [];
		$condition_params = [];

		foreach ($_SESSION['expanded_stats'] as $year => $months) {
			if (!empty($months)) {
				$condition_text[] = 'YEAR(date) = {int:year_' . $year . '} AND MONTH(date) IN ({array_int:months_' . $year . '})';
				$condition_params['year_' . $year] = $year;
				$condition_params['months_' . $year] = $months;
			}
		}

		// No daily stats to even look at?
		if (empty($condition_text)) {
			return;
		}

		$this->getDailyStats(implode(' OR ', $condition_text), $condition_params);
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
	}

	/**
	 * Loads the statistics on a daily basis in Utils::$context.
	 * called by DisplayStats().
	 *
	 * @param string $condition_string An SQL condition string
	 * @param array $condition_parameters Parameters for $condition_string
	 */
	protected function getDailyStats($condition_string, $condition_parameters = [])
	{
		// Activity by day.
		$days_result = Db::$db->query(
			'',
			'SELECT YEAR(date) AS stats_year, MONTH(date) AS stats_month, DAYOFMONTH(date) AS stats_day, topics, posts, registers, most_on, hits
			FROM {db_prefix}log_activity
			WHERE ' . $condition_string . '
			ORDER BY stats_day ASC',
			$condition_parameters,
		);

		while ($row_days = Db::$db->fetch_assoc($days_result)) {
			Utils::$context['yearly'][$row_days['stats_year']]['months'][(int) $row_days['stats_month']]['days'][] = [
				'day' => sprintf('%02d', $row_days['stats_day']),
				'month' => sprintf('%02d', $row_days['stats_month']),
				'year' => $row_days['stats_year'],
				'new_topics' => Lang::numberFormat($row_days['topics']),
				'new_posts' => Lang::numberFormat($row_days['posts']),
				'new_members' => Lang::numberFormat($row_days['registers']),
				'most_members_online' => Lang::numberFormat($row_days['most_on']),
				'hits' => Lang::numberFormat($row_days['hits']),
			];
		}
		Db::$db->free_result($days_result);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Stats::exportStatic')) {
	Stats::exportStatic();
}

?>