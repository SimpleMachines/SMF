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

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Logging;
use SMF\Msg;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * This action handles restoring a topic from the recycle board back to its
 * original board.
 */
class TopicRestore implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'RestoreTopic',
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
	 * Move back a topic from the recycle board to its original board.
	 */
	public function execute(): void
	{
		// Check session.
		User::$me->checkSession('get');

		// Is recycled board enabled?
		if (empty(Config::$modSettings['recycle_enable'])) {
			ErrorHandler::fatalLang('restored_disabled', 'critical');
		}

		// Can we be in here?
		User::$me->isAllowedTo('move_any', Config::$modSettings['recycle_board']);

		$unfound_messages = [];
		$topics_to_restore = [];

		// Restoring messages?
		if (!empty($_REQUEST['msgs'])) {
			$msgs = explode(',', $_REQUEST['msgs']);

			foreach ($msgs as $k => $msg) {
				$msgs[$k] = (int) $msg;
			}

			// Get the id_previous_board and id_previous_topic.
			$request = Db::$db->query(
				'',
				'SELECT m.id_topic, m.id_msg, m.id_board, m.subject, m.id_member, t.id_previous_board, t.id_previous_topic,
					t.id_first_msg, b.count_posts, COALESCE(pt.id_board, 0) AS possible_prev_board
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					LEFT JOIN {db_prefix}topics AS pt ON (pt.id_topic = t.id_previous_topic)
				WHERE m.id_msg IN ({array_int:messages})',
				[
					'messages' => $msgs,
				],
			);

			$actioned_messages = [];
			$previous_topics = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				// Restoring the first post means topic.
				if ($row['id_msg'] == $row['id_first_msg'] && $row['id_previous_topic'] == $row['id_topic']) {
					$topics_to_restore[] = $row['id_topic'];

					continue;
				}

				// Don't know where it's going?
				if (empty($row['id_previous_topic'])) {
					$unfound_messages[$row['id_msg']] = $row['subject'];

					continue;
				}

				$previous_topics[] = $row['id_previous_topic'];

				if (empty($actioned_messages[$row['id_previous_topic']])) {
					$actioned_messages[$row['id_previous_topic']] = [
						'msgs' => [],
						'count_posts' => $row['count_posts'],
						'subject' => $row['subject'],
						'previous_board' => $row['id_previous_board'],
						'possible_prev_board' => $row['possible_prev_board'],
						'current_topic' => $row['id_topic'],
						'current_board' => $row['id_board'],
						'members' => [],
					];
				}

				$actioned_messages[$row['id_previous_topic']]['msgs'][$row['id_msg']] = $row['subject'];

				if ($row['id_member']) {
					$actioned_messages[$row['id_previous_topic']]['members'][] = $row['id_member'];
				}
			}
			Db::$db->free_result($request);

			// Check for topics we are going to fully restore.
			foreach ($actioned_messages as $topic => $data) {
				if (in_array($topic, $topics_to_restore)) {
					unset($actioned_messages[$topic]);
				}
			}

			// Load any previous topics to check they exist.
			if (!empty($previous_topics)) {
				$request = Db::$db->query(
					'',
					'SELECT t.id_topic, t.id_board, m.subject
					FROM {db_prefix}topics AS t
						INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
					WHERE t.id_topic IN ({array_int:previous_topics})',
					[
						'previous_topics' => $previous_topics,
					],
				);
				$previous_topics = [];

				while ($row = Db::$db->fetch_assoc($request)) {
					$previous_topics[$row['id_topic']] = [
						'board' => $row['id_board'],
						'subject' => $row['subject'],
					];
				}
				Db::$db->free_result($request);
			}

			// Restore each topic.
			$messages = [];

			foreach ($actioned_messages as $topic => $data) {
				// If we have topics we are going to restore the whole lot ignore them.
				if (in_array($topic, $topics_to_restore)) {
					unset($actioned_messages[$topic]);

					continue;
				}

				// Move the posts back then!
				if (isset($previous_topics[$topic])) {
					self::mergePosts(array_keys($data['msgs']), $data['current_topic'], $topic);
					// Log em.
					Logging::logAction('restore_posts', ['topic' => $topic, 'subject' => $previous_topics[$topic]['subject'], 'board' => empty($data['previous_board']) ? $data['possible_prev_board'] : $data['previous_board']]);
					$messages = array_merge(array_keys($data['msgs']), $messages);
				} else {
					foreach ($data['msgs'] as $msg) {
						$unfound_messages[$msg['id']] = $msg['subject'];
					}
				}
			}
		}

		// Now any topics?
		if (!empty($_REQUEST['topics'])) {
			$topics = explode(',', $_REQUEST['topics']);

			foreach ($topics as $id) {
				$topics_to_restore[] = (int) $id;
			}
		}

		if (!empty($topics_to_restore)) {
			// Lets get the data for these topics.
			$request = Db::$db->query(
				'',
				'SELECT t.id_topic, t.id_previous_board, t.id_board, t.id_first_msg, m.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_topic IN ({array_int:topics})',
				[
					'topics' => $topics_to_restore,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				// We can only restore if the previous board is set.
				if (empty($row['id_previous_board'])) {
					$unfound_messages[$row['id_first_msg']] = $row['subject'];

					continue;
				}

				// Ok we got here so me move them from here to there.
				Topic::move($row['id_topic'], $row['id_previous_board']);

				// Lets see if the board that we are returning to has post count enabled.
				$request2 = Db::$db->query(
					'',
					'SELECT count_posts
					FROM {db_prefix}boards
					WHERE id_board = {int:board}',
					[
						'board' => $row['id_previous_board'],
					],
				);
				list($count_posts) = Db::$db->fetch_row($request2);
				Db::$db->free_result($request2);

				if (empty($count_posts)) {
					// Lets get the members that need their post count restored.
					$request2 = Db::$db->query(
						'',
						'SELECT id_member, COUNT(*) AS post_count
						FROM {db_prefix}messages
						WHERE id_topic = {int:topic}
							AND approved = {int:is_approved}
						GROUP BY id_member',
						[
							'topic' => $row['id_topic'],
							'is_approved' => 1,
						],
					);

					while ($member = Db::$db->fetch_assoc($request2)) {
						User::updateMemberData($member['id_member'], ['posts' => 'posts + ' . $member['post_count']]);
					}
					Db::$db->free_result($request2);
				}

				// Log it.
				Logging::logAction('restore_topic', ['topic' => $row['id_topic'], 'board' => $row['id_board'], 'board_to' => $row['id_previous_board']]);
			}
			Db::$db->free_result($request);
		}

		// Didn't find some things?
		if (!empty($unfound_messages)) {
			ErrorHandler::fatalLang('restore_not_found', false, [implode('<br>', $unfound_messages)]);
		}

		// Just send them to the index if they get here.
		Utils::redirectexit();
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Take a load of messages from one place and stick them in a topic
	 *
	 * @param array $msgs The IDs of the posts to merge
	 * @param int $from_topic The ID of the topic the messages were originally in
	 * @param int $target_topic The ID of the topic the messages are being merged into
	 */
	protected static function mergePosts($msgs, $from_topic, $target_topic)
	{
		// !!! This really needs to be rewritten to take a load of messages from ANY topic, it's also inefficient.

		// Is it an array?
		if (!is_array($msgs)) {
			$msgs = [$msgs];
		}

		// Lets make sure they are int.
		foreach ($msgs as $key => $msg) {
			$msgs[$key] = (int) $msg;
		}

		// Get the source information.
		$request = Db::$db->query(
			'',
			'SELECT t.id_board, t.id_first_msg, t.num_replies, t.unapproved_posts
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic = {int:from_topic}',
			[
				'from_topic' => $from_topic,
			],
		);
		list($from_board, $from_first_msg, $from_replies, $from_unapproved_posts) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Get some target topic and board stats.
		$request = Db::$db->query(
			'',
			'SELECT t.id_board, t.id_first_msg, t.num_replies, t.unapproved_posts, b.count_posts
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic = {int:target_topic}',
			[
				'target_topic' => $target_topic,
			],
		);
		list($target_board, $target_first_msg, $target_replies, $target_unapproved_posts, $count_posts) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Lets see if the board that we are returning to has post count enabled.
		if (empty($count_posts)) {
			// Lets get the members that need their post count restored.
			$request = Db::$db->query(
				'',
				'SELECT id_member
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:messages})
					AND approved = {int:is_approved}',
				[
					'messages' => $msgs,
					'is_approved' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				User::updateMemberData($row['id_member'], ['posts' => '+']);
			}
		}

		// Time to move the messages.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET
				id_topic = {int:target_topic},
				id_board = {int:target_board}
			WHERE id_msg IN({array_int:msgs})',
			[
				'target_topic' => $target_topic,
				'target_board' => $target_board,
				'msgs' => $msgs,
			],
		);

		// Fix the id_first_msg and id_last_msg for the target topic.
		$target_topic_data = [
			'num_replies' => 0,
			'unapproved_posts' => 0,
			'id_first_msg' => 9999999999,
		];
		$request = Db::$db->query(
			'',
			'SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved
			FROM {db_prefix}messages
			WHERE id_topic = {int:target_topic}
			GROUP BY id_topic, approved
			ORDER BY approved ASC
			LIMIT 2',
			[
				'target_topic' => $target_topic,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($row['id_first_msg'] < $target_topic_data['id_first_msg']) {
				$target_topic_data['id_first_msg'] = $row['id_first_msg'];
			}
			$target_topic_data['id_last_msg'] = $row['id_last_msg'];

			if (!$row['approved']) {
				$target_topic_data['unapproved_posts'] = $row['message_count'];
			} else {
				$target_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
			}
		}
		Db::$db->free_result($request);

		// We have a new post count for the board.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET
				num_posts = num_posts + {int:diff_replies},
				unapproved_posts = unapproved_posts + {int:diff_unapproved_posts}
			WHERE id_board = {int:target_board}',
			[
				'diff_replies' => $target_topic_data['num_replies'] - $target_replies, // Lets keep in mind that the first message in a topic counts towards num_replies in a board.
				'diff_unapproved_posts' => $target_topic_data['unapproved_posts'] - $target_unapproved_posts,
				'target_board' => $target_board,
			],
		);

		// In some cases we merged the only post in a topic so the topic data is left behind in the topic table.
		$request = Db::$db->query(
			'',
			'SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_topic = {int:from_topic}',
			[
				'from_topic' => $from_topic,
			],
		);

		// Remove the topic if it doesn't have any messages.
		$topic_exists = true;

		if (Db::$db->num_rows($request) == 0) {
			Topic::remove($from_topic, false, true);
			$topic_exists = false;
		}
		Db::$db->free_result($request);

		// Recycled topic.
		if ($topic_exists == true) {
			// Fix the id_first_msg and id_last_msg for the source topic.
			$source_topic_data = [
				'num_replies' => 0,
				'unapproved_posts' => 0,
				'id_first_msg' => 9999999999,
			];
			$request = Db::$db->query(
				'',
				'SELECT MIN(id_msg) AS id_first_msg, MAX(id_msg) AS id_last_msg, COUNT(*) AS message_count, approved, subject
				FROM {db_prefix}messages
				WHERE id_topic = {int:from_topic}
				GROUP BY id_topic, approved, subject
				ORDER BY approved ASC
				LIMIT 2',
				[
					'from_topic' => $from_topic,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if ($row['id_first_msg'] < $source_topic_data['id_first_msg']) {
					$source_topic_data['id_first_msg'] = $row['id_first_msg'];
				}
				$source_topic_data['id_last_msg'] = $row['id_last_msg'];

				if (!$row['approved']) {
					$source_topic_data['unapproved_posts'] = $row['message_count'];
				} else {
					$source_topic_data['num_replies'] = max(0, $row['message_count'] - 1);
				}
			}
			Db::$db->free_result($request);

			// Update the topic details for the source topic.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}topics
				SET
					id_first_msg = {int:id_first_msg},
					id_last_msg = {int:id_last_msg},
					num_replies = {int:num_replies},
					unapproved_posts = {int:unapproved_posts}
				WHERE id_topic = {int:from_topic}',
				[
					'id_first_msg' => $source_topic_data['id_first_msg'],
					'id_last_msg' => $source_topic_data['id_last_msg'],
					'num_replies' => $source_topic_data['num_replies'],
					'unapproved_posts' => $source_topic_data['unapproved_posts'],
					'from_topic' => $from_topic,
				],
			);

			// We have a new post count for the source board.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}boards
				SET
					num_posts = num_posts + {int:diff_replies},
					unapproved_posts = unapproved_posts + {int:diff_unapproved_posts}
				WHERE id_board = {int:from_board}',
				[
					'diff_replies' => $source_topic_data['num_replies'] - $from_replies, // Lets keep in mind that the first message in a topic counts towards num_replies in a board.
					'diff_unapproved_posts' => $source_topic_data['unapproved_posts'] - $from_unapproved_posts,
					'from_board' => $from_board,
				],
			);
		}

		// Finally get around to updating the destination topic, now all indexes etc on the source are fixed.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET
				id_first_msg = {int:id_first_msg},
				id_last_msg = {int:id_last_msg},
				num_replies = {int:num_replies},
				unapproved_posts = {int:unapproved_posts}
			WHERE id_topic = {int:target_topic}',
			[
				'id_first_msg' => $target_topic_data['id_first_msg'],
				'id_last_msg' => $target_topic_data['id_last_msg'],
				'num_replies' => $target_topic_data['num_replies'],
				'unapproved_posts' => $target_topic_data['unapproved_posts'],
				'target_topic' => $target_topic,
			],
		);

		// Update stats.
		Logging::updateStats('topic');
		Logging::updateStats('message');

		// Subject cache?
		$cache_updates = [];

		if ($target_first_msg != $target_topic_data['id_first_msg']) {
			$cache_updates[] = $target_topic_data['id_first_msg'];
		}

		if (!empty($source_topic_data['id_first_msg']) && $from_first_msg != $source_topic_data['id_first_msg']) {
			$cache_updates[] = $source_topic_data['id_first_msg'];
		}

		if (!empty($cache_updates)) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic, subject
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:first_messages})',
				[
					'first_messages' => $cache_updates,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Logging::updateStats('subject', $row['id_topic'], $row['subject']);
			}
			Db::$db->free_result($request);
		}

		Msg::updateLastMessages([$from_board, $target_board]);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TopicRestore::exportStatic')) {
	TopicRestore::exportStatic();
}

?>