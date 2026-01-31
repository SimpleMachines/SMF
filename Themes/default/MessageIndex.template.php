<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\Config;
use SMF\Lang;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

/**
 * The main messageindex.
 */
function template_main()
{
	echo '<div id="display_head">
			<h2 class="display_title">', Utils::$context['name'], '</h2>';

	if (isset(Utils::$context['description']) && Utils::$context['description'] != '') {
		echo '
			<p>', Utils::$context['description'], '</p>';
	}

	if (!empty(Utils::$context['moderators'])) {
		echo '
			<p>', Lang::getTxt('moderators_list', ['num' => count(Utils::$context['link_moderators']), 'list' => Lang::sentenceList(Utils::$context['link_moderators'])], file: 'General'), '.</p>';
	}

	if (!empty(Theme::$current->settings['display_who_viewing'])) {
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

	if (!empty(Utils::$context['boards']) && (!empty(Theme::$current->options['show_children']) || Utils::$context['start'] == 0)) {
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
	if (Utils::$context['becomesUnapproved']) {
		echo '
	<div class="noticebox">
		', Lang::getTxt('post_becomes_unapproved', file: 'General'), '
	</div>';
	}

	// If this person can approve items and we have some awaiting approval tell them.
	if (!empty(Utils::$context['unapproved_posts_message'])) {
		echo '
	<div class="noticebox">
		', Utils::$context['unapproved_posts_message'], '
	</div>';
	}

	if (!Utils::$context['no_topic_listing']) {
		echo '
	<div class="pagesection">
		', Utils::$context['menu_separator'], '
		<div class="pagelinks floatleft">
			<a href="#bot" class="button">', Lang::getTxt('go_down', file: 'General'), '</a>
			', Utils::$context['page_index'], '
		</div>
		', template_button_strip(Utils::$context['normal_buttons'], 'right');

		// Mobile action buttons (top)
		if (!empty(Utils::$context['normal_buttons'])) {
			echo '
		<div class="mobile_buttons floatright">
			<a class="button mobile_act">', Lang::getTxt('mobile_action', file: 'General'), '</a>
		</div>';
		}

		echo '
	</div>';

		// If Quick Moderation is enabled start the form.
		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics'])) {
			echo '
	<form action="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], '" method="post" accept-charset="UTF-8" class="clear" name="quickModForm" id="quickModForm">';
		}

		template_list_topics(Utils::$context['topics_headers'], Utils::$context['topics']);

		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics'])) {
			echo '
			<div class="righttext" id="quick_actions">
				<select class="qaction" name="qaction"', Utils::$context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
					<option value="">--------</option>';

			foreach (Utils::$context['qmod_actions'] as $qmod_action) {
				if (Utils::$context['can_' . $qmod_action]) {
					echo '
					<option value="' . $qmod_action . '">' . Lang::getTxt('quick_mod_' . $qmod_action, file: 'General') . '</option>';
				}
			}

			echo '
				</select>';

			// Show a list of boards they can move the topic to.
			if (Utils::$context['can_move']) {
				echo '
				<span id="quick_mod_jump_to"></span>';
			}

			echo '
				<input type="submit" value="', Lang::getTxt('quick_mod_go', file: 'General'), '" onclick="return document.forms.quickModForm.qaction.value != \'\' &amp;&amp; confirm(\'', Lang::getTxt('quickmod_confirm', file: 'General'), '\');" class="button qaction">
			</div><!-- #quick_actions -->';
		}

		if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] > 0 && !empty(Utils::$context['topics'])) {
			echo '
		<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
	</form>';
		}

		echo '
	<div class="pagesection">
		', Utils::$context['menu_separator'], '
		<div class="pagelinks floatleft">
			<a href="#main_content_section" class="button" id="bot">', Lang::getTxt('go_up', file: 'General'), '</a>
			', Utils::$context['page_index'], '
		</div>
		', template_button_strip(Utils::$context['normal_buttons'], 'right'), '';

		// Mobile action buttons (bottom)
		if (!empty(Utils::$context['normal_buttons'])) {
			echo '
			<div class="mobile_buttons floatright">
				<a class="button mobile_act">', Lang::getTxt('mobile_action', file: 'General'), '</a>
			</div>';
		}

		echo '
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	theme_linktree();

	echo '
	<script>
		window.addEventListener("DOMContentLoaded", function() {';

	if (!empty(Utils::$context['can_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1 && !empty(Utils::$context['topics']) && Utils::$context['can_move']) {
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
	}

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
 * Get the previous or next value in an associative array based on a given key.
 *
 * @param array $array The associative array.
 * @param mixed $current_key The key to search for.
 * @param string $direction "before" to get the previous value, "after" to get the next value.
 *
 * @return mixed|null The found value if exists, otherwise null.
 */
function get_adjacent_value(array $array, $current_key, string $direction = 'before')
{
	reset($array);
	$previous_value = null;

	while (key($array) !== null) {
		$key_in_loop = key($array);
		$value = current($array);

		if ($direction === 'before' && $key_in_loop === $current_key) {
			return $previous_value;
		}

		next($array);

		if ($direction === 'after' && $key_in_loop === $current_key) {
			return current($array) !== false ? current($array) : null;
		}

		$previous_value = $value;
	}


}

/**
 * This actually displays the message index
 */
function template_list_topics(array $headers, array $topics): void
{
	/**
	 * Topic header column definitions.
	 *
	 * @var array $columns
	 * Each column has:
	 * - `class`: The CSS class name.
	 * - `content`: The displayed content.
	 * - `size`: The column width in CSS grid format.
	 * - `rowspan`: Number of rows the column should span.
	 * - `colspan`: Number of columns the column should span.
	 * - `row_number`: The row in which the element starts (1-based).
	 */
	$columns = [
		'icon' => [
			'class'      => 'topic_icon',
			'content'    => ['header' => ''],
			'size'       => 'max-content',
			'rowspan'    => 2,
			'colspan'    => 1,
			'row_number' => 1,
		],
		'info' => [
			'class'      => 'info',
			'content'    => ['header' => '{subject} / {starter}'],
			'size'       => '1fr',
			'rowspan'    => 1,
			'colspan'    => 2,
			'row_number' => 1,
		],
		'author' => [
			'class'      => 'topic_author',
			'content'    => ['header' => ''],
			'size'       => 'auto',
			'rowspan'    => 1,
			'colspan'    => 1,
			'row_number' => 2,
		],
		'stats' => [
			'class'      => 'topic_stats',
			'content'    => ['header' => '{replies} / {views}'],
			'size'       => '10%',
			'rowspan'    => 2,
			'colspan'    => 1,
			'row_number' => 1,
		],
		'lastpost' => [
			'class'      => 'lastpost',
			'content'    => ['header' => '{last_post}'],
			'size'       => '28%',
			'rowspan'    => 2,
			'colspan'    => 1,
			'row_number' => 1,
		],
		'moderation' => [
			'class'      => 'moderation',
			'content'    => [
				'header' => '<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">',
			],
			'size'       => 'auto',
			'rowspan'    => 2,
			'colspan'    => 1,
			'row_number' => 1,
		],
	];

	// Remove moderation column if quick mod is disabled
	if (empty(Utils::$context['can_quick_mod']) || empty(Theme::$current->options['display_quick_mod'])) {
		unset($columns['moderation']);
	}

	$grid_rows = [];
	$num_rows = 1;
	$grid_sizes = [];
	$row_numbers = [];
	$indexed_grid_areas = [];
	$column_index = 0; // Tracks column position
	$column_numbers = [];
	$prev_name = '';

	foreach ($columns as $name => $column) {
		$row_index = $column['row_number'] - 1; // Convert to zero-based index

		if (!isset($grid_rows[$row_index])) {
			$grid_rows[$row_index] = [];
		}

		for ($i = 0; $i < $column['colspan']; $i++) {
			for ($y = 0; $y < $column['rowspan']; $y++) {
				$current_row = $row_index + $y;
				$row_numbers[$current_row][$name] = ($column_numbers[$row_index] ?? 0);

				if ($row_numbers[$current_row][$name] === 0) {
					$row_numbers[$current_row][$name] = (get_adjacent_value($row_numbers[$current_row], $name) ?? -1) + 1;
				}

				$column_index = $row_numbers[$current_row][$name];
				$grid_rows[$current_row][$column_index + $i] = $name;
			}

			// Add column sizes only if we're on the first row.
			if ($column['row_number'] === 1) {
				$grid_sizes[] = $column['size'];
			}
		}

		// Move to the next available column
		//~ $column_index = $row_numbers[$row_index][$column['colspan'];
		$column_numbers[$row_index] = ($column_numbers[$row_index] ?? $row_numbers[$row_index][$name]) + $column['colspan'];
		$num_rows = max($num_rows, $row_index + $column['rowspan']);
		$prev_name = $name;
	}

	// Fill empty grid areas.
	for ($y = 0; $y < $num_rows; $y++) {
		for ($i = 0, $n = count($grid_sizes); $i < $n; $i++) {
			$indexed_grid_areas[$y][$i] = $grid_rows[$y][$i] ?? '.';
		}
	}

	// Convert rows into CSS grid template areas.
	$grid_areas_str = implode('" "', array_map(fn($r) => implode(' ', $r), $indexed_grid_areas));

	echo '
	<style>';

	foreach ($columns as $name => $column) {
		echo '
		.' . $column['class'] . ' {
			grid-area: ' . $name . ';
		}';
	}

	echo '
#topic_header,
.topic_container {
			--grid-template-columns:' . implode(' ', $grid_sizes) . ';
			--grid-template-areas: "' . $grid_areas_str . '";
		}
	</style>';

	if ($topics == []) {
		// No topics... just say, "sorry bub".
		echo '
		<div class="title_bar">
			<h3 class="titlebg">', Lang::getTxt('topic_alert_none', file: 'General'), '</h3>
		</div>';
	} else {
		echo '
		<div id="topic_container">
			<div class="title_bar" id="topic_header">';

		foreach ($columns as $column) {
			echo '
				<div class="' . $column['class'] . '">' . Lang::formatText($column['content']['header'], $headers) . '</div>';
		}

		echo '
			</div><!-- #topic_header -->';

		foreach ($topics as $topic) {
			echo '
			<div class="topic_container', $topic['css_class'], '">';

			foreach ($columns as $name => $column) {
				echo '
				<div class="' . $column['class'] . '">';

				$callable = $column['content']['callable'] ?? ('template_topic_' . $name);

				if (is_callable($callable)) {
					call_user_func($callable, $topic);
				}

				echo '
				</div><!-- .', $column['class'], ' -->';
			}
			echo '
			</div><!-- .topic_container $topic[css_class] -->';
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

	if (empty(Utils::$context['no_topic_listing'])) {
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
	}

	if (!empty(Utils::$context['jump_to'])) {
		echo '
			<script>
				window.addEventListener("DOMContentLoaded", function() {
					new JumpTo({
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
				});
			</script>';
	}

	echo '
		</div><!-- .information -->
	</div><!-- #topic_icons -->';
}

/**
 * Renders the topic icon column.
 *
 * @param array $topic The topic data.
 */
function template_topic_icon($topic)
{
	echo '
					<img src="', $topic['first_post']['icon_url'], '" alt="">', $topic['is_posted_in'] ? '
					<span class="main_icons profile_sm"></span>' : '';
}

/**
 * Renders the topic info column, including title and preview.
 *
 * @param array $topic The topic data.
 */
function template_topic_info($topic)
{
		// Now we handle the icons
		echo '
					<div id="icons', $topic['first_post']['id'], '" class="icons floatright">';

		if ($topic['is_watched']) {
			echo '
						<span class="main_icons watch" title="', Lang::getTxt('watching_this_topic', file: 'General'), '"></span>';
		}

		if ($topic['is_locked']) {
			echo '
						<span class="main_icons lock"></span>';
		}

		if ($topic['is_sticky']) {
			echo '
						<span class="main_icons sticky"></span>';
		}

		if ($topic['is_redirect']) {
			echo '
						<span class="main_icons move"></span>';
		}

		if ($topic['is_poll']) {
			echo '
						<span class="main_icons poll"></span>';
		}

		echo '
					</div>';

		echo '
					<div class="message_index_title">', $topic['new'] && User::$me->is_logged ? '
						<a href="' . $topic['new_href'] . '" id="newicon' . $topic['first_post']['id'] . '" class="new_posts">' . Lang::getTxt('new', file: 'General') . '</a>' : '', '
						<span class="preview', $topic['is_sticky'] ? ' bold_text' : '', '" title="', $topic[(empty(Config::$modSettings['message_index_preview_first']) ? 'last_post' : 'first_post')]['preview'], '">
							<span id="msg', $topic['first_post']['id'], '">', $topic['first_post']['link'], (!$topic['approved'] ? '&nbsp;<em>(' . Lang::getTxt('awaiting_approval', file: 'General') . ')</em>' : ''), '</span>
						</span>
					</div>';
}

/**
 * Renders the topic author.
 *
 * @param array $topic The topic data.
 */
function template_topic_author($topic)
{
		echo '
					<p class="floatleft">
						', Lang::getTxt('started_by_member', ['member' => $topic['first_post']['member']['link']]), '
					</p>', !empty($topic['pages']) ? '
					<span id="pages' . $topic['first_post']['id'] . '" class="pagelinks">' . $topic['pages'] . '</span>' : '';
}

/**
 * Renders the topic stats column.
 *
 * @param array $topic The topic data.
 */
function template_topic_stats($topic)
{
	echo '
					' . Lang::formatText(
		'{0}<br>{1}',
		[
			Lang::getTxt('number_of_replies', [$topic['replies']]),
			Lang::getTxt('number_of_views', [$topic['views']]),
		],
	);
}

/**
 * Renders the last post column.
 *
 * @param array $topic The topic data.
 */
function template_topic_lastpost($topic)
{
	echo '
					' . Lang::getTxt(
		'last_post_topic',
		[
			'post_link'   => '<a href="' . $topic['last_post']['href'] . '">' . $topic['last_post']['time'] . '</a>',
			'member_link' => $topic['last_post']['member']['link'],
		],
	);
}

/**
 * Renders the topic moderation column (checkboxes, remove, lock, etc.).
 *
 * @param array $topic The topic data.
 */
function template_topic_moderation($topic)
{
				if (Theme::$current->options['display_quick_mod'] == 1) {
					echo '
					<input type="checkbox" name="topics[]" value="', $topic['id'], '">';
				} else {
					// Check permissions on each and show only the ones they are allowed to use.
					if ($topic['quick_mod']['remove']) {
						echo '
						<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=remove;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons delete" title="', Lang::getTxt('remove_topic', file: 'General'), '"></span></a>';
					}

					if ($topic['quick_mod']['lock']) {
						echo '
						<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=lock;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons lock" title="', Lang::getTxt($topic['is_locked'] ? 'set_unlock' : 'set_lock', file: 'General'), '"></span></a>';
					}

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove']) {
						echo '
						<br>';
					}

					if ($topic['quick_mod']['sticky']) {
						echo '
						<a href="', Config::$scripturl, '?action=quickmod;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';actions%5B', $topic['id'], '%5D=sticky;', Utils::$context['session_var'], '=', Utils::$context['session_id'], '" class="you_sure"><span class="main_icons sticky" title="', Lang::getTxt($topic['is_sticky'] ? 'set_nonsticky' : 'set_sticky', file: 'General'), '"></span></a>';
					}

					if ($topic['quick_mod']['move']) {
						echo '
						<a href="', Config::$scripturl, '?action=movetopic;current_board=', Utils::$context['current_board'], ';board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';topic=', $topic['id'], '.0"><span class="main_icons move" title="', Lang::getTxt('move_topic', file: 'General'), '"></span></a>';
					}
				}
}
