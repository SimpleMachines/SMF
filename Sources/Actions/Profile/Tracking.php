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

namespace SMF\Actions\Profile;

use SMF\BackwardCompatibility;
use SMF\Actions\ActionInterface;

use SMF\BBCodeParser;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Profile;
use SMF\User;
use SMF\Utils;
use SMF\Actions\TrackIP;
use SMF\Db\DatabaseApi as Db;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class Tracking implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'tracking',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'activity';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Keys are $_REQUEST['sa'] params.
	 * Values are arrays of the form:
	 *
	 *    array('method', 'txt_key_for_page_title', 'permission')
	 */
	public static array $subactions = array(
		'activity' => array(
			'activity',
			'trackActivity',
			'moderate_forum',
		),
		'ip' => array(
			'ip',
			'trackIP',
			'moderate_forum',
		),
		'edits' => array(
			'edits',
			'trackEdits',
			'moderate_forum',
		),
		'groupreq' => array(
			'groupRequests',
			'trackGroupRequests',
			'approve_group_requests',
		),
		'logins' => array(
			'logins',
			'trackLogins',
			'moderate_forum',
		),
	);

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
		if (!isset($this->subaction, self::$subactions))
			ErrorHandler::fatalLang('no_access', false);

		// This is only here for backward compatiblity in case a mod needs it.
		Utils::$context['tracking_area'] = &$this->subaction;

		// Create the tabs for the template.
		Menu::$loaded['profile']->tab_data = array(
			'title' => Lang::$txt['tracking'],
			'description' => Lang::$txt['tracking_description'],
			'icon_class' => 'main_icons profile_hd',
			'tabs' => array(),
		);

		foreach (self::$subactions as $sa => $dummy)
			Menu::$loaded['profile']->tab_data['tabs'][$sa] = array();

		// Set a page title.
		Utils::$context['page_title'] = Lang::$txt['trackUser'] . ' - ' . Lang::$txt[self::$subactions[$this->subaction][1]] . ' - ' . Profile::$member->name;

		call_helper(method_exists($this, self::$subactions[$this->subaction][0]) ? array($this, self::$subactions[$this->subaction][0]) : self::$subactions[$this->subaction][0]);
	}

	/**
	 * Handles tracking a user's activity
	 */
	public function activity(): void
	{
		// Verify if the user has sufficient permissions.
		isAllowedTo('moderate_forum');

		// Set the sub_template.
		Utils::$context['sub_template'] = 'trackActivity';

		Utils::$context['last_ip'] = Profile::$member->ip;

		if (Utils::$context['last_ip'] != Profile::$member->ip2)
			Utils::$context['last_ip2'] = Profile::$member->ip2;

		Utils::$context['member']['name'] = Profile::$member->name;

		// Set the options for the list component.
		$list_options = array(
			'id' => 'track_user_list',
			'title' => Lang::$txt['errors_by'] . ' ' . Utils::$context['member']['name'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['no_errors_from_user'],
			'base_href' => Config::$scripturl . '?action=profile;area=tracking;sa=user;u=' . Profile::$member->id,
			'default_sort_col' => 'date',
			'get_items' => array(
				'function' => __CLASS__ . '::list_getUserErrors',
				'params' => array(
					'le.id_member = {int:current_member}',
					array('current_member' => Profile::$member->id),
				),
			),
			'get_count' => array(
				'function' => __CLASS__ . '::list_getUserErrorCount',
				'params' => array(
					'id_member = {int:current_member}',
					array('current_member' => Profile::$member->id),
				),
			),
			'columns' => array(
				'ip_address' => array(
					'header' => array(
						'value' => Lang::$txt['ip_address'],
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<a href="' . Config::$scripturl . '?action=profile;area=tracking;sa=ip;searchip=%1$s;u=' . Profile::$member->id . '">%1$s</a>',
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
		new ItemList($list_options);

		// @todo cache this
		// If this is a big forum, or a large posting user, let's limit the search.
		if (Config::$modSettings['totalMessages'] > 50000 && Profile::$member->posts > 500)
		{
			$request = Db::$db->query('', '
				SELECT MAX(id_msg)
				FROM {db_prefix}messages AS m
				WHERE m.id_member = {int:current_member}',
				array(
					'current_member' => Profile::$member->id,
				)
			);
			list($max_msg_member) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
			$min_msg_member = max(0, $max_msg_member - Profile::$member->posts * 3);
		}

		// Default to at least the ones we know about.
		$ips = array(
			Profile::$member->ip,
			Profile::$member->ip2,
		);

		// @todo cache this
		// Get all IP addresses this user has used for his messages.
		Utils::$context['ips'] = array();

		$request = Db::$db->query('', '
			SELECT poster_ip
			FROM {db_prefix}messages
			WHERE id_member = {int:current_member}
			' . (isset($min_msg_member) ? '
				AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : '') . '
			GROUP BY poster_ip',
			array(
				'current_member' => Profile::$member->id,
				'min_msg_member' => !empty($min_msg_member) ? $min_msg_member : 0,
				'max_msg_member' => !empty($max_msg_member) ? $max_msg_member : 0,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$row['poster_ip'] = inet_dtop($row['poster_ip']);

			Utils::$context['ips'][] = '<a href="' . Config::$scripturl . '?action=profile;area=tracking;sa=ip;searchip=' . $row['poster_ip'] . ';u=' . Profile::$member->id . '">' . $row['poster_ip'] . '</a>';

			$ips[] = $row['poster_ip'];
		}
		Db::$db->free_result($request);

		// Now also get the IP addresses from the error messages.
		Utils::$context['error_ips'] = array();

		$request = Db::$db->query('', '
			SELECT COUNT(*) AS error_count, ip
			FROM {db_prefix}log_errors
			WHERE id_member = {int:current_member}
			GROUP BY ip',
			array(
				'current_member' => Profile::$member->id,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$row['ip'] = inet_dtop($row['ip']);

			Utils::$context['error_ips'][] = '<a href="' . Config::$scripturl . '?action=profile;area=tracking;sa=ip;searchip=' . $row['ip'] . ';u=' . Profile::$member->id . '">' . $row['ip'] . '</a>';

			$ips[] = $row['ip'];
		}
		Db::$db->free_result($request);

		// Find other users that might use the same IP.
		$ips = array_unique($ips);
		Utils::$context['members_in_range'] = array();

		if (!empty($ips))
		{
			// Get member ID's which are in messages...
			$message_members = array();
			$request = Db::$db->query('', '
				SELECT DISTINCT mem.id_member
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.poster_ip IN ({array_inet:ip_list})
					AND mem.id_member != {int:current_member}',
				array(
					'current_member' => Profile::$member->id,
					'ip_list' => $ips,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$message_members[] = $row['id_member'];
			}
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
				{
					Utils::$context['members_in_range'][$row['id_member']] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
				}
				Db::$db->free_result($request);
			}

			$request = Db::$db->query('', '
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member != {int:current_member}
					AND member_ip IN ({array_inet:ip_list})',
				array(
					'current_member' => Profile::$member->id,
					'ip_list' => $ips,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				Utils::$context['members_in_range'][$row['id_member']] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			}
			Db::$db->free_result($request);
		}
	}

	/**
	 * Handles tracking a particular IP address.
	 */
	public function ip(): void
	{
		TrackIP::call();
	}

	/**
	 * Tracks a user's profile edits.
	 */
	public function edits(): void
	{
		// Get the names of any custom fields.
		Utils::$context['custom_field_titles'] = array();

		$request = Db::$db->query('', '
			SELECT col_name, field_name, bbc
			FROM {db_prefix}custom_fields',
			array(
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['custom_field_titles']['customfield_' . $row['col_name']] = array(
				'title' => $row['field_name'],
				'parse_bbc' => $row['bbc'],
			);
		}
		Db::$db->free_result($request);

		// Set the options for the error lists.
		$list_options = array(
			'id' => 'edit_list',
			'title' => Lang::$txt['trackEdits'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['trackEdit_no_edits'],
			'base_href' => Config::$scripturl . '?action=profile;area=tracking;sa=edits;u=' . Profile::$member->id,
			'default_sort_col' => 'time',
			'get_items' => array(
				'function' => __CLASS__ . '::list_getProfileEdits',
				'params' => array(),
			),
			'get_count' => array(
				'function' => __CLASS__ . '::list_getProfileEditCount',
				'params' => array(),
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
		new ItemList($list_options);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'edit_list';
	}

	/**
	 * Display the history of group requests made by the user whose profile we are viewing.
	 */
	public function groupRequests(): void
	{
		// Set the options for the error lists.
		$list_options = array(
			'id' => 'request_list',
			'title' => sprintf(Lang::$txt['trackGroupRequests_title'], Utils::$context['member']['name']),
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['requested_none'],
			'base_href' => Config::$scripturl . '?action=profile;area=tracking;sa=groupreq;u=' . Profile::$member->id,
			'default_sort_col' => 'time_applied',
			'get_items' => array(
				'function' => __CLASS__ . '::list_getGroupRequests',
				'params' => array(),
			),
			'get_count' => array(
				'function' => __CLASS__ . '::list_getGroupRequestsCount',
				'params' => array(),
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
		new ItemList($list_options);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'request_list';
	}

	/**
	 * Tracks a user's logins.
	 */
	public function logins(): void
	{
		Utils::$context['base_url'] = Config::$scripturl . '?action=profile;area=tracking;sa=ip;u=' . Profile::$member->id;

		// Start with the user messages.
		$list_options = array(
			'id' => 'track_logins_list',
			'title' => Lang::$txt['trackLogins'],
			'no_items_label' => Lang::$txt['trackLogins_none_found'],
			'base_href' => Utils::$context['base_url'],
			'get_items' => array(
				'function' => __CLASS__ . '::list_getLogins',
				'params' => array(
					'id_member = {int:current_member}',
					array('current_member' => Profile::$member->id),
				),
			),
			'get_count' => array(
				'function' => __CLASS__ . '::list_getLoginCount',
				'params' => array(
					'id_member = {int:current_member}',
					array('current_member' => Profile::$member->id),
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
		new ItemList($list_options);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'track_logins_list';
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
		if (!isset(self::$obj))
			self::$obj = new self();

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
	 * Gets all of the errors generated by a user's actions. Callback for the list in track_activity
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @param string $where A query indicating how to filter the results (eg 'id_member={int:id_member}')
	 * @param array $where_vars An array of parameters for $where
	 * @return array An array of information about the error messages
	 */
	public static function list_getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array()): array
	{
		// Get a list of error messages from this ip (range).
		$error_messages = array();

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
		while ($row = Db::$db->fetch_assoc($request))
		{
			$error_messages[] = array(
				'ip' => inet_dtop($row['ip']),
				'member_link' => $row['id_member'] > 0 ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>' : $row['display_name'],
				'message' => strtr($row['message'], array('&lt;span class=&quot;remove&quot;&gt;' => '', '&lt;/span&gt;' => '')),
				'url' => $row['url'],
				'time' => timeformat($row['log_time']),
				'timestamp' => $row['log_time'],
			);
		}
		Db::$db->free_result($request);

		return $error_messages;
	}

	/**
	 * Get the number of user errors
	 *
	 * @param string $where A query to limit which errors are counted
	 * @param array $where_vars The parameters for $where
	 * @return int Number of user errors
	 */
	public static function list_getUserErrorCount($where, $where_vars = array()): int
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_errors
			WHERE ' . $where,
			$where_vars
		);
		list($count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $count;
	}

	/**
	 * Loads up information about a user's profile edits. Callback for the list in trackEdits()
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of information about the profile edits
	 */
	public static function list_getProfileEdits(int $start, int $items_per_page, string $sort): array
	{
		$edits = array();
		$applicators = array();

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
				'owner' => Profile::$member->id,
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$extra = Utils::jsonDecode($row['extra'], true);

			if (!empty($extra['applicator']))
				$applicators[] = $extra['applicator'];

			// Work out what the name of the action is.
			if (isset(Lang::$txt['trackEdit_action_' . $row['action']]))
			{
				$action_text = Lang::$txt['trackEdit_action_' . $row['action']];
			}
			elseif (isset(Lang::$txt[$row['action']]))
			{
				$action_text = Lang::$txt[$row['action']];
			}
			// Custom field?
			elseif (isset(Utils::$context['custom_field_titles'][$row['action']]))
			{
				$action_text = Utils::$context['custom_field_titles'][$row['action']]['title'];
			}
			else
			{
				$action_text = $row['action'];
			}

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
		if (!empty($applicators))
		{
			$members = array();

			$request = Db::$db->query('', '
				SELECT
					id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:applicators})',
				array(
					'applicators' => $applicators,
				)
			);
			while ($row = Db::$db->fetch_assoc($request))
			{
				$members[$row['id_member']] = $row['real_name'];
			}
			Db::$db->free_result($request);

			foreach ($edits as $key => $value)
			{
				if (isset($members[$value['id_member']]))
				{
					$edits[$key]['member_link'] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $value['id_member'] . '">' . $members[$value['id_member']] . '</a>';
				}
			}
		}

		return $edits;
	}

	/**
	 * How many edits?
	 *
	 * @return int The number of profile edits
	 */
	public static function list_getProfileEditCount(): int
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*) AS edit_count
			FROM {db_prefix}log_actions
			WHERE id_log = {int:log_type}
				AND id_member = {int:owner}',
			array(
				'log_type' => 2,
				'owner' => Profile::$member->id,
			)
		);
		list($edit_count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $edit_count;
	}

	/**
	 * Loads up information about a user's group requests. Callback for the list in trackGroupReq()
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of information about the user's group requests
	 */
	public static function list_getGroupRequests(int $start, int $items_per_page, string $sort): array
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
				'memID' => Profile::$member->id,
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
	 * How many edits?
	 *
	 * @return int The number of profile edits
	 */
	public static function list_getGroupRequestsCount(): int
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*) AS req_count
			FROM {db_prefix}log_group_requests AS lgr
			WHERE id_member = {int:memID}
				AND ' . (User::$me->mod_cache['gq'] == '1=1' ? User::$me->mod_cache['gq'] : 'lgr.' . User::$me->mod_cache['gq']),
			array(
				'memID' => Profile::$member->id,
			)
		);
		list($report_count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $report_count;
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
	public static function list_getLogins(int $start, int $items_per_page, string $sort, string $where, array $where_vars = array()): array
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
	 * Finds the total number of tracked logins for a particular user
	 *
	 * @param string $where A query to limit which logins are counted
	 * @param array $where_vars An array of parameters for $where
	 * @return int count of messages matching the IP
	 */
	public static function list_getLoginCount(string $where, array $where_vars = array()): int
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*) AS message_count
			FROM {db_prefix}member_logins
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $where_vars['current_member'],
			)
		);
		list($count) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $count;
	}

	/**
	 * Backward compatibility wrapper for the activity sub-action.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function trackActivity(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->subaction = 'activity';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the edits sub-action.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function trackEdits(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->subaction = 'edits';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the groupreq sub-action.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function trackGroupReq(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->subaction = 'groupreq';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the logins sub-action.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function trackLogins(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

		self::$obj->subaction = 'logins';
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
		if (!isset(Profile::$member))
			Profile::load();

		// Moderation must be on to track edits.
		if (empty(Config::$modSettings['userlog_enabled']))
			unset(self::$subactions['edits']);

		// Group requests must be active to show it...
		if (empty(Config::$modSettings['show_group_membership']))
			unset(self::$subactions['groupreq']);

		// Only show the sub-actions they are allowed to see.
		foreach (self::$subactions as $sa => $action)
		{
			if (!allowedTo($action[2]))
				unset(self::$subactions[$sa]);
		}

		// Now that we've filtered out all the sub-actions they cannot do,
		// let them choose from whatever is left.
		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']]))
		{
			$this->subaction = $_REQUEST['sa'];
		}
		elseif (!empty(self::$subactions))
		{
			$this->subaction = array_key_first(self::$subactions);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Tracking::exportStatic'))
	Tracking::exportStatic();

?>