<?php

/**
 * Helper file for handling themes.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

use SMF\Config;
use SMF\Lang;
use SMF\Utils;
use SMF\Db\DatabaseApi as Db;
use SMF\PackageManager\SubsPackage;
use SMF\PackageManager\XmlArray;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Gets a single theme's info.
 *
 * @param int $id The theme ID to get the info from.
 * @param string[] $variables
 * @return array The theme info as an array.
 */
function get_single_theme($id, array $variables = array())
{
	// No data, no fun!
	if (empty($id))
		return false;

	// Make sure $id is an int.
	$id = (int) $id;

	// Make changes if you really want it.
	call_integration_hook('integrate_get_single_theme', array(&$variables, $id));

	$single = array(
		'id' => $id,
	);

	// Make our known/enable themes a little easier to work with.
	$knownThemes = !empty(Config::$modSettings['knownThemes']) ? explode(',', Config::$modSettings['knownThemes']) : array();
	$enableThemes = !empty(Config::$modSettings['enableThemes']) ? explode(',', Config::$modSettings['enableThemes']) : array();

	$request = Db::$db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme = ({int:id_theme})
			AND id_member = {int:no_member}' . (!empty($variables) ? '
			AND variable IN ({array_string:variables})' : ''),
		array(
			'variables' => $variables,
			'id_theme' => $id,
			'no_member' => 0,
		)
	);

	while ($row = Db::$db->fetch_assoc($request))
	{
		$single[$row['variable']] = $row['value'];

		// Fix the path and tell if it's a valid one.
		if ($row['variable'] == 'theme_dir')
		{
			$single['theme_dir'] = realpath($row['value']);
			$single['valid_path'] = file_exists($row['value']) && is_dir($row['value']);
		}
	}

	// Is this theme installed and enabled?
	$single['known'] = in_array($single['id'], $knownThemes);
	$single['enable'] = in_array($single['id'], $enableThemes);

	// It should at least return if the theme is a known one or if its enable.
	return $single;
}

/**
 * Loads and returns all installed themes.
 *
 * Stores all themes on Utils::$context['themes'] for easier use.
 *
 * Config::$modSettings['knownThemes'] stores themes that the user is able to select.
 *
 * @param bool $enable_only Whether to fetch only enabled themes. Default is false.
 */
function get_all_themes($enable_only = false)
{
	// Make our known/enable themes a little easier to work with.
	$knownThemes = !empty(Config::$modSettings['knownThemes']) ? explode(',', Config::$modSettings['knownThemes']) : array();
	$enableThemes = !empty(Config::$modSettings['enableThemes']) ? explode(',', Config::$modSettings['enableThemes']) : array();

	// List of all possible themes values.
	$themeValues = array(
		'theme_dir',
		'images_url',
		'theme_url',
		'name',
		'theme_layers',
		'theme_templates',
		'version',
		'install_for',
		'based_on',
	);

	// Make changes if you really want it.
	call_integration_hook('integrate_get_all_themes', array(&$themeValues, $enable_only));

	// So, what is it going to be?
	$query_where = $enable_only ? $enableThemes : $knownThemes;

	// Perform the query as requested.
	$request = Db::$db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({array_string:theme_values})
			AND id_theme IN ({array_string:query_where})
			AND id_member = {int:no_member}',
		array(
			'query_where' => $query_where,
			'theme_values' => $themeValues,
			'no_member' => 0,
		)
	);

	Utils::$context['themes'] = array();

	while ($row = Db::$db->fetch_assoc($request))
	{
		if (!isset(Utils::$context['themes'][$row['id_theme']]))
			Utils::$context['themes'][$row['id_theme']] = array(
				'id' => (int) $row['id_theme'],
				'known' => in_array($row['id_theme'], $knownThemes),
				'enable' => in_array($row['id_theme'], $enableThemes)
			);

		// Fix the path and tell if it's a valid one.
		if ($row['variable'] == 'theme_dir')
		{
			$row['value'] = realpath($row['value']);
			Utils::$context['themes'][$row['id_theme']]['valid_path'] = file_exists($row['value']) && is_dir($row['value']);
		}
		Utils::$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}

	Db::$db->free_result($request);
}

/**
 * Loads and returns all installed themes.
 *
 * Stores all themes on Utils::$context['themes'] for easier use.
 *
 * Config::$modSettings['knownThemes'] stores themes that the user is able to select.
 */
function get_installed_themes()
{
	// Make our known/enable themes a little easier to work with.
	$knownThemes = !empty(Config::$modSettings['knownThemes']) ? explode(',', Config::$modSettings['knownThemes']) : array();
	$enableThemes = !empty(Config::$modSettings['enableThemes']) ? explode(',', Config::$modSettings['enableThemes']) : array();

	// List of all possible themes values.
	$themeValues = array(
		'theme_dir',
		'images_url',
		'theme_url',
		'name',
		'theme_layers',
		'theme_templates',
		'version',
		'install_for',
		'based_on',
	);

	// Make changes if you really want it.
	call_integration_hook('integrate_get_installed_themes', array(&$themeValues));

	// Perform the query as requested.
	$request = Db::$db->query('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({array_string:theme_values})
			AND id_member = {int:no_member}',
		array(
			'theme_values' => $themeValues,
			'no_member' => 0,
		)
	);

	Utils::$context['themes'] = array();

	while ($row = Db::$db->fetch_assoc($request))
	{
		if (!isset(Utils::$context['themes'][$row['id_theme']]))
			Utils::$context['themes'][$row['id_theme']] = array(
				'id' => (int) $row['id_theme'],
				'known' => in_array($row['id_theme'], $knownThemes),
				'enable' => in_array($row['id_theme'], $enableThemes)
			);

		// Fix the path and tell if it's a valid one.
		if ($row['variable'] == 'theme_dir')
		{
			$row['value'] = realpath($row['value']);
			Utils::$context['themes'][$row['id_theme']]['valid_path'] = file_exists($row['value']) && is_dir($row['value']);
		}
		Utils::$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}

	Db::$db->free_result($request);
}

/**
 * Reads an .xml file and returns the data as an array
 *
 * Removes the entire theme if the .xml file couldn't be found or read.
 *
 * @param string $path The absolute path to the xml file.
 * @return array An array with all the info extracted from the xml file.
 */
function get_theme_info($path)
{
	if (empty($path))
		return false;

	$xml_data = array();

	// Perhaps they are trying to install a mod, lets tell them nicely this is the wrong function.
	if (file_exists($path . '/package-info.xml'))
	{
		Lang::load('Errors');

		// We need to delete the dir otherwise the next time you try to install a theme you will get the same error.
		remove_dir($path);

		Lang::$txt['package_get_error_is_mod'] = str_replace('{MANAGEMODURL}', Config::$scripturl . '?action=admin;area=packages;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'], Lang::$txt['package_get_error_is_mod']);
		fatal_lang_error('package_theme_upload_error_broken', false, Lang::$txt['package_get_error_is_mod']);
	}

	// Parse theme-info.xml into an XmlArray.
	$theme_info_xml = new XmlArray(file_get_contents($path . '/theme_info.xml'));

	// Error message, there isn't any valid info.
	if (!$theme_info_xml->exists('theme-info[0]'))
	{
		remove_dir($path);
		fatal_lang_error('package_get_error_packageinfo_corrupt', false);
	}

	// Check for compatibility with 2.1 or greater.
	if (!$theme_info_xml->exists('theme-info/install'))
	{
		remove_dir($path);
		fatal_lang_error('package_get_error_theme_not_compatible', false, SMF_FULL_VERSION);
	}

	// So, we have an install tag which is cool and stuff but we also need to check it and match your current SMF version...
	$the_version = SMF_VERSION;
	$install_versions = $theme_info_xml->fetch('theme-info/install/@for');

	// The theme isn't compatible with the current SMF version.
	if (!$install_versions || !SubsPackage::matchPackageVersion($the_version, $install_versions))
	{
		remove_dir($path);
		fatal_lang_error('package_get_error_theme_not_compatible', false, SMF_FULL_VERSION);
	}

	$theme_info_xml = $theme_info_xml->to_array('theme-info[0]');

	$xml_elements = array(
		'theme_layers' => 'layers',
		'theme_templates' => 'templates',
		'based_on' => 'based-on',
		'version' => 'version',
	);

	// Assign the values to be stored.
	foreach ($xml_elements as $var => $name)
		if (!empty($theme_info_xml[$name]))
			$xml_data[$var] = $theme_info_xml[$name];

	// Add the supported versions.
	$xml_data['install_for'] = $install_versions;

	// Overwrite the default images folder.
	if (!empty($theme_info_xml['images']))
	{
		$xml_data['images_url'] = $path . '/' . $theme_info_xml['images'];
		$xml_data['explicit_images'] = true;
	}
	else
		$xml_data['explicit_images'] = false;

	if (!empty($theme_info_xml['extra']))
		$xml_data += Utils::jsonDecode($theme_info_xml['extra'], true);

	return $xml_data;
}

/**
 * Inserts a theme's data to the DataBase.
 *
 * Ends execution with fatal_lang_error() if an error appears.
 *
 * @param array $to_install An array containing all values to be stored into the DB.
 * @return int The newly created theme ID.
 */
function theme_install($to_install = array())
{
	global $settings;

	// External use? no problem!
	if (!empty($to_install))
		Utils::$context['to_install'] = $to_install;

	// One last check.
	if (empty(Utils::$context['to_install']['theme_dir']) || basename(Utils::$context['to_install']['theme_dir']) == 'Themes')
		fatal_lang_error('theme_install_invalid_dir', false);

	// OK, is this a newer version of an already installed theme?
	if (!empty(Utils::$context['to_install']['version']))
	{
		$request = Db::$db->query('', '
			SELECT id_theme
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable = {literal:name}
				AND value LIKE {string:name_value}
			LIMIT 1',
			array(
				'no_member' => 0,
				'name_value' => '%' . Utils::$context['to_install']['name'] . '%',
			)
		);

		list ($id_to_update) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		$to_update = get_single_theme($id_to_update, array('version'));

		// Got something, lets figure it out what to do next.
		if (!empty($id_to_update) && !empty($to_update['version']))
			switch (SubsPackage::compareVersions(Utils::$context['to_install']['version'], $to_update['version']))
			{
				case 1: // Got a newer version, update the old entry.
					Db::$db->query('', '
						UPDATE {db_prefix}themes
						SET value = {string:new_value}
						WHERE variable = {literal:version}
							AND id_theme = {int:id_theme}',
						array(
							'new_value' => Utils::$context['to_install']['version'],
							'id_theme' => $id_to_update,
						)
					);

					// Done with the update, tell the user about it.
					Utils::$context['to_install']['updated'] = true;

					return $id_to_update;

				case 0: // This is exactly the same theme.
				case -1: // The one being installed is older than the one already installed.
				default: // Any other possible result.
					fatal_lang_error('package_get_error_theme_no_new_version', false, array(Utils::$context['to_install']['version'], $to_update['version']));
			}
	}

	if (!empty(Utils::$context['to_install']['based_on']))
	{
		// No need for elaborated stuff when the theme is based on the default one.
		if (Utils::$context['to_install']['based_on'] == 'default')
		{
			Utils::$context['to_install']['theme_url'] = $settings['default_theme_url'];
			Utils::$context['to_install']['images_url'] = $settings['default_images_url'];
		}

		// Custom theme based on another custom theme, lets get some info.
		elseif (Utils::$context['to_install']['based_on'] != '')
		{
			Utils::$context['to_install']['based_on'] = preg_replace('~[^A-Za-z0-9\-_ ]~', '', Utils::$context['to_install']['based_on']);

			// Get the theme info first.
			$request = Db::$db->query('', '
				SELECT id_theme
				FROM {db_prefix}themes
				WHERE id_member = {int:no_member}
					AND (value LIKE {string:based_on} OR value LIKE {string:based_on_path})
				LIMIT 1',
				array(
					'no_member' => 0,
					'based_on' => '%/' . Utils::$context['to_install']['based_on'],
					'based_on_path' => '%' . "\\" . Utils::$context['to_install']['based_on'],
				)
			);

			list ($id_based_on) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
			$temp = get_single_theme($id_based_on, array('theme_dir', 'images_url', 'theme_url'));

			// Found the based on theme info, add it to the current one being installed.
			if (!empty($temp))
			{
				Utils::$context['to_install']['base_theme_url'] = $temp['theme_url'];
				Utils::$context['to_install']['base_theme_dir'] = $temp['theme_dir'];

				if (empty(Utils::$context['to_install']['explicit_images']) && !empty(Utils::$context['to_install']['base_theme_url']))
					Utils::$context['to_install']['theme_url'] = Utils::$context['to_install']['base_theme_url'];
			}

			// Nope, sorry, couldn't find any theme already installed.
			else
				fatal_lang_error('package_get_error_theme_no_based_on_found', false, Utils::$context['to_install']['based_on']);
		}

		unset(Utils::$context['to_install']['based_on']);
	}

	// Find the newest id_theme.
	$result = Db::$db->query('', '
		SELECT MAX(id_theme)
		FROM {db_prefix}themes',
		array(
		)
	);
	list ($id_theme) = Db::$db->fetch_row($result);
	Db::$db->free_result($result);

	// This will be theme number...
	$id_theme++;

	// Last minute changes? although, the actual array is a context value you might want to use the new ID.
	call_integration_hook('integrate_theme_install', array(&Utils::$context['to_install'], $id_theme));

	$inserts = array();
	foreach (Utils::$context['to_install'] as $var => $val)
		$inserts[] = array($id_theme, $var, $val);

	if (!empty($inserts))
		Db::$db->insert('insert',
			'{db_prefix}themes',
			array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			$inserts,
			array('id_theme', 'variable')
		);

	// Update the known and enable Theme's settings.
	$known = strtr(Config::$modSettings['knownThemes'] . ',' . $id_theme, array(',,' => ','));
	$enable = strtr(Config::$modSettings['enableThemes'] . ',' . $id_theme, array(',,' => ','));
	Config::updateModSettings(array('knownThemes' => $known, 'enableThemes' => $enable));

	return $id_theme;
}

/**
 * Removes a directory from the themes dir.
 *
 * This is a recursive function, it will call itself if there are subdirs inside the main directory.
 *
 * @param string $path The absolute path to the directory to be removed
 * @return bool true when success, false on error.
 */
function remove_dir($path)
{
	if (empty($path))
		return false;

	if (is_dir($path))
	{
		$objects = scandir($path);

		foreach ($objects as $object)
			if ($object != '.' && $object != '..')
			{
				if (filetype($path . '/' . $object) == 'dir')
					remove_dir($path . '/' . $object);

				else
					unlink($path . '/' . $object);
			}
	}

	reset($objects);
	rmdir($path);
}

/**
 * Removes a theme from the DB, includes all possible places where the theme might be used.
 *
 * @param int $themeID The theme ID
 * @return bool true when success, false on error.
 */
function remove_theme($themeID)
{
	// Can't delete the default theme, sorry!
	if (empty($themeID) || $themeID == 1)
		return false;

	$known = explode(',', Config::$modSettings['knownThemes']);
	$enable = explode(',', Config::$modSettings['enableThemes']);

	// Remove it from the themes table.
	Db::$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}',
		array(
			'current_theme' => $themeID,
		)
	);

	// Update users preferences.
	Db::$db->query('', '
		UPDATE {db_prefix}members
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $themeID,
		)
	);

	// Some boards may have it as preferred theme.
	Db::$db->query('', '
		UPDATE {db_prefix}boards
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $themeID,
		)
	);

	// Remove it from the list of known themes.
	$known = array_diff($known, array($themeID));

	// And the enable list too.
	$enable = array_diff($enable, array($themeID));

	// Back to good old comma separated string.
	$known = strtr(implode(',', $known), array(',,' => ','));
	$enable = strtr(implode(',', $enable), array(',,' => ','));

	// Update the enableThemes list.
	Config::updateModSettings(array('enableThemes' => $enable, 'knownThemes' => $known));

	// Fix it if the theme was the overall default theme.
	if (Config::$modSettings['theme_guests'] == $themeID)
		Config::updateModSettings(array('theme_guests' => '1'));

	return true;
}

/**
 * Generates a file listing for a given directory
 *
 * @param string $path The full path to the directory
 * @param string $relative The relative path (relative to the Themes directory)
 * @return array An array of information about the files and directories found
 */
function get_file_listing($path, $relative)
{
	// Is it even a directory?
	if (!is_dir($path))
		fatal_lang_error('error_invalid_dir', 'critical');

	$dir = dir($path);
	$entries = array();
	while ($entry = $dir->read())
		$entries[] = $entry;
	$dir->close();

	natcasesort($entries);

	$listing1 = array();
	$listing2 = array();

	foreach ($entries as $entry)
	{
		// Skip all dot files, including .htaccess.
		if (substr($entry, 0, 1) == '.' || $entry == 'CVS')
			continue;

		if (is_dir($path . '/' . $entry))
			$listing1[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => Config::$scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;directory=' . $relative . $entry,
				'size' => '',
			);
		else
		{
			$size = filesize($path . '/' . $entry);
			if ($size > 2048 || $size == 1024)
				$size = Lang::numberFormat($size / 1024) . ' ' . Lang::$txt['themeadmin_edit_kilobytes'];
			else
				$size = Lang::numberFormat($size) . ' ' . Lang::$txt['themeadmin_edit_bytes'];

			$listing2[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => false,
				'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
				'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png)$~', $entry) != 0,
				'is_editable' => is_writable($path . '/' . $entry) && preg_match('~\.(php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py)$~', $entry) != 0,
				'href' => Config::$scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => timeformat(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

?>