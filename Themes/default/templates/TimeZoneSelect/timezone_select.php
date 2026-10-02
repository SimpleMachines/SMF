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

use SMF\TimeZone;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Template for a select menu to choose a time zone.
 *
 * @param string $name The name of the select menu.
 * @param string $selection The currently selected time zone identifier.
 * @param int $disabled If 0, the select is enabled. If 1, it is disabled.
 *    If 2, it is disabled and cannot be re-enabled by JavaScript.
 *    Default: 0.
 * @param array $extra Key-value pairs for any extra attributes to set on the
 *    select menu, where keys are attributes names. For boolean attributes, set
 *    the value to true.
 *    Default: ''.
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['disabled' => 0, 'extra' => []], EXTR_SKIP);
?><?php
$timezone_list = Utils::$context['all_timezones'] ?? TimeZone::list();
$attributes = [
	'name="' . $name . '"',
	'id="' . $name . '"',
];
?><?php if ($disabled): ?><?php $attributes[] = 'disabled'; ?><?php endif; ?><?php if ($disabled > 1): ?><?php $attributes[] = 'data-force-disabled'; ?><?php endif; ?><?php foreach ($extra as $key => $value): ?><?php if (empty($value)): ?><?php continue; ?><?php endif; ?><?php if ($value === true): ?><?php $attributes[] = $key; ?><?php else: ?><?php $attributes[] = $key . '="' . $value . '"'; ?><?php endif; ?><?php endforeach; ?><?php
// Setting width on selects inside floating elements can be flaky,
// so we need to calculate the width value manually.
$width = 0;
?><?php foreach ($timezone_list as $list): ?><?php $width = max($width, max(array_map(fn($label) => Utils::entityStrlen($label), $list))); ?><?php endforeach; ?><?php $attributes[] = 'style="width:min(' . ($width * 0.9) . 'ch, 100%)'; ?>
								<select <?= implode(' ', $attributes) ?>"><?php foreach ($timezone_list as $country => $list): ?>
									<optgroup label="<?= $country ?>"><?php foreach ($list as $tzid => $label): ?>
										<option<?= is_numeric($tzid) ? ' value="" disabled' : ' value="' . $tzid . '"' ?><?= $tzid === $selection ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
									</optgroup><?php endforeach; ?>
								</select>