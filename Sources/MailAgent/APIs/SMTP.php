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
use SMF\Url;

/**
 * Sends mail via SMTP
 */
class SMTP extends MailAgent implements MailAgentInterface
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var bool
	 *
	 * When enabled, sends mail using TLS.  This is set in another class that inherits this class.
	 */
	public bool $useTLS = false;

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var bool
	 *
	 * This is used to determine if we have sent any mail previosuly and issue a reset prior to sending another message.
	 */
	private bool $sentAny = false;

	/**
	 * @var resource|false
	 *
	 * A file pointer containing the active connection to the SMTP server.
	 * PHP does not have a type hint for resource of type Stream.  So we just use mixed.
	 */
	private mixed $socket;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isSupported(): bool
	{
		return \function_exists('fsockopen');
	}

	/**
	 *
	 */
	public function isConfigured(): bool
	{
		return !empty(Config::$modSettings['smtp_host']);
	}

	/**
	 *
	 */
	public function connect(): bool
	{
		Config::$modSettings['smtp_host'] = trim(Config::$modSettings['smtp_host']);

		// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
		if (!$this->socket = fsockopen(Config::$modSettings['smtp_host'], empty(Config::$modSettings['smtp_port']) ? 25 : Config::$modSettings['smtp_port'], $errno, $errstr, 3)) {
			// Maybe we can still save this?  The port might be wrong.
			if (substr(Config::$modSettings['smtp_host'], 0, 4) == 'ssl:' && (empty(Config::$modSettings['smtp_port']) || Config::$modSettings['smtp_port'] == 25)) {
				// ssl:hostname can cause fsocketopen to fail with a lookup failure, ensure it exists for this test.
				if (substr(Config::$modSettings['smtp_host'], 0, 6) != 'ssl://') {
					Config::$modSettings['smtp_host'] = str_replace('ssl:', 'ss://', Config::$modSettings['smtp_host']);
				}

				if ($this->socket = fsockopen(Config::$modSettings['smtp_host'], 465, $errno, $errstr, 3)) {
					ErrorHandler::log(Lang::getTxt('smtp_port_ssl', file: 'General'));
				}
			}

			// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
			if (!$this->socket) {
				ErrorHandler::log(Lang::getTxt('smtp_no_connect', file: 'General') . ': ' . $errno . ' : ' . $errstr);

				return false;
			}
		}

		// Wait for a response of 220, without "-" continuer.
		if (!$this->serverParse(null, '220')) {
			ErrorHandler::log(Lang::getTxt('smtp_no_connect', file: 'General') . ': No 220 Response');

			return false;
		}

		$helo = $this->getHostname();

		if (Config::$modSettings['smtp_username'] != '' && Config::$modSettings['smtp_password'] != '') {
			// EHLO could be understood to mean encrypted hello...
			if ($this->serverParse('EHLO ' . $helo, null, $response) == '250') {
				// Are we using STARTTLS and does the server support STARTTLS?
				if ($this->useTLS && preg_match('~250( |-)STARTTLS~mi', $response)) {
					// Send STARTTLS to enable encryption
					if (!$this->serverParse('STARTTLS', '220')) {
						return false;
					}

					$crypto_method = STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

					if (!stream_socket_enable_crypto($this->socket, true, $crypto_method)) {
						return false;
					}

					// Send the EHLO command again
					if (!$this->serverParse('EHLO ' . $helo, null) == '250') {
						return false;
					}
				}

				if (!$this->serverParse('AUTH LOGIN', '334')) {
					return false;
				}

				// Send the username and password, encoded.
				if (!$this->serverParse(base64_encode(Config::$modSettings['smtp_username']), '334')) {
					return false;
				}

				// The password is already encoded ;)
				if (!$this->serverParse(Config::$modSettings['smtp_password'], '235')) {
					return false;
				}
			} elseif (!$this->serverParse('HELO ' . $helo, '250')) {
				return false;
			}
		}

		// Just say "helo".
		return ! (!$this->serverParse('HELO ' . $helo, '250'));
	}

	/**
	 *
	 */
	public function send(string $to, string $subject, string $message, string $headers): bool
	{
		if (($address = new EmailAddress($to))->isValid()) {
			$to = $address->sendable();
		} else {
			ErrorHandler::log(Lang::getTxt('mail_send_unable', [$to], file: 'General'));

			return false;
		}

		if (empty($this->socket)) {
			return false;
		}

		// Reset the connection to send another email.
		if ($this->sentAny) {
			if (!self::serverParse('RSET', '250')) {
				return false;
			}
		} else {
			$this->sentAny = true;
		}

		// From, to, and then start the data...
		if (!self::serverParse('MAIL FROM: <' . (empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']) . '>', '250')) {
			return false;
		}

		if (!self::serverParse('RCPT TO: <' . $to . '>', '250')) {
			return false;
		}

		if (!self::serverParse('DATA', '354')) {
			return false;
		}

		fputs($this->socket, 'Subject: ' . $this->stripControlChars($subject) . "\r\n");

		if (\strlen($to) > 0) {
			fputs($this->socket, 'To: <' . $to . '>' . "\r\n");
		}

		fputs($this->socket, $headers . "\r\n\r\n");
		fputs($this->socket, $message . "\r\n");

		// Send a ., or in other words "end of data".
		if (!self::serverParse('.', '250')) {
			return false;
		}

		// Almost done, almost done... don't stop me just yet!
		Sapi::setTimeLimit(300);
		Sapi::resetTimeout();

		return true;
	}

	/**
	 *
	 */
	public function disconnect(): bool
	{
		fputs($this->socket, 'QUIT' . "\r\n");
		fclose($this->socket);
		$this->socket = null;

		return true;
	}

	/**
	 * Specify custom settings that the agent supports.
	 *
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 */
	public function agentSettings(array &$config_vars): void
	{
		$config_vars[] = ['text', 'smtp_host'];
		$config_vars[] = ['text', 'smtp_port'];
		$config_vars[] = ['text', 'smtp_username'];
		$config_vars[] = ['password', 'smtp_password'];
	}

	/**
	 * Only exists to provide backwards compatbility.
	 *
	 * @internal
	 * @deprecated Only exists backwards compatbility.
	 * @param array $config_vars Additional config_vars, see ManageSettings.php for usage.
	 */
	public function compatServerParse(string $message, $socket, string $code, ?string &$response = null): bool
	{
		$this->socket = $socket;

		return $this->serverParse($message, $code, $response);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Removes Excess tags
	 * @param string $raw
	 * @param array $tags
	 * @return string
	 */
	protected function stripControlChars(string $raw, array $tags = ["\r" => '', "\n" => '']): string
	{
		return strtr($raw, $tags);
	}

	/**
	 * Parse a message to the SMTP server.
	 * Sends the specified message to the server, and checks for the
	 * expected response.
	 *
	 * @internal
	 *
	 * @param ?string $message The message to send
	 * @param ?string $code The expected response code
	 * @param string|null $response The response from the SMTP server
	 * @return bool|string Whether it responded as such.
	 */
	private function serverParse(?string $message, ?string $code, ?string &$response = null): bool|string
	{
		if ($message !== null) {
			fputs($this->socket, $message . "\r\n");
		}

		// No response yet.
		$server_response = '';

		while (substr($server_response, 3, 1) != ' ') {
			if (!($server_response = fgets($this->socket, 256))) {
				// @todo Change this message to reflect that it may mean bad user/password/server issues/etc.
				ErrorHandler::log(Lang::getTxt('smtp_bad_response', file: 'General'));

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
			if ($response_code < 500 && !\in_array($response_code, [450, 451])) {
				ErrorHandler::log(Lang::getTxt('smtp_error', file: 'General') . $server_response);
			}

			return false;
		}

		return true;
	}

	/**
	 * Try to determine the server's fully qualified domain name.
	 * Can't rely on $_SERVER['SERVER_NAME'] because it can be
	 * spoofed on Apache
	 *
	 * @internal
	 *
	 * @return string hostname of the server.
	 */
	private function getHostname(): string
	{
		static $helo;

		if (!empty($helo)) {
			return $helo;
		}

		// See if we can get the domain name from the host itself
		if (\function_exists('gethostname')) {
			$helo = gethostname();
		} elseif (\function_exists('php_uname')) {
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

		if (!\function_exists('idn_to_ascii')) {
			require_once Sapi::canonicalPath(Config::$sourcedir . '/Subs-Compat.php');
		}

		$helo = idn_to_ascii($helo, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

		return $helo;
	}
}
