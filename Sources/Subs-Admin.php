<?php

/**
 * This file contains functions that are specifically done by administrators.
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
use SMF\User;
use SMF\Utils;
use SMF\Cache\CacheApi;
use SMF\Cache\CacheApiInterface;
use SMF\Db\DatabaseApi as Db;

if (!defined('SMF'))
	die('No direct access...');

/**
 * Get a list of versions that are currently installed on the server.
 *
 * @param array $checkFor An array of what to check versions for - can contain one or more of 'gd', 'imagemagick', 'db_server', 'phpa', 'memcache', 'php' or 'server'
 * @return array An array of versions (keys are same as what was in $checkFor, values are the versions)
 */
function getServerVersions($checkFor)
{
	Lang::load('Admin');
	Lang::load('ManageSettings');

	$versions = array();

	// Is GD available?  If it is, we should show version information for it too.
	if (in_array('gd', $checkFor) && function_exists('gd_info'))
	{
		$temp = gd_info();
		$versions['gd'] = array('title' => Lang::$txt['support_versions_gd'], 'version' => $temp['GD Version']);
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
		$versions['imagemagick'] = array('title' => Lang::$txt['support_versions_imagemagick'], 'version' => $im_version . ' (' . $extension_version . ')');
	}

	// Now lets check for the Database.
	if (in_array('db_server', $checkFor))
	{
		if (!isset(Db::$db_connection) || Db::$db_connection === false)
		{
			Lang::load('Errors');
			trigger_error(Lang::$txt['get_server_versions_no_database'], E_USER_NOTICE);
		}
		else
		{
			$versions['db_engine'] = array(
				'title' => sprintf(Lang::$txt['support_versions_db_engine'], Db::$db->title),
				'version' => Db::$db->get_vendor(),
			);
			$versions['db_server'] = array(
				'title' => sprintf(Lang::$txt['support_versions_db'], Db::$db->title),
				'version' => Db::$db->get_version(),
			);
		}
	}

	// Check to see if we have any accelerators installed.
	$detected = CacheApi::detect();

	/* @var CacheApiInterface $cache_api */
	foreach ($detected as $class_name => $cache_api)
	{
		$class_name_txt_key = strtolower($cache_api->getImplementationClassKeyName());

		if (in_array($class_name_txt_key, $checkFor))
			$versions[$class_name_txt_key] = array(
				'title' => isset(Lang::$txt[$class_name_txt_key . '_cache']) ?
					Lang::$txt[$class_name_txt_key . '_cache'] : $class_name,
				'version' => $cache_api->getVersion(),
			);
	}

	if (in_array('php', $checkFor))
		$versions['php'] = array(
			'title' => 'PHP',
			'version' => PHP_VERSION,
			'more' => '?action=admin;area=serversettings;sa=phpinfo',
		);

	if (in_array('server', $checkFor))
		$versions['server'] = array(
			'title' => Lang::$txt['support_versions_server'],
			'version' => $_SERVER['SERVER_SOFTWARE'],
		);

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
	global $settings;

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
	if (!empty($versionOptions['include_ssi']) && file_exists(Config::$boarddir . '/SSI.php'))
	{
		$fp = fopen(Config::$boarddir . '/SSI.php', 'rb');
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
	if (!empty($versionOptions['include_subscriptions']) && file_exists(Config::$boarddir . '/subscriptions.php'))
	{
		$fp = fopen(Config::$boarddir . '/subscriptions.php', 'rb');
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
	$sources_dir = dir(Config::$sourcedir);
	while ($entry = $sources_dir->read())
	{
		if (substr($entry, -4) === '.php' && !is_dir(Config::$sourcedir . '/' . $entry) && $entry !== 'index.php')
		{
			// Read the first 4k from the file.... enough for the header.
			$fp = fopen(Config::$sourcedir . '/' . $entry, 'rb');
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
		$tasks_dir = dir(Config::$tasksdir);
		while ($entry = $tasks_dir->read())
		{
			if (substr($entry, -4) === '.php' && !is_dir(Config::$tasksdir . '/' . $entry) && $entry !== 'index.php')
			{
				// Read the first 4k from the file.... enough for the header.
				$fp = fopen(Config::$tasksdir . '/' . $entry, 'rb');
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
 * Saves the admin's current preferences to the database.
 */
function updateAdminPreferences()
{
	global $options, $settings;

	// This must exist!
	if (!isset(Utils::$context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = Utils::jsonEncode(Utils::$context['admin_preferences']);

	// Just check we haven't ended up with something theme exclusive somehow.
	Db::$db->query('', '
		DELETE FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND variable = {string:admin_preferences}',
		array(
			'default_theme' => 1,
			'admin_preferences' => 'admin_preferences',
		)
	);

	// Update the themes table.
	Db::$db->insert('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array(User::$me->id, 1, 'admin_preferences', $options['admin_preferences']),
		array('id_member', 'id_theme', 'variable')
	);

	// Make sure we invalidate any cache.
	CacheApi::put('theme_settings-' . $settings['theme_id'] . ':' . User::$me->id, null, 0);
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
	// We certainly want this.
	require_once(Config::$sourcedir . '/Subs-Post.php');

	// Load all members which are effectively admins.
	require_once(Config::$sourcedir . '/Subs-Members.php');
	$members = membersAllowedTo('admin_forum');

	// Load their alert preferences
	require_once(Config::$sourcedir . '/Subs-Notify.php');
	$prefs = getNotifyPrefs($members, 'announcements', true);

	$request = Db::$db->query('', '
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE id_member IN({array_int:members})',
		array(
			'members' => $members,
		)
	);
	$emails_sent = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		if (empty($prefs[$row['id_member']]['announcements']))
			continue;

		// Stick their particulars in the replacement data.
		$replacements['IDMEMBER'] = $row['id_member'];
		$replacements['REALNAME'] = $row['member_name'];
		$replacements['USERNAME'] = $row['real_name'];

		// Load the data from the template.
		$emaildata = loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $row['lngfile']);

		// Then send the actual email.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);

		// Track who we emailed so we don't do it twice.
		$emails_sent[] = $row['email_address'];
	}
	Db::$db->free_result($request);

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
			$emaildata = loadEmailTemplate($template, $replacements, empty($recipient['lang']) || empty(Config::$modSettings['userLanguage']) ? Lang::$default : $recipient['lang']);

			// Send off the email.
			sendmail($recipient['email'], $emaildata['subject'], $emaildata['body'], null, $template, $emaildata['is_html'], 1);
		}
}

?>