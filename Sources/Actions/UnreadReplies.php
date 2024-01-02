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

use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Finds and retrieves information about replies to the user's posts.
 */
class UnreadReplies extends Unread
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * Either 'query_see_board' or 'query_wanna_see_board'.
	 */
	protected string $see_board = 'query_see_board';

	/**
	 * @var string
	 *
	 * Name of the sub-template to use.
	 */
	protected string $sub_template = 'replies';

	/**
	 * @var bool
	 *
	 * Whether we are getting topics or replies.
	 */
	protected bool $is_topics = false;

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
		// 'all' is never applicable for this action.
		unset($_GET['all']);

		parent::__construct();

		Utils::$context['page_title'] = Lang::$txt['unread_replies'];
		$this->linktree_name = Lang::$txt['unread_replies'];
		$this->action_url = Config::$scripturl . '?action=unreadreplies';
	}

	/**
	 * Checks that the load averages aren't too high to show unread replies.
	 */
	protected function checkLoadAverage()
	{
		if (empty(Utils::$context['load_average'])) {
			return;
		}

		if (empty(Config::$modSettings['loadavg_unreadreplies'])) {
			return;
		}

		if (Utils::$context['load_average'] >= Config::$modSettings['loadavg_unreadreplies']) {
			ErrorHandler::fatalLang('loadavg_unreadreplies_disabled', false);
		}
	}

	/**
	 * Sets $this->topic_request to the appropriate query.
	 */
	protected function setTopicRequest()
	{
		if (Config::$modSettings['totalMessages'] > 100000) {
			$this->makeTempTable();
		}

		if ($this->have_temp_table) {
			$this->getTopicRequestWithTempTable();
		} else {
			$this->getTopicRequestWithoutTempTable();
		}
	}

	/**
	 * For large forums, creates a temporary table to use when showing unread replies.
	 */
	protected function makeTempTable()
	{
		Db::$db->query(
			'',
			'DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
			[
			],
		);

		Db::$db->query(
			'',
			'DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
			[
			],
		);

		$sortKey_joins = [
			'ms.subject' => '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
			'COALESCE(mems.real_name, ms.poster_name)' => '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
		];

		// The main benefit of this temporary table is not that it's faster; it's that it avoids locks later.
		$this->have_temp_table = false !== Db::$db->query(
			'',
			'CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
				id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
				id_board smallint(5) unsigned NOT NULL default {string:string_zero},
				id_last_msg int(10) unsigned NOT NULL default {string:string_zero},
				id_msg int(10) unsigned NOT NULL default {string:string_zero},
				PRIMARY KEY (id_topic)
			)
			SELECT t.id_topic, t.id_board, t.id_last_msg, COALESCE(lmr.id_msg, 0) AS id_msg' . (!in_array($_REQUEST['sort'], ['t.id_last_msg', 't.id_topic']) ? ', ' . $_REQUEST['sort'] . ' AS sort_key' : '') . '
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				LEFT JOIN {db_prefix}log_topics_unread AS lt ON (lt.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . ($sortKey_joins[$_REQUEST['sort']] ?? '') . '
			WHERE m.id_member = {int:current_member}' . (!empty(Board::$info->id) ? '
				AND t.id_board = {int:current_board}' : '') . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1
			GROUP BY m.id_topic',
			[
				'current_board' => Board::$info->id,
				'current_member' => User::$me->id,
				'is_approved' => 1,
				'string_zero' => '0',
				'db_error_skip' => true,
			],
		);

		// If that worked, create a sample of the log_topics table too.
		if ($this->have_temp_table) {
			$this->have_temp_table = false !== Db::$db->query(
				'',
				'CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
					PRIMARY KEY (id_topic)
				)
				SELECT lt.id_topic, lt.id_msg
				FROM {db_prefix}log_topics AS lt
					INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
				WHERE lt.id_member = {int:current_member}',
				[
					'current_member' => User::$me->id,
					'db_error_skip' => true,
				],
			);
		}
	}

	/**
	 * For large forums, sets $this->topic_request with the help of a temporary table.
	 */
	protected function getTopicRequestWithTempTable()
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}topics_posted_in AS pi
				LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
			WHERE pi.' . $this->query_this_board . '
				AND COALESCE(lt.id_msg, pi.id_msg) < pi.id_last_msg',
			array_merge($this->query_parameters, [
			]),
		);
		list($this->num_topics) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if ($this->num_topics == 0) {
			$this->setNoTopics();

			return;
		}

		$topics = [];
		$request = Db::$db->query(
			'',
			'SELECT t.id_topic
			FROM {db_prefix}topics_posted_in AS t
				LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
			WHERE t.' . $this->query_this_board . '
				AND COALESCE(lt.id_msg, t.id_msg) < t.id_last_msg
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($this->query_parameters, [
				'order' => (in_array($_REQUEST['sort'], ['t.id_last_msg', 't.id_topic']) ? $_REQUEST['sort'] : 't.sort_key') . ($this->ascending ? '' : ' DESC'),
				'offset' => Utils::$context['start'],
				'limit' => Utils::$context['topics_per_page'],
			]),
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$topics[] = $row['id_topic'];
		}
		Db::$db->free_result($request);

		// Sanity... where have you gone?
		if (empty($topics)) {
			$this->setNoTopics();

			return;
		}

		$this->topic_request = Db::$db->query(
			'substring',
			'SELECT ' . implode(', ', $this->selects) . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty(Theme::$current->settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mems.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY {raw:sort}' . ($this->ascending ? '' : ' DESC') . '
			LIMIT {int:limit}',
			[
				'current_member' => User::$me->id,
				'topic_list' => $topics,
				'sort' => $_REQUEST['sort'],
				'limit' => count($topics),
			],
		);
	}

	/**
	 * Sets $this->topic_request without the help of a temporary table.
	 */
	protected function getTopicRequestWithoutTempTable()
	{
		$request = Db::$db->query(
			'unread_fetch_topic_count',
			'SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $this->query_this_board . '
				AND m.id_member = {int:current_member}
				AND COALESCE(lt.id_msg, lmr.id_msg, 0) < t.id_last_msg' . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
				AND COALESCE(lt.unwatched, 0) != 1',
			array_merge($this->query_parameters, [
				'current_member' => User::$me->id,
				'is_approved' => 1,
			]),
		);
		list($num_topics, $min_message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$this->num_topics = $num_topics ?? 0;
		$this->min_message = $min_message ?? 0;

		if ($this->num_topics == 0) {
			$this->setNoTopics();

			return;
		}

		$topics = [];

		$request = Db::$db->query(
			'',
			'SELECT DISTINCT t.id_topic,' . $_REQUEST['sort'] . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($_REQUEST['sort'], 'ms.') === false ? '' : '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($_REQUEST['sort'], 'mems.') === false ? '' : '
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $this->query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND (COALESCE(lt.id_msg, lmr.id_msg, 0)) < t.id_last_msg
				AND t.approved = {int:is_approved}
				AND COALESCE(lt.unwatched, 0) != 1
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($this->query_parameters, [
				'current_member' => User::$me->id,
				'min_message' => (int) $this->min_message,
				'is_approved' => 1,
				'order' => $_REQUEST['sort'] . ($this->ascending ? '' : ' DESC'),
				'offset' => Utils::$context['start'],
				'limit' => Utils::$context['topics_per_page'],
				'sort' => $_REQUEST['sort'],
			]),
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$topics[] = $row['id_topic'];
		}
		Db::$db->free_result($request);

		// Sanity... where have you gone?
		if (empty($topics)) {
			$this->setNoTopics();

			return;
		}

		$this->topic_request = Db::$db->query(
			'substring',
			'SELECT ' . implode(', ', $this->selects) . '
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' . (!empty(Theme::$current->settings['avatars_on_indexes']) ? '
				LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = mems.id_member)
				LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = meml.id_member)' : '') . '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.id_topic IN ({array_int:topic_list})
			ORDER BY {raw:sort}' . ($this->ascending ? '' : ' DESC') . '
			LIMIT {int:limit}',
			[
				'current_member' => User::$me->id,
				'topic_list' => $topics,
				'sort' => $_REQUEST['sort'],
				'limit' => count($topics),
			],
		);
	}
}

?>