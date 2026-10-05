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
 * Moving a topic and leaving a "MOVED:" redirection topic behind.
 */
#[CoversNothing]
class MoveTopicTest extends HttpTestCase
{
	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int The board this test made to move a topic into.
	 */
	private int $board = 0;

	/**
	 * @var array Topics this test created.
	 */
	private array $topics = [];

	/**
	 * @var string The subject of the topic this test moved.
	 */
	private string $subject = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * The redirection topic left behind by a move does not notify anyone.
	 *
	 * Expected: moving a topic with a redirection topic leaves the "MOVED: ..."
	 *           topic in the old board and queues no CreatePost_Notify task
	 *           for it.
	 * Guards:   the redirection topic was posted like any new topic, so everyone
	 *           watching the old board was emailed "New Topic: MOVED: ..." and
	 *           found it in their digest, on top of the notice about the move
	 *           that the topic's own watchers get.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/147061675 Introduced by "Shift watched topics and watched boards to use alerts"
	 * @link https://github.com/SimpleMachines/SMF/pull/9748
	 */
	public function testTheRedirectionTopicNotifiesNobody(): void
	{
		$from = (int) $this->queryRow('SELECT id_board FROM {db_prefix}boards WHERE redirect = {string:empty} ORDER BY id_board LIMIT 1', ['empty' => ''])['id_board'];
		$this->board = $this->makeBoard($from);

		$this->signInAsAdmin();

		$subject = $this->subject = 'Integration test move ' . bin2hex(random_bytes(4));
		$form = $this->fetch('?action=post;board=' . $from . '.0');
		$this->submitForm($form, ['subject' => $subject, 'message' => 'To be moved.', 'post' => 'Post'], '//form[contains(@action, "action=post2")]');

		$topic = (int) ($this->queryRow('SELECT id_topic FROM {db_prefix}messages WHERE subject = {string:s}', ['s' => $subject])['id_topic'] ?? 0);
		$this->assertNotSame(0, $topic, 'the topic to move was not posted');
		$this->topics[] = $topic;

		$form = $this->fetch('?action=movetopic;topic=' . $topic . '.0');
		$response = $this->submitForm($form, [
			'toboard' => (string) $this->board,
			'postRedirect' => '1',
			'reason' => 'This topic has been moved.',
		], '//form[contains(@action, "action=movetopic2")]');

		$this->assertLessThan(400, $response->status, 'moving failed: ' . $response->errorText());

		$redirect = $this->queryRow(
			'SELECT id_topic, id_msg FROM {db_prefix}messages WHERE id_board = {int:b} AND icon = {literal:moved} AND subject LIKE {string:s}',
			['b' => $from, 's' => '%' . $subject . '%'],
		);
		$this->assertNotNull($redirect, 'no redirection topic was left behind');
		$this->topics[] = (int) $redirect['id_topic'];

		$this->assertNull(
			$this->queryRow(
				'SELECT id_task FROM {db_prefix}background_tasks WHERE task_data LIKE {string:subject} AND task_data LIKE {string:type}',
				['subject' => '%MOVED: ' . $subject . '%', 'type' => '%"type":"topic"%'],
			),
			'posting the redirection topic queued notifications about it',
		);
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->topics !== []) {
			Db::$db->query('DELETE FROM {db_prefix}background_tasks WHERE task_data LIKE {string:s}', ['s' => '%' . $this->subject . '%']);

			Topic::remove($this->topics);
			$this->topics = [];
		}

		if ($this->board !== 0) {
			Db::$db->query('DELETE FROM {db_prefix}boards WHERE id_board = {int:b}', ['b' => $this->board]);
			$this->board = 0;
		}

		parent::tearDown();
	}

	/**
	 * Makes a board next to another one, for a topic to be moved into.
	 *
	 * @param int $next_to The other board.
	 * @return int The new board's id.
	 */
	private function makeBoard(int $next_to): int
	{
		$other = $this->queryRow('SELECT id_cat, id_profile FROM {db_prefix}boards WHERE id_board = {int:b}', ['b' => $next_to]);

		return (int) Db::$db->insert(
			'insert',
			'{db_prefix}boards',
			[
				'id_cat' => 'int', 'board_order' => 'int', 'member_groups' => 'string', 'id_profile' => 'int',
				'name' => 'string', 'description' => 'string', 'redirect' => 'string', 'deny_member_groups' => 'string',
			],
			[[(int) $other['id_cat'], 9999, '-1,0,2', (int) $other['id_profile'], 'Integration test move target', '', '', '']],
			['id_board'],
			1,
		);
	}
}
