<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * The main search form
 */
function template_main()
{
	global $context, $txt, $scripturl, $modSettings;

	echo '
	<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '" name="searchform" id="searchform">';

	if (!empty($context['search_errors']))
		echo '
		<div class="errorbox">
			', implode('<br>', $context['search_errors']['messages']), '
		</div>';

	if (!empty($context['search_ignored']))
		echo '
		<div class="noticebox">
			', $txt['search_warning_ignored_word' . (count($context['search_ignored']) == 1 ? '' : 's')], ': ', implode(', ', $context['search_ignored']), '
		</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons filter"></span>', $txt['set_parameters'], '
			</h3>
		</div>';

	echo '
		<div id="advanced_search" class="roundframe">
			<dl class="settings" id="search_options">
				<dt>
					<strong><label for="searchfor">', $txt['search_for'], ':</label></strong>
				</dt>
				<dd>
					<input type="search" name="search" id="searchfor" ', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40">';

	if (empty($modSettings['search_simple_fulltext']))
		echo '
					<br><em class="smalltext">', $txt['search_example'], '</em>';

	echo '
				</dd>

				<dt>
					<label for="searchtype">', $txt['search_match'], ':</label>
				</dt>
				<dd>
					<select name="searchtype" id="searchtype">
						<option value="1"', empty($context['search_params']['searchtype']) ? ' selected' : '', '>', $txt['all_words'], '</option>
						<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected' : '', '>', $txt['any_words'], '</option>
					</select>
				</dd>
				<dt>
					<label for="userspec">', $txt['by_user'], ':</label>
				</dt>
				<dd>
					<input id="userspec" type="text" name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '" size="40">
				</dd>
				<dt>
					<label for="sort">', $txt['search_order'], ':</label>
				</dt>
				<dd>
					<select id="sort" name="sort">
						<option value="relevance|desc">', $txt['search_orderby_relevant_first'], '</option>
						<option value="num_replies|desc">', $txt['search_orderby_large_first'], '</option>
						<option value="num_replies|asc">', $txt['search_orderby_small_first'], '</option>
						<option value="id_msg|desc">', $txt['search_orderby_recent_first'], '</option>
						<option value="id_msg|asc">', $txt['search_orderby_old_first'], '</option>
					</select>
				</dd>
				<dt class="righttext options">',
					$txt['search_options'], ':
				</dt>
				<dd class="options">
					<ul>
						<li>
							<input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked' : '', '>
							<label for="show_complete">', $txt['search_show_complete_messages'], '</label>
						</li>
						<li>
							<input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked' : '', '>
							<label for="subject_only">', $txt['search_subject_only'], '</label>
						</li>
					</ul>
				</dd>
				<dt class="between">',
					$txt['search_post_age'], ':
				</dt>
				<dd>
					<label for="minage">', $txt['search_between'], ' </label>
					<input type="number" name="minage" id="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="4">
					<label for="maxage"> ', $txt['search_and'], ' </label>
					<input type="number" name="maxage" id="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="4"> ', $txt['days_word'], '
				</dd>
			</dl>
			<script>
				createEventListener(window);
				window.addEventListener("load", initSearch, false);
			</script>
			<input type="hidden" name="advanced" value="1">';

	// Require an image to be typed to save spamming?
	if ($context['require_verification'])
		echo '
			<p>
				<strong>', $txt['verification'], ':</strong>
				', template_control_verification($context['visual_verification_id'], 'all'), '
			</p>';

	// If $context['search_params']['topic'] is set, that means we're searching just one topic.
	if (!empty($context['search_params']['topic']))
		echo '
			<p>
				', $txt['search_specific_topic'], ' &quot;', $context['search_topic']['link'], '&quot;.
			</p>
			<input type="hidden" name="topic" value="', $context['search_topic']['id'], '">
			<input type="submit" name="b_search" value="', $txt['search'], '" class="button">';

	echo '
		</div>';

	if (empty($context['search_params']['topic']))
	{
		echo '
		<fieldset class="flow_hidden">
			<div class="roundframe alt">
				<div class="title_bar">
					<h4 class="titlebg">
						<span id="advanced_panel_toggle" class="toggle_down floatright" style="display: none;"></span>
						<a href="#" id="advanced_panel_link">', $txt['choose_board'], '</a>
					</h4>
				</div>
				<div class="flow_auto boardslist" id="advanced_panel_div"', $context['boards_check_all'] ? ' style="display: none;"' : '', '>
					<ul>';

		foreach ($context['categories'] as $category)
		{
			echo '
						<li>
							<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'searchform\'); return false;">', $category['name'], '</a>
							<ul>';

			foreach ($category['boards'] as $board)
			{
				echo '
								<li>
									<label for="brd', $board['id'], '" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em;">
										<input type="checkbox" id="brd', $board['id'], '" name="brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', '> ', $board['name'], '
									</label>
								</li>';
			}

			echo '
							</ul>
						</li>';
		}

		echo '
					</ul>
				</div><!-- #advanced_panel_div -->
				<br class="clear">
				<div class="padding">
					<input type="checkbox" name="all" id="check_all" value=""', $context['boards_check_all'] ? ' checked' : '', ' onclick="invertAll(this, this.form, \'brd\');">
					<label for="check_all"><em>', $txt['check_all'], '</em></label>
					<input type="submit" name="b_search" value="', $txt['search'], '" class="button floatright">
				</div>
			</div><!-- .roundframe -->
		</fieldset>';

		echo '
		<script>
			var oAdvancedPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', $context['boards_check_all'] ? 'true' : 'false', ',
				aSwappableContainers: [
					\'advanced_panel_div\'
				],
				aSwapImages: [
					{
						sId: \'advanced_panel_toggle\',
						altExpanded: ', JavaScriptEscape($txt['hide']), ',
						altCollapsed: ', JavaScriptEscape($txt['show']), '
					}
				],
				aSwapLinks: [
					{
						sId: \'advanced_panel_link\',
						msgExpanded: ', JavaScriptEscape($txt['choose_board']), ',
						msgCollapsed: ', JavaScriptEscape($txt['choose_board']), '
					}
				]
			});
		</script>';
	}

	echo '
	</form>
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSelf: \'oAddMemberSuggest\',
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sControlId: \'userspec\',
			sSearchType: \'member\',
			bItemList: false
		});
	</script>';
}

/**
 * The search results page.
 */
function template_results()
{
	global $context, $options, $txt, $scripturl, $message;

	if (isset($context['did_you_mean']) || empty($context['topics']) || !empty($context['search_ignored']))
	{
		echo '
	<div id="search_results">
		<div class="cat_bar">
			<h3 class="catbg">
				', $txt['search_adjust_query'], '
			</h3>
		</div>
		<div class="roundframe">';

		// Did they make any typos or mistakes, perhaps?
		if (isset($context['did_you_mean']))
			echo '
			<p>
				', $txt['search_did_you_mean'], ' <a href="', $scripturl, '?action=search2;params=', $context['did_you_mean_params'], '">', $context['did_you_mean'], '</a>.
			</p>';

		if (!empty($context['search_ignored']))
			echo '
			<p>
				', $txt['search_warning_ignored_word' . (count($context['search_ignored']) == 1 ? '' : 's')], ': ', implode(', ', $context['search_ignored']), '
			</p>';

		echo '
			<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
				<strong>', $txt['search_for'], ':</strong>
				<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40">
				<input type="submit" name="edit_search" value="', $txt['search_adjust_submit'], '" class="button">
				<input type="hidden" name="searchtype" value="', !empty($context['search_params']['searchtype']) ? $context['search_params']['searchtype'] : 0, '">
				<input type="hidden" name="userspec" value="', !empty($context['search_params']['userspec']) ? $context['search_params']['userspec'] : '', '">
				<input type="hidden" name="show_complete" value="', !empty($context['search_params']['show_complete']) ? 1 : 0, '">
				<input type="hidden" name="subject_only" value="', !empty($context['search_params']['subject_only']) ? 1 : 0, '">
				<input type="hidden" name="minage" value="', !empty($context['search_params']['minage']) ? $context['search_params']['minage'] : '0', '">
				<input type="hidden" name="maxage" value="', !empty($context['search_params']['maxage']) ? $context['search_params']['maxage'] : '9999', '">
				<input type="hidden" name="sort" value="', !empty($context['search_params']['sort']) ? $context['search_params']['sort'] : 'relevance', '">';

		if (!empty($context['search_params']['brd']))
			foreach ($context['search_params']['brd'] as $board_id)
				echo '
				<input type="hidden" name="brd[', $board_id, ']" value="', $board_id, '">';

		echo '
			</form>
		</div><!-- .roundframe -->
	</div><!-- #search_results -->';
	}

	if ($context['compact'])
	{
		// Quick moderation set to checkboxes? Oh, how fun :/
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
	<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="topicForm">';

		echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="floatright">';

		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
					<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">';
		echo '
				</span>
				<span class="main_icons filter"></span> ', $txt['mlist_search_results'], ': ', $context['search_params']['search'], '
			</h3>
		</div>';

		// Was anything even found?
		if (!empty($context['topics']))
			echo '
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';

		else
			echo '
		<div class="roundframe noup">', $txt['find_no_results'], '</div>';

		// While we have results to show ...
		while ($topic = $context['get_topics']())
		{
			echo '
		<div class="', $topic['css_class'], '">';

			foreach ($topic['matches'] as $message)
			{
				echo '
			<div class="block">
				<span class="floatleft half_content">
					<div class="counter">', $message['counter'], '</div>
					<h5>', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
					<span class="smalltext">&#171;&nbsp;', $txt['by'], '&nbsp;<strong>', $message['member']['link'], '</strong>&nbsp;', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
				</span>';

				if (!empty($options['display_quick_mod']))
				{
					echo '
				<span class="floatright">';

					if ($options['display_quick_mod'] == 1)
						echo '
					<input type="checkbox" name="topics[]" value="', $topic['id'], '">';

					else
					{
						if ($topic['quick_mod']['remove'])
							echo '
					<a href="', $scripturl, '?action=quickmod;board=' . $topic['board']['id'] . '.0;actions%5B', $topic['id'], '%5D=remove;', $context['session_var'], '=', $context['session_id'], '" class="you_sure"><span class="main_icons delete" title="', $txt['remove_topic'], '"></span></a>';

						if ($topic['quick_mod']['lock'])
							echo '
					<a href="', $scripturl, '?action=quickmod;board=' . $topic['board']['id'] . '.0;actions%5B', $topic['id'], '%5D=lock;', $context['session_var'], '=', $context['session_id'], '" class="you_sure"><span class="main_icons lock" title="', $topic['is_locked'] ? $txt['set_unlock'] : $txt['set_lock'], '"></span></a>';

						if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
							echo '
					<br>';

						if ($topic['quick_mod']['sticky'])
							echo '
					<a href="', $scripturl, '?action=quickmod;board=' . $topic['board']['id'] . '.0;actions%5B', $topic['id'], '%5D=sticky;', $context['session_var'], '=', $context['session_id'], '" class="you_sure"><span class="main_icons sticky" title="', $topic['is_sticky'] ? $txt['set_nonsticky'] : $txt['set_sticky'], '"></span></a>';

						if ($topic['quick_mod']['move'])
							echo '
					<a href="', $scripturl, '?action=movetopic;topic=', $topic['id'], '.0"><span class="main_icons move" title="', $txt['move_topic'], '"></span></a>';
					}

					echo '
				</span><!-- .floatright -->';
				}

				echo '
			</div><!-- .block -->';

				if ($message['body_highlighted'] != '')
					echo '
				<div class="list_posts double_height">', $message['body_highlighted'], '</div>';
			}

			echo '
		</div><!-- $topic[css_class] -->';
		}

		if (!empty($context['topics']))
			echo '
		<div class="pagesection">
			<span>', $context['page_index'], '</span>
		</div>';

		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
		<div class="quick_actions righttext">
			<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
				<option value="">--------</option>';

			foreach ($context['qmod_actions'] as $qmod_action)
				if ($context['can_' . $qmod_action])
					echo '
				<option value="' . $qmod_action . '">' . $txt['quick_mod_' . $qmod_action] . '</option>';

			echo '
			</select>';

			if ($context['can_move'])
				echo '
			<span id="quick_mod_jump_to"></span>';

			echo '
			<input type="hidden" name="redirect_url" value="', $scripturl . '?action=search2;params=' . $context['params'], '">
			<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return this.form.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button">
		</div><!-- .quick_actions -->';
		}

		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
			echo '
		<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';
	}
	else
	{
		echo '
	<div class="cat_bar">
		<h3 class="catbg">
			<span class="main_icons filter"></span> ', $txt['mlist_search_results'], ': ', $context['search_params']['search'], '
		</h3>
	</div>
	<div class="pagesection">
		<span>', $context['page_index'], '</span>
	</div>';

		if (empty($context['topics']))
			echo '
	<div class="information">(', $txt['search_no_results'], ')</div>';

		while ($topic = $context['get_topics']())
		{
			foreach ($topic['matches'] as $message)
			{
				echo '
	<div class="', $topic['css_class'], '">
		<div class="counter">', $message['counter'], '</div>
		<div class="topic_details">
			<h5>
				', $topic['board']['link'], ' / <a href="', $scripturl, '?topic=', $topic['id'], '.', $message['start'], ';topicseen#msg', $message['id'], '">', $message['subject_highlighted'], '</a>
			</h5>
			<span class="smalltext">&#171;&nbsp;', $txt['message'], ' ', $txt['by'], ' <strong>', $message['member']['link'], ' </strong>', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
		</div>
		<div class="list_posts">', $message['body_highlighted'], '</div>';

				echo '
		<br class="clear">
	</div><!-- $topic[css_class] -->';
			}
		}

		echo '
	<div class="pagesection">
		<span>', $context['page_index'], '</span>
	</div>';
	}

	// Show a jump to box for easy navigation.
	echo '
	<br class="clear">
	<div class="smalltext righttext" id="search_jump_to"></div>
	<script>';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']) && $context['can_move'])
		echo '
		if (typeof(window.XMLHttpRequest) != "undefined")
			aJumpTo[aJumpTo.length] = new JumpTo({
				sContainerId: "quick_mod_jump_to",
				sClassName: "qaction",
				sJumpToTemplate: "%dropdown_list%",
				sCurBoardName: "', $context['jump_to']['board_name'], '",
				sBoardChildLevelIndicator: "==",
				sBoardPrefix: "=> ",
				sCatSeparator: "-----------------------------",
				sCatPrefix: "",
				bNoRedirect: true,
				bDisabled: true,
				sCustomName: "move_to"
			});';

	echo '
		if (typeof(window.XMLHttpRequest) != "undefined")
			aJumpTo[aJumpTo.length] = new JumpTo({
				sContainerId: "search_jump_to",
				sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', $context['jump_to']['label'], '<" + "/label> %dropdown_list%",
				iCurBoardId: 0,
				iCurBoardChildLevel: 0,
				sCurBoardName: "', $context['jump_to']['board_name'], '",
				sBoardChildLevelIndicator: "==",
				sBoardPrefix: "=> ",
				sCatSeparator: "-----------------------------",
				sCatPrefix: "",
				sGoButtonLabel: "', $txt['quick_mod_go'], '"
			});
		</script>';
}

?>