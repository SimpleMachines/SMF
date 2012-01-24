<?php
/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

// This contains the html for the side bar of the admin center, which is used for all admin pages.
function template_generic_menu_sidebar_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// This is the main table - we need it so we can keep the content to the right of it.
	echo '
	<div id="main_container">
		<div id="left_admsection"><span id="admin_menu"></span>';

	// What one are we rendering?
	$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 1;
	$menu_context = &$context['menu_data_' . $context['cur_menu_id']];

	// For every section that appears on the sidebar...
	$firstSection = true;
	foreach ($menu_context['sections'] as $section)
	{
		// Show the section header - and pump up the line spacing for readability.
		echo '
			<div class="adm_section">
				<div class="cat_bar">
					<h4 class="catbg">';

		if ($firstSection && !empty($menu_context['can_toggle_drop_down']))
		{
			echo '
						<span class="ie6_header floatleft">
							<a href="', $menu_context['toggle_url'], '">', $section['title'],'<img style="margin: 0 5px; vertical-align: middle;" src="', $context['menu_image_path'], '/change_menu', $context['right_to_left'] ? '' : '2', '.png" alt="!" /></a>
						</span>';
		}
		else
		{
			echo '
						', $section['title'];
		}

		echo '
					</h4>
				</div>
				<ul class="smalltext left_admmenu">';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		foreach ($section['areas'] as $i => $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
				continue;

			echo '
					<li>';

			// Is this the current area, or just some area?
			if ($i == $menu_context['current_area'])
			{
				echo '
						<strong><a href="', isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i, $menu_context['extra_parameters'], '">', $area['label'], '</a></strong>';

				if (empty($context['tabs']))
					$context['tabs'] = isset($area['subsections']) ? $area['subsections'] : array();
			}
			else
				echo '
						<a href="', isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i, $menu_context['extra_parameters'], '">', $area['label'], '</a>';

			echo '
					</li>';
		}

		echo '
				</ul>
			</div>';

		$firstSection = false;
	}

	// This is where the actual "main content" area for the admin section starts.
	echo '
		</div>
		<div id="main_admsection">';

	// If there are any "tabs" setup, this is the place to shown them.
	if (!empty($context['tabs']) && empty($context['force_disable_tabs']))
		template_generic_menu_tabs($menu_context);
}

// Part of the sidebar layer - closes off the main bit.
function template_generic_menu_sidebar_below()
{
	global $context, $settings, $options;

	echo '
		</div>
	</div><br class="clear" />';
}

// This contains the html for the side bar of the admin center, which is used for all admin pages.
function template_generic_menu_dropdown_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Which menu are we rendering?
	$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 1;
	$menu_context = &$context['menu_data_' . $context['cur_menu_id']];

	if (!empty($menu_context['can_toggle_drop_down']))
		echo '
	<a href="', $menu_context['toggle_url'], '"><img id="menu_toggle" src="', $context['menu_image_path'], '/change_menu', $context['right_to_left'] ? '2' : '', '.png" alt="*" /></a>';

	echo '
<div id="admin_menu">
	<ul class="dropmenu" id="dropdown_menu_', $context['cur_menu_id'], '">';

	// Main areas first.
	foreach ($menu_context['sections'] as $section)
	{
		if ($section['id'] == $menu_context['current_section'])
		{
			echo '
			<li><a class="active firstlevel" href="#"><span class="firstlevel">', $section['title'] , '</span></a>
				<ul>';
		}
		else
			echo '
			<li><a class="firstlevel" href="#"><span class="firstlevel">', $section['title'] , '</span></a>
				<ul>';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		$additional_items = 0;
		foreach ($section['areas'] as $i => $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
				continue;

			echo '
					<li', (++$additional_items > 6) ? ' class="additional_items"' : '' ,'>';

			// Is this the current area, or just some area?
			if ($i == $menu_context['current_area'])
			{
				echo '
						<a class="chosen" href="', (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i), $menu_context['extra_parameters'], '"><span>', $area['icon'], $area['label'], !empty($area['subsections']) ? '...' : '', '</span></a>';

				if (empty($context['tabs']))
					$context['tabs'] = isset($area['subsections']) ? $area['subsections'] : array();
			}
			else
				echo '
						<a href="', (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $i), $menu_context['extra_parameters'], '"><span>', $area['icon'], $area['label'], !empty($area['subsections']) ? '...' : '', '</span></a>';

			// Is there any subsections?
			$additional_items_sub = 0;
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
							<li', (++$additional_items_sub > 6) ? ' class="additional_items"' : '' ,'>
								<a ', !empty($sub['selected']) ? 'class="active" ' : '', 'href="', $url, $menu_context['extra_parameters'], '"><span>', $sub['label'], '</span></a>
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
	if (!empty($context['tabs']))
		template_generic_menu_tabs($menu_context);
}

// Part of the admin layer - used with admin_above to close the table started in it.
function template_generic_menu_dropdown_below()
{
	global $context, $settings, $options;

	echo '
</div>';
}

// Some code for showing a tabbed view.
function template_generic_menu_tabs(&$menu_context)
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Handy shortcut.
	$tab_context = &$menu_context['tab_data'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">';

	// Exactly how many tabs do we have?
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

	// Show an icon and/or a help item?
	if (!empty($selected_tab['icon']) || !empty($tab_context['icon']) || !empty($selected_tab['help']) || !empty($tab_context['help']))
	{
		echo '
			<span class="ie6_header floatleft">';

		if (!empty($selected_tab['icon']) || !empty($tab_context['icon']))
			echo '<img src="', $settings['images_url'], '/icons/', !empty($selected_tab['icon']) ? $selected_tab['icon'] : $tab_context['icon'], '" alt="" class="icon" />';

		if (!empty($selected_tab['help']) || !empty($tab_context['help']))
			echo '<a href="', $scripturl, '?action=helpadmin;help=', !empty($selected_tab['help']) ? $selected_tab['help'] : $tab_context['help'], '" onclick="return reqWin(this.href);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" class="icon" /></a>';

		echo $tab_context['title'], '
			</span>';
	}
	else
	{
		echo '
			', $tab_context['title'];
	}

	echo '
		</h3>
	</div>';

	// Shall we use the tabs?
	if (!empty($settings['use_tabs']))
	{
		echo '
	<p class="windowbg description">
		', !empty($selected_tab['description']) ? $selected_tab['description'] : $tab_context['description'], '
	</p>';

		// The admin tabs.
		echo '
	<div id="adm_submenus">
		<ul class="dropmenu">';

		// Print out all the items in this tab.
		foreach ($tab_context['tabs'] as $sa => $tab)
		{
			if (!empty($tab['disabled']))
				continue;

			if (!empty($tab['is_selected']))
			{
				echo '
			<li>
				<a class="active firstlevel" href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], isset($tab['add_params']) ? $tab['add_params'] : '', '"><span class="firstlevel">', $tab['label'], '</span></a>
			</li>';
			}
			else
				echo '
			<li>
				<a class="firstlevel" href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], isset($tab['add_params']) ? $tab['add_params'] : '', '"><span class="firstlevel">', $tab['label'], '</span></a>
			</li>';
		}

		// the end of tabs
		echo '
		</ul>
	</div><br class="clear" />';
	}
	// ...if not use the old style
	else
	{
		echo '
	<p class="tabs">';

		// Print out all the items in this tab.
		foreach ($tab_context['tabs'] as $sa => $tab)
		{
			if (!empty($tab['disabled']))
				continue;

			if (!empty($tab['is_selected']))
			{
				echo '
		<img src="', $settings['images_url'], '/selected.gif" alt="*" /> <strong><a href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], '">', $tab['label'], '</a></strong>';
			}
			else
				echo '
		<a href="', isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa, $menu_context['extra_parameters'], '">', $tab['label'], '</a>';

			if (empty($tab['is_last']))
				echo ' | ';
		}

		echo '
	</p>
	<p class="windowbg description">', isset($selected_tab['description']) ? $selected_tab['description'] : $tab_context['description'], '</p>';
	}
}

?>