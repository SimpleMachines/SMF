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
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Menu;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Shows a list of members or a selection of members.
 */
class Members implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ViewMembers',
			'list_getMembers' => 'list_getMembers',
			'list_getNumMembers' => 'list_getNumMembers',
			'viewMemberlist' => 'ViewMemberlist',
			'adminApprove' => 'AdminApprove',
			'membersAwaitingActivation' => 'MembersAwaitingActivation',
			'searchMembers' => 'SearchMembers',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'all';

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $show_activate = false;

	/**
	 * @var bool
	 *
	 *
	 */
	public bool $show_approve = false;

	/**
	 * @var array
	 *
	 *
	 */
	public array $activation_numbers = [];

	/**
	 * @var int
	 *
	 *
	 */
	public int $awaiting_activation = 0;

	/**
	 * @var int
	 *
	 *
	 */
	public int $awaiting_approval = 0;

	/**
	 * @var array
	 *
	 *
	 */
	public array $membergroups = [];

	/**
	 * @var array
	 *
	 *
	 */
	public array $postgroups = [];

	/**
	 * @var int
	 *
	 *
	 */
	public int $current_filter = -1;

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Format: 'sa' => array('method', 'required_permission')
	 */
	public static array $subactions = [
		'all' => ['view', 'moderate_forum'],
		'approve' => ['approve', 'moderate_forum'],
		'browse' => ['browse', 'moderate_forum'],
		'search' => ['search', 'moderate_forum'],
		'query' => ['view', 'moderate_forum'],
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
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction][0]) ? [$this, self::$subactions[$this->subaction][0]] : Utils::getCallable(self::$subactions[$this->subaction][0]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * View all members list. It allows sorting on several columns, and deletion of
	 * selected members. It also handles the search query sent by
	 * ?action=admin;area=viewmembers;sa=search.
	 * Called by ?action=admin;area=viewmembers;sa=all or ?action=admin;area=viewmembers;sa=query.
	 * Requires the moderate_forum permission.
	 */
	public function view()
	{
		// Are we performing a delete?
		if (isset($_POST['delete_members']) && !empty($_POST['delete']) && User::$me->allowedTo('profile_remove_any')) {
			User::$me->checkSession();

			// Clean the input.
			foreach ($_POST['delete'] as $key => $value) {
				// Don't delete yourself, idiot.
				if ($value != User::$me->id) {
					$delete[$key] = (int) $value;
				}
			}

			if (!empty($delete)) {
				// Delete all the selected members.
				User::delete($delete, true);
			}
		}

		// Check input after a member search has been submitted.
		if ($this->subaction == 'query') {
			// Retrieving the membergroups and postgroups.
			$this->membergroups = [];
			$this->postgroups = [];

			foreach (Group::loadSimple(Group::LOAD_BOTH, [Group::GUEST, Group::MOD]) as $group) {
				if ($group->min_posts == -1) {
					$this->membergroups[] = $group;
				} else {
					$this->postgroups[] = $group;
				}
			}

			// Some data about the form fields and how they are linked to the database.
			$params = [
				'mem_id' => [
					'db_fields' => ['id_member'],
					'type' => 'int',
					'range' => true,
				],
				'age' => [
					'db_fields' => ['birthdate'],
					'type' => 'age',
					'range' => true,
				],
				'posts' => [
					'db_fields' => ['posts'],
					'type' => 'int',
					'range' => true,
				],
				'reg_date' => [
					'db_fields' => ['date_registered'],
					'type' => 'date',
					'range' => true,
				],
				'last_online' => [
					'db_fields' => ['last_login'],
					'type' => 'date',
					'range' => true,
				],
				'activated' => [
					'db_fields' => ['CASE WHEN is_activated IN (1, 11) THEN 1 ELSE 0 END'],
					'type' => 'checkbox',
					'values' => ['0', '1'],
				],
				'membername' => [
					'db_fields' => ['member_name', 'real_name'],
					'type' => 'string',
				],
				'email' => [
					'db_fields' => ['email_address'],
					'type' => 'string',
				],
				'website' => [
					'db_fields' => ['website_title', 'website_url'],
					'type' => 'string',
				],
				'ip' => [
					'db_fields' => ['member_ip'],
					'type' => 'inet',
				],
				'membergroups' => [
					'db_fields' => ['id_group'],
					'type' => 'groups',
				],
				'postgroups' => [
					'db_fields' => ['id_group'],
					'type' => 'groups',
				],
			];
			$range_trans = [
				'--' => '<',
				'-' => '<=',
				'=' => '=',
				'+' => '>=',
				'++' => '>',
			];

			IntegrationHook::call('integrate_view_members_params', [&$params]);

			$search_params = [];

			if ($this->subaction == 'query' && !empty($_REQUEST['params']) && empty($_POST['types'])) {
				$search_params = Utils::jsonDecode(base64_decode($_REQUEST['params']), true);
			} elseif (!empty($_POST)) {
				$search_params['types'] = $_POST['types'];

				foreach ($params as $param_name => $param_info) {
					if (isset($_POST[$param_name])) {
						$search_params[$param_name] = $_POST[$param_name];
					}
				}
			}

			$search_url_params = isset($search_params) ? base64_encode(Utils::jsonEncode($search_params)) : null;

			// @todo Validate a little more.

			// Loop through every field of the form.
			$query_parts = [];
			$where_params = [];

			foreach ($params as $param_name => $param_info) {
				// Not filled in?
				if (!isset($search_params[$param_name]) || $search_params[$param_name] === '') {
					continue;
				}

				// Make sure numeric values are really numeric.
				if (in_array($param_info['type'], ['int', 'age'])) {
					$search_params[$param_name] = (int) $search_params[$param_name];
				}
				// Date values have to match a date format that PHP recognizes.
				elseif ($param_info['type'] == 'date') {
					$search_params[$param_name] = strtotime($search_params[$param_name] . ' ' . User::getTimezone());

					if (!is_int($search_params[$param_name])) {
						continue;
					}
				} elseif ($param_info['type'] == 'inet') {
					$search_params[$param_name] = IP::ip2range($search_params[$param_name]);

					if (empty($search_params[$param_name])) {
						continue;
					}
				}

				// Those values that are in some kind of range (<, <=, =, >=, >).
				if (!empty($param_info['range'])) {
					// Default to '=', just in case...
					if (empty($range_trans[$search_params['types'][$param_name]])) {
						$search_params['types'][$param_name] = '=';
					}

					// Handle special case 'age'.
					if ($param_info['type'] == 'age') {
						// All people that were born between $lowerlimit and $upperlimit are currently the specified age.
						$datearray = getdate(time());
						$upperlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $search_params[$param_name], $datearray['mon'], $datearray['mday']);
						$lowerlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $search_params[$param_name] - 1, $datearray['mon'], $datearray['mday']);

						if (in_array($search_params['types'][$param_name], ['-', '--', '='])) {
							$query_parts[] = ($param_info['db_fields'][0]) . ' > {string:' . $param_name . '_minlimit}';
							$where_params[$param_name . '_minlimit'] = ($search_params['types'][$param_name] == '--' ? $upperlimit : $lowerlimit);
						}

						if (in_array($search_params['types'][$param_name], ['+', '++', '='])) {
							$query_parts[] = ($param_info['db_fields'][0]) . ' <= {string:' . $param_name . '_pluslimit}';
							$where_params[$param_name . '_pluslimit'] = ($search_params['types'][$param_name] == '++' ? $lowerlimit : $upperlimit);

							// Make sure that members that didn't set their birth year are not queried.
							$query_parts[] = ($param_info['db_fields'][0]) . ' > {date:dec_zero_date}';
							$where_params['dec_zero_date'] = '0004-12-31';
						}
					}
					// Special case - equals a date.
					elseif ($param_info['type'] == 'date') {
						if ($search_params['types'][$param_name] == '=') {
							$query_parts[] = $param_info['db_fields'][0] . ' >= ' . $search_params[$param_name] . ' AND ' . $param_info['db_fields'][0] . ' < ' . ($search_params[$param_name] + 86400);
						}
						// Less than or equal to
						elseif ($search_params['types'][$param_name] == '-') {
							$query_parts[] = $param_info['db_fields'][0] . ' < ' . ($search_params[$param_name] + 86400);
						}
						// Greater than
						elseif ($search_params['types'][$param_name] == '++') {
							$query_parts[] = $param_info['db_fields'][0] . ' >= ' . ($search_params[$param_name] + 86400);
						} else {
							$query_parts[] = $param_info['db_fields'][0] . ' ' . $range_trans[$search_params['types'][$param_name]] . ' ' . $search_params[$param_name];
						}
					} else {
						$query_parts[] = $param_info['db_fields'][0] . ' ' . $range_trans[$search_params['types'][$param_name]] . ' ' . $search_params[$param_name];
					}
				}
				// Checkboxes.
				elseif ($param_info['type'] == 'checkbox') {
					// Each checkbox or no checkbox at all is checked -> ignore.
					if (
						!is_array($search_params[$param_name])
						|| count($search_params[$param_name]) == 0
						|| count($search_params[$param_name]) == count($param_info['values'])
					) {
						continue;
					}

					$query_parts[] = ($param_info['db_fields'][0]) . ' IN ({array_string:' . $param_name . '_check})';

					$where_params[$param_name . '_check'] = $search_params[$param_name];
				}
				// INET.
				elseif ($param_info['type'] == 'inet') {
					if (count($search_params[$param_name]) === 1) {
						$query_parts[] = '(' . $param_info['db_fields'][0] . ' = {inet:' . $param_name . '})';

						$where_params[$param_name] = $search_params[$param_name][0];
					} elseif (count($search_params[$param_name]) === 2) {
						$query_parts[] = '(' . $param_info['db_fields'][0] . ' <= {inet:' . $param_name . '_high} and ' . $param_info['db_fields'][0] . ' >= {inet:' . $param_name . '_low})';

						$where_params[$param_name . '_low'] = $search_params[$param_name]['low'];
						$where_params[$param_name . '_high'] = $search_params[$param_name]['high'];
					}
				} elseif ($param_info['type'] != 'groups') {
					// Replace the wildcard characters ('*' and '?') into MySQL ones.
					$parameter = strtolower(strtr(Utils::htmlspecialchars($search_params[$param_name], ENT_QUOTES), ['%' => '\\%', '_' => '\\_', '*' => '%', '?' => '_']));

					if (Db::$db->case_sensitive) {
						$query_parts[] = '(LOWER(' . implode(') LIKE {string:' . $param_name . '_normal} OR LOWER(', $param_info['db_fields']) . ') LIKE {string:' . $param_name . '_normal})';
					} else {
						$query_parts[] = '(' . implode(' LIKE {string:' . $param_name . '_normal} OR ', $param_info['db_fields']) . ' LIKE {string:' . $param_name . '_normal})';
					}

					$where_params[$param_name . '_normal'] = '%' . $parameter . '%';
				}
			}

			// Set up the membergroup query part.
			$mg_query_parts = [];

			// Primary membergroups, but only if at least was was not selected.
			if (!empty($search_params['membergroups'][1]) && count($this->membergroups) != count($search_params['membergroups'][1])) {
				$mg_query_parts[] = 'mem.id_group IN ({array_int:group_check})';
				$where_params['group_check'] = $search_params['membergroups'][1];
			}

			// Additional membergroups (these are only relevant if not all primary groups where selected!).
			if (
				!empty($search_params['membergroups'][2])
				&& (
					empty($search_params['membergroups'][1])
					|| count($this->membergroups) != count($search_params['membergroups'][1])
				)
			) {
				foreach ($search_params['membergroups'][2] as $mg) {
					$mg_query_parts[] = 'FIND_IN_SET({int:add_group_' . $mg . '}, mem.additional_groups) != 0';
					$where_params['add_group_' . $mg] = $mg;
				}
			}

			// Combine the one or two membergroup parts into one query part linked with an OR.
			if (!empty($mg_query_parts)) {
				$query_parts[] = '(' . implode(' OR ', $mg_query_parts) . ')';
			}

			// Get all selected post count related membergroups.
			if (!empty($search_params['postgroups']) && count($search_params['postgroups']) != count($this->postgroups)) {
				$query_parts[] = 'id_post_group IN ({array_int:post_groups})';
				$where_params['post_groups'] = $search_params['postgroups'];
			}

			// Construct the where part of the query.
			$where = empty($query_parts) ? '1=1' : implode('
				AND ', $query_parts);
		} else {
			$search_url_params = null;
		}

		// Construct the additional URL part with the query info in it.
		$params_url = $this->subaction == 'query' ? ';sa=query;params=' . $search_url_params : '';

		// Get the title and sub template ready..
		Utils::$context['page_title'] = Lang::$txt['admin_members'];

		$listOptions = [
			'id' => 'member_list',
			'title' => Lang::$txt['members_list'],
			'items_per_page' => Config::$modSettings['defaultMaxMembers'],
			'base_href' => Config::$scripturl . '?action=admin;area=viewmembers' . $params_url,
			'default_sort_col' => 'user_name',
			'get_items' => [
				'function' => __CLASS__ . '::list_getMembers',
				'params' => [
					$where ?? '1=1',
					$where_params ?? [],
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumMembers',
				'params' => [
					$where ?? '1=1',
					$where_params ?? [],
				],
			],
			'columns' => [
				'id_member' => [
					'header' => [
						'value' => Lang::$txt['member_id'],
					],
					'data' => [
						'db' => 'id_member',
					],
					'sort' => [
						'default' => 'id_member',
						'reverse' => 'id_member DESC',
					],
				],
				'user_name' => [
					'header' => [
						'value' => Lang::$txt['username'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . strtr(Config::$scripturl, ['%' => '%%']) . '?action=profile;u=%1$d">%2$s</a>',
							'params' => [
								'id_member' => false,
								'member_name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_name',
						'reverse' => 'member_name DESC',
					],
				],
				'display_name' => [
					'header' => [
						'value' => Lang::$txt['display_name'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . strtr(Config::$scripturl, ['%' => '%%']) . '?action=profile;u=%1$d">%2$s</a>',
							'params' => [
								'id_member' => false,
								'real_name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'real_name',
						'reverse' => 'real_name DESC',
					],
				],
				'email' => [
					'header' => [
						'value' => Lang::$txt['email_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="mailto:%1$s">%1$s</a>',
							'params' => [
								'email_address' => true,
							],
						],
					],
					'sort' => [
						'default' => 'email_address',
						'reverse' => 'email_address DESC',
					],
				],
				'ip' => [
					'header' => [
						'value' => Lang::$txt['ip_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . strtr(Config::$scripturl, ['%' => '%%']) . '?action=trackip;searchip=%1$s">%1$s</a>',
							'params' => [
								'member_ip' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_ip',
						'reverse' => 'member_ip DESC',
					],
				],
				'last_active' => [
					'header' => [
						'value' => Lang::$txt['viewmembers_online'],
					],
					'data' => [
						'function' => function ($rowData) {
							// Calculate number of days since last online.
							if (empty($rowData['last_login'])) {
								$difference = Lang::$txt['never'];
							} else {
								$tz = timezone_open(User::getTimezone());
								$today = new \DateTime('today', $tz);
								$prev = (new \DateTime('@' . $rowData['last_login']))->setTimezone($tz)->setTime(0, 0, 0, 0);

								$num_days_difference = $prev->diff($today)->days;

								// Today.
								if (empty($num_days_difference)) {
									$difference = Lang::$txt['viewmembers_today'];
								}
								// Yesterday.
								elseif ($num_days_difference == 1) {
									$difference = sprintf('1 %1$s', Lang::$txt['viewmembers_day_ago']);
								}
								// X days ago.
								else {
									$difference = sprintf('%1$d %2$s', $num_days_difference, Lang::$txt['viewmembers_days_ago']);
								}
							}

							// Show it in italics if they're not activated...
							if ($rowData['is_activated'] % 10 != 1) {
								$difference = sprintf('<em title="%1$s">%2$s</em>', Lang::$txt['not_activated'], $difference);
							}

							return $difference;
						},
					],
					'sort' => [
						'default' => 'last_login DESC',
						'reverse' => 'last_login',
					],
				],
				'posts' => [
					'header' => [
						'value' => Lang::$txt['member_postcount'],
					],
					'data' => [
						'db' => 'posts',
					],
					'sort' => [
						'default' => 'posts',
						'reverse' => 'posts DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							return '<input type="checkbox" name="delete[]" value="' . $rowData['id_member'] . '"' . ($rowData['id_member'] == User::$me->id || $rowData['id_group'] == 1 || in_array(1, explode(',', $rowData['additional_groups'])) ? ' disabled' : '') . '>';
						},
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=viewmembers' . $params_url,
				'include_start' => true,
				'include_sort' => true,
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="delete_members" value="' . Lang::$txt['admin_delete_members'] . '" data-confirm="' . Lang::$txt['confirm_delete_members'] . '" class="button you_sure">',
				],
			],
		];

		// Without enough permissions, don't show 'delete members' checkboxes.
		if (!User::$me->allowedTo('profile_remove_any')) {
			unset($listOptions['cols']['check'], $listOptions['form'], $listOptions['additional_rows']);
		}

		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'member_list';
	}

	/**
	 * Search the member list, using one or more criteria.
	 * Called by ?action=admin;area=viewmembers;sa=search.
	 * Requires the moderate_forum permission.
	 * The form is submitted to action=admin;area=viewmembers;sa=query.
	 */
	public function search()
	{
		// Get a list of all the membergroups and postgroups that can be selected.
		$this->membergroups = [];
		$this->postgroups = [];

		foreach (Group::loadSimple(Group::LOAD_BOTH, [Group::GUEST, Group::MOD]) as $group) {
			if ($group->min_posts == -1) {
				$this->membergroups[] = $group;
			} else {
				$this->postgroups[] = $group;
			}
		}

		Utils::$context['page_title'] = Lang::$txt['admin_members'];
		Utils::$context['sub_template'] = 'search_members';
	}

	/**
	 * Lists all members who are awaiting approval/activation, sortable on different columns.
	 *
	 * It allows instant approval or activation of (a selection of) members.
	 * Called by ?action=admin;area=viewmembers;sa=browse;type=approve
	 *  or ?action=admin;area=viewmembers;sa=browse;type=activate.
	 * The form submits to ?action=admin;area=viewmembers;sa=approve.
	 * Requires the moderate_forum permission.
	 */
	public function browse()
	{
		// Not a lot here!
		Utils::$context['page_title'] = Lang::$txt['admin_members'];
		Utils::$context['sub_template'] = 'admin_browse';

		$browse_type = $_REQUEST['type'] ?? (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1 ? 'activate' : 'approve');

		if (isset(Utils::$context['tabs'][$browse_type])) {
			Utils::$context['tabs'][$browse_type]['is_selected'] = true;
		}

		// Allowed filters are those we can have, in theory.
		$allowed_filters = $browse_type == 'approve' ? [3, 4, 5] : [0, 2];

		$this->current_filter = isset($_REQUEST['filter']) && in_array($_REQUEST['filter'], $allowed_filters) && !empty($this->activation_numbers[$_REQUEST['filter']]) ? (int) $_REQUEST['filter'] : -1;

		// Sort out the different sub areas that we can actually filter by.
		$available_filters = [];

		foreach ($this->activation_numbers as $type => $amount) {
			// We have some of these...
			if (in_array($type, $allowed_filters) && $amount > 0) {
				$available_filters[] = [
					'type' => $type,
					'amount' => $amount,
					'desc' => Lang::$txt['admin_browse_filter_type_' . $type] ?? '?',
					'selected' => $type == $this->current_filter,
				];
			}
		}

		// If the filter was not sent, set it to whatever has people in it!
		if ($this->current_filter == -1 && !empty($available_filters[0]['amount'])) {
			$this->current_filter = $available_filters[0]['type'];
		}

		// This little variable is used to determine if we should flag where we are looking.
		$show_filter = ($this->current_filter != 0 && $this->current_filter != 3) || count($available_filters) > 1;

		// The columns that can be sorted.
		Utils::$context['columns'] = [
			'id_member' => ['label' => Lang::$txt['admin_browse_id']],
			'member_name' => ['label' => Lang::$txt['admin_browse_username']],
			'email_address' => ['label' => Lang::$txt['admin_browse_email']],
			'member_ip' => ['label' => Lang::$txt['admin_browse_ip']],
			'date_registered' => ['label' => Lang::$txt['admin_browse_registered']],
		];

		// Are we showing duplicate information?
		if (isset($_GET['showdupes'])) {
			$_SESSION['showdupes'] = (int) $_GET['showdupes'];
		}

		$show_duplicates = !empty($_SESSION['showdupes']);

		// Determine which actions we should allow on this page.
		if ($browse_type == 'approve') {
			// If we are approving deleted accounts we have a slightly different list... actually a mirror ;)
			if ($this->current_filter == 4) {
				$allowed_actions = [
					'reject' => Lang::$txt['admin_browse_w_approve_deletion'],
					'ok' => Lang::$txt['admin_browse_w_reject'],
				];
			} else {
				$allowed_actions = [
					'ok' => Lang::$txt['admin_browse_w_approve'] . ' ' . Lang::$txt['admin_browse_no_email'],
					'okemail' => Lang::$txt['admin_browse_w_approve'] . ' ' . Lang::$txt['admin_browse_w_email'],
					'require_activation' => Lang::$txt['admin_browse_w_approve_require_activate'],
					'reject' => Lang::$txt['admin_browse_w_reject'],
					'rejectemail' => Lang::$txt['admin_browse_w_reject'] . ' ' . Lang::$txt['admin_browse_w_email'],
				];
			}
		} elseif ($browse_type == 'activate') {
			$allowed_actions = [
				'ok' => Lang::$txt['admin_browse_w_activate'],
				'okemail' => Lang::$txt['admin_browse_w_activate'] . ' ' . Lang::$txt['admin_browse_w_email'],
				'delete' => Lang::$txt['admin_browse_w_delete'],
				'deleteemail' => Lang::$txt['admin_browse_w_delete'] . ' ' . Lang::$txt['admin_browse_w_email'],
				'remind' => Lang::$txt['admin_browse_w_remind'] . ' ' . Lang::$txt['admin_browse_w_email'],
			];
		}

		// Create an option list for actions allowed to be done with selected members.
		$action_options = '
				<option selected value="">' . Lang::$txt['admin_browse_with_selected'] . ':</option>
				<option value="" disabled>-----------------------------</option>';

		foreach ($allowed_actions as $key => $desc) {
			$action_options .= '
				<option value="' . $key . '">' . $desc . '</option>';
		}

		// Setup the Javascript function for selecting an action for the list.
		$javascript = '
			function onSelectChange()
			{
				if (document.forms.postForm.todo.value == "")
					return;

				var message = "";';

		// We have special messages for approving deletion of accounts - it's surprisingly logical - honest.
		if ($this->current_filter == 4) {
			$javascript .= '
				if (document.forms.postForm.todo.value.indexOf("reject") != -1)
					message = "' . Lang::$txt['admin_browse_w_delete'] . '";
				else
					message = "' . Lang::$txt['admin_browse_w_reject'] . '";';
		}
		// Otherwise a nice standard message.
		else {
			$javascript .= '
				if (document.forms.postForm.todo.value.indexOf("delete") != -1)
					message = "' . Lang::$txt['admin_browse_w_delete'] . '";
				else if (document.forms.postForm.todo.value.indexOf("reject") != -1)
					message = "' . Lang::$txt['admin_browse_w_reject'] . '";
				else if (document.forms.postForm.todo.value == "remind")
					message = "' . Lang::$txt['admin_browse_w_remind'] . '";
				else
					message = "' . ($browse_type == 'approve' ? Lang::$txt['admin_browse_w_approve'] : Lang::$txt['admin_browse_w_activate']) . '";';
		}

		$javascript .= '
				if (confirm(message + " ' . Lang::$txt['admin_browse_warn'] . '"))
					document.forms.postForm.submit();
			}';

		$listOptions = [
			'id' => 'approve_list',
			'items_per_page' => Config::$modSettings['defaultMaxMembers'],
			'base_href' => Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;type=' . $browse_type . (!empty($show_filter) ? ';filter=' . $this->current_filter : ''),
			'default_sort_col' => 'date_registered',
			'get_items' => [
				'function' => __CLASS__ . '::list_getMembers',
				'params' => [
					'is_activated = {int:activated_status}',
					['activated_status' => $this->current_filter],
					$show_duplicates,
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumMembers',
				'params' => [
					'is_activated = {int:activated_status}',
					['activated_status' => $this->current_filter],
				],
			],
			'columns' => [
				'id_member' => [
					'header' => [
						'value' => Lang::$txt['member_id'],
					],
					'data' => [
						'db' => 'id_member',
					],
					'sort' => [
						'default' => 'id_member',
						'reverse' => 'id_member DESC',
					],
				],
				'user_name' => [
					'header' => [
						'value' => Lang::$txt['username'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . strtr(Config::$scripturl, ['%' => '%%']) . '?action=profile;u=%1$d">%2$s</a>',
							'params' => [
								'id_member' => false,
								'member_name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_name',
						'reverse' => 'member_name DESC',
					],
				],
				'email' => [
					'header' => [
						'value' => Lang::$txt['email_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="mailto:%1$s">%1$s</a>',
							'params' => [
								'email_address' => true,
							],
						],
					],
					'sort' => [
						'default' => 'email_address',
						'reverse' => 'email_address DESC',
					],
				],
				'ip' => [
					'header' => [
						'value' => Lang::$txt['ip_address'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . strtr(Config::$scripturl, ['%' => '%%']) . '?action=trackip;searchip=%1$s">%1$s</a>',
							'params' => [
								'member_ip' => false,
							],
						],
					],
					'sort' => [
						'default' => 'member_ip',
						'reverse' => 'member_ip DESC',
					],
				],
				'hostname' => [
					'header' => [
						'value' => Lang::$txt['hostname'],
					],
					'data' => [
						'function' => function ($rowData) {
							$ip = new IP($rowData['member_ip']);

							return $ip->getHost();
						},
						'class' => 'smalltext',
					],
				],
				'date_registered' => [
					'header' => [
						'value' => $this->current_filter == 4 ? Lang::$txt['viewmembers_online'] : Lang::$txt['date_registered'],
					],
					'data' => [
						'function' => function ($rowData) {
							return Time::create('@' . $rowData[$this->current_filter == 4 ? 'last_login' : 'date_registered'])->format();
						},
					],
					'sort' => [
						'default' => $this->current_filter == 4 ? 'mem.last_login DESC' : 'date_registered DESC',
						'reverse' => $this->current_filter == 4 ? 'mem.last_login' : 'date_registered',
					],
				],
				'duplicates' => [
					'header' => [
						'value' => Lang::$txt['duplicates'],
						// Make sure it doesn't go too wide.
						'style' => 'width: 20%;',
					],
					'data' => [
						'function' => function ($rowData) {
							$member_links = [];

							foreach ($rowData['duplicate_members'] as $member) {
								if ($member['id']) {
									$member_links[] = '<a href="' . Config::$scripturl . '?action=profile;u=' . $member['id'] . '" ' . (!empty($member['is_banned']) ? 'class="red"' : '') . '>' . $member['name'] . '</a>';
								} else {
									$member_links[] = $member['name'] . ' (' . Lang::$txt['guest'] . ')';
								}
							}

							return implode(', ', $member_links);
						},
						'class' => 'smalltext',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="todoAction[]" value="%1$d">',
							'params' => [
								'id_member' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'javascript' => $javascript,
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=viewmembers;sa=approve;type=' . $browse_type,
				'name' => 'postForm',
				'include_start' => true,
				'include_sort' => true,
				'hidden_fields' => [
					'orig_filter' => $this->current_filter,
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
						<a href="' . Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;showdupes=' . ($show_duplicates ? 0 : 1) . ';type=' . $browse_type . (!empty($show_filter) ? ';filter=' . $this->current_filter : '') . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" class="button floatnone">' . ($show_duplicates ? Lang::$txt['dont_check_for_duplicate'] : Lang::$txt['check_for_duplicate']) . '</a>
						<select name="todo" onchange="onSelectChange();">
							' . $action_options . '
						</select>
						<noscript><input type="submit" value="' . Lang::$txt['go'] . '" class="button"><br class="clear_right"></noscript>
					',
					'class' => 'floatright',
				],
			],
		];

		// Pick what column to actually include if we're showing duplicates.
		if ($show_duplicates) {
			unset($listOptions['columns']['email']);
		} else {
			unset($listOptions['columns']['duplicates']);
		}

		// Only show hostname on duplicates as it takes a lot of time.
		if (!$show_duplicates || !empty(Config::$modSettings['disableHostnameLookup'])) {
			unset($listOptions['columns']['hostname']);
		}

		// Is there any need to show filters?
		if (isset($available_filters) && count($available_filters) > 1) {
			$filterOptions = '
				<strong>' . Lang::$txt['admin_browse_filter_by'] . ':</strong>
				<select name="filter" onchange="this.form.submit();">';

			foreach ($available_filters as $filter) {
				$filterOptions .= '
					<option value="' . $filter['type'] . '"' . ($filter['selected'] ? ' selected' : '') . '>' . $filter['desc'] . ' - ' . $filter['amount'] . ' ' . ($filter['amount'] == 1 ? Lang::$txt['user'] : Lang::$txt['users']) . '</option>';
			}

			$filterOptions .= '
				</select>
				<noscript><input type="submit" value="' . Lang::$txt['go'] . '" name="filter" class="button"></noscript>';

			$listOptions['additional_rows'][] = [
				'position' => 'top_of_list',
				'value' => $filterOptions,
				'class' => 'righttext',
			];
		}

		// What about if we only have one filter, but it's not the "standard" filter - show them what they are looking at.
		if (!empty($show_filter) && !empty($available_filters)) {
			$listOptions['additional_rows'][] = [
				'position' => 'above_column_headers',
				'value' => '<strong>' . Lang::$txt['admin_browse_filter_show'] . ':</strong> ' . ((isset($this->current_filter, Lang::$txt['admin_browse_filter_type_' . $this->current_filter])) ? Lang::$txt['admin_browse_filter_type_' . $this->current_filter] : $available_filters[0]['desc']),
				'class' => 'filter_row generic_list_wrapper smalltext',
			];
		}

		// Now that we have all the options, create the list.
		new ItemList($listOptions);
	}

	/**
	 * This method handles the approval, rejection, activation or deletion of members.
	 *
	 * Called by ?action=admin;area=viewmembers;sa=approve.
	 * Requires the moderate_forum permission.
	 * Redirects to ?action=admin;area=viewmembers;sa=browse
	 * with the same parameters as the calling page.
	 */
	public function approve()
	{
		// First, check our session.
		User::$me->checkSession();

		// We also need to the login languages here - for emails.
		Lang::load('Login');

		// Sort out where we are going...
		$current_filter = (int) $_REQUEST['orig_filter'];

		// If we are applying a filter do just that - then redirect.
		if (isset($_REQUEST['filter']) && $_REQUEST['filter'] != $_REQUEST['orig_filter']) {
			Utils::redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $_REQUEST['filter'] . ';start=' . $_REQUEST['start']);
		}

		// Nothing to do?
		if (!isset($_POST['todoAction']) && !isset($_POST['time_passed'])) {
			Utils::redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);
		}

		// Are we dealing with members who have been waiting for > set amount of time?
		if (isset($_POST['time_passed'])) {
			$timeBefore = time() - 86400 * (int) $_POST['time_passed'];

			$condition = '
				AND date_registered < {int:time_before}';
		}
		// Coming from checkboxes - validate the members passed through to us.
		else {
			$members = [];

			foreach ($_POST['todoAction'] as $id) {
				$members[] = (int) $id;
			}

			$condition = '
				AND id_member IN ({array_int:members})';
		}

		// Get information on each of the members, things that are important to us, like email address...
		$request = Db::$db->query(
			'',
			'SELECT id_member, member_name, real_name, email_address, validation_code, lngfile
			FROM {db_prefix}members
			WHERE is_activated = {int:activated_status}' . $condition . '
			ORDER BY lngfile',
			[
				'activated_status' => $current_filter,
				'time_before' => empty($timeBefore) ? 0 : $timeBefore,
				'members' => empty($members) ? [] : $members,
			],
		);

		$member_count = Db::$db->num_rows($request);

		// If no results then just return!
		if ($member_count == 0) {
			Utils::redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);
		}

		$member_info = [];
		$members = [];

		// Fill the info array.
		while ($row = Db::$db->fetch_assoc($request)) {
			$members[] = $row['id_member'];
			$member_info[] = [
				'id' => $row['id_member'],
				'username' => $row['member_name'],
				'name' => $row['real_name'],
				'email' => $row['email_address'],
				'language' => empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile'],
				'code' => $row['validation_code'],
			];
		}
		Db::$db->free_result($request);

		// Are we activating or approving the members?
		if ($_POST['todo'] == 'ok' || $_POST['todo'] == 'okemail') {
			// Approve/activate this member.
			Db::$db->query(
				'',
				'UPDATE {db_prefix}members
				SET validation_code = {string:blank_string}, is_activated = {int:is_activated}
				WHERE is_activated = {int:activated_status}' . $condition,
				[
					'is_activated' => 1,
					'time_before' => empty($timeBefore) ? 0 : $timeBefore,
					'members' => empty($members) ? [] : $members,
					'activated_status' => $current_filter,
					'blank_string' => '',
				],
			);

			// Do we have to let the integration code know about the activations?
			if (!empty(Config::$modSettings['integrate_activate'])) {
				foreach ($member_info as $member) {
					IntegrationHook::call('integrate_activate', [$member['username']]);
				}
			}

			// Check for email.
			if ($_POST['todo'] == 'okemail') {
				foreach ($member_info as $member) {
					$replacements = [
						'NAME' => $member['name'],
						'USERNAME' => $member['username'],
						'PROFILELINK' => Config::$scripturl . '?action=profile;u=' . $member['id'],
						'FORGOTPASSWORDLINK' => Config::$scripturl . '?action=reminder',
					];

					$emaildata = Mail::loadEmailTemplate('admin_approve_accept', $replacements, $member['language']);

					Mail::send($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accapp' . $member['id'], $emaildata['is_html'], 0);
				}
			}
		}
		// Maybe we're sending it off for activation?
		elseif ($_POST['todo'] == 'require_activation') {
			// We have to do this for each member I'm afraid.
			foreach ($member_info as $member) {
				// Generate a random activation code.
				$validation_code = User::generateValidationCode();

				// Set these members for activation - I know this includes two id_member checks but it's safer than bodging $condition ;).
				Db::$db->query(
					'',
					'UPDATE {db_prefix}members
					SET validation_code = {string:validation_code}, is_activated = {int:not_activated}
					WHERE is_activated = {int:activated_status}
						' . $condition . '
						AND id_member = {int:selected_member}',
					[
						'not_activated' => 0,
						'activated_status' => $current_filter,
						'selected_member' => $member['id'],
						'validation_code' => $validation_code,
						'time_before' => empty($timeBefore) ? 0 : $timeBefore,
						'members' => empty($members) ? [] : $members,
					],
				);

				$replacements = [
					'USERNAME' => $member['name'],
					'ACTIVATIONLINK' => Config::$scripturl . '?action=activate;u=' . $member['id'] . ';code=' . $validation_code,
					'ACTIVATIONLINKWITHOUTCODE' => Config::$scripturl . '?action=activate;u=' . $member['id'],
					'ACTIVATIONCODE' => $validation_code,
				];

				$emaildata = Mail::loadEmailTemplate('admin_approve_activation', $replacements, $member['language']);

				Mail::send($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accact' . $member['id'], $emaildata['is_html'], 0);
			}
		}
		// Are we rejecting them?
		elseif ($_POST['todo'] == 'reject' || $_POST['todo'] == 'rejectemail') {
			User::delete($members);

			// Send email telling them they aren't welcome?
			if ($_POST['todo'] == 'rejectemail') {
				foreach ($member_info as $member) {
					$replacements = [
						'USERNAME' => $member['name'],
					];

					$emaildata = Mail::loadEmailTemplate('admin_approve_reject', $replacements, $member['language']);

					Mail::send($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accrej', $emaildata['is_html'], 1);
				}
			}
		}
		// A simple delete?
		elseif ($_POST['todo'] == 'delete' || $_POST['todo'] == 'deleteemail') {
			User::delete($members);

			// Send email telling them they aren't welcome?
			if ($_POST['todo'] == 'deleteemail') {
				foreach ($member_info as $member) {
					$replacements = [
						'USERNAME' => $member['name'],
					];

					$emaildata = Mail::loadEmailTemplate('admin_approve_delete', $replacements, $member['language']);

					Mail::send($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accdel', $emaildata['is_html'], 1);
				}
			}
		}
		// Remind them to activate their account?
		elseif ($_POST['todo'] == 'remind') {
			foreach ($member_info as $member) {
				$replacements = [
					'USERNAME' => $member['name'],
					'ACTIVATIONLINK' => Config::$scripturl . '?action=activate;u=' . $member['id'] . ';code=' . $member['code'],
					'ACTIVATIONLINKWITHOUTCODE' => Config::$scripturl . '?action=activate;u=' . $member['id'],
					'ACTIVATIONCODE' => $member['code'],
				];

				$emaildata = Mail::loadEmailTemplate('admin_approve_remind', $replacements, $member['language']);

				Mail::send($member['email'], $emaildata['subject'], $emaildata['body'], null, 'accrem' . $member['id'], $emaildata['is_html'], 1);
			}
		}

		// @todo current_language is never set, no idea what this is for. Remove?
		// Back to the user's language!
		if (isset($current_language) && $current_language != User::$me->language) {
			Lang::load('index');
			Lang::load('ManageMembers');
		}

		// Log what we did?
		if (!empty(Config::$modSettings['modlog_enabled']) && in_array($_POST['todo'], ['ok', 'okemail', 'require_activation', 'remind'])) {
			$log_action = $_POST['todo'] == 'remind' ? 'remind_member' : 'approve_member';

			foreach ($member_info as $member) {
				Logging::logAction($log_action, ['member' => $member['id']], 'admin');
			}
		}

		// Although updateStats *may* catch this, best to do it manually just in case (Doesn't always sort out unapprovedMembers).
		if (in_array($current_filter, [3, 4, 5])) {
			Config::updateModSettings(['unapprovedMembers' => (Config::$modSettings['unapprovedMembers'] > $member_count ? Config::$modSettings['unapprovedMembers'] - $member_count : 0)]);
		}

		// Update the member's stats. (but, we know the member didn't change their name.)
		Logging::updateStats('member', false);

		// If they haven't been deleted, update the post group statistics on them...
		if (!in_array($_POST['todo'], ['delete', 'deleteemail', 'reject', 'rejectemail', 'remind'])) {
			Logging::updateStats('postgroups', $members);
		}

		Utils::redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);
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

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show per page
	 * @param string $sort An SQL query indicating how to sort the results
	 * @param string $where An SQL query used to filter the results
	 * @param array $where_params An array of parameters for $where
	 * @param bool $get_duplicates Whether to get duplicates (used for the admin member list)
	 * @return array An array of information for displaying the list of members
	 */
	public static function list_getMembers($start, $items_per_page, $sort, $where, $where_params = [], $get_duplicates = false)
	{
		$members = [];

		$request = Db::$db->query(
			'',
			'SELECT
				mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.member_ip, mem.member_ip2, mem.last_login,
				mem.posts, mem.is_activated, mem.date_registered, mem.id_group, mem.additional_groups, mg.group_name
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)
			WHERE ' . ($where == '1' ? '1=1' : $where) . '
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:per_page}',
			array_merge($where_params, [
				'sort' => $sort,
				'start' => $start,
				'per_page' => $items_per_page,
			]),
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['member_ip'] = new IP($row['member_ip']);
			$row['member_ip2'] = new IP($row['member_ip2']);
			$members[] = $row;
		}
		Db::$db->free_result($request);

		// If we want duplicates pass the members array off.
		if ($get_duplicates) {
			self::populateDuplicateMembers($members);
		}

		return $members;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param string $where An SQL query to filter the results
	 * @param array $where_params An array of parameters for $where
	 * @return int The number of members matching the given situation
	 */
	public static function list_getNumMembers($where, $where_params = [])
	{
		// We know how many members there are in total.
		if (empty($where) || $where == '1=1') {
			$num_members = Config::$modSettings['totalMembers'];
		}
		// The database knows the amount when there are extra conditions.
		else {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}members AS mem
				WHERE ' . $where,
				array_merge($where_params, [
				]),
			);
			list($num_members) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		return $num_members;
	}

	/**
	 * Backward compatibility wrapper for the all sub-action.
	 */
	public static function viewMemberlist(): void
	{
		self::load();
		self::$obj->subaction = 'all';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the approve sub-action.
	 */
	public static function adminApprove(): void
	{
		self::load();
		self::$obj->subaction = 'approve';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the browse sub-action.
	 */
	public static function membersAwaitingActivation(): void
	{
		self::load();
		self::$obj->subaction = 'browse';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the search sub-action.
	 */
	public static function searchMembers(): void
	{
		self::load();
		self::$obj->subaction = 'search';
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// Load the essentials.
		Lang::load('ManageMembers');
		Theme::loadTemplate('ManageMembers');

		// Fetch our activation counts.
		$this->getActivationCounts();

		// For the page header... do we show activation?
		$this->show_activate = (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 1) || !empty($this->awaiting_activation);

		// What about approval?
		$this->show_approve = (!empty(Config::$modSettings['registration_method']) && Config::$modSettings['registration_method'] == 2) || !empty($this->awaiting_approval) || !empty(Config::$modSettings['approveAccountDeletion']);

		// Setup the admin tabs.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['admin_members'],
			'help' => 'view_members',
			'description' => Lang::$txt['admin_members_list'],
			'tabs' => [],
		];

		Utils::$context['tabs'] = [
			'viewmembers' => [
				'label' => Lang::$txt['view_all_members'],
				'description' => Lang::$txt['admin_members_list'],
				'url' => Config::$scripturl . '?action=admin;area=viewmembers;sa=all',
				'selected_actions' => ['all'],
			],
			'search' => [
				'label' => Lang::$txt['mlist_search'],
				'description' => Lang::$txt['admin_members_list'],
				'url' => Config::$scripturl . '?action=admin;area=viewmembers;sa=search',
				'selected_actions' => ['search', 'query'],
			],
		];
		Utils::$context['last_tab'] = 'search';

		// Do we have approvals
		if ($this->show_approve) {
			Utils::$context['tabs']['approve'] = [
				'label' => sprintf(Lang::$txt['admin_browse_awaiting_approval'], $this->awaiting_approval),
				'description' => Lang::$txt['admin_browse_approve_desc'],
				'url' => Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve',
			];
			Utils::$context['last_tab'] = 'approve';
		}

		// Do we have activations to show?
		if ($this->show_activate) {
			Utils::$context['tabs']['activate'] = [
				'label' => sprintf(Lang::$txt['admin_browse_awaiting_activate'], $this->awaiting_activation),
				'description' => Lang::$txt['admin_browse_activate_desc'],
				'url' => Config::$scripturl . '?action=admin;area=viewmembers;sa=browse;type=activate',
			];
			Utils::$context['last_tab'] = 'activate';
		}

		// Call our hook now, letting customizations add to the subActions and/or modify Utils::$context as needed.
		IntegrationHook::call('integrate_manage_members', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		// We know the sub action, now we know what you're allowed to do.
		User::$me->isAllowedTo(self::$subactions[$this->subaction][1]);

		// Set the last tab.
		Utils::$context['tabs'][Utils::$context['last_tab']]['is_last'] = true;

		// Find the active tab.
		if (isset(Utils::$context['tabs'][$this->subaction])) {
			Utils::$context['tabs'][$this->subaction]['is_selected'] = true;
		} elseif (isset($this->subaction)) {
			foreach (Utils::$context['tabs'] as $id_tab => $tab_data) {
				if (!empty($tab_data['selected_actions']) && in_array($this->subaction, $tab_data['selected_actions'])) {
					Utils::$context['tabs'][$id_tab]['is_selected'] = true;
				}
			}
		}

		Utils::$context['membergroups'] = &$this->membergroups;
		Utils::$context['postgroups'] = &$this->postgroups;
		Utils::$context['current_filter'] = &$this->current_filter;
	}

	/**
	 * Fetches all the activation counts for ViewMembers.
	 */
	protected function getActivationCounts()
	{
		// Get counts on every type of activation - for sections and filtering alike.
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*) AS total_members, is_activated
			FROM {db_prefix}members
			WHERE is_activated != {int:is_activated}
			GROUP BY is_activated',
			[
				'is_activated' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->activation_numbers[$row['is_activated']] = $row['total_members'];
		}
		Db::$db->free_result($request);

		foreach ($this->activation_numbers as $activation_type => $total_members) {
			if (in_array($activation_type, [0, 2])) {
				$this->awaiting_activation += $total_members;
			} elseif (in_array($activation_type, [3, 4, 5])) {
				$this->awaiting_approval += $total_members;
			}
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Find potential duplicate registration members based on the same IP address
	 *
	 * @param array $members An array of members
	 */
	protected static function populateDuplicateMembers(&$members)
	{
		// This will hold all the ip addresses.
		$ips = [];

		foreach ($members as $key => $member) {
			// Create the duplicate_members element.
			$members[$key]['duplicate_members'] = [];

			// Store the IPs.
			if (!empty($member['member_ip'])) {
				$ips[] = $member['member_ip'];
			}

			if (!empty($member['member_ip2'])) {
				$ips[] = $member['member_ip2'];
			}
		}

		$ips = array_unique($ips);

		if (empty($ips)) {
			return false;
		}

		// Fetch all members with this IP address, we'll filter out the current ones in a sec.
		$duplicate_members = [];
		$duplicate_ids = [];

		$request = Db::$db->query(
			'',
			'SELECT
				id_member, member_name, email_address, member_ip, member_ip2, is_activated
			FROM {db_prefix}members
			WHERE member_ip IN ({array_inet:ips})
				OR member_ip2 IN ({array_inet:ips})',
			[
				'ips' => $ips,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// $duplicate_ids[] = $row['id_member'];
			$row['member_ip'] = new IP($row['member_ip']);
			$row['member_ip2'] = new IP($row['member_ip2']);

			$member_context = [
				'id' => $row['id_member'],
				'name' => $row['member_name'],
				'email' => $row['email_address'],
				'is_banned' => $row['is_activated'] > 10,
				'ip' => $row['member_ip'],
				'ip2' => $row['member_ip2'],
			];

			if (in_array($row['member_ip'], $ips)) {
				$duplicate_members[$row['member_ip']][] = $member_context;
			}

			if ($row['member_ip'] != $row['member_ip2'] && in_array($row['member_ip2'], $ips)) {
				$duplicate_members[$row['member_ip2']][] = $member_context;
			}
		}
		Db::$db->free_result($request);

		// Also try to get a list of messages using these ips.
		$had_ips = [];

		$request = Db::$db->query(
			'',
			'SELECT
				m.poster_ip, mem.id_member, mem.member_name, mem.email_address, mem.is_activated
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_member != 0
				' . (!empty($duplicate_ids) ? 'AND m.id_member NOT IN ({array_int:duplicate_ids})' : '') . '
				AND m.poster_ip IN ({array_inet:ips})',
			[
				'duplicate_ids' => $duplicate_ids,
				'ips' => $ips,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['poster_ip'] = new IP($row['poster_ip']);

			// Don't collect lots of the same.
			if (isset($had_ips[$row['poster_ip']]) && in_array($row['id_member'], $had_ips[$row['poster_ip']])) {
				continue;
			}

			$had_ips[$row['poster_ip']][] = $row['id_member'];

			$duplicate_members[$row['poster_ip']][] = [
				'id' => $row['id_member'],
				'name' => $row['member_name'],
				'email' => $row['email_address'],
				'is_banned' => $row['is_activated'] > 10,
				'ip' => $row['poster_ip'],
				'ip2' => $row['poster_ip'],
			];
		}
		Db::$db->free_result($request);

		// Now we have all the duplicate members, stick them with their respective member in the list.
		if (!empty($duplicate_members)) {
			foreach ($members as $key => $member) {
				if (isset($duplicate_members[$member['member_ip']])) {
					$members[$key]['duplicate_members'] = $duplicate_members[$member['member_ip']];
				}

				if ($member['member_ip'] != $member['member_ip2'] && isset($duplicate_members[$member['member_ip2']])) {
					$members[$key]['duplicate_members'] = array_merge($member['duplicate_members'], $duplicate_members[$member['member_ip2']]);
				}

				// Check we don't have lots of the same member.
				$member_track = [$member['id_member']];

				foreach ($members[$key]['duplicate_members'] as $duplicate_id_member => $duplicate_member) {
					if (in_array($duplicate_member['id'], $member_track)) {
						unset($members[$key]['duplicate_members'][$duplicate_id_member]);

						continue;
					}

					$member_track[] = $duplicate_member['id'];
				}
			}
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Members::exportStatic')) {
	Members::exportStatic();
}

?>