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

namespace SMF\Sources\Actions;

use SMF\Sources\ActionInterface;
use SMF\Sources\ActionTrait;
use SMF\Sources\Board;
use SMF\Sources\Config;
use SMF\Sources\Db\DatabaseApi as Db;
use SMF\Sources\ErrorHandler;
use SMF\Sources\Logging;
use SMF\Sources\Mail;
use SMF\Sources\Topic;
use SMF\Sources\User;
use SMF\Sources\Utils;

/**
 * This action handles the deletion of topics.
 */
class TopicRemove implements ActionInterface
{
	use ActionTrait;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Completely remove an entire topic.
	 * Redirects to the board when completed.
	 */
	public function execute(): void
	{
		// Make sure they aren't being lead around by someone. (:@)
		User::$me->checkSession('get');

		// Trying to fool us around, are we?
		if (empty(Topic::$topic_id)) {
			Utils::redirectexit();
		}

		self::removeDeleteConcurrence();

		$request = Db::$db->query(
			'',
			'SELECT t.id_member_started, ms.subject, t.approved, t.locked
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
			],
		);
		list($starter, $subject, $approved, $locked) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if ($starter == User::$me->id && !User::$me->allowedTo('remove_any')) {
			User::$me->isAllowedTo('remove_own');
		} else {
			User::$me->isAllowedTo('remove_any');
		}

		// Can they see the topic?
		if (Config::$modSettings['postmod_active'] && !$approved && $starter != User::$me->id) {
			User::$me->isAllowedTo('approve_posts');
		}

		// Ok, we got that far, but is it locked?
		if ($locked) {
			if (!($locked == 1 && $starter == User::$me->id || User::$me->allowedTo('lock_any'))) {
				ErrorHandler::fatalLang('cannot_remove_locked', 'user');
			}
		}

		// Notify people that this topic has been removed.
		Mail::sendNotifications(Topic::$topic_id, 'remove');

		Topic::remove(Topic::$topic_id);

		// Note, only log topic ID in native form if it's not gone forever.
		if (User::$me->allowedTo('remove_any') || (User::$me->allowedTo('remove_own') && $starter == User::$me->id)) {
			Logging::logAction('remove', [
				(empty(Config::$modSettings['recycle_enable']) || Config::$modSettings['recycle_board'] != Board::$info->id ? 'topic' : 'old_topic_id') => Topic::$topic_id,
				'subject' => $subject,
				'member' => $starter,
				'board' => Board::$info->id,
			]);
		}

		Utils::redirectexit('board=' . Board::$info->id . '.0');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Try to determine if the topic has already been deleted by another user.
	 *
	 * @return bool False if it can't be deleted (recycling not enabled or no recycling board set), true if we've confirmed it can be deleted. Dies with an error if it's already been deleted.
	 */
	public static function removeDeleteConcurrence(): bool
	{
		// No recycle no need to go further
		if (empty(Config::$modSettings['recycle_enable']) || empty(Config::$modSettings['recycle_board'])) {
			return false;
		}

		// If it's confirmed go on and delete (from recycle)
		if (isset($_GET['confirm_delete'])) {
			return true;
		}

		if (empty(Board::$info->id)) {
			return false;
		}

		if (Config::$modSettings['recycle_board'] != Board::$info->id) {
			return true;
		}

		if (isset($_REQUEST['msg'])) {
			$confirm_url = Config::$scripturl . '?action=deletemsg;confirm_delete;topic=' . Utils::$context['current_topic'] . '.0;msg=' . $_REQUEST['msg'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		} else {
			$confirm_url = Config::$scripturl . '?action=removetopic2;confirm_delete;topic=' . Utils::$context['current_topic'] . '.0;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		}

		ErrorHandler::fatalLang('post_already_deleted', false, [$confirm_url]);

		return false;
	}

	/**
	 * So long as you are sure... all old posts will be gone.
	 *
	 * Used by SMF\Sources\Actions\Admin\Maintenance to prune old topics.
	 */
	public static function old(): void
	{
		User::$me->isAllowedTo('admin_forum');
		User::$me->checkSession('post', 'admin');

		// No boards at all?  Forget it then :/.
		if (empty($_POST['boards'])) {
			Utils::redirectexit('action=admin;area=maintain;sa=topics');
		}

		// This should exist, but we can make sure.
		$_POST['delete_type'] = $_POST['delete_type'] ?? 'nothing';

		// Custom conditions.
		$condition = '';
		$condition_params = [
			'boards' => array_keys($_POST['boards']),
			'poster_time' => time() - 3600 * 24 * $_POST['maxdays'],
		];

		// Just moved notice topics?
		// Note that this ignores redirection topics unless it's a non-expiring one
		if ($_POST['delete_type'] == 'moved') {
			$condition .= '
				AND m.icon = {string:icon}
				AND t.locked = {int:locked}
				AND t.redirect_expires = {int:not_expiring}';
			$condition_params['icon'] = 'moved';
			$condition_params['locked'] = 1;
			$condition_params['not_expiring'] = 0;
		}
		// Otherwise, maybe locked topics only?
		elseif ($_POST['delete_type'] == 'locked') {
			// Exclude moved/merged notices since we have another option for those...
			$condition .= '
				AND t.icon != {string:icon}
				AND t.locked = {int:locked}';
			$condition_params['icon'] = 'moved';
			$condition_params['locked'] = 1;
		}

		// Exclude stickies?
		if (isset($_POST['delete_old_not_sticky'])) {
			$condition .= '
				AND t.is_sticky = {int:is_sticky}';
			$condition_params['is_sticky'] = 0;
		}

		// All we're gonna do here is grab the id_topic's and send them to Topic::remove().
		$request = Db::$db->query(
			'',
			'SELECT t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
			WHERE
				m.poster_time < {int:poster_time}' . $condition . '
				AND t.id_board IN ({array_int:boards})',
			$condition_params,
		);
		$topics = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$topics[] = $row['id_topic'];
		}
		Db::$db->free_result($request);

		Topic::remove($topics, false, true);

		// Log an action into the moderation log.
		Logging::logAction('pruned', ['days' => $_POST['maxdays']]);

		Utils::redirectexit('action=admin;area=maintain;sa=topics;done=purgeold');
	}

	/*************************
	 * Internal static methods
	 *************************/
}

?>