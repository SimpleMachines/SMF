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

namespace SMF\MailAgent\APIs;

use SMF\Config;
use SMF\EmailAddress;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\MailAgent\MailAgent;
use SMF\MailAgent\MailAgentInterface;
use SMF\Sapi;

/**
 * Sends mail via SendMail
 */
class SendMail extends MailAgent implements MailAgentInterface
{
	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isSupported(): bool
	{
		// Always do this, even if mail() has been disabled.
		return true;
	}

	/**
	 *
	 */
	public function isConfigured(): bool
	{
		// Always do this, even if mail() has been disabled.
		return true;
	}

	/**
	 *
	 */
	public function connect(): bool
	{
		return true;
	}

	/**
	 *
	 */
	public function send(string $to, string $subject, string $message, string $headers): bool
	{
		$mail_result = true;

		if (($address = new EmailAddress($to))->isValid()) {
			$to = $address->sendable();
		} else {
			ErrorHandler::log(Lang::getTxt('mail_send_unable', [$to], file: 'General'));

			return false;
		}

		$subject = strtr($subject, ["\r" => '', "\n" => '']);

		if (!empty(Config::$modSettings['mail_strip_carriage'])) {
			$message = strtr($message, ["\r" => '']);
			$headers = strtr($headers, ["\r" => '']);
		}

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
			if (!mail($to, $subject, $message, $headers)) {
				ErrorHandler::log(Lang::getTxt('mail_send_unable', [$to], file: 'General'));
				$mail_result = false;
			}
		} catch (\ErrorException $ex) {
			ErrorHandler::logException($ex);
			ErrorHandler::log(Lang::getTxt('mail_send_unable', [$to], file: 'General'));
			$mail_result = false;
		}
		restore_error_handler();

		// Wait, wait, I'm still sending here!
		Sapi::setTimeLimit();
		Sapi::resetTimeout();

		return $mail_result;
	}
}
