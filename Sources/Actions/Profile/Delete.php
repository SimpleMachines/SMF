<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Sources\Actions\Profile;

use SMF\Sources\ActionInterface;
use SMF\Sources\Actions\Logout;
use SMF\Sources\ActionTrait;
use SMF\Sources\Config;
use SMF\Sources\Db\DatabaseApi as Db;
use SMF\Sources\ErrorHandler;
use SMF\Sources\Lang;
use SMF\Sources\Msg;
use SMF\Sources\Profile;
use SMF\Sources\Sapi;
use SMF\Sources\Topic;
use SMF\Sources\User;
use SMF\Sources\Utils;

/**
 * Handles deleting an account.
 */
class Delete implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever method is necessary.
	 */
	public function execute(): void
	{
		if (!empty(Utils::$context['completed_save'])) {
			$this->delete();
		} else {
			$this->show();
		}
	}

	/**
	 * Presents the UI to make sure the user wants to be deleted.
	 */
	public function show(): void
	{
		if (!User::$me->is_owner) {
			User::$me->isAllowedTo('profile_remove_any');
		} elseif (!User::$me->allowedTo('profile_remove_any')) {
			User::$me->isAllowedTo('profile_remove_own');
		}

		// Permissions for removing stuff...
		Utils::$context['can_delete_posts'] = !User::$me->is_owner && User::$me->allowedTo('moderate_forum');

		// Show an extra option if recycling is enabled...
		Utils::$context['show_perma_delete'] = !empty(Config::$modSettings['recycle_enable']) && !empty(Config::$modSettings['recycle_board']);

		// Can they do this, or will they need approval?
		Utils::$context['needs_approval'] = User::$me->is_owner && !empty(Config::$modSettings['approveAccountDeletion']) && !User::$me->allowedTo('moderate_forum');

		Utils::$context['page_title'] = Lang::getTxt('deleteAccount', ['name' => Profile::$member->name]);
	}

	/**
	 * Actually deletes the account.
	 */
	public function delete(): void
	{
		if (!User::$me->is_owner) {
			User::$me->isAllowedTo('profile_remove_any');
		} elseif (!User::$me->allowedTo('profile_remove_any')) {
			User::$me->isAllowedTo('profile_remove_own');
		}

		// Try get more time...
		@set_time_limit(600);

		User::$me->checkSession();

		// Too often, people remove/delete their own only account.
		if (in_array(1, Profile::$member->groups)) {
			// Are you allowed to administrate the forum, as they are?
			User::$me->isAllowedTo('admin_forum');

			$request = Db::$db->query(
				'',
				'SELECT id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND id_member != {int:selected_member}
				LIMIT 1',
				[
					'admin_group' => 1,
					'selected_member' => Profile::$member->id,
				],
			);
			list($another) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			if (empty($another)) {
				ErrorHandler::fatalLang('at_least_one_admin', 'critical');
			}
		}

		// Deleting someone else's account.
		if (!User::$me->is_owner) {
			// Now, have you been naughty and need your posts deleting?
			// @todo Should this check board permissions?
			if (!empty($_POST['deleteVotes']) && User::$me->allowedTo('moderate_forum')) {
				// First we find any polls that this user has voted in...
				$polls_to_update = [];
				$get_voted_polls = Db::$db->query(
					'',
					'SELECT DISTINCT id_poll
					FROM {db_prefix}log_polls
					WHERE id_member = {int:selected_member}',
					[
						'selected_member' => Profile::$member->id,
					],
				);

				while ($row = Db::$db->fetch_assoc($get_voted_polls)) {
					$polls_to_update[] = $row['id_poll'];
				}
				Db::$db->free_result($get_voted_polls);

				// Now we delete the votes and update the polls
				if (!empty($polls_to_update)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}log_polls
						WHERE id_member = {int:selected_member}',
						[
							'selected_member' => Profile::$member->id,
						],
					);

					Db::$db->query(
						'',
						'UPDATE {db_prefix}polls
						SET votes = votes - 1
						WHERE id_poll IN ({array_int:polls_to_update})',
						[
							'polls_to_update' => $polls_to_update,
						],
					);
				}

				// Next, delete the posts, if requested.
				if (in_array($_POST['remove_type'], ['posts', 'topics'])) {
					$extra = empty($_POST['perma_delete']) ? ' AND t.id_board != {int:recycle_board}' : '';

					$recycle_board = empty(Config::$modSettings['recycle_board']) ? 0 : Config::$modSettings['recycle_board'];

					// First off we delete any topics the member has started, if requested.
					if ($_POST['remove_type'] == 'topics') {
						// Fetch all topics started by this user.
						$request = Db::$db->query(
							'',
							'SELECT t.id_topic
							FROM {db_prefix}topics AS t
							WHERE t.id_member_started = {int:selected_member}' . $extra,
							[
								'selected_member' => Profile::$member->id,
								'recycle_board' => $recycle_board,
							],
						);
						$topic_ids = Db::$db->fetch_all($request);
						Db::$db->free_result($request);

						// Actually remove the topics.
						// Ignore recycling if we want to perma-delete things...
						if (!empty($topic_ids)) {
							Topic::remove($topic_ids, true, !empty($extra));
						}
					}

					// Now delete the remaining messages.
					$request = Db::$db->query(
						'',
						'SELECT m.id_msg
						FROM {db_prefix}messages AS m
							INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic
								AND t.id_first_msg != m.id_msg)
						WHERE m.id_member = {int:selected_member}' . $extra,
						[
							'selected_member' => Profile::$member->id,
							'recycle_board' => $recycle_board,
						],
					);

					// This could take a while... but ya know it's gonna be worth it in the end.
					while ($row = Db::$db->fetch_assoc($request)) {
						Sapi::resetTimeout();

						Msg::remove($row['id_msg']);
					}
					Db::$db->free_result($request);
				}
			}

			// Only delete this poor member's account if they are actually being booted out of camp.
			if (isset($_POST['deleteAccount'])) {
				User::delete(Profile::$member->id);
			}
		}
		// Deleting their own account, but they need approval to delete.
		elseif (!empty(Config::$modSettings['approveAccountDeletion']) && !User::$me->allowedTo('moderate_forum')) {
			// Setup their account for deletion.
			User::updateMemberData(Profile::$member->id, ['is_activated' => User::REQUESTED_DELETE]);

			// Another account needs approval...
			Config::updateModSettings(['unapprovedMembers' => true], true);
		}
		// Deleting their own account, and they don't need approval.
		else {
			User::delete(Profile::$member->id);

			Logout::call(true);

			Utils::redirectexit();
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Backward compatibility wrapper for the delete method.
	 *
	 * @param int $memID The ID of the user.
	 */
	public static function deleteAccount2(int $memID): void
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
}

?>