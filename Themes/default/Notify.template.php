<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2022 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.0
 */

/**
 * The main notification bar.
 */
function template_main()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', $txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', $txt['notify_topic_prompt'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=notify;sa=on;topic=', $context['current_topic'], '.', $context['start'], ';', (!empty($context['notify_info']['token']) ? 'u=' . $context['notify_info']['u'] . ';token=' . $context['notify_info']['token'] : $context['session_var'] . '=' . $context['session_id']), '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=notify;sa=off;topic=', $context['current_topic'], '.', $context['start'], ';', (!empty($context['notify_info']['token']) ? 'u=' . $context['notify_info']['u'] . ';token=' . $context['notify_info']['token'] : $context['session_var'] . '=' . $context['session_id']), '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}

/**
 * Board notification bar.
 */
function template_notify_board()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', $txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', $txt['notify_board_prompt'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=notifyboard;sa=on;board=', $context['current_board'], '.', $context['start'], ';', (!empty($context['notify_info']['token']) ? 'u=' . $context['notify_info']['u'] . ';token=' . $context['notify_info']['token'] : $context['session_var'] . '=' . $context['session_id']), '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=notifyboard;sa=off;board=', $context['current_board'], '.', $context['start'], ';', (!empty($context['notify_info']['token']) ? 'u=' . $context['notify_info']['u'] . ';token=' . $context['notify_info']['token'] : $context['session_var'] . '=' . $context['session_id']), '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}

/**
 *
 */
function template_notify_announcements()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', $txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', $txt['notify_announcements_prompt'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=notifyannouncements;sa=on;', (!empty($context['notify_info']['token']) ? 'u=' . $context['notify_info']['u'] . ';token=' . $context['notify_info']['token'] : $context['session_var'] . '=' . $context['session_id']), '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=notifyannouncements;sa=off;', (!empty($context['notify_info']['token']) ? 'u=' . $context['notify_info']['u'] . ';token=' . $context['notify_info']['token'] : $context['session_var'] . '=' . $context['session_id']), '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}

/**
 * Displays a message indicating the user's notification preferences were successfully changed
 */
function template_notify_pref_changed()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', $txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', $context['notify_success_msg'], '</p>
		</div>';
}

/**
 *
 */
function template_unsubscribe_request()
{
	global $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', $txt['unsubscribe'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', $txt['unsubscribe_process'], '</p>
		</div>
		<form method="post" accept-charset="', $context['character_set'], '" action="', $scripturl, '?action=unsubscribe">
			<div class="roundframe centertext">
				<dl>
					<dt>', $txt['user_email_address'], ':</dt>
					<dd><input type="text" name="email" value="', $context['email_address'], '" size="60"></dd>
				</dl>
				<p class="centertext">
					<input type="submit" value="', $txt['request_unsubscribe'], '" class="button">
				</p>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['unsubscribe_token_var'], '" value="', $context['unsubscribe_token'], '">
		</form>';
}

/**
 *
 */
function template_unsubscribe_success()
{
	global $txt, $context;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', $txt['unsubscribe'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', $context['success_msg'], '</p>
		</div>';
}

/**
 *
 */
function template_unsubscribe_confirm()
{
	global $txt, $scripturl, $context;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', $txt['unsubscribe'], '
			</h3>
		</div>
		<form method="post" accept-charset="', $context['character_set'], '" action="', $scripturl, '?action=unsubscribe">
			<div class="roundframe centertext">
				<p class="centertext">
					<button name="notify" class="button">', $txt['unsubscribe_remove_notifications'], '</button>
				</p>
			</div>
			<input type="hidden" name="u" value="', $context['user_id'], '">
			<input type="hidden" name="token" value="', $context['token'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="', $context['unsubscribe_token_var'], '" value="', $context['unsubscribe_token'], '">
		</form>';
}

?>