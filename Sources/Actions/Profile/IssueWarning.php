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
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\ItemList;
use SMF\Lang;
use SMF\Msg;
use SMF\PersonalMessage\PM;
use SMF\Profile;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Rename here and in the exportStatic call at the end of the file.
 */
class IssueWarning implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'list_getUserWarnings' => 'list_getUserWarnings',
			'list_getUserWarningCount' => 'list_getUserWarningCount',
			'issueWarning' => 'issueWarning',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var array
	 *
	 * This stores any legitimate errors.
	 */
	public array $issueErrors = [];

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
	 * Does the job.
	 */
	public function execute(): void
	{
		// Doesn't hurt to be overly cautious.
		if (empty(Config::$modSettings['warning_enable']) || (User::$me->is_owner && !Profile::$member->warning) || !User::$me->allowedTo('issue_warning')) {
			ErrorHandler::fatalLang('no_access', false);
		}

		// Get the base (errors related) stuff done.
		Lang::load('Errors');
		Utils::$context['custom_error_title'] = Lang::$txt['profile_warning_errors_occured'];

		Utils::$context['warning_limit'] = User::$me->allowedTo('admin_forum') ? 0 : Config::$modSettings['user_limit'];

		// What are the limits we can apply?
		Utils::$context['min_allowed'] = 0;
		Utils::$context['max_allowed'] = 100;

		if (Utils::$context['warning_limit'] > 0) {
			// Make sure we cannot go outside of our limit for the day.
			$request = Db::$db->query(
				'',
				'SELECT SUM(counter)
				FROM {db_prefix}log_comments
				WHERE id_recipient = {int:selected_member}
					AND id_member = {int:current_member}
					AND comment_type = {string:warning}
					AND log_time > {int:day_time_period}',
				[
					'current_member' => User::$me->id,
					'selected_member' => Profile::$member->id,
					'day_time_period' => time() - 86400,
					'warning' => 'warning',
				],
			);
			list($current_applied) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			Utils::$context['min_allowed'] = max(0, Profile::$member->warning - $current_applied - Utils::$context['warning_limit']);

			Utils::$context['max_allowed'] = min(100, Profile::$member->warning - $current_applied + Utils::$context['warning_limit']);
		}

		// Defaults.
		Utils::$context['warning_data'] = [
			'reason' => '',
			'notify' => '',
			'notify_subject' => '',
			'notify_body' => '',
		];

		// Are we saving?
		if (isset($_POST['save'])) {
			$this->save();
		}

		if (isset($_POST['preview'])) {
			$this->preview();
		}

		if (!empty($this->issueErrors)) {
			// Fill in the suite of errors.
			Utils::$context['post_errors'] = [];

			foreach ($this->issueErrors as $error) {
				Utils::$context['post_errors'][] = Lang::$txt[$error];
			}
		}

		Utils::$context['page_title'] = Lang::$txt['profile_issue_warning'];

		// Work our the various levels.
		Utils::$context['level_effects'] = [
			0 => Lang::$txt['profile_warning_effect_none'],
			Config::$modSettings['warning_watch'] => Lang::$txt['profile_warning_effect_watch'],
			Config::$modSettings['warning_moderate'] => Lang::$txt['profile_warning_effect_moderation'],
			Config::$modSettings['warning_mute'] => Lang::$txt['profile_warning_effect_mute'],
		];

		Utils::$context['current_level'] = 0;

		foreach (Utils::$context['level_effects'] as $limit => $dummy) {
			if (Utils::$context['member']['warning'] >= $limit) {
				Utils::$context['current_level'] = $limit;
			}
		}

		$list_options = [
			'id' => 'view_warnings',
			'title' => Lang::$txt['profile_viewwarning_previous_warnings'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'no_items_label' => Lang::$txt['profile_viewwarning_no_warnings'],
			'base_href' => Config::$scripturl . '?action=profile;area=issuewarning;sa=user;u=' . Profile::$member->id,
			'default_sort_col' => 'log_time',
			'get_items' => [
				'function' => __CLASS__ . '::list_getUserWarnings',
				'params' => [],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getUserWarningCount',
				'params' => [],
			],
			'columns' => [
				'issued_by' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_issued'],
						'style' => 'width: 20%;',
					],
					'data' => [
						'function' => function ($warning) {
							return $warning['issuer']['link'];
						},
					],
					'sort' => [
						'default' => 'lc.member_name DESC',
						'reverse' => 'lc.member_name',
					],
				],
				'log_time' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_time'],
						'style' => 'width: 30%;',
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
						'function' => function ($warning) {
							$ret = '
							<div class="floatleft">
								' . $warning['reason'] . '
							</div>';

							if (!empty($warning['id_notice'])) {
								$ret .= '
							<div class="floatright">
								<a href="' . Config::$scripturl . '?action=moderate;area=notice;nid=' . $warning['id_notice'] . '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=400,height=250\');return false;" target="_blank" rel="noopener" title="' . Lang::$txt['profile_warning_previous_notice'] . '"><span class="main_icons filter centericon"></span></a>
							</div>';
							}

							return $ret;
						},
					],
				],
				'level' => [
					'header' => [
						'value' => Lang::$txt['profile_warning_previous_level'],
						'style' => 'width: 6%;',
					],
					'data' => [
						'db' => 'counter',
					],
					'sort' => [
						'default' => 'lc.counter DESC',
						'reverse' => 'lc.counter',
					],
				],
			],
		];

		// Create the list for viewing.
		new ItemList($list_options);

		// Are they warning because of a message?
		if (isset($_REQUEST['msg']) && 0 < (int) $_REQUEST['msg']) {
			$request = Db::$db->query(
				'',
				'SELECT m.subject
				FROM {db_prefix}messages AS m
				WHERE m.id_msg = {int:message}
					AND {query_see_message_board}
				LIMIT 1',
				[
					'message' => (int) $_REQUEST['msg'],
				],
			);

			if (Db::$db->num_rows($request) != 0) {
				Utils::$context['warning_for_message'] = (int) $_REQUEST['msg'];
				list(Utils::$context['warned_message_subject']) = Db::$db->fetch_row($request);
			}
			Db::$db->free_result($request);
		}

		// Didn't find the message?
		if (empty(Utils::$context['warning_for_message'])) {
			Utils::$context['warning_for_message'] = 0;
			Utils::$context['warned_message_subject'] = '';
		}

		// Any custom templates?
		Utils::$context['notification_templates'] = [];

		$request = Db::$db->query(
			'',
			'SELECT recipient_name AS template_title, body
			FROM {db_prefix}log_comments
			WHERE comment_type = {literal:warntpl}
				AND (id_recipient = {int:generic} OR id_recipient = {int:current_member})',
			[
				'generic' => 0,
				'current_member' => User::$me->id,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// If we're not warning for a message skip any that are.
			if (!Utils::$context['warning_for_message'] && strpos($row['body'], '{MESSAGE}') !== false) {
				continue;
			}

			Utils::$context['notification_templates'][] = [
				'title' => $row['template_title'],
				'body' => $row['body'],
			];
		}
		Db::$db->free_result($request);

		// Setup the "default" templates.
		foreach (['spamming', 'offence', 'insulting'] as $type) {
			Utils::$context['notification_templates'][] = [
				'title' => Lang::$txt['profile_warning_notify_title_' . $type],
				'body' => sprintf(Lang::$txt['profile_warning_notify_template_outline' . (!empty(Utils::$context['warning_for_message']) ? '_post' : '')], Lang::$txt['profile_warning_notify_for_' . $type]),
			];
		}

		// Replace all the common variables in the templates.
		foreach (Utils::$context['notification_templates'] as $k => $name) {
			Utils::$context['notification_templates'][$k]['body'] = strtr($name['body'], [
				'{MEMBER}' => Utils::htmlspecialcharsDecode(Utils::$context['member']['name']),
				'{MESSAGE}' => '[url=' . Config::$scripturl . '?msg=' . Utils::$context['warning_for_message'] . ']' . Utils::htmlspecialcharsDecode(Utils::$context['warned_message_subject']) . '[/url]',
				'{SCRIPTURL}' => Config::$scripturl,
				'{FORUMNAME}' => Config::$mbname,
				'{REGARDS}' => sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']),
			]);
		}
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
	 * Get the data about a user's warnings.
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array of information about the user's warnings
	 */
	public static function list_getUserWarnings(int $start, int $items_per_page, string $sort): array
	{
		$previous_warnings = [];

		$request = Db::$db->query(
			'',
			'SELECT COALESCE(mem.id_member, 0) AS id_member, COALESCE(mem.real_name, lc.member_name) AS member_name,
				lc.log_time, lc.body, lc.counter, lc.id_notice
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.id_recipient = {int:selected_member}
				AND lc.comment_type = {literal:warning}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:max}',
			[
				'selected_member' => Profile::$member->id,
				'sort' => $sort,
				'start' => $start,
				'max' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$previous_warnings[] = [
				'issuer' => [
					'id' => $row['id_member'],
					'link' => $row['id_member'] ? ('<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['member_name'] . '</a>') : $row['member_name'],
				],
				'time' => Time::create('@' . $row['log_time'])->format(),
				'reason' => $row['body'],
				'counter' => $row['counter'] > 0 ? '+' . $row['counter'] : $row['counter'],
				'id_notice' => $row['id_notice'],
			];
		}
		Db::$db->free_result($request);

		return $previous_warnings;
	}

	/**
	 * Get the number of warnings a user has.
	 *
	 * @return int Total number of warnings for the user.
	 */
	public static function list_getUserWarningCount(): int
	{
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}log_comments
			WHERE id_recipient = {int:selected_member}
				AND comment_type = {literal:warning}',
			[
				'selected_member' => Profile::$member->id,
			],
		);
		list($total_warnings) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $total_warnings;
	}

	/**
	 * Backward compatibility wrapper.
	 *
	 * @param int $memID The ID of the user.
	 */
	public static function issueWarning(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		self::load();

		$_REQUEST['u'] = $u;

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
		if (!isset(Profile::$member)) {
			Profile::load();
		}

		// Get all the actual settings.
		list(Config::$modSettings['warning_enable'], Config::$modSettings['user_limit']) = explode(',', Config::$modSettings['warning_settings']);
	}

	/**
	 * Saves the newly issued warning.
	 *
	 * Also logs the action and, if the relevant setting is enabled, sends a
	 * personal message to notify the warned member about it.
	 */
	protected function save(): void
	{
		// Security is good here.
		User::$me->checkSession();

		// This cannot be empty!
		$_POST['warn_reason'] = isset($_POST['warn_reason']) ? trim($_POST['warn_reason']) : '';

		if ($_POST['warn_reason'] == '' && !User::$me->is_owner) {
			$this->issueErrors[] = 'warning_no_reason';
		}

		$_POST['warn_reason'] = Utils::htmlspecialchars($_POST['warn_reason']);

		$_POST['warning_level'] = (int) $_POST['warning_level'];
		$_POST['warning_level'] = min(max($_POST['warning_level'], 0), 100);
		$_POST['warning_level'] = min(max($_POST['warning_level'], Utils::$context['min_allowed']), Utils::$context['max_allowed']);

		// Do we actually have to issue them with a PM?
		$id_notice = 0;

		if (!empty($_POST['warn_notify']) && empty($this->issueErrors)) {
			$_POST['warn_sub'] = trim($_POST['warn_sub']);
			$_POST['warn_body'] = trim($_POST['warn_body']);

			if (empty($_POST['warn_sub']) || empty($_POST['warn_body'])) {
				$this->issueErrors[] = 'warning_notify_blank';
			}
			// Send the PM?
			else {
				PM::send(
					[
						'to' => [Profile::$member->id],
						'bcc' => [],
					],
					$_POST['warn_sub'],
					$_POST['warn_body'],
					false,
					[
						'id' => 0,
						'name' => Utils::$context['forum_name_html_safe'],
						'username' => Utils::$context['forum_name_html_safe'],
					],
				);

				// Log the notice!
				$id_notice = Db::$db->insert(
					'',
					'{db_prefix}log_member_notices',
					[
						'subject' => 'string-255',
						'body' => 'string-65534',
					],
					[
						Utils::htmlspecialchars($_POST['warn_sub']),
						Utils::htmlspecialchars($_POST['warn_body']),
					],
					['id_notice'],
					1,
				);
			}
		}

		// What have we changed?
		$level_change = $_POST['warning_level'] - Profile::$member->warning;

		// No errors? Proceed! Only log if you're not the owner.
		if (empty($this->issueErrors)) {
			// Log what we've done!
			if (!User::$me->is_owner) {
				Db::$db->insert(
					'',
					'{db_prefix}log_comments',
					[
						'id_member' => 'int',
						'member_name' => 'string',
						'comment_type' => 'string',
						'id_recipient' => 'int',
						'recipient_name' => 'string-255',
						'log_time' => 'int',
						'id_notice' => 'int',
						'counter' => 'int',
						'body' => 'string-65534',
					],
					[
						User::$me->id,
						User::$me->name,
						'warning',
						Profile::$member->id,
						Profile::$member->name,
						time(),
						(int) $id_notice,
						$level_change,
						$_POST['warn_reason'],
					],
					['id_comment'],
				);
			}

			// Make the change.
			User::updateMemberData(Profile::$member->id, ['warning' => $_POST['warning_level']]);

			// Leave a lovely message.
			Utils::$context['profile_updated'] = User::$me->is_owner ? Lang::$txt['profile_updated_own'] : Lang::$txt['profile_warning_success'];
		} else {
			// Try to remember some bits.
			Utils::$context['warning_data'] = [
				'reason' => $_POST['warn_reason'],
				'notify' => !empty($_POST['warn_notify']),
				'notify_subject' => $_POST['warn_sub'] ?? '',
				'notify_body' => $_POST['warn_body'] ?? '',
			];
		}

		// Show the new improved warning level.
		Utils::$context['member']['warning'] = $_POST['warning_level'];
	}

	/**
	 * Gets a preview of the warning message.
	 */
	protected function preview(): void
	{
		$warning_body = !empty($_POST['warn_body']) ? trim(Lang::censorText($_POST['warn_body'])) : '';

		Utils::$context['preview_subject'] = !empty($_POST['warn_sub']) ? trim(Utils::htmlspecialchars($_POST['warn_sub'])) : '';

		if (empty($_POST['warn_sub']) || empty($_POST['warn_body'])) {
			$this->issueErrors[] = 'warning_notify_blank';
		}

		if (!empty($_POST['warn_body'])) {
			Msg::preparsecode($warning_body);
			$warning_body = BBCodeParser::load()->parse($warning_body);
		}

		// Try to remember some bits.
		Utils::$context['warning_data'] = [
			'reason' => $_POST['warn_reason'],
			'notify' => !empty($_POST['warn_notify']),
			'notify_subject' => $_POST['warn_sub'] ?? '',
			'notify_body' => $_POST['warn_body'] ?? '',
			'body_preview' => $warning_body,
		];
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\IssueWarning::exportStatic')) {
	IssueWarning::exportStatic();
}

?>