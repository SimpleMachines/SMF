<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2017 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 3
 */

/**
 * The form that asks how you want to split things
 */
function template_ask()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<form action="', $scripturl, '?action=splittopics;sa=execute;topic=', $context['current_topic'], '.0" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="at" value="', $context['message']['id'], '">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['split'], '</h3>
			</div>
			<div class="windowbg">
				<p class="split_topics">
					<strong><label for="subname">', $txt['subject_new_topic'], '</label>:</strong>
					<input type="text" name="subname" id="subname" value="', $context['message']['subject'], '" size="25" class="input_text">
				</p>
				<ul class="split_topics">
					<li>
						<input type="radio" id="onlythis" name="step2" value="onlythis" checked class="input_radio"> <label for="onlythis">', $txt['split_this_post'], '</label>
					</li>
					<li>
						<input type="radio" id="afterthis" name="step2" value="afterthis" class="input_radio"> <label for="afterthis">', $txt['split_after_and_this_post'], '</label>
					</li>
					<li>
						<input type="radio" id="selective" name="step2" value="selective" class="input_radio"> <label for="selective">', $txt['select_split_posts'], '</label>
					</li>
				</ul>
				<hr>
				<div class="auto_flow">
					<input type="submit" value="', $txt['split'], '" class="button_submit">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>
	</div>';
}

/**
 * A simple confirmation that things were split as expected, with links to the current board and the old and new topics.
 */
function template_main()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['split'], '</h3>
		</div>
		<div class="windowbg">
			<p>', $txt['split_successful'], '</p>
			<ul>
				<li>
					<a href="', $scripturl, '?board=', $context['current_board'], '.0">', $txt['message_index'], '</a>
				</li>
				<li>
					<a href="', $scripturl, '?topic=', $context['old_topic'], '.0">', $txt['origin_topic'], '</a>
				</li>
				<li>
					<a href="', $scripturl, '?topic=', $context['new_topic'], '.0">', $txt['new_topic'], '</a>
				</li>
			</ul>
		</div>
	</div>';
}

/**
 * The form for selecting which posts to split.
 */
function template_select()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<form action="', $scripturl, '?action=splittopics;sa=splitSelection;board=', $context['current_board'], '.0" method="post" accept-charset="', $context['character_set'], '">
			<div id="not_selected" class="floatleft">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['split'], ' - ', $txt['select_split_posts'], '</h3>
				</div>
				<div class="information">
					', $txt['please_select_split'], '
				</div>
				<div class="pagesection">
					<span id="pageindex_not_selected">', $context['not_selected']['page_index'], '</span>
				</div>
				<ul id="messages_not_selected" class="split_messages smalltext">';

	foreach ($context['not_selected']['messages'] as $message)
		echo '
					<li class="windowbg" id="not_selected_', $message['id'], '">
						<div class="message_header">
							<a class="split_icon floatright" href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=down;msg=', $message['id'], '" onclick="return select(\'down\', ', $message['id'], ');"><span class="generic_icons split_sel" title="-&gt;"></span></a>
							', sprintf($txt['post_by_member'], $message['subject'], $message['poster']), '<br>
							<em>', $message['time'], '</em>
						</div>
						<div class="post">', $message['body'], '</div>
					</li>';

	echo '
					<li class="dummy">
				</ul>
			</div>
			<div id="selected" class="floatright">
				<div class="cat_bar">
					<h3 class="catbg">
						', $txt['split_selected_posts'], ' (<a href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=reset;msg=0" onclick="return select(\'reset\', 0);">', $txt['split_reset_selection'], '</a>)
					</h3>
				</div>
				<div class="information">
					', $txt['split_selected_posts_desc'], '
				</div>
				<div class="pagesection">
					<span id="pageindex_selected">', $context['selected']['page_index'], '</span>
				</div>
				<ul id="messages_selected" class="split_messages smalltext">';

	if (!empty($context['selected']['messages']))
		foreach ($context['selected']['messages'] as $message)
			echo '
					<li class="windowbg" id="selected_', $message['id'], '">
						<div class="message_header">
							<a class="split_icon floatleft" href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=up;msg=', $message['id'], '" onclick="return select(\'up\', ', $message['id'], ');"><span class="generic_icons split_desel" title="&lt;-"></span></a>
							', sprintf($txt['post_by_member'], $message['subject'], $message['poster']), '<br>
							<em>', $message['time'], '</em>
						</div>
						<div class="post">', $message['body'], '</div>
					</li>';

	echo '
					<li class="dummy">
				</ul>
			</div>
			<br class="clear">
			<div class="flow_auto">
				<input type="hidden" name="topic" value="', $context['current_topic'], '">
				<input type="hidden" name="subname" value="', $context['new_subject'], '">
				<input type="submit" value="', $txt['split'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<script>
		var start = new Array();
		start[0] = ', $context['not_selected']['start'], ';
		start[1] = ', $context['selected']['start'], ';

		function select(direction, msg_id)
		{
				getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '." + start[0] + ";start2=" + start[1] + ";move=" + direction + ";msg=" + msg_id + ";xml;splitjs", onDocReceived);
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
					// Loop through the list to try and find an item to insert after.
					oListItems = curList.getElementsByTagName("LI");
					for (j = 0; j < oListItems.length; j++)
					{
						if (parseInt(oListItems[j].id.substr(curSection.length + 1)) < curId)
						{
							// This would be a nice place to insert the row.
							sInsertBeforeId = oListItems[j].id;
							// We\'re done for now. Escape the loop.
							j = oListItems.length + 1;
						}
					}

					// Let\'s create a nice container for the message.
					newItem = document.createElement("LI");
					newItem.className = "windowbg2";
					newItem.id = curSection + "_" + curId;
					newItem.innerHTML = "<div class=\\"message_header\\"><a class=\\"split_icon float" + (curSection == "selected" ? "left" : "right") + "\\" href=\\"" + smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=" + (curSection == "selected" ? "up" : "down") + ";msg=" + curId + "\\" onclick=\\"return select(\'" + (curSection == "selected" ? "up" : "down") + "\', " + curId + ");\\"><span class=\\"generic_icons split_" + (curSection == "selected" ? "de" : "") + "sel\\" title=\\"" + (curSection == "selected" ? "&lt;-" : "-&gt;") + "\\"></span></a><strong>" + curChange.getElementsByTagName("subject")[0].firstChild.nodeValue + "</strong> ', $txt['by'], ' <strong>" + curChange.getElementsByTagName("poster")[0].firstChild.nodeValue + "</strong><br><em>" + curChange.getElementsByTagName("time")[0].firstChild.nodeValue + "</em><div class=\\"post\\">" + curChange.getElementsByTagName("body")[0].firstChild.nodeValue + "</div></div>";

					// So, where do we insert it?
					if (typeof sInsertBeforeId == "string")
						curList.insertBefore(newItem, document.getElementById(sInsertBeforeId));
					else
						curList.appendChild(newItem);
				}
			}
			// After all changes, make sure the window backgrounds are still correct for both lists.
			applyWindowClasses(document.getElementById("messages_selected"));
			applyWindowClasses(document.getElementById("messages_not_selected"));
		}
	</script>';
}

?>