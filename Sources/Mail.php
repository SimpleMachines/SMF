<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF;

use SMF\Db\DatabaseApi as Db;
use SMF\MailAgent\MailAgent;

/**
 * Class for preparing and handling email messages.
 */
class Mail
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * Maximum number of tries to send a email.
	 *
	 * @var int
	 */
	private const MAX_TRIES = 15;

	/**
	 * Multiplier for delaying emails that fail to send.
	 * See calculateNextTry() for implementation
	 * @var int
	 */
	private const DELAY_MULTIPLIER = 15;

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * This function sends an email to the specified recipient(s).
	 * It uses the mail_type settings and webmaster_email variable.
	 *
	 * @param array|string $to The email(s) to send to
	 * @param string $subject Email subject, expected to have entities, and slashes, but not be parsed
	 * @param string $message Email body, expected to have slashes, no htmlentities
	 * @param null|string $from The address to use for replies
	 * @param null|string $message_id If specified, it will be used as local part of the Message-ID header.
	 * @param bool $send_html Whether or not the message is HTML vs. plain text
	 * @param int $priority The priority of the message
	 * @param bool $hotmail_fix Whether to apply the "hotmail fix"
	 * @param bool $is_private Whether this is private
	 * @return bool Whether ot not the email was sent properly.
	 */
	public static function send(
		array|string $to,
		string $subject,
		string $message,
		?string $from = null,
		?string $message_id = null,
		bool $send_html = false,
		int $priority = 3,
		?bool $hotmail_fix = null,
		bool $is_private = false,
	): bool {
		// So far so good.
		$mail_result = true;

		// If the recipient list isn't an array, make it one.
		$to_array = \is_array($to) ? $to : [$to];

		// Make sure we actually have email addresses to send this to.
		$to_array = self::prepareAddresses($to_array);

		// Nothing left? Nothing else to do
		if (empty($to_array)) {
			return true;
		}

		// Once upon a time, Hotmail could not interpret non-ASCII mails.
		// In honour of those days, it's still called the 'hotmail fix'.
		if ($hotmail_fix === null) {
			$hotmail_to = [];

			foreach ($to_array as $i => $to_address) {
				if (preg_match('~@(att|comcast|bellsouth)\.[a-zA-Z\.]{2,6}$~i', $to_address) === 1) {
					$hotmail_to[] = $to_address;
					$to_array = array_diff($to_array, [$to_address]);
				}
			}

			// Call this function recursively for the hotmail addresses.
			if (!empty($hotmail_to)) {
				$mail_result = self::send($hotmail_to, $subject, $message, $from, $message_id, $send_html, $priority, true, $is_private);
			}

			// The remaining addresses no longer need the fix.
			$hotmail_fix = false;

			// No other addresses left? Return instantly.
			if (empty($to_array)) {
				return $mail_result;
			}
		}

		// Get rid of entities.
		$subject = strtr(Utils::htmlspecialcharsDecode($subject), ["\r" => '', "\n" => '']);
		// Make the message use the proper line breaks.
		$message = str_replace(["\r", "\n"], ['', "\r\n"], $message);

		// Make sure hotmail mails are sent as HTML so that HTML entities work.
		if ($hotmail_fix && !$send_html) {
			$send_html = true;
			$message = strtr($message, ["\r\n" => '<br>' . "\r\n"]);
			$message = preg_replace('~(' . preg_quote(Config::$scripturl, '~') . '(?:[?/][\w\-_%\.,\?&;=#]+)?)~', '<a href="$1">$1</a>', $message);
		}

		// Respect the queryless URLs setting.
		$message = QueryString::rewriteAsQueryless($message);

		// Use real tabs.
		$message = strtr($message, [Utils::TAB_SUBSTITUTE => $send_html ? '<span style="white-space: pre;">' . "\t" . '</span>' : "\t"]);

		list(, $from_name) = self::mimespecialchars(addcslashes($from !== null ? $from : Utils::$context['forum_name'], '<>()\'\\"'), true, $hotmail_fix, "\r\n");
		list(, $subject) = self::mimespecialchars($subject, true, $hotmail_fix, "\r\n");

		// Construct the mail headers...
		$headers = 'From: "' . $from_name . '" <' . (empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']) . '>' . "\r\n";
		$headers .= $from !== null ? 'Reply-To: <' . $from . '>' . "\r\n" : '';
		$headers .= 'Return-Path: ' . (empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']) . "\r\n";
		$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . "\r\n";
		$headers .= 'Message-ID: <' . md5(Config::$scripturl . microtime()) . '-' . ($message_id ?? 0) . strstr(empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from'], '@') . '>' . "\r\n";
		$headers .= 'X-Mailer: SMF' . "\r\n";

		// Pass this to the integration before we start modifying the output -- it'll make it easier later.
		if (\in_array(false, IntegrationHook::call('integrate_outgoing_email', [&$subject, &$message, &$headers, &$to_array]), true)) {
			return false;
		}

		// Save the original message...
		$orig_message = $message;

		// The mime boundary separates the different alternative versions.
		$mime_boundary = 'SMF-' . md5($message . time());

		// Using mime, as it allows to send a plain unencoded alternative.
		$headers .= 'Mime-Version: 1.0' . "\r\n";
		$headers .= 'content-type: multipart/alternative; boundary="' . $mime_boundary . '"' . "\r\n";
		$headers .= 'content-transfer-encoding: 7bit' . "\r\n";

		// Sending HTML?  Let's plop in some basic stuff, then.
		if ($send_html) {
			$no_html_message = Utils::htmlspecialcharsDecode(strip_tags(strtr($orig_message, ['</title>' => "\r\n"])));

			// But, then, dump it and use a plain one for dinosaur clients.
			list(, $plain_message) = self::mimespecialchars($no_html_message, false, true, "\r\n");
			$message = $plain_message . "\r\n" . '--' . $mime_boundary . "\r\n";

			// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
			list($charset, $plain_charset_message, $encoding) = self::mimespecialchars($no_html_message, false, false, "\r\n");
			$message .= 'content-type: text/plain; charset=' . $charset . "\r\n";
			$message .= 'content-transfer-encoding: ' . $encoding . "\r\n\r\n";
			$message .= $plain_charset_message . "\r\n" . '--' . $mime_boundary . "\r\n";

			// This is the actual HTML message, prim and proper.  If we wanted images, they could be inlined here (with multipart/related, etc.)
			list($charset, $html_message, $encoding) = self::mimespecialchars($orig_message, false, $hotmail_fix, "\r\n");
			$message .= 'content-type: text/html; charset=' . $charset . "\r\n";
			$message .= 'content-transfer-encoding: ' . ($encoding == '' ? '7bit' : $encoding) . "\r\n\r\n";
			$message .= $html_message . "\r\n" . '--' . $mime_boundary . '--';
		}
		// Text is good too.
		else {
			// Send a plain message first, for the older web clients.
			list(, $plain_message) = self::mimespecialchars($orig_message, false, true, "\r\n");
			$message = $plain_message . "\r\n" . '--' . $mime_boundary . "\r\n";

			// Now add an encoded message using the forum's character set.
			list($charset, $encoded_message, $encoding) = self::mimespecialchars($orig_message, false, false, "\r\n");
			$message .= 'content-type: text/plain; charset=' . $charset . "\r\n";
			$message .= 'content-transfer-encoding: ' . $encoding . "\r\n\r\n";
			$message .= $encoded_message . "\r\n" . '--' . $mime_boundary . '--';
		}

		// Are we using the mail queue, if so this is where we butt in...
		if ($priority != 0) {
			return self::addToQueue(false, $to_array, $subject, $message, $headers, $send_html, $priority, $is_private);
		}

		// If it's a priority mail, send it now - note though that this should NOT be used for sending many at once.
		if (!empty(Config::$modSettings['mail_limit'])) {
			list($last_mail_time, $mails_this_minute) = @explode('|', Config::$modSettings['mail_recent']);

			if (empty($mails_this_minute) || time() > $last_mail_time + 60) {
				$new_queue_stat = time() . '|' . 1;
			} else {
				$new_queue_stat = $last_mail_time . '|' . ((int) $mails_this_minute + 1);
			}

			Config::updateModSettings(['mail_recent' => $new_queue_stat]);
		}

		// Loadup the agent.
		$agent = MailAgent::load();

		if ($agent === false || !$agent->connect()) {
			return false;
		}

		$mail_result = true;

		foreach ($to_array as $to) {
			$mail_result = $mail_result && $agent->send($to, $subject, $message, $headers);
		}

		$agent->disconnect();

		// Everything go smoothly?
		return $mail_result;
	}

	/**
	 * Add an email to the mail queue.
	 *
	 * @param bool $flush Whether to flush the queue
	 * @param array $to_array An array of recipients
	 * @param string $subject The subject of the message
	 * @param string $message The message
	 * @param string $headers The headers
	 * @param bool $send_html Whether to send in HTML format
	 * @param int $priority The priority
	 * @param bool $is_private Whether this is private
	 * @return bool Whether the message was added
	 */
	public static function addToQueue(
		bool $flush = false,
		array $to_array = [],
		string $subject = '',
		string $message = '',
		string $headers = '',
		bool $send_html = false,
		int $priority = 3,
		bool $is_private = false,
	): bool {
		static $cur_insert = [];
		static $cur_insert_len = 0;

		if ($cur_insert_len == 0) {
			$cur_insert = [];
		}

		// If we're flushing, make the final inserts - also if we're near the MySQL length limit!
		if (($flush || $cur_insert_len > 800000) && !empty($cur_insert)) {
			// Only do these once.
			$cur_insert_len = 0;

			// Dump the data...
			Db::$db->insert(
				'',
				'{db_prefix}mail_queue',
				[
					'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
					'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
				],
				$cur_insert,
				['id_mail'],
			);

			$cur_insert = [];
			Utils::$context['flush_mail'] = false;
		}

		// If we're flushing we're done.
		if ($flush) {
			$nextSendTime = time() + 10;

			Db::$db->query(
				'UPDATE {db_prefix}settings
				SET value = {string:nextSendTime}
				WHERE variable = {literal:mail_next_send}
					AND value = {string:no_outstanding}',
				[
					'nextSendTime' => $nextSendTime,
					'no_outstanding' => '0',
				],
			);

			return true;
		}

		// Ensure we tell obExit to flush.
		Utils::$context['flush_mail'] = true;

		$to_array = self::prepareAddresses($to_array);

		foreach ($to_array as $to) {
			// Will this insert go over MySQL's limit?
			$this_insert_len = \strlen($to) + \strlen($message) + \strlen($headers) + 700;

			// Insert limit of 1M (just under the safety) is reached?
			if ($this_insert_len + $cur_insert_len > 1000000) {
				// Flush out what we have so far.
				Db::$db->insert(
					'',
					'{db_prefix}mail_queue',
					[
						'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
						'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
					],
					$cur_insert,
					['id_mail'],
				);

				// Clear this out.
				$cur_insert = [];
				$cur_insert_len = 0;
			}

			// Now add the current insert to the array...
			$cur_insert[] = [time(), (string) $to, (string) $message, (string) $subject, (string) $headers, ($send_html ? 1 : 0), $priority, (int) $is_private];
			$cur_insert_len += $this_insert_len;
		}

		// If they are using SSI there is a good chance obExit will never be called.  So lets be nice and flush it for them.
		if (SMF === 'SSI' || SMF === 'BACKGROUND') {
			return self::addToQueue(true);
		}

		return true;
	}

	/**
	 * Send a group of emails from the mail queue.
	 *
	 * @param bool|int $number The number to send each loop through or false to use the standard limits
	 * @param bool $override_limit Whether to bypass the limit
	 * @param bool $force_send Whether to forcibly send the messages now (useful when using cron jobs)
	 * @return bool Whether things were sent
	 */
	public static function reduceQueue(bool|int $number = false, bool $override_limit = false, bool $force_send = false): bool
	{
		// Are we intending another script to be sending out the queue?
		if (!empty(Config::$modSettings['mail_queue_use_cron']) && empty($force_send)) {
			return false;
		}

		// Just in case we run into a problem.
		if (empty(Lang::$txt)) {
			Theme::loadEssential();
			Lang::load('Errors', Config::$language, false);
			Lang::load('General', Config::$language, false);
		}

		// By default send 5 at once.
		if (!$number) {
			$number = empty(Config::$modSettings['mail_quantity']) ? 5 : (int) Config::$modSettings['mail_quantity'];
		}

		// If we came with a timestamp, and that doesn't match the next event, then someone else has beaten us.
		if (isset($_GET['ts']) && $_GET['ts'] != Config::$modSettings['mail_next_send'] && empty($force_send)) {
			return false;
		}

		$agent = MailAgent::load();

		if ($agent === false || !$agent->connect()) {
			return false;
		}

		// By default move the next sending on by 10 seconds, and require an affected row.
		if (!$override_limit) {
			$delay = max(TaskRunner::MAX_CRON_TIME, (int) (Config::$modSettings['mail_queue_delay'] ?? 10));

			Db::$db->query(
				'UPDATE {db_prefix}settings
				SET value = {string:next_mail_send}
				WHERE variable = {literal:mail_next_send}
					AND value = {string:last_send}',
				[
					'next_mail_send' => time() + $delay,
					'last_send' => Config::$modSettings['mail_next_send'],
				],
			);

			if (Db::$db->affected_rows() == 0) {
				return false;
			}

			Config::$modSettings['mail_next_send'] = time() + $delay;
		}

		// If we're not overriding how many are we allow to send?
		if (!$override_limit && !empty(Config::$modSettings['mail_limit'])) {
			list($mt, $mn) = explode('|', Config::$modSettings['mail_recent'] ?? '');

			// Nothing worth noting...
			if (empty($mn) || $mt < time() - 60) {
				$mt = time();
				$mn = $number;
			}
			// Otherwise we have a few more we can spend?
			elseif ($mn < Config::$modSettings['mail_limit']) {
				$mn += $number;
			}
			// No more I'm afraid, return!
			else {
				return false;
			}

			// Reflect that we're about to send some, do it now to be safe.
			Config::updateModSettings(['mail_recent' => $mt . '|' . $mn]);
		}

		// Now we know how many we're sending, let's send them.
		$ids = [];
		$emails = [];

		$request = Db::$db->query(
			'SELECT id_mail, recipient, body, subject, headers, send_html, time_sent, private, priority, next_try, tries, extra
			FROM {db_prefix}mail_queue
			WHERE next_try <= {int:current_time}
			ORDER BY priority ASC, next_try ASC, tries ASC, id_mail ASC
			LIMIT {int:limit}',
			[
				'current_time' => time(),
				'limit' => $number,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			// We want to delete these from the database ASAP, so just get the data and go.
			$ids[] = $row['id_mail'];

			$emails[] = [
				'to' => $row['recipient'],
				'body' => $row['body'],
				'subject' => $row['subject'],
				'headers' => $row['headers'],
				'send_html' => $row['send_html'],
				'time_sent' => $row['time_sent'],
				'private' => $row['private'],
				'priority' => $row['priority'],
				'next_try' => $row['next_try'],
				'tries' => $row['tries'],
				'extra' => $row['extra'],
			];
		}
		Db::$db->free_result($request);

		// Delete, delete, delete!!!
		if (!empty($ids)) {
			Db::$db->query(
				'DELETE FROM {db_prefix}mail_queue
				WHERE id_mail IN ({array_int:mail_list})',
				[
					'mail_list' => $ids,
				],
			);
		}

		// Don't believe we have any left?
		if (\count($ids) < $number) {
			// Only update the setting if no-one else has beaten us to it.
			Db::$db->query(
				'UPDATE {db_prefix}settings
				SET value = {string:no_send}
				WHERE variable = {literal:mail_next_send}
					AND value = {string:last_mail_send}',
				[
					'no_send' => '0',
					'last_mail_send' => Config::$modSettings['mail_next_send'],
				],
			);
		}

		if (empty($ids)) {
			return false;
		}

		// Send each email, yea!
		$failed_emails = [];

		foreach ($emails as $email) {
			$email['to'] = current(self::prepareAddresses([$email['to']]));

			// Can't send without a valid address!
			if ($email['to'] === false) {
				continue;
			}

			$result = $agent->send($email['to'], $email['subject'], $email['body'], $email['headers']);

			// Old emails should expire
			if (!$result && $email['tries'] >= self::MAX_TRIES) {
				$result = true;
			}

			// Hopefully it sent?
			if (!$result) {
				$failed_emails[] = [
					$email['to'],
					$email['body'],
					$email['subject'],
					$email['headers'],
					$email['send_html'],
					$email['time_sent'],
					$email['private'],
					$email['priority'],
					self::calculateNextTry($email['tries']),
					++$email['tries'],
					$email['extra'],
				];
			}
		}

		$agent->disconnect();

		// Any emails that didn't send?
		if (!empty($failed_emails)) {
			// Update the failed attempts check.
			Db::$db->insert(
				'replace',
				'{db_prefix}settings',
				[
					'variable' => 'string',
					'value' => 'string',
				],
				[
					[
						'mail_failed_attempts',
						empty(Config::$modSettings['mail_failed_attempts']) ? 1 : ++Config::$modSettings['mail_failed_attempts'],
					],
				],
				['variable'],
			);

			// If we have failed too many times, tell mail to wait a bit and try again.
			if (Config::$modSettings['mail_failed_attempts'] > 5) {
				Db::$db->query(
					'UPDATE {db_prefix}settings
					SET value = {string:next_mail_send}
					WHERE variable = {literal:mail_next_send}
						AND value = {string:last_send}',
					[
						'next_mail_send' => time() + 60,
						'last_send' => Config::$modSettings['mail_next_send'],
					],
				);
			}

			// Add our email back to the queue, manually.
			Db::$db->insert(
				'insert',
				'{db_prefix}mail_queue',
				[
					'recipient' => 'string',
					'body' => 'string',
					'subject' => 'string',
					'headers' => 'string',
					'send_html' => 'string',
					'time_sent' => 'string',
					'private' => 'int',
					'next_try' => 'int',
					'tries' => 'int',
					'extra' => 'string',

				],
				$failed_emails,
				['id_mail'],
			);

			return false;
		}

		// We where unable to send the email, clear our failed attempts.
		if (!empty(Config::$modSettings['mail_failed_attempts'])) {
			Db::$db->query(
				'UPDATE {db_prefix}settings
				SET value = {string:zero}
				WHERE variable = {string:mail_failed_attempts}',
				[
					'zero' => '0',
					'mail_failed_attempts' => 'mail_failed_attempts',
				],
			);
		}

		// Had something to send...
		return true;
	}

	/**
	 * Prepare text strings for sending as email body or header.
	 *
	 * In case there are Unicode characters in the given string, this
	 * function will attempt the transport method 'base64'.
	 * Otherwise the transport method '7bit' is used.
	 *
	 * @param string $string The string
	 * @param bool $with_charset Whether we're specifying a charset ($custom_charset must be set here)
	 * @param bool $hotmail_fix Whether to apply the hotmail fix  (all Unicode characters are converted to HTML entities to assure proper display of the mail)
	 * @param string $line_break The linebreak
	 * @param ?string $custom_charset The character set of the incoming string. Optional.
	 * @return array An array containing the character set, the converted string and the transport method.
	 */
	public static function mimespecialchars(string $string, bool $with_charset = true, bool $hotmail_fix = false, string $line_break = "\r\n", ?string $custom_charset = null): array
	{
		if (isset($custom_charset)) {
			$string = mb_convert_encoding($string, 'UTF-8', $custom_charset);
		}

		$string = Utils::entityDecode($string);

		// Convert all special characters to HTML entities...just for Hotmail :-\
		if ($hotmail_fix) {
			return ['UTF-8', mb_encode_numericentity($string, [0x80, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8'), '7bit'];
		}

		// We don't need to mess with the subject line if no special characters were in it..
		if (preg_match('/([^\x{09}\x{0A}\x{0D}\x{20}-\x{7F}])/u', $string)) {
			// Base64 encode.
			$string = base64_encode($string);

			// Show the characterset and the transfer-encoding for header strings.
			if ($with_charset) {
				$string = '=?UTF-8?B?' . $string . '?=';
			}

			// Break it up in lines (mail body).
			else {
				$string = chunk_split($string, 76, $line_break);
			}

			return ['UTF-8', $string, 'base64'];
		}

		return ['UTF-8', $string, '7bit'];
	}

	/**
	 * Sends a notification to members who have elected to receive emails
	 * when things happen to a topic, such as replies are posted.
	 * The function automatically finds the subject and its board, and
	 * checks permissions for each member who is "signed up" for notifications.
	 * It will not send 'reply' notifications more than once in a row.
	 * Uses Post language file
	 *
	 * @param int|array $topics Represents the topics the action is happening to.
	 * @param string $type Can be any of reply, sticky, lock, unlock, remove, move, merge, and split.  An appropriate message will be sent for each.
	 * @param array $exclude Members in the exclude array will not be processed for the topic with the same key.
	 * @param array $members_only Are the only ones that will be sent the notification if they have it on.
	 */
	public static function sendNotifications(int|array $topics, string $type, array $exclude = [], array $members_only = []): void
	{
		// Can't do it if there's no topics.
		if (empty($topics)) {
			return;
		}

		// It must be an array - it must!
		// @TODO: $topics = (array) $topics;
		if (!\is_array($topics)) {
			$topics = [$topics];
		}

		// Get the subject and body...
		$result = Db::$db->query(
			'SELECT mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic, t.id_board,
				COALESCE(mem.real_name, ml.poster_name) AS poster_name, mf.id_msg
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
			WHERE t.id_topic IN ({array_int:topic_list})
			LIMIT 1',
			[
				'topic_list' => $topics,
			],
		);
		$task_rows = [];

		while ($row = Db::$db->fetch_assoc($result)) {
			$task_rows[] = [
				'SMF\\Tasks\\CreatePost_Notify',
				Utils::jsonEncode([
					'msgOptions' => [
						'id' => $row['id_msg'],
						'subject' => $row['subject'],
						'body' => $row['body'],
					],
					'topicOptions' => [
						'id' => $row['id_topic'],
						'board' => $row['id_board'],
					],
					// Kinda cheeky, but for any action the originator is usually the current user
					'posterOptions' => [
						'id' => User::$me->id,
						'name' => User::$me->name,
					],
					'type' => $type,
					'members_only' => $members_only,
				]),
				0,
			];
		}
		Db::$db->free_result($result);

		if (!empty($task_rows)) {
			Db::$db->insert(
				'',
				'{db_prefix}background_tasks',
				[
					'task_class' => 'string',
					'task_data' => 'string',
					'claimed_time' => 'int',
				],
				$task_rows,
				['id_task'],
			);
		}
	}

	/**
	 * This simple function gets a list of all administrators and sends them an email
	 *  to let them know a new member has joined.
	 * Called by registerMember() function in Subs-Members.php.
	 * Email is sent to all groups that have the moderate_forum permission.
	 * The language set by each member is being used (if available).
	 * Uses the Login language file
	 *
	 * @param string $type The type. Types supported are 'approval', 'activation', and 'standard'.
	 * @param int $memberID The ID of the member
	 * @param null|string $member_name The name of the member (if null, it is pulled from the database)
	 */
	public static function adminNotify(string $type, int $memberID, ?string $member_name = null): void
	{
		if ($member_name == null) {
			// Get the new user's name....
			$request = Db::$db->query(
				'SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				[
					'id_member' => $memberID,
				],
			);
			list($member_name) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
		}

		// This is really just a wrapper for making a new background task to deal with all the fun.
		Db::$db->insert(
			'insert',
			'{db_prefix}background_tasks',
			[
				'task_class' => 'string',
				'task_data' => 'string',
				'claimed_time' => 'int',
			],
			[
				[
					'SMF\\Tasks\\Register_Notify',
					Utils::jsonEncode([
						'new_member_id' => $memberID,
						'new_member_name' => $member_name,
						'notify_type' => $type,
						'time' => time(),
					]),
					0,
				],
			],
			['id_task'],
		);
	}

	/**
	 * Load a template from EmailTemplates language file.
	 *
	 * @param string $template The name of the template to load
	 * @param array $replacements An array of replacements for the variables in the template
	 * @param string $lang The language to use, if different than the user's current language
	 * @param bool $loadLang Whether to load the language file first
	 * @return array An array containing the subject and body of the email template, with replacements made
	 */
	public static function loadEmailTemplate(string $template, array $replacements = [], string $lang = '', bool $loadLang = true): array
	{
		if (
			!Lang::txtExists($template . '_subject', file: 'EmailTemplates')
			|| !Lang::txtExists($template . '_body', file: 'EmailTemplates')
		) {
			ErrorHandler::fatalLang('email_no_template', 'template', [$template]);
		}

		$ret = [
			'subject' => Lang::getTxt($template . '_subject', file: 'EmailTemplates', lang: $loadLang ? $lang : ''),
			'body' => Lang::getTxt($template . '_body', file: 'EmailTemplates', lang: $loadLang ? $lang : ''),
			'is_html' => Lang::txtExists($template . '_html', file: 'EmailTemplates'),
		];

		// Add in the default replacements.
		$replacements += [
			'FORUMNAME' => Config::$mbname,
			'SCRIPTURL' => Config::$scripturl,
			'THEMEURL' => Theme::$current->settings['theme_url'],
			'IMAGESURL' => Theme::$current->settings['images_url'],
			'DEFAULT_THEMEURL' => Theme::$current->settings['default_theme_url'],
			'REGARDS' => Lang::getTxt('regards_team', ['forum_name' => Utils::$context['forum_name']], file: 'General'),
		];

		// Split the replacements up into two arrays, for use with str_replace
		$find = [];
		$replace = [];

		foreach ($replacements as $f => $r) {
			$find[] = '{' . $f . '}';
			$replace[] = $r;
		}

		// Do the variable replacements.
		$ret['subject'] = str_replace($find, $replace, $ret['subject']);
		$ret['body'] = str_replace($find, $replace, $ret['body']);

		// Now deal with the {USER.variable} items.
		$ret['subject'] = preg_replace_callback('~{USER.([^}]+)}~', __CLASS__ . '::userInfoCallback', $ret['subject']);
		$ret['body'] = preg_replace_callback('~{USER.([^}]+)}~', __CLASS__ . '::userInfoCallback', $ret['body']);

		// Finally return the email to the caller so they can send it out.
		return $ret;
	}

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Processes a list of email addresses to weed out any invalid ones and to
	 * ensure the valid ones use the form with the best chance of delivery.
	 *
	 * @param array $addresses A list of email addresses.
	 * @return array Updated list of email addresses.
	 */
	protected static function prepareAddresses(array $addresses): array
	{
		$addresses = array_map(fn($address) => new EmailAddress((string) $address), $addresses);

		// Filter out invalid email addresses.
		$addresses = array_filter($addresses, fn($address) => $address->isValid());

		// Use the form that has the best chance of successful delivery.
		return array_map(fn($address) => $address->sendable(), $addresses);
	}

	/**
	 * Callback function for loadEmailTemplate on subject and body
	 * Uses capture group 1 in array
	 *
	 * @param array $matches An array of matches
	 * @return string The match
	 */
	protected static function userInfoCallback(array $matches): string
	{
		if (empty($matches[1])) {
			return '';
		}

		$use_ref = true;

		foreach (explode('.', $matches[1]) as $index) {
			if ($use_ref && isset(User::$me->{$index})) {
				$ref = &User::$me->{$index};
			} else {
				$use_ref = false;
				break;
			}
		}

		return $use_ref ? $ref : $matches[0];
	}

	/**
	 * Based on the number of tries, increase the time we delay the next sending.
	 *
	 * @param int $tries
	 * @return int Next time we should try to send.
	 */
	private static function calculateNextTry(int $tries)
	{
		$next = time();

		for ($i = 0; $i < ($tries + 1); $i++) {
			$next_send_time += $i * self::DELAY_MULTIPLIER;
		}

		return $next;
	}
}
