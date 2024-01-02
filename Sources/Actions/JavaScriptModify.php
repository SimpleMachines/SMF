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
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Logging;
use SMF\Msg;
use SMF\Time;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * Used to edit the body or subject of a message inline.
 *
 * Called via '?action=jsmodify' by script.js and topic.js
 */
class JavaScriptModify implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'JavaScriptModify',
		],
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
	 * Does the job.
	 */
	public function execute(): void
	{
		// Assume the first message if no message ID was given.
		$request = Db::$db->query(
			'',
			'SELECT
				t.locked, t.num_replies, t.id_member_started, t.id_first_msg,
				m.id_msg, m.id_member, m.poster_time, m.subject, m.smileys_enabled, m.body, m.icon,
				m.modified_time, m.modified_name, m.modified_reason, m.approved,
				m.poster_name, m.poster_email
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			WHERE m.id_msg = {raw:id_msg}
				AND m.id_topic = {int:current_topic}' . (User::$me->allowedTo('modify_any') || User::$me->allowedTo('approve_posts') ? '' : (!Config::$modSettings['postmod_active'] ? '
				AND (m.id_member != {int:guest_id} AND m.id_member = {int:current_member})' : '
				AND (m.approved = {int:is_approved} OR (m.id_member != {int:guest_id} AND m.id_member = {int:current_member}))')),
			[
				'current_member' => User::$me->id,
				'current_topic' => Topic::$topic_id,
				'id_msg' => empty($_REQUEST['msg']) ? 't.id_first_msg' : (int) $_REQUEST['msg'],
				'is_approved' => 1,
				'guest_id' => 0,
			],
		);

		if (Db::$db->num_rows($request) == 0) {
			ErrorHandler::fatalLang('no_board', false);
		}
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		// Change either body or subject requires permissions to modify messages.
		if (isset($_POST['message']) || isset($_POST['subject']) || isset($_REQUEST['icon'])) {
			if (!empty($row['locked'])) {
				User::$me->isAllowedTo('moderate_board');
			}

			if ($row['id_member'] == User::$me->id && !User::$me->allowedTo('modify_any')) {
				if ((!Config::$modSettings['postmod_active'] || $row['approved']) && !empty(Config::$modSettings['edit_disable_time']) && $row['poster_time'] + (Config::$modSettings['edit_disable_time'] + 5) * 60 < time()) {
					ErrorHandler::fatalLang('modify_post_time_passed', false);
				} elseif ($row['id_member_started'] == User::$me->id && !User::$me->allowedTo('modify_own')) {
					User::$me->isAllowedTo('modify_replies');
				} else {
					User::$me->isAllowedTo('modify_own');
				}
			}
			// Otherwise, they're locked out; someone who can modify the replies is needed.
			elseif ($row['id_member_started'] == User::$me->id && !User::$me->allowedTo('modify_any')) {
				User::$me->isAllowedTo('modify_replies');
			} else {
				User::$me->isAllowedTo('modify_any');
			}

			// Only log this action if it wasn't your message.
			$moderationAction = $row['id_member'] != User::$me->id;
		}

		$post_errors = [];

		if (isset($_POST['subject']) && Utils::htmlTrim(Utils::htmlspecialchars($_POST['subject'])) !== '') {
			$_POST['subject'] = strtr(Utils::htmlspecialchars($_POST['subject']), ["\r" => '', "\n" => '', "\t" => '']);

			// Maximum number of characters.
			if (Utils::entityStrlen($_POST['subject']) > 100) {
				$_POST['subject'] = Utils::entitySubstr($_POST['subject'], 0, 100);
			}
		} elseif (isset($_POST['subject'])) {
			$post_errors[] = 'no_subject';
			unset($_POST['subject']);
		}

		if (isset($_POST['message'])) {
			if (Utils::htmlTrim(Utils::htmlspecialchars($_POST['message'])) === '') {
				$post_errors[] = 'no_message';
				unset($_POST['message']);
			} elseif (!empty(Config::$modSettings['max_messageLength']) && Utils::entityStrlen($_POST['message']) > Config::$modSettings['max_messageLength']) {
				$post_errors[] = 'long_message';
				unset($_POST['message']);
			} else {
				$_POST['message'] = Utils::htmlspecialchars($_POST['message'], ENT_QUOTES);

				Msg::preparsecode($_POST['message']);

				if (Utils::htmlTrim(strip_tags(BBCodeParser::load()->parse($_POST['message'], false), implode('', Utils::$context['allowed_html_tags']))) === '') {
					$post_errors[] = 'no_message';
					unset($_POST['message']);
				}
			}
		}

		IntegrationHook::call('integrate_post_JavascriptModify', [&$post_errors, $row]);

		if (isset($_POST['lock'])) {
			if (!User::$me->allowedTo(['lock_any', 'lock_own']) || (!User::$me->allowedTo('lock_any') && User::$me->id != $row['id_member'])) {
				unset($_POST['lock']);
			} elseif (!User::$me->allowedTo('lock_any')) {
				if ($row['locked'] == 1) {
					unset($_POST['lock']);
				} else {
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
				}
			} elseif (!empty($row['locked']) && !empty($_POST['lock']) || $_POST['lock'] == $row['locked']) {
				unset($_POST['lock']);
			} else {
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
			}
		}

		if (isset($_POST['sticky']) && !User::$me->allowedTo('make_sticky')) {
			unset($_POST['sticky']);
		}

		if (isset($_POST['modify_reason'])) {
			$_POST['modify_reason'] = strtr(Utils::htmlspecialchars($_POST['modify_reason']), ["\r" => '', "\n" => '', "\t" => '']);

			// Maximum number of characters.
			if (Utils::entityStrlen($_POST['modify_reason']) > 100) {
				$_POST['modify_reason'] = Utils::entitySubstr($_POST['modify_reason'], 0, 100);
			}
		}

		if (empty($post_errors)) {
			$msgOptions = [
				'id' => $row['id_msg'],
				'subject' => $_POST['subject'] ?? null,
				'body' => $_POST['message'] ?? null,
				'icon' => isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : null,
				'modify_reason' => ($_POST['modify_reason'] ?? ''),
				'approved' => ($row['approved'] ?? null),
			];

			$topicOptions = [
				'id' => Topic::$topic_id,
				'board' => Board::$info->id,
				'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
				'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
				'mark_as_read' => true,
			];

			$posterOptions = [
				'id' => User::$me->id,
				'name' => $row['poster_name'],
				'email' => $row['poster_email'],
				'update_post_count' => !User::$me->is_guest && !isset($_REQUEST['msg']) && Board::$info->posts_count,
			];

			// Only consider marking as editing if they have edited the subject, message or icon.
			if ((isset($_POST['subject']) && $_POST['subject'] != $row['subject']) || (isset($_POST['message']) && $_POST['message'] != $row['body']) || (isset($_REQUEST['icon']) && $_REQUEST['icon'] != $row['icon'])) {
				// And even then only if the time has passed...
				if (time() - $row['poster_time'] > Config::$modSettings['edit_wait_time'] || User::$me->id != $row['id_member']) {
					$msgOptions['modify_time'] = time();
					$msgOptions['modify_name'] = User::$me->name;
				}
			}
			// If nothing was changed there's no need to add an entry to the moderation log.
			else {
				$moderationAction = false;
			}

			Msg::modify($msgOptions, $topicOptions, $posterOptions);

			// If we didn't change anything this time but had before put back the old info.
			if (!isset($msgOptions['modify_time']) && !empty($row['modified_time'])) {
				$msgOptions['modify_time'] = $row['modified_time'];
				$msgOptions['modify_name'] = $row['modified_name'];
				$msgOptions['modify_reason'] = $row['modified_reason'];
			}

			// Changing the first subject updates other subjects to 'Re: new_subject'.
			if (isset($_POST['subject'], $_REQUEST['change_all_subjects'])   && $row['id_first_msg'] == $row['id_msg'] && !empty($row['num_replies']) && (User::$me->allowedTo('modify_any') || ($row['id_member_started'] == User::$me->id && User::$me->allowedTo('modify_replies')))) {
				// Get the proper (default language) response prefix first.
				if (!isset(Utils::$context['response_prefix']) && !(Utils::$context['response_prefix'] = CacheApi::get('response_prefix'))) {
					if (Lang::$default === User::$me->language) {
						Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
					} else {
						Lang::load('index', Lang::$default, false);
						Utils::$context['response_prefix'] = Lang::$txt['response_prefix'];
						Lang::load('index');
					}
					CacheApi::put('response_prefix', Utils::$context['response_prefix'], 600);
				}

				Db::$db->query(
					'',
					'UPDATE {db_prefix}messages
					SET subject = {string:subject}
					WHERE id_topic = {int:current_topic}
						AND id_msg != {int:id_first_msg}',
					[
						'current_topic' => Topic::$topic_id,
						'id_first_msg' => $row['id_first_msg'],
						'subject' => Utils::$context['response_prefix'] . $_POST['subject'],
					],
				);
			}

			if (!empty($moderationAction)) {
				Logging::logAction('modify', ['topic' => Topic::$topic_id, 'message' => $row['id_msg'], 'member' => $row['id_member'], 'board' => Board::$info->id]);
			}
		}

		if (isset($_REQUEST['xml'])) {
			Utils::$context['sub_template'] = 'modifydone';

			if (empty($post_errors) && isset($msgOptions['subject'], $msgOptions['body'])) {
				Utils::$context['message'] = [
					'id' => $row['id_msg'],
					'modified' => [
						'time' => isset($msgOptions['modify_time']) ? Time::create('@' . $msgOptions['modify_time'])->format() : '',
						'timestamp' => $msgOptions['modify_time'] ?? 0,
						'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
						'reason' => $msgOptions['modify_reason'],
					],
					'subject' => $msgOptions['subject'],
					'first_in_topic' => $row['id_msg'] == $row['id_first_msg'],
					'body' => strtr($msgOptions['body'], [']]>' => ']]]]><![CDATA[>']),
				];

				Lang::censorText(Utils::$context['message']['subject']);
				Lang::censorText(Utils::$context['message']['body']);

				Utils::$context['message']['body'] = BBCodeParser::load()->parse(Utils::$context['message']['body'], $row['smileys_enabled'], $row['id_msg']);
			}
			// Topic?
			elseif (empty($post_errors)) {
				Utils::$context['sub_template'] = 'modifytopicdone';
				Utils::$context['message'] = [
					'id' => $row['id_msg'],
					'modified' => [
						'time' => isset($msgOptions['modify_time']) ? Time::create('@' . $msgOptions['modify_time'])->format() : '',
						'timestamp' => $msgOptions['modify_time'] ?? 0,
						'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
					],
					'subject' => $msgOptions['subject'] ?? '',
				];

				Lang::censorText(Utils::$context['message']['subject']);
			} else {
				Utils::$context['message'] = [
					'id' => $row['id_msg'],
					'errors' => [],
					'error_in_subject' => in_array('no_subject', $post_errors),
					'error_in_body' => in_array('no_message', $post_errors) || in_array('long_message', $post_errors),
				];

				Lang::load('Errors');

				foreach ($post_errors as $post_error) {
					if ($post_error == 'long_message') {
						Utils::$context['message']['errors'][] = sprintf(Lang::$txt['error_' . $post_error], Config::$modSettings['max_messageLength']);
					} else {
						Utils::$context['message']['errors'][] = Lang::$txt['error_' . $post_error];
					}
				}
			}

			// Allow mods to do something with Utils::$context before we return.
			IntegrationHook::call('integrate_jsmodify_xml');
		} else {
			Utils::obExit(false);
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// We have to have a topic!
		if (empty(Topic::$topic_id)) {
			Utils::obExit(false);
		}

		User::$me->checkSession('get');
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\JavaScriptModify::exportStatic')) {
	JavaScriptModify::exportStatic();
}

?>