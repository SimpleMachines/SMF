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

use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for showing theme settings. Note: template_options() actually adds the theme specific options.
 */
?><?php
$skeys = array_keys(Utils::$context['theme_options']);
$first_option_key = array_shift($skeys);
$titled_section = false;
?><?php foreach (Utils::$context['theme_options'] as $i => $setting): ?><?php /* Just spit out separators and move on */ ?><?php if (empty($setting) || !is_array($setting)): ?><?php
// Avoid double separators and empty titled sections
$empty_section = true;
?><?php for ($j = $i + 1; $j < count(Utils::$context['theme_options']); $j++): ?><?php /* Found another separator, so we're done */ ?><?php if (!is_array(Utils::$context['theme_options'][$j])): ?><?php break; ?><?php endif; ?><?php /* Once we know there's something to show in this section, we can stop */ ?><?php if (!isset(Utils::$context['theme_options'][$j]['enabled']) || !empty(Utils::$context['theme_options'][$j]['enabled'])): ?><?php
$empty_section = false;
break;
?><?php endif; ?><?php endfor; ?><?php if ($empty_section): ?><?php if ($i === $first_option_key): ?><?php $first_option_key = array_shift($skeys); ?><?php endif; ?><?php continue; ?><?php endif; ?><?php /* Insert a separator (unless this is the first item in the list) */ ?><?php if ($i !== $first_option_key): ?>
				</dl>
				<hr>
				<dl class="settings"><?php endif; ?><?php /* Should we give a name to this section? */ ?><?php if (is_string($setting) && !empty($setting)): ?><?php $titled_section = true; ?>
					<dt><strong><?= $setting ?></strong></dt>
					<dd></dd><?php else: ?><?php $titled_section = false; ?><?php endif; ?><?php continue; ?><?php endif; ?><?php /* Is this disabled? */ ?><?php if (isset($setting['enabled']) && $setting['enabled'] === false): ?><?php if ($i === $first_option_key): ?><?php $first_option_key = array_shift($skeys); ?><?php endif; ?><?php continue; ?><?php endif; ?><?php
// Some of these may not be set...  Set to defaults here
$opts = ['calendar_start_day', 'topics_per_page', 'messages_per_page', 'display_quick_mod'];
?><?php if (in_array($setting['id'], $opts) && !isset(Utils::$context['member']['options'][$setting['id']])): ?><?php Utils::$context['member']['options'][$setting['id']] = 0; ?><?php endif; ?><?php if (!isset($setting['type']) || $setting['type'] == 'bool'): ?><?php $setting['type'] = 'checkbox'; ?><?php elseif ($setting['type'] == 'int' || $setting['type'] == 'integer'): ?><?php $setting['type'] = 'number'; ?><?php elseif ($setting['type'] == 'string'): ?><?php $setting['type'] = 'text'; ?><?php endif; ?><?php if (isset($setting['options'])): ?><?php $setting['type'] = 'list'; ?><?php endif; ?>
					<dt>
						<label for="<?= $setting['id'] ?>"><?= !$titled_section ? '<strong>' : '' ?><?= $setting['label'] ?><?= !$titled_section ? '</strong>' : '' ?></label><?php if (isset($setting['description'])): ?>
						<br>
						<span class="smalltext"><?= $setting['description'] ?></span><?php endif; ?>
					</dt>
					<dd><?php /* Display checkbox options */ ?><?php if ($setting['type'] == 'checkbox'): ?>
						<input type="hidden" name="default_options[<?= $setting['id'] ?>]" value="0">
						<input type="checkbox" name="default_options[<?= $setting['id'] ?>]" id="<?= $setting['id'] ?>"<?= !empty(Utils::$context['member']['options'][$setting['id']]) ? ' checked' : '' ?> value="1"><?php /* How about selection lists, we all love them */ ?><?php elseif ($setting['type'] == 'list'): ?>
						<select name="default_options[<?= $setting['id'] ?>]" id="<?= $setting['id'] ?>"><?php foreach ($setting['options'] as $value => $label): ?>
							<option value="<?= $value ?>"<?= isset(Utils::$context['member']['options'][$setting['id']]) && $value == Utils::$context['member']['options'][$setting['id']] ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
						</select><?php /* A textbox it is then */ ?><?php else: ?><?php if (isset($setting['type']) && $setting['type'] == 'number'): ?><?php
$min = isset($setting['min']) ? ' min="' . $setting['min'] . '"' : ' min="0"';
$max = isset($setting['max']) ? ' max="' . $setting['max'] . '"' : '';
$step = isset($setting['step']) ? ' step="' . $setting['step'] . '"' : '';
?>
						<input type="number"<?= $min ?><?= $max ?><?= $step ?><?php elseif (isset($setting['type']) && $setting['type'] == 'url'): ?>
						<input type="url"<?php else: ?>
						<input type="text"<?php endif; ?> name="default_options[<?= $setting['id'] ?>]" id="<?= $setting['id'] ?>" value="<?= Utils::$context['member']['options'][$setting['id']] ?? $setting['value'] ?>"<?= $setting['type'] == 'number' ? ' size="5"' : '' ?>><?php endif; ?><?php /* end of this definition */ ?>
					</dd><?php endforeach; ?>
