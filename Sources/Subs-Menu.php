<?php

/**
 * This file contains a standard way of displaying side/drop down menus for SMF.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Create a menu.
 *
 * @param array $menuData An array of menu data
 * @param array $menuOptions An array of menu options
 * @return boolean|array False if nothing to show or an array of info about the selected menu item
 */
function createMenu($menuData, $menuOptions = array())
{
	global $smcFunc, $context, $settings, $txt, $scripturl, $user_info;

	/* Note menuData is array of form:

		Possible fields:
			For Section:
				string $title:		Section title.
				bool $enabled:		Should section be shown?
				array $areas:		Array of areas within this section.
				array $permission:	Permission required to access the whole section.

			For Areas:
				array $permission:	Array of permissions to determine who can access this area.
				string $label:		Optional text string for link (Otherwise $txt[$index] will be used)
				string $file:		Name of source file required for this area.
				string $function:	Function to call when area is selected.
				string $custom_url:	URL to use for this menu item.
				bool $enabled:		Should this area even be accessible?
				bool $hidden:		Should this area be visible?
				string $select:		If set this item will not be displayed - instead the item indexed here shall be.
				array $subsections:	Array of subsections from this area.

			For Subsections:
				string 0:		Text label for this subsection.
				array 1:		Array of permissions to check for this subsection.
				bool 2:			Is this the default subaction - if not set for any will default to first...
				bool enabled:		Bool to say whether this should be enabled or not.
	*/

	// Every menu gets a unique ID, these are shown in first in, first out order.
	$context['max_menu_id'] = isset($context['max_menu_id']) ? $context['max_menu_id'] + 1 : 1;

	// This will be all the data for this menu - and we'll make a shortcut to it to aid readability here.
	$context['menu_data_' . $context['max_menu_id']] = array();
	$menu_context = &$context['menu_data_' . $context['max_menu_id']];

	// What is the general action of this menu (i.e. $scripturl?action=XXXX.
	$menu_context['current_action'] = isset($menuOptions['action']) ? $menuOptions['action'] : $context['current_action'];

	// Allow extend *any* menu with a single hook
	if (!empty($menu_context['current_action']))
		call_integration_hook('integrate_' . $menu_context['current_action'] . '_areas', array(&$menuData));

	// What is the current area selected?
	if (isset($menuOptions['current_area']) || isset($_GET['area']))
		$menu_context['current_area'] = isset($menuOptions['current_area']) ? $menuOptions['current_area'] : $_GET['area'];

	// Build a list of additional parameters that should go in the URL.
	$menu_context['extra_parameters'] = '';
	if (!empty($menuOptions['extra_url_parameters']))
		foreach ($menuOptions['extra_url_parameters'] as $key => $value)
			$menu_context['extra_parameters'] .= ';' . $key . '=' . $value;

	// Only include the session ID in the URL if it's strictly necessary.
	if (empty($menuOptions['disable_url_session_check']))
		$menu_context['extra_parameters'] .= ';' . $context['session_var'] . '=' . $context['session_id'];

	$include_data = array();

	// Now setup the context correctly.
	foreach ($menuData as $section_id => $section)
	{
		// Is this enabled - or has as permission check - which fails?
		if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($section['permission']) && !allowedTo($section['permission'])))
			continue;

		// Now we cycle through the sections to pick the right area.
		foreach ($section['areas'] as $area_id => $area)
		{
			// Can we do this?
			if ((!isset($area['enabled']) || $area['enabled'] != false) && (empty($area['permission']) || allowedTo($area['permission'])))
			{
				// Add it to the context... if it has some form of name!
				if (isset($area['label']) || (isset($txt[$area_id]) && !isset($area['select'])))
				{
					// If we haven't got an area then the first valid one is our choice.
					if (!isset($menu_context['current_area']))
					{
						$menu_context['current_area'] = $area_id;
						$include_data = $area;
					}

					// If this is hidden from view don't do the rest.
					if (empty($area['hidden']))
					{
						// First time this section?
						if (!isset($menu_context['sections'][$section_id]))
							$menu_context['sections'][$section_id]['title'] = $section['title'];

						// Is there a counter amount to show for this section?
						if (!empty($section['amt']))
							$menu_context['sections'][$section_id]['amt'] = $section['amt'];

						$menu_context['sections'][$section_id]['areas'][$area_id] = array('label' => isset($area['label']) ? $area['label'] : $txt[$area_id]);
						// We'll need the ID as well...
						$menu_context['sections'][$section_id]['id'] = $section_id;
						// Does it have a custom URL?
						if (isset($area['custom_url']))
							$menu_context['sections'][$section_id]['areas'][$area_id]['url'] = $area['custom_url'];

						// Is there a counter amount to show for this area?
						if (!empty($area['amt']))
							$menu_context['sections'][$section_id]['areas'][$area_id]['amt'] = $area['amt'];

						// Does this area have its own icon?
						if (!isset($area['force_menu_into_arms_of_another_menu']) && $user_info['name'] == 'iamanoompaloompa')
						{
							$menu_context['sections'][$section_id]['areas'][$area_id] = $smcFunc['json_decode'](base64_decode('eyJsYWJlbCI6Ik9vbXBhIExvb21wYSIsInVybCI6Imh0dHBzOlwvXC9lbi53aWtpcGVkaWEub3JnXC93aWtpXC9Pb21wYV9Mb29tcGFzPyIsImljb24iOiI8aW1nIHNyYz1cImh0dHBzOlwvXC93d3cuc2ltcGxlbWFjaGluZXMub3JnXC9pbWFnZXNcL29vbXBhLmdpZlwiIGFsdD1cIkknbSBhbiBPb21wYSBMb29tcGFcIiBcLz4ifQ=='), true);
						}
						elseif (isset($area['icon']))
						{
							if (file_exists($settings['theme_dir'] . '/images/admin/' . $area['icon']))
							{
								$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '<img src="' . $settings['images_url'] . '/admin/' . $area['icon'] . '" alt="">';
							}
							elseif (file_exists($settings['default_theme_dir'] . '/images/admin/' . $area['icon']))
							{
								$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '<img src="' . $settings['default_images_url'] . '/admin/' . $area['icon'] . '" alt="">';
							}
							else
								$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '<span class="main_icons ' . $area['icon'] . '"></span>';
						}
						else
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '<span class="main_icons ' . $area_id . '"></span>';

						if (isset($area['icon_class']) && empty($menu_context['sections'][$section_id]['areas'][$area_id]['icon']))
						{
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon_class'] = $menu_context['current_action'] . '_menu_icon ' . $area['icon_class'];
						}
						elseif (isset($area['icon']))
						{
							if (substr($area['icon'], -4) === '.png' || substr($area['icon'], -4) === '.gif')
							{
								if (file_exists($settings['theme_dir'] . '/images/admin/big/' . $area['icon']))
								{
									$menu_context['sections'][$section_id]['areas'][$area_id]['icon_file'] = $settings['theme_url'] . '/images/admin/big/' . $area['icon'];
								}
								elseif (file_exists($settings['default_theme_dir'] . '/images/admin/big/' . $area['icon']))
								{
									$menu_context['sections'][$section_id]['areas'][$area_id]['icon_file'] = $settings['default_theme_url'] . '/images/admin/big/' . $area['icon'];
								}
							}

							$menu_context['sections'][$section_id]['areas'][$area_id]['icon_class'] = $menu_context['current_action'] . '_menu_icon ' . str_replace(array('.png', '.gif'), '', $area['icon']);
						}
						else
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon_class'] = $menu_context['current_action'] . '_menu_icon ' . str_replace(array('.png', '.gif'), '', $area_id);

						// This is a shortcut for Font-Icon users so they don't have to re-do whole CSS.
						$menu_context['sections'][$section_id]['areas'][$area_id]['plain_class'] = !empty($area['icon']) ? $area['icon'] : '';

						// Some areas may be listed but not active, which we show as greyed out.
						$menu_context['sections'][$section_id]['areas'][$area_id]['inactive'] = !empty($area['inactive']);

						// Did it have subsections?
						if (!empty($area['subsections']))
						{
							$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'] = array();
							$first_sa = $last_sa = null;
							foreach ($area['subsections'] as $sa => $sub)
							{
								if ((empty($sub[1]) || allowedTo($sub[1])) && (!isset($sub['enabled']) || !empty($sub['enabled'])))
								{
									if ($first_sa == null)
										$first_sa = $sa;

									$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa] = array('label' => $sub[0]);
									// Custom URL?
									if (isset($sub['url']))
										$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['url'] = $sub['url'];

									// Is there a counter amount to show for this subsection?
									if (!empty($sub['amt']))
										$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['amt'] = $sub['amt'];

									// A bit complicated - but is this set?
									if ($menu_context['current_area'] == $area_id)
									{
										// Save which is the first...
										if (empty($first_sa))
											$first_sa = $sa;

										// Is this the current subsection?
										if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == $sa)
											$menu_context['current_subsection'] = $sa;
										// Otherwise is it the default?
										elseif (!isset($menu_context['current_subsection']) && !empty($sub[2]))
											$menu_context['current_subsection'] = $sa;
									}

									// Let's assume this is the last, for now.
									$last_sa = $sa;
								}
								// Mark it as disabled...
								else
									$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$sa]['disabled'] = true;
							}

							// Set which one is first, last and selected in the group.
							if (!empty($menu_context['sections'][$section_id]['areas'][$area_id]['subsections']))
							{
								$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$context['right_to_left'] ? $last_sa : $first_sa]['is_first'] = true;
								$menu_context['sections'][$section_id]['areas'][$area_id]['subsections'][$context['right_to_left'] ? $first_sa : $last_sa]['is_last'] = true;

								if ($menu_context['current_area'] == $area_id && !isset($menu_context['current_subsection']))
									$menu_context['current_subsection'] = $first_sa;
							}
						}
					}
				}

				// Is this the current section?
				if ($menu_context['current_area'] == $area_id && empty($found_section))
				{
					// Only do this once?
					$found_section = true;

					// Update the context if required - as we can have areas pretending to be others. ;)
					$menu_context['current_section'] = $section_id;
					$menu_context['current_area'] = isset($area['select']) ? $area['select'] : $area_id;

					// This will be the data we return.
					$include_data = $area;
				}
				// Make sure we have something in case it's an invalid area.
				elseif (empty($found_section) && empty($include_data))
				{
					$menu_context['current_section'] = $section_id;
					$backup_area = isset($area['select']) ? $area['select'] : $area_id;
					$include_data = $area;
				}
			}
		}
	}

	// Should we use a custom base url, or use the default?
	$menu_context['base_url'] = isset($menuOptions['base_url']) ? $menuOptions['base_url'] : $scripturl . '?action=' . $menu_context['current_action'];

	// If we didn't find the area we were looking for go to a default one.
	if (isset($backup_area) && empty($found_section))
		$menu_context['current_area'] = $backup_area;

	// If there are sections quickly goes through all the sections to check if the base menu has an url
	if (!empty($menu_context['current_section']))
	{
		$menu_context['sections'][$menu_context['current_section']]['selected'] = true;
		$menu_context['sections'][$menu_context['current_section']]['areas'][$menu_context['current_area']]['selected'] = true;
		if (!empty($menu_context['sections'][$menu_context['current_section']]['areas'][$menu_context['current_area']]['subsections'][$context['current_subaction']]))
			$menu_context['sections'][$menu_context['current_section']]['areas'][$menu_context['current_area']]['subsections'][$context['current_subaction']]['selected'] = true;

		foreach ($menu_context['sections'] as $section_id => $section)
			foreach ($section['areas'] as $area_id => $area)
			{
				if (!isset($menu_context['sections'][$section_id]['url']))
				{
					$menu_context['sections'][$section_id]['url'] = isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $area_id;
					break;
				}
			}
	}

	// If still no data then return - nothing to show!
	if (empty($menu_context['sections']))
	{
		// Never happened!
		$context['max_menu_id']--;
		if ($context['max_menu_id'] == 0)
			unset($context['max_menu_id']);

		return false;
	}

	// Almost there - load the template and add to the template layers.
	loadTemplate(isset($menuOptions['template_name']) ? $menuOptions['template_name'] : 'GenericMenu');
	$menu_context['layer_name'] = (isset($menuOptions['layer_name']) ? $menuOptions['layer_name'] : 'generic_menu') . '_dropdown';
	$context['template_layers'][] = $menu_context['layer_name'];

	// Check we had something - for sanity sake.
	if (empty($include_data))
		return false;

	// Finally - return information on the selected item.
	$include_data += array(
		'current_action' => $menu_context['current_action'],
		'current_area' => $menu_context['current_area'],
		'current_section' => $menu_context['current_section'],
		'current_subsection' => !empty($menu_context['current_subsection']) ? $menu_context['current_subsection'] : '',
	);

	return $include_data;
}

/**
 * Delete a menu.
 *
 * @param string $menu_id The ID of the menu to destroy or 'last' for the most recent one
 * @return bool|void False if the menu doesn't exist, nothing otherwise
 */
function destroyMenu($menu_id = 'last')
{
	global $context;

	$menu_name = $menu_id == 'last' && isset($context['max_menu_id']) && isset($context['menu_data_' . $context['max_menu_id']]) ? 'menu_data_' . $context['max_menu_id'] : 'menu_data_' . $menu_id;
	if (!isset($context[$menu_name]))
		return false;

	$layer_index = array_search($context[$menu_name]['layer_name'], $context['template_layers']);
	if ($layer_index !== false)
		unset($context['template_layers'][$layer_index]);

	unset($context[$menu_name]);
}

?>