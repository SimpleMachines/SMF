<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

/**
 * Template for browsing the mail queue.
 */
function template_browse()
{
	global $context, $txt;

	echo '
	<div id="manage_mail">
		<div id="mailqueue_stats">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mailqueue_stats'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt><strong>', $txt['mailqueue_size'], '</strong></dt>
					<dd>', $context['mail_queue_size'], '</dd>
					<dt><strong>', $txt['mailqueue_oldest'], '</strong></dt>
					<dd>', $context['oldest_mail'], '</dd>
				</dl>
			</div>
		</div>';

	template_show_list('mail_queue');

	echo '
	</div>';
}

/**
 * Template for testing mail send.
 */

function template_mailtest()
{
	global $context, $txt, $scripturl;

	// The results.
	if (!empty($context['result']))
	{
		if ($context['result'] == 'failure')
			$result_txt = sprintf($txt['mailtest_result_failure'], $scripturl . '?action=admin;area=logs;sa=errorlog;desc');
		else
			$result_txt = $txt['mailtest_result_success'];

		echo '
					<div class="', $context['result'] == 'success' ? 'infobox' : 'errorbox', '">', $result_txt, '</div>';
	}

	echo '
	<form id="admin_form_wrapper" action="', $context['post_url'], '" method="post">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['mailtest_header'], '</h3>
		</div>
		<div class="windowbg">
				<dl id="post_header">
					<dt><span id="caption_subject">', $txt['subject'], '</span></dt>
					<dd><input type="text" name="subject" tabindex="1" size="80" maxlength="80"></dd>
				</dl>
				<textarea class="editor" name="message" rows="5" cols="200" tabindex="2"></textarea>
				<dl id="post_footer">
					<dd><input type="submit" value="', $txt['send_message'], '" class="button"></dd>
				</dl>
		</div>
	</form>';
}

?>