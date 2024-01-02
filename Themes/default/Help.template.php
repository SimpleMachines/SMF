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

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * This displays a help popup thingy
 */
function template_popup()
{
	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="', Utils::$context['character_set'], '">
		<meta name="robots" content="noindex">
		<title>', Utils::$context['page_title'], '</title>
		', Theme::template_css(), '
		<script src="', Theme::$current->settings['default_theme_url'], '/scripts/script.js', Utils::$context['browser_cache'], '"></script>
	</head>
	<body id="help_popup">
		<div class="windowbg description">
			', Utils::$context['help_text'], '<br>
			<br>
			<a href="javascript:self.close();">', Lang::$txt['close_window'], '</a>
		</div>
	</body>
</html>';
}

/**
 * The template for the popup for finding members
 */
function template_find_members()
{
	echo '<!DOCTYPE html>
<html', Utils::$context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', Lang::$txt['find_members'], '</title>
		<meta charset="', Utils::$context['character_set'], '">
		<meta name="robots" content="noindex">
		', Theme::template_css(), '
		<script src="', Theme::$current->settings['default_theme_url'], '/scripts/script.js', Utils::$context['browser_cache'], '"></script>
		<script>
			var membersAdded = [];
			function addMember(name)
			{
				var theTextBox = window.opener.document.getElementById("', Utils::$context['input_box_name'], '");

				if (name in membersAdded)
					return;

				// If we only accept one name don\'t remember what is there.
				if (', Utils::JavaScriptEscape(Utils::$context['delimiter']), ' != \'null\')
					membersAdded[name] = true;

				if (theTextBox.value.length < 1 || ', Utils::JavaScriptEscape(Utils::$context['delimiter']), ' == \'null\')
					theTextBox.value = ', Utils::$context['quote_results'] ? '"\"" + name + "\""' : 'name', ';
				else
					theTextBox.value += ', Utils::JavaScriptEscape(Utils::$context['delimiter']), ' + ', Utils::$context['quote_results'] ? '"\"" + name + "\""' : 'name', ';

				window.focus();
			}
		</script>
	</head>
	<body id="help_popup">
		<form action="', Config::$scripturl, '?action=findmember;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" method="post" accept-charset="', Utils::$context['character_set'], '" class="padding description">
			<div class="roundframe">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['find_members'], '</h3>
				</div>
				<div class="padding">
					<strong>', Lang::$txt['find_username'], ':</strong><br>
					<input type="text" name="search" id="search" value="', isset(Utils::$context['last_search']) ? Utils::$context['last_search'] : '', '" style="margin-top: 4px; width: 96%;"><br>
					<span class="smalltext"><em>', Lang::$txt['find_wildcards'], '</em></span><br>';

	// Only offer to search for buddies if we have some!
	if (!empty(Utils::$context['show_buddies']))
		echo '
					<span class="smalltext">
						<label for="buddies"><input type="checkbox" name="buddies" id="buddies"', !empty(Utils::$context['buddy_search']) ? ' checked' : '', '> ', Lang::$txt['find_buddies'], '</label>
					</span><br>';

	echo '
					<div class="padding righttext">
						<input type="submit" value="', Lang::$txt['search'], '" class="button">
						<input type="button" value="', Lang::$txt['find_close'], '" onclick="window.close();" class="button">
					</div>
				</div><!-- .padding -->
			</div><!-- .roundframe -->
			<br>
			<div class="roundframe">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['find_results'], '</h3>
				</div>';

	if (empty(Utils::$context['results']))
		echo '
				<p class="error">', Lang::$txt['find_no_results'], '</p>';
	else
	{
		echo '
				<ul class="padding">';

		foreach (Utils::$context['results'] as $result)
			echo '
					<li class="windowbg">
						<a href="', $result['href'], '" target="_blank" rel="noopener"> <span class="main_icons profile_sm"></span>
						<a href="javascript:void(0);" onclick="addMember(this.innerHTML); return false;">', $result['name'], '</a>
					</li>';

		echo '
				</ul>
				<div class="pagesection">
					<div class="pagelinks">', Utils::$context['page_index'], '</div>
				</div>';
	}

	echo '
			</div><!-- .roundframe -->
			<input type="hidden" name="input" value="', Utils::$context['input_box_name'], '">
			<input type="hidden" name="delim" value="', Utils::$context['delimiter'], '">
			<input type="hidden" name="quote" value="', Utils::$context['quote_results'] ? '1' : '0', '">
		</form>';

	if (empty(Utils::$context['results']))
		echo '
		<script>
			document.getElementById("search").focus();
		</script>';

	echo '
	</body>
</html>';
}

/**
 * The main help page
 */
function template_manual()
{
	echo '
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['manual_smf_user_help'], '</h3>
			</div>
			<div id="help_container">
				<div id="helpmain" class="windowbg">
					<p>', sprintf(Lang::$txt['manual_welcome'], Utils::$context['forum_name_html_safe']), '</p>
					<p>', Lang::$txt['manual_introduction'], '</p>
					<ul>';

	foreach (Utils::$context['manual_sections'] as $section_id => $wiki_id)
		echo '
						<li><a href="', Utils::$context['wiki_url'], '/', Utils::$context['wiki_prefix'], $wiki_id, (Lang::$txt['lang_dictionary'] != 'en' ? '/' . Lang::$txt['lang_dictionary'] : ''), '" target="_blank" rel="noopener">', Lang::$txt['manual_section_' . $section_id . '_title'], '</a> - ', Lang::$txt['manual_section_' . $section_id . '_desc'], '</li>';

	echo '
					</ul>
					<p>', sprintf(Lang::$txt['manual_docs_and_credits'], Utils::$context['wiki_url'], Config::$scripturl . '?action=credits'), '</p>
				</div><!-- #helpmain -->
			</div><!-- #help_container -->';
}

?>