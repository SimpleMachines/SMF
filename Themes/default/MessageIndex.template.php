<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;
use SMF\User;

/**
 * The main messageindex.
 */
function template_main()
{
	echo '<div id="display_head">
			<h2 class="display_title">', Utils::$context['name'], '</h2>';

	if (isset(Utils::$context['description']) && Utils::$context['description'] != '')
		echo '
			<p>', Utils::$context['description'], '</p>';

	if (!empty(Utils::$context['moderators']))
		echo '
			<p>', Lang::getTxt('moderators_list', ['num' => count(Utils::$context['link_moderators']), 'list' => Lang::sentenceList(Utils::$context['link_moderators'])], file: 'General'), '.</p>';

	if (!empty(Theme::$current->settings['display_who_viewing']))
	{
		// Show just numbers...?
		if (Theme::$current->settings['display_who_viewing'] == 1 || empty(Utils::$context['view_members_list'])) {
			$list_of_viewers = [
				Lang::getTxt('number_of_members', [0], file: 'General'),
			];
		}
		// Or show the actual people viewing the topic?
		else {
			$list_of_viewers = Utils::$context['view_members_list'];
		}

		if (!empty(Utils::$context['view_num_hidden']) && !Utils::$context['can_moderate_forum']) {
			$list_of_viewers[] = Lang::getTxt('number_of_hidden_members', [Utils::$context['view_num_hidden']], file: 'General');
		}

		// Now show how many guests are here too.
		if (!empty(Utils::$context['view_num_guests'])) {
			$list_of_viewers[] = Lang::getTxt('guest_plural', [Utils::$context['view_num_guests']], file: 'General');
		}

		echo '
			<p>
				', Lang::getTxt(
					'who_viewing_board',
					[
						'list_of_viewers' => Lang::sentenceList(array_values($list_of_viewers)),
						'num_viewing' => count(Utils::$context['view_members_list'] ?? []) + (int) (Utils::$context['view_num_guests'] ?? 0) + (int) (Utils::$context['view_num_hidden'] ?? 0),
					],
					file: 'General',
				), '
			</p>';
	}

	echo '
		</div>';

	if (!empty(Utils::$context['boards']) && (!empty(Theme::$current->options['show_children']) || Utils::$context['start'] == 0))
	{
		echo '
		<div class="cat_bar">
			<h3 class="catbg">', Lang::getTxt('sub_boards', file: 'General'), '</h3>
		</div>
		<div id="board_', Utils::$context['current_board'], '_childboards" class="boards_container">';

		template_list_boards(Utils::$context['boards']);

		echo '
		</div><!-- #board_[current_board]_childboards -->';
	}

	// Let them know why their message became unapproved.
	if (Utils::$context['becomesUnapproved'])
		echo '
	<div class="noticebox">
		', Lang::getTxt('post_becomes_unapproved', file: 'General'), '
	</div>';

	// If this person can approve items and we have some awaiting approval tell them.
	if (!empty(Utils::$context['unapproved_posts_message']))
		echo '
	<div class="noticebox">
		', Utils::$context['unapproved_posts_message'], '
	</div>';

	if (!Utils::$context['no_topic_listing'])
	{
		echo '
	<div class="pagesection">
		', Utils::$context['menu_separator'], '
		<div class="pagelinks floatleft">
			<a href="#bot" class="button">', Lang::getTxt('go_down', file: 'General'), '</a>
			', Utils::$context['page_index'], '
		</div>
		', template_button_strip(Utils::$context['normal_buttons'], 'right');

		// Mobile action buttons (top)
		if (!empty(Utils::$context['normal_buttons']))
			echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', Lang::getTxt('mobile_action', file: 'General'), '</a>
		</div>';

		echo '
	</div>';

		// If Quick Moderation is enabled start the form.
		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics']))
			echo '
	<form action="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], '" method="post" accept-charset="UTF-8" class="clear" name="quickModForm" id="quickModForm">';

		template_list_topics(Utils::$context['topics_headers'], Utils::$context['topics']);

		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics']))
		{
			echo '
			<div class="righttext" id="quick_actions">
				<select class="qaction" name="qaction"', Utils::$context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
					<option value="">--------</option>';

			foreach (Utils::$context['qmod_actions'] as $qmod_action)
				if (Utils::$context['can_' . $qmod_action])
					echo '
					<option value="' . $qmod_action . '">' . Lang::getTxt('quick_mod_' . $qmod_action, file: 'General') . '</option>';

			echo '
				</select>';

			// Show a list of boards they can move the topic to.
			if (Utils::$context['can_move'])
				echo '
				<span id="quick_mod_jump_to"></span>';

			echo '
				<input type="submit" value="', Lang::getTxt('quick_mod_go', file: 'General'), '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', Lang::getTxt('quickmod_confirm', file: 'General'), '\');" class="button qaction">
			</div><!-- #quick_actions -->';
		}

		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics']))
			echo '
		<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">
		', Utils::$context['menu_separator'], '
		<div class="pagelinks floatleft">
			<a href="#main_content_section" class="button" id="bot">', Lang::getTxt('go_up', file: 'General'), '</a>
			', Utils::$context['page_index'], '
		</div>
		', template_button_strip(Utils::$context['normal_buttons'], 'right'), '';

		// Mobile action buttons (bottom)
		if (!empty(Utils::$context['normal_buttons']))
			echo '
			<div class="mobile_buttons floatright">
				<a class="button mobile_act">', Lang::getTxt('mobile_action', file: 'General'), '</a>
			</div>';

		echo '
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	theme_linktree();

	echo '
	<script>
		window.addEventListener("DOMContentLoaded", function() {';

	if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics']) && Utils::$context['can_move'])
		echo '
			aJumpTo[aJumpTo.length] = new JumpTo({
				sContainerId: "quick_mod_jump_to",
				sClassName: "qaction",
				sJumpToTemplate: "%dropdown_list%",
				iCurBoardId: ', Utils::$context['current_board'], ',
				iCurBoardChildLevel: ', Utils::$context['jump_to']['child_level'], ',
				sCurBoardName: "', Utils::$context['jump_to']['board_name'], '",
				sBoardChildLevelIndicator: "==",
				sBoardPrefix: "=> ",
				sCatSeparator: "-----------------------------",
				sCatPrefix: "",
				bNoRedirect: true,
				bDisabled: true,
				sCustomName: "move_to"
			});';

	// Javascript for inline editing.
	echo '
			var oQuickModifyTopic = new QuickModifyTopic({
				aHidePrefixes: ["icons", "msg", "pages", "newicon"],
				bMouseOnDiv: false,
				sTopicContainer: "topic_container",
			});
		});
	</script>';

	template_topic_legend();

	// Lets pop the...
	echo '
	<div id="mobile_action" class="popup_container">
		<div class="popup_window description">
			<div class="popup_heading">', Lang::getTxt('mobile_action', file: 'General'), '
				<a href="javascript:void(0);" class="main_icons hide_popup"></a>
			</div>
			', template_button_strip(Utils::$context['normal_buttons']), '
		</div>
	</div>';
}

/**
 * This actually displays the message index
 */
function template_list_topics(array $headers, array $topics): void
{
	if ($topics == [])
	{
		// No topics... just say, "sorry bub".
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', Lang::$txt['topic_alert_none'], '</h3>
		</div>';
	}
	else
	{
		echo '
		<div id="topic_container">
			<div class="title_bar" id="topic_header">
				<div class="topic_icon"></div>
				<div class="info">', $headers['subject'], ' / ', $headers['starter'], '</div>
				<div class="topic_stats">', $headers['replies'], ' / ', $headers['views'], '</div>
				<div class="lastpost">', $headers['last_post'], '</div>';

		// Show a "select all" box for quick moderation?
		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1)
			echo '
				<div class="moderation">
					<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">
				</div>';

		echo '
			</div><!-- #topic_header -->';

		foreach ($topics as $topic)
		{
			echo '
			<div class="topic_container', $topic['css_class'], '">
				<div class="topic_icon">
					<img src="', $topic['first_post']['icon_url'], '" alt="">', $topic['is_posted_in'] ? '
					<span class="main_icons profile_sm"></span>' : '', '
				</div>
				<div', !empty($topic['quick_mod']['modify']) ? ' data-msg-id="' . $topic['first_post']['id'] . '"' : '', '>';

			// Now we handle the icons
			echo '
						<div id="icons', $topic['first_post']['id'], '" class="icons floatright">';

			if ($topic['is_watched'])
				echo '
							<span class="main_icons watch" title="', Lang::$txt['watching_this_topic'], '"></span>';

			if ($topic['is_locked'])
				echo '
							<span class="main_icons lock"></span>';

			if ($topic['is_sticky'])
				echo '
							<span class="main_icons sticky"></span>';

			if ($topic['is_redirect'])
				echo '
							<span class="main_icons move"></span>';

			if ($topic['is_poll'])
				echo '
							<span class="main_icons poll"></span>';

			echo '
						</div>';

			echo '
						<div class="message_index_title">', $topic['new'] && User::$me->is_logged ? '
							<a href="' . $topic['new_href'] . '" id="newicon' . $topic['first_post']['id'] . '" class="new_posts">' . Lang::$txt['new'] . '</a>' : '', '
							<span class="preview', $topic['is_sticky'] ? ' bold_text' : '', '" title="', $topic[(empty(Config::$modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '">
								<span id="msg', $topic['first_post']['id'], '">', $topic['first_post']['link'], (!$topic['approved'] ? '&nbsp;<em>(' . Lang::$txt['awaiting_approval'] . ')</em>' : ''), '</span>
							</span>
						</div>
						<p class="floatleft">
							', Lang::getTxt('started_by_member', ['member' => $topic['first_post']['member']['link']]), '
						</p>', !empty($topic['pages']) ? '
						<span id="pages' . $topic['first_post']['id'] . '" class="pagelinks">' . $topic['pages'] . '</span>' : '', '
				</div><!-- .info -->
				<div class="topic_stats">
					<p>', Lang::getTxt('number_of_replies', [$topic['replies']]), '<br>', Lang::getTxt('number_of_views', [$topic['views']]), '</p>
				</div>
				<div class="topic_lastpost">
					<p>', Lang::getTxt('last_post_topic', ['post_link' => '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', 'member_link' => $topic['last_post']['member']['link']]), '</p>
				</div>';

			// Show the quick moderation options?
			if (!empty(Utils::$context['can_quick_mod']))
			{
				echo '
				<div class="topic_moderation">';

				if (Theme::$current->options['display_quick_mod'] == 1)
					echo '
					<input type="checkbox" name="topics[]" value="', $topic['id'], '">';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					if ($topic['quick_mod']['remove'])
						echo '
						<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=remove;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons delete" title="', Lang::$txt['remove_topic'], '"></span></a>';

					if ($topic['quick_mod']['lock'])
						echo '
						<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=lock;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons lock" title="', $topic['is_locked'] ? Lang::$txt['set_unlock'] : Lang::$txt['set_lock'], '"></span></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '
						<br>';

					if ($topic['quick_mod']['sticky'])
						echo '
						<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=sticky;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons sticky" title="', $topic['is_sticky'] ? Lang::$txt['set_nonsticky'] : Lang::$txt['set_sticky'], '"></span></a>';

					if ($topic['quick_mod']['move'])
						echo '
						<a href="', Config::$scripturl, '?action=movetopic;current_board=', Utils::$context['current_board'], ';board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';topic=', $topic['id'], '.0"><span class="main_icons move" title="', Lang::$txt['move_topic'], '"></span></a>';
				}
				echo '
				</div><!-- .moderation -->';
			}
			echo '
			</div><!-- #topic_container.$topic[css_class] -->';
		}
		echo '
		</div><!-- #topic_container -->';
	}
}

/**
 * Shows a legend for topic icons.
 */
function template_topic_legend()
{
	echo '
	<div class="tborder" id="topic_icons">
		<div class="information">
			<p id="message_index_jump_to"></p>';

	if (empty(Utils::$context['no_topic_listing']))
		echo '
			<p class="floatleft">', !empty(Config::$modSettings['enableParticipation']) && User::$me->is_logged ? '
				<span class="main_icons profile_sm"></span> ' . Lang::getTxt('participation_caption', file: 'General') . '<br>' : '', '
				' . (Config::$modSettings['pollMode'] == '1' ? '<span class="main_icons poll"></span> ' . Lang::getTxt('poll', file: 'General') . '<br>' : '') . '
				<span class="main_icons move"></span> ' . Lang::getTxt('moved_topic', file: 'General') . '<br>
			</p>
			<p>
				<span class="main_icons lock"></span> ' . Lang::getTxt('locked_topic', file: 'General') . '<br>
				<span class="main_icons sticky"></span> ' . Lang::getTxt('sticky_topic', file: 'General') . '<br>
				<span class="main_icons watch"></span> ' . Lang::getTxt('watching_topic', file: 'General') . '<br>
			</p>';

	if (!empty(Utils::$context['jump_to']))
		echo '
			<script>
				if (typeof(window.XMLHttpRequest) != "undefined")
					aJumpTo[aJumpTo.length] = new JumpTo({
						sContainerId: "message_index_jump_to",
						sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', Utils::$context['jump_to']['label'], '<" + "/label> %dropdown_list%",
						iCurBoardId: ', Utils::$context['current_board'], ',
						iCurBoardChildLevel: ', Utils::$context['jump_to']['child_level'], ',
						sCurBoardName: "', Utils::$context['jump_to']['board_name'], '",
						sBoardChildLevelIndicator: "==",
						sBoardPrefix: "=> ",
						sCatSeparator: "-----------------------------",
						sCatPrefix: "",
						sGoButtonLabel: "', Lang::getTxt('quick_mod_go', file: 'General'), '"
					});
			</script>';

	echo '
		</div><!-- .information -->
	</div><!-- #topic_icons -->';
}

?>