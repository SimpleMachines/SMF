<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

namespace SMF\Actions\Moderation;

use SMF\Actions\Groups as ViewGroups;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Shows group info and allows certain privileged members to add/remove members.
 */
class Groups extends ViewGroups
{
	use ActionTrait;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'index';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'index' => 'index',
		'members' => 'members',
		'requests' => 'requests',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * The action and area URL query to use in links to sub-actions.
	 */
	protected string $action_url = '?action=moderate;area=groups';

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 *
	 * It allows moderators and users to access the group showing functions.
	 * It handles permission checks, and puts the moderation bar on as required.
	 */
	public function execute(): void
	{
		$_GET['area'] = $this->subaction == 'requests' ? 'groups' : 'viewgroups';
		$this->action_url = '?action=moderate;area=' . $_GET['area'];

		// Get the template stuff up and running.
		Lang::load('ManageMembers');
		Lang::load('ModerationCenter');
		Theme::loadTemplate('ManageMembergroups');

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Display members of a group, and allow adding of members to a group.
	 *
	 * It can be called from ManageMembergroups if it needs templating within the admin environment.
	 * It shows a list of members that are part of a given membergroup.
	 * It is called by ?action=moderate;area=viewgroups;sa=members;group=x
	 * It requires the manage_membergroups permission.
	 * It allows to add and remove members from the selected membergroup.
	 * It allows sorting on several columns.
	 * It redirects to itself.
	 *
	 * @todo use SMF\ItemList
	 */
	public function members(): void
	{
		// List the members.
		parent::members();

		// Removing members from group?
		if (isset($_POST['remove']) && !empty($_REQUEST['rem']) && is_array($_REQUEST['rem']) && Utils::$context['group']->assignable) {
			User::$me->checkSession();
			SecurityToken::validate('mod-mgm');

			Utils::$context['group']->removeMembers($_REQUEST['rem'], true);
		}
		// Adding members to the group?
		elseif (isset($_REQUEST['add']) && (!empty($_REQUEST['toAdd']) || !empty($_REQUEST['member_add'])) && Utils::$context['group']->assignable) {
			User::$me->checkSession();
			SecurityToken::validate('mod-mgm');

			$member_query = [];
			$member_parameters = [];

			// Get all the members to be added... taking into account names can be quoted ;)
			$_REQUEST['toAdd'] = strtr(Utils::htmlspecialchars($_REQUEST['toAdd'], ENT_QUOTES), ['&quot;' => '"']);

			preg_match_all('~"([^"]+)"~', $_REQUEST['toAdd'], $matches);

			$member_names = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_REQUEST['toAdd']))));

			foreach ($member_names as $index => $member_name) {
				$member_names[$index] = trim(Utils::strtolower($member_names[$index]));

				if (strlen($member_names[$index]) == 0) {
					unset($member_names[$index]);
				}
			}

			// Any passed by ID?
			$member_ids = [];

			if (!empty($_REQUEST['member_add'])) {
				foreach ($_REQUEST['member_add'] as $id) {
					if ($id > 0) {
						$member_ids[] = (int) $id;
					}
				}
			}

			// Construct the query pelements.
			if (!empty($member_ids)) {
				$member_query[] = 'id_member IN ({array_int:member_ids})';
				$member_parameters['member_ids'] = $member_ids;
			}

			if (!empty($member_names)) {
				$member_query[] = 'LOWER(member_name) IN ({array_string:member_names})';
				$member_query[] = 'LOWER(real_name) IN ({array_string:member_names})';
				$member_parameters['member_names'] = $member_names;
			}

			$members = [];

			if (!empty($member_query)) {
				$request = Db::$db->query(
					'',
					'SELECT id_member
					FROM {db_prefix}members
					WHERE (' . implode(' OR ', $member_query) . ')
						AND id_group != {int:id_group}
						AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
					array_merge($member_parameters, [
						'id_group' => $_REQUEST['group'],
					]),
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					$members[] = $row['id_member'];
				}
				Db::$db->free_result($request);
			}

			// @todo Add $_POST['additional'] to templates!

			// Do the updates...
			if (!empty($members)) {
				Utils::$context['group']->addMembers($members, isset($_POST['additional']) ? 'only_additional' : 'auto', true);
			}
		}

		// Show the UI for adding/removing members?
		if (Utils::$context['group']->assignable) {
			Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
			Utils::$context['can_add_remove'] = true;
		}
	}

	/**
	 * Show and manage all group requests.
	 */
	public function requests(): void
	{
		// Set up the template stuff...
		Utils::$context['page_title'] = Lang::$txt['mc_group_requests'];
		Utils::$context['sub_template'] = 'show_list';

		// Verify we can be here.
		if (User::$me->mod_cache['gq'] == '0=1') {
			User::$me->isAllowedTo('manage_membergroups');
		}

		// Normally, we act normally...
		$where = (User::$me->mod_cache['gq'] == '1=1' || User::$me->mod_cache['gq'] == '0=1' ? User::$me->mod_cache['gq'] : 'lgr.' . User::$me->mod_cache['gq']);

		$where .= ' AND lgr.status ' . (isset($_GET['closed']) ? '!=' : '=') . ' {int:status_open}';

		$where_parameters = [
			'status_open' => 0,
		];

		// We've submitted?
		if (isset($_POST[Utils::$context['session_var']]) && !empty($_POST['groupr']) && !empty($_POST['req_action'])) {
			User::$me->checkSession();
			SecurityToken::validate('mod-gr');

			// Clean the values.
			foreach ($_POST['groupr'] as $k => $request) {
				$_POST['groupr'][$k] = (int) $request;
			}

			$log_changes = [];

			// If we are giving a reason (And why shouldn't we?), then we don't actually do much.
			if ($_POST['req_action'] == 'reason') {
				// Different sub template...
				Utils::$context['sub_template'] = 'group_request_reason';

				// And a limitation. We don't care that the page number bit makes no sense, as we don't need it!
				$where .= ' AND lgr.id_request IN ({array_int:request_ids})';
				$where_parameters['request_ids'] = $_POST['groupr'];

				Utils::$context['group_requests'] = self::list_getGroupRequests(0, Config::$modSettings['defaultMaxListItems'], 'lgr.id_request', $where, $where_parameters);

				// Need to make another token for this.
				SecurityToken::create('mod-gr');

				// Let obExit etc sort things out.
				Utils::obExit();
			}
			// Otherwise we do something!
			else {
				$request_list = [];

				$members_to_add = [];

				$request = Db::$db->query(
					'',
					'SELECT lgr.id_request, lgr.id_group, lgr.id_member
					FROM {db_prefix}log_group_requests AS lgr
					WHERE ' . $where . '
						AND lgr.id_request IN ({array_int:request_list})',
					[
						'request_list' => $_POST['groupr'],
						'status_open' => 0,
					],
				);

				while ($row = Db::$db->fetch_assoc($request)) {
					if ($_POST['req_action'] === 'approve') {
						$members_to_add[$row['id_group']] = $row['id_member'];
					}

					if (!isset($log_changes[$row['id_request']])) {
						$log_changes[$row['id_request']] = [
							'id_request' => $row['id_request'],
							'status' => $_POST['req_action'] == 'approve' ? 1 : 2, // 1 = approved, 2 = rejected
							'id_member_acted' => User::$me->id,
							'member_name_acted' => User::$me->name,
							'time_acted' => time(),
							'act_reason' => $_POST['req_action'] != 'approve' && !empty($_POST['groupreason']) && !empty($_POST['groupreason'][$row['id_request']]) ? Utils::htmlspecialchars($_POST['groupreason'][$row['id_request']], ENT_QUOTES) : '',
						];
					}

					$request_list[] = $row['id_request'];
				}
				Db::$db->free_result($request);

				if (!empty($members_to_add)) {
					foreach ($members_to_add as $group_id => $members) {
						$groups = Group::load((int) $group_id);

						$result = false;

						/** @var \SMF\Group $group */
						if ($groups != [] && ($group = $groups[$group_id]) && $group instanceof Group) {
							$result = $group->addMembers($members);
						}

						if (!$result) {
							ErrorHandler::fatalLang('membergroup_does_not_exist', false);
						}
					}
				}

				// Add a background task to handle notifying people of this request
				$data = Utils::jsonEncode([
					'member_id' => User::$me->id,
					'member_ip' => User::$me->ip,
					'request_list' => $request_list,
					'status' => $_POST['req_action'],
					'reason' => $_POST['groupreason'] ?? '',
					'time' => time(),
				]);

				Db::$db->insert(
					'insert',
					'{db_prefix}background_tasks',
					[
						'task_class' => 'string-255',
						'task_data' => 'string',
						'claimed_time' => 'int',
					],
					[
						'SMF\\Tasks\\GroupAct_Notify',
						$data,
						0,
					],
					[],
				);

				// Some changes to log?
				if (!empty($log_changes)) {
					foreach ($log_changes as $id_request => $details) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}log_group_requests
							SET status = {int:status},
								id_member_acted = {int:id_member_acted},
								member_name_acted = {string:member_name_acted},
								time_acted = {int:time_acted},
								act_reason = {string:act_reason}
							WHERE id_request = {int:id_request}',
							$details,
						);
					}
				}
			}
		}

		// This is all the information required for a group listing.
		$listOptions = [
			'id' => 'group_request_list',
			'width' => '100%',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['mc_groupr_none_found'],
			'base_href' => Config::$scripturl . $this->action_url . ';sa=requests',
			'default_sort_col' => 'member',
			'get_items' => [
				'function' => __CLASS__ . '::list_getGroupRequests',
				'params' => [
					$where,
					$where_parameters,
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getGroupRequestCount',
				'params' => [
					$where,
					$where_parameters,
				],
			],
			'columns' => [
				'member' => [
					'header' => [
						'value' => Lang::$txt['mc_groupr_member'],
					],
					'data' => [
						'db' => 'member_link',
					],
					'sort' => [
						'default' => 'mem.member_name',
						'reverse' => 'mem.member_name DESC',
					],
				],
				'group' => [
					'header' => [
						'value' => Lang::$txt['mc_groupr_group'],
					],
					'data' => [
						'db' => 'group_link',
					],
					'sort' => [
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					],
				],
				'reason' => [
					'header' => [
						'value' => Lang::$txt['mc_groupr_reason'],
					],
					'data' => [
						'db' => 'reason',
					],
				],
				'date' => [
					'header' => [
						'value' => Lang::$txt['date'],
						'style' => 'width: 18%; white-space:nowrap;',
					],
					'data' => [
						'db' => 'time_submitted',
					],
				],
				'action' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="groupr[]" value="%1$d">',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . $this->action_url . ';sa=requests',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
				],
				'token' => 'mod-gr',
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '
						<select id="req_action" name="req_action" onchange="if (this.value != 0 &amp;&amp; (this.value == \'reason\' || confirm(\'' . Lang::$txt['mc_groupr_warning'] . '\'))) this.form.submit();">
							<option value="0">' . Lang::$txt['with_selected'] . ':</option>
							<option value="0" disabled>---------------------</option>
							<option value="approve">' . Lang::$txt['mc_groupr_approve'] . '</option>
							<option value="reject">' . Lang::$txt['mc_groupr_reject'] . '</option>
							<option value="reason">' . Lang::$txt['mc_groupr_reject_w_reason'] . '</option>
						</select>
						<input type="submit" name="go" value="' . Lang::$txt['go'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . Lang::$txt['mc_groupr_warning'] . '\')) return false;" class="button">',
					'class' => 'floatright',
				],
			],
		];

		if (isset($_GET['closed'])) {
			// Closed requests don't require interaction.
			unset($listOptions['columns']['action'], $listOptions['form'], $listOptions['additional_rows'][0]);
			$listOptions['base_href'] .= 'closed';
		}

		// Create the request list.
		SecurityToken::create('mod-gr');
		new ItemList($listOptions);

		Utils::$context['default_list'] = 'group_request_list';
		Menu::$loaded['moderate']->tab_data = [
			'title' => Lang::$txt['mc_group_requests'],
		];
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Callback function for SMF\ItemList().
	 *
	 * @param string $where The WHERE clause for the query
	 * @param array $where_parameters The parameters for the WHERE clause
	 * @return int The number of group requests
	 */
	public static function list_getGroupRequestCount($where, $where_parameters): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_group_requests AS lgr
			WHERE ' . $where,
			array_merge($where_parameters, [
			]),
		);
		list($totalRequests) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $totalRequests;
	}

	/**
	 * Callback function for SMF\ItemList().
	 *
	 * @param int $start The result to start with.
	 * @param int $items_per_page The number of items per page.
	 * @param string $sort An SQL sort expression (column/direction).
	 * @param string $where Data for the WHERE clause.
	 * @param array $where_parameters Parameter values to be inserted into the WHERE clause.
	 * @return array An array of group requests.
	 * Each group request has:
	 * 		'id'
	 * 		'member_link'
	 * 		'group_link'
	 * 		'reason'
	 * 		'time_submitted'
	 */
	public static function list_getGroupRequests($start, $items_per_page, $sort, $where, $where_parameters): array
	{
		$group_requests = [];

		$request = Db::$db->query(
			'',
			'SELECT
				lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, lgr.reason,
				lgr.status, lgr.id_member_acted, lgr.member_name_acted, lgr.time_acted, lgr.act_reason,
				mem.member_name, mg.group_name, mg.online_color, mem.real_name
			FROM {db_prefix}log_group_requests AS lgr
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
			WHERE ' . $where . '
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			array_merge($where_parameters, [
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			]),
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (empty($row['reason'])) {
				$reason = '<em>(' . Lang::$txt['mc_groupr_no_reason'] . ')</em>';
			} else {
				$reason = Lang::censorText($row['reason']);
			}

			if (isset($_GET['closed'])) {
				if ($row['status'] == 1) {
					$reason .= '<br><br><strong>' . Lang::$txt['mc_groupr_approved'] . '</strong>';
				} elseif ($row['status'] == 2) {
					$reason .= '<br><br><strong>' . Lang::$txt['mc_groupr_rejected'] . '</strong>';
				}

				$reason .= ' (' . Time::create('@' . $row['time_acted'])->format() . ')';

				if (!empty($row['act_reason'])) {
					$reason .= '<br><br>' . Lang::censorText($row['act_reason']);
				}
			}

			$group_requests[] = [
				'id' => $row['id_request'],
				'member_link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'group_link' => '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>',
				'reason' => $reason,
				'time_submitted' => Time::create('@' . $row['time_applied'])->format(),
			];
		}
		Db::$db->free_result($request);

		return $group_requests;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		IntegrationHook::call('integrate_manage_groups', [&self::$subactions]);

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}
}

?>