<?php

/*
 * Builds the content the template snapshots are taken of, and prints where it is.
 *
 *   php tests/Integration/Http/Snapshot/fixture-forum.php
 *
 * Run by SnapshotTestCase in a process of its own, because it has to boot the
 * whole forum through SSI.php to use SMF's own API for this, and a test process
 * that has booted SMF cannot make HTTP requests to it afterwards.
 *
 * The snapshots only ever look at what this makes, so they do not change when
 * somebody posts, registers or adds a board anywhere else on the forum. For that
 * to hold, the content has to be the same on every forum it is built on:
 *
 *  - Every timestamp is pinned to a fixed moment in January 2020, so no date on
 *    a snapshotted page depends on when the fixtures were made.
 *  - There are at least two of everything a template loops over - boards,
 *    child boards, topics, replies, recipients - so that one more of them is
 *    another copy of something the snapshot already shows rather than a new
 *    shape.
 *  - Everything is marked read for every member the snapshots sign in as, so a
 *    page does not grow or lose a "new" marker depending on who has looked at
 *    what.
 *
 * Idempotent. When the fixture category already exists nothing is created, and
 * the ids are looked up and printed instead.
 *
 * Prints a single line of JSON on standard output.
 */

declare(strict_types=1);

use SMF\Board;
use SMF\Category;
use SMF\Db\DatabaseApi as Db;
use SMF\Msg;
use SMF\PersonalMessage\PM;
use SMF\User;
use SMF\Utils;

// SSI.php must not think it is answering a request.
$_SERVER['REQUEST_METHOD'] = 'GET';

$board_dir = dirname(__DIR__, 4);

ob_start();

require_once $board_dir . '/SSI.php';

// SSI prints nothing when there is no request, but anything it did print would
// be in front of the JSON the caller is waiting for.
ob_end_clean();

/**
 * The names everything is found by. They are what the snapshots show, so
 * changing one means updating every snapshot.
 */
const FIXTURE_CATEGORY = 'Snapshot fixtures';
const FIXTURE_TIME = 1579082400; // 2020-01-15 10:00:00 UTC
const MEMBER_PASSWORD = 'snapshot-password-1';

$fixtures = new SnapshotFixtures();

echo json_encode($fixtures->build(), JSON_PRETTY_PRINT), "\n";

/**
 * Does the work, so the script above reads as what it does.
 */
final class SnapshotFixtures
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The administrator, who creates the boards and posts some replies.
	 */
	private int $admin;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Creates the fixtures, or finds the ones already there.
	 *
	 * @return array Where everything is, for the tests.
	 */
	public function build(): array
	{
		$this->admin = $this->adminId();

		User::setMe($this->admin);

		// Some of what follows asks for the administrator's password again,
		// unless the forum has switched that off. Having just given it is what
		// User::validateSession() looks for.
		$_SESSION['admin_time'] = time();

		$category = $this->categoryId();

		if ($category === 0) {
			$this->create();
		}

		$fixtures = $this->locate();

		$this->ensureEvents($fixtures['members']);

		return $fixtures;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Makes the fixtures.
	 */
	private function create(): void
	{
		$member = $this->registerMember('snapshot_member', 'Snapshot Member');
		$other = $this->registerMember('snapshot_other', 'Snapshot Other');

		$category = Category::create([
			'cat_name' => FIXTURE_CATEGORY,
			'cat_desc' => 'Content for the template snapshot tests.',
			'move_after' => 0,
		]);

		// -1 is guests and 0 regular members; administrators see everything.
		$groups = [-1, 0];

		$board = Board::create([
			'board_name' => 'Snapshot board',
			'board_description' => 'Topics, replies and a poll for the snapshot tests.',
			'move_to' => 'bottom',
			'target_category' => $category,
			'access_groups' => $groups,
			'moderators' => 'snapshot_other',
		]);

		$child_one = Board::create([
			'board_name' => 'Snapshot child one',
			'board_description' => 'The first child board.',
			'move_to' => 'child',
			'target_category' => $category,
			'target_board' => $board,
			'access_groups' => $groups,
		]);

		Board::create([
			'board_name' => 'Snapshot child two',
			'board_description' => 'The second child board, which stays empty.',
			'move_to' => 'after',
			'target_category' => $category,
			'target_board' => $child_one,
			'access_groups' => $groups,
		]);

		Board::create([
			'board_name' => 'Snapshot empty board',
			'board_description' => 'A board with nothing in it.',
			'move_to' => 'bottom',
			'target_category' => $category,
			'access_groups' => $groups,
		]);

		$time = FIXTURE_TIME;

		// The topic most snapshots look at. The guest reply is deliberately not
		// the last post anywhere, so that no list of recent posts ever has to
		// show a guest's name - one does on this topic's own page, and that is
		// enough.
		$topic = $this->post($board, 0, $member, 'Snapshot topic', self::richBody(), $time);
		$this->post($board, $topic, 0, 'Re: Snapshot topic', 'A reply from a guest.', $time += 3600, 'Snapshot Guest');
		$this->post($board, $topic, $this->admin, 'Re: Snapshot topic', '[quote author=Snapshot Member]The quoted text.[/quote]' . "\n" . 'A reply from the administrator.', $time += 3600);
		$this->post($board, $topic, $other, 'Re: Snapshot topic', 'A reply from another member, with a smiley :)', $time += 3600);

		$poll = $this->poll($this->admin, 'Which colour?', ['Red', 'Green', 'Blue']);

		$this->post($board, 0, $this->admin, 'Snapshot poll topic', 'A sticky, locked topic with a poll.', $time += 3600, '', [
			'poll' => $poll,
			'sticky_mode' => 1,
			'lock_mode' => 1,
		]);

		$this->post($board, 0, $other, 'Snapshot second topic', 'Another topic, so the board lists more than one.', $time += 3600);
		$this->post($child_one, 0, $other, 'Snapshot child topic', 'A topic in a child board.', $time += 3600);
		$this->post($child_one, 0, $member, 'Snapshot second child topic', 'A second topic in the child board.', $time += 3600);

		$this->personalMessage($other, [$member, $this->admin], 'Snapshot message', 'A personal message to two people.', $time += 3600);


		// Every visit to a topic counts as a view, and "1 view" is worded
		// differently from "2 views", so the count starts high enough that
		// the wording never changes again.
		Db::$db->query(
			'UPDATE {db_prefix}topics
			SET num_views = {int:views}
			WHERE id_board IN ({array_int:boards})',
			['views' => 10, 'boards' => [$board, $child_one]],
		);

		$this->pinMembers([$member, $other], $time);
		$this->markEverythingRead([$this->admin, $member, $other]);
	}

	/**
	 * Creates the calendar events, when they are not there yet.
	 *
	 * Separate from create() so that a forum whose fixtures were built before
	 * the calendar snapshots existed gets them too.
	 *
	 * @param array $members The fixture members, by name.
	 */
	private function ensureEvents(array $members): void
	{
		$row = $this->queryRow(
			'SELECT id_event FROM {db_prefix}calendar WHERE title = {string:title}',
			['title' => 'Snapshot event'],
		);

		if ($row !== null) {
			return;
		}

		// In the same month as everything else, which is the month the calendar
		// snapshots look at. One with a time and one lasting all day, since the
		// calendar draws the two differently.
			SMF\Calendar\Event::create([
				'board' => 0,
				'topic' => 0,
				'member' => $members['snapshot_member'],
				'title' => 'Snapshot event',
				'location' => 'Snapshot location',
				'start_date' => '2020-01-20',
				'end_date' => '2020-01-20',
				'start_time' => '10:00',
				'end_time' => '11:30',
				'timezone' => 'UTC',
				'allday' => false,
			]);

			SMF\Calendar\Event::create([
				'board' => 0,
				'topic' => 0,
				'member' => $members['snapshot_other'],
				'title' => 'Snapshot all day event',
				'location' => '',
				'start_date' => '2020-01-22',
				'end_date' => '2020-01-23',
				'start_time' => '00:00',
				'end_time' => '00:00',
				'timezone' => 'UTC',
				'allday' => true,
			]);
	}

	/**
	 * Finds everything the tests need to know about.
	 *
	 * @return array Board, topic and member ids by name.
	 */
	private function locate(): array
	{
		$category = $this->categoryId();

		$boards = $this->column(
			'SELECT name, id_board FROM {db_prefix}boards WHERE id_cat = {int:cat}',
			['cat' => $category],
		);

		$topics = $this->column(
			'SELECT m.subject, t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_board IN ({array_int:boards})',
			['boards' => array_values($boards)],
		);

		$first_msgs = $this->column(
			'SELECT m.subject, t.id_first_msg
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_board IN ({array_int:boards})',
			['boards' => array_values($boards)],
		);

		$members = $this->column(
			'SELECT member_name, id_member FROM {db_prefix}members WHERE member_name IN ({array_string:names})',
			['names' => ['snapshot_member', 'snapshot_other']],
		);

		$pm = $this->column(
			'SELECT subject, id_pm FROM {db_prefix}personal_messages WHERE subject = {string:subject}',
			['subject' => 'Snapshot message'],
		);

		return [
			'category' => $category,
			'boards' => $boards,
			'topics' => $topics,
			'first_msgs' => $first_msgs,
			'members' => $members,
			'admin' => $this->admin,
			'member_password' => MEMBER_PASSWORD,
			'pm' => (int) ($pm['Snapshot message'] ?? 0),
		];
	}

	/**
	 * Posts, as the given member or as a guest.
	 *
	 * @param int $board Where.
	 * @param int $topic The topic to reply to, or 0 for a new one.
	 * @param int $poster The member, or 0 for a guest.
	 * @param string $subject The subject.
	 * @param string $body The body, in BBCode.
	 * @param int $time When it was posted.
	 * @param string $guest_name The name a guest posts under.
	 * @param array $topic_options Anything else the topic needs.
	 * @return int The topic id.
	 */
	private function post(int $board, int $topic, int $poster, string $subject, string $body, int $time, string $guest_name = '', array $topic_options = []): int
	{
		$msgOptions = [
			'subject' => Utils::htmlspecialchars($subject),
			'body' => Utils::htmlspecialchars($body),
			'icon' => 'xx',
			'smileys_enabled' => true,
			'approved' => 1,
			'poster_time' => $time,
			'send_notifications' => false,
		];

		$topicOptions = $topic_options + [
			'id' => $topic,
			'board' => $board,
			'mark_as_read' => false,
		];

		$posterOptions = [
			'id' => $poster,
			'ip' => '127.0.0.1',
			'update_post_count' => $poster !== 0,
		];

		if ($poster === 0) {
			$posterOptions['name'] = $guest_name;
			$posterOptions['email'] = 'guest@example.com';
		}

		Msg::create($msgOptions, $topicOptions, $posterOptions);

		// Msg::create() stamps the topic and board with the time now in a few
		// places of its own; the message carries the pinned time already.
		return (int) $topicOptions['id'];
	}

	/**
	 * Creates a poll to attach to a topic.
	 *
	 * @param int $poster Who asked.
	 * @param string $question The question.
	 * @param array $choices The answers.
	 * @return int The poll id.
	 */
	private function poll(int $poster, string $question, array $choices): int
	{
		$id = (int) Db::$db->insert(
			'',
			'{db_prefix}polls',
			[
				'question' => 'string-255',
				'hide_results' => 'int',
				'max_votes' => 'int',
				'expire_time' => 'int',
				'id_member' => 'int',
				'poster_name' => 'string-255',
				'change_vote' => 'int',
				'guest_vote' => 'int',
			],
			[[$question, 0, 1, 0, $poster, 'admin', 0, 0]],
			['id_poll'],
			Db::INSERT_RETURN_MODE_SINGLE,
		);

		$rows = [];

		foreach ($choices as $i => $label) {
			// Two votes for the first, one for the second, none for the third, so
			// the results show a bar of each width.
			$rows[] = [$id, $i, $label, max(0, 2 - $i)];
		}

		Db::$db->insert(
			'',
			'{db_prefix}poll_choices',
			['id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255', 'votes' => 'int'],
			$rows,
			['id_poll', 'id_choice'],
		);

		return $id;
	}

	/**
	 * Sends a personal message and pins its time.
	 *
	 * @param int $from The sender.
	 * @param array $to The recipients.
	 * @param string $subject The subject.
	 * @param string $body The body.
	 * @param int $time When it was sent.
	 */
	private function personalMessage(int $from, array $to, string $subject, string $body, int $time): void
	{
		$sender = $this->queryRow(
			'SELECT member_name, real_name FROM {db_prefix}members WHERE id_member = {int:id}',
			['id' => $from],
		);

		PM::send(['to' => $to, 'bcc' => []], $subject, $body, true, [
			'id' => $from,
			'name' => $sender['real_name'],
			'username' => $sender['member_name'],
		]);

		Db::$db->query(
			'UPDATE {db_prefix}personal_messages
			SET msgtime = {int:time}
			WHERE subject = {string:subject}',
			['time' => $time, 'subject' => $subject],
		);

		// Read already, as it will be once the member tests have opened it, so
		// the unread count in the menu is the same before that as after.
		Db::$db->query(
			'UPDATE {db_prefix}pm_recipients
			SET is_read = {int:read}, is_new = {int:not_new}
			WHERE id_member IN ({array_int:members})',
			['read' => 1, 'not_new' => 0, 'members' => $to],
		);

		Db::$db->query(
			'UPDATE {db_prefix}members
			SET unread_messages = {int:none}, new_pm = {int:none}
			WHERE id_member IN ({array_int:members})',
			['none' => 0, 'members' => $to],
		);
	}

	/**
	 * Registers a member who can sign in straight away.
	 *
	 * @param string $name The member name.
	 * @param string $display The display name.
	 * @return int The member id.
	 */
	private function registerMember(string $name, string $display): int
	{
		$options = [
			'interface' => 'admin',
			'username' => $name,
			'email' => $name . '@example.com',
			'password' => MEMBER_PASSWORD,
			'password_check' => MEMBER_PASSWORD,
			'check_reserved_name' => false,
			'check_password_strength' => false,
			'check_email_ban' => false,
			'send_welcome_email' => false,
			'require' => 'nothing',
			'memberGroup' => 0,
		];

		$id = SMF\Actions\Register2::registerMember($options, true);

		if (!is_int($id)) {
			fwrite(STDERR, 'could not register ' . $name . ': ' . json_encode($id) . "\n");

			exit(1);
		}

		Db::$db->query(
			'UPDATE {db_prefix}members
			SET real_name = {string:display},
				personal_text = {string:personal_text},
				signature = {string:signature},
				website_title = {string:website_title},
				website_url = {string:website_url},
				member_ip = {inet:ip},
				member_ip2 = {inet:ip}
			WHERE id_member = {int:id}',
			[
				'id' => $id,
				'display' => $display,
				'personal_text' => 'Personal text for ' . $display,
				'signature' => 'A signature with [b]bold[/b] text.',
				'website_title' => 'Example',
				'website_url' => 'https://www.example.com',
				// Registering from the command line records no address, which
				// no real member is without, and the tracking pages fatal on.
				'ip' => '127.0.0.1',
			],
		);

		return $id;
	}

	/**
	 * Pins the dates a profile shows.
	 *
	 * @param array $members Who.
	 * @param int $time The last thing that happened.
	 */
	private function pinMembers(array $members, int $time): void
	{
		Db::$db->query(
			'UPDATE {db_prefix}members
			SET date_registered = {int:registered}, last_login = {int:login}
			WHERE id_member IN ({array_int:members})',
			[
				'members' => $members,
				'registered' => FIXTURE_TIME - 86400,
				'login' => $time,
			],
		);
	}

	/**
	 * Marks every fixture board and topic read for these members.
	 *
	 * @param array $members Who.
	 */
	private function markEverythingRead(array $members): void
	{
		$boards = $this->column(
			'SELECT id_board, id_msg_updated FROM {db_prefix}boards WHERE id_cat = {int:cat}',
			['cat' => $this->categoryId()],
		);

		$topics = $this->column(
			'SELECT id_topic, id_last_msg FROM {db_prefix}topics WHERE id_board IN ({array_int:boards})',
			['boards' => array_keys($boards)],
		);

		$latest = max(array_merge([0], $topics, $boards));

		foreach ($members as $member) {
			foreach ($boards as $board => $msg) {
				Db::$db->insert('replace', '{db_prefix}log_boards', ['id_member' => 'int', 'id_board' => 'int', 'id_msg' => 'int'], [[$member, $board, $latest]], ['id_member', 'id_board']);
				Db::$db->insert('replace', '{db_prefix}log_mark_read', ['id_member' => 'int', 'id_board' => 'int', 'id_msg' => 'int'], [[$member, $board, $latest]], ['id_member', 'id_board']);
			}

			foreach ($topics as $topic => $msg) {
				Db::$db->insert('replace', '{db_prefix}log_topics', ['id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'unwatched' => 'int'], [[$member, $topic, $latest, 0]], ['id_member', 'id_topic']);
			}
		}
	}

	/**
	 * The fixture category, if it has been made.
	 *
	 * @return int Its id, or 0.
	 */
	private function categoryId(): int
	{
		$row = $this->queryRow(
			'SELECT id_cat FROM {db_prefix}categories WHERE name = {string:name}',
			['name' => FIXTURE_CATEGORY],
		);

		return (int) ($row['id_cat'] ?? 0);
	}

	/**
	 * The first administrator.
	 *
	 * @return int The member id.
	 */
	private function adminId(): int
	{
		$row = $this->queryRow(
			'SELECT id_member FROM {db_prefix}members WHERE id_group = {int:group} ORDER BY id_member LIMIT 1',
			['group' => 1],
		);

		return (int) ($row['id_member'] ?? 1);
	}

	/**
	 * Runs a two column query into key => value.
	 *
	 * @param string $sql The query.
	 * @param array $params Its parameters.
	 * @return array The first column as keys, the second as integer values.
	 */
	private function column(string $sql, array $params): array
	{
		$request = Db::$db->query($sql, $params);
		$result = [];

		while ($row = Db::$db->fetch_row($request)) {
			$result[$row[0]] = (int) $row[1];
		}

		Db::$db->free_result($request);

		return $result;
	}

	/**
	 * Runs a query and returns its first row.
	 *
	 * @param string $sql The query.
	 * @param array $params Its parameters.
	 * @return array|null The row.
	 */
	private function queryRow(string $sql, array $params): ?array
	{
		$request = Db::$db->query($sql, $params);
		$row = Db::$db->fetch_assoc($request);
		Db::$db->free_result($request);

		return is_array($row) ? $row : null;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * A first post that exercises as much of the BBCode parser's output as a
	 * template is likely to wrap.
	 *
	 * @return string The body.
	 */
	private static function richBody(): string
	{
		return implode("\n", [
			'The first post, with [b]bold[/b], [i]italic[/i], [u]underlined[/u] and [s]struck[/s] text.',
			'[url=https://www.simplemachines.org]A link[/url] and [email]someone@example.com[/email].',
			'[quote]An unattributed quote.[/quote]',
			'[code]echo "code";[/code]',
			'[list][li]One[/li][li]Two[/li][/list]',
			'[color=red]Red[/color] [size=12pt]Sized[/size] [center]Centred[/center]',
			'[table][tr][td]A[/td][td]B[/td][/tr][/table]',
			'[spoiler]Hidden[/spoiler] [hr] Smileys: :) ;)',
		]);
	}
}
