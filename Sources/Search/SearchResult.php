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

declare(strict_types=1);

namespace SMF\Search;

use SMF\BackwardCompatibility;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IP;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 *
 */
class SearchResult extends \SMF\Msg
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'highlight' => 'highlight',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * ID of the search result's topic's first message.
	 */
	public int $first_msg = 0;

	/**
	 * @var string
	 *
	 * Subject of the search result's topic's first message.
	 */
	public string $first_subject = '';

	/**
	 * @var string
	 *
	 * Icon of the search result's topic's first message.
	 */
	public string $first_icon = '';

	/**
	 * @var int
	 *
	 * Timestamp of the search result's topic's first message.
	 */
	public int $first_poster_time = 0;

	/**
	 * @var int
	 *
	 * ID of the search result's topic's first message's author.
	 */
	public int $first_member_id = 0;

	/**
	 * @var string
	 *
	 * Name of the search result's topic's first message's author.
	 */
	public string $first_member_name = '';

	/**
	 * @var int
	 *
	 * ID of the search result's topic's last message.
	 */
	public int $last_msg = 0;

	/**
	 * @var string
	 *
	 * Subject of the search result's topic's last message.
	 */
	public string $last_subject = '';

	/**
	 * @var string
	 *
	 * Icon of the search result's topic's last message.
	 */
	public string $last_icon = '';

	/**
	 * @var int
	 *
	 * Timestamp of the search result's topic's last message.
	 */
	public int $last_poster_time = 0;

	/**
	 * @var int
	 *
	 * ID of the search result's topic's last message's author.
	 */
	public int $last_member_id = 0;

	/**
	 * @var string
	 *
	 * Name of the search result's topic's last message's author.
	 */
	public string $last_member_name = '';

	/**
	 * @var int
	 *
	 * ID of the search result's board.
	 */
	public int $id_board = 0;

	/**
	 * @var string
	 *
	 * Name of the search result's board.
	 */
	public string $board_name = '';

	/**
	 * @var int
	 *
	 * ID of the search result's category.
	 */
	public int $id_cat = 0;

	/**
	 * @var string
	 *
	 * Name of the search result's category
	 */
	public string $cat_name = '';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Permissions this user has in the boards containing the search results.
	 */
	public static array $boards_can = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * Sets the formatted versions of search result data for use in templates.
	 *
	 * @param int $counter The number of this result in a list of results.
	 * @param array $format_options Options to control output. (Currently ignored.)
	 * @return array A copy of $this->formatted.
	 */
	public function format(int $counter = 0, array $format_options = []): array
	{
		// Can't have an empty subject can we?
		$this->subject = $this->subject != '' ? $this->subject : Lang::$txt['no_subject'];

		$this->first_subject = $this->first_subject != '' ? $this->first_subject : Lang::$txt['no_subject'];
		$this->last_subject = $this->last_subject != '' ? $this->last_subject : Lang::$txt['no_subject'];

		// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
		if (empty($this->id_member) || !isset(User::$loaded[$this->id_member])) {
			// Notice this information isn't used anywhere else.... *cough guest table cough*.
			$author['name'] = $this->poster_name;
			$author['id'] = 0;
			$author['group'] = Lang::$txt['guest_title'];
			$author['link'] = $this->poster_name;
			$author['email'] = $this->poster_email;
		} else {
			$author = User::$loaded[$this->id_member]->format(true);
		}

		$author['ip'] = new IP($this->poster_ip);

		// Do the censor thang...
		Lang::censorText($this->body);
		Lang::censorText($this->subject);

		Lang::censorText($this->first_subject);
		Lang::censorText($this->last_subject);

		// Shorten this message if necessary.
		if (!SearchApi::$loadedApi->params['show_complete']) {
			// Set the number of characters before and after the searched keyword.
			$charLimit = 50;

			$this->body = strtr($this->body, ["\n" => ' ', '<br>' => "\n", '<br/>' => "\n", '<br />' => "\n"]);
			$this->body = BBCodeParser::load()->parse($this->body, $this->smileys_enabled, $this->id_msg);
			$this->body = strip_tags(strtr($this->body, ['</div>' => '<br>', '</li>' => '<br>']), '<br>');

			if (Utils::entityStrlen($this->body) > $charLimit) {
				if (empty(SearchApi::$loadedApi->searchArray)) {
					$this->body = Utils::entitySubstr($this->body, 0, $charLimit) . '<strong>...</strong>';
				} else {
					$matchString = '';
					$force_partial_word = false;

					foreach (SearchApi::$loadedApi->searchArray as $keyword) {
						$keyword = Utils::htmlspecialcharsDecode($keyword);
						$keyword = Utils::sanitizeEntities(Utils::entityFix(strtr($keyword, ['\\\'' => '\'', '&' => '&amp;'])));

						if (preg_match('~[\'\.,/@%&;:(){}\[\]_\-+\\\\]$~', $keyword) || preg_match('~^[\'\.,/@%&;:(){}\[\]_\-+\\\\]~', $keyword)) {
							$force_partial_word = true;
						}
						$matchString .= strtr(preg_quote($keyword, '/'), ['\\*' => '.+?']) . '|';
					}
					$matchString = Utils::htmlspecialcharsDecode(substr($matchString, 0, -1));

					$this->body = Utils::htmlspecialcharsDecode(strtr($this->body, ['&nbsp;' => ' ', '<br>' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@']));

					if (empty(Config::$modSettings['search_method']) || $force_partial_word) {
						preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{0,' . $charLimit . '})/is' . (Utils::$context['utf8'] ? 'u' : ''), $this->body, $matches);
					} else {
						preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{0,' . $charLimit . '})/is' . (Utils::$context['utf8'] ? 'u' : ''), $this->body, $matches);
					}

					$this->body = '';

					foreach ($matches[0] as $index => $match) {
						$match = strtr(Utils::htmlspecialchars($match, ENT_QUOTES), ["\n" => '&nbsp;']);
						$this->body .= '<strong>......</strong>&nbsp;' . $match . '&nbsp;<strong>......</strong>';
					}
				}

				// Re-fix the international characters.
				$this->body = Utils::sanitizeEntities(Utils::entityFix($this->body));
			}
			$this->subject_highlighted = self::highlight($this->subject, SearchApi::$loadedApi->searchArray);
			$this->body_highlighted = self::highlight($this->body, SearchApi::$loadedApi->searchArray);
		} else {
			// Run BBC interpreter on the message.
			$this->body = BBCodeParser::load()->parse($this->body, $this->smileys_enabled, $this->id_msg);

			$this->subject_highlighted = self::highlight($this->subject, SearchApi::$loadedApi->searchArray);
			$this->body_highlighted = self::highlight($this->body, SearchApi::$loadedApi->searchArray);
		}

		// Make sure we don't end up with a practically empty message body.
		$this->body = preg_replace('~^(?:&nbsp;)+$~', '', $this->body);

		if (!empty($recycle_board) && $this->id_board == $recycle_board) {
			$this->first_icon = 'recycled';
			$this->last_icon = 'recycled';
			$this->icon = 'recycled';
		}

		// Sadly, we need to check the icon ain't broke.
		if (!empty(Config::$modSettings['messageIconChecks_enable'])) {
			if (!isset(Utils::$context['icon_sources'][$this->first_icon])) {
				Utils::$context['icon_sources'][$this->first_icon] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $this->first_icon . '.png') ? 'images_url' : 'default_images_url';
			}

			if (!isset(Utils::$context['icon_sources'][$this->last_icon])) {
				Utils::$context['icon_sources'][$this->last_icon] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $this->last_icon . '.png') ? 'images_url' : 'default_images_url';
			}

			if (!isset(Utils::$context['icon_sources'][$this->icon])) {
				Utils::$context['icon_sources'][$this->icon] = file_exists(Theme::$current->settings['theme_dir'] . '/images/post/' . $this->icon . '.png') ? 'images_url' : 'default_images_url';
			}
		} else {
			if (!isset(Utils::$context['icon_sources'][$this->first_icon])) {
				Utils::$context['icon_sources'][$this->first_icon] = 'images_url';
			}

			if (!isset(Utils::$context['icon_sources'][$this->last_icon])) {
				Utils::$context['icon_sources'][$this->last_icon] = 'images_url';
			}

			if (!isset(Utils::$context['icon_sources'][$this->icon])) {
				Utils::$context['icon_sources'][$this->icon] = 'images_url';
			}
		}

		// Do we have quote tag enabled?
		$quote_enabled = empty(Config::$modSettings['disabledBBC']) || !in_array('quote', explode(',', Config::$modSettings['disabledBBC']));

		// Reference the main color class.
		$colorClass = 'windowbg';

		// Sticky topics should get a different color, too.
		if ($this->is_sticky) {
			$colorClass .= ' sticky';
		}

		// Locked topics get special treatment as well.
		if ($this->locked) {
			$colorClass .= ' locked';
		}

		$output = array_merge(SearchApi::$loadedApi->results[$this->id], [
			'id' => $this->id_topic,
			'is_sticky' => !empty($this->is_sticky),
			'is_locked' => !empty($this->locked),
			'css_class' => $colorClass,
			'is_poll' => Config::$modSettings['pollMode'] == '1' && $this->id_poll > 0,
			'posted_in' => !empty(SearchApi::$loadedApi->participants[$this->id_topic]),
			'views' => $this->num_views,
			'replies' => $this->num_replies,
			'can_reply' => in_array($this->id_board, self::$boards_can['post_reply_any']) || in_array(0, self::$boards_can['post_reply_any']),
			'can_quote' => (in_array($this->id_board, self::$boards_can['post_reply_any']) || in_array(0, self::$boards_can['post_reply_any'])) && $quote_enabled,
			'first_post' => [
				'id' => $this->first_msg,
				'time' => Time::create('@' . $this->first_poster_time)->format(),
				'timestamp' => $this->first_poster_time,
				'subject' => $this->first_subject,
				'href' => Config::$scripturl . '?topic=' . $this->id_topic . '.0',
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $this->id_topic . '.0">' . $this->first_subject . '</a>',
				'icon' => $this->first_icon,
				'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$this->first_icon]] . '/post/' . $this->first_icon . '.png',
				'member' => [
					'id' => $this->first_member_id,
					'name' => $this->first_member_name,
					'href' => !empty($this->first_member_id) ? Config::$scripturl . '?action=profile;u=' . $this->first_member_id : '',
					'link' => !empty($this->first_member_id) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->first_member_id . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $this->first_member_name) . '">' . $this->first_member_name . '</a>' : $this->first_member_name,
				],
			],
			'last_post' => [
				'id' => $this->last_msg,
				'time' => Time::create('@' . $this->last_poster_time)->format(),
				'timestamp' => $this->last_poster_time,
				'subject' => $this->last_subject,
				'href' => Config::$scripturl . '?topic=' . $this->id_topic . ($this->num_replies == 0 ? '.0' : '.msg' . $this->last_msg) . '#msg' . $this->last_msg,
				'link' => '<a href="' . Config::$scripturl . '?topic=' . $this->id_topic . ($this->num_replies == 0 ? '.0' : '.msg' . $this->last_msg) . '#msg' . $this->last_msg . '">' . $this->last_subject . '</a>',
				'icon' => $this->last_icon,
				'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$this->last_icon]] . '/post/' . $this->last_icon . '.png',
				'member' => [
					'id' => $this->last_member_id,
					'name' => $this->last_member_name,
					'href' => !empty($this->last_member_id) ? Config::$scripturl . '?action=profile;u=' . $this->last_member_id : '',
					'link' => !empty($this->last_member_id) ? '<a href="' . Config::$scripturl . '?action=profile;u=' . $this->last_member_id . '" title="' . sprintf(Lang::$txt['view_profile_of_username'], $this->last_member_name) . '">' . $this->last_member_name . '</a>' : $this->last_member_name,
				],
			],
			'board' => [
				'id' => $this->id_board,
				'name' => $this->board_name,
				'href' => Config::$scripturl . '?board=' . $this->id_board . '.0',
				'link' => '<a href="' . Config::$scripturl . '?board=' . $this->id_board . '.0">' . $this->board_name . '</a>',
			],
			'category' => [
				'id' => $this->id_cat,
				'name' => $this->cat_name,
				'href' => Config::$scripturl . '#c' . $this->id_cat,
				'link' => '<a href="' . Config::$scripturl . '#c' . $this->id_cat . '">' . $this->cat_name . '</a>',
			],
		]);

		$output['matches'][] = [
			'id' => $this->id_msg,
			'attachment' => [],
			'member' => &$author,
			'icon' => $this->icon,
			'icon_url' => Theme::$current->settings[Utils::$context['icon_sources'][$this->icon]] . '/post/' . $this->icon . '.png',
			'subject' => $this->subject,
			'subject_highlighted' => $this->subject_highlighted,
			'time' => Time::create('@' . $this->poster_time)->format(),
			'timestamp' => $this->poster_time,
			'counter' => $counter,
			'modified' => [
				'time' => Time::create('@' . $this->modified_time)->format(),
				'timestamp' => $this->modified_time,
				'name' => $this->modified_name,
			],
			'body' => $this->body,
			'body_highlighted' => $this->body_highlighted,
			'start' => 'msg' . $this->id_msg,
		];

		return $output;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Generator that yields instances of this class.
	 *
	 * @param int|array $ids The ID numbers of the messages to load.
	 * @param array $query_customizations Customizations to the SQL query.
	 * @return \Generator<array> Iterating over result gives SearchResult instances.
	 */
	public static function get(/*int|array*/ $ids, array $query_customizations = [])/*: Generator*/
	{
		$selects = $query_customizations['selects'] ?? [
			'm.*',
			't.*',
			'first_m.id_msg AS first_msg',
			'first_m.subject AS first_subject',
			'first_m.icon AS first_icon',
			'first_m.poster_time AS first_poster_time',
			'COALESCE(first_mem.id_member, 0) AS first_member_id',
			'COALESCE(first_mem.real_name, first_m.poster_name) AS first_member_name',
			'last_m.id_msg AS last_msg',
			'last_m.poster_time AS last_poster_time',
			'COALESCE(last_mem.id_member, 0) AS last_member_id',
			'COALESCE(last_mem.real_name, last_m.poster_name) AS last_member_name',
			'last_m.icon AS last_icon',
			'last_m.subject AS last_subject',
			'b.id_board',
			'b.name AS board_name',
			'c.id_cat',
			'c.name AS cat_name',
		];

		$joins = $query_customizations['joins'] ?? [
			'INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)',
			'INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)',
			'INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)',
			'INNER JOIN {db_prefix}messages AS first_m ON (first_m.id_msg = t.id_first_msg)',
			'INNER JOIN {db_prefix}messages AS last_m ON (last_m.id_msg = t.id_last_msg)',
			'LEFT JOIN {db_prefix}members AS first_mem ON (first_mem.id_member = first_m.id_member)',
			'LEFT JOIN {db_prefix}members AS last_mem ON (last_mem.id_member = first_m.id_member)',
		];

		$where = $query_customizations['where'] ?? array_filter([
			'm.id_msg IN ({array_int:message_list})',
			// If post moderation is enabled, only get the posts this user can see.
			empty(Config::$modSettings['postmod_active'])
			? null
			: (
				empty(User::$me->mod_cache['ap'])
				? 'm.approved = {int:is_approved} OR m.id_member = {int:current_member}'
				: (
					User::$me->mod_cache['ap'] !== [0]
					? 'm.approved = {int:is_approved} OR m.id_member = {int:current_member} OR m.id_board IN ({array_int:approve_boards})'
					: null
				)
			),
		]);

		$order = $query_customizations['order'] ?? [
			Db::$db->custom_order('m.id_msg', $ids),
		];

		$group = $query_customizations['group'] ?? [];
		$limit = $query_customizations['limit'] ?? '{int:limit}';

		$params = $query_customizations['params'] ?? [
			'is_approved' => 1,
			'current_member' => User::$me->id,
			'approve_boards' => !empty(Config::$modSettings['postmod_active']) ? User::$me->mod_cache['ap'] : [],
			'limit' => count($ids),
		];

		if (!empty($ids)) {
			$params['message_list'] = self::$messages_to_get = array_filter(array_unique(array_map('intval', (array) $ids)));
		}

		foreach(self::queryData($selects, $params, $joins, $where, $order, $group, $limit) as $row) {
			$id = (int) $row['id_msg'];

			yield (new self($id, $row));

			if (!self::$keep_all) {
				unset(self::$loaded[$id]);
			}
		}

		// Reset this when done.
		self::$messages_to_get = [];
	}

	/**
	 * Highlights matching substrings in a string.
	 *
	 * @param string $text Text to search through.
	 * @param array $words List of keywords to search for.
	 * @return string Text with highlighted keywords.
	 */
	public static function highlight(string $text, array $words): string
	{
		$words = Utils::buildRegex($words, '~');

		$highlighted = '';

		// Don't mess with the content of HTML tags.
		$parts = preg_split('~(<[^>]+>)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $n = count($parts); $i < $n; $i++) {
			$highlighted .= $i % 2 === 0 ? preg_replace('~' . $words . '~iu', '<mark class="highlight">$0</mark>', $parts[$i]) : $parts[$i];
		}

		if (!empty($highlighted)) {
			$text = $highlighted;
		}

		return $text;
	}

	/**
	 * Gets the number of search results that the user can see.
	 */
	public static function getNumResults(): int
	{
		// Initialize the generator in order to set self::$messages_request.
		self::$getter->current();

		return Db::$db->num_rows(self::$messages_request) + (int) $_REQUEST['start'];
	}

	/**
	 * Populates self::$boards_can with various permissions organized by board.
	 */
	public static function setBoardsCan(): void
	{
		// Create an array for the permissions.
		$perms = ['post_reply_own', 'post_reply_any'];

		if (!empty(Theme::$current->options['display_quick_mod'])) {
			$perms = array_merge($perms, ['lock_any', 'lock_own', 'make_sticky', 'move_any', 'move_own', 'remove_any', 'remove_own', 'merge_any']);
		}

		self::$boards_can = User::$me->boardsAllowedTo($perms, true, false);

		// How's about some quick moderation?
		if (!empty(Theme::$current->options['display_quick_mod'])) {
			Utils::$context['can_lock'] = in_array(0, self::$boards_can['lock_any']);
			Utils::$context['can_sticky'] = in_array(0, self::$boards_can['make_sticky']);
			Utils::$context['can_move'] = in_array(0, self::$boards_can['move_any']);
			Utils::$context['can_remove'] = in_array(0, self::$boards_can['remove_any']);
			Utils::$context['can_merge'] = in_array(0, self::$boards_can['merge_any']);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\SearchResult::exportStatic')) {
	SearchResult::exportStatic();
}

?>