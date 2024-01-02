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
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Lang;
use SMF\User;
use SMF\Utils;

/**
 * Represents a personal message label.
 */
class Label implements \ArrayAccess
{
	use ArrayAccessHelper;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * ID number of this label.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * Name of this label
	 */
	public string $name;

	/**
	 * @var int
	 *
	 * The number of PMs that have this label.
	 */
	public int $messages;

	/**
	 * @var int
	 *
	 * The number of unread PMs that have this label.
	 */
	public int $unread_messages;

	/**
	 * @var array
	 *
	 * IDs of the PMs that have this label.
	 */
	public array $pms = [];

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
		'id_label' => 'id',
		'id_member' => 'member',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param int $id ID number of this label.
	 * @param string $name Name of this label.
	 * @param array $props Properties to set for this label.
	 */
	public function __construct(int $id, string $name, array $props = [])
	{
		$this->id = $id;
		$this->name = $name;
		$this->set($props);
		self::$loaded[$id] = $this;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads all the labels that belong to the current user.
	 *
	 * @return array A copy of self::$loaded.
	 */
	public static function load(): array
	{
		if (!empty(self::$loaded)) {
			return self::$loaded;
		}

		// Load the label data.
		if (User::$me->new_pm || ($labels = CacheApi::get('labelCounts:' . User::$me->id, 720)) === null) {
			// Inbox "label"
			$labels[-1] = [
				'id' => -1,
				'name' => Lang::$txt['pm_msg_label_inbox'],
				'messages' => 0,
				'unread_messages' => 0,
				'pms' => [],
			];

			$selects = [
				'SUM(is_read & 1) AS num_read',
				'COUNT(*) AS total',
				Db::$db->title === POSTGRE_TITLE ? "string_agg(id_pm::text, ',') AS pms" : 'GROUP_CONCAT(id_pm) AS pms',
			];

			// First get the inbox counts
			$result = Db::$db->query(
				'',
				'SELECT ' . implode(', ', $selects) . '
				FROM {db_prefix}pm_recipients
				WHERE id_member = {int:me}
					AND in_inbox = {int:in_inbox}
					AND deleted = {int:not_deleted}',
				[
					'me' => User::$me->id,
					'in_inbox' => 1,
					'not_deleted' => 0,
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$labels[-1]['messages'] = $row['total'];
				$labels[-1]['unread_messages'] = $row['total'] - $row['num_read'];
				$labels[-1]['pms'] = empty($row['pms']) ? [] : explode(',', $row['pms']);
			}
			Db::$db->free_result($result);

			$selects = [
				'l.id_label',
				'l.name',
				'COALESCE(SUM(pr.is_read & 1), 0) AS num_read',
				'COALESCE(COUNT(pr.id_pm), 0) AS total',
				Db::$db->title === POSTGRE_TITLE ? "string_agg(pr.id_pm::text, ',') AS pms" : 'GROUP_CONCAT(pr.id_pm) AS pms',
			];

			// Now load info about all the other labels
			$result = Db::$db->query(
				'',
				'SELECT ' . implode(', ', $selects) . '
				FROM {db_prefix}pm_labels AS l
					LEFT JOIN {db_prefix}pm_labeled_messages AS pl ON (pl.id_label = l.id_label)
					LEFT JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pl.id_pm)
				WHERE l.id_member = {int:me}
				GROUP BY l.id_label, l.name',
				[
					'me' => User::$me->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$labels[(int) $row['id_label']] = [
					'id' => (int) $row['id_label'],
					'name' => $row['name'],
					'messages' => (int) $row['total'],
					'unread_messages' => (int) ($row['total'] - $row['num_read']),
					'pms' => empty($row['pms']) ? [] : array_filter(explode(',', $row['pms'])),
				];
			}
			Db::$db->free_result($result);

			// Store it please!
			CacheApi::put('labelCounts:' . User::$me->id, $labels, 720);
		}

		foreach ($labels as $label) {
			new self($label['id'], $label['name'], $label);
		}

		Utils::$context['labels'] = &self::$loaded;

		return self::$loaded;
	}

	/**
	 * Handles adding, deleting, and editing labels on messages.
	 */
	public static function manage(): void
	{
		// Build the link tree elements...
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=manlabels',
			'name' => Lang::$txt['pm_manage_labels'],
		];

		Utils::$context['page_title'] = Lang::$txt['pm_manage_labels'];
		Utils::$context['sub_template'] = 'labels';

		$the_labels = [];
		$labels_to_add = [];
		$labels_to_remove = [];
		$label_updates = [];

		// Add all of the current user's existing labels to the array to save, slashing them as necessary...
		foreach (Label::$loaded as $label) {
			if ($label['id'] != -1) {
				$the_labels[$label['id']] = $label['name'];
			}
		}

		if (isset($_POST[Utils::$context['session_var']])) {
			User::$me->checkSession();

			// This will be for updating messages.
			$message_changes = [];
			$rule_changes = [];

			// We will most likely need this.
			Rule::load();

			// Adding a new label?
			if (isset($_POST['add'])) {
				$_POST['label'] = strtr(Utils::htmlspecialchars(trim($_POST['label'])), [',' => '&#044;']);

				if (Utils::entityStrlen($_POST['label']) > 30) {
					$_POST['label'] = Utils::entitySubstr($_POST['label'], 0, 30);
				}

				if ($_POST['label'] != '') {
					$the_labels[] = $_POST['label'];
					$labels_to_add[] = $_POST['label'];
				}
			}
			// Deleting an existing label?
			elseif (isset($_POST['delete'], $_POST['delete_label'])) {
				foreach ($_POST['delete_label'] as $label => $dummy) {
					if (array_key_exists($label, $the_labels)) {
						unset($the_labels[$label]);
						$labels_to_remove[] = $label;
					}
				}
			}
			// The hardest one to deal with... changes.
			elseif (isset($_POST['save']) && !empty($_POST['label_name'])) {
				foreach ($the_labels as $id => $name) {
					if ($id == -1) {
						continue;
					}

					if (isset($_POST['label_name'][$id])) {
						$_POST['label_name'][$id] = trim(strtr(Utils::htmlspecialchars($_POST['label_name'][$id]), [',' => '&#044;']));

						if (Utils::entityStrlen($_POST['label_name'][$id]) > 30) {
							$_POST['label_name'][$id] = Utils::entitySubstr($_POST['label_name'][$id], 0, 30);
						}

						if ($_POST['label_name'][$id] != '') {
							// Changing the name of this label?
							if ($the_labels[$id] != $_POST['label_name'][$id]) {
								$label_updates[$id] = $_POST['label_name'][$id];
							}

							$the_labels[(int) $id] = $_POST['label_name'][$id];
						} else {
							unset($the_labels[(int) $id]);
							$labels_to_remove[] = $id;
							$message_changes[(int) $id] = true;
						}
					}
				}
			}

			// Save any new labels
			if (!empty($labels_to_add)) {
				$inserts = [];

				foreach ($labels_to_add as $label) {
					$inserts[] = [User::$me->id, $label];
				}

				Db::$db->insert('', '{db_prefix}pm_labels', ['id_member' => 'int', 'name' => 'string-30'], $inserts, []);
			}

			// Update existing labels as needed
			if (!empty($label_updates)) {
				foreach ($label_updates as $id => $name) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}pm_labels
						SET name = {string:name}
						WHERE id_label = {int:id_label}
						AND id_member = {int:me}',
						[
							'name' => $name,
							'id_label' => $id,
							'me' => User::$me->id,
						],
					);
				}
			}

			// Now the fun part... Deleting labels.
			if (!empty($labels_to_remove)) {
				// First delete the labels
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}pm_labels
					WHERE id_label IN ({array_int:labels_to_delete})
					AND id_member = {int:me}',
					[
						'labels_to_delete' => $labels_to_remove,
						'me' => User::$me->id,
					],
				);

				// Now remove the now-deleted labels from any PMs...
				Db::$db->query(
					'',
					'DELETE FROM {db_prefix}pm_labeled_messages
					WHERE id_label IN ({array_int:labels_to_delete})',
					[
						'labels_to_delete' => $labels_to_remove,
					],
				);

				// Get any PMs with no labels which aren't in the inbox.
				$stranded_messages = [];

				$get_stranded_pms = Db::$db->query(
					'',
					'SELECT pmr.id_pm
					FROM {db_prefix}pm_recipients AS pmr
						LEFT JOIN {db_prefix}pm_labeled_messages AS pml ON (pml.id_pm = pmr.id_pm)
					WHERE pml.id_label IS NULL
						AND pmr.in_inbox = {int:not_in_inbox}
						AND pmr.deleted = {int:not_deleted}
						AND pmr.id_member = {int:me}',
					[
						'not_in_inbox' => 0,
						'not_deleted' => 0,
						'me' => User::$me->id,
					],
				);

				while ($row = Db::$db->fetch_assoc($get_stranded_pms)) {
					$stranded_messages[] = $row['id_pm'];
				}
				Db::$db->free_result($get_stranded_pms);

				// Move these back to the inbox if necessary.
				if (!empty($stranded_messages)) {
					// We now have more messages in the inbox.
					Label::$loaded[-1]['messages'] += count($stranded_messages);
					Db::$db->query(
						'',
						'UPDATE {db_prefix}pm_recipients
						SET in_inbox = {int:in_inbox}
						WHERE id_pm IN ({array_int:stranded_messages})
							AND id_member = {int:me}',
						[
							'stranded_messages' => $stranded_messages,
							'in_inbox' => 1,
							'me' => User::$me->id,
						],
					);
				}

				// Now do the same the rules - check through each rule.
				foreach (Rule::$loaded as $k => $rule) {
					// Each action...
					foreach ($rule->actions as $k2 => $action) {
						if ($action['t'] != 'lab' || !in_array($action['v'], $labels_to_remove)) {
							continue;
						}

						$rule_changes[] = $rule->id;

						// Can't apply this label anymore if it doesn't exist
						unset(Rule::$loaded[$k]->actions[$k2]);
					}
				}
			}

			// If we have rules to change do so now.
			if (!empty($rule_changes)) {
				$rule_changes = array_unique($rule_changes);

				// Update/delete as appropriate.
				foreach ($rule_changes as $k => $id) {
					if (!empty(Rule::$loaded[$id]->actions)) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}pm_rules
							SET actions = {string:actions}
							WHERE id_rule = {int:id_rule}
								AND id_member = {int:me}',
							[
								'me' => User::$me->id,
								'id_rule' => $id,
								'actions' => Utils::jsonEncode(Rule::$loaded[$id]->actions),
							],
						);
						unset($rule_changes[$k]);
					}
				}

				// Anything left here means it's lost all actions...
				if (!empty($rule_changes)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}pm_rules
						WHERE id_rule IN ({array_int:rule_list})
							AND id_member = {int:me}',
						[
							'me' => User::$me->id,
							'rule_list' => $rule_changes,
						],
					);
				}
			}

			// Make sure we're not caching this!
			CacheApi::put('labelCounts:' . User::$me->id, null, 720);

			// To make the changes appear right away, redirect.
			Utils::redirectexit('action=pm;sa=manlabels');
		}
	}
}

?>