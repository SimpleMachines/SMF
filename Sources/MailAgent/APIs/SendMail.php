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

namespace SMF\MailAgent\APIs;

use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\MailAgent\MailAgent;
use SMF\MailAgent\MailAgentInterface;
use SMF\Utils;

/**
 * Sends mail via SendMail
 */
class SendMail extends MailAgent implements MailAgentInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function isSupported(): bool
	{
		// Always do this, even if mail() has been disabled.
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConfigured(): bool
	{
		// Always do this, even if mail() has been disabled.
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function send(string $to, string $subject, string $message, string $headers): bool
	{
		$mail_result = true;

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
		Utils::sapiSetTimeLimit();
		Utils::sapiResetTimeout();

		return $mail_result;
	}
}

?>