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
use SMF\Topic;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * The main page. This shows the relevant info in a printer-friendly format
 */
?><?php if (!empty(Utils::$context['poll'])): ?>
			<div id="poll_data">
				<span><?= Lang::getTxt('poll', file: 'General') ?></span>
				<div class="question">
					<?= Lang::getTxt('poll_question', file: 'General') ?>: <strong><?= Utils::$context['poll']['question'] ?></strong>
				</div><?php $options = 1; ?><?php foreach (Utils::$context['poll']['options'] as $option): ?>
					<div>
						<?= Lang::getTxt('option_number', [$options++], file: 'Post') ?>: <strong><?= $option['option'] ?></strong>
						<?= Utils::$context['allow_results_view'] ? Lang::getTxt('number_of_votes', [$option['votes']], file: 'Post') : '' ?>
					</div><?php endforeach; ?>
			</div><?php endif; ?><?php foreach (Utils::$context['posts'] as $post): ?>
			<div class="postheader">
				<div><strong><?= $post['subject'] ?></strong></div>
				<div><?= Lang::getTxt('posted_by_member_time', $post, file: 'General') ?></div>
			</div>
			<div class="postbody">
				<?= Utils::adjustHeadingLevels($post['body'], 2) ?><?php /* Show attachment images */ ?><?php if (isset($_GET['images']) && !empty(Utils::$context['printattach'][$post['id_msg']])): ?>
				<hr><?php foreach (Utils::$context['printattach'][$post['id_msg']] as $attach): ?>
					<img width="<?= $attach['width'] ?>" height="<?= $attach['height'] ?>" src="<?= Config::$scripturl ?>?action=dlattach;topic=<?= Topic::$topic_id ?>.0;attach=<?= $attach['id_attach'] ?>" alt=""><?php endforeach; ?><?php endif; ?>
			</div><!-- .postbody --><?php endforeach; ?>
