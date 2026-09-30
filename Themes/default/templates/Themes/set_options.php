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
 * This displays the form for setting theme options
 */
?>

		<form action="<?= Config::$scripturl ?>?action=admin;area=theme;th=<?= Utils::$context['theme_settings']['theme_id'] ?>;sa=reset" method="post" accept-charset="UTF-8">
			<input type="hidden" name="who" value="<?= Utils::$context['theme_options_reset'] ? 1 : 0 ?>">
			<div class="cat_bar">
				<h3 class="catbg">
					<?= Lang::getTxt(Utils::$context['theme_options_reset'] ? 'themeadmin_reset_options_title' : 'theme_options_title', ['theme' => Utils::$context['theme_settings']['name']], file: 'Themes') ?>

				</h3>
			</div>
			<div class="information noup">
				<?= Lang::getTxt(Utils::$context['theme_options_reset'] ? 'themeadmin_reset_options_info' : 'theme_options_defaults', file: 'Themes') ?>

			</div>
			<div class="windowbg noup">
				<dl class="settings"><?php
$skeys = array_keys(Utils::$context['options']);
$first_option_key = array_shift($skeys);
$titled_section = false;
?><?php foreach (Utils::$context['options'] as $i => $setting): ?><?php /* Just spit out separators and move on */ ?><?php if (empty($setting) || !is_array($setting)): ?><?php /* Insert a separator (unless this is the first item in the list) */ ?><?php if ($i !== $first_option_key): ?>

				</dl>
				<hr>
				<dl class="settings"><?php endif; ?><?php /* Should we give a name to this section? */ ?><?php if (is_string($setting) && !empty($setting)): ?><?php $titled_section = true; ?>

					<dt><strong><?= $setting ?></strong></dt>
					<dd></dd><?php else: ?><?php $titled_section = false; ?><?php endif; ?><?php continue; ?><?php endif; ?>

					<dt><?php /* Show the change option box? */ ?><?php if (Utils::$context['theme_options_reset']): ?>

						<select name="<?= !empty($setting['default']) ? 'default_' : '' ?>options_master[<?= $setting['id'] ?>]" onchange="this.form.options_<?= $setting['id'] ?>.disabled = this.selectedIndex != 1;">
							<option value="0" selected><?= Lang::getTxt('themeadmin_reset_options_none', file: 'Themes') ?></option>
							<option value="1"><?= Lang::getTxt('themeadmin_reset_options_change', file: 'Themes') ?></option>
							<option value="2"><?= Lang::getTxt('themeadmin_reset_options_default', file: 'Themes') ?></option>
						</select><?php endif; ?>

						<label for="options_<?= $setting['id'] ?>"><?= !$titled_section ? '<strong>' : '' ?><?= $setting['label'] ?><?= !$titled_section ? '</strong>' : '' ?></label><?php if (isset($setting['description'])): ?>

						<br>
						<span class="smalltext"><?= $setting['description'] ?></span><?php endif; ?>

					</dt><?php /* Display checkbox options */ ?><?php if ($setting['type'] == 'checkbox'): ?>

					<dd>
						<input type="hidden" name="<?= (!empty($setting['default']) ? 'default_' : '') ?>options[<?= $setting['id'] ?>]" value="0">
						<input type="checkbox" name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" id="options_<?= $setting['id'] ?>"<?= !empty($setting['value']) ? ' checked' : '' ?><?= Utils::$context['theme_options_reset'] ? ' disabled' : '' ?> value="1" class="floatleft"><?php /* How about selection lists, we all love them */ ?><?php elseif ($setting['type'] == 'list'): ?>

					<dd>
						<select class="floatleft" name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" id="options_<?= $setting['id'] ?>"<?= Utils::$context['theme_options_reset'] ? ' disabled' : '' ?>><?php foreach ($setting['options'] as $value => $label): ?>

							<option value="<?= $value ?>"<?= $value == $setting['value'] ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>

						</select><?php /* A textbox it is then */ ?><?php else: ?>

					<dd><?php if (isset($setting['type']) && $setting['type'] == 'number'): ?><?php
$min = isset($setting['min']) ? ' min="' . $setting['min'] . '"' : ' min="0"';
$max = isset($setting['max']) ? ' max="' . $setting['max'] . '"' : '';
$step = isset($setting['step']) ? ' step="' . $setting['step'] . '"' : '';
?>

						<input type="number"<?= $min ?><?= $max ?><?= $step ?><?php elseif (isset($setting['type']) && $setting['type'] == 'url'): ?>

						<input type="url"<?php else: ?>

						<input type="text"<?php endif; ?> name="<?= !empty($setting['default']) ? 'default_' : '' ?>options[<?= $setting['id'] ?>]" id="options_<?= $setting['id'] ?>" value="<?= $setting['value'] ?>"<?= $setting['type'] == 'number' ? ' size="5"' : '' ?><?= Utils::$context['theme_options_reset'] ? ' disabled' : '' ?>><?php endif; ?><?php /* End of this definition, close open dds */ ?>

					</dd><?php endforeach; ?><?php /* Close the option page up */ ?>

				</dl>
				<input type="submit" name="submit" value="<?= Lang::getTxt('save', file: 'General') ?>" class="button">
				<input type="hidden" name="<?= Utils::$context['session_var'] ?>" value="<?= Utils::$context['session_id'] ?>">
				<input type="hidden" name="<?= Utils::$context['admin-sto_token_var'] ?>" value="<?= Utils::$context['admin-sto_token'] ?>">
			</div>
		</form>