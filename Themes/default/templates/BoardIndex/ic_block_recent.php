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
use SMF\Theme;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The recent posts section of the info center
 */
?>

<?php /* This is the "Recent Posts" bar. */ ?>
			<div class="sub_bar">
				<h4 class="subbg">
					<a href="<?= Config::$scripturl ?>?action=recent"><span class="main_icons recent_posts"></span> <?= Lang::getTxt('recent_posts', file: 'General') ?></a>
				</h4>
			</div>
			<div id="recent_posts_content">
<?php /* Only show one post. */ ?>
<?php if (Theme::$current->settings['number_recent_posts'] == 1): ?>
<?php /* latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.) */ ?>
				<p id="infocenter_onepost" class="inline">
					<a href="<?= Config::$scripturl ?>?action=recent"><?= Lang::getTxt('recent_view', file: 'General') ?></a> <?= Lang::getTxt('is_recent_updated', ['link' => '&quot;' . Utils::$context['latest_post']['link'] . '&quot;'], file: 'General') ?> (<?= Utils::$context['latest_post']['time'] ?>)<br>
				</p>
<?php /* Show lots of posts. */ ?>
<?php elseif (!empty(Utils::$context['latest_posts'])): ?>
				<table id="ic_recentposts">
					<tr class="windowbg">
						<th class="recentpost"><?= Lang::getTxt('message', file: 'General') ?></th>
						<th class="recentposter"><?= Lang::getTxt('author', file: 'General') ?></th>
						<th class="recentboard"><?= Lang::getTxt('board', file: 'General') ?></th>
						<th class="recenttime"><?= Lang::getTxt('date', file: 'General') ?></th>
					</tr>
<?php
/* Each post in latest_posts has:
			board (with an id, name, and link.), topic (the topic's id.), member (with id, name, and link.),
			subject, short_subject (shortened with...), time, link, and href. */
?>
<?php foreach (Utils::$context['latest_posts'] as $post): ?>
					<tr class="windowbg">
						<td class="recentpost"><strong><?= $post['link'] ?></strong></td>
						<td class="recentposter"><?= $post['member']['link'] ?></td>
						<td class="recentboard"><?= $post['board']['link'] ?></td>
						<td class="recenttime"><?= $post['time'] ?></td>
					</tr>
<?php endforeach; ?>
				</table>
<?php endif; ?>
			</div><!-- #recent_posts_content -->