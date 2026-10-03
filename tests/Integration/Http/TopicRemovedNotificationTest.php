<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Actions\Notify;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Tests\Support\HttpResponse;

/**
 * The email a member watching a topic gets when it is removed.
 *
 * The topic is removed the way a moderator removes it, and the notification
 * task runs the way the forum runs it: through cron.php, with the mail queue
 * held so the email can be read from it.
 */
#[CoversNothing]
class TopicRemovedNotificationTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The member this test made.
	 */
	private int $member = 0;

	/**
	 * @var string The subject of the topic this test posted.
	 */
	private string $subject = '';

	/**
	 * @var ?string mail_next_send before the test.
	 */
	private ?string $mail_next_send = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Members watching a removed topic are told.
	 *
	 * The notice was queued before the topic was removed, but removing it also
	 * deleted the record of who was watching it, and the task only looked for
	 * watchers there once it ran. It never found anyone, so this email has not
	 * been sent since notifications moved into background tasks.
	 */
	public function testMembersWatchingARemovedTopicAreTold(): void
	{
		$this->mail_next_send = $this->rawSetting('mail_next_send');
		Config::updateModSettings(['mail_next_send' => time() + 3600]);

		$this->signInAsAdmin();

		$board = (int) $this->queryRow(
			'SELECT id_board FROM {db_prefix}boards WHERE FIND_IN_SET({string:regular}, member_groups) != 0 AND redirect = {string:empty} ORDER BY id_board LIMIT 1',
			['regular' => '0', 'empty' => ''],
		)['id_board'];

		$this->subject = 'Integration test removal ' . bin2hex(random_bytes(4));
		$form = $this->fetch('?action=post;board=' . $board . '.0');
		$this->submitForm($form, ['subject' => $this->subject, 'message' => 'To be removed.', 'post' => 'Post'], '//form[contains(@action, "action=post2")]');

		$topic = (int) ($this->queryRow('SELECT id_topic FROM {db_prefix}messages WHERE subject = {string:s}', ['s' => $this->subject])['id_topic'] ?? 0);
		$this->assertNotSame(0, $topic, 'the topic was not posted');

		// A member watching it by email. Email alone, so that only the email
		// is involved.
		$this->member = $this->makeMember();
		Notify::setNotifyPrefs($this->member, ['topic_notify_' . $topic => Notify::PREF_EMAIL, 'msg_notify_pref' => 1, 'msg_notify_type' => 1]);
		Db::$db->insert('insert', '{db_prefix}log_notify', ['id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'sent' => 'int'], [[$this->member, $topic, 0, 0]], []);

		$page = $this->fetch('?topic=' . $topic . '.0');
		$response = $this->http->get('?action=removetopic2;topic=' . $topic . '.0;' . $this->sessionQuery($page));
		$this->assertLessThan(400, $response->status, 'removing the topic failed: ' . $response->errorText());

		$this->http->get('cron.php?ts=' . (time() - time() % 15));

		$this->assertNotNull(
			$this->queryRow(
				'SELECT id_mail FROM {db_prefix}mail_queue WHERE recipient = {string:to} AND body LIKE {string:body}',
				['to' => $this->email(), 'body' => '%A topic you are watching has been removed.%'],
			),
			'the member watching the topic was not told it was removed',
		);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->member !== 0 && isset(Db::$db)) {
			Db::$db->query('DELETE FROM {db_prefix}mail_queue WHERE recipient = {string:to}', ['to' => $this->email()]);
			Db::$db->query('DELETE FROM {db_prefix}background_tasks WHERE task_data LIKE {string:s}', ['s' => '%' . $this->subject . '%']);

			foreach (['log_notify', 'user_alerts_prefs', 'members'] as $table) {
				Db::$db->query('DELETE FROM {db_prefix}' . $table . ' WHERE id_member = {int:m}', ['m' => $this->member]);
			}

			$this->member = 0;
		}

		if ($this->mail_next_send !== null || $this->subject !== '') {
			Config::updateModSettings(['mail_next_send' => $this->mail_next_send ?? '0']);
			$this->mail_next_send = null;
		}

		parent::tearDown();
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

	/**
	 * The session variable and value, as a query string pair.
	 *
	 * @param HttpResponse $page Any page for a signed in member.
	 * @return string The pair.
	 */
	private function sessionQuery(HttpResponse $page): string
	{
		foreach ($page->xpath('//a[@href]') as $link) {
			if (preg_match('~;([0-9a-f]+=[0-9a-f]{32})\b~', html_entity_decode($link->getAttribute('href')), $match)) {
				return $match[1];
			}
		}

		$this->fail('no link on the page carries the session');
	}
}
