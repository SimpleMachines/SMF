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

function template_browse()
{
	global $context, $txt;

	echo '
	<div id="manage_mail">
		<div id="mailqueue_stats">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mailqueue_stats'], '</h3>
			</div>
			<div class="windowbg2">
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