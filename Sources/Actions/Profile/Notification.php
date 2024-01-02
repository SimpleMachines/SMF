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

namespace SMF\Actions\Profile;

use SMF\Actions\ActionInterface;
use SMF\Actions\Notify;
use SMF\Alert;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Profile;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Handles preferences related to notifications.
 */
class Notification implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'notification',
			'list_getTopicNotificationCount' => 'list_getTopicNotificationCount',
			'list_getTopicNotifications' => 'list_getTopicNotifications',
			'list_getBoardNotifications' => 'list_getBoardNotifications',
			'alert_configuration' => 'alert_configuration',
			'alert_markread' => 'alert_markread',
			'alert_notifications_topics' => 'alert_notifications_topics',
			'alert_notifications_boards' => 'alert_notifications_boards',
			'makeNotificationChanges' => 'makeNotificationChanges',
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
	public string $subaction = 'alerts';

	/**
	 * @var array
	 *
	 * Defines all the types of alerts and their default values.
	 *
	 * The 'alert' and 'email' keys are required for each alert type.
	 * The 'help' and 'permission' keys are optional.
	 *
	 * Valid values for 'alert' and 'email' keys are: 'always', 'yes', 'never'.
	 * If using 'always' or 'never' you should add a help string.
	 */
	public array $alert_types = [
		'board' => [
			'topic_notify' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
			'board_notify' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
		],
		'msg' => [
			'msg_mention' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
			'msg_quote' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
			'msg_like' => [
				'alert' => 'yes',
				'email' => 'never',
			],
			'unapproved_reply' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
		],
		'pm' => [
			'pm_new' => [
				'alert' => 'never',
				'email' => 'yes',
				'help' => 'alert_pm_new',
				'permission' => [
					'name' => 'pm_read',
					'is_board' => false,
				],
			],
			'pm_reply' => [
				'alert' => 'never',
				'email' => 'yes',
				'help' => 'alert_pm_new',
				'permission' => [
					'name' => 'pm_send',
					'is_board' => false,
				],
			],
		],
		'groupr' => [
			'groupr_approved' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
			'groupr_rejected' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
		],
		'moderation' => [
			'unapproved_attachment' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'approve_posts',
					'is_board' => true,
				],
			],
			'unapproved_post' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'approve_posts',
					'is_board' => true,
				],
			],
			'msg_report' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'moderate_board',
					'is_board' => true,
				],
			],
			'msg_report_reply' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'moderate_board',
					'is_board' => true,
				],
			],
			'member_report' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'moderate_forum',
					'is_board' => false,
				],
			],
			'member_report_reply' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'moderate_forum',
					'is_board' => false,
				],
			],
		],
		'members' => [
			'member_register' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'moderate_forum',
					'is_board' => false,
				],
			],
			'request_group' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
			'warn_any' => [
				'alert' => 'yes',
				'email' => 'yes',
				'permission' => [
					'name' => 'issue_warning',
					'is_board' => false,
				],
			],
			'buddy_request' => [
				'alert' => 'yes',
				'email' => 'never',
			],
			'birthday' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
		],
		'calendar' => [
			'event_new' => [
				'alert' => 'yes',
				'email' => 'yes',
				'help' => 'alert_event_new',
			],
		],
		'paidsubs' => [
			'paidsubs_expiring' => [
				'alert' => 'yes',
				'email' => 'yes',
			],
		],
	];

	/**
	 * @var array
	 *
	 * The group level options.
	 */
	public array $group_options = [
		'board' => [
			'msg_auto_notify' => [
				'check',
				'msg_auto_notify',
				'label' => 'after',
			],
			'msg_receive_body' => [
				'check',
				'msg_receive_body',
				'label' => 'after',
			],
			'msg_notify_pref' => [
				'select',
				'msg_notify_pref',
				'label' => 'before',
				'opts' => [
					0 => 'alert_opt_msg_notify_pref_never',
					1 => 'alert_opt_msg_notify_pref_instant',
					2 => 'alert_opt_msg_notify_pref_first',
					3 => 'alert_opt_msg_notify_pref_daily',
					4 => 'alert_opt_msg_notify_pref_weekly',
				],
			],
			'msg_notify_type' => [
				'select',
				'msg_notify_type',
				'label' => 'before',
				'opts' => [
					1 => 'notify_send_type_everything',
					2 => 'notify_send_type_everything_own',
					3 => 'notify_send_type_only_replies',
					4 => 'notify_send_type_nothing',
				],
			],
		],
		'pm' => [
			'pm_notify' => [
				'select',
				'pm_notify',
				'label' => 'before',
				'opts' => [
					1 => 'email_notify_all',
					2 => 'email_notify_buddies',
				],
			],
		],
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'alerts' => 'configuration',
		'markread' => 'markRead',
		'topics' => 'topics',
		'boards' => 'boards',
	];

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subtemplates = [
		'alerts' => 'alert_configuration',
		'markread' => 'alert_markread',
		'topics' => 'alert_notifications_topics',
		'boards' => 'alert_notifications_boards',
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
		// Going to want this for consistency.
		Theme::loadCSSFile('admin.css', [], 'smf_admin');

		Utils::$context['sub_template'] = self::$subtemplates[$this->subaction];

		if (isset(Menu::$loaded['profile'])) {
			Menu::$loaded['profile']->tab_data = [
				'title' => Lang::$txt['notification'],
				'help' => '',
				'description' => Lang::$txt['notification_info'],
			];
		}

		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Handles configuration of alert preferences.
	 *
	 * @param bool $defaultSettings If true, we are loading default options.
	 */
	public function configuration($defaultSettings = false)
	{
		if (!isset(Utils::$context['token_check'])) {
			Utils::$context['token_check'] = 'profile-nt' . Profile::$member->id;
		}

		User::$me->kickIfGuest();

		if (!User::$me->is_owner) {
			User::$me->isAllowedTo('profile_extra_any');
		}

		// Set the post action if we're coming from the profile...
		if (!isset(Utils::$context['action'])) {
			Utils::$context['action'] = 'action=profile;area=notification;sa=alerts;u=' . Profile::$member->id;
		}

		// What options are set?
		Profile::$member->loadThemeOptions($defaultSettings);

		Theme::loadJavaScriptFile('alertSettings.js', ['minimize' => true], 'smf_alertSettings');

		// Now load all the values for this user.
		$prefs = Notify::getNotifyPrefs(Profile::$member->id, '', Profile::$member->id != 0);

		Utils::$context['alert_prefs'] = !empty($prefs[Profile::$member->id]) ? $prefs[Profile::$member->id] : [];

		Utils::$context['member'] += [
			'alert_timeout' => Utils::$context['alert_prefs']['alert_timeout'] ?? 10,
			'notify_announcements' => Utils::$context['alert_prefs']['announcements'] ?? 0,
		];

		// There are certain things that are disabled at the group level.
		if (empty(Config::$modSettings['cal_enabled'])) {
			unset($this->alert_types['calendar']);
		}

		// Disable paid subscriptions at group level if they're disabled.
		if (empty(Config::$modSettings['paid_enabled'])) {
			unset($this->alert_types['paidsubs']);
		}

		// Disable membergroup requests at group level if they're disabled.
		if (empty(Config::$modSettings['show_group_membership'])) {
			unset($this->alert_types['groupr'], $this->alert_types['members']['request_group']);
		}

		// Disable mentions if they're disabled.
		if (empty(Config::$modSettings['enable_mentions'])) {
			unset($this->alert_types['msg']['msg_mention']);
		}

		// Disable likes if they're disabled.
		if (empty(Config::$modSettings['enable_likes'])) {
			unset($this->alert_types['msg']['msg_like']);
		}

		// Disable buddy requests if they're disabled.
		if (empty(Config::$modSettings['enable_buddylist'])) {
			unset($this->alert_types['members']['buddy_request']);
		}

		// Disable sending the body if it's disabled.
		if (!empty(Config::$modSettings['disallow_sendBody'])) {
			$this->group_options['board']['msg_receive_body'][0] = 'hide';
		}

		// Finalize the string values of the options.
		foreach ($this->group_options as &$options) {
			foreach ($options as &$option) {
				if (!isset($option['opts'])) {
					continue;
				}

				foreach ($option['opts'] as &$value) {
					$value = Lang::$txt[$value] ?? $value;
				}
			}
		}

		// Now, now, we could pass this through global but we should really get into the habit of
		// passing content to hooks, not expecting hooks to splatter everything everywhere.
		IntegrationHook::call('integrate_alert_types', [&$this->alert_types, &$this->group_options]);

		// Now we have to do some permissions testing - but only if we're not loading this from the admin center
		if (!empty(Profile::$member->id)) {
			$group_permissions = ['manage_membergroups'];
			$board_permissions = [];

			foreach ($this->alert_types as $group => $items) {
				foreach ($items as $alert_key => $alert_value) {
					if (isset($alert_value['permission'])) {
						if (empty($alert_value['permission']['is_board'])) {
							$group_permissions[] = $alert_value['permission']['name'];
						} else {
							$board_permissions[] = $alert_value['permission']['name'];
						}
					}
				}
			}

			$member_groups = User::getGroupsWithPermissions($group_permissions, $board_permissions);

			if (empty($member_groups['manage_membergroups']['allowed'])) {
				$request = Db::$db->query(
					'',
					'SELECT COUNT(*)
					FROM {db_prefix}group_moderators
					WHERE id_member = {int:memID}',
					[
						'memID' => Profile::$member->id,
					],
				);
				list($is_group_moderator) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				if (empty($is_group_moderator)) {
					unset($this->alert_types['members']['request_group']);
				}
			}

			foreach ($this->alert_types as $group => $items) {
				foreach ($items as $alert_key => $alert_value) {
					if (isset($alert_value['permission'])) {
						$allowed = count(array_intersect(Profile::$member->groups, $member_groups[$alert_value['permission']['name']]['allowed'])) != 0;

						if (!$allowed) {
							unset($this->alert_types[$group][$alert_key]);
						}
					}
				}

				if (empty($this->alert_types[$group])) {
					unset($this->alert_types[$group]);
				}
			}
		}

		// And finally, exporting it to be useful later.
		Utils::$context['alert_types'] = $this->alert_types;
		Utils::$context['alert_group_options'] = $this->group_options;

		Utils::$context['alert_bits'] = [
			'alert' => 0b01,
			'email' => 0b10,
		];

		if (isset($_POST['notify_submit'])) {
			User::$me->checkSession();
			SecurityToken::validate(Utils::$context['token_check'], 'post');

			// We need to step through the list of valid settings and figure out what the user has set.
			$update_prefs = [];

			// Now the group level options
			foreach ($this->group_options as $opt_group => $group) {
				foreach ($group as $this_option) {
					switch ($this_option[0]) {
						case 'check':
							$update_prefs[$this_option[1]] = !empty($_POST['opt_' . $this_option[1]]) ? 1 : 0;
							break;

						case 'select':
							if (isset($_POST['opt_' . $this_option[1]], $this_option['opts'][$_POST['opt_' . $this_option[1]]])) {
								$update_prefs[$this_option[1]] = $_POST['opt_' . $this_option[1]];
							} else {
								// We didn't have a sane value. Let's grab the first item from the possibles.
								$keys = array_keys($this_option['opts']);
								$first = array_shift($keys);
								$update_prefs[$this_option[1]] = $first;
							}
							break;
					}
				}
			}

			// Now the individual options
			foreach (Utils::$context['alert_types'] as $alert_group => $items) {
				foreach ($items as $item_key => $this_options) {
					$this_value = 0;

					foreach (Utils::$context['alert_bits'] as $type => $bitvalue) {
						if ($this_options[$type] == 'yes' && !empty($_POST[$type . '_' . $item_key]) || $this_options[$type] == 'always') {
							$this_value |= $bitvalue;
						}
					}

					$update_prefs[$item_key] = $this_value;
				}
			}

			if (isset($_POST['opt_alert_timeout'])) {
				$update_prefs['alert_timeout'] = Utils::$context['member']['alert_timeout'] = (int) $_POST['opt_alert_timeout'];
			} else {
				$update_prefs['alert_timeout'] = Utils::$context['alert_prefs']['alert_timeout'];
			}

			if (isset($_POST['notify_announcements'])) {
				$update_prefs['announcements'] = Utils::$context['member']['notify_announcements'] = (int) $_POST['notify_announcements'];
			} else {
				$update_prefs['announcements'] = Utils::$context['alert_prefs']['announcements'];
			}

			$update_prefs['announcements'] = !empty($update_prefs['announcements']) ? 2 : 0;

			Notify::setNotifyPrefs((int) Profile::$member->id, $update_prefs);

			foreach ($update_prefs as $pref => $value) {
				Utils::$context['alert_prefs'][$pref] = $value;
			}

			$this->changeNotifications();

			Utils::$context['profile_updated'] = Lang::$txt['profile_updated_own'];
		}

		SecurityToken::create(Utils::$context['token_check'], 'post');
	}

	/**
	 * Marks all alerts as read for user.
	 */
	public function markRead()
	{
		// We do not want to output debug information here.
		Config::$db_show_debug = false;

		// We only want to output our little layer here.
		Utils::$context['template_layers'] = [];
		Utils::$context['sub_template'] = 'alerts_all_read';

		Lang::load('Alerts');

		// Now we're all set up.
		User::$me->kickIfGuest();

		if (!User::$me->is_owner) {
			ErrorHandler::fatal('no_access');
		}

		User::$me->checkSession('get');

		Alert::markAll(Profile::$member->id, true);
	}

	/**
	 * Handles alerts related to topics and posts.
	 */
	public function topics()
	{
		// Because of the way this stuff works, we want to do this ourselves.
		if (isset($_POST['edit_notify_topics']) || isset($_POST['remove_notify_topics'])) {
			User::$me->checkSession();
			SecurityToken::validate(str_replace('%u', Profile::$member->id, 'profile-nt%u'), 'post');

			$thid->changeNotifications();
			Utils::$context['profile_updated'] = Lang::$txt['profile_updated_own'];
		}

		// Now set up for the token check.
		Utils::$context['token_check'] = str_replace('%u', Profile::$member->id, 'profile-nt%u');
		SecurityToken::create(Utils::$context['token_check'], 'post');

		// Do the topic notifications.
		$list_options = [
			'id' => 'topic_notification_list',
			'width' => '100%',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['notifications_topics_none'] . '<br><br>' . Lang::$txt['notifications_topics_howto'],
			'no_items_align' => 'left',
			'base_href' => Config::$scripturl . '?action=profile;u=' . Profile::$member->id . ';area=notification;sa=topics',
			'default_sort_col' => 'last_post',
			'get_items' => [
				'function' => __CLASS__ . '::list_getTopicNotifications',
				'params' => [],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getTopicNotificationCount',
				'params' => [],
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => Lang::$txt['notifications_topics'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function ($topic) {
							$link = $topic['link'];

							if ($topic['new']) {
								$link .= ' <a href="' . $topic['new_href'] . '" class="new_posts">' . Lang::$txt['new'] . '</a>';
							}

							$link .= '<br><span class="smalltext"><em>' . Lang::$txt['in'] . ' ' . $topic['board_link'] . '</em></span>';

							return $link;
						},
					],
					'sort' => [
						'default' => 'ms.subject',
						'reverse' => 'ms.subject DESC',
					],
				],
				'started_by' => [
					'header' => [
						'value' => Lang::$txt['started_by'],
						'class' => 'lefttext',
					],
					'data' => [
						'db' => 'poster_link',
					],
					'sort' => [
						'default' => 'real_name_col',
						'reverse' => 'real_name_col DESC',
					],
				],
				'last_post' => [
					'header' => [
						'value' => Lang::$txt['last_post'],
						'class' => 'lefttext',
					],
					'data' => [
						'sprintf' => [
							'format' => '<span class="smalltext">%1$s<br>' . Lang::$txt['by'] . ' %2$s</span>',
							'params' => [
								'updated' => false,
								'poster_updated_link' => false,
							],
						],
					],
					'sort' => [
						'default' => 'ml.id_msg DESC',
						'reverse' => 'ml.id_msg',
					],
				],
				'alert_pref' => [
					'header' => [
						'value' => Lang::$txt['notify_what_how'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function ($topic) {
							$pref = $topic['notify_pref'];
							$mode = !empty($topic['unwatched']) ? 0 : ($pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1));

							return Lang::$txt['notify_topic_' . $mode];
						},
					],
				],
				'delete' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="notify_topics[]" value="%1$d">',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=profile;area=notification;sa=topics',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					'u' => Profile::$member->id,
					'sa' => Utils::$context['menu_item_selected'],
					Utils::$context['session_var'] => Utils::$context['session_id'],
				],
				'token' => Utils::$context['token_check'],
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="edit_notify_topics" value="' . Lang::$txt['notifications_update'] . '" class="button">
								<input type="submit" name="remove_notify_topics" value="' . Lang::$txt['notification_remove_pref'] . '" class="button">',
					'class' => 'floatright',
				],
			],
		];

		// Create the notification list.
		new ItemList($list_options);
	}

	/**
	 * Handles preferences related to board-level notifications.
	 */
	public function boards()
	{
		// Because of the way this stuff works, we want to do this ourselves.
		if (isset($_POST['edit_notify_boards']) || isset($_POSt['remove_notify_boards'])) {
			User::$me->checkSession();
			SecurityToken::validate(str_replace('%u', Profile::$member->id, 'profile-nt%u'), 'post');

			$this->changeNotifications();
			Utils::$context['profile_updated'] = Lang::$txt['profile_updated_own'];
		}

		// Now set up for the token check.
		Utils::$context['token_check'] = str_replace('%u', Profile::$member->id, 'profile-nt%u');
		SecurityToken::create(Utils::$context['token_check'], 'post');

		// Fine, start with the board list.
		$list_options = [
			'id' => 'board_notification_list',
			'width' => '100%',
			'no_items_label' => Lang::$txt['notifications_boards_none'] . '<br><br>' . Lang::$txt['notifications_boards_howto'],
			'no_items_align' => 'left',
			'base_href' => Config::$scripturl . '?action=profile;u=' . Profile::$member->id . ';area=notification;sa=boards',
			'default_sort_col' => 'board_name',
			'get_items' => [
				'function' => __CLASS__ . '::list_getBoardNotifications',
				'params' => [],
			],
			'columns' => [
				'board_name' => [
					'header' => [
						'value' => Lang::$txt['notifications_boards'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function ($board) {
							$link = $board['link'];

							if ($board['new']) {
								$link .= ' <a href="' . $board['href'] . '" class="new_posts">' . Lang::$txt['new'] . '</a>';
							}

							return $link;
						},
					],
					'sort' => [
						'default' => 'name',
						'reverse' => 'name DESC',
					],
				],
				'alert_pref' => [
					'header' => [
						'value' => Lang::$txt['notify_what_how'],
						'class' => 'lefttext',
					],
					'data' => [
						'function' => function ($board) {
							$pref = $board['notify_pref'];
							$mode = $pref & 0x02 ? 3 : ($pref & 0x01 ? 2 : 1);

							return Lang::$txt['notify_board_' . $mode];
						},
					],
				],
				'delete' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d">',
							'params' => [
								'id' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=profile;area=notification;sa=boards',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					'u' => Profile::$member->id,
					'sa' => Utils::$context['menu_item_selected'],
					Utils::$context['session_var'] => Utils::$context['session_id'],
				],
				'token' => Utils::$context['token_check'],
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="edit_notify_boards" value="' . Lang::$txt['notifications_update'] . '" class="button">
								<input type="submit" name="remove_notify_boards" value="' . Lang::$txt['notification_remove_pref'] . '" class="button">',
					'class' => 'floatright',
				],
			],
		];

		// Create the board notification list.
		new ItemList($list_options);
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
	 * Determines how many topics the user has requested notifications for.
	 *
	 * @return int The number of topics the user has subscribed to.
	 */
	public static function list_getTopicNotificationCount()
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_notify AS ln' . (!Config::$modSettings['postmod_active'] && User::$me->query_see_board === '1=1' ? '' : '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)') . '
			WHERE ln.id_member = {int:selected_member}' . (User::$me->query_see_topic_board === '1=1' ? '' : '
				AND {query_see_topic_board}') . (Config::$modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : ''),
			[
				'selected_member' => Profile::$member->id,
				'is_approved' => 1,
			],
		);
		list($totalNotifications) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $totalNotifications;
	}

	/**
	 * Gets information about all the topics the user has requested notifications for.
	 *
	 * @param int $start Which item to start with (for pagination purposes).
	 * @param int $items_per_page How many items to display on each page.
	 * @param string $sort A string indicating how to sort the results.
	 * @return array An array of information about the topics the user has subscribed to.
	 */
	public static function list_getTopicNotifications($start, $items_per_page, $sort)
	{
		$prefs = Notify::getNotifyPrefs(Profile::$member->id);
		$prefs = $prefs[Profile::$member->id] ?? [];

		// All the topics with notification on...
		$notification_topics = [];

		$request = Db::$db->query(
			'',
			'SELECT
				COALESCE(lt.id_msg, lmr.id_msg, -1) + 1 AS new_from, b.id_board, b.name,
				t.id_topic, ms.subject, ms.id_member, COALESCE(mem.real_name, ms.poster_name) AS real_name_col,
				ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
				COALESCE(mem2.real_name, ml.poster_name) AS last_real_name,
				lt.unwatched
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic' . (Config::$modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
			WHERE ln.id_member = {int:selected_member}
			ORDER BY {raw:sort}
			LIMIT {int:offset}, {int:items_per_page}',
			[
				'current_member' => User::$me->id,
				'is_approved' => 1,
				'selected_member' => Profile::$member->id,
				'sort' => $sort,
				'offset' => $start,
				'items_per_page' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Lang::censorText($row['subject']);

			$notification_topics[] = [
				'id' => $row['id_topic'],
				'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
				'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
				'subject' => $row['subject'],
				'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'updated' => Time::create('@' . $row['poster_time'])->format(),
				'new_href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'new_link' => '<a href="' . Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
				'board_link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'notify_pref' => $prefs['topic_notify_' . $row['id_topic']] ?? (!empty($prefs['topic_notify']) ? $prefs['topic_notify'] : 0),
				'unwatched' => $row['unwatched'],
			];
		}
		Db::$db->free_result($request);

		return $notification_topics;
	}

	/**
	 * Gets information about all the boards the user has requested notifications for.
	 *
	 * @param int $start Which item to start with (not used here).
	 * @param int $items_per_page How many items to show on each page (not used here).
	 * @param string $sort A string indicating how to sort the results.
	 * @return array An array of information about all the boards the user is subscribed to.
	 */
	public static function list_getBoardNotifications($start, $items_per_page, $sort)
	{
		$prefs = Notify::getNotifyPrefs(Profile::$member->id);
		$prefs = $prefs[Profile::$member->id] ?? [];

		$notification_boards = [];

		$request = Db::$db->query(
			'',
			'SELECT b.id_board, b.name, COALESCE(lb.id_msg, 0) AS board_read, b.id_msg_updated
			FROM {db_prefix}log_notify AS ln
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
				LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
			WHERE ln.id_member = {int:selected_member}
				AND {query_see_board}
			ORDER BY {raw:sort}',
			[
				'current_member' => User::$me->id,
				'selected_member' => Profile::$member->id,
				'sort' => $sort,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$notification_boards[] = [
				'id' => $row['id_board'],
				'name' => $row['name'],
				'href' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
				'new' => $row['board_read'] < $row['id_msg_updated'],
				'notify_pref' => $prefs['board_notify_' . $row['id_board']] ?? (!empty($prefs['board_notify']) ? $prefs['board_notify'] : 0),
			];
		}
		Db::$db->free_result($request);

		return $notification_boards;
	}

	/**
	 * Backward compatibility wrapper for the configuration sub-action.
	 *
	 * @param int $memID The ID of the member.
	 * @param bool $defaultSettings If true, we are loading default options.
	 */
	public static function alert_configuration($memID, $defaultSettings = false): void
	{
		self::load();
		Profile::load($memID);
		self::$obj->subaction = 'alerts';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the markRead sub-action.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function alert_markread($memID): void
	{
		self::load();
		Profile::load($memID);
		self::$obj->subaction = 'markread';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the topics sub-action.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function alert_notifications_topics($memID): void
	{
		self::load();
		Profile::load($memID);
		self::$obj->subaction = 'topics';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the boards sub-action.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function alert_notifications_boards($memID): void
	{
		self::load();
		Profile::load($memID);
		self::$obj->subaction = 'boards';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the changeNotifications method.
	 *
	 * @param int $memID The ID of the member.
	 */
	public static function makeNotificationChanges($memID): void
	{
		self::load();
		Profile::load($memID);
		self::$obj->changeNotifications();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		if (!isset(Profile::$member)) {
			Profile::load();
		}

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}
	}

	/**
	 * Make any notification changes that need to be made.
	 */
	protected function changeNotifications()
	{
		// Update the boards they are being notified about.
		if (isset($_POST['edit_notify_boards']) && !empty($_POST['notify_boards'])) {
			// Make sure only integers are deleted.
			foreach ($_POST['notify_boards'] as $index => $id) {
				$_POST['notify_boards'][$index] = (int) $id;
			}

			// id_board = 0 is reserved for topic notifications.
			$_POST['notify_boards'] = array_diff($_POST['notify_boards'], [0]);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_notify
				WHERE id_board IN ({array_int:board_list})
					AND id_member = {int:selected_member}',
				[
					'board_list' => $_POST['notify_boards'],
					'selected_member' => Profile::$member->id,
				],
			);
		}

		// Update the topics they are being notified about.
		if (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics'])) {
			foreach ($_POST['notify_topics'] as $index => $id) {
				$_POST['notify_topics'][$index] = (int) $id;
			}

			// Make sure there are no zeros left.
			$_POST['notify_topics'] = array_filter($_POST['notify_topics']);

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_notify
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:selected_member}',
				[
					'topic_list' => $_POST['notify_topics'],
					'selected_member' => Profile::$member->id,
				],
			);

			foreach ($_POST['notify_topics'] as $topic) {
				Notify::setNotifyPrefs(Profile::$member->id, ['topic_notify_' . $topic => 0]);
			}
		}

		// Are we removing topic preferences?
		if (isset($_POST['remove_notify_topics']) && !empty($_POST['notify_topics'])) {
			$prefs = [];

			foreach ($_POST['notify_topics'] as $topic) {
				$prefs[] = 'topic_notify_' . $topic;
			}

			Notify::deleteNotifyPrefs(Profile::$member->id, $prefs);
		}

		// Are we removing board preferences?
		if (isset($_POST['remove_notify_board']) && !empty($_POST['notify_boards'])) {
			$prefs = [];

			foreach ($_POST['notify_boards'] as $board) {
				$prefs[] = 'board_notify_' . $board;
			}

			Notify::deleteNotifyPrefs(Profile::$member->id, $prefs);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Notification::exportStatic')) {
	Notification::exportStatic();
}

?>