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
 * Template for showing all of a user's PM drafts.
 */
?>

		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons inbox"></span> <?= Lang::getTxt('drafts_show', file: 'Drafts') ?>

			</h3>
		</div>
		<p class="information">
			<?= Lang::getTxt('drafts_show_desc', file: 'Drafts') ?>

		</p><?php /* No drafts? Just show an informative message. */ ?><?php if (empty(Utils::$context['drafts'])): ?>

		<div class="windowbg centertext">
			<?= Lang::getTxt('draft_none', file: 'Drafts') ?>

		</div><?php else: ?>

		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div><?php /* For every draft to be displayed, give it its own div, and show the important details of the draft. */ ?><?php foreach (Utils::$context['drafts'] as $draft): ?>

		<div class="windowbg">
			<div class="page_number floatright"> #<?= $draft['counter'] ?></div>
			<div class="topic_details">
				<h4>
					<strong><?= $draft['subject'] ?></strong>
				</h4>
				<div class="smalltext">
					<div class="recipient_to"><?= Lang::getTxt('pm_to', ['list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', $draft['recipients']['to'])]) ?></div><?php if (!empty($draft['recipients']['bcc'])): ?>

					<div class="pm_bbc"><?= Lang::getTxt('pm_bcc', ['list' => implode(Lang::getTxt('sentence_list_separator', file: 'General') . ' ', $draft['recipients']['bcc'])]) ?></div><?php endif; ?>

				</div>
				<div class="smalltext">
					<?= Lang::getTxt('draft_last_saved', ['age' => $draft['age'], 'remaining' => $draft['remaining']], file: 'Drafts') ?>

				</div>
			</div>
			<div class="list_posts">
				<?= Utils::adjustHeadingLevels($draft['body'], 4) ?>

			</div><?php /* Draft buttons */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $draft['quickbuttons'], 'list_class' => 'pm_drafts']); ?>

		</div><!-- .windowbg --><?php endforeach; ?><?php /* Show page numbers. */ ?>

		<div class="pagesection">
			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div><?php endif; ?>
