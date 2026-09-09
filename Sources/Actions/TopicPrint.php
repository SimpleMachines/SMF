<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Actions;

use SMF\ActionInterface;
use SMF\ActionSuffixRouter;
use SMF\ActionTrait;
use SMF\Attachment;
use SMF\Board;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\PageIndex;
use SMF\Parser;
use SMF\Poll;
use SMF\Routable;
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
class TopicPrint implements ActionInterface, Routable
{
	use ActionSuffixRouter;
	use ActionTrait;

	/*****************
	 * Class constants
	 *****************/

	/**
	 * The number of posts to show on one print page in forums that never show
	 * the "All" view.
	 */
	public const DEFAULT_MAX_POSTS = 250;

	/****************
	 * Public methods
	 ****************/

	public function isSimpleAction(): bool
	{
		return true;
	}

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

		// Only the posts this user is allowed to see are printed or counted.
		$approval_filter = Config::$modSettings['postmod_active'] && !User::$me->allowedTo('approve_posts') ? '
				AND (m.approved = {int:is_approved}' . (User::$me->is_guest ? '' : ' OR m.id_member = {int:current_member}') . ')' : '';

		$request = Db::$db->query(
			'SELECT COUNT(*)
			FROM {db_prefix}messages AS m
			WHERE m.id_topic = {int:current_topic}' . $approval_filter,
			[
				'current_topic' => Topic::$topic_id,
				'is_approved' => 1,
				'current_member' => User::$me->id,
			],
		);
		list($total_posts) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		$per_page = $this->getPostsPerPage();

		Utils::$context['start'] = (int) $_REQUEST['start'];

		$page_index = new PageIndex(
			Config::$scripturl . '?action=printpage;topic=' . Topic::$topic_id . '.%1$d' . (isset($_REQUEST['images']) ? ';images' : ''),
			Utils::$context['start'],
			(int) $total_posts,
			$per_page,
			true,
			true,
			// The print page carries its own styles and no scripts, so the
			// icons and the expanding page list have to be plain text here.
			[
				'previous_page' => Lang::getTxt('prev', file: 'General'),
				'next_page' => Lang::getTxt('next', file: 'General'),
				'expand_pages' => ' ... ',
			],
		);

		// If the supplied start value was invalid, redirect to the correct one.
		if ($_REQUEST['start'] != Utils::$context['start']) {
			Utils::redirectexit(\sprintf($page_index->base_url, Utils::$context['start']));
		}

		// There is nothing to navigate when the whole topic fits on one page.
		if ($total_posts > $per_page) {
			Utils::$context['page_index'] = $page_index;
		}

		// The poll belongs to the topic rather than to any of its posts, so it
		// is printed once, with the first page.
		if (!empty($row['id_poll']) && Utils::$context['start'] === 0) {
			$poll = Poll::load(Topic::$topic_id, Poll::LOAD_BY_TOPIC);
			Utils::$context['poll'] = $poll->format(['no_buttons' => true]);
		}

		// Let's output all that info.
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
			'SELECT subject, poster_time, body, COALESCE(mem.real_name, poster_name) AS poster_name, id_msg
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_topic = {int:current_topic}' . $approval_filter . '
			ORDER BY m.id_msg
			LIMIT {int:per_page} OFFSET {int:start}',
			[
				'current_topic' => Topic::$topic_id,
				'is_approved' => 1,
				'current_member' => User::$me->id,
				'per_page' => $per_page,
				'start' => Utils::$context['start'],
			],
		);
		Utils::$context['posts'] = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			// Censor the subject and message.
			Lang::censorText($row['subject']);
			Lang::censorText($row['body']);

			$row['body'] = Parser::transform($row['body'], options: ['for_print' => true]);

			Utils::$context['posts'][] = [
				'subject' => $row['subject'],
				'member' => $row['poster_name'],
				'time' => Time::create('@' . $row['poster_time'])->format(null, false),
				'timestamp' => $row['poster_time'],
				'body' => $row['body'],
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
				'SELECT
					a.id_attach, a.id_msg, a.approved, a.width, a.height, a.file_hash, a.filename, a.id_folder, a.mime_type
				FROM {db_prefix}attachments AS a
				WHERE a.id_msg IN ({array_int:message_list})
					AND a.attachment_type = {int:attachment_type}',
				[
					'message_list' => $messages,
					'attachment_type' => Attachment::TYPE_STANDARD,
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

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Works out how many posts belong on a single print page.
	 *
	 * A print page holds whole posts, parsed and in memory all at once, so it
	 * shows no more of them at a time than the admin is willing to show in the
	 * "All" view of a topic.
	 *
	 * @return int The maximum number of posts on one print page.
	 */
	protected function getPostsPerPage(): int
	{
		$per_page = (int) (Config::$modSettings['enableAllMessages'] ?? 0);

		return $per_page > 0 ? $per_page : self::DEFAULT_MAX_POSTS;
	}
}
