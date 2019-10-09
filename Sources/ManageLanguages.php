<?php

/**
 * This file handles the administration of languages tasks.
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
 * This is the main function for the languages area.
 * It dispatches the requests.
 * Loads the ManageLanguages template. (sub-actions will use it)
 *
 * @todo lazy loading.
 *
 * @uses ManageSettings language file
 */
function ManageLanguages()
{
	global $context, $txt;

	loadTemplate('ManageLanguages');
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['edit_languages'];
	$context['sub_template'] = 'show_settings';

	$subActions = array(
		'edit' => 'ModifyLanguages',
		'add' => 'AddLanguage',
		'settings' => 'ModifyLanguageSettings',
		'downloadlang' => 'DownloadLanguage',
		'editlang' => 'ModifyLanguage',
	);

	// By default we're managing languages.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'edit';
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['language_configuration'],
		'description' => $txt['language_description'],
	);

	call_integration_hook('integrate_manage_languages', array(&$subActions));

	// Call the right function for this sub-action.
	call_helper($subActions[$_REQUEST['sa']]);
}

/**
 * Interface for adding a new language
 *
 * @uses ManageLanguages template, add_language sub-template.
 */
function AddLanguage()
{
	global $context, $sourcedir, $txt, $smcFunc;

	// Are we searching for new languages courtesy of Simple Machines?
	if (!empty($_POST['smf_add_sub']))
	{
		$context['smf_search_term'] = $smcFunc['htmlspecialchars'](trim($_POST['smf_add']));

		$listOptions = array(
			'id' => 'smf_languages',
			'get_items' => array(
				'function' => 'list_getLanguagesList',
			),
			'columns' => array(
				'name' => array(
					'header' => array(
						'value' => $txt['name'],
					),
					'data' => array(
						'db' => 'name',
					),
				),
				'description' => array(
					'header' => array(
						'value' => $txt['add_language_smf_desc'],
					),
					'data' => array(
						'db' => 'description',
					),
				),
				'version' => array(
					'header' => array(
						'value' => $txt['add_language_smf_version'],
					),
					'data' => array(
						'db' => 'version',
					),
				),
				'utf8' => array(
					'header' => array(
						'value' => $txt['add_language_smf_utf8'],
					),
					'data' => array(
						'db' => 'utf8',
					),
				),
				'install_link' => array(
					'header' => array(
						'value' => $txt['add_language_smf_install'],
						'class' => 'centercol',
					),
					'data' => array(
						'db' => 'install_link',
						'class' => 'centercol',
					),
				),
			),
		);

		require_once($sourcedir . '/Subs-List.php');
		createList($listOptions);

		$context['default_list'] = 'smf_languages';
	}

	$context['sub_template'] = 'add_language';
}

/**
 * Gets a list of available languages from the mother ship
 * Will return a subset if searching, otherwise all avaialble
 *
 * @return array An array containing information about each available language
 */
function list_getLanguagesList()
{
	global $context, $sourcedir, $smcFunc, $txt, $scripturl;

	// We're going to use this URL.
	$url = 'https://download.simplemachines.org/fetch_language.php?version=' . urlencode(SMF_VERSION);

	// Load the class file and stick it into an array.
	require_once($sourcedir . '/Class-Package.php');
	$language_list = new xmlArray(fetch_web_data($url), true);

	// Check that the site responded and that the language exists.
	if (!$language_list->exists('languages'))
		$context['smf_error'] = 'no_response';
	elseif (!$language_list->exists('languages/language'))
		$context['smf_error'] = 'no_files';
	else
	{
		$language_list = $language_list->path('languages[0]');
		$lang_files = $language_list->set('language');
		$smf_languages = array();
		foreach ($lang_files as $file)
		{
			// Were we searching?
			if (!empty($context['smf_search_term']) && strpos($file->fetch('name'), $smcFunc['strtolower']($context['smf_search_term'])) === false)
				continue;

			$smf_languages[] = array(
				'id' => $file->fetch('id'),
				'name' => $smcFunc['ucwords']($file->fetch('name')),
				'version' => $file->fetch('version'),
				'utf8' => $txt['yes'],
				'description' => $file->fetch('description'),
				'install_link' => '<a href="' . $scripturl . '?action=admin;area=languages;sa=downloadlang;did=' . $file->fetch('id') . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . $txt['add_language_smf_install'] . '</a>',
			);
		}
		if (empty($smf_languages))
			$context['smf_error'] = 'no_files';
		else
			return $smf_languages;
	}
}

/**
 * Download a language file from the Simple Machines website.
 * Requires a valid download ID ("did") in the URL.
 * Also handles installing language files.
 * Attempts to chmod things as needed.
 * Uses a standard list to display information about all the files and where they'll be put.
 *
 * @uses ManageLanguages template, download_language sub-template.
 * @uses Admin template, show_list sub-template.
 */
function DownloadLanguage()
{
	global $context, $sourcedir, $boarddir, $txt, $scripturl, $modSettings, $cache_enable;

	loadLanguage('ManageSettings');
	require_once($sourcedir . '/Subs-Package.php');

	// Clearly we need to know what to request.
	if (!isset($_GET['did']))
		fatal_lang_error('no_access', false);

	// Some lovely context.
	$context['download_id'] = $_GET['did'];
	$context['sub_template'] = 'download_language';
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'add';

	// Can we actually do the installation - and do they want to?
	if (!empty($_POST['do_install']) && !empty($_POST['copy_file']))
	{
		checkSession('get');
		validateToken('admin-dlang');

		$chmod_files = array();
		$install_files = array();

		// Check writable status.
		foreach ($_POST['copy_file'] as $file)
		{
			// Check it's not very bad.
			if (strpos($file, '..') !== false || (strpos($file, 'Themes') !== 0 && !preg_match('~agreement\.[A-Za-z-_0-9]+\.txt$~', $file)))
				fatal_error($txt['languages_download_illegal_paths']);

			$chmod_files[] = $boarddir . '/' . $file;
			$install_files[] = $file;
		}

		// Call this in case we have work to do.
		$file_status = create_chmod_control($chmod_files);
		$files_left = $file_status['files']['notwritable'];

		// Something not writable?
		if (!empty($files_left))
			$context['error_message'] = $txt['languages_download_not_chmod'];
		// Otherwise, go go go!
		elseif (!empty($install_files))
		{
			read_tgz_file('https://download.simplemachines.org/fetch_language.php?version=' . urlencode(SMF_VERSION) . ';fetch=' . urlencode($_GET['did']), $boarddir, false, true, $install_files);

			// Make sure the files aren't stuck in the cache.
			package_flush_cache();
			$context['install_complete'] = sprintf($txt['languages_download_complete_desc'], $scripturl . '?action=admin;area=languages');

			return;
		}
	}

	// Open up the old china.
	if (!isset($archive_content))
		$archive_content = read_tgz_file('https://download.simplemachines.org/fetch_language.php?version=' . urlencode(SMF_VERSION) . ';fetch=' . urlencode($_GET['did']), null);

	if (empty($archive_content))
		fatal_error($txt['add_language_error_no_response']);

	// Now for each of the files, let's do some *stuff*
	$context['files'] = array(
		'lang' => array(),
		'other' => array(),
	);
	$context['make_writable'] = array();
	foreach ($archive_content as $file)
	{
		$pathinfo = pathinfo($file['filename']);
		$dirname = $pathinfo['dirname'];
		$basename = $pathinfo['basename'];
		$extension = $pathinfo['extension'];

		// Don't do anything with files we don't understand.
		if (!in_array($extension, array('php', 'jpg', 'gif', 'jpeg', 'png', 'txt')))
			continue;

		// Basic data.
		$context_data = array(
			'name' => $basename,
			'destination' => $boarddir . '/' . $file['filename'],
			'generaldest' => $file['filename'],
			'size' => $file['size'],
			// Does chmod status allow the copy?
			'writable' => false,
			// Should we suggest they copy this file?
			'default_copy' => true,
			// Does the file already exist, if so is it same or different?
			'exists' => false,
		);

		// Does the file exist, is it different and can we overwrite?
		if (file_exists($boarddir . '/' . $file['filename']))
		{
			if (is_writable($boarddir . '/' . $file['filename']))
				$context_data['writable'] = true;

			// Finally, do we actually think the content has changed?
			if ($file['size'] == filesize($boarddir . '/' . $file['filename']) && $file['md5'] == md5_file($boarddir . '/' . $file['filename']))
			{
				$context_data['exists'] = 'same';
				$context_data['default_copy'] = false;
			}
			// Attempt to discover newline character differences.
			elseif ($file['md5'] == md5(preg_replace("~[\r]?\n~", "\r\n", file_get_contents($boarddir . '/' . $file['filename']))))
			{
				$context_data['exists'] = 'same';
				$context_data['default_copy'] = false;
			}
			else
				$context_data['exists'] = 'different';
		}
		// No overwrite?
		else
		{
			// Can we at least stick it in the directory...
			if (is_writable($boarddir . '/' . $dirname))
				$context_data['writable'] = true;
		}

		// I love PHP files, that's why I'm a developer and not an artistic type spending my time drinking absinth and living a life of sin...
		if ($extension == 'php' && preg_match('~\w+\.\w+(?:-utf8)?\.php~', $basename))
		{
			$context_data += array(
				'version' => '??',
				'cur_version' => false,
				'version_compare' => 'newer',
			);

			list ($name, $language) = explode('.', $basename);

			// Let's get the new version, I like versions, they tell me that I'm up to date.
			if (preg_match('~\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '~i', $file['preview'], $match) == 1)
				$context_data['version'] = $match[1];

			// Now does the old file exist - if so what is it's version?
			if (file_exists($boarddir . '/' . $file['filename']))
			{
				// OK - what is the current version?
				$fp = fopen($boarddir . '/' . $file['filename'], 'rb');
				$header = fread($fp, 768);
				fclose($fp);

				// Find the version.
				if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				{
					$context_data['cur_version'] = $match[1];

					// How does this compare?
					if ($context_data['cur_version'] == $context_data['version'])
						$context_data['version_compare'] = 'same';
					elseif ($context_data['cur_version'] > $context_data['version'])
						$context_data['version_compare'] = 'older';

					// Don't recommend copying if the version is the same.
					if ($context_data['version_compare'] != 'newer')
						$context_data['default_copy'] = false;
				}
			}

			// Add the context data to the main set.
			$context['files']['lang'][] = $context_data;
		}
		elseif ($extension == 'txt' && stripos($basename, 'agreement') !== false)
		{
			$context_data += array(
				'version' => '??',
				'cur_version' => false,
				'version_compare' => 'newer',
			);

			// Registration agreement is a primary file
			$context['files']['lang'][] = $context_data;
		}
		else
		{
			// There shouldn't be anything else, but load this into "other" in case we decide to handle it in the future
			$context['files']['other'][] = $context_data;
		}

		// Collect together all non-writable areas.
		if (!$context_data['writable'])
			$context['make_writable'][] = $context_data['destination'];
	}

	// Before we go to far can we make anything writable, eh, eh?
	if (!empty($context['make_writable']))
	{
		// What is left to be made writable?
		$file_status = create_chmod_control($context['make_writable']);
		$context['still_not_writable'] = $file_status['files']['notwritable'];

		// Mark those which are now writable as such.
		foreach ($context['files'] as $type => $data)
		{
			foreach ($data as $k => $file)
			{
				if (!$file['writable'] && !in_array($file['destination'], $context['still_not_writable']))
					$context['files'][$type][$k]['writable'] = true;
			}
		}

		// Are we going to need more language stuff?
		if (!empty($context['still_not_writable']))
			loadLanguage('Packages');
	}

	// This is the list for the main files.
	$listOptions = array(
		'id' => 'lang_main_files_list',
		'title' => $txt['languages_download_main_files'],
		'get_items' => array(
			'function' => function() use ($context)
			{
				return $context['files']['lang'];
			},
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['languages_download_filename'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return '<strong>' . $rowData['name'] . '</strong><br><span class="smalltext">' . $txt['languages_download_dest'] . ': ' . $rowData['destination'] . '</span>' . ($rowData['version_compare'] == 'older' ? '<br>' . $txt['languages_download_older'] : '');
					},
				),
			),
			'writable' => array(
				'header' => array(
					'value' => $txt['languages_download_writable'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return '<span style="color: ' . ($rowData['writable'] ? 'green' : 'red') . ';">' . ($rowData['writable'] ? $txt['yes'] : $txt['no']) . '</span>';
					},
				),
			),
			'version' => array(
				'header' => array(
					'value' => $txt['languages_download_version'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return '<span style="color: ' . ($rowData['version_compare'] == 'older' ? 'red' : ($rowData['version_compare'] == 'same' ? 'orange' : 'green')) . ';">' . $rowData['version'] . '</span>';
					},
				),
			),
			'exists' => array(
				'header' => array(
					'value' => $txt['languages_download_exists'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return $rowData['exists'] ? ($rowData['exists'] == 'same' ? $txt['languages_download_exists_same'] : $txt['languages_download_exists_different']) : $txt['no'];
					},
				),
			),
			'copy' => array(
				'header' => array(
					'value' => $txt['languages_download_overwrite'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="checkbox" name="copy_file[]" value="' . $rowData['generaldest'] . '"' . ($rowData['default_copy'] ? ' checked' : '') . '>';
					},
					'style' => 'width: 4%;',
					'class' => 'centercol',
				),
			),
		),
	);

	// Kill the cache, as it is now invalid..
	if (!empty($cache_enable))
	{
		cache_put_data('known_languages', null, !empty($cache_enable) && $cache_enable < 1 ? 86400 : 3600);
		cache_put_data('known_languages_all', null, !empty($cache_enable) && $cache_enable < 1 ? 86400 : 3600);
	}

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['default_list'] = 'lang_main_files_list';
	createToken('admin-dlang');
}

/**
 * This lists all the current languages and allows editing of them.
 */
function ModifyLanguages()
{
	global $txt, $context, $scripturl, $modSettings;
	global $sourcedir, $language, $boarddir;

	// Setting a new default?
	if (!empty($_POST['set_default']) && !empty($_POST['def_language']))
	{
		checkSession();
		validateToken('admin-lang');

		getLanguages();
		$lang_exists = false;
		foreach ($context['languages'] as $lang)
		{
			if ($_POST['def_language'] == $lang['filename'])
			{
				$lang_exists = true;
				break;
			}
		}

		if ($_POST['def_language'] != $language && $lang_exists)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			updateSettingsFile(array('language' => '\'' . $_POST['def_language'] . '\''));
			$language = $_POST['def_language'];
		}
	}

	// Create another one time token here.
	createToken('admin-lang');

	$listOptions = array(
		'id' => 'language_list',
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=languages',
		'title' => $txt['edit_languages'],
		'get_items' => array(
			'function' => 'list_getLanguages',
		),
		'get_count' => array(
			'function' => 'list_getNumLanguages',
		),
		'columns' => array(
			'default' => array(
				'header' => array(
					'value' => $txt['languages_default'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="radio" name="def_language" value="' . $rowData['id'] . '"' . ($rowData['default'] ? ' checked' : '') . ' onclick="highlightSelected(\'list_language_list_' . $rowData['id'] . '\');">';
					},
					'style' => 'width: 8%;',
					'class' => 'centercol',
				),
			),
			'name' => array(
				'header' => array(
					'value' => $txt['languages_lang_name'],
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl)
					{
						return sprintf('<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s">%3$s</a>', $scripturl, $rowData['id'], $rowData['name']);
					},
					'class' => 'centercol',
				),
			),
			'character_set' => array(
				'header' => array(
					'value' => $txt['languages_character_set'],
				),
				'data' => array(
					'db_htmlsafe' => 'char_set',
					'class' => 'centercol',
				),
			),
			'count' => array(
				'header' => array(
					'value' => $txt['languages_users'],
				),
				'data' => array(
					'db_htmlsafe' => 'count',
					'class' => 'centercol',
				),
			),
			'locale' => array(
				'header' => array(
					'value' => $txt['languages_locale'],
				),
				'data' => array(
					'db_htmlsafe' => 'locale',
					'class' => 'centercol',
				),
			),
			'editlang' => array(
				'header' => array(
					'value' => '',
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl, $txt)
					{
						return sprintf('<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s" class="button">%3$s</a>', $scripturl, $rowData['id'], $txt['edit']);
					},
					'style' => 'width: 8%;',
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=languages',
			'token' => 'admin-lang',
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '"><input type="submit" name="set_default" value="' . $txt['save'] . '"' . (is_writable($boarddir . '/Settings.php') ? '' : ' disabled') . ' class="button">',
			),
		),
	);

	// We want to highlight the selected language. Need some Javascript for this.
	addInlineJavaScript('
	function highlightSelected(box)
	{
		$("tr.highlight2").removeClass("highlight2");
		$("#" + box).addClass("highlight2");
	}
	highlightSelected("list_language_list_' . ($language == '' ? 'english' : $language) . '");', true);

	// Display a warning if we cannot edit the default setting.
	if (!is_writable($boarddir . '/Settings.php'))
		$listOptions['additional_rows'][] = array(
			'position' => 'after_title',
			'value' => $txt['language_settings_writable'],
			'class' => 'smalltext alert',
		);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'language_list';
}

/**
 * How many languages?
 * Callback for the list in ManageLanguageSettings().
 *
 * @return int The number of available languages
 */
function list_getNumLanguages()
{
	return count(getLanguages());
}

/**
 * Fetch the actual language information.
 * Callback for $listOptions['get_items']['function'] in ManageLanguageSettings.
 * Determines which languages are available by looking for the "index.{language}.php" file.
 * Also figures out how many users are using a particular language.
 *
 * @return array An array of information about currenty installed languages
 */
function list_getLanguages()
{
	global $settings, $smcFunc, $language, $context, $txt;

	$languages = array();
	// Keep our old entries.
	$old_txt = $txt;
	$backup_actual_theme_dir = $settings['actual_theme_dir'];
	$backup_base_theme_dir = !empty($settings['base_theme_dir']) ? $settings['base_theme_dir'] : '';

	// Override these for now.
	$settings['actual_theme_dir'] = $settings['base_theme_dir'] = $settings['default_theme_dir'];
	getLanguages();

	// Put them back.
	$settings['actual_theme_dir'] = $backup_actual_theme_dir;
	if (!empty($backup_base_theme_dir))
		$settings['base_theme_dir'] = $backup_base_theme_dir;
	else
		unset($settings['base_theme_dir']);

	// Get the language files and data...
	foreach ($context['languages'] as $lang)
	{
		// Load the file to get the character set.
		require($settings['default_theme_dir'] . '/languages/index.' . $lang['filename'] . '.php');

		$languages[$lang['filename']] = array(
			'id' => $lang['filename'],
			'count' => 0,
			'char_set' => $txt['lang_character_set'],
			'default' => $language == $lang['filename'] || ($language == '' && $lang['filename'] == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => $smcFunc['ucwords'](strtr($lang['filename'], array('_' => ' ', '-utf8' => ''))),
		);
	}

	// Work out how many people are using each language.
	$request = $smcFunc['db_query']('', '
		SELECT lngfile, COUNT(*) AS num_users
		FROM {db_prefix}members
		GROUP BY lngfile',
		array(
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Default?
		if (empty($row['lngfile']) || !isset($languages[$row['lngfile']]))
			$row['lngfile'] = $language;

		if (!isset($languages[$row['lngfile']]) && isset($languages['english']))
			$languages['english']['count'] += $row['num_users'];
		elseif (isset($languages[$row['lngfile']]))
			$languages[$row['lngfile']]['count'] += $row['num_users'];
	}
	$smcFunc['db_free_result']($request);

	// Restore the current users language.
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

/**
 * Edit language related settings.
 *
 * @param bool $return_config Whether to return the $config_vars array (used in admin search)
 * @return void|array Returns nothing or the $config_vars array if $return_config is true
 */
function ModifyLanguageSettings($return_config = false)
{
	global $scripturl, $context, $txt, $boarddir, $sourcedir;

	// We'll want to save them someday.
	require_once $sourcedir . '/ManageServer.php';

	// Warn the user if the backup of Settings.php failed.
	$settings_not_writable = !is_writable($boarddir . '/Settings.php');
	$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		'language' => array('language', $txt['default_language'], 'file', 'select', array(), null, 'disabled' => $settings_not_writable),
		array('userLanguage', $txt['userLanguage'], 'db', 'check', null, 'userLanguage'),
	);

	call_integration_hook('integrate_language_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// Get our languages. No cache
	getLanguages(false);
	foreach ($context['languages'] as $lang)
		$config_vars['language'][4][$lang['filename']] = array($lang['filename'], $lang['name']);

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_language_settings', array(&$config_vars));

		saveSettings($config_vars);
		if (!$settings_not_writable && !$settings_backup_fail)
			$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=languages;sa=settings');
	}

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=languages;sa=settings;save';
	$context['settings_title'] = $txt['language_settings'];
	$context['save_disabled'] = $settings_not_writable;

	if ($settings_not_writable)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['settings_not_writable'] . '</strong></div><br>';
	elseif ($settings_backup_fail)
		$context['settings_message'] = '<div class="centertext"><strong>' . $txt['admin_backup_fail'] . '</strong></div><br>';

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

/**
 * Edit a particular set of language entries.
 */
function ModifyLanguage()
{
	global $settings, $context, $smcFunc, $txt, $modSettings, $boarddir, $sourcedir, $language, $cache_enable;

	loadLanguage('ManageSettings');

	// Select the languages tab.
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
	$context['page_title'] = $txt['edit_languages'];
	$context['sub_template'] = 'modify_language_entries';

	$context['lang_id'] = $_GET['lid'];
	list($theme_id, $file_id) = empty($_REQUEST['tfid']) || strpos($_REQUEST['tfid'], '+') === false ? array(1, '') : explode('+', $_REQUEST['tfid']);

	// Clean the ID - just in case.
	preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches);
	$context['lang_id'] = $matches[1];

	// Get all the theme data.
	$request = $smcFunc['db_query']('', '
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND id_member = {int:no_member}
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'default_theme' => 1,
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $settings['default_theme_dir'],
		),
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	$smcFunc['db_free_result']($request);

	// This will be where we look
	$lang_dirs = array();

	// There are different kinds of strings
	$string_types = array('txt', 'helptxt', 'editortxt', 'tztxt', 'txtBirthdayEmails');
	$additional_string_types = array();

	// Some files allow the admin to add and/or remove certain types of strings
	$allows_add_remove = array(
		'Timezones' => array(
			'add' => array('tztxt', 'txt'),
			'remove' => array('tztxt', 'txt'),
		),
		'Modifications' => array(
			'add' => array('txt'),
			'remove' => array('txt'),
		),
		'ThemeStrings' => array(
			'add' => array('txt'),
		),
	);

	// Does a hook need to add in some additional places to look for languages or info about how to handle them?
	call_integration_hook('integrate_modifylanguages', array(&$themes, &$lang_dirs, &$allows_add_remove, &$additional_string_types));

	$string_types = array_unique(array_merge($string_types, $additional_string_types));

	// Check we have themes with a path and a name - just in case - and add the path.
	foreach ($themes as $id => $data)
	{
		if (count($data) != 2)
			unset($themes[$id]);
		elseif (is_dir($data['theme_dir'] . '/languages'))
			$lang_dirs[$id] = $data['theme_dir'] . '/languages';

		// How about image directories?
		if (is_dir($data['theme_dir'] . '/images/' . $context['lang_id']))
			$images_dirs[$id] = $data['theme_dir'] . '/images/' . $context['lang_id'];
	}

	$current_file = $file_id ? $lang_dirs[$theme_id] . '/' . $file_id . '.' . $context['lang_id'] . '.php' : '';

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = array();
	foreach ($lang_dirs as $theme => $theme_dir)
	{
		// Open it up.
		$dir = dir($theme_dir);
		while ($entry = $dir->read())
		{
			// We're only after the files for this language.
			if (preg_match('~^([A-Za-z]+)\.' . $context['lang_id'] . '\.php$~', $entry, $matches) == 0)
				continue;

			if (!isset($context['possible_files'][$theme]))
				$context['possible_files'][$theme] = array(
					'id' => $theme,
					'name' => $themes[$theme]['name'],
					'files' => array(),
				);

			$context['possible_files'][$theme]['files'][] = array(
				'id' => $matches[1],
				'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
				'selected' => $theme_id == $theme && $file_id == $matches[1],
			);
		}
		$dir->close();
		usort($context['possible_files'][$theme]['files'], function($val1, $val2)
		{
			return strcmp($val1['name'], $val2['name']);
		});
	}

	// We no longer wish to speak this language.
	if (!empty($_POST['delete_main']) && $context['lang_id'] != 'english')
	{
		checkSession();
		validateToken('admin-mlang');

		// @todo Todo: FTP Controls?
		require_once($sourcedir . '/Subs-Package.php');

		// First, Make a backup?
		if (!empty($modSettings['package_make_backups']) && (!isset($_SESSION['last_backup_for']) || $_SESSION['last_backup_for'] != $context['lang_id'] . '$$$'))
		{
			$_SESSION['last_backup_for'] = $context['lang_id'] . '$$$';
			$result = package_create_backup('backup_lang_' . $context['lang_id']);
			if (!$result)
				fatal_lang_error('could_not_language_backup', false);
		}

		// Second, loop through the array to remove the files.
		foreach ($lang_dirs as $curPath)
		{
			foreach ($context['possible_files'][1]['files'] as $lang)
				if (file_exists($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php'))
					unlink($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php');

			// Check for the email template.
			if (file_exists($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php'))
				unlink($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php');
		}

		// Third, the agreement file.
		if (file_exists($boarddir . '/agreement.' . $context['lang_id'] . '.txt'))
			unlink($boarddir . '/agreement.' . $context['lang_id'] . '.txt');

		// Fourth, a related images folder, if it exists...
		if (!empty($images_dirs))
			foreach ($images_dirs as $curPath)
				if (is_dir($curPath))
					deltree($curPath);

		// Members can no longer use this language.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET lngfile = {empty}
			WHERE lngfile = {string:current_language}',
			array(
				'empty_string' => '',
				'current_language' => $context['lang_id'],
			)
		);

		// Fifth, update getLanguages() cache.
		if (!empty($cache_enable))
		{
			cache_put_data('known_languages', null, !empty($cache_enable) && $cache_enable < 1 ? 86400 : 3600);
		}

		// Sixth, if we deleted the default language, set us back to english?
		if ($context['lang_id'] == $language)
		{
			require_once($sourcedir . '/Subs-Admin.php');
			$language = 'english';
			updateSettingsFile(array('language' => '\'' . $language . '\''));
		}

		// Seventh, get out of here.
		redirectexit('action=admin;area=languages;sa=edit;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Saving primary settings?
	$primary_settings = array('native_name' => 'string', 'lang_character_set' => 'string', 'lang_locale' => 'string', 'lang_rtl' => 'bool', 'lang_dictionary' => 'string', 'lang_spelling' => 'string', 'lang_recaptcha' => 'string');
	$madeSave = false;
	if (!empty($_POST['save_main']) && !$current_file)
	{
		checkSession();
		validateToken('admin-mlang');

		// Read in the current file.
		$current_data = implode('', file($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php'));

		// Build the replacements. old => new
		$replace_array = array();
		foreach ($primary_settings as $setting => $type)
			$replace_array['~\$txt\[\'' . $setting . '\'\]\s*=\s*[^\r\n]+~'] = '$txt[\'' . $setting . '\'] = ' . ($type === 'bool' ? (!empty($_POST[$setting]) ? 'true' : 'false') : '\'' . ($setting = 'native_name' ? htmlentities(un_htmlspecialchars($_POST[$setting]), ENT_QUOTES, $context['character_set']) : preg_replace('~[^\w-]~i', '', $_POST[$setting])) . '\'') . ';';

		$current_data = preg_replace(array_keys($replace_array), array_values($replace_array), $current_data);
		$fp = fopen($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php', 'w+');
		fwrite($fp, $current_data);
		fclose($fp);

		$madeSave = true;
	}

	// Quickly load index language entries.
	$old_txt = $txt;
	require($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	$context['lang_file_not_writable_message'] = is_writable($settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php') ? '' : sprintf($txt['lang_file_not_writable'], $settings['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	// Setup the primary settings context.
	$context['primary_settings']['name'] = $smcFunc['ucwords'](strtr($context['lang_id'], array('_' => ' ', '-utf8' => '')));
	foreach ($primary_settings as $setting => $type)
	{
		$context['primary_settings'][$setting] = array(
			'label' => str_replace('lang_', '', $setting),
			'value' => $txt[$setting],
		);
	}

	// Restore normal service.
	$txt = $old_txt;

	// Are we saving?
	$save_strings = array();
	$remove_strings = array();
	$add_strings = array();
	if (isset($_POST['save_entries']))
	{
		checkSession();
		validateToken('admin-mlang');

		if (!empty($_POST['edit']))
		{
			foreach ($_POST['edit'] as $k => $v)
			{
				if (is_string($v))
				{
					// Only try to save if 'edit' was specified and if the string has changed
					if ($v == 'edit' && isset($_POST['entry'][$k]) && isset($_POST['comp'][$k]) && $_POST['entry'][$k] != $_POST['comp'][$k])
						$save_strings[$k] = cleanLangString($_POST['entry'][$k], false);

					// Record any add or remove requests. We'll decide on them later.
					elseif ($v == 'remove')
						$remove_strings[] = $k;
					elseif ($v == 'add' && isset($_POST['entry'][$k]))
					{
						$add_strings[$k] = array(
							'group' => isset($_POST['grp'][$k]) ? $_POST['grp'][$k] : 'txt',
							'string' => cleanLangString($_POST['entry'][$k], false),
						);
					}
				}
				elseif (is_array($v))
				{
					foreach ($v as $subk => $subv)
					{
						if ($subv == 'edit' && isset($_POST['entry'][$k][$subk]) && isset($_POST['comp'][$k][$subk]) && $_POST['entry'][$k][$subk] != $_POST['comp'][$k][$subk])
							$save_strings[$k][$subk] = cleanLangString($_POST['entry'][$k][$subk], false);

						elseif ($subv == 'remove')
							$remove_strings[$k][] = $subk;
						elseif ($subv == 'add' && isset($_POST['entry'][$k][$subk]))
						{
							$add_strings[$k][$subk] = array(
								'group' => isset($_POST['grp'][$k]) ? $_POST['grp'][$k] : 'txt',
								'string' => cleanLangString($_POST['entry'][$k][$subk], false),
							);
						}

					}
				}
			}
		}
	}

	// If we are editing a file work away at that.
	$context['can_add_lang_entry'] = array();
	if ($current_file)
	{
		$context['entries_not_writable_message'] = is_writable($current_file) ? '' : sprintf($txt['lang_entries_not_writable'], $current_file);

		// How many strings will PHP let us edit at once?
		// Each string needs 3 inputs, and there are 5 others in the form.
		$context['max_inputs'] = floor(ini_get('max_input_vars') / 3) - 5;

		// Do we want to override the helptxt for certain types of text variables?
		$special_groups = array(
			'Timezones' => array('txt' => 'txt_for_timezones'),
			'EmailTemplates' => array('txt' => 'txt_for_email_templates', 'txtBirthdayEmails' => 'txt_for_email_templates'),
		);
		call_integration_hook('integrate_language_edit_helptext', array(&$special_groups));

		// Determine which groups of strings (if any) allow adding new entries
		if (isset($allows_add_remove[$file_id]['add']))
		{
			foreach ($allows_add_remove[$file_id]['add'] as $var_group)
			{
				$group = !empty($special_groups[$file_id][$var_group]) ? $special_groups[$file_id][$var_group] : $var_group;
				if (in_array($var_group, $allows_add_remove[$file_id]['add']))
					$context['can_add_lang_entry'][$group] = true;
			}
		}

		// Read in the file's contents and process it into entries.
		// Also, remove any lines for uneditable variables like $forum_copyright from the working data.
		$entries = array();
		foreach (preg_split('~^(?=\$(?:' . implode('|', $string_types) . ')\[\'([^\n]+?)\'\])~m' . ($context['utf8'] ? 'u' : ''), preg_replace('~\s*\n(\$(?!(?:' . implode('|', $string_types) . '))[^\n]*)~', '', file_get_contents($current_file))) as $blob)
		{
			// Comment lines at the end of the blob can make terrible messes
			$blob = preg_replace('~(\n[ \t]*//[^\n]*)*$~' . ($context['utf8'] ? 'u' : ''), '', $blob);

			// Extract the variable
			if (preg_match('~^\$(' . implode('|', $string_types) . ')\[\'([^\n]+?)\'\](?:\[\'?([^\n]+?)\'?\])?\s?=\s?(.+);([ \t]*(?://[^\n]*)?)$~ms' . ($context['utf8'] ? 'u' : ''), strtr($blob, array("\r" => '')), $matches))
			{
				// If no valid subkey was found, we need it to be explicitly null
				$matches[3] = isset($matches[3]) && $matches[3] !== '' ? $matches[3] : null;

				// The point of this exercise
				$entries[$matches[2] . (isset($matches[3]) ? '[' . $matches[3] . ']' : '')] = array(
					'type' => $matches[1],
					'group' => !empty($special_groups[$file_id][$matches[1]]) ? $special_groups[$file_id][$matches[1]] : $matches[1],
					'can_remove' => isset($allows_add_remove[$file_id]['remove']) && in_array($matches[1], $allows_add_remove[$file_id]['remove']),
					'key' => $matches[2],
					'subkey' => $matches[3],
					'full' => $matches[0],
					'entry' => $matches[4],
					'cruft' => $matches[5],
				);
			}
		}

		// These will be the entries we can definitely save.
		$final_saves = array();

		$context['file_entries'] = array();
		foreach ($entries as $entryKey => $entryValue)
		{
			// Ignore some things we set separately.
			if (in_array($entryKey, array_keys($primary_settings)))
				continue;

			// These are arrays that need breaking out.
			if (strpos($entryValue['entry'], 'array(') === 0 && strpos($entryValue['entry'], ')', -1) === strlen($entryValue['entry']) - 1)
			{
				// No, you may not use multidimensional arrays of $txt strings. Madness stalks that path.
				if (isset($entryValue['subkey']))
					continue;

				// Trim off the array construct bits.
				$entryValue['entry'] = substr($entryValue['entry'], strpos($entryValue['entry'], 'array(') + 6, -1);

				// This crazy regex extracts each array element, even if the value contains commas or escaped quotes
				// The keys can be either integers or strings
				// The values must be strings, or the regex will fail
				$m = preg_match_all('/
					# Optional explicit key assignment
					(?:
						(?:
							\d+
							|
							(?:
								(?:
									\'(?:[^\']|(?<=\\\)\')*\'
								)
								|
								(?:
									"(?:[^"]|(?<=\\\)")*"
								)
							)
						)
						\s*=>\s*
					)?

					# String value in single or double quotes
					(?:
						(?:
							\'(?:[^\']|(?<=\\\)\')*\'
						)
						|
						(?:
							"(?:[^"]|(?<=\\\)")*"
						)
					)

					# Followed by a comma or the end of the string
					(?=,|$)

					/x' . ($context['utf8'] ? 'u' : ''), $entryValue['entry'], $matches);

				if (empty($m))
					continue;

				$entryValue['entry'] = $matches[0];

				// Now create an entry for each item.
				$cur_index = -1;
				$save_cache = array(
					'enabled' => false,
					'entries' => array(),
				);
				foreach ($entryValue['entry'] as $id => $subValue)
				{
					// Is this a new index?
					if (preg_match('~^(\d+|(?:(?:\'(?:[^\']|(?<=\\\)\')*\')|(?:"(?:[^"]|(?<=\\\)")*")))\s*=>~', $subValue, $matches))
					{
						$subKey = trim($matches[1], '\'"');

						if (ctype_digit($subKey))
							$cur_index = $subKey;

						$subValue = trim(substr($subValue, strpos($subValue, '=>') + 2));
					}
					else
						$subKey = ++$cur_index;

					// Clean up some bits.
					if (strpos($subValue, '\'') === 0)
						$subValue = trim($subValue, '\'');
					elseif (strpos($subValue, '"') === 0)
						$subValue = trim($subValue, '"');

					// Can we save?
					if (isset($save_strings[$entryKey][$subKey]))
					{
						$save_cache['entries'][$subKey] = strtr($save_strings[$entryKey][$subKey], array('\'' => ''));
						$save_cache['enabled'] = true;
					}
					// Should we remove this one?
					elseif (isset($remove_strings[$entryKey]) && in_array($subKey, $remove_strings[$entryKey]) && $entryValue['can_remove'])
						$save_cache['enabled'] = true;
					// Just keep this one as it is
					else
						$save_cache['entries'][$subKey] = $subValue;

					$context['file_entries'][$entryValue['group']][] = array(
						'key' => $entryKey,
						'subkey' => $subKey,
						'value' => $subValue,
						'rows' => 1,
						'can_remove' => $entryValue['can_remove'],
					);
				}

				// Should we add a new string to this array?
				if (!empty($context['can_add_lang_entry'][$entryValue['type']]) && isset($add_strings[$entryKey]))
				{
					foreach ($add_strings[$entryKey] as $string_key => $string_val)
						$save_cache['entries'][$string_key] = strtr($string_val['string'], array('\'' => ''));

					$save_cache['enabled'] = true;

					// Make sure we don't add this again as an independent line
					unset($add_strings[$entryKey][$string_key]);
					if (empty($add_strings[$entryKey]))
						unset($add_strings[$entryKey]);
				}

				// Do we need to save?
				if ($save_cache['enabled'])
				{
					// Format the string, checking the indexes first.
					$items = array();
					$cur_index = 0;
					foreach ($save_cache['entries'] as $k2 => $v2)
					{
						// Manually show the custom index.
						if ($k2 != $cur_index)
						{
							$items[] = $k2 . ' => \'' . $v2 . '\'';
							$cur_index = $k2;
						}
						else
							$items[] = '\'' . $v2 . '\'';

						$cur_index++;
					}
					// Now create the string!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '// ' . implode("\n// ", explode("\n", rtrim($entryValue['full'], "\n"))) . "\n" . '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = array(' . implode(', ', $items) . ');' . $entryValue['cruft'],
					);
				}
			}
			// A single array element, like: $txt['foo']['bar'] = 'baz';
			elseif (isset($entryValue['subkey']))
			{
				// Saving?
				if (isset($save_strings[$entryValue['key']][$entryValue['subkey']]) && $save_strings[$entryValue['key']][$entryValue['subkey']] != $entryValue['entry'])
				{
					if ($save_strings[$entryValue['key']][$entryValue['subkey']] == '')
						$save_strings[$entryValue['key']][$entryValue['subkey']] = '\'\'';

					// Preserve subkey as either digit or string
					$subKey = ctype_digit($entryValue['subkey']) ? $entryValue['subkey'] : '\'' . $entryValue['subkey'] . '\'';

					// We have a new value, so we should use it
					$entryValue['entry'] = $save_strings[$entryValue['key']][$entryValue['subkey']];

					// And save it
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '// ' . implode("\n// ", explode("\n", rtrim($entryValue['full'], "\n"))) . "\n" . '$' . $entryValue['type'] . '[\'' . $entryValue['key'] . '\'][' . $subKey . '] = ' . $save_strings[$entryValue['key']][$entryValue['subkey']] . ';' . $entryValue['cruft'],
					);
				}

				// Remove this entry only if it is allowed
				if (isset($remove_strings[$entryValue['key']]) && in_array($entryValue['subkey'], $remove_strings[$entryValue['key']]) && $entryValue['can_remove'])
				{
					$entryValue['entry'] = '\'\'';
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '// ' . implode("\n// ", explode("\n", rtrim($entryValue['full'], "\n"))) . "\n",
					);
				}

				$editing_string = cleanLangString($entryValue['entry'], true);
				$context['file_entries'][$entryValue['group']][] = array(
					'key' => $entryValue['key'],
					'subkey' => $entryValue['subkey'],
					'value' => $editing_string,
					'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
					'can_remove' => $entryValue['can_remove'],
				);
			}
			// A simple string entry
			else
			{
				// Saving?
				if (isset($save_strings[$entryValue['key']]) && $save_strings[$entryValue['key']] != $entryValue['entry'])
				{
					// @todo Fix this properly.
					if ($save_strings[$entryValue['key']] == '')
						$save_strings[$entryValue['key']] = '\'\'';

					// Set the new value.
					$entryValue['entry'] = $save_strings[$entryValue['key']];
					// And we know what to save now!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '// ' . implode("\n// ", explode("\n", rtrim($entryValue['full'], "\n"))) . "\n" . '$' . $entryValue['type'] . '[\'' . $entryValue['key'] . '\'] = ' . $save_strings[$entryValue['key']] . ';' . $entryValue['cruft'],
					);
				}
				// Remove this entry only if it is allowed
				if (in_array($entryValue['key'], $remove_strings) && $entryValue['can_remove'])
				{
					$entryValue['entry'] = '\'\'';
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '// ' . implode("\n// ", explode("\n", rtrim($entryValue['full'], "\n"))) . "\n",
					);
				}

				$editing_string = cleanLangString($entryValue['entry'], true);
				$context['file_entries'][$entryValue['group']][] = array(
					'key' => $entryValue['key'],
					'subkey' => null,
					'value' => $editing_string,
					'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
					'can_remove' => $entryValue['can_remove'],
				);
			}
		}

		// Do they want to add some brand new strings? Does this file allow that?
		if (!empty($add_strings) && !empty($allows_add_remove[$file_id]['add']))
		{
			$special_types = isset($special_groups[$file_id]) ? array_flip($special_groups[$file_id]) : array();

			foreach ($add_strings as $string_key => $string_val)
			{
				// Adding a normal string
				if (isset($string_val['string']) && is_string($string_val['string']))
				{
					$type = isset($special_types[$string_val['group']]) ? $special_types[$string_val['group']] : $string_val['group'];

					if (empty($context['can_add_lang_entry'][$type]))
						continue;

					$final_saves[$string_key] = array(
						'find' => "\s*\?" . '>$',
						'replace' => "\n\$" . $type . '[\'' . $string_key . '\'] = ' . $string_val['string'] . ';' . "\n\n?" . '>',
						'is_regex' => true,
					);
				}
				// Adding an array element
				else
				{
					foreach ($string_val as $substring_key => $substring_val)
					{
						$type = isset($special_types[$substring_val['group']]) ? $special_types[$substring_val['group']] : $substring_val['group'];

						if (empty($context['can_add_lang_entry'][$type]))
							continue;

						$subKey = ctype_digit(trim($substring_key, '\'')) ? trim($substring_key, '\'') : '\'' . $substring_key . '\'';

						$final_saves[$string_key . '[' . $substring_key . ']'] = array(
							'find' => "\s*\?" . '>$',
							'replace' => "\n\$" . $type . '[\'' . $string_key . '\'][' . $subKey . '] = ' . $substring_val['string'] . ';' . "\n\n?" . '>',
							'is_regex' => true,
						);
					}
				}
			}
		}

		// Any saves to make?
		if (!empty($final_saves))
		{
			checkSession();

			// Get a fresh copy of the file's current content.
			$file_contents = file_get_contents($current_file);

			// Apply our changes.
			foreach ($final_saves as $save)
			{
				if (!empty($save['is_regex']))
					$file_contents = preg_replace('~' . $save['find'] . '~' . ($context['utf8'] ? 'u' : ''), $save['replace'], $file_contents);
				else
					$file_contents = str_replace($save['find'], $save['replace'], $file_contents);
			}

			// Save the result back to the file.
			file_put_contents($current_file, $file_contents);

			$madeSave = true;
		}

		// Another restore.
		$txt = $old_txt;

		// If they can add new language entries, make sure the UI is set up for that.
		if (!empty($context['can_add_lang_entry']))
		{
			// Make sure the Add button has a place to show up.
			foreach ($context['can_add_lang_entry'] as $group => $value)
			{
				if (!isset($context['file_entries'][$group]))
					$context['file_entries'][$group] = array();
			}

			addInlineJavaScript('
				function add_lang_entry(group) {
					var key = prompt("' . $txt['languages_enter_key'] . '");

					if (key !== null) {
						++entry_num;

						var array_regex = /^(.*)(\[[^\[\]]*\])$/
						var result = array_regex.exec(key);
						if (result != null) {
							key = result[1];
							var subkey = result[2];
						} else {
							var subkey = "";
						}

						var bracket_regex = /[\[\]]/
						if (bracket_regex.test(key)) {
							alert("' . $txt['languages_invalid_key'] . '" + key + subkey);
							return;
						}

						$("#language_" + group).append("<dt><span>" + key + subkey + "</span></dt> <dd id=\"entry_" + entry_num + "\"><input id=\"entry_" + entry_num + "_edit\" class=\"entry_toggle\" type=\"checkbox\" name=\"edit[" + key + "]" + subkey + "\" value=\"add\" data-target=\"#entry_" + entry_num + "\" checked> <label for=\"entry_" + entry_num + "_edit\">' . $txt['edit'] . '</label> <input type=\"hidden\" class=\"entry_oldvalue\" name=\"grp[" + key + "]\" value=\"" + group + "\"> <textarea name=\"entry[" + key + "]" + subkey + "\" class=\"entry_textfield\" cols=\"40\" rows=\"1\" style=\"width: 96%; margin-bottom: 2em;\"></textarea></dd>");
					}
				};');

			addInlineJavaScript('
				$(".add_lang_entry_button").show();', true);
		}

		// Warn them if they try to submit more changes than the server can accept in a single request.
		// Also make it obvious that they cannot submit changes to both the primary settings and the entries at the same time.
		if (!empty($context['file_entries']))
		{
			addInlineJavaScript('
				max_inputs = ' . $context['max_inputs'] . ';
				num_inputs = 0;

				$(".entry_textfield").prop("disabled", true);
				$(".entry_oldvalue").prop("disabled", true);

				$(".entry_toggle").click(function() {
					var target_dd = $( $(this).data("target") );

					if ($(this).prop("checked") === true && $(this).val() === "edit") {
						if (++num_inputs <= max_inputs) {
							target_dd.find(".entry_oldvalue, .entry_textfield").prop("disabled", false);
						} else {
							alert("' . sprintf($txt['languages_max_inputs_warning'], $context['max_inputs']) . '");
							$(this).prop("checked", false);
						}
					} else {
						--num_inputs;
						target_dd.find(".entry_oldvalue, .entry_textfield").prop("disabled", true);
					}

					if (num_inputs > 0) {
						$("#primary_settings").trigger("reset");
						$("#primary_settings input").prop("disabled", true);
					} else {
						$("#primary_settings input").prop("disabled", false);
					}
				});

				$("#primary_settings input").change(function() {
					num_changed = 0;
					$("#primary_settings input:text").each(function(i, e) {
						if ($(e).data("orig") != $(e).val())
							num_changed++;
					});
					$("#primary_settings input:checkbox").each(function(i, e) {
						cur_val = $(e).is(":checked");
						orig_val = $(e).val == "true";
						if (cur_val != orig_val)
							num_changed++;
					});

					if (num_changed > 0) {
						$("#entry_fields").fadeOut();
					} else {
						$("#entry_fields").fadeIn();
					}
				});
				$("#reset_main").click(function() {
					$("#entry_fields").fadeIn();
				});', true);
		}
	}

	// If we saved, redirect.
	if ($madeSave)
		redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . (!empty($file_id) ? ';entries;tfid=' . $theme_id . rawurlencode('+') . $file_id : ''));

	createToken('admin-mlang');
}

/**
 * This function cleans language entries to/from display.
 *
 * @todo This function could be two functions?
 *
 * @param string $string The language string
 * @param bool $to_display Whether or not this is going to be displayed
 * @return string The cleaned string
 */
function cleanLangString($string, $to_display = true)
{
	global $smcFunc;

	// If going to display we make sure it doesn't have any HTML in it - etc.
	$new_string = '';
	if ($to_display)
	{
		// Are we in a string (0 = no, 1 = single quote, 2 = parsed)
		$in_string = 0;
		$is_escape = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// Handle escapes first.
			if ($string{$i} == '\\')
			{
				// Toggle the escape.
				$is_escape = !$is_escape;
				// If we're now escaped don't add this string.
				if ($is_escape)
					continue;
			}
			// Special case - parsed string with line break etc?
			elseif (($string{$i} == 'n' || $string{$i} == 't') && $in_string == 2 && $is_escape)
			{
				// Put the escape back...
				$new_string .= $string{$i} == 'n' ? "\n" : "\t";
				$is_escape = false;
				continue;
			}
			// Have we got a single quote?
			elseif ($string{$i} == '\'')
			{
				// Already in a parsed string, or escaped in a linear string, means we print it - otherwise something special.
				if ($in_string != 2 && ($in_string != 1 || !$is_escape))
				{
					// Is it the end of a single quote string?
					if ($in_string == 1)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 1;

					// Don't actually include this character!
					continue;
				}
			}
			// Otherwise a double quote?
			elseif ($string{$i} == '"')
			{
				// Already in a single quote string, or escaped in a parsed string, means we print it - otherwise something special.
				if ($in_string != 1 && ($in_string != 2 || !$is_escape))
				{
					// Is it the end of a double quote string?
					if ($in_string == 2)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 2;

					// Don't actually include this character!
					continue;
				}
			}
			// A join/space outside of a string is simply removed.
			elseif ($in_string == 0 && (empty($string{$i}) || $string{$i} == '.'))
				continue;
			// Start of a variable?
			elseif ($in_string == 0 && $string{$i} == '$')
			{
				// Find the whole of it!
				preg_match('~([\$A-Za-z0-9\'\[\]_-]+)~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					// Come up with some pseudo thing to indicate this is a var.
					/**
					 * @todo Do better than this, please!
					 */
					$new_string .= '{%' . $matches[1] . '%}';

					// We're not going to reparse this.
					$i += strlen($matches[1]) - 1;
				}

				continue;
			}
			// Right, if we're outside of a string we have DANGER, DANGER!
			elseif ($in_string == 0)
			{
				continue;
			}

			// Actually add the character to the string!
			$new_string .= $string{$i};
			// If anything was escaped it ain't any longer!
			$is_escape = false;
		}

		// Unhtml then rehtml the whole thing!
		$new_string = $smcFunc['htmlspecialchars'](un_htmlspecialchars($new_string));
	}
	else
	{
		// Keep track of what we're doing...
		$in_string = 0;
		// This is for deciding whether to HTML a quote.
		$in_html = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// We don't do parsed strings apart from for breaks.
			if ($in_string == 2)
			{
				$in_string = 0;
				$new_string .= '"';
			}

			// Not in a string yet?
			if ($in_string != 1)
			{
				$in_string = 1;
				$new_string .= ($new_string ? ' . ' : '') . '\'';
			}

			// Is this a variable?
			if ($string{$i} == '{' && $string{$i + 1} == '%' && $string{$i + 2} == '$')
			{
				// Grab the variable.
				preg_match('~\{%([\$A-Za-z0-9\'\[\]_-]+)%\}~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					if ($in_string == 1)
						$new_string .= '\' . ';
					elseif ($new_string)
						$new_string .= ' . ';

					$new_string .= $matches[1];
					$i += strlen($matches[1]) + 3;
					$in_string = 0;
				}

				continue;
			}
			// Is this a lt sign?
			elseif ($string{$i} == '<')
			{
				// Probably HTML?
				if ($string{$i + 1} != ' ')
					$in_html = true;
				// Assume we need an entity...
				else
				{
					$new_string .= '&lt;';
					continue;
				}
			}
			// What about gt?
			elseif ($string{$i} == '>')
			{
				// Will it be HTML?
				if ($in_html)
					$in_html = false;
				// Otherwise we need an entity...
				else
				{
					$new_string .= '&gt;';
					continue;
				}
			}
			// Is it a slash? If so escape it...
			if ($string{$i} == '\\')
				$new_string .= '\\';
			// The infamous double quote?
			elseif ($string{$i} == '"')
			{
				// If we're in HTML we leave it as a quote - otherwise we entity it.
				if (!$in_html)
				{
					$new_string .= '&quot;';
					continue;
				}
			}
			// A single quote?
			elseif ($string{$i} == '\'')
			{
				// Must be in a string so escape it.
				$new_string .= '\\';
			}

			// Finally add the character to the string!
			$new_string .= $string{$i};
		}

		// If we ended as a string then close it off.
		if ($in_string == 1)
			$new_string .= '\'';
		elseif ($in_string == 2)
			$new_string .= '"';
	}

	return $new_string;
}

?>