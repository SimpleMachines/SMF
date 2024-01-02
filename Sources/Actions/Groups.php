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

use SMF\Actions\Moderation\Main as ModCenter;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\PageIndex;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Shows group info and allows certain priviledged members to add/remove members.
 */
class Groups implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Groups',
			'list_getMembergroups' => 'list_getMembergroups',
			'listMembergroupMembers_Href' => 'listMembergroupMembers_Href',
			'list_getGroupRequestCount' => 'list_getGroupRequestCount',
			'list_getGroupRequests' => 'list_getGroupRequests',
			'GroupList' => 'GroupList',
			'MembergroupMembers' => 'MembergroupMembers',
			'GroupRequests' => 'GroupRequests',
		],
	];

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
	 *
	 * Possible values:
	 *  - '?action=groups'
	 *  - '?action=moderate;area=groups'
	 *  - '?action=moderate;area=viewgroups'
	 */
	protected string $action_url = '?action=groups';

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
	 *
	 * It allows moderators and users to access the group showing functions.
	 * It handles permission checks, and puts the moderation bar on as required.
	 */
	public function execute(): void
	{
		// Get the template stuff up and running.
		Lang::load('ManageMembers');
		Lang::load('ModerationCenter');
		Theme::loadTemplate('ManageMembergroups');

		// If needed, set the mod center menu.
		if (User::$me->allowedTo('access_mod_center') && (User::$me->mod_cache['gq'] != '0=1' || User::$me->allowedTo('manage_membergroups')) && !isset(Menu::$loaded['admin']) && !isset(Menu::$loaded['moderate'])) {
			$_GET['area'] = $this->subaction == 'requests' ? 'groups' : 'viewgroups';

			$this->action_url = '?action=moderate;area=' . $_GET['area'];

			ModCenter::load()->createMenu();
		}
		// Otherwise add something to the link tree, for normal people.
		else {
			User::$me->isAllowedTo('view_mlist');

			$this->action_url = '?action=groups';

			Utils::$context['linktree'][] = [
				'url' => Config::$scripturl . $this->action_url,
				'name' => Lang::$txt['groups'],
			];
		}

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * This very simply lists the groups, nothing snazy.
	 */
	public function index(): void
	{
		Utils::$context['page_title'] = Lang::$txt['viewing_groups'];

		// Use the standard templates for showing this.
		$listOptions = [
			'id' => 'group_lists',
			'title' => Utils::$context['page_title'],
			'base_href' => Config::$scripturl . $this->action_url . ';sa=view',
			'default_sort_col' => 'group',
			'get_items' => [
				'function' => __CLASS__ . '::list_getMembergroups',
				'params' => [
					'regular',
				],
			],
			'columns' => [
				'group' => [
					'header' => [
						'value' => Lang::$txt['name'],
					],
					'data' => [
						'function' => function ($rowData) {
							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData['id_group'] == 3) {
								$group_name = $rowData['group_name'];
							} else {
								$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);

								$group_name = sprintf('<a href="%1$s' . $this->action_url . ';sa=members;sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData['id_group'] == 1) {
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							} elseif ($rowData['id_group'] == 3) {
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							}

							return $group_name;
						},
					],
					'sort' => [
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					],
				],
				'icons' => [
					'header' => [
						'value' => Lang::$txt['membergroups_icons'],
					],
					'data' => [
						'db' => 'icons',
					],
					'sort' => [
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					],
				],
				'moderators' => [
					'header' => [
						'value' => Lang::$txt['moderators'],
					],
					'data' => [
						'function' => function ($group) {
							return empty($group['moderators']) ? '<em>' . Lang::$txt['membergroups_new_copy_none'] . '</em>' : implode(', ', $group['moderators']);
						},
					],
				],
				'members' => [
					'header' => [
						'value' => Lang::$txt['membergroups_members_top'],
					],
					'data' => [
						'function' => function ($rowData) {
							// No explicit members for the moderator group.
							return $rowData['id_group'] == 3 ? Lang::$txt['membergroups_guests_na'] : Lang::numberFormat($rowData['num_members']);
						},
						'class' => 'centercol',
					],
					'sort' => [
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					],
				],
			],
		];

		// Create the request list.
		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'group_lists';
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
		$_REQUEST['group'] = isset($_REQUEST['group']) ? (int) $_REQUEST['group'] : 0;

		// No browsing of guests, membergroup 0 or moderators.
		if (in_array($_REQUEST['group'], [-1, 0, 3])) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		// Load up the group details.
		@list($group) = Group::load($_REQUEST['group']);

		if (empty($group->id)) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		Utils::$context['group'] = $group;

		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . $this->action_url . ';sa=members;group=' . $group->id,
			'name' => $group->name,
		];
		Utils::$context['can_send_email'] = User::$me->allowedTo('moderate_forum');

		// Load all the group moderators, for fun.
		User::load($group->loadModerators(), User::LOAD_BY_ID, 'minimal');

		foreach ($group->moderator_ids as $mod_id) {
			$group->moderators[] = [
				'id' => $mod_id,
				'name' => User::$loaded[$mod_id]->name,
			];
		}

		// If this group is hidden then it can only "exist" if the user can moderate it!
		if ($group->hidden === Group::INVISIBLE && !$group->can_moderate) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		// Removing member from group?
		if (isset($_POST['remove']) && !empty($_REQUEST['rem']) && is_array($_REQUEST['rem']) && $group->assignable) {
			User::$me->checkSession();
			SecurityToken::validate('mod-mgm');

			$group->removeMembers($_REQUEST['rem'], true);
		}
		// Must be adding new members to the group...
		elseif (isset($_REQUEST['add']) && (!empty($_REQUEST['toAdd']) || !empty($_REQUEST['member_add'])) && $group->assignable) {
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
				@list($group) = Group::load((int) $_REQUEST['group']);

				if ($group instanceof Group) {
					$group->addMembers($members, isset($_POST['additional']) ? 'only_additional' : 'auto', true);
				}
			}
		}

		// Sort out the sorting!
		$sort_methods = [
			'name' => 'real_name',
			'email' => 'email_address',
			'active' => 'last_login',
			'registered' => 'date_registered',
			'posts' => 'posts',
		];

		// They didn't pick one, default to by name..
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']])) {
			Utils::$context['sort_by'] = 'name';
			$querySort = 'real_name';
		}
		// Otherwise default to ascending.
		else {
			Utils::$context['sort_by'] = $_REQUEST['sort'];
			$querySort = $sort_methods[$_REQUEST['sort']];
		}

		Utils::$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';

		// Count members of the group.
		Utils::$context['total_members'] = $group->countMembers();

		// Create the page index.
		Utils::$context['page_index'] = new PageIndex(Config::$scripturl . $this->action_url . ';sa=members;group=' . $_REQUEST['group'] . ';sort=' . Utils::$context['sort_by'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], Utils::$context['total_members'], Config::$modSettings['defaultMaxMembers']);
		Utils::$context['total_members'] = Lang::numberFormat(Utils::$context['total_members']);
		Utils::$context['start'] = $_REQUEST['start'];
		Utils::$context['can_moderate_forum'] = User::$me->allowedTo('moderate_forum');

		// Load up all members of this group.
		Utils::$context['members'] = [];

		if ($group->loadMembers() !== []) {
			foreach (User::load($group->members, User::LOAD_BY_ID, 'normal') as $member) {
				Utils::$context['members'][] = $member->format();
			}
		}

		// Select the template.
		Utils::$context['sub_template'] = 'group_members';
		Utils::$context['page_title'] = Lang::$txt['membergroups_members_title'] . ': ' . $group->name;
		SecurityToken::create('mod-mgm');

		if ($group->assignable) {
			Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
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
					'SELECT lgr.id_request
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
						@list($group) = Group::load((int) $group_id);

						if ($group instanceof Group) {
							$group->addMembers($members);
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
	 * Helper function to generate a list of membergroups for display
	 *
	 * @param int $start What item to start with (not used here)
	 * @param int $items_per_page How many items to show on each page (not used here)
	 * @param string $sort An SQL query indicating how to sort the results
	 * @param string $membergroup_type Should be 'post_count' for post groups or anything else for regular groups
	 * @return array An array of group member info for the list
	 */
	public static function list_getMembergroups($start, $items_per_page, $sort, $membergroup_type): array
	{
		// Start collecting the data.
		$groups = [];
		$group_ids = [];
		Utils::$context['can_moderate'] = User::$me->allowedTo('manage_membergroups');

		$query_customizations = [
			'where' => [
				'mg.min_posts' . ($membergroup_type === 'post_count' ? '!= ' : '= ') . '-1',
			],
			'order' => (array) $sort,
		];

		if (!User::$me->allowedTo('admin_forum')) {
			$query_customizations['where'][] = 'mg.id_group != {int:mod_group}';
			$query_customizations['params']['mod_group'] = Group::MOD;
		}

		$temp = Group::load([], $query_customizations);
		Group::loadModeratorsBatch(array_map(fn ($group) => $group->id, $temp));

		foreach ($temp as $group) {
			// We only list the groups they can see.
			if ($group->hidden === Group::INVISIBLE && !$group->can_moderate) {
				continue;
			}

			Utils::$context['can_moderate'] |= $group->can_moderate;

			$group->description = BBCodeParser::load()->parse($group->description, false, '', Utils::$context['description_allowed_tags']);

			$groups[$group->id] = $group;
			$group_ids[] = $group->id;
		}

		// If we found any membergroups, get the amount of members in them.
		if (!empty($group_ids)) {
			Group::countMembersBatch($group_ids);
			Group::loadModeratorsBatch($group_ids);

			foreach ($group_ids as $group_id) {
				User::load(Group::$loaded[$group_id]->moderator_ids);

				foreach (Group::$loaded[$group_id]->moderator_ids as $mod_id) {
					if (!isset(User::$loaded[$mod_id])) {
						continue;
					}

					Group::$loaded[$group_id]->moderators[] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $mod_id . '">' . User::$loaded[$mod_id]->name . '</a>';
				}
			}
		}

		// Apply manual sorting if the 'number of members' column is selected.
		if (substr($sort, 0, 1) == '1' || strpos($sort, ', 1') !== false) {
			$sort_ascending = strpos($sort, 'DESC') === false;

			foreach ($groups as $group) {
				$sort_array[] = $group->id_group != Group::MOD ? (int) $group->num_members : Group::GUEST;
			}

			array_multisort($sort_array, $sort_ascending ? SORT_ASC : SORT_DESC, SORT_REGULAR, $groups);
		}

		return $groups;
	}

	/**
	 * Gets the members of a supplied membergroup.
	 * Returns them as a link for display.
	 *
	 * @param array &$members The IDs of the members.
	 * @param int $membergroup The ID of the group.
	 * @param int $limit How many members to show (null for no limit).
	 * @return bool True if there are more members to display, false otherwise.
	 */
	public static function listMembergroupMembers_Href(&$members, $membergroup, $limit = null): bool
	{
		$members = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_group = {int:id_group} OR FIND_IN_SET({int:id_group}, additional_groups) != 0' . ($limit === null ? '' : '
			LIMIT ' . ($limit + 1)),
			[
				'id_group' => $membergroup,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$members[$row['id_member']] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		}
		Db::$db->free_result($request);

		// If there are more than $limit members, add a 'more' link.
		if ($limit !== null && count($members) > $limit) {
			array_pop($members);

			return true;
		}

		return false;
	}

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
	 * @param string $where_parameters Parameter values to be inserted into the WHERE clause.
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

	/**
	 * Backward compatibility wrapper for index sub-action.
	 */
	public static function GroupList(): void
	{
		self::load();
		self::$obj->subaction = 'index';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for members sub-action.
	 */
	public static function MembergroupMembers(): void
	{
		self::load();
		self::$obj->subaction = 'members';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for requests sub-action.
	 */
	public static function GroupRequests(): void
	{
		self::load();
		self::$obj->subaction = 'requests';
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
		IntegrationHook::call('integrate_manage_groups', [&self::$subactions]);

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Groups::exportStatic')) {
	Groups::exportStatic();
}

?>