<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

/**
 * Represents a menu, such as the admin menu or profile menu.
 *
 * The $data parameter for the constructor is array of sections, which contain
 * areas, which can contain subsections. The properties for each are as follows:
 *
 *   For Sections:
 *
 *     string title:         Section title.
 *
 *     bool   enabled:       Should this section be accessible?
 *
 *     array  areas:         Array of areas within this section. (See below.)
 *
 *     array  permission:    Permission required to access the whole section.
 *
 *   For Areas:
 *
 *     string label:         Optional text string for link. If this is not set,
 *                           Lang::$txt[$area_id] will be used.
 *
 *     string file:          Name of source file required for this area.
 *
 *     string function:      Function to call when area is selected.
 *
 *     string custom_url:    URL to use for this menu item.
 *
 *     bool   enabled:       Should this area be accessible?
 *
 *     bool   hidden:        Should this area be visible? (Used when an area is
 *                           accessible but should not be shown.)
 *
 *     string select:        If set this item will not be displayed. Instead the
 *                           item indexed here will be.
 *
 *     array  subsections:   Array of subsections from this area. (See below.)
 *
 *     array  permission:    Array of permissions to determine who can access
 *                           this area.
 *
 * 	For Subsections:
 *
 *     string label:         Text label for this subsection.
 *
 *     array  permission:    Array of permissions to check for this subsection.
 *
 *     bool   enabled:       Should this subsection be accessible?
 *
 *     bool   is_default:    Is this the default subaction? If no subsection is
 *                           set as the default, the first one will be used.
 *
 *
 * The $options parameter for the constructor is an array that can contain some
 * some combination of the following:
 *
 *     string action:        The action for this menu. If this is not set, it
 *                           will be determined automatically.
 *
 *     string current_area:  The currently selected area. If this is not set, it
 *                           will be determined automatically.
 *
 *     string base_url:      The base URL for items in this menu. If this is not
 *                           set, it will be determined automatically.
 *
 *     string template_name: The theme template to load for this menu.
 *
 *     string layer_name:    The template layer to load for this menu.
 *
 *     bool   do_big_icons:  If true, get large icons for admin home page.
 *
 *     array  extra_url_parameters:
 *                           Key-value pairs of extra parameters to append to
 *                           the menu item URLs.
 *
 *     bool   disable_url_session_check:
 *                           If true, does not append the session parameter to
 *                           the menu item URLs.
 *
 *     bool   disable_hook_call:
 *                           If true, skips the integrate_{action}_areas hook.
 */
class Menu implements \ArrayAccess
{
	use BackwardCompatibility;
	use ArrayAccessHelper;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'create' => 'createMenu',
			'destroy' => 'destroyMenu',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var int
	 *
	 * ID number of this menu.
	 */
	public int $id;

	/**
	 * @var string
	 *
	 * Generic name of this menu.
	 * Simply takes the form: 'menu_data_' . $id
	 * Used to provide a unique key in Utils::$context.
	 */
	public string $name = '';

	/**
	 * @var string
	 *
	 * Base URL for the menu's buttons.
	 */
	public string $base_url;

	/**
	 * @var string
	 *
	 * The action that the user is currently viewing.
	 *
	 * This corresponds to the 'action=...' URL parameter.
	 */
	public string $current_action = '';

	/**
	 * @var string
	 *
	 * The section of the menu that the current area is in.
	 *
	 * This does not correspond to an URL parameter. It is just a way of
	 * organizing the areas within the action.
	 */
	public string $current_section = '';

	/**
	 * @var string
	 *
	 * The area within the action that the user is currently viewing.
	 *
	 * This corresponds to the 'area=...' URL parameter.
	 */
	public string $current_area = '';

	/**
	 * @var string
	 *
	 * The subsection within the area that the user is currently viewing.
	 *
	 * This corresponds to the 'sa=...' URL parameter.
	 */
	public string $current_subsection = '';

	/**
	 * @var string
	 *
	 * Extra URL parameters that should be appended to menu item links.
	 *
	 * Typically this contains the session parameter, if anything.
	 */
	public string $extra_parameters = '';

	/**
	 * @var string
	 *
	 * The theme template to load for this menu.
	 */
	public string $template_name;

	/**
	 * @var string
	 *
	 * The template layer to load for this menu.
	 */
	public string $layer_name;

	/**
	 * @var array
	 *
	 * The constructed hierarchical menu data.
	 */
	public array $sections = [];

	/**
	 * @var array
	 *
	 * Data about files to include, functions to call, etc., in order to make
	 * the current area work.
	 */
	public array $include_data = [];

	/**
	 * @var array
	 *
	 * A set of tab buttons to show in a secondary menu below the main menu.
	 *
	 * This is intended for more complex menus.
	 * The tab buttons typically (but not always) correspond to the menu items
	 * in the current subsection.
	 */
	public array $tab_data = [];

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var int
	 *
	 * The highest $id value that has been assigned to a menu thus far.
	 */
	public static int $max_id = 0;

	/**
	 * @var array
	 *
	 * All loaded instances of this class.
	 * Keys are action names.
	 */
	public static array $loaded = [];

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var array
	 *
	 * Holds the $data parameter passed to the constructor.
	 */
	protected array $data = [];

	/**
	 * @var array
	 *
	 * Holds the $options parameter passed to the constructor.
	 */
	protected array $options = [];

	/**
	 * @var bool
	 *
	 * Whether we have found the current section yet.
	 *
	 * Used while we are building the menu's sections.
	 */
	protected bool $found_section = false;

	/**
	 * @var string
	 *
	 * Fallback area to show if $current_area can't be shown.
	 */
	protected string $backup_area;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * @param array $data An array of menu data.
	 * @param array $options An array of menu options.
	 */
	public function __construct(array $data, array $options = [])
	{
		// Let other methods access the passed data and options.
		$this->data = $data;
		$this->options = $options;
		unset($data, $options);

		// What is the general action of this menu? (i.e. Config::$scripturl?action=XXXX)
		$this->current_action = $this->options['action'] ?? Utils::$context['current_action'];

		// Every menu gets a unique ID, these are shown in first in, first out order.
		$this->id = ++self::$max_id;
		$this->name = 'menu_data_' . $this->id;

		// In most cases, referring to a menu by the associated action is easiest.
		self::$loaded[$this->current_action] = $this;

		/*
		 * Allow extending *any* menu with a single hook.
		 *
		 * For the sake of people searching for specific hooks, here are some common examples:
		 *		integrate_moderate_areas
		 *		integrate_pm_areas
		 */
		if (!empty($this->current_action) && empty($this->options['disable_hook_call'])) {
			IntegrationHook::call('integrate_' . $this->current_action . '_areas', [&$this->data]);
		}

		// Should we use a custom base url, or use the default?
		$this->base_url = $this->options['base_url'] ?? Config::$scripturl . '?action=' . $this->current_action;

		// What is the current area selected?
		if (isset($this->options['current_area']) || isset($_GET['area'])) {
			$this->current_area = $this->options['current_area'] ?? $_GET['area'];
		}

		$this->buildExtraParameters();

		// Now setup the context correctly.
		foreach ($this->data as $section_id => $section) {
			$this->section_id = $section_id;
			$this->buildSection($section);
		}

		// If still no data then return - nothing to show!
		if (empty($this->sections)) {
			// Never happened!
			if (--self::$max_id == 0) {
				unset(Utils::$context['max_menu_id'], $this->data, $this->options);
			}

			$this->include_data = [];

			return;
		}

		// If we didn't find the area we were looking for go to a default one.
		if (isset($this->backup_area) && empty($this->found_section)) {
			$this->current_area = $this->backup_area;
		}

		$this->setSelected();
		$this->checkBaseUrl();

		// Almost there - load the template and add to the template layers.
		Theme::loadTemplate($this->options['template_name'] ?? 'GenericMenu');

		$this->layer_name = ($this->options['layer_name'] ?? 'generic_menu') . '_dropdown';

		Utils::$context['template_layers'][] = $this->layer_name;

		// We're done with these.
		unset($this->data, $this->options);

		// Check we had something - for sanity sake.
		if (empty($this->include_data)) {
			return;
		}

		// Finally - return information on the selected item.
		$this->include_data += [
			'current_action' => $this->current_action,
			'current_section' => $this->current_section,
			'current_area' => $this->current_area,
			'current_subsection' => $this->current_subsection,
		];

		// Backward compatibility...
		Utils::$context['max_menu_id'] = &self::$max_id;
		Utils::$context[$this->name] = $this;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @param array $data An array of menu data.
	 * @param array $options An array of menu options.
	 * @return array|false Info about the selected menu item, or false if nothing to show.
	 */
	public static function create(array $data, array $options = []): array|false
	{
		$menu = new self($data, $options);

		return empty($menu->include_data) ? false : $menu->include_data;
	}

	/**
	 * Delete a menu.
	 *
	 * @param int|string $id The ID of a menu, or 'last' for the most recent one.
	 */
	public static function destroy(int|string $id = 'last'): void
	{
		if ($id === 'last') {
			$id = self::$max_id;
		}

		if (!is_int($id)) {
			$to_delete = $id;
		} else {
			foreach (self::$loaded as $action => $menu) {
				if ($menu->id == $id) {
					$to_delete = $action;
				}
			}
		}

		if (!isset(self::$loaded[$to_delete])) {
			return;
		}

		if (isset(self::$loaded[$to_delete]->layer_name)) {
			Utils::$context['template_layers'] = array_diff(Utils::$context['template_layers'], [self::$loaded[$to_delete]->layer_name]);
		}

		unset(Utils::$context[self::$loaded[$to_delete]->name], self::$loaded[$to_delete]);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Build a list of additional parameters that should go in the URL.
	 */
	protected function buildExtraParameters(): void
	{
		if (!empty($this->options['extra_url_parameters'])) {
			foreach ($this->options['extra_url_parameters'] as $key => $value) {
				$this->extra_parameters .= ';' . $key . '=' . $value;
			}
		}

		// Only include the session ID in the URL if it's strictly necessary.
		if (empty($this->options['disable_url_session_check'])) {
			$this->extra_parameters .= ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		}
	}

	/**
	 * Checks whether the given menu item is enabled and whether the current
	 * user has permission to access it.
	 */
	protected function enabledAndAllowed($menu_item): bool
	{
		if (isset($menu_item['enabled']) && $menu_item['enabled'] == false) {
			return false;
		}

		return !(isset($menu_item['permission']) && !User::$me->allowedTo($menu_item['permission']));
	}

	/**
	 * Build the data array for a section of the menu.
	 */
	protected function buildSection($section): void
	{
		// Is this enabled - or has as permission check - which fails?
		if (!$this->enabledAndAllowed($section)) {
			return;
		}

		$this->sections[$this->section_id] = [
			'id' => $this->section_id,
			'title' => $section['title'],
			'amt' => $section['amt'] ?? null,
			'areas' => [],
			'selected' => false,
		];

		// Now we cycle through the sections to pick the right area.
		foreach ($section['areas'] as $area_id => $area) {
			$this->area_id = $area_id;
			$this->buildArea($area);
		}

		// Delete the section if it contains no visible areas.
		if (empty($this->sections[$this->section_id]['areas'])) {
			unset($this->sections[$this->section_id]);
		}
	}

	/**
	 * Build the data array for an area of the menu.
	 */
	protected function buildArea($area): void
	{
		// Can we do this?
		if (!$this->enabledAndAllowed($area)) {
			return;
		}

		if (!isset($area['label']) && (!isset(Lang::$txt[$this->area_id]) || isset($area['select']))) {
			$this->setCurrentSectionAndArea();

			return;
		}

		// If we haven't got an area then the first valid one is our choice.
		if (empty($this->current_area)) {
			$this->current_area = $this->area_id;
		}

		// If this is hidden from view don't do the rest.
		if (!empty($area['hidden'])) {
			$this->setCurrentSectionAndArea();

			return;
		}

		// Define the new area.
		$this->sections[$this->section_id]['areas'][$this->area_id] = [
			'id' => $this->area_id,
			'label' => $area['label'] ?? (Lang::$txt[$this->area_id] ?? $this->area_id),
			'url' => $area['custom_url'] ?? $this->base_url . ';area=' . $this->area_id,
			'amt' => $area['amt'] ?? null,
			'subsections' => [],
			'selected' => false,
			// Some areas may be listed but not active, which we show as greyed out.
			'inactive' => !empty($area['inactive']),
			// This will usually change when we build the subsections.
			'hide_subsections' => true,
		];

		// A reference to keep things legible.
		$this_area = &$this->sections[$this->section_id]['areas'][$this->area_id];

		// Does this area have its own icon?
		$this->setAreaIcon($area);

		// Did it have subsections?
		if (!empty($area['subsections'])) {
			foreach ($area['subsections'] as $sa => $subsection) {
				$this->subsection_id = $sa;
				$this->buildSubsection($subsection);
			}

			// If permissions removed/disabled for all submenu items, remove the menu item
			if (empty($this_area['subsections'])) {
				unset($this_area, $this->sections[$this->section_id]['areas'][$this->area_id]);

				return;
			}

			// Set which one is first, last, and selected in the group.
			$first_sa = array_key_first($this_area['subsections']);
			$last_sa = array_key_last($this_area['subsections']);

			$this_area['subsections'][Utils::$context['right_to_left'] ? $last_sa : $first_sa]['is_first'] = true;

			$this_area['subsections'][Utils::$context['right_to_left'] ? $first_sa : $last_sa]['is_last'] = true;

			if ($this->current_area == $this->area_id && empty($this->current_subsection)) {
				$this->current_subsection = $first_sa;
			}
		}

		$this->setCurrentSectionAndArea();
	}

	/**
	 * Build the data array for a subsection of the menu.
	 */
	protected function buildSubsection($subsection): void
	{
		$this_area = &$this->sections[$this->section_id]['areas'][$this->area_id];

		// In SMF 2.x, the subsection label and permission keys were just 0 and 1.
		if (!isset($subsection['label']) && !empty($subsection[0])) {
			$subsection['label'] = $subsection[0];
		}

		if (!isset($subsection['permission']) && !empty($subsection[1])) {
			$subsection['permission'] = $subsection[1];
		}

		if (!isset($subsection['is_default']) && !empty($subsection[2])) {
			$subsection['is_default'] = $subsection[2];
		}

		// Define the new subsection.
		$this_area['subsections'][$this->subsection_id] = [
			'id' => $this->subsection_id,
			'label' => $subsection['label'],
			'url' => $subsection['url'] ?? $this->base_url . ';area=' . $this->area_id . ';sa=' . $this->subsection_id,
			'amt' => $subsection['amt'] ?? null,
			'selected' => false,
		];

		// Another reference to keep things legible.
		$this_subsection = &$this_area['subsections'][$this->subsection_id];

		// If not enabled, mark it as disabled...
		if (!$this->enabledAndAllowed($subsection)) {
			$this_subsection['disabled'] = true;

			return;
		}

		// A bit complicated - but is this set?
		if ($this->current_area == $this->area_id) {
			// Is this the current subsection?
			if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == $this->subsection_id) {
				$this->current_subsection = $this->subsection_id;
			}
			// Otherwise is it the default?
			elseif (empty($this->current_subsection) && !empty($subsection['is_default'])) {
				$this->current_subsection = $this->subsection_id;
			}
		}

		// At this point, we know at least one subsection is visible.
		$this_area['hide_subsections'] = false;
	}

	/**
	 * Figures out which section and area the user is currently viewing.
	 */
	protected function setCurrentSectionAndArea(): void
	{
		$area = $this->data[$this->section_id]['areas'][$this->area_id];

		// Is this the current section?
		if (!empty($this->current_area) && $this->current_area == $this->area_id && empty($this->found_section)) {
			// Only do this once?
			$this->found_section = true;

			// Update the context if required - as we can have areas pretending to be others. ;)
			$this->current_section = $this->section_id;
			$this->current_area = $area['select'] ?? $this->area_id;

			// This will be the data we return.
			$this->include_data = $area;

			if (isset($this->sections[$this->section_id]['areas'][$this->area_id]['subsections'])) {
				$this->include_data['subsections'] = $this->sections[$this->section_id]['areas'][$this->area_id]['subsections'];
			}
		}
		// Make sure we have something in case it's an invalid area.
		elseif (empty($this->found_section) && empty($this->include_data)) {
			$this->current_section = $this->section_id;
			$this->backup_area = $area['select'] ?? $this->area_id;
			$this->include_data = $area;

			if (isset($this->sections[$this->section_id]['areas'][$this->area_id]['subsections'])) {
				$this->include_data['subsections'] = $this->sections[$this->section_id]['areas'][$this->area_id]['subsections'];
			}
		}
	}

	/**
	 * Sets the icon for an area.
	 */
	protected function setAreaIcon($area): void
	{
		$dirs = ['theme_dir' => 'images_url', 'default_theme_dir' => 'default_images_url'];
		$icon_paths = ['icon' => 'admin'];

		// Big icons are for the admin home page.
		if (!empty($this->options['do_big_icons'])) {
			$icon_paths['icon_file'] = 'admin/big';
		}

		// For convenience.
		$this_area = &$this->sections[$this->section_id]['areas'][$this->area_id];

		// Default icon name is the area's ID string.
		$area['icon'] = $area['icon'] ?? $this->area_id;

		// Icon is a file name.
		if (($ext = pathinfo($area['icon'], PATHINFO_EXTENSION)) !== '') {
			$no_ext = str_replace('.' . $ext, '', $area['icon']);

			// The icon_class never uses the extension.
			$this_area['icon_class'] = $this->current_action . '_menu_icon ' . $no_ext;

			// Try to find the files.
			foreach ($dirs as $dir => $url) {
				foreach ($icon_paths as $key => $path) {
					if (file_exists(Theme::$current->settings[$dir] . '/images/' . $path . '/' . $area['icon'])) {
						$this_area[$key] = '<img src="' . Theme::$current->settings[$url] . '/' . $path . '/' . $area['icon'] . '" alt="">';
					}
				}
			}

			// File not found, so fall back to a class name.
			if (!isset($this_area['icon'])) {
				$this_area['icon'] = '<span class="main_icons ' . $no_ext . '"></span>';
			}
		}
		// Icon is a class name.
		else {
			$this_area['icon'] = '<span class="main_icons ' . $area['icon'] . '"></span>';
			$this_area['icon_class'] = $this->current_action . '_menu_icon ' . $area['icon'];
		}

		// This is a shortcut for Font-Icon users so they don't have to re-do whole CSS.
		$this_area['plain_class'] = !empty($area['icon']) ? $area['icon'] : '';
	}

	/**
	 * Figures out which section, area, and subsection are currently selected.
	 */
	protected function setSelected(): void
	{
		if (!empty($this->current_section) && isset($this->sections[$this->current_section])) {
			$this->sections[$this->current_section]['selected'] = true;
		}

		if (!empty($this->current_area) && isset($this->sections[$this->current_section]['areas'][$this->current_area])) {
			$this->sections[$this->current_section]['areas'][$this->current_area]['selected'] = true;
		}

		if (!empty($this->current_subsection) && isset($this->sections[$this->current_section]['areas'][$this->current_area]['subsections'][$this->current_subsection])) {
			$this->sections[$this->current_section]['areas'][$this->current_area]['subsections'][$this->current_subsection]['selected'] = true;
		}
	}

	/**
	 * Goes through all the sections to check if the base menu has an url.
	 */
	protected function checkBaseUrl(): void
	{
		foreach ($this->sections as $section_id => $section) {
			if (isset($section['url'])) {
				continue;
			}

			$first_area = reset($section['areas']);

			$this->sections[$section_id]['url'] = $first_area['url'] ?? $this->base_url . ';area=' . array_key_first($section['areas']);
		}
	}
}

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Menu::exportStatic')) {
	Menu::exportStatic();
}

?>