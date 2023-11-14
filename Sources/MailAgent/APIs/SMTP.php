<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
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

if (!defined('SMF'))
	die('No direct access...');

/**
 * Our Cache API class
 *
 * @package CacheAPI
 */
class SMTP extends MailAgent implements MailAgentInterface
{
	private $sentAny = false;
	public $UseTLS = false;
	private $socket;

	/**
	 * {@inheritDoc}
	 */
	public function isSupported(): bool
	{
		return function_exists('fsockopen');
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConfigured(): bool
	{
		return !empty(Config::$modSettings['smtp_host']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): bool
	{
		static $helo;

		Config::$modSettings['smtp_host'] = trim(Config::$modSettings['smtp_host']);

		// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
		if (!$this->socket = fsockopen(Config::$modSettings['smtp_host'], empty(Config::$modSettings['smtp_port']) ? 25 : Config::$modSettings['smtp_port'], $errno, $errstr, 3))
		{
			// Maybe we can still save this?  The port might be wrong.
			if (substr(Config::$modSettings['smtp_host'], 0, 4) == 'ssl:' && (empty(Config::$modSettings['smtp_port']) || Config::$modSettings['smtp_port'] == 25))
			{
				// ssl:hostname can cause fsocketopen to fail with a lookup failure, ensure it exists for this test.
				if (substr(Config::$modSettings['smtp_host'], 0, 6) != 'ssl://')
					Config::$modSettings['smtp_host'] = str_replace('ssl:', 'ss://', Config::$modSettings['smtp_host']);

				if ($this->socket = fsockopen(Config::$modSettings['smtp_host'], 465, $errno, $errstr, 3))
					ErrorHandler::log(Lang::$txt['smtp_port_ssl']);
			}

			// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
			if (!$this->socket)
			{
				ErrorHandler::log(Lang::$txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
				return false;
			}
		}

		// Wait for a response of 220, without "-" continuer.
		if (!$this->serverParse(null, '220'))
		{
			ErrorHandler::log(Lang::$txt['smtp_no_connect'] . ': No 220 Response');
			return false;
		}

		// Try to determine the server's fully qualified domain name
		// Can't rely on $_SERVER['SERVER_NAME'] because it can be spoofed on Apache
		if (empty($helo))
		{
			// See if we can get the domain name from the host itself
			if (function_exists('gethostname'))
				$helo = gethostname();
			elseif (function_exists('php_uname'))
				$helo = php_uname('n');

			// If the hostname isn't a fully qualified domain name, we can use the host name from Config::$boardurl instead
			if (
				empty($helo)
				|| strpos($helo, '.') === false
				|| substr_compare($helo, '.local', -6) === 0
				|| (
					!empty(Config::$modSettings['tld_regex'])
					&& !preg_match('/\.' . Config::$modSettings['tld_regex'] . '$/u', $helo)
				)
			)
			{
				$url = new Url(Config::$boardurl);
				$helo = $url->host;
			}

			// This is one of those situations where 'www.' is undesirable
			if (strpos($helo, 'www.') === 0)
				$helo = substr($helo, 4);

			if (!function_exists('idn_to_ascii'))
				require_once(Config::$sourcedir . '/Subs-Compat.php');

			$helo = idn_to_ascii($helo, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
		}

		if (Config::$modSettings['smtp_username'] != '' && Config::$modSettings['smtp_password'] != '')
		{
			// EHLO could be understood to mean encrypted hello...
			if ($this->serverParse('EHLO ' . $helo, null, $response) == '250')
			{
				// Are we using STARTTLS and does the server support STARTTLS?
				if ($this->UseTLS && preg_match("~250( |-)STARTTLS~mi", $response))
				{
					// Send STARTTLS to enable encryption
					if (!$this->serverParse('STARTTLS', '220'))
						return false;

					// Enable the encryption
					// php 5.6+ fix
					$crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

					if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT'))
					{
						$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
						$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
					}

					if (!@stream_socket_enable_crypto($this->socket, true, $crypto_method))
						return false;
					// Send the EHLO command again
					if (!$this->serverParse('EHLO ' . $helo, null) == '250')
						return false;
				}

				if (!$this->serverParse('AUTH LOGIN', '334'))
					return false;
				// Send the username and password, encoded.
				if (!$this->serverParse(base64_encode(Config::$modSettings['smtp_username']), '334'))
					return false;
				// The password is already encoded ;)
				if (!$this->serverParse(Config::$modSettings['smtp_password'], '235'))
					return false;
			}
			elseif (!$this->serverParse('HELO ' . $helo, '250'))
				return false;
		}
		else
		{
			// Just say "helo".
			if (!$this->serverParse('HELO ' . $helo, '250'))
				return false;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function send(string $to, string $subject, string $message, string $headers): bool
	{
		if (empty($this->socket))
			return false;

		// Reset the connection to send another email.
		if ($this->sentAny)
		{
			if (!self::serverParse('RSET', '250'))
				return false;
		}
		else
			$this->sentAny = true;

		// From, to, and then start the data...
		if (!self::serverParse('MAIL FROM: <' . (empty(Config::$modSettings['mail_from']) ? Config::$webmaster_email : Config::$modSettings['mail_from']) . '>', '250'))
			return false;
		if (!self::serverParse('RCPT TO: <' . $to . '>', '250'))
			return false;
		if (!self::serverParse('DATA', '354'))
			return false;
		fputs($this->socket, 'Subject: ' . $subject . "\r\n");
		if (strlen($to) > 0)
			fputs($this->socket, 'To: <' . $to . '>' . "\r\n");
		fputs($this->socket, $headers . "\r\n\r\n");
		fputs($this->socket, $message . "\r\n");

		// Send a ., or in other words "end of data".
		if (!self::serverParse('.', '250'))
			return false;

		// Almost done, almost done... don't stop me just yet!
		@set_time_limit(300);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		return true;
	}

	/**
	 * {@inheritDoc}
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
	 * @access public
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
	 * Parse a message to the SMTP server.
	 * Sends the specified message to the server, and checks for the
	 * expected response.
	 *
	 * @internal
	 *
	 * @param string $message The message to send
	 * @param resource $this->socket Socket to send on
	 * @param string $code The expected response code
	 * @param string $response The response from the SMTP server
	 * @return bool Whether it responded as such.
	 */
	public function serverParse($message, $code, &$response = null): bool|string
	{
		if ($message !== null)
			fputs($this->socket, $message . "\r\n");

		// No response yet.
		$server_response = '';

		while (substr($server_response, 3, 1) != ' ')
		{
			if (!($server_response = fgets($this->socket, 256)))
			{
				// @todo Change this message to reflect that it may mean bad user/password/server issues/etc.
				ErrorHandler::log(Lang::$txt['smtp_bad_response']);
				return false;
			}

			$response .= $server_response;
		}

		if ($code === null)
			return substr($server_response, 0, 3);

		$response_code = (int) substr($server_response, 0, 3);

		if ($response_code != $code)
		{
			// Ignoreable errors that we can't fix should not be logged.
			/*
			 * 550 - cPanel rejected sending due to DNS issues
			 * 450 - DNS Routing issues
			 * 451 - cPanel "Temporary local problem - please try later"
			 */
			if ($response_code < 500 && !in_array($response_code, array(450, 451)))
				ErrorHandler::log(Lang::$txt['smtp_error'] . $server_response);

			return false;
		}

		return true;
	}
}

?>