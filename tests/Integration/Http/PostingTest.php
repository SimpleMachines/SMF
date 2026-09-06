<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Topic;

/**
 * Starting a topic and replying to it, over HTTP.
 *
 * This is the journey the forum exists for, and the one with the most behind it:
 * the editor, the session check, the security token, permissions, the post
 * itself, and then every counter and index SMF updates afterwards. Nothing short
 * of a real request covers that.
 *
 * These tests write, and an HTTP test cannot be rolled back - the request runs in
 * the web server's process on its own connection. So whatever they create, they
 * remove again in tearDown through SMF's own Topic::remove(), which puts the
 * board and member counts back the way deleting a topic in the browser would.
 */
#[CoversNothing]
class PostingTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Topics this test created, to be removed afterwards.
	 */
	private array $created_topics = [];

	/****************
	 * Public methods
	 ****************/

	public function testStartingATopicAndReplyingToIt(): void
	{
		$this->signInAsAdmin();

		$subject = 'Integration test topic ' . bin2hex(random_bytes(6));
		$body = 'Posted by the integration suite at ' . date('c') . '.';

		$topic_id = $this->startTopic($subject, $body);

		$topic = $this->fetch('?topic=' . $topic_id . '.0');

		$this->assertStringContainsString(
			$subject,
			$topic->text(),
			'the new topic does not show its own subject',
		);

		$this->assertStringContainsString($body, $topic->text(), 'the post body is missing');

		// And now a reply, which goes through a different form on a different
		// page and updates a different set of counters.
		$reply = 'A reply from the integration suite.';

		$form = $this->fetch('?action=post;topic=' . $topic_id . '.0');

		$posted = $this->submitForm($form, [
			'subject' => 'Re: ' . $subject,
			'message' => $reply,
			'post' => 'Post',
		], '//form[contains(@action, "action=post2")]');

		$this->assertLessThan(
			400,
			$posted->status,
			'replying returned ' . $posted->status . ': ' . $posted->errorText(),
		);

		$after = $this->fetch('?topic=' . $topic_id . '.0');

		$this->assertStringContainsString($reply, $after->text(), 'the reply is not on the topic');

		$this->assertSame(
			2,
			$this->countMessages($topic_id),
			'the topic should hold the first post and the reply',
		);

		$this->assertNoErrorsLogged('posting logged something.' . "\n");
	}

	/**
	 * A guest cannot post on a stock install, and the forum should say so rather
	 * than accept it.
	 */
	public function testAGuestCannotStartATopic(): void
	{
		$before = $this->countTopicsInBoard(1);

		$this->http->post('?action=post2;board=1', [
			'subject' => 'Integration test guest post',
			'message' => 'This should not be accepted.',
		]);

		$this->assertSame(
			$before,
			$this->countTopicsInBoard(1),
			'a guest with no session check managed to start a topic',
		);
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
	 * Starts a topic in the first board and returns its id.
	 *
	 * @param string $subject The subject.
	 * @param string $body The message.
	 * @return int The new topic's id.
	 */
	private function startTopic(string $subject, string $body): int
	{
		$before = $this->latestTopicId();

		$form = $this->fetch('?action=post;board=1.0');

		$this->assertGreaterThan(
			0,
			$form->xpath('//form[contains(@action, "action=post2")]')->length,
			'there is no posting form on the new topic page',
		);

		$posted = $this->submitForm($form, [
			'subject' => $subject,
			'message' => $body,
			// The button we are pressing. Without it the form's other button,
			// "preview", is the one SMF acts on.
			'post' => 'Post',
		], '//form[contains(@action, "action=post2")]');

		$this->assertLessThan(
			400,
			$posted->status,
			'posting returned ' . $posted->status . ': ' . $posted->errorText(),
		);

		$topic_id = $this->latestTopicId();

		// Quote the page when this fails. SMF answers a rejected post with a
		// perfectly ordinary 200 and the reason in a box, so without this the
		// only evidence is a topic id that did not move.
		$this->assertGreaterThan(
			$before,
			$topic_id,
			'no new topic appeared after posting. The forum said: '
			. ($posted->errorText() !== '' ? $posted->errorText() : '(nothing) - page title "' . $posted->title() . '"'),
		);

		$this->created_topics[] = $topic_id;

		return $topic_id;
	}

	/**
	 * The highest topic id in the forum.
	 *
	 * @return int The id, or 0 when there are no topics.
	 */
	private function latestTopicId(): int
	{
		$row = $this->queryRow('SELECT COALESCE(MAX(id_topic), 0) AS id FROM {db_prefix}topics');

		return (int) ($row['id'] ?? 0);
	}

	/**
	 * How many messages a topic holds.
	 *
	 * @param int $topic_id The topic.
	 * @return int The number of messages.
	 */
	private function countMessages(int $topic_id): int
	{
		$row = $this->queryRow(
			'SELECT COUNT(*) AS total
			FROM {db_prefix}messages
			WHERE id_topic = {int:topic}',
			['topic' => $topic_id],
		);

		return (int) ($row['total'] ?? 0);
	}

	/**
	 * How many topics a board holds.
	 *
	 * @param int $board_id The board.
	 * @return int The number of topics.
	 */
	private function countTopicsInBoard(int $board_id): int
	{
		$row = $this->queryRow(
			'SELECT COUNT(*) AS total
			FROM {db_prefix}topics
			WHERE id_board = {int:board}',
			['board' => $board_id],
		);

		return (int) ($row['total'] ?? 0);
	}
}
