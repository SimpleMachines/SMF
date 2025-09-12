<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

/**
 * The main search form
 */
function template_main()
{
	echo '
	<form action="', Config::$scripturl, '?action=search2" method="post" accept-charset="UTF-8" name="searchform" id="searchform">';

	if (!empty(Utils::$context['search_errors']))
		echo '
		<div class="errorbox">
			', implode('<br>', Utils::$context['search_errors']['messages']), '
		</div>';

	if (!empty(Utils::$context['search_ignored']))
		echo '
		<div class="noticebox">
			', Lang::getTxt(
				'search_warning_ignored',
				[
					'number_of_terms' => count(Utils::$context['search_ignored']),
					'list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', Utils::$context['search_ignored']),
				],
				file: 'Search',
			), '
		</div>';

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons filter"></span>', Lang::getTxt('set_parameters', file: 'Search'), '
			</h3>
		</div>
		<div id="advanced_search" class="roundframe">
			<dl class="settings" id="search_options">
				<dt>
					<strong><label for="searchfor">', Lang::getTxt('search_for', file: 'General'), '</label></strong>
				</dt>
				<dd>
					<input type="search" name="search" id="searchfor" ', !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '', ' maxlength="', Utils::$context['search_string_limit'], '" size="40">';

	if (empty(Config::$modSettings['search_simple_fulltext']))
		echo '
					<br><em class="smalltext">', Lang::getTxt('search_example', file: 'Search'), '</em>';

	echo '
				</dd>

				<dt>
					<label for="searchtype">', Lang::getTxt('search_match', file: 'General'), '</label>
				</dt>
				<dd>
					<select name="searchtype" id="searchtype">
						<option value="1"', empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '', '>', Lang::getTxt('all_words', file: 'Search'), '</option>
						<option value="2"', !empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '', '>', Lang::getTxt('any_words', file: 'Search'), '</option>
					</select>
				</dd>
				<dt>
					<label for="userspec">', Lang::getTxt('by_user', file: 'Search'), '</label>
				</dt>
				<dd>
					<input id="userspec" type="text" name="userspec" value="', empty(Utils::$context['search_params']['userspec']) ? '*' : Utils::$context['search_params']['userspec'], '" size="40">
				</dd>
				<dt>
					<label for="sort">', Lang::getTxt('search_order', file: 'Search'), '</label>
				</dt>
				<dd>
					<select id="sort" name="sort">
						<option value="relevance|desc">', Lang::getTxt('search_orderby_relevant_first', file: 'Search'), '</option>
						<option value="num_replies|desc">', Lang::getTxt('search_orderby_large_first', file: 'Search'), '</option>
						<option value="num_replies|asc">', Lang::getTxt('search_orderby_small_first', file: 'Search'), '</option>
						<option value="id_msg|desc">', Lang::getTxt('search_orderby_recent_first', file: 'Search'), '</option>
						<option value="id_msg|asc">', Lang::getTxt('search_orderby_old_first', file: 'Search'), '</option>
					</select>
				</dd>
				<dt class="righttext options">',
					Lang::getTxt('search_options', file: 'Search'), '
				</dt>
				<dd class="options">
					<ul>';

	foreach (Utils::$context['search_options'] as $option) {
		echo '
						<li>
							<label>
								', $option['html'], '
								<span>', Lang::getTxt($option['label'], file: 'Search'), '</span>
							</label>
						</li>';
	}

	echo '
					</ul>
				</dd>
				<dt class="between">',
					Lang::getTxt('search_post_age', file: 'Search'), '
				</dt>
				<dd>
					', Lang::getTxt(
						'search_age_range',
						[
							'min' => '<input type="number" name="minage" id="minage" min="0" max="9999" value="' . (empty(Utils::$context['search_params']['minage']) ? '0' : Utils::$context['search_params']['minage']) . '">',
							'max' => '<input type="number" name="maxage" id="maxage" min="0" max="9999" value="' . (empty(Utils::$context['search_params']['maxage']) ? '9999' : Utils::$context['search_params']['maxage']) . '">',
						],
						file: 'Search',
					), '
				</dd>
			</dl>
			<script>
				window.addEventListener("load", initSearch, false);
			</script>
			<input type="hidden" name="advanced" value="1">';

	// Require an image to be typed to save spamming?
	if (Utils::$context['require_verification'])
		echo '
			<p>
				<strong>', Lang::getTxt('verification', file: 'General'), '</strong>
				', template_control_verification(Utils::$context['visual_verification_id'], 'all'), '
			</p>';

	// If Utils::$context['search_params']['topic'] is set, that means we're searching just one topic.
	if (!empty(Utils::$context['search_params']['topic']))
		echo '
			<p>
				', Lang::getTxt('search_specific_topic', ['topic' => Utils::$context['search_topic']['link']], file: 'Search'), '
			</p>
			<input type="hidden" name="topic" value="', Utils::$context['search_topic']['id'], '">
			<input type="submit" name="b_search" value="', Lang::getTxt('search', file: 'General'), '" class="button">';

	echo '
		</div>';

	if (empty(Utils::$context['search_params']['topic']))
	{
		echo '
		<fieldset class="flow_hidden">
			<div class="roundframe alt">
				<div class="title_bar">
					<h4 class="titlebg">
						<span id="advanced_panel_toggle" class="toggle_down floatright" style="display: none;"></span>
						<a href="#" id="advanced_panel_link">', Lang::getTxt('choose_board', file: 'Search'), '</a>
					</h4>
				</div>
				<div class="flow_auto boardslist" id="advanced_panel_div"', Utils::$context['boards_check_all'] ? ' style="display: none;"' : '', '>
					<ul>';

		foreach (Utils::$context['categories'] as $category)
		{
			echo '
						<li>
							<a href="javascript:void(0);" onclick="selectBoards([', implode(', ', $category['child_ids']), '], \'searchform\'); return false;">', $category['name'], '</a>
							<ul>';

			$cat_boards = array_values($category['boards']);
			foreach ($cat_boards as $key => $board)
			{
				echo '
								<li>
									<label for="brd', $board['id'], '">
										<input type="checkbox" id="brd', $board['id'], '" name="brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', '>
										', $board['name'], '
									</label>';

				// Nest child boards inside another list.
				$curr_child_level = $board['child_level'];
				$next_child_level = $cat_boards[$key + 1]['child_level'] ?? 0;

				if ($next_child_level > $curr_child_level)
				{
					echo '
									<ul style="margin-', Utils::$context['right_to_left'] ? 'right' : 'left', ': 2.5ch;">';
				}
				else
				{
					// Close child board lists until we reach a common level
					// with the next board.
					while ($next_child_level < $curr_child_level--)
					{
						echo '
										</li>
									</ul>';
					}

					echo '
								</li>';
				}
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
					<input type="checkbox" name="all" id="check_all" value=""', Utils::$context['boards_check_all'] ? ' checked' : '', ' onclick="invertAll(this, this.form, \'brd\');">
					<label for="check_all"><em>', Lang::getTxt('check_all', file: 'General'), '</em></label>
					<input type="submit" name="b_search" value="', Lang::getTxt('search', file: 'General'), '" class="button floatright">
				</div>
			</div><!-- .roundframe -->
		</fieldset>';

		echo '
		<script>
			var oAdvancedPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', Utils::$context['boards_check_all'] ? 'true' : 'false', ',
				aSwappableContainers: [
					\'advanced_panel_div\'
				],
				aSwapImages: [
					{
						sId: \'advanced_panel_toggle\',
						altExpanded: ', Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')), ',
						altCollapsed: ', Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')), '
					}
				],
				aSwapLinks: [
					{
						sId: \'advanced_panel_link\',
						msgExpanded: ', Utils::escapeJavaScript(Lang::getTxt('choose_board', file: 'Search')), ',
						msgCollapsed: ', Utils::escapeJavaScript(Lang::getTxt('choose_board', file: 'Search')), '
					}
				]
			});
		</script>';
	}

	echo '
	</form>
	<script>
		var oAddMemberSuggest = new smc_AutoSuggest({
			sSessionId: smf_session_id,
			sSessionVar: smf_session_var,
			sControlId: \'userspec\',
			sSearchType: \'member\'
		});
	</script>';
}

/**
 * The search results page.
 */
function template_results()
{
	if (isset(Utils::$context['did_you_mean']) || empty(Utils::$context['topics']) || !empty(Utils::$context['search_ignored']))
	{
		echo '
	<div id="search_results">
		<div class="cat_bar">
			<h3 class="catbg">
				', Lang::getTxt('search_adjust_query', file: 'Search'), '
			</h3>
		</div>
		<div class="roundframe">';

		// Did they make any typos or mistakes, perhaps?
		if (isset(Utils::$context['did_you_mean']))
			echo '
			<p>
				', Lang::getTxt('search_did_you_mean', ['suggested_query' => '<a href="' . Config::$scripturl . '?action=search2;params=' . Utils::$context['did_you_mean_params'] . '">' . Utils::$context['did_you_mean'] . '</a>'], file: 'Search'), '
			</p>';

		if (!empty(Utils::$context['search_ignored']))
			echo '
			<p>
				', Lang::getTxt(
					'search_warning_ignored',
					[
						'number_of_terms' => count(Utils::$context['search_ignored']),
						'list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', Utils::$context['search_ignored']),
					],
					file: 'Search',
				), '
			</p>';

		echo '
			<form action="', Config::$scripturl, '?action=search2" method="post" accept-charset="UTF-8">
				<strong>', Lang::getTxt('search_for', file: 'General'), '</strong>
				<input type="text" name="search"', !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '', ' maxlength="', Utils::$context['search_string_limit'], '" size="40">
				<input type="submit" name="edit_search" value="', Lang::getTxt('search_adjust_submit', file: 'Search'), '" class="button">';

		foreach (Utils::$context['hidden_inputs'] as $input) {
			echo "\n\t\t\t\t" . $input;
		}

		echo '
			</form>
		</div><!-- .roundframe -->
	</div><!-- #search_results -->';
	}

	if (Utils::$context['compact'])
	{
		echo '
	<form id="new_search" name="new_search" action="', Config::$scripturl, '?action=search2" method="post" accept-charset="UTF-8">
		<input type="hidden" name="search"', !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '', ' maxlength="', Utils::$context['search_string_limit'], '" size="40">';

		foreach (Utils::$context['hidden_inputs'] as $input) {
			echo "\n\t\t" . $input;
		}

		echo '
	</form>';

		echo '
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span>', Lang::getTxt('search_results', ['params' => Utils::$context['search_params']['search']], file: 'General'), '</span>
			</h2>
			<div class="floatleft">
				<a class="button" href="', Config::$scripturl, '?action=search;params=' . Utils::$context['params'], '">', Lang::getTxt('search_adjust_query', file: 'Search'), '</a>
			</div>';

		// Was anything even found?
		if (!empty(Utils::$context['topics']))
		{
			echo '
			<div class="floatright">
				<span class="padding">', Lang::getTxt('search_order', file: 'Search'), '</span>
				<select name="sort" class="floatright" form="new_search" onchange="document.forms.new_search.submit()">';

			foreach (Utils::$context['sort_options'] as $option) {
				echo '
					<option value="' . $option['value'] . '"' . ($option['selected'] ? ' selected' : '') . '>' . Lang::getTxt($option['label'], file: 'Search') . '</option>';
			}

			echo'
				</select>
			</div>
		</div>
		<div class="pagesection">
			<div class="pagelinks">', Utils::$context['page_index'], '</div>
		</div>';
		}
		else
		{
			echo '
		</div>
		<div class="roundframe noup">', Lang::getTxt('search_no_results', file: 'General'), '</div>';
		}

		// While we have results to show ...
		while ($topic = Utils::$context['get_topics']())
		{
			echo '
		<div class="', $topic['css_class'], '">';

			foreach ($topic['matches'] as $message)
			{
				echo '
			<div class="block">
				<div class="page_number floatright"> #', $message['counter'], '</div>
				<div class="half_content">
					<div class="topic_details">
						<h5>', $topic['board']['link'], ' / <a href="', Config::$scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
						<span class="smalltext">', str_replace('<br>', ' ', Lang::getTxt('last_post_updated', ['time' => $message['time'], 'member_link' => '<strong>' . $message['member']['link'] . '</strong>'], file: 'General')), '</span>
					</div>
				</div>
			</div><!-- .block -->';

				if ($message['body_highlighted'] != '')
					echo '
			<div class="list_posts word_break">', $message['body_highlighted'], '</div>';
			}

			echo '
		</div><!-- $topic[css_class] -->';
		}
	}
	else
	{
		echo '
	<div id="display_head" class="information">
		<h2 class="display_title">
			<span>', Lang::getTxt('search_results', ['params' => Utils::$context['search_params']['search']], file: 'General'), '</span>
		</h2>
		<div class="floatleft">
			<a class="button" href="', Config::$scripturl, '?action=search;params=' . Utils::$context['params'], '">', Lang::getTxt('search_adjust_query', file: 'Search'), '</a>
		</div>';

		// Was anything even found?
		if (!empty(Utils::$context['topics']))
		{
			echo '
		<div class="floatright">
			<span class="padding">', Lang::getTxt('search_order', file: 'Search'), '</span>
			<select name="sort" class="floatright" form="new_search" onchange="document.forms.new_search.submit()">';

			foreach (Utils::$context['sort_options'] as $option) {
				echo '
				<option value="' . $option['value'] . '"' . ($option['selected'] ? ' selected' : '') . '>' . Lang::getTxt($option['label'], file: 'Search') . '</option>';
			}

			echo'
			</select>
		</div>
	</div>
	<div class="pagesection">
		<div class="pagelinks">', Utils::$context['page_index'], '</div>
	</div>';
		}
		else
		{
			echo '
	</div>
	<div class="roundframe noup">', Lang::getTxt('search_no_results', file: 'General'), '</div>';
		}

		while ($topic = Utils::$context['get_topics']())
		{
			foreach ($topic['matches'] as $message)
			{
				echo '
	<div class="', $topic['css_class'], '">
		<div class="page_number floatright"> #', $message['counter'], '</div>
		<div class="topic_details">
			<h5>
				', $topic['board']['link'], ' / <a href="', Config::$scripturl, '?topic=', $topic['id'], '.', $message['start'], ';topicseen#msg', $message['id'], '">', $message['subject_highlighted'], '</a>
			</h5>
			<span class="smalltext">', str_replace('<br>', ' ', Lang::getTxt('last_post_topic', ['post_link' => $message['time'], 'member_link' => '<strong>' . $message['member']['link'] . '</strong>'], file: 'General')), '</span>
		</div>
		<div class="list_posts">', $message['body_highlighted'], '</div>';

				echo '
		<br class="clear">
	</div><!-- $topic[css_class] -->';
			}
		}
	}

	echo '
	<div class="pagesection">';

	if (!empty(Utils::$context['topics']))
		echo '
		<div class="pagelinks">', Utils::$context['page_index'], '</div>';

	// Show a jump to box for easy navigation.
	echo '
		<div class="smalltext pagelinks floatright" id="search_jump_to"></div>
		<script>
		if (typeof(window.XMLHttpRequest) != "undefined")
			new JumpTo({
				sContainerId: "search_jump_to",
				sJumpToTemplate: "<label class=\"smalltext jump_to\" for=\"%select_id%\">', Utils::$context['jump_to']['label'], '<" + "/label> %dropdown_list%",
				iCurBoardId: 0,
				iCurBoardChildLevel: 0,
				sCurBoardName: "', Utils::$context['jump_to']['board_name'], '",
				sBoardChildLevelIndicator: "==",
				sBoardPrefix: "=> ",
				sCatSeparator: "-----------------------------",
				sCatPrefix: "",
				sGoButtonLabel: "', Lang::getTxt('quick_mod_go', file: 'General'), '"
			});
		</script>
	</div>';
}
