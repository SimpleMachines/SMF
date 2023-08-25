<?php

/**
 * This file has all the main functions in it that relate to, well, everything.
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

use SMF\BrowserDetector;
use SMF\BBCodeParser;
use SMF\Config;
use SMF\ErrorHandler;
use SMF\Forum;
use SMF\Group;
use SMF\Lang;
use SMF\Logging;
use SMF\Mail;
use SMF\Security;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Db\DatabaseApi as Db;
use SMF\Fetchers\CurlFetcher;
use SMF\Unicode\Utf8String;

if (!defined('SMF'))
	die('No direct access...');

class_exists('SMF\\Attachment');
class_exists('SMF\\BBCodeParser');
class_exists('SMF\\Logging');
class_exists('SMF\\Theme');
class_exists('SMF\\Time');
class_exists('SMF\\User');
class_exists('SMF\\Utils');

/**
 * Constructs a page list.
 *
 * - builds the page list, e.g. 1 ... 6 7 [8] 9 10 ... 15.
 * - flexible_start causes it to use "url.page" instead of "url;start=page".
 * - very importantly, cleans up the start value passed, and forces it to
 *   be a multiple of num_per_page.
 * - checks that start is not more than max_value.
 * - base_url should be the URL without any start parameter on it.
 * - uses the compactTopicPagesEnable and compactTopicPagesContiguous
 *   settings to decide how to display the menu.
 *
 * an example is available near the function definition.
 * $pageindex = constructPageIndex(Config::$scripturl . '?board=' . $board, $_REQUEST['start'], $num_messages, $maxindex, true);
 *
 * @param string $base_url The basic URL to be used for each link.
 * @param int &$start The start position, by reference. If this is not a multiple of the number of items per page, it is sanitized to be so and the value will persist upon the function's return.
 * @param int $max_value The total number of items you are paginating for.
 * @param int $num_per_page The number of items to be displayed on a given page. $start will be forced to be a multiple of this value.
 * @param bool $flexible_start Whether a ;start=x component should be introduced into the URL automatically (see above)
 * @param bool $show_prevnext Whether the Previous and Next links should be shown (should be on only when navigating the list)
 *
 * @return string The complete HTML of the page index that was requested, formatted by the template.
 */
function constructPageIndex($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show_prevnext = true)
{
	// Save whether $start was less than 0 or not.
	$start = (int) $start;
	$start_invalid = $start < 0;

	// $start must be within bounds and be a multiple of $num_per_page.
	$start = min(max(0, $start), $max_value);
	$start = $start - ($start % $num_per_page);

	if (!isset(Utils::$context['current_page']))
		Utils::$context['current_page'] = $start / $num_per_page;

	// Define some default page index settings for compatibility with old themes.
	// !!! Should this be moved to Theme::load()?
	if (!isset(Theme::$current->settings['page_index']))
		Theme::$current->settings['page_index'] = array(
			'extra_before' => '<span class="pages">' . Lang::$txt['pages'] . '</span>',
			'previous_page' => '<span class="main_icons previous_page"></span>',
			'current_page' => '<span class="current_page">%1$d</span> ',
			'page' => '<a class="nav_page" href="{URL}">%2$s</a> ',
			'expand_pages' => '<span class="expand_pages" onclick="expandPages(this, {LINK}, {FIRST_PAGE}, {LAST_PAGE}, {PER_PAGE});"> ... </span>',
			'next_page' => '<span class="main_icons next_page"></span>',
			'extra_after' => '',
		);

	$last_page_value = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
	$base_link = strtr(Theme::$current->settings['page_index']['page'], array('{URL}' => $flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d'));
	$pageindex = Theme::$current->settings['page_index']['extra_before'];

	// Show the "prev page" link. (>prev page< 1 ... 6 7 [8] 9 10 ... 15 next page)
	if ($start != 0 && !$start_invalid && $show_prevnext)
		$pageindex .= sprintf($base_link, $start - $num_per_page, Theme::$current->settings['page_index']['previous_page']);

	// Compact pages is off or on?
	if (empty(Config::$modSettings['compactTopicPagesEnable']))
	{
		// Show all the pages.
		$display_page = 1;
		for ($counter = 0; $counter < $max_value; $counter += $num_per_page)
			$pageindex .= $start == $counter && !$start_invalid ? sprintf(Theme::$current->settings['page_index']['current_page'], $display_page++) : sprintf($base_link, $counter, $display_page++);
	}
	else
	{
		// If they didn't enter an odd value, pretend they did.
		$page_contiguous = (int) (Config::$modSettings['compactTopicPagesContiguous'] - (Config::$modSettings['compactTopicPagesContiguous'] % 2)) / 2;

		// Show the first page. (prev page >1< ... 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * $page_contiguous)
			$pageindex .= sprintf($base_link, 0, '1');

		// Show the ... after the first page.  (prev page 1 >...< 6 7 [8] 9 10 ... 15 next page)
		if ($start > $num_per_page * ($page_contiguous + 1))
			$pageindex .= strtr(Theme::$current->settings['page_index']['expand_pages'], array(
				'{LINK}' => JavaScriptEscape(Utils::htmlspecialchars($base_link)),
				'{FIRST_PAGE}' => $num_per_page,
				'{LAST_PAGE}' => $start - $num_per_page * $page_contiguous,
				'{PER_PAGE}' => $num_per_page,
			));

		for ($nCont = -$page_contiguous; $nCont <= $page_contiguous; $nCont++)
		{
			$tmpStart = $start + $num_per_page * $nCont;
			if ($nCont == 0)
			{
				// Show the current page. (prev page 1 ... 6 7 >[8]< 9 10 ... 15 next page)
				if (!$start_invalid)
					$pageindex .= sprintf(Theme::$current->settings['page_index']['current_page'], $start / $num_per_page + 1);
				else
					$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);
			}
			// Show the pages before the current one. (prev page 1 ... >6 7< [8] 9 10 ... 15 next page)
			// ... or ...
			// Show the pages after the current one... (prev page 1 ... 6 7 [8] >9 10< ... 15 next page)
			elseif (($nCont < 0 && $start >= $num_per_page * -$nCont) || ($nCont > 0 && $tmpStart <= $last_page_value))
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
		}

		// Show the '...' part near the end. (prev page 1 ... 6 7 [8] 9 10 >...< 15 next page)
		if ($start + $num_per_page * ($page_contiguous + 1) < $last_page_value)
			$pageindex .= strtr(Theme::$current->settings['page_index']['expand_pages'], array(
				'{LINK}' => JavaScriptEscape(Utils::htmlspecialchars($base_link)),
				'{FIRST_PAGE}' => $start + $num_per_page * ($page_contiguous + 1),
				'{LAST_PAGE}' => $last_page_value,
				'{PER_PAGE}' => $num_per_page,
			));

		// Show the last number in the list. (prev page 1 ... 6 7 [8] 9 10 ... >15<  next page)
		if ($start + $num_per_page * $page_contiguous < $last_page_value)
			$pageindex .= sprintf($base_link, $last_page_value, $last_page_value / $num_per_page + 1);
	}

	// Show the "next page" link. (prev page 1 ... 6 7 [8] 9 10 ... 15 >next page<)
	if ($start != $last_page_value && !$start_invalid && $show_prevnext)
		$pageindex .= sprintf($base_link, $start + $num_per_page, Theme::$current->settings['page_index']['next_page']);

	$pageindex .= Theme::$current->settings['page_index']['extra_after'];

	return $pageindex;
}

/**
 * Format a time to make it look purdy.
 *
 * - returns a pretty formatted version of time based on the user's format in User::$me->time_format.
 * - applies all necessary time offsets to the timestamp, unless offset_type is set.
 * - if todayMod is set and show_today was not not specified or true, an
 *   alternate format string is used to show the date with something to show it is "today" or "yesterday".
 * - performs localization (more than just strftime would do alone.)
 *
 * @param int $log_time A timestamp
 * @param bool|string $show_today Whether to show "Today"/"Yesterday" or just a date.
 *     If a string is specified, that is used to temporarily override the date format.
 * @param null|string $tzid Time zone to use when generating the formatted string.
 *     If empty, the user's time zone will be used.
 *     If set to 'forum', the value of Config::$modSettings['default_timezone'] will be used.
 *     If set to a valid time zone identifier, that will be used.
 *     Otherwise, the value of date_default_timezone_get() will be used.
 * @return string A formatted time string
 */
function timeformat($log_time, $show_today = true, $tzid = null)
{
	static $today;

	// Ensure required values are set
	User::$me->time_format = !empty(User::$me->time_format) ? User::$me->time_format : (!empty(Config::$modSettings['time_format']) ? Config::$modSettings['time_format'] : '%F %H:%M');

	// For backward compatibility, replace empty values with user's time zone
	// and replace 'forum' with forum's default time zone.
	$tzid = empty($tzid) ? User::getTimezone() : (($tzid === 'forum' || @timezone_open((string) $tzid) === false) ? Config::$modSettings['default_timezone'] : (string) $tzid);

	// Today and Yesterday?
	$prefix = '';
	if (Config::$modSettings['todayMod'] >= 1 && $show_today === true)
	{
		if (!isset($today[$tzid]))
			$today[$tzid] = date_format(date_create('today ' . $tzid), 'U');

		// Tomorrow? We don't support the future. ;)
		if ($log_time >= $today[$tzid] + 86400)
		{
			$prefix = '';
		}
		// Today.
		elseif ($log_time >= $today[$tzid])
		{
			$prefix = Lang::$txt['today'];
		}
		// Yesterday.
		elseif (Config::$modSettings['todayMod'] > 1 && $log_time >= $today[$tzid] - 86400)
		{
			$prefix = Lang::$txt['yesterday'];
		}
	}

	// If $show_today is not a bool, use it as the date format & don't use User::$me->time_format. Allows for temp override of the format.
	$format = !is_bool($show_today) ? $show_today : User::$me->time_format;

	$format = !empty($prefix) ? Time::getDateOrTimeFormat('time', $format) : $format;

	// And now, the moment we've all be waiting for...
	return $prefix . smf_strftime($format, $log_time, $tzid);
}

/**
 * Replacement for strftime() that is compatible with PHP 8.1+.
 *
 * This does not use the system's strftime library or locale setting,
 * so results may vary in a few cases from the results of strftime():
 *
 *  - %a, %A, %b, %B, %p, %P: Output will use SMF's language strings
 *    to localize these values. If SMF's language strings have not
 *    been loaded, PHP's default English strings will be used.
 *
 *  - %c, %x, %X: Output will always use ISO format.
 *
 * @param string $format A strftime() format string.
 * @param int|null $timestamp A Unix timestamp.
 *     If null, defaults to the current time.
 * @param string|null $tzid Time zone identifier.
 *     If null, uses default time zone.
 * @return string The formatted datetime string.
 */
function smf_strftime(string $format, int $timestamp = null, string $tzid = null)
{
	static $dates = array();

	// Set default values as necessary.
	if (!isset($timestamp))
		$timestamp = time();

	if (!isset($tzid))
		$tzid = date_default_timezone_get();

	// A few substitutions to make life easier.
	$format = strtr($format, array(
		'%h' => '%b',
		'%r' => '%I:%M:%S %p',
		'%R' => '%H:%M',
		'%T' => '%H:%M:%S',
		'%X' => '%H:%M:%S',
		'%D' => '%m/%d/%y',
		'%F' => '%Y-%m-%d',
		'%x' => '%Y-%m-%d',
	));

	// Avoid unnecessary repetition.
	if (isset($dates[$tzid . '_' . $timestamp]['results'][$format]))
		return $dates[$tzid . '_' . $timestamp]['results'][$format];

	// Ensure the TZID is valid.
	if (($tz = @timezone_open($tzid)) === false)
	{
		$tzid = date_default_timezone_get();

		// Check again now that we have a valid TZID.
		if (isset($dates[$tzid . '_' . $timestamp]['results'][$format]))
			return $dates[$tzid . '_' . $timestamp]['results'][$format];

		$tz = timezone_open($tzid);
	}

	// Create the DateTime object and set its time zone.
	if (!isset($dates[$tzid . '_' . $timestamp]['object']))
	{
		$dates[$tzid . '_' . $timestamp]['object'] = date_create('@' . $timestamp);
		date_timezone_set($dates[$tzid . '_' . $timestamp]['object'], $tz);
	}

	$format_equivalents = array(
		// Day
		'a' => 'D', // Complex: prefer Lang::$txt strings if available.
		'A' => 'l', // Complex: prefer Lang::$txt strings if available.
		'e' => 'j', // Complex: sprintf to prepend whitespace.
		'd' => 'd',
		'j' => 'z', // Complex: must add one and then sprintf to prepend zeros.
		'u' => 'N',
		'w' => 'w',
		// Week
		'U' => 'z_w_0', // Complex: calculated from these other values.
		'V' => 'W',
		'W' => 'z_w_1', // Complex: calculated from these other values.
		// Month
		'b' => 'M', // Complex: prefer Lang::$txt strings if available.
		'B' => 'F', // Complex: prefer Lang::$txt strings if available.
		'm' => 'm',
		// Year
		'C' => 'Y', // Complex: Get 'Y' then truncate to first two digits.
		'g' => 'o', // Complex: Get 'o' then truncate to last two digits.
		'G' => 'o', // Complex: Get 'o' then sprintf to ensure four digits.
		'y' => 'y',
		'Y' => 'Y',
		// Time
		'H' => 'H',
		'k' => 'G',
		'I' => 'h',
		'l' => 'g', // Complex: sprintf to prepend whitespace.
		'M' => 'i',
		'p' => 'A', // Complex: prefer Lang::$txt strings if available.
		'P' => 'a', // Complex: prefer Lang::$txt strings if available.
		'S' => 's',
		'z' => 'O',
		'Z' => 'T',
		// Time and Date Stamps
		'c' => 'c',
		's' => 'U',
		// Miscellaneous
		'n' => "\n",
		't' => "\t",
		'%' => '%',
	);

	// Translate from strftime format to DateTime format.
	$parts = preg_split('/%(' . implode('|', array_keys($format_equivalents)) . ')/', $format, 0, PREG_SPLIT_DELIM_CAPTURE);

	$placeholders = array();
	$complex = false;

	for ($i = 0; $i < count($parts); $i++)
	{
		// Parts that are not strftime formats.
		if ($i % 2 === 0 || !isset($format_equivalents[$parts[$i]]))
		{
			if ($parts[$i] === '')
				continue;

			$placeholder = "\xEE\x84\x80" . $i . "\xEE\x84\x81";

			$placeholders[$placeholder] = $parts[$i];
			$parts[$i] = $placeholder;
		}
		// Parts that need localized strings.
		elseif (in_array($parts[$i], array('a', 'A', 'b', 'B')))
		{
			switch ($parts[$i])
			{
				case 'a':
					$min = 0;
					$max = 6;
					$key = 'days_short';
					$f = 'w';
					$placeholder_end = "\xEE\x84\x83";

					break;

				case 'A':
					$min = 0;
					$max = 6;
					$key = 'days';
					$f = 'w';
					$placeholder_end = "\xEE\x84\x82";

					break;

				case 'b':
					$min = 1;
					$max = 12;
					$key = 'months_short';
					$f = 'n';
					$placeholder_end = "\xEE\x84\x85";

					break;

				case 'B':
					$min = 1;
					$max = 12;
					$key = 'months';
					$f = 'n';
					$placeholder_end = "\xEE\x84\x84";

					break;
			}

			$placeholder = "\xEE\x84\x80" . $f . $placeholder_end;

			// Check whether Lang::$txt contains all expected strings.
			// If not, use English default.
			$txt_strings_exist = true;
			for ($num = $min; $num <= $max; $num++)
			{
				if (!isset(Lang::$txt[$key][$num]))
				{
					$txt_strings_exist = false;
					break;
				}
				else
					$placeholders[str_replace($f, $num, $placeholder)] = Lang::$txt[$key][$num];
			}

			$parts[$i] = $txt_strings_exist ? $placeholder : $format_equivalents[$parts[$i]];
		}
		elseif (in_array($parts[$i], array('p', 'P')))
		{
			if (!isset(Lang::$txt['time_am']) || !isset(Lang::$txt['time_pm']))
				continue;

			$placeholder = "\xEE\x84\x90" . $format_equivalents[$parts[$i]] . "\xEE\x84\x91";

			switch ($parts[$i])
			{
				// Lower case
				case 'p':
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'AM', $placeholder)] = Utils::strtoupper(Lang::$txt['time_am']);
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'PM', $placeholder)] = Utils::strtoupper(Lang::$txt['time_pm']);
					break;

				// Upper case
				case 'P':
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'am', $placeholder)] = Utils::strtolower(Lang::$txt['time_am']);
					$placeholders[str_replace($format_equivalents[$parts[$i]], 'pm', $placeholder)] = Utils::strtolower(Lang::$txt['time_pm']);
					break;
			}

			$parts[$i] = $placeholder;
		}
		// Parts that will need further processing.
		elseif (in_array($parts[$i], array('j', 'C', 'U', 'W', 'G', 'g', 'e', 'l')))
		{
			$complex = true;

			switch ($parts[$i])
			{
				case 'j':
					$placeholder_end = "\xEE\x84\xA1";
					break;

				case 'C':
					$placeholder_end = "\xEE\x84\xA2";
					break;

				case 'U':
				case 'W':
					$placeholder_end = "\xEE\x84\xA3";
					break;

				case 'G':
					$placeholder_end = "\xEE\x84\xA4";
					break;

				case 'g':
					$placeholder_end = "\xEE\x84\xA5";
					break;

				case 'e':
				case 'l':
					$placeholder_end = "\xEE\x84\xA6";
			}

			$parts[$i] = "\xEE\x84\xA0" . $format_equivalents[$parts[$i]] . $placeholder_end;
		}
		// Parts with simple equivalents.
		else
			$parts[$i] = $format_equivalents[$parts[$i]];
	}

	// The main event.
	$dates[$tzid . '_' . $timestamp]['results'][$format] = strtr(date_format($dates[$tzid . '_' . $timestamp]['object'], implode('', $parts)), $placeholders);

	// Deal with the complicated ones.
	if ($complex)
	{
		$dates[$tzid . '_' . $timestamp]['results'][$format] = preg_replace_callback(
			'/\xEE\x84\xA0([\d_]+)(\xEE\x84(?:[\xA1-\xAF]))/',
			function ($matches)
			{
				switch ($matches[2])
				{
					// %j
					case "\xEE\x84\xA1":
						$replacement = sprintf('%03d', (int) $matches[1] + 1);
						break;

					// %C
					case "\xEE\x84\xA2":
						$replacement = substr(sprintf('%04d', $matches[1]), 0, 2);
						break;

					// %U and %W
					case "\xEE\x84\xA3":
						list($day_of_year, $day_of_week, $first_day) = explode('_', $matches[1]);
						$replacement = sprintf('%02d', floor(((int) $day_of_year - (int) $day_of_week + (int) $first_day) / 7) + 1);
						break;

					// %G
					case "\xEE\x84\xA4":
						$replacement = sprintf('%04d', $matches[1]);
						break;

					// %g
					case "\xEE\x84\xA5":
						$replacement = substr(sprintf('%04d', $matches[1]), -2);
						break;

					// %e and %l
					case "\xEE\x84\xA6":
						$replacement = sprintf('%2d', $matches[1]);
						break;

					// Shouldn't happen, but just in case...
					default:
						$replacement = $matches[1];
						break;
				}

				return $replacement;
			},
			$dates[$tzid . '_' . $timestamp]['results'][$format]
		);
	}

	return $dates[$tzid . '_' . $timestamp]['results'][$format];
}

/**
 * Replacement for gmstrftime() that is compatible with PHP 8.1+.
 *
 * Calls smf_strftime() with the $tzid parameter set to 'UTC'.
 *
 * @param string $format A strftime() format string.
 * @param int|null $timestamp A Unix timestamp.
 *     If null, defaults to the current time.
 * @return string The formatted datetime string.
 */
function smf_gmstrftime(string $format, int $timestamp = null)
{
	return smf_strftime($format, $timestamp, 'UTC');
}

/**
 * Shorten a subject + internationalization concerns.
 *
 * - shortens a subject so that it is either shorter than length, or that length plus an ellipsis.
 * - respects internationalization characters and entities as one character.
 * - avoids trailing entities.
 * - returns the shortened string.
 *
 * @param string $subject The subject
 * @param int $len How many characters to limit it to
 * @return string The shortened subject - either the entire subject (if it's <= $len) or the subject shortened to $len characters with "..." appended
 */
function shorten_subject($subject, $len)
{
	// It was already short enough!
	if (Utils::entityStrlen((string) $subject) <= (int) $len)
		return $subject;

	// Shorten it by the length it was too long, and strip off junk from the end.
	return Utils::entitySubstr((string) $subject, 0, (int) $len) . '...';
}

/**
 * Deprecated function that formerly applied manual offsets to Unix timestamps
 * in order to provide a fake version of time zone support on ancient versions
 * of PHP. It now simply returns an unaltered timestamp.
 *
 * @deprecated since 2.1
 * @param bool $use_user_offset This parameter is deprecated and nonfunctional
 * @param int $timestamp A timestamp (null to use current time)
 * @return int Seconds since the Unix epoch
 */
function forum_time($use_user_offset = true, $timestamp = null)
{
	return !isset($timestamp) ? time() : (int) $timestamp;
}

/**
 * Calculates all the possible permutations (orders) of array.
 * should not be called on huge arrays (bigger than like 10 elements.)
 * returns an array containing each permutation.
 *
 * @deprecated since 2.1
 * @param array $array An array
 * @return array An array containing each permutation
 */
function permute($array)
{
	$orders = array($array);

	$n = count($array);
	$p = range(0, $n);
	for ($i = 1; $i < $n; null)
	{
		$p[$i]--;
		$j = $i % 2 != 0 ? $p[$i] : 0;

		$temp = $array[$i];
		$array[$i] = $array[$j];
		$array[$j] = $temp;

		for ($i = 1; $p[$i] == 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}

/**
 * Gets the appropriate URL to use for images (or whatever) when using SSL
 *
 * The returned URL may or may not be a proxied URL, depending on the situation.
 * Mods can implement alternative proxies using the 'integrate_proxy' hook.
 *
 * @param string $url The original URL of the requested resource
 * @return string The URL to use
 */
function get_proxied_url($url)
{

	// Only use the proxy if enabled, and never for robots
	if (empty(Config::$image_proxy_enabled) || !empty(User::$me->possibly_robot))
		return $url;

	$parsedurl = parse_iri($url);

	// Don't bother with HTTPS URLs, schemeless URLs, or obviously invalid URLs
	if (empty($parsedurl['scheme']) || empty($parsedurl['host']) || empty($parsedurl['path']) || $parsedurl['scheme'] === 'https')
		return $url;

	// We don't need to proxy our own resources
	if ($parsedurl['host'] === parse_iri(Config::$boardurl, PHP_URL_HOST))
		return strtr($url, array('http://' => 'https://'));

	// By default, use SMF's own image proxy script
	$proxied_url = strtr(Config::$boardurl, array('http://' => 'https://')) . '/proxy.php?request=' . urlencode($url) . '&hash=' . hash_hmac('sha1', $url, Config::$image_proxy_secret);

	// Allow mods to easily implement an alternative proxy
	// MOD AUTHORS: To add settings UI for your proxy, use the integrate_general_settings hook.
	call_integration_hook('integrate_proxy', array($url, &$proxied_url));

	return $proxied_url;
}

/**
 * Make sure the browser doesn't come back and repost the form data.
 * Should be used whenever anything is posted.
 *
 * @param string $setLocation The URL to redirect them to
 * @param bool $refresh Whether to use a meta refresh instead
 * @param bool $permanent Whether to send a 301 Moved Permanently instead of a 302 Moved Temporarily
 */
function redirectexit($setLocation = '', $refresh = false, $permanent = false)
{
	// In case we have mail to send, better do that - as obExit doesn't always quite make it...
	if (!empty(Utils::$context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in Mail::addToQueue itself... :\
		Mail::addToQueue(true);

	$add = preg_match('~^(ftp|http)[s]?://~', $setLocation) == 0 && substr($setLocation, 0, 6) != 'about:';

	if ($add)
		$setLocation = Config::$scripturl . ($setLocation != '' ? '?' . $setLocation : '');

	// Put the session ID in.
	if (defined('SID') && SID != '')
		$setLocation = preg_replace('/^' . preg_quote(Config::$scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', Config::$scripturl . '?' . SID . ';', $setLocation);
	// Keep that debug in their for template debugging!
	elseif (isset($_GET['debug']))
		$setLocation = preg_replace('/^' . preg_quote(Config::$scripturl, '/') . '\\??/', Config::$scripturl . '?debug;', $setLocation);

	if (!empty(Config::$modSettings['queryless_urls']) && (empty(Utils::$context['server']['is_cgi']) || ini_get('cgi.fix_pathinfo') == 1 || @get_cfg_var('cgi.fix_pathinfo') == 1) && (!empty(Utils::$context['server']['is_apache']) || !empty(Utils::$context['server']['is_lighttpd']) || !empty(Utils::$context['server']['is_litespeed'])))
	{
		if (defined('SID') && SID != '')
			$setLocation = preg_replace_callback(
				'~^' . preg_quote(Config::$scripturl, '~') . '\?(?:' . SID . '(?:;|&|&amp;))((?:board|topic)=[^#]+?)(#[^"]*?)?$~',
				function($m)
				{
					return Config::$scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html?' . SID . (isset($m[2]) ? "$m[2]" : "");
				},
				$setLocation
			);
		else
			$setLocation = preg_replace_callback(
				'~^' . preg_quote(Config::$scripturl, '~') . '\?((?:board|topic)=[^#"]+?)(#[^"]*?)?$~',
				function($m)
				{
					return Config::$scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html' . (isset($m[2]) ? "$m[2]" : "");
				},
				$setLocation
			);
	}

	// Maybe integrations want to change where we are heading?
	call_integration_hook('integrate_redirect', array(&$setLocation, &$refresh, &$permanent));

	// Set the header.
	header('location: ' . str_replace(' ', '%20', $setLocation), true, $permanent ? 301 : 302);

	// Debugging.
	if (isset(Config::$db_show_debug) && Config::$db_show_debug === true)
		$_SESSION['debug_redirect'] = Db::$cache;

	obExit(false);
}

/**
 * Ends execution.  Takes care of template loading and remembering the previous URL.
 *
 * @param bool $header Whether to do the header
 * @param bool $do_footer Whether to do the footer
 * @param bool $from_index Whether we're coming from the board index
 * @param bool $from_fatal_error Whether we're coming from a fatal error
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;
	if ($from_fatal_error)
		$has_fatal_error = true;

	// Clear out the stat cache.
	if (function_exists('trackStats'))
		Logging::trackStats();

	// If we have mail to send, send it.
	if (class_exists('SMF\\Mail', false) && !empty(Utils::$context['flush_mail']))
		// @todo this relies on 'flush_mail' being only set in Mail::addToQueue itself... :\
		Mail::addToQueue(true);

	$do_header = $header === null ? !$header_done : $header;
	if ($do_footer === null)
		$do_footer = $do_header;

	// Has the template/header been done yet?
	if ($do_header)
	{
		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty(Utils::$context['page_title']) && empty(Utils::$context['page_title_html_safe']))
			Utils::$context['page_title_html_safe'] = Utils::htmlspecialchars(html_entity_decode(Utils::$context['page_title'])) . (!empty(Utils::$context['current_page']) ? ' - ' . Lang::$txt['page'] . ' ' . (Utils::$context['current_page'] + 1) : '');

		// Start up the session URL fixer.
		ob_start('SMF\\QueryString::ob_sessrewrite');

		if (!empty(Theme::$current->settings['output_buffers']) && is_string(Theme::$current->settings['output_buffers']))
			$buffers = explode(',', Theme::$current->settings['output_buffers']);
		elseif (!empty(Theme::$current->settings['output_buffers']))
			$buffers = Theme::$current->settings['output_buffers'];
		else
			$buffers = array();

		if (isset(Config::$modSettings['integrate_buffer']))
			$buffers = array_merge(explode(',', Config::$modSettings['integrate_buffer']), $buffers);

		if (!empty($buffers))
			foreach ($buffers as $function)
			{
				$call = call_helper($function, true);

				// Is it valid?
				if (!empty($call))
					ob_start($call);
			}

		// Display the screen in the logical order.
		Theme::template_header();
		$header_done = true;
	}
	if ($do_footer)
	{
		Theme::loadSubTemplate(isset(Utils::$context['sub_template']) ? Utils::$context['sub_template'] : 'main');

		// Anything special to put out?
		if (!empty(Utils::$context['insert_after_template']) && !isset($_REQUEST['xml']))
			echo Utils::$context['insert_after_template'];

		// Just so we don't get caught in an endless loop of errors from the footer...
		if (!$footer_done)
		{
			$footer_done = true;
			Theme::template_footer();

			// (since this is just debugging... it's okay that it's after </html>.)
			if (!isset($_REQUEST['xml']))
				Logging::displayDebug();
		}
	}

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (!is_filtered_request(Forum::$unlogged_actions, 'action'))
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verification.... don't switch browsers...
	$_SESSION['USER_AGENT'] = empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];

	// Hand off the output to the portal, etc. we're integrated with.
	call_integration_hook('integrate_exit', array($do_footer));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index)
		exit;
}

/**
 * Get the size of a specified image with better error handling.
 *
 * @todo see if it's better in Subs-Graphics, but one step at the time.
 * Uses getimagesize() to determine the size of a file.
 * Attempts to connect to the server first so it won't time out.
 *
 * @param string $url The URL of the image
 * @return array|false The image size as array (width, height), or false on failure
 */
function url_image_size($url)
{
	// Make sure it is a proper URL.
	$url = str_replace(' ', '%20', $url);

	// Can we pull this from the cache... please please?
	if (($temp = CacheApi::get('url_image_size-' . md5($url), 240)) !== null)
		return $temp;
	$t = microtime(true);

	// Get the host to pester...
	preg_match('~^\w+://(.+?)/(.*)$~', $url, $match);

	// Can't figure it out, just try the image size.
	if ($url == '' || $url == 'http://' || $url == 'https://')
	{
		return false;
	}
	elseif (!isset($match[1]))
	{
		$size = @getimagesize($url);
	}
	else
	{
		// Try to connect to the server... give it half a second.
		$temp = 0;
		$fp = @fsockopen($match[1], 80, $temp, $temp, 0.5);

		// Successful?  Continue...
		if ($fp != false)
		{
			// Send the HEAD request (since we don't have to worry about chunked, HTTP/1.1 is fine here.)
			fwrite($fp, 'HEAD /' . $match[2] . ' HTTP/1.1' . "\r\n" . 'Host: ' . $match[1] . "\r\n" . 'user-agent: '. SMF_USER_AGENT . "\r\n" . 'Connection: close' . "\r\n\r\n");

			// Read in the HTTP/1.1 or whatever.
			$test = substr(fgets($fp, 11), -1);
			fclose($fp);

			// See if it returned a 404/403 or something.
			if ($test < 4)
			{
				$size = @getimagesize($url);

				// This probably means allow_url_fopen is off, let's try GD.
				if ($size === false && function_exists('imagecreatefromstring'))
				{
					// It's going to hate us for doing this, but another request...
					$image = @imagecreatefromstring(fetch_web_data($url));
					if ($image !== false)
					{
						$size = array(imagesx($image), imagesy($image));
						imagedestroy($image);
					}
				}
			}
		}
	}

	// If we didn't get it, we failed.
	if (!isset($size))
		$size = false;

	// If this took a long time, we may never have to do it again, but then again we might...
	if (microtime(true) - $t > 0.8)
		CacheApi::put('url_image_size-' . md5($url), $size, 240);

	// Didn't work.
	return $size;
}

/**
 * Helper function to set the system memory to a needed value
 * - If the needed memory is greater than current, will attempt to get more
 * - if in_use is set to true, will also try to take the current memory usage in to account
 *
 * @param string $needed The amount of memory to request, if needed, like 256M
 * @param bool $in_use Set to true to account for current memory usage of the script
 * @return boolean True if we have at least the needed memory
 */
function setMemoryLimit($needed, $in_use = false)
{
	// everything in bytes
	$memory_current = memoryReturnBytes(ini_get('memory_limit'));
	$memory_needed = memoryReturnBytes($needed);

	// should we account for how much is currently being used?
	if ($in_use)
		$memory_needed += function_exists('memory_get_usage') ? memory_get_usage() : (2 * 1048576);

	// if more is needed, request it
	if ($memory_current < $memory_needed)
	{
		@ini_set('memory_limit', ceil($memory_needed / 1048576) . 'M');
		$memory_current = memoryReturnBytes(ini_get('memory_limit'));
	}

	$memory_current = max($memory_current, memoryReturnBytes(get_cfg_var('memory_limit')));

	// return success or not
	return (bool) ($memory_current >= $memory_needed);
}

/**
 * Helper function to convert memory string settings to bytes
 *
 * @param string $val The byte string, like 256M or 1G
 * @return integer The string converted to a proper integer in bytes
 */
function memoryReturnBytes($val)
{
	if (is_integer($val))
		return $val;

	// Separate the number from the designator
	$val = trim($val);
	$num = intval(substr($val, 0, strlen($val) - 1));
	$last = strtolower(substr($val, -1));

	// convert to bytes
	switch ($last)
	{
		case 'g':
			$num *= 1024;
		case 'm':
			$num *= 1024;
		case 'k':
			$num *= 1024;
	}
	return $num;
}

/**
 * Convert a single IP to a ranged IP.
 * internal function used to convert a user-readable format to a format suitable for the database.
 *
 * @param string $fullip The full IP
 * @return array An array of IP parts
 */
function ip2range($fullip)
{
	// Pretend that 'unknown' is 255.255.255.255. (since that can't be an IP anyway.)
	if ($fullip == 'unknown')
		$fullip = '255.255.255.255';

	$ip_parts = explode('-', $fullip);
	$ip_array = array();

	// if ip 22.12.31.21
	if (count($ip_parts) == 1 && isValidIP($fullip))
	{
		$ip_array['low'] = $fullip;
		$ip_array['high'] = $fullip;
		return $ip_array;
	} // if ip 22.12.* -> 22.12.*-22.12.*
	elseif (count($ip_parts) == 1)
	{
		$ip_parts[0] = $fullip;
		$ip_parts[1] = $fullip;
	}

	// if ip 22.12.31.21-12.21.31.21
	if (count($ip_parts) == 2 && isValidIP($ip_parts[0]) && isValidIP($ip_parts[1]))
	{
		$ip_array['low'] = $ip_parts[0];
		$ip_array['high'] = $ip_parts[1];
		return $ip_array;
	}
	elseif (count($ip_parts) == 2) // if ip 22.22.*-22.22.*
	{
		$valid_low = isValidIP($ip_parts[0]);
		$valid_high = isValidIP($ip_parts[1]);
		$count = 0;
		$mode = (preg_match('/:/', $ip_parts[0]) > 0 ? ':' : '.');
		$max = ($mode == ':' ? 'ffff' : '255');
		$min = 0;
		if (!$valid_low)
		{
			$ip_parts[0] = preg_replace('/\*/', '0', $ip_parts[0]);
			$valid_low = isValidIP($ip_parts[0]);
			while (!$valid_low)
			{
				$ip_parts[0] .= $mode . $min;
				$valid_low = isValidIP($ip_parts[0]);
				$count++;
				if ($count > 9) break;
			}
		}

		$count = 0;
		if (!$valid_high)
		{
			$ip_parts[1] = preg_replace('/\*/', $max, $ip_parts[1]);
			$valid_high = isValidIP($ip_parts[1]);
			while (!$valid_high)
			{
				$ip_parts[1] .= $mode . $max;
				$valid_high = isValidIP($ip_parts[1]);
				$count++;
				if ($count > 9) break;
			}
		}

		if ($valid_high && $valid_low)
		{
			$ip_array['low'] = $ip_parts[0];
			$ip_array['high'] = $ip_parts[1];
		}
	}

	return $ip_array;
}

/**
 * Lookup an IP; try shell_exec first because we can do a timeout on it.
 *
 * @param string $ip The IP to get the hostname from
 * @return string The hostname
 */
function host_from_ip($ip)
{
	if (($host = CacheApi::get('hostlookup-' . $ip, 600)) !== null)
		return $host;
	$t = microtime(true);

	$exists = function_exists('shell_exec');

	// Try the Linux host command, perhaps?
	if ($exists && !isset($host) && (strpos(strtolower(PHP_OS), 'win') === false || strpos(strtolower(PHP_OS), 'darwin') !== false) && mt_rand(0, 1) == 1)
	{
		if (!isset(Config::$modSettings['host_to_dis']))
			$test = @shell_exec('host -W 1 ' . @escapeshellarg($ip));
		else
			$test = @shell_exec('host ' . @escapeshellarg($ip));

		// Did host say it didn't find anything?
		if (strpos($test, 'not found') !== false)
			$host = '';
		// Invalid server option?
		elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset(Config::$modSettings['host_to_dis']))
			Config::updateModSettings(array('host_to_dis' => 1));
		// Maybe it found something, after all?
		elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is nslookup; usually only Windows, but possibly some Unix?
	if ($exists && !isset($host) && stripos(PHP_OS, 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
	{
		$test = @shell_exec('nslookup -timeout=1 ' . @escapeshellarg($ip));
		if (strpos($test, 'Non-existent domain') !== false)
			$host = '';
		elseif (preg_match('~Name:\s+([^\s]+)~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is the last try :/.
	if (!isset($host) || $host === false)
		$host = @gethostbyaddr($ip);

	// It took a long time, so let's cache it!
	if (microtime(true) - $t > 0.5)
		CacheApi::put('hostlookup-' . $ip, $host, 600);

	return $host;
}

/**
 * Chops a string into words and prepares them to be inserted into (or searched from) the database.
 *
 * @param string $text The text to split into words
 * @param int $max_chars The maximum number of characters per word
 * @param bool $encrypt Whether to encrypt the results
 * @return array An array of ints or words depending on $encrypt
 */
function text2words($text, $max_chars = 20, $encrypt = false)
{
	// Upgrader may be working on old DBs...
	if (!isset(Utils::$context['utf8']))
		Utils::$context['utf8'] = false;

	// Step 1: Remove entities/things we don't consider words:
	$words = preg_replace('~(?:[\x0B\0' . (Utils::$context['utf8'] ? '\x{A0}' : '\xA0') . '\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~\?/\\\\]+|&(?:amp|lt|gt|quot);)+~' . (Utils::$context['utf8'] ? 'u' : ''), ' ', strtr($text, array('<br>' => ' ')));

	// Step 2: Entities we left to letters, where applicable, lowercase.
	$words = Utils::htmlspecialcharsDecode(Utils::strtolower($words));

	// Step 3: Ready to split apart and index!
	$words = explode(' ', $words);

	if ($encrypt)
	{
		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));
		$returned_ints = array();
		foreach ($words as $word)
		{
			if (($word = trim($word, '-_\'')) !== '')
			{
				$encrypted = substr(crypt($word, 'uk'), 2, $max_chars);
				$total = 0;
				for ($i = 0; $i < $max_chars; $i++)
					$total += $possible_chars[ord($encrypted[$i])] * pow(63, $i);
				$returned_ints[] = $max_chars == 4 ? min($total, 16777215) : $total;
			}
		}
		return array_unique($returned_ints);
	}
	else
	{
		// Trim characters before and after and add slashes for database insertion.
		$returned_words = array();
		foreach ($words as $word)
			if (($word = trim($word, '-_\'')) !== '')
				$returned_words[] = $max_chars === null ? $word : substr($word, 0, $max_chars);

		// Filter out all words that occur more than once.
		return array_unique($returned_words);
	}
}

/**
 * Generate a random seed and ensure it's stored in settings.
 */
function smf_seed_generator()
{
	Config::updateModSettings(array('rand_seed' => microtime(true)));
}
/**
 * Process functions of an integration hook.
 * calls all functions of the given hook.
 * supports static class method calls.
 *
 * @param string $hook The hook name
 * @param array $parameters An array of parameters this hook implements
 * @return array The results of the functions
 */
function call_integration_hook($hook, $parameters = array())
{
	if (!class_exists('SMF\\Utils', false))
		return;

	if (Config::$db_show_debug === true)
		Utils::$context['debug']['hooks'][] = $hook;

	// Need to have some control.
	if (!isset(Utils::$context['instances']))
		Utils::$context['instances'] = array();

	$results = array();
	if (empty(Config::$modSettings[$hook]))
		return $results;

	$functions = explode(',', Config::$modSettings[$hook]);
	// Loop through each function.
	foreach ($functions as $function)
	{
		// Hook has been marked as "disabled". Skip it!
		if (strpos($function, '!') !== false)
			continue;

		$call = call_helper($function, true);

		// Is it valid?
		if (!empty($call))
			$results[$function] = call_user_func_array($call, $parameters);
		// This failed, but we want to do so silently.
		elseif (!empty($function) && !empty(Utils::$context['ignore_hook_errors']))
			return $results;
		// Whatever it was suppose to call, it failed :(
		elseif (!empty($function))
		{
			Lang::load('Errors');

			// Get a full path to show on error.
			if (strpos($function, '|') !== false)
			{
				list ($file, $string) = explode('|', $function);
				$absPath = empty(Theme::$current->settings['theme_dir']) ? (strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir))) : (strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => Theme::$current->settings['theme_dir'])));
				ErrorHandler::log(sprintf(Lang::$txt['hook_fail_call_to'], $string, $absPath), 'general');
			}
			// "Assume" the file resides on Config::$boarddir somewhere...
			else
				ErrorHandler::log(sprintf(Lang::$txt['hook_fail_call_to'], $function, Config::$boarddir), 'general');
		}
	}

	return $results;
}

/**
 * Add a function for integration hook.
 * Does nothing if the function is already added.
 * Cleans up enabled/disabled variants before taking requested action.
 *
 * @param string $hook The complete hook name.
 * @param string $function The function name. Can be a call to a method via Class::method.
 * @param bool $permanent If true, updates the value in settings table.
 * @param string $file The file. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
 * @param bool $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
 */
function add_integration_function($hook, $function, $permanent = true, $file = '', $object = false)
{
	// Any objects?
	if ($object)
		$function = $function . '#';

	// Any files  to load?
	if (!empty($file) && is_string($file))
		$function = $file . (!empty($function) ? '|' . $function : '');

	// Get the correct string.
	$integration_call = $function;
	$enabled_call = rtrim($function, '!');
	$disabled_call = $enabled_call . '!';

	// Is it going to be permanent?
	if ($permanent)
	{
		$request = Db::$db->query('', '
			SELECT value
			FROM {db_prefix}settings
			WHERE variable = {string:variable}',
			array(
				'variable' => $hook,
			)
		);
		list ($current_functions) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if (!empty($current_functions))
		{
			$current_functions = explode(',', $current_functions);

			// Cleanup enabled/disabled variants before taking action.
			$current_functions = array_diff($current_functions, array($enabled_call, $disabled_call));

			$permanent_functions = array_unique(array_merge($current_functions, array($integration_call)));
		}
		else
			$permanent_functions = array($integration_call);

		Config::updateModSettings(array($hook => implode(',', $permanent_functions)));
	}

	// Make current function list usable.
	$functions = empty(Config::$modSettings[$hook]) ? array() : explode(',', Config::$modSettings[$hook]);

	// Cleanup enabled/disabled variants before taking action.
	$functions = array_diff($functions, array($enabled_call, $disabled_call));

	$functions = array_unique(array_merge($functions, array($integration_call)));
	Config::$modSettings[$hook] = implode(',', $functions);

	// It is handy to be able to know which hooks are temporary...
	if ($permanent !== true)
	{
		if (!isset(Utils::$context['integration_hooks_temporary']))
			Utils::$context['integration_hooks_temporary'] = array();
		Utils::$context['integration_hooks_temporary'][$hook][$function] = true;
	}
}

/**
 * Remove an integration hook function.
 * Removes the given function from the given hook.
 * Does nothing if the function is not available.
 * Cleans up enabled/disabled variants before taking requested action.
 *
 * @param string $hook The complete hook name.
 * @param string $function The function name. Can be a call to a method via Class::method.
 * @param boolean $permanent Irrelevant for the function itself but need to declare it to match
 * @param string $file The filename. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
 * @param boolean $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
 * @see add_integration_function
 */
function remove_integration_function($hook, $function, $permanent = true, $file = '', $object = false)
{
	// Any objects?
	if ($object)
		$function = $function . '#';

	// Any files  to load?
	if (!empty($file) && is_string($file))
		$function = $file . '|' . $function;

	// Get the correct string.
	$integration_call = $function;
	$enabled_call = rtrim($function, '!');
	$disabled_call = $enabled_call . '!';

	// Get the permanent functions.
	$request = Db::$db->query('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:variable}',
		array(
			'variable' => $hook,
		)
	);
	list ($current_functions) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	if (!empty($current_functions))
	{
		$current_functions = explode(',', $current_functions);

		// Cleanup enabled and disabled variants.
		$current_functions = array_unique(array_diff($current_functions, array($enabled_call, $disabled_call)));

		Config::updateModSettings(array($hook => implode(',', $current_functions)));
	}

	// Turn the function list into something usable.
	$functions = empty(Config::$modSettings[$hook]) ? array() : explode(',', Config::$modSettings[$hook]);

	// Cleanup enabled and disabled variants.
	$functions = array_unique(array_diff($functions, array($enabled_call, $disabled_call)));

	Config::$modSettings[$hook] = implode(',', $functions);
}

/**
 * Receives a string and tries to figure it out if it's a method or a function.
 * If a method is found, it looks for a "#" which indicates SMF should create a new instance of the given class.
 * Checks the string/array for is_callable() and return false/fatal_lang_error is the given value results in a non callable string/array.
 * Prepare and returns a callable depending on the type of method/function found.
 *
 * @param mixed $string The string containing a function name or a static call. The function can also accept a closure, object or a callable array (object/class, valid_callable)
 * @param boolean $return If true, the function will not call the function/method but instead will return the formatted string.
 * @return string|array|boolean Either a string or an array that contains a callable function name or an array with a class and method to call. Boolean false if the given string cannot produce a callable var.
 */
function call_helper($string, $return = false)
{
	// Really?
	if (empty($string))
		return false;

	// An array? should be a "callable" array IE array(object/class, valid_callable).
	// A closure? should be a callable one.
	if (is_array($string) || $string instanceof Closure)
		return $return ? $string : (is_callable($string) ? call_user_func($string) : false);

	// No full objects, sorry! pass a method or a property instead!
	if (is_object($string))
		return false;

	// Stay vitaminized my friends...
	$string = Utils::htmlspecialchars(Utils::htmlTrim($string));

	// Is there a file to load?
	$string = load_file($string);

	// Loaded file failed
	if (empty($string))
		return false;

	// Found a method.
	if (strpos($string, '::') !== false)
	{
		list ($class, $method) = explode('::', $string);

		// Check if a new object will be created.
		if (strpos($method, '#') !== false)
		{
			// Need to remove the # thing.
			$method = str_replace('#', '', $method);

			// Don't need to create a new instance for every method.
			if (empty(Utils::$context['instances'][$class]) || !(Utils::$context['instances'][$class] instanceof $class))
			{
				Utils::$context['instances'][$class] = new $class;

				// Add another one to the list.
				if (Config::$db_show_debug === true)
				{
					if (!isset(Utils::$context['debug']['instances']))
						Utils::$context['debug']['instances'] = array();

					Utils::$context['debug']['instances'][$class] = $class;
				}
			}

			$func = array(Utils::$context['instances'][$class], $method);
		}

		// Right then. This is a call to a static method.
		else
			$func = array($class, $method);
	}

	// Nope! just a plain regular function.
	else
		$func = $string;

	// We can't call this helper, but we want to silently ignore this.
	if (!is_callable($func, false, $callable_name) && !empty(Utils::$context['ignore_hook_errors']))
		return false;

	// Right, we got what we need, time to do some checks.
	elseif (!is_callable($func, false, $callable_name))
	{
		Lang::load('Errors');
		ErrorHandler::log(sprintf(Lang::$txt['sub_action_fail'], $callable_name), 'general');

		// Gotta tell everybody.
		return false;
	}

	// Everything went better than expected.
	else
	{
		// What are we gonna do about it?
		if ($return)
			return $func;

		// If this is a plain function, avoid the heat of calling call_user_func().
		else
		{
			if (is_array($func))
				call_user_func($func);

			else
				$func();
		}
	}
}

/**
 * Receives a string and tries to figure it out if it contains info to load a file.
 * Checks for a | (pipe) symbol and tries to load a file with the info given.
 * The string should be format as follows File.php|. You can use the following wildcards: $boarddir, $sourcedir and if available at the moment of execution, $themedir.
 *
 * @param string $string The string containing a valid format.
 * @return string|boolean The given string with the pipe and file info removed. Boolean false if the file couldn't be loaded.
 */
function load_file($string)
{
	if (empty($string))
		return false;

	if (strpos($string, '|') !== false)
	{
		list ($file, $string) = explode('|', $string);

		// Match the wildcards to their regular vars.
		if (empty(Theme::$current->settings['theme_dir']))
			$absPath = strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir));

		else
			$absPath = strtr(trim($file), array('$boarddir' => Config::$boarddir, '$sourcedir' => Config::$sourcedir, '$themedir' => Theme::$current->settings['theme_dir']));

		// Load the file if it can be loaded.
		if (file_exists($absPath))
			require_once($absPath);

		// No? try a fallback to Config::$sourcedir
		else
		{
			$absPath = Config::$sourcedir . '/' . $file;

			if (file_exists($absPath))
				require_once($absPath);

			// Sorry, can't do much for you at this point.
			elseif (empty(Utils::$context['uninstalling']))
			{
				Lang::load('Errors');
				ErrorHandler::log(sprintf(Lang::$txt['hook_fail_loading_file'], $absPath), 'general');

				// File couldn't be loaded.
				return false;
			}
		}
	}

	return $string;
}

/**
 * Get the contents of a URL, irrespective of allow_url_fopen.
 *
 * - reads the contents of an http or ftp address and returns the page in a string
 * - will accept up to 3 page redirections (redirectio_level in the function call is private)
 * - if post_data is supplied, the value and length is posted to the given url as form data
 * - URL must be supplied in lowercase
 *
 * @param string $url The URL
 * @param string $post_data The data to post to the given URL
 * @param bool $keep_alive Whether to send keepalive info
 * @param int $redirection_level How many levels of redirection
 * @return string|false The fetched data or false on failure
 */
function fetch_web_data($url, $post_data = '', $keep_alive = false, $redirection_level = 0)
{
	static $keep_alive_dom = null, $keep_alive_fp = null;

	preg_match('~^(http|ftp)(s)?://([^/:]+)(:(\d+))?(.+)$~', iri_to_url($url), $match);

	// No scheme? No data for you!
	if (empty($match[1]))
		return false;

	// An FTP url. We should try connecting and RETRieving it...
	// @todo Move this to a SMF\Fetchers\FtpFetcher class.
	elseif ($match[1] == 'ftp')
	{
		// Establish a connection and attempt to enable passive mode.
		$ftp = new SMF\PackageManager\FtpConnection(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? 21 : $match[5], 'anonymous', Config::$webmaster_email);

		if ($ftp->error !== false || !$ftp->passive())
			return false;

		// I want that one *points*!
		fwrite($ftp->connection, 'RETR ' . $match[6] . "\r\n");

		// Since passive mode worked (or we would have returned already!) open the connection.
		$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
		if (!$fp)
			return false;

		// The server should now say something in acknowledgement.
		$ftp->check_response(150);

		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// All done, right?  Good.
		$ftp->check_response(226);
		$ftp->close();
	}

	// This is more likely; a standard HTTP URL.
	elseif (isset($match[1]) && $match[1] == 'http')
	{
		// First try to use fsockopen, because it is fastest.
		// @todo Move this to a SMF\Fetchers\SocketFetcher class.
		if ($keep_alive && $match[3] == $keep_alive_dom)
			$fp = $keep_alive_fp;
		if (empty($fp))
		{
			// Open the socket on the port we want...
			$fp = @fsockopen(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? ($match[2] ? 443 : 80) : $match[5], $err, $err, 5);
		}
		if (!empty($fp))
		{
			if ($keep_alive)
			{
				$keep_alive_dom = $match[3];
				$keep_alive_fp = $fp;
			}

			// I want this, from there, and I'm not going to be bothering you for more (probably.)
			if (empty($post_data))
			{
				fwrite($fp, 'GET ' . ($match[6] !== '/' ? str_replace(' ', '%20', $match[6]) : '') . ' HTTP/1.0' . "\r\n");
				fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
				fwrite($fp, 'user-agent: '. SMF_USER_AGENT . "\r\n");
				if ($keep_alive)
					fwrite($fp, 'connection: Keep-Alive' . "\r\n\r\n");
				else
					fwrite($fp, 'connection: close' . "\r\n\r\n");
			}
			else
			{
				fwrite($fp, 'POST ' . ($match[6] !== '/' ? $match[6] : '') . ' HTTP/1.0' . "\r\n");
				fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? ':443' : '') : ':' . $match[5]) . "\r\n");
				fwrite($fp, 'user-agent: '. SMF_USER_AGENT . "\r\n");
				if ($keep_alive)
					fwrite($fp, 'connection: Keep-Alive' . "\r\n");
				else
					fwrite($fp, 'connection: close' . "\r\n");
				fwrite($fp, 'content-type: application/x-www-form-urlencoded' . "\r\n");
				fwrite($fp, 'content-length: ' . strlen($post_data) . "\r\n\r\n");
				fwrite($fp, $post_data);
			}

			$response = fgets($fp, 768);

			// Redirect in case this location is permanently or temporarily moved.
			if ($redirection_level < 3 && preg_match('~^HTTP/\S+\s+30[127]~i', $response) === 1)
			{
				$header = '';
				$location = '';
				while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
					if (stripos($header, 'location:') !== false)
						$location = trim(substr($header, strpos($header, ':') + 1));

				if (empty($location))
					return false;
				else
				{
					if (!$keep_alive)
						fclose($fp);
					return fetch_web_data($location, $post_data, $keep_alive, $redirection_level + 1);
				}
			}

			// Make sure we get a 200 OK.
			elseif (preg_match('~^HTTP/\S+\s+20[01]~i', $response) === 0)
				return false;

			// Skip the headers...
			while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
			{
				if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
					$content_length = $match[1];
				elseif (preg_match('~connection:\s*close~i', $header) != 0)
				{
					$keep_alive_dom = null;
					$keep_alive = false;
				}

				continue;
			}

			$data = '';
			if (isset($content_length))
			{
				while (!feof($fp) && strlen($data) < $content_length)
					$data .= fread($fp, $content_length - strlen($data));
			}
			else
			{
				while (!feof($fp))
					$data .= fread($fp, 4096);
			}

			if (!$keep_alive)
				fclose($fp);
		}

		// If using fsockopen didn't work, try to use cURL if available.
		elseif (function_exists('curl_init'))
		{
			$fetch_data = new CurlFetcher();
			$fetch_data->get_url_data($url, $post_data);

			// no errors and a 200 result, then we have a good dataset, well we at least have data. ;)
			if ($fetch_data->result('code') == 200 && !$fetch_data->result('error'))
				$data = $fetch_data->result('body');
			else
				return false;
		}

		// Neither fsockopen nor curl are available. Well, phooey.
		else
			return false;
	}
	else
	{
		// Umm, this shouldn't happen?
		Lang::load('Errors');
		trigger_error(Lang::$txt['fetch_web_data_bad_url'], E_USER_NOTICE);
		$data = false;
	}

	return $data;
}

/**
 * Attempts to determine the MIME type of some data or a file.
 *
 * @param string $data The data to check, or the path or URL of a file to check.
 * @param string $is_path If true, $data is a path or URL to a file.
 * @return string|bool A MIME type, or false if we cannot determine it.
 */
function get_mime_type($data, $is_path = false)
{
	$finfo_loaded = extension_loaded('fileinfo');
	$exif_loaded = extension_loaded('exif') && function_exists('image_type_to_mime_type');

	// Oh well. We tried.
	if (!$finfo_loaded && !$exif_loaded)
		return false;

	// Start with the 'empty' MIME type.
	$mime_type = 'application/x-empty';

	if ($finfo_loaded)
	{
		// Just some nice, simple data to analyze.
		if (empty($is_path))
			$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);

		// A file, or maybe a URL?
		else
		{
			// Local file.
			if (file_exists($data))
				$mime_type = mime_content_type($data);

			// URL.
			elseif ($data = fetch_web_data($data))
				$mime_type = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);
		}
	}
	// Workaround using Exif requires a local file.
	else
	{
		// If $data is a URL to fetch, do so.
		if (!empty($is_path) && !file_exists($data) && url_exists($data))
		{
			$data = fetch_web_data($data);
			$is_path = false;
		}

		// If we don't have a local file, create one and use it.
		if (empty($is_path))
		{
			$temp_file = tempnam(Config::$cachedir, md5($data));
			file_put_contents($temp_file, $data);
			$is_path = true;
			$data = $temp_file;
		}

		$imagetype = @exif_imagetype($data);

		if (isset($temp_file))
			unlink($temp_file);

		// Unfortunately, this workaround only works for image files.
		if ($imagetype !== false)
			$mime_type = image_type_to_mime_type($imagetype);
	}

	return $mime_type;
}

/**
 * Checks whether a file or data has the expected MIME type.
 *
 * @param string $data The data to check, or the path or URL of a file to check.
 * @param string $type_pattern A regex pattern to match the acceptable MIME types.
 * @param string $is_path If true, $data is a path or URL to a file.
 * @return int 1 if the detected MIME type matches the pattern, 0 if it doesn't, or 2 if we can't check.
 */
function check_mime_type($data, $type_pattern, $is_path = false)
{
	// Get the MIME type.
	$mime_type = get_mime_type($data, $is_path);

	// Couldn't determine it.
	if ($mime_type === false)
		return 2;

	// Check whether the MIME type matches expectations.
	return (int) @preg_match('~' . $type_pattern . '~', $mime_type);
}

/**
 * Prepares an array of "likes" info for the topic specified by $topic
 *
 * @param integer $topic The topic ID to fetch the info from.
 * @return array An array of IDs of messages in the specified topic that the current user likes
 */
function prepareLikesContext($topic)
{
	// Make sure we have something to work with.
	if (empty($topic))
		return array();

	// We already know the number of likes per message, we just want to know whether the current user liked it or not.
	$user = User::$me->id;
	$cache_key = 'likes_topic_' . $topic . '_' . $user;
	$ttl = 180;

	if (($temp = CacheApi::get($cache_key, $ttl)) === null)
	{
		$temp = array();
		$request = Db::$db->query('', '
			SELECT content_id
			FROM {db_prefix}user_likes AS l
				INNER JOIN {db_prefix}messages AS m ON (l.content_id = m.id_msg)
			WHERE l.id_member = {int:current_user}
				AND l.content_type = {literal:msg}
				AND m.id_topic = {int:topic}',
			array(
				'current_user' => $user,
				'topic' => $topic,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$temp[] = (int) $row['content_id'];

		CacheApi::put($cache_key, $temp, $ttl);
	}

	return $temp;
}

/**
 * Decode HTML entities to their UTF-8 equivalent character, except for
 * HTML special characters, which are always converted to numeric entities.
 *
 * Callback function for preg_replace_callback in subs-members
 * Uses capture group 2 in the supplied array
 * Does basic scan to ensure characters are inside a valid range
 *
 * @deprecated since 3.0
 *
 * @param array $matches An array of matches (relevant info should be the 3rd item)
 * @return string A fixed string
 */
function replaceEntities__callback($matches)
{
	return strtr(
		htmlspecialchars(Utils::entityDecode($matches[1], true), ENT_QUOTES),
		array(
			'&amp;' => '&#038;',
			'&quot;' => '&#034;',
			'&lt;' => '&#060;',
			'&gt;' => '&#062;',
		)
	);
}

/**
 * Converts HTML entities to UTF-8 equivalents.
 *
 * Callback function for preg_replace_callback
 * Uses capture group 1 in the supplied array
 * Does basic checks to keep characters inside a viewable range.
 *
 * @deprecated since 3.0
 *
 * @param array $matches An array of matches (relevant info should be the 2nd item in the array)
 * @return string The fixed string
 */
function fixchar__callback($matches)
{
	return Utils::entityDecode($matches[0], true);
}

/**
 * Strips out invalid HTML entities and fixes double-encoded entities.
 *
 * Callback function for preg_replace_callback.
 *
 * @deprecated since 3.0
 *
 * @param array $matches An array of matches (relevant info should be the 3rd
 *    item in the array)
 * @return string The fixed string
 */
function entity_fix__callback($matches)
{
	return Utils::sanitizeEntities(Utils::entityFix($matches[1]));
}

/**
 * Return a Gravatar URL based on
 * - the supplied email address,
 * - the global maximum rating,
 * - the global default fallback,
 * - maximum sizes as set in the admin panel.
 *
 * It is SSL aware, and caches most of the parameters.
 *
 * @param string $email_address The user's email address
 * @return string The gravatar URL
 */
function get_gravatar_url($email_address)
{
	static $url_params = null;

	if ($url_params === null)
	{
		$ratings = array('G', 'PG', 'R', 'X');
		$defaults = array('mm', 'identicon', 'monsterid', 'wavatar', 'retro', 'blank');
		$url_params = array();
		if (!empty(Config::$modSettings['gravatarMaxRating']) && in_array(Config::$modSettings['gravatarMaxRating'], $ratings))
			$url_params[] = 'rating=' . Config::$modSettings['gravatarMaxRating'];
		if (!empty(Config::$modSettings['gravatarDefault']) && in_array(Config::$modSettings['gravatarDefault'], $defaults))
			$url_params[] = 'default=' . Config::$modSettings['gravatarDefault'];
		if (!empty(Config::$modSettings['avatar_max_width_external']))
			$size_string = (int) Config::$modSettings['avatar_max_width_external'];
		if (!empty(Config::$modSettings['avatar_max_height_external']) && !empty($size_string))
			if ((int) Config::$modSettings['avatar_max_height_external'] < $size_string)
				$size_string = Config::$modSettings['avatar_max_height_external'];

		if (!empty($size_string))
			$url_params[] = 's=' . $size_string;
	}
	$http_method = !empty(Config::$modSettings['force_ssl']) ? 'https://secure' : 'http://www';

	return $http_method . '.gravatar.com/avatar/' . md5(Utils::strtolower($email_address)) . '?' . implode('&', $url_params);
}

/**
 * Get a list of time zones.
 *
 * @param string $when The date/time for which to calculate the time zone values.
 *		May be a Unix timestamp or any string that strtotime() can understand.
 *		Defaults to 'now'.
 * @return array An array of time zone identifiers and label text.
 */
function smf_list_timezones($when = 'now')
{
	static $timezones_when = array();

	require_once(Config::$sourcedir . '/Subs-Timezones.php');

	// Parseable datetime string?
	if (is_int($timestamp = strtotime($when)))
		$when = $timestamp;

	// A Unix timestamp?
	elseif (is_numeric($when))
		$when = intval($when);

	// Invalid value? Just get current Unix timestamp.
	else
		$when = time();

	// No point doing this over if we already did it once
	if (isset($timezones_when[$when]))
		return $timezones_when[$when];

	// We'll need these too
	$date_when = date_create('@' . $when);
	$later = strtotime('@' . $when . ' + 1 year');

	// Load up any custom time zone descriptions we might have
	Lang::load('Timezones');

	$tzid_metazones = get_tzid_metazones($later);

	// Should we put time zones from certain countries at the top of the list?
	$priority_countries = !empty(Config::$modSettings['timezone_priority_countries']) ? explode(',', Config::$modSettings['timezone_priority_countries']) : array();

	$priority_tzids = array();
	foreach ($priority_countries as $country)
	{
		$country_tzids = get_sorted_tzids_for_country($country);

		if (!empty($country_tzids))
			$priority_tzids = array_merge($priority_tzids, $country_tzids);
	}

	// Antarctic research stations should be listed last, unless you're running a penguin forum
	$low_priority_tzids = !in_array('AQ', $priority_countries) ? timezone_identifiers_list(DateTimeZone::ANTARCTICA) : array();

	$normal_priority_tzids = array_diff(array_unique(array_merge(array_keys($tzid_metazones), timezone_identifiers_list())), $priority_tzids, $low_priority_tzids);

	// Process them in order of importance.
	$tzids = array_merge($priority_tzids, $normal_priority_tzids, $low_priority_tzids);

	// Idea here is to get exactly one representative identifier for each and every unique set of time zone rules.
	$dst_types = array();
	$labels = array();
	$offsets = array();
	foreach ($tzids as $tzid)
	{
		// We don't want UTC right now
		if ($tzid == 'UTC')
			continue;

		$tz = @timezone_open($tzid);

		if ($tz == null)
			continue;

		// First, get the set of transition rules for this tzid
		$tzinfo = timezone_transitions_get($tz, $when, $later);

		// Use the entire set of transition rules as the array *key* so we can avoid duplicates
		$tzkey = serialize($tzinfo);

		// ...But make sure to include all explicitly defined meta-zones.
		if (isset($zones[$tzkey]['metazone']) && isset($tzid_metazones[$tzid]))
			$tzkey = serialize(array_merge($tzinfo, array('metazone' => $tzid_metazones[$tzid])));

		// Don't overwrite our preferred tzids
		if (empty($zones[$tzkey]['tzid']))
		{
			$zones[$tzkey]['tzid'] = $tzid;
			$zones[$tzkey]['dst_type'] = count($tzinfo) > 1 ? 1 : ($tzinfo[0]['isdst'] ? 2 : 0);

			foreach ($tzinfo as $transition) {
				$zones[$tzkey]['abbrs'][] = $transition['abbr'];
			}

			if (isset($tzid_metazones[$tzid]))
				$zones[$tzkey]['metazone'] = $tzid_metazones[$tzid];
			else
			{
				$tzgeo = timezone_location_get($tz);
				$country_tzids = get_sorted_tzids_for_country($tzgeo['country_code']);

				if (count($country_tzids) === 1)
					$zones[$tzkey]['metazone'] = Lang::$txt['iso3166'][$tzgeo['country_code']];
			}
		}

		// A time zone from a prioritized country?
		if (in_array($tzid, $priority_tzids))
			$priority_zones[$tzkey] = true;

		// Keep track of the location for this tzid.
		if (!empty(Lang::$txt[$tzid]))
			$zones[$tzkey]['locations'][] = Lang::$txt[$tzid];
		else
		{
			$tzid_parts = explode('/', $tzid);
			$zones[$tzkey]['locations'][] = str_replace(array('St_', '_'), array('St. ', ' '), array_pop($tzid_parts));
		}

		// Keep track of the current offset for this tzid.
		$offsets[$tzkey] = $tzinfo[0]['offset'];

		// Keep track of the Standard Time offset for this tzid.
		foreach ($tzinfo as $transition)
		{
			if (!$transition['isdst'])
			{
				$std_offsets[$tzkey] = $transition['offset'];
				break;
			}
		}
		if (!isset($std_offsets[$tzkey]))
			$std_offsets[$tzkey] = $tzinfo[0]['offset'];

		// Figure out the "meta-zone" info for the label
		if (empty($zones[$tzkey]['metazone']) && isset($tzid_metazones[$tzid]))
		{
			$zones[$tzkey]['metazone'] = $tzid_metazones[$tzid];
			$zones[$tzkey]['dst_type'] = count($tzinfo) > 1 ? 1 : ($tzinfo[0]['isdst'] ? 2 : 0);
		}
		$dst_types[$tzkey] = count($tzinfo) > 1 ? 'c' : ($tzinfo[0]['isdst'] ? 't' : 'f');
		$labels[$tzkey] = !empty($zones[$tzkey]['metazone']) && !empty(Lang::$tztxt[$zones[$tzkey]['metazone']]) ? Lang::$tztxt[$zones[$tzkey]['metazone']] : '';

		// Remember this for later
		if (isset(User::$me->timezone) && User::$me->timezone == $tzid)
			$member_tzkey = $tzkey;
		if (isset(Utils::$context['event']['tz']) && Utils::$context['event']['tz'] == $tzid)
			$event_tzkey = $tzkey;
		if (Config::$modSettings['default_timezone'] == $tzid)
			$default_tzkey = $tzkey;
	}

	// Sort by current offset, then standard offset, then DST type, then label.
	array_multisort($offsets, SORT_DESC, SORT_NUMERIC, $std_offsets, SORT_DESC, SORT_NUMERIC, $dst_types, SORT_ASC, $labels, SORT_ASC, $zones);

	// Build the final array of formatted values
	$priority_timezones = array();
	$timezones = array();
	foreach ($zones as $tzkey => $tzvalue)
	{
		date_timezone_set($date_when, timezone_open($tzvalue['tzid']));

		// Use the human friendly time zone name, if there is one.
		$desc = '';
		if (!empty($tzvalue['metazone']))
		{
			if (!empty(Lang::$tztxt[$tzvalue['metazone']]))
				$metazone = Lang::$tztxt[$tzvalue['metazone']];
			else
				$metazone = sprintf(Lang::$tztxt['generic_timezone'], $tzvalue['metazone'], '%1$s');

			switch ($tzvalue['dst_type'])
			{
				case 0:
					$desc = sprintf($metazone, Lang::$tztxt['daylight_saving_time_false']);
					break;

				case 1:
					$desc = sprintf($metazone, '');
					break;

				case 2:
					$desc = sprintf($metazone, Lang::$tztxt['daylight_saving_time_true']);
					break;
			}
		}
		// Otherwise, use the list of locations (max 5, so things don't get silly)
		else
			$desc = implode(', ', array_slice(array_unique($tzvalue['locations']), 0, 5)) . (count($tzvalue['locations']) > 5 ? ', ' . Lang::$txt['etc'] : '');

		// We don't want abbreviations like '+03' or '-11'.
		$abbrs = array_filter(
			$tzvalue['abbrs'],
			function ($abbr)
			{
				return !strspn($abbr, '+-');
			}
		);
		$abbrs = count($abbrs) == count($tzvalue['abbrs']) ? array_unique($abbrs) : array();

		// Show the UTC offset and abbreviation(s).
		$desc = '[UTC' . date_format($date_when, 'P') . '] - ' . str_replace('  ', ' ', $desc) . (!empty($abbrs) ? ' (' . implode('/', $abbrs) . ')' : '');

		if (isset($priority_zones[$tzkey]))
			$priority_timezones[$tzvalue['tzid']] = $desc;
		else
			$timezones[$tzvalue['tzid']] = $desc;

		// Automatically fix orphaned time zones.
		if (isset($member_tzkey) && $member_tzkey == $tzkey)
			User::$me->timezone = $tzvalue['tzid'];
		if (isset($event_tzkey) && $event_tzkey == $tzkey)
			Utils::$context['event']['tz'] = $tzvalue['tzid'];
		if (isset($default_tzkey) && $default_tzkey == $tzkey && Config::$modSettings['default_timezone'] != $tzvalue['tzid'])
			Config::updateModSettings(array('default_timezone' => $tzvalue['tzid']));
	}

	if (!empty($priority_timezones))
		$priority_timezones[] = '-----';

	$timezones = array_merge(
		$priority_timezones,
		array('UTC' => 'UTC' . (!empty(Lang::$tztxt['UTC']) ? ' - ' . Lang::$tztxt['UTC'] : ''), '-----'),
		$timezones
	);

	$timezones_when[$when] = $timezones;

	return $timezones_when[$when];
}

/**
 * Converts an IP address into binary
 *
 * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
 * @return string|false The IP address in binary or false
 */
function inet_ptod($ip_address)
{
	if (!isValidIP($ip_address))
		return $ip_address;

	$bin = inet_pton($ip_address);
	return $bin;
}

/**
 * Converts a binary version of an IP address into a readable format
 *
 * @param string $bin An IP address in IPv4, IPv6 (Either string (postgresql) or binary (other databases))
 * @return string|false The IP address in presentation format or false on error
 */
function inet_dtop($bin)
{
	if (empty($bin))
		return '';
	elseif (Config::$db_type == 'postgresql')
		return $bin;
	// Already a String?
	elseif (isValidIP($bin))
		return $bin;
	return inet_ntop($bin);
}

/**
 * Safe serialize() and unserialize() replacements
 *
 * @license Public Domain
 *
 * @author anthon (dot) pang (at) gmail (dot) com
 */

/**
 * Safe serialize() replacement. Recursive
 * - output a strict subset of PHP's native serialized representation
 * - does not serialize objects
 *
 * @param mixed $value
 * @return string
 */
function _safe_serialize($value)
{
	if (is_null($value))
		return 'N;';

	if (is_bool($value))
		return 'b:' . (int) $value . ';';

	if (is_int($value))
		return 'i:' . $value . ';';

	if (is_float($value))
		return 'd:' . str_replace(',', '.', $value) . ';';

	if (is_string($value))
		return 's:' . strlen($value) . ':"' . $value . '";';

	if (is_array($value))
	{
		// Check for nested objects or resources.
		$contains_invalid = false;
		array_walk_recursive(
			$value,
			function($v) use (&$contains_invalid)
			{
				if (is_object($v) || is_resource($v))
					$contains_invalid = true;
			}
		);
		if ($contains_invalid)
			return false;

		$out = '';
		foreach ($value as $k => $v)
			$out .= _safe_serialize($k) . _safe_serialize($v);

		return 'a:' . count($value) . ':{' . $out . '}';
	}

	// safe_serialize cannot serialize resources or objects.
	return false;
}

/**
 * Wrapper for _safe_serialize() that handles exceptions and multibyte encoding issues.
 *
 * @param mixed $value
 * @return string
 */
function safe_serialize($value)
{
	// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 2))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_serialize($value);

	if (isset($mbIntEnc))
		mb_internal_encoding($mbIntEnc);

	return $out;
}

/**
 * Safe unserialize() replacement
 * - accepts a strict subset of PHP's native serialized representation
 * - does not unserialize objects
 *
 * @param string $str
 * @return mixed
 * @throw Exception if $str is malformed or contains unsupported types (e.g., resources, objects)
 */
function _safe_unserialize($str)
{
	// Input  is not a string.
	if (empty($str) || !is_string($str))
		return false;

	// The substring 'O:' is used to serialize objects.
	// If it is not present, then there are none in the serialized data.
	if (strpos($str, 'O:') === false)
		return unserialize($str);

	$stack = array();
	$expected = array();

	/*
	 * states:
	 *   0 - initial state, expecting a single value or array
	 *   1 - terminal state
	 *   2 - in array, expecting end of array or a key
	 *   3 - in array, expecting value or another array
	 */
	$state = 0;
	while ($state != 1)
	{
		$type = isset($str[0]) ? $str[0] : '';
		if ($type == '}')
			$str = substr($str, 1);

		elseif ($type == 'N' && $str[1] == ';')
		{
			$value = null;
			$str = substr($str, 2);
		}
		elseif ($type == 'b' && preg_match('/^b:([01]);/', $str, $matches))
		{
			$value = $matches[1] == '1' ? true : false;
			$str = substr($str, 4);
		}
		elseif ($type == 'i' && preg_match('/^i:(-?[0-9]+);(.*)/s', $str, $matches))
		{
			$value = (int) $matches[1];
			$str = $matches[2];
		}
		elseif ($type == 'd' && preg_match('/^d:(-?[0-9]+\.?[0-9]*(E[+-][0-9]+)?);(.*)/s', $str, $matches))
		{
			$value = (float) $matches[1];
			$str = $matches[3];
		}
		elseif ($type == 's' && preg_match('/^s:([0-9]+):"(.*)/s', $str, $matches) && substr($matches[2], (int) $matches[1], 2) == '";')
		{
			$value = substr($matches[2], 0, (int) $matches[1]);
			$str = substr($matches[2], (int) $matches[1] + 2);
		}
		elseif ($type == 'a' && preg_match('/^a:([0-9]+):{(.*)/s', $str, $matches))
		{
			$expectedLength = (int) $matches[1];
			$str = $matches[2];
		}

		// Object or unknown/malformed type.
		else
			return false;

		switch ($state)
		{
			case 3: // In array, expecting value or another array.
				if ($type == 'a')
				{
					$stack[] = &$list;
					$list[$key] = array();
					$list = &$list[$key];
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}
				if ($type != '}')
				{
					$list[$key] = $value;
					$state = 2;
					break;
				}

				// Missing array value.
				return false;

			case 2: // in array, expecting end of array or a key
				if ($type == '}')
				{
					// Array size is less than expected.
					if (count($list) < end($expected))
						return false;

					unset($list);
					$list = &$stack[count($stack) - 1];
					array_pop($stack);

					// Go to terminal state if we're at the end of the root array.
					array_pop($expected);

					if (count($expected) == 0)
						$state = 1;

					break;
				}

				if ($type == 'i' || $type == 's')
				{
					// Array size exceeds expected length.
					if (count($list) >= end($expected))
						return false;

					$key = $value;
					$state = 3;
					break;
				}

				// Illegal array index type.
				return false;

			// Expecting array or value.
			case 0:
				if ($type == 'a')
				{
					$data = array();
					$list = &$data;
					$expected[] = $expectedLength;
					$state = 2;
					break;
				}

				if ($type != '}')
				{
					$data = $value;
					$state = 1;
					break;
				}

				// Not in array.
				return false;
		}
	}

	// Trailing data in input.
	if (!empty($str))
		return false;

	return $data;
}

/**
 * Wrapper for _safe_unserialize() that handles exceptions and multibyte encoding issue
 *
 * @param string $str
 * @return mixed
 */
function safe_unserialize($str)
{
	// Make sure we use the byte count for strings even when strlen() is overloaded by mb_strlen()
	if (function_exists('mb_internal_encoding') &&
		(((int) ini_get('mbstring.func_overload')) & 0x02))
	{
		$mbIntEnc = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}

	$out = _safe_unserialize($str);

	if (isset($mbIntEnc))
		mb_internal_encoding($mbIntEnc);

	return $out;
}

/**
 * Tries different modes to make file/dirs writable. Wrapper function for chmod()
 *
 * @param string $file The file/dir full path.
 * @param int $value Not needed, added for legacy reasons.
 * @return boolean  true if the file/dir is already writable or the function was able to make it writable, false if the function couldn't make the file/dir writable.
 */
function smf_chmod($file, $value = 0)
{
	// No file? no checks!
	if (empty($file))
		return false;

	// Already writable?
	if (is_writable($file))
		return true;

	// Do we have a file or a dir?
	$isDir = is_dir($file);
	$isWritable = false;

	// Set different modes.
	$chmodValues = $isDir ? array(0750, 0755, 0775, 0777) : array(0644, 0664, 0666);

	foreach ($chmodValues as $val)
	{
		// If it's writable, break out of the loop.
		if (is_writable($file))
		{
			$isWritable = true;
			break;
		}

		else
			@chmod($file, $val);
	}

	return $isWritable;
}

/**
 * Check the given String if he is a valid IPv4 or IPv6
 * return true or false
 *
 * @param string $IPString
 *
 * @return bool
 */
function isValidIP($IPString)
{
	return filter_var($IPString, FILTER_VALIDATE_IP) !== false;
}

/**
 * Outputs a response.
 * It assumes the data is already a string.
 *
 * @param string $data The data to print
 * @param string $type The content type. Defaults to Json.
 * @return void
 */
function smf_serverResponse($data = '', $type = 'content-type: application/json')
{
	// Defensive programming anyone?
	if (empty($data))
		return false;

	// Don't need extra stuff...
	Config::$db_show_debug = false;

	// Kill anything else.
	ob_end_clean();

	if (!empty(Config::$modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	// Set the header.
	header($type);

	// Echo!
	echo $data;

	// Done.
	obExit(false);
}

/**
 * Creates an optimized regex to match all known top level domains.
 *
 * The optimized regex is stored in Config::$modSettings['tld_regex'].
 *
 * To update the stored version of the regex to use the latest list of valid
 * TLDs from iana.org, set the $update parameter to true. Updating can take some
 * time, based on network connectivity, so it should normally only be done by
 * calling this function from a background or scheduled task.
 *
 * If $update is not true, but the regex is missing or invalid, the regex will
 * be regenerated from a hard-coded list of TLDs. This regenerated regex will be
 * overwritten on the next scheduled update.
 *
 * @param bool $update If true, fetch and process the latest official list of TLDs from iana.org.
 */
function set_tld_regex($update = false)
{
	static $done = false;

	// If we don't need to do anything, don't
	if (!$update && $done)
		return;

	// Should we get a new copy of the official list of TLDs?
	if ($update)
	{
		$tlds = fetch_web_data('https://data.iana.org/TLD/tlds-alpha-by-domain.txt');
		$tlds_md5 = fetch_web_data('https://data.iana.org/TLD/tlds-alpha-by-domain.txt.md5');

		/**
		 * If the Internet Assigned Numbers Authority can't be reached, the Internet is GONE!
		 * We're probably running on a server hidden in a bunker deep underground to protect
		 * it from marauding bandits roaming on the surface. We don't want to waste precious
		 * electricity on pointlessly repeating background tasks, so we'll wait until the next
		 * regularly scheduled update to see if civilization has been restored.
		 */
		if ($tlds === false || $tlds_md5 === false)
			$postapocalypticNightmare = true;

		// Make sure nothing went horribly wrong along the way.
		if (md5($tlds) != substr($tlds_md5, 0, 32))
			$tlds = array();
	}
	// If we aren't updating and the regex is valid, we're done
	elseif (!empty(Config::$modSettings['tld_regex']) && @preg_match('~' . Config::$modSettings['tld_regex'] . '~', '') !== false)
	{
		$done = true;
		return;
	}

	// If we successfully got an update, process the list into an array
	if (!empty($tlds))
	{
		// Clean $tlds and convert it to an array
		$tlds = array_filter(
			explode("\n", strtolower($tlds)),
			function($line)
			{
				$line = trim($line);
				if (empty($line) || strlen($line) != strspn($line, 'abcdefghijklmnopqrstuvwxyz0123456789-'))
					return false;
				else
					return true;
			}
		);

		// Convert Punycode to Unicode
		if (!function_exists('idn_to_utf8'))
			require_once(Config::$sourcedir . '/Subs-Compat.php');

		foreach ($tlds as &$tld)
			$tld = idn_to_utf8($tld, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
	}
	// Otherwise, use the 2012 list of gTLDs and ccTLDs for now and schedule a background update
	else
	{
		$tlds = array('com', 'net', 'org', 'edu', 'gov', 'mil', 'aero', 'asia', 'biz',
			'cat', 'coop', 'info', 'int', 'jobs', 'mobi', 'museum', 'name', 'post',
			'pro', 'tel', 'travel', 'xxx', 'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al',
			'am', 'ao', 'aq', 'ar', 'as', 'at', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd',
			'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bv',
			'bw', 'by', 'bz', 'ca', 'cc', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm',
			'cn', 'co', 'cr', 'cu', 'cv', 'cx', 'cy', 'cz', 'de', 'dj', 'dk', 'dm', 'do',
			'dz', 'ec', 'ee', 'eg', 'er', 'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm', 'fo',
			'fr', 'ga', 'gb', 'gd', 'ge', 'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp',
			'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy', 'hk', 'hm', 'hn', 'hr', 'ht', 'hu',
			'id', 'ie', 'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it', 'je', 'jm', 'jo',
			'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr', 'kw', 'ky', 'kz', 'la',
			'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly', 'ma', 'mc', 'md',
			'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt',
			'mu', 'mv', 'mw', 'mx', 'my', 'mz', 'na', 'nc', 'ne', 'nf', 'ng', 'ni', 'nl',
			'no', 'np', 'nr', 'nu', 'nz', 'om', 'pa', 'pe', 'pf', 'pg', 'ph', 'pk', 'pl',
			'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'py', 'qa', 're', 'ro', 'rs', 'ru', 'rw',
			'sa', 'sb', 'sc', 'sd', 'se', 'sg', 'sh', 'si', 'sj', 'sk', 'sl', 'sm', 'sn',
			'so', 'sr', 'ss', 'st', 'su', 'sv', 'sx', 'sy', 'sz', 'tc', 'td', 'tf', 'tg',
			'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt', 'tv', 'tw', 'tz', 'ua',
			'ug', 'uk', 'us', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu', 'wf',
			'ws', 'ye', 'yt', 'za', 'zm', 'zw',
		);

		// Schedule a background update, unless civilization has collapsed and/or we are having connectivity issues.
		if (empty($postapocalypticNightmare))
		{
			Db::$db->insert('insert', '{db_prefix}background_tasks',
				array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/UpdateTldRegex.php', 'SMF\Tasks\UpdateTldRegex', '', 0), array()
			);
		}
	}

	// Tack on some "special use domain names" that aren't in DNS but may possibly resolve.
	// See https://www.iana.org/assignments/special-use-domain-names/ for more info.
	$tlds = array_merge($tlds, array('local', 'onion', 'test'));

	// Get an optimized regex to match all the TLDs
	$tld_regex = build_regex($tlds);

	// Remember the new regex in Config::$modSettings
	Config::updateModSettings(array('tld_regex' => $tld_regex));

	// Redundant repetition is redundant
	$done = true;
}

/**
 * Creates optimized regular expressions from an array of strings.
 *
 * An optimized regex built using this function will be much faster than a
 * simple regex built using `implode('|', $strings)` --- anywhere from several
 * times to several orders of magnitude faster.
 *
 * However, the time required to build the optimized regex is approximately
 * equal to the time it takes to execute the simple regex. Therefore, it is only
 * worth calling this function if the resulting regex will be used more than
 * once.
 *
 * Because PHP places an upper limit on the allowed length of a regex, very
 * large arrays of $strings may not fit in a single regex. Normally, the excess
 * strings will simply be dropped. However, if the $returnArray parameter is set
 * to true, this function will build as many regexes as necessary to accommodate
 * everything in $strings and return them in an array. You will need to iterate
 * through all elements of the returned array in order to test all possible
 * matches.
 *
 * @param array $strings An array of strings to make a regex for.
 * @param string $delim An optional delimiter character to pass to preg_quote().
 * @param bool $returnArray If true, returns an array of regexes.
 * @return string|array One or more regular expressions to match any of the input strings.
 */
function build_regex($strings, $delim = null, $returnArray = false)
{
	static $regexes = array();

	// If it's not an array, there's not much to do. ;)
	if (!is_array($strings))
		return preg_quote(@strval($strings), $delim);

	$regex_key = md5(json_encode(array($strings, $delim, $returnArray)));

	if (isset($regexes[$regex_key]))
		return $regexes[$regex_key];

	// The mb_* functions are faster than the SMF\Utils ones, but may not be available
	if (function_exists('mb_internal_encoding') && function_exists('mb_detect_encoding') && function_exists('mb_strlen') && function_exists('mb_substr'))
	{
		if (($string_encoding = mb_detect_encoding(implode(' ', $strings))) !== false)
		{
			$current_encoding = mb_internal_encoding();
			mb_internal_encoding($string_encoding);
		}

		$strlen = 'mb_strlen';
		$substr = 'mb_substr';
	}
	else
	{
		$strlen = 'SMF\\Utils::entityStrlen';
		$substr = 'SMF\\Utils::entitySubstr';
	}

	// This recursive function creates the index array from the strings
	$add_string_to_index = function($string, $index) use (&$strlen, &$substr, &$add_string_to_index)
	{
		static $depth = 0;
		$depth++;

		$first = (string) @$substr($string, 0, 1);

		// No first character? That's no good.
		if ($first === '')
		{
			// A nested array? Really? Ugh. Fine.
			if (is_array($string) && $depth < 20)
			{
				foreach ($string as $str)
					$index = $add_string_to_index($str, $index);
			}

			$depth--;
			return $index;
		}

		if (empty($index[$first]))
			$index[$first] = array();

		if ($strlen($string) > 1)
		{
			// Sanity check on recursion
			if ($depth > 99)
				$index[$first][$substr($string, 1)] = '';

			else
				$index[$first] = $add_string_to_index($substr($string, 1), $index[$first]);
		}
		else
			$index[$first][''] = '';

		$depth--;
		return $index;
	};

	// This recursive function turns the index array into a regular expression
	$index_to_regex = function(&$index, $delim) use (&$strlen, &$index_to_regex)
	{
		static $depth = 0;
		$depth++;

		// Absolute max length for a regex is 32768, but we might need wiggle room
		$max_length = 30000;

		$regex = array();
		$length = 0;

		foreach ($index as $key => $value)
		{
			$key_regex = preg_quote($key, $delim);
			$new_key = $key;

			if (empty($value))
				$sub_regex = '';
			else
			{
				$sub_regex = $index_to_regex($value, $delim);

				if (count(array_keys($value)) == 1)
				{
					$new_key_array = explode('(?' . '>', $sub_regex);
					$new_key .= $new_key_array[0];
				}
				else
					$sub_regex = '(?' . '>' . $sub_regex . ')';
			}

			if ($depth > 1)
				$regex[$new_key] = $key_regex . $sub_regex;
			else
			{
				if (($length += strlen($key_regex . $sub_regex) + 1) < $max_length || empty($regex))
				{
					$regex[$new_key] = $key_regex . $sub_regex;
					unset($index[$key]);
				}
				else
					break;
			}
		}

		// Sort by key length and then alphabetically
		uksort(
			$regex,
			function($k1, $k2) use (&$strlen)
			{
				$l1 = $strlen($k1);
				$l2 = $strlen($k2);

				if ($l1 == $l2)
					return strcmp($k1, $k2) > 0 ? 1 : -1;
				else
					return $l1 > $l2 ? -1 : 1;
			}
		);

		$depth--;
		return implode('|', $regex);
	};

	// Now that the functions are defined, let's do this thing
	$index = array();
	$regex = '';

	foreach ($strings as $string)
		$index = $add_string_to_index($string, $index);

	if ($returnArray === true)
	{
		$regex = array();
		while (!empty($index))
			$regex[] = '(?' . '>' . $index_to_regex($index, $delim) . ')';
	}
	else
		$regex = '(?' . '>' . $index_to_regex($index, $delim) . ')';

	// Restore PHP's internal character encoding to whatever it was originally
	if (!empty($current_encoding))
		mb_internal_encoding($current_encoding);

	$regexes[$regex_key] = $regex;
	return $regex;
}

/**
 * Check if the passed url has an SSL certificate.
 *
 * Returns true if a cert was found & false if not.
 *
 * @param string $url to check, in Config::$boardurl format (no trailing slash).
 */
function ssl_cert_found($url)
{
	// This check won't work without OpenSSL
	if (!extension_loaded('openssl'))
		return true;

	// First, strip the subfolder from the passed url, if any
	$parsedurl = parse_iri($url);
	$url = 'ssl://' . $parsedurl['host'] . ':443';

	// Next, check the ssl stream context for certificate info
	if (version_compare(PHP_VERSION, '5.6.0', '<'))
		$ssloptions = array("capture_peer_cert" => true);
	else
		$ssloptions = array("capture_peer_cert" => true, "verify_peer" => true, "allow_self_signed" => true);

	$result = false;
	$strem_context = stream_context_create(array("ssl" => $ssloptions));
	$stream = @stream_socket_client($url, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, Utils::$strem_context);
	if ($stream !== false)
	{
		$params = stream_context_get_params($stream);
		$result = isset($params["options"]["ssl"]["peer_certificate"]) ? true : false;
	}
	return $result;
}

/**
 * Check if the passed url has a redirect to https:// by querying headers.
 *
 * Returns true if a redirect was found & false if not.
 * Note that when force_ssl = 2, SMF issues its own redirect...  So if this
 * returns true, it may be caused by SMF, not necessarily an .htaccess redirect.
 *
 * @param string $url to check, in Config::$boardurl format (no trailing slash).
 */
function https_redirect_active($url)
{
	// Ask for the headers for the passed url, but via http...
	// Need to add the trailing slash, or it puts it there & thinks there's a redirect when there isn't...
	$url = str_ireplace('https://', 'http://', $url) . '/';
	$headers = @get_headers($url);
	if ($headers === false)
		return false;

	// Now to see if it came back https...
	// First check for a redirect status code in first row (301, 302, 307)
	if (strstr($headers[0], '301') === false && strstr($headers[0], '302') === false && strstr($headers[0], '307') === false)
		return false;

	// Search for the location entry to confirm https
	$result = false;
	foreach ($headers as $header)
	{
		if (stristr($header, 'Location: https://') !== false)
		{
			$result = true;
			break;
		}
	}
	return $result;
}

/**
 * Check if the connection is using https.
 *
 * @return boolean true if connection used https
 */
function httpsOn()
{
	$secure = false;

	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
		$secure = true;
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
		$secure = true;

	return $secure;
}

/**
 * A wrapper for `parse_url($url)` that can handle URLs with international
 * characters (a.k.a. IRIs)
 *
 * @param string $iri The IRI to parse.
 * @param int $component Optional parameter to pass to parse_url().
 * @return mixed Same as parse_url(), but with unmangled Unicode.
 */
function parse_iri($iri, $component = -1)
{
	$iri = preg_replace_callback(
		'~[^\x00-\x7F\pZ\pC]|%~u',
		function($matches)
		{
			return rawurlencode($matches[0]);
		},
		$iri
	);

	$parsed = parse_url($iri, $component);

	if (is_array($parsed))
	{
		foreach ($parsed as &$part)
			$part = rawurldecode($part);
	}
	elseif (is_string($parsed))
		$parsed = rawurldecode($parsed);

	return $parsed;
}

/**
 * A wrapper for `filter_var($url, FILTER_VALIDATE_URL)` that can handle URLs
 * with international characters (a.k.a. IRIs)
 *
 * @param string $iri The IRI to test.
 * @param int $flags Optional flags to pass to filter_var()
 * @return string|bool Either the original IRI, or false if the IRI was invalid.
 */
function validate_iri($iri, $flags = 0)
{
	$url = iri_to_url($iri);

	// PHP 5 doesn't recognize IPv6 addresses in the URL host.
	if (version_compare(phpversion(), '7.0.0', '<'))
	{
		$host = parse_url((strpos($url, '//') === 0 ? 'http:' : '') . $url, PHP_URL_HOST);

		if (strpos($host, '[') === 0 && strpos($host, ']') === strlen($host) - 1 && strpos($host, ':') !== false)
			$url = str_replace($host, '127.0.0.1', $url);
	}

	if (filter_var($url, FILTER_VALIDATE_URL, $flags) !== false)
		return $iri;
	else
		return false;
}

/**
 * A wrapper for `filter_var($url, FILTER_SANITIZE_URL)` that can handle URLs
 * with international characters (a.k.a. IRIs)
 *
 * Note: The returned value will still be an IRI, not a URL. To convert to URL,
 * feed the result of this function to iri_to_url()
 *
 * @param string $iri The IRI to sanitize.
 * @return string|bool The sanitized version of the IRI
 */
function sanitize_iri($iri)
{
	// Encode any non-ASCII characters (but not space or control characters of any sort)
	// Also encode '%' in order to preserve anything that is already percent-encoded.
	$iri = preg_replace_callback(
		'~[^\x00-\x7F\pZ\pC]|%~u',
		function($matches)
		{
			return rawurlencode($matches[0]);
		},
		$iri
	);

	// Perform normal sanitization
	$iri = filter_var($iri, FILTER_SANITIZE_URL);

	// Decode the non-ASCII characters
	$iri = rawurldecode($iri);

	return $iri;
}

/**
 * Performs Unicode normalization on IRIs.
 *
 * Internally calls sanitize_iri(), then performs Unicode normalization on the
 * IRI as a whole, using NFKC normalization for the domain name (see RFC 3491)
 * and NFC normalization for the rest.
 *
 * @param string $iri The IRI to normalize.
 * @return string|bool The normalized version of the IRI.
 */
function normalize_iri($iri)
{
	// If we are not using UTF-8, just sanitize and return.
	if (isset(Utils::$context['utf8']) ? !Utils::$context['utf8'] : (isset(Lang::$txt['lang_character_set']) ? Lang::$txt['lang_character_set'] != 'UTF-8' : (isset(Config::$db_character_set) && Config::$db_character_set != 'utf8')))
		return sanitize_iri($iri);

	$iri = sanitize_iri(Utils::normalize($iri));

	$host = parse_iri((strpos($iri, '//') === 0 ? 'http:' : '') . $iri, PHP_URL_HOST);

	if (!empty($host))
	{
		$normalized_host = Utils::normalize($host, 'kc_casefold');
		$pos = strpos($iri, $host);
	}
	else
	{
		$host = '';
		$normalized_host = '';
		$pos = 0;
	}

	$before_host = substr($iri, 0, $pos);
	$after_host = substr($iri, $pos + strlen($host));

	return $before_host . $normalized_host . $after_host;
}

/**
 * Converts a URL with international characters (an IRI) into a pure ASCII URL
 *
 * Uses Punycode to encode any non-ASCII characters in the domain name, and uses
 * standard URL encoding on the rest.
 *
 * @param string $iri A IRI that may or may not contain non-ASCII characters.
 * @return string|bool The URL version of the IRI.
 */
function iri_to_url($iri)
{
	// Sanity check: must be using UTF-8 to do this.
	if (isset(Utils::$context['utf8']) ? !Utils::$context['utf8'] : (isset(Lang::$txt['lang_character_set']) ? Lang::$txt['lang_character_set'] != 'UTF-8' : (isset(Config::$db_character_set) && Config::$db_character_set != 'utf8')))
		return $iri;

	$iri = sanitize_iri(Utils::normalize($iri));

	$host = parse_iri((strpos($iri, '//') === 0 ? 'http:' : '') . $iri, PHP_URL_HOST);

	if (!empty($host))
	{
		if (!function_exists('idn_to_ascii'))
			require_once(Config::$sourcedir . '/Subs-Compat.php');

		// Convert the host using the Punycode algorithm
		$encoded_host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

		$pos = strpos($iri, $host);
	}
	else
	{
		$host = '';
		$encoded_host = '';
		$pos = 0;
	}

	$before_host = substr($iri, 0, $pos);
	$after_host = substr($iri, $pos + strlen($host));

	// Encode any disallowed characters in the rest of the URL
	$unescaped = array(
		'%21' => '!', '%23' => '#', '%24' => '$', '%26' => '&',
		'%27' => "'", '%28' => '(', '%29' => ')', '%2A' => '*',
		'%2B' => '+', '%2C' => ',', '%2F' => '/', '%3A' => ':',
		'%3B' => ';', '%3D' => '=', '%3F' => '?', '%40' => '@',
		'%25' => '%',
	);

	$before_host = strtr(rawurlencode($before_host), $unescaped);
	$after_host = strtr(rawurlencode($after_host), $unescaped);

	return $before_host . $encoded_host . $after_host;
}

/**
 * Decodes a URL containing encoded international characters to UTF-8
 *
 * Decodes any Punycode encoded characters in the domain name, then uses
 * standard URL decoding on the rest.
 *
 * @param string $url The pure ASCII version of a URL.
 * @return string|bool The UTF-8 version of the URL.
 */
function url_to_iri($url)
{
	// Sanity check: must be using UTF-8 to do this.
	if (isset(Utils::$context['utf8']) ? !Utils::$context['utf8'] : (isset(Lang::$txt['lang_character_set']) ? Lang::$txt['lang_character_set'] != 'UTF-8' : (isset(Config::$db_character_set) && Config::$db_character_set != 'utf8')))
		return $url;

	$host = parse_iri((strpos($url, '//') === 0 ? 'http:' : '') . $url, PHP_URL_HOST);

	if (!empty($host))
	{
		if (!function_exists('idn_to_utf8'))
			require_once(Config::$sourcedir . '/Subs-Compat.php');

		// Decode the domain from Punycode
		$decoded_host = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

		$pos = strpos($url, $host);
	}
	else
	{
		$decoded_host = '';
		$pos = 0;
	}

	$before_host = substr($url, 0, $pos);
	$after_host = substr($url, $pos + strlen($host));

	// Decode the rest of the URL, but preserve escaped URL syntax characters.
	$double_escaped = array(
		'%21' => '%2521', '%23' => '%2523', '%24' => '%2524', '%26' => '%2526',
		'%27' => '%2527', '%28' => '%2528', '%29' => '%2529', '%2A' => '%252A',
		'%2B' => '%252B', '%2C' => '%252C', '%2F' => '%252F', '%3A' => '%253A',
		'%3B' => '%253B', '%3D' => '%253D', '%3F' => '%253F', '%40' => '%2540',
		'%25' => '%2525',
	);

	$before_host = rawurldecode(strtr($before_host, $double_escaped));
	$after_host = rawurldecode(strtr($after_host, $double_escaped));

	return $before_host . $decoded_host . $after_host;
}

/**
 * Ensures SMF's scheduled tasks are being run as intended
 *
 * If the admin activated the cron_is_real_cron setting, but the cron job is
 * not running things at least once per day, we need to go back to SMF's default
 * behaviour using "web cron" JavaScript calls.
 */
function check_cron()
{
	if (!empty(Config::$modSettings['cron_is_real_cron']) && time() - @intval(Config::$modSettings['cron_last_checked']) > 84600)
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}scheduled_tasks
			WHERE disabled = {int:not_disabled}
				AND next_time < {int:yesterday}',
			array(
				'not_disabled' => 0,
				'yesterday' => time() - 84600,
			)
		);
		list($overdue) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// If we have tasks more than a day overdue, cron isn't doing its job.
		if (!empty($overdue))
		{
			Lang::load('ManageScheduledTasks');
			ErrorHandler::log(Lang::$txt['cron_not_working']);
			Config::updateModSettings(array('cron_is_real_cron' => 0));
		}
		else
			Config::updateModSettings(array('cron_last_checked' => time()));
	}
}

/**
 * Sends an appropriate HTTP status header based on a given status code
 *
 * @param int $code The status code
 * @param string $status The string for the status. Set automatically if not provided.
 */
function send_http_status($code, $status = '')
{
	// This will fail anyways if headers have been sent.
	if (headers_sent())
		return;

	$statuses = array(
		204 => 'No Content',
		206 => 'Partial Content',
		304 => 'Not Modified',
		400 => 'Bad Request',
		403 => 'Forbidden',
		404 => 'Not Found',
		410 => 'Gone',
		500 => 'Internal Server Error',
		503 => 'Service Unavailable',
	);

	$protocol = !empty($_SERVER['SERVER_PROTOCOL']) && preg_match('~^\s*(HTTP/[12]\.\d)\s*$~i', $_SERVER['SERVER_PROTOCOL'], $matches) ? $matches[1] : 'HTTP/1.0';

	// Typically during these requests, we have cleaned the response (ob_*clean), ensure these headers exist.
	Security::frameOptionsHeader();
	Security::corsPolicyHeader();

	if (!isset($statuses[$code]) && empty($status))
		header($protocol . ' 500 Internal Server Error');
	else
		header($protocol . ' ' . $code . ' ' . (!empty($status) ? $status : $statuses[$code]));
}

/**
 * Truncate an array to a specified length
 *
 * @param array $array The array to truncate
 * @param int $max_length The upperbound on the length
 * @param int $deep How levels in an multidimensional array should the function take into account.
 * @return array The truncated array
 */
function truncate_array($array, $max_length = 1900, $deep = 3)
{
	$array = (array) $array;

	$curr_length = array_length($array, $deep);

	if ($curr_length <= $max_length)
		return $array;

	else
	{
		// Truncate each element's value to a reasonable length
		$param_max = floor($max_length / count($array));

		$current_deep = $deep - 1;

		foreach ($array as $key => &$value)
		{
			if (is_array($value))
				if ($current_deep > 0)
					$value = truncate_array($value, $current_deep);

			else
				$value = substr($value, 0, $param_max - strlen($key) - 5);
		}

		return $array;
	}
}

/**
 * array_length Recursive
 * @param array $array
 * @param int $deep How many levels should the function
 * @return int
 */
function array_length($array, $deep = 3)
{
	// Work with arrays
	$array = (array) $array;
	$length = 0;

	$deep_count = $deep - 1;

	foreach ($array as $value)
	{
		// Recursive?
		if (is_array($value))
		{
			// No can't do
			if ($deep_count <= 0)
				continue;

			$length += array_length($value, $deep_count);
		}
		else
			$length += strlen($value);
	}

	return $length;
}

/**
 * Compares existance request variables against an array.
 *
 * The input array is associative, where keys denote accepted values
 * in a request variable denoted by `$req_val`. Values can be:
 *
 * - another associative array where at least one key must be found
 *   in the request and their values are accepted request values.
 * - A scalar value, in which case no furthur checks are done.
 *
 * @param array $array
 * @param string $req_var request variable
 *
 * @return bool whether any of the criteria was satisfied
 */
function is_filtered_request(array $array, $req_var)
{
	$matched = false;
	if (isset($_REQUEST[$req_var], $array[$_REQUEST[$req_var]]))
	{
		if (is_array($array[$_REQUEST[$req_var]]))
		{
			foreach ($array[$_REQUEST[$req_var]] as $subtype => $subnames)
				$matched |= isset($_REQUEST[$subtype]) && in_array($_REQUEST[$subtype], $subnames);
		}
		else
			$matched = true;
	}

	return (bool) $matched;
}

/**
 * Clean up the XML to make sure it doesn't contain invalid characters.
 *
 * See https://www.w3.org/TR/xml/#charsets
 *
 * @param string $string The string to clean
 * @return string The cleaned string
 */
function cleanXml($string)
{
	$illegal_chars = array(
		// Remove all ASCII control characters except \t, \n, and \r.
		"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08",
		"\x0B", "\x0C", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13", "\x14",
		"\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D",
		"\x1E", "\x1F",
		// Remove \xFFFE and \xFFFF
		"\xEF\xBF\xBE", "\xEF\xBF\xBF",
	);

	$string = str_replace($illegal_chars, '', $string);

	// The Unicode surrogate pair code points should never be present in our
	// strings to begin with, but if any snuck in, they need to be removed.
	if (!empty(Utils::$context['utf8']) && strpos($string, "\xED") !== false)
		$string = preg_replace('/\xED[\xA0-\xBF][\x80-\xBF]/', '', $string);

	return $string;
}

/**
 * Escapes (replaces) characters in strings to make them safe for use in JavaScript
 *
 * @param string $string The string to escape
 * @param bool $as_json If true, escape as double-quoted string. Default false.
 * @return string The escaped string
 */
function JavaScriptEscape($string, $as_json = false)
{
	$q = !empty($as_json) ? '"' : '\'';

	return $q . strtr($string, array(
		"\r" => '',
		"\n" => '\\n',
		"\t" => '\\t',
		'\\' => '\\\\',
		$q => addslashes($q),
		'</' => '<' . $q . ' + ' . $q . '/',
		'<script' => '<scri' . $q . '+' . $q . 'pt',
		'<body>' => '<bo' . $q . '+' . $q . 'dy>',
		'<a href' => '<a hr' . $q . '+' . $q . 'ef',
		Config::$scripturl => $q . ' + smf_scripturl + ' . $q,
	)) . $q;
}

?>