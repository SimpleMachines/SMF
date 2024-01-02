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
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * Handles mail configuration, as well as reviewing the mail queue.
 */
class Mail implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageMail',
			'list_getMailQueue' => 'list_getMailQueue',
			'list_getMailQueueSize' => 'list_getMailQueueSize',
			'timeSince' => 'timeSince',
			'browseMailQueue' => 'BrowseMailQueue',
			'clearMailQueue' => 'ClearMailQueue',
			'modifyMailSettings' => 'ModifyMailSettings',
			'testMailSend' => 'TestMailSend',
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
	public string $subaction = 'browse';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'browse' => 'browse',
		'clear' => 'clear',
		'settings' => 'settings',
		'test' => 'test',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Processed version of Lang::$txtBirthdayEmails.
	 * This is used internally by the settings() method.
	 */
	protected static array $processedBirthdayEmails = [];

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
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Display the mail queue...
	 */
	public function browse(): void
	{
		// First, are we deleting something from the queue?
		if (isset($_REQUEST['delete'])) {
			User::$me->checkSession();

			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}mail_queue
				WHERE id_mail IN ({array_int:mail_ids})',
				[
					'mail_ids' => $_REQUEST['delete'],
				],
			);
		}

		// How many items do we have?
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*) AS queue_size, MIN(time_sent) AS oldest
			FROM {db_prefix}mail_queue',
			[
			],
		);
		list($mailQueueSize, $mailOldest) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Utils::$context['oldest_mail'] = empty($mailOldest) ? Lang::$txt['mailqueue_oldest_not_available'] : self::timeSince(time() - $mailOldest);
		Utils::$context['mail_queue_size'] = Lang::numberFormat($mailQueueSize);

		$listOptions = [
			'id' => 'mail_queue',
			'title' => Lang::$txt['mailqueue_browse'],
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=mailqueue',
			'default_sort_col' => 'age',
			'no_items_label' => Lang::$txt['mailqueue_no_items'],
			'get_items' => [
				'function' => __CLASS__ . '::list_getMailQueue',
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getMailQueueSize',
			],
			'columns' => [
				'subject' => [
					'header' => [
						'value' => Lang::$txt['mailqueue_subject'],
					],
					'data' => [
						'function' => function ($rowData) {
							return Utils::entityStrlen($rowData['subject']) > 50 ? sprintf('%1$s...', Utils::htmlspecialchars(Utils::entitySubstr($rowData['subject'], 0, 47))) : Utils::htmlspecialchars($rowData['subject']);
						},
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'subject',
						'reverse' => 'subject DESC',
					],
				],
				'recipient' => [
					'header' => [
						'value' => Lang::$txt['mailqueue_recipient'],
					],
					'data' => [
						'sprintf' => [
							'format' => '<a href="mailto:%1$s">%1$s</a>',
							'params' => [
								'recipient' => true,
							],
						],
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'recipient',
						'reverse' => 'recipient DESC',
					],
				],
				'priority' => [
					'header' => [
						'value' => Lang::$txt['mailqueue_priority'],
					],
					'data' => [
						'function' => function ($rowData) {
							// We probably have a text label with your priority.
							$txtKey = sprintf('mq_mpriority_%1$s', $rowData['priority']);

							// But if not, revert to priority 0.
							return Lang::$txt[$txtKey] ?? Lang::$txt['mq_mpriority_1'];
						},
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'priority',
						'reverse' => 'priority DESC',
					],
				],
				'age' => [
					'header' => [
						'value' => Lang::$txt['mailqueue_age'],
					],
					'data' => [
						'function' => function ($rowData) {
							return self::timeSince(time() - $rowData['time_sent']);
						},
						'class' => 'smalltext',
					],
					'sort' => [
						'default' => 'time_sent',
						'reverse' => 'time_sent DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					],
					'data' => [
						'function' => function ($rowData) {
							return '<input type="checkbox" name="delete[]" value="' . $rowData['id_mail'] . '">';
						},
						'class' => 'smalltext',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=mailqueue',
				'include_start' => true,
				'include_sort' => true,
			],
			'additional_rows' => [
				[
					'position' => 'top_of_list',
					'value' => '<input type="submit" name="delete_redirects" value="' . Lang::$txt['quickmod_delete_selected'] . '" data-confirm="' . Lang::$txt['quickmod_confirm'] . '" class="button you_sure"><a class="button you_sure" href="' . Config::$scripturl . '?action=admin;area=mailqueue;sa=clear;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" data-confirm="' . Lang::$txt['mailqueue_clear_list_warning'] . '">' . Lang::$txt['mailqueue_clear_list'] . '</a> ',
				],
				[
					'position' => 'bottom_of_list',
					'value' => '<input type="submit" name="delete_redirects" value="' . Lang::$txt['quickmod_delete_selected'] . '" data-confirm="' . Lang::$txt['quickmod_confirm'] . '" class="button you_sure"><a class="button you_sure" href="' . Config::$scripturl . '?action=admin;area=mailqueue;sa=clear;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" data-confirm="' . Lang::$txt['mailqueue_clear_list_warning'] . '">' . Lang::$txt['mailqueue_clear_list'] . '</a> ',
				],
			],
		];

		new ItemList($listOptions);

		Theme::loadTemplate('ManageMail');
		Utils::$context['sub_template'] = 'browse';
	}

	/**
	 * Allows to view and modify the mail settings.
	 */
	public function settings(): void
	{
		$config_vars = self::getConfigVars();

		// Saving?
		if (isset($_GET['save'])) {
			// Make the SMTP password a little harder to see in a backup etc.
			if (!empty($_POST['smtp_password'][1])) {
				$_POST['smtp_password'][0] = base64_encode($_POST['smtp_password'][0]);
				$_POST['smtp_password'][1] = base64_encode($_POST['smtp_password'][1]);
			}

			User::$me->checkSession();

			// We don't want to save the subject and body previews.
			unset($config_vars['birthday_subject'], $config_vars['birthday_body']);

			IntegrationHook::call('integrate_save_mail_settings');

			ACP::saveDBSettings($config_vars);
			Utils::redirectexit('action=admin;area=mailqueue;sa=settings');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=mailqueue;save;sa=settings';
		Utils::$context['settings_title'] = Lang::$txt['mailqueue_settings'];

		ACP::prepareDBSettingContext($config_vars);

		Utils::$context['settings_insert_above'] = '
		<script>
			var bDay = {';

		$i = 0;

		foreach (self::$processedBirthdayEmails as $index => $email) {
			$is_last = ++$i == count(self::$processedBirthdayEmails);

			Utils::$context['settings_insert_above'] .= '
				' . $index . ': {
					subject: ' . Utils::JavaScriptEscape($email['subject']) . ',
					body: ' . Utils::JavaScriptEscape(nl2br($email['body'])) . '
				}' . (!$is_last ? ',' : '');
		}

		Utils::$context['settings_insert_above'] .= '
			};
			function fetch_birthday_preview()
			{
				var index = document.getElementById(\'birthday_email\').value;
				document.getElementById(\'birthday_subject\').innerHTML = bDay[index].subject;
				document.getElementById(\'birthday_body\').innerHTML = bDay[index].body;
			}
		</script>';
	}

	/**
	 * This function clears the mail queue of all emails, and at the end redirects to browse.
	 */
	public function clear(): void
	{
		User::$me->checkSession('get');

		// If we don't yet have the total to clear, find it.
		if (!isset($_GET['te'])) {
			// How many items do we have?
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*) AS queue_size
				FROM {db_prefix}mail_queue',
				[
				],
			);
			list($_GET['te']) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		} else {
			$_GET['te'] = (int) $_GET['te'];
		}

		$_GET['sent'] = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;

		// Send 50 at a time, then go for a break...
		while (\SMF\Mail::reduceQueue(50, true, true) === true) {
			// Sent another 50.
			$_GET['sent'] += 50;
			$this->pauseMailQueueClear();
		}

		$this->browse();
	}

	/**
	 * Test mail sending ability.
	 */
	public function test(): void
	{
		Lang::load('ManageMail');
		Theme::loadTemplate('ManageMail');
		Utils::$context['sub_template'] = 'mailtest';
		Utils::$context['base_url'] = Config::$scripturl . '?action=admin;area=mailqueue;sa=test';
		Utils::$context['post_url'] = Utils::$context['base_url'] . ';save';

		// Sending the test message now.
		if (isset($_GET['save'])) {
			// Send to the current user, no options.
			$to = User::$me->email;
			$subject = Utils::htmlspecialchars($_POST['subject']);
			$message = Utils::htmlspecialchars($_POST['message']);

			$result = \SMF\Mail::send($to, $subject, $message, null, null, false, 0);
			Utils::redirectexit(Utils::$context['base_url'] . ';result=' . ($result ? 'success' : 'failure'));
		}

		// The result.
		if (isset($_GET['result'])) {
			Utils::$context['result'] = ($_GET['result'] == 'success' ? 'success' : 'failure');
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
	 * Gets the configuration variables for this admin area.
	 *
	 * @return array $config_vars for the news area.
	 */
	public static function getConfigVars(): array
	{
		Lang::load('EmailTemplates');

		$body = Lang::$txtBirthdayEmails[(empty(Config::$modSettings['birthday_email']) ? 'happy_birthday' : Config::$modSettings['birthday_email']) . '_body'];
		$subject = Lang::$txtBirthdayEmails[(empty(Config::$modSettings['birthday_email']) ? 'happy_birthday' : Config::$modSettings['birthday_email']) . '_subject'];

		$emails = [];

		foreach (Lang::$txtBirthdayEmails as $key => $value) {
			$index = substr($key, 0, strrpos($key, '_'));
			$element = substr($key, strrpos($key, '_') + 1);
			self::$processedBirthdayEmails[$index][$element] = $value;
		}

		foreach (self::$processedBirthdayEmails as $index => $dummy) {
			$emails[$index] = $index;
		}

		$config_vars = [
			// Mail queue stuff, this rocks ;)
			['int', 'mail_limit', 'subtext' => Lang::$txt['zero_to_disable']],
			['int', 'mail_quantity'],
			'',

			// SMTP stuff.
			['select', 'mail_type', [Lang::$txt['mail_type_default'], 'SMTP', 'SMTP - STARTTLS']],
			['text', 'smtp_host'],
			['text', 'smtp_port'],
			['text', 'smtp_username'],
			['password', 'smtp_password'],
			'',

			['select', 'birthday_email', $emails, 'value' => ['subject' => $subject, 'body' => $body], 'javascript' => 'onchange="fetch_birthday_preview()"'],
			'birthday_subject' => ['var_message', 'birthday_subject', 'var_message' => self::$processedBirthdayEmails[empty(Config::$modSettings['birthday_email']) ? 'happy_birthday' : Config::$modSettings['birthday_email']]['subject'], 'disabled' => true, 'size' => strlen($subject) + 3],
			'birthday_body' => ['var_message', 'birthday_body', 'var_message' => nl2br($body), 'disabled' => true, 'size' => ceil(strlen($body) / 25)],
		];

		IntegrationHook::call('integrate_modify_mail_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * This function grabs the mail queue items from the database, according to the params given.
	 * Callback for $listOptions['get_items'] in $this->browse()
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $items_per_page How many items to show on each page
	 * @param string $sort A string indicating how to sort the results
	 * @return array An array with info about the mail queue items
	 */
	public static function list_getMailQueue($start, $items_per_page, $sort): array
	{
		$mails = [];

		$request = Db::$db->query(
			'',
			'SELECT
				id_mail, time_sent, recipient, priority, private, subject
			FROM {db_prefix}mail_queue
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:items_per_page}',
			[
				'start' => $start,
				'sort' => $sort,
				'items_per_page' => $items_per_page,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
			if (!empty($row['private'])) {
				$row['subject'] = Lang::$txt['personal_message'];
			} else {
				$row['subject'] = mb_decode_mimeheader($row['subject']);
			}

			$mails[] = $row;
		}
		Db::$db->free_result($request);

		return $mails;
	}

	/**
	 * Returns the total count of items in the mail queue.
	 * Callback for $listOptions['get_count'] in $this->browse
	 *
	 * @return int The total number of mail queue items
	 */
	public static function list_getMailQueueSize(): int
	{
		// How many items do we have?
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*) AS queue_size
			FROM {db_prefix}mail_queue',
			[
			],
		);
		list($mailQueueSize) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $mailQueueSize;
	}

	/**
	 * Little utility function to calculate how long ago a time was.
	 *
	 * @param int $time_diff The time difference, in seconds
	 * @return string A string indicating how many days, hours, minutes or seconds (depending on $time_diff)
	 */
	public static function timeSince($time_diff): string
	{
		if ($time_diff < 0) {
			$time_diff = 0;
		}

		// Just do a bit of an if fest...
		if ($time_diff > 86400) {
			$days = round($time_diff / 86400, 1);

			return sprintf($days == 1 ? Lang::$txt['mq_day'] : Lang::$txt['mq_days'], $time_diff / 86400);
		}

		// Hours?
		if ($time_diff > 3600) {
			$hours = round($time_diff / 3600, 1);

			return sprintf($hours == 1 ? Lang::$txt['mq_hour'] : Lang::$txt['mq_hours'], $hours);
		}

		// Minutes?
		if ($time_diff > 60) {
			$minutes = (int) ($time_diff / 60);

			return sprintf($minutes == 1 ? Lang::$txt['mq_minute'] : Lang::$txt['mq_minutes'], $minutes);
		}

		// Otherwise must be second
		return sprintf($time_diff == 1 ? Lang::$txt['mq_second'] : Lang::$txt['mq_seconds'], $time_diff);
	}

	/**
	 * Backward compatibility wrapper for the browse sub-action.
	 */
	public static function browseMailQueue(): void
	{
		self::load();
		self::$obj->subaction = 'browse';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the clear sub-action.
	 */
	public static function clearMailQueue(): void
	{
		self::load();
		self::$obj->subaction = 'clear';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the settings sub-action.
	 */
	public static function modifyMailSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::getConfigVars();
		}

		self::load();
		self::$obj->subaction = 'settings';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the test sub-action.
	 */
	public static function testMailSend(): void
	{
		self::load();
		self::$obj->subaction = 'test';
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
		// You need to be an admin to edit settings!
		User::$me->isAllowedTo('admin_forum');

		Lang::load('Help');
		Lang::load('ManageMail');

		Utils::$context['page_title'] = Lang::$txt['mailqueue_title'];
		Utils::$context['sub_template'] = 'show_settings';

		IntegrationHook::call('integrate_manage_mail', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']])) {
			$this->subaction = $_REQUEST['sa'];
		}

		Utils::$context['sub_action'] = $this->subaction;

		// Load up all the tabs...
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['mailqueue_title'],
			'help' => '',
			'description' => Lang::$txt['mailqueue_desc'],
		];
	}

	/**
	 * Used for pausing the mail queue.
	 */
	protected function pauseMailQueueClear(): void
	{
		// Try get more time...
		@set_time_limit(600);

		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}

		// Have we already used our maximum time?
		if ((time() - TIME_START) < 5) {
			return;
		}

		Utils::$context['continue_get_data'] = '?action=admin;area=mailqueue;sa=clear;te=' . $_GET['te'] . ';sent=' . $_GET['sent'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		Utils::$context['page_title'] = Lang::$txt['not_done_title'];
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_countdown'] = '2';
		Utils::$context['sub_template'] = 'not_done';

		// Keep browse selected.
		Utils::$context['selected'] = 'browse';

		// What percent through are we?
		Utils::$context['continue_percent'] = round(($_GET['sent'] / $_GET['te']) * 100, 1);

		// Never more than 100%!
		Utils::$context['continue_percent'] = min(Utils::$context['continue_percent'], 100);

		Utils::obExit();
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Mail::exportStatic')) {
	Mail::exportStatic();
}

?>