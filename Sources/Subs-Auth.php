<?php

/**
 * This file has functions in it to do with authentication, user handling, and the like.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF'))
	die('No direct access...');

// Some functions have moved.
class_exists('SMF\\Cookie');
class_exists('SMF\\User');
class_exists('SMF\\Actions\\Admin');

/**
 * Throws guests out to the login screen when guest access is off.
 * - sets $_SESSION['login_url'] to $_SERVER['REQUEST_URL'].
 * - uses the 'kick_guest' sub template found in Login.template.php.
 */
function KickGuest()
{
	Theme::load();
	Lang::load('Login');
	Theme::loadTemplate('Login');
	SecurityToken::create('login');

	// Never redirect to an attachment
	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	Utils::$context['sub_template'] = 'kick_guest';
	Utils::$context['page_title'] = Lang::$txt['login'];
}

/**
 * Display a message about the forum being in maintenance mode.
 * - display a login screen with sub template 'maintenance'.
 * - sends a 503 header, so search engines don't bother indexing while we're in maintenance mode.
 */
function InMaintenance()
{
	Lang::load('Login');
	Theme::loadTemplate('Login');
	SecurityToken::create('login');

	// Send a 503 header, so search engines don't bother indexing while we're in maintenance mode.
	send_http_status(503, 'Service Temporarily Unavailable');

	// Basic template stuff..
	Utils::$context['sub_template'] = 'maintenance';
	Utils::$context['title'] = Utils::htmlspecialchars(Config::$mtitle);
	Utils::$context['description'] = &Config::$mmessage;
	Utils::$context['page_title'] = Lang::$txt['maintain_mode'];
}

/**
 * Hashes username with password
 *
 * @param string $username The username
 * @param string $password The unhashed password
 * @param int $cost The cost
 * @return string The hashed password
 */
function hash_password($username, $password, $cost = null)
{
	$cost = empty($cost) ? (empty(Config::$modSettings['bcrypt_hash_cost']) ? 10 : Config::$modSettings['bcrypt_hash_cost']) : $cost;

	return password_hash(Utils::strtolower($username) . $password, PASSWORD_BCRYPT, array(
		'cost' => $cost,
	));
}

/**
 * Verifies a raw SMF password against the bcrypt'd string
 *
 * @param string $username The username
 * @param string $password The password
 * @param string $hash The hashed string
 * @return bool Whether the hashed password matches the string
 */
function hash_verify_password($username, $password, $hash)
{
	return password_verify(Utils::strtolower($username) . $password, $hash);
}

/**
 * Benchmarks the server to figure out an appropriate cost factor (minimum 9)
 *
 * @param float $hashTime Time to target, in seconds
 * @return int The cost
 */
function hash_benchmark($hashTime = 0.2)
{
	$cost = 9;
	do
	{
		$timeStart = microtime(true);
		hash_password('test', 'thisisatestpassword', $cost);
		$timeTaken = microtime(true) - $timeStart;
		$cost++;
	}
	while ($timeTaken < $hashTime);

	return $cost;
}

// Based on code by "examplehash at user dot com".
// https://www.php.net/manual/en/function.hash-equals.php#125034
if (!function_exists('hash_equals'))
{
	/**
	 * A compatibility function for when PHP's "hash_equals" function isn't available
	 * @param string $known_string A known hash
	 * @param string $user_string The hash of the user string
	 * @return bool Whether or not the two are equal
	 */
	function hash_equals($known_string, $user_string)
	{
		$known_string = (string) $known_string;
		$user_string = (string) $user_string;

		$sx = 0;
		$sy = strlen($known_string);
		$uy = strlen($user_string);
		$result = $sy - $uy;
		for ($ux = 0; $ux < $uy; $ux++)
		{
			$result |= ord($user_string[$ux]) ^ ord($known_string[$sx]);
			$sx = ($sx + 1) % $sy;
		}

		return !$result;
	}
}

?>