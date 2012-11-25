<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

function template_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="manage_mail">
		<div id="mailqueue_stats">
			<div class="cat_bar">
				<h3 class="catbg">', $txt['mailqueue_stats'], '</h3>
			</div>
			<div class="windowbg">
				<div class="content">
					<dl class="settings">
						<dt><strong>', $txt['mailqueue_size'], '</strong></dt>
						<dd>', $context['mail_queue_size'], '</dd>
						<dt><strong>', $txt['mailqueue_oldest'], '</strong></dt>
						<dd>', $context['oldest_mail'], '</dd>
					</dl>
				</div>
			</div>
		</div>';

	template_show_list('mail_queue');

	echo '
	</div>';
}

?>