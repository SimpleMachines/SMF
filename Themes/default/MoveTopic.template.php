<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
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
		<form action="', Config::$scripturl, '?action=movetopic2;current_board=' . Utils::$context['current_board'] . ';topic=', Utils::$context['current_topic'], '.0" method="post" accept-charset="', Utils::$context['character_set'], '" onsubmit="submitonce(this);">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['move_topic'], '</h3>
			</div>
			<div class="windowbg centertext">
				<div class="move_topic">
					<dl class="settings">
						<dt>
							<strong>', Lang::$txt['move_to'], ':</strong>
						</dt>
						<dd>
							<select name="toboard">';

	foreach (Utils::$context['categories'] as $category)
	{
		echo '
								<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
									<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', $board['id'] == Utils::$context['current_board'] ? ' disabled' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt; ' : '', $board['name'], '</option>';
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
						<input type="checkbox" name="reset_subject" id="reset_subject" onclick="document.getElementById(\'subjectArea\').classList.toggle(\'hidden\');"> ', Lang::$txt['movetopic_change_subject'], '.
					</label><br>
					<fieldset id="subjectArea" class="hidden">
						<dl class="settings">
							<dt><strong>', Lang::$txt['movetopic_new_subject'], ':</strong></dt>
							<dd><input type="text" name="custom_subject" size="30" value="', Utils::$context['subject'], '"></dd>
						</dl>
						<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject"> ', Lang::$txt['movetopic_change_all_subjects'], '.</label>
					</fieldset>';

	// Stick our "create a redirection topic" template in here...
	template_redirect_options('move');

	echo '
					<input type="submit" value="', Lang::$txt['move_topic'], '" onclick="return submitThisOnce(this);" accesskey="s" class="button">
				</div><!-- .move_topic -->
			</div><!-- .windowbg -->';

	if (Utils::$context['back_to_topic'])
		echo '
			<input type="hidden" name="goback" value="1">';

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
						<input type="checkbox" name="postRedirect" id="postRedirect"', Utils::$context['is_approved'] ? ' checked' : '', ' onclick="', Utils::$context['is_approved'] ? '' : 'if (this.checked && !confirm(\'' . Lang::$txt[$type . '_topic_unapproved_js'] . '\')) return false; ', 'document.getElementById(\'reasonArea\').classList.toggle(\'hidden\');"> ', Lang::$txt['post_redirection'], '.
					</label>
					<fieldset id="reasonArea"', Utils::$context['is_approved'] ? '' : 'class="hidden"', '>
						<dl class="settings">
							<dt>
								', Lang::$txt[$type . '_why'], '
							</dt>
							<dd>
								<textarea name="reason">', sprintf(Lang::$txt[$type . 'topic_default'], Lang::$txt['movetopic_auto_board'], Lang::$txt['movetopic_auto_topic']), '</textarea>
							</dd>
							<dt>
								<label for="redirect_topic">', Lang::$txt[$type . 'topic_redirect'], '</label>
							</dt>
							<dd>
								<input type="checkbox" name="redirect_topic" id="redirect_topic" checked>
							</dd>';

	if (!empty(Config::$modSettings['allow_expire_redirect']))
	{
		echo '
							<dt>
								', Lang::$txt['redirect_topic_expires'], '
							</dt>
							<dd>
								<select name="redirect_expires">
									<option value="0">', Lang::$txt['never'], '</option>
									<option value="1440">', Lang::$txt['one_day'], '</option>
									<option value="10080" selected>', Lang::$txt['one_week'], '</option>
									<option value="20160">', Lang::$txt['two_weeks'], '</option>
									<option value="43200">', Lang::$txt['one_month'], '</option>
									<option value="86400">', Lang::$txt['two_months'], '</option>
								</select>
							</dd>';
	}
	else
		echo '
							<input type="hidden" name="redirect_expires" value="0">';

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
				<h3 class="catbg">', Lang::$txt['merge'], '</h3>
			</div>
			<div class="windowbg">
				<p>', Lang::$txt['merge_successful'], '</p>
				<br>
				<ul>
					<li>
						<a href="', Config::$scripturl, '?board=', Utils::$context['target_board'], '.0">', Lang::$txt['message_index'], '</a>
					</li>
					<li>
						<a href="', Config::$scripturl, '?topic=', Utils::$context['target_topic'], '.0">', Lang::$txt['new_merged_topic'], '</a>
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
				<h3 class="catbg">', Lang::$txt['merge'], '</h3>
			</div>
			<div class="information">
				', Lang::$txt['merge_desc'], '
			</div>
			<div class="windowbg">
				<dl class="settings merge_topic">
					<dt>
						<strong>', Lang::$txt['topic_to_merge'], ':</strong>
					</dt>
					<dd>
						', Utils::$context['origin_subject'], '
					</dd>
				</dl>
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['target_topic'], '</h3>
			</div>
			<div class="title_bar">
				<h4 class="titlebg">';

	if (isset(Utils::$context['merge_categories']))
	{
		echo '
					<form action="' . Config::$scripturl . '?action=mergetopics;from=' . Utils::$context['origin_topic'] . ';targetboard=' . Utils::$context['target_board'] . ';board=' . Utils::$context['current_board'] . '.0" method="post" accept-charset="', Utils::$context['character_set'], '" id="mergeSelectBoard">
						', Lang::$txt['target_below'], ' (', Lang::$txt['board'], ':
						<select name="targetboard" onchange="this.form.submit();">';

		foreach (Utils::$context['merge_categories'] as $cat)
		{
			echo '
							<optgroup label="', $cat['name'], '">';

			foreach ($cat['boards'] as $board)
				echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';

			echo '
							</optgroup>';
		}
		echo '
						</select>)
						<input type="hidden" name="from" value="' . Utils::$context['origin_topic'] . '">
						<input type="submit" value="', Lang::$txt['go'], '" class="button">
					</form>';
	}
	else
		echo Lang::$txt['target_below'];

	echo '		</h4>
			</div><!-- .title_bar -->
			<form action="', Config::$scripturl, '?action=mergetopics;sa=options" method="post" accept-charset="', Utils::$context['character_set'], '">';

	// Don't show this if there aren't any topics...
	if (!empty(Utils::$context['topics']))
	{
		echo '
				<div class="pagesection">
					<div class="pagelinks">', Utils::$context['page_index'], '</div>
				</div>
				<div class="windowbg">
					<ul class="merge_topics">';

		foreach (Utils::$context['topics'] as $topic)
			echo '
						<li>
							<a href="', Config::$scripturl, '?action=mergetopics;sa=options;board=', Utils::$context['current_board'], '.0;from=', Utils::$context['origin_topic'], ';to=', $topic['id'], ';', Utils::$context['session_var'], '=', Utils::$context['session_id'], '"><span class="main_icons merge"></span></a>
							<a href="', Config::$scripturl, '?topic=', $topic['id'], '.0" target="_blank" rel="noopener">', $topic['subject'], '</a> ', Lang::$txt['started_by'], ' ', $topic['poster']['link'], '
						</li>';

		echo '
					</ul>
				</div>
				<div class="pagesection">
					<div class="pagelinks">', Utils::$context['page_index'], '</div>
				</div>';
	}
	// Just a nice "There aren't any topics" message
	else
		echo '
				<div class="windowbg">', Lang::$txt['topic_alert_none'], '</div>';

	echo '
				<br>
				<div class="title_bar">
					<h4 class="titlebg">', Lang::$txt['target_id'], '</h4>
				</div>
				<div class="windowbg">
					<dl class="settings merge_topic">
						<dt>
							<strong>', Lang::$txt['merge_to_topic_id'], ': </strong>
						</dt>
						<dd>
							<input type="hidden" name="topics[]" value="', Utils::$context['origin_topic'], '">
							<input type="text" name="topics[]">
							<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">

						</dd>
					</dl>
					<input type="submit" value="', Lang::$txt['merge'], '" class="button">
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
		<form action="', Config::$scripturl, '?action=mergetopics;sa=merge;" method="post" accept-charset="', Utils::$context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['merge_topic_list'], '</h3>
			</div>
			<table class="bordercolor table_grid">
				<thead>
					<tr class="title_bar">
						<th scope="col" style="width:10px;">', Lang::$txt['merge_check'], '</th>
						<th scope="col" class="lefttext">', Lang::$txt['subject'], '</th>
						<th scope="col" class="lefttext">', Lang::$txt['started_by'], '</th>
						<th scope="col" class="lefttext">', Lang::$txt['last_post'], '</th>
						<th scope="col" style="width:20px;">' . Lang::$txt['merge_include_notifications'] . '</th>
					</tr>
				</thead>
				<tbody>';

	foreach (Utils::$context['topics'] as $topic)
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
	echo '
				</tbody>
			</table>
			<br>
			<div class="windowbg">
				<fieldset id="merge_subject" class="merge_options">
					<legend>', Lang::$txt['merge_select_subject'], '</legend>
					<select name="subject" onchange="this.form.custom_subject.style.display = (this.options[this.selectedIndex].value != 0) ? \'none\': \'\' ;">';

	foreach (Utils::$context['topics'] as $topic)
		echo '
						<option value="', $topic['id'], '"' . ($topic['selected'] ? ' selected' : '') . '>', $topic['subject'], '</option>';
	echo '
						<option value="0">', Lang::$txt['merge_custom_subject'], ':</option>
					</select>
					<br>
					<input type="text" name="custom_subject" size="60" id="custom_subject" class="custom_subject" style="display: none;"><br>
					<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject" value="1"> ', Lang::$txt['movetopic_change_all_subjects'], '</label>
				</fieldset>';

	// Show an option to create a redirection topic as well...
	template_redirect_options('merge');

	if (!empty(Utils::$context['boards']) && count(Utils::$context['boards']) > 1)
	{
		echo '
				<fieldset id="merge_board" class="merge_options">
					<legend>', Lang::$txt['merge_select_target_board'], '</legend>
					<ul>';

		foreach (Utils::$context['boards'] as $board)
			echo '
						<li>
							<input type="radio" name="board" value="' . $board['id'] . '"' . ($board['selected'] ? ' checked' : '') . '> ' . $board['name'] . '
						</li>';
		echo '
					</ul>
				</fieldset>';
	}
	if (!empty(Utils::$context['polls']))
	{
		echo '
				<fieldset id="merge_poll" class="merge_options">
					<legend>' . Lang::$txt['merge_select_poll'] . '</legend>
					<ul>';

		foreach (Utils::$context['polls'] as $poll)
			echo '
						<li>
							<input type="radio" name="poll" value="' . $poll['id'] . '"' . ($poll['selected'] ? ' checked' : '') . '> ' . $poll['question'] . ' (' . Lang::$txt['topic'] . ': <a href="' . Config::$scripturl . '?topic=' . $poll['topic']['id'] . '.0" target="_blank" rel="noopener">' . $poll['topic']['subject'] . '</a>)
						</li>';
		echo '
						<li>
							<input type="radio" name="poll" value="-1"> (' . Lang::$txt['merge_no_poll'] . ')
						</li>
					</ul>
				</fieldset>';
	}

	echo '
				<div class="auto_flow">
					<input type="submit" value="' . Lang::$txt['merge'] . '" class="button">
					<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
					<input type="hidden" name="sa" value="execute">
				</div>
			</div><!-- .windowbg -->
		</form>
	</div><!-- #merge_topics -->';
}

?>