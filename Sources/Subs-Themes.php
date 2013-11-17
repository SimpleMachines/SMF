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
		WHERE variable IN ({string:theme_dir}, {string:theme_url}, {string:images_url}, {string:name}, {string:theme_layers}, {string:theme_templates}, {string:version}, {string:install_for})
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
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$single[$row['variable']] = $row['value'];

	return $single;
}

function get_theme_info($path)
{
	global $sourcedir, $forum_version;

	if (empty($path))
		return false;

	$xml_data = array();

	// Parse theme-info.xml into an xmlArray.
	require_once($sourcedir . '/Class-Package.php');
	$theme_info_xml = new xmlArray(file_get_contents($path . '/theme_info.xml'));

	// Error message, there isn't any valid info.
	if (!$theme_info_xml->exists('theme-info[0]'))
		fatal_lang_error('package_get_error_packageinfo_corrupt', false);

	// Check for compatibility with 2.1 or greater.
	if (!$theme_info_xml->exists('theme-info/install'))
		fatal_lang_error('package_get_error_theme_not_compatible', false, $forum_version);

	// So, we have an install tag which is cool and stuff but we also need to check it and match your current SMF version...
	$the_version = strtr($forum_version, array('SMF ' => ''));
	$install_versions = $theme_info_xml->path('theme-info/install/@for');

	// The theme isn't compatible with the current SMF version.
	if (!$install_versions || !matchPackageVersion($the_version, $install_versions))
		fatal_lang_error('package_get_error_theme_not_compatible', false, $forum_version);

	$theme_info_xml = $theme_info_xml->path('theme-info[0]');
	$theme_info_xml = $theme_info_xml->to_array();

	$xml_elements = array(
		'name' => 'name',
		'theme_layers' => 'layers',
		'theme_templates' => 'templates',
		'based_on' => 'based-on',
		'version' => 'version',
	);

	// Assign the values to be stored.
	foreach ($xml_elements as $var => $name)
		if (!empty($theme_info_xml[$name]))
			$xml_data[$var] = $theme_info_xml[$name];

	return $xml_data;
}

function theme_install($to_install = array())
{
	global $sourcedir, $txt, $context, $boarddir, $boardurl;
	global $themedir, $themeurl;

	// External use? no problem!
	if ($to_install)
		$context['to_install'] = $to_install;

	// We kinda need this and it should have been previously set.
	if (empty($context['to_install']))
		return false;

	// One last check.
	if ($context['to_install']['dir'] != '' && basename($context['to_install']['dir']) != 'Themes')
		return false;

	// Defaults.
	$context['to_install'] = array(
		'theme_url' => $themeurl . '/' . basename($context['to_install']['dir']),
	);

	// This vars could have been set, it all depends from where are we coming.
	if (empty($context['to_install']['images_url']))
		$context['to_install']['images_url'] = $themeurl . '/' . basename($context['to_install']['dir']) . '/images';

	// Perhaps they are trying to install a mod, lets tell them nicely this is the wrong function.
	if (file_exists($context['to_install']['dir'] . '/package-info.xml'))
	{
		$txt['package_get_error_is_mod'] = str_replace('{MANAGEMODURL}', $scripturl . '?action=admin;area=packages;' . $context['session_var'] . '=' . $context['session_id'], $txt['package_get_error_is_mod']);
		fatal_lang_error('package_theme_upload_error_broken', false, $txt['package_get_error_is_mod']);
	}

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

					// Do a redirect and set a nice updated message.
					redirectexit('action=admin;area=theme;sa=install;theme_id=' . $to_update['id_theme'] . ';updated;' . $context['session_var'] . '=' . $context['session_id']);
					break;
			}
	}

	if (!empty($theme_info_xml['images']))
	{
		$context['to_install']['images_url'] = $context['to_install']['theme_url'] . '/' . $theme_info_xml['images'];
		$explicit_images = true;
	}

	if (!empty($theme_info_xml['extra']))
		$context['to_install'] += unserialize($theme_info_xml['extra']);

	if (isset($context['to_install']['based_on']))
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
}

?>
