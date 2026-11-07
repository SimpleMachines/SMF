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
 * The "popup" showing the user's alerts
 */
?><?php /* Unlike almost every other template, this is designed to be included into the HTML directly via $().load() */ ?>
		<div class="alert_bar">
			<div class="alerts_opts block">
				<a href="<?= Config::$scripturl ?>?action=profile;area=notification;sa=markread;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>" onclick="return markAlertsRead(this)"><?= Lang::getTxt('mark_alerts_read', file: 'Alerts') ?></a>
				<a href="<?= Config::$scripturl ?>?action=profile;area=notification;sa=alerts" class="floatright"><?= Lang::getTxt('alert_settings', file: 'Alerts') ?></a>
			</div>
			<div class="alerts_box centertext">
				<a href="<?= Config::$scripturl ?>?action=profile;area=showalerts" class="button"><?= Lang::getTxt('all_alerts', file: 'Alerts') ?></a>
			</div>
		</div>
		<div class="alerts_unread"><?php if (empty(Utils::$context['unread_alerts'])): ?><?php $this->subTemplate('alerts_all_read'); ?><?php else: ?><?php foreach (Utils::$context['unread_alerts'] as $id_alert => $details): ?>
			<<?= !$details['show_links'] ? 'a href="' . Config::$scripturl . '?action=profile;area=showalerts;alert=' . $id_alert . '" onclick="this.classList.add(\'alert_read\')"' : 'div' ?> class="unread_notify">
				<div class="unread_notify_image">
					<?= empty($details['sender']['avatar']['image']) ? '' : $details['sender']['avatar']['image'] . '
					' ?><?= $details['icon'] ?>
				</div>
				<div class="details">
					<span class="alert_text"><?= $details['text'] ?></span> - <span class="alert_time"><?= $details['time'] ?></span>
				</div>
			</<?= !$details['show_links'] ? 'a' : 'div' ?>><?php endforeach; ?><?php endif; ?>
		</div><!-- .alerts_unread -->
		<script>
			function markAlertsRead(obj) {
				ajax_indicator(true);
				$.get(
					obj.href,
					function(data) {
						ajax_indicator(false);
						$("#alerts_menu_top span.amt").remove();
						$("#alerts_menu div.alerts_unread").html(data);
						if (typeof localStorage != "undefined")
							localStorage.setItem("alertsCounter", 0);
					}
				);
				return false;
			}
		</script>