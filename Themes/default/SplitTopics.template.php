<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * The form that asks how you want to split things
 */
function template_ask()
{
	echo '
	<div id="split_topics">
		<form action="', Config::$scripturl, '?action=splittopics;sa=split;topic=', Utils::$context['current_topic'], '.0" method="post" accept-charset="', Utils::$context['character_set'], '">
			<input type="hidden" name="at" value="', Utils::$context['message']['id'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['split'], '</h3>
			</div>
			<div class="windowbg">
				<p class="split_topics">
					<strong><label for="subname">', Lang::$txt['subject_new_topic'], '</label></strong>
					<input type="text" name="subname" id="subname" value="', Utils::$context['message']['subject'], '" size="25">
				</p>
				<ul class="split_topics">
					<li>
						<input type="radio" id="onlythis" name="step2" value="onlythis" checked> <label for="onlythis">', Lang::$txt['split_this_post'], '</label>
					</li>
					<li>
						<input type="radio" id="afterthis" name="step2" value="afterthis"> <label for="afterthis">', Lang::$txt['split_after_and_this_post'], '</label>
					</li>
					<li>
						<input type="radio" id="selective" name="step2" value="selective"> <label for="selective">', Lang::$txt['select_split_posts'], '</label>
					</li>
				</ul>
				<hr>
				<div class="auto_flow">
					<input type="submit" value="', Lang::$txt['split'], '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
				</div>
			</div><!-- .windowbg -->
		</form>
	</div><!-- #split_topics -->';
}

/**
 * A simple confirmation that things were split as expected, with links to the current board and the old and new topics.
 */
function template_main()
{
	echo '
	<div id="split_topics">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['split'], '</h3>
		</div>
		<div class="windowbg">
			<p>', Lang::$txt['split_successful'], '</p>
			<ul>
				<li>
					<a href="', Config::$scripturl, '?board=', Utils::$context['current_board'], '.0">', Lang::$txt['message_index'], '</a>
				</li>
				<li>
					<a href="', Config::$scripturl, '?topic=', Utils::$context['old_topic'], '.0">', Lang::$txt['origin_topic'], '</a>
				</li>
				<li>
					<a href="', Config::$scripturl, '?topic=', Utils::$context['new_topic'], '.0">', Lang::$txt['new_topic'], '</a>
				</li>
			</ul>
		</div><!-- .windowbg -->
	</div><!-- #split_topics -->';
}

/**
 * The form for selecting which posts to split.
 */
function template_select()
{
	echo '
	<div id="split_topics">
		<form action="', Config::$scripturl, '?action=splittopics;sa=splitSelection;board=', Utils::$context['current_board'], '.0" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div id="not_selected" class="floatleft">
				<div class="cat_bar">
					<h3 class="catbg">', Lang::$txt['select_split_posts'], '</h3>
				</div>
				<div class="information">
					', Lang::$txt['please_select_split'], '
				</div>
				<div class="pagesection">
					<div id="pageindex_not_selected" class="pagelinks">', Utils::$context['not_selected']['page_index'], '</div>
				</div>
				<ul id="messages_not_selected" class="split_messages smalltext">';

	foreach (Utils::$context['not_selected']['messages'] as $message)
		echo '
					<li class="windowbg" id="not_selected_', $message['id'], '">
						<div class="message_header">
							<a class="split_icon floatright" href="', Config::$scripturl, '?action=splittopics;sa=selectTopics;subname=', Utils::$context['topic']['subject'], ';topic=', Utils::$context['topic']['id'], '.', Utils::$context['not_selected']['start'], ';start2=', Utils::$context['selected']['start'], ';move=down;msg=', $message['id'], '" onclick="return select(\'down\', ', $message['id'], ');"><span class="main_icons split_sel" title="-&gt;"></span></a>
							', Lang::getTxt('post_by_member', $message), '
							<em>', $message['time'], '</em>
						</div>
						<div class="post">', $message['body'], '</div>
					</li>';

	echo '
				</ul>
			</div><!-- #not_selected -->
			<div id="selected" class="floatright">
				<div class="cat_bar">
					<h3 class="catbg">
						', Lang::getTxt('split_selected_posts', ['reset_link' => '<a href="' . Config::$scripturl . '?action=splittopics;sa=selectTopics;subname=' . Utils::$context['topic']['subject'] . ';topic=' . Utils::$context['topic']['id'] . '.' . Utils::$context['not_selected']['start'] . ';start2=' . Utils::$context['selected']['start'] . ';move=reset;msg=0" onclick="return select(\'reset\', 0);">' . Lang::$txt['split_reset_selection'] . '</a>']), '
					</h3>
				</div>
				<div class="information">
					', Lang::$txt['split_selected_posts_desc'], '
				</div>
				<div class="pagesection">
					<div id="pageindex_selected" class="pagelinks">', Utils::$context['selected']['page_index'], '</div>
				</div>
				<ul id="messages_selected" class="split_messages smalltext">';

	if (!empty(Utils::$context['selected']['messages']))
		foreach (Utils::$context['selected']['messages'] as $message)
			echo '
					<li class="windowbg" id="selected_', $message['id'], '">
						<div class="message_header">
							<a class="split_icon floatleft" href="', Config::$scripturl, '?action=splittopics;sa=selectTopics;subname=', Utils::$context['topic']['subject'], ';topic=', Utils::$context['topic']['id'], '.', Utils::$context['not_selected']['start'], ';start2=', Utils::$context['selected']['start'], ';move=up;msg=', $message['id'], '" onclick="return select(\'up\', ', $message['id'], ');"><span class="main_icons split_desel" title="&lt;-"></span></a>
							', Lang::getTxt('post_by_member', $message), '
							<em>', $message['time'], '</em>
						</div>
						<div class="post">', $message['body'], '</div>
					</li>';

	echo '
				</ul>
			</div><!-- #selected -->
			<br class="clear">
			<div class="flow_auto">
				<input type="hidden" name="topic" value="', Utils::$context['current_topic'], '">
				<input type="hidden" name="subname" value="', Utils::$context['new_subject'], '">
				<input type="submit" value="', Lang::$txt['split'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</div>
		</form>
	</div><!-- #split_topics -->
	<script>
		var start = new Array();
		start[0] = ', Utils::$context['not_selected']['start'], ';
		start[1] = ', Utils::$context['selected']['start'], ';

		function select(direction, msg_id)
		{
			getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', Utils::$context['topic']['subject'], ';topic=', Utils::$context['topic']['id'], '." + start[0] + ";start2=" + start[1] + ";move=" + direction + ";msg=" + msg_id + ";xml;splitjs", onDocReceived);
			return false;
		}
		function onDocReceived(XMLDoc)
		{
			var i, j, pageIndex;
			for (i = 0; i < 2; i++)
			{
				pageIndex = XMLDoc.getElementsByTagName("pageIndex")[i];
				setInnerHTML(document.getElementById("pageindex_" + pageIndex.getAttribute("section")), pageIndex.firstChild.nodeValue);
				start[i] = pageIndex.getAttribute("startFrom");
			}
			var numChanges = XMLDoc.getElementsByTagName("change").length;
			var curChange, curSection, curAction, curId, curList, curData, newItem, sInsertBeforeId;
			for (i = 0; i < numChanges; i++)
			{
				curChange = XMLDoc.getElementsByTagName("change")[i];
				curSection = curChange.getAttribute("section");
				curAction = curChange.getAttribute("curAction");
				curId = curChange.getAttribute("id");
				curList = document.getElementById("messages_" + curSection);
				if (curAction == "remove")
					curList.removeChild(document.getElementById(curSection + "_" + curId));
				// Insert a message.
				else
				{
					// By default, insert the element at the end of the list.
					sInsertBeforeId = null;
					// Loop through the list to try to find an item to insert after.
					oListItems = curList.getElementsByTagName("LI");
					for (j = 0; j < oListItems.length; j++)
					{
						if (parseInt(oListItems[j].id.substr(curSection.length + 1)) ' . (empty(Theme::$current->options['view_newest_first']) ? '>' : '<') . ' curId)
						{
							// This would be a nice place to insert the row.
							sInsertBeforeId = oListItems[j].id;
							// We\'re done for now. Escape the loop.
							j = oListItems.length + 1;
						}
					}

					// Let\'s create a nice container for the message.
					newItem = document.createElement("LI");
					newItem.className = "windowbg";
					newItem.id = curSection + "_" + curId;
					newItem.innerHTML = "<div class=\\"message_header\\"><a class=\\"split_icon float" + (curSection == "selected" ? "left" : "right") + "\\" href=\\"" + smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', Utils::$context['topic']['subject'], ';topic=', Utils::$context['topic']['id'], '.', Utils::$context['not_selected']['start'], ';start2=', Utils::$context['selected']['start'], ';move=" + (curSection == "selected" ? "up" : "down") + ";msg=" + curId + "\\" onclick=\\"return select(\'" + (curSection == "selected" ? "up" : "down") + "\', " + curId + ");\\"><span class=\\"main_icons split_" + (curSection == "selected" ? "de" : "") + "sel\\" title=\\"" + (curSection == "selected" ? "&lt;-" : "-&gt;") + "\\"></span></a><strong>" + curChange.getElementsByTagName("subject")[0].firstChild.nodeValue + "</strong> ', Lang::$txt['by'], ' <strong>" + curChange.getElementsByTagName("poster")[0].firstChild.nodeValue + "</strong><br><em>" + curChange.getElementsByTagName("time")[0].firstChild.nodeValue + "</em></div><div class=\\"post\\">" + curChange.getElementsByTagName("body")[0].firstChild.nodeValue + "</div>";

					// So, where do we insert it?
					if (typeof sInsertBeforeId == "string")
						curList.insertBefore(newItem, document.getElementById(sInsertBeforeId));
					else
						curList.appendChild(newItem);
				}
			}
		}
	</script>';
}

?>