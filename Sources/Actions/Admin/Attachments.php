<?php

/**
 * This file doing the job of attachments and avatars maintenance and management.
 *
 * @todo refactor as controller-model
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF\Actions\Admin;

use SMF\Actions\ActionInterface;
use SMF\Attachment;
use SMF\BackwardCompatibility;
use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\IntegrationHook;
use SMF\ItemList;
use SMF\Lang;
use SMF\Menu;
use SMF\SecurityToken;
use SMF\Theme;
use SMF\Time;
use SMF\User;
use SMF\Utils;

/**
 * Maintains and manages attachments and avatars.
 */
class Attachments implements ActionInterface
{
	use BackwardCompatibility;

	/**
	 * @var array
	 *
	 * BackwardCompatibility settings for this class.
	 */
	private static $backcompat = [
		'func_names' => [
			'call' => 'ManageAttachments',
			'list_getFiles' => 'list_getFiles',
			'list_getNumFiles' => 'list_getNumFiles',
			'list_getAttachDirs' => 'list_getAttachDirs',
			'list_getBaseDirs' => 'list_getBaseDirs',
			'attachDirStatus' => 'attachDirStatus',
			'manageAttachmentSettings' => 'ManageAttachmentSettings',
			'manageAvatarSettings' => 'ManageAvatarSettings',
			'browseFiles' => 'BrowseFiles',
			'maintainFiles' => 'MaintainFiles',
			'removeAttachment' => 'RemoveAttachment',
			'removeAttachmentByAge' => 'RemoveAttachmentByAge',
			'removeAttachmentBySize' => 'RemoveAttachmentBySize',
			'removeAllAttachments' => 'RemoveAllAttachments',
			'repairAttachments' => 'RepairAttachments',
			'manageAttachmentPaths' => 'ManageAttachmentPaths',
			'transferAttachments' => 'TransferAttachments',
		],
	];

	/*******************
	 * Public properties
	 *******************/

	/**
	 * @var string
	 *
	 * The requested sub-action.
	 * This should be set by the constructor.
	 */
	public string $subaction = 'browse';

	/**************************
	 * Public static properties
	 **************************/

	/**
	 * @var array
	 *
	 * Available sub-actions.
	 */
	public static array $subactions = [
		'attachments' => 'attachmentSettings',
		'avatars' => 'avatarSettings',
		'browse' => 'browse',
		'maintenance' => 'maintain',
		'remove' => 'remove',
		'byage' => 'removeByAge',
		'bysize' => 'removeBySize',
		'removeall' => 'removeAll',
		'repair' => 'repair',
		'attachpaths' => 'paths',
		'transfer' => 'transfer',
	];

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var object
	 *
	 * An instance of this class.
	 * This is used by the load() method to prevent mulitple instantiations.
	 */
	protected static object $obj;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Dispatcher to whichever sub-action method is necessary.
	 */
	public function execute(): void
	{
		$call = method_exists($this, self::$subactions[$this->subaction]) ? [$this, self::$subactions[$this->subaction]] : Utils::getCallable(self::$subactions[$this->subaction]);

		if (!empty($call)) {
			call_user_func($call);
		}
	}

	/**
	 * Allows to show/change attachment settings.
	 * This is the default sub-action of the 'Attachments and Avatars' center.
	 * Called by index.php?action=admin;area=manageattachments;sa=attachments.
	 * Uses 'attachments' sub template.
	 *
	 * @param bool $return_config Whether to return the array of config variables (used for admin search)
	 * @return void|array If $return_config is true, simply returns the config_vars array, otherwise returns nothing
	 */
	public function attachmentSettings(): void
	{
		$config_vars = self::attachConfigVars();

		Utils::$context['settings_post_javascript'] = '
		var storing_type = document.getElementById(\'automanage_attachments\');
		var base_dir = document.getElementById(\'use_subdirectories_for_attachments\');

		createEventListener(storing_type)
		storing_type.addEventListener("change", toggleSubDir, false);
		createEventListener(base_dir)
		base_dir.addEventListener("change", toggleSubDir, false);
		toggleSubDir();';

		// Saving settings?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			if (isset($_POST['attachmentUploadDir'])) {
				unset($_POST['attachmentUploadDir']);
			}

			if (!empty($_POST['use_subdirectories_for_attachments'])) {
				if (
					isset($_POST['use_subdirectories_for_attachments'])
					&& empty($_POST['basedirectory_for_attachments'])
				) {
					$_POST['basedirectory_for_attachments'] = (!empty(Config::$modSettings['basedirectory_for_attachments']) ? (Config::$modSettings['basedirectory_for_attachments']) : Config::$boarddir);
				}

				if (
					!empty($_POST['use_subdirectories_for_attachments'])
					&& !empty(Config::$modSettings['attachment_basedirectories'])
				) {
					if (!is_array(Config::$modSettings['attachment_basedirectories'])) {
						Config::$modSettings['attachment_basedirectories'] = Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true);
					}
				} else {
					Config::$modSettings['attachment_basedirectories'] = [];
				}

				if (
					!empty($_POST['use_subdirectories_for_attachments'])
					&& !empty($_POST['basedirectory_for_attachments'])
					&& !in_array($_POST['basedirectory_for_attachments'], Config::$modSettings['attachment_basedirectories'])
				) {
					$currentAttachmentUploadDir = Config::$modSettings['currentAttachmentUploadDir'];

					if (!in_array($_POST['basedirectory_for_attachments'], Config::$modSettings['attachmentUploadDir'])) {
						if (!Attachment::automanageCreateDirectory($_POST['basedirectory_for_attachments'])) {
							$_POST['basedirectory_for_attachments'] = Config::$modSettings['basedirectory_for_attachments'];
						}
					}

					if (!in_array($_POST['basedirectory_for_attachments'], Config::$modSettings['attachment_basedirectories'])) {
						Config::$modSettings['attachment_basedirectories'][Config::$modSettings['currentAttachmentUploadDir']] = $_POST['basedirectory_for_attachments'];

						Config::updateModSettings([
							'attachment_basedirectories' => Utils::jsonEncode(
								Config::$modSettings['attachment_basedirectories'],
							),
							'currentAttachmentUploadDir' => $currentAttachmentUploadDir,
						]);

						$_POST['use_subdirectories_for_attachments'] = 1;
						$_POST['attachmentUploadDir'] = Utils::jsonEncode(
							Config::$modSettings['attachmentUploadDir'],
						);
					}
				}
			}

			IntegrationHook::call('integrate_save_attachment_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=manageattachments;sa=attachments');
		}

		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=manageattachments;save;sa=attachments';

		ACP::prepareDBSettingContext($config_vars);

		Utils::$context['sub_template'] = 'show_settings';
	}

	/**
	 * This allows to show/change avatar settings.
	 * Called by index.php?action=admin;area=manageattachments;sa=avatars.
	 * Show/set permissions for permissions: 'profile_server_avatar',
	 * 	'profile_upload_avatar' and 'profile_remote_avatar'.
	 */
	public function avatarSettings(): void
	{
		$config_vars = self::avatarConfigVars();

		// Saving avatar settings?
		if (isset($_GET['save'])) {
			User::$me->checkSession();

			// These settings cannot be left empty!
			if (empty($_POST['custom_avatar_dir'])) {
				$_POST['custom_avatar_dir'] = Config::$boarddir . '/custom_avatar';
			}

			if (empty($_POST['custom_avatar_url'])) {
				$_POST['custom_avatar_url'] = Config::$boardurl . '/custom_avatar';
			}

			if (empty($_POST['avatar_directory'])) {
				$_POST['avatar_directory'] = Config::$boarddir . '/avatars';
			}

			if (empty($_POST['avatar_url'])) {
				$_POST['avatar_url'] = Config::$boardurl . '/avatars';
			}

			IntegrationHook::call('integrate_save_avatar_settings');

			ACP::saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			Utils::redirectexit('action=admin;area=manageattachments;sa=avatars');
		}

		// Attempt to figure out if the admin is trying to break things.
		Utils::$context['settings_save_onclick'] = 'return (document.getElementById(\'custom_avatar_dir\').value == \'\' || document.getElementById(\'custom_avatar_url\').value == \'\') ? confirm(\'' . Lang::$txt['custom_avatar_check_empty'] . '\') : true;';

		// We need this for the in-line permissions
		SecurityToken::create('admin-mp');

		// Prepare the context.
		Utils::$context['post_url'] = Config::$scripturl . '?action=admin;area=manageattachments;save;sa=avatars';
		ACP::prepareDBSettingContext($config_vars);

		// Add a layer for the javascript.
		Utils::$context['template_layers'][] = 'avatar_settings';
		Utils::$context['sub_template'] = 'show_settings';
	}

	/**
	 * Show a list of attachment or avatar files.
	 * Called by ?action=admin;area=manageattachments;sa=browse for attachments
	 *  and ?action=admin;area=manageattachments;sa=browse;avatars for avatars.
	 * Allows sorting by name, date, size and member.
	 * Paginates results.
	 */
	public function browse(): void
	{
		// Attachments or avatars?
		Utils::$context['browse_type'] = isset($_REQUEST['avatars']) ? 'avatars' : (isset($_REQUEST['thumbs']) ? 'thumbs' : 'attachments');

		// Set the options for the list component.
		$listOptions = [
			'id' => 'file_list',
			'items_per_page' => Config::$modSettings['defaultMaxListItems'],
			'base_href' => Config::$scripturl . '?action=admin;area=manageattachments;sa=browse' . (Utils::$context['browse_type'] === 'avatars' ? ';avatars' : (Utils::$context['browse_type'] === 'thumbs' ? ';thumbs' : '')),
			'default_sort_col' => 'name',
			'no_items_label' => Lang::$txt['attachment_manager_' . (Utils::$context['browse_type'] === 'avatars' ? 'avatars' : (Utils::$context['browse_type'] === 'thumbs' ? 'thumbs' : 'attachments')) . '_no_entries'],
			'get_items' => [
				'function' => __CLASS__ . '::list_getFiles',
				'params' => [
					Utils::$context['browse_type'],
				],
			],
			'get_count' => [
				'function' => __CLASS__ . '::list_getNumFiles',
				'params' => [
					Utils::$context['browse_type'],
				],
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => Lang::$txt['attachment_name'],
					],
					'data' => [
						'function' => function ($rowData) {
							$link = '<a href="';

							// In case of a custom avatar URL attachments have a fixed directory.
							if ($rowData['attachment_type'] == 1) {
								$link .= sprintf('%1$s/%2$s', Config::$modSettings['custom_avatar_url'], $rowData['filename']);
							}
							// By default avatars are downloaded almost as attachments.
							elseif (Utils::$context['browse_type'] == 'avatars') {
								$link .= sprintf('%1$s?action=dlattach;type=avatar;attach=%2$d', Config::$scripturl, $rowData['id_attach']);
							}
							// Normal attachments are always linked to a topic ID.
							else {
								$link .= sprintf('%1$s?action=dlattach;topic=%2$d.0;attach=%3$d', Config::$scripturl, $rowData['id_topic'], $rowData['id_attach']);
							}

							$link .= '"';

							// Show a popup on click if it's a picture and we know its dimensions.
							if (!empty($rowData['width']) && !empty($rowData['height'])) {
								$link .= sprintf(' onclick="return reqWin(this.href' . ($rowData['attachment_type'] == 1 ? '' : ' + \';image\'') . ', %1$d, %2$d, true);"', $rowData['width'] + 20, $rowData['height'] + 20);
							}

							$link .= sprintf('>%1$s</a>', preg_replace('~&amp;#(\\\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\\\1;', Utils::htmlspecialchars($rowData['filename'])));

							// Show the dimensions.
							if (!empty($rowData['width']) && !empty($rowData['height'])) {
								$link .= sprintf(' <span class="smalltext">%1$dx%2$d</span>', $rowData['width'], $rowData['height']);
							}

							return $link;
						},
					],
					'sort' => [
						'default' => 'a.filename',
						'reverse' => 'a.filename DESC',
					],
				],
				'filesize' => [
					'header' => [
						'value' => Lang::$txt['attachment_file_size'],
					],
					'data' => [
						'function' => function ($rowData) {
							return sprintf('%1$s%2$s', round($rowData['size'] / 1024, 2), Lang::$txt['kilobyte']);
						},
					],
					'sort' => [
						'default' => 'a.size',
						'reverse' => 'a.size DESC',
					],
				],
				'member' => [
					'header' => [
						'value' => Utils::$context['browse_type'] == 'avatars' ? Lang::$txt['attachment_manager_member'] : Lang::$txt['posted_by'],
					],
					'data' => [
						'function' => function ($rowData) {
							// In case of an attachment, return the poster of the attachment.
							if (empty($rowData['id_member'])) {
								return Utils::htmlspecialchars($rowData['poster_name']);
							}

							// Otherwise it must be an avatar, return the link to the owner of it.
							return sprintf('<a href="%1$s?action=profile;u=%2$d">%3$s</a>', Config::$scripturl, $rowData['id_member'], $rowData['poster_name']);
						},
					],
					'sort' => [
						'default' => 'mem.real_name',
						'reverse' => 'mem.real_name DESC',
					],
				],
				'date' => [
					'header' => [
						'value' => Utils::$context['browse_type'] == 'avatars' ? Lang::$txt['attachment_manager_last_active'] : Lang::$txt['date'],
					],
					'data' => [
						'function' => function ($rowData) {
							// The date the message containing the attachment was posted or the owner of the avatar was active.
							$date = empty($rowData['poster_time']) ? Lang::$txt['never'] : Time::create('@' . $rowData['poster_time'])->format(null, true);

							// Add a link to the topic in case of an attachment.
							if (Utils::$context['browse_type'] !== 'avatars') {
								$date .= sprintf('<br>%1$s <a href="%2$s?topic=%3$d.msg%4$d#msg%4$d">%5$s</a>', Lang::$txt['in'], Config::$scripturl, $rowData['id_topic'], $rowData['id_msg'], $rowData['subject']);
							}

							return $date;
						},
					],
					'sort' => [
						'default' => Utils::$context['browse_type'] === 'avatars' ? 'mem.last_login' : 'm.id_msg',
						'reverse' => Utils::$context['browse_type'] === 'avatars' ? 'mem.last_login DESC' : 'm.id_msg DESC',
					],
				],
				'downloads' => [
					'header' => [
						'value' => Lang::$txt['downloads'],
					],
					'data' => [
						'db' => 'downloads',
						'comma_format' => true,
					],
					'sort' => [
						'default' => 'a.downloads',
						'reverse' => 'a.downloads DESC',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
						'class' => 'centercol',
					],
					'data' => [
						'sprintf' => [
							'format' => '<input type="checkbox" name="remove[%1$d]">',
							'params' => [
								'id_attach' => false,
							],
						],
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=manageattachments;sa=remove' . (Utils::$context['browse_type'] === 'avatars' ? ';avatars' : (Utils::$context['browse_type'] === 'thumbs' ? ';thumbs' : '')),
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					'type' => Utils::$context['browse_type'],
				],
			],
			'additional_rows' => [
				[
					'position' => 'above_column_headers',
					'value' => '<input type="submit" name="remove_submit" class="button you_sure" value="' . Lang::$txt['quickmod_delete_selected'] . '" data-confirm="' . Lang::$txt['confirm_delete_attachments'] . '">',
				],
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="remove_submit" class="button you_sure" value="' . Lang::$txt['quickmod_delete_selected'] . '" data-confirm="' . Lang::$txt['confirm_delete_attachments'] . '">',
				],
			],
		];

		$titles = [
			'attachments' => ['?action=admin;area=manageattachments;sa=browse', Lang::$txt['attachment_manager_attachments']],
			'avatars' => ['?action=admin;area=manageattachments;sa=browse;avatars', Lang::$txt['attachment_manager_avatars']],
			'thumbs' => ['?action=admin;area=manageattachments;sa=browse;thumbs', Lang::$txt['attachment_manager_thumbs']],
		];

		$list_title = Lang::$txt['attachment_manager_browse_files'] . ': ';

		// Does a hook want to display their attachments better?
		IntegrationHook::call('integrate_attachments_browse', [&$listOptions, &$titles]);

		foreach ($titles as $browse_type => $details) {
			if ($browse_type != 'attachments') {
				$list_title .= ' | ';
			}

			if (Utils::$context['browse_type'] == $browse_type) {
				$list_title .= '<img src="' . Theme::$current->settings['images_url'] . '/selected.png" alt="&gt;"> ';
			}

			$list_title .= '<a href="' . Config::$scripturl . $details[0] . '">' . $details[1] . '</a>';
		}

		$listOptions['title'] = $list_title;

		// Create the list.
		new ItemList($listOptions);

		Utils::$context['sub_template'] = 'show_list';
		Utils::$context['default_list'] = 'file_list';
	}

	/**
	 * Show several file maintenance options.
	 * Called by ?action=admin;area=manageattachments;sa=maintain.
	 * Calculates file statistics (total file size, number of attachments,
	 * number of avatars, attachment space available).
	 *
	 * @uses template_maintenance()
	 */
	public function maintain(): void
	{
		Utils::$context['sub_template'] = 'maintenance';

		$attach_dirs = Config::$modSettings['attachmentUploadDir'];

		// Get the number of attachments....
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}attachments
			WHERE attachment_type = {int:attachment_type}
				AND id_member = {int:guest_id_member}',
			[
				'attachment_type' => 0,
				'guest_id_member' => 0,
			],
		);
		list(Utils::$context['num_attachments']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		Utils::$context['num_attachments'] = Lang::numberFormat(Utils::$context['num_attachments'], 0);

		// Also get the avatar amount....
		$request = Db::$db->query(
			'',
			'SELECT COUNT(*)
			FROM {db_prefix}attachments
			WHERE id_member != {int:guest_id_member}',
			[
				'guest_id_member' => 0,
			],
		);
		list(Utils::$context['num_avatars']) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		Utils::$context['num_avatars'] = Lang::numberFormat(Utils::$context['num_avatars'], 0);

		// Check the size of all the directories.
		$request = Db::$db->query(
			'',
			'SELECT SUM(size)
			FROM {db_prefix}attachments
			WHERE attachment_type != {int:type}',
			[
				'type' => 1,
			],
		);
		list($attachmentDirSize) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		// Divide it into kilobytes.
		$attachmentDirSize /= 1024;
		Utils::$context['attachment_total_size'] = Lang::numberFormat($attachmentDirSize, 2);

		$request = Db::$db->query(
			'',
			'SELECT COUNT(*), SUM(size)
			FROM {db_prefix}attachments
			WHERE id_folder = {int:folder_id}
				AND attachment_type != {int:type}',
			[
				'folder_id' => Config::$modSettings['currentAttachmentUploadDir'],
				'type' => 1,
			],
		);
		list($current_dir_files, $current_dir_size) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);
		$current_dir_size /= 1024;

		// If they specified a limit only....
		if (!empty(Config::$modSettings['attachmentDirSizeLimit'])) {
			Utils::$context['attachment_space'] = Lang::numberFormat(max(Config::$modSettings['attachmentDirSizeLimit'] - $current_dir_size, 0), 2);
		}

		Utils::$context['attachment_current_size'] = Lang::numberFormat($current_dir_size, 2);

		if (!empty(Config::$modSettings['attachmentDirFileLimit'])) {
			Utils::$context['attachment_files'] = Lang::numberFormat(max(Config::$modSettings['attachmentDirFileLimit'] - $current_dir_files, 0), 0);
		}

		Utils::$context['attachment_current_files'] = Lang::numberFormat($current_dir_files, 0);

		Utils::$context['attach_multiple_dirs'] = count($attach_dirs) > 1 ? true : false;

		Utils::$context['attach_dirs'] = $attach_dirs;

		Utils::$context['base_dirs'] = !empty(Config::$modSettings['attachment_basedirectories']) ? Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true) : [];

		Utils::$context['checked'] = $_SESSION['checked'] ?? true;

		if (!empty($_SESSION['results'])) {
			Utils::$context['results'] = implode('<br>', $_SESSION['results']);
			unset($_SESSION['results']);
		}
	}

	/**
	 * Remove a selection of attachments or avatars.
	 * Called from the browse screen as submitted form by
	 *  ?action=admin;area=manageattachments;sa=remove
	 */
	public function remove(): void
	{
		User::$me->checkSession();

		if (!empty($_POST['remove'])) {
			$attachments = [];

			// There must be a quicker way to pass this safety test??
			foreach ($_POST['remove'] as $removeID => $dummy) {
				$attachments[] = (int) $removeID;
			}

			// If the attachments are from a 3rd party, let them remove it. Hooks should remove their ids from the array.
			$filesRemoved = false;

			IntegrationHook::call('integrate_attachment_remove', [&$filesRemoved, $attachments]);

			if ($_REQUEST['type'] == 'avatars' && !empty($attachments)) {
				Attachment::remove(['id_attach' => $attachments]);
			} elseif (!empty($attachments)) {
				$messages = Attachment::remove(['id_attach' => $attachments], 'messages', true);

				// And change the message to reflect this.
				if (!empty($messages)) {
					Lang::load('index', Lang::$default, true);

					Db::$db->query(
						'',
						'UPDATE {db_prefix}messages
						SET body = CONCAT(body, {string:deleted_message})
						WHERE id_msg IN ({array_int:messages_affected})',
						[
							'messages_affected' => $messages,
							'deleted_message' => '<br><br>' . Lang::$txt['attachment_delete_admin'],
						],
					);

					Lang::load('index', User::$me->language, true);
				}
			}
		}

		$_GET['sort'] = $_GET['sort'] ?? 'date';

		Utils::redirectexit('action=admin;area=manageattachments;sa=browse;' . $_REQUEST['type'] . ';sort=' . $_GET['sort'] . (isset($_GET['desc']) ? ';desc' : '') . ';start=' . $_REQUEST['start']);
	}

	/**
	 * Remove attachments older than a given age.
	 * Called from the maintenance screen by
	 *   ?action=admin;area=manageattachments;sa=byage.
	 * It optionally adds a certain text to the messages the attachments
	 *  were removed from.
	 */
	public function removeByAge(): void
	{
		User::$me->checkSession('post', 'admin');

		// @todo Ignore messages in topics that are stickied?

		// Deleting an attachment?
		if ($_REQUEST['type'] != 'avatars') {
			// Get rid of all the old attachments.
			$messages = Attachment::remove(['attachment_type' => 0, 'poster_time' => (time() - 24 * 60 * 60 * $_POST['age'])], 'messages', true);

			// Update the messages to reflect the change.
			if (!empty($messages) && !empty($_POST['notice'])) {
				Db::$db->query(
					'',
					'UPDATE {db_prefix}messages
					SET body = CONCAT(body, {string:notice})
					WHERE id_msg IN ({array_int:messages})',
					[
						'messages' => $messages,
						'notice' => '<br><br>' . $_POST['notice'],
					],
				);
			}
		} else {
			// Remove all the old avatars.
			Attachment::remove(['not_id_member' => 0, 'last_login' => (time() - 24 * 60 * 60 * $_POST['age'])], 'members');
		}

		Utils::redirectexit('action=admin;area=manageattachments' . (empty($_REQUEST['avatars']) ? ';sa=maintenance' : ';avatars'));
	}

	/**
	 * Remove attachments larger than a given size.
	 * Called from the maintenance screen by
	 *  ?action=admin;area=manageattachments;sa=bysize.
	 * Optionally adds a certain text to the messages the attachments were
	 * 	removed from.
	 */
	public function removeBySize(): void
	{
		User::$me->checkSession('post', 'admin');

		// Find humungous attachments.
		$messages = Attachment::remove(['attachment_type' => 0, 'size' => 1024 * $_POST['size']], 'messages', true);

		// And make a note on the post.
		if (!empty($messages) && !empty($_POST['notice'])) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET body = CONCAT(body, {string:notice})
				WHERE id_msg IN ({array_int:messages})',
				[
					'messages' => $messages,
					'notice' => '<br><br>' . $_POST['notice'],
				],
			);
		}

		Utils::redirectexit('action=admin;area=manageattachments;sa=maintenance');
	}

	/**
	 * Removes all attachments in a single click
	 * Called from the maintenance screen by
	 *  ?action=admin;area=manageattachments;sa=removeall.
	 */
	public function removeAll(): void
	{
		User::$me->checkSession('get', 'admin');

		$messages = Attachment::remove(['attachment_type' => 0], '', true);

		if (!isset($_POST['notice'])) {
			$_POST['notice'] = Lang::$txt['attachment_delete_admin'];
		}

		// Add the notice on the end of the changed messages.
		if (!empty($messages)) {
			Db::$db->query(
				'',
				'UPDATE {db_prefix}messages
				SET body = CONCAT(body, {string:deleted_message})
				WHERE id_msg IN ({array_int:messages})',
				[
					'messages' => $messages,
					'deleted_message' => '<br><br>' . $_POST['notice'],
				],
			);
		}

		Utils::redirectexit('action=admin;area=manageattachments;sa=maintenance');
	}

	/**
	 * This function should find attachments in the database that no longer exist and clear them, and fix filesize issues.
	 */
	public function repair(): void
	{
		User::$me->checkSession('get');

		// If we choose cancel, redirect right back.
		if (isset($_POST['cancel'])) {
			Utils::redirectexit('action=admin;area=manageattachments;sa=maintenance');
		}

		// Try give us a while to sort this out...
		@set_time_limit(600);

		$_GET['step'] = empty($_GET['step']) ? 0 : (int) $_GET['step'];

		Utils::$context['starting_substep'] = $_GET['substep'] = empty($_GET['substep']) ? 0 : (int) $_GET['substep'];

		// Don't recall the session just in case.
		if ($_GET['step'] == 0 && $_GET['substep'] == 0) {
			unset($_SESSION['attachments_to_fix'], $_SESSION['attachments_to_fix2']);

			// If we're actually fixing stuff - work out what.
			if (isset($_GET['fixErrors'])) {
				// Nothing?
				if (empty($_POST['to_fix'])) {
					Utils::redirectexit('action=admin;area=manageattachments;sa=maintenance');
				}

				$_SESSION['attachments_to_fix'] = [];

				// @todo No need to do this I think.
				foreach ($_POST['to_fix'] as $value) {
					$_SESSION['attachments_to_fix'][] = $value;
				}
			}
		}

		// All the valid problems are here:
		Utils::$context['repair_errors'] = [
			'missing_thumbnail_parent' => 0,
			'parent_missing_thumbnail' => 0,
			'file_missing_on_disk' => 0,
			'file_wrong_size' => 0,
			'file_size_of_zero' => 0,
			'attachment_no_msg' => 0,
			'avatar_no_member' => 0,
			'wrong_folder' => 0,
			'files_without_attachment' => 0,
		];

		$to_fix = !empty($_SESSION['attachments_to_fix']) ? $_SESSION['attachments_to_fix'] : [];

		Utils::$context['repair_errors'] = $_SESSION['attachments_to_fix2'] ?? Utils::$context['repair_errors'];

		$fix_errors = isset($_GET['fixErrors']) ? true : false;

		// Get stranded thumbnails.
		if ($_GET['step'] <= 0) {
			$result = Db::$db->query(
				'',
				'SELECT MAX(id_attach)
				FROM {db_prefix}attachments
				WHERE attachment_type = {int:thumbnail}',
				[
					'thumbnail' => 3,
				],
			);
			list($thumbnails) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500) {
				$to_remove = [];

				$result = Db::$db->query(
					'',
					'SELECT thumb.id_attach, thumb.id_folder, thumb.filename, thumb.file_hash
					FROM {db_prefix}attachments AS thumb
						LEFT JOIN {db_prefix}attachments AS tparent ON (tparent.id_thumb = thumb.id_attach)
					WHERE thumb.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND thumb.attachment_type = {int:thumbnail}
						AND tparent.id_attach IS NULL',
					[
						'thumbnail' => 3,
						'substep' => $_GET['substep'],
					],
				);

				while ($row = Db::$db->fetch_assoc($result)) {
					// Only do anything once... just in case
					if (!isset($to_remove[$row['id_attach']])) {
						$to_remove[$row['id_attach']] = $row['id_attach'];
						Utils::$context['repair_errors']['missing_thumbnail_parent']++;

						// If we are repairing remove the file from disk now.
						if ($fix_errors && in_array('missing_thumbnail_parent', $to_fix)) {
							$filename = Attachment::getFilePath($row['id_attach']);
							@unlink($filename);
						}
					}
				}

				if (Db::$db->num_rows($result) != 0) {
					$to_fix[] = 'missing_thumbnail_parent';
				}
				Db::$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove) && in_array('missing_thumbnail_parent', $to_fix)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}attachments
						WHERE id_attach IN ({array_int:to_remove})
							AND attachment_type = {int:attachment_type}',
						[
							'to_remove' => $to_remove,
							'attachment_type' => 3,
						],
					);
				}

				$this->pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 1;
			$_GET['substep'] = 0;
			$this->pauseAttachmentMaintenance($to_fix);
		}

		// Find parents which think they have thumbnails, but actually, don't.
		if ($_GET['step'] <= 1) {
			$result = Db::$db->query(
				'',
				'SELECT MAX(id_attach)
				FROM {db_prefix}attachments
				WHERE id_thumb != {int:no_thumb}',
				[
					'no_thumb' => 0,
				],
			);
			list($thumbnails) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500) {
				$to_update = [];

				$result = Db::$db->query(
					'',
					'SELECT a.id_attach
					FROM {db_prefix}attachments AS a
						LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
					WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND a.id_thumb != {int:no_thumb}
						AND thumb.id_attach IS NULL',
					[
						'no_thumb' => 0,
						'substep' => $_GET['substep'],
					],
				);

				while ($row = Db::$db->fetch_assoc($result)) {
					$to_update[] = $row['id_attach'];
					Utils::$context['repair_errors']['parent_missing_thumbnail']++;
				}

				if (Db::$db->num_rows($result) != 0) {
					$to_fix[] = 'parent_missing_thumbnail';
				}
				Db::$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_update) && in_array('parent_missing_thumbnail', $to_fix)) {
					Db::$db->query(
						'',
						'UPDATE {db_prefix}attachments
						SET id_thumb = {int:no_thumb}
						WHERE id_attach IN ({array_int:to_update})',
						[
							'to_update' => $to_update,
							'no_thumb' => 0,
						],
					);
				}

				$this->pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 2;
			$_GET['substep'] = 0;
			$this->pauseAttachmentMaintenance($to_fix);
		}

		// This may take forever I'm afraid, but life sucks... recount EVERY attachments!
		if ($_GET['step'] <= 2) {
			$result = Db::$db->query(
				'',
				'SELECT MAX(id_attach)
				FROM {db_prefix}attachments',
				[
				],
			);
			list($thumbnails) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 250) {
				$to_remove = [];
				$errors_found = [];

				$result = Db::$db->query(
					'',
					'SELECT id_attach, id_folder, filename, file_hash, size, attachment_type
					FROM {db_prefix}attachments
					WHERE id_attach BETWEEN {int:substep} AND {int:substep} + 249',
					[
						'substep' => $_GET['substep'],
					],
				);

				while ($row = Db::$db->fetch_assoc($result)) {
					// Get the filename.
					if ($row['attachment_type'] == 1) {
						$filename = Config::$modSettings['custom_avatar_dir'] . '/' . $row['filename'];
					} else {
						$filename = Attachment::getFilePath($row['id_attach']);
					}

					// File doesn't exist?
					if (!file_exists($filename)) {
						// If we're lucky it might just be in a different folder.
						if (!empty(Config::$modSettings['currentAttachmentUploadDir'])) {
							// Get the attachment name with out the folder.
							$attachment_name = $row['id_attach'] . '_' . $row['file_hash'] . '.dat';

							// Loop through the other folders.
							foreach (Config::$modSettings['attachmentUploadDir'] as $id => $dir) {
								if (file_exists($dir . '/' . $attachment_name)) {
									Utils::$context['repair_errors']['wrong_folder']++;
									$errors_found[] = 'wrong_folder';

									// Are we going to fix this now?
									if ($fix_errors && in_array('wrong_folder', $to_fix)) {
										Db::$db->query(
											'',
											'UPDATE {db_prefix}attachments
											SET id_folder = {int:new_folder}
											WHERE id_attach = {int:id_attach}',
											[
												'new_folder' => $id,
												'id_attach' => $row['id_attach'],
											],
										);
									}

									continue 2;
								}
							}
						}

						$to_remove[] = $row['id_attach'];
						Utils::$context['repair_errors']['file_missing_on_disk']++;
						$errors_found[] = 'file_missing_on_disk';
					} elseif (filesize($filename) == 0) {
						Utils::$context['repair_errors']['file_size_of_zero']++;
						$errors_found[] = 'file_size_of_zero';

						// Fixing?
						if ($fix_errors && in_array('file_size_of_zero', $to_fix)) {
							$to_remove[] = $row['id_attach'];
							@unlink($filename);
						}
					} elseif (filesize($filename) != $row['size']) {
						Utils::$context['repair_errors']['file_wrong_size']++;
						$errors_found[] = 'file_wrong_size';

						// Fix it here?
						if ($fix_errors && in_array('file_wrong_size', $to_fix)) {
							Db::$db->query(
								'',
								'UPDATE {db_prefix}attachments
								SET size = {int:filesize}
								WHERE id_attach = {int:id_attach}',
								[
									'filesize' => filesize($filename),
									'id_attach' => $row['id_attach'],
								],
							);
						}
					}
				}

				if (in_array('file_missing_on_disk', $errors_found)) {
					$to_fix[] = 'file_missing_on_disk';
				}

				if (in_array('file_size_of_zero', $errors_found)) {
					$to_fix[] = 'file_size_of_zero';
				}

				if (in_array('file_wrong_size', $errors_found)) {
					$to_fix[] = 'file_wrong_size';
				}

				if (in_array('wrong_folder', $errors_found)) {
					$to_fix[] = 'wrong_folder';
				}

				Db::$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}attachments
						WHERE id_attach IN ({array_int:to_remove})',
						[
							'to_remove' => $to_remove,
						],
					);

					Db::$db->query(
						'',
						'UPDATE {db_prefix}attachments
						SET id_thumb = {int:no_thumb}
						WHERE id_thumb IN ({array_int:to_remove})',
						[
							'to_remove' => $to_remove,
							'no_thumb' => 0,
						],
					);
				}

				$this->pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 3;
			$_GET['substep'] = 0;
			$this->pauseAttachmentMaintenance($to_fix);
		}

		// Get avatars with no members associated with them.
		if ($_GET['step'] <= 3) {
			$result = Db::$db->query(
				'',
				'SELECT MAX(id_attach)
				FROM {db_prefix}attachments',
				[
				],
			);
			list($thumbnails) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500) {
				$to_remove = [];

				$result = Db::$db->query(
					'',
					'SELECT a.id_attach, a.id_folder, a.filename, a.file_hash, a.attachment_type
					FROM {db_prefix}attachments AS a
						LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)
					WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND a.id_member != {int:no_member}
						AND a.id_msg = {int:no_msg}
						AND mem.id_member IS NULL',
					[
						'no_member' => 0,
						'no_msg' => 0,
						'substep' => $_GET['substep'],
					],
				);

				while ($row = Db::$db->fetch_assoc($result)) {
					$to_remove[] = $row['id_attach'];
					Utils::$context['repair_errors']['avatar_no_member']++;

					// If we are repairing remove the file from disk now.
					if ($fix_errors && in_array('avatar_no_member', $to_fix)) {
						if ($row['attachment_type'] == 1) {
							$filename = Config::$modSettings['custom_avatar_dir'] . '/' . $row['filename'];
						} else {
							$filename = Attachment::getFilePath($row['id_attach']);
						}

						@unlink($filename);
					}
				}

				if (Db::$db->num_rows($result) != 0) {
					$to_fix[] = 'avatar_no_member';
				}
				Db::$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove) && in_array('avatar_no_member', $to_fix)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}attachments
						WHERE id_attach IN ({array_int:to_remove})
							AND id_member != {int:no_member}
							AND id_msg = {int:no_msg}',
						[
							'to_remove' => $to_remove,
							'no_member' => 0,
							'no_msg' => 0,
						],
					);
				}

				$this->pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 4;
			$_GET['substep'] = 0;
			$this->pauseAttachmentMaintenance($to_fix);
		}

		// What about attachments, who are missing a message :'(
		if ($_GET['step'] <= 4) {
			$result = Db::$db->query(
				'',
				'SELECT MAX(id_attach)
				FROM {db_prefix}attachments',
				[
				],
			);
			list($thumbnails) = Db::$db->fetch_row($result);
			Db::$db->free_result($result);

			for (; $_GET['substep'] < $thumbnails; $_GET['substep'] += 500) {
				$to_remove = [];
				$ignore_ids = [0];

				// returns an array of ints of id_attach's that should not be deleted
				IntegrationHook::call('integrate_repair_attachments_nomsg', [&$ignore_ids, $_GET['substep'], $_GET['substep'] + 500]);

				$result = Db::$db->query(
					'',
					'SELECT a.id_attach, a.id_folder, a.filename, a.file_hash
					FROM {db_prefix}attachments AS a
						LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
					WHERE a.id_attach BETWEEN {int:substep} AND {int:substep} + 499
						AND a.id_member = {int:no_member}
						AND (a.id_msg = {int:no_msg} OR m.id_msg IS NULL)
						AND a.id_attach NOT IN ({array_int:ignore_ids})
						AND a.attachment_type IN ({array_int:attach_thumb})',
					[
						'no_member' => 0,
						'no_msg' => 0,
						'substep' => $_GET['substep'],
						'ignore_ids' => $ignore_ids,
						'attach_thumb' => [0, 3],
					],
				);

				while ($row = Db::$db->fetch_assoc($result)) {
					$to_remove[] = $row['id_attach'];
					Utils::$context['repair_errors']['attachment_no_msg']++;

					// If we are repairing remove the file from disk now.
					if ($fix_errors && in_array('attachment_no_msg', $to_fix)) {
						$filename = Attachment::getFilePath($row['id_attach']);
						@unlink($filename);
					}
				}

				if (Db::$db->num_rows($result) != 0) {
					$to_fix[] = 'attachment_no_msg';
				}
				Db::$db->free_result($result);

				// Do we need to delete what we have?
				if ($fix_errors && !empty($to_remove) && in_array('attachment_no_msg', $to_fix)) {
					Db::$db->query(
						'',
						'DELETE FROM {db_prefix}attachments
						WHERE id_attach IN ({array_int:to_remove})
							AND id_member = {int:no_member}
							AND attachment_type IN ({array_int:attach_thumb})',
						[
							'to_remove' => $to_remove,
							'no_member' => 0,
							'attach_thumb' => [0, 3],
						],
					);
				}

				$this->pauseAttachmentMaintenance($to_fix, $thumbnails);
			}

			$_GET['step'] = 5;
			$_GET['substep'] = 0;
			$this->pauseAttachmentMaintenance($to_fix);
		}

		// What about files who are not recorded in the database?
		if ($_GET['step'] <= 5) {
			$attach_dirs = Config::$modSettings['attachmentUploadDir'];

			$current_check = 0;
			$max_checks = 500;
			$files_checked = empty($_GET['substep']) ? 0 : $_GET['substep'];

			foreach ($attach_dirs as $attach_dir) {
				if ($dir = @opendir($attach_dir)) {
					while ($file = readdir($dir)) {
						if (in_array($file, ['.', '..', '.htaccess', 'index.php'])) {
							continue;
						}

						if ($files_checked <= $current_check) {
							// Temporary file, get rid of it!
							if (strpos($file, 'post_tmp_') !== false) {
								// Temp file is more than 5 hours old!
								if (filemtime($attach_dir . '/' . $file) < time() - 18000) {
									@unlink($attach_dir . '/' . $file);
								}
							}
							// That should be an attachment, let's check if we have it in the database
							elseif (strpos($file, '_') !== false) {
								$attachID = (int) substr($file, 0, strpos($file, '_'));

								if (!empty($attachID)) {
									$request = Db::$db->query(
										'',
										'SELECT  id_attach
										FROM {db_prefix}attachments
										WHERE id_attach = {int:attachment_id}
										LIMIT 1',
										[
											'attachment_id' => $attachID,
										],
									);

									if (Db::$db->num_rows($request) == 0) {
										if ($fix_errors && in_array('files_without_attachment', $to_fix)) {
											@unlink($attach_dir . '/' . $file);
										} else {
											Utils::$context['repair_errors']['files_without_attachment']++;
											$to_fix[] = 'files_without_attachment';
										}
									}
									Db::$db->free_result($request);
								}
							} else {
								if ($fix_errors && in_array('files_without_attachment', $to_fix)) {
									@unlink($attach_dir . '/' . $file);
								} else {
									Utils::$context['repair_errors']['files_without_attachment']++;
									$to_fix[] = 'files_without_attachment';
								}
							}
						}
						$current_check++;
						$_GET['substep'] = $current_check;

						if ($current_check - $files_checked >= $max_checks) {
							$this->pauseAttachmentMaintenance($to_fix);
						}
					}

					closedir($dir);
				}
			}

			$_GET['step'] = 5;
			$_GET['substep'] = 0;
			$this->pauseAttachmentMaintenance($to_fix);
		}

		// Got here we must be doing well - just the template! :D
		Utils::$context['page_title'] = Lang::$txt['repair_attachments'];
		Menu::$loaded['admin']['current_subsection'] = 'maintenance';
		Utils::$context['sub_template'] = 'attachment_repair';

		// What stage are we at?
		Utils::$context['completed'] = $fix_errors ? true : false;
		Utils::$context['errors_found'] = !empty($to_fix) ? true : false;
	}

	/**
	 * This function lists and allows updating of multiple attachments paths.
	 */
	public function paths(): void
	{
		// Since this needs to be done eventually.
		if (!isset(Config::$modSettings['attachment_basedirectories'])) {
			Config::$modSettings['attachment_basedirectories'] = [];
		} elseif (!is_array(Config::$modSettings['attachment_basedirectories'])) {
			Config::$modSettings['attachment_basedirectories'] = Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true);
		}

		$errors = [];

		// Saving?
		if (isset($_REQUEST['save'])) {
			User::$me->checkSession();

			$_POST['current_dir'] = (int) $_POST['current_dir'];

			$new_dirs = [];

			foreach ($_POST['dirs'] as $id => $path) {
				$error = '';
				$id = (int) $id;

				if ($id < 1) {
					continue;
				}

				// Sorry, these dirs are NOT valid
				$invalid_dirs = [Config::$boarddir, Theme::$current->settings['default_theme_dir'], Config::$sourcedir];

				if (in_array($path, $invalid_dirs)) {
					$errors[] = $path . ': ' . Lang::$txt['attach_dir_invalid'];

					continue;
				}

				// Hmm, a new path maybe?
				// Don't allow empty paths
				if (!array_key_exists($id, Config::$modSettings['attachmentUploadDir']) && !empty($path)) {
					// or is it?
					if (in_array($path, Config::$modSettings['attachmentUploadDir']) || in_array(Config::$boarddir . DIRECTORY_SEPARATOR . $path, Config::$modSettings['attachmentUploadDir'])) {
						$errors[] = $path . ': ' . Lang::$txt['attach_dir_duplicate_msg'];

						continue;
					}

					if (empty($path)) {
						// Ignore this and set $id to one less
						continue;
					}

					// OK, so let's try to create it then.
					if (Attachment::automanageCreateDirectory($path)) {
						$_POST['current_dir'] = Config::$modSettings['currentAttachmentUploadDir'];
					} else {
						$errors[] = $path . ': ' . Lang::$txt[Utils::$context['dir_creation_error']];
					}
				}

				// Changing a directory name?
				if (!empty(Config::$modSettings['attachmentUploadDir'][$id]) && !empty($path) && $path != Config::$modSettings['attachmentUploadDir'][$id]) {
					if ($path != Config::$modSettings['attachmentUploadDir'][$id] && !is_dir($path)) {
						if (!@rename(Config::$modSettings['attachmentUploadDir'][$id], $path)) {
							$errors[] = $path . ': ' . Lang::$txt['attach_dir_no_rename'];
							$path = Config::$modSettings['attachmentUploadDir'][$id];
						}
					} else {
						$errors[] = $path . ': ' . Lang::$txt['attach_dir_exists_msg'];
						$path = Config::$modSettings['attachmentUploadDir'][$id];
					}

					// Update the base directory path
					if (!empty(Config::$modSettings['attachment_basedirectories']) && array_key_exists($id, Config::$modSettings['attachment_basedirectories'])) {
						$base = Config::$modSettings['basedirectory_for_attachments'] == Config::$modSettings['attachmentUploadDir'][$id] ? $path : Config::$modSettings['basedirectory_for_attachments'];

						Config::$modSettings['attachment_basedirectories'][$id] = $path;

						Config::updateModSettings([
							'attachment_basedirectories' => Utils::jsonEncode(Config::$modSettings['attachment_basedirectories']),
							'basedirectory_for_attachments' => $base,
						]);

						Config::$modSettings['attachment_basedirectories'] = Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true);
					}
				}

				if (empty($path)) {
					$path = Config::$modSettings['attachmentUploadDir'][$id];

					// It's not a good idea to delete the current directory.
					if ($id == (!empty($_POST['current_dir']) ? $_POST['current_dir'] : Config::$modSettings['currentAttachmentUploadDir'])) {
						$errors[] = $path . ': ' . Lang::$txt['attach_dir_is_current'];
					}
					// Or the current base directory
					elseif (!empty(Config::$modSettings['basedirectory_for_attachments']) && Config::$modSettings['basedirectory_for_attachments'] == Config::$modSettings['attachmentUploadDir'][$id]) {
						$errors[] = $path . ': ' . Lang::$txt['attach_dir_is_current_bd'];
					} else {
						// Let's not try to delete a path with files in it.
						$request = Db::$db->query(
							'',
							'SELECT COUNT(id_attach) AS num_attach
							FROM {db_prefix}attachments
							WHERE id_folder = {int:id_folder}',
							[
								'id_folder' => (int) $id,
							],
						);
						list($num_attach) = Db::$db->fetch_row($request);
						Db::$db->free_result($request);

						// A check to see if it's a used base dir.
						if (!empty(Config::$modSettings['attachment_basedirectories'])) {
							// Count any sub-folders.
							foreach (Config::$modSettings['attachmentUploadDir'] as $sub) {
								if (strpos($sub, $path . DIRECTORY_SEPARATOR) !== false) {
									$num_attach++;
								}
							}
						}

						// It's safe to delete. So try to delete the folder also
						if ($num_attach == 0) {
							if (is_dir($path)) {
								$doit = true;
							} elseif (is_dir(Config::$boarddir . DIRECTORY_SEPARATOR . $path)) {
								$doit = true;
								$path = Config::$boarddir . DIRECTORY_SEPARATOR . $path;
							}

							if (isset($doit) && realpath($path) != realpath(Config::$boarddir)) {
								unlink($path . '/.htaccess');
								unlink($path . '/index.php');

								if (!@rmdir($path)) {
									$error = $path . ': ' . Lang::$txt['attach_dir_no_delete'];
								}
							}

							// Remove it from the base directory list.
							if (empty($error) && !empty(Config::$modSettings['attachment_basedirectories'])) {
								unset(Config::$modSettings['attachment_basedirectories'][$id]);

								Config::updateModSettings(['attachment_basedirectories' => Utils::jsonEncode(Config::$modSettings['attachment_basedirectories'])]);

								Config::$modSettings['attachment_basedirectories'] = Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true);
							}
						} else {
							$error = $path . ': ' . Lang::$txt['attach_dir_no_remove'];
						}

						if (empty($error)) {
							continue;
						}

						$errors[] = $error;
					}
				}

				$new_dirs[$id] = $path;
			}

			// We need to make sure the current directory is right.
			if (empty($_POST['current_dir']) && !empty(Config::$modSettings['currentAttachmentUploadDir'])) {
				$_POST['current_dir'] = Config::$modSettings['currentAttachmentUploadDir'];
			}

			// Find the current directory if there's no value carried,
			if (empty($_POST['current_dir']) || empty($new_dirs[$_POST['current_dir']])) {
				if (array_key_exists(Config::$modSettings['currentAttachmentUploadDir'], Config::$modSettings['attachmentUploadDir'])) {
					$_POST['current_dir'] = Config::$modSettings['currentAttachmentUploadDir'];
				} else {
					$_POST['current_dir'] = max(array_keys(Config::$modSettings['attachmentUploadDir']));
				}
			}

			// If the user wishes to go back, update the last_dir array
			if (
				$_POST['current_dir'] != Config::$modSettings['currentAttachmentUploadDir']
				&& !empty(Config::$modSettings['last_attachments_directory'])
				&& (
					isset(Config::$modSettings['last_attachments_directory'][$_POST['current_dir']])
					|| isset(Config::$modSettings['last_attachments_directory'][0])
				)
			) {
				if (!is_array(Config::$modSettings['last_attachments_directory'])) {
					Config::$modSettings['last_attachments_directory'] = Utils::jsonDecode(Config::$modSettings['last_attachments_directory'], true);
				}

				$num = substr(strrchr(Config::$modSettings['attachmentUploadDir'][$_POST['current_dir']], '_'), 1);

				if (is_numeric($num)) {
					// Need to find the base folder.
					$bid = -1;
					$use_subdirectories_for_attachments = 0;

					if (!empty(Config::$modSettings['attachment_basedirectories'])) {
						foreach (Config::$modSettings['attachment_basedirectories'] as $bid => $base) {
							if (strpos(Config::$modSettings['attachmentUploadDir'][$_POST['current_dir']], $base . DIRECTORY_SEPARATOR) !== false) {
								$use_subdirectories_for_attachments = 1;
								break;
							}
						}
					}

					if ($use_subdirectories_for_attachments == 0 && strpos(Config::$modSettings['attachmentUploadDir'][$_POST['current_dir']], Config::$boarddir . DIRECTORY_SEPARATOR) !== false) {
						$bid = 0;
					}

					Config::$modSettings['last_attachments_directory'][$bid] = (int) $num;

					Config::$modSettings['basedirectory_for_attachments'] = !empty(Config::$modSettings['basedirectory_for_attachments']) ? Config::$modSettings['basedirectory_for_attachments'] : '';

					Config::$modSettings['use_subdirectories_for_attachments'] = !empty(Config::$modSettings['use_subdirectories_for_attachments']) ? Config::$modSettings['use_subdirectories_for_attachments'] : 0;

					Config::updateModSettings([
						'last_attachments_directory' => Utils::jsonEncode(Config::$modSettings['last_attachments_directory']),
						'basedirectory_for_attachments' => $bid == 0 ? Config::$modSettings['basedirectory_for_attachments'] : Config::$modSettings['attachment_basedirectories'][$bid],
						'use_subdirectories_for_attachments' => $use_subdirectories_for_attachments,
					]);
				}
			}

			// Going back to just one path?
			if (count($new_dirs) == 1) {
				// We might need to reset the paths. This loop will just loop through once.
				foreach ($new_dirs as $id => $dir) {
					if ($id != 1) {
						Db::$db->query(
							'',
							'UPDATE {db_prefix}attachments
							SET id_folder = {int:default_folder}
							WHERE id_folder = {int:current_folder}',
							[
								'default_folder' => 1,
								'current_folder' => $id,
							],
						);
					}

					$update = [
						'currentAttachmentUploadDir' => 1,
						'attachmentUploadDir' => Utils::jsonEncode([1 => $dir]),
					];
				}
			} else {
				// Save it to the database.
				$update = [
					'currentAttachmentUploadDir' => $_POST['current_dir'],
					'attachmentUploadDir' => Utils::jsonEncode($new_dirs),
				];
			}

			if (!empty($update)) {
				Config::updateModSettings($update);
			}

			if (!empty($errors)) {
				$_SESSION['errors']['dir'] = $errors;
			}

			Utils::redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		// Saving a base directory?
		if (isset($_REQUEST['save2'])) {
			User::$me->checkSession();

			// Changing the current base directory?
			$_POST['current_base_dir'] = isset($_POST['current_base_dir']) ? (int) $_POST['current_base_dir'] : 1;

			if (empty($_POST['new_base_dir']) && !empty($_POST['current_base_dir'])) {
				if (Config::$modSettings['basedirectory_for_attachments'] != Config::$modSettings['attachmentUploadDir'][$_POST['current_base_dir']]) {
					$update = [
						'basedirectory_for_attachments' => Config::$modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
					];
				}
			}

			if (isset($_POST['base_dir'])) {
				foreach ($_POST['base_dir'] as $id => $dir) {
					if (!empty($dir) && $dir != Config::$modSettings['attachmentUploadDir'][$id]) {
						if (@rename(Config::$modSettings['attachmentUploadDir'][$id], $dir)) {
							Config::$modSettings['attachmentUploadDir'][$id] = $dir;
							Config::$modSettings['attachment_basedirectories'][$id] = $dir;

							$update = [
								'attachmentUploadDir' => Utils::jsonEncode(Config::$modSettings['attachmentUploadDir']),
								'attachment_basedirectories' => Utils::jsonEncode(Config::$modSettings['attachment_basedirectories']),
								'basedirectory_for_attachments' => Config::$modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
							];
						}
					}

					if (empty($dir)) {
						if ($id == $_POST['current_base_dir']) {
							$errors[] = Config::$modSettings['attachmentUploadDir'][$id] . ': ' . Lang::$txt['attach_dir_is_current'];

							continue;
						}

						unset(Config::$modSettings['attachment_basedirectories'][$id]);

						$update = [
							'attachment_basedirectories' => Utils::jsonEncode(Config::$modSettings['attachment_basedirectories']),
							'basedirectory_for_attachments' => Config::$modSettings['attachmentUploadDir'][$_POST['current_base_dir']],
						];
					}
				}
			}

			// Or adding a new one?
			if (!empty($_POST['new_base_dir'])) {
				$_POST['new_base_dir'] = Utils::htmlspecialchars($_POST['new_base_dir'], ENT_QUOTES);

				$current_dir = Config::$modSettings['currentAttachmentUploadDir'];

				if (!in_array($_POST['new_base_dir'], Config::$modSettings['attachmentUploadDir'])) {
					if (!Attachment::automanageCreateDirectory($_POST['new_base_dir'])) {
						$errors[] = $_POST['new_base_dir'] . ': ' . Lang::$txt['attach_dir_base_no_create'];
					}
				}

				Config::$modSettings['currentAttachmentUploadDir'] = array_search($_POST['new_base_dir'], Config::$modSettings['attachmentUploadDir']);

				if (!in_array($_POST['new_base_dir'], Config::$modSettings['attachment_basedirectories'])) {
					Config::$modSettings['attachment_basedirectories'][Config::$modSettings['currentAttachmentUploadDir']] = $_POST['new_base_dir'];
				}

				ksort(Config::$modSettings['attachment_basedirectories']);

				$update = [
					'attachment_basedirectories' => Utils::jsonEncode(Config::$modSettings['attachment_basedirectories']),
					'basedirectory_for_attachments' => $_POST['new_base_dir'],
					'currentAttachmentUploadDir' => $current_dir,
				];
			}

			if (!empty($errors)) {
				$_SESSION['errors']['base'] = $errors;
			}

			if (!empty($update)) {
				Config::updateModSettings($update);
			}

			Utils::redirectexit('action=admin;area=manageattachments;sa=attachpaths;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id']);
		}

		if (isset($_SESSION['errors'])) {
			if (is_array($_SESSION['errors'])) {
				$errors = [];

				if (!empty($_SESSION['errors']['dir'])) {
					foreach ($_SESSION['errors']['dir'] as $error) {
						$errors['dir'][] = Utils::htmlspecialchars($error, ENT_QUOTES);
					}
				}

				if (!empty($_SESSION['errors']['base'])) {
					foreach ($_SESSION['errors']['base'] as $error) {
						$errors['base'][] = Utils::htmlspecialchars($error, ENT_QUOTES);
					}
				}
			}

			unset($_SESSION['errors']);
		}

		$listOptions = [
			'id' => 'attach_paths',
			'base_href' => Config::$scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			'title' => Lang::$txt['attach_paths'],
			'get_items' => [
				'function' => __CLASS__ . '::list_getAttachDirs',
			],
			'columns' => [
				'current_dir' => [
					'header' => [
						'value' => Lang::$txt['attach_current'],
						'class' => 'centercol',
					],
					'data' => [
						'function' => function ($rowData) {
							return '<input type="radio" name="current_dir" value="' . $rowData['id'] . '"' . ($rowData['current'] ? ' checked' : '') . (!empty($rowData['disable_current']) ? ' disabled' : '') . '>';
						},
						'style' => 'width: 10%;',
						'class' => 'centercol',
					],
				],
				'path' => [
					'header' => [
						'value' => Lang::$txt['attach_path'],
					],
					'data' => [
						'function' => function ($rowData) {
							return '<input type="hidden" name="dirs[' . $rowData['id'] . ']" value="' . $rowData['path'] . '"><input type="text" size="40" name="dirs[' . $rowData['id'] . ']" value="' . $rowData['path'] . '"' . (!empty($rowData['disable_base_dir']) ? ' disabled' : '') . ' style="width: 100%">';
						},
						'style' => 'width: 40%;',
					],
				],
				'current_size' => [
					'header' => [
						'value' => Lang::$txt['attach_current_size'],
					],
					'data' => [
						'db' => 'current_size',
						'style' => 'width: 15%;',
					],
				],
				'num_files' => [
					'header' => [
						'value' => Lang::$txt['attach_num_files'],
					],
					'data' => [
						'db' => 'num_files',
						'style' => 'width: 15%;',
					],
				],
				'status' => [
					'header' => [
						'value' => Lang::$txt['attach_dir_status'],
						'class' => 'centercol',
					],
					'data' => [
						'db' => 'status',
						'style' => 'width: 25%;',
						'class' => 'centercol',
					],
				],
			],
			'form' => [
				'href' => Config::$scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
					<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '">
					<input type="submit" name="save" value="' . Lang::$txt['save'] . '" class="button">
					<input type="submit" name="new_path" value="' . Lang::$txt['attach_add_path'] . '" class="button">',
				],
				empty($errors['dir']) ? [
					'position' => 'top_of_list',
					'value' => Lang::$txt['attach_dir_desc'],
					'class' => 'information',
				] : [
					'position' => 'top_of_list',
					'value' => Lang::$txt['attach_dir_save_problem'] . '<br>' . implode('<br>', $errors['dir']),
					'style' => 'padding-left: 35px;',
					'class' => 'noticebox',
				],
			],
		];
		new ItemList($listOptions);

		if (!empty(Config::$modSettings['attachment_basedirectories'])) {
			$listOptions2 = [
				'id' => 'base_paths',
				'base_href' => Config::$scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				'title' => Lang::$txt['attach_base_paths'],
				'get_items' => [
					'function' => __CLASS__ . '::list_getBaseDirs',
				],
				'columns' => [
					'current_dir' => [
						'header' => [
							'value' => Lang::$txt['attach_current'],
							'class' => 'centercol',
						],
						'data' => [
							'function' => function ($rowData) {
								return '<input type="radio" name="current_base_dir" value="' . $rowData['id'] . '"' . ($rowData['current'] ? ' checked' : '') . '>';
							},
							'style' => 'width: 10%;',
							'class' => 'centercol',
						],
					],
					'path' => [
						'header' => [
							'value' => Lang::$txt['attach_path'],
						],
						'data' => [
							'db' => 'path',
							'style' => 'width: 45%;',
							'class' => 'word_break',
						],
					],
					'num_dirs' => [
						'header' => [
							'value' => Lang::$txt['attach_num_dirs'],
						],
						'data' => [
							'db' => 'num_dirs',
							'style' => 'width: 15%;',
						],
					],
					'status' => [
						'header' => [
							'value' => Lang::$txt['attach_dir_status'],
						],
						'data' => [
							'db' => 'status',
							'style' => 'width: 15%;',
							'class' => 'centercol',
						],
					],
				],
				'form' => [
					'href' => Config::$scripturl . '?action=admin;area=manageattachments;sa=attachpaths;' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'],
				],
				'additional_rows' => [
					[
						'position' => 'below_table_data',
						'value' => '<input type="hidden" name="' . Utils::$context['session_var'] . '" value="' . Utils::$context['session_id'] . '"><input type="submit" name="save2" value="' . Lang::$txt['save'] . '" class="button">
						<input type="submit" name="new_base_path" value="' . Lang::$txt['attach_add_path'] . '" class="button">',
					],
					empty($errors['base']) ? [
						'position' => 'top_of_list',
						'value' => Lang::$txt['attach_dir_base_desc'],
						'style' => 'padding: 5px 10px;',
						'class' => 'windowbg smalltext',
					] : [
						'position' => 'top_of_list',
						'value' => Lang::$txt['attach_dir_save_problem'] . '<br>' . implode('<br>', $errors['base']),
						'style' => 'padding-left: 35px',
						'class' => 'noticebox',
					],
				],
			];
			new ItemList($listOptions2);
		}

		// Fix up our template.
		Menu::$loaded['admin']['current_subsection'] = 'attachpaths';
		Utils::$context['page_title'] = Lang::$txt['attach_path_manage'];
		Utils::$context['sub_template'] = 'attachment_paths';
	}

	/**
	 * Maintance function to move attachments from one directory to another
	 */
	public function transfer(): void
	{
		User::$me->checkSession();

		if (!empty(Config::$modSettings['attachment_basedirectories'])) {
			Config::$modSettings['attachment_basedirectories'] = Utils::jsonDecode(Config::$modSettings['attachment_basedirectories'], true);
		} else {
			Config::$modSettings['basedirectory_for_attachments'] = [];
		}

		$_POST['from'] = (int) $_POST['from'];
		$_POST['auto'] = !empty($_POST['auto']) ? (int) $_POST['auto'] : 0;
		$_POST['to'] = (int) $_POST['to'];
		$start = !empty($_POST['empty_it']) ? 0 : Config::$modSettings['attachmentDirFileLimit'];
		$_SESSION['checked'] = !empty($_POST['empty_it']) ? true : false;
		$limit = 501;
		$results = [];
		$dir_files = 0;
		$current_progress = 0;
		$total_moved = 0;
		$total_not_moved = 0;

		if (empty($_POST['from']) || (empty($_POST['auto']) && empty($_POST['to']))) {
			$results[] = Lang::$txt['attachment_transfer_no_dir'];
		}

		if ($_POST['from'] == $_POST['to']) {
			$results[] = Lang::$txt['attachment_transfer_same_dir'];
		}

		if (empty($results)) {
			// Get the total file count for the progess bar.
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}attachments
				WHERE id_folder = {int:folder_id}
					AND attachment_type != {int:attachment_type}',
				[
					'folder_id' => $_POST['from'],
					'attachment_type' => 1,
				],
			);
			list($total_progress) = Db::$db->fetch_row($request);
			Db::$db->free_result($request);
			$total_progress -= $start;

			if ($total_progress < 1) {
				$results[] = Lang::$txt['attachment_transfer_no_find'];
			}
		}

		if (empty($results)) {
			// Where are they going?
			if (!empty($_POST['auto'])) {
				Config::$modSettings['automanage_attachments'] = 1;
				Config::$modSettings['use_subdirectories_for_attachments'] = $_POST['auto'] == -1 ? 0 : 1;
				Config::$modSettings['basedirectory_for_attachments'] = $_POST['auto'] > 0 ? Config::$modSettings['attachmentUploadDir'][$_POST['auto']] : Config::$modSettings['basedirectory_for_attachments'];

				Attachment::automanageCheckDirectory();
				$new_dir = Config::$modSettings['currentAttachmentUploadDir'];
			} else {
				$new_dir = $_POST['to'];
			}

			Config::$modSettings['currentAttachmentUploadDir'] = $new_dir;

			$break = false;

			while ($break == false) {
				@set_time_limit(300);

				if (function_exists('apache_reset_timeout')) {
					@apache_reset_timeout();
				}

				// If limits are set, get the file count and size for the destination folder
				if (
					$dir_files <= 0
					&& (
						!empty(Config::$modSettings['attachmentDirSizeLimit'])
						|| !empty(Config::$modSettings['attachmentDirFileLimit'])
					)
				) {
					$request = Db::$db->query(
						'',
						'SELECT COUNT(*), SUM(size)
						FROM {db_prefix}attachments
						WHERE id_folder = {int:folder_id}
							AND attachment_type != {int:attachment_type}',
						[
							'folder_id' => $new_dir,
							'attachment_type' => 1,
						],
					);
					list($dir_files, $dir_size) = Db::$db->fetch_row($request);
					Db::$db->free_result($request);
				}

				// Find some attachments to move
				$request = Db::$db->query(
					'',
					'SELECT id_attach, filename, id_folder, file_hash, size
					FROM {db_prefix}attachments
					WHERE id_folder = {int:folder}
						AND attachment_type != {int:attachment_type}
					LIMIT {int:start}, {int:limit}',
					[
						'folder' => $_POST['from'],
						'attachment_type' => 1,
						'start' => $start,
						'limit' => $limit,
					],
				);

				if (Db::$db->num_rows($request) === 0) {
					if (empty($current_progress)) {
						$results[] = Lang::$txt['attachment_transfer_no_find'];
					}
					break;
				}

				if (Db::$db->num_rows($request) < $limit) {
					$break = true;
				}

				// Move them
				$moved = [];

				while ($row = Db::$db->fetch_assoc($request)) {
					$source = Attachment::getFilePath($row['id_attach']);
					$dest = Config::$modSettings['attachmentUploadDir'][$new_dir] . '/' . basename($source);

					// Size and file count check
					if (!empty(Config::$modSettings['attachmentDirSizeLimit']) || !empty(Config::$modSettings['attachmentDirFileLimit'])) {
						$dir_files++;
						$dir_size += !empty($row['size']) ? $row['size'] : filesize($source);

						// If we've reached a limit. Do something.
						if (
							!empty(Config::$modSettings['attachmentDirSizeLimit'])
							&& $dir_size > Config::$modSettings['attachmentDirSizeLimit'] * 1024
							|| (
								!empty(Config::$modSettings['attachmentDirFileLimit'])
								&& $dir_files > Config::$modSettings['attachmentDirFileLimit']
							)
						) {
							if (!empty($_POST['auto'])) {
								// Since we're in auto mode. Create a new folder and reset the counters.
								Attachment::automanageBySpace();

								$results[] = sprintf(Lang::$txt['attachments_transferred'], $total_moved, Config::$modSettings['attachmentUploadDir'][$new_dir]);

								if (!empty($total_not_moved)) {
									$results[] = sprintf(Lang::$txt['attachments_not_transferred'], $total_not_moved);
								}

								$dir_files = 0;
								$total_moved = 0;
								$total_not_moved = 0;

								$break = false;

								break;
							}

							// Hmm, not in auto. Time to bail out then...
							$results[] = Lang::$txt['attachment_transfer_no_room'];
							$break = true;

							break;
						}
					}

					if (@rename($source, $dest)) {
						$total_moved++;
						$current_progress++;
						$moved[] = $row['id_attach'];
					} else {
						$total_not_moved++;
					}
				}
				Db::$db->free_result($request);

				if (!empty($moved)) {
					// Update the database
					Db::$db->query(
						'',
						'UPDATE {db_prefix}attachments
						SET id_folder = {int:new}
						WHERE id_attach IN ({array_int:attachments})',
						[
							'attachments' => $moved,
							'new' => $new_dir,
						],
					);
				}

				$new_dir = Config::$modSettings['currentAttachmentUploadDir'];

				// Create the progress bar.
				if (!$break) {
					$percent_done = min(round($current_progress / $total_progress * 100, 0), 100);

					$prog_bar = '
						<div class="progress_bar">
							<div class="bar" style="width: ' . $percent_done . '%;"></div>
							<span>' . $percent_done . '%</span>
						</div>';

					// Write it to a file so it can be displayed
					$fp = fopen(Config::$boarddir . '/progress.php', 'w');
					fwrite($fp, $prog_bar);
					fclose($fp);
					usleep(500000);
				}
			}

			$results[] = sprintf(Lang::$txt['attachments_transferred'], $total_moved, Config::$modSettings['attachmentUploadDir'][$new_dir]);

			if (!empty($total_not_moved)) {
				$results[] = sprintf(Lang::$txt['attachments_not_transferred'], $total_not_moved);
			}
		}

		$_SESSION['results'] = $results;

		if (file_exists(Config::$boarddir . '/progress.php')) {
			unlink(Config::$boarddir . '/progress.php');
		}

		Utils::redirectexit('action=admin;area=manageattachments;sa=maintenance#transfer');
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Static wrapper for constructor.
	 *
	 * @return object An instance of this class.
	 */
	public static function load(): object
	{
		if (!isset(self::$obj)) {
			self::$obj = new self();
		}

		return self::$obj;
	}

	/**
	 * Convenience method to load() and execute() an instance of this class.
	 */
	public static function call(): void
	{
		self::load()->execute();
	}

	/**
	 * Gets the configuration variables for the attachments sub-action.
	 *
	 * @return array $config_vars for the attachments sub-action.
	 */
	public static function attachConfigVars(): array
	{
		Utils::$context['attachmentUploadDir'] = Config::$modSettings['attachmentUploadDir'][Config::$modSettings['currentAttachmentUploadDir']];

		// If not set, show a default path for the base directory
		if (!isset($_GET['save']) && empty(Config::$modSettings['basedirectory_for_attachments'])) {
			if (is_dir(Config::$modSettings['attachmentUploadDir'][1])) {
				Config::$modSettings['basedirectory_for_attachments'] = Config::$modSettings['attachmentUploadDir'][1];
			} else {
				Config::$modSettings['basedirectory_for_attachments'] = Utils::$context['attachmentUploadDir'];
			}
		}

		Utils::$context['valid_upload_dir'] = is_dir(Utils::$context['attachmentUploadDir']) && is_writable(Utils::$context['attachmentUploadDir']);

		if (!empty(Config::$modSettings['automanage_attachments'])) {
			Utils::$context['valid_basedirectory'] = !empty(Config::$modSettings['basedirectory_for_attachments']) && is_writable(Config::$modSettings['basedirectory_for_attachments']);
		} else {
			Utils::$context['valid_basedirectory'] = true;
		}

		// A bit of razzle dazzle with the Lang::$txt strings. :)
		Lang::$txt['attachment_path'] = Utils::$context['attachmentUploadDir'];

		if (
			empty(Config::$modSettings['attachment_basedirectories'])
			&& Config::$modSettings['currentAttachmentUploadDir'] == 1
			&& count(Config::$modSettings['attachmentUploadDir']) == 1
		) {
			Lang::$txt['attachmentUploadDir_path'] = Config::$modSettings['attachmentUploadDir'][1];
		}

		Lang::$txt['basedirectory_for_attachments_path'] = Config::$modSettings['basedirectory_for_attachments'] ?? '';

		Lang::$txt['use_subdirectories_for_attachments_note'] = empty(Config::$modSettings['attachment_basedirectories']) || empty(Config::$modSettings['use_subdirectories_for_attachments']) ? Lang::$txt['use_subdirectories_for_attachments_note'] : '';

		Lang::$txt['attachmentUploadDir_multiple_configure'] = '<a href="' . Config::$scripturl . '?action=admin;area=manageattachments;sa=attachpaths">[' . Lang::$txt['attachmentUploadDir_multiple_configure'] . ']</a>';

		Lang::$txt['attach_current_dir'] = empty(Config::$modSettings['automanage_attachments']) ? Lang::$txt['attach_current_dir'] : Lang::$txt['attach_last_dir'];

		Lang::$txt['attach_current_dir_warning'] = Lang::$txt['attach_current_dir'] . Lang::$txt['attach_current_dir_warning'];

		Lang::$txt['basedirectory_for_attachments_warning'] = Lang::$txt['basedirectory_for_attachments_current'] . Lang::$txt['basedirectory_for_attachments_warning'];

		// Perform a test to see if the GD module or ImageMagick are installed.
		$testImg = get_extension_funcs('gd') || class_exists('Imagick');

		// See if we can find if the server is set up to support the attachment limits
		$post_max_kb = floor(Config::memoryReturnBytes(ini_get('post_max_size')) / 1024);
		$file_max_kb = floor(Config::memoryReturnBytes(ini_get('upload_max_filesize')) / 1024);

		$config_vars = [
			['title', 'attachment_manager_settings'],
			// Are attachments enabled?
			['select', 'attachmentEnable', [Lang::$txt['attachmentEnable_deactivate'], Lang::$txt['attachmentEnable_enable_all'], Lang::$txt['attachmentEnable_disable_new']]],
			'',

			// Directory and size limits.
			['select', 'automanage_attachments', [0 => Lang::$txt['attachments_normal'], 1 => Lang::$txt['attachments_auto_space'], 2 => Lang::$txt['attachments_auto_years'], 3 => Lang::$txt['attachments_auto_months'], 4 => Lang::$txt['attachments_auto_16']]],
			['check', 'use_subdirectories_for_attachments', 'subtext' => Lang::$txt['use_subdirectories_for_attachments_note']],
			(empty(Config::$modSettings['attachment_basedirectories']) ? ['text', 'basedirectory_for_attachments', 40] : ['var_message', 'basedirectory_for_attachments', 'message' => 'basedirectory_for_attachments_path', 'invalid' => empty(Utils::$context['valid_basedirectory']), 'text_label' => (!empty(Utils::$context['valid_basedirectory']) ? Lang::$txt['basedirectory_for_attachments_current'] : sprintf(Lang::$txt['basedirectory_for_attachments_warning'], Config::$scripturl))]),
			empty(Config::$modSettings['attachment_basedirectories']) && Config::$modSettings['currentAttachmentUploadDir'] == 1 && count(Config::$modSettings['attachmentUploadDir']) == 1 ? ['var_message', 'attachmentUploadDir_path', 'subtext' => Lang::$txt['attachmentUploadDir_multiple_configure'], 40, 'invalid' => !Utils::$context['valid_upload_dir'], 'text_label' => Lang::$txt['attachmentUploadDir'], 'message' => 'attachmentUploadDir_path'] : ['var_message', 'attach_current_directory', 'subtext' => Lang::$txt['attachmentUploadDir_multiple_configure'], 'message' => 'attachment_path', 'invalid' => empty(Utils::$context['valid_upload_dir']), 'text_label' => (!empty(Utils::$context['valid_upload_dir']) ? Lang::$txt['attach_current_dir'] : sprintf(Lang::$txt['attach_current_dir_warning'], Config::$scripturl))],
			['int', 'attachmentDirFileLimit', 'subtext' => Lang::$txt['zero_for_no_limit'], 6],
			['int', 'attachmentDirSizeLimit', 'subtext' => Lang::$txt['zero_for_no_limit'], 6, 'postinput' => Lang::$txt['kilobyte']],
			['check', 'dont_show_attach_under_post', 'subtext' => Lang::$txt['dont_show_attach_under_post_sub']],
			'',

			// Posting limits
			['int', 'attachmentPostLimit', 'subtext' => sprintf(Lang::$txt['attachment_ini_max'], $post_max_kb . ' ' . Lang::$txt['kilobyte']), 6, 'postinput' => Lang::$txt['kilobyte'], 'min' => 1, 'max' => $post_max_kb, 'disabled' => empty($post_max_kb)],
			['int', 'attachmentSizeLimit', 'subtext' => sprintf(Lang::$txt['attachment_ini_max'], $file_max_kb . ' ' . Lang::$txt['kilobyte']), 6, 'postinput' => Lang::$txt['kilobyte'], 'min' => 1, 'max' => $file_max_kb, 'disabled' => empty($file_max_kb)],
			['int', 'attachmentNumPerPostLimit', 'subtext' => Lang::$txt['zero_for_no_limit'], 6, 'min' => 0],
			// Security Items
			['title', 'attachment_security_settings'],
			// Extension checks etc.
			['check', 'attachmentCheckExtensions'],
			['text', 'attachmentExtensions', 40],
			'',

			// Image checks.
			['warning', empty($testImg) ? 'attachment_img_enc_warning' : ''],
			['check', 'attachment_image_reencode'],
			'',

			['warning', 'attachment_image_paranoid_warning'],
			['check', 'attachment_image_paranoid'],
			// Thumbnail settings.
			['title', 'attachment_thumbnail_settings'],
			['check', 'attachmentShowImages'],
			['check', 'attachmentThumbnails'],
			['check', 'attachment_thumb_png'],
			['check', 'attachment_thumb_memory'],
			['warning', 'attachment_thumb_memory_note'],
			['text', 'attachmentThumbWidth', 6],
			['text', 'attachmentThumbHeight', 6],
			'',

			['int', 'max_image_width', 'subtext' => Lang::$txt['zero_for_no_limit']],
			['int', 'max_image_height', 'subtext' => Lang::$txt['zero_for_no_limit']],
		];

		IntegrationHook::call('integrate_modify_attachment_settings', [&$config_vars]);

		return $config_vars;
	}

	/**
	 * Gets the configuration variables for the avatars sub-action.
	 *
	 * @return array $config_vars for the avatars sub-action.
	 */
	public static function avatarConfigVars(): array
	{
		// Perform a test to see if the GD module or ImageMagick are installed.
		$testImg = get_extension_funcs('gd') || class_exists('Imagick');

		Utils::$context['valid_avatar_dir'] = is_dir(Config::$modSettings['avatar_directory']);
		Utils::$context['valid_custom_avatar_dir'] = !empty(Config::$modSettings['custom_avatar_dir']) && is_dir(Config::$modSettings['custom_avatar_dir']) && is_writable(Config::$modSettings['custom_avatar_dir']);

		$config_vars = [
			// Server stored avatars!
			['title', 'avatar_server_stored'],
			['warning', empty($testImg) ? 'avatar_img_enc_warning' : ''],
			['permissions', 'profile_server_avatar', 0, Lang::$txt['avatar_server_stored_groups']],
			['warning', !Utils::$context['valid_avatar_dir'] ? 'avatar_directory_wrong' : ''],
			['text', 'avatar_directory', 40, 'invalid' => !Utils::$context['valid_avatar_dir']],
			['text', 'avatar_url', 40],
			// External avatars?
			['title', 'avatar_external'],
			['permissions', 'profile_remote_avatar', 0, Lang::$txt['avatar_external_url_groups']],
			['check', 'avatar_download_external', 0, 'onchange' => 'fUpdateStatus();'],
			['text', 'avatar_max_width_external', 'subtext' => Lang::$txt['zero_for_no_limit'], 6],
			['text', 'avatar_max_height_external', 'subtext' => Lang::$txt['zero_for_no_limit'], 6],
			['select', 'avatar_action_too_large',
				[
					'option_refuse' => Lang::$txt['option_refuse'],
					'option_css_resize' => Lang::$txt['option_css_resize'],
					'option_download_and_resize' => Lang::$txt['option_download_and_resize'],
				],
			],
			// Uploadable avatars?
			['title', 'avatar_upload'],
			['permissions', 'profile_upload_avatar', 0, Lang::$txt['avatar_upload_groups']],
			['text', 'avatar_max_width_upload', 'subtext' => Lang::$txt['zero_for_no_limit'], 6],
			['text', 'avatar_max_height_upload', 'subtext' => Lang::$txt['zero_for_no_limit'], 6],
			['check', 'avatar_resize_upload', 'subtext' => Lang::$txt['avatar_resize_upload_note']],
			['check', 'avatar_download_png'],
			['check', 'avatar_reencode'],
			'',

			['warning', 'avatar_paranoid_warning'],
			['check', 'avatar_paranoid'],
			'',

			['warning', !Utils::$context['valid_custom_avatar_dir'] ? 'custom_avatar_dir_wrong' : ''],
			['text', 'custom_avatar_dir', 40, 'subtext' => Lang::$txt['custom_avatar_dir_desc'], 'invalid' => !Utils::$context['valid_custom_avatar_dir']],
			['text', 'custom_avatar_url', 40],
			// Grvatars?
			['title', 'gravatar_settings'],
			['check', 'gravatarEnabled'],
			['check', 'gravatarOverride'],
			['check', 'gravatarAllowExtraEmail'],
			'',

			['select', 'gravatarMaxRating',
				[
					'G' => Lang::$txt['gravatar_maxG'],
					'PG' => Lang::$txt['gravatar_maxPG'],
					'R' => Lang::$txt['gravatar_maxR'],
					'X' => Lang::$txt['gravatar_maxX'],
				],
			],
			['select', 'gravatarDefault',
				[
					'mm' => Lang::$txt['gravatar_mm'],
					'identicon' => Lang::$txt['gravatar_identicon'],
					'monsterid' => Lang::$txt['gravatar_monsterid'],
					'wavatar' => Lang::$txt['gravatar_wavatar'],
					'retro' => Lang::$txt['gravatar_retro'],
					'blank' => Lang::$txt['gravatar_blank'],
				],
			],
		];

		IntegrationHook::call('integrate_modify_avatar_settings', [&$config_vars]);

		return $config_vars;
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
	public static function list_getFiles($start, $items_per_page, $sort, $browse_type): array
	{
		$files = [];

		// Choose a query depending on what we are viewing.
		if ($browse_type === 'avatars') {
			$request = Db::$db->query(
				'',
				'SELECT
					{string:blank_text} AS id_msg, COALESCE(mem.real_name, {string:not_applicable_text}) AS poster_name,
					mem.last_login AS poster_time, 0 AS id_topic, a.id_member, a.id_attach, a.filename, a.file_hash, a.attachment_type,
					a.size, a.width, a.height, a.downloads, {string:blank_text} AS subject, 0 AS id_board
				FROM {db_prefix}attachments AS a
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_member)
				WHERE a.id_member != {int:guest_id}
				ORDER BY {raw:sort}
				LIMIT {int:start}, {int:per_page}',
				[
					'guest_id' => 0,
					'blank_text' => '',
					'not_applicable_text' => Lang::$txt['not_applicable'],
					'sort' => $sort,
					'start' => $start,
					'per_page' => $items_per_page,
				],
			);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT
					m.id_msg, COALESCE(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.id_topic, m.id_member,
					a.id_attach, a.filename, a.file_hash, a.attachment_type, a.size, a.width, a.height, a.downloads, mf.subject, t.id_board
				FROM {db_prefix}attachments AS a
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE a.attachment_type = {int:attachment_type}
					AND a.id_member = {int:guest_id_member}
				ORDER BY {raw:sort}
				LIMIT {int:start}, {int:per_page}',
				[
					'attachment_type' => $browse_type == 'thumbs' ? '3' : '0',
					'guest_id_member' => 0,
					'sort' => $sort,
					'start' => $start,
					'per_page' => $items_per_page,
				],
			);
		}

		while ($row = Db::$db->fetch_assoc($request)) {
			$files[] = $row;
		}
		Db::$db->free_result($request);

		return $files;
	}

	/**
	 * Return the number of files of the specified type recorded in the database.
	 * (the specified type being attachments or avatars).
	 *
	 * @param string $browse_type can be one of 'avatars' or not. (in which case they're attachments)
	 * @return int The number of files
	 */
	public static function list_getNumFiles($browse_type): int
	{
		// Depending on the type of file, different queries are used.
		if ($browse_type === 'avatars') {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*)
				FROM {db_prefix}attachments
				WHERE id_member != {int:guest_id_member}',
				[
					'guest_id_member' => 0,
				],
			);
		} else {
			$request = Db::$db->query(
				'',
				'SELECT COUNT(*) AS num_attach
				FROM {db_prefix}attachments AS a
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				WHERE a.attachment_type = {int:attachment_type}
					AND a.id_member = {int:guest_id_member}',
				[
					'attachment_type' => $browse_type === 'thumbs' ? '3' : '0',
					'guest_id_member' => 0,
				],
			);
		}
		list($num_files) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return $num_files;
	}

	/**
	 * Prepare the actual attachment directories to be displayed in the list.
	 *
	 * @return array An array of information about the attachment directories
	 */
	public static function list_getAttachDirs(): array
	{
		$expected_files = [];
		$expected_size = [];

		$request = Db::$db->query(
			'',
			'SELECT id_folder, COUNT(id_attach) AS num_attach, SUM(size) AS size_attach
			FROM {db_prefix}attachments
			WHERE attachment_type != {int:type}
			GROUP BY id_folder',
			[
				'type' => 1,
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			$expected_files[$row['id_folder']] = $row['num_attach'];
			$expected_size[$row['id_folder']] = $row['size_attach'];
		}
		Db::$db->free_result($request);

		$attachdirs = [];

		foreach (Config::$modSettings['attachmentUploadDir'] as $id => $dir) {
			// If there aren't any attachments in this directory this won't exist.
			if (!isset($expected_files[$id])) {
				$expected_files[$id] = 0;
			}

			// Check if the directory is doing okay.
			list($status, $error, $files) = attachDirStatus($dir, $expected_files[$id]);

			// If it is one, let's show that it's a base directory.
			$sub_dirs = 0;
			$is_base_dir = false;

			if (!empty(Config::$modSettings['attachment_basedirectories'])) {
				$is_base_dir = in_array($dir, Config::$modSettings['attachment_basedirectories']);

				// Count any sub-folders.
				foreach (Config::$modSettings['attachmentUploadDir'] as $sid => $sub) {
					if (strpos($sub, $dir . DIRECTORY_SEPARATOR) !== false) {
						$expected_files[$id]++;
						$sub_dirs++;
					}
				}
			}

			$attachdirs[] = [
				'id' => $id,
				'current' => $id == Config::$modSettings['currentAttachmentUploadDir'],
				'disable_current' => isset(Config::$modSettings['automanage_attachments']) && Config::$modSettings['automanage_attachments'] > 1,
				'disable_base_dir' => $is_base_dir && $sub_dirs > 0 && !empty($files) && empty($error) && empty($save_errors),
				'path' => $dir,
				'current_size' => !empty($expected_size[$id]) ? Lang::numberFormat($expected_size[$id] / 1024, 0) : 0,
				'num_files' => Lang::numberFormat($expected_files[$id] - $sub_dirs, 0) . ($sub_dirs > 0 ? ' (' . $sub_dirs . ')' : ''),
				'status' => ($is_base_dir ? Lang::$txt['attach_dir_basedir'] . '<br>' : '') . ($error ? '<div class="error">' : '') . sprintf(Lang::$txt['attach_dir_' . $status], Utils::$context['session_id'], Utils::$context['session_var'], Config::$scripturl) . ($error ? '</div>' : ''),
			];
		}

		// Just stick a new directory on at the bottom.
		if (isset($_REQUEST['new_path'])) {
			$attachdirs[] = [
				'id' => max(array_merge(array_keys($expected_files), array_keys(Config::$modSettings['attachmentUploadDir']))) + 1,
				'current' => false,
				'path' => '',
				'current_size' => '',
				'num_files' => '',
				'status' => '',
			];
		}

		return $attachdirs;
	}

	/**
	 * Prepare the base directories to be displayed in a list.
	 *
	 * @return array Returns an array of info about the directories.
	 */
	public static function list_getBaseDirs(): array
	{
		if (empty(Config::$modSettings['attachment_basedirectories'])) {
			return [];
		}

		$basedirs = [];

		// Get a list of the base directories.
		foreach (Config::$modSettings['attachment_basedirectories'] as $id => $dir) {
			// Loop through the attach directory array to count any sub-directories
			$expected_dirs = 0;

			foreach (Config::$modSettings['attachmentUploadDir'] as $sid => $sub) {
				if (strpos($sub, $dir . DIRECTORY_SEPARATOR) !== false) {
					$expected_dirs++;
				}
			}

			if (!is_dir($dir)) {
				$status = 'does_not_exist';
			} elseif (!is_writeable($dir)) {
				$status = 'not_writable';
			} else {
				$status = 'ok';
			}

			$basedirs[] = [
				'id' => $id,
				'current' => $dir == Config::$modSettings['basedirectory_for_attachments'],
				'path' => $expected_dirs > 0 ? $dir : ('<input type="text" name="base_dir[' . $id . ']" value="' . $dir . '" size="40">'),
				'num_dirs' => $expected_dirs,
				'status' => $status == 'ok' ? Lang::$txt['attach_dir_ok'] : ('<span class="error">' . Lang::$txt['attach_dir_' . $status] . '</span>'),
			];
		}

		if (isset($_REQUEST['new_base_path'])) {
			$basedirs[] = [
				'id' => '',
				'current' => false,
				'path' => '<input type="text" name="new_base_dir" value="" size="40">',
				'num_dirs' => '',
				'status' => '',
			];
		}

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
	public static function attachDirStatus($dir, $expected_files): array
	{
		if (!is_dir($dir)) {
			return ['does_not_exist', true, ''];
		}

		if (!is_writable($dir)) {
			return ['not_writable', true, ''];
		}

		// Everything is okay so far, start to scan through the directory.
		$num_files = 0;
		$dir_handle = dir($dir);

		while ($file = $dir_handle->read()) {
			// Now do we have a real file here?
			if (in_array($file, ['.', '..', '.htaccess', 'index.php'])) {
				continue;
			}

			$num_files++;
		}

		$dir_handle->close();

		if ($num_files < $expected_files) {
			return ['files_missing', true, $num_files];
		}

		// Empty?
		if ($expected_files == 0) {
			return ['unused', false, $num_files];
		}

		// All good!
		return ['ok', false, $num_files];
	}

	/**
	 * Backward compatibility wrapper for the attachments sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function manageAttachmentSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::attachConfigVars();
		}

		self::load();
		self::$obj->subaction = 'attachments';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the avatars sub-action.
	 *
	 * @param bool $return_config Whether to return the config_vars array.
	 * @return void|array Returns nothing or returns the config_vars array.
	 */
	public static function manageAvatarSettings($return_config = false)
	{
		if (!empty($return_config)) {
			return self::avatarConfigVars();
		}

		self::load();
		self::$obj->subaction = 'avatars';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the browse sub-action.
	 */
	public static function browseFiles(): void
	{
		self::load();
		self::$obj->subaction = 'browse';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the maintenance sub-action.
	 */
	public static function maintainFiles(): void
	{
		self::load();
		self::$obj->subaction = 'maintenance';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the remove sub-action.
	 */
	public static function removeAttachment(): void
	{
		self::load();
		self::$obj->subaction = 'remove';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the byage sub-action.
	 */
	public static function removeAttachmentByAge(): void
	{
		self::load();
		self::$obj->subaction = 'byage';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the bysize sub-action.
	 */
	public static function removeAttachmentBySize(): void
	{
		self::load();
		self::$obj->subaction = 'bysize';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the removeall sub-action.
	 */
	public static function removeAllAttachments(): void
	{
		self::load();
		self::$obj->subaction = 'removeall';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the repair sub-action.
	 */
	public static function repairAttachments(): void
	{
		self::load();
		self::$obj->subaction = 'repair';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the attachpaths sub-action.
	 */
	public static function manageAttachmentPaths(): void
	{
		self::load();
		self::$obj->subaction = 'attachpaths';
		self::$obj->execute();
	}

	/**
	 * Backward compatibility wrapper for the transfer sub-action.
	 */
	public static function transferAttachments(): void
	{
		self::load();
		self::$obj->subaction = 'transfer';
		self::$obj->execute();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Constructor. Protected to force instantiation via self::load().
	 */
	protected function __construct()
	{
		// You have to be able to moderate the forum to do this.
		User::$me->isAllowedTo('manage_attachments');

		// Setup the template stuff we'll probably need.
		Theme::loadTemplate('ManageAttachments');

		// This uses admin tabs - as it should!
		Menu::$loaded['admin']->tab_data = [
			'title' => Lang::$txt['attachments_avatars'],
			'help' => 'manage_files',
			'description' => Lang::$txt['attachments_desc'],
		];

		IntegrationHook::call('integrate_manage_attachments', [&self::$subactions]);

		if (!empty($_REQUEST['sa']) && isset(self::$subactions[strtolower($_REQUEST['sa'])])) {
			$this->subaction = strtolower($_REQUEST['sa']);
		}

		Utils::$context['sub_action'] = &$this->subaction;

		// Default page title is good.
		Utils::$context['page_title'] = Lang::$txt['attachments_avatars'];
	}

	/**
	 * Function called in-between each round of attachments and avatar repairs.
	 *
	 * Called by self::repair().
	 *
	 * If more steps are ever added to self::repair(), this method will need to
	 * be updated!
	 *
	 * @param array $to_fix IDs of attachments to fix.
	 * @param int $max_substep The maximum substep to reach before pausing.
	 */
	protected function pauseAttachmentMaintenance($to_fix, $max_substep = 0): void
	{
		// Try get more time...
		@set_time_limit(600);

		if (function_exists('apache_reset_timeout')) {
			@apache_reset_timeout();
		}

		// Have we already used our maximum time?
		if ((time() - TIME_START) < 3 || Utils::$context['starting_substep'] == $_GET['substep']) {
			return;
		}

		Utils::$context['continue_get_data'] = '?action=admin;area=manageattachments;sa=repair' . (isset($_GET['fixErrors']) ? ';fixErrors' : '') . ';step=' . $_GET['step'] . ';substep=' . $_GET['substep'] . ';' . Utils::$context['session_var'] . '=' . Utils::$context['session_id'];
		Utils::$context['page_title'] = Lang::$txt['not_done_title'];
		Utils::$context['continue_post_data'] = '';
		Utils::$context['continue_countdown'] = '2';
		Utils::$context['sub_template'] = 'not_done';

		// Specific stuff to not break this template!
		Menu::$loaded['admin']['current_subsection'] = 'maintenance';

		// Change these two if more steps are added!
		if (empty($max_substep)) {
			Utils::$context['continue_percent'] = round(($_GET['step'] * 100) / 25);
		} else {
			Utils::$context['continue_percent'] = round(($_GET['step'] * 100 + ($_GET['substep'] * 100) / $max_substep) / 25);
		}

		// Never more than 100%!
		Utils::$context['continue_percent'] = min(Utils::$context['continue_percent'], 100);

		$_SESSION['attachments_to_fix'] = $to_fix;
		$_SESSION['attachments_to_fix2'] = Utils::$context['repair_errors'];

		Utils::obExit();
	}
}

// Some functions have been migrated from here to the Attachment class.
class_exists('SMF\\Attachment');

// Export public static functions and properties to global namespace for backward compatibility.
if (is_callable(__NAMESPACE__ . '\\Attachments::exportStatic')) {
	Attachments::exportStatic();
}

?>