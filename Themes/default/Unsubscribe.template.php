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
 * This displays a unsubscribe
 */
function template_main()
{
	global $scripturl, $context, $txt;
		echo '
	<div id="unsubscribe">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['unsubscribe_topic'], '</h3>
		</div>
		<div class="windowbg2 noup">
			<form action="', $scripturl, '?action=unsubscribe;token=', $context['unsubscribe_token_req'] ,';activity=unsubscribe" method="post" accept-charset="', $context['character_set'], '">
				<p>', $txt['unsubscribe_info'], '</p>
				<input type="submit" value="', $txt['yes'], '" class="button_submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="', $context['unsubscribe_token_var'], '" value="', $context['unsubscribe_token'], '">
			</form>
		</div>
	</div>';
}

?>