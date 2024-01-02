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
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Group;
use SMF\Lang;
use SMF\Security;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Represents a sorting rule that can be applied to incoming personal messages.
 */
class Rule implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'load' => 'loadRules',
			'apply' => 'applyRules',
			'delete' => 'delete',
			'manage' => 'manage',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Whether a rule's criteria are combined using AND or OR logic.
	 */
	public const RULE_AND = false;
	public const RULE_OR = true;

	/**
	 * Maximum number of criteria and actions allowed per rule.
	 */
	public const LIMITS = [
		'criteria' => 10,
		'actions' => 10,
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * This rule's ID number.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * This rule's name.
	 */
	public string $name = '';

	/**
	 * @var int
	 *
	 * ID number of the member that this rule belongs to.
	 */
	public int $member;

	/**
	 * @var array
	 *
	 * This rule's criteria.
	 */
	public array $criteria = [];

	/**
	 * @var array
	 *
	 * Actions that this rule performs.
	 */
	public array $actions = [];

	/**
	 * @var bool
	 *
	 * Whether this rule deletes PMs.
	 */
	public bool $delete = false;

	/**
	 * @var bool
	 *
	 * Whether to combine this rule with others using OR logic or AND logic.
	 * Either self::RULE_AND or self::RULE_OR.
	 */
	public bool $logic = self::RULE_AND;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 */
	public static array $loaded = [];

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = [
		'id_rule' => 'id',
		'id_member' => 'member',
		'rule_name' => 'name',
		'delete_pm' => 'delete',
		'is_or' => 'logic',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $props Properties to set for this rule.
	 */
	public function __construct(array $props = [])
	{
		$this->set($props);

		// Default to the current user.
		$this->member = $this->member ?? User::$me->id;

		if (!empty($this->id)) {
			self::$loaded[$this->id] = $this;
		}
	}

	/**
	 * Save this rule to the database.
	 */
	public function save(): void
	{
		if (empty($this->name)) {
			ErrorHandler::fatalLang('pm_rule_no_name', false);
		}

		if (empty($this->criteria) || (empty($this->actions) && !$this->delete)) {
			ErrorHandler::fatalLang('pm_rule_no_criteria', false);
		}

		if (empty($this->id)) {
			$this->id = Db::$db->insert(
				'',
				'{db_prefix}pm_rules',
				[
					'id_member' => 'int',
					'rule_name' => 'string',
					'criteria' => 'string',
					'actions' => 'string',
					'delete_pm' => 'int',
					'is_or' => 'int',
				],
				[
					$this->member,
					$this->name,
					Utils::jsonEncode($this->criteria),
					Utils::jsonEncode($this->actions),
					(int) $this->delete,
					(int) $this->logic,
				],
				['id_rule'],
				1,
			);

			self::$loaded[$this->id] = $this;
		} else {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}pm_rules
				SET
					rule_name = {string:rule_name},
					criteria = {string:criteria},
					actions = {string:actions},
					delete_pm = {int:delete_pm},
					is_or = {int:is_or}
				WHERE id_rule = {int:id_rule}
					AND id_member = {int:current_member}',
				[
					'id_rule' => $this->id,
					'current_member' => $this->member,
					'rule_name' => $this->name,
					'criteria' => Utils::jsonEncode($this->criteria),
					'actions' => Utils::jsonEncode($this->actions),
					'delete_pm' => (int) $this->delete,
					'is_or' => (int) $this->logic,
				],
			);
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Loads all the rules for the current user.
	 *
	 * @param bool $reload If true, force a reload.
	 */
	public static function load(bool $reload = false): array
	{
		if (!empty(self::$loaded) && !$reload) {
			return self::$loaded;
		}

		self::$loaded = [];
		Utils::$context['rules'] = &self::$loaded;

		$request = Db::$db->query(
			'',
			'SELECT
				id_rule, rule_name, criteria, actions, delete_pm, is_or
			FROM {db_prefix}pm_rules
			WHERE id_member = {int:me}',
			[
				'me' => User::$me->id,
			],
		);

		// Simply fill in the data!
		while ($row = Db::$db->fetch_assoc($request)) {
			$row['criteria'] = Utils::jsonDecode($row['criteria'], true);
			$row['actions'] = Utils::jsonDecode($row['actions'], true);

			if ($row['delete_pm']) {
				$row['actions'][] = [
					't' => 'del',
					'v' => 1,
				];
			}

			new self($row);
		}
		Db::$db->free_result($request);

		return self::$loaded;
	}

	/**
	 * This will apply rules to all unread messages.
	 *
	 * If $all_messages is set will, obviously, do it to all!
	 *
	 * @param bool $all_messages Whether to apply this to all messages or just unread ones.
	 */
	public static function apply(bool $all_messages = false): void
	{
		// Want this - duh!
		self::load();

		// No rules?
		if (empty(self::$loaded)) {
			return;
		}

		// Just unread ones?
		$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';

		// @todo Apply all should have timeout protection!
		// Get all the messages that match this.
		$actions = [];

		$request = Db::$db->query(
			'',
			'SELECT
				pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pmr.id_member = {int:me}
				AND pmr.deleted = {int:not_deleted}
				' . $ruleQuery,
			[
				'me' => User::$me->id,
				'not_deleted' => 0,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			foreach (self::$loaded as $rule) {
				$match = false;

				// Loop through all the criteria hoping to make a match.
				foreach ($rule->criteria as $criterion) {
					if (
						(
							$criterion['t'] == 'mid'
							&& $criterion['v'] == $row['id_member_from']
						)
						|| (
							$criterion['t'] == 'gid'
							&& $criterion['v'] == $row['id_group']
						)
						|| (
							$criterion['t'] == 'sub'
							&& strpos($row['subject'], $criterion['v']) !== false
						)
						|| (
							$criterion['t'] == 'msg'
							&& strpos($row['body'], $criterion['v']) !== false
						)
					) {
						$match = true;
					}
					// If all criteria must match but one criterion doesn't, then we stop!
					elseif ($rule->logic == self::RULE_AND) {
						$match = false;
						break;
					}
				}

				// Criteria matched, so act on this message.
				if ($match) {
					if ($rule->delete) {
						$actions['deletes'][] = $row['id_pm'];
					} else {
						foreach ($rule->actions as $ruleAction) {
							if ($ruleAction['t'] == 'lab') {
								// Get a collection started.
								if (!isset($actions['labels'][$row['id_pm']])) {
									$actions['labels'][$row['id_pm']] = [];
								}

								$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
							}
						}
					}
				}
			}
		}
		Db::$db->free_result($request);

		// Deletes are easy!
		if (!empty($actions['deletes'])) {
			PM::delete($actions['deletes']);
		}

		// Relabel?
		if (!empty($actions['labels'])) {
			foreach ($actions['labels'] as $pm => $labels) {
				// Quickly check each label is valid!
				$realLabels = [];

				foreach (Utils::$context['labels'] as $label) {
					if (in_array($label['id'], $labels)) {
						$realLabels[] = $label['id'];
					}
				}

				if (!empty(Theme::$current->options['pm_remove_inbox_label'])) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}pm_recipients
						SET in_inbox = {int:in_inbox}
						WHERE id_pm = {int:id_pm}
							AND id_member = {int:me}',
						[
							'in_inbox' => 0,
							'id_pm' => $pm,
							'me' => User::$me->id,
						],
					);
				}

				$inserts = [];

				// Now we insert the label info
				foreach ($realLabels as $a_label) {
					$inserts[] = [$pm, $a_label];
				}

				Db::$db->insert(
					'ignore',
					'{db_prefix}pm_labeled_messages',
					['id_pm' => 'int', 'id_label' => 'int'],
					$inserts,
					['id_pm', 'id_label'],
				);
			}
		}
	}

	/**
	 * Deletes the given rules from the database.
	 *
	 * @param array $ids IDs of the rules to delete.
	 */
	public static function delete(array $ids): void
	{
		$ids = array_filter(array_map('intval', $ids));

		if (empty($ids)) {
			return;
		}

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}pm_rules
			WHERE id_rule IN ({array_int:delete_list})
				AND id_member = {int:me}',
			[
				'me' => User::$me->id,
				'delete_list' => $ids,
			],
		);

		foreach ($ids as $id) {
			unset(self::$loaded[$id]);
		}
	}

	/**
	 * List all rules, and allow adding, editing, etc...
	 */
	public static function manage(): void
	{
		// Give the template access to the rule limiters.
		Utils::$context['rule_limiters'] = self::LIMITS;

		// The link tree - gotta have this :o
		Utils::$context['linktree'][] = [
			'url' => Config::$scripturl . '?action=pm;sa=manrules',
			'name' => Lang::$txt['pm_manage_rules'],
		];

		Utils::$context['page_title'] = Lang::$txt['pm_manage_rules'];
		Utils::$context['sub_template'] = 'rules';

		// Load them... load them!!
		self::load();

		// Likely to need all the groups!
		Utils::$context['groups'] = [];

		$groups = Group::loadSimple();
		Group::loadModeratorsBatch(array_map(fn ($group) => $group->id, $groups));

		foreach ($groups as $group) {
			if ($group->hidden === Group::INVISIBLE && !$group->can_moderate) {
				continue;
			}

			Utils::$context['groups'][$group->id] = $group->name;
		}

		// Applying all rules?
		if (isset($_GET['apply'])) {
			User::$me->checkSession('get');
			Security::spamProtection('pm');

			self::apply(true);
			Utils::redirectexit('action=pm;sa=manrules');
		}

		// Editing a specific one?
		if (isset($_GET['add'])) {
			Utils::$context['rid'] = isset($_GET['rid']) && isset(self::$loaded[$_GET['rid']]) ? (int) $_GET['rid'] : 0;

			Utils::$context['sub_template'] = 'add_rule';

			// Current rule information...
			if (Utils::$context['rid']) {
				$rule = self::$loaded[Utils::$context['rid']];
				Utils::$context['rule'] = &$rule;

				$members = [];

				// Need to get member names!
				foreach ($rule->criteria as $k => $criteria) {
					if ($criteria['t'] == 'mid' && !empty($criteria['v'])) {
						$members[(int) $criteria['v']] = $k;
					}
				}

				if (!empty($members)) {
					$request = Db::$db->query(
						'',
						'SELECT id_member, member_name
						FROM {db_prefix}members
						WHERE id_member IN ({array_int:member_list})',
						[
							'member_list' => array_keys($members),
						],
					);

					while ($row = Db::$db->fetch_assoc($request)) {
						$rule->criteria[$members[$row['id_member']]]['v'] = $row['member_name'];
					}
					Db::$db->free_result($request);
				}
			} else {
				Utils::$context['rule'] = new self([
					'id' => 0,
					'name' => '',
					'criteria' => [],
					'actions' => [],
					'logic' => 'and',
				]);
			}
		}
		// Saving?
		elseif (isset($_GET['save'])) {
			User::$me->checkSession();

			$rid = isset($_GET['rid']) && isset(self::$loaded[$_GET['rid']]) ? (int) $_GET['rid'] : 0;

			$rule = !empty($rid) ? self::$loaded[$rid] : new self();

			// Name is easy!
			$rule->name = Utils::htmlspecialchars(trim($_POST['rule_name']));

			// Sanity check...
			if (empty($_POST['ruletype']) || empty($_POST['acttype'])) {
				ErrorHandler::fatalLang('pm_rule_no_criteria', false);
			}

			// Let's do the criteria first - it's also hardest!
			$rule->criteria = [];
			$criteriaCount = 0;

			foreach ($_POST['ruletype'] as $ind => $type) {
				// Check everything is here...
				if ($type == 'gid' && (!isset($_POST['ruledefgroup'][$ind]) || !isset(Utils::$context['groups'][$_POST['ruledefgroup'][$ind]]))) {
					continue;
				}

				if ($type != 'bud' && !isset($_POST['ruledef'][$ind])) {
					continue;
				}

				// Too many criteria in this rule.
				if ($criteriaCount++ >= self::LIMITS['criteria']) {
					break;
				}

				// Members need to be found.
				if ($type == 'mid') {
					$name = trim($_POST['ruledef'][$ind]);

					$request = Db::$db->query(
						'',
						'SELECT id_member
						FROM {db_prefix}members
						WHERE real_name = {string:member_name}
							OR member_name = {string:member_name}',
						[
							'member_name' => $name,
						],
					);

					if (Db::$db->num_rows($request) == 0) {
						Lang::load('Errors');
						ErrorHandler::fatalLang('invalid_username', false);
					}
					list($memID) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					$rule->criteria[] = [
						't' => 'mid',
						'v' => $memID,
					];
				} elseif ($type == 'bud') {
					$rule->criteria[] = [
						't' => 'bud',
						'v' => 1,
					];
				} elseif ($type == 'gid') {
					$rule->criteria[] = [
						't' => 'gid',
						'v' => (int) $_POST['ruledefgroup'][$ind],
					];
				} elseif (in_array($type, ['sub', 'msg']) && trim($_POST['ruledef'][$ind]) != '') {
					$rule->criteria[] = [
						't' => $type,
						'v' => Utils::htmlspecialchars(trim($_POST['ruledef'][$ind])),
					];
				}
			}

			// Also do the actions!
			$rule->actions = [];
			$rule->delete = false;
			$rule->logic = $_POST['rule_logic'] == 'or' ? true : false;
			$actionCount = 0;

			foreach ($_POST['acttype'] as $ind => $type) {
				// Picking a valid label?
				if (
					$type == 'lab'
					&& (
						!ctype_digit((string) $ind)
						|| !isset($_POST['labdef'][$ind])
						|| $_POST['labdef'][$ind] == ''
						|| !isset(Label::$loaded[$_POST['labdef'][$ind]])
					)
				) {
					continue;
				}

				// Too many actions in this rule.
				if ($actionCount++ >= self::LIMITS['actions']) {
					break;
				}

				// Record what we're doing.
				if ($type == 'del') {
					$rule->delete = true;
				} elseif ($type == 'lab') {
					$rule->actions[] = [
						't' => 'lab',
						'v' => (int) $_POST['labdef'][$ind],
					];
				}
			}

			$rule->save();

			Utils::redirectexit('action=pm;sa=manrules');
		}
		// Deleting?
		elseif (isset($_POST['delselected']) && !empty($_POST['delrule'])) {
			User::$me->checkSession();

			self::delete(array_keys($_POST['delrule']));

			Utils::redirectexit('action=pm;sa=manrules');
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Rule::exportStatic')) {
	Rule::exportStatic();
}

?>