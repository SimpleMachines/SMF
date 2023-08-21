<?php

/**
 * This file has the very important job of ensuring forum security.
 * This task includes banning and permissions, namely.
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
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\User;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

// Some functions have moved.
class_exists('SMF\\SecurityToken');
class_exists('SMF\\User');

/**
 * Check if a specific confirm parameter was given.
 *
 * @param string $action The action we want to check against
 * @return bool|string True if the check passed or a token
 */
function checkConfirm($action)
{
	if (isset($_GET['confirm']) && isset($_SESSION['confirm_' . $action]) && md5($_GET['confirm'] . $_SERVER['HTTP_USER_AGENT']) == $_SESSION['confirm_' . $action])
		return true;

	else
	{
		$token = md5(Utils::randomInt() . session_id() . (string) microtime() . Config::$modSettings['rand_seed']);
		$_SESSION['confirm_' . $action] = md5($token . $_SERVER['HTTP_USER_AGENT']);

		return $token;
	}
}

/**
 * Check whether a form has been submitted twice.
 * Registers a sequence number for a form.
 * Checks whether a submitted sequence number is registered in the current session.
 * Depending on the value of is_fatal shows an error or returns true or false.
 * Frees a sequence number from the stack after it's been checked.
 * Frees a sequence number without checking if action == 'free'.
 *
 * @param string $action The action - can be 'register', 'check' or 'free'
 * @param bool $is_fatal Whether to die with a fatal error
 * @return void|bool If the action isn't check, returns nothing, otherwise returns whether the check was successful
 */
function checkSubmitOnce($action, $is_fatal = true)
{
	if (!isset($_SESSION['forms']))
		$_SESSION['forms'] = array();

	// Register a form number and store it in the session stack. (use this on the page that has the form.)
	if ($action == 'register')
	{
		Utils::$context['form_sequence_number'] = 0;
		while (empty(Utils::$context['form_sequence_number']) || in_array(Utils::$context['form_sequence_number'], $_SESSION['forms']))
			Utils::$context['form_sequence_number'] = mt_rand(1, 16000000);
	}
	// Check whether the submitted number can be found in the session.
	elseif ($action == 'check')
	{
		if (!isset($_REQUEST['seqnum']))
			return true;
		elseif (!in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		{
			$_SESSION['forms'][] = (int) $_REQUEST['seqnum'];
			return true;
		}
		elseif ($is_fatal)
			ErrorHandler::fatalLang('error_form_already_submitted', false);
		else
			return false;
	}
	// Don't check, just free the stack number.
	elseif ($action == 'free' && isset($_REQUEST['seqnum']) && in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		$_SESSION['forms'] = array_diff($_SESSION['forms'], array($_REQUEST['seqnum']));
	elseif ($action != 'free')
	{
		Lang::load('Errors');
		trigger_error(sprintf(Lang::$txt['check_submit_once_invalid_action'], $action), E_USER_WARNING);
	}
}

/**
 * This function attempts to protect from spammed messages and the like.
 * The time taken depends on error_type - generally uses the modSetting.
 *
 * @param string $error_type The error type. Also used as a Lang::$txt index (not an actual string).
 * @param boolean $only_return_result Whether you want the function to die with a fatal_lang_error.
 * @return bool Whether they've posted within the limit
 */
function spamProtection($error_type, $only_return_result = false)
{
	// Certain types take less/more time.
	$timeOverrides = array(
		'login' => 2,
		'register' => 2,
		'remind' => 30,
		'sendmail' => Config::$modSettings['spamWaitTime'] * 5,
		'reporttm' => Config::$modSettings['spamWaitTime'] * 4,
		'search' => !empty(Config::$modSettings['search_floodcontrol_time']) ? Config::$modSettings['search_floodcontrol_time'] : 1,
	);

	call_integration_hook('integrate_spam_protection', array(&$timeOverrides));

	// Moderators are free...
	if (!User::$me->allowedTo('moderate_board'))
		$timeLimit = isset($timeOverrides[$error_type]) ? $timeOverrides[$error_type] : Config::$modSettings['spamWaitTime'];
	else
		$timeLimit = 2;

	// Delete old entries...
	Db::$db->query('', '
		DELETE FROM {db_prefix}log_floodcontrol
		WHERE log_time < {int:log_time}
			AND log_type = {string:log_type}',
		array(
			'log_time' => time() - $timeLimit,
			'log_type' => $error_type,
		)
	);

	// Add a new entry, deleting the old if necessary.
	Db::$db->insert('replace',
		'{db_prefix}log_floodcontrol',
		array('ip' => 'inet', 'log_time' => 'int', 'log_type' => 'string'),
		array(User::$me->ip, time(), $error_type),
		array('ip', 'log_type')
	);

	// If affected is 0 or 2, it was there already.
	if (Db::$db->affected_rows() != 1)
	{
		// Spammer!  You only have to wait a *few* seconds!
		if (!$only_return_result)
			ErrorHandler::fatalLang($error_type . '_WaitTime_broken', false, array($timeLimit));

		return true;
	}

	// They haven't posted within the limit.
	return false;
}

/**
 * A generic function to create a pair of index.php and .htaccess files in a directory
 *
 * @param string|array $paths The (absolute) directory path
 * @param boolean $attachments Whether this is an attachment directory
 * @return bool|array True on success an array of errors if anything fails
 */
function secureDirectory($paths, $attachments = false)
{
	$errors = array();

	// Work with arrays
	$paths = (array) $paths;

	if (empty($paths))
		$errors[] = 'empty_path';

	if (!empty($errors))
		return $errors;

	foreach ($paths as $path)
	{
		if (!is_writable($path))
		{
			$errors[] = 'path_not_writable';

			continue;
		}

		$directory_name = basename($path);

		$close = empty($attachments) ? '
</Files>' : '
	Allow from localhost
</Files>

RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml';

		if (file_exists($path . '/.htaccess'))
		{
			$errors[] = 'htaccess_exists';

			continue;
		}

		else
		{
			$fh = @fopen($path . '/.htaccess', 'w');

			if ($fh)
			{
				fwrite($fh, '<Files *>
	Order Deny,Allow
	Deny from all' . $close);
				fclose($fh);
			}

			else
				$errors[] = 'htaccess_cannot_create_file';
		}

		if (file_exists($path . '/index.php'))
		{
			$errors[] = 'index-php_exists';

			continue;
		}

		else
		{
			$fh = @fopen($path . '/index.php', 'w');

			if ($fh)
			{
				fwrite($fh, '<' . '?php

/**
 * This file is here solely to protect your ' . $directory_name . ' directory.
 */

// Look for Settings.php....
if (file_exists(dirname(dirname(__FILE__)) . \'/Settings.php\'))
{
	// Found it!
	require(dirname(dirname(__FILE__)) . \'/Settings.php\');
	header(\'location: \' . $boardurl);
}
// Can\'t find it... just forget it.
else
	exit;

?' . '>');
				fclose($fh);
			}

			else
				$errors[] = 'index-php_cannot_create_file';
		}
	}

	if (!empty($errors))
		return $errors;

	else
		return true;
}

/**
 * This sets the X-Frame-Options header.
 *
 * @param string $override An option to override (either 'SAMEORIGIN' or 'DENY')
 * @since 2.1
 */
function frameOptionsHeader($override = null)
{
	$option = 'SAMEORIGIN';
	if (is_null($override) && !empty(Config::$modSettings['frame_security']))
		$option = Config::$modSettings['frame_security'];
	elseif (in_array($override, array('SAMEORIGIN', 'DENY')))
		$option = $override;

	// Don't bother setting the header if we have disabled it.
	if ($option == 'DISABLE')
		return;

	// Finally set it.
	header('x-frame-options: ' . $option);

	// And some other useful ones.
	header('x-xss-protection: 1');
	header('x-content-type-options: nosniff');
}

/**
 * This sets the Access-Control-Allow-Origin header.
 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 *
 * @param bool $set_header (Default: true): When false, we will do the logic, but not send the headers.  The relevant logic is still saved in Utils::$context and can be sent manually.
 *
 * @since 2.1
 */
function corsPolicyHeader($set_header = true)
{
	if (empty(Config::$modSettings['allow_cors']) || empty($_SERVER['HTTP_ORIGIN']))
		return;

	foreach (array('origin' => $_SERVER['HTTP_ORIGIN'], 'boardurl_parts' => Config::$boardurl) as $var => $url)
	{
		// Convert any Punycode to Unicode for the sake of comparison, then parse.
		$$var = parse_iri(url_to_iri((string) validate_iri(normalize_iri(trim($url)))));
	}

	// The admin wants weak security... :(
	if (!empty(Config::$modSettings['cors_domains']) && Config::$modSettings['cors_domains'] === '*')
	{
		Utils::$context['cors_domain'] = '*';
		Utils::$context['valid_cors_found'] = 'wildcard';
	}

	// Oh good, the admin cares about security. :)
	else
	{
		$i = 0;

		// Build our list of allowed CORS origins.
		$allowed_origins = array();

		// If subdomain-independent cookies are on, allow CORS requests from subdomains.
		if (!empty(Config::$modSettings['globalCookies']) && !empty(Config::$modSettings['globalCookiesDomain']))
		{
			$allowed_origins[++$i] = array_merge(parse_iri('//*.' . trim(Config::$modSettings['globalCookiesDomain'])), array('type' => 'subdomain'));
		}

		// Support forum_alias_urls as well, since those are supported by our login cookie.
		if (!empty(Config::$modSettings['forum_alias_urls']))
		{
			foreach (explode(',', Config::$modSettings['forum_alias_urls']) as $alias)
				$allowed_origins[++$i] = array_merge(parse_iri((strpos($alias, '//') === false ? '//' : '') . trim($alias)), array('type' => 'alias'));
		}

		// Additional CORS domains.
		if (!empty(Config::$modSettings['cors_domains']))
		{
			foreach (explode(',', Config::$modSettings['cors_domains']) as $cors_domain)
			{
				$allowed_origins[++$i] = array_merge(parse_iri((strpos($cors_domain, '//') === false ? '//' : '') . trim($cors_domain)), array('type' => 'additional'));

				if (strpos($allowed_origins[$i]['host'], '*') === 0)
					 $allowed_origins[$i]['type'] .= '_wildcard';
			}
		}

		// Does the origin match any of our allowed domains?
		foreach ($allowed_origins as $allowed_origin)
		{
			// If a specific scheme is required, it must match.
			if (!empty($allowed_origin['scheme']) && $allowed_origin['scheme'] !== $origin['scheme'])
				continue;

			// If a specific port is required, it must match.
			if (!empty($allowed_origin['port']))
			{
				// Automatically supply the default port for the "special" schemes.
				// See https://url.spec.whatwg.org/#special-scheme
				if (empty($origin['port']))
				{
					switch ($origin['scheme'])
					{
						case 'http':
						case 'ws':
							$origin['port'] = 80;
							break;

						case 'https':
						case 'wss':
							$origin['port'] = 443;
							break;

						case 'ftp':
							$origin['port'] = 21;
							break;

						case 'file':
						default:
							$origin['port'] = null;
							break;
					}
				}

				if ((int) $allowed_origin['port'] !== (int) $origin['port'])
					continue;
			}

			// Wildcard can only be the first character.
			if (strrpos($allowed_origin['host'], '*') > 0)
				continue;

			// Wildcard means allow the domain or any subdomains.
			if (strpos($allowed_origin['host'], '*') === 0)
				$host_regex = '(?:^|\.)' . preg_quote(ltrim($allowed_origin['host'], '*.'), '~') . '$';

			// No wildcard means allow the domain only.
			else
				$host_regex = '^' . preg_quote($allowed_origin['host'], '~') . '$';

			if (preg_match('~' . $host_regex . '~u', $origin['host']))
			{
				Utils::$context['cors_domain'] = trim($_SERVER['HTTP_ORIGIN']);
				Utils::$context['valid_cors_found'] = $allowed_origin['type'];
				break;
			}
		}
	}

	// The default is just to place the root URL of the forum into the policy.
	if (empty(Utils::$context['cors_domain']))
	{
		Utils::$context['cors_domain'] = iri_to_url($boardurl_parts['scheme'] . '://' . $boardurl_parts['host']);

		// Attach the port if needed.
		if (!empty($boardurl_parts['port']))
			Utils::$context['cors_domain'] .= ':' . $boardurl_parts['port'];

		Utils::$context['valid_cors_found'] = 'same';
	}

	Utils::$context['cors_headers'] = 'X-SMF-AJAX';

	// Any additional headers?
	if (!empty(Config::$modSettings['cors_headers']))
	{
		// Cleanup any typos.
		$cors_headers = explode(',', Config::$modSettings['cors_headers']);
		foreach ($cors_headers as &$ch)
			$ch = str_replace(' ', '-', trim($ch));

		Utils::$context['cors_headers'] .= ',' . implode(',', $cors_headers);
	}

	// Allowing Cross-Origin Resource Sharing (CORS).
	if ($set_header && !empty(Utils::$context['valid_cors_found']) && !empty(Utils::$context['cors_domain']))
	{
		header('Access-Control-Allow-Origin: ' . Utils::$context['cors_domain']);
		header('Access-Control-Allow-Headers: ' . Utils::$context['cors_headers']);

		// Be careful with this, you're allowing an external site to allow the browser to send cookies with this.
		if (!empty(Config::$modSettings['allow_cors_credentials']))
			header('Access-Control-Allow-Credentials: true');
	}
}

?>