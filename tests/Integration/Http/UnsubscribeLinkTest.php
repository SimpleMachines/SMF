<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use SMF\Actions\Notify;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * The unsubscribe link at the bottom of a reply notification email.
 *
 * The member clicking it is very often not logged in on the browser it opens
 * in, so every one of these follows the link as a guest. The link carries a
 * token in place of a login, and is no use at all if the forum asks them to
 * log in first.
 */
#[CoversNothing]
class UnsubscribeLinkTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The member this test made, so tearDown() can remove them.
	 */
	private int $member_id = 0;

	/**
	 * @var array The settings to put back afterwards: name => value.
	 */
	private array $settings_backup = [];

	/**
	 * @var array The boards whose member groups to put back: id => groups.
	 */
	private array $boards_backup = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * A token made for one topic does not unsubscribe from another.
	 *
	 * Expected: following a link for one topic with a token made for the next
	 *           topic returns 403 with "does not appear to be valid", and the
	 *           member's preference for the topic is unchanged.
	 */
	public function testATokenForAnotherTopicIsRefused(): void
	{
		[$topic] = $this->anyTopic();
		$member = $this->makeSubscribedMember($topic);

		$response = $this->fetch($this->unsubscribeLink($member, $topic, $topic + 1), 403);

		$this->assertStringContainsString(
			'does not appear to be valid',
			$response->errorText(),
			'a token for another topic was accepted',
		);
		$this->assertSame(3, $this->topicPref($member, $topic), 'the preference changed anyway');
	}

	/**
	 * Following the link stops the emails and leaves the alerts alone.
	 *
	 * Expected: each case in situations() follows a valid unsubscribe link as
	 *           a guest and sees "has been unsubscribed from new reply
	 *           notifications". The member's preference for the topic changes
	 *           from both to alerts only, and nothing is logged.
	 * Guards:   none of these situations worked. The token was checked against
	 *           topic 0 whatever topic it was made for, so every link was
	 *           refused as invalid. A forum with guest access off, or a topic
	 *           in a board that guests cannot see, sent the member to the login
	 *           form before the link was looked at.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/4bf14e2a6 Introduced by "Implements SMF\Actions\Notify and sub-classes"
	 * @link https://github.com/SimpleMachines/SMF/pull/9734
	 */
	#[DataProvider('situations')]
	public function testTheLinkUnsubscribesAMemberWhoIsNotLoggedIn(bool $guest_access, bool $guests_see_board): void
	{
		[$topic, $board] = $this->anyTopic();
		$member = $this->makeSubscribedMember($topic);

		if (!$guest_access) {
			$this->changeSetting('allow_guestAccess', '0');
		}

		if (!$guests_see_board) {
			$this->hideBoardFromGuests($board);
		}

		$response = $this->fetch($this->unsubscribeLink($member, $topic, $topic));

		$this->assertStringContainsString(
			'has been unsubscribed from new reply notifications',
			$response->text(),
			'the link did not unsubscribe: ' . ($response->errorText() ?: $response->title()),
		);
		$this->assertSame(1, $this->topicPref($member, $topic), 'the email bit was not cleared, or the alert bit went with it');
		$this->assertNoErrorsLogged('following an unsubscribe link logged something.' . "\n");
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * The ways a guest can be kept away from a topic.
	 *
	 * @return array The cases: whether guest access is on, and whether guests
	 *    can see the topic's board.
	 */
	public static function situations(): array
	{
		return [
			'a board guests can see' => [true, true],
			'guest access turned off' => [false, true],
			'a board guests cannot see' => [true, false],
		];
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->member_id !== 0) {
			foreach (['members', 'user_alerts_prefs', 'log_notify', 'log_topics'] as $table) {
				Db::$db->query(
					'DELETE FROM {db_prefix}' . $table . '
					WHERE id_member = {int:member}',
					['member' => $this->member_id],
				);
			}

			$this->member_id = 0;
		}

		foreach ($this->boards_backup as $board => $groups) {
			Db::$db->query(
				'UPDATE {db_prefix}boards
				SET member_groups = {string:groups}
				WHERE id_board = {int:board}',
				['groups' => $groups, 'board' => $board],
			);
		}

		$this->boards_backup = [];

		if ($this->settings_backup !== []) {
			Config::updateModSettings($this->settings_backup);
			$this->settings_backup = [];
		}

		parent::tearDown();
	}

	/**
	 * The link CreatePost_Notify puts in a reply notification.
	 *
	 * @param int $member Who it is for.
	 * @param int $topic The topic named in the link.
	 * @param int $token_topic The topic the token is made for.
	 * @return string The link, relative to the forum.
	 */
	private function unsubscribeLink(int $member, int $topic, int $token_topic): string
	{
		$token = Notify::createUnsubscribeToken($member, $this->emailOf($member), 'topic', $token_topic);

		return '?action=notifytopic;item=' . $topic . ';sa=off;u=' . $member . ';token=' . $token;
	}

	/**
	 * The first topic on the forum, and its board.
	 *
	 * @return array The topic's id and its board's id.
	 */
	private function anyTopic(): array
	{
		$row = $this->queryRow(
			'SELECT id_topic, id_board
			FROM {db_prefix}topics
			ORDER BY id_topic
			LIMIT 1',
		);

		$this->assertNotNull($row, 'the forum has no topics');

		return [(int) $row['id_topic'], (int) $row['id_board']];
	}

	/**
	 * Makes a member who gets both an email and an alert for replies to a topic.
	 *
	 * @param int $topic The topic.
	 * @return int The member's id.
	 */
	private function makeSubscribedMember(int $topic): int
	{
		$name = 'unsub_' . bin2hex(random_bytes(4));

		$this->member_id = (int) Db::$db->insert(
			'insert',
			'{db_prefix}members',
			[
				'member_name' => 'string',
				'real_name' => 'string',
				'email_address' => 'string',
				'passwd' => 'string',
				'date_registered' => 'int',
				'is_activated' => 'int',
				'buddy_list' => 'string',
				'signature' => 'string',
				'ignore_boards' => 'string',
			],
			[[$name, $name, $name . '@example.com', '', time(), 1, '', '', '']],
			['id_member'],
			1,
		);

		Notify::setNotifyPrefs($this->member_id, ['topic_notify_' . $topic => Notify::PREF_BOTH]);

		return $this->member_id;
	}

	/**
	 * @param int $member The member.
	 * @return string Their email address.
	 */
	private function emailOf(int $member): string
	{
		return (string) $this->queryRow(
			'SELECT email_address
			FROM {db_prefix}members
			WHERE id_member = {int:member}',
			['member' => $member],
		)['email_address'];
	}

	/**
	 * @param int $member The member.
	 * @param int $topic The topic.
	 * @return ?int Their notification preference for it, or null for none.
	 */
	private function topicPref(int $member, int $topic): ?int
	{
		$row = $this->queryRow(
			'SELECT alert_value
			FROM {db_prefix}user_alerts_prefs
			WHERE id_member = {int:member}
				AND alert_pref = {string:pref}',
			['member' => $member, 'pref' => 'topic_notify_' . $topic],
		);

		return $row === null ? null : (int) $row['alert_value'];
	}

	/**
	 * Changes a setting until the end of the test.
	 *
	 * @param string $name The setting.
	 * @param string $value Its value for this test.
	 */
	private function changeSetting(string $name, string $value): void
	{
		$this->settings_backup[$name] ??= (string) ($this->rawSetting($name) ?? '');

		Config::updateModSettings([$name => $value]);
	}

	/**
	 * Takes guests out of a board's member groups until the end of the test.
	 *
	 * @param int $board The board.
	 */
	private function hideBoardFromGuests(int $board): void
	{
		$groups = (string) $this->queryRow(
			'SELECT member_groups
			FROM {db_prefix}boards
			WHERE id_board = {int:board}',
			['board' => $board],
		)['member_groups'];

		$this->boards_backup[$board] ??= $groups;

		Db::$db->query(
			'UPDATE {db_prefix}boards
			SET member_groups = {string:groups}
			WHERE id_board = {int:board}',
			[
				'groups' => implode(',', array_diff(explode(',', $groups), ['-1'])),
				'board' => $board,
			],
		);
	}
}
