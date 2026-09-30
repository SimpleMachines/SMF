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
 * Little section for making... notes.
 */
?><?php /* Let them know the action was a success. */ ?><?php if (!empty(Utils::$context['report_post_action'])): ?>

		<div class="infobox">
			<?= Lang::getTxt('report_action_' . Utils::$context['report_post_action'], file: 'ModerationCenter') ?>

		</div><?php endif; ?>

		<div id="modnotes">
			<form action="<?= Config::$scripturl ?>?action=moderate;area=index;modnote" method="post">
				<div class="cat_bar">
					<h3 class="catbg"><?= Lang::getTxt('mc_notes', file: 'ModerationCenter') ?></h3>
				</div>
				<div class="windowbg"><?php if (!empty(Utils::$context['notes'])): ?>

					<ul class="moderation_notes"><?php /* Cycle through the notes. */ ?><?php foreach (Utils::$context['notes'] as $note): ?>

						<li class="smalltext">
							<?= ($note['can_delete'] ? '<a href="' . $note['delete_href'] . ';' . Utils::$context['mod-modnote-del_token_var'] . '=' . Utils::$context['mod-modnote-del_token'] . '" data-confirm="' . Lang::getTxt('mc_reportedp_delete_confirm', file: 'ModerationCenter') . '" class="you_sure"><span class="main_icons delete"></span></a>' : '') ?><?= $note['time'] ?> <strong><?= $note['author']['link'] ?>:</strong> <?= Utils::adjustHeadingLevels($note['text'], 4) ?>

						</li><?php endforeach; ?>

					</ul>
					<div class="pagesection notes">
						<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
					</div><?php endif; ?>

					<div class="floatleft post_note">
						<input type="text" name="new_note" placeholder="<?= Lang::getTxt('mc_click_add_note', file: 'ModerationCenter') ?>">
					</div>
					<input type="hidden" name="<?= Utils::$context['mod-modnote-add_token_var'] ?>" value="<?= Utils::$context['mod-modnote-add_token'] ?>">
					<input type="submit" name="makenote" value="<?= Lang::getTxt('mc_add_note', file: 'ModerationCenter') ?>" class="button">
				</div><!-- .windowbg -->
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
			</form>
		</div><!-- #modnotes -->