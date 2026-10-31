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
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Tests\Support\HttpResponse;

/**
 * The reminder sent when a paid subscription is about to run out, run from
 * the admin's scheduled tasks page as an administrator would run it.
 */
#[CoversNothing]
class PaidSubsReminderTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	private const NAME = 'Integration test subscription';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The member this test made.
	 */
	private int $member = 0;

	/**
	 * @var int The subscription this test made.
	 */
	private int $subscription = 0;

	/**
	 * @var ?string mail_next_send before the test.
	 */
	private ?string $mail_next_send = null;

	/****************
	 * Public methods
	 ****************/

	/**
	 * A member whose subscription is about to run out is reminded once.
	 *
	 * Expected: running the paid_subscriptions task emails the reminder, saves
	 *           a paidsubs alert and sets reminder_sent to 1.
	 * Guards:   the alert was built with the member and subscription IDs as the
	 *           database returned them, which can be strings, and saving it
	 *           ended in a TypeError from User's constructor. That came after
	 *           the email had gone and before the reminder was marked as sent,
	 *           so the run failed and the next one sent the same reminder again.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/8d1de9be9 Introduced by "Strict type more files"
	 * @link https://github.com/SimpleMachines/SMF/pull/9745
	 */
	public function testTheReminderIsSentAndMarkedAsSent(): void
	{
		$this->mail_next_send = $this->rawSetting('mail_next_send');
		Config::updateModSettings(['mail_next_send' => time() + 3600]);

		$this->member = $this->makeMember();
		Notify::setNotifyPrefs($this->member, ['paidsubs_expiring' => Notify::PREF_BOTH]);

		// A subscription with a week's reminder, running out in three days.
		$this->subscription = (int) Db::$db->insert(
			'insert',
			'{db_prefix}subscriptions',
			[
				'name' => 'string', 'description' => 'string', 'cost' => 'string', 'length' => 'string', 'id_group' => 'int',
				'add_groups' => 'string', 'active' => 'int', 'repeatable' => 'int', 'allow_partial' => 'int', 'reminder' => 'int',
				'email_complete' => 'string',
			],
			[[self::NAME, '', '{"fixed":1}', '1M', 0, '', 1, 0, 0, 7, '']],
			['id_subscribe'],
			1,
		);

		Db::$db->insert(
			'insert',
			'{db_prefix}log_subscribed',
			[
				'id_subscribe' => 'int', 'id_member' => 'int', 'old_id_group' => 'int', 'start_time' => 'int', 'end_time' => 'int',
				'status' => 'int', 'payments_pending' => 'int', 'pending_details' => 'string', 'reminder_sent' => 'int', 'vendor_ref' => 'string',
			],
			[[$this->subscription, $this->member, 0, time() - 86400 * 27, time() + 86400 * 3, 1, 0, '', 0, '']],
			['id_sublog'],
		);

		$this->signInAsAdmin();
		$page = $this->http->get('?action=admin');

		if ($page->xpath('//form[.//input[@name="admin_pass"]]')->length > 0) {
			$this->submitForm($page, ['admin_pass' => self::adminPassword()], '//form[.//input[@name="admin_pass"]]');
		}

		$task = (int) $this->queryRow('SELECT id_task FROM {db_prefix}scheduled_tasks WHERE task = {string:task}', ['task' => 'paid_subscriptions'])['id_task'];
		$page = $this->fetch('?action=admin;area=scheduledtasks');
		$response = $this->http->post('?action=admin;area=scheduledtasks', $this->hiddenFields($page) + ['run_task' => [$task => 'on'], 'run' => 'Run now']);

		$this->assertLessThan(500, $response->status, 'running the task failed: ' . $response->errorText());

		$this->assertSame(
			'1',
			(string) $this->queryRow('SELECT reminder_sent FROM {db_prefix}log_subscribed WHERE id_subscribe = {int:s}', ['s' => $this->subscription])['reminder_sent'],
			'the reminder was not marked as sent, so it will be sent again',
		);

		$this->assertNotNull(
			$this->queryRow('SELECT id_mail FROM {db_prefix}mail_queue WHERE recipient = {string:to} AND body LIKE {string:name}', ['to' => $this->email(), 'name' => '%' . self::NAME . '%']),
			'no reminder was emailed',
		);

		$this->assertNotNull(
			$this->queryRow('SELECT id_alert FROM {db_prefix}user_alerts WHERE id_member = {int:m} AND content_type = {literal:paidsubs}', ['m' => $this->member]),
			'no reminder alert was saved',
		);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->member !== 0 && isset(Db::$db)) {
			Db::$db->query('DELETE FROM {db_prefix}mail_queue WHERE recipient = {string:to}', ['to' => $this->email()]);

			foreach (['log_subscribed', 'user_alerts', 'user_alerts_prefs', 'members'] as $table) {
				Db::$db->query('DELETE FROM {db_prefix}' . $table . ' WHERE id_member = {int:m}', ['m' => $this->member]);
			}

			Db::$db->query('DELETE FROM {db_prefix}subscriptions WHERE id_subscribe = {int:s}', ['s' => $this->subscription]);

			Config::updateModSettings(['mail_next_send' => $this->mail_next_send ?? '0']);

			$this->member = 0;
			$this->subscription = 0;
		}

		parent::tearDown();
	}

	/**
	 * Makes a member who is not the one running the task.
	 *
	 * @return int The member's id.
	 */
	private function makeMember(): int
	{
		$name = 'paidsub_' . bin2hex(random_bytes(4));

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
	 * Every hidden input on the page outside the search form.
	 *
	 * @param HttpResponse $page The page.
	 * @return array Name => value.
	 */
	private function hiddenFields(HttpResponse $page): array
	{
		$fields = [];

		foreach ($page->xpath('//input[@type="hidden"][not(ancestor::form[@id="search_form"])]') as $input) {
			if ($input->getAttribute('name') !== '') {
				$fields[$input->getAttribute('name')] = $input->getAttribute('value');
			}
		}

		return $fields;
	}
}
