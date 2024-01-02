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
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Who's online, and what are they doing?
 * This class prepares the who's online data for the Who template.
 * It requires the who_view permission.
 * It is enabled with the who_enabled setting.
 * It is accessed via ?action=who.
 *
 * Uses Who template, main sub-template
 * Uses Who language file.
 */
class Who implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Who',
			'determineActions' => 'determineActions',
		],
	];

	/*******************
	 * Public static properties
	 *******************/

	/**
	 * @var array
	 *
	 * Actions that require a specific permission level.
	 */
	public static array $allowedActions = [
		'admin' => ['moderate_forum', 'manage_membergroups', 'manage_bans', 'admin_forum', 'manage_permissions', 'send_mail', 'manage_attachments', 'manage_smileys', 'manage_boards', 'edit_news'],
		'ban' => ['manage_bans'],
		'boardrecount' => ['admin_forum'],
		'calendar' => ['calendar_view'],
		'corefeatures' => ['admin_forum'],
		'editnews' => ['edit_news'],
		'featuresettings' => ['admin_forum'],
		'languages' => ['admin_forum'],
		'logs' => ['admin_forum'],
		'mailing' => ['send_mail'],
		'mailqueue' => ['admin_forum'],
		'maintain' => ['admin_forum'],
		'manageattachments' => ['manage_attachments'],
		'manageboards' => ['manage_boards'],
		'managecalendar' => ['admin_forum'],
		'managesearch' => ['admin_forum'],
		'managesmileys' => ['manage_smileys'],
		'membergroups' => ['manage_membergroups'],
		'mlist' => ['view_mlist'],
		'moderate' => ['access_mod_center', 'moderate_forum', 'manage_membergroups'],
		'modsettings' => ['admin_forum'],
		'news' => ['edit_news', 'send_mail', 'admin_forum'],
		'optimizetables' => ['admin_forum'],
		'packages' => ['admin_forum'],
		'paidsubscribe' => ['admin_forum'],
		'permissions' => ['manage_permissions'],
		'postsettings' => ['admin_forum'],
		'regcenter' => ['admin_forum', 'moderate_forum'],
		'repairboards' => ['admin_forum'],
		'reports' => ['admin_forum'],
		'scheduledtasks' => ['admin_forum'],
		'search' => ['search_posts'],
		'search2' => ['search_posts'],
		'securitysettings' => ['admin_forum'],
		'sengines' => ['admin_forum'],
		'serversettings' => ['admin_forum'],
		'setcensor' => ['moderate_forum'],
		'setreserve' => ['moderate_forum'],
		'stats' => ['view_stats'],
		'theme' => ['admin_forum'],
		'viewerrorlog' => ['admin_forum'],
		'viewmembers' => ['moderate_forum'],
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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		// Permissions, permissions, permissions.
		User::$me->isAllowedTo('who_view');

		// You can't do anything if this is off.
		if (empty(Config::$modSettings['who_enabled'])) {
			ErrorHandler::fatalLang('who_off', false);
		}

		// Discourage robots from indexing this page.
		Utils::$context['robot_no_index'] = true;

		// Sort out... the column sorting.
		$sort_methods = [
			'user' => 'mem.real_name',
			'time' => 'lo.log_time',
		];

		$show_methods = [
			'members' => '(lo.id_member != 0)',
			'guests' => '(lo.id_member = 0)',
			'all' => '1=1',
		];

		// Store the sort methods and the show types for use in the template.
		Utils::$context['sort_methods'] = [
			'user' => Lang::$txt['who_user'],
			'time' => Lang::$txt['who_time'],
		];

		Utils::$context['show_methods'] = [
			'all' => Lang::$txt['who_show_all'],
			'members' => Lang::$txt['who_show_members_only'],
			'guests' => Lang::$txt['who_show_guests_only'],
		];

		// Can they see spiders too?
		if (
			!empty(Config::$modSettings['show_spider_online'])
			&& (
				Config::$modSettings['show_spider_online'] == 2
				|| User::$me->allowedTo('admin_forum')
			)
			&& !empty(Config::$modSettings['spider_name_cache'])
		) {
			$show_methods['spiders'] = '(lo.id_member = 0 AND lo.id_spider > 0)';
			$show_methods['guests'] = '(lo.id_member = 0 AND lo.id_spider = 0)';
			Utils::$context['show_methods']['spiders'] = Lang::$txt['who_show_spiders_only'];
		} elseif (
			empty(Config::$modSettings['show_spider_online'])
			&& isset($_SESSION['who_online_filter'])
			&& $_SESSION['who_online_filter'] == 'spiders'
		) {
			unset($_SESSION['who_online_filter']);
		}

		// Does the user prefer a different sort direction?
		if (isset($_REQUEST['sort'], $sort_methods[$_REQUEST['sort']])) {
			Utils::$context['sort_by'] = $_SESSION['who_online_sort_by'] = $_REQUEST['sort'];
			$sort_method = $sort_methods[$_REQUEST['sort']];
		}
		// Did we set a preferred sort order earlier in the session?
		elseif (isset($_SESSION['who_online_sort_by'])) {
			Utils::$context['sort_by'] = $_SESSION['who_online_sort_by'];
			$sort_method = $sort_methods[$_SESSION['who_online_sort_by']];
		}
		// Default to last time online.
		else {
			Utils::$context['sort_by'] = $_SESSION['who_online_sort_by'] = 'time';
			$sort_method = 'lo.log_time';
		}

		Utils::$context['sort_direction'] = isset($_REQUEST['asc']) || (isset($_REQUEST['sort_dir']) && $_REQUEST['sort_dir'] == 'asc') ? 'up' : 'down';

		$conditions = [];

		if (!User::$me->allowedTo('moderate_forum')) {
			$conditions[] = '(COALESCE(mem.show_online, 1) = 1)';
		}

		// Fallback to top filter?
		if (isset($_REQUEST['submit_top'], $_REQUEST['show_top'])) {
			$_REQUEST['show'] = $_REQUEST['show_top'];
		}

		// Does the user wish to apply a filter?
		if (isset($_REQUEST['show'], $show_methods[$_REQUEST['show']])) {
			Utils::$context['show_by'] = $_SESSION['who_online_filter'] = $_REQUEST['show'];
		}
		// Perhaps we saved a filter earlier in the session?
		elseif (isset($_SESSION['who_online_filter'])) {
			Utils::$context['show_by'] = $_SESSION['who_online_filter'];
		} else {
			Utils::$context['show_by'] = 'members';
		}

		$conditions[] = $show_methods[Utils::$context['show_by']];

		// Get the total amount of members online.
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)' . (!empty($conditions) ? '
			WHERE ' . implode(' AND ', $conditions) : ''),
			[
			],
		);
		list($totalMembers) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Prepare some page index variables.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . '?action=who;sort=' . Utils::$context['sort_by'] . (Utils::$context['sort_direction'] == 'up' ? ';asc' : '') . ';show=' . Utils::$context['show_by'], $_REQUEST['start'], $totalMembers, Config::$modSettings['defaultMaxMembers']);

		Utils::$context['start'] = $_REQUEST['start'];

		// Look for people online, provided they don't mind if you see they are.
		Utils::$context['members'] = [];
		$member_ids = [];
		$url_data = [];

		$request = Db::$db->query(
			'',
			'SELECT
				lo.log_time, lo.id_member, lo.url, lo.ip AS ip, mem.real_name,
				lo.session, mg.online_color, COALESCE(mem.show_online, 1) AS show_online,
				lo.id_spider
			FROM {db_prefix}log_online AS lo
				LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_member} THEN mem.id_post_group ELSE mem.id_group END)' . (!empty($conditions) ? '
			WHERE ' . implode(' AND ', $conditions) : '') . '
			ORDER BY {raw:sort_method} {raw:sort_direction}
			LIMIT {int:offset}, {int:limit}',
			[
				'regular_member' => 0,
				'sort_method' => $sort_method,
				'sort_direction' => Utils::$context['sort_direction'] == 'up' ? 'ASC' : 'DESC',
				'offset' => Utils::$context['start'],
				'limit' => Config::$modSettings['defaultMaxMembers'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$actions = Utils::jsonDecode($row['url'], true);

			if ($actions === []) {
				continue;
			}

			// Send the information to the template.
			Utils::$context['members'][$row['session']] = [
				'id' => $row['id_member'],
				'ip' => User::$me->allowedTo('moderate_forum') ? new IP($row['ip']) : '',
				// It is *going* to be today or yesterday, so why keep that information in there?
				'time' => strtr(Time::create('@' . $row['log_time'])->format(), [Lang::$txt['today'] => '', Lang::$txt['yesterday'] => '']),
				'timestamp' => $row['log_time'],
				'query' => $actions,
				'is_hidden' => $row['show_online'] == 0,
				'id_spider' => $row['id_spider'],
				'color' => empty($row['online_color']) ? '' : $row['online_color'],
			];

			$url_data[$row['session']] = [$row['url'], $row['id_member']];
			$member_ids[] = $row['id_member'];
		}
		Db::$db->free_result($request);

		// Load the user data for these members.
		User::load($member_ids);

		// Are we showing spiders?
		$spiderFormatted = [];

		if (
			!empty(Config::$modSettings['show_spider_online'])
			&& !empty(Config::$modSettings['spider_name_cache'])
			&& (
				Config::$modSettings['show_spider_online'] == 2
				|| User::$me->allowedTo('admin_forum')
			)
		) {
			foreach (Utils::jsonDecode(Config::$modSettings['spider_name_cache'], true) as $id => $name) {
				$spiderFormatted[$id] = [
					'name' => $name,
					'group' => Lang::$txt['spiders'],
					'link' => $name,
					'email' => $name,
				];
			}
		}

		$url_data = self::determineActions($url_data);

		// Setup the linktree and page title (do it down here because the language files are now loaded..)
		Utils::$context['page_title'] = Lang::$txt['who_title'];
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=who',
			'name' => Lang::$txt['who_title'],
		];

		// Put it in the context variables.
		foreach (Utils::$context['members'] as $i => $member) {
			$member['id'] = isset(User::$loaded[$member['id']]) ? $member['id'] : 0;

			$formatted = User::$loaded[$member['id']]->format();

			// Keep the IP that came from the database.
			$formatted['ip'] = $member['ip'];

			if ($member['id'] == 0) {
				if (isset($spiderFormatted[$member['id_spider']])) {
					$formatted = array_merge($formatted, $spiderFormatted[$member['id_spider']]);
				} else {
					$formatted = array_merge($formatted, [
						'link' => Lang::$txt['guest_title'],
						'email' => Lang::$txt['guest_title'],
					]);
				}
			}

			Utils::$context['members'][$i] = array_merge(Utils::$context['members'][$i], $formatted);

			Utils::$context['members'][$i]['action'] = $url_data[$i] ?? ['label' => 'who_hidden', 'class' => 'em'];
		}

		// Some people can't send personal messages...
		Utils::$context['can_send_pm'] = User::$me->allowedTo('pm_send');
		Utils::$context['can_send_email'] = User::$me->allowedTo('moderate_forum');

		// any profile fields disabled?
		Utils::$context['disabled_fields'] = isset(Config::$modSettings['disabled_profile_fields']) ? array_flip(explode(',', Config::$modSettings['disabled_profile_fields'])) : [];
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
	 * This method determines the actions of the members passed in URLs.
	 *
	 * Adding actions to the Who's Online list:
	 * Adding actions to this list is actually relatively easy...
	 *  - for actions anyone should be able to see, just add a string named whoall_ACTION.
	 *    (where ACTION is the action used in index.php.)
	 *  - for actions that have a subaction which should be represented differently, use whoall_ACTION_SUBACTION.
	 *  - for actions that include a topic, and should be restricted, use whotopic_ACTION.
	 *  - for actions that use a message, by msg or quote, use whopost_ACTION.
	 *  - for administrator-only actions, use whoadmin_ACTION.
	 *  - for actions that should be viewable only with certain permissions,
	 *    use whoallow_ACTION and add a list of possible permissions to the
	 *    self::$allowedActions array, using ACTION as the key.
	 *
	 * @param mixed $urls a single url (string) or an array of arrays, each inner array being (JSON-encoded request data, id_member)
	 * @param string|bool $preferred_prefix = false
	 * @return array an array of descriptions if you passed an array, otherwise the string describing their current location.
	 */
	public static function determineActions($urls, $preferred_prefix = false)
	{
		if (!User::$me->allowedTo('who_view')) {
			return [];
		}

		Lang::load('Who');

		IntegrationHook::call('who_allowed', [&self::$allowedActions]);

		if (!is_array($urls)) {
			$url_list = [[$urls, User::$me->id]];
		} else {
			$url_list = $urls;
		}

		// These are done to later query these in large chunks. (instead of one by one.)
		$topic_ids = [];
		$profile_ids = [];
		$board_ids = [];

		$data = [];

		foreach ($url_list as $k => $url) {
			// Get the request parameters..
			$actions = Utils::jsonDecode($url[0], true);

			if ($actions === []) {
				continue;
			}

			// If it's the admin or moderation center, and there is an area set, use that instead.
			if (isset($actions['action']) && ($actions['action'] == 'admin' || $actions['action'] == 'moderate') && isset($actions['area'])) {
				$actions['action'] = $actions['area'];
			}

			// Check if there was no action or the action is display.
			if (!isset($actions['action']) || $actions['action'] == 'display') {
				// It's a topic!  Must be!
				if (isset($actions['topic'])) {
					// Assume they can't view it, and queue it up for later.
					$data[$k] = ['label' => 'who_hidden', 'class' => 'em'];
					$topic_ids[(int) $actions['topic']][$k] = Lang::$txt['who_topic'];
				}
				// It's a board!
				elseif (isset($actions['board'])) {
					// Hide first, show later.
					$data[$k] = ['label' => 'who_hidden', 'class' => 'em'];
					$board_ids[$actions['board']][$k] = Lang::$txt['who_board'];
				}
				// It's the board index!!  It must be!
				else {
					$data[$k] = sprintf(Lang::$txt['who_index'], Config::$scripturl, Utils::$context['forum_name_html_safe']);
				}
			}
			// Probably an error or some goon?
			elseif ($actions['action'] == '') {
				$data[$k] = sprintf(Lang::$txt['who_index'], Config::$scripturl, Utils::$context['forum_name_html_safe']);
			}
			// Some other normal action...?
			else {
				// Viewing/editing a profile.
				if ($actions['action'] == 'profile') {
					// Whose?  Their own?
					if (empty($actions['u'])) {
						$actions['u'] = $url[1];
					}

					$data[$k] = ['label' => 'who_hidden', 'class' => 'em'];
					$profile_ids[(int) $actions['u']][$k] = $actions['u'] == $url[1] ? Lang::$txt['who_viewownprofile'] : Lang::$txt['who_viewprofile'];
				} elseif (($actions['action'] == 'post' || $actions['action'] == 'post2') && empty($actions['topic']) && isset($actions['board'])) {
					$data[$k] = ['label' => 'who_hidden', 'class' => 'em'];
					$board_ids[(int) $actions['board']][$k] = isset($actions['poll']) ? Lang::$txt['who_poll'] : Lang::$txt['who_post'];
				}
				// A subaction anyone can view... if the language string is there, show it.
				elseif (isset($actions['sa'], Lang::$txt['whoall_' . $actions['action'] . '_' . $actions['sa']])) {
					$data[$k] = $preferred_prefix && isset(Lang::$txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']]) ? Lang::$txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']] : sprintf(Lang::$txt['whoall_' . $actions['action'] . '_' . $actions['sa']], Config::$scripturl);
				}
				// An action any old fellow can look at. (if ['whoall_' . $action] exists, we know everyone can see it.)
				elseif (isset(Lang::$txt['whoall_' . $actions['action']])) {
					$data[$k] = $preferred_prefix && isset(Lang::$txt[$preferred_prefix . $actions['action']]) ? Lang::$txt[$preferred_prefix . $actions['action']] : sprintf(Lang::$txt['whoall_' . $actions['action']], Config::$scripturl);
				}
				// Viewable if and only if they can see the board...
				elseif (isset(Lang::$txt['whotopic_' . $actions['action']])) {
					// Find out what topic they are accessing.
					$topic = (int) ($actions['topic'] ?? ($actions['from'] ?? 0));

					$data[$k] = ['label' => 'who_hidden', 'class' => 'em'];
					$topic_ids[$topic][$k] = Lang::$txt['whotopic_' . $actions['action']];
				} elseif (isset(Lang::$txt['whopost_' . $actions['action']])) {
					// Find out what message they are accessing.
					$msgid = (int) ($actions['msg'] ?? ($actions['quote'] ?? 0));

					$result = Db::$db->query(
						'',
						'SELECT m.id_topic, m.subject
						FROM {db_prefix}messages AS m
							' . (Config::$modSettings['postmod_active'] ? 'INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.approved = {int:is_approved})' : '') . '
						WHERE m.id_msg = {int:id_msg}
							AND {query_see_message_board}' . (Config::$modSettings['postmod_active'] ? '
							AND m.approved = {int:is_approved}' : '') . '
						LIMIT 1',
						[
							'is_approved' => 1,
							'id_msg' => $msgid,
						],
					);
					list($id_topic, $subject) = Db::$db->fetch_row($result);
					Db::$db->free_result($result);

					$data[$k] = sprintf(Lang::$txt['whopost_' . $actions['action']], $id_topic, $subject, Config::$scripturl);

					if (empty($id_topic)) {
						$data[$k] = ['label' => 'who_hidden', 'class' => 'em'];
					}
				}
				// Viewable only by administrators.. (if it starts with whoadmin, it's admin only!)
				elseif (User::$me->allowedTo('moderate_forum') && isset(Lang::$txt['whoadmin_' . $actions['action']])) {
					$data[$k] = sprintf(Lang::$txt['whoadmin_' . $actions['action']], Config::$scripturl);
				}
				// Viewable by permission level.
				elseif (isset(self::$allowedActions[$actions['action']])) {
					if (User::$me->allowedTo(self::$allowedActions[$actions['action']]) && !empty(Lang::$txt['whoallow_' . $actions['action']])) {
						$data[$k] = sprintf(Lang::$txt['whoallow_' . $actions['action']], Config::$scripturl);
					} elseif (in_array('moderate_forum', self::$allowedActions[$actions['action']])) {
						$data[$k] = Lang::$txt['who_moderate'];
					} elseif (in_array('admin_forum', self::$allowedActions[$actions['action']])) {
						$data[$k] = Lang::$txt['who_admin'];
					} else {
						$data[$k] = ['label' => 'who_hidden', 'class' => 'em'];
					}
				} elseif (!empty($actions['action'])) {
					$data[$k] = Lang::$txt['who_generic'] . ' ' . $actions['action'];
				} else {
					$data[$k] = ['label' => 'who_unknown', 'class' => 'em'];
				}
			}

			if (isset($actions['error'])) {
				Lang::load('Errors');

				if (isset(Lang::$txt[$actions['error']])) {
					$error_message = str_replace('"', '&quot;', empty($actions['error_params']) ? Lang::$txt[$actions['error']] : vsprintf(Lang::$txt[$actions['error']], (array) $actions['error_params']));
				} elseif ($actions['error'] == 'guest_login') {
					$error_message = str_replace('"', '&quot;', Lang::$txt['who_guest_login']);
				} else {
					$error_message = str_replace('"', '&quot;', $actions['error']);
				}

				if (!empty($error_message)) {
					$error_message = ' <span class="main_icons error" title="' . $error_message . '"></span>';

					if (is_array($data[$k])) {
						$data[$k]['error_message'] = $error_message;
					} else {
						$data[$k] .= $error_message;
					}
				}
			}

			// Maybe the action is integrated into another system?
			if (count($integrate_actions = IntegrationHook::call('integrate_whos_online', [$actions])) > 0) {
				foreach ($integrate_actions as $integrate_action) {
					if (!empty($integrate_action)) {
						$data[$k] = $integrate_action;

						if (isset($actions['topic'], $topic_ids[(int) $actions['topic']][$k])) {
							$topic_ids[(int) $actions['topic']][$k] = $integrate_action;
						}

						if (isset($actions['board'], $board_ids[(int) $actions['board']][$k])) {
							$board_ids[(int) $actions['board']][$k] = $integrate_action;
						}

						if (isset($actions['u'], $profile_ids[(int) $actions['u']][$k])) {
							$profile_ids[(int) $actions['u']][$k] = $integrate_action;
						}
						break;
					}
				}
			}
		}

		// Load topic names.
		if (!empty($topic_ids)) {
			$result = Db::$db->query(
				'',
				'SELECT t.id_topic, m.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE {query_see_topic_board}
					AND t.id_topic IN ({array_int:topic_list})' . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . '
				LIMIT {int:limit}',
				[
					'topic_list' => array_keys($topic_ids),
					'is_approved' => 1,
					'limit' => count($topic_ids),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				// Show the topic's subject for each of the actions.
				foreach ($topic_ids[$row['id_topic']] as $k => $session_text) {
					$data[$k] = sprintf($session_text, $row['id_topic'], Lang::censorText($row['subject']), Config::$scripturl);
				}
			}
			Db::$db->free_result($result);
		}

		// Load board names.
		if (!empty($board_ids)) {
			$result = Db::$db->query(
				'',
				'SELECT b.id_board, b.name
				FROM {db_prefix}boards AS b
				WHERE {query_see_board}
					AND b.id_board IN ({array_int:board_list})
				LIMIT {int:limit}',
				[
					'board_list' => array_keys($board_ids),
					'limit' => count($board_ids),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				// Put the board name into the string for each member...
				foreach ($board_ids[$row['id_board']] as $k => $session_text) {
					$data[$k] = sprintf($session_text, $row['id_board'], $row['name'], Config::$scripturl);
				}
			}
			Db::$db->free_result($result);
		}

		// Load member names for the profile. (is_not_guest permission for viewing their own profile)
		$allow_view_own = User::$me->allowedTo('is_not_guest');
		$allow_view_any = User::$me->allowedTo('profile_view');

		if (!empty($profile_ids) && ($allow_view_any || $allow_view_own)) {
			$result = Db::$db->query(
				'',
				'SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT ' . count($profile_ids),
				[
					'member_list' => array_keys($profile_ids),
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				// If they aren't allowed to view this person's profile, skip it.
				if (!$allow_view_any && (User::$me->id != $row['id_member'])) {
					continue;
				}

				// Set their action on each - session/text to sprintf.
				foreach ($profile_ids[$row['id_member']] as $k => $session_text) {
					$data[$k] = sprintf($session_text, $row['id_member'], $row['real_name'], Config::$scripturl);
				}
			}
			Db::$db->free_result($result);
		}

		IntegrationHook::call('whos_online_after', [&$urls, &$data]);

		if (!is_array($urls)) {
			return $data[0] ?? false;
		}

		return $data;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Load the 'Who' template.
		Theme::loadTemplate('Who');
		Lang::load('Who');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Who::exportStatic')) {
	Who::exportStatic();
}

?>