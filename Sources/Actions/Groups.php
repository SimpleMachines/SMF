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

namespace SMF\Actions;

use SMF\BackwardCompatibility;

use SMF\BBCodeParser;
use SMF\Config;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

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
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'Groups',
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
	public string $subaction = 'index';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = array(
		'index' => 'index',
		'members' => 'members',
		'requests' => 'requests',
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

		// If we can see the moderation center, and this has a mod bar entry, add the mod center bar.
		if (allowedTo('access_mod_center') || User::$me->mod_cache['bq'] != '0=1' || User::$me->mod_cache['gq'] != '0=1' || allowedTo('manage_membergroups'))
		{
			require_once(Config::$sourcedir . '/Actions/Moderation/Main.php');
			$_GET['area'] = $this->subaction == 'requests' ? 'groups' : 'viewgroups';
			ModerationMain(true);
		}
		// Otherwise add something to the link tree, for normal people.
		else
		{
			isAllowedTo('view_mlist');

			Utils::$context['linktree'][] = array(
				'url' => Config::$scripturl . '?action=groups',
				'name' => Lang::$txt['groups'],
			);
		}

		call_helper(method_exists($this, self::$subactions[$this->subaction]) ? array($this, self::$subactions[$this->subaction]) : self::$subactions[$this->subaction]);
	}

	/**
	 * This very simply lists the groups, nothing snazy.
	 */
	public function index(): void
	{
		Utils::$context['page_title'] = Lang::$txt['viewing_groups'];

		// Use the standard templates for showing this.
		$listOptions = array(
			'id' => 'group_lists',
			'title' => Utils::$context['page_title'],
			'base_href' => Config::$scripturl . '?action=moderate;area=viewgroups;sa=view',
			'default_sort_col' => 'group',
			'get_items' => array(
				'function' => __CLASS__ . '::list_getMembergroups',
				'params' => array(
					'regular',
				),
			),
			'columns' => array(
				'group' => array(
					'header' => array(
						'value' => Lang::$txt['name'],
					),
					'data' => array(
						'function' => function($rowData)
						{
							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData['id_group'] == 3)
							{
								$group_name = $rowData['group_name'];
							}
							else
							{
								$color_style = empty($rowData['online_color']) ? '' : sprintf(' style="color: %1$s;"', $rowData['online_color']);

								if (allowedTo('manage_membergroups'))
								{
									$group_name = sprintf('<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
								}
								else
								{
									$group_name = sprintf('<a href="%1$s?action=groups;sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
								}
							}

							// Add a help option for moderator and administrator.
							if ($rowData['id_group'] == 1)
							{
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							}
							elseif ($rowData['id_group'] == 3)
							{
								$group_name .= sprintf(' (<a href="%1$s?action=helpadmin;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)', Config::$scripturl);
							}

							return $group_name;
						},
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_icons'],
					),
					'data' => array(
						'db' => 'icons',
					),
					'sort' => array(
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					)
				),
				'moderators' => array(
					'header' => array(
						'value' => Lang::$txt['moderators'],
					),
					'data' => array(
						'function' => function($group)
						{
							return empty($group['moderators']) ? '<em>' . Lang::$txt['membergroups_new_copy_none'] . '</em>' : implode(', ', $group['moderators']);
						},
					),
				),
				'members' => array(
					'header' => array(
						'value' => Lang::$txt['membergroups_members_top'],
					),
					'data' => array(
						'function' => function($rowData)
						{
							// No explicit members for the moderator group.
							return $rowData['id_group'] == 3 ? Lang::$txt['membergroups_guests_na'] : Lang::numberFormat($rowData['num_members']);
						},
						'class' => 'centercol',
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
			),
		);

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
		if (in_array($_REQUEST['group'], array(-1, 0, 3)))
			fatal_lang_error('membergroup_does_not_exist', false);

		// Load up the group details.
		$request = Db::$db->query('', '
			SELECT id_group AS id, group_name AS name, CASE WHEN min_posts = {int:min_posts} THEN 1 ELSE 0 END AS assignable, hidden, online_color,
				icons, description, CASE WHEN min_posts != {int:min_posts} THEN 1 ELSE 0 END AS is_post_group, group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:id_group}
			LIMIT 1',
			array(
				'min_posts' => -1,
				'id_group' => $_REQUEST['group'],
			)
		);
		// Doesn't exist?
		if (Db::$db->num_rows($request) == 0)
		{
			fatal_lang_error('membergroup_does_not_exist', false);
		}
		Utils::$context['group'] = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		// Fix the membergroup icons.
		Utils::$context['group']['icons'] = explode('#', Utils::$context['group']['icons']);
		Utils::$context['group']['icons'] = !empty(Utils::$context['group']['icons'][0]) && !empty(Utils::$context['group']['icons'][1]) ? str_repeat('<img src="' . Theme::$current->settings['images_url'] . '/membericons/' . Utils::$context['group']['icons'][1] . '" alt="*">', Utils::$context['group']['icons'][0]) : '';
		Utils::$context['group']['can_moderate'] = allowedTo('manage_membergroups') && (allowedTo('admin_forum') || Utils::$context['group']['group_type'] != 1);

		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=groups;sa=members;group=' . Utils::$context['group']['id'],
			'name' => Utils::$context['group']['name'],
		);
		Utils::$context['can_send_email'] = allowedTo('moderate_forum');

		// Load all the group moderators, for fun.
		Utils::$context['group']['moderators'] = array();
		$request = Db::$db->query('', '
			SELECT mem.id_member, mem.real_name
			FROM {db_prefix}group_moderators AS mods
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
			WHERE mods.id_group = {int:id_group}',
			array(
				'id_group' => $_REQUEST['group'],
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['group']['moderators'][] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name']
			);

			if (User::$me->id == $row['id_member'] && Utils::$context['group']['group_type'] != 1)
				Utils::$context['group']['can_moderate'] = true;
		}
		Db::$db->free_result($request);

		// If this group is hidden then it can only "exist" if the user can moderate it!
		if (Utils::$context['group']['hidden'] && !Utils::$context['group']['can_moderate'])
			fatal_lang_error('membergroup_does_not_exist', false);

		// You can only assign membership if you are the moderator and/or can manage groups!
		if (!Utils::$context['group']['can_moderate'])
		{
			Utils::$context['group']['assignable'] = 0;
		}
		// Non-admins cannot assign admins.
		elseif (Utils::$context['group']['id'] == 1 && !allowedTo('admin_forum'))
		{
			Utils::$context['group']['assignable'] = 0;
		}

		// Removing member from group?
		if (isset($_POST['remove']) && !empty($_REQUEST['rem']) && is_array($_REQUEST['rem']) && Utils::$context['group']['assignable'])
		{
			checkSession();
			validateToken('mod-mgm');

			// Only proven admins can remove admins.
			if (Utils::$context['group']['id'] == 1)
				validateSession();

			// Make sure we're dealing with integers only.
			foreach ($_REQUEST['rem'] as $key => $group)
				$_REQUEST['rem'][$key] = (int) $group;

			self::removeMembers($_REQUEST['rem'], $_REQUEST['group'], true);
		}
		// Must be adding new members to the group...
		elseif (isset($_REQUEST['add']) && (!empty($_REQUEST['toAdd']) || !empty($_REQUEST['member_add'])) && Utils::$context['group']['assignable'])
		{
			// Demand an admin password before adding new admins -- every time, no matter what.
			if (Utils::$context['group']['id'] == 1)
				validateSession('admin', true);

			checkSession();
			validateToken('mod-mgm');

			$member_query = array();
			$member_parameters = array();

			// Get all the members to be added... taking into account names can be quoted ;)
			$_REQUEST['toAdd'] = strtr(Utils::htmlspecialchars($_REQUEST['toAdd'], ENT_QUOTES), array('&quot;' => '"'));

			preg_match_all('~"([^"]+)"~', $_REQUEST['toAdd'], $matches);

			$member_names = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_REQUEST['toAdd']))));

			foreach ($member_names as $index => $member_name)
			{
				$member_names[$index] = trim(Utils::strtolower($member_names[$index]));

				if (strlen($member_names[$index]) == 0)
					unset($member_names[$index]);
			}

			// Any passed by ID?
			$member_ids = array();
			if (!empty($_REQUEST['member_add']))
			{
				foreach ($_REQUEST['member_add'] as $id)
				{
					if ($id > 0)
						$member_ids[] = (int) $id;
				}
			}

			// Construct the query pelements.
			if (!empty($member_ids))
			{
				$member_query[] = 'id_member IN ({array_int:member_ids})';
				$member_parameters['member_ids'] = $member_ids;
			}
			if (!empty($member_names))
			{
				$member_query[] = 'LOWER(member_name) IN ({array_string:member_names})';
				$member_query[] = 'LOWER(real_name) IN ({array_string:member_names})';
				$member_parameters['member_names'] = $member_names;
			}

			$members = array();
			if (!empty($member_query))
			{
				$request = Db::$db->query('', '
					SELECT id_member
					FROM {db_prefix}members
					WHERE (' . implode(' OR ', $member_query) . ')
						AND id_group != {int:id_group}
						AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
					array_merge($member_parameters, array(
						'id_group' => $_REQUEST['group'],
					))
				);
				while ($row = Db::$db->fetch_assoc($request))
				{
					$members[] = $row['id_member'];
				}
				Db::$db->free_result($request);
			}

			// @todo Add $_POST['additional'] to templates!

			// Do the updates...
			if (!empty($members))
			{
				self::addMembers($members, $_REQUEST['group'], isset($_POST['additional']) || Utils::$context['group']['hidden'] ? 'only_additional' : 'auto', true);
			}
		}

		// Sort out the sorting!
		$sort_methods = array(
			'name' => 'real_name',
			'email' => 'email_address',
			'active' => 'last_login',
			'registered' => 'date_registered',
			'posts' => 'posts',
		);

		// They didn't pick one, default to by name..
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
		{
			Utils::$context['sort_by'] = 'name';
			$querySort = 'real_name';
		}
		// Otherwise default to ascending.
		else
		{
			Utils::$context['sort_by'] = $_REQUEST['sort'];
			$querySort = $sort_methods[$_REQUEST['sort']];
		}

		Utils::$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';

		// The where on the query is interesting. Non-moderators should only see people who are in this group as primary.
		if (Utils::$context['group']['can_moderate'])
		{
			$where = Utils::$context['group']['is_post_group'] ? 'id_post_group = {int:group}' : 'id_group = {int:group} OR FIND_IN_SET({int:group}, additional_groups) != 0';
		}
		else
		{
			$where = Utils::$context['group']['is_post_group'] ? 'id_post_group = {int:group}' : 'id_group = {int:group}';
		}

		// Count members of the group.
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE ' . $where,
			array(
				'group' => $_REQUEST['group'],
			)
		);
		list(Utils::$context['total_members']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Create the page index.
		Utils::$context['page_index'] = constructPageIndex(Config::$scripturl . '?action=' . (Utils::$context['group']['can_moderate'] ? 'moderate;area=viewgroups' : 'groups') . ';sa=members;group=' . $_REQUEST['group'] . ';sort=' . Utils::$context['sort_by'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], Utils::$context['total_members'], Config::$modSettings['defaultMaxMembers']);
		Utils::$context['total_members'] = Lang::numberFormat(Utils::$context['total_members']);
		Utils::$context['start'] = $_REQUEST['start'];
		Utils::$context['can_moderate_forum'] = allowedTo('moderate_forum');

		// Load up all members of this group.
		Utils::$context['members'] = array();
		$request = Db::$db->query('', '
			SELECT id_member, member_name, real_name, email_address, member_ip, date_registered, last_login,
				posts, is_activated, real_name
			FROM {db_prefix}members
			WHERE ' . $where . '
			ORDER BY ' . $querySort . ' ' . (Utils::$context['sort_direction'] == 'down' ? 'DESC' : 'ASC') . '
			LIMIT {int:start}, {int:max}',
			array(
				'group' => $_REQUEST['group'],
				'start' => Utils::$context['start'],
				'max' => Config::$modSettings['defaultMaxMembers'],
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$row['member_ip'] = inet_dtop($row['member_ip']);
			$last_online = empty($row['last_login']) ? Lang::$txt['never'] : timeformat($row['last_login']);

			// Italicize the online note if they aren't activated.
			if ($row['is_activated'] % 10 != 1)
				$last_online = '<em title="' . Lang::$txt['not_activated'] . '">' . $last_online . '</em>';

			Utils::$context['members'][] = array(
				'id' => $row['id_member'],
				'name' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'email' => $row['email_address'],
				'ip' => '<a href="' . Config::$scripturl . '?action=trackip;searchip=' . $row['member_ip'] . '">' . $row['member_ip'] . '</a>',
				'registered' => timeformat($row['date_registered']),
				'last_online' => $last_online,
				'posts' => Lang::numberFormat($row['posts']),
				'is_activated' => $row['is_activated'] % 10 == 1,
			);
		}
		Db::$db->free_result($request);

		// Select the template.
		Utils::$context['sub_template'] = 'group_members';
		Utils::$context['page_title'] = Lang::$txt['membergroups_members_title'] . ': ' . Utils::$context['group']['name'];
		createToken('mod-mgm');

		if (Utils::$context['group']['assignable'])
		{
			Theme::loadJavaScriptFile('suggest.js', array('defer' => false, 'minimize' => true), 'smf_suggest');
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
		if (User::$me->mod_cache['gq'] == '0=1')
			isAllowedTo('manage_membergroups');

		// Normally, we act normally...
		$where = (User::$me->mod_cache['gq'] == '1=1' || User::$me->mod_cache['gq'] == '0=1' ? User::$me->mod_cache['gq'] : 'lgr.' . User::$me->mod_cache['gq']);

		$where .= ' AND lgr.status ' . (isset($_GET['closed']) ? '!=' : '=') . ' {int:status_open}';

		$where_parameters = array(
			'status_open' => 0,
		);

		// We've submitted?
		if (isset($_POST[Utils::$context['session_var']]) && !empty($_POST['groupr']) && !empty($_POST['req_action']))
		{
			checkSession();
			validateToken('mod-gr');

			// Clean the values.
			foreach ($_POST['groupr'] as $k => $request)
				$_POST['groupr'][$k] = (int) $request;

			$log_changes = array();

			// If we are giving a reason (And why shouldn't we?), then we don't actually do much.
			if ($_POST['req_action'] == 'reason')
			{
				// Different sub template...
				Utils::$context['sub_template'] = 'group_request_reason';

				// And a limitation. We don't care that the page number bit makes no sense, as we don't need it!
				$where .= ' AND lgr.id_request IN ({array_int:request_ids})';
				$where_parameters['request_ids'] = $_POST['groupr'];

				Utils::$context['group_requests'] = self::list_getGroupRequests(0, Config::$modSettings['defaultMaxListItems'], 'lgr.id_request', $where, $where_parameters);

				// Need to make another token for this.
				createToken('mod-gr');

				// Let obExit etc sort things out.
				obExit();
			}
			// Otherwise we do something!
			else
			{
				$request_list = array();

				$request = Db::$db->query('', '
					SELECT lgr.id_request
					FROM {db_prefix}log_group_requests AS lgr
					WHERE ' . $where . '
						AND lgr.id_request IN ({array_int:request_list})',
					array(
						'request_list' => $_POST['groupr'],
						'status_open' => 0,
					)
				);
				while ($row = Db::$db->fetch_assoc($request))
				{
					if (!isset($log_changes[$row['id_request']]))
					{
						$log_changes[$row['id_request']] = array(
							'id_request' => $row['id_request'],
							'status' => $_POST['req_action'] == 'approve' ? 1 : 2, // 1 = approved, 2 = rejected
							'id_member_acted' => User::$me->id,
							'member_name_acted' => User::$me->name,
							'time_acted' => time(),
							'act_reason' => $_POST['req_action'] != 'approve' && !empty($_POST['groupreason']) && !empty($_POST['groupreason'][$row['id_request']]) ? Utils::htmlspecialchars($_POST['groupreason'][$row['id_request']], ENT_QUOTES) : '',
						);
					}

					$request_list[] = $row['id_request'];
				}
				Db::$db->free_result($request);

				// Add a background task to handle notifying people of this request
				$data = Utils::jsonEncode(array('member_id' => User::$me->id, 'member_ip' => User::$me->ip, 'request_list' => $request_list, 'status' => $_POST['req_action'], 'reason' => isset($_POST['groupreason']) ? $_POST['groupreason'] : '', 'time' => time()));

				Db::$db->insert('insert', '{db_prefix}background_tasks',
					array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
					array('$sourcedir/tasks/GroupAct_Notify.php', 'SMF\Tasks\GroupAct_Notify', $data, 0), array()
				);

				// Some changes to log?
				if (!empty($log_changes))
				{
					foreach ($log_changes as $id_request => $details)
					{
						Db::$db->query('', '
							UPDATE {db_prefix}log_group_requests
							SET status = {int:status},
								id_member_acted = {int:id_member_acted},
								member_name_acted = {string:member_name_acted},
								time_acted = {int:time_acted},
								act_reason = {string:act_reason}
							WHERE id_request = {int:id_request}',
							$details
						);
					}
				}
			}
		}

		// This is all the information required for a group listing.
		$listOptions = array(
			'id' => 'group_request_list',
			'width' => '100%',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['mc_groupr_none_found'],
			'base_href' => Config::$scripturl . '?action=groups;sa=requests',
			'default_sort_col' => 'member',
			'get_items' => array(
				'function' => __CLASS__ . '::list_getGroupRequests',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'get_count' => array(
				'function' => __CLASS__ . '::list_getGroupRequestCount',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'columns' => array(
				'member' => array(
					'header' => array(
						'value' => Lang::$txt['mc_groupr_member'],
					),
					'data' => array(
						'db' => 'member_link',
					),
					'sort' => array(
						'default' => 'mem.member_name',
						'reverse' => 'mem.member_name DESC',
					),
				),
				'group' => array(
					'header' => array(
						'value' => Lang::$txt['mc_groupr_group'],
					),
					'data' => array(
						'db' => 'group_link',
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'reason' => array(
					'header' => array(
						'value' => Lang::$txt['mc_groupr_reason'],
					),
					'data' => array(
						'db' => 'reason',
					),
				),
				'date' => array(
					'header' => array(
						'value' => Lang::$txt['date'],
						'style' => 'width: 18%; white-space:nowrap;',
					),
					'data' => array(
						'db' => 'time_submitted',
					),
				),
				'action' => array(
					'header' => array(
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="groupr[]" value="%1$d">',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centercol',
					),
				),
			),
			'form' => array(
				'href' => Config::$scripturl . '?action=groups;sa=requests',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					Utils::$context['session_var'] => Utils::$context['session_id'],
				),
				'token' => 'mod-gr',
			),
			'additional_rows' => array(
				array(
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
				),
			),
		);

		if (isset($_GET['closed']))
		{
			// Closed requests don't require interaction.
			unset($listOptions['columns']['action'], $listOptions['form'], $listOptions['additional_rows'][0]);
			$listOptions['base_href'] .= 'closed';
		}

		// Create the request list.
		createToken('mod-gr');
		new ItemList($listOptions);

		Utils::$context['default_list'] = 'group_request_list';
		Menu::$loaded['moderate']->tab_data = array(
			'title' => Lang::$txt['mc_group_requests'],
		);
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
		$request = Db::$db->query('substring_membergroups', '
			SELECT mg.id_group, mg.group_name, mg.min_posts, mg.description, mg.group_type, mg.online_color, mg.hidden,
				mg.icons, COALESCE(gm.id_member, 0) AS can_moderate, 0 AS num_members
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
			WHERE mg.min_posts {raw:min_posts}' . (allowedTo('admin_forum') ? '' : '
				AND mg.id_group != {int:mod_group}') . '
			ORDER BY {raw:sort}',
			array(
				'current_member' => User::$me->id,
				'min_posts' => ($membergroup_type === 'post_count' ? '!= ' : '= ') . -1,
				'mod_group' => 3,
				'sort' => $sort,
			)
		);

		// Start collecting the data.
		$groups = array();
		$group_ids = array();
		Utils::$context['can_moderate'] = allowedTo('manage_membergroups');
		while ($row = Db::$db->fetch_assoc($request))
		{
			// We only list the groups they can see.
			if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
				continue;

			$row['icons'] = explode('#', $row['icons']);

			$groups[$row['id_group']] = array(
				'id_group' => $row['id_group'],
				'group_name' => $row['group_name'],
				'min_posts' => $row['min_posts'],
				'desc' => BBCodeParser::load()->parse($row['description'], false, '', Utils::$context['description_allowed_tags']),
				'online_color' => $row['online_color'],
				'type' => $row['group_type'],
				'num_members' => $row['num_members'],
				'moderators' => array(),
				'icons' => !empty($row['icons'][0]) && !empty($row['icons'][1]) ? str_repeat('<img src="' . Theme::$current->settings['images_url'] . '/membericons/' . $row['icons'][1] . '" alt="*">', $row['icons'][0]) : '',
			);

			Utils::$context['can_moderate'] |= $row['can_moderate'];
			$group_ids[] = $row['id_group'];
		}
		Db::$db->free_result($request);

		// If we found any membergroups, get the amount of members in them.
		if (!empty($group_ids))
		{
			if ($membergroup_type === 'post_count')
			{
				$query = Db::$db->query('', '
					SELECT id_post_group AS id_group, COUNT(*) AS num_members
					FROM {db_prefix}members
					WHERE id_post_group IN ({array_int:group_list})
					GROUP BY id_post_group',
					array(
						'group_list' => $group_ids,
					)
				);
				while ($row = Db::$db->fetch_assoc($query))
					$groups[$row['id_group']]['num_members'] += $row['num_members'];
				Db::$db->free_result($query);
			}

			else
			{
				$query = Db::$db->query('', '
					SELECT id_group, COUNT(*) AS num_members
					FROM {db_prefix}members
					WHERE id_group IN ({array_int:group_list})
					GROUP BY id_group',
					array(
						'group_list' => $group_ids,
					)
				);
				while ($row = Db::$db->fetch_assoc($query))
					$groups[$row['id_group']]['num_members'] += $row['num_members'];
				Db::$db->free_result($query);

				// Only do additional groups if we can moderate...
				if (Utils::$context['can_moderate'])
				{
					$query = Db::$db->query('', '
						SELECT mg.id_group, COUNT(*) AS num_members
						FROM {db_prefix}membergroups AS mg
							INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
								AND mem.id_group != mg.id_group
								AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
						WHERE mg.id_group IN ({array_int:group_list})
						GROUP BY mg.id_group',
						array(
							'group_list' => $group_ids,
							'blank_string' => '',
						)
					);
					while ($row = Db::$db->fetch_assoc($query))
						$groups[$row['id_group']]['num_members'] += $row['num_members'];
					Db::$db->free_result($query);
				}
			}

			$query = Db::$db->query('', '
				SELECT mods.id_group, mods.id_member, mem.member_name, mem.real_name
				FROM {db_prefix}group_moderators AS mods
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				WHERE mods.id_group IN ({array_int:group_list})',
				array(
					'group_list' => $group_ids,
				)
			);
			while ($row = Db::$db->fetch_assoc($query))
				$groups[$row['id_group']]['moderators'][] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			Db::$db->free_result($query);
		}

		// Apply manual sorting if the 'number of members' column is selected.
		if (substr($sort, 0, 1) == '1' || strpos($sort, ', 1') !== false)
		{
			$sort_ascending = strpos($sort, 'DESC') === false;

			foreach ($groups as $group)
				$sort_array[] = $group['id_group'] != 3 ? (int) $group['num_members'] : -1;

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
		$members = array();

		$request = Db::$db->query('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_group = {int:id_group} OR FIND_IN_SET({int:id_group}, additional_groups) != 0' . ($limit === null ? '' : '
			LIMIT ' . ($limit + 1)),
			array(
				'id_group' => $membergroup,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			$members[$row['id_member']] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		}
		Db::$db->free_result($request);

		// If there are more than $limit members, add a 'more' link.
		if ($limit !== null && count($members) > $limit)
		{
			array_pop($members);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Add one or more members to a membergroup
	 *
	 * Requires the manage_membergroups permission.
	 * Function has protection against adding members to implicit groups.
	 * Non-admins are not able to add members to the admin group.
	 *
	 * @todo This is only public and static because SMF\Tasks\GroupAct_Notify
	 * calls it. But GroupAct_Notify should not be doing that. Once that is
	 * fixed, this can be turned into a protected, non-static method.
	 *
	 * @param int|array $members The IDs of one or more members.
	 * @param int $group The group to add the members to.
	 * @param string $type Specifies whether the group is added as primary or as
	 *    an additional group.
	 *
	 *    Supported types:
	 *
	 * 	  only_primary     Assigns a membergroup as primary membergroup, but
	 *                     only if a member has not yet a primary membergroup
	 *                     assigned, unless the member is already part of the
	 *                     membergroup.
	 *
	 * 	  only_additional  Assigns a membergroup to the additional membergroups,
	 *                     unless the member is already part of the membergroup.
	 *
	 * 	  force_primary    Assigns a membergroup as primary no matter what the
	 *                     previous primary membergroup was.
	 *
	 * 	  auto             Assigns a membergroup as primary if primary is still
	 *                     available. If not, assign it to the additional group.
	 *
	 * @param bool $permissionCheckDone Whether we've already checked permissions.
	 * @param bool $ignoreProtected Whether to ignore protected groups.
	 * @return bool Whether the operation was successful.
	 */
	public static function addMembers($members, $group, $type = 'auto', $permissionCheckDone = false, $ignoreProtected = false): bool
	{
		// Show your licence, but only if it hasn't been done yet.
		if (!$permissionCheckDone)
			isAllowedTo('manage_membergroups');

		// Make sure we don't keep old stuff cached.
		Config::updateModSettings(array('settings_updated' => time()));

		// Make sure all members are integer.
		$members = array_unique(array_map('intval', (array) $members));

		$group = (int) $group;

		// Some groups just don't like explicitly having members.
		$implicitGroups = array(-1, 0, 3);
		$group_names = array();
		$request = Db::$db->query('', '
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $group,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if ($row['min_posts'] != -1)
			{
				$implicitGroups[] = $row['id_group'];
			}
			else
			{
				$group_names[$row['id_group']] = $row['group_name'];
			}
		}
		Db::$db->free_result($request);

		// Sorry, you can't join an implicit group.
		if (in_array($group, $implicitGroups) || empty($members))
			return false;

		// Only admins can add admins...
		if (!allowedTo('admin_forum') && $group == 1)
			return false;

		// ... and assign protected groups!
		if (!allowedTo('admin_forum') && !$ignoreProtected)
		{
			$request = Db::$db->query('', '
				SELECT group_type
				FROM {db_prefix}membergroups
				WHERE id_group = {int:current_group}
				LIMIT {int:limit}',
				array(
					'current_group' => $group,
					'limit' => 1,
				)
			);
			list ($is_protected) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			// Is it protected?
			if ($is_protected == 1)
				return false;
		}

		// Do the actual updates.
		if ($type == 'only_additional')
		{
			Db::$db->query('', '
				UPDATE {db_prefix}members
				SET additional_groups = CASE WHEN additional_groups = {string:blank_string} THEN {string:id_group_string} ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
				WHERE id_member IN ({array_int:member_list})
					AND id_group != {int:id_group}
					AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
				array(
					'member_list' => $members,
					'id_group' => $group,
					'id_group_string' => (string) $group,
					'id_group_string_extend' => ',' . $group,
					'blank_string' => '',
				)
			);
		}
		elseif ($type == 'only_primary' || $type == 'force_primary')
		{
			Db::$db->query('', '
				UPDATE {db_prefix}members
				SET id_group = {int:id_group}
				WHERE id_member IN ({array_int:member_list})' . ($type == 'force_primary' ? '' : '
					AND id_group = {int:regular_group}
					AND FIND_IN_SET({int:id_group}, additional_groups) = 0'),
				array(
					'member_list' => $members,
					'id_group' => $group,
					'regular_group' => 0,
				)
			);
		}
		elseif ($type == 'auto')
		{
			Db::$db->query('', '
				UPDATE {db_prefix}members
				SET
					id_group = CASE WHEN id_group = {int:regular_group} THEN {int:id_group} ELSE id_group END,
					additional_groups = CASE WHEN id_group = {int:id_group} THEN additional_groups
						WHEN additional_groups = {string:blank_string} THEN {string:id_group_string}
						ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
				WHERE id_member IN ({array_int:member_list})
					AND id_group != {int:id_group}
					AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
				array(
					'member_list' => $members,
					'regular_group' => 0,
					'id_group' => $group,
					'blank_string' => '',
					'id_group_string' => (string) $group,
					'id_group_string_extend' => ',' . $group,
				)
			);
		}
		// Ack!!?  What happened?
		else
		{
			Lang::load('Errors');
			trigger_error(sprintf(Lang::$txt['add_members_to_group_invalid_type'], $type), E_USER_WARNING);
		}

		call_integration_hook('integrate_add_members_to_group', array($members, $group, &$group_names));

		// Update their postgroup statistics.
		updateStats('postgroups', $members);

		// Log the data.
		require_once(Config::$sourcedir . '/Logging.php');

		foreach ($members as $member)
		{
			logAction(
				'added_to_group',
				array(
					'group' => $group_names[$group],
					'member' => $member
				),
				'admin'
			);
		}

		return true;
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
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}log_group_requests AS lgr
			WHERE ' . $where,
			array_merge($where_parameters, array(
			))
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
		$group_requests = array();

		$request = Db::$db->query('', '
			SELECT
				lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, lgr.reason,
				lgr.status, lgr.id_member_acted, lgr.member_name_acted, lgr.time_acted, lgr.act_reason,
				mem.member_name, mg.group_name, mg.online_color, mem.real_name
			FROM {db_prefix}log_group_requests AS lgr
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
			WHERE ' . $where . '
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			array_merge($where_parameters, array(
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			))
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (empty($row['reason']))
			{
				$reason = '<em>(' . Lang::$txt['mc_groupr_no_reason'] . ')</em>';
			}
			else
			{
				$reason = Lang::censorText($row['reason']);
			}

			if (isset($_GET['closed']))
			{
				if ($row['status'] == 1)
				{
					$reason .= '<br><br><strong>' . Lang::$txt['mc_groupr_approved'] . '</strong>';
				}
				elseif ($row['status'] == 2)
				{
					$reason .= '<br><br><strong>' . Lang::$txt['mc_groupr_rejected'] . '</strong>';
				}

				$reason .= ' (' . timeformat($row['time_acted']) . ')';

				if (!empty($row['act_reason']))
					$reason .= '<br><br>' . Lang::censorText($row['act_reason']);
			}

			$group_requests[] = array(
				'id' => $row['id_request'],
				'member_link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'group_link' => '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>',
				'reason' => $reason,
				'time_submitted' => timeformat($row['time_applied']),
			);
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
		call_integration_hook('integrate_manage_groups', array(&self::$subactions));

		if (!empty($_GET['sa']) && isset(self::$subactions[$_GET['sa']]))
			$this->subaction = $_GET['sa'];
	}

	/**
	 * Remove one or more members from one or more membergroups.
	 * Requires the manage_membergroups permission.
	 * Function includes a protection against removing from implicit groups.
	 * Non-admins are not able to remove members from the admin group.
	 *
	 * @param int|array $members The ID of a member or an array of member IDs
	 * @param null|array The groups to remove the member(s) from. If null, the specified members are stripped from all their membergroups.
	 * @param bool $permissionCheckDone Whether we've already checked permissions prior to calling this function
	 * @param bool $ignoreProtected Whether to ignore protected groups
	 * @return bool Whether the operation was successful
	 */
	protected function removeMembers($members, $groups = null, $permissionCheckDone = false, $ignoreProtected = false): bool
	{
		// You're getting nowhere without this permission, unless of course you are the group's moderator.
		if (!$permissionCheckDone)
			isAllowedTo('manage_membergroups');

		// Assume something will happen.
		Config::updateModSettings(array('settings_updated' => time()));

		// Cleaning the input.
		if (!is_array($members))
			$members = array((int) $members);
		else
		{
			$members = array_unique($members);

			// Cast the members to integer.
			foreach ($members as $key => $value)
				$members[$key] = (int) $value;
		}

		// Before we get started, let's check we won't leave the admin group empty!
		if ($groups === null || $groups == 1 || (is_array($groups) && in_array(1, $groups)))
		{
			$admins = array();
			self::listMembergroupMembers_Href($admins, 1);

			// Remove any admins if there are too many.
			$non_changing_admins = array_diff(array_keys($admins), $members);

			if (empty($non_changing_admins))
				$members = array_diff($members, array_keys($admins));
		}

		// Just in case.
		if (empty($members))
			return false;
		elseif ($groups === null)
		{
			// Wanna remove all groups from these members? That's easy.
			Db::$db->query('', '
				UPDATE {db_prefix}members
				SET
					id_group = {int:regular_member},
					additional_groups = {string:blank_string}
				WHERE id_member IN ({array_int:member_list})' . (allowedTo('admin_forum') ? '' : '
					AND id_group != {int:admin_group}
					AND FIND_IN_SET({int:admin_group}, additional_groups) = 0'),
				array(
					'member_list' => $members,
					'regular_member' => 0,
					'admin_group' => 1,
					'blank_string' => '',
				)
			);

			updateStats('postgroups', $members);

			// Log what just happened.
			foreach ($members as $member)
				logAction('removed_all_groups', array('member' => $member), 'admin');

			return true;
		}
		elseif (!is_array($groups))
			$groups = array((int) $groups);
		else
		{
			$groups = array_unique($groups);

			// Make sure all groups are integer.
			foreach ($groups as $key => $value)
				$groups[$key] = (int) $value;
		}

		// Fetch a list of groups members cannot be assigned to explicitly, and the group names of the ones we want.
		$implicitGroups = array(-1, 0, 3);
		$request = Db::$db->query('', '
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);
		$group_names = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			if ($row['min_posts'] != -1)
				$implicitGroups[] = $row['id_group'];
			else
				$group_names[$row['id_group']] = $row['group_name'];
		}
		Db::$db->free_result($request);

		// Now get rid of those groups.
		$groups = array_diff($groups, $implicitGroups);

		// Don't forget the protected groups.
		if (!allowedTo('admin_forum') && !$ignoreProtected)
		{
			$request = Db::$db->query('', '
				SELECT id_group
				FROM {db_prefix}membergroups
				WHERE group_type = {int:is_protected}',
				array(
					'is_protected' => 1,
				)
			);
			$protected_groups = array(1);
			while ($row = Db::$db->fetch_assoc($request))
				$protected_groups[] = $row['id_group'];
			Db::$db->free_result($request);

			// If you're not an admin yourself, you can't touch protected groups!
			$groups = array_diff($groups, array_unique($protected_groups));
		}

		// Only continue if there are still groups and members left.
		if (empty($groups) || empty($members))
			return false;

		// First, reset those who have this as their primary group - this is the easy one.
		$log_inserts = array();
		$request = Db::$db->query('', '
			SELECT id_member, id_group
			FROM {db_prefix}members AS members
			WHERE id_group IN ({array_int:group_list})
				AND id_member IN ({array_int:member_list})',
			array(
				'group_list' => $groups,
				'member_list' => $members,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$log_inserts[] = array('group' => $group_names[$row['id_group']], 'member' => $row['id_member']);
		Db::$db->free_result($request);

		Db::$db->query('', '
			UPDATE {db_prefix}members
			SET id_group = {int:regular_member}
			WHERE id_group IN ({array_int:group_list})
				AND id_member IN ({array_int:member_list})',
			array(
				'group_list' => $groups,
				'member_list' => $members,
				'regular_member' => 0,
			)
		);

		// Those who have it as part of their additional group must be updated the long way... sadly.
		$request = Db::$db->query('', '
			SELECT id_member, additional_groups
			FROM {db_prefix}members
			WHERE (FIND_IN_SET({raw:additional_groups_implode}, additional_groups) != 0)
				AND id_member IN ({array_int:member_list})
			LIMIT {int:limit}',
			array(
				'member_list' => $members,
				'additional_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
				'limit' => count($members),
			)
		);
		$updates = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			// What log entries must we make for this one, eh?
			foreach (explode(',', $row['additional_groups']) as $group)
				if (in_array($group, $groups))
					$log_inserts[] = array('group' => $group_names[$group], 'member' => $row['id_member']);

			$updates[$row['additional_groups']][] = $row['id_member'];
		}
		Db::$db->free_result($request);

		foreach ($updates as $additional_groups => $memberArray)
			Db::$db->query('', '
				UPDATE {db_prefix}members
				SET additional_groups = {string:additional_groups}
				WHERE id_member IN ({array_int:member_list})',
				array(
					'member_list' => $memberArray,
					'additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups)),
				)
			);

		// Their post groups may have changed now...
		updateStats('postgroups', $members);

		// Do the log.
		if (!empty($log_inserts) && !empty(Config::$modSettings['modlog_enabled']))
		{
			require_once(Config::$sourcedir . '/Logging.php');
			foreach ($log_inserts as $extra)
				logAction('removed_from_group', $extra, 'admin');
		}

		// Mission successful.
		return true;
	}

}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Groups::exportStatic'))
	Groups::exportStatic();

?>