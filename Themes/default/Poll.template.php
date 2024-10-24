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
use SMF\Utils;

/**
 * A form for creating and/or editing a poll.
 */
function template_main()
{
	if (!empty(Utils::$context['poll_error']['messages']))
		echo '
			<div class="errorbox">
				<dl class="poll_error">
					<dt>
						', Utils::$context['is_edit'] ? Lang::$txt['error_while_editing_poll'] : Lang::$txt['error_while_adding_poll'], '
					</dt>
					<dt>
						', empty(Utils::$context['poll_error']['messages']) ? '' : implode('<br>', Utils::$context['poll_error']['messages']), '
					</dt>
				</dl>
			</div>';

	// Start the main poll form.
	echo '
	<div id="edit_poll">
		<form action="' . Config::$scripturl . '?action=editpoll2', Utils::$context['is_edit'] ? '' : ';add', ';topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start'] . '" method="post" accept-charset="', Utils::$context['character_set'], '" name="postmodify" id="postmodify">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>';

	echo '
			<div>
				<div class="roundframe noup">
					<input type="hidden" name="poll" value="', Utils::$context['poll']['id'], '">
					<fieldset id="poll_main">
						<legend><span ', (isset(Utils::$context['poll_error']['no_question']) ? ' class="error"' : ''), '>', Lang::$txt['poll_question'], '</span></legend>
							<dt>', Lang::$txt['poll_question'], '</dt>
						<dl class="settings poll_options" data-more-txt="', Lang::$txt['poll_add_option'], '" data-option-txt="', Lang::$txt['option'], '">
							<dd><input type="text" name="question" size="80" value="', Utils::$context['poll']['question'], '"></dd>';

	foreach (Utils::$context['choices'] as $choice)
	{
		echo '
							<dt>
								<label for="options-', $choice['id'], '" ', (isset(Utils::$context['poll_error']['poll_few']) ? ' class="error"' : ''), '>', Lang::getTxt('option_number', [$choice['number']]), '</label>
							</dt>
							<dd>
								<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" size="80" maxlength="255">';

		// Does this option have a vote count yet, or is it new?
		if ($choice['votes'] != -1)
			echo ' (', Lang::getTxt('number_of_votes', [$choice['votes']]), ')';

		echo '
							</dd>';
	}

	echo '
						</dl>
					</fieldset>
					<fieldset id="poll_options">
						<legend>', Lang::$txt['poll_options'], '</legend>
						<dl class="settings poll_options">';

	if (Utils::$context['can_moderate_poll'])
	{
		echo '
							<dt>
								<label for="poll_max_votes">', Lang::$txt['poll_max_votes'], '</label>
							</dt>
							<dd>
								<input type="number" name="poll_max_votes" id="poll_max_votes" min="1" value="', Utils::$context['poll']['max_votes'], '">
							</dd>
							<dt>
								<label for="poll_expire">', Lang::$txt['poll_run'], '</label><br>
								<small><i>', Lang::$txt['poll_run_limit'], '</i></small>
							</dt>
							<dd>
								<input type="number" name="poll_expire" id="poll_expire" min="0" max="9999" value="', intval(Utils::$context['poll']['expiration']), '" onchange="this.form.poll_hide[2].disabled = isEmptyText(this) || this.value == 0; if (this.form.poll_hide[2].checked) this.form.poll_hide[1].checked = true;">
							</dd>
							<dt>
								<label for="poll_change_vote">', Lang::$txt['poll_do_change_vote'], '</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty(Utils::$context['poll']['change_vote']) ? ' checked' : '', '>
							</dd>';

		if (Utils::$context['poll']['guest_vote_allowed'])
			echo '
							<dt>
								<label for="poll_guest_vote">', Lang::$txt['poll_guest_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty(Utils::$context['poll']['guest_vote']) ? ' checked' : '', '>
							</dd>';
	}

	echo '
							<dt>
								', Lang::$txt['poll_results_visibility'], ':
							</dt>
							<dd>
								<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', Utils::$context['poll']['hide_results'] == 0 ? ' checked' : '', '> <label for="poll_results_anyone">', Lang::$txt['poll_results_anyone'], '</label><br>
								<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', Utils::$context['poll']['hide_results'] == 1 ? ' checked' : '', '> <label for="poll_results_voted">', Lang::$txt['poll_results_voted'], '</label><br>
								<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', Utils::$context['poll']['hide_results'] == 2 ? ' checked' : '', empty(Utils::$context['poll']['expiration']) ? ' disabled' : '', '> <label for="poll_results_expire">', Lang::$txt['poll_results_after'], '</label>
							</dd>
						</dl>
					</fieldset>';

	// If this is an edit, we can allow them to reset the vote counts.
	if (Utils::$context['is_edit'])
		echo '
					<fieldset id="poll_reset">
						<legend>', Lang::$txt['reset_votes'], '</legend>
						<input type="checkbox" name="resetVoteCount" value="on"> ' . Lang::$txt['reset_votes_check'] . '
					</fieldset>';
	echo '
					<input type="submit" name="post" value="', Lang::$txt['save'], '" accesskey="s" class="button">
				</div><!-- .roundframe -->
			</div>
			<input type="hidden" name="seqnum" value="', Utils::$context['form_sequence_number'], '">
			<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
		</form>
	</div><!-- #edit_poll -->';
}

?>