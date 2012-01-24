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

/*	This file contains those functions specific to the editing box and is
	generally used for WYSIWYG type functionality. Doing all this is the
	following:

	void EditorMain()
		// !!

	void bbc_to_html()
		// !!

	void html_to_bbc()
		// !!

	void theme_postbox(string message)
		- for compatibility - passes right through to the template_control_richedit function.

	void create_control_richedit(&array editorOptions)
		// !!

	void create_control_verification(&array suggestOptions)
		// !!

	void fetchTagAttributes()
		// !!

	array getMessageIcons(int board_id)
	- retrieves a list of message icons.
	- based on the settings, the array will either contain a list of default
	  message icons or a list of custom message icons retrieved from the
	  database.
	- the board_id is needed for the custom message icons (which can be set for
	  each board individually).

	void AutoSuggestHandler(string checkRegistered = null)
		// !!!

	void AutoSuggest_Search_Member()
		// !!!
*/

// At the moment this is only used for returning WYSIWYG data...
function EditorMain()
{
	global $context, $smcFunc;

	checkSession('get');

	if (!isset($_REQUEST['view']) || !isset($_REQUEST['message']))
		fatal_lang_error('no_access', false);

	$context['sub_template'] = 'sendbody';

	$context['view'] = (int) $_REQUEST['view'];

	// Return the right thing for the mode.
	if ($context['view'])
	{
		$_REQUEST['message'] = strtr($_REQUEST['message'], array('#smcol#' => ';', '#smlt#' => '&lt;', '#smgt#' => '&gt;', '#smamp#' => '&amp;'));
		$context['message'] = bbc_to_html($_REQUEST['message']);
	}
	else
	{
		$_REQUEST['message'] = un_htmlspecialchars($_REQUEST['message']);
		$_REQUEST['message'] = strtr($_REQUEST['message'], array('#smcol#' => ';', '#smlt#' => '&lt;', '#smgt#' => '&gt;', '#smamp#' => '&amp;'));

		$context['message'] = html_to_bbc($_REQUEST['message']);
	}

	$context['message'] = $smcFunc['htmlspecialchars']($context['message']);
}

// Convert only the BBC that can be edited in HTML mode for the editor.
function bbc_to_html($text)
{
	global $modSettings, $smcFunc;

	// Turn line breaks back into br's.
	$text = strtr($text, array("\r" => '', "\n" => '<br />'));

	// Prevent conversion of all bbcode inside these bbcodes.
	// !!! Tie in with bbc permissions ?
	foreach (array('code', 'php', 'nobbc') as $code)
	{
		if (strpos($text, '['. $code) !== false)
		{
			$parts = preg_split('~(\[/' . $code . '\]|\[' . $code . '(?:=[^\]]+)?\])~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

			// Only mess with stuff inside tags.
			for ($i = 0, $n = count($parts); $i < $n; $i++)
			{
				// Value of 2 means we're inside the tag.
				if ($i % 4 == 2)
					$parts[$i] = strtr($parts[$i], array('[' => '&#91;', ']' => '&#93;', "'" => "'"));
			}
			// Put our humpty dumpty message back together again.
			$text = implode('', $parts);
		}
	}

	// What tags do we allow?
	$allowed_tags = array('b', 'u', 'i', 's', 'hr', 'list', 'li', 'font', 'size', 'color', 'img', 'left', 'center', 'right', 'url', 'email', 'ftp', 'sub', 'sup');

	$text = parse_bbc($text, true, '', $allowed_tags);

	// Fix for having a line break then a thingy.
	$text = strtr($text, array('<br /><div' => '<div', "\n" => '', "\r" => ''));

	// Note that IE doesn't understand spans really - make them something "legacy"
	$working_html = array(
		'~<del>(.+?)</del>~i' => '<strike>$1</strike>',
		'~<span\sclass="bbc_u">(.+?)</span>~i' => '<u>$1</u>',
		'~<span\sstyle="color:\s*([#\d\w]+);" class="bbc_color">(.+?)</span>~i' => '<font color="$1">$2</font>',
		'~<span\sstyle="font-family:\s*([#\d\w\s]+);" class="bbc_font">(.+?)</span>~i' => '<font face="$1">$2</font>',
		'~<div\sstyle="text-align:\s*(left|right);">(.+?)</div>~i' => '<p align="$1">$2</p>',
	);
	$text = preg_replace(array_keys($working_html), array_values($working_html), $text);

	// Parse unique ID's and disable javascript into the smileys - using the double space.
	$i = 1;
	$text = preg_replace('~(?:\s|&nbsp;)?<(img\ssrc="' . preg_quote($modSettings['smileys_url'], '~') . '/[^<>]+?/([^<>]+?)"\s*)[^<>]*?class="smiley" />~e', '\'<\' . ' . 'stripslashes(\'$1\') . \'alt="" title="" onresizestart="return false;" id="smiley_\' . ' . "\$" . 'i++ . \'_$2" style="padding: 0 3px 0 3px;" />\'', $text);

	return $text;
}

// The harder one - wysiwyg to BBC!
function html_to_bbc($text)
{
	global $modSettings, $smcFunc, $sourcedir, $scripturl, $context;

	// Replace newlines with spaces, as that's how browsers usually interpret them.
	$text = preg_replace("~\s*[\r\n]+\s*~", ' ', $text);

	// Though some of us love paragraphs, the parser will do better with breaks.
	$text = preg_replace('~</p>\s*?<p~i', '</p><br /><p', $text);
	$text = preg_replace('~</p>\s*(?!<)~i', '</p><br />', $text);

	// Safari/webkit wraps lines in Wysiwyg in <div>'s.
	if ($context['browser']['is_webkit'])
		$text = preg_replace(array('~<div(?:\s(?:[^<>]*?))?' . '>~i', '</div>'), array('<br />', ''), $text);

	// If there's a trailing break get rid of it - Firefox tends to add one.
	$text = preg_replace('~<br\s?/?' . '>$~i', '', $text);

	// Remove any formatting within code tags.
	if (strpos($text, '[code') !== false)
	{
		$text = preg_replace('~<br\s?/?' . '>~i', '#smf_br_spec_grudge_cool!#', $text);
		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

		// Only mess with stuff outside [code] tags.
		for ($i = 0, $n = count($parts); $i < $n; $i++)
		{
			// Value of 2 means we're inside the tag.
			if ($i % 4 == 2)
				$parts[$i] = strip_tags($parts[$i]);
		}

		$text = strtr(implode('', $parts), array('#smf_br_spec_grudge_cool!#' => '<br />'));
	}

	// Remove scripts, style and comment blocks.
	$text = preg_replace('~<script[^>]*[^/]?' . '>.*?</script>~i', '', $text);
	$text = preg_replace('~<style[^>]*[^/]?' . '>.*?</style>~i', '', $text);
	$text = preg_replace('~\\<\\!--.*?-->~i', '', $text);
	$text = preg_replace('~\\<\\!\\[CDATA\\[.*?\\]\\]\\>~i', '', $text);

	// Do the smileys ultra first!
	preg_match_all('~<img\s+[^<>]*?id="*smiley_\d+_([^<>]+?)[\s"/>]\s*[^<>]*?/*>(?:\s)?~i', $text, $matches);
	if (!empty($matches[0]))
	{
		// Easy if it's not custom.
		if (empty($modSettings['smiley_enable']))
		{
			$smileysfrom = array('>:D', ':D', '::)', '>:(', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', '0:)');
			$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');

			foreach ($matches[1] as $k => $file)
			{
				$found = array_search($file, $smileysto);
				// Note the weirdness here is to stop double spaces between smileys.
				if ($found)
					$matches[1][$k] = '-[]-smf_smily_start#|#' . htmlspecialchars($smileysfrom[$found]) . '-[]-smf_smily_end#|#';
				else
					$matches[1][$k] = '';
			}
		}
		else
		{
			// Load all the smileys.
			$names = array();
			foreach ($matches[1] as $file)
				$names[] = $file;
			$names = array_unique($names);

			if (!empty($names))
			{
				$request = $smcFunc['db_query']('', '
					SELECT code, filename
					FROM {db_prefix}smileys
					WHERE filename IN ({array_string:smiley_filenames})',
					array(
						'smiley_filenames' => $names,
					)
				);
				$mappings = array();
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$mappings[$row['filename']] = htmlspecialchars($row['code']);
				$smcFunc['db_free_result']($request);

				foreach ($matches[1] as $k => $file)
					if (isset($mappings[$file]))
						$matches[1][$k] = '-[]-smf_smily_start#|#' . $mappings[$file] . '-[]-smf_smily_end#|#';
			}
		}

		// Replace the tags!
		$text = str_replace($matches[0], $matches[1], $text);

		// Now sort out spaces
		$text = str_replace(array('-[]-smf_smily_end#|#-[]-smf_smily_start#|#', '-[]-smf_smily_end#|#', '-[]-smf_smily_start#|#'), ' ', $text);
	}

	// Only try to buy more time if the client didn't quit.
	if (connection_aborted() && $context['server']['is_apache'])
		@apache_reset_timeout();

	$parts = preg_split('~(<[A-Za-z]+\s*[^<>]*?style="?[^<>"]+"?[^<>]*?(?:/?)>|</[A-Za-z]+>)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	$replacement = '';
	$stack = array();

	foreach ($parts as $part)
	{
		if (preg_match('~(<([A-Za-z]+)\s*[^<>]*?)style="?([^<>"]+)"?([^<>]*?(/?)>)~', $part, $matches) === 1)
		{
			// If it's being closed instantly, we can't deal with it...yet.
			if ($matches[5] === '/')
				continue;
			else
			{
				// Get an array of styles that apply to this element. (The strtr is there to combat HTML generated by Word.)
				$styles = explode(';', strtr($matches[3], array('&quot;' => '')));
				$curElement = $matches[2];
				$precedingStyle = $matches[1];
				$afterStyle = $matches[4];
				$curCloseTags = '';
				$extra_attr = '';

				foreach ($styles as $type_value_pair)
				{
					// Remove spaces and convert uppercase letters.
					$clean_type_value_pair = strtolower(strtr(trim($type_value_pair), '=', ':'));

					// Something like 'font-weight: bold' is expected here.
					if (strpos($clean_type_value_pair, ':') === false)
						continue;

					// Capture the elements of a single style item (e.g. 'font-weight' and 'bold').
					list ($style_type, $style_value) = explode(':', $type_value_pair);

					$style_value = trim($style_value);

					switch (trim($style_type))
					{
						case 'font-weight':
							if ($style_value === 'bold')
							{
								$curCloseTags .= '[/b]';
								$replacement .= '[b]';
							}
						break;

						case 'text-decoration':
							if ($style_value == 'underline')
							{
								$curCloseTags .= '[/u]';
								$replacement .= '[u]';
							}
							elseif ($style_value == 'line-through')
							{
								$curCloseTags .= '[/s]';
								$replacement .= '[s]';
							}
						break;

						case 'text-align':
							if ($style_value == 'left')
							{
								$curCloseTags .= '[/left]';
								$replacement .= '[left]';
							}
							elseif ($style_value == 'center')
							{
								$curCloseTags .= '[/center]';
								$replacement .= '[center]';
							}
							elseif ($style_value == 'right')
							{
								$curCloseTags .= '[/right]';
								$replacement .= '[right]';
							}
						break;

						case 'font-style':
							if ($style_value == 'italic')
							{
								$curCloseTags .= '[/i]';
								$replacement .= '[i]';
							}
						break;

						case 'color':
							$curCloseTags .= '[/color]';
							$replacement .= '[color=' . $style_value . ']';
						break;

						case 'font-size':
							// Sometimes people put decimals where decimals should not be.
							if (preg_match('~(\d)+\.\d+(p[xt])~i', $style_value, $dec_matches) === 1)
								$style_value = $dec_matches[1] . $dec_matches[2];

							$curCloseTags .= '[/size]';
							$replacement .= '[size=' . $style_value . ']';
						break;

						case 'font-family':
							// Only get the first freaking font if there's a list!
							if (strpos($style_value, ',') !== false)
								$style_value = substr($style_value, 0, strpos($style_value, ','));

							$curCloseTags .= '[/font]';
							$replacement .= '[font=' . strtr($style_value, array("'" => '')) . ']';
						break;

						// This is a hack for images with dimensions embedded.
						case 'width':
						case 'height':
							if (preg_match('~[1-9]\d*~i', $style_value, $dimension) === 1)
								$extra_attr .= ' ' . $style_type . '="' . $dimension[0] . '"';
						break;

						case 'list-style-type':
							if (preg_match('~none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha~i', $style_value, $listType) === 1)
								$extra_attr .= ' listtype="' . $listType[0] . '"';
						break;
					}
				}

				// Preserve some tags stripping the styling.
				if (in_array($matches[2], array('a', 'font')))
				{
					$replacement .= $precedingStyle . $afterStyle;
					$curCloseTags = '</' . $matches[2] . '>' . $curCloseTags;
				}

				// If there's something that still needs closing, push it to the stack.
				if (!empty($curCloseTags))
					array_push($stack, array(
							'element' => strtolower($curElement),
							'closeTags' => $curCloseTags
						)
					);
				elseif (!empty($extra_attr))
					$replacement .= $precedingStyle . $extra_attr . $afterStyle;
			}
		}

		elseif (preg_match('~</([A-Za-z]+)>~', $part, $matches) === 1)
		{
			// Is this the element that we've been waiting for to be closed?
			if (!empty($stack) && strtolower($matches[1]) === $stack[count($stack) - 1]['element'])
			{
				$byebyeTag = array_pop($stack);
				$replacement .= $byebyeTag['closeTags'];
			}

			// Must've been something else.
			else
				$replacement .= $part;
		}
		// In all other cases, just add the part to the replacement.
		else
			$replacement .= $part;
	}

	// Now put back the replacement in the text.
	$text = $replacement;

	// We are not finished yet, request more time.
	if (connection_aborted() && $context['server']['is_apache'])
		@apache_reset_timeout();

	// Let's pull out any legacy alignments.
	while (preg_match('~<([A-Za-z]+)\s+[^<>]*?(align="*(left|center|right)"*)[^<>]*?(/?)>~i', $text, $matches) === 1)
	{
		// Find the position in the text of this tag over again.
		$start_pos = strpos($text, $matches[0]);
		if ($start_pos === false)
			break;

		// End tag?
		if ($matches[4] != '/' && strpos($text, '</' . $matches[1] . '>', $start_pos) !== false)
		{
			$end_length = strlen('</' . $matches[1] . '>');
			$end_pos = strpos($text, '</' . $matches[1] . '>', $start_pos);

			// Remove the align from that tag so it's never checked again.
			$tag = substr($text, $start_pos, strlen($matches[0]));
			$content = substr($text, $start_pos + strlen($matches[0]), $end_pos - $start_pos - strlen($matches[0]));
			$tag = str_replace($matches[2], '', $tag);

			// Put the tags back into the body.
			$text = substr($text, 0, $start_pos) . $tag . '[' . $matches[3] . ']' . $content . '[/' . $matches[3] . ']' . substr($text, $end_pos);
		}
		else
		{
			// Just get rid of this evil tag.
			$text = substr($text, 0, $start_pos) . substr($text, $start_pos + strlen($matches[0]));
		}
	}

	// Let's do some special stuff for fonts - cause we all love fonts.
	while (preg_match('~<font\s+([^<>]*)>~i', $text, $matches) === 1)
	{
		// Find the position of this again.
		$start_pos = strpos($text, $matches[0]);
		$end_pos = false;
		if ($start_pos === false)
			break;

		// This must have an end tag - and we must find the right one.
		$lower_text = strtolower($text);

		$start_pos_test = $start_pos + 4;
		// How many starting tags must we find closing ones for first?
		$start_font_tag_stack = 0;
		while ($start_pos_test < strlen($text))
		{
			// Where is the next starting font?
			$next_start_pos = strpos($lower_text, '<font', $start_pos_test);
			$next_end_pos = strpos($lower_text, '</font>', $start_pos_test);

			// Did we past another starting tag before an end one?
			if ($next_start_pos !== false && $next_start_pos < $next_end_pos)
			{
				$start_font_tag_stack++;
				$start_pos_test = $next_start_pos + 4;
			}
			// Otherwise we have an end tag but not the right one?
			elseif ($start_font_tag_stack)
			{
				$start_font_tag_stack--;
				$start_pos_test = $next_end_pos + 4;
			}
			// Otherwise we're there!
			else
			{
				$end_pos = $next_end_pos;
				break;
			}
		}
		if ($end_pos === false)
			break;

		// Now work out what the attributes are.
		$attribs = fetchTagAttributes($matches[1]);
		$tags = array();
		foreach ($attribs as $s => $v)
		{
			if ($s == 'size')
				$tags[] = array('[size=' . (int) trim($v) . ']', '[/size]');
			elseif ($s == 'face')
				$tags[] = array('[font=' . trim(strtolower($v)) . ']', '[/font]');
			elseif ($s == 'color')
				$tags[] = array('[color=' . trim(strtolower($v)) . ']', '[/color]');
		}

		// As before add in our tags.
		$before = $after = '';
		foreach ($tags as $tag)
		{
			$before .= $tag[0];
			if (isset($tag[1]))
				$after = $tag[1] . $after;
		}

		// Remove the tag so it's never checked again.
		$content = substr($text, $start_pos + strlen($matches[0]), $end_pos - $start_pos - strlen($matches[0]));

		// Put the tags back into the body.
		$text = substr($text, 0, $start_pos) . $before . $content . $after . substr($text, $end_pos + 7);
	}

	// Almost there, just a little more time.
	if (connection_aborted() && $context['server']['is_apache'])
		@apache_reset_timeout();

	if (count($parts = preg_split('~<(/?)(li|ol|ul)([^>]*)>~i', $text, null, PREG_SPLIT_DELIM_CAPTURE)) > 1)
	{
		// A toggle that dermines whether we're directly under a <ol> or <ul>.
		$inList = false;

		// Keep track of the number of nested list levels.
		$listDepth = 0;

		// Map what we can expect from the HTML to what is supported by SMF.
		$listTypeMapping = array(
			'1' => 'decimal',
			'A' => 'upper-alpha',
			'a' => 'lower-alpha',
			'I' => 'upper-roman',
			'i' => 'lower-roman',
			'disc' => 'disc',
			'square' => 'square',
			'circle' => 'circle',
		);

		// $i: text, $i + 1: '/', $i + 2: tag, $i + 3: tail.
		for ($i = 0, $numParts = count($parts) - 1; $i < $numParts; $i += 4)
		{
			$tag = strtolower($parts[$i + 2]);
			$isOpeningTag = $parts[$i + 1] === '';

			if ($isOpeningTag)
			{
				switch ($tag)
				{
					case 'ol':
					case 'ul':

						// We have a problem, we're already in a list.
						if ($inList)
						{
							// Inject a list opener, we'll deal with the ol/ul next loop.
							array_splice($parts, $i, 0, array(
								'',
								'',
								str_repeat("\t", $listDepth) . '[li]',
								'',
							));
							$numParts = count($parts) - 1;

							// The inlist status changes a bit.
							$inList = false;
						}

						// Just starting a new list.
						else
						{
							$inList = true;

							if ($tag === 'ol')
								$listType = 'decimal';
							elseif (preg_match('~type="?(' . implode('|', array_keys($listTypeMapping)) . ')"?~', $parts[$i + 3], $match) === 1)
								$listType = $listTypeMapping[$match[1]];
							else
								$listType = null;

							$listDepth++;

							$parts[$i + 2] = '[list' . ($listType === null ? '' : ' type=' . $listType) . ']' . "\n";
							$parts[$i + 3] = '';
						}
					break;

					case 'li':

						// This is how it should be: a list item inside the list.
						if ($inList)
						{
							$parts[$i + 2] = str_repeat("\t", $listDepth) . '[li]';
							$parts[$i + 3] = '';

							// Within a list item, it's almost as if you're outside.
							$inList = false;
						}

						// The li is no direct child of a list.
						else
						{
							// We are apparently in a list item.
							if ($listDepth > 0)
							{
								$parts[$i + 2] = '[/li]' . "\n" . str_repeat("\t", $listDepth) . '[li]';
								$parts[$i + 3] = '';
							}

							// We're not even near a list.
							else
							{
								// Quickly create a list with an item.
								$listDepth++;

								$parts[$i + 2] = '[list]' . "\n\t" . '[li]';
								$parts[$i + 3] = '';
							}
						}

					break;
				}
			}

			// Handle all the closing tags.
			else
			{
				switch ($tag)
				{
					case 'ol':
					case 'ul':

						// As we expected it, closing the list while we're in it.
						if ($inList)
						{
							$inList = false;

							$listDepth--;

							$parts[$i + 1] = '';
							$parts[$i + 2] = str_repeat("\t", $listDepth) . '[/list]';
							$parts[$i + 3] = '';
						}

						else
						{
							// We're in a list item.
							if ($listDepth > 0)
							{
								// Inject closure for this list item first.
								// The content of $parts[$i] is left as is!
								array_splice($parts, $i + 1, 0, array(
									'',				// $i + 1
									'[/li]' . "\n",	// $i + 2
									'',				// $i + 3
									'',				// $i + 4
								));
								$numParts = count($parts) - 1;

								// Now that we've closed the li, we're in list space.
								$inList = true;
							}

							// We're not even in a list, ignore
							else
							{
								$parts[$i + 1] = '';
								$parts[$i + 2] = '';
								$parts[$i + 3] = '';
							}
						}
					break;

					case 'li':

						if ($inList)
						{
							// There's no use for a </li> after <ol> or <ul>, ignore.
							$parts[$i + 1] = '';
							$parts[$i + 2] = '';
							$parts[$i + 3] = '';
						}

						else
						{
							// Remove the trailing breaks from the list item.
							$parts[$i] = preg_replace('~\s*<br\s*' . '/?' . '>\s*$~', '', $parts[$i]);
							$parts[$i + 1] = '';
							$parts[$i + 2] = '[/li]' . "\n";
							$parts[$i + 3] = '';

							// And we're back in the [list] space.
							$inList = true;
						}

					break;
				}
			}

			// If we're in the [list] space, no content is allowed.
			if ($inList && trim(preg_replace('~\s*<br\s*' . '/?' . '>\s*~', '', $parts[$i + 4])) !== '')
			{
				// Fix it by injecting an extra list item.
				array_splice($parts, $i + 4, 0, array(
					'', // No content.
					'', // Opening tag.
					'li', // It's a <li>.
					'', // No tail.
				));
				$numParts = count($parts) - 1;
			}
		}

		$text = implode('', $parts);

		if ($inList)
		{
			$listDepth--;
			$text .= str_repeat("\t", $listDepth) . '[/list]';
		}

		for ($i = $listDepth; $i > 0; $i--)
			$text .= '[/li]' . "\n" . str_repeat("\t", $i - 1) . '[/list]';

	}

	// I love my own image...
	while (preg_match('~<img\s+([^<>]*)/*>~i', $text, $matches) === 1)
	{
		// Find the position of the image.
		$start_pos = strpos($text, $matches[0]);
		if ($start_pos === false)
			break;
		$end_pos = $start_pos + strlen($matches[0]);

		$params = '';
		$had_params = array();
		$src = '';

		$attrs = fetchTagAttributes($matches[1]);
		foreach ($attrs as $attrib => $value)
		{
			if (in_array($attrib, array('width', 'height')))
				$params .= ' ' . $attrib . '=' . (int) $value;
			elseif ($attrib == 'alt' && trim($value) != '')
				$params .= ' alt=' . trim($value);
			elseif ($attrib == 'src')
				$src = trim($value);
		}

		$tag = '';
		if (!empty($src))
		{
			// Attempt to fix the path in case it's not present.
			if (preg_match('~^https?://~i', $src) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
			{
				$baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

				if (substr($src, 0, 1) === '/')
					$src = $baseURL . $src;
				else
					$src = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $src;
			}

			$tag = '[img' . $params . ']' . $src . '[/img]';
		}

		// Replace the tag
		$text = substr($text, 0, $start_pos) . $tag . substr($text, $end_pos);
	}

	// The final bits are the easy ones - tags which map to tags which map to tags - etc etc.
	$tags = array(
		'~<b(\s(.)*?)*?' . '>~i' => '[b]',
		'~</b>~i' => '[/b]',
		'~<i(\s(.)*?)*?' . '>~i' => '[i]',
		'~</i>~i' => '[/i]',
		'~<u(\s(.)*?)*?' . '>~i' => '[u]',
		'~</u>~i' => '[/u]',
		'~<strong(\s(.)*?)*?' . '>~i' => '[b]',
		'~</strong>~i' => '[/b]',
		'~<em(\s(.)*?)*?' . '>~i' => '[i]',
		'~</em>~i' => '[/i]',
		'~<s(\s(.)*?)*?' . '>~i' => "[s]",
		'~</s>~i' => "[/s]",
		'~<strike(\s(.)*?)*?' . '>~i' => '[s]',
		'~</strike>~i' => '[/s]',
		'~<del(\s(.)*?)*?' . '>~i' => '[s]',
		'~</del>~i' => '[/s]',
		'~<center(\s(.)*?)*?' . '>~i' => '[center]',
		'~</center>~i' => '[/center]',
		'~<pre(\s(.)*?)*?' . '>~i' => '[pre]',
		'~</pre>~i' => '[/pre]',
		'~<sub(\s(.)*?)*?' . '>~i' => '[sub]',
		'~</sub>~i' => '[/sub]',
		'~<sup(\s(.)*?)*?' . '>~i' => '[sup]',
		'~</sup>~i' => '[/sup]',
		'~<tt(\s(.)*?)*?' . '>~i' => '[tt]',
		'~</tt>~i' => '[/tt]',
		'~<table(\s(.)*?)*?' . '>~i' => '[table]',
		'~</table>~i' => '[/table]',
		'~<tr(\s(.)*?)*?' . '>~i' => '[tr]',
		'~</tr>~i' => '[/tr]',
		'~<(td|th)\s[^<>]*?colspan="?(\d{1,2})"?.*?' . '>~ie' => 'str_repeat(\'[td][/td]\', $2 - 1) . \'[td]\'',
		'~<(td|th)(\s(.)*?)*?' . '>~i' => '[td]',
		'~</(td|th)>~i' => '[/td]',
		'~<br(?:\s[^<>]*?)?' . '>~i' => "\n",
		'~<hr[^<>]*>(\n)?~i' => "[hr]\n$1",
		'~(\n)?\\[hr\\]~i' => "\n[hr]",
		'~^\n\\[hr\\]~i' => "[hr]",
		'~<blockquote(\s(.)*?)*?' . '>~i' => "&lt;blockquote&gt;",
		'~</blockquote>~i' => "&lt;/blockquote&gt;",
		'~<ins(\s(.)*?)*?' . '>~i' => "&lt;ins&gt;",
		'~</ins>~i' => "&lt;/ins&gt;",
	);
	$text = preg_replace(array_keys($tags), array_values($tags), $text);

	// Please give us just a little more time.
	if (connection_aborted() && $context['server']['is_apache'])
		@apache_reset_timeout();

	// What about URL's - the pain in the ass of the tag world.
	while (preg_match('~<a\s+([^<>]*)>([^<>]*)</a>~i', $text, $matches) === 1)
	{
		// Find the position of the URL.
		$start_pos = strpos($text, $matches[0]);
		if ($start_pos === false)
			break;
		$end_pos = $start_pos + strlen($matches[0]);

		$tag_type = 'url';
		$href = '';

		$attrs = fetchTagAttributes($matches[1]);
		foreach ($attrs as $attrib => $value)
		{
			if ($attrib == 'href')
			{
				$href = trim($value);

				// Are we dealing with an FTP link?
				if (preg_match('~^ftps?://~', $href) === 1)
					$tag_type = 'ftp';

				// Or is this a link to an email address?
				elseif (substr($href, 0, 7) == 'mailto:')
				{
					$tag_type = 'email';
					$href = substr($href, 7);
				}

				// No http(s), so attempt to fix this potential relative URL.
				elseif (preg_match('~^https?://~i', $href) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
				{
					$baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

					if (substr($href, 0, 1) === '/')
						$href = $baseURL . $href;
					else
						$href = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $href;
				}
			}

			// External URL?
			if ($attrib == 'target' && $tag_type == 'url')
			{
				if (trim($value) == '_blank')
					$tag_type == 'iurl';
			}
		}

		$tag = '';
		if ($href != '')
		{
			if ($matches[2] == $href)
				$tag = '[' . $tag_type . ']' . $href . '[/' . $tag_type . ']';
			else
				$tag = '[' . $tag_type . '=' . $href . ']' . $matches[2] . '[/' . $tag_type . ']';
		}

		// Replace the tag
		$text = substr($text, 0, $start_pos) . $tag . substr($text, $end_pos);
	}

	$text = strip_tags($text);

	// Some tags often end up as just dummy tags - remove those.
	$text = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $text);

	// Fix up entities.
	$text = preg_replace('~&#38;~i', '&#38;#38;', $text);

	$text = legalise_bbc($text);

	return $text;
}

// Returns an array of attributes associated with a tag.
function fetchTagAttributes($text)
{
	$attribs = array();
	$key = $value = '';
	$strpos = 0;
	$tag_state = 0; // 0 = key, 1 = attribute with no string, 2 = attribute with string
	for ($i = 0; $i < strlen($text); $i++)
	{
		// We're either moving from the key to the attribute or we're in a string and this is fine.
		if ($text{$i} == '=')
		{
			if ($tag_state == 0)
				$tag_state = 1;
			elseif ($tag_state == 2)
				$value .= '=';
		}
		// A space is either moving from an attribute back to a potential key or in a string is fine.
		elseif ($text{$i} == ' ')
		{
			if ($tag_state == 2)
				$value .= ' ';
			elseif ($tag_state == 1)
			{
				$attribs[$key] = $value;
				$key = $value = '';
				$tag_state = 0;
			}
		}
		// A quote?
		elseif ($text{$i} == '"')
		{
			// Must be either going into or out of a string.
			if ($tag_state == 1)
				$tag_state = 2;
			else
				$tag_state = 1;
		}
		// Otherwise it's fine.
		else
		{
			if ($tag_state == 0)
				$key .= $text{$i};
			else
				$value .= $text{$i};
		}
	}

	// Anything left?
	if ($key != '' && $value != '')
		$attribs[$key] = $value;

	return $attribs;
}

function getMessageIcons($board_id)
{
	global $modSettings, $context, $txt, $settings, $smcFunc;

	if (empty($modSettings['messageIcons_enable']))
	{
		loadLanguage('Post');

		$icons = array(
			array('value' => 'xx', 'name' => $txt['standard']),
			array('value' => 'thumbup', 'name' => $txt['thumbs_up']),
			array('value' => 'thumbdown', 'name' => $txt['thumbs_down']),
			array('value' => 'exclamation', 'name' => $txt['excamation_point']),
			array('value' => 'question', 'name' => $txt['question_mark']),
			array('value' => 'lamp', 'name' => $txt['lamp']),
			array('value' => 'smiley', 'name' => $txt['icon_smiley']),
			array('value' => 'angry', 'name' => $txt['icon_angry']),
			array('value' => 'cheesy', 'name' => $txt['icon_cheesy']),
			array('value' => 'grin', 'name' => $txt['icon_grin']),
			array('value' => 'sad', 'name' => $txt['icon_sad']),
			array('value' => 'wink', 'name' => $txt['icon_wink'])
		);

		foreach ($icons as $k => $dummy)
		{
			$icons[$k]['url'] = $settings['images_url'] . '/post/' . $dummy['value'] . '.gif';
			$icons[$k]['is_last'] = false;
		}
	}
	// Otherwise load the icons, and check we give the right image too...
	else
	{
		if (($temp = cache_get_data('posting_icons-' . $board_id, 480)) == null)
		{
			$request = $smcFunc['db_query']('select_message_icons', '
				SELECT title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})',
				array(
					'board_id' => $board_id,
				)
			);
			$icon_data = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$icon_data[] = $row;
			$smcFunc['db_free_result']($request);

			cache_put_data('posting_icons-' . $board_id, $icon_data, 480);
		}
		else
			$icon_data = $temp;

		$icons = array();
		foreach ($icon_data as $icon)
		{
			$icons[$icon['filename']] = array(
				'value' => $icon['filename'],
				'name' => $icon['title'],
				'url' => $settings[file_exists($settings['theme_dir'] . '/images/post/' . $icon['filename'] . '.gif') ? 'images_url' : 'default_images_url'] . '/post/' . $icon['filename'] . '.gif',
				'is_last' => false,
			);
		}
	}

	return array_values($icons);
}

// This is an important yet frustrating function - it attempts to clean up illegal BBC caused by browsers like Opera which don't obey the rules!!!
function legalise_bbc($text)
{
	global $modSettings;

	// Don't care about the texts that are too short.
	if (strlen($text) < 3)
		return $text;

	// We are going to cycle through the BBC and keep track of tags as they arise - in order. If get to a block level tag we're going to make sure it's not in a non-block level tag!
	// This will keep the order of tags that are open.
	$current_tags = array();

	// This will quickly let us see if the tag is active.
	$active_tags = array();

	// A list of tags that's disabled by the admin.
	$disabled = empty($modSettings['disabledBBC']) ? array() : array_flip(explode(',', strtolower($modSettings['disabledBBC'])));

	// Add flash if it's disabled as embedded tag.
	if (empty($modSettings['enableEmbeddedFlash']))
		$disabled['flash'] = true;

	// Get a list of all the tags that are not disabled.
	$all_tags = parse_bbc(false);
	$valid_tags = array();
	$self_closing_tags = array();
	foreach ($all_tags as $tag)
	{
		if (!isset($disabled[$tag['tag']]))
			$valid_tags[$tag['tag']] = !empty($tag['block_level']);
		if (isset($tag['type']) && $tag['type'] == 'closed')
			$self_closing_tags[] = $tag['tag'];
	}

	// Don't worry if we're in a code/nobbc.
	$in_code_nobbc = false;

	// Right - we're going to start by going through the whole lot to make sure we don't have align stuff crossed as this happens load and is stupid!
	$align_tags = array('left', 'center', 'right', 'pre');

	// Remove those align tags that are not valid.
	$align_tags = array_intersect($align_tags, array_keys($valid_tags));

	// These keep track of where we are!
	if (!empty($align_tags) && count($matches = preg_split('~(\\[/?(?:' . implode('|', $align_tags) . ')\\])~', $text, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1)
	{
		// The first one is never a tag.
		$isTag = false;

		// By default we're not inside a tag too.
		$insideTag = null;

		foreach ($matches as $i => $match)
		{
			// We're only interested in tags, not text.
			if ($isTag)
			{
				$isClosingTag = substr($match, 1, 1) === '/';
				$tagName = substr($match, $isClosingTag ? 2 : 1, -1);

				// We're closing the exact same tag that we opened.
				if ($isClosingTag && $insideTag === $tagName)
					$insideTag = null;

				// We're opening a tag and we're not yet inside one either
				elseif (!$isClosingTag && $insideTag === null)
					$insideTag = $tagName;

				// In all other cases, this tag must be invalid
				else
					unset($matches[$i]);
			}

			// The next one is gonna be the other one.
			$isTag = !$isTag;
		}

		// We're still inside a tag and had no chance for closure?
		if ($insideTag !== null)
			$matches[] = '[/' . $insideTag . ']';

		// And a complete text string again.
		$text = implode('', $matches);
	}

	// Quickly remove any tags which are back to back.
	$backToBackPattern = '~\\[(' . implode('|', array_diff(array_keys($valid_tags), array('td', 'anchor'))) . ')[^<>\\[\\]]*\\]\s*\\[/\\1\\]~';
	$lastlen = 0;
	while (strlen($text) !== $lastlen)
		$lastlen = strlen($text = preg_replace($backToBackPattern, '', $text));

	// Need to sort the tags my name length.
	uksort($valid_tags, 'sort_array_length');

	// These inline tags can compete with each other regarding style.
	$competing_tags = array(
		'color',
		'size',
	);

	// In case things changed above set these back to normal.
	$in_code_nobbc = false;
	$new_text_offset = 0;

	// These keep track of where we are!
	if (count($parts = preg_split(sprintf('~(\\[)(/?)(%1$s)((?:[\\s=][^\\]\\[]*)?\\])~', implode('|', array_keys($valid_tags))), $text, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1)
	{
		// Start with just text.
		$isTag = false;

		// Start outside [nobbc] or [code] blocks.
		$inCode = false;
		$inNoBbc = false;

		// A buffer containing all opened inline elements.
		$inlineElements = array();

		// A buffer containing all opened block elements.
		$blockElements = array();

		// A buffer containing the opened inline elements that might compete.
		$competingElements = array();

		// $i: text, $i + 1: '[', $i + 2: '/', $i + 3: tag, $i + 4: tag tail.
		for ($i = 0, $n = count($parts) - 1; $i < $n; $i += 5)
		{
			$tag = $parts[$i + 3];
			$isOpeningTag = $parts[$i + 2] === '';
			$isClosingTag = $parts[$i + 2] === '/';
			$isBlockLevelTag = isset($valid_tags[$tag]) && $valid_tags[$tag] && !in_array($tag, $self_closing_tags);
			$isCompetingTag = in_array($tag, $competing_tags);

			// Check if this might be one of those cleaned out tags.
			if ($tag === '')
				continue;

			// Special case: inside [code] blocks any code is left untouched.
			elseif ($tag === 'code')
			{
				// We're inside a code block and closing it.
				if ($inCode && $isClosingTag)
				{
					$inCode = false;

					// Reopen tags that were closed before the code block.
					if (!empty($inlineElements))
						$parts[$i + 4] .= '[' . implode('][', array_keys($inlineElements)) . ']';
				}

				// We're outside a coding and nobbc block and opening it.
				elseif (!$inCode && !$inNoBbc && $isOpeningTag)
				{
					// If there are still inline elements left open, close them now.
					if (!empty($inlineElements))
					{
						$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';
						//$inlineElements = array();
					}

					$inCode = true;
				}

				// Nothing further to do.
				continue;
			}

			// Special case: inside [nobbc] blocks any BBC is left untouched.
			elseif ($tag === 'nobbc')
			{
				// We're inside a nobbc block and closing it.
				if ($inNoBbc && $isClosingTag)
				{
					$inNoBbc = false;

					// Some inline elements might've been closed that need reopening.
					if (!empty($inlineElements))
						$parts[$i + 4] .= '[' . implode('][', array_keys($inlineElements)) . ']';
				}

				// We're outside a nobbc and coding block and opening it.
				elseif (!$inNoBbc && !$inCode && $isOpeningTag)
				{
					// Can't have inline elements still opened.
					if (!empty($inlineElements))
					{
						$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';
						//$inlineElements = array();
					}

					$inNoBbc = true;
				}

				continue;
			}

			// So, we're inside one of the special blocks: ignore any tag.
			elseif ($inCode || $inNoBbc)
				continue;

			// We're dealing with an opening tag.
			if ($isOpeningTag)
			{
				// Everyting inside the square brackets of the opening tag.
				$elementContent = $parts[$i + 3] . substr($parts[$i + 4], 0, -1);

				// A block level opening tag.
				if ($isBlockLevelTag)
				{
					// Are there inline elements still open?
					if (!empty($inlineElements))
					{
						// Close all the inline tags, a block tag is coming...
						$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

						// Now open them again, we're inside the block tag now.
						$parts[$i + 5] = '[' . implode('][', array_keys($inlineElements)) . ']' . $parts[$i + 5];
					}

					$blockElements[] = $tag;
				}

				// Inline opening tag.
				elseif (!in_array($tag, $self_closing_tags))
				{
					// Can't have two opening elements with the same contents!
					if (isset($inlineElements[$elementContent]))
					{
						// Get rid of this tag.
						$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';

						// Now try to find the corresponding closing tag.
						$curLevel = 1;
						for ($j = $i + 5, $m = count($parts) - 1; $j < $m; $j += 5)
						{
							// Find the tags with the same tagname
							if ($parts[$j + 3] === $tag)
							{
								// If it's an opening tag, increase the level.
								if ($parts[$j + 2] === '')
									$curLevel++;

								// A closing tag, decrease the level.
								else
								{
									$curLevel--;

									// Gotcha! Clean out this closing tag gone rogue.
									if ($curLevel === 0)
									{
										$parts[$j + 1] = $parts[$j + 2] = $parts[$j + 3] = $parts[$j + 4] = '';
										break;
									}
								}
							}
						}
					}

					// Otherwise, add this one to the list.
					else
					{
						if ($isCompetingTag)
						{
							if (!isset($competingElements[$tag]))
								$competingElements[$tag] = array();

							$competingElements[$tag][] = $parts[$i + 4];

							if (count($competingElements[$tag]) > 1)
								$parts[$i] .= '[/' . $tag . ']';
						}

						$inlineElements[$elementContent] = $tag;
					}
				}

			}

			// Closing tag.
			else
			{
				// Closing the block tag.
				if ($isBlockLevelTag)
				{
					// Close the elements that should've been closed by closing this tag.
					if (!empty($blockElements))
					{
						$addClosingTags = array();
						while ($element = array_pop($blockElements))
						{
							if ($element === $tag)
								break;

							// Still a block tag was open not equal to this tag.
							$addClosingTags[] = $element['type'];
						}

						if (!empty($addClosingTags))
							$parts[$i + 1] = '[/' . implode('][/', array_reverse($addClosingTags)) . ']' . $parts[$i + 1];

						// Apparently the closing tag was not found on the stack.
						if (!is_string($element) || $element !== $tag)
						{
							// Get rid of this particular closing tag, it was never opened.
							$parts[$i + 1] = substr($parts[$i + 1], 0, -1);
							$parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
							continue;
						}
					}
					else
					{
						// Get rid of this closing tag!
						$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
						continue;
					}

					// Inline elements are still left opened?
					if (!empty($inlineElements))
					{
						// Close them first..
						$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

						// Then reopen them.
						$parts[$i + 5] = '[' . implode('][', array_keys($inlineElements)) . ']' . $parts[$i + 5];
					}
				}
				// Inline tag.
				else
				{
					// Are we expecting this tag to end?
					if (in_array($tag, $inlineElements))
					{
						foreach (array_reverse($inlineElements, true) as $tagContentToBeClosed => $tagToBeClosed)
						{
							// Closing it one way or the other.
							unset($inlineElements[$tagContentToBeClosed]);

							// Was this the tag we were looking for?
							if ($tagToBeClosed === $tag)
								break;

							// Nope, close it and look further!
							else
								$parts[$i] .= '[/' . $tagToBeClosed . ']';
						}

						if ($isCompetingTag && !empty($competingElements[$tag]))
						{
							array_pop($competingElements[$tag]);

							if (count($competingElements[$tag]) > 0)
								$parts[$i + 5] = '[' . $tag . $competingElements[$tag][count($competingElements[$tag]) - 1] . $parts[$i + 5];
						}
					}

					// Unexpected closing tag, ex-ter-mi-nate.
					else
						$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
				}
			}
		}

		// Close the code tags.
		if ($inCode)
			$parts[$i] .= '[/code]';

		// The same for nobbc tags.
		elseif ($inNoBbc)
			$parts[$i] .= '[/nobbc]';

		// Still inline tags left unclosed? Close them now, better late than never.
		elseif (!empty($inlineElements))
			$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

		// Now close the block elements.
		if (!empty($blockElements))
			$parts[$i] .= '[/' . implode('][/', array_reverse($blockElements)) . ']';

		$text = implode('', $parts);
	}

	// Final clean up of back to back tags.
	$lastlen = 0;
	while (strlen($text) !== $lastlen)
		$lastlen = strlen($text = preg_replace($backToBackPattern, '', $text));

	return $text;
}

// A help function for legalise_bbc for sorting arrays based on length.
function sort_array_length($a, $b)
{
	return strlen($a) < strlen($b) ? 1 : -1;
}

// Compatibility function - used in 1.1 for showing a post box.
function theme_postbox($msg)
{
	global $context;

	return template_control_richedit($context['post_box_name']);
}

// Creates a box that can be used for richedit stuff like BBC, Smileys etc.
function create_control_richedit($editorOptions)
{
	global $txt, $modSettings, $options, $smcFunc;
	global $context, $settings, $user_info, $sourcedir, $scripturl;

	// Load the Post language file... for the moment at least.
	loadLanguage('Post');

	// Every control must have a ID!
	assert(isset($editorOptions['id']));
	assert(isset($editorOptions['value']));

	// Is this the first richedit - if so we need to ensure some template stuff is initialised.
	if (empty($context['controls']['richedit']))
	{
		// Some general stuff.
		$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];

		// This really has some WYSIWYG stuff.
		loadTemplate('GenericControls', $context['browser']['is_ie'] ? 'editor_ie' : 'editor');
		$context['html_headers'] .= '
		<script type="text/javascript"><!-- // --><![CDATA[
			var smf_smileys_url = \'' . $settings['smileys_url'] . '\';
			var oEditorStrings= {
				wont_work: \'' . addcslashes($txt['rich_edit_wont_work'], "'") . '\',
				func_disabled: \'' . addcslashes($txt['rich_edit_function_disabled'], "'") . '\',
				prompt_text_email: \'' . addcslashes($txt['prompt_text_email'], "'") . '\',
				prompt_text_ftp: \'' . addcslashes($txt['prompt_text_ftp'], "'") . '\',
				prompt_text_url: \'' . addcslashes($txt['prompt_text_url'], "'") . '\',
				prompt_text_img: \'' . addcslashes($txt['prompt_text_img'], "'") . '\'
			}
		// ]]></script>
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/editor.js?fin20"></script>';

		$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
		if ($context['show_spellchecking'])
		{
			$context['html_headers'] .= '
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/spellcheck.js"></script>';

			// Some hidden information is needed in order to make the spell checking work.
			if (!isset($_REQUEST['xml']))
				$context['insert_after_template'] .= '
		<form name="spell_form" id="spell_form" method="post" accept-charset="' . $context['character_set'] . '" target="spellWindow" action="' . $scripturl . '?action=spellcheck">
			<input type="hidden" name="spellstring" value="" />
		</form>';

			// Also make sure that spell check works with rich edit.
			$context['html_headers'] .= '
		<script type="text/javascript"><!-- // --><![CDATA[
		function spellCheckDone()
		{
			for (i = 0; i < smf_editorArray.length; i++)
				setTimeout("smf_editorArray[" + i + "].spellCheckEnd()", 150);
		}
		// ]]></script>';
		}
	}

	// Start off the editor...
	$context['controls']['richedit'][$editorOptions['id']] = array(
		'id' => $editorOptions['id'],
		'value' => $editorOptions['value'],
		'rich_value' => bbc_to_html($editorOptions['value']),
		'rich_active' => empty($modSettings['disable_wysiwyg']) && (!empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode'])),
		'disable_smiley_box' => !empty($editorOptions['disable_smiley_box']),
		'columns' => isset($editorOptions['columns']) ? $editorOptions['columns'] : 60,
		'rows' => isset($editorOptions['rows']) ? $editorOptions['rows'] : 12,
		'width' => isset($editorOptions['width']) ? $editorOptions['width'] : '70%',
		'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '150px',
		'form' => isset($editorOptions['form']) ? $editorOptions['form'] : 'postmodify',
		'bbc_level' => !empty($editorOptions['bbc_level']) ? $editorOptions['bbc_level'] : 'full',
		'preview_type' => isset($editorOptions['preview_type']) ? (int) $editorOptions['preview_type'] : 1,
		'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : array(),
	);

	// Switch between default images and back... mostly in case you don't have an PersonalMessage template, but do have a Post template.
	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$temp1 = $settings['theme_url'];
		$settings['theme_url'] = $settings['default_theme_url'];

		$temp2 = $settings['images_url'];
		$settings['images_url'] = $settings['default_images_url'];

		$temp3 = $settings['theme_dir'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}

	if (empty($context['bbc_tags']))
	{
		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		$context['bbc_tags'] = array();
		$context['bbc_tags'][] = array(
			array(
				'image' => 'bold',
				'code' => 'b',
				'before' => '[b]',
				'after' => '[/b]',
				'description' => $txt['bold'],
			),
			array(
				'image' => 'italicize',
				'code' => 'i',
				'before' => '[i]',
				'after' => '[/i]',
				'description' => $txt['italic'],
			),
			array(
				'image' => 'underline',
				'code' => 'u',
				'before' => '[u]',
				'after' => '[/u]',
				'description' => $txt['underline']
			),
			array(
				'image' => 'strike',
				'code' => 's',
				'before' => '[s]',
				'after' => '[/s]',
				'description' => $txt['strike']
			),
			array(),
			array(
				'image' => 'pre',
				'code' => 'pre',
				'before' => '[pre]',
				'after' => '[/pre]',
				'description' => $txt['preformatted']
			),
			array(
				'image' => 'left',
				'code' => 'left',
				'before' => '[left]',
				'after' => '[/left]',
				'description' => $txt['left_align']
			),
			array(
				'image' => 'center',
				'code' => 'center',
				'before' => '[center]',
				'after' => '[/center]',
				'description' => $txt['center']
			),
			array(
				'image' => 'right',
				'code' => 'right',
				'before' => '[right]',
				'after' => '[/right]',
				'description' => $txt['right_align']
			),
		);
		$context['bbc_tags'][] = array(
			array(
				'image' => 'flash',
				'code' => 'flash',
				'before' => '[flash=200,200]',
				'after' => '[/flash]',
				'description' => $txt['flash']
			),
			array(
				'image' => 'img',
				'code' => 'img',
				'before' => '[img]',
				'after' => '[/img]',
				'description' => $txt['image']
			),
			array(
				'image' => 'url',
				'code' => 'url',
				'before' => '[url]',
				'after' => '[/url]',
				'description' => $txt['hyperlink']
			),
			array(
				'image' => 'email',
				'code' => 'email',
				'before' => '[email]',
				'after' => '[/email]',
				'description' => $txt['insert_email']
			),
			array(
				'image' => 'ftp',
				'code' => 'ftp',
				'before' => '[ftp]',
				'after' => '[/ftp]',
				'description' => $txt['ftp']
			),
			array(),
			array(
				'image' => 'glow',
				'code' => 'glow',
				'before' => '[glow=red,2,300]',
				'after' => '[/glow]',
				'description' => $txt['glow']
			),
			array(
				'image' => 'shadow',
				'code' => 'shadow',
				'before' => '[shadow=red,left]',
				'after' => '[/shadow]',
				'description' => $txt['shadow']
			),
			array(
				'image' => 'move',
				'code' => 'move',
				'before' => '[move]',
				'after' => '[/move]',
				'description' => $txt['marquee']
			),
			array(),
			array(
				'image' => 'sup',
				'code' => 'sup',
				'before' => '[sup]',
				'after' => '[/sup]',
				'description' => $txt['superscript']
			),
			array(
				'image' => 'sub',
				'code' => 'sub',
				'before' => '[sub]',
				'after' => '[/sub]',
				'description' => $txt['subscript']
			),
			array(
				'image' => 'tele',
				'code' => 'tt',
				'before' => '[tt]',
				'after' => '[/tt]',
				'description' => $txt['teletype']
			),
			array(),
			array(
				'image' => 'table',
				'code' => 'table',
				'before' => '[table]\n[tr]\n[td]',
				'after' => '[/td]\n[/tr]\n[/table]',
				'description' => $txt['table']
			),
			array(
				'image' => 'code',
				'code' => 'code',
				'before' => '[code]',
				'after' => '[/code]',
				'description' => $txt['bbc_code']
			),
			array(
				'image' => 'quote',
				'code' => 'quote',
				'before' => '[quote]',
				'after' => '[/quote]',
				'description' => $txt['bbc_quote']
			),
			array(),
			array(
				'image' => 'list',
				'code' => 'list',
				'before' => '[list]\n[li]',
				'after' => '[/li]\n[li][/li]\n[/list]',
				'description' => $txt['list_unordered']
			),
			array(
				'image' => 'orderlist',
				'code' => 'orderlist',
				'before' => '[list type=decimal]\n[li]',
				'after' => '[/li]\n[li][/li]\n[/list]',
				'description' => $txt['list_ordered']
			),
			array(
				'image' => 'hr',
				'code' => 'hr',
				'before' => '[hr]',
				'description' => $txt['horizontal_rule']
			),
		);

		// Allow mods to modify BBC buttons.
		call_integration_hook('integrate_bbc_buttons', array(&$context['bbc_tags']));

		// Show the toggle?
		if (empty($modSettings['disable_wysiwyg']))
		{
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array();
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'image' => 'unformat',
				'code' => 'unformat',
				'before' => '',
				'description' => $txt['unformat_text'],
			);
			$context['bbc_tags'][count($context['bbc_tags']) - 1][] = array(
				'image' => 'toggle',
				'code' => 'toggle',
				'before' => '',
				'description' => $txt['toggle_view'],
			);
		}

		foreach ($context['bbc_tags'] as $row => $tagRow)
			$context['bbc_tags'][$row][count($tagRow) - 1]['isLast'] = true;
	}

	// Initialize smiley array... if not loaded before.
	if (empty($context['smileys']) && empty($editorOptions['disable_smiley_box']))
	{
		$context['smileys'] = array(
			'postform' => array(),
			'popup' => array(),
		);

		// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
		if (empty($modSettings['smiley_enable']) && $user_info['smiley_set'] != 'none')
			$context['smileys']['postform'][] = array(
				'smileys' => array(
					array(
						'code' => ':)',
						'filename' => 'smiley.gif',
						'description' => $txt['icon_smiley'],
					),
					array(
						'code' => ';)',
						'filename' => 'wink.gif',
						'description' => $txt['icon_wink'],
					),
					array(
						'code' => ':D',
						'filename' => 'cheesy.gif',
						'description' => $txt['icon_cheesy'],
					),
					array(
						'code' => ';D',
						'filename' => 'grin.gif',
						'description' => $txt['icon_grin']
					),
					array(
						'code' => '>:(',
						'filename' => 'angry.gif',
						'description' => $txt['icon_angry'],
					),
					array(
						'code' => ':(',
						'filename' => 'sad.gif',
						'description' => $txt['icon_sad'],
					),
					array(
						'code' => ':o',
						'filename' => 'shocked.gif',
						'description' => $txt['icon_shocked'],
					),
					array(
						'code' => '8)',
						'filename' => 'cool.gif',
						'description' => $txt['icon_cool'],
					),
					array(
						'code' => '???',
						'filename' => 'huh.gif',
						'description' => $txt['icon_huh'],
					),
					array(
						'code' => '::)',
						'filename' => 'rolleyes.gif',
						'description' => $txt['icon_rolleyes'],
					),
					array(
						'code' => ':P',
						'filename' => 'tongue.gif',
						'description' => $txt['icon_tongue'],
					),
					array(
						'code' => ':-[',
						'filename' => 'embarrassed.gif',
						'description' => $txt['icon_embarrassed'],
					),
					array(
						'code' => ':-X',
						'filename' => 'lipsrsealed.gif',
						'description' => $txt['icon_lips'],
					),
					array(
						'code' => ':-\\',
						'filename' => 'undecided.gif',
						'description' => $txt['icon_undecided'],
					),
					array(
						'code' => ':-*',
						'filename' => 'kiss.gif',
						'description' => $txt['icon_kiss'],
					),
					array(
						'code' => ':\'(',
						'filename' => 'cry.gif',
						'description' => $txt['icon_cry'],
						'isLast' => true,
					),
				),
				'isLast' => true,
			);
		elseif ($user_info['smiley_set'] != 'none')
		{
			if (($temp = cache_get_data('posting_smileys', 480)) == null)
			{
				$request = $smcFunc['db_query']('', '
					SELECT code, filename, description, smiley_row, hidden
					FROM {db_prefix}smileys
					WHERE hidden IN (0, 2)
					ORDER BY smiley_row, smiley_order',
					array(
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$row['filename'] = htmlspecialchars($row['filename']);
					$row['description'] = htmlspecialchars($row['description']);

					$context['smileys'][empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
				}
				$smcFunc['db_free_result']($request);

				foreach ($context['smileys'] as $section => $smileyRows)
				{
					foreach ($smileyRows as $rowIndex => $smileys)
						$context['smileys'][$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;

					if (!empty($smileyRows))
						$context['smileys'][$section][count($smileyRows) - 1]['isLast'] = true;
				}

				cache_put_data('posting_smileys', $context['smileys'], 480);
			}
			else
				$context['smileys'] = $temp;
		}
	}

	// Set a flag so the sub template knows what to do...
	$context['show_bbc'] = !empty($modSettings['enableBBC']) && !empty($settings['show_bbc']);

	// Generate a list of buttons that shouldn't be shown - this should be the fastest way to do this.
	$disabled_tags = array();
	if (!empty($modSettings['disabledBBC']))
		$disabled_tags = explode(',', $modSettings['disabledBBC']);
	if (empty($modSettings['enableEmbeddedFlash']))
		$disabled_tags[] = 'flash';

	foreach ($disabled_tags as $tag)
	{
		if ($tag == 'list')
			$context['disabled_tags']['orderlist'] = true;

		$context['disabled_tags'][trim($tag)] = true;
	}

	// Switch the URLs back... now we're back to whatever the main sub template is.  (like folder in PersonalMessage.)
	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'defaults' && isset($settings['default_template']))
	{
		$settings['theme_url'] = $temp1;
		$settings['images_url'] = $temp2;
		$settings['theme_dir'] = $temp3;
	}
}

// Create a anti-bot verification control?
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $txt, $modSettings, $options, $smcFunc;
	global $context, $settings, $user_info, $sourcedir, $scripturl;

	// First verification means we need to set up some bits...
	if (empty($context['controls']['verification']))
	{
		// The template
		loadTemplate('GenericControls');

		// Some javascript ma'am?
		if (!empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])))
			$context['html_headers'] .= '
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/captcha.js"></script>';

		$context['use_graphic_library'] = in_array('gd', get_loaded_extensions());

		// Skip I, J, L, O, Q, S and Z.
		$context['standard_captcha_range'] = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));
	}

	// Always have an ID.
	assert(isset($verificationOptions['id']));
	$isNew = !isset($context['controls']['verification'][$verificationOptions['id']]);

	// Log this into our collection.
	if ($isNew)
		$context['controls']['verification'][$verificationOptions['id']] = array(
			'id' => $verificationOptions['id'],
			'show_visual' => !empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])),
			'number_questions' => isset($verificationOptions['override_qs']) ? $verificationOptions['override_qs'] : (!empty($modSettings['qa_verification_number']) ? $modSettings['qa_verification_number'] : 0),
			'max_errors' => isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3,
			'image_href' => $scripturl . '?action=verificationcode;vid=' . $verificationOptions['id'] . ';rand=' . md5(mt_rand()),
			'text_value' => '',
			'questions' => array(),
		);
	$thisVerification = &$context['controls']['verification'][$verificationOptions['id']];

	// Add javascript for the object.
	if ($context['controls']['verification'][$verificationOptions['id']]['show_visual'] && !WIRELESS)
		$context['insert_after_template'] .= '
			<script type="text/javascript"><!-- // --><![CDATA[
				var verification' . $verificationOptions['id'] . 'Handle = new smfCaptcha("' . $thisVerification['image_href'] . '", "' . $verificationOptions['id'] . '", ' . ($context['use_graphic_library'] ? 1 : 0) . ');
			// ]]></script>';

	// Is there actually going to be anything?
	if (empty($thisVerification['show_visual']) && empty($thisVerification['number_questions']))
		return false;
	elseif (!$isNew && !$do_test)
		return true;

	// If we want questions do we have a cache of all the IDs?
	if (!empty($thisVerification['number_questions']) && empty($modSettings['question_id_cache']))
	{
		if (($modSettings['question_id_cache'] = cache_get_data('verificationQuestionIds', 300)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_comment
				FROM {db_prefix}log_comments
				WHERE comment_type = {string:ver_test}',
				array(
					'ver_test' => 'ver_test',
				)
			);
			$modSettings['question_id_cache'] = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$modSettings['question_id_cache'][] = $row['id_comment'];
			$smcFunc['db_free_result']($request);

			if (!empty($modSettings['cache_enable']))
				cache_put_data('verificationQuestionIds', $modSettings['question_id_cache'], 300);
		}
	}

	if (!isset($_SESSION[$verificationOptions['id'] . '_vv']))
		$_SESSION[$verificationOptions['id'] . '_vv'] = array();

	// Do we need to refresh the verification?
	if (!$do_test && (!empty($_SESSION[$verificationOptions['id'] . '_vv']['did_pass']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) || $_SESSION[$verificationOptions['id'] . '_vv']['count'] > 3) && empty($verificationOptions['dont_refresh']))
		$force_refresh = true;
	else
		$force_refresh = false;

	// This can also force a fresh, although unlikely.
	if (($thisVerification['show_visual'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['code'])) || ($thisVerification['number_questions'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['q'])))
		$force_refresh = true;

	$verification_errors = array();

	// Start with any testing.
	if ($do_test)
	{
		// This cannot happen!
		if (!isset($_SESSION[$verificationOptions['id'] . '_vv']['count']))
			fatal_lang_error('no_access', false);
		// ... nor this!
		if ($thisVerification['number_questions'] && (!isset($_SESSION[$verificationOptions['id'] . '_vv']['q']) || !isset($_REQUEST[$verificationOptions['id'] . '_vv']['q'])))
			fatal_lang_error('no_access', false);

		if ($thisVerification['show_visual'] && (empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['code']) || strtoupper($_REQUEST[$verificationOptions['id'] . '_vv']['code']) !== $_SESSION[$verificationOptions['id'] . '_vv']['code']))
			$verification_errors[] = 'wrong_verification_code';
		if ($thisVerification['number_questions'])
		{
			// Get the answers and see if they are all right!
			$request = $smcFunc['db_query']('', '
				SELECT id_comment, recipient_name AS answer
				FROM {db_prefix}log_comments
				WHERE comment_type = {string:ver_test}
					AND id_comment IN ({array_int:comment_ids})',
				array(
					'ver_test' => 'ver_test',
					'comment_ids' => $_SESSION[$verificationOptions['id'] . '_vv']['q'],
				)
			);
			$incorrectQuestions = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (empty($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) || trim($smcFunc['htmlspecialchars'](strtolower($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]))) != strtolower($row['answer']))
					$incorrectQuestions[] = $row['id_comment'];
			}
			$smcFunc['db_free_result']($request);

			if (!empty($incorrectQuestions))
				$verification_errors[] = 'wrong_verification_answer';
		}
	}

	// Any errors means we refresh potentially.
	if (!empty($verification_errors))
	{
		if (empty($_SESSION[$verificationOptions['id'] . '_vv']['errors']))
			$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		// Too many errors?
		elseif ($_SESSION[$verificationOptions['id'] . '_vv']['errors'] > $thisVerification['max_errors'])
			$force_refresh = true;

		// Keep a track of these.
		$_SESSION[$verificationOptions['id'] . '_vv']['errors']++;
	}

	// Are we refreshing then?
	if ($force_refresh)
	{
		// Assume nothing went before.
		$_SESSION[$verificationOptions['id'] . '_vv']['count'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = false;
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();
		$_SESSION[$verificationOptions['id'] . '_vv']['code'] = '';

		// Generating a new image.
		if ($thisVerification['show_visual'])
		{
			// Are we overriding the range?
			$character_range = !empty($verificationOptions['override_range']) ? $verificationOptions['override_range'] : $context['standard_captcha_range'];

			for ($i = 0; $i < 6; $i++)
				$_SESSION[$verificationOptions['id'] . '_vv']['code'] .= $character_range[array_rand($character_range)];
		}

		// Getting some new questions?
		if ($thisVerification['number_questions'])
		{
			// Pick some random IDs
			$questionIDs = array();
			if ($thisVerification['number_questions'] == 1)
				$questionIDs[] = $modSettings['question_id_cache'][array_rand($modSettings['question_id_cache'], $thisVerification['number_questions'])];
			else
				foreach (array_rand($modSettings['question_id_cache'], $thisVerification['number_questions']) as $index)
					$questionIDs[] = $modSettings['question_id_cache'][$index];
		}
	}
	else
	{
		// Same questions as before.
		$questionIDs = !empty($_SESSION[$verificationOptions['id'] . '_vv']['q']) ? $_SESSION[$verificationOptions['id'] . '_vv']['q'] : array();
		$thisVerification['text_value'] = !empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) ? $smcFunc['htmlspecialchars']($_REQUEST[$verificationOptions['id'] . '_vv']['code']) : '';
	}

	// Have we got some questions to load?
	if (!empty($questionIDs))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_comment, body AS question
			FROM {db_prefix}log_comments
			WHERE comment_type = {string:ver_test}
				AND id_comment IN ({array_int:comment_ids})',
			array(
				'ver_test' => 'ver_test',
				'comment_ids' => $questionIDs,
			)
		);
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$thisVerification['questions'][] = array(
				'id' => $row['id_comment'],
				'q' => parse_bbc($row['question']),
				'is_error' => !empty($incorrectQuestions) && in_array($row['id_comment'], $incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$verificationOptions['id'] . '_vv'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) ? $smcFunc['htmlspecialchars']($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) : '',
			);
			$_SESSION[$verificationOptions['id'] . '_vv']['q'][] = $row['id_comment'];
		}
		$smcFunc['db_free_result']($request);
	}

	$_SESSION[$verificationOptions['id'] . '_vv']['count'] = empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) ? 1 : $_SESSION[$verificationOptions['id'] . '_vv']['count'] + 1;

	// Return errors if we have them.
	if (!empty($verification_errors))
		return $verification_errors;
	// If we had a test that one, make a note.
	elseif ($do_test)
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = true;

	// Say that everything went well chaps.
	return true;
}

// This keeps track of all registered handling functions for auto suggest functionality and passes execution to them.
function AutoSuggestHandler($checkRegistered = null)
{
	global $context;

	// These are all registered types.
	$searchTypes = array(
		'member' => 'Member',
	);

	// If we're just checking the callback function is registered return true or false.
	if ($checkRegistered != null)
		return isset($searchTypes[$checkRegistered]) && function_exists('AutoSuggest_Search_' . $checkRegistered);

	checkSession('get');
	loadTemplate('Xml');

	// Any parameters?
	$context['search_param'] = isset($_REQUEST['search_param']) ? unserialize(base64_decode($_REQUEST['search_param'])) : array();

	if (isset($_REQUEST['suggest_type'], $_REQUEST['search']) && isset($searchTypes[$_REQUEST['suggest_type']]))
	{
		$function = 'AutoSuggest_Search_' . $searchTypes[$_REQUEST['suggest_type']];
		$context['sub_template'] = 'generic_xml';
		$context['xml_data'] = $function();
	}
}

// Search for a member - by real_name or member_name by default.
function AutoSuggest_Search_Member()
{
	global $user_info, $txt, $smcFunc, $context;

	$_REQUEST['search'] = trim($smcFunc['strtolower']($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	// Find the member.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE real_name LIKE {string:search}' . (!empty($context['search_param']['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . ($smcFunc['strlen']($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'buddy_list' => $user_info['buddies'],
			'search' => $_REQUEST['search'],
		)
	);
	$xml_data = array(
		'items' => array(
			'identifier' => 'item',
			'children' => array(),
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

		$xml_data['items']['children'][] = array(
			'attributes' => array(
				'id' => $row['id_member'],
			),
			'value' => $row['real_name'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $xml_data;
}

?>