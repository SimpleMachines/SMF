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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for user statistics, showing graphs and the like.
 */
?>

<?php /* First, show a few text statistics such as post/topic count. */ ?>
	<div id="profileview" class="roundframe noup">
		<div id="generalstats">
			<dl class="stats">
<?php foreach (Utils::$context['text_stats'] as $key => $stat): ?>
				<dt><?= Lang::getTxt('statPanel_' . $key, file: 'Profile') ?></dt>
<?php if (!empty($stat['url'])): ?>
				<dd><a href="<?= $stat['url'] ?>"><?= $stat['text'] ?></a></dd>
<?php else: ?>
				<dd><?= $stat['text'] ?></dd>
<?php endif; ?>
<?php endforeach; ?>
			</dl>
		</div>
<?php /* This next section draws a graph showing what times of day they post the most. */ ?>
		<div id="activitytime" class="flow_hidden">
			<div class="title_bar">
				<h3 class="titlebg">
					<span class="main_icons history"></span> <?= Lang::getTxt('statPanel_activityTime', file: 'Profile') ?>
				</h3>
			</div>
<?php /* If they haven't post at all, don't draw the graph. */ ?>
<?php if (empty(Utils::$context['posts_by_time'])): ?>
			<p class="centertext padding"><?= Lang::getTxt('statPanel_noPosts', file: 'Profile') ?></p>
<?php /* Otherwise do! */ ?>
<?php else: ?>
			<ul class="activity_stats flow_hidden">
<?php /* The labels. */ ?>
<?php foreach (Utils::$context['posts_by_time'] as $time_of_day): ?>
				<li>
					<div class="generic_bar vertical">
						<div class="bar" style="height: <?= (int) $time_of_day['relative_percent'] ?>%;">
							<span><?= Lang::getTxt('statPanel_activityTime_posts', [$time_of_day['posts'], $time_of_day['posts_percent'] / 100], file: 'Profile') ?></span>
						</div>
					</div>
					<span class="stats_hour"><?= $time_of_day['hour_format'] ?></span>
				</li>
<?php endforeach; ?>
			</ul>
<?php endif; ?>
		</div><!-- #activitytime -->
<?php /* Two columns with the most popular boards by posts and activity (activity = users posts / total posts). */ ?>
		<div class="flow_hidden">
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="main_icons replies"></span> <?= Lang::getTxt('statPanel_topBoards', file: 'Profile') ?>
					</h3>
				</div>
<?php if (empty(Utils::$context['popular_boards'])): ?>
				<p class="centertext padding"><?= Lang::getTxt('statPanel_noPosts', file: 'Profile') ?></p>
<?php else: ?>
				<dl class="stats">
<?php /* Draw a bar for every board. */ ?>
<?php foreach (Utils::$context['popular_boards'] as $board): ?>
					<dt><?= $board['link'] ?></dt>
					<dd>
						<div class="profile_pie" style="background-position: -<?= ((int) ($board['posts_percent'] / 5) * 20) ?>px 0;" title="<?= Lang::getTxt('statPanel_topBoards_memberposts', [$board['posts'], $board['total_posts_member'], $board['posts_percent'] / 100], file: 'Profile') ?>">
							<?= Lang::getTxt('statPanel_topBoards_memberposts', [$board['posts'], $board['total_posts_member'], $board['posts_percent'] / 100], file: 'Profile') ?>
						</div>
						<?= empty(Utils::$context['hide_num_posts']) ? $board['posts'] : '' ?>
					</dd>
<?php endforeach; ?>
				</dl>
<?php endif; ?>
			</div><!-- .half_content -->
			<div class="half_content">
				<div class="title_bar">
					<h3 class="titlebg">
						<span class="main_icons replies"></span> <?= Lang::getTxt('statPanel_topBoardsActivity', file: 'Profile') ?>
					</h3>
				</div>
<?php if (empty(Utils::$context['board_activity'])): ?>
				<p class="centertext padding"><?= Lang::getTxt('statPanel_noPosts', file: 'Profile') ?></p>
<?php else: ?>
				<dl class="stats">
<?php /* Draw a bar for every board. */ ?>
<?php foreach (Utils::$context['board_activity'] as $activity): ?>
					<dt><?= $activity['link'] ?></dt>
					<dd>
						<div class="profile_pie" style="background-position: -<?= ((int) ($activity['posts_percent'] / 5) * 20) ?>px 0;" title="<?= Lang::getTxt('statPanel_topBoards_posts', [$activity['posts'], $activity['total_posts'], $activity['posts_percent'] / 100], file: 'Profile') ?>">
							<?= Lang::getTxt('statPanel_topBoards_posts', [$activity['posts'], $activity['total_posts'], $activity['posts_percent'] / 100], file: 'Profile') ?>
						</div>
						<?= Lang::formatText('{0, number, percent}', [$activity['percent'] / 100]) ?>
					</dd>
<?php endforeach; ?>
				</dl>
<?php endif; ?>
			</div><!-- .half_content -->
		</div><!-- .flow_hidden -->
	</div><!-- #profileview -->