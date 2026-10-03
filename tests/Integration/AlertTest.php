<?php

declare(strict_types=1);

namespace SMF\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversMethod;
use SMF\Alert;
use SMF\Db\DatabaseApi as Db;

/**
 * Alert::createBatch(), which every notification task saves its alerts with.
 */
#[CoversMethod(Alert::class, 'createBatch')]
class AlertTest extends IntegrationTestCase
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * Every alert in a batch is saved, each as itself.
	 *
	 * The visibility checks walked the batch by reference and never let go of
	 * the reference, so the loop that built the rows wrote every alert into
	 * the last one. In a batch of three, the third was lost and the second was
	 * saved twice.
	 */
	public function testEveryAlertInABatchIsSavedAsItself(): void
	{
		$members = [$this->makeMember(), $this->makeMember(), $this->makeMember()];

		Alert::createBatch(array_map(fn(int $member) => $this->memberAlert($member), $members));

		foreach ($members as $member) {
			$this->assertSame(
				[$member],
				$this->alertedAbout($member),
				'member ' . $member . ' did not get exactly the alert made for them',
			);
		}

		$this->assertNoErrorsLogged();
	}

	/**
	 * Alerts about messages are checked for visibility without complaint.
	 *
	 * The check looked them up by id_alert, which an alert does not have until
	 * it is saved, so every batch logged an undefined array key three times,
	 * and every alert in it was checked under the same empty key.
	 */
	public function testAlertsAboutAMessageAreSavedWithoutErrors(): void
	{
		$msg = (int) $this->queryRow(
			'SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE FIND_IN_SET({string:regular}, b.member_groups) != 0
				AND m.approved = {int:approved}
			ORDER BY m.id_msg
			LIMIT 1',
			['regular' => '0', 'approved' => 1],
		)['id_msg'];

		$members = [$this->makeMember(), $this->makeMember()];

		Alert::createBatch(array_map(fn(int $member) => [
			'alert_time' => time(),
			'id_member' => $member,
			'id_member_started' => 0,
			'member_name' => '',
			'content_type' => 'msg',
			'content_id' => $msg,
			'content_action' => 'quote',
			'is_read' => 0,
			'extra' => '',
		], $members));

		foreach ($members as $member) {
			$this->assertSame([$msg], $this->alertedAbout($member, 'msg'), 'member ' . $member . ' got no alert about a message they can see');
		}

		$this->assertNoErrorsLogged();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * An alert about the member themselves, which needs no visibility check.
	 *
	 * @param int $member The member.
	 * @return array The alert's properties.
	 */
	private function memberAlert(int $member): array
	{
		return [
			'alert_time' => time(),
			'id_member' => $member,
			'id_member_started' => $member,
			'member_name' => '',
			'content_type' => 'member',
			'content_id' => $member,
			'content_action' => 'register_standard',
			'is_read' => 0,
			'extra' => '',
		];
	}

	/**
	 * @param int $member The member.
	 * @param string $type The content type of the alerts to look at.
	 * @return array The content IDs of their alerts of that type.
	 */
	private function alertedAbout(int $member, string $type = 'member'): array
	{
		$request = Db::$db->query(
			'SELECT content_id
			FROM {db_prefix}user_alerts
			WHERE id_member = {int:member}
				AND content_type = {string:type}
			ORDER BY id_alert',
			['member' => $member, 'type' => $type],
		);

		$ids = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$ids[] = (int) $row['content_id'];
		}

		Db::$db->free_result($request);

		return $ids;
	}

	/**
	 * Makes a regular member, inside this test's transaction.
	 *
	 * @return int The member's id.
	 */
	private function makeMember(): int
	{
		$name = 'alert_' . bin2hex(random_bytes(4));

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
