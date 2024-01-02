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
 * The main notification bar.
 */
function template_main()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', Lang::$txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', Lang::$txt['notify_topic_prompt'], '</p>
			<p>
				<strong><a href="', Config::$scripturl, '?action=notifytopic;sa=on;topic=', Utils::$context['current_topic'], '.', Utils::$context['start'], ';', (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '">', Lang::$txt['yes'], '</a> - <a href="', Config::$scripturl, '?action=notifytopic;sa=off;topic=', Utils::$context['current_topic'], '.', Utils::$context['start'], ';', (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '">', Lang::$txt['no'], '</a></strong>
			</p>
		</div>';
}

/**
 * Board notification bar.
 */
function template_notify_board()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', Lang::$txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', Lang::$txt['notify_board_prompt'], '</p>
			<p>
				<strong><a href="', Config::$scripturl, '?action=notifyboard;sa=on;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';', (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '">', Lang::$txt['yes'], '</a> - <a href="', Config::$scripturl, '?action=notifyboard;sa=off;board=', Utils::$context['current_board'], '.', Utils::$context['start'], ';', (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '">', Lang::$txt['no'], '</a></strong>
			</p>
		</div>';
}

/**
 *
 */
function template_notify_announcements()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', Lang::$txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', Lang::$txt['notify_announcements_prompt'], '</p>
			<p>
				<strong><a href="', Config::$scripturl, '?action=notifyannouncements;sa=on;', (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '">', Lang::$txt['yes'], '</a> - <a href="', Config::$scripturl, '?action=notifyannouncements;sa=off;', (!empty(Utils::$context['notify_info']['token']) ? 'u=' . Utils::$context['notify_info']['u'] . ';token=' . Utils::$context['notify_info']['token'] : Utils::$context['session_var'] . '=' . Utils::$context['session_id']), '">', Lang::$txt['no'], '</a></strong>
			</p>
		</div>';
}

/**
 * Displays a message indicating the user's notification preferences were successfully changed
 */
function template_notify_pref_changed()
{
	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons mail icon"></span>
				', Lang::$txt['notify'], '
			</h3>
		</div>
		<div class="roundframe centertext">
			<p>', Utils::$context['notify_success_msg'], '</p>
		</div>';
}

?>