<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2011 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.0
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file contains a standard way of displaying side/drop down menus for SMF.
*/

// Create a menu...
function createMenu($menuData, $menuOptions = array())
{
	global $context, $settings, $options, $txt, $modSettings, $scripturl, $smcFunc, $user_info, $sourcedir, $options;

	// First are we toggling use of the side bar generally?
	if (isset($_GET['togglebar']) && !$user_info['is_guest'])
	{
		// Save the new dropdown menu state.
		$smcFunc['db_insert']('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			array(
				array(
					$user_info['id'],
					$settings['theme_id'],
					'use_sidebar_menu',
					empty($options['use_sidebar_menu']) ? '1' : '0',
				),
			),
			array('id_member', 'id_theme', 'variable')
		);

		// Clear the theme settings cache for this user.
		$themes = explode(',', $modSettings['knownThemes']);
		foreach ($themes as $theme)
			cache_put_data('theme_settings-' . $theme . ':' . $user_info['id'], null, 60);

		// Redirect as this seems to work best.
		$redirect_url = isset($menuOptions['toggle_redirect_url']) ? $menuOptions['toggle_redirect_url'] : 'action=' . (isset($_GET['action']) ? $_GET['action'] : 'admin') . ';area=' . (isset($_GET['area']) ? $_GET['area'] : 'index') . ';sa=' . (isset($_GET['sa']) ? $_GET['sa'] : 'settings') . (isset($_GET['u']) ? ';u=' . $_GET['u'] : '') . ';' . $context['session_var'] . '=' . $context['session_id'];
		redirectexit($redirect_url);
	}

	// Work out where we should get our images from.
	$context['menu_image_path'] = file_exists($settings['theme_dir'] . '/images/admin/change_menu.png') ? $settings['images_url'] . '/admin' : $settings['default_images_url'] . '/admin';

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

						$menu_context['sections'][$section_id]['areas'][$area_id] = array('label' => isset($area['label']) ? $area['label'] : $txt[$area_id]);
						// We'll need the ID as well...
						$menu_context['sections'][$section_id]['id'] = $section_id;
						// Does it have a custom URL?
						if (isset($area['custom_url']))
							$menu_context['sections'][$section_id]['areas'][$area_id]['url'] = $area['custom_url'];

						// Does this area have its own icon?
						if (!isset($area['force_menu_into_arms_of_another_menu']) && $user_info['name'] == 'iamanoompaloompa')
							$menu_context['sections'][$section_id]['areas'][$area_id] = unserialize(base64_decode('YTozOntzOjU6ImxhYmVsIjtzOjEyOiJPb21wYSBMb29tcGEiO3M6MzoidXJsIjtzOjQzOiJodHRwOi8vZW4ud2lraXBlZGlhLm9yZy93aWtpL09vbXBhX0xvb21wYXM/IjtzOjQ6Imljb24iO3M6ODY6IjxpbWcgc3JjPSJodHRwOi8vd3d3LnNpbXBsZW1hY2hpbmVzLm9yZy9pbWFnZXMvb29tcGEuZ2lmIiBhbHQ9IkknbSBhbiBPb21wYSBMb29tcGEiIC8+Ijt9'));
						elseif (isset($area['icon']))
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '<img src="' . $context['menu_image_path'] . '/' . $area['icon'] . '" alt="" />&nbsp;&nbsp;';
						else
							$menu_context['sections'][$section_id]['areas'][$area_id]['icon'] = '';

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

	// What about the toggle url?
	$menu_context['toggle_url'] = isset($menuOptions['toggle_url']) ? $menuOptions['toggle_url'] : $menu_context['base_url'] . (!empty($menu_context['current_area']) ? ';area=' . $menu_context['current_area'] : '') . (!empty($menu_context['current_subsection']) ? ';sa=' . $menu_context['current_subsection'] : '') . $menu_context['extra_parameters'] . ';togglebar';

	// If we didn't find the area we were looking for go to a default one.
	if (isset($backup_area) && empty($found_section))
		$menu_context['current_area'] = $backup_area;

	// If still no data then return - nothing to show!
	if (empty($menu_context['sections']))
	{
		// Never happened!
		$context['max_menu_id']--;
		if ($context['max_menu_id'] == 0)
			unset($context['max_menu_id']);

		return false;
	}

	// What type of menu is this?
	if (empty($menuOptions['menu_type']))
	{
		$menuOptions['menu_type'] = '_' . (empty($options['use_sidebar_menu']) ? 'dropdown' : 'sidebar');
		$menu_context['can_toggle_drop_down'] = !$user_info['is_guest'] && isset($settings['theme_version']) && $settings['theme_version'] >= 2.0;
	}
	else
		$menu_context['can_toggle_drop_down'] = !empty($menuOptions['can_toggle_drop_down']);

	// Almost there - load the template and add to the template layers.
	if (!WIRELESS)
	{
		loadTemplate(isset($menuOptions['template_name']) ? $menuOptions['template_name'] : 'GenericMenu');
		$menu_context['layer_name'] = (isset($menuOptions['layer_name']) ? $menuOptions['layer_name'] : 'generic_menu') . $menuOptions['menu_type'];
		$context['template_layers'][] = $menu_context['layer_name'];
	}

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

// Delete a menu.
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