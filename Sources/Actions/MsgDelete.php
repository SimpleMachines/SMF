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

use SMF\Board;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Msg;
use SMF\Topic;
use SMF\User;
use SMF\Db\DatabaseApi as Db;

/**
 * This action handles the deletion of posts.
 */
class MsgDelete implements ActionInterface
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
			'call' => 'DeleteMessage',
		),
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
	 * Remove just a single post.
	 * On completion redirect to the topic or to the board.
	 */
	public function execute(): void
	{
		checkSession('get');

		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Is Topic::$topic_id set?
		if (empty(Topic::$topic_id) && isset($_REQUEST['topic']))
			Topic::$topic_id = (int) $_REQUEST['topic'];

		TopicRemove::removeDeleteConcurrence();

		$request = Db::$db->query('', '
			SELECT t.id_member_started, m.id_member, m.subject, m.poster_time, m.approved
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = {int:id_msg} AND m.id_topic = {int:current_topic})
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => Topic::$topic_id,
				'id_msg' => $_REQUEST['msg'],
			)
		);
		list($starter, $poster, $subject, $post_time, $approved) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Verify they can see this!
		if (Config::$modSettings['postmod_active'] && !$approved && !empty($poster) && $poster != User::$me->id)
		{
			isAllowedTo('approve_posts');
		}

		if ($poster == User::$me->id)
		{
			if (!allowedTo('delete_own'))
			{
				if ($starter == User::$me->id && !allowedTo('delete_any'))
				{
					isAllowedTo('delete_replies');
				}
				elseif (!allowedTo('delete_any'))
				{
					isAllowedTo('delete_own');
				}
			}
			elseif (!allowedTo('delete_any') && ($starter != User::$me->id || !allowedTo('delete_replies')) && !empty(Config::$modSettings['edit_disable_time']) && $post_time + Config::$modSettings['edit_disable_time'] * 60 < time())
			{
				ErrorHandler::fatalLang('modify_post_time_passed', false);
			}
		}
		elseif ($starter == User::$me->id && !allowedTo('delete_any'))
		{
			isAllowedTo('delete_replies');
		}
		else
		{
			isAllowedTo('delete_any');
		}

		// If the full topic was removed go back to the board.
		$full_topic = Msg::remove($_REQUEST['msg']);

		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $poster != User::$me->id))
		{
			logAction('delete', array('topic' => Topic::$topic_id, 'subject' => $subject, 'member' => $poster, 'board' => Board::$info->id));
		}

		// We want to redirect back to recent action.
		if (isset($_REQUEST['modcenter']))
		{
			redirectexit('action=moderate;area=reportedposts;done');
		}
		elseif (isset($_REQUEST['recent']))
		{
			redirectexit('action=recent');
		}
		elseif (isset($_REQUEST['profile'], $_REQUEST['start'], $_REQUEST['u']))
		{
			redirectexit('action=profile;u=' . $_REQUEST['u'] . ';area=showposts;start=' . $_REQUEST['start']);
		}
		elseif ($full_topic)
		{
			redirectexit('board=' . Board::$info->id . '.0');
		}
		else
		{
			redirectexit('topic=' . Topic::$topic_id . '.' . $_REQUEST['start']);
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\MsgDelete::exportStatic'))
	MsgDelete::exportStatic();

?>