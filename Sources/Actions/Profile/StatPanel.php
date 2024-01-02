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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class StatPanel implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'statPanel' => 'statPanel',
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
	 * Does the job.
	 */
	public function execute(): void
	{
		Utils::$context['page_title'] = Lang::$txt['statPanel_showStats'] . ' ' . Profile::$member->name;

		// Menu tab
		Menu::$loaded['profile']->tab_data = [
			'title' => Lang::$txt['statPanel_generalStats'] . ' - ' . Profile::$member->name,
			'icon' => 'stats_info.png',
		];

		// Is the load average too high to allow searching just now?
		if (!empty(Utils::$context['load_average']) && !empty(Config::$modSettings['loadavg_userstats']) && Utils::$context['load_average'] >= Config::$modSettings['loadavg_userstats']) {
			ErrorHandler::fatalLang('loadavg_userstats_disabled', false);
		}

		// General user statistics.
		Utils::$context['time_logged_in'] = Profile::$member->time_logged_in;

		Utils::$context['num_posts'] = Lang::numberFormat(Profile::$member->posts);

		// Number of topics started and Number polls started
		$result = Db::$db->query(
			'',
			'SELECT COUNT(*), COUNT( CASE WHEN id_poll != {int:no_poll} THEN 1 ELSE NULL END )
			FROM {db_prefix}topics
			WHERE id_member_started = {int:current_member}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND id_board != {int:recycle_board}' : ''),
			[
				'current_member' => Profile::$member->id,
				'recycle_board' => Config::$modSettings['recycle_board'],
				'no_poll' => 0,
			],
		);
		list(Utils::$context['num_topics'], Utils::$context['num_polls']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// Number polls voted in.
		$result = Db::$db->query(
			'distinct_poll_votes',
			'SELECT COUNT(DISTINCT id_poll)
			FROM {db_prefix}log_polls
			WHERE id_member = {int:current_member}',
			[
				'current_member' => Profile::$member->id,
			],
		);
		list(Utils::$context['num_votes']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// Format the numbers...
		Utils::$context['num_topics'] = Lang::numberFormat(Utils::$context['num_topics']);
		Utils::$context['num_polls'] = Lang::numberFormat(Utils::$context['num_polls']);
		Utils::$context['num_votes'] = Lang::numberFormat(Utils::$context['num_votes']);

		// Grab the boards this member posted in most often.
		Utils::$context['popular_boards'] = [];

		$result = Db::$db->query(
			'',
			'SELECT
				b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_member = {int:current_member}
				AND b.count_posts = {int:count_enabled}
				AND {query_see_board}
			GROUP BY b.id_board
			ORDER BY message_count DESC
			LIMIT 10',
			[
				'current_member' => Profile::$member->id,
				'count_enabled' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			Utils::$context['popular_boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'posts' => $row['message_count'],
				'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'posts_percent' => Profile::$member->posts == 0 ? 0 : ($row['message_count'] * 100) / Profile::$member->posts,
				'total_posts' => $row['num_posts'],
				'total_posts_member' => Profile::$member->posts,
			];
		}
		Db::$db->free_result($result);

		// Now get the 10 boards this user has most often participated in.
		Utils::$context['board_activity'] = [];

		$result = Db::$db->query(
			'profile_board_stats',
			'SELECT
				b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
				CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_member = {int:current_member}
				AND {query_see_board}
			GROUP BY b.id_board, b.num_posts
			ORDER BY percentage DESC
			LIMIT 10',
			[
				'current_member' => Profile::$member->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			Utils::$context['board_activity'][$row['id_board']] = [
				'id' => $row['id_board'],
				'posts' => $row['message_count'],
				'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'percent' => Lang::numberFormat((float) $row['percentage'], 2),
				'posts_percent' => (float) $row['percentage'],
				'total_posts' => $row['num_posts'],
			];
		}
		Db::$db->free_result($result);

		// Posting activity by time.
		$maxPosts = $realPosts = 0;
		Utils::$context['posts_by_time'] = [];

		$result = Db::$db->query(
			'user_activity_by_time',
			'SELECT
				HOUR(FROM_UNIXTIME(poster_time + {int:time_offset})) AS hour,
				COUNT(*) AS post_count
			FROM (
				SELECT poster_time, id_msg
				FROM {db_prefix}messages WHERE id_member = {int:current_member}
				ORDER BY id_msg DESC
				LIMIT {int:max_messages}
			) a
			GROUP BY hour',
			[
				'current_member' => Profile::$member->id,
				'time_offset' => User::$me->time_offset * 3600,
				'max_messages' => 1001,
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			// Cast as an integer to remove the leading 0.
			$row['hour'] = (int) $row['hour'];

			$maxPosts = max($row['post_count'], $maxPosts);
			$realPosts += $row['post_count'];

			Utils::$context['posts_by_time'][$row['hour']] = [
				'hour' => $row['hour'],
				'hour_format' => stripos(User::$me->time_format, '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
				'posts' => $row['post_count'],
				'posts_percent' => 0,
				'is_last' => $row['hour'] == 23,
			];
		}
		Db::$db->free_result($result);

		if ($maxPosts > 0) {
			for ($hour = 0; $hour < 24; $hour++) {
				if (!isset(Utils::$context['posts_by_time'][$hour])) {
					Utils::$context['posts_by_time'][$hour] = [
						'hour' => $hour,
						'hour_format' => stripos(User::$me->time_format, '%p') === false ? $hour : date('g a', mktime($hour)),
						'posts' => 0,
						'posts_percent' => 0,
						'relative_percent' => 0,
						'is_last' => $hour == 23,
					];
				} else {
					Utils::$context['posts_by_time'][$hour]['posts_percent'] = round((Utils::$context['posts_by_time'][$hour]['posts'] * 100) / $realPosts);
					Utils::$context['posts_by_time'][$hour]['relative_percent'] = round((Utils::$context['posts_by_time'][$hour]['posts'] * 100) / $maxPosts);
				}
			}
		}

		// Put it in the right order.
		ksort(Utils::$context['posts_by_time']);

		/*
		 * Adding new entries:
		 * 'key' => array(
		 * 		'text' => string, // The text that will be shown next to the entry.
		 * 		'url' => string, // OPTIONAL: The entry will be a url
		 * ),
		 *
		 * 'key' will be used to look up the language string as Lang::$txt['statPanel_' . $key].
		 * Make sure to add a new entry when writing your mod!
		 */
		Utils::$context['text_stats'] = [
			'total_time_online' => [
				'text' => Utils::$context['time_logged_in'],
			],
			'total_posts' => [
				'text' => Utils::$context['num_posts'] . ' ' . Lang::$txt['statPanel_posts'],
				'url' => Config::$scripturl . '?action=profile;area=showposts;sa=messages;u=' . Profile::$member->id,
			],
			'total_topics' => [
				'text' => Utils::$context['num_topics'] . ' ' . Lang::$txt['statPanel_topics'],
				'url' => Config::$scripturl . '?action=profile;area=showposts;sa=topics;u=' . Profile::$member->id,
			],
			'users_polls' => [
				'text' => Utils::$context['num_polls'] . ' ' . Lang::$txt['statPanel_polls'],
			],
			'users_votes' => [
				'text' => Utils::$context['num_votes'] . ' ' . Lang::$txt['statPanel_votes'],
			],
		];

		// Custom stats (just add a template_layer to add it to the template!)
		IntegrationHook::call('integrate_profile_stats', [Profile::$member->id, &Utils::$context['text_stats']]);
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

	/**
	 * Backward compatibility wrapper.
	 */
	public static function statPanel(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\StatPanel::exportStatic')) {
	StatPanel::exportStatic();
}

?>