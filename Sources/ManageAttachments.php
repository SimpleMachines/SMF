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

/* /!!!

	void ManageAttachments()
		- main 'Attachments and Avatars' center function.
		- entry point for index.php?action=admin;area=manageattachments.
		- requires the manage_attachments permission.
		- load the ManageAttachments template.
		- uses the Admin language file.
		- uses the template layer 'manage_files' for showing the tab bar.
		- calls a function based on the sub-action.

	void ManageAttachmentSettings()
		- show/change attachment settings.
		- default sub action for the 'Attachments and Avatars' center.
		- uses the 'attachments' sub template.
		- called by index.php?action=admin;area=manageattachments;sa=attachements.

	void ManageAvatarSettings()
		- show/change avatar settings.
		- called by index.php?action=admin;area=manageattachments;sa=avatars.
		- uses the 'avatars' sub template.
		- show/set permissions for permissions: 'profile_server_avatar',
		  'profile_upload_avatar' and 'profile_remote_avatar'.

	void BrowseFiles()
		- show a list of attachment or avatar files.
		- called by ?action=admin;area=manageattachments;sa=browse for attachments and
		  ?action=admin;area=manageattachments;sa=browse;avatars for avatars.
		- uses the 'browse' sub template
		- allows sorting by name, date, size and member.
		- paginates results.

	void MaintainFiles()
		- show several file maintenance options.
		- called by ?action=admin;area=manageattachments;sa=maintain.
		- uses the 'maintain' sub template.
		- calculates file statistics (total file size, number of attachments,
		  number of avatars, attachment space available).

	void MoveAvatars()
		- move avatars from or to the attachment directory.
		- called from the maintenance screen by
		  ?action=admin;area=manageattachments;sa=moveAvatars.

	void RemoveAttachmentByAge()
		- remove attachments older than a given age.
		- called from the maintenance screen by
		  ?action=admin;area=manageattachments;sa=byAge.
		- optionally adds a certain text to the messages the attachments were
		  removed from.

	void RemoveAttachmentBySize()
		- remove attachments larger than a given size.
		- called from the maintenance screen by
		  ?action=admin;area=manageattachments;sa=bySize.
		- optionally adds a certain text to the messages the attachments were
		  removed from.

	void RemoveAttachment()
		- remove a selection of attachments or avatars.
		- called from the browse screen as submitted form by
		  ?action=admin;area=manageattachments;sa=remove

	void RemoveAllAttachments()
		- removes all attachments in a single click
		- called from the maintenance screen by
		  ?action=admin;area=manageattachments;sa=removeall.

	array removeAttachments(array condition, string query_type = '', bool return_affected_messages = false, bool autoThumbRemoval = true)
		- removes attachments or avatars based on a given query condition.
		- called by several remove avatar/attachment functions in this file.
		- removes attachments based that match the $condition.
		- allows query_types 'messages' and 'members', whichever is need by the
		  $condition parameter.

	void RepairAttachments()
		// !!!

	void PauseAttachmentMaintenance()
		// !!!

	void ApproveAttach()
		// !!!

	void ApproveAttachments()
		// !!!
*/

// The main attachment management function.
function ManageAttachments()
{
	global $txt, $modSettings, $scripturl, $context, $options;

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
		'moveAvatars' => 'MoveAvatars',
		'repair' => 'RepairAttachments',
		'remove' => 'RemoveAttachment',
		'removeall' => 'RemoveAllAttachments'
	);

	// Pick the correct sub-action.
	if (isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
		$context['sub_action'] = 'browse';

	// Default page title is good.
	$context['page_title'] = $txt['attachments_avatars'];

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['attachments_avatars'],
		'help' => 'manage_files',
		'description' => $txt['attachments_desc'],
	);

	// Finally fall through to what we are doing.
	$subActions[$context['sub_action']]();
}

function ManageAttachmentSettings($return_config = false)
{
	global $txt, $modSettings, $scripturl, $context, $options, $sourcedir;

	$context['valid_upload_dir'] = is_dir($modSettings['attachmentUploadDir']) && is_writable($modSettings['attachmentUploadDir']);

	// Perform a test to see if the GD module is installed.
	$testGD = get_extension_funcs('gd');

	$config_vars = array(
		array('title', 'attachment_manager_settings'),
			// Are attachments enabled?
			array('select', 'attachmentEnable', array($txt['attachmentEnable_deactivate'], $txt['attachmentEnable_enable_all'], $txt['attachmentEnable_disable_new'])),
		'',
			// Extension checks etc.
			array('check', 'attachmentCheckExtensions'),
			array('text', 'attachmentExtensions', 40),
			array('check', 'attachmentRecodeLineEndings'),
		'',
			// Directory and size limits.
			empty($modSettings['currentAttachmentUploadDir']) ? array('text', 'attachmentUploadDir', 40, 'invalid' => !$context['valid_upload_dir']) : array('var_message', 'attachmentUploadDir_multiple', 'message' => 'attachmentUploadDir_multiple_configure'),
			array('text', 'attachmentDirSizeLimit', 6, 'postinput' => $txt['kilobyte']),
			array('text', 'attachmentPostLimit', 6, 'postinput' => $txt['kilobyte']),
			array('text', 'attachmentSizeLimit', 6, 'postinput' => $txt['kilobyte']),
			array('text', 'attachmentNumPerPostLimit', 6),
		'',
			// Image settings.
			array('warning', empty($testGD) ? 'attachment_gd_warning' : ''),
			array('check', 'attachment_image_reencode'),
		'',
			array('warning', 'attachment_image_paranoid_warning'),
			array('check', 'attachment_image_paranoid'),
		'',
			// Thumbnail settings.
			array('check', 'attachmentShowImages'),
			array('check', 'attachmentThumbnails'),
			array('check', 'attachment_thumb_png'),
			array('text', 'attachmentThumbWidth', 6),
			array('text', 'attachmentThumbHeight', 6),
	);

	if ($return_config)
		return $config_vars;

	// These are very likely to come in handy! (i.e. without them we're doomed!)
	require_once($sourcedir . '/ManagePermissions.php');
	require_once($sourcedir . '/ManageServer.php');

	// Saving settings?
	if (isset($_GET['save']))
	{
		checkSession();

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=manageattachments;sa=attachments');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=manageattachments;save;sa=attachments';
	prepareDBSettingContext($config_vars);

	$context['sub_template'] = 'show_settings';
}

function ManageAvatarSettings($return_config = false)
{
	global $txt, $context, $modSettings, $sourcedir, $scripturl;

	// Perform a test to see if the GD module is installed.
	$testGD = get_extension_funcs('gd');

	$context['valid_avatar_dir'] = is_dir($modSettings['avatar_directory']);
	$context['valid_custom_avatar_dir'] = empty($modSettings['custom_avatar_enabled']) || (!empty($modSettings['custom_avatar_dir']) && is_dir($modSettings['custom_avatar_dir']) && is_writable($modSettings['custom_avatar_dir']));

	$config_vars = array(
		// Server stored avatars!
		array('title', 'avatar_server_stored'),
			array('warning', empty($testGD) ? 'avatar_gd_warning' : ''),
			array('permissions', 'profile_server_avatar', 0, $txt['avatar_server_stored_groups']),
			array('text', 'avatar_directory', 40, 'invalid' => !$context['valid_avatar_dir']),
			array('text', 'avatar_url', 40),
		// External avatars?
		array('title', 'avatar_external'),
			array('permissions', 'profile_remote_avatar', 0, $txt['avatar_external_url_groups']),
			array('check', 'avatar_download_external', 0, 'onchange' => 'fUpdateStatus();'),
			array('text', 'avatar_max_width_external', 6),
			array('text', 'avatar_max_height_external', 6),
			array('select', 'avatar_action_too_large',
				array(
					'option_refuse' => $txt['option_refuse'],
					'option_html_resize' => $txt['option_html_resize'],
					'option_js_resize' => $txt['option_js_resize'],
					'option_download_and_resize' => $txt['option_download_and_resize'],
				),
			),
		// Uploadable avatars?
		array('title', 'avatar_upload'),
			array('permissions', 'profile_upload_avatar', 0, $txt['avatar_upload_groups']),
			array('text', 'avatar_max_width_upload', 6),
			array('text', 'avatar_max_height_upload', 6),
			array('check', 'avatar_resize_upload', 'subtext' => $txt['avatar_resize_upload_note']),
			array('check', 'avatar_reencode'),
		'',
			array('warning', 'avatar_paranoid_warning'),
			array('check', 'avatar_paranoid'),
		'',
			array('check', 'avatar_download_png'),
			array('select', 'custom_avatar_enabled', array($txt['option_attachment_dir'], $txt['option_specified_dir']), 'onchange' => 'fUpdateStatus();'),
			array('text', 'custom_avatar_dir', 40, 'subtext' => $txt['custom_avatar_dir_desc'], 'invalid' => !$context['valid_custom_avatar_dir']),
			array('text', 'custom_avatar_url', 40),
	);

	if ($return_config)
		return $config_vars;

	// We need these files for the inline permission settings, and the settings template.
	require_once($sourcedir . '/ManagePermissions.php');
	require_once($sourcedir . '/ManageServer.php');

	// Saving avatar settings?
	if (isset($_GET['save']))
	{
		checkSession();

		// Just incase the admin forgot to set both custom avatar values, we disable it to prevent errors.
		if (isset($_POST['custom_avatar_enabled']) && $_POST['custom_avatar_enabled'] == 1 && (empty($_POST['custom_avatar_dir']) || empty($_POST['custom_avatar_url'])))
			$_POST['custom_avatar_enabled'] = 0;

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=manageattachments;sa=avatars');
	}

	// Attempt to figure out if the admin is trying to break things.
	$context['settings_save_onclick'] = 'return document.getElementById(\'custom_avatar_enabled\').value == 1 && (document.getElementById(\'custom_avatar_dir\').value == \'\' || document.getElementById(\'custom_avatar_url\').value == \'\') ? confirm(\'' . $txt['custom_avatar_check_empty'] . '\') : true;';

	// Prepare the context.
	$context['post_url'] = $scripturl . '?action=admin;area=manageattachments;save;sa=avatars';
	prepareDBSettingContext($config_vars);

	// Add a layer for the javascript.
	$context['template_layers'][] = 'avatar_settings';
	$context['sub_template'] = 'show_settings';
}

function BrowseFiles()
{
	global $context, $txt, $scripturl, $options, $modSettings;
	global $smcFunc, $sourcedir;

	$context['sub_template'] = 'browse';

	// Attachments or avatars?
	$context['browse_type'] = isset($_REQUEST['avatars']) ? 'avatars' : (isset($_REQUEST['thumbs']) ? 'thumbs' : 'attachments');

	// Set the options for the list component.
	$listOptions = array(
		'id' => 'file_list',
		'title' => $txt['attachment_manager_' . ($context['browse_type'] === 'avatars' ? 'avatars' : ( $context['browse_type'] === 'thumbs' ? 'thumbs' : 'attachments'))],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'base_href' => $scripturl . '?action=admin;area=manageattachments;sa=browse' . ($context['browse_type'] === 'avatars' ? ';avatars' : ($context['browse_type'] === 'thumbs' ? ';thumbs' : '')),
		'default_sort_col' => 'name',
		'no_items_label' => $txt['attachment_manager_' . ($context['browse_type'] === 'avatars' ? 'avatars' : ( $context['browse_type'] === 'thumbs' ? 'thumbs' : 'attachments')) . '_no_entries'],
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
					'function' => create_function('$rowData', '
						global $modSettings, $context, $scripturl;

						$link = \'<a href="\';

						// In case of a custom avatar URL attachments have a fixed directory.
						if ($rowData[\'attachment_type\'] == 1)
							$link .= sprintf(\'%1$s/%2$s\', $modSettings[\'custom_avatar_url\'], $rowData[\'filename\']);

						// By default avatars are downloaded almost as attachments.
						elseif ($context[\'browse_type\'] == \'avatars\')
							$link .= sprintf(\'%1$s?action=dlattach;type=avatar;attach=%2$d\', $scripturl, $rowData[\'id_attach\']);

						// Normal attachments are always linked to a topic ID.
						else
							$link .= sprintf(\'%1$s?action=dlattach;topic=%2$d.0;attach=%3$d\', $scripturl, $rowData[\'id_topic\'], $rowData[\'id_attach\']);

						$link .= \'"\';

						// Show a popup on click if it\'s a picture and we know its dimensions.
						if (!empty($rowData[\'width\']) && !empty($rowData[\'height\']))
							$link .= sprintf(\' onclick="return reqWin(this.href\' . ($rowData[\'attachment_type\'] == 1 ? \'\' : \' + \\\';image\\\'\') . \', %1$d, %2$d, true);"\', $rowData[\'width\'] + 20, $rowData[\'height\'] + 20);

						$link .= sprintf(\'>%1$s</a>\', preg_replace(\'~&amp;#(\\\\d{1,7}|x[0-9a-fA-F]{1,6});~\', \'&#\\\\1;\', htmlspecialchars($rowData[\'filename\'])));

						// Show the dimensions.
						if (!empty($rowData[\'width\']) && !empty($rowData[\'height\']))
							$link .= sprintf(\' <span class="smalltext">%1$dx%2$d</span>\', $rowData[\'width\'], $rowData[\'height\']);

						return $link;
					'),
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
					'function' => create_function('$rowData','
						global $txt;

						return sprintf(\'%1$s%2$s\', round($rowData[\'size\'] / 1024, 2), $txt[\'kilobyte\']);
					'),
					'class' => 'windowbg',
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
					'function' => create_function('$rowData', '
						global $scripturl;

						// In case of an attachment, return the poster of the attachment.
						if (empty($rowData[\'id_member\']))
							return htmlspecialchars($rowData[\'poster_name\']);

						// Otherwise it must be an avatar, return the link to the owner of it.
						else
							return sprintf(\'<a href="%1$s?action=profile;u=%2$d">%3$s</a>\', $scripturl, $rowData[\'id_member\'], $rowData[\'poster_name\']);
					'),
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
					'function' => create_function('$rowData', '
						global $txt, $context, $scripturl;

						// The date the message containing the attachment was posted or the owner of the avatar was active.
						$date = empty($rowData[\'poster_time\']) ? $txt[\'never\'] : timeformat($rowData[\'poster_time\']);

						// Add a link to the topic in case of an attachment.
						if ($context[\'browse_type\'] !== \'avatars\')
							$date .= sprintf(\'<br />%1$s <a href="%2$s?topic=%3$d.0.msg%4$d#msg%4$d">%5$s</a>\', $txt[\'in\'], $scripturl, $rowData[\'id_topic\'], $rowData[\'id_msg\'], $rowData[\'subject\']);

						return $date;
						'),
					'class' => 'windowbg',
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
					'function' => create_function('$rowData','
						global $txt;

						return comma_format($rowData[\'downloads\']);
					'),
					'class' => 'windowbg',
				),
				'sort' => array(
					'default' => 'a.downloads',
					'reverse' => 'a.downloads DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[%1$d]" class="input_check" />',
						'params' => array(
							'id_attach' => false,
						),
					),
					'style' => 'text-align: center',
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
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="remove_submit" class="button_submit" value="' . $txt['quickmod_delete_selected'] . '" onclick="return confirm(\'' . $txt['confirm_delete_attachments'] . '\');" />',
				'style' => 'text-align: right;',
			),
		),
	);

	// Create the list.
	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);
}

function list_getFiles($start, $items_per_page, $sort, $browse_type)
{
	global $smcFunc, $txt;

	// Choose a query depending on what we are viewing.
	if ($browse_type === 'avatars')
		$request = $smcFunc['db_query']('', '
			SELECT
				{string:blank_text} AS id_msg, IFNULL(mem.real_name, {string:not_applicable_text}) AS poster_name,
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
				m.id_msg, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.id_topic, m.id_member,
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

function MaintainFiles()
{
	global $context, $modSettings, $txt, $smcFunc;

	$context['sub_template'] = 'maintenance';

	if (!empty($modSettings['currentAttachmentUploadDir']))
		$attach_dirs = unserialize($modSettings['attachmentUploadDir']);
	else
		$attach_dirs = array($modSettings['attachmentUploadDir']);

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

	// Find out how big the directory is. We have to loop through all our attachment paths in case there's an old temp file in one of them.
	$attachmentDirSize = 0;
	foreach ($attach_dirs as $id => $attach_dir)
	{
		$dir = @opendir($attach_dir) or fatal_lang_error('cant_access_upload_path', 'critical');
		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..')
				continue;

			if (preg_match('~^post_tmp_\d+_\d+$~', $file) != 0)
			{
				// Temp file is more than 5 hours old!
				if (filemtime($attach_dir . '/' . $file) < time() - 18000)
					@unlink($attach_dir . '/' . $file);
				continue;
			}

			// We're only counting the size of the current attachment directory.
			if (empty($modSettings['currentAttachmentUploadDir']) || $modSettings['currentAttachmentUploadDir'] == $id)
				$attachmentDirSize += filesize($attach_dir . '/' . $file);
		}
		closedir($dir);
	}
	// Divide it into kilobytes.
	$attachmentDirSize /= 1024;

	// If they specified a limit only....
	if (!empty($modSettings['attachmentDirSizeLimit']))
		$context['attachment_space'] = max(round($modSettings['attachmentDirSizeLimit'] - $attachmentDirSize, 2), 0);
	$context['attachment_total_size'] = round($attachmentDirSize, 2);

	$context['attach_multiple_dirs'] = !empty($modSettings['currentAttachmentUploadDir']);
}

// !!! Not implemented yet.
function MoveAvatars()
{
	global $modSettings, $smcFunc;

	// First make sure the custom avatar dir is writable.
	if (!is_writable($modSettings['custom_avatar_dir']))
	{
		// Try to fix it.
		@chmod($modSettings['custom_avatar_dir'], 0777);

		// Guess that didn't work?
		if (!is_writable($modSettings['custom_avatar_dir']))
			fatal_lang_error('attachments_no_write', 'critical');
	}

	$request = $smcFunc['db_query']('', '
		SELECT id_attach, id_folder, id_member, filename, file_hash
		FROM {db_prefix}attachments
		WHERE attachment_type = {int:attachment_type}
			AND id_member > {int:guest_id_member}',
		array(
			'attachment_type' => 0,
			'guest_id_member' => 0,
		)
	);
	$updatedAvatars = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$filename = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], false, $row['file_hash']);

		if (rename($filename, $modSettings['custom_avatar_dir'] . '/' . $row['filename']))
			$updatedAvatars[] = $row['id_attach'];
	}
	$smcFunc['db_free_result']($request);

	if (!empty($updatedAvatars))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}attachments
			SET attachment_type = {int:attachment_type}
			WHERE id_attach IN ({array_int:updated_avatars})',
			array(
				'updated_avatars' => $updatedAvatars,
				'attachment_type' => 1,
			)
		);

	redirectexit('action=admin;area=manageattachments;sa=maintenance');
}

function RemoveAttachmentByAge()
{
	global $modSettings, $smcFunc;

	checkSession('post', 'admin');

	// !!! Ignore messages in topics that are stickied?

	// Deleting an attachment?
	if ($_REQUEST['type'] != 'avatars')
	{
		// Get all the old attachments.
		$messages = removeAttachments(array('attachment_type' => 0, 'poster_time' => (time() - 24 * 60 * 60 * $_POST['age'])), 'messages', true);

		// Update the messages to reflect the change.
		if (!empty($messages))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}messages
				SET body = CONCAT(body, ' . (!empty($_POST['notice']) ? '{string:notice}' : '') . ')
				WHERE id_msg IN ({array_int:messages})',
				array(
					'messages' => $messages,
					'notice' => empty($_POST['notice']) ? '' : '<br /><br />' . $_POST['notice'],
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

function RemoveAttachmentBySize()
{
	global $modSettings, $smcFunc;

	checkSession('post', 'admin');

	// Find humungous attachments.
	$messages = removeAttachments(array('attachment_type' => 0, 'size' => 1024 * $_POST['size']), 'messages', true);

	// And make a note on the post.
	if (!empty($messages))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}messages
			SET body = CONCAT(body, ' . (!empty($_POST['notice']) ? '{string:notice}' : '') . ')
			WHERE id_msg IN ({array_int:messages})',
			array(
				'messages' => $messages,
				'notice' => empty($_POST['notice']) ? '' : '<br /><br />' . $_POST['notice'],
			)
		);

	redirectexit('action=admin;area=manageattachments;sa=maintenance');
}

function RemoveAttachment()
{
	global $modSettings, $txt, $smcFunc;

	checkSession('post');

	if (!empty($_POST['remove']))
	{
		$attachments = array();
		// There must be a quicker way to pass this safety test??
		foreach ($_POST['remove'] as $removeID => $dummy)
			$attachments[] = (int) $removeID;

		if ($_REQUEST['type'] == 'avatars' && !empty($attachments))
			removeAttachments(array('id_attach' => $attachments));
		else if (!empty($attachments))
		{
			$messages = removeAttachments(array('id_attach' => $attachments), 'messages', true);

			// And change the message to reflect this.
			if (!empty($messages))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}messages
					SET body = CONCAT(body, {string:deleted_message})
					WHERE id_msg IN ({array_int:messages_affected})',
					array(
						'messages_affected' => $messages,
						'deleted_message' => '<br /><br />' . $txt['attachment_delete_admin'],
					)
				);
		}
	}

	$_GET['sort'] = isset($_GET['sort']) ? $_GET['sort'] : 'date';
	redirectexit('action=admin;area=manageattachments;sa=browse;' . $_REQUEST['type'] . ';sort=' . $_GET['sort'] . (isset($_GET['desc']) ? ';desc' : '') . ';start=' . $_REQUEST['start']);
}

// !!! Not implemented (yet?)
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
				'deleted_message' => '<br /><br />' . $_POST['notice'],
			)
		);

	redirectexit('action=admin;area=manageattachments;sa=maintenance');
}

// Removes attachments - allowed query_types: '', 'messages', 'members'
function removeAttachments($condition, $query_type = '', $return_affected_messages = false, $autoThumbRemoval = true)
{
	global $modSettings, $smcFunc;

	//!!! This might need more work!
	$new_condition = array();
	$query_parameter = array(
		'thumb_attachment_type' => 3,
	);

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
			thumb.id_folder AS thumb_folder, IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.filename AS thumb_filename, thumb.file_hash AS thumb_file_hash, thumb_parent.id_attach AS id_parent
		FROM {db_prefix}attachments AS a' .($query_type == 'members' ? '
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
			@unlink($modSettings['custom_avatar_dir'] . '/' . $row['filename']);
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

	if (!empty($attach))
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}attachments
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $attach,
			)
		);

	if ($return_affected_messages)
		return array_unique($msgs);
}

// This function should find attachments in the database that no longer exist and clear them, and fix filesize issues.
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
	$_GET['substep'] = empty($_GET['substep']) ? 0 : (int) $_GET['substep'];

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
			//!!! No need to do this I think.
			foreach ($_POST['to_fix'] as $key => $value)
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
						$attachment_name = !empty($row['file_hash']) ? $row['id_attach'] . '_' . $row['file_hash'] : getLegacyAttachmentFilename($row['filename'], $row['id_attach'], null, true);

						if (!is_array($modSettings['attachmentUploadDir']))
							$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

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

			$result = $smcFunc['db_query']('', '
				SELECT a.id_attach, a.id_folder, a.filename, a.file_hash
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
					AND a.id_member = {int:no_member}
					AND a.id_msg != {int:no_msg}
					AND m.id_msg IS NULL',
				array(
					'no_member' => 0,
					'no_msg' => 0,
					'substep' => $_GET['substep'],
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
						AND id_msg != {int:no_msg}',
					array(
						'to_remove' => $to_remove,
						'no_member' => 0,
						'no_msg' => 0,
					)
				);

			pauseAttachmentMaintenance($to_fix, $thumbnails);
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

function pauseAttachmentMaintenance($to_fix, $max_substep = 0)
{
	global $context, $txt, $time_start;

	// Try get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if (time() - array_sum(explode(' ', $time_start)) < 3)
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

// Called from a mouse click, works out what we want to do with attachments and actions it.
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

			// Also come up witht he redirection URL.
			$redirect = 'topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'];
		}
	}
	$smcFunc['db_free_result']($request);

	if (empty($attachments))
		fatal_lang_error('no_access', false);

	// Finally, we are there. Follow through!
	if ($is_approve)
		ApproveAttachments($attachments);
	else
		removeAttachments(array('id_attach' => $attachments));

	// Return to the topic....
	redirectexit($redirect);
}

// Approve an attachment, or maybe even more - no permission check!
function ApproveAttachments($attachments)
{
	global $smcFunc;

	if (empty($attachments))
		return 0;

	// For safety, check for thumbnails...
	$request = $smcFunc['db_query']('', '
		SELECT
			a.id_attach, a.id_member, IFNULL(thumb.id_attach, 0) AS id_thumb
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

	// Remove from the approval queue.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}approval_queue
		WHERE id_attach IN ({array_int:attachments})',
		array(
			'attachments' => $attachments,
		)
	);
}

function ManageAttachmentPaths()
{
	global $modSettings, $scripturl, $context, $txt, $sourcedir, $smcFunc;

	// Saving?
	if (isset($_REQUEST['save']))
	{
		checkSession();

		$new_dirs = array();
		foreach ($_POST['dirs'] as $id => $path)
		{
			$id = (int) $id;
			if ($id < 1)
				continue;

			if (empty($path))
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

				// It's safe to delete.
				if ($num_attach == 0)
					continue;
			}

			$new_dirs[$id] = $path;
		}

		// We need to make sure the current directory is right.
		$_POST['current_dir'] = (int) $_POST['current_dir'];
		if (empty($_POST['current_dir']) || empty($new_dirs[$_POST['current_dir']]))
			fatal_lang_error('attach_path_current_bad', false);

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

				updateSettings(array(
					'currentAttachmentUploadDir' => 0,
					'attachmentUploadDir' => $dir,
				));
			}
		}
		else
			// Save it to the database.
			updateSettings(array(
				'currentAttachmentUploadDir' => $_POST['current_dir'],
				'attachmentUploadDir' => serialize($new_dirs),
			));
	}

	// Are they here for the first time?
	if (empty($modSettings['currentAttachmentUploadDir']))
	{
		$modSettings['attachmentUploadDir'] = array(
			1 => $modSettings['attachmentUploadDir']
		);
		$modSettings['currentAttachmentUploadDir'] = 1;
	}
	// Otherwise just load up their attachment paths.
	else
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

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
					'value' => $txt['attach_current_dir'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="radio" name="current_dir" value="\' . $rowData[\'id\'] . \'" \' . ($rowData[\'current\'] ? \'checked="checked"\' : \'\') . \' class="input_radio" />\';
					'),
					'style' => 'text-align: center; width: 15%;',
				),
			),
			'path' => array(
				'header' => array(
					'value' => $txt['attach_path'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="text" size="30" name="dirs[\' . $rowData[\'id\'] . \']" value="\' . $rowData[\'path\'] . \'" class="input_text" style="width: 100%" />\';
					'),
					'style' => 'text-align: center; width: 30%;',
				),
			),
			'current_size' => array(
				'header' => array(
					'value' => $txt['attach_current_size'],
				),
				'data' => array(
					'db' => 'current_size',
					'style' => 'text-align: center; width: 15%;',
				),
			),
			'num_files' => array(
				'header' => array(
					'value' => $txt['attach_num_files'],
				),
				'data' => array(
					'db' => 'num_files',
					'style' => 'text-align: center; width: 15%;',
				),
			),
			'status' => array(
				'header' => array(
					'value' => $txt['attach_dir_status'],
				),
				'data' => array(
					'db' => 'status',
					'style' => 'text-align: center; width: 25%;',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . $context['session_var'] . '=' . $context['session_id'],
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" /><input type="submit" name="new_path" value="' . $txt['attach_add_path'] . '" class="button_submit" />&nbsp;<input type="submit" name="save" value="' . $txt['save'] . '" class="button_submit" />',
				'style' => 'text-align: right;',
			),
		),
	);

	require_once($sourcedir . '/Subs-List.php');
	createList($listOptions);

	// Fix up our template.
	$context[$context['admin_menu_name']]['current_subsection'] = 'attachments';
	$context['page_title'] = $txt['attach_path_manage'];
	$context['sub_template'] = 'attachment_paths';
}

// Prepare the actual attachment directories to be displayed in the list.
function list_getAttachDirs()
{
	global $smcFunc, $modSettings, $context, $txt;

	// The dirs should already have been unserialized but just in case...
	if (!is_array($modSettings['attachmentUploadDir']))
		$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

	$request = $smcFunc['db_query']('', '
		SELECT id_folder, COUNT(id_attach) AS num_attach
		FROM {db_prefix}attachments' . (empty($modSettings['custom_avatar_enabled']) ? '' : '
		WHERE attachment_type != {int:type_avatar}') . '
		GROUP BY id_folder',
		array(
			'type_avatar' => 1,
		)
	);

	$expected_files = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$expected_files[$row['id_folder']] = $row['num_attach'];
	$smcFunc['db_free_result']($request);

	$attachdirs = array();
	foreach ($modSettings['attachmentUploadDir'] as $id => $dir)
	{
		// If there aren't any attachments in this directory this won't exist.
		if (!isset($expected_files[$id]))
			$expected_files[$id] = 0;

		// Check if the directory is doing okay.
		list ($status, $error, $size) = attachDirStatus($dir, $expected_files[$id]);

		$attachdirs[] = array(
			'id' => $id,
			'current' => $id == $modSettings['currentAttachmentUploadDir'],
			'path' => $dir,
			'current_size' => $size,
			'num_files' => $expected_files[$id],
			'status' => ($error ? '<span class="error">' : '') . sprintf($txt['attach_dir_' . $status], $context['session_id'], $context['session_var']) . ($error ? '</span>' : ''),
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

// Checks the status of an attachment directory and returns an array of the status key, if that status key signifies an error, and the folder size.
function attachDirStatus($dir, $expected_files)
{
	if (!is_dir($dir))
		return array('does_not_exist', true, '');
	elseif (!is_writable($dir))
		return array('not_writable', true, '');

	// Everything is okay so far, start to scan through the directory.
	$dir_size = 0;
	$num_files = 0;
	$dir_handle = dir($dir);
	while ($file = $dir_handle->read())
	{
		// Now do we have a real file here?
		if (in_array($file, array('.', '..', '.htaccess', 'index.php')))
			continue;

		$dir_size += filesize($dir . '/' . $file);
		$num_files++;
	}
	$dir_handle->close();

	$dir_size = round($dir_size / 1024, 2);

	if ($num_files < $expected_files)
		return array('files_missing', true, $dir_size);
	// Empty?
	elseif ($expected_files == 0)
		return array('unused', false, $dir_size);
	// All good!
	else
		return array('ok', false, $dir_size);
}

?>