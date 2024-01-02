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
 * Template for browsing the mail queue.
 */
function template_browse()
{
	echo '
	<div id="manage_mail">
		<div id="mailqueue_stats">
			<div class="cat_bar">
				<h3 class="catbg">', Lang::$txt['mailqueue_stats'], '</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt><strong>', Lang::$txt['mailqueue_size'], '</strong></dt>
					<dd>', Utils::$context['mail_queue_size'], '</dd>
					<dt><strong>', Lang::$txt['mailqueue_oldest'], '</strong></dt>
					<dd>', Utils::$context['oldest_mail'], '</dd>
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
	// The results.
	if (!empty(Utils::$context['result']))
	{
		if (Utils::$context['result'] == 'failure')
			$result_txt = sprintf(Lang::$txt['mailtest_result_failure'], Config::$scripturl . '?action=admin;area=logs;sa=errorlog;desc');
		else
			$result_txt = Lang::$txt['mailtest_result_success'];

		echo '
					<div class="', Utils::$context['result'] == 'success' ? 'infobox' : 'errorbox', '">', $result_txt, '</div>';
	}

	echo '
	<form id="admin_form_wrapper" action="', Utils::$context['post_url'], '" method="post">
		<div class="cat_bar">
			<h3 class="catbg">', Lang::$txt['mailtest_header'], '</h3>
		</div>
		<div class="windowbg">
				<dl id="post_header">
					<dt><span id="caption_subject">', Lang::$txt['subject'], '</span></dt>
					<dd><input type="text" name="subject" tabindex="1" size="80" maxlength="80"></dd>
				</dl>
				<textarea class="editor" name="message" rows="5" cols="200" tabindex="2"></textarea>
				<dl id="post_footer">
					<dd><input type="submit" value="', Lang::$txt['send_message'], '" class="button"></dd>
				</dl>
		</div>
	</form>';
}

?>