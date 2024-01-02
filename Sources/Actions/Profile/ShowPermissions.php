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
use SMF\Actions\Admin\Permissions;
use SMF\BackwardCompatibility;
use SMF\Board;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\Profile;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class ShowPermissions implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'showPermissions' => 'showPermissions',
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
	 * Does the job.
	 */
	public function execute(): void
	{
		// Verify if the user has sufficient permissions.
		User::$me->isAllowedTo('manage_permissions');

		Utils::$context['page_title'] = Lang::$txt['showPermissions'];

		// If they're an admin we know they can do everything, so we might as well leave.
		Profile::$member->formatted['has_all_permissions'] = Profile::$member->is_admin;

		if (Profile::$member->formatted['has_all_permissions']) {
			return;
		}

		// Load all the permission profiles.
		Permissions::loadPermissionProfiles();

		Board::$info->id = empty(Board::$info->id) ? 0 : (int) Board::$info->id;
		Utils::$context['board'] = Board::$info->id;

		// Load a list of boards for the jump box - except the defaults.
		Utils::$context['boards'] = [];
		Utils::$context['no_access_boards'] = [];

		$request = Db::$db->query(
			'order_by_board_order',
			'SELECT b.id_board, b.name, b.id_profile, b.member_groups, COALESCE(mods.id_member, modgs.id_group, 0) AS is_mod
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:current_groups}))
			WHERE {query_see_board}',
			[
				'current_member' => Profile::$member->id,
				'current_groups' => Profile::$member->groups,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!$row['is_mod'] && !Profile::$member->can_manage_boards && count(array_intersect(Profile::$member->groups, explode(',', $row['member_groups']))) === 0) {
				Utils::$context['no_access_boards'][] = [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'is_last' => false,
				];
			} elseif ($row['id_profile'] != 1 || $row['is_mod']) {
				Utils::$context['boards'][$row['id_board']] = [
					'id' => $row['id_board'],
					'name' => $row['name'],
					'selected' => Board::$info->id == $row['id_board'],
					'profile' => $row['id_profile'],
					'profile_name' => Utils::$context['profiles'][$row['id_profile']]['name'],
				];
			}
		}
		Db::$db->free_result($request);

		Board::sort(Utils::$context['boards']);

		if (!empty(Utils::$context['no_access_boards'])) {
			Utils::$context['no_access_boards'][count(Utils::$context['no_access_boards']) - 1]['is_last'] = true;
		}

		Profile::$member->formatted['permissions'] = [
			'general' => [],
			'board' => [],
		];

		// For legibility below.
		$general_perms = &Profile::$member->formatted['permissions']['general'];
		$board_perms = &Profile::$member->formatted['permissions']['board'];

		$denied = [];

		// Get all general permissions.
		$result = Db::$db->query(
			'',
			'SELECT p.permission, p.add_deny, mg.group_name, p.id_group
			FROM {db_prefix}permissions AS p
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
			WHERE p.id_group IN ({array_int:group_list})
			ORDER BY p.add_deny DESC, p.permission, mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
			[
				'group_list' => Profile::$member->groups,
				'newbie_group' => 4,
			],
		);

		while ($row = Db::$db->fetch_assoc($result)) {
			// We don't know about this permission, it doesn't exist :P.
			if (!isset(Lang::$txt['permissionname_' . $row['permission']])) {
				continue;
			}

			if (empty($row['add_deny'])) {
				$denied[] = $row['permission'];
			}

			// Permissions that end with _own or _any consist of two parts.
			if (in_array(substr($row['permission'], -4), ['_own', '_any']) && isset(Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)])) {
				$name = Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . Lang::$txt['permissionname_' . $row['permission']];
			} else {
				$name = Lang::$txt['permissionname_' . $row['permission']];
			}

			// Is this group allowed or denied?
			$denied_allowed = empty($row['add_deny']) ? 'denied' : 'allowed';

			// The name of the group.
			$group_name = $row['id_group'] == 0 ? Lang::$txt['membergroups_members'] : $row['group_name'];

			// Add this permission if it doesn't exist yet.
			if (!isset($general_perms[$row['permission']])) {
				$general_perms[$row['permission']] = [
					'id' => $row['permission'],
					'groups' => [
						'allowed' => [],
						'denied' => [],
					],
					'name' => $name,
					'is_denied' => false,
					'is_global' => true,
				];
			}

			// Add the membergroup to either the denied or the allowed groups.
			$general_perms[$row['permission']]['groups'][$denied_allowed][] = $group_name;

			// Once denied is always denied.
			$general_perms[$row['permission']]['is_denied'] |= empty($row['add_deny']);
		}
		Db::$db->free_result($result);

		$request = Db::$db->query(
			'',
			'SELECT
				bp.add_deny, bp.permission, bp.id_group, mg.group_name' . (empty(Board::$info->id) ? '' : ',
				b.id_profile, CASE WHEN (mods.id_member IS NULL AND modgs.id_group IS NULL) THEN 0 ELSE 1 END AS is_moderator') . '
			FROM {db_prefix}board_permissions AS bp' . (empty(Board::$info->id) ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = {int:current_board})
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
				LEFT JOIN {db_prefix}moderator_groups AS modgs ON (modgs.id_board = b.id_board AND modgs.id_group IN ({array_int:group_list}))') . '
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bp.id_group)
			WHERE bp.id_profile = {raw:current_profile}
				AND bp.id_group IN ({array_int:group_list}' . (empty(Board::$info->id) ? ')' : ', {int:moderator_group})
				AND (mods.id_member IS NOT NULL OR modgs.id_group IS NOT NULL OR bp.id_group != {int:moderator_group})'),
			[
				'current_board' => Board::$info->id,
				'group_list' => Profile::$member->groups,
				'current_member' => Profile::$member->id,
				'current_profile' => empty(Board::$info->id) ? '1' : 'b.id_profile',
				'moderator_group' => 3,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// We don't know about this permission, it doesn't exist :P.
			if (!isset(Lang::$txt['permissionname_' . $row['permission']])) {
				continue;
			}

			// The name of the permission using the format 'permission name' - 'own/any topic/event/etc.'.
			if (
				in_array(substr($row['permission'], -4), ['_own', '_any'])
				&& isset(Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)])
			) {
				$name = Lang::$txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . Lang::$txt['permissionname_' . $row['permission']];
			} else {
				$name = Lang::$txt['permissionname_' . $row['permission']];
			}

			// Is this group allowed or denied?
			$denied_allowed = empty($row['add_deny']) ? 'denied' : 'allowed';

			// The name of the group.
			$group_name = $row['id_group'] == 0 ? Lang::$txt['membergroups_members'] : $row['group_name'];

			// Create the structure for this permission.
			if (!isset($board_perms[$row['permission']])) {
				$board_perms[$row['permission']] = [
					'id' => $row['permission'],
					'groups' => [
						'allowed' => [],
						'denied' => [],
					],
					'name' => $name,
					'is_denied' => false,
					'is_global' => empty(Board::$info->id),
				];
			}

			$board_perms[$row['permission']]['groups'][$denied_allowed][$row['id_group']] = $group_name;

			$board_perms[$row['permission']]['is_denied'] |= empty($row['add_deny']);
		}
		Db::$db->free_result($request);
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
	 * Backward compatibility wrapper.
	 */
	public static function showPermissions(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

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
		Lang::load('ManagePermissions');
		Lang::load('Admin');
		Theme::loadTemplate('ManageMembers');

		if (!isset(Profile::$member)) {
			Profile::load();
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\ShowPermissions::exportStatic')) {
	ShowPermissions::exportStatic();
}

?>