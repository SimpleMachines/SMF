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
 * The code for displaying the menu
 *
 * @param array $menu_context An array of menu context data
 */
?><?php
// Handy shortcut.
$tab_context = $menu_context['tab_data'];
?><?php if (!empty($tab_context['title'])): ?>
					<div class="cat_bar">
						<h3 class="catbg"><?php /* Exactly how many tabs do we have? */ ?><?php if (!empty(Utils::$context['tabs'])): ?><?php foreach (Utils::$context['tabs'] as $id => $tab): ?><?php /* Can this not be accessed? */ ?><?php if (!empty($tab['disabled'])): ?><?php
$tab_context['tabs'][$id]['disabled'] = true;
continue;
?><?php endif; ?><?php /* Did this not even exist - or do we not have a label? */ ?><?php if (!isset($tab_context['tabs'][$id])): ?><?php $tab_context['tabs'][$id] = ['label' => $tab['label']]; ?><?php elseif (!isset($tab_context['tabs'][$id]['label'])): ?><?php $tab_context['tabs'][$id]['label'] = $tab['label']; ?><?php endif; ?><?php /* Has a custom URL defined in the main admin structure? */ ?><?php if (isset($tab['url']) && !isset($tab_context['tabs'][$id]['url'])): ?><?php $tab_context['tabs'][$id]['url'] = $tab['url']; ?><?php endif; ?><?php /* Any additional parameters for the url? */ ?><?php if (isset($tab['add_params']) && !isset($tab_context['tabs'][$id]['add_params'])): ?><?php $tab_context['tabs'][$id]['add_params'] = $tab['add_params']; ?><?php endif; ?><?php /* Has it been deemed selected? */ ?><?php if (!empty($tab['is_selected'])): ?><?php $tab_context['tabs'][$id]['is_selected'] = true; ?><?php endif; ?><?php /* Does it have its own help? */ ?><?php if (!empty($tab['help'])): ?><?php $tab_context['tabs'][$id]['help'] = $tab['help']; ?><?php endif; ?><?php /* Is this the last one? */ ?><?php if (!empty($tab['is_last']) && !isset($tab_context['override_last'])): ?><?php $tab_context['tabs'][$id]['is_last'] = true; ?><?php endif; ?><?php endforeach; ?><?php /* Find the selected tab */ ?><?php foreach ($tab_context['tabs'] as $sa => $tab): ?><?php if (!empty($tab['is_selected']) || (isset($menu_context['current_subsection']) && $menu_context['current_subsection'] == $sa)): ?><?php
$selected_tab = $tab;
$tab_context['tabs'][$sa]['is_selected'] = true;
?><?php endif; ?><?php endforeach; ?><?php endif; ?><?php /* Show an icon and/or a help item? */ ?><?php if (!empty($selected_tab['icon_class']) || !empty($tab_context['icon_class']) || !empty($selected_tab['icon']) || !empty($tab_context['icon']) || !empty($selected_tab['help']) || !empty($tab_context['help'])): ?><?php if (!empty($selected_tab['icon_class']) || !empty($tab_context['icon_class'])): ?>
								<span class="<?= !empty($selected_tab['icon_class']) ? $selected_tab['icon_class'] : $tab_context['icon_class'] ?> icon"></span><?php elseif (!empty($selected_tab['icon']) || !empty($tab_context['icon'])): ?>
								<img src="<?= Theme::$current->settings['images_url'] ?>/icons/<?= !empty($selected_tab['icon']) ? $selected_tab['icon'] : $tab_context['icon'] ?>" alt="" class="icon"><?php endif; ?><?php if (!empty($selected_tab['help']) || !empty($tab_context['help'])): ?>
								<a href="<?= Config::$scripturl ?>?action=helpadmin;help=<?= !empty($selected_tab['help']) ? $selected_tab['help'] : $tab_context['help'] ?>" onclick="return reqOverlayDiv(this.href);" class="help"><span class="main_icons help" title="<?= Lang::getTxt('help', file: 'General') ?>"></span></a><?php endif; ?><?= $tab_context['title'] ?><?php else: ?>
								<?= $tab_context['title'] ?><?php endif; ?>
						</h3><?php
// The function is in Admin.template.php, but since this template is used
// elsewhere too better check if the function is available. It goes after
// the heading: .cat_bar is a flex row, so the search box lands at the end
// of it by being last, not by floating.
?><?php if ($this->hasSubTemplate('admin_quick_search')): ?><?php $this->subTemplate('admin_quick_search'); ?><?php endif; ?>
					</div><!-- .cat_bar --><?php endif; ?><?php /* Shall we use the tabs? Yes, it's the only known way! */ ?><?php if (!empty($selected_tab['description']) || !empty($tab_context['description'])): ?>
					<p class="information">
						<?= !empty($selected_tab['description']) ? $selected_tab['description'] : $tab_context['description'] ?>
					</p><?php endif; ?><?php /* Print out all the items in this tab (if any). */ ?><?php
// What gets drawn below is $tab_context['tabs'], and that is only assembled
// above when this menu has a title of its own. Utils::$context['tabs'] is
// set as a side effect of drawing the menu, so it can be full while this
// one is still empty, which produced an empty menu on those pages.
// Labels arrive from the loop above, and it only runs over the areas the
// menu itself knows about, so a page that forces tabs of its own can leave
// some without one. Those cannot be drawn, so they do not count towards
// having anything to draw either. Same treatment the areas above get.
$drawable_tabs = array_filter(
	$tab_context['tabs'] ?? [],
	fn($tab) => empty($tab['disabled']) && !empty($tab['label']),
);
?><?php if (!empty($drawable_tabs)): ?><?php /* The admin tabs. */ ?>
					<a class="mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>_tabs">
						<span class="menu_icon"></span>
						<span class="text_menu"><?= Lang::getTxt('mobile_generic_menu', ['label' => $tab_context['title'] ?? ''], file: 'General') ?></span>
					</a>
					<div id="adm_submenus">
						<div id="mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>_tabs" class="popup_container">
							<div class="popup_window description">
								<div class="popup_heading">
									<?= Lang::getTxt('mobile_generic_menu', ['label' => $tab_context['title'] ?? ''], file: 'General') ?>
									<a href="javascript:void(0);" class="main_icons hide_popup"></a>
								</div>
								<div class="generic_menu">
									<ul class="dropmenu dropdown_menu_<?= Utils::$context['cur_menu_id'] ?>_tabs"><?php foreach ($drawable_tabs as $sa => $tab): ?><?php if (!empty($tab['is_selected'])): ?>
										<li>
											<a class="active" href="<?= $tab['url'] ?? $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa ?><?= $menu_context['extra_parameters'] ?><?= $tab['add_params'] ?? '' ?>"><?= $tab['label'] ?></a>
										</li><?php else: ?>
										<li>
											<a href="<?= $tab['url'] ?? $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa ?><?= $menu_context['extra_parameters'] ?><?= $tab['add_params'] ?? '' ?>"><?= $tab['label'] ?></a>
										</li><?php endif; ?><?php endforeach; ?><?php /* The end of tabs */ ?>
									</ul>
								</div>
							</div>
						</div>
					</div><!-- #adm_submenus -->
					<script>
						$( ".mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>_tabs" ).click(function() {
							$( "#mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>_tabs" ).show();
							});
						$( ".hide_popup" ).click(function() {
							$( "#mobile_generic_menu_<?= Utils::$context['cur_menu_id'] ?>_tabs" ).hide();
						});
					</script><?php endif; ?>
