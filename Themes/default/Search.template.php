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

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '" name="searchform" id="searchform">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="ie6_header floatleft">', !empty($settings['use_buttons']) ? '<img src="' . $settings['images_url'] . '/buttons/search.gif" alt="" class="icon" />' : ' ', $txt['set_parameters'], '</span>
			</h3>
		</div>';

	if (!empty($context['search_errors']))
		echo '
		<p id="search_error" class="error">', implode('<br />', $context['search_errors']['messages']), '</p>';

	// Simple Search?
	if ($context['simple_search'])
	{
		echo '
		<fieldset id="simple_search">
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<div id="search_term_input">
					<strong>', $txt['search_for'], ':</strong>
					<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
					', $context['require_verification'] ? '' : '&nbsp;<input type="submit" name="submit" value="' . $txt['search'] . '" class="button_submit" />
				</div>';

		if (empty($modSettings['search_simple_fulltext']))
			echo '
				<p class="smalltext">', $txt['search_example'], '</p>';

		if ($context['require_verification'])
			echo '
				<div class="verification>
					<strong>', $txt['search_visual_verification_label'], ':</strong>
					<br />', template_control_verification($context['visual_verification_id'], 'all'), '<br />
					<input id="submit" type="submit" name="submit" value="' . $txt['search'] . '" class="button_submit" />
				</div>';

		echo '
				<a href="', $scripturl, '?action=search;advanced" onclick="this.href += \';search=\' + escape(document.forms.searchform.search.value);">', $txt['search_advanced'], '</a>
				<input type="hidden" name="advanced" value="0" />
			</div>
			<span class="lowerframe"><span></span></span>
		</fieldset>';
	}

	// Advanced search!
	else
	{
		echo '
		<fieldset id="advanced_search">
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<input type="hidden" name="advanced" value="1" />
				<span class="enhanced">
					<strong>', $txt['search_for'], ':</strong>
					<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
					<script type="text/javascript"><!-- // --><![CDATA[
						function initSearch()
						{
							if (document.forms.searchform.search.value.indexOf("%u") != -1)
								document.forms.searchform.search.value = unescape(document.forms.searchform.search.value);
						}
						createEventListener(window);
						window.addEventListener("load", initSearch, false);
					// ]]></script>
					<select name="searchtype">
						<option value="1"', empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['all_words'], '</option>
						<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['any_words'], '</option>
					</select>
				</span>';

		if (empty($modSettings['search_simple_fulltext']))
			echo '
				<em class="smalltext">', $txt['search_example'], '</em>';

		echo '
				<dl id="search_options">
					<dt>', $txt['by_user'], ':</dt>
					<dd><input id="userspec" type="text" name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '" size="40" class="input_text" /></dd>
					<dt>', $txt['search_order'], ':</dt>
					<dd>
						<select id="sort" name="sort">
							<option value="relevance|desc">', $txt['search_orderby_relevant_first'], '</option>
							<option value="num_replies|desc">', $txt['search_orderby_large_first'], '</option>
							<option value="num_replies|asc">', $txt['search_orderby_small_first'], '</option>
							<option value="id_msg|desc">', $txt['search_orderby_recent_first'], '</option>
							<option value="id_msg|asc">', $txt['search_orderby_old_first'], '</option>
						</select>
					</dd>
					<dt class="options">', $txt['search_options'], ':</dt>
					<dd class="options">
						<label for="show_complete"><input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked="checked"' : '', ' class="input_check" /> ', $txt['search_show_complete_messages'], '</label><br />
						<label for="subject_only"><input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked="checked"' : '', ' class="input_check" /> ', $txt['search_subject_only'], '</label>
					</dd>
					<dt class="between">', $txt['search_post_age'], ': </dt>
					<dd>', $txt['search_between'], ' <input type="text" name="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="4" class="input_text" />&nbsp;', $txt['search_and'], '&nbsp;<input type="text" name="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="4" class="input_text" /> ', $txt['days_word'], '</dd>
				</dl>';

		// Require an image to be typed to save spamming?
		if ($context['require_verification'])
		{
			echo '
				<p>
					<strong>', $txt['verification'], ':</strong>
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</p>';
		}

		// If $context['search_params']['topic'] is set, that means we're searching just one topic.
		if (!empty($context['search_params']['topic']))
			echo '
				<p>', $txt['search_specific_topic'], ' &quot;', $context['search_topic']['link'], '&quot;.</p>
				<input type="hidden" name="topic" value="', $context['search_topic']['id'], '" />';

		echo '
			</div>
			<span class="lowerframe"><span></span></span>
		</fieldset>';

		if (empty($context['search_params']['topic']))
		{
			echo '
		<fieldset class="flow_hidden">
			<span class="upperframe"><span></span></span>
			<div class="roundframe">
				<div class="title_bar">
					<h4 class="titlebg">
						<a href="javascript:void(0);" onclick="expandCollapseBoards(); return false;"><img src="', $settings['images_url'], '/expand.gif" id="expandBoardsIcon" alt="" /></a> <a href="javascript:void(0);" onclick="expandCollapseBoards(); return false;"><strong>', $txt['choose_board'], '</strong></a>
					</h4>
				</div>
				<div class="flow_auto" id="searchBoardsExpand"', $context['boards_check_all'] ? ' style="display: none;"' : '', '>
					<ul class="ignoreboards floatleft">';

	$i = 0;
	$limit = ceil($context['num_boards'] / 2);
	foreach ($context['categories'] as $category)
	{
		echo '
						<li class="category">
							<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), ']); return false;">', $category['name'], '</a>
							<ul>';

		foreach ($category['boards'] as $board)
		{
			if ($i == $limit)
				echo '
							</ul>
						</li>
					</ul>
					<ul class="ignoreboards floatright">
						<li class="category">
							<ul>';

			echo '
								<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
									<label for="brd', $board['id'], '"><input type="checkbox" id="brd', $board['id'], '" name="brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked="checked"' : '', ' class="input_check" /> ', $board['name'], '</label>
								</li>';

			$i ++;
		}

		echo '
							</ul>
						</li>';
	}

	echo '
					</ul>
				</div>
				<br class="clear" />';

			echo '
				<div class="padding">
					<input type="checkbox" name="all" id="check_all" value=""', $context['boards_check_all'] ? ' checked="checked"' : '', ' onclick="invertAll(this, this.form, \'brd\');" class="input_check floatleft" />
					<label for="check_all" class="floatleft">', $txt['check_all'], '</label>
					<input type="submit" name="submit" value="', $txt['search'], '" class="button_submit floatright" />
				</div>
				<br class="clear" />
			</div>
			<span class="lowerframe"><span></span></span>
		</fieldset>';
		}

	}

	echo '
	</form>

	<script type="text/javascript"><!-- // --><![CDATA[
		function selectBoards(ids)
		{
			var toggle = true;

			for (i = 0; i < ids.length; i++)
				toggle = toggle & document.forms.searchform["brd" + ids[i]].checked;

			for (i = 0; i < ids.length; i++)
				document.forms.searchform["brd" + ids[i]].checked = !toggle;
		}

		function expandCollapseBoards()
		{
			var current = document.getElementById("searchBoardsExpand").style.display != "none";

			document.getElementById("searchBoardsExpand").style.display = current ? "none" : "";
			document.getElementById("expandBoardsIcon").src = smf_images_url + (current ? "/expand.gif" : "/collapse.gif");
		}';

	echo '
	// ]]></script>';
}

function template_results()
{
	global $context, $settings, $options, $txt, $scripturl, $message;

	if (isset($context['did_you_mean']) || empty($context['topics']))
	{
		echo '
	<div id="search_results">
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['search_adjust_query'], '
			</h3>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe">';

		// Did they make any typos or mistakes, perhaps?
		if (isset($context['did_you_mean']))
			echo '
			<p>', $txt['search_did_you_mean'], ' <a href="', $scripturl, '?action=search2;params=', $context['did_you_mean_params'], '">', $context['did_you_mean'], '</a>.</p>';

		echo '
			<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
				<strong>', $txt['search_for'], ':</strong>
				<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
				<input type="submit" name="submit" value="', $txt['search_adjust_submit'], '" class="button_submit" />
				<input type="hidden" name="searchtype" value="', !empty($context['search_params']['searchtype']) ? $context['search_params']['searchtype'] : 0, '" />
				<input type="hidden" name="userspec" value="', !empty($context['search_params']['userspec']) ? $context['search_params']['userspec'] : '', '" />
				<input type="hidden" name="show_complete" value="', !empty($context['search_params']['show_complete']) ? 1 : 0, '" />
				<input type="hidden" name="subject_only" value="', !empty($context['search_params']['subject_only']) ? 1 : 0, '" />
				<input type="hidden" name="minage" value="', !empty($context['search_params']['minage']) ? $context['search_params']['minage'] : '0', '" />
				<input type="hidden" name="maxage" value="', !empty($context['search_params']['maxage']) ? $context['search_params']['maxage'] : '9999', '" />
				<input type="hidden" name="sort" value="', !empty($context['search_params']['sort']) ? $context['search_params']['sort'] : 'relevance', '" />';

		if (!empty($context['search_params']['brd']))
			foreach ($context['search_params']['brd'] as $board_id)
				echo '
				<input type="hidden" name="brd[', $board_id, ']" value="', $board_id, '" />';

		echo '
			</form>
		</div>
		<span class="lowerframe"><span></span></span>
	</div><br />';
	}

	if ($context['compact'])
	{
		// Quick moderation set to checkboxes? Oh, how fun :/.
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
	<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="topicForm">';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatright">';
					if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
					echo '
							<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />';
				echo '
				</span>
				<span class="ie6_header floatleft"><img src="' . $settings['images_url'] . '/buttons/search.gif" alt="" />&nbsp;', $txt['mlist_search_results'],':&nbsp;',$context['search_params']['search'],'</span>
			</h3>
		</div>
		<div class="pagesection">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';

		while ($topic = $context['get_topics']())
		{
			$color_class = '';
			if ($topic['is_sticky'])
				$color_class = 'stickybg';
			if ($topic['is_locked'])
				$color_class .= 'lockedbg';

			echo '
			<div class="search_results_posts">
			<div class="', $message['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
				<span class="topslice"><span></span></span>
				<div class="content flow_auto">';

			foreach ($topic['matches'] as $message)
			{
				echo '
					<div class="topic_details floatleft" style="width: 94%">
						<div class="counter">', $message['counter'], '</div>
						<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
						<span class="smalltext">&#171;&nbsp;',$txt['by'],'&nbsp;<strong>', $message['member']['link'], '</strong>&nbsp;',$txt['on'],'&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
					</div>';

				if (!empty($options['display_quick_mod']))
				{
					echo '
					<div class="floatright">';

					if ($options['display_quick_mod'] == 1)
					{
						echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />';
					}
					else
					{
						if ($topic['quick_mod']['remove'])
							echo '
						<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.gif" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

						if ($topic['quick_mod']['lock'])
							echo '
						<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.gif" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" /></a>';

						if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
							echo '
						<br />';

						if ($topic['quick_mod']['sticky'])
							echo '
						<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.gif" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" /></a>';

						if ($topic['quick_mod']['move'])
							echo '
						<a href="', $scripturl, '?action=movetopic;topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.gif" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
					}

					echo '
					</div>';
				}

				if ($message['body_highlighted'] != '')
					echo '
					<br class="clear" />
					<div class="list_posts double_height">', $message['body_highlighted'], '</div>';
			}

			echo '
				</div>
				<span class="botslice"><span></span></span>
			</div>
		</div>';

		}
		if (!empty($context['topics']))
		echo '
		<div class="pagesection">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';

		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
			<div class="middletext titlebg2" style="padding: 4px;">
				<div class="floatright">
					<select name="qaction"', $context['can_move'] ? ' onchange="this.form.moveItTo.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
						<option value="">--------</option>', $context['can_remove'] ? '
						<option value="remove">' . $txt['quick_mod_remove'] . '</option>' : '', $context['can_lock'] ? '
						<option value="lock">' . $txt['quick_mod_lock'] . '</option>' : '', $context['can_sticky'] ? '
						<option value="sticky">' . $txt['quick_mod_sticky'] . '</option>' : '',	$context['can_move'] ? '
						<option value="move">' . $txt['quick_mod_move'] . ': </option>' : '', $context['can_merge'] ? '
						<option value="merge">' . $txt['quick_mod_merge'] . '</option>' : '', '
						<option value="markread">', $txt['quick_mod_markread'], '</option>
					</select>';

			if ($context['can_move'])
			{
					echo '
					<select id="moveItTo" name="move_to" disabled="disabled">';

					foreach ($context['move_to_boards'] as $category)
					{
						echo '
						<optgroup label="', $category['name'], '">';
						foreach ($category['boards'] as $board)
								echo '
						<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
						echo '
						</optgroup>';
					}
					echo '
					</select>';
			}

			echo '
					<input type="hidden" name="redirect_url" value="', $scripturl . '?action=search2;params=' . $context['params'], '" />
					<input type="submit" style="font-size: 0.8em;" value="', $txt['quick_mod_go'], '" onclick="return this.form.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit" />
				</div>
				<br class="clear" />
			</div>';
		}


		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
			echo '
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
		</form>';

	}
	else
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="ie6_header floatleft"><img src="' . $settings['images_url'] . '/buttons/search.gif" alt="" />&nbsp;', $txt['mlist_search_results'],':&nbsp;',$context['search_params']['search'],'</span>
			</h3>
		</div>
		<div class="pagesection">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';

		if (empty($context['topics']))
			echo '
		<div class="information">(', $txt['search_no_results'], ')</div>';

		while ($topic = $context['get_topics']())
		{
			foreach ($topic['matches'] as $message)
			{
				echo '
			<div class="search_results_posts">
				<div class="', $message['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
					<span class="topslice"><span></span></span>
					<div class="content">
						<div class="counter">', $message['counter'], '</div>
						<div class="topic_details">
							<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.', $message['start'], ';topicseen#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
							<span class="smalltext">&#171;&nbsp;', $txt['message'], ' ', $txt['by'], ' <strong>', $message['member']['link'], ' </strong>', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
						</div>
						<div class="list_posts">', $message['body_highlighted'], '</div>';

				if ($topic['can_reply'] || $topic['can_mark_notify'])
					echo '
						<div class="quickbuttons_wrap">
							<ul class="reset smalltext quickbuttons">';

				// If they *can* reply?
				if ($topic['can_reply'])
					echo '
								<li class="reply_button"><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'], '">', $txt['reply'], '</a></li>';

				// If they *can* quote?
				if ($topic['can_quote'])
					echo '
								<li class="quote_button"><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'] . ';quote=' . $message['id'] . '">', $txt['quote'], '</a></li>';

				// Can we request notification of topics?
				if ($topic['can_mark_notify'])
					echo '
								<li class="notify_button"><a href="', $scripturl . '?action=notify;topic=' . $topic['id'] . '.' . $message['start'], '">', $txt['notify'], '</a></li>';

				if ($topic['can_reply'] || $topic['can_mark_notify'])
					echo '
							</ul>
						</div>';
				echo '
						<br class="clear" />
					</div>
					<span class="botslice"><span></span></span>
				</div>
			</div>';
			}
		}

		echo '
		<div class="pagesection">
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';
	}

	// Show a jump to box for easy navigation.
	echo '
		<br class="clear" />
		<div class="smalltext righttext" id="search_jump_to">&nbsp;</div>
		<script type="text/javascript"><!-- // --><![CDATA[
			if (typeof(window.XMLHttpRequest) != "undefined")
				aJumpTo[aJumpTo.length] = new JumpTo({
					sContainerId: "search_jump_to",
					sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
					iCurBoardId: 0,
					iCurBoardChildLevel: 0,
					sCurBoardName: "', $context['jump_to']['board_name'], '",
					sBoardChildLevelIndicator: "==",
					sBoardPrefix: "=> ",
					sCatSeparator: "-----------------------------",
					sCatPrefix: "",
					sGoButtonLabel: "', $txt['quick_mod_go'], '"
				});
		// ]]></script>';

}

?>