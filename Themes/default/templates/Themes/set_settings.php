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
 * The page for setting and managing theme settings.
 */
?>
	<div id="admin_form_wrapper">
		<form action="<?= Config::$scripturl ?>?action=admin;area=theme;sa=list;th=<?= Utils::$context['theme_settings']['theme_id'] ?>" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3 class="catbg">
					<a href="<?= Config::$scripturl ?>?action=helpadmin;help=theme_settings" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a> <?= Lang::getTxt('theme_settings_for', ['theme' => Utils::$context['theme_settings']['name']], file: 'Themes') ?>
				</h3>
			</div>
			<div class="windowbg"><?php /* @todo Why can't I edit the default theme popup. */ ?><?php if (Utils::$context['theme_settings']['theme_id'] != 1): ?>
				<div class="title_bar">
					<h3 class="titlebg config_hd">
						<?= Lang::getTxt('theme_edit', file: 'Themes') ?>
					</h3>
				</div>
				<div class="windowbg">
					<ul>
						<li>
							<a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= Utils::$context['theme_settings']['theme_id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=edit;filename=index.template.php"><?= Lang::getTxt('theme_edit_index', file: 'Themes') ?></a>
						</li>
						<li>
							<a href="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= Utils::$context['theme_settings']['theme_id'] ?>;<?= Utils::$context['session_var'] ?>=<?= Utils::$context['session_id'] ?>;sa=edit;directory=css"><?= Lang::getTxt('theme_edit_style', file: 'Themes') ?></a>
						</li>
					</ul>
				</div><?php endif; ?>
				<div class="title_bar">
					<h3 class="titlebg config_hd">
						<?= Lang::getTxt('theme_url_config', file: 'Themes') ?>
					</h3>
				</div>
				<dl class="settings">
					<dt>
						<label for="theme_name"><?= Lang::getTxt('actual_theme_name', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="text" id="theme_name" name="options[name]" value="<?= Utils::$context['theme_settings']['name'] ?>" size="32">
					</dd>
					<dt>
						<label for="theme_url"><?= Lang::getTxt('actual_theme_url', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="text" id="theme_url" name="options[theme_url]" value="<?= Utils::$context['theme_settings']['actual_theme_url'] ?>" size="50">
					</dd>
					<dt>
						<label for="images_url"><?= Lang::getTxt('actual_images_url', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="text" id="images_url" name="options[images_url]" value="<?= Utils::$context['theme_settings']['actual_images_url'] ?>" size="50">
					</dd>
					<dt>
						<label for="theme_dir"><?= Lang::getTxt('actual_theme_dir', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="text" id="theme_dir" name="options[theme_dir]" value="<?= Utils::$context['theme_settings']['actual_theme_dir'] ?>" size="50">
					</dd>
				</dl><?php /* Do we allow theme variants? */ ?><?php if (!empty(Utils::$context['theme_variants'])): ?>
				<div class="title_bar">
					<h3 class="titlebg config_hd">
						<?= Lang::getTxt('theme_variants', file: 'Themes') ?>
					</h3>
				</div>
				<dl class="settings">
					<dt>
						<label for="variant"><?= Lang::getTxt('theme_variants_default', file: 'Themes') ?></label>
					</dt>
					<dd>
						<select id="variant" name="options[default_variant]" onchange="changeVariant(this.value)"><?php foreach (Utils::$context['theme_variants'] as $key => $variant): ?>
							<option value="<?= $key ?>"<?= Utils::$context['default_variant'] == $key ? ' selected' : '' ?>><?= $variant['label'] ?></option><?php endforeach; ?>
						</select>
					</dd>
					<dt>
						<label for="disable_user_variant"><?= Lang::getTxt('theme_variants_user_disable', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="hidden" name="options[disable_user_variant]" value="0">
						<input type="checkbox" name="options[disable_user_variant]" id="disable_user_variant"<?= !empty(Utils::$context['theme_settings']['disable_user_variant']) ? ' checked' : '' ?> value="1">
					</dd>
				</dl>
				<img src="<?= Utils::$context['theme_variants'][Utils::$context['default_variant']]['thumbnail'] ?>" id="variant_preview" alt=""><?php endif; ?><?php /* Color modes for the theme? */ ?><?php if (!empty(Theme::$current->settings['has_dark_mode'])): ?>
				<div class="title_bar">
					<h3 class="titlebg config_hd">
						<?= Lang::getTxt('theme_colormodes', file: 'Themes') ?>
					</h3>
				</div>
				<dl class="settings">
					<dt>
						<label for="colormode"><?= Lang::getTxt('theme_colormode_default', file: 'Themes') ?></label>
					</dt>
					<dd>
						<select id="colormode" name="options[default_colormode]"><?php foreach (Theme::$current->settings['theme_colormodes'] as $mode): ?>
							<option value="<?= $mode ?>"<?= Theme::$current->settings['default_colormode'] == $mode ? ' selected' : '' ?>><?= Lang::getTxt('colormode_' . $mode, file: 'Themes') ?></option><?php endforeach; ?>
						</select>
					</dd>
					<dt>
						<label for="disable_user_mode"><?= Lang::getTxt('theme_colormode_user_disable', file: 'Themes') ?></label>
					</dt>
					<dd>
						<input type="hidden" name="options[disable_user_mode]" value="0">
						<input type="checkbox" name="options[disable_user_mode]" id="disable_user_mode"<?= !empty(Utils::$context['theme_settings']['disable_user_mode']) ? ' checked' : '' ?> value="1">
					</dd>
				</dl><?php endif; ?>
				<div class="title_bar">
					<h3 class="titlebg config_hd">
						<?= Lang::getTxt('theme_options', file: 'Themes') ?>
					</h3>
				</div>
				<dl class="settings"><?php
$skeys = array_keys(Utils::$context['settings']);
$first_setting_key = array_shift($skeys);
$titled_section = false;
?><?php foreach (Utils::$context['settings'] as $i => $setting): ?><?php /* Is this a separator? */ ?><?php if (empty($setting) || !is_array($setting)): ?><?php /* We don't need a separator before the first list element */ ?><?php if ($i !== $first_setting_key): ?>
				</dl>
				<hr>
				<dl class="settings"><?php endif; ?><?php /* Add a fake heading? */ ?><?php if (is_string($setting) && !empty($setting)): ?><?php $titled_section = true; ?>
					<dt><strong><?= $setting ?></strong></dt>
					<dd></dd><?php else: ?><?php $titled_section = false; ?><?php endif; ?><?php continue; ?><?php endif; ?>
					<dt>
						<label for="options_<?= $setting['id'] ?>"><?= !$titled_section ? '<strong>' : '' ?><?= $setting['label'] ?><?= !$titled_section ? '</strong>' : '' ?></label>:<?php if (isset($setting['description'])): ?><br>
						<span class="smalltext"><?= $setting['description'] ?></span><?php endif; ?>
					</dt><?php /* A checkbox? */ ?><?php if ($setting['type'] == 'checkbox'): ?>
					<dd>
						<input type="hidden" name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" value="0">
						<input type="checkbox" name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" id="options_<?= $setting['id'] ?>"<?= !empty($setting['value']) ? ' checked' : '' ?> value="1">
					</dd><?php /* A list with options? */ ?><?php elseif ($setting['type'] == 'list'): ?>
					<dd>
						<select name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" id="options_<?= $setting['id'] ?>"><?php foreach ($setting['options'] as $value => $label): ?>
							<option value="<?= $value ?>"<?= $value == $setting['value'] ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
						</select>
					</dd><?php /* A Textarea? */ ?><?php elseif ($setting['type'] == 'textarea'): ?>
					<dd>
						<textarea rows="4" style="width: 95%;" cols="40" name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" id="options_<?= $setting['id'] ?>"><?= $setting['value'] ?></textarea>
					</dd><?php /* A regular input box, then? */ ?><?php else: ?>
					<dd><?php if (isset($setting['type']) && $setting['type'] == 'number'): ?><?php
$min = isset($setting['min']) ? ' min="' . $setting['min'] . '"' : ' min="0"';
$max = isset($setting['max']) ? ' max="' . $setting['max'] . '"' : '';
$step = isset($setting['step']) ? ' step="' . $setting['step'] . '"' : '';
?>
						<input type="number"<?= $min ?><?= $max ?><?= $step ?><?php elseif (isset($setting['type']) && $setting['type'] == 'url'): ?>
						<input type="url"<?php elseif (isset($setting['type']) && $setting['type'] == 'color'): ?>
						<input type="color"<?php else: ?>
						<input type="text"<?php endif; ?> name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" id="options_<?= $setting['id'] ?>" value="<?= $setting['value'] ?>"<?= $setting['type'] == 'number' ? '' : (empty($setting['size']) ? ' size="40"' : ' size="' . $setting['size'] . '"') ?>>
					</dd><?php endif; ?><?php endforeach; ?>
				</dl>
				<input type="submit" name="save" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-sts_token_var'] ?>" value="<?= Utils::$context['admin-sts_token'] ?>">
			</div><!-- .windowbg -->
		</form>
	</div><!-- #admin_form_wrapper --><?php if (!empty(Utils::$context['theme_variants'])): ?>
		<script>
		var oThumbnails = {<?php
// All the variant thumbnails.
$count = 1;
?><?php foreach (Utils::$context['theme_variants'] as $key => $variant): ?>

			'<?= $key ?>': '<?= $variant['thumbnail'] ?>'<?= (count(Utils::$context['theme_variants']) == $count ? '' : ',') ?><?php $count++; ?><?php endforeach; ?>

		}
		</script><?php endif; ?>
