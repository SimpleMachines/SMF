<?php
/**
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
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
			<div class="windowbg2 noup">
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

?>