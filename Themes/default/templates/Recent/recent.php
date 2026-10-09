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
 * Template for showing recent posts
 */
?>
	<div id="recent" class="main_section">
		<div id="display_head" class="information">
			<h2 class="display_title">
				<span id="top_subject"><?= Lang::getTxt('recent_posts', file: 'General') ?></span>
			</h2>
		</div><?php if (!empty(Utils::$context['page_index'])): ?>
		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div><?php endif; ?><?php if (empty(Utils::$context['posts'])): ?>
		<div class="windowbg"><?= Lang::getTxt('no_messages', file: 'General') ?></div><?php endif; ?><?php foreach (Utils::$context['posts'] as $post): ?>
		<div class="<?= $post['css_class'] ?>">
			<div class="page_number floatright"> #<?= $post['counter'] ?></div>
			<div class="topic_details">
				<h5><?= $post['board']['link'] ?> / <?= $post['link'] ?></h5>
				<span class="smalltext"><?= Lang::getTxt('last_post_member_date', ['member' => $post['poster']['link'], 'relative' => str_contains($post['time'], Lang::getTxt('today', file: 'General')) ? 'today' : (str_contains($post['time'], Lang::getTxt('yesterday', file: 'General')) ? 'yesterday' : 'other'), 'date' => $post['time']]) ?></span>
			</div>
			<div class="list_posts"><?= $post['body'] ?></div><?php /* Post options */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $post['quickbuttons'], 'list_class' => 'recent']); ?>
		</div><!-- $post[css_class] --><?php endforeach; ?>
		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div>
	</div><!-- #recent -->