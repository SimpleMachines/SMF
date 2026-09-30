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
 * The template for displaying a menu
 *
 * @param array $menu_context An array of menu information
 */
?>

				<div class="generic_menu">
					<ul class="dropmenu dropdown_menu_<?= Utils::$context['cur_menu_id'] ?>">
<?php /* Main areas first. */ ?>
<?php foreach ($menu_context['sections'] as $section): ?>
						<li <?= !empty($section['areas']) ? 'class="subsections"' : '' ?>><a class="<?= !empty($section['selected']) ? 'active ' : '' ?>" href="<?= $section['url'] ?><?= $menu_context['extra_parameters'] ?>"><?= $section['title'] ?><?= !empty($section['amt']) ? ' <span class="amt">' . $section['amt'] . '</span>' : '' ?></a>
							<ul>
<?php
// For every area of this section show a link to that area (bold if it's currently selected.)
// @todo Code for additional_items class was deprecated and has been removed. Suggest following up in Sources if required.
?>
<?php foreach ($section['areas'] as $i => $area): ?>
<?php /* Not supposed to be printed? */ ?>
<?php if (empty($area['label'])): ?>
<?php continue; ?>
<?php endif; ?>
								<li<?= !empty($area['subsections']) && empty($area['hide_subsections']) ? ' class="subsections"' : '' ?>>
									<a class="<?= $area['icon_class'] ?><?= !empty($area['selected']) ? ' chosen ' : '' ?>" href="<?= ($area['url'] ?? $menu_context['base_url'] . ';area=' . $i) ?><?= $menu_context['extra_parameters'] ?>"><?= $area['icon'] ?><?= $area['label'] ?><?= !empty($area['amt']) ? ' <span class="amt">' . $area['amt'] . '</span>' : '' ?></a>
<?php /* Is this the current area, or just some area? */ ?>
<?php if (!empty($area['selected']) && empty(Utils::$context['tabs'])): ?>
<?php Utils::$context['tabs'] = $area['subsections'] ?? []; ?>
<?php endif; ?>
<?php /* Are there any subsections? */ ?>
<?php if (!empty($area['subsections']) && empty($area['hide_subsections'])): ?>
									<ul>
<?php foreach ($area['subsections'] as $sa => $sub): ?>
<?php if (!empty($sub['disabled'])): ?>
<?php continue; ?>
<?php endif; ?>
<?php $url = $sub['url'] ?? ($area['url'] ?? $menu_context['base_url'] . ';area=' . $i) . ';sa=' . $sa; ?>
										<li>
											<a <?= !empty($sub['selected']) ? 'class="chosen" ' : '' ?> href="<?= $url ?><?= $menu_context['extra_parameters'] ?>"><?= $sub['label'] ?><?= !empty($sub['amt']) ? ' <span class="amt">' . $sub['amt'] . '</span>' : '' ?></a>
										</li>
<?php endforeach; ?>
									</ul>
<?php endif; ?>
								</li>
<?php endforeach; ?>
							</ul>
						</li>
<?php endforeach; ?>
					</ul><!-- .dropmenu -->
				</div><!-- .generic_menu -->