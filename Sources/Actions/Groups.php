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

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionRouter;
use SMF\ActionTrait;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Parser;
use SMF\Routable;
use SMF\SecurityToken;
use SMF\Slug;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Shows group info.
 */
class Groups implements ActionInterface, Routable
{
	use ActionRouter;
	use ActionTrait;
	use BackwardCompatibility;

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
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * The action and area URL query to use in links to sub-actions.
	 */
	protected string $action_url = '?action=groups';

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
		User::$me->isAllowedTo('view_mlist');

		// Get the template stuff up and running.
		Lang::load('ManageMembers');
		Lang::load('ModerationCenter');
		Theme::loadTemplate('ManageMembergroups');

		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . $this->action_url,
			'name' => Lang::$txt['groups'],
		];

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
			'base_href' => Config::$scripturl . $this->action_url,
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

								$group_name = sprintf('<a href="%1$s' . $this->action_url . ';sa=members;group=%2$d"%3$s>%4$s</a>', Config::$scripturl, $rowData['id_group'], $color_style, $rowData['group_name']);
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
	 * Display members of a group.
	 *
	 * It shows a list of members that are part of a given membergroup.
	 * It is called by ?action=groups;sa=members;group=x
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
		$groups = Group::load($_REQUEST['group']);

		/** @var \SMF\Group $group */
		if ($groups == [] || !($group = $groups[$_REQUEST['group']]) || empty($group->id)) {
			ErrorHandler::fatalLang('membergroup_does_not_exist', false);
		}

		Utils::$context['group'] = $group;

		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . $this->action_url . ';sa=members;group=' . $group->id,
			'name' => $group->name,
		];
		Utils::$context['can_send_email'] = User::$me->allowedTo('moderate_forum');

		// Can't add or remove members via ?action=groups.
		Utils::$context['can_add_remove'] = false;

		// Load all the group moderators, for fun.
		$group->loadModerators();

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
	}

	/***********************
	 * Public static methods
	 ***********************/

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
		Group::loadModeratorsBatch(array_map(fn($group) => $group->id, $temp));

		foreach ($temp as $group) {
			// We only list the groups they can see.
			if ($group->hidden === Group::INVISIBLE && !$group->can_moderate) {
				continue;
			}

			Utils::$context['can_moderate'] |= $group->can_moderate;

			$group->description = Parser::transform(
				string: $group->description,
				input_types: Parser::INPUT_BBC | Parser::INPUT_MARKDOWN,
				options: ['parse_tags' => Utils::$context['description_allowed_tags']],
			);

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
		if (str_starts_with($sort, '1') || str_contains($sort, ', 1')) {
			$sort_ascending = !str_contains($sort, 'DESC');

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
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array $params URL query parameters.
	 * @return array Contains two elements: ['route' => [], 'params' => []].
	 *    The 'route' element contains the routing path. The 'params' element
	 *    contains any $params that weren't incorporated into the route.
	 */
	public static function buildRoute(array $params): array
	{
		$route[] = $params['action'];
		unset($params['action']);

		if (($params['sa'] ?? '') === 'members' && isset($params['group'])) {
			if (isset(Slug::$known['group'][(int) $params['group']])) {
				$slug = (string) Slug::$known['group'][(int) $params['group']];
			} elseif (($slug = Slug::getCached('group', (int) $params['group'])) === '') {
				$group = current(Group::load((int) $params['group']));

				if ($group instanceof Group) {
					$slug = (string) new Slug($group->name, 'group', $group->id);
				} else {
					$slug = '';
				}
			}

			$route[] = $slug . (str_ends_with($slug, '-' . $params['group']) ? '' : ($slug !== '' ? '-' : '') . $params['group']);

			unset($params['sa'], $params['group']);
		}

		return ['route' => $route, 'params' => $params];
	}

	/**
	 * Parses a route to get URL query parameters.
	 *
	 * @param array $route Array of routing path components.
	 * @param array $params Any existing URL query parameters.
	 * @return array URL query parameters
	 */
	public static function parseRoute(array $route, array $params = []): array
	{
		$params['action'] = array_shift($route);

		if (!empty($route)) {
			$params['sa'] = 'members';

			preg_match('/^(\X*?)(\d+)$/u', array_shift($route), $matches);

			$params['group'] = $matches[2];

			Slug::setRequested(rtrim($matches[1], '-'), 'group', (int) $params['group']);
		}

		return $params;
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