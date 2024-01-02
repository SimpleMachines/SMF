<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

use SMF\Db\DatabaseApi as Db;

/**
 * Class for preparing and handling email messages.
 */
class Mail
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'send' => 'sendmail',
			'addToQueue' => 'AddMailQueue',
			'reduceQueue' => 'reduceQueue',
			'mimespecialchars' => 'mimespecialchars',
			'sendSmtp' => 'smtp_mail',
			'serverParse' => 'serverParse',
			'sendNotifications' => 'sendNotifications',
			'adminNotify' => 'adminNotify',
			'loadEmailTemplate' => 'loadEmailTemplate',
		],
	];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * This function sends an email to the specified recipient(s).
	 * It uses the mail_type settings and webmaster_email variable.
	 *
	 * @param array $to The email(s) to send to
	 * @param string $subject Email subject, expected to have entities, and slashes, but not be parsed
	 * @param string $message Email body, expected to have slashes, no htmlentities
	 * @param string $from The address to use for replies
	 * @param string $message_id If specified, it will be used as local part of the Message-ID header.
	 * @param bool $send_html Whether or not the message is HTML vs. plain text
	 * @param int $priority The priority of the message
	 * @param bool $hotmail_fix Whether to apply the "hotmail fix"
	 * @param bool $is_private Whether this is private
	 * @return bool Whether ot not the email was sent properly.
	 */
	public static function send($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false)
	{
		// Use sendmail if it's set or if no SMTP server is set.
		$use_sendmail = empty(Config::$modSettings['mail_type']) || Config::$modSettings['smtp_host'] == '';

		// Line breaks need to be \r\n only in windows or for SMTP.
		// Starting with php 8x, line breaks need to be \r\n even for linux.
		$line_break = (Utils::$context['server']['is_windows'] || !$use_sendmail || version_compare(PHP_VERSION, '8.0.0', '>=')) ? "\r\n" : "\n";

		// So far so good.
		$mail_result = true;

		// If the recipient list isn't an array, make it one.
		$to_array = is_array($to) ? $to : [$to];

		// Make sure we actually have email addresses to send this to
		foreach ($to_array as $k => $v) {
			// This should never happen, but better safe than sorry
			if (trim($v) == '') {
				unset($to_array[$k]);
			}
		}

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
		$subject = Utils::htmlspecialcharsDecode($subject);
		// Make the message use the proper line breaks.
		$message = str_replace(["\r", "\n"], ['', $line_break], $message);

		// Make sure hotmail mails are sent as HTML so that HTML entities work.
		if ($hotmail_fix && !$send_html) {
			$send_html = true;
			$message = strtr($message, [$line_break => '<br>' . $line_break]);
			$message = preg_replace('~(' . preg_quote(Config::$scripturl, '~') . '(?:[?/][\w\-_%\.,\?&;=#]+)?)~', '<a href="$1">$1</a>', $message);
		}

		list(, $from_name) = self::mimespecialchars(addcslashes($from !== null ? $from : Utils::$context['forum_name'], '<>()\'\\"'), true, $hotmail_fix, $line_break);
		list(, $subject) = self::mimespecialchars($subject, true, $hotmail_fix, $line_break);

		// Construct the mail headers...
		$headers = 'From: "' . $from_name . '" <' . (empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']) . '>' . $line_break;
		$headers .= $from !== null ? 'Reply-To: <' . $from . '>' . $line_break : '';
		$headers .= 'Return-Path: ' . (empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']) . $line_break;
		$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . $line_break;

		if ($message_id !== null && empty(Config::$modSettings['mail_no_message_id'])) {
			$headers .= 'Message-ID: <' . md5(Config::$scripturl . microtime()) . '-' . $message_id . strstr(empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from'], '@') . '>' . $line_break;
		}
		$headers .= 'X-Mailer: SMF' . $line_break;

		// Pass this to the integration before we start modifying the output -- it'll make it easier later.
		if (in_array(false, IntegrationHook::call('integrate_outgoing_email', [&$subject, &$message, &$headers, &$to_array]), true)) {
			return false;
		}

		// Save the original message...
		$orig_message = $message;

		// The mime boundary separates the different alternative versions.
		$mime_boundary = 'SMF-' . md5($message . time());

		// Using mime, as it allows to send a plain unencoded alternative.
		$headers .= 'Mime-Version: 1.0' . $line_break;
		$headers .= 'content-type: multipart/alternative; boundary="' . $mime_boundary . '"' . $line_break;
		$headers .= 'content-transfer-encoding: 7bit' . $line_break;

		// Sending HTML?  Let's plop in some basic stuff, then.
		if ($send_html) {
			$no_html_message = Utils::htmlspecialcharsDecode(strip_tags(strtr($orig_message, ['</title>' => $line_break])));

			// But, then, dump it and use a plain one for dinosaur clients.
			list(, $plain_message) = self::mimespecialchars($no_html_message, false, true, $line_break);
			$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

			// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
			list($charset, $plain_charset_message, $encoding) = self::mimespecialchars($no_html_message, false, false, $line_break);
			$message .= 'content-type: text/plain; charset=' . $charset . $line_break;
			$message .= 'content-transfer-encoding: ' . $encoding . $line_break . $line_break;
			$message .= $plain_charset_message . $line_break . '--' . $mime_boundary . $line_break;

			// This is the actual HTML message, prim and proper.  If we wanted images, they could be inlined here (with multipart/related, etc.)
			list($charset, $html_message, $encoding) = self::mimespecialchars($orig_message, false, $hotmail_fix, $line_break);
			$message .= 'content-type: text/html; charset=' . $charset . $line_break;
			$message .= 'content-transfer-encoding: ' . ($encoding == '' ? '7bit' : $encoding) . $line_break . $line_break;
			$message .= $html_message . $line_break . '--' . $mime_boundary . '--';
		}
		// Text is good too.
		else {
			// Send a plain message first, for the older web clients.
			list(, $plain_message) = self::mimespecialchars($orig_message, false, true, $line_break);
			$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

			// Now add an encoded message using the forum's character set.
			list($charset, $encoded_message, $encoding) = self::mimespecialchars($orig_message, false, false, $line_break);
			$message .= 'content-type: text/plain; charset=' . $charset . $line_break;
			$message .= 'content-transfer-encoding: ' . $encoding . $line_break . $line_break;
			$message .= $encoded_message . $line_break . '--' . $mime_boundary . '--';
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

		// SMTP or sendmail?
		if ($use_sendmail) {
			$subject = strtr($subject, ["\r" => '', "\n" => '']);

			if (!empty(Config::$modSettings['mail_strip_carriage'])) {
				$message = strtr($message, ["\r" => '']);
				$headers = strtr($headers, ["\r" => '']);
			}

			foreach ($to_array as $to) {
				set_error_handler(
					function ($errno, $errstr, $errfile, $errline) {
						// error was suppressed with the @-operator
						if (0 === error_reporting()) {
							return false;
						}

						throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
					},
				);

				try {
					if (!mail(strtr($to, ["\r" => '', "\n" => '']), $subject, $message, $headers)) {
						ErrorHandler::log(sprintf(Lang::$txt['mail_send_unable'], $to));
						$mail_result = false;
					}
				} catch (\ErrorException $e) {
					ErrorHandler::log($e->getMessage(), 'general', $e->getFile(), $e->getLine());
					ErrorHandler::log(sprintf(Lang::$txt['mail_send_unable'], $to));
					$mail_result = false;
				}
				restore_error_handler();

				// Wait, wait, I'm still sending here!
				@set_time_limit(300);

				if (function_exists('apache_reset_timeout')) {
					@apache_reset_timeout();
				}
			}
		} else {
			$mail_result = $mail_result && self::sendSmtp($to_array, $subject, $message, $headers);
		}

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
	public static function addToQueue($flush = false, $to_array = [], $subject = '', $message = '', $headers = '', $send_html = false, $priority = 3, $is_private = false)
	{
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
				'',
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

		foreach ($to_array as $to) {
			// Will this insert go over MySQL's limit?
			$this_insert_len = strlen($to) + strlen($message) + strlen($headers) + 700;

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
	public static function reduceQueue($number = false, $override_limit = false, $force_send = false)
	{
		// Are we intending another script to be sending out the queue?
		if (!empty(Config::$modSettings['mail_queue_use_cron']) && empty($force_send)) {
			return false;
		}

		// Just in case we run into a problem.
		if (empty(Lang::$txt)) {
			Theme::loadEssential();
			Lang::load('Errors', Lang::$default, false);
			Lang::load('index', Lang::$default, false);
		}

		// By default send 5 at once.
		if (!$number) {
			$number = empty(Config::$modSettings['mail_quantity']) ? 5 : Config::$modSettings['mail_quantity'];
		}

		// If we came with a timestamp, and that doesn't match the next event, then someone else has beaten us.
		if (isset($_GET['ts']) && $_GET['ts'] != Config::$modSettings['mail_next_send'] && empty($force_send)) {
			return false;
		}

		// By default move the next sending on by 10 seconds, and require an affected row.
		if (!$override_limit) {
			$delay = max(TaskRunner::MAX_CRON_TIME, (int) (Config::$modSettings['mail_queue_delay'] ?? 10));

			Db::$db->query(
				'',
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
			'',
			'SELECT id_mail, recipient, body, subject, headers, send_html, time_sent, private, priority
			FROM {db_prefix}mail_queue
			ORDER BY priority ASC, id_mail ASC
			LIMIT {int:limit}',
			[
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
			];
		}
		Db::$db->free_result($request);

		// Delete, delete, delete!!!
		if (!empty($ids)) {
			Db::$db->query(
				'',
				'DELETE FROM {db_prefix}mail_queue
				WHERE id_mail IN ({array_int:mail_list})',
				[
					'mail_list' => $ids,
				],
			);
		}

		// Don't believe we have any left?
		if (count($ids) < $number) {
			// Only update the setting if no-one else has beaten us to it.
			Db::$db->query(
				'',
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
		$max_priority = 127;
		$smtp_expire = 259200;
		$priority_offset = 4;

		foreach ($emails as $email) {
			// This seems odd, but check the priority if we should try again so soon. Do this so we don't DOS some poor mail server.
			if ($email['priority'] > $priority_offset && (time() - $email['time_sent']) % $priority_offset != rand(0, $priority_offset)) {
				$failed_emails[] = [
					$email['to'],
					$email['body'],
					$email['subject'],
					$email['headers'],
					$email['send_html'],
					$email['time_sent'],
					$email['private'],
					$email['priority'],
				];

				continue;
			}

			if (empty(Config::$modSettings['mail_type']) || Config::$modSettings['smtp_host'] == '') {
				$email['subject'] = strtr($email['subject'], ["\r" => '', "\n" => '']);

				if (!empty(Config::$modSettings['mail_strip_carriage'])) {
					$email['body'] = strtr($email['body'], ["\r" => '']);
					$email['headers'] = strtr($email['headers'], ["\r" => '']);
				}

				// No point logging a specific error here, as we have no language. PHP error is helpful anyway...
				$result = mail(strtr($email['to'], ["\r" => '', "\n" => '']), $email['subject'], $email['body'], $email['headers']);

				// Try to stop a timeout, this would be bad...
				@set_time_limit(300);

				if (function_exists('apache_reset_timeout')) {
					@apache_reset_timeout();
				}
			} else {
				$result = self::sendSmtp([$email['to']], $email['subject'], $email['body'], $email['headers']);
			}

			// Old emails should expire
			if (!$result && $email['priority'] >= $max_priority) {
				$result = true;
			}

			// Hopefully it sent?
			if (!$result) {
				// Determine the "priority" as a way to keep track of SMTP failures.
				$email['priority'] = max($priority_offset, $email['priority'], min(ceil((time() - $email['time_sent']) / $smtp_expire * ($max_priority - $priority_offset)) + $priority_offset, $max_priority));

				$failed_emails[] = [$email['to'], $email['body'], $email['subject'], $email['headers'], $email['send_html'], $email['time_sent'], $email['private'], $email['priority']];
			}
		}

		// Any emails that didn't send?
		if (!empty($failed_emails)) {
			// Update the failed attempts check.
			Db::$db->insert(
				'replace',
				'{db_prefix}settings',
				['variable' => 'string', 'value' => 'string'],
				['mail_failed_attempts', empty(Config::$modSettings['mail_failed_attempts']) ? 1 : ++Config::$modSettings['mail_failed_attempts']],
				['variable'],
			);

			// If we have failed to many times, tell mail to wait a bit and try again.
			if (Config::$modSettings['mail_failed_attempts'] > 5) {
				Db::$db->query(
					'',
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
					'priority' => 'int',
				],
				$failed_emails,
				['id_mail'],
			);

			return false;
		}

		// We where unable to send the email, clear our failed attempts.
		if (!empty(Config::$modSettings['mail_failed_attempts'])) {
			Db::$db->query(
				'',
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
	 * In case there are higher ASCII characters in the given string, this
	 * function will attempt the transport method 'quoted-printable'.
	 * Otherwise the transport method '7bit' is used.
	 *
	 * @param string $string The string
	 * @param bool $with_charset Whether we're specifying a charset ($custom_charset must be set here)
	 * @param bool $hotmail_fix Whether to apply the hotmail fix  (all higher ASCII characters are converted to HTML entities to assure proper display of the mail)
	 * @param string $line_break The linebreak
	 * @param string $custom_charset If set, it uses this character set
	 * @return array An array containing the character set, the converted string and the transport method.
	 */
	public static function mimespecialchars($string, $with_charset = true, $hotmail_fix = false, $line_break = "\r\n", $custom_charset = null)
	{
		$charset = $custom_charset !== null ? $custom_charset : Utils::$context['character_set'];

		// This is the fun part....
		if (preg_match_all('~&#(\d{3,8});~', $string, $matches) !== 0 && !$hotmail_fix) {
			// Let's, for now, assume there are only &#021;'ish characters.
			$simple = true;

			foreach ($matches[1] as $entity) {
				if ($entity > 128) {
					$simple = false;
				}
			}
			unset($matches);

			if ($simple) {
				$string = preg_replace_callback(
					'~&#(\d{3,8});~',
					function ($m) {
						return chr("{$m[1]}");
					},
					$string,
				);
			} else {
				// Try to convert the string to UTF-8.
				if (!Utils::$context['utf8'] && function_exists('iconv')) {
					$newstring = @iconv(Utils::$context['character_set'], 'UTF-8', $string);

					if ($newstring) {
						$string = $newstring;
					}
				}

				$string = Utils::entityDecode($string, true);

				// Unicode, baby.
				$charset = 'UTF-8';
			}
		}

		// Convert all special characters to HTML entities...just for Hotmail :-\
		if ($hotmail_fix && (Utils::$context['utf8'] || function_exists('iconv') || Utils::$context['character_set'] === 'ISO-8859-1')) {
			if (!Utils::$context['utf8'] && function_exists('iconv')) {
				$newstring = @iconv(Utils::$context['character_set'], 'UTF-8', $string);

				if ($newstring) {
					$string = $newstring;
				}
			}

			$entityConvert = function ($m) {
				$c = $m[1];

				if (strlen($c) === 1 && ord($c[0]) <= 0x7F) {
					return $c;
				}

				if (strlen($c) === 2 && ord($c[0]) >= 0xC0 && ord($c[0]) <= 0xDF) {
					return '&#' . (((ord($c[0]) ^ 0xC0) << 6) + (ord($c[1]) ^ 0x80)) . ';';
				}

				if (strlen($c) === 3 && ord($c[0]) >= 0xE0 && ord($c[0]) <= 0xEF) {
					return '&#' . (((ord($c[0]) ^ 0xE0) << 12) + ((ord($c[1]) ^ 0x80) << 6) + (ord($c[2]) ^ 0x80)) . ';';
				}

				if (strlen($c) === 4 && ord($c[0]) >= 0xF0 && ord($c[0]) <= 0xF7) {
					return '&#' . (((ord($c[0]) ^ 0xF0) << 18) + ((ord($c[1]) ^ 0x80) << 12) + ((ord($c[2]) ^ 0x80) << 6) + (ord($c[3]) ^ 0x80)) . ';';
				}

				return '';
			};

			// Convert all 'special' characters to HTML entities.
			return [$charset, preg_replace_callback('~([\x80-\x{10FFFF}])~u', $entityConvert, $string), '7bit'];
		}

		// We don't need to mess with the subject line if no special characters were in it..
		if (!$hotmail_fix && preg_match('~([^\x09\x0A\x0D\x20-\x7F])~', $string) === 1) {
			// Base64 encode.
			$string = base64_encode($string);

			// Show the characterset and the transfer-encoding for header strings.
			if ($with_charset) {
				$string = '=?' . $charset . '?B?' . $string . '?=';
			}

			// Break it up in lines (mail body).
			else {
				$string = chunk_split($string, 76, $line_break);
			}

			return [$charset, $string, 'base64'];
		}

		return [$charset, $string, '7bit'];
	}

	/**
	 * Sends mail, like mail() but over SMTP.
	 * It expects no slashes or entities.
	 *
	 * @internal
	 *
	 * @param array $mail_to_array Array of strings (email addresses)
	 * @param string $subject Email subject
	 * @param string $message Email message
	 * @param string $headers Email headers
	 * @return bool Whether it sent or not.
	 */
	public static function sendSmtp($mail_to_array, $subject, $message, $headers)
	{
		static $helo;

		Config::$modSettings['smtp_host'] = trim(Config::$modSettings['smtp_host']);

		// Try POP3 before SMTP?
		// @todo There's no interface for this yet.
		if (Config::$modSettings['mail_type'] == 3 && Config::$modSettings['smtp_username'] != '' && Config::$modSettings['smtp_password'] != '') {
			$socket = fsockopen(Config::$modSettings['smtp_host'], 110, $errno, $errstr, 2);

			if (!$socket && (substr(Config::$modSettings['smtp_host'], 0, 5) == 'smtp.' || substr(Config::$modSettings['smtp_host'], 0, 11) == 'ssl://smtp.')) {
				$socket = fsockopen(strtr(Config::$modSettings['smtp_host'], ['smtp.' => 'pop.']), 110, $errno, $errstr, 2);
			}

			if ($socket) {
				fgets($socket, 256);
				fputs($socket, 'USER ' . Config::$modSettings['smtp_username'] . "\r\n");
				fgets($socket, 256);
				fputs($socket, 'PASS ' . base64_decode(Config::$modSettings['smtp_password']) . "\r\n");
				fgets($socket, 256);
				fputs($socket, 'QUIT' . "\r\n");

				fclose($socket);
			}
		}

		// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
		if (!$socket = fsockopen(Config::$modSettings['smtp_host'], empty(Config::$modSettings['smtp_port']) ? 25 : Config::$modSettings['smtp_port'], $errno, $errstr, 3)) {
			// Maybe we can still save this?  The port might be wrong.
			if (substr(Config::$modSettings['smtp_host'], 0, 4) == 'ssl:' && (empty(Config::$modSettings['smtp_port']) || Config::$modSettings['smtp_port'] == 25)) {
				// ssl:hostname can cause fsocketopen to fail with a lookup failure, ensure it exists for this test.
				if (substr(Config::$modSettings['smtp_host'], 0, 6) != 'ssl://') {
					Config::$modSettings['smtp_host'] = str_replace('ssl:', 'ss://', Config::$modSettings['smtp_host']);
				}

				if ($socket = fsockopen(Config::$modSettings['smtp_host'], 465, $errno, $errstr, 3)) {
					ErrorHandler::log(Lang::$txt['smtp_port_ssl']);
				}
			}

			// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
			if (!$socket) {
				ErrorHandler::log(Lang::$txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);

				return false;
			}
		}

		// Wait for a response of 220, without "-" continuer.
		if (!self::serverParse(null, $socket, '220')) {
			return false;
		}

		// Try to determine the server's fully qualified domain name
		// Can't rely on $_SERVER['SERVER_NAME'] because it can be spoofed on Apache
		if (empty($helo)) {
			// See if we can get the domain name from the host itself
			if (function_exists('gethostname')) {
				$helo = gethostname();
			} elseif (function_exists('php_uname')) {
				$helo = php_uname('n');
			}

			// If the hostname isn't a fully qualified domain name, we can use the host name from Config::$boardurl instead
			if (
				empty($helo)
				|| strpos($helo, '.') === false
				|| substr_compare($helo, '.local', -6) === 0
				|| (
					!empty(Config::$modSettings['tld_regex'])
					&& !preg_match('/\.' . Config::$modSettings['tld_regex'] . '$/u', $helo)
				)
			) {
				$url = new Url(Config::$boardurl);
				$helo = $url->host;
			}

			// This is one of those situations where 'www.' is undesirable
			if (strpos($helo, 'www.') === 0) {
				$helo = substr($helo, 4);
			}

			if (!function_exists('idn_to_ascii')) {
				require_once Config::$sourcedir . '/Subs-Compat.php';
			}

			$helo = idn_to_ascii($helo, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
		}

		// SMTP = 1, SMTP - STARTTLS = 2
		if (in_array(Config::$modSettings['mail_type'], [1, 2]) && Config::$modSettings['smtp_username'] != '' && Config::$modSettings['smtp_password'] != '') {
			// EHLO could be understood to mean encrypted hello...
			if (self::serverParse('EHLO ' . $helo, $socket, null, $response) == '250') {
				// Are we using STARTTLS and does the server support STARTTLS?
				if (Config::$modSettings['mail_type'] == 2 && preg_match('~250( |-)STARTTLS~mi', $response)) {
					// Send STARTTLS to enable encryption
					if (!self::serverParse('STARTTLS', $socket, '220')) {
						return false;
					}
					// Enable the encryption
					// php 5.6+ fix
					$crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

					if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
						$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
						$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
					}

					if (!@stream_socket_enable_crypto($socket, true, $crypto_method)) {
						return false;
					}

					// Send the EHLO command again
					if (!self::serverParse('EHLO ' . $helo, $socket, null) == '250') {
						return false;
					}
				}

				if (!self::serverParse('AUTH LOGIN', $socket, '334')) {
					return false;
				}

				// Send the username and password, encoded.
				if (!self::serverParse(base64_encode(Config::$modSettings['smtp_username']), $socket, '334')) {
					return false;
				}

				// The password is already encoded ;)
				if (!self::serverParse(Config::$modSettings['smtp_password'], $socket, '235')) {
					return false;
				}
			} elseif (!self::serverParse('HELO ' . $helo, $socket, '250')) {
				return false;
			}
		} else {
			// Just say "helo".
			if (!self::serverParse('HELO ' . $helo, $socket, '250')) {
				return false;
			}
		}

		// Fix the message for any lines beginning with a period! (the first is ignored, you see.)
		$message = strtr($message, ["\r\n" . '.' => "\r\n" . '..']);

		// !! Theoretically, we should be able to just loop the RCPT TO.
		$mail_to_array = array_values($mail_to_array);

		foreach ($mail_to_array as $i => $mail_to) {
			// Reset the connection to send another email.
			if ($i != 0) {
				if (!self::serverParse('RSET', $socket, '250')) {
					return false;
				}
			}

			// From, to, and then start the data...
			if (!self::serverParse('MAIL FROM: <' . (empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']) . '>', $socket, '250')) {
				return false;
			}

			if (!self::serverParse('RCPT TO: <' . $mail_to . '>', $socket, '250')) {
				return false;
			}

			if (!self::serverParse('DATA', $socket, '354')) {
				return false;
			}
			fputs($socket, 'Subject: ' . $subject . "\r\n");

			if (strlen($mail_to) > 0) {
				fputs($socket, 'To: <' . $mail_to . '>' . "\r\n");
			}
			fputs($socket, $headers . "\r\n\r\n");
			fputs($socket, $message . "\r\n");

			// Send a ., or in other words "end of data".
			if (!self::serverParse('.', $socket, '250')) {
				return false;
			}

			// Almost done, almost done... don't stop me just yet!
			@set_time_limit(300);

			if (function_exists('apache_reset_timeout')) {
				@apache_reset_timeout();
			}
		}
		fputs($socket, 'QUIT' . "\r\n");
		fclose($socket);

		return true;
	}

	/**
	 * Parse a message to the SMTP server.
	 * Sends the specified message to the server, and checks for the
	 * expected response.
	 *
	 * @internal
	 *
	 * @param string $message The message to send
	 * @param resource $socket Socket to send on
	 * @param string $code The expected response code
	 * @param string $response The response from the SMTP server
	 * @return bool Whether it responded as such.
	 */
	public static function serverParse($message, $socket, $code, &$response = null)
	{
		if ($message !== null) {
			fputs($socket, $message . "\r\n");
		}

		// No response yet.
		$server_response = '';

		while (substr($server_response, 3, 1) != ' ') {
			if (!($server_response = fgets($socket, 256))) {
				// @todo Change this message to reflect that it may mean bad user/password/server issues/etc.
				ErrorHandler::log(Lang::$txt['smtp_bad_response']);

				return false;
			}

			$response .= $server_response;
		}

		if ($code === null) {
			return substr($server_response, 0, 3);
		}

		$response_code = (int) substr($server_response, 0, 3);

		if ($response_code != $code) {
			// Ignoreable errors that we can't fix should not be logged.
			/*
			 * 550 - cPanel rejected sending due to DNS issues
			 * 450 - DNS Routing issues
			 * 451 - cPanel "Temporary local problem - please try later"
			 */
			if ($response_code < 500 && !in_array($response_code, [450, 451])) {
				ErrorHandler::log(Lang::$txt['smtp_error'] . $server_response);
			}

			return false;
		}

		return true;
	}

	/**
	 * Sends a notification to members who have elected to receive emails
	 * when things happen to a topic, such as replies are posted.
	 * The function automatically finds the subject and its board, and
	 * checks permissions for each member who is "signed up" for notifications.
	 * It will not send 'reply' notifications more than once in a row.
	 * Uses Post language file
	 *
	 * @param array $topics Represents the topics the action is happening to.
	 * @param string $type Can be any of reply, sticky, lock, unlock, remove, move, merge, and split.  An appropriate message will be sent for each.
	 * @param array $exclude Members in the exclude array will not be processed for the topic with the same key.
	 * @param array $members_only Are the only ones that will be sent the notification if they have it on.
	 */
	public static function sendNotifications($topics, $type, $exclude = [], $members_only = [])
	{
		// Can't do it if there's no topics.
		if (empty($topics)) {
			return;
		}

		// It must be an array - it must!
		if (!is_array($topics)) {
			$topics = [$topics];
		}

		// Get the subject and body...
		$result = Db::$db->query(
			'',
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
	 * @param string $member_name The name of the member (if null, it is pulled from the database)
	 */
	public static function adminNotify($type, $memberID, $member_name = null)
	{
		if ($member_name == null) {
			// Get the new user's name....
			$request = Db::$db->query(
				'',
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
				'SMF\\Tasks\\Register_Notify',
				Utils::jsonEncode([
					'new_member_id' => $memberID,
					'new_member_name' => $member_name,
					'notify_type' => $type,
					'time' => time(),
				]),
				0,
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
	public static function loadEmailTemplate($template, $replacements = [], $lang = '', $loadLang = true)
	{
		// First things first, load up the email templates language file, if we need to.
		if ($loadLang) {
			Lang::load('EmailTemplates', $lang);
		}

		if (!isset(Lang::$txt[$template . '_subject']) || !isset(Lang::$txt[$template . '_body'])) {
			ErrorHandler::fatalLang('email_no_template', 'template', [$template]);
		}

		$ret = [
			'subject' => Lang::$txt[$template . '_subject'],
			'body' => Lang::$txt[$template . '_body'],
			'is_html' => !empty(Lang::$txt[$template . '_html']),
		];

		// Add in the default replacements.
		$replacements += [
			'FORUMNAME' => Config::$mbname,
			'SCRIPTURL' => Config::$scripturl,
			'THEMEURL' => Theme::$current->settings['theme_url'],
			'IMAGESURL' => Theme::$current->settings['images_url'],
			'DEFAULT_THEMEURL' => Theme::$current->settings['default_theme_url'],
			'REGARDS' => sprintf(Lang::$txt['regards_team'], Utils::$context['forum_name']),
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
	 * Callback function for loadEmailTemplate on subject and body
	 * Uses capture group 1 in array
	 *
	 * @param array $matches An array of matches
	 * @return string The match
	 */
	protected static function userInfoCallback($matches)
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
}

// Export public static functions to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Mail::exportStatic')) {
	Mail::exportStatic();
}

?>