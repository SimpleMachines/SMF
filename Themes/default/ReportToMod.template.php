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

use SMF\Lang;
use SMF\Utils;

//------------------------------------------------------------------------------
/*	This template contains two humble sub templates - main. Its job is pretty
	simple: it collects the information we need to actually send the topic.

	The report sub template gets shown from:
		'?action=reporttm;topic=##.##;msg=##'
		'?action=reporttm;u=#'
	It should submit to:
		'?action=reporttm;topic=' . Utils::$context['current_topic'] . '.' . Utils::$context['start']
		'?action=reporttm;u=#'
	It only needs to send the following fields:
		comment: an additional comment to give the moderator.
		sc: the session id, or Utils::$context['session_id'].
*/

/**
 * The main "report this to the moderator" page
 */
function template_main()
{
	// Want to see your master piece?
	echo '
	<div id="preview_section"', isset(Utils::$context['preview_message']) ? '' : ' class="hidden"', '>
		<div class="cat_bar">
			<h3 class="catbg">
				<span>', Lang::$txt['preview'], '</span>
			</h3>
		</div>
		<div class="windowbg">
			<div class="post" id="preview_body">
				', empty(Utils::$context['preview_message']) ? '<br>' : Utils::$context['preview_message'], '
			</div>
		</div>
	</div>';

	echo '
	<div id="report_form">
		<form action="', Utils::$context['submit_url'], '" method="post" accept-charset="', Utils::$context['character_set'], '">
			<input type="hidden" name="', Utils::$context['report_type'], '" value="', Utils::$context['reported_item'], '">
			<div class="cat_bar">
				<h3 class="catbg">', Utils::$context['page_title'], '</h3>
			</div>
			<div class="windowbg">';

	if (!empty(Utils::$context['post_errors']))
	{
		echo '
				<div id="error_box" class="errorbox">
					<ul id="error_list">';

		foreach (Utils::$context['post_errors'] as $key => $error)
			echo '
						<li id="error_', $key, '" class="error">', $error, '</li>';

		echo '
					</ul>';
	}
	else
		echo '
				<div id="error_box" class="errorbox hidden">';

	echo '
				</div>';

	echo '
				<p class="noticebox">', Utils::$context['notice'], '</p>
				<dl class="settings" id="report_post">
					<dt>
						<label for="report_comment">', Lang::$txt['enter_comment'], '</label>:
					</dt>
					<dd>
						<textarea type="text" id="report_comment" name="comment" maxlength="254">', Utils::$context['comment_body'], '</textarea>
					</dd>
				</dl>
				<input type="submit" name="preview" value="', Lang::$txt['preview'], '" class="button">
				<input type="submit" name="save" value="', Lang::$txt['report_submit'], '" class="button">
				<input type="hidden" name="', Utils::$context['session_var'], '" value="', Utils::$context['session_id'], '">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #report_form -->';
}

?>