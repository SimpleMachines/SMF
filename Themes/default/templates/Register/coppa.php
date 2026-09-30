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
 * Template for giving instructions about COPPA activation.
 */
?>

<?php /* Formulate a nice complicated message! */ ?>
			<div class="title_bar">
				<h3 class="titlebg"><?= Utils::$context['page_title'] ?></h3>
			</div>
			<div id="coppa" class="roundframe noup">
				<p><?= Utils::$context['coppa']['body'] ?></p>
				<p>
					<span><a href="<?= Config::$scripturl ?>?action=coppa;form;member=<?= Utils::$context['coppa']['id'] ?>" target="_blank" rel="noopener"><?= Lang::getTxt('coppa_form_link_popup', file: 'Login') ?></a> | <a href="<?= Config::$scripturl ?>?action=coppa;form;dl;member=<?= Utils::$context['coppa']['id'] ?>"><?= Lang::getTxt('coppa_form_link_download', file: 'Login') ?></a></span>
				</p>
				<p><?= Lang::getTxt(Utils::$context['coppa']['many_options'] ? 'coppa_send_to_two_options' : 'coppa_send_to_one_option', file: 'Login') ?></p>
<?php /* Can they send by post? */ ?>
<?php if (!empty(Utils::$context['coppa']['post'])): ?>
				<h4>1) <?= Lang::getTxt('coppa_send_by_post', file: 'Login') ?></h4>
				<div class="coppa_contact">
					<?= Utils::$context['coppa']['post'] ?>

				</div>
<?php endif; ?>
<?php /* Can they send by fax?? */ ?>
<?php if (!empty(Utils::$context['coppa']['fax'])): ?>
				<h4><?= !empty(Utils::$context['coppa']['post']) ? '2' : '1' ?>) <?= Lang::getTxt('coppa_send_by_fax', file: 'Login') ?></h4>
				<div class="coppa_contact">
					<?= Utils::$context['coppa']['fax'] ?>

				</div>
<?php endif; ?>
<?php /* Offer an alternative Phone Number? */ ?>
<?php if (Utils::$context['coppa']['phone']): ?>
				<p><?= Utils::$context['coppa']['phone'] ?></p>
<?php endif; ?>
			</div><!-- #coppa -->