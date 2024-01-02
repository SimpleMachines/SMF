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

namespace SMF\Actions;

use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * This abstract class contains the main functionality to toggle email
 * notification preferences for topics, boards and announcements.
 *
 * It is extended by concrete classes that deal with each of those.
 *
 * Mods can add support for more notification types by extending this class.
 */
abstract class Notify
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'getNotifyPrefs' => 'getNotifyPrefs',
			'setNotifyPrefs' => 'setNotifyPrefs',
			'deleteNotifyPrefs' => 'deleteNotifyPrefs',
			'getMemberWithToken' => 'getMemberWithToken',
			'createUnsubscribeToken' => 'createUnsubscribeToken',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	// Pref refers to the value that will be saved to user_alerts_prefs table.
	public const PREF_NONE = 0;
	public const PREF_ALERT = 1;
	public const PREF_EMAIL = 2;
	public const PREF_BOTH = 3;

	// Mode refers to the input submitted by $_GET or $_POST.
	// Unfortunately, mode != pref.
	public const MODE_NO_EMAIL = -2;
	public const MODE_NO_ALERT = -1;
	public const MODE_IGNORE = 0;
	public const MODE_NONE = 1;
	public const MODE_ALERT = 2;
	public const MODE_BOTH = 3;

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The notification type that this action handles.
	 */
	public string $type;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * ID of the topic or board.
	 */
	protected int $id;

	/**
	 * @var int
	 *
	 * Requested notification mode.
	 */
	protected int $mode;

	/**
	 * @var int
	 *
	 * Preference value to save to the table.
	 */
	protected int $alert_pref;

	/**
	 * @var string
	 *
	 * The unsubscribe token that the user supplied, if any.
	 */
	protected string $token;

	/**
	 * @var array
	 *
	 * Information about the user whose notifications preferences are changing.
	 * Will include ID number and email address.
	 */
	protected array $member_info;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$this->setMemberInfo();
		$this->setId();
		$this->setMode();

		if (!isset($this->mode)) {
			return;
		}

		// We don't tolerate imposters around here.
		if (empty($this->token)) {
			User::$me->checkSession('get');
		}

		$this->changePref();

		// AJAX call.
		if (isset($_GET['xml'])) {
			$this->prepareAjaxResponse();
		}
		// Nothing to redirect to or they got here via an unsubscribe link,
		// so just show a confirmation message.
		elseif (!isset($this->id) || !empty($this->token)) {
			$this->showConfirmation();
		}
		// Send them back to wherever they came from.
		else {
			Utils::redirectexit($this->type . '=' . $this->id . '.' . ($_REQUEST['start'] ?? 0));
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Fetches the list of preferences (or a single/subset of preferences) for
	 * notifications for one or more users.
	 *
	 * @param int|array $members A user id or an array of (integer) user ids to load preferences for
	 * @param string|array $prefs An empty string to load all preferences, or a string (or array) of preference name(s) to load
	 * @param bool $process_default Whether to apply the default values to the members' values or not.
	 * @return array An array of user ids => array (pref name -> value), with user id 0 representing the defaults
	 */
	public static function getNotifyPrefs($members, $prefs = '', $process_default = false)
	{
		// We want this as an array whether it is or not.
		$members = array_map('intval', (array) $members);

		if (!empty($prefs)) {
			$prefs = is_array($prefs) ? $prefs : (array) $prefs;
		}

		$result = [];

		// We want to now load the default, which is stored with a member id of 0.
		$members[] = 0;

		$request = Db::$db->query(
			'',
			'SELECT id_member, alert_pref, alert_value
			FROM {db_prefix}user_alerts_prefs
			WHERE id_member IN ({array_int:members})' . (!empty($prefs) ? '
				AND alert_pref IN ({array_string:prefs})' : ''),
			[
				'members' => $members,
				'prefs' => $prefs,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$result[$row['id_member']][$row['alert_pref']] = $row['alert_value'];
		}

		// We may want to keep the default values separate from a given user's. Or we might not.
		if ($process_default && isset($result[0])) {
			foreach ($members as $member) {
				if (isset($result[$member])) {
					$result[$member] += $result[0];
				} else {
					$result[$member] = $result[0];
				}
			}

			unset($result[0]);
		}

		return $result;
	}

	/**
	 * Sets the list of preferences for a single user.
	 *
	 * @param int $memID The user whose preferences you are setting
	 * @param array $prefs An array key of pref -> value
	 */
	public static function setNotifyPrefs($memID, $prefs = [])
	{
		if (empty($prefs) || !is_int($memID)) {
			return;
		}

		$update_rows = [];

		foreach ($prefs as $k => $v) {
			$update_rows[] = [$memID, $k, min(max((int) $v, -128), 127)];
		}

		Db::$db->insert(
			'replace',
			'{db_prefix}user_alerts_prefs',
			['id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'int'],
			$update_rows,
			['id_member', 'alert_pref'],
		);
	}

	/**
	 * Deletes notification preference.
	 *
	 * @param int $memID The user whose preference you're setting
	 * @param array $prefs The preferences to delete
	 */
	public static function deleteNotifyPrefs($memID, array $prefs)
	{
		if (empty($prefs) || empty($memID)) {
			return;
		}

		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}user_alerts_prefs
			WHERE id_member = {int:member}
				AND alert_pref IN ({array_string:prefs})',
			[
				'member' => $memID,
				'prefs' => $prefs,
			],
		);
	}

	/**
	 * Verifies a member's unsubscribe token, then returns some member info.
	 *
	 * @param string $type The type of notification the token is for (e.g. 'board', 'topic', etc.)
	 * @return array The id and email address of the specified member
	 */
	public static function getMemberWithToken($type)
	{
		// Keep it sanitary, folks
		$id_member = !empty($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0;

		// We can't do anything without these
		if (empty($id_member) || empty($_REQUEST['token'])) {
			ErrorHandler::fatalLang('unsubscribe_invalid', false);
		}

		// Get the user info we need
		$request = Db::$db->query(
			'',
			'SELECT id_member AS id, email_address AS email
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			[
				'id_member' => $id_member,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('unsubscribe_invalid', false);
		}
		$this->member_info = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		// What token are we expecting?
		$expected_token = Notify::createUnsubscribeToken($this->member_info['id'], $this->member_info['email'], $type, in_array($type, ['board', 'topic']) && !empty($$type) ? $$type : 0);

		// Don't do anything if the token they gave is wrong
		if ($_REQUEST['token'] !== $expected_token) {
			ErrorHandler::fatalLang('unsubscribe_invalid', false);
		}

		// At this point, we know we have a legitimate unsubscribe request
		return $this->member_info;
	}

	/**
	 * Builds an unsubscribe token.
	 *
	 * @param int $memID The id of the member that this token is for
	 * @param string $email The member's email address
	 * @param string $type The type of notification the token is for (e.g. 'board', 'topic', etc.)
	 * @param int $itemID The id of the notification item, if applicable.
	 * @return string The unsubscribe token
	 */
	public static function createUnsubscribeToken($memID, $email, $type = '', $itemID = 0)
	{
		$token_items = implode(' ', [$memID, $email, $type, $itemID]);

		// When the message is public and the key is secret, an HMAC is the appropriate tool.
		$token = hash_hmac('sha256', $token_items, Config::getAuthSecret(), true);

		// When using an HMAC, 80 bits (10 bytes) is plenty for security.
		$token = substr($token, 0, 10);

		// Use base64 (with URL-friendly characters) to make the token shorter.
		return strtr(base64_encode($token), ['+' => '_', '/' => '-', '=' => '']);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Sets $this->member_info with info about the member in question.
	 */
	protected function setMemberInfo()
	{
		if (isset($_REQUEST['u'], $_REQUEST['token'])) {
			$this->member_info = self::getMemberWithToken($this->type);
			$this->token = $_REQUEST['token'];
		}
		// No token, so try with the current user.
		else {
			// Permissions are an important part of anything ;).
			User::$me->kickIfGuest();
			$this->member_info = (array) User::$me;
		}
	}

	/**
	 * For board and topic, make sure we have the necessary ID.
	 */
	abstract protected function setId();

	/**
	 * Converts $_GET['sa'] to $_GET['mode'].
	 *
	 * sa=on/off is used for email subscribe/unsubscribe links.
	 */
	abstract protected function saToMode();

	/**
	 * Sets $this->mode.
	 */
	protected function setMode()
	{
		$this->saToMode();

		// What do we do?  Better ask if they didn't say..
		if (!isset($_GET['mode']) && !isset($_GET['xml'])) {
			$this->ask();
		}

		if (isset($_GET['mode'])) {
			$this->mode = (int) $_GET['mode'];
		}
	}

	/**
	 *
	 */
	protected function ask()
	{
		Theme::loadTemplate('Notify');
		Utils::$context['page_title'] = Lang::$txt['notification'];

		if ($this->member_info['id'] !== User::$me->id) {
			Utils::$context['notify_info'] = [
				'u' => $this->member_info['id'],
				'token' => $_REQUEST['token'],
			];
		}

		$this->askTemplateData();

		Utils::obExit();
	}

	/**
	 * Sets any additional data needed for the ask template.
	 */
	abstract protected function askTemplateData();

	/**
	 * Updates the notification preference in the database.
	 */
	abstract protected function changePref();

	/**
	 * Sets $this->alert_pref.
	 */
	protected function setAlertPref()
	{
		switch ($this->mode) {
			case self::MODE_IGNORE:
			case self::MODE_NONE:
				$this->alert_pref = self::PREF_NONE;
				break;

			case self::MODE_ALERT:
				$this->alert_pref = self::PREF_ALERT;
				break;

			case self::MODE_BOTH:
				$this->alert_pref = self::PREF_BOTH;
				break;

			// self::MODE_NO_EMAIL is used to turn off email notifications
			// while leaving the alert preference unchanged.
			case self::MODE_NO_EMAIL:
				// Use bitwise operator to turn off the email part of the setting.
				$this->alert_pref = self::getNotifyPrefs($this->member_info['id'], [$this->type . '_notify_' . $this->id], true) & self::PREF_ALERT;
				break;
		}
	}

	/**
	 * Updates notification preferences for the board or topic.
	 */
	protected function changeBoardTopicPref()
	{
		self::setNotifyPrefs((int) $this->member_info['id'], [$this->type . '_notify_' . $this->id => $this->alert_pref]);

		if ($this->alert_pref > self::PREF_NONE) {
			$id_board = $this->type === 'board' ? $this->id : 0;
			$id_topic = $this->type === 'topic' ? $this->id : 0;

			// Turn notification on.  (note this just blows smoke if it's already on.)
			Db::$db->insert(
				'ignore',
				'{db_prefix}log_notify',
				['id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'],
				[User::$me->id, $id_topic, $id_board],
				['id_member', 'id_topic', 'id_board'],
			);
		} else {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}log_notify
				WHERE id_member = {int:member}
					AND {raw:column} = {int:id}',
				[
					'column' => 'id_' . $this->type,
					'id' => $this->id,
					'member' => $this->member_info['id'],
				],
			);
		}
	}

	/**
	 * Adds some stuff to Utils::$context for AJAX output.
	 */
	protected function prepareAjaxResponse()
	{
		Utils::$context['xml_data']['errors'] = [
			'identifier' => 'error',
			'children' => [
				[
					'value' => 0,
				],
			],
		];

		Utils::$context['sub_template'] = 'generic_xml';
	}

	/**
	 * Shows a confirmation message.
	 */
	protected function showConfirmation()
	{
		Theme::loadTemplate('Notify');
		Utils::$context['page_title'] = Lang::$txt['notification'];
		Utils::$context['sub_template'] = 'notify_pref_changed';

		Utils::$context['notify_success_msg'] = $this->getSuccessMsg();
	}

	/**
	 * Gets the success message to display.
	 */
	abstract protected function getSuccessMsg();
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Notify::exportStatic')) {
	Notify::exportStatic();
}

?>