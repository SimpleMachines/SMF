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
use SMF\Mail;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

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
 * Finds members by email address, username, or real name.
 * - searches for members whose username, display name, or e-mail address match the given pattern of array names.
 * - searches only buddies if buddies_only is set.
 *
 * @param array $names The names of members to search for
 * @param bool $use_wildcards Whether to use wildcards. Accepts wildcards ? and * in the pattern if true
 * @param bool $buddies_only Whether to only search for the user's buddies
 * @param int $max The maximum number of results
 * @return array An array containing information about the matching members
 */
function findMembers($names, $use_wildcards = false, $buddies_only = false, $max = 500)
{

	// If it's not already an array, make it one.
	if (!is_array($names))
		$names = explode(',', $names);

	$maybe_email = false;
	$names_list = array();
	foreach (array_values($names) as $i => $name)
	{
		// Trim, and fix wildcards for each name.
		$names[$i] = trim(Utils::strtolower($name));

		$maybe_email |= strpos($name, '@') !== false;

		// Make it so standard wildcards will work. (* and ?)
		if ($use_wildcards)
			$names[$i] = strtr($names[$i], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '\'' => '&#039;'));
		else
			$names[$i] = strtr($names[$i], array('\'' => '&#039;'));

		$names_list[] = '{string:lookup_name_' . $i . '}';
		$where_params['lookup_name_' . $i] = $names[$i];
	}

	// What are we using to compare?
	$comparison = $use_wildcards ? 'LIKE' : '=';

	// Nothing found yet.
	$results = array();

	// This ensures you can't search someones email address if you can't see it.
	if (($use_wildcards || $maybe_email) && allowedTo('moderate_forum'))
		$email_condition = '
			OR (email_address ' . $comparison . ' \'' . implode('\') OR (email_address ' . $comparison . ' \'', $names) . '\')';
	else
		$email_condition = '';

	// Get the case of the columns right - but only if we need to as things like MySQL will go slow needlessly otherwise.
	$member_name = Db::$db->case_sensitive ? 'LOWER(member_name)' : 'member_name';
	$real_name = Db::$db->case_sensitive ? 'LOWER(real_name)' : 'real_name';

	// Searches.
	$member_name_search = $member_name . ' ' . $comparison . ' ' . implode(' OR ' . $member_name . ' ' . $comparison . ' ', $names_list);
	$real_name_search = $real_name . ' ' . $comparison . ' ' . implode(' OR ' . $real_name . ' ' . $comparison . ' ', $names_list);

	// Search by username, display name, and email address.
	$request = Db::$db->query('', '
		SELECT id_member, member_name, real_name, email_address
		FROM {db_prefix}members
		WHERE (' . $member_name_search . '
			OR ' . $real_name_search . ' ' . $email_condition . ')
			' . ($buddies_only ? 'AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT {int:limit}',
		array_merge($where_params, array(
			'buddy_list' => User::$me->buddies,
			'limit' => $max,
		))
	);
	while ($row = Db::$db->fetch_assoc($request))
	{
		$results[$row['id_member']] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'username' => $row['member_name'],
			'email' => allowedTo('moderate_forum') ? $row['email_address'] : '',
			'href' => Config::$scripturl . '?action=profile;u=' . $row['id_member'],
			'link' => '<a href="' . Config::$scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
		);
	}
	Db::$db->free_result($request);

	// Return all the results.
	return $results;
}

/**
 * Generates a random password for a user and emails it to them.
 * - called by Actions/Profile/Main.php when changing someone's username.
 * - checks the validity of the new username.
 * - generates and sets a new password for the given user.
 * - mails the new password to the email address of the user.
 * - if username is not set, only a new password is generated and sent.
 *
 * @param int $memID The ID of the member
 * @param string $username The new username. If set, also checks the validity of the username
 */
function resetPassword($memID, $username = null)
{
	// Language...
	Lang::load('Login');

	// Get some important details.
	$request = Db::$db->query('', '
		SELECT member_name, email_address, lngfile
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}',
		array(
			'id_member' => $memID,
		)
	);
	list ($user, $email, $lngfile) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	if ($username !== null)
	{
		$old_user = $user;
		$user = trim($username);
	}

	// Generate a random password.
	$newPassword = substr(preg_replace('/\W/', '', md5(Utils::randomInt())), 0, 10);
	$newPassword_sha1 = hash_password($user, $newPassword);

	// Do some checks on the username if needed.
	if ($username !== null)
	{
		User::validateUsername($memID, $user);

		// Update the database...
		User::updateMemberData($memID, array('member_name' => $user, 'passwd' => $newPassword_sha1));
	}
	else
		User::updateMemberData($memID, array('passwd' => $newPassword_sha1));

	call_integration_hook('integrate_reset_pass', array($old_user, $user, $newPassword));

	$replacements = array(
		'USERNAME' => $user,
		'PASSWORD' => $newPassword,
	);

	$emaildata = Mail::loadEmailTemplate('change_password', $replacements, empty($lngfile) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $lngfile);

	// Send them the email informing them of the change - then we're done!
	Mail::send($email, $emaildata['subject'], $emaildata['body'], null, 'chgpass' . $memID, $emaildata['is_html'], 0);
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
 * Returns the length for current hash
 *
 * @return int The length for the current hash
 */
function hash_length()
{
	return 60;
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