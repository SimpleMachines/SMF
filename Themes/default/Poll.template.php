<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Some javascript for adding more options.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			var pollOptionNum = 0;

			function addPollOption()
			{
				if (pollOptionNum == 0)
				{
					for (var i = 0; i < document.forms.postmodify.elements.length; i++)
						if (document.forms.postmodify.elements[i].id.substr(0, 8) == "options-")
							pollOptionNum++;
				}
				pollOptionNum++

				setOuterHTML(document.getElementById("pollMoreOptions"), \'<li><label for="options-\' + pollOptionNum + \'" ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['option'], ' \' + pollOptionNum + \'</label>: <input type="text" name="options[\' + (pollOptionNum - 1) + \']" id="options-\' + (pollOptionNum - 1) + \'" value="" size="80" maxlength="255" class="input_text" /></li><li id="pollMoreOptions"></li\');
			}
		// ]]></script>';

	// Start the main poll form.
	echo '
	<div id="edit_poll">
		<form action="' . $scripturl . '?action=editpoll2', $context['is_edit'] ? '' : ';add', ';topic=' . $context['current_topic'] . '.' . $context['start'] . '" method="post" accept-charset="', $context['character_set'], '" onsubmit="submitonce(this); smc_saveEntities(\'postmodify\', [\'question\'], \'options-\');" name="postmodify" id="postmodify">
			<div class="cat_bar">
				<h3 class="catbg">', $context['page_title'], '</h3>
			</div>';

	if (!empty($context['poll_error']['messages']))
		echo '
			<div class="errorbox">
				<dl class="poll_error">
					<dt>
						', $context['is_edit'] ? $txt['error_while_editing_poll'] : $txt['error_while_adding_poll'], ':
					</dt>
					<dt>
						', empty($context['poll_error']['messages']) ? '' : implode('<br />', $context['poll_error']['messages']), '
					</dt>
				</dl>
			</div>';

	echo '
			<div>
			<span class="upperframe"><span></span></span>
				<div class="roundframe">
					<input type="hidden" name="poll" value="', $context['poll']['id'], '" />
					<fieldset id="poll_main">
						<legend><span ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], ':</span></legend>
						<input type="text" name="question" size="80" value="', $context['poll']['question'], '" class="input_text" />
						<ul class="poll_main">';

	foreach ($context['choices'] as $choice)
	{
		echo '
							<li>
								<label for="options-', $choice['id'], '" ', (isset($context['poll_error']['poll_few']) ? ' class="error"' : ''), '>', $txt['option'], ' ', $choice['number'], '</label>:
								<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" class="input_text" size="80" maxlength="255" />';

		// Does this option have a vote count yet, or is it new?
		if ($choice['votes'] != -1)
			echo ' (', $choice['votes'], ' ', $txt['votes'], ')';

		echo '
							</li>';
	}

	echo '
							<li id="pollMoreOptions"></li>
						</ul>
						<strong><a href="javascript:addPollOption(); void(0);">(', $txt['poll_add_option'], ')</a></strong>
					</fieldset>
					<fieldset id="poll_options">
						<legend>', $txt['poll_options'], ':</legend>
						<dl class="settings poll_options">';

	if ($context['can_moderate_poll'])
	{
		echo '
							<dt>
								<label for="poll_max_votes">', $txt['poll_max_votes'], ':</label>
							</dt>
							<dd>
								<input type="text" name="poll_max_votes" id="poll_max_votes" size="2" value="', $context['poll']['max_votes'], '" class="input_text" />
							</dd>
							<dt>
								<label for="poll_expire">', $txt['poll_run'], ':</label><br />
								<em class="smalltext">', $txt['poll_run_limit'], '</em>
							</dt>
							<dd>
								<input type="text" name="poll_expire" id="poll_expire" size="2" value="', $context['poll']['expiration'], '" onchange="this.form.poll_hide[2].disabled = isEmptyText(this) || this.value == 0; if (this.form.poll_hide[2].checked) this.form.poll_hide[1].checked = true;" maxlength="4" class="input_text" /> ', $txt['days_word'], '
							</dd>
							<dt>
								<label for="poll_change_vote">', $txt['poll_do_change_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty($context['poll']['change_vote']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';

		if ($context['poll']['guest_vote_allowed'])
			echo '
							<dt>
								<label for="poll_guest_vote">', $txt['poll_guest_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty($context['poll']['guest_vote']) ? ' checked="checked"' : '', ' class="input_check" />
							</dd>';
	}

	echo '
							<dt>
								', $txt['poll_results_visibility'], ':
							</dt>
							<dd>
								<input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', $context['poll']['hide_results'] == 0 ? ' checked="checked"' : '', ' class="input_radio" /> <label for="poll_results_anyone">', $txt['poll_results_anyone'], '</label><br />
								<input type="radio" name="poll_hide" id="poll_results_voted" value="1"', $context['poll']['hide_results'] == 1 ? ' checked="checked"' : '', ' class="input_radio" /> <label for="poll_results_voted">', $txt['poll_results_voted'], '</label><br />
								<input type="radio" name="poll_hide" id="poll_results_expire" value="2"', $context['poll']['hide_results'] == 2 ? ' checked="checked"' : '', empty($context['poll']['expiration']) ? 'disabled="disabled"' : '', ' class="input_radio" /> <label for="poll_results_expire">', $txt['poll_results_after'], '</label>
							</dd>
						</dl>
					</fieldset>';
	// If this is an edit, we can allow them to reset the vote counts.
	if ($context['is_edit'])
		echo '
					<fieldset id="poll_reset">
						<legend>', $txt['reset_votes'], '</legend>
						<input type="checkbox" name="resetVoteCount" value="on" class="input_check" /> ' . $txt['reset_votes_check'] . '
					</fieldset>';
	echo '
					<div class="righttext padding">
						<input type="submit" name="post" value="', $txt['save'], '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" />
					</div>
				</div>
				<span class="lowerframe"><span></span></span>
			</div>
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
		</form>
	</div>
	<br class="clear" />';
}

?>