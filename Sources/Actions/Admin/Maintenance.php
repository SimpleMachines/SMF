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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Actions\TopicRemove;
use SMF\BackwardCompatibility;
use SMF\Cache\CacheApi;
use SMF\Category;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Draft;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\TaskRunner;
use SMF\Theme;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Forum maintenance. Important stuff.
 */
class Maintenance implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageMaintenance',
			'getIntegrationHooksData' => 'getIntegrationHooksData',
			'reattributePosts' => 'reattributePosts',
			'maintainRoutine' => 'MaintainRoutine',
			'maintainDatabase' => 'MaintainDatabase',
			'maintainMembers' => 'MaintainMembers',
			'maintainTopics' => 'MaintainTopics',
			'list_integration_hooks' => 'list_integration_hooks',
			'versionDetail' => 'VersionDetail',
			'maintainFindFixErrors' => 'MaintainFindFixErrors',
			'adminBoardRecount' => 'AdminBoardRecount',
			'rebuildSettingsFile' => 'RebuildSettingsFile',
			'maintainEmptyUnimportantLogs' => 'MaintainEmptyUnimportantLogs',
			'maintainCleanCache' => 'MaintainCleanCache',
			'optimizeTables' => 'OptimizeTables',
			'convertEntities' => 'ConvertEntities',
			'convertMsgBody' => 'ConvertMsgBody',
			'maintainReattributePosts' => 'MaintainReattributePosts',
			'maintainPurgeInactiveMembers' => 'MaintainPurgeInactiveMembers',
			'maintainRecountPosts' => 'MaintainRecountPosts',
			'maintainMassMoveTopics' => 'MaintainMassMoveTopics',
			'maintainRemoveOldPosts' => 'MaintainRemoveOldPosts',
			'maintainRemoveOldDrafts' => 'MaintainRemoveOldDrafts',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// code...

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'routine';

	/**
	 * @var string
	 *
	 * The requested activity within the sub-action.
	 * This should be set by the constructor.
	 */
	public string $activity;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'routine' => [
			'function' => 'routine',
			'template' => 'maintain_routine',
			'activities' => [
				'version' => 'version',
				'repair' => 'repair',
				'recount' => 'recountBoards',
				'rebuild_settings' => 'rebuildSettings',
				'logs' => 'emptyLogs',
				'cleancache' => 'cleanCache',
			],
		],
		'database' => [
			'function' => 'database',
			'template' => 'maintain_database',
			'activities' => [
				'optimize' => 'optimize',
				'convertentities' => 'entitiesToUnicode',
				'convertmsgbody' => 'changeMsgBodyLength',
			],
		],
		'members' => [
			'function' => 'members',
			'template' => 'maintain_members',
			'activities' => [
				'reattribute' => 'reattribute',
				'purgeinactive' => 'purgeInactiveMembers',
				'recountposts' => 'recountPosts',
			],
		],
		'topics' => [
			'function' => 'topics',
			'template' => 'maintain_topics',
			'activities' => [
				'massmove' => 'massMove',
				'pruneold' => 'prunePosts',
				'olddrafts' => 'pruneDrafts',
			],
		],
		'hooks' => [
			'function' => 'hooks',
		],
		'destroy' => [
			'function' => 'destroy',
			'activities' => [],
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	// code...

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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]['function']) ? [$this, self::$subactions[$this->subaction]['function']] : Utils::getCallable(self::$subactions[$this->subaction]['function']);

		if (!empty($call)) {
			call_user_func($call);
		}

		// Any special activity?
		if (!empty($this->activity)) {
			$call = method_exists($this, self::$subactions[$this->subaction]['activities'][$this->activity]) ? [$this, self::$subactions[$this->subaction]['activities'][$this->activity]] : Utils::getCallable(self::$subactions[$this->subaction]['activities'][$this->activity]);

			if (!empty($call)) {
				call_user_func($call);
			}
		}

		// Create a maintenance token.  Kinda hard to do it any other way.
		SecurityToken::create('admin-maint');
	}

	/**
	 * Supporting function for the routine maintenance area.
	 */
	public function routine(): void
	{
		if (isset($_GET['done']) && in_array($_GET['done'], ['recount', 'rebuild_settings'])) {
			Utils::$context['maintenance_finished'] = Lang::$txt['maintain_' . $_GET['done']];
		}
	}

	/**
	 * Supporting function for the database maintenance area.
	 */
	public function database(): void
	{
		// Show some conversion options?
		Utils::$context['convert_entities'] = isset(Config::$modSettings['global_character_set']) && Config::$modSettings['global_character_set'] === 'UTF-8';

		if (Config::$db_type == 'mysql') {
			$colData = Db::$db->list_columns('{db_prefix}messages', true);

			foreach ($colData as $column) {
				if ($column['name'] == 'body') {
					$body_type = $column['type'];
				}
			}

			Utils::$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';

			Utils::$context['convert_to_suggest'] = ($body_type != 'text' && !empty(Config::$modSettings['max_messageLength']) && Config::$modSettings['max_messageLength'] < 65536);
		}

		if (isset($_GET['done']) && $_GET['done'] == 'convertentities') {
			Utils::$context['maintenance_finished'] = Lang::$txt['entity_convert_title'];
		}
	}

	/**
	 * Supporting function for the members maintenance area.
	 */
	public function members(): void
	{
		// Get membergroups - for deleting members and the like.
		Utils::$context['membergroups'] = array_merge(
			[new Group(Group::REGULAR, ['name' => Lang::$txt['maintain_members_ungrouped']])],
			Group::load(),
		);

		if (isset($_GET['done']) && $_GET['done'] == 'recountposts') {
			Utils::$context['maintenance_finished'] = Lang::$txt['maintain_recountposts'];
		}

		Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
	}

	/**
	 * Supporting function for the topics maintenance area.
	 */
	public function topics(): void
	{
		// Let's load up the boards in case they are useful.
		Utils::$context['categories'] = [];

		$result = Db::$db->query(
			'order_by_board_order',
			'SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE {query_see_board}
				AND redirect = {string:blank_redirect}',
			[
				'blank_redirect' => '',
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			if (!isset(Utils::$context['categories'][$row['id_cat']])) {
				Utils::$context['categories'][$row['id_cat']] = [
					'name' => $row['cat_name'],
					'boards' => [],
				];
			}

			Utils::$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = [
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
			];
		}
		Db::$db->free_result($result);

		Category::sort(Utils::$context['categories']);

		if (isset($_GET['done']) && $_GET['done'] == 'purgeold') {
			Utils::$context['maintenance_finished'] = Lang::$txt['maintain_old'];
		} elseif (isset($_GET['done']) && $_GET['done'] == 'massmove') {
			Utils::$context['maintenance_finished'] = Lang::$txt['move_topics_maintenance'];
		}
	}

	/**
	 * Oh noes! I'd document this but that would give it away.
	 */
	public function destroy(): void
	{
		echo '<!DOCTYPE html>
			<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '><head><title>', Utils::$context['forum_name_html_safe'], ' deleted!</title></head>
			<body style="background-color: orange; font-family: arial, sans-serif; text-align: center;">
			<div style="margin-top: 8%; font-size: 400%; color: black;">Oh my, you killed ', Utils::$context['forum_name_html_safe'], '!</div>
			<div style="margin-top: 7%; font-size: 500%; color: red;"><strong>You lazy bum!</strong></div>
			</body></html>';

		Utils::obExit(false);
	}

	/**
	 * Perform a detailed version check.  A very good thing ;).
	 * The function parses the comment headers in all files for their version information,
	 * and outputs that for some javascript to check with simplemachines.org.
	 * It does not connect directly with simplemachines.org, but rather expects the client to.
	 *
	 * It requires the admin_forum permission.
	 * Uses the view_versions admin area.
	 * Accessed through ?action=admin;area=maintain;sa=routine;activity=version.
	 */
	public function version(): void
	{
		User::$me->isAllowedTo('admin_forum');

		// Call the function that'll get all the version info we need.
		$versionOptions = [
			'include_root' => true,
			'include_tasks' => true,
			'sort_results' => true,
		];
		$version_info = ACP::getFileVersions($versionOptions);

		// Add the new info to the template context.
		Utils::$context += [
			'root_versions' => $version_info['root_versions'],
			'file_versions' => $version_info['file_versions'],
			'default_template_versions' => $version_info['default_template_versions'],
			'template_versions' => $version_info['template_versions'],
			'default_language_versions' => $version_info['default_language_versions'],
			'default_known_languages' => array_keys($version_info['default_language_versions']),
			'tasks_versions' => $version_info['tasks_versions'],
		];

		// Make it easier to manage for the template.
		Utils::$context['forum_version'] = SMF_FULL_VERSION;

		Utils::$context['sub_template'] = 'view_versions';
		Utils::$context['page_title'] = Lang::$txt['admin_version_check'];
	}

	/**
	 * Find and fix all errors on the forum.
	 */
	public function repair(): void
	{
		// Honestly, this should be done in the sub function.
		SecurityToken::validate('admin-maint');

		RepairBoards::call();
	}

	/**
	 * Recount many forum totals that can be recounted automatically without harm.
	 * it requires the admin_forum permission.
	 * It shows the maintain_forum admin area.
	 *
	 * Totals recounted:
	 * - fixes for topics with wrong num_replies.
	 * - updates for num_posts and num_topics of all boards.
	 * - recounts instant_messages but not unread_messages.
	 * - repairs messages pointing to boards with topics pointing to other boards.
	 * - updates the last message posted in boards and children.
	 * - updates member count, latest member, topic count, and message count.
	 *
	 * The function redirects back to ?action=admin;area=maintain when complete.
	 * It is accessed via ?action=admin;area=maintain;sa=database;activity=recount.
	 */
	public function recountBoards(): void
	{
		User::$me->isAllowedTo('admin_forum');
		User::$me->checkSession('request');

		// validate the request or the loop
		SecurityToken::validate(!isset($_REQUEST['step']) ? 'admin-maint' : 'admin-boardrecount');
		Utils::$context['not_done_token'] = 'admin-boardrecount';
		SecurityToken::create(Utils::$context['not_done_token']);

		Utils::$context['page_title'] = Lang::$txt['not_done_title'];
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_countdown'] = 3;
		Utils::$context['sub_template'] = 'not_done';

		// Try for as much time as possible.
		@set_time_limit(600);

		// Step the number of topics at a time so things don't time out...
		$request = Db::$db->query(
			'',
			'SELECT MAX(id_topic)
			FROM {db_prefix}topics',
			[
			],
		);
		list($max_topics) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$increment = min(max(50, ceil($max_topics / 4)), 2000);

		if (empty($_REQUEST['start'])) {
			$_REQUEST['start'] = 0;
		}

		$total_steps = 8;

		// Get each topic with a wrong reply count and fix it - let's just do some at a time, though.
		if (empty($_REQUEST['step'])) {
			$_REQUEST['step'] = 0;

			while ($_REQUEST['start'] < $max_topics) {
				// Recount approved messages
				$request = Db::$db->query(
					'',
					'SELECT t.id_topic, MAX(t.num_replies) AS num_replies,
						GREATEST(COUNT(ma.id_msg) - 1, 0) AS real_num_replies
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
					WHERE t.id_topic > {int:start}
						AND t.id_topic <= {int:max_id}
					GROUP BY t.id_topic
					HAVING GREATEST(COUNT(ma.id_msg) - 1, 0) != MAX(t.num_replies)',
					[
						'is_approved' => 1,
						'start' => $_REQUEST['start'],
						'max_id' => $_REQUEST['start'] + $increment,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}topics
						SET num_replies = {int:num_replies}
						WHERE id_topic = {int:id_topic}',
						[
							'num_replies' => $row['real_num_replies'],
							'id_topic' => $row['id_topic'],
						],
					);
				}
				Db::$db->free_result($request);

				// Recount unapproved messages
				$request = Db::$db->query(
					'',
					'SELECT t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
						COUNT(mu.id_msg) AS real_unapproved_posts
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
					WHERE t.id_topic > {int:start}
						AND t.id_topic <= {int:max_id}
					GROUP BY t.id_topic
					HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
					[
						'not_approved' => 0,
						'start' => $_REQUEST['start'],
						'max_id' => $_REQUEST['start'] + $increment,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}topics
						SET unapproved_posts = {int:unapproved_posts}
						WHERE id_topic = {int:id_topic}',
						[
							'unapproved_posts' => $row['real_unapproved_posts'],
							'id_topic' => $row['id_topic'],
						],
					);
				}
				Db::$db->free_result($request);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - TIME_START > 3) {
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
					Utils::$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the post count of each board.
		if ($_REQUEST['step'] <= 1) {
			if (empty($_REQUEST['start'])) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}boards
					SET num_posts = {int:num_posts}
					WHERE redirect = {string:redirect}',
					[
						'num_posts' => 0,
						'redirect' => '',
					],
				);
			}

			while ($_REQUEST['start'] < $max_topics) {
				$request = Db::$db->query(
					'',
					'SELECT m.id_board, COUNT(*) AS real_num_posts
					FROM {db_prefix}messages AS m
					WHERE m.id_topic > {int:id_topic_min}
						AND m.id_topic <= {int:id_topic_max}
						AND m.approved = {int:is_approved}
					GROUP BY m.id_board',
					[
						'id_topic_min' => $_REQUEST['start'],
						'id_topic_max' => $_REQUEST['start'] + $increment,
						'is_approved' => 1,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET num_posts = num_posts + {int:real_num_posts}
						WHERE id_board = {int:id_board}',
						[
							'id_board' => $row['id_board'],
							'real_num_posts' => $row['real_num_posts'],
						],
					);
				}
				Db::$db->free_result($request);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - TIME_START > 3) {
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
					Utils::$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the topic count of each board.
		if ($_REQUEST['step'] <= 2) {
			if (empty($_REQUEST['start'])) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}boards
					SET num_topics = {int:num_topics}',
					[
						'num_topics' => 0,
					],
				);
			}

			while ($_REQUEST['start'] < $max_topics) {
				$request = Db::$db->query(
					'',
					'SELECT t.id_board, COUNT(*) AS real_num_topics
					FROM {db_prefix}topics AS t
					WHERE t.approved = {int:is_approved}
						AND t.id_topic > {int:id_topic_min}
						AND t.id_topic <= {int:id_topic_max}
					GROUP BY t.id_board',
					[
						'is_approved' => 1,
						'id_topic_min' => $_REQUEST['start'],
						'id_topic_max' => $_REQUEST['start'] + $increment,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET num_topics = num_topics + {int:real_num_topics}
						WHERE id_board = {int:id_board}',
						[
							'id_board' => $row['id_board'],
							'real_num_topics' => $row['real_num_topics'],
						],
					);
				}
				Db::$db->free_result($request);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - TIME_START > 3) {
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
					Utils::$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the unapproved post count of each board.
		if ($_REQUEST['step'] <= 3) {
			if (empty($_REQUEST['start'])) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}boards
					SET unapproved_posts = {int:unapproved_posts}',
					[
						'unapproved_posts' => 0,
					],
				);
			}

			while ($_REQUEST['start'] < $max_topics) {
				$request = Db::$db->query(
					'',
					'SELECT m.id_board, COUNT(*) AS real_unapproved_posts
					FROM {db_prefix}messages AS m
					WHERE m.id_topic > {int:id_topic_min}
						AND m.id_topic <= {int:id_topic_max}
						AND m.approved = {int:is_approved}
					GROUP BY m.id_board',
					[
						'id_topic_min' => $_REQUEST['start'],
						'id_topic_max' => $_REQUEST['start'] + $increment,
						'is_approved' => 0,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
						WHERE id_board = {int:id_board}',
						[
							'id_board' => $row['id_board'],
							'unapproved_posts' => $row['real_unapproved_posts'],
						],
					);
				}
				Db::$db->free_result($request);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - TIME_START > 3) {
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
					Utils::$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the unapproved topic count of each board.
		if ($_REQUEST['step'] <= 4) {
			if (empty($_REQUEST['start'])) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}boards
					SET unapproved_topics = {int:unapproved_topics}',
					[
						'unapproved_topics' => 0,
					],
				);
			}

			while ($_REQUEST['start'] < $max_topics) {
				$request = Db::$db->query(
					'',
					'SELECT t.id_board, COUNT(*) AS real_unapproved_topics
					FROM {db_prefix}topics AS t
					WHERE t.approved = {int:is_approved}
						AND t.id_topic > {int:id_topic_min}
						AND t.id_topic <= {int:id_topic_max}
					GROUP BY t.id_board',
					[
						'is_approved' => 0,
						'id_topic_min' => $_REQUEST['start'],
						'id_topic_max' => $_REQUEST['start'] + $increment,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
						WHERE id_board = {int:id_board}',
						[
							'id_board' => $row['id_board'],
							'real_unapproved_topics' => $row['real_unapproved_topics'],
						],
					);
				}
				Db::$db->free_result($request);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - TIME_START > 3) {
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
					Utils::$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Get all members with wrong number of personal messages.
		if ($_REQUEST['step'] <= 5) {
			$request = Db::$db->query(
				'',
				'SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num,
					MAX(mem.instant_messages) AS instant_messages
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
				GROUP BY mem.id_member
				HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
				[
					'is_not_deleted' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				User::updateMemberData($row['id_member'], ['instant_messages' => $row['real_num']]);
			}
			Db::$db->free_result($request);

			$request = Db::$db->query(
				'',
				'SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num,
					MAX(mem.unread_messages) AS unread_messages
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
				GROUP BY mem.id_member
				HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
				[
					'is_not_deleted' => 0,
					'is_not_read' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				User::updateMemberData($row['id_member'], ['unread_messages' => $row['real_num']]);
			}
			Db::$db->free_result($request);

			if (microtime(true) - TIME_START > 3) {
				Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
				Utils::$context['continue_percent'] = round(700 / $total_steps);

				return;
			}
		}

		// Any messages pointing to the wrong board?
		if ($_REQUEST['step'] <= 6) {
			while ($_REQUEST['start'] < Config::$modSettings['maxMsgID']) {
				$request = Db::$db->query(
					'',
					'SELECT t.id_board, m.id_msg
					FROM {db_prefix}messages AS m
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
					WHERE m.id_msg > {int:id_msg_min}
						AND m.id_msg <= {int:id_msg_max}',
					[
						'id_msg_min' => $_REQUEST['start'],
						'id_msg_max' => $_REQUEST['start'] + $increment,
					],
				);
				$boards = [];

				while ($row = Db::$db->fetch_assoc($request)) {
					$boards[$row['id_board']][] = $row['id_msg'];
				}

				Db::$db->free_result($request);

				foreach ($boards as $board_id => $messages) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}messages
						SET id_board = {int:id_board}
						WHERE id_msg IN ({array_int:id_msg_array})',
						[
							'id_msg_array' => $messages,
							'id_board' => $board_id,
						],
					);
				}

				$_REQUEST['start'] += $increment;

				if (microtime(true) - TIME_START > 3) {
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
					Utils::$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / Config::$modSettings['maxMsgID']) / $total_steps);

					return;
				}
			}

			$_REQUEST['start'] = 0;
		}

		// Update the latest message of each board.
		$request = Db::$db->query(
			'',
			'SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
			FROM {db_prefix}messages AS m
			WHERE m.approved = {int:is_approved}
			GROUP BY m.id_board',
			[
				'is_approved' => 1,
			],
		);
		$realBoardCounts = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
		}
		Db::$db->free_result($request);

		$request = Db::$db->query(
			'',
			'SELECT id_board, id_parent, id_last_msg, child_level, id_msg_updated
			FROM {db_prefix}boards',
			[
			],
		);
		$resort_me = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['local_last_msg'] = $realBoardCounts[$row['id_board']] ?? 0;
			$resort_me[$row['child_level']][] = $row;
		}
		Db::$db->free_result($request);

		krsort($resort_me);

		$lastModifiedMsg = [];

		foreach ($resort_me as $rows) {
			foreach ($rows as $row) {
				// The latest message is the latest of the current board and its children.
				if (isset($lastModifiedMsg[$row['id_board']])) {
					$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
				} else {
					$curLastModifiedMsg = $row['local_last_msg'];
				}

				// If what is and what should be the latest message differ, an update is necessary.
				if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated']) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}boards
						SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
						WHERE id_board = {int:id_board}',
						[
							'id_last_msg' => $row['local_last_msg'],
							'id_msg_updated' => $curLastModifiedMsg,
							'id_board' => $row['id_board'],
						],
					);
				}

				// Parent boards inherit the latest modified message of their children.
				if (isset($lastModifiedMsg[$row['id_parent']])) {
					$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
				} else {
					$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
				}
			}
		}

		// Update all the basic statistics.
		Logging::updateStats('member');
		Logging::updateStats('message');
		Logging::updateStats('topic');

		// Finally, update the latest event times.
		TaskRunner::calculateNextTrigger();

		Utils::redirectexit('action=admin;area=maintain;sa=routine;done=recount');
	}

	/**
	 * Rebuilds Settings.php to make it nice and pretty.
	 */
	public function rebuildSettings(): void
	{
		User::$me->isAllowedTo('admin_forum');

		Config::updateSettingsFile([], false, true);

		Utils::redirectexit('action=admin;area=maintain;sa=routine;done=rebuild_settings');
	}

	/**
	 * Empties all uninmportant logs
	 */
	public function emptyLogs(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-maint');

		// No one's online now.... MUHAHAHAHA :P.
		Db::$db->query('', 'DELETE FROM {db_prefix}log_online');

		// Dump the banning logs.
		Db::$db->query('', 'DELETE FROM {db_prefix}log_banned');

		// Start id_error back at 0 and dump the error log.
		Db::$db->query('truncate_table', 'TRUNCATE {db_prefix}log_errors');

		// Clear out the spam log.
		Db::$db->query('', 'DELETE FROM {db_prefix}log_floodcontrol');

		// Last but not least, the search logs!
		Db::$db->query('truncate_table', 'TRUNCATE {db_prefix}log_search_topics');

		Db::$db->query('truncate_table', 'TRUNCATE {db_prefix}log_search_messages');

		Db::$db->query('truncate_table', 'TRUNCATE {db_prefix}log_search_results');

		Config::updateModSettings(['search_pointer' => 0]);

		Utils::$context['maintenance_finished'] = Lang::$txt['maintain_logs'];
	}

	/**
	 * Wipes the whole cache.
	 */
	public function cleanCache(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-maint');

		// Just wipe the whole cache!
		CacheApi::clean();

		Utils::$context['maintenance_finished'] = Lang::$txt['maintain_cache'];
	}

	/**
	 * Optimizes all tables in the database and lists how much was saved.
	 * It requires the admin_forum permission.
	 * It shows as the maintain_forum admin area.
	 * It is accessed from ?action=admin;area=maintain;sa=database;activity=optimize.
	 * It also updates the optimize scheduled task such that the tables are not automatically optimized again too soon.
	 *
	 * @uses template_optimize()
	 */
	public function optimize(): void
	{
		User::$me->isAllowedTo('admin_forum');

		User::$me->checkSession('request');

		if (!isset($_SESSION['optimized_tables'])) {
			SecurityToken::validate('admin-maint');
		} else {
			SecurityToken::validate('admin-optimize', 'post', false);
		}

		ignore_user_abort(true);

		Utils::$context['page_title'] = Lang::$txt['database_optimize'];
		Utils::$context['sub_template'] = 'optimize';
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_countdown'] = 3;

		// Only optimize the tables related to this smf install, not all the tables in the db
		$real_prefix = preg_match('~^(`?)(.+?)\1\.(.*?)$~', Db::$db->prefix, $match) === 1 ? $match[3] : Db::$db->prefix;

		// Get a list of tables, as well as how many there are.
		$temp_tables = Db::$db->list_tables(false, $real_prefix . '%');
		$tables = [];

		foreach ($temp_tables as $table) {
			$tables[] = ['table_name' => $table];
		}

		// If there aren't any tables then I believe that would mean the world has exploded...
		Utils::$context['num_tables'] = count($tables);

		if (Utils::$context['num_tables'] == 0) {
			ErrorHandler::fatal('You appear to be running SMF in a flat file mode... fantastic!', false);
		}

		$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

		// Try for extra time due to large tables.
		@set_time_limit(100);

		// For each table....
		$_SESSION['optimized_tables'] = !empty($_SESSION['optimized_tables']) ? $_SESSION['optimized_tables'] : [];

		for ($key = $_REQUEST['start']; Utils::$context['num_tables'] - 1; $key++) {
			if (empty($tables[$key])) {
				break;
			}

			// Continue?
			if (microtime(true) - TIME_START > 10) {
				$_REQUEST['start'] = $key;
				Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=optimize;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
				Utils::$context['continue_percent'] = round(100 * $_REQUEST['start'] / Utils::$context['num_tables']);
				Utils::$context['sub_template'] = 'not_done';
				Utils::$context['page_title'] = Lang::$txt['not_done_title'];

				SecurityToken::create('admin-optimize');
				Utils::$context['continue_post_data'] = '<input type="hidden" name="' . Utils::$context['admin-optimize_token_var'] . '" value="' . Utils::$context['admin-optimize_token'] . '">';

				if (function_exists('apache_reset_timeout')) {
					apache_reset_timeout();
				}

				return;
			}

			// Optimize the table!  We use backticks here because it might be a custom table.
			$data_freed = Db::$db->optimize_table($tables[$key]['table_name']);

			if ($data_freed > 0) {
				$_SESSION['optimized_tables'][] = [
					'name' => $tables[$key]['table_name'],
					'data_freed' => $data_freed,
				];
			}
		}

		// Number of tables, etc...
		Lang::$txt['database_numb_tables'] = sprintf(Lang::$txt['database_numb_tables'], Utils::$context['num_tables']);
		Utils::$context['num_tables_optimized'] = count($_SESSION['optimized_tables']);
		Utils::$context['optimized_tables'] = $_SESSION['optimized_tables'];
		unset($_SESSION['optimized_tables']);
	}

	/**
	 * Converts HTML-entities to their UTF-8 character equivalents.
	 * This requires the admin_forum permission.
	 * Pre-condition: UTF-8 has been set as database and global character set.
	 *
	 * It is divided in steps of 10 seconds.
	 * This action is linked from the maintenance screen (if applicable).
	 * It is accessed by ?action=admin;area=maintain;sa=database;activity=convertentities.
	 *
	 * @uses template_convert_entities()
	 */
	public function entitiesToUnicode(): void
	{
		User::$me->isAllowedTo('admin_forum');

		// Check to see if UTF-8 is currently the default character set.
		if (Config::$modSettings['global_character_set'] !== 'UTF-8') {
			ErrorHandler::fatalLang('entity_convert_only_utf8');
		}

		// Some starting values.
		Utils::$context['table'] = empty($_REQUEST['table']) ? 0 : (int) $_REQUEST['table'];
		Utils::$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

		Utils::$context['start_time'] = time();

		Utils::$context['first_step'] = !isset($_REQUEST[Utils::$context['session_var']]);
		Utils::$context['last_step'] = false;

		// The first step is just a text screen with some explanation.
		if (Utils::$context['first_step']) {
			SecurityToken::validate('admin-maint');
			SecurityToken::create('admin-maint');

			Utils::$context['sub_template'] = 'convert_entities';

			return;
		}
		// Otherwise use the generic "not done" template.
		Utils::$context['sub_template'] = 'not_done';
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_countdown'] = 3;

		// Now we're actually going to convert...
		User::$me->checkSession('request');
		SecurityToken::validate('admin-maint');
		SecurityToken::create('admin-maint');
		Utils::$context['not_done_token'] = 'admin-maint';

		// A list of tables ready for conversion.
		$tables = [
			'ban_groups',
			'ban_items',
			'boards',
			'calendar',
			'calendar_holidays',
			'categories',
			'log_errors',
			'log_search_subjects',
			'membergroups',
			'members',
			'message_icons',
			'messages',
			'package_servers',
			'personal_messages',
			'pm_recipients',
			'polls',
			'poll_choices',
			'smileys',
			'themes',
		];
		Utils::$context['num_tables'] = count($tables);

		// Loop through all tables that need converting.
		for (; Utils::$context['table'] < Utils::$context['num_tables']; Utils::$context['table']++) {
			$cur_table = $tables[Utils::$context['table']];
			$primary_key = '';
			// Make sure we keep stuff unique!
			$primary_keys = [];

			if (function_exists('apache_reset_timeout')) {
				@apache_reset_timeout();
			}

			// Get a list of text columns.
			$columns = [];

			if (Config::$db_type == 'postgresql') {
				$request = Db::$db->query(
					'',
					'SELECT column_name "Field", data_type "Type"
					FROM information_schema.columns
					WHERE table_name = {string:cur_table}
						AND (data_type = \'character varying\' or data_type = \'text\')',
					[
						'cur_table' => Db::$db->prefix . $cur_table,
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW FULL COLUMNS
					FROM {db_prefix}{raw:cur_table}',
					[
						'cur_table' => $cur_table,
					],
				);
			}

			while ($column_info = Db::$db->fetch_assoc($request)) {
				if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false) {
					$columns[] = strtolower($column_info['Field']);
				}
			}

			// Get the column with the (first) primary key.
			if (Config::$db_type == 'postgresql') {
				$request = Db::$db->query(
					'',
					'SELECT a.attname "Column_name", \'PRIMARY\' "Key_name", attnum "Seq_in_index"
					FROM   pg_index i
					JOIN   pg_attribute a ON a.attrelid = i.indrelid
						AND a.attnum = ANY(i.indkey)
					WHERE  i.indrelid = {string:cur_table}::regclass
						AND    i.indisprimary',
					[
						'cur_table' => Db::$db->prefix . $cur_table,
					],
				);
			} else {
				$request = Db::$db->query(
					'',
					'SHOW KEYS
					FROM {db_prefix}{raw:cur_table}',
					[
						'cur_table' => $cur_table,
					],
				);
			}

			while ($row = Db::$db->fetch_assoc($request)) {
				if ($row['Key_name'] === 'PRIMARY') {
					if ((empty($primary_key) || $row['Seq_in_index'] == 1) && !in_array(strtolower($row['Column_name']), $columns)) {
						$primary_key = $row['Column_name'];
					}

					$primary_keys[] = $row['Column_name'];
				}
			}
			Db::$db->free_result($request);

			// No primary key, no glory.
			// Same for columns. Just to be sure we've work to do!
			if (empty($primary_key) || empty($columns)) {
				continue;
			}

			// Get the maximum value for the primary key.
			$request = Db::$db->query(
				'',
				'SELECT MAX({identifier:key})
				FROM {db_prefix}{raw:cur_table}',
				[
					'key' => $primary_key,
					'cur_table' => $cur_table,
				],
			);
			list($max_value) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (empty($max_value)) {
				continue;
			}

			while (Utils::$context['start'] <= $max_value) {
				// Retrieve a list of rows that has at least one entity to convert.
				$request = Db::$db->query(
					'',
					'SELECT {raw:primary_keys}, {raw:columns}
					FROM {db_prefix}{raw:cur_table}
					WHERE {raw:primary_key} BETWEEN {int:start} AND {int:start} + 499
						AND {raw:like_compare}
					LIMIT 500',
					[
						'primary_keys' => implode(', ', $primary_keys),
						'columns' => implode(', ', $columns),
						'cur_table' => $cur_table,
						'primary_key' => $primary_key,
						'start' => Utils::$context['start'],
						'like_compare' => '(' . implode(' LIKE \'%&#%\' OR ', $columns) . ' LIKE \'%&#%\')',
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$insertion_variables = [];
					$changes = [];

					foreach ($row as $column_name => $column_value) {
						if ($column_name !== $primary_key && strpos($column_value, '&#') !== false) {
							$changes[] = $column_name . ' = {string:changes_' . $column_name . '}';
							$insertion_variables['changes_' . $column_name] = Utils::entityDecode($column_value);
						}
					}

					$where = [];

					foreach ($primary_keys as $key) {
						$where[] = $key . ' = {string:where_' . $key . '}';
						$insertion_variables['where_' . $key] = $row[$key];
					}

					// Update the row.
					if (!empty($changes)) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}' . $cur_table . '
							SET
								' . implode(',
								', $changes) . '
							WHERE ' . implode(' AND ', $where),
							$insertion_variables,
						);
					}
				}
				Db::$db->free_result($request);
				Utils::$context['start'] += 500;

				// After ten seconds interrupt.
				if (time() - Utils::$context['start_time'] > 10) {
					// Calculate an approximation of the percentage done.
					Utils::$context['continue_percent'] = round(100 * (Utils::$context['table'] + (Utils::$context['start'] / $max_value)) / Utils::$context['num_tables'], 1);
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertentities;table=' . Utils::$context['table'] . ';start=' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];

					return;
				}
			}
			Utils::$context['start'] = 0;
		}

		// If we're here, we must be done.
		Utils::$context['continue_percent'] = 100;
		Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;done=convertentities';
		Utils::$context['last_step'] = true;
		Utils::$context['continue_countdown'] = 3;
	}

	/**
	 * Convert the column "body" of the table {db_prefix}messages from TEXT to MEDIUMTEXT and vice versa.
	 * It requires the admin_forum permission.
	 * This is needed only for MySQL.
	 * During the conversion from MEDIUMTEXT to TEXT it check if any of the posts exceed the TEXT length and if so it aborts.
	 * This action is linked from the maintenance screen (if it's applicable).
	 * Accessed by ?action=admin;area=maintain;sa=database;activity=convertmsgbody.
	 *
	 * @uses template_convert_msgbody()
	 */
	public function changeMsgBodyLength(): void
	{
		// Show me your badge!
		User::$me->isAllowedTo('admin_forum');

		if (Config::$db_type != 'mysql') {
			return;
		}

		$colData = Db::$db->list_columns('{db_prefix}messages', true);

		foreach ($colData as $column) {
			if ($column['name'] == 'body') {
				$body_type = $column['type'];
			}
		}

		Utils::$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';

		if ($body_type == 'text' || ($body_type != 'text' && isset($_POST['do_conversion']))) {
			User::$me->checkSession();
			SecurityToken::validate('admin-maint');

			// Make it longer so we can do their limit.
			if ($body_type == 'text') {
				Db::$db->change_column('{db_prefix}messages', 'body', ['type' => 'mediumtext']);
			}
			// Shorten the column so we can have a bit (literally per record) less space occupied
			else {
				Db::$db->change_column('{db_prefix}messages', 'body', ['type' => 'text']);
			}

			// 3rd party integrations may be interested in knowning about this.
			IntegrationHook::call('integrate_convert_msgbody', [$body_type]);

			$colData = Db::$db->list_columns('{db_prefix}messages', true);

			foreach ($colData as $column) {
				if ($column['name'] == 'body') {
					$body_type = $column['type'];
				}
			}

			Utils::$context['maintenance_finished'] = Lang::$txt[Utils::$context['convert_to'] . '_title'];
			Utils::$context['convert_to'] = $body_type == 'text' ? 'mediumtext' : 'text';
			Utils::$context['convert_to_suggest'] = ($body_type != 'text' && !empty(Config::$modSettings['max_messageLength']) && Config::$modSettings['max_messageLength'] < 65536);

			return;
		}

		if ($body_type != 'text' && (!isset($_POST['do_conversion']) || isset($_POST['cont']))) {
			User::$me->checkSession();

			if (empty($_REQUEST['start'])) {
				SecurityToken::validate('admin-maint');
			} else {
				SecurityToken::validate('admin-convertMsg');
			}

			Utils::$context['page_title'] = Lang::$txt['not_done_title'];
			Utils::$context['continue_post_data'] = '';
			Utils::$context['continue_countdown'] = 3;
			Utils::$context['sub_template'] = 'not_done';
			$increment = 500;
			$id_msg_exceeding = isset($_POST['id_msg_exceeding']) ? explode(',', $_POST['id_msg_exceeding']) : [];

			$request = Db::$db->query(
				'',
				'SELECT COUNT(*) as count
				FROM {db_prefix}messages',
				[],
			);
			list($max_msgs) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Try for as much time as possible.
			@set_time_limit(600);

			while ($_REQUEST['start'] < $max_msgs) {
				$request = Db::$db->query(
					'',
					'SELECT id_msg
					FROM {db_prefix}messages
					WHERE id_msg BETWEEN {int:start} AND {int:start} + {int:increment}
						AND LENGTH(body) > 65535',
					[
						'start' => $_REQUEST['start'],
						'increment' => $increment - 1,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$id_msg_exceeding[] = $row['id_msg'];
				}
				Db::$db->free_result($request);

				$_REQUEST['start'] += $increment;

				if (microtime(true) - TIME_START > 3) {
					SecurityToken::create('admin-convertMsg');
					Utils::$context['continue_post_data'] = '
						<input type="hidden" name="' . Utils::$context['admin-convertMsg_token_var'] . '" value="' . Utils::$context['admin-convertMsg_token'] . '">
						<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
						<input type="hidden" name="id_msg_exceeding" value="' . implode(',', $id_msg_exceeding) . '">';

					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=database;activity=convertmsgbody;start=' . $_REQUEST['start'];
					Utils::$context['continue_percent'] = round(100 * $_REQUEST['start'] / $max_msgs);

					return;
				}
			}
			SecurityToken::create('admin-maint');
			Utils::$context['page_title'] = Lang::$txt[Utils::$context['convert_to'] . '_title'];
			Utils::$context['sub_template'] = 'convert_msgbody';

			if (!empty($id_msg_exceeding)) {
				if (count($id_msg_exceeding) > 100) {
					$query_msg = array_slice($id_msg_exceeding, 0, 100);
					Utils::$context['exceeding_messages_morethan'] = sprintf(Lang::$txt['exceeding_messages_morethan'], count($id_msg_exceeding));
				} else {
					$query_msg = $id_msg_exceeding;
				}

				Utils::$context['exceeding_messages'] = [];
				$request = Db::$db->query(
					'',
					'SELECT id_msg, id_topic, subject
					FROM {db_prefix}messages
					WHERE id_msg IN ({array_int:messages})',
					[
						'messages' => $query_msg,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					Utils::$context['exceeding_messages'][] = '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
				}
				Db::$db->free_result($request);
			}
		}
	}

	/**
	 * Re-attribute posts.
	 */
	public function reattribute(): void
	{
		User::$me->checkSession();

		// Find the member.
		$members = User::find($_POST['to']);

		if (empty($members)) {
			ErrorHandler::fatalLang('reattribute_cannot_find_member');
		}

		$memID = array_shift($members);
		$memID = $memID['id'];

		$email = $_POST['type'] == 'email' ? $_POST['from_email'] : '';
		$membername = $_POST['type'] == 'name' ? $_POST['from_name'] : '';

		// Now call the reattribute function.
		self::reattributePosts($memID, $email, $membername, !empty($_POST['posts']));

		Utils::$context['maintenance_finished'] = Lang::$txt['maintain_reattribute_posts'];
	}

	/**
	 * Removing old members. Done and out!
	 *
	 * @todo refactor
	 */
	public function purgeInactiveMembers(): void
	{
		$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];

		if (!empty($_POST['groups']) && $_POST['maxdays'] > 0) {
			User::$me->checkSession();
			SecurityToken::validate('admin-maint');

			$groups = [];

			foreach ($_POST['groups'] as $id => $dummy) {
				$groups[] = (int) $id;
			}
			$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
			$where_vars = [
				'time_limit' => $time_limit,
			];

			if ($_POST['del_type'] == 'activated') {
				$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
				$where_vars['is_activated'] = 0;
			} else {
				$where = 'mem.last_login < {int:time_limit} AND (mem.last_login != 0 OR mem.date_registered < {int:time_limit})';
			}

			// Need to get all groups then work out which (if any) we avoid.
			foreach (Group::loadSimple(Group::LOAD_BOTH, [Group::GUEST, Group::MOD]) as $group) {
				// Avoid this one?
				if (!in_array($group->id, $groups)) {
					// Post group?
					if ($group->min_posts != -1) {
						$where .= ' AND mem.id_post_group != {int:id_post_group_' . $group->id . '}';
						$where_vars['id_post_group_' . $group->id] = $group->id;
					} else {
						$where .= ' AND mem.id_group != {int:id_group_' . $group->id . '} AND FIND_IN_SET({int:id_group_' . $group->id . '}, mem.additional_groups) = 0';
						$where_vars['id_group_' . $group->id] = $group->id;
					}
				}
			}

			// If we have ungrouped unselected we need to avoid those guys.
			if (!in_array(0, $groups)) {
				$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
				$where_vars['blank_add_groups'] = '';
			}

			// Select all the members we're about to murder/remove...
			$request = Db::$db->query(
				'',
				'SELECT mem.id_member, COALESCE(m.id_member, 0) AS is_mod
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
				WHERE ' . $where,
				$where_vars,
			);
			$members = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				if (!$row['is_mod'] || !in_array(3, $groups)) {
					$members[] = $row['id_member'];
				}
			}
			Db::$db->free_result($request);

			User::delete($members);
		}

		Utils::$context['maintenance_finished'] = Lang::$txt['maintain_members'];
		SecurityToken::create('admin-maint');
	}

	/**
	 * Recalculate all members post counts
	 * it requires the admin_forum permission.
	 *
	 * - recounts all posts for members found in the message table
	 * - updates the members post count record in the members table
	 * - honors the boards post count flag
	 * - does not count posts in the recycle bin
	 * - zeros post counts for all members with no posts in the message table
	 * - runs as a delayed loop to avoid server overload
	 * - uses the not_done template in Admin.template
	 *
	 * The function redirects back to action=admin;area=maintain;sa=members when complete.
	 * It is accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
	 */
	public function recountPosts(): void
	{
		// You have to be allowed in here
		User::$me->isAllowedTo('admin_forum');
		User::$me->checkSession('request');

		// Set up to the context.
		Utils::$context['page_title'] = Lang::$txt['not_done_title'];
		Utils::$context['continue_countdown'] = 3;
		Utils::$context['continue_get_data'] = '';
		Utils::$context['sub_template'] = 'not_done';

		// init
		$increment = 200;
		$_REQUEST['start'] = !isset($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];

		// Ask for some extra time, on big boards this may take a bit
		@set_time_limit(600);

		// Only run this query if we don't have the total number of members that have posted
		if (!isset($_SESSION['total_members'])) {
			SecurityToken::validate('admin-maint');

			$request = Db::$db->query(
				'',
				'SELECT COUNT(DISTINCT m.id_member)
				FROM {db_prefix}messages AS m
				JOIN {db_prefix}boards AS b on m.id_board = b.id_board
				WHERE m.id_member != 0
					AND b.count_posts = 0',
				[
				],
			);

			// save it so we don't do this again for this task
			list($_SESSION['total_members']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		} else {
			SecurityToken::validate('admin-recountposts');
		}

		// Lets get a group of members and determine their post count (from the boards that have post count enabled of course).
		$request = Db::$db->query(
			'',
			'SELECT m.id_member, COUNT(*) AS posts
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON m.id_board = b.id_board
			WHERE m.id_member != {int:zero}
				AND b.count_posts = {int:zero}
				' . (!empty(Config::$modSettings['recycle_enable']) ? ' AND b.id_board != {int:recycle}' : '') . '
			GROUP BY m.id_member
			LIMIT {int:start}, {int:number}',
			[
				'start' => $_REQUEST['start'],
				'number' => $increment,
				'recycle' => Config::$modSettings['recycle_board'],
				'zero' => 0,
			],
		);
		$total_rows = Db::$db->num_rows($request);

		// Update the post count for this group
		while ($row = Db::$db->fetch_assoc($request)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
				SET posts = {int:posts}
				WHERE id_member = {int:row}',
				[
					'row' => $row['id_member'],
					'posts' => $row['posts'],
				],
			);
		}
		Db::$db->free_result($request);

		// Continue?
		if ($total_rows == $increment) {
			$_REQUEST['start'] += $increment;
			Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $_REQUEST['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
			Utils::$context['continue_percent'] = round(100 * $_REQUEST['start'] / $_SESSION['total_members']);

			SecurityToken::create('admin-recountposts');
			Utils::$context['continue_post_data'] = '<input type="hidden" name="' . Utils::$context['admin-recountposts_token_var'] . '" value="' . Utils::$context['admin-recountposts_token'] . '">';

			if (function_exists('apache_reset_timeout')) {
				apache_reset_timeout();
			}

			return;
		}

		// final steps ... made more difficult since we don't yet support sub-selects on joins
		// place all members who have posts in the message table in a temp table
		$createTemporary = Db::$db->query(
			'',
			'CREATE TEMPORARY TABLE {db_prefix}tmp_maint_recountposts (
				id_member mediumint(8) unsigned NOT NULL default {string:string_zero},
				PRIMARY KEY (id_member)
			)
			SELECT m.id_member
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON m.id_board = b.id_board
			WHERE m.id_member != {int:zero}
				AND b.count_posts = {int:zero}
				' . (!empty(Config::$modSettings['recycle_enable']) ? ' AND b.id_board != {int:recycle}' : '') . '
			GROUP BY m.id_member',
			[
				'zero' => 0,
				'string_zero' => '0',
				'db_error_skip' => true,
				'recycle' => !empty(Config::$modSettings['recycle_board']) ? Config::$modSettings['recycle_board'] : 0,
			],
		) !== false;

		if ($createTemporary) {
			// outer join the members table on the temporary table finding the members that have a post count but no posts in the message table
			$request = Db::$db->query(
				'',
				'SELECT mem.id_member, mem.posts
				FROM {db_prefix}members AS mem
					LEFT OUTER JOIN {db_prefix}tmp_maint_recountposts AS res
					ON res.id_member = mem.id_member
				WHERE res.id_member IS null
					AND mem.posts != {int:zero}',
				[
					'zero' => 0,
				],
			);

			// set the post count to zero for any delinquents we may have found
			while ($row = Db::$db->fetch_assoc($request)) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}members
					SET posts = {int:zero}
					WHERE id_member = {int:row}',
					[
						'row' => $row['id_member'],
						'zero' => 0,
					],
				);
			}
			Db::$db->free_result($request);
		}

		// all done
		unset($_SESSION['total_members']);
		Utils::$context['maintenance_finished'] = Lang::$txt['maintain_recountposts'];
		Utils::redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
	}

	/**
	 * Moves topics from one board to another.
	 *
	 * @uses template_not_done() to pause the process.
	 */
	public function massMove(): void
	{
		// Only admins.
		User::$me->isAllowedTo('admin_forum');

		User::$me->checkSession('request');
		SecurityToken::validate('admin-maint');

		// Set up to the context.
		Utils::$context['page_title'] = Lang::$txt['not_done_title'];
		Utils::$context['continue_countdown'] = 3;
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_get_data'] = '';
		Utils::$context['sub_template'] = 'not_done';
		Utils::$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
		Utils::$context['start_time'] = time();

		// First time we do this?
		$id_board_from = isset($_REQUEST['id_board_from']) ? (int) $_REQUEST['id_board_from'] : 0;
		$id_board_to = isset($_REQUEST['id_board_to']) ? (int) $_REQUEST['id_board_to'] : 0;
		$max_days = isset($_REQUEST['maxdays']) ? (int) $_REQUEST['maxdays'] : 0;
		$locked = isset($_POST['move_type_locked']) || isset($_GET['locked']);
		$sticky = isset($_POST['move_type_sticky']) || isset($_GET['sticky']);

		// No boards then this is your stop.
		if (empty($id_board_from) || empty($id_board_to)) {
			return;
		}

		// The big WHERE clause
		$conditions = 'WHERE t.id_board = {int:id_board_from}
			AND m.icon != {string:moved}';

		// DB parameters
		$params = [
			'id_board_from' => $id_board_from,
			'moved' => 'moved',
		];

		// Only moving topics not posted in for x days?
		if (!empty($max_days)) {
			$conditions .= '
				AND m.poster_time < {int:poster_time}';
			$params['poster_time'] = time() - 3600 * 24 * $max_days;
		}

		// Moving locked topics?
		if ($locked) {
			$conditions .= '
				AND t.locked = {int:locked}';
			$params['locked'] = 1;
		}

		// What about sticky topics?
		if ($sticky) {
			$conditions .= '
				AND t.is_sticky = {int:sticky}';
			$params['sticky'] = 1;
		}

		// How many topics are we converting?
		if (!isset($_REQUEST['totaltopics'])) {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)' .
				$conditions,
				$params,
			);
			list($total_topics) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		} else {
			$total_topics = (int) $_REQUEST['totaltopics'];
		}

		// Seems like we need this here.
		Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';max_days=' . $max_days;

		if ($locked) {
			Utils::$context['continue_get_data'] .= ';locked';
		}

		if ($sticky) {
			Utils::$context['continue_get_data'] .= ';sticky';
		}

		Utils::$context['continue_get_data'] .= ';start=' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];

		// We have topics to move so start the process.
		if (!empty($total_topics)) {
			while (Utils::$context['start'] <= $total_topics) {
				// Lets get the topics.
				$request = Db::$db->query(
					'',
					'SELECT t.id_topic
					FROM {db_prefix}topics AS t
						INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
					' . $conditions . '
					LIMIT 10',
					$params,
				);

				// Get the ids.
				$topics = [];

				while ($row = Db::$db->fetch_assoc($request)) {
					$topics[] = $row['id_topic'];
				}

				// Just return if we don't have any topics left to move.
				if (empty($topics)) {
					CacheApi::put('board-' . $id_board_from, null, 120);
					CacheApi::put('board-' . $id_board_to, null, 120);
					Utils::redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
				}

				// Lets move them.
				Topic::move($topics, $id_board_to);

				// We've done at least ten more topics.
				Utils::$context['start'] += 10;

				// Lets wait a while.
				if (time() - Utils::$context['start_time'] > 3) {
					// What's the percent?
					Utils::$context['continue_percent'] = round(100 * (Utils::$context['start'] / $total_topics), 1);
					Utils::$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . $id_board_from . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];

					// Let the template system do it's thang.
					return;
				}
			}
		}

		// Don't confuse admins by having an out of date cache.
		CacheApi::put('board-' . $id_board_from, null, 120);
		CacheApi::put('board-' . $id_board_to, null, 120);

		Utils::redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
	}

	/**
	 * Removing old posts doesn't take much as we really pass through.
	 */
	public function prunePosts(): void
	{
		SecurityToken::validate('admin-maint');

		// Actually do what we're told!
		TopicRemove::old();
	}

	/**
	 * Removing old drafts
	 */
	public function pruneDrafts(): void
	{
		SecurityToken::validate('admin-maint');

		$drafts = [];

		// Find all of the old drafts
		$request = Db::$db->query(
			'',
			'SELECT id_draft
			FROM {db_prefix}user_drafts
			WHERE poster_time <= {int:poster_time_old}',
			[
				'poster_time_old' => time() - (86400 * $_POST['draftdays']),
			],
		);

		while ($row = Db::$db->fetch_row($request)) {
			$drafts[] = (int) $row[0];
		}
		Db::$db->free_result($request);

		// If we have old drafts, remove them
		if (count($drafts) > 0) {
			Draft::delete($drafts, false);
		}
	}

	/**
	 * Generates a list of integration hooks for display
	 * Accessed through ?action=admin;area=maintain;sa=hooks;
	 * Allows for removal or disabling of selected hooks
	 */
	public function hooks(): void
	{
		$filter_url = '';
		$current_filter = '';
		$hooks = $this->getIntegrationHooks();
		$hooks_filters = [];

		if (isset($_GET['filter'], $hooks[$_GET['filter']])) {
			$filter_url = ';filter=' . $_GET['filter'];
			$current_filter = $_GET['filter'];
		}
		$filtered_hooks = array_filter(
			$hooks,
			function ($hook) use ($current_filter) {
				return $current_filter == '' || $current_filter == $hook;
			},
			ARRAY_FILTER_USE_KEY,
		);
		ksort($hooks);

		foreach ($hooks as $hook => $functions) {
			$hooks_filters[] = '<option' . ($current_filter == $hook ? ' selected ' : '') . ' value="' . $hook . '">' . $hook . '</option>';
		}

		if (!empty($hooks_filters)) {
			Utils::$context['insert_after_template'] .= '
			<script>
				var hook_name_header = document.getElementById(\'header_list_integration_hooks_hook_name\');
				hook_name_header.innerHTML += ' . Utils::JavaScriptEscape('<select style="margin-left:15px;" onchange="window.location=(\'' . Config::$scripturl . '?action=admin;area=maintain;sa=hooks\' + (this.value ? \';filter=\' + this.value : \'\'));"><option value="">' . Lang::$txt['hooks_reset_filter'] . '</option>' . implode('', $hooks_filters) . '</select>') . ';
			</script>';
		}

		if (!empty($_REQUEST['do']) && isset($_REQUEST['hook'], $_REQUEST['function'])) {
			User::$me->checkSession('request');
			SecurityToken::validate('admin-hook', 'request');

			if ($_REQUEST['do'] == 'remove') {
				IntegrationHook::remove($_REQUEST['hook'], urldecode($_REQUEST['function']));
			} else {
				// Disable/enable logic; always remove exactly what was passed
				$function_remove = urldecode($_REQUEST['function']);
				$function_add = urldecode(rtrim($_REQUEST['function'], '!')) . (($_REQUEST['do'] == 'disable') ? '!' : '');

				IntegrationHook::remove($_REQUEST['hook'], $function_remove);
				IntegrationHook::add($_REQUEST['hook'], $function_add);
			}

			Utils::redirectexit('action=admin;area=maintain;sa=hooks' . $filter_url);
		}

		SecurityToken::create('admin-hook', 'request');

		$list_options = [
			'id' => 'list_integration_hooks',
			'title' => Lang::$txt['hooks_title_list'],
			'items_per_page' => 20,
			'base_href' => Config::$scripturl . '?action=admin;area=maintain;sa=hooks' . $filter_url . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'default_sort_col' => 'hook_name',
			'get_items' => [
				'function' => __CLASS__ . '::getIntegrationHooksData',
				'params' => [
					$filtered_hooks,
					strtr(Config::$boarddir, '\\', '/'),
					strtr(Config::$sourcedir, '\\', '/'),
				],
			],
			'get_count' => [
				'value' => array_reduce(
					$filtered_hooks,
					function ($accumulator, $functions) {
						return $accumulator + count($functions);
					},
					0,
				),
			],
			'no_items_label' => Lang::$txt['hooks_no_hooks'],
			'columns' => [
				'hook_name' => [
					'header' => [
						'value' => Lang::$txt['hooks_field_hook_name'],
					],
					'data' => [
						'db' => 'hook_name',
					],
					'sort' => [
						'default' => 'hook_name',
						'reverse' => 'hook_name DESC',
					],
				],
				'function_name' => [
					'header' => [
						'value' => Lang::$txt['hooks_field_function_name'],
					],
					'data' => [
						'function' => function ($data) {
							// Show a nice icon to indicate this is an instance.
							$instance = (!empty($data['instance']) ? '<span class="main_icons news" title="' . Lang::$txt['hooks_field_function_method'] . '"></span> ' : '');

							if (!empty($data['included_file']) && !empty($data['real_function'])) {
								return $instance . Lang::$txt['hooks_field_function'] . ': ' . $data['real_function'] . '<br>' . Lang::$txt['hooks_field_included_file'] . ': ' . $data['included_file'];
							}

							return $instance . $data['real_function'];
						},
						'class' => 'word_break',
					],
					'sort' => [
						'default' => 'function_name',
						'reverse' => 'function_name DESC',
					],
				],
				'file_name' => [
					'header' => [
						'value' => Lang::$txt['hooks_field_file_name'],
					],
					'data' => [
						'db' => 'file_name',
						'class' => 'word_break',
					],
					'sort' => [
						'default' => 'file_name',
						'reverse' => 'file_name DESC',
					],
				],
				'status' => [
					'header' => [
						'value' => Lang::$txt['hooks_field_hook_exists'],
						'style' => 'width:3%;',
					],
					'data' => [
						'function' => function ($data) use ($filter_url) {
							// Cannot update temp hooks in any way, really.  Just show the appropriate icon.
							if ($data['status'] == 'temp') {
								return '<span class="main_icons ' . ($data['hook_exists'] ? 'posts' : 'error') . '" title="' . $data['img_text'] . '"></span>';
							}

							$change_status = ['before' => '', 'after' => ''];

							// Can only enable/disable if it exists...
							if ($data['hook_exists']) {
								$change_status['before'] = '<a href="' . Config::$scripturl . '?action=admin;area=maintain;sa=hooks;do=' . ($data['enabled'] ? 'disable' : 'enable') . ';hook=' . $data['hook_name'] . ';function=' . urlencode($data['function_name']) . $filter_url . ';' . Utils::$context['admin-hook_token_var'] . '=' . Utils::$context['admin-hook_token'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" data-confirm="' . Lang::$txt['quickmod_confirm'] . '" class="you_sure">';
								$change_status['after'] = '</a>';
							}

							return $change_status['before'] . '<span class="main_icons post_moderation_' . $data['status'] . '" title="' . $data['img_text'] . '"></span>' . $change_status['after'];
						},
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'status',
						'reverse' => 'status DESC',
					],
				],
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => Lang::$txt['hooks_disable_instructions'] . '<br>
						' . Lang::$txt['hooks_disable_legend'] . ':
					<ul style="list-style: none;">
						<li><span class="main_icons post_moderation_allow"></span> ' . Lang::$txt['hooks_disable_legend_exists'] . '</li>
						<li><span class="main_icons post_moderation_moderate"></span> ' . Lang::$txt['hooks_disable_legend_disabled'] . '</li>
						<li><span class="main_icons post_moderation_deny"></span> ' . Lang::$txt['hooks_disable_legend_missing'] . '</li>
						<li><span class="main_icons posts"></span> ' . Lang::$txt['hooks_disable_legend_temp'] . '</li>
						<li><span class="main_icons error"></span> ' . Lang::$txt['hooks_disable_legend_temp_missing'] . '</li>
					</ul>',
				],
			],
		];

		$list_options['columns']['remove'] = [
			'header' => [
				'value' => Lang::$txt['hooks_button_remove'],
				'style' => 'width:3%',
			],
			'data' => [
				'function' => function ($data) use ($filter_url) {
					// Note: Cannot remove temp hooks via the UI...
					if (!$data['hook_exists'] && $data['status'] != 'temp') {
						return '
						<a href="' . Config::$scripturl . '?action=admin;area=maintain;sa=hooks;do=remove;hook=' . $data['hook_name'] . ';function=' . urlencode($data['function_name']) . $filter_url . ';' . Utils::$context['admin-hook_token_var'] . '=' . Utils::$context['admin-hook_token'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" data-confirm="' . Lang::$txt['quickmod_confirm'] . '" class="you_sure">
							<span class="main_icons delete" title="' . Lang::$txt['hooks_button_remove'] . '"></span>
						</a>';
					}
				},
				'class' => 'centertext',
			],
		];
		$list_options['form'] = [
			'href' => Config::$scripturl . '?action=admin;area=maintain;sa=hooks' . $filter_url . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'name' => 'list_integration_hooks',
		];

		new ItemList($list_options);

		Utils::$context['page_title'] = Lang::$txt['hooks_title_list'];
		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'list_integration_hooks';
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
	 * Callback function for the integration hooks list (list_integration_hooks)
	 * Gets all of the hooks in the system and their status
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $per_page How many items to display on each page
	 * @param string $sort A string indicating how to sort things
	 * @return array An array of information about the integration hooks
	 */
	public static function getIntegrationHooksData($start, $per_page, $sort, $filtered_hooks, $normalized_boarddir, $normalized_sourcedir): array
	{
		$function_list = $sort_array = $temp_data = [];
		$files = self::getFileRecursive($normalized_sourcedir);

		foreach ($files as $currentFile => $fileInfo) {
			$function_list += self::getDefinedFunctionsInFile($currentFile);
		}

		$sort_types = [
			'hook_name' => ['hook_name', SORT_ASC],
			'hook_name DESC' => ['hook_name', SORT_DESC],
			'function_name' => ['function_name', SORT_ASC],
			'function_name DESC' => ['function_name', SORT_DESC],
			'file_name' => ['file_name', SORT_ASC],
			'file_name DESC' => ['file_name', SORT_DESC],
			'status' => ['status', SORT_ASC],
			'status DESC' => ['status', SORT_DESC],
		];

		foreach ($filtered_hooks as $hook => $functions) {
			foreach ($functions as $rawFunc) {
				$hookParsedData = self::parseIntegrationHook($hook, $rawFunc);

				// Handle hooks pointing outside the sources directory.
				$absPath_clean =  rtrim($hookParsedData['absPath'], '!');

				if ($absPath_clean != '' && !isset($files[$absPath_clean]) && file_exists($absPath_clean)) {
					$function_list += self::getDefinedFunctionsInFile($absPath_clean);
				}

				$hook_exists = isset($function_list[$hookParsedData['call']]) || (substr($hook, -8) === '_include' && isset($files[$absPath_clean]));
				$hook_temp = !empty(Utils::$context['integration_hooks_temporary'][$hook][$hookParsedData['rawData']]);
				$temp = [
					'hook_name' => $hook,
					'function_name' => $hookParsedData['rawData'],
					'real_function' => $hookParsedData['call'],
					'included_file' => $hookParsedData['hookFile'],
					'file_name' => strtr($hookParsedData['absPath'] ?: ($function_list[$hookParsedData['call']] ?? ''), [$normalized_boarddir => '.']),
					'instance' => $hookParsedData['object'],
					'hook_exists' => $hook_exists,
					'status' => ($hook_temp ? 'temp' : ($hook_exists ? ($hookParsedData['enabled'] ? 'allow' : 'moderate') : 'deny')),
					'img_text' => Lang::$txt['hooks_' . ($hook_exists ? ($hook_temp ? 'temp' : ($hookParsedData['enabled'] ? 'active' : 'disabled')) : 'missing')],
					'enabled' => $hookParsedData['enabled'],
				];
				$sort_array[] = $temp[$sort_types[$sort][0]];
				$temp_data[] = $temp;
			}
		}

		array_multisort($sort_array, $sort_types[$sort][1], $temp_data);

		return array_slice($temp_data, $start, $per_page, true);
	}

	/**
	 * This method is used to reassociate members with relevant posts.
	 *
	 * Does not check for any permissions.
	 * If $post_count is set, the member's post count is increased.
	 *
	 * @param int $memID The ID of the original poster.
	 * @param bool|string $email If set, should be the email of the poster.
	 * @param bool|string $membername If set, the membername of the poster.
	 * @param bool $post_count Whether to adjust post counts.
	 * @return array The numbers of messages, topics, and reports updated.
	 */
	public static function reattributePosts(int $memID, ?string $email = null, ?string $membername = null, bool $post_count = false)
	{
		$updated = [
			'messages' => 0,
			'topics' => 0,
			'reports' => 0,
		];

		// Firstly, if email and username aren't passed find out the members email address and name.
		if ($email === null && $membername === null) {
			$request = Db::$db->query(
				'',
				'SELECT email_address, member_name
				FROM {db_prefix}members
				WHERE id_member = {int:memID}
				LIMIT 1',
				[
					'memID' => $memID,
				],
			);
			list($email, $membername) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// If they want the post count restored then we need to do some research.
		if ($post_count) {
			$recycle_board = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']) ? (int) Config::$modSettings['recycle_board'] : 0;

			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND b.count_posts = {int:count_posts})
				WHERE m.id_member = {int:guest_id}
					AND m.approved = {int:is_approved}' . (!empty($recycle_board) ? '
					AND m.id_board != {int:recycled_board}' : '') . (empty($email) ? '' : '
					AND m.poster_email = {string:email_address}') . (empty($membername) ? '' : '
					AND m.poster_name = {string:member_name}'),
				[
					'count_posts' => 0,
					'guest_id' => 0,
					'email_address' => $email,
					'member_name' => $membername,
					'is_approved' => 1,
					'recycled_board' => $recycle_board,
				],
			);
			list($messageCount) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			User::updateMemberData($memID, ['posts' => 'posts + ' . $messageCount]);
		}

		$query_parts = [];

		if (!empty($email)) {
			$query_parts[] = 'poster_email = {string:email_address}';
		}

		if (!empty($membername)) {
			$query_parts[] = 'poster_name = {string:member_name}';
		}

		$query = implode(' AND ', $query_parts);

		// Finally, update the posts themselves!
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_member = {int:memID}
			WHERE ' . $query,
			[
				'memID' => $memID,
				'email_address' => $email,
				'member_name' => $membername,
			],
		);
		$updated['messages'] = Db::$db->affected_rows();

		// Did we update any messages?
		if ($updated['messages'] > 0) {
			// First, check for updated topics.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics AS t
				SET id_member_started = {int:memID}
				WHERE t.id_first_msg = (
					SELECT m.id_msg
					FROM {db_prefix}messages m
					WHERE m.id_member = {int:memID}
						AND m.id_msg = t.id_first_msg
						AND ' . $query . '
					)',
				[
					'memID' => $memID,
					'email_address' => $email,
					'member_name' => $membername,
				],
			);
			$updated['topics'] = Db::$db->affected_rows();

			// Second, check for updated reports.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}log_reported AS lr
				SET id_member = {int:memID}
				WHERE lr.id_msg = (
					SELECT m.id_msg
					FROM {db_prefix}messages m
					WHERE m.id_member = {int:memID}
						AND m.id_msg = lr.id_msg
						AND ' . $query . '
					)',
				[
					'memID' => $memID,
					'email_address' => $email,
					'member_name' => $membername,
				],
			);
			$updated['reports'] = Db::$db->affected_rows();
		}

		// Allow mods with their own post tables to reattribute posts as well :)
		IntegrationHook::call('integrate_reattribute_posts', [$memID, $email, $membername, $post_count, &$updated]);

		return $updated;
	}

	/**
	 * Backward compatibility wrapper for the routine sub-action.
	 */
	public static function maintainRoutine(): void
	{
		self::load();
		self::$obj->subaction = 'routine';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the database sub-action.
	 */
	public static function maintainDatabase(): void
	{
		self::load();
		self::$obj->subaction = 'database';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the members sub-action.
	 */
	public static function maintainMembers(): void
	{
		self::load();
		self::$obj->subaction = 'members';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the topics sub-action.
	 */
	public static function maintainTopics(): void
	{
		self::load();
		self::$obj->subaction = 'topics';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the hooks sub-action.
	 */
	public static function list_integration_hooks(): void
	{
		self::load();
		self::$obj->subaction = 'hooks';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the version activity.
	 */
	public static function versionDetail(): void
	{
		self::load();
		self::$obj->subaction = 'routine';
		self::$obj->activity = 'version';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the repair activity.
	 */
	public static function maintainFindFixErrors(): void
	{
		self::load();
		self::$obj->subaction = 'routine';
		self::$obj->activity = 'repair';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the recount activity.
	 */
	public static function adminBoardRecount(): void
	{
		self::load();
		self::$obj->subaction = 'routine';
		self::$obj->activity = 'recount';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the rebuild_settings activity.
	 */
	public static function rebuildSettingsFile(): void
	{
		self::load();
		self::$obj->subaction = 'routine';
		self::$obj->activity = 'rebuild_settings';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the logs activity.
	 */
	public static function maintainEmptyUnimportantLogs(): void
	{
		self::load();
		self::$obj->subaction = 'routine';
		self::$obj->activity = 'logs';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the cleancache activity.
	 */
	public static function maintainCleanCache(): void
	{
		self::load();
		self::$obj->subaction = 'routine';
		self::$obj->activity = 'cleancache';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the optimize activity.
	 */
	public static function optimizeTables(): void
	{
		self::load();
		self::$obj->subaction = 'database';
		self::$obj->activity = 'optimize';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the convertentities activity.
	 */
	public static function convertEntities(): void
	{
		self::load();
		self::$obj->subaction = 'database';
		self::$obj->activity = 'convertentities';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the convertmsgbody activity.
	 */
	public static function convertMsgBody(): void
	{
		self::load();
		self::$obj->subaction = 'database';
		self::$obj->activity = 'convertmsgbody';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the reattribute activity.
	 */
	public static function maintainReattributePosts(): void
	{
		self::load();
		self::$obj->subaction = 'members';
		self::$obj->activity = 'reattribute';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the purgeinactive activity.
	 */
	public static function maintainPurgeInactiveMembers(): void
	{
		self::load();
		self::$obj->subaction = 'members';
		self::$obj->activity = 'purgeinactive';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the recountposts activity.
	 */
	public static function maintainRecountPosts(): void
	{
		self::load();
		self::$obj->subaction = 'members';
		self::$obj->activity = 'recountposts';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the massmove activity.
	 */
	public static function maintainMassMoveTopics(): void
	{
		self::load();
		self::$obj->subaction = 'topics';
		self::$obj->activity = 'massmove';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the pruneold activity.
	 */
	public static function maintainRemoveOldPosts(): void
	{
		self::load();
		self::$obj->subaction = 'topics';
		self::$obj->activity = 'pruneold';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the olddrafts activity.
	 */
	public static function maintainRemoveOldDrafts(): void
	{
		self::load();
		self::$obj->subaction = 'topics';
		self::$obj->activity = 'olddrafts';
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
		// You absolutely must be an admin by here!
		User::$me->isAllowedTo('admin_forum');

		// Need something to talk about?
		Lang::load('ManageMaintenance');
		Theme::loadTemplate('ManageMaintenance');

		// This uses admin tabs - as it should!
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['maintain_title'],
			'description' => Lang::$txt['maintain_info'],
			'tabs' => [
				'routine' => [],
				'database' => [],
				'members' => [],
				'topics' => [],
			],
		];

		IntegrationHook::call('integrate_manage_maintenance', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		// Doing something special?
		if (isset($_REQUEST['activity'], self::$subactions[$this->subaction]['activities'][$_REQUEST['activity']])) {
			$this->activity = $_REQUEST['activity'];
		}

		// Set a few things.
		Utils::$context['page_title'] = Lang::$txt['maintain_title'];
		Utils::$context['sub_action'] = $this->subaction;
		Utils::$context['sub_template'] = !empty(self::$subactions[$this->subaction]['template']) ? self::$subactions[$this->subaction]['template'] : '';
	}

	/**
	 * Parses modSettings to create integration hook array
	 *
	 * @return array An array of information about the integration hooks
	 */
	protected function getIntegrationHooks(): array
	{
		static $integration_hooks;

		if (!isset($integration_hooks)) {
			$integration_hooks = [];

			foreach (Config::$modSettings as $key => $value) {
				if (!empty($value) && substr($key, 0, 10) === 'integrate_') {
					$integration_hooks[$key] = explode(',', $value);
				}
			}
		}

		return $integration_hooks;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Gets all of the files in a directory and its children directories
	 *
	 * @param string $dirname The path to the directory
	 * @return array An array containing information about the files found in the specified directory and its children
	 */
	protected static function getFileRecursive(string $dirname): array
	{
		return \iterator_to_array(
			new \RecursiveIteratorIterator(
				new \RecursiveCallbackFilterIterator(
					new \RecursiveDirectoryIterator($dirname, \FilesystemIterator::UNIX_PATHS),
					function ($fileInfo, $currentFile, $iterator) {
						// Allow recursion
						if ($iterator->hasChildren()) {
							return true;
						}

						return $fileInfo->getExtension() == 'php';
					},
				),
			),
		);
	}

	/**
	 * Parses each hook data and returns an array.
	 *
	 * @param string $hook
	 * @param string $rawData A string as it was saved to the DB.
	 * @return array everything found in the string itself
	 */
	protected static function parseIntegrationHook(string $hook, string $rawData): array
	{
		// A single string can hold tons of info!
		$hookData = [
			'object' => false,
			'enabled' => true,
			'absPath' => '',
			'hookFile' => '',
			'pureFunc' => '',
			'method' => '',
			'class' => '',
			'call' => '',
			'rawData' => $rawData,
		];

		// Meh...
		if (empty($rawData)) {
			return $hookData;
		}

		$modFunc = $rawData;

		// Any files?
		if (substr($hook, -8) === '_include') {
			$modFunc = $modFunc . '|';
		}

		if (strpos($modFunc, '|') !== false) {
			list($hookData['hookFile'], $modFunc) = explode('|', $modFunc);
			$hookData['absPath'] = strtr(strtr(trim($hookData['hookFile']), ['$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => Theme::$current->settings['theme_dir'] ?? '']), '\\', '/');
		}

		// Hook is an instance.
		if (strpos($modFunc, '#') !== false) {
			$modFunc = str_replace('#', '', $modFunc);
			$hookData['object'] = true;
		}

		// Hook is "disabled"
		// May need to inspect $rawData here for includes...
		if ((strpos($modFunc, '!') !== false) || (empty($modFunc) && (strpos($rawData, '!') !== false))) {
			$modFunc = str_replace('!', '', $modFunc);
			$hookData['enabled'] = false;
		}

		// Handling methods?
		if (strpos($modFunc, '::') !== false) {
			list($hookData['class'], $hookData['method']) = explode('::', $modFunc);
			$hookData['pureFunc'] = $hookData['method'];
			$hookData['call'] = $modFunc;
		} else {
			$hookData['call'] = $hookData['pureFunc'] = $modFunc;
		}

		return $hookData;
	}

	protected static function getDefinedFunctionsInFile(string $file): array
	{
		$source = file_get_contents($file);
		// token_get_all() is too slow so use a nice little regex instead.
		preg_match_all('/\bnamespace\s++((?P>label)(?:\\\(?P>label))*+)\s*+;|\bclass\s++((?P>label))[\w\s]*+{|\bfunction\s++((?P>label))\s*+\(.*\)[:\|\w\s]*+{(?(DEFINE)(?<label>[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*+))/i', $source, $matches, PREG_SET_ORDER);

		$functions = [];
		$namespace = '';
		$class = '';

		foreach ($matches as $match) {
			if (!empty($match[1])) {
				$namespace = $match[1] . '\\';
			} elseif (!empty($match[2])) {
				$class = $namespace . $match[2] . '::';
			} elseif (!empty($match[3])) {
				$functions[$class . $match[3]] = $file;
			}
		}

		return $functions;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Maintenance::exportStatic')) {
	Maintenance::exportStatic();
}

?>