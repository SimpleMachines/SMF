<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

function template_popup()
{
	global $context, $settings, $options, $txt;

	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<meta name="robots" content="noindex" />
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index.css" />
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js"></script>
	</head>
	<body id="help_popup">
		<div class="windowbg description">
			', $context['help_text'], '<br />
			<br />
			<a href="javascript:self.close();">', $txt['close_window'], '</a>
		</div>
	</body>
</html>';
}

function template_find_members()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $txt['find_members'], '</title>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<meta name="robots" content="noindex" />
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index.css" />
		<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js"></script>
		<script type="text/javascript"><!-- // --><![CDATA[
			var membersAdded = [];
			function addMember(name)
			{
				var theTextBox = window.opener.document.getElementById("', $context['input_box_name'], '");

				if (name in membersAdded)
					return;

				// If we only accept one name don\'t remember what is there.
				if (', JavaScriptEscape($context['delimiter']), ' != \'null\')
					membersAdded[name] = true;

				if (theTextBox.value.length < 1 || ', JavaScriptEscape($context['delimiter']), ' == \'null\')
					theTextBox.value = ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';
				else
					theTextBox.value += ', JavaScriptEscape($context['delimiter']), ' + ', $context['quote_results'] ? '"\"" + name + "\""' : 'name', ';

				window.focus();
			}
		// ]]></script>
	</head>
	<body id="help_popup">
		<form action="', $scripturl, '?action=findmember;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="', $context['character_set'], '" class="padding description">
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<div class="innerframe">
					<div class="cat_bar">
						<h3 class="catbg">', $txt['find_members'], '</h3>
					</div>
					<div class="padding">
						<strong>', $txt['find_username'], ':</strong><br />
						<input type="text" name="search" id="search" value="', isset($context['last_search']) ? $context['last_search'] : '', '" style="margin-top: 4px; width: 96%;" class="input_text" /><br />
						<span class="smalltext"><em>', $txt['find_wildcards'], '</em></span><br />';

	// Only offer to search for buddies if we have some!
	if (!empty($context['show_buddies']))
		echo '
						<span class="smalltext"><label for="buddies"><input type="checkbox" class="input_check" name="buddies" id="buddies"', !empty($context['buddy_search']) ? ' checked="checked"' : '', ' /> ', $txt['find_buddies'], '</label></span><br />';

	echo '
						<div class="padding righttext">
							<input type="submit" value="', $txt['search'], '" class="button_submit" />
							<input type="button" value="', $txt['find_close'], '" onclick="window.close();" class="button_submit" />
						</div>
					</div>
				</div>
			</div>
			<span class="lowerframe"><span></span></span>
			<br />
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<div class="innerframe">
					<div class="cat_bar">
						<h3 class="catbg">', $txt['find_results'], '</h3>
					</div>';

	if (empty($context['results']))
		echo '
					<p class="error">', $txt['find_no_results'], '</p>';
	else
	{
		echo '
					<ul class="reset padding">';

		$alternate = true;
		foreach ($context['results'] as $result)
		{
			echo '
						<li class="', $alternate ? 'windowbg2' : 'windowbg', '">
							<a href="', $result['href'], '" target="_blank" class="new_win"><img src="', $settings['images_url'], '/icons/profile_sm.gif" alt="', $txt['view_profile'], '" title="', $txt['view_profile'], '" /></a>
							<a href="javascript:void(0);" onclick="addMember(this.innerHTML); return false;">', $result['name'], '</a>
						</li>';

			$alternate = !$alternate;
		}

		echo '
					</ul>
					<div class="pagesection">
						', $txt['pages'], ': ', $context['page_index'], '
					</div>';
	}

	echo '
				</div>
			</div>
			<span class="lowerframe"><span></span></span>
			<input type="hidden" name="input" value="', $context['input_box_name'], '" />
			<input type="hidden" name="delim" value="', $context['delimiter'], '" />
			<input type="hidden" name="quote" value="', $context['quote_results'] ? '1' : '0', '" />
		</form>';

	if (empty($context['results']))
		echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			document.getElementById("search").focus();
		// ]]></script>';

	echo '
	</body>
</html>';
}

// The main help page.
function template_manual()
{
	global $context, $scripturl, $txt;

	echo '
			<div class="cat_bar">
				<h3 class="catbg">', $txt['manual_smf_user_help'], '</h3>
			</div>
			<div id="help_container">
				<div class="windowbg2">
					<span class="topslice"><span></span></span>
					<div id="helpmain">
						<p>', sprintf($txt['manual_welcome'], $context['forum_name']), '</p>
						<p>', $txt['manual_introduction'], '</p>
						<ul>';

	foreach ($context['manual_sections'] as $section_id => $wiki_id)
	{
		echo '
							<li><a href="', $context['wiki_url'], '/', $wiki_id, ($txt['lang_dictionary'] != 'en' ? '/' . $txt['lang_dictionary'] : ''), '" target="_blank" class="new_win">', $txt['manual_section_' . $section_id . '_title'], '</a> - ', $txt['manual_section_' . $section_id . '_desc'], '</li>';
	}

	echo '
						</ul>
						<p>', sprintf($txt['manual_docs_and_credits'], $context['wiki_url'], $scripturl . '?action=credits'), '</p>
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>';
}

?>