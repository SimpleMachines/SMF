<?php

declare(strict_types=1);

namespace SMF\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\CoversNothing;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;

/**
 * The email moderators get when someone else comments on a report about a
 * member's profile.
 *
 * It is sent by a background task, so the task is run the way the forum runs
 * it: through cron.php. The mail queue is held back for the length of the
 * test, so the email can be read from it before anything sends it.
 */
#[CoversNothing]
class MemberReportReplyTest extends HttpTestCase
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * A report that does not exist. The task only uses its ID in links and to
	 * find the earlier comments.
	 */
	private const REPORT = 7654321;

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
	 * The email is sent, and its links lead to the profile and the report.
	 *
	 * The task asked for a template called reply_to_user_reports, which has
	 * never existed (it is reply_to_member_report), and for a member_name
	 * that the report never passed it. It failed every time it ran, so the
	 * email was never sent. Its profile link had also lost the "?" before
	 * "action", and its report link pointed at a moderation area that does
	 * not exist.
	 */
	public function testModeratorsAreEmailedWithLinksThatWork(): void
	{
		$admin = $this->adminId();
		$this->email = (string) $this->queryRow('SELECT email_address FROM {db_prefix}members WHERE id_member = {int:m}', ['m' => $admin])['email_address'];

		$this->backup = [
			'mail_next_send' => $this->rawSetting('mail_next_send'),
			'pref' => $this->queryRow('SELECT alert_value FROM {db_prefix}user_alerts_prefs WHERE id_member = {int:m} AND alert_pref = {string:p}', ['m' => $admin, 'p' => 'member_report_reply'])['alert_value'] ?? null,
		];

		// Hold the queue, and make sure the administrator wants the email.
		Config::updateModSettings(['mail_next_send' => time() + 3600]);
		\SMF\Actions\Notify::setNotifyPrefs($admin, ['member_report_reply' => 3]);

		// Someone else commented first; then the administrator comments.
		$this->comment(0, 'Someone else');
		$comment = $this->comment($admin, 'admin');

		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			['task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'],
			[[
				'SMF\\Tasks\\MemberReportReply_Notify',
				json_encode([
					'report_id' => self::REPORT,
					'user_id' => $admin,
					'user_name' => 'Reported Member',
					'sender_id' => $admin,
					'sender_name' => 'admin',
					'comment_id' => $comment,
					'time' => time(),
				]),
				0,
			]],
			['id_task'],
		);

		$this->http->get('cron.php?ts=' . (time() - time() % 15));

		$mail = $this->queryRow(
			'SELECT subject, body FROM {db_prefix}mail_queue WHERE recipient = {string:to} AND body LIKE {string:report}',
			['to' => $this->email, 'report' => '%rid=' . self::REPORT . '%'],
		);

		$this->assertNotNull($mail, 'no email was queued for the administrator');
		$this->assertStringContainsString('Reported Member', $mail['body'], 'the email does not name the reported member');
		$this->assertStringContainsString(Config::$scripturl . '?action=profile;u=' . $admin, $mail['body'], 'the profile link is broken');
		$this->assertStringContainsString(Config::$scripturl . '?action=moderate;area=reportedmembers;sa=details;rid=' . self::REPORT, $mail['body'], 'the report link is broken');
	}

	/******************
	 * Internal methods
	 ******************/

	protected function tearDown(): void
	{
		if ($this->backup !== [] && isset(Db::$db)) {
			Db::$db->query('DELETE FROM {db_prefix}log_comments WHERE id_notice = {int:r} AND comment_type = {literal:reportc}', ['r' => self::REPORT]);
			Db::$db->query('DELETE FROM {db_prefix}background_tasks WHERE task_data LIKE {string:r}', ['r' => '%"report_id":' . self::REPORT . '%']);
			Db::$db->query('DELETE FROM {db_prefix}mail_queue WHERE recipient = {string:to} AND body LIKE {string:report}', ['to' => $this->email, 'report' => '%rid=' . self::REPORT . '%']);
			Db::$db->query('DELETE FROM {db_prefix}user_alerts WHERE content_action = {literal:report_reply} AND extra LIKE {string:r}', ['r' => '%rid=' . self::REPORT . '%']);

			Config::updateModSettings(['mail_next_send' => $this->backup['mail_next_send'] ?? '0']);

			if ($this->backup['pref'] === null) {
				\SMF\Actions\Notify::deleteNotifyPrefs($this->adminId(), ['member_report_reply']);
			} else {
				\SMF\Actions\Notify::setNotifyPrefs($this->adminId(), ['member_report_reply' => (int) $this->backup['pref']]);
			}

			$this->backup = [];
		}

		parent::tearDown();
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
