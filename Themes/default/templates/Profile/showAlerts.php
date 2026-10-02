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
use SMF\Profile;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing all alerts
 */
?><?php /* Do we have an update message? */ ?><?php if (!empty(Utils::$context['update_message'])): ?>
		<div class="infobox">
			<?= Utils::$context['update_message'] ?>
		</div><?php endif; ?>
		<div class="cat_bar">
			<h3 class="catbg">
			<?= Lang::getTxt(!Profile::$member->is_me ? 'alerts_member' : 'alerts', ['member' => Utils::$context['member']['name']], file: 'General') ?>
			</h3>
		</div><?php if (empty(Utils::$context['alerts'])): ?>
		<div class="information">
			<?= Lang::getTxt('alerts_none', file: 'Alerts') ?>
		</div><?php else: ?><?php /* Start the form if checkboxes are in use */ ?><?php if (Utils::$context['showCheckboxes']): ?>
		<form action="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=showalerts;save" method="post" accept-charset="UTF-8" id="mark_all"><?php endif; ?>
			<table id="alerts" class="table_grid"><?php foreach (Utils::$context['alerts'] as $id => $alert): ?>
				<tr class="windowbg">
					<td class="alert_image">
						<div>
							<?= empty($alert['sender']['avatar']['image']) ? '' : $alert['sender']['avatar']['image'] . '
							' ?><?= $alert['icon'] ?>
						</div>
					</td>
					<td class="alert_text">
						<div><?= $alert['text'] ?></div>
						<time class="alert_inline_time" datetime="<?= $alert['alert_time'] ?>"><?= $alert['time'] ?></time>
					</td>
					<td class="alert_time">
						<time datetime="<?= $alert['alert_time'] ?>"><?= $alert['time'] ?></time>
					</td>
					<td class="alert_buttons"><?php /* Alert options */ ?><?php $this->subTemplate('quickbuttons', ['list_items' => $alert['quickbuttons'], 'list_class' => 'profile_alerts']); ?>
					</td>
				</tr><?php endforeach; ?>
			</table>
			<div class="pagesection">
				<div class="pagelinks"><?= Utils::$context['pagination'] ?></div>
				<div class="floatright"><?php if (Utils::$context['showCheckboxes']): ?>
					<?= Lang::getTxt('check_all', file: 'General') ?> <input type="checkbox" name="select_all" id="select_all">
					<select name="mark_as">
						<option value="read"><?= Lang::getTxt('quick_mod_markread', file: 'General') ?></option>
						<option value="unread"><?= Lang::getTxt('quick_mod_markunread', file: 'General') ?></option>
						<option value="remove"><?= Lang::getTxt('quick_mod_remove', file: 'General') ?></option>
					</select>
					<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
					<input type="hidden" name="start" value="<?= Utils::$context['start'] ?>">
					<input type="submit" name="req" value="<?= Lang::getTxt('quick_mod_go', file: 'General') ?>" class="button you_sure"><?php endif; ?>
					<a href="<?= Utils::$context['alert_purge_link'] ?>" class="button you_sure"><?= Lang::getTxt('alert_purge', file: 'Profile') ?></a>
				</div>
			</div><?php if (Utils::$context['showCheckboxes']): ?>
		</form><?php endif; ?><?php endif; ?>
