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

use SMF\Lang;
use SMF\Utils;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Generate a strip of buttons.
 *
 * @param array $button_strip An array with info for displaying the strip
 * @param string $direction The direction
 * @param array $strip_options Options for the button strip
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['direction' => '', 'strip_options' => []], EXTR_SKIP);
?><?php if (!is_array($strip_options)): ?><?php $strip_options = []; ?><?php endif; ?><?php
// Create the buttons...
$buttons = [];
?><?php foreach ($button_strip as $key => $value): ?><?php /* As of 2.1, the 'test' for each button happens while the array is being generated. The extra 'test' check here is deprecated but kept for backward compatibility (update your mods, folks!) */ ?><?php if (!isset($value['test']) || !empty(Utils::$context[$value['test']])): ?><?php if (!isset($value['id'])): ?><?php $value['id'] = $key; ?><?php endif; ?><?php
$button = '
				<a class="button button_strip_' . $key . (!empty($value['active']) ? ' active' : '') . (isset($value['class']) ? ' ' . $value['class'] : '') . '" ' . (!empty($value['url']) ? 'href="' . $value['url'] . '"' : '') . ' ' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . (!empty($value['icon']) ? '<span class="main_icons ' . $value['icon'] . '"></span>' : '') . Lang::getTxt($value['text']) . '</a>';
?><?php if (!empty($value['sub_buttons'])): ?><?php
$button .= '
					<div class="top_menu dropmenu ' . $key . '_dropdown">
						<div class="viewport">
							<div class="overview">';
?><?php foreach ($value['sub_buttons'] as $element): ?><?php if (isset($element['test']) && empty(Utils::$context[$element['test']])): ?><?php continue; ?><?php endif; ?><?php
$button .= '
								<a href="' . $element['url'] . '"><strong>' . Lang::getTxt($element['text']) . '</strong>';
?><?php if (Lang::txtExists($element['text'] . '_desc')): ?><?php $button .= '<br><span>' . Lang::getTxt($element['text'] . '_desc') . '</span>'; ?><?php endif; ?><?php $button .= '</a>'; ?><?php endforeach; ?><?php
$button .= '
							</div><!-- .overview -->
						</div><!-- .viewport -->
					</div><!-- .top_menu -->';
?><?php endif; ?><?php $buttons[] = $button; ?><?php endif; ?><?php endforeach; ?><?php /* No buttons? No button strip either. */ ?><?php if (empty($buttons)): ?><?php return; ?><?php endif; ?>
		<div class="buttonlist<?= !empty($direction) ? ' float' . $direction : '' ?>"<?= (empty($buttons) ? ' style="display: none;"' : '') ?><?= (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"' : '') ?>>
			<?= implode('', $buttons) ?>
		</div>