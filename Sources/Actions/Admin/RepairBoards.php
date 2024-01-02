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

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\User;
use SMF\Utils;

/**
 * This is here for the "repair any errors" feature in the admin center.
 */
class RepairBoards implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'RepairBoards',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * All the tests we might want to do.
	 *
	 * This array is defined like so:
	 *
	 * string check_query:  Query to be executed when testing if errors exist.
	 *
	 * string check_type:   Defines how it knows if a problem was found.
	 *                      If set to count looks for the first variable from
	 *                      check_query being > 0. Anything else it looks for
	 *                      some results. If not set assumes you want results.
	 *
	 * string fix_it_query: When doing fixes if an error was detected this query
	 *                      is executed to "fix" it.
	 *
	 * string fix_query:    The query to execute to get data when doing a fix.
	 *                      If not set check_query is used again.
	 *
	 * array fix_collect:   This array is used if the fix is basically gathering
	 *                      all broken ids and then doing something with them.
	 *  - string index:     The value returned from the main query and passed to
	 *                      the processing function.
	 *  - string process:   Name of a function that will be passed an array of
	 *                      ids to execute the fix on.
	 *
	 * string fix_processing:
	 *                      Name of a function called for each row returned from
	 *                      fix_query to execute whatever fixes are required.
	 *
	 * string fix_full_processing:
	 *                      As above but does the while loop and everything
	 *                      itself, except the freeing.
	 *
	 * array force_fix:	    If this is set then the error types included within
	 *                      this array will also be assumed broken.
	 *                      These are only processed if they occur after the
	 *                      primary error in the array.
	 *
	 * In all cases where a function name is provided, the findForumErrors()
	 * method will first look for a method of this class with that name. If no
	 * such method exists, it will ask Utils::getCallable() to figure out what
	 * to call.
	 *
	 * MOD AUTHORS: If you want to add tests to this array so that SMF can fix
	 * data for your mod, use the integrate_repair_boards hook.
	 */
	public array $errorTests = [
		// Make a last-ditch-effort check to get rid of topics with zeros..
		'zero_topics' => [
			'check_query' => '
				SELECT COUNT(*)
				FROM {db_prefix}topics
				WHERE id_topic = 0',
			'check_type' => 'count',
			'fix_it_query' => '
				UPDATE {db_prefix}topics
				SET id_topic = NULL
				WHERE id_topic = 0',
			'message' => 'repair_zero_ids',
		],
		// ... and same with messages.
		'zero_messages' => [
			'check_query' => '
				SELECT COUNT(*)
				FROM {db_prefix}messages
				WHERE id_msg = 0',
			'check_type' => 'count',
			'fix_it_query' => '
				UPDATE {db_prefix}messages
				SET id_msg = NULL
				WHERE id_msg = 0',
			'message' => 'repair_zero_ids',
		],
		// Find messages that don't have existing topics.
		'missing_topics' => [
			'substeps' => [
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}messages',
			],
			'check_query' => '
				SELECT m.id_topic, m.id_msg
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				WHERE m.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND t.id_topic IS NULL
				ORDER BY m.id_topic, m.id_msg',
			'fix_query' => '
				SELECT
					m.id_board, m.id_topic, MIN(m.id_msg) AS myid_first_msg, MAX(m.id_msg) AS myid_last_msg,
					COUNT(*) - 1 AS my_num_replies
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				WHERE t.id_topic IS NULL
				GROUP BY m.id_topic, m.id_board',
			'fix_processing' => 'fixMissingTopics',
			'force_fix' => ['stats_topics'],
			'messages' => ['repair_missing_topics', 'id_msg', 'id_topic'],
		],
		// Find topics with no messages.
		'missing_messages' => [
			'substeps' => [
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics',
			],
			'check_query' => '
				SELECT t.id_topic, COUNT(m.id_msg) AS num_msg
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY t.id_topic
				HAVING COUNT(m.id_msg) = 0',
			// Remove all topics that have zero messages in the messages table.
			'fix_collect' => [
				'index' => 'id_topic',
				'process' => 'fixMissingMessages',
			],
			'messages' => ['repair_missing_messages', 'id_topic'],
		],
		'poll_options_missing_poll' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}poll_choices',
			],
			'check_query' => '
				SELECT o.id_poll, count(*) as amount, t.id_topic, t.id_board, t.id_member_started AS id_poster, m.member_name AS poster_name
				FROM {db_prefix}poll_choices AS o
					LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = o.id_poll)
					LEFT JOIN {db_prefix}topics AS t ON (t.id_poll = o.id_poll)
					LEFT JOIN {db_prefix}members AS m ON (m.id_member = t.id_member_started)
				WHERE o.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND p.id_poll IS NULL
				GROUP BY o.id_poll, t.id_topic, t.id_board, t.id_member_started, m.member_name',
			'fix_processing' => 'fixMissingPollOptions',
			'force_fix' => ['stats_topics'],
			'messages' => ['repair_poll_options_missing_poll', 'id_poll', 'amount'],
		],
		'polls_missing_topics' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}polls',
			],
			'check_query' => '
				SELECT p.id_poll, p.id_member, p.poster_name, t.id_board
				FROM {db_prefix}polls AS p
					LEFT JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
				WHERE p.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND t.id_poll IS NULL',
			'fix_processing' => 'fixMissingPollTopics',
			'force_fix' => ['stats_topics'],
			'messages' => ['repair_polls_missing_topics', 'id_poll', 'id_topic'],
		],
		'stats_topics' => [
			'substeps' => [
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics',
			],
			'check_query' => '
				SELECT
					t.id_topic, t.id_first_msg, t.id_last_msg,
					CASE WHEN MIN(ma.id_msg) > 0 THEN
						CASE WHEN MIN(mu.id_msg) > 0 THEN
							CASE WHEN MIN(mu.id_msg) < MIN(ma.id_msg) THEN MIN(mu.id_msg) ELSE MIN(ma.id_msg) END ELSE
						MIN(ma.id_msg) END ELSE
					MIN(mu.id_msg) END AS myid_first_msg,
					CASE WHEN MAX(ma.id_msg) > 0 THEN MAX(ma.id_msg) ELSE MIN(mu.id_msg) END AS myid_last_msg,
					t.approved, mf.approved, mf.approved AS firstmsg_approved
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = 1)
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = 0)
					LEFT JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY t.id_topic, t.id_first_msg, t.id_last_msg, t.approved, mf.approved
				ORDER BY t.id_topic',
			'fix_processing' => 'fixTopicStats',
			'message_function' => 'topicStatsMessage',
		],
		// Find topics with incorrect num_replies.
		'stats_topics2' => [
			'substeps' => [
				'step_size' => 300,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics',
			],
			'check_query' => '
				SELECT
					t.id_topic, t.num_replies, mf.approved,
					CASE WHEN COUNT(ma.id_msg) > 0 THEN CASE WHEN mf.approved > 0 THEN COUNT(ma.id_msg) - 1 ELSE COUNT(ma.id_msg) END ELSE 0 END AS my_num_replies
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = 1)
					LEFT JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY t.id_topic, t.num_replies, mf.approved
				ORDER BY t.id_topic',
			'fix_processing' => 'fixTopicStats2',
			'message_function' => 'topicStatsMessage2',
		],
		// Find topics with incorrect unapproved_posts.
		'stats_topics3' => [
			'substeps' => [
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics',
			],
			'check_query' => '
				SELECT
					t.id_topic, t.unapproved_posts, COUNT(mu.id_msg) AS my_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = 0)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY t.id_topic, t.unapproved_posts
				HAVING unapproved_posts != COUNT(mu.id_msg)
				ORDER BY t.id_topic',
			'fix_processing' => 'fixTopicStats3',
			'messages' => ['repair_topic_wrong_unapproved_number', 'id_topic', 'unapproved_posts'],
		],
		// Find topics with nonexistent boards.
		'missing_boards' => [
			'substeps' => [
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics',
			],
			'check_query' => '
				SELECT t.id_topic, t.id_board
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				WHERE b.id_board IS NULL
					AND t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				ORDER BY t.id_board, t.id_topic',
			'fix_query' => '
				SELECT t.id_board, COUNT(*) AS my_num_topics, COUNT(m.id_msg) AS my_num_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
				WHERE b.id_board IS NULL
					AND t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY t.id_board',
			'fix_processing' => 'fixMissingBoards',
			'messages' => ['repair_missing_boards', 'id_topic', 'id_board'],
		],
		// Find boards with nonexistent categories.
		'missing_categories' => [
			'check_query' => '
				SELECT b.id_board, b.id_cat
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE c.id_cat IS NULL
				ORDER BY b.id_cat, b.id_board',
			'fix_collect' => [
				'index' => 'id_cat',
				'process' => 'fixMissingCategories',
			],
			'messages' => ['repair_missing_categories', 'id_board', 'id_cat'],
		],
		// Find messages with nonexistent members.
		'missing_posters' => [
			'substeps' => [
				'step_size' => 2000,
				'step_max' => '
					SELECT MAX(id_msg)
					FROM {db_prefix}messages',
			],
			'check_query' => '
				SELECT m.id_msg, m.id_member
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE mem.id_member IS NULL
					AND m.id_member != 0
					AND m.id_msg BETWEEN {STEP_LOW} AND {STEP_HIGH}
				ORDER BY m.id_msg',
			// Last step-make sure all non-guest posters still exist.
			'fix_collect' => [
				'index' => 'id_msg',
				'process' => 'fixMissingPosters',
			],
			'messages' => ['repair_missing_posters', 'id_msg', 'id_member'],
		],
		// Find boards with nonexistent parents.
		'missing_parents' => [
			'check_query' => '
				SELECT b.id_board, b.id_parent
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}boards AS p ON (p.id_board = b.id_parent)
				WHERE b.id_parent != 0
					AND (p.id_board IS NULL OR p.id_board = b.id_board)
				ORDER BY b.id_parent, b.id_board',
			'fix_collect' => [
				'index' => 'id_parent',
				'process' => 'fixMissingParents',
			],
			'messages' => ['repair_missing_parents', 'id_board', 'id_parent'],
		],
		'missing_polls' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}topics',
			],
			'check_query' => '
				SELECT t.id_poll, t.id_topic
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
				WHERE t.id_poll != 0
					AND t.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND p.id_poll IS NULL',
			'fix_collect' => [
				'index' => 'id_poll',
				'process' => 'fixMissingPolls',
			],
			'messages' => ['repair_missing_polls', 'id_topic', 'id_poll'],
		],
		'missing_calendar_topics' => [
			'substeps' => [
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}calendar',
			],
			'check_query' => '
				SELECT cal.id_topic, cal.id_event
				FROM {db_prefix}calendar AS cal
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)
				WHERE cal.id_topic != 0
					AND cal.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND t.id_topic IS NULL
				ORDER BY cal.id_topic',
			'fix_collect' => [
				'index' => 'id_topic',
				'process' => 'fixMissingCaledarTopics',
			],
			'messages' => ['repair_missing_calendar_topics', 'id_event', 'id_topic'],
		],
		'missing_log_topics' => [
			'substeps' => [
				'step_size' => 150,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_topics',
			],
			'check_query' => '
				SELECT lt.id_topic
				FROM {db_prefix}log_topics AS lt
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lt.id_topic)
				WHERE t.id_topic IS NULL
					AND lt.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}',
			'fix_collect' => [
				'index' => 'id_topic',
				'process' => 'fixMissingLogTopics',
			],
			'messages' => ['repair_missing_log_topics', 'id_topic'],
		],
		'missing_log_topics_members' => [
			'substeps' => [
				'step_size' => 150,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_topics',
			],
			'check_query' => '
				SELECT lt.id_member
				FROM {db_prefix}log_topics AS lt
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lt.id_member)
				WHERE mem.id_member IS NULL
					AND lt.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lt.id_member',
			'fix_collect' => [
				'index' => 'id_member',
				'process' => 'fixMissingLogTopicsMembers',
			],
			'messages' => ['repair_missing_log_topics_members', 'id_member'],
		],
		'missing_log_boards' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_boards',
			],
			'check_query' => '
				SELECT lb.id_board
				FROM {db_prefix}log_boards AS lb
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lb.id_board)
				WHERE b.id_board IS NULL
					AND lb.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lb.id_board',
			'fix_collect' => [
				'index' => 'id_board',
				'process' => 'fixMissingLogBoards',
			],
			'messages' => ['repair_missing_log_boards', 'id_board'],
		],
		'missing_log_boards_members' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_boards',
			],
			'check_query' => '
				SELECT lb.id_member
				FROM {db_prefix}log_boards AS lb
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lb.id_member)
				WHERE mem.id_member IS NULL
					AND lb.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lb.id_member',
			'fix_collect' => [
				'index' => 'id_member',
				'process' => 'fixMissingLogBoardsMembers',
			],
			'messages' => ['repair_missing_log_boards_members', 'id_member'],
		],
		'missing_log_mark_read' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_mark_read',
			],
			'check_query' => '
				SELECT lmr.id_board
				FROM {db_prefix}log_mark_read AS lmr
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lmr.id_board)
				WHERE b.id_board IS NULL
					AND lmr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lmr.id_board',
			'fix_collect' => [
				'index' => 'id_board',
				'process' => 'fixMissingLogMarkRead',
			],
			'messages' => ['repair_missing_log_mark_read', 'id_board'],
		],
		'missing_log_mark_read_members' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_mark_read',
			],
			'check_query' => '
				SELECT lmr.id_member
				FROM {db_prefix}log_mark_read AS lmr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lmr.id_member)
				WHERE mem.id_member IS NULL
					AND lmr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lmr.id_member',
			'fix_collect' => [
				'index' => 'id_member',
				'process' => 'fixMissingLogMarkReadMembers',
			],
			'messages' => ['repair_missing_log_mark_read_members', 'id_member'],
		],
		'missing_pms' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_pm)
					FROM {db_prefix}pm_recipients',
			],
			'check_query' => '
				SELECT pmr.id_pm
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				WHERE pm.id_pm IS NULL
					AND pmr.id_pm BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY pmr.id_pm',
			'fix_collect' => [
				'index' => 'id_pm',
				'process' => 'fixMissingPMs',
			],
			'messages' => ['repair_missing_pms', 'id_pm'],
		],
		'missing_recipients' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}pm_recipients',
			],
			'check_query' => '
				SELECT pmr.id_member
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
				WHERE pmr.id_member != 0
					AND pmr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL
				GROUP BY pmr.id_member',
			'fix_collect' => [
				'index' => 'id_member',
				'process' => 'fixMissingRecipients',
			],
			'messages' => ['repair_missing_recipients', 'id_member'],
		],
		'missing_senders' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_pm)
					FROM {db_prefix}personal_messages',
			],
			'check_query' => '
				SELECT pm.id_pm, pm.id_member_from
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_member_from != 0
					AND pm.id_pm BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL',
			'fix_collect' => [
				'index' => 'id_pm',
				'process' => 'fixMissingSenders',
			],
			'messages' => ['repair_missing_senders', 'id_pm', 'id_member_from'],
		],
		'missing_notify_members' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_notify',
			],
			'check_query' => '
				SELECT ln.id_member
				FROM {db_prefix}log_notify AS ln
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
				WHERE ln.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL
				GROUP BY ln.id_member',
			'fix_collect' => [
				'index' => 'id_member',
				'process' => 'fixMissingNotifyMembers',
			],
			'messages' => ['repair_missing_notify_members', 'id_member'],
		],
		'missing_cached_subject' => [
			'substeps' => [
				'step_size' => 100,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics',
			],
			'check_query' => '
				SELECT t.id_topic, fm.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}log_search_subjects AS lss ON (lss.id_topic = t.id_topic)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lss.id_topic IS NULL',
			'fix_full_processing' => 'fixMissingCachedSubject',
			'message_function' => 'missingCachedSubjectMessage',
		],
		'missing_topic_for_cache' => [
			'substeps' => [
				'step_size' => 50,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}log_search_subjects',
			],
			'check_query' => '
				SELECT lss.id_topic, lss.word
				FROM {db_prefix}log_search_subjects AS lss
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lss.id_topic)
				WHERE lss.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND t.id_topic IS NULL',
			'fix_collect' => [
				'index' => 'id_topic',
				'process' => 'fixMissingTopicForCache',
			],
			'messages' => ['repair_missing_topic_for_cache', 'word'],
		],
		'missing_member_vote' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_polls',
			],
			'check_query' => '
				SELECT lp.id_poll, lp.id_member
				FROM {db_prefix}log_polls AS lp
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lp.id_member)
				WHERE lp.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lp.id_member > 0
					AND mem.id_member IS NULL',
			'fix_collect' => [
				'index' => 'id_member',
				'process' => 'fixMissingMemberVote',
			],
			'messages' => ['repair_missing_log_poll_member', 'id_poll', 'id_member'],
		],
		'missing_log_poll_vote' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}log_polls',
			],
			'check_query' => '
				SELECT lp.id_poll, lp.id_member
				FROM {db_prefix}log_polls AS lp
					LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = lp.id_poll)
				WHERE lp.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND p.id_poll IS NULL',
			'fix_collect' => [
				'index' => 'id_poll',
				'process' => 'fixMissingLogPollVote',
			],
			'messages' => ['repair_missing_log_poll_vote', 'id_member', 'id_poll'],
		],
		'report_missing_comments' => [
			'substeps' => [
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_report)
					FROM {db_prefix}log_reported',
			],
			'check_query' => '
				SELECT lr.id_report, lr.subject
				FROM {db_prefix}log_reported AS lr
					LEFT JOIN {db_prefix}log_reported_comments AS lrc ON (lrc.id_report = lr.id_report)
				WHERE lr.id_report BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lrc.id_report IS NULL',
			'fix_collect' => [
				'index' => 'id_report',
				'process' => 'fixReportMissingComments',
			],
			'messages' => ['repair_report_missing_comments', 'id_report', 'subject'],
		],
		'comments_missing_report' => [
			'substeps' => [
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_report)
					FROM {db_prefix}log_reported_comments',
			],
			'check_query' => '
				SELECT lrc.id_report, lrc.membername
				FROM {db_prefix}log_reported_comments AS lrc
					LEFT JOIN {db_prefix}log_reported AS lr ON (lr.id_report = lrc.id_report)
				WHERE lrc.id_report BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lr.id_report IS NULL',
			'fix_collect' => [
				'index' => 'id_report',
				'process' => 'fixCommentMissingReport',
			],
			'messages' => ['repair_comments_missing_report', 'id_report', 'membername'],
		],
		'group_request_missing_member' => [
			'substeps' => [
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_group_requests',
			],
			'check_query' => '
				SELECT lgr.id_member
				FROM {db_prefix}log_group_requests AS lgr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				WHERE lgr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL
				GROUP BY lgr.id_member',
			'fix_collect' => [
				'index' => 'id_member',
				'process' => 'fixGroupRequestMissingMember',
			],
			'messages' => ['repair_group_request_missing_member', 'id_member'],
		],
		'group_request_missing_group' => [
			'substeps' => [
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_group)
					FROM {db_prefix}log_group_requests',
			],
			'check_query' => '
				SELECT lgr.id_group
				FROM {db_prefix}log_group_requests AS lgr
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
				WHERE lgr.id_group BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mg.id_group IS NULL
				GROUP BY lgr.id_group',
			'fix_collect' => [
				'index' => 'id_group',
				'process' => 'fixGroupRequestMissingGroup',
			],
			'messages' => ['repair_group_request_missing_group', 'id_group'],
		],
	];

	/**
	 * @var int
	 *
	 * Tracks how many loops we have done for pauseRepairProcess().
	 */
	public int $loops = 0;

	/**
	 * @var bool
	 *
	 * Whether the salvage area has been created yet.
	 */
	public bool $salvage_created = false;

	/**
	 * @var int
	 *
	 *
	 */
	public int $salvage_board;

	/**
	 * @var int
	 *
	 *
	 */
	public int $salvage_category;

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
		User::$me->isAllowedTo('admin_forum');

		// Try to secure more memory.
		Config::setMemoryLimit('128M');

		// Start displaying errors without fixing them.
		if (isset($_GET['fixErrors'])) {
			User::$me->checkSession('get');
		}

		// Giant if/else. The first displays the forum errors if a variable is not set and asks
		// if you would like to continue, the other fixes the errors.
		if (!isset($_GET['fixErrors'])) {
			Utils::$context['error_search'] = true;
			Utils::$context['repair_errors'] = [];

			Utils::$context['to_fix'] = $this->findForumErrors();

			if (!empty(Utils::$context['to_fix'])) {
				$_SESSION['repairboards_to_fix'] = Utils::$context['to_fix'];
				$_SESSION['repairboards_to_fix2'] = null;

				if (empty(Utils::$context['repair_errors'])) {
					Utils::$context['repair_errors'][] = '???';
				}
			}

			// Need a token here.
			SecurityToken::create('admin-repairboards', 'request');
		} else {
			// Validate the token, create a new one and tell the not done template.
			SecurityToken::validate('admin-repairboards', 'request');
			SecurityToken::create('admin-repairboards', 'request');
			Utils::$context['not_done_token'] = 'admin-repairboards';

			Utils::$context['error_search'] = false;
			Utils::$context['to_fix'] = $_SESSION['repairboards_to_fix'] ?? [];

			// Actually do the fix.
			$this->findForumErrors(true);

			// Note that we've changed everything possible ;)
			Config::updateModSettings([
				'settings_updated' => time(),
			]);
			Logging::updateStats('message');
			Logging::updateStats('topic');
			Config::updateModSettings([
				'calendar_updated' => time(),
			]);

			// If we created a salvage area, we may need to recount stats properly.
			if (!empty($this->salvage_board) || !empty($_SESSION['salvageBoardID'])) {
				unset($_SESSION['salvageBoardID']);
				Utils::$context['redirect_to_recount'] = true;
				SecurityToken::create('admin-maint');
			}

			$_SESSION['repairboards_to_fix'] = null;
			$_SESSION['repairboards_to_fix2'] = null;

			// We are done at this point, dump the token,
			SecurityToken::validate('admin-repairboards', 'request', false);
		}
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
		// Print out the top of the webpage.
		Utils::$context['page_title'] = Lang::$txt['admin_repair'];
		Utils::$context['sub_template'] = 'repair_boards';
		Menu::$loaded['admin']['current_subsection'] = 'general';

		// Load the language file.
		Lang::load('ManageMaintenance');

		// Make sure the tabs stay nice.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['maintain_title'],
			'help' => '',
			'description' => Lang::$txt['maintain_info'],
			'tabs' => [],
		];
	}

	/**
	 * Checks for errors in steps, until 5 seconds have passed.
	 *
	 * It keeps track of the errors it did find, so that the actual repair
	 * won't have to recheck everything.
	 *
	 * @param bool $do_fix Whether to fix the errors or just return the info.
	 * @return array The errors found.
	 */
	protected function findForumErrors(bool $do_fix = false): array
	{
		// This may take some time...
		@set_time_limit(600);

		$to_fix = !empty($_SESSION['repairboards_to_fix']) ? $_SESSION['repairboards_to_fix'] : [];

		Utils::$context['repair_errors'] = $_SESSION['repairboards_to_fix2'] ?? [];

		$_GET['step'] = empty($_GET['step']) ? 0 : (int) $_GET['step'];
		$_GET['substep'] = empty($_GET['substep']) ? 0 : (int) $_GET['substep'];

		// Do mods want to add anything here to allow repairing their data?
		IntegrationHook::call('integrate_repair_boards', [&$this->errorTests]);

		// Don't allow the cache to get too full.
		Utils::$context['db_cache'] = Db::$cache;
		Db::$cache = [];

		Utils::$context['total_steps'] = count($this->errorTests);

		// For all the defined error types do the necessary tests.
		$current_step = -1;
		$total_queries = 0;

		foreach ($this->errorTests as $error_type => $test) {
			$current_step++;

			// Already done this?
			if ($_GET['step'] > $current_step) {
				continue;
			}

			// If we're fixing it but it ain't broke why try?
			if ($do_fix && !in_array($error_type, $to_fix)) {
				$_GET['step']++;

				continue;
			}

			// Has it got substeps?
			if (isset($test['substeps'])) {
				$step_size = $test['substeps']['step_size'] ?? 100;

				$request = Db::$db->query(
					'',
					$test['substeps']['step_max'],
					[
					],
				);
				list($step_max) = Db::$db->fetch_row($request);
				$total_queries++;
				Db::$db->free_result($request);
			}

			// We in theory keep doing this... the substeps.
			$done = false;

			while (!$done) {
				// Make sure there's at least one ID to test.
				if (isset($test['substeps']) && empty($step_max)) {
					break;
				}

				// What is the testing query (Changes if we are testing or fixing)
				$test_query = $do_fix && isset($test['fix_query']) ? 'fix_query' : 'check_query';

				// Do the test...
				$request = Db::$db->query(
					'',
					isset($test['substeps']) ? strtr($test[$test_query], ['{STEP_LOW}' => $_GET['substep'], '{STEP_HIGH}' => $_GET['substep'] + $step_size - 1]) : $test[$test_query],
					[
					],
				);

				// Does it need a fix?
				if (!empty($test['check_type']) && $test['check_type'] == 'count') {
					list($needs_fix) = Db::$db->fetch_row($request);
				} else {
					$needs_fix = Db::$db->num_rows($request);
				}

				$total_queries++;

				if ($needs_fix) {
					// What about a message to the user?
					if (!$do_fix) {
						// Assume need to fix.
						$found_errors = true;

						if (isset($test['message'])) {
							Utils::$context['repair_errors'][] = Lang::$txt[$test['message']];
						}
						// One per row!
						elseif (isset($test['messages'])) {
							while ($row = Db::$db->fetch_assoc($request)) {
								$variables = $test['messages'];

								foreach ($variables as $k => $v) {
									if ($k == 0 && isset(Lang::$txt[$v])) {
										$variables[$k] = Lang::$txt[$v];
									} elseif ($k > 0 && isset($row[$v])) {
										$variables[$k] = $row[$v];
									}
								}

								Utils::$context['repair_errors'][] = call_user_func_array('sprintf', $variables);
							}
						}
						// A function to process?
						elseif (isset($test['message_function'])) {
							// Find out if there are actually errors.
							$found_errors = false;

							$func = method_exists($this, $test['message_function']) ? [$this, $test['message_function']] : Utils::getCallable($test['message_function']);

							while ($row = Db::$db->fetch_assoc($request)) {
								$found_errors |= call_user_func($func, $row);
							}
						}

						// Actually have something to fix?
						if ($found_errors) {
							$to_fix[] = $error_type;
						}
					}
					// We want to fix, we need to fix - so work out what exactly to do!
					else {
						// Are we simply getting a collection of ids?
						if (isset($test['fix_collect'])) {
							$ids = [];

							while ($row = Db::$db->fetch_assoc($request)) {
								$ids[] = $row[$test['fix_collect']['index']];
							}

							if (!empty($ids)) {
								$func = method_exists($this, $test['fix_collect']['process']) ? [$this, $test['fix_collect']['process']] : Utils::getCallable($test['fix_collect']['process']);

								// Fix it!
								call_user_func($func, $ids);
							}
						}
						// Simply executing a fix it query?
						elseif (isset($test['fix_it_query'])) {
							Db::$db->query(
								'',
								$test['fix_it_query'],
								[
								],
							);
						}
						// Do we have some processing to do?
						elseif (isset($test['fix_processing'])) {
							$func = method_exists($this, $test['fix_processing']) ? [$this, $test['fix_processing']] : Utils::getCallable($test['fix_processing']);

							while ($row = Db::$db->fetch_assoc($request)) {
								call_user_func($func, $row);
							}
						}
						// What about the full set of processing?
						elseif (isset($test['fix_full_processing'])) {
							$func = method_exists($this, $test['fix_full_processing']) ? [$this, $test['fix_full_processing']] : Utils::getCallable($test['fix_full_processing']);

							call_user_func($func, $request);
						}

						// Do we have other things we need to fix as a result?
						if (!empty($test['force_fix'])) {
							foreach ($test['force_fix'] as $item) {
								if (!in_array($item, $to_fix)) {
									$to_fix[] = $item;
								}
							}
						}
					}
				}
				// Free the result.
				Db::$db->free_result($request);

				// Keep memory down.
				Db::$cache = [];

				// Are we done yet?
				if (isset($test['substeps'])) {
					$_GET['substep'] += $step_size;

					// Not done?
					if ($_GET['substep'] <= $step_max) {
						$this->pauseRepairProcess($to_fix, $error_type, $step_max);
					} else {
						$done = true;
					}
				} else {
					$done = true;
				}

				// Don't allow more than 1000 queries at a time.
				if ($total_queries >= 1000) {
					$this->pauseRepairProcess($to_fix, $error_type, $step_max, true);
				}
			}

			// Keep going.
			$_GET['step']++;
			$_GET['substep'] = 0;

			$to_fix = array_unique($to_fix);

			// If we're doing fixes and this needed a fix and we're all done then don't do it again.
			if ($do_fix) {
				$key = array_search($error_type, $to_fix);

				if ($key !== false && isset($to_fix[$key])) {
					unset($to_fix[$key]);
				}
			}

			// Are we done?
			$this->pauseRepairProcess($to_fix, $error_type);
		}

		// Restore the cache.
		Db::$cache = Utils::$context['db_cache'];

		return $to_fix;
	}

	/**
	 * Shows the not_done template to avoid CGI timeouts and similar.
	 *
	 * Called when 3 or more seconds have passed while searching for errors.
	 *
	 * @param array $to_fix An array of information about what to fix.
	 * @param string $current_step_description Description of the current step.
	 * @param int $max_substep The maximum substep to reach before pausing.
	 * @param bool $force Whether to force pausing even if we don't need to.
	 */
	protected function pauseRepairProcess($to_fix, $current_step_description, $max_substep = 0, $force = false): void
	{
		++$this->loops;

		// More time, I need more time!
		@set_time_limit(600);

		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}

		$return = true;

		// If we are from a SSI/cron job, we can allow this through, if enabled.
		if ((SMF === 'SSI' || SMF === 'BACKGROUND') && php_sapi_name() == 'cli' && !empty(Utils::$context['no_pause_process'])) {
			$return = true;
		} elseif ($force) {
			$return = false;
		}
		// Try to stay under our memory limit.
		elseif ((memory_get_usage() + 65536) > Config::memoryReturnBytes(ini_get('memory_limit'))) {
			$return = false;
		}
		// Errr, wait.  How much time has this taken already?
		elseif ((time() - TIME_START) > 3) {
			$return = false;
		}
		// If we have a lot of errors, lets do smaller batches, to save on memory needs.
		elseif (count(Utils::$context['repair_errors']) > 100000 && $this->loops > 50) {
			$return = false;
		}

		// If we can return, lets do so.
		if ($return) {
			return;
		}

		// Restore the query cache if interested.
		if (!empty(Utils::$context['db_cache'])) {
			Db::$cache = Utils::$context['db_cache'];
		}

		Utils::$context['continue_get_data'] = '?action=admin;area=repairboards' . (isset($_GET['fixErrors']) ? ';fixErrors' : '') . ';step=' . $_GET['step'] . ';substep=' . $_GET['substep'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		Utils::$context['page_title'] = Lang::$txt['not_done_title'];
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_countdown'] = '2';
		Utils::$context['sub_template'] = 'not_done';

		// Change these two if more steps are added!
		if (empty($max_substep)) {
			Utils::$context['continue_percent'] = round(($_GET['step'] * 100) / Utils::$context['total_steps']);
		} else {
			Utils::$context['continue_percent'] = round((($_GET['step'] + ($_GET['substep'] / $max_substep)) * 100) / Utils::$context['total_steps']);
		}

		// Never more than 100%!
		Utils::$context['continue_percent'] = min(Utils::$context['continue_percent'], 100);

		// What about substeps?
		Utils::$context['substep_enabled'] = $max_substep != 0;
		Utils::$context['substep_title'] = sprintf(Lang::$txt['repair_currently_' . (isset($_GET['fixErrors']) ? 'fixing' : 'checking')], (Lang::$txt['repair_operation_' . $current_step_description] ?? $current_step_description));
		Utils::$context['substep_continue_percent'] = $max_substep == 0 ? 0 : round(($_GET['substep'] * 100) / $max_substep, 1);

		$_SESSION['repairboards_to_fix'] = $to_fix;
		$_SESSION['repairboards_to_fix2'] = Utils::$context['repair_errors'];

		Utils::obExit();
	}

	/**
	 * Create a salvage area for repair purposes, if one doesn't already exist.
	 * Uses the forum's default language, and checks based on that name.
	 */
	protected function createSalvageArea(): void
	{
		// Have we already created it?
		if ($this->salvage_created) {
			return;
		}

		$this->salvage_created = true;

		// Back to the forum's default language.
		Lang::load('Admin', Lang::$default);

		// Check to see if a 'Salvage Category' exists, if not => insert one.
		$result = Db::$db->query(
			'',
			'SELECT id_cat
			FROM {db_prefix}categories
			WHERE name = {string:cat_name}
			LIMIT 1',
			[
				'cat_name' => Lang::$txt['salvaged_category_name'],
			],
		);

		if (Db::$db->num_rows($result) != 0) {
			list($this->salvage_category) = Db::$db->fetch_row($result);
		}
		Db::$db->free_result($result);

		if (empty($this->salvage_category)) {
			$this->salvage_category = Db::$db->insert(
				'',
				'{db_prefix}categories',
				['name' => 'string-255', 'cat_order' => 'int', 'description' => 'string-255'],
				[Lang::$txt['salvaged_category_name'], -1, Lang::$txt['salvaged_category_description']],
				['id_cat'],
				1,
			);

			if (Db::$db->affected_rows() <= 0) {
				Lang::load('Admin');
				ErrorHandler::fatalLang('salvaged_category_error', false);
			}
		}

		// Check to see if a 'Salvage Board' exists. If not, insert one.
		$result = Db::$db->query(
			'',
			'SELECT id_board
			FROM {db_prefix}boards
			WHERE id_cat = {int:id_cat}
				AND name = {string:board_name}
			LIMIT 1',
			[
				'id_cat' => $this->salvage_category,
				'board_name' => Lang::$txt['salvaged_board_name'],
			],
		);

		if (Db::$db->num_rows($result) != 0) {
			list($this->salvage_board) = Db::$db->fetch_row($result);
		}
		Db::$db->free_result($result);

		if (empty($this->salvage_board)) {
			$this->salvage_board = Db::$db->insert(
				'',
				'{db_prefix}boards',
				['name' => 'string-255', 'description' => 'string-255', 'id_cat' => 'int', 'member_groups' => 'string', 'board_order' => 'int', 'redirect' => 'string'],
				[Lang::$txt['salvaged_board_name'], Lang::$txt['salvaged_board_description'], $this->salvage_category, '1', -1, ''],
				['id_board'],
				1,
			);

			if (Db::$db->affected_rows() <= 0) {
				Lang::load('Admin');
				ErrorHandler::fatalLang('salvaged_board_error', false);
			}
		}

		// Restore the user's language.
		Lang::load('Admin');
	}

	/**
	 * Callback to fix missing topics.
	 */
	protected function fixMissingTopics($row): void
	{
		// Only if we don't have a reasonable idea of where to put it.
		if ($row['id_board'] == 0) {
			$this->createSalvageArea();
			$row['id_board'] = $_SESSION['salvageBoardID'] = $this->salvage_board;
		}

		// Make sure that no topics claim the first/last message as theirs.
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_first_msg = 0
			WHERE id_first_msg = {int:id_first_msg}',
			[
				'id_first_msg' => $row['myid_first_msg'],
			],
		);
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_last_msg = 0
			WHERE id_last_msg = {int:id_last_msg}',
			[
				'id_last_msg' => $row['myid_last_msg'],
			],
		);

		$memberStartedID = (int) Board::getMsgMemberID($row['myid_first_msg']);
		$memberUpdatedID = (int) Board::getMsgMemberID($row['myid_last_msg']);

		$newTopicID = Db::$db->insert(
			'',
			'{db_prefix}topics',
			[
				'id_board' => 'int',
				'id_member_started' => 'int',
				'id_member_updated' => 'int',
				'id_first_msg' => 'int',
				'id_last_msg' => 'int',
				'num_replies' => 'int',
			],
			[
				$row['id_board'],
				$memberStartedID,
				$memberUpdatedID,
				$row['myid_first_msg'],
				$row['myid_last_msg'],
				$row['my_num_replies'],
			],
			['id_topic'],
			1,
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_topic = {int:newTopicID}, id_board = {int:board_id}
			WHERE id_topic = {int:topic_id}',
			[
				'board_id' => $row['id_board'],
				'topic_id' => $row['id_topic'],
				'newTopicID' => $newTopicID,
			],
		);
	}

	/**
	 * Callback to remove all topics that have zero messages in the messages table.
	 */
	protected function fixMissingMessages($topics): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}topics
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
	}

	/**
	 * Callback to fix missing poll options.
	 */
	protected function fixMissingPollOptions($row): void
	{
		$row['poster_name'] = !empty($row['poster_name']) ? $row['poster_name'] : Lang::$txt['guest'];
		$row['id_poster'] = !empty($row['id_poster']) ? $row['id_poster'] : 0;

		if (empty($row['id_board'])) {
			// Only if we don't have a reasonable idea of where to put it.
			$this->createSalvageArea();
			$row['id_board'] = $_SESSION['salvageBoardID'] = $this->salvage_board;
		}

		if (empty($row['id_topic'])) {
			$newMessageID = Db::$db->insert(
				'',
				'{db_prefix}messages',
				[
					'id_board' => 'int',
					'id_topic' => 'int',
					'poster_time' => 'int',
					'id_member' => 'int',
					'subject' => 'string-255',
					'poster_name' => 'string-255',
					'poster_email' => 'string-255',
					'poster_ip' => 'inet',
					'smileys_enabled' => 'int',
					'body' => 'string-65534',
					'icon' => 'string-16',
					'approved' => 'int',
				],
				[
					$row['id_board'],
					0,
					time(),
					$row['id_poster'],
					Lang::$txt['salvaged_poll_topic_name'],
					$row['poster_name'],
					Lang::$txt['salvaged_poll_topic_name'],
					'127.0.0.1',
					1,
					Lang::$txt['salvaged_poll_message_body'],
					'xx',
					1,
				],
				['id_msg'],
				1,
			);

			$row['id_topic'] = Db::$db->insert(
				'',
				'{db_prefix}topics',
				[
					'id_board' => 'int',
					'id_poll' => 'int',
					'id_member_started' => 'int',
					'id_member_updated' => 'int',
					'id_first_msg' => 'int',
					'id_last_msg' => 'int',
					'num_replies' => 'int',
				],
				[
					$row['id_board'],
					$row['id_poll'],
					$row['id_poster'],
					$row['id_poster'],
					$newMessageID,
					$newMessageID,
					0,
				],
				['id_topic'],
				1,
			);

			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET id_topic = {int:newTopicID}, id_board = {int:id_board}
				WHERE id_msg = {int:newMessageID}',
				[
					'id_board' => $row['id_board'],
					'newTopicID' => $row['id_topic'],
					'newMessageID' => $newMessageID,
				],
			);

			Logging::updateStats('subject', $row['id_topic'], Lang::$txt['salvaged_poll_topic_name']);
		}

		Db::$db->insert(
			'',
			'{db_prefix}polls',
			[
				'id_poll' => 'int',
				'question' => 'string-255',
				'voting_locked' => 'int',
				'max_votes' => 'int',
				'expire_time' => 'int',
				'hide_results' => 'int',
				'change_vote' => 'int',
				'guest_vote' => 'int',
				'num_guest_voters' => 'int',
				'reset_poll' => 'int',
				'id_member' => 'int',
				'poster_name' => 'string-255',
			],
			[
				$row['id_poll'],
				Lang::$txt['salvaged_poll_question'],
				1,
				0,
				0,
				0,
				0,
				0,
				0,
				0,
				$row['id_poster'],
				$row['poster_name'],
			],
			[],
		);
	}

	/**
	 * Callback to fix polls that have no topic.
	 */
	protected function fixMissingPollTopics($row): void
	{
		// Only if we don't have a reasonable idea of where to put it.
		if ($row['id_board'] == 0) {
			$this->createSalvageArea();
			$row['id_board'] = $_SESSION['salvageBoardID'] = $this->salvage_board;
		}

		$row['poster_name'] = !empty($row['poster_name']) ? $row['poster_name'] : Lang::$txt['guest'];

		$newMessageID = Db::$db->insert(
			'',
			'{db_prefix}messages',
			[
				'id_board' => 'int',
				'id_topic' => 'int',
				'poster_time' => 'int',
				'id_member' => 'int',
				'subject' => 'string-255',
				'poster_name' => 'string-255',
				'poster_email' => 'string-255',
				'poster_ip' => 'inet',
				'smileys_enabled' => 'int',
				'body' => 'string-65534',
				'icon' => 'string-16',
				'approved' => 'int',
			],
			[
				$row['id_board'],
				0,
				time(),
				$row['id_member'],
				Lang::$txt['salvaged_poll_topic_name'],
				$row['poster_name'],
				'',
				'127.0.0.1',
				1,
				Lang::$txt['salvaged_poll_message_body'],
				'xx',
				1,
			],
			['id_msg'],
			1,
		);

		$newTopicID = Db::$db->insert(
			'',
			'{db_prefix}topics',
			[
				'id_board' => 'int',
				'id_poll' => 'int',
				'id_member_started' => 'int',
				'id_member_updated' => 'int',
				'id_first_msg' => 'int',
				'id_last_msg' => 'int',
				'num_replies' => 'int',
			],
			[
				$row['id_board'],
				$row['id_poll'],
				$row['id_member'],
				$row['id_member'],
				$newMessageID,
				$newMessageID,
				0,
			],
			['id_topic'],
			1,
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_topic = {int:newTopicID}, id_board = {int:id_board}
			WHERE id_msg = {int:newMessageID}',
			[
				'id_board' => $row['id_board'],
				'newTopicID' => $newTopicID,
				'newMessageID' => $newMessageID,
			],
		);

		Logging::updateStats('subject', $newTopicID, Lang::$txt['salvaged_poll_topic_name']);
	}

	/**
	 * Callback to fix missing first and last message IDs for a topic.
	 */
	protected function fixTopicStats($row): bool
	{
		$row['firstmsg_approved'] = (int) $row['firstmsg_approved'];
		$row['myid_first_msg'] = (int) $row['myid_first_msg'];
		$row['myid_last_msg'] = (int) $row['myid_last_msg'];

		// Not really a problem?
		if ($row['id_first_msg'] == $row['myid_first_msg'] && $row['id_last_msg'] == $row['myid_last_msg'] && $row['approved'] == $row['firstmsg_approved']) {
			return false;
		}

		$memberStartedID = (int) Board::getMsgMemberID($row['myid_first_msg']);
		$memberUpdatedID = (int) Board::getMsgMemberID($row['myid_last_msg']);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_first_msg = {int:myid_first_msg},
				id_member_started = {int:memberStartedID}, id_last_msg = {int:myid_last_msg},
				id_member_updated = {int:memberUpdatedID}, approved = {int:firstmsg_approved}
			WHERE id_topic = {int:topic_id}',
			[
				'myid_first_msg' => $row['myid_first_msg'],
				'memberStartedID' => $memberStartedID,
				'myid_last_msg' => $row['myid_last_msg'],
				'memberUpdatedID' => $memberUpdatedID,
				'firstmsg_approved' => $row['firstmsg_approved'],
				'topic_id' => $row['id_topic'],
			],
		);

		return true;
	}

	/**
	 * Callback to get a message about missing first and last message IDs for a
	 * topic.
	 */
	protected function topicStatsMessage($row): bool
	{
		// A pretend error?
		if ($row['id_first_msg'] == $row['myid_first_msg'] && $row['id_last_msg'] == $row['myid_last_msg'] && $row['approved'] == $row['firstmsg_approved']) {
			return false;
		}

		if ($row['id_first_msg'] != $row['myid_first_msg']) {
			Utils::$context['repair_errors'][] = sprintf(Lang::$txt['repair_topic_wrong_first_id'], $row['id_topic'], $row['id_first_msg']);
		}

		if ($row['id_last_msg'] != $row['myid_last_msg']) {
			Utils::$context['repair_errors'][] = sprintf(Lang::$txt['repair_topic_wrong_last_id'], $row['id_topic'], $row['id_last_msg']);
		}

		if ($row['approved'] != $row['firstmsg_approved']) {
			Utils::$context['repair_errors'][] = sprintf(Lang::$txt['repair_topic_wrong_approval'], $row['id_topic']);
		}

		return true;
	}

	/**
	 * Callback to fix the recorded number of replies to a topic.
	 */
	protected function fixTopicStats2($row): bool
	{
		$row['my_num_replies'] = (int) $row['my_num_replies'];

		// Not really a problem?
		if ($row['my_num_replies'] == $row['num_replies']) {
			return false;
		}

		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET num_replies = {int:my_num_replies}
			WHERE id_topic = {int:topic_id}',
			[
				'my_num_replies' => $row['my_num_replies'],
				'topic_id' => $row['id_topic'],
			],
		);

		return true;
	}

	/**
	 * Callback to get a message about an incorrect record of the number of
	 * replies to a topic.
	 */
	protected function topicStatsMessage2($row): bool
	{
		// Just joking?
		if ($row['my_num_replies'] == $row['num_replies']) {
			return false;
		}

		if ($row['num_replies'] != $row['my_num_replies']) {
			Utils::$context['repair_errors'][] = sprintf(Lang::$txt['repair_topic_wrong_replies'], $row['id_topic'], $row['num_replies']);
		}

		return true;
	}

	/**
	 * Callback to fix the recorded number of unapproved replies to a topic.
	 */
	protected function fixTopicStats3($row): void
	{
		$row['my_unapproved_posts'] = (int) $row['my_unapproved_posts'];

		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET unapproved_posts = {int:my_unapproved_posts}
			WHERE id_topic = {int:topic_id}',
			[
				'my_unapproved_posts' => $row['my_unapproved_posts'],
				'topic_id' => $row['id_topic'],
			],
		);
	}

	/**
	 * Callback to give a home to topics that have no board.
	 */
	protected function fixMissingBoards($row): void
	{
		$this->createSalvageArea();

		$row['my_num_topics'] = (int) $row['my_num_topics'];
		$row['my_num_posts'] = (int) $row['my_num_posts'];

		$newBoardID = Db::$db->insert(
			'',
			'{db_prefix}boards',
			['id_cat' => 'int', 'name' => 'string', 'description' => 'string', 'num_topics' => 'int', 'num_posts' => 'int', 'member_groups' => 'string'],
			[$this->salvage_category, Lang::$txt['salvaged_board_name'], Lang::$txt['salvaged_board_description'], $row['my_num_topics'], $row['my_num_posts'], '1'],
			['id_board'],
			1,
		);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_board = {int:newBoardID}
			WHERE id_board = {int:board_id}',
			[
				'newBoardID' => $newBoardID,
				'board_id' => $row['id_board'],
			],
		);
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_board = {int:newBoardID}
			WHERE id_board = {int:board_id}',
			[
				'newBoardID' => $newBoardID,
				'board_id' => $row['id_board'],
			],
		);
	}

	/**
	 * Callback to give a home to boards that have no category.
	 */
	protected function fixMissingCategories($cats): void
	{
		$this->createSalvageArea();

		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET id_cat = {int:salvage_category}
			WHERE id_cat IN ({array_int:categories})',
			[
				'salvage_category' => $this->salvage_category,
				'categories' => $cats,
			],
		);
	}

	/**
	 * Callback to give an author to messages that don't have one.
	 */
	protected function fixMissingPosters($msgs): void
	{
		Db::$db->query(
			'',
			'UPDATE {db_prefix}messages
			SET id_member = {int:guest_id}
			WHERE id_msg IN ({array_int:msgs})',
			[
				'msgs' => $msgs,
				'guest_id' => 0,
			],
		);
	}

	/**
	 * Callback to let our salvage board adopt orphaned child boards.
	 */
	protected function fixMissingParents($parents): void
	{
		$this->createSalvageArea();
		$_SESSION['salvageBoardID'] = $this->salvage_board;

		Db::$db->query(
			'',
			'UPDATE {db_prefix}boards
			SET id_parent = {int:salvage_board}, id_cat = {int:salvage_category}, child_level = 1
			WHERE id_parent IN ({array_int:parents})',
			[
				'salvage_board' => $this->salvage_board,
				'salvage_category' => $this->salvage_category,
				'parents' => $parents,
			],
		);
	}

	/**
	 * Callback to remove non-existent polls from topics.
	 */
	protected function fixMissingPolls($polls): void
	{
		Db::$db->query(
			'',
			'UPDATE {db_prefix}topics
			SET id_poll = 0
			WHERE id_poll IN ({array_int:polls})',
			[
				'polls' => $polls,
			],
		);
	}

	/**
	 * Callback to remove broken links to topics from calendar events.
	 */
	protected function fixMissingCaledarTopics($events): void
	{
		Db::$db->query(
			'',
			'UPDATE {db_prefix}calendar
			SET id_topic = 0, id_board = 0
			WHERE id_topic IN ({array_int:events})',
			[
				'events' => $events,
			],
		);
	}

	/**
	 * Callback to remove log_topics entries for non-existent topics.
	 */
	protected function fixMissingLogTopics($topics): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:topics})',
			[
				'topics' => $topics,
			],
		);
	}

	/**
	 * Callback to remove log_topics entries for non-existent members.
	 */
	protected function fixMissingLogTopicsMembers($members): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_topics
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
			],
		);
	}

	/**
	 * Callback to remove log_boards entries for non-existent boards.
	 */
	protected function fixMissingLogBoards($boards): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_boards
			WHERE id_board IN ({array_int:boards})',
			[
				'boards' => $boards,
			],
		);
	}

	/**
	 * Callback to remove log_boards entries for non-existent members.
	 */
	protected function fixMissingLogBoardsMembers($members): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_boards
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
			],
		);
	}

	/**
	 * Callback to remove log_mark_read entries for non-existent boards.
	 */
	protected function fixMissingLogMarkRead($boards): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_mark_read
			WHERE id_board IN ({array_int:boards})',
			[
				'boards' => $boards,
			],
		);
	}

	/**
	 * Callback to remove log_mark_read entries for non-existent members.
	 */
	protected function fixMissingLogMarkReadMembers($members): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_mark_read
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
			],
		);
	}

	/**
	 * Callback to remove non-existent personal messages from the recipients'
	 * inboxes.
	 */
	protected function fixMissingPMs($pms): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm IN ({array_int:pms})',
			[
				'pms' => $pms,
			],
		);
	}

	/**
	 * Callback to remove non-existent recipients from personal messages.
	 */
	protected function fixMissingRecipients($members): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
			],
		);
	}

	/**
	 * Callback to fix the assigned authorship of PMs from non-existent senders.
	 * Specifically, such PMs will be shown to have been sent from a guest.
	 */
	protected function fixMissingSenders($guestMessages): void
	{
		Db::$db->query(
			'',
			'UPDATE {db_prefix}personal_messages
			SET id_member_from = 0
			WHERE id_pm IN ({array_int:guestMessages})',
			[
				'guestMessages' => $guestMessages,
			],
		);
	}

	/**
	 * Callback to remove log_notify entries for non-existent members.
	 */
	protected function fixMissingNotifyMembers($members): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_notify
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
			],
		);
	}

	/**
	 * Callback to fix missing log_search_subjects entries for a topic.
	 */
	protected function fixMissingCachedSubject($result): void
	{
		$inserts = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			foreach (Utils::text2words($row['subject']) as $word) {
				$inserts[] = [$word, $row['id_topic']];
			}

			if (count($inserts) > 500) {
				Db::$db->insert(
					'ignore',
					'{db_prefix}log_search_subjects',
					['word' => 'string', 'id_topic' => 'int'],
					$inserts,
					['word', 'id_topic'],
				);

				$inserts = [];
			}
		}

		if (!empty($inserts)) {
			Db::$db->insert(
				'ignore',
				'{db_prefix}log_search_subjects',
				['word' => 'string', 'id_topic' => 'int'],
				$inserts,
				['word', 'id_topic'],
			);
		}
	}

	/**
	 * Callback to get a message about missing log_search_subjects entries for a
	 * topic.
	 */
	protected function missingCachedSubjectMessage($row): bool
	{
		if (count(Utils::text2words($row['subject'])) != 0) {
			Utils::$context['repair_errors'][] = sprintf(Lang::$txt['repair_missing_cached_subject'], $row['id_topic']);

			return true;
		}

		return false;
	}

	/**
	 * Callback to remove log_search_subjects entries for non-existent topics.
	 */
	protected function fixMissingTopicForCache($deleteTopics): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_search_subjects
			WHERE id_topic IN ({array_int:deleteTopics})',
			[
				'deleteTopics' => $deleteTopics,
			],
		);
	}

	/**
	 * Callback to remove poll votes made by non-existent members.
	 */
	protected function fixMissingMemberVote($members): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_polls
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
			],
		);
	}

	/**
	 * Callback to remove poll votes made in non-existent polls.
	 */
	protected function fixMissingLogPollVote($polls): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_polls
			WHERE id_poll IN ({array_int:polls})',
			[
				'polls' => $polls,
			],
		);
	}

	/**
	 * Callback to remove non-existent comments from reports.
	 */
	protected function fixReportMissingComments($reports): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_reported
			WHERE id_report IN ({array_int:reports})',
			[
				'reports' => $reports,
			],
		);
	}

	/**
	 * Callback to remove comments made on non-existent reports.
	 */
	protected function fixCommentMissingReport($reports): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_reported_comments
			WHERE id_report IN ({array_int:reports})',
			[
				'reports' => $reports,
			],
		);
	}

	/**
	 * Callback to remove requests to join a group made by non-existent members.
	 */
	protected function fixGroupRequestMissingMember($members): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_group_requests
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
			],
		);
	}

	/**
	 * Callback to remove requests to join non-existent groups.
	 */
	protected function fixGroupRequestMissingGroup($groups): void
	{
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_group_requests
			WHERE id_group IN ({array_int:groups})',
			[
				'groups' => $groups,
			],
		);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\RepairBoards::exportStatic')) {
	RepairBoards::exportStatic();
}

?>