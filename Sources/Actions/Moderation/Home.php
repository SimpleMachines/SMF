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

namespace SMF\Actions\Moderation;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\PageIndex;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This is the home page of the moderation center.
 */
class Home implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ModerationHome',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * Blocks of data to show on the moderation center home page.
	 */
	public array $blocks = [
		'g' => [
			'func' => 'groupRequests',
			'sub_template' => 'group_requests_block',
			'context_var' => 'group_requests',
			'permissions' => ['can_moderate_groups'],
		],
		'r' => [
			'func' => 'reportedPosts',
			'sub_template' => 'reported_posts_block',
			'context_var' => 'reported_posts',
			'permissions' => ['can_moderate_boards'],
		],
		'w' => [
			'func' => 'watchedUsers',
			'sub_template' => 'watched_users',
			'context_var' => 'watched_users',
			// There are two possible reasons to grant someone access to this.
			'permissions' => ['can_moderate_boards', 'can_moderate_users'],
		],
		'rm' => [
			'func' => 'reportedMembers',
			'sub_template' => 'reported_users_block',
			'context_var' => 'reported_users',
			'permissions' => ['can_moderate_users'],
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
	 * Do the job.
	 */
	public function execute(): void
	{
		// Normally this will already have been done, but just in case...
		Main::checkAccessPermissions();

		Utils::$context['mod_blocks'] = [];

		// Handle moderators' notes.
		$this->notes();

		IntegrationHook::call('integrate_moderation_home_blocks', [&$this->blocks]);

		foreach ($this->blocks as $k => $block) {
			// Define the context variable even if the block is disabled.
			Utils::$context[$block['context_var']] = [];

			// Is this block enabled?
			$enabled = false;

			foreach ($block['permissions'] as $permission) {
				$enabled |= Utils::$context[$permission];
			}

			if (!$enabled) {
				continue;
			}

			if (method_exists($this, $block['func'])) {
				call_user_func([$this, $block['func']]);
			} else {
				$call = Utils::getCallable($block['func']);

				if (!empty($call)) {
					call_user_func($call);
				}
			}

			Utils::$context['mod_blocks'][] = $this->blocks[$k]['sub_template'];
		}

		// Backward compatibility for mods using the integrate_mod_centre_blocks hook.
		self::integrateModBlocks();

		Utils::$context['admin_prefs'] = !empty(Theme::$current->options['admin_preferences']) ? Utils::jsonDecode(Theme::$current->options['admin_preferences'], true) : [];
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
		Theme::loadTemplate('ModerationCenter');
		Theme::loadJavaScriptFile('admin.js', ['minimize' => true], 'smf_admin');

		Utils::$context['page_title'] = Lang::$txt['moderation_center'];
		Utils::$context['sub_template'] = 'moderation_center';
	}

	/**
	 * Show an area for the moderator to type into.
	 */
	protected function notes(): void
	{
		// Set a nice and informative message.
		Utils::$context['report_post_action'] = !empty($_SESSION['rc_confirmation']) ? $_SESSION['rc_confirmation'] : [];

		unset($_SESSION['rc_confirmation']);

		// Are we saving a note?
		if (isset($_GET['modnote'], $_POST['makenote'], $_POST['new_note'])) {
			User::$me->checkSession();
			SecurityToken::validate('mod-modnote-add');

			$_POST['new_note'] = Utils::htmlspecialchars(trim($_POST['new_note']));

			// Make sure they actually entered something.
			if (!empty($_POST['new_note'])) {
				// Insert it into the database then!
				Db::$db->insert(
					'',
					'{db_prefix}log_comments',
					[
						'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
						'body' => 'string', 'log_time' => 'int',
					],
					[
						User::$me->id, User::$me->name, 'modnote', '', $_POST['new_note'], time(),
					],
					['id_comment'],
				);

				// Clear the cache.
				CacheApi::put('moderator_notes', null, 240);
				CacheApi::put('moderator_notes_total', null, 240);
			}

			// Everything went better than expected!
			$_SESSION['rc_confirmation'] = 'message_saved';

			// Redirect otherwise people can resubmit.
			Utils::redirectexit('action=moderate');
		}

		// Bye... bye...
		if (isset($_GET['notes'], $_GET['delete'])   && is_numeric($_GET['delete'])) {
			User::$me->checkSession('get');
			SecurityToken::validate('mod-modnote-del', 'get');

			// No sneaky stuff now!
			if (!User::$me->allowedTo('admin_forum')) {
				// Is this your note?
				$get_owner = Db::$db->query(
					'',
					'SELECT id_member
					FROM {db_prefix}log_comments
					WHERE id_comment = {int:note}
						AND comment_type = {literal:modnote}
						AND id_member = {int:user}',
					[
						'note' => $_GET['delete'],
						'user' => User::$me->id,
					],
				);

				$note_owner = Db::$db->num_rows($get_owner);
				Db::$db->free_result($get_owner);

				if (empty($note_owner)) {
					ErrorHandler::fatalLang('mc_notes_delete_own', false);
				}
			}

			// Lets delete it.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_comments
				WHERE id_comment = {int:note}
					AND comment_type = {literal:modnote}',
				[
					'note' => $_GET['delete'],
				],
			);

			// Clear the cache.
			CacheApi::put('moderator_notes', null, 240);
			CacheApi::put('moderator_notes_total', null, 240);

			// Tell them the message was deleted.
			$_SESSION['rc_confirmation'] = 'message_deleted';

			Utils::redirectexit('action=moderate');
		}

		// How many notes in total?
		if (($moderator_notes_total = CacheApi::get('moderator_notes_total', 240)) === null) {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}log_comments AS lc
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
				WHERE lc.comment_type = {literal:modnote}',
				[
				],
			);
			list($moderator_notes_total) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			CacheApi::put('moderator_notes_total', $moderator_notes_total, 240);
		}

		// Grab the current notes. We can only use the cache for the first page of notes.
		$offset = isset($_GET['notes']) && isset($_GET['start']) ? $_GET['start'] : 0;
		$start = (int) ($_GET['start'] ?? 0);

		if ($offset != 0 || ($moderator_notes = CacheApi::get('moderator_notes', 240)) === null) {
			$moderator_notes = [];

			$request = Db::$db->query(
				'',
				'SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
					lc.log_time, lc.body, lc.id_comment AS id_note
				FROM {db_prefix}log_comments AS lc
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
				WHERE lc.comment_type = {literal:modnote}
				ORDER BY id_comment DESC
				LIMIT {int:offset}, 10',
				[
					'offset' => $offset,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$moderator_notes[] = $row;
			}
			Db::$db->free_result($request);

			if ($offset == 0) {
				CacheApi::put('moderator_notes', $moderator_notes, 240);
			}
		}

		// Lets construct a page index.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=moderate;area=index;notes', $start, $moderator_notes_total, 10);
		Utils::$context['start'] = $start;

		Utils::$context['notes'] = [];

		foreach ($moderator_notes as $note) {
			Utils::$context['notes'][] = [
				'author' => [
					'id' => $note['id_member'],
					'link' => $note['id_member'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $note['id_member'] . '">' . $note['member_name'] . '</a>') : $note['member_name'],
				],
				'time' => Time::create('@' . $note['log_time'])->format(),
				'text' => BBCodeParser::load()->parse($note['body']),
				'delete_href' => Config::$scripturl . '?action=moderate;area=index;notes;delete=' . $note['id_note'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'can_delete' => User::$me->allowedTo('admin_forum') || $note['id_member'] == User::$me->id,
			];
		}

		// Couple tokens for add/delete modnotes
		SecurityToken::create('mod-modnote-add');
		SecurityToken::create('mod-modnote-del', 'get');
	}

	/**
	 * Show a list of all the group requests they can see.
	 */
	protected function groupRequests(): void
	{
		// Make sure they can even moderate someone!
		if (User::$me->mod_cache['gq'] == '0=1') {
			return;
		}

		// What requests are outstanding?
		$request = Db::$db->query(
			'',
			'SELECT lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, mem.member_name, mg.group_name, mem.real_name
			FROM {db_prefix}log_group_requests AS lgr
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
			WHERE ' . (User::$me->mod_cache['gq'] == '1=1' || User::$me->mod_cache['gq'] == '0=1' ? User::$me->mod_cache['gq'] : 'lgr.' . User::$me->mod_cache['gq']) . '
				AND lgr.status = {int:status_open}
			ORDER BY lgr.id_request DESC
			LIMIT 10',
			[
				'status_open' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['group_requests'][] = [
				'id' => $row['id_request'],
				'request_href' => Config::$scripturl . '?action=groups;sa=requests;gid=' . $row['id_group'],
				'member' => [
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
				],
				'group' => [
					'id' => $row['id_group'],
					'name' => $row['group_name'],
				],
				'time_submitted' => Time::create('@' . $row['time_applied'])->format(),
			];
		}
		Db::$db->free_result($request);
	}

	/**
	 * Show a list of the most active watched users.
	 */
	protected function watchedUsers(): void
	{
		if (($watched_users = CacheApi::get('recent_user_watches', 240)) === null) {
			Config::$modSettings['warning_watch'] = empty(Config::$modSettings['warning_watch']) ? 1 : Config::$modSettings['warning_watch'];

			$watched_users = [];

			$request = Db::$db->query(
				'',
				'SELECT id_member, real_name, last_login
				FROM {db_prefix}members
				WHERE warning >= {int:warning_watch}
				ORDER BY last_login DESC
				LIMIT 10',
				[
					'warning_watch' => Config::$modSettings['warning_watch'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$watched_users[] = $row;
			}
			Db::$db->free_result($request);

			CacheApi::put('recent_user_watches', $watched_users, 240);
		}

		foreach ($watched_users as $user) {
			Utils::$context['watched_users'][] = [
				'id' => $user['id_member'],
				'name' => $user['real_name'],
				'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $user['id_member'] . '">' . $user['real_name'] . '</a>',
				'href' => Config::$scripturl . '?action=profile;u=' . $user['id_member'],
				'last_login' => !empty($user['last_login']) ? Time::create('@' . $user['last_login'])->format() : '',
			];
		}
	}

	/**
	 * Show a list of the most recent reported posts.
	 */
	protected function reportedPosts(): void
	{
		if (User::$me->mod_cache['bq'] == '0=1') {
			return;
		}

		// Got the info already?
		$cachekey = md5(Utils::jsonEncode(User::$me->mod_cache['bq']));

		if (($reported_posts = CacheApi::get('reported_posts_' . $cachekey, 90)) === null) {
			// By George, that means we in a position to get the reports, jolly good.
			$reported_posts = [];

			$request = Db::$db->query(
				'',
				'SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject,
					lr.num_reports, COALESCE(mem.real_name, lr.membername) AS author_name,
					COALESCE(mem.id_member, 0) AS id_author
				FROM {db_prefix}log_reported AS lr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
				WHERE ' . (User::$me->mod_cache['bq'] == '1=1' || User::$me->mod_cache['bq'] == '0=1' ? User::$me->mod_cache['bq'] : 'lr.' . User::$me->mod_cache['bq']) . '
					AND lr.id_board != {int:not_a_reported_post}
					AND lr.closed = {int:not_closed}
					AND lr.ignore_all = {int:not_ignored}
				ORDER BY lr.time_updated DESC
				LIMIT 10',
				[
					'not_a_reported_post' => 0,
					'not_closed' => 0,
					'not_ignored' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$reported_posts[] = $row;
			}
			Db::$db->free_result($request);

			CacheApi::put('reported_posts_' . $cachekey, $reported_posts, 90);
		}

		foreach ($reported_posts as $i => $row) {
			Utils::$context['reported_posts'][] = [
				'id' => $row['id_report'],
				'topic_href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'report_href' => Config::$scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $row['id_report'],
				'report_link' => '<a href="' . Config::$scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $row['id_report'] . '">' . $row['subject'] . '</a>',
				'author' => [
					'id' => $row['id_author'],
					'name' => $row['author_name'],
					'link' => $row['id_author'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_author'],
				],
				'subject' => $row['subject'],
				'num_reports' => $row['num_reports'],
			];
		}
	}

	/**
	 * Show a list of the most recent reported members.
	 */
	protected function reportedMembers(): void
	{
		if (!User::$me->allowedTo('moderate_forum')) {
			return;
		}

		$cachekey = md5(Utils::jsonEncode((int) User::$me->allowedTo('moderate_forum')));

		if (($reported_users = CacheApi::get('reported_users_' . $cachekey, 90)) === null) {
			$reported_users = [];

			$request = Db::$db->query(
				'',
				'SELECT lr.id_report, lr.id_member,
					lr.num_reports, COALESCE(mem.real_name, lr.membername) AS user_name,
					COALESCE(mem.id_member, 0) AS id_user
				FROM {db_prefix}log_reported AS lr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
				WHERE lr.id_board = {int:not_a_reported_post}
					AND lr.closed = {int:not_closed}
					AND lr.ignore_all = {int:not_ignored}
				ORDER BY lr.time_updated DESC
				LIMIT 10',
				[
					'not_a_reported_post' => 0,
					'not_closed' => 0,
					'not_ignored' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$reported_users[] = $row;
			}
			Db::$db->free_result($request);

			CacheApi::put('reported_users_' . $cachekey, $reported_users, 90);
		}

		foreach ($reported_users as $i => $row) {
			Utils::$context['reported_users'][] = [
				'id' => $row['id_report'],
				'report_href' => Config::$scripturl . '?action=moderate;area=reportedmembers;report=' . $row['id_report'],
				'user' => [
					'id' => $row['id_user'],
					'name' => $row['user_name'],
					'link' => $row['id_user'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_user'] . '">' . $row['user_name'] . '</a>' : $row['user_name'],
					'href' => Config::$scripturl . '?action=profile;u=' . $row['id_user'],
				],
				'num_reports' => $row['num_reports'],
			];
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Provides a home for the deprecated integrate_mod_centre_blocks hook.
	 *
	 * MOD AUTHORS: Please use the integrate_moderation_home_blocks instead.
	 */
	protected static function integrateModBlocks()
	{
		$valid_blocks = [];

		IntegrationHook::call('integrate_mod_centre_blocks', [&$valid_blocks]);

		if (empty($valid_blocks)) {
			return;
		}

		foreach ($valid_blocks as $k => $func) {
			$func = 'ModBlock' . $func;

			if (is_callable($func)) {
				Utils::$context['mod_blocks'][] = $func();
			}
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Home::exportStatic')) {
	Home::exportStatic();
}

?>