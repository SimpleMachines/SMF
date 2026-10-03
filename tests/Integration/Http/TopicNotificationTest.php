<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Actions\Notify;
use SMF\Db\DatabaseApi as Db;

/**
 * Turning off email notifications for a topic, which is what the unsubscribe
 * link in a reply notification does.
 */
#[CoversNothing]
class TopicNotificationTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The topic used.
	 */
	private int $topic = 0;

	/**
	 * @var array What to put back afterwards: 'log_notify', 'topic_pref' and
	 *    'general_pref', each null when there was no row.
	 */
	private array $backup = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * A member subscribed by posting keeps their alerts when the emails stop.
	 *
	 * Posting with "notify me" ticked records the subscription in log_notify
	 * only, with no preference for that topic, and the reply notifications
	 * follow the member's general preference instead. Turning the emails off
	 * read the topic's own preference regardless: it logged two errors for
	 * the missing row, took the preference as 0, and so turned the alerts off
	 * and dropped the subscription as well.
	 */
	public function testTurningOffEmailsLeavesTheAlertsOfAMemberSubscribedByPosting(): void
	{
		$admin = $this->adminId();
		$this->topic = (int) $this->queryRow('SELECT id_topic FROM {db_prefix}topics ORDER BY id_topic LIMIT 1')['id_topic'];

		$this->backup = [
			'log_notify' => $this->queryRow('SELECT id_member FROM {db_prefix}log_notify WHERE id_member = {int:m} AND id_topic = {int:t}', ['m' => $admin, 't' => $this->topic]),
			'topic_pref' => $this->pref('topic_notify_' . $this->topic),
			'general_pref' => $this->pref('topic_notify'),
		];

		// Subscribed the way posting with "notify me" ticked does it, with
		// emails and alerts both on in general.
		Notify::deleteNotifyPrefs($admin, ['topic_notify_' . $this->topic]);
		Notify::setNotifyPrefs($admin, ['topic_notify' => Notify::PREF_BOTH]);
		Db::$db->insert('ignore', '{db_prefix}log_notify', ['id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'], [[$admin, $this->topic, 0]], ['id_member', 'id_topic', 'id_board']);

		$this->signInAsAdmin();
		$page = $this->fetch('?topic=' . $this->topic . '.0');
		$response = $this->http->get('?action=notifytopic;topic=' . $this->topic . '.0;sa=off;' . $this->sessionQuery($page));

		$this->assertLessThan(400, $response->status, 'turning off emails failed: ' . $response->errorText());
		$this->assertSame(Notify::PREF_ALERT, $this->pref('topic_notify_' . $this->topic), 'the alerts did not survive turning the emails off');
		$this->assertNotNull(
			$this->queryRow('SELECT id_member FROM {db_prefix}log_notify WHERE id_member = {int:m} AND id_topic = {int:t}', ['m' => $admin, 't' => $this->topic]),
			'the subscription was dropped, so no alerts will come either',
		);
		$this->assertNoErrorsLogged('turning off emails logged something.' . "\n");
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->topic !== 0 && isset(Db::$db)) {
			$admin = $this->adminId();

			if ($this->backup['log_notify'] === null) {
				Db::$db->query('DELETE FROM {db_prefix}log_notify WHERE id_member = {int:m} AND id_topic = {int:t}', ['m' => $admin, 't' => $this->topic]);
			}

			foreach (['topic_pref' => 'topic_notify_' . $this->topic, 'general_pref' => 'topic_notify'] as $key => $pref) {
				if ($this->backup[$key] === null) {
					Notify::deleteNotifyPrefs($admin, [$pref]);
				} else {
					Notify::setNotifyPrefs($admin, [$pref => $this->backup[$key]]);
				}
			}

			$this->topic = 0;
		}

		parent::tearDown();
	}

	/**
	 * @param string $pref The preference.
	 * @return ?int The admin's own value for it, or null for none.
	 */
	private function pref(string $pref): ?int
	{
		$row = $this->queryRow(
			'SELECT alert_value FROM {db_prefix}user_alerts_prefs WHERE id_member = {int:m} AND alert_pref = {string:p}',
			['m' => $this->adminId(), 'p' => $pref],
		);

		return $row === null ? null : (int) $row['alert_value'];
	}

	/**
	 * The session variable and value, as a query string pair.
	 *
	 * @param \SMF\Tests\Support\HttpResponse $page Any page for a signed in member.
	 * @return string The pair.
	 */
	private function sessionQuery(\SMF\Tests\Support\HttpResponse $page): string
	{
		foreach ($page->xpath('//a[@href]') as $link) {
			if (preg_match('~;([0-9a-f]+=[0-9a-f]{32})\b~', html_entity_decode($link->getAttribute('href')), $match)) {
				return $match[1];
			}
		}

		$this->fail('no link on the page carries the session');
	}
}
