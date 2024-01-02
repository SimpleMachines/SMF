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

namespace SMF\Actions\Moderation;

use SMF\Actions\ActionInterface;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Menu;
use SMF\Msg;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Allows the moderator to view stuff related to warnings.
 */
class Warnings implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ViewWarnings',
			'list_getWarningCount' => 'list_getWarningCount',
			'list_getWarnings' => 'list_getWarnings',
			'list_getWarningTemplateCount' => 'list_getWarningTemplateCount',
			'list_getWarningTemplates' => 'list_getWarningTemplates',
			'ViewWarningLog' => 'ViewWarningLog',
			'ViewWarningTemplates' => 'ViewWarningTemplates',
			'ModifyWarningTemplate' => 'ModifyWarningTemplate',
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
	public string $subaction = 'log';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'log' => ['log', ['view_warning_any', 'moderate_forum']],
		'templates' => ['templates', 'issue_warning'],
		'templateedit' => ['templateEdit', 'issue_warning'],
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
		Theme::loadTemplate('ModerationCenter');
		Lang::load('Profile');

		Menu::$loaded['moderate']->tab_data = [
			'title' => Lang::$txt['mc_warnings'],
			'description' => Lang::$txt['mc_warnings_description'],
		];

		$call = method_exists($this, self::$subactions[$this->subaction][0]) ? [$this, self::$subactions[$this->subaction][0]] : Utils::getCallable(self::$subactions[$this->subaction][0]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Simply put, look at the warning log!
	 */
	public function log(): void
	{
		// Setup context as always.
		Utils::$context['page_title'] = Lang::$txt['mc_warning_log_title'];

		Lang::load('Modlog');

		// If we're coming from a search, get the variables.
		if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search'])) {
			$search_params = base64_decode(strtr($_REQUEST['params'], [' ' => '+']));
			$search_params = Utils::jsonDecode($search_params, true);
		}

		// This array houses all the valid search types.
		$searchTypes = [
			'member' => ['sql' => 'member_name_col', 'label' => Lang::$txt['profile_warning_previous_issued']],
			'recipient' => ['sql' => 'recipient_name', 'label' => Lang::$txt['mc_warnings_recipient']],
		];

		// Do the column stuff!
		$sort_types = [
			'member' => 'member_name_col',
			'recipient' => 'recipient_name',
		];

		// Setup the direction stuff...
		Utils::$context['order'] = isset($_REQUEST['sort']) && isset($sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'member';

		if (!isset($search_params['string']) || (!empty($_REQUEST['search']) && $search_params['string'] != $_REQUEST['search'])) {
			$search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
		} else {
			$search_params_string = $search_params['string'];
		}

		if (isset($_REQUEST['search_type']) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']])) {
			$search_params_type = isset($_REQUEST['search_type']) && isset($searchTypes[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($searchTypes[Utils::$context['order']]) ? Utils::$context['order'] : 'member');
		} else {
			$search_params_type = $search_params['type'];
		}

		$search_params = [
			'string' => $search_params_string,
			'type' => $search_params_type,
		];

		Utils::$context['url_start'] = '?action=moderate;area=warnings;sa=log;sort=' . Utils::$context['order'];

		// Setup the search context.
		Utils::$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(Utils::jsonEncode($search_params));

		Utils::$context['search'] = [
			'string' => $search_params['string'],
			'type' => $search_params['type'],
			'label' => $searchTypes[$search_params_type]['label'],
		];

		// This is all the information required for a watched user listing.
		$listOptions = [
			'id' => 'warning_list',
			'title' => Lang::$txt['mc_warning_log_title'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['mc_warnings_none'],
			'base_href' => Config::$scripturl . '?action=moderate;area=warnings;sa=log;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'default_sort_col' => 'time',
			'get_items' => [
				'function' => __CLASS__ . '::list_getWarnings',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getWarningCount',
			],
			// This assumes we are viewing by user.
			'columns' => [
				'issuer' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_issued'],
					],
					'data' => [
						'db' => 'issuer_link',
					],
					'sort' => [
						'default' => 'member_name_col',
						'reverse' => 'member_name_col DESC',
					],
				],
				'recipient' => [
					'header' => [
						'value' => Lang::$txt['mc_warnings_recipient'],
					],
					'data' => [
						'db' => 'recipient_link',
					],
					'sort' => [
						'default' => 'recipient_name',
						'reverse' => 'recipient_name DESC',
					],
				],
				'time' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_time'],
					],
					'data' => [
						'db' => 'time',
					],
					'sort' => [
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					],
				],
				'reason' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_reason'],
					],
					'data' => [
						'function' => function ($rowData) {
							$output = '
								<div class="floatleft">
									' . $rowData['reason'] . '
								</div>';

							if (!empty($rowData['id_notice'])) {
								$output .= '
									&nbsp;<a href="' . Config::$scripturl . '?action=moderate;area=notice;nid=' . $rowData['id_notice'] . '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=400,height=250\');return false;" target="_blank" rel="noopener" title="' . Lang::$txt['profile_warning_previous_notice'] . '"><span class="main_icons filter centericon"></span></a>';
							}

							return $output;
						},
					],
				],
				'points' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_level'],
					],
					'data' => [
						'db' => 'counter',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . Utils::$context['url_start'],
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					Utils::$context['session_var'] => Utils::$context['session_id'],
					'params' => false,
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
						' . Lang::$txt['modlog_search'] . ':
						<input type="text" name="search" size="18" value="' . Utils::htmlspecialchars(Utils::$context['search']['string']) . '">
						<input type="submit" name="is_search" value="' . Lang::$txt['modlog_go'] . '" class="button">',
					'class' => 'floatright',
				],
			],
		];

		// Create the watched user list.
		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'warning_list';
	}

	/**
	 * Load all the warning templates.
	 */
	public function templates(): void
	{
		// Submitting a new one?
		if (isset($_POST['add'])) {
			$this->ModifyWarningTemplate();

			return;
		}

		if (isset($_POST['delete']) && !empty($_POST['deltpl'])) {
			User::$me->checkSession();
			SecurityToken::validate('mod-wt');

			// Log the actions.
			$request = Db::$db->query(
				'',
				'SELECT recipient_name
				FROM {db_prefix}log_comments
				WHERE id_comment IN ({array_int:delete_ids})
					AND comment_type = {string:warntpl}
					AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
				[
					'delete_ids' => $_POST['deltpl'],
					'warntpl' => 'warntpl',
					'generic' => 0,
					'current_member' => User::$me->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Logging::logAction('delete_warn_template', ['template' => $row['recipient_name']]);
			}
			Db::$db->free_result($request);

			// Do the deletes.
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_comments
				WHERE id_comment IN ({array_int:delete_ids})
					AND comment_type = {string:warntpl}
					AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
				[
					'delete_ids' => $_POST['deltpl'],
					'warntpl' => 'warntpl',
					'generic' => 0,
					'current_member' => User::$me->id,
				],
			);
		}

		// Setup context as always.
		Utils::$context['page_title'] = Lang::$txt['mc_warning_templates_title'];

		// This is all the information required for a watched user listing.
		$listOptions = [
			'id' => 'warning_template_list',
			'title' => Lang::$txt['mc_warning_templates_title'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['mc_warning_templates_none'],
			'base_href' => Config::$scripturl . '?action=moderate;area=warnings;sa=templates;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'default_sort_col' => 'title',
			'get_items' => [
				'function' => __CLASS__ . '::list_getWarningTemplates',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getWarningTemplateCount',
			],
			// This assumes we are viewing by user.
			'columns' => [
				'title' => [
					'header' => [
						'value' => Lang::$txt['mc_warning_templates_name'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="' . Config::$scripturl . '?action=moderate;area=warnings;sa=templateedit;tid=%1$d">%2$s</a>',
							'params' => [
								'id_comment' => false,
								'title' => false,
								'body' => false,
							],
						],
					],
					'sort' => [
						'default' => 'template_title',
						'reverse' => 'template_title DESC',
					],
				],
				'creator' => [
					'header' => [
						'value' => Lang::$txt['mc_warning_templates_creator'],
					],
					'data' => [
						'db' => 'creator',
					],
					'sort' => [
						'default' => 'creator_name',
						'reverse' => 'creator_name DESC',
					],
				],
				'time' => [
					'header' => [
						'value' => Lang::$txt['mc_warning_templates_time'],
					],
					'data' => [
						'db' => 'time',
					],
					'sort' => [
						'default' => 'lc.log_time DESC',
						'reverse' => 'lc.log_time',
					],
				],
				'delete' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'style' => 'width: 4%;',
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							return '<input type="checkbox" name="deltpl[]" value="' . $rowData['id_comment'] . '">';
						},
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=moderate;area=warnings;sa=templates',
				'token' => 'mod-wt',
			],
			'additional_rows' => [
				[
					'position' => 'bottom_of_list',
					'value' => '&nbsp;<input type="submit" name="delete" value="' . Lang::$txt['mc_warning_template_delete'] . '" data-confirm="' . Lang::$txt['mc_warning_template_delete_confirm'] . '" class="button you_sure">',
				],
				[
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="add" value="' . Lang::$txt['mc_warning_template_add'] . '" class="button">',
				],
			],
		];

		// Create the watched user list.
		SecurityToken::create('mod-wt');
		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'warning_template_list';
	}

	/**
	 * Edit a warning template.
	 */
	public function templateEdit(): void
	{
		Utils::$context['id_template'] = isset($_REQUEST['tid']) ? (int) $_REQUEST['tid'] : 0;
		Utils::$context['is_edit'] = Utils::$context['id_template'];

		// Standard template things.
		Utils::$context['page_title'] = Utils::$context['is_edit'] ? Lang::$txt['mc_warning_template_modify'] : Lang::$txt['mc_warning_template_add'];
		Utils::$context['sub_template'] = 'warn_template';
		Menu::$loaded['moderate']['current_subsection'] = 'templates';

		// Defaults.
		Utils::$context['template_data'] = [
			'title' => '',
			'body' => Lang::$txt['mc_warning_template_body_default'],
			'personal' => false,
			'can_edit_personal' => true,
		];

		// If it's an edit load it.
		if (Utils::$context['is_edit']) {
			$request = Db::$db->query(
				'',
				'SELECT id_member, id_recipient, recipient_name AS template_title, body
				FROM {db_prefix}log_comments
				WHERE id_comment = {int:id}
					AND comment_type = {string:warntpl}
					AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
				[
					'id' => Utils::$context['id_template'],
					'warntpl' => 'warntpl',
					'generic' => 0,
					'current_member' => User::$me->id,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['template_data'] = [
					'title' => $row['template_title'],
					'body' => Utils::htmlspecialchars($row['body']),
					'personal' => $row['id_recipient'],
					'can_edit_personal' => $row['id_member'] == User::$me->id,
				];
			}
			Db::$db->free_result($request);
		}

		// Wait, we are saving?
		if (isset($_POST['save'])) {
			User::$me->checkSession();
			SecurityToken::validate('mod-wt');

			// Bit of cleaning!
			$_POST['template_body'] = trim($_POST['template_body']);
			$_POST['template_title'] = trim($_POST['template_title']);

			// Need something in both boxes.
			if (!empty($_POST['template_body']) && !empty($_POST['template_title'])) {
				// Safety first.
				$_POST['template_title'] = Utils::htmlspecialchars($_POST['template_title']);

				// Clean up BBC.
				Msg::preparsecode($_POST['template_body']);

				// But put line breaks back!
				$_POST['template_body'] = strtr($_POST['template_body'], ['<br>' => "\n"]);

				// Is this personal?
				$recipient_id = !empty($_POST['make_personal']) ? User::$me->id : 0;

				// If we are this far it's save time.
				if (Utils::$context['is_edit']) {
					// Simple update...
					Db::$db->query(
						'',
						'UPDATE {db_prefix}log_comments
						SET id_recipient = {int:personal}, recipient_name = {string:title}, body = {string:body}
						WHERE id_comment = {int:id}
							AND comment_type = {string:warntpl}
							AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})' .
							($recipient_id ? ' AND id_member = {int:current_member}' : ''),
						[
							'personal' => $recipient_id,
							'title' => $_POST['template_title'],
							'body' => $_POST['template_body'],
							'id' => Utils::$context['id_template'],
							'warntpl' => 'warntpl',
							'generic' => 0,
							'current_member' => User::$me->id,
						],
					);

					// If it wasn't visible and now is they've effectively added it.
					if (Utils::$context['template_data']['personal'] && !$recipient_id) {
						Logging::logAction('add_warn_template', ['template' => $_POST['template_title']]);
					}
					// Conversely if they made it personal it's a delete.
					elseif (!Utils::$context['template_data']['personal'] && $recipient_id) {
						Logging::logAction('delete_warn_template', ['template' => $_POST['template_title']]);
					}
					// Otherwise just an edit.
					else {
						Logging::logAction('modify_warn_template', ['template' => $_POST['template_title']]);
					}
				} else {
					Db::$db->insert(
						'',
						'{db_prefix}log_comments',
						[
							'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int',
							'recipient_name' => 'string-255', 'body' => 'string-65535', 'log_time' => 'int',
						],
						[
							User::$me->id, User::$me->name, 'warntpl', $recipient_id,
							$_POST['template_title'], $_POST['template_body'], time(),
						],
						['id_comment'],
					);

					Logging::logAction('add_warn_template', ['template' => $_POST['template_title']]);
				}

				// Get out of town...
				Utils::redirectexit('action=moderate;area=warnings;sa=templates');
			} else {
				Utils::$context['warning_errors'] = [];
				Utils::$context['template_data']['title'] = !empty($_POST['template_title']) ? $_POST['template_title'] : '';
				Utils::$context['template_data']['body'] = !empty($_POST['template_body']) ? $_POST['template_body'] : Lang::$txt['mc_warning_template_body_default'];
				Utils::$context['template_data']['personal'] = !empty($_POST['make_personal']);

				if (empty($_POST['template_title'])) {
					Utils::$context['warning_errors'][] = Lang::$txt['mc_warning_template_error_no_title'];
				}

				if (empty($_POST['template_body'])) {
					Utils::$context['warning_errors'][] = Lang::$txt['mc_warning_template_error_no_body'];
				}
			}
		}

		SecurityToken::create('mod-wt');
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
	 * @return int The total number of warnings that have been issued
	 */
	public static function list_getWarningCount(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_comments
			WHERE comment_type = {string:warning}',
			[
				'warning' => 'warning',
			],
		);
		list($totalWarns) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $totalWarns;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of data about warning log entries
	 */
	public static function list_getWarnings($start, $items_per_page, $sort): array
	{
		$warnings = [];

		$request = Db::$db->query(
			'',
			'SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name_col,
				COALESCE(mem2.id_member, 0) AS id_recipient, COALESCE(mem2.real_name, lc.recipient_name) AS recipient_name,
				lc.log_time, lc.body, lc.id_notice, lc.counter
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
				LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = lc.id_recipient)
			WHERE lc.comment_type = {string:warning}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			[
				'warning' => 'warning',
				'start' => $start,
				'max' => $items_per_page,
				'sort' => $sort,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$warnings[] = [
				'issuer_link' => $row['id_member'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name_col'] . '</a>') : $row['member_name_col'],
				'recipient_link' => $row['id_recipient'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_recipient'] . '">' . $row['recipient_name'] . '</a>') : $row['recipient_name'],
				'time' => Time::create('@' . $row['log_time'])->format(),
				'reason' => $row['body'],
				'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
				'id_notice' => $row['id_notice'],
			];
		}
		Db::$db->free_result($request);

		return $warnings;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @return int The total number of warning templates
	 */
	public static function list_getWarningTemplateCount(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_comments
			WHERE comment_type = {string:warntpl}
				AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})',
			[
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => User::$me->id,
			],
		);
		list($totalWarns) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $totalWarns;
	}

	/**
	 * Callback for SMF\ItemList().
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page The number of items to show per page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An arrray of info about the available warning templates
	 */
	public static function list_getWarningTemplates($start, $items_per_page, $sort): array
	{
		$templates = [];

		$request = Db::$db->query(
			'',
			'SELECT lc.id_comment, COALESCE(mem.id_member, 0) AS id_member,
				COALESCE(mem.real_name, lc.member_name) AS creator_name, recipient_name AS template_title,
				lc.log_time, lc.body
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {string:warntpl}
				AND (id_recipient = {string:generic} OR id_recipient = {int:current_member})
			ORDER BY ' . $sort . '
			LIMIT ' . $start . ', ' . $items_per_page,
			[
				'warntpl' => 'warntpl',
				'generic' => 0,
				'current_member' => User::$me->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$templates[] = [
				'id_comment' => $row['id_comment'],
				'creator' => $row['id_member'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['creator_name'] . '</a>') : $row['creator_name'],
				'time' => Time::create('@' . $row['log_time'])->format(),
				'title' => $row['template_title'],
				'body' => Utils::htmlspecialchars($row['body']),
			];
		}
		Db::$db->free_result($request);

		return $templates;
	}

	/**
	 * Backward compatibility wrapper for the log sub-action.
	 */
	public static function ViewWarningLog(): void
	{
		self::load();
		self::$obj->subaction = 'log';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the templates sub-action.
	 */
	public static function ViewWarningTemplates(): void
	{
		self::load();
		self::$obj->subaction = 'templates';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the templateedit sub-action.
	 */
	public static function ModifyWarningTemplate(): void
	{
		self::load();
		self::$obj->subaction = 'templateedit';
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
		IntegrationHook::call('integrate_warning_log_actions', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		// If the user can't do the specified sub-action, choose the first one they can.
		if (!User::$me->allowedTo(self::$subactions[$this->subaction][1])) {
			$this->subaction = '';

			foreach (self::$subactions as $sa => $sa_info) {
				if ($sa === $this->subaction) {
					continue;
				}

				if (User::$me->allowedTo(self::$subactions[$sa][1])) {
					$this->subaction = $sa;
					break;
				}
			}

			// This shouldn't happen, but just in case...
			if (empty($this->subaction)) {
				Utils::redirectexit('action=moderate;area=index');
			}
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	// code...
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Warnings::exportStatic')) {
	Warnings::exportStatic();
}

?>