<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
 */

// This contains the html for the side bar of the admin center, which is used for all admin pages.
function template_generic_menu_dropdown_above()
{
	global $context;

	// Which menu are we rendering?
	$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 1;
	$menu_context = &$context['menu_data_' . $context['cur_menu_id']];

	echo '
				<div id="admin_menu">';

	echo '
					<ul class="dropmenu" id="dropdown_menu_', $context['cur_menu_id'], '">';

	// Main areas first.
	foreach ($menu_context['sections'] as $section)
	{
		echo '
						<li ', !empty($section['areas']) ? 'class="subsections"' : '', '><a class="', !empty($section['selected']) ? 'active ' : '', '" href="', $section['url'], $menu_context['extra_parameters'], '">', $section['title'] , '</a>
							<ul>';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		// @todo Code for additional_items class was deprecated and has been removed. Suggest following up in Sources if required.
		foreach ($section['areas'] as $i => $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
				continue;

			echo '
								<li', !empty($area['subsections']) ? ' class="subsections"' : '', '>';

			echo '
									<a class="', $area['icon_class'], !empty($area['selected']) ? ' chosen ' : '', '" href="', (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i), $menu_context['extra_parameters'], '">', $area['icon'], $area['label'], '</a>';

			// Is this the current area, or just some area?
			if (!empty($area['selected']) && empty($context['tabs']))
					$context['tabs'] = isset($area['subsections']) ? $area['subsections'] : array();

			// Are there any subsections?
			if (!empty($area['subsections']))
			{
				echo '
									<ul>';

				foreach ($area['subsections'] as $sa => $sub)
				{
					if (!empty($sub['disabled']))
						continue;

					$url = isset($sub['url']) ? $sub['url'] : (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i) . ';sa=' . $sa;

					echo '
										<li>
											<a ', !empty($sub['selected']) ? 'class="chosen" ' : '', ' href="', $url, $menu_context['extra_parameters'], '">', $sub['label'], '</a>
										</li>';
				}

				echo '
									</ul>';
			}

			echo '
								</li>';
		}
		echo '
							</ul>
						</li>';
	}

	echo '
					</ul>
				</div>';

	// This is the main table - we need it so we can keep the content to the right of it.
	echo '
				<div id="admin_content">';

	// It's possible that some pages have their own tabs they wanna force...
// 	if (!empty($context['tabs']))
		template_generic_menu_tabs($menu_context);
}

// Part of the admin layer - used with admin_above to close the table started in it.
function template_generic_menu_dropdown_below()
{
	echo '
				</div>';
}

// Some code for showing a tabbed view.
function template_generic_menu_tabs(&$menu_context)
{
	global $context, $settings, $scripturl, $txt;

	// Handy shortcut.
	$tab_context = &$menu_context['tab_data'];

	if (!empty($tab_context['title']))
	{
		echo '
					<div class="cat_bar">
						<h3 class="catbg">';

		// The function is in Admin.template.php, but since this template is used elsewhere too better check if the function is available
		if (function_exists('template_admin_quick_search'))
			template_admin_quick_search();

		// Exactly how many tabs do we have?
		if (!empty($context['tabs']))
		{
			foreach ($context['tabs'] as $id => $tab)
			{
				// Can this not be accessed?
				if (!empty($tab['disabled']))
				{
					$tab_context['tabs'][$id]['disabled'] = true;
					continue;
				}

				// Did this not even exist - or do we not have a label?
				if (!isset($tab_context['tabs'][$id]))
					$tab_context['tabs'][$id] = array('label' => $tab['label']);
				elseif (!isset($tab_context['tabs'][$id]['label']))
					$tab_context['tabs'][$id]['label'] = $tab['label'];

				// Has a custom URL defined in the main admin structure?
				if (isset($tab['url']) && !isset($tab_context['tabs'][$id]['url']))
					$tab_context['tabs'][$id]['url'] = $tab['url'];

				// Any additional paramaters for the url?
				if (isset($tab['add_params']) && !isset($tab_context['tabs'][$id]['add_params']))
					$tab_context['tabs'][$id]['add_params'] = $tab['add_params'];

				// Has it been deemed selected?
				if (!empty($tab['is_selected']))
					$tab_context['tabs'][$id]['is_selected'] = true;

				// Does it have its own help?
				if (!empty($tab['help']))
					$tab_context['tabs'][$id]['help'] = $tab['help'];

				// Is this the last one?
				if (!empty($tab['is_last']) && !isset($tab_context['override_last']))
					$tab_context['tabs'][$id]['is_last'] = true;
			}

			// Find the selected tab
			foreach ($tab_context['tabs'] as $sa => $tab)
			{
				if (!empty($tab['is_selected']) || (isset($menu_context['current_subsection']) && $menu_context['current_subsection'] == $sa))
				{
					$selected_tab = $tab;
					$tab_context['tabs'][$sa]['is_selected'] = true;
				}
			}
		}

		// Show an icon and/or a help item?
		if (!empty($selected_tab['icon_class']) || !empty($tab_context['icon_class']) || !empty($selected_tab['icon']) || !empty($tab_context['icon']) || !empty($selected_tab['help']) || !empty($tab_context['help']))
		{
			if (!empty($selected_tab['icon_class']) || !empty($tab_context['icon_class']))
				echo '<span class="', !empty($selected_tab['icon_class']) ? $selected_tab['icon_class'] : $tab_context['icon_class'], ' icon"></span>';
			elseif (!empty($selected_tab['icon']) || !empty($tab_context['icon']))
				echo '<img src="', $settings['images_url'], '/icons/', !empty($selected_tab['icon']) ? $selected_tab['icon'] : $tab_context['icon'], '" alt="" class="icon">';

			if (!empty($selected_tab['help']) || !empty($tab_context['help']))
				echo '<a href="', $scripturl, '?action=helpadmin;help=', !empty($selected_tab['help']) ? $selected_tab['help'] : $tab_context['help'], '" onclick="return reqOverlayDiv(this.href);" class="help"><span class="generic_icons help" title="', $txt['help'],'"></span></a>';

			echo $tab_context['title'];
		}
		else
		{
			echo '
							', $tab_context['title'];
		}

		echo '
						</h3>
					</div>';
	}

	// Shall we use the tabs? Yes, it's the only known way!
	if (!empty($selected_tab['description']) || !empty($tab_context['description']))
		echo '
					<p class="information">
						', !empty($selected_tab['description']) ? $selected_tab['description'] : $tab_context['description'], '
					</p>';

	// Print out all the items in this tab (if any).
	if (!empty($context['tabs']))
	{
		// The admin tabs.
		echo '
					<div id="adm_submenus">
						<ul class="dropmenu">';

		foreach ($tab_context['tabs'] as $sa => $tab)
		{
			if (!empty($tab['disabled']))
				continue;

			if (!empty($tab['is_selected']))
			{
				echo '
							<li>
								<a class="active" href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], isset($tab['add_params']) ? $tab['add_params'] : '', '">', $tab['label'], '</a>
							</li>';
			}
			else
				echo '
							<li>
								<a href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], isset($tab['add_params']) ? $tab['add_params'] : '', '">', $tab['label'], '</a>
							</li>';
		}

		// the end of tabs
		echo '
						</ul>
					</div>';

	}
}

?>