<?php

/**
 * This file contains functions that are specifically done by administrators.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC2
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Get a list of versions that are currently installed on the server.
 *
 * @param array $checkFor An array of what to check versions for - can contain one or more of 'gd', 'imagemagick', 'db_server', 'phpa', 'memcache', 'xcache', 'apc', 'php' or 'server'
 * @return array An array of versions (keys are same as what was in $checkFor, values are the versions)
 */
function getServerVersions($checkFor)
{
	global $txt, $db_connection, $_PHPA, $smcFunc, $cache_accelerator, $cache_memcached, $cacheAPI, $modSettings;

	loadLanguage('Admin');

	$versions = array();

	// Is GD available?  If it is, we should show version information for it too.
	if (in_array('gd', $checkFor) && function_exists('gd_info'))
	{
		$temp = gd_info();
		$versions['gd'] = array('title' => $txt['support_versions_gd'], 'version' => $temp['GD Version']);
	}

	// Why not have a look at ImageMagick? If it's installed, we should show version information for it too.
	if (in_array('imagemagick', $checkFor) && (class_exists('Imagick') || function_exists('MagickGetVersionString')))
	{
		if (class_exists('Imagick'))
		{
			$temp = New Imagick;
			$temp2 = $temp->getVersion();
			$im_version = $temp2['versionString'];
			$extension_version = 'Imagick ' . phpversion('Imagick');
		}
		else
		{
			$im_version = MagickGetVersionString();
			$extension_version = 'MagickWand ' . phpversion('MagickWand');
		}

		// We already know it's ImageMagick and the website isn't needed...
		$im_version = str_replace(array('ImageMagick ', ' https://www.imagemagick.org'), '', $im_version);
		$versions['imagemagick'] = array('title' => $txt['support_versions_imagemagick'], 'version' => $im_version . ' (' . $extension_version . ')');
	}

	// Now lets check for the Database.
	if (in_array('db_server', $checkFor))
	{
		db_extend();
		if (!isset($db_connection) || $db_connection === false)
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		else
		{
			$versions['db_engine'] = array('title' => sprintf($txt['support_versions_db_engine'], $smcFunc['db_title']), 'version' => '');
			$versions['db_engine']['version'] = $smcFunc['db_get_vendor']();

			$versions['db_server'] = array('title' => sprintf($txt['support_versions_db'], $smcFunc['db_title']), 'version' => '');
			$versions['db_server']['version'] = $smcFunc['db_get_version']();
		}
	}

	// If we're using memcache we need the server info.
	$memcache_version = '???';
	if (!empty($cache_accelerator) && ($cache_accelerator == 'memcached' || $cache_accelerator == 'memcache') && !empty($cache_memcached) && !empty($cacheAPI))
		$memcache_version = $cacheAPI->getVersion();

	// Check to see if we have any accelerators installed...
	if (in_array('phpa', $checkFor) && isset($_PHPA))
		$versions['phpa'] = array('title' => 'ionCube PHP-Accelerator', 'version' => $_PHPA['VERSION']);
	if (in_array('apc', $checkFor) && extension_loaded('apc'))
		$versions['apc'] = array('title' => 'Alternative PHP Cache', 'version' => phpversion('apc'));
	if (in_array('memcache', $checkFor) && function_exists('memcache_set'))
		$versions['memcache'] = array('title' => 'Memcached', 'version' => $memcache_version);
	if (in_array('xcache', $checkFor) && function_exists('xcache_set'))
		$versions['xcache'] = array('title' => 'XCache', 'version' => XCACHE_VERSION);

	if (in_array('php', $checkFor))
		$versions['php'] = array('title' => 'PHP', 'version' => PHP_VERSION, 'more' => '?action=admin;area=serversettings;sa=phpinfo');

	if (in_array('server', $checkFor))
		$versions['server'] = array('title' => $txt['support_versions_server'], 'version' => $_SERVER['SERVER_SOFTWARE']);

	return $versions;
}

/**
 * Search through source, theme and language files to determine their version.
 * Get detailed version information about the physical SMF files on the server.
 *
 * - the input parameter allows to set whether to include SSI.php and whether
 *   the results should be sorted.
 * - returns an array containing information on source files, templates and
 *   language files found in the default theme directory (grouped by language).
 *
 * @param array &$versionOptions An array of options. Can contain one or more of 'include_ssi', 'include_subscriptions', 'include_tasks' and 'sort_results'
 * @return array An array of file version info.
 */
function getFileVersions(&$versionOptions)
{
	global $boarddir, $sourcedir, $settings, $tasksdir;

	// Default place to find the languages would be the default theme dir.
	$lang_dir = $settings['default_theme_dir'] . '/languages';

	$version_info = array(
		'file_versions' => array(),
		'default_template_versions' => array(),
		'template_versions' => array(),
		'default_language_versions' => array(),
		'tasks_versions' => array(),
	);

	// Find the version in SSI.php's file header.
	if (!empty($versionOptions['include_ssi']) && file_exists($boarddir . '/SSI.php'))
	{
		$fp = fopen($boarddir . '/SSI.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// The comment looks rougly like... that.
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['SSI.php'] = $match[1];
		// Not found!  This is bad.
		else
			$version_info['file_versions']['SSI.php'] = '??';
	}

	// Do the paid subscriptions handler?
	if (!empty($versionOptions['include_subscriptions']) && file_exists($boarddir . '/subscriptions.php'))
	{
		$fp = fopen($boarddir . '/subscriptions.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// Found it?
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['subscriptions.php'] = $match[1];
		// If we haven't how do we all get paid?
		else
			$version_info['file_versions']['subscriptions.php'] = '??';
	}

	// Load all the files in the Sources directory, except this file and the redirect.
	$sources_dir = dir($sourcedir);
	while ($entry = $sources_dir->read())
	{
		if (substr($entry, -4) === '.php' && !is_dir($sourcedir . '/' . $entry) && $entry !== 'index.php')
		{
			// Read the first 4k from the file.... enough for the header.
			$fp = fopen($sourcedir . '/' . $entry, 'rb');
			$header = fread($fp, 4096);
			fclose($fp);

			// Look for the version comment in the file header.
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				$version_info['file_versions'][$entry] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['file_versions'][$entry] = '??';
		}
	}
	$sources_dir->close();

	// Load all the files in the tasks directory.
	if (!empty($versionOptions['include_tasks']))
	{
		$tasks_dir = dir($tasksdir);
		while ($entry = $tasks_dir->read())
		{
			if (substr($entry, -4) === '.php' && !is_dir($tasksdir . '/' . $entry) && $entry !== 'index.php')
			{
				// Read the first 4k from the file.... enough for the header.
				$fp = fopen($tasksdir . '/' . $entry, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				// Look for the version comment in the file header.
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
					$version_info['tasks_versions'][$entry] = $match[1];
				// It wasn't found, but the file was... show a '??'.
				else
					$version_info['tasks_versions'][$entry] = '??';
			}
		}
		$tasks_dir->close();
	}

	// Load all the files in the default template directory - and the current theme if applicable.
	$directories = array('default_template_versions' => $settings['default_theme_dir']);
	if ($settings['theme_id'] != 1)
		$directories += array('template_versions' => $settings['theme_dir']);

	foreach ($directories as $type => $dirname)
	{
		$this_dir = dir($dirname);
		while ($entry = $this_dir->read())
		{
			if (substr($entry, -12) == 'template.php' && !is_dir($dirname . '/' . $entry))
			{
				// Read the first 768 bytes from the file.... enough for the header.
				$fp = fopen($dirname . '/' . $entry, 'rb');
				$header = fread($fp, 768);
				fclose($fp);

				// Look for the version comment in the file header.
				if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
					$version_info[$type][$entry] = $match[1];
				// It wasn't found, but the file was... show a '??'.
				else
					$version_info[$type][$entry] = '??';
			}
		}
		$this_dir->close();
	}

	// Load up all the files in the default language directory and sort by language.
	$this_dir = dir($lang_dir);
	while ($entry = $this_dir->read())
	{
		if (substr($entry, -4) == '.php' && $entry != 'index.php' && !is_dir($lang_dir . '/' . $entry))
		{
			// Read the first 768 bytes from the file.... enough for the header.
			$fp = fopen($lang_dir . '/' . $entry, 'rb');
			$header = fread($fp, 768);
			fclose($fp);

			// Split the file name off into useful bits.
			list ($name, $language) = explode('.', $entry);

			// Look for the version comment in the file header.
			if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				$version_info['default_language_versions'][$language][$name] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['default_language_versions'][$language][$name] = '??';
		}
	}
	$this_dir->close();

	// Sort the file versions by filename.
	if (!empty($versionOptions['sort_results']))
	{
		ksort($version_info['file_versions']);
		ksort($version_info['default_template_versions']);
		ksort($version_info['template_versions']);
		ksort($version_info['default_language_versions']);
		ksort($version_info['tasks_versions']);

		// For languages sort each language too.
		foreach ($version_info['default_language_versions'] as $language => $dummy)
			ksort($version_info['default_language_versions'][$language]);
	}
	return $version_info;
}

/**
 * Update the Settings.php file.
 *
 * The most important function in this file for mod makers happens to be the
 * updateSettingsFile() function, but it shouldn't be used often anyway.
 *
 * - updates the Settings.php file with the changes supplied in config_vars.
 * - expects config_vars to be an associative array, with the keys as the
 *   variable names in Settings.php, and the values the variable values.
 * - does not escape or quote values.
 * - preserves case, formatting, and additional options in file.
 * - writes nothing if the resulting file would be less than 10 lines
 *   in length (sanity check for read lock.)
 * - check for changes to db_last_error and passes those off to a separate handler
 * - attempts to create a backup file and will use it should the writing of the
 *   new settings file fail
 *
 * @param array $config_vars An array of one or more variables to update
 */
function updateSettingsFile($config_vars)
{
	global $boarddir, $cachedir, $context;

	// Updating the db_last_error, then don't mess around with Settings.php
	if (count($config_vars) === 1 && isset($config_vars['db_last_error']))
	{
		updateDbLastError($config_vars['db_last_error']);
		return;
	}

	// When was Settings.php last changed?
	$last_settings_change = filemtime($boarddir . '/Settings.php');

	// Load the settings file.
	$settingsArray = trim(file_get_contents($boarddir . '/Settings.php'));

	// Break it up based on \r or \n, and then clean out extra characters.
	if (strpos($settingsArray, "\n") !== false)
		$settingsArray = explode("\n", $settingsArray);
	elseif (strpos($settingsArray, "\r") !== false)
		$settingsArray = explode("\r", $settingsArray);
	else
		return;

	// Presumably, the file has to have stuff in it for this function to be called :P.
	if (count($settingsArray) < 10)
		return;

	// remove any /r's that made there way in here
	foreach ($settingsArray as $k => $dummy)
		$settingsArray[$k] = strtr($dummy, array("\r" => '')) . "\n";

	// go line by line and see whats changing
	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		// Don't trim or bother with it if it's not a variable.
		if (substr($settingsArray[$i], 0, 1) != '$')
			continue;

		$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

		// Look through the variables to set....
		foreach ($config_vars as $var => $val)
		{
			// be sure someone is not updating db_last_error this with a group
			if ($var === 'db_last_error')
			{
				updateDbLastError($val);
				unset($config_vars[$var]);
			}
			elseif (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
			{
				$comment = strstr(substr($settingsArray[$i], strpos($settingsArray[$i], ';')), '#');
				$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment == '' ? '' : "\t\t" . rtrim($comment)) . "\n";

				// This one's been 'used', so to speak.
				unset($config_vars[$var]);
			}
		}

		// End of the file ... maybe
		if (substr(trim($settingsArray[$i]), 0, 2) == '?' . '>')
			$end = $i;
	}

	// This should never happen, but apparently it is happening.
	if (empty($end) || $end < 10)
		$end = count($settingsArray) - 1;

	// Still more variables to go?  Then lets add them at the end.
	if (!empty($config_vars))
	{
		if (trim($settingsArray[$end]) == '?' . '>')
			$settingsArray[$end++] = '';
		else
			$end++;

		// Add in any newly defined vars that were passed
		foreach ($config_vars as $var => $val)
			$settingsArray[$end++] = '$' . $var . ' = ' . $val . ';' . "\n";

		$settingsArray[$end] = '?' . '>';
	}
	else
		$settingsArray[$end] = trim($settingsArray[$end]);

	// Sanity error checking: the file needs to be at least 12 lines.
	if (count($settingsArray) < 12)
		return;

	// Try to avoid a few pitfalls:
	//  - like a possible race condition,
	//  - or a failure to write at low diskspace
	//
	// Check before you act: if cache is enabled, we can do a simple write test
	// to validate that we even write things on this filesystem.
	if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
		$cachedir = $boarddir . '/cache';

	$test_fp = @fopen($cachedir . '/settings_update.tmp', "w+");
	if ($test_fp)
	{
		fclose($test_fp);
		$written_bytes = file_put_contents($cachedir . '/settings_update.tmp', 'test', LOCK_EX);
		@unlink($cachedir . '/settings_update.tmp');

		if ($written_bytes !== 4)
		{
			// Oops. Low disk space, perhaps. Don't mess with Settings.php then.
			// No means no. :P
			return;
		}
	}

	// Protect me from what I want! :P
	clearstatcache();
	if (filemtime($boarddir . '/Settings.php') === $last_settings_change)
	{
		// save the old before we do anything
		$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');
		$settings_backup_fail = !$settings_backup_fail ? (!file_exists($boarddir . '/Settings_bak.php') || filesize($boarddir . '/Settings_bak.php') === 0) : $settings_backup_fail;

		// write out the new
		$write_settings = implode('', $settingsArray);
		$written_bytes = file_put_contents($boarddir . '/Settings.php', $write_settings, LOCK_EX);

		// survey says ...
		if ($written_bytes !== strlen($write_settings) && !$settings_backup_fail)
		{
			// Well this is not good at all, lets see if we can save this
			$context['settings_message'] = 'settings_error';

			if (file_exists($boarddir . '/Settings_bak.php'))
				@copy($boarddir . '/Settings_bak.php', $boarddir . '/Settings.php');
		}
	}

	// Even though on normal installations the filemtime should prevent this being used by the installer incorrectly
	// it seems that there are times it might not. So let's MAKE it dump the cache.
	if (function_exists('opcache_invalidate'))
		opcache_invalidate($boarddir . '/Settings.php', true);
}

/**
 * Saves the time of the last db error for the error log
 * - Done separately from updateSettingsFile to avoid race conditions
 *   which can occur during a db error
 * - If it fails Settings.php will assume 0
 *
 * @param int $time The timestamp of the last DB error
 */
function updateDbLastError($time)
{
	global $boarddir, $cachedir;

	// Write out the db_last_error file with the error timestamp
	file_put_contents($cachedir . '/db_last_error.php', '<' . '?' . "php\n" . '$db_last_error = ' . $time . ';' . "\n" . '?' . '>', LOCK_EX);
	@touch($boarddir . '/' . 'Settings.php');
}

/**
 * Saves the admin's current preferences to the database.
 */
function updateAdminPreferences()
{
	global $options, $context, $smcFunc, $settings, $user_info;

	// This must exist!
	if (!isset($context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = $smcFunc['json_encode']($context['admin_preferences']);

	// Just check we haven't ended up with something theme exclusive somehow.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND variable = {string:admin_preferences}',
		array(
			'default_theme' => 1,
			'admin_preferences' => 'admin_preferences',
		)
	);

	// Update the themes table.
	$smcFunc['db_insert']('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array($user_info['id'], 1, 'admin_preferences', $options['admin_preferences']),
		array('id_member', 'id_theme', 'variable')
	);

	// Make sure we invalidate any cache.
	cache_put_data('theme_settings-' . $settings['theme_id'] . ':' . $user_info['id'], null, 0);
}

/**
 * Send all the administrators a lovely email.
 * - loads all users who are admins or have the admin forum permission.
 * - uses the email template and replacements passed in the parameters.
 * - sends them an email.
 *
 * @param string $template Which email template to use
 * @param array $replacements An array of items to replace the variables in the template
 * @param array $additional_recipients An array of arrays of info for additional recipients. Should have 'id', 'email' and 'name' for each.
 */
function emailAdmins($template, $replacements = array(), $additional_recipients = array())
{
	global $smcFunc, $sourcedir, $language, $modSettings;

	// We certainly want this.
	require_once($sourcedir . '/Subs-Post.php');

	// Load all members which are effectively admins.
	require_once($sourcedir . '/Subs-Members.php');
	$members = membersAllowedTo('admin_forum');

	// Load their alert preferences
	require_once($sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($members, 'announcements', true);

	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE id_member IN({array_int:members})',
		array(
			'members' => $members,
		)
	);
	$emails_sent = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (empty($prefs[$row['id_member']]['announcements']))
			continue;

		// Stick their particulars in the replacement data.
		$replacements['IDMEMBER'] = $row['id_member'];
		$replacements['REALNAME'] = $row['member_name'];
		$replacements['USERNAME'] = $row['real_name'];

		// Load the data from the template.
		$emaildata = loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Then send the actual email.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);

		// Track who we emailed so we don't do it twice.
		$emails_sent[] = $row['email_address'];
	}
	$smcFunc['db_free_result']($request);

	// Any additional users we must email this to?
	if (!empty($additional_recipients))
		foreach ($additional_recipients as $recipient)
		{
			if (in_array($recipient['email'], $emails_sent))
				continue;

			$replacements['IDMEMBER'] = $recipient['id'];
			$replacements['REALNAME'] = $recipient['name'];
			$replacements['USERNAME'] = $recipient['name'];

			// Load the template again.
			$emaildata = loadEmailTemplate($template, $replacements, empty($recipient['lang']) || empty($modSettings['userLanguage']) ? $language : $recipient['lang']);

			// Send off the email.
			sendmail($recipient['email'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);
		}
}

?>