<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Actions\Notify;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * The email a member watching a topic gets when someone replies, sent by the
 * background task the way the forum sends it: through cron.php, with the mail
 * queue held so the email can be read from it.
 */
#[CoversNothing]
class ReplyNotificationTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * A message that does not exist. The task uses its ID only in links and
	 * the digest, so nothing real is touched.
	 */
	private const MSG = 7654323;

	/**
	 * What the "until you visit" emails say, and the others must not.
	 */
	private const ONCE = 'you will not receive any more notifications for this topic until you visit it';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The member this test made.
	 */
	private int $member = 0;

	/**
	 * @var int The topic used.
	 */
	private int $topic = 0;

	/**
	 * @var ?string mail_next_send before the test.
	 */
	private ?string $mail_next_send = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * A member emailed about every reply is not told they will hear no more.
	 *
	 * The "until you visit it" wording was chosen for any frequency other
	 * than "never", so a member who asked for an email about every reply was
	 * told they would get no more until they visited the topic, and then
	 * went on getting them.
	 */
	public function testAMemberEmailedAboutEveryReplyIsNotToldTheEmailsWillStop(): void
	{
		$body = $this->notify(1);

		$this->assertStringNotContainsString(self::ONCE, $body);
	}

	public function testAMemberEmailedOnceUntilTheyVisitIsToldSo(): void
	{
		$body = $this->notify(2);

		$this->assertStringContainsString(self::ONCE, $body);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->member !== 0 && isset(Db::$db)) {
			Db::$db->query('DELETE FROM {db_prefix}mail_queue WHERE recipient = {string:to}', ['to' => $this->email()]);
			Db::$db->query('DELETE FROM {db_prefix}background_tasks WHERE task_data LIKE {string:msg}', ['msg' => '%"id":' . self::MSG . ',%']);
			Db::$db->query('DELETE FROM {db_prefix}log_digest WHERE id_msg = {int:msg}', ['msg' => self::MSG]);

			foreach (['log_notify', 'user_alerts_prefs', 'members'] as $table) {
				Db::$db->query('DELETE FROM {db_prefix}' . $table . ' WHERE id_member = {int:m}', ['m' => $this->member]);
			}

			Config::updateModSettings(['mail_next_send' => $this->mail_next_send ?? '0']);

			$this->member = 0;
		}

		parent::tearDown();
	}

	/**
	 * Has a member watch a topic by email, posts a reply as the administrator
	 * and runs the task.
	 *
	 * @param int $frequency The member's msg_notify_pref.
	 * @return string The body of the email they were sent.
	 */
	private function notify(int $frequency): string
	{
		$this->mail_next_send = $this->rawSetting('mail_next_send');
		Config::updateModSettings(['mail_next_send' => time() + 3600]);

		$topic = $this->queryRow(
			'SELECT t.id_topic, t.id_board
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE FIND_IN_SET({string:regular}, b.member_groups) != 0
			ORDER BY t.id_topic
			LIMIT 1',
			['regular' => '0'],
		);
		$this->assertNotNull($topic, 'the forum has no topic a member can see');
		$this->topic = (int) $topic['id_topic'];

		$this->member = $this->makeMember();

		// Email only, so that only the email is involved.
		Notify::setNotifyPrefs($this->member, [
			'topic_notify_' . $this->topic => Notify::PREF_EMAIL,
			'msg_notify_pref' => $frequency,
			'msg_notify_type' => 1,
		]);

		Db::$db->insert('insert', '{db_prefix}log_notify', ['id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'sent' => 'int'], [[$this->member, $this->topic, 0, 0]], []);

		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			['task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
			[[
				'SMF\\Tasks\\CreatePost_Notify',
				json_encode([
					'msgOptions' => [
						'id' => self::MSG,
						'subject' => 'Re: an integration test',
						'body' => 'A reply from the integration suite.',
						'icon' => 'xx',
						'smileys_enabled' => true,
						'attachments' => [],
						'approved' => 1,
						'poster_time' => time(),
						'send_notifications' => true,
						'quoted_members' => [],
					],
					'topicOptions' => [
						'id' => $this->topic,
						'board' => (int) $topic['id_board'],
					],
					'posterOptions' => [
						'id' => $this->adminId(),
						'name' => self::adminName(),
						'email' => '',
						'update_post_count' => true,
						'ip' => '127.0.0.1',
					],
					'type' => 'reply',
				]),
				0,
			]],
			['id_task'],
		);

		$this->http->get('cron.php?ts=' . (time() - time() % 15));

		$mail = $this->queryRow('SELECT body FROM {db_prefix}mail_queue WHERE recipient = {string:to}', ['to' => $this->email()]);
		$this->assertNotNull($mail, 'the watching member was not emailed');

		return (string) $mail['body'];
	}

	/**
	 * Makes a regular member.
	 *
	 * @return int The member's id.
	 */
	private function makeMember(): int
	{
		$name = 'watcher_' . bin2hex(random_bytes(4));

		return (int) Db::$db->insert(
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
	}

	/**
	 * @return string The test member's email address.
	 */
	private function email(): string
	{
		return (string) ($this->queryRow('SELECT email_address FROM {db_prefix}members WHERE id_member = {int:m}', ['m' => $this->member])['email_address'] ?? '');
	}
}
