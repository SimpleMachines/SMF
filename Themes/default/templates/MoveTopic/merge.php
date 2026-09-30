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
 * Merge topic page.
 */
?>

		<div id="merge_topics">
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('merge', file: 'General') ?></h3>
			</div>
			<div class="information">
				<?= Lang::getTxt('merge_desc', file: 'General') ?>

			</div>
			<div class="windowbg">
				<dl class="settings merge_topic">
					<dt>
						<strong><?= Lang::getTxt('topic_to_merge', file: 'General') ?></strong>
					</dt>
					<dd>
						<?= Utils::$context['origin_subject'] ?>

					</dd>
				</dl>
			</div>
			<br>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('target_topic', file: 'General') ?></h3>
			</div>
			<div class="title_bar">
				<h4 class="titlebg"><?php if (isset(Utils::$context['merge_categories'])): ?>

					<form action="<?= Config::$scripturl ?>?action=mergetopics;from=<?= Utils::$context['origin_topic'] ?>;targetboard=<?= Utils::$context['target_board'] ?>;board=<?= Utils::$context['current_board'] ?>.0" method="post" accept-charset="UTF-8" id="mergeSelectBoard">
						<?= Lang::getTxt('target_below', file: 'General') ?> (<?= Lang::getTxt('board', file: 'General') ?>:
						<select name="targetboard" onchange="this.form.submit();"><?php foreach (Utils::$context['merge_categories'] as $cat): ?>

							<optgroup label="<?= $cat['name'] ?>"><?php foreach ($cat['boards'] as $board): ?>

								<option value="<?= $board['id'] ?>"<?= $board['selected'] ? ' selected' : '' ?>><?= $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '' ?> <?= $board['name'] ?></option><?php endforeach; ?>

							</optgroup><?php endforeach; ?>

						</select>)
						<input type="hidden" name="from" value="<?= Utils::$context['origin_topic'] ?>">
						<input type="submit" value="<?= Lang::getTxt('go', file: 'General') ?>" class="button">
					</form><?php else: ?><?= Lang::getTxt('target_below', file: 'General') ?><?php endif; ?>		</h4>
			</div><!-- .title_bar -->
			<form action="<?= Config::$scripturl ?>?action=mergetopics;sa=options" method="post" accept-charset="UTF-8"><?php /* Don't show this if there aren't any topics... */ ?><?php if (!empty(Utils::$context['topics'])): ?>

				<div class="pagesection">
					<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
				</div>
				<div class="windowbg">
					<ul class="merge_topics"><?php foreach (Utils::$context['topics'] as $topic): ?>

						<li>
							<a href="<?= Config::$scripturl ?>?action=mergetopics;sa=options;board=<?= Utils::$context['current_board'] ?>.0;from=<?= Utils::$context['origin_topic'] ?>;to=<?= $topic['id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>"><span class="main_icons merge"></span></a>
							<a href="<?= Config::$scripturl ?>?topic=<?= $topic['id'] ?>.0" target="_blank" rel="noopener"><?= $topic['subject'] ?></a> <?= Lang::getTxt('started_by_member', ['member' => $topic['poster']['link']], file: 'General') ?>

						</li><?php endforeach; ?>

					</ul>
				</div>
				<div class="pagesection">
					<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
				</div><?php /* Just a nice "There aren't any topics" message */ ?><?php else: ?>

				<div class="windowbg"><?= Lang::getTxt('topic_alert_none', file: 'General') ?></div><?php endif; ?>

				<br>
				<div class="title_bar">
					<h4 class="titlebg"><?= Lang::getTxt('target_id', file: 'General') ?></h4>
				</div>
				<div class="windowbg">
					<dl class="settings merge_topic">
						<dt>
							<strong><?= Lang::getTxt('merge_to_topic_id', file: 'General') ?></strong>
						</dt>
						<dd>
							<input type="hidden" name="topics[]" value="<?= Utils::$context['origin_topic'] ?>">
							<input type="text" name="topics[]">
							<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">

						</dd>
					</dl>
					<input type="submit" value="<?= Lang::getTxt('merge', file: 'General') ?>" class="button">
				</div>
			</form>
		</div><!-- #merge_topics -->