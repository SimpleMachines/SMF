<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF;

use SMF\Db\DatabaseApi as Db;

/**
 * Handles the query string, request variables, and session management.
 */
class QueryString
{
	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Maps elements that could appear at the start of a virtual route path to
	 * the names of classes that can fully parse the route.
	 *
	 * Classes listed in this array must implement the Routable interface.
	 *
	 * MOD AUTHORS: To add a new route parser to this list for a custom action
	 * or content type, use the integrate_parse_route hook in self::parseRoute().
	 */
	public static array $route_parsers = [
		'about:unknown'			=> Actions\Like::class,
		'acceptagreement'		=> Actions\AgreementAccept::class,
		'activate'				=> Actions\Activate::class,
		'admin'					=> Actions\Admin\Main::class,
		'agreement'				=> Actions\Agreement::class,
		'announce'				=> Actions\Announce::class,
		'attachapprove'			=> Actions\AttachmentApprove::class,
		'board'					=> Board::class,
		'boardindex'			=> Actions\BoardIndex::class,
		'boards'				=> Board::class,
		'buddy'					=> Actions\BuddyListToggle::class,
		'calendar'				=> Actions\Calendar::class,
		'clock'					=> Actions\Calendar::class,
		'coppa'					=> Actions\CoppaForm::class,
		'credits'				=> Actions\Credits::class,
		'deletemsg'				=> Actions\MsgDelete::class,
		'display'				=> Actions\Display::class,
		'dlattach'				=> Actions\AttachmentDownload::class,
		'editpoll'				=> Actions\PollEdit::class,
		'editpoll2'				=> Actions\PollEdit2::class,
		'feed'					=> Actions\Feed::class,
		'groups'				=> Actions\Groups::class,
		'help'					=> Actions\Help::class,
		'helpadmin'				=> Actions\HelpAdmin::class,
		'jsmodify'				=> Actions\JavaScriptModify::class,
		'jsoption'				=> Actions\ThemeSetOption::class,
		'likes'					=> Actions\Like::class,
		'lock'					=> Actions\TopicLock::class,
		'lockvoting'			=> Actions\PollLock::class,
		'login'					=> Actions\Login::class,
		'login2'				=> Actions\Login2::class,
		'logintfa'				=> Actions\LoginTFA::class,
		'logout'				=> Actions\Logout::class,
		'markasread'			=> Actions\MarkRead::class,
		'mergetopics'			=> Actions\TopicMerge::class,
		'messageindex'			=> Actions\MessageIndex::class,
		'mlist'					=> Actions\Memberlist::class,
		'members'				=> Actions\Profile\Main::class,
		'member'				=> Actions\Profile\Main::class,
		'moderate'				=> Actions\Moderation\Main::class,
		'modifycat'				=> Actions\Admin\Main::class,
		'movetopic'				=> Actions\TopicMove::class,
		'movetopic2'			=> Actions\TopicMove2::class,
		'msg'					=> Msg::class,
		'msgs'					=> Msg::class,
		'notifyannouncements'	=> Actions\NotifyAnnouncements::class,
		'notifyboard'			=> Actions\NotifyBoard::class,
		'notifytopic'			=> Actions\NotifyTopic::class,
		'pm'					=> Actions\PersonalMessage::class,
		'post'					=> Actions\Post::class,
		'post2'					=> Actions\Post2::class,
		'printpage'				=> Actions\TopicPrint::class,
		'profile'				=> Actions\Profile\Main::class,
		'quickmod'				=> Actions\QuickModeration::class,
		'quickmod2'				=> Actions\QuickModerationInTopic::class,
		'quotefast'				=> Actions\QuoteFast::class,
		'recent'				=> Actions\Recent::class,
		'reminder'				=> Actions\Reminder::class,
		'removepoll'			=> Actions\PollRemove::class,
		'removetopic2'			=> Actions\TopicRemove::class,
		'reporttm'				=> Actions\ReportToMod::class,
		'restoretopic'			=> Actions\TopicRestore::class,
		'search'				=> Actions\Search::class,
		'search2'				=> Actions\Search2::class,
		'sendactivation'		=> Actions\SendActivation::class,
		'signup'				=> Actions\Register::class,
		'signup2'				=> Actions\Register2::class,
		'smstats'				=> Actions\SmStats::class,
		'splittopics'			=> Actions\TopicSplit::class,
		'stats'					=> Actions\Stats::class,
		'sticky'				=> Actions\TopicSticky::class,
		'suggest'				=> Actions\AutoSuggest::class,
		'termsofservice'		=> Actions\Agreement::class,
		'themechooser'			=> Actions\ThemeChooser::class,
		'topic'					=> Topic::class,
		'topics'				=> Topic::class,
		'trackip'				=> Actions\TrackIP::class,
		'unread'				=> Actions\Unread::class,
		'unreadreplies'			=> Actions\UnreadReplies::class,
		'uploadAttach'			=> Actions\AttachmentUpload::class,
		'users'					=> Actions\Profile\Main::class,
		'user'					=> Actions\Profile\Main::class,
		'verificationcode'		=> Actions\VerificationCode::class,
		'viewprofile'			=> Actions\Profile\Main::class,
		'viewquery'				=> Actions\ViewQuery::class,
		'viewsmfile'			=> Actions\DisplayAdminFile::class,
		'vote'					=> Actions\PollVote::class,
		'who'					=> Actions\Who::class,
		'xmlhttp'				=> Actions\XmlHttp::class,
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
		$_SERVER['QUERY_STRING'] = (string) ($_SERVER['QUERY_STRING'] ?? getenv('QUERY_STRING'));

		// It seems that sticking a URL after the query string is mighty common, well, it's evil - don't.
		if (str_starts_with($_SERVER['QUERY_STRING'], 'http')) {
			Utils::sendHttpStatus(400);

			die;
		}

		// Are we going to need to parse the ; out?
		if (!str_contains(ini_get('arg_separator.input'), ';') && !empty($_SERVER['QUERY_STRING'])) {
			// Get rid of the old one! You don't know where it's been!
			$_GET = [];

			// Was this redirected? If so, get the REDIRECT_QUERY_STRING.
			// Do not urldecode() the querystring.
			$_SERVER['QUERY_STRING'] = str_starts_with($_SERVER['QUERY_STRING'], 'url=/') ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING'];

			// Replace ';' with '&' and '&something&' with '&something=&'.  (this is done for compatibility...)
			parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr($_SERVER['QUERY_STRING'], [';?' => '&', ';' => '&', '%00' => '', "\0" => ''])), $_GET);
		} elseif (str_contains(ini_get('arg_separator.input'), ';')) {
			// Search engines will send action=profile%3Bu=1, which confuses PHP.
			foreach ($_GET as $k => $v) {
				if ((string) $v === $v && str_contains($k, ';')) {
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
				if (str_starts_with($k, '?')) {
					$_GET[substr($k, 1)] = $v;
					unset($_GET[$k]);
				}
			}
		}

		// Are we using routing (a.k.a. queryless/friendly/pretty URLs)?
		$_GET = self::parseRoute($_SERVER['PATH_INFO'] ?? '', $_GET);

		// Add entities to GET.  This is kinda like the slashes on everything else.
		$_GET = Utils::htmlspecialcharsRecursive($_GET);

		// Let's not depend on the ini settings... why even have COOKIE in there, anyway?
		$_REQUEST = $_POST + $_GET;

		// Have they by chance specified a message ID but nothing else?
		self::redirectFromMsg();

		// Make sure Board::$board_id and Topic::$topic_id are numbers.
		if (isset($_REQUEST['board'])) {
			// Make sure it's a string and not something else like an array
			$_REQUEST['board'] = (string) $_REQUEST['board'];

			// If there's a slash in it, we've got a start value! (old, compatible links.)
			if (str_contains($_REQUEST['board'], '/')) {
				list($_REQUEST['board'], $_REQUEST['start']) = explode('/', $_REQUEST['board']);
			}
			// Same idea, but dots.  This is the currently used format - ?board=1.0...
			elseif (str_contains($_REQUEST['board'], '.')) {
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
			if (str_contains($_REQUEST['topic'], '/')) {
				list($_REQUEST['topic'], $_REQUEST['start']) = explode('/', $_REQUEST['topic']);
			}
			// Dots are useful and fun ;).  This is ?topic=1.15.
			elseif (str_contains($_REQUEST['topic'], '.')) {
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
			elseif (str_starts_with($_REQUEST['start'], 'msg')) {
				$virtual_msg = (int) substr($_REQUEST['start'], 3);
				$_REQUEST['start'] = $virtual_msg === 0 ? 0 : 'msg' . $virtual_msg;
			}
			// ... or whatever is new ...
			elseif (str_starts_with($_REQUEST['start'], 'new')) {
				$_REQUEST['start'] = 'new';
			}
			// ... or since a certain time ...
			elseif (str_starts_with($_REQUEST['start'], 'from')) {
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
		if (!empty($_REQUEST['action']) && str_starts_with(strtolower($_SERVER['QUERY_STRING']), 'action=activate%3b')) {
			header('location: ' . Config::$scripturl . '?' . str_ireplace('%3b', ';', $_SERVER['QUERY_STRING']));

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
					if (
						$proxy == $_SERVER['REMOTE_ADDR']
						|| (new IP($_SERVER['REMOTE_ADDR']))->matchToCIDR($proxy)
					) {
						$valid_sender = true;
						break;
					}
				}

				if (!$valid_sender) {
					continue;
				}
			}

			// If there are commas, get the last one.. probably.
			if (str_contains($_SERVER[$proxyIPheader], ',')) {
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
	 * The second parameter, $var, gives the name of the $_REQUEST variable
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
	 * Rewrites URLs for the queryless URLs option.
	 *
	 * MOD AUTHORS: If your mod implements an alternative form of pretty URLs,
	 * the 'integrate_rewrite_as_queryless' hook inside this method will be of
	 * interest to you.
	 *
	 * @param string $buffer A string that might contain URLs.
	 * @return string Modified version of $buffer.
	 */
	public static function rewriteAsQueryless(string $buffer): string
	{
		// Give mods a chance to rewrite the buffer before we do anything to it.
		IntegrationHook::call('integrate_rewrite_as_queryless', [&$buffer]);

		// If Config::$scripturl doesn't appear anywhere, there's nothing to do.
		if (!str_contains($buffer, Config::$scripturl)) {
			return $buffer;
		}

		// Do we want full queryless URLs?
		if (
			!empty(Config::$modSettings['queryless_urls'])
			&& (
				!Sapi::isCGI()
				|| ini_get('cgi.fix_pathinfo') == 1
				|| @get_cfg_var('cgi.fix_pathinfo') == 1
			)
			&& Sapi::isSoftware([Sapi::SERVER_APACHE, Sapi::SERVER_LIGHTTPD, Sapi::SERVER_LITESPEED])
		) {
			$buffer = preg_replace_callback(
				'~' . Autolinker::load()->getUrlRegex() . '~u',
				function (array $matches) {
					if (
						// Don't change external URLs.
						!str_starts_with($matches[0], Config::$scripturl)
						// Never change ?action=admin, just in case something
						// goes wrong and the admin needs to be able to navigate
						// to the admin control panel to fix it.
						|| str_contains($matches[0], 'action=admin')
					) {
						return $matches[0];
					}

					$url = new Url($matches[0]);

					// Convert query to route.
					if (!empty($url->query)) {
						$matches[0] = str_replace('?' . $url->query, QueryString::buildRoute($url->query), $matches[0]);
					}

					// Remove '/index.php'.
					if (!empty(Config::$modSettings['hide_index_php'])) {
						$matches[0] = str_replace(Config::$scripturl, Config::$boardurl, $matches[0]);
					}

					return str_replace('/#', '#', $matches[0]);
				},
				$buffer,
			);
		}
		// Not doing queryless URLs, but admin still wants to hide index.php.
		elseif (!empty(Config::$modSettings['hide_index_php'])) {
			$buffer = preg_replace_callback(
				'~' . Autolinker::load()->getUrlRegex() . '~u',
				function (array $matches) {
					// Don't change external URLs.
					if (!str_starts_with($matches[0], Config::$scripturl)) {
						return $matches[0];
					}

					return str_replace(
						[
							Config::$scripturl,
							'/#',
						],
						[
							Config::$boardurl . '/',
							'#',
						],
						$matches[0],
					);
				},
				$buffer,
			);
		}

		return $buffer;
	}

	/**
	 * Builds a routing path based on URL query parameters.
	 *
	 * @param array|string $params URL query, as a string or array of parameters.
	 * @return string A routing path plus any remaining URL query string.
	 */
	public static function buildRoute(array|string $params): string
	{
		if (is_string($params)) {
			$params = strtr(ltrim($params, '?'), ';', '&');
			parse_str($params, $temp);

			$params = $temp;
		}

		$route = [];

		if (isset($params['action'])) {
			$route_base = $params['action'];
		} elseif (isset($params['board'])) {
			$route_base = 'boards';
		} elseif (isset($params['topic'])) {
			$route_base = 'topics';
		} elseif (isset($params['msg'])) {
			$route_base = 'msgs';
		}

		if (
			isset($route_base, self::$route_parsers[$route_base])
			&& method_exists(self::$route_parsers[$route_base], 'buildRoute')
		) {
			extract(call_user_func(self::$route_parsers[$route_base] . '::buildRoute', $params));
		}

		$route = !empty($route) ? '/' . implode('/', $route) : '';

		$query = [];

		foreach ($params as $key => $value) {
			$query[] = $key . ((string) $value !== '' ? '=' . $value : '');
		}

		$query = !empty($query) ? '?' . implode(';', $query) : '';

		return $route . (!empty($query) ? '/' . $query : '');
	}

	/**
	 * Updates an array of URL parameters based on a routing path.
	 *
	 * MOD AUTHORS: If your mod implements an alternative form of pretty URLs,
	 * or if you just want to add something to the list of known route parsers,
	 * the 'integrate_parse_route' hook inside this method will be of interest
	 * to you.
	 *
	 * @param string $path A virtual path. Typically $_SERVER['PATH_INFO'].
	 * @param array $params Existing URL query parameters. Typically $_GET.
	 * @return array Updated copy of $params.
	 */
	public static function parseRoute(string $path, array $params): array
	{
		// Give mods a chance to parse the route before we do anything to it.
		// This hook can also be used add new route parsers to self::$route_parsers.
		IntegrationHook::call('integrate_parse_route', [&$path, &$params]);

		if (!str_starts_with($path, '/')) {
			return $params;
		}

		// The pre-3.0 form of queryless URLs appended a fake file extension.
		if (str_ends_with($path, '.html') || str_ends_with($path, '.htm')) {
			$path = substr($path, 0, strrpos($path, '.'));
		}

		$new_params = [];

		$route = explode('/', trim($path, '/'));

		if (isset(self::$route_parsers[$route[0]])) {
			$new_params = call_user_func(self::$route_parsers[$route[0]] . '::parseRoute', $route);
		} else {
			// Maintain support for the pre-3.0 form of queryless URLs.
			parse_str(substr(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', $path), '/', '&')), 1), $new_params);
		}

		// Existing values in $params always takes precedence over routing.
		// This is because $params is typically a copy of $_GET, and we want
		// the real $_GET parameters to take precedence.
		foreach ($params as $key => $value) {
			$new_params[$key] = $value;
		}

		return $new_params;
	}

	/**
	 * Rewrite URLs to include the session ID, if the user is not accepting
	 * cookies and is using a standard web browser.
	 *
	 * @param string $buffer The unmodified output buffer.
	 * @return string The modified buffer.
	 */
	public static function ob_sessrewrite(string $buffer): string
	{
		// PHP 8.4 deprecated SID. A better long-term solution is needed, but this works for now.
		$sid = defined('SID') ? @constant('SID') : null;

		// If Config::$scripturl is set to nothing, or the SID is not defined (SSI?) just quit.
		if (Config::$scripturl == '' || !isset($sid)) {
			return $buffer;
		}

		// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
		if (empty($_COOKIE) && $sid != '' && !BrowserDetector::isBrowser('possibly_robot')) {
			$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote(Config::$scripturl, '/') . '(?!\?' . preg_quote($sid, '/') . ')\??/', '"' . Config::$scripturl . '?' . $sid . '&amp;', $buffer);
		}
		// Debugging templates, are we?
		elseif (isset($_GET['debug'])) {
			$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote(Config::$scripturl, '/') . '\??/', '"' . Config::$scripturl . '?debug;', $buffer);
		}

		// Return the changed buffer.
		return $buffer;
	}

	/**
	 * Handles redirecting 'index.php?msg=123' links to the canonical URL.
	 */
	protected static function redirectFromMsg(): void
	{
		if (
			empty($_REQUEST['msg'])
			|| !empty($_REQUEST['action'])
			|| !empty($_REQUEST['topic'])
			|| !empty($_REQUEST['board'])
		) {
			return;
		}

		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (($topic = Cache\CacheApi::get('msg_topic-' . $_REQUEST['msg'], 120)) === null) {
			$request = Db::$db->query(
				'',
				'SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				[
					'id_msg' => $_REQUEST['msg'],
				],
			);

			// So did it find anything?
			if (Db::$db->num_rows($request)) {
				list($topic) = Db::$db->fetch_row($request);
				Db::$db->free_result($request);

				// Save save save.
				Cache\CacheApi::put('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic)) {
			$redirect_url = 'topic=' . $topic . '.msg' . $_REQUEST['msg'];

			if (($other_get_params = array_diff(array_keys($_GET), ['msg'])) !== []) {
				$redirect_url .= ';' . implode(';', $other_get_params);
			}

			$redirect_url .= '#msg' . $_REQUEST['msg'];

			Utils::redirectexit($redirect_url);
		}
	}
}

?>