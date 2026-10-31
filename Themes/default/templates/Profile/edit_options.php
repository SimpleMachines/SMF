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
 * Template for editing profile options.
 */
?><?php
// The main header!
// because some browsers ignore autocomplete=off and fill username in display name and/ or email field, fake them out.
$url = !empty(Utils::$context['profile_custom_submit_url']) ? Utils::$context['profile_custom_submit_url'] : Config::$scripturl . '?action=profile;area=' . Utils::$context['menu_item_selected'] . ';u=' . Utils::$context['id_member'];
$url = Utils::$context['require_password'] && !empty(Config::$modSettings['force_ssl']) ? strtr($url, ['http://' => 'https://']) : $url;
?>
		<form action="<?= $url ?>" method="post" accept-charset="UTF-8" name="creator" id="creator" enctype="multipart/form-data"<?= (Utils::$context['menu_item_selected'] == 'account' ? ' autocomplete="off"' : '') ?>>
			<div class="cat_bar">
				<h3 class="catbg profile_hd"><?php /* Don't say "Profile" if this isn't the profile... */ ?><?php if (!empty(Utils::$context['profile_header_text'])): ?>
					<?= Utils::$context['profile_header_text'] ?><?php else: ?>
					<?= Lang::getTxt('profile', file: 'General') ?><?php endif; ?>
				</h3>
			</div><?php /* Have we some description? */ ?><?php if (Utils::$context['page_desc']): ?>
			<p class="information"><?= Utils::$context['page_desc'] ?></p><?php endif; ?>
			<div class="roundframe"><?php /* Any bits at the start? */ ?><?php if (!empty(Utils::$context['profile_prehtml'])): ?>
				<div><?= Utils::$context['profile_prehtml'] ?></div><?php endif; ?><?php if (!empty(Utils::$context['profile_fields'])): ?>
				<dl class="settings"><?php endif; ?><?php
// Start the big old loop 'of love.
$lastItem = 'hr';
?><?php foreach (Utils::$context['profile_fields'] as $key => $field): ?><?php /* We add a little hack to be sure we never get more than one hr in a row! */ ?><?php if ($lastItem == 'hr' && $field['type'] == 'hr'): ?><?php continue; ?><?php endif; ?><?php $lastItem = $field['type']; ?><?php if ($field['type'] == 'hr'): ?>
				</dl>
				<hr>
				<dl class="settings"><?php elseif ($field['type'] == 'callback'): ?><?php if (isset($field['callback_func']) && $this->hasSubTemplate('profile_' . $field['callback_func'])): ?><?php $this->subTemplate('profile_' . $field['callback_func']); ?><?php endif; ?><?php else: ?>
					<dt>
						<strong<?= !empty($field['is_error']) ? ' class="error"' : '' ?>><?= $field['type'] !== 'label' ? '<label for="' . $key . '">' : '' ?><?= $field['label'] ?><?= $field['type'] !== 'label' ? '</label>' : '' ?></strong><?php /* Does it have any subtext to show? */ ?><?php if (!empty($field['subtext'])): ?>
						<br>
						<span class="smalltext"><?= $field['subtext'] ?></span><?php endif; ?>
					</dt>
					<dd><?php /* Want to put something infront of the box? */ ?><?php if (!empty($field['preinput'])): ?>
						<?= $field['preinput'] ?><?php endif; ?><?php /* What type of data are we showing? */ ?><?php if ($field['type'] == 'label'): ?>
						<?= $field['value'] ?><?php /* Maybe it's a text box - very likely! */ ?><?php elseif (in_array($field['type'], ['int', 'float', 'text', 'password', 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'number', 'time', 'url'])): ?><?php if ($field['type'] == 'int' || $field['type'] == 'float'): ?><?php $type = 'number'; ?><?php else: ?><?php $type = $field['type']; ?><?php endif; ?><?php $step = $field['type'] == 'float' ? ' step="0.1"' : ''; ?>
						<input type="<?= $type ?>" name="<?= $key ?>" id="<?= $key ?>" size="<?= empty($field['size']) ? 30 : $field['size'] ?>"<?= isset($field['min']) ? ' min="' . $field['min'] . '"' : '' ?><?= isset($field['max']) ? ' max="' . $field['max'] . '"' : '' ?> value="<?= $field['value'] ?>" <?= $field['input_attr'] ?> <?= $step ?>><?php /* You "checking" me out? ;) */ ?><?php elseif ($field['type'] == 'check'): ?>
						<input type="hidden" name="<?= $key ?>" value="0">
						<input type="checkbox" name="<?= $key ?>" id="<?= $key ?>"<?= !empty($field['value']) ? ' checked' : '' ?> value="1" <?= $field['input_attr'] ?>><?php /* Always fun - select boxes! */ ?><?php elseif ($field['type'] == 'select'): ?>
						<select name="<?= $key ?>" id="<?= $key ?>"><?php if (isset($field['options'])): ?><?php /* Is this some code to generate the options? */ ?><?php if (!is_array($field['options'])): ?><?php $field['options'] = $field['options'](); ?><?php endif; ?><?php /* Assuming we now have some! */ ?><?php if (is_array($field['options'])): ?><?php foreach ($field['options'] as $value => $name): ?>
							<option value="<?= $value ?>"<?= (!empty($field['disabled_options']) && is_array($field['disabled_options']) && in_array($value, $field['disabled_options'], true) ? ' disabled' : ($value == $field['value'] ? ' selected' : '')) ?>><?= $name ?></option><?php endforeach; ?><?php endif; ?><?php endif; ?>
						</select><?php endif; ?><?php /* Something to end with? */ ?><?php if (!empty($field['postinput'])): ?>
						<?= $field['postinput'] ?><?php endif; ?>
					</dd><?php endif; ?><?php endforeach; ?><?php if (!empty(Utils::$context['profile_fields'])): ?>
				</dl><?php endif; ?><?php /* Are there any custom profile fields - if so print them! */ ?><?php if (!empty(Utils::$context['custom_fields'])): ?><?php if ($lastItem != 'hr'): ?>
				<hr><?php endif; ?>
				<dl class="settings"><?php foreach (Utils::$context['custom_fields'] as $field): ?>
					<dt>
						<strong><?= $field['name'] ?></strong><br>
						<span class="smalltext"><?= $field['desc'] ?></span>
					</dt>
					<dd>
						<?= $field['input_html'] ?>
					</dd><?php endforeach; ?>
				</dl><?php endif; ?><?php /* Any closing HTML? */ ?><?php if (!empty(Utils::$context['profile_posthtml'])): ?>
				<div><?= Utils::$context['profile_posthtml'] ?></div><?php endif; ?><?php /* Only show the password box if it's actually needed. */ ?><?php if (Utils::$context['require_password']): ?>
				<dl class="settings">
					<dt>
						<strong<?= isset(Utils::$context['modify_error']['bad_password']) || isset(Utils::$context['modify_error']['no_password']) ? ' class="error"' : '' ?>><label for="oldpasswrd"><?= Lang::getTxt('current_password', file: 'Profile') ?></label></strong><br>
						<span class="smalltext"><?= Lang::getTxt('required_security_reasons', file: 'Profile') ?></span>
					</dt>
					<dd>
						<input type="password" name="oldpasswrd" id="oldpasswrd" size="20">
					</dd>
				</dl><?php endif; ?><?php /* The button shouldn't say "Change profile" unless we're changing the profile... */ ?><?php if (!empty(Utils::$context['submit_button_text'])): ?>
				<input type="submit" name="save" value="<?= Utils::$context['submit_button_text'] ?>" class="button floatright"><?php else: ?>
				<input type="submit" name="save" value="<?= Lang::getTxt('change_profile', file: 'Profile') ?>" class="button floatright"><?php endif; ?><?php if (!empty(Utils::$context['token_check'])): ?>
				<input type="hidden" name="<?= Utils::$context[Utils::$context['token_check'] . '_token_var'] ?>" value="<?= Utils::$context[Utils::$context['token_check'] . '_token'] ?>"><?php endif; ?>
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="u" value="<?= Utils::$context['id_member'] ?>">
				<input type="hidden" name="sa" value="<?= Utils::$context['menu_item_selected'] ?>">
			</div><!-- .roundframe -->
		</form><?php /* Any final spellchecking stuff? */ ?><?php if (!empty(Utils::$context['show_spellchecking'])): ?>
		<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="<?= Config::$scripturl ?>?action=spellcheck"><input type="hidden" name="spellstring" value=""></form><?php endif; ?>
