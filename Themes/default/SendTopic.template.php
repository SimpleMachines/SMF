<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2014 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

//------------------------------------------------------------------------------
/*	This template contains two humble sub templates - main. Its job is pretty
	simple: it collects the information we need to actually send the topic.

	The report sub template gets shown from:
		'?action=reporttm;topic=##.##;msg=##'
	It should submit to:
		'?action=reporttm;topic=' . $context['current_topic'] . '.' . $context['start']
	It only needs to send the following fields:
		comment: an additional comment to give the moderator.
		sc: the session id, or $context['session_id'].
*/

// Send an email to a user!
function template_custom_email()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="send_topic">
		<form action="', $scripturl, '?action=emailuser;sa=email" method="post" accept-charset="', $context['character_set'], '">
			<div class="cat_bar">
				<h3 class="catbg">
					<span class="generic_icons mail icon"></span>', $context['page_title'], '
				</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<dl class="settings send_mail">
						<dt>
							<strong>', $txt['sendtopic_receiver_name'], ':</strong>
						</dt>
						<dd>
							', $context['recipient']['link'], '
						</dd>';

	// Can the user see the persons email?
	if ($context['can_view_receipient_email'])
		echo '
						<dt>
							<strong>', $txt['sendtopic_receiver_email'], ':</strong>
						</dt>
						<dd>
							', $context['recipient']['email_link'], '
						</dd>
					</dl>
					<hr>
					<dl class="settings send_mail">';

	// If it's a guest we need their details.
	if ($context['user']['is_guest'])
		echo '
						<dt>
							<label for="y_name"><strong>', $txt['sendtopic_sender_name'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="y_name" name="y_name" size="24" maxlength="40" value="', $context['user']['name'], '" class="input_text">
						</dd>
						<dt>
							<label for="y_email"><strong>', $txt['sendtopic_sender_email'], ':</strong></label><br>
							<span class="smalltext">', $txt['send_email_disclosed'], '</span>
						</dt>
						<dd>
							<input type="text" id="y_mail" name="y_email" size="24" maxlength="50" value="', $context['user']['email'], '" class="input_text">
						</dt>';
	// Otherwise show the user that we know their email.
	else
		echo '
						<dt>
							<strong>', $txt['sendtopic_sender_email'], ':</strong><br>
							<span class="smalltext">', $txt['send_email_disclosed'], '</span>
						</dt>
						<dd>
							<em>', $context['user']['email'], '</em>
						</dd>';

	echo '
						<dt>
							<label for="email_subject"><strong>', $txt['send_email_subject'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="email_subject" name="email_subject" size="50" maxlength="100" class="input_text">
						</dd>
						<dt>
							<label for="email_body"><strong>', $txt['message'], ':</strong></label>
						</dt>
						<dd>
							<textarea id="email_body" name="email_body" rows="10" cols="20" style="' . (isBrowser('is_ie8') ? 'width: 635px; max-width: 90%; min-width: 90%' : 'width: 90%') . ';"></textarea>
						</dd>
					</dl>
					<hr class="hrcolor">
					<div class="flow_auto">
						<input type="submit" name="send" value="', $txt['sendtopic_send'], '" class="button_submit">
					</div>
				</div>
			</div>';

	foreach ($context['form_hidden_vars'] as $key => $value)
		echo '
			<input type="hidden" name="', $key, '" value="', $value, '">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>';
}

function template_report()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="report_topic">
		<form action="', $scripturl, '?action=reporttm;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '">
			<input type="hidden" name="msg" value="' . $context['message_id'] . '">
				<div class="cat_bar">
					<h3 class="catbg">', $txt['report_to_mod'], '</h3>
				</div>
				<div class="windowbg">
					<div class="content">';

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
						<p class="noticebox">', $txt['report_to_mod_func'], '</p>
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
							<input type="submit" name="save" value="', $txt['rtm10'], '" style="margin-left: 1ex;" class="button_submit">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						</div>
					</div>
				</div>
		</form>
	</div>';
}

?>