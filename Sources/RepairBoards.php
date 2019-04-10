<?php

/**
 * This is here for the "repair any errors" feature in the admin center.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Finds or repairs errors in the database to fix possible problems.
 * Requires the admin_forum permission.
 * Calls createSalvageArea() to create a new board, if necessary.
 * Accessed by ?action=admin;area=repairboards.
 *
 * @uses repair_boards sub-template.
 */
function RepairBoards()
{
	global $txt, $context, $sourcedir, $salvageBoardID;

	isAllowedTo('admin_forum');

	// Try secure more memory.
	setMemoryLimit('128M');

	// Print out the top of the webpage.
	$context['page_title'] = $txt['admin_repair'];
	$context['sub_template'] = 'repair_boards';
	$context[$context['admin_menu_name']]['current_subsection'] = 'general';

	// Load the language file.
	loadLanguage('ManageMaintenance');

	// Make sure the tabs stay nice.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['maintain_title'],
		'help' => '',
		'description' => $txt['maintain_info'],
		'tabs' => array(),
	);

	// Start displaying errors without fixing them.
	if (isset($_GET['fixErrors']))
		checkSession('get');

	// Will want this.
	loadForumTests();

	// Giant if/else. The first displays the forum errors if a variable is not set and asks
	// if you would like to continue, the other fixes the errors.
	if (!isset($_GET['fixErrors']))
	{
		$context['error_search'] = true;
		$context['repair_errors'] = array();
		$context['to_fix'] = findForumErrors();

		if (!empty($context['to_fix']))
		{
			$_SESSION['repairboards_to_fix'] = $context['to_fix'];
			$_SESSION['repairboards_to_fix2'] = null;

			if (empty($context['repair_errors']))
				$context['repair_errors'][] = '???';
		}

		// Need a token here.
		createToken('admin-repairboards', 'request');
	}
	else
	{
		// Validate the token, create a new one and tell the not done template.
		validateToken('admin-repairboards', 'request');
		createToken('admin-repairboards', 'request');
		$context['not_done_token'] = 'admin-repairboards';

		$context['error_search'] = false;
		$context['to_fix'] = isset($_SESSION['repairboards_to_fix']) ? $_SESSION['repairboards_to_fix'] : array();

		require_once($sourcedir . '/Subs-Boards.php');

		// Actually do the fix.
		findForumErrors(true);

		// Note that we've changed everything possible ;)
		updateSettings(array(
			'settings_updated' => time(),
		));
		updateStats('message');
		updateStats('topic');
		updateSettings(array(
			'calendar_updated' => time(),
		));

		// If we created a salvage area, we may need to recount stats properly.
		if (!empty($salvageBoardID) || !empty($_SESSION['salvageBoardID']))
		{
			unset($_SESSION['salvageBoardID']);
			$context['redirect_to_recount'] = true;
			createToken('admin-maint');
		}

		$_SESSION['repairboards_to_fix'] = null;
		$_SESSION['repairboards_to_fix2'] = null;

		// We are done at this point, dump the token,
		validateToken('admin-repairboards', 'request', false);
	}
}

/**
 * Show the not_done template to avoid CGI timeouts and similar.
 * Called when 3 or more seconds have passed while searching for errors.
 * If max_substep is set, $_GET['substep'] / $max_substep is the percent
 * done this step is.
 *
 * @param array $to_fix An array of information about what to fix
 * @param string $current_step_description The description of the current step
 * @param int $max_substep The maximum substep to reach before pausing
 * @param bool $force Whether to force pausing even if we don't really need to
 */
function pauseRepairProcess($to_fix, $current_step_description, $max_substep = 0, $force = false)
{
	global $context, $txt, $time_start, $db_temp_cache, $db_cache;

	// More time, I need more time!
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Errr, wait.  How much time has this taken already?
	if (!$force && (time() - $time_start) < 3)
		return;

	// Restore the query cache if interested.
	if (!empty($db_temp_cache))
		$db_cache = $db_temp_cache;

	$context['continue_get_data'] = '?action=admin;area=repairboards' . (isset($_GET['fixErrors']) ? ';fixErrors' : '') . ';step=' . $_GET['step'] . ';substep=' . $_GET['substep'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Change these two if more steps are added!
	if (empty($max_substep))
		$context['continue_percent'] = round(($_GET['step'] * 100) / $context['total_steps']);
	else
		$context['continue_percent'] = round((($_GET['step'] + ($_GET['substep'] / $max_substep)) * 100) / $context['total_steps']);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	// What about substeps?
	$context['substep_enabled'] = $max_substep != 0;
	$context['substep_title'] = sprintf($txt['repair_currently_' . (isset($_GET['fixErrors']) ? 'fixing' : 'checking')], (isset($txt['repair_operation_' . $current_step_description]) ? $txt['repair_operation_' . $current_step_description] : $current_step_description));
	$context['substep_continue_percent'] = $max_substep == 0 ? 0 : round(($_GET['substep'] * 100) / $max_substep, 1);

	$_SESSION['repairboards_to_fix'] = $to_fix;
	$_SESSION['repairboards_to_fix2'] = $context['repair_errors'];

	obExit();
}

/**
 * Load up all the tests we might want to do ;)
 */
function loadForumTests()
{
	global $errorTests, $smcFunc, $txt, $context;

	/* Here this array is defined like so:
		string check_query:	Query to be executed when testing if errors exist.
		string check_type:	Defines how it knows if a problem was found. If set to count looks for the first variable from check_query
					being > 0. Anything else it looks for some results. If not set assumes you want results.
		string fix_it_query:	When doing fixes if an error was detected this query is executed to "fix" it.
		string fix_query:	The query to execute to get data when doing a fix. If not set check_query is used again.
		array fix_collect:	This array is used if the fix is basically gathering all broken ids and then doing something with it.
			- string index:		The value returned from the main query and passed to the processing function.
			- process:		A function passed an array of ids to execute the fix on.
		function fix_processing:
					Function called for each row returned from fix_query to execute whatever fixes are required.
		function fix_full_processing:
					As above but does the while loop and everything itself - except the freeing.
		array force_fix:	If this is set then the error types included within this array will also be assumed broken.
					Note: At the moment only processes these if they occur after the primary error in the array.
	*/

	// This great array contains all of our error checks, fixes, etc etc etc.
	$errorTests = array(
		// Make a last-ditch-effort check to get rid of topics with zeros..
		'zero_topics' => array(
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
		),
		// ... and same with messages.
		'zero_messages' => array(
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
		),
		// Find messages that don't have existing topics.
		'missing_topics' => array(
			'substeps' => array(
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}messages'
			),
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
			'fix_processing' => function($row) use ($smcFunc)
			{
				global $salvageBoardID;

				// Only if we don't have a reasonable idea of where to put it.
				if ($row['id_board'] == 0)
				{
					createSalvageArea();
					$row['id_board'] = $_SESSION['salvageBoardID'] = (int) $salvageBoardID;
				}

				// Make sure that no topics claim the first/last message as theirs.
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_first_msg = 0
					WHERE id_first_msg = {int:id_first_msg}',
					array(
						'id_first_msg' => $row['myid_first_msg'],
					)
				);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_last_msg = 0
					WHERE id_last_msg = {int:id_last_msg}',
					array(
						'id_last_msg' => $row['myid_last_msg'],
					)
				);

				$memberStartedID = (int) getMsgMemberID($row['myid_first_msg']);
				$memberUpdatedID = (int) getMsgMemberID($row['myid_last_msg']);

				$newTopicID = $smcFunc['db_insert']('',
					'{db_prefix}topics',
					array(
						'id_board' => 'int',
						'id_member_started' => 'int',
						'id_member_updated' => 'int',
						'id_first_msg' => 'int',
						'id_last_msg' => 'int',
						'num_replies' => 'int'
					),
					array(
						$row['id_board'],
						$memberStartedID,
						$memberUpdatedID,
						$row['myid_first_msg'],
						$row['myid_last_msg'],
						$row['my_num_replies']
					),
					array('id_topic'),
					1
				);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET id_topic = {int:newTopicID}, id_board = {int:board_id}
					WHERE id_topic = {int:topic_id}',
					array(
						'board_id' => $row['id_board'],
						'topic_id' => $row['id_topic'],
						'newTopicID' => $newTopicID,
					)
				);
			},
			'force_fix' => array('stats_topics'),
			'messages' => array('repair_missing_topics', 'id_msg', 'id_topic'),
		),
		// Find topics with no messages.
		'missing_messages' => array(
			'substeps' => array(
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics'
			),
			'check_query' => '
				SELECT t.id_topic, COUNT(m.id_msg) AS num_msg
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY t.id_topic
				HAVING COUNT(m.id_msg) = 0',
			// Remove all topics that have zero messages in the messages table.
			'fix_collect' => array(
				'index' => 'id_topic',
				'process' => function($topics) use ($smcFunc)
				{
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}topics
						WHERE id_topic IN ({array_int:topics})',
						array(
							'topics' => $topics,
						)
					);
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_topics
						WHERE id_topic IN ({array_int:topics})',
						array(
							'topics' => $topics,
						)
					);
				},
			),
			'messages' => array('repair_missing_messages', 'id_topic'),
		),
		'poll_options_missing_poll' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}poll_choices'
			),
			'check_query' => '
				SELECT o.id_poll, count(*) as amount, t.id_topic, t.id_board, t.id_member_started AS id_poster, m.member_name AS poster_name
				FROM {db_prefix}poll_choices AS o
					LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = o.id_poll)
					LEFT JOIN {db_prefix}topics AS t ON (t.id_poll = o.id_poll)
					LEFT JOIN {db_prefix}members AS m ON (m.id_member = t.id_member_started)
				WHERE o.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND p.id_poll IS NULL
				GROUP BY o.id_poll, t.id_topic, t.id_board, t.id_member_started, m.member_name',
			'fix_processing' => function($row) use ($smcFunc, $txt)
			{
				global $salvageBoardID;

				$row['poster_name'] = !empty($row['poster_name']) ? $row['poster_name'] : $txt['guest'];
				$row['id_poster'] = !empty($row['id_poster']) ? $row['id_poster'] : 0;

				if (empty($row['id_board']))
				{
					// Only if we don't have a reasonable idea of where to put it.
					createSalvageArea();
					$row['id_board'] = $_SESSION['salvageBoardID'] = (int) $salvageBoardID;
				}

				if (empty($row['id_topic']))
				{
					$newMessageID = $smcFunc['db_insert']('',
						'{db_prefix}messages',
						array(
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
						),
						array(
							$row['id_board'],
							0,
							time(),
							$row['id_poster'],
							$txt['salvaged_poll_topic_name'],
							$row['poster_name'],
							$txt['salvaged_poll_topic_name'],
							'127.0.0.1',
							1,
							$txt['salvaged_poll_message_body'],
							'xx',
							1,
						),
						array('id_msg'),
						1
					);

					$row['id_topic'] = $smcFunc['db_insert']('',
						'{db_prefix}topics',
						array(
							'id_board' => 'int',
							'id_poll' => 'int',
							'id_member_started' => 'int',
							'id_member_updated' => 'int',
							'id_first_msg' => 'int',
							'id_last_msg' => 'int',
							'num_replies' => 'int',
						),
						array(
							$row['id_board'],
							$row['id_poll'],
							$row['id_poster'],
							$row['id_poster'],
							$newMessageID,
							$newMessageID,
							0,
						),
						array('id_topic'),
						1
					);

					$smcFunc['db_query']('', '
						UPDATE {db_prefix}messages
						SET id_topic = {int:newTopicID}, id_board = {int:id_board}
						WHERE id_msg = {int:newMessageID}',
						array(
							'id_board' => $row['id_board'],
							'newTopicID' => $row['id_topic'],
							'newMessageID' => $newMessageID,
						)
					);

					updateStats('subject', $row['id_topic'], $txt['salvaged_poll_topic_name']);
				}

				$smcFunc['db_insert']('',
					'{db_prefix}polls',
					array(
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
					),
					array(
						$row['id_poll'],
						$txt['salvaged_poll_question'],
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
					),
					array()
				);
			},
			'force_fix' => array('stats_topics'),
			'messages' => array('repair_poll_options_missing_poll', 'id_poll', 'amount'),
		),
		'polls_missing_topics' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}polls'
			),
			'check_query' => '
				SELECT p.id_poll, p.id_member, p.poster_name, t.id_board
				FROM {db_prefix}polls AS p
					LEFT JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
				WHERE p.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND t.id_poll IS NULL',
			'fix_processing' => function($row) use ($smcFunc, $txt)
			{
				global $salvageBoardID;

				// Only if we don't have a reasonable idea of where to put it.
				if ($row['id_board'] == 0)
				{
					createSalvageArea();
					$row['id_board'] = $_SESSION['salvageBoardID'] = (int) $salvageBoardID;
				}

				$row['poster_name'] = !empty($row['poster_name']) ? $row['poster_name'] : $txt['guest'];

				$newMessageID = $smcFunc['db_insert']('',
					'{db_prefix}messages',
					array(
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
					),
					array(
						$row['id_board'],
						0,
						time(),
						$row['id_member'],
						$txt['salvaged_poll_topic_name'],
						$row['poster_name'],
						'',
						'127.0.0.1',
						1,
						$txt['salvaged_poll_message_body'],
						'xx',
						1,
					),
					array('id_msg'),
					1
				);

				$newTopicID = $smcFunc['db_insert']('',
					'{db_prefix}topics',
					array(
						'id_board' => 'int',
						'id_poll' => 'int',
						'id_member_started' => 'int',
						'id_member_updated' => 'int',
						'id_first_msg' => 'int',
						'id_last_msg' => 'int',
						'num_replies' => 'int',
					),
					array(
						$row['id_board'],
						$row['id_poll'],
						$row['id_member'],
						$row['id_member'],
						$newMessageID,
						$newMessageID,
						0,
					),
					array('id_topic'),
					1
				);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET id_topic = {int:newTopicID}, id_board = {int:id_board}
					WHERE id_msg = {int:newMessageID}',
					array(
						'id_board' => $row['id_board'],
						'newTopicID' => $newTopicID,
						'newMessageID' => $newMessageID,
					)
				);

				updateStats('subject', $newTopicID, $txt['salvaged_poll_topic_name']);
			},
			'force_fix' => array('stats_topics'),
			'messages' => array('repair_polls_missing_topics', 'id_poll', 'id_topic'),
		),
		'stats_topics' => array(
			'substeps' => array(
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics'
			),
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
			'fix_processing' => function($row) use ($smcFunc)
			{
				$row['firstmsg_approved'] = (int) $row['firstmsg_approved'];
				$row['myid_first_msg'] = (int) $row['myid_first_msg'];
				$row['myid_last_msg'] = (int) $row['myid_last_msg'];

				// Not really a problem?
				if ($row['id_first_msg'] == $row['myid_first_msg'] && $row['id_last_msg'] == $row['myid_last_msg'] && $row['approved'] == $row['firstmsg_approved'])
					return false;

				$memberStartedID = (int) getMsgMemberID($row['myid_first_msg']);
				$memberUpdatedID = (int) getMsgMemberID($row['myid_last_msg']);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_first_msg = {int:myid_first_msg},
						id_member_started = {int:memberStartedID}, id_last_msg = {int:myid_last_msg},
						id_member_updated = {int:memberUpdatedID}, approved = {int:firstmsg_approved}
					WHERE id_topic = {int:topic_id}',
					array(
						'myid_first_msg' => $row['myid_first_msg'],
						'memberStartedID' => $memberStartedID,
						'myid_last_msg' => $row['myid_last_msg'],
						'memberUpdatedID' => $memberUpdatedID,
						'firstmsg_approved' => $row['firstmsg_approved'],
						'topic_id' => $row['id_topic'],
					)
				);
			},
			'message_function' => function($row) use ($txt, &$context)
			{
				// A pretend error?
				if ($row['id_first_msg'] == $row['myid_first_msg'] && $row['id_last_msg'] == $row['myid_last_msg'] && $row['approved'] == $row['firstmsg_approved'])
					return false;

				if ($row['id_first_msg'] != $row['myid_first_msg'])
					$context['repair_errors'][] = sprintf($txt['repair_topic_wrong_first_id'], $row['id_topic'], $row['id_first_msg']);
				if ($row['id_last_msg'] != $row['myid_last_msg'])
					$context['repair_errors'][] = sprintf($txt['repair_topic_wrong_last_id'], $row['id_topic'], $row['id_last_msg']);
				if ($row['approved'] != $row['firstmsg_approved'])
					$context['repair_errors'][] = sprintf($txt['repair_topic_wrong_approval'], $row['id_topic']);

				return true;
			},
		),
		// Find topics with incorrect num_replies.
		'stats_topics2' => array(
			'substeps' => array(
				'step_size' => 300,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics'
			),
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
			'fix_processing' => function($row)
			{
				global $smcFunc;
				$row['my_num_replies'] = (int) $row['my_num_replies'];

				// Not really a problem?
				if ($row['my_num_replies'] == $row['num_replies'])
					return false;

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET num_replies = {int:my_num_replies}
					WHERE id_topic = {int:topic_id}',
					array(
						'my_num_replies' => $row['my_num_replies'],
						'topic_id' => $row['id_topic'],
					)
				);
			},
			'message_function' => function($row)
			{
				global $txt, $context;

				// Just joking?
				if ($row['my_num_replies'] == $row['num_replies'])
					return false;

				if ($row['num_replies'] != $row['my_num_replies'])
					$context['repair_errors'][] = sprintf($txt['repair_topic_wrong_replies'], $row['id_topic'], $row['num_replies']);

				return true;
			},
		),
		// Find topics with incorrect unapproved_posts.
		'stats_topics3' => array(
			'substeps' => array(
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics'
			),
			'check_query' => '
				SELECT
					t.id_topic, t.unapproved_posts, COUNT(mu.id_msg) AS my_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = 0)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY t.id_topic, t.unapproved_posts
				HAVING unapproved_posts != COUNT(mu.id_msg)
				ORDER BY t.id_topic',
			'fix_processing' => function($row)
			{
				global $smcFunc;
				$row['my_unapproved_posts'] = (int) $row['my_unapproved_posts'];

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET unapproved_posts = {int:my_unapproved_posts}
					WHERE id_topic = {int:topic_id}',
					array(
						'my_unapproved_posts' => $row['my_unapproved_posts'],
						'topic_id' => $row['id_topic'],
					)
				);
			},
			'messages' => array('repair_topic_wrong_unapproved_number', 'id_topic', 'unapproved_posts'),
		),
		// Find topics with nonexistent boards.
		'missing_boards' => array(
			'substeps' => array(
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics'
			),
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
			'fix_processing' => function($row)
			{
				global $smcFunc, $salvageCatID, $txt;
				createSalvageArea();

				$row['my_num_topics'] = (int) $row['my_num_topics'];
				$row['my_num_posts'] = (int) $row['my_num_posts'];

				$newBoardID = $smcFunc['db_insert']('',
					'{db_prefix}boards',
					array('id_cat' => 'int', 'name' => 'string', 'description' => 'string', 'num_topics' => 'int', 'num_posts' => 'int', 'member_groups' => 'string'),
					array($salvageCatID, $txt['salvaged_board_name'], $txt['salvaged_board_description'], $row['my_num_topics'], $row['my_num_posts'], '1'),
					array('id_board'),
					1
				);

				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics
					SET id_board = {int:newBoardID}
					WHERE id_board = {int:board_id}',
					array(
						'newBoardID' => $newBoardID,
						'board_id' => $row['id_board'],
					)
				);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET id_board = {int:newBoardID}
					WHERE id_board = {int:board_id}',
					array(
						'newBoardID' => $newBoardID,
						'board_id' => $row['id_board'],
					)
				);
			},
			'messages' => array('repair_missing_boards', 'id_topic', 'id_board'),
		),
		// Find boards with nonexistent categories.
		'missing_categories' => array(
			'check_query' => '
				SELECT b.id_board, b.id_cat
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE c.id_cat IS NULL
				ORDER BY b.id_cat, b.id_board',
			'fix_collect' => array(
				'index' => 'id_cat',
				'process' => function($cats)
				{
					global $smcFunc, $salvageCatID;
					createSalvageArea();
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}boards
						SET id_cat = {int:salvageCatID}
						WHERE id_cat IN ({array_int:categories})',
						array(
							'salvageCatID' => $salvageCatID,
							'categories' => $cats,
						)
					);
				},
			),
			'messages' => array('repair_missing_categories', 'id_board', 'id_cat'),
		),
		// Find messages with nonexistent members.
		'missing_posters' => array(
			'substeps' => array(
				'step_size' => 2000,
				'step_max' => '
					SELECT MAX(id_msg)
					FROM {db_prefix}messages'
			),
			'check_query' => '
				SELECT m.id_msg, m.id_member
				FROM {db_prefix}messages AS m
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE mem.id_member IS NULL
					AND m.id_member != 0
					AND m.id_msg BETWEEN {STEP_LOW} AND {STEP_HIGH}
				ORDER BY m.id_msg',
			// Last step-make sure all non-guest posters still exist.
			'fix_collect' => array(
				'index' => 'id_msg',
				'process' => function($msgs)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}messages
						SET id_member = {int:guest_id}
						WHERE id_msg IN ({array_int:msgs})',
						array(
							'msgs' => $msgs,
							'guest_id' => 0,
						)
					);
				},
			),
			'messages' => array('repair_missing_posters', 'id_msg', 'id_member'),
		),
		// Find boards with nonexistent parents.
		'missing_parents' => array(
			'check_query' => '
				SELECT b.id_board, b.id_parent
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}boards AS p ON (p.id_board = b.id_parent)
				WHERE b.id_parent != 0
					AND (p.id_board IS NULL OR p.id_board = b.id_board)
				ORDER BY b.id_parent, b.id_board',
			'fix_collect' => array(
				'index' => 'id_parent',
				'process' => function($parents)
				{
					global $smcFunc, $salvageBoardID, $salvageCatID;

					createSalvageArea();
					$_SESSION['salvageBoardID'] = (int) $salvageBoardID;

					$smcFunc['db_query']('', '
						UPDATE {db_prefix}boards
						SET id_parent = {int:salvageBoardID}, id_cat = {int:salvageCatID}, child_level = 1
						WHERE id_parent IN ({array_int:parents})',
						array(
							'salvageBoardID' => $salvageBoardID,
							'salvageCatID' => $salvageCatID,
							'parents' => $parents,
						)
					);
				},
			),
			'messages' => array('repair_missing_parents', 'id_board', 'id_parent'),
		),
		'missing_polls' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}topics'
			),
			'check_query' => '
				SELECT t.id_poll, t.id_topic
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = t.id_poll)
				WHERE t.id_poll != 0
					AND t.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND p.id_poll IS NULL',
			'fix_collect' => array(
				'index' => 'id_poll',
				'process' => function($polls)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}topics
						SET id_poll = 0
						WHERE id_poll IN ({array_int:polls})',
						array(
							'polls' => $polls,
						)
					);
				},
			),
			'messages' => array('repair_missing_polls', 'id_topic', 'id_poll'),
		),
		'missing_calendar_topics' => array(
			'substeps' => array(
				'step_size' => 1000,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}calendar'
			),
			'check_query' => '
				SELECT cal.id_topic, cal.id_event
				FROM {db_prefix}calendar AS cal
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = cal.id_topic)
				WHERE cal.id_topic != 0
					AND cal.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND t.id_topic IS NULL
				ORDER BY cal.id_topic',
			'fix_collect' => array(
				'index' => 'id_topic',
				'process' => function($events)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}calendar
						SET id_topic = 0, id_board = 0
						WHERE id_topic IN ({array_int:events})',
						array(
							'events' => $events,
						)
					);
				},
			),
			'messages' => array('repair_missing_calendar_topics', 'id_event', 'id_topic'),
		),
		'missing_log_topics' => array(
			'substeps' => array(
				'step_size' => 150,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_topics'
			),
			'check_query' => '
				SELECT lt.id_topic
				FROM {db_prefix}log_topics AS lt
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lt.id_topic)
				WHERE t.id_topic IS NULL
					AND lt.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}',
			'fix_collect' => array(
				'index' => 'id_topic',
				'process' => function($topics)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_topics
						WHERE id_topic IN ({array_int:topics})',
						array(
							'topics' => $topics,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_topics', 'id_topic'),
		),
		'missing_log_topics_members' => array(
			'substeps' => array(
				'step_size' => 150,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_topics'
			),
			'check_query' => '
				SELECT lt.id_member
				FROM {db_prefix}log_topics AS lt
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lt.id_member)
				WHERE mem.id_member IS NULL
					AND lt.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lt.id_member',
			'fix_collect' => array(
				'index' => 'id_member',
				'process' => function($members)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_topics
						WHERE id_member IN ({array_int:members})',
						array(
							'members' => $members,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_topics_members', 'id_member'),
		),
		'missing_log_boards' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_boards'
			),
			'check_query' => '
				SELECT lb.id_board
				FROM {db_prefix}log_boards AS lb
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lb.id_board)
				WHERE b.id_board IS NULL
					AND lb.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lb.id_board',
			'fix_collect' => array(
				'index' => 'id_board',
				'process' => function($boards)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_boards
						WHERE id_board IN ({array_int:boards})',
						array(
							'boards' => $boards,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_boards', 'id_board'),
		),
		'missing_log_boards_members' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_boards'
			),
			'check_query' => '
				SELECT lb.id_member
				FROM {db_prefix}log_boards AS lb
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lb.id_member)
				WHERE mem.id_member IS NULL
					AND lb.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lb.id_member',
			'fix_collect' => array(
				'index' => 'id_member',
				'process' => function($members) use ($smcFunc)
				{
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_boards
						WHERE id_member IN ({array_int:members})',
						array(
							'members' => $members,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_boards_members', 'id_member'),
		),
		'missing_log_mark_read' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_mark_read'
			),
			'check_query' => '
				SELECT lmr.id_board
				FROM {db_prefix}log_mark_read AS lmr
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lmr.id_board)
				WHERE b.id_board IS NULL
					AND lmr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lmr.id_board',
			'fix_collect' => array(
				'index' => 'id_board',
				'process' => function($boards) use ($smcFunc)
				{
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_mark_read
						WHERE id_board IN ({array_int:boards})',
						array(
							'boards' => $boards,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_mark_read', 'id_board'),
		),
		'missing_log_mark_read_members' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_mark_read'
			),
			'check_query' => '
				SELECT lmr.id_member
				FROM {db_prefix}log_mark_read AS lmr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lmr.id_member)
				WHERE mem.id_member IS NULL
					AND lmr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY lmr.id_member',
			'fix_collect' => array(
				'index' => 'id_member',
				'process' => function($members) use ($smcFunc)
				{
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_mark_read
						WHERE id_member IN ({array_int:members})',
						array(
							'members' => $members,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_mark_read_members', 'id_member'),
		),
		'missing_pms' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_pm)
					FROM {db_prefix}pm_recipients'
			),
			'check_query' => '
				SELECT pmr.id_pm
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				WHERE pm.id_pm IS NULL
					AND pmr.id_pm BETWEEN {STEP_LOW} AND {STEP_HIGH}
				GROUP BY pmr.id_pm',
			'fix_collect' => array(
				'index' => 'id_pm',
				'process' => function($pms) use ($smcFunc)
				{
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}pm_recipients
						WHERE id_pm IN ({array_int:pms})',
						array(
							'pms' => $pms,
						)
					);
				},
			),
			'messages' => array('repair_missing_pms', 'id_pm'),
		),
		'missing_recipients' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}pm_recipients'
			),
			'check_query' => '
				SELECT pmr.id_member
				FROM {db_prefix}pm_recipients AS pmr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
				WHERE pmr.id_member != 0
					AND pmr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL
				GROUP BY pmr.id_member',
			'fix_collect' => array(
				'index' => 'id_member',
				'process' => function($members)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}pm_recipients
						WHERE id_member IN ({array_int:members})',
						array(
							'members' => $members,
						)
					);
				},
			),
			'messages' => array('repair_missing_recipients', 'id_member'),
		),
		'missing_senders' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_pm)
					FROM {db_prefix}personal_messages'
			),
			'check_query' => '
				SELECT pm.id_pm, pm.id_member_from
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_member_from != 0
					AND pm.id_pm BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL',
			'fix_collect' => array(
				'index' => 'id_pm',
				'process' => function($guestMessages)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}personal_messages
						SET id_member_from = 0
						WHERE id_pm IN ({array_int:guestMessages})',
						array(
							'guestMessages' => $guestMessages,
						)
					);
				},
			),
			'messages' => array('repair_missing_senders', 'id_pm', 'id_member_from'),
		),
		'missing_notify_members' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_notify'
			),
			'check_query' => '
				SELECT ln.id_member
				FROM {db_prefix}log_notify AS ln
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
				WHERE ln.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL
				GROUP BY ln.id_member',
			'fix_collect' => array(
				'index' => 'id_member',
				'process' => function($members) use ($smcFunc)
				{
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_notify
						WHERE id_member IN ({array_int:members})',
						array(
							'members' => $members,
						)
					);
				},
			),
			'messages' => array('repair_missing_notify_members', 'id_member'),
		),
		'missing_cached_subject' => array(
			'substeps' => array(
				'step_size' => 100,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}topics'
			),
			'check_query' => '
				SELECT t.id_topic, fm.subject
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}log_search_subjects AS lss ON (lss.id_topic = t.id_topic)
				WHERE t.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lss.id_topic IS NULL',
			'fix_full_processing' => function($result)
			{
				global $smcFunc;

				$inserts = array();
				while ($row = $smcFunc['db_fetch_assoc']($result))
				{
					foreach (text2words($row['subject']) as $word)
						$inserts[] = array($word, $row['id_topic']);
					if (count($inserts) > 500)
					{
						$smcFunc['db_insert']('ignore',
							'{db_prefix}log_search_subjects',
							array('word' => 'string', 'id_topic' => 'int'),
							$inserts,
							array('word', 'id_topic')
						);
						$inserts = array();
					}
				}

				if (!empty($inserts))
					$smcFunc['db_insert']('ignore',
						'{db_prefix}log_search_subjects',
						array('word' => 'string', 'id_topic' => 'int'),
						$inserts,
						array('word', 'id_topic')
					);
			},
			'message_function' => function($row)
			{
				global $txt, $context;

				if (count(text2words($row['subject'])) != 0)
				{
					$context['repair_errors'][] = sprintf($txt['repair_missing_cached_subject'], $row['id_topic']);
					return true;
				}

				return false;
			},
		),
		'missing_topic_for_cache' => array(
			'substeps' => array(
				'step_size' => 50,
				'step_max' => '
					SELECT MAX(id_topic)
					FROM {db_prefix}log_search_subjects'
			),
			'check_query' => '
				SELECT lss.id_topic, lss.word
				FROM {db_prefix}log_search_subjects AS lss
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lss.id_topic)
				WHERE lss.id_topic BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND t.id_topic IS NULL',
			'fix_collect' => array(
				'index' => 'id_topic',
				'process' => function($deleteTopics)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_search_subjects
						WHERE id_topic IN ({array_int:deleteTopics})',
						array(
							'deleteTopics' => $deleteTopics,
						)
					);
				},
			),
			'messages' => array('repair_missing_topic_for_cache', 'word'),
		),
		'missing_member_vote' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_polls'
			),
			'check_query' => '
				SELECT lp.id_poll, lp.id_member
				FROM {db_prefix}log_polls AS lp
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lp.id_member)
				WHERE lp.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lp.id_member > 0
					AND mem.id_member IS NULL',
			'fix_collect' => array(
				'index' => 'id_member',
				'process' => function($members)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_polls
						WHERE id_member IN ({array_int:members})',
						array(
							'members' => $members,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_poll_member', 'id_poll', 'id_member'),
		),
		'missing_log_poll_vote' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_poll)
					FROM {db_prefix}log_polls'
			),
			'check_query' => '
				SELECT lp.id_poll, lp.id_member
				FROM {db_prefix}log_polls AS lp
					LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = lp.id_poll)
				WHERE lp.id_poll BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND p.id_poll IS NULL',
			'fix_collect' => array(
				'index' => 'id_poll',
				'process' => function($polls)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_polls
						WHERE id_poll IN ({array_int:polls})',
						array(
							'polls' => $polls,
						)
					);
				},
			),
			'messages' => array('repair_missing_log_poll_vote', 'id_member', 'id_poll'),
		),
		'report_missing_comments' => array(
			'substeps' => array(
				'step_size' => 500,
				'step_max' => '
					SELECT MAX(id_report)
					FROM {db_prefix}log_reported'
			),
			'check_query' => '
				SELECT lr.id_report, lr.subject
				FROM {db_prefix}log_reported AS lr
					LEFT JOIN {db_prefix}log_reported_comments AS lrc ON (lrc.id_report = lr.id_report)
				WHERE lr.id_report BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lrc.id_report IS NULL',
			'fix_collect' => array(
				'index' => 'id_report',
				'process' => function($reports)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_reported
						WHERE id_report IN ({array_int:reports})',
						array(
							'reports' => $reports,
						)
					);
				},
			),
			'messages' => array('repair_report_missing_comments', 'id_report', 'subject'),
		),
		'comments_missing_report' => array(
			'substeps' => array(
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_report)
					FROM {db_prefix}log_reported_comments'
			),
			'check_query' => '
				SELECT lrc.id_report, lrc.membername
				FROM {db_prefix}log_reported_comments AS lrc
					LEFT JOIN {db_prefix}log_reported AS lr ON (lr.id_report = lrc.id_report)
				WHERE lrc.id_report BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND lr.id_report IS NULL',
			'fix_collect' => array(
				'index' => 'id_report',
				'process' => function($reports)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_reported_comments
						WHERE id_report IN ({array_int:reports})',
						array(
							'reports' => $reports,
						)
					);
				},
			),
			'messages' => array('repair_comments_missing_report', 'id_report', 'membername'),
		),
		'group_request_missing_member' => array(
			'substeps' => array(
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_member)
					FROM {db_prefix}log_group_requests'
			),
			'check_query' => '
				SELECT lgr.id_member
				FROM {db_prefix}log_group_requests AS lgr
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
				WHERE lgr.id_member BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mem.id_member IS NULL
				GROUP BY lgr.id_member',
			'fix_collect' => array(
				'index' => 'id_member',
				'process' => function($members)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_group_requests
						WHERE id_member IN ({array_int:members})',
						array(
							'members' => $members,
						)
					);
				},
			),
			'messages' => array('repair_group_request_missing_member', 'id_member'),
		),
		'group_request_missing_group' => array(
			'substeps' => array(
				'step_size' => 200,
				'step_max' => '
					SELECT MAX(id_group)
					FROM {db_prefix}log_group_requests'
			),
			'check_query' => '
				SELECT lgr.id_group
				FROM {db_prefix}log_group_requests AS lgr
					LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
				WHERE lgr.id_group BETWEEN {STEP_LOW} AND {STEP_HIGH}
					AND mg.id_group IS NULL
				GROUP BY lgr.id_group',
			'fix_collect' => array(
				'index' => 'id_group',
				'process' => function($groups)
				{
					global $smcFunc;
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_group_requests
						WHERE id_group IN ({array_int:groups})',
						array(
							'groups' => $groups,
						)
					);
				},
			),
			'messages' => array('repair_group_request_missing_group', 'id_group'),
		),
	);
}

/**
 * Checks for errors in steps, until 5 seconds have passed.
 * It keeps track of the errors it did find, so that the actual repair
 * won't have to recheck everything.
 *
 * @param bool $do_fix Whether to actually fix the errors or just return the info
 * @return array, the errors found.
 */
function findForumErrors($do_fix = false)
{
	global $context, $txt, $smcFunc, $errorTests, $db_cache, $db_temp_cache;

	// This may take some time...
	@set_time_limit(600);

	$to_fix = !empty($_SESSION['repairboards_to_fix']) ? $_SESSION['repairboards_to_fix'] : array();
	$context['repair_errors'] = isset($_SESSION['repairboards_to_fix2']) ? $_SESSION['repairboards_to_fix2'] : array();

	$_GET['step'] = empty($_GET['step']) ? 0 : (int) $_GET['step'];
	$_GET['substep'] = empty($_GET['substep']) ? 0 : (int) $_GET['substep'];

	// Don't allow the cache to get too full.
	$db_temp_cache = $db_cache;
	$db_cache = array();

	$context['total_steps'] = count($errorTests);

	// For all the defined error types do the necessary tests.
	$current_step = -1;
	$total_queries = 0;
	foreach ($errorTests as $error_type => $test)
	{
		$current_step++;

		// Already done this?
		if ($_GET['step'] > $current_step)
			continue;

		// If we're fixing it but it ain't broke why try?
		if ($do_fix && !in_array($error_type, $to_fix))
		{
			$_GET['step']++;
			continue;
		}

		// Has it got substeps?
		if (isset($test['substeps']))
		{
			$step_size = isset($test['substeps']['step_size']) ? $test['substeps']['step_size'] : 100;
			$request = $smcFunc['db_query']('',
				$test['substeps']['step_max'],
				array(
				)
			);
			list ($step_max) = $smcFunc['db_fetch_row']($request);

			$total_queries++;
			$smcFunc['db_free_result']($request);
		}

		// We in theory keep doing this... the substeps.
		$done = false;
		while (!$done)
		{
			// Make sure there's at least one ID to test.
			if (isset($test['substeps']) && empty($step_max))
				break;

			// What is the testing query (Changes if we are testing or fixing)
			if (!$do_fix)
				$test_query = 'check_query';
			else
				$test_query = isset($test['fix_query']) ? 'fix_query' : 'check_query';

			// Do the test...
			$request = $smcFunc['db_query']('',
				isset($test['substeps']) ? strtr($test[$test_query], array('{STEP_LOW}' => $_GET['substep'], '{STEP_HIGH}' => $_GET['substep'] + $step_size - 1)) : $test[$test_query],
				array(
				)
			);

			// Does it need a fix?
			if (!empty($test['check_type']) && $test['check_type'] == 'count')
				list ($needs_fix) = $smcFunc['db_fetch_row']($request);
			else
				$needs_fix = $smcFunc['db_num_rows']($request);

			$total_queries++;

			if ($needs_fix)
			{
				// What about a message to the user?
				if (!$do_fix)
				{
					// Assume need to fix.
					$found_errors = true;

					if (isset($test['message']))
						$context['repair_errors'][] = $txt[$test['message']];

					// One per row!
					elseif (isset($test['messages']))
					{
						while ($row = $smcFunc['db_fetch_assoc']($request))
						{
							$variables = $test['messages'];
							foreach ($variables as $k => $v)
							{
								if ($k == 0 && isset($txt[$v]))
									$variables[$k] = $txt[$v];
								elseif ($k > 0 && isset($row[$v]))
									$variables[$k] = $row[$v];
							}
							$context['repair_errors'][] = call_user_func_array('sprintf', $variables);
						}
					}

					// A function to process?
					elseif (isset($test['message_function']))
					{
						// Find out if there are actually errors.
						$found_errors = false;
						while ($row = $smcFunc['db_fetch_assoc']($request))
							$found_errors |= $test['message_function']($row);
					}

					// Actually have something to fix?
					if ($found_errors)
						$to_fix[] = $error_type;
				}

				// We want to fix, we need to fix - so work out what exactly to do!
				else
				{
					// Are we simply getting a collection of ids?
					if (isset($test['fix_collect']))
					{
						$ids = array();
						while ($row = $smcFunc['db_fetch_assoc']($request))
							$ids[] = $row[$test['fix_collect']['index']];
						if (!empty($ids))
						{
							// Fix it!
							$test['fix_collect']['process']($ids);
						}
					}

					// Simply executing a fix it query?
					elseif (isset($test['fix_it_query']))
						$smcFunc['db_query']('',
							$test['fix_it_query'],
							array(
							)
						);

					// Do we have some processing to do?
					elseif (isset($test['fix_processing']))
					{
						while ($row = $smcFunc['db_fetch_assoc']($request))
							$test['fix_processing']($row);
					}

					// What about the full set of processing?
					elseif (isset($test['fix_full_processing']))
						$test['fix_full_processing']($request);

					// Do we have other things we need to fix as a result?
					if (!empty($test['force_fix']))
					{
						foreach ($test['force_fix'] as $item)
							if (!in_array($item, $to_fix))
								$to_fix[] = $item;
					}
				}
			}

			// Free the result.
			$smcFunc['db_free_result']($request);
			// Keep memory down.
			$db_cache = array();

			// Are we done yet?
			if (isset($test['substeps']))
			{
				$_GET['substep'] += $step_size;
				// Not done?
				if ($_GET['substep'] <= $step_max)
				{
					pauseRepairProcess($to_fix, $error_type, $step_max);
				}
				else
					$done = true;
			}
			else
				$done = true;

			// Don't allow more than 1000 queries at a time.
			if ($total_queries >= 1000)
				pauseRepairProcess($to_fix, $error_type, $step_max, true);
		}

		// Keep going.
		$_GET['step']++;
		$_GET['substep'] = 0;

		$to_fix = array_unique($to_fix);

		// If we're doing fixes and this needed a fix and we're all done then don't do it again.
		if ($do_fix)
		{
			$key = array_search($error_type, $to_fix);
			if ($key !== false && isset($to_fix[$key]))
				unset($to_fix[$key]);
		}

		// Are we done?
		pauseRepairProcess($to_fix, $error_type);
	}

	// Restore the cache.
	$db_cache = $db_temp_cache;

	return $to_fix;
}

/**
 * Create a salvage area for repair purposes, if one doesn't already exist.
 * Uses the forum's default language, and checks based on that name.
 */
function createSalvageArea()
{
	global $txt, $language, $salvageBoardID, $salvageCatID, $smcFunc;
	static $createOnce = false;

	// Have we already created it?
	if ($createOnce)
		return;
	else
		$createOnce = true;

	// Back to the forum's default language.
	loadLanguage('Admin', $language);

	// Check to see if a 'Salvage Category' exists, if not => insert one.
	$result = $smcFunc['db_query']('', '
		SELECT id_cat
		FROM {db_prefix}categories
		WHERE name = {string:cat_name}
		LIMIT 1',
		array(
			'cat_name' => $txt['salvaged_category_name'],
		)
	);
	if ($smcFunc['db_num_rows']($result) != 0)
		list ($salvageCatID) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if (empty($salvageCatID))
	{
		$salvageCatID = $smcFunc['db_insert']('',
			'{db_prefix}categories',
			array('name' => 'string-255', 'cat_order' => 'int', 'description' => 'string-255'),
			array($txt['salvaged_category_name'], -1, $txt['salvaged_category_description']),
			array('id_cat'),
			1
		);

		if ($smcFunc['db_affected_rows']() <= 0)
		{
			loadLanguage('Admin');
			fatal_lang_error('salvaged_category_error', false);
		}
	}

	// Check to see if a 'Salvage Board' exists, if not => insert one.
	$result = $smcFunc['db_query']('', '
		SELECT id_board
		FROM {db_prefix}boards
		WHERE id_cat = {int:id_cat}
			AND name = {string:board_name}
		LIMIT 1',
		array(
			'id_cat' => $salvageCatID,
			'board_name' => $txt['salvaged_board_name'],
		)
	);
	if ($smcFunc['db_num_rows']($result) != 0)
		list ($salvageBoardID) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	if (empty($salvageBoardID))
	{
		$salvageBoardID = $smcFunc['db_insert']('',
			'{db_prefix}boards',
			array('name' => 'string-255', 'description' => 'string-255', 'id_cat' => 'int', 'member_groups' => 'string', 'board_order' => 'int', 'redirect' => 'string'),
			array($txt['salvaged_board_name'], $txt['salvaged_board_description'], $salvageCatID, '1', -1, ''),
			array('id_board'),
			1
		);

		if ($smcFunc['db_affected_rows']() <= 0)
		{
			loadLanguage('Admin');
			fatal_lang_error('salvaged_board_error', false);
		}
	}

	// Restore the user's language.
	loadLanguage('Admin');
}

?>