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
 * Displays all reported posts.
 */
?><?php /* Let them know the action was a success. */ ?><?php if (!empty(Utils::$context['report_post_action'])): ?>

	<div class="infobox">
		<?= Lang::getTxt('report_action_' . Utils::$context['report_post_action'], file: 'ModerationCenter') ?>

	</div><?php endif; ?>

	<form id="reported_posts" action="<?= Config::$scripturl ?>?action=moderate;area=reportedposts;sa=show<?= Utils::$context['view_closed'] ? ';closed' : '' ?>;start=<?= Utils::$context['start'] ?>" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Lang::getTxt(Utils::$context['view_closed'] ? 'mc_reportedp_closed' : 'mc_reportedp_active', file: 'ModerationCenter') ?>

			</h3>
		</div>
		<div class="pagesection"><?php if (!empty(Utils::$context['reports']) && !Utils::$context['view_closed'] && !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1): ?>

			<ul class="buttonlist floatright">
				<li class="inline_mod_check">
					<input type="checkbox" onclick="invertAll(this, this.form, 'close[]');">
				</li>
			</ul><?php endif; ?>

			<div class="pagelinks floatleft"><?= Utils::$context['page_index'] ?></div>
		</div><?php foreach (Utils::$context['reports'] as $report): ?>

		<div class="windowbg">
			<h5>
				<?= !empty($report['topic']['board_name']) ? '<a href="' . Config::$scripturl . '?board=' . $report['topic']['id_board'] . '.0">' . $report['topic']['board_name'] . '</a>' : '??' ?> / <?= Lang::getTxt('mc_reportedp_subject_author', ['subject' => '<a href="' . $report['topic']['href'] . '">' . $report['subject'] . '</a>', 'author' => $report['author']['link']], file: 'ModerationCenter') ?>

			</h5>
			<div class="smalltext">
				<?= Lang::getTxt('mc_reportedp_last_reported', ['date' => $report['last_updated']], file: 'ModerationCenter') ?><br><?php
// Prepare the comments...
$comments = [];
?><?php foreach ($report['comments'] as $comment): ?><?php $comments[$comment['member']['id']] = $comment['member']['link']; ?><?php endforeach; ?>

				<?= Lang::getTxt('mc_reportedp_reported_by', ['list' => Lang::sentenceList($comments)], file: 'ModerationCenter') ?>

			</div>
			<hr>
			<?= Utils::adjustHeadingLevels($report['body'], 5) ?>

			<br><?php /* Reported post options */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $report['quickbuttons'], 'list_class' => 'reported_posts']); ?>

		</div><!-- .windowbg --><?php endforeach; ?><?php /* Were none found? */ ?><?php if (empty(Utils::$context['reports'])): ?>

		<div class="windowbg">
			<div class="centertext"><?= Lang::getTxt('mc_reportedp_none_found', file: 'ModerationCenter') ?></div>
		</div><?php endif; ?>

		<div class="pagesection">
			<div class="pagelinks floatleft"><?= Utils::$context['page_index'] ?></div><?php if (!empty(Utils::$context['reports']) && !Utils::$context['view_closed'] && !empty(Theme::$current->options['display_quick_mod']) && Theme::$current->options['display_quick_mod'] == 1): ?>

			<div class="floatright">
				<input type="hidden" name="<?= Utils::$context['mod-report-close-all_token_var'] ?>" value="<?= Utils::$context['mod-report-close-all_token'] ?>">
				<input type="submit" name="close_selected" value="<?= Lang::getTxt('mc_reportedp_close_selected', file: 'ModerationCenter') ?>" class="button">
			</div><?php endif; ?>

		</div>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>