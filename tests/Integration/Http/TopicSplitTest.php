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
use SMF\Topic;

/**
 * The page for choosing which posts to split off a topic.
 */
#[CoversNothing]
class TopicSplitTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Topics this test made, for tearDown() to remove.
	 */
	private array $created_topics = [];

	/****************
	 * Public methods
	 ****************/

	/**
	 * The posts to choose from are listed oldest first.
	 *
	 * The edit in the test is what makes PostgreSQL misorder the list. On MySQL
	 * the test passes either way.
	 *
	 * Expected: after the first post of a three post topic is edited,
	 *           ?action=splittopics lists the posts in the order of their
	 *           ids, with nothing logged.
	 * Guards:   the queries behind the list only said ORDER BY when the member
	 *           views topics newest first, and otherwise left the order to the
	 *           database. MySQL happens to return InnoDB rows in primary key
	 *           order, so it never showed there. PostgreSQL returns them in the
	 *           order they sit in the table, and an UPDATE writes a row's new
	 *           version at the end, so a post that had been edited was listed
	 *           after the ones posted later. With LIMIT on the same queries, it
	 *           could also turn up on two pages of the list and be missing from
	 *           another.
	 *
	 * @link https://github.com/SimpleMachines/SMF/pull/9714
	 */
	public function testThePostsAreListedOldestFirst(): void
	{
		$this->signInAsAdmin();

		$subject = 'Integration test split ' . bin2hex(random_bytes(6));
		$topic_id = $this->startTopic($subject);

		$this->reply($topic_id, $subject, 'The first reply.');
		$this->reply($topic_id, $subject, 'The second reply.');

		$messages = $this->messageIds($topic_id);

		$this->assertCount(3, $messages, 'the topic should hold the first post and two replies');

		// Edit the first post in place, which moves its row on PostgreSQL.
		Db::$db->query(
			'UPDATE {db_prefix}messages
			SET body = {string:body}
			WHERE id_msg = {int:id_msg}',
			[
				'body' => 'The first post, edited.',
				'id_msg' => $messages[0],
			],
		);

		$page = $this->fetch('?action=splittopics;topic=' . $topic_id . '.0;at=' . $messages[0]);

		$listed = [];

		foreach ($page->xpath('//ul[@id="messages_not_selected"]/li') as $item) {
			$listed[] = (int) substr($item->getAttribute('id'), \strlen('not_selected_'));
		}

		$this->assertSame($messages, $listed, 'the posts to split are not listed oldest first');
		$this->assertNoErrorsLogged('the split page logged something.' . "\n");
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		// Before the parent runs, while the connection is still ours.
		if ($this->created_topics !== []) {
			Topic::remove($this->created_topics);

			$this->created_topics = [];
		}

		parent::tearDown();
	}

	/**
	 * Starts a topic in the first board through the posting form.
	 *
	 * @param string $subject The subject.
	 * @return int The new topic's id.
	 */
	private function startTopic(string $subject): int
	{
		$before = $this->latestTopicId();

		$form = $this->fetch('?action=post;board=1.0');

		$posted = $this->submitForm($form, [
			'subject' => $subject,
			'message' => 'The first post.',
			'post' => 'Post',
		], '//form[contains(@action, "action=post2")]');

		$topic_id = $this->latestTopicId();

		$this->assertGreaterThan(
			$before,
			$topic_id,
			'no new topic appeared after posting: ' . $posted->errorText(),
		);

		$this->created_topics[] = $topic_id;

		return $topic_id;
	}

	/**
	 * Replies to a topic through the posting form.
	 *
	 * @param int $topic_id The topic.
	 * @param string $subject Its subject.
	 * @param string $body What to say.
	 */
	private function reply(int $topic_id, string $subject, string $body): void
	{
		$form = $this->fetch('?action=post;topic=' . $topic_id . '.0');

		$posted = $this->submitForm($form, [
			'subject' => 'Re: ' . $subject,
			'message' => $body,
			'post' => 'Post',
		], '//form[contains(@action, "action=post2")]');

		$this->assertLessThan(400, $posted->status, 'replying returned ' . $posted->status . ': ' . $posted->errorText());
	}

	/**
	 * The ids of a topic's posts, oldest first.
	 *
	 * @param int $topic_id The topic.
	 * @return array The ids.
	 */
	private function messageIds(int $topic_id): array
	{
		$request = Db::$db->query(
			'SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:topic}
			ORDER BY id_msg',
			[
				'topic' => $topic_id,
			],
		);

		$ids = [];

		while ($row = Db::$db->fetch_assoc($request)) {
			$ids[] = (int) $row['id_msg'];
		}

		Db::$db->free_result($request);

		return $ids;
	}

	/**
	 * The newest topic on the forum.
	 *
	 * @return int Its id, or 0.
	 */
	private function latestTopicId(): int
	{
		$row = $this->queryRow('SELECT COALESCE(MAX(id_topic), 0) AS id FROM {db_prefix}topics');

		return (int) ($row['id'] ?? 0);
	}
}
