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

use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use ZxcvbnPhp\Zxcvbn;

/**
 * A collection of miscellaneous methods related to forum security.
 */
class Security
{
	/*****************
	 * Class constants
	 *****************/

	/**
	 * @var int
	 *
	 * Used to tell Security::checkPassword() not to check password fallbacks.
	 */
	public const PASSWORD_FALLBACK_NONE  = 0b0000;

	/**
	 * @var int
	 *
	 * Used to tell Security::checkPassword() to check password fallbacks for
	 * other forum software and/or via the integrate_other_passwords hook.
	 *
	 * Note: Config::$modSettings['enable_password_conversion'] must also be
	 * true in order to check fallbacks for other forum software.
	 */
	public const PASSWORD_FALLBACK_OTHER = 0b0001;

	/**
	 * @var int
	 *
	 * Used to tell Security::checkPassword() to check password fallbacks for
	 * YaBB SE and SMF 1.0.
	 */
	public const PASSWORD_FALLBACK_SMF10 = 0b0010;

	/**
	 * @var int
	 *
	 * Used to tell Security::checkPassword() to check password fallbacks for
	 * SMF 1.1 and SMF 2.0.
	 */
	public const PASSWORD_FALLBACK_SMF20 = 0b0100;

	/**
	 * @var int
	 *
	 * Used to tell Security::checkPassword() to check password fallbacks for
	 * SMF 2.1.
	 */
	public const PASSWORD_FALLBACK_SMF21 = 0b1000;

	/**
	 * @var int
	 *
	 * Used to tell Security::checkPassword() to check password fallbacks for
	 * all versions of SMF.
	 */
	public const PASSWORD_FALLBACK_SMF = (
		self::PASSWORD_FALLBACK_SMF10
		| self::PASSWORD_FALLBACK_SMF20
		| self::PASSWORD_FALLBACK_SMF21
	);

	/**
	 * @var int
	 *
	 * Used to tell Security::checkPassword() to check all password fallbacks.
	 */
	public const PASSWORD_FALLBACK_ALL = (
		self::PASSWORD_FALLBACK_OTHER
		| self::PASSWORD_FALLBACK_SMF
	);

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Checks whether the password is the correct one for the given member.
	 *
	 * @param string $password The plain text password to check.
	 * @param User $member The member whose password we are checking.
	 * @param int $allowed_fallbacks Which fallbacks to check.
	 *    Must be a bitmask of this class's PASSWORD_FALLBACK_* constants.
	 * @param bool $flood_check Whether to check for flooding attempts.
	 *    Default: true
	 * @param bool $update Whether to update the member's stored password if
	 *    the given password is correct but the stored password uses the wrong
	 *    encryption algorithm. This should almost always be set to true.
	 *    Default: true
	 * @return bool Whether the password is correct.
	 */
	public static function checkPassword(
		#[\SensitiveParameter]
		string $password,
		User $member,
		int $allowed_fallbacks,
		bool $flood_check = true,
		bool $update = true,
	): bool {
		if (self::hashVerifyPassword($password, $member->passwd)) {
			return true;
		}

		// Let's be cautious, no hacking please. thanx.
		if ($flood_check) {
			self::validatePasswordFlood($member->id, $member->username, $member->passwd_flood);
		}

		// If the forum was recently upgraded, password might be encrypted
		// using a different algorithm.
		$is_correct = self::checkPasswordFallbacks($password, $member, $allowed_fallbacks);

		// Whichever encryption it was using, let's make it use SMF's now ;).
		if ($is_correct && $update) {
			$member->passwd = self::hashPassword($password);
			$member->password_salt = bin2hex(random_bytes(16));
			$member->passwd_flood = '';

			$member->save();
		}

		return $is_correct;
	}

	/**
	 * Hashes the user's password.
	 *
	 * @param string $password The plain text password.
	 * @param int|null $cost The cost.
	 * @return string The hashed password.
	 */
	public static function hashPassword(
		#[\SensitiveParameter]
		string $password,
		?int $cost = null,
	): string {
		$cost = empty($cost) ? (empty(Config::$modSettings['bcrypt_hash_cost']) ? 10 : Config::$modSettings['bcrypt_hash_cost']) : $cost;

		return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
	}

	/**
	 * Verifies a raw SMF password against the encrypted string
	 *
	 * @param string $password The plain text password.
	 * @param string $hash The hashed string.
	 * @return bool Whether the hashed password matches the string.
	 */
	public static function hashVerifyPassword(
		#[\SensitiveParameter]
		string $password,
		string $hash,
	): bool {
		return password_verify($password, $hash);
	}

	/**
	 * Benchmarks the server to figure out an appropriate cost factor.
	 *
	 * @param float $hashTime Time to target, in seconds.
	 * @return int The cost.
	 */
	public static function hashBenchmark(float $hashTime = 0.2): int
	{
		$cost = 9;

		do {
			$timeStart = microtime(true);
			self::hashPassword('thisisatestpassword', $cost);
			$timeTaken = microtime(true) - $timeStart;
			$cost++;
		} while ($timeTaken < $hashTime);

		return $cost;
	}

	/**
	 * Generates a random password.
	 *
	 * @return string A random password.
	 */
	public static function generatePassword(): string
	{
		$password = implode('-', str_split(substr(preg_replace('/\W/', '', base64_encode(random_bytes(18))), 0, 18), 6));

		// Maybe a mod wants to do something different?
		IntegrationHook::call('integrate_generate_password', [&$password]);

		return $password;
	}

	/**
	 * Generate a random validation code.
	 *
	 * @return string A random validation code
	 */
	public static function generateValidationCode(): string
	{
		return bin2hex(random_bytes(5));
	}

	/**
	 * Gets the minimum required password length.
	 *
	 * @return int The minimum required password length.
	 */
	public static function minimumPasswordLength(): int
	{
		return empty(Config::$modSettings['password_strength']) ? 6 : 8;
	}

	/**
	 * Checks whether a password meets the current forum rules.
	 *
	 * Called when registering and when choosing a new password in the profile.
	 *
	 * If password checking is enabled, will check that none of the words in
	 * $restrict_in appear in the password.
	 *
	 * Returns an error identifier if the password is invalid, or null if valid.
	 *
	 * @param string $password The desired password.
	 * @param string $username The username.
	 * @param array $restrict_in An array of restricted strings that cannot be
	 *    part of the password (email address, username, etc.)
	 * @return null|string Null if valid or a string indicating the problem.
	 */
	public static function validatePassword(
		#[\SensitiveParameter]
		string $password,
		string $username,
		array $restrict_in = [],
	): ?string {
		// Perform basic requirements first.
		if (Utils::entityStrlen($password) < self::minimumPasswordLength()) {
			return 'short';
		}

		// Maybe we need some more fancy password checks.
		$pass_error = '';

		IntegrationHook::call('integrate_validatePassword', [$password, $username, $restrict_in, &$pass_error]);

		if (!empty($pass_error)) {
			return $pass_error;
		}

		if (
			// Check if password appears in the restricted strings.
			preg_match('~\b' . preg_quote($password, '~') . '\b~u', implode(' ', $restrict_in))
			// Check if password appears in the username.
			|| preg_match('~\b' . preg_quote($password, '~') . '\b~iu', $username)
			// Check if username appears in the password.
			|| preg_match('~\b' . preg_quote($username, '~') . '\b~iu', $password)
		) {
			return 'restricted_words';
		}

		// Use zxcvbn to assess the password's strength.
		$zxcvbn = new Zxcvbn();
		$strength = $zxcvbn->passwordStrength($password, array_merge([$username], $restrict_in));

		if ((int) $strength['score'] < (Config::$modSettings['password_strength'] ?? 0) + 2) {
			// List of known feedback strings from zxcvbn mapped to Lang::$txt keys.
			$feedback_strings = [
				'This is a top-10 common password' => 'top_10',
				'This is a top-100 common password' => 'top_100',
				'This is a very common password' => 'very_common',
				'This is similar to a commonly used password' => 'similar_to_common',
				'A word by itself is easy to guess' => 'single_word',
				'Use a few words, avoid common phrases' => 'use_a_few_words',
				'No need for symbols, digits, or uppercase letters' => 'simple_is_fine',
				'Add another word or two. Uncommon words are better.' => 'add_more_words',
				'Recent years are easy to guess' => 'recent_years',
				'Avoid recent years' => 'avoid_recent_years',
				'Avoid years that are associated with you' => 'avoid_personal_years',
				'Dates are often easy to guess' => 'dates_are_easy',
				'Avoid dates and years that are associated with you' => 'avoid_personal_dates_and_years',
				'Straight rows of keys are easy to guess' => 'straight_rows',
				'Short keyboard patterns are easy to guess' => 'short_patterns',
				'Use a longer keyboard pattern with more turns' => 'use_longer_pattern',
				'Sequences like abc or 6543 are easy to guess' => 'sequences',
				'Avoid sequences' => 'avoid_sequences',
				'Reversed words aren\'t much harder to guess' => 'reversed_words',
				'Repeats like "aaa" are easy to guess' => 'repeated_chars',
				'Repeats like "abcabcabc" are only slightly harder to guess than "abc"' => 'repeated_strings',
				'Avoid repeated words and characters' => 'avoid_repeated',
				'Predictable substitutions like \'@\' instead of \'a\' don\'t help very much' => 'l33t_useless',
				'Names and surnames by themselves are easy to guess' => 'names',
				'Common names and surnames are easy to guess' => 'common_names',
				'Capitalization doesn\'t help very much' => 'caps_useless',
				'All-uppercase is almost as easy to guess as all-lowercase' => 'all_caps_useless',
			];

			$feedback = [];

			if (isset($strength['feedback']['warning'], $feedback_strings[$strength['feedback']['warning']])) {
				$feedback[] = Lang::getTxt('profile_error_password_' . $feedback_strings[$strength['feedback']['warning']], file: 'Errors');
			}

			if (!empty($strength['feedback']['suggestions'])) {
				foreach ($strength['feedback']['suggestions'] as $suggestion) {
					if (isset($feedback_strings[$suggestion])) {
						$feedback[] = Lang::getTxt('profile_error_password_' . $feedback_strings[$suggestion], file: 'Errors');
					}
				}
			}

			if (!empty($feedback)) {
				return implode('<br>', $feedback);
			}

			// Generic error message.
			return 'weak';
		}

		// If we get here, the password is strong enough.
		return null;
	}

	/**
	 * Checks whether a username obeys a load of rules.
	 *
	 * @param int $memID The ID of the member
	 * @param string $username The username to validate.
	 * @param bool $return_error Whether to return errors.
	 * @param bool $check_reserved_name Whether to check this against the list
	 *    of reserved names.
	 * @return array|null Null if there are no errors, otherwise an array of
	 *    errors if $return_error is true.
	 */
	public static function validateUsername(int $memID, string $username, bool $return_error = false, bool $check_reserved_name = true): ?array
	{
		$errors = [];

		// Don't use too long a name.
		if (Utils::entityStrlen($username) > 25) {
			$errors[] = ['lang', 'error_long_name'];
		}

		// No name?!  How can you register with no name?
		if ($username == '') {
			$errors[] = ['lang', 'need_username'];
		}

		// Only these characters are permitted.
		if (
			\in_array($username, ['_', '|'])
			|| strpos($username, '[code') !== false
			|| strpos($username, '[/code') !== false
			|| preg_match('~[<>&"\'=\\\\]~', preg_replace('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', '', $username))
		) {
			$errors[] = ['lang', 'error_invalid_characters_username'];
		}

		if (stristr($username, Lang::getTxt('guest_title', file: 'General')) !== false) {
			$errors[] = ['lang', 'username_reserved', 'general', [Lang::getTxt('guest_title', file: 'General')]];
		}

		// $return_error is a promise to hand the problems back rather than die
		// of them, and the caller that asks for it wants XML. isReservedName()
		// dies by default, so say otherwise, or a name on the admin's reserved
		// list answers the registration form's availability check with an error
		// page and puts a row in the error log on the way.
		if ($check_reserved_name && self::isReservedName($username, $memID, false, !$return_error)) {
			$errors[] = ['done', '(' . Utils::htmlspecialchars($username) . ') ' . Lang::getTxt('name_in_use', file: 'General')];
		}

		// Maybe a mod wants to perform more checks?
		IntegrationHook::call('integrate_validate_username', [$username, &$errors]);

		if ($return_error) {
			return $errors;
		}

		if (empty($errors)) {
			return null;
		}

		$error = $errors[0];

		ErrorHandler::fatal(
			Lang::getTxt(
				$error[1],
				(array) ($error[3] ?? []),
				file: 'Errors',
			),
			empty($error[2]) || User::$me->is_admin ? false : $error[2],
		);
	}

	/**
	 * Check if a name is in the reserved words list.
	 * (name, current member id, name/username?.)
	 * - checks if name is a reserved name or username.
	 * - if is_name is false, the name is assumed to be a username.
	 * - the current_id_member variable is used to ignore duplicate matches with
	 *   the current member.
	 *
	 * @param string $name The name to check
	 * @param int $current_id_member The ID of the current member (to avoid false positives with the current member)
	 * @param bool $is_name Whether we're checking against reserved names or just usernames
	 * @param bool $fatal Whether to die with a fatal error if the name is reserved
	 * @return bool False if name is not reserved, otherwise true if $fatal is false or dies with a fatal_lang_error if $fatal is true
	 */
	public static function isReservedName(string $name, int $current_id_member = 0, bool $is_name = true, bool $fatal = true): bool
	{
		$name = Utils::entityDecode($name);
		$checkName = Utils::strtolower($name);

		// Administrators are never restricted ;).
		if (
			!User::$me->allowedTo('moderate_forum')
			&& (
				(
					!empty(Config::$modSettings['reserveName'])
					&& $is_name
				)
				|| (
					!empty(Config::$modSettings['reserveUser'])
					&& !$is_name
				)
			)
		) {
			if (Unicode\SpoofDetector::checkReservedName($name, $fatal)) {
				return true;
			}

			$censor_name = $name;

			if (Lang::censorText($censor_name) != $name) {
				if ($fatal) {
					ErrorHandler::fatalLang('name_censored', 'password', [$name]);
				}

				return true;
			}
		}

		// Characters we just shouldn't allow, regardless.
		foreach (['*'] as $char) {
			if (strpos($checkName, $char) !== false) {
				if ($fatal) {
					ErrorHandler::fatalLang('username_reserved', 'password', [$char]);
				}

				return true;
			}
		}

		/*
		 * A name someone else already answers to is taken, not forbidden, and
		 * the two want telling apart: the checks above are about what an admin
		 * banned, and dying with "that name is reserved" is the right answer to
		 * them. These two say "somebody has this already", which is ordinary
		 * and is what every caller is asking about, so hand the answer back and
		 * let them phrase it. 2.1 asked the members table here and did the same.
		 */
		// Check for similar existing member names.
		if (Unicode\SpoofDetector::checkSimilarMemberName($name, $current_id_member, false)) {
			return true;
		}

		// Does the name resemble a member group name?
		if (Unicode\SpoofDetector::checkSimilarGroupName($name, false)) {
			return true;
		}

		// Okay, they passed.
		$is_reserved = false;

		// Maybe a mod wants to perform further checks?
		IntegrationHook::call('integrate_check_name', [$checkName, &$is_reserved, $current_id_member, $is_name]);

		return $is_reserved;
	}

	/**
	 * Checks whether the given User is subject to any bans.
	 *
	 * Caches this information for optimization purposes.
	 *
	 * @param User $user An instance of SMF\User.
	 * @param bool $force_check Whether to force a recheck.
	 * @return array Info about any bans placed on this user.
	 */
	public static function checkBans(User $user, bool $force_check = false): array
	{
		$bans = [];

		$restrictions = [
			'cannot_access',
			'cannot_login',
			'cannot_post',
			'cannot_register',
		];

		// You cannot be banned if you are an admin.
		if ($user->is_admin) {
			return $bans;
		}

		// Only check the ban every so often, to reduce load.
		if (
			!$force_check
			&& ($_SESSION['ban']['last_checked'] ?? NAN) >= (Config::$modSettings['banLastUpdated'] ?? NAN)
			&& ($_SESSION['ban']['id_member'] ?? NAN) == ($user->id ?? NAN)
			&& ($_SESSION['ban']['ip'] ?? NAN) == ($user->ip ?? NAN)
			&& ($_SESSION['ban']['ip2'] ?? NAN) == ($user->ip2 ?? NAN)
			&& ($_SESSION['ban']['email'] ?? NAN) == ($user->email ?? NAN)
		) {
			foreach ($restrictions as $restriction) {
				if (isset($_SESSION['ban'][$restriction])) {
					$bans[$restriction] = $_SESSION['ban'][$restriction];
				}
			}

			if (isset($_SESSION['ban']['expire_time'])) {
				$bans['expire_time'] = $_SESSION['ban']['expire_time'];
			}

			return $bans;
		}

		// Innocent until proven guilty. (but we know you are! :P)
		$ban_query = [];
		$ban_query_vars = ['current_time' => time()];

		// Check both IP addresses.
		foreach (['ip', 'ip2'] as $ip_number) {
			if (!isset($user->{$ip_number})) {
				continue;
			}

			if ($ip_number == 'ip2' && $user->ip2 == $user->ip) {
				continue;
			}

			$ban_query[] = ' {inet:' . $ip_number . '} BETWEEN bi.ip_low and bi.ip_high';
			$ban_query_vars[$ip_number] = $user->{$ip_number};

			// IP was valid, maybe there's also a hostname...
			if (empty(Config::$modSettings['disableHostnameLookup']) && $user->{$ip_number} != 'unknown') {
				$ip = new IP($user->{$ip_number});
				$hostname = $ip->getHost();

				if (\strlen($hostname) > 0) {
					$ban_query[] = '({string:hostname' . $ip_number . '} LIKE bi.hostname)';
					$ban_query_vars['hostname' . $ip_number] = $hostname;
				}
			}
		}

		// Is their email address banned?
		$folded_email = EmailAddress::create($user->email ?? '')->casefolded();

		if (\strlen($folded_email) > 0) {
			$ban_query[] = '({string:email} LIKE bi.email_address)';
			$ban_query_vars['email'] = $folded_email;
		}

		// How about this user?
		if (!empty($user->id)) {
			$ban_query[] = 'bi.id_member = {int:id_member}';
			$ban_query_vars['id_member'] = $user->id;
		}

		// Check the ban, if there's information.
		if (!empty($ban_query)) {
			// Store every type of ban that applies to you in your session.
			$request = Db::$db->query(
				'SELECT
					bi.id_ban,
					bi.email_address,
					bi.id_member,
					bg.cannot_access,
					bg.cannot_register,
					bg.cannot_post,
					bg.cannot_login,
					bg.reason,
					COALESCE(bg.expire_time, 0) AS expire_time
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (
						bg.id_ban_group = bi.id_ban_group
						AND (
							bg.expire_time IS NULL
							OR bg.expire_time > {int:current_time}
						)
					)
				WHERE
					(' . implode(' OR ', $ban_query) . ')',
				$ban_query_vars,
				identifier: 'ban_like',
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				foreach ($restrictions as $restriction) {
					if (!empty($row[$restriction])) {
						$bans[$restriction]['reason'] = $row['reason'];
						$bans[$restriction]['ids'][] = $row['id_ban'];

						if (!empty($row['id_member'])) {
							$bans[$restriction]['id_member'] = $user->id;
						}

						if (!empty($row['email_address'])) {
							$bans[$restriction]['email_address'] = $user->email;
						}

						$bans['expire_time'] = match (true) {
							$row['expire_time'] == 0 => 0,
							($bans['expire_time'] ?? 1) == 0 => 0,
							default => max($row['expire_time'], $bans['expire_time'] ?? 0),
						};
					}
				}
			}
			Db::$db->free_result($request);
		}

		// If this is the current user, remember for later.
		if ($user->is_me) {
			$_SESSION['ban'] = array_merge(
				[
					'last_checked' => time(),
					'id_member' => $user->id,
					'ip' => $user->ip,
					'ip2' => $user->ip2,
					'email' => $user->email,
				],
				$bans,
			);
		}

		return $bans;
	}

	/**
	 * Check if a specific confirm parameter was given.
	 *
	 * @param string $action The action we want to check against.
	 * @return bool|string True if the check passed. Otherwise a token string.
	 */
	public static function checkConfirm(string $action): bool|string
	{
		if (
			isset($_GET['confirm'], $_SESSION['confirm_' . $action])
			&& hash_equals(
				$_SESSION['confirm_' . $action],
				hash_hmac('md5', $_SERVER['HTTP_USER_AGENT'], $_GET['confirm']),
			)
		) {
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

			if (!\in_array($_REQUEST['seqnum'], $_SESSION['forms'])) {
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

			while (empty(Utils::$context['form_sequence_number']) || \in_array(Utils::$context['form_sequence_number'], $_SESSION['forms'])) {
				Utils::$context['form_sequence_number'] = random_int(1, 16000000);
			}
		}
		// Don't check, just free the stack number.
		elseif ($action == 'free' && isset($_REQUEST['seqnum']) && \in_array($_REQUEST['seqnum'], $_SESSION['forms'])) {
			$_SESSION['forms'] = array_diff($_SESSION['forms'], [$_REQUEST['seqnum']]);
		}
		// Bail out if $action is unknown.
		elseif ($action != 'free') {
			trigger_error(Lang::getTxt('check_submit_once_invalid_action', [$action], file: 'Errors'), E_USER_WARNING);
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
				[
					User::$me->ip,
					time(),
					$error_type,
				],
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
	 * This protects against brute force attacks on a member's password.
	 * Importantly, even if the password was right we DON'T TELL THEM!
	 *
	 * @param int $id_member The ID of the member.
	 * @param string $member_name The name of the member.
	 * @param bool|string $password_flood_value False if we don't have a flood
	 *    value, otherwise a string with a timestamp and number of tries
	 *    separated by a `|` character.
	 * @param bool $was_correct Whether or not the password was correct.
	 * @param bool $tfa Whether we're validating for two-factor authentication.
	 */
	public static function validatePasswordFlood(
		int $id_member,
		string $member_name,
		bool|string $password_flood_value = false,
		bool $was_correct = false,
		bool $tfa = false,
	): void {
		// As this is only brute protection, we allow 5 attempts every 10 seconds.

		// Destroy any session or cookie data about this member, as they validated wrong.
		// Only if they're not validating for 2FA
		if (!$tfa) {
			Cookie::setLoginCookie(-3600, 0);

			if (isset($_SESSION['login_' . Config::$cookiename])) {
				unset($_SESSION['login_' . Config::$cookiename]);
			}
		}

		// We need a member!
		if (!$id_member) {
			// Redirect back!
			Utils::redirectexit();

			// Probably not needed, but still make sure...
			ErrorHandler::fatalLang('no_access', false);
		}

		// Right, have we got a flood value?
		if ($password_flood_value !== false) {
			@list($time_stamp, $number_tries) = explode('|', $password_flood_value);
		}

		// Timestamp or number of tries invalid?
		if (empty($number_tries) || empty($time_stamp)) {
			$number_tries = 0;
			$time_stamp = time();
		}

		// They've failed logging in already
		if (!empty($number_tries)) {
			// Give them less chances if they failed before
			$number_tries = $time_stamp < time() - 20 ? 2 : $number_tries;

			// They are trying too fast, make them wait longer
			if ($time_stamp < time() - 10) {
				$time_stamp = time();
			}
		}

		$number_tries++;

		// Broken the law?
		if ($number_tries > 5) {
			ErrorHandler::fatalLang('login_threshold_brute_fail', 'login', [$member_name]);
		}

		// Otherwise set the members data. If they correct on their first attempt then we actually clear it, otherwise we set it!
		$member = current(User::load($id_member, dataset: UserDataset::None));
		$member->passwd_flood = $was_correct && $number_tries == 1 ? '' : $time_stamp . '|' . $number_tries;
		$member->save();
	}

	/**
	 * Checks for the existence and security status of specific files and directories
	 * required for the proper functioning of the system. Ensures that security measures
	 * are applied and generates a list of warnings for any issues detected.
	 *
	 * Warnings include:
	 * - Missing or insecure critical files (e.g., install.php, upgrade.php).
	 * - Directories not properly secured (e.g., cache directory).
	 * - Missing legal agreement or policy documents.
	 * - Missing authentication secrets.
	 *
	 * @return bool|array Returns an array of warnings if any security risks are detected,
	 *    or false if the user lacks the necessary permissions or is a guest.
	 */
	public static function checkSecurityFiles(): bool|array
	{
		if (User::$me->allowedTo('admin_forum') && !User::$me->is_guest) {
			$security_files = [
				'install.php',
				'upgrade.php',
				'convert.php',
				'repair_paths.php',
				'repair_settings.php',
				'Settings.php~',
				'Settings_bak.php~',
			];

			// Add your own files.
			IntegrationHook::call('integrate_security_files', [&$security_files]);

			// Filter out missing security files
			$security_files = array_filter($security_files, function ($file) {
				return file_exists(Config::$boarddir . '/' . $file);
			});

			// Determine attachment upload directory path
			$path = (
				!empty(Config::$modSettings['currentAttachmentUploadDir'])
				? Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']]
				: Config::$modSettings['attachmentUploadDir']
			);

			// Secure directories
			self::secureDirectory($path, true);
			self::secureDirectory(Config::$cachedir);

			// Check for required files
			$agreement = (
				!empty(Config::$modSettings['requireAgreement'])
				&& !file_exists(Config::$languagesdir . '/en_US/agreement.txt')
			);
			$policy_agreement = (
				!empty(Config::$modSettings['requirePolicyAgreement'])
				&& empty(Config::$modSettings['policy_' . Config::$language])
			);

			// Compile warnings
			$warnings = [];

			foreach ($security_files as $security_file) {
				$warnings['file'][] = ['not_removed', [
					'filename' => $security_file,
				]];

				if (\in_array($security_file, ['Settings.php~', 'Settings_bak.php~'])) {
					$warnings['file'][] = ['not_removed_extra', [
						'backup_filename' => $security_file,
						'filename' => substr($security_file, 0, -1),
					]];
				}
			}

			if (!empty(CacheApi::$enable) && !is_writable(Config::$cachedir)) {
				$warnings[] = ['cache_writable'];
			}

			if ($agreement) {
				$warnings[] = ['agreement_missing'];
			}

			if ($policy_agreement) {
				$warnings[] = ['policy_agreement_missing'];
			}

			if (!empty(Utils::$context['auth_secret_missing'])) {
				$warnings[] = ['auth_secret_missing'];
			}

			return $warnings;
		}

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

			if (!file_exists($path . '/.htaccess')) {
				if (@file_put_contents($path . '/.htaccess', $contents) !== \strlen($contents)) {
					$errors[] = 'htaccess_cannot_create_file';
				}
			} elseif (file_get_contents($path . '/.htaccess') !== $contents) {
				$errors[] = 'htaccess_exists';
				continue;
			}

			// Next, the index.php file
			$contents = <<<END
				<?php

				// Try to handle it with the upper level index.php. (it should know what to do.)
				if (file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'index.php')) {
					include dirname(__DIR__) . DIRECTORY_SEPARATOR . 'index.php';
				} else {
					exit;
				}

				END;

			if (!file_exists($path . '/index.php')) {
				if (@file_put_contents($path . '/index.php', $contents) !== \strlen($contents)) {
					$errors[] = 'index-php_cannot_create_file';
				}
			} elseif (!\in_array(file_get_contents($path . '/index.php'), [$contents, str_replace('DIRECTORY_SEPARATOR . \'index.php\'', '\'/index.php\'', $contents)])) {
				$errors[] = 'index-php_exists';
				continue;
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * This sets the X-Frame-Options header.
	 *
	 * @param string|null $override An option to override (either 'SAMEORIGIN' or 'DENY')
	 * @since 2.1
	 */
	public static function frameOptionsHeader(?string $override = null): void
	{
		$option = 'SAMEORIGIN';

		if (\is_null($override) && !empty(Config::$modSettings['frame_security'])) {
			$option = Config::$modSettings['frame_security'];
		} elseif (\in_array($override, ['SAMEORIGIN', 'DENY'])) {
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

		$origin = Url::create(trim($_SERVER['HTTP_ORIGIN']), true)->validate()->toUtf8();
		$forumurl = Url::create(trim(Config::$boardurl), true)->validate()->toUtf8();

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
			if (!empty(Config::$modSettings['globalCookies'])) {
				$allowed_origins[++$i] = array_merge(
					Url::create('//*.' . ltrim(Cookie::getDefaultDomain(), '.'))->parse(),
					['type' => 'subdomain'],
				);
			}

			// Support forum_alias_urls as well, since those are supported by our login cookie.
			if (!empty(Config::$modSettings['forum_alias_urls'])) {
				foreach (explode(',', Config::$modSettings['forum_alias_urls']) as $alias) {
					$allowed_origins[++$i] = array_merge(
						Url::create((!str_contains($alias, '//') ? '//' : '') . trim($alias))->parse(),
						['type' => 'alias'],
					);
				}
			}

			// Additional CORS domains.
			if (!empty(Config::$modSettings['cors_domains'])) {
				foreach (explode(',', Config::$modSettings['cors_domains']) as $cors_domain) {
					$allowed_origins[++$i] = array_merge(
						Url::create((!str_contains($cors_domain, '//') ? '//' : '') . trim($cors_domain))->parse(),
						['type' => 'additional'],
					);

					if (str_starts_with($allowed_origins[$i]['host'], '*')) {
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
				if (str_starts_with($allowed_origin['host'], '*')) {
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

	/*************************
	 * Internal static methods
	 *************************/

	/**
	 * Checks a user-supplied password against other possible encrypted strings.
	 *
	 * If a match is found, the old encrypted string is replaced with an updated
	 * version that uses modern encryption.
	 *
	 * This allows seamlessly updating the encryption after the forum has been
	 * upgraded or converted.
	 *
	 * @param string $password The password to check.
	 * @param User $member The member whose password we are checking.
	 * @param int $allowed_fallbacks Which fallbacks to check.
	 *    Must be a bitmask of this class's PASSWORD_FALLBACK_* constants.
	 * @return bool Whether the supplied password was correct.
	 */
	protected static function checkPasswordFallbacks(
		#[\SensitiveParameter]
		string $password,
		User $member,
		int $allowed_fallbacks,
	): bool {
		// Maybe we were too hasty... let's try some other authentication methods.
		$other_passwords = [];

		// SMF 2.1 prepended the username before the password.
		if (
			$allowed_fallbacks & self::PASSWORD_FALLBACK_SMF21
			&& self::hashVerifyPassword(Utils::strtolower($member->username) . $password, $member->passwd)
		) {
			$other_passwords[] = $member->passwd;
		}

		// SMF 2.0 and 1.1.
		if (
			$allowed_fallbacks & self::PASSWORD_FALLBACK_SMF20
			&& \strlen($member->passwd) == 40
		) {
			// Maybe they are using a hash from before the password fix.
			// This is also valid for SMF 1.1 to 2.0 style of hashing, changed to bcrypt in SMF 2.1
			$other_passwords[] = sha1(strtolower($member->username) . $password);

			// Perhaps we converted to UTF-8 and have a valid password being hashed differently.
			if (!empty(Config::$modSettings['previousCharacterSet']) && Config::$modSettings['previousCharacterSet'] != 'utf8') {
				// Try iconv first, for no particular reason.
				if (\function_exists('iconv')) {
					$other_passwords['iconv'] = sha1(strtolower(iconv('UTF-8', Config::$modSettings['previousCharacterSet'], $member->username)) . Utils::htmlspecialcharsDecode(iconv('UTF-8', Config::$modSettings['previousCharacterSet'], $password)));
				}

				// Say it aint so, iconv failed!
				if (empty($other_passwords['iconv']) && \function_exists('mb_convert_encoding')) {
					$other_passwords[] = sha1(strtolower(mb_convert_encoding($member->username, 'UTF-8', Config::$modSettings['previousCharacterSet'])) . Utils::htmlspecialcharsDecode(mb_convert_encoding($password, 'UTF-8', Config::$modSettings['previousCharacterSet'])));
				}
			}
		}

		// SMF 1.0 and YaBB SE.
		if (
			$allowed_fallbacks & self::PASSWORD_FALLBACK_SMF10
			&& \strlen($member->passwd) == 32
			&& $member->password_salt == ''
		) {
			$other_passwords[] = hash_hmac('md5', $password, strtolower($member->username));
		}

		// Other forum software packages, for the case of conversions.
		if (
			$allowed_fallbacks & self::PASSWORD_FALLBACK_OTHER
			&& !empty(Config::$modSettings['enable_password_conversion'])
		) {
			// If we're checking for recent conversions from other forum software
			// and $password contains special HTML characters, check for variants
			// with the special characters encoded as entities.
			if ($password !== htmlspecialchars($password, ENT_QUOTES, double_encode: false)) {
				$alt_passwords = array_filter([
					$password,
					htmlspecialchars($password, ENT_NOQUOTES, double_encode: false),
					htmlspecialchars($password, ENT_COMPAT, double_encode: false),
					htmlspecialchars($password, ENT_QUOTES, double_encode: false),
					htmlspecialchars($password, ENT_QUOTES | ENT_HTML5, double_encode: false),
				]);
			} else {
				$alt_passwords = [$password];
			}

			foreach ($alt_passwords as $p) {
				// None of the below cases will be used most of the time (because the salt is normally set.)
				if ($member->password_salt == '') {
					// Discus, MD5 (used a lot), SHA-1 (used some), IkonBoard, and none at all.
					switch (\strlen($member->passwd)) {
						case 13:
							$other_passwords[] = crypt($p, substr($p, 0, 2));
							$other_passwords[] = crypt($p, substr($member->passwd, 0, 2));
							$other_passwords[] = crypt($p, $member->passwd);

							// This one is a strange one... MyPHP, crypt() on the MD5 hash.
							$other_passwords[] = crypt(md5($p), md5($p));
							break;

						case 32:
							$other_passwords[] = md5($p);
							$other_passwords[] = md5($p . strtolower($member->username));
							$other_passwords[] = md5(md5($p));

							// APBoard 2 Login Method.
							$other_passwords[] = md5(crypt($p, 'CRYPT_MD5'));
							break;

						case 34:
							// phpBB3.
							$other_passwords[] = self::phpBB3_password_check($p, $member->passwd);
							break;

						case 40:
							$other_passwords[] = sha1($p);
							break;

						case 64:
							// Snitz style - SHA-256.
							$other_passwords[] = hash('sha256', $p);
							break;
					}

					$other_passwords[] = $p;
				}
				// If the salt is set let's try some other options
				else {
					switch (\strlen($member->passwd)) {
						case 32:
							// MyBB
							$other_passwords[] = md5(md5($member->password_salt) . md5($p));

							// vBulletin 3 style hashing?  Let's welcome them with open arms \o/.
							$other_passwords[] = md5(md5($p) . stripslashes($member->password_salt));

							// Hmm.. p'raps it's Invision 2 style?
							$other_passwords[] = md5(md5($member->password_salt) . md5($p));

							// Some common md5 ones.
							$other_passwords[] = md5($member->password_salt . $p);
							$other_passwords[] = md5($p . $member->password_salt);
							break;

						case 40:
							// BurningBoard3 style of hashing.
							$other_passwords[] = sha1($member->password_salt . sha1($member->password_salt . sha1($p)));
							// PunBB
							$other_passwords[] = sha1($member->password_salt . sha1($p));
							break;

						case 64:
							// PHP-Fusion
							$other_passwords[] = hash_hmac('sha256', $p, $member->password_salt);
							break;
					}
				}
			}
		}

		// Allows mods to easily extend the $other_passwords array
		if ($allowed_fallbacks & self::PASSWORD_FALLBACK_OTHER) {
			IntegrationHook::call('integrate_other_passwords', [&$other_passwords]);
		}

		// Did anything match?
		// Check all options with hash_equals() to prevent timing attacks.
		foreach ($other_passwords as $other_password) {
			$is_correct = ($is_correct ?? 0) | hash_equals($member->passwd, $other_password);
		}

		return (bool) $is_correct;
	}

	/**
	 * Custom encryption for phpBB3 based passwords.
	 *
	 * @param string $passwd The password to check.
	 * @param string $passwd_hash The hashed password stored in the database.
	 * @return ?string The hashed version of $passwd.
	 */
	protected static function phpBB3_password_check(
		#[\SensitiveParameter]
		string $passwd,
		string $passwd_hash,
	): ?string {
		// Too long or too short?
		if (\strlen($passwd_hash) != 34) {
			return null;
		}

		// Range of characters allowed.
		$range = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		// Tests
		$strpos = strpos($range, $passwd_hash[3]);
		$count = 1 << $strpos;
		$salt = substr($passwd_hash, 4, 8);

		$hash = md5($salt . $passwd, true);

		for (; $count != 0; --$count) {
			$hash = md5($hash . $passwd, true);
		}

		$output = substr($passwd_hash, 0, 12);
		$i = 0;

		while ($i < 16) {
			$value = \ord($hash[$i++]);
			$output .= $range[$value & 0x3f];

			if ($i < 16) {
				$value |= \ord($hash[$i]) << 8;
			}

			$output .= $range[($value >> 6) & 0x3f];

			if ($i++ >= 16) {
				break;
			}

			if ($i < 16) {
				$value |= \ord($hash[$i]) << 16;
			}

			$output .= $range[($value >> 12) & 0x3f];

			if ($i++ >= 16) {
				break;
			}

			$output .= $range[($value >> 18) & 0x3f];
		}

		// Return now.
		return $output;
	}
}
