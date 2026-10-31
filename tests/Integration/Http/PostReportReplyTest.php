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

/**
 * The email moderators get when someone else comments on a report about a post.
 *
 * It is sent by a background task, so the task is run the way the forum runs
 * it: through cron.php. The mail queue is held back for the length of the
 * test, so the email can be read from it before anything sends it.
 */
#[CoversNothing]
class PostReportReplyTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * A report that does not exist. The task only uses its ID in links and to
	 * find the earlier comments.
	 */
	private const REPORT = 7654322;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array Settings and preferences to put back: name => value, or null.
	 */
	private array $backup = [];

	/**
	 * @var string The administrator's email address.
	 */
	private string $email = '';

	/****************
	 * Public methods
	 ****************/

	/**
	 * A reply to a post report is emailed, with links to the post and the report.
	 *
	 * Expected: running MsgReportReply_Notify queues an email to the moderator
	 *           containing the post's link and
	 *           ?action=moderate;area=reportedposts;sa=details;rid=N.
	 * Guards:   the report queues the task with the message, topic and board IDs
	 *           as the database returned them, which can be strings. The task
	 *           handed the board ID straight to User::getAllowedTo(), which takes
	 *           ?int, so it died with a TypeError every time it ran and the
	 *           email was never sent.
	 *
	 * @link https://github.com/SimpleMachines/SMF/commit/110afa97a Introduced by "More strict typing"
	 * @link https://github.com/SimpleMachines/SMF/pull/9743
	 */
	public function testModeratorsAreEmailedWithLinksThatWork(): void
	{
		$admin = $this->adminId();
		$this->email = (string) $this->queryRow('SELECT email_address FROM {db_prefix}members WHERE id_member = {int:m}', ['m' => $admin])['email_address'];

		$post = $this->queryRow('SELECT id_msg, id_topic, id_board FROM {db_prefix}messages WHERE approved = 1 ORDER BY id_msg LIMIT 1');
		$this->assertNotNull($post, 'the forum has no posts');

		$this->backup = [
			'mail_next_send' => $this->rawSetting('mail_next_send'),
			'pref' => $this->queryRow('SELECT alert_value FROM {db_prefix}user_alerts_prefs WHERE id_member = {int:m} AND alert_pref = {string:p}', ['m' => $admin, 'p' => 'msg_report_reply'])['alert_value'] ?? null,
		];

		// Hold the queue, and have the administrator take the email alone,
		// which is what this test is about.
		Config::updateModSettings(['mail_next_send' => time() + 3600]);
		Notify::setNotifyPrefs($admin, ['msg_report_reply' => Notify::PREF_EMAIL]);

		$this->report($post);

		// The administrator commented first; then another moderator comments.
		$this->comment($admin, 'admin');
		$comment = $this->comment(0, 'Another moderator');

		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			['task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
			[[
				'SMF\\Tasks\\MsgReportReply_Notify',
				json_encode([
					'report_id' => self::REPORT,
					'comment_id' => $comment,
					// Strings, as the report passes them.
					'msg_id' => (string) $post['id_msg'],
					'topic_id' => (string) $post['id_topic'],
					'board_id' => (string) $post['id_board'],
					'sender_id' => 0,
					'sender_name' => 'Another moderator',
					'time' => time(),
				]),
				0,
			]],
			['id_task'],
		);

		$this->http->get('cron.php?ts=' . (time() - time() % 15));

		$mail = $this->queryRow(
			'SELECT body FROM {db_prefix}mail_queue WHERE recipient = {string:to} AND body LIKE {string:report}',
			['to' => $this->email, 'report' => '%rid=' . self::REPORT . '%'],
		);

		$this->assertNotNull($mail, 'no email was queued for the administrator');
		$this->assertStringContainsString(Config::$scripturl . '?topic=' . $post['id_topic'] . '.msg' . $post['id_msg'] . '#msg' . $post['id_msg'], $mail['body'], 'the link to the post is wrong');
		$this->assertStringContainsString(Config::$scripturl . '?action=moderate;area=reportedposts;sa=details;rid=' . self::REPORT, $mail['body'], 'the link to the report is wrong');
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->backup !== [] && isset(Db::$db)) {
			Db::$db->query('DELETE FROM {db_prefix}log_reported WHERE id_report = {int:r}', ['r' => self::REPORT]);
			Db::$db->query('DELETE FROM {db_prefix}log_comments WHERE id_notice = {int:r} AND comment_type = {literal:reportc}', ['r' => self::REPORT]);
			Db::$db->query('DELETE FROM {db_prefix}background_tasks WHERE task_data LIKE {string:r}', ['r' => '%"report_id":' . self::REPORT . '%']);
			Db::$db->query('DELETE FROM {db_prefix}mail_queue WHERE recipient = {string:to} AND body LIKE {string:report}', ['to' => $this->email, 'report' => '%rid=' . self::REPORT . '%']);
			Db::$db->query('DELETE FROM {db_prefix}user_alerts WHERE content_action = {literal:report_reply} AND extra LIKE {string:r}', ['r' => '%rid=' . self::REPORT . '%']);

			Config::updateModSettings(['mail_next_send' => $this->backup['mail_next_send'] ?? '0']);

			if ($this->backup['pref'] === null) {
				Notify::deleteNotifyPrefs($this->adminId(), ['msg_report_reply']);
			} else {
				Notify::setNotifyPrefs($this->adminId(), ['msg_report_reply' => (int) $this->backup['pref']]);
			}

			$this->backup = [];
		}

		parent::tearDown();
	}

	/**
	 * Reports the post.
	 *
	 * @param array $post The post's id_msg, id_topic and id_board.
	 */
	private function report(array $post): void
	{
		Db::$db->insert(
			'insert',
			'{db_prefix}log_reported',
			[
				'id_report' => 'int', 'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int',
				'membername' => 'string', 'subject' => 'string', 'body' => 'string', 'time_started' => 'int',
				'time_updated' => 'int', 'num_reports' => 'int', 'closed' => 'int', 'ignore_all' => 'int',
			],
			[[self::REPORT, (int) $post['id_msg'], (int) $post['id_topic'], (int) $post['id_board'], 0, 'Poster', 'Reported post', 'Body', time(), time(), 1, 0, 0]],
			['id_report'],
		);
	}

	/**
	 * Adds a moderator comment to the report.
	 *
	 * @param int $member Who made it.
	 * @param string $name Their name.
	 * @return int The comment's ID.
	 */
	private function comment(int $member, string $name): int
	{
		return (int) Db::$db->insert(
			'insert',
			'{db_prefix}log_comments',
			[
				'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'id_recipient' => 'int',
				'recipient_name' => 'string', 'log_time' => 'int', 'id_notice' => 'int', 'counter' => 'int', 'body' => 'string',
			],
			[[$member, $name, 'reportc', 0, '', time(), self::REPORT, 0, 'A comment.']],
			['id_comment'],
			1,
		);
	}
}
