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
 * The stuff shown immediately below the main content, including the footer
 */
?>

			</main><!-- #main_content_section -->
		</div><!-- #content_section -->
	</div><!-- #wrapper -->
</div><!-- #footerfix -->
<?php /* Show the footer with copyright, terms and help links. */ ?>
	<footer id="footer">
		<div class="content_wrapper">
<?php
// The copyright at one end, the help links and the "Go to top" link at the
// other. They used to be separated by literal pipes; the gap does that now.
?>
		<ul>
			<li class="copyright"><?= Theme::copyright() ?></li>
			<li class="helplinks">
				<a href="<?= Config::$scripturl ?>?action=help"><?= Lang::getTxt('help', file: 'General') ?></a><?= (!empty(Config::$modSettings['requireAgreement'])) ? '
				<a href="' . Config::$scripturl . '?action=agreement">' . Lang::getTxt('terms_and_rules', file: 'General') . '</a>' : '' ?>

				<a href="#top_section"><?= Lang::getTxt('go_up', file: 'General') ?> &#9650;</a>
			</li>
		</ul>
<?php /* Show the load time? */ ?>
<?php if (Utils::$context['show_load_time']): ?>
		<p><?= Lang::getTxt(
			'page_created_full',
			[
				Utils::$context['load_time'],
				Utils::$context['load_queries'],
			],
			file: 'General',
		) ?></p>
<?php endif; ?>
		</div>
	</footer><!-- #footer -->