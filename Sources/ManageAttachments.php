<?php

/**
 * This file doing the job of attachments and avatars maintenance and management.
 *
 * @todo refactor as controller-model
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
 * The main 'Attachments and Avatars' management function.
 * This function is the entry point for index.php?action=admin;area=manageattachments
 * and it calls a function based on the sub-action.
 * It requires the manage_attachments permission.
 *
 * @uses ManageAttachments template.
 * @uses Admin language file.
 * @uses template layer 'manage_files' for showing the tab bar.
 *
 */
function ManageAttachments()
{
	global $txt, $context;

	// You have to be able to moderate the forum to do this.
	isAllowedTo('manage_attachments');

	// Setup the template stuff we'll probably need.
	loadTemplate('ManageAttachments');

	// If they want to delete attachment(s), delete them. (otherwise fall through..)
	$subActions = array(
		'attachments' => 'ManageAttachmentSettings',
		'attachpaths' => 'ManageAttachmentPaths',
		'avatars' => 'ManageAvatarSettings',
		'browse' => 'BrowseFiles',
		'byAge' => 'RemoveAttachmentByAge',
		'bySize' => 'RemoveAttachmentBySize',
		'maintenance' => 'MaintainFiles',
		'repair' => 'RepairAttachments',
		'remove' => 'RemoveAttachment',
		'removeall' => 'RemoveAllAttachments',
		'transfer' => 'TransferAttachments',
	);

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['attachments_avatars'],
		'help' => 'manage_files',
		'description' => $txt['attachments_desc'],
	);

	call_integration_hook('integrate_manage_attachments', array(&$subActions));

	// Pick the correct sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'browse';

	// Default page title is good.
	$context['page_title'] = $txt['attachments_avatars'];

	// Finally fall through to what we are doing.
	call_helper($subActions[$context['sub_action']]);
}

/**
 * Allows to show/change attachment settings.
 * This is the default sub-action of the 'Attachments and Avatars' center.
 * Called by index.php?action=admin;area=manageattachments;sa=attachments.
 *
 * @param bool $return_config Whether to return the array of config variables (used for admin search)
 * @return void|array If $return_config is true, simply returns the config_vars array, otherwise returns nothing
 * @uses 'attachments' sub template.
 */

function ManageAttachmentSettings($return_config = false)
{
	global $smcFunc, $txt, $modSettings, $scripturl, $context, $sourcedir, $boarddir;

	require_once($sourcedir . '/Subs-Attachments.php');

	$context['attachmentUploadDir'] = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];

	// If not set, show a default path for the base directory
	if (!isset($_GET['save']) && empty($modSettings['basedirectory_for_attachments']))
		if (is_dir($modSettings['attachmentUploadDir'][1]))
			$modSettings['basedirectory_for_attachments'] = $modSettings['attachmentUploadDir'][1];

		else
			$modSettings['basedirectory_for_attachments'] = $context['attachmentUploadDir'];

	$context['valid_upload_dir'] = is_dir($context['attachmentUploadDir']) && is_writable($context['attachmentUploadDir']);

	if (!empty($modSettings['automanage_attachments']))
		$context['valid_basedirectory'] = !empty($modSettings['basedirectory_for_attachments']) && is_writable($modSettings['basedirectory_for_attachments']);

	else
		$context['valid_basedirectory'] = true;

	// A bit of razzle dazzle with the $txt strings. :)
	$txt['attachment_path'] = $context['attachmentUploadDir'];
	$txt['basedirectory_for_attachments_path'] = isset($modSettings['basedirectory_for_attachments']) ? $modSettings['basedirectory_for_attachments'] : '';
	$txt['use_subdirectories_for_attachments_note'] = empty($modSettings['attachment_basedirectories']) || empty($modSettings['use_subdirectories_for_attachments']) ? $txt['use_subdirectories_for_attachments_note'] : '';
	$txt['attachmentUploadDir_multiple_configure'] = '<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">[' . $txt['attachmentUploadDir_multiple_configure'] . ']</a>';
	$txt['attach_current_dir'] = empty($modSettings['automanage_attachments']) ? $txt['attach_current_dir'] : $txt['attach_last_dir'];
	$txt['attach_current_dir_warning'] = $txt['attach_current_dir'] . $txt['attach_current_dir_warning'];
	$txt['basedirectory_for_attachments_warning'] = $txt['basedirectory_for_attachments_current'] . $txt['basedirectory_for_attachments_warning'];

	// Perform a test to see if the GD module or ImageMagick are installed.
	$testImg = get_extension_funcs('gd') || class_exists('Imagick') || get_extension_funcs('MagickWand');

	// See if we can find if the server is set up to support the attachment limits
	$post_max_kb = floor(memoryReturnBytes(ini_get('post_max_size')) / 1024);
	$file_max_kb = floor(memoryReturnBytes(ini_get('upload_max_filesize')) / 1024);

	$config_vars = array(
		array('title', 'attachment_manager_settings'),
		// Are attachments enabled?
		array('select', 'attachmentEnable', array($txt['attachmentEnable_deactivate'], $txt['attachmentEnable_enable_all'], $txt['attachmentEnable_disable_new'])),
		'',

		// Directory and size limits.
		array('select', 'automanage_attachments', array(0 => $txt['attachments_normal'], 1 => $txt['attachments_auto_space'], 2 => $txt['attachments_auto_years'], 3 => $txt['attachments_auto_months'], 4 => $txt['attachments_auto_16'])),
		array('check', 'use_subdirectories_for_attachments', 'subtext' => $txt['use_subdirectories_for_attachments_note']),
		(empty($modSettings['attachment_basedirectories']) ? array('text', 'basedirectory_for_attachments', 40,) : array('var_message', 'basedirectory_for_attachments', 'message' => 'basedirectory_for_attachments_path', 'invalid' => empty($context['valid_basedirectory']), 'text_label' => (!empty($context['valid_basedirectory']) ? $txt['basedirectory_for_attachments_current'] : $txt['basedirectory_for_attachments_warning']))),
		empty($modSettings['attachment_basedirectories']) && $modSettings['currentAttachmentUploadDir'] == 1 && count($modSettings['attachmentUploadDir']) == 1 ? array('json', 'attachmentUploadDir', 'subtext' => $txt['attachmentUploadDir_multiple_configure'], 40, 'invalid' => !$context['valid_upload_dir'], 'disabled' => true) : array('var_message', 'attach_current_directory', 'subtext' => $txt['attachmentUploadDir_multiple_configure'], 'message' => 'attachment_path', 'invalid' => empty($context['valid_upload_dir']), 'text_label' => (!empty($context['valid_upload_dir']) ? $txt['attach_current_dir'] : $txt['attach_current_dir_warning'])),
		array('int', 'attachmentDirFileLimit', 'subtext' => $txt['zero_for_no_limit'], 6),
		array('int', 'attachmentDirSizeLimit', 'subtext' => $txt['zero_for_no_limit'], 6, 'postinput' => $txt['kilobyte']),
		array('check', 'dont_show_attach_under_post', 'subtext' => $txt['dont_show_attach_under_post_sub']),
		'',

		// Posting limits
		array('int', 'attachmentPostLimit', 'subtext' => sprintf($txt['attachment_ini_max'], $post_max_kb . ' ' . $txt['kilobyte']), 6, 'postinput' => $txt['kilobyte'], 'min' => 1, 'max' => $post_max_kb, 'disabled' => empty($post_max_kb)),
		array('int', 'attachmentSizeLimit', 'subtext' => sprintf($txt['attachment_ini_max'], $file_max_kb . ' ' . $txt['kilobyte']), 6, 'postinput' => $txt['kilobyte'], 'min' => 1, 'max' => $file_max_kb, 'disabled' => empty($file_max_kb)),
		array('int', 'attachmentNumPerPostLimit', 'subtext' => $txt['zero_for_no_limit'], 6),
		// Security Items
		array('title', 'attachment_security_settings'),
		// Extension checks etc.
		array('check', 'attachmentCheckExtensions'),
		array('text', 'attachmentExtensions', 40),
		'',

		// Image checks.
		array('warning', empty($testImg) ? 'attachment_img_enc_warning' : ''),
		array('check', 'attachment_image_reencode'),
		'',

		array('warning', 'attachment_image_paranoid_warning'),
		array('check', 'attachment_image_paranoid'),
		// Thumbnail settings.
		array('title', 'attachment_thumbnail_settings'),
		array('check', 'attachmentShowImages'),
		array('check', 'attachmentThumbnails'),
		array('check', 'attachment_thumb_png'),
		array('check', 'attachment_thumb_memory'),
		array('warning', 'attachment_thumb_memory_note'),
		array('text', 'attachmentThumbWidth', 6),
		array('text', 'attachmentThumbHeight', 6),
		'',

		array('int', 'max_image_width', 'subtext' => $txt['zero_for_no_limit']),
		array('int', 'max_image_height', 'subtext' => $txt['zero_for_no_limit']),
	);

	$context['settings_post_javascript'] = '
	var storing_type = document.getElementById(\'automanage_attachments\');
	var base_dir = document.getElementById(\'use_subdirectories_for_attachments\');

	createEventListener(storing_type)
	storing_type.addEventListener("change", toggleSubDir, false);
	createEventListener(base_dir)
	base_dir.addEventListener("change", toggleSubDir, false);
	toggleSubDir();';

	call_integration_hook('integrate_modify_attachment_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// These are very likely to come in handy! (i.e. without them we're doomed!)
	require_once($sourcedir . '/ManagePermissions.php');
	require_once($sourcedir . '/ManageServer.php');

	// Saving settings?
	if (isset($_GET['save']))
	{
		checkSession();

		if (isset($_POST['attachmentUploadDir']))
			unset($_POST['attachmentUploadDir']);

		if (!empty($_POST['use_subdirectories_for_attachments']))
		{
			if (isset($_POST['use_subdirectories_for_attachments']) && empty($_POST['basedirectory_for_attachments']))
				$_POST['basedirectory_for_attachments'] = (!empty($modSettings['basedirectory_for_attachments']) ? ($modSettings['basedirectory_for_attachments']) : $boarddir);

			if (!empty($_POST['use_subdirectories_for_attachments']) && !empty($modSettings['attachment_basedirectories']))
			{
				if (!is_array($modSettings['attachment_basedirectories']))
					$modSettings['attachment_basedirectories'] = $smcFunc['json_decode']($modSettings['attachment_basedirectories'], true);
			}
			else
				$modSettings['attachment_basedirectories'] = array();

			if (!empty($_POST['use_subdirectories_for_attachments']) && !empty($_POST['basedirectory_for_attachments']) && !in_array($_POST['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']))
			{
				$currentAttachmentUploadDir = $modSettings['currentAttachmentUploadDir'];

				if (!in_array($_POST['basedirectory_for_attachments'], $modSettings['attachmentUploadDir']))
				{
					if (!automanage_attachments_create_directory($_POST['basedirectory_for_attachments']))
						$_POST['basedirectory_for_attachments'] = $modSettings['basedirectory_for_attachments'];
				}

				if (!in_array($_POST['basedirectory_for_attachments'], $modSettings['attachment_basedirectories']))
				{
					$modSettings['attachment_basedirectories'][$modSettings['currentAttachmentUploadDir']] = $_POST['basedirectory_for_attachments'];
					updateSettings(array(
						'attachment_basedirectories' => $smcFunc['json_encode']($modSettings['attachment_basedirectories']),
						'currentAttachmentUploadDir' => $currentAttachmentUploadDir,
					));

					$_POST['use_subdirectories_for_attachments'] = 1;
					$_POST['attachmentUploadDir'] = $smcFunc['json_encode']($modSettings['attachmentUploadDir']);
				}
			}
		}

		call_integration_hook('integrate_save_attachment_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=manageattachments;sa=attachments');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=manageattachments;save;sa=attachments';
	prepareDBSettingContext($config_vars);

	$context['sub_template'] = 'show_settings';
}

/**
 * This allows to show/change avatar settings.
 * Called by index.php?action=admin;area=manageattachments;sa=avatars.
 * Show/set permissions for permissions: 'profile_server_avatar',
 * 	'profile_upload_avatar' and 'profile_remote_avatar'.
 *
 * @param bool $return_config Whether to return the config_vars array (used for admin search)
 * @return void|array Returns the config_vars array if $return_config is true, otherwise returns nothing
 * @uses 'avatars' sub template.
 */
function ManageAvatarSettings($return_config = false)
{
	global $txt, $context, $modSettings, $sourcedir, $scripturl;
	global $boarddir, $boardurl;

	// Perform a test to see if the GD module or ImageMagick are installed.
	$testImg = get_extension_funcs('gd') || class_exists('Imagick');

	$context['valid_avatar_dir'] = is_dir($modSettings['avatar_directory']);
	$context['valid_custom_avatar_dir'] = !empty($modSettings['custom_avatar_dir']) && is_dir($modSettings['custom_avatar_dir']) && is_writable($modSettings['custom_avatar_dir']);

	$config_vars = array(
		// Server stored avatars!
		array('title', 'avatar_server_stored'),
		array('warning', empty($testImg) ? 'avatar_img_enc_warning' : ''),
		array('permissions', 'profile_server_avatar', 0, $txt['avatar_server_stored_groups']),
		array('warning', !$context['valid_avatar_dir'] ? 'avatar_directory_wrong' : ''),
		array('text', 'avatar_directory', 40, 'invalid' => !$context['valid_avatar_dir']),
		array('text', 'avatar_url', 40),
		// External avatars?
		array('title', 'avatar_external'),
		array('permissions', 'profile_remote_avatar', 0, $txt['avatar_external_url_groups']),
		array('check', 'avatar_download_external', 0, 'onchange' => 'fUpdateStatus();'),
		array('text', 'avatar_max_width_external', 'subtext' => $txt['zero_for_no_limit'], 6),
		array('text', 'avatar_max_height_external', 'subtext' => $txt['zero_for_no_limit'], 6),
		array('select', 'avatar_action_too_large',
			array(
				'option_refuse' => $txt['option_refuse'],
				'option_css_resize' => $txt['option_css_resize'],
				'option_download_and_resize' => $txt['option_download_and_resize'],
			),
		),
		// Uploadable avatars?
		array('title', 'avatar_upload'),
		array('permissions', 'profile_upload_avatar', 0, $txt['avatar_upload_groups']),
		array('text', 'avatar_max_width_upload', 'subtext' => $txt['zero_for_no_limit'], 6),
		array('text', 'avatar_max_height_upload', 'subtext' => $txt['zero_for_no_limit'], 6),
		array('check', 'avatar_resize_upload', 'subtext' => $txt['avatar_resize_upload_note']),
		array('check', 'avatar_download_png'),
		array('check', 'avatar_reencode'),
		'',

		array('warning', 'avatar_paranoid_warning'),
		array('check', 'avatar_paranoid'),
		'',

		array('warning', !$context['valid_custom_avatar_dir'] ? 'custom_avatar_dir_wrong' : ''),
		array('text', 'custom_avatar_dir', 40, 'subtext' => $txt['custom_avatar_dir_desc'], 'invalid' => !$context['valid_custom_avatar_dir']),
		array('text', 'custom_avatar_url', 40),
		// Grvatars?
		array('title', 'gravatar_settings'),
		array('check', 'gravatarEnabled'),
		array('check', 'gravatarOverride'),
		array('check', 'gravatarAllowExtraEmail'),
		'',

		array('select', 'gravatarMaxRating',
			array(
				'G' => $txt['gravatar_maxG'],
				'PG' => $txt['gravatar_maxPG'],
				'R' => $txt['gravatar_maxR'],
				'X' => $txt['gravatar_maxX'],
			),
		),
		array('select', 'gravatarDefault',
			array(
				'mm' => $txt['gravatar_mm'],
				'identicon' => $txt['gravatar_identicon'],
				'monsterid' => $txt['gravatar_monsterid'],
				'wavatar' => $txt['gravatar_wavatar'],
				'retro' => $txt['gravatar_retro'],
				'blank' => $txt['gravatar_blank'],
			),
		),
	);

	call_integration_hook('integrate_modify_avatar_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	// We need this file for the settings template.
	require_once($sourcedir . '/ManageServer.php');

	// Saving avatar settings?
	if (isset($_GET['save']))
	{
		checkSession();

		// These settings cannot be left empty!
		if (empty($_POST['custom_avatar_dir']))
			$_POST['custom_avatar_dir'] = $boarddir . '/custom_avatar';

		if (empty($_POST['custom_avatar_url']))
			$_POST['custom_avatar_url'] = $boardurl . '/custom_avatar';

		if (empty($_POST['avatar_directory']))
			$_POST['avatar_directory'] = $boarddir . '/avatars';

		if (empty($_POST['avatar_url']))
			$_POST['avatar_url'] = $boardurl . '/avatars';

		call_integration_hook('integrate_save_avatar_settings');

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=manageattachments;sa=avatars');
	}

	// Attempt to figure out if the admin is trying to break things.
	$context['settings_save_onclick'] = 'return (document.getElementById(\'custom_avatar_dir\').value == \'\' || document.getElementById(\'custom_avatar_url\').value == \'\') ? confirm(\'' . $txt['custom_avatar_check_empty'] . '\') : true;';

	// We need this for the in-line permissions
	createToken('admin-mp');

	// Prepare the context.
	$context['post_url'] = $scripturl . '?action=admin;area=manageattachments;save;sa=avatars';
	prepareDBSettingContext($config_vars);

	// Add a layer for the javascript.
	$context['template_layers'][] = 'avatar_settings';
	$context['sub_template'] = 'show_settings';
}

/**
 * Show a list of attachment or avatar files.
 * Called by ?action=admin;area=manageattachments;sa=browse for attachments
 *  and ?action=admin;area=manageattachments;sa=browse;avatars for avatars.
 * Allows sorting by name, date, size and member.
 * Paginates results.
 */
function BrowseFiles()
{
	global $context, $txt, $scripturl, $modSettings;
	global $smcFunc, $sourcedir, $settings;

	// Attachments or avatars?
	$context['browse_type'] = isset($_REQUEST['avatars']) ? 'avatars' : (isset($_REQUEST['thumbs']) ? 'thumbs' : 'attachments');

	$titles = array(
		'attachments' => array('?action=admin;area=manageattachments;sa=browse', $txt['attachment_manager_attachments']),
		'avatars' => array('?action=admin;area=manageattachments;sa=browse;avatars', $txt['attachment_manager_avatars']),
		'thumbs' => array('?action=admin;area=manageattachments;sa=browse;thumbs', $txt['attachment_manager_thumbs']),
	);

	$list_title = $txt['attachment_manager_browse_files'] . ': ';
	foreach ($titles as $browse_type => $details)
	{
		if ($browse_type != 'attachments')
			$list_title .= ' | ';

		if ($context['browse_type'] == $browse_type)
			$list_title .= '<img src="' . $settings['images_url'] . '/selected.png" alt="&gt;"> ';

		$list_title .= '<a href="' . $scripturl . $details[0] . '">' . $details[1] . '</a>';
	}

	// Set the options for the list component.
	$listOptions = array(
		'id' => 'file_list',
		'title' => $list_title,
		'items_per_page' => $modSettings['defaultMaxListItems'],
		'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=browse' . ($context['browse_type'] === 'avatars' ? ';avatars' : ($context['browse_type'] === 'thumbs' ? ';thumbs' : '')),
		'default_sort_col' => 'name',
		'no_items_label' => $txt['attachment_manager_' . ($context['browse_type'] === 'avatars' ? 'avatars' : ($context['browse_type'] === 'thumbs' ? 'thumbs' : 'attachments')) . '_no_entries'],
		'get_items' => array(
			'function' => 'list_getFiles',
			'params' => array(
				$context['browse_type'],
			),
		),
		'get_count' => array(
			'function' => 'list_getNumFiles',
			'params' => array(
				$context['browse_type'],
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['attachment_name'],
				),
				'data' => array(
					'function' => function($rowData) use ($modSettings, $context, $scripturl, $smcFunc)
					{
						$link = '<a href="';

						// In case of a custom avatar URL attachments have a fixed directory.
						if ($rowData['attachment_type'] == 1)
							$link .= sprintf('%1$s/%2$s', $modSettings['custom_avatar_url'], $rowData['filename']);

						// By default avatars are downloaded almost as attachments.
						elseif ($context['browse_type'] == 'avatars')
							$link .= sprintf('%1$s?action=dlattach;type=avatar;attach=%2$d', $scripturl, $rowData['id_attach']);

						// Normal attachments are always linked to a topic ID.
						else
							$link .= sprintf('%1$s?action=dlattach;topic=%2$d.0;attach=%3$d', $scripturl, $rowData['id_topic'], $rowData['id_attach']);

						$link .= '"';

						// Show a popup on click if it's a picture and we know its dimensions.
						if (!empty($rowData['width']) && !empty($rowData['height']))
							$link .= sprintf(' onclick="return reqWin(this.href' . ($rowData['attachment_type'] == 1 ? '' : ' + \';image\'') . ', %1$d, %2$d, true);"', $rowData['width'] + 20, $rowData['height'] + 20);

						$link .= sprintf('>%1$s</a>', preg_replace('~&amp;#(\\\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\\\1;', $smcFunc['htmlspecialchars']($rowData['filename'])));

						// Show the dimensions.
						if (!empty($rowData['width']) && !empty($rowData['height']))
							$link .= sprintf(' <span class="smalltext">%1$dx%2$d</span>', $rowData['width'], $rowData['height']);

						return $link;
					},
				),
				'sort' => array(
					'default' => 'a.filename',
					'reverse' => 'a.filename DESC',
				),
			),
			'filesize' => array(
				'header' => array(
					'value' => $txt['attachment_file_size'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt)
					{
						return sprintf('%1$s%2$s', round($rowData['size'] / 1024, 2), $txt['kilobyte']);
					},
				),
				'sort' => array(
					'default' => 'a.size',
					'reverse' => 'a.size DESC',
				),
			),
			'member' => array(
				'header' => array(
					'value' => $context['browse_type'] == 'avatars' ? $txt['attachment_manager_member'] : $txt['posted_by'],
				),
				'data' => array(
					'function' => function($rowData) use ($scripturl, $smcFunc)
					{
						// In case of an attachment, return the poster of the attachment.
						if (empty($rowData['id_member']))
							return $smcFunc['htmlspecialchars']($rowData['poster_name']);

						// Otherwise it must be an avatar, return the link to the owner of it.
						else
							return sprintf('<a href="%1$s?action=profile;u=%2$d">%3$s</a>', $scripturl, $rowData['id_member'], $rowData['poster_name']);
					},
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'date' => array(
				'header' => array(
					'value' => $context['browse_type'] == 'avatars' ? $txt['attachment_manager_last_active'] : $txt['date'],
				),
				'data' => array(
					'function' => function($rowData) use ($txt, $context, $scripturl)
					{
						// The date the message containing the attachment was posted or the owner of the avatar was active.
						$date = empty($rowData['poster_time']) ? $txt['never'] : timeformat($rowData['poster_time']);

						// Add a link to the topic in case of an attachment.
						if ($context['browse_type'] !== 'avatars')
							$date .= sprintf('<br>%1$s <a href="%2$s?topic=%3$d.msg%4$d#msg%4$d">%5$s</a>', $txt['in'], $scripturl, $rowData['id_topic'], $rowData['id_msg'], $rowData['subject']);

						return $date;
					},
				),
				'sort' => array(
					'default' => $context['browse_type'] === 'avatars' ? 'mem.last_login' : 'm.id_msg',
					'reverse' => $context['browse_type'] === 'avatars' ? 'mem.last_login DESC' : 'm.id_msg DESC',
				),
			),
			'downloads' => array(
				'header' => array(
					'value' => $txt['downloads'],
				),
				'data' => array(
					'db' => 'downloads',
					'comma_format' => true,
				),
				'sort' => array(
					'default' => 'a.downloads',
					'reverse' => 'a.downloads DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'centercol',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[%1$d]">',
						'params' => array(
							'id_attach' => false,
						),
					),
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=manageattachments;sa=remove' . ($context['browse_type'] === 'avatars' ? ';avatars' : ($context['browse_type'] === 'thumbs' ? ';thumbs' : '')),
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				'type' => $context['browse_type'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'above_table_headers',
				'value' => '<input type="submit" name="remove_submit" class="button you_sure" value="' . $txt['quickmod_delete_selected'] . '" data-confirm="' . $txt['confirm_delete_attachments'] . '">',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="remove_submit" class="button you_sure" value="' . $txt['quickmod_delete_selected'] . '" data-confirm="' . $txt['confirm_delete_attachments'] . '">',
			),
		),
	);

	// Does a hook want to display their attachments better?
	call_integration_hook('integrate_attachments_browse', array(&$listOptions, &$titles, &$list_title));

	// Create the list.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	$context['sub_template'] = 'show_list';
	$context['default_list'] = 'file_list';
}

/**
 * Returns the list of attachments files (avatars or not), recorded
 * in the database, per the parameters received.
 *
 * @param int $start The item to start with
 * @param int $items_per_page How many items to show per page
 * @param string $sort A string indicating how to sort results
 * @param string $browse_type can be one of 'avatars' or ... not. :P
 * @return array An array of file info
 */
function list_getFiles($start, $items_per_page, $sort, $browse_type)
{
	global $smcFunc, $txt;

	// Choose a query depending on what we are viewing.
	if ($browse_type === 'avatars')
		$request = $smcFunc['db_query']('', '
			SELECT
				{string:blank_text} AS id_msg, COALESCE(mem.real_name, {string:not_applicable_text}) AS poster_name,
				mem.last_login AS poster_time, 0 AS id_topic, a.id_member, a.id_attach, a.filename, a.file_hash, a.attachment_type,
				a.size, a.width, a.height, a.downloads, {string:blank_text} AS subject, 0 AS id_board
			FROM {db_prefix}attachments AS a
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)
			WHERE a.id_member != {int:guest_id}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:per_page}',
			array(
				'guest_id' => 0,
				'blank_text' => '',
				'not_applicable_text' => $txt['not_applicable'],
				'sort' => $sort,
				'start' => $start,
				'per_page' => $items_per_page,
			)
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT
				m.id_msg, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.id_topic, m.id_member,
				a.id_attach, a.filename, a.file_hash, a.attachment_type, a.size, a.width, a.height, a.downloads, mf.subject, t.id_board
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE a.attachment_type = {int:attachment_type}
			ORDER BY {raw:sort}
			LIMIT {int:start}, {int:per_page}',
			array(
				'attachment_type' => $browse_type == 'thumbs' ? '3' : '0',
				'sort' => $sort,
				'start' => $start,
				'per_page' => $items_per_page,
			)
		);
	$files = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$files[] = $row;
	$smcFunc['db_free_result']($request);

	return $files;
}

/**
 * Return the number of files of the specified type recorded in the database.
 * (the specified type being attachments or avatars).
 *
 * @param string $browse_type can be one of 'avatars' or not. (in which case they're attachments)
 * @return int The number of files
 */
function list_getNumFiles($browse_type)
{
	global $smcFunc;

	// Depending on the type of file, different queries are used.
	if ($browse_type === 'avatars')
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}attachments
			WHERE id_member != {int:guest_id_member}',
			array(
				'guest_id_member' => 0,
			)
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS num_attach
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			WHERE a.attachment_type = {int:attachment_type}
				AND a.id_member = {int:guest_id_member}',
			array(
				'attachment_type' => $browse_type === 'thumbs' ? '3' : '0',
				'guest_id_member' => 0,
			)
		);

	list ($num_files) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return $num_files;
}

/**
 * Show several file maintenance options.
 * Called by ?action=admin;area=manageattachments;sa=maintain.
 * Calculates file statistics (total file size, number of attachments,
 * number of avatars, attachment space available).
 *
 * @uses the 'maintain' sub template.
 */
function MaintainFiles()
{
	global $context, $modSettings, $smcFunc;

	$context['sub_template'] = 'maintenance';

	$attach_dirs = $modSettings['attachmentUploadDir'];

	// Get the number of attachments....
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member = {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	);
	list ($context['num_attachments']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	$context['num_attachments'] = comma_format($context['num_attachments'], 0);

	// Also get the avatar amount....
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}attachments
		WHERE id_member != {int:guest_id_member}',
		array(
			'guest_id_member' => 0,
		)
	);
	list ($context['num_avatars']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	$context['num_avatars'] = comma_format($context['num_avatars'], 0);

	// Check the size of all the directories.
	$request = $smcFunc['db_query']('', '
		SELECT SUM(size)
		FROM {db_prefix}attachments
		WHERE attachment_type != {int:type}',
		array(
			'type' => 1,
		)
	);
	list ($attachmentDirSize) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Divide it into kilobytes.
	$attachmentDirSize /= 1024;
	$context['attachment_total_size'] = comma_format($attachmentDirSize, 2);

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*), SUM(size)
		FROM {db_prefix}attachments
		WHERE id_folder = {int:folder_id}
			AND attachment_type != {int:type}',
		array(
			'folder_id' => $modSettings['currentAttachmentUploadDir'],
			'type' => 1,
		)
	);
	list ($current_dir_files, $current_dir_size) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	$current_dir_size /= 1024;

	// If they specified a limit only....
	if (!empty($modSettings['attachmentDirSizeLimit']))
		$context['attachment_space'] = comma_format(max($modSettings['attachmentDirSizeLimit'] - $current_dir_size, 0), 2);
	$context['attachment_current_size'] = comma_format($current_dir_size, 2);

	if (!empty($modSettings['attachmentDirFileLimit']))
		$context['attachment_files'] = comma_format(max($modSettings['attachmentDirFileLimit'] - $current_dir_files, 0), 0);
	$context['attachment_current_files'] = comma_format($current_dir_files, 0);

	$context['attach_multiple_dirs'] = count($attach_dirs) > 1 ? true : false;
	$context['attach_dirs'] = $attach_dirs;
	$context['base_dirs'] = !empty($modSettings['attachment_basedirectories']) ? $smcFunc['json_decode']($modSettings['attachment_basedirectories'], true) : array();
	$context['checked'] = isset($_SESSION['checked']) ? $_SESSION['checked'] : true;
	if (!empty($_SESSION['results']))
	{
		$context['results'] = implode('<br>', $_SESSION['results']);
		unset($_SESSION['results']);
	}
}

/**
 * Remove attachments older than a given age.
 * Called from the maintenance screen by
 *   ?action=admin;area=manageattachments;sa=byAge.
 * It optionally adds a certain text to the messages the attachments
 *  were removed from.
 *
 * @todo refactor this silly superglobals use...
 */
function RemoveAttachmentByAge()
{
	global $smcFunc;

	checkSession('post', 'admin');

	// @todo Ignore messages in topics that are stickied?

	// Deleting an attachment?
	if ($_REQUEST['type'] != 'avatars')
	{
		// Get rid of all the old attachments.
		$messages = removeAttachments(array('attachment_type' => 0, 'poster_time' => (time() - 24 * 60 * 60 * $_POST['age'])), 'messages', true);

		// Update the messages to reflect the change.
		if (!empty($messages) && !empty($_POST['notice']))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET body = CONCAT(body, {string:notice})
				WHERE id_msg IN ({array_int:messages})',
				array(
					'messages' => $messages,
					'notice' => '<br><br>' . $_POST['notice'],
				)
			);
	}
	else
	{
		// Remove all the old avatars.
		removeAttachments(array('not_id_member' => 0, 'last_login' => (time() - 24 * 60 * 60 * $_POST['age'])), 'members');
	}
	redirectexit('action=admin;area=manageattachments' . (empty($_REQUEST['avatars']) ? ';sa=maintenance' : ';avatars'));
}

/**
 * Remove attachments larger than a given size.
 * Called from the maintenance screen by
 *  ?action=admin;area=manageattachments;sa=bySize.
 * Optionally adds a certain text to the messages the attachments were
 * 	removed from.
 */
function RemoveAttachmentBySize()
{
	global $smcFunc;

	checkSession('post', 'admin');

	// Find humungous attachments.
	$messages = removeAttachments(array('attachment_type' => 0, 'size' => 1024 * $_POST['size']), 'messages', true);

	// And make a note on the post.
	if (!empty($messages) && !empty($_POST['notice']))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET body = CONCAT(body, {string:notice})
			WHERE id_msg IN ({array_int:messages})',
			array(
				'messages' => $messages,
				'notice' => '<br><br>' . $_POST['notice'],
			)
		);

	redirectexit('action=admin;area=manageattachments;sa=maintenance');
}

/**
 * Remove a selection of attachments or avatars.
 * Called from the browse screen as submitted form by
 *  ?action=admin;area=manageattachments;sa=remove
 */
function RemoveAttachment()
{
	global $txt, $smcFunc, $language, $user_info;

	checkSession();

	if (!empty($_POST['remove']))
	{
		$attachments = array();
		// There must be a quicker way to pass this safety test??
		foreach ($_POST['remove'] as $removeID => $dummy)
			$attachments[] = (int) $removeID;

		// If the attachments are from a 3rd party, let them remove it. Hooks should remove their ids from the array.
		$filesRemoved = false;
		call_integration_hook('integrate_attachment_remove', array(&$filesRemoved, $attachments));

		if ($_REQUEST['type'] == 'avatars' && !empty($attachments))
			removeAttachments(array('id_attach' => $attachments));
		elseif (!empty($attachments))
		{
			$messages = removeAttachments(array('id_attach' => $attachments), 'messages', true);

			// And change the message to reflect this.
			if (!empty($messages))
			{
				loadLanguage('index', $language, true);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET body = CONCAT(body, {string:deleted_message})
					WHERE id_msg IN ({array_int:messages_affected})',
					array(
						'messages_affected' => $messages,
						'deleted_message' => '<br><br>' . $txt['attachment_delete_admin'],
					)
				);
				loadLanguage('index', $user_info['language'], true);
			}
		}
	}

	$_GET['sort'] = isset($_GET['sort']) ? $_GET['sort'] : 'date';
	redirectexit('action=admin;area=manageattachments;sa=browse;' . $_REQUEST['type'] . ';sort=' . $_GET['sort'] . (isset($_GET['desc']) ? ';desc' : '') . ';start=' . $_REQUEST['start']);
}

/**
 * Removes all attachments in a single click
 * Called from the maintenance screen by
 *  ?action=admin;area=manageattachments;sa=removeall.
 */
function RemoveAllAttachments()
{
	global $txt, $smcFunc;

	checkSession('get', 'admin');

	$messages = removeAttachments(array('attachment_type' => 0), '', true);

	if (!isset($_POST['notice']))
		$_POST['notice'] = $txt['attachment_delete_admin'];

	// Add the notice on the end of the changed messages.
	if (!empty($messages))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET body = CONCAT(body, {string:deleted_message})
			WHERE id_msg IN ({array_int:messages})',
			array(
				'messages' => $messages,
				'deleted_message' => '<br><br>' . $_POST['notice'],
			)
		);

	redirectexit('action=admin;area=manageattachments;sa=maintenance');
}

/**
 * Removes attachments or avatars based on a given query condition.
 * Called by several remove avatar/attachment functions in this file.
 * It removes attachments based that match the $condition.
 * It allows query_types 'messages' and 'members', whichever is need by the
 * $condition parameter.
 * It does no permissions check.
 *
 * @internal
 *
 * @param array $condition An array of conditions
 * @param string $query_type The query type. Can be 'messages' or 'members'
 * @param bool $return_affected_messages Whether to return an array with the IDs of affected messages
 * @param bool $autoThumbRemoval Whether to automatically remove any thumbnails associated with the removed files
 * @return void|int[] Returns an array containing IDs of affected messages if $return_affected_messages is true
 */
function removeAttachments($condition, $query_type = '', $return_affected_messages = false, $autoThumbRemoval = true)
{
	global $modSettings, $smcFunc;

	// @todo This might need more work!
	$new_condition = array();
	$query_parameter = array(
		'thumb_attachment_type' => 3,
	);
	$do_logging = array();

	if (is_array($condition))
	{
		foreach ($condition as $real_type => $restriction)
		{
			// Doing a NOT?
			$is_not = substr($real_type, 0, 4) == 'not_';
			$type = $is_not ? substr($real_type, 4) : $real_type;

			if (in_array($type, array('id_member', 'id_attach', 'id_msg')))
				$new_condition[] = 'a.' . $type . ($is_not ? ' NOT' : '') . ' IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';
			elseif ($type == 'attachment_type')
				$new_condition[] = 'a.attachment_type = {int:' . $real_type . '}';
			elseif ($type == 'poster_time')
				$new_condition[] = 'm.poster_time < {int:' . $real_type . '}';
			elseif ($type == 'last_login')
				$new_condition[] = 'mem.last_login < {int:' . $real_type . '}';
			elseif ($type == 'size')
				$new_condition[] = 'a.size > {int:' . $real_type . '}';
			elseif ($type == 'id_topic')
				$new_condition[] = 'm.id_topic IN (' . (is_array($restriction) ? '{array_int:' . $real_type . '}' : '{int:' . $real_type . '}') . ')';

			// Add the parameter!
			$query_parameter[$real_type] = $restriction;

			if ($type == 'do_logging')
				$do_logging = $condition['id_attach'];
		}
		$condition = implode(' AND ', $new_condition);
	}

	// Delete it only if it exists...
	$msgs = array();
	$attach = array();
	$parents = array();

	// Get all the attachment names and id_msg's.
	$request = $smcFunc['db_query']('', '
		SELECT
			a.id_folder, a.filename, a.file_hash, a.attachment_type, a.id_attach, a.id_member' . ($query_type == 'messages' ? ', m.id_msg' : ', a.id_msg') . ',
			thumb.id_folder AS thumb_folder, COALESCE(thumb.id_attach, 0) AS id_thumb, thumb.filename AS thumb_filename, thumb.file_hash AS thumb_file_hash, thumb_parent.id_attach AS id_parent
		FROM {db_prefix}attachments AS a' . ($query_type == 'members' ? '
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)' : ($query_type == 'messages' ? '
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)' : '')) . '
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
			LEFT JOIN {db_prefix}attachments AS thumb_parent ON (thumb.attachment_type = {int:thumb_attachment_type} AND thumb_parent.id_thumb = a.id_attach)
		WHERE ' . $condition,
		$query_parameter
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Figure out the "encrypted" filename and unlink it ;).
		if ($row['attachment_type'] == 1)
		{
			// if attachment_type = 1, it's... an avatar in a custom avatar directory.
			// wasn't it obvious? :P
			// @todo look again at this.
			@unlink($modSettings['custom_avatar_dir'] . '/' . $row['filename']);
		}
		else
		{
			$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
			@unlink($filename);

			// If this was a thumb, the parent attachment should know about it.
			if (!empty($row['id_parent']))
				$parents[] = $row['id_parent'];

			// If this attachments has a thumb, remove it as well.
			if (!empty($row['id_thumb']) && $autoThumbRemoval)
			{
				$thumb_filename = getAttachmentFilename($row['thumb_filename'], $row['id_thumb'], $row['thumb_folder'], false, $row['thumb_file_hash']);
				@unlink($thumb_filename);
				$attach[] = $row['id_thumb'];
			}
		}

		// Make a list.
		if ($return_affected_messages && empty($row['attachment_type']))
			$msgs[] = $row['id_msg'];

		$attach[] = $row['id_attach'];
	}
	$smcFunc['db_free_result']($request);

	// Removed attachments don't have to be updated anymore.
	$parents = array_diff($parents, $attach);
	if (!empty($parents))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}attachments
			SET id_thumb = {int:no_thumb}
			WHERE id_attach IN ({array_int:parent_attachments})',
			array(
				'parent_attachments' => $parents,
				'no_thumb' => 0,
			)
		);

	if (!empty($do_logging))
	{
		// In order to log the attachments, we really need their message and filename
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, a.filename
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
			WHERE a.id_attach IN ({array_int:attachments})
				AND a.attachment_type = {int:attachment_type}',
			array(
				'attachments' => $do_logging,
				'attachment_type' => 0,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($request))
			logAction(
				'remove_attach',
				array(
					'message' => $row['id_msg'],
					'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($row['filename'])),
				)
			);
		$smcFunc['db_free_result']($request);
	}

	if (!empty($attach))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $attach,
			)
		);

	call_integration_hook('integrate_remove_attachments', array($attach));

	if ($return_affected_messages)
		return array_unique($msgs);
}

/**
 * This function should find attachments in the database that no longer exist and clear them, and fix filesize issues.
 */
function RepairAttachments()
{
	global $modSettings, $context, $txt, $smcFunc;

	checkSession('get');

	// If we choose cancel, redirect right back.
	if (isset($_POST['cancel']))
		redirectexit('action=admin;area=manageattachments;sa=maintenance');

	// Try give us a while to sort this out...
	@set_time_limit(600);

	$_GET['step'] = empty($_GET['step']) ? 0 : (int) $_GET['step'];
	$context['starting_substep'] = $_GET['substep'] = empty($_GET['substep']) ? 0 : (int) $_GET['substep'];

	// Don't recall the session just in case.
	if ($_GET['step'] == 0 && $_GET['substep'] == 0)
	{
		unset($_SESSION['attachments_to_fix'], $_SESSION['attachments_to_fix2']);

		// If we're actually fixing stuff - work out what.
		if (isset($_GET['fixErrors']))
		{
			// Nothing?
			if (empty($_POST['to_fix']))
				redirectexit('action=admin;area=manageattachments;sa=maintenance');

			$_SESSION['attachments_to_fix'] = array();
			// @todo No need to do this I think.
			foreach ($_POST['to_fix'] as $value)
				$_SESSION['attachments_to_fix'][] = $value;
		}
	}

	// All the valid problems are here:
	$context['repair_errors'] = array(
		'missing_thumbnail_parent' => 0,
		'parent_missing_thumbnail' => 0,
		'file_missing_on_disk' => 0,
		'file_wrong_size' => 0,
		'file_size_of_zero' => 0,
		'attachment_no_msg' => 0,
		'avatar_no_member' => 0,
		'wrong_folder' => 0,
		'files_without_attachment' => 0,
	);

	$to_fix = !empty($_SESSION['attachments_to_fix']) ? $_SESSION['attachments_to_fix'] : array();
	$context['repair_errors'] = isset($_SESSION['attachments_to_fix2']) ? $_SESSION['attachments_to_fix2'] : $context['repair_errors'];
	$fix_errors = isset($_GET['fixErrors']) ? true : false;

	// Get stranded thumbnails.
	if ($_GET['step'] <= 0)
	{
		$result = $smcFunc['db_query']('', '
			SELECT MAX(id_attach)
			FROM {db_prefix}attachments
			WHERE attachment_type = {int:thumbnail}',
			array(
				'thumbnail' => 3,
			)
		);
		list ($thumbnails) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
		{
			$to_remove = array();

			$result = $smcFunc['db_query']('', '
				SELECT thumb.id_attach, thumb.id_folder, thumb.filename, thumb.file_hash
				FROM {db_prefix}attachments AS thumb
					LEFT JOIN {db_prefix}attachments AS tparent ON (tparent.id_thumb = thumb.id_attach)
				WHERE thumb.id_attach BETWEEN {int:substep} AND {int:substep} + 499
					AND thumb.attachment_type = {int:thumbnail}
					AND tparent.id_attach IS NULL',
				array(
					'thumbnail' => 3,
					'substep' => $_GET['substep'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				// Only do anything once... just in case
				if (!isset($to_remove[$row['id_attach']]))
				{
					$to_remove[$row['id_attach']] = $row['id_attach'];
					$context['repair_errors']['missing_thumbnail_parent']++;

					// If we are repairing remove the file from disk now.
					if ($fix_errors && in_array('missing_thumbnail_parent', $to_fix))
					{
						$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
						@unlink($filename);
					}
				}
			}
			if ($smcFunc['db_num_rows']($result) != 0)
				$to_fix[] = 'missing_thumbnail_parent';
			$smcFunc['db_free_result']($result);

			// Do we need to delete what we have?
			if ($fix_errors && !empty($to_remove) && in_array('missing_thumbnail_parent', $to_fix))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}attachments
					WHERE id_attach IN ({array_int:to_remove})
						AND attachment_type = {int:attachment_type}',
					array(
						'to_remove' => $to_remove,
						'attachment_type' => 3,
					)
				);

			pauseAttachmentMaintenance($to_fix, $thumbnails);
		}

		$_GET['step'] = 1;
		$_GET['substep'] = 0;
		pauseAttachmentMaintenance($to_fix);
	}

	// Find parents which think they have thumbnails, but actually, don't.
	if ($_GET['step'] <= 1)
	{
		$result = $smcFunc['db_query']('', '
			SELECT MAX(id_attach)
			FROM {db_prefix}attachments
			WHERE id_thumb != {int:no_thumb}',
			array(
				'no_thumb' => 0,
			)
		);
		list ($thumbnails) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
		{
			$to_update = array();

			$result = $smcFunc['db_query']('', '
				SELECT a.id_attach
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
				WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
					AND a.id_thumb != {int:no_thumb}
					AND thumb.id_attach IS NULL',
				array(
					'no_thumb' => 0,
					'substep' => $_GET['substep'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$to_update[] = $row['id_attach'];
				$context['repair_errors']['parent_missing_thumbnail']++;
			}
			if ($smcFunc['db_num_rows']($result) != 0)
				$to_fix[] = 'parent_missing_thumbnail';
			$smcFunc['db_free_result']($result);

			// Do we need to delete what we have?
			if ($fix_errors && !empty($to_update) && in_array('parent_missing_thumbnail', $to_fix))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}attachments
					SET id_thumb = {int:no_thumb}
					WHERE id_attach IN ({array_int:to_update})',
					array(
						'to_update' => $to_update,
						'no_thumb' => 0,
					)
				);

			pauseAttachmentMaintenance($to_fix, $thumbnails);
		}

		$_GET['step'] = 2;
		$_GET['substep'] = 0;
		pauseAttachmentMaintenance($to_fix);
	}

	// This may take forever I'm afraid, but life sucks... recount EVERY attachments!
	if ($_GET['step'] <= 2)
	{
		$result = $smcFunc['db_query']('', '
			SELECT MAX(id_attach)
			FROM {db_prefix}attachments',
			array(
			)
		);
		list ($thumbnails) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 250)
		{
			$to_remove = array();
			$errors_found = array();

			$result = $smcFunc['db_query']('', '
				SELECT id_attach, id_folder, filename, file_hash, size, attachment_type
				FROM {db_prefix}attachments
				WHERE id_attach BETWEEN {int:substep} AND {int:substep} + 249',
				array(
					'substep' => $_GET['substep'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				// Get the filename.
				if ($row['attachment_type'] == 1)
					$filename = $modSettings['custom_avatar_dir'] . '/' . $row['filename'];
				else
					$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

				// File doesn't exist?
				if (!file_exists($filename))
				{
					// If we're lucky it might just be in a different folder.
					if (!empty($modSettings['currentAttachmentUploadDir']))
					{
						// Get the attachment name with out the folder.
						$attachment_name = $row['id_attach'] . '_' . $row['file_hash'] . '.dat';

						// Loop through the other folders.
						foreach ($modSettings['attachmentUploadDir'] as $id => $dir)
							if (file_exists($dir . '/' . $attachment_name))
							{
								$context['repair_errors']['wrong_folder']++;
								$errors_found[] = 'wrong_folder';

								// Are we going to fix this now?
								if ($fix_errors && in_array('wrong_folder', $to_fix))
									$smcFunc['db_query']('', '
										UPDATE {db_prefix}attachments
										SET id_folder = {int:new_folder}
										WHERE id_attach = {int:id_attach}',
										array(
											'new_folder' => $id,
											'id_attach' => $row['id_attach'],
										)
									);

								continue 2;
							}
					}

					$to_remove[] = $row['id_attach'];
					$context['repair_errors']['file_missing_on_disk']++;
					$errors_found[] = 'file_missing_on_disk';
				}
				elseif (filesize($filename) == 0)
				{
					$context['repair_errors']['file_size_of_zero']++;
					$errors_found[] = 'file_size_of_zero';

					// Fixing?
					if ($fix_errors && in_array('file_size_of_zero', $to_fix))
					{
						$to_remove[] = $row['id_attach'];
						@unlink($filename);
					}
				}
				elseif (filesize($filename) != $row['size'])
				{
					$context['repair_errors']['file_wrong_size']++;
					$errors_found[] = 'file_wrong_size';

					// Fix it here?
					if ($fix_errors && in_array('file_wrong_size', $to_fix))
					{
						$smcFunc['db_query']('', '
							UPDATE {db_prefix}attachments
							SET size = {int:filesize}
							WHERE id_attach = {int:id_attach}',
							array(
								'filesize' => filesize($filename),
								'id_attach' => $row['id_attach'],
							)
						);
					}
				}
			}

			if (in_array('file_missing_on_disk', $errors_found))
				$to_fix[] = 'file_missing_on_disk';
			if (in_array('file_size_of_zero', $errors_found))
				$to_fix[] = 'file_size_of_zero';
			if (in_array('file_wrong_size', $errors_found))
				$to_fix[] = 'file_wrong_size';
			if (in_array('wrong_folder', $errors_found))
				$to_fix[] = 'wrong_folder';
			$smcFunc['db_free_result']($result);

			// Do we need to delete what we have?
			if ($fix_errors && !empty($to_remove))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}attachments
					WHERE id_attach IN ({array_int:to_remove})',
					array(
						'to_remove' => $to_remove,
					)
				);
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}attachments
					SET id_thumb = {int:no_thumb}
					WHERE id_thumb IN ({array_int:to_remove})',
					array(
						'to_remove' => $to_remove,
						'no_thumb' => 0,
					)
				);
			}

			pauseAttachmentMaintenance($to_fix, $thumbnails);
		}

		$_GET['step'] = 3;
		$_GET['substep'] = 0;
		pauseAttachmentMaintenance($to_fix);
	}

	// Get avatars with no members associated with them.
	if ($_GET['step'] <= 3)
	{
		$result = $smcFunc['db_query']('', '
			SELECT MAX(id_attach)
			FROM {db_prefix}attachments',
			array(
			)
		);
		list ($thumbnails) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
		{
			$to_remove = array();

			$result = $smcFunc['db_query']('', '
				SELECT a.id_attach, a.id_folder, a.filename, a.file_hash, a.attachment_type
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)
				WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
					AND a.id_member != {int:no_member}
					AND a.id_msg = {int:no_msg}
					AND mem.id_member IS NULL',
				array(
					'no_member' => 0,
					'no_msg' => 0,
					'substep' => $_GET['substep'],
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$to_remove[] = $row['id_attach'];
				$context['repair_errors']['avatar_no_member']++;

				// If we are repairing remove the file from disk now.
				if ($fix_errors && in_array('avatar_no_member', $to_fix))
				{
					if ($row['attachment_type'] == 1)
						$filename = $modSettings['custom_avatar_dir'] . '/' . $row['filename'];
					else
						$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
					@unlink($filename);
				}
			}
			if ($smcFunc['db_num_rows']($result) != 0)
				$to_fix[] = 'avatar_no_member';
			$smcFunc['db_free_result']($result);

			// Do we need to delete what we have?
			if ($fix_errors && !empty($to_remove) && in_array('avatar_no_member', $to_fix))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}attachments
					WHERE id_attach IN ({array_int:to_remove})
						AND id_member != {int:no_member}
						AND id_msg = {int:no_msg}',
					array(
						'to_remove' => $to_remove,
						'no_member' => 0,
						'no_msg' => 0,
					)
				);

			pauseAttachmentMaintenance($to_fix, $thumbnails);
		}

		$_GET['step'] = 4;
		$_GET['substep'] = 0;
		pauseAttachmentMaintenance($to_fix);
	}

	// What about attachments, who are missing a message :'(
	if ($_GET['step'] <= 4)
	{
		$result = $smcFunc['db_query']('', '
			SELECT MAX(id_attach)
			FROM {db_prefix}attachments',
			array(
			)
		);
		list ($thumbnails) = $smcFunc['db_fetch_row']($result);
		$smcFunc['db_free_result']($result);

		for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500)
		{
			$to_remove = array();
			$ignore_ids = array(0);

			// returns an array of ints of id_attach's that should not be deleted
			call_integration_hook('integrate_repair_attachments_nomsg', array(&$ignore_ids, $_GET['substep'], $_GET['substep'] + 500));

			$result = $smcFunc['db_query']('', '
				SELECT a.id_attach, a.id_folder, a.filename, a.file_hash
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
					AND a.id_member = {int:no_member}
					AND (a.id_msg = {int:no_msg} OR m.id_msg IS NULL)
					AND a.id_attach NOT IN ({array_int:ignore_ids})
					AND a.attachment_type IN ({array_int:attach_thumb})',
				array(
					'no_member' => 0,
					'no_msg' => 0,
					'substep' => $_GET['substep'],
					'ignore_ids' => $ignore_ids,
					'attach_thumb' => array(0, 3),
				)
			);

			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$to_remove[] = $row['id_attach'];
				$context['repair_errors']['attachment_no_msg']++;

				// If we are repairing remove the file from disk now.
				if ($fix_errors && in_array('attachment_no_msg', $to_fix))
				{
					$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
					@unlink($filename);
				}
			}
			if ($smcFunc['db_num_rows']($result) != 0)
				$to_fix[] = 'attachment_no_msg';
			$smcFunc['db_free_result']($result);

			// Do we need to delete what we have?
			if ($fix_errors && !empty($to_remove) && in_array('attachment_no_msg', $to_fix))
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}attachments
					WHERE id_attach IN ({array_int:to_remove})
						AND id_member = {int:no_member}
						AND attachment_type IN ({array_int:attach_thumb})',
					array(
						'to_remove' => $to_remove,
						'no_member' => 0,
						'attach_thumb' => array(0, 3),
					)
				);

			pauseAttachmentMaintenance($to_fix, $thumbnails);
		}

		$_GET['step'] = 5;
		$_GET['substep'] = 0;
		pauseAttachmentMaintenance($to_fix);
	}

	// What about files who are not recorded in the database?
	if ($_GET['step'] <= 5)
	{
		$attach_dirs = $modSettings['attachmentUploadDir'];

		$current_check = 0;
		$max_checks = 500;
		$files_checked = empty($_GET['substep']) ? 0 : $_GET['substep'];
		foreach ($attach_dirs as $attach_dir)
		{
			if ($dir = @opendir($attach_dir))
			{
				while ($file = readdir($dir))
				{
					if (in_array($file, array('.', '..', '.htaccess', 'index.php')))
						continue;

					if ($files_checked <= $current_check)
					{
						// Temporary file, get rid of it!
						if (strpos($file, 'post_tmp_') !== false)
						{
							// Temp file is more than 5 hours old!
							if (filemtime($attach_dir . '/' . $file) < time() - 18000)
								@unlink($attach_dir . '/' . $file);
						}
						// That should be an attachment, let's check if we have it in the database
						elseif (strpos($file, '_') !== false)
						{
							$attachID = (int) substr($file, 0, strpos($file, '_'));
							if (!empty($attachID))
							{
								$request = $smcFunc['db_query']('', '
									SELECT  id_attach
									FROM {db_prefix}attachments
									WHERE id_attach = {int:attachment_id}
									LIMIT 1',
									array(
										'attachment_id' => $attachID,
									)
								);
								if ($smcFunc['db_num_rows']($request) == 0)
								{
									if ($fix_errors && in_array('files_without_attachment', $to_fix))
									{
										@unlink($attach_dir . '/' . $file);
									}
									else
									{
										$context['repair_errors']['files_without_attachment']++;
										$to_fix[] = 'files_without_attachment';
									}
								}
								$smcFunc['db_free_result']($request);
							}
						}
						else
						{
							if ($fix_errors && in_array('files_without_attachment', $to_fix))
							{
								@unlink($attach_dir . '/' . $file);
							}
							else
							{
								$context['repair_errors']['files_without_attachment']++;
								$to_fix[] = 'files_without_attachment';
							}
						}
					}
					$current_check++;
					$_GET['substep'] = $current_check;
					if ($current_check - $files_checked >= $max_checks)
						pauseAttachmentMaintenance($to_fix);
				}
				closedir($dir);
			}
		}

		$_GET['step'] = 5;
		$_GET['substep'] = 0;
		pauseAttachmentMaintenance($to_fix);
	}

	// Got here we must be doing well - just the template! :D
	$context['page_title'] = $txt['repair_attachments'];
	$context[$context['admin_menu_name']]['current_subsection'] = 'maintenance';
	$context['sub_template'] = 'attachment_repair';

	// What stage are we at?
	$context['completed'] = $fix_errors ? true : false;
	$context['errors_found'] = !empty($to_fix) ? true : false;

}

/**
 * Function called in-between each round of attachments and avatar repairs.
 * Called by repairAttachments().
 * If repairAttachments() has more steps added, this function needs updated!
 *
 * @param array $to_fix IDs of attachments to fix
 * @param int $max_substep The maximum substep to reach before pausing
 */
function pauseAttachmentMaintenance($to_fix, $max_substep = 0)
{
	global $context, $txt, $time_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if ((time() - $time_start) < 3 || $context['starting_substep'] == $_GET['substep'])
		return;

	$context['continue_get_data'] = '?action=admin;area=manageattachments;sa=repair' . (isset($_GET['fixErrors']) ? ';fixErrors' : '') . ';step=' . $_GET['step'] . ';substep=' . $_GET['substep'] . ';' . $context['session_var'] . '=' . $context['session_id'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	$context['sub_template'] = 'not_done';

	// Specific stuff to not break this template!
	$context[$context['admin_menu_name']]['current_subsection'] = 'maintenance';

	// Change these two if more steps are added!
	if (empty($max_substep))
		$context['continue_percent'] = round(($_GET['step'] * 100) / 25);
	else
		$context['continue_percent'] = round(($_GET['step'] * 100 + ($_GET['substep'] * 100) / $max_substep) / 25);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	$_SESSION['attachments_to_fix'] = $to_fix;
	$_SESSION['attachments_to_fix2'] = $context['repair_errors'];

	obExit();
}

/**
 * Called from a mouse click, works out what we want to do with attachments and actions it.
 */
function ApproveAttach()
{
	global $smcFunc;

	// Security is our primary concern...
	checkSession('get');

	// If it approve or delete?
	$is_approve = !isset($_GET['sa']) || $_GET['sa'] != 'reject' ? true : false;

	$attachments = array();
	// If we are approving all ID's in a message , get the ID's.
	if ($_GET['sa'] == 'all' && !empty($_GET['mid']))
	{
		$id_msg = (int) $_GET['mid'];

		$request = $smcFunc['db_query']('', '
			SELECT id_attach
			FROM {db_prefix}attachments
			WHERE id_msg = {int:id_msg}
				AND approved = {int:is_approved}
				AND attachment_type = {int:attachment_type}',
			array(
				'id_msg' => $id_msg,
				'is_approved' => 0,
				'attachment_type' => 0,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$attachments[] = $row['id_attach'];
		$smcFunc['db_free_result']($request);
	}
	elseif (!empty($_GET['aid']))
		$attachments[] = (int) $_GET['aid'];

	if (empty($attachments))
		fatal_lang_error('no_access', false);

	// Now we have some ID's cleaned and ready to approve, but first - let's check we have permission!
	$allowed_boards = boardsAllowedTo('approve_posts');

	// Validate the attachments exist and are the right approval state.
	$request = $smcFunc['db_query']('', '
		SELECT a.id_attach, m.id_board, m.id_msg, m.id_topic
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}
			AND a.approved = {int:is_approved}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
			'is_approved' => 0,
		)
	);
	$attachments = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// We can only add it if we can approve in this board!
		if ($allowed_boards = array(0) || in_array($row['id_board'], $allowed_boards))
		{
			$attachments[] = $row['id_attach'];

			// Also come up with the redirection URL.
			$redirect = 'topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'];
		}
	}
	$smcFunc['db_free_result']($request);

	if (empty($attachments))
		fatal_lang_error('no_access', false);

	// Finally, we are there. Follow through!
	if ($is_approve)
	{
		// Checked and deemed worthy.
		ApproveAttachments($attachments);
	}
	else
		removeAttachments(array('id_attach' => $attachments, 'do_logging' => true));

	// Return to the topic....
	redirectexit($redirect);
}

/**
 * Approve an attachment, or maybe even more - no permission check!
 *
 * @param array $attachments The IDs of the attachments to approve
 * @return void|int Returns 0 if the operation failed, otherwise returns nothing
 */
function ApproveAttachments($attachments)
{
	global $smcFunc;

	if (empty($attachments))
		return 0;

	// For safety, check for thumbnails...
	$request = $smcFunc['db_query']('', '
		SELECT
			a.id_attach, a.id_member, COALESCE(thumb.id_attach, 0) AS id_thumb
		FROM {db_prefix}attachments AS a
			LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
		)
	);
	$attachments = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Update the thumbnail too...
		if (!empty($row['id_thumb']))
			$attachments[] = $row['id_thumb'];

		$attachments[] = $row['id_attach'];
	}
	$smcFunc['db_free_result']($request);

	if (empty($attachments))
		return 0;

	// Approving an attachment is not hard - it's easy.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}attachments
		SET approved = {int:is_approved}
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
			'is_approved' => 1,
		)
	);

	// In order to log the attachments, we really need their message and filename
	$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, a.filename
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (a.id_msg = m.id_msg)
		WHERE a.id_attach IN ({array_int:attachments})
			AND a.attachment_type = {int:attachment_type}',
		array(
			'attachments' => $attachments,
			'attachment_type' => 0,
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		logAction(
			'approve_attach',
			array(
				'message' => $row['id_msg'],
				'filename' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', $smcFunc['htmlspecialchars']($row['filename'])),
			)
		);
	$smcFunc['db_free_result']($request);

	// Remove from the approval queue.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}approval_queue
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
		)
	);

	call_integration_hook('integrate_approve_attachments', array($attachments));
}

/**
 * This function lists and allows updating of multiple attachments paths.
 */
function ManageAttachmentPaths()
{
	global $modSettings, $scripturl, $context, $txt, $sourcedir, $boarddir, $smcFunc, $settings;

	// Since this needs to be done eventually.
	if (!isset($modSettings['attachment_basedirectories']))
		$modSettings['attachment_basedirectories'] = array();

	elseif (!is_array($modSettings['attachment_basedirectories']))
		$modSettings['attachment_basedirectories'] = $smcFunc['json_decode']($modSettings['attachment_basedirectories'], true);

	$errors = array();

	// Saving?
	if (isset($_REQUEST['save']))
	{
		checkSession();

		$_POST['current_dir'] = (int) $_POST['current_dir'];
		$new_dirs = array();
		foreach ($_POST['dirs'] as $id => $path)
		{
			$error = '';
			$id = (int) $id;
			if ($id < 1)
				continue;

			// Sorry, these dirs are NOT valid
			$invalid_dirs = array($boarddir, $settings['default_theme_dir'], $sourcedir);
			if (in_array($path, $invalid_dirs))
			{
				$errors[] = $path . ': ' . $txt['attach_dir_invalid'];
				continue;
			}

			// Hmm, a new path maybe?
			// Don't allow empty paths
			if (!array_key_exists($id, $modSettings['attachmentUploadDir']) && !empty($path))
			{
				// or is it?
				if (in_array($path, $modSettings['attachmentUploadDir']) || in_array($boarddir . DIRECTORY_SEPARATOR . $path, $modSettings['attachmentUploadDir']))
				{
					$errors[] = $path . ': ' . $txt['attach_dir_duplicate_msg'];
					continue;
				}
				elseif (empty($path))
				{
					// Ignore this and set $id to one less
					continue;
				}

				// OK, so let's try to create it then.
				require_once($sourcedir . '/Subs-Attachments.php');
				if (automanage_attachments_create_directory($path))
					$_POST['current_dir'] = $modSettings['currentAttachmentUploadDir'];
				else
					$errors[] = $path . ': ' . $txt[$context['dir_creation_error']];
			}

			// Changing a directory name?
			if (!empty($modSettings['attachmentUploadDir'][$id]) && !empty($path) && $path != $modSettings['attachmentUploadDir'][$id])
			{
				if ($path != $modSettings['attachmentUploadDir'][$id] && !is_dir($path))
				{
					if (!@rename($modSettings['attachmentUploadDir'][$id], $path))
					{
						$errors[] = $path . ': ' . $txt['attach_dir_no_rename'];
						$path = $modSettings['attachmentUploadDir'][$id];
					}
				}
				else
				{
					$errors[] = $path . ': ' . $txt['attach_dir_exists_msg'];
					$path = $modSettings['attachmentUploadDir'][$id];
				}

				// Update the base directory path
				if (!empty($modSettings['attachment_basedirectories']) && array_key_exists($id, $modSettings['attachment_basedirectories']))
				{
					$base = $modSettings['basedirectory_for_attachments'] == $modSettings['attachmentUploadDir'][$id] ? $path : $modSettings['basedirectory_for_attachments'];

					$modSettings['attachment_basedirectories'][$id] = $path;
					updateSettings(array(
						'attachment_basedirectories' => $smcFunc['json_encode']($modSettings['attachment_basedirectories']),
						'basedirectory_for_attachments' => $base,
					));
					$modSettings['attachment_basedirectories'] = $smcFunc['json_decode']($modSettings['attachment_basedirectories'], true);
				}
			}

			if (empty($path))
			{
				$path = $modSettings['attachmentUploadDir'][$id];

				// It's not a good idea to delete the current directory.
				if ($id == (!empty($_POST['current_dir']) ? $_POST['current_dir'] : $modSettings['currentAttachmentUploadDir']))
					$errors[] = $path . ': ' . $txt['attach_dir_is_current'];
				// Or the current base directory
				elseif (!empty($modSettings['basedirectory_for_attachments']) && $modSettings['basedirectory_for_attachments'] == $modSettings['attachmentUploadDir'][$id])
					$errors[] = $path . ': ' . $txt['attach_dir_is_current_bd'];
				else
				{
					// Let's not try to delete a path with files in it.
					$request = $smcFunc['db_query']('', '
						SELECT COUNT(id_attach) AS num_attach
						FROM {db_prefix}attachments
						WHERE id_folder = {int:id_folder}',
						array(
							'id_folder' => (int) $id,
						)
					);

					list ($num_attach) = $smcFunc['db_fetch_row']($request);
					$smcFunc['db_free_result']($request);

					// A check to see if it's a used base dir.
					if (!empty($modSettings['attachment_basedirectories']))
					{
						// Count any sub-folders.
						foreach ($modSettings['attachmentUploadDir'] as $sub)
							if (strpos($sub, $path . DIRECTORY_SEPARATOR) !== false)
								$num_attach++;
					}

					// It's safe to delete. So try to delete the folder also
					if ($num_attach == 0)
					{
						if (is_dir($path))
							$doit = true;
						elseif (is_dir($boarddir . DIRECTORY_SEPARATOR . $path))
						{
							$doit = true;
							$path = $boarddir . DIRECTORY_SEPARATOR . $path;
						}

						if (isset($doit) && realpath($path) != realpath($boarddir))
						{
							unlink($path . '/.htaccess');
							unlink($path . '/index.php');
							if (!@rmdir($path))
								$error = $path . ': ' . $txt['attach_dir_no_delete'];
						}

						// Remove it from the base directory list.
						if (empty($error) && !empty($modSettings['attachment_basedirectories']))
						{
							unset($modSettings['attachment_basedirectories'][$id]);
							updateSettings(array('attachment_basedirectories' => $smcFunc['json_encode']($modSettings['attachment_basedirectories'])));
							$modSettings['attachment_basedirectories'] = $smcFunc['json_decode']($modSettings['attachment_basedirectories'], true);
						}
					}
					else
						$error = $path . ': ' . $txt['attach_dir_no_remove'];

					if (empty($error))
						continue;
					else
						$errors[] = $error;
				}
			}

			$new_dirs[$id] = $path;
		}

		// We need to make sure the current directory is right.
		if (empty($_POST['current_dir']) && !empty($modSettings['currentAttachmentUploadDir']))
			$_POST['current_dir'] = $modSettings['currentAttachmentUploadDir'];

		// Find the current directory if there's no value carried,
		if (empty($_POST['current_dir']) || empty($new_dirs[$_POST['current_dir']]))
		{
			if (array_key_exists($modSettings['currentAttachmentUploadDir'], $modSettings['attachmentUploadDir']))
				$_POST['current_dir'] = $modSettings['currentAttachmentUploadDir'];
			else
				$_POST['current_dir'] = max(array_keys($modSettings['attachmentUploadDir']));
		}

		// If the user wishes to go back, update the last_dir array
		if ($_POST['current_dir'] != $modSettings['currentAttachmentUploadDir'] && !empty($modSettings['last_attachments_directory']) && (isset($modSettings['last_attachments_directory'][$_POST['current_dir']]) || isset($modSettings['last_attachments_directory'][0])))
		{
			if (!is_array($modSettings['last_attachments_directory']))
				$modSettings['last_attachments_directory'] = $smcFunc['json_decode']($modSettings['last_attachments_directory'], true);
			$num = substr(strrchr($modSettings['attachmentUploadDir'][$_POST['current_dir']], '_'), 1);

			if (is_numeric($num))
			{
				// Need to find the base folder.
				$bid = -1;
				$use_subdirectories_for_attachments = 0;
				if (!empty($modSettings['attachment_basedirectories']))
					foreach ($modSettings['attachment_basedirectories'] as $bid => $base)
						if (strpos($modSettings['attachmentUploadDir'][$_POST['current_dir']], $base . DIRECTORY_SEPARATOR) !== false)
						{
							$use_subdirectories_for_attachments = 1;
							break;
						}

				if ($use_subdirectories_for_attachments == 0 && strpos($modSettings['attachmentUploadDir'][$_POST['current_dir']], $boarddir . DIRECTORY_SEPARATOR) !== false)
					$bid = 0;

				$modSettings['last_attachments_directory'][$bid] = (int) $num;
				$modSettings['basedirectory_for_attachments'] = !empty($modSettings['basedirectory_for_attachments']) ? $modSettings['basedirectory_for_attachments'] : '';
				$modSettings['use_subdirectories_for_attachments'] = !empty($modSettings['use_subdirectories_for_attachments']) ? $modSettings['use_subdirectories_for_attachments'] : 0;
				updateSettings(array(
					'last_attachments_directory' => $smcFunc['json_encode']($modSettings['last_attachments_directory']),
					'basedirectory_for_attachments' => $bid == 0 ? $modSettings['basedirectory_for_attachments'] : $modSettings['attachment_basedirectories'][$bid],
					'use_subdirectories_for_attachments' => $use_subdirectories_for_attachments,
				));
			}
		}

		// Going back to just one path?
		if (count($new_dirs) == 1)
		{
			// We might need to reset the paths. This loop will just loop through once.
			foreach ($new_dirs as $id => $dir)
			{
				if ($id != 1)
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}attachments
						SET id_folder = {int:default_folder}
						WHERE id_folder = {int:current_folder}',
						array(
							'default_folder' => 1,
							'current_folder' => $id,
						)
					);

				$update = array(
					'currentAttachmentUploadDir' => 1,
					'attachmentUploadDir' => $smcFunc['json_encode'](array(1 => $dir)),
				);
			}
		}
		else
		{
			// Save it to the database.
			$update = array(
				'currentAttachmentUploadDir' => $_POST['current_dir'],
				'attachmentUploadDir' => $smcFunc['json_encode']($new_dirs),
			);
		}

		if (!empty($update))
			updateSettings($update);

		if (!empty($errors))
			$_SESSION['errors']['dir'] = $errors;

		redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Saving a base directory?
	if (isset($_REQUEST['save2']))
	{
		checkSession();

		// Changing the current base directory?
		$_POST['current_base_dir'] = isset($_POST['current_base_dir']) ? (int) $_POST['current_base_dir'] : 1;
		if (empty($_POST['new_base_dir']) && !empty($_POST['current_base_dir']))
		{
			if ($modSettings['basedirectory_for_attachments'] != $modSettings['attachmentUploadDir'][$_POST['current_base_dir']])
				$update = (array(
					'basedirectory_for_attachments' => $modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
				));
		}

		if (isset($_POST['base_dir']))
		{
			foreach ($_POST['base_dir'] as $id => $dir)
			{
				if (!empty($dir) && $dir != $modSettings['attachmentUploadDir'][$id])
				{
					if (@rename($modSettings['attachmentUploadDir'][$id], $dir))
					{
						$modSettings['attachmentUploadDir'][$id] = $dir;
						$modSettings['attachment_basedirectories'][$id] = $dir;
						$update = (array(
							'attachmentUploadDir' => $smcFunc['json_encode']($modSettings['attachmentUploadDir']),
							'attachment_basedirectories' => $smcFunc['json_encode']($modSettings['attachment_basedirectories']),
							'basedirectory_for_attachments' => $modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
						));
					}
				}

				if (empty($dir))
				{
					if ($id == $_POST['current_base_dir'])
					{
						$errors[] = $modSettings['attachmentUploadDir'][$id] . ': ' . $txt['attach_dir_is_current'];
						continue;
					}

					unset($modSettings['attachment_basedirectories'][$id]);
					$update = (array(
						'attachment_basedirectories' => $smcFunc['json_encode']($modSettings['attachment_basedirectories']),
						'basedirectory_for_attachments' => $modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
					));
				}
			}
		}

		// Or adding a new one?
		if (!empty($_POST['new_base_dir']))
		{
			require_once($sourcedir . '/Subs-Attachments.php');
			$_POST['new_base_dir'] = $smcFunc['htmlspecialchars']($_POST['new_base_dir'], ENT_QUOTES);

			$current_dir = $modSettings['currentAttachmentUploadDir'];

			if (!in_array($_POST['new_base_dir'], $modSettings['attachmentUploadDir']))
			{
				if (!automanage_attachments_create_directory($_POST['new_base_dir']))
					$errors[] = $_POST['new_base_dir'] . ': ' . $txt['attach_dir_base_no_create'];
			}

			$modSettings['currentAttachmentUploadDir'] = array_search($_POST['new_base_dir'], $modSettings['attachmentUploadDir']);
			if (!in_array($_POST['new_base_dir'], $modSettings['attachment_basedirectories']))
				$modSettings['attachment_basedirectories'][$modSettings['currentAttachmentUploadDir']] = $_POST['new_base_dir'];
			ksort($modSettings['attachment_basedirectories']);

			$update = (array(
				'attachment_basedirectories' => $smcFunc['json_encode']($modSettings['attachment_basedirectories']),
				'basedirectory_for_attachments' => $_POST['new_base_dir'],
				'currentAttachmentUploadDir' => $current_dir,
			));
		}

		if (!empty($errors))
			$_SESSION['errors']['base'] = $errors;

		if (!empty($update))
			updateSettings($update);

		redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id']);
	}

	if (isset($_SESSION['errors']))
	{
		if (is_array($_SESSION['errors']))
		{
			$errors = array();
			if (!empty($_SESSION['errors']['dir']))
				foreach ($_SESSION['errors']['dir'] as $error)
					$errors['dir'][] = $smcFunc['htmlspecialchars']($error, ENT_QUOTES);

			if (!empty($_SESSION['errors']['base']))
				foreach ($_SESSION['errors']['base'] as $error)
					$errors['base'][] = $smcFunc['htmlspecialchars']($error, ENT_QUOTES);
		}
		unset($_SESSION['errors']);
	}

	$listOptions = array(
		'id' => 'attach_paths',
		'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
		'title' => $txt['attach_paths'],
		'get_items' => array(
			'function' => 'list_getAttachDirs',
		),
		'columns' => array(
			'current_dir' => array(
				'header' => array(
					'value' => $txt['attach_current'],
					'class' => 'centercol',
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="radio" name="current_dir" value="' . $rowData['id'] . '"' . ($rowData['current'] ? ' checked' : '') . (!empty($rowData['disable_current']) ? ' disabled' : '') . '>';
					},
					'style' => 'width: 10%;',
					'class' => 'centercol',
				),
			),
			'path' => array(
				'header' => array(
					'value' => $txt['attach_path'],
				),
				'data' => array(
					'function' => function($rowData)
					{
						return '<input type="hidden" name="dirs[' . $rowData['id'] . ']" value="' . $rowData['path'] . '"><input type="text" size="40" name="dirs[' . $rowData['id'] . ']" value="' . $rowData['path'] . '"' . (!empty($rowData['disable_base_dir']) ? ' disabled' : '') . ' style="width: 100%">';
					},
					'style' => 'width: 40%;',
				),
			),
			'current_size' => array(
				'header' => array(
					'value' => $txt['attach_current_size'],
				),
				'data' => array(
					'db' => 'current_size',
					'style' => 'width: 15%;',
				),
			),
			'num_files' => array(
				'header' => array(
					'value' => $txt['attach_num_files'],
				),
				'data' => array(
					'db' => 'num_files',
					'style' => 'width: 15%;',
				),
			),
			'status' => array(
				'header' => array(
					'value' => $txt['attach_dir_status'],
					'class' => 'centercol',
				),
				'data' => array(
					'db' => 'status',
					'style' => 'width: 25%;',
					'class' => 'centercol',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
				<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
				<input type="submit" name="save" value="' . $txt['save'] . '" class="button">
				<input type="submit" name="new_path" value="' . $txt['attach_add_path'] . '" class="button">',
			),
			empty($errors['dir']) ? array(
				'position' => 'top_of_list',
				'value' => $txt['attach_dir_desc'],
				'class' => 'information'
			) : array(
				'position' => 'top_of_list',
				'value' => $txt['attach_dir_save_problem'] . '<br>' . implode('<br>', $errors['dir']),
				'style' => 'padding-left: 35px;',
				'class' => 'noticebox',
			),
		),
	);
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	if (!empty($modSettings['attachment_basedirectories']))
	{
		$listOptions2 = array(
			'id' => 'base_paths',
			'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
			'title' => $txt['attach_base_paths'],
			'get_items' => array(
				'function' => 'list_getBaseDirs',
			),
			'columns' => array(
				'current_dir' => array(
					'header' => array(
						'value' => $txt['attach_current'],
						'class' => 'centercol',
					),
					'data' => array(
						'function' => function($rowData)
						{
							return '<input type="radio" name="current_base_dir" value="' . $rowData['id'] . '"' . ($rowData['current'] ? ' checked' : '') . '>';
						},
						'style' => 'width: 10%;',
						'class' => 'centercol',
					),
				),
				'path' => array(
					'header' => array(
						'value' => $txt['attach_path'],
					),
					'data' => array(
						'db' => 'path',
						'style' => 'width: 45%;',
					),
				),
				'num_dirs' => array(
					'header' => array(
						'value' => $txt['attach_num_dirs'],
					),
					'data' => array(
						'db' => 'num_dirs',
						'style' => 'width: 15%;',
					),
				),
				'status' => array(
					'header' => array(
						'value' => $txt['attach_dir_status'],
					),
					'data' => array(
						'db' => 'status',
						'style' => 'width: 15%;',
						'class' => 'centercol',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
			),
			'additional_rows' => array(
				array(
					'position' => 'below_table_data',
					'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '"><input type="submit" name="save2" value="' . $txt['save'] . '" class="button">
					<input type="submit" name="new_base_path" value="' . $txt['attach_add_path'] . '" class="button">',
				),
				empty($errors['base']) ? array(
					'position' => 'top_of_list',
					'value' => $txt['attach_dir_base_desc'],
					'style' => 'padding: 5px 10px;',
					'class' => 'windowbg smalltext'
				) : array(
					'position' => 'top_of_list',
					'value' => $txt['attach_dir_save_problem'] . '<br>' . implode('<br>', $errors['base']),
					'style' => 'padding-left: 35px',
					'class' => 'noticebox',
				),
			),
		);
		createList($listOptions2);
	}

	// Fix up our template.
	$context[$context['admin_menu_name']]['current_subsection'] = 'attachpaths';
	$context['page_title'] = $txt['attach_path_manage'];
	$context['sub_template'] = 'attachment_paths';
}

/**
 * Prepare the actual attachment directories to be displayed in the list.
 *
 * @return array An array of information about the attachment directories
 */
function list_getAttachDirs()
{
	global $smcFunc, $modSettings, $context, $txt;

	$request = $smcFunc['db_query']('', '
		SELECT id_folder, COUNT(id_attach) AS num_attach, SUM(size) AS size_attach
		FROM {db_prefix}attachments
		WHERE attachment_type != {int:type}
		GROUP BY id_folder',
		array(
			'type' => 1,
		)
	);

	$expected_files = array();
	$expected_size = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$expected_files[$row['id_folder']] = $row['num_attach'];
		$expected_size[$row['id_folder']] = $row['size_attach'];
	}
	$smcFunc['db_free_result']($request);

	$attachdirs = array();
	foreach ($modSettings['attachmentUploadDir'] as $id => $dir)
	{
		// If there aren't any attachments in this directory this won't exist.
		if (!isset($expected_files[$id]))
			$expected_files[$id] = 0;

		// Check if the directory is doing okay.
		list ($status, $error, $files) = attachDirStatus($dir, $expected_files[$id]);

		// If it is one, let's show that it's a base directory.
		$sub_dirs = 0;
		$is_base_dir = false;
		if (!empty($modSettings['attachment_basedirectories']))
		{
			$is_base_dir = in_array($dir, $modSettings['attachment_basedirectories']);

			// Count any sub-folders.
			foreach ($modSettings['attachmentUploadDir'] as $sid => $sub)
				if (strpos($sub, $dir . DIRECTORY_SEPARATOR) !== false)
				{
					$expected_files[$id]++;
					$sub_dirs++;
				}
		}

		$attachdirs[] = array(
			'id' => $id,
			'current' => $id == $modSettings['currentAttachmentUploadDir'],
			'disable_current' => isset($modSettings['automanage_attachments']) && $modSettings['automanage_attachments'] > 1,
			'disable_base_dir' => $is_base_dir && $sub_dirs > 0 && !empty($files) && empty($error) && empty($save_errors),
			'path' => $dir,
			'current_size' => !empty($expected_size[$id]) ? comma_format($expected_size[$id] / 1024, 0) : 0,
			'num_files' => comma_format($expected_files[$id] - $sub_dirs, 0) . ($sub_dirs > 0 ? ' (' . $sub_dirs . ')' : ''),
			'status' => ($is_base_dir ? $txt['attach_dir_basedir'] . '<br>' : '') . ($error ? '<div class="error">' : '') . sprintf($txt['attach_dir_' . $status], $context['session_id'], $context['session_var']) . ($error ? '</div>' : ''),
		);
	}

	// Just stick a new directory on at the bottom.
	if (isset($_REQUEST['new_path']))
		$attachdirs[] = array(
			'id' => max(array_merge(array_keys($expected_files), array_keys($modSettings['attachmentUploadDir']))) + 1,
			'current' => false,
			'path' => '',
			'current_size' => '',
			'num_files' => '',
			'status' => '',
		);

	return $attachdirs;
}

/**
 * Prepare the base directories to be displayed in a list.
 *
 * @return void|array Returns nothing if there are no base directories, otherwise returns an array of info about the directories
 */
function list_getBaseDirs()
{
	global $modSettings, $txt;

	if (empty($modSettings['attachment_basedirectories']))
		return;

	$basedirs = array();
	// Get a list of the base directories.
	foreach ($modSettings['attachment_basedirectories'] as $id => $dir)
	{
		// Loop through the attach directory array to count any sub-directories
		$expected_dirs = 0;
		foreach ($modSettings['attachmentUploadDir'] as $sid => $sub)
			if (strpos($sub, $dir . DIRECTORY_SEPARATOR) !== false)
				$expected_dirs++;

		if (!is_dir($dir))
			$status = 'does_not_exist';
		elseif (!is_writeable($dir))
			$status = 'not_writable';
		else
			$status = 'ok';

		$basedirs[] = array(
			'id' => $id,
			'current' => $dir == $modSettings['basedirectory_for_attachments'],
			'path' => $expected_dirs > 0 ? $dir : ('<input type="text" name="base_dir[' . $id . ']" value="' . $dir . '" size="40">'),
			'num_dirs' => $expected_dirs,
			'status' => $status == 'ok' ? $txt['attach_dir_ok'] : ('<span class="error">' . $txt['attach_dir_' . $status] . '</span>'),
		);
	}

	if (isset($_REQUEST['new_base_path']))
		$basedirs[] = array(
			'id' => '',
			'current' => false,
			'path' => '<input type="text" name="new_base_dir" value="" size="40">',
			'num_dirs' => '',
			'status' => '',
		);

	return $basedirs;
}

/**
 * Checks the status of an attachment directory and returns an array
 *  of the status key, if that status key signifies an error, and
 *  the file count.
 *
 * @param string $dir The directory to check
 * @param int $expected_files How many files should be in that directory
 * @return array An array containing the status of the directory, whether the number of files was what we expected and how many were in the directory
 */
function attachDirStatus($dir, $expected_files)
{
	if (!is_dir($dir))
		return array('does_not_exist', true, '');
	elseif (!is_writable($dir))
		return array('not_writable', true, '');

	// Everything is okay so far, start to scan through the directory.
	$num_files = 0;
	$dir_handle = dir($dir);
	while ($file = $dir_handle->read())
	{
		// Now do we have a real file here?
		if (in_array($file, array('.', '..', '.htaccess', 'index.php')))
			continue;

		$num_files++;
	}
	$dir_handle->close();

	if ($num_files < $expected_files)
		return array('files_missing', true, $num_files);
	// Empty?
	elseif ($expected_files == 0)
		return array('unused', false, $num_files);
	// All good!
	else
		return array('ok', false, $num_files);
}

/**
 * Maintance function to move attachments from one directory to another
 */
function TransferAttachments()
{
	global $modSettings, $smcFunc, $sourcedir, $txt, $boarddir;

	checkSession();

	if (!empty($modSettings['attachment_basedirectories']))
		$modSettings['attachment_basedirectories'] = $smcFunc['json_decode']($modSettings['attachment_basedirectories'], true);
	else
		$modSettings['basedirectory_for_attachments'] = array();

	$_POST['from'] = (int) $_POST['from'];
	$_POST['auto'] = !empty($_POST['auto']) ? (int) $_POST['auto'] : 0;
	$_POST['to'] = (int) $_POST['to'];
	$start = !empty($_POST['empty_it']) ? 0 : $modSettings['attachmentDirFileLimit'];
	$_SESSION['checked'] = !empty($_POST['empty_it']) ? true : false;
	$limit = 501;
	$results = array();
	$dir_files = 0;
	$current_progress = 0;
	$total_moved = 0;
	$total_not_moved = 0;

	if (empty($_POST['from']) || (empty($_POST['auto']) && empty($_POST['to'])))
		$results[] = $txt['attachment_transfer_no_dir'];

	if ($_POST['from'] == $_POST['to'])
		$results[] = $txt['attachment_transfer_same_dir'];

	if (empty($results))
	{
		// Get the total file count for the progess bar.
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}attachments
			WHERE id_folder = {int:folder_id}
				AND attachment_type != {int:attachment_type}',
			array(
				'folder_id' => $_POST['from'],
				'attachment_type' => 1,
			)
		);
		list ($total_progress) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$total_progress -= $start;

		if ($total_progress < 1)
			$results[] = $txt['attachment_transfer_no_find'];
	}

	if (empty($results))
	{
		// Where are they going?
		if (!empty($_POST['auto']))
		{
			require_once($sourcedir . '/Subs-Attachments.php');

			$modSettings['automanage_attachments'] = 1;
			$modSettings['use_subdirectories_for_attachments'] = $_POST['auto'] == -1 ? 0 : 1;
			$modSettings['basedirectory_for_attachments'] = $_POST['auto'] > 0 ? $modSettings['attachmentUploadDir'][$_POST['auto']] : $modSettings['basedirectory_for_attachments'];

			automanage_attachments_check_directory();
			$new_dir = $modSettings['currentAttachmentUploadDir'];
		}
		else
			$new_dir = $_POST['to'];

		$modSettings['currentAttachmentUploadDir'] = $new_dir;

		$break = false;
		while ($break == false)
		{
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			// If limits are set, get the file count and size for the destination folder
			if ($dir_files <= 0 && (!empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit'])))
			{
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(*), SUM(size)
					FROM {db_prefix}attachments
					WHERE id_folder = {int:folder_id}
						AND attachment_type != {int:attachment_type}',
					array(
						'folder_id' => $new_dir,
						'attachment_type' => 1,
					)
				);
				list ($dir_files, $dir_size) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
			}

			// Find some attachments to move
			$request = $smcFunc['db_query']('', '
				SELECT id_attach, filename, id_folder, file_hash, size
				FROM {db_prefix}attachments
				WHERE id_folder = {int:folder}
					AND attachment_type != {int:attachment_type}
				LIMIT {int:start}, {int:limit}',
				array(
					'folder' => $_POST['from'],
					'attachment_type' => 1,
					'start' => $start,
					'limit' => $limit,
				)
			);

			if ($smcFunc['db_num_rows']($request) === 0)
			{
				if (empty($current_progress))
					$results[] = $txt['attachment_transfer_no_find'];
				break;
			}

			if ($smcFunc['db_num_rows']($request) < $limit)
				$break = true;

			// Move them
			$moved = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$source = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);
				$dest = $modSettings['attachmentUploadDir'][$new_dir] . '/' . basename($source);

				// Size and file count check
				if (!empty($modSettings['attachmentDirSizeLimit']) || !empty($modSettings['attachmentDirFileLimit']))
				{
					$dir_files++;
					$dir_size += !empty($row['size']) ? $row['size'] : filesize($source);

					// If we've reached a limit. Do something.
					if (!empty($modSettings['attachmentDirSizeLimit']) && $dir_size > $modSettings['attachmentDirSizeLimit'] * 1024 || (!empty($modSettings['attachmentDirFileLimit']) && $dir_files > $modSettings['attachmentDirFileLimit']))
					{
						if (!empty($_POST['auto']))
						{
							// Since we're in auto mode. Create a new folder and reset the counters.
							automanage_attachments_by_space();

							$results[] = sprintf($txt['attachments_transferred'], $total_moved, $modSettings['attachmentUploadDir'][$new_dir]);
							if (!empty($total_not_moved))
								$results[] = sprintf($txt['attachments_not_transferred'], $total_not_moved);

							$dir_files = 0;
							$total_moved = 0;
							$total_not_moved = 0;

							$break = false;
							break;
						}
						else
						{
							// Hmm, not in auto. Time to bail out then...
							$results[] = $txt['attachment_transfer_no_room'];
							$break = true;
							break;
						}
					}
				}

				if (@rename($source, $dest))
				{
					$total_moved++;
					$current_progress++;
					$moved[] = $row['id_attach'];
				}
				else
					$total_not_moved++;
			}
			$smcFunc['db_free_result']($request);

			if (!empty($moved))
			{
				// Update the database
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}attachments
					SET id_folder = {int:new}
					WHERE id_attach IN ({array_int:attachments})',
					array(
						'attachments' => $moved,
						'new' => $new_dir,
					)
				);
			}

			$new_dir = $modSettings['currentAttachmentUploadDir'];

			// Create the progress bar.
			if (!$break)
			{
				$percent_done = min(round($current_progress / $total_progress * 100, 0), 100);
				$prog_bar = '
					<div class="progress_bar">
						<div class="bar" style="width: ' . $percent_done . '%;"></div>
						<span>' . $percent_done . '%</span>
					</div>';
				// Write it to a file so it can be displayed
				$fp = fopen($boarddir . '/progress.php', "w");
				fwrite($fp, $prog_bar);
				fclose($fp);
				usleep(500000);
			}
		}

		$results[] = sprintf($txt['attachments_transferred'], $total_moved, $modSettings['attachmentUploadDir'][$new_dir]);
		if (!empty($total_not_moved))
			$results[] = sprintf($txt['attachments_not_transferred'], $total_not_moved);
	}

	$_SESSION['results'] = $results;
	if (file_exists($boarddir . '/progress.php'))
		unlink($boarddir . '/progress.php');

	redirectexit('action=admin;area=manageattachments;sa=maintenance#transfer');
}

?>