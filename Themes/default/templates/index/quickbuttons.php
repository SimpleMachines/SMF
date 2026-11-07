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

use SMF\IntegrationHook;
use SMF\Lang;

if (!defined('SMF')) {
	die('No direct access...');
}

/*
 * Generate a list of quickbuttons.
 *
 * @param array $list_items An array with info for displaying the strip
 * @param string $list_class Used for integration hooks and as a class name
 * @param string $output_method Ignored: the buttons are output, and template_quickbuttons() returns them when asked.
 */

// The parameters that were not passed get the defaults they had as arguments.
extract(['list_class' => null, 'output_method' => 'echo'], EXTR_SKIP);
?><?php /* Enable manipulation with hooks */ ?><?php if (!empty($list_class)): ?><?php IntegrationHook::call('integrate_' . $list_class . '_quickbuttons', [&$list_items]); ?><?php endif; ?><?php /* Make sure the list has at least one shown item */ ?><?php foreach ($list_items as $key => $li): ?><?php /* Is there a sublist, and does it have any shown items */ ?><?php if ($key == 'more'): ?><?php foreach ($li as $subkey => $subli): ?><?php if (isset($subli['show']) && !$subli['show']): ?><?php unset($list_items[$key][$subkey]); ?><?php endif; ?><?php endforeach; ?><?php if (empty($list_items[$key])): ?><?php unset($list_items[$key]); ?><?php endif; ?><?php /* A normal list item */ ?><?php elseif (isset($li['show']) && !$li['show']): ?><?php unset($list_items[$key]); ?><?php endif; ?><?php endforeach; ?><?php /* Now check if there are any items left */ ?><?php if (empty($list_items)): ?><?php return; ?><?php endif; ?><?php /* Print the quickbuttons */ ?><?php
$output = '
		<ul class="quickbuttons' . (!empty($list_class) ? ' quickbuttons_' . $list_class : '') . '">';
// This is used for a list item or a sublist item
$list_item_format = function ($li) {
		$html = '
			<li' . (!empty($li['class']) ? ' class="' . $li['class'] . '"' : '') . (!empty($li['id']) ? ' id="' . $li['id'] . '"' : '') . (!empty($li['custom']) ? ' ' . $li['custom'] : '') . '>';

		if (isset($li['content'])) {
			$html .= $li['content'];
		} else {
		$html .= '
				<a href="' . (!empty($li['href']) ? $li['href'] : 'javascript:void(0);') . '"' . (!empty($li['javascript']) ? ' ' . $li['javascript'] : '') . '>
					' . (!empty($li['icon']) ? '<span class="main_icons ' . $li['icon'] . '"></span>' : '') . (!empty($li['label']) ? $li['label'] : '') . '
				</a>';
		}

		$html .= '
			</li>';

		return $html;
	};
?><?php foreach ($list_items as $key => $li): ?><?php /* Handle the sublist */ ?><?php if ($key == 'more'): ?><?php
$output .= '
			<li class="post_options">
				<a href="javascript:void(0);">' . Lang::getTxt('post_options', file: 'General') . '</a>
				<ul>';
?><?php foreach ($li as $subli): ?><?php $output .= $list_item_format($subli); ?><?php endforeach; ?><?php
$output .= '
				</ul>
			</li>';
// Ordinary list item
?><?php else: ?><?php $output .= $list_item_format($li); ?><?php endif; ?><?php endforeach; ?><?php
$output .= '
		</ul><!-- .quickbuttons -->';
// There are a few spots where the result needs to be returned
?><?= $output ?>
