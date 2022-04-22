<?php

/**
 * This file contains the files necessary to display news as an XML feed.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Outputs xml data representing recent information or a profile.
 *
 * Can be passed subactions which decide what is output:
 *  'recent' for recent posts,
 *  'news' for news topics,
 *  'members' for recently registered members,
 *  'profile' for a member's profile.
 *  'posts' for a member's posts.
 *  'personal_messages' for a member's personal messages.
 *
 * When displaying a member's profile or posts, the u parameter identifies which member. Defaults
 * to the current user's id.
 * To display a member's personal messages, the u parameter must match the id of the current user.
 *
 * Outputs can be in RSS 0.92, RSS 2, Atom, RDF, or our own custom XML format. Default is RSS 2.
 *
 * Accessed via ?action=.xml.
 *
 * Does not use any templates, sub templates, or template layers.
 *
 * Uses Stats, Profile, Post, and PersonalMessage language files.
 */
function ShowXmlFeed()
{
	global $board, $board_info, $context, $scripturl, $boardurl, $txt, $modSettings, $user_info;
	global $query_this_board, $smcFunc, $forum_version, $settings, $cache_enable, $cachedir;

	// List all the different types of data they can pull.
	$subActions = array(
		'recent' => 'getXmlRecent',
		'news' => 'getXmlNews',
		'members' => 'getXmlMembers',
		'profile' => 'getXmlProfile',
		'posts' => 'getXmlPosts',
		'personal_messages' => 'getXmlPMs',
	);

	// Easy adding of sub actions
	call_integration_hook('integrate_xmlfeeds', array(&$subActions));

	$subaction = empty($_GET['sa']) || !isset($subActions[$_GET['sa']]) ? 'recent' : $_GET['sa'];

	// Make sure the id is a number and not "I like trying to hack the database".
	$context['xmlnews_uid'] = isset($_GET['u']) ? (int) $_GET['u'] : $user_info['id'];

	// Default to latest 5.  No more than 255, please.
	$context['xmlnews_limit'] = empty($_GET['limit']) || (int) $_GET['limit'] < 1 ? 5 : min((int) $_GET['limit'], 255);

	// Users can always export their own profile data
	if (in_array($subaction, array('profile', 'posts', 'personal_messages')) && !$user_info['is_guest'] && $context['xmlnews_uid'] == $user_info['id'])
		$modSettings['xmlnews_enable'] = true;

	// If it's not enabled, die.
	if (empty($modSettings['xmlnews_enable']))
		obExit(false);

	loadLanguage('Stats');

	// Show in rss or proprietary format?
	$xml_format = $_GET['type'] = isset($_GET['type']) && in_array($_GET['type'], array('smf', 'rss', 'rss2', 'atom', 'rdf')) ? $_GET['type'] : 'rss2';

	// Some general metadata for this feed. We'll change some of these values below.
	$feed_meta = array(
		'title' => '',
		'desc' => sprintf($txt['xml_rss_desc'], $context['forum_name']),
		'author' => $context['forum_name'],
		'source' => $scripturl,
		'rights' => '© ' . date('Y') . ' ' . $context['forum_name'],
		'icon' => !empty($settings['og_image']) ? $settings['og_image'] : $boardurl . '/favicon.ico',
		'language' => !empty($txt['lang_locale']) ? str_replace("_", "-", substr($txt['lang_locale'], 0, strcspn($txt['lang_locale'], "."))) : 'en',
		'self' => $scripturl,
	);
	foreach (array('action', 'sa', 'type', 'board', 'boards', 'c', 'u', 'limit', 'offset') as $var)
		if (isset($_GET[$var]))
			$feed_meta['self'] .= ($feed_meta['self'] === $scripturl ? '?' : ';' ) . $var . '=' . $_GET[$var];

	// Handle the cases where a board, boards, or category is asked for.
	$query_this_board = 1;
	$context['optimize_msg'] = array(
		'highest' => 'm.id_msg <= b.id_last_msg',
	);
	if (!empty($_GET['c']) && empty($board))
	{
		$_GET['c'] = explode(',', $_GET['c']);
		foreach ($_GET['c'] as $i => $c)
			$_GET['c'][$i] = (int) $c;

		if (count($_GET['c']) == 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT name
				FROM {db_prefix}categories
				WHERE id_cat = {int:current_category}',
				array(
					'current_category' => (int) $_GET['c'][0],
				)
			);
			list ($feed_meta['title']) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_cat IN ({array_int:current_category_list})
				AND {query_see_board}',
			array(
				'current_category_list' => $_GET['c'],
			)
		);
		$total_cat_posts = 0;
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$boards[] = $row['id_board'];
			$total_cat_posts += $row['num_posts'];
		}
		$smcFunc['db_free_result']($request);

		if (!empty($boards))
			$query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';

		// Try to limit the number of messages we look through.
		if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 400 - $context['xmlnews_limit'] * 5);
	}
	elseif (!empty($_GET['boards']))
	{
		$_GET['boards'] = explode(',', $_GET['boards']);
		foreach ($_GET['boards'] as $i => $b)
			$_GET['boards'][$i] = (int) $b;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts, b.name
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND {query_see_board}
			LIMIT {int:limit}',
			array(
				'board_list' => $_GET['boards'],
				'limit' => count($_GET['boards']),
			)
		);

		// Either the board specified doesn't exist or you have no access.
		$num_boards = $smcFunc['db_num_rows']($request);
		if ($num_boards == 0)
			fatal_lang_error('no_board', false);

		$total_posts = 0;
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($num_boards == 1)
				$feed_meta['title'] = $row['name'];

			$boards[] = $row['id_board'];
			$total_posts += $row['num_posts'];
		}
		$smcFunc['db_free_result']($request);

		if (!empty($boards))
			$query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';

		// The more boards, the more we're going to look through...
		if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 500 - $context['xmlnews_limit'] * 5);
	}
	elseif (!empty($board))
	{
		$request = $smcFunc['db_query']('', '
			SELECT num_posts
			FROM {db_prefix}boards AS b
			WHERE id_board = {int:current_board}
				AND {query_see_board}
			LIMIT 1',
			array(
				'current_board' => $board,
			)
		);

		if ($smcFunc['db_num_rows']($request) == 0)
			fatal_lang_error('no_board', false);

		list ($total_posts) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$feed_meta['title'] = $board_info['name'];
		$feed_meta['source'] .= '?board=' . $board . '.0';

		$query_this_board = 'b.id_board = ' . $board;

		// Try to look through just a few messages, if at all possible.
		if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 600 - $context['xmlnews_limit'] * 5);
	}
	else
	{
		$query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != ' . $modSettings['recycle_board'] : '');
		$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 100 - $context['xmlnews_limit'] * 5);
	}

	$feed_meta['title'] .= (!empty($feed_meta['title']) ? ' - ' : '') . $context['forum_name'];

	// Sanitize feed metadata values
	foreach ($feed_meta as $mkey => $mvalue)
		$feed_meta[$mkey] = strip_tags($mvalue);

	// We only want some information, not all of it.
	$cachekey = array($xml_format, $_GET['action'], $context['xmlnews_limit'], $subaction);
	foreach (array('board', 'boards', 'c') as $var)
		if (isset($_GET[$var]))
			$cachekey[] = $var . '=' . implode(',', (array) $_GET[$var]);
	$cachekey = md5($smcFunc['json_encode']($cachekey) . (!empty($query_this_board) ? $query_this_board : ''));
	$cache_t = microtime(true);

	// Get the associative array representing the xml.
	if (!empty($cache_enable) && (!$user_info['is_guest'] || $cache_enable >= 3))
	{
		$xml_data = cache_get_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, 240);
	}
	if (empty($xml_data))
	{
		$call = call_helper($subActions[$subaction], true);

		if (!empty($call))
			$xml_data = call_user_func($call, $xml_format);

		if (!empty($cache_enable) && (($user_info['is_guest'] && $cache_enable >= 3)
		|| (!$user_info['is_guest'] && (microtime(true) - $cache_t > 0.2))))
			cache_put_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, $xml_data, 240);
	}

	buildXmlFeed($xml_format, $xml_data, $feed_meta, $subaction);

	// Descriptive filenames = good
	$filename[] = $feed_meta['title'];
	$filename[] = $subaction;
	if (in_array($subaction, array('profile', 'posts', 'personal_messages')))
		$filename[] = 'u=' . $context['xmlnews_uid'];
	if (!empty($boards))
		$filename[] = 'boards=' . implode(',', $boards);
	elseif (!empty($board))
		$filename[] = 'board=' . $board;
	$filename[] = $xml_format;
	$filename = preg_replace($context['utf8'] ? '/[^\p{L}\p{M}\p{N}\-]+/u' : '/[\s_,.\/\\;:\'<>?|\[\]{}~!@#$%^&*()=+`]+/', '_', str_replace('"', '', un_htmlspecialchars(strip_tags(implode('-', $filename)))));

	// This is an xml file....
	ob_end_clean();
	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	if ($xml_format == 'smf' || isset($_GET['debug']))
		header('content-type: text/xml; charset=' . (empty($context['character_set']) ? 'UTF-8' : $context['character_set']));
	elseif ($xml_format == 'rss' || $xml_format == 'rss2')
		header('content-type: application/rss+xml; charset=' . (empty($context['character_set']) ? 'UTF-8' : $context['character_set']));
	elseif ($xml_format == 'atom')
		header('content-type: application/atom+xml; charset=' . (empty($context['character_set']) ? 'UTF-8' : $context['character_set']));
	elseif ($xml_format == 'rdf')
		header('content-type: ' . (isBrowser('ie') ? 'text/xml' : 'application/rdf+xml') . '; charset=' . (empty($context['character_set']) ? 'UTF-8' : $context['character_set']));

	header('content-disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $filename . '.xml"');

	echo implode('', $context['feed']);

	obExit(false);
}

function buildXmlFeed($xml_format, $xml_data, $feed_meta, $subaction)
{
	global $context, $txt, $scripturl;

	/* Important: Do NOT change this to an HTTPS address!
	 *
	 * Why? Because XML namespace names must be both unique and invariant
	 * once defined. They look like URLs merely because that's a convenient
	 * way to ensure uniqueness, but they are not used as URLs. They are
	 * used as case-sensitive identifier strings. If the string changes in
	 * any way, XML processing software (including PHP's own XML functions)
	 * will interpret the two versions of the string as entirely different
	 * namespaces, which could cause it to mangle the XML horrifically
	 * during processing.
	 */
	$smf_ns = 'htt'.'p:/'.'/ww'.'w.simple'.'machines.o'.'rg/xml/' . $subaction;

	// Allow mods to add extra namespaces and tags to the feed/channel
	$namespaces = array(
		'rss' => array(),
		'rss2' => array('atom' => 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/2005/Atom'),
		'atom' => array('' => 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/2005/Atom'),
		'rdf' => array(
			'' => 'htt'.'p:/'.'/purl.o'.'rg/rss/1.0/',
			'rdf' => 'htt'.'p:/'.'/ww'.'w.w3.o'.'rg/1999/02/22-rdf-syntax-ns#',
			'dc' => 'htt'.'p:/'.'/purl.o'.'rg/dc/elements/1.1/',
		),
		'smf' => array(
			'smf' => $smf_ns,
		),
	);
	if (in_array($subaction, array('profile', 'posts', 'personal_messages')))
	{
		$namespaces['rss']['smf'] = $smf_ns;
		$namespaces['rss2']['smf'] = $smf_ns;
		$namespaces['atom']['smf'] = $smf_ns;
	}

	$extraFeedTags = array(
		'rss' => array(),
		'rss2' => array(),
		'atom' => array(),
		'rdf' => array(),
		'smf' => array(),
	);

	// Allow mods to specify any keys that need special handling
	$forceCdataKeys = array();
	$nsKeys = array();

	// Maybe someone needs to insert a DOCTYPE declaration?
	$doctype = '';

	// Remember this, just in case...
	$orig_feed_meta = $feed_meta;

	// If mods want to do somthing with this feed, let them do that now.
	// Provide the feed's data, metadata, namespaces, extra feed-level tags, keys that need special handling, the feed format, and the requested subaction
	call_integration_hook('integrate_xml_data', array(&$xml_data, &$feed_meta, &$namespaces, &$extraFeedTags, &$forceCdataKeys, &$nsKeys, $xml_format, $subaction, &$doctype));

	// These can't be empty
	foreach (array('title', 'desc', 'source', 'self') as $mkey)
		$feed_meta[$mkey] = !empty($feed_meta[$mkey]) ? $feed_meta[$mkey] : $orig_feed_meta[$mkey];

	// Sanitize feed metadata values
	foreach ($feed_meta as $mkey => $mvalue)
		$feed_meta[$mkey] = cdata_parse(fix_possible_url($mvalue));

	$ns_string = '';
	if (!empty($namespaces[$xml_format]))
	{
		foreach ($namespaces[$xml_format] as $nsprefix => $nsurl)
			$ns_string .= ' xmlns' . ($nsprefix !== '' ? ':' : '') . $nsprefix . '="' . $nsurl . '"';
	}

	$i = in_array($xml_format, array('atom', 'smf')) ? 1 : 2;

	$extraFeedTags_string = '';
	if (!empty($extraFeedTags[$xml_format]))
	{
		$indent = str_repeat("\t", $i);
		foreach ($extraFeedTags[$xml_format] as $extraTag)
			$extraFeedTags_string .= "\n" . $indent . $extraTag;
	}

	$context['feed'] = array();

	// First, output the xml header.
	$context['feed']['header'] = '<?xml version="1.0" encoding="' . $context['character_set'] . '"?' . '>' . ($doctype !== '' ? "\n" . trim($doctype) : '');

	// Are we outputting an rss feed or one with more information?
	if ($xml_format == 'rss' || $xml_format == 'rss2')
	{
		// Start with an RSS 2.0 header.
		$context['feed']['header'] .= '
<rss version=' . ($xml_format == 'rss2' ? '"2.0"' : '"0.92"') . ' xml:lang="' . strtr($txt['lang_locale'], '_', '-') . '"' . $ns_string . '>
	<channel>
		<title>' . $feed_meta['title'] . '</title>
		<link>' . $feed_meta['source'] . '</link>
		<description>' . $feed_meta['desc'] . '</description>';

		if (!empty($feed_meta['icon']))
			$context['feed']['header'] .= '
		<image>
			<url>' . $feed_meta['icon'] . '</url>
			<title>' . $feed_meta['title'] . '</title>
			<link>' . $feed_meta['source'] . '</link>
		</image>';

		if (!empty($feed_meta['rights']))
			$context['feed']['header'] .= '
		<copyright>' . $feed_meta['rights'] . '</copyright>';

		if (!empty($feed_meta['language']))
			$context['feed']['header'] .= '
		<language>' . $feed_meta['language'] . '</language>';

		// RSS2 calls for this.
		if ($xml_format == 'rss2')
			$context['feed']['header'] .= '
		<atom:link rel="self" type="application/rss+xml" href="' . $feed_meta['self'] . '" />';

		$context['feed']['header'] .= $extraFeedTags_string;

		// Write the data as an XML string to $context['feed']['items']
		dumpTags($xml_data, $i, $xml_format, $forceCdataKeys, $nsKeys);

		// Output the footer of the xml.
		$context['feed']['footer'] = '
	</channel>
</rss>';
	}
	elseif ($xml_format == 'atom')
	{
		$context['feed']['header'] .= '
<feed' . $ns_string . (!empty($feed_meta['language']) ? ' xml:lang="' . $feed_meta['language'] . '"' : '') . '>
	<title>' . $feed_meta['title'] . '</title>
	<link rel="alternate" type="text/html" href="' . $feed_meta['source'] . '" />
	<link rel="self" type="application/atom+xml" href="' . $feed_meta['self'] . '" />
	<updated>' . smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ') . '</updated>
	<id>' . $feed_meta['source'] . '</id>
	<subtitle>' . $feed_meta['desc'] . '</subtitle>
	<generator uri="https://www.simplemachines.org" version="' . SMF_VERSION . '">SMF</generator>';

		if (!empty($feed_meta['icon']))
			$context['feed']['header'] .= '
	<icon>' . $feed_meta['icon'] . '</icon>';

		if (!empty($feed_meta['author']))
			$context['feed']['header'] .= '
	<author>
		<name>' . $feed_meta['author'] . '</name>
	</author>';

		if (!empty($feed_meta['rights']))
			$context['feed']['header'] .= '
	<rights>' . $feed_meta['rights'] . '</rights>';

		$context['feed']['header'] .= $extraFeedTags_string;

		dumpTags($xml_data, $i, $xml_format, $forceCdataKeys, $nsKeys);

		$context['feed']['footer'] = '
</feed>';
	}
	elseif ($xml_format == 'rdf')
	{
		$context['feed']['header'] .= '
<rdf:RDF' . $ns_string . '>
	<channel rdf:about="' . $scripturl . '">
		<title>' . $feed_meta['title'] . '</title>
		<link>' . $feed_meta['source'] . '</link>
		<description>' . $feed_meta['desc'] . '</description>';

		$context['feed']['header'] .= $extraFeedTags_string;

		$context['feed']['header'] .= '
		<items>
			<rdf:Seq>';

		foreach ($xml_data as $item)
		{
			$link = array_filter(
				$item['content'],
				function($e)
				{
					return ($e['tag'] == 'link');
				}
			);
			$link = array_pop($link);

			$context['feed']['header'] .= '
				<rdf:li rdf:resource="' . $link['content'] . '" />';
		}

		$context['feed']['header'] .= '
			</rdf:Seq>
		</items>
	</channel>';

		dumpTags($xml_data, $i, $xml_format, $forceCdataKeys, $nsKeys);

		$context['feed']['footer'] = '
</rdf:RDF>';
	}
	// Otherwise, we're using our proprietary formats - they give more data, though.
	else
	{
		$context['feed']['header'] .= '
<smf:xml-feed xml:lang="' . strtr($txt['lang_locale'], '_', '-') . '"' . $ns_string . ' version="' . SMF_VERSION . '" forum-name="' . $context['forum_name'] . '" forum-url="' . $scripturl . '"' . (!empty($feed_meta['title']) && $feed_meta['title'] != $context['forum_name'] ? ' title="' . $feed_meta['title'] . '"' : '') . (!empty($feed_meta['desc']) ? ' description="' . $feed_meta['desc'] . '"' : '') . ' source="' . $feed_meta['source'] . '" generated-date-localized="' . strip_tags(timeformat(time(), false, 'forum')) . '" generated-date-UTC="' . smf_gmstrftime('%F %T') . '"' . (!empty($feed_meta['page']) ? ' page="' . $feed_meta['page'] . '"' : '') . '>';

		// Hard to imagine anyone wanting to add these for the proprietary format, but just in case...
		$context['feed']['header'] .= $extraFeedTags_string;

		// Dump out that associative array.  Indent properly.... and use the right names for the base elements.
		dumpTags($xml_data, $i, $xml_format, $forceCdataKeys, $nsKeys);

		$context['feed']['footer'] = '
</smf:xml-feed>';
	}
}

/**
 * Called from dumpTags to convert data to xml
 * Finds urls for local site and sanitizes them
 *
 * @param string $val A string containing a possible URL
 * @return string $val The string with any possible URLs sanitized
 */
function fix_possible_url($val)
{
	global $modSettings, $context, $scripturl;

	if (substr($val, 0, strlen($scripturl)) != $scripturl)
		return $val;

	call_integration_hook('integrate_fix_url', array(&$val));

	if (empty($modSettings['queryless_urls']) || ($context['server']['is_cgi'] && ini_get('cgi.fix_pathinfo') == 0 && @get_cfg_var('cgi.fix_pathinfo') == 0) || (!$context['server']['is_apache'] && !$context['server']['is_lighttpd']))
		return $val;

	$val = preg_replace_callback(
		'~\b' . preg_quote($scripturl, '~') . '\?((?:board|topic)=[^#"]+)(#[^"]*)?$~',
		function($m) use ($scripturl)
		{
			return $scripturl . '/' . strtr("$m[1]", '&;=', '//,') . '.html' . (isset($m[2]) ? $m[2] : "");
		},
		$val
	);
	return $val;
}

/**
 * Ensures supplied data is properly encapsulated in cdata xml tags
 * Called from getXmlProfile in News.php
 *
 * @param string $data XML data
 * @param string $ns A namespace prefix for the XML data elements (used by mods, maybe)
 * @param boolean $force If true, enclose the XML data in cdata tags no matter what (used by mods, maybe)
 * @return string The XML data enclosed in cdata tags when necessary
 */
function cdata_parse($data, $ns = '', $force = false)
{
	global $smcFunc;

	// Do we even need to do this?
	if (strpbrk($data, '<>&') == false && $force !== true)
		return $data;

	$cdata = '<![CDATA[';

	// @todo If we drop the obsolete $ns parameter, this whole loop could be replaced with a simple `str_replace(']]>', ']]]]><[CDATA[>', $data)`

	for ($pos = 0, $n = strlen($data); $pos < $n; null)
	{
		$positions = array(
			strpos($data, ']]>', $pos),
		);
		if ($ns != '')
			$positions[] = strpos($data, '<', $pos);
		foreach ($positions as $k => $dummy)
		{
			if ($dummy === false)
				unset($positions[$k]);
		}

		$old = $pos;
		$pos = empty($positions) ? $n : min($positions);

		if ($pos - $old > 0)
			$cdata .= substr($data, $old, $pos - $old);
		if ($pos >= $n)
			break;

		if (substr($data, $pos, 1) == '<')
		{
			$pos2 = strpos($data, '>', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			if (substr($data, $pos + 1, 1) == '/')
				$cdata .= ']]></' . $ns . ':' . substr($data, $pos + 2, $pos2 - $pos - 1) . '<![CDATA[';
			else
				$cdata .= ']]><' . $ns . ':' . substr($data, $pos + 1, $pos2 - $pos) . '<![CDATA[';
			$pos = $pos2 + 1;
		}
		elseif (substr($data, $pos, 3) == ']]>')
		{
			$cdata .= ']]]]><![CDATA[>';
			$pos = $pos + 3;
		}
	}

	$cdata .= ']]>';

	return strtr($cdata, array('<![CDATA[]]>' => ''));
}

/**
 * Formats data retrieved in other functions into xml format.
 * Additionally formats data based on the specific format passed.
 * This function is recursively called to handle sub arrays of data.
 *
 * @param array $data The array to output as xml data
 * @param int $i The amount of indentation to use.
 * @param string $xml_format The format to use ('atom', 'rss', 'rss2' or empty for plain XML)
 * @param array $forceCdataKeys A list of keys on which to force cdata wrapping (used by mods, maybe)
 * @param array $nsKeys Key-value pairs of namespace prefixes to pass to cdata_parse() (used by mods, maybe)
 */
function dumpTags($data, $i, $xml_format = '', $forceCdataKeys = array(), $nsKeys = array())
{
	global $context;

	if (empty($context['feed']['items']))
		$context['feed']['items'] = '';

	// For every array in the data...
	foreach ($data as $element)
	{
		$key = isset($element['tag']) ? $element['tag'] : null;
		$val = isset($element['content']) ? $element['content'] : null;
		$attrs = isset($element['attributes']) ? $element['attributes'] : null;

		// Skip it, it's been set to null.
		if ($key === null || ($val === null && $attrs === null))
			continue;

		$forceCdata = in_array($key, $forceCdataKeys);
		$ns = !empty($nsKeys[$key]) ? $nsKeys[$key] : '';

		// First let's indent!
		$context['feed']['items'] .= "\n" . str_repeat("\t", $i);

		// Beginning tag.
		$context['feed']['items'] .= '<' . $key;

		if (!empty($attrs))
		{
			foreach ($attrs as $attr_key => $attr_value)
				$context['feed']['items'] .= ' ' . $attr_key . '="' . fix_possible_url($attr_value) . '"';
		}

		// If it's empty, simply output an empty element.
		if (empty($val) && $val !== '0' && $val !== 0)
		{
			$context['feed']['items'] .= ' />';
		}
		else
		{
			$context['feed']['items'] .= '>';

			// The element's value.
			if (is_array($val))
			{
				// An array.  Dump it, and then indent the tag.
				dumpTags($val, $i + 1, $xml_format, $forceCdataKeys, $nsKeys);
				$context['feed']['items'] .= "\n" . str_repeat("\t", $i);
			}
			// A string with returns in it.... show this as a multiline element.
			elseif (strpos($val, "\n") !== false)
				$context['feed']['items'] .= "\n" . (!empty($element['cdata']) || $forceCdata ? cdata_parse(fix_possible_url($val), $ns, $forceCdata) : fix_possible_url($val)) . "\n" . str_repeat("\t", $i);
			// A simple string.
			else
				$context['feed']['items'] .= !empty($element['cdata']) || $forceCdata ? cdata_parse(fix_possible_url($val), $ns, $forceCdata) : fix_possible_url($val);

			// Ending tag.
			$context['feed']['items'] .= '</' . $key . '>';
		}
	}
}

/**
 * Retrieve the list of members from database.
 * The array will be generated to match the format.
 *
 * @todo get the list of members from Subs-Members.
 *
 * @param string $xml_format The format to use. Can be 'atom', 'rdf', 'rss', 'rss2' or 'smf'
 * @param bool $ascending If true, get the earliest members first. Default false.
 * @return array An array of arrays of feed items. Each array has keys corresponding to the appropriate tags for the specified format.
 */
function getXmlMembers($xml_format, $ascending = false)
{
	global $scripturl, $smcFunc, $txt, $context;

	if (!allowedTo('view_mlist'))
		return array();

	loadLanguage('Profile');

	// Find the most (or least) recent members.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, date_registered, last_login
		FROM {db_prefix}members
		ORDER BY id_member {raw:ascdesc}
		LIMIT {int:limit}',
		array(
			'limit' => $context['xmlnews_limit'],
			'ascdesc' => !empty($ascending) ? 'ASC' : 'DESC',
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If any control characters slipped in somehow, kill the evil things
		$row = filter_var($row, FILTER_CALLBACK, array('options' => 'cleanXml'));

		// Create a GUID for each member using the tag URI scheme
		$guid = 'tag:' . parse_iri($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['date_registered']) . ':member=' . $row['id_member'];

		// Make the data look rss-ish.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['real_name'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?action=profile;u=' . $row['id_member'],
					),
					array(
						'tag' => 'comments',
						'content' => $scripturl . '?action=pm;sa=send;u=' . $row['id_member'],
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['date_registered']),
					),
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
				),
			);
		elseif ($xml_format == 'rdf')
			$data[] = array(
				'tag' => 'item',
				'attributes' => array('rdf:about' => $scripturl . '?action=profile;u=' . $row['id_member']),
				'content' => array(
					array(
						'tag' => 'dc:format',
						'content' => 'text/html',
					),
					array(
						'tag' => 'title',
						'content' => $row['real_name'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?action=profile;u=' . $row['id_member'],
					),
				),
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['real_name'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'attributes' => array(
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
						),
					),
					array(
						'tag' => 'published',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['date_registered']),
					),
					array(
						'tag' => 'updated',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['last_login']),
					),
					array(
						'tag' => 'id',
						'content' => $guid,
					),
				),
			);
		// More logical format for the data, but harder to apply.
		else
			$data[] = array(
				'tag' => 'member',
				'attributes' => array('label' => $txt['who_member']),
				'content' => array(
					array(
						'tag' => 'name',
						'attributes' => array('label' => $txt['name']),
						'content' => $row['real_name'],
						'cdata' => true,
					),
					array(
						'tag' => 'time',
						'attributes' => array('label' => $txt['date_registered'], 'UTC' => smf_gmstrftime('%F %T', $row['date_registered'])),
						'content' => $smcFunc['htmlspecialchars'](strip_tags(timeformat($row['date_registered'], false, 'forum'))),
					),
					array(
						'tag' => 'id',
						'content' => $row['id_member'],
					),
					array(
						'tag' => 'link',
						'attributes' => array('label' => $txt['url']),
						'content' => $scripturl . '?action=profile;u=' . $row['id_member'],
					),
				),
			);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the latest topics information from a specific board,
 * to display later.
 * The returned array will be generated to match the xml_format.
 *
 * @param string $xml_format The XML format. Can be 'atom', 'rdf', 'rss', 'rss2' or 'smf'.
 * @param bool $ascending If true, get the oldest topics first. Default false.
 * @return array An array of arrays of topic data for the feed. Each array has keys corresponding to the tags for the specified format.
 */
function getXmlNews($xml_format, $ascending = false)
{
	global $scripturl, $modSettings, $board, $user_info;
	global $query_this_board, $smcFunc, $context, $txt;

	/* Find the latest (or earliest) posts that:
		- are the first post in their topic.
		- are on an any board OR in a specified board.
		- can be seen by this user. */

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = $smcFunc['db_query']('', '
			SELECT
				m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.modified_time,
				m.icon, t.id_topic, t.id_board, t.num_replies,
				b.name AS bname,
				COALESCE(mem.id_member, 0) AS id_member,
				COALESCE(mem.email_address, m.poster_email) AS poster_email,
				COALESCE(mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE ' . $query_this_board . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . (empty($board) ? '' : '
				AND t.id_board = {int:current_board}') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY t.id_first_msg {raw:ascdesc}
			LIMIT {int:limit}',
			array(
				'current_board' => $board,
				'is_approved' => 1,
				'limit' => $context['xmlnews_limit'],
				'optimize_msg' => $optimize_msg,
				'ascdesc' => !empty($ascending) ? 'ASC' : 'DESC',
			)
		);
		// If we don't have $context['xmlnews_limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $smcFunc['db_num_rows']($request) < $context['xmlnews_limit'])
		{
			$smcFunc['db_free_result']($request);
			if (empty($_GET['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = 'm.id_msg >= t.id_first_msg';
			$context['optimize_msg']['highest'] = 'm.id_msg <= t.id_last_msg';
			$loops++;
		}
		else
			$done = true;
	}
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If any control characters slipped in somehow, kill the evil things
		$row = filter_var($row, FILTER_CALLBACK, array('options' => 'cleanXml'));

		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && $smcFunc['strlen'](str_replace('<br>', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr($smcFunc['substr'](str_replace('<br>', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br>')) . '...';

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Do we want to include any attachments?
		if (!empty($modSettings['attachmentEnable']) && !empty($modSettings['xmlnews_attachments']) && allowedTo('view_attachments', $row['id_board']))
		{
			$attach_request = $smcFunc['db_query']('', '
				SELECT
					a.id_attach, a.filename, COALESCE(a.size, 0) AS filesize, a.mime_type, a.downloads, a.approved, m.id_topic AS topic
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.attachment_type = {int:attachment_type}
					AND a.id_msg = {int:message_id}',
				array(
					'message_id' => $row['id_msg'],
					'attachment_type' => 0,
					'is_approved' => 1,
				)
			);
			$loaded_attachments = array();
			while ($attach = $smcFunc['db_fetch_assoc']($attach_request))
			{
				// Include approved attachments only
				if ($attach['approved'])
					$loaded_attachments['attachment_' . $attach['id_attach']] = $attach;
			}
			$smcFunc['db_free_result']($attach_request);

			// Sort the attachments by size to make things easier below
			if (!empty($loaded_attachments))
			{
				uasort(
					$loaded_attachments,
					function($a, $b)
					{
						if ($a['filesize'] == $b['filesize'])
							return 0;

						return ($a['filesize'] < $b['filesize']) ? -1 : 1;
					}
				);
			}
			else
				$loaded_attachments = null;
		}
		else
			$loaded_attachments = null;

		// Create a GUID for this topic using the tag URI scheme
		$guid = 'tag:' . parse_iri($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['poster_time']) . ':topic=' . $row['id_topic'];

		// Being news, this actually makes sense in rss format.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
		{
			// Only one attachment allowed in RSS.
			if ($loaded_attachments !== null)
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'url' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'author',
						'content' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? $row['poster_email'] . ' (' . $row['poster_name'] . ')' : null,
						'cdata' => true,
					),
					array(
						'tag' => 'comments',
						'content' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'category',
						'content' => $row['bname'],
						'cdata' => true,
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					),
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
					array(
						'tag' => 'enclosure',
						'attributes' => $enclosure,
					),
				),
			);
		}
		elseif ($xml_format == 'rdf')
		{
			$data[] = array(
				'tag' => 'item',
				'attributes' => array('rdf:about' => $scripturl . '?topic=' . $row['id_topic'] . '.0'),
				'content' => array(
					array(
						'tag' => 'dc:format',
						'content' => 'text/html',
					),
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
				),
			);
		}
		elseif ($xml_format == 'atom')
		{
			// Only one attachment allowed
			if (!empty($loaded_attachments))
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'rel' => 'enclosure',
					'href' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'attributes' => array(
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
						),
					),
					array(
						'tag' => 'summary',
						'attributes' => array('type' => 'html'),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'category',
						'attributes' => array('term' => $row['bname']),
						'cdata' => true,
					),
					array(
						'tag' => 'author',
						'content' => array(
							array(
								'tag' => 'name',
								'content' => $row['poster_name'],
								'cdata' => true,
							),
							array(
								'tag' => 'email',
								'content' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? $row['poster_email'] : null,
								'cdata' => true,
							),
							array(
								'tag' => 'uri',
								'content' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : null,
							),
						)
					),
					array(
						'tag' => 'published',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					),
					array(
						'tag' => 'updated',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					),
					array(
						'tag' => 'id',
						'content' => $guid,
					),
					array(
						'tag' => 'link',
						'attributes' => $enclosure,
					),
				),
			);
		}
		// The biggest difference here is more information.
		else
		{
			loadLanguage('Post');

			$attachments = array();
			if (!empty($loaded_attachments))
			{
				foreach ($loaded_attachments as $attachment)
				{
					$attachments[] = array(
						'tag' => 'attachment',
						'attributes' => array('label' => $txt['attachment']),
						'content' => array(
							array(
								'tag' => 'id',
								'content' => $attachment['id_attach'],
							),
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($attachment['filename'])),
							),
							array(
								'tag' => 'downloads',
								'attributes' => array('label' => $txt['downloads']),
								'content' => $attachment['downloads'],
							),
							array(
								'tag' => 'size',
								'attributes' => array('label' => $txt['filesize']),
								'content' => ($attachment['filesize'] < 1024000) ? round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'] : round($attachment['filesize'] / 1024 / 1024, 2) . ' ' . $txt['megabyte'],
							),
							array(
								'tag' => 'byte_size',
								'attributes' => array('label' => $txt['filesize']),
								'content' => $attachment['filesize'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach'],
							),
						)
					);
				}
			}
			else
				$attachments = null;

			$data[] = array(
				'tag' => 'article',
				'attributes' => array('label' => $txt['news']),
				'content' => array(
					array(
						'tag' => 'time',
						'attributes' => array('label' => $txt['date'], 'UTC' => smf_gmstrftime('%F %T', $row['poster_time'])),
						'content' => $smcFunc['htmlspecialchars'](strip_tags(timeformat($row['poster_time'], false, 'forum'))),
					),
					array(
						'tag' => 'id',
						'content' => $row['id_topic'],
					),
					array(
						'tag' => 'subject',
						'attributes' => array('label' => $txt['subject']),
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'body',
						'attributes' => array('label' => $txt['message']),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'poster',
						'attributes' => array('label' => $txt['author']),
						'content' => array(
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => $row['poster_name'],
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_member'],
							),
							array(
								'tag' => 'link',
								'attributes' => !empty($row['id_member']) ? array('label' => $txt['url']) : null,
								'content' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
							),
						)
					),
					array(
						'tag' => 'topic',
						'attributes' => array('label' => $txt['topic']),
						'content' => $row['id_topic'],
					),
					array(
						'tag' => 'board',
						'attributes' => array('label' => $txt['board']),
						'content' => array(
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => $row['bname'],
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_board'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?board=' . $row['id_board'] . '.0',
							),
						),
					),
					array(
						'tag' => 'link',
						'attributes' => array('label' => $txt['url']),
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'attachments',
						'attributes' => array('label' => $txt['attachments']),
						'content' => $attachments,
					),
				),
			);
		}
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the recent topics to display.
 * The returned array will be generated to match the xml_format.
 *
 * @param string $xml_format The XML format. Can be 'atom', 'rdf', 'rss', 'rss2' or 'smf'
 * @return array An array of arrays containing data for the feed. Each array has keys corresponding to the appropriate tags for the specified format.
 */
function getXmlRecent($xml_format)
{
	global $scripturl, $modSettings, $board, $txt;
	global $query_this_board, $smcFunc, $context, $user_info, $sourcedir;

	require_once($sourcedir . '/Subs-Attachments.php');

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE ' . $query_this_board . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . (empty($board) ? '' : '
				AND m.id_board = {int:current_board}') . ($modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			array(
				'limit' => $context['xmlnews_limit'],
				'current_board' => $board,
				'is_approved' => 1,
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $context['xmlnews_limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $smcFunc['db_num_rows']($request) < $context['xmlnews_limit'])
		{
			$smcFunc['db_free_result']($request);
			if (empty($_GET['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = $loops ? 'm.id_msg >= t.id_first_msg' : 'm.id_msg >= (t.id_last_msg - t.id_first_msg) / 2';
			$loops++;
		}
		else
			$done = true;
	}
	$messages = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$messages[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	if (empty($messages))
		return array();

	// Find the most recent posts this user can see.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.id_topic, t.id_board,
			b.name AS bname, t.num_replies, m.id_member, m.icon, mf.id_member AS id_first_member,
			COALESCE(mem.real_name, m.poster_name) AS poster_name, mf.subject AS first_subject,
			COALESCE(memf.real_name, mf.poster_name) AS first_poster_name,
			COALESCE(mem.email_address, m.poster_email) AS poster_email, m.modified_time
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			' . (empty($board) ? '' : 'AND t.id_board = {int:current_board}') . '
		ORDER BY m.id_msg DESC
		LIMIT {int:limit}',
		array(
			'limit' => $context['xmlnews_limit'],
			'current_board' => $board,
			'message_list' => $messages,
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If any control characters slipped in somehow, kill the evil things
		$row = filter_var($row, FILTER_CALLBACK, array('options' => 'cleanXml'));

		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && $smcFunc['strlen'](str_replace('<br>', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr($smcFunc['substr'](str_replace('<br>', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br>')) . '...';

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Do we want to include any attachments?
		if (!empty($modSettings['attachmentEnable']) && !empty($modSettings['xmlnews_attachments']) && allowedTo('view_attachments', $row['id_board']))
		{
			$attach_request = $smcFunc['db_query']('', '
				SELECT
					a.id_attach, a.filename, COALESCE(a.size, 0) AS filesize, a.mime_type, a.downloads, a.approved, m.id_topic AS topic
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.attachment_type = {int:attachment_type}
					AND a.id_msg = {int:message_id}',
				array(
					'message_id' => $row['id_msg'],
					'attachment_type' => 0,
					'is_approved' => 1,
				)
			);
			$loaded_attachments = array();
			while ($attach = $smcFunc['db_fetch_assoc']($attach_request))
			{
				// Include approved attachments only
				if ($attach['approved'])
					$loaded_attachments['attachment_' . $attach['id_attach']] = $attach;
			}
			$smcFunc['db_free_result']($attach_request);

			// Sort the attachments by size to make things easier below
			if (!empty($loaded_attachments))
			{
				uasort(
					$loaded_attachments,
					function($a, $b)
					{
						if ($a['filesize'] == $b['filesize'])
							return 0;

						return ($a['filesize'] < $b['filesize']) ? -1 : 1;
					}
				);
			}
			else
				$loaded_attachments = null;
		}
		else
			$loaded_attachments = null;

		// Create a GUID for this post using the tag URI scheme
		$guid = 'tag:' . parse_iri($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['poster_time']) . ':msg=' . $row['id_msg'];

		// Doesn't work as well as news, but it kinda does..
		if ($xml_format == 'rss' || $xml_format == 'rss2')
		{
			// Only one attachment allowed in RSS.
			if ($loaded_attachments !== null)
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'url' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'author',
						'content' => (allowedTo('moderate_forum') || (!empty($row['id_member']) && $row['id_member'] == $user_info['id'])) ? $row['poster_email'] : null,
						'cdata' => true,
					),
					array(
						'tag' => 'category',
						'content' => $row['bname'],
						'cdata' => true,
					),
					array(
						'tag' => 'comments',
						'content' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					),
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
					array(
						'tag' => 'enclosure',
						'attributes' => $enclosure,
					),
				),
			);
		}
		elseif ($xml_format == 'rdf')
		{
			$data[] = array(
				'tag' => 'item',
				'attributes' => array('rdf:about' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']),
				'content' => array(
					array(
						'tag' => 'dc:format',
						'content' => 'text/html',
					),
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
				),
			);
		}
		elseif ($xml_format == 'atom')
		{
			// Only one attachment allowed
			if (!empty($loaded_attachments))
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'rel' => 'enclosure',
					'href' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'attributes' => array(
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
						),
					),
					array(
						'tag' => 'summary',
						'attributes' => array('type' => 'html'),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'category',
						'attributes' => array('term' => $row['bname']),
						'cdata' => true,
					),
					array(
						'tag' => 'author',
						'content' => array(
							array(
								'tag' => 'name',
								'content' => $row['poster_name'],
								'cdata' => true,
							),
							array(
								'tag' => 'email',
								'content' => (allowedTo('moderate_forum') || (!empty($row['id_member']) && $row['id_member'] == $user_info['id'])) ? $row['poster_email'] : null,
								'cdata' => true,
							),
							array(
								'tag' => 'uri',
								'content' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : null,
							),
						),
					),
					array(
						'tag' => 'published',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					),
					array(
						'tag' => 'updated',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					),
					array(
						'tag' => 'id',
						'content' => $guid,
					),
					array(
						'tag' => 'link',
						'attributes' => $enclosure,
					),
				),
			);
		}
		// A lot of information here.  Should be enough to please the rss-ers.
		else
		{
			loadLanguage('Post');

			$attachments = array();
			if (!empty($loaded_attachments))
			{
				foreach ($loaded_attachments as $attachment)
				{
					$attachments[] = array(
						'tag' => 'attachment',
						'attributes' => array('label' => $txt['attachment']),
						'content' => array(
							array(
								'tag' => 'id',
								'content' => $attachment['id_attach'],
							),
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($attachment['filename'])),
							),
							array(
								'tag' => 'downloads',
								'attributes' => array('label' => $txt['downloads']),
								'content' => $attachment['downloads'],
							),
							array(
								'tag' => 'size',
								'attributes' => array('label' => $txt['filesize']),
								'content' => ($attachment['filesize'] < 1024000) ? round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'] : round($attachment['filesize'] / 1024 / 1024, 2) . ' ' . $txt['megabyte'],
							),
							array(
								'tag' => 'byte_size',
								'attributes' => array('label' => $txt['filesize']),
								'content' => $attachment['filesize'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach'],
							),
						)
					);
				}
			}
			else
				$attachments = null;

			$data[] = array(
				'tag' => 'recent-post', // Hyphen rather than underscore for backward compatibility reasons
				'attributes' => array('label' => $txt['post']),
				'content' => array(
					array(
						'tag' => 'time',
						'attributes' => array('label' => $txt['date'], 'UTC' => smf_gmstrftime('%F %T', $row['poster_time'])),
						'content' => $smcFunc['htmlspecialchars'](strip_tags(timeformat($row['poster_time'], false, 'forum'))),
					),
					array(
						'tag' => 'id',
						'content' => $row['id_msg'],
					),
					array(
						'tag' => 'subject',
						'attributes' => array('label' => $txt['subject']),
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'body',
						'attributes' => array('label' => $txt['message']),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'starter',
						'attributes' => array('label' => $txt['topic_started']),
						'content' => array(
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => $row['first_poster_name'],
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_first_member'],
							),
							array(
								'tag' => 'link',
								'attributes' => !empty($row['id_first_member']) ? array('label' => $txt['url']) : null,
								'content' => !empty($row['id_first_member']) ? $scripturl . '?action=profile;u=' . $row['id_first_member'] : '',
							),
						),
					),
					array(
						'tag' => 'poster',
						'attributes' => array('label' => $txt['author']),
						'content' => array(
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => $row['poster_name'],
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_member'],
							),
							array(
								'tag' => 'link',
								'attributes' => !empty($row['id_member']) ? array('label' => $txt['url']) : null,
								'content' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
							),
						),
					),
					array(
						'tag' => 'topic',
						'attributes' => array('label' => $txt['topic']),
						'content' => array(
							array(
								'tag' => 'subject',
								'attributes' => array('label' => $txt['subject']),
								'content' => $row['first_subject'],
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_topic'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?topic=' . $row['id_topic'] . '.new#new',
							),
						),
					),
					array(
						'tag' => 'board',
						'attributes' => array('label' => $txt['board']),
						'content' => array(
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => $row['bname'],
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_board'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?board=' . $row['id_board'] . '.0',
							),
						),
					),
					array(
						'tag' => 'link',
						'attributes' => array('label' => $txt['url']),
						'content' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					),
					array(
						'tag' => 'attachments',
						'attributes' => array('label' => $txt['attachments']),
						'content' => $attachments,
					),
				),
			);
		}
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the profile information for member into an array,
 * which will be generated to match the xml_format.
 *
 * @param string $xml_format The XML format. Can be 'atom', 'rdf', 'rss', 'rss2' or 'smf'
 * @return array An array profile data
 */
function getXmlProfile($xml_format)
{
	global $scripturl, $memberContext, $user_info, $txt, $context;

	// You must input a valid user, and you must be allowed to view that user's profile.
	if (empty($context['xmlnews_uid']) || ($context['xmlnews_uid'] != $user_info['id'] && !allowedTo('profile_view')) || !loadMemberData($context['xmlnews_uid']))
		return array();

	// Load the member's contextual information! (Including custom fields for our proprietary XML type)
	if (!loadMemberContext($context['xmlnews_uid'], ($xml_format == 'smf')))
		return array();

	$profile = &$memberContext[$context['xmlnews_uid']];

	// If any control characters slipped in somehow, kill the evil things
	$profile = filter_var($profile, FILTER_CALLBACK, array('options' => 'cleanXml'));

	// Create a GUID for this member using the tag URI scheme
	$guid = 'tag:' . parse_iri($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $profile['registered_timestamp']) . ':member=' . $profile['id'];

	if ($xml_format == 'rss' || $xml_format == 'rss2')
	{
		$data[] = array(
			'tag' => 'item',
			'content' => array(
				array(
					'tag' => 'title',
					'content' => $profile['name'],
					'cdata' => true,
				),
				array(
					'tag' => 'link',
					'content' => $scripturl . '?action=profile;u=' . $profile['id'],
				),
				array(
					'tag' => 'description',
					'content' => isset($profile['group']) ? $profile['group'] : $profile['post_group'],
					'cdata' => true,
				),
				array(
					'tag' => 'comments',
					'content' => $scripturl . '?action=pm;sa=send;u=' . $profile['id'],
				),
				array(
					'tag' => 'pubDate',
					'content' => gmdate('D, d M Y H:i:s \G\M\T', $profile['registered_timestamp']),
				),
				array(
					'tag' => 'guid',
					'content' => $guid,
					'attributes' => array(
						'isPermaLink' => 'false',
					),
				),
			)
		);
	}
	elseif ($xml_format == 'rdf')
	{
		$data[] = array(
			'tag' => 'item',
			'attributes' => array('rdf:about' => $scripturl . '?action=profile;u=' . $profile['id']),
			'content' => array(
				array(
					'tag' => 'dc:format',
					'content' => 'text/html',
				),
				array(
					'tag' => 'title',
					'content' => $profile['name'],
					'cdata' => true,
				),
				array(
					'tag' => 'link',
					'content' => $scripturl . '?action=profile;u=' . $profile['id'],
				),
				array(
					'tag' => 'description',
					'content' => isset($profile['group']) ? $profile['group'] : $profile['post_group'],
					'cdata' => true,
				),
			)
		);
	}
	elseif ($xml_format == 'atom')
	{
		$data[] = array(
			'tag' => 'entry',
			'content' => array(
				array(
					'tag' => 'title',
					'content' => $profile['name'],
					'cdata' => true,
				),
				array(
					'tag' => 'link',
					'attributes' => array(
						'rel' => 'alternate',
						'type' => 'text/html',
						'href' => $scripturl . '?action=profile;u=' . $profile['id'],
					),
				),
				array(
					'tag' => 'summary',
					'attributes' => array('type' => 'html'),
					'content' => isset($profile['group']) ? $profile['group'] : $profile['post_group'],
					'cdata' => true,
				),
				array(
					'tag' => 'author',
					'content' => array(
						array(
							'tag' => 'name',
							'content' => $profile['name'],
							'cdata' => true,
						),
						array(
							'tag' => 'email',
							'content' => $profile['show_email'] ? $profile['email'] : null,
							'cdata' => true,
						),
						array(
							'tag' => 'uri',
							'content' => !empty($profile['website']['url']) ? $profile['website']['url'] : $scripturl . '?action=profile;u=' . $profile['id_member'],
							'cdata' => !empty($profile['website']['url']),
						),
					),
				),
				array(
					'tag' => 'published',
					'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $profile['registered_timestamp']),
				),
				array(
					'tag' => 'updated',
					'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $profile['last_login_timestamp']),
				),
				array(
					'tag' => 'id',
					'content' => $guid,
				),
			)
		);
	}
	else
	{
		loadLanguage('Profile');

		$data = array(
			array(
				'tag' => 'username',
				'attributes' => $user_info['is_admin'] || $user_info['id'] == $profile['id'] ? array('label' => $txt['username']) : null,
				'content' => $user_info['is_admin'] || $user_info['id'] == $profile['id'] ? $profile['username'] : null,
				'cdata' => true,
			),
			array(
				'tag' => 'name',
				'attributes' => array('label' => $txt['name']),
				'content' => $profile['name'],
				'cdata' => true,
			),
			array(
				'tag' => 'link',
				'attributes' => array('label' => $txt['url']),
				'content' => $scripturl . '?action=profile;u=' . $profile['id'],
			),
			array(
				'tag' => 'posts',
				'attributes' => array('label' => $txt['member_postcount']),
				'content' => $profile['posts'],
			),
			array(
				'tag' => 'post-group',
				'attributes' => array('label' => $txt['post_based_membergroup']),
				'content' => $profile['post_group'],
				'cdata' => true,
			),
			array(
				'tag' => 'language',
				'attributes' => array('label' => $txt['preferred_language']),
				'content' => $profile['language'],
				'cdata' => true,
			),
			array(
				'tag' => 'last-login',
				'attributes' => array('label' => $txt['lastLoggedIn'], 'UTC' => smf_gmstrftime('%F %T', $profile['last_login_timestamp'])),
				'content' => timeformat($profile['last_login_timestamp'], false, 'forum'),
			),
			array(
				'tag' => 'registered',
				'attributes' => array('label' => $txt['date_registered'], 'UTC' => smf_gmstrftime('%F %T', $profile['registered_timestamp'])),
				'content' => timeformat($profile['registered_timestamp'], false, 'forum'),
			),
			array(
				'tag' => 'avatar',
				'attributes' => !empty($profile['avatar']['url']) ? array('label' => $txt['personal_picture']) : null,
				'content' => !empty($profile['avatar']['url']) ? $profile['avatar']['url'] : null,
				'cdata' => true,
			),
			array(
				'tag' => 'signature',
				'attributes' => !empty($profile['signature']) ? array('label' => $txt['signature']) : null,
				'content' => !empty($profile['signature']) ? $profile['signature'] : null,
				'cdata' => true,
			),
			array(
				'tag' => 'blurb',
				'attributes' => !empty($profile['blurb']) ? array('label' => $txt['personal_text']) : null,
				'content' => !empty($profile['blurb']) ? $profile['blurb'] : null,
				'cdata' => true,
			),
			array(
				'tag' => 'title',
				'attributes' => !empty($profile['title']) ? array('label' => $txt['title']) : null,
				'content' => !empty($profile['title']) ? $profile['title'] : null,
				'cdata' => true,
			),
			array(
				'tag' => 'position',
				'attributes' => !empty($profile['group']) ? array('label' => $txt['position']) : null,
				'content' => !empty($profile['group']) ? $profile['group'] : null,
				'cdata' => true,
			),
			array(
				'tag' => 'email',
				'attributes' => !empty($profile['show_email']) || $user_info['is_admin'] || $user_info['id'] == $profile['id'] ? array('label' => $txt['user_email_address']) : null,
				'content' => !empty($profile['show_email']) || $user_info['is_admin'] || $user_info['id'] == $profile['id'] ? $profile['email'] : null,
				'cdata' => true,
			),
			array(
				'tag' => 'website',
				'attributes' => empty($profile['website']['url']) ? null : array('label' => $txt['website']),
				'content' => empty($profile['website']['url']) ? null : array(
					array(
						'tag' => 'title',
						'attributes' => !empty($profile['website']['title']) ? array('label' => $txt['website_title']) : null,
						'content' => !empty($profile['website']['title']) ? $profile['website']['title'] : null,
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'attributes' => array('label' => $txt['website_url']),
						'content' => $profile['website']['url'],
						'cdata' => true,
					),
				),
			),
			array(
				'tag' => 'online',
				'attributes' => !empty($profile['online']['is_online']) ? array('label' => $txt['online']) : null,
				'content' => !empty($profile['online']['is_online']) ? 'true' : null,
			),
			array(
				'tag' => 'ip_addresses',
				'attributes' => array('label' => $txt['ip_address']),
				'content' => allowedTo('moderate_forum') || $user_info['id'] == $profile['id'] ? array(
					array(
						'tag' => 'ip',
						'attributes' => array('label' => $txt['most_recent_ip']),
						'content' => $profile['ip'],
					),
					array(
						'tag' => 'ip2',
						'content' => $profile['ip'] != $profile['ip2'] ? $profile['ip2'] : null,
					),
				) : null,
			),
		);

		if (!empty($profile['birth_date']) && substr($profile['birth_date'], 0, 4) != '0000' && substr($profile['birth_date'], 0, 4) != '1004')
		{
			list ($birth_year, $birth_month, $birth_day) = sscanf($profile['birth_date'], '%d-%d-%d');
			$datearray = getdate(time());
			$age = $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1);

			$data[] = array(
				'tag' => 'age',
				'attributes' => array('label' => $txt['age']),
				'content' => $age,
			);
			$data[] = array(
				'tag' => 'birthdate',
				'attributes' => array('label' => $txt['dob']),
				'content' => $profile['birth_date'],
			);
		}

		if (!empty($profile['custom_fields']))
		{
			foreach ($profile['custom_fields'] as $custom_field)
			{
				$data[] = array(
					'tag' => $custom_field['col_name'],
					'attributes' => array('label' => $custom_field['title']),
					'content' => $custom_field['simple'],
					'cdata' => true,
				);
			}
		}
	}

	// Save some memory.
	unset($profile, $memberContext[$context['xmlnews_uid']]);

	return $data;
}

/**
 * Get a user's posts.
 * The returned array will be generated to match the xml_format.
 *
 * @param string $xml_format The XML format. Can be 'atom', 'rdf', 'rss', 'rss2' or 'smf'
 * @param bool $ascending If true, get the oldest posts first. Default false.
 * @return array An array of arrays containing data for the feed. Each array has keys corresponding to the appropriate tags for the specified format.
 */
function getXmlPosts($xml_format, $ascending = false)
{
	global $scripturl, $modSettings, $board, $txt, $context, $user_info;
	global $query_this_board, $smcFunc, $sourcedir, $cachedir;

	if (empty($context['xmlnews_uid']) || ($context['xmlnews_uid'] != $user_info['id'] && !allowedTo('profile_view')))
		return array();

	$show_all = !empty($user_info['is_admin']) || defined('EXPORTING');

	$query_this_message_board = str_replace(array('{query_see_board}', 'b.'), array('{query_see_message_board}', 'm.'), $query_this_board);

	require_once($sourcedir . '/Subs-Attachments.php');

	/* MySQL can choke if we use joins in the main query when the user has
	 * massively long posts. To avoid that, we get the names of the boards
	 * and the user's displayed name in separate queries.
	 */
	$boardnames = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_board, name
		FROM {db_prefix}boards',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$boardnames[$row['id_board']] = $row['name'];
	$smcFunc['db_free_result']($request);

	if ($context['xmlnews_uid'] == $user_info['id'])
		$poster_name = $user_info['name'];
	else
	{
		$request = $smcFunc['db_query']('', '
			SELECT COALESCE(real_name, member_name) AS poster_name
			FROM {db_prefix}members
			WHERE id_member = {int:uid}',
			array(
				'uid' => $context['xmlnews_uid'],
			)
		);
		list($poster_name) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	$request = $smcFunc['db_query']('', '
		SELECT
			m.id_msg, m.id_topic, m.id_board, m.id_member, m.poster_email, m.poster_ip,
			m.poster_time, m.subject, m.modified_time, m.modified_name, m.modified_reason, m.body,
			m.likes, m.approved, m.smileys_enabled
		FROM {db_prefix}messages AS m' . ($modSettings['postmod_active'] && !$show_all ?'
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)' : '') . '
		WHERE m.id_member = {int:uid}
			AND m.id_msg > {int:start_after}
			AND ' . $query_this_message_board . ($modSettings['postmod_active'] && !$show_all ? '
			AND m.approved = {int:is_approved}
			AND t.approved = {int:is_approved}' : '') . '
		ORDER BY m.id_msg {raw:ascdesc}
		LIMIT {int:limit}',
		array(
			'limit' => $context['xmlnews_limit'],
			'start_after' => !empty($context['posts_start']) ? $context['posts_start'] : 0,
			'uid' => $context['xmlnews_uid'],
			'is_approved' => 1,
			'ascdesc' => !empty($ascending) ? 'ASC' : 'DESC',
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['last'] = $row['id_msg'];

		// We want a readable version of the IP address
		$row['poster_ip'] = inet_dtop($row['poster_ip']);

		// If any control characters slipped in somehow, kill the evil things
		$row = filter_var($row, FILTER_CALLBACK, array('options' => 'cleanXml'));

		// If using our own format, we want both the raw and the parsed content.
		$row[$xml_format === 'smf' ? 'body_html' : 'body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// Do we want to include any attachments?
		if (!empty($modSettings['attachmentEnable']) && !empty($modSettings['xmlnews_attachments']))
		{
			$attach_request = $smcFunc['db_query']('', '
				SELECT
					a.id_attach, a.filename, COALESCE(a.size, 0) AS filesize, a.mime_type, a.downloads, a.approved, m.id_topic AS topic
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.attachment_type = {int:attachment_type}
					AND a.id_msg = {int:message_id}',
				array(
					'message_id' => $row['id_msg'],
					'attachment_type' => 0,
					'is_approved' => 1,
				)
			);
			$loaded_attachments = array();
			while ($attach = $smcFunc['db_fetch_assoc']($attach_request))
			{
				// Include approved attachments only, unless showing all.
				if ($attach['approved'] || $show_all)
					$loaded_attachments['attachment_' . $attach['id_attach']] = $attach;
			}
			$smcFunc['db_free_result']($attach_request);

			// Sort the attachments by size to make things easier below
			if (!empty($loaded_attachments))
			{
				uasort(
					$loaded_attachments,
					function($a, $b)
					{
						if ($a['filesize'] == $b['filesize'])
					        return 0;

						return ($a['filesize'] < $b['filesize']) ? -1 : 1;
					}
				);
			}
			else
				$loaded_attachments = null;
		}
		else
			$loaded_attachments = null;

		// Create a GUID for this post using the tag URI scheme
		$guid = 'tag:' . parse_iri($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['poster_time']) . ':msg=' . $row['id_msg'];

		if ($xml_format == 'rss' || $xml_format == 'rss2')
		{
			// Only one attachment allowed in RSS.
			if ($loaded_attachments !== null)
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'url' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?msg=' . $row['id_msg'],
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'author',
						'content' => (allowedTo('moderate_forum') || ($row['id_member'] == $user_info['id'])) ? $row['poster_email'] : null,
						'cdata' => true,
					),
					array(
						'tag' => 'category',
						'content' => $boardnames[$row['id_board']],
						'cdata' => true,
					),
					array(
						'tag' => 'comments',
						'content' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
					),
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
					array(
						'tag' => 'enclosure',
						'attributes' => $enclosure,
					),
				),
			);
		}
		elseif ($xml_format == 'rdf')
		{
			$data[] = array(
				'tag' => 'item',
				'attributes' => array('rdf:about' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']),
				'content' => array(
					array(
						'tag' => 'dc:format',
						'content' => 'text/html',
					),
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?msg=' . $row['id_msg'],
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
				),
			);
		}
		elseif ($xml_format == 'atom')
		{
			// Only one attachment allowed
			if (!empty($loaded_attachments))
			{
				$attachment = array_pop($loaded_attachments);
				$enclosure = array(
					'rel' => 'enclosure',
					'href' => fix_possible_url($scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach']),
					'length' => $attachment['filesize'],
					'type' => $attachment['mime_type'],
				);
			}
			else
				$enclosure = null;

			$data[] = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'attributes' => array(
							'rel' => 'alternate',
							'type' => 'text/html',
							'href' => $scripturl . '?msg=' . $row['id_msg'],
						),
					),
					array(
						'tag' => 'summary',
						'attributes' => array('type' => 'html'),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'author',
						'content' => array(
							array(
								'tag' => 'name',
								'content' => $poster_name,
								'cdata' => true,
							),
							array(
								'tag' => 'email',
								'content' => (allowedTo('moderate_forum') || ($row['id_member'] == $user_info['id'])) ? $row['poster_email'] : null,
								'cdata' => true,
							),
							array(
								'tag' => 'uri',
								'content' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : null,
							),
						),
					),
					array(
						'tag' => 'published',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
					),
					array(
						'tag' => 'updated',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
					),
					array(
						'tag' => 'id',
						'content' => $guid,
					),
					array(
						'tag' => 'link',
						'attributes' => $enclosure,
					),
				),
			);
		}
		// A lot of information here.  Should be enough to please the rss-ers.
		else
		{
			loadLanguage('Post');

			$attachments = array();
			if (!empty($loaded_attachments))
			{
				foreach ($loaded_attachments as $attachment)
				{
					$attachments[] = array(
						'tag' => 'attachment',
						'attributes' => array('label' => $txt['attachment']),
						'content' => array(
							array(
								'tag' => 'id',
								'content' => $attachment['id_attach'],
							),
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($attachment['filename'])),
							),
							array(
								'tag' => 'downloads',
								'attributes' => array('label' => $txt['downloads']),
								'content' => $attachment['downloads'],
							),
							array(
								'tag' => 'size',
								'attributes' => array('label' => $txt['filesize']),
								'content' => ($attachment['filesize'] < 1024000) ? round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'] : round($attachment['filesize'] / 1024 / 1024, 2) . ' ' . $txt['megabyte'],
							),
							array(
								'tag' => 'byte_size',
								'attributes' => array('label' => $txt['filesize']),
								'content' => $attachment['filesize'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?action=dlattach;topic=' . $attachment['topic'] . '.0;attach=' . $attachment['id_attach'],
							),
							array(
								'tag' => 'approval_status',
								'attributes' => $show_all ? array('label' => $txt['approval_status']) : null,
								'content' => $show_all ? $attachment['approved'] : null,
							),
						)
					);
				}
			}
			else
				$attachments = null;

			$data[] = array(
				'tag' => 'member_post',
				'attributes' => array('label' => $txt['post']),
				'content' => array(
					array(
						'tag' => 'id',
						'content' => $row['id_msg'],
					),
					array(
						'tag' => 'subject',
						'attributes' => array('label' => $txt['subject']),
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'body',
						'attributes' => array('label' => $txt['message']),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'body_html',
						'attributes' => array('label' => $txt['html']),
						'content' => $row['body_html'],
						'cdata' => true,
					),
					array(
						'tag' => 'poster',
						'attributes' => array('label' => $txt['author']),
						'content' => array(
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => $poster_name,
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_member'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?action=profile;u=' . $row['id_member'],
							),
							array(
								'tag' => 'email',
								'attributes' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? array('label' => $txt['user_email_address']) : null,
								'content' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? $row['poster_email'] : null,
								'cdata' => true,
							),
							array(
								'tag' => 'ip',
								'attributes' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? array('label' => $txt['ip']) : null,
								'content' => (allowedTo('moderate_forum') || $row['id_member'] == $user_info['id']) ? $row['poster_ip'] : null,
							),
						),
					),
					array(
						'tag' => 'topic',
						'attributes' => array('label' => $txt['topic']),
						'content' => array(
							array(
								'tag' => 'id',
								'content' => $row['id_topic'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
							),
						),
					),
					array(
						'tag' => 'board',
						'attributes' => array('label' => $txt['board']),
						'content' => array(
							array(
								'tag' => 'id',
								'content' => $row['id_board'],
							),
							array(
								'tag' => 'name',
								'content' => $boardnames[$row['id_board']],
								'cdata' => true,
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?board=' . $row['id_board'] . '.0',
							),
						),
					),
					array(
						'tag' => 'link',
						'attributes' => array('label' => $txt['url']),
						'content' => $scripturl . '?msg=' . $row['id_msg'],
					),
					array(
						'tag' => 'time',
						'attributes' => array('label' => $txt['date'], 'UTC' => smf_gmstrftime('%F %T', $row['poster_time'])),
						'content' => $smcFunc['htmlspecialchars'](strip_tags(timeformat($row['poster_time'], false, 'forum'))),
					),
					array(
						'tag' => 'modified_time',
						'attributes' => !empty($row['modified_time']) ? array('label' => $txt['modified_time'], 'UTC' => smf_gmstrftime('%F %T', $row['modified_time'])) : null,
						'content' => !empty($row['modified_time']) ? $smcFunc['htmlspecialchars'](strip_tags(timeformat($row['modified_time'], false, 'forum'))) : null,
					),
					array(
						'tag' => 'modified_by',
						'attributes' => !empty($row['modified_name']) ? array('label' => $txt['modified_by']) : null,
						'content' => !empty($row['modified_name']) ? $row['modified_name'] : null,
						'cdata' => true,
					),
					array(
						'tag' => 'modified_reason',
						'attributes' => !empty($row['modified_reason']) ? array('label' => $txt['reason_for_edit']) : null,
						'content' => !empty($row['modified_reason']) ? $row['modified_reason'] : null,
						'cdata' => true,
					),
					array(
						'tag' => 'likes',
						'attributes' => array('label' => $txt['likes']),
						'content' => $row['likes'],
					),
					array(
						'tag' => 'approval_status',
						'attributes' => $show_all ? array('label' => $txt['approval_status']) : null,
						'content' => $show_all ? $row['approved'] : null,
					),
					array(
						'tag' => 'attachments',
						'attributes' => array('label' => $txt['attachments']),
						'content' => $attachments,
					),
				),
			);
		}
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get a user's personal messages.
 * Only the user can do this, and no one else -- not even the admin!
 *
 * @param string $xml_format The XML format. Can be 'atom', 'rdf', 'rss', 'rss2' or 'smf'
 * @param bool $ascending If true, get the oldest PMs first. Default false.
 * @return array An array of arrays containing data for the feed. Each array has keys corresponding to the appropriate tags for the specified format.
 */
function getXmlPMs($xml_format, $ascending = false)
{
	global $scripturl, $modSettings, $board, $txt, $context, $user_info;
	global $smcFunc, $sourcedir, $cachedir;

	// Personal messages are supposed to be private
	if (empty($context['xmlnews_uid']) || ($context['xmlnews_uid'] != $user_info['id']))
		return array();

	$select_id_members_to = $smcFunc['db_title'] === POSTGRE_TITLE ? "string_agg(pmr.id_member::text, ',')" : 'GROUP_CONCAT(pmr.id_member)';

	$select_to_names = $smcFunc['db_title'] === POSTGRE_TITLE ? "string_agg(COALESCE(mem.real_name, mem.member_name), ',')" : 'GROUP_CONCAT(COALESCE(mem.real_name, mem.member_name))';

	$request = $smcFunc['db_query']('', '
		SELECT pm.id_pm, pm.msgtime, pm.subject, pm.body, pm.id_member_from, nis.from_name, nis.id_members_to, nis.to_names
		FROM {db_prefix}personal_messages AS pm
		INNER JOIN
		(
			SELECT pm2.id_pm, COALESCE(memf.real_name, pm2.from_name) AS from_name, ' . $select_id_members_to . ' AS id_members_to, ' . $select_to_names . ' AS to_names
			FROM {db_prefix}personal_messages AS pm2
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pm2.id_pm = pmr.id_pm)
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = pm2.id_member_from)
			WHERE pm2.id_pm > {int:start_after}
				AND (
					(pm2.id_member_from = {int:uid} AND pm2.deleted_by_sender = {int:not_deleted})
					OR (pmr.id_member = {int:uid} AND pmr.deleted = {int:not_deleted})
				)
			GROUP BY pm2.id_pm, COALESCE(memf.real_name, pm2.from_name)
			ORDER BY pm2.id_pm {raw:ascdesc}
			LIMIT {int:limit}
		) AS nis ON nis.id_pm = pm.id_pm
		ORDER BY pm.id_pm {raw:ascdesc}',
		array(
			'limit' => $context['xmlnews_limit'],
			'start_after' => !empty($context['personal_messages_start']) ? $context['personal_messages_start'] : 0,
			'uid' => $context['xmlnews_uid'],
			'not_deleted' => 0,
			'ascdesc' => !empty($ascending) ? 'ASC' : 'DESC',
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['personal_messages_start'] = $row['id_pm'];

		// If any control characters slipped in somehow, kill the evil things
		$row = filter_var($row, FILTER_CALLBACK, array('options' => 'cleanXml'));

		// If using our own format, we want both the raw and the parsed content.
		$row[$xml_format === 'smf' ? 'body_html' : 'body'] = parse_bbc($row['body']);

		$recipients = array_combine(explode(',', $row['id_members_to']), explode(',', $row['to_names']));

		// Create a GUID for this post using the tag URI scheme
		$guid = 'tag:' . parse_iri($scripturl, PHP_URL_HOST) . ',' . gmdate('Y-m-d', $row['msgtime']) . ':pm=' . $row['id_pm'];

		if ($xml_format == 'rss' || $xml_format == 'rss2')
		{
			$item = array(
				'tag' => 'item',
				'content' => array(
					array(
						'tag' => 'guid',
						'content' => $guid,
						'attributes' => array(
							'isPermaLink' => 'false',
						),
					),
					array(
						'tag' => 'pubDate',
						'content' => gmdate('D, d M Y H:i:s \G\M\T', $row['msgtime']),
					),
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'smf:sender',
						// This technically violates the RSS spec, but meh...
						'content' => $row['from_name'],
						'cdata' => true,
					),
				),
			);

			foreach ($recipients as $recipient_id => $recipient_name)
				$item['content'][] = array(
					'tag' => 'smf:recipient',
					'content' => $recipient_name,
					'cdata' => true,
				);

			$data[] = $item;
		}
		elseif ($xml_format == 'rdf')
		{
			$data[] = array(
				'tag' => 'item',
				'attributes' => array('rdf:about' => $scripturl . '?action=pm#msg' . $row['id_pm']),
				'content' => array(
					array(
						'tag' => 'dc:format',
						'content' => 'text/html',
					),
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'link',
						'content' => $scripturl . '?action=pm#msg' . $row['id_pm'],
					),
					array(
						'tag' => 'description',
						'content' => $row['body'],
						'cdata' => true,
					),
				),
			);
		}
		elseif ($xml_format == 'atom')
		{
			$item = array(
				'tag' => 'entry',
				'content' => array(
					array(
						'tag' => 'id',
						'content' => $guid,
					),
					array(
						'tag' => 'updated',
						'content' => smf_gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['msgtime']),
					),
					array(
						'tag' => 'title',
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'content',
						'attributes' => array('type' => 'html'),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'author',
						'content' => array(
							array(
								'tag' => 'name',
								'content' => $row['from_name'],
								'cdata' => true,
							),
						),
					),
				),
			);

			foreach ($recipients as $recipient_id => $recipient_name)
				$item['content'][] = array(
					'tag' => 'contributor',
					'content' => array(
						array(
							'tag' => 'smf:role',
							'content' => 'recipient',
						),
						array(
							'tag' => 'name',
							'content' => $recipient_name,
							'cdata' => true,
						),
					),
				);

			$data[] = $item;
		}
		else
		{
			loadLanguage('PersonalMessage');

			$item = array(
				'tag' => 'personal_message',
				'attributes' => array('label' => $txt['pm']),
				'content' => array(
					array(
						'tag' => 'id',
						'content' => $row['id_pm'],
					),
					array(
						'tag' => 'sent_date',
						'attributes' => array('label' => $txt['date'], 'UTC' => smf_gmstrftime('%F %T', $row['msgtime'])),
						'content' => $smcFunc['htmlspecialchars'](strip_tags(timeformat($row['msgtime'], false, 'forum'))),
					),
					array(
						'tag' => 'subject',
						'attributes' => array('label' => $txt['subject']),
						'content' => $row['subject'],
						'cdata' => true,
					),
					array(
						'tag' => 'body',
						'attributes' => array('label' => $txt['message']),
						'content' => $row['body'],
						'cdata' => true,
					),
					array(
						'tag' => 'body_html',
						'attributes' => array('label' => $txt['html']),
						'content' => $row['body_html'],
						'cdata' => true,
					),
					array(
						'tag' => 'sender',
						'attributes' => array('label' => $txt['author']),
						'content' => array(
							array(
								'tag' => 'name',
								'attributes' => array('label' => $txt['name']),
								'content' => $row['from_name'],
								'cdata' => true,
							),
							array(
								'tag' => 'id',
								'content' => $row['id_member_from'],
							),
							array(
								'tag' => 'link',
								'attributes' => array('label' => $txt['url']),
								'content' => $scripturl . '?action=profile;u=' . $row['id_member_from'],
							),
						),
					),
				),
			);

			foreach ($recipients as $recipient_id => $recipient_name)
				$item['content'][] = array(
					'tag' => 'recipient',
					'attributes' => array('label' => $txt['recipient']),
					'content' => array(
						array(
							'tag' => 'name',
							'attributes' => array('label' => $txt['name']),
							'content' => $recipient_name,
							'cdata' => true,
						),
						array(
							'tag' => 'id',
							'content' => $recipient_id,
						),
						array(
							'tag' => 'link',
							'attributes' => array('label' => $txt['url']),
							'content' => $scripturl . '?action=profile;u=' . $recipient_id,
						),
					),
				);

			$data[] = $item;
		}
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

?>