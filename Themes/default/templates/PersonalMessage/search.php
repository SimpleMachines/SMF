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
 * The form for the PM search feature
 */
?><?php if (!empty(Utils::$context['search_errors'])): ?>
		<div class="errorbox">
			<?= implode('<br>', Utils::$context['search_errors']['messages']) ?>
		</div><?php endif; ?>
	<form action="<?= Config::$scripturl ?>?action=pm;sa=search2" method="post" accept-charset="UTF-8" name="searchform" id="searchform">
		<div class="cat_bar">
			<h3 class="catbg"><?= Lang::getTxt('pm_search_title', file: 'PersonalMessage') ?></h3>
		</div>
		<div id="advanced_search" class="roundframe">
			<input type="hidden" name="advanced" value="1">
			<dl id="search_options" class="settings">
				<dt>
					<strong><label for="searchfor"><?= Lang::getTxt('pm_search_text', file: 'PersonalMessage') ?></label></strong>
				</dt>
				<dd>
					<input type="search" name="search"<?= !empty(Utils::$context['search_params']['search']) ? ' value="' . Utils::$context['search_params']['search'] . '"' : '' ?> size="40">
					<script>
						window.addEventListener("load", initSearch, false);
					</script>
				</dd>
				<dt>
					<label for="searchtype"><?= Lang::getTxt('search_match', file: 'General') ?></label>
				</dt>
				<dd>
					<select name="searchtype">
						<option value="1"<?= empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '' ?>><?= Lang::getTxt('pm_search_match_all', file: 'PersonalMessage') ?></option>
						<option value="2"<?= !empty(Utils::$context['search_params']['searchtype']) ? ' selected' : '' ?>><?= Lang::getTxt('pm_search_match_any', file: 'PersonalMessage') ?></option>
					</select>
				</dd>
				<dt>
					<label for="userspec"><?= Lang::getTxt('pm_search_user', file: 'PersonalMessage') ?></label>
				</dt>
				<dd>
					<input type="text" name="userspec" value="<?= empty(Utils::$context['search_params']['userspec']) ? '*' : Utils::$context['search_params']['userspec'] ?>" size="40">
				</dd>
				<dt>
					<label for="sort"><?= Lang::getTxt('pm_search_order', file: 'PersonalMessage') ?></label>
				</dt>
				<dd>
					<select name="sort">
						<option value="relevance|desc"><?= Lang::getTxt('pm_search_orderby_relevant_first', file: 'PersonalMessage') ?></option>
						<option value="id_pm|desc"><?= Lang::getTxt('pm_search_orderby_recent_first', file: 'PersonalMessage') ?></option>
						<option value="id_pm|asc"><?= Lang::getTxt('pm_search_orderby_old_first', file: 'PersonalMessage') ?></option>
					</select>
				</dd>
				<dt class="options">
					<?= Lang::getTxt('pm_search_options', file: 'PersonalMessage') ?>
				</dt>
				<dd class="options">
					<label for="show_complete">
						<input type="checkbox" name="show_complete" id="show_complete" value="1"<?= !empty(Utils::$context['search_params']['show_complete']) ? ' checked' : '' ?>> <?= Lang::getTxt('pm_search_show_complete', file: 'PersonalMessage') ?>
					</label><br>
					<label for="subject_only">
						<input type="checkbox" name="subject_only" id="subject_only" value="1"<?= !empty(Utils::$context['search_params']['subject_only']) ? ' checked' : '' ?>> <?= Lang::getTxt('pm_search_subject_only', file: 'PersonalMessage') ?>
					</label>
				</dd>
				<dt class="between">
					<?= Lang::getTxt('pm_search_post_age', file: 'PersonalMessage') ?>
				</dt>
				<dd>
					<?= Lang::getTxt(
		'pm_search_age_range',
		[
			'min' => '<input type="number" name="minage" min="0" max="9999" value="' . (empty(Utils::$context['search_params']['minage']) ? '0' : Utils::$context['search_params']['minage']) . '">',
			'max' => '<input type="number" name="maxage" min="0" max="9999" value="' . (empty(Utils::$context['search_params']['maxage']) ? '9999' : Utils::$context['search_params']['maxage']) . '">',
		],
		file: 'PersonalMessage',
	) ?>
				</dd>
			</dl><?php if (!Utils::$context['currently_using_labels']): ?>
				<input type="submit" name="pm_search" value="<?= Lang::getTxt('pm_search_go', file: 'PersonalMessage') ?>" class="button floatright"><?php endif; ?>
		</div><!-- .roundframe --><?php /* Do we have some labels setup? If so offer to search by them! */ ?><?php if (Utils::$context['currently_using_labels']): ?>
		<fieldset class="labels">
			<div class="roundframe alt">
				<div class="title_bar">
					<h3 class="titlebg">
						<a href="#" id="advanced_panel_link"><?= Lang::getTxt('pm_search_choose_label', file: 'PersonalMessage') ?></a>
					</h3>
					<span id="advanced_panel_toggle" class="toggle_up" style="display: none;"></span>
				</div>
				<div id="advanced_panel_div">
					<ul id="search_labels"><?php foreach (Utils::$context['search_labels'] as $label): ?>
						<li>
							<label for="searchlabel_<?= $label['id'] ?>"><input type="checkbox" id="searchlabel_<?= $label['id'] ?>" name="searchlabel[<?= $label['id'] ?>]" value="<?= $label['id'] ?>"<?= $label['checked'] ? ' checked' : '' ?>>
							<?= $label['name'] ?></label>
						</li><?php endforeach; ?>
					</ul>
				</div>
				<br class="clear">
				<div class="padding">
					<input type="checkbox" name="all" id="check_all" value=""<?= Utils::$context['check_all'] ? ' checked' : '' ?> onclick="invertAll(this, this.form, 'searchlabel');">
					<label for="check_all"><em><?= Lang::getTxt('check_all', file: 'General') ?></em></label>
					<input type="submit" name="pm_search" value="<?= Lang::getTxt('pm_search_go', file: 'PersonalMessage') ?>" class="button floatright">
				</div class="padding">
			</div><!-- .roundframe -->
		</fieldset><?php /* Some javascript for the advanced toggling */ ?>
		<script>
			var oAdvancedPanelToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					'advanced_panel_div'
				],
				aSwapImages: [
					{
						sId: 'advanced_panel_toggle',
						altExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('hide', file: 'General')) ?>,
						altCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('show', file: 'General')) ?>

					}
				],
				aSwapLinks: [
					{
						sId: 'advanced_panel_link',
						msgExpanded: <?= Utils::escapeJavaScript(Lang::getTxt('pm_search_choose_label', file: 'PersonalMessage')) ?>,
						msgCollapsed: <?= Utils::escapeJavaScript(Lang::getTxt('pm_search_choose_label', file: 'PersonalMessage')) ?>

					}
				]
			});
		</script><?php endif; ?>
	</form>