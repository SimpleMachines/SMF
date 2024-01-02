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

namespace SMF;

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * Performs all the necessary setup and security checks for SSI access, and
 * provides a number of useful functions that allow external scripts to access
 * SMF data.
 *
 * External scripts can affect various aspects of SSI behaviour using global
 * variables that are named in this class's $ssi_globals property. For more
 * information on this, see the DocBlock comments below.
 */
class ServerSideIncludes
{
	use BackwardCompatibility;

	/******************************
	 * Properties for internal use.
	 ******************************/

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'shutdown' => 'ssi_shutdown',
			'version' => 'ssi_version',
			'fullVersion' => 'ssi_full_version',
			'softwareYear' => 'ssi_software_year',
			'copyright' => 'ssi_copyright',
			'welcome' => 'ssi_welcome',
			'menubar' => 'ssi_menubar',
			'logout' => 'ssi_logout',
			'recentPosts' => 'ssi_recentPosts',
			'fetchPosts' => 'ssi_fetchPosts',
			'queryPosts' => 'ssi_queryPosts',
			'recentTopics' => 'ssi_recentTopics',
			'topPoster' => 'ssi_topPoster',
			'topBoards' => 'ssi_topBoards',
			'topTopics' => 'ssi_topTopics',
			'topTopicsReplies' => 'ssi_topTopicsReplies',
			'topTopicsViews' => 'ssi_topTopicsViews',
			'latestMember' => 'ssi_latestMember',
			'randomMember' => 'ssi_randomMember',
			'fetchMember' => 'ssi_fetchMember',
			'fetchGroupMembers' => 'ssi_fetchGroupMembers',
			'queryMembers' => 'ssi_queryMembers',
			'boardStats' => 'ssi_boardStats',
			'whosOnline' => 'ssi_whosOnline',
			'logOnline' => 'ssi_logOnline',
			'login' => 'ssi_login',
			'topPoll' => 'ssi_topPoll',
			'recentPoll' => 'ssi_recentPoll',
			'showPoll' => 'ssi_showPoll',
			'pollVote' => 'ssi_pollVote',
			'quickSearch' => 'ssi_quickSearch',
			'news' => 'ssi_news',
			'todaysBirthdays' => 'ssi_todaysBirthdays',
			'todaysHolidays' => 'ssi_todaysHolidays',
			'todaysEvents' => 'ssi_todaysEvents',
			'todaysCalendar' => 'ssi_todaysCalendar',
			'boardNews' => 'ssi_boardNews',
			'recentEvents' => 'ssi_recentEvents',
			'checkPassword' => 'ssi_checkPassword',
			'recentAttachments' => 'ssi_recentAttachments',
		],
	];

	/**
	 * @var int
	 *
	 * Remembers the error_reporting level prior to SSI starting, so that we can
	 * restore it once we are done.
	 */
	protected $error_reporting;

	/**
	 * @var bool
	 *
	 * Whether SSI setup has been completed.
	 */
	protected static $setup_done = false;

	/******************************************************************
	 * Properties that allow external scripts to control SSI behaviour.
	 ******************************************************************/

	/**
	 * @var array
	 *
	 * Names of various global variables that external scripts can declare in
	 * order to influence the behaviour of SSI.
	 */
	protected $ssi_globals = [
		'ssi_on_error_method',
		'ssi_maintenance_off',
		'ssi_theme',
		'ssi_layers',
		'ssi_gzip',
		'ssi_ban',
		'ssi_guest_access',
	];

	/**
	 * @var bool|string
	 *
	 * Local copy of the global $ssi_on_error_method variable.
	 *
	 * Set this to one of three values depending on what you want to happen in
	 * the case of a fatal error:
	 *
	 *  false:   Default. Will just load the error sub template and die without
	 *           putting any theme layers around it.
	 *
	 *  true:    Will load the error sub template AND put the SMF layers around
	 *           it. (Not useful if on total custom pages.)
	 *
	 *  string:  Name of a callback function to call in the event of an error to
	 *           allow you to define your own methods. Will die after function
	 *           returns.
	 */
	public static $on_error_method = false;

	/**
	 * @var bool
	 *
	 * Local copy of the global $ssi_maintenance_off variable.
	 *
	 * If true, allows SSI access even when the forum is in maintenance mode.
	 */
	public $maintenance_off = false;

	/**
	 * @var bool
	 *
	 * Local copy of the global $ssi_guest_access variable.
	 *
	 * If true, allows guests to see SSI data even when they cannot access the
	 * forum itself.
	 */
	public $guest_access = false;

	/**
	 * @var bool
	 *
	 * Local copy of the global $ssi_ban variable.
	 *
	 * Whether to check that the viewing user is not banned.
	 */
	public $ban = false;

	/**
	 * @var int
	 *
	 * Local copy of the global $ssi_theme variable.
	 *
	 * ID number of a theme to use for SSI functions.
	 */
	public $theme = 0;

	/**
	 * @var array
	 *
	 * Local copy of the global $ssi_layers variable.
	 *
	 * An array of template layers in which to wrap the content. If not set, the
	 * theme's defaults will be used.
	 */
	public $layers;

	/**
	 * @var bool
	 *
	 * Local copy of the global $ssi_gzip variable.
	 *
	 * Whether to compress output using the GZip algorithm.
	 */
	public $gzip;

	/****************************************************************
	 * Static methods that allow external scripts to access SMF data.
	 * These are the interesting parts of this class.
	 ****************************************************************/

	/**
	 * This shuts down the SSI and shows the footer.
	 *
	 * Alias: ssi_shutdown()
	 *
	 */
	public static function shutdown()
	{
		if (!self::$setup_done) {
			new self();
		}

		if (!isset($_GET['ssi_function']) || $_GET['ssi_function'] != 'shutdown') {
			Theme::template_footer();
		}
	}

	/**
	 * Show the SMF version.
	 *
	 * Alias: ssi_version()
	 *
	 * @param string $output_method If 'echo', displays the version, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the version
	 */
	public static function version($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($output_method == 'echo') {
			echo SMF_VERSION;
		} else {
			return SMF_VERSION;
		}
	}

	/**
	 * Show the full SMF version string.
	 *
	 * Alias: ssi_full_version()
	 *
	 * @param string $output_method If 'echo', displays the full version string, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the version string
	 */
	public static function fullVersion($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($output_method == 'echo') {
			echo SMF_FULL_VERSION;
		} else {
			return SMF_FULL_VERSION;
		}
	}

	/**
	 * Show the SMF software year.
	 *
	 * Alias: ssi_software_year()
	 *
	 * @param string $output_method If 'echo', displays the software year, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the software year
	 */
	public static function softwareYear($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($output_method == 'echo') {
			echo SMF_SOFTWARE_YEAR;
		} else {
			return SMF_SOFTWARE_YEAR;
		}
	}

	/**
	 * Show the forum copyright. Only used in our ssi_examples files.
	 *
	 * Alias: ssi_copyright()
	 *
	 * @param string $output_method If 'echo', displays the forum copyright, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the copyright string
	 */
	public static function copyright($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($output_method == 'echo') {
			printf(Lang::$forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, Config::$scripturl);
		} else {
			return sprintf(Lang::$forum_copyright, SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, Config::$scripturl);
		}
	}

	/**
	 * Display a welcome message, like: Hey, User, you have 0 messages, 0 are new.
	 *
	 * Alias: ssi_welcome()
	 *
	 * @param string $output_method The output method. If 'echo', will display everything. Otherwise returns an array of user info.
	 * @return void|array Displays a welcome message or returns an array of user data depending on output_method.
	 */
	public static function welcome($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($output_method == 'echo') {
			if (User::$me->is_guest) {
				echo sprintf(Lang::$txt[Utils::$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest'], Utils::$context['forum_name_html_safe'], Config::$scripturl . '?action=login', 'return reqOverlayDiv(this.href, ' . Utils::JavaScriptEscape(Lang::$txt['login']) . ');', Config::$scripturl . '?action=signup');
			} else {
				echo Lang::$txt['hello_member'], ' <strong>', User::$me->name, '</strong>', User::$me->allowedTo('pm_read') ? ', ' . (empty(User::$me->messages) ? Lang::$txt['msg_alert_no_messages'] : ((User::$me->messages == 1 ? sprintf(Lang::$txt['msg_alert_one_message'], Config::$scripturl . '?action=pm') : sprintf(Lang::$txt['msg_alert_many_message'], Config::$scripturl . '?action=pm', User::$me->messages)) . ', ' . (User::$me->unread_messages == 1 ? Lang::$txt['msg_alert_one_new'] : sprintf(Lang::$txt['msg_alert_many_new'], User::$me->unread_messages)))) : '';
			}
		}
		// Don't echo... then do what?!
		else {
			return User::$me;
		}
	}

	/**
	 * Display a menu bar, like is displayed at the top of the forum.
	 *
	 * Alias: ssi_menubar()
	 *
	 * @param string $output_method The output method. If 'echo', will display the menu, otherwise returns an array of menu data.
	 * @return void|array Displays the menu or returns an array of menu data depending on output_method.
	 */
	public static function menubar($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($output_method == 'echo') {
			template_menu();
		}
		// What else could this do?
		else {
			return Utils::$context['menu_buttons'];
		}
	}

	/**
	 * Show a logout link.
	 *
	 * Alias: ssi_logout()
	 *
	 * @param string $redirect_to A URL to redirect the user to after they log out.
	 * @param string $output_method The output method. If 'echo', shows a logout link, otherwise returns the HTML for it.
	 * @return void|string Displays a logout link or returns its HTML depending on output_method.
	 */
	public static function logout($redirect_to = '', $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($redirect_to != '') {
			$_SESSION['logout_url'] = $redirect_to;
		}

		// Guests can't log out.
		if (User::$me->is_guest) {
			return false;
		}

		$link = '<a href="' . Config::$scripturl . '?action=logout;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '">' . Lang::$txt['logout'] . '</a>';

		if ($output_method == 'echo') {
			echo $link;
		} else {
			return $link;
		}
	}

	/**
	 * Recent post list:   [board] Subject by Poster    Date
	 *
	 * Alias: ssi_recentPosts()
	 *
	 * @param int $num_recent How many recent posts to display
	 * @param null|array $exclude_boards If set, doesn't show posts from the specified boards
	 * @param null|array $include_boards If set, only includes posts from the specified boards
	 * @param string $output_method The output method. If 'echo', displays the posts, otherwise returns an array of information about them.
	 * @param bool $limit_body Whether or not to only show the first 384 characters of each post
	 * @return void|array Displays a list of recent posts or returns an array of information about them depending on output_method.
	 */
	public static function recentPosts($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo', $limit_body = true)
	{
		if (!self::$setup_done) {
			new self();
		}

		// Excluding certain boards...
		if ($exclude_boards === null && !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board'])) {
			$exclude_boards = [Config::$modSettings['recycle_board']];
		} else {
			$exclude_boards = empty($exclude_boards) ? [] : (is_array($exclude_boards) ? $exclude_boards : [$exclude_boards]);
		}

		// What about including certain boards - note we do some protection here as pre-2.0 didn't have this parameter.
		if (is_array($include_boards) || (int) $include_boards === $include_boards) {
			$include_boards = is_array($include_boards) ? $include_boards : [$include_boards];
		} elseif ($include_boards != null) {
			$include_boards = [];
		}

		// Let's restrict the query boys (and girls)
		$query_where = '
			m.id_msg >= {int:min_message_id}
			' . (empty($exclude_boards) ? '' : '
			AND b.id_board NOT IN ({array_int:exclude_boards})') . '
			' . ($include_boards === null ? '' : '
			AND b.id_board IN ({array_int:include_boards})') . '
			AND {query_wanna_see_board}' . (Config::$modSettings['postmod_active'] ? '
			AND m.approved = {int:is_approved}' : '');

		$query_where_params = [
			'is_approved' => 1,
			'include_boards' => $include_boards === null ? '' : $include_boards,
			'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
			'min_message_id' => Config::$modSettings['maxMsgID'] - (!empty(Utils::$context['min_message_posts']) ? Utils::$context['min_message_posts'] : 25) * min($num_recent, 5),
		];

		// Past to this simpleton of a function...
		return self::queryPosts($query_where, $query_where_params, $num_recent, 'm.id_msg DESC', $output_method, $limit_body);
	}

	/**
	 * Fetches one or more posts by ID.
	 *
	 * Alias: ssi_fetchPosts()
	 *
	 * @param array $post_ids An array containing the IDs of the posts to show
	 * @param bool $override_permissions Whether to ignore permissions. If true, will show posts even if the user doesn't have permission to see them.
	 * @param string $output_method The output method. If 'echo', displays the posts, otherwise returns an array of info about them
	 * @return void|array Displays the specified posts or returns an array of info about them, depending on output_method.
	 */
	public static function fetchPosts($post_ids = [], $override_permissions = false, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (empty($post_ids)) {
			return;
		}

		// Allow the user to request more than one - why not?
		$post_ids = is_array($post_ids) ? $post_ids : [$post_ids];

		// Restrict the posts required...
		$query_where = '
			m.id_msg IN ({array_int:message_list})' . ($override_permissions ? '' : '
				AND {query_wanna_see_board}') . (Config::$modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '');
		$query_where_params = [
			'message_list' => $post_ids,
			'is_approved' => 1,
		];

		// Then make the query and dump the data.
		return self::queryPosts($query_where, $query_where_params, '', 'm.id_msg DESC', $output_method, false, $override_permissions);
	}

	/**
	 * This handles actually pulling post info. Called from other functions to eliminate duplication.
	 *
	 * Alias: ssi_queryPosts()
	 *
	 * @param string $query_where The WHERE clause for the query
	 * @param array $query_where_params An array of parameters for the WHERE clause
	 * @param int $query_limit The maximum number of rows to return
	 * @param string $query_order The ORDER BY clause for the query
	 * @param string $output_method The output method. If 'echo', displays the posts, otherwise returns an array of info about them.
	 * @param bool $limit_body If true, will only show the first 384 characters of the post rather than all of it
	 * @param bool|false $override_permissions Whether or not to ignore permissions. If true, will show all posts regardless of whether the user can actually see them
	 * @return void|array Displays the posts or returns an array of info about them, depending on output_method
	 */
	public static function queryPosts($query_where = '', $query_where_params = [], $query_limit = 10, $query_order = 'm.id_msg DESC', $output_method = 'echo', $limit_body = false, $override_permissions = false)
	{
		if (!self::$setup_done) {
			new self();
		}

		if (!empty(Config::$modSettings['enable_likes'])) {
			Utils::$context['can_like'] = User::$me->allowedTo('likes_like');
		}

		// Find all the posts. Newer ones will have higher IDs.
		$request = Db::$db->query(
			'substring',
			'SELECT
				m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg, m.id_board, m.likes, b.name AS board_name,
				COALESCE(mem.real_name, m.poster_name) AS poster_name, ' . (User::$me->is_guest ? '1 AS is_read, 0 AS new_from' : '
				COALESCE(lt.id_msg, lmr.id_msg, 0) >= m.id_msg_modified AS is_read,
				COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from') . ', ' . ($limit_body ? 'SUBSTRING(m.body, 1, 384) AS body' : 'm.body') . ', m.smileys_enabled
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)' . (Config::$modSettings['postmod_active'] ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' : '') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!User::$me->is_guest ? '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:current_member})' : '') . '
			WHERE 1=1 ' . ($override_permissions ? '' : '
				AND {query_wanna_see_board}') . (Config::$modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}' : '') . '
			' . (empty($query_where) ? '' : 'AND ' . $query_where) . '
			ORDER BY ' . $query_order . '
			' . ($query_limit == '' ? '' : 'LIMIT ' . $query_limit),
			array_merge($query_where_params, [
				'current_member' => User::$me->id,
				'is_approved' => 1,
			]),
		);
		$posts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$topic = new Topic($row['id_topic'], [
				'id_board' => $row['id_board'],
				'id_first_msg' => $row['id_msg'],
			]);

			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			// Censor it!
			Lang::censorText($row['subject']);
			Lang::censorText($row['body']);

			$preview = strip_tags(strtr($row['body'], ['<br>' => '&#10;']));

			// Build the array.
			$posts[$row['id_msg']] = [
				'id' => $row['id_msg'],
				'board' => [
					'id' => $row['id_board'],
					'name' => $row['board_name'],
					'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
					'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>',
				],
				'topic' => $row['id_topic'],
				'poster' => [
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'href' => empty($row['id_member']) ? '' : Config::$scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
				],
				'subject' => $row['subject'],
				'short_subject' => Utils::shorten($row['subject'], 25),
				'preview' => Utils::shorten($preview, 128),
				'body' => $row['body'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>',
				'new' => !empty($row['is_read']),
				'is_new' => empty($row['is_read']),
				'new_from' => $row['new_from'],
			];

			// Get the likes for each message.
			if (!empty(Config::$modSettings['enable_likes'])) {
				$posts[$row['id_msg']]['likes'] = [
					'count' => $row['likes'],
					'you' => in_array($row['id_msg'], $topic->getLikedMsgs()),
					'can_like' => !User::$me->is_guest && $row['id_member'] != User::$me->id && !empty(Utils::$context['can_like']),
				];
			}
		}
		Db::$db->free_result($request);

		// If mods want to do something with this list of posts, let them do that now.
		IntegrationHook::call('integrate_ssi_queryPosts', [&$posts]);

		// Just return it.
		if ($output_method != 'echo' || empty($posts)) {
			return $posts;
		}

		echo '
			<table style="border: none" class="ssi_table">';

		foreach ($posts as $post) {
			echo '
				<tr>
					<td style="text-align: right; vertical-align: top; white-space: nowrap">
						[', $post['board']['link'], ']
					</td>
					<td style="vertical-align: top">
						<a href="', $post['href'], '">', $post['subject'], '</a>
						', Lang::$txt['by'], ' ', $post['poster']['link'], '
						', $post['is_new'] ? '<a href="' . Config::$scripturl . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . ';topicseen#new" rel="nofollow" class="new_posts">' . Lang::$txt['new'] . '</a>' : '', '
					</td>
					<td style="text-align: right; white-space: nowrap">
						', $post['time'], '
					</td>
				</tr>';
		}
		echo '
			</table>';
	}

	/**
	 * Recent topic list:   [board] Subject by Poster   Date
	 *
	 * Alias: ssi_recentTopics()
	 *
	 * @param int $num_recent How many recent topics to show
	 * @param null|array $exclude_boards If set, exclude topics from the specified board(s)
	 * @param null|array $include_boards If set, only include topics from the specified board(s)
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them
	 * @return void|array Either displays a list of topics or returns an array of info about them, depending on output_method.
	 */
	public static function recentTopics($num_recent = 8, $exclude_boards = null, $include_boards = null, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($exclude_boards === null && !empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0) {
			$exclude_boards = [Config::$modSettings['recycle_board']];
		} else {
			$exclude_boards = empty($exclude_boards) ? [] : (is_array($exclude_boards) ? $exclude_boards : [$exclude_boards]);
		}

		// Only some boards?.
		if (is_array($include_boards) || (int) $include_boards === $include_boards) {
			$include_boards = is_array($include_boards) ? $include_boards : [$include_boards];
		} elseif ($include_boards != null) {
			$output_method = $include_boards;
			$include_boards = [];
		}

		$icon_sources = [];

		foreach (Utils::$context['stable_icons'] as $icon) {
			$icon_sources[$icon] = 'images_url';
		}

		// Find all the posts in distinct topics.  Newer ones will have higher IDs.
		$request = Db::$db->query(
			'substring',
			'SELECT
				t.id_topic, b.id_board, b.name AS board_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_last_msg >= {int:min_message_id}' . (empty($exclude_boards) ? '' : '
				AND b.id_board NOT IN ({array_int:exclude_boards})') . '' . (empty($include_boards) ? '' : '
				AND b.id_board IN ({array_int:include_boards})') . '
				AND {query_wanna_see_board}' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}
				AND ml.approved = {int:is_approved}' : '') . '
			ORDER BY t.id_last_msg DESC
			LIMIT ' . $num_recent,
			[
				'include_boards' => empty($include_boards) ? '' : $include_boards,
				'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
				'min_message_id' => Config::$modSettings['maxMsgID'] - (!empty(Utils::$context['min_message_topics']) ? Utils::$context['min_message_topics'] : 35) * min($num_recent, 5),
				'is_approved' => 1,
			],
		);
		$topics = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$topics[$row['id_topic']] = $row;
		}
		Db::$db->free_result($request);

		// Did we find anything? If not, bail.
		if (empty($topics)) {
			return [];
		}

		$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;

		// Find all the posts in distinct topics.  Newer ones will have higher IDs.
		$request = Db::$db->query(
			'substring',
			'SELECT
				ml.poster_time, mf.subject, mf.id_topic, ml.id_member, ml.id_msg, t.num_replies, t.num_views, mg.online_color, t.id_last_msg,
				COALESCE(mem.real_name, ml.poster_name) AS poster_name, ' . (User::$me->is_guest ? '1 AS is_read, 0 AS new_from' : '
				COALESCE(lt.id_msg, lmr.id_msg, 0) >= ml.id_msg_modified AS is_read,
				COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from') . ', SUBSTRING(ml.body, 1, 384) AS body, ml.smileys_enabled, ml.icon
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)' . (!User::$me->is_guest ? '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' : '') . '
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY t.id_last_msg DESC',
			[
				'current_member' => User::$me->id,
				'topic_list' => array_keys($topics),
			],
		);
		$posts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['body'] = strip_tags(strtr(BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']), ['<br>' => '&#10;']));

			if (Utils::entityStrlen($row['body']) > 128) {
				$row['body'] = Utils::entitySubstr($row['body'], 0, 128) . '...';
			}

			// Censor the subject.
			Lang::censorText($row['subject']);
			Lang::censorText($row['body']);

			// Recycled icon
			if (!empty($recycle_board) && $topics[$row['id_topic']]['id_board'] == $recycle_board) {
				$row['icon'] = 'recycled';
			}

			if (!empty(Config::$modSettings['messageIconChecks_enable']) && !isset($icon_sources[$row['icon']])) {
				$icon_sources[$row['icon']] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $row['icon'] . '.png') ? 'images_url' : 'default_images_url';
			} elseif (!isset($icon_sources[$row['icon']])) {
				$icon_sources[$row['icon']] = 'images_url';
			}

			// Build the array.
			$posts[] = [
				'board' => [
					'id' => $topics[$row['id_topic']]['id_board'],
					'name' => $topics[$row['id_topic']]['board_name'],
					'href' => Config::$scripturl . '?board=' . $topics[$row['id_topic']]['id_board'] . '.0',
					'link' => '<a href="' . Config::$scripturl . '?board=' . $topics[$row['id_topic']]['id_board'] . '.0">' . $topics[$row['id_topic']]['board_name'] . '</a>',
				],
				'topic' => $row['id_topic'],
				'poster' => [
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'href' => empty($row['id_member']) ? '' : Config::$scripturl . '?action=profile;u=' . $row['id_member'],
					'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
				],
				'subject' => $row['subject'],
				'replies' => $row['num_replies'],
				'views' => $row['num_views'],
				'short_subject' => Utils::shorten($row['subject'], 25),
				'preview' => $row['body'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#new" rel="nofollow">' . $row['subject'] . '</a>',
				// Retained for compatibility - is technically incorrect!
				'new' => !empty($row['is_read']),
				'is_new' => empty($row['is_read']),
				'new_from' => $row['new_from'],
				'icon' => '<img src="' . Theme::$current->settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.png" style="vertical-align:middle;" alt="' . $row['icon'] . '">',
			];
		}
		Db::$db->free_result($request);

		// If mods want to do something with this list of topics, let them do that now.
		IntegrationHook::call('integrate_ssi_recentTopics', [&$posts]);

		// Just return it.
		if ($output_method != 'echo' || empty($posts)) {
			return $posts;
		}

		echo '
			<table style="border: none" class="ssi_table">';

		foreach ($posts as $post) {
			echo '
				<tr>
					<td style="text-align: right; vertical-align: top; white-space: nowrap">
						[', $post['board']['link'], ']
					</td>
					<td style="vertical-align: top">
						<a href="', $post['href'], '">', $post['subject'], '</a>
						', Lang::$txt['by'], ' ', $post['poster']['link'], '
						', !$post['is_new'] ? '' : '<a href="' . Config::$scripturl . '?topic=' . $post['topic'] . '.msg' . $post['new_from'] . ';topicseen#new" rel="nofollow" class="new_posts">' . Lang::$txt['new'] . '</a>', '
					</td>
					<td style="text-align: right; white-space: nowrap">
						', $post['time'], '
					</td>
				</tr>';
		}
		echo '
			</table>';
	}

	/**
	 * Shows a list of top posters
	 *
	 * Alias: ssi_topPoster()
	 *
	 * @param int $topNumber How many top posters to list
	 * @param string $output_method The output method. If 'echo', will display a list of users, otherwise returns an array of info about them.
	 * @return void|array Either displays a list of users or returns an array of info about them, depending on output_method.
	 */
	public static function topPoster($topNumber = 1, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		// Find the latest poster.
		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name, posts
			FROM {db_prefix}members
			ORDER BY posts DESC
			LIMIT ' . $topNumber,
			[
			],
		);
		$return = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$return[] = [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'posts' => $row['posts'],
			];
		}
		Db::$db->free_result($request);

		// If mods want to do something with this list of members, let them do that now.
		IntegrationHook::call('integrate_ssi_topPoster', [&$return]);

		// Just return all the top posters.
		if ($output_method != 'echo') {
			return $return;
		}

		// Make a quick array to list the links in.
		$temp_array = [];

		foreach ($return as $member) {
			$temp_array[] = $member['link'];
		}

		echo implode(', ', $temp_array);
	}

	/**
	 * Shows a list of top boards based on activity
	 *
	 * Alias: ssi_topBoards()
	 *
	 * @param int $num_top How many boards to display
	 * @param string $output_method The output method. If 'echo', displays a list of boards, otherwise returns an array of info about them.
	 * @return void|array Displays a list of the top boards or returns an array of info about them, depending on output_method.
	 */
	public static function topBoards($num_top = 10, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		// Find boards with lots of posts.
		$request = Db::$db->query(
			'',
			'SELECT
				b.name, b.num_topics, b.num_posts, b.id_board,' . (!User::$me->is_guest ? ' 1 AS is_read' : '
				(COALESCE(lb.id_msg, 0) >= b.id_last_msg) AS is_read') . '
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
			WHERE {query_wanna_see_board}' . (!empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? '
				AND b.id_board != {int:recycle_board}' : '') . '
			ORDER BY b.num_posts DESC
			LIMIT ' . $num_top,
			[
				'current_member' => User::$me->id,
				'recycle_board' => !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : null,
			],
		);
		$boards = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$boards[] = [
				'id' => $row['id_board'],
				'num_posts' => $row['num_posts'],
				'num_topics' => $row['num_topics'],
				'name' => $row['name'],
				'new' => empty($row['is_read']),
				'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			];
		}
		Db::$db->free_result($request);

		// If mods want to do something with this list of boards, let them do that now.
		IntegrationHook::call('integrate_ssi_topBoards', [&$boards]);

		// If we shouldn't output or have nothing to output, just jump out.
		if ($output_method != 'echo' || empty($boards)) {
			return $boards;
		}

		echo '
			<table class="ssi_table">
				<tr>
					<th style="text-align: left">', Lang::$txt['board'], '</th>
					<th style="text-align: left">', Lang::$txt['board_topics'], '</th>
					<th style="text-align: left">', Lang::$txt['posts'], '</th>
				</tr>';

		foreach ($boards as $sBoard) {
			echo '
				<tr>
					<td>', $sBoard['link'], $sBoard['new'] ? ' <a href="' . $sBoard['href'] . '" class="new_posts">' . Lang::$txt['new'] . '</a>' : '', '</td>
					<td style="text-align: right">', Lang::numberFormat($sBoard['num_topics']), '</td>
					<td style="text-align: right">', Lang::numberFormat($sBoard['num_posts']), '</td>
				</tr>';
		}
		echo '
			</table>';
	}

	/**
	 * Shows a list of top topics based on views or replies
	 *
	 * Alias: ssi_topTopics()
	 *
	 * @param string $type Can be either replies or views
	 * @param int $num_topics How many topics to display
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them.
	 * @return void|array Either displays a list of topics or returns an array of info about them, depending on output_method.
	 */
	public static function topTopics($type = 'replies', $num_topics = 10, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (Config::$modSettings['totalMessages'] > 100000) {
			// @todo Why don't we use {query(_wanna)_see_board}?
			$request = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}topics
				WHERE num_' . ($type != 'replies' ? 'views' : 'replies') . ' != 0' . (Config::$modSettings['postmod_active'] ? '
					AND approved = {int:is_approved}' : '') . '
				ORDER BY num_' . ($type != 'replies' ? 'views' : 'replies') . ' DESC
				LIMIT {int:limit}',
				[
					'is_approved' => 1,
					'limit' => $num_topics > 100 ? ($num_topics + ($num_topics / 2)) : 100,
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

		$request = Db::$db->query(
			'',
			'SELECT m.subject, m.id_topic, t.num_views, t.num_replies
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE {query_wanna_see_board}' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . (!empty($topic_ids) ? '
				AND t.id_topic IN ({array_int:topic_list})' : '') . (!empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? '
				AND b.id_board != {int:recycle_board}' : '') . '
			ORDER BY t.num_' . ($type != 'replies' ? 'views' : 'replies') . ' DESC
			LIMIT {int:limit}',
			[
				'topic_list' => $topic_ids,
				'is_approved' => 1,
				'recycle_board' => !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : null,
				'limit' => $num_topics,
			],
		);
		$topics = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			Lang::censorText($row['subject']);

			$topics[] = [
				'id' => $row['id_topic'],
				'subject' => $row['subject'],
				'num_replies' => $row['num_replies'],
				'num_views' => $row['num_views'],
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
			];
		}
		Db::$db->free_result($request);

		// If mods want to do something with this list of topics, let them do that now.
		IntegrationHook::call('integrate_ssi_topTopics', [&$topics, $type]);

		if ($output_method != 'echo' || empty($topics)) {
			return $topics;
		}

		echo '
			<table class="ssi_table">
				<tr>
					<th style="text-align: left"></th>
					<th style="text-align: left">', Lang::$txt['views'], '</th>
					<th style="text-align: left">', Lang::$txt['replies'], '</th>
				</tr>';

		foreach ($topics as $sTopic) {
			echo '
				<tr>
					<td style="text-align: left">
						', $sTopic['link'], '
					</td>
					<td style="text-align: right">', Lang::numberFormat($sTopic['num_views']), '</td>
					<td style="text-align: right">', Lang::numberFormat($sTopic['num_replies']), '</td>
				</tr>';
		}
		echo '
			</table>';
	}

	/**
	 * Top topics based on replies
	 *
	 * Alias: ssi_topTopicsReplies()
	 *
	 * @param int $num_topics How many topics to show
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them
	 * @return void|array Either displays a list of top topics or returns an array of info about them, depending on output_method.
	 */
	public static function topTopicsReplies($num_topics = 10, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		return self::topTopics('replies', $num_topics, $output_method);
	}

	/**
	 * Top topics based on views
	 *
	 * Alias: ssi_topTopicsViews()
	 *
	 * @param int $num_topics How many topics to show
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them
	 * @return void|array Either displays a list of top topics or returns an array of info about them, depending on output_method.
	 */
	public static function topTopicsViews($num_topics = 10, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		return self::topTopics('views', $num_topics, $output_method);
	}

	/**
	 * Show a link to the latest member: Please welcome, Someone, our latest member.
	 *
	 * Alias: ssi_latestMember()
	 *
	 * @param string $output_method The output method. If 'echo', returns a string with a link to the latest member's profile, otherwise returns an array of info about them.
	 * @return void|array Displays a "welcome" message for the latest member or returns an array of info about them, depending on output_method.
	 */
	public static function latestMember($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($output_method == 'echo') {
			echo '
		', sprintf(Lang::$txt['welcome_newest_member'], Utils::$context['common_stats']['latest_member']['link']), '<br>';
		} else {
			return Utils::$context['common_stats']['latest_member'];
		}
	}

	/**
	 * Fetches a random member.
	 *
	 * Alias: ssi_randomMember()
	 *
	 * @param string $random_type If 'day', only fetches a new random member once a day.
	 * @param string $output_method The output method. If 'echo', displays a link to the member's profile, otherwise returns an array of info about them.
	 * @return void|array Displays a link to a random member's profile or returns an array of info about them depending on output_method.
	 */
	public static function randomMember($random_type = '', $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		// If we're looking for something to stay the same each day then seed the generator.
		if ($random_type == 'day') {
			// Set the seed to change only once per day.
			mt_srand(floor(time() / 86400));
		}

		// Get the lowest ID we're interested in.
		$member_id = mt_rand(1, Config::$modSettings['latestMember']);

		$where_query = '
			id_member >= {int:selected_member}
			AND is_activated = {int:is_activated}';

		$query_where_params = [
			'selected_member' => $member_id,
			'is_activated' => 1,
		];

		$result = self::queryMembers($where_query, $query_where_params, 1, 'id_member ASC', $output_method);

		// If we got nothing do the reverse - in case of unactivated members.
		if (empty($result)) {
			$where_query = '
				id_member <= {int:selected_member}
				AND is_activated = {int:is_activated}';

			$query_where_params = [
				'selected_member' => $member_id,
				'is_activated' => 1,
			];

			$result = self::queryMembers($where_query, $query_where_params, 1, 'id_member DESC', $output_method);
		}

		// Just to be sure put the random generator back to something... random.
		if ($random_type != '') {
			mt_srand(time());
		}

		return $result;
	}

	/**
	 * Fetch specific members
	 *
	 * Alias: ssi_fetchMember()
	 *
	 * @param array $member_ids The IDs of the members to fetch
	 * @param string $output_method The output method. If 'echo', displays a list of links to the members' profiles, otherwise returns an array of info about them.
	 * @return void|array Displays links to the specified members' profiles or returns an array of info about them, depending on output_method.
	 */
	public static function fetchMember($member_ids = [], $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (empty($member_ids)) {
			return;
		}

		// Can have more than one member if you really want...
		$member_ids = is_array($member_ids) ? $member_ids : [$member_ids];

		// Restrict it right!
		$query_where = '
			id_member IN ({array_int:member_list})';

		$query_where_params = [
			'member_list' => $member_ids,
		];

		// Then make the query and dump the data.
		return self::queryMembers($query_where, $query_where_params, '', 'id_member', $output_method);
	}

	/**
	 * Get al members in the specified group
	 *
	 * Alias: ssi_fetchGroupMembers()
	 *
	 * @param int $group_id The ID of the group to get members from
	 * @param string $output_method The output method. If 'echo', returns a list of group members, otherwise returns an array of info about them.
	 * @return void|array Displays a list of group members or returns an array of info about them, depending on output_method.
	 */
	public static function fetchGroupMembers($group_id = null, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($group_id === null) {
			return;
		}

		$query_where = '
			id_group = {int:id_group}
			OR id_post_group = {int:id_group}
			OR FIND_IN_SET({int:id_group}, additional_groups) != 0';

		$query_where_params = [
			'id_group' => $group_id,
		];

		return self::queryMembers($query_where, $query_where_params, '', 'real_name', $output_method);
	}

	/**
	 * Pulls info about members based on the specified parameters. Used by other
	 * functions to eliminate duplication.
	 *
	 * Alias: ssi_queryMembers()
	 *
	 * @param string $query_where The info for the WHERE clause of the query
	 * @param array $query_where_params The parameters for the WHERE clause
	 * @param string|int $query_limit The number of rows to return or an empty string to return all
	 * @param string $query_order The info for the ORDER BY clause of the query
	 * @param string $output_method The output method. If 'echo', displays a list of members, otherwise returns an array of info about them
	 * @return void|array Displays a list of members or returns an array of info about them, depending on output_method.
	 */
	public static function queryMembers($query_where = null, $query_where_params = [], $query_limit = '', $query_order = 'id_member DESC', $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($query_where === null) {
			return;
		}

		// Fetch the members in question.
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}members
			WHERE ' . $query_where . '
			ORDER BY ' . $query_order . '
			' . ($query_limit == '' ? '' : 'LIMIT ' . $query_limit),
			array_merge($query_where_params, [
			]),
		);
		$members = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[] = $row['id_member'];
		}
		Db::$db->free_result($request);

		if (empty($members)) {
			return [];
		}

		// If mods want to do something with this list of members, let them do that now.
		IntegrationHook::call('integrate_ssi_queryMembers', [&$members]);

		// Load the members.
		User::load($members);

		// Draw the table!
		if ($output_method == 'echo') {
			echo '
			<table style="border: none" class="ssi_table">';
		}

		$query_members = [];

		foreach ($members as $member) {
			// Load their context data.
			if (!isset(User::$loaded[$member])) {
				continue;
			}

			// Store this member's information.
			$query_members[$member] = User::$loaded[$member]->format();

			// Only do something if we're echo'ing.
			if ($output_method == 'echo') {
				echo '
				<tr>
					<td style="text-align: right; vertical-align: top; white-space: nowrap">
						', $query_members[$member]['link'], '
						<br>', $query_members[$member]['blurb'], '
						<br>', $query_members[$member]['avatar']['image'], '
					</td>
				</tr>';
			}
		}

		// End the table if appropriate.
		if ($output_method == 'echo') {
			echo '
			</table>';
		}

		// Send back the data.
		return $query_members;
	}

	/**
	 * Show some basic stats:   Total This: XXXX, etc.
	 *
	 * Alias: ssi_boardStats()
	 *
	 * @param string $output_method The output method. If 'echo', displays the stats, otherwise returns an array of info about them
	 * @return void|array Doesn't return anything if the user can't view stats. Otherwise either displays the stats or returns an array of info about them, depending on output_method.
	 */
	public static function boardStats($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (!User::$me->allowedTo('view_stats')) {
			return;
		}

		$totals = [
			'members' => Config::$modSettings['totalMembers'],
			'posts' => Config::$modSettings['totalMessages'],
			'topics' => Config::$modSettings['totalTopics'],
		];

		$result = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}boards',
			[
			],
		);
		list($totals['boards']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		$result = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}categories',
			[
			],
		);
		list($totals['categories']) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		// If mods want to do something with the board stats, let them do that now.
		IntegrationHook::call('integrate_ssi_boardStats', [&$totals]);

		if ($output_method != 'echo') {
			return $totals;
		}

		echo '
			', Lang::$txt['total_members'], ': <a href="', Config::$scripturl . '?action=mlist">', Lang::numberFormat($totals['members']), '</a><br>
			', Lang::$txt['total_posts'], ': ', Lang::numberFormat($totals['posts']), '<br>
			', Lang::$txt['total_topics'], ': ', Lang::numberFormat($totals['topics']), ' <br>
			', Lang::$txt['total_cats'], ': ', Lang::numberFormat($totals['categories']), '<br>
			', Lang::$txt['total_boards'], ': ', Lang::numberFormat($totals['boards']);
	}

	/**
	 * Shows a list of online users:  YY Guests, ZZ Users and then a list...
	 *
	 * Alias: ssi_whosOnline()
	 *
	 * @param string $output_method The output method. If 'echo', displays a list, otherwise returns an array of info about the online users.
	 * @return void|array Either displays a list of online users or returns an array of info about them, depending on output_method.
	 */
	public static function whosOnline($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		$membersOnlineOptions = [
			'show_hidden' => User::$me->allowedTo('moderate_forum'),
		];
		$return = Logging::getMembersOnlineStats($membersOnlineOptions);

		// If mods want to do something with the list of who is online, let them do that now.
		IntegrationHook::call('integrate_ssi_whosOnline', [&$return]);

		// Add some redundancy for backwards compatibility reasons.
		if ($output_method != 'echo') {
			return $return + [
				'users' => $return['users_online'],
				'guests' => $return['num_guests'],
				'hidden' => $return['num_users_hidden'],
				'buddies' => $return['num_buddies'],
				'num_users' => $return['num_users_online'],
				'total_users' => $return['num_users_online'] + $return['num_guests'],
			];
		}

		echo '
			', Lang::numberFormat($return['num_guests']), ' ', $return['num_guests'] == 1 ? Lang::$txt['guest'] : Lang::$txt['guests'], ', ', Lang::numberFormat($return['num_users_online']), ' ', $return['num_users_online'] == 1 ? Lang::$txt['user'] : Lang::$txt['users'];

		$bracketList = [];

		if (!empty(User::$me->buddies)) {
			$bracketList[] = Lang::numberFormat($return['num_buddies']) . ' ' . ($return['num_buddies'] == 1 ? Lang::$txt['buddy'] : Lang::$txt['buddies']);
		}

		if (!empty($return['num_spiders'])) {
			$bracketList[] = Lang::numberFormat($return['num_spiders']) . ' ' . ($return['num_spiders'] == 1 ? Lang::$txt['spider'] : Lang::$txt['spiders']);
		}

		if (!empty($return['num_users_hidden'])) {
			$bracketList[] = Lang::numberFormat($return['num_users_hidden']) . ' ' . Lang::$txt['hidden'];
		}

		if (!empty($bracketList)) {
			echo ' (' . implode(', ', $bracketList) . ')';
		}

		echo '<br>
				', implode(', ', $return['list_users_online']);

		// Showing membergroups?
		if (!empty(Theme::$current->settings['show_group_key']) && !empty($return['online_groups'])) {
			$membergroups = CacheApi::quickGet('membergroup_list', 'Group.php', 'SMF\\Group::getCachedList', []);

			$groups = [];

			foreach ($return['online_groups'] as $group) {
				if (isset($membergroups[$group['id']])) {
					$groups[] = $membergroups[$group['id']];
				}
			}

			echo '<br>
				[' . implode(']&nbsp;&nbsp;[', $groups) . ']';
		}
	}

	/**
	 * Just like whosOnline except it also logs the online presence.
	 *
	 * Alias: ssi_logOnline()
	 *
	 * @param string $output_method The output method. If 'echo', displays a list, otherwise returns an array of info about the online users.
	 * @return void|array Either displays a list of online users or returns an aray of info about them, depending on output_method.
	 */
	public static function logOnline($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		User::$me->logOnline();

		if ($output_method != 'echo') {
			return self::whosOnline($output_method);
		}

		self::whosOnline($output_method);
	}

	/**
	 * Shows a login box
	 *
	 * Alias: ssi_login()
	 *
	 * @param string $redirect_to The URL to redirect the user to after they login
	 * @param string $output_method The output method. If 'echo' and the user is a guest, displays a login box, otherwise returns whether the user is a guest
	 * @return void|bool Either displays a login box or returns whether the user is a guest, depending on whether the user is logged in and output_method.
	 */
	public static function login($redirect_to = '', $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if ($redirect_to != '') {
			$_SESSION['login_url'] = $redirect_to;
		}

		if ($output_method != 'echo' || !User::$me->is_guest) {
			return User::$me->is_guest;
		}

		// Create a login token
		SecurityToken::create('login');

		echo '
			<form action="', Config::$scripturl, '?action=login2" method="post" accept-charset="', Utils::$context['character_set'], '">
				<table style="border: none" class="ssi_table">
					<tr>
						<td style="text-align: right; border-spacing: 1"><label for="user">', Lang::$txt['username'], ':</label>&nbsp;</td>
						<td><input type="text" id="user" name="user" size="9" value="', User::$me->username, '"></td>
					</tr><tr>
						<td style="text-align: right; border-spacing: 1"><label for="passwrd">', Lang::$txt['password'], ':</label>&nbsp;</td>
						<td><input type="password" name="passwrd" id="passwrd" size="9"></td>
					</tr>
					<tr>
						<td>
							<input type="hidden" name="cookielength" value="-1">
							<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '" />
							<input type="hidden" name="', Utils::$context['login_token_var'], '" value="', Utils::$context['login_token'], '">
						</td>
						<td><input type="submit" value="', Lang::$txt['login'], '" class="button"></td>
					</tr>
				</table>
			</form>';
	}

	/**
	 * Show the top poll based on votes
	 *
	 * Alias: ssi_topPoll()
	 *
	 * @param string $output_method The output method. If 'echo', displays the poll, otherwise returns an array of info about it
	 * @return void|array Either shows the top poll or returns an array of info about it, depending on output_method.
	 */
	public static function topPoll($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		// Just use recentPoll, no need to duplicate code...
		return self::recentPoll(true, $output_method);
	}

	/**
	 * Shows the most recent poll
	 *
	 * Alias: ssi_recentPoll()
	 *
	 * @param bool $topPollInstead Whether to show the top poll (based on votes) instead of the most recent one
	 * @param string $output_method The output method. If 'echo', displays the poll, otherwise returns an array of info about it.
	 * @return void|array Either shows the poll or returns an array of info about it, depending on output_method.
	 */
	public static function recentPoll($topPollInstead = false, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		$poll = Poll::load(0, ($topPollInstead ? Poll::LOAD_BY_VOTES : Poll::LOAD_BY_RECENT) | Poll::CHECK_ACCESS | Poll::CHECK_IGNORE | Poll::CHECK_LOCKED | Poll::CHECK_EXPIRY);

		if (empty($poll)) {
			return [];
		}

		$return = $poll->format();

		// If mods want to do something with this poll, let them do that now.
		IntegrationHook::call('integrate_ssi_recentPoll', [&$return, $topPollInstead]);

		if ($output_method != 'echo') {
			return $return;
		}

		if ($return['allow_vote']) {
			echo '
				<form class="ssi_poll" action="', Config::$boardurl, '/SSI.php?ssi_function=pollVote" method="post" accept-charset="', Utils::$context['character_set'], '">
					<strong>', $return['question'], '</strong><br>
					', !empty($return['allowed_warning']) ? $return['allowed_warning'] . '<br>' : '';

			foreach ($return['options'] as $option) {
				echo '
					<label for="', $option['id'], '">', $option['vote_button'], ' ', $option['option'], '</label><br>';
			}

			echo '
					<input type="submit" value="', Lang::$txt['poll_vote'], '" class="button">
					<input type="hidden" name="poll" value="', $return['id'], '">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				</form>';
		} elseif ($return['allow_view_results']) {
			echo '
				<div class="ssi_poll">
					<strong>', $return['question'], '</strong>
					<dl>';

			foreach ($return['options'] as $option) {
				echo '
						<dt>', $option['option'], '</dt>
						<dd>';

				if ($return['allow_view_results']) {
					echo '
							<div class="ssi_poll_bar" style="border: 1px solid #666; height: 1em">
								<div class="ssi_poll_bar_fill" style="background: #ccf; height: 1em; width: ', $option['percent'], '%;">
								</div>
							</div>
							', $option['votes'], ' (', $option['percent'], '%)';
				}

				echo '
						</dd>';
			}

			echo '
					</dl>', ($return['allow_view_results'] ? '
					<strong>' . Lang::$txt['poll_total_voters'] . ': ' . $return['total_votes'] . '</strong>' : ''), '
				</div>';
		} else {
			echo Lang::$txt['poll_cannot_see'];
		}
	}

	/**
	 * Shows the poll from the specified topic
	 *
	 * Alias: ssi_showPoll()
	 *
	 * @param null|int $topic The topic to show the poll from. If null, $_REQUEST['ssi_topic'] will be used instead.
	 * @param string $output_method The output method. If 'echo', displays the poll, otherwise returns an array of info about it.
	 * @return void|array Either displays the poll or returns an array of info about it, depending on output_method.
	 */
	public static function showPoll($topic = null, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		$topic = (int) ($topic ?? ($_REQUEST['ssi_topic'] ?? 0));

		if (empty($topic)) {
			return [];
		}

		$poll = Poll::load($topic, Poll::LOAD_BY_TOPIC | Poll::CHECK_ACCESS);

		if (empty($poll)) {
			return [];
		}

		$return = $poll->format();

		// If mods want to do something with this poll, let them do that now.
		IntegrationHook::call('integrate_ssi_showPoll', [&$return]);

		if ($output_method != 'echo') {
			return $return;
		}

		if ($return['allow_vote']) {
			echo '
				<form class="ssi_poll" action="', Config::$boardurl, '/SSI.php?ssi_function=pollVote" method="post" accept-charset="', Utils::$context['character_set'], '">
					<strong>', $return['question'], '</strong><br>
					', !empty($return['allowed_warning']) ? $return['allowed_warning'] . '<br>' : '';

			foreach ($return['options'] as $option) {
				echo '
					<label for="', $option['id'], '">', $option['vote_button'], ' ', $option['option'], '</label><br>';
			}

			echo '
					<input type="submit" value="', Lang::$txt['poll_vote'], '" class="button">
					<input type="hidden" name="poll" value="', $return['id'], '">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				</form>';
		} elseif ($return['allow_view_results']) {
			echo '
				<div class="ssi_poll">
					<strong>', $return['question'], '</strong>
					<dl>';

			foreach ($return['options'] as $option) {
				echo '
						<dt>', $option['option'], '</dt>
						<dd>';

				if ($return['allow_view_results']) {
					echo '
							<div class="ssi_poll_bar" style="border: 1px solid #666; height: 1em">
								<div class="ssi_poll_bar_fill" style="background: #ccf; height: 1em; width: ', $option['percent'], '%;">
								</div>
							</div>
							', $option['votes'], ' (', $option['percent'], '%)';
				}

				echo '
						</dd>';
			}

			echo '
					</dl>', ($return['allow_view_results'] ? '
					<strong>' . Lang::$txt['poll_total_voters'] . ': ' . $return['total_votes'] . '</strong>' : ''), '
				</div>';
		} else {
			echo Lang::$txt['poll_cannot_see'];
		}
	}

	/**
	 * Handles voting in a poll (done automatically)
	 *
	 * Alias: ssi_pollVote()
	 */
	public static function pollVote()
	{
		if (!self::$setup_done) {
			new self();
		}

		if (!isset($_POST[Utils::$context['session_var']]) || $_POST[Utils::$context['session_var']] != User::$sc || empty($_POST['options']) || !isset($_POST['poll'])) {
			echo '<!DOCTYPE html>
	<html>
	<head>
		<script>
			history.go(-1);
		</script>
	</head>
	<body>&laquo;</body>
	</html>';

			return;
		}

		// This can cause weird errors! (ie. copyright missing.)
		User::$me->checkSession();

		$_POST['poll'] = (int) $_POST['poll'];

		// Check if they have already voted, or voting is locked.
		$request = Db::$db->query(
			'',
			'SELECT
				p.id_poll, p.voting_locked, p.expire_time, p.max_votes, p.guest_vote,
				t.id_topic,
				COALESCE(lp.id_choice, -1) AS selected
			FROM {db_prefix}polls AS p
				INNER JOIN {db_prefix}topics AS t ON (t.id_poll = {int:current_poll})
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member = {int:current_member})
			WHERE p.id_poll = {int:current_poll}
				AND {query_see_board}' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
			LIMIT 1',
			[
				'current_member' => User::$me->id,
				'current_poll' => $_POST['poll'],
				'is_approved' => 1,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			die;
		}
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		if (!empty($row['voting_locked']) || ($row['selected'] != -1 && !User::$me->is_guest) || (!empty($row['expire_time']) && time() > $row['expire_time'])) {
			Utils::redirectexit('topic=' . $row['id_topic'] . '.0');
		}

		// Too many options checked?
		if (count($_REQUEST['options']) > $row['max_votes']) {
			Utils::redirectexit('topic=' . $row['id_topic'] . '.0');
		}

		// It's a guest who has already voted?
		if (User::$me->is_guest) {
			// Guest voting disabled?
			if (!$row['guest_vote']) {
				Utils::redirectexit('topic=' . $row['id_topic'] . '.0');
			}
			// Already voted?
			elseif (isset($_COOKIE['guest_poll_vote']) && in_array($row['id_poll'], explode(',', $_COOKIE['guest_poll_vote']))) {
				Utils::redirectexit('topic=' . $row['id_topic'] . '.0');
			}
		}

		$sOptions = [];
		$inserts = [];

		foreach ($_REQUEST['options'] as $id) {
			$id = (int) $id;

			$sOptions[] = $id;
			$inserts[] = [$_POST['poll'], User::$me->id, $id];
		}

		// Add their vote in to the tally.
		Db::$db->insert(
			'insert',
			Db::$db->prefix . 'log_polls',
			['id_poll' => 'int', 'id_member' => 'int', 'id_choice' => 'int'],
			$inserts,
			['id_poll', 'id_member', 'id_choice'],
		);
		Db::$db->query(
			'',
			'UPDATE {db_prefix}poll_choices
			SET votes = votes + 1
			WHERE id_poll = {int:current_poll}
				AND id_choice IN ({array_int:option_list})',
			[
				'option_list' => $sOptions,
				'current_poll' => $_POST['poll'],
			],
		);

		// Track the vote if a guest.
		if (User::$me->is_guest) {
			$_COOKIE['guest_poll_vote'] = !empty($_COOKIE['guest_poll_vote']) ? ($_COOKIE['guest_poll_vote'] . ',' . $row['id_poll']) : $row['id_poll'];

			$cookie = new Cookie('guest_poll_vote', $_COOKIE['guest_poll_vote'], time() + 2500000);
			$cookie->set();
		}

		Utils::redirectexit('topic=' . $row['id_topic'] . '.0');
	}

	/**
	 * Shows a search box
	 *
	 * Alias: ssi_quickSearch()
	 *
	 * @param string $output_method The output method. If 'echo', displays a search box, otherwise returns the URL of the search page.
	 * @return void|string Displays a search box or returns the URL to the search page depending on output_method. If you don't have permission to search, the function won't return anything.
	 */
	public static function quickSearch($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (!User::$me->allowedTo('search_posts')) {
			return;
		}

		if ($output_method != 'echo') {
			return Config::$scripturl . '?action=search';
		}

		echo '
			<form action="', Config::$scripturl, '?action=search2" method="post" accept-charset="', Utils::$context['character_set'], '">
				<input type="hidden" name="advanced" value="0"><input type="text" name="search" size="30"> <input type="submit" value="', Lang::$txt['search'], '" class="button">
			</form>';
	}

	/**
	 * Show a random forum news item
	 *
	 * Alias: ssi_news()
	 *
	 * @param string $output_method The output method. If 'echo', shows the news item, otherwise returns it.
	 * @return void|string Shows or returns a random forum news item, depending on output_method.
	 */
	public static function news($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		Utils::$context['random_news_line'] = !empty(Utils::$context['news_lines']) ? Utils::$context['news_lines'][mt_rand(0, count(Utils::$context['news_lines']) - 1)] : '';

		// If mods want to do somthing with the news, let them do that now. Don't need to pass the news line itself, since it is already in Utils::$context.
		IntegrationHook::call('integrate_ssi_news');

		if ($output_method != 'echo') {
			return Utils::$context['random_news_line'];
		}

		echo Utils::$context['random_news_line'];
	}

	/**
	 * Show today's birthdays.
	 *
	 * Alias: ssi_todaysBirthdays()
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of users, otherwise returns an array of info about them.
	 * @return void|array Displays a list of users or returns an array of info about them depending on output_method.
	 */
	public static function todaysBirthdays($output_method = 'echo')
	{

		if (!self::$setup_done) {
			new self();
		}

		if (empty(Config::$modSettings['cal_enabled']) || !User::$me->allowedTo('calendar_view') || !User::$me->allowedTo('profile_view')) {
			return;
		}

		$eventOptions = [
			'include_birthdays' => true,
			'num_days_shown' => empty(Config::$modSettings['cal_days_for_index']) || Config::$modSettings['cal_days_for_index'] < 1 ? 1 : Config::$modSettings['cal_days_for_index'],
		];
		$return = CacheApi::quickGet('calendar_index_offset_' . User::$me->time_offset, 'Actions/Calendar.php', 'SMF\\Actions\\Calendar::cache_getRecentEvents', [$eventOptions]);

		// The self::todaysCalendar variants all use the same hook and just pass on $eventOptions so the hooked code can distinguish different cases if necessary
		IntegrationHook::call('integrate_ssi_calendar', [&$return, $eventOptions]);

		if ($output_method != 'echo') {
			return $return['calendar_birthdays'];
		}

		foreach ((array) $return['calendar_birthdays'] as $member) {
			echo '
				<a href="', Config::$scripturl, '?action=profile;u=', $member['id'], '"><span class="fix_rtl_names">' . $member['name'] . '</span>' . (isset($member['age']) ? ' (' . $member['age'] . ')' : '') . '</a>' . (!$member['is_last'] ? ', ' : '');
		}
	}

	/**
	 * Shows today's holidays.
	 *
	 * Alias: ssi_todaysHolidays()
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of holidays, otherwise returns an array of info about them.
	 * @return void|array Displays a list of holidays or returns an array of info about them depending on output_method
	 */
	public static function todaysHolidays($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (empty(Config::$modSettings['cal_enabled']) || !User::$me->allowedTo('calendar_view')) {
			return;
		}

		$eventOptions = [
			'include_holidays' => true,
			'num_days_shown' => empty(Config::$modSettings['cal_days_for_index']) || Config::$modSettings['cal_days_for_index'] < 1 ? 1 : Config::$modSettings['cal_days_for_index'],
		];
		$return = CacheApi::quickGet('calendar_index_offset_' . User::$me->time_offset, 'Actions/Calendar.php', 'SMF\\Actions\\Calendar::cache_getRecentEvents', [$eventOptions]);

		// The self::todaysCalendar variants all use the same hook and just pass on $eventOptions so the hooked code can distinguish different cases if necessary
		IntegrationHook::call('integrate_ssi_calendar', [&$return, $eventOptions]);

		if ($output_method != 'echo') {
			return $return['calendar_holidays'];
		}

		echo '
			', implode(', ', $return['calendar_holidays']);
	}

	/**
	 * Shows today's events.
	 *
	 * Alias: ssi_todaysEvents()
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of events, otherwise returns an array of info about them.
	 * @return void|array Displays a list of events or returns an array of info about them depending on output_method
	 */
	public static function todaysEvents($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (empty(Config::$modSettings['cal_enabled']) || !User::$me->allowedTo('calendar_view')) {
			return;
		}

		$eventOptions = [
			'include_events' => true,
			'num_days_shown' => empty(Config::$modSettings['cal_days_for_index']) || Config::$modSettings['cal_days_for_index'] < 1 ? 1 : Config::$modSettings['cal_days_for_index'],
		];
		$return = CacheApi::quickGet('calendar_index_offset_' . User::$me->time_offset, 'Actions/Calendar.php', 'SMF\\Actions\\Calendar::cache_getRecentEvents', [$eventOptions]);

		// The self::todaysCalendar variants all use the same hook and just pass on $eventOptions so the hooked code can distinguish different cases if necessary
		IntegrationHook::call('integrate_ssi_calendar', [&$return, $eventOptions]);

		if ($output_method != 'echo') {
			return $return['calendar_events'];
		}

		foreach ($return['calendar_events'] as $event) {
			if ($event['can_edit']) {
				echo '
		<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';
			}
			echo '
		' . $event['link'] . (!$event['is_last'] ? ', ' : '');
		}
	}

	/**
	 * Shows today's calendar items (events, birthdays and holidays)
	 *
	 * Alias: ssi_todaysCalendar()
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of calendar items, otherwise returns an array of info about them.
	 * @return void|array Displays a list of calendar items or returns an array of info about them depending on output_method
	 */
	public static function todaysCalendar($output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (empty(Config::$modSettings['cal_enabled']) || !User::$me->allowedTo('calendar_view')) {
			return;
		}

		$eventOptions = [
			'include_birthdays' => User::$me->allowedTo('profile_view'),
			'include_holidays' => true,
			'include_events' => true,
			'num_days_shown' => empty(Config::$modSettings['cal_days_for_index']) || Config::$modSettings['cal_days_for_index'] < 1 ? 1 : Config::$modSettings['cal_days_for_index'],
		];
		$return = CacheApi::quickGet('calendar_index_offset_' . User::$me->time_offset, 'Actions/Calendar.php', 'SMF\\Actions\\Calendar::cache_getRecentEvents', [$eventOptions]);

		// The self::todaysCalendar variants all use the same hook and just pass on $eventOptions so the hooked code can distinguish different cases if necessary
		IntegrationHook::call('integrate_ssi_calendar', [&$return, $eventOptions]);

		if ($output_method != 'echo') {
			return $return;
		}

		if (!empty($return['calendar_holidays'])) {
			echo '
				<span class="holiday">' . Lang::$txt['calendar_prompt'] . ' ' . implode(', ', $return['calendar_holidays']) . '<br></span>';
		}

		if (!empty($return['calendar_birthdays'])) {
			echo '
				<span class="birthday">' . Lang::$txt['birthdays_upcoming'] . '</span> ';

			foreach ($return['calendar_birthdays'] as $member) {
				echo '
				<a href="', Config::$scripturl, '?action=profile;u=', $member['id'], '"><span class="fix_rtl_names">', $member['name'], '</span>', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', !$member['is_last'] ? ', ' : '';
			}
			echo '
				<br>';
		}

		if (!empty($return['calendar_events'])) {
			echo '
				<span class="event">' . Lang::$txt['events_upcoming'] . '</span> ';

			foreach ($return['calendar_events'] as $event) {
				if ($event['can_edit']) {
					echo '
				<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';
				}
				echo '
				' . $event['link'] . (!$event['is_last'] ? ', ' : '');
			}
		}
	}

	/**
	 * Show the latest news, with a template... by board.
	 *
	 * Alias: ssi_boardNews()
	 *
	 * @param null|int $board The ID of the board to get the info from. Defaults to $board or $_GET['board'] if not set.
	 * @param null|int $limit How many items to show. Defaults to $_GET['limit'] or 5 if not set.
	 * @param null|int $start Start with the specified item. Defaults to $_GET['start'] or 0 if not set.
	 * @param null|int $length How many characters to show from each post. Defaults to $_GET['length'] or 0 (no limit) if not set.
	 * @param string $output_method The output method. If 'echo', displays the news items, otherwise returns an array of info about them.
	 * @return void|array Displays the news items or returns an array of info about them, depending on output_method.
	 */
	public static function boardNews($board = null, $limit = null, $start = null, $length = null, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		Lang::load('Stats');

		// Must be integers....
		if ($limit === null) {
			$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 5;
		} else {
			$limit = (int) $limit;
		}

		if ($start === null) {
			$start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
		} else {
			$start = (int) $start;
		}

		if ($board !== null) {
			$board = (int) $board;
		} elseif (isset($_GET['board'])) {
			$board = (int) $_GET['board'];
		}

		if ($length === null) {
			$length = isset($_GET['length']) ? (int) $_GET['length'] : 0;
		} else {
			$length = (int) $length;
		}

		$limit = max(0, $limit);
		$start = max(0, $start);

		// Make sure guests can see this board.
		$request = Db::$db->query(
			'',
			'SELECT id_board
			FROM {db_prefix}boards
			WHERE ' . ($board === null ? '' : 'id_board = {int:current_board}
				AND ') . 'FIND_IN_SET(-1, member_groups) != 0
			LIMIT 1',
			[
				'current_board' => $board,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			if ($output_method == 'echo') {
				die(Lang::$txt['ssi_no_guests']);
			}

			return [];
		}
		list($board) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$icon_sources = [];

		foreach (Utils::$context['stable_icons'] as $icon) {
			$icon_sources[$icon] = 'images_url';
		}

		if (!empty(Config::$modSettings['enable_likes'])) {
			Utils::$context['can_like'] = User::$me->allowedTo('likes_like');
		}

		// Find the post ids.
		$request = Db::$db->query(
			'',
			'SELECT t.id_first_msg
			FROM {db_prefix}topics as t
				LEFT JOIN {db_prefix}boards as b ON (b.id_board = t.id_board)
			WHERE t.id_board = {int:current_board}' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
				AND {query_see_board}
			ORDER BY t.id_first_msg DESC
			LIMIT ' . $start . ', ' . $limit,
			[
				'current_board' => $board,
				'is_approved' => 1,
			],
		);
		$posts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$posts[] = $row['id_first_msg'];
		}
		Db::$db->free_result($request);

		if (empty($posts)) {
			return [];
		}

		// Find the posts.
		$request = Db::$db->query(
			'',
			'SELECT
				m.icon, m.subject, m.body, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.likes,
				t.num_replies, t.id_topic, m.id_member, m.smileys_enabled, m.id_msg, t.locked, t.id_last_msg, m.id_board
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE t.id_first_msg IN ({array_int:post_list})
			ORDER BY t.id_first_msg DESC
			LIMIT ' . count($posts),
			[
				'post_list' => $posts,
			],
		);
		$return = [];
		$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;

		while ($row = Db::$db->fetch_assoc($request)) {
			$topic = new Topic($row['id_topic'], [
				'id_board' => $row['id_board'],
				'num_replies' => $row['num_replies'],
				'locked' => $row['locked'],
				'id_first_msg' => $row['id_msg'],
				'id_last_msg' => $row['id_last_msg'],
			]);

			// If we want to limit the length of the post.
			if (!empty($length) && Utils::entityStrlen($row['body']) > $length) {
				$row['body'] = Utils::entitySubstr($row['body'], 0, $length);
				$cutoff = false;

				$last_space = strrpos($row['body'], ' ');
				$last_open = strrpos($row['body'], '<');
				$last_close = strrpos($row['body'], '>');

				if (empty($last_space) || ($last_space == $last_open + 3 && (empty($last_close) || (!empty($last_close) && $last_close < $last_open))) || $last_space < $last_open || $last_open == $length - 6) {
					$cutoff = $last_open;
				} elseif (empty($last_close) || $last_close < $last_open) {
					$cutoff = $last_space;
				}

				if ($cutoff !== false) {
					$row['body'] = Utils::entitySubstr($row['body'], 0, $cutoff);
				}
				$row['body'] .= '...';
			}

			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			if (!empty($recycle_board) && $row['id_board'] == $recycle_board) {
				$row['icon'] = 'recycled';
			}

			// Check that this message icon is there...
			if (!empty(Config::$modSettings['messageIconChecks_enable']) && !isset($icon_sources[$row['icon']])) {
				$icon_sources[$row['icon']] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $row['icon'] . '.png') ? 'images_url' : 'default_images_url';
			} elseif (!isset($icon_sources[$row['icon']])) {
				$icon_sources[$row['icon']] = 'images_url';
			}

			Lang::censorText($row['subject']);
			Lang::censorText($row['body']);

			$return[] = [
				'id' => $row['id_topic'],
				'message_id' => $row['id_msg'],
				'icon' => '<img src="' . Theme::$current->settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.png" alt="' . $row['icon'] . '">',
				'subject' => $row['subject'],
				'time' => Time::create('@' . $row['poster_time'])->format(),
				'timestamp' => $row['poster_time'],
				'body' => $row['body'],
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['num_replies'] . ' ' . ($row['num_replies'] == 1 ? Lang::$txt['ssi_comment'] : Lang::$txt['ssi_comments']) . '</a>',
				'replies' => $row['num_replies'],
				'comment_href' => !empty($row['locked']) ? '' : Config::$scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'],
				'comment_link' => !empty($row['locked']) ? '' : '<a href="' . Config::$scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'] . '">' . Lang::$txt['ssi_write_comment'] . '</a>',
				'new_comment' => !empty($row['locked']) ? '' : '<a href="' . Config::$scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . '">' . Lang::$txt['ssi_write_comment'] . '</a>',
				'poster' => [
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'href' => !empty($row['id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : '',
					'link' => !empty($row['id_member']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				],
				'locked' => !empty($row['locked']),
				'is_last' => false,
				// Nasty ternary for likes not messing around the "is_last" check.
				'likes' => !empty(Config::$modSettings['enable_likes']) ? [
					'count' => $row['likes'],
					'you' => in_array($row['id_msg'], $topic->getLikedMsgs()),
					'can_like' => !User::$me->is_guest && $row['id_member'] != User::$me->id && !empty(Utils::$context['can_like']),
				] : [],
			];
		}
		Db::$db->free_result($request);

		if (empty($return)) {
			return $return;
		}

		$return[count($return) - 1]['is_last'] = true;

		// If mods want to do something with this list of posts, let them do that now.
		IntegrationHook::call('integrate_ssi_boardNews', [&$return]);

		if ($output_method != 'echo') {
			return $return;
		}

		foreach ($return as $news) {
			echo '
				<div class="news_item">
					<h3 class="news_header">
						', $news['icon'], '
						<a href="', $news['href'], '">', $news['subject'], '</a>
					</h3>
					<div class="news_timestamp">', $news['time'], ' ', Lang::$txt['by'], ' ', $news['poster']['link'], '</div>
					<div class="news_body" style="padding: 2ex 0;">', $news['body'], '</div>
					', $news['link'], $news['locked'] ? '' : ' | ' . $news['comment_link'], '';

			// Is there any likes to show?
			if (!empty(Config::$modSettings['enable_likes'])) {
				echo '
						<ul>';

				if (!empty($news['likes']['can_like'])) {
					echo '
							<li class="smflikebutton" id="msg_', $news['message_id'], '_likes"><a href="', Config::$scripturl, '?action=likes;ltype=msg;sa=like;like=', $news['message_id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="msg_like"><span class="', $news['likes']['you'] ? 'unlike' : 'like', '"></span>', $news['likes']['you'] ? Lang::$txt['unlike'] : Lang::$txt['like'], '</a></li>';
				}

				if (!empty($news['likes']['count'])) {
					Utils::$context['some_likes'] = true;
					$count = $news['likes']['count'];
					$base = 'likes_';

					if ($news['likes']['you']) {
						$base = 'you_' . $base;
						$count--;
					}
					$base .= (isset(Lang::$txt[$base . $count])) ? $count : 'n';

					echo '
							<li class="like_count smalltext">', sprintf(Lang::$txt[$base], Config::$scripturl . '?action=likes;sa=view;ltype=msg;like=' . $news['message_id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], Lang::numberFormat($count)), '</li>';
				}

				echo '
						</ul>';
			}

			// Close the main div.
			echo '
				</div>';

			if (!$news['is_last']) {
				echo '
				<hr>';
			}
		}
	}

	/**
	 * Show the most recent events
	 *
	 * Alias: ssi_recentEvents()
	 *
	 * @param int $max_events The maximum number of events to show
	 * @param string $output_method The output method. If 'echo', displays the events, otherwise returns an array of info about them.
	 * @return void|array Displays the events or returns an array of info about them, depending on output_method.
	 */
	public static function recentEvents($max_events = 7, $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		if (empty(Config::$modSettings['cal_enabled']) || !User::$me->allowedTo('calendar_view')) {
			return;
		}

		// Find all events which are happening in the near future that the member can see.
		$request = Db::$db->query(
			'',
			'SELECT
				cal.id_event, cal.start_date, cal.end_date, cal.title, cal.id_member, cal.id_topic,
				cal.start_time, cal.end_time, cal.timezone, cal.location,
				cal.id_board, t.id_first_msg, t.approved
			FROM {db_prefix}calendar AS cal
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)
			WHERE cal.start_date <= {date:current_date}
				AND cal.end_date >= {date:current_date}
				AND (cal.id_board = {int:no_board} OR {query_wanna_see_board})
			ORDER BY cal.start_date DESC
			LIMIT ' . $max_events,
			[
				'current_date' => Time::strftime('%Y-%m-%d', time()),
				'no_board' => 0,
			],
		);
		$return = [];
		$duplicates = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Check if we've already come by an event linked to this same topic with the same title... and don't display it if we have.
			if (!empty($duplicates[$row['title'] . $row['id_topic']])) {
				continue;
			}

			// Censor the title.
			Lang::censorText($row['title']);

			if ($row['start_date'] < Time::strftime('%Y-%m-%d', time())) {
				$date = Time::strftime('%Y-%m-%d', time());
			} else {
				$date = $row['start_date'];
			}

			// If the topic it is attached to is not approved then don't link it.
			if (!empty($row['id_first_msg']) && !$row['approved']) {
				$row['id_board'] = $row['id_topic'] = $row['id_first_msg'] = 0;
			}

			$allday = (empty($row['start_time']) || empty($row['end_time']) || empty($row['timezone']) || !in_array($row['timezone'], timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC))) ? true : false;

			$return[$date][] = [
				'id' => $row['id_event'],
				'title' => $row['title'],
				'location' => $row['location'],
				'can_edit' => User::$me->allowedTo('calendar_edit_any') || ($row['id_member'] == User::$me->id && User::$me->allowedTo('calendar_edit_own')),
				'modify_href' => Config::$scripturl . '?action=' . ($row['id_board'] == 0 ? 'calendar;sa=post;' : 'post;msg=' . $row['id_first_msg'] . ';topic=' . $row['id_topic'] . '.0;calendar;') . 'eventid=' . $row['id_event'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'href' => $row['id_board'] == 0 ? '' : Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => $row['id_board'] == 0 ? $row['title'] : '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['title'] . '</a>',
				'start_date' => $row['start_date'],
				'end_date' => $row['end_date'],
				'start_time' => !$allday ? $row['start_time'] : null,
				'end_time' => !$allday ? $row['end_time'] : null,
				'tz' => !$allday ? $row['timezone'] : null,
				'allday' => $allday,
				'is_last' => false,
			];

			// Let's not show this one again, huh?
			$duplicates[$row['title'] . $row['id_topic']] = true;
		}
		Db::$db->free_result($request);

		foreach ($return as $mday => $array) {
			$return[$mday][count($array) - 1]['is_last'] = true;
		}

		// If mods want to do something with this list of events, let them do that now.
		IntegrationHook::call('integrate_ssi_recentEvents', [&$return]);

		if ($output_method != 'echo' || empty($return)) {
			return $return;
		}

		// Well the output method is echo.
		echo '
				<span class="event">' . Lang::$txt['events'] . '</span> ';

		foreach ($return as $mday => $array) {
			foreach ($array as $event) {
				if ($event['can_edit']) {
					echo '
					<a href="' . $event['modify_href'] . '" style="color: #ff0000;">*</a> ';
				}

				echo '
					' . $event['link'] . (!$event['is_last'] ? ', ' : '');
			}
		}
	}

	/**
	 * Checks whether the specified password is correct for the specified user.
	 *
	 * Alias: ssi_checkPassword()
	 *
	 * @param int|string $id The ID or username of a user
	 * @param string $password The password to check
	 * @param bool $is_username If true, treats $id as a username rather than a user ID
	 * @return bool Whether or not the password is correct.
	 */
	public static function checkPassword($id = null, $password = null, $is_username = false)
	{
		if (!self::$setup_done) {
			new self();
		}

		// If $id is null, this was most likely called from a query string and should do nothing.
		if ($id === null) {
			return;
		}

		$request = Db::$db->query(
			'',
			'SELECT passwd, member_name, is_activated
			FROM {db_prefix}members
			WHERE ' . ($is_username ? 'member_name' : 'id_member') . ' = {string:id}
			LIMIT 1',
			[
				'id' => $id,
			],
		);
		list($pass, $user, $active) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return Security::hashVerifyPassword($user, $password, $pass) && $active == 1;
	}

	/**
	 * Shows the most recent attachments that the user can see
	 *
	 * Alias: ssi_recentAttachments()
	 *
	 * @param int $num_attachments How many to show
	 * @param array $attachment_ext Only shows attachments with the specified extensions ('jpg', 'gif', etc.) if set
	 * @param string $output_method The output method. If 'echo', displays a table with links/info, otherwise returns an array with information about the attachments
	 * @return void|array Displays a table of attachment info or returns an array containing info about the attachments, depending on output_method.
	 */
	public static function recentAttachments($num_attachments = 10, $attachment_ext = [], $output_method = 'echo')
	{
		if (!self::$setup_done) {
			new self();
		}

		// We want to make sure that we only get attachments for boards that we can see *if* any.
		$attachments_boards = User::$me->boardsAllowedTo('view_attachments');

		// No boards?  Adios amigo.
		if (empty($attachments_boards)) {
			return [];
		}

		// Is it an array?
		$attachment_ext = (array) $attachment_ext;

		// Lets build the query.
		$request = Db::$db->query(
			'',
			'SELECT
				att.id_attach, att.id_msg, att.filename, COALESCE(att.size, 0) AS filesize, att.downloads, mem.id_member,
				COALESCE(mem.real_name, m.poster_name) AS poster_name, m.id_topic, m.subject, t.id_board, m.poster_time,
				att.width, att.height' . (empty(Config::$modSettings['attachmentShowImages']) || empty(Config::$modSettings['attachmentThumbnails']) ? '' : ', COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
			FROM {db_prefix}attachments AS att
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = att.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (empty(Config::$modSettings['attachmentShowImages']) || empty(Config::$modSettings['attachmentThumbnails']) ? '' : '
				LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = att.id_thumb)') . '
			WHERE att.attachment_type = 0' . ($attachments_boards === [0] ? '' : '
				AND m.id_board IN ({array_int:boards_can_see})') . (!empty($attachment_ext) ? '
				AND att.fileext IN ({array_string:attachment_ext})' : '') .
				(!Config::$modSettings['postmod_active'] || User::$me->allowedTo('approve_posts') ? '' : '
				AND t.approved = {int:is_approved}
				AND m.approved = {int:is_approved}
				AND att.approved = {int:is_approved}') . '
			ORDER BY att.id_attach DESC
			LIMIT {int:num_attachments}',
			[
				'boards_can_see' => $attachments_boards,
				'attachment_ext' => $attachment_ext,
				'num_attachments' => $num_attachments,
				'is_approved' => 1,
			],
		);

		// We have something.
		$attachments = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$filename = preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#$1;', htmlspecialchars($row['filename']));

			// Is it an image?
			$attachments[$row['id_attach']] = [
				'member' => [
					'id' => $row['id_member'],
					'name' => $row['poster_name'],
					'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
				],
				'file' => [
					'filename' => $filename,
					'filesize' => round($row['filesize'] / 1024, 2) . Lang::$txt['kilobyte'],
					'downloads' => $row['downloads'],
					'href' => Config::$scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'],
					'link' => '<img src="' . Theme::$current->settings['images_url'] . '/icons/clip.png" alt=""> <a href="' . Config::$scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . '">' . $filename . '</a>',
					'is_image' => !empty($row['width']) && !empty($row['height']) && !empty(Config::$modSettings['attachmentShowImages']),
				],
				'topic' => [
					'id' => $row['id_topic'],
					'subject' => $row['subject'],
					'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
					'time' => Time::create('@' . $row['poster_time'])->format(),
				],
			];

			// Images.
			if ($attachments[$row['id_attach']]['file']['is_image']) {
				$id_thumb = empty($row['id_thumb']) ? $row['id_attach'] : $row['id_thumb'];
				$attachments[$row['id_attach']]['file']['image'] = [
					'id' => $id_thumb,
					'width' => $row['width'],
					'height' => $row['height'],
					'img' => '<img src="' . Config::$scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image" alt="' . $filename . '">',
					'thumb' => '<img src="' . Config::$scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '">',
					'href' => Config::$scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image',
					'link' => '<a href="' . Config::$scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $row['id_attach'] . ';image"><img src="' . Config::$scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=' . $id_thumb . ';image" alt="' . $filename . '"></a>',
				];
			}
		}
		Db::$db->free_result($request);

		// If mods want to do something with this list of attachments, let them do that now.
		IntegrationHook::call('integrate_ssi_recentAttachments', [&$attachments]);

		// So you just want an array?  Here you can have it.
		if ($output_method == 'array' || empty($attachments)) {
			return $attachments;
		}

		// Give them the default.
		echo '
			<table class="ssi_downloads">
				<tr>
					<th style="text-align: left; padding: 2">', Lang::$txt['file'], '</th>
					<th style="text-align: left; padding: 2">', Lang::$txt['posted_by'], '</th>
					<th style="text-align: left; padding: 2">', Lang::$txt['downloads'], '</th>
					<th style="text-align: left; padding: 2">', Lang::$txt['filesize'], '</th>
				</tr>';

		foreach ($attachments as $attach) {
			echo '
				<tr>
					<td>', $attach['file']['link'], '</td>
					<td>', $attach['member']['link'], '</td>
					<td style="text-align: center">', $attach['file']['downloads'], '</td>
					<td>', $attach['file']['filesize'], '</td>
				</tr>';
		}
		echo '
			</table>';
	}

	/******************
	 * Primary methods.
	 ******************/

	/**
	 * Constructor. Sets up stuff we need for safe use of SSI.
	 *
	 */
	public function __construct()
	{
		// SSI isn't meant to be used from within the forum,
		// but apparently someone is doing so anyway...
		if (defined('SMF') && SMF !== 'SSI') {
			if (!self::$setup_done) {
				IntegrationHook::call('integrate_SSI');
			}

			self::$setup_done = true;
		}

		// Don't do the setup steps more than once.
		if (self::$setup_done) {
			return;
		}

		foreach ($this->ssi_globals as $var) {
			if (isset($GLOBALS[$var])) {
				if ($var === 'ssi_on_error_method') {
					self::$on_error_method = $GLOBALS[$var];
				} else {
					$this->{substr($var, 4)} = $GLOBALS[$var];
				}
			}
		}

		$this->error_reporting = error_reporting(!empty(Config::$db_show_debug) ? E_ALL : E_ALL & ~E_DEPRECATED);

		if (!isset($this->gzip)) {
			$this->gzip = !empty(Config::$modSettings['enableCompressedOutput']);
		}

		// Don't do john didley if the forum's been shut down completely.
		if (Config::$maintenance == 2 && $this->maintenance_off !== true) {
			ErrorHandler::displayMaintenanceMessage();
		}

		// Initiate the database connection and define some database functions to use.
		Db::load();

		// Load installed 'Mods' settings.
		Config::reloadModSettings();

		// Clean the request variables.
		QueryString::cleanRequest();

		// Seed the random generator?
		if (empty(Config::$modSettings['rand_seed']) || mt_rand(1, 250) == 69) {
			Config::generateSeed();
		}

		// Check on any hacking attempts.
		if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS'])) {
			die('No direct access...');
		}

		if (isset($_REQUEST['ssi_theme']) && (int) $_REQUEST['ssi_theme'] == (int) $this->theme) {
			die('No direct access...');
		}

		if (isset($_COOKIE['ssi_theme']) && (int) $_COOKIE['ssi_theme'] == (int) $this->theme) {
			die('No direct access...');
		}

		if (isset($_REQUEST['ssi_layers'], $this->layers) && $_REQUEST['ssi_layers'] == $this->layers) {
			die('No direct access...');
		}

		if (isset($_REQUEST['context'])) {
			die('No direct access...');
		}

		// Gzip output? (because it must be boolean and true, this can't be hacked.)
		if ($this->gzip === true && ini_get('zlib.output_compression') != '1' && ini_get('output_handler') != 'ob_gzhandler' && version_compare(PHP_VERSION, '4.2.0', '>=')) {
			ob_start('ob_gzhandler');
		} else {
			Config::$modSettings['enableCompressedOutput'] = '0';
		}

		// Primarily, this is to fix the URLs...
		ob_start('SMF\\QueryString::ob_sessrewrite');

		// Start the session... known to scramble SSI includes in cases...
		if (!headers_sent()) {
			Session::load();
		} else {
			if (isset($_COOKIE[session_name()]) || isset($_REQUEST[session_name()])) {
				// Make a stab at it, but ignore the E_WARNINGs generated because we can't send headers.
				$temp = error_reporting(error_reporting() & !E_WARNING);
				Session::load();
				error_reporting($temp);
			}

			if (!isset($_SESSION['session_value'])) {
				// Ensure session_var always starts with a letter.
				$_SESSION['session_var'] = dechex(random_int(0xA000000000, 0xFFFFFFFFFF));
				$_SESSION['session_value'] = bin2hex(random_bytes(16));
			}
			User::$sc = $_SESSION['session_value'];
		}

		// Get rid of Board::$board_id and Topic::$topic_id... do stuff loadBoard would do.
		Board::$board_id = null;
		Topic::$topic_id = null;
		Utils::$context['linktree'] = [];

		// Load the user and their cookie, as well as their settings.
		User::load();

		// No one is a moderator outside the forum.
		User::$me->is_mod = false;

		// Load the current user's permissions....
		User::$me->loadPermissions();

		// Load the current or SSI theme. (just use $this->theme = id_theme;)
		Theme::load((int) $this->theme);

		// @todo: probably not the best place, but somewhere it should be set...
		if (!headers_sent()) {
			header('content-type: text/html; charset=' . (empty(Config::$modSettings['global_character_set']) ? (empty(Lang::$txt['lang_character_set']) ? 'ISO-8859-1' : Lang::$txt['lang_character_set']) : Config::$modSettings['global_character_set']));
		}

		// Take care of any banning that needs to be done.
		if (isset($_REQUEST['ssi_ban']) || $this->ban === true) {
			User::$me->kickIfBanned();
		}

		// Do we allow guests in here?
		if (empty($this->guest_access) && empty(Config::$modSettings['allow_guestAccess']) && User::$me->is_guest && basename($_SERVER['PHP_SELF']) != 'SSI.php') {
			User::$me->kickIfGuest();
			Utils::obExit(null, true);
		}

		// Load the stuff like the menu bar, etc.
		if (isset($this->layers)) {
			Utils::$context['template_layers'] = $this->layers;
			Theme::template_header();
		} else {
			Theme::setupContext();
		}

		// Make sure they didn't muss around with the settings... but only if it's not cli.
		if (isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['is_cli']) && session_id() == '') {
			trigger_error(Lang::$txt['ssi_session_broken'], E_USER_NOTICE);
		}

		// Without visiting the forum this session variable might not be set on submit.
		if (!isset($_SESSION['USER_AGENT']) && (!isset($_GET['ssi_function']) || $_GET['ssi_function'] !== 'pollVote')) {
			$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
		}

		// Have the ability to easily add functions to SSI.
		IntegrationHook::call('integrate_SSI');

		self::$setup_done = true;
	}

	/**
	 * Allows accessing an SSI function via URL parameters.
	 *
	 * @return true
	 */
	public function execute()
	{
		// Ignore a call to ssi_* functions if we are not accessing SSI.php directly.
		if (basename($_SERVER['SCRIPT_FILENAME']) == 'SSI.php') {
			// You shouldn't just access SSI.php directly by URL!!
			if (!isset($_GET['ssi_function'])) {
				die(sprintf(Lang::$txt['ssi_not_direct'], User::$me->is_admin ? '\'' . addslashes(__FILE__) . '\'' : '\'SSI.php\''));
			}

			// Call a function passed by GET.
			if (method_exists(__CLASS__, $_GET['ssi_function']) && (!empty(Config::$modSettings['allow_guestAccess']) || !User::$me->is_guest)) {
				call_user_func([__CLASS__, $_GET['ssi_function']]);
			}

			exit;
		}

		// To avoid side effects later on.
		unset($_GET['ssi_function']);

		error_reporting($this->error_reporting);

		return true;
	}
}

// Export public static functions to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ServerSideIncludes::exportStatic')) {
	ServerSideIncludes::exportStatic();
}

?>