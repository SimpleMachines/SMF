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

//------------------------------------------------------------------------------
/*	This template contains two humble sub templates - main. Its job is pretty
	simple: it collects the information we need to actually send the topic.

	The report sub template gets shown from:
		'?action=reporttm;topic=##.##;msg=##'
		'?action=reporttm;u=#'
	It should submit to:
		'?action=reporttm;topic=' . $context['current_topic'] . '.' . $context['start']
		'?action=reporttm;u=#'
	It only needs to send the following fields:
		comment: an additional comment to give the moderator.
		sc: the session id, or $context['session_id'].
*/

function template_main()
{
	global $context, $txt;

	// Want to see your master piece?
	echo '
	<div id="preview_section"', isset($context['preview_message']) ? '' : ' style="display: none;"', '>
		<div class="cat_bar">
			<h3 class="catbg">
				<span>', $txt['preview'], '</span>
			</h3>
		</div>
		<div class="windowbg">
			<div class="post" id="preview_body">
				', empty($context['preview_message']) ? '<br>' : $context['preview_message'], '
			</div>
		</div>
	</div><br>';

	echo '
	<div id="report_form">
		<form action="', $context['submit_url'], '" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="', $context['report_type'], '" value="', $context['reported_item'], '">
				<div class="cat_bar">
					<h3 class="catbg">', $context['page_title'], '</h3>
				</div>
				<div class="windowbg">';

	if (!empty($context['post_errors']))
	{
		echo '
					<div id="error_box" class="errorbox">
						<ul id="error_list">';

		foreach ($context['post_errors'] as $key => $error)
			echo '
							<li id="error_', $key, '" class="error">', $error, '</li>';

		echo '
						</ul>';
	}
	else
		echo '
						<div style="display:none" id="error_box" class="errorbox">';

	echo '
					</div>';

	echo '
					<p class="noticebox">', $context['notice'], '</p>
					<br>
					<dl class="settings" id="report_post">';

	echo '
						<dt>
							<label for="report_comment">', $txt['enter_comment'], '</label>:
						</dt>
						<dd>
							<textarea type="text" id="report_comment" name="comment" rows="5">', $context['comment_body'], '</textarea>
						</dd>';

	echo '
					</dl>
					<div class="flow_auto">
						<input type="submit" name="preview" value="', $txt['preview'] , '" class="button_submit">
						<input type="submit" name="save" value="', $txt['rtm10'], '" style="margin-left: 1ex;" class="button_submit">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					</div>
				</div>
		</form>
	</div>';
}

?>