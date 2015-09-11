<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 2
 */

/**
 * The main messageindex.
 */
function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// Let them know why their message became unapproved.
	if ($context['becomesUnapproved'])
	{
		echo '
			<div class="noticebox">
				', $txt['post_becomesUnapproved'], '
			</div>';
	}

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
	<div id="board_', $context['current_board'], '_childboards" class="boardindex_table">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['sub_boards'], '</h3>
		</div>';

		template_list_boards($context['boards']);

		echo '
	</div>';
	}

	// They can only mark read if they are logged in and it's enabled!
	if (!$context['user']['is_logged'])
		unset($context['normal_buttons']['markread']);

	if (!$context['no_topic_listing'])
	{
		echo '
	<div class="pagesection">
		', $context['menu_separator'], '<a href="#bot" class="topbottom floatleft">', $txt['go_down'], '</a>
		<div class="pagelinks floatleft">', $context['page_index'], '</div>
		', template_button_strip($context['normal_buttons'], 'right'), '
	</div>';

	if ($context['description'] != '' || !empty($context['moderators']))
		{
		echo '
	<div id="description_board" class="generic_list_wrapper">
		<h3 class="floatleft">', $context['name'], '&nbsp;-&nbsp;</h3>
		<p>';
	if ($context['description'] != '')
	echo '
		', $context['description'], '&nbsp;';

	if (!empty($context['moderators']))
	echo '
		', count($context['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $context['link_moderators']), '.';

	echo '
		</p>
	</div>';
		}

		// If Quick Moderation is enabled start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" class="clear" name="quickModForm" id="quickModForm">';

		echo '
		<div id="messageindex">';
		if (!empty($settings['display_who_viewing']))
		{
			echo '
				<P class="information">';

				if ($settings['display_who_viewing'] == 1)
					echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
			else
					echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_forum'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');
				echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'];

			echo '
				</p>';
		}

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
				<div class="noticebox">
					', $context['unapproved_posts_message'], '
				</div>';
		}

		template_topic_list();

		echo '
		</div>';

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
				<div class="righttext" id="quick_actions">
					<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
						<option value="">--------</option>';

			foreach ($context['qmod_actions'] as $qmod_action)
				if ($context['can_' . $qmod_action])
					echo '
						<option value="' . $qmod_action . '">' . $txt['quick_mod_'  . $qmod_action] . '</option>';

			echo '
					</select>';

			// Show a list of boards they can move the topic to.
			if ($context['can_move'])
				echo '
			<span id="quick_mod_jump_to">&nbsp;</span>';

			echo '
					<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit qaction">
				</div>';
		}

		echo '
	</div>';

		// Finish off the form - again.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">
		', template_button_strip($context['normal_buttons'], 'right'), '
		', $context['menu_separator'], '<a href="#main_content_section" class="topbottom floatleft" id="bot">', $txt['go_up'], '</a>
		<div class="pagelinks floatleft">', $context['page_index'], '</div>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	theme_linktree();

	if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']) && $context['can_move'])
		echo '
			<script>
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "quick_mod_jump_to",
						sClassName: "qaction",
						sJumpToTemplate: "%dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						bNoRedirect: true,
						bDisabled: true,
						sCustomName: "move_to"
					});
			</script>';

	// Javascript for inline editing.
	echo '
<script>
	var oQuickModifyTopic = new QuickModifyTopic({
		aHidePrefixes: Array("lockicon", "stickyicon", "pages", "newicon"),
		bMouseOnDiv: false,
	});
</script>';

	template_topic_legend();
}

/**
 * Shows a legend for topic icons.
 */
function template_topic_legend()
{
	global $context, $settings, $txt, $modSettings;

	echo '
	<div class="tborder" id="topic_icons">
		<div class="information">
			<p class="floatright" id="message_index_jump_to">&nbsp;</p>';

	if (empty($context['no_topic_listing']))
		echo '
			<p class="floatleft">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
				<img src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="" class="centericon"> ' . $txt['participation_caption'] . '<br>' : '', '
				'. ($modSettings['pollMode'] == '1' ? '<span class="generic_icons poll centericon"></span> ' . $txt['poll'] : '') . '<br>
				<img src="' . $settings['images_url'] . '/post/moved.png" alt="" class="centericon sizefix"> ' . $txt['moved_topic'] . '<br>
			</p>
			<p>
				<span class="generic_icons lock centericon"></span> ' . $txt['locked_topic'] . '<br>
				<span class="generic_icons sticky centericon"></span> ' . $txt['sticky_topic'] . '<br>
			</p>';

	if (!empty($context['jump_to']))
		echo '
			<script>
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "message_index_jump_to",
						sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', $context['jump_to']['label'], '<" + "/label> %dropdown_list%",
						iCurBoardId: ', $context['current_board'], ',
						iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
						sCurBoardName: "', $context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						sGoButtonLabel: "', $txt['quick_mod_go'], '"
					});
			</script>';

	echo '
			<br class="clear">
		</div>
	</div>';
}

function template_topic_list()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '
		<div class="grid bold_text title_bar">';

		// Are there actually any topics to show?
		if (!empty($context['topics']))
		{
			echo '
					<div class="cell1 whide">&nbsp;</div>
					<div class="cell9 mcell16">', $context['topics_headers']['subject'], ' / ', $context['topics_headers']['starter'], '</div>
					<div class="cell2 whide">', $context['topics_headers']['replies'], ' / ', $context['topics_headers']['views'], '</div>
					<div class="cell3 whide">', $context['topics_headers']['last_post'], '</div>';

			// Show a "select all" box for quick moderation?
			if (!empty($context['can_quick_mod']))
				echo '
					<div class="cell1 whide"><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check"></div>';
		}
		// No topics.... just say, "sorry bub".
		else
			echo '
					<h3 class="titlebg">', $txt['topic_alert_none'], '</h3>';

		echo '
		</div>';

		foreach ($context['topics'] as $topic)
		{
			$color_class = 'stripes';

			// Is this topic pending approval, or does it have any posts pending approval?
			if ($context['can_approve_posts'] && $topic['unapproved_posts'])
				$color_class = (!$topic['approved'] ? 'approvetopic ' : 'approvepost ') . $color_class;

			// Sticky topics should get a different color, too.
			if ($topic['is_sticky'])
				$color_class = 'sticky ' . $color_class;
			// Locked topics get special treatment as well.
			if ($topic['is_locked'])
				$color_class = 'locked ' . $color_class;

			echo '
			<div class="grid ', $color_class, '">
				<div class="cell1 whide">
					<img src="', $topic['first_post']['icon_url'], '" alt="">
					', $topic['is_posted_in'] ? '<img class="posted" src="' . $settings['images_url'] . '/icons/profile_sm.png" alt="">' : '', '
				</div>
					<div class="cell9 mcell16">
						<div ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '"  ondblclick="oQuickModifyTopic.modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>';

			// Now we handle the icons
			echo '
							<div class="icons">';
			if (!empty($topic['is_watched']))
				echo '
								<span class="generic_icons watch floatright" title="', $txt['watching_this_topic'], '"></span>';
			if ($topic['is_locked'])
				echo '
								<span class="generic_icons lock floatright"></span>';
			if ($topic['is_sticky'])
				echo '
								<span class="generic_icons sticky floatright"></span>';
			if (!empty($topic['is_redirect']))
				echo '
								<span class="generic_icons move floatright"></span>';
			if ($topic['is_poll'])
				echo '
								<span class="generic_icons poll floatright"></span>';
			echo '
							</div>';

			echo '
							<h5 class="message_index_title">
								', !empty($topic['new']) && $context['user']['is_logged'] ? '<a href="' . $topic['new_href'] . '" id="newicon' . $topic['first_post']['id'] . '"><span class="new_posts">' . $txt['new'] . '</span></a>' : '', '
								<span class="preview', $topic['is_sticky'] ? ' bold_text' : '', '" title="', $topic[(empty($modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '">
									<span id="msg_', $topic['first_post']['id'], '">', $topic['first_post']['link'], ($context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : ''), '</span>
								</span>
							</h5>
							', $txt['started_by'], ' ', $topic['first_post']['member']['link'], '
						</div>
					</div>
					<div class="cell2 whide"><p>', $topic['replies'], ' ', $txt['replies'], '<br>', $topic['views'], ' ', $txt['views'], '</p></div>
					<div class="cell3 whide">
						<p>', sprintf($txt['last_post_topic'], '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', $topic['last_post']['member']['link']), '</p>
					</div>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
				echo '
					<div class="cell1 whide">
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check">
					</div>';

				echo '
				</div>';
		}
}

?>