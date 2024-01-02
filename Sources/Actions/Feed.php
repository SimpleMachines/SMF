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
use SMF\BrowserDetector;
use SMF\Cache\CacheApi;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\ErrorHandler;
use SMF\IntegrationHook;
use SMF\IP;
use SMF\Lang;
use SMF\Theme;
use SMF\Time;
use SMF\Url;
use SMF\User;
use SMF\Utils;

/**
 * This class contains the code necessary to display XML feeds.
 *
 * Outputs xml data representing recent information or a profile.
 *
 * Can be passed subactions which decide what is output:
 *  'recent' for recent posts,
 *  'news' for news topics,
 *  'members' for recently registered members,
 *  'profile' for a member's profile.
 *  'posts' for a member's posts.
 *  'personal_messages' for a member's personal messages.
 *
 * When displaying a member's profile or posts, the u parameter identifies which member. Defaults
 * to the current user's id.
 * To display a member's personal messages, the u parameter must match the id of the current user.
 *
 * Outputs can be in RSS 0.92, RSS 2, Atom, RDF, or our own custom XML format. Default is RSS 2.
 *
 * Accessed via ?action=.xml.
 *
 * Does not use any templates, sub templates, or template layers.
 *
 * Uses Stats, Profile, Post, and PersonalMessage language files.
 */
class Feed implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ShowXmlFeed',
			'build' => 'buildXmlFeed',
			'cdataParse' => 'cdata_parse',
		],
	];

	/*****************
	 * Class constants
	 *****************/

	/**
	 * An array of sprintf() strings to define XML namespaces.
	 *
	 * Do NOT change any of these to HTTPS addresses! Not even the SMF one.
	 *
	 * Why? Because XML namespace names must be both unique and invariant
	 * once defined. They look like URLs merely because that's a convenient
	 * way to ensure uniqueness, but they are not used as URLs. They are
	 * used as case-sensitive identifier strings. If the string changes in
	 * any way, XML processing software (including PHP's own XML functions)
	 * will interpret the two versions of the string as entirely different
	 * namespaces, which could cause it to mangle the XML horrifically
	 * during processing.
	 *
	 * These strings have been broken up and concatenated to help prevent any
	 * automatic search and replace attempts from changing them.
	 */
	public const XML_NAMESPACES = [
		'rss' => [],
		'rss2' => [
			'atom' => 'htt' . 'p:/' . '/ww' . 'w.w3.o' . 'rg/2005/Atom',
		],
		'atom' => [
			'' => 'htt' . 'p:/' . '/ww' . 'w.w3.o' . 'rg/2005/Atom',
		],
		'rdf' => [
			'' => 'htt' . 'p:/' . '/purl.o' . 'rg/rss/1.0/',
			'rdf' => 'htt' . 'p:/' . '/ww' . 'w.w3.o' . 'rg/1999/02/22-rdf-syntax-ns#',
			'dc' => 'htt' . 'p:/' . '/purl.o' . 'rg/dc/elements/1.1/',
		],
		'smf' => [
			'smf' => 'htt' . 'p:/' . '/ww' . 'w.simple' . 'machines.o' . 'rg/xml/%1s',
		],
	];

	/**
	 * An array of MIME types for feed formats.
	 */
	public const MIME_TYPES = [
		'rss' => 'application/rss+xml',
		'rss2' => 'application/rss+xml',
		'atom' => 'application/atom+xml',
		'rdf' => 'application/rdf+xml',
		'smf' => 'text/xml',
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 */
	public string $subaction = 'recent';

	/**
	 * @var string
	 *
	 * The requested feed format (e.g. RSS2, Atom, etc.)
	 */
	public string $format = 'rss2';

	/**
	 * @var int
	 *
	 * Maximum number of items to fetch.
	 * Range: 1 - 255.
	 *
	 * Only relevant for some sub-actions.
	 */
	public int $limit = 5;

	/**
	 * @var int
	 *
	 * ID of item to start after.
	 * Only relevant for posts and personal_messages sub-actions.
	 */
	public int $start_after = 0;

	/**
	 * @var bool
	 *
	 * Whether data is in ascending order or not.
	 * Only relevant for some sub-actions.
	 */
	public bool $ascending = false;

	/**
	 * @var int
	 *
	 * ID of a member.
	 * Used by members, profile, posts, and personal_messages sub-actions.
	 */
	public int $member;

	/**
	 * @var array
	 *
	 * Metadata about this feed.
	 */
	public array $metadata = [];

	/**
	 * @var array
	 *
	 * Main data in this feed.
	 */
	public array $data = [];

	/**
	 * @var array
	 *
	 * Boards to fetch posts from.
	 */
	public array $boards = [];

	/**
	 * @var string
	 *
	 * Part of an SQL WHERE clause.
	 * Restricts query to only fetch posts from certain boards.
	 */
	public string $query_this_board = '';

	/**
	 * @var array
	 *
	 * The constructed XML.
	 */
	public array $xml = [
		'header' => '',
		'items' => '',
		'footer' => '',
	];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * List all the different types of data they can pull.
	 */
	public static array $subactions = [
		'recent' => 'getXmlRecent',
		'news' => 'getXmlNews',
		'members' => 'getXmlMembers',
		'profile' => 'getXmlProfile',
		'posts' => 'getXmlPosts',
		'personal_messages' => 'getXmlPMs',
	];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Used to optimize SQL queries.
	 */
	protected array $optimize_msg = [
		'highest' => 'm.id_msg <= b.id_last_msg',
		'lowest' => 'm.id_msg >= 0',
	];

	/**
	 * @var string
	 *
	 * Host extracted from $scripturl.
	 * Used to generate globally unique identifiers.
	 */
	protected string $host = '';

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param string $subaction Sets the sub-action to call.
	 *     If null, will try $_GET['sa'] and then the default sub-action.
	 * @param int $member The member whose data is being requested.
	 *     If null, will try $_GET['u'] and then User::$me->id.
	 */
	public function __construct(?string $subaction = null, ?int $member = null)
	{
		$this->host = Url::create(Config::$scripturl)->host;

		// Easy adding of sub actions
		IntegrationHook::call('integrate_xmlfeeds', [&self::$subactions]);

		// These things are simple to set.
		$this->setSubaction($subaction);
		$this->setMember($member);
		$this->setLimit();
		$this->setFormat();

		// Bail out if feeds are disabled.
		$this->checkEnabled();

		// The feed metadata and query are a bit more complicated...
		Lang::load('Stats');

		// Some general metadata for this feed. We'll change some of these values below.
		$this->metadata = [
			'title' => '',
			'desc' => sprintf(Lang::$txt['xml_rss_desc'], Utils::$context['forum_name']),
			'author' => Utils::$context['forum_name'],
			'source' => Config::$scripturl,
			'rights' => '© ' . date('Y') . ' ' . Utils::$context['forum_name'],
			'icon' => !empty(Theme::$current->settings['og_image']) ? Theme::$current->settings['og_image'] : Config::$boardurl . '/favicon.ico',
			'language' => !empty(Lang::$txt['lang_locale']) ? str_replace('_', '-', substr(Lang::$txt['lang_locale'], 0, strcspn(Lang::$txt['lang_locale'], '.'))) : 'en',
			'self' => Config::$scripturl,
		];

		// Set $this->metadata['self'] to the canonical form of the requested URL.
		foreach (['action', 'sa', 'type', 'board', 'boards', 'c', 'u', 'limit', 'offset'] as $var) {
			if (isset($_GET[$var])) {
				$this->metadata['self'] .= ($this->metadata['self'] === Config::$scripturl ? '?' : ';') . $var . '=' . $_GET[$var];
			}
		}

		// Handle the cases where a board, boards, or category is asked for.
		if (!empty($_GET['c']) && empty(Board::$info->id)) {
			$_GET['c'] = explode(',', $_GET['c']);

			foreach ($_GET['c'] as $i => $c) {
				$_GET['c'][$i] = (int) $c;
			}

			if (count($_GET['c']) == 1) {
				$request = Db::$db->query(
					'',
					'SELECT name
					FROM {db_prefix}categories
					WHERE id_cat = {int:current_category}',
					[
						'current_category' => (int) $_GET['c'][0],
					],
				);
				list($this->metadata['title']) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);
			}

			$total_cat_posts = 0;
			$this->boards = [];
			$request = Db::$db->query(
				'',
				'SELECT b.id_board, b.num_posts
				FROM {db_prefix}boards AS b
				WHERE b.id_cat IN ({array_int:current_category_list})
					AND {query_see_board}',
				[
					'current_category_list' => $_GET['c'],
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$this->boards[] = $row['id_board'];
				$total_cat_posts += $row['num_posts'];
			}
			Db::$db->free_result($request);

			if (!empty($this->boards)) {
				$this->query_this_board = 'b.id_board IN (' . implode(', ', $this->boards) . ')';
			} else {
				ErrorHandler::fatalLang('no_board', false);
			}

			// Try to limit the number of messages we look through.
			if ($total_cat_posts > 100 && $total_cat_posts > Config::$modSettings['totalMessages'] / 15) {
				$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, Config::$modSettings['maxMsgID'] - 400 - $this->limit * 5);
			}
		} elseif (!empty($_GET['boards'])) {
			$_GET['boards'] = explode(',', $_GET['boards']);

			foreach ($_GET['boards'] as $i => $b) {
				$_GET['boards'][$i] = (int) $b;
			}

			$total_posts = 0;
			$this->boards = [];
			$num_boards = 0;

			$request = Db::$db->query(
				'',
				'SELECT b.id_board, b.num_posts, b.name
				FROM {db_prefix}boards AS b
				WHERE b.id_board IN ({array_int:board_list})
					AND {query_see_board}
				LIMIT {int:limit}',
				[
					'board_list' => $_GET['boards'],
					'limit' => count($_GET['boards']),
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				if (++$num_boards == 1) {
					$this->metadata['title'] = $row['name'];
				}

				$this->boards[] = $row['id_board'];
				$total_posts += $row['num_posts'];
			}
			Db::$db->free_result($request);

			if ($num_boards == 0) {
				ErrorHandler::fatalLang('no_board', false);
			}

			if (!empty($this->boards)) {
				$this->query_this_board = 'b.id_board IN (' . implode(', ', $this->boards) . ')';
			}

			// The more boards, the more we're going to look through...
			if ($total_posts > 100 && $total_posts > Config::$modSettings['totalMessages'] / 12) {
				$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, Config::$modSettings['maxMsgID'] - 500 - $this->limit * 5);
			}
		} elseif (!empty(Board::$info->id)) {
			$request = Db::$db->query(
				'',
				'SELECT num_posts
				FROM {db_prefix}boards AS b
				WHERE id_board = {int:current_board}
					AND {query_see_board}
				LIMIT 1',
				[
					'current_board' => Board::$info->id,
				],
			);

			if (Db::$db->num_rows($request) == 0) {
				Db::$db->free_result($request);
				ErrorHandler::fatalLang('no_board', false);
			}
			list($total_posts) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);

			$this->metadata['title'] = Board::$info->name;
			$this->metadata['source'] .= '?board=' . Board::$info->id . '.0';

			$this->boards[] = Board::$info->id;

			$this->query_this_board = 'b.id_board = ' . Board::$info->id;

			// Try to look through just a few messages, if at all possible.
			if ($total_posts > 80 && $total_posts > Config::$modSettings['totalMessages'] / 10) {
				$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, Config::$modSettings['maxMsgID'] - 600 - $this->limit * 5);
			}
		} else {
			$this->query_this_board = '{query_see_board}' . (!empty(Config::$modSettings['recycle_enable']) && Config::$modSettings['recycle_board'] > 0 ? '
				AND b.id_board != ' . Config::$modSettings['recycle_board'] : '');

			$this->optimize_msg['lowest'] = 'm.id_msg >= ' . max(0, Config::$modSettings['maxMsgID'] - 100 - $this->limit * 5);
		}

		$this->metadata['title'] .= (!empty($this->metadata['title']) ? ' - ' : '') . Utils::$context['forum_name'];

		// Sanitize feed metadata values
		foreach ($this->metadata as $key => $value) {
			$this->metadata[$key] = strip_tags($value);
		}
	}

	/**
	 * Fetches the data based on the sub-action, builds the XML, and emits it.
	 */
	public function execute(): void
	{
		$this->getData();
		$this->xml = self::build($this->format, $this->data, $this->metadata, $this->subaction);
		$this->emit();
	}

	/**
	 * Retrieve the correct type of data based on $this->subaction.
	 * The array will be structured to match $this->format.
	 *
	 * @return array An array of arrays of feed items. Each array has keys corresponding to the appropriate tags for the specified format.
	 */
	public function getData(): array
	{
		// We only want some information, not all of it.
		$cachekey = [$this->subaction, $this->limit, $this->ascending, $this->start_after];

		if (!empty($this->member)) {
			$cachekey[] = $this->member;
		}

		if (!empty($this->boards)) {
			$cachekey[] = 'boards=' . implode(',', $this->boards);
		}

		$cachekey = md5(Utils::jsonEncode($cachekey) . (!empty($this->query_this_board) ? $this->query_this_board : ''));

		$cache_t = microtime(true);

		// Get the associative array representing the xml.
		if (!empty(CacheApi::$enable) && (!User::$me->is_guest || CacheApi::$enable >= 3)) {
			$this->data = CacheApi::get('xmlfeed-' . $this->format . ':' . (User::$me->is_guest ? '' : User::$me->id . '-') . $cachekey, 240) ?? [];
		}

		if (empty($this->data)) {
			// Should we call one of this class's own methods, or something added by a mod?
			if (is_callable([$this, self::$subactions[$this->subaction]])) {
				$call = [$this, self::$subactions[$this->subaction]];
			} else {
				$call = Utils::getCallable(self::$subactions[$this->subaction]);
			}

			$this->data = !empty($call) ? call_user_func($call, $this->format) : [];

			if (
				!empty(CacheApi::$enable)
				&& (
					(User::$me->is_guest && CacheApi::$enable >= 3)
					|| (!User::$me->is_guest && (microtime(true) - $cache_t > 0.2))
				)
			) {
				CacheApi::put('xmlfeed-' . $this->format . ':' . (User::$me->is_guest ? '' : User::$me->id . '-') . $cachekey, $this->data, 240);
			}
		}

		return $this->data;
	}

	/**
	 * Emits the feed as an XML file.
	 */
	public function emit(): void
	{
		// Descriptive filenames = good
		$filename[] = $this->metadata['title'];
		$filename[] = $this->subaction;

		if (in_array($this->subaction, ['profile', 'posts', 'personal_messages'])) {
			$filename[] = 'u=' . $this->member;
		}

		if (!empty($this->boards)) {
			if (count($this->boards) > 1) {
				$filename[] = 'boards=' . implode(',', $this->boards);
			} else {
				$filename[] = 'board=' . reset($this->boards);
			}
		}

		$filename[] = $this->format;

		$filename = preg_replace(Utils::$context['utf8'] ? '/[^\p{L}\p{M}\p{N}\-]+/u' : '/[\s_,.\/\\;:\'<>?|\[\]{}~!@#$%^&*()=+`]+/', '_', str_replace('"', '', Utils::htmlspecialcharsDecode(strip_tags(implode('-', $filename)))));

		$file = [
			'filename' => $filename . '.xml',
			'mime_type' => self::MIME_TYPES[$this->format] . '; charset=' . (empty(Utils::$context['character_set']) ? 'UTF-8' : Utils::$context['character_set']),
			'content' => implode('', $this->xml),
			'disposition' => isset($_GET['download']) ? 'attachment' : 'inline',
		];

		if (isset($_GET['debug']) || (BrowserDetector::isBrowser('ie') && $this->format == 'rdf')) {
			$file['mime_type'] = str_replace(self::MIME_TYPES[$this->format], self::MIME_TYPES['smf'], $file['mime_type']);
		}

		Utils::emitFile($file);
	}

	/**
	 * Retrieve the list of members from database.
	 * The array will be generated to match $this->format.
	 *
	 * @todo get the list of members from Subs-Members.
	 *
	 * @return array An array of arrays of feed items. Each array has keys corresponding to the appropriate tags for the specified format.
	 */
	public function getXmlMembers(): array
	{
		if (!User::$me->allowedTo('view_mlist')) {
			return [];
		}

		Lang::load('Profile');

		// Find the most (or least) recent members.
		$data = [];

		$request = Db::$db->query(
			'',
			'SELECT id_member, member_name, real_name, date_registered, last_login
			FROM {db_prefix}members
			ORDER BY id_member {raw:ascdesc}
			LIMIT {int:limit}',
			[
				'limit' => $this->limit,
				'ascdesc' => !empty($this->ascending) ? 'ASC' : 'DESC',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// If any control characters slipped in somehow, kill the evil things
			$row = filter_var($row, FILTER_CALLBACK, ['options' => '\\SMF\\Utils::cleanXml']);

			// Create a GUID for each member using the tag URI scheme
			$guid = 'tag:' . $this->host . ',' . gmdate('Y-m-d', $row['date_registered']) . ':member=' . $row['id_member'];

			// Make the data look rss-ish.
			if ($this->format == 'rss' || $this->format == 'rss2') {
				$data[] = [
					'tag' => 'item',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['real_name'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
						],
						[
							'tag' => 'comments',
							'content' => Config::$scripturl . '?action=pm;sa=send;u=' . $row['id_member'],
						],
						[
							'tag' => 'pubDate',
							'content' => gmdate('D, d M Y H:i:s \\G\\M\\T', $row['date_registered']),
						],
						[
							'tag' => 'guid',
							'content' => $guid,
							'attributes' => [
								'isPermaLink' => 'false',
							],
						],
					],
				];
			} elseif ($this->format == 'rdf') {
				$data[] = [
					'tag' => 'item',
					'attributes' => ['rdf:about' => Config::$scripturl . '?action=profile;u=' . $row['id_member']],
					'content' => [
						[
							'tag' => 'dc:format',
							'content' => 'text/html',
						],
						[
							'tag' => 'title',
							'content' => $row['real_name'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
						],
					],
				];
			} elseif ($this->format == 'atom') {
				$data[] = [
					'tag' => 'entry',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['real_name'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'attributes' => [
								'rel' => 'alternate',
								'type' => 'text/html',
								'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
							],
						],
						[
							'tag' => 'published',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['date_registered']),
						],
						[
							'tag' => 'updated',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['last_login']),
						],
						[
							'tag' => 'id',
							'content' => $guid,
						],
					],
				];
			}
			// More logical format for the data, but harder to apply.
			else {
				$data[] = [
					'tag' => 'member',
					'attributes' => ['label' => Lang::$txt['who_member']],
					'content' => [
						[
							'tag' => 'name',
							'attributes' => ['label' => Lang::$txt['name']],
							'content' => $row['real_name'],
							'cdata' => true,
						],
						[
							'tag' => 'time',
							'attributes' => ['label' => Lang::$txt['date_registered'], 'UTC' => Time::gmstrftime('%F %T', $row['date_registered'])],
							'content' => Utils::htmlspecialchars(strip_tags(Time::create('@' . $row['date_registered'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false))),
						],
						[
							'tag' => 'id',
							'content' => $row['id_member'],
						],
						[
							'tag' => 'link',
							'attributes' => ['label' => Lang::$txt['url']],
							'content' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
						],
					],
				];
			}
		}
		Db::$db->free_result($request);

		return $data;
	}

	/**
	 * Get the latest topics information from a specific board,
	 * to display later.
	 * The returned array will be generated to match the xml_format.
	 *
	 * @return array An array of arrays of topic data for the feed. Each array has keys corresponding to the tags for the specified format.
	 */
	public function getXmlNews(): array
	{
		/* Find the latest (or earliest) posts that:
			- are the first post in their topic.
			- are on an any board OR in a specified board.
			- can be seen by this user. */

		$data = [];

		$done = false;
		$loops = 0;

		while (!$done) {
			$optimize_msg = implode(' AND ', $this->optimize_msg);
			$request = Db::$db->query(
				'',
				'SELECT
					m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.modified_time,
					m.icon, t.id_topic, t.id_board, t.num_replies,
					b.name AS bname,
					COALESCE(mem.id_member, 0) AS id_member,
					COALESCE(mem.email_address, m.poster_email) AS poster_email,
					COALESCE(mem.real_name, m.poster_name) AS poster_name
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
					INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
				WHERE ' . $this->query_this_board . (empty($optimize_msg) ? '' : '
					AND {raw:optimize_msg}') . (empty(Board::$info->id) ? '' : '
					AND t.id_board = {int:current_board}') . (Config::$modSettings['postmod_active'] ? '
					AND t.approved = {int:is_approved}' : '') . '
				ORDER BY t.id_first_msg {raw:ascdesc}
				LIMIT {int:limit}',
				[
					'current_board' => Board::$info->id,
					'is_approved' => 1,
					'limit' => $this->limit,
					'optimize_msg' => $optimize_msg,
					'ascdesc' => !empty($this->ascending) ? 'ASC' : 'DESC',
				],
			);

			// If we don't have $this->limit results, try again with an unoptimized version covering all rows.
			if ($loops < 2 && Db::$db->num_rows($request) < $this->limit) {
				Db::$db->free_result($request);

				if (empty($_GET['boards']) && empty(Board::$info->id)) {
					unset($this->optimize_msg['lowest']);
				} else {
					$this->optimize_msg['lowest'] = 'm.id_msg >= t.id_first_msg';
				}

				$this->optimize_msg['highest'] = 'm.id_msg <= t.id_last_msg';
				$loops++;
			} else {
				$done = true;
			}
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			// If any control characters slipped in somehow, kill the evil things
			$row = filter_var($row, FILTER_CALLBACK, ['options' => '\\SMF\\Utils::cleanXml']);

			// Limit the length of the message, if the option is set.
			if (!empty(Config::$modSettings['xmlnews_maxlen']) && Utils::entityStrlen(str_replace('<br>', "\n", $row['body'])) > Config::$modSettings['xmlnews_maxlen']) {
				$row['body'] = strtr(Utils::entitySubstr(str_replace('<br>', "\n", $row['body']), 0, Config::$modSettings['xmlnews_maxlen'] - 3), ["\n" => '<br>']) . '...';
			}

			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			Lang::censorText($row['body']);
			Lang::censorText($row['subject']);

			// Do we want to include any attachments?
			if (!empty(Config::$modSettings['attachmentEnable']) && !empty(Config::$modSettings['xmlnews_attachments']) && User::$me->allowedTo('view_attachments', $row['id_board'])) {
				$loaded_attachments = Attachment::loadByMsg($row['id_msg'], Attachment::APPROVED_TRUE);

				// Sort the attachments by size to make things easier below
				if (!empty($loaded_attachments)) {
					uasort(
						$loaded_attachments,
						function ($a, $b) {
							if ($a->size == $b->size) {
								return 0;
							}

							return ($a->size < $b->size) ? -1 : 1;
						},
					);
				} else {
					$loaded_attachments = null;
				}
			} else {
				$loaded_attachments = null;
			}

			// Create a GUID for this topic using the tag URI scheme
			$guid = 'tag:' . $this->host . ',' . gmdate('Y-m-d', $row['poster_time']) . ':topic=' . $row['id_topic'];

			// Being news, this actually makes sense in rss format.
			if ($this->format == 'rss' || $this->format == 'rss2') {
				// Only one attachment allowed in RSS.
				if ($loaded_attachments !== null) {
					$attachment = array_pop($loaded_attachments);
					$enclosure = [
						'url' => self::fixPossibleUrl(Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id),
						'length' => $attachment->size,
						'type' => $attachment->mime_type,
					];
				} else {
					$enclosure = null;
				}

				$data[] = [
					'tag' => 'item',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'author',
							'content' => (User::$me->allowedTo('moderate_forum') || $row['id_member'] == User::$me->id) ? $row['poster_email'] . ' (' . $row['poster_name'] . ')' : null,
							'cdata' => true,
						],
						[
							'tag' => 'comments',
							'content' => Config::$scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
						],
						[
							'tag' => 'category',
							'content' => $row['bname'],
							'cdata' => true,
						],
						[
							'tag' => 'pubDate',
							'content' => gmdate('D, d M Y H:i:s \\G\\M\\T', $row['poster_time']),
						],
						[
							'tag' => 'guid',
							'content' => $guid,
							'attributes' => [
								'isPermaLink' => 'false',
							],
						],
						[
							'tag' => 'enclosure',
							'attributes' => $enclosure,
						],
					],
				];
			} elseif ($this->format == 'rdf') {
				$data[] = [
					'tag' => 'item',
					'attributes' => ['rdf:about' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0'],
					'content' => [
						[
							'tag' => 'dc:format',
							'content' => 'text/html',
						],
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
					],
				];
			} elseif ($this->format == 'atom') {
				// Only one attachment allowed
				if (!empty($loaded_attachments)) {
					$attachment = array_pop($loaded_attachments);
					$enclosure = [
						'rel' => 'enclosure',
						'href' => self::fixPossibleUrl(Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id),
						'length' => $attachment->size,
						'type' => $attachment->mime_type,
					];
				} else {
					$enclosure = null;
				}

				$data[] = [
					'tag' => 'entry',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'attributes' => [
								'rel' => 'alternate',
								'type' => 'text/html',
								'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
							],
						],
						[
							'tag' => 'summary',
							'attributes' => ['type' => 'html'],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'category',
							'attributes' => ['term' => $row['bname']],
							'cdata' => true,
						],
						[
							'tag' => 'author',
							'content' => [
								[
									'tag' => 'name',
									'content' => $row['poster_name'],
									'cdata' => true,
								],
								[
									'tag' => 'email',
									'content' => (User::$me->allowedTo('moderate_forum') || $row['id_member'] == User::$me->id) ? $row['poster_email'] : null,
									'cdata' => true,
								],
								[
									'tag' => 'uri',
									'content' => !empty($row['id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : null,
								],
							],
						],
						[
							'tag' => 'published',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
						],
						[
							'tag' => 'updated',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
						],
						[
							'tag' => 'id',
							'content' => $guid,
						],
						[
							'tag' => 'link',
							'attributes' => $enclosure,
						],
					],
				];
			}
			// The biggest difference here is more information.
			else {
				Lang::load('Post');

				$attachments = [];

				if (!empty($loaded_attachments)) {
					foreach ($loaded_attachments as $attachment) {
						$attachments[] = [
							'tag' => 'attachment',
							'attributes' => ['label' => Lang::$txt['attachment']],
							'content' => [
								[
									'tag' => 'id',
									'content' => $attachment->id,
								],
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\1;', $attachment->name),
								],
								[
									'tag' => 'downloads',
									'attributes' => ['label' => Lang::$txt['downloads']],
									'content' => $attachment->downloads,
								],
								[
									'tag' => 'size',
									'attributes' => ['label' => Lang::$txt['filesize']],
									'content' => ($attachment->size < 1024000) ? round($attachment->size / 1024, 2) . ' ' . Lang::$txt['kilobyte'] : round($attachment->size / 1024 / 1024, 2) . ' ' . Lang::$txt['megabyte'],
								],
								[
									'tag' => 'byte_size',
									'attributes' => ['label' => Lang::$txt['filesize']],
									'content' => $attachment->size,
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id,
								],
							],
						];
					}
				} else {
					$attachments = null;
				}

				$data[] = [
					'tag' => 'article',
					'attributes' => ['label' => Lang::$txt['news']],
					'content' => [
						[
							'tag' => 'time',
							'attributes' => ['label' => Lang::$txt['date'], 'UTC' => Time::gmstrftime('%F %T', $row['poster_time'])],
							'content' => Utils::htmlspecialchars(strip_tags(Time::create('@' . $row['poster_time'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false))),
						],
						[
							'tag' => 'id',
							'content' => $row['id_topic'],
						],
						[
							'tag' => 'subject',
							'attributes' => ['label' => Lang::$txt['subject']],
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'body',
							'attributes' => ['label' => Lang::$txt['message']],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'poster',
							'attributes' => ['label' => Lang::$txt['author']],
							'content' => [
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => $row['poster_name'],
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_member'],
								],
								[
									'tag' => 'link',
									'attributes' => !empty($row['id_member']) ? ['label' => Lang::$txt['url']] : null,
									'content' => !empty($row['id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : '',
								],
							],
						],
						[
							'tag' => 'topic',
							'attributes' => ['label' => Lang::$txt['topic']],
							'content' => $row['id_topic'],
						],
						[
							'tag' => 'board',
							'attributes' => ['label' => Lang::$txt['board']],
							'content' => [
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => $row['bname'],
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_board'],
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
								],
							],
						],
						[
							'tag' => 'link',
							'attributes' => ['label' => Lang::$txt['url']],
							'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
						],
						[
							'tag' => 'attachments',
							'attributes' => ['label' => Lang::$txt['attachments']],
							'content' => $attachments,
						],
					],
				];
			}
		}
		Db::$db->free_result($request);

		return $data;
	}

	/**
	 * Get the recent topics to display.
	 * The returned array will be generated to match the xml_format.
	 *
	 * @return array An array of arrays containing data for the feed. Each array has keys corresponding to the appropriate tags for the specified format.
	 */
	public function getXmlRecent(): array
	{
		$data = [];
		$messages = [];

		$done = false;
		$loops = 0;
		$current_board = isset(Board::$info) ? Board::$info->id : 0;

		while (!$done) {
			$optimize_msg = implode(' AND ', $this->optimize_msg);
			$request = Db::$db->query(
				'',
				'SELECT m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (m.id_board = b.id_board)
					INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
				WHERE ' . $this->query_this_board . (empty($optimize_msg) ? '' : '
					AND {raw:optimize_msg}') . (empty(Board::$info->id) ? '' : '
					AND m.id_board = {int:current_board}') . (Config::$modSettings['postmod_active'] ? '
					AND m.approved = {int:is_approved}
					AND t.approved = {int:is_approved}' : '') . '
				ORDER BY m.id_msg DESC
				LIMIT {int:limit}',
				[
					'limit' => $this->limit,
					'current_board' => $current_board,
					'is_approved' => 1,
					'optimize_msg' => $optimize_msg,
				],
			);

			// If we don't have $this->limit results, try again with an unoptimized version covering all rows.
			if ($loops < 2 && Db::$db->num_rows($request) < $this->limit) {
				Db::$db->free_result($request);

				if (empty($_GET['boards']) && empty(Board::$info->id)) {
					unset($this->optimize_msg['lowest']);
				} else {
					$this->optimize_msg['lowest'] = $loops ? 'm.id_msg >= t.id_first_msg' : 'm.id_msg >= (t.id_last_msg - t.id_first_msg) / 2';
				}
				$loops++;
			} else {
				$done = true;
			}
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			$messages[] = $row['id_msg'];
		}
		Db::$db->free_result($request);

		if (empty($messages)) {
			return [];
		}

		// Find the most recent posts this user can see.
		$request = Db::$db->query(
			'',
			'SELECT
				m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.id_topic, t.id_board,
				b.name AS bname, t.num_replies, m.id_member, m.icon, mf.id_member AS id_first_member,
				COALESCE(mem.real_name, m.poster_name) AS poster_name, mf.subject AS first_subject,
				COALESCE(memf.real_name, mf.poster_name) AS first_poster_name,
				COALESCE(mem.email_address, m.poster_email) AS poster_email, m.modified_time
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (t.id_first_msg = mf.id_msg)
				INNER JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (mf.id_member = memf.id_member)
			WHERE m.id_msg IN ({array_int:message_list})
				' . (empty(Board::$info->id) ? '' : 'AND t.id_board = {int:current_board}') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			[
				'limit' => $this->limit,
				'current_board' => $current_board,
				'message_list' => $messages,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// If any control characters slipped in somehow, kill the evil things
			$row = filter_var($row, FILTER_CALLBACK, ['options' => '\\SMF\\Utils::cleanXml']);

			// Limit the length of the message, if the option is set.
			if (!empty(Config::$modSettings['xmlnews_maxlen']) && Utils::entityStrlen(str_replace('<br>', "\n", $row['body'])) > Config::$modSettings['xmlnews_maxlen']) {
				$row['body'] = strtr(Utils::entitySubstr(str_replace('<br>', "\n", $row['body']), 0, Config::$modSettings['xmlnews_maxlen'] - 3), ["\n" => '<br>']) . '...';
			}

			$row['body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			Lang::censorText($row['body']);
			Lang::censorText($row['subject']);

			// Do we want to include any attachments?
			if (!empty(Config::$modSettings['attachmentEnable']) && !empty(Config::$modSettings['xmlnews_attachments']) && User::$me->allowedTo('view_attachments', $row['id_board'])) {
				$loaded_attachments = Attachment::loadByMsg($row['id_msg'], Attachment::APPROVED_TRUE);

				// Sort the attachments by size to make things easier below
				if (!empty($loaded_attachments)) {
					uasort(
						$loaded_attachments,
						function ($a, $b) {
							if ($a->size == $b->size) {
								return 0;
							}

							return ($a->size < $b->size) ? -1 : 1;
						},
					);
				} else {
					$loaded_attachments = null;
				}
			} else {
				$loaded_attachments = null;
			}

			// Create a GUID for this post using the tag URI scheme
			$guid = 'tag:' . $this->host . ',' . gmdate('Y-m-d', $row['poster_time']) . ':msg=' . $row['id_msg'];

			// Doesn't work as well as news, but it kinda does..
			if ($this->format == 'rss' || $this->format == 'rss2') {
				// Only one attachment allowed in RSS.
				if ($loaded_attachments !== null) {
					$attachment = array_pop($loaded_attachments);
					$enclosure = [
						'url' => self::fixPossibleUrl(Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id),
						'length' => $attachment->size,
						'type' => $attachment->mime_type,
					];
				} else {
					$enclosure = null;
				}

				$data[] = [
					'tag' => 'item',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'author',
							'content' => (User::$me->allowedTo('moderate_forum') || (!empty($row['id_member']) && $row['id_member'] == User::$me->id)) ? $row['poster_email'] : null,
							'cdata' => true,
						],
						[
							'tag' => 'category',
							'content' => $row['bname'],
							'cdata' => true,
						],
						[
							'tag' => 'comments',
							'content' => Config::$scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
						],
						[
							'tag' => 'pubDate',
							'content' => gmdate('D, d M Y H:i:s \\G\\M\\T', $row['poster_time']),
						],
						[
							'tag' => 'guid',
							'content' => $guid,
							'attributes' => [
								'isPermaLink' => 'false',
							],
						],
						[
							'tag' => 'enclosure',
							'attributes' => $enclosure,
						],
					],
				];
			} elseif ($this->format == 'rdf') {
				$data[] = [
					'tag' => 'item',
					'attributes' => ['rdf:about' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']],
					'content' => [
						[
							'tag' => 'dc:format',
							'content' => 'text/html',
						],
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
					],
				];
			} elseif ($this->format == 'atom') {
				// Only one attachment allowed
				if (!empty($loaded_attachments)) {
					$attachment = array_pop($loaded_attachments);
					$enclosure = [
						'rel' => 'enclosure',
						'href' => self::fixPossibleUrl(Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id),
						'length' => $attachment->size,
						'type' => $attachment->mime_type,
					];
				} else {
					$enclosure = null;
				}

				$data[] = [
					'tag' => 'entry',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'attributes' => [
								'rel' => 'alternate',
								'type' => 'text/html',
								'href' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
							],
						],
						[
							'tag' => 'summary',
							'attributes' => ['type' => 'html'],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'category',
							'attributes' => ['term' => $row['bname']],
							'cdata' => true,
						],
						[
							'tag' => 'author',
							'content' => [
								[
									'tag' => 'name',
									'content' => $row['poster_name'],
									'cdata' => true,
								],
								[
									'tag' => 'email',
									'content' => (User::$me->allowedTo('moderate_forum') || (!empty($row['id_member']) && $row['id_member'] == User::$me->id)) ? $row['poster_email'] : null,
									'cdata' => true,
								],
								[
									'tag' => 'uri',
									'content' => !empty($row['id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : null,
								],
							],
						],
						[
							'tag' => 'published',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
						],
						[
							'tag' => 'updated',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
						],
						[
							'tag' => 'id',
							'content' => $guid,
						],
						[
							'tag' => 'link',
							'attributes' => $enclosure,
						],
					],
				];
			}
			// A lot of information here.  Should be enough to please the rss-ers.
			else {
				Lang::load('Post');

				$attachments = [];

				if (!empty($loaded_attachments)) {
					foreach ($loaded_attachments as $attachment) {
						$attachments[] = [
							'tag' => 'attachment',
							'attributes' => ['label' => Lang::$txt['attachment']],
							'content' => [
								[
									'tag' => 'id',
									'content' => $attachment->id,
								],
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\1;', $attachment->name),
								],
								[
									'tag' => 'downloads',
									'attributes' => ['label' => Lang::$txt['downloads']],
									'content' => $attachment->downloads,
								],
								[
									'tag' => 'size',
									'attributes' => ['label' => Lang::$txt['filesize']],
									'content' => ($attachment->size < 1024000) ? round($attachment->size / 1024, 2) . ' ' . Lang::$txt['kilobyte'] : round($attachment->size / 1024 / 1024, 2) . ' ' . Lang::$txt['megabyte'],
								],
								[
									'tag' => 'byte_size',
									'attributes' => ['label' => Lang::$txt['filesize']],
									'content' => $attachment->size,
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id,
								],
							],
						];
					}
				} else {
					$attachments = null;
				}

				$data[] = [
					'tag' => 'recent-post', // Hyphen rather than underscore for backward compatibility reasons
					'attributes' => ['label' => Lang::$txt['post']],
					'content' => [
						[
							'tag' => 'time',
							'attributes' => ['label' => Lang::$txt['date'], 'UTC' => Time::gmstrftime('%F %T', $row['poster_time'])],
							'content' => Utils::htmlspecialchars(strip_tags(Time::create('@' . $row['poster_time'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false))),
						],
						[
							'tag' => 'id',
							'content' => $row['id_msg'],
						],
						[
							'tag' => 'subject',
							'attributes' => ['label' => Lang::$txt['subject']],
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'body',
							'attributes' => ['label' => Lang::$txt['message']],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'starter',
							'attributes' => ['label' => Lang::$txt['topic_started']],
							'content' => [
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => $row['first_poster_name'],
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_first_member'],
								],
								[
									'tag' => 'link',
									'attributes' => !empty($row['id_first_member']) ? ['label' => Lang::$txt['url']] : null,
									'content' => !empty($row['id_first_member']) ? Config::$scripturl . '?action=profile;u=' . $row['id_first_member'] : '',
								],
							],
						],
						[
							'tag' => 'poster',
							'attributes' => ['label' => Lang::$txt['author']],
							'content' => [
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => $row['poster_name'],
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_member'],
								],
								[
									'tag' => 'link',
									'attributes' => !empty($row['id_member']) ? ['label' => Lang::$txt['url']] : null,
									'content' => !empty($row['id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : '',
								],
							],
						],
						[
							'tag' => 'topic',
							'attributes' => ['label' => Lang::$txt['topic']],
							'content' => [
								[
									'tag' => 'subject',
									'attributes' => ['label' => Lang::$txt['subject']],
									'content' => $row['first_subject'],
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_topic'],
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.new#new',
								],
							],
						],
						[
							'tag' => 'board',
							'attributes' => ['label' => Lang::$txt['board']],
							'content' => [
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => $row['bname'],
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_board'],
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
								],
							],
						],
						[
							'tag' => 'link',
							'attributes' => ['label' => Lang::$txt['url']],
							'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
						],
						[
							'tag' => 'attachments',
							'attributes' => ['label' => Lang::$txt['attachments']],
							'content' => $attachments,
						],
					],
				];
			}
		}
		Db::$db->free_result($request);

		return $data;
	}

	/**
	 * Get the profile information for member into an array,
	 * which will be generated to match the xml_format.
	 *
	 * @return array An array profile data
	 */
	public function getXmlProfile(): array
	{
		// You must input a valid user, and you must be allowed to view that user's profile.
		if (empty($this->member) || ($this->member != User::$me->id && !User::$me->allowedTo('profile_view')) || (User::load($this->member) === [])) {
			return [];
		}

		// Load the member's contextual information! (Including custom fields for our proprietary XML type)
		$profile = User::$loaded[$this->member]->format($this->format == 'smf');

		// If any control characters slipped in somehow, kill the evil things
		$profile = filter_var($profile, FILTER_CALLBACK, ['options' => '\\SMF\\Utils::cleanXml']);

		// Create a GUID for this member using the tag URI scheme
		$guid = 'tag:' . $this->host . ',' . gmdate('Y-m-d', $profile['registered_timestamp']) . ':member=' . $profile['id'];

		if ($this->format == 'rss' || $this->format == 'rss2') {
			$data[] = [
				'tag' => 'item',
				'content' => [
					[
						'tag' => 'title',
						'content' => $profile['name'],
						'cdata' => true,
					],
					[
						'tag' => 'link',
						'content' => Config::$scripturl . '?action=profile;u=' . $profile['id'],
					],
					[
						'tag' => 'description',
						'content' => $profile['group'] ?? $profile['post_group'],
						'cdata' => true,
					],
					[
						'tag' => 'comments',
						'content' => Config::$scripturl . '?action=pm;sa=send;u=' . $profile['id'],
					],
					[
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \\G\\M\\T', $profile['registered_timestamp']),
					],
					[
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => [
							'isPermaLink' => 'false',
						],
					],
				],
			];
		} elseif ($this->format == 'rdf') {
			$data[] = [
				'tag' => 'item',
				'attributes' => ['rdf:about' => Config::$scripturl . '?action=profile;u=' . $profile['id']],
				'content' => [
					[
						'tag' => 'dc:format',
						'content' => 'text/html',
					],
					[
						'tag' => 'title',
						'content' => $profile['name'],
						'cdata' => true,
					],
					[
						'tag' => 'link',
						'content' => Config::$scripturl . '?action=profile;u=' . $profile['id'],
					],
					[
						'tag' => 'description',
						'content' => $profile['group'] ?? $profile['post_group'],
						'cdata' => true,
					],
				],
			];
		} elseif ($this->format == 'atom') {
			$data[] = [
				'tag' => 'entry',
				'content' => [
					[
						'tag' => 'title',
						'content' => $profile['name'],
						'cdata' => true,
					],
					[
						'tag' => 'link',
						'attributes' => [
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => Config::$scripturl . '?action=profile;u=' . $profile['id'],
						],
					],
					[
						'tag' => 'summary',
						'attributes' => ['type' => 'html'],
						'content' => $profile['group'] ?? $profile['post_group'],
						'cdata' => true,
					],
					[
						'tag' => 'author',
						'content' => [
							[
								'tag' => 'name',
								'content' => $profile['name'],
								'cdata' => true,
							],
							[
								'tag' => 'email',
								'content' => $profile['show_email'] ? $profile['email'] : null,
								'cdata' => true,
							],
							[
								'tag' => 'uri',
								'content' => !empty($profile['website']['url']) ? $profile['website']['url'] : Config::$scripturl . '?action=profile;u=' . $profile['id_member'],
								'cdata' => !empty($profile['website']['url']),
							],
						],
					],
					[
						'tag' => 'published',
						'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $profile['registered_timestamp']),
					],
					[
						'tag' => 'updated',
						'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $profile['last_login_timestamp']),
					],
					[
						'tag' => 'id',
						'content' => $guid,
					],
				],
			];
		} else {
			Lang::load('Profile');

			$data = [
				[
					'tag' => 'username',
					'attributes' => User::$me->is_admin || User::$me->id == $profile['id'] ? ['label' => Lang::$txt['username']] : null,
					'content' => User::$me->is_admin || User::$me->id == $profile['id'] ? $profile['username'] : null,
					'cdata' => true,
				],
				[
					'tag' => 'name',
					'attributes' => ['label' => Lang::$txt['name']],
					'content' => $profile['name'],
					'cdata' => true,
				],
				[
					'tag' => 'link',
					'attributes' => ['label' => Lang::$txt['url']],
					'content' => Config::$scripturl . '?action=profile;u=' . $profile['id'],
				],
				[
					'tag' => 'posts',
					'attributes' => ['label' => Lang::$txt['member_postcount']],
					'content' => $profile['posts'],
				],
				[
					'tag' => 'post-group',
					'attributes' => ['label' => Lang::$txt['post_based_membergroup']],
					'content' => $profile['post_group'],
					'cdata' => true,
				],
				[
					'tag' => 'language',
					'attributes' => ['label' => Lang::$txt['preferred_language']],
					'content' => $profile['language'],
					'cdata' => true,
				],
				[
					'tag' => 'last-login',
					'attributes' => ['label' => Lang::$txt['lastLoggedIn'], 'UTC' => Time::gmstrftime('%F %T', $profile['last_login_timestamp'])],
					'content' => Time::create('@' . $row['last_login_timestamp'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false),
				],
				[
					'tag' => 'registered',
					'attributes' => ['label' => Lang::$txt['date_registered'], 'UTC' => Time::gmstrftime('%F %T', $profile['registered_timestamp'])],
					'content' => Time::create('@' . $row['registered_timestamp'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false),
				],
				[
					'tag' => 'avatar',
					'attributes' => !empty($profile['avatar']['url']) ? ['label' => Lang::$txt['personal_picture']] : null,
					'content' => !empty($profile['avatar']['url']) ? $profile['avatar']['url'] : null,
					'cdata' => true,
				],
				[
					'tag' => 'signature',
					'attributes' => !empty($profile['signature']) ? ['label' => Lang::$txt['signature']] : null,
					'content' => !empty($profile['signature']) ? $profile['signature'] : null,
					'cdata' => true,
				],
				[
					'tag' => 'blurb',
					'attributes' => !empty($profile['blurb']) ? ['label' => Lang::$txt['personal_text']] : null,
					'content' => !empty($profile['blurb']) ? $profile['blurb'] : null,
					'cdata' => true,
				],
				[
					'tag' => 'title',
					'attributes' => !empty($profile['title']) ? ['label' => Lang::$txt['title']] : null,
					'content' => !empty($profile['title']) ? $profile['title'] : null,
					'cdata' => true,
				],
				[
					'tag' => 'position',
					'attributes' => !empty($profile['group']) ? ['label' => Lang::$txt['position']] : null,
					'content' => !empty($profile['group']) ? $profile['group'] : null,
					'cdata' => true,
				],
				[
					'tag' => 'email',
					'attributes' => !empty($profile['show_email']) || User::$me->is_admin || User::$me->id == $profile['id'] ? ['label' => Lang::$txt['user_email_address']] : null,
					'content' => !empty($profile['show_email']) || User::$me->is_admin || User::$me->id == $profile['id'] ? $profile['email'] : null,
					'cdata' => true,
				],
				[
					'tag' => 'website',
					'attributes' => empty($profile['website']['url']) ? null : ['label' => Lang::$txt['website']],
					'content' => empty($profile['website']['url']) ? null : [
						[
							'tag' => 'title',
							'attributes' => !empty($profile['website']['title']) ? ['label' => Lang::$txt['website_title']] : null,
							'content' => !empty($profile['website']['title']) ? $profile['website']['title'] : null,
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'attributes' => ['label' => Lang::$txt['website_url']],
							'content' => $profile['website']['url'],
							'cdata' => true,
						],
					],
				],
				[
					'tag' => 'online',
					'attributes' => !empty($profile['online']['is_online']) ? ['label' => Lang::$txt['online']] : null,
					'content' => !empty($profile['online']['is_online']) ? 'true' : null,
				],
				[
					'tag' => 'ip_addresses',
					'attributes' => ['label' => Lang::$txt['ip_address']],
					'content' => User::$me->allowedTo('moderate_forum') || User::$me->id == $profile['id'] ? [
						[
							'tag' => 'ip',
							'attributes' => ['label' => Lang::$txt['most_recent_ip']],
							'content' => $profile['ip'],
						],
						[
							'tag' => 'ip2',
							'content' => $profile['ip'] != $profile['ip2'] ? $profile['ip2'] : null,
						],
					] : null,
				],
			];

			if (!empty($profile['birth_date']) && substr($profile['birth_date'], 0, 4) != '0000' && substr($profile['birth_date'], 0, 4) != '1004') {
				list($birth_year, $birth_month, $birth_day) = sscanf($profile['birth_date'], '%d-%d-%d');

				$datearray = getdate(time());

				$age = $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1);

				$data[] = [
					'tag' => 'age',
					'attributes' => ['label' => Lang::$txt['age']],
					'content' => $age,
				];
				$data[] = [
					'tag' => 'birthdate',
					'attributes' => ['label' => Lang::$txt['dob']],
					'content' => $profile['birth_date'],
				];
			}

			if (!empty($profile['custom_fields'])) {
				foreach ($profile['custom_fields'] as $custom_field) {
					$data[] = [
						'tag' => $custom_field['col_name'],
						'attributes' => ['label' => $custom_field['title']],
						'content' => $custom_field['simple'],
						'cdata' => true,
					];
				}
			}
		}

		// Save some memory.
		unset($profile);

		return $data;
	}

	/**
	 * Get a user's posts.
	 * The returned array will be generated to match the xml_format.
	 *
	 * @return array An array of arrays containing data for the feed. Each array has keys corresponding to the appropriate tags for the specified format.
	 */
	public function getXmlPosts(): array
	{
		if (empty($this->member) || ($this->member != User::$me->id && !User::$me->allowedTo('profile_view'))) {
			return [];
		}

		$data = [];

		$show_all = !empty(User::$me->is_admin) || defined('EXPORTING');

		$query_this_message_board = str_replace(['{query_see_board}', 'b.'], ['{query_see_message_board}', 'm.'], $this->query_this_board);

		/* MySQL can choke if we use joins in the main query when the user has
		 * massively long posts. To avoid that, we get the names of the boards
		 * and the user's displayed name in separate queries.
		 */
		$boardnames = [];
		$request = Db::$db->query(
			'',
			'SELECT id_board, name
			FROM {db_prefix}boards',
			[],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$boardnames[$row['id_board']] = $row['name'];
		}
		Db::$db->free_result($request);

		if ($this->member == User::$me->id) {
			$poster_name = User::$me->name;
		} else {
			$request = Db::$db->query(
				'',
				'SELECT COALESCE(real_name, member_name) AS poster_name
				FROM {db_prefix}members
				WHERE id_member = {int:uid}',
				[
					'uid' => $this->member,
				],
			);
			list($poster_name) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		$request = Db::$db->query(
			'',
			'SELECT
				m.id_msg, m.id_topic, m.id_board, m.id_member, m.poster_email, m.poster_ip,
				m.poster_time, m.subject, m.modified_time, m.modified_name, m.modified_reason, m.body,
				m.likes, m.approved, m.smileys_enabled
			FROM {db_prefix}messages AS m' . (Config::$modSettings['postmod_active'] && !$show_all ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' : '') . '
			WHERE m.id_member = {int:uid}
				AND m.id_msg > {int:start_after}
				AND ' . $query_this_message_board . (Config::$modSettings['postmod_active'] && !$show_all ? '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg {raw:ascdesc}
			LIMIT {int:limit}',
			[
				'limit' => $this->limit,
				'start_after' => $this->start_after,
				'uid' => $this->member,
				'is_approved' => 1,
				'ascdesc' => !empty($this->ascending) ? 'ASC' : 'DESC',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			Utils::$context['last'] = $row['id_msg'];

			// We want a readable version of the IP address
			$row['poster_ip'] = new IP($row['poster_ip']);

			// If any control characters slipped in somehow, kill the evil things
			$row = filter_var($row, FILTER_CALLBACK, ['options' => '\\SMF\\Utils::cleanXml']);

			// If using our own format, we want both the raw and the parsed content.
			$row[$this->format === 'smf' ? 'body_html' : 'body'] = BBCodeParser::load()->parse($row['body'], $row['smileys_enabled'], $row['id_msg']);

			// Do we want to include any attachments?
			if (!empty(Config::$modSettings['attachmentEnable']) && !empty(Config::$modSettings['xmlnews_attachments'])) {
				$loaded_attachments = Attachment::loadByMsg($row['id_msg'], Attachment::APPROVED_TRUE);

				// Sort the attachments by size to make things easier below
				if (!empty($loaded_attachments)) {
					uasort(
						$loaded_attachments,
						function ($a, $b) {
							if ($a->size == $b->size) {
								return 0;
							}

							return ($a->size < $b->size) ? -1 : 1;
						},
					);
				} else {
					$loaded_attachments = null;
				}
			} else {
				$loaded_attachments = null;
			}

			// Create a GUID for this post using the tag URI scheme
			$guid = 'tag:' . $this->host . ',' . gmdate('Y-m-d', $row['poster_time']) . ':msg=' . $row['id_msg'];

			if ($this->format == 'rss' || $this->format == 'rss2') {
				// Only one attachment allowed in RSS.
				if ($loaded_attachments !== null) {
					$attachment = array_pop($loaded_attachments);
					$enclosure = [
						'url' => self::fixPossibleUrl(Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id),
						'length' => $attachment->size,
						'type' => $attachment->mime_type,
					];
				} else {
					$enclosure = null;
				}

				$data[] = [
					'tag' => 'item',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?msg=' . $row['id_msg'],
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'author',
							'content' => (User::$me->allowedTo('moderate_forum') || ($row['id_member'] == User::$me->id)) ? $row['poster_email'] : null,
							'cdata' => true,
						],
						[
							'tag' => 'category',
							'content' => $boardnames[$row['id_board']],
							'cdata' => true,
						],
						[
							'tag' => 'comments',
							'content' => Config::$scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
						],
						[
							'tag' => 'pubDate',
							'content' => gmdate('D, d M Y H:i:s \\G\\M\\T', $row['poster_time']),
						],
						[
							'tag' => 'guid',
							'content' => $guid,
							'attributes' => [
								'isPermaLink' => 'false',
							],
						],
						[
							'tag' => 'enclosure',
							'attributes' => $enclosure,
						],
					],
				];
			} elseif ($this->format == 'rdf') {
				$data[] = [
					'tag' => 'item',
					'attributes' => ['rdf:about' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']],
					'content' => [
						[
							'tag' => 'dc:format',
							'content' => 'text/html',
						],
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?msg=' . $row['id_msg'],
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
					],
				];
			} elseif ($this->format == 'atom') {
				// Only one attachment allowed
				if (!empty($loaded_attachments)) {
					$attachment = array_pop($loaded_attachments);
					$enclosure = [
						'rel' => 'enclosure',
						'href' => self::fixPossibleUrl(Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id),
						'length' => $attachment->size,
						'type' => $attachment->mime_type,
					];
				} else {
					$enclosure = null;
				}

				$data[] = [
					'tag' => 'entry',
					'content' => [
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'attributes' => [
								'rel' => 'alternate',
								'type' => 'text/html',
								'href' => Config::$scripturl . '?msg=' . $row['id_msg'],
							],
						],
						[
							'tag' => 'summary',
							'attributes' => ['type' => 'html'],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'author',
							'content' => [
								[
									'tag' => 'name',
									'content' => $poster_name,
									'cdata' => true,
								],
								[
									'tag' => 'email',
									'content' => (User::$me->allowedTo('moderate_forum') || ($row['id_member'] == User::$me->id)) ? $row['poster_email'] : null,
									'cdata' => true,
								],
								[
									'tag' => 'uri',
									'content' => !empty($row['id_member']) ? Config::$scripturl . '?action=profile;u=' . $row['id_member'] : null,
								],
							],
						],
						[
							'tag' => 'published',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
						],
						[
							'tag' => 'updated',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
						],
						[
							'tag' => 'id',
							'content' => $guid,
						],
						[
							'tag' => 'link',
							'attributes' => $enclosure,
						],
					],
				];
			}
			// A lot of information here.  Should be enough to please the rss-ers.
			else {
				Lang::load('Post');

				$attachments = [];

				if (!empty($loaded_attachments)) {
					foreach ($loaded_attachments as $attachment) {
						$attachments[] = [
							'tag' => 'attachment',
							'attributes' => ['label' => Lang::$txt['attachment']],
							'content' => [
								[
									'tag' => 'id',
									'content' => $attachment->id,
								],
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => preg_replace('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\1;', $attachment->name),
								],
								[
									'tag' => 'downloads',
									'attributes' => ['label' => Lang::$txt['downloads']],
									'content' => $attachment->downloads,
								],
								[
									'tag' => 'size',
									'attributes' => ['label' => Lang::$txt['filesize']],
									'content' => ($attachment->size < 1024000) ? round($attachment->size / 1024, 2) . ' ' . Lang::$txt['kilobyte'] : round($attachment->size / 1024 / 1024, 2) . ' ' . Lang::$txt['megabyte'],
								],
								[
									'tag' => 'byte_size',
									'attributes' => ['label' => Lang::$txt['filesize']],
									'content' => $attachment->size,
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?action=dlattach;topic=' . $attachment->topic . '.0;attach=' . $attachment->id,
								],
								[
									'tag' => 'approval_status',
									'attributes' => $show_all ? ['label' => Lang::$txt['approval_status']] : null,
									'content' => $show_all ? $attachment->approved : null,
								],
							],
						];
					}
				} else {
					$attachments = null;
				}

				$data[] = [
					'tag' => 'member_post',
					'attributes' => ['label' => Lang::$txt['post']],
					'content' => [
						[
							'tag' => 'id',
							'content' => $row['id_msg'],
						],
						[
							'tag' => 'subject',
							'attributes' => ['label' => Lang::$txt['subject']],
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'body',
							'attributes' => ['label' => Lang::$txt['message']],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'body_html',
							'attributes' => ['label' => Lang::$txt['html']],
							'content' => $row['body_html'],
							'cdata' => true,
						],
						[
							'tag' => 'poster',
							'attributes' => ['label' => Lang::$txt['author']],
							'content' => [
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => $poster_name,
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_member'],
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
								],
								[
									'tag' => 'email',
									'attributes' => (User::$me->allowedTo('moderate_forum') || $row['id_member'] == User::$me->id) ? ['label' => Lang::$txt['user_email_address']] : null,
									'content' => (User::$me->allowedTo('moderate_forum') || $row['id_member'] == User::$me->id) ? $row['poster_email'] : null,
									'cdata' => true,
								],
								[
									'tag' => 'ip',
									'attributes' => (User::$me->allowedTo('moderate_forum') || $row['id_member'] == User::$me->id) ? ['label' => Lang::$txt['ip']] : null,
									'content' => (User::$me->allowedTo('moderate_forum') || $row['id_member'] == User::$me->id) ? $row['poster_ip'] : null,
								],
							],
						],
						[
							'tag' => 'topic',
							'attributes' => ['label' => Lang::$txt['topic']],
							'content' => [
								[
									'tag' => 'id',
									'content' => $row['id_topic'],
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?topic=' . $row['id_topic'] . '.0',
								],
							],
						],
						[
							'tag' => 'board',
							'attributes' => ['label' => Lang::$txt['board']],
							'content' => [
								[
									'tag' => 'id',
									'content' => $row['id_board'],
								],
								[
									'tag' => 'name',
									'content' => $boardnames[$row['id_board']],
									'cdata' => true,
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?board=' . $row['id_board'] . '.0',
								],
							],
						],
						[
							'tag' => 'link',
							'attributes' => ['label' => Lang::$txt['url']],
							'content' => Config::$scripturl . '?msg=' . $row['id_msg'],
						],
						[
							'tag' => 'time',
							'attributes' => ['label' => Lang::$txt['date'], 'UTC' => Time::gmstrftime('%F %T', $row['poster_time'])],
							'content' => Utils::htmlspecialchars(strip_tags(Time::create('@' . $row['poster_time'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false))),
						],
						[
							'tag' => 'modified_time',
							'attributes' => !empty($row['modified_time']) ? ['label' => Lang::$txt['modified_time'], 'UTC' => Time::gmstrftime('%F %T', $row['modified_time'])] : null,
							'content' => !empty($row['modified_time']) ? Utils::htmlspecialchars(strip_tags(Time::create('@' . $row['modified_time'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false))) : null,
						],
						[
							'tag' => 'modified_by',
							'attributes' => !empty($row['modified_name']) ? ['label' => Lang::$txt['modified_by']] : null,
							'content' => !empty($row['modified_name']) ? $row['modified_name'] : null,
							'cdata' => true,
						],
						[
							'tag' => 'modified_reason',
							'attributes' => !empty($row['modified_reason']) ? ['label' => Lang::$txt['reason_for_edit']] : null,
							'content' => !empty($row['modified_reason']) ? $row['modified_reason'] : null,
							'cdata' => true,
						],
						[
							'tag' => 'likes',
							'attributes' => ['label' => Lang::$txt['likes']],
							'content' => $row['likes'],
						],
						[
							'tag' => 'approval_status',
							'attributes' => $show_all ? ['label' => Lang::$txt['approval_status']] : null,
							'content' => $show_all ? $row['approved'] : null,
						],
						[
							'tag' => 'attachments',
							'attributes' => ['label' => Lang::$txt['attachments']],
							'content' => $attachments,
						],
					],
				];
			}
		}
		Db::$db->free_result($request);

		return $data;
	}

	/**
	 * Get a user's personal messages.
	 * Only the user can do this, and no one else -- not even the admin!
	 *
	 * @return array An array of arrays containing data for the feed. Each array has keys corresponding to the appropriate tags for the specified format.
	 */
	public function getXmlPMs(): array
	{
		// Personal messages are supposed to be private
		if (empty($this->member) || ($this->member != User::$me->id)) {
			return [];
		}

		$data = [];

		// Use a private-use Unicode character to separate member names.
		// This ensures that the separator will not occur in the names themselves.
		$separator = "\xEE\x88\xA0";

		$select_id_members_to = Db::$db->title === POSTGRE_TITLE ? "string_agg(pmr.id_member::text, ',')" : 'GROUP_CONCAT(pmr.id_member)';

		$select_to_names = Db::$db->title === POSTGRE_TITLE ? "string_agg(COALESCE(mem.real_name, mem.member_name), '{$separator}')" : "GROUP_CONCAT(COALESCE(mem.real_name, mem.member_name) SEPARATOR '{$separator}')";

		$request = Db::$db->query(
			'',
			'SELECT pm.id_pm, pm.msgtime, pm.subject, pm.body, pm.id_member_from, nis.from_name, nis.id_members_to, nis.to_names
			FROM {db_prefix}personal_messages AS pm
			INNER JOIN
			(
				SELECT pm2.id_pm, COALESCE(memf.real_name, pm2.from_name) AS from_name, ' . $select_id_members_to . ' AS id_members_to, ' . $select_to_names . ' AS to_names
				FROM {db_prefix}personal_messages AS pm2
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm2.id_pm = pmr.id_pm)
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
					LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = pm2.id_member_from)
				WHERE pm2.id_pm > {int:start_after}
					AND (
						(pm2.id_member_from = {int:uid} AND pm2.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})
					)
				GROUP BY pm2.id_pm, COALESCE(memf.real_name, pm2.from_name)
				ORDER BY pm2.id_pm {raw:ascdesc}
				LIMIT {int:limit}
			) AS nis ON pm.id_pm = nis.id_pm
			ORDER BY pm.id_pm {raw:ascdesc}',
			[
				'limit' => $this->limit,
				'start_after' => $this->start_after,
				'uid' => $this->member,
				'not_deleted' => 0,
				'ascdesc' => !empty($this->ascending) ? 'ASC' : 'DESC',
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$this->start_after = $row['id_pm'];

			// If any control characters slipped in somehow, kill the evil things
			$row = filter_var($row, FILTER_CALLBACK, ['options' => '\\SMF\\Utils::cleanXml']);

			// If using our own format, we want both the raw and the parsed content.
			$row[$this->format === 'smf' ? 'body_html' : 'body'] = BBCodeParser::load()->parse($row['body']);

			$recipients = array_combine(explode(',', $row['id_members_to']), explode($separator, $row['to_names']));

			// Create a GUID for this post using the tag URI scheme
			$guid = 'tag:' . $this->host . ',' . gmdate('Y-m-d', $row['msgtime']) . ':pm=' . $row['id_pm'];

			if ($this->format == 'rss' || $this->format == 'rss2') {
				$item = [
					'tag' => 'item',
					'content' => [
						[
							'tag' => 'guid',
							'content' => $guid,
							'attributes' => [
								'isPermaLink' => 'false',
							],
						],
						[
							'tag' => 'pubDate',
							'content' => gmdate('D, d M Y H:i:s \\G\\M\\T', $row['msgtime']),
						],
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'smf:sender',
							// This technically violates the RSS spec, but meh...
							'content' => $row['from_name'],
							'cdata' => true,
						],
					],
				];

				foreach ($recipients as $recipient_id => $recipient_name) {
					$item['content'][] = [
						'tag' => 'smf:recipient',
						'content' => $recipient_name,
						'cdata' => true,
					];
				}

				$data[] = $item;
			} elseif ($this->format == 'rdf') {
				$data[] = [
					'tag' => 'item',
					'attributes' => ['rdf:about' => Config::$scripturl . '?action=pm#msg' . $row['id_pm']],
					'content' => [
						[
							'tag' => 'dc:format',
							'content' => 'text/html',
						],
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'link',
							'content' => Config::$scripturl . '?action=pm#msg' . $row['id_pm'],
						],
						[
							'tag' => 'description',
							'content' => $row['body'],
							'cdata' => true,
						],
					],
				];
			} elseif ($this->format == 'atom') {
				$item = [
					'tag' => 'entry',
					'content' => [
						[
							'tag' => 'id',
							'content' => $guid,
						],
						[
							'tag' => 'updated',
							'content' => Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['msgtime']),
						],
						[
							'tag' => 'title',
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'content',
							'attributes' => ['type' => 'html'],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'author',
							'content' => [
								[
									'tag' => 'name',
									'content' => $row['from_name'],
									'cdata' => true,
								],
							],
						],
					],
				];

				foreach ($recipients as $recipient_id => $recipient_name) {
					$item['content'][] = [
						'tag' => 'contributor',
						'content' => [
							[
								'tag' => 'smf:role',
								'content' => 'recipient',
							],
							[
								'tag' => 'name',
								'content' => $recipient_name,
								'cdata' => true,
							],
						],
					];
				}

				$data[] = $item;
			} else {
				Lang::load('PersonalMessage');

				$item = [
					'tag' => 'personal_message',
					'attributes' => ['label' => Lang::$txt['pm']],
					'content' => [
						[
							'tag' => 'id',
							'content' => $row['id_pm'],
						],
						[
							'tag' => 'sent_date',
							'attributes' => ['label' => Lang::$txt['date'], 'UTC' => Time::gmstrftime('%F %T', $row['msgtime'])],
							'content' => Utils::htmlspecialchars(strip_tags(Time::create('@' . $row['msgtime'], new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false))),
						],
						[
							'tag' => 'subject',
							'attributes' => ['label' => Lang::$txt['subject']],
							'content' => $row['subject'],
							'cdata' => true,
						],
						[
							'tag' => 'body',
							'attributes' => ['label' => Lang::$txt['message']],
							'content' => $row['body'],
							'cdata' => true,
						],
						[
							'tag' => 'body_html',
							'attributes' => ['label' => Lang::$txt['html']],
							'content' => $row['body_html'],
							'cdata' => true,
						],
						[
							'tag' => 'sender',
							'attributes' => ['label' => Lang::$txt['author']],
							'content' => [
								[
									'tag' => 'name',
									'attributes' => ['label' => Lang::$txt['name']],
									'content' => $row['from_name'],
									'cdata' => true,
								],
								[
									'tag' => 'id',
									'content' => $row['id_member_from'],
								],
								[
									'tag' => 'link',
									'attributes' => ['label' => Lang::$txt['url']],
									'content' => Config::$scripturl . '?action=profile;u=' . $row['id_member_from'],
								],
							],
						],
					],
				];

				foreach ($recipients as $recipient_id => $recipient_name) {
					$item['content'][] = [
						'tag' => 'recipient',
						'attributes' => ['label' => Lang::$txt['recipient']],
						'content' => [
							[
								'tag' => 'name',
								'attributes' => ['label' => Lang::$txt['name']],
								'content' => $recipient_name,
								'cdata' => true,
							],
							[
								'tag' => 'id',
								'content' => $recipient_id,
							],
							[
								'tag' => 'link',
								'attributes' => ['label' => Lang::$txt['url']],
								'content' => Config::$scripturl . '?action=profile;u=' . $recipient_id,
							],
						],
					];
				}

				$data[] = $item;
			}
		}
		Db::$db->free_result($request);

		return $data;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of the class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of the child class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Builds the XML from the data.
	 *
	 * Returns an array containing three parts: the feed's header section, its
	 * items section, and its footer section. For convenience, the array is also
	 * made available as Utils::$context['feed'].
	 *
	 * This method is static for the sake of the ExportProfileData task, which
	 * needs to do a lot of custom manipulation of the XML.
	 *
	 * @param string $format A supported feed format.
	 * @param array $data Structured data to build as XML.
	 * @param array $metadata Metadata about the feed.
	 * @param string $subaction The sub-action that was requested.
	 * @return array The feed's header, items, and footer.
	 */
	public static function build(string $format, array $data, array $metadata, string $subaction): array
	{
		// Allow mods to add extra namespaces to the feed/channel
		$namespaces = self::XML_NAMESPACES;

		// Finalize the value of the SMF namespace for this sub-action.
		$namespaces['smf']['smf'] = sprintf($namespaces['smf']['smf'], $subaction);

		// These sub-actions need the SMF namespace in other feed formats.
		if (in_array($subaction, ['profile', 'posts', 'personal_messages'])) {
			$namespaces['rss']['smf'] = $namespaces['smf']['smf'];
			$namespaces['rss2']['smf'] = $namespaces['smf']['smf'];
			$namespaces['atom']['smf'] = $namespaces['smf']['smf'];
		}

		// Allow mods to add extra feed-level tags to the feed/channel
		$extraFeedTags = [
			'rss' => [],
			'rss2' => [],
			'atom' => [],
			'rdf' => [],
			'smf' => [],
		];

		// Allow mods to specify any keys that need special handling
		$forceCdataKeys = [];
		$nsKeys = [];

		// Maybe someone needs to insert a DOCTYPE declaration?
		$doctype = '';

		// Remember this, just in case...
		$orig_metadata = $metadata;

		// If mods want to do something with this feed, let them do that now.
		// Provide the feed's data, metadata, namespaces, extra feed-level tags, keys that need special handling, the feed format, and the requested subaction.
		IntegrationHook::call('integrate_xml_data', [&$data, &$metadata, &$namespaces, &$extraFeedTags, &$forceCdataKeys, &$nsKeys, $format, $subaction, &$doctype]);

		// These can't be empty.
		foreach (['title', 'desc', 'source', 'self'] as $mkey) {
			$metadata[$mkey] = !empty($metadata[$mkey]) ? $metadata[$mkey] : $orig_metadata[$mkey];
		}

		// Sanitize feed metadata values.
		foreach ($metadata as $mkey => $mvalue) {
			$metadata[$mkey] = self::cdataParse(self::fixPossibleUrl($mvalue));
		}

		$ns_string = '';

		if (!empty($namespaces[$format])) {
			foreach ($namespaces[$format] as $nsprefix => $nsurl) {
				$ns_string .= ' xmlns' . ($nsprefix !== '' ? ':' : '') . $nsprefix . '="' . $nsurl . '"';
			}
		}

		$i = in_array($format, ['atom', 'smf']) ? 1 : 2;

		$extraFeedTags_string = '';

		if (!empty($extraFeedTags[$format])) {
			$indent = str_repeat("\t", $i);

			foreach ($extraFeedTags[$format] as $extraTag) {
				$extraFeedTags_string .= "\n" . $indent . $extraTag;
			}
		}

		Utils::$context['feed'] = [];

		// First, output the xml header.
		Utils::$context['feed']['header'] = '<?xml version="1.0" encoding="' . Utils::$context['character_set'] . '"?' . '>' . ($doctype !== '' ? "\n" . trim($doctype) : '');

		// Are we outputting an rss feed or one with more information?
		if ($format == 'rss' || $format == 'rss2') {
			// Start with an RSS 2.0 header.
			Utils::$context['feed']['header'] .= '
<rss version="' . ($format == 'rss2' ? '2.0' : '0.92') . '" xml:lang="' . $metadata['language'] . '"' . $ns_string . '>
	<channel>
		<title>' . $metadata['title'] . '</title>
		<link>' . $metadata['source'] . '</link>
		<description>' . $metadata['desc'] . '</description>';

			if (!empty($metadata['icon'])) {
				Utils::$context['feed']['header'] .= '
		<image>
			<url>' . $metadata['icon'] . '</url>
			<title>' . $metadata['title'] . '</title>
			<link>' . $metadata['source'] . '</link>
		</image>';
			}

			if (!empty($metadata['rights'])) {
				Utils::$context['feed']['header'] .= '
		<copyright>' . $metadata['rights'] . '</copyright>';
			}

			if (!empty($metadata['language'])) {
				Utils::$context['feed']['header'] .= '
		<language>' . $metadata['language'] . '</language>';
			}

			// RSS2 calls for this.
			if ($format == 'rss2') {
				Utils::$context['feed']['header'] .= '
		<atom:link rel="self" type="application/rss+xml" href="' . $metadata['self'] . '" />';
			}

			Utils::$context['feed']['header'] .= $extraFeedTags_string;

			// Write the data as an XML string to Utils::$context['feed']['items']
			self::dumpTags($data, $i, $format, $forceCdataKeys, $nsKeys);

			// Output the footer of the xml.
			Utils::$context['feed']['footer'] = '
	</channel>
</rss>';
		} elseif ($format == 'atom') {
			Utils::$context['feed']['header'] .= '
<feed xml:lang="' . $metadata['language'] . '"' . $ns_string . '>
	<title>' . $metadata['title'] . '</title>
	<link rel="alternate" type="text/html" href="' . $metadata['source'] . '" />
	<link rel="self" type="application/atom+xml" href="' . $metadata['self'] . '" />
	<updated>' . Time::gmstrftime('%Y-%m-%dT%H:%M:%SZ') . '</updated>
	<id>' . $metadata['source'] . '</id>
	<subtitle>' . $metadata['desc'] . '</subtitle>
	<generator uri="https://www.simplemachines.org" version="' . SMF_VERSION . '">SMF</generator>';

			if (!empty($metadata['icon'])) {
				Utils::$context['feed']['header'] .= '
	<icon>' . $metadata['icon'] . '</icon>';
			}

			if (!empty($metadata['author'])) {
				Utils::$context['feed']['header'] .= '
	<author>
		<name>' . $metadata['author'] . '</name>
	</author>';
			}

			if (!empty($metadata['rights'])) {
				Utils::$context['feed']['header'] .= '
	<rights>' . $metadata['rights'] . '</rights>';
			}

			Utils::$context['feed']['header'] .= $extraFeedTags_string;

			self::dumpTags($data, $i, $format, $forceCdataKeys, $nsKeys);

			Utils::$context['feed']['footer'] = '
</feed>';
		} elseif ($format == 'rdf') {
			Utils::$context['feed']['header'] .= '
<rdf:RDF' . $ns_string . '>
	<channel rdf:about="' . Config::$scripturl . '">
		<title>' . $metadata['title'] . '</title>
		<link>' . $metadata['source'] . '</link>
		<description>' . $metadata['desc'] . '</description>';

			Utils::$context['feed']['header'] .= $extraFeedTags_string;

			Utils::$context['feed']['header'] .= '
		<items>
			<rdf:Seq>';

			foreach ($data as $item) {
				$link = array_filter(
					$item['content'],
					function ($e) {
						return ($e['tag'] == 'link');
					},
				);
				$link = array_pop($link);

				Utils::$context['feed']['header'] .= '
					<rdf:li rdf:resource="' . $link['content'] . '" />';
			}

			Utils::$context['feed']['header'] .= '
			</rdf:Seq>
		</items>
	</channel>';

			self::dumpTags($data, $i, $format, $forceCdataKeys, $nsKeys);

			Utils::$context['feed']['footer'] = '
</rdf:RDF>';
		}
		// Otherwise, we're using our proprietary formats - they give more data, though.
		else {
			Utils::$context['feed']['header'] .= '
<smf:xml-feed xml:lang="' . $metadata['language'] . '"' . $ns_string . ' version="' . SMF_VERSION . '" forum-name="' . Utils::$context['forum_name'] . '" forum-url="' . Config::$scripturl . '"' . (!empty($metadata['title']) && $metadata['title'] != Utils::$context['forum_name'] ? ' title="' . $metadata['title'] . '"' : '') . (!empty($metadata['desc']) ? ' description="' . $metadata['desc'] . '"' : '') . ' source="' . $metadata['source'] . '" generated-date-localized="' . strip_tags(Time::create('now', new \DateTimeZone(Config::$modSettings['default_timezone']))->format(null, false)) . '" generated-date-UTC="' . Time::gmstrftime('%F %T') . '"' . (!empty($metadata['page']) ? ' page="' . $metadata['page'] . '"' : '') . '>';

			// Hard to imagine anyone wanting to add these for the proprietary format, but just in case...
			Utils::$context['feed']['header'] .= $extraFeedTags_string;

			// Dump out that associative array.  Indent properly.... and use the right names for the base elements.
			self::dumpTags($data, $i, $format, $forceCdataKeys, $nsKeys);

			Utils::$context['feed']['footer'] = '
</smf:xml-feed>';
		}

		return Utils::$context['feed'];
	}

	/**
	 * Ensures supplied data is properly encapsulated in cdata xml tags
	 *
	 * @param string $data XML data
	 * @param string $ns A namespace prefix for the XML data elements (used by mods, maybe)
	 * @param bool $force If true, enclose the XML data in cdata tags no matter what (used by mods, maybe)
	 * @return string The XML data enclosed in cdata tags when necessary
	 */
	public static function cdataParse(string $data, string $ns = '', bool $force = false): string
	{
		// Do we even need to do this?
		if (strpbrk($data, '<>&') == false && $force !== true) {
			return $data;
		}

		$cdata = '<![CDATA[';

		// If there's no namespace prefix to worry about, things are easy.
		if ($ns === '') {
			$cdata .= str_replace(']]>', ']]]]><[CDATA[>', $data);
		}
		// Looks like we need to do it the hard way.
		else {
			for ($pos = 0, $n = strlen($data); $pos < $n; null) {
				$positions = [
					strpos($data, ']]>', $pos),
					strpos($data, '<', $pos),
				];

				$positions = array_filter($positions, 'is_int');

				$old = $pos;
				$pos = empty($positions) ? $n : min($positions);

				if ($pos - $old > 0) {
					$cdata .= substr($data, $old, $pos - $old);
				}

				if ($pos >= $n) {
					break;
				}

				if (substr($data, $pos, 1) == '<') {
					$pos2 = strpos($data, '>', $pos);

					if ($pos2 === false) {
						$pos2 = $n;
					}

					if (substr($data, $pos + 1, 1) == '/') {
						$cdata .= ']]></' . $ns . ':' . substr($data, $pos + 2, $pos2 - $pos - 1) . '<![CDATA[';
					} else {
						$cdata .= ']]><' . $ns . ':' . substr($data, $pos + 1, $pos2 - $pos) . '<![CDATA[';
					}

					$pos = $pos2 + 1;
				} elseif (substr($data, $pos, 3) == ']]>') {
					$cdata .= ']]]]><![CDATA[>';
					$pos = $pos + 3;
				}
			}
		}

		$cdata .= ']]>';

		return strtr($cdata, ['<![CDATA[]]>' => '']);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setSubaction($subaction)
	{
		if (isset($subaction, self::$subactions[$subaction])) {
			$this->subaction = $subaction;
		} elseif (isset($_GET['sa'], self::$subactions[$_GET['sa']])) {
			$this->subaction = $_GET['sa'];
		} else {
			$this->subaction = array_key_first(self::$subactions);
		}
	}

	protected function setMember($member)
	{
		// Member ID was passed to the constructor.
		if (isset($member)) {
			$this->member = $member;
		}
		// Member ID was set via Utils::$context.
		elseif (isset(Utils::$context['xmlnews_uid'])) {
			$this->member = Utils::$context['xmlnews_uid'];
		}
		// Member ID was set via URL parameter.
		elseif (isset($_GET['u'])) {
			$this->member = $_GET['u'];
		}
		// Default to current user.
		else {
			$this->member = User::$me->id;
		}

		// Make sure the ID is a number and not "I like trying to hack the database."
		$this->member = (int) $this->member;

		// For backward compatibility.
		Utils::$context['xmlnews_uid'] = $this->member;
	}

	protected function setFormat()
	{
		if (isset($_GET['type'], self::XML_NAMESPACES[$_GET['type']])) {
			$this->format = $_GET['type'];
		}
	}

	protected function setlimit()
	{
		// Limit was set via Utils::$context.
		if (isset(Utils::$context['xmlnews_limit'])) {
			$this->limit = Utils::$context['xmlnews_limit'];
		}
		// Limit was set via URL parameter.
		elseif (isset($_GET['limit'])) {
			$this->limit = $_GET['limit'];
		}

		// Default to latest 5.  No more than 255, please.
		$this->limit = (int) $this->limit < 1 ? 5 : min((int) $this->limit, 255);

		// For backward compatibility.
		Utils::$context['xmlnews_limit'] = $this->limit;
	}

	protected function checkEnabled(): void
	{
		// Users can always export their own profile data.
		if (in_array($this->subaction, ['profile', 'posts', 'personal_messages']) && $this->member == User::$me->id && !User::$me->is_guest) {
			return;
		}

		// If it's not enabled, die.
		if (empty(Config::$modSettings['xmlnews_enable'])) {
			Utils::obExit(false);
		}
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Populates Utils::$context['feed']['items'].
	 *
	 * Formats data retrieved in other functions into XML format.
	 * Additionally formats data based on the specific format passed.
	 * This function is recursively called to handle sub arrays of data.
	 *
	 * @param array $data The array to output as XML data.
	 * @param int $i The amount of indentation to use.
	 * @param string $format The format to use ('atom', 'rss', 'rss2' or 'smf')
	 * @param array $forceCdataKeys A list of keys on which to force cdata wrapping (used by mods, maybe)
	 * @param array $nsKeys Key-value pairs of namespace prefixes to pass to self::cdataParse() (used by mods, maybe)
	 */
	protected static function dumpTags(array $data, int $i, string $format = '', array $forceCdataKeys = [], array $nsKeys = []): void
	{
		if (empty(Utils::$context['feed']['items'])) {
			Utils::$context['feed']['items'] = '';
		}

		// For every array in the data...
		foreach ($data as $element) {
			$key = $element['tag'] ?? null;
			$val = $element['content'] ?? null;
			$attrs = $element['attributes'] ?? null;

			// Skip it, it's been set to null.
			if ($key === null || ($val === null && $attrs === null)) {
				continue;
			}

			$forceCdata = in_array($key, $forceCdataKeys);
			$ns = !empty($nsKeys[$key]) ? $nsKeys[$key] : '';

			// First let's indent!
			Utils::$context['feed']['items'] .= "\n" . str_repeat("\t", $i);

			// Beginning tag.
			Utils::$context['feed']['items'] .= '<' . $key;

			if (!empty($attrs)) {
				foreach ($attrs as $attr_key => $attr_value) {
					Utils::$context['feed']['items'] .= ' ' . $attr_key . '="' . self::fixPossibleUrl($attr_value) . '"';
				}
			}

			// If it's empty, simply output an empty element.
			if (empty($val) && $val !== '0' && $val !== 0) {
				Utils::$context['feed']['items'] .= ' />';
			} else {
				Utils::$context['feed']['items'] .= '>';

				// The element's value.
				if (is_array($val)) {
					// An array.  Dump it, and then indent the tag.
					self::dumpTags($val, $i + 1, $format, $forceCdataKeys, $nsKeys);
					Utils::$context['feed']['items'] .= "\n" . str_repeat("\t", $i);
				}
				// A string with returns in it.... show this as a multiline element.
				elseif (strpos($val, "\n") !== false) {
					Utils::$context['feed']['items'] .= "\n" . (!empty($element['cdata']) || $forceCdata ? self::cdataParse(self::fixPossibleUrl($val), $ns, $forceCdata) : self::fixPossibleUrl($val)) . "\n" . str_repeat("\t", $i);
				}
				// A simple string.
				else {
					Utils::$context['feed']['items'] .= !empty($element['cdata']) || $forceCdata ? self::cdataParse(self::fixPossibleUrl($val), $ns, $forceCdata) : self::fixPossibleUrl($val);
				}

				// Ending tag.
				Utils::$context['feed']['items'] .= '</' . $key . '>';
			}
		}
	}

	/**
	 * Called from self::dumpTags to convert data to XML.
	 * Finds URLs for local site and sanitizes them.
	 *
	 * @param string $val A string containing a possible URL.
	 * @return string $val The string with any possible URLs sanitized.
	 */
	protected static function fixPossibleUrl($val)
	{
		if (substr($val, 0, strlen(Config::$scripturl)) != Config::$scripturl) {
			return $val;
		}

		IntegrationHook::call('integrate_fix_url', [&$val]);

		if (
			empty(Config::$modSettings['queryless_urls'])
			|| (
				Utils::$context['server']['is_cgi']
				&& ini_get('cgi.fix_pathinfo') == 0
				&& @get_cfg_var('cgi.fix_pathinfo') == 0
			)
			|| (
				!Utils::$context['server']['is_apache']
				&& !Utils::$context['server']['is_lighttpd']
			)
		) {
			return $val;
		}

		$val = preg_replace_callback(
			'~\b' . preg_quote(Config::$scripturl, '~') . '\?((?:board|topic)=[^#"]+)(#[^"]*)?$~',
			function ($m) {
				return Config::$scripturl . '/' . strtr("{$m[1]}", '&;=', '//,') . '.html' . ($m[2] ?? '');
			},
			$val,
		);

		return $val;
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Feed::exportStatic')) {
	Feed::exportStatic();
}

?>