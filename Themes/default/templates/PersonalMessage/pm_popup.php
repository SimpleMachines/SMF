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
 * Displays a popup with information about your personal messages
 */
?>

<?php /* Unlike almost every other template, this is designed to be included into the HTML directly via $().load() */ ?>
		<div class="pm_bar">
			<div class="pm_sending block">
				<?= Utils::$context['can_send_pm'] ? '<a href="' . Config::$scripturl . '?action=pm;sa=send">' . Lang::getTxt('pm_new_short', file: 'PersonalMessage') . '</a>' : '' ?>
				<?= Utils::$context['can_draft'] ? ' | <a href="' . Config::$scripturl . '?action=pm;f=drafts">' . Lang::getTxt('pm_drafts_short', file: 'PersonalMessage') . '</a>' : '' ?>
				<a href="<?= Config::$scripturl ?>?action=pm;sa=settings" class="floatright"><?= Lang::getTxt('pm_settings_short', file: 'PersonalMessage') ?></a>
			</div>
			<div class="pm_mailbox centertext">
				<a href="<?= Config::$scripturl ?>?action=pm;f=inbox" class="button"><?= Lang::getTxt('inbox', file: 'PersonalMessage') ?></a>
				<a href="<?= Config::$scripturl ?>?action=pm;f=sent" class="button"><?= Lang::getTxt('sent_items', file: 'PersonalMessage') ?></a>
			</div>
		</div>
		<div class="pm_unread">
<?php if (empty(Utils::$context['unread_pms'])): ?>
			<div class="no_unread"><?= Lang::getTxt('pm_no_unread', file: 'PersonalMessage') ?></div>
<?php else: ?>
<?php foreach (Utils::$context['unread_pms'] as $id_pm => $pm_details): ?>
			<div class="unread_notify">
				<div class="unread_notify_image">
					<?= !empty($pm_details['member']) ? $pm_details['member']['avatar']['image'] : '' ?>
				</div>
				<div class="details">
					<div class="subject"><?= $pm_details['pm_link'] ?></div>
					<div class="sender">
						<?= $pm_details['replied_to_you'] ? '<span class="main_icons replied centericon" style="margin-right: 4px" title="' . Lang::getTxt('pm_you_were_replied_to', file: 'PersonalMessage') . '"></span>' : '<span class="main_icons im_off centericon" style="margin-right: 4px" title="' . Lang::getTxt('pm_was_sent_to_you', file: 'PersonalMessage') . '"></span>' ?><?= !empty($pm_details['member']) ? $pm_details['member']['link'] : $pm_details['member_from'] ?> - <?= $pm_details['time'] ?>
					</div>
				</div>
			</div>
<?php endforeach; ?>
<?php endif; ?>
		</div><!-- #pm_unread -->