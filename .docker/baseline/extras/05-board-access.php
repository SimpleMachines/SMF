<?php

/**
 * Makes the boards Populate.php created visible to ordinary members.
 *
 * createBoard() inserts a board with an empty member_groups and then relies on
 * inherit_permissions to copy its parent's access -- which for a top-level
 * board means copying nothing. Populate never sets access_groups either, so
 * every board it makes ends up visible to administrators only. Browse the
 * result as a guest and the forum looks like a fresh install: one board, no
 * content, a Recent Posts list full of things you cannot open.
 *
 * That matters beyond appearances. Board permissions are what the upgrade's
 * permission work runs against, and a forum where nothing is readable is not a
 * realistic starting point for anything.
 *
 * So: give most boards the same audience the default board has, and leave two
 * deliberately restricted, because a baseline where every board is identical
 * would not notice a migration that flattened them.
 *
 * Exercises: Permissions, BoardPermissionsView, v3_0\PermissionChanges.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

$baseline_name = '05-board-access';

if (baseline_applied($baseline_name) && empty($baseline_force))
{
	baseline_say($baseline_name . ': skipped');
}
else
{
	global $smcFunc, $sourcedir;

	// Going through modifyBoard() rather than updating boards.member_groups
	// directly is what keeps board_permissions_view -- 2.1's denormalised copy
	// of the same information -- in step. Writing the column by hand would
	// leave the two disagreeing, which is not a state a real forum is ever in.
	require_once $sourcedir . '/Subs-Boards.php';

	// -1 guests, 0 regular members, 2 global moderators: the audience a stock
	// 2.1 install gives its one board.
	$public_groups = array(-1, 0, 2);

	// Members only. A baseline where every board has identical permissions
	// would not notice a migration that flattened them.
	$members_only = array(0, 2);

	// Not named $boards: getBoardTree(), which modifyBoard() calls, writes to a
	// global of that name and would be rewriting this list underneath us.
	$board_ids = array();
	$board_cats = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_board, id_cat
		FROM {db_prefix}boards
		ORDER BY id_board',
		array()
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$board_ids[] = (int) $row['id_board'];
		$board_cats[(int) $row['id_board']] = (int) $row['id_cat'];
	}

	$smcFunc['db_free_result']($request);

	$restricted = array_slice($board_ids, -2);
	$public = array_values(array_diff($board_ids, $restricted));

	foreach ($board_ids as $id_board)
	{
		// old_id_cat is passed because modifyBoard() reads it unconditionally
		// near the end, even when nothing is being moved. Leaving it out works,
		// but fills the log with an undefined-index warning per board.
		$board_options = array(
			'access_groups' => in_array($id_board, $restricted) ? $members_only : $public_groups,
			'deny_groups' => array(),
			'old_id_cat' => $board_cats[$id_board],
			'dont_log' => true,
		);

		modifyBoard($id_board, $board_options);
	}

	// Cached board data would otherwise keep serving the old audience.
	clean_cache('data');

	baseline_say(sprintf(
		'%s: %d board(s) opened up, %d left members-only',
		$baseline_name,
		count($public),
		count($restricted)
	));

	baseline_mark_applied($baseline_name);
}
