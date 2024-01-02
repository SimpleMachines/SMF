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
 * A collection of miscellaneous methods related to forum security.
 */
class Security
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'hashPassword' => 'hash_password',
			'hashVerifyPassword' => 'hash_verify_password',
			'hashBenchmark' => 'hash_benchmark',
			'checkConfirm' => 'checkConfirm',
			'checkSubmitOnce' => 'checkSubmitOnce',
			'spamProtection' => 'spamProtection',
			'secureDirectory' => 'secureDirectory',
			'frameOptionsHeader' => 'frameOptionsHeader',
			'corsPolicyHeader' => 'corsPolicyHeader',
			'kickGuest' => 'KickGuest',
		],
	];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Hashes username with password
	 *
	 * @param string $username The username
	 * @param string $password The unhashed password
	 * @param int $cost The cost
	 * @return string The hashed password
	 */
	public static function hashPassword($username, $password, $cost = null)
	{
		$cost = empty($cost) ? (empty(Config::$modSettings['bcrypt_hash_cost']) ? 10 : Config::$modSettings['bcrypt_hash_cost']) : $cost;

		return password_hash(Utils::strtolower($username) . $password, PASSWORD_BCRYPT, [
			'cost' => $cost,
		]);
	}

	/**
	 * Verifies a raw SMF password against the bcrypt'd string
	 *
	 * @param string $username The username
	 * @param string $password The password
	 * @param string $hash The hashed string
	 * @return bool Whether the hashed password matches the string
	 */
	public static function hashVerifyPassword($username, $password, $hash)
	{
		return password_verify(Utils::strtolower($username) . $password, $hash);
	}

	/**
	 * Benchmarks the server to figure out an appropriate cost factor (minimum 9)
	 *
	 * @param float $hashTime Time to target, in seconds
	 * @return int The cost
	 */
	public static function hashBenchmark($hashTime = 0.2)
	{
		$cost = 9;

		do {
			$timeStart = microtime(true);
			self::hashPassword('test', 'thisisatestpassword', $cost);
			$timeTaken = microtime(true) - $timeStart;
			$cost++;
		} while ($timeTaken < $hashTime);

		return $cost;
	}

	/**
	 * Check if a specific confirm parameter was given.
	 *
	 * @param string $action The action we want to check against.
	 * @return bool|string True if the check passed. Otherwise a token string.
	 */
	public static function checkConfirm(string $action): bool|string
	{
		if (isset($_GET['confirm'], $_SESSION['confirm_' . $action])     && hash_hmac('md5', $_SERVER['HTTP_USER_AGENT'], $_GET['confirm']) == $_SESSION['confirm_' . $action]) {
			return true;
		}

		$token = bin2hex(random_bytes(16));

		$_SESSION['confirm_' . $action] = hash_hmac('md5', $_SERVER['HTTP_USER_AGENT'], $token);

		return $token;
	}

	/**
	 * Check whether a form has been submitted twice.
	 *
	 *  - Registers a sequence number for a form.
	 *  - Checks whether a submitted sequence number is registered in the
	 *    current session.
	 *  - Frees a sequence number from the stack after it's been checked.
	 *  - Frees a sequence number without checking if $action == 'free'.
	 *  - If $action == 'check', returns a value. If the check passes, returns
	 *    true. Otherwise, it either shows an error if $is_fatal == true, or
	 *    else just returns false.
	 *  - If an invalid $action is passed, triggers an error.
	 *
	 * @param string $action The action. Can be 'register', 'check', or 'free'.
	 * @param bool $is_fatal Whether to die with a fatal error. Only used when
	 *    $action == 'check'.
	 * @return null|bool If $action == 'check', returns whether the check was
	 *    successful. Otherwise, returns null.
	 */
	public static function checkSubmitOnce(string $action, bool $is_fatal = true): ?bool
	{
		if (!isset($_SESSION['forms'])) {
			$_SESSION['forms'] = [];
		}

		// Check whether the submitted number can be found in the session.
		if ($action == 'check') {
			if (!isset($_REQUEST['seqnum'])) {
				return true;
			}

			if (!in_array($_REQUEST['seqnum'], $_SESSION['forms'])) {
				$_SESSION['forms'][] = (int) $_REQUEST['seqnum'];

				return true;
			}

			if ($is_fatal) {
				ErrorHandler::fatalLang('error_form_already_submitted', false);
			}

			return false;
		}

		// Register a form number and store it in the session stack.
		// (Use this value on the page that has the form.)
		if ($action == 'register') {
			Utils::$context['form_sequence_number'] = 0;

			while (empty(Utils::$context['form_sequence_number']) || in_array(Utils::$context['form_sequence_number'], $_SESSION['forms'])) {
				Utils::$context['form_sequence_number'] = random_int(1, 16000000);
			}
		}
		// Don't check, just free the stack number.
		elseif ($action == 'free' && isset($_REQUEST['seqnum']) && in_array($_REQUEST['seqnum'], $_SESSION['forms'])) {
			$_SESSION['forms'] = array_diff($_SESSION['forms'], [$_REQUEST['seqnum']]);
		}
		// Bail out if $action is unknown.
		elseif ($action != 'free') {
			Lang::load('Errors');

			trigger_error(sprintf(Lang::$txt['check_submit_once_invalid_action'], $action), E_USER_WARNING);
		}

		return null;
	}

	/**
	 * This function attempts to protect from spammed messages and the like.
	 *
	 * The time required between actions depends on $error_type. If there is no
	 * specific time requirement for the $error_type, the time required will
	 * just be Config::$modSettings['spamWaitTime'].
	 *
	 * @param string $error_type The error type. Also used as a Lang::$txt key.
	 * @param bool $only_return_result Whether you want the function to die with
	 *    a fatal_lang_error.
	 * @return bool Whether they've posted within the limit.
	 */
	public static function spamProtection(string $error_type, bool $only_return_result = false): bool
	{
		// Certain types take less/more time.
		$timeOverrides = [
			'login' => 2,
			'register' => 2,
			'remind' => 30,
			'sendmail' => Config::$modSettings['spamWaitTime'] * 5,
			'reporttm' => Config::$modSettings['spamWaitTime'] * 4,
			'search' => !empty(Config::$modSettings['search_floodcontrol_time']) ? Config::$modSettings['search_floodcontrol_time'] : 1,
		];

		IntegrationHook::call('integrate_spam_protection', [&$timeOverrides]);

		// Moderators are free...
		if (!User::$me->allowedTo('moderate_board')) {
			$timeLimit = $timeOverrides[$error_type] ?? Config::$modSettings['spamWaitTime'];
		} else {
			$timeLimit = 2;
		}

		// Delete old entries...
		Db::$db->query(
			'',
			'DELETE FROM {db_prefix}log_floodcontrol
			WHERE log_time < {int:log_time}
				AND log_type = {string:log_type}',
			[
				'log_time' => time() - $timeLimit,
				'log_type' => $error_type,
			],
		);

		// Add a new entry, deleting the old if necessary.
		Db::$db->insert(
			'replace',
			'{db_prefix}log_floodcontrol',
			[
				'ip' => 'inet',
				'log_time' => 'int',
				'log_type' => 'string',
			],
			[
				User::$me->ip,
				time(),
				$error_type,
			],
			['ip', 'log_type'],
		);

		// If affected is 0 or 2, it was there already.
		if (Db::$db->affected_rows() != 1) {
			// Spammer! You only have to wait a *few* seconds!
			if (!$only_return_result) {
				ErrorHandler::fatalLang($error_type . '_WaitTime_broken', false, [$timeLimit]);
			}

			return true;
		}

		// They haven't posted within the limit.
		return false;
	}

	/**
	 * A generic function to create a pair of index.php and .htaccess files in a directory
	 *
	 * @param string|array $paths The (absolute) directory path(s).
	 * @param bool $attachments Whether this is an attachment directory.
	 * @return bool|array True on success, or an array of errors on failure.
	 */
	public static function secureDirectory(string|array $paths, bool $attachments = false): bool|array
	{
		$errors = [];

		// Work with arrays
		$paths = (array) $paths;

		if (empty($paths)) {
			$errors[] = 'empty_path';
		}

		if (!empty($errors)) {
			return $errors;
		}

		foreach ($paths as $path) {
			if (!is_writable($path)) {
				$errors[] = 'path_not_writable';

				continue;
			}

			$directory_name = basename($path);

			// First, create the .htaccess file.
			$contents = <<<END
				<Files *>
					Order Deny,Allow
					Deny from all
				END;

			if (empty($attachments)) {
				$contents .= "\n" . '</Files>';
			} else {
				$contents .= <<<END

						Allow from localhost
					</Files>

					RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml
					END;
			}

			if (file_exists($path . '/.htaccess')) {
				$errors[] = 'htaccess_exists';

				continue;
			}

			$fh = @fopen($path . '/.htaccess', 'w');

			if ($fh) {
				fwrite($fh, $contents);
				fclose($fh);
			} else {
				$errors[] = 'htaccess_cannot_create_file';
			}

			// Next, the index.php file
			if (file_exists($path . '/index.php')) {
				$errors[] = 'index-php_exists';

				continue;
			}

			$contents = <<<END
				<?php

				// Try to handle it with the upper level index.php. (it should know what to do.)
				if (file_exists(dirname(__DIR__) . '/index.php'))
					include (dirname(__DIR__) . '/index.php');
				else
					exit;

				?>
				END;

			$fh = @fopen($path . '/index.php', 'w');

			if ($fh) {
				fwrite($fh, $contents);
				fclose($fh);
			} else {
				$errors[] = 'index-php_cannot_create_file';
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * This sets the X-Frame-Options header.
	 *
	 * @param string $override An option to override (either 'SAMEORIGIN' or 'DENY')
	 * @since 2.1
	 */
	public static function frameOptionsHeader($override = null)
	{
		$option = 'SAMEORIGIN';

		if (is_null($override) && !empty(Config::$modSettings['frame_security'])) {
			$option = Config::$modSettings['frame_security'];
		} elseif (in_array($override, ['SAMEORIGIN', 'DENY'])) {
			$option = $override;
		}

		// Don't bother setting the header if we have disabled it.
		if ($option == 'DISABLE') {
			return;
		}

		// Finally set it.
		header('x-frame-options: ' . $option);

		// And some other useful ones.
		header('x-xss-protection: 1');
		header('x-content-type-options: nosniff');
	}

	/**
	 * This sets the Access-Control-Allow-Origin header.
	 *
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
	 * @since 2.1
	 *
	 * @param bool $set_header When false, we will do the logic, but not send
	 *    the headers. The relevant logic is still saved in Utils::$context and
	 *    can be sent manually. Default: true.
	 */
	public static function corsPolicyHeader(bool $set_header = true): void
	{
		if (empty(Config::$modSettings['allow_cors']) || empty($_SERVER['HTTP_ORIGIN'])) {
			return;
		}

		foreach (['origin' => $_SERVER['HTTP_ORIGIN'], 'forumurl' => Config::$boardurl] as $var => $url) {
			// Convert any Punycode to Unicode for the sake of comparison.
			$$var = Url::create(trim($url), true)->validate()->toUtf8();
		}

		// The admin wants weak security... :(
		if (!empty(Config::$modSettings['cors_domains']) && Config::$modSettings['cors_domains'] === '*') {
			Utils::$context['cors_domain'] = '*';
			Utils::$context['valid_cors_found'] = 'wildcard';
		}
		// Oh good, the admin cares about security. :)
		else {
			$i = 0;

			// Build our list of allowed CORS origins.
			$allowed_origins = [];

			// If subdomain-independent cookies are on, allow CORS requests from subdomains.
			if (!empty(Config::$modSettings['globalCookies']) && !empty(Config::$modSettings['globalCookiesDomain'])) {
				$allowed_origins[++$i] = array_merge(
					Url::create('//*.' . trim(Config::$modSettings['globalCookiesDomain']))->parse(),
					['type' => 'subdomain'],
				);
			}

			// Support forum_alias_urls as well, since those are supported by our login cookie.
			if (!empty(Config::$modSettings['forum_alias_urls'])) {
				foreach (explode(',', Config::$modSettings['forum_alias_urls']) as $alias) {
					$allowed_origins[++$i] = array_merge(
						Url::create((strpos($alias, '//') === false ? '//' : '') . trim($alias))->parse(),
						['type' => 'alias'],
					);
				}
			}

			// Additional CORS domains.
			if (!empty(Config::$modSettings['cors_domains'])) {
				foreach (explode(',', Config::$modSettings['cors_domains']) as $cors_domain) {
					$allowed_origins[++$i] = array_merge(
						Url::create((strpos($cors_domain, '//') === false ? '//' : '') . trim($cors_domain))->parse(),
						['type' => 'additional'],
					);

					if (strpos($allowed_origins[$i]['host'], '*') === 0) {
						$allowed_origins[$i]['type'] .= '_wildcard';
					}
				}
			}

			// Does the origin match any of our allowed domains?
			foreach ($allowed_origins as $allowed_origin) {
				// If a specific scheme is required, it must match.
				if (!empty($allowed_origin['scheme']) && $allowed_origin['scheme'] !== $origin->scheme) {
					continue;
				}

				// If a specific port is required, it must match.
				if (!empty($allowed_origin['port'])) {
					// Automatically supply the default port for the "special" schemes.
					// See https://url.spec.whatwg.org/#special-scheme
					if (empty($origin->port)) {
						switch ($origin->scheme) {
							case 'http':
							case 'ws':
								$origin->port = 80;
								break;

							case 'https':
							case 'wss':
								$origin->port = 443;
								break;

							case 'ftp':
								$origin->port = 21;
								break;

							case 'file':
							default:
								$origin->port = null;
								break;
						}
					}

					if ((int) $allowed_origin['port'] !== (int) $origin->port) {
						continue;
					}
				}

				// Wildcard can only be the first character.
				if (strrpos($allowed_origin['host'], '*') > 0) {
					continue;
				}

				// Wildcard means allow the domain or any subdomains.
				if (strpos($allowed_origin['host'], '*') === 0) {
					$host_regex = '(?:^|\\.)' . preg_quote(ltrim($allowed_origin['host'], '*.'), '~') . '$';
				}
				// No wildcard means allow the domain only.
				else {
					$host_regex = '^' . preg_quote($allowed_origin['host'], '~') . '$';
				}

				if (preg_match('~' . $host_regex . '~u', $origin->host)) {
					Utils::$context['cors_domain'] = trim($_SERVER['HTTP_ORIGIN']);
					Utils::$context['valid_cors_found'] = $allowed_origin['type'];
					break;
				}
			}
		}

		// The default is just to place the root URL of the forum into the policy.
		if (empty(Utils::$context['cors_domain'])) {
			Utils::$context['cors_domain'] = Url::create($forumurl->scheme . '://' . $forumurl->host . (!empty($forumurl->port) ? ':' . $forumurl->port : ''))->toAscii();

			Utils::$context['valid_cors_found'] = 'same';
		}

		Utils::$context['cors_headers'] = 'X-SMF-AJAX';

		// Any additional headers?
		if (!empty(Config::$modSettings['cors_headers'])) {
			// Cleanup any typos.
			$cors_headers = explode(',', Config::$modSettings['cors_headers']);

			foreach ($cors_headers as &$ch) {
				$ch = str_replace(' ', '-', trim($ch));
			}

			Utils::$context['cors_headers'] .= ',' . implode(',', $cors_headers);
		}

		// Allowing Cross-Origin Resource Sharing (CORS).
		if ($set_header && !empty(Utils::$context['valid_cors_found']) && !empty(Utils::$context['cors_domain'])) {
			header('Access-Control-Allow-Origin: ' . Utils::$context['cors_domain']);
			header('Access-Control-Allow-Headers: ' . Utils::$context['cors_headers']);

			// Be careful with this, you're allowing an external site to allow the browser to send cookies with this.
			if (!empty(Config::$modSettings['allow_cors_credentials'])) {
				header('Access-Control-Allow-Credentials: true');
			}
		}
	}

	/**
	 * Backward compatibility wrapper for User::$me->kickIfGuest().
	 */
	public static function kickGuest(): void
	{
		User::$me->kickIfGuest(null, false);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Security::exportStatic')) {
	Security::exportStatic();
}

// Some functions have moved.
class_exists('SMF\\SecurityToken');
class_exists('SMF\\User');

?>