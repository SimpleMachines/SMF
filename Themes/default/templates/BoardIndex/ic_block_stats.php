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
 * The stats section of the info center
 */
?>

<?php /* Show statistical style information... */ ?>
			<div class="sub_bar">
				<h4 class="subbg">
					<?= Utils::$context['show_stats'] ? '<a href="' . Config::$scripturl . '?action=stats" title="' . Lang::getTxt('more_stats', file: 'General') . '">' : '' ?><span class="main_icons stats"></span> <?= Lang::getTxt('forum_stats', file: 'General') ?><?= Utils::$context['show_stats'] ? '</a>' : '' ?>
				</h4>
			</div>
			<p class="inline">
				<?= Utils::$context['common_stats']['boardindex_total_posts'] ?><?= !empty(Theme::$current->settings['show_latest_member']) ? ' - ' . Lang::getTxt('latest_member', file: 'General') . ': <strong> ' . Utils::$context['common_stats']['latest_member']['link'] . '</strong>' : '' ?><br>
				<?= (!empty(Utils::$context['latest_post']) ? Lang::getTxt('latest_post', file: 'General') . ': <strong>&quot;' . Utils::$context['latest_post']['link'] . '&quot;</strong>  (' . Utils::$context['latest_post']['time'] . ')<br>' : '') ?>
				<a href="<?= Config::$scripturl ?>?action=recent"><?= Lang::getTxt('recent_view', file: 'General') ?></a>
			</p>