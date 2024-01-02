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
use SMF\Actions\Notify;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Editor;
use SMF\Group;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Menu;
use SMF\Msg;
use SMF\PersonalMessage\PM;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * This class manages... the news. :P
 */
class News extends ACP implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageNews',
			'list_getNews' => 'list_getNews',
			'list_getNewsTextarea' => 'list_getNewsTextarea',
			'list_getNewsPreview' => 'list_getNewsPreview',
			'list_getNewsCheckbox' => 'list_getNewsCheckbox',
			'prepareMailingForPreview' => 'prepareMailingForPreview',
			'editNews' => 'EditNews',
			'selectMailingMembers' => 'SelectMailingMembers',
			'composeMailing' => 'ComposeMailing',
			'sendMailing' => 'SendMailing',
			'modifyNewsSettings' => 'ModifyNewsSettings',
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
	public string $subaction = 'editnews';

	/**
	 * @var array
	 *
	 * List options for showing the news items.
	 *
	 * All occurrences of '{scripturl}' and '{boardurl}' in value strings will
	 * be replaced at runtime with the real values of Config::$scripturl and
	 * Config::$boardurl.
	 *
	 * All occurrences of '{txt:...}' in value strings will be replaced at
	 * runtime with Lang::$txt strings, using whatever appears between the colon
	 * and the closing brace as the key for Lang::$txt.
	 *
	 * All occurrences of '{js_escape:...}' in value strings will be replaced at
	 * runtime with escaped versions of whatever appears between the colon and
	 * the closing brace. This escaping is done using Utils::JavaScriptEscape().
	 */
	public array $list_options = [
		'id' => 'news_lists',
		'get_items' => [
			'function' => __CLASS__ . '::list_getNews',
		],
		'columns' => [
			'news' => [
				'header' => [
					'value' => '{txt:admin_edit_news}',
					'class' => 'half_table',
				],
				'data' => [
					'function' => __CLASS__ . '::list_getNewsTextarea',
					'class' => 'half_table',
				],
			],
			'preview' => [
				'header' => [
					'value' => '{txt:preview}',
					'class' => 'half_table',
				],
				'data' => [
					'function' => __CLASS__ . '::list_getNewsPreview',
					'class' => 'half_table',
				],
			],
			'check' => [
				'header' => [
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol icon',
				],
				'data' => [
					'function' => __CLASS__ . '::list_getNewsCheckbox',
					'class' => 'centercol icon',
				],
			],
		],
		'form' => [
			'href' => '{scripturl}?action=admin;area=news;sa=editnews',
			// Will be populated at runtime with session_var => session_id
			'hidden_fields' => [],
		],
		'additional_rows' => [
			[
				'position' => 'bottom_of_list',
				'value' => '
				<span id="moreNewsItems_link" class="floatleft" style="display: none;">
					<a class="button" href="javascript:void(0);" onclick="addNewsItem(); return false;">{txt:editnews_clickadd}</a>
				</span>
				<input type="submit" name="save_items" value="{txt:save}" class="button">
				<input type="submit" name="delete_selection" value="{txt:editnews_remove_selected}" data-confirm="{txt:editnews_remove_confirm}" class="button you_sure">',
			],
		],
		'javascript' => '
			document.getElementById(\'list_news_lists_last\').style.display = "none";
			document.getElementById("moreNewsItems_link").style.display = "";
			var last_preview = 0;

			$(document).ready(function () {
				$("div[id ^= \'preview_\']").each(function () {
					var preview_id = $(this).attr(\'id\').split(\'_\')[1];
					if (last_preview < preview_id)
						last_preview = preview_id;
					make_preview_btn(preview_id);
				});
			});

			function make_preview_btn (preview_id)
			{
				$("#preview_" + preview_id).addClass("button");
				$("#preview_" + preview_id).text(\'{txt:preview}\').click(function () {
					$.ajax({
						type: "POST",
						headers: {
							"X-SMF-AJAX": 1
						},
						xhrFields: {
							withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
						},
						url: "{scripturl}?action=xmlhttp;sa=previews;xml",
						data: {item: "newspreview", news: $("#data_" + preview_id).val()},
						context: document.body,
						success: function(request){
							if ($(request).find("error").text() == \'\')
								$(document).find("#box_preview_" + preview_id).html($(request).text());
							else
								$(document).find("#box_preview_" + preview_id).text(\'{txt:news_error_no_news}\');
						},
					});
				});
			}

			function addNewsItem ()
			{
				last_preview++;
				$("#list_news_lists_last").before(' .

				'{js_escape:
				<tr class="windowbg}' .

				' + (last_preview % 2 == 0 ? \'\' : \'2\') + ' .

				'{js_escape:">
					<td style="width: 50%;">
							<textarea id="data_}' .

							' + last_preview + ' .

							'{js_escape:" rows="3" cols="65" name="news[]" style="width: 95%;"></textarea>
							<br>
							<div class="floatleft" id="preview_}' .

							' + last_preview + ' .

							'{js_escape:"></div>
					</td>
					<td style="width: 45%;">
						<div id="box_preview_}' .

						' + last_preview + ' .

						'{js_escape:" style="overflow: auto; width: 100%; height: 10ex;"></div>
					</td>
					<td></td>
				</tr>}' .

				');
				make_preview_btn(last_preview);
			}',
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 *
	 * Format: 'sub-action' => array('function', 'permission')
	 */
	public static array $subactions = [
		'editnews' => ['edit', 'edit_news'],
		'mailingmembers' => ['selectMembers', 'send_mail'],
		'mailingcompose' => ['compose', 'send_mail'],
		'mailingsend' => ['send', 'send_mail'],
		'settings' => ['settings', 'admin_forum'],
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
		// Have you got the proper permissions?
		User::$me->isAllowedTo(self::$subactions[$this->subaction][1]);

		call_user_func([$this, self::$subactions[$this->subaction][0]]);
	}

	/**
	 * Let the administrator(s) edit the news items for the forum.
	 *
	 * It writes an entry into the moderation log.
	 * This function uses the edit_news administration area.
	 * Called by ?action=admin;area=news.
	 * Requires the edit_news permission.
	 * Can be accessed with ?action=admin;sa=editnews.
	 * Uses a standard list (@see SMF\ItemList())
	 */
	public function edit(): void
	{
		// The 'remove selected' button was pressed.
		if (!empty($_POST['delete_selection']) && !empty($_POST['remove'])) {
			User::$me->checkSession();

			// Store the news temporarily in this array.
			$temp_news = explode("\n", Config::$modSettings['news']);

			// Remove the items that were selected.
			foreach ($temp_news as $i => $news) {
				if (in_array($i, $_POST['remove'])) {
					unset($temp_news[$i]);
				}
			}

			// Update the database.
			Config::updateModSettings(['news' => implode("\n", $temp_news)]);

			Utils::$context['saved_successful'] = true;

			Logging::logAction('news');
		}
		// The 'Save' button was pressed.
		elseif (!empty($_POST['save_items'])) {
			User::$me->checkSession();

			foreach ($_POST['news'] as $i => $news) {
				if (trim($news) == '') {
					unset($_POST['news'][$i]);
				} else {
					$_POST['news'][$i] = Utils::htmlspecialchars($_POST['news'][$i], ENT_QUOTES);

					Msg::preparsecode($_POST['news'][$i]);
				}
			}

			// Send the new news to the database.
			Config::updateModSettings(['news' => implode("\n", $_POST['news'])]);

			Utils::$context['saved_successful'] = true;

			// Log this into the moderation log.
			Logging::logAction('news');
		}

		Utils::$context['page_title'] = Lang::$txt['admin_edit_news'];

		// Create the request list.
		new ItemList($this->list_options);

		// And go!
		Theme::loadTemplate('ManageNews');
		Utils::$context['sub_template'] = 'news_lists';
	}

	/**
	 * Allows the user to select the membergroups to send their mailing to.
	 *
	 * Called by ?action=admin;area=news;sa=mailingmembers.
	 * Requires the send_mail permission.
	 * Form is submitted to ?action=admin;area=news;mailingcompose.
	 *
	 * @uses template_email_members()
	 */
	public function selectMembers(): void
	{
		// Is there any confirm message?
		Utils::$context['newsletter_sent'] = $_SESSION['newsletter_sent'] ?? '';

		Utils::$context['page_title'] = Lang::$txt['admin_newsletters'];

		Utils::$context['sub_template'] = 'email_members';

		Utils::$context['groups'] = [];
		$postGroups = [];
		$normalGroups = [];

		// Get all the extra groups as well as Administrator and Global Moderator.
		// If we have post groups disabled then we need to give a "ungrouped members" option.
		if (empty(Config::$modSettings['permission_enable_postgroups'])) {
			$include = Group::LOAD_NORMAL;
			$exclude = [Group::GUEST, Group::MOD];
		} else {
			$include = Group::LOAD_BOTH;
			$exclude = [Group::GUEST, Group::REGULAR, Group::MOD];
		}

		foreach (Group::loadSimple($include, $exclude) as $group) {
			Utils::$context['groups'][$group->id] = $group;

			if ($group->min_posts == -1) {
				$normalGroups[$group->id] = $group->id;
			} else {
				$postGroups[$group->id] = $group->id;
			}
		}

		// Let's count the number of members in each group...
		$groups_to_count = array_keys(Utils::$context['groups']);

		// Counting all the regular members could be a performance hit on large forums,
		// so don't do that for anyone without high level permissions.
		if (!User::$me->allowedTo('manage_membergroups')) {
			$groups_to_count = array_diff($groups_to_count, [Group::REGULAR]);
		}

		Group::countMembersBatch($groups_to_count);

		Utils::$context['can_send_pm'] = User::$me->allowedTo('pm_send');

		Theme::loadJavaScriptFile('suggest.js', ['defer' => false, 'minimize' => true], 'smf_suggest');
	}

	/**
	 * Shows a form to edit a forum mailing and its recipients.
	 *
	 * Called by ?action=admin;area=news;sa=mailingcompose.
	 * Requires the send_mail permission.
	 * Form is submitted to ?action=admin;area=news;sa=mailingsend.
	 *
	 * @uses template_email_members_compose()
	 */
	public function compose(): void
	{
		// Setup the template!
		Utils::$context['page_title'] = Lang::$txt['admin_newsletters'];
		Utils::$context['sub_template'] = 'email_members_compose';

		Utils::$context['subject'] = !empty($_POST['subject']) ? $_POST['subject'] : Utils::htmlspecialchars(Utils::$context['forum_name'] . ': ' . Lang::$txt['subject']);
		Utils::$context['message'] = !empty($_POST['message']) ? $_POST['message'] : Utils::htmlspecialchars(Lang::$txt['message'] . "\n\n" . sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']) . "\n\n" . '{$board_url}');

		// Now create the editor.
		new Editor([
			'id' => 'message',
			'value' => Utils::$context['message'],
			'height' => '150px',
			'width' => '100%',
			'labels' => [
				'post_button' => Lang::$txt['sendtopic_send'],
			],
			'preview_type' => Editor::PREVIEW_XML,
			'required' => true,
		]);

		if (!empty(Utils::$context['preview'])) {
			Utils::$context['recipients']['members'] = !empty($_POST['members']) ? explode(',', $_POST['members']) : [];
			Utils::$context['recipients']['exclude_members'] = !empty($_POST['exclude_members']) ? explode(',', $_POST['exclude_members']) : [];
			Utils::$context['recipients']['groups'] = !empty($_POST['groups']) ? explode(',', $_POST['groups']) : [];
			Utils::$context['recipients']['exclude_groups'] = !empty($_POST['exclude_groups']) ? explode(',', $_POST['exclude_groups']) : [];
			Utils::$context['recipients']['emails'] = !empty($_POST['emails']) ? explode(';', $_POST['emails']) : [];
			Utils::$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
			Utils::$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
			Utils::$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
			Utils::$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';

			self::prepareMailingForPreview();

			return;
		}

		// Start by finding any members!
		$toClean = [];

		if (!empty($_POST['members'])) {
			$toClean[] = 'members';
		}

		if (!empty($_POST['exclude_members'])) {
			$toClean[] = 'exclude_members';
		}

		if (!empty($toClean)) {
			foreach ($toClean as $type) {
				// Remove the quotes.
				$_POST[$type] = strtr($_POST[$type], ['\\"' => '"']);

				preg_match_all('~"([^"]+)"~', $_POST[$type], $matches);

				$_POST[$type] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST[$type]))));

				foreach ($_POST[$type] as $index => $member) {
					if (strlen(trim($member)) > 0) {
						$_POST[$type][$index] = Utils::htmlspecialchars(Utils::strtolower(trim($member)));
					} else {
						unset($_POST[$type][$index]);
					}
				}

				// Find the members
				$_POST[$type] = implode(',', array_keys(User::find($_POST[$type])));
			}
		}

		if (isset($_POST['member_list']) && is_array($_POST['member_list'])) {
			$members = [];

			foreach ($_POST['member_list'] as $member_id) {
				$members[] = (int) $member_id;
			}

			$_POST['members'] = implode(',', $members);
		}

		if (isset($_POST['exclude_member_list']) && is_array($_POST['exclude_member_list'])) {
			$members = [];

			foreach ($_POST['exclude_member_list'] as $member_id) {
				$members[] = (int) $member_id;
			}

			$_POST['exclude_members'] = implode(',', $members);
		}

		// Clean the other vars.
		self::send(true);

		// We need a couple strings from the email template file
		Lang::load('EmailTemplates');

		// Get a list of all full banned users.  Use their Username and email to find them.  Only get the ones that can't login to turn off notification.
		$request = Db::$db->query(
			'',
			'SELECT DISTINCT mem.id_member
			FROM {db_prefix}ban_groups AS bg
				INNER JOIN {db_prefix}ban_items AS bi ON (bg.id_ban_group = bi.id_ban_group)
				INNER JOIN {db_prefix}members AS mem ON (bi.id_member = mem.id_member)
			WHERE (bg.cannot_access = {int:cannot_access} OR bg.cannot_login = {int:cannot_login})
				AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})',
			[
				'cannot_access' => 1,
				'cannot_login' => 1,
				'current_time' => time(),
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['recipients']['exclude_members'][] = $row['id_member'];
		}
		Db::$db->free_result($request);

		$condition_array = [];
		$condition_array_params = [];
		$count = 0;

		$request = Db::$db->query(
			'',
			'SELECT DISTINCT bi.email_address
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
			WHERE (bg.cannot_access = {int:cannot_access} OR bg.cannot_login = {int:cannot_login})
				AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
				AND bi.email_address != {string:blank_string}',
			[
				'cannot_access' => 1,
				'cannot_login' => 1,
				'current_time' => time(),
				'blank_string' => '',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$condition_array[] = '{string:email_' . $count . '}';
			$condition_array_params['email_' . $count++] = $row['email_address'];
		}
		Db::$db->free_result($request);

		if (!empty($condition_array)) {
			$request = Db::$db->query(
				'',
				'SELECT id_member
				FROM {db_prefix}members
				WHERE email_address IN (' . implode(', ', $condition_array) . ')',
				$condition_array_params,
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				Utils::$context['recipients']['exclude_members'][] = $row['id_member'];
			}
			Db::$db->free_result($request);
		}

		// Did they select moderators - if so add them as specific members...
		if (
			(
				!empty(Utils::$context['recipients']['groups'])
				&& in_array(3, Utils::$context['recipients']['groups'])
			)
			|| (
				!empty(Utils::$context['recipients']['exclude_groups'])
				&& in_array(3, Utils::$context['recipients']['exclude_groups'])
			)
		) {
			$request = Db::$db->query(
				'',
				'SELECT DISTINCT mem.id_member AS identifier
				FROM {db_prefix}members AS mem
					INNER JOIN {db_prefix}moderators AS mods ON (mods.id_member = mem.id_member)
				WHERE mem.is_activated = {int:is_activated}',
				[
					'is_activated' => 1,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (in_array(3, Utils::$context['recipients'])) {
					Utils::$context['recipients']['exclude_members'][] = $row['identifier'];
				} else {
					Utils::$context['recipients']['members'][] = $row['identifier'];
				}
			}
			Db::$db->free_result($request);
		}

		// For progress bar!
		Utils::$context['total_emails'] = count(Utils::$context['recipients']['emails']);

		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}members',
			[
			],
		);
		list(Utils::$context['total_members']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Clean up the arrays.
		Utils::$context['recipients']['members'] = array_unique(Utils::$context['recipients']['members']);
		Utils::$context['recipients']['exclude_members'] = array_unique(Utils::$context['recipients']['exclude_members']);
	}

	/**
	 * Handles the sending of the forum mailing in batches.
	 *
	 * Called by ?action=admin;area=news;sa=mailingsend
	 * Requires the send_mail permission.
	 * Redirects to itself when more batches need to be sent.
	 * Redirects to ?action=admin;area=news;sa=mailingmembers after everything has been sent.
	 *
	 * @uses template_email_members_send()
	 *
	 * @param bool $clean_only If set, it will only clean the variables, put them in context, then return.
	 */
	public function send($clean_only = false): void
	{
		if (isset($_POST['preview'])) {
			Utils::$context['preview'] = true;
			self::compose();

			return;
		}

		// How many to send at once? Quantity depends on whether we are queueing or not.
		// @todo Might need an interface? (used in Post.php too with different limits)
		$num_at_once = 1000;

		// If by PM's I suggest we half the above number.
		if (!empty($_POST['send_pm'])) {
			$num_at_once /= 2;
		}

		User::$me->checkSession();

		// Where are we actually to?
		Utils::$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		Utils::$context['email_force'] = !empty($_POST['email_force']) ? 1 : 0;
		Utils::$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
		Utils::$context['total_emails'] = !empty($_POST['total_emails']) ? (int) $_POST['total_emails'] : 0;
		Utils::$context['send_html'] = !empty($_POST['send_html']) ? '1' : '0';
		Utils::$context['parse_html'] = !empty($_POST['parse_html']) ? '1' : '0';

		// One can't simply nullify things around
		if (empty($_REQUEST['total_members'])) {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}members',
				[
				],
			);
			list(Utils::$context['total_members']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		} else {
			Utils::$context['total_members'] = (int) $_REQUEST['total_members'];
		}

		// Create our main context.
		Utils::$context['recipients'] = [
			'groups' => [],
			'exclude_groups' => [],
			'members' => [],
			'exclude_members' => [],
			'emails' => [],
		];

		// Have we any excluded members?
		if (!empty($_POST['exclude_members'])) {
			$members = explode(',', $_POST['exclude_members']);

			foreach ($members as $member) {
				if ($member >= Utils::$context['start']) {
					Utils::$context['recipients']['exclude_members'][] = (int) $member;
				}
			}
		}

		// What about members we *must* do?
		if (!empty($_POST['members'])) {
			$members = explode(',', $_POST['members']);

			foreach ($members as $member) {
				if ($member >= Utils::$context['start']) {
					Utils::$context['recipients']['members'][] = (int) $member;
				}
			}
		}

		// Cleaning groups is simple - although deal with both checkbox and commas.
		if (isset($_POST['groups'])) {
			if (is_array($_POST['groups'])) {
				foreach ($_POST['groups'] as $group => $dummy) {
					Utils::$context['recipients']['groups'][] = (int) $group;
				}
			} else {
				$groups = explode(',', $_POST['groups']);

				foreach ($groups as $group) {
					Utils::$context['recipients']['groups'][] = (int) $group;
				}
			}
		}

		// Same for excluded groups
		if (isset($_POST['exclude_groups'])) {
			if (is_array($_POST['exclude_groups'])) {
				foreach ($_POST['exclude_groups'] as $group => $dummy) {
					Utils::$context['recipients']['exclude_groups'][] = (int) $group;
				}
			}
			// Ignore an empty string - we don't want to exclude "Regular Members" unless it's specifically selected
			elseif ($_POST['exclude_groups'] != '') {
				$groups = explode(',', $_POST['exclude_groups']);

				foreach ($groups as $group) {
					Utils::$context['recipients']['exclude_groups'][] = (int) $group;
				}
			}
		}

		// Finally - emails!
		if (!empty($_POST['emails'])) {
			$addressed = array_unique(explode(';', strtr($_POST['emails'], ["\n" => ';', "\r" => ';', ',' => ';'])));

			foreach ($addressed as $curmem) {
				$curmem = trim($curmem);

				if ($curmem != '' && filter_var($curmem, FILTER_VALIDATE_EMAIL)) {
					Utils::$context['recipients']['emails'][$curmem] = $curmem;
				}
			}
		}

		// If we're only cleaning drop out here.
		if ($clean_only) {
			return;
		}

		// We are relying too much on writing to superglobals...
		$_POST['subject'] = !empty($_POST['subject']) ? $_POST['subject'] : '';
		$_POST['message'] = !empty($_POST['message']) ? $_POST['message'] : '';

		// Save the message and its subject in Utils::$context
		Utils::$context['subject'] = Utils::htmlspecialchars($_POST['subject'], ENT_QUOTES);
		Utils::$context['message'] = Utils::htmlspecialchars($_POST['message'], ENT_QUOTES);

		// Prepare the message for sending it as HTML
		if (!Utils::$context['send_pm'] && !empty($_POST['send_html'])) {
			// Prepare the message for HTML.
			if (!empty($_POST['parse_html'])) {
				$_POST['message'] = str_replace(["\n", '  '], ['<br>' . "\n", '&nbsp; '], $_POST['message']);
			}

			// This is here to prevent spam filters from tagging this as spam.
			if (preg_match('~\\<html~i', $_POST['message']) == 0) {
				if (preg_match('~\\<body~i', $_POST['message']) == 0) {
					$_POST['message'] = '<html><head><title>' . $_POST['subject'] . '</title></head>' . "\n" . '<body>' . $_POST['message'] . '</body></html>';
				} else {
					$_POST['message'] = '<html>' . $_POST['message'] . '</html>';
				}
			}
		}

		if (empty($_POST['message']) || empty($_POST['subject'])) {
			Utils::$context['preview'] = true;
			self::compose();

			return;
		}

		// Include an unsubscribe link if necessary.
		if (!Utils::$context['send_pm']) {
			$include_unsubscribe = true;

			if (strpos($_POST['message'], '{$member.unsubscribe}') === false) {
				$_POST['message'] .= "\n\n" . '{$member.unsubscribe}';
			}
		}

		// Use the default time format.
		User::$me->time_format = Config::$modSettings['time_format'];

		$variables = [
			'{$board_url}',
			'{$current_time}',
			'{$latest_member.link}',
			'{$latest_member.id}',
			'{$latest_member.name}',
		];

		// We might need this in a bit
		$cleanLatestMember = empty($_POST['send_html']) || Utils::$context['send_pm'] ? Utils::htmlspecialcharsDecode(Config::$modSettings['latestRealName']) : Config::$modSettings['latestRealName'];

		// Replace in all the standard things.
		$_POST['message'] = str_replace(
			$variables,
			[
				!empty($_POST['send_html']) ? '<a href="' . Config::$scripturl . '">' . Config::$scripturl . '</a>' : Config::$scripturl,
				Time::create('now')->format(null, false),
				!empty($_POST['send_html']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . Config::$modSettings['latestMember'] . '">' . $cleanLatestMember . '</a>' : (Utils::$context['send_pm'] ? '[url=' . Config::$scripturl . '?action=profile;u=' . Config::$modSettings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : Config::$scripturl . '?action=profile;u=' . Config::$modSettings['latestMember']),
				Config::$modSettings['latestMember'],
				$cleanLatestMember,
			],
			$_POST['message'],
		);

		$_POST['subject'] = str_replace(
			$variables,
			[
				Config::$scripturl,
				Time::create('now')->format(null, false),
				Config::$modSettings['latestRealName'],
				Config::$modSettings['latestMember'],
				Config::$modSettings['latestRealName'],
			],
			$_POST['subject'],
		);

		$from_member = [
			'{$member.email}',
			'{$member.link}',
			'{$member.id}',
			'{$member.name}',
			'{$member.unsubscribe}',
		];

		// If we still have emails, do them first!
		$i = 0;

		foreach (Utils::$context['recipients']['emails'] as $k => $email) {
			// Done as many as we can?
			if ($i >= $num_at_once) {
				break;
			}

			// Don't sent it twice!
			unset(Utils::$context['recipients']['emails'][$k]);

			// Dammit - can't PM emails!
			if (Utils::$context['send_pm']) {
				continue;
			}

			// Non-members can't unsubscribe via the automated system.
			$unsubscribe_link = sprintf(Lang::$txt['unsubscribe_announcements_manual'], empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']);

			$to_member = [
				$email,
				!empty($_POST['send_html']) ? '<a href="mailto:' . $email . '">' . $email . '</a>' : $email,
				'??',
				$email,
				$unsubscribe_link,
			];

			Mail::send(
				$email,
				str_replace($from_member, $to_member, $_POST['subject']),
				str_replace($from_member, $to_member, $_POST['message']),
				null,
				'news',
				!empty($_POST['send_html']),
				5,
			);

			// Done another...
			$i++;
		}

		if ($i < $num_at_once) {
			// Need to build quite a query!
			$sendQuery = '(';
			$sendParams = [];

			if (!empty(Utils::$context['recipients']['groups'])) {
				// Take the long route...
				$queryBuild = [];

				foreach (Utils::$context['recipients']['groups'] as $group) {
					$sendParams['group_' . $group] = $group;
					$queryBuild[] = 'mem.id_group = {int:group_' . $group . '}';

					if (!empty($group)) {
						$queryBuild[] = 'FIND_IN_SET({int:group_' . $group . '}, mem.additional_groups) != 0';

						$queryBuild[] = 'mem.id_post_group = {int:group_' . $group . '}';
					}
				}

				if (!empty($queryBuild)) {
					$sendQuery .= implode(' OR ', $queryBuild);
				}
			}

			if (!empty(Utils::$context['recipients']['members'])) {
				$sendQuery .= ($sendQuery == '(' ? '' : ' OR ') . 'mem.id_member IN ({array_int:members})';

				$sendParams['members'] = Utils::$context['recipients']['members'];
			}

			$sendQuery .= ')';

			// If we've not got a query then we must be done!
			if ($sendQuery == '()') {
				// Set a confirmation message.
				$_SESSION['newsletter_sent'] = 'queue_done';
				Utils::redirectexit('action=admin;area=news;sa=mailingmembers');
			}

			// Anything to exclude?
			if (!empty(Utils::$context['recipients']['exclude_groups']) && in_array(0, Utils::$context['recipients']['exclude_groups'])) {
				$sendQuery .= ' AND mem.id_group != {int:regular_group}';
			}

			if (!empty(Utils::$context['recipients']['exclude_members'])) {
				$sendQuery .= ' AND mem.id_member NOT IN ({array_int:exclude_members})';
				$sendParams['exclude_members'] = Utils::$context['recipients']['exclude_members'];
			}

			// Get the smelly people - note we respect the id_member range as it gives us a quicker query.
			$rows = [];

			$result = Db::$db->query(
				'',
				'SELECT mem.id_member, mem.email_address, mem.real_name, mem.id_group, mem.additional_groups, mem.id_post_group
				FROM {db_prefix}members AS mem
				WHERE ' . $sendQuery . '
					AND mem.is_activated = {int:is_activated}
				ORDER BY mem.id_member ASC
				LIMIT {int:start}, {int:atonce}',
				array_merge($sendParams, [
					'start' => Utils::$context['start'],
					'atonce' => $num_at_once,
					'regular_group' => 0,
					'is_activated' => 1,
				]),
			);

			while ($row = Db::$db->fetch_assoc($result)) {
				$rows[$row['id_member']] = $row;
			}
			Db::$db->free_result($result);

			// Load their alert preferences
			$prefs = Notify::getNotifyPrefs(array_keys($rows), 'announcements', true);

			foreach ($rows as $row) {
				// Force them to have it?
				if (empty(Utils::$context['email_force']) && empty($prefs[$row['id_member']]['announcements'])) {
					continue;
				}

				// What groups are we looking at here?
				if (empty($row['additional_groups'])) {
					$groups = [$row['id_group'], $row['id_post_group']];
				} else {
					$groups = array_merge(
						[$row['id_group'], $row['id_post_group']],
						explode(',', $row['additional_groups']),
					);
				}

				// Excluded groups?
				if (array_intersect($groups, Utils::$context['recipients']['exclude_groups'])) {
					continue;
				}

				// We might need this
				$cleanMemberName = empty($_POST['send_html']) || Utils::$context['send_pm'] ? Utils::htmlspecialcharsDecode($row['real_name']) : $row['real_name'];

				if (!empty($include_unsubscribe)) {
					$token = Notify::createUnsubscribeToken($row['id_member'], $row['email_address'], 'announcements');

					$unsubscribe_link = sprintf(Lang::$txt['unsubscribe_announcements_' . (!empty($_POST['send_html']) ? 'html' : 'plain')], Config::$scripturl . '?action=notifyannouncements;u=' . $row['id_member'] . ';token=' . $token);
				} else {
					$unsubscribe_link = '';
				}

				// Replace the member-dependant variables
				$message = str_replace(
					$from_member,
					[
						$row['email_address'],
						!empty($_POST['send_html']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $cleanMemberName . '</a>' : (Utils::$context['send_pm'] ? '[url=' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . ']' . $cleanMemberName . '[/url]' : Config::$scripturl . '?action=profile;u=' . $row['id_member']),
						$row['id_member'],
						$cleanMemberName,
						$unsubscribe_link,
					],
					$_POST['message'],
				);

				$subject = str_replace(
					$from_member,
					[
						$row['email_address'],
						$row['real_name'],
						$row['id_member'],
						$row['real_name'],
					],
					$_POST['subject'],
				);

				// Send the actual email - or a PM!
				if (!Utils::$context['send_pm']) {
					Mail::send(
						$row['email_address'],
						$subject,
						$message,
						null,
						'news',
						!empty($_POST['send_html']),
						5,
					);
				} else {
					PM::send(
						['to' => [$row['id_member']],
							'bcc' => []],
						$subject,
						$message,
					);
				}
			}
		}

		Utils::$context['start'] = Utils::$context['start'] + $num_at_once;

		if (empty(Utils::$context['recipients']['emails']) && (Utils::$context['start'] >= Utils::$context['total_members'])) {
			// Log this into the admin log.
			Logging::logAction('newsletter', [], 'admin');
			$_SESSION['newsletter_sent'] = 'queue_done';
			Utils::redirectexit('action=admin;area=news;sa=mailingmembers');
		}

		// Working out progress is a black art of sorts.
		$percentEmails = Utils::$context['total_emails'] == 0 ? 0 : ((count(Utils::$context['recipients']['emails']) / Utils::$context['total_emails']) * (Utils::$context['total_emails'] / (Utils::$context['total_emails'] + Utils::$context['total_members'])));

		$percentMembers = (Utils::$context['start'] / Utils::$context['total_members']) * (Utils::$context['total_members'] / (Utils::$context['total_emails'] + Utils::$context['total_members']));

		Utils::$context['percentage_done'] = round(($percentEmails + $percentMembers) * 100, 2);

		Utils::$context['page_title'] = Lang::$txt['admin_newsletters'];
		Utils::$context['sub_template'] = 'email_members_send';
	}

	/**
	 * Set general news and newsletter settings and permissions.
	 *
	 * Called by ?action=admin;area=news;sa=settings.
	 * Requires the forum_admin permission.
	 *
	 * @uses template_show_settings()
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		Utils::$context['page_title'] = Lang::$txt['admin_edit_news'] . ' - ' . Lang::$txt['settings'];
		Utils::$context['sub_template'] = 'show_settings';

		// Wrap it all up nice and warm...
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=news;save;sa=settings';

		// Add some javascript at the bottom...
		Theme::addInlineJavaScript("\n\t" . 'document.getElementById("xmlnews_maxlen").disabled = !document.getElementById("xmlnews_enable").checked;', true);

		// Saving the settings?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			IntegrationHook::call('integrate_save_news_settings');

			self::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=news;sa=settings');
		}

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		self::prepareDBSettingContext($config_vars);
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
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the news area.
	 */
	public static function getConfigVars(): array
	{
		$config_vars = [
			['title', 'settings'],

			// Inline permissions.
			['permissions', 'edit_news', 'help' => ''],
			['permissions', 'send_mail'],
			'',

			// Just the remaining settings.
			['check', 'xmlnews_enable', 'onclick' => 'document.getElementById(\'xmlnews_maxlen\').disabled = !this.checked;'],
			['int', 'xmlnews_maxlen', 'subtext' => Lang::$txt['xmlnews_maxlen_note'], 10],
			['check', 'xmlnews_attachments', 'subtext' => Lang::$txt['xmlnews_attachments_note']],
		];

		IntegrationHook::call('integrate_modify_news_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Prepares an array of the forum news items for display in the template
	 *
	 * @return array An array of information about the news items
	 */
	public static function list_getNews(): array
	{
		$admin_current_news = [];

		// Ready the current news.
		foreach (explode("\n", Config::$modSettings['news']) as $id => $line) {
			$admin_current_news[$id] = [
				'id' => $id,
				'unparsed' => Msg::un_preparsecode($line),
				'parsed' => preg_replace('~<([/]?)form[^>]*?[>]*>~i', '<em class="smalltext">&lt;$1form&gt;</em>', BBCodeParser::load()->parse($line)),
			];
		}

		$admin_current_news['last'] = [
			'id' => 'last',
			'unparsed' => '<div id="moreNewsItems"></div>
			<noscript><textarea rows="3" cols="65" name="news[]" style="width: 85%;"></textarea></noscript>',
			'parsed' => '<div id="moreNewsItems_preview"></div>',
		];

		return $admin_current_news;
	}

	/**
	 * Callback to prepare HTML for the input fields in the news editing form.
	 *
	 * @param $news Info about a news item.
	 * @return string HTML string to show in the form.
	 */
	public static function list_getNewsTextarea($news): string
	{
		return !is_numeric($news['id']) ? $news['unparsed'] : '
			<textarea id="data_' . $news['id'] . '" rows="3" cols="50" name="news[]" class="padding block">' . $news['unparsed'] . '</textarea>
			<div class="floatleft" id="preview_' . $news['id'] . '"></div>';
	}

	/**
	 * Callback to prepare HTML for the previews in the news editing form.
	 *
	 * @param $news Info about a news item.
	 * @return string HTML string to show in the form.
	 */
	public static function list_getNewsPreview($news): string
	{
		return '<div id="box_preview_' . $news['id'] . '" style="overflow: auto; width: 100%; height: 10ex;">' . $news['parsed'] . '</div>';
	}

	/**
	 * Callback to prepare HTML for the checkboxes in the news editing form.
	 *
	 * @param $news Info about a news item.
	 * @return string HTML string to show in the form.
	 */
	public static function list_getNewsCheckbox($news): string
	{
		return !is_numeric($news['id']) ? '' : '<input type="checkbox" name="remove[]" value="' . $news['id'] . '">';
	}

	/**
	 * Prepare subject and message of an email for the preview box
	 * Used in ComposeMailing and RetrievePreview (XmlHttp.php)
	 */
	public static function prepareMailingForPreview(): void
	{
		Lang::load('Errors');

		$processing = ['preview_subject' => 'subject', 'preview_message' => 'message'];

		// Use the default time format.
		User::$me->time_format = Config::$modSettings['time_format'];

		$variables = [
			'{$board_url}',
			'{$current_time}',
			'{$latest_member.link}',
			'{$latest_member.id}',
			'{$latest_member.name}',
		];

		// We might need this in a bit
		$cleanLatestMember = empty(Utils::$context['send_html']) || Utils::$context['send_pm'] ? Utils::htmlspecialcharsDecode(Config::$modSettings['latestRealName']) : Config::$modSettings['latestRealName'];

		foreach ($processing as $key => $post) {
			Utils::$context[$key] = !empty($_REQUEST[$post]) ? $_REQUEST[$post] : '';

			if (empty(Utils::$context[$key]) && empty($_REQUEST['xml'])) {
				Utils::$context['post_error']['messages'][] = Lang::$txt['error_no_' . $post];
			} elseif (!empty($_REQUEST['xml'])) {
				continue;
			}

			Msg::preparsecode(Utils::$context[$key]);

			if (!empty(Utils::$context['send_html'])) {
				$enablePostHTML = Config::$modSettings['enablePostHTML'];
				Config::$modSettings['enablePostHTML'] = Utils::$context['send_html'];
				Utils::$context[$key] = BBCodeParser::load()->parse(Utils::$context[$key]);
				Config::$modSettings['enablePostHTML'] = $enablePostHTML;
			}

			// Replace in all the standard things.
			Utils::$context[$key] = str_replace(
				$variables,
				[
					!empty(Utils::$context['send_html']) ? '<a href="' . Config::$scripturl . '">' . Config::$scripturl . '</a>' : Config::$scripturl,
					Time::create('now')->format(null, false),
					!empty(Utils::$context['send_html']) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . Config::$modSettings['latestMember'] . '">' . $cleanLatestMember . '</a>' : (Utils::$context['send_pm'] ? '[url=' . Config::$scripturl . '?action=profile;u=' . Config::$modSettings['latestMember'] . ']' . $cleanLatestMember . '[/url]' : $cleanLatestMember),
					Config::$modSettings['latestMember'],
					$cleanLatestMember,
				],
				Utils::$context[$key],
			);
		}
	}

	/**
	 * Backward compatibility wrapper for the edit sub-action.
	 */
	public static function editNews(): void
	{
		self::load();
		self::$obj->subaction = 'edit';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the mailingmembers sub-action.
	 */
	public static function selectMailingMembers(): void
	{
		self::load();
		self::$obj->subaction = 'mailingmembers';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the mailingcompose sub-action.
	 */
	public static function composeMailing(): void
	{
		self::load();
		self::$obj->subaction = 'mailingcompose';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the mailingsend sub-action.
	 */
	public static function sendMailing(): void
	{
		self::load();
		self::$obj->subaction = 'mailingsend';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function modifyNewsSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = 'settings';
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
		Theme::loadTemplate('ManageNews');

		// Create the tabs for the template.
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['news_title'],
			'help' => 'edit_news',
			'description' => Lang::$txt['admin_news_desc'],
			'tabs' => [
				'editnews' => [
				],
				'mailingmembers' => [
					'description' => Lang::$txt['news_mailing_desc'],
				],
				'settings' => [
					'description' => Lang::$txt['news_settings_desc'],
				],
			],
		];

		IntegrationHook::call('integrate_manage_news', [&self::$subactions]);

		// Default to sub action 'main' or 'settings' depending on permissions.
		$this->subaction = isset($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (User::$me->allowedTo('edit_news') ? 'editnews' : (User::$me->allowedTo('send_mail') ? 'mailingmembers' : 'settings'));

		// Force the right area...
		if (substr($this->subaction, 0, 7) == 'mailing') {
			Menu::$loaded['admin']['current_subsection'] = 'mailingmembers';
		}

		// Insert dynamic values into the list options.
		$this->setListOptions();
	}

	/**
	 * Sets dynamic values in $this->list_options.
	 */
	protected function setListOptions(): void
	{
		// Finalize various string values.
		self::setDynamicStrings($this->list_options);

		// Add session info to the form's hidden fields.
		$this->list_options['form']['hidden_fields'][Utils::$context['session_var']] = Utils::$context['session_id'];
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Sets any dynamic string values in the passed array.
	 */
	protected static function setDynamicStrings(array &$data): void
	{
		array_walk_recursive(
			$data,
			function (&$value, $key) {
				$value = preg_replace_callback(
					'/{(scripturl|boardurl|txt:|js_escape:)([^}]*)}/',
					function ($matches) {
						switch ($matches[1]) {
							case 'scripturl':
								$new_value = Config::$scripturl;
								break;

							case 'boardurl':
								$new_value = Config::$boardurl;
								break;

							case 'js_escape:':
								$new_value = Utils::JavaScriptEscape($matches[2]);
								break;

							default:
								$new_value = Lang::$txt[$matches[2]] ?? $matches[0];
								break;
						}

						return $new_value;
					},
					$value,
				);
			},
		);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\News::exportStatic')) {
	News::exportStatic();
}

?>