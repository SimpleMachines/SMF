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

/*	This file contains functions that are specifically done by administrators.
	The most important function in this file for mod makers happens to be the
	updateSettingsFile() function, but it shouldn't be used often anyway.

	void getServerVersions(array checkFor)
		- get a list of versions that are currently installed on the server.

	void getFileVersions(array versionOptions)
		- get detailed version information about the physical SMF files on the
		  server.
		- the input parameter allows to set whether to include SSI.php and
		  whether the results should be sorted.
		- returns an array containing information on source files, templates
		  and language files found in the default theme directory (grouped by
		  language).

	void updateSettingsFile(array config_vars)
		- updates the Settings.php file with the changes in config_vars.
		- expects config_vars to be an associative array, with the keys as the
		  variable names in Settings.php, and the values the varaible values.
		- does not escape or quote values.
		- preserves case, formatting, and additional options in file.
		- writes nothing if the resulting file would be less than 10 lines
		  in length (sanity check for read lock.)

	void updateAdminPreferences()
		- saves the admins current preferences to the database.

	void emailAdmins(string $template, array $replacements = array(), additional_recipients = array())
		- loads all users who are admins or have the admin forum permission.
		- uses the email template and replacements passed in the parameters.
		- sends them an email.

	bool updateLastDatabaseError()
		- attempts to use the backup file first, to store the last database error
		- and only update Settings.php if the first was successful.

*/

function getServerVersions($checkFor)
{
	global $txt, $db_connection, $_PHPA, $smcFunc, $memcached, $modSettings;

	loadLanguage('Admin');

	$versions = array();

	// Is GD available?  If it is, we should show version information for it too.
	if (in_array('gd', $checkFor) && function_exists('gd_info'))
	{
		$temp = gd_info();
		$versions['gd'] = array('title' => $txt['support_versions_gd'], 'version' => $temp['GD Version']);
	}

	// Now lets check for the Database.
	if (in_array('db_server', $checkFor))
	{
		db_extend();
		if (!isset($db_connection) || $db_connection === false)
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		else
		{
			$versions['db_server'] = array('title' => sprintf($txt['support_versions_db'], $smcFunc['db_title']), 'version' => '');
			$versions['db_server']['version'] = $smcFunc['db_get_version']();
		}
	}

	// If we're using memcache we need the server info.
	if (empty($memcached) && function_exists('memcache_get') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
		get_memcached_server();

	// Check to see if we have any accelerators installed...
	if (in_array('mmcache', $checkFor) && defined('MMCACHE_VERSION'))
		$versions['mmcache'] = array('title' => 'Turck MMCache', 'version' => MMCACHE_VERSION);
	if (in_array('eaccelerator', $checkFor) && defined('EACCELERATOR_VERSION'))
		$versions['eaccelerator'] = array('title' => 'eAccelerator', 'version' => EACCELERATOR_VERSION);
	if (in_array('phpa', $checkFor) && isset($_PHPA))
		$versions['phpa'] = array('title' => 'ionCube PHP-Accelerator', 'version' => $_PHPA['VERSION']);
	if (in_array('apc', $checkFor) && extension_loaded('apc'))
		$versions['apc'] = array('title' => 'Alternative PHP Cache', 'version' => phpversion('apc'));
	if (in_array('memcache', $checkFor) && function_exists('memcache_set'))
		$versions['memcache'] = array('title' => 'Memcached', 'version' => empty($memcached) ? '???' : memcache_get_version($memcached));
	if (in_array('xcache', $checkFor) && function_exists('xcache_set'))
		$versions['xcache'] = array('title' => 'XCache', 'version' => XCACHE_VERSION);
	if (in_array('php', $checkFor))
		$versions['php'] = array('title' => 'PHP', 'version' => PHP_VERSION);

	if (in_array('server', $checkFor))
		$versions['server'] = array('title' => $txt['support_versions_server'], 'version' => $_SERVER['SERVER_SOFTWARE']);

	return $versions;
}

// Search through source, theme and language files to determine their version.
function getFileVersions(&$versionOptions)
{
	global $boarddir, $sourcedir, $settings;

	// Default place to find the languages would be the default theme dir.
	$lang_dir = $settings['default_theme_dir'] . '/languages';

	$version_info = array(
		'file_versions' => array(),
		'default_template_versions' => array(),
		'template_versions' => array(),
		'default_language_versions' => array(),
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

		// For languages sort each language too.
		foreach ($version_info['default_language_versions'] as $language => $dummy)
			ksort($version_info['default_language_versions'][$language]);
	}
	return $version_info;
}

// Update the Settings.php file.
function updateSettingsFile($config_vars)
{
	global $boarddir, $cachedir;

	// When is Settings.php last changed?
	$last_settings_change = filemtime($boarddir . '/Settings.php');

	// Load the file.  Break it up based on \r or \n, and then clean out extra characters.
	$settingsArray = trim(file_get_contents($boarddir . '/Settings.php'));
	if (strpos($settingsArray, "\n") !== false)
		$settingsArray = explode("\n", $settingsArray);
	elseif (strpos($settingsArray, "\r") !== false)
		$settingsArray = explode("\r", $settingsArray);
	else
		return;

	// Make sure we got a good file.
	if (count($config_vars) == 1 && isset($config_vars['db_last_error']))
	{
		$temp = trim(implode("\n", $settingsArray));
		if (substr($temp, 0, 5) != '<?php' || substr($temp, -2) != '?' . '>')
			return;
		if (strpos($temp, 'sourcedir') === false || strpos($temp, 'boarddir') === false || strpos($temp, 'cookiename') === false)
			return;
	}

	// Presumably, the file has to have stuff in it for this function to be called :P.
	if (count($settingsArray) < 10)
		return;

	foreach ($settingsArray as $k => $dummy)
		$settingsArray[$k] = strtr($dummy, array("\r" => '')) . "\n";

	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		// Don't trim or bother with it if it's not a variable.
		if (substr($settingsArray[$i], 0, 1) != '$')
			continue;

		$settingsArray[$i] = trim($settingsArray[$i]) . "\n";

		// Look through the variables to set....
		foreach ($config_vars as $var => $val)
		{
			if (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
			{
				$comment = strstr(substr($settingsArray[$i], strpos($settingsArray[$i], ';')), '#');
				$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment == '' ? '' : "\t\t" . rtrim($comment)) . "\n";

				// This one's been 'used', so to speak.
				unset($config_vars[$var]);
			}
		}

		if (substr(trim($settingsArray[$i]), 0, 2) == '?' . '>')
			$end = $i;
	}

	// This should never happen, but apparently it is happening.
	if (empty($end) || $end < 10)
		$end = count($settingsArray) - 1;

	// Still more?  Add them at the end.
	if (!empty($config_vars))
	{
		if (trim($settingsArray[$end]) == '?' . '>')
			$settingsArray[$end++] = '';
		else
			$end++;

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
	// like a possible race condition,
	// or a failure to write at low diskspace

	// Check before you act: if cache is enabled, we can do a simple test
	// Can we even write things on this filesystem?
	if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
		$cachedir = $boarddir . '/cache';
	$test_fp = @fopen($cachedir . '/settings_update.tmp', "w+");
	if ($test_fp)
	{
		fclose($test_fp);

		$test_fp = @fopen($cachedir . '/settings_update.tmp', 'r+');
		$written_bytes = fwrite($test_fp, "test");
		fclose($test_fp);
		@unlink($cachedir . '/settings_update.tmp');

		if ($written_bytes !== strlen("test"))
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
		// You asked for it...
		// Blank out the file - done to fix a oddity with some servers.
		$fp = @fopen($boarddir . '/Settings.php', 'w');

		// Is it even writable, though?
		if ($fp)
		{
			fclose($fp);

			$fp = fopen($boarddir . '/Settings.php', 'r+');
			foreach ($settingsArray as $line)
				fwrite($fp, strtr($line, "\r", ''));
			fclose($fp);
		}
	}
}

function updateAdminPreferences()
{
	global $options, $context, $smcFunc, $settings, $user_info;

	// This must exist!
	if (!isset($context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = serialize($context['admin_preferences']);

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

// Send all the administrators a lovely email.
function emailAdmins($template, $replacements = array(), $additional_recipients = array())
{
	global $smcFunc, $sourcedir, $language, $modSettings;

	// We certainly want this.
	require_once($sourcedir . '/Subs-Post.php');

	// Load all groups which are effectively admins.
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:admin_forum}
			AND add_deny = {int:add_deny}
			AND id_group != {int:id_group}',
		array(
			'add_deny' => 1,
			'id_group' => 0,
			'admin_forum' => 'admin_forum',
		)
	);
	$groups = array(1);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$groups[] = $row['id_group'];
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$emails_sent = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Stick their particulars in the replacement data.
		$replacements['IDMEMBER'] = $row['id_member'];
		$replacements['REALNAME'] = $row['member_name'];
		$replacements['USERNAME'] = $row['real_name'];

		// Load the data from the template.
		$emaildata = loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Then send the actual email.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);

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
			sendmail($recipient['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
		}
}

function updateLastDatabaseError()
{
	global $boarddir;

	// Find out this way if we can even write things on this filesystem.
	// In addition, store things first in the backup file

	$last_settings_change = @filemtime($boarddir . '/Settings.php');

	// Make sure the backup file is there...
	$file = $boarddir . '/Settings_bak.php';
	if ((!file_exists($file) || filesize($file) == 0) && !copy($boarddir . '/Settings.php', $file))
			return false;

	// ...and writable!
	if (!is_writable($file))
	{
		chmod($file, 0755);
		if (!is_writable($file))
		{
			chmod($file, 0775);
			if (!is_writable($file))
			{
				chmod($file, 0777);
				if (!is_writable($file))
						return false;
			}
		}
	}

	// Put the new timestamp.
	$data = file_get_contents($file);
	$data = preg_replace('~\$db_last_error = \d+;~', '$db_last_error = ' . time() . ';', $data);

	// Open the backup file for writing
	if ($fp = @fopen($file, 'w'))
	{
		// Reset the file buffer.
		set_file_buffer($fp, 0);

		// Update the file.
		$t = flock($fp, LOCK_EX);
		$bytes = fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);

		// Was it a success?
		// ...only relevant if we're still dealing with the same good ole' settings file.
		clearstatcache();
		if (($bytes == strlen($data)) && (filemtime($boarddir . '/Settings.php') === $last_settings_change))
		{
			// This is our new Settings file...
			// At least this one is an atomic operation
			@copy($file, $boarddir . '/Settings.php');
			return true;
		}
		else
		{
			// Oops. Someone might have been faster
			// or we have no more disk space left, troubles, troubles...
			// Copy the file back and run for your life!
			@copy($boarddir . '/Settings.php', $file);
		}
	}

	return false;
}

?>