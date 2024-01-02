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
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * The moderation and adminstration logs are this class's only job.
 * It views them, and that's about all it does.
 */
class Logs implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ViewModlog',
			'list_getModLogEntryCount' => 'list_getModLogEntryCount',
			'list_getModLogEntries' => 'list_getModLogEntries',
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
	 * The requested action.
	 * This should be set by the constructor.
	 */
	public string $action = 'moderate';

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'modlog';

	/**
	 * @var int
	 *
	 * How many log entries to show per page.
	 */
	public int $per_page = 30;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Actions that might call this class.
	 */
	public static array $actions = [
		'moderate',
		'admin',
	];

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'modlog' => 'modlog',
		'adminlog' => 'adminlog',
	];

	/**
	 * @var array
	 *
	 * Actions whose log entries cannot be deleted.
	 */
	public static array $uneditable_actions = [
		'agreement_updated',
		'policy_updated',
	];

	/**
	 * @var array
	 *
	 * Instructions for how to sort columns.
	 */
	public static array $sort_types = [
		'action' => 'lm.action',
		'time' => 'lm.log_time',
		'member' => 'mem.real_name',
		'group' => 'mg.group_name',
		'ip' => 'lm.ip',
	];

	/**
	 * @var array
	 *
	 * This array houses all the valid search types.
	 */
	public static array $search_types = [
		'action' => [
			'sql' => 'lm.action',
			'label' => 'modlog_action',
		],
		'member' => [
			'sql' => 'mem.real_name',
			'label' => 'modlog_member',
		],
		'group' => [
			'sql' => 'mg.group_name',
			'label' => 'modlog_position',
		],
		'ip' => [
			'sql' => 'lm.ip',
			'label' => 'modlog_ip',
		],
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * The log type to show.
	 * 1 for the moderation log.
	 * 3 for the administration log.
	 */
	protected int $log_type = 1;

	/**
	 * @var bool
	 *
	 * Whether the current user can delete log entries.
	 */
	protected bool $can_delete = false;

	/**
	 * @var string
	 *
	 *
	 */
	protected string $url_start = '';

	/**
	 * @var string
	 *
	 *
	 */
	protected string $sort = 'time';

	/**
	 * @var string
	 *
	 *
	 */
	protected string $search_params_type = 'member';

	/**
	 * @var array
	 *
	 *
	 */
	protected array $search_params;

	/**
	 * @var string
	 *
	 *
	 */
	protected string $search_params_string;

	/**
	 * @var string
	 *
	 *
	 */
	protected string $search_params_column;

	/**
	 * @var string
	 *
	 *
	 */
	protected string $encoded_search_params;

	/**
	 * @var array
	 *
	 *
	 */
	protected array $search_info;

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
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 *
	 */
	public function adminlog()
	{
		User::$me->isAllowedTo('admin_forum');

		Utils::$context['page_title'] = Lang::$txt['modlog_admin_log'];

		$this->deleteEntries();

		$this->createList();
	}

	/**
	 *
	 */
	public function modlog()
	{
		Utils::$context['page_title'] = Lang::$txt['modlog_view'];

		$this->deleteEntries();

		$this->createList();
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
	 * Get the number of mod log entries.
	 * Callback for SMF\ItemList().
	 *
	 * @param string $query_string An extra string for the WHERE clause in the query to further filter results
	 * @param array $query_params An array of parameters for the query_string
	 * @param int $log_type The log type (1 for mod log, 3 for admin log)
	 * @param bool $ignore_boards Whether to ignore board restrictions
	 * @return int Total number of visible log entries.
	 */
	public static function list_getModLogEntryCount($query_string = '', $query_params = [], $log_type = 1, $ignore_boards = false): int
	{
		$modlog_query = User::$me->allowedTo('admin_forum') || User::$me->mod_cache['bq'] == '1=1' ? '1=1' : ((User::$me->mod_cache['bq'] == '0=1' || $ignore_boards) ? 'lm.id_board = 0 AND lm.id_topic = 0' : (strtr(User::$me->mod_cache['bq'], ['id_board' => 'b.id_board']) . ' AND ' . strtr(User::$me->mod_cache['bq'], ['id_board' => 't.id_board'])));

		$result = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_actions AS lm
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lm.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lm.id_board)
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lm.id_topic)
			WHERE id_log = {int:log_type}
				AND {raw:modlog_query}'
				. (!empty($query_string) ? '
				AND ' . $query_string : ''),
			array_merge($query_params, [
				'reg_group_id' => 0,
				'log_type' => $log_type,
				'modlog_query' => $modlog_query,
			]),
		);
		list($entry_count) = Db::$db->fetch_row($result);
		Db::$db->free_result($result);

		return $entry_count;
	}

	/**
	 * Gets the moderation log entries that match the specified parameters.
	 * Callback for SMF\ItemList().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $query_string An extra string for the WHERE clause of the query, to further filter results
	 * @param array $query_params An array of parameters for the query string
	 * @param int $log_type The log type - 1 for mod log or 3 for admin log
	 * @param bool $ignore_boards Whether to ignore board restrictions
	 * @return array An array of info about the mod log entries
	 */
	public static function list_getModLogEntries($start, $items_per_page, $sort, $query_string = '', $query_params = [], $log_type = 1, $ignore_boards = false): array
	{
		$modlog_query = User::$me->allowedTo('admin_forum') || User::$me->mod_cache['bq'] == '1=1' ? '1=1' : ((User::$me->mod_cache['bq'] == '0=1' || $ignore_boards) ? 'lm.id_board = 0 AND lm.id_topic = 0' : (strtr(User::$me->mod_cache['bq'], ['id_board' => 'b.id_board']) . ' AND ' . strtr(User::$me->mod_cache['bq'], ['id_board' => 't.id_board'])));

		if (!isset(self::$uneditable_actions)) {
			self::$uneditable_actions = [];
		}

		// Can they see the IP address?
		$seeIP = User::$me->allowedTo('moderate_forum');

		// Here we have the query getting the log details.
		$result = Db::$db->query(
			'',
			'SELECT
				lm.id_action, lm.id_member, lm.ip, lm.log_time, lm.action, lm.id_board, lm.id_topic, lm.id_msg, lm.extra,
				mem.real_name, mg.group_name
			FROM {db_prefix}log_actions AS lm
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lm.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lm.id_board)
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lm.id_topic)
			WHERE id_log = {int:log_type}
				AND {raw:modlog_query}'
				. (!empty($query_string) ? '
				AND ' . $query_string : '') . '
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			array_merge($query_params, [
				'reg_group_id' => 0,
				'log_type' => $log_type,
				'modlog_query' => $modlog_query,
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			]),
		);

		// Arrays for decoding objects into.
		$topics = [];
		$boards = [];
		$members = [];
		$messages = [];
		$entries = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			$row['extra'] = Utils::jsonDecode($row['extra'], true);

			// Corrupt?
			$row['extra'] = is_array($row['extra']) ? $row['extra'] : [];

			// Add on some of the column stuff info
			if (!empty($row['id_board'])) {
				if ($row['action'] == 'move') {
					$row['extra']['board_to'] = $row['id_board'];
				} else {
					$row['extra']['board'] = $row['id_board'];
				}
			}

			if (!empty($row['id_topic'])) {
				$row['extra']['topic'] = $row['id_topic'];
			}

			if (!empty($row['id_msg'])) {
				$row['extra']['message'] = $row['id_msg'];
			}

			// Is this associated with a topic?
			if (isset($row['extra']['topic'])) {
				$topics[(int) $row['extra']['topic']][] = $row['id_action'];
			}

			if (isset($row['extra']['new_topic'])) {
				$topics[(int) $row['extra']['new_topic']][] = $row['id_action'];
			}

			// How about a member?
			if (isset($row['extra']['member'])) {
				// Guests don't have names!
				if (empty($row['extra']['member'])) {
					$row['extra']['member'] = Lang::$txt['modlog_parameter_guest'];
				} else {
					// Try to find it...
					$members[(int) $row['extra']['member']][] = $row['id_action'];
				}
			}

			// Associated with a board?
			if (isset($row['extra']['board_to'])) {
				$boards[(int) $row['extra']['board_to']][] = $row['id_action'];
			}

			if (isset($row['extra']['board_from'])) {
				$boards[(int) $row['extra']['board_from']][] = $row['id_action'];
			}

			if (isset($row['extra']['board'])) {
				$boards[(int) $row['extra']['board']][] = $row['id_action'];
			}

			// A message?
			if (isset($row['extra']['message'])) {
				$messages[(int) $row['extra']['message']][] = $row['id_action'];
			}

			// IP Info?
			if (isset($row['extra']['ip_range'])) {
				if ($seeIP) {
					$row['extra']['ip_range'] = '<a href="' . Config::$scripturl . '?action=trackip;searchip=' . $row['extra']['ip_range'] . '">' . $row['extra']['ip_range'] . '</a>';
				} else {
					$row['extra']['ip_range'] = Lang::$txt['logged'];
				}
			}

			// Email?
			if (isset($row['extra']['email'])) {
				$row['extra']['email'] = '<a href="mailto:' . $row['extra']['email'] . '">' . $row['extra']['email'] . '</a>';
			}

			// Bans are complex.
			if ($row['action'] == 'ban' || $row['action'] == 'banremove') {
				$row['action_text'] = Lang::$txt['modlog_ac_ban' . ($row['action'] == 'banremove' ? '_remove' : '')];

				foreach (['member', 'email', 'ip_range', 'hostname'] as $type) {
					if (isset($row['extra'][$type])) {
						$row['action_text'] .= Lang::$txt['modlog_ac_ban_trigger_' . $type];
					}
				}
			}

			// The array to go to the template. Note here that action is set to a "default" value of the action doesn't match anything in the descriptions. Allows easy adding of logging events with basic details.
			$entries[$row['id_action']] = [
				'id' => $row['id_action'],
				'ip' => $seeIP ? new IP($row['ip']) : Lang::$txt['logged'],
				'position' => empty($row['real_name']) && empty($row['group_name']) ? Lang::$txt['guest'] : $row['group_name'],
				'moderator_link' => $row['id_member'] ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : (empty($row['real_name']) ? (Lang::$txt['guest'] . (!empty($row['extra']['member_acted']) ? ' (' . $row['extra']['member_acted'] . ')' : '')) : $row['real_name']),
				'time' => Time::create('@' . $row['log_time'])->format(),
				'timestamp' => $row['log_time'],
				'editable' => substr($row['action'], 0, 8) !== 'clearlog' && !in_array($row['action'], self::$uneditable_actions),
				'extra' => $row['extra'],
				'action' => $row['action'],
				'action_text' => $row['action_text'] ?? '',
			];
		}
		Db::$db->free_result($result);

		if (!empty($boards)) {
			$request = Db::$db->query(
				'',
				'SELECT id_board, name
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:board_list})
				LIMIT {int:limit}',
				[
					'board_list' => array_keys($boards),
					'limit' => count(array_keys($boards)),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				foreach ($boards[$row['id_board']] as $action) {
					// Make the board number into a link - dealing with moving too.
					if (isset($entries[$action]['extra']['board_to']) && $entries[$action]['extra']['board_to'] == $row['id_board']) {
						$entries[$action]['extra']['board_to'] = '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
					} elseif (isset($entries[$action]['extra']['board_from']) && $entries[$action]['extra']['board_from'] == $row['id_board']) {
						$entries[$action]['extra']['board_from'] = '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
					} elseif (isset($entries[$action]['extra']['board']) && $entries[$action]['extra']['board'] == $row['id_board']) {
						$entries[$action]['extra']['board'] = '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
					}
				}
			}
			Db::$db->free_result($request);
		}

		if (!empty($topics)) {
			$request = Db::$db->query(
				'',
				'SELECT ms.subject, t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				WHERE t.id_topic IN ({array_int:topic_list})
				LIMIT {int:limit}',
				[
					'topic_list' => array_keys($topics),
					'limit' => count(array_keys($topics)),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				foreach ($topics[$row['id_topic']] as $action) {
					$this_action = &$entries[$action];

					// This isn't used in the current theme.
					$this_action['topic'] = [
						'id' => $row['id_topic'],
						'subject' => $row['subject'],
						'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
						'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
					];

					// Make the topic number into a link - dealing with splitting too.
					if (isset($this_action['extra']['topic']) && $this_action['extra']['topic'] == $row['id_topic']) {
						$this_action['extra']['topic'] = '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.' . (isset($this_action['extra']['message']) ? 'msg' . $this_action['extra']['message'] . '#msg' . $this_action['extra']['message'] : '0') . '">' . $row['subject'] . '</a>';
					} elseif (isset($this_action['extra']['new_topic']) && $this_action['extra']['new_topic'] == $row['id_topic']) {
						$this_action['extra']['new_topic'] = '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.' . (isset($this_action['extra']['message']) ? 'msg' . $this_action['extra']['message'] . '#msg' . $this_action['extra']['message'] : '0') . '">' . $row['subject'] . '</a>';
					}
				}
			}
			Db::$db->free_result($request);
		}

		if (!empty($messages)) {
			$request = Db::$db->query(
				'',
				'SELECT id_msg, subject
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:message_list})
				LIMIT {int:limit}',
				[
					'message_list' => array_keys($messages),
					'limit' => count(array_keys($messages)),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				foreach ($messages[$row['id_msg']] as $action) {
					$this_action = &$entries[$action];

					// This isn't used in the current theme.
					$this_action['message'] = [
						'id' => $row['id_msg'],
						'subject' => $row['subject'],
						'href' => Config::$scripturl . '?msg=' . $row['id_msg'],
						'link' => '<a href="' . Config::$scripturl . '?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
					];

					// Make the message number into a link.
					if (isset($this_action['extra']['message']) && $this_action['extra']['message'] == $row['id_msg']) {
						$this_action['extra']['message'] = '<a href="' . Config::$scripturl . '?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
					}
				}
			}
			Db::$db->free_result($request);
		}

		if (!empty($members)) {
			$request = Db::$db->query(
				'',
				'SELECT real_name, id_member
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT {int:limit}',
				[
					'member_list' => array_keys($members),
					'limit' => count(array_keys($members)),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				foreach ($members[$row['id_member']] as $action) {
					// Not used currently.
					$entries[$action]['member'] = [
						'id' => $row['id_member'],
						'name' => $row['real_name'],
						'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
						'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
					];
					// Make the member number into a name.
					$entries[$action]['extra']['member'] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
				}
			}
			Db::$db->free_result($request);
		}

		// Do some formatting of the action string.
		foreach ($entries as $k => $entry) {
			// Make any message info links so its easier to go find that message.
			if (isset($entry['extra']['message']) && (empty($entry['message']) || empty($entry['message']['id']))) {
				$entries[$k]['extra']['message'] = '<a href="' . Config::$scripturl . '?msg=' . $entry['extra']['message'] . '">' . $entry['extra']['message'] . '</a>';
			}

			// Mark up any deleted members, topics and boards.
			foreach (['board', 'board_from', 'board_to', 'member', 'topic', 'new_topic'] as $type) {
				if (!empty($entry['extra'][$type]) && is_numeric($entry['extra'][$type])) {
					$entries[$k]['extra'][$type] = sprintf(Lang::$txt['modlog_id'], $entry['extra'][$type]);
				}
			}

			if (isset($entry['extra']['report'])) {
				// Member profile reports go in a different area
				if (stristr($entry['action'], 'user_report')) {
					$entries[$k]['extra']['report'] = '<a href="' . Config::$scripturl . '?action=moderate;area=reportedmembers;sa=details;rid=' . $entry['extra']['report'] . '">' . Lang::$txt['modlog_report'] . '</a>';
				} else {
					$entries[$k]['extra']['report'] = '<a href="' . Config::$scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . $entry['extra']['report'] . '">' . Lang::$txt['modlog_report'] . '</a>';
				}
			}

			if (empty($entries[$k]['action_text'])) {
				$entries[$k]['action_text'] = Lang::$txt['modlog_ac_' . $entry['action']] ?? $entry['action'];
			}

			$entries[$k]['action_text'] = preg_replace_callback(
				'~\{([A-Za-z\d_]+)\}~i',
				function ($matches) use ($entries, $k) {
					return $entries[$k]['extra'][$matches[1]] ?? '';
				},
				$entries[$k]['action_text'],
			);
		}

		// Back we go!
		return $entries;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], self::$actions)) {
			$this->action = $_REQUEST['action'];
		}

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		$this->log_type = $this->subaction == 'adminlog' ? 3 : 1;

		// These change dependant on whether we are viewing the moderation or admin log.
		if ($this->action == 'admin') {
			$this->url_start = '?action=admin;area=logs;sa=' . ($this->log_type == 3 ? 'adminlog' : 'modlog') . ';type=' . $this->log_type;
		} else {
			$this->url_start = '?action=moderate;area=modlog';
		}

		$this->can_delete = User::$me->allowedTo('admin_forum');

		Lang::load('Admin+Modlog');

		// Setup the direction stuff...
		if (!empty($_REQUEST['sort']) && isset(self::$sort_types[$_REQUEST['sort']])) {
			$this->sort = $_REQUEST['sort'];
		}

		// If we're coming from a search, set those variables.
		$this->setupSearch();
	}

	/**
	 *
	 */
	protected function setupSearch(): void
	{
		if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search'])) {
			$this->search_params = base64_decode(strtr($_REQUEST['params'], [' ' => '+']));
			$this->search_params = Utils::jsonDecode($this->search_params, true);
		}

		if (!isset($this->search_params['string']) || (!empty($_REQUEST['search']) && $this->search_params['string'] != $_REQUEST['search'])) {
			$this->search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
		} else {
			$this->search_params_string = $this->search_params['string'];
		}

		if (isset($_REQUEST['search_type']) || empty($this->search_params['type']) || !isset(self::$search_types[$this->search_params['type']])) {
			$this->search_params_type = isset($_REQUEST['search_type']) && isset(self::$search_types[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset(self::$search_types[$this->sort]) ? $this->sort : 'member');
		} else {
			$this->search_params_type = $this->search_params['type'];
		}

		$this->search_params_column = self::$search_types[$this->search_params_type]['sql'];
		$this->search_params = [
			'string' => $this->search_params_string,
			'type' => $this->search_params_type,
		];

		// Setup the search context.
		$this->encoded_search_params = empty($this->search_params['string']) ? '' : base64_encode(Utils::jsonEncode($this->search_params));

		$this->search_info = [
			'string' => $this->search_params['string'],
			'type' => $this->search_params['type'],
			'label' => Lang::$txt[self::$search_types[$this->search_params_type]['label']],
		];

		// If they are searching by action, then we must do some manual intervention to search in their language!
		if ($this->search_params['type'] == 'action' && !empty($this->search_params['string'])) {
			// For the moment they can only search for ONE action!
			foreach (Lang::$txt as $key => $text) {
				if (substr($key, 0, 10) == 'modlog_ac_' && strpos($text, $this->search_params['string']) !== false) {
					$this->search_params['string'] = substr($key, 10);
					break;
				}
			}
		}
	}

	/**
	 *
	 */
	protected function deleteEntries(): void
	{
		if (isset($_POST['removeall']) && $this->can_delete) {
			$this->deleteAll();
		} elseif (!empty($_POST['remove']) && isset($_POST['delete']) && $this->can_delete) {
			$this->deleteEntry();
		}
	}

	/**
	 *
	 */
	protected function deleteAll(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('mod-ml');

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:moderate_log}
			AND action NOT IN ({array_string:uneditable})',
			[
				'moderate_log' => $this->log_type,
				'uneditable' => self::$uneditable_actions,
			],
		);

		$log_type = isset($this->subaction) && $this->subaction == 'adminlog' ? 'admin' : 'moderate';
		Logging::logAction('clearlog_' . $log_type, [], $log_type);
	}

	/**
	 *
	 */
	protected function deleteEntry(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('mod-ml');

		// No sneaky removing the 'cleared the log' entries.
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:moderate_log}
				AND id_action IN ({array_string:delete_actions})
				AND action NOT LIKE {string:clearlog}
				AND action NOT IN ({array_string:uneditable})',
			[
				'delete_actions' => array_unique($_POST['delete']),
				'moderate_log' => $this->log_type,
				'uneditable' => self::$uneditable_actions,
				'clearlog' => 'clearlog_%',
			],
		);
	}

	/**
	 *
	 */
	protected function createList(): void
	{
		// This is all the information required for a watched user listing.
		$listOptions = [
			'id' => 'moderation_log_list',
			'title' => $this->log_type == 3 ? Lang::$txt['admin_log'] : Lang::$txt['moderation_log'],
			'width' => '100%',
			'items_per_page' => $this->per_page,
			'no_items_label' => Lang::$txt['modlog_' . ($this->log_type == 3 ? 'admin_log_' : '') . 'no_entries_found'],
			'base_href' => Config::$scripturl . $this->url_start . (!empty($this->encoded_search_params) ? ';params=' . $this->encoded_search_params : ''),
			'default_sort_col' => 'time',
			'get_items' => [
				'function' => __CLASS__ . '::list_getModLogEntries',
				'params' => [
					(!empty($this->search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string}) > 0' : ''),
					['sql_type' => $this->search_params_column, 'search_string' => $this->search_params['string']],
					$this->log_type,
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getModLogEntryCount',
				'params' => [
					(!empty($this->search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string}) > 0' : ''),
					['sql_type' => $this->search_params_column, 'search_string' => $this->search_params['string']],
					$this->log_type,
				],
			],
			// This assumes we are viewing by user.
			'columns' => [
				'action' => [
					'header' => [
						'value' => Lang::$txt['modlog_action'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'action_text',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'lm.action',
						'reverse' => 'lm.action DESC',
					],
				],
				'time' => [
					'header' => [
						'value' => Lang::$txt['modlog_date'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'time',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'lm.log_time DESC',
						'reverse' => 'lm.log_time',
					],
				],
				'moderator' => [
					'header' => [
						'value' => Lang::$txt['modlog_member'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'moderator_link',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'position' => [
					'header' => [
						'value' => Lang::$txt['modlog_position'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'position',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					],
				],
				'ip' => [
					'header' => [
						'value' => Lang::$txt['modlog_ip'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'ip',
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'lm.ip',
						'reverse' => 'lm.ip DESC',
					],
				],
				'delete' => [
					'header' => [
						'value' => '<input type="checkbox" name="all" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($entry) {
							return '<input type="checkbox" name="delete[]" value="' . $entry['id'] . '"' . ($entry['editable'] ? '' : ' disabled') . '>';
						},
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . $this->url_start,
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					'params' => $this->encoded_search_params,
				],
				'token' => 'mod-ml',
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => '
						' . Lang::$txt['modlog_search'] . ' (' . Lang::$txt['modlog_by'] . ': ' . $this->search_info['label'] . '):
						<input type="text" name="search" size="18" value="' . Utils::htmlspecialchars($this->search_info['string']) . '">
						<input type="submit" name="is_search" value="' . Lang::$txt['modlog_go'] . '" class="button" style="float:none">
						' . ($this->can_delete ? '
						<input type="submit" name="remove" value="' . Lang::$txt['modlog_remove'] . '" data-confirm="' . Lang::$txt['modlog_remove_selected_confirm'] . '" class="button you_sure">
						<input type="submit" name="removeall" value="' . Lang::$txt['modlog_removeall'] . '" data-confirm="' . Lang::$txt['modlog_remove_all_confirm'] . '" class="button you_sure">' : ''),
					'class' => '',
				],
				[
					'position' => 'below_table_data',
					'value' => $this->can_delete ? '
						<input type="submit" name="remove" value="' . Lang::$txt['modlog_remove'] . '" data-confirm="' . Lang::$txt['modlog_remove_selected_confirm'] . '" class="button you_sure">
						<input type="submit" name="removeall" value="' . Lang::$txt['modlog_removeall'] . '" data-confirm="' . Lang::$txt['modlog_remove_all_confirm'] . '" class="button you_sure">' : '',
					'class' => 'floatright',
				],
			],
		];

		// Overriding this with a hook?
		$moderation_menu_name = [];
		IntegrationHook::call('integrate_viewModLog', [&$listOptions, &$moderation_menu_name]);

		SecurityToken::create('mod-ml');

		// Create the watched user list.
		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'moderation_log_list';

		// If a hook has changed this, respect it.
		if (!empty($moderation_menu_name)) {
			Menu::$loaded['moderate']->tab_data = $moderation_menu_name;
		} elseif (isset(Utils::$context['moderation_menu_name'])) {
			Menu::$loaded['moderate']->tab_data = [
				'title' => Lang::$txt['modlog_' . ($this->log_type == 3 ? 'admin' : 'moderation') . '_log'],
				'help' => $this->log_type == 3 ? 'adminlog' : 'modlog',
				'description' => Lang::$txt['modlog_' . ($this->log_type == 3 ? 'admin' : 'moderation') . '_log_desc'],
			];
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	// code...
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Logs::exportStatic')) {
	Logs::exportStatic();
}

?>