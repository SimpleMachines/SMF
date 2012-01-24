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

/*	This file contains the files necessary to display news as an XML feed.

	void ShowXmlFeed()
		- is called to output xml information.
		- can be passed four subactions which decide what is output: 'recent'
		  for recent posts, 'news' for news topics, 'members' for recently
		  registered members, and 'profile' for a member's profile.
		- To display a member's profile, a user id has to be given. (;u=1)
		- uses the Stats language file.
		- outputs an rss feed instead of a proprietary one if the 'type' get
		  parameter is 'rss' or 'rss2'.
		- does not use any templates, sub templates, or template layers.
		- is accessed via ?action=.xml.

	void dumpTags(array data, int indentation, string tag = use_array,
			string format)
		- formats data retrieved in other functions into xml format.
		- additionally formats data based on the specific format passed.
		- the data parameter is the array to output as xml data.
		- indentation is the amount of indentation to use.
		- if a tag is specified, it will be used instead of the keys of data.
		- this function is recursively called to handle sub arrays of data.

	array getXmlMembers(string format)
		- is called to retrieve list of members from database.
		- the array will be generated to match the format.
		- returns array of data.

	array getXmlNews(string format)
		- is called to retrieve news topics from database.
		- the array will be generated to match the format.
		- returns array of topics.

	array getXmlRecent(string format)
		- is called to retrieve list of recent topics.
		- the array will be generated to match the format.
		- returns an array of recent posts.

	array getXmlProfile(string format)
		- is called to retrieve profile information for member into array.
		- the array will be generated to match the format.
		- returns an array of data.
*/

// Show an xml file representing recent information or a profile.
function ShowXmlFeed()
{
	global $board, $board_info, $context, $scripturl, $txt, $modSettings, $user_info;
	global $query_this_board, $smcFunc, $forum_version, $cdata_override;

	// If it's not enabled, die.
	if (empty($modSettings['xmlnews_enable']))
		obExit(false);

	loadLanguage('Stats');

	// Default to latest 5.  No more than 255, please.
	$_GET['limit'] = empty($_GET['limit']) || (int) $_GET['limit'] < 1 ? 5 : min((int) $_GET['limit'], 255);

	// Handle the cases where a board, boards, or category is asked for.
	$query_this_board = 1;
	$context['optimize_msg'] = array(
		'highest' => 'm.id_msg <= b.id_last_msg',
	);
	if (!empty($_REQUEST['c']) && empty($board))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		if (count($_REQUEST['c']) == 1)
		{
			$request = $smcFunc['db_query']('', '
				SELECT name
				FROM {db_prefix}categories
				WHERE id_cat = {int:current_category}',
				array(
					'current_category' => (int) $_REQUEST['c'][0],
				)
			);
			list ($feed_title) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$feed_title = ' - ' . strip_tags($feed_title);
		}

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_cat IN ({array_int:current_category_list})
				AND {query_see_board}',
			array(
				'current_category_list' => $_REQUEST['c'],
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
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 400 - $_GET['limit'] * 5);
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_posts, b.name
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND {query_see_board}
			LIMIT ' . count($_REQUEST['boards']),
			array(
				'board_list' => $_REQUEST['boards'],
			)
		);

		// Either the board specified doesn't exist or you have no access.
		$num_boards = $smcFunc['db_num_rows']($request);
		if ($num_boards == 0)
			fatal_lang_error('no_board');

		$total_posts = 0;
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if ($num_boards == 1)
				$feed_title = ' - ' . strip_tags($row['name']);

			$boards[] = $row['id_board'];
			$total_posts += $row['num_posts'];
		}
		$smcFunc['db_free_result']($request);

		if (!empty($boards))
			$query_this_board = 'b.id_board IN (' . implode(', ', $boards) . ')';

		// The more boards, the more we're going to look through...
		if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 500 - $_GET['limit'] * 5);
	}
	elseif (!empty($board))
	{
		$request = $smcFunc['db_query']('', '
			SELECT num_posts
			FROM {db_prefix}boards
			WHERE id_board = {int:current_board}
			LIMIT 1',
			array(
				'current_board' => $board,
			)
		);
		list ($total_posts) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$feed_title = ' - ' . strip_tags($board_info['name']);

		$query_this_board = 'b.id_board = ' . $board;

		// Try to look through just a few messages, if at all possible.
		if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 600 - $_GET['limit'] * 5);
	}
	else
	{
		$query_this_board = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != ' . $modSettings['recycle_board'] : '');
		$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 100 - $_GET['limit'] * 5);
	}

	// Show in rss or proprietary format?
	$xml_format = isset($_GET['type']) && in_array($_GET['type'], array('smf', 'rss', 'rss2', 'atom', 'rdf', 'webslice')) ? $_GET['type'] : 'smf';

	// !!! Birthdays?

	// List all the different types of data they can pull.
	$subActions = array(
		'recent' => array('getXmlRecent', 'recent-post'),
		'news' => array('getXmlNews', 'article'),
		'members' => array('getXmlMembers', 'member'),
		'profile' => array('getXmlProfile', null),
	);
	if (empty($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'recent';

	//!!! Temp - webslices doesn't do everything yet.
	if ($xml_format == 'webslice' && $_GET['sa'] != 'recent')
		$xml_format = 'rss2';
	// If this is webslices we kinda cheat - we allow a template that we call direct for the HTML, and we override the CDATA.
	elseif ($xml_format == 'webslice')
	{
		$context['user'] += $user_info;
		$cdata_override = true;
		loadTemplate('Xml');
	}

	// We only want some information, not all of it.
	$cachekey = array($xml_format, $_GET['action'], $_GET['limit'], $_GET['sa']);
	foreach (array('board', 'boards', 'c') as $var)
		if (isset($_REQUEST[$var]))
			$cachekey[] = $_REQUEST[$var];
	$cachekey = md5(serialize($cachekey) . (!empty($query_this_board) ? $query_this_board : ''));
	$cache_t = microtime();

	// Get the associative array representing the xml.
	if (!empty($modSettings['cache_enable']) && (!$user_info['is_guest'] || $modSettings['cache_enable'] >= 3))
		$xml = cache_get_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, 240);
	if (empty($xml))
	{
		$xml = $subActions[$_GET['sa']][0]($xml_format);

		if (!empty($modSettings['cache_enable']) && (($user_info['is_guest'] && $modSettings['cache_enable'] >= 3)
		|| (!$user_info['is_guest'] && (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $cache_t)) > 0.2))))
			cache_put_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, $xml, 240);
	}

	$feed_title = htmlspecialchars(strip_tags($context['forum_name'])) . (isset($feed_title) ? $feed_title : '');

	// This is an xml file....
	ob_end_clean();
	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	if ($xml_format == 'smf' || isset($_REQUEST['debug']))
		header('Content-Type: text/xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	elseif ($xml_format == 'rss' || $xml_format == 'rss2' || $xml_format == 'webslice')
		header('Content-Type: application/rss+xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	elseif ($xml_format == 'atom')
		header('Content-Type: application/atom+xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	elseif ($xml_format == 'rdf')
		header('Content-Type: ' . ($context['browser']['is_ie'] ? 'text/xml' : 'application/rdf+xml') . '; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

	// First, output the xml header.
	echo '<?xml version="1.0" encoding="', $context['character_set'], '"?' . '>';

	// Are we outputting an rss feed or one with more information?
	if ($xml_format == 'rss' || $xml_format == 'rss2')
	{
		// Start with an RSS 2.0 header.
		echo '
<rss version=', $xml_format == 'rss2' ? '"2.0"' : '"0.92"', ' xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">
	<channel>
		<title>', $feed_title, '</title>
		<link>', $scripturl, '</link>
		<description><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></description>';

		// Output all of the associative array, start indenting with 2 tabs, and name everything "item".
		dumpTags($xml, 2, 'item', $xml_format);

		// Output the footer of the xml.
		echo '
	</channel>
</rss>';
	}
	elseif ($xml_format == 'webslice')
	{
		$context['recent_posts_data'] = $xml;

		// This always has RSS 2
		echo '
<rss version="2.0" xmlns:mon="http://www.microsoft.com/schemas/rss/monitoring/2007" xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">
	<channel>
		<title>', $feed_title, ' - ', $txt['recent_posts'], '</title>
		<link>', $scripturl, '?action=recent</link>
		<description><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></description>
		<item>
			<title>', $feed_title, ' - ', $txt['recent_posts'], '</title>
			<link>', $scripturl, '?action=recent</link>
			<description><![CDATA[
				', template_webslice_header_above(), '
				', template_webslice_recent_posts(), '
				', template_webslice_header_below(), '
			]]></description>
		</item>
	</channel>
</rss>';
	}
	elseif ($xml_format == 'atom')
	{
		echo '
<feed xmlns="http://www.w3.org/2005/Atom">
	<title>', $feed_title, '</title>
	<link rel="alternate" type="text/html" href="', $scripturl, '" />

	<modified>', gmstrftime('%Y-%m-%dT%H:%M:%SZ'), '</modified>
	<tagline><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></tagline>
	<generator uri="http://www.simplemachines.org" version="', strtr($forum_version, array('SMF' => '')), '">SMF</generator>
	<author>
		<name>', strip_tags($context['forum_name']), '</name>
	</author>';

		dumpTags($xml, 2, 'entry', $xml_format);

		echo '
</feed>';
	}
	elseif ($xml_format == 'rdf')
	{
		echo '
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns="http://purl.org/rss/1.0/">
	<channel rdf:about="', $scripturl, '">
		<title>', $feed_title, '</title>
		<link>', $scripturl, '</link>
		<description><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></description>
		<items>
			<rdf:Seq>';

		foreach ($xml as $item)
			echo '
				<rdf:li rdf:resource="', $item['link'], '" />';

		echo '
			</rdf:Seq>
		</items>
	</channel>
';

		dumpTags($xml, 1, 'item', $xml_format);

		echo '
</rdf:RDF>';
	}
	// Otherwise, we're using our proprietary formats - they give more data, though.
	else
	{
		echo '
<smf:xml-feed xmlns:smf="http://www.simplemachines.org/" xmlns="http://www.simplemachines.org/xml/', $_GET['sa'], '" xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">';

		// Dump out that associative array.  Indent properly.... and use the right names for the base elements.
		dumpTags($xml, 1, $subActions[$_GET['sa']][1], $xml_format);

		echo '
</smf:xml-feed>';
}

	obExit(false);
}

function fix_possible_url($val)
{
	global $modSettings, $context, $scripturl;

	if (substr($val, 0, strlen($scripturl)) != $scripturl)
		return $val;

	call_integration_hook('integrate_fix_url', array(&$val));

	if (empty($modSettings['queryless_urls']) || ($context['server']['is_cgi'] && @ini_get('cgi.fix_pathinfo') == 0 && @get_cfg_var('cgi.fix_pathinfo') == 0) || (!$context['server']['is_apache'] && !$context['server']['is_lighttpd']))
		return $val;

	$val = preg_replace('/^' . preg_quote($scripturl, '/') . '\?((?:board|topic)=[^#"]+)(#[^"]*)?$/e', '\'\' . $scripturl . \'/\' . strtr(\'$1\', \'&;=\', \'//,\') . \'.html$2\'', $val);
	return $val;
}

function cdata_parse($data, $ns = '')
{
	global $smcFunc, $cdata_override;

	// Are we not doing it?
	if (!empty($cdata_override))
		return $data;

	$cdata = '<![CDATA[';

	for ($pos = 0, $n = $smcFunc['strlen']($data); $pos < $n; null)
	{
		$positions = array(
			$smcFunc['strpos']($data, '&', $pos),
			$smcFunc['strpos']($data, ']', $pos),
		);
		if ($ns != '')
			$positions[] = $smcFunc['strpos']($data, '<', $pos);
		foreach ($positions as $k => $dummy)
		{
			if ($dummy === false)
				unset($positions[$k]);
		}

		$old = $pos;
		$pos = empty($positions) ? $n : min($positions);

		if ($pos - $old > 0)
			$cdata .= $smcFunc['substr']($data, $old, $pos - $old);
		if ($pos >= $n)
			break;

		if ($smcFunc['substr']($data, $pos, 1) == '<')
		{
			$pos2 = $smcFunc['strpos']($data, '>', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			if ($smcFunc['substr']($data, $pos + 1, 1) == '/')
				$cdata .= ']]></' . $ns . ':' . $smcFunc['substr']($data, $pos + 2, $pos2 - $pos - 1) . '<![CDATA[';
			else
				$cdata .= ']]><' . $ns . ':' . $smcFunc['substr']($data, $pos + 1, $pos2 - $pos) . '<![CDATA[';
			$pos = $pos2 + 1;
		}
		elseif ($smcFunc['substr']($data, $pos, 1) == ']')
		{
			$cdata .= ']]>&#093;<![CDATA[';
			$pos++;
		}
		elseif ($smcFunc['substr']($data, $pos, 1) == '&')
		{
			$pos2 = $smcFunc['strpos']($data, ';', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			$ent = $smcFunc['substr']($data, $pos + 1, $pos2 - $pos - 1);

			if ($smcFunc['substr']($data, $pos + 1, 1) == '#')
				$cdata .= ']]>' . $smcFunc['substr']($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';
			elseif (in_array($ent, array('amp', 'lt', 'gt', 'quot')))
				$cdata .= ']]>' . $smcFunc['substr']($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';
			// !!! ??

			$pos = $pos2 + 1;
		}
	}

	$cdata .= ']]>';

	return strtr($cdata, array('<![CDATA[]]>' => ''));
}

function dumpTags($data, $i, $tag = null, $xml_format = '')
{
	global $modSettings, $context, $scripturl;

	// For every array in the data...
	foreach ($data as $key => $val)
	{
		// Skip it, it's been set to null.
		if ($val === null)
			continue;

		// If a tag was passed, use it instead of the key.
		$key = isset($tag) ? $tag : $key;

		// First let's indent!
		echo "\n", str_repeat("\t", $i);

		// Grr, I hate kludges... almost worth doing it properly, here, but not quite.
		if ($xml_format == 'atom' && $key == 'link')
		{
			echo '<link rel="alternate" type="text/html" href="', fix_possible_url($val), '" />';
			continue;
		}

		// If it's empty/0/nothing simply output an empty tag.
		if ($val == '')
			echo '<', $key, ' />';
		else
		{
			// Beginning tag.
			if ($xml_format == 'rdf' && $key == 'item' && isset($val['link']))
			{
				echo '<', $key, ' rdf:about="', fix_possible_url($val['link']), '">';
				echo "\n", str_repeat("\t", $i + 1);
				echo '<dc:format>text/html</dc:format>';
			}
			elseif ($xml_format == 'atom' && $key == 'summary')
				echo '<', $key, ' type="html">';
			else
				echo '<', $key, '>';

			if (is_array($val))
			{
				// An array.  Dump it, and then indent the tag.
				dumpTags($val, $i + 1, null, $xml_format);
				echo "\n", str_repeat("\t", $i), '</', $key, '>';
			}
			// A string with returns in it.... show this as a multiline element.
			elseif (strpos($val, "\n") !== false || strpos($val, '<br />') !== false)
				echo "\n", fix_possible_url($val), "\n", str_repeat("\t", $i), '</', $key, '>';
			// A simple string.
			else
				echo fix_possible_url($val), '</', $key, '>';
		}
	}
}

function getXmlMembers($xml_format)
{
	global $scripturl, $smcFunc;

	if (!allowedTo('view_mlist'))
		return array();

	// Find the most recent members.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, date_registered, last_login
		FROM {db_prefix}members
		ORDER BY id_member DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Make the data look rss-ish.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'comments' => $scripturl . '?action=pm;sa=send;u=' . $row['id_member'],
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['date_registered']),
				'guid' => $scripturl . '?action=profile;u=' . $row['id_member'],
			);
		elseif ($xml_format == 'rdf')
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['date_registered']),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['last_login']),
				'id' => $scripturl . '?action=profile;u=' . $row['id_member'],
			);
		// More logical format for the data, but harder to apply.
		else
			$data[] = array(
				'name' => cdata_parse($row['real_name']),
				'time' => htmlspecialchars(strip_tags(timeformat($row['date_registered']))),
				'id' => $row['id_member'],
				'link' => $scripturl . '?action=profile;u=' . $row['id_member']
			);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

function getXmlNews($xml_format)
{
	global $user_info, $scripturl, $modSettings, $board;
	global $query_this_board, $smcFunc, $settings, $context;

	/* Find the latest posts that:
		- are the first post in their topic.
		- are on an any board OR in a specified board.
		- can be seen by this user.
		- are actually the latest posts. */

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
				mem.hide_email, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.email_address, m.poster_email) AS poster_email,
				IFNULL(mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE ' . $query_this_board . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . (empty($board) ? '' : '
				AND t.id_board = {int:current_board}') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY t.id_first_msg DESC
			LIMIT {int:limit}',
			array(
				'current_board' => $board,
				'is_approved' => 1,
				'limit' => $_GET['limit'],
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $smcFunc['db_num_rows']($request) < $_GET['limit'])
		{
			$smcFunc['db_free_result']($request);
			if (empty($_REQUEST['boards']) && empty($board))
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
		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && $smcFunc['strlen'](str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr($smcFunc['substr'](str_replace('<br />', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br />')) . '...';

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Being news, this actually makes sense in rss format.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => cdata_parse($row['subject']),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'description' => cdata_parse($row['body']),
				'author' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
				'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
				'category' => '<![CDATA[' . $row['bname'] . ']]>',
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
				'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			);
		elseif ($xml_format == 'rdf')
			$data[] = array(
				'title' => cdata_parse($row['subject']),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'description' => cdata_parse($row['body']),
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'title' => cdata_parse($row['subject']),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'summary' => cdata_parse($row['body']),
				'category' => array('term' => $row['id_board'], 'label' => cdata_parse($row['bname'])),
				'author' => array(
					'name' => $row['poster_name'],
					'email' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
					'uri' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				),
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
				'modified' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
				'id' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'icon' => $settings['images_url'] . '/icons/' . $row['icon'] . '.gif',
			);
		// The biggest difference here is more information.
		else
			$data[] = array(
				'time' => htmlspecialchars(strip_tags(timeformat($row['poster_time']))),
				'id' => $row['id_topic'],
				'subject' => cdata_parse($row['subject']),
				'body' => cdata_parse($row['body']),
				'poster' => array(
					'name' => cdata_parse($row['poster_name']),
					'id' => $row['id_member'],
					'link' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				),
				'topic' => $row['id_topic'],
				'board' => array(
					'name' => cdata_parse($row['bname']),
					'id' => $row['id_board'],
					'link' => $scripturl . '?board=' . $row['id_board'] . '.0',
				),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

function getXmlRecent($xml_format)
{
	global $user_info, $scripturl, $modSettings, $board;
	global $query_this_board, $smcFunc, $settings, $context;

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
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			array(
				'limit' => $_GET['limit'],
				'current_board' => $board,
				'is_approved' => 1,
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $smcFunc['db_num_rows']($request) < $_GET['limit'])
		{
			$smcFunc['db_free_result']($request);
			if (empty($_REQUEST['boards']) && empty($board))
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
			IFNULL(mem.real_name, m.poster_name) AS poster_name, mf.subject AS first_subject,
			IFNULL(memf.real_name, mf.poster_name) AS first_poster_name, mem.hide_email,
			IFNULL(mem.email_address, m.poster_email) AS poster_email, m.modified_time
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
			'limit' => $_GET['limit'],
			'current_board' => $board,
			'message_list' => $messages,
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && $smcFunc['strlen'](str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr($smcFunc['substr'](str_replace('<br />', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br />')) . '...';

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Doesn't work as well as news, but it kinda does..
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => $row['subject'],
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'description' => cdata_parse($row['body']),
				'author' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
				'category' => cdata_parse($row['bname']),
				'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
				'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']
			);
		elseif ($xml_format == 'rdf')
			$data[] = array(
				'title' => $row['subject'],
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'description' => cdata_parse($row['body']),
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'title' => $row['subject'],
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'summary' => cdata_parse($row['body']),
				'category' => array(
					'term' => $row['id_board'],
					'label' => cdata_parse($row['bname'])
				),
				'author' => array(
					'name' => $row['poster_name'],
					'email' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
					'uri' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : ''
				),
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
				'id' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'icon' => $settings['images_url'] . '/icons/' . $row['icon'] . '.gif',
			);
		// A lot of information here.  Should be enough to please the rss-ers.
		else
			$data[] = array(
				'time' => htmlspecialchars(strip_tags(timeformat($row['poster_time']))),
				'id' => $row['id_msg'],
				'subject' => cdata_parse($row['subject']),
				'body' => cdata_parse($row['body']),
				'starter' => array(
					'name' => cdata_parse($row['first_poster_name']),
					'id' => $row['id_first_member'],
					'link' => !empty($row['id_first_member']) ? $scripturl . '?action=profile;u=' . $row['id_first_member'] : ''
				),
				'poster' => array(
					'name' => cdata_parse($row['poster_name']),
					'id' => $row['id_member'],
					'link' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : ''
				),
				'topic' => array(
					'subject' => cdata_parse($row['first_subject']),
					'id' => $row['id_topic'],
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.new#new'
				),
				'board' => array(
					'name' => cdata_parse($row['bname']),
					'id' => $row['id_board'],
					'link' => $scripturl . '?board=' . $row['id_board'] . '.0'
				),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']
			);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

function getXmlProfile($xml_format)
{
	global $scripturl, $memberContext, $user_profile, $modSettings, $user_info;

	// You must input a valid user....
	if (empty($_GET['u']) || loadMemberData((int) $_GET['u']) === false)
		return array();

	// Make sure the id is a number and not "I like trying to hack the database".
	$_GET['u'] = (int) $_GET['u'];
	// Load the member's contextual information!
	if (!loadMemberContext($_GET['u']) || !allowedTo('profile_view_any'))
		return array();

	// Okay, I admit it, I'm lazy.  Stupid $_GET['u'] is long and hard to type.
	$profile = &$memberContext[$_GET['u']];

	if ($xml_format == 'rss' || $xml_format == 'rss2')
		$data = array(array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'description' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
			'comments' => $scripturl . '?action=pm;sa=send;u=' . $profile['id'],
			'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['date_registered']),
			'guid' => $scripturl . '?action=profile;u=' . $profile['id'],
		));
	elseif ($xml_format == 'rdf')
		$data = array(array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'description' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
		));
	elseif ($xml_format == 'atom')
		$data[] = array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'summary' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
			'author' => array(
				'name' => $profile['real_name'],
				'email' => in_array(showEmailAddress(!empty($profile['hide_email']), $profile['id']), array('yes', 'yes_permission_override')) ? $profile['email'] : null,
				'uri' => !empty($profile['website']) ? $profile['website']['url'] : ''
			),
			'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['date_registered']),
			'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['last_login']),
			'id' => $scripturl . '?action=profile;u=' . $profile['id'],
			'logo' => !empty($profile['avatar']) ? $profile['avatar']['url'] : '',
		);
	else
	{
		$data = array(
			'username' => $user_info['is_admin'] || $user_info['id'] == $profile['id'] ? cdata_parse($profile['username']) : '',
			'name' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'posts' => $profile['posts'],
			'post-group' => cdata_parse($profile['post_group']),
			'language' => cdata_parse($profile['language']),
			'last-login' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['last_login']),
			'registered' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['date_registered'])
		);

		// Everything below here might not be set, and thus maybe shouldn't be displayed.
		if ($profile['gender']['name'] != '')
			$data['gender'] = cdata_parse($profile['gender']['name']);

		if ($profile['avatar']['name'] != '')
			$data['avatar'] = $profile['avatar']['url'];

		// If they are online, show an empty tag... no reason to put anything inside it.
		if ($profile['online']['is_online'])
			$data['online'] = '';

		if ($profile['signature'] != '')
			$data['signature'] = cdata_parse($profile['signature']);
		if ($profile['blurb'] != '')
			$data['blurb'] = cdata_parse($profile['blurb']);
		if ($profile['location'] != '')
			$data['location'] = cdata_parse($profile['location']);
		if ($profile['title'] != '')
			$data['title'] = cdata_parse($profile['title']);

		if (!empty($profile['icq']['name']) && !(!empty($modSettings['guest_hideContacts']) && $user_info['is_guest']))
			$data['icq'] = $profile['icq']['name'];
		if ($profile['aim']['name'] != '' && !(!empty($modSettings['guest_hideContacts']) && $user_info['is_guest']))
			$data['aim'] = $profile['aim']['name'];
		if ($profile['msn']['name'] != '' && !(!empty($modSettings['guest_hideContacts']) && $user_info['is_guest']))
			$data['msn'] = $profile['msn']['name'];
		if ($profile['yim']['name'] != '' && !(!empty($modSettings['guest_hideContacts']) && $user_info['is_guest']))
			$data['yim'] = $profile['yim']['name'];

		if ($profile['website']['title'] != '')
			$data['website'] = array(
				'title' => cdata_parse($profile['website']['title']),
				'link' => $profile['website']['url']
			);

		if ($profile['group'] != '')
			$data['position'] = cdata_parse($profile['group']);

		if (!empty($modSettings['karmaMode']))
			$data['karma'] = array(
				'good' => $profile['karma']['good'],
				'bad' => $profile['karma']['bad']
			);

		if (in_array($profile['show_email'], array('yes', 'yes_permission_override')))
			$data['email'] = $profile['email'];

		if (!empty($profile['birth_date']) && substr($profile['birth_date'], 0, 4) != '0000')
		{
			list ($birth_year, $birth_month, $birth_day) = sscanf($profile['birth_date'], '%d-%d-%d');
			$datearray = getdate(forum_time());
			$data['age'] = $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1);
		}
	}

	// Save some memory.
	unset($profile, $memberContext[$_GET['u']]);

	return $data;
}

?>