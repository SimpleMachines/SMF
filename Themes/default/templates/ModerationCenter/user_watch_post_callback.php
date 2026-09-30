<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 5-dev
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Callback function for showing a watched users post in the table.
 *
 * @param array $post An array of data about the post.
 * @return string An array of HTML for showing the post info.
 */
?><?php
// We'll have a delete and a checkbox please bob.
// @todo Discuss this with the team and rewrite if required.
$quickbuttons = [
	'delete' => [
		'label' => Lang::getTxt('remove_message', file: 'General'),
		'href' => Config::$scripturl . '?action=moderate;area=userwatch;sa=post;delete=' . $post['id'] . ';start=' . Utils::$context['start'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
		'javascript' => 'data-confirm="' . Lang::getTxt('mc_watched_users_delete_post', file: 'ModerationCenter') . '"',
		'class' => 'you_sure',
		'icon' => 'remove_button',
		'show' => $post['can_delete'],
	],
	'quickmod' => [
		'class' => 'inline_mod_check',
		'content' => '<input type="checkbox" name="delete[]" value="' . $post['id'] . '">',
		'show' => $post['can_delete'],
	],
];
$output_html = '
					<div>
						<div class="floatleft">
							' . Lang::getTxt('mc_post_report', ['report_link' => '<a href="' . Config::$scripturl . '?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a>', 'author_link' => $post['author_link']], file: 'ModerationCenter') . '
						</div>
					</div>
					<br>
					<div class="smalltext">
						' . Lang::getTxt('mc_watched_users_posted', ['time' => $post['poster_time']], file: 'ModerationCenter') . '
					</div>
					<div class="list_posts">
						' . $post['body'] . '
					</div>';
?><?php $output_html .= $this->fetchSubTemplate('quickbuttons', ['list_items' => $quickbuttons, 'list_class' => 'user_watch_post', 'output_method' => 'return']); ?><?php echo $output_html; ?>
