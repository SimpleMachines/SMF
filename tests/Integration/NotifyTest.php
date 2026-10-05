<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use SMF\Actions\Notify;
use SMF\Actions\NotifyTopic;
use SMF\Db\DatabaseApi as Db;

/**
 * Turning on notifications for a member other than the one making the request.
 *
 * That is what an unsubscribe token is for: the link in an email changes the
 * preferences of the member it was sent to, whoever happens to follow it. The
 * notify page it leads to offers to subscribe again, through the same token.
 */
#[CoversClass(Notify::class)]
class NotifyTest extends IntegrationTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * changeBoardTopicPref() wrote the log_notify row for User::$me rather
	 * than for the member whose preference was being changed. Subscribing
	 * through a token therefore subscribed whoever followed the link, which
	 * for a guest meant a row for member 0, and left the member themselves
	 * with a preference saying they were subscribed and nothing to send to.
	 */
	public function testSubscribingThroughATokenRecordsTheMemberItIsFor(): void
	{
		$this->actingAs($this->adminId());

		$topic = (int) $this->queryRow(
			'SELECT id_topic
			FROM {db_prefix}topics
			ORDER BY id_topic
			LIMIT 1',
		)['id_topic'];

		$admin_was_subscribed = $this->isSubscribed($this->adminId(), $topic);
		$member = $this->makeMember();

		$action = new \ReflectionClass(NotifyTopic::class)->newInstanceWithoutConstructor();
		new \ReflectionProperty(Notify::class, 'member_info')->setValue(null, ['id' => $member, 'email' => 'notify_test@example.com']);
		new \ReflectionProperty(Notify::class, 'id')->setValue($action, $topic);
		new \ReflectionProperty(Notify::class, 'alert_pref')->setValue($action, Notify::PREF_BOTH);

		new \ReflectionMethod(Notify::class, 'changeBoardTopicPref')->invoke($action);

		$this->assertTrue($this->isSubscribed($member, $topic), 'the member the token is for was not subscribed');
		$this->assertSame($admin_was_subscribed, $this->isSubscribed($this->adminId(), $topic), 'the member making the request was subscribed instead');
		$this->assertNoErrorsLogged();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * @param int $member The member.
	 * @param int $topic The topic.
	 * @return bool Whether log_notify has them down for the topic.
	 */
	private function isSubscribed(int $member, int $topic): bool
	{
		return $this->queryRow(
			'SELECT id_member
			FROM {db_prefix}log_notify
			WHERE id_member = {int:member}
				AND id_topic = {int:topic}',
			['member' => $member, 'topic' => $topic],
		) !== null;
	}

	/**
	 * Makes a member, inside this test's transaction.
	 *
	 * @return int The member's id.
	 */
	private function makeMember(): int
	{
		$name = 'notify_' . bin2hex(random_bytes(4));

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
}
