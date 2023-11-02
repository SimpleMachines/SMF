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

namespace SMF\Actions;

use SMF\BackwardCompatibility;

use SMF\BBCodeParser;
use SMF\BrowserDetector;
use SMF\Board;
use SMF\Config;
use SMF\Lang;
use SMF\Mail;
use SMF\Msg;
use SMF\Theme;
use SMF\Topic;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;

/**
 * This class handles sending announcements about topics.
 */
class Announce implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = array(
		'func_names' => array(
			'load' => false,
			'call' => 'AnnounceTopic',
			'selectGroup' => 'AnnouncementSelectMembergroup',
			'send' => 'AnnouncementSend',
		),
	);

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'selectgroup';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = array(
		'selectgroup' => 'selectGroup',
		'send' => 'send',
	);

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
		call_user_func(array($this, self::$subactions[$this->subaction]));
	}

	/**
	 *
	 */
	public function selectGroup(): void
	{
		$groups = array_merge(Board::$info->groups, array(1));

		foreach ($groups as $id => $group)
			$groups[$id] = (int) $group;

		Utils::$context['groups'] = array();

		if (in_array(0, $groups))
		{
			Utils::$context['groups'][0] = array(
				'id' => 0,
				'name' => Lang::$txt['announce_regular_members'],
				'member_count' => 'n/a',
			);
		}

		// Get all membergroups that have access to the board the announcement was made on.
		$request = Db::$db->query('', '
			SELECT mg.id_group, COUNT(mem.id_member) AS num_members
			FROM {db_prefix}membergroups AS mg
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_group = mg.id_group OR FIND_IN_SET(mg.id_group, mem.additional_groups) != 0 OR mg.id_group = mem.id_post_group)
			WHERE mg.id_group IN ({array_int:group_list})
			GROUP BY mg.id_group',
			array(
				'group_list' => $groups,
				'newbie_id_group' => 4,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['groups'][$row['id_group']] = array(
				'id' => $row['id_group'],
				'name' => '',
				'member_count' => $row['num_members'],
			);
		}
		Db::$db->free_result($request);

		// Now get the membergroup names.
		$request = Db::$db->query('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:group_list})',
			array(
				'group_list' => $groups,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			Utils::$context['groups'][$row['id_group']]['name'] = $row['group_name'];
		}
		Db::$db->free_result($request);

		// Get the subject of the topic we're about to announce.
		$request = Db::$db->query('', '
			SELECT m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:current_topic}',
			array(
				'current_topic' => Topic::$topic_id,
			)
		);
		list(Utils::$context['topic_subject']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Lang::censorText(Utils::$context['announce_topic']['subject']);

		Utils::$context['move'] = isset($_REQUEST['move']) ? 1 : 0;
		Utils::$context['go_back'] = isset($_REQUEST['goback']) ? 1 : 0;

		Utils::$context['sub_template'] = 'announce';
	}

	/**
	 *
	 */
	public function send(): void
	{
		checkSession();

		Utils::$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
		$groups = array_merge(Board::$info->groups, array(1));

		if (isset($_POST['membergroups']))
			$_POST['who'] = explode(',', $_POST['membergroups']);

		// Check whether at least one membergroup was selected.
		if (empty($_POST['who']))
			fatal_lang_error('no_membergroup_selected');

		// Make sure all membergroups are integers and can access the board of the announcement.
		foreach ($_POST['who'] as $id => $mg)
			$_POST['who'][$id] = in_array((int) $mg, $groups) ? (int) $mg : 0;

		// Get the topic subject and censor it.
		$request = Db::$db->query('', '
			SELECT m.id_msg, m.subject, m.body
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:current_topic}',
			array(
				'current_topic' => Topic::$topic_id,
			)
		);
		list($id_msg, Utils::$context['topic_subject'], $message) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		Lang::censorText(Utils::$context['topic_subject']);
		Lang::censorText($message);

		$message = trim(un_htmlspecialchars(strip_tags(strtr(BBCodeParser::load()->parse($message, false, $id_msg), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

		// Select the email addresses for this batch.
		$announcements = array();
		$rows = array();

		$request = Db::$db->query('', '
			SELECT mem.id_member, mem.email_address, mem.lngfile
			FROM {db_prefix}members AS mem
			WHERE (mem.id_group IN ({array_int:group_list}) OR mem.id_post_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:additional_group_list}, mem.additional_groups) != 0)
				AND mem.is_activated = {int:is_activated}
				AND mem.id_member > {int:start}
			ORDER BY mem.id_member
			LIMIT {int:chunk_size}',
			array(
				'group_list' => $_POST['who'],
				'is_activated' => 1,
				'start' => Utils::$context['start'],
				'additional_group_list' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $_POST['who']),
				// @todo Might need an interface?
				'chunk_size' => 500,
			)
		);
		// All members have received a mail. Go to the next screen.
		if (Db::$db->num_rows($request) == 0)
		{
			Db::$db->free_result($request);

			logAction('announce_topic', array('topic' => Topic::$topic_id), 'user');

			if (!empty($_REQUEST['move']) && allowedTo('move_any'))
			{
				redirectexit('action=movetopic;topic=' . Topic::$topic_id . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
			}
			elseif (!empty($_REQUEST['goback']))
			{
				redirectexit('topic=' . Topic::$topic_id . '.new;boardseen#new', BrowserDetector::isBrowser('ie'));
			}
			else
			{
				redirectexit('board=' . Board::$info->id . '.0');
			}
		}
		// Loop through all members that'll receive an announcement in this batch.
		while ($row = Db::$db->fetch_assoc($request))
		{
			$rows[$row['id_member']] = $row;
		}
		Db::$db->free_result($request);

		// Load their alert preferences
		require_once(Config::$sourcedir . '/Actions/Notify.php');
		$prefs = getNotifyPrefs(array_keys($rows), 'announcements', true);

		foreach ($rows as $row)
		{
			Utils::$context['start'] = $row['id_member'];

			// Force them to have it?
			if (empty($prefs[$row['id_member']]['announcements']))
				continue;

			$cur_language = empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile'];

			// If the language wasn't defined yet, load it and compose a notification message.
			if (!isset($announcements[$cur_language]))
			{
				$replacements = array(
					'TOPICSUBJECT' => Utils::$context['topic_subject'],
					'MESSAGE' => $message,
					'TOPICLINK' => Config::$scripturl . '?topic=' . Topic::$topic_id . '.0',
					'UNSUBSCRIBELINK' => Config::$scripturl . '?action=notifyannouncements;u={UNSUBSCRIBE_ID};token={UNSUBSCRIBE_TOKEN}',
				);

				$emaildata = Mail::loadEmailTemplate('new_announcement', $replacements, $cur_language);

				$announcements[$cur_language] = array(
					'subject' => $emaildata['subject'],
					'body' => $emaildata['body'],
					'is_html' => $emaildata['is_html'],
					'recipients' => array(),
				);
			}

			$announcements[$cur_language]['recipients'][$row['id_member']] = $row['email_address'];
		}

		// For each language send a different mail - low priority...
		foreach ($announcements as $lang => $mail)
		{
			foreach ($mail['recipients'] as $member_id => $member_email)
			{
				$token = createUnsubscribeToken($member_id, $member_email, 'announcements');

				$body = str_replace(array('{UNSUBSCRIBE_ID}', '{UNSUBSCRIBE_TOKEN}'), array($member_id, $token), $mail['body']);

				Mail::send($member_email, $mail['subject'], $body, null, null, false, 5);
			}

		}

		Utils::$context['percentage_done'] = round(100 * Utils::$context['start'] / Config::$modSettings['latestMember'], 1);

		Utils::$context['move'] = empty($_REQUEST['move']) ? 0 : 1;
		Utils::$context['go_back'] = empty($_REQUEST['goback']) ? 0 : 1;
		Utils::$context['membergroups'] = implode(',', $_POST['who']);
		Utils::$context['sub_template'] = 'announcement_send';

		// Go back to the correct language for the user ;).
		if (!empty(Config::$modSettings['userLanguage']))
			Lang::load('Post');
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
		if (!isset(self::$obj))
			self::$obj = new self();

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		isAllowedTo('announce_topic');

		validateSession();

		if (empty(Topic::$topic_id))
			fatal_lang_error('topic_gone', false);

		Lang::load('Post');
		Theme::loadTemplate('Post');

		Utils::$context['page_title'] = Lang::$txt['announce_topic'];

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[$_REQUEST['sa']]))
			$this->subaction = $_REQUEST['sa'];
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\Announce::exportStatic'))
	Announce::exportStatic();

?>