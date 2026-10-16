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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing all the posts of the user, in chronological order.
 */
?>
		<div class="cat_bar<?= !isset(Utils::$context['attachments']) ? ' cat_bar_round' : '' ?>">
			<h3 class="catbg">
				<?= Lang::getTxt(
		!isset(Utils::$context['attachments']) && empty(Utils::$context['is_topics'])
					? 'showMessages'
					: (
						!empty(Utils::$context['is_topics'])
						? 'showTopics'
						: 'showAttachments'
					),
		file: 'Profile',
	) ?><?= !Profile::$member->is_me ? ' - ' . Utils::$context['member']['name'] : '' ?>
			</h3>
		</div><?= !empty(Utils::$context['page_index']) ? '
		<div class="pagesection">
			<div class="pagelinks">' . Utils::$context['page_index'] . '</div>
		</div>' : '' ?><?php /* Are we displaying posts or attachments? */ ?><?php if (!isset(Utils::$context['attachments'])): ?><?php /* For every post to be displayed, give it its own div, and show the important details of the post. */ ?><?php foreach (Utils::$context['posts'] as $post): ?>
		<div class="<?= $post['css_class'] ?>">
			<div class="page_number floatright"> #<?= $post['counter'] ?></div>
			<div class="topic_details">
				<h4>
					<strong><a href="<?= Config::$scripturl ?>?board=<?= $post['board']['id'] ?>.0"><?= $post['board']['name'] ?></a> / <a href="<?= Config::$scripturl ?>?topic=<?= $post['topic'] ?>.<?= $post['start'] ?>#msg<?= $post['id'] ?>"><?= $post['subject'] ?></a></strong>
				</h4>
				<span class="smalltext"><?= $post['time'] ?></span>
			</div><?php if (!$post['approved']): ?>
			<div class="noticebox">
				<?= Lang::getTxt('post_awaiting_approval', file: 'General') ?>
			</div><?php endif; ?>
			<div class="post">
				<div class="inner">
					<?= Utils::adjustHeadingLevels($post['body'], 4) ?>
				</div>
			</div><!-- .post --><?php /* Post options */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $post['quickbuttons'], 'list_class' => 'profile_showposts']); ?>
		</div><!-- .<?= $post['css_class'] ?> --><?php endforeach; ?><?php else: ?><?php $this->subTemplate('show_list', ['list_id' => 'attachments']); ?><?php endif; ?><?php /* No posts? Just end with an informative message. */ ?><?php if ((isset(Utils::$context['attachments']) && empty(Utils::$context['attachments'])) || (!isset(Utils::$context['attachments']) && empty(Utils::$context['posts']))): ?>
		<div class="windowbg">
			<?= Lang::getTxt(
			isset(Utils::$context['attachments'])
				? 'show_attachments_none'
				: (
					Utils::$context['is_topics']
					? 'show_topics_none'
					: 'show_posts_none'
				),
			file: 'Profile',
		) ?>
		</div><?php endif; ?><?php /* Show more page numbers. */ ?><?php if (!empty(Utils::$context['page_index'])): ?>
		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div><?php endif; ?>
