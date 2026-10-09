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
 * The main help page
 */
?>
			<div class="cat_bar">
				<h3 class="catbg"><?= Lang::getTxt('manual_smf_user_help', file: 'Manual') ?></h3>
			</div>
			<div id="help_container">
				<div id="helpmain" class="windowbg">
					<p><?= Lang::getTxt('manual_welcome', ['forum_name' => Utils::$context['forum_name_html_safe']], file: 'Manual') ?></p>
					<p><?= Lang::getTxt('manual_introduction', file: 'Manual') ?></p>
					<ul>
<?php foreach (Utils::$context['manual_sections'] as $section_id => $wiki_id): ?>
						<li><a href="<?= Utils::$context['wiki_url'] ?>/<?= Utils::$context['wiki_prefix'] ?><?= $wiki_id ?><?= (Lang::getTxt('lang_dictionary', file: 'General') != 'en' ? '/' . Lang::getTxt('lang_dictionary', file: 'General') : '') ?>" target="_blank" rel="noopener"><?= Lang::getTxt('manual_section_' . $section_id . '_title', file: 'Manual') ?></a> - <?= Lang::getTxt('manual_section_' . $section_id . '_desc', file: 'Manual') ?></li>
<?php endforeach; ?>
					</ul>
					<p><?= Lang::getTxt('manual_docs_and_credits', ['wiki_url' => Utils::$context['wiki_url'], 'credits_url' => Config::$scripturl . '?action=credits'], file: 'Manual') ?></p>
				</div><!-- #helpmain -->
			</div><!-- #help_container -->