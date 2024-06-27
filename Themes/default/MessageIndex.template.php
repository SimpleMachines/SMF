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
use SMF\User;

/**
 * The main messageindex.
 */
function template_main()
{
	echo '<div id="display_head" class="information">
			<h2 class="display_title">', Utils::$context['name'], '</h2>';

	if (isset(Utils::$context['description']) && Utils::$context['description'] != '')
		echo '
			<p>', Utils::$context['description'], '</p>';

	if (!empty(Utils::$context['moderators']))
		echo '
			<p>', Lang::getTxt('moderators_list', ['num' => count(Utils::$context['link_moderators']), 'list' => Lang::sentenceList(Utils::$context['link_moderators'])]), '.</p>';

	if (!empty(Theme::$current->settings['display_who_viewing']))
	{
		// Show just numbers...?
		if (Theme::$current->settings['display_who_viewing'] == 1 || empty(Utils::$context['view_members_list'])) {
			$list_of_viewers = [
				Lang::getTxt('number_of_members', [0]),
			];
		}
		// Or show the actual people viewing the topic?
		else {
			$list_of_viewers = Utils::$context['view_members_list'];
		}

		if (!empty(Utils::$context['view_num_hidden']) && !Utils::$context['can_moderate_forum']) {
			$list_of_viewers[] = Lang::getTxt('number_of_hidden_members', [Utils::$context['view_num_hidden']]);
		}

		// Now show how many guests are here too.
		if (!empty(Utils::$context['view_num_guests'])) {
			$list_of_viewers[] = Lang::getTxt('guest_plural', [Utils::$context['view_num_guests']]);
		}

		echo '
			<p>
				', Lang::getTxt(
					'who_viewing_board',
					[
						'list_of_viewers' => Lang::sentenceList(array_values($list_of_viewers)),
						'num_viewing' => count(Utils::$context['view_members_list'] ?? []) + (int) (Utils::$context['view_num_guests'] ?? 0) + (int) (Utils::$context['view_num_hidden'] ?? 0),
					],
				), '
			</p>';
	}

	echo '
		</div>';

	if (!empty(Utils::$context['boards']) && (!empty(Theme::$current->options['show_children']) || Utils::$context['start'] == 0))
	{
		echo '
	<div id="board_', Utils::$context['current_board'], '_childboards" class="boardindex_table main_container">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['sub_boards'], '</h3>
		</div>';

		foreach (Utils::$context['boards'] as $board)
		{
			echo '
		<div id="board_', $board['id'], '" class="up_contain ', (!empty($board['css_class']) ? $board['css_class'] : ''), '">
			<div class="board_icon">
				', function_exists('template_bi_' . $board['type'] . '_icon') ? call_user_func('template_bi_' . $board['type'] . '_icon', $board) : template_bi_board_icon($board), '
			</div>
			<div class="info">
				', function_exists('template_bi_' . $board['type'] . '_info') ? call_user_func('template_bi_' . $board['type'] . '_info', $board) : template_bi_board_info($board), '
			</div><!-- .info -->';

			// Show some basic information about the number of posts, etc.
			echo '
			<div class="board_stats">
				', function_exists('template_bi_' . $board['type'] . '_stats') ? call_user_func('template_bi_' . $board['type'] . '_stats', $board) : template_bi_board_stats($board), '
			</div>';

			// Show the last post if there is one.
			echo '
			<div class="lastpost">
				', function_exists('template_bi_' . $board['type'] . '_lastpost') ? call_user_func('template_bi_' . $board['type'] . '_lastpost', $board) : template_bi_board_lastpost($board), '
			</div>';

			// Won't somebody think of the children!
			if (function_exists('template_bi_' . $board['type'] . '_children'))
				call_user_func('template_bi_' . $board['type'] . '_children', $board);
			else
				template_bi_board_children($board);

				echo '
		</div><!-- #board_[id] -->';
		}

		echo '
	</div><!-- #board_[current_board]_childboards -->';
	}

	// Let them know why their message became unapproved.
	if (Utils::$context['becomesUnapproved'])
		echo '
	<div class="noticebox">
		', Lang::$txt['post_becomes_unapproved'], '
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
			<a href="#bot" class="button">', Lang::$txt['go_down'], '</a>
			', Utils::$context['page_index'], '
		</div>
		', template_button_strip(Utils::$context['normal_buttons'], 'right');

		// Mobile action buttons (top)
		if (!empty(Utils::$context['normal_buttons']))
			echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', Lang::$txt['mobile_action'], '</a>
		</div>';

		echo '
	</div>';

		// If Quick Moderation is enabled start the form.
		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics']))
			echo '
	<form action="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], '" method="post" accept-charset="', Utils::$context['character_set'], '" class="clear" name="quickModForm" id="quickModForm">';

		echo '
		<div id="messageindex">';

		echo '
			<div class="title_bar" id="topic_header">';

		// Are there actually any topics to show?
		if (!empty(Utils::$context['topics']))
		{
			echo '
				<div class="board_icon"></div>
				<div class="info">', Utils::$context['topics_headers']['subject'], ' / ', Utils::$context['topics_headers']['starter'], '</div>
				<div class="board_stats centertext">', Utils::$context['topics_headers']['replies'], ' / ', Utils::$context['topics_headers']['views'], '</div>
				<div class="lastpost">', Utils::$context['topics_headers']['last_post'], '</div>';

			// Show a "select all" box for quick moderation?
			if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1)
				echo '
				<div class="moderation">
					<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">
				</div>';

			// If it's on in "image" mode, don't show anything but the column.
			elseif (!empty(Utils::$context['can_quick_mod']))
				echo '
				<div class="moderation"></div>';
		}

		// No topics... just say, "sorry bub".
		else
			echo '
				<h3 class="titlebg">', Lang::$txt['topic_alert_none'], '</h3>';

		echo '
			</div><!-- #topic_header -->';

		// Contain the topic list
		echo '
			<div id="topic_container">';

		foreach (Utils::$context['topics'] as $topic)
		{
			echo '
				<div class="', $topic['css_class'], '">
					<div class="board_icon">
						<img src="', $topic['first_post']['icon_url'], '" alt="">
						', $topic['is_posted_in'] ? '<span class="main_icons profile_sm"></span>' : '', '
					</div>
					<div class="info', !empty(Utils::$context['can_quick_mod']) ? '' : ' info_block', '">
						<div ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '"  ondblclick="oQuickModifyTopic.modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>';

			// Now we handle the icons
			echo '
							<div class="icons floatright">';

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
							<div class="message_index_title">
								', $topic['new'] && User::$me->is_logged ? '<a href="' . $topic['new_href'] . '" id="newicon' . $topic['first_post']['id'] . '" class="new_posts">' . Lang::$txt['new'] . '</a>' : '', '
								<span class="preview', $topic['is_sticky'] ? ' bold_text' : '', '" title="', $topic[(empty(Config::$modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '">
									<span id="msg_', $topic['first_post']['id'], '">', $topic['first_post']['link'], (!$topic['approved'] ? '&nbsp;<em>(' . Lang::$txt['awaiting_approval'] . ')</em>' : ''), '</span>
								</span>
							</div>
							<p class="floatleft">
								', Lang::getTxt('started_by_member', ['member' => $topic['first_post']['member']['link']]), '
							</p>
							', !empty($topic['pages']) ? '<span id="pages' . $topic['first_post']['id'] . '" class="topic_pages">' . $topic['pages'] . '</span>' : '', '
						</div><!-- #topic_[first_post][id] -->
					</div><!-- .info -->
					<div class="board_stats centertext">
						<p>', Lang::getTxt('number_of_replies', [$topic['replies']]), '<br>', Lang::getTxt('number_of_views', [$topic['views']]), '</p>
					</div>
					<div class="lastpost">
						<p>', Lang::getTxt('last_post_topic', ['post_link' => '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>', 'member_link' => $topic['last_post']['member']['link']]), '</p>
					</div>';

			// Show the quick moderation options?
			if (!empty(Utils::$context['can_quick_mod']))
			{
				echo '
					<div class="moderation">';

				if (Theme::$current->options['display_quick_mod'] == 1)
					echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '">';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					if ($topic['quick_mod']['remove'])
						echo '<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=remove;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons delete" title="', Lang::$txt['remove_topic'], '"></span></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=lock;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons lock" title="', $topic['is_locked'] ? Lang::$txt['set_unlock'] : Lang::$txt['set_lock'], '"></span></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br>';

					if ($topic['quick_mod']['sticky'])
						echo '<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=sticky;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons sticky" title="', $topic['is_sticky'] ? Lang::$txt['set_nonsticky'] : Lang::$txt['set_sticky'], '"></span></a>';

					if ($topic['quick_mod']['move'])
						echo '<a href="', Config::$scripturl, '?action=movetopic;current_board=', Utils::$context['current_board'], ';board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';topic=', $topic['id'], '.0"><span class="main_icons move" title="', Lang::$txt['move_topic'], '"></span></a>';
				}
				echo '
					</div><!-- .moderation -->';
			}
			echo '
				</div><!-- $topic[css_class] -->';
		}
		echo '
			</div><!-- #topic_container -->';

		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics']))
		{
			echo '
			<div class="righttext" id="quick_actions">
				<select class="qaction" name="qaction"', Utils::$context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
					<option value="">--------</option>';

			foreach (Utils::$context['qmod_actions'] as $qmod_action)
				if (Utils::$context['can_' . $qmod_action])
					echo '
					<option value="' . $qmod_action . '">' . Lang::$txt['quick_mod_' . $qmod_action] . '</option>';

			echo '
				</select>';

			// Show a list of boards they can move the topic to.
			if (Utils::$context['can_move'])
				echo '
				<span id="quick_mod_jump_to"></span>';

			echo '
				<input type="submit" value="', Lang::$txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', Lang::$txt['quickmod_confirm'], '\');" class="button qaction">
			</div><!-- #quick_actions -->';
		}

		echo '
		</div><!-- #messageindex -->';

		// Finish off the form - again.
		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics']))
			echo '
		<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">
		', template_button_strip(Utils::$context['normal_buttons'], 'right'), '
		', Utils::$context['menu_separator'], '
		<div class="pagelinks floatleft">
			<a href="#main_content_section" class="button" id="bot">', Lang::$txt['go_up'], '</a>
			', Utils::$context['page_index'], '
		</div>';

		// Mobile action buttons (bottom)
		if (!empty(Utils::$context['normal_buttons']))
			echo '
			<div class="mobile_buttons floatright">
				<a class="button mobile_act">', Lang::$txt['mobile_action'], '</a>
			</div>';

		echo '
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	theme_linktree();

	if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics']) && Utils::$context['can_move'])
		echo '
	<script>
		if (typeof(window.XMLHttpRequest) != "undefined")
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

	// Lets pop the...
	echo '
	<div id="mobile_action" class="popup_container">
		<div class="popup_window description">
			<div class="popup_heading">', Lang::$txt['mobile_action'], '
				<a href="javascript:void(0);" class="main_icons hide_popup"></a>
			</div>
			', template_button_strip(Utils::$context['normal_buttons']), '
		</div>
	</div>';
}

/**
 * Outputs the board icon for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_icon($board)
{
	echo '
		<a href="', (User::$me->is_guest ? $board['href'] : Config::$scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '" class="board_', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>';
}

/**
 * Outputs the board icon for a redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_redirect_icon($board)
{
	echo '
		<a href="', $board['href'], '" class="board_', $board['board_class'], '"', !empty($board['board_tooltip']) ? ' title="' . $board['board_tooltip'] . '"' : '', '></a>';
}

/**
 * Outputs the board info for a standard board or redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_board_info($board)
{
	echo '
		<a class="subject mobile_subject" href="', $board['href'], '" id="b', $board['id'], '">
			', $board['name'], '
		</a>';

	// Has it outstanding posts for approval?
	if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
		echo '
		<a href="', Config::$scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" title="', Lang::getTxt('unapproved_posts', $board), '" class="moderation_link amt">!</a>';

	echo '
		<div class="board_description">', $board['description'], '</div>';

	// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
	if (!empty($board['moderators']) || !empty($board['moderator_groups']))
		echo '
		<p class="moderators">', Lang::getTxt('moderators_list', ['num' => count($board['link_moderators']), 'list' => Lang::sentenceList($board['link_moderators'])]), '</p>';
}

/**
 * Outputs the board stats for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_stats($board)
{
	echo '
		<p>
			', Lang::getTxt('number_of_posts', [$board->posts]), '<br>', Lang::getTxt('number_of_topics', [$board->topics]), '
		</p>';
}

/**
 * Outputs the board stats for a redirect.
 *
 * @param array $board Current board information.
 */
function template_bi_redirect_stats($board)
{
	echo '
		<p>
			', Lang::getTxt('number_of_redirects', [$board->posts]), '
		</p>';
}

/**
 * Outputs the board lastposts for a standard board or a redirect.
 * When on a mobile device, this may be hidden if no last post exists.
 *
 * @param array $board Current board information.
 */
function template_bi_board_lastpost($board)
{
	if (!empty($board['last_post']['id']))
		echo '
			<p>', $board['last_post']['last_post_message'], '</p>';
}

/**
 * Outputs the board children for a standard board.
 *
 * @param array $board Current board information.
 */
function template_bi_board_children($board)
{
	// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
	if (!empty($board['children']))
	{
		// Sort the links into an array with new boards bold so it can be imploded.
		$children = array();
		/* Each child in each board's children has:
			id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
		foreach ($board['children'] as $child)
		{
			if (!$child['is_redirect'])
				$child['link'] = '' . ($child['new'] ? '<a href="' . Config::$scripturl . '?action=unread;board=' . $child['id'] . '" title="' . Lang::getTxt('new_posts_stats', ['posts' => $child['posts'], 'topics' => $child['topics']]) . '" class="new_posts">' . Lang::$txt['new'] . '</a> ' : '') . '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="board_new_posts" ' : '') . 'title="' . Lang::getTxt($child['new'] ? 'new_posts_stats' : 'old_posts_stats', ['posts' => $child['posts'], 'topics' => $child['topics']]) . '">' . $child['name'] . '</a>';
			else
				$child['link'] = '<a href="' . $child['href'] . '" title="' . Lang::getTxt('number_of_redirects', [$child['posts']]) . ' - ' . $child['short_description'] . '">' . $child['name'] . '</a>';

			// Has it posts awaiting approval?
			if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
				$child['link'] .= ' <a href="' . Config::$scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . '" title="' . Lang::getTxt('unapproved_posts', $child) . '" class="moderation_link amt">!</a>';

			$children[] = $child['new'] ? '<span class="strong">' . $child['link'] . '</span>' : '<span>' . $child['link'] . '</span>';
		}

		echo '
			<div id="board_', $board['id'], '_children" class="children">
				<p>',
				Lang::getTxt(
					'sub_boards_list',
					[
						'id' => 'child_list_' . $board['id'],
						'num' => count($children),
						'list' => implode(' ', $children),
					],
				),
				'</p>
			</div>';
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
				<span class="main_icons profile_sm"></span> ' . Lang::$txt['participation_caption'] . '<br>' : '', '
				' . (Config::$modSettings['pollMode'] == '1' ? '<span class="main_icons poll"></span> ' . Lang::$txt['poll'] . '<br>' : '') . '
				<span class="main_icons move"></span> ' . Lang::$txt['moved_topic'] . '<br>
			</p>
			<p>
				<span class="main_icons lock"></span> ' . Lang::$txt['locked_topic'] . '<br>
				<span class="main_icons sticky"></span> ' . Lang::$txt['sticky_topic'] . '<br>
				<span class="main_icons watch"></span> ' . Lang::$txt['watching_topic'] . '<br>
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
						sGoButtonLabel: "', Lang::$txt['quick_mod_go'], '"
					});
			</script>';

	echo '
		</div><!-- .information -->
	</div><!-- #topic_icons -->';
}

?>