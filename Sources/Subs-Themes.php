<?php

/**
 * Helper file for handling themes.
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
 * Gets a single theme's info.
 *
 * @param int $id The theme ID to get the info from.
 * @return array The theme info as an array.
 */
function get_single_theme($id)
{
	global $smcFunc, $modSettings;

	// No data, no fun!
	if (empty($id))
		return false;

	// Make sure $id is an int.
	$id = (int) $id;

	// List of all possible  values.
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
	call_integration_hook('integrate_get_single_theme', array(&$themeValues, $id));

	$single = array(
		'id' => $id,
	);

	// Make our known/enable themes a little easier to work with.
	$knownThemes = !empty($modSettings['knownThemes']) ? explode(',', $modSettings['knownThemes']) : array();
	$enableThemes = !empty($modSettings['enableThemes']) ? explode(',', $modSettings['enableThemes']) : array();

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({array_string:theme_values})
			AND id_theme = ({int:id_theme})
			AND id_member = {int:no_member}',
		array(
			'theme_values' => $themeValues,
			'id_theme' => $id,
			'no_member' => 0,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$single[$row['variable']] = $row['value'];

		// Fix the path and tell if its a valid one.
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
 * Stores all themes on $context['themes'] for easier use.
 *
 * @param bool $enable_only false by default for getting all themes. If true the function will return all themes that are currently enable.
 * @return array With the theme's IDs as key.
 */
function get_all_themes($enable_only = false)
{
	global $modSettings, $context, $smcFunc;

	// Make our known/enable themes a little easier to work with.
	$knownThemes = !empty($modSettings['knownThemes']) ? explode(',', $modSettings['knownThemes']) : array();
	$enableThemes = !empty($modSettings['enableThemes']) ? explode(',', $modSettings['enableThemes']) : array();

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
	$request = $smcFunc['db_query']('', '
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

	$context['themes'] = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$context['themes'][$row['id_theme']]['id'] = (int) $row['id_theme'];

		// Fix the path and tell if its a valid one.
		if ($row['variable'] == 'theme_dir')
		{
			$context['themes'][$row['id_theme']][$row['variable']] = realpath($row['value']);
			$context['themes'][$row['id_theme']]['valid_path'] = file_exists(realpath($row['value'])) && is_dir(realpath($row['value']));
		}

		$context['themes'][$row['id_theme']]['known'] = in_array($row['id_theme'], $knownThemes);
		$context['themes'][$row['id_theme']]['enable'] = in_array($row['id_theme'], $enableThemes);
		$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}

	$smcFunc['db_free_result']($request);
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
	global $smcFunc, $sourcedir, $forum_version, $txt, $scripturl, $context;
	global $explicit_images;

	if (empty($path))
		return false;

	$xml_data = array();
	$explicit_images = false;

	// Perhaps they are trying to install a mod, lets tell them nicely this is the wrong function.
	if (file_exists($path . '/package-info.xml'))
	{
		loadLanguage('Errors');

		// We need to delete the dir otherwise the next time you try to install a theme you will get the same error.
		remove_dir($path);

		$txt['package_get_error_is_mod'] = str_replace('{MANAGEMODURL}', $scripturl . '?action=admin;area=packages;' . $context['session_var'] . '=' . $context['session_id'], $txt['package_get_error_is_mod']);
		fatal_lang_error('package_theme_upload_error_broken', false, $txt['package_get_error_is_mod']);
	}

	// Parse theme-info.xml into an xmlArray.
	require_once($sourcedir . '/Class-Package.php');
	$theme_info_xml = new xmlArray(file_get_contents($path . '/theme_info.xml'));

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
		fatal_lang_error('package_get_error_theme_not_compatible', false, $forum_version);
	}

	// So, we have an install tag which is cool and stuff but we also need to check it and match your current SMF version...
	$the_version = strtr($forum_version, array('SMF ' => ''));
	$install_versions = $theme_info_xml->path('theme-info/install/@for');

	// The theme isn't compatible with the current SMF version.
	if (!$install_versions || !matchPackageVersion($the_version, $install_versions))
	{
		remove_dir($path);
		fatal_lang_error('package_get_error_theme_not_compatible', false, $forum_version);
	}

	$theme_info_xml = $theme_info_xml->path('theme-info[0]');
	$theme_info_xml = $theme_info_xml->to_array();

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
		$explicit_images = true;
	}

	if (!empty($theme_info_xml['extra']))
		$xml_data += $smcFunc['json_decode']($theme_info_xml['extra'], true);

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
	global $smcFunc, $context, $modSettings;
	global $settings, $explicit_images;

	// External use? no problem!
	if ($to_install)
		$context['to_install'] = $to_install;

	// One last check.
	if (empty($context['to_install']['theme_dir']) || basename($context['to_install']['theme_dir']) == 'Themes')
		fatal_lang_error('theme_install_invalid_dir', false);

	// OK, is this a newer version of an already installed theme?
	if (!empty($context['to_install']['version']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable = {string:name}
				AND value LIKE {string:name_value}
			LIMIT 1',
			array(
				'no_member' => 0,
				'name' => 'name',
				'version' => 'version',
				'name_value' => '%' . $context['to_install']['name'] . '%',
			)
		);

		$to_update = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Got something, lets figure it out what to do next.
		if (!empty($to_update) && !empty($to_update['version']))
			switch (compareVersions($context['to_install']['version'], $to_update['version']))
			{
				case 1: // Got a newer version, update the old entry.
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}themes
						SET value = {string:new_value}
						WHERE variable = {string:version}
							AND id_theme = {int:id_theme}',
						array(
							'new_value' => $context['to_install']['version'],
							'version' => 'version',
							'id_theme' => $to_update['id_theme'],
						)
					);

					// Done with the update, tell the user about it.
					$context['to_install']['updated'] = true;

					return $to_update['id_theme'];
					break; // Just for reference.
				case 0: // This is exactly the same theme.
				case -1: // The one being installed is older than the one already installed.
				default: // Any other possible result.
					fatal_lang_error('package_get_error_theme_no_new_version', false, array($context['to_install']['version'], $to_update['version']));
			}
	}

	if (!empty($context['to_install']['based_on']))
	{
		// No need for elaborated stuff when the theme is based on the default one.
		if ($context['to_install']['based_on'] == 'default')
		{
			$context['to_install']['theme_url'] = $settings['default_theme_url'];
			$context['to_install']['images_url'] = $settings['default_images_url'];
		}

		// Custom theme based on another custom theme, lets get some info.
		elseif ($context['to_install']['based_on'] != '')
		{
			$context['to_install']['based_on'] = preg_replace('~[^A-Za-z0-9\-_ ]~', '', $context['to_install']['based_on']);

			// Get the theme info first.
			$request = $smcFunc['db_query']('', '
				SELECT id_theme
				FROM {db_prefix}themes
				WHERE id_member = {int:no_member}
					AND (value LIKE {string:based_on} OR value LIKE {string:based_on_path})
				LIMIT 1',
				array(
					'no_member' => 0,
					'based_on' => '%/' . $context['to_install']['based_on'],
					'based_on_path' => '%' . "\\" . $context['to_install']['based_on'],
				)
			);

			$based_on = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			$request = $smcFunc['db_query']('', '
				SELECT variable, value
				FROM {db_prefix}themes
				WHERE variable IN ({array_string:theme_values})
					AND id_theme = ({int:based_on})
				LIMIT 1',
				array(
					'no_member' => 0,
					'theme__values' => array('theme_url', 'images_url', 'theme_dir',),
					'based_on' => $based_on['id_theme'],
				)
			);
			$temp = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			// Found the based on theme info, add it to the current one being installed.
			if (is_array($temp))
			{
				$context['to_install']['base_theme_url'] = $temp['theme_url'];
				$context['to_install']['base_theme_dir'] = $temp['theme_dir'];

				if (empty($explicit_images) && !empty($context['to_install']['base_theme_url']))
					$context['to_install']['theme_url'] = $context['to_install']['base_theme_url'];
			}

			// Nope, sorry, couldn't find any theme already installed.
			else
				fatal_lang_error('package_get_error_theme_no_based_on_found', false, $context['to_install']['based_on']);
		}

		unset($context['to_install']['based_on']);
	}

	// Find the newest id_theme.
	$result = $smcFunc['db_query']('', '
		SELECT MAX(id_theme)
		FROM {db_prefix}themes',
		array(
		)
	);
	list ($id_theme) = $smcFunc['db_fetch_row']($result);
	$smcFunc['db_free_result']($result);

	// This will be theme number...
	$id_theme++;

	// Last minute changes? although, the actual array is a context value you might want to use the new ID.
	call_integration_hook('integrate_theme_install', array(&$context['to_install'], $id_theme));

	$inserts = array();
	foreach ($context['to_install'] as $var => $val)
		$inserts[] = array($id_theme, $var, $val);

	if (!empty($inserts))
		$smcFunc['db_insert']('insert',
			'{db_prefix}themes',
			array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
			$inserts,
			array('id_theme', 'variable')
		);

	// Update the known and enable Theme's settings.
	$known = strtr($modSettings['knownThemes'] . ',' . $id_theme, array(',,' => ','));
	$enable = strtr($modSettings['enableThemes'] . ',' . $id_theme, array(',,' => ','));
	updateSettings(array('knownThemes' => $known, 'enableThemes' => $enable));

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
	global $smcFunc, $modSettings;

	// Can't delete the default theme, sorry!
	if (empty($themeID) || $themeID == 1)
		return false;

	$known = explode(',', $modSettings['knownThemes']);
	$enable = explode(',', $modSettings['enableThemes']);

	// Remove it from the themes table.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}',
		array(
			'current_theme' => $themeID,
		)
	);

	// Update users preferences.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $themeID,
		)
	);

	// Some boards may have it as preferred theme.
	$smcFunc['db_query']('', '
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
	updateSettings(array('enableThemes' => $enable, 'knownThemes' => $known));

	// Fix it if the theme was the overall default theme.
	if ($modSettings['theme_guests'] == $themeID)
		updateSettings(array('theme_guests' => '1'));

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
	global $scripturl, $txt, $context;

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
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=' . $relative . $entry,
				'size' => '',
			);
		else
		{
			$size = filesize($path . '/' . $entry);
			if ($size > 2048 || $size == 1024)
				$size = comma_format($size / 1024) . ' ' . $txt['themeadmin_edit_kilobytes'];
			else
				$size = comma_format($size) . ' ' . $txt['themeadmin_edit_bytes'];

			$listing2[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => false,
				'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
				'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png)$~', $entry) != 0,
				'is_editable' => is_writable($path . '/' . $entry) && preg_match('~\.(php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py)$~', $entry) != 0,
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => timeformat(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

?>