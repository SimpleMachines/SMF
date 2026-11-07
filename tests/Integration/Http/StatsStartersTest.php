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
use SMF\Db\DatabaseApi as Db;

/**
 * The statistics centre's list of the members who started the most topics.
 *
 * The members and topics it counts are made for the test and removed again
 * afterwards: an HTTP test runs on another connection than the forum, so it
 * cannot be rolled back.
 */
#[CoversNothing]
class StatsStartersTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array The members made for the test.
	 */
	private array $member_ids = [];

	/**
	 * @var array The topics made for the test.
	 */
	private array $topic_ids = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * The top topic starters list holds ten members.
	 *
	 * Expected: with eleven members who each started two topics, ?action=stats
	 *           lists exactly ten starters, with nothing logged.
	 * Guards:   the list skipped the members ranked after index 10, which kept
	 *           indexes 0 to 10: eleven members under a heading that says ten.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/58920877d Introduced by "Show 10 top topic starters"
	 * @link https://github.com/SimpleMachines/SMF/pull/9725
	 */
	public function testTheTopTenStartersAreTen(): void
	{
		// Eleven members who have started two topics each, which ranks them
		// above the one topic each the forum starts with.
		for ($i = 1; $i <= 11; $i++) {
			$member_id = $this->makeMember('Stats Starter ' . $i);

			$this->makeTopicStartedBy($member_id, 0);
			$this->makeTopicStartedBy($member_id, 1);
		}

		$page = $this->fetch('?action=stats');

		$starters = $page->xpath('//div[contains(@class, "half_content")][.//span[contains(@class, "starters")]]//dl/dt');

		$this->assertSame(10, $starters->length, 'the top ten starters list the wrong number. The forum said: ' . $page->errorText());

		$this->assertNoErrorsLogged('the stats page logged something.' . "\n");
	}

	/**
	 * Starters with no member do not take a place in the top ten.
	 *
	 * Topics credited to nobody, or to a member since deleted, are counted
	 * too, but there is no name to list. Such a starter must not take one of
	 * the ten places and leave it empty.
	 *
	 * Expected: with eleven members who each started two topics and three
	 *           topics credited to no member, ?action=stats lists exactly ten
	 *           starters, with nothing logged.
	 * Guards:   a starter with no member to name kept its place in the list
	 *           empty, so the ten places held fewer than ten members.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9725
	 */
	public function testStartersWithNoMemberDoNotTakeAPlace(): void
	{
		for ($i = 1; $i <= 11; $i++) {
			$member_id = $this->makeMember('Stats Starter ' . $i);

			$this->makeTopicStartedBy($member_id, 0);
			$this->makeTopicStartedBy($member_id, 1);
		}

		// Ranked first, with more topics than anybody, but not a member.
		for ($n = 0; $n < 3; $n++) {
			$this->makeTopicStartedBy(0, $n);
		}

		$page = $this->fetch('?action=stats');

		$starters = $page->xpath('//div[contains(@class, "half_content")][.//span[contains(@class, "starters")]]//dl/dt');

		$this->assertSame(10, $starters->length, 'the top ten starters list the wrong number. The forum said: ' . $page->errorText());

		$this->assertNoErrorsLogged('the stats page logged something.' . "\n");
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->topic_ids !== []) {
			Db::$db->query(
				'DELETE FROM {db_prefix}topics
				WHERE id_topic IN ({array_int:topics})',
				['topics' => $this->topic_ids],
			);

			$this->topic_ids = [];
		}

		if ($this->member_ids !== []) {
			Db::$db->query(
				'DELETE FROM {db_prefix}members
				WHERE id_member IN ({array_int:members})',
				['members' => $this->member_ids],
			);

			$this->member_ids = [];
		}

		parent::tearDown();
	}

	/**
	 * Makes a member.
	 *
	 * @param string $name Their name.
	 * @return int The member's id.
	 */
	private function makeMember(string $name): int
	{
		$login = 'starter_' . bin2hex(random_bytes(4));

		$id = (int) Db::$db->insert(
			'insert',
			'{db_prefix}members',
			[
				'member_name' => 'string',
				'real_name' => 'string',
				'email_address' => 'string',
				'is_activated' => 'int',
				'buddy_list' => 'string',
				'signature' => 'string',
				'ignore_boards' => 'string',
			],
			[[$login, $name, $login . '@example.com', 1, '', '', '']],
			['id_member'],
			1,
		);

		$this->member_ids[] = $id;

		return $id;
	}

	/**
	 * Makes a topic row credited to a member. The list only counts topics, so
	 * it needs no messages, but each topic needs message ids of its own: the
	 * last message and the board are a unique key.
	 *
	 * @param int $member_id The member who started it.
	 * @param int $n Which of the member's topics this is, from 0.
	 */
	private function makeTopicStartedBy(int $member_id, int $n): void
	{
		$message_id = 900000000 + $member_id * 10 + $n;

		$this->topic_ids[] = (int) Db::$db->insert(
			'insert',
			'{db_prefix}topics',
			[
				'id_board' => 'int',
				'id_first_msg' => 'int',
				'id_last_msg' => 'int',
				'id_member_started' => 'int',
				'id_member_updated' => 'int',
				'approved' => 'int',
			],
			[[1, $message_id, $message_id, $member_id, $member_id, 1]],
			['id_topic'],
			1,
		);
	}
}
