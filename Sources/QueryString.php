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
 * Handles the query string, request variables, and session management.
 */
class QueryString
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'cleanRequest' => 'cleanRequest',
			'isFilteredRequest' => 'is_filtered_request',
			'ob_sessrewrite' => 'ob_sessrewrite',
			'matchIPtoCIDR' => 'matchIPtoCIDR',
		],
	];

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Clean the request variables - add html entities to GET and slashes if magic_quotes_gpc is Off.
	 *
	 * What it does:
	 * - cleans the request variables (ENV, GET, POST, COOKIE, SERVER) and
	 * - makes sure the query string was parsed correctly.
	 * - handles the URLs passed by the queryless URLs option.
	 * - makes sure, regardless of php.ini, everything has slashes.
	 * - sets up Board::$board_id, Topic::$topic_id, and $_REQUEST['start'].
	 * - determines, or rather tries to determine, the client's IP.
	 */
	public static function cleanRequest(): void
	{
		// Save some memory.. (since we don't use these anyway.)
		unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_FILES'], $GLOBALS['HTTP_POST_FILES']);

		// These keys shouldn't be set...ever.
		if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS'])) {
			die('Invalid request variable.');
		}

		// Same goes for numeric keys.
		foreach (array_merge(array_keys($_POST), array_keys($_GET), array_keys($_FILES)) as $key) {
			if (is_numeric($key)) {
				die('Numeric request keys are invalid.');
			}
		}

		// Numeric keys in cookies are less of a problem. Just unset those.
		foreach ($_COOKIE as $key => $value) {
			if (is_numeric($key)) {
				unset($_COOKIE[$key]);
			}
		}

		// Get the correct query string.  It may be in an environment variable...
		if (!isset($_SERVER['QUERY_STRING'])) {
			$_SERVER['QUERY_STRING'] = getenv('QUERY_STRING');
		}

		// It seems that sticking a URL after the query string is mighty common, well, it's evil - don't.
		if (strpos($_SERVER['QUERY_STRING'], 'http') === 0) {
			Utils::sendHttpStatus(400);

			die;
		}

		// Are we going to need to parse the ; out?
		if (strpos(ini_get('arg_separator.input'), ';') === false && !empty($_SERVER['QUERY_STRING'])) {
			// Get rid of the old one! You don't know where it's been!
			$_GET = [];

			// Was this redirected? If so, get the REDIRECT_QUERY_STRING.
			// Do not urldecode() the querystring.
			$_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], 0, 5) === 'url=/' ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING'];

			// Replace ';' with '&' and '&something&' with '&something=&'.  (this is done for compatibility...)
			// @todo smflib
			parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr($_SERVER['QUERY_STRING'], [';?' => '&', ';' => '&', '%00' => '', "\0" => ''])), $_GET);
		} elseif (strpos(ini_get('arg_separator.input'), ';') !== false) {
			// Search engines will send action=profile%3Bu=1, which confuses PHP.
			foreach ($_GET as $k => $v) {
				if ((string) $v === $v && strpos($k, ';') !== false) {
					$temp = explode(';', $v);
					$_GET[$k] = $temp[0];

					for ($i = 1, $n = count($temp); $i < $n; $i++) {
						@list($key, $val) = @explode('=', $temp[$i], 2);

						if (!isset($_GET[$key])) {
							$_GET[$key] = $val;
						}
					}
				}

				// This helps a lot with integration!
				if (strpos($k, '?') === 0) {
					$_GET[substr($k, 1)] = $v;
					unset($_GET[$k]);
				}
			}
		}

		// There's no query string, but there is a URL... try to get the data from there.
		if (!empty($_SERVER['REQUEST_URI'])) {
			// Remove the .html, assuming there is one.
			if (substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '.'), 4) == '.htm') {
				$request = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '.'));
			} else {
				$request = $_SERVER['REQUEST_URI'];
			}

			// @todo smflib.
			// Replace 'index.php/a,b,c/d/e,f' with 'a=b,c&d=&e=f' and parse it into $_GET.
			if (strpos($request, basename(Config::$scripturl) . '/') !== false) {
				parse_str(substr(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', substr($request, strpos($request, basename(Config::$scripturl)) + strlen(basename(Config::$scripturl)))), '/', '&')), 1), $temp);

				$_GET += $temp;
			}
		}

		// Add entities to GET.  This is kinda like the slashes on everything else.
		$_GET = Utils::htmlspecialcharsRecursive($_GET);

		// Let's not depend on the ini settings... why even have COOKIE in there, anyway?
		$_REQUEST = $_POST + $_GET;

		// Make sure Board::$board_id and Topic::$topic_id are numbers.
		if (isset($_REQUEST['board'])) {
			// Make sure it's a string and not something else like an array
			$_REQUEST['board'] = (string) $_REQUEST['board'];

			// If there's a slash in it, we've got a start value! (old, compatible links.)
			if (strpos($_REQUEST['board'], '/') !== false) {
				list($_REQUEST['board'], $_REQUEST['start']) = explode('/', $_REQUEST['board']);
			}
			// Same idea, but dots.  This is the currently used format - ?board=1.0...
			elseif (strpos($_REQUEST['board'], '.') !== false) {
				list($_REQUEST['board'], $_REQUEST['start']) = explode('.', $_REQUEST['board']);
			}

			// Now make absolutely sure it's a number.
			Board::$board_id = (int) $_REQUEST['board'];
			$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

			// This is for "Who's Online" because it might come via POST - and it should be an int here.
			$_GET['board'] = Board::$board_id;
		}
		// Well, Board::$board_id is going to be a number no matter what.
		else {
			Board::$board_id = 0;
		}

		// If there's a threadid, it's probably an old YaBB SE link.  Flow with it.
		if (isset($_REQUEST['threadid']) && !isset($_REQUEST['topic'])) {
			$_REQUEST['topic'] = $_REQUEST['threadid'];
		}

		// We've got topic!
		if (isset($_REQUEST['topic'])) {
			// Make sure it's a string and not something else like an array
			$_REQUEST['topic'] = (string) $_REQUEST['topic'];

			// Slash means old, beta style, formatting.  That's okay though, the link should still work.
			if (strpos($_REQUEST['topic'], '/') !== false) {
				list($_REQUEST['topic'], $_REQUEST['start']) = explode('/', $_REQUEST['topic']);
			}
			// Dots are useful and fun ;).  This is ?topic=1.15.
			elseif (strpos($_REQUEST['topic'], '.') !== false) {
				list($_REQUEST['topic'], $_REQUEST['start']) = explode('.', $_REQUEST['topic']);
			}

			// Topic should always be an integer
			Topic::$topic_id = $_GET['topic'] = $_REQUEST['topic'] = (int) $_REQUEST['topic'];

			// Start could be a lot of things...
			// ... empty ...
			if (empty($_REQUEST['start'])) {
				$_REQUEST['start'] = 0;
			}
			// ... a simple number ...
			elseif (is_numeric($_REQUEST['start'])) {
				$_REQUEST['start'] = (int) $_REQUEST['start'];
			}
			// ... or a specific message ...
			elseif (strpos($_REQUEST['start'], 'msg') === 0) {
				$virtual_msg = (int) substr($_REQUEST['start'], 3);
				$_REQUEST['start'] = $virtual_msg === 0 ? 0 : 'msg' . $virtual_msg;
			}
			// ... or whatever is new ...
			elseif (strpos($_REQUEST['start'], 'new') === 0) {
				$_REQUEST['start'] = 'new';
			}
			// ... or since a certain time ...
			elseif (strpos($_REQUEST['start'], 'from') === 0) {
				$timestamp = (int) substr($_REQUEST['start'], 4);
				$_REQUEST['start'] = $timestamp === 0 ? 0 : 'from' . $timestamp;
			}
			// ... or something invalid, in which case we reset it to 0.
			else {
				$_REQUEST['start'] = 0;
			}
		} else {
			Topic::$topic_id = 0;
		}

		// There should be a $_REQUEST['start'], some at least.
		// If you need to default to other than 0, use $_GET['start'].
		if (empty($_REQUEST['start']) || $_REQUEST['start'] < 0 || (int) $_REQUEST['start'] > 2147473647) {
			$_REQUEST['start'] = 0;
		}

		// The action needs to be a string and not an array or anything else
		if (isset($_REQUEST['action'])) {
			$_REQUEST['action'] = (string) $_REQUEST['action'];
		}

		if (isset($_GET['action'])) {
			$_GET['action'] = (string) $_GET['action'];
		}

		// Some mail providers like to encode semicolons in activation URLs...
		if (!empty($_REQUEST['action']) && substr($_SERVER['QUERY_STRING'], 0, 18) == 'action=activate%3b') {
			header('location: ' . Config::$scripturl . '?' . str_replace('%3b', ';', $_SERVER['QUERY_STRING']));

			exit;
		}

		// Make sure we have a valid REMOTE_ADDR.
		if (!isset($_SERVER['REMOTE_ADDR'])) {
			$_SERVER['REMOTE_ADDR'] = '';

			// A new magic variable to indicate we think this is command line.
			$_SERVER['is_cli'] = true;
		}
		// Perhaps we have a IPv6 address.
		elseif (IP::create($_SERVER['REMOTE_ADDR'])->isValid()) {
			$_SERVER['REMOTE_ADDR'] = preg_replace('~^::ffff:(\d+\.\d+\.\d+\.\d+)~', '$1', $_SERVER['REMOTE_ADDR']);
		}

		// Try to calculate their most likely IP for those people behind proxies (And the like).
		$_SERVER['BAN_CHECK_IP'] = $_SERVER['REMOTE_ADDR'];

		// If we haven't specified how to handle Reverse Proxy IP headers, lets do what we always used to do.
		if (!isset(Config::$modSettings['proxy_ip_header'])) {
			Config::$modSettings['proxy_ip_header'] = 'autodetect';
		}

		// Which headers are we going to check for Reverse Proxy IP headers?
		if (Config::$modSettings['proxy_ip_header'] == 'disabled') {
			$reverseIPheaders = [];
		} elseif (Config::$modSettings['proxy_ip_header'] == 'autodetect') {
			$reverseIPheaders = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'];
		} else {
			$reverseIPheaders = [Config::$modSettings['proxy_ip_header']];
		}

		// Find the user's IP address. (but don't let it give you 'unknown'!)
		foreach ($reverseIPheaders as $proxyIPheader) {
			// Ignore if this is not set.
			if (!isset($_SERVER[$proxyIPheader])) {
				continue;
			}

			if (!empty(Config::$modSettings['proxy_ip_servers'])) {
				$valid_sender = false;

				foreach (explode(',', Config::$modSettings['proxy_ip_servers']) as $proxy) {
					if ($proxy == $_SERVER['REMOTE_ADDR'] || self::matchIPtoCIDR($_SERVER['REMOTE_ADDR'], $proxy)) {
						$valid_sender = true;
						break;
					}
				}

				if (!$valid_sender) {
					continue;
				}
			}

			// If there are commas, get the last one.. probably.
			if (strpos($_SERVER[$proxyIPheader], ',') !== false) {
				$ips = array_reverse(explode(', ', $_SERVER[$proxyIPheader]));

				// Go through each IP...
				foreach ($ips as $i => $ip) {
					// Make sure it's in a valid range...
					if (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $ip) != 0 && preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER['REMOTE_ADDR']) == 0) {
						if (!IP::create($_SERVER[$proxyIPheader])->isValid(FILTER_FLAG_IPV6) || preg_match('~::ffff:\d+\.\d+\.\d+\.\d+~', $_SERVER[$proxyIPheader]) !== 0) {
							$_SERVER[$proxyIPheader] = preg_replace('~^::ffff:(\d+\.\d+\.\d+\.\d+)~', '$1', $_SERVER[$proxyIPheader]);

							// Just incase we have a legacy IPv4 address.
							// @ TODO: Convert to IPv6.
							if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER[$proxyIPheader]) === 0) {
								continue;
							}
						}

						continue;
					}

					// Otherwise, we've got an IP!
					$_SERVER['BAN_CHECK_IP'] = trim($ip);

					break;
				}
			}
			// Otherwise just use the only one.
			elseif (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER[$proxyIPheader]) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown|::1|fe80::|fc00::)~', $_SERVER['REMOTE_ADDR']) != 0) {
				$_SERVER['BAN_CHECK_IP'] = $_SERVER[$proxyIPheader];
			} elseif (!IP::create($_SERVER[$proxyIPheader])->isValid(FILTER_FLAG_IPV6) || preg_match('~::ffff:\d+\.\d+\.\d+\.\d+~', $_SERVER[$proxyIPheader]) !== 0) {
				$_SERVER[$proxyIPheader] = preg_replace('~^::ffff:(\d+\.\d+\.\d+\.\d+)~', '$1', $_SERVER[$proxyIPheader]);

				// Just incase we have a legacy IPv4 address.
				// @ TODO: Convert to IPv6.
				if (preg_match('~^(((1?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER[$proxyIPheader]) === 0) {
					continue;
				}
			}
		}

		// Make sure we know the URL of the current request.
		if (empty($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URL'] = Config::$scripturl . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
		} elseif (preg_match('~^([^/]+//[^/]+)~', Config::$scripturl, $match) == 1) {
			$_SERVER['REQUEST_URL'] = $match[1] . $_SERVER['REQUEST_URI'];
		} else {
			$_SERVER['REQUEST_URL'] = $_SERVER['REQUEST_URI'];
		}

		// And make sure HTTP_USER_AGENT is set.
		$_SERVER['HTTP_USER_AGENT'] = isset($_SERVER['HTTP_USER_AGENT']) ? Utils::htmlspecialchars(Db::$db->unescape_string($_SERVER['HTTP_USER_AGENT']), ENT_QUOTES) : '';

		// Some final checking.
		if (!IP::create($_SERVER['BAN_CHECK_IP'])->isValid()) {
			$_SERVER['BAN_CHECK_IP'] = '';
		}

		if ($_SERVER['REMOTE_ADDR'] == 'unknown') {
			$_SERVER['REMOTE_ADDR'] = '';
		}
	}

	/**
	 * Checks whether a $_REQUEST variable contains an expected value.
	 *
	 * The second paramenter, $var, gives the name of the $_REQUEST variable
	 * to check. For example, if $var == 'action', then $_REQUEST['action']
	 * will be tested.
	 *
	 * The first parameter, $value_list, is an associative array whose keys
	 * denote accepted values in $_REQUEST[$var], and whose values can be:
	 *
	 * - Null, in which case the existence of $_REQUEST[$var] causes the test
	 *   to fail.
	 *
	 * - A non-null scalar value, in which case the existence of $_REQUEST[$var]
	 *   is all that is necessary to pass the test.
	 *
	 * - Another associative array indicating additional $_REQUEST variables
	 *   and acceptable values that must also be present.
	 *
	 * For example, if $var == 'action' and $value_list contains this:
	 *
	 *       'logout' => true,
	 *       'pm' => array('sa' => array('popup')),
	 *
	 * ... then the test will pass (a) if $_REQUEST['action'] == 'logout'
	 * or (b) if $_REQUEST['action'] == 'pm' and $_REQUEST['sa'] == 'popup'.
	 *
	 * @param array $value_list A list of acceptable values.
	 * @param string $var Name of a $_REQUEST variable.
	 * @return bool Whether any of the criteria were satisfied.
	 */
	public static function isFilteredRequest(array $value_list, string $var): bool
	{
		$matched = false;

		if (isset($_REQUEST[$var], $value_list[$_REQUEST[$var]])) {
			if (is_array($value_list[$_REQUEST[$var]])) {
				foreach ($value_list[$_REQUEST[$var]] as $subvar => $subvalues) {
					$matched |= isset($_REQUEST[$subvar]) && in_array($_REQUEST[$subvar], $subvalues);
				}
			} else {
				$matched = true;
			}
		}

		return (bool) $matched;
	}

	/**
	 * Rewrite URLs to include the session ID.
	 *
	 * What it does:
	 * - rewrites the URLs outputted to have the session ID, if the user
	 *   is not accepting cookies and is using a standard web browser.
	 * - handles rewriting URLs for the queryless URLs option.
	 * - can be turned off entirely by setting Config::$scripturl to an empty
	 *   string, ''. (it wouldn't work well like that anyway.)
	 * - because of bugs in certain builds of PHP, does not function in
	 *   versions lower than 4.3.0 - please upgrade if this hurts you.
	 *
	 * @param string $buffer The unmodified output buffer.
	 * @return string The modified buffer.
	 */
	public static function ob_sessrewrite(string $buffer): string
	{
		// If Config::$scripturl is set to nothing, or the SID is not defined (SSI?) just quit.
		if (Config::$scripturl == '' || !defined('SID')) {
			return $buffer;
		}

		// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
		if (empty($_COOKIE) && SID != '' && !BrowserDetector::isBrowser('possibly_robot')) {
			$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote(Config::$scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\??/', '"' . Config::$scripturl . '?' . SID . '&amp;', $buffer);
		}
		// Debugging templates, are we?
		elseif (isset($_GET['debug'])) {
			$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote(Config::$scripturl, '/') . '\??/', '"' . Config::$scripturl . '?debug;', $buffer);
		}

		// More work needed if using "queryless" URLS.
		if (
			!empty(Config::$modSettings['queryless_urls'])
			&& (
				!Utils::$context['server']['is_cgi']
				|| ini_get('cgi.fix_pathinfo') == 1
				|| @get_cfg_var('cgi.fix_pathinfo') == 1
			)
			&& (
				Utils::$context['server']['is_apache']
				|| Utils::$context['server']['is_lighttpd']
				|| Utils::$context['server']['is_litespeed']
			)
		) {
			// Let's do something special for session ids!
			if (defined('SID') && SID != '') {
				$buffer = preg_replace_callback(
					'~"' . preg_quote(Config::$scripturl, '~') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#"]+?)(#[^"]*?)?"~',
					function ($m) {
						return '"' . Config::$scripturl . '/' . strtr("{$m[1]}", '&;=', '//,') . '.html?' . SID . ($m[2] ?? '') . '"';
					},
					$buffer,
				);
			} else {
				$buffer = preg_replace_callback(
					'~"' . preg_quote(Config::$scripturl, '~') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?"~',
					function ($m) {
						return '"' . Config::$scripturl . '/' . strtr("{$m[1]}", '&;=', '//,') . '.html' . ($m[2] ?? '') . '"';
					},
					$buffer,
				);
			}
		}

		// Return the changed buffer.
		return $buffer;
	}

	/**
	 * Detect if a IP is in a CIDR address.
	 *
	 * @param string $ip_address IP address to check.
	 * @param string $cidr_address CIDR address to verify.
	 * @return bool Whether the IP matches the CIDR.
	 */
	public function matchIPtoCIDR(string $ip_address, string $cidr_address): bool
	{
		list($cidr_network, $cidr_subnetmask) = preg_split('/', $cidr_address);

		// v6?
		if ((strpos($cidr_network, ':') !== false)) {
			if (!filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) || !filter_var($cidr_network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				return false;
			}

			$ip_address = inet_pton($ip_address);
			$cidr_network = inet_pton($cidr_network);
			$binMask = str_repeat('f', $cidr_subnetmask / 4);

			switch ($cidr_subnetmask % 4) {
				case 0:
					break;

				case 1:
					$binMask .= '8';
					break;

				case 2:
					$binMask .= 'c';
					break;

				case 3:
					$binMask .= 'e';
					break;
			}
			$binMask = str_pad($binMask, 32, '0');
			$binMask = pack('H*', $binMask);

			return ($ip_address & $binMask) == $cidr_network;
		}

		return (ip2long($ip_address) & (~((1 << (32 - $cidr_subnetmask)) - 1))) == ip2long($cidr_network);
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\QueryString::exportStatic')) {
	QueryString::exportStatic();
}

?>