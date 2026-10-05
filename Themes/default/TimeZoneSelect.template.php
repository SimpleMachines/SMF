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

/**
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
function template_timezone_select(string $name, string $selection, int $disabled = 0, array $extra = [])
{
	$timezone_list = Utils::$context['all_timezones'] ?? TimeZone::list();

	$attributes = [
		'name="' . $name . '"',
		'id="' . $name . '"',
	];

	if ($disabled) {
		$attributes[] = 'disabled';
	}

	if ($disabled > 1) {
		$attributes[] = 'data-force-disabled';
	}

	foreach ($extra as $key => $value) {
		if (empty($value)) {
			continue;
		}

		if ($value === true) {
			$attributes[] = $key;
		} else {
			$attributes[] = $key . '="' . $value . '"';
		}
	}

	// Setting width on selects inside floating elements can be flaky,
	// so we need to calculate the width value manually.
	$width = 0;

	foreach ($timezone_list as $list) {
		$width = max($width, max(array_map(fn($label) => Utils::entityStrlen($label), $list)));
	}

	$attributes[] = 'style="width:min(' . ($width * 0.9) . 'ch, 100%)';

	echo '
								<select ', implode(' ', $attributes), '">';

	foreach ($timezone_list as $country => $list) {
		echo '
									<optgroup label="', $country, '">';

		foreach ($list as $tzid => $label) {
			echo '
										<option', is_numeric($tzid) ? ' value="" disabled' : ' value="' . $tzid . '"', $tzid === $selection ? ' selected' : '', '>', $label, '</option>';
		}

		echo '
									</optgroup>';
	}

	echo '
								</select>';
}
