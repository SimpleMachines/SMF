<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file does a lot of important stuff.  Mainly, this means it handles
	the query string, request variables, and session management.  It contains
	the following functions:

	void cleanRequest()
		- cleans the request variables (ENV, GET, POST, COOKIE, SERVER) and
		  makes sure the query string was parsed correctly.
		- handles the URLs passed by the queryless URLs option.
		- makes sure, regardless of php.ini, everything has slashes.
		- sets up $board, $topic, and $scripturl and $_REQUEST['start'].
		- determines, or rather tries to determine, the client's IP.

	array escapestring__recursive(array var)
		- returns the var, as an array or string, with escapes as required.
		- importantly escapes all keys and values!
		- calls itself recursively if necessary.

	array htmlspecialchars__recursive(array var)
		- adds entities (&quot;, &lt;, &gt;) to the array or string var.
		- importantly, does not effect keys, only values.
		- calls itself recursively if necessary.

	array urldecode__recursive(array var)
		- takes off url encoding (%20, etc.) from the array or string var.
		- importantly, does it to keys too!
		- calls itself recursively if there are any sub arrays.

	array unescapestring__recursive(array var)
		- unescapes, recursively, from the array or string var.
		- effects both keys and values of arrays.
		- calls itself recursively to handle arrays of arrays.

	array stripslashes__recursive(array var)
		- removes slashes, recursively, from the array or string var.
		- effects both keys and values of arrays.
		- calls itself recursively to handle arrays of arrays.

	array htmltrim__recursive(array var)
		- trims a string or an the var array using html characters as well.
		- does not effect keys, only values.
		- may call itself recursively if needed.

	string cleanXml(string var)
		- removes invalid XML characters to assure the input string being
		  parsed properly.

	string ob_sessrewrite(string buffer)
		- rewrites the URLs outputted to have the session ID, if the user
		  is not accepting cookies and is using a standard web browser.
		- handles rewriting URLs for the queryless URLs option.
		- can be turned off entirely by setting $scripturl to an empty
		  string, ''. (it wouldn't work well like that anyway.)
		- because of bugs in certain builds of PHP, does not function in
		  versions lower than 4.3.0 - please upgrade if this hurts you.
*/

// Clean the request variables - add html entities to GET and slashes if magic_quotes_gpc is Off.
function cleanRequest()
{
	global $board, $topic, $boardurl, $scripturl, $modSettings, $smcFunc;

	// Makes it easier to refer to things this way.
	$scripturl = $boardurl . '/index.php';

	// What function to use to reverse magic quotes - if sybase is on we assume that the database sensibly has the right unescape function!
	$removeMagicQuoteFunction = @ini_get('magic_quotes_sybase') || strtolower(@ini_get('magic_quotes_sybase')) == 'on' ? 'unescapestring__recursive' : 'stripslashes__recursive';

	// Save some memory.. (since we don't use these anyway.)
	unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_VARS']);
	unset($GLOBALS['HTTP_POST_FILES'], $GLOBALS['HTTP_POST_FILES']);

	// These keys shouldn't be set...ever.
	if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
		die('Invalid request variable.');

	// Same goes for numeric keys.
	foreach (array_merge(array_keys($_POST), array_keys($_GET), array_keys($_FILES)) as $key)
		if (is_numeric($key))
			die('Numeric request keys are invalid.');

	// Numeric keys in cookies are less of a problem. Just unset those.
	foreach ($_COOKIE as $key => $value)
		if (is_numeric($key))
			unset($_COOKIE[$key]);

	// Get the correct query string.  It may be in an environment variable...
	if (!isset($_SERVER['QUERY_STRING']))
		$_SERVER['QUERY_STRING'] = getenv('QUERY_STRING');

	// It seems that sticking a URL after the query string is mighty common, well, it's evil - don't.
	if (strpos($_SERVER['QUERY_STRING'], 'http') === 0)
	{
		header('HTTP/1.1 400 Bad Request');
		die;
	}

	// Are we going to need to parse the ; out?
	if ((strpos(@ini_get('arg_separator.input'), ';') === false || @version_compare(PHP_VERSION, '4.2.0') == -1) && !empty($_SERVER['QUERY_STRING']))
	{
		// Get rid of the old one! You don't know where it's been!
		$_GET = array();

		// Was this redirected? If so, get the REDIRECT_QUERY_STRING.
		// Do not urldecode() the querystring, unless you so much wish to break OpenID implementation. :)
		$_SERVER['QUERY_STRING'] = substr($_SERVER['QUERY_STRING'], 0, 5) === 'url=/' ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING'];

		// Replace ';' with '&' and '&something&' with '&something=&'.  (this is done for compatibility...)
		// !!! smflib
		parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr($_SERVER['QUERY_STRING'], array(';?' => '&', ';' => '&', '%00' => '', "\0" => ''))), $_GET);

		// Magic quotes still applies with parse_str - so clean it up.
		if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0 && empty($modSettings['integrate_magic_quotes']))
			$_GET = $removeMagicQuoteFunction($_GET);
	}
	elseif (strpos(@ini_get('arg_separator.input'), ';') !== false)
	{
		if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0 && empty($modSettings['integrate_magic_quotes']))
			$_GET = $removeMagicQuoteFunction($_GET);

		// Search engines will send action=profile%3Bu=1, which confuses PHP.
		foreach ($_GET as $k => $v)
		{
			if (is_string($v) && strpos($k, ';') !== false)
			{
				$temp = explode(';', $v);
				$_GET[$k] = $temp[0];

				for ($i = 1, $n = count($temp); $i < $n; $i++)
				{
					@list ($key, $val) = @explode('=', $temp[$i], 2);
					if (!isset($_GET[$key]))
						$_GET[$key] = $val;
				}
			}

			// This helps a lot with integration!
			if (strpos($k, '?') === 0)
			{
				$_GET[substr($k, 1)] = $v;
				unset($_GET[$k]);
			}
		}
	}

	// There's no query string, but there is a URL... try to get the data from there.
	if (!empty($_SERVER['REQUEST_URI']))
	{
		// Remove the .html, assuming there is one.
		if (substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '.'), 4) == '.htm')
			$request = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '.'));
		else
			$request = $_SERVER['REQUEST_URI'];

		// !!! smflib.
		// Replace 'index.php/a,b,c/d/e,f' with 'a=b,c&d=&e=f' and parse it into $_GET.
		if (strpos($request, basename($scripturl) . '/') !== false)
		{
			parse_str(substr(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', substr($request, strpos($request, basename($scripturl)) + strlen(basename($scripturl)))), '/', '&')), 1), $temp);
			if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0 && empty($modSettings['integrate_magic_quotes']))
				$temp = $removeMagicQuoteFunction($temp);
			$_GET += $temp;
		}
	}

	// If magic quotes is on we have some work...
	if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
	{
		$_ENV = $removeMagicQuoteFunction($_ENV);
		$_POST = $removeMagicQuoteFunction($_POST);
		$_COOKIE = $removeMagicQuoteFunction($_COOKIE);
		foreach ($_FILES as $k => $dummy)
			if (isset($_FILES[$k]['name']))
				$_FILES[$k]['name'] = $removeMagicQuoteFunction($_FILES[$k]['name']);
	}

	// Add entities to GET.  This is kinda like the slashes on everything else.
	$_GET = htmlspecialchars__recursive($_GET);

	// Let's not depend on the ini settings... why even have COOKIE in there, anyway?
	$_REQUEST = $_POST + $_GET;

	// Make sure $board and $topic are numbers.
	if (isset($_REQUEST['board']))
	{
		// Make sure its a string and not something else like an array
		$_REQUEST['board'] = (string) $_REQUEST['board'];

		// If there's a slash in it, we've got a start value! (old, compatible links.)
		if (strpos($_REQUEST['board'], '/') !== false)
			list ($_REQUEST['board'], $_REQUEST['start']) = explode('/', $_REQUEST['board']);
		// Same idea, but dots.  This is the currently used format - ?board=1.0...
		elseif (strpos($_REQUEST['board'], '.') !== false)
			list ($_REQUEST['board'], $_REQUEST['start']) = explode('.', $_REQUEST['board']);
		// Now make absolutely sure it's a number.
		$board = (int) $_REQUEST['board'];
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		// This is for "Who's Online" because it might come via POST - and it should be an int here.
		$_GET['board'] = $board;
	}
	// Well, $board is going to be a number no matter what.
	else
		$board = 0;

	// If there's a threadid, it's probably an old YaBB SE link.  Flow with it.
	if (isset($_REQUEST['threadid']) && !isset($_REQUEST['topic']))
		$_REQUEST['topic'] = $_REQUEST['threadid'];

	// We've got topic!
	if (isset($_REQUEST['topic']))
	{
		// Make sure its a string and not something else like an array
		$_REQUEST['topic'] = (string) $_REQUEST['topic'];

		// Slash means old, beta style, formatting.  That's okay though, the link should still work.
		if (strpos($_REQUEST['topic'], '/') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('/', $_REQUEST['topic']);
		// Dots are useful and fun ;).  This is ?topic=1.15.
		elseif (strpos($_REQUEST['topic'], '.') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('.', $_REQUEST['topic']);

		$topic = (int) $_REQUEST['topic'];

		// Now make sure the online log gets the right number.
		$_GET['topic'] = $topic;
	}
	else
		$topic = 0;

	// There should be a $_REQUEST['start'], some at least.  If you need to default to other than 0, use $_GET['start'].
	if (empty($_REQUEST['start']) || $_REQUEST['start'] < 0 || (int) $_REQUEST['start'] > 2147473647)
		$_REQUEST['start'] = 0;

	// The action needs to be a string and not an array or anything else
	if (isset($_REQUEST['action']))
		$_REQUEST['action'] = (string) $_REQUEST['action'];
	if (isset($_GET['action']))
		$_GET['action'] = (string) $_GET['action'];

	// Make sure we have a valid REMOTE_ADDR.
	if (!isset($_SERVER['REMOTE_ADDR']))
	{
		$_SERVER['REMOTE_ADDR'] = '';
		// A new magic variable to indicate we think this is command line.
		$_SERVER['is_cli'] = true;
	}
	elseif (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER['REMOTE_ADDR']) === 0)
		$_SERVER['REMOTE_ADDR'] = 'unknown';

	// Try to calculate their most likely IP for those people behind proxies (And the like).
	$_SERVER['BAN_CHECK_IP'] = $_SERVER['REMOTE_ADDR'];

	// Find the user's IP address. (but don't let it give you 'unknown'!)
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0))
	{
		// We have both forwarded for AND client IP... check the first forwarded for as the block - only switch if it's better that way.
		if (strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.') && '.' . strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') == strrchr($_SERVER['HTTP_CLIENT_IP'], '.') && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0))
			$_SERVER['BAN_CHECK_IP'] = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
		else
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_CLIENT_IP'];
	}
	if (!empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0))
	{
		// Since they are in different blocks, it's probably reversed.
		if (strtok($_SERVER['REMOTE_ADDR'], '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.'))
			$_SERVER['BAN_CHECK_IP'] = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
		else
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_CLIENT_IP'];
	}
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		// If there are commas, get the last one.. probably.
		if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false)
		{
			$ips = array_reverse(explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']));

			// Go through each IP...
			foreach ($ips as $i => $ip)
			{
				// Make sure it's in a valid range...
				if (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $ip) != 0 && preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) == 0)
					continue;

				// Otherwise, we've got an IP!
				$_SERVER['BAN_CHECK_IP'] = trim($ip);
				break;
			}
		}
		// Otherwise just use the only one.
		elseif (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0)
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}

	// Make sure we know the URL of the current request.
	if (empty($_SERVER['REQUEST_URI']))
		$_SERVER['REQUEST_URL'] = $scripturl . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
	elseif (preg_match('~^([^/]+//[^/]+)~', $scripturl, $match) == 1)
		$_SERVER['REQUEST_URL'] = $match[1] . $_SERVER['REQUEST_URI'];
	else
		$_SERVER['REQUEST_URL'] = $_SERVER['REQUEST_URI'];

	// And make sure HTTP_USER_AGENT is set.
	$_SERVER['HTTP_USER_AGENT'] = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($smcFunc['db_unescape_string']($_SERVER['HTTP_USER_AGENT']), ENT_QUOTES) : '';

	// Some final checking.
	if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER['BAN_CHECK_IP']) === 0)
		$_SERVER['BAN_CHECK_IP'] = '';
	if ($_SERVER['REMOTE_ADDR'] == 'unknown')
		$_SERVER['REMOTE_ADDR'] = '';
}

// Adds slashes to the array/variable.  Uses two underscores to guard against overloading.
function escapestring__recursive($var)
{
	global $smcFunc;

	if (!is_array($var))
		return $smcFunc['db_escape_string']($var);

	// Reindex the array with slashes.
	$new_var = array();

	// Add slashes to every element, even the indexes!
	foreach ($var as $k => $v)
		$new_var[$smcFunc['db_escape_string']($k)] = escapestring__recursive($v);

	return $new_var;
}

// Adds html entities to the array/variable.  Uses two underscores to guard against overloading.
function htmlspecialchars__recursive($var, $level = 0)
{
	global $smcFunc;

	if (!is_array($var))
		return isset($smcFunc['htmlspecialchars']) ? $smcFunc['htmlspecialchars']($var, ENT_QUOTES) : htmlspecialchars($var, ENT_QUOTES);

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmlspecialchars__recursive($v, $level + 1);

	return $var;
}

// Removes url stuff from the array/variable.  Uses two underscores to guard against overloading.
function urldecode__recursive($var, $level = 0)
{
	if (!is_array($var))
		return urldecode($var);

	// Reindex the array...
	$new_var = array();

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$new_var[urldecode($k)] = $level > 25 ? null : urldecode__recursive($v, $level + 1);

	return $new_var;
}
// Unescapes any array or variable.  Two underscores for the normal reason.
function unescapestring__recursive($var)
{
	global $smcFunc;

	if (!is_array($var))
		return $smcFunc['db_unescape_string']($var);

	// Reindex the array without slashes, this time.
	$new_var = array();

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[$smcFunc['db_unescape_string']($k)] = unescapestring__recursive($v);

	return $new_var;
}

// Remove slashes recursively...
function stripslashes__recursive($var, $level = 0)
{
	if (!is_array($var))
		return stripslashes($var);

	// Reindex the array without slashes, this time.
	$new_var = array();

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[stripslashes($k)] = $level > 25 ? null : stripslashes__recursive($v, $level + 1);

	return $new_var;
}

// Trim a string including the HTML space, character 160.
function htmltrim__recursive($var, $level = 0)
{
	global $smcFunc;

	// Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
	if (!is_array($var))
		return isset($smcFunc) ? $smcFunc['htmltrim']($var) : trim($var, ' ' . "\t\n\r\x0B" . '\0' . "\xA0");

	// Go through all the elements and remove the whitespace.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmltrim__recursive($v, $level + 1);

	return $var;
}

// Clean up the XML to make sure it doesn't contain invalid characters.
function cleanXml($string)
{
	global $context;

	// http://www.w3.org/TR/2000/REC-xml-20001006#NT-Char
	return preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19' . ($context['utf8'] ? (@version_compare(PHP_VERSION, '4.3.3') != -1 ? '\x{D800}-\x{DFFF}\x{FFFE}\x{FFFF}' : "\xED\xA0\x80-\xED\xBF\xBF\xEF\xBF\xBE\xEF\xBF\xBF") : '') . ']~' . ($context['utf8'] ? 'u' : ''), '', $string);
}

function JavaScriptEscape($string)
{
	global $scripturl;

	return '\'' . strtr($string, array(
		"\r" => '',
		"\n" => '\\n',
		"\t" => '\\t',
		'\\' => '\\\\',
		'\'' => '\\\'',
		'</' => '<\' + \'/',
		'script' => 'scri\'+\'pt',
		'<a href' => '<a hr\'+\'ef',
		$scripturl => $scripturl . '\'+\'',
	)) . '\'';
}

// Rewrite URLs to include the session ID.
function ob_sessrewrite($buffer)
{
	global $scripturl, $modSettings, $user_info, $context;

	// If $scripturl is set to nothing, or the SID is not defined (SSI?) just quit.
	if ($scripturl == '' || !defined('SID'))
		return $buffer;

	// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().  This doesn't work below PHP 4.3.0, because it makes the output buffer bigger.
	// !!! smflib
	if (empty($_COOKIE) && SID != '' && empty($context['browser']['possibly_robot']) && @version_compare(PHP_VERSION, '4.3.0') != -1)
		$buffer = preg_replace('/"' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', '"' . $scripturl . '?' . SID . '&amp;', $buffer);
	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);

	// This should work even in 4.2.x, just not CGI without cgi.fix_pathinfo.
	if (!empty($modSettings['queryless_urls']) && (!$context['server']['is_cgi'] || @ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1) && ($context['server']['is_apache'] || $context['server']['is_lighttpd']))
	{
		// Let's do something special for session ids!
		if (defined('SID') && SID != '')
			$buffer = preg_replace('/"' . preg_quote($scripturl, '/') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#"]+?)(#[^"]*?)?"/e', "'\"' . \$scripturl . '/' . strtr('\$1', '&;=', '//,') . '.html?' . SID . '\$2\"'", $buffer);
		else
			$buffer = preg_replace('/"' . preg_quote($scripturl, '/') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?"/e', "'\"' . \$scripturl . '/' . strtr('\$1', '&;=', '//,') . '.html\$2\"'", $buffer);
	}

	// Return the changed buffer.
	return $buffer;
}

?>