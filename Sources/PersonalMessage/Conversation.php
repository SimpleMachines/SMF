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

namespace SMF\PersonalMessage;

use SMF\Db\DatabaseApi as Db;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Represents a collection of related personal messages.
 */
class Conversation
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * ID number of the PM that started this conversation.
	 */
	public int $head;

	/**
	 * @var int
	 *
	 * ID number of the latest PM in this conversation.
	 */
	public int $latest;

	/**
	 * @var array
	 *
	 * Info about who sent and received each PM in this conversation.
	 *
	 * Keys are ID numbers of PMs. Values are arrays with sender and recipient IDs.
	 */
	public array $pms = [];

	/**
	 * @var bool
	 *
	 * Whether the current user started this conversation.
	 */
	public bool $started_by_me;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var array
	 *
	 * Holds the results of Received::getRecent().
	 *
	 * The keys of this array are string representations of the parameters that
	 * Received::getRecent() was called with. The values are key-value pairs
	 * that match those parameters, where each key-value pair is the ID of the
	 * head PM and the latest PM in a conversation.
	 */
	protected static array $recent = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $pm The ID number of any PM in the conversation.
	 */
	public function __construct(int $pm)
	{
		// Find all the PMs in this conversation.
		$request = Db::$db->query(
			'',
			'SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from, pm.deleted_by_sender,
				pmr.id_member, pmr.bcc, pmr.deleted
			FROM {db_prefix}personal_messages AS pm
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm AND pmr.id_member = {int:me})
			WHERE pm.id_pm_head = (
				SELECT pm.id_pm_head
				FROM {db_prefix}personal_messages AS pm
				WHERE pm.id_pm = {int:pm}
				LIMIT 1
			)
			ORDER BY pm.id_pm',
			[
				'pm' => $pm,
				'me' => User::$me->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row = array_map('intval', $row);

			// Did the curent user send this PM?
			$from_me = $row['id_member_from'] == User::$me->id;

			// Did the curent user receive this PM?
			// $from_me and $to_me can both be true if users send PMs to themselves.
			$to_me = isset($row['id_member']) && $row['id_member'] == User::$me->id;

			// Track the head PM.
			$this->head = $row['id_pm_head'];

			// Did the current user start this conversation?
			if ($this->head == $row['id_pm']) {
				$this->started_by_me = $from_me;
			}

			// If the current user deleted this PM, skip it.
			if (($from_me && !empty($row['deleted_by_sender'])) || ($to_me && !empty($row['deleted']))) {
				continue;
			}

			if (!isset($this->pms[$row['id_pm']])) {
				$this->pms[$row['id_pm']] = [
					'sender' => $row['id_member_from'],
					'received' => [
						'to' => [],
						'bcc' => [],
					],
				];
			}

			$this->pms[$row['id_pm']]['received'][empty($row['bcc']) ? 'to' : 'bcc'] = $row['id_member'];

			$this->latest = max($this->latest ?? $pm, $row['id_pm']);
		}
		Db::$db->free_result($request);

		// If user wants newest first, make it so.
		if (!empty(Theme::$current->options['view_newest_pm_first'])) {
			array_reverse($this->pms, true);
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Counts the visible conversations in the specified folder and/or label.
	 *
	 * @param bool $inbox Whether we're viewing the inbox or sent items.
	 * @param int $label The ID of the label being viewed, or -1 for no label.
	 *    Does nothing if $inbox is false.
	 * @return int The number of conversations in the current folder/label view.
	 */
	public static function count(bool $inbox = true, int $label = -1): int
	{
		$num = 0;

		$joins = [];
		$where = [];
		$params = [];

		if ($inbox) {
			$joins[] = 'INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)';

			$where[] = 'pmr.id_member = {int:me}';
			$where[] = 'pmr.deleted = 0';

			if ($label === -1) {
				$where[] = 'pmr.in_inbox = 1';
			}
			// If you're viewing a label, it's still the inbox, but filtered
			// to only show the PMs that have that label.
			else {
				$joins[] = 'INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pm.id_pm)';
				$where[] = 'pl.id_label = {int:label}';
				$params['label'] = $label;
			}
		} else {
			$where[] = 'pm.id_member_from = {int:me}';
			$where[] = 'pm.deleted_by_sender = 0';
		}

		$params['me'] = User::$me->id;

		$request = Db::$db->query(
			'',
			'SELECT COUNT(DISTINCT pm.id_pm_head)
			FROM {db_prefix}personal_messages AS pm' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . '
			WHERE (' . implode(') AND (', $where) . ')',
			$params,
		);
		list($num) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $num;
	}

	/**
	 * Gets the latest posts for conversations in a folder.
	 *
	 * @param bool $inbox Whether we're viewing the inbox or sent items.
	 * @param int $label The ID of the label being viewed, or -1 for no label.
	 *    Does nothing if $folder == 'sent'.
	 * @param string $sort Instructions on how to sort the results.
	 * @param bool $descending Whether to sort in descending order.
	 * @param int $limit The max number of results to retreive. Zero = no limit.
	 * @param int $offset Offset where we should begin retreiving results.
	 *    Does nothing if $limit == 0.
	 * @return array Keys are head PMs, values are latest PMs.
	 */
	public static function getRecent(bool $inbox = true, int $label = -1, string $sort = 'pm.id_pm', bool $descending = false, int $limit = 0, int $offset = 0): array
	{
		$paramskey = Utils::jsonEncode([$inbox, $label, $sort, $descending, $limit, $offset]);

		if (isset(self::$recent[$paramskey])) {
			return self::$recent[$paramskey];
		}

		self::$recent[$paramskey] = [];

		$joins = [];
		$where = [];
		$params = [
			'me' => User::$me->id,
		];

		if ($inbox) {
			$joins[] = 'INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)';

			$where[] = 'pmr.id_member = {int:me}';
			$where[] = 'pmr.deleted = 0';

			if ($label === -1) {
				$where[] = 'pmr.in_inbox = 1';
			}
			// If you're viewing a label, it's still the inbox, but filtered
			// to only show the PMs that have that label.
			else {
				$joins[] = 'INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pm.id_pm)';
				$where[] = 'pl.id_label = {int:label}';
				$params['label'] = $label;
			}
		} else {
			if ($sort == 'COALESCE(mem.real_name, \'\')') {
				$joins[] = 'LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pm.id_pm = pmr.id_pm)';
			}

			$where[] = 'pm.id_member_from = {int:me}';
			$where[] = 'pm.deleted_by_sender = 0';
		}

		if ($sort == 'COALESCE(mem.real_name, \'\')') {
			$joins[] = 'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})';
			$params['pm_member'] = $inbox ? 'pmr.id_member' : 'pm.id_member_from';
		}

		$request = Db::$db->query(
			'',
			'SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
			FROM {db_prefix}personal_messages AS pm' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . '
			WHERE (' . implode(') AND (', $where) . ')
			GROUP BY pm.id_pm_head' . ($sort != 'pm.id_pm' ? ', ' . $sort : '') . '
			ORDER BY ' . ($sort == 'pm.id_pm' ? 'id_pm' : $sort) . ($descending ? ' DESC' : ' ASC') . (!empty($limit) ? '
			LIMIT ' . $offset . ', ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$recent[$paramskey][$row['id_pm_head']] = $row['id_pm'];
		}
		Db::$db->free_result($request);

		return self::$recent[$paramskey];
	}
}

?>