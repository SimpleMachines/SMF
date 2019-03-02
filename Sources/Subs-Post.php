<?php

/**
 * This file contains those functions pertaining to posting, and other such
 * operations, including sending emails, ims, blocking spam, preparsing posts,
 * spell checking, and the post box.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Takes a message and parses it, returning nothing.
 * Cleans up links (javascript, etc.) and code/quote sections.
 * Won't convert \n's and a few other things if previewing is true.
 *
 * @param string $message The mesasge
 * @param bool $previewing Whether we're previewing
 */
function preparsecode(&$message, $previewing = false)
{
	global $user_info, $modSettings, $context, $sourcedir;

	static $tags_regex, $disallowed_tags_regex;

	// This line makes all languages *theoretically* work even with the wrong charset ;).
	if (empty($context['utf8']))
		$message = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $message);

	// Clean up after nobbc ;).
	$message = preg_replace_callback('~\[nobbc\](.+?)\[/nobbc\]~is', function($a)
	{
		return '[nobbc]' . strtr($a[1], array('[' => '&#91;', ']' => '&#93;', ':' => '&#58;', '@' => '&#64;')) . '[/nobbc]';
	}, $message);

	// Remove \r's... they're evil!
	$message = strtr($message, array("\r" => ''));

	// You won't believe this - but too many periods upsets apache it seems!
	$message = preg_replace('~\.{100,}~', '...', $message);

	// Trim off trailing quotes - these often happen by accident.
	while (substr($message, -7) == '[quote]')
		$message = substr($message, 0, -7);
	while (substr($message, 0, 8) == '[/quote]')
		$message = substr($message, 8);

	if (strpos($message, '[cowsay') !== false && !allowedTo('bbc_cowsay'))
		$message = preg_replace('~\[(/?)cowsay[^\]]*\]~iu', '[$1pre]', $message);

	// Find all code blocks, work out whether we'd be parsing them, then ensure they are all closed.
	$in_tag = false;
	$had_tag = false;
	$codeopen = 0;
	if (preg_match_all('~(\[(/)*code(?:=[^\]]+)?\])~is', $message, $matches))
		foreach ($matches[0] as $index => $dummy)
		{
			// Closing?
			if (!empty($matches[2][$index]))
			{
				// If it's closing and we're not in a tag we need to open it...
				if (!$in_tag)
					$codeopen = true;
				// Either way we ain't in one any more.
				$in_tag = false;
			}
			// Opening tag...
			else
			{
				$had_tag = true;
				// If we're in a tag don't do nought!
				if (!$in_tag)
					$in_tag = true;
			}
		}

	// If we have an open tag, close it.
	if ($in_tag)
		$message .= '[/code]';
	// Open any ones that need to be open, only if we've never had a tag.
	if ($codeopen && !$had_tag)
		$message = '[code]' . $message;

	// Replace code BBC with placeholders. We'll restore them at the end.
	$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
	for ($i = 0, $n = count($parts); $i < $n; $i++)
	{
		// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
		if ($i % 4 == 2)
		{
			$code_tag = $parts[$i - 1] . $parts[$i] . $parts[$i + 1];
			$substitute = $parts[$i - 1] . $i . $parts[$i + 1];
			$code_tags[$substitute] = $code_tag;
			$parts[$i] = $i;
		}
	}

	$message = implode('', $parts);

	// The regular expression non breaking space has many versions.
	$non_breaking_space = $context['utf8'] ? '\x{A0}' : '\xA0';

	// Now that we've fixed all the code tags, let's fix the img and url tags...
	fixTags($message);

	// Replace /me.+?\n with [me=name]dsf[/me]\n.
	if (strpos($user_info['name'], '[') !== false || strpos($user_info['name'], ']') !== false || strpos($user_info['name'], '\'') !== false || strpos($user_info['name'], '"') !== false)
		$message = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=&quot;' . $user_info['name'] . '&quot;]$2[/me]', $message);
	else
		$message = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=' . $user_info['name'] . ']$2[/me]', $message);

	if (!$previewing && strpos($message, '[html]') !== false)
	{
		if (allowedTo('bbc_html'))
			$message = preg_replace_callback('~\[html\](.+?)\[/html\]~is', function($m)
			{
				return '[html]' . strtr(un_htmlspecialchars($m[1]), array("\n" => '&#13;', '  ' => ' &#32;', '[' => '&#91;', ']' => '&#93;')) . '[/html]';
			}, $message);

		// We should edit them out, or else if an admin edits the message they will get shown...
		else
		{
			while (strpos($message, '[html]') !== false)
				$message = preg_replace('~\[[/]?html\]~i', '', $message);
		}
	}

	// Let's look at the time tags...
	$message = preg_replace_callback('~\[time(?:=(absolute))*\](.+?)\[/time\]~i', function($m) use ($modSettings, $user_info)
	{
		return "[time]" . (is_numeric("$m[2]") || @strtotime("$m[2]") == 0 ? "$m[2]" : strtotime("$m[2]") - ("$m[1]" == "absolute" ? 0 : (($modSettings["time_offset"] + $user_info["time_offset"]) * 3600))) . "[/time]";
	}, $message);

	// Change the color specific tags to [color=the color].
	$message = preg_replace('~\[(black|blue|green|red|white)\]~', '[color=$1]', $message); // First do the opening tags.
	$message = preg_replace('~\[/(black|blue|green|red|white)\]~', '[/color]', $message); // And now do the closing tags

	// Neutralize any BBC tags this member isn't permitted to use.
	if (empty($disallowed_tags_regex))
	{
		// Legacy BBC are only retained for historical reasons. They're not for use in new posts.
		$disallowed_bbc = $context['legacy_bbc'];

		// Some BBC require permissions.
		foreach ($context['restricted_bbc'] as $bbc)
		{
			// Skip html, since we handled it separately above.
			if ($bbc === 'html')
				continue;
			if (!allowedTo('bbc_' . $bbc))
				$disallowed_bbc[] = $bbc;
		}

		$disallowed_tags_regex = build_regex(array_unique($disallowed_bbc), '~');
	}
	if (!empty($disallowed_tags_regex))
		$message = preg_replace('~\[(?=/?' . $disallowed_tags_regex . '\b)~i', '&#91;', $message);

	// Make sure all tags are lowercase.
	$message = preg_replace_callback('~\[(/?)(list|li|table|tr|td)\b([^\]]*)\]~i', function($m)
	{
		return "[$m[1]" . strtolower("$m[2]") . "$m[3]]";
	}, $message);

	$list_open = substr_count($message, '[list]') + substr_count($message, '[list ');
	$list_close = substr_count($message, '[/list]');
	if ($list_close - $list_open > 0)
		$message = str_repeat('[list]', $list_close - $list_open) . $message;
	if ($list_open - $list_close > 0)
		$message = $message . str_repeat('[/list]', $list_open - $list_close);

	$mistake_fixes = array(
		// Find [table]s not followed by [tr].
		'~\[table\](?![\s' . $non_breaking_space . ']*\[tr\])~s' . ($context['utf8'] ? 'u' : '') => '[table][tr]',
		// Find [tr]s not followed by [td].
		'~\[tr\](?![\s' . $non_breaking_space . ']*\[td\])~s' . ($context['utf8'] ? 'u' : '') => '[tr][td]',
		// Find [/td]s not followed by something valid.
		'~\[/td\](?![\s' . $non_breaking_space . ']*(?:\[td\]|\[/tr\]|\[/table\]))~s' . ($context['utf8'] ? 'u' : '') => '[/td][/tr]',
		// Find [/tr]s not followed by something valid.
		'~\[/tr\](?![\s' . $non_breaking_space . ']*(?:\[tr\]|\[/table\]))~s' . ($context['utf8'] ? 'u' : '') => '[/tr][/table]',
		// Find [/td]s incorrectly followed by [/table].
		'~\[/td\][\s' . $non_breaking_space . ']*\[/table\]~s' . ($context['utf8'] ? 'u' : '') => '[/td][/tr][/table]',
		// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
		'~\[(table|tr|/td)\]([\s' . $non_breaking_space . ']*)\[td\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_td_]',
		// Now, any [td]s left should have a [tr] before them.
		'~\[td\]~s' => '[tr][td]',
		// Look for [tr]s which are correctly placed.
		'~\[(table|/tr)\]([\s' . $non_breaking_space . ']*)\[tr\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_tr_]',
		// Any remaining [tr]s should have a [table] before them.
		'~\[tr\]~s' => '[table][tr]',
		// Look for [/td]s followed by [/tr].
		'~\[/td\]([\s' . $non_breaking_space . ']*)\[/tr\]~s' . ($context['utf8'] ? 'u' : '') => '[/td]$1[_/tr_]',
		// Any remaining [/tr]s should have a [/td].
		'~\[/tr\]~s' => '[/td][/tr]',
		// Look for properly opened [li]s which aren't closed.
		'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
		'~\[li\]([^\[\]]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
		'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
		// Lists - find correctly closed items/lists.
		'~\[/li\]([\s' . $non_breaking_space . ']*)\[/list\]~s' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[/list]',
		// Find list items closed and then opened.
		'~\[/li\]([\s' . $non_breaking_space . ']*)\[li\]~s' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[_li_]',
		// Now, find any [list]s or [/li]s followed by [li].
		'~\[(list(?: [^\]]*?)?|/li)\]([\s' . $non_breaking_space . ']*)\[li\]~s' . ($context['utf8'] ? 'u' : '') => '[$1]$2[_li_]',
		// Allow for sub lists.
		'~\[/li\]([\s' . $non_breaking_space . ']*)\[list\]~' . ($context['utf8'] ? 'u' : '') => '[_/li_]$1[list]',
		'~\[/list\]([\s' . $non_breaking_space . ']*)\[li\]~' . ($context['utf8'] ? 'u' : '') => '[/list]$1[_li_]',
		// Any remaining [li]s weren't inside a [list].
		'~\[li\]~' => '[list][li]',
		// Any remaining [/li]s weren't before a [/list].
		'~\[/li\]~' => '[/li][/list]',
		// Put the correct ones back how we found them.
		'~\[_(li|/li|td|tr|/tr)_\]~' => '[$1]',
		// Images with no real url.
		'~\[img\]https?://.{0,7}\[/img\]~' => '',
	);

	// Fix up some use of tables without [tr]s, etc. (it has to be done more than once to catch it all.)
	for ($j = 0; $j < 3; $j++)
		$message = preg_replace(array_keys($mistake_fixes), $mistake_fixes, $message);

	// Remove empty bbc from the sections outside the code tags
	if (empty($tags_regex))
	{
		require_once($sourcedir . '/Subs.php');

		$allowed_empty = array('anchor', 'td',);

		$tags = array();
		foreach (($codes = parse_bbc(false)) as $code)
			if (!in_array($code['tag'], $allowed_empty))
				$tags[] = $code['tag'];

		$tags_regex = build_regex($tags, '~');
	}
	while (preg_match('~\[(' . $tags_regex . ')\b[^\]]*\]\s*\[/\1\]\s?~i', $message))
		$message = preg_replace('~\[(' . $tags_regex . ')[^\]]*\]\s*\[/\1\]\s?~i', '', $message);

	// Restore code blocks
	if (!empty($code_tags))
		$message = str_replace(array_keys($code_tags), array_values($code_tags), $message);

	// Restore white space entities
	if (!$previewing)
		$message = strtr($message, array('  ' => '&nbsp; ', "\n" => '<br>', $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;'));
	else
		$message = strtr($message, array('  ' => '&nbsp; ', $context['utf8'] ? "\xC2\xA0" : "\xA0" => '&nbsp;'));

	// Now let's quickly clean up things that will slow our parser (which are common in posted code.)
	$message = strtr($message, array('[]' => '&#91;]', '[&#039;' => '&#91;&#039;'));
}

/**
 * This is very simple, and just removes things done by preparsecode.
 *
 * @param string $message The message
 */
function un_preparsecode($message)
{
	global $smcFunc;

	$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

	// We're going to unparse only the stuff outside [code]...
	for ($i = 0, $n = count($parts); $i < $n; $i++)
	{
		// If $i is a multiple of four (0, 4, 8, ...) then it's not a code section...
		if ($i % 4 == 2)
		{
			$code_tag = $parts[$i - 1] . $parts[$i] . $parts[$i + 1];
			$substitute = $parts[$i - 1] . $i . $parts[$i + 1];
			$code_tags[$substitute] = $code_tag;
			$parts[$i] = $i;
		}
	}

	$message = implode('', $parts);

	$message = preg_replace_callback('~\[html\](.+?)\[/html\]~i', function($m) use ($smcFunc)
	{
		return "[html]" . strtr($smcFunc['htmlspecialchars']("$m[1]", ENT_QUOTES), array("\\&quot;" => "&quot;", "&amp;#13;" => "<br>", "&amp;#32;" => " ", "&amp;#91;" => "[", "&amp;#93;" => "]")) . "[/html]";
	}, $message);

	if (strpos($message, '[cowsay') !== false && !allowedTo('bbc_cowsay'))
		$message = preg_replace('~\[(/?)cowsay[^\]]*\]~iu', '[$1pre]', $message);

	// Attempt to un-parse the time to something less awful.
	$message = preg_replace_callback('~\[time\](\d{0,10})\[/time\]~i', function($m)
	{
		return "[time]" . timeformat("$m[1]", false) . "[/time]";
	}, $message);

	if (!empty($code_tags))
		$message = strtr($message, $code_tags);

	// Change breaks back to \n's and &nsbp; back to spaces.
	return preg_replace('~<br\s*/?' . '>~', "\n", str_replace('&nbsp;', ' ', $message));
}

/**
 * Fix any URLs posted - ie. remove 'javascript:'.
 * Used by preparsecode, fixes links in message and returns nothing.
 *
 * @param string $message The message
 */
function fixTags(&$message)
{
	global $modSettings;

	// WARNING: Editing the below can cause large security holes in your forum.
	// Edit only if you are sure you know what you are doing.

	$fixArray = array(
		// [img]http://...[/img] or [img width=1]http://...[/img]
		array(
			'tag' => 'img',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
			'hasExtra' => true,
		),
		// [url]http://...[/url]
		array(
			'tag' => 'url',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
		),
		// [url=http://...]name[/url]
		array(
			'tag' => 'url',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [iurl]http://...[/iurl]
		array(
			'tag' => 'iurl',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
		),
		// [iurl=http://...]name[/iurl]
		array(
			'tag' => 'iurl',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [ftp]ftp://...[/ftp]
		array(
			'tag' => 'ftp',
			'protocols' => array('ftp', 'ftps'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
		),
		// [ftp=ftp://...]name[/ftp]
		array(
			'tag' => 'ftp',
			'protocols' => array('ftp', 'ftps'),
			'embeddedUrl' => true,
			'hasEqualSign' => true,
		),
		// [flash]http://...[/flash]
		array(
			'tag' => 'flash',
			'protocols' => array('http', 'https'),
			'embeddedUrl' => false,
			'hasEqualSign' => false,
			'hasExtra' => true,
		),
	);

	// Fix each type of tag.
	foreach ($fixArray as $param)
		fixTag($message, $param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));

	// Now fix possible security problems with images loading links automatically...
	$message = preg_replace_callback('~(\[img.*?\])(.+?)\[/img\]~is', function($m)
	{
		return "$m[1]" . preg_replace("~action(=|%3d)(?!dlattach)~i", "action-", "$m[2]") . "[/img]";
	}, $message);

}

/**
 * Fix a specific class of tag - ie. url with =.
 * Used by fixTags, fixes a specific tag's links.
 *
 * @param string $message The message
 * @param string $myTag The tag
 * @param string $protocols The protocols
 * @param bool $embeddedUrl Whether it *can* be set to something
 * @param bool $hasEqualSign Whether it *is* set to something
 * @param bool $hasExtra Whether it can have extra cruft after the begin tag.
 */
function fixTag(&$message, $myTag, $protocols, $embeddedUrl = false, $hasEqualSign = false, $hasExtra = false)
{
	global $boardurl, $scripturl;

	if (preg_match('~^([^:]+://[^/]+)~', $boardurl, $match) != 0)
		$domain_url = $match[1];
	else
		$domain_url = $boardurl . '/';

	$replaces = array();

	if ($hasEqualSign && $embeddedUrl)
	{
		$quoted = preg_match('~\[(' . $myTag . ')=&quot;~', $message);
		preg_match_all('~\[(' . $myTag . ')=' . ($quoted ? '&quot;(.*?)&quot;' : '([^\]]*?)') . '\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
	}
	elseif ($hasEqualSign)
		preg_match_all('~\[(' . $myTag . ')=([^\]]*?)\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
	else
		preg_match_all('~\[(' . $myTag . ($hasExtra ? '(?:[^\]]*?)' : '') . ')\](.+?)\[/(' . $myTag . ')\]~is', $message, $matches);

	foreach ($matches[0] as $k => $dummy)
	{
		// Remove all leading and trailing whitespace.
		$replace = trim($matches[2][$k]);
		$this_tag = $matches[1][$k];
		$this_close = $hasEqualSign ? (empty($matches[4][$k]) ? '' : $matches[4][$k]) : $matches[3][$k];

		$found = false;
		foreach ($protocols as $protocol)
		{
			$found = strncasecmp($replace, $protocol . '://', strlen($protocol) + 3) === 0;
			if ($found)
				break;
		}

		if (!$found && $protocols[0] == 'http')
		{
			if (substr($replace, 0, 1) == '/' && substr($replace, 0, 2) != '//')
				$replace = $domain_url . $replace;
			elseif (substr($replace, 0, 1) == '?')
				$replace = $scripturl . $replace;
			elseif (substr($replace, 0, 1) == '#' && $embeddedUrl)
			{
				$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
				$this_tag = 'iurl';
				$this_close = 'iurl';
			}
			elseif (substr($replace, 0, 2) != '//')
				$replace = $protocols[0] . '://' . $replace;
		}
		elseif (!$found && $protocols[0] == 'ftp')
			$replace = $protocols[0] . '://' . preg_replace('~^(?!ftps?)[^:]+://~', '', $replace);
		elseif (!$found)
			$replace = $protocols[0] . '://' . $replace;

		if ($hasEqualSign && $embeddedUrl)
			$replaces[$matches[0][$k]] = '[' . $this_tag . '=&quot;' . $replace . '&quot;]' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
		elseif ($hasEqualSign)
			$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
		elseif ($embeddedUrl)
			$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']' . $matches[2][$k] . '[/' . $this_close . ']';
		else
			$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . ']' . $replace . '[/' . $this_close . ']';
	}

	foreach ($replaces as $k => $v)
	{
		if ($k == $v)
			unset($replaces[$k]);
	}

	if (!empty($replaces))
		$message = strtr($message, $replaces);
}

/**
 * This function sends an email to the specified recipient(s).
 * It uses the mail_type settings and webmaster_email variable.
 *
 * @param array $to The email(s) to send to
 * @param string $subject Email subject, expected to have entities, and slashes, but not be parsed
 * @param string $message Email body, expected to have slashes, no htmlentities
 * @param string $from The address to use for replies
 * @param string $message_id If specified, it will be used as local part of the Message-ID header.
 * @param bool $send_html Whether or not the message is HTML vs. plain text
 * @param int $priority The priority of the message
 * @param bool $hotmail_fix Whether to apply the "hotmail fix"
 * @param bool $is_private Whether this is private
 * @return boolean Whether ot not the email was sent properly.
 */
function sendmail($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false)
{
	global $webmaster_email, $context, $modSettings, $txt, $scripturl;

	// Use sendmail if it's set or if no SMTP server is set.
	$use_sendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '';

	// Line breaks need to be \r\n only in windows or for SMTP.
	$line_break = $context['server']['is_windows'] || !$use_sendmail ? "\r\n" : "\n";

	// So far so good.
	$mail_result = true;

	// If the recipient list isn't an array, make it one.
	$to_array = is_array($to) ? $to : array($to);

	// Make sure we actually have email addresses to send this to
	foreach ($to_array as $k => $v)
	{
		// This should never happen, but better safe than sorry
		if (trim($v) == '')
		{
			unset($to_array[$k]);
		}
	}

	// Nothing left? Nothing else to do
	if (empty($to_array))
		return true;

	// Once upon a time, Hotmail could not interpret non-ASCII mails.
	// In honour of those days, it's still called the 'hotmail fix'.
	if ($hotmail_fix === null)
	{
		$hotmail_to = array();
		foreach ($to_array as $i => $to_address)
		{
			if (preg_match('~@(att|comcast|bellsouth)\.[a-zA-Z\.]{2,6}$~i', $to_address) === 1)
			{
				$hotmail_to[] = $to_address;
				$to_array = array_diff($to_array, array($to_address));
			}
		}

		// Call this function recursively for the hotmail addresses.
		if (!empty($hotmail_to))
			$mail_result = sendmail($hotmail_to, $subject, $message, $from, $message_id, $send_html, $priority, true, $is_private);

		// The remaining addresses no longer need the fix.
		$hotmail_fix = false;

		// No other addresses left? Return instantly.
		if (empty($to_array))
			return $mail_result;
	}

	// Get rid of entities.
	$subject = un_htmlspecialchars($subject);
	// Make the message use the proper line breaks.
	$message = str_replace(array("\r", "\n"), array('', $line_break), $message);

	// Make sure hotmail mails are sent as HTML so that HTML entities work.
	if ($hotmail_fix && !$send_html)
	{
		$send_html = true;
		$message = strtr($message, array($line_break => '<br>' . $line_break));
		$message = preg_replace('~(' . preg_quote($scripturl, '~') . '(?:[?/][\w\-_%\.,\?&;=#]+)?)~', '<a href="$1">$1</a>', $message);
	}

	list (, $from_name) = mimespecialchars(addcslashes($from !== null ? $from : $context['forum_name'], '<>()\'\\"'), true, $hotmail_fix, $line_break);
	list (, $subject) = mimespecialchars($subject, true, $hotmail_fix, $line_break);

	// Construct the mail headers...
	$headers = 'From: ' . $from_name . ' <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>' . $line_break;
	$headers .= $from !== null ? 'Reply-To: <' . $from . '>' . $line_break : '';
	$headers .= 'Return-Path: ' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . $line_break;
	$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . $line_break;

	if ($message_id !== null && empty($modSettings['mail_no_message_id']))
		$headers .= 'Message-ID: <' . md5($scripturl . microtime()) . '-' . $message_id . strstr(empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from'], '@') . '>' . $line_break;
	$headers .= 'X-Mailer: SMF' . $line_break;

	// Pass this to the integration before we start modifying the output -- it'll make it easier later.
	if (in_array(false, call_integration_hook('integrate_outgoing_email', array(&$subject, &$message, &$headers, &$to_array)), true))
		return false;

	// Save the original message...
	$orig_message = $message;

	// The mime boundary separates the different alternative versions.
	$mime_boundary = 'SMF-' . md5($message . time());

	// Using mime, as it allows to send a plain unencoded alternative.
	$headers .= 'Mime-Version: 1.0' . $line_break;
	$headers .= 'content-type: multipart/alternative; boundary="' . $mime_boundary . '"' . $line_break;
	$headers .= 'content-transfer-encoding: 7bit' . $line_break;

	// Sending HTML?  Let's plop in some basic stuff, then.
	if ($send_html)
	{
		$no_html_message = un_htmlspecialchars(strip_tags(strtr($orig_message, array('</title>' => $line_break))));

		// But, then, dump it and use a plain one for dinosaur clients.
		list(, $plain_message) = mimespecialchars($no_html_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
		list($charset, $plain_charset_message, $encoding) = mimespecialchars($no_html_message, false, false, $line_break);
		$message .= 'content-type: text/plain; charset=' . $charset . $line_break;
		$message .= 'content-transfer-encoding: ' . $encoding . $line_break . $line_break;
		$message .= $plain_charset_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the actual HTML message, prim and proper.  If we wanted images, they could be inlined here (with multipart/related, etc.)
		list($charset, $html_message, $encoding) = mimespecialchars($orig_message, false, $hotmail_fix, $line_break);
		$message .= 'content-type: text/html; charset=' . $charset . $line_break;
		$message .= 'content-transfer-encoding: ' . ($encoding == '' ? '7bit' : $encoding) . $line_break . $line_break;
		$message .= $html_message . $line_break . '--' . $mime_boundary . '--';
	}
	// Text is good too.
	else
	{
		// Send a plain message first, for the older web clients.
		list(, $plain_message) = mimespecialchars($orig_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// Now add an encoded message using the forum's character set.
		list ($charset, $encoded_message, $encoding) = mimespecialchars($orig_message, false, false, $line_break);
		$message .= 'content-type: text/plain; charset=' . $charset . $line_break;
		$message .= 'content-transfer-encoding: ' . $encoding . $line_break . $line_break;
		$message .= $encoded_message . $line_break . '--' . $mime_boundary . '--';
	}

	// Are we using the mail queue, if so this is where we butt in...
	if ($priority != 0)
		return AddMailQueue(false, $to_array, $subject, $message, $headers, $send_html, $priority, $is_private);

	// If it's a priority mail, send it now - note though that this should NOT be used for sending many at once.
	elseif (!empty($modSettings['mail_limit']))
	{
		list ($last_mail_time, $mails_this_minute) = @explode('|', $modSettings['mail_recent']);
		if (empty($mails_this_minute) || time() > $last_mail_time + 60)
			$new_queue_stat = time() . '|' . 1;
		else
			$new_queue_stat = $last_mail_time . '|' . ((int) $mails_this_minute + 1);

		updateSettings(array('mail_recent' => $new_queue_stat));
	}

	// SMTP or sendmail?
	if ($use_sendmail)
	{
		$subject = strtr($subject, array("\r" => '', "\n" => ''));
		if (!empty($modSettings['mail_strip_carriage']))
		{
			$message = strtr($message, array("\r" => ''));
			$headers = strtr($headers, array("\r" => ''));
		}

		foreach ($to_array as $to)
		{
			set_error_handler(function($errno, $errstr, $errfile, $errline)
			{
				// error was suppressed with the @-operator
				if (0 === error_reporting())
				{
					return false;
				}

				throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
			}
			);
			try
			{
				if (!mail(strtr($to, array("\r" => '', "\n" => '')), $subject, $message, $headers))
				{
					log_error(sprintf($txt['mail_send_unable'], $to));
					$mail_result = false;
				}
			}
			catch (ErrorException $e)
			{
				log_error($e->getMessage(), 'general', $e->getFile(), $e->getLine());
				log_error(sprintf($txt['mail_send_unable'], $to));
				$mail_result = false;
			}
			restore_error_handler();

			// Wait, wait, I'm still sending here!
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
	}
	else
		$mail_result = $mail_result && smtp_mail($to_array, $subject, $message, $headers);

	// Everything go smoothly?
	return $mail_result;
}

/**
 * Add an email to the mail queue.
 *
 * @param bool $flush Whether to flush the queue
 * @param array $to_array An array of recipients
 * @param string $subject The subject of the message
 * @param string $message The message
 * @param string $headers The headers
 * @param bool $send_html Whether to send in HTML format
 * @param int $priority The priority
 * @param bool $is_private Whether this is private
 * @return boolean Whether the message was added
 */
function AddMailQueue($flush = false, $to_array = array(), $subject = '', $message = '', $headers = '', $send_html = false, $priority = 3, $is_private = false)
{
	global $context, $smcFunc;

	static $cur_insert = array();
	static $cur_insert_len = 0;

	if ($cur_insert_len == 0)
		$cur_insert = array();

	// If we're flushing, make the final inserts - also if we're near the MySQL length limit!
	if (($flush || $cur_insert_len > 800000) && !empty($cur_insert))
	{
		// Only do these once.
		$cur_insert_len = 0;

		// Dump the data...
		$smcFunc['db_insert']('',
			'{db_prefix}mail_queue',
			array(
				'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
				'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
			),
			$cur_insert,
			array('id_mail')
		);

		$cur_insert = array();
		$context['flush_mail'] = false;
	}

	// If we're flushing we're done.
	if ($flush)
	{
		$nextSendTime = time() + 10;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:nextSendTime}
			WHERE variable = {literal:mail_next_send}
				AND value = {string:no_outstanding}',
			array(
				'nextSendTime' => $nextSendTime,
				'no_outstanding' => '0',
			)
		);

		return true;
	}

	// Ensure we tell obExit to flush.
	$context['flush_mail'] = true;

	foreach ($to_array as $to)
	{
		// Will this insert go over MySQL's limit?
		$this_insert_len = strlen($to) + strlen($message) + strlen($headers) + 700;

		// Insert limit of 1M (just under the safety) is reached?
		if ($this_insert_len + $cur_insert_len > 1000000)
		{
			// Flush out what we have so far.
			$smcFunc['db_insert']('',
				'{db_prefix}mail_queue',
				array(
					'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string', 'subject' => 'string-255',
					'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
				),
				$cur_insert,
				array('id_mail')
			);

			// Clear this out.
			$cur_insert = array();
			$cur_insert_len = 0;
		}

		// Now add the current insert to the array...
		$cur_insert[] = array(time(), (string) $to, (string) $message, (string) $subject, (string) $headers, ($send_html ? 1 : 0), $priority, (int) $is_private);
		$cur_insert_len += $this_insert_len;
	}

	// If they are using SSI there is a good chance obExit will never be called.  So lets be nice and flush it for them.
	if (SMF === 'SSI' || SMF === 'BACKGROUND')
		return AddMailQueue(true);

	return true;
}

/**
 * Sends an personal message from the specified person to the specified people
 * ($from defaults to the user)
 *
 * @param array $recipients An array containing the arrays 'to' and 'bcc', both containing id_member's.
 * @param string $subject Should have no slashes and no html entities
 * @param string $message Should have no slashes and no html entities
 * @param bool $store_outbox Whether to store it in the sender's outbox
 * @param array $from An array with the id, name, and username of the member.
 * @param int $pm_head The ID of the chain being replied to - if any.
 * @return array An array with log entries telling how many recipients were successful and which recipients it failed to send to.
 */
function sendpm($recipients, $subject, $message, $store_outbox = false, $from = null, $pm_head = 0)
{
	global $scripturl, $txt, $user_info, $language, $sourcedir;
	global $modSettings, $smcFunc;

	// Make sure the PM language file is loaded, we might need something out of it.
	loadLanguage('PersonalMessage');

	// Initialize log array.
	$log = array(
		'failed' => array(),
		'sent' => array()
	);

	if ($from === null)
		$from = array(
			'id' => $user_info['id'],
			'name' => $user_info['name'],
			'username' => $user_info['username']
		);

	// This is the one that will go in their inbox.
	$htmlmessage = $smcFunc['htmlspecialchars']($message, ENT_QUOTES);
	preparsecode($htmlmessage);
	$htmlsubject = strtr($smcFunc['htmlspecialchars']($subject), array("\r" => '', "\n" => '', "\t" => ''));
	if ($smcFunc['strlen']($htmlsubject) > 100)
		$htmlsubject = $smcFunc['substr']($htmlsubject, 0, 100);

	// Make sure is an array
	if (!is_array($recipients))
		$recipients = array($recipients);

	// Integrated PMs
	call_integration_hook('integrate_personal_message', array(&$recipients, &$from, &$subject, &$message));

	// Get a list of usernames and convert them to IDs.
	$usernames = array();
	foreach ($recipients as $rec_type => $rec)
	{
		foreach ($rec as $id => $member)
		{
			if (!is_numeric($recipients[$rec_type][$id]))
			{
				$recipients[$rec_type][$id] = $smcFunc['strtolower'](trim(preg_replace('~[<>&"\'=\\\]~', '', $recipients[$rec_type][$id])));
				$usernames[$recipients[$rec_type][$id]] = 0;
			}
		}
	}
	if (!empty($usernames))
	{
		$request = $smcFunc['db_query']('pm_find_username', '
			SELECT id_member, member_name
			FROM {db_prefix}members
			WHERE ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(member_name)' : 'member_name') . ' IN ({array_string:usernames})',
			array(
				'usernames' => array_keys($usernames),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (isset($usernames[$smcFunc['strtolower']($row['member_name'])]))
				$usernames[$smcFunc['strtolower']($row['member_name'])] = $row['id_member'];
		$smcFunc['db_free_result']($request);

		// Replace the usernames with IDs. Drop usernames that couldn't be found.
		foreach ($recipients as $rec_type => $rec)
			foreach ($rec as $id => $member)
			{
				if (is_numeric($recipients[$rec_type][$id]))
					continue;

				if (!empty($usernames[$member]))
					$recipients[$rec_type][$id] = $usernames[$member];
				else
				{
					$log['failed'][$id] = sprintf($txt['pm_error_user_not_found'], $recipients[$rec_type][$id]);
					unset($recipients[$rec_type][$id]);
				}
			}
	}

	// Make sure there are no duplicate 'to' members.
	$recipients['to'] = array_unique($recipients['to']);

	// Only 'bcc' members that aren't already in 'to'.
	$recipients['bcc'] = array_diff(array_unique($recipients['bcc']), $recipients['to']);

	// Combine 'to' and 'bcc' recipients.
	$all_to = array_merge($recipients['to'], $recipients['bcc']);

	// Check no-one will want it deleted right away!
	$request = $smcFunc['db_query']('', '
		SELECT
			id_member, criteria, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member IN ({array_int:to_members})
			AND delete_pm = {int:delete_pm}',
		array(
			'to_members' => $all_to,
			'delete_pm' => 1,
		)
	);
	$deletes = array();
	// Check whether we have to apply anything...
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$criteria = $smcFunc['json_decode']($row['criteria'], true);
		// Note we don't check the buddy status, cause deletion from buddy = madness!
		$delete = false;
		foreach ($criteria as $criterium)
		{
			if (($criterium['t'] == 'mid' && $criterium['v'] == $from['id']) || ($criterium['t'] == 'gid' && in_array($criterium['v'], $user_info['groups'])) || ($criterium['t'] == 'sub' && strpos($subject, $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($message, $criterium['v']) !== false))
				$delete = true;
			// If we're adding and one criteria don't match then we stop!
			elseif (!$row['is_or'])
			{
				$delete = false;
				break;
			}
		}
		if ($delete)
			$deletes[$row['id_member']] = 1;
	}
	$smcFunc['db_free_result']($request);

	// Load the membergrounp message limits.
	// @todo Consider caching this?
	static $message_limit_cache = array();
	if (!allowedTo('moderate_forum') && empty($message_limit_cache))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group, max_messages
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$message_limit_cache[$row['id_group']] = $row['max_messages'];
		$smcFunc['db_free_result']($request);
	}

	// Load the groups that are allowed to read PMs.
	require_once($sourcedir . '/Subs-Members.php');
	$pmReadGroups = groupsAllowedTo('pm_read');

	if (empty($modSettings['permission_enable_deny']))
		$pmReadGroups['denied'] = array();

	// Load their alert preferences
	require_once($sourcedir . '/Subs-Notify.php');
	$notifyPrefs = getNotifyPrefs($all_to, array('pm_new', 'pm_reply', 'pm_notify'), true);

	$request = $smcFunc['db_query']('', '
		SELECT
			member_name, real_name, id_member, email_address, lngfile,
			instant_messages,' . (allowedTo('moderate_forum') ? ' 0' : '
			(pm_receive_from = {int:admins_only}' . (empty($modSettings['enable_buddylist']) ? '' : ' OR
			(pm_receive_from = {int:buddies_only} AND FIND_IN_SET({string:from_id}, buddy_list) = 0) OR
			(pm_receive_from = {int:not_on_ignore_list} AND FIND_IN_SET({string:from_id}, pm_ignore_list) != 0)') . ')') . ' AS ignored,
			FIND_IN_SET({string:from_id}, buddy_list) != 0 AS is_buddy, is_activated,
			additional_groups, id_group, id_post_group
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:recipients})
		ORDER BY lngfile
		LIMIT {int:count_recipients}',
		array(
			'not_on_ignore_list' => 1,
			'buddies_only' => 2,
			'admins_only' => 3,
			'recipients' => $all_to,
			'count_recipients' => count($all_to),
			'from_id' => $from['id'],
		)
	);
	$notifications = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Don't do anything for members to be deleted!
		if (isset($deletes[$row['id_member']]))
			continue;

		// Load the preferences for this member (if any)
		$prefs = !empty($notifyPrefs[$row['id_member']]) ? $notifyPrefs[$row['id_member']] : array();
		$prefs = array_merge(array(
			'pm_new' => 0,
			'pm_reply' => 0,
			'pm_notify' => 0,
		), $prefs);

		// We need to know this members groups.
		$groups = explode(',', $row['additional_groups']);
		$groups[] = $row['id_group'];
		$groups[] = $row['id_post_group'];

		$message_limit = -1;
		// For each group see whether they've gone over their limit - assuming they're not an admin.
		if (!in_array(1, $groups))
		{
			foreach ($groups as $id)
			{
				if (isset($message_limit_cache[$id]) && $message_limit != 0 && $message_limit < $message_limit_cache[$id])
					$message_limit = $message_limit_cache[$id];
			}

			if ($message_limit > 0 && $message_limit <= $row['instant_messages'])
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_data_limit_reached'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}

			// Do they have any of the allowed groups?
			if (count(array_intersect($pmReadGroups['allowed'], $groups)) == 0 || count(array_intersect($pmReadGroups['denied'], $groups)) != 0)
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}
		}

		// Note that PostgreSQL can return a lowercase t/f for FIND_IN_SET
		if (!empty($row['ignored']) && $row['ignored'] != 'f' && $row['id_member'] != $from['id'])
		{
			$log['failed'][$row['id_member']] = sprintf($txt['pm_error_ignored_by_user'], $row['real_name']);
			unset($all_to[array_search($row['id_member'], $all_to)]);
			continue;
		}

		// If the receiving account is banned (>=10) or pending deletion (4), refuse to send the PM.
		if ($row['is_activated'] >= 10 || ($row['is_activated'] == 4 && !$user_info['is_admin']))
		{
			$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
			unset($all_to[array_search($row['id_member'], $all_to)]);
			continue;
		}

		// Send a notification, if enabled - taking the buddy list into account.
		if (!empty($row['email_address'])
			&& ((empty($pm_head) && $prefs['pm_new'] & 0x02) || (!empty($pm_head) && $prefs['pm_reply'] & 0x02))
			&& ($prefs['pm_notify'] <= 1 || ($prefs['pm_notify'] > 1 && (!empty($modSettings['enable_buddylist']) && $row['is_buddy']))) && $row['is_activated'] == 1)
		{
			$notifications[empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']][] = $row['email_address'];
		}

		$log['sent'][$row['id_member']] = sprintf(isset($txt['pm_successfully_sent']) ? $txt['pm_successfully_sent'] : '', $row['real_name']);
	}
	$smcFunc['db_free_result']($request);

	// Only 'send' the message if there are any recipients left.
	if (empty($all_to))
		return $log;

	// Insert the message itself and then grab the last insert id.
	$id_pm = $smcFunc['db_insert']('',
		'{db_prefix}personal_messages',
		array(
			'id_pm_head' => 'int', 'id_member_from' => 'int', 'deleted_by_sender' => 'int',
			'from_name' => 'string-255', 'msgtime' => 'int', 'subject' => 'string-255', 'body' => 'string-65534',
		),
		array(
			$pm_head, $from['id'], ($store_outbox ? 0 : 1),
			$from['username'], time(), $htmlsubject, $htmlmessage,
		),
		array('id_pm'),
		1
	);

	// Add the recipients.
	if (!empty($id_pm))
	{
		// If this is new we need to set it part of it's own conversation.
		if (empty($pm_head))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}personal_messages
				SET id_pm_head = {int:id_pm_head}
				WHERE id_pm = {int:id_pm_head}',
				array(
					'id_pm_head' => $id_pm,
				)
			);

		// Some people think manually deleting personal_messages is fun... it's not. We protect against it though :)
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}',
			array(
				'id_pm' => $id_pm,
			)
		);

		$insertRows = array();
		$to_list = array();
		foreach ($all_to as $to)
		{
			$insertRows[] = array($id_pm, $to, in_array($to, $recipients['bcc']) ? 1 : 0, isset($deletes[$to]) ? 1 : 0, 1);
			if (!in_array($to, $recipients['bcc']))
				$to_list[] = $to;
		}

		$smcFunc['db_insert']('insert',
			'{db_prefix}pm_recipients',
			array(
				'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'deleted' => 'int', 'is_new' => 'int'
			),
			$insertRows,
			array('id_pm', 'id_member')
		);
	}

	censorText($subject);
	if (empty($modSettings['disallow_sendBody']))
	{
		censorText($message);
		$message = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($smcFunc['htmlspecialchars']($message), false), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
	}
	else
		$message = '';

	$to_names = array();
	if (count($to_list) > 1)
	{
		$request = $smcFunc['db_query']('', '
			SELECT real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:to_members})',
			array(
				'to_members' => $to_list,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$to_names[] = un_htmlspecialchars($row['real_name']);
		$smcFunc['db_free_result']($request);
	}
	$replacements = array(
		'SUBJECT' => $subject,
		'MESSAGE' => $message,
		'SENDER' => un_htmlspecialchars($from['name']),
		'READLINK' => $scripturl . '?action=pm;pmsg=' . $id_pm . '#msg' . $id_pm,
		'REPLYLINK' => $scripturl . '?action=pm;sa=send;f=inbox;pmsg=' . $id_pm . ';quote;u=' . $from['id'],
		'TOLIST' => implode(', ', $to_names),
	);
	$email_template = 'new_pm' . (empty($modSettings['disallow_sendBody']) ? '_body' : '') . (!empty($to_names) ? '_tolist' : '');

	foreach ($notifications as $lang => $notification_list)
	{
		$emaildata = loadEmailTemplate($email_template, $replacements, $lang);

		// Off the notification email goes!
		sendmail($notification_list, $emaildata['subject'], $emaildata['body'], null, 'p' . $id_pm, $emaildata['is_html'], 2, null, true);
	}

	// Integrated After PMs
	call_integration_hook('integrate_personal_message_after', array(&$id_pm, &$log, &$recipients, &$from, &$subject, &$message));

	// Back to what we were on before!
	loadLanguage('index+PersonalMessage');

	// Add one to their unread and read message counts.
	foreach ($all_to as $k => $id)
		if (isset($deletes[$id]))
			unset($all_to[$k]);
	if (!empty($all_to))
		updateMemberData($all_to, array('instant_messages' => '+', 'unread_messages' => '+', 'new_pm' => 1));

	return $log;
}

/**
 * Prepare text strings for sending as email body or header.
 * In case there are higher ASCII characters in the given string, this
 * function will attempt the transport method 'quoted-printable'.
 * Otherwise the transport method '7bit' is used.
 *
 * @param string $string The string
 * @param bool $with_charset Whether we're specifying a charset ($custom_charset must be set here)
 * @param bool $hotmail_fix Whether to apply the hotmail fix  (all higher ASCII characters are converted to HTML entities to assure proper display of the mail)
 * @param string $line_break The linebreak
 * @param string $custom_charset If set, it uses this character set
 * @return array An array containing the character set, the converted string and the transport method.
 */
function mimespecialchars($string, $with_charset = true, $hotmail_fix = false, $line_break = "\r\n", $custom_charset = null)
{
	global $context;

	$charset = $custom_charset !== null ? $custom_charset : $context['character_set'];

	// This is the fun part....
	if (preg_match_all('~&#(\d{3,8});~', $string, $matches) !== 0 && !$hotmail_fix)
	{
		// Let's, for now, assume there are only &#021;'ish characters.
		$simple = true;

		foreach ($matches[1] as $entity)
			if ($entity > 128)
				$simple = false;
		unset($matches);

		if ($simple)
			$string = preg_replace_callback('~&#(\d{3,8});~', function($m)
			{
				return chr("$m[1]");
			}, $string);
		else
		{
			// Try to convert the string to UTF-8.
			if (!$context['utf8'] && function_exists('iconv'))
			{
				$newstring = @iconv($context['character_set'], 'UTF-8', $string);
				if ($newstring)
					$string = $newstring;
			}

			$string = preg_replace_callback('~&#(\d{3,8});~', 'fixchar__callback', $string);

			// Unicode, baby.
			$charset = 'UTF-8';
		}
	}

	// Convert all special characters to HTML entities...just for Hotmail :-\
	if ($hotmail_fix && ($context['utf8'] || function_exists('iconv') || $context['character_set'] === 'ISO-8859-1'))
	{
		if (!$context['utf8'] && function_exists('iconv'))
		{
			$newstring = @iconv($context['character_set'], 'UTF-8', $string);
			if ($newstring)
				$string = $newstring;
		}

		$entityConvert = function($m)
		{
			$c = $m[1];
			if (strlen($c) === 1 && ord($c[0]) <= 0x7F)
				return $c;
			elseif (strlen($c) === 2 && ord($c[0]) >= 0xC0 && ord($c[0]) <= 0xDF)
				return "&#" . (((ord($c[0]) ^ 0xC0) << 6) + (ord($c[1]) ^ 0x80)) . ";";
			elseif (strlen($c) === 3 && ord($c[0]) >= 0xE0 && ord($c[0]) <= 0xEF)
				return "&#" . (((ord($c[0]) ^ 0xE0) << 12) + ((ord($c[1]) ^ 0x80) << 6) + (ord($c[2]) ^ 0x80)) . ";";
			elseif (strlen($c) === 4 && ord($c[0]) >= 0xF0 && ord($c[0]) <= 0xF7)
				return "&#" . (((ord($c[0]) ^ 0xF0) << 18) + ((ord($c[1]) ^ 0x80) << 12) + ((ord($c[2]) ^ 0x80) << 6) + (ord($c[3]) ^ 0x80)) . ";";
			else
				return "";
		};

		// Convert all 'special' characters to HTML entities.
		return array($charset, preg_replace_callback('~([\x80-\x{10FFFF}])~u', $entityConvert, $string), '7bit');
	}

	// We don't need to mess with the subject line if no special characters were in it..
	elseif (!$hotmail_fix && preg_match('~([^\x09\x0A\x0D\x20-\x7F])~', $string) === 1)
	{
		// Base64 encode.
		$string = base64_encode($string);

		// Show the characterset and the transfer-encoding for header strings.
		if ($with_charset)
			$string = '=?' . $charset . '?B?' . $string . '?=';

		// Break it up in lines (mail body).
		else
			$string = chunk_split($string, 76, $line_break);

		return array($charset, $string, 'base64');
	}

	else
		return array($charset, $string, '7bit');
}

/**
 * Sends mail, like mail() but over SMTP.
 * It expects no slashes or entities.
 *
 * @internal
 *
 * @param array $mail_to_array Array of strings (email addresses)
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $headers Email headers
 * @return boolean Whether it sent or not.
 */
function smtp_mail($mail_to_array, $subject, $message, $headers)
{
	global $modSettings, $webmaster_email, $txt, $boardurl;

	static $helo;

	$modSettings['smtp_host'] = trim($modSettings['smtp_host']);

	// Try POP3 before SMTP?
	// @todo There's no interface for this yet.
	if ($modSettings['mail_type'] == 3 && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
	{
		$socket = fsockopen($modSettings['smtp_host'], 110, $errno, $errstr, 2);
		if (!$socket && (substr($modSettings['smtp_host'], 0, 5) == 'smtp.' || substr($modSettings['smtp_host'], 0, 11) == 'ssl://smtp.'))
			$socket = fsockopen(strtr($modSettings['smtp_host'], array('smtp.' => 'pop.')), 110, $errno, $errstr, 2);

		if ($socket)
		{
			fgets($socket, 256);
			fputs($socket, 'USER ' . $modSettings['smtp_username'] . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'PASS ' . base64_decode($modSettings['smtp_password']) . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'QUIT' . "\r\n");

			fclose($socket);
		}
	}

	// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
	if (!$socket = fsockopen($modSettings['smtp_host'], empty($modSettings['smtp_port']) ? 25 : $modSettings['smtp_port'], $errno, $errstr, 3))
	{
		// Maybe we can still save this?  The port might be wrong.
		if (substr($modSettings['smtp_host'], 0, 4) == 'ssl:' && (empty($modSettings['smtp_port']) || $modSettings['smtp_port'] == 25))
		{
			// ssl:hostname can cause fsocketopen to fail with a lookup failure, ensure it exists for this test.
			if (substr($modSettings['smtp_host'], 0, 6) != 'ssl://')
				$modSettings['smtp_host'] = str_replace('ssl:', 'ss://', $modSettings['smtp_host']);

			if ($socket = fsockopen($modSettings['smtp_host'], 465, $errno, $errstr, 3))
				log_error($txt['smtp_port_ssl']);
		}

		// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
		if (!$socket)
		{
			log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
			return false;
		}
	}

	// Wait for a response of 220, without "-" continuer.
	if (!server_parse(null, $socket, '220'))
		return false;

	// Try to determine the server's fully qualified domain name
	// Can't rely on $_SERVER['SERVER_NAME'] because it can be spoofed on Apache
	if (empty($helo))
	{
		// See if we can get the domain name from the host itself
		if (function_exists('gethostname'))
			$helo = gethostname();
		elseif (function_exists('php_uname'))
			$helo = php_uname('n');

		// If the hostname isn't a fully qualified domain name, we can use the host name from $boardurl instead
		if (empty($helo) || strpos($helo, '.') === false || substr_compare($helo, '.local', -6) === 0 || (!empty($modSettings['tld_regex']) && !preg_match('/\.' . $modSettings['tld_regex'] . '$/u', $helo)))
			$helo = parse_url($boardurl, PHP_URL_HOST);

		// This is one of those situations where 'www.' is undesirable
		if (strpos($helo, 'www.') === 0)
			$helo = substr($helo, 4);
	}

	// SMTP = 1, SMTP - STARTTLS = 2
	if (in_array($modSettings['mail_type'], array(1, 2)) && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
	{
		// EHLO could be understood to mean encrypted hello...
		if (server_parse('EHLO ' . $helo, $socket, null, $response) == '250')
		{
			// Are we using STARTTLS and does the server support STARTTLS?
			if ($modSettings['mail_type'] == 2 && preg_match("~250( |-)STARTTLS~mi", $response))
			{
				// Send STARTTLS to enable encryption
				if (!server_parse('STARTTLS', $socket, '220'))
					return false;
				// Enable the encryption
				// php 5.6+ fix
				$crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

				if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT'))
				{
					$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
					$crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
				}

				if (!@stream_socket_enable_crypto($socket, true, $crypto_method))
					return false;
				// Send the EHLO command again
				if (!server_parse('EHLO ' . $helo, $socket, null) == '250')
					return false;
			}

			if (!server_parse('AUTH LOGIN', $socket, '334'))
				return false;
			// Send the username and password, encoded.
			if (!server_parse(base64_encode($modSettings['smtp_username']), $socket, '334'))
				return false;
			// The password is already encoded ;)
			if (!server_parse($modSettings['smtp_password'], $socket, '235'))
				return false;
		}
		elseif (!server_parse('HELO ' . $helo, $socket, '250'))
			return false;
	}
	else
	{
		// Just say "helo".
		if (!server_parse('HELO ' . $helo, $socket, '250'))
			return false;
	}

	// Fix the message for any lines beginning with a period! (the first is ignored, you see.)
	$message = strtr($message, array("\r\n" . '.' => "\r\n" . '..'));

	// !! Theoretically, we should be able to just loop the RCPT TO.
	$mail_to_array = array_values($mail_to_array);
	foreach ($mail_to_array as $i => $mail_to)
	{
		// Reset the connection to send another email.
		if ($i != 0)
		{
			if (!server_parse('RSET', $socket, '250'))
				return false;
		}

		// From, to, and then start the data...
		if (!server_parse('MAIL FROM: <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>', $socket, '250'))
			return false;
		if (!server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
			return false;
		if (!server_parse('DATA', $socket, '354'))
			return false;
		fputs($socket, 'Subject: ' . $subject . "\r\n");
		if (strlen($mail_to) > 0)
			fputs($socket, 'To: <' . $mail_to . '>' . "\r\n");
		fputs($socket, $headers . "\r\n\r\n");
		fputs($socket, $message . "\r\n");

		// Send a ., or in other words "end of data".
		if (!server_parse('.', $socket, '250'))
			return false;

		// Almost done, almost done... don't stop me just yet!
		@set_time_limit(300);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();
	}
	fputs($socket, 'QUIT' . "\r\n");
	fclose($socket);

	return true;
}

/**
 * Parse a message to the SMTP server.
 * Sends the specified message to the server, and checks for the
 * expected response.
 *
 * @internal
 *
 * @param string $message The message to send
 * @param resource $socket Socket to send on
 * @param string $code The expected response code
 * @param string $response The response from the SMTP server
 * @return bool Whether it responded as such.
 */
function server_parse($message, $socket, $code, &$response = null)
{
	global $txt;

	if ($message !== null)
		fputs($socket, $message . "\r\n");

	// No response yet.
	$server_response = '';

	while (substr($server_response, 3, 1) != ' ')
	{
		if (!($server_response = fgets($socket, 256)))
		{
			// @todo Change this message to reflect that it may mean bad user/password/server issues/etc.
			log_error($txt['smtp_bad_response']);
			return false;
		}
		$response .= $server_response;
	}

	if ($code === null)
		return substr($server_response, 0, 3);

	if (substr($server_response, 0, 3) != $code)
	{
		log_error($txt['smtp_error'] . $server_response);
		return false;
	}

	return true;
}

/**
 * Spell checks the post for typos ;).
 * It uses the pspell or enchant library, one of which MUST be installed.
 * It has problems with internationalization.
 * It is accessed via ?action=spellcheck.
 */
function SpellCheck()
{
	global $txt, $context, $smcFunc;

	// A list of "words" we know about but pspell doesn't.
	$known_words = array('smf', 'php', 'mysql', 'www', 'gif', 'jpeg', 'png', 'http', 'smfisawesome', 'grandia', 'terranigma', 'rpgs');

	loadLanguage('Post');
	loadTemplate('Post');

	// Create a pspell or enchant dictionary resource
	$dict = spell_init();

	if (!isset($_POST['spellstring']) || !$dict)
		die;

	// Construct a bit of Javascript code.
	$context['spell_js'] = '
		var txt = {"done": "' . $txt['spellcheck_done'] . '"};
		var mispstr = window.opener.spellCheckGetText(spell_fieldname);
		var misps = Array(';

	// Get all the words (Javascript already separated them).
	$alphas = explode("\n", strtr($_POST['spellstring'], array("\r" => '')));

	$found_words = false;
	for ($i = 0, $n = count($alphas); $i < $n; $i++)
	{
		// Words are sent like 'word|offset_begin|offset_end'.
		$check_word = explode('|', $alphas[$i]);

		// If the word is a known word, or spelled right...
		if (in_array($smcFunc['strtolower']($check_word[0]), $known_words) || spell_check($dict, $check_word[0]) || !isset($check_word[2]))
			continue;

		// Find the word, and move up the "last occurrence" to here.
		$found_words = true;

		// Add on the javascript for this misspelling.
		$context['spell_js'] .= '
			new misp("' . strtr($check_word[0], array('\\' => '\\\\', '"' => '\\"', '<' => '', '&gt;' => '')) . '", ' . (int) $check_word[1] . ', ' . (int) $check_word[2] . ', [';

		// If there are suggestions, add them in...
		$suggestions = spell_suggest($dict, $check_word[0]);
		if (!empty($suggestions))
		{
			// But first check they aren't going to be censored - no naughty words!
			foreach ($suggestions as $k => $word)
				if ($suggestions[$k] != censorText($word))
					unset($suggestions[$k]);

			if (!empty($suggestions))
				$context['spell_js'] .= '"' . implode('", "', $suggestions) . '"';
		}

		$context['spell_js'] .= ']),';
	}

	// If words were found, take off the last comma.
	if ($found_words)
		$context['spell_js'] = substr($context['spell_js'], 0, -1);

	$context['spell_js'] .= '
		);';

	// And instruct the template system to just show the spellcheck sub template.
	$context['template_layers'] = array();
	$context['sub_template'] = 'spellcheck';

	// Free resources for enchant...
	if (isset($context['enchant_broker']))
	{
		enchant_broker_free_dict($dict);
		enchant_broker_free($context['enchant_broker']);
	}
}

/**
 * Sends a notification to members who have elected to receive emails
 * when things happen to a topic, such as replies are posted.
 * The function automatically finds the subject and its board, and
 * checks permissions for each member who is "signed up" for notifications.
 * It will not send 'reply' notifications more than once in a row.
 *
 * @param array $topics Represents the topics the action is happening to.
 * @param string $type Can be any of reply, sticky, lock, unlock, remove, move, merge, and split.  An appropriate message will be sent for each.
 * @param array $exclude Members in the exclude array will not be processed for the topic with the same key.
 * @param array $members_only Are the only ones that will be sent the notification if they have it on.
 * @uses Post language file
 */
function sendNotifications($topics, $type, $exclude = array(), $members_only = array())
{
	global $user_info, $smcFunc;

	// Can't do it if there's no topics.
	if (empty($topics))
		return;
	// It must be an array - it must!
	if (!is_array($topics))
		$topics = array($topics);

	// Get the subject and body...
	$result = $smcFunc['db_query']('', '
		SELECT mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic, t.id_board,
			COALESCE(mem.real_name, ml.poster_name) AS poster_name, mf.id_msg
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
		WHERE t.id_topic IN ({array_int:topic_list})
		LIMIT 1',
		array(
			'topic_list' => $topics,
		)
	);
	$task_rows = array();
	while ($row = $smcFunc['db_fetch_assoc']($result))
	{
		// Clean it up.
		censorText($row['subject']);
		censorText($row['body']);
		$row['subject'] = un_htmlspecialchars($row['subject']);
		$row['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($row['body'], false, $row['id_last_msg']), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

		$task_rows[] = array(
			'$sourcedir/tasks/CreatePost-Notify.php', 'CreatePost_Notify_Background', $smcFunc['json_encode'](array(
				'msgOptions' => array(
					'id' => $row['id_msg'],
					'subject' => $row['subject'],
					'body' => $row['body'],
				),
				'topicOptions' => array(
					'id' => $row['id_topic'],
					'board' => $row['id_board'],
				),
				// Kinda cheeky, but for any action the originator is usually the current user
				'posterOptions' => array(
					'id' => $user_info['id'],
					'name' => $user_info['name'],
				),
				'type' => $type,
				'members_only' => $members_only,
			)), 0
		);
	}
	$smcFunc['db_free_result']($result);

	if (!empty($task_rows))
		$smcFunc['db_insert']('',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			$task_rows,
			array('id_task')
		);
}

/**
 * Create a post, either as new topic (id_topic = 0) or in an existing one.
 * The input parameters of this function assume:
 * - Strings have been escaped.
 * - Integers have been cast to integer.
 * - Mandatory parameters are set.
 *
 * @param array $msgOptions An array of information/options for the post
 * @param array $topicOptions An array of information/options for the topic
 * @param array $posterOptions An array of information/options for the poster
 * @return bool Whether the operation was a success
 */
function createPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $txt, $modSettings, $smcFunc, $sourcedir;

	require_once($sourcedir . '/Mentions.php');

	// Set optional parameters to the default value.
	$msgOptions['icon'] = empty($msgOptions['icon']) ? 'xx' : $msgOptions['icon'];
	$msgOptions['smileys_enabled'] = !empty($msgOptions['smileys_enabled']);
	$msgOptions['attachments'] = empty($msgOptions['attachments']) ? array() : $msgOptions['attachments'];
	$msgOptions['approved'] = isset($msgOptions['approved']) ? (int) $msgOptions['approved'] : 1;
	$topicOptions['id'] = empty($topicOptions['id']) ? 0 : (int) $topicOptions['id'];
	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;
	$topicOptions['redirect_expires'] = isset($topicOptions['redirect_expires']) ? $topicOptions['redirect_expires'] : null;
	$topicOptions['redirect_topic'] = isset($topicOptions['redirect_topic']) ? $topicOptions['redirect_topic'] : null;
	$posterOptions['id'] = empty($posterOptions['id']) ? 0 : (int) $posterOptions['id'];
	$posterOptions['ip'] = empty($posterOptions['ip']) ? $user_info['ip'] : $posterOptions['ip'];

	// Not exactly a post option but it allows hooks and/or other sources to skip sending notifications if they don't want to
	$msgOptions['send_notifications'] = isset($msgOptions['send_notifications']) ? (bool) $msgOptions['send_notifications'] : true;

	// We need to know if the topic is approved. If we're told that's great - if not find out.
	if (!$modSettings['postmod_active'])
		$topicOptions['is_approved'] = true;
	elseif (!empty($topicOptions['id']) && !isset($topicOptions['is_approved']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT approved
			FROM {db_prefix}topics
			WHERE id_topic = {int:id_topic}
			LIMIT 1',
			array(
				'id_topic' => $topicOptions['id'],
			)
		);
		list ($topicOptions['is_approved']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// If nothing was filled in as name/e-mail address, try the member table.
	if (!isset($posterOptions['name']) || $posterOptions['name'] == '' || (empty($posterOptions['email']) && !empty($posterOptions['id'])))
	{
		if (empty($posterOptions['id']))
		{
			$posterOptions['id'] = 0;
			$posterOptions['name'] = $txt['guest_title'];
			$posterOptions['email'] = '';
		}
		elseif ($posterOptions['id'] != $user_info['id'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT member_name, email_address
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $posterOptions['id'],
				)
			);
			// Couldn't find the current poster?
			if ($smcFunc['db_num_rows']($request) == 0)
			{
				trigger_error('createPost(): Invalid member id ' . $posterOptions['id'], E_USER_NOTICE);
				$posterOptions['id'] = 0;
				$posterOptions['name'] = $txt['guest_title'];
				$posterOptions['email'] = '';
			}
			else
				list ($posterOptions['name'], $posterOptions['email']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$posterOptions['name'] = $user_info['name'];
			$posterOptions['email'] = $user_info['email'];
		}
	}

	if (!empty($modSettings['enable_mentions']))
	{
		$msgOptions['mentioned_members'] = Mentions::getMentionedMembers($msgOptions['body']);
		if (!empty($msgOptions['mentioned_members']))
			$msgOptions['body'] = Mentions::getBody($msgOptions['body'], $msgOptions['mentioned_members']);
	}

	// It's do or die time: forget any user aborts!
	$previous_ignore_user_abort = ignore_user_abort(true);

	$new_topic = empty($topicOptions['id']);

	$message_columns = array(
		'id_board' => 'int', 'id_topic' => 'int', 'id_member' => 'int', 'subject' => 'string-255', 'body' => (!empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] > 65534 ? 'string-' . $modSettings['max_messageLength'] : (empty($modSettings['max_messageLength']) ? 'string' : 'string-65534')),
		'poster_name' => 'string-255', 'poster_email' => 'string-255', 'poster_time' => 'int', 'poster_ip' => 'inet',
		'smileys_enabled' => 'int', 'modified_name' => 'string', 'icon' => 'string-16', 'approved' => 'int',
	);

	$message_parameters = array(
		$topicOptions['board'], $topicOptions['id'], $posterOptions['id'], $msgOptions['subject'], $msgOptions['body'],
		$posterOptions['name'], $posterOptions['email'], time(), $posterOptions['ip'],
		$msgOptions['smileys_enabled'] ? 1 : 0, '', $msgOptions['icon'], $msgOptions['approved'],
	);

	// What if we want to do anything with posts?
	call_integration_hook('integrate_create_post', array(&$msgOptions, &$topicOptions, &$posterOptions, &$message_columns, &$message_parameters));

	// Insert the post.
	$msgOptions['id'] = $smcFunc['db_insert']('',
		'{db_prefix}messages',
		$message_columns,
		$message_parameters,
		array('id_msg'),
		1
	);

	// Something went wrong creating the message...
	if (empty($msgOptions['id']))
		return false;

	// Fix the attachments.
	if (!empty($msgOptions['attachments']))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}attachments
			SET id_msg = {int:id_msg}
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $msgOptions['attachments'],
				'id_msg' => $msgOptions['id'],
			)
		);

	// What if we want to export new posts out to a CMS?
	call_integration_hook('integrate_after_create_post', array($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters));

	// Insert a new topic (if the topicID was left empty.)
	if ($new_topic)
	{
		$topic_columns = array(
			'id_board' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int', 'id_first_msg' => 'int',
			'id_last_msg' => 'int', 'locked' => 'int', 'is_sticky' => 'int', 'num_views' => 'int',
			'id_poll' => 'int', 'unapproved_posts' => 'int', 'approved' => 'int',
			'redirect_expires' => 'int', 'id_redirect_topic' => 'int',
		);
		$topic_parameters = array(
			$topicOptions['board'], $posterOptions['id'], $posterOptions['id'], $msgOptions['id'],
			$msgOptions['id'], $topicOptions['lock_mode'] === null ? 0 : $topicOptions['lock_mode'], $topicOptions['sticky_mode'] === null ? 0 : $topicOptions['sticky_mode'], 0,
			$topicOptions['poll'] === null ? 0 : $topicOptions['poll'], $msgOptions['approved'] ? 0 : 1, $msgOptions['approved'],
			$topicOptions['redirect_expires'] === null ? 0 : $topicOptions['redirect_expires'], $topicOptions['redirect_topic'] === null ? 0 : $topicOptions['redirect_topic'],
		);

		call_integration_hook('integrate_before_create_topic', array(&$msgOptions, &$topicOptions, &$posterOptions, &$topic_columns, &$topic_parameters));

		$topicOptions['id'] = $smcFunc['db_insert']('',
			'{db_prefix}topics',
			$topic_columns,
			$topic_parameters,
			array('id_topic'),
			1
		);

		// The topic couldn't be created for some reason.
		if (empty($topicOptions['id']))
		{
			// We should delete the post that did work, though...
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);

			return false;
		}

		// Fix the message with the topic.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET id_topic = {int:id_topic}
			WHERE id_msg = {int:id_msg}',
			array(
				'id_topic' => $topicOptions['id'],
				'id_msg' => $msgOptions['id'],
			)
		);

		// There's been a new topic AND a new post today.
		trackStats(array('topics' => '+', 'posts' => '+'));

		updateStats('topic', true);
		updateStats('subject', $topicOptions['id'], $msgOptions['subject']);

		// What if we want to export new topics out to a CMS?
		call_integration_hook('integrate_create_topic', array(&$msgOptions, &$topicOptions, &$posterOptions));
	}
	// The topic already exists, it only needs a little updating.
	else
	{
		$update_parameters = array(
			'poster_id' => $posterOptions['id'],
			'id_msg' => $msgOptions['id'],
			'locked' => $topicOptions['lock_mode'],
			'is_sticky' => $topicOptions['sticky_mode'],
			'id_topic' => $topicOptions['id'],
			'counter_increment' => 1,
		);
		if ($msgOptions['approved'])
			$topics_columns = array(
				'id_member_updated = {int:poster_id}',
				'id_last_msg = {int:id_msg}',
				'num_replies = num_replies + {int:counter_increment}',
			);
		else
			$topics_columns = array(
				'unapproved_posts = unapproved_posts + {int:counter_increment}',
			);
		if ($topicOptions['lock_mode'] !== null)
			$topics_columns[] = 'locked = {int:locked}';
		if ($topicOptions['sticky_mode'] !== null)
			$topics_columns[] = 'is_sticky = {int:is_sticky}';

		call_integration_hook('integrate_modify_topic', array(&$topics_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions));

		// Update the number of replies and the lock/sticky status.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET
				' . implode(', ', $topics_columns) . '
			WHERE id_topic = {int:id_topic}',
			$update_parameters
		);

		// One new post has been added today.
		trackStats(array('posts' => '+'));
	}

	// Creating is modifying...in a way.
	// @todo Why not set id_msg_modified on the insert?
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_msg_modified = {int:id_msg}
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $msgOptions['id'],
		)
	);

	// Increase the number of posts and topics on the board.
	if ($msgOptions['approved'])
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1' . ($new_topic ? ', num_topics = num_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);
	else
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET unapproved_posts = unapproved_posts + 1' . ($new_topic ? ', unapproved_topics = unapproved_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);

		// Add to the approval queue too.
		$smcFunc['db_insert']('',
			'{db_prefix}approval_queue',
			array(
				'id_msg' => 'int',
			),
			array(
				$msgOptions['id'],
			),
			array()
		);

		$smcFunc['db_insert']('',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array(
				'$sourcedir/tasks/ApprovePost-Notify.php', 'ApprovePost_Notify_Background', $smcFunc['json_encode'](array(
					'msgOptions' => $msgOptions,
					'topicOptions' => $topicOptions,
					'posterOptions' => $posterOptions,
					'type' => $new_topic ? 'topic' : 'post',
				)), 0
			),
			array('id_task')
		);
	}

	// Mark inserted topic as read (only for the user calling this function).
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before replying, let's try an UPDATE first.
		if (!$new_topic)
		{
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}log_topics
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_topic = {int:id_topic}',
				array(
					'current_member' => $posterOptions['id'],
					'id_msg' => $msgOptions['id'],
					'id_topic' => $topicOptions['id'],
				)
			);

			$flag = $smcFunc['db_affected_rows']() != 0;
		}

		if (empty($flag))
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
				array($topicOptions['id'], $posterOptions['id'], $msgOptions['id']),
				array('id_topic', 'id_member')
			);
		}
	}

	if ($msgOptions['approved'] && empty($topicOptions['is_approved']) && $posterOptions['id'] != $user_info['id'])
		$smcFunc['db_insert']('',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array(
				'$sourcedir/tasks/ApproveReply-Notify.php', 'ApproveReply_Notify_Background', $smcFunc['json_encode'](array(
					'msgOptions' => $msgOptions,
					'topicOptions' => $topicOptions,
					'posterOptions' => $posterOptions,
				)), 0
			),
			array('id_task')
		);

	// If there's a custom search index, it may need updating...
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'postCreated')))
		$searchAPI->postCreated($msgOptions, $topicOptions, $posterOptions);

	// Increase the post counter for the user that created the post.
	if (!empty($posterOptions['update_post_count']) && !empty($posterOptions['id']) && $msgOptions['approved'])
	{
		// Are you the one that happened to create this post?
		if ($user_info['id'] == $posterOptions['id'])
			$user_info['posts']++;
		updateMemberData($posterOptions['id'], array('posts' => '+'));
	}

	// They've posted, so they can make the view count go up one if they really want. (this is to keep views >= replies...)
	$_SESSION['last_read_topic'] = 0;

	// Better safe than sorry.
	if (isset($_SESSION['topicseen_cache'][$topicOptions['board']]))
		$_SESSION['topicseen_cache'][$topicOptions['board']]--;

	// Update all the stats so everyone knows about this new topic and message.
	updateStats('message', true, $msgOptions['id']);

	// Update the last message on the board assuming it's approved AND the topic is.
	if ($msgOptions['approved'])
		updateLastMessages($topicOptions['board'], $new_topic || !empty($topicOptions['is_approved']) ? $msgOptions['id'] : 0);

	// Queue createPost background notification
	if ($msgOptions['send_notifications'] && $msgOptions['approved'])
		$smcFunc['db_insert']('',
			'{db_prefix}background_tasks',
			array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/CreatePost-Notify.php', 'CreatePost_Notify_Background', $smcFunc['json_encode'](array(
				'msgOptions' => $msgOptions,
				'topicOptions' => $topicOptions,
				'posterOptions' => $posterOptions,
				'type' => $new_topic ? 'topic' : 'reply',
			)), 0),
			array('id_task')
		);

	// Alright, done now... we can abort now, I guess... at least this much is done.
	ignore_user_abort($previous_ignore_user_abort);

	// Success.
	return true;
}

/**
 * Modifying a post...
 *
 * @param array &$msgOptions An array of information/options for the post
 * @param array &$topicOptions An array of information/options for the topic
 * @param array &$posterOptions An array of information/options for the poster
 * @return bool Whether the post was modified successfully
 */
function modifyPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $modSettings, $smcFunc, $sourcedir;

	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;

	// This is longer than it has to be, but makes it so we only set/change what we have to.
	$messages_columns = array();
	if (isset($posterOptions['name']))
		$messages_columns['poster_name'] = $posterOptions['name'];
	if (isset($posterOptions['email']))
		$messages_columns['poster_email'] = $posterOptions['email'];
	if (isset($msgOptions['icon']))
		$messages_columns['icon'] = $msgOptions['icon'];
	if (isset($msgOptions['subject']))
		$messages_columns['subject'] = $msgOptions['subject'];
	if (isset($msgOptions['body']))
	{
		$messages_columns['body'] = $msgOptions['body'];

		// using a custom search index, then lets get the old message so we can update our index as needed
		if (!empty($modSettings['search_custom_index_config']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT body
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);
			list ($msgOptions['old_body']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}
	}
	if (!empty($msgOptions['modify_time']))
	{
		$messages_columns['modified_time'] = $msgOptions['modify_time'];
		$messages_columns['modified_name'] = $msgOptions['modify_name'];
		$messages_columns['modified_reason'] = $msgOptions['modify_reason'];
		$messages_columns['id_msg_modified'] = $modSettings['maxMsgID'];
	}
	if (isset($msgOptions['smileys_enabled']))
		$messages_columns['smileys_enabled'] = empty($msgOptions['smileys_enabled']) ? 0 : 1;

	// Which columns need to be ints?
	$messageInts = array('modified_time', 'id_msg_modified', 'smileys_enabled');
	$update_parameters = array(
		'id_msg' => $msgOptions['id'],
	);

	// Update search api
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if ($searchAPI->supportsMethod('postRemoved'))
		$searchAPI->postRemoved($msgOptions['id']);

	if (!empty($modSettings['enable_mentions']) && isset($msgOptions['body']))
	{
		require_once($sourcedir . '/Mentions.php');

		$oldmentions = array();

		if (!empty($msgOptions['old_body']))
		{
			preg_match_all('/\[member\=([0-9]+)\]([^\[]*)\[\/member\]/U', $msgOptions['old_body'], $match);

			if (isset($match[1]) && isset($match[2]) && is_array($match[1]) && is_array($match[2]))
				foreach ($match[1] as $i => $oldID)
					$oldmentions[$oldID] = array('id' => $oldID, 'real_name' => $match[2][$i]);

			if (empty($modSettings['search_custom_index_config']))
				unset($msgOptions['old_body']);
		}

		$mentions = Mentions::getMentionedMembers($msgOptions['body']);
		$messages_columns['body'] = $msgOptions['body'] = Mentions::getBody($msgOptions['body'], $mentions);

		// Remove the poster.
		if (isset($mentions[$user_info['id']]))
			unset($mentions[$user_info['id']]);

		if (isset($oldmentions[$user_info['id']]))
			unset($oldmentions[$user_info['id']]);

		if (is_array($mentions) && is_array($oldmentions) && count(array_diff_key($mentions, $oldmentions)) > 0 && count($mentions) > count($oldmentions))
		{
			// Queue this for notification.
			$msgOptions['mentioned_members'] = array_diff_key($mentions, $oldmentions);

			$smcFunc['db_insert']('',
				'{db_prefix}background_tasks',
				array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/CreatePost-Notify.php', 'CreatePost_Notify_Background', $smcFunc['json_encode'](array(
					'msgOptions' => $msgOptions,
					'topicOptions' => $topicOptions,
					'posterOptions' => $posterOptions,
					'type' => 'edit',
				)), 0),
				array('id_task')
			);
		}
	}

	call_integration_hook('integrate_modify_post', array(&$messages_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions, &$messageInts));

	foreach ($messages_columns as $var => $val)
	{
		$messages_columns[$var] = $var . ' = {' . (in_array($var, $messageInts) ? 'int' : 'string') . ':var_' . $var . '}';
		$update_parameters['var_' . $var] = $val;
	}

	// Nothing to do?
	if (empty($messages_columns))
		return true;

	// Change the post.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET ' . implode(', ', $messages_columns) . '
		WHERE id_msg = {int:id_msg}',
		$update_parameters
	);

	// Lock and or sticky the post.
	if ($topicOptions['sticky_mode'] !== null || $topicOptions['lock_mode'] !== null || $topicOptions['poll'] !== null)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET
				is_sticky = {raw:is_sticky},
				locked = {raw:locked},
				id_poll = {raw:id_poll}
			WHERE id_topic = {int:id_topic}',
			array(
				'is_sticky' => $topicOptions['sticky_mode'] === null ? 'is_sticky' : (int) $topicOptions['sticky_mode'],
				'locked' => $topicOptions['lock_mode'] === null ? 'locked' : (int) $topicOptions['lock_mode'],
				'id_poll' => $topicOptions['poll'] === null ? 'id_poll' : (int) $topicOptions['poll'],
				'id_topic' => $topicOptions['id'],
			)
		);
	}

	// Mark the edited post as read.
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before editing, let's try an UPDATE first.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_topics
			SET id_msg = {int:id_msg}
			WHERE id_member = {int:current_member}
				AND id_topic = {int:id_topic}',
			array(
				'current_member' => $user_info['id'],
				'id_msg' => $modSettings['maxMsgID'],
				'id_topic' => $topicOptions['id'],
			)
		);

		$flag = $smcFunc['db_affected_rows']() != 0;

		if (empty($flag))
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
				array($topicOptions['id'], $user_info['id'], $modSettings['maxMsgID']),
				array('id_topic', 'id_member')
			);
		}
	}

	// If there's a custom search index, it needs to be modified...
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'postModified')))
		$searchAPI->postModified($msgOptions, $topicOptions, $posterOptions);

	if (isset($msgOptions['subject']))
	{
		// Only update the subject if this was the first message in the topic.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_first_msg = {int:id_first_msg}
			LIMIT 1',
			array(
				'id_first_msg' => $msgOptions['id'],
			)
		);
		if ($smcFunc['db_num_rows']($request) == 1)
			updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
		$smcFunc['db_free_result']($request);
	}

	// Finally, if we are setting the approved state we need to do much more work :(
	if ($modSettings['postmod_active'] && isset($msgOptions['approved']))
		approvePosts($msgOptions['id'], $msgOptions['approved']);

	return true;
}

/**
 * Approve (or not) some posts... without permission checks...
 *
 * @param array $msgs Array of message ids
 * @param bool $approve Whether to approve the posts (if false, posts are unapproved)
 * @param bool $notify Whether to notify users
 * @return bool Whether the operation was successful
 */
function approvePosts($msgs, $approve = true, $notify = true)
{
	global $smcFunc;

	if (!is_array($msgs))
		$msgs = array($msgs);

	if (empty($msgs))
		return false;

	// May as well start at the beginning, working out *what* we need to change.
	$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.approved, m.id_topic, m.id_board, t.id_first_msg, t.id_last_msg,
			m.body, m.subject, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.id_member,
			t.approved AS topic_approved, b.count_posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			AND m.approved = {int:approved_state}',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 0 : 1,
		)
	);
	$msgs = array();
	$topics = array();
	$topic_changes = array();
	$board_changes = array();
	$notification_topics = array();
	$notification_posts = array();
	$member_post_changes = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Easy...
		$msgs[] = $row['id_msg'];
		$topics[] = $row['id_topic'];

		// Ensure our change array exists already.
		if (!isset($topic_changes[$row['id_topic']]))
			$topic_changes[$row['id_topic']] = array(
				'id_last_msg' => $row['id_last_msg'],
				'approved' => $row['topic_approved'],
				'replies' => 0,
				'unapproved_posts' => 0,
			);
		if (!isset($board_changes[$row['id_board']]))
			$board_changes[$row['id_board']] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
			);

		// If it's the first message then the topic state changes!
		if ($row['id_msg'] == $row['id_first_msg'])
		{
			$topic_changes[$row['id_topic']]['approved'] = $approve ? 1 : 0;

			$board_changes[$row['id_board']]['unapproved_topics'] += $approve ? -1 : 1;
			$board_changes[$row['id_board']]['topics'] += $approve ? 1 : -1;

			// Note we need to ensure we announce this topic!
			$notification_topics[] = array(
				'body' => $row['body'],
				'subject' => $row['subject'],
				'name' => $row['poster_name'],
				'board' => $row['id_board'],
				'topic' => $row['id_topic'],
				'msg' => $row['id_first_msg'],
				'poster' => $row['id_member'],
				'new_topic' => true,
			);
		}
		else
		{
			$topic_changes[$row['id_topic']]['replies'] += $approve ? 1 : -1;

			// This will be a post... but don't notify unless it's not followed by approved ones.
			if ($row['id_msg'] > $row['id_last_msg'])
				$notification_posts[$row['id_topic']] = array(
					'id' => $row['id_msg'],
					'body' => $row['body'],
					'subject' => $row['subject'],
					'name' => $row['poster_name'],
					'topic' => $row['id_topic'],
					'board' => $row['id_board'],
					'poster' => $row['id_member'],
					'new_topic' => false,
					'msg' => $row['id_msg'],
				);
		}

		// If this is being approved and id_msg is higher than the current id_last_msg then it changes.
		if ($approve && $row['id_msg'] > $topic_changes[$row['id_topic']]['id_last_msg'])
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_msg'];
		// If this is being unapproved, and it's equal to the id_last_msg we need to find a new one!
		elseif (!$approve)
			// Default to the first message and then we'll override in a bit ;)
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_first_msg'];

		$topic_changes[$row['id_topic']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['posts'] += $approve ? 1 : -1;

		// Post count for the user?
		if ($row['id_member'] && empty($row['count_posts']))
			$member_post_changes[$row['id_member']] = isset($member_post_changes[$row['id_member']]) ? $member_post_changes[$row['id_member']] + 1 : 1;
	}
	$smcFunc['db_free_result']($request);

	if (empty($msgs))
		return;

	// Now we have the differences make the changes, first the easy one.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET approved = {int:approved_state}
		WHERE id_msg IN ({array_int:message_list})',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 1 : 0,
		)
	);

	// If we were unapproving find the last msg in the topics...
	if (!$approve)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, MAX(id_msg) AS id_last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND approved = {int:approved}
			GROUP BY id_topic',
			array(
				'topic_list' => $topics,
				'approved' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
		$smcFunc['db_free_result']($request);
	}

	// ... next the topics...
	foreach ($topic_changes as $id => $changes)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET approved = {int:approved}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_replies = num_replies + {int:num_replies}, id_last_msg = {int:id_last_msg}
			WHERE id_topic = {int:id_topic}',
			array(
				'approved' => $changes['approved'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_replies' => $changes['replies'],
				'id_last_msg' => $changes['id_last_msg'],
				'id_topic' => $id,
			)
		);

	// ... finally the boards...
	foreach ($board_changes as $id => $changes)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + {int:num_posts}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_topics = num_topics + {int:num_topics}, unapproved_topics = unapproved_topics + {int:unapproved_topics}
			WHERE id_board = {int:id_board}',
			array(
				'num_posts' => $changes['posts'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_topics' => $changes['topics'],
				'unapproved_topics' => $changes['unapproved_topics'],
				'id_board' => $id,
			)
		);

	// Finally, least importantly, notifications!
	if ($approve)
	{
		$task_rows = array();
		foreach (array_merge($notification_topics, $notification_posts) as $topic)
			$task_rows[] = array(
				'$sourcedir/tasks/CreatePost-Notify.php', 'CreatePost_Notify_Background', $smcFunc['json_encode'](array(
					'msgOptions' => array(
						'id' => $topic['msg'],
						'body' => $topic['body'],
						'subject' => $topic['subject'],
					),
					'topicOptions' => array(
						'id' => $topic['topic'],
						'board' => $topic['board'],
					),
					'posterOptions' => array(
						'id' => $topic['poster'],
						'name' => $topic['name'],
					),
					'type' => $topic['new_topic'] ? 'topic' : 'reply',
				)), 0
			);

		if ($notify)
			$smcFunc['db_insert']('',
				'{db_prefix}background_tasks',
				array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
				$task_rows,
				array('id_task')
			);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}approval_queue
			WHERE id_msg IN ({array_int:message_list})
				AND id_attach = {int:id_attach}',
			array(
				'message_list' => $msgs,
				'id_attach' => 0,
			)
		);
	}
	// If unapproving add to the approval queue!
	else
	{
		$msgInserts = array();
		foreach ($msgs as $msg)
			$msgInserts[] = array($msg);

		$smcFunc['db_insert']('ignore',
			'{db_prefix}approval_queue',
			array('id_msg' => 'int'),
			$msgInserts,
			array('id_msg')
		);
	}

	// Update the last messages on the boards...
	updateLastMessages(array_keys($board_changes));

	// Post count for the members?
	if (!empty($member_post_changes))
		foreach ($member_post_changes as $id_member => $count_change)
			updateMemberData($id_member, array('posts' => 'posts ' . ($approve ? '+' : '-') . ' ' . $count_change));

	return true;
}

/**
 * Approve topics?
 *
 * @todo shouldn't this be in topic
 *
 * @param array $topics Array of topic ids
 * @param bool $approve Whether to approve the topics. If false, unapproves them instead
 * @return bool Whether the operation was successful
 */
function approveTopics($topics, $approve = true)
{
	global $smcFunc;

	if (!is_array($topics))
		$topics = array($topics);

	if (empty($topics))
		return false;

	$approve_type = $approve ? 0 : 1;

	// Just get the messages to be approved and pass through...
	$request = $smcFunc['db_query']('', '
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topic_list})
			AND approved = {int:approve_type}',
		array(
			'topic_list' => $topics,
			'approve_type' => $approve_type,
		)
	);
	$msgs = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$msgs[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	return approvePosts($msgs, $approve);
}

/**
 * Takes an array of board IDs and updates their last messages.
 * If the board has a parent, that parent board is also automatically
 * updated.
 * The columns updated are id_last_msg and last_updated.
 * Note that id_last_msg should always be updated using this function,
 * and is not automatically updated upon other changes.
 *
 * @param array $setboards An array of board IDs
 * @param int $id_msg The ID of the message
 * @return void|false Returns false if $setboards is empty for some reason
 */
function updateLastMessages($setboards, $id_msg = 0)
{
	global $board_info, $board, $smcFunc;

	// Please - let's be sane.
	if (empty($setboards))
		return false;

	if (!is_array($setboards))
		$setboards = array($setboards);

	// If we don't know the id_msg we need to find it.
	if (!$id_msg)
	{
		// Find the latest message on this board (highest id_msg.)
		$request = $smcFunc['db_query']('', '
			SELECT id_board, MAX(id_last_msg) AS id_msg
			FROM {db_prefix}topics
			WHERE id_board IN ({array_int:board_list})
				AND approved = {int:approved}
			GROUP BY id_board',
			array(
				'board_list' => $setboards,
				'approved' => 1,
			)
		);
		$lastMsg = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$lastMsg[$row['id_board']] = $row['id_msg'];
		$smcFunc['db_free_result']($request);
	}
	else
	{
		// Just to note - there should only be one board passed if we are doing this.
		foreach ($setboards as $id_board)
			$lastMsg[$id_board] = $id_msg;
	}

	$parent_boards = array();
	// Keep track of last modified dates.
	$lastModified = $lastMsg;
	// Get all the child boards for the parents, if they have some...
	foreach ($setboards as $id_board)
	{
		if (!isset($lastMsg[$id_board]))
		{
			$lastMsg[$id_board] = 0;
			$lastModified[$id_board] = 0;
		}

		if (!empty($board) && $id_board == $board)
			$parents = $board_info['parent_boards'];
		else
			$parents = getBoardParents($id_board);

		// Ignore any parents on the top child level.
		// @todo Why?
		foreach ($parents as $id => $parent)
		{
			if ($parent['level'] != 0)
			{
				// If we're already doing this one as a board, is this a higher last modified?
				if (isset($lastModified[$id]) && $lastModified[$id_board] > $lastModified[$id])
					$lastModified[$id] = $lastModified[$id_board];
				elseif (!isset($lastModified[$id]) && (!isset($parent_boards[$id]) || $parent_boards[$id] < $lastModified[$id_board]))
					$parent_boards[$id] = $lastModified[$id_board];
			}
		}
	}

	// Note to help understand what is happening here. For parents we update the timestamp of the last message for determining
	// whether there are child boards which have not been read. For the boards themselves we update both this and id_last_msg.

	$board_updates = array();
	$parent_updates = array();
	// Finally, to save on queries make the changes...
	foreach ($parent_boards as $id => $msg)
	{
		if (!isset($parent_updates[$msg]))
			$parent_updates[$msg] = array($id);
		else
			$parent_updates[$msg][] = $id;
	}

	foreach ($lastMsg as $id => $msg)
	{
		if (!isset($board_updates[$msg . '-' . $lastModified[$id]]))
			$board_updates[$msg . '-' . $lastModified[$id]] = array(
				'id' => $msg,
				'updated' => $lastModified[$id],
				'boards' => array($id)
			);

		else
			$board_updates[$msg . '-' . $lastModified[$id]]['boards'][] = $id;
	}

	// Now commit the changes!
	foreach ($parent_updates as $id_msg => $boards)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})
				AND id_msg_updated < {int:id_msg_updated}',
			array(
				'board_list' => $boards,
				'id_msg_updated' => $id_msg,
			)
		);
	}
	foreach ($board_updates as $board_data)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})',
			array(
				'board_list' => $board_data['boards'],
				'id_last_msg' => $board_data['id'],
				'id_msg_updated' => $board_data['updated'],
			)
		);
	}
}

/**
 * This simple function gets a list of all administrators and sends them an email
 *  to let them know a new member has joined.
 * Called by registerMember() function in Subs-Members.php.
 * Email is sent to all groups that have the moderate_forum permission.
 * The language set by each member is being used (if available).
 *
 * @param string $type The type. Types supported are 'approval', 'activation', and 'standard'.
 * @param int $memberID The ID of the member
 * @param string $member_name The name of the member (if null, it is pulled from the database)
 * @uses the Login language file.
 */
function adminNotify($type, $memberID, $member_name = null)
{
	global $smcFunc;

	if ($member_name == null)
	{
		// Get the new user's name....
		$request = $smcFunc['db_query']('', '
			SELECT real_name
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $memberID,
			)
		);
		list ($member_name) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// This is really just a wrapper for making a new background task to deal with all the fun.
	$smcFunc['db_insert']('insert',
		'{db_prefix}background_tasks',
		array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/Register-Notify.php', 'Register_Notify_Background', $smcFunc['json_encode'](array(
			'new_member_id' => $memberID,
			'new_member_name' => $member_name,
			'notify_type' => $type,
			'time' => time(),
		)), 0),
		array('id_task')
	);
}

/**
 * Load a template from EmailTemplates language file.
 *
 * @param string $template The name of the template to load
 * @param array $replacements An array of replacements for the variables in the template
 * @param string $lang The language to use, if different than the user's current language
 * @param bool $loadLang Whether to load the language file first
 * @return array An array containing the subject and body of the email template, with replacements made
 */
function loadEmailTemplate($template, $replacements = array(), $lang = '', $loadLang = true)
{
	global $txt, $mbname, $scripturl, $settings;

	// First things first, load up the email templates language file, if we need to.
	if ($loadLang)
		loadLanguage('EmailTemplates', $lang);

	if (!isset($txt[$template . '_subject']) || !isset($txt[$template . '_body']))
		fatal_lang_error('email_no_template', 'template', array($template));

	$ret = array(
		'subject' => $txt[$template . '_subject'],
		'body' => $txt[$template . '_body'],
		'is_html' => !empty($txt[$template . '_html']),
	);

	// Add in the default replacements.
	$replacements += array(
		'FORUMNAME' => $mbname,
		'SCRIPTURL' => $scripturl,
		'THEMEURL' => $settings['theme_url'],
		'IMAGESURL' => $settings['images_url'],
		'DEFAULT_THEMEURL' => $settings['default_theme_url'],
		'REGARDS' => $txt['regards_team'],
	);

	// Split the replacements up into two arrays, for use with str_replace
	$find = array();
	$replace = array();

	foreach ($replacements as $f => $r)
	{
		$find[] = '{' . $f . '}';
		$replace[] = $r;
	}

	// Do the variable replacements.
	$ret['subject'] = str_replace($find, $replace, $ret['subject']);
	$ret['body'] = str_replace($find, $replace, $ret['body']);

	// Now deal with the {USER.variable} items.
	$ret['subject'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['subject']);
	$ret['body'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['body']);

	// Finally return the email to the caller so they can send it out.
	return $ret;
}

/**
 * Callback function for loademaitemplate on subject and body
 * Uses capture group 1 in array
 *
 * @param array $matches An array of matches
 * @return string The match
 */
function user_info_callback($matches)
{
	global $user_info;
	if (empty($matches[1]))
		return '';

	$use_ref = true;
	$ref = &$user_info;

	foreach (explode('.', $matches[1]) as $index)
	{
		if ($use_ref && isset($ref[$index]))
			$ref = &$ref[$index];
		else
		{
			$use_ref = false;
			break;
		}
	}

	return $use_ref ? $ref : $matches[0];
}

/**
 * spell_init()
 *
 * Sets up a dictionary resource handle. Tries enchant first then falls through to pspell.
 *
 * @return resource|bool An enchant or pspell dictionary resource handle or false if the dictionary couldn't be loaded
 */
function spell_init()
{
	global $context, $txt;

	// Check for UTF-8 and strip ".utf8" off the lang_locale string for enchant
	$context['spell_utf8'] = ($txt['lang_character_set'] == 'UTF-8');
	$lang_locale = str_replace('.utf8', '', $txt['lang_locale']);

	// Try enchant first since PSpell is (supposedly) deprecated as of PHP 5.3
	// enchant only does UTF-8, so we need iconv if you aren't using UTF-8
	if (function_exists('enchant_broker_init') && ($context['spell_utf8'] || function_exists('iconv')))
	{
		// We'll need this to free resources later...
		$context['enchant_broker'] = enchant_broker_init();

		// Try locale first, then general...
		if (!empty($lang_locale) && enchant_broker_dict_exists($context['enchant_broker'], $lang_locale))
		{
			$enchant_link = enchant_broker_request_dict($context['enchant_broker'], $lang_locale);
		}
		elseif (enchant_broker_dict_exists($context['enchant_broker'], $txt['lang_dictionary']))
		{
			$enchant_link = enchant_broker_request_dict($context['enchant_broker'], $txt['lang_dictionary']);
		}

		// Success
		if (!empty($enchant_link))
		{
			$context['provider'] = 'enchant';
			return $enchant_link;
		}
		else
		{
			// Free up any resources used...
			@enchant_broker_free($context['enchant_broker']);
		}
	}

	// Fall through to pspell if enchant didn't work
	if (function_exists('pspell_new'))
	{
		// Okay, this looks funny, but it actually fixes a weird bug.
		ob_start();
		$old = error_reporting(0);

		// See, first, some windows machines don't load pspell properly on the first try.  Dumb, but this is a workaround.
		pspell_new('en');

		// Next, the dictionary in question may not exist. So, we try it... but...
		$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', strtr($context['character_set'], array('iso-' => 'iso', 'ISO-' => 'iso')), PSPELL_FAST | PSPELL_RUN_TOGETHER);

		// Most people don't have anything but English installed... So we use English as a last resort.
		if (!$pspell_link)
			$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		error_reporting($old);
		ob_end_clean();

		// If we have pspell, exit now...
		if ($pspell_link)
		{
			$context['provider'] = 'pspell';
			return $pspell_link;
		}
	}

	// If we get this far, we're doomed
	return false;
}

/**
 * spell_check()
 *
 * Determines whether or not the specified word is spelled correctly
 *
 * @param resource $dict An enchant or pspell dictionary resource set up by {@link spell_init()}
 * @param string $word A word to check the spelling of
 * @return bool Whether or not the specified word is spelled properly
 */
function spell_check($dict, $word)
{
	global $context, $txt;

	// Enchant or pspell?
	if ($context['provider'] == 'enchant')
	{
		// This is a bit tricky here...
		if (!$context['spell_utf8'])
		{
			// Convert the word to UTF-8 with iconv
			$word = iconv($txt['lang_character_set'], 'UTF-8', $word);
		}
		return enchant_dict_check($dict, $word);
	}
	elseif ($context['provider'] == 'pspell')
	{
		return pspell_check($dict, $word);
	}
}

/**
 * spell_suggest()
 *
 * Returns an array of suggested replacements for the specified word
 *
 * @param resource $dict An enchant or pspell dictioary resource
 * @param string $word A misspelled word
 * @return array An array of suggested replacements for the misspelled word
 */
function spell_suggest($dict, $word)
{
	global $context, $txt;

	if ($context['provider'] == 'enchant')
	{
		// If we're not using UTF-8, we need iconv to handle some stuff...
		if (!$context['spell_utf8'])
		{
			// Convert the word to UTF-8 before getting suggestions
			$word = iconv($txt['lang_character_set'], 'UTF-8', $word);
			$suggestions = enchant_dict_suggest($dict, $word);

			// Go through the suggestions and convert them back to the proper character set
			foreach ($suggestions as $index => $suggestion)
			{
				// //TRANSLIT makes it use similar-looking characters for incompatible ones...
				$suggestions[$index] = iconv('UTF-8', $txt['lang_character_set'] . '//TRANSLIT', $suggestion);
			}

			return $suggestions;
		}
		else
		{
			return enchant_dict_suggest($dict, $word);
		}
	}
	elseif ($context['provider'] == 'pspell')
	{
		return pspell_suggest($dict, $word);
	}
}

?>