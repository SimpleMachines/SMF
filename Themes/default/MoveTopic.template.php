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
use SMF\Utils;

/**
 * Show an interface for selecting which board to move a post to.
 */
function template_move()
{
	echo '
	<div id="move_topic" class="lower_padding">
		<form action="', Config::$scripturl, '?action=movetopic2;current_board=' . Utils::$context['current_board'] . ';topic=', Utils::$context['current_topic'], '.0" method="post" accept-charset="UTF-8" onsubmit="submitonce(this);">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('move_topic', file: 'General'), '</h3>
			</div>
			<div class="windowbg centertext">
				<div class="move_topic">
					<dl class="settings">
						<dt>
							<strong>', Lang::getTxt('move_to', file: 'General'), '</strong>
						</dt>
						<dd>
							<select name="toboard">';

	foreach (Utils::$context['categories'] as $category) {
		echo '
								<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board) {
			echo '
									<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', $board['id'] == Utils::$context['current_board'] ? ' disabled' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt; ' : '', $board['name'], '</option>';
		}
		echo '
								</optgroup>';
	}

	echo '
							</select>
						</dd>';

	// Disable the reason textarea when the postRedirect checkbox is unchecked...
	echo '
					</dl>
					<label for="reset_subject">
						<input type="checkbox" name="reset_subject" id="reset_subject" onclick="document.getElementById(\'subjectArea\').classList.toggle(\'hidden\');"> ', Lang::getTxt('movetopic_change_subject', file: 'General'), '
					</label><br>
					<fieldset id="subjectArea" class="hidden">
						<dl class="settings">
							<dt><strong>', Lang::getTxt('movetopic_new_subject', file: 'General'), '</strong></dt>
							<dd><input type="text" name="custom_subject" size="30" value="', Utils::$context['subject'], '"></dd>
						</dl>
						<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject"> ', Lang::getTxt('movetopic_change_all_subjects', file: 'General'), '</label>
					</fieldset>';

	// Stick our "create a redirection topic" template in here...
	template_redirect_options('move');

	echo '
					<input type="submit" value="', Lang::getTxt('move_topic', file: 'General'), '" onclick="return submitThisOnce(this);" accesskey="s" class="button">
				</div><!-- .move_topic -->
			</div><!-- .windowbg -->';

	if (Utils::$context['back_to_topic']) {
		echo '
			<input type="hidden" name="goback" value="1">';
	}

	echo '
			<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			<input type="hidden" name="seqnum" value="', Utils::$context['form_sequence_number'], '">
		</form>
	</div><!-- #move_topic -->';
}

/**
 * Redirection topic options
 *
 * @param string $type What type of topic this is for - currently 'merge' or 'move'. Used to display appropriate text strings...
 */
function template_redirect_options($type)
{
	echo '
					<label for="postRedirect" class="block">
						<input type="checkbox" name="postRedirect" id="postRedirect"', Utils::$context['is_approved'] ? ' checked' : '', ' onclick="', Utils::$context['is_approved'] ? '' : 'if (this.checked && !confirm(\'' . Lang::getTxt($type . '_topic_unapproved_js', file: 'General') . '\')) return false; ', 'document.getElementById(\'reasonArea\').classList.toggle(\'hidden\');"> ', Lang::getTxt('post_redirection', file: 'General'), '
					</label>
					<fieldset id="reasonArea"', Utils::$context['is_approved'] ? '' : 'class="hidden"', '>
						<dl class="settings">
							<dt>
								', Lang::getTxt($type . '_why', file: 'General'), '
							</dt>
							<dd>
								<textarea name="reason">', Lang::getTxt($type . 'topic_default', ['board_link' => Lang::getTxt('movetopic_auto_board', file: 'General'), 'topic_link' => Lang::getTxt('movetopic_auto_topic', file: 'General')]), '</textarea>
							</dd>
							<dt>
								<label for="redirect_topic">', Lang::getTxt($type . 'topic_redirect', file: 'General'), '</label>
							</dt>
							<dd>
								<input type="checkbox" name="redirect_topic" id="redirect_topic" checked>
							</dd>';

	if (!empty(Config::$modSettings['allow_expire_redirect'])) {
		echo '
							<dt>
								', Lang::getTxt('redirect_topic_expires', file: 'General'), '
							</dt>
							<dd>
								<select name="redirect_expires">
									<option value="0">', Lang::getTxt('never', file: 'General'), '</option>
									<option value="1440">', Lang::getTxt('one_day', file: 'General'), '</option>
									<option value="10080" selected>', Lang::getTxt('one_week', file: 'General'), '</option>
									<option value="20160">', Lang::getTxt('two_weeks', file: 'General'), '</option>
									<option value="43200">', Lang::getTxt('one_month', file: 'General'), '</option>
									<option value="86400">', Lang::getTxt('two_months', file: 'General'), '</option>
								</select>
							</dd>';
	} else {
	echo '
							<input type="hidden" name="redirect_expires" value="0">';
	}

	echo '
						</dl>
					</fieldset>';
}

/**
 * Confirmation page shown when finished merging topics.
 */
function template_merge_done()
{
	echo '
		<div id="merge_topics">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('merge', file: 'General'), '</h3>
			</div>
			<div class="windowbg">
				<p>', Lang::getTxt('merge_successful', file: 'General'), '</p>
				<br>
				<ul>
					<li>
						<a href="', Config::$scripturl, '?board=', Utils::$context['target_board'], '.0">', Lang::getTxt('message_index', file: 'General'), '</a>
					</li>
					<li>
						<a href="', Config::$scripturl, '?topic=', Utils::$context['target_topic'], '.0">', Lang::getTxt('new_merged_topic', file: 'General'), '</a>
					</li>
				</ul>
			</div>
		</div>
		<br class="clear">';
}

/**
 * Merge topic page.
 */
function template_merge()
{
	echo '
		<div id="merge_topics">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('merge', file: 'General'), '</h3>
			</div>
			<div class="information">
				', Lang::getTxt('merge_desc', file: 'General'), '
			</div>
			<div class="windowbg">
				<dl class="settings merge_topic">
					<dt>
						<strong>', Lang::getTxt('topic_to_merge', file: 'General'), '</strong>
					</dt>
					<dd>
						', Utils::$context['origin_subject'], '
					</dd>
				</dl>
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('target_topic', file: 'General'), '</h3>
			</div>
			<div class="title_bar">
				<h4 class="titlebg">';

	if (isset(Utils::$context['merge_categories'])) {
		echo '
					<form action="' . Config::$scripturl . '?action=mergetopics;from=' . Utils::$context['origin_topic'] . ';targetboard=' . Utils::$context['target_board'] . ';board=' . Utils::$context['current_board'] . '.0" method="post" accept-charset="UTF-8" id="mergeSelectBoard">
						', Lang::getTxt('target_below', file: 'General'), ' (', Lang::getTxt('board', file: 'General'), ':
						<select name="targetboard" onchange="this.form.submit();">';

		foreach (Utils::$context['merge_categories'] as $cat) {
			echo '
							<optgroup label="', $cat['name'], '">';

			foreach ($cat['boards'] as $board) {
				echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
			}

			echo '
							</optgroup>';
		}
		echo '
						</select>)
						<input type="hidden" name="from" value="' . Utils::$context['origin_topic'] . '">
						<input type="submit" value="', Lang::getTxt('go', file: 'General'), '" class="button">
					</form>';
	} else {
	echo Lang::getTxt('target_below', file: 'General');
	}

	echo '		</h4>
			</div><!-- .title_bar -->
			<form action="', Config::$scripturl, '?action=mergetopics;sa=options" method="post" accept-charset="UTF-8">';

	// Don't show this if there aren't any topics...
	if (!empty(Utils::$context['topics'])) {
		echo '
				<div class="pagesection">
					<div class="pagelinks">', Utils::$context['page_index'], '</div>
				</div>
				<div class="windowbg">
					<ul class="merge_topics">';

		foreach (Utils::$context['topics'] as $topic) {
			echo '
						<li>
							<a href="', Config::$scripturl, '?action=mergetopics;sa=options;board=', Utils::$context['current_board'], '.0;from=', Utils::$context['origin_topic'], ';to=', $topic['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '"><span class="main_icons merge"></span></a>
							<a href="', Config::$scripturl, '?topic=', $topic['id'], '.0" target="_blank" rel="noopener">', $topic['subject'], '</a> ', Lang::getTxt('started_by_member', ['member' => $topic['poster']['link']], file: 'General'), '
						</li>';
		}

		echo '
					</ul>
				</div>
				<div class="pagesection">
					<div class="pagelinks">', Utils::$context['page_index'], '</div>
				</div>';
	}
	// Just a nice "There aren't any topics" message
	else {
	echo '
				<div class="windowbg">', Lang::getTxt('topic_alert_none', file: 'General'), '</div>';
	}

	echo '
				<br>
				<div class="title_bar">
					<h4 class="titlebg">', Lang::getTxt('target_id', file: 'General'), '</h4>
				</div>
				<div class="windowbg">
					<dl class="settings merge_topic">
						<dt>
							<strong>', Lang::getTxt('merge_to_topic_id', file: 'General'), '</strong>
						</dt>
						<dd>
							<input type="hidden" name="topics[]" value="', Utils::$context['origin_topic'], '">
							<input type="text" name="topics[]">
							<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">

						</dd>
					</dl>
					<input type="submit" value="', Lang::getTxt('merge', file: 'General'), '" class="button">
				</div>
			</form>
		</div><!-- #merge_topics -->';
}

/**
 * Extra options related to merging topics.
 */
function template_merge_extra_options()
{
	echo '
	<div id="merge_topics">
		<form action="', Config::$scripturl, '?action=mergetopics;sa=merge;" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::getTxt('merge_topic_list', file: 'General'), '</h3>
			</div>
			<table class="bordercolor table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col" style="width:10px;">', Lang::getTxt('merge_check', file: 'General'), '</th>
						<th scope="col" class="lefttext">', Lang::getTxt('subject', file: 'General'), '</th>
						<th scope="col" class="lefttext">', Lang::getTxt('started_by', file: 'General'), '</th>
						<th scope="col" class="lefttext">', Lang::getTxt('last_post', file: 'General'), '</th>
						<th scope="col" style="width:20px;">' . Lang::getTxt('merge_include_notifications', file: 'General') . '</th>
					</tr>
				</thead>
				<tbody>';

	foreach (Utils::$context['topics'] as $topic) {
		echo '
					<tr class="windowbg">
						<td>
							<input type="checkbox" name="topics[]" value="' . $topic['id'] . '" checked>
						</td>
						<td>
							<a href="' . Config::$scripturl . '?topic=' . $topic['id'] . '.0" target="_blank" rel="noopener">' . $topic['subject'] . '</a>
						</td>
						<td>
							', $topic['started']['link'], '<br>
							<span class="smalltext">', $topic['started']['time'], '</span>
						</td>
						<td>
							' . $topic['updated']['link'] . '<br>
							<span class="smalltext">', $topic['updated']['time'], '</span>
						</td>
						<td>
							<input type="checkbox" name="notifications[]" value="' . $topic['id'] . '" checked>
						</td>
					</tr>';
	}
	echo '
				</tbody>
			</table>
			<br>
			<div class="windowbg">
				<fieldset id="merge_subject" class="merge_options">
					<legend>', Lang::getTxt('merge_select_subject', file: 'General'), '</legend>
					<select name="subject" onchange="this.form.custom_subject.style.display = (this.options[this.selectedIndex].value != 0) ? \'none\': \'\' ;">';

	foreach (Utils::$context['topics'] as $topic) {
		echo '
						<option value="', $topic['id'], '"' . ($topic['selected'] ? ' selected' : '') . '>', $topic['subject'], '</option>';
	}
	echo '
						<option value="0">', Lang::getTxt('merge_custom_subject', file: 'General'), '</option>
					</select>
					<br>
					<input type="text" name="custom_subject" size="60" id="custom_subject" class="custom_subject" style="display: none;"><br>
					<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject" value="1"> ', Lang::getTxt('movetopic_change_all_subjects', file: 'General'), '</label>
				</fieldset>';

	// Show an option to create a redirection topic as well...
	template_redirect_options('merge');

	if (!empty(Utils::$context['boards']) && count(Utils::$context['boards']) > 1) {
		echo '
				<fieldset id="merge_board" class="merge_options">
					<legend>', Lang::getTxt('merge_select_target_board', file: 'General'), '</legend>
					<ul>';

		foreach (Utils::$context['boards'] as $board) {
			echo '
						<li>
							<input type="radio" name="board" value="' . $board['id'] . '"' . ($board['selected'] ? ' checked' : '') . '> ' . $board['name'] . '
						</li>';
		}
		echo '
					</ul>
				</fieldset>';
	}

	if (!empty(Utils::$context['polls'])) {
		echo '
				<fieldset id="merge_poll" class="merge_options">
					<legend>' . Lang::getTxt('merge_select_poll', file: 'General') . '</legend>
					<ul>';

		foreach (Utils::$context['polls'] as $poll) {
			echo '
						<li>
							<input type="radio" name="poll" value="' . $poll['id'] . '"' . ($poll['selected'] ? ' checked' : '') . '> ' . $poll['question'] . ' (' . Lang::getTxt('topic', file: 'General') . ': <a href="' . Config::$scripturl . '?topic=' . $poll['topic']['id'] . '.0" target="_blank" rel="noopener">' . $poll['topic']['subject'] . '</a>)
						</li>';
		}
		echo '
						<li>
							<input type="radio" name="poll" value="-1"> (' . Lang::getTxt('merge_no_poll', file: 'General') . ')
						</li>
					</ul>
				</fieldset>';
	}

	echo '
				<div class="auto_flow">
					<input type="submit" value="' . Lang::getTxt('merge', file: 'General') . '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="sa" value="execute">
				</div>
			</div><!-- .windowbg -->
		</form>
	</div><!-- #merge_topics -->';
}
