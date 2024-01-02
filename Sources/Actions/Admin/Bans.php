<?php

/**
 * This file contains all the functions used for the ban center.
 *
 * @todo refactor as controller-model
 *
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
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class contains all the methods used for the ban center.
 */
class Bans implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'Ban',
			'updateBanMembers' => 'updateBanMembers',
			'list_getBans' => 'list_getBans',
			'list_getNumBans' => 'list_getNumBans',
			'list_getBanItems' => 'list_getBanItems',
			'list_getNumBanItems' => 'list_getNumBanItems',
			'list_getBanTriggers' => 'list_getBanTriggers',
			'list_getNumBanTriggers' => 'list_getNumBanTriggers',
			'list_getBanLogEntries' => 'list_getBanLogEntries',
			'list_getNumBanLogEntries' => 'list_getNumBanLogEntries',
			'banList' => 'BanList',
			'banEdit' => 'BanEdit',
			'banBrowseTriggers' => 'BanBrowseTriggers',
			'banEditTrigger' => 'BanEditTrigger',
			'banLog' => 'BanLog',
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
	public string $subaction = 'list';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'list' => 'list',
		'edit' => 'edit',
		'add' => 'edit',
		'browse' => 'browseTriggers',
		'edittrigger' => 'editTrigger',
		'log' => 'log',
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
		User::$me->isAllowedTo('manage_bans');

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Shows a list of bans currently set.
	 *
	 * It is accessed by ?action=admin;area=ban;sa=list.
	 * It removes expired bans.
	 * It allows sorting on different criteria.
	 * It also handles removal of selected ban items.
	 */
	public function list(): void
	{
		// User pressed the 'remove selection button'.
		if (!empty($_POST['removeBans']) && !empty($_POST['remove']) && is_array($_POST['remove'])) {
			User::$me->checkSession();

			// Make sure every entry is a proper integer.
			array_map('intval', $_POST['remove']);

			// Unban them all!
			$this->removeBanGroups($_POST['remove']);

			// No more caching this ban!
			Config::updateModSettings(['banLastUpdated' => time()]);

			// Some members might be unbanned now. Update the members table.
			self::updateBanMembers();
		}

		// Create a date string so we don't overload them with date info.
		if (preg_match('~%[AaBbCcDdeGghjmuYy](?:[^%]*%[AaBbCcDdeGghjmuYy])*~', User::$me->time_format, $matches) == 0 || empty($matches[0])) {
			Utils::$context['ban_time_format'] = User::$me->time_format;
		} else {
			Utils::$context['ban_time_format'] = $matches[0];
		}

		$listOptions = [
			'id' => 'ban_list',
			'title' => Lang::$txt['ban_title'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=ban;sa=list',
			'default_sort_col' => 'added',
			'default_sort_dir' => 'desc',
			'get_items' => [
				'function' => __CLASS__ . '::list_getBans',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumBans',
			],
			'no_items_label' => Lang::$txt['ban_no_entries'],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['ban_name'],
					],
					'data' => [
						'db' => 'name',
					],
					'sort' => [
						'default' => 'bg.name',
						'reverse' => 'bg.name DESC',
					],
				],
				'notes' => [
					'header' => [
						'value' => Lang::$txt['ban_notes'],
					],
					'data' => [
						'db' => 'notes',
						'class' => 'smalltext word_break',
					],
					'sort' => [
						'default' => 'LENGTH(bg.notes) > 0 DESC, bg.notes',
						'reverse' => 'LENGTH(bg.notes) > 0, bg.notes DESC',
					],
				],
				'reason' => [
					'header' => [
						'value' => Lang::$txt['ban_reason'],
					],
					'data' => [
						'db' => 'reason',
						'class' => 'smalltext word_break',
					],
					'sort' => [
						'default' => 'LENGTH(bg.reason) > 0 DESC, bg.reason',
						'reverse' => 'LENGTH(bg.reason) > 0, bg.reason DESC',
					],
				],
				'added' => [
					'header' => [
						'value' => Lang::$txt['ban_added'],
					],
					'data' => [
						'function' => function ($rowData) {
							$time = new Time('@' . $rowData['ban_time']);

							if (empty(Utils::$context['ban_time_format'])) {
								return $time->format();
							}

							return $time->format(Utils::$context['ban_time_format'], true);
						},
					],
					'sort' => [
						'default' => 'bg.ban_time',
						'reverse' => 'bg.ban_time DESC',
					],
				],
				'expires' => [
					'header' => [
						'value' => Lang::$txt['ban_expires'],
					],
					'data' => [
						'function' => function ($rowData) {
							// This ban never expires...whahaha.
							if ($rowData['expire_time'] === null) {
								return Lang::$txt['never'];
							}

							// This ban has already expired.
							if ($rowData['expire_time'] < time()) {
								return sprintf('<span class="red">%1$s</span>', Lang::$txt['ban_expired']);
							}

							// Still need to wait a few days for this ban to expire.
							return sprintf('%1$d&nbsp;%2$s', ceil(($rowData['expire_time'] - time()) / (60 * 60 * 24)), Lang::$txt['ban_days']);
						},
					],
					'sort' => [
						'default' => 'COALESCE(bg.expire_time, 1=1) DESC, bg.expire_time DESC',
						'reverse' => 'COALESCE(bg.expire_time, 1=1), bg.expire_time',
					],
				],
				'num_triggers' => [
					'header' => [
						'value' => Lang::$txt['ban_triggers'],
					],
					'data' => [
						'db' => 'num_triggers',
					],
					'sort' => [
						'default' => 'num_triggers DESC',
						'reverse' => 'num_triggers',
					],
				],
				'actions' => [
					'header' => [
						'value' => Lang::$txt['ban_actions'],
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">' . Lang::$txt['modify'] . '</a>',
							'params' => [
								'id_ban_group' => false,
							],
						],
						'class' => 'centercol',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
							'params' => [
								'id_ban_group' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=ban;sa=list',
			],
			'additional_rows' => [
				[
					'position' => 'top_of_list',
					'value' => '<input type="submit" name="removeBans" value="' . Lang::$txt['ban_remove_selected'] . '" class="button">',
				],
				[
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="removeBans" value="' . Lang::$txt['ban_remove_selected'] . '" class="button">',
				],
			],
			'javascript' => '
			var removeBans = $("input[name=\'removeBans\']");

			removeBans.on( "click", function(e) {
				var removeItems = $("input[name=\'remove[]\']:checked").length;

				if (removeItems == 0)
				{
					e.preventDefault();
					return alert("' . Lang::$txt['select_item_check'] . '");
				}


				return confirm("' . Lang::$txt['ban_remove_selected_confirm'] . '");
			});',
		];

		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'ban_list';
	}

	/**
	 * Adds new bans and modifies existing ones.
	 *
	 * Adding new bans:
	 * 	- is accessed by ?action=admin;area=ban;sa=add.
	 *
	 * Modifying existing bans:
	 *  - is accessed by ?action=admin;area=ban;sa=edit;bg=x
	 *  - shows a list of ban triggers for the specified ban.
	 */
	public function edit(): void
	{
		if ((isset($_POST['add_ban']) || isset($_POST['modify_ban']) || isset($_POST['remove_selection'])) && empty(Utils::$context['ban_errors'])) {
			$this->edit2();
		}

		$ban_group_id = Utils::$context['ban']['id'] ?? (isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0);

		// Template needs this to show errors using javascript
		Lang::load('Errors');

		SecurityToken::create('admin-bet');

		Utils::$context['form_url'] = Config::$scripturl . '?action=admin;area=ban;sa=edit';

		if (!empty(Utils::$context['ban_errors'])) {
			foreach (Utils::$context['ban_errors'] as $error) {
				Utils::$context['error_messages'][$error] = Lang::$txt[$error];
			}
		} else {
			// If we're editing an existing ban, get it from the database.
			if (!empty($ban_group_id)) {
				Utils::$context['ban_group_id'] = $ban_group_id;

				$listOptions = [
					'id' => 'ban_items',
					'base_href' => Config::$scripturl . '?action=admin;area=ban;sa=edit;bg=' . $ban_group_id,
					'no_items_label' => Lang::$txt['ban_no_triggers'],
					'items_per_page' => Config::$modSettings['defaultMaxListItems'],
					'get_items' => [
						'function' => __CLASS__ . '::list_getBanItems',
						'params' => [
							'ban_group_id' => $ban_group_id,
						],
					],
					'get_count' => [
						'function' => __CLASS__ . '::list_getNumBanItems',
						'params' => [
							'ban_group_id' => $ban_group_id,
						],
					],
					'columns' => [
						'type' => [
							'header' => [
								'value' => Lang::$txt['ban_banned_entity'],
								'style' => 'width: 60%;text-align: left;',
							],
							'data' => [
								'function' => function ($ban_item) {
									if (in_array($ban_item['type'], ['ip', 'hostname', 'email'])) {
										return '<strong>' . Lang::$txt[$ban_item['type']] . ':</strong>&nbsp;' . $ban_item[$ban_item['type']];
									}

									if ($ban_item['type'] == 'user') {
										return '<strong>' . Lang::$txt['username'] . ':</strong>&nbsp;' . $ban_item['user']['link'];
									}

									return '<strong>' . Lang::$txt['unknown'] . ':</strong>&nbsp;' . $ban_item['no_bantype_selected'];
								},
								'style' => 'text-align: left;',
							],
						],
						'hits' => [
							'header' => [
								'value' => Lang::$txt['ban_hits'],
								'style' => 'width: 15%; text-align: center;',
							],
							'data' => [
								'db' => 'hits',
								'style' => 'text-align: center;',
							],
						],
						'id' => [
							'header' => [
								'value' => Lang::$txt['ban_actions'],
								'style' => 'width: 15%; text-align: center;',
							],
							'data' => [
								'function' => function ($ban_item) {
									return '<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . Utils::$context['ban_group_id'] . ';bi=' . $ban_item['id'] . '">' . Lang::$txt['ban_edit_trigger'] . '</a>';
								},
								'style' => 'text-align: center;',
							],
						],
						'checkboxes' => [
							'header' => [
								'value' => '<input type="checkbox" onclick="invertAll(this, this.form, \'ban_items\');">',
								'style' => 'width: 5%; text-align: center;',
							],
							'data' => [
								'sprintf' => [
									'format' => '<input type="checkbox" name="ban_items[]" value="%1$d">',
									'params' => [
										'id' => false,
									],
								],
								'style' => 'text-align: center;',
							],
						],
					],
					'form' => [
						'href' => Config::$scripturl . '?action=admin;area=ban;sa=edit;bg=' . $ban_group_id,
					],
					'additional_rows' => [
						[
							'position' => 'above_column_headers',
							'value' => '
							<input type="submit" name="remove_selection" value="' . Lang::$txt['ban_remove_selected_triggers'] . '" class="button"> <a class="button" href="' . Config::$scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . $ban_group_id . '">' . Lang::$txt['ban_add_trigger'] . '</a>',
							'style' => 'text-align: right;',
						],
						[
							'position' => 'above_column_headers',
							'value' => '
							<input type="hidden" name="bg" value="' . $ban_group_id . '">
							<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
							<input type="hidden" name="' . Utils::$context['admin-bet_token_var'] . '" value="' . Utils::$context['admin-bet_token'] . '">',
						],
						[
							'position' => 'below_table_data',
							'value' => '
							<input type="submit" name="remove_selection" value="' . Lang::$txt['ban_remove_selected_triggers'] . '" class="button"> <a class="button" href="' . Config::$scripturl . '?action=admin;area=ban;sa=edittrigger;bg=' . $ban_group_id . '">' . Lang::$txt['ban_add_trigger'] . '</a>',
							'style' => 'text-align: right;',
						],
						[
							'position' => 'below_table_data',
							'value' => '
							<input type="hidden" name="bg" value="' . $ban_group_id . '">
							<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
							<input type="hidden" name="' . Utils::$context['admin-bet_token_var'] . '" value="' . Utils::$context['admin-bet_token'] . '">',
						],
					],
					'javascript' => '
			var removeBans = $("input[name=\'remove_selection\']");

			removeBans.on( "click", function(e) {
				var removeItems = $("input[name=\'ban_items[]\']:checked").length;

				if (removeItems == 0)
				{
					e.preventDefault();
					return alert("' . Lang::$txt['select_item_check'] . '");
				}


				return confirm("' . Lang::$txt['ban_remove_selected_confirm'] . '");
			});',
				];

				IntegrationHook::call('integrate_ban_edit_list', [&$listOptions]);

				new ItemList($listOptions);
			}
			// Not an existing one, then it's probably a new one.
			else {
				Utils::$context['ban'] = [
					'id' => 0,
					'name' => '',
					'expiration' => [
						'status' => 'never',
						'days' => 0,
					],
					'reason' => '',
					'notes' => '',
					'ban_days' => 0,
					'cannot' => [
						'access' => true,
						'post' => false,
						'register' => false,
						'login' => false,
					],
					'is_new' => true,
				];
				Utils::$context['ban_suggestions'] = [
					'main_ip' => '',
					'hostname' => '',
					'email' => '',
					'member' => [
						'id' => 0,
					],
				];

				// Overwrite some of the default form values if a user ID was given.
				if (!empty($_REQUEST['u'])) {
					$request = Db::$db->query(
						'',
						'SELECT id_member, real_name, member_ip, email_address
						FROM {db_prefix}members
						WHERE id_member = {int:current_user}
						LIMIT 1',
						[
							'current_user' => (int) $_REQUEST['u'],
						],
					);

					if (Db::$db->num_rows($request) > 0) {
						list(Utils::$context['ban_suggestions']['member']['id'], Utils::$context['ban_suggestions']['member']['name'], Utils::$context['ban_suggestions']['main_ip'], Utils::$context['ban_suggestions']['email']) = Db::$db->fetch_row($request);

						Utils::$context['ban_suggestions']['main_ip'] = new IP(Utils::$context['ban_suggestions']['main_ip']);
					}
					Db::$db->free_result($request);

					if (!empty(Utils::$context['ban_suggestions']['member']['id'])) {
						Utils::$context['ban_suggestions']['href'] = Config::$scripturl . '?action=profile;u=' . Utils::$context['ban_suggestions']['member']['id'];

						Utils::$context['ban_suggestions']['member']['link'] = '<a href="' . Utils::$context['ban_suggestions']['href'] . '">' . Utils::$context['ban_suggestions']['member']['name'] . '</a>';

						// Default the ban name to the name of the banned member.
						Utils::$context['ban']['name'] = Utils::$context['ban_suggestions']['member']['name'];

						// @todo: there should be a better solution...used to lock the "Ban on Username" input when banning from profile
						Utils::$context['ban']['from_user'] = true;

						$main_ip = new IP(Utils::$context['ban_suggestions']['main_ip']);

						// Would be nice if we could also ban the hostname.
						if (
							empty(Config::$modSettings['disableHostnameLookup'])
							&& $main_ip->isValid()
						) {
							Utils::$context['ban_suggestions']['hostname'] = $main_ip->getHost();
						}

						Utils::$context['ban_suggestions']['other_ips'] = $this->banLoadAdditionalIPs(Utils::$context['ban_suggestions']['member']['id']);
					}
				}
				// We came from the mod center.
				elseif (isset($_GET['msg']) && !empty($_GET['msg'])) {
					$request = Db::$db->query(
						'',
						'SELECT poster_name, poster_ip, poster_email
						FROM {db_prefix}messages
						WHERE id_msg = {int:message}
						LIMIT 1',
						[
							'message' => (int) $_REQUEST['msg'],
						],
					);

					if (Db::$db->num_rows($request) > 0) {
						list(Utils::$context['ban_suggestions']['member']['name'], Utils::$context['ban_suggestions']['main_ip'], Utils::$context['ban_suggestions']['email']) = Db::$db->fetch_row($request);

						Utils::$context['ban_suggestions']['main_ip'] = new IP(Utils::$context['ban_suggestions']['main_ip']);
					}
					Db::$db->free_result($request);

					// Can't hurt to ban based on the guest name...
					Utils::$context['ban']['name'] = Utils::$context['ban_suggestions']['member']['name'];

					Utils::$context['ban']['from_user'] = true;
				}

				IntegrationHook::call('integrate_ban_edit_new', []);
			}
		}

		Theme::loadJavaScriptFile('suggest.js', ['minimize' => true], 'smf_suggest');
		Utils::$context['sub_template'] = 'ban_edit';
	}

	/**
	 * This handles the screen for showing the banned entities.
	 *
	 * It is accessed by ?action=admin;area=ban;sa=browse
	 * It uses sub-tabs for browsing by IP, hostname, email or username.
	 *
	 * Uses a standard list (@see SMF\ItemList())
	 */
	public function browseTriggers(): void
	{
		if (!empty($_POST['remove_triggers']) && !empty($_POST['remove']) && is_array($_POST['remove'])) {
			User::$me->checkSession();

			self::removeBanTriggers($_POST['remove']);

			// Rehabilitate some members.
			if ($_REQUEST['entity'] == 'member') {
				self::updateBanMembers();
			}

			// Make sure the ban cache is refreshed.
			Config::updateModSettings(['banLastUpdated' => time()]);
		}

		Utils::$context['selected_entity'] = isset($_REQUEST['entity']) && in_array($_REQUEST['entity'], ['ip', 'hostname', 'email', 'member']) ? $_REQUEST['entity'] : 'ip';

		$listOptions = [
			'id' => 'ban_trigger_list',
			'title' => Lang::$txt['ban_trigger_browse'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=ban;sa=browse;entity=' . Utils::$context['selected_entity'],
			'default_sort_col' => 'banned_entity',
			'no_items_label' => Lang::$txt['ban_no_triggers'],
			'get_items' => [
				'function' => __CLASS__ . '::list_getBanTriggers',
				'params' => [
					Utils::$context['selected_entity'],
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumBanTriggers',
				'params' => [
					Utils::$context['selected_entity'],
				],
			],
			'columns' => [
				'banned_entity' => [
					'header' => [
						'value' => Lang::$txt['ban_banned_entity'],
					],
				],
				'ban_name' => [
					'header' => [
						'value' => Lang::$txt['ban_name'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=edit;bg=%1$d">%2$s</a>',
							'params' => [
								'id_ban_group' => false,
								'name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'bg.name',
						'reverse' => 'bg.name DESC',
					],
				],
				'hits' => [
					'header' => [
						'value' => Lang::$txt['ban_hits'],
					],
					'data' => [
						'db' => 'hits',
					],
					'sort' => [
						'default' => 'bi.hits DESC',
						'reverse' => 'bi.hits',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
							'params' => [
								'id_ban' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=ban;sa=browse;entity=' . Utils::$context['selected_entity'],
				'include_start' => true,
				'include_sort' => true,
			],
			'additional_rows' => [
				[
					'position' => 'above_column_headers',
					'value' => '<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=browse;entity=ip">' . (Utils::$context['selected_entity'] == 'ip' ? '<img src="' . Theme::$current->settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . Lang::$txt['ip'] . '</a>&nbsp;|&nbsp;<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=browse;entity=hostname">' . (Utils::$context['selected_entity'] == 'hostname' ? '<img src="' . Theme::$current->settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . Lang::$txt['hostname'] . '</a>&nbsp;|&nbsp;<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=browse;entity=email">' . (Utils::$context['selected_entity'] == 'email' ? '<img src="' . Theme::$current->settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . Lang::$txt['email'] . '</a>&nbsp;|&nbsp;<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=browse;entity=member">' . (Utils::$context['selected_entity'] == 'member' ? '<img src="' . Theme::$current->settings['images_url'] . '/selected.png" alt="&gt;"> ' : '') . Lang::$txt['username'] . '</a>',
				],
				[
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="remove_triggers" value="' . Lang::$txt['ban_remove_selected_triggers'] . '" data-confirm="' . Lang::$txt['ban_remove_selected_triggers_confirm'] . '" class="button you_sure">',
				],
			],
		];

		// Specific data for the first column depending on the selected entity.
		if (Utils::$context['selected_entity'] === 'ip') {
			$listOptions['columns']['banned_entity']['data'] = [
				'function' => function ($rowData) {
					return IP::range2ip($rowData['ip_low'], $rowData['ip_high']);
				},
			];
			$listOptions['columns']['banned_entity']['sort'] = [
				'default' => 'bi.ip_low, bi.ip_high, bi.ip_low',
				'reverse' => 'bi.ip_low DESC, bi.ip_high DESC',
			];
		} elseif (Utils::$context['selected_entity'] === 'hostname') {
			$listOptions['columns']['banned_entity']['data'] = [
				'function' => function ($rowData) {
					return strtr(Utils::htmlspecialchars($rowData['hostname']), ['%' => '*']);
				},
			];
			$listOptions['columns']['banned_entity']['sort'] = [
				'default' => 'bi.hostname',
				'reverse' => 'bi.hostname DESC',
			];
		} elseif (Utils::$context['selected_entity'] === 'email') {
			$listOptions['columns']['banned_entity']['data'] = [
				'function' => function ($rowData) {
					return strtr(Utils::htmlspecialchars($rowData['email_address']), ['%' => '*']);
				},
			];
			$listOptions['columns']['banned_entity']['sort'] = [
				'default' => 'bi.email_address',
				'reverse' => 'bi.email_address DESC',
			];
		} elseif (Utils::$context['selected_entity'] === 'member') {
			$listOptions['columns']['banned_entity']['data'] = [
				'sprintf' => [
					'format' => '<a href="' . Config::$scripturl . '?action=profile;u=%1$d">%2$s</a>',
					'params' => [
						'id_member' => false,
						'real_name' => false,
					],
				],
			];
			$listOptions['columns']['banned_entity']['sort'] = [
				'default' => 'mem.real_name',
				'reverse' => 'mem.real_name DESC',
			];
		}

		// Create the list.
		new ItemList($listOptions);

		// The list is the only thing to show, so make it the default sub template.
		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'ban_trigger_list';
	}

	/**
	 * This function handles the ins and outs of the screen for adding new ban
	 * triggers or modifying existing ones.
	 *
	 * Adding new ban triggers:
	 * 	- is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x
	 * 	- uses the ban_edit_trigger sub template of ManageBans.
	 *
	 * Editing existing ban triggers:
	 *  - is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x;bi=y
	 *  - uses the ban_edit_trigger sub template of ManageBans.
	 */
	public function editTrigger(): void
	{
		Utils::$context['sub_template'] = 'ban_edit_trigger';
		Utils::$context['form_url'] = Config::$scripturl . '?action=admin;area=ban;sa=edittrigger';

		$ban_group = (int) ($_REQUEST['bg'] ?? 0);
		$ban_id = (int) ($_REQUEST['bi'] ?? 0);

		if (empty($ban_group)) {
			ErrorHandler::fatalLang('ban_not_found', false);
		}

		if (isset($_POST['add_new_trigger']) && !empty($_POST['ban_suggestions'])) {
			$this->saveTriggers($_POST['ban_suggestions'], $ban_group, 0, $ban_id);

			Utils::redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
		} elseif (isset($_POST['edit_trigger']) && !empty($_POST['ban_suggestions'])) {
			$this->saveTriggers($_POST['ban_suggestions'], $ban_group, 0, $ban_id);

			Utils::redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
		} elseif (isset($_POST['edit_trigger'])) {
			self::removeBanTriggers($ban_id);

			Utils::redirectexit('action=admin;area=ban;sa=edit' . (!empty($ban_group) ? ';bg=' . $ban_group : ''));
		}

		Theme::loadJavaScriptFile('suggest.js', ['minimize' => true], 'smf_suggest');

		if (empty($ban_id)) {
			Utils::$context['ban_trigger'] = [
				'id' => 0,
				'group' => $ban_group,
				'ip' => [
					'value' => '',
					'selected' => true,
				],
				'hostname' => [
					'selected' => false,
					'value' => '',
				],
				'email' => [
					'value' => '',
					'selected' => false,
				],
				'banneduser' => [
					'value' => '',
					'selected' => false,
				],
				'is_new' => true,
			];
		} else {
			$request = Db::$db->query(
				'',
				'SELECT
					bi.id_ban, bi.id_ban_group, bi.hostname, bi.email_address, bi.id_member,
					bi.ip_low, bi.ip_high,
					mem.member_name, mem.real_name
				FROM {db_prefix}ban_items AS bi
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
				WHERE bi.id_ban = {int:ban_item}
					AND bi.id_ban_group = {int:ban_group}
				LIMIT 1',
				[
					'ban_item' => $ban_id,
					'ban_group' => $ban_group,
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				ErrorHandler::fatalLang('ban_not_found', false);
			}
			$row = Db::$db->fetch_assoc($request);
			Db::$db->free_result($request);

			Utils::$context['ban_trigger'] = [
				'id' => $row['id_ban'],
				'group' => $row['id_ban_group'],
				'ip' => [
					'value' => empty($row['ip_low']) ? '' : IP::range2ip($row['ip_low'], $row['ip_high']),
					'selected' => !empty($row['ip_low']),
				],
				'hostname' => [
					'value' => str_replace('%', '*', $row['hostname']),
					'selected' => !empty($row['hostname']),
				],
				'email' => [
					'value' => str_replace('%', '*', $row['email_address']),
					'selected' => !empty($row['email_address']),
				],
				'banneduser' => [
					'value' => $row['member_name'],
					'selected' => !empty($row['member_name']),
				],
				'is_new' => false,
			];
		}

		SecurityToken::create('admin-bet');
	}

	/**
	 * This handles the listing of ban log entries, and allows their deletion.
	 * Shows a list of logged access attempts by banned users.
	 * It is accessed by ?action=admin;area=ban;sa=log.
	 * How it works:
	 *  - allows sorting of several columns.
	 *  - also handles deletion of (a selection of) log entries.
	 */
	public function log(): void
	{
		// Delete one or more entries.
		if (!empty($_POST['removeAll']) || (!empty($_POST['removeSelected']) && !empty($_POST['remove']))) {
			User::$me->checkSession();
			SecurityToken::validate('admin-bl');

			// 'Delete all entries' button was pressed.
			if (!empty($_POST['removeAll'])) {
				$this->removeBanLogs();
			}
			// 'Delete selection' button was pressed.
			else {
				$this->removeBanLogs(array_map('intval', $_POST['remove']));
			}
		}

		$listOptions = [
			'id' => 'ban_log',
			'title' => Lang::$txt['ban_log'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Utils::$context['admin_area'] == 'ban' ? Config::$scripturl . '?action=admin;area=ban;sa=log' : Config::$scripturl . '?action=admin;area=logs;sa=banlog',
			'default_sort_col' => 'date',
			'get_items' => [
				'function' => __CLASS__ . '::list_getBanLogEntries',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumBanLogEntries',
			],
			'no_items_label' => Lang::$txt['ban_log_no_entries'],
			'columns' => [
				'ip' => [
					'header' => [
						'value' => Lang::$txt['ban_log_ip'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=trackip;searchip=%1$s">%1$s</a>',
							'params' => [
								'ip' => false,
							],
						],
					],
					'sort' => [
						'default' => 'lb.ip',
						'reverse' => 'lb.ip DESC',
					],
				],
				'email' => [
					'header' => [
						'value' => Lang::$txt['ban_log_email'],
					],
					'data' => [
						'db_htmlsafe' => 'email',
					],
					'sort' => [
						'default' => 'lb.email = \'\', lb.email',
						'reverse' => 'lb.email != \'\', lb.email DESC',
					],
				],
				'member' => [
					'header' => [
						'value' => Lang::$txt['ban_log_member'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=profile;u=%1$d">%2$s</a>',
							'params' => [
								'id_member' => false,
								'real_name' => false,
							],
						],
					],
					'sort' => [
						'default' => 'COALESCE(mem.real_name, 1=1), mem.real_name',
						'reverse' => 'COALESCE(mem.real_name, 1=1) DESC, mem.real_name DESC',
					],
				],
				'date' => [
					'header' => [
						'value' => Lang::$txt['ban_log_date'],
					],
					'data' => [
						'function' => function ($rowData) {
							return timeformat($rowData['log_time']);
						},
					],
					'sort' => [
						'default' => 'lb.log_time DESC',
						'reverse' => 'lb.log_time',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
							'params' => [
								'id_ban_log' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Utils::$context['admin_area'] == 'ban' ? Config::$scripturl . '?action=admin;area=ban;sa=log' : Config::$scripturl . '?action=admin;area=logs;sa=banlog',
				'include_start' => true,
				'include_sort' => true,
				'token' => 'admin-bl',
			],
			'additional_rows' => [
				[
					'position' => 'after_title',
					'value' => '
						<input type="submit" name="removeSelected" value="' . Lang::$txt['ban_log_remove_selected'] . '" data-confirm="' . Lang::$txt['ban_log_remove_selected_confirm'] . '" class="button you_sure">
						<input type="submit" name="removeAll" value="' . Lang::$txt['ban_log_remove_all'] . '" data-confirm="' . Lang::$txt['ban_log_remove_all_confirm'] . '" class="button you_sure">',
				],
				[
					'position' => 'bottom_of_list',
					'value' => '
						<input type="submit" name="removeSelected" value="' . Lang::$txt['ban_log_remove_selected'] . '" data-confirm="' . Lang::$txt['ban_log_remove_selected_confirm'] . '" class="button you_sure">
						<input type="submit" name="removeAll" value="' . Lang::$txt['ban_log_remove_all'] . '" data-confirm="' . Lang::$txt['ban_log_remove_all_confirm'] . '" class="button you_sure">',
				],
			],
		];

		SecurityToken::create('admin-bl');

		new ItemList($listOptions);

		Utils::$context['page_title'] = Lang::$txt['ban_log'];
		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'ban_log';
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
	 * As it says... this tries to review the list of banned members, to match new bans.
	 *
	 * Note: if is_activated >= 10, then the member is banned.
	 */
	public static function updateBanMembers(): void
	{
		$updates = [];
		$allMembers = [];
		$newMembers = [];

		// Start by getting all active bans - it's quicker doing this in parts...
		$memberIDs = [];
		$memberEmails = [];
		$memberEmailWild = [];

		$request = Db::$db->query(
			'',
			'SELECT bi.id_member, bi.email_address
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
			WHERE (bi.id_member > {int:no_member} OR bi.email_address != {string:blank_string})
				AND bg.cannot_access = {int:cannot_access_on}
				AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})',
			[
				'no_member' => 0,
				'cannot_access_on' => 1,
				'current_time' => time(),
				'blank_string' => '',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($row['id_member']) {
				$memberIDs[$row['id_member']] = $row['id_member'];
			}

			if ($row['email_address']) {
				// Does it have a wildcard - if so we can't do a IN on it.
				if (strpos($row['email_address'], '%') !== false) {
					$memberEmailWild[$row['email_address']] = $row['email_address'];
				} else {
					$memberEmails[$row['email_address']] = $row['email_address'];
				}
			}
		}
		Db::$db->free_result($request);

		// Build up the query.
		$queryPart = [];
		$queryValues = [];

		if (!empty($memberIDs)) {
			$queryPart[] = 'mem.id_member IN ({array_string:member_ids})';
			$queryValues['member_ids'] = $memberIDs;
		}

		if (!empty($memberEmails)) {
			$queryPart[] = 'mem.email_address IN ({array_string:member_emails})';
			$queryValues['member_emails'] = $memberEmails;
		}

		$count = 0;

		foreach ($memberEmailWild as $email) {
			$queryPart[] = 'mem.email_address LIKE {string:wild_' . $count . '}';
			$queryValues['wild_' . $count++] = $email;
		}

		// Find all banned members.
		if (!empty($queryPart)) {
			$request = Db::$db->query(
				'',
				'SELECT mem.id_member, mem.is_activated
				FROM {db_prefix}members AS mem
				WHERE ' . implode(' OR ', $queryPart),
				$queryValues,
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (!in_array($row['id_member'], $allMembers)) {
					$allMembers[] = $row['id_member'];

					// Do they need an update?
					if ($row['is_activated'] < 10) {
						$updates[($row['is_activated'] + 10)][] = $row['id_member'];
						$newMembers[] = $row['id_member'];
					}
				}
			}
			Db::$db->free_result($request);
		}

		// We welcome our new members in the realm of the banned.
		if (!empty($newMembers)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_online
				WHERE id_member IN ({array_int:new_banned_members})',
				[
					'new_banned_members' => $newMembers,
				],
			);
		}

		// Find members that are wrongfully marked as banned.
		$request = Db::$db->query(
			'',
			'SELECT mem.id_member, mem.is_activated - 10 AS new_value
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_member = mem.id_member OR mem.email_address LIKE bi.email_address)
				LEFT JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND bg.cannot_access = {int:cannot_access_activated} AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time}))
			WHERE (bi.id_ban IS NULL OR bg.id_ban_group IS NULL)
				AND mem.is_activated >= {int:ban_flag}',
			[
				'cannot_access_activated' => 1,
				'current_time' => time(),
				'ban_flag' => 10,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Don't do this twice!
			if (!in_array($row['id_member'], $allMembers)) {
				$updates[$row['new_value']][] = $row['id_member'];
				$allMembers[] = $row['id_member'];
			}
		}
		Db::$db->free_result($request);

		if (!empty($updates)) {
			foreach ($updates as $newStatus => $members) {
				User::updateMemberData($members, ['is_activated' => $newStatus]);
			}
		}

		// Update the latest member and our total members as banning may change them.
		Logging::updateStats('member');
	}

	/**
	 * Get bans, what else? For the given options.
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string telling ORDER BY how to sort the results
	 * @return array An array of information about the bans for the list
	 */
	public static function list_getBans($start, $items_per_page, $sort): array
	{
		$bans = [];

		$request = Db::$db->query(
			'',
			'SELECT bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes, COUNT(bi.id_ban) AS num_triggers
			FROM {db_prefix}ban_groups AS bg
				LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
			GROUP BY bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time, bg.reason, bg.notes
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:limit}',
			[
				'sort' => $sort,
				'offset' => $start,
				'limit' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$bans[] = $row;
		}
		Db::$db->free_result($request);

		return $bans;
	}

	/**
	 * Get the total number of ban from the ban group table
	 *
	 * @return int The total number of bans
	 */
	public static function list_getNumBans(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*) AS num_bans
			FROM {db_prefix}ban_groups',
			[
			],
		);
		list($numBans) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $numBans;
	}

	/**
	 * Retrieves all the ban items belonging to a certain ban group
	 *
	 * @param int $start Which item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param int $sort Not used here
	 * @param int $ban_group_id The ID of the group to get the bans for
	 * @return array An array with information about the returned ban items
	 */
	public static function list_getBanItems($start = 0, $items_per_page = 0, $sort = 0, $ban_group_id = 0): array
	{
		$ban_items = [];

		$request = Db::$db->query(
			'',
			'SELECT
				bi.id_ban, bi.hostname, bi.email_address, bi.id_member, bi.hits,
				bi.ip_low, bi.ip_high,
				bg.id_ban_group, bg.name, bg.ban_time, bg.expire_time AS expire_time, bg.reason, bg.notes, bg.cannot_access, bg.cannot_register, bg.cannot_login, bg.cannot_post,
				COALESCE(mem.id_member, 0) AS id_member, mem.member_name, mem.real_name
			FROM {db_prefix}ban_groups AS bg
				LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
			WHERE bg.id_ban_group = {int:current_ban}
			LIMIT {int:start}, {int:items_per_page}',
			[
				'current_ban' => $ban_group_id,
				'start' => $start,
				'items_per_page' => $items_per_page,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('ban_not_found', false);
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			if (!isset(Utils::$context['ban'])) {
				Utils::$context['ban'] = [
					'id' => $row['id_ban_group'],
					'name' => $row['name'],
					'expiration' => [
						'status' => $row['expire_time'] === null ? 'never' : ($row['expire_time'] < time() ? 'expired' : 'one_day'),
						'days' => $row['expire_time'] > time() ? ($row['expire_time'] - time() < 86400 ? 1 : ceil(($row['expire_time'] - time()) / 86400)) : 0,
					],
					'reason' => $row['reason'],
					'notes' => $row['notes'],
					'cannot' => [
						'access' => !empty($row['cannot_access']),
						'post' => !empty($row['cannot_post']),
						'register' => !empty($row['cannot_register']),
						'login' => !empty($row['cannot_login']),
					],
					'is_new' => false,
					'hostname' => '',
					'email' => '',
				];
			}

			if (!empty($row['id_ban'])) {
				$ban_items[$row['id_ban']] = [
					'id' => $row['id_ban'],
					'hits' => $row['hits'],
				];

				if (!empty($row['ip_high'])) {
					$ban_items[$row['id_ban']]['type'] = 'ip';
					$ban_items[$row['id_ban']]['ip'] = IP::range2ip($row['ip_low'], $row['ip_high']);
				} elseif (!empty($row['hostname'])) {
					$ban_items[$row['id_ban']]['type'] = 'hostname';
					$ban_items[$row['id_ban']]['hostname'] = str_replace('%', '*', $row['hostname']);
				} elseif (!empty($row['email_address'])) {
					$ban_items[$row['id_ban']]['type'] = 'email';
					$ban_items[$row['id_ban']]['email'] = str_replace('%', '*', $row['email_address']);
				} elseif (!empty($row['id_member'])) {
					$ban_items[$row['id_ban']]['type'] = 'user';
					$ban_items[$row['id_ban']]['user'] = [
						'id' => $row['id_member'],
						'name' => $row['real_name'],
						'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
						'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
					];
				}
				// Invalid ban (member probably doesn't exist anymore).
				else {
					unset($ban_items[$row['id_ban']]);
					self::removeBanTriggers($row['id_ban']);
				}
			}
		}
		Db::$db->free_result($request);

		IntegrationHook::call('integrate_ban_list', [&$ban_items]);

		return $ban_items;
	}

	/**
	 * Gets the number of ban items belonging to a certain ban group
	 *
	 * @return int The number of ban items
	 */
	public static function list_getNumBanItems(): int
	{
		$ban_group_id = Utils::$context['ban_group_id'] ?? 0;

		$request = Db::$db->query(
			'',
			'SELECT COUNT(bi.id_ban)
			FROM {db_prefix}ban_groups AS bg
				LEFT JOIN {db_prefix}ban_items AS bi ON (bi.id_ban_group = bg.id_ban_group)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
			WHERE bg.id_ban_group = {int:current_ban}',
			[
				'current_ban' => $ban_group_id,
			],
		);
		list($banNumber) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $banNumber;
	}

	/**
	 * Gets ban triggers for the given parameters.
	 *
	 * Callback for $listOptions['get_items'] in BanBrowseTriggers().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string telling ORDER BY how to sort the results
	 * @param string $trigger_type The trigger type - can be 'ip', 'hostname' or 'email'
	 * @return array An array of ban trigger info for the list
	 */
	public static function list_getBanTriggers($start, $items_per_page, $sort, $trigger_type): array
	{
		$ban_triggers = [];

		$where = [
			'ip' => 'bi.ip_low is not null',
			'hostname' => 'bi.hostname != {string:blank_string}',
			'email' => 'bi.email_address != {string:blank_string}',
		];

		$request = Db::$db->query(
			'',
			'SELECT
				bi.id_ban, bi.ip_low, bi.ip_high, bi.hostname, bi.email_address, bi.hits,
				bg.id_ban_group, bg.name' . ($trigger_type === 'member' ? ',
				mem.id_member, mem.real_name' : '') . '
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)' . ($trigger_type === 'member' ? '
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
			WHERE ' . $where[$trigger_type]) . '
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			[
				'blank_string' => '',
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$ban_triggers[] = $row;
		}
		Db::$db->free_result($request);

		return $ban_triggers;
	}

	/**
	 * Returns the total number of ban triggers of the given type.
	 *
	 * Callback for $listOptions['get_count'] in BanBrowseTriggers().
	 *
	 * @param string $trigger_type The trigger type. Can be 'ip', 'hostname' or 'email'
	 * @return int The number of triggers of the specified type
	 */
	public static function list_getNumBanTriggers($trigger_type): int
	{
		$where = [
			'ip' => 'bi.ip_low is not null',
			'hostname' => 'bi.hostname != {string:blank_string}',
			'email' => 'bi.email_address != {string:blank_string}',
		];

		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}ban_items AS bi' . ($trigger_type === 'member' ? '
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)' : '
			WHERE ' . $where[$trigger_type]),
			[
				'blank_string' => '',
			],
		);
		list($num_triggers) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $num_triggers;
	}

	/**
	 * Load a list of ban log entries from the database.
	 * (no permissions check). Callback for $listOptions['get_items'] in BanLog()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string telling ORDER BY how to sort the results
	 * @return array An array of info about the ban log entries for the list.
	 */
	public static function list_getBanLogEntries($start, $items_per_page, $sort): array
	{
		$log_entries = [];

		$request = Db::$db->query(
			'',
			'SELECT lb.id_ban_log, lb.id_member, lb.ip AS ip, COALESCE(lb.email, {string:dash}) AS email, lb.log_time, COALESCE(mem.real_name, {string:blank_string}) AS real_name
			FROM {db_prefix}log_banned AS lb
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lb.id_member)
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items}',
			[
				'blank_string' => '',
				'dash' => '-',
				'sort' => $sort,
				'start' => $start,
				'items' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$row['ip'] = $row['ip'] === null ? '-' : new IP($row['ip']);
			$log_entries[] = $row;
		}
		Db::$db->free_result($request);

		return $log_entries;
	}

	/**
	 * This returns the total count of ban log entries. Callback for $listOptions['get_count'] in BanLog().
	 *
	 * @return int The total number of ban log entries.
	 */
	public static function list_getNumBanLogEntries(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_banned AS lb',
			[
			],
		);
		list($num_entries) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $num_entries;
	}

	/**
	 * Backward compatibility wrapper for the list sub-action.
	 */
	public static function banList(): void
	{
		self::load();
		self::$obj->subaction = 'list';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the edit sub-action.
	 */
	public static function banEdit(): void
	{
		self::load();
		self::$obj->subaction = 'edit';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the browse sub-action.
	 */
	public static function banBrowseTriggers(): void
	{
		self::load();
		self::$obj->subaction = 'browse';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the edittrigger sub-action.
	 */
	public static function banEditTrigger(): void
	{
		self::load();
		self::$obj->subaction = 'edittrigger';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the log sub-action.
	 */
	public static function banLog(): void
	{
		self::load();
		self::$obj->subaction = 'log';
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
		Theme::loadTemplate('ManageBans');

		// Tab data might already be set if this was called from Logs::execute().
		if (empty(Menu::$loaded['admin']->tab_data)) {
			// Tabs for browsing the different ban functions.
			Menu::$loaded['admin']->tab_data = [
				'title' => Lang::$txt['ban_title'],
				'help' => 'ban_members',
				'description' => Lang::$txt['ban_description'],
				'tabs' => [
					'list' => [
						'description' => Lang::$txt['ban_description'],
						'href' => Config::$scripturl . '?action=admin;area=ban;sa=list',
					],
					'add' => [
						'description' => Lang::$txt['ban_description'],
						'href' => Config::$scripturl . '?action=admin;area=ban;sa=add',
					],
					'browse' => [
						'description' => Lang::$txt['ban_trigger_browse_description'],
						'href' => Config::$scripturl . '?action=admin;area=ban;sa=browse',
					],
					'log' => [
						'description' => Lang::$txt['ban_log_description'],
						'href' => Config::$scripturl . '?action=admin;area=ban;sa=log',
						'is_last' => true,
					],
				],
			];
		}

		IntegrationHook::call('integrate_manage_bans', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		// Mark the appropriate menu entry as selected
		if (array_key_exists($this->subaction, Menu::$loaded['admin']->tab_data['tabs'])) {
			Menu::$loaded['admin']->tab_data['tabs'][$this->subaction]['is_selected'] = true;
		}

		Utils::$context['page_title'] = Lang::$txt['ban_title'];
		Utils::$context['sub_action'] = $this->subaction;
	}

	/**
	 * This method handles submitted forms that add, modify or remove ban triggers.
	 */
	protected function edit2(): void
	{
		User::$me->checkSession();
		SecurityToken::validate('admin-bet');

		Utils::$context['ban_errors'] = [];

		// Adding or editing a ban group
		if (isset($_POST['add_ban']) || isset($_POST['modify_ban'])) {
			// Let's collect all the information we need
			$ban_info['id'] = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
			$ban_info['is_new'] = empty($ban_info['id']);
			$ban_info['expire_date'] = !empty($_POST['expire_date']) ? (int) $_POST['expire_date'] : 0;
			$ban_info['expiration'] = [
				'status' => isset($_POST['expiration']) && in_array($_POST['expiration'], ['never', 'one_day', 'expired']) ? $_POST['expiration'] : 'never',
				'days' => $ban_info['expire_date'],
			];
			$ban_info['db_expiration'] = $ban_info['expiration']['status'] == 'never' ? 'NULL' : ($ban_info['expiration']['status'] == 'one_day' ? time() + 24 * 60 * 60 * $ban_info['expire_date'] : 0);
			$ban_info['full_ban'] = empty($_POST['full_ban']) ? 0 : 1;
			$ban_info['reason'] = !empty($_POST['reason']) ? Utils::htmlspecialchars($_POST['reason'], ENT_QUOTES) : '';
			$ban_info['name'] = !empty($_POST['ban_name']) ? Utils::htmlspecialchars($_POST['ban_name'], ENT_QUOTES) : '';
			$ban_info['notes'] = isset($_POST['notes']) ? Utils::htmlspecialchars($_POST['notes'], ENT_QUOTES) : '';
			$ban_info['notes'] = str_replace(["\r", "\n", '  '], ['', '<br>', '&nbsp; '], $ban_info['notes']);
			$ban_info['cannot']['access'] = empty($ban_info['full_ban']) ? 0 : 1;
			$ban_info['cannot']['post'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_post']) ? 0 : 1;
			$ban_info['cannot']['register'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_register']) ? 0 : 1;
			$ban_info['cannot']['login'] = !empty($ban_info['full_ban']) || empty($_POST['cannot_login']) ? 0 : 1;

			IntegrationHook::call('integrate_edit_bans', [&$ban_info, empty($_REQUEST['bg'])]);

			// Limit 'reason' characters
			$ban_info['reason'] = Utils::truncate($ban_info['reason'], 255);

			// Adding a new ban group
			if (empty($_REQUEST['bg'])) {
				$ban_group_id = $this->insertBanGroup($ban_info);
			}
			// Editing an existing ban group
			else {
				$ban_group_id = $this->updateBanGroup($ban_info);
			}

			if (is_numeric($ban_group_id)) {
				$ban_info['id'] = $ban_group_id;
				$ban_info['is_new'] = false;
			}

			Utils::$context['ban'] = $ban_info;
		}

		if (isset($_POST['ban_suggestions'])) {
			// @TODO: is $_REQUEST['bi'] ever set?
			$saved_triggers = $this->saveTriggers($_POST['ban_suggestions'], $ban_info['id'], (int) ($_REQUEST['u'] ?? 0), (int) ($_REQUEST['bi'] ?? 0));
		}

		// Something went wrong somewhere... Oh well, let's go back.
		if (!empty(Utils::$context['ban_errors'])) {
			Utils::$context['ban_suggestions'] = !empty($saved_triggers) ? $saved_triggers : [];

			if (isset($_REQUEST['u'])) {
				Utils::$context['ban']['from_user'] = true;
				Utils::$context['ban_suggestions'] = array_merge(Utils::$context['ban_suggestions'], $this->getMemberData((int) $_REQUEST['u']));
			}

			// Not strictly necessary, but it's nice
			if (!empty(Utils::$context['ban_suggestions']['member']['id'])) {
				Utils::$context['ban_suggestions']['other_ips'] = $this->banLoadAdditionalIPs(Utils::$context['ban_suggestions']['member']['id']);
			}

			$this->edit();

			return;
		}

		Utils::$context['ban_suggestions']['saved_triggers'] = !empty($saved_triggers) ? $saved_triggers : [];

		if (isset($_POST['ban_items'])) {
			$ban_group_id = isset($_REQUEST['bg']) ? (int) $_REQUEST['bg'] : 0;
			array_map('intval', $_POST['ban_items']);

			self::removeBanTriggers($_POST['ban_items'], $ban_group_id);
		}

		IntegrationHook::call('integrate_edit_bans_post', []);

		// Register the last modified date.
		Config::updateModSettings(['banLastUpdated' => time()]);

		// Update the member table to represent the new ban situation.
		self::updateBanMembers();
		Utils::redirectexit('action=admin;area=ban;sa=edit;bg=' . $ban_group_id);
	}

	/**
	 * Finds additional IPs related to a certain user
	 *
	 * @param int $member_id The ID of the member to get additional IPs for
	 * @return array An containing two arrays - ips_in_messages (IPs used in posts) and ips_in_errors (IPs used in error messages)
	 */
	protected function banLoadAdditionalIPs($member_id): array
	{
		// Borrowing a few language strings from profile.
		Lang::load('Profile');

		$search_list = [];
		IntegrationHook::call('integrate_load_addtional_ip_ban', [&$search_list]);

		$search_list += [
			'ips_in_messages' => [$this, 'banLoadAdditionalIPsMember'],
			'ips_in_errors' => [$this, 'banLoadAdditionalIPsError'],
		];

		$return = [];

		foreach ($search_list as $key => $callable) {
			if (is_callable($callable)) {
				$return[$key] = call_user_func($callable, $member_id);
			}
		}

		return $return;
	}

	/**
	 * Loads additional IPs used by a specific member
	 *
	 * @param int $member_id The ID of the member
	 * @return array An array of IPs used in posts by this member
	 */
	protected function banLoadAdditionalIPsMember($member_id): array
	{
		// Find some additional IP's used by this member.
		$message_ips = [];

		$request = Db::$db->query(
			'',
			'SELECT DISTINCT poster_ip
			FROM {db_prefix}messages
			WHERE id_member = {int:current_user}
				AND poster_ip IS NOT NULL
			ORDER BY poster_ip',
			[
				'current_user' => $member_id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$message_ips[] = new IP($row['poster_ip']);
		}
		Db::$db->free_result($request);

		return $message_ips;
	}

	/**
	 * Loads additional IPs used by a member from the error log
	 *
	 * @param int $member_id The ID of the member
	 * @return array An array of IPs associated with error messages generated by this user
	 */
	protected function banLoadAdditionalIPsError($member_id): array
	{
		$error_ips = [];

		$request = Db::$db->query(
			'',
			'SELECT DISTINCT ip
			FROM {db_prefix}log_errors
			WHERE id_member = {int:current_user}
				AND ip IS NOT NULL
			ORDER BY ip',
			[
				'current_user' => $member_id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$error_ips[] = new IP($row['ip']);
		}
		Db::$db->free_result($request);

		return $error_ips;
	}

	/**
	 * Saves one or more ban triggers into a ban item.
	 *
	 * Checks the $_POST variable to verify that the trigger is present.
	 *
	 * @param array $suggestions An array of suggested triggers (IP, email, etc.).
	 * @param int $ban_group The ID of the group we're saving bans for.
	 * @param int $member The ID of the member associated with this ban (if applicable).
	 * @param int $ban_id The ID of the ban (0 if this is a new ban).
	 * @return array Triggers that encountered errors. Empty if triggers saved successfully.
	 */
	protected function saveTriggers(array $suggestions, $ban_group, $member = 0, $ban_id = 0)
	{
		$triggers = [
			'main_ip' => '',
			'hostname' => '',
			'email' => '',
			'member' => [
				'id' => $member,
			],
		];

		foreach ($suggestions as $key => $value) {
			if (is_array($value)) {
				$triggers[$key] = $value;
			} else {
				$triggers[$value] = !empty($_POST[$value]) ? $_POST[$value] : '';
			}
		}

		$ban_triggers = $this->validateTriggers($triggers);

		IntegrationHook::call('integrate_save_triggers', [&$ban_triggers, &$ban_group]);

		// Time to save!
		if (!empty($ban_triggers['ban_triggers']) && empty(Utils::$context['ban_errors'])) {
			if (empty($ban_id)) {
				$this->addTriggers($ban_group, $ban_triggers['ban_triggers'], $ban_triggers['log_info']);
			} else {
				$this->updateTriggers($ban_id, $ban_group, array_shift($ban_triggers['ban_triggers']), $ban_triggers['log_info']);
			}
		}

		if (!empty(Utils::$context['ban_errors'])) {
			return $triggers;
		}

		return [];
	}

	/**
	 * This function removes a bunch of ban groups based on ids.
	 *
	 * Doesn't clean the input.
	 *
	 * @param array $group_ids The IDs of the groups to remove.
	 * @return bool Returns true if successful or false if $group_ids is empty
	 */
	protected function removeBanGroups($group_ids): bool
	{
		if (!is_array($group_ids)) {
			$group_ids = [$group_ids];
		}

		$group_ids = array_unique($group_ids);

		if (empty($group_ids)) {
			return false;
		}

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}ban_groups
			WHERE id_ban_group IN ({array_int:ban_list})',
			[
				'ban_list' => $group_ids,
			],
		);

		// Remove all ban triggers for these bans groups
		$request = Db::$db->query(
			'',
			'SELECT id_ban
			FROM {db_prefix}ban_items
			WHERE id_ban_group IN ({array_int:ban_list})',
			[
				'ban_list' => $group_ids,
			],
		);

		$id_ban_triggers = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$id_ban_triggers[] = $row['id_ban'];
		}
		Db::$db->free_result($request);

		self::removeBanTriggers($id_ban_triggers);

		return true;
	}

	/**
	 * Removes logs.
	 *
	 * Doesn't clean the input.
	 *
	 * @param array $ids IDs of the log entries to remove, or empty to remove all.
	 * @return bool Returns true if successful or false if $ids is invalid.
	 */
	protected function removeBanLogs($ids = []): bool
	{
		if (empty($ids)) {
			Db::$db->query(
				'truncate_table',
				'TRUNCATE {db_prefix}log_banned',
				[
				],
			);
		} else {
			$ids = array_filter(array_unique(array_map('intval', (array) $ids)));

			if (empty($ids)) {
				return false;
			}

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_banned
				WHERE id_ban_log IN ({array_int:ban_list})',
				[
					'ban_list' => $ids,
				],
			);
		}

		return true;
	}

	/**
	 * This function validates the ban triggers
	 *
	 * Errors in Utils::$context['ban_errors']
	 *
	 * @param array $triggers The triggers to validate
	 * @return array An array of riggers and log info ready to be used
	 */
	protected function validateTriggers(&$triggers): array
	{
		if (empty($triggers)) {
			Utils::$context['ban_errors'][] = 'ban_empty_triggers';
		}

		$ban_triggers = [];
		$log_info = [];

		foreach ($triggers as $key => $value) {
			if (!empty($value)) {
				if ($key == 'member') {
					continue;
				}

				if ($key == 'main_ip') {
					$value = trim($value);
					$ip_range = IP::ip2range($value);

					if (!$this->checkExistingTriggerIP($ip_range, $value)) {
						Utils::$context['ban_errors'][] = 'invalid_ip';
					} else {
						$ban_triggers['main_ip'] = [
							'ip_low' => $ip_range['low'],
							'ip_high' => $ip_range['high'],
						];
					}
				} elseif ($key == 'hostname') {
					if (preg_match('/[^\w.\-*]/', $value) == 1) {
						Utils::$context['ban_errors'][] = 'invalid_hostname';
					} else {
						// Replace the * wildcard by a MySQL wildcard %.
						$value = substr(str_replace('*', '%', $value), 0, 255);

						$ban_triggers['hostname']['hostname'] = $value;
					}
				} elseif ($key == 'email') {
					if (preg_match('/[^\w.\-\+*@]/', $value) == 1) {
						Utils::$context['ban_errors'][] = 'invalid_email';
					}

					// Check the user is not banning an admin.
					$request = Db::$db->query(
						'',
						'SELECT id_member
						FROM {db_prefix}members
						WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
							AND email_address LIKE {string:email}
						LIMIT 1',
						[
							'admin_group' => 1,
							'email' => $value,
						],
					);

					if (Db::$db->num_rows($request) != 0) {
						Utils::$context['ban_errors'][] = 'no_ban_admin';
					}
					Db::$db->free_result($request);

					$value = substr(strtolower(str_replace('*', '%', $value)), 0, 255);

					$ban_triggers['email']['email_address'] = $value;
				} elseif ($key == 'user') {
					$user = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', Utils::htmlspecialchars($value, ENT_QUOTES));

					$request = Db::$db->query(
						'',
						'SELECT id_member, (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0) AS isAdmin
						FROM {db_prefix}members
						WHERE member_name = {string:username} OR real_name = {string:username}
						LIMIT 1',
						[
							'admin_group' => 1,
							'username' => $user,
						],
					);

					if (Db::$db->num_rows($request) == 0) {
						Utils::$context['ban_errors'][] = 'invalid_username';
					}
					list($value, $isAdmin) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);

					if ($isAdmin && strtolower($isAdmin) != 'f') {
						unset($value);
						Utils::$context['ban_errors'][] = 'no_ban_admin';
					} else {
						$ban_triggers['user']['id_member'] = $value;
					}
				} elseif (in_array($key, ['ips_in_messages', 'ips_in_errors'])) {
					// Special case, those two are arrays themselves
					$values = array_unique($value);
					unset($value);

					// Don't add the main IP again.
					if (isset($triggers['main_ip'])) {
						$values = array_diff($values, [$triggers['main_ip']]);
					}

					foreach ($values as $val) {
						$val = trim($val);
						$ip_range = IP::ip2range($val);

						if (!$this->checkExistingTriggerIP($ip_range, $val)) {
							Utils::$context['ban_errors'][] = 'invalid_ip';
						} else {
							$ban_triggers[$key][] = [
								'ip_low' => $ip_range['low'],
								'ip_high' => $ip_range['high'],
							];

							$log_info[] = [
								'value' => $val,
								'bantype' => 'ip_range',
							];
						}
					}
				} else {
					Utils::$context['ban_errors'][] = 'no_bantype_selected';
				}

				if (isset($value) && !is_array($value)) {
					$log_info[] = [
						'value' => $value,
						'bantype' => $key,
					];
				}
			}
		}

		return ['ban_triggers' => $ban_triggers, 'log_info' => $log_info];
	}

	/**
	 * Checks whether a given IP range already exists in the trigger list.
	 *
	 * @param array $ip_array An array of IP trigger data.
	 * @param string $fullip The full IP.
	 * @return bool Whether the IP trigger data is valid.
	 */
	protected function checkExistingTriggerIP($ip_array, $fullip = ''): bool
	{
		$values = [
			'ip_low' => $ip_array['low'],
			'ip_high' => $ip_array['high'],
		];

		$is_valid = true;

		$request = Db::$db->query(
			'',
			'SELECT bg.id_ban_group, bg.name
			FROM {db_prefix}ban_groups AS bg
			INNER JOIN {db_prefix}ban_items AS bi ON
				(bi.id_ban_group = bg.id_ban_group)
				AND ip_low = {inet:ip_low} AND ip_high = {inet:ip_high}
			LIMIT 1',
			$values,
		);

		if (Db::$db->num_rows($request) != 0) {
			$is_valid = false;

			$row = Db::$db->fetch_assoc($request);

			// @todo Why do we die on this error, but not others?
			ErrorHandler::fatalLang('ban_trigger_already_exists', false, [
				$fullip,
				'<a href="' . Config::$scripturl . '?action=admin;area=ban;sa=edit;bg=' . $row['id_ban_group'] . '">' . $row['name'] . '</a>',
			]);
		}
		Db::$db->free_result($request);

		return $is_valid;
	}

	/**
	 * This function actually inserts the ban triggers into the database.
	 *
	 * Errors in Utils::$context['ban_errors']
	 *
	 * @param int $group_id The ID of the group to add the triggers to.
	 *    0 to create a new one.
	 * @param array $triggers The triggers to add.
	 * @param array $logs The log data.
	 * @return bool Whether or not the action was successful.
	 */
	protected function addTriggers($group_id = 0, $triggers = [], $logs = []): bool
	{
		if (empty($group_id)) {
			Utils::$context['ban_errors'][] = 'ban_id_empty';
		}

		// Preset all values that are required.
		$values = [
			'id_ban_group' => $group_id,
			'hostname' => '',
			'email_address' => '',
			'id_member' => 0,
			'ip_low' => 'null',
			'ip_high' => 'null',
		];

		$insertKeys = [
			'id_ban_group' => 'int',
			'hostname' => 'string',
			'email_address' => 'string',
			'id_member' => 'int',
			'ip_low' => 'inet',
			'ip_high' => 'inet',
		];

		$insertTriggers = [];

		foreach ($triggers as $key => $trigger) {
			// Exceptions, exceptions, exceptions...always exceptions... :P
			if (in_array($key, ['ips_in_messages', 'ips_in_errors'])) {
				foreach ($trigger as $real_trigger) {
					$insertTriggers[] = array_merge($values, $real_trigger);
				}
			} else {
				$insertTriggers[] = array_merge($values, $trigger);
			}
		}

		if (empty($insertTriggers)) {
			Utils::$context['ban_errors'][] = 'ban_no_triggers';
		}

		if (!empty(Utils::$context['ban_errors'])) {
			return false;
		}

		Db::$db->insert(
			'',
			'{db_prefix}ban_items',
			$insertKeys,
			$insertTriggers,
			['id_ban'],
		);

		self::logTriggersUpdates($logs, true);

		return true;
	}

	/**
	 * Updates an existing ban trigger in the database.
	 *
	 * Errors in Utils::$context['ban_errors']
	 *
	 * @param int $ban_item The ID of the ban item.
	 * @param int $group_id The ID of the ban group.
	 * @param array $trigger An array of triggers.
	 * @param array $logs An array of log info.
	 */
	protected function updateTriggers($ban_item = 0, $group_id = 0, $trigger = [], $logs = []): void
	{
		if (empty($ban_item)) {
			Utils::$context['ban_errors'][] = 'ban_ban_item_empty';
		}

		if (empty($group_id)) {
			Utils::$context['ban_errors'][] = 'ban_id_empty';
		}

		if (empty($trigger)) {
			Utils::$context['ban_errors'][] = 'ban_no_triggers';
		}

		if (!empty(Utils::$context['ban_errors'])) {
			return;
		}

		// Preset all values that are required.
		$values = [
			'id_ban_group' => $group_id,
			'hostname' => '',
			'email_address' => '',
			'id_member' => 0,
			'ip_low' => 'null',
			'ip_high' => 'null',
		];

		$trigger = array_merge($values, $trigger);

		Db::$db->query(
			'',
			'UPDATE {db_prefix}ban_items
			SET
				hostname = {string:hostname}, email_address = {string:email_address}, id_member = {int:id_member},
				ip_low = {inet:ip_low}, ip_high = {inet:ip_high}
			WHERE id_ban = {int:ban_item}
				AND id_ban_group = {int:id_ban_group}',
			array_merge($trigger, [
				'id_ban_group' => $group_id,
				'ban_item' => $ban_item,
			]),
		);

		self::logTriggersUpdates($logs, false);
	}

	/**
	 * Updates an existing ban group.
	 *
	 * Errors in Utils::$context['ban_errors']
	 *
	 * @param array $ban_info An array of info about the ban group.
	 *    Should have name and may also have an id.
	 * @return int The ban group's ID.
	 */
	protected function updateBanGroup($ban_info = []): int
	{
		if (empty($ban_info['name'])) {
			Utils::$context['ban_errors'][] = 'ban_name_empty';
		}

		if (empty($ban_info['id'])) {
			Utils::$context['ban_errors'][] = 'ban_id_empty';
		}

		if (
			empty($ban_info['cannot']['access'])
			&& empty($ban_info['cannot']['register'])
			&& empty($ban_info['cannot']['post'])
			&& empty($ban_info['cannot']['login'])
		) {
			Utils::$context['ban_errors'][] = 'ban_unknown_restriction_type';
		}

		if (!empty($ban_info['id'])) {
			// Verify the ban group exists.
			$request = Db::$db->query(
				'',
				'SELECT id_ban_group
				FROM {db_prefix}ban_groups
				WHERE id_ban_group = {int:ban_group}
				LIMIT 1',
				[
					'ban_group' => $ban_info['id'],
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				Utils::$context['ban_errors'][] = 'ban_not_found';
			}
			Db::$db->free_result($request);
		}

		if (!empty($ban_info['name'])) {
			// Make sure the name does not already exist (Of course, if it exists in the ban group we are editing, proceed.)
			$request = Db::$db->query(
				'',
				'SELECT id_ban_group
				FROM {db_prefix}ban_groups
				WHERE name = {string:new_ban_name}
					AND id_ban_group != {int:ban_group}
				LIMIT 1',
				[
					'ban_group' => empty($ban_info['id']) ? 0 : $ban_info['id'],
					'new_ban_name' => $ban_info['name'],
				],
			);

			if (Db::$db->num_rows($request) != 0) {
				Utils::$context['ban_errors'][] = 'ban_name_exists';
			}
			Db::$db->free_result($request);
		}

		if (empty(Utils::$context['ban_errors'])) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}ban_groups
				SET
					name = {string:ban_name},
					reason = {string:reason},
					notes = {string:notes},
					expire_time = {raw:expiration},
					cannot_access = {int:cannot_access},
					cannot_post = {int:cannot_post},
					cannot_register = {int:cannot_register},
					cannot_login = {int:cannot_login}
				WHERE id_ban_group = {int:id_ban_group}',
				[
					'expiration' => $ban_info['db_expiration'],
					'cannot_access' => $ban_info['cannot']['access'],
					'cannot_post' => $ban_info['cannot']['post'],
					'cannot_register' => $ban_info['cannot']['register'],
					'cannot_login' => $ban_info['cannot']['login'],
					'id_ban_group' => $ban_info['id'],
					'ban_name' => $ban_info['name'],
					'reason' => $ban_info['reason'],
					'notes' => $ban_info['notes'],
				],
			);
		}

		return $ban_info['id'];
	}

	/**
	 * Creates a new ban group.
	 *
	 * If the group is successfully created the ID is returned
	 * On error the error code is returned or false
	 *
	 * Errors in Utils::$context['ban_errors']
	 *
	 * @param array $ban_info An array containing 'name', which is the name of the ban group.
	 * @return int|false The ban group's ID, or false on error.
	 */
	protected function insertBanGroup($ban_info = []): int|false
	{
		if (empty($ban_info['name'])) {
			Utils::$context['ban_errors'][] = 'ban_name_empty';
		}

		if (
			empty($ban_info['cannot']['access'])
			&& empty($ban_info['cannot']['register'])
			&& empty($ban_info['cannot']['post'])
			&& empty($ban_info['cannot']['login'])
		) {
			Utils::$context['ban_errors'][] = 'ban_unknown_restriction_type';
		}

		if (!empty($ban_info['name'])) {
			// Check whether a ban with this name already exists.
			$request = Db::$db->query(
				'',
				'SELECT id_ban_group
				FROM {db_prefix}ban_groups
				WHERE name = {string:new_ban_name}' . '
				LIMIT 1',
				[
					'new_ban_name' => $ban_info['name'],
				],
			);

			if (Db::$db->num_rows($request) == 1) {
				Utils::$context['ban_errors'][] = 'ban_name_exists';
			}
			Db::$db->free_result($request);
		}

		if (!empty(Utils::$context['ban_errors'])) {
			return false;
		}

		// Yes yes, we're ready to add now.
		$ban_info['id'] = Db::$db->insert(
			'',
			'{db_prefix}ban_groups',
			[
				'name' => 'string-20', 'ban_time' => 'int', 'expire_time' => 'raw', 'cannot_access' => 'int', 'cannot_register' => 'int',
				'cannot_post' => 'int', 'cannot_login' => 'int', 'reason' => 'string-255', 'notes' => 'string-65534',
			],
			[
				$ban_info['name'], time(), $ban_info['db_expiration'], $ban_info['cannot']['access'], $ban_info['cannot']['register'],
				$ban_info['cannot']['post'], $ban_info['cannot']['login'], $ban_info['reason'], $ban_info['notes'],
			],
			['id_ban_group'],
			1,
		);

		if (empty($ban_info['id'])) {
			Utils::$context['ban_errors'][] = 'impossible_insert_new_bangroup';

			return false;
		}

		return $ban_info['id'];
	}

	/**
	 * Gets basic member data for the ban.
	 *
	 * @param int $id The ID of the member to get data for.
	 * @return array The ID, name, main IP, and email address of the member.
	 */
	protected function getMemberData($id): array
	{
		$suggestions = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, real_name, member_ip, email_address
			FROM {db_prefix}members
			WHERE id_member = {int:current_user}
			LIMIT 1',
			[
				'current_user' => $id,
			],
		);

		if (Db::$db->num_rows($request) > 0) {
			list($suggestions['member']['id'], $suggestions['member']['name'], $suggestions['main_ip'], $suggestions['email']) = Db::$db->fetch_row($request);

			$suggestions['main_ip'] = new IP($suggestions['main_ip']);
		}
		Db::$db->free_result($request);

		return $suggestions;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * This function removes a bunch of triggers based on IDs.
	 *
	 * Doesn't clean the inputs.
	 *
	 * @param array $items_ids The triggers to remove.
	 * @param int $group_id The ID of the group these triggers are associated with.
	 *    If null, the triggers will be deleted from all groups.
	 * @return bool Whether the operation was successful.
	 */
	protected static function removeBanTriggers($items_ids = [], $group_id = null): bool
	{
		if (isset($group_id)) {
			$group_id = (int) $group_id;
		}

		if (empty($group_id) && empty($items_ids)) {
			return false;
		}

		if (!is_array($items_ids)) {
			$items_ids = [$items_ids];
		}

		$log_info = [];
		$ban_items = [];

		IntegrationHook::call('integrate_remove_triggers', [&$items_ids, $group_id]);

		// First order of business: Load up the info so we can log this...
		$request = Db::$db->query(
			'',
			'SELECT
				bi.id_ban, bi.hostname, bi.email_address, bi.id_member, bi.hits,
				bi.ip_low, bi.ip_high,
				COALESCE(mem.id_member, 0) AS id_member, mem.member_name, mem.real_name
			FROM {db_prefix}ban_items AS bi
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = bi.id_member)
			WHERE bi.id_ban IN ({array_int:ban_list})',
			[
				'ban_list' => $items_ids,
			],
		);

		// Get all the info for the log
		while ($row = Db::$db->fetch_assoc($request)) {
			if (!empty($row['id_ban'])) {
				$ban_items[$row['id_ban']] = [
					'id' => $row['id_ban'],
				];

				if (!empty($row['ip_high'])) {
					$ban_items[$row['id_ban']]['type'] = 'ip';
					$ban_items[$row['id_ban']]['ip'] = IP::range2ip($row['ip_low'], $row['ip_high']);

					$is_range = (strpos($ban_items[$row['id_ban']]['ip'], '-') !== false || strpos($ban_items[$row['id_ban']]['ip'], '*') !== false);

					$log_info[] = [
						'bantype' => ($is_range ? 'ip_range' : 'main_ip'),
						'value' => $ban_items[$row['id_ban']]['ip'],
					];
				} elseif (!empty($row['hostname'])) {
					$ban_items[$row['id_ban']]['type'] = 'hostname';
					$ban_items[$row['id_ban']]['hostname'] = str_replace('%', '*', $row['hostname']);

					$log_info[] = [
						'bantype' => 'hostname',
						'value' => $row['hostname'],
					];
				} elseif (!empty($row['email_address'])) {
					$ban_items[$row['id_ban']]['type'] = 'email';
					$ban_items[$row['id_ban']]['email'] = str_replace('%', '*', $row['email_address']);

					$log_info[] = [
						'bantype' => 'email',
						'value' => $ban_items[$row['id_ban']]['email'],
					];
				} elseif (!empty($row['id_member'])) {
					$ban_items[$row['id_ban']]['type'] = 'user';
					$ban_items[$row['id_ban']]['user'] = [
						'id' => $row['id_member'],
						'name' => $row['real_name'],
						'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
						'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
					];

					$log_info[] = [
						'bantype' => 'user',
						'value' => $row['id_member'],
					];
				}
			}
		}
		Db::$db->free_result($request);

		// Log this!
		self::logTriggersUpdates($log_info, false, true);

		if (isset($group_id)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}ban_items
				WHERE id_ban IN ({array_int:ban_list})
					AND id_ban_group = {int:ban_group}',
				[
					'ban_list' => $items_ids,
					'ban_group' => $group_id,
				],
			);
		} elseif (!empty($items_ids)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}ban_items
				WHERE id_ban IN ({array_int:ban_list})',
				[
					'ban_list' => $items_ids,
				],
			);
		}

		return true;
	}

	/**
	 * A small function to unify logging of triggers (updates and new)
	 *
	 * @param array $logs an array of logs, each log contains the following keys:
	 *    - bantype: a known type of ban (ip_range, hostname, email, user, main_ip)
	 *    - value: the value of the bantype (e.g. the IP or the email address banned)
	 * @param bool $new Whether the trigger is new or an update of an existing one
	 * @param bool $removal Whether the trigger is being deleted
	 */
	protected static function logTriggersUpdates($logs, $new = true, $removal = false): void
	{
		if (empty($logs)) {
			return;
		}

		$log_name_map = [
			'main_ip' => 'ip_range',
			'hostname' => 'hostname',
			'email' => 'email',
			'user' => 'member',
			'ip_range' => 'ip_range',
		];

		// Log the addion of the ban entries into the moderation log.
		foreach ($logs as $log) {
			Logging::logAction('ban' . ($removal == true ? 'remove' : ''), [
				$log_name_map[$log['bantype']] => $log['value'],
				'new' => empty($new) ? 0 : 1,
				'remove' => empty($removal) ? 0 : 1,
				'type' => $log['bantype'],
			]);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Bans::exportStatic')) {
	Bans::exportStatic();
}

?>