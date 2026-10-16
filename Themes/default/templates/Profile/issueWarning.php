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
 * Template for issuing warnings
 */
?><?php $this->subTemplate('load_warning_variables'); ?><?php
// The bodies of the notification templates and the text for each warning
// level. profile.js does the rest; all it needs from here is the data.
?>
	<script>
		var notification_templates = <?= json_encode(array_column(Utils::$context['notification_templates'], 'body'), JSON_UNESCAPED_SLASHES) ?>;
		var level_effects = <?= json_encode(Utils::$context['level_effects'], JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT) ?>;
	</script>
	<form action="<?= Config::$scripturl ?>?action=profile;u=<?= Utils::$context['id_member'] ?>;area=issuewarning" method="post" class="flow_hidden" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">
				<?= Lang::getTxt(Profile::$member->is_me ? 'profile_warning_level' : 'profile_issue_warning', file: 'Profile') ?>
			</h3>
		</div><?php if (!Profile::$member->is_me): ?>
		<p class="information"><?= Lang::getTxt('profile_warning_desc', file: 'Profile') ?></p><?php endif; ?>
		<div class="windowbg">
			<dl class="settings"><?php if (!Profile::$member->is_me): ?>
				<dt>
					<strong><?= Lang::getTxt('profile_warning_name', file: 'Profile') ?></strong>
				</dt>
				<dd>
					<strong><?= Utils::$context['member']['name'] ?></strong>
				</dd><?php endif; ?>
				<dt>
					<strong><?= Lang::getTxt('profile_warning_level', file: 'Profile') ?></strong><?php /* Is there only so much they can apply? */ ?><?php if (Utils::$context['warning_limit']): ?>
					<br>
					<span class="smalltext"><?= Lang::getTxt('profile_warning_limit_attribute', [Utils::$context['warning_limit'] / 100], file: 'Profile') ?></span><?php endif; ?>
				</dt>
				<dd>
					<?= Lang::formatText('{0, number, :: percent}', [0]) ?> <input name="warning_level" id="warning_level" type="range" min="0" max="100" step="5" value="<?= Utils::$context['member']['warning'] ?>"> <?= Lang::formatText('{0, number, :: percent}', [100]) ?>
					<div class="clear_left">
						<?= Lang::getTxt('profile_warning_impact', file: 'Profile') ?>: <output name="cur_level" for="warning_level" data-format="<?= Lang::getTxt('percent_format', file: 'General') ?>"><?= Lang::formatText('{0, number, :: percent}', [Utils::$context['member']['warning']]) ?> (<?= Utils::$context['level_effects'][Utils::$context['current_level']] ?>)</output>
					</div>
				</dd><?php if (!Profile::$member->is_me): ?>
				<dt>
					<strong><?= Lang::getTxt('profile_warning_reason', file: 'Profile') ?></strong><br>
					<span class="smalltext"><?= Lang::getTxt('profile_warning_reason_desc', file: 'Profile') ?></span>
				</dt>
				<dd>
					<input type="text" name="warn_reason" id="warn_reason" value="<?= Utils::$context['warning_data']['reason'] ?>" size="50">
				</dd>
			</dl>
			<hr>
			<div id="box_preview"<?= !empty(Utils::$context['warning_data']['body_preview']) ? '' : ' style="display:none"' ?>>
				<dl class="settings">
					<dt>
						<strong><?= Lang::getTxt('preview', file: 'General') ?></strong>
					</dt>
					<dd id="body_preview">
						<?= !empty(Utils::$context['warning_data']['body_preview']) ? Utils::adjustHeadingLevels(Utils::$context['warning_data']['body_preview'], 3) : '' ?>
					</dd>
				</dl>
				<hr>
			</div>
			<dl class="settings">
				<dt>
					<strong><label for="warn_notify"><?= Lang::getTxt('profile_warning_notify', file: 'Profile') ?></label></strong>
				</dt>
				<dd>
					<input type="checkbox" name="warn_notify" id="warn_notify"<?= Utils::$context['warning_data']['notify'] ? ' checked' : '' ?>>
				</dd>
				<dt>
					<strong><label for="warn_sub"><?= Lang::getTxt('profile_warning_notify_subject', file: 'Profile') ?></label></strong>
				</dt>
				<dd>
					<input type="text" name="warn_sub" id="warn_sub" value="<?= empty(Utils::$context['warning_data']['notify_subject']) ? Lang::getTxt('profile_warning_notify_template_subject', file: 'Profile') : Utils::$context['warning_data']['notify_subject'] ?>" size="50">
				</dd>
				<dt>
					<strong><label for="warn_temp"><?= Lang::getTxt('profile_warning_notify_body', file: 'Profile') ?></label></strong>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled>
						<option value="-1"><?= Lang::getTxt('profile_warning_notify_template', file: 'Profile') ?></option>
						<option value="-1" disabled>------------------------------</option><?php foreach (Utils::$context['notification_templates'] as $id_template => $template): ?>
						<option value="<?= $id_template ?>"><?= $template['title'] ?></option><?php endforeach; ?>
					</select>
					<span id="new_template_link" hidden><a href="<?= Config::$scripturl ?>?action=moderate;area=warnings;sa=templateedit;tid=0" class="button floatnone" target="_blank" rel="noopener"><?= Lang::getTxt('profile_warning_new_template', file: 'Profile') ?></a></span>
					<br>
					<textarea name="warn_body" id="warn_body" cols="40" rows="8"><?= Utils::$context['warning_data']['notify_body'] ?></textarea>
				</dd><?php endif; ?>
			</dl>
			<div class="righttext"><?php if (!empty(Utils::$context['token_check'])): ?>
				<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>"><?php endif; ?>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="button" name="preview" id="preview_button" value="<?= Lang::getTxt('preview', file: 'General') ?>" class="button">
				<input type="submit" name="save" value="<?= Lang::getTxt(Profile::$member->is_me ? 'change_profile' : 'profile_warning_issue', file: 'Profile') ?>" class="button">
			</div><!-- .righttext -->
		</div><!-- .windowbg -->
	</form><?php /* Previous warnings? */ ?><?php $this->subTemplate('show_list', ['list_id' => 'view_warnings']); ?>
