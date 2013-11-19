<?php

/**
 * Helper file for handing themes.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 *
 * @copyright 2013 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...');


function get_single_theme($id)
{
	global $smcFunc, $context;

	// No data, no fun!
	if (empty($id))
		return false;

	$single = array(
		'id' => $id,
	);

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:theme_dir}, {string:theme_url}, {string:images_url}, {string:name}, {string:theme_layers}, {string:theme_templates}, {string:version}, {string:install_for}, {string:based_on}, {string:enable})
			AND id_theme = {int:id_theme}
			AND id_member = {int:no_member}',
		array(
			'id_theme' => $id,
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
			'images_url' => 'images_url',
			'theme_url' => 'theme_url',
			'name' => 'name',
			'theme_layers' => 'theme_layers',
			'theme_templates' => 'theme_templates',
			'version' => 'version',
			'install_for' => 'install_for',
			'based_on' => 'based_on',
			'enable' => 'enable',
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$single[$row['variable']] = $row['value'];

	return $single;
}

function get_all_themes()
{
	global $modSettings, $context, $smcFunc;

	// Make our known themes a little easier to work with.
	$knownThemes = !empty($modSettings['knownThemes']) ? explode(',',$modSettings['knownThemes']) : array();

	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:theme_dir}, {string:theme_url}, {string:images_url}, {string:name}, {string:theme_layers}, {string:theme_templates}, {string:version}, {string:install_for}, {string:based_on}, {string:enable})
			AND id_member = {int:no_member}',
		array(
			'no_member' => 0,
			'theme_dir' => 'theme_dir',
			'images_url' => 'images_url',
			'theme_url' => 'theme_url',
			'name' => 'name',
			'theme_layers' => 'theme_layers',
			'theme_templates' => 'theme_templates',
			'version' => 'version',
			'install_for' => 'install_for',
			'based_on' => 'based_on',
			'enable' => 'enable',
		)
	);
	$context['themes'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($context['themes'][$row['id_theme']]))
			$context['themes'][$row['id_theme']] = array(
				'id' => $row['id_theme'],
				'name' => $row['value'],
				'known' => in_array($row['id_theme'], $knownThemes),
			);
		$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}
	$smcFunc['db_free_result']($request);

	foreach ($context['themes'] as $i => $theme)
	{
		$context['themes'][$i]['theme_dir'] = realpath($context['themes'][$i]['theme_dir']);
		$context['themes'][$i]['valid_path'] = file_exists($context['themes'][$i]['theme_dir']) && is_dir($context['themes'][$i]['theme_dir']);
	}

	return $context['themes'];
}

function get_theme_info($path)
{
	global $sourcedir, $forum_version, $txt, $scripturl, $context;
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
		$xml_data += unserialize($theme_info_xml['extra']);

	return $xml_data;
}

function theme_install($to_install = array())
{
	global $smcFunc, $context, $themedir, $themeurl, $modSettings;
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
		$to_update = array();
		$request = $smcFunc['db_query']('', '
			SELECT th.value AS name, th.id_theme, th2.value AS version
			FROM {db_prefix}themes AS th
				INNER JOIN {db_prefix}themes AS th2 ON (th2.id_theme = th.id_theme
					AND th2.id_member = {int:no_member}
					AND th2.variable = {string:version})
			WHERE th.id_member = {int:no_member}
				AND th.variable = {string:name}
				AND th.value LIKE {string:name_value}
			LIMIT 1',
			array(
				'no_member' => 0,
				'name' => 'name',
				'version' => 'version',
				'name_value' => '%'. $context['to_install']['name'] .'%',
			)
		);
		$to_update = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Got something, lets figure it out what to do next.
		if (!empty($to_update) && !empty($to_update['version']))
			switch (compareVersions($context['to_install']['version'], $to_update['version']))
			{
				case 0: // This is exactly the same theme.
				case -1: // The one being installed is older than the one already installed.
				default: // Any other possible result.
					fatal_lang_error('package_get_error_theme_no_new_version', false, array($context['to_install']['version'], $to_update['version']));
					break;
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
					$context['to_install']['id'] = $to_update['id_theme'];

					return $context['to_install'];
					break; // Just for reference.
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

			$request = $smcFunc['db_query']('', '
				SELECT th.value AS base_theme_dir, th2.value AS base_theme_url' . (!empty($explicit_images) ? '' : ', th3.value AS images_url') . '
				FROM {db_prefix}themes AS th
					INNER JOIN {db_prefix}themes AS th2 ON (th2.id_theme = th.id_theme
						AND th2.id_member = {int:no_member}
						AND th2.variable = {string:theme_url})' . (!empty($explicit_images) ? '' : '
					INNER JOIN {db_prefix}themes AS th3 ON (th3.id_theme = th.id_theme
						AND th3.id_member = {int:no_member}
						AND th3.variable = {string:images_url})') . '
				WHERE th.id_member = {int:no_member}
					AND (th.value LIKE {string:based_on} OR th.value LIKE {string:based_on_path})
					AND th.variable = {string:theme_dir}
				LIMIT 1',
				array(
					'no_member' => 0,
					'theme_url' => 'theme_url',
					'images_url' => 'images_url',
					'theme_dir' => 'theme_dir',
					'based_on' => '%/' . $context['to_install']['based_on'],
					'based_on_path' => '%' . "\\" . $context['to_install']['based_on'],
				)
			);
			$temp = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			// Found the based on theme info, add it to the current one being installed.
			if (is_array($temp))
			{
				$context['to_install'] = $temp + $context['to_install'];

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

	updateSettings(array('knownThemes' => strtr($modSettings['knownThemes'] . ',' . $id_theme, array(',,' => ','))));

	return $id_theme;
}

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
				if (filetype($path .'/'. $object) == 'dir')
					remove_dir($path .'/'.$object);

				else
					unlink($path .'/'. $object);
			}
	}

	reset($objects);
	rmdir($path);
}

function remove_theme($themeID)
{
	global $smcFunc, $modSetting;

	if (empty($themeID))
		return false;

	$known = explode(',', $modSettings['knownThemes']);

	// Remove it from the list of known themes.
	for ($i = 0, $n = count($known); $i < $n; $i++)
		if ($known[$i] == $themeID)
			unset($known[$i]);

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

	$known = strtr(implode(',', $known), array(',,' => ','));

	// Fix it if the theme was the overall default theme.
	if ($modSettings['theme_guests'] == $themeID)
		updateSettings(array('theme_guests' => '1', 'knownThemes' => $known));

	else
		updateSettings(array('knownThemes' => $known));

	return true;
}

?>
