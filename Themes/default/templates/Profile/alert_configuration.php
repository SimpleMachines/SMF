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
 * The template for configuring alerts
 */
?>
		<div class="cat_bar">
			<h3 class="catbg">
				<?= Lang::getTxt('alert_prefs', file: 'Profile') ?>
			</h3>
		</div>
		<p class="information">
			<?= (empty(Utils::$context['description']) ? Lang::getTxt('alert_prefs_desc', file: 'Profile') : Utils::$context['description']) ?>
		</p>
		<form action="<?= Config::$scripturl ?>?<?= Utils::$context['action'] ?>" method="post" accept-charset="UTF-8" id="notify_options" class="flow_auto">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('notification_general', file: 'Profile') ?>
				</h3>
			</div>
			<div class="windowbg">
				<dl class="settings">
					<dt>
						<label for="notify_announcements"><?= Lang::getTxt('notify_important_email', file: 'Profile') ?></label><?= Utils::$context['id_member'] == 0 ? '
						<br>
						<span class="smalltext alert">' . Lang::getTxt('notify_announcements_desc', file: 'Admin') . '</span>' : '' ?>
					</dt>
					<dd>
						<input type="hidden" name="notify_announcements" value="0">
						<input type="checkbox" id="notify_announcements" name="notify_announcements" value="1"<?= !empty(Utils::$context['member']['notify_announcements']) ? ' checked' : '' ?>>
					</dd><?php if (!empty(Config::$modSettings['enable_ajax_alerts'])): ?>
					<dt>
						<label for="notify_alert_timeout"><?= Lang::getTxt('notify_alert_timeout', file: 'Profile') ?></label>
					</dt>
					<dd>
						<input type="number" size="4" id="notify_alert_timeout" name="opt_alert_timeout" min="0" max="127" value="<?= Utils::$context['member']['alert_timeout'] ?>">
					</dd><?php endif; ?>
				</dl>
			</div><!-- .windowbg -->
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt('notify_what_how', file: 'Profile') ?>
				</h3>
			</div>
			<table class="table_grid"><?php foreach (Utils::$context['alert_types'] as $alert_group => $alerts): ?>
				<tr class="title_bar">
					<th><?= Lang::getTxt('alert_group_' . $alert_group, file: 'Profile') ?></th>
					<th><?= Lang::getTxt('receive_alert', file: 'Profile') ?></th>
					<th><?= Lang::getTxt('receive_mail', file: 'Profile') ?></th>
				</tr>
				<tr class="windowbg"><?php if (isset(Utils::$context['alert_group_options'][$alert_group])): ?><?php foreach (Utils::$context['alert_group_options'][$alert_group] as $opts): ?><?php if ($opts[0] == 'hide'): ?><?php continue; ?><?php endif; ?>
				<tr class="windowbg">
					<td colspan="3"><?php
$label = Lang::getTxt('alert_opt_' . $opts[1], file: 'Profile');
$label_pos = $opts['label'] ?? '';
?><?php if ($label_pos == 'before'): ?>
						<label for="opt_<?= $opts[1] ?>"><?= $label ?></label><?php endif; ?><?php $this_value = Utils::$context['alert_prefs'][$opts[1]] ?? 0; ?><?php
switch ($opts[0]) {
					case 'check':
						echo '
						<input type="checkbox" name="opt_', $opts[1], '" id="opt_', $opts[1], '"', $this_value ? ' checked' : '', '>';
						break;

					case 'select':
						echo '
						<select name="opt_', $opts[1], '" id="opt_', $opts[1], '">';

						foreach ($opts['opts'] as $k => $v) {
							echo '
							<option value="', $k, '"', $this_value == $k ? ' selected' : '', '>', $v, '</option>';
						}
						echo '
						</select>';
						break;
				}
?><?php if ($label_pos == 'after'): ?>
						<label for="opt_<?= $opts[1] ?>"><?= $label ?></label><?php endif; ?>
					</td>
				</tr><?php endforeach; ?><?php endif; ?><?php foreach ($alerts as $alert_id => $alert_details): ?>
				<tr class="windowbg">
					<td>
						<?= Lang::getTxt('alert_' . $alert_id, file: 'Profile') ?><?= isset($alert_details['help']) ? '<a href="' . Config::$scripturl . '?action=helpadmin;help=' . $alert_details['help'] . '" onclick="return reqOverlayDiv(this.href);" class="help floatright"><span class="main_icons help" title="' . Lang::getTxt('help', file: 'General') . '"></span></a>' : '' ?>
					</td><?php foreach (Utils::$context['alert_bits'] as $type => $bitmask): ?>
					<td class="centercol"><?php $this_value = Utils::$context['alert_prefs'][$alert_id] ?? 0; ?><?php
switch ($alert_details[$type]) {
					case 'always':
						echo '
						<input type="checkbox" checked disabled>';
						break;

					case 'yes':
						echo '
						<input type="checkbox" name="', $type, '_', $alert_id, '"', ($this_value & $bitmask) ? ' checked' : '', '>';
						break;

					case 'never':
						echo '
						<input type="checkbox" disabled>';
						break;
				}
?>
					</td><?php endforeach; ?>
				</tr><?php endforeach; ?><?php endforeach; ?>
			</table>
			<br>
			<div>
				<input id="notify_submit" type="submit" name="notify_submit" value="<?= Lang::getTxt('notify_save', file: 'Profile') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>"><?= !empty(Utils::$context['token_check']) ? '
				<input type="hidden" name="' . Utils::$context[Utils::$context['token_check'] . '_token_var'] . '" value="' . Utils::$context[Utils::$context['token_check'] . '_token'] . '">' : '' ?>
				<input type="hidden" name="u" value="<?= Utils::$context['id_member'] ?>">
				<input type="hidden" name="sa" value="<?= Utils::$context['menu_item_selected'] ?>">
			</div>
		</form>
		<br>