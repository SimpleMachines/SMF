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
 * Just list all the personal message subjects - to make templates easier.
 */
?>

	<div class="cat_bar">
		<h3 class="catbg">
			<?= Utils::$context['folder'] == 'sent' ? Lang::getTxt('sent_items', file: 'PersonalMessage') : Utils::$context['current_label'] ?>

		</h3>
	</div>
	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th class="centercol table_icon pm_icon">
					<a href="<?= Config::$scripturl ?>?action=pm;view;f=<?= Utils::$context['folder'] ?>;start=<?= Utils::$context['start'] ?>;sort=<?= Utils::$context['sort_by'] ?><?= (Utils::$context['sort_direction'] == 'up' ? '' : ';desc') ?><?= (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '') ?>"> <span class="main_icons switch" title="<?= Lang::getTxt('pm_change_view', file: 'PersonalMessage') ?>"></span></a>
				</th>
				<th class="lefttext quarter_table pm_time">
					<a href="<?= Config::$scripturl ?>?action=pm;f=<?= Utils::$context['folder'] ?>;start=<?= Utils::$context['start'] ?>;sort=date<?= Utils::$context['sort_by'] == 'date' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?><?= Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '' ?>"><?= Lang::getTxt('date', file: 'General') ?><?= Utils::$context['sort_by'] == 'date' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a>
				</th>
				<th class="lefttext half_table pm_subject">
					<a href="<?= Config::$scripturl ?>?action=pm;f=<?= Utils::$context['folder'] ?>;start=<?= Utils::$context['start'] ?>;sort=subject<?= Utils::$context['sort_by'] == 'subject' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?><?= Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '' ?>"><?= Lang::getTxt('subject', file: 'General') ?><?= Utils::$context['sort_by'] == 'subject' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a>
				</th>
				<th class="lefttext pm_from_to">
					<a href="<?= Config::$scripturl ?>?action=pm;f=<?= Utils::$context['folder'] ?>;start=<?= Utils::$context['start'] ?>;sort=name<?= Utils::$context['sort_by'] == 'name' && Utils::$context['sort_direction'] == 'up' ? ';desc' : '' ?><?= Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : '' ?>"><?= Lang::getTxt(Utils::$context['from_or_to'] == 'from' ? 'from' : 'to', file: 'General') ?><?= Utils::$context['sort_by'] == 'name' ? ' <span class="main_icons sort_' . Utils::$context['sort_direction'] . '"></span>' : '' ?></a>
				</th>
				<th class="centercol table_icon pm_moderation">
					<input type="checkbox" onclick="invertAll(this, this.form);">
				</th>
			</tr>
		</thead>
		<tbody><?php if (!Utils::$context['show_delete']): ?>

			<tr class="windowbg">
				<td colspan="5"><?= Lang::getTxt('pm_alert_none', file: 'General') ?></td>
			</tr><?php endif; ?><?php while ($message = Utils::$context['get_pmessage']('subject')): ?>

			<tr class="windowbg<?= $message['is_unread'] ? ' unread_pm' : '' ?>">
				<td class="table_icon pm_icon">
					<script>
						currentLabels[<?= $message['id'] ?>] = {<?php if (!empty($message['labels'])): ?><?php $first = true; ?><?php foreach ($message['labels'] as $label): ?><?= $first ? '' : ',' ?>

				"<?= $label['id'] ?>": "<?= $label['name'] ?>"<?php $first = false; ?><?php endforeach; ?><?php endif; ?>

						};
					</script>
					<?= $message['is_replied_to'] ? '<span class="main_icons replied" title="' . Lang::getTxt('pm_replied', file: 'PersonalMessage') . '"></span>' : '<span class="main_icons im_off" title="' . Lang::getTxt('pm_read', file: 'PersonalMessage') . '"></span>' ?>

				</td>
				<td class="pm_time"><?= $message['time'] ?></td>
				<td class="pm_subject">
					<?= (Utils::$context['display_mode'] != 0 && Utils::$context['current_pm'] == $message['id'] ? '<img src="' . Theme::$current->settings['images_url'] . '/selected.png" alt="*">' : '') ?><a href="<?= (Utils::$context['display_mode'] == 0 || Utils::$context['current_pm'] == $message['id'] ? '' : (Config::$scripturl . '?action=pm;pmid=' . $message['id'] . ';kstart;f=' . Utils::$context['folder'] . ';start=' . Utils::$context['start'] . ';sort=' . Utils::$context['sort_by'] . (Utils::$context['sort_direction'] == 'up' ? ';' : ';desc') . (Utils::$context['current_label_id'] != -1 ? ';l=' . Utils::$context['current_label_id'] : ''))) ?>#msg<?= $message['id'] ?>"><?= $message['subject'] ?><?= $message['is_unread'] ? '&nbsp;<span class="new_posts">' . Lang::getTxt('new', file: 'General') . '</span>' : '' ?></a>
				</td>
				<td class="pm_from_to">
					<?= (Utils::$context['from_or_to'] == 'from' ? $message['member']['link'] : (empty($message['recipients']['to']) ? '' : implode(', ', $message['recipients']['to']))) ?>

				</td>
				<td class="centercol table_icon pm_moderation">
					<input type="checkbox" name="pms[]" id="deletelisting<?= $message['id'] ?>" value="<?= $message['id'] ?>"<?= $message['is_selected'] ? ' checked' : '' ?> onclick="if (document.getElementById('deletedisplay<?= $message['id'] ?>')) document.getElementById('deletedisplay<?= $message['id'] ?>').checked = this.checked;">
				</td>
			</tr><?php endwhile; ?>

		</tbody>
	</table>
	<div class="pagesection">
		<div class="pagelinks"><?= Utils::$context['page_index'] ?></div>
		<div class="floatright">&nbsp;<?php if (Utils::$context['show_delete']): ?><?php if (!empty(Utils::$context['currently_using_labels']) && Utils::$context['folder'] != 'sent'): ?>

			<select name="pm_action" onchange="if (this.options[this.selectedIndex].value) this.form.submit();" onfocus="loadLabelChoices();">
				<option value=""><?= Lang::getTxt('pm_sel_label_title', file: 'PersonalMessage') ?></option>
				<option value="" disabled>---------------</option>
				<option value="" disabled><?= Lang::getTxt('pm_msg_label_apply', file: 'PersonalMessage') ?></option><?php foreach (Utils::$context['labels'] as $label): ?><?php if ($label['id'] != Utils::$context['current_label_id']): ?>

				<option value="add_<?= $label['id'] ?>">&nbsp;<?= $label['name'] ?></option><?php endif; ?><?php endforeach; ?>

				<option value="" disabled><?= Lang::getTxt('pm_msg_label_remove', file: 'PersonalMessage') ?></option><?php foreach (Utils::$context['labels'] as $label): ?>

				<option value="rem_<?= $label['id'] ?>">&nbsp;<?= $label['name'] ?></option><?php endforeach; ?>

			</select>
			<noscript>
				<input type="submit" value="<?= Lang::getTxt('pm_apply', file: 'PersonalMessage') ?>" class="button">
			</noscript><?php endif; ?>

			<input type="submit" name="del_selected" value="<?= Lang::getTxt('quickmod_delete_selected', file: 'General') ?>" data-confirm="<?= Lang::getTxt('delete_selected_confirm', file: 'PersonalMessage') ?>" class="button you_sure"><?php endif; ?>

		</div><!-- .floatright -->
	</div><!-- .pagesection -->