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
 * Template for showing all of a user's drafts
 */
?>
		<div class="cat_bar cat_bar_round">
			<h3 class="catbg">
				<?= !Profile::$member->is_me ? Lang::getTxt('drafts_member', ['member' => Utils::$context['member']['name']], file: 'Drafts') : Lang::getTxt('drafts', file: 'Drafts') ?>
			</h3>
		</div><?= !empty(Utils::$context['page_index']) ? '
		<div class="pagesection">
			<div class="pagelinks">' . Utils::$context['page_index'] . '</div>
		</div>' : '' ?><?php /* No drafts? Just show an informative message. */ ?><?php if (empty(Utils::$context['drafts'])): ?>
		<div class="windowbg centertext">
			<?= Lang::getTxt('draft_none', file: 'Drafts') ?>
		</div><?php else: ?><?php /* For every draft to be displayed, give it its own div, and show the important details of the draft. */ ?><?php foreach (Utils::$context['drafts'] as $draft): ?>
		<div class="windowbg">
			<div class="page_number floatright"> #<?= $draft['counter'] ?></div>
			<div class="topic_details">
				<h4>
					<strong><a href="<?= Config::$scripturl ?>?board=<?= $draft['board']['id'] ?>.0"><?= $draft['board']['name'] ?></a> / <?= $draft['topic']['link'] ?></strong> &nbsp; &nbsp;<?php if (!empty($draft['sticky'])): ?>
					<span class="main_icons sticky" title="<?= Lang::getTxt('sticky_topic', file: 'General') ?>"></span><?php endif; ?><?php if (!empty($draft['locked'])): ?>
					<span class="main_icons lock" title="<?= Lang::getTxt('locked_topic', file: 'General') ?>"></span><?php endif; ?>
				</h4>
				<span class="smalltext"><?= Lang::getTxt('draft_saved_on', ['date' => $draft['time']], file: 'Drafts') ?></span>
			</div><!-- .topic_details -->
			<div class="list_posts">
				<?= Utils::adjustHeadingLevels($draft['body'], 4) ?>
			</div>
			<div class="floatright"><?php /* Draft buttons */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $draft['quickbuttons'], 'list_class' => 'profile_drafts']); ?>
			</div><!-- .floatright -->
		</div><!-- .windowbg --><?php endforeach; ?><?php endif; ?><?php /* Show page numbers. */ ?>
		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div>