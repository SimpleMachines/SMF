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

use SMF\Attachment;
use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Poll;
use SMF\Theme;
use SMF\Time;
use SMF\Topic;
use SMF\User;
use SMF\Utils;

/**
 * This class formats a topic to be printer friendly.
 *
 * @todo Rewrite to use Msg::get() in order to reduce memory load?
 */
class TopicPrint implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'PrintTopic',
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
	 * Format a topic to be printer friendly.
	 * Must be called with a topic specified.
	 * Accessed via ?action=printpage.
	 *
	 * Uses Printpage template, main sub-template.
	 * Uses print_above/print_below later without the main layer.
	 */
	public function execute(): void
	{
		// Redirect to the boardindex if no valid topic id is provided.
		if (empty(Topic::$topic_id)) {
			Utils::redirectexit();
		}

		if (!empty(Config::$modSettings['disable_print_topic'])) {
			unset($_REQUEST['action']);
			Utils::$context['theme_loaded'] = false;
			ErrorHandler::fatalLang('feature_disabled', false);
		}

		// Whatever happens don't index this.
		Utils::$context['robot_no_index'] = true;

		// Get the topic starter information.
		$request = Db::$db->query(
			'',
			'SELECT mem.id_member, m.poster_time, COALESCE(mem.real_name, m.poster_name) AS poster_name, t.id_poll
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				LEFT JOIN {db_prefix}topics as t ON (t.id_first_msg = m.id_msg)
			WHERE m.id_topic = {int:current_topic}
			ORDER BY m.id_msg
			LIMIT 1',
			[
				'current_topic' => Topic::$topic_id,
			],
		);

		// Redirect to the boardindex if no valid topic id is provided.
		if (Db::$db->num_rows($request) == 0) {
			Utils::redirectexit();
		}
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		if (!empty($row['id_poll'])) {
			Lang::load('Post');
			$poll = Poll::load(Topic::$topic_id, Poll::LOAD_BY_TOPIC);
			Utils::$context['poll'] = $poll->format(['no_buttons' => true]);
		}

		// We want a separate BBCodeParser instance for this, not the reusable one
		// that would be returned by BBCodeParser::load().
		$bbcparser = new BBCodeParser();

		// Set the BBCodeParser to print mode.
		$bbcparser->for_print = true;

		// Lets "output" all that info.
		Theme::loadTemplate('Printpage');
		Utils::$context['template_layers'] = ['print'];
		Utils::$context['board_name'] = Board::$info->name;
		Utils::$context['category_name'] = Board::$info->cat['name'];
		Utils::$context['poster_name'] = $row['poster_name'];
		Utils::$context['post_time'] = Time::create('@' . $row['poster_time'])->format(null, false);
		Utils::$context['parent_boards'] = [];

		foreach (Board::$info->parent_boards as $parent) {
			Utils::$context['parent_boards'][] = $parent['name'];
		}

		// Split the topics up so we can print them.
		$request = Db::$db->query(
			'',
			'SELECT subject, poster_time, body, COALESCE(mem.real_name, poster_name) AS poster_name, id_msg
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_topic = {int:current_topic}' . (Config::$modSettings['postmod_active'] && !User::$me->allowedTo('approve_posts') ? '
				AND (m.approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR m.id_member = {int:current_member}') . ')' : '') . '
			ORDER BY m.id_msg',
			[
				'current_topic' => Topic::$topic_id,
				'is_approved' => 1,
				'current_member' => User::$me->id,
			],
		);
		Utils::$context['posts'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Censor the subject and message.
			Lang::censorText($row['subject']);
			Lang::censorText($row['body']);

			Utils::$context['posts'][] = [
				'subject' => $row['subject'],
				'member' => $row['poster_name'],
				'time' => Time::create('@' . $row['poster_time'])->format(null, false),
				'timestamp' => $row['poster_time'],
				'body' => $bbcparser->parse($row['body']),
				'id_msg' => $row['id_msg'],
			];

			if (!isset(Utils::$context['topic_subject'])) {
				Utils::$context['topic_subject'] = $row['subject'];
			}
		}
		Db::$db->free_result($request);

		// Fetch attachments so we can print them if asked, enabled and allowed
		if (isset($_REQUEST['images']) && !empty(Config::$modSettings['attachmentEnable']) && User::$me->allowedTo('view_attachments')) {
			$messages = [];

			foreach (Utils::$context['posts'] as $temp) {
				$messages[] = $temp['id_msg'];
			}

			// build the request
			$request = Db::$db->query(
				'',
				'SELECT
					a.id_attach, a.id_msg, a.approved, a.width, a.height, a.file_hash, a.filename, a.id_folder, a.mime_type
				FROM {db_prefix}attachments AS a
				WHERE a.id_msg IN ({array_int:message_list})
					AND a.attachment_type = {int:attachment_type}',
				[
					'message_list' => $messages,
					'attachment_type' => 0,
					'is_approved' => 1,
				],
			);
			$temp = [];

			while ($row = Db::$db->fetch_assoc($request)) {
				$temp[$row['id_attach']] = $row;

				if (!isset(Utils::$context['printattach'][$row['id_msg']])) {
					Utils::$context['printattach'][$row['id_msg']] = [];
				}
			}
			Db::$db->free_result($request);
			ksort($temp);

			// load them into Utils::$context so the template can use them
			foreach ($temp as $row) {
				if (!empty(Config::$modSettings['dont_show_attach_under_post']) && !empty(Utils::$context['show_attach_under_post'][$row['id_attach']])) {
					continue;
				}

				if (!empty($row['width']) && !empty($row['height'])) {
					if (!empty(Config::$modSettings['max_image_width']) && (empty(Config::$modSettings['max_image_height']) || $row['height'] * (Config::$modSettings['max_image_width'] / $row['width']) <= Config::$modSettings['max_image_height'])) {
						if ($row['width'] > Config::$modSettings['max_image_width']) {
							$row['height'] = floor($row['height'] * (Config::$modSettings['max_image_width'] / $row['width']));
							$row['width'] = Config::$modSettings['max_image_width'];
						}
					} elseif (!empty(Config::$modSettings['max_image_width'])) {
						if ($row['height'] > Config::$modSettings['max_image_height']) {
							$row['width'] = floor($row['width'] * Config::$modSettings['max_image_height'] / $row['height']);
							$row['height'] = Config::$modSettings['max_image_height'];
						}
					}

					$row['filename'] = Attachment::getFilePath($row['id_attach']);

					// save for the template
					Utils::$context['printattach'][$row['id_msg']][] = $row;
				}
			}
		}

		// Set a canonical URL for this page.
		Utils::$context['canonical_url'] = Config::$scripturl . '?topic=' . Topic::$topic_id . '.0';
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
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\TopicPrint::exportStatic')) {
	TopicPrint::exportStatic();
}

?>