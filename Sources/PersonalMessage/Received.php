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

use SMF\ArrayAccessHelper;
use SMF\Db\DatabaseApi as Db;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This class represents the received copy of a personal message in a member's
 * inbox. It has info such as whether the PM has been read by this member,
 * deleted by the member, etc., and whether it was sent to this member as a
 * visible recipient or as a hidden one (i.e a "BCC" recipient).
 *
 * @todo Rename the pm_recipients table to pm_received. A recipient is a person
 * who receives something, not the thing received, so the table name is
 * misleading.
 */
class Received implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * The PM's ID number.
	 */
	public int $id;

	/**
	 * @var int
	 *
	 * ID number of the recipent of the PM.
	 */
	public int $member;

	/**
	 * @var string
	 *
	 * Name of the recipent of the PM.
	 */
	public string $name;

	/**
	 * @var bool
	 *
	 * Whether the member was a hidden recipient.
	 *
	 * Just like a "blind carbon copy" recipient of an e-mail message, a BCC
	 * recipient of a personal message is not visible to other recipients.
	 */
	public bool $bcc = false;

	/**
	 * @var bool
	 *
	 * Whether the recipent has read the PM.
	 */
	public bool $unread = true;

	/**
	 * @var bool
	 *
	 * Whether the recipent has replied the PM.
	 */
	public bool $replied = false;

	/**
	 * @var bool
	 *
	 * Whether the recipent has been informed yet that they received the PM.
	 */
	public bool $is_new = false;

	/**
	 * @var bool
	 *
	 * Whether the recipent has deleted the PM.
	 */
	public bool $deleted = false;

	/**
	 * @var bool
	 *
	 * Whether the PM is in the recipent's inbox folder.
	 */
	public bool $in_inbox = true;

	/**
	 * @var array
	 *
	 * Labels assigned to this PM by the recipent.
	 */
	public array $labels = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_pm' => 'id',
		'id_member' => 'member',
		'real_name' => 'name',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * Database query used in Received::queryData().
	 */
	protected static $messages_request;

	/**
	 * @var array
	 *
	 * Holds the results of Received::getRecent().
	 *
	 * The keys of this array are string representations of the parameters that
	 * Received::getRecent() was called with. The values are lists of PM IDs
	 * that match those parameters.
	 */
	protected static array $recent = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $props Properties to set for this message.
	 */
	public function __construct(array $props = [])
	{
		$this->set($props);
		$this->getLabels();

		if (isset($this->id, $this->member)) {
			self::$loaded[$this->id][$this->member] = $this;
		}
	}

	/**
	 * Applies a label to this PM.
	 *
	 * @return array The label IDs.
	 */
	public function addLabel(int $label_id): void
	{
		Label::load();

		if (isset(Label::$loaded[$label_id])) {
			$this->labels[] = $label_id;
		}

		// Some people want to remove the inbox label when adding a different label.
		if (!empty(Theme::$current->options['pm_remove_inbox_label']) && $label_id !== -1) {
			$this->in_inbox = false;
			$this->labels = array_diff($this->labels, [-1]);
		}

		$this->labels = array_unique($this->labels);

		sort($this->labels);

		$this->save();
	}

	/**
	 * Removes a label from this PM.
	 *
	 * @return array The label IDs.
	 */
	public function removeLabel(int $label_id): void
	{
		$this->labels = array_diff($this->labels, [$label_id]);

		// If it has no labels, put it back in the inbox.
		if (empty($this->labels) || in_array(-1, $this->labels)) {
			$this->in_inbox = true;
			$this->labels[] = -1;
		}

		$this->labels = array_unique($this->labels);

		sort($this->labels);

		$this->save();
	}

	/**
	 * Update the received status in the database.
	 */
	public function save(): void
	{
		$is_read = ($this->replied ? 0b10 : 0) | !$this->unread;

		$this->$labels = array_map('intval', $this->$labels);

		if (empty($this->labels) || in_array(-1, $this->labels)) {
			$this->in_inbox = true;
		}

		Db::$db->query(
			'',
			'UPDATE {db_prefix}pm_recipients
			SET
				id_member = {int:member},
				bcc = {int:bcc},
				is_read = {int:is_read},
				is_new = {int:is_new},
				deleted = {int:deleted},
				in_inbox = {int:in_inbox}
			WHERE id_pm = {int:id}',
			[
				'id' => (int) $this->id,
				'member' => (int) $this->member,
				'bcc' => (int) $this->bcc,
				'is_read' => (int) $is_read,
				'is_new' => (int) $this->is_new,
				'deleted' => (int) $this->deleted,
				'in_inbox' => (int) $this->in_inbox,
			],
		);

		$labels = array_diff($this->labels, [-1]);

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}pm_labeled_messages
			WHERE id_pm = {int:current_pm}' . (empty($labels) ? '' : '
				AND id_label NOT IN ({array_int:labels})'),
			[
				'current_pm' => $this->id,
				'labels' => $labels,
			],
		);

		if (!empty($labels)) {
			$inserts = [];

			foreach ($labels as $label) {
				$inserts[] = [$this->id, $label];
			}

			Db::$db->insert(
				'ignore',
				'{db_prefix}pm_labeled_messages',
				['id_pm' => 'int', 'id_label' => 'int'],
				$inserts,
				[],
			);
		}
	}

	/**
	 * Sets custom properties.
	 *
	 * @param string $prop The property name.
	 * @param mixed $value The value to set.
	 */
	public function __set(string $prop, $value): void
	{
		// This is a bitmap where the lowest bit is the read status and the
		// second bit is the replied status.
		if ($prop == 'is_read') {
			$this->unread = !($value & 0b01);
			$this->replied = $value & 0b10;
		} else {
			$this->customPropertySet($prop, $value);
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads instances of this class for the passed personal message IDs.
	 *
	 * The newly loaded instances are returned, but also stored in self::$loaded
	 * for future reference.
	 *
	 * @param int|array $ids The IDs of one or more personal messages.
	 * @return array The newly loaded instances of this class.
	 */
	public static function loadByPm(int|array $ids): array
	{
		$loaded = [];

		$ids = (array) $ids;

		// Have we already loaded these?
		foreach ($ids as $key => $id) {
			if (isset(self::$loaded[$id])) {
				foreach (self::$loaded[$id] as $received) {
					$loaded[$id] = $received;
				}

				unset($ids[$key]);
			}
		}

		if (empty($ids)) {
			ksort($loaded);

			return $loaded;
		}

		// We have new ones that we need to load.
		$selects = [
			'pmr.*',
			'COALESCE(mem.real_name, "") AS real_name',
		];

		$joins = [
			'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)',
		];

		$where = [
			'pmr.id_pm IN ({array_int:ids})',
		];

		$params = [
			'ids' => $ids,
		];

		foreach (self::queryData($selects, $params, $joins, $where) as $row) {
			$loaded[(int) $row['id_pm']] = new self($row);
		}

		ksort($loaded);

		return $loaded;
	}

	/**
	 * Loads instances of this class for unread personal messages.
	 */
	public static function loadUnread(): array
	{
		$loaded = [];

		$selects = [
			'pmr.*',
		];

		$joins = [];

		$where = [
			'pmr.id_member = {int:me}',
			'pmr.is_read = {int:not_read}',
			'pmr.deleted = {int:not_deleted}',
		];

		$params = [
			'me' => User::$me->id,
			'not_read' => 0,
			'not_deleted' => 0,
		];

		foreach (self::queryData($selects, $params, $joins, $where) as $row) {
			$loaded[] = new self($row);
		}

		return $loaded;
	}

	/**
	 * Returns the IDs of personal messages received before a given time.
	 *
	 * @param int $time A Unix timestamp.
	 * @return array The IDs of personal messages received before $time.
	 */
	public static function old(int $time): array
	{
		$ids = [];

		$selects = [
			'pmr.id_pm',
		];

		$joins = [
			'INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)',
		];

		$where = [
			'pmr.id_member = {int:me}',
			'pmr.deleted = {int:not_deleted}',
			'pm.msgtime < {int:time}',
		];

		$params = [
			'me' => User::$me->id,
			'not_deleted' => 0,
			'time' => $time,
		];

		foreach (self::queryData($selects, $params, $joins, $where) as $row) {
			$ids[] = $row['id_pm'];
		}

		return $loaded;
	}

	/**
	 * Counts the personal messages in a given label, with optional limits.
	 *
	 * @param int $label The ID of a label, or -1 for the inbox.
	 * @param int $boundary The ID of a PM that limits the range of counted PMs.
	 * @param bool $greater_than If true, only count PMs whose IDs are greater
	 *    than $boundary. If false, only count PMs whose IDs are less than it.
	 */
	public static function count(int $label = -1, int $boundary = 0, bool $greater_than = false): int
	{
		$num = 0;

		$selects = [
			'COUNT(*)',
		];

		$joins = [];

		$where = [
			'pmr.id_member = {int:me}',
			'pmr.deleted = {int:not_deleted}',
		];

		$params = [
			'me' => User::$me->id,
			'not_deleted' => 0,
		];

		if ($label === -1) {
			$where[] = 'pmr.in_inbox = 1';
		} else {
			$joins[] = 'INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pmr.id_pm)';
			$where[] = 'pl.id_label = {int:label}';
			$params['label'] = $label;
		}

		if (!empty($boundary)) {
			$where[] = 'pmr.id_pm ' . ($greater_than ? '>' : '<') . ' {int:boundary}';
			$params['boundary'] = $boundary;
		}

		foreach (self::queryData($selects, $params, $joins, $where) as $row) {
			$num += reset($row);
		}

		return $num;
	}

	/**
	 * Gets the ID of the most recent personal message in a label or the inbox.
	 *
	 * @param int $label The ID of a label, or -1 for the inbox.
	 * @return int The ID of the most recent PM.
	 */
	public static function getLatest(int $label = -1): int
	{
		$latest = self::getRecent($label, 'pmr.id_pm', true, 1);

		return reset($latest);
	}

	/**
	 * Gets the IDs of the most recent personal messages in a label or the inbox.
	 *
	 * @param int $label The ID of a label, or -1 for the inbox.
	 *    Default: -1.
	 * @param string $sort The column to sort by in the SQL query.
	 *    Default: pmr.id_pm
	 * @param bool $descending Whether to sort descending or ascending.
	 *    Default: true.
	 * @param int $limit How many results to get. Zero for no limit.
	 *    Default: 0.
	 * @param int $offset How many results to skip before retrieving the rest.
	 *    Default: 0.
	 * @return array The IDs of the most recent PMs.
	 */
	public static function getRecent(int $label = -1, string $sort = 'pmr.id_pm', bool $descending = true, int $limit = 0, int $offset = 0): array
	{
		$paramskey = Utils::jsonEncode([$label, $sort, $descending, $limit, $offset]);

		if (isset(self::$recent[$paramskey])) {
			return self::$recent[$paramskey];
		}

		self::$recent[$paramskey] = [];

		$joins = [];

		$where = [
			'pmr.id_member = {int:me}',
			'pmr.deleted = 0',
		];

		$params = [
			'me' => User::$me->id,
		];

		if ($label === -1) {
			$where[] = 'pmr.in_inbox = 1';
		}
		// If you're viewing a label, it's still the inbox, but filtered
		// to only show the PMs that have that label.
		else {
			$joins[] = 'INNER JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_pm = pmr.id_pm)';
			$where[] = 'pl.id_label = {int:label}';
			$params['label'] = $label;
		}

		if ($sort == 'COALESCE(mem.real_name, \'\')') {
			$joins[] = 'LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)';
		}

		$request = Db::$db->query(
			'',
			'SELECT pmr.id_pm
			FROM {db_prefix}pm_recipients AS pmr' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . '
			WHERE (' . implode(') AND (', $where) . ')
			ORDER BY ' . ($sort == 'pm.id_pm' ? 'pmr.id_pm' : $sort) . ($descending ? ' DESC' : ' ASC') . (!empty($limit) ? '
			LIMIT ' . $offset . ', ' . $limit : ''),
			$params,
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			self::$recent[$paramskey][] = $row['id_pm'];
		}
		Db::$db->free_result($request);

		return self::$recent[$paramskey];
	}

	/**
	 * Removes the 'is_new' status from the current user's PMs.
	 */
	public static function setNotNew(): void
	{
		User::updateMemberData(User::$me->id, ['new_pm' => 0]);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}pm_recipients
			SET is_new = {int:not_new}
			WHERE id_member = {int:me}',
			[
				'me' => User::$me->id,
				'not_new' => 0,
			],
		);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Gets the IDs of labels applied to this PM by a member who received it.
	 *
	 * The data is stored in $this->labels.
	 */
	protected function getLabels(): void
	{
		if ($this->in_inbox) {
			$this->labels[] = -1;
		}

		foreach (Label::load() as $label) {
			if (in_array($this->id, $label->pms)) {
				$this->labels[] = $label->id;
			}
		}

		if (empty($this->labels) || in_array(-1, $this->labels)) {
			$this->in_inbox = true;
		}

		sort($this->labels);
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Generator that runs queries about PM data and yields the result rows.
	 *
	 * @param array $selects Table columns to select.
	 * @param array $params Parameters to substitute into query text.
	 * @param array $joins Zero or more *complete* JOIN clauses.
	 *    E.g.: 'LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)'
	 *    Note that 'FROM {db_prefix}boards AS b' is always part of the query.
	 * @param array $where Zero or more conditions for the WHERE clause.
	 *    Conditions will be placed in parentheses and concatenated with AND.
	 *    If this is left empty, no WHERE clause will be used.
	 * @param array $order Zero or more conditions for the ORDER BY clause.
	 *    If this is left empty, no ORDER BY clause will be used.
	 * @param array $group Zero or more conditions for the GROUP BY clause.
	 *    If this is left empty, no GROUP BY clause will be used.
	 * @param int|string $limit Maximum number of results to retrieve.
	 *    If this is left empty, all results will be retrieved.
	 *
	 * @return Generator<array> Iterating over the result gives database rows.
	 */
	protected static function queryData(array $selects, array $params = [], array $joins = [], array $where = [], array $order = [], array $group = [], int|string $limit = 0)
	{
		self::$messages_request = Db::$db->query(
			'',
			'SELECT
				' . implode(', ', $selects) . '
			FROM {db_prefix}pm_recipients AS pmr' . (empty($joins) ? '' : '
				' . implode("\n\t\t\t\t", $joins)) . (empty($where) ? '' : '
			WHERE (' . implode(') AND (', $where) . ')') . (empty($order) ? '' : '
			ORDER BY ' . implode(', ', $order)) . (empty($group) ? '' : '
			LIMIT ' . $limit),
			$params,
		);

		while ($row = Db::$db->fetch_assoc(self::$messages_request)) {
			yield $row;
		}
		Db::$db->free_result(self::$messages_request);
	}
}

?>