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
use SMF\Actions\Notify;
use SMF\Db\DatabaseApi as Db;
use SMF\Tests\Support\HttpClient;

/**
 * The unsubscribe link at the bottom of a newsletter.
 *
 * The newsletter is sent through the admin form like any other, and the link is
 * read out of the mail queue, so what is followed is exactly what the member
 * would have received. Newsletters go out at priority 5, which always queues,
 * and only cron.php empties the queue, so nothing sends it before it is read.
 */
#[CoversNothing]
class NewsletterUnsubscribeTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The member this test made, so tearDown() can remove them.
	 */
	private int $member_id = 0;

	/**
	 * @var string Their email address, which is how the queue knows them.
	 */
	private string $email = '';

	/**
	 * @var int The highest id_action before the test, so the log entry the
	 *    newsletter adds can be removed again.
	 */
	private int $action_watermark = 0;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Following the unsubscribe link in a newsletter unsubscribes, there and then.
	 *
	 * Expected: the link in a newsletter answers "has been unsubscribed from
	 *           forum newsletters", turns the announcements preference off and
	 *           logs nothing.
	 * Guards:   the link carried no sa=off, so it led to a page asking whether
	 *           the member wanted newsletters, and they had to answer it before
	 *           anything happened. The links in board and topic notifications
	 *           go straight to the unsubscribe.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/6228a85e4 Introduced by "Implements unsubscribe tokens for email notifications"
	 * @link https://github.com/SimpleMachines/SMF/pull/9736
	 */
	public function testTheLinkUnsubscribesInOneStep(): void
	{
		$this->makeSubscribedMember();
		$this->sendNewsletter();

		$link = $this->unsubscribeLinkFromTheQueue();

		// As a guest, in a browser of their own.
		$response = new HttpClient()->get($link);

		$this->assertStringContainsString(
			'has been unsubscribed from forum newsletters',
			$response->text(),
			'the link did not unsubscribe: ' . ($response->errorText() ?: $response->title()),
		);
		$this->assertSame(0, $this->announcementsPref(), 'the preference was not turned off');
		$this->assertNoErrorsLogged('sending a newsletter and unsubscribing from it logged something.' . "\n");
	}

	/******************
	 * Internal methods
	 ******************/

	protected function setUp(): void
	{
		parent::setUp();

		$this->action_watermark = (int) ($this->queryRow('SELECT MAX(id_action) AS id FROM {db_prefix}log_actions')['id'] ?? 0);
	}

	protected function tearDown(): void
	{
		if ($this->member_id !== 0) {
			foreach (['members', 'user_alerts_prefs'] as $table) {
				Db::$db->query(
					'DELETE FROM {db_prefix}' . $table . '
					WHERE id_member = {int:member}',
					['member' => $this->member_id],
				);
			}

			Db::$db->query(
				'DELETE FROM {db_prefix}mail_queue
				WHERE recipient = {string:email}',
				['email' => $this->email],
			);

			Db::$db->query(
				'DELETE FROM {db_prefix}log_actions
				WHERE id_action > {int:watermark}
					AND action = {string:action}',
				['watermark' => $this->action_watermark, 'action' => 'newsletter'],
			);

			$this->member_id = 0;
		}

		parent::tearDown();
	}

	/**
	 * Makes a member who wants newsletters.
	 */
	private function makeSubscribedMember(): void
	{
		$name = 'newsletter_' . bin2hex(random_bytes(4));
		$this->email = $name . '@example.com';

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
			[[$name, $name, $this->email, '', time(), 1, '', '', '']],
			['id_member'],
			1,
		);

		Notify::setNotifyPrefs($this->member_id, ['announcements' => 1]);
	}

	/**
	 * Sends a plain text newsletter to that member alone, as the admin.
	 */
	private function sendNewsletter(): void
	{
		$this->signInAsAdmin();

		$page = $this->http->get('?action=admin');
		$form = '//form[.//input[@name="admin_pass"]]';

		if ($page->xpath($form)->length > 0) {
			$page = $this->submitForm($page, ['admin_pass' => self::adminPassword()], $form);
		}

		$page = $this->fetch('?action=admin;area=news;sa=mailingmembers');

		$session = [];

		foreach ($page->xpath('//input[@type="hidden"]') as $input) {
			if (preg_match('~^[0-9a-f]+$~', $input->getAttribute('name')) && preg_match('~^[0-9a-f]{32}$~', $input->getAttribute('value'))) {
				$session[$input->getAttribute('name')] = $input->getAttribute('value');
			}
		}

		$this->assertNotEmpty($session, 'the newsletter page carries no session field');

		$response = $this->http->post('?action=admin;area=news;sa=mailingsend', $session + [
			'subject' => 'Newsletter test',
			'message' => 'Hello {$member.name}.',
			'members' => (string) $this->member_id,
			'total_members' => '1',
		]);

		$this->assertNotNull(
			$this->queryRow(
				'SELECT id_mail
				FROM {db_prefix}mail_queue
				WHERE recipient = {string:email}',
				['email' => $this->email],
			),
			'the newsletter was not queued: ' . ($response->errorText() ?: $response->status),
		);
	}

	/**
	 * Reads the unsubscribe link out of the queued newsletter.
	 *
	 * @return string The link, relative to the forum.
	 */
	private function unsubscribeLinkFromTheQueue(): string
	{
		$body = (string) $this->queryRow(
			'SELECT body
			FROM {db_prefix}mail_queue
			WHERE recipient = {string:email}',
			['email' => $this->email],
		)['body'];

		$this->assertMatchesRegularExpression('~\?action=notifyannouncements[^\s"<>]*~', $body, 'the newsletter has no unsubscribe link');

		preg_match('~\?action=notifyannouncements[^\s"<>]*~', $body, $match);

		return $match[0];
	}

	/**
	 * @return ?int The member's newsletter preference, or null for none.
	 */
	private function announcementsPref(): ?int
	{
		$row = $this->queryRow(
			'SELECT alert_value
			FROM {db_prefix}user_alerts_prefs
			WHERE id_member = {int:member}
				AND alert_pref = {string:pref}',
			['member' => $this->member_id, 'pref' => 'announcements'],
		);

		return $row === null ? null : (int) $row['alert_value'];
	}
}
