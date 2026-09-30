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
 * Lists all reported members
 */
?><?php /* Let them know the action was a success. */ ?><?php if (!empty(Utils::$context['report_post_action']) && Lang::txtExists('report_action_' . Utils::$context['report_post_action'], file: 'ModerationCenter')): ?>

	<div class="infobox">
		<?= Lang::getTxt('report_action_' . Utils::$context['report_post_action'], file: 'ModerationCenter') ?>

	</div><?php endif; ?>

	<form id="reported_members" action="<?= Config::$scripturl ?>?action=moderate;area=reportedmembers;sa=show<?= Utils::$context['view_closed'] ? ';closed' : '' ?>;start=<?= Utils::$context['start'] ?>" method="post" accept-charset="UTF-8">
		<div class="cat_bar cat_bar_round">
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

			<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		</div><?php foreach (Utils::$context['reports'] as $report): ?>

		<div class="generic_list_wrapper windowbg">
			<h5>
				<strong><a href="<?= $report['user']['href'] ?>"><?= $report['user']['name'] ?></a></strong>
			</h5>
			<div class="smalltext">
				<?= Lang::getTxt('mc_reportedp_last_reported', ['date' => $report['last_updated']], file: 'ModerationCenter') ?><br><?php
// Prepare the comments...
$comments = [];
?><?php foreach ($report['comments'] as $comment): ?><?php $comments[$comment['member']['id']] = $comment['member']['link']; ?><?php endforeach; ?>

				<?= Lang::getTxt('mc_reportedp_reported_by', ['list' => Lang::sentenceList($comments)], file: 'ModerationCenter') ?>

			</div>
			<hr>
			<?php $this->subTemplate('quickbuttons', ['list_items' => $report['quickbuttons'], 'list_class' => 'reported_members']); ?>

		</div><!-- .generic_list_wrapper --><?php endforeach; ?><?php /* Were none found? */ ?><?php if (empty(Utils::$context['reports'])): ?>

		<div class="windowbg">
			<div class="centertext"><?= Lang::getTxt('mc_reportedp_none_found', file: 'ModerationCenter') ?></div>
		</div><?php endif; ?>

		<div class="pagesection">
			<div class="pagelinks floatleft"><?= Utils::$context['page_index'] ?></div>
			<div class="floatright">
				<?= (!Utils::$context['view_closed'] && !empty(Utils::$context['reports'])) ? '<input type="submit" name="close_selected" value="' . Lang::getTxt('mc_reportedp_close_selected', file: 'ModerationCenter') . '" class="button">' : '' ?>

			</div>
		</div>
		<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
	</form>