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
 * A form for creating and/or editing a poll.
 */
function template_main()
{
	if (!empty(Utils::$context['poll_error']['messages'])) {
		echo '
			<div class="errorbox">
				<dl class="poll_error">
					<dt>
						', Lang::getTxt(Utils::$context['is_edit'] ? 'error_while_editing_poll' : 'error_while_adding_poll', file: 'Errors'), '
					</dt>
					<dt>
						', empty(Utils::$context['poll_error']['messages']) ? '' : implode('<br>', Utils::$context['poll_error']['messages']), '
					</dt>
				</dl>
			</div>';
	}

	// Start the main poll form.
	echo '
	<div id="edit_poll">
		<form action="' . Config::$scripturl . '?action=editpoll2', Utils::$context['is_edit'] ? '' : ';add', ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . '" method="post" accept-charset="UTF-8" onsubmit="submitonce(this);" name="postmodify" id="postmodify">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>';

	echo '
			<div>
				<div class="roundframe noup">
					<input type="hidden" name="poll" value="', Utils::$context['poll']['id'], '">
					<fieldset id="poll_main">
						<legend><span ', (isset(Utils::$context['poll_error']['no_question']) ? ' class="error"' : ''), '>', Lang::getTxt('poll_question', file: 'General'), '</span></legend>
						<dl class="settings poll_options" id="poll_choices" data-more-txt="', Lang::getTxt('poll_add_option', file: 'Post'), '" data-option-txt="', Lang::getTxt('option_number', [999], file: 'Post'), '">
							<dt>', Lang::getTxt('poll_question', file: 'General'), '</dt>
							<dd><input type="text" name="question" size="80" value="', Utils::$context['poll']['question'], '"></dd>';

	foreach (Utils::$context['choices'] as $choice) {
		echo '
							<dt>
								<label for="options-', $choice['id'], '" ', (isset(Utils::$context['poll_error']['poll_few']) ? ' class="error"' : ''), '>', Lang::getTxt('option_number', [$choice['number']], file: 'Post'), '</label>
							</dt>
							<dd>
								<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" size="80" maxlength="255">';

		// Does this option have a vote count yet, or is it new?
		if ($choice['votes'] != -1) {
			echo ' (', Lang::getTxt('number_of_votes', [$choice['votes']], file: 'Post'), ')';
		}

		echo '
							</dd>';
	}

	// poll.js puts the "add option" button here, after the list it appends to.
	echo '
						</dl>
					</fieldset>
					<fieldset id="poll_options">
						<legend>', Lang::getTxt('poll_options', file: 'Post'), '</legend>
						<dl class="settings poll_options">';

	if (Utils::$context['can_moderate_poll']) {
		echo '
							<dt>
								<label for="poll_max_votes">', Lang::getTxt('poll_max_votes', file: 'Post'), '</label>
							</dt>
							<dd>
								<input type="number" name="poll_max_votes" id="poll_max_votes" min="1" value="', Utils::$context['poll']['max_votes'], '">
							</dd>
							<dt>
								<label for="poll_expire">', Lang::getTxt('poll_run', file: 'Post'), '</label><br>
								<em class="smalltext">', Lang::getTxt('poll_run_limit', file: 'Post'), '</em>
							</dt>
							<dd>
								<input type="number" name="poll_expire" id="poll_expire" min="0" max="9999" value="', intval(Utils::$context['poll']['expiration']), '">
							</dd>
							<dt>
								<label for="poll_change_vote">', Lang::getTxt('poll_do_change_vote', file: 'Post'), '</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty(Utils::$context['poll']['change_vote']) ? ' checked' : '', '>
							</dd>';

		if (Utils::$context['poll']['guest_vote_allowed']) {
			echo '
							<dt>
								<label for="poll_guest_vote">', Lang::getTxt('poll_guest_vote', file: 'Post'), '</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty(Utils::$context['poll']['guest_vote']) ? ' checked' : '', '>
							</dd>';
		}
	}

	echo '
							<dt>
								', Lang::getTxt('poll_results_visibility', file: 'Post'), '
							</dt>
							<dd>
								<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', Utils::$context['poll']['hide_results'] == 0 ? ' checked' : '', '> <label for="poll_results_anyone">', Lang::getTxt('poll_results_anyone', file: 'Post'), '</label><br>
								<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', Utils::$context['poll']['hide_results'] == 1 ? ' checked' : '', '> <label for="poll_results_voted">', Lang::getTxt('poll_results_voted', file: 'Post'), '</label><br>
								<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', Utils::$context['poll']['hide_results'] == 2 ? ' checked' : '', empty(Utils::$context['poll']['expiration']) ? ' disabled' : '', '> <label for="poll_results_expire">', Lang::getTxt('poll_results_after', file: 'Post'), '</label>
							</dd>
						</dl>
					</fieldset>';

	// If this is an edit, we can allow them to reset the vote counts.
	if (Utils::$context['is_edit']) {
		echo '
					<fieldset id="poll_reset">
						<legend>', Lang::getTxt('reset_votes', file: 'Post'), '</legend>
						<input type="checkbox" name="resetVoteCount" value="on"> ' . Lang::getTxt('reset_votes_check', file: 'Post') . '
					</fieldset>';
	}
	echo '
					<input type="submit" name="post" value="', Lang::getTxt('save', file: 'General'), '" onclick="return submitThisOnce(this);" accesskey="s" class="button">
				</div><!-- .roundframe -->
			</div>
			<input type="hidden" name="seqnum" value="', Utils::$context['form_sequence_number'], '">
			<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
		</form>
	</div><!-- #edit_poll -->';
}
