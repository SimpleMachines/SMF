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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\Lang;
use SMF\Profile;
use SMF\User;
use SMF\Utils;

/**
 * Allows a user to choose, or at least request, group memberships.
 */
class GroupMembership implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'groupMembership',
			'groupMembership2' => 'groupMembership2',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The type of change that was made when saving.
	 */
	public string $change_type;

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
	 * Dispatcher to whichever method is necessary.
	 */
	public function execute(): void
	{
		if (!empty(Utils::$context['completed_save'])) {
			$this->save();
		} else {
			$this->show();
		}
	}

	/**
	 * Shows the UI.
	 */
	public function show(): void
	{
		if (!User::$me->allowedTo('manage_membergroups') && !User::$me->is_owner) {
			ErrorHandler::fatalLang('cannot_manage_membergroups', false);
		}

		Utils::$context['primary_group'] = Profile::$member->group_id;
		Utils::$context['update_message'] = Lang::$txt['group_membership_msg_' . ($_GET['msg'] ?? '')] ?? '';

		// Can they manage groups?
		Utils::$context['can_edit_primary'] = $this->canEditPrimary();

		// This beast will be our group holder.
		Utils::$context['groups'] = [
			'member' => [],
			'available' => [],
		];

		// Get all the membergroups they can join.
		$this->loadCurrentAndAssignableGroups();

		// Get any pending join requests.
		$request = Db::$db->query(
			'',
			'SELECT id_group
			FROM {db_prefix}log_group_requests
			WHERE id_member = {int:selected_member}
				AND status = {int:status_open}',
			[
				'selected_member' => Profile::$member->id,
				'status_open' => 0,
			],
		);
		$open_requests = Db::$db->fetch_all($request);
		Db::$db->free_result($request);

		// Show the assignable groups in the templates.
		foreach (Profile::$member->current_and_assignable_groups as $id => $group) {
			// Skip "Regular Members" for now.
			if ($id == 0) {
				continue;
			}

			// Are they in this group?
			$member_or_available = in_array($id, Profile::$member->groups) ? 'member' : 'available';

			// Can't join private or protected groups.
			if ($group->type < Group::TYPE_REQUESTABLE && $member_or_available == 'available') {
				continue;
			}

			Utils::$context['groups'][$member_or_available][$id] = $group;

			// Do they have a pending request to join this group?
			Utils::$context['groups'][$member_or_available][$id]->pending = in_array($id, $open_requests);
		}

		// If needed, add "Regular Members" on the end.
		if (Utils::$context['can_edit_primary'] || Profile::$member->group_id == Group::REGULAR) {
			Utils::$context['groups']['member'][Group::REGULAR] = Profile::$member->assignable_groups[Group::REGULAR];
			Utils::$context['groups']['member'][Group::REGULAR]->name = Lang::$txt['regular_members'];
		}

		// No changing primary group unless you have enough groups!
		if (count(Utils::$context['groups']['member']) < 2) {
			Utils::$context['can_edit_primary'] = false;
		}

		// In the special case that someone is requesting membership of a group, setup some special context vars.
		if (
			isset($_REQUEST['request'], Utils::$context['groups']['available'][(int) $_REQUEST['request']])

			&& Utils::$context['groups']['available'][(int) $_REQUEST['request']]->type == Group::TYPE_REQUESTABLE
		) {
			Utils::$context['group_request'] = Utils::$context['groups']['available'][(int) $_REQUEST['request']];
		}
	}

	/**
	 * Saves the changes.
	 */
	public function save(): void
	{
		if (!isset($_REQUEST['gid']) && !isset($_POST['primary'])) {
			return;
		}

		// Let's be extra cautious...
		if (!User::$me->is_owner || empty(Config::$modSettings['show_group_membership'])) {
			User::$me->isAllowedTo('manage_membergroups');
		}

		User::$me->checkSession(isset($_GET['gid']) ? 'get' : 'post');

		// By default the new groups are the old groups.
		$new_primary = Profile::$member->group_id;
		$new_additional_groups = Profile::$member->additional_groups;

		// What kind of change is this supposed to be?
		$this->change_type = isset($_POST['primary']) ? 'primary' : (isset($_POST['req']) ? 'request' : 'free');

		// One way or another, we have a target group in mind...
		$new_group_id = (int) ($_REQUEST['gid'] ?? $_POST['primary']);

		// Which groups can they be assigned to?
		$this->loadCurrentAndAssignableGroups();

		if (!isset(Profile::$member->current_and_assignable_groups[$new_group_id])) {
			ErrorHandler::fatalLang('cannot_manage_membergroups', false);
		}

		// Just for improved legibility...
		$new_group_info = Profile::$member->current_and_assignable_groups[$new_group_id];

		// Can't request a non-requestable group.
		if ($this->change_type == 'request' && $new_group_info['type'] != 2) {
			ErrorHandler::fatalLang('no_access', false);
		}

		if ($this->change_type == 'free') {
			// Can't freely join or leave private or protected groups.
			if ($new_group_info['type'] <= 1) {
				ErrorHandler::fatalLang('no_access', false);
			}

			// Can't leave a requestable group that you're not part of.
			if ($new_group_info['type'] == 2 && !in_array($new_group_id, Profile::$member->groups)) {
				ErrorHandler::fatalLang('no_access', false);
			}
		}

		// Whatever we are doing, we need to determine if changing primary is possible.
		$can_edit_primary = $this->canEditPrimary($new_group_id);

		switch ($this->change_type) {
			// If they're requesting, add the note then return.
			case 'request':
				$this->sendJoinRequest($new_group_id);

				return;

			// Leaving/joining a group.
			case 'free':
				// Are they leaving?
				if (Profile::$member->group_id == $new_group_id) {
					$new_primary = $can_edit_primary ? 0 : Profile::$member->group_id;
				} elseif (in_array($new_group_id, Profile::$member->additional_groups)) {
					$new_additional_groups = array_diff($new_additional_groups, [$new_group_id]);
				}
				// ... if not, must be joining.
				else {
					// If they're just a regular member and this can be a primary group,
					// then make it the primary.
					if (Profile::$member->group_id == 0 && $can_edit_primary && !empty($new_group_info['can_be_primary'])) {
						$new_primary = $new_group_id;
					}
					// Otherwise, make it an addtional group.
					else {
						$new_additional_groups[] = $new_group_id;
					}
				}

				break;

			// Finally, we must be setting the primary.
			default:
				if (Profile::$member->group_id != 0) {
					$new_additional_groups[] = Profile::$member->group_id;
				}

				if (in_array($new_group_id, $new_additional_groups)) {
					$new_additional_groups = array_diff($new_additional_groups, [$new_group_id]);
				}

				$new_primary = $new_group_id;

				break;
		}

		// Run the changes through the validation method for group membership.
		Profile::$member->validateGroups($new_primary, $new_additional_groups ?? []);
		Profile::$member->new_data['id_group'] = $new_primary;
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
	 * Backward compatibility wrapper for the save method.
	 *
	 * @param int $memID The ID of the user.
	 * @return string The type of change that was made.
	 */
	public static function groupMembership2(int $memID): string
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$saving = Utils::$context['completed_save'];
		Utils::$context['completed_save'] = true;

		$_REQUEST['u'] = $u;

		self::$obj->execute();

		Utils::$context['completed_save'] = $saving;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}

	/**
	 *
	 */
	protected function loadCurrentAndAssignableGroups(): void
	{
		if (isset(Profile::$member->current_and_assignable_groups)) {
			return;
		}

		if (empty(Profile::$member->assignable_groups)) {
			Profile::$member->loadAssignableGroups();
		}

		$current_and_assignable_groups = Profile::$member->assignable_groups;

		// If they are already in an unassignable group, show that too.
		$current_unassignable_groups = array_intersect(Profile::$member->groups, Group::getUnassignable());

		if ($current_unassignable_groups !== []) {
			foreach (Group::load($current_unassignable_groups) as $group) {
				if ($group->min_posts > -1) {
					continue;
				}

				$group->is_primary = $group->id == Profile::$member->group_id;
				$group->is_additional = in_array($group->id, Profile::$member->additional_groups);

				$current_and_assignable_groups[$group->id] = $group;
			}
		}

		uasort(
			$current_and_assignable_groups,
			function ($a, $b) {
				if ($a['id'] >= 4 && $b['id'] >= 4) {
					return $a['name'] <=> $b['name'];
				}

				return $a['id'] <=> $b['id'];
			},
		);

		Profile::$member->current_and_assignable_groups = $current_and_assignable_groups;
	}

	/**
	 * Figures out whether the current user can change the primary membergroup
	 * of the member whose profile is being viewed.
	 */
	protected function canEditPrimary(?int $new_group_id = null): bool
	{
		$this->loadCurrentAndAssignableGroups();

		// Hidden groups cannot be primary groups.
		if (isset($new_group_id)) {
			$can_edit_primary = Profile::$member->current_and_assignable_groups[$new_group_id]->can_be_primary;
		} else {
			$possible_primary_groups = array_filter(Profile::$member->current_and_assignable_groups, fn ($group) => !empty($group->can_be_primary));

			$can_edit_primary = !empty($possible_primary_groups);
		}

		// Changing the primary group means turning the current primary group
		// into an additional group. So, is that possible?
		$can_edit_primary &= Profile::$member->group_id == Group::REGULAR || Profile::$member->current_and_assignable_groups[Profile::$member->group_id]->can_be_additional;

		return (bool) $can_edit_primary;
	}

	/**
	 *
	 */
	protected function sendJoinRequest(int $new_group_id): void
	{
		$request = Db::$db->query(
			'',
			'SELECT id_member
			FROM {db_prefix}log_group_requests
			WHERE id_member = {int:selected_member}
				AND id_group = {int:selected_group}
				AND status = {int:status_open}',
			[
				'selected_member' => Profile::$member->id,
				'selected_group' => $new_group_id,
				'status_open' => 0,
			],
		);
		$already_requested = Db::$db->num_rows($request) != 0;
		Db::$db->free_result($request);

		if ($already_requested) {
			ErrorHandler::fatalLang('profile_error_already_requested_group');
		}

		// Log the request.
		Db::$db->insert(
			'',
			'{db_prefix}log_group_requests',
			[
				'id_member' => 'int',
				'id_group' => 'int',
				'time_applied' => 'int',
				'reason' => 'string-65534',
				'status' => 'int',
				'id_member_acted' => 'int',
				'member_name_acted' => 'string',
				'time_acted' => 'int',
				'act_reason' => 'string',
			],
			[
				Profile::$member->id,
				$new_group_id,
				time(),
				$_POST['reason'],
				0,
				0,
				'',
				0,
				'',
			],
			['id_request'],
		);

		// Set up some data for our background task...
		$data = Utils::jsonEncode([
			'id_member' => Profile::$member->id,
			'member_name' => User::$me->name,
			'id_group' => $new_group_id,
			'group_name' => Profile::$member->assignable_groups[$new_group_id]['name'],
			'reason' => $_POST['reason'],
			'time' => time(),
		]);

		// Add a background task to handle notifying people of this request
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string-255',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				'SMF\\Tasks\\GroupReq_Notify',
				$data,
				0,
			],
			[],
		);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\GroupMembership::exportStatic')) {
	GroupMembership::exportStatic();
}

?>