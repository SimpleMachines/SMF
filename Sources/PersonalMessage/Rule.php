<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\PersonalMessage;

use SMF\ArrayAccessHelper;
use SMF\BackwardCompatibility;

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

/**
 * Represents a sorting rule that can be applied to incoming personal messages.
 */
class Rule implements \ArrayAccess
{
	use BackwardCompatibility, ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => 'loadRules',
			'apply' => 'applyRules',
		),
	);

	/*****************
	 * Class constants
	 *****************/

	/**
	 * Whether a rule's criteria are combined using AND or OR logic.
	 */
	const RULE_AND = false;
	const RULE_OR = true;

	/**
	 * Maximum number of criteria and actions allowed per rule.
	 */
	const LIMITS = array(
		'criteria' => 10,
		'actions' => 10,
	);

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
	public array $criteria = array();

	/**
	 * @var array
	 *
	 * Actions that this rule performs.
	 */
	public array $actions = array();

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
	public static array $loaded = array();

	/**
	 * @var array
	 *
	 * Alternate names for some object properties.
	 */
	protected array $prop_aliases = array(
		'id_rule' => 'id',
		'id_member' => 'member',
		'rule_name' => 'name',
		'delete_pm' => 'delete',
		'is_or' => 'logic',
	);

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $props Properties to set for this rule.
	 */
	public function __construct(array $props = array())
	{
		$this->set($props);

		// Default to the current user.
		$this->member = $this->member ?? User::$me->id;

		if (!empty($this->id))
			self::$loaded[$this->id] = $this;
	}

	/**
	 * Save this rule to the database.
	 */
	public function save(): void
	{
		if (empty($this->name))
			fatal_lang_error('pm_rule_no_name', false);

		if (empty($this->criteria) || (empty($this->actions) && !$this->delete))
			fatal_lang_error('pm_rule_no_criteria', false);

		if (empty($this->id))
		{
			$this->id = Db::$db->insert('',
				'{db_prefix}pm_rules',
				array(
					'id_member' => 'int',
					'rule_name' => 'string',
					'criteria' => 'string',
					'actions' => 'string',
					'delete_pm' => 'int',
					'is_or' => 'int',
				),
				array(
					$this->member,
					$this->name,
					Utils::jsonEncode($this->criteria),
					Utils::jsonEncode($this->actions),
					(int) $this->delete,
					(int) $this->logic,
				),
				array('id_rule'),
				1
			);

			self::$loaded[$this->id] = $this;
		}
		else
		{
			Db::$db->query('', '
				UPDATE {db_prefix}pm_rules
				SET
					rule_name = {string:rule_name},
					criteria = {string:criteria},
					actions = {string:actions},
					delete_pm = {int:delete_pm},
					is_or = {int:is_or}
				WHERE id_rule = {int:id_rule}
					AND id_member = {int:current_member}',
				array(
					'id_rule' => $this->id,
					'current_member' => $this->member,
					'rule_name' => $this->name,
					'criteria' => Utils::jsonEncode($this->criteria),
					'actions' => Utils::jsonEncode($this->actions),
					'delete_pm' => (int) $this->delete,
					'is_or' => (int) $this->logic,
				)
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
		if (!empty(self::$loaded) && !$reload)
			return self::$loaded;

		self::$loaded = array();
		Utils::$context['rules'] = &self::$loaded;

		$request = Db::$db->query('', '
			SELECT
				id_rule, rule_name, criteria, actions, delete_pm, is_or
			FROM {db_prefix}pm_rules
			WHERE id_member = {int:me}',
			array(
				'me' => User::$me->id,
			)
		);
		// Simply fill in the data!
		while ($row = Db::$db->fetch_assoc($request))
		{
			$row['criteria'] = Utils::jsonDecode($row['criteria'], true);
			$row['actions'] = Utils::jsonDecode($row['actions'], true);

			if ($row['delete_pm'])
			{
				$row['actions'][] = array(
					't' => 'del',
					'v' => 1,
				);
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
		if (empty(self::$loaded))
			return;

		// Just unread ones?
		$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';

		// @todo Apply all should have timeout protection!
		// Get all the messages that match this.
		$actions = array();

		$request = Db::$db->query('', '
			SELECT
				pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pmr.id_member = {int:me}
				AND pmr.deleted = {int:not_deleted}
				' . $ruleQuery,
			array(
				'me' => User::$me->id,
				'not_deleted' => 0,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			foreach (self::$loaded as $rule)
			{
				$match = false;

				// Loop through all the criteria hoping to make a match.
				foreach ($rule->criteria as $criterion)
				{
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
					)
					{
						$match = true;
					}
					// If all criteria must match but one criterion doesn't, then we stop!
					elseif ($rule->logic == self::RULE_AND)
					{
						$match = false;
						break;
					}
				}

				// Criteria matched, so act on this message.
				if ($match)
				{
					if ($rule->delete)
					{
						$actions['deletes'][] = $row['id_pm'];
					}
					else
					{
						foreach ($rule->actions as $ruleAction)
						{
							if ($ruleAction['t'] == 'lab')
							{
								// Get a collection started.
								if (!isset($actions['labels'][$row['id_pm']]))
									$actions['labels'][$row['id_pm']] = array();

								$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
							}
						}
					}
				}
			}
		}
		Db::$db->free_result($request);

		// Deletes are easy!
		if (!empty($actions['deletes']))
			PM::delete($actions['deletes']);

		// Relabel?
		if (!empty($actions['labels']))
		{
			foreach ($actions['labels'] as $pm => $labels)
			{
				// Quickly check each label is valid!
				$realLabels = array();

				foreach (Utils::$context['labels'] as $label)
				{
					if (in_array($label['id'], $labels))
						$realLabels[] = $label['id'];
				}

				if (!empty(Theme::$current->options['pm_remove_inbox_label']))
				{
					Db::$db->query('', '
						UPDATE {db_prefix}pm_recipients
						SET in_inbox = {int:in_inbox}
						WHERE id_pm = {int:id_pm}
							AND id_member = {int:me}',
						array(
							'in_inbox' => 0,
							'id_pm' => $pm,
							'me' => User::$me->id,
						)
					);
				}

				$inserts = array();

				// Now we insert the label info
				foreach ($realLabels as $a_label)
					$inserts[] = array($pm, $a_label);

				Db::$db->insert('ignore',
					'{db_prefix}pm_labeled_messages',
					array('id_pm' => 'int', 'id_label' => 'int'),
					$inserts,
					array('id_pm', 'id_label')
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

		if (empty($ids))
			return;

		Db::$db->query('', '
			DELETE FROM {db_prefix}pm_rules
			WHERE id_rule IN ({array_int:delete_list})
				AND id_member = {int:me}',
			array(
				'me' => User::$me->id,
				'delete_list' => $ids,
			)
		);

		foreach ($ids as $id)
			unset(self::$loaded[$id]);
	}

	/**
	 * List all rules, and allow adding, editing, etc...
	 */
	public static function manage(): void
	{
		// Give the template access to the rule limiters.
		Utils::$context['rule_limiters'] = self::LIMITS;

		// The link tree - gotta have this :o
		Utils::$context['linktree'][] = array(
			'url' => Config::$scripturl . '?action=pm;sa=manrules',
			'name' => Lang::$txt['pm_manage_rules']
		);

		Utils::$context['page_title'] = Lang::$txt['pm_manage_rules'];
		Utils::$context['sub_template'] = 'rules';

		// Load them... load them!!
		self::load();

		// Likely to need all the groups!
		Utils::$context['groups'] = array();

		$request = Db::$db->query('', '
			SELECT mg.id_group, mg.group_name, COALESCE(gm.id_member, 0) AS can_moderate, mg.hidden
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:me})
			WHERE mg.min_posts = {int:min_posts}
				AND mg.id_group != {int:moderator_group}
				AND mg.hidden = {int:not_hidden}
			ORDER BY mg.group_name',
			array(
				'me' => User::$me->id,
				'min_posts' => -1,
				'moderator_group' => 3,
				'not_hidden' => 0,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			// Hide hidden groups!
			if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
				continue;

			Utils::$context['groups'][$row['id_group']] = $row['group_name'];
		}
		Db::$db->free_result($request);

		// Applying all rules?
		if (isset($_GET['apply']))
		{
			checkSession('get');
			spamProtection('pm');

			self::apply(true);
			redirectexit('action=pm;sa=manrules');
		}

		// Editing a specific one?
		if (isset($_GET['add']))
		{
			Utils::$context['rid'] = isset($_GET['rid']) && isset(self::$loaded[$_GET['rid']]) ? (int) $_GET['rid'] : 0;

			Utils::$context['sub_template'] = 'add_rule';

			// Current rule information...
			if (Utils::$context['rid'])
			{
				$rule = self::$loaded[Utils::$context['rid']];
				Utils::$context['rule'] = &$rule;

				$members = array();

				// Need to get member names!
				foreach ($rule->criteria as $k => $criteria)
				{
					if ($criteria['t'] == 'mid' && !empty($criteria['v']))
						$members[(int) $criteria['v']] = $k;
				}

				if (!empty($members))
				{
					$request = Db::$db->query('', '
						SELECT id_member, member_name
						FROM {db_prefix}members
						WHERE id_member IN ({array_int:member_list})',
						array(
							'member_list' => array_keys($members),
						)
					);
					while ($row = Db::$db->fetch_assoc($request))
					{
						$rule->criteria[$members[$row['id_member']]]['v'] = $row['member_name'];
					}
					Db::$db->free_result($request);
				}
			}
			else
			{
				Utils::$context['rule'] = new self(array(
					'id' => 0,
					'name' => '',
					'criteria' => array(),
					'actions' => array(),
					'logic' => 'and',
				));
			}
		}
		// Saving?
		elseif (isset($_GET['save']))
		{
			checkSession();

			$rid = isset($_GET['rid']) && isset(self::$loaded[$_GET['rid']]) ? (int) $_GET['rid'] : 0;

			$rule = !empty($rid) ? self::$loaded[$rid] : new self();

			// Name is easy!
			$rule->name = Utils::htmlspecialchars(trim($_POST['rule_name']));

			// Sanity check...
			if (empty($_POST['ruletype']) || empty($_POST['acttype']))
				fatal_lang_error('pm_rule_no_criteria', false);

			// Let's do the criteria first - it's also hardest!
			$rule->criteria = array();
			$criteriaCount = 0;

			foreach ($_POST['ruletype'] as $ind => $type)
			{
				// Check everything is here...
				if ($type == 'gid' && (!isset($_POST['ruledefgroup'][$ind]) || !isset(Utils::$context['groups'][$_POST['ruledefgroup'][$ind]])))
				{
					continue;
				}

				if ($type != 'bud' && !isset($_POST['ruledef'][$ind]))
					continue;

				// Too many criteria in this rule.
				if ($criteriaCount++ >= self::LIMITS['criteria'])
					break;

				// Members need to be found.
				if ($type == 'mid')
				{
					$name = trim($_POST['ruledef'][$ind]);

					$request = Db::$db->query('', '
						SELECT id_member
						FROM {db_prefix}members
						WHERE real_name = {string:member_name}
							OR member_name = {string:member_name}',
						array(
							'member_name' => $name,
						)
					);
					if (Db::$db->num_rows($request) == 0)
					{
						Lang::load('Errors');
						fatal_lang_error('invalid_username', false);
					}
					list($memID) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					$rule->criteria[] = array(
						't' => 'mid',
						'v' => $memID,
					);
				}
				elseif ($type == 'bud')
				{
					$rule->criteria[] = array(
						't' => 'bud',
						'v' => 1,
					);
				}
				elseif ($type == 'gid')
				{
					$rule->criteria[] = array(
						't' => 'gid',
						'v' => (int) $_POST['ruledefgroup'][$ind],
					);
				}
				elseif (in_array($type, array('sub', 'msg')) && trim($_POST['ruledef'][$ind]) != '')
				{
					$rule->criteria[] = array(
						't' => $type,
						'v' => Utils::htmlspecialchars(trim($_POST['ruledef'][$ind])),
					);
				}
			}

			// Also do the actions!
			$rule->actions = array();
			$rule->delete = false;
			$rule->logic = $_POST['rule_logic'] == 'or' ? true : false;
			$actionCount = 0;

			foreach ($_POST['acttype'] as $ind => $type)
			{
				// Picking a valid label?
				if (
					$type == 'lab'
					&& (
						!ctype_digit((string) $ind)
						|| !isset($_POST['labdef'][$ind])
						|| $_POST['labdef'][$ind] == ''
						|| !isset(self::$labels[$_POST['labdef'][$ind]])
					)
				)
				{
					continue;
				}

				// Too many actions in this rule.
				if ($actionCount++ >= self::LIMITS['actions'])
					break;

				// Record what we're doing.
				if ($type == 'del')
				{
					$rule->delete = true;
				}
				elseif ($type == 'lab')
				{
					$rule->actions[] = array(
						't' => 'lab',
						'v' => (int) $_POST['labdef'][$ind],
					);
				}
			}

			$rule->save();

			redirectexit('action=pm;sa=manrules');
		}
		// Deleting?
		elseif (isset($_POST['delselected']) && !empty($_POST['delrule']))
		{
			checkSession();

			self::delete(array_keys($_POST['delrule']));

			redirectexit('action=pm;sa=manrules');
		}
	}

}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Rule::exportStatic'))
	Rule::exportStatic();

?>