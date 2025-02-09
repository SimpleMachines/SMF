<?php

/**
 * This file provides compatibility functions and code for older versions of
 * SMF and PHP, such as missing extensions or 64-bit vs 32-bit systems.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

if (!defined('SMF')) {
	die('No direct access...');
}

/*********************************************
 * SMF\Config::$backward_compatibility support
 *********************************************/

/*
 * Modifications that rely on backward compatibility support (meaning, they
 * were written for SMF 2.1) expect all of SMF's code to be available as a
 * collection of named functions in the global namespace. For that reason,
 * providing backward compatibility support requires providing all of those
 * global functions as wrappers around class methods.
 */
if (!empty(SMF\Config::$backward_compatibility)) {
	/****************************
	 * Begin SMF\Actions\Activate
	 ****************************/

	/**
	 * Activate an users account.
	 *
	 * Checks for mail changes, resends password if needed.
	 */
	function Activate()
	{
		return SMF\Actions\Activate::call();
	}

	/*****************************
	 * Begin SMF\Actions\Admin\ACP
	 *****************************/

	/**
	 * The main admin handling function.<br>
	 * It initialises all the basic context required for the admin center.<br>
	 * It passes execution onto the relevant admin section.<br>
	 * If the passed section is not found it shows the admin home page.
	 */
	function AdminMain()
	{
		return SMF\Actions\Admin\ACP::call();
	}

	/**
	 * Helper function, it sets up the context for database settings.
	 *
	 * @todo see rev. 10406 from 2.1-requests
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	function prepareDBSettingContext(&$config_vars): void
	{
		SMF\Actions\Admin\ACP::prepareDBSettingContext($config_vars);
	}

	/**
	 * Helper function. Saves settings by putting them in Settings.php or saving them in the settings table.
	 *
	 * - Saves those settings set from ?action=admin;area=serversettings.
	 * - Requires the admin_forum permission.
	 * - Contains arrays of the types of data to save into Settings.php.
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	function saveSettings(&$config_vars): void
	{
		SMF\Actions\Admin\ACP::saveSettings($config_vars);
	}

	/**
	 * Helper function for saving database settings.
	 *
	 * @todo see rev. 10406 from 2.1-requests
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	function saveDbSettings(&$config_vars): void
	{
		SMF\Actions\Admin\ACP::saveDBSettings($config_vars);
	}

	/**
	 * Get a list of versions that are currently installed on the server.
	 *
	 * @param array $checkFor An array of what to check versions for - can contain one or more of 'gd', 'imagemagick', 'db_server', 'phpa', 'memcache', 'php' or 'server'
	 * @return array An array of versions (keys are same as what was in $checkFor, values are the versions)
	 */
	function getServerVersions(array $checkFor): array
	{
		return SMF\Actions\Admin\ACP::getServerVersions($checkFor);
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
	function getFileVersions(array &$versionOptions): array
	{
		return SMF\Actions\Admin\ACP::getFileVersions($versionOptions);
	}

	/**
	 * Saves the admin's current preferences to the database.
	 */
	function updateAdminPreferences(): void
	{
		SMF\Actions\Admin\ACP::updateAdminPreferences();
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
	function emailAdmins(string $template, array $replacements = [], array $additional_recipients = []): void
	{
		SMF\Actions\Admin\ACP::emailAdmins($template, $replacements, $additional_recipients);
	}

	/**
	 * Question the verity of the admin by asking for his or her password.
	 * - loads Login.template.php and uses the admin_login sub template.
	 * - sends data to template so the admin is sent on to the page they
	 *   wanted if their password is correct, otherwise they can try again.
	 *
	 * @param string $type What login type is this - can be 'admin' or 'moderate'
	 */
	function adminLogin(string $type = 'admin'): void
	{
		SMF\Actions\Admin\ACP::adminLogin($type);
	}

	/**********************************
	 * Begin SMF\Actions\Admin\AntiSpam
	 **********************************/

	/**
	 * Let's try keep the spam to a minimum ah Thantos?
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyAntispamSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\AntiSpam::subActionProvider(return_config: $return_config);
	}

	/*************************************
	 * Begin SMF\Actions\Admin\Attachments
	 *************************************/

	/**
	 * The main 'Attachments and Avatars' management function.
	 * This function is the entry point for index.php?action=admin;area=manageattachments
	 * and it calls a function based on the sub-action.
	 * It requires the manage_attachments permission.
	 *
	 * Uses ManageAttachments template.
	 * Uses Admin language file.
	 * Uses template layer 'manage_files' for showing the tab bar.
	 *
	 */
	function ManageAttachments()
	{
		return SMF\Actions\Admin\Attachments::call();
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
	function attachDirStatus(string $dir, int $expected_files): array
	{
		return SMF\Actions\Admin\Attachments::attachDirStatus($dir, $expected_files);
	}

	/**
	 * Allows to show/change attachment settings.
	 * This is the default sub-action of the 'Attachments and Avatars' center.
	 * Called by index.php?action=admin;area=manageattachments;sa=attachments.
	 * Uses 'attachments' sub template.
	 *
	 * @param bool $return_config Whether to return the array of config variables (used for admin search)
	 * @return ?array If $return_config is true, simply returns the config_vars array, otherwise returns nothing
	 */
	function ManageAttachmentSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Attachments::subActionProvider(sa: 'attachments', return_config: $return_config);
	}

	/**
	 * This allows to show/change avatar settings.
	 * Called by index.php?action=admin;area=manageattachments;sa=avatars.
	 * Show/set permissions for permissions: 'profile_server_avatar',
	 * 	'profile_upload_avatar' and 'profile_remote_avatar'.
	 *
	 * @param bool $return_config Whether to return the config_vars array (used for admin search)
	 * @return ?array Returns the config_vars array if $return_config is true, otherwise returns nothing
	 */
	function ManageAvatarSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Attachments::subActionProvider(sa: 'avatars', return_config: $return_config);
	}

	/**
	 * Show a list of attachment or avatar files.
	 * Called by ?action=admin;area=manageattachments;sa=browse for attachments
	 *  and ?action=admin;area=manageattachments;sa=browse;avatars for avatars.
	 * Allows sorting by name, date, size and member.
	 * Paginates results.
	 */
	function BrowseFiles(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'browse');
	}

	/**
	 * Show several file maintenance options.
	 * Called by ?action=admin;area=manageattachments;sa=maintain.
	 * Calculates file statistics (total file size, number of attachments,
	 * number of avatars, attachment space available).
	 *
	 * @uses template_maintenance()
	 */
	function MaintainFiles(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'maintenance');
	}

	/**
	 * Remove a selection of attachments or avatars.
	 * Called from the browse screen as submitted form by
	 *  ?action=admin;area=manageattachments;sa=remove
	 */
	function RemoveAttachment(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'remove');
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
	function RemoveAttachmentByAge(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'byage');
	}

	/**
	 * Remove attachments larger than a given size.
	 * Called from the maintenance screen by
	 *  ?action=admin;area=manageattachments;sa=bySize.
	 * Optionally adds a certain text to the messages the attachments were
	 * 	removed from.
	 */
	function RemoveAttachmentBySize(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'bysize');
	}

	/**
	 * Removes all attachments in a single click
	 * Called from the maintenance screen by
	 *  ?action=admin;area=manageattachments;sa=removeall.
	 */
	function RemoveAllAttachments(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'removeall');
	}

	/**
	 * This function should find attachments in the database that no longer exist and clear them, and fix filesize issues.
	 */
	function RepairAttachments(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'repair');
	}

	/**
	 * This function lists and allows updating of multiple attachments paths.
	 */
	function ManageAttachmentPaths(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'attachpaths');
	}

	/**
	 * Maintance function to move attachments from one directory to another
	 */
	function TransferAttachments(): void
	{
		SMF\Actions\Admin\Attachments::subActionProvider(sa: 'transfer');
	}

	/******************************
	 * Begin SMF\Actions\Admin\Bans
	 ******************************/

	/**
	 * Ban center. The main entrance point for all ban center functions.
	 * It is accesssed by ?action=admin;area=ban.
	 * It choses a function based on the 'sa' parameter, like many others.
	 * The default sub-action is BanList().
	 * It requires the ban_members permission.
	 * It initializes the admin tabs.
	 *
	 * Uses ManageBans template.
	 */
	function Ban(): void
	{
		SMF\Actions\Admin\Bans::call();
	}

	/**
	 * As it says... this tries to review the list of banned members, to match new bans.
	 * Note: is_activated >= 10: a member is banned.
	 */
	function updateBanMembers(): void
	{
		SMF\Actions\Admin\Bans::updateBanMembers();
	}

	/**
	 * Shows a list of bans currently set.
	 * It is accessed by ?action=admin;area=ban;sa=list.
	 * It removes expired bans.
	 * It allows sorting on different criteria.
	 * It also handles removal of selected ban items.
	 *
	 * Uses the main ManageBans template.
	 */
	function BanList(): void
	{
		SMF\Actions\Admin\Bans::subActionProvider(sa: 'list');
	}

	/**
	 * This function is behind the screen for adding new bans and modifying existing ones.
	 * Adding new bans:
	 * 	- is accessed by ?action=admin;area=ban;sa=add.
	 * 	- uses the ban_edit sub template of the ManageBans template.
	 * Modifying existing bans:
	 *  - is accessed by ?action=admin;area=ban;sa=edit;bg=x
	 *  - uses the ban_edit sub template of the ManageBans template.
	 *  - shows a list of ban triggers for the specified ban.
	 */
	function BanEdit(): void
	{
		SMF\Actions\Admin\Bans::subActionProvider(sa: 'edit');
	}

	/**
	 * This handles the screen for showing the banned entities
	 * It is accessed by ?action=admin;area=ban;sa=browse
	 * It uses sub-tabs for browsing by IP, hostname, email or username.
	 *
	 * Uses a standard list (@see createList())
	 */
	function BanBrowseTriggers(): void
	{
		SMF\Actions\Admin\Bans::subActionProvider(sa: 'browse');
	}

	/**
	 * This function handles the ins and outs of the screen for adding new ban
	 * triggers or modifying existing ones.
	 * Adding new ban triggers:
	 * 	- is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x
	 * 	- uses the ban_edit_trigger sub template of ManageBans.
	 * Editing existing ban triggers:
	 *  - is accessed by ?action=admin;area=ban;sa=edittrigger;bg=x;bi=y
	 *  - uses the ban_edit_trigger sub template of ManageBans.
	 */
	function BanEditTrigger(): void
	{
		SMF\Actions\Admin\Bans::subActionProvider(sa: 'edittrigger');
	}

	/**
	 * This handles the listing of ban log entries, and allows their deletion.
	 * Shows a list of logged access attempts by banned users.
	 * It is accessed by ?action=admin;area=ban;sa=log.
	 * How it works:
	 *  - allows sorting of several columns.
	 *  - also handles deletion of (a selection of) log entries.
	 */
	function BanLog(): void
	{
		SMF\Actions\Admin\Bans::subActionProvider(sa: 'log');
	}

	/********************************
	 * Begin SMF\Actions\Admin\Boards
	 ********************************/

	/**
	 * The main dispatcher; doesn't do anything, just delegates.
	 * This is the main entry point for all the manageboards admin screens.
	 * Called by ?action=admin;area=manageboards.
	 * It checks the permissions, based on the sub-action, and calls a function based on the sub-action.
	 *
	 * Uses ManageBoards language file.
	 */
	function ManageBoards(): void
	{
		SMF\Actions\Admin\Boards::call();
	}

	/**
	 * A screen to set a few general board and category settings.
	 *
	 * @uses template_show_settings()
	 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
	 * @return ?array Returns nothing or the array of config vars if $return_config is true
	 */
	function EditBoardSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Boards::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/**********************************
	 * Begin SMF\Actions\Admin\Calendar
	 **********************************/

	/**
	 * The main controlling function doesn't have much to do... yet.
	 * Just check permissions and delegate to the rest.
	 *
	 * Uses ManageCalendar language file.
	 */
	function ManageCalendar(): void
	{
		SMF\Actions\Admin\Calendar::call();
	}

	/**
	 * The function that handles adding, and deleting holiday data
	 */
	function ModifyHolidays(): void
	{
		SMF\Actions\Admin\Calendar::subActionProvider(sa: 'holidays');
	}

	/**
	 * This function is used for adding/editing a specific holiday
	 */
	function EditHoliday(): void
	{
		SMF\Actions\Admin\Calendar::subActionProvider(sa: 'editholiday');
	}

	/**
	 * Show and allow to modify calendar settings. Obviously.
	 *
	 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns $config_vars if $return_config is true
	 */
	function ModifyCalendarSettings(bool $return_config = false): ?array
	{
		SMF\Actions\Admin\Calendar::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/************************************
	 * Begin SMF\Actions\Admin\EndSession
	 ************************************/

	/**
	 * This ends a admin session, requiring authentication to access the ACP again.
	 */
	function AdminEndSession(): void
	{
		SMF\Actions\Admin\EndSession::call();
	}

	/**********************************
	 * Begin SMF\Actions\Admin\ErrorLog
	 **********************************/

	/**
	 * View the forum's error log.
	 * This function sets all the context up to show the error log for maintenance.
	 * It requires the maintain_forum permission.
	 * It is accessed from ?action=admin;area=logs;sa=errorlog.
	 *
	 * @uses template_error_log()
	 */
	function ViewErrorLog(): void
	{
		SMF\Actions\Admin\ErrorLog::call();
	}

	/**********************************
	 * Begin SMF\Actions\Admin\Features
	 **********************************/

	/**
	 * This function passes control through to the relevant tab.
	 */
	function ModifyFeatureSettings(): void
	{
		SMF\Actions\Admin\Features::call();
	}

	/**
	 * Config array for changing the basic forum settings
	 * Accessed  from ?action=admin;area=featuresettings;sa=basic;
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyBasicSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Features::basicConfigVars();
		}

		SMF\Actions\Admin\Features::load();
		SMF\Actions\Admin\Features::$obj->subaction = 'basic';
		SMF\Actions\Admin\Features::$obj->execute();

		return null;

	}

	/**
	 * Set a few Bulletin Board Code settings. It loads a list of Bulletin Board Code tags to allow disabling tags.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=featuresettings;sa=bbc.
	 * @uses template_show_settings()
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyBBCSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Features::bbcConfigVars();
		}

		SMF\Actions\Admin\Features::load();
		SMF\Actions\Admin\Features::$obj->subaction = 'bbc';
		SMF\Actions\Admin\Features::$obj->execute();

		return null;
	}

	/**
	 * Allows modifying the global layout settings in the forum
	 * Accessed through ?action=admin;area=featuresettings;sa=layout;
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyLayoutSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Features::layoutConfigVars();
		}

		SMF\Actions\Admin\Features::load();
		SMF\Actions\Admin\Features::$obj->subaction = 'layout';
		SMF\Actions\Admin\Features::$obj->execute();

		return null;
	}

	/**
	 * You'll never guess what this function does...
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifySignatureSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Features::sigConfigVars();
		}

		SMF\Actions\Admin\Features::load();
		SMF\Actions\Admin\Features::$obj->subaction = 'sig';
		SMF\Actions\Admin\Features::$obj->execute();

		return null;
	}

	/**
	 * Show all the custom profile fields available to the user.
	 */
	function ShowCustomProfiles(): void
	{
		SMF\Actions\Admin\Features::subActionProvider(sa: 'profile');
	}

	/**
	 * Edit some profile fields?
	 */
	function EditCustomProfiles(): void
	{
		SMF\Actions\Admin\Features::subActionProvider(sa: 'profileedit');
	}

	/**
	 * Config array for changing like settings
	 * Accessed  from ?action=admin;area=featuresettings;sa=likes;
	 *
	 * @param bool $return_config Whether or not to return the config_vars array
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyLikesSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Features::likesConfigVars();
		}

		SMF\Actions\Admin\Features::load();
		SMF\Actions\Admin\Features::$obj->subaction = 'likes';
		SMF\Actions\Admin\Features::$obj->execute();

		return null;
	}

	/**
	 * Config array for changing like settings
	 * Accessed  from ?action=admin;area=featuresettings;sa=mentions;
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyMentionsSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Features::mentionsConfigVars();
		}

		SMF\Actions\Admin\Features::load();
		SMF\Actions\Admin\Features::$obj->subaction = 'mentions';
		SMF\Actions\Admin\Features::$obj->execute();

		return null;
	}

	/**
	 * Handles modifying the alerts settings
	 */
	function ModifyAlertsSettings(): void
	{
		SMF\Actions\Admin\Features::subActionProvider(sa: 'alerts');
	}

	/******************************
	 * Begin SMF\Actions\Admin\Find
	 ******************************/

	/**
	 * This function allocates out all the search stuff.
	 */
	function AdminSearch(): void
	{
		SMF\Actions\Admin\Find::call();
	}

	/******************************
	 * Begin SMF\Actions\Admin\Home
	 ******************************/

	/**
	 * The main administration section.
	 * It prepares all the data necessary for the administration front page.
	 * It uses the Admin template along with the admin sub template.
	 * It requires the moderate_forum, manage_membergroups, manage_bans,
	 *  admin_forum, manage_permissions, manage_attachments, manage_smileys,
	 *  manage_boards, edit_news, or send_mail permission.
	 *  It uses the index administrative area.
	 *  It can be found by going to ?action=admin.
	 */
	function AdminHome(): void
	{
		SMF\Actions\Admin\Home::call();
	}

	/***********************************
	 * Begin SMF\Actions\Admin\Languages
	 ***********************************/

	/**
	 * This is the main function for the languages area.
	 * It dispatches the requests.
	 * Loads the ManageLanguages template. (sub-actions will use it)
	 *
	 * @todo lazy loading.
	 *
	 * Uses ManageSettings language file
	 */
	function ManageLanguages(): void
	{
		SMF\Actions\Admin\Languages::call();
	}

	/**
	 * This lists all the current languages and allows editing of them.
	 */
	function ModifyLanguages(): void
	{
		SMF\Actions\Admin\Languages::subActionProvider(sa: 'edit');
	}

	/**
	 * Interface for adding a new language
	 *
	 * @uses template_add_language()
	 */
	function AddLanguage(): void
	{
		SMF\Actions\Admin\Languages::subActionProvider(sa: 'add');
	}

	/**
	 * Edit language related settings.
	 *
	 * @param bool $return_config Whether to return the $config_vars array (used in admin search)
	 * @return ?array Returns nothing or the $config_vars array if $return_config is true
	 */
	function ModifyLanguageSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Languages::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/**
	 * Download a language file from the Simple Machines website.
	 * Requires a valid download ID ("did") in the URL.
	 * Also handles installing language files.
	 * Attempts to chmod things as needed.
	 * Uses a standard list to display information about all the files and where they'll be put.
	 *
	 * @uses template_download_language()
	 * Uses a standard list for displaying languages (@see createList())
	 */
	function DownloadLanguage(): void
	{
		SMF\Actions\Admin\Languages::subActionProvider(sa: 'download');
	}

	/**
	 * Edit a particular set of language entries.
	 */
	function ModifyLanguage(): void
	{
		SMF\Actions\Admin\Languages::subActionProvider(sa: 'editlang');
	}

	/******************************
	 * Begin SMF\Actions\Admin\Logs
	 ******************************/

	/**
	 * This function decides which log to load.
	 */
	function AdminLogs(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Logs::subActionProvider(return_config: $return_config);
	}

	/******************************
	 * Begin SMF\Actions\Admin\Mail
	 ******************************/

	/**
	 * Main dispatcher. This function checks permissions and passes control through to the relevant section.
	 */
	function ManageMail(): void
	{
		SMF\Actions\Admin\Mail::call();
	}

	/**
	 * Display the mail queue...
	 */
	function BrowseMailQueue(): void
	{
		SMF\Actions\Admin\Mail::subActionProvider(sa: 'browse');
	}

	/**
	 * This function clears the mail queue of all emails, and at the end redirects to browse.
	 */
	function ClearMailQueue(): void
	{
		SMF\Actions\Admin\Mail::subActionProvider(sa: 'clear');
	}

	/**
	 * Allows to view and modify the mail settings.
	 *
	 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyMailSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Mail::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/**
	 * Test mail sending ability.
	 *
	 */
	function TestMailSend(): void
	{
		SMF\Actions\Admin\Mail::subActionProvider(sa: 'test');
	}

	/*************************************
	 * Begin SMF\Actions\Admin\Maintenance
	 *************************************/

	/**
	 * Main dispatcher, the maintenance access point.
	 * This, as usual, checks permissions, loads language files, and forwards to the actual workers.
	 */
	function ManageMaintenance(): void
	{
		SMF\Actions\Admin\Maintenance::call();
	}

	/**
	 * Callback function for the integration hooks list (list_integration_hooks)
	 * Gets all of the hooks in the system and their status
	 *
	 * @param int $start The item to start with (for pagination purposes)
	 * @param int $per_page How many items to display on each page
	 * @param string $sort A string indicating how to sort things
	 * @return array An array of information about the integration hooks
	 */
	function get_integration_hooks_data(
		int $start,
		int $per_page,
		string $sort,
		array $filtered_hooks,
		string $normalized_boarddir,
		string $normalized_sourcedir,
	): array {
		return SMF\Actions\Admin\Maintenance::getIntegrationHooksData(
			$start,
			$per_page,
			$sort,
			$filtered_hooks,
			$normalized_boarddir,
			$normalized_sourcedir,
		);
	}

	/**
	 * This function is used to reassociate members with relevant posts.
	 * Reattribute guest posts to a specified member.
	 * Does not check for any permissions.
	 * If add_to_post_count is set, the member's post count is increased.
	 *
	 * @param int $memID The ID of the original poster
	 * @param bool|string $email If set, should be the email of the poster
	 * @param bool|string $membername If set, the membername of the poster
	 * @param bool $post_count Whether to adjust post counts
	 * @return array An array containing the number of messages, topics and reports updated
	 */
	function reattributePosts(
		int $memID,
		?string $email = null,
		?string $membername = null,
		bool $post_count = false,
	): array {
		return SMF\Actions\Admin\Maintenance::reattributePosts($memID, $email, $membername, $post_count);
	}

	/**
	 * Supporting function for the routine maintenance area.
	 */
	function MaintainRoutine(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'routine');
	}

	/**
	 * Supporting function for the database maintenance area.
	 */
	function MaintainDatabase(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'database');
	}

	/**
	 * Supporting function for the members maintenance area.
	 */
	function MaintainMembers(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'members');
	}

	/**
	 * Supporting function for the topics maintenance area.
	 */
	function MaintainTopics(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'topics');
	}

	/**
	 * Generates a list of integration hooks for display
	 * Accessed through ?action=admin;area=maintain;sa=hooks;
	 * Allows for removal or disabling of selected hooks
	 */
	function list_integration_hooks(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'hooks');
	}

	/**
	 * Perform a detailed version check.  A very good thing ;).
	 * The function parses the comment headers in all files for their version information,
	 * and outputs that for some javascript to check with simplemachines.org.
	 * It does not connect directly with simplemachines.org, but rather expects the client to.
	 *
	 * It requires the admin_forum permission.
	 * Uses the view_versions admin area.
	 * Accessed through ?action=admin;area=maintain;sa=routine;activity=version.
	 *
	 * @uses template_view_versions()
	 */
	function VersionDetail(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'routine', activity: 'version');
	}

	/**
	 * Find and fix all errors on the forum.
	 */
	function MaintainFindFixErrors(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'routine', activity: 'repair');
	}

	/**
	 * Recount many forum totals that can be recounted automatically without harm.
	 * it requires the admin_forum permission.
	 * It shows the maintain_forum admin area.
	 *
	 * Totals recounted:
	 * - fixes for topics with wrong num_replies.
	 * - updates for num_posts and num_topics of all boards.
	 * - recounts instant_messages but not unread_messages.
	 * - repairs messages pointing to boards with topics pointing to other boards.
	 * - updates the last message posted in boards and children.
	 * - updates member count, latest member, topic count, and message count.
	 *
	 * The function redirects back to ?action=admin;area=maintain when complete.
	 * It is accessed via ?action=admin;area=maintain;sa=database;activity=recount.
	 */
	function AdminBoardRecount(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'routine', activity: 'recount');
	}

	/**
	 * Rebuilds Settings.php to make it nice and pretty.
	 */
	function RebuildSettingsFile(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'routine', activity: 'rebuild_settings');
	}

	/**
	 * Empties all uninmportant logs
	 */
	function MaintainEmptyUnimportantLogs(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'routine', activity: 'logs');
	}

	/**
	 * Wipes the whole cache.
	 */
	function MaintainCleanCache(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'routine', activity: 'cleancache');
	}

	/**
	 * Optimizes all tables in the database and lists how much was saved.
	 * It requires the admin_forum permission.
	 * It shows as the maintain_forum admin area.
	 * It is accessed from ?action=admin;area=maintain;sa=database;activity=optimize.
	 * It also updates the optimize scheduled task such that the tables are not automatically optimized again too soon.
	 *
	 * @uses template_optimize()
	 */
	function OptimizeTables(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'database', activity: 'optimize');
	}

	/**
	 * Converts HTML-entities to their UTF-8 character equivalents.
	 * This requires the admin_forum permission.
	 * Pre-condition: UTF-8 has been set as database and global character set.
	 *
	 * It is divided in steps of 10 seconds.
	 * This action is linked from the maintenance screen (if applicable).
	 * It is accessed by ?action=admin;area=maintain;sa=database;activity=convertentities.
	 *
	 * @uses template_convert_entities()
	 */
	function ConvertEntities(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'database', activity: 'convertentities');
	}

	/**
	 * Convert the column "body" of the table {db_prefix}messages from TEXT to MEDIUMTEXT and vice versa.
	 * It requires the admin_forum permission.
	 * This is needed only for MySQL.
	 * During the conversion from MEDIUMTEXT to TEXT it check if any of the posts exceed the TEXT length and if so it aborts.
	 * This action is linked from the maintenance screen (if it's applicable).
	 * Accessed by ?action=admin;area=maintain;sa=database;activity=convertmsgbody.
	 *
	 * @uses template_convert_msgbody()
	 */
	function ConvertMsgBody(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'database', activity: 'convertmsgbody');
	}

	/**
	 * Re-attribute posts.
	 */
	function MaintainReattributePosts(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'members', activity: 'reattribute');
	}

	/**
	 * Removing old members. Done and out!
	 *
	 * @todo refactor
	 */
	function MaintainPurgeInactiveMembers(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'members', activity: 'purgeinactive');
	}

	/**
	 * Recalculate all members post counts
	 * it requires the admin_forum permission.
	 *
	 * - recounts all posts for members found in the message table
	 * - updates the members post count record in the members table
	 * - honors the boards post count flag
	 * - does not count posts in the recycle bin
	 * - zeros post counts for all members with no posts in the message table
	 * - runs as a delayed loop to avoid server overload
	 * - uses the not_done template in Admin.template
	 *
	 * The function redirects back to action=admin;area=maintain;sa=members when complete.
	 * It is accessed via ?action=admin;area=maintain;sa=members;activity=recountposts
	 */
	function MaintainRecountPosts(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'members', activity: 'recountposts');
	}

	/**
	 * Moves topics from one board to another.
	 *
	 * @uses template_not_done() to pause the process.
	 */
	function MaintainMassMoveTopics(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'topics', activity: 'massmove');
	}

	/**
	 * Removing old posts doesn't take much as we really pass through.
	 */
	function MaintainRemoveOldPosts(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'topics', activity: 'pruneold');
	}

	/**
	 * Removing old drafts
	 */
	function MaintainRemoveOldDrafts(): void
	{
		SMF\Actions\Admin\Maintenance::subActionProvider(sa: 'topics', activity: 'olddrafts');
	}

	/**************************************
	 * Begin SMF\Actions\Admin\Membergroups
	 **************************************/

	/**
	 * Main dispatcher, the entrance point for all 'Manage Membergroup' actions.
	 * It forwards to a function based on the given subaction, default being subaction 'index', or, without manage_membergroup
	 * permissions, then 'settings'.
	 * Called by ?action=admin;area=membergroups.
	 * Requires the manage_membergroups or the admin_forum permission.
	 *
	 * Uses ManageMembergroups template.
	 * Uses ManageMembers language file.
	 */
	function ModifyMembergroups(): void
	{
		SMF\Actions\Admin\Membergroups::call();
	}

	/**
	 * This function handles adding a membergroup and setting some initial properties.
	 * Called by ?action=admin;area=membergroups;sa=add.
	 * It requires the manage_membergroups permission.
	 * Allows to use a predefined permission profile or copy one from another group.
	 * Redirects to action=admin;area=membergroups;sa=edit;group=x.
	 *
	 * @uses template_new_group()
	 */
	function AddMemberGroup(): void
	{
		SMF\Actions\Admin\Membergroups::subActionProvider(sa: 'add');
	}

	/**
	 * Deleting a membergroup by URL (not implemented).
	 * Called by ?action=admin;area=membergroups;sa=delete;group=x;session_var=y.
	 * Requires the manage_membergroups permission.
	 * Redirects to ?action=admin;area=membergroups.
	 *
	 * @todo look at this
	 */
	function DeleteMembergroup(): void
	{
		SMF\Actions\Admin\Membergroups::subActionProvider(sa: 'delete');
	}

	/**
	 * Editing a membergroup.
	 * Screen to edit a specific membergroup.
	 * Called by ?action=admin;area=membergroups;sa=edit;group=x.
	 * It requires the manage_membergroups permission.
	 * Also handles the delete button of the edit form.
	 * Redirects to ?action=admin;area=membergroups.
	 *
	 * @uses template_edit_group()
	 */
	function EditMembergroup(): void
	{
		SMF\Actions\Admin\Membergroups::subActionProvider(sa: 'edit');
	}

	/**
	 * Shows an overview of the current membergroups.
	 * Called by ?action=admin;area=membergroups.
	 * Requires the manage_membergroups permission.
	 * Splits the membergroups in regular ones and post count based groups.
	 * It also counts the number of members part of each membergroup.
	 *
	 * Uses ManageMembergroups template, main.
	 */
	function MembergroupIndex(): void
	{
		SMF\Actions\Admin\Membergroups::subActionProvider(sa: 'index');
	}

	/**
	 * Set some general membergroup settings and permissions.
	 * Called by ?action=admin;area=membergroups;sa=settings
	 * Requires the admin_forum permission (and manage_permissions for changing permissions)
	 * Redirects to itself.
	 *
	 * @uses template_show_settings()
	 */
	function ModifyMembergroupsettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Membergroups::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/*********************************
	 * Begin SMF\Actions\Admin\Members
	 *********************************/

	/**
	 * The main entrance point for the Manage Members screen.
	 * As everyone else, it calls a function based on the given sub-action.
	 * Called by ?action=admin;area=viewmembers.
	 * Requires the moderate_forum permission.
	 *
	 * Uses ManageMembers template
	 * Uses ManageMembers language file.
	 */
	function ViewMembers(): void
	{
		SMF\Actions\Admin\Members::call();
	}

	/**
	 * View all members list. It allows sorting on several columns, and deletion of
	 * selected members. It also handles the search query sent by
	 * ?action=admin;area=viewmembers;sa=search.
	 * Called by ?action=admin;area=viewmembers;sa=all or ?action=admin;area=viewmembers;sa=query.
	 * Requires the moderate_forum permission.
	 *
	 * Uses a standard list (@see createList())
	 */
	function ViewMemberlist(): void
	{
		SMF\Actions\Admin\Members::subActionProvider(sa: 'all');
	}

	/**
	 * This function handles the approval, rejection, activation or deletion of members.
	 * Called by ?action=admin;area=viewmembers;sa=approve.
	 * Requires the moderate_forum permission.
	 * Redirects to ?action=admin;area=viewmembers;sa=browse
	 * with the same parameters as the calling page.
	 */
	function AdminApprove(): void
	{
		SMF\Actions\Admin\Members::subActionProvider(sa: 'approve');
	}

	/**
	 * List all members who are awaiting approval / activation, sortable on different columns.
	 * It allows instant approval or activation of (a selection of) members.
	 * Called by ?action=admin;area=viewmembers;sa=browse;type=approve
	 *  or ?action=admin;area=viewmembers;sa=browse;type=activate.
	 * The form submits to ?action=admin;area=viewmembers;sa=approve.
	 * Requires the moderate_forum permission.
	 *
	 * @uses template_admin_browse()
	 */
	function MembersAwaitingActivation(): void
	{
		SMF\Actions\Admin\Members::subActionProvider(sa: 'browse');
	}

	/**
	 * Search the member list, using one or more criteria.
	 * Called by ?action=admin;area=viewmembers;sa=search.
	 * Requires the moderate_forum permission.
	 * form is submitted to action=admin;area=viewmembers;sa=query.
	 *
	 * @uses template_search_members()
	 */
	function SearchMembers(): void
	{
		SMF\Actions\Admin\Members::subActionProvider(sa: 'search');
	}

	/******************************
	 * Begin SMF\Actions\Admin\Mods
	 ******************************/

	/**
	 * This my friend, is for all the mod authors out there.
	 */
	function ModifyModSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Mods::subActionProvider(sa: 'general', return_config: $return_config);
	}

	/******************************
	 * Begin SMF\Actions\Admin\News
	 ******************************/

	/**
	 * The news dispatcher; doesn't do anything, just delegates.
	 * This is the entrance point for all News and Newsletter screens.
	 * Called by ?action=admin;area=news.
	 * It does the permission checks, and calls the appropriate function
	 * based on the requested sub-action.
	 */
	function ManageNews(): void
	{
		SMF\Actions\Admin\News::call();
	}

	/**
	 * Prepare subject and message of an email for the preview box
	 * Used in ComposeMailing and RetrievePreview (Xml.php)
	 */
	function prepareMailingForPreview(): void
	{
		SMF\Actions\Admin\News::prepareMailingForPreview();
	}

	/**
	 * Let the administrator(s) edit the news items for the forum.
	 * It writes an entry into the moderation log.
	 * This function uses the edit_news administration area.
	 * Called by ?action=admin;area=news.
	 * Requires the edit_news permission.
	 * Can be accessed with ?action=admin;sa=editnews.
	 *
	 * Uses a standard list (@see createList())
	 */
	function EditNews(): void
	{
		SMF\Actions\Admin\News::subActionProvider(sa: 'edit');
	}

	/**
	 * This function allows a user to select the membergroups to send their
	 * mailing to.
	 * Called by ?action=admin;area=news;sa=mailingmembers.
	 * Requires the send_mail permission.
	 * Form is submitted to ?action=admin;area=news;mailingcompose.
	 *
	 * @uses template_email_members()
	 */
	function SelectMailingMembers(): void
	{
		SMF\Actions\Admin\News::subActionProvider(sa: 'mailingmembers');
	}

	/**
	 * Shows a form to edit a forum mailing and its recipients.
	 * Called by ?action=admin;area=news;sa=mailingcompose.
	 * Requires the send_mail permission.
	 * Form is submitted to ?action=admin;area=news;sa=mailingsend.
	 *
	 * @uses template_email_members_compose()
	 */
	function ComposeMailing(): void
	{
		SMF\Actions\Admin\News::subActionProvider(sa: 'mailingcompose');
	}

	/**
	 * Handles the sending of the forum mailing in batches.
	 * Called by ?action=admin;area=news;sa=mailingsend
	 * Requires the send_mail permission.
	 * Redirects to itself when more batches need to be sent.
	 * Redirects to ?action=admin;area=news;sa=mailingmembers after everything has been sent.
	 * @uses template_email_members_send()
	 *
	 * @param bool $clean_only If set, it will only clean the variables, put them in context, then return.
	 */
	function SendMailing(): void
	{
		SMF\Actions\Admin\News::subActionProvider(sa: 'mailingsend');
	}

	/**
	 * Set general news and newsletter settings and permissions.
	 * Called by ?action=admin;area=news;sa=settings.
	 * Requires the forum_admin permission.
	 * @uses template_show_settings()
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the config_vars array if $return_config is true
	 */
	function ModifyNewsSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\News::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/*************************************
	 * Begin SMF\Actions\Admin\Permissions
	 *************************************/

	/**
	 * Dispatches to the right function based on the given subaction.
	 * Checks the permissions, based on the sub-action.
	 * Called by ?action=managepermissions.
	 *
	 * Uses ManagePermissions language file.
	 */
	function ModifyPermissions(): void
	{
		SMF\Actions\Admin\Permissions::call();
	}

	/**
	 * Set the permission level for a specific profile, group, or group for a profile.
	 *
	 * @internal
	 *
	 * @param string $level The level ('restrict', 'standard', etc.)
	 * @param int $group The group to set the permission for
	 * @param string|int $profile The ID of the permissions profile or 'null' if we're setting it for a group
	 */
	function setPermissionLevel(string $level, int $group, string|int $profile = 'null'): void
	{
		SMF\Actions\Admin\Permissions::setPermissionLevel($level, $group, $profile);
	}

	/**
	 * Initialize a form with inline permissions settings.
	 * It loads a context variable for each permission.
	 * This function is used by several settings screens to set specific permissions.
	 *
	 * To exclude groups from the form for a given permission, add the group IDs as
	 * an array to $context['excluded_permissions'][$permission]. For backwards
	 * compatibility, it is also possible to pass group IDs in via the
	 * $excluded_groups parameter, which will exclude the groups from the forms for
	 * all of the permissions passed in via $permissions.
	 *
	 * @internal
	 *
	 * @param array $permissions The permissions to display inline
	 * @param array $excluded_groups The IDs of one or more groups to exclude
	 *
	 * Uses ManagePermissions language
	 * Uses ManagePermissions template
	 */
	function init_inline_permissions(array $permissions, array $excluded_groups = []): void
	{
		SMF\Actions\Admin\Permissions::init_inline_permissions($permissions, $excluded_groups);
	}

	/**
	 * Show a collapsible box to set a specific permission.
	 * The function is called by templates to show a list of permissions settings.
	 * Calls the template function template_inline_permissions().
	 *
	 * @param string $permission The permission to display inline
	 */
	function theme_inline_permissions(string $permission): void
	{
		SMF\Actions\Admin\Permissions::theme_inline_permissions($permission);
	}

	/**
	 * Save the permissions of a form containing inline permissions.
	 *
	 * @internal
	 *
	 * @param array $permissions The permissions to save
	 */
	function save_inline_permissions(array $permissions): void
	{
		SMF\Actions\Admin\Permissions::save_inline_permissions($permissions);
	}

	/**
	 * Load permissions profiles.
	 */
	function loadPermissionProfiles(): void
	{
		SMF\Actions\Admin\Permissions::loadPermissionProfiles();
	}

	/**
	 * This function updates the permissions of any groups based off this group.
	 *
	 * @param null|array $parents The parent groups
	 * @param null|int $profile the ID of a permissions profile to update
	 * @return ?bool Returns nothing if successful or false if there are no child groups to update
	 */
	function updateChildPermissions(int|array|null $parents = null, ?int $profile = null): ?bool
	{
		return SMF\Actions\Admin\Permissions::updateChildPermissions($parents, $profile);
	}

	/**
	 * Load permissions someone cannot grant.
	 */
	function loadIllegalPermissions(): array
	{
		return SMF\Actions\Admin\Permissions::loadIllegalPermissions();
	}

	/**
	 * Sets up the permissions by membergroup index page.
	 * Called by ?action=managepermissions
	 * Creates an array of all the groups with the number of members and permissions.
	 *
	 * Uses ManagePermissions language file.
	 * Uses ManagePermissions template file.
	 * @uses template_permission_index()
	 */
	function PermissionIndex(): void
	{
		SMF\Actions\Admin\Permissions::subActionProvider(sa: 'index');
	}

	/**
	 * Handle permissions by board... more or less. :P
	 */
	function PermissionsByBoard(): void
	{
		SMF\Actions\Admin\Permissions::subActionProvider(sa: 'board');
	}

	/**
	 * Initializes the necessary to modify a membergroup's permissions.
	 */
	function ModifyMembergroup(): void
	{
		SMF\Actions\Admin\Permissions::subActionProvider(sa: 'modify');
	}

	/**
	 * This function actually saves modifications to a membergroup's board permissions.
	 */
	function ModifyMembergroup2(): void
	{
		SMF\Actions\Admin\Permissions::subActionProvider(sa: 'modify2');
	}

	/**
	 * Handles permission modification actions from the upper part of the
	 * permission manager index.
	 */
	function SetQuickGroups(): void
	{
		SMF\Actions\Admin\Permissions::subActionProvider(sa: 'quick');
	}

	/**
	 * Present a nice way of applying post moderation.
	 */
	function ModifyPostModeration(): void
	{
		SMF\Actions\Admin\Permissions::subActionProvider(sa: 'postmod');
	}

	/**
	 * Add/Edit/Delete profiles.
	 */
	function EditPermissionProfiles(): void
	{
		SMF\Actions\Admin\Permissions::subActionProvider(sa: 'profiles');
	}

	/**
	 * A screen to set some general settings for permissions.
	 *
	 * @param bool $return_config Whether to return the $config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the config_vars array if $return_config is true
	 */
	function GeneralPermissionSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Permissions::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/******************************
	 * Begin SMF\Actions\Admin\Post
	 ******************************/

	/**
	 * The main entrance point for the 'Posts and topics' screen.
	 * Like all others, it checks permissions, then forwards to the right function
	 * based on the given sub-action.
	 * Defaults to sub-action 'posts'.
	 * Accessed from ?action=admin;area=postsettings.
	 * Requires (and checks for) the admin_forum permission.
	 */
	function ManagePostSettings(): void
	{
		SMF\Actions\Admin\Posts::call();
	}

	/**
	 * Modify any setting related to posts and posting.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=posts.
	 * @uses template_show_settings()
	 *
	 * @param bool $return_config Whether or not to return the $config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the config_vars array if $return_config is true
	 */
	function ModifyPostSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Posts::subActionProvider(sa: 'posts', return_config: $return_config);
	}

	/**
	 * Modify any setting related to topics.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=topics.
	 * @uses template_show_settings()
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns $config_vars if $return_config is true
	 */
	function ModifyTopicSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Posts::subActionProvider(sa: 'topics', return_config: $return_config);
	}

	/**
	 * Modify any setting related to drafts.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=postsettings;sa=drafts
	 * @uses template_show_settings()
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyDraftSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Posts::subActionProvider(sa: 'drafts', return_config: $return_config);
	}

	/**************************************
	 * Begin SMF\Actions\Admin\Registration
	 **************************************/

	/**
	 * Entrance point for the registration center, it checks permissions and forwards
	 * to the right function based on the subaction.
	 * Accessed by ?action=admin;area=regcenter.
	 * Requires either the moderate_forum or the admin_forum permission.
	 *
	 * Uses Login language file
	 * Uses Register template.
	 */
	function RegCenter(): void
	{
		SMF\Actions\Admin\Registration::call();
	}

	/**
	 * This function allows the admin to register a new member by hand.
	 * It also allows assigning a primary group to the member being registered.
	 * Accessed by ?action=admin;area=regcenter;sa=register
	 * Requires the moderate_forum permission.
	 *
	 * @uses template_admin_register()
	 */
	function AdminRegister(): void
	{
		SMF\Actions\Admin\Registration::subActionProvider(sa: 'register');
	}

	/**
	 * Allows the administrator to edit the registration agreement, and choose whether
	 * it should be shown or not. It writes and saves the agreement to the agreement.txt
	 * file.
	 * Accessed by ?action=admin;area=regcenter;sa=agreement.
	 * Requires the admin_forum permission.
	 *
	 * @uses template_edit_agreement()
	 */
	function EditAgreement(): void
	{
		SMF\Actions\Admin\Registration::subActionProvider(sa: 'agreement');
	}

	/**
	 * Sure, you can sell my personal info for profit (...or not)
	 */
	function EditPrivacyPolicy(): void
	{
		SMF\Actions\Admin\Registration::subActionProvider(sa: 'policy');
	}

	/**
	 * Set the names under which users are not allowed to register.
	 * Accessed by ?action=admin;area=regcenter;sa=reservednames.
	 * Requires the admin_forum permission.
	 *
	 * @uses template_edit_reserved_words()
	 */
	function SetReserved(): void
	{
		SMF\Actions\Admin\Registration::subActionProvider(sa: 'reservednames');
	}

	/**
	 * This function handles registration settings, and provides a few pretty stats too while it's at it.
	 * General registration settings and Coppa compliance settings.
	 * Accessed by ?action=admin;area=regcenter;sa=settings.
	 * Requires the admin_forum permission.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyRegistrationSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Registration::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/**************************************
	 * Begin SMF\Actions\Admin\RepairBoards
	 **************************************/

	/**
	 * Finds or repairs errors in the database to fix possible problems.
	 * Requires the admin_forum permission.
	 * Calls createSalvageArea() to create a new board, if necessary.
	 * Accessed by ?action=admin;area=repairboards.
	 *
	 * @uses template_repair_boards()
	 */
	function RepairBoards(): void
	{
		SMF\Actions\Admin\RepairBoards::call();
	}

	/*********************************
	 * Begin SMF\Actions\Admin\Reports
	 *********************************/

	/**
	 * Handling function for generating reports.
	 * Requires the admin_forum permission.
	 * Loads the Reports template and language files.
	 * Decides which type of report to generate, if this isn't passed
	 * through the querystring it will set the report_type sub-template to
	 * force the user to choose which type.
	 * When generating a report chooses which sub_template to use.
	 * Depends on the cal_enabled setting, and many of the other cal_
	 * settings.
	 * Will call the relevant report generation function.
	 * If generating report will call finishTables before returning.
	 * Accessed through ?action=admin;area=reports.
	 */
	function ReportsMain(): void
	{
		SMF\Actions\Admin\Reports::call();
	}

	/**
	 * Standard report about what settings the boards have.
	 * functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * they are all called from ReportsMain.
	 * never access the context directly, but use the data handling
	 * functions to do so.
	 */
	function BoardReport(): void
	{
		SMF\Actions\Admin\Reports::subActionProvider(sa: 'boards');
	}

	/**
	 * Generate a report on the current permissions by board and membergroup.
	 * functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * they are all called from ReportsMain.
	 * never access the context directly, but use the data handling
	 * functions to do so.
	 */
	function BoardPermissionsReport(): void
	{
		SMF\Actions\Admin\Reports::subActionProvider(sa: 'board_perms');
	}

	/**
	 * Show what the membergroups are made of.
	 * functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * they are all called from ReportsMain.
	 * never access the context directly, but use the data handling
	 * functions to do so.
	 */
	function MemberGroupsReport(): void
	{
		SMF\Actions\Admin\Reports::subActionProvider(sa: 'member_groups');
	}

	/**
	 * Show the large variety of group permissions assigned to each membergroup.
	 * functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * they are all called from ReportsMain.
	 * never access the context directly, but use the data handling
	 * functions to do so.
	 */
	function GroupPermissionsReport(): void
	{
		SMF\Actions\Admin\Reports::subActionProvider(sa: 'group_perms');
	}

	/**
	 * Report for showing all the forum staff members - quite a feat!
	 * functions ending with "Report" are responsible for generating data
	 * for reporting.
	 * they are all called from ReportsMain.
	 * never access the context directly, but use the data handling
	 * functions to do so.
	 */
	function StaffReport(): void
	{
		SMF\Actions\Admin\Reports::subActionProvider(sa: 'staff');
	}

	/********************************
	 * Begin SMF\Actions\Admin\Search
	 ********************************/

	/**
	 * Main entry point for the admin search settings screen.
	 * It checks permissions, and it forwards to the appropriate function based on
	 * the given sub-action.
	 * Defaults to sub-action 'settings'.
	 * Called by ?action=admin;area=managesearch.
	 * Requires the admin_forum permission.
	 *
	 * Uses ManageSearch template.
	 * Uses Search language file.
	 */
	function ManageSearch(): void
	{
		SMF\Actions\Admin\Search::call();
	}

	/**
	 * Edit some general settings related to the search function.
	 * Called by ?action=admin;area=managesearch;sa=settings.
	 * Requires the admin_forum permission.
	 * @uses template_show_settings()
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function EditSearchSettings(bool $return_config = false): ?array
	{
		SMF\Actions\Admin\Search::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/**
	 * Edit the relative weight of the search factors.
	 * Called by ?action=admin;area=managesearch;sa=weights.
	 * Requires the admin_forum permission.
	 *
	 * @uses template_modify_weights()
	 */
	function EditWeights(): void
	{
		SMF\Actions\Admin\Search::subActionProvider(sa: 'weights');
	}

	/**
	 * Edit the search method and search index used.
	 * Calculates the size of the current search indexes in use.
	 * Allows to create and delete a fulltext index on the messages table.
	 * Allows to delete a custom index (that CreateMessageIndex() created).
	 * Called by ?action=admin;area=managesearch;sa=method.
	 * Requires the admin_forum permission.
	 *
	 * @uses template_select_search_method()
	 */
	function EditSearchMethod(): void
	{
		SMF\Actions\Admin\Search::subActionProvider(sa: 'method');
	}

	/**
	 * Create a custom search index for the messages table.
	 * Called by ?action=admin;area=managesearch;sa=createmsgindex.
	 * Linked from the EditSearchMethod screen.
	 * Requires the admin_forum permission.
	 * Depending on the size of the message table, the process is divided in steps.
	 *
	 * @uses template_create_index()
	 * @uses template_create_index_progress()
	 * @uses template_create_index_done()
	 */
	function CreateMessageIndex(): void
	{
		SMF\Actions\Admin\Search::subActionProvider(sa: 'createmsgindex');
	}

	/***************************************
	 * Begin SMF\Actions\Admin\SearchEngines
	 ***************************************/

	/**
	 * Entry point for this section.
	 */
	function SearchEngines(): void
	{
		SMF\Actions\Admin\SearchEngines::call();
	}

	/**
	 * This function takes any unprocessed hits and turns them into stats.
	 */
	function consolidateSpiderStats(): void
	{
		SMF\Actions\Admin\SearchEngines::consolidateSpiderStats();
	}

	/**
	 * Recache spider names?
	 */
	function recacheSpiderNames(): void
	{
		SMF\Actions\Admin\SearchEngines::recacheSpiderNames();
	}

	/**
	 * Show the spider statistics.
	 */
	function SpiderStats(): void
	{
		SMF\Actions\Admin\SearchEngines::subActionProvider(sa: 'stats');
	}

	/**
	 * See what spiders have been up to.
	 */
	function SpiderLogs(): void
	{
		SMF\Actions\Admin\SearchEngines::subActionProvider(sa: 'logs');
	}

	/**
	 * View a list of all the spiders we know about.
	 */
	function ViewSpiders(): void
	{
		SMF\Actions\Admin\SearchEngines::subActionProvider(sa: 'spiders');
	}

	/**
	 * This is really just the settings page.
	 *
	 * @param bool $return_config Whether to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ManageSearchEngineSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\SearchEngines::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/**
	 * Here we can add, and edit, spider info!
	 */
	function EditSpider(): void
	{
		SMF\Actions\Admin\SearchEngines::subActionProvider(sa: 'editspiders');
	}

	/********************************
	 * Begin SMF\Actions\Admin\Server
	 ********************************/

	/**
	 * This is the main dispatcher. Sets up all the available sub-actions, all the tabs and selects
	 * the appropriate one based on the sub-action.
	 *
	 * Requires the admin_forum permission.
	 * Redirects to the appropriate function based on the sub-action.
	 *
	 * Uses edit_settings adminIndex.
	 */
	function ModifySettings(): void
	{
		SMF\Actions\Admin\Server::call();
	}

	/**
	 * Helper function, it sets up the context for the manage server settings.
	 * - The basic usage of the six numbered key fields are
	 * - array (0 ,1, 2, 3, 4, 5
	 *		0 variable name - the name of the saved variable
	 *		1 label - the text to show on the settings page
	 *		2 saveto - file or db, where to save the variable name - value pair
	 *		3 type - type of data to save, int, float, text, check
	 *		4 size - false or field size
	 *		5 help - '' or helptxt variable name
	 *	)
	 *
	 * the following named keys are also permitted
	 * 'disabled' => A string of code that will determine whether or not the setting should be disabled
	 * 'postinput' => Text to display after the input field
	 * 'preinput' => Text to display before the input field
	 * 'subtext' => Additional descriptive text to display under the field's label
	 * 'min' => minimum allowed value (for int/float). Defaults to 0 if not set.
	 * 'max' => maximum allowed value (for int/float)
	 * 'step' => how much to increment/decrement the value by (only for int/float - mostly used for float values).
	 *
	 * @param array $config_vars An array of configuration variables
	 */
	function prepareServerSettingsContext(&$config_vars)
	{
		SMF\Actions\Admin\Server::prepareServerSettingsContext($config_vars);
	}

	/**
	 * General forum settings - forum name, maintenance mode, etc.
	 * Practically, this shows an interface for the settings in Settings.php to be changed.
	 *
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Contains the actual array of settings to show from Settings.php.
	 * - Accessed from ?action=admin;area=serversettings;sa=general.
	 *
	 * @param bool $return_config Whether to return the $config_vars array (for pagination purposes)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyGeneralSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Server::generalConfigVars();
		}

		SMF\Actions\Admin\Server::load();
		SMF\Actions\Admin\Server::$obj->subaction = 'general';
		SMF\Actions\Admin\Server::$obj->execute();

		return null;
	}

	/**
	 * Basic database and paths settings - database name, host, etc.
	 *
	 * - It shows an interface for the settings in Settings.php to be changed.
	 * - It contains the actual array of settings to show from Settings.php.
	 * - Requires the admin_forum permission.
	 * - Uses the edit_settings administration area.
	 * - Accessed from ?action=admin;area=serversettings;sa=database.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyDatabaseSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Server::databaseConfigVars();
		}

		SMF\Actions\Admin\Server::load();
		SMF\Actions\Admin\Server::$obj->subaction = 'database';
		SMF\Actions\Admin\Server::$obj->execute();

		return null;
	}

	/**
	 * This function handles cookies settings modifications.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyCookieSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Server::cookieConfigVars();
		}

		SMF\Actions\Admin\Server::load();
		SMF\Actions\Admin\Server::$obj->subaction = 'cookie';
		SMF\Actions\Admin\Server::$obj->execute();

		return null;
	}

	/**
	 * Settings really associated with general security aspects.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyGeneralSecuritySettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Server::securityConfigVars();
		}

		SMF\Actions\Admin\Server::load();
		SMF\Actions\Admin\Server::$obj->subaction = 'security';
		SMF\Actions\Admin\Server::$obj->execute();

		return null;
	}

	/**
	 * Simply modifying cache functions
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyCacheSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Server::cacheConfigVars();
		}

		SMF\Actions\Admin\Server::load();
		SMF\Actions\Admin\Server::$obj->subaction = 'cache';
		SMF\Actions\Admin\Server::$obj->execute();

		return null;
	}

	/**
	 * Controls settings for data export functionality
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyExportSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Server::exportConfigVars();
		}

		SMF\Actions\Admin\Server::load();
		SMF\Actions\Admin\Server::$obj->subaction = 'export';
		SMF\Actions\Admin\Server::$obj->execute();

		return null;
	}

	/**
	 * Allows to edit load balancing settings.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyLoadBalancingSettings(bool $return_config = false): ?array
	{
		if (!empty($return_config)) {
			return SMF\Actions\Admin\Server::loadBalancingConfigVars();
		}

		SMF\Actions\Admin\Server::load();
		SMF\Actions\Admin\Server::$obj->subaction = 'loads';
		SMF\Actions\Admin\Server::$obj->execute();

		return null;
	}

	/**
	 * Allows us to see the servers php settings
	 *
	 * - loads the settings into an array for display in a template
	 * - drops cookie values just in case
	 */
	function ShowPHPinfoSettings(): void
	{
		SMF\Actions\Admin\Server::subActionProvider(sa: 'phpinfo');
	}

	/*********************************
	 * Begin SMF\Actions\Admin\Smileys
	 *********************************/

	/**
	 * This is the dispatcher of smileys administration.
	 */
	function ManageSmileys(): void
	{
		SMF\Actions\Admin\Smileys::call();
	}

	/**
	 * Handles modifying smileys settings.
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function EditSmileySettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Smileys::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/**
	 * Add a smiley, that's right.
	 */
	function AddSmiley(): void
	{
		SMF\Actions\Admin\Smileys::subActionProvider(sa: 'addsmiley');
	}

	/**
	 * Add, remove, edit smileys.
	 */
	function EditSmileys(): void
	{
		SMF\Actions\Admin\Smileys::subActionProvider(sa: 'editsmileys');
	}

	/**
	 * Allows to edit smileys order.
	 */
	function EditSmileyOrder(): void
	{
		SMF\Actions\Admin\Smileys::subActionProvider(sa: 'setorder');
	}

	/**
	 * Install a smiley set.
	 */
	function InstallSmileySet(): void
	{
		SMF\Actions\Admin\Smileys::subActionProvider(sa: 'install');
	}

	/**
	 * Handles editing message icons
	 */
	function EditMessageIcons(): void
	{
		SMF\Actions\Admin\Smileys::subActionProvider(sa: 'editsets');
	}

	/***************************************
	 * Begin SMF\Actions\Admin\Subscriptions
	 ***************************************/

	/**
	 * The main entrance point for the 'Paid Subscription' screen, calling
	 * the right function based on the given sub-action.
	 * It defaults to sub-action 'view'.
	 * Accessed from ?action=admin;area=paidsubscribe.
	 * It requires admin_forum permission for admin based actions.
	 */
	function ManagePaidSubscriptions(): void
	{
		SMF\Actions\Admin\Subscriptions::call();
	}

	/**
	 * This just kind of caches all the subscription data.
	 */
	function loadSubscriptions(): array
	{
		return SMF\Actions\Admin\Subscriptions::getSubs();
	}

	/**
	 * Add or extend a subscription of a user.
	 *
	 * @param int $id_subscribe The subscription ID
	 * @param int $id_member The ID of the member
	 * @param int|string $renewal 0 if we're forcing start/end time, otherwise a string indicating how long to renew the subscription for ('D', 'W', 'M' or 'Y')
	 * @param int $forceStartTime If set, forces the subscription to start at the specified time
	 * @param int $forceEndTime If set, forces the subscription to end at the specified time
	 */
	function addSubscription(
		int $id_subscribe,
		int $id_member,
		int|string $renewal = 0,
		int $forceStartTime = 0,
		int $forceEndTime = 0,
	): void {
		SMF\Actions\Admin\Subscriptions::add($id_subscribe, $id_member, $renewal, $forceStartTime, $forceEndTime);
	}

	/**
	 * Removes a subscription from a user, as in removes the groups.
	 *
	 * @param int $id_subscribe The ID of the subscription
	 * @param int $id_member The ID of the member
	 * @param bool $delete Whether to delete the subscription or just disable it
	 */
	function removeSubscription(int $id_subscribe, int $id_member, bool $delete = false): void
	{
		SMF\Actions\Admin\Subscriptions::remove($id_subscribe, $id_member, $delete);
	}

	/**
	 * Reapplies all subscription rules for each of the users.
	 *
	 * @param array $users An array of user IDs
	 */
	function reapplySubscriptions(array $users): void
	{
		SMF\Actions\Admin\Subscriptions::reapply($users);
	}

	/**
	 * Load all the payment gateways.
	 * Checks the Sources directory for any files fitting the format of a payment gateway,
	 * loads each file to check it's valid, includes each file and returns the
	 * function name and whether it should work with this version of SMF.
	 *
	 * @return array An array of information about available payment gateways
	 */
	function loadPaymentGateways(): array
	{
		return SMF\Actions\Admin\Subscriptions::loadPaymentGateways();
	}

	/**
	 * View a list of all the current subscriptions
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=paidsubscribe;sa=view.
	 */
	function ViewSubscriptions(): void
	{
		SMF\Actions\Admin\Subscriptions::subActionProvider(sa: 'view');
	}

	/**
	 * View all the users subscribed to a particular subscription.
	 * Requires the admin_forum permission.
	 * Accessed from ?action=admin;area=paidsubscribe;sa=viewsub.
	 *
	 * Subscription ID is required, in the form of $_GET['sid'].
	 */
	function ViewSubscribedUsers(): void
	{
		SMF\Actions\Admin\Subscriptions::subActionProvider(sa: 'viewsub');
	}

	/**
	 * Adding, editing and deleting subscriptions.
	 * Accessed from ?action=admin;area=paidsubscribe;sa=modify.
	 */
	function ModifySubscription(): void
	{
		SMF\Actions\Admin\Subscriptions::subActionProvider(sa: 'modify');
	}

	/**
	 * Edit or add a user subscription.
	 * Accessed from ?action=admin;area=paidsubscribe;sa=modifyuser.
	 */
	function ModifyUserSubscription(): void
	{
		SMF\Actions\Admin\Subscriptions::subActionProvider(sa: 'modifyuser');
	}

	/**
	 * Set any setting related to paid subscriptions, i.e.
	 * modify which payment methods are to be used.
	 * It requires the moderate_forum permission
	 * Accessed from ?action=admin;area=paidsubscribe;sa=settings.
	 *
	 * @param bool $return_config Whether or not to return the $config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the config_vars array if $return_config is true
	 */
	function ModifySubscriptionSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Subscriptions::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/******************************
	 * Begin SMF\Actions\Admin\Task
	 ******************************/

	/**
	 * Scheduled tasks management dispatcher. This function checks permissions and delegates
	 * to the appropriate function based on the sub-action.
	 * Everything here requires admin_forum permission.
	 *
	 * Uses ManageScheduledTasks template file
	 * Uses ManageScheduledTasks language file
	 */
	function ManageScheduledTasks(): void
	{
		SMF\Actions\Admin\Tasks::call();
	}

	/**
	 * List all the scheduled task in place on the forum.
	 *
	 * @uses template_view_scheduled_tasks()
	 */
	function ScheduledTasks(): void
	{
		SMF\Actions\Admin\Tasks::subActionProvider(sa: 'tasks');
	}

	/**
	 * Function for editing a task.
	 *
	 * @uses template_edit_scheduled_tasks()
	 */
	function EditTask(): void
	{
		SMF\Actions\Admin\Tasks::subActionProvider(sa: 'taskedit');
	}

	/**
	 * Show the log of all tasks that have taken place.
	 *
	 * Uses ManageScheduledTasks language file
	 */
	function TaskLog(): void
	{
		SMF\Actions\Admin\Tasks::subActionProvider(sa: 'tasklog');
	}

	/**
	 * This handles settings related to scheduled tasks
	 *
	 * @param bool $return_config Whether or not to return the config vars. Used in the admin search.
	 * @return ?array If return_config is true, returns the array of $config_vars
	 */
	function TaskSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Tasks::subActionProvider(sa: 'settings', return_config: $return_config);
	}

	/********************************
	 * Begin SMF\Actions\Admin\Themes
	 ********************************/

	/**
	 * Subaction handler - manages the action and delegates control to the proper
	 * sub-action.
	 * It loads both the Themes and Settings language files.
	 * Checks the session by GET or POST to verify the sent data.
	 * Requires the user not be a guest. (@todo what?)
	 * Accessed via ?action=admin;area=theme.
	 */
	function ThemesMain(): void
	{
		SMF\Actions\Admin\Themes::call();
	}

	/**
	 * This function allows administration of themes and their settings,
	 * as well as global theme settings.
	 *  - sets the settings theme_allow, theme_guests, and knownThemes.
	 *  - requires the admin_forum permission.
	 *  - accessed with ?action=admin;area=theme;sa=admin.
	 *
	 * Uses Themes template
	 * Uses Admin language file
	 */
	function ThemeAdmin(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'admin');
	}

	/**
	 * This function lists the available themes and provides an interface to reset
	 * the paths of all the installed themes.
	 */
	function ThemeList(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'list');
	}

	/**
	 * Administrative global settings.
	 */
	function SetThemeOptions(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'options');
	}

	/**
	 * Remove a theme from the database.
	 * - removes an installed theme.
	 * - requires an administrator.
	 * - accessed with ?action=admin;area=theme;sa=remove.
	 */
	function RemoveTheme(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'remove');
	}

	/**
	 * Handles enabling/disabling a theme from the admin center
	 */
	function EnableTheme(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'enable');
	}

	/**
	 * Installs new themes, calls the respective function according to the install type.
	 * - puts themes in $boardurl/Themes.
	 * - assumes the gzip has a root directory in it. (ie default.)
	 * Requires admin_forum.
	 * Accessed with ?action=admin;area=theme;sa=install.
	 */
	function ThemeInstall(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'install');
	}

	/**
	 * Shows an interface for editing the templates.
	 * - uses the Themes template and edit_template/edit_style sub template.
	 * - accessed via ?action=admin;area=theme;sa=edit
	 */
	function EditTheme(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'edit');
	}

	/**
	 * Makes a copy of a template file in a new location
	 *
	 * @uses template_copy_template()
	 */
	function CopyTemplate(): void
	{
		SMF\Actions\Admin\Themes::subActionProvider(sa: 'copy');
	}

	/**********************************
	 * Begin SMF\Actions\Admin\Warnings
	 **********************************/

	/**
	 * Moderation type settings - although there are fewer than we have you believe ;)
	 *
	 * @param bool $return_config Whether or not to return the config_vars array (used for admin search)
	 * @return ?array Returns nothing or returns the $config_vars array if $return_config is true
	 */
	function ModifyWarningSettings(bool $return_config = false): ?array
	{
		return SMF\Actions\Admin\Warnings::subActionProvider(return_config: $return_config);
	}

	/*****************************************
	 * Begin SMF\Actions\Moderation\EndSession
	 *****************************************/

	/**
	 * This ends a moderator session, requiring authentication to access the MCP again.
	 */
	function ModEndSession(): void
	{
		SMF\Actions\Moderation\EndSession::call();
	}

	/***********************************
	 * Begin SMF\Actions\Moderation\Home
	 ***********************************/

	/**
	 * This function basically is the home page of the moderation center.
	 */
	function ModerationHome(): void
	{
		SMF\Actions\Moderation\Home::call();
	}

	/***********************************
	 * Begin SMF\Actions\Moderation\Logs
	 ***********************************/

	/**
	 * Prepares the information from the moderation log for viewing.
	 * Show the moderation log.
	 * If clearing the log, leaves a message in the log to indicate it was cleared, by whom and when.
	 * Requires the admin_forum permission.
	 * Accessed via ?action=moderate;area=modlog.
	 *
	 * Uses Modlog template, main sub-template.
	 */
	function ViewModlog(): void
	{
		SMF\Actions\Moderation\Logs::call();
	}

	/***********************************
	 * Begin SMF\Actions\Moderation\Main
	 ***********************************/

	/**
	 * Entry point for the moderation center.
	 *
	 * @param bool $dont_call If true, just creates the menu and doesn't call
	 *    the function for the appropriate mod area.
	 */
	function ModerationMain(bool $dont_call = false): void
	{
		if ($dont_call) {
			SMF\Actions\Moderation\Main::load()->createMenu();
		} else {
			SMF\Actions\Moderation\Main::call();
		}
	}

	/************************************
	 * Begin SMF\Actions\Moderation\Posts
	 ************************************/

	/**
	 * This is a handling function for all things post moderation.
	 */
	function PostModerationMain(): void
	{
		SMF\Actions\Moderation\Posts::call();
	}

	/**
	 * This is a helper function - basically approve everything!
	 */
	function approveAllData(): void
	{
		SMF\Actions\Moderation\Posts::approveAllData();
	}

	/**
	 * View all unapproved posts.
	 */
	function UnapprovedPosts(): void
	{
		SMF\Actions\Moderation\Posts::subActionProvider(sa: 'replies');
	}

	/**
	 * View all unapproved attachments.
	 */
	function UnapprovedAttachments(): void
	{
		SMF\Actions\Moderation\Posts::subActionProvider(sa: 'attachments');
	}

	/**
	 * Approve a post, just the one.
	 */
	function ApproveMessage(): void
	{
		SMF\Actions\Moderation\Posts::subActionProvider(sa: 'approve');
	}

	/**********************************************
	 * Begin SMF\Actions\Moderation\ReportedContent
	 **********************************************/

	/**
	 * Sets and call a function based on the given subaction. Acts as a dispatcher function.
	 * It requires the moderate_forum permission.
	 *
	 * Uses ModerationCenter template.
	 * Uses ModerationCenter language file.
	 *
	 */
	function ReportedContent(): void
	{
		SMF\Actions\Moderation\ReportedContent::call();
	}

	/**
	 * Recount all open reports. Sets a SESSION var with the updated info.
	 *
	 * @param string $type the type of reports to count
	 * @return int the update open report count.
	 */
	function recountOpenReports(string $type): int
	{
		return SMF\Actions\Moderation\ReportedContent::recountOpenReports($type);
	}

	/**
	 * Shows all currently open reported posts.
	 * Handles closing multiple reports
	 *
	 */
	function ShowReports(): void
	{
		SMF\Actions\Moderation\ReportedContent::subActionProvider(sa: 'show');
	}

	/**
	 * Shows all currently closed reported posts.
	 *
	 */
	function ShowClosedReports(): void
	{
		SMF\Actions\Moderation\ReportedContent::subActionProvider(sa: 'closed');
	}

	/**
	 * Shows detailed information about a report. such as report comments and moderator comments.
	 * Shows a list of moderation actions for the specific report.
	 *
	 */
	function ReportDetails(): void
	{
		SMF\Actions\Moderation\ReportedContent::subActionProvider(sa: 'details');
	}

	/**
	 * Performs closing/ignoring actions for a given report.
	 *
	 */
	function HandleReport(): void
	{
		SMF\Actions\Moderation\ReportedContent::subActionProvider(sa: 'handle');
	}

	/**
	 * Creates/Deletes moderator comments.
	 *
	 */
	function HandleComment(): void
	{
		SMF\Actions\Moderation\ReportedContent::subActionProvider(sa: 'handlecomment');
	}

	/**
	 * Shows a textarea for editing a moderator comment.
	 * Handles the edited comment and stores it on the DB.
	 *
	 */
	function EditComment(): void
	{
		SMF\Actions\Moderation\ReportedContent::subActionProvider(sa: 'editcomment');
	}

	/*****************************************
	 * Begin SMF\Actions\Moderation\ShowNotice
	 *****************************************/

	/**
	 * Show a notice sent to a user.
	 */
	function ShowNotice(): void
	{
		SMF\Actions\Moderation\ShowNotice::call();
	}

	/***************************************
	 * Begin SMF\Actions\Moderation\Warnings
	 ***************************************/

	/**
	 * Entry point for viewing warning related stuff.
	 */
	function ViewWarnings(): void
	{
		SMF\Actions\Moderation\Warnings::call();
	}

	/**
	 * Simply put, look at the warning log!
	 */
	function ViewWarningLog(): void
	{
		SMF\Actions\Moderation\Warnings::subActionProvider(sa: 'log');
	}

	/**
	 * Load all the warning templates.
	 */
	function ViewWarningTemplates(): void
	{
		SMF\Actions\Moderation\Warnings::subActionProvider(sa: 'templates');
	}

	/**
	 * Edit a warning template.
	 */
	function ModifyWarningTemplate(): void
	{
		SMF\Actions\Moderation\Warnings::subActionProvider(sa: 'templateedit');
	}

	/*******************************************
	 * Begin SMF\Actions\Moderation\WatchedUsers
	 *******************************************/

	/**
	 * View watched users.
	 */
	function ViewWatchedUsers(): void
	{
		SMF\Actions\Moderation\WatchedUsers::call();
	}

	/***********************************
	 * Begin SMF\Actions\Profile\Account
	 ***********************************/

	/**
	 * Handles the account section of the profile
	 *
	 * @param int $memID The ID of the member
	 */
	function account(): void
	{
		SMF\Actions\Profile\Account::call();
	}

	/************************************
	 * Begin SMF\Actions\Profile\Activate
	 ************************************/

	/**
	 * Activate an account.
	 *
	 * @param int $memID The ID of the member whose account we're activating
	 */
	function activateAccount(): void
	{
		SMF\Actions\Profile\Activate::call();
	}

	/***************************************
	 * Begin SMF\Actions\Profile\AlertsPopup
	 ***************************************/

	/**
	 * Set up the requirements for the alerts popup - the area that shows all the alerts just quickly for the current user.
	 *
	 * @param int $memID The ID of the member
	 */
	function alerts_popup(): void
	{
		SMF\Actions\Profile\AlertsPopup::call();
	}

	/********************************************
	 * Begin SMF\Actions\Profile\BuddyIgnoreLists
	 ********************************************/

	/**
	 * Show all the users buddies, as well as a add/delete interface.
	 *
	 * @param int $memID The ID of the member
	 */
	function editBuddyIgnoreLists(): void
	{
		SMF\Actions\Profile\BuddyIgnoreLists::call();
	}

	/**
	 * Show all the users buddies, as well as a add/delete interface.
	 *
	 * @param int $memID The ID of the member
	 */
	function editBuddies(int $memID): void
	{
		SMF\Profile::load($memID);
		SMF\Actions\Profile\BuddyIgnoreLists::subActionProvider(sa: 'buddies');
	}

	/**
	 * Allows the user to view their ignore list, as well as the option to manage members on it.
	 *
	 * @param int $memID The ID of the member
	 */
	function editIgnoreList(int $memID): void
	{
		SMF\Profile::load($memID);
		SMF\Actions\Profile\BuddyIgnoreLists::subActionProvider(sa: 'ignore');
	}

	/**********************************
	 * Begin SMF\Actions\Profile\Delete
	 **********************************/

	/**
	 * Present a screen to make sure the user wants to be deleted
	 *
	 * @param int $memID The member ID
	 */
	function deleteAccount(): void
	{
		SMF\Actions\Profile\Delete::call();
	}

	/**
	 * Actually delete an account.
	 *
	 * @param int $memID The member ID
	 */
	function deleteAccount2(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\Delete::load();

		$saving = SMF\Utils::$context['completed_save'];
		SMF\Utils::$context['completed_save'] = true;

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\Delete::$obj->execute();

		SMF\Utils::$context['completed_save'] = $saving;
	}

	/**********************************
	 * Begin SMF\Actions\Profile\Export
	 **********************************/

	/**
	 * Initiates exports a member's profile, posts, and personal messages to a file.
	 *
	 * @todo Add CSV, JSON as other possible export formats besides XML and HTML?
	 *
	 * @param int $uid The ID of the member whose data we're exporting.
	 */
	function export_profile_data(): void
	{
		SMF\Actions\Profile\Export::call();
	}

	/**
	 * Returns the path to a secure directory for storing exported profile data.
	 *
	 * The directory is created if it does not yet exist, and is secured using the
	 * same method that we use to secure attachment directories. Files in this
	 * directory can only be downloaded via the download_export_file() function.
	 *
	 * @return string|bool The path to the directory, or false on error.
	 */
	function create_export_dir(string $fallback = ''): string|bool
	{
		return SMF\Actions\Profile\Export::createDir($fallback);
	}

	/**
	 * Helper function that defines data export formats in a single location.
	 *
	 * @return array Information about supported data formats for profile exports.
	 */
	function get_export_formats(): array
	{
		return SMF\Actions\Profile\Export::getFormats();
	}

	/********************************************
	 * Begin SMF\Actions\Profile\ExportAttachment
	 ********************************************/

	/**
	 * Allows a member to export their attachments.
	 * Mostly just a wrapper for showAttachment() but with a few tweaks.
	 *
	 * @param int $uid The ID of the member whose data we're exporting.
	 */
	function export_attachment(): void
	{
		SMF\Actions\Profile\ExportAttachment::call();
	}

	/******************************************
	 * Begin SMF\Actions\Profile\ExportDownload
	 ******************************************/

	/**
	 * Downloads exported profile data file.
	 *
	 * @param int $uid The ID of the member whose data we're exporting.
	 */
	function download_export_file(): void
	{
		SMF\Actions\Profile\ExportDownload::call();
	}

	/****************************************
	 * Begin SMF\Actions\Profile\ForumProfile
	 ****************************************/

	/**
	 * Handles the main "Forum Profile" section of the profile
	 *
	 * @param int $memID The ID of the member
	 */
	function forumProfile(): void
	{
		SMF\Actions\Profile\ForumProfile::call();
	}

	/*******************************************
	 * Begin SMF\Actions\Profile\GroupMembership
	 *******************************************/

	/**
	 * Function to allow the user to choose group membership etc...
	 *
	 * @param int $memID The ID of the member
	 */
	function groupMembership(): void
	{
		SMF\Actions\Profile\GroupMembership::call();
	}

	/**
	 * This function actually makes all the group changes
	 *
	 * Note: $profile_vars and $post_errors were unused even in 2.1.
	 *
	 * @param array $profile_vars The profile variables. (Ignored.)
	 * @param array $post_errors Any errors that have occurred. (Ignored.)
	 * @param int $memID The ID of the member.
	 * @return string What type of change this is - 'primary' if changing the primary group, 'request' if requesting to join a group or 'free' if it's an open group
	 */
	function groupMembership2(array $profile_vars, array $post_errors, int $memID): string
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\GroupMembership::load();

		$saving = SMF\Utils::$context['completed_save'];
		SMF\Utils::$context['completed_save'] = true;

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\GroupMembership::$obj->execute();

		SMF\Utils::$context['completed_save'] = $saving;

		return SMF\Actions\Profile\GroupMembership::$obj->change_type;
	}

	/****************************************
	 * Begin SMF\Actions\Profile\IgnoreBoards
	 ****************************************/

	/**
	 * Handles the "ignored boards" section of the profile (if enabled)
	 *
	 * @param int $memID The ID of the member
	 */
	function ignoreboards(int $memID): void
	{
		SMF\Profile::load($memID);
		SMF\Actions\Profile\IgnoreBoards::call();
	}

	/****************************************
	 * Begin SMF\Actions\Profile\IssueWarning
	 ****************************************/

	/**
	 * Issue/manage an user's warning status.
	 *
	 * @param int $memID The ID of the user
	 */
	function issueWarning(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\IssueWarning::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\IssueWarning::$obj->execute();
	}

	/********************************
	 * Begin SMF\Actions\Profile\Main
	 ********************************/

	/**
	 * The main designating function for modifying profiles. Loads up info, determins what to do, etc.
	 *
	 * @param array $post_errors Any errors that occurred
	 */
	function ModifyProfile(array $post_errors = []): void
	{
		SMF\Actions\Profile\Main::load();
		SMF\Profile::$member->save_errors = $post_errors;
		SMF\Actions\Profile\Main::$obj->execute();
	}

	/****************************************
	 * Begin SMF\Actions\Profile\Notification
	 ****************************************/

	/**
	 * Display the notifications and settings for changes.
	 *
	 * @param int $memID The ID of the member
	 */
	function notification(): void
	{
		SMF\Actions\Profile\Notification::call();
	}

	/**
	 * Handles configuration of alert preferences
	 *
	 * @param int $memID The ID of the member
	 * @param bool $defaultSettings If true, we are loading default options.
	 */
	function alert_configuration(int $memID, bool $defaultSettings = false): void
	{
		SMF\Actions\Profile\Notification::load();
		SMF\Profile::load($memID);
		SMF\Actions\Profile\Notification::$obj->subaction = 'alerts';
		SMF\Actions\Profile\Notification::$obj->execute();
	}

	/**
	 * Marks all alerts as read for the specified user
	 *
	 * @param int $memID The ID of the member
	 */
	function alert_markread(int $memID): void
	{
		SMF\Actions\Profile\Notification::load();
		SMF\Profile::load($memID);
		SMF\Actions\Profile\Notification::$obj->subaction = 'markread';
		SMF\Actions\Profile\Notification::$obj->execute();
	}

	/**
	 * Handles alerts related to topics and posts
	 *
	 * @param int $memID The ID of the member
	 */
	function alert_notifications_topics(int $memID): void
	{
		SMF\Actions\Profile\Notification::load();
		SMF\Profile::load($memID);
		SMF\Actions\Profile\Notification::$obj->subaction = 'topics';
		SMF\Actions\Profile\Notification::$obj->execute();
	}

	/**
	 * Handles preferences related to board-level notifications
	 *
	 * @param int $memID The ID of the member
	 */
	function alert_notifications_boards(int $memID): void
	{
		SMF\Actions\Profile\Notification::load();
		SMF\Profile::load($memID);
		SMF\Actions\Profile\Notification::$obj->subaction = 'boards';
		SMF\Actions\Profile\Notification::$obj->execute();
	}

	/**
	 * Make any notification changes that need to be made.
	 *
	 * @param int $memID The ID of the member
	 */
	function makeNotificationChanges(int $memID): void
	{
		SMF\Actions\Profile\Notification::load();
		SMF\Profile::load($memID);
		SMF\Actions\Profile\Notification::$obj->changeNotifications();
	}

	/************************************
	 * Begin SMF\Actions\Profile\PaidSubs
	 ************************************/

	/**
	 * Function for doing all the paid subscription stuff - kinda.
	 *
	 * @param int $memID The ID of the user whose subscriptions we're viewing
	 */
	function subscriptions(): void
	{
		SMF\Actions\Profile\PaidSubs::call();
	}

	/*********************************
	 * Begin SMF\Actions\Profile\Popup
	 *********************************/

	/**
	 * Set up the requirements for the profile popup - the area that is shown as the popup menu for the current user.
	 *
	 * @param int $memID The ID of the member
	 */
	function profile_popup(): void
	{
		SMF\Actions\Profile\Popup::call();
	}

	/**************************************
	 * Begin SMF\Actions\Profile\ShowAlerts
	 **************************************/

	/**
	 * Shows all alerts for a member
	 *
	 * @param int $memID The ID of the member
	 */
	function showAlerts(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\ShowAlerts::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\ShowAlerts::$obj->execute();
	}

	/*******************************************
	 * Begin SMF\Actions\Profile\ShowPermissions
	 *******************************************/

	/**
	 * Shows which permissions a user has
	 *
	 * @param int $memID The ID of the member
	 */
	function showPermissions(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\ShowPermissions::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\ShowPermissions::$obj->execute();
	}

	/************************************
	 * Begin SMF\Actions\Profile\ShowPost
	 ************************************/

	/**
	 * Show all posts by a member
	 *
	 * @todo This function needs to be split up properly.
	 *
	 * @param int $memID The ID of the member
	 */
	function showPosts(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\ShowPosts::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\ShowPosts::$obj->execute();
	}

	/**
	 * Show all the unwatched topics.
	 *
	 * @param int $memID The ID of the member
	 */
	function showUnwatched(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\ShowPosts::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\ShowPosts::$obj->subaction = 'unwatchedtopics';
		SMF\Actions\Profile\ShowPosts::$obj->execute();
	}

	/**
	 * Show all the attachments belonging to a member.
	 *
	 * @param int $memID The ID of the member
	 */
	function showAttachments(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\ShowPosts::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\ShowPosts::$obj->subaction = 'attach';
		SMF\Actions\Profile\ShowPosts::$obj->execute();
	}

	/*************************************
	 * Begin SMF\Actions\Profile\StatPanel
	 *************************************/

	/**
	 * Gets the user stats for display
	 *
	 * @param int $memID The ID of the member
	 */
	function statPanel(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\StatPanel::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\StatPanel::$obj->execute();
	}

	/***********************************
	 * Begin SMF\Actions\Profile\Summary
	 ***********************************/

	/**
	 * View a summary.
	 *
	 * @param int $memID The ID of the member
	 */
	function summary(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\Summary::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\Summary::$obj->execute();
	}

	/**************************************
	 * Begin SMF\Actions\Profile\TFADisable
	 **************************************/

	/**
	 * Provides interface to disable two-factor authentication in SMF
	 *
	 * @param int $memID The ID of the member
	 */
	function tfadisable(): void
	{
		SMF\Actions\Profile\TFADisable::call();
	}

	/************************************
	 * Begin SMF\Actions\Profile\TFASetup
	 ************************************/

	/**
	 * Provides interface to setup Two Factor Auth in SMF
	 *
	 * @param int $memID The ID of the member
	 */
	function tfasetup(): void
	{
		SMF\Actions\Profile\TFASetup::call();
	}

	/****************************************
	 * Begin SMF\Actions\Profile\ThemeOptions
	 ****************************************/

	/**
	 * Handles the "Look and Layout" section of the profile
	 *
	 * @param int $memID The ID of the member
	 */
	function theme(): void
	{
		SMF\Actions\Profile\ThemeOptions::call();
	}

	/************************************
	 * Begin SMF\Actions\Profile\Tracking
	 ************************************/

	/**
	 * Loads up the information for the "track user" section of the profile
	 *
	 * @param int $memID The ID of the member
	 */
	function tracking(): void
	{
		SMF\Actions\Profile\Tracking::call();
	}

	/**
	 * Handles tracking a user's activity
	 *
	 * @param int $memID The ID of the member
	 */
	function trackActivity(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\Tracking::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\Tracking::$obj->subaction = 'activity';
		SMF\Actions\Profile\Tracking::$obj->execute();
	}

	/**
	 * Tracks a user's profile edits
	 *
	 * @param int $memID The ID of the member
	 */
	function trackEdits(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\Tracking::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\Tracking::$obj->subaction = 'edits';
		SMF\Actions\Profile\Tracking::$obj->execute();
	}

	/**
	 * Display the history of group requests made by the user whose profile we are viewing.
	 *
	 * @param int $memID The ID of the member
	 */
	function trackGroupReq(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\Tracking::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\Tracking::$obj->subaction = 'groupreq';
		SMF\Actions\Profile\Tracking::$obj->execute();
	}

	/**
	 * Tracks a user's logins.
	 *
	 * @param int $memID The ID of the member
	 */
	function TrackLogins(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\Tracking::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\Tracking::$obj->subaction = 'logins';
		SMF\Actions\Profile\Tracking::$obj->execute();
	}

	/***************************************
	 * Begin SMF\Actions\Profile\ViewWarning
	 ***************************************/

	/**
	 * View a member's warnings
	 *
	 * @param int $memID The ID of the member
	 */
	function viewWarning(int $memID): void
	{
		$u = $_REQUEST['u'] ?? null;
		$_REQUEST['u'] = $memID;

		SMF\Actions\Profile\ViewWarning::load();

		$_REQUEST['u'] = $u;

		SMF\Actions\Profile\ViewWarning::$obj->execute();
	}

	/*****************************
	 * Begin SMF\Actions\Agreement
	 *****************************/

	/**
	 * Let's tell them there's a new agreement
	 */
	function Agreement(): void
	{
		SMF\Actions\Agreement::call();
	}

	/**
	 * Checks whether this user needs to accept the registration agreement.
	 *
	 * @return bool Whether they need to accept the agreement.
	 */
	function canRequireAgreement(): bool
	{
		return SMF\Actions\Agreement::canRequireAgreement();
	}

	/**
	 * Checks whether this user needs to accept the privacy policy.
	 *
	 * @return bool Whether they need to accept the policy.
	 */
	function canRequirePrivacyPolicy(): bool
	{
		return SMF\Actions\Agreement::canRequirePrivacyPolicy();
	}

	/***********************************
	 * Begin SMF\Actions\AgreementAccept
	 ***********************************/

	/**
	 * I solemly swear to no longer chase squirrels.
	 */
	function AcceptAgreement(): void
	{
		SMF\Actions\AgreementAccept::call();
	}

	/****************************
	 * Begin SMF\Actions\Announce
	 ****************************/

	/**
	 * Handle the announce topic function (action=announce).
	 *
	 * checks the topic announcement permissions and loads the announcement template.
	 * requires the announce_topic permission.
	 * uses the ManageMembers template and Post language file.
	 * call the right function based on the sub-action.
	 */
	function AnnounceTopic(): void
	{
		SMF\Actions\Announce::call();
	}

	/**
	 * Allow a user to chose the membergroups to send the announcement to.
	 *
	 * lets the user select the membergroups that will receive the topic announcement.
	 */
	function AnnouncementSelectMembergroup(): void
	{
		SMF\Actions\Announce::subActionProvider(sa: 'selectgroup');
	}

	/**
	 * Send the announcement in chunks.
	 *
	 * splits the members to be sent a topic announcement into chunks.
	 * composes notification messages in all languages needed.
	 * does the actual sending of the topic announcements in chunks.
	 * calculates a rough estimate of the percentage items sent.
	 */
	function AnnouncementSend(): void
	{
		SMF\Actions\Announce::subActionProvider(sa: 'send');
	}

	/*************************************
	 * Begin SMF\Actions\AttachmentApprove
	 *************************************/

	/**
	 * Called from a mouse click, works out what we want to do with attachments and actions it.
	 */
	function ApproveAttach(): void
	{
		SMF\Actions\AttachmentApprove::call();
	}

	/**************************************
	 * Begin SMF\Actions\AttachmentDownload
	 **************************************/

	/**
	 * Downloads an avatar or attachment based on $_GET['attach'], and increments the download count.
	 * It requires the view_attachments permission.
	 * It disables the session parser, and clears any previous output.
	 * It depends on the attachmentUploadDir setting being correct.
	 * It is accessed via the query string ?action=dlattach.
	 * Views to attachments do not increase hits and are not logged in the "Who's Online" log.
	 */
	function showAttachment(): void
	{
		SMF\Actions\AttachmentDownload::call();
	}

	/*******************************
	 * Begin SMF\Actions\AutoSuggest
	 *******************************/

	/**
	 * This keeps track of all registered handling functions for auto suggest functionality and passes execution to them.
	 *
	 * @param bool $checkRegistered If set to something other than null, checks whether the callback function is registered
	 * @return ?bool Returns whether the callback function is registered if $checkRegistered isn't null
	 */
	function AutoSuggestHandler(?string $suggest_type = null): ?bool
	{
		if (isset($suggest_type)) {
			return SMF\Actions\AutoSuggest::checkRegistered($suggest_type);
		}

		SMF\Actions\AutoSuggest::call();
	}

	/**
	 * Search for a member - by real_name or member_name by default.
	 *
	 * @return array An array of information for displaying the suggestions
	 */
	function AutoSuggest_Search_Member(): void
	{
		SMF\Actions\AutoSuggest::load();
		SMF\Actions\AutoSuggest::$obj->suggest_type = 'member';
		SMF\Actions\AutoSuggest::$obj->execute();
	}

	/**
	 * Search for a membergroup by name
	 *
	 * @return array An array of information for displaying the suggestions
	 */
	function AutoSuggest_Search_MemberGroups(): void
	{
		SMF\Actions\AutoSuggest::load();
		SMF\Actions\AutoSuggest::$obj->suggest_type = 'membergroups';
		SMF\Actions\AutoSuggest::$obj->execute();
	}

	/**
	 * Provides a list of possible SMF versions to use in emulation
	 *
	 * @return array An array of data for displaying the suggestions
	 */
	function AutoSuggest_Search_SMFVersions(): void
	{
		SMF\Actions\AutoSuggest::load();
		SMF\Actions\AutoSuggest::$obj->suggest_type = 'versions';
		SMF\Actions\AutoSuggest::$obj->execute();
	}

	/******************************
	 * Begin SMF\Actions\BoardIndex
	 ******************************/

	/**
	 * This function shows the board index.
	 * It uses the BoardIndex template, and main sub template.
	 * It updates the most online statistics.
	 * It is accessed by ?action=boardindex.
	 */
	function BoardIndex(): SMF\Actions\BoardIndex
	{
		return SMF\Actions\BoardIndex::load();
	}

	/**
	 * Fetches a list of boards and (optional) categories including
	 * statistical information, child boards and moderators.
	 * 	- Used by both the board index (main data) and the message index (child
	 * boards).
	 * 	- Depending on the include_categories setting returns an associative
	 * array with categories->boards->child_boards or an associative array
	 * with boards->child_boards.
	 *
	 * @param array $board_index_options An array of boardindex options
	 * @return array An array of information for displaying the boardindex
	 */
	function getBoardIndex(array $board_index_options): array
	{
		return SMF\Actions\BoardIndex::get($board_index_options);
	}

	/***********************************
	 * Begin SMF\Actions\BuddyListToggle
	 ***********************************/

	/**
	 * This simple function adds/removes the passed user from the current users buddy list.
	 * Requires profile_identity_own permission.
	 * Called by ?action=buddy;u=x;session_id=y.
	 * Redirects to ?action=profile;u=x.
	 */
	function BuddyListToggle(): void
	{
		SMF\Actions\BuddyListToggle::call();
	}

	/****************************
	 * Begin SMF\Actions\Calendar
	 ****************************/

	/**
	 * Show the calendar.
	 * It loads the specified month's events, holidays, and birthdays.
	 * It requires the calendar_view permission.
	 * It depends on the cal_enabled setting, and many of the other cal_ settings.
	 * It uses the calendar_start_day theme option. (Monday/Sunday)
	 * It uses the main sub template in the Calendar template.
	 * It goes to the month and year passed in 'month' and 'year' by get or post.
	 * It is accessed through ?action=calendar.
	 */
	function CalendarMain(): void
	{
		SMF\Actions\Calendar::call();
	}

	/**
	 * This function offers up a download of an event in iCal 2.0 format.
	 *
	 * Follows the conventions in {@link https://tools.ietf.org/html/rfc5546 RFC5546}
	 * Sets events as all day events since we don't have hourly events
	 * Will honor and set multi day events
	 * Sets a sequence number if the event has been modified
	 *
	 * @todo .... allow for week or month export files as well?
	 */
	function iCalDownload(): void
	{
		SMF\Actions\Calendar::subActionProvider(sa: 'ical');
	}

	/**
	 * This function processes posting/editing/deleting a calendar event.
	 *
	 * 	- calls {@link Post.php|Post() Post()} function if event is linked to a post.
	 *  - calls {@link Subs-Calendar.php|insertEvent() insertEvent()} to insert the event if not linked to post.
	 *
	 * It requires the calendar_post permission to use.
	 * It uses the event_post sub template in the Calendar template.
	 * It is accessed with ?action=calendar;sa=post.
	 */
	function CalendarPost(): void
	{
		SMF\Actions\Calendar::subActionProvider(sa: 'post');
	}

	/**
	 * Get all birthdays within the given time range.
	 * finds all the birthdays in the specified range of days.
	 * works with birthdays set for no year, or any other year, and respects month and year boundaries.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @return array An array of days, each of which is an array of birthday information for the context
	 */
	function getBirthdayRange(string $low_date, string $high_date): array
	{
		return SMF\Actions\Calendar::getBirthdayRange($low_date, $high_date);
	}

	/**
	 * Get all calendar events within the given time range.
	 *
	 * - finds all the posted calendar events within a date range.
	 * - both the earliest_date and latest_date should be in the standard YYYY-MM-DD format.
	 * - censors the posted event titles.
	 * - uses the current user's permissions if use_permissions is true, otherwise it does nothing "permission specific"
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @param bool $use_permissions Whether to use permissions
	 * @return array Contextual information if use_permissions is true, and an array of the data needed to build that otherwise
	 */
	function getEventRange(string $low_date, string $high_date, bool $use_permissions = true): array
	{
		return SMF\Actions\Calendar::getEventRange($low_date, $high_date, $use_permissions);
	}

	/**
	 * Get all holidays within the given time range.
	 *
	 * @param string $low_date The low end of the range, inclusive, in YYYY-MM-DD format
	 * @param string $high_date The high end of the range, inclusive, in YYYY-MM-DD format
	 * @return array An array of days, which are all arrays of holiday names.
	 */
	function getHolidayRange(string $low_date, string $high_date): array
	{
		return SMF\Actions\Calendar::getHolidayRange($low_date, $high_date);
	}

	/**
	 * Does permission checks to see if an event can be linked to a board/topic.
	 * checks if the current user can link the current topic to the calendar, permissions et al.
	 * this requires the calendar_post permission, a forum moderator, or a topic starter.
	 * expects the $topic and $board variables to be set.
	 * if the user doesn't have proper permissions, an error will be shown.
	 */
	function canLinkEvent(): void
	{
		SMF\Actions\Calendar::canLinkEvent();
	}

	/**
	 * Returns date information about 'today' relative to the users time offset.
	 * returns an array with the current date, day, month, and year.
	 * takes the users time offset into account.
	 *
	 * @return array An array of info about today, based on forum time. Has 'day', 'month', 'year' and 'date' (in YYYY-MM-DD format)
	 */
	function getTodayInfo(): array
	{
		return SMF\Actions\Calendar::getTodayInfo();
	}

	/**
	 * Provides information (link, month, year) about the previous and next month.
	 *
	 * @param string $selected_date A date in YYYY-MM-DD format
	 * @param array $calendarOptions An array of calendar options
	 * @param bool $is_previous Whether this is the previous month
	 * @param bool $has_picker Wheter to add javascript to handle a date picker
	 * @return array A large array containing all the information needed to show a calendar grid for the given month
	 */
	function getCalendarGrid(
		string $selected_date,
		array $calendarOptions,
		bool $is_previous = false,
		bool $has_picker = true,
	): array {
		return SMF\Actions\Calendar::getCalendarGrid(
			$selected_date,
			$calendarOptions,
			$is_previous,
			$has_picker,
		);
	}

	/**
	 * Returns the information needed to show a calendar for the given week.
	 *
	 * @param string $selected_date A date in YYYY-MM-DD format
	 * @param array $calendarOptions An array of calendar options
	 * @return array An array of information needed to display the grid for a single week on the calendar
	 */
	function getCalendarWeek(string $selected_date, array $calendarOptions): array
	{
		return SMF\Actions\Calendar::getCalendarWeek($selected_date, $calendarOptions);
	}

	/**
	 * Returns the information needed to show a list of upcoming events, birthdays, and holidays on the calendar.
	 *
	 * @param string $start_date The start of a date range in YYYY-MM-DD format
	 * @param string $end_date The end of a date range in YYYY-MM-DD format
	 * @param array $calendarOptions An array of calendar options
	 * @return array An array of information needed to display a list of upcoming events, etc., on the calendar
	 */
	function getCalendarList(string $start_date, string $end_date, array $calendarOptions): array
	{
		return SMF\Actions\Calendar::getCalendarList($start_date, $end_date, $calendarOptions);
	}

	/**
	 * Loads the necessary JavaScript and CSS to create a datepicker.
	 *
	 * @param string $selector A CSS selector for the input field(s) that the datepicker should be attached to.
	 * @param string $date_format The date format to use, in strftime() format.
	 */
	function loadDatePicker(string $selector = 'input.date_input', string $date_format = ''): void
	{
		// SMF 3.0 no longer uses a date picker script.
	}

	/**
	 * Loads the necessary JavaScript and CSS to create a timepicker.
	 *
	 * @param string $selector A CSS selector for the input field(s) that the timepicker should be attached to.
	 * @param string $time_format A time format in strftime format
	 */
	function loadTimePicker(string $selector = 'input.time_input', string $time_format = ''): void
	{
		// SMF 3.0 no longer uses a time picker script.
	}

	/**
	 * Loads the necessary JavaScript for Datepair.js.
	 *
	 * Datepair.js helps to keep date ranges sane in the UI.
	 *
	 * @param string $container CSS selector for the containing element of the date/time inputs to be paired.
	 * @param string $date_class The CSS class of the date inputs to be paired.
	 * @param string $time_class The CSS class of the time inputs to be paired.
	 */
	function loadDatePair(string $container, string $date_class = '', string $time_class = ''): void
	{
		// SMF 3.0 no longer uses a date pair script.
	}

	/**
	 * Retrieve all events for the given days, independently of the users offset.
	 * cache callback function used to retrieve the birthdays, holidays, and events between now and now + days_to_index.
	 * widens the search range by an extra 24 hours to support time offset shifts.
	 * used by the cache_getRecentEvents function to get the information needed to calculate the events taking the users time offset into account.
	 *
	 * @param array $eventOptions With the keys 'num_days_shown', 'include_holidays', 'include_birthdays' and 'include_events'
	 * @return array An array containing the data that was cached as well as an expression to calculate whether the data should be refreshed and when it expires
	 */
	function cache_getOffsetIndependentEvents(array $eventOptions): array
	{
		return SMF\Actions\Calendar::cache_getOffsetIndependentEvents($eventOptions);
	}

	/**
	 * cache callback function used to retrieve the upcoming birthdays, holidays, and events within the given period, taking into account the users time offset.
	 * Called from the BoardIndex to display the current day's events on the board index
	 * used by the board index and SSI to show the upcoming events.
	 *
	 * @param array $eventOptions An array of event options.
	 * @return array An array containing the info that was cached as well as a few other relevant things
	 */
	function cache_getRecentEvents(array $eventOptions): array
	{
		return SMF\Actions\Calendar::cache_getRecentEvents($eventOptions);
	}

	/**
	 * Makes sure the calendar post is valid.
	 */
	function validateEventPost(): void
	{
		SMF\Actions\Calendar::validateEventPost();
	}

	/**
	 * Get the event's poster.
	 *
	 * @param int $event_id The ID of the event
	 * @return int|bool The ID of the poster or false if the event was not found
	 */
	function getEventPoster(int $event_id): int|bool
	{
		return SMF\Actions\Calendar::getEventPoster($event_id);
	}

	/**
	 * Remove a holiday from the calendar
	 *
	 * @param array $holiday_ids An array of IDs of holidays to delete
	 */
	function removeHolidays(array $holiday_ids): void
	{
		foreach ($holiday_ids as $holiday_id) {
			Calendar\Holiday::remove($holiday_id);
		}
	}

	/*****************************
	 * Begin SMF\Actions\CoppaForm
	 *****************************/

	/**
	 * This function will display the contact information for the forum, as well a form to fill in.
	 */
	function CoppaForm(): void
	{
		SMF\Actions\CoppaForm::call();
	}

	/***************************
	 * Begin SMF\Actions\Credits
	 ***************************/

	/**
	 * It prepares credit and copyright information for the credits page or the admin page
	 *
	 * @param bool $in_admin = false, if parameter is true the it will not load the sub-template nor the template file
	 */
	function Credits(bool $in_admin = false): void
	{
		SMF\Actions\Credits::call($in_admin);
	}

	/***************************
	 * Begin SMF\Actions\Display
	 ***************************/

	/**
	 * The central part of the board - topic display.
	 * This function loads the posts in a topic up so they can be displayed.
	 * It uses the main sub template of the Display template.
	 * It requires a topic, and can go to the previous or next topic from it.
	 * It jumps to the correct post depending on a number/time/IS_MSG passed.
	 * It depends on the messages_per_page, defaultMaxMessages and enableAllMessages settings.
	 * It is accessed by ?topic=id_topic.START.
	 */
	function Display(): void
	{
		SMF\Actions\Display::call();
	}

	/************************************
	 * Begin SMF\Actions\DisplayAdminFile
	 ************************************/

	/**
	 * Get one of the admin information files from Simple Machines.
	 */
	function DisplayAdminFile(): void
	{
		SMF\Actions\DisplayAdminFile::call();
	}

	/************************
	 * Begin SMF\Actions\Feed
	 ************************/

	/**
	 * Outputs xml data representing recent information or a profile.
	 *
	 * Can be passed subactions which decide what is output:
	 *  'recent' for recent posts,
	 *  'news' for news topics,
	 *  'members' for recently registered members,
	 *  'profile' for a member's profile.
	 *  'posts' for a member's posts.
	 *  'personal_messages' for a member's personal messages.
	 *
	 * When displaying a member's profile or posts, the u parameter identifies which member. Defaults
	 * to the current user's id.
	 * To display a member's personal messages, the u parameter must match the id of the current user.
	 *
	 * Outputs can be in RSS 0.92, RSS 2, Atom, RDF, or our own custom XML format. Default is RSS 2.
	 *
	 * Accessed via ?action=.xml.
	 *
	 * Does not use any templates, sub templates, or template layers.
	 *
	 * Uses Stats, Profile, Post, and PersonalMessage language files.
	 */
	function ShowXmlFeed(): void
	{
		SMF\Actions\Feed::call();
	}

	/**
	 * Builds the XML from the data.
	 *
	 * Returns an array containing three parts: the feed's header section, its
	 * items section, and its footer section. For convenience, the array is also
	 * made available as SMF\Utils::$context['feed'].
	 *
	 * This method is static for the sake of the ExportProfileData task, which
	 * needs to do a lot of custom manipulation of the XML.
	 *
	 * @param string $format A supported feed format.
	 * @param array $data Structured data to build as XML.
	 * @param array $metadata Metadata about the feed.
	 * @param string $subaction The sub-action that was requested.
	 * @return array The feed's header, items, and footer.
	 */
	function buildXmlFeed(string $format, array $data, array $metadata, string $subaction): array
	{
		return SMF\Actions\Feed::build($format, $data, $metadata, $subaction);
	}

	/**
	 * Ensures supplied data is properly encapsulated in cdata xml tags
	 * Called from getXmlProfile in News.php
	 *
	 * @param string $data XML data
	 * @param string $ns A namespace prefix for the XML data elements (used by mods, maybe)
	 * @param bool $force If true, enclose the XML data in cdata tags no matter what (used by mods, maybe)
	 * @return string The XML data enclosed in cdata tags when necessary
	 */
	function cdata_parse(string $data, string $ns = '', bool $force = false): string
	{
		return SMF\Actions\Feed::cdataParse($data, $ns, $force);
	}

	/******************************
	 * Begin SMF\Actions\FindMember
	 ******************************/

	/**
	 * Called by index.php?action=findmember.
	 * - is used as a popup for searching members.
	 * - uses sub template find_members of the Help template.
	 * - also used to add members for PM's sent using wap2/imode protocol.
	 */
	function JSMembers(): void
	{
		SMF\Actions\FindMember::call();
	}

	/**************************
	 * Begin SMF\Actions\Groups
	 **************************/

	/**
	 * Entry point function, permission checks, admin bars, etc.
	 * It allows moderators and users to access the group showing functions.
	 * It handles permission checks, and puts the moderation bar on as required.
	 */
	function Groups(): void
	{
		SMF\Actions\Groups::call();
	}

	/**
	 * Gets the members of a supplied membergroup
	 * Returns them as a link for display
	 *
	 * @param array &$members The IDs of the members
	 * @param int $membergroup The ID of the group
	 * @param int $limit How many members to show (null for no limit)
	 * @return bool True if there are more members to display, false otherwise
	 */
	function listMembergroupMembers_Href(array &$members, int $membergroup, ?int $limit = null): bool
	{
		return SMF\Actions\Groups::listMembergroupMembers_Href($members, $membergroup, $limit);
	}

	/**
	 * This very simply lists the groups, nothing snazy.
	 */
	function GroupList(): void
	{
		SMF\Actions\Groups::subActionProvider(sa: 'index');
	}

	/**
	 * Display members of a group, and allow adding of members to a group. Silly function name though ;)
	 * It can be called from ManageMembergroups if it needs templating within the admin environment.
	 * It shows a list of members that are part of a given membergroup.
	 * It is called by ?action=moderate;area=viewgroups;sa=members;group=x
	 * It requires the manage_membergroups permission.
	 * It allows to add and remove members from the selected membergroup.
	 * It allows sorting on several columns.
	 * It redirects to itself.
	 *
	 * @uses template_group_members()
	 * @todo: use createList
	 */
	function MembergroupMembers(): void
	{
		SMF\Actions\Groups::subActionProvider(sa: 'members');
	}

	/**
	 * Show and manage all group requests.
	 */
	function GroupRequests(): void
	{
		SMF\Actions\Groups::subActionProvider(sa: 'requests');
	}

	/************************
	 * Begin SMF\Actions\Help
	 ************************/

	/**
	 * Redirect to the user help ;).
	 * It loads information needed for the help section.
	 * It is accessed by ?action=help.
	 *
	 * Uses Help template and Manual language file.
	 */
	function ShowHelp(): void
	{
		SMF\Actions\Help::call();
	}

	/**
	 * The main page for the Help section
	 */
	function HelpIndex(): void
	{
		SMF\Actions\Help::subActionProvider(sa: 'index');
	}

	/*****************************
	 * Begin SMF\Actions\HelpAdmin
	 *****************************/

	/**
	 * Show some of the more detailed help to give the admin an idea...
	 * It shows a popup for administrative or user help.
	 * It uses the help parameter to decide what string to display and where to get
	 * the string from. ($helptxt or $txt?)
	 * It is accessed via ?action=helpadmin;help=?.
	 *
	 * Uses ManagePermissions language file, if the help starts with permissionhelp.
	 * @uses template_popup() with no layers.
	 */
	function ShowAdminHelp(): void
	{
		SMF\Actions\HelpAdmin::call();
	}

	/************************************
	 * Begin SMF\Actions\JavaScriptModify
	 ************************************/

	/**
	 * Used to edit the body or subject of a message inline
	 * called from action=jsmodify from script and topic js
	 */
	function JavaScriptModify(): void
	{
		SMF\Actions\JavaScriptModify::call();
	}

	/*************************
	 * Begin SMF\Actions\Login
	 *************************/

	/**
	 * Ask them for their login information. (shows a page for the user to type
	 *  in their username and password.)
	 *  It caches the referring URL in $_SESSION['login_url'].
	 *  It is accessed from ?action=login.
	 *
	 * Uses Login template and language file with the login sub-template.
	 */
	function Login(): void
	{
		SMF\Actions\Login::call();
	}

	/**************************
	 * Begin SMF\Actions\Login2
	 **************************/

	/**
	 * Actually logs you in.
	 * What it does:
	 * - checks credentials and checks that login was successful.
	 * - it employs protection against a specific IP or user trying to brute force
	 *  a login to an account.
	 * - upgrades password encryption on login, if necessary.
	 * - after successful login, redirects you to $_SESSION['login_url'].
	 * - accessed from ?action=login2, by forms.
	 * On error, uses the same templates Login() uses.
	 */
	function Login2(): void
	{
		SMF\Actions\Login2::call();
	}

	/**
	 * This protects against brute force attacks on a member's password.
	 * Importantly, even if the password was right we DON'T TELL THEM!
	 *
	 * @param int $id_member The ID of the member
	 * @param string $member_name The name of the member.
	 * @param bool|string $password_flood_value False if we don't have a flood value, otherwise a string with a timestamp and number of tries separated by a |
	 * @param bool $was_correct Whether or not the password was correct
	 * @param bool $tfa Whether we're validating for two-factor authentication
	 */
	function validatePasswordFlood(
		int $id_member,
		string $member_name,
		bool|string $password_flood_value = false,
		bool $was_correct = false,
		bool $tfa = false,
	): void {
		SMF\Actions\Login2::validatePasswordFlood(
			$id_member,
			$member_name,
			$password_flood_value,
			$was_correct,
			$tfa,
		);
	}

	/****************************
	 * Begin SMF\Actions\LoginTFA
	 ****************************/

	/**
	 * Allows the user to enter their Two-Factor Authentication code
	 */
	function LoginTFA(): void
	{
		SMF\Actions\LoginTFA::call();
	}

	/**************************
	 * Begin SMF\Actions\Logout
	 **************************/

	/**
	 * Logs the current user out of their account.
	 * It requires that the session hash is sent as well, to prevent automatic logouts by images or javascript.
	 * It redirects back to $_SESSION['logout_url'], if it exists.
	 * It is accessed via ?action=logout;session_var=...
	 *
	 * @param bool $internal If true, it doesn't check the session
	 * @param bool $redirect Whether or not to redirect the user after they log out
	 */
	function Logout(): void
	{
		SMF\Actions\Logout::call();
	}

	/****************************
	 * Begin SMF\Actions\MarkRead
	 ****************************/

	/**
	 * Mark one or more boards as read.
	 */
	function MarkRead(): void
	{
		SMF\Actions\MarkRead::call();
	}

	/******************************
	 * Begin SMF\Actions\Memberlist
	 ******************************/

	/**
	 * Shows a listing of registered members.
	 * - If a subaction is not specified, lists all registered members.
	 * - It allows searching for members with the 'search' sub action.
	 * - It calls MLAll or MLSearch depending on the sub action.
	 * - Requires the view_mlist permission.
	 * - Accessed via ?action=mlist.
	 *
	 * Uses Memberlist template, main sub template.
	 */
	function Memberlist(): void
	{
		SMF\Actions\Memberlist::call();
	}

	/**
	 * List all members, page by page, with sorting.
	 * Called from MemberList().
	 * Can be passed a sort parameter, to order the display of members.
	 * Calls printMemberListRows to retrieve the results of the query.
	 */
	function MLAll(): void
	{
		SMF\Actions\Memberlist::subActionProvider(sa: 'all');
	}

	/**
	 * Search for members, or display search results.
	 * - Called by MemberList().
	 * - If variable 'search' is empty displays search dialog box, using the search sub template.
	 * - Calls printMemberListRows to retrieve the results of the query.
	 */
	function MLSearch(): void
	{
		SMF\Actions\Memberlist::subActionProvider(sa: 'search');
	}

	/**
	 * Retrieves results of the request passed to it
	 * Puts results of request into the context for the sub template.
	 *
	 * @param resource $request An SQL result resource
	 */
	function printMemberListRows($request): void
	{
		SMF\Actions\Memberlist::printRows($request);
	}

	/**
	 * Sets the label, sort and join info for every custom field column.
	 *
	 * @return array An array of info about the custom fields for the member list
	 */
	function getCustFieldsMList(): array
	{
		return SMF\Actions\Memberlist::getCustFields();
	}

	/********************************
	 * Begin SMF\Actions\MessageIndex
	 ********************************/

	/**
	 * Show the list of topics in this board, along with any child boards.
	 */
	function MessageIndex(): void
	{
		SMF\Actions\MessageIndex::call();
	}

	/**
	 * Generates the query to determine the list of available boards for a user
	 * Executes the query and returns the list
	 *
	 * @param array $boardListOptions An array of options for the board list
	 * @return array An array of board info
	 */
	function getBoardList(array $boardListOptions = []): array
	{
		return SMF\Actions\MessageIndex::getBoardList($boardListOptions);
	}

	/*****************************
	 * Begin SMF\Actions\MsgDelete
	 *****************************/

	/**
	 * Remove just a single post.
	 * On completion redirect to the topic or to the board.
	 */
	function DeleteMessage(): void
	{
		SMF\Actions\MsgDelete::call();
	}

	/**************************
	 * Begin SMF\Actions\Notify
	 **************************/

	/**
	 * Fetches the list of preferences (or a single/subset of preferences) for
	 * notifications for one or more users.
	 *
	 * @param int|array $members A user id or an array of (integer) user ids to load preferences for
	 * @param string|array $prefs An empty string to load all preferences, or a string (or array) of preference name(s) to load
	 * @param bool $process_default Whether to apply the default values to the members' values or not.
	 * @return array An array of user ids => array (pref name -> value), with user id 0 representing the defaults
	 */
	function getNotifyPrefs(int|array $members, string|array $prefs = '', bool $process_defaults = false): array
	{
		return SMF\Actions\Notify::getNotifyPrefs($members, $prefs, $process_defaults);
	}

	/**
	 * Sets the list of preferences for a single user.
	 *
	 * @param int $memID The user whose preferences you are setting
	 * @param array $prefs An array key of pref -> value
	 */
	function setNotifyPrefs(int $memID, array $prefs = [])
	{
		return SMF\Actions\Notify::setNotifyPrefs($memID, $prefs);
	}

	/**
	 * Deletes notification preference
	 *
	 * @param int $memID The user whose preference you're setting
	 * @param array $prefs The preferences to delete
	 */
	function deleteNotifyPrefs(int $memID, array $prefs)
	{
		return SMF\Actions\Notify::deleteNotifyPrefs($memID, $prefs);
	}

	/**
	 * Verifies a member's unsubscribe token, then returns some member info
	 *
	 * @param string $type The type of notification the token is for (e.g. 'board', 'topic', etc.)
	 * @return array The id and email address of the specified member
	 */
	function getMemberWithToken(string $type): array
	{
		return SMF\Actions\Notify::getMemberWithToken($type);
	}

	/**
	 * Builds an unsubscribe token
	 *
	 * @param int $memID The id of the member that this token is for
	 * @param string $email The member's email address
	 * @param string $type The type of notification the token is for (e.g. 'board', 'topic', etc.)
	 * @param int $itemID The id of the notification item, if applicable.
	 * @return string The unsubscribe token
	 */
	function createUnsubscribeToken(int $memID, string $email, string $type = '', int $itemID = 0): string
	{
		return SMF\Actions\Notify::createUnsubscribeToken($memID, $email, $type, $itemID);
	}

	/***************************************
	 * Begin SMF\Actions\NotifyAnnouncements
	 ***************************************/

	/**
	 * Turn off/on notifications for announcements.
	 * Only uses the template if no mode was given.
	 * Accessed via ?action=notifyannouncements.
	 */
	function AnnouncementsNotify(): void
	{
		SMF\Actions\NotifyAnnouncements::call();
	}

	/*******************************
	 * Begin SMF\Actions\NotifyBoard
	 *******************************/

	/**
	 * Turn off/on notification for a particular board.
	 * Must be called with a board specified in the URL.
	 * Only uses the template if no mode (or subaction) was given.
	 * Redirects the user back to the board after it is done.
	 * Accessed via ?action=notifyboard.
	 *
	 * @uses template_notify_board()
	 */
	function BoardNotify(): void
	{
		SMF\Actions\NotifyBoard::call();
	}

	/*******************************
	 * Begin SMF\Actions\NotifyTopic
	 *******************************/

	/**
	 * Turn off/on unread replies subscription for a topic as well as sets individual topic's alert preferences
	 * Must be called with a topic specified in the URL.
	 * The mode can be from 0 to 3
	 * 0 => unwatched, 1 => no alerts/emails, 2 => alerts, 3 => emails/alerts
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=notifytopic.
	 */
	function TopicNotify(): void
	{
		SMF\Actions\NotifyTopic::call();
	}

	/***********************************
	 * Begin SMF\Actions\PersonalMessage
	 ***********************************/

	/**
	 * This helps organize things...
	 *
	 * @todo this should be a simple dispatcher....
	 */
	function MessageMain(): void
	{
		SMF\Actions\PersonalMessage::call();
	}

	/**
	 * A folder, ie. inbox/sent etc.
	 */
	function MessageFolder(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'show');
	}

	/**
	 * The popup for when we ask for the popup from the user.
	 */
	function MessagePopup(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'popup');
	}

	/**
	 * This function handles adding, deleting and editing labels on messages.
	 */
	function ManageLabels(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'manlabels');
	}

	/**
	 * List all rules, and allow adding/entering etc...
	 */
	function ManageRules(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'manrules');
	}

	/**
	 * This function performs all additional stuff...
	 */
	function MessageActionsApply(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'pmactions');
	}

	/**
	 * This function allows the user to delete all messages older than so many days.
	 */
	function MessagePrune()
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'prune');
	}

	/**
	 * Delete ALL the messages!
	 */
	function MessageKillAll(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'removalall2');
	}

	/**
	 * Allows the user to report a personal message to an administrator.
	 *
	 * - In the first instance requires that the ID of the message to report is passed through $_GET.
	 * - It allows the user to report to either a particular administrator - or the whole admin team.
	 * - It will forward on a copy of the original message without allowing the reporter to make changes.
	 *
	 * @uses template_report_message()
	 */
	function ReportMessage(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'report');
	}

	/**
	 * Allows searching through personal messages.
	 */
	function MessageSearch(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'search');
	}

	/**
	 * Actually do the search of personal messages.
	 */
	function MessageSearch2(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'search2');
	}

	/**
	 * Send a new message?
	 */
	function MessagePost(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'send');
	}

	/**
	 * Send it!
	 */
	function MessagePost2(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'send2');
	}

	/**
	 * Allows to edit Personal Message Settings.
	 *
	 * Uses Profile.php
	 * Uses Profile-Modify.php
	 * Uses Profile template.
	 * Uses Profile language file.
	 */
	function MessageSettings(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'settings');
	}

	/**
	 * This function allows the user to view their PM drafts
	 */
	function MessageDrafts(): void
	{
		SMF\Actions\PersonalMessage::subActionProvider(sa: 'showpmdrafts');
	}

	/************************
	 * Begin SMF\Actions\Post
	 ************************/

	/**
	 * Handles showing the post screen, loading the post to be modified, and loading any post quoted.
	 *
	 * - additionally handles previews of posts.
	 * - Uses the Post template and language file, main sub template.
	 * - requires different permissions depending on the actions, but most notably post_new, post_reply_own, and post_reply_any.
	 * - shows options for the editing and posting of calendar events and attachments, as well as the posting of polls.
	 * - accessed from ?action=post.
	 *
	 * @param array $post_errors Holds any errors found while trying to post
	 */
	function Post(array $post_errors = []): void
	{
		SMF\Actions\Post::load();
		SMF\Actions\Post::$obj->errors = (array) $post_errors;
		SMF\Actions\Post::$obj->execute();
	}

	/*************************
	 * Begin SMF\Actions\Post2
	 *************************/

	/**
	 * Posts or saves the message composed with Post().
	 *
	 * requires various permissions depending on the action.
	 * handles attachment, post, and calendar saving.
	 * sends off notifications, and allows for announcements and moderation.
	 * accessed from ?action=post2.
	 */
	function Post2(): void
	{
		SMF\Actions\Post2::call();
	}

	/***********************************
	 * Begin SMF\Actions\QuickModeration
	 ***********************************/

	/**
	 * Handles moderation from the message index.
	 *
	 * @todo refactor this...
	 */
	function QuickModeration(): void
	{
		SMF\Actions\QuickModeration::call();
	}

	/******************************************
	 * Begin SMF\Actions\QuickModerationInTopic
	 ******************************************/

	/**
	 * In-topic quick moderation.
	 */
	function QuickInTopicModeration(): void
	{
		SMF\Actions\QuickModerationInTopic::call();
	}

	/*****************************
	 * Begin SMF\Actions\QuoteFast
	 *****************************/

	/**
	 * Loads a post an inserts it into the current editing text box.
	 * uses the Post language file.
	 * uses special (sadly browser dependent) javascript to parse entities for internationalization reasons.
	 * accessed with ?action=quotefast.
	 */
	function QuoteFast(): void
	{
		SMF\Actions\QuoteFast::call();
	}

	/**************************
	 * Begin SMF\Actions\Recent
	 **************************/

	/**
	 * Find the ten most recent posts.
	 */
	function RecentPosts(): void
	{
		SMF\Actions\Recent::call();
	}

	/**
	 * Get the latest post made on the system
	 *
	 * - respects approved, recycled, and board permissions
	 *
	 * @return array An array of information about the last post that you can see
	 */
	function getLastPost(): array
	{
		return SMF\Actions\Recent::getLastPost();
	}

	/****************************
	 * Begin SMF\Actions\Register
	 ****************************/

	/**
	 * Begin the registration process.
	 *
	 * @param array $reg_errors Holds information about any errors that occurred
	 */
	function Register(array $reg_errors = []): void
	{
		SMF\Actions\Register::load();
		SMF\Actions\Register::$obj->subaction = 'show';
		SMF\Actions\Register::$obj->errors = (array) $reg_errors;
		SMF\Actions\Register::$obj->execute();
	}

	/*****************************
	 * Begin SMF\Actions\Register2
	 *****************************/

	/**
	 * Actually register the member.
	 */
	function Register2(): void
	{
		SMF\Actions\Register2::call();
	}

	/**
	 * Registers a member to the forum.
	 * Allows two types of interface: 'guest' and 'admin'. The first
	 * includes hammering protection, the latter can perform the
	 * registration silently.
	 * The strings used in the options array are assumed to be escaped.
	 * Allows to perform several checks on the input, e.g. reserved names.
	 * The function will adjust member statistics.
	 * If an error is detected will fatal error on all errors unless return_errors is true.
	 *
	 * @param array $reg_options An array of registration options
	 * @param bool $return_errors Whether to return the errors
	 * @return int|array The ID of the newly registered user or an array of error info if $return_errors is true
	 */
	function registerMember(array &$reg_options, bool $return_errors = false): int|array
	{
		return SMF\Actions\Register2::registerMember($reg_options, $return_errors);
	}

	/****************************
	 * Begin SMF\Actions\Reminder
	 ****************************/

	/**
	 * This is the controlling delegator
	 *
	 * Uses Profile language files and Reminder template
	 */
	function RemindMe(): void
	{
		SMF\Actions\Reminder::call();
	}

	/*******************************
	 * Begin SMF\Actions\ReportToMod
	 *******************************/

	/**
	 * Report a post or profile to the moderator... ask for a comment.
	 * Gathers data from the user to report abuse to the moderator(s).
	 * Uses the ReportToModerator template, main sub template.
	 * Requires the report_any permission.
	 * Uses ReportToModerator2() if post data was sent.
	 * Accessed through ?action=reporttm.
	 */
	function ReportToModerator(): void
	{
		SMF\Actions\ReportToMod::call();
	}

	/**
	 * Send the emails.
	 * Sends off emails to all the moderators.
	 * Sends to administrators and global moderators. (1 and 2)
	 * Called by ReportToModerator(), and thus has the same permission and setting requirements as it does.
	 * Accessed through ?action=reporttm when posting.
	 */
	function ReportToModerator2(): void
	{
		SMF\Actions\ReportToMod::subActionProvider(sa: 'submit');
	}

	/**
	 * Actually reports a post using information specified from a form
	 *
	 * @param int $msg The ID of the post being reported
	 * @param string $reason The reason specified for reporting the post
	 */
	function reportPost($msg, $reason): void
	{
		$_POST['msg'] = (int) $msg;
		$_POST['comment'] = SMF\Utils::htmlspecialcharsDecode((string) $reason);

		SMF\Actions\ReportToMod::load();
		SMF\Actions\ReportToMod::$obj->subaction = 'submit';
		SMF\Actions\ReportToMod::$obj->execute();
	}

	/**
	 * Actually reports a user's profile using information specified from a form
	 *
	 * @param int $id_member The ID of the member whose profile is being reported
	 * @param string $reason The reason specified by the reporter for this report
	 */
	function reportUser($id_member, $reason): void
	{
		$_POST['u'] = (int) $id_member;
		$_POST['comment'] = SMF\Utils::htmlspecialcharsDecode((string) $reason);

		SMF\Actions\ReportToMod::load();
		SMF\Actions\ReportToMod::$obj->subaction = 'submit';
		SMF\Actions\ReportToMod::$obj->execute();
	}

	/**********************************
	 * Begin SMF\Actions\RequestMembers
	 **********************************/

	/**
	 * Outputs each member name on its own line.
	 * - used by javascript to find members matching the request.
	 */
	function RequestMembers(): void
	{
		SMF\Actions\RequestMembers::call();
	}

	/**************************
	 * Begin SMF\Actions\Search
	 **************************/

	/**
	 * Ask the user what they want to search for.
	 * What it does:
	 * - shows the screen to search forum posts (action=search)
	 * - uses the main sub template of the Search template.
	 * - uses the Search language file.
	 * - requires the search_posts permission.
	 * - decodes and loads search parameters given in the URL (if any).
	 * - the form redirects to index.php?action=search2.
	 */
	function PlushSearch1(): void
	{
		SMF\Actions\Search::call();
	}

	/***************************
	 * Begin SMF\Actions\Search2
	 ***************************/

	/**
	 * Gather the results and show them.
	 * What it does:
	 * - checks user input and searches the messages table for messages matching the query.
	 * - requires the search_posts permission.
	 * - uses the results sub template of the Search template.
	 * - uses the Search language file.
	 * - stores the results into the search cache.
	 * - show the results of the search query.
	 */
	function PlushSearch2(): void
	{
		SMF\Actions\Search2::call();
	}

	/**********************************
	 * Begin SMF\Actions\SendActivation
	 **********************************/

	/**
	 * It doesn't actually send anything, this action just shows a message for a guest.
	 */
	function SendActivation(): void
	{
		SMF\Actions\SendActivation::call();
	}

	/***************************
	 * Begin SMF\Actions\SmStats
	 ***************************/

	/**
	 * This is the function which returns stats to simplemachines.org IF enabled!
	 * called by simplemachines.org.
	 * only returns anything if stats was enabled during installation.
	 * can also be accessed by the admin, to show what stats sm.org collects.
	 * does not return any data directly to sm.org, instead starts a new request for security.
	 *
	 * @link https://www.simplemachines.org/about/stats.php for more info.
	 */
	function SMStats(): void
	{
		SMF\Actions\SmStats::call();
	}

	/*************************
	 * Begin SMF\Actions\Stats
	 *************************/

	/**
	 * Display some useful/interesting board statistics.
	 *
	 * gets all the statistics in order and puts them in.
	 * uses the Stats template and language file. (and main sub template.)
	 * requires the view_stats permission.
	 * accessed from ?action=stats.
	 */
	function DisplayStats(): void
	{
		SMF\Actions\Stats::call();
	}

	/******************************
	 * Begin SMF\Actions\TopicMerge
	 ******************************/

	/**
	 * merges two or more topics into one topic.
	 * delegates to the other functions (based on the URL parameter sa).
	 * loads the SplitTopics template.
	 * requires the merge_any permission.
	 * is accessed with ?action=mergetopics.
	 */
	function MergeTopics(): void
	{
		SMF\Actions\TopicMerge::call();
	}

	/**
	 * allows to pick a topic to merge the current topic with.
	 * is accessed with ?action=mergetopics;sa=index
	 * default sub action for ?action=mergetopics.
	 * uses 'merge' sub template of the MoveTopic template.
	 * allows to set a different target board.
	 */
	function MergeIndex(): void
	{
		SMF\Actions\TopicMerge::subActionProvider(sa: 'index');
	}

	/**
	 * set merge options and do the actual merge of two or more topics.
	 *
	 * the merge options screen:
	 * * shows topics to be merged and allows to set some merge options.
	 * * is accessed by ?action=mergetopics;sa=options.and can also internally be called by QuickModeration() (Subs-Boards.php).
	 * * uses 'merge_extra_options' sub template of the MoveTopic template.
	 *
	 * the actual merge:
	 * * is accessed with ?action=mergetopics;sa=execute.
	 * * updates the statistics to reflect the merge.
	 * * logs the action in the moderation log.
	 * * sends a notification is sent to all users monitoring this topic.
	 * * redirects to ?action=mergetopics;sa=done.
	 *
	 * @param array $topics The IDs of the topics to merge
	 */
	function MergeExecute(array $topics = []): void
	{
		SMF\Actions\TopicMerge::load();
		SMF\Actions\TopicMerge::$obj->subaction = !empty($_GET['sa']) && $_GET['sa'] === 'merge' ? 'merge' : 'options';
		SMF\Actions\TopicMerge::$obj->topics = array_map('intval', $topics);
		SMF\Actions\TopicMerge::$obj->execute();
	}

	/**
	 * Shows a 'merge completed' screen.
	 * is accessed with ?action=mergetopics;sa=done.
	 * uses 'merge_done' sub template of the SplitTopics template.
	 */
	function MergeDone(): void
	{
		SMF\Actions\TopicMerge::subActionProvider(sa: 'done');
	}

	/*****************************
	 * Begin SMF\Actions\TopicMove
	 *****************************/

	/**
	 * This function allows to move a topic, making sure to ask the moderator
	 * to give reason for topic move.
	 * It must be called with a topic specified. (that is, global $topic must
	 * be set... @todo fix this thing.)
	 * If the member is the topic starter requires the move_own permission,
	 * otherwise the move_any permission.
	 * Accessed via ?action=movetopic.
	 *
	 * Uses the MoveTopic template, main sub-template.
	 */
	function MoveTopic(): void
	{
		SMF\Actions\TopicMove::call();
	}

	/******************************
	 * Begin SMF\Actions\TopicMove2
	 ******************************/

	/**
	 * Execute the move of a topic.
	 * It is called on the submit of MoveTopic.
	 * This function logs that topics have been moved in the moderation log.
	 * If the member is the topic starter requires the move_own permission,
	 * otherwise requires the move_any permission.
	 * Upon successful completion redirects to message index.
	 * Accessed via ?action=movetopic2.
	 *
	 * Uses Subs-Post.php
	 */
	function MoveTopic2(): void
	{
		SMF\Actions\TopicMove2::call();
	}

	/**
	 * Called after a topic is moved to update $board_link and $topic_link to point to new location
	 */
	function moveTopicConcurrence()
	{
		SMF\Actions\TopicMove2::moveTopicConcurrence();
	}

	/******************************
	 * Begin SMF\Actions\TopicPrint
	 ******************************/

	/**
	 * Format a topic to be printer friendly.
	 * Must be called with a topic specified.
	 * Accessed via ?action=printpage.
	 *
	 * Uses Printpage template, main sub-template.
	 * Uses print_above/print_below later without the main layer.
	 */
	function PrintTopic(): void
	{
		SMF\Actions\TopicPrint::call();
	}

	/*******************************
	 * Begin SMF\Actions\TopicRemove
	 *******************************/

	/**
	 * Completely remove an entire topic.
	 * Redirects to the board when completed.
	 */
	function RemoveTopic2(): void
	{
		SMF\Actions\TopicRemove::call();
	}

	/**
	 * Try to determine if the topic has already been deleted by another user.
	 *
	 * @return bool False if it can't be deleted (recycling not enabled or no recycling board set), true if we've confirmed it can be deleted. Dies with an error if it's already been deleted.
	 */
	function removeDeleteConcurrence(): bool
	{
		return SMF\Actions\TopicRemove::removeDeleteConcurrence();
	}

	/**
	 * So long as you are sure... all old posts will be gone.
	 * Used in ManageMaintenance.php to prune old topics.
	 */
	function RemoveOldTopics2()
	{
		SMF\Actions\TopicRemove::old();
	}

	/********************************
	 * Begin SMF\Actions\TopicRestore
	 ********************************/

	/**
	 * Move back a topic from the recycle board to its original board.
	 */
	function RestoreTopic(): void
	{
		SMF\Actions\TopicRestore::call();
	}

	/******************************
	 * Begin SMF\Actions\TopicSplit
	 ******************************/

	/**
	 * splits a topic into two topics.
	 * delegates to the other functions (based on the URL parameter 'sa').
	 * loads the SplitTopics template.
	 * requires the split_any permission.
	 * is accessed with ?action=splittopics.
	 */
	function SplitTopics(): void
	{
		SMF\Actions\TopicSplit::call();
	}

	/**
	 * general function to split off a topic.
	 * creates a new topic and moves the messages with the IDs in
	 * array messagesToBeSplit to the new topic.
	 * the subject of the newly created topic is set to 'newSubject'.
	 * marks the newly created message as read for the user splitting it.
	 * updates the statistics to reflect a newly created topic.
	 * logs the action in the moderation log.
	 * a notification is sent to all users monitoring this topic.
	 *
	 * @param int $split1_ID_TOPIC The ID of the topic we're splitting
	 * @param array $splitMessages The IDs of the messages being split
	 * @param string $new_subject The subject of the new topic
	 * @return int The ID of the new split topic.
	 */
	function splitTopic(int $split1_ID_TOPIC, array $splitMessages, string $new_subject): int
	{
		return SMF\Actions\TopicSplit::splitTopic($split1_ID_TOPIC, $splitMessages, $new_subject);
	}

	/**
	 * screen shown before the actual split.
	 * is accessed with ?action=splittopics;sa=index.
	 * default sub action for ?action=splittopics.
	 * uses 'ask' sub template of the SplitTopics template.
	 * redirects to SplitSelectTopics if the message given turns out to be
	 * the first message of a topic.
	 * shows the user three ways to split the current topic.
	 */
	function SplitIndex(): void
	{
		SMF\Actions\TopicSplit::subActionProvider(sa: 'index');
	}

	/**
	 * do the actual split.
	 * is accessed with ?action=splittopics;sa=execute.
	 * uses the main SplitTopics template.
	 * supports three ways of splitting:
	 * (1) only one message is split off.
	 * (2) all messages after and including a given message are split off.
	 * (3) select topics to split (redirects to SplitSelectTopics()).
	 * uses splitTopic function to do the actual splitting.
	 */
	function SplitExecute(): void
	{
		SMF\Actions\TopicSplit::subActionProvider(sa: 'split');
	}

	/**
	 * allows the user to select the messages to be split.
	 * is accessed with ?action=splittopics;sa=selectTopics.
	 * uses 'select' sub template of the SplitTopics template or (for
	 * XMLhttp) the 'split' sub template of the Xml template.
	 * supports XMLhttp for adding/removing a message to the selection.
	 * uses a session variable to store the selected topics.
	 * shows two independent page indexes for both the selected and
	 * not-selected messages (;topic=1.x;start2=y).
	 */
	function SplitSelectTopics(): void
	{
		SMF\Actions\TopicSplit::subActionProvider(sa: 'selectTopics');
	}

	/**
	 * do the actual split of a selection of topics.
	 * is accessed with ?action=splittopics;sa=splitSelection.
	 * uses the main SplitTopics template.
	 * uses splitTopic function to do the actual splitting.
	 */
	function SplitSelectionExecute(): void
	{
		SMF\Actions\TopicSplit::subActionProvider(sa: 'splitSelection');
	}

	/***************************
	 * Begin SMF\Actions\TrackIP
	 ***************************/

	/**
	 * Handles tracking a particular IP address
	 *
	 * @param int $memID The ID of a member whose IP we want to track
	 */
	function TrackIP(int $memID = 0): void
	{
		SMF\Actions\TrackIP::load();
		SMF\Actions\TrackIP::$obj->memID = $memID;
		SMF\Actions\TrackIP::$obj->execute();
	}

	/**************************
	 * Begin SMF\Actions\Unread
	 **************************/

	/**
	 * Find unread topics and replies.
	 */
	function UnreadTopics(): void
	{
		SMF\Actions\Unread::call();
	}

	/************************************
	 * Begin SMF\Actions\VerificationCode
	 ************************************/

	/**
	 * Show the verification code or let it be heard.
	 */
	function VerificationCode(): void
	{
		SMF\Actions\VerificationCode::call();
	}

	/*****************************
	 * Begin SMF\Actions\ViewQUery
	 *****************************/

	/**
	 * Show the database queries for debugging
	 * What this does:
	 * - Toggles the session variable 'view_queries'.
	 * - Views a list of queries and analyzes them.
	 * - Requires the admin_forum permission.
	 * - Is accessed via ?action=viewquery.
	 * - Strings in this function have not been internationalized.
	 */
	function ViewQuery(): void
	{
		SMF\Actions\ViewQuery::call();
	}

	/***********************
	 * Begin SMF\Actions\Who
	 ***********************/

	/**
	 * Who's online, and what are they doing?
	 * This function prepares the who's online data for the Who template.
	 * It requires the who_view permission.
	 * It is enabled with the who_enabled setting.
	 * It is accessed via ?action=who.
	 *
	 * Uses Who template, main sub-template
	 * Uses Who language file.
	 */
	function Who(): void
	{
		SMF\Actions\Who::call();
	}

	/**
	 * This function determines the actions of the members passed in urls.
	 *
	 * Adding actions to the Who's Online list:
	 * Adding actions to this list is actually relatively easy...
	 *  - for actions anyone should be able to see, just add a string named whoall_ACTION.
	 *    (where ACTION is the action used in index.php.)
	 *  - for actions that have a subaction which should be represented differently, use whoall_ACTION_SUBACTION.
	 *  - for actions that include a topic, and should be restricted, use whotopic_ACTION.
	 *  - for actions that use a message, by msg or quote, use whopost_ACTION.
	 *  - for administrator-only actions, use whoadmin_ACTION.
	 *  - for actions that should be viewable only with certain permissions,
	 *    use whoallow_ACTION and add a list of possible permissions to the
	 *    $allowedActions array, using ACTION as the key.
	 *
	 * @param mixed $urls a single url (string) or an array of arrays, each inner array being (JSON-encoded request data, id_member)
	 * @param string|bool $preferred_prefix = false
	 * @return array an array of descriptions if you passed an array, otherwise the string describing their current location.
	 */
	function determineActions(string|array $urls, string|bool $preferred_prefix = false): array
	{
		return SMF\Actions\Who::determineActions($urls, $preferred_prefix);
	}

	/***************************
	 * Begin SMF\Actions\XmlHttp
	 ***************************/

	/**
	 * The main handler and designator for AJAX stuff - jumpto, message icons and previews
	 */
	function XMLhttpMain(): void
	{
		SMF\Actions\XmlHttp::call();
	}

	/**
	 * Get a list of boards and categories used for the jumpto dropdown.
	 */
	function GetJumpTo(): void
	{
		SMF\Actions\XmlHttp::subActionProvider(sa: 'jumpto');
	}

	/**
	 * Gets a list of available message icons and sends the info to the template for display
	 */
	function ListMessageIcons(): void
	{
		SMF\Actions\XmlHttp::subActionProvider(sa: 'messageicons');
	}

	/**
	 * Handles retrieving previews of news items, newsletters, signatures and warnings.
	 * Calls the appropriate function based on $_POST['item']
	 *
	 * @return ?bool Returns false if $_POST['item'] isn't set or isn't valid
	 */
	function RetrievePreview(): ?bool
	{
		SMF\Actions\XmlHttp::subActionProvider(sa: 'previews');
	}

	/**************************
	 * Begin SMF\Cache\CacheApi
	 **************************/

	/**
	 * Try to load up a supported caching method. This is saved in $cacheAPI if we are not overriding it.
	 *
	 * @param string $overrideCache Try to use a different cache method other than that defined in $cache_accelerator.
	 * @param bool $fallbackSMF Use the default SMF method if the accelerator fails.
	 * @return object|false A object of $cacheAPI, or False on failure.
	 */
	function loadCacheAccelerator(string $overrideCache = '', bool $fallbackSMF = true): SMF\Cache\CacheApi|false
	{
		return SMF\Cache\CacheApi::load($overrideCache, $fallbackSMF);
	}

	/**
	 * Get the installed Cache API implementations.
	 *
	 */
	function loadCacheAPIs(): array
	{
		return SMF\Cache\CacheApi::detect();
	}

	/**
	 * Empty out the cache in use as best it can
	 *
	 * It may only remove the files of a certain type (if the $type parameter is given)
	 * Type can be user, data or left blank
	 * 	- user clears out user data
	 *  - data clears out system / opcode data
	 *  - If no type is specified will perform a complete cache clearing
	 * For cache engines that do not distinguish on types, a full cache flush will be done
	 *
	 * @param string $type The cache type ('memcached', 'zend' or something else for SMF's file cache)
	 */
	function clean_cache(string $type = ''): void
	{
		SMF\Cache\CacheApi::clean($type);
	}

	/**
	 * Try to retrieve a cache entry. On failure, call the appropriate function.
	 *
	 * @param string $key The key for this entry
	 * @param string $file The file associated with this entry
	 * @param string $function The function to call
	 * @param array $params Parameters to be passed to the specified function
	 * @param int $level The cache level
	 * @return string The cached data
	 */
	function cache_quick_get(string $key, string $file, string $function, array $params, int $level = 1): string
	{
		return SMF\Cache\CacheApi::quickGet($key, $file, $function, $params, $level);
	}

	/**
	 * Puts value in the cache under key for ttl seconds.
	 *
	 * - It may "miss" so shouldn't be depended on
	 * - Uses the cache engine chosen in the ACP and saved in settings.php
	 * - It supports:
	 *	 memcache: https://php.net/memcache
	 *   APCu: https://php.net/book.apcu
	 *	 Zend: http://files.zend.com/help/Zend-Platform/output_cache_functions.htm
	 *	 Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
	 *
	 * @param string $key A key for this value
	 * @param mixed $value The data to cache
	 * @param int $ttl How long (in seconds) the data should be cached for
	 */
	function cache_put_data(string $key, mixed $value, int $ttl = 120): void
	{
		SMF\Cache\CacheApi::put($key, $value, $ttl);
	}

	/**
	 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
	 * - It may often "miss", so shouldn't be depended on.
	 * - It supports the same as cache_put_data().
	 *
	 * @param string $key The key for the value to retrieve
	 * @param int $ttl The maximum age of the cached data
	 * @return array|null The cached data or null if nothing was loaded
	 */
	function cache_get_data(string $key, int $ttl = 120): mixed
	{
		return SMF\Cache\CacheApi::get($key, $ttl);
	}

	/**************
	 * Begin Config
	 **************/

	/**
	 * Load the $modSettings array.
	 */
	function reloadSettings()
	{
		return SMF\Config::reloadModSettings();
	}

	/**
	 * Updates the settings table as well as $modSettings... only does one at a time if $update is true.
	 *
	 * - updates both the settings table and $modSettings array.
	 * - all of changeArray's indexes and values are assumed to have escaped apostrophes (')!
	 * - if a variable is already set to what you want to change it to, that
	 *   variable will be skipped over; it would be unnecessary to reset.
	 * - When use_update is true, UPDATEs will be used instead of REPLACE.
	 * - when use_update is true, the value can be true or false to increment
	 *  or decrement it, respectively.
	 *
	 * @param array $change_array An array of info about what we're changing in 'setting' => 'value' format
	 * @param bool $update Whether to use an UPDATE query instead of a REPLACE query
	 */
	function updateSettings(array $change_array, bool $update = false)
	{
		return SMF\Config::updateModSettings($change_array, $update);
	}

	/**
	 * Gets, and if necessary creates, the authentication secret to use for cookies, tokens, etc.
	 *
	 * Note: Never use the $auth_secret variable directly. Always call this function instead.
	 *
	 * @return string The authentication secret.
	 */
	function get_auth_secret()
	{
		return SMF\Config::getAuthSecret();
	}

	/**
	 * Describes properties of all known Settings.php variables and other content.
	 * Helper for updateSettingsFile(); also called by saveSettings().
	 *
	 * @return array Descriptions of all known Settings.php content
	 */
	function get_settings_defs()
	{
		return SMF\Config::getSettingsDefs();
	}

	/**
	 * Update the Settings.php file.
	 *
	 * The most important function in this file for mod makers happens to be the
	 * updateSettingsFile() function, but it shouldn't be used often anyway.
	 *
	 * - Updates the Settings.php file with the changes supplied in config_vars.
	 *
	 * - Expects config_vars to be an associative array, with the keys as the
	 *   variable names in Settings.php, and the values the variable values.
	 *
	 * - Correctly formats the values using smf_var_export().
	 *
	 * - Restores standard formatting of the file, if $rebuild is true.
	 *
	 * - Checks for changes to db_last_error and passes those off to a separate
	 *   handler.
	 *
	 * - Creates a backup file and will use it should the writing of the
	 *   new settings file fail.
	 *
	 * - Tries to intelligently trim quotes and remove slashes from string values.
	 *   This is done for backwards compatibility purposes (old versions of this
	 *   function expected strings to have been manually escaped and quoted). This
	 *   behaviour can be controlled by the $keep_quotes parameter.
	 *
	 * MOD AUTHORS: If you are adding a setting to Settings.php, you should use the
	 * integrate_update_settings_file hook to define it in get_settings_defs().
	 *
	 * @param array $config_vars An array of one or more variables to update.
	 * @param bool|null $keep_quotes Whether to strip slashes & trim quotes from string values. Defaults to auto-detection.
	 * @param bool $rebuild If true, attempts to rebuild with standard format. Default false.
	 * @return bool True on success, false on failure.
	 */
	function updateSettingsFile(array $config_vars, ?bool $keep_quotes = null, bool $rebuild = false)
	{
		return SMF\Config::updateSettingsFile($config_vars, $keep_quotes, $rebuild);
	}

	/**
	 * Writes data to a file, optionally making a backup, while avoiding race conditions.
	 *
	 * @param string $file The filepath of the file where the data should be written.
	 * @param string $data The data to be written to $file.
	 * @param string $backup_file The filepath where the backup should be saved. Default null.
	 * @param int $mtime If modification time of $file is more recent than this Unix timestamp, the write operation will abort. Defaults to time that the script started execution.
	 * @param bool $append If true, the data will be appended instead of overwriting the existing content of the file. Default false.
	 * @return bool Whether the write operation succeeded or not.
	 */
	function safe_file_write(string $file, string $data, ?string $backup_file = null, ?int $mtime = null, bool $append = false)
	{
		return SMF\Config::safeFileWrite($file, $data, $backup_file, $mtime, $append);
	}

	/**
	 * A wrapper around var_export whose output matches SMF coding conventions.
	 *
	 * @todo Add special handling for objects?
	 *
	 * @param mixed $var The variable to export
	 * @return mixed A PHP-parseable representation of the variable's value
	 */
	function smf_var_export(mixed $var)
	{
		return SMF\Config::varExport($var);
	}

	/**
	 * Saves the time of the last db error for the error log
	 * - Done separately from updateSettingsFile to avoid race conditions
	 *   which can occur during a db error
	 * - If it fails Settings.php will assume 0
	 *
	 * @param int $time The timestamp of the last DB error
	 * @param bool True If we should update the current db_last_error context as well.  This may be useful in cases where the current context needs to know a error was logged since the last check.
	 * @return bool True If we could succesfully put the file or not.
	 */
	function updateDbLastError(int $time)
	{
		return SMF\Config::updateDbLastError($time);
	}

	/**
	 * Locates the most appropriate temp directory.
	 *
	 * Systems using `open_basedir` restrictions may receive errors with
	 * `sys_get_temp_dir()` due to misconfigurations on servers. Other
	 * cases sys_temp_dir may not be set to a safe value. Additionally
	 * `sys_get_temp_dir` may use a readonly directory. This attempts to
	 * find a working temp directory that is accessible under the
	 * restrictions and is writable to the web service account.
	 *
	 * Directories checked against `open_basedir`:
	 *
	 * - `sys_get_temp_dir()`
	 * - `upload_tmp_dir`
	 * - `session.save_path`
	 * - `cachedir`
	 *
	 * @return string
	 */
	function sm_temp_dir()
	{
		return SMF\Config::getTempDir();
	}

	/**
	 * Generate a random seed and ensure it's stored in settings.
	 */
	function smf_seed_generator()
	{
		return SMF\Config::generateSeed();
	}

	/**
	 * Ensures SMF's scheduled tasks are being run as intended
	 *
	 * If the admin activated the cron_is_real_cron setting, but the cron job is
	 * not running things at least once per day, we need to go back to SMF's default
	 * behaviour using "web cron" JavaScript calls.
	 */
	function check_cron()
	{
		return SMF\Config::checkCron();
	}

	/**************************
	 * Begin SMF\Db\DatabaseApi
	 **************************/

	/**
	 * Initialize a database connection.
	 */
	function loadDatabase(array $options = []): SMF\Db\DatabaseApi
	{
		return SMF\Db\DatabaseApi::load((array) $options);
	}

	/**
	 * Extend the database functionality. It calls the respective file's init
	 * to add the implementations in that file to $smcFunc array.
	 *
	 * In SMF 3.0 this is a no-op.
	 *
	 * @param string $type Indicates which additional file to load. ('extra', 'packages')
	 */
	function db_extend(string $type): void {}

	/**************************
	 * Begin SMF\Graphics\Image
	 **************************/

	/**
	 * See if we have enough memory to thumbnail an image
	 *
	 * @param array $sizes image size
	 * @return bool Whether we do
	 */
	function imageMemoryCheck(array $sizes): bool
	{
		return SMF\Graphics\Image::checkMemory($sizes);
	}

	/**
	 * Get the size of a specified image with better error handling.
	 *
	 * @todo see if it's better in Subs-Graphics, but one step at the time.
	 * Uses getimagesize() to determine the size of a file.
	 * Attempts to connect to the server first so it won't time out.
	 *
	 * @param string $url The URL of the image
	 * @return array|false The image size as array (width, height), or false on failure
	 */
	function url_image_size(string $url): array|false
	{
		return SMF\Graphics\Image::getSizeExternal($url);
	}

	/**
	 * Writes a gif file to disk as a png file.
	 *
	 * @param gif_file $gif A gif image resource
	 * @param string $lpszFileName The name of the file
	 * @param int $background_color The background color
	 * @return bool Whether the operation was successful
	 */
	function gif_outputAsPng($gif, $lpszFileName, $background_color = -1): bool
	{
		return SMF\Graphics\Image::gifOutputAsPng($gif, $lpszFileName, $background_color);
	}

	/**
	 * Gets the dimensions of an SVG image (specifically, of its viewport).
	 *
	 * If $filepath is not the path to a valid SVG file, the returned width and
	 * height will both be null.
	 *
	 * See https://www.w3.org/TR/SVG11/coords.html#IntrinsicSizing
	 *
	 * @param string $filepath The path to the SVG file.
	 * @return array The width and height of the SVG image in pixels.
	 */
	function getSvgSize(string $filepath): array
	{
		$image = new Image($filepath);

		if ($image->mime_type !== 'image/svg+xml') {
			return ['width' => null, 'height' => null];
		}

		return ['width' => $image->width, 'height' => $image->height];
	}

	/**
	 * Create a thumbnail of the given source.
	 *
	 * @uses resizeImageFile() function to achieve the resize.
	 *
	 * @param string $source The name of the source image
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @return bool Whether the thumbnail creation was successful.
	 */
	function createThumbnail(string $source, int $max_width, int $max_height): bool
	{
		return ((new Image($source))->createThumbnail($max_width, $max_height) !== false);
	}

	/**
	 * Used to re-econodes an image to a specified image format
	 * - creates a copy of the file at the same location as fileName.
	 * - the file would have the format preferred_format if possible, otherwise the default format is jpeg.
	 * - the function makes sure that all non-essential image contents are disposed.
	 *
	 * @param string $fileName The path to the file
	 * @param int $preferred_format The preferred format - 0 to automatically determine, 1 for gif, 2 for jpg, 3 for png, 6 for bmp and 15 for wbmp
	 * @return bool Whether the reencoding was successful
	 */
	function reencodeImage(string $source, int $preferred_type = 0): bool
	{
		return (new Image($source))->reencode($preferred_type);
	}

	/**
	 * Searches through the file to see if there's potentially harmful non-binary content.
	 * - if extensiveCheck is true, searches for asp/php short tags as well.
	 *
	 * @param string $fileName The path to the file
	 * @param bool $extensiveCheck Whether to perform extensive checks
	 * @return bool Whether the image appears to be safe
	 */
	function checkImageContents(string $source, bool $extensive = false): bool
	{
		return (new Image($source))->check($extensive);
	}

	/**
	 * Searches through an SVG file to see if there's potentially harmful content.
	 *
	 * @param string $fileName The path to the file.
	 * @return bool Whether the image appears to be safe.
	 */
	function checkSvgContents(string $source): bool
	{
		return (new Image($source))->check();
	}

	/**
	 * Resizes an image from a remote location or a local file.
	 * Puts the resized image at the destination location.
	 * The file would have the format preferred_format if possible,
	 * otherwise the default format is jpeg.
	 *
	 * @param string $source The path to the source image
	 * @param string $destination The path to the destination image
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param int $preferred_format - The preferred format (0 to use jpeg, 1 for gif, 2 to force jpeg, 3 for png, 6 for bmp and 15 for wbmp)
	 * @return bool Whether it succeeded.
	 */
	function resizeImageFile(
		string $source,
		string $destination,
		int $max_width,
		int $max_height,
		int $preferred_type = 0,
	): bool {
		return (new Image($source))->resize($destination, $max_width, $max_height, $preferred_type);
	}

	/**
	 * Resizes src_img proportionally to fit within max_width and max_height limits
	 * if it is too large.
	 * If GD2 is present, it'll use it to achieve better quality.
	 * It saves the new image to destination_filename, as preferred_format
	 * if possible, default is jpeg.
	 *
	 * Uses Imagemagick (IMagick or MagickWand extension) or GD
	 *
	 * @param resource $source The source image
	 * @param string $destination The path to the destination image
	 * @param int $src_width The width of the source image
	 * @param int $src_height The height of the source image
	 * @param int $max_width The maximum allowed width
	 * @param int $max_height The maximum allowed height
	 * @param bool $force_resize = false Whether to forcibly resize it
	 * @param int $preferred_type - 1 for gif, 2 for jpeg, 3 for png, 6 for bmp or 15 for wbmp
	 * @return bool Whether the resize was successful
	 */
	function resizeImage(
		string $source,
		string $destination,
		int $src_width,
		int $src_height,
		int $max_width,
		int $max_height,
		bool $force_resize = false,
		int $preferred_type = 0,
	): bool {
		return (new Image($source))->resize($destination, $max_width, $max_height, $preferred_type);
	}

	/**************************************
	 * Begin SMF\Packagemanager\SubsPackage
	 **************************************/

	/**
	 * Reads an archive from either a remote location or from the local filesystem.
	 *
	 * @param string $gzfilename The path to the tar.gz file
	 * @param string $destination The path to the desitnation directory
	 * @param bool $single_file If true returns the contents of the file specified by destination if it exists
	 * @param bool $overwrite Whether to overwrite existing files
	 * @param null|array $files_to_extract Specific files to extract
	 * @return array|false An array of information about extracted files or false on failure
	 */
	function read_tgz_file(
		string $gzfilename,
		?string $destination,
		bool $single_file = false,
		bool $overwrite = false,
		?array $files_to_extract = null,
	): array|bool {
		return SMF\PackageManager\SubsPackage::read_tgz_file(
			$gzfilename,
			isset($destination) ? (string) $destination : null,
			$single_file,
			$overwrite,
			$files_to_extract,
		);
	}

	/**
	 * Extracts a file or files from the .tar.gz contained in data.
	 *
	 * detects if the file is really a .zip file, and if so returns the result of read_zip_data
	 *
	 * if destination is null
	 *	- returns a list of files in the archive.
	 *
	 * if single_file is true
	 * - returns the contents of the file specified by destination, if it exists, or false.
	 * - destination can start with * and / to signify that the file may come from any directory.
	 * - destination should not begin with a / if single_file is true.
	 *
	 * overwrites existing files with newer modification times if and only if overwrite is true.
	 * creates the destination directory if it doesn't exist, and is is specified.
	 * requires zlib support be built into PHP.
	 * returns an array of the files extracted.
	 * if files_to_extract is not equal to null only extracts file within this array.
	 *
	 * @param string $data The gzipped tarball
	 * @param null|string $destination The destination
	 * @param bool $single_file Whether to only extract a single file
	 * @param bool $overwrite Whether to overwrite existing data
	 * @param null|array $files_to_extract If set, only extracts the specified files
	 * @return array|false An array of information about the extracted files or false on failure
	 */
	function read_tgz_data(
		string $data,
		?string $destination,
		bool $single_file = false,
		bool $overwrite = false,
		?array $files_to_extract = null,
	): array|bool {
		return SMF\PackageManager\SubsPackage::read_tgz_data(
			$data,
			$destination,
			$single_file,
			$overwrite,
			$files_to_extract,
		);
	}

	/**
	 * Extract zip data.
	 *
	 * If single_file is true, destination can start with * and / to signify that the file may come from any directory.
	 * Destination should not begin with a / if single_file is true.
	 *
	 * @param string $data ZIP data
	 * @param string $destination Null to display a listing of files in the archive, the destination for the files in the archive or the name of a single file to display (if $single_file is true)
	 * @param bool $single_file If true, returns the contents of the file specified by destination or false if the file can't be found (default value is false).
	 * @param bool $overwrite If true, will overwrite files with newer modication times. Default is false.
	 * @param array $files_to_extract
	 * @return mixed If destination is null, return a short array of a few file details optionally delimited by $files_to_extract. If $single_file is true, return contents of a file as a string; false otherwise
	 */
	function read_zip_data(
		string $data,
		string $destination,
		bool $single_file = false,
		bool $overwrite = false,
		?array $files_to_extract = null,
	): mixed {
		return SMF\PackageManager\SubsPackage::read_zip_data(
			$data,
			$destination,
			$single_file,
			$overwrite,
			$files_to_extract,
		);
	}

	/**
	 * Checks the existence of a remote file since file_exists() does not do remote.
	 * will return false if the file is "moved permanently" or similar.
	 *
	 * @param string $url The URL to parse
	 * @return bool Whether the specified URL exists
	 */
	function url_exists(string $url): bool
	{
		return SMF\PackageManager\SubsPackage::url_exists($url);
	}

	/**
	 * Loads and returns an array of installed packages.
	 *
	 *  default sort order is package_installed time
	 *
	 * @return array An array of info about installed packages
	 */
	function loadInstalledPackages(): array
	{
		return SMF\PackageManager\SubsPackage::loadInstalledPackages();
	}

	/**
	 * Loads a package's information and returns a representative array.
	 * - expects the file to be a package in Packages/.
	 * - returns a error string if the package-info is invalid.
	 * - otherwise returns a basic array of id, version, filename, and similar information.
	 * - an xmlArray is available in 'xml'.
	 *
	 * @param string $gzfilename The path to the file
	 * @return array|string An array of info about the file or a string indicating an error
	 */
	function getPackageInfo(string $gzfilename): array|string
	{
		return SMF\PackageManager\SubsPackage::getPackageInfo($gzfilename);
	}

	/**
	 * Create a chmod control for chmoding files.
	 *
	 * @param array $chmodFiles Which files to chmod
	 * @param array $chmodOptions Options for chmod
	 * @param bool $restore_write_status Whether to restore write status
	 * @return array An array of file info
	 */
	function create_chmod_control(
		array $chmodFiles = [],
		array $chmodOptions = [],
		bool $restore_write_status = false,
	): array {
		return SMF\PackageManager\SubsPackage::create_chmod_control($chmodFiles, $chmodOptions, $restore_write_status);
	}

	/**
	 * Get a listing of files that will need to be set back to the original state
	 *
	 * @param null $dummy1
	 * @param null $dummy2
	 * @param null $dummy3
	 * @param bool $do_change
	 * @return array An array of info about the files that need to be restored back to their original state
	 */
	function list_restoreFiles(mixed $dummy1, mixed $dummy2, mixed $dummy3, bool $do_change): array
	{
		return SMF\PackageManager\SubsPackage::list_restoreFiles($dummy1, $dummy2, $dummy3, $do_change);
	}

	/**
	 * Use FTP functions to work with a package download/install
	 *
	 * @param string $destination_url The destination URL
	 * @param null|array $files The files to CHMOD
	 * @param bool $return Whether to return an array of file info if there's an error
	 * @return array An array of file info
	 */
	function packageRequireFTP(string $destination_url, ?array $files = null, bool $return = false): array
	{
		return SMF\PackageManager\SubsPackage::packageRequireFTP($destination_url, $files, $return);
	}

	/**
	 * Parses the actions in package-info.xml file from packages.
	 *
	 * - package should be an xmlArray with package-info as its base.
	 * - testing_only should be true if the package should not actually be applied.
	 * - method can be upgrade, install, or uninstall.  Its default is install.
	 * - previous_version should be set to the previous installed version of this package, if any.
	 * - does not handle failure terribly well; testing first is always better.
	 *
	 * @param xmlArray &$packageXML The info from the package-info file
	 * @param bool $testing_only Whether we're only testing
	 * @param string $method The method ('install', 'upgrade', or 'uninstall')
	 * @param string $previous_version The previous version of the mod, if method is 'upgrade'
	 * @return array An array of those changes made.
	 */
	function parsePackageInfo(
		SMF\PackageManager\XmlArray &$packageXML,
		bool $testing_only = true,
		string $method = 'install',
		string $previous_version = '',
	): array {
		return SMF\PackageManager\SubsPackage::parsePackageInfo(
			$packageXML,
			$testing_only,
			$method,
			$previous_version,
		);
	}

	/**
	 * Checks if version matches any of the versions in `$versions`.
	 *
	 * - supports comma separated version numbers, with or without whitespace.
	 * - supports lower and upper bounds. (1.0-1.2)
	 * - returns true if the version matched.
	 *
	 * @param string $versions The versions that this package will install on
	 * @param bool $reset Whether to reset $near_version
	 * @param string $the_version The forum version
	 * @return string|bool Highest install value string or false
	 */
	function matchHighestPackageVersion(string $versions, bool $reset, string $the_version): string|bool
	{
		return SMF\PackageManager\SubsPackage::matchHighestPackageVersion($versions, $reset, $the_version);
	}

	/**
	 * Checks if the forum version matches any of the available versions from the package install xml.
	 * - supports comma separated version numbers, with or without whitespace.
	 * - supports lower and upper bounds. (1.0-1.2)
	 * - returns true if the version matched.
	 *
	 * @param string $version The forum version
	 * @param string $versions The versions that this package will install on
	 * @return bool Whether the version matched
	 */
	function matchPackageVersion(string $version, string $versions): bool
	{
		return SMF\PackageManager\SubsPackage::matchPackageVersion($version, $versions);
	}

	/**
	 * Compares two versions and determines if one is newer, older or the same, returns
	 * - (-1) if version1 is lower than version2
	 * - (0) if version1 is equal to version2
	 * - (1) if version1 is higher than version2
	 *
	 * @param string $version1 The first version
	 * @param string $version2 The second version
	 * @return int -1 if version2 is greater than version1, 0 if they're equal, 1 if version1 is greater than version2
	 */
	function compareVersions(string $version1, string $version2): int
	{
		return SMF\PackageManager\SubsPackage::compareVersions($version1, $version2);
	}

	/**
	 * Parses special identifiers out of the specified path.
	 *
	 * @param string $path The path
	 * @return string The parsed path
	 */
	function parse_path(string $path): string
	{
		return SMF\PackageManager\SubsPackage::parse_path($path);
	}

	/**
	 * Deletes a directory, and all the files and direcories inside it.
	 * requires access to delete these files.
	 *
	 * @param string $dir A directory
	 * @param bool $delete_dir If false, only deletes everything inside the directory but not the directory itself
	 */
	function deltree(string $dir, bool $delete_dir = true): void
	{
		SMF\PackageManager\SubsPackage::deltree($dir, $delete_dir);
	}

	/**
	 * Creates the specified tree structure with the mode specified.
	 * creates every directory in path until it finds one that already exists.
	 *
	 * @param string $strPath The path
	 * @param int $mode The permission mode for CHMOD (0666, etc.)
	 * @return bool True if successful, false otherwise
	 */
	function mktree(string $strPath, int $mode): bool
	{
		return SMF\PackageManager\SubsPackage::mktree($strPath, $mode);
	}

	/**
	 * Copies one directory structure over to another.
	 * requires the destination to be writable.
	 *
	 * @param string $source The directory to copy
	 * @param string $destination The directory to copy $source to
	 */
	function copytree(string $source, string $destination): void
	{
		SMF\PackageManager\SubsPackage::copytree($source, $destination);
	}

	/**
	 * Create a tree listing for a given directory path
	 *
	 * @param string $path The path
	 * @param string $sub_path The sub-path
	 * @return array An array of information about the files at the specified path/subpath
	 */
	function listtree(string $path, string $sub_path = ''): array
	{
		return SMF\PackageManager\SubsPackage::listtree($path, $sub_path);
	}

	/**
	 * Parses a xml-style modification file (file).
	 *
	 * @param string $file The modification file to parse
	 * @param bool $testing Whether we're just doing a test
	 * @param bool $undo If true, specifies that the modifications should be undone. Used when uninstalling. Doesn't work with regex.
	 * @param array $theme_paths An array of information about custom themes to apply the changes to
	 * @return array An array of those changes made.
	 */
	function parseModification(string $file, bool $testing = true, bool $undo = false, array $theme_paths = []): array
	{
		return SMF\PackageManager\SubsPackage::parseModification($file, $testing, $undo, $theme_paths);
	}

	/**
	 * Parses a boardmod-style (.mod) modification file
	 *
	 * @param string $file The modification file to parse
	 * @param bool $testing Whether we're just doing a test
	 * @param bool $undo If true, specifies that the modifications should be undone. Used when uninstalling.
	 * @param array $theme_paths An array of information about custom themes to apply the changes to
	 * @return array An array of those changes made.
	 */
	function parseBoardMod(string $file, bool $testing = true, bool $undo = false, array $theme_paths = []): array
	{
		return SMF\PackageManager\SubsPackage::parseBoardMod($file, $testing, $undo, $theme_paths);
	}

	/**
	 * Get the physical contents of a packages file
	 *
	 * @param string $filename The package file
	 * @return string The contents of the specified file
	 */
	function package_get_contents(string $filename): string
	{
		return SMF\PackageManager\SubsPackage::package_get_contents($filename);
	}

	/**
	 * Writes data to a file, almost exactly like the file_put_contents() function.
	 * uses FTP to create/chmod the file when necessary and available.
	 * uses text mode for text mode file extensions.
	 * returns the number of bytes written.
	 *
	 * @param string $filename The name of the file
	 * @param string $data The data to write to the file
	 * @param bool $testing Whether we're just testing things
	 * @return int The length of the data written (in bytes)
	 */
	function package_put_contents(string $filename, string $data, bool $testing = false): int
	{
		return SMF\PackageManager\SubsPackage::package_put_contents($filename, $data, $testing);
	}

	/**
	 * Flushes the cache from memory to the filesystem
	 *
	 * @param bool $trash
	 */
	function package_flush_cache(bool $trash = false): void
	{
		SMF\PackageManager\SubsPackage::package_flush_cache($trash);
	}

	/**
	 * Try to make a file writable.
	 *
	 * @param string $filename The name of the file
	 * @param string $perm_state The permission state - can be either 'writable' or 'execute'
	 * @param bool $track_change Whether to track this change
	 * @return bool True if it worked, false if it didn't
	 */
	function package_chmod(string $filename, string $perm_state = 'writable', bool $track_change = false): bool
	{
		return SMF\PackageManager\SubsPackage::package_chmod($filename, $perm_state, $track_change);
	}

	/**
	 * Used to crypt the supplied ftp password in this session
	 *
	 * @param string $pass The password
	 * @return string The encrypted password
	 */
	function package_crypt(#[\SensitiveParameter] string $pass): string
	{
		return SMF\PackageManager\SubsPackage::package_crypt($pass);
	}

	/**
	 * @param string $dir
	 * @param string $filename The filename without an extension
	 * @param string $ext
	 * @return string The filename with a number appended but no extension
	 * @since 2.1
	 */
	function package_unique_filename(string $dir, string $filename, string $ext): string
	{
		return SMF\PackageManager\SubsPackage::package_unique_filename($dir, $filename, $ext);
	}

	/**
	 * Creates a backup of forum files prior to modifying them
	 *
	 * @param string $id The name of the backup
	 * @return bool True if it worked, false if it didn't
	 */
	function package_create_backup(string $id = 'backup'): bool
	{
		return SMF\PackageManager\SubsPackage::package_create_backup($id);
	}

	/**
	 * Validate a package during install
	 *
	 * @param array $package Package data
	 * @return array Results from the package validation.
	 */
	function package_validate_installtest(array $package): array
	{
		return SMF\PackageManager\SubsPackage::package_validate_installtest($package);
	}

	/**
	 * Validate multiple packages.
	 *
	 * @param array $packages Package data
	 * @return array Results from the package validation.
	 */
	function package_validate(array $packages): array
	{
		return SMF\PackageManager\SubsPackage::package_validate($packages);
	}

	/**
	 * Sending data off to validate packages.
	 *
	 * @param array $sendData Json encoded data to be sent to the validation servers.
	 * @return array Results from the package validation.
	 */
	function package_validate_send(array $sendData): array
	{
		return SMF\PackageManager\SubsPackage::package_validate_send($sendData);
	}

	/******************************
	 * Begin SMF\PersonalMessage\PM
	 ******************************/

	/**
	 * Sends an personal message from the specified person to the specified people
	 * ($from defaults to the user)
	 *
	 * @param array $recipients An array containing the arrays 'to' and 'bcc', both containing id_member's.
	 * @param string $subject Should have no slashes and no html entities
	 * @param string $message Should have no slashes and no html entities
	 * @param bool $store_outbox Whether to store it in the sender's outbox
	 * @param array $from An array with the id, name, and username of the member.
	 * @param int $pm_head The ID of the chain being replied to - if any.
	 * @return array An array with log entries telling how many recipients were successful and which recipients it failed to send to.
	 */
	function sendpm(
		array $recipients,
		string $subject,
		string $message,
		bool $store_outbox = false,
		?array $from = null,
		int $pm_head = 0,
	): array {
		return SMF\PersonalMessage\PM::send($recipients, $subject, $message, $store_outbox, $from ?? null, $pm_head);
	}

	/**
	 * Delete the specified personal messages.
	 *
	 * @param array|null $personal_messages An array containing the IDs of PMs to delete or null to delete all of them
	 * @param string|null $folder Which "folder" to delete PMs from - 'sent' to delete them from the outbox, null or anything else to delete from the inbox
	 * @param array|int|null $owner An array of IDs of users whose PMs are being deleted, the ID of a single user or null to use the current user's ID
	 */
	function deleteMessages(?array $personal_messages, ?string $folder = null, array|int|null $owner = null): void
	{
		SMF\PersonalMessage\PM::delete($personal_messages, $folder, $owner);
	}

	/**
	 * Mark the specified personal messages read.
	 *
	 * @param array|null $personal_messages An array of PM IDs to mark or null to mark all
	 * @param int|null $label The ID of a label. If set, only messages with this label will be marked.
	 * @param int|null $owner If owner is set, marks messages owned by that member id
	 */
	function markMessages(?array $personal_messages = null, ?int $label = null, ?int $owner = null): void
	{
		SMF\PersonalMessage\PM::markRead($personal_messages, $label, $owner);
	}

	/**
	 * An error in the message...
	 *
	 * @param array $error_types An array of strings indicating which type of errors occurred
	 * @param array $named_recipients
	 * @param $recipient_ids
	 */
	function messagePostError(array $error_types, array $named_recipients, array $recipient_ids = []): void
	{
		SMF\PersonalMessage\PM::reportErrors($error_types, $named_recipients, $recipient_ids);
	}

	/**
	 * Check if the PM is available to the current user.
	 *
	 * @param int $pmID The ID of the PM
	 * @param string $validFor Which folders this is valud for - can be 'inbox', 'outbox' or 'in_or_outbox'
	 * @return bool Whether the PM is accessible in that folder for the current user
	 */
	function isAccessiblePM(int $pmID, string $folders = 'both'): bool
	{
		if ($folders === 'in_or_outbox') {
			$folders = 'both';
		}

		if ($folders === 'outbox') {
			$folders = 'sent';
		}

		if (!isset(SMF\PersonalMessage\PM::$loaded[$pmID])) {
			SMF\PersonalMessage\PM::load($pmID);
		}

		return SMF\PersonalMessage\PM::$loaded[$pmID]->canAccess($folders);
	}

	/********************************
	 * Begin SMF\PersonalMessage\Rule
	 ********************************/

	/**
	 * Load up all the rules for the current user.
	 *
	 * @param bool $reload Whether or not to reload all the rules from the database if $context['rules'] is set
	 */
	function LoadRules(bool $reload = false): array
	{
		return SMF\PersonalMessage\Rule::load($reload);
	}

	/**
	 * This will apply rules to all unread messages. If all_messages is set will, clearly, do it to all!
	 *
	 * @param bool $all_messages Whether to apply this to all messages or just unread ones
	 */
	function ApplyRules(bool $all_messages = false): void
	{
		SMF\PersonalMessage\Rule::apply($all_messages);
	}

	/****************************
	 * Begin SMF\Search\SearchApi
	 ****************************/

	/**
	 * Creates a search API and returns the object.
	 *
	 * @return search_api_interface An instance of the search API interface
	 */
	function findSearchAPI(): SMF\Search\SearchApiInterface
	{
		return SMF\Search\SearchApi::load();
	}

	/**
	 * Get the installed Search API implementations.
	 * This function checks for patterns in comments on top of the Search-API files!
	 * In addition to filenames pattern.
	 * It loads the search API classes if identified.
	 * This function is used by EditSearchMethod to list all installed API implementations.
	 *
	 * @return array Info about the detected search APIs.
	 */
	function loadSearchAPIs(): array
	{
		return SMF\Search\SearchApi::detect();
	}

	/*******************************
	 * Begin SMF\Search\SearchResult
	 *******************************/

	/**
	 * Highlighting matching string
	 *
	 * @param string $text Text to search through
	 * @param array $words List of keywords to search
	 *
	 * @return string Text with highlighted keywords
	 */
	function highlight(string $text, array $words): string
	{
		return SMF\Search\SearchResult::highlight($text, $words);
	}

	/******************************
	 * Begin SMF\Unicode\Utf8String
	 ******************************/

	/**
	 * Helper function for utf8_normalize_d and utf8_normalize_kd.
	 *
	 * @param array $chars Array of Unicode characters
	 * @param bool $compatibility If true, perform compatibility decomposition. Default false.
	 * @return array Array of decomposed Unicode characters.
	 */
	function utf8_decompose(array $chars, bool $compatibility = false): array
	{
		return SMF\Unicode\Utf8String::decompose($chars, $compatibility);
	}

	/**
	 * Helper function for utf8_normalize_c and utf8_normalize_kc.
	 *
	 * @param array $chars Array of decomposed Unicode characters
	 * @return array Array of composed Unicode characters.
	 */
	function utf8_compose(array $chars): array
	{
		return SMF\Unicode\Utf8String::compose($chars);
	}

	/**
	 * Converts the given UTF-8 string into lowercase.
	 * Equivalent to mb_strtolower($string, 'UTF-8'), except that we can keep the
	 * output consistent across PHP versions and up to date with the latest version
	 * of Unicode.
	 *
	 * @param string $string The string
	 * @return string The lowercase version of $string
	 */
	function utf8_strtolower(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->convertCase('lower');
	}

	/**
	 * Convert the given UTF-8 string to uppercase.
	 * Equivalent to mb_strtoupper($string, 'UTF-8'), except that we can keep the
	 * output consistent across PHP versions and up to date with the latest version
	 * of Unicode.
	 *
	 * @param string $string The string
	 * @return string The uppercase version of $string
	 */
	function utf8_strtoupper(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->convertCase('upper');
	}

	/**
	 * Casefolds the given UTF-8 string.
	 * Equivalent to mb_convert_case($string, MB_CASE_FOLD, 'UTF-8'), except that
	 * we can keep the output consistent across PHP versions and up to date with
	 * the latest version of Unicode.
	 *
	 * @param string $string The string
	 * @return string The uppercase version of $string
	 */
	function utf8_casefold(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->convertCase('fold');
	}

	/**
	 * Converts the case of the given UTF-8 string.
	 *
	 * @param string $string The string.
	 * @param string $case One of 'upper', 'lower', 'fold', 'title', 'ucfirst', or 'ucwords'.
	 * @param bool $simple If true, use simple maps instead of full maps. Default: false.
	 * @return string A version of $string converted to the specified case.
	 */
	function utf8_convert_case(string $string, string $case, bool $simple = false): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->convertCase($case, $simple);
	}

	/**
	 * Normalizes UTF-8 via Canonical Decomposition.
	 *
	 * @param string $string A UTF-8 string
	 * @return string The decomposed version of $string
	 */
	function utf8_normalize_d(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->normalize('d');
	}

	/**
	 * Normalizes UTF-8 via Compatibility Decomposition.
	 *
	 * @param string $string A UTF-8 string.
	 * @return string The decomposed version of $string.
	 */
	function utf8_normalize_kd(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->normalize('kd');
	}

	/**
	 * Normalizes UTF-8 via Canonical Decomposition then Canonical Composition.
	 *
	 * @param string $string A UTF-8 string
	 * @return string The composed version of $string
	 */
	function utf8_normalize_c(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->normalize('c');
	}

	/**
	 * Normalizes UTF-8 via Compatibility Decomposition then Canonical Composition.
	 *
	 * @param string $string The string
	 * @return string The composed version of $string
	 */
	function utf8_normalize_kc(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->normalize('kc');
	}

	/**
	 * Casefolds UTF-8 via Compatibility Composition Casefolding.
	 * Used by idn_to_ascii polyfill in Subs-Compat.php
	 *
	 * @param string $string The string
	 * @return string The casefolded version of $string
	 */
	function utf8_normalize_kc_casefold(string $string): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->normalize('ks_casefold');
	}

	/**
	 * Checks whether a string is already normalized to a given form.
	 *
	 * @param string|array $string A string of UTF-8 characters.
	 * @param string $form One of 'd', 'c', 'kd', 'kc', or 'kc_casefold'
	 * @return bool Whether the string is already normalized to the given form.
	 */
	function utf8_is_normalized(string $string, string $form): bool
	{
		return SMF\Unicode\Utf8String::create($string)->isNormalized($form);
	}

	/**
	 * Helper function for sanitize_chars() that deals with invisible characters.
	 *
	 * This function deals with control characters, private use characters,
	 * non-characters, and characters that are invisible by definition in the
	 * Unicode standard. It does not deal with characters that are supposed to be
	 * visible according to the Unicode standard, and makes no attempt to compensate
	 * for possibly incomplete Unicode support in text rendering engines on client
	 * devices.
	 *
	 * @param string $string The string to sanitize.
	 * @param int $level Controls how invisible formatting characters are handled.
	 *      0: Allow valid formatting characters. Use for sanitizing text in posts.
	 *      1: Allow necessary formatting characters. Use for sanitizing usernames.
	 *      2: Disallow all formatting characters. Use for internal comparisions
	 *         only, such as in the word censor, search contexts, etc.
	 * @param string $substitute Replacement string for the invalid characters.
	 * @return string The sanitized string.
	 */
	function utf8_sanitize_invisibles(string $string, int $level, string $substitute): string
	{
		return (string) SMF\Unicode\Utf8String::create($string)->sanitizeInvisibles($level, $substitute);
	}

	/********************************
	 * Begin SMF\WebFetch\WebFetchApi
	 ********************************/

	/**
	 * Get the contents of a URL, irrespective of allow_url_fopen.
	 *
	 * - reads the contents of an http or ftp address and returns the page in a string
	 * - will accept up to 3 page redirections (redirectio_level in the function call is private)
	 * - if post_data is supplied, the value and length is posted to the given url as form data
	 * - URL must be supplied in lowercase
	 *
	 * @param string $url The URL
	 * @param string $post_data The data to post to the given URL
	 * @param bool $keep_alive Whether to send keepalive info
	 * @param int $redirection_level How many levels of redirection
	 * @return string|false The fetched data or false on failure
	 */
	function fetch_web_data(string $url, string|array $post_data = [], bool $keep_alive = false): string|false
	{
		return SMF\WebFetch\WebFetchApi::fetch($url, $post_data, $keep_alive);
	}

	/*****************
	 * Begin SMF\Alert
	 *****************/

	/**
	 * Fetch the alerts a member currently has.
	 *
	 * @param int $memID The ID of the member.
	 * @param mixed $to_fetch Alerts to fetch: true/false for all/unread, or a list of one or more IDs.
	 * @param array $limit Maximum number of alerts to fetch (0 for no limit).
	 * @param array $offset Number of alerts to skip for pagination. Ignored if $to_fetch is a list of IDs.
	 * @param bool $with_avatar Whether to load the avatar of the alert sender.
	 * @param bool $show_links Whether to show links in the constituent parts of the alert meessage.
	 * @return array An array of information about the fetched alerts.
	 */
	function fetch_alerts(
		int $memID,
		int|bool|array $to_fetch = false,
		int $limit = 0,
		int $offset = 0,
		bool $with_avatar = false,
		bool $show_links = false,
	): array {
		if (!is_bool($to_fetch) && !is_array($to_fetch)) {
			$to_fetch = (array) $to_fetch;
		}

		return SMF\Alert::fetch($memID, $to_fetch, $limit, $offset, $with_avatar, $show_links);
	}

	/**
	 * Counts how many alerts a user has - either unread or all depending on $unread
	 * We can't use db_num_rows here, as we have to determine what boards the user can see
	 * Possibly in future versions as database support for json is mainstream, we can simplify this.
	 *
	 * @param int $memID The user ID.
	 * @param bool $unread Whether to only count unread alerts.
	 * @return int The number of requested alerts
	 */
	function alert_count(int $memID, bool $unread = false): int
	{
		return SMF\Alert::count($memID, $unread);
	}

	/**
	 * Marks a group of alerts as un/read
	 *
	 * @param array|int $members The user IDs.
	 * @param bool|int $read To mark as read or unread, 1 for read, 0 or any other value different than 1 for unread.
	 * @param array|int $toMark The ID of a single alert or an array of IDs. The function will convert single integers to arrays for better handling.
	 * @return int How many alerts remain unread
	 */
	function alert_mark(array|int $members, array|int $to_mark, bool|int $read): int
	{
		SMF\Alert::mark($members, $to_mark, (bool) $read);

		return SMF\Alert::count($memID, $unread);
	}

	/**
	 * Deletes a single or a group of alerts by ID
	 *
	 * @param int|array The ID of a single alert to delete or an array containing the IDs of multiple alerts. The function will convert integers into an array for better handling.
	 * @param bool|int $memID The user ID. Used to update the user unread alerts count.
	 * @return ?int If the $memID param is set, returns the new amount of unread alerts.
	 */
	function alert_delete(int|array $ids, int|array $members = []): ?int
	{
		SMF\Alert::delete($ids, $members);
	}

	/**
	 * Deletes all the alerts that a user has already read.
	 *
	 * @param int $memID The user ID. Defaults to the current user's ID.
	 */
	function alert_purge(int $memID = 0, int $before = 0): void
	{
		SMF\Alert::purge($memID, $before);
	}

	/**********************
	 * Begin SMF\Attachment
	 **********************/

	/**
	 * Check if the current directory is still valid or not.
	 * If not creates the new directory
	 *
	 * @return ?bool False if any error occurred
	 */
	function automanage_attachments_check_directory(): ?bool
	{
		return SMF\Attachment::automanageCheckDirectory();
	}

	/**
	 * Creates a directory
	 *
	 * @param string $updir The directory to be created
	 *
	 * @return bool False on errors
	 */
	function automanage_attachments_create_directory(string $updir): bool
	{
		return SMF\Attachment::automanageCreateDirectory($updir);
	}

	/**
	 * Called when a directory space limit is reached.
	 * Creates a new directory and increments the directory suffix number.
	 *
	 * @return ?bool False on errors, true if successful, nothing if auto-management of attachments is disabled
	 */
	function automanage_attachments_by_space(): ?bool
	{
		return SMF\Attachment::automanageBySpace();
	}

	/**
	 * Moves an attachment to the proper directory and set the relevant data into $_SESSION['temp_attachments']
	 */
	function processAttachments(): void
	{
		SMF\Attachment::process();
	}

	/**
	 * Performs various checks on an uploaded file.
	 * - Requires that $_SESSION['temp_attachments'][$attachID] be properly populated.
	 *
	 * @param int $attachID The ID of the attachment
	 * @return bool Whether the attachment is OK
	 */
	function attachmentChecks(int $attachID): bool
	{
		return SMF\Attachment::check($attachID);
	}

	/**
	 * Create an attachment, with the given array of parameters.
	 * - Adds any additional or missing parameters to $attachmentOptions.
	 * - Renames the temporary file.
	 * - Creates a thumbnail if the file is an image and the option enabled.
	 *
	 * @param array $attachmentOptions An array of attachment options
	 * @return bool Whether the attachment was created successfully
	 */
	function createAttachment(&$attachmentOptions): bool
	{
		return SMF\Attachment::create($attachmentOptions);
	}

	/**
	 * Assigns the given attachments to the given message ID.
	 *
	 * @param $attachIDs array of attachment IDs to assign.
	 * @param $msgID integer the message ID.
	 *
	 * @return bool false on error or missing params.
	 */
	function assignAttachments(array $attachIDs = [], int $msgID = 0): bool
	{
		return SMF\Attachment::assign($attachIDs, $msgID);
	}

	/**
	 * Approve an attachment, or maybe even more - no permission check!
	 *
	 * @param array $attachments The IDs of the attachments to approve
	 * @return ?int Returns 0 if the operation failed, otherwise returns nothing
	 */
	function ApproveAttachments(array $attachments): ?int
	{
		return SMF\Attachment::approve($attachments);
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
	 * @return ?array Returns an array containing IDs of affected messages if $return_affected_messages is true
	 */
	function removeAttachments(
		$condition,
		$query_type = '',
		$return_affected_messages = false,
		$autoThumbRemoval = true,
	): ?array {
		return SMF\Attachment::remove(
			$condition,
			$query_type,
			$return_affected_messages,
			$autoThumbRemoval,
		);
	}

	/**
	 * Gets an attach ID and tries to load all its info.
	 *
	 * @param int $attachID the attachment ID to load info from.
	 *
	 * @return mixed If succesful, it will return an array of loaded data. String, most likely a $txt key if there was some error.
	 */
	function parseAttachBBC(int $attachID = 0): array|string
	{
		return SMF\Attachment::parseAttachBBC($attachID);
	}

	/**
	 * Gets all needed message data associated with an attach ID
	 *
	 * @param int $attachID the attachment ID to load info from.
	 *
	 * @return array
	 */
	function getAttachMsgInfo(int $attachID): SMF\Attachment|array
	{
		return SMF\Attachment::getAttachMsgInfo($attachID);
	}

	/**
	 * This loads an attachment's contextual data including, most importantly, its size if it is an image.
	 * It requires the view_attachments permission to calculate image size.
	 * It attempts to keep the "aspect ratio" of the posted image in line, even if it has to be resized by
	 * the max_image_width and max_image_height settings.
	 *
	 * @param int $id_msg ID of the post to load attachments for
	 * @param array $attachments  An array of already loaded attachments. This function no longer depends on having $topic declared, thus, you need to load the actual topic ID for each attachment.
	 * @return array An array of attachment info
	 */
	function loadAttachmentContext(int $id_msg, array $attachments): array
	{
		return SMF\Attachment::loadAttachmentContext($id_msg, $attachments);
	}

	/**
	 * prepare the Attachment api for all messages
	 *
	 * @param int array $msgIDs the message ID to load info from.
	 */
	function prepareAttachsByMsg(array $msgIDs): void
	{
		SMF\Attachment::prepareByMsg($msgIDs);
	}

	/**
	 * Backward compatibility only.
	 *
	 * New code should use Attachment::getFilePath() or Attachment::createHash()
	 * to get whichever type of output is desired for a given situation.
	 *
	 * Get an attachment's encrypted filename. If $new is true, won't check for
	 * file existence.
	 *
	 * This currently returns the hash if new, and the full filename otherwise,
	 * which is very messy. And of course everything that calls this function
	 * relies on that behavior and works around it. :P
	 *
	 * @param string $filename The name of the file. (Ignored.)
	 * @param int $attachment_id The ID of the attachment.
	 * @param ?string $dir Which directory it should be in. (Ignored.)
	 * @param bool $new Whether this is a new attachment.
	 * @param string $file_hash The file hash.  (Ignored.)
	 * @return string A hash or the path to the file.
	 */
	function getAttachmentFilename(
		string $filename,
		int $attachment_id,
		?string $dir = null,
		bool $new = false,
		string $file_hash = '',
	): string {
		// Just make up a nice hash...
		if ($new || empty($attachment_id)) {
			return SMF\Attachment::createHash();
		}

		return SMF\Attachment::getFilePath($attachment_id);
	}

	/********************************
	 * Begin SMF\Parsers\BBCodeParser
	 ********************************/

	/**
	 * Return an array with allowed bbc tags for signatures, that can be passed to parse_bbc().
	 *
	 * @return array An array containing allowed tags for signatures, or an empty array if all tags are allowed.
	 */
	function get_signature_allowed_bbc_tags(): array
	{
		return SMF\Parser::getSigTags();
	}

	/**
	 * Highlight any code.
	 *
	 * Uses PHP's highlight_string() to highlight PHP syntax
	 * does special handling to keep the tabs in the code available.
	 * used to parse PHP code from inside [code] and [php] tags.
	 *
	 * @param string $code The code
	 * @return string The code with highlighted HTML.
	 */
	function highlight_php_code(string $code): string
	{
		return SMF\Parser::highlightPhpCode($code);
	}

	/**
	 * Parse bulletin board code in a string, as well as smileys optionally.
	 *
	 * - only parses bbc tags which are not disabled in disabledBBC.
	 * - handles basic HTML, if enablePostHTML is on.
	 * - caches the from/to replace regular expressions so as not to reload them every time a string is parsed.
	 * - only parses smileys if smileys is true.
	 * - does nothing if the enableBBC setting is off.
	 * - uses the cache_id as a unique identifier to facilitate any caching it may do.
	 * - returns the modified message.
	 *
	 * @param string|bool $message The message.
	 *		When a empty string, nothing is done.
	 *		When false we provide a list of BBC codes available.
	 *		When a string, the message is parsed and bbc handled.
	 * @param bool $smileys Whether to parse smileys as well
	 * @param string $cache_id The cache ID
	 * @param array $parse_tags If set, only parses these tags rather than all of them
	 * @return string The parsed message
	 */
	function parse_bbc(
		string|bool $message,
		bool|string $smileys = true,
		string $cache_id = '',
		array $parse_tags = [],
	): string|array {
		if ($message === false) {
			return SMF\Parser::getBBCodes();
		}

		return SMF\Parser::transform(
			string: $message,
			input_types: SMF\Parser::INPUT_BBC | SMF\Parser::INPUT_MARKDOWN | (!empty($smileys) ? SMF\Parser::INPUT_SMILEYS : 0),
			options: [
				'cache_id' => $cache_id,
				'parse_tags' => $parse_tags,
				'for_print' => $smileys === 'print',
			],
		);
	}

	/**
	 * Parse smileys in the passed message.
	 *
	 * The smiley parsing function which makes pretty faces appear :).
	 * If custom smiley sets are turned off by smiley_enable, the default set of smileys will be used.
	 * These are specifically not parsed in code tags [url=mailto:Dad@blah.com]
	 * Caches the smileys from the database or array in memory.
	 * Doesn't return anything, but rather modifies message directly.
	 *
	 * @param string &$message The message to parse smileys in
	 */
	function parseSmileys(string &$message): void
	{
		$message = SMF\Parser::transform($message, SMF\Parser::INPUT_SMILEYS);
	}

	/**
	 * Converts HTML to BBC
	 * As of SMF 2.1, only used by ManageBoards.php (and possibly mods)
	 *
	 * @param string $text Text containing HTML
	 * @return string The text with html converted to bbc
	 */
	function html_to_bbc(string $string): string
	{
		// We want to ignore Markdown in this backward compatibility function.
		return SMF\Parser::transform(
			string: $string,
			input_types: SMF\Parser::INPUT_BBC | SMF\Parser::INPUT_SMILEYS,
			output_type: SMF\Parser::OUTPUT_BBC,
		);
	}

	/*****************
	 * Begin SMF\Board
	 *****************/

	/**
	 * Check for moderators and see if they have access to the board.
	 * What it does:
	 * - sets up the $board_info array for current board information.
	 * - if cache is enabled, the $board_info array is stored in cache.
	 * - redirects to appropriate post if only message id is requested.
	 * - is only used when inside a topic or board.
	 * - determines the local moderators for the board.
	 * - adds group id 3 if the user is a local moderator for the board they are in.
	 * - prevents access if user is not in proper group nor a local moderator of the board.
	 */
	function loadBoard(): array
	{
		return SMF\Board::load();
	}

	/**
	 * Mark a board or multiple boards read.
	 *
	 * @param int|array $boards The ID of a single board or an array of boards
	 * @param bool $unread Whether we're marking them as unread
	 */
	function markBoardsRead(int|array $boards, bool $unread = false): void
	{
		SMF\Board::markBoardsRead($boards, $unread);
	}

	/**
	 * Get the id_member associated with the specified message.
	 *
	 * @param int $messageID The ID of the message
	 * @return int The ID of the member associated with that post
	 */
	function getMsgMemberID(int $messageID): int
	{
		return SMF\Board::getMsgMemberID($messageID);
	}

	/**
	 * Modify the settings and position of a board.
	 * Used by ManageBoards.php to change the settings of a board.
	 *
	 * @param int $board_id The ID of the board
	 * @param array &$boardOptions An array of options related to the board
	 */
	function modifyBoard(int $board_id, array &$boardOptions): void
	{
		SMF\Board::modify($board_id, $boardOptions);
	}

	/**
	 * Create a new board and set its properties and position.
	 * Allows (almost) the same options as the modifyBoard() function.
	 * With the option inherit_permissions set, the parent board permissions
	 * will be inherited.
	 *
	 * @param array $boardOptions An array of information for the new board
	 * @return int The ID of the new board
	 */
	function createBoard(array $boardOptions): int
	{
		return SMF\Board::create($boardOptions);
	}

	/**
	 * Remove one or more boards.
	 * Allows to move the children of the board before deleting it
	 * if moveChildrenTo is set to null, the child boards will be deleted.
	 * Deletes:
	 *   - all topics that are on the given boards;
	 *   - all information that's associated with the given boards;
	 * updates the statistics to reflect the new situation.
	 *
	 * @param array $boards_to_remove The boards to remove
	 * @param int $moveChildrenTo The ID of the board to move the child boards to (null to remove the child boards, 0 to make them a top-level board)
	 */
	function deleteBoards(array $boards_to_remove, ?int $moveChildrenTo = null): void
	{
		SMF\Board::delete($boards_to_remove, $moveChildrenTo);
	}

	/**
	 * Put all boards in the right order and sorts the records of the boards table.
	 * Used by modifyBoard(), deleteBoards(), modifyCategory(), and deleteCategories() functions
	 */
	function reorderBoards(): void
	{
		SMF\Board::reorder();
	}

	/**
	 * Fixes the children of a board by setting their child_levels to new values.
	 * Used when a board is deleted or moved, to affect its children.
	 *
	 * @param int $parent The ID of the parent board
	 * @param int $newLevel The new child level for each of the child boards
	 * @param int $newParent The ID of the new parent board
	 */
	function fixChildren(int $parent, int $newLevel, int $newParent): void
	{
		SMF\Board::fixChildren($parent, $newLevel, $newParent);
	}

	/**
	 * Takes a board array and sorts it
	 *
	 * @param array &$boards The boards
	 */
	function sortBoards(array &$boards): void
	{
		SMF\Board::sort($boards);
	}

	/**
	 * Returns the given board's moderators, with their names and links
	 *
	 * @param array $boards The boards to get moderators of
	 * @return array An array containing information about the moderators of each board
	 */
	function getBoardModerators(array $boards): array
	{
		return SMF\Board::getModerators($boards);
	}

	/**
	 * Returns board's moderator groups with their names and link
	 *
	 * @param array $boards The boards to get moderator groups of
	 * @return array An array containing information about the groups assigned to moderate each board
	 */
	function getBoardModeratorGroups(array $boards): array
	{
		return SMF\Board::getModeratorGroups($boards);
	}

	/**
	 * Returns whether the child board id is actually a child of the parent (recursive).
	 *
	 * @param int $child The ID of the child board
	 * @param int $parent The ID of a parent board
	 * @return bool Whether the specified child board is actually a child of the specified parent board.
	 */
	function isChildOf(int $child, int $parent): bool
	{
		return SMF\Board::isChildOf($child, $parent);
	}

	/**
	 * Get all parent boards (requires first parent as parameter)
	 * It finds all the parents of id_parent, and that board itself.
	 * Additionally, it detects the moderators of said boards.
	 *
	 * @param int $id_parent The ID of the parent board
	 * @return array An array of information about the boards found.
	 */
	function getBoardParents(int $id_parent): array
	{
		return SMF\Board::getParents($id_parent);
	}

	/***************************
	 * Begin SMF\BrowserDetector
	 ***************************/

	/**
	 * Loads information about what browser the user is viewing with and places it in $context
	 *  - uses the class from {@link Class-BrowserDetect.php}
	 */
	function detectBrowser(): void
	{
		SMF\BrowserDetector::call();
	}

	/**
	 * Are we using this browser?
	 *
	 * Wrapper function for detectBrowser
	 *
	 * @param string $browser The browser we are checking for.
	 * @return bool Whether or not the current browser is what we're looking for
	 */
	function isBrowser(string $browser): bool
	{
		return SMF\BrowserDetector::isBrowser($browser);
	}

	/********************
	 * Begin SMF\Category
	 ********************/

	/**
	 * Edit the position and properties of a category.
	 * general function to modify the settings and position of a category.
	 * used by ManageBoards.php to change the settings of a category.
	 *
	 * @param int $category_id The ID of the category
	 * @param array $catOptions An array containing data and options related to the category
	 */
	function modifyCategory(int $category_id, array $catOptions): void
	{
		SMF\Category::modify($category_id, $catOptions);
	}

	/**
	 * Create a new category.
	 * general function to create a new category and set its position.
	 * allows (almost) the same options as the modifyCat() function.
	 * returns the ID of the newly created category.
	 *
	 * @param array $catOptions An array of data and settings related to the new category. Should have at least 'cat_name' and can also have 'cat_desc', 'move_after' and 'is_collapsable'
	 * @return mixed
	 */
	function createCategory(array $catOptions): int
	{
		return SMF\Category::create($catOptions);
	}

	/**
	 * Remove one or more categories.
	 * general function to delete one or more categories.
	 * allows to move all boards in the categories to a different category before deleting them.
	 * if moveChildrenTo is set to null, all boards inside the given categories will be deleted.
	 * deletes all information that's associated with the given categories.
	 * updates the statistics to reflect the new situation.
	 *
	 * @param array $categories The IDs of the categories to delete
	 * @param int $moveBoardsTo The ID of the category to move any boards to or null to delete the boards
	 */
	function deleteCategories(array $categories, ?int $moveBoardsTo = null): void
	{
		SMF\Category::delete($categories, $moveBoardsTo);
	}

	/**
	 * Takes a category array and sorts it
	 *
	 * @param array &$categories The categories
	 */
	function sortCategories(array &$categories): void
	{
		SMF\Category::sort($categories);
	}

	/**
	 * Tries to load up the entire board order and category very very quickly
	 * Returns an array with two elements, cats and boards
	 *
	 * @return array An array of categories and boards
	 */
	function getTreeOrder(): array
	{
		return SMF\Category::getTreeOrder();
	}

	/**
	 * Load a lot of useful information regarding the boards and categories.
	 * The information retrieved is stored in globals:
	 *  $boards		properties of each board.
	 *  $boardList	a list of boards grouped by category ID.
	 *  $cat_tree	properties of each category.
	 */
	function getBoardTree(): void
	{
		SMF\Category::getTree();
	}

	/**
	 * Recursively get a list of boards.
	 * Used by getBoardTree
	 *
	 * @param array &$_boardList The board list
	 * @param array &$_tree The board tree
	 */
	function recursiveBoards(&$list, &$tree): void
	{
		SMF\Category::recursiveBoards($list, $tree);
	}

	/******************
	 * Begin SMF\Cookie
	 ******************/

	/**
	 * Sets the SMF-style login cookie and session based on the id_member and password passed.
	 * - password should be already encrypted with the cookie salt.
	 * - logs the user out if id_member is zero.
	 * - sets the cookie and session to last the number of seconds specified by cookie_length, or
	 *   ends them if cookie_length is less than 0.
	 * - when logging out, if the globalCookies setting is enabled, attempts to clear the subdomain's
	 *   cookie too.
	 *
	 * @param int $cookie_length How many seconds the cookie should last. If negative, forces logout.
	 * @param int $id The ID of the member to set the cookie for
	 * @param string $password The hashed password
	 */
	function setLoginCookie(int $cookie_length, int $id, string $password = ''): void
	{
		SMF\Cookie::setLoginCookie($cookie_length, $id, $password);
	}

	/**
	 * Sets Two Factor Auth cookie
	 *
	 * @param int $cookie_length How long the cookie should last, in seconds
	 * @param int $id The ID of the member
	 * @param string $secret Should be a salted secret using hash_salt
	 */
	function setTFACookie(int $cookie_length, int $id, string $secret): void
	{
		SMF\Cookie::setTFACookie($cookie_length, $id, $secret);
	}

	/**
	 * Get the domain and path for the cookie
	 * - normally, local and global should be the localCookies and globalCookies settings, respectively.
	 * - uses boardurl to determine these two things.
	 *
	 * @param bool $local Whether we want local cookies
	 * @param bool $global Whether we want global cookies
	 * @return array An array to set the cookie on with domain and path in it, in that order
	 */
	function url_parts(bool $local, bool $global): array
	{
		return SMF\Cookie::urlParts($local, $global);
	}

	/**
	 * Hashes password with salt and authentication secret. This is solely used for cookies.
	 *
	 * @param string $password The password
	 * @param string $salt The salt
	 * @return string The hashed password
	 */
	function hash_salt(string $password, string $salt): string
	{
		return SMF\Cookie::encrypt($password, $salt);
	}

	/**
	 * A wrapper for setcookie that gives integration hook access to it
	 *
	 * @param string $name
	 * @param string $value = ''
	 * @param int $expires = 0
	 * @param string $path = ''
	 * @param string $domain = ''
	 * @param ?bool $secure = null
	 * @param bool $httponly = true
	 * @param ?string $samesite = null
	 */
	function smf_setcookie(
		string $name,
		string $value = '',
		int $expires = 0,
		string $path = '',
		string $domain = '',
		?bool $secure = null,
		bool $httponly = true,
		?string $samesite = null,
	): void {
		$data = SMF\Utils::jsonDecode($value);
		$cookie = new SMF\Cookie($name, $data, $expires, $domain, $path, $secure, $httponly, $samesite);
		$cookie->set();

	}

	/*****************
	 * Begin SMF\Draft
	 *****************/

	/**
	 * Deletes one or many drafts from the DB
	 * Validates the drafts are from the user
	 * is supplied an array of drafts will attempt to remove all of them
	 *
	 * @param int $id_draft The ID of the draft to delete
	 * @param bool $check Whether or not to check that the draft belongs to the current user
	 * @return bool False if it couldn't be deleted (doesn't return anything otherwise)
	 */
	function DeleteDraft(int $id_draft, bool $check = true): bool
	{
		return SMF\Draft::delete((array) $id_draft, $check);
	}

	/**
	 * Loads in a group of drafts for the user of a given type (0/posts, 1/pm's)
	 * loads a specific draft for forum use if selected.
	 * Used in the posting screens to allow draft selection
	 * Will load a draft if selected is supplied via post
	 *
	 * @param int $member_id ID of the member to show drafts for.
	 * @param bool|inr $reply_to ID of the topic or PM being replied to.
	 * @param int $draft_type The type of drafts to show: 0 = post, 1 = PM.
	 * @return bool False if the drafts couldn't be loaded, nothing otherwise
	 */
	function ShowDrafts(int $member_id, int $reply_to = 0, int $draft_type = 0): bool
	{
		if ($draft_type === 1) {
			return SMF\DraftPM::showInEditor($member_id, $reply_to);
		}

		return SMF\Draft::showInEditor($member_id, $reply_to);
	}

	/**
	 * Show all drafts of a given type by the current user
	 * Uses the showdraft template
	 * Allows for the deleting and loading/editing of drafts
	 *
	 * @param int $memID
	 * @param int $draft_type
	 */
	function showProfileDrafts(int $memID, int $draft_type = 0): void
	{
		if ($draft_type === 1) {
			SMF\DraftPM::showInProfile($memID);
		}

		SMF\Draft::showInProfile($memID);
	}

	/******************
	 * Begin SMF\Editor
	 ******************/

	/**
	 * Creates a box that can be used for richedit stuff like BBC, Smileys etc.
	 *
	 * @param array $editorOptions Various options for the editor
	 */
	function create_control_richedit(array $options): SMF\Editor
	{
		return SMF\Editor::load($options);
	}

	/**
	 * Retrieves a list of message icons.
	 * - Based on the settings, the array will either contain a list of default
	 *   message icons or a list of custom message icons retrieved from the database.
	 * - The board_id is needed for the custom message icons (which can be set for
	 *   each board individually).
	 *
	 * @param int $board_id The ID of the board
	 * @return array An array of info about available icons
	 */
	function getMessageIcons(int $board_id): array
	{
		return SMF\Editor::getMessageIcons($board_id);
	}

	/************************
	 * Begin SMF\ErrorHandler
	 ************************/

	/**
	 * Handler for standard error messages, standard PHP error handler replacement.
	 * It dies with fatal_error() if the error_level matches with error_reporting.
	 *
	 * @param int $error_level A pre-defined error-handling constant (see {@link https://php.net/errorfunc.constants})
	 * @param string $error_string The error message
	 * @param string $file The file where the error occurred
	 * @param int $line The line where the error occurred
	 */
	function smf_error_handler(int $error_level, string $error_string, string $file, int $line): void
	{
		SMF\ErrorHandler::call($error_level, $error_string, $file, $line);
	}

	/**
	 * Log an error, if the error logging is enabled.
	 * filename and line should be __FILE__ and __LINE__, respectively.
	 * Example use:
	 *  die(log_error($msg));
	 *
	 * @param string $error_message The message to log
	 * @param string|bool $error_type The type of error
	 * @param string $file The name of the file where this error occurred
	 * @param int $line The line where the error occurred
	 * @return string The message that was logged
	 */
	function log_error(
		string $error_message,
		string|bool $error_type = 'general',
		string $file = '',
		int $line = 0,
	): string {
		return SMF\ErrorHandler::log(
			$error_message,
			$error_type,
			$file,
			$line,
		);
	}

	/**
	 * An irrecoverable error. This function stops execution and displays an error message.
	 * It logs the error message if $log is specified.
	 *
	 * @param string $error The error message
	 * @param string|bool $log = 'general' What type of error to log this as (false to not log it))
	 * @param int $status The HTTP status code associated with this error
	 */
	function fatal_error(string $error, string|bool $log = 'general', int $status = 500): void
	{
		SMF\ErrorHandler::fatal($error, $log, $status);
	}

	/**
	 * Shows a fatal error with a message stored in the language file.
	 *
	 * This function stops execution and displays an error message by key.
	 *  - uses the string with the error_message_key key.
	 *  - logs the error in the forum's default language while displaying the error
	 *    message in the user's language.
	 *  - uses Errors language file and applies the $sprintf information if specified.
	 *  - the information is logged if log is specified.
	 *
	 * @param string $error The error message
	 * @param string|false $log The type of error, or false to not log it
	 * @param array $sprintf An array of data to be sprintf()'d into the specified message
	 * @param int $status = false The HTTP status code associated with this error
	 */
	function fatal_lang_error(string $error, string|bool $log = 'general', array $sprintf = [], int $status = 403)
	{
		SMF\ErrorHandler::fatalLang($error, $log, $sprintf, $status);
	}

	/**
	 * Show a message for the (full block) maintenance mode.
	 * It shows a complete page independent of language files or themes.
	 * It is used only if $maintenance = 2 in Settings.php.
	 * It stops further execution of the script.
	 */
	function display_maintenance_message(): void
	{
		SMF\ErrorHandler::displayMaintenanceMessage();
	}

	/**
	 * Show an error message for the connection problems.
	 * It shows a complete page independent of language files or themes.
	 * It is used only if there's no way to connect to the database.
	 * It stops further execution of the script.
	 */
	function display_db_error(): void
	{
		SMF\ErrorHandler::displayDbError();
	}

	/**
	 * Show an error message for load average blocking problems.
	 * It shows a complete page independent of language files or themes.
	 * It is used only if the load averages are too high to continue execution.
	 * It stops further execution of the script.
	 */
	function display_loadavg_error(): void
	{
		SMF\ErrorHandler::displayLoadAvgError();
	}

	/*****************
	 * Begin SMF\Event
	 *****************/

	/**
	 * Consolidating the various INSERT statements into this function.
	 * Inserts the passed event information into the calendar table.
	 * Allows to either set a time span (in days) or an end_date.
	 * Does not check any permissions of any sort.
	 *
	 * @param array $eventOptions An array of event options ('title', 'span', 'start_date', 'end_date', etc.)
	 */
	function insertEvent(array $eventOptions): void
	{
		SMF\Calendar\Event::create($eventOptions);
	}

	/**
	 * modifies an event.
	 * allows to either set a time span (in days) or an end_date.
	 * does not check any permissions of any sort.
	 *
	 * @param int $event_id The ID of the event
	 * @param array $eventOptions An array of event information
	 */
	function modifyEvent(int $event_id, array &$eventOptions): void
	{
		SMF\Calendar\Event::modify($event_id, $eventOptions);
	}

	/**
	 * Remove an event
	 * removes an event.
	 * does no permission checks.
	 *
	 * @param int $event_id The ID of the event to remove
	 */
	function removeEvent(int $event_id): void
	{
		SMF\Calendar\Event::remove($event_id);
	}

	/*****************
	 * Begin SMF\Group
	 *****************/

	/**
	 * Retrieve a list of (visible) membergroups used by the cache.
	 *
	 * @return array An array of information about the cache
	 */
	function cache_getMembergroupList(): array
	{
		return SMF\Group::getCachedList();
	}

	/***************************
	 * Begin SMF\IntegrationHook
	 ***************************/

	/**
	 * Process functions of an integration hook.
	 * calls all functions of the given hook.
	 * supports static class method calls.
	 *
	 * @param string $name The hook name
	 * @param array $parameters An array of parameters this hook implements
	 * @return array The results of the functions
	 */
	function call_integration_hook(string $name, array $parameters = []): array
	{
		return SMF\IntegrationHook::call($name, $parameters);
	}

	/**
	 * Add a function for integration hook.
	 * Does nothing if the function is already added.
	 * Cleans up enabled/disabled variants before taking requested action.
	 *
	 * @param string $name The complete hook name.
	 * @param string $function The function name. Can be a call to a method via Class::method.
	 * @param bool $permanent If true, updates the value in settings table.
	 * @param string $file The file. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
	 * @param bool $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
	 */
	function add_integration_function(
		string $name,
		string $function,
		bool $permanent = true,
		string $file = '',
		bool $object = false,
	): void {
		SMF\IntegrationHook::add(
			$name,
			$function,
			$permanent,
			$file,
			$object,
		);
	}

	/**
	 * Remove an integration hook function.
	 * Removes the given function from the given hook.
	 * Does nothing if the function is not available.
	 * Cleans up enabled/disabled variants before taking requested action.
	 *
	 * @param string $name The complete hook name.
	 * @param string $function The function name. Can be a call to a method via Class::method.
	 * @param bool $permanent Irrelevant for the function itself but need to declare it to match
	 * @param string $file The filename. Must include one of the following wildcards: $boarddir, $sourcedir, $themedir, example: $sourcedir/Test.php
	 * @param bool $object Indicates if your class will be instantiated when its respective hook is called. If true, your function must be a method.
	 * @see add_integration_function
	 */
	function remove_integration_function(
		string $name,
		string $function,
		bool $permanent = true,
		string $file = '',
		bool $object = false,
	): void {
		SMF\IntegrationHook::remove(
			$name,
			$function,
			$permanent,
			$file,
			$object,
		);
	}

	/**************
	 * Begin SMF\IP
	 **************/

	/**
	 * Convert a single IP to a ranged IP.
	 * internal function used to convert a user-readable format to a format suitable for the database.
	 *
	 * @param string $fullip The full IP
	 * @return array An array of IP parts
	 */
	function ip2range(string $addr): array
	{
		return SMF\IP::ip2range($addr);
	}

	/**
	 * Convert a range of given IP number into a single string.
	 * It's practically the reverse function of ip2range().
	 *
	 * @example
	 * range2ip(array(10, 10, 10, 0), array(10, 10, 20, 255)) returns '10.10.10-20.*
	 *
	 * @param array $low The low end of the range in IPv4 format
	 * @param array $high The high end of the range in IPv4 format
	 * @return string A string indicating the range
	 */
	function range2ip(string $low, string $high): string
	{
		return SMF\IP::range2ip($low, $high);
	}

	/**
	 * Check the given String if he is a valid IPv4 or IPv6
	 * return true or false
	 *
	 * @param string $IPString
	 *
	 * @return bool
	 */
	function isValidIP(string $ip): bool
	{
		return (new SMF\IP($ip))->isValid();
	}

	/**
	 * Validates a IPv6 address. returns true if it is ipv6.
	 *
	 * @param string $ip The ip address to be validated
	 * @return bool Whether the specified IP is a valid IPv6 address
	 */
	function isValidIPv6(string $ip): bool
	{
		return (new SMF\IP($ip))->isValid(FILTER_FLAG_IPV6);
	}

	/**
	 * Lookup an IP; try shell_exec first because we can do a timeout on it.
	 *
	 * @param string $ip The IP to get the hostname from
	 * @return string The hostname
	 */
	function host_from_ip(string $ip): string
	{
		return (new SMF\IP($ip))->getHost(0);
	}

	/**
	 * Converts an IP address into binary
	 *
	 * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
	 * @return string|false The IP address in binary or false
	 */
	function inet_ptod(string $ip): string|bool
	{
		return (new SMF\IP($ip))->toBinary();
	}

	/**
	 * Converts a binary version of an IP address into a readable format
	 *
	 * @param string $bin An IP address in IPv4, IPv6 (Either string (postgresql) or binary (other databases))
	 * @return string|false The IP address in presentation format or false on error
	 */
	function inet_dtop(string $ip): string
	{
		return (string) (new SMF\IP($ip));
	}

	/**
	 * Expands a IPv6 address to its full form.
	 *
	 * @param string $addr The IPv6 address
	 * @param bool $strict_check Whether to check the length of the expanded address for compliance
	 * @return string|bool The expanded IPv6 address or false if $strict_check is true and the result isn't valid
	 */
	function expandIPv6(string $ip, bool $return_bool_if_invalid = true): string|bool
	{
		$ip = new SMF\IP($ip);

		if ($return_bool_if_invalid && !$ip->isValid(FILTER_FLAG_IPV6)) {
			return false;
		}

		return $ip->expand();
	}

	/**
	 * Detect if a IP is in a CIDR address
	 * - returns true or false
	 *
	 * @param string $ip_address IP address to check
	 * @param string $cidr_address CIDR address to verify
	 * @return bool Whether the IP matches the CIDR
	 */
	function matchIPtoCIDR(string $ip_address, string $cidr_address): bool
	{
		return (new SMF\IP($ip_address))->matchtoCIDR($cidr_address);
	}

	/********************
	 * Begin SMF\ItemList
	 ********************/

	/**
	 * Create a new list
	 *
	 * @param array $listOptions An array of options for the list - 'id', 'columns', 'items_per_page', 'get_count', etc.
	 */
	function createList(array $options): SMF\ItemList
	{
		return SMF\ItemList::load($options);
	}

	/****************
	 * Begin SMF\Lang
	 ****************/

	/**
	 * Load a language file.  Tries the current and default themes as well as the user and global languages.
	 *
	 * @param string $template_name The name of a template file
	 * @param string $lang A specific language to load this file from
	 * @param bool $fatal Whether to die with an error if it can't be loaded
	 * @param bool $force_reload Whether to load the file again if it's already loaded
	 * @return string The language actually loaded.
	 */
	function loadLanguage(
		string $template_name,
		string $lang = '',
		bool $fatal = true,
		bool $force_reload = false,
	): string {
		return SMF\Lang::load($template_name, $lang, $fatal, $force_reload);
	}

	/**
	 * Attempt to reload our known languages.
	 * It will try to choose only utf8 or non-utf8 languages.
	 *
	 * @param bool $use_cache Whether or not to use the cache
	 * @return array An array of information about available languages
	 */
	function getLanguages(bool $use_cache = true): array
	{
		return SMF\Lang::get($use_cache);
	}

	/**
	 * Replace all vulgar words with respective proper words. (substring or whole words..)
	 * What this function does:
	 *  - it censors the passed string.
	 *  - if the theme setting allow_no_censored is on, and the theme option
	 *	show_no_censored is enabled, does not censor, unless force is also set.
	 *  - it caches the list of censored words to reduce parsing.
	 *
	 * @param string &$text The text to censor
	 * @param bool $force Whether to censor the text regardless of settings
	 * @return string The censored text
	 */
	function censorText(&$text, bool $force = false): string
	{
		return SMF\Lang::censorText($text, $force);
	}

	function tokenTxtReplace(string $string = ''): string
	{
		return SMF\Lang::tokenTxtReplace($string);
	}

	/**
	 * Concatenates an array of strings into a grammatically correct sentence list
	 *
	 * Uses formats defined in the language files to build the list appropropriately
	 * for the currently loaded language.
	 *
	 * @param array $list An array of strings to concatenate.
	 * @return string The localized sentence list.
	 */
	function sentence_list(array $list): string
	{
		return SMF\Lang::sentenceList($list);
	}

	/**
	 * - Formats a number.
	 * - uses the format of number_format to decide how to format the number.
	 *   for example, it might display "1 234,50".
	 * - caches the formatting data from the setting for optimization.
	 *
	 * @param float $number A number
	 * @param bool|int $override_decimal_count If set, will use the specified number of decimal places. Otherwise it's automatically determined
	 * @return string A formatted number
	 */
	function comma_format(int|float $number, ?int $decimals = null): string
	{
		return SMF\Lang::numberFormat($number, $decimals);
	}

	/*******************
	 * Begin SMF\Logging
	 *******************/

	/**
	 * Put this user in the online log.
	 *
	 * @param bool $force Whether to force logging the data
	 */
	function writeLog(bool $force = false): void
	{
		if (!isset(User::$me)) {
			return;
		}

		User::$me->logOnline($force);
	}

	/**
	 * This function logs an action to the database. It is a
	 * thin wrapper around {@link logActions()}.
	 *
	 * @example logAction('remove', array('starter' => $id_member_started));
	 *
	 * @param string $action A code for the report; a list of such strings
	 * can be found in Modlog.{language}.php (modlog_ac_ strings)
	 * @param array $extra An associated array of parameters for the
	 * item being logged. Typically this will include 'topic' for the topic's id.
	 * @param string $log_type A string reflecting the type of log.
	 *
	 * @return int The ID of the row containing the logged data
	 */
	function logAction($action, array $extra = [], $log_type = 'moderate'): int
	{
		return SMF\Logging::logAction($action, $extra, $log_type);
	}

	/**
	 * Log changes to the forum, such as moderation events or administrative
	 * changes. This behaves just like {@link logAction()} in SMF 2.0, except
	 * that it is designed to log multiple actions at once.
	 *
	 * SMF uses three log types:
	 *
	 * - `user` for actions executed that aren't related to
	 *    moderation (e.g. signature or other changes from the profile);
	 * - `moderate` for moderation actions (e.g. topic changes);
	 * - `admin` for administrative actions.
	 *
	 * @param array $logs An array of log data
	 *
	 * @return int The last logged ID
	 */
	function logActions(array $logs): int
	{
		return SMF\Logging::logActions($logs);
	}

	/**
	 * Update some basic statistics.
	 *
	 * 'member' statistic updates the latest member, the total member
	 *  count, and the number of unapproved members.
	 * 'member' also only counts approved members when approval is on, but
	 *  is much more efficient with it off.
	 *
	 * 'message' changes the total number of messages, and the
	 *  highest message id by id_msg - which can be parameters 1 and 2,
	 *  respectively.
	 *
	 * 'topic' updates the total number of topics, or if parameter1 is true
	 *  simply increments them.
	 *
	 * 'subject' updates the log_search_subjects in the event of a topic being
	 *  moved, removed or split.  parameter1 is the topicid, parameter2 is the new subject
	 *
	 * 'postgroups' case updates those members who match condition's
	 *  post-based membergroups in the database (restricted by parameter1).
	 *
	 * @param string $type Stat type - can be 'member', 'message', 'topic', 'subject' or 'postgroups'
	 * @param mixed $parameter1 A parameter for updating the stats
	 * @param mixed $parameter2 A 2nd parameter for updating the stats
	 */
	function updateStats(string $type, mixed $parameter1 = null, mixed $parameter2 = null): void
	{
		SMF\Logging::updateStats($type, $parameter1, $parameter2);
	}

	/**
	 * Track Statistics.
	 * Caches statistics changes, and flushes them if you pass nothing.
	 * If '+' is used as a value, it will be incremented.
	 * It does not actually commit the changes until the end of the page view.
	 * It depends on the trackStats setting.
	 *
	 * @param array $stats An array of data
	 * @return bool Whether or not the info was updated successfully
	 */
	function trackStats(array $stats = []): bool
	{
		return SMF\Logging::trackStats($stats);
	}

	/**
	 * Check if the number of users online is a record and store it.
	 *
	 * @param int $total_users_online The total number of members online
	 */
	function trackStatsUsersOnline(int $total_users_online): void
	{
		SMF\Logging::trackStatsUsersOnline($total_users_online);
	}

	/**
	 * Retrieve a list and several other statistics of the users currently online.
	 * Used by the board index and SSI.
	 * Also returns the membergroups of the users that are currently online.
	 * (optionally) hides members that chose to hide their online presence.
	 *
	 * @param array $membersOnlineOptions An array of options for the list
	 * @return array An array of information about the online users
	 */
	function getMembersOnlineStats(array $membersOnlineOptions): array
	{
		return SMF\Logging::getMembersOnlineStats($membersOnlineOptions);
	}

	/**
	 * This function shows the debug information tracked when $db_show_debug = true
	 * in Settings.php
	 */
	function displayDebug(): void
	{
		SMF\Logging::displayDebug();
	}

	/****************
	 * Begin SMF\Mail
	 ****************/

	/**
	 * This function sends an email to the specified recipient(s).
	 * It uses the mail_type settings and webmaster_email variable.
	 *
	 * @param array $to The email(s) to send to
	 * @param string $subject Email subject, expected to have entities, and slashes, but not be parsed
	 * @param string $message Email body, expected to have slashes, no htmlentities
	 * @param string $from The address to use for replies
	 * @param string $message_id If specified, it will be used as local part of the Message-ID header.
	 * @param bool $send_html Whether or not the message is HTML vs. plain text
	 * @param int $priority The priority of the message
	 * @param bool $hotmail_fix Whether to apply the "hotmail fix"
	 * @param bool $is_private Whether this is private
	 * @return bool Whether ot not the email was sent properly.
	 */
	function sendMail(
		array $to,
		string $subject,
		string $message,
		?string $from = null,
		?string $message_id = null,
		bool $send_html = false,
		int $priority = 3,
		?bool $hotmail_fix = null,
		bool $is_private = false,
	): bool {
		return SMF\Mail::send(
			$to,
			$subject,
			$message,
			$from,
			$message_id,
			$send_html,
			$priority,
			$hotmail_fix,
			$is_private,
		);
	}

	/**
	 * Add an email to the mail queue.
	 *
	 * @param bool $flush Whether to flush the queue
	 * @param array $to_array An array of recipients
	 * @param string $subject The subject of the message
	 * @param string $message The message
	 * @param string $headers The headers
	 * @param bool $send_html Whether to send in HTML format
	 * @param int $priority The priority
	 * @param bool $is_private Whether this is private
	 * @return bool Whether the message was added
	 */
	function AddMailQueue(
		bool $flush = false,
		array $to_array = [],
		string $subject = '',
		string $message = '',
		string $headers = '',
		bool $send_html = false,
		int $priority = 3,
		bool $is_private = false,
	): bool {
		return SMF\Mail::addToQueue(
			$flush,
			$to_array,
			$subject,
			$message,
			$headers,
			$send_html,
			$priority,
			$is_private,
		);
	}

	function reduceQueue(bool|int $number = false, bool $override_limit = false, bool $force_send = false): bool
	{
		return SMF\Mail::reduceQueue($number, $override_limit, $force_send);
	}

	/**
	 * Prepare text strings for sending as email body or header.
	 * In case there are higher ASCII characters in the given string, this
	 * function will attempt the transport method 'quoted-printable'.
	 * Otherwise the transport method '7bit' is used.
	 *
	 * @param string $string The string
	 * @param bool $with_charset Whether we're specifying a charset ($custom_charset must be set here)
	 * @param bool $hotmail_fix Whether to apply the hotmail fix  (all higher ASCII characters are converted to HTML entities to assure proper display of the mail)
	 * @param string $line_break The linebreak
	 * @param string $custom_charset If set, it uses this character set
	 * @return array An array containing the character set, the converted string and the transport method.
	 */
	function mimespecialchars(
		string $string,
		bool $with_charset = true,
		bool $hotmail_fix = false,
		string $line_break = "\r\n",
		?string $custom_charset = null,
	): array {
		return SMF\Mail::mimespecialchars(
			$string,
			$with_charset,
			$hotmail_fix,
			$line_break,
			$custom_charset,
		);
	}

	/**
	 * Sends mail, like mail() but over SMTP.
	 * It expects no slashes or entities.
	 *
	 * @internal
	 *
	 * @param array $mail_to_array Array of strings (email addresses)
	 * @param string $subject Email subject
	 * @param string $message Email message
	 * @param string $headers Email headers
	 * @return bool Whether it sent or not.
	 */
	function smtp_mail(array $mail_to_array, string $subject, string $message, string $headers): bool
	{
		return SMF\Mail::sendSmtp($mail_to_array, $subject, $message, $headers);
	}

	function serverParse(string $message, $socket, string $code, ?string &$response = null): bool
	{
		return SMF\Mail::serverParse($message, $socket, $code, $response);
	}

	/**
	 * Sends a notification to members who have elected to receive emails
	 * when things happen to a topic, such as replies are posted.
	 * The function automatically finds the subject and its board, and
	 * checks permissions for each member who is "signed up" for notifications.
	 * It will not send 'reply' notifications more than once in a row.
	 * Uses Post language file
	 *
	 * @param array $topics Represents the topics the action is happening to.
	 * @param string $type Can be any of reply, sticky, lock, unlock, remove, move, merge, and split.  An appropriate message will be sent for each.
	 * @param array $exclude Members in the exclude array will not be processed for the topic with the same key.
	 * @param array $members_only Are the only ones that will be sent the notification if they have it on.
	 */
	function sendNotifications(array $topics, string $type, array $exclude = [], array $members_only = [])
	{
		return SMF\Mail::sendNotifications($topics, $type, $exclude, $members_only);
	}

	/**
	 * This simple function gets a list of all administrators and sends them an email
	 *  to let them know a new member has joined.
	 * Called by registerMember() function in Subs-Members.php.
	 * Email is sent to all groups that have the moderate_forum permission.
	 * The language set by each member is being used (if available).
	 * Uses the Login language file
	 *
	 * @param string $type The type. Types supported are 'approval', 'activation', and 'standard'.
	 * @param int $memberID The ID of the member
	 * @param string $member_name The name of the member (if null, it is pulled from the database)
	 */
	function adminNotify(string $type, int $memberID, ?string $member_name = null): void
	{
		SMF\Mail::adminNotify($type, $memberID, $member_name);
	}

	/**
	 * Load a template from EmailTemplates language file.
	 *
	 * @param string $template The name of the template to load
	 * @param array $replacements An array of replacements for the variables in the template
	 * @param string $lang The language to use, if different than the user's current language
	 * @param bool $loadLang Whether to load the language file first
	 * @return array An array containing the subject and body of the email template, with replacements made
	 */
	function loadEmailTemplate(
		string $template,
		array $replacements = [],
		string $lang = '',
		bool $loadLang = true,
	): array {
		return SMF\Mail::loadEmailTemplate($template, $replacements, $lang, $loadLang);
	}

	/****************
	 * Begin SMF\Menu
	 ****************/

	/**
	 * Create a menu.
	 *
	 * @param array $menuData An array of menu data
	 * @param array $menuOptions An array of menu options
	 * @return bool|array False if nothing to show or an array of info about the selected menu item
	 */
	function createMenu(array $data, array $options = []): array|false
	{
		return SMF\Menu::create($data, $options);
	}

	/**
	 * Delete a menu.
	 *
	 * @param string $menu_id The ID of the menu to destroy or 'last' for the most recent one
	 * @return bool|void False if the menu doesn't exist, nothing otherwise
	 */
	function destroyMenu(int|string $id = 'last'): void
	{
		SMF\Menu::destroy($id);
	}

	/***************
	 * Begin SMF\Msg
	 ***************/

	/**
	 * Takes a message and parses it, returning nothing.
	 * Cleans up links (javascript, etc.) and code/quote sections.
	 * Won't convert \n's and a few other things if previewing is true.
	 *
	 * @param string $message The mesasge
	 * @param bool $previewing Whether we're previewing
	 */
	function preparsecode(string &$message, bool $previewing = false): void
	{
		SMF\Msg::preparsecode($message, $previewing);
	}

	/**
	 * This is very simple, and just removes things done by preparsecode.
	 *
	 * @param string $message The message
	 */
	function un_preparsecode(string $message): string
	{
		return SMF\Msg::un_preparsecode($message);
	}

	/**
	 * Fix any URLs posted - ie. remove 'javascript:'.
	 * Used by preparsecode, fixes links in message and returns nothing.
	 *
	 * @param string $message The message
	 */
	function fixTags(string &$message): void
	{
		SMF\Msg::fixTags($message);
	}

	/**
	 * Fix a specific class of tag - ie. url with =.
	 * Used by fixTags, fixes a specific tag's links.
	 *
	 * @param string $message The message
	 * @param string $myTag The tag
	 * @param string $protocols The protocols
	 * @param bool $embeddedUrl Whether it *can* be set to something
	 * @param bool $hasEqualSign Whether it *is* set to something
	 * @param bool $hasExtra Whether it can have extra cruft after the begin tag.
	 */
	function fixTag(
		string &$message,
		string $myTag,
		array $protocols,
		bool $embeddedUrl = false,
		bool $hasEqualSign = false,
		bool $hasExtra = false,
	): void {
		SMF\Msg::fixTag(
			$message,
			$myTag,
			$protocols,
			$embeddedUrl,
			$hasEqualSign,
			$hasExtra,
		);
	}

	/**
	 * Create a post, either as new topic (id_topic = 0) or in an existing one.
	 * The input parameters of this function assume:
	 * - Strings have been escaped.
	 * - Integers have been cast to integer.
	 * - Mandatory parameters are set.
	 *
	 * @param array $msgOptions An array of information/options for the post
	 * @param array $topicOptions An array of information/options for the topic
	 * @param array $posterOptions An array of information/options for the poster
	 * @return bool Whether the operation was a success
	 */
	function createPost(array &$msgOptions, array &$topicOptions, array &$posterOptions): bool
	{
		return SMF\Msg::create($msgOptions, $topicOptions, $posterOptions);
	}

	/**
	 * Modifying a post...
	 *
	 * @param array &$msgOptions An array of information/options for the post
	 * @param array &$topicOptions An array of information/options for the topic
	 * @param array &$posterOptions An array of information/options for the poster
	 * @return bool Whether the post was modified successfully
	 */
	function modifyPost(array &$msgOptions, array &$topicOptions, array &$posterOptions): bool
	{
		return SMF\Msg::modify($msgOptions, $topicOptions, $posterOptions);
	}

	/**
	 * Approve (or not) some posts... without permission checks...
	 *
	 * @param array $msgs Array of message ids
	 * @param bool $approve Whether to approve the posts (if false, posts are unapproved)
	 * @param bool $notify Whether to notify users
	 * @return bool Whether the operation was successful
	 */
	function approvePosts(array $msgs, bool $approve = true, bool $notify = true): bool
	{
		return SMF\Msg::approve($msgs, $approve, $notify);
	}

	/**
	 * Upon approval, clear unread alerts.
	 *
	 * @param int[] $content_ids either id_msgs or id_topics
	 * @param string $content_action will be either 'unapproved_post' or 'unapproved_topic'
	 */
	function clearApprovalAlerts(array $content_ids, string $content_action): void
	{
		SMF\Msg::clearApprovalAlerts($content_ids, $content_action);
	}

	/**
	 * Takes an array of board IDs and updates their last messages.
	 * If the board has a parent, that parent board is also automatically
	 * updated.
	 * The columns updated are id_last_msg and last_updated.
	 * Note that id_last_msg should always be updated using this function,
	 * and is not automatically updated upon other changes.
	 *
	 * @param array $setboards An array of board IDs
	 * @param int $id_msg The ID of the message
	 * @return ?bool Returns false if $setboards is empty for some reason
	 */
	function updateLastMessages(array $setboards, int $id_msg = 0): ?bool
	{
		return SMF\Msg::updateLastMessages($setboards, $id_msg);
	}

	/**
	 * Remove a specific message (including permission checks).
	 *
	 * @param int $message The message id
	 * @param bool $decreasePostCount Whether to decrease users' post counts
	 * @return bool Whether the operation succeeded
	 */
	function removeMessage(int $message, bool $decreasePostCount = true): bool
	{
		return SMF\Msg::remove($message, $decreasePostCount);
	}

	/*********************
	 * Begin SMF\PageIndex
	 *********************/

	/**
	 * Constructs a page list.
	 *
	 * - builds the page list, e.g. 1 ... 6 7 [8] 9 10 ... 15.
	 * - flexible_start causes it to use "url.page" instead of "url;start=page".
	 * - very importantly, cleans up the start value passed, and forces it to
	 *   be a multiple of num_per_page.
	 * - checks that start is not more than max_value.
	 * - base_url should be the URL without any start parameter on it.
	 * - uses the compactTopicPagesEnable and compactTopicPagesContiguous
	 *   settings to decide how to display the menu.
	 *
	 * an example is available near the function definition.
	 * $pageindex = constructPageIndex($scripturl . '?board=' . $board, $_REQUEST['start'], $num_messages, $maxindex, true);
	 *
	 * @param string $base_url The basic URL to be used for each link.
	 * @param int &$start The start position, by reference. If this is not a multiple of the number of items per page, it is sanitized to be so and the value will persist upon the function's return.
	 * @param int $max_value The total number of items you are paginating for.
	 * @param int $num_per_page The number of items to be displayed on a given page. $start will be forced to be a multiple of this value.
	 * @param bool $short_format Whether a ;start=x component should be introduced into the URL automatically (see above)
	 * @param bool $show_prevnext Whether the Previous and Next links should be shown (should be on only when navigating the list)
	 *
	 * @return string The complete HTML of the page index that was requested, formatted by the template.
	 */
	function constructPageIndex(
		string $base_url,
		int &$start,
		int $max_value,
		int $num_per_page,
		bool $short_format = false,
		bool $show_prevnext = true,
	): string {
		return (string) SMF\PageIndex::load(
			$base_url,
			$start,
			$max_value,
			$num_per_page,
			$short_format,
			$show_prevnext,
		);
	}

	/****************
	 * Begin SMF\Poll
	 ****************/

	/**
	 * Allow the user to vote.
	 * It is called to register a vote in a poll.
	 * Must be called with a topic and option specified.
	 * Requires the poll_vote permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=vote.
	 *
	 * Uses Post language file.
	 */
	function Vote(): void
	{
		SMF\Actions\PollVote::call();
	}

	/**
	 * Lock the voting for a poll.
	 * Must be called with a topic specified in the URL.
	 * An admin always has over riding permission to lock a poll.
	 * If not an admin must have poll_lock_any permission, otherwise must
	 * be poll starter with poll_lock_own permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=lockvoting.
	 */
	function LockVoting(): void
	{
		SMF\Actions\PollLock::call();
	}

	/**
	 * Display screen for editing or adding a poll.
	 * Must be called with a topic specified in the URL.
	 * If the user is adding a poll to a topic, must contain the variable
	 * 'add' in the url.
	 * User must have poll_edit_any/poll_add_any permission for the
	 * relevant action, otherwise must be poll starter with poll_edit_own
	 * permission for editing, or be topic starter with poll_add_any permission for adding.
	 * Accessed via ?action=editpoll.
	 *
	 * Uses Post language file.
	 * Uses Poll template, main sub-template.
	 */
	function EditPoll(): void
	{
		SMF\Actions\PollEdit::call();
	}

	/**
	 * Update the settings for a poll, or add a new one.
	 * Must be called with a topic specified in the URL.
	 * The user must have poll_edit_any/poll_add_any permission
	 * for the relevant action. Otherwise they must be poll starter
	 * with poll_edit_own permission for editing, or be topic starter
	 * with poll_add_any permission for adding.
	 * In the case of an error, this function will redirect back to
	 * EditPoll and display the relevant error message.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=editpoll2.
	 */
	function EditPoll2(): void
	{
		SMF\Actions\PollEdit2::call();
	}

	/**
	 * Remove a poll from a topic without removing the topic.
	 * Must be called with a topic specified in the URL.
	 * Requires poll_remove_any permission, unless it's the poll starter
	 * with poll_remove_own permission.
	 * Upon successful completion of action will direct user back to topic.
	 * Accessed via ?action=removepoll.
	 */
	function RemovePoll(): void
	{
		SMF\Actions\PollRemove::call();
	}

	/*******************
	 * Begin SMF\Profile
	 *******************/

	/**
	 * Validate the signature
	 *
	 * @param string &$value The new signature
	 * @return bool|string True if the signature passes the checks, otherwise a string indicating what the problem is
	 */
	function profileValidateSignature(string &$value): bool|string
	{
		return SMF\Profile::validateSignature($value);
	}

	/**
	 * Handles the "manage groups" section of the profile
	 *
	 * @return true Always returns true
	 */
	function profileLoadGroups(?int $id = null): bool
	{
		if (!isset(SMF\Profile::$loaded[$id])) {
			SMF\Profile::load($id);
		}

		SMF\Profile::$loaded[$id]->loadAssignableGroups();

		return true;
	}

	/**
	 * This defines every profile field known to man.
	 *
	 * @param bool $force_reload Whether to reload the data
	 */
	function loadProfileFields(bool $force_reload = false, ?int $id = null): void
	{
		if (!isset(SMF\Profile::$loaded[$id])) {
			SMF\Profile::load($id);
		}

		SMF\Profile::$loaded[$id]->loadStandardFields($force_reload);
	}

	/**
	 * Load any custom fields for this area... no area means load all, 'summary' loads all public ones.
	 *
	 * @param int $id The ID of the member
	 * @param string $area Which area to load fields for
	 */
	function loadCustomFields(int $id, string $area = 'summary'): void
	{
		if (!isset(SMF\Profile::$loaded[$id])) {
			SMF\Profile::load($id);
		}

		SMF\Profile::$loaded[$id]->loadCustomFields($area);
	}

	/**
	 * Loads the theme options for a user
	 *
	 * @param int $id The ID of the member
	 * @param bool $defaultSettings If true, we are loading default options.
	 */
	function loadThemeOptions(int $id, bool $defaultSettings = false): void
	{
		if (!isset(SMF\Profile::$loaded[$id])) {
			SMF\Profile::load($id);
		}

		SMF\Profile::$loaded[$id]->loadThemeOptions($defaultSettings);
	}

	/**
	 * Setup the context for a page load!
	 *
	 * @param array $fields The profile fields to display. Each item should correspond to an item in the $profile_fields array generated by loadProfileFields
	 */
	function setupProfileContext(array $fields, int $id): void
	{
		if (!isset(SMF\Profile::$loaded[$id])) {
			SMF\Profile::load($id);
		}

		SMF\Profile::$member->setupContext($fields);
	}

	/**
	 * Save any changes to the custom profile fields
	 *
	 * @param int $id The ID of the member
	 * @param string $area The area of the profile these fields are in
	 * @param bool $sanitize = true Whether or not to sanitize the data
	 * @param bool $returnErrors Whether or not to return any error information
	 * @return ?array Returns nothing or returns an array of error info if $returnErrors is true
	 */
	function makeCustomFieldChanges(int $id, string $area, bool $sanitize = true, bool $return_errors = false): ?array
	{
		if (!isset(SMF\Profile::$loaded[$id])) {
			SMF\Profile::load($id);
		}

		$_REQUEST['sa'] = $area;
		SMF\Profile::$member->post_sanitized = !$sanitize;
		SMF\Profile::$member->save();

		if ($return_errors) {
			return SMF\Profile::$member->cf_save_errors;
		}

		return null;
	}

	/**
	 * Make any theme changes that are sent with the profile.
	 *
	 * @param int $id The ID of the user
	 * @param int $id_theme The ID of the theme
	 */
	function makeThemeChanges(int $id, int $id_theme): void
	{
		if (!isset(SMF\Profile::$loaded[$id])) {
			SMF\Profile::load($id);
		}

		SMF\Profile::$member->new_data['id_theme'] = $id_theme;
		SMF\Profile::$member->save();
	}

	/***********************
	 * Begin SMF\QueryString
	 ***********************/

	function cleanRequest(): void
	{
		SMF\QueryString::cleanRequest();
	}

	/**
	 * Compares existance request variables against an array.
	 *
	 * The input array is associative, where keys denote accepted values
	 * in a request variable denoted by `$req_val`. Values can be:
	 *
	 * - another associative array where at least one key must be found
	 *   in the request and their values are accepted request values.
	 * - A scalar value, in which case no furthur checks are done.
	 *
	 * @param array $array
	 * @param string $req_var request variable
	 *
	 * @return bool whether any of the criteria was satisfied
	 */
	function is_filtered_request(array $value_list, string $var): bool
	{
		return SMF\QueryString::isFilteredRequest($value_list, $var);
	}

	/**
	 * Rewrite URLs to include the session ID.
	 * What it does:
	 * - rewrites the URLs outputted to have the session ID, if the user
	 *   is not accepting cookies and is using a standard web browser.
	 * - handles rewriting URLs for the queryless URLs option.
	 * - can be turned off entirely by setting $scripturl to an empty
	 *   string, ''. (it wouldn't work well like that anyway.)
	 * - because of bugs in certain builds of PHP, does not function in
	 *   versions lower than 4.3.0 - please upgrade if this hurts you.
	 *
	 * @param string $buffer The unmodified output buffer
	 * @return string The modified buffer
	 */
	function ob_sessrewrite(string $buffer): string
	{
		return SMF\QueryString::ob_sessrewrite($buffer);
	}

	/****************
	 * Begin SMF\Sapi
	 ****************/

	/**
	 * Helper function to set the system memory to a needed value
	 * - If the needed memory is greater than current, will attempt to get more
	 * - if in_use is set to true, will also try to take the current memory usage in to account
	 *
	 * @param string $needed The amount of memory to request, if needed, like 256M
	 * @param bool $in_use Set to true to account for current memory usage of the script
	 * @return bool True if we have at least the needed memory
	 */
	function setMemoryLimit(string $needed, bool $in_use = false): bool
	{
		return SMF\Sapi::setMemoryLimit($needed, $in_use);
	}
	/**
	 * Helper function to convert memory string settings to bytes
	 *
	 * @param string $val The byte string, like 256M or 1G
	 * @return int The string converted to a proper integer in bytes
	 */
	function memoryReturnBytes(string $val): int
	{
		return SMF\Sapi::memoryReturnBytes($val);
	}

	/********************
	 * Begin SMF\Security
	 ********************/

	/**
	 * Hashes username with password
	 *
	 * @param string $username The username
	 * @param string $password The unhashed password
	 * @param int $cost The cost
	 * @return string The hashed password
	 */
	function hash_password(string $username, string $password, ?int $cost = null): string
	{
		return SMF\Security::hashPassword($username, $password, $cost);
	}

	/**
	 * Verifies a raw SMF password against the bcrypt'd string
	 *
	 * @param string $username The username
	 * @param string $password The password
	 * @param string $hash The hashed string
	 * @return bool Whether the hashed password matches the string
	 */
	function hash_verify_password(string $username, string $password, string $hash): bool
	{
		return SMF\Security::hashVerifyPassword($username, $password, $hash);
	}

	/**
	 * Benchmarks the server to figure out an appropriate cost factor (minimum 9)
	 *
	 * @param float $hashTime Time to target, in seconds
	 * @return int The cost
	 */
	function hash_benchmark(float $hashTime = 0.2): int
	{
		return SMF\Security::hashBenchmark($hashTime);
	}

	/**
	 * Check if a specific confirm parameter was given.
	 *
	 * @param string $action The action we want to check against
	 * @return bool|string True if the check passed or a token
	 */
	function checkConfirm(string $action): bool|string
	{
		return SMF\Security::checkConfirm($action);
	}

	/**
	 * Check whether a form has been submitted twice.
	 * Registers a sequence number for a form.
	 * Checks whether a submitted sequence number is registered in the current session.
	 * Depending on the value of is_fatal shows an error or returns true or false.
	 * Frees a sequence number from the stack after it's been checked.
	 * Frees a sequence number without checking if action == 'free'.
	 *
	 * @param string $action The action - can be 'register', 'check' or 'free'
	 * @param bool $is_fatal Whether to die with a fatal error
	 * @return ?bool If the action isn't check, returns nothing, otherwise returns whether the check was successful
	 */
	function checkSubmitOnce(string $action, bool $is_fatal = true): ?bool
	{
		return SMF\Security::checkSubmitOnce($action, $is_fatal);
	}

	/**
	 * This function attempts to protect from spammed messages and the like.
	 * The time taken depends on error_type - generally uses the modSetting.
	 *
	 * @param string $error_type The error type. Also used as a $txt index (not an actual string).
	 * @param bool $only_return_result Whether you want the function to die with a fatal_lang_error.
	 * @return bool Whether they've posted within the limit
	 */
	function spamProtection(string $error_type, bool $only_return_result = false): bool
	{
		return SMF\Security::spamProtection($error_type, $only_return_result);
	}

	/**
	 * A generic function to create a pair of index.php and .htaccess files in a directory
	 *
	 * @param string|array $paths The (absolute) directory path
	 * @param bool $attachments Whether this is an attachment directory
	 * @return bool|array True on success an array of errors if anything fails
	 */
	function secureDirectory(string|array $paths, bool $attachments = false): bool|array
	{
		return SMF\Security::secureDirectory($paths, $attachments);
	}

	/**
	 * This sets the X-Frame-Options header.
	 *
	 * @param string $override An option to override (either 'SAMEORIGIN' or 'DENY')
	 * @since 2.1
	 */
	function frameOptionsHeader(?string $override = null)
	{
		return SMF\Security::frameOptionsHeader($override);
	}

	/**
	 * This sets the Access-Control-Allow-Origin header.
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
	 *
	 * @param bool $set_header (Default: true): When false, we will do the logic, but not send the headers.  The relevant logic is still saved in the $context and can be sent manually.
	 *
	 * @since 2.1
	 */
	function corsPolicyHeader(bool $set_header = true): void
	{
		SMF\Security::corsPolicyHeader($set_header);
	}

	/**
	 * Throws guests out to the login screen when guest access is off.
	 * - sets $_SESSION['login_url'] to $_SERVER['REQUEST_URL'].
	 * - uses the 'kick_guest' sub template found in Login.template.php.
	 */
	function KickGuest(): void
	{
		SMF\User::$me->kickIfGuest(null, false);
	}

	/*************************
	 * Begin SMF\SecurityToken
	 *************************/

	/**
	 * Lets give you a token of our appreciation.
	 *
	 * @param string $action The action to create the token for
	 * @param string $type The type of token ('post', 'get' or 'request')
	 * @return array An array containing the name of the token var and the actual token
	 */
	function createToken(string $action, string $type = 'post'): array
	{
		return SMF\SecurityToken::create($action, $type);
	}

	/**
	 * Only patrons with valid tokens can ride this ride.
	 *
	 * @param string $action The action to validate the token for
	 * @param string $type The type of request (get, request, or post)
	 * @param bool $reset Whether to reset the token and display an error if validation fails
	 * @return bool returns whether the validation was successful
	 */
	function validateToken(string $action, string $type = 'post', bool $reset = true): bool
	{
		return SMF\SecurityToken::validate($action, $type, $reset);
	}

	/**
	 * Removes old unused tokens from session
	 * defaults to 3 hours before a token is considered expired
	 * if $complete = true will remove all tokens
	 *
	 * @param bool $complete Whether to remove all tokens or only expired ones
	 */
	function cleanTokens(bool $complete = false): void
	{
		SMF\SecurityToken::clean($complete);
	}

	/******************************
	 * Begin SMF\ServerSideIncludes
	 ******************************/

	/**
	 * This shuts down the SSI and shows the footer.
	 */
	function ssi_shutdown(): void
	{
		SMF\ServerSideIncludes::shutdown();
	}

	/**
	 * Show the SMF version.
	 *
	 * @param string $output_method If 'echo', displays the version, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the version
	 */
	function ssi_version($output_method = 'echo')
	{
		return SMF\ServerSideIncludes::version($output_method);
	}

	/**
	 * Show the full SMF version string.
	 *
	 * @param string $output_method If 'echo', displays the full version string, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the version string
	 */
	function ssi_full_version($output_method = 'echo')
	{
		return SMF\ServerSideIncludes::fullVersion($output_method);
	}

	/**
	 * Show the SMF software year.
	 *
	 * @param string $output_method If 'echo', displays the software year, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the software year
	 */
	function ssi_software_year($output_method = 'echo')
	{
		return SMF\ServerSideIncludes::softwareYear($output_method = 'echo');
	}

	/**
	 * Show the forum copyright. Only used in our ssi_examples files.
	 *
	 * @param string $output_method If 'echo', displays the forum copyright, otherwise returns it
	 * @return void|string Returns nothing if output_method is 'echo', otherwise returns the copyright string
	 */
	function ssi_copyright($output_method = 'echo')
	{
		return SMF\ServerSideIncludes::copyright($output_method);
	}

	/**
	 * Display a welcome message, like: Hey, User, you have 0 messages, 0 are new.
	 *
	 * @param string $output_method The output method. If 'echo', will display everything. Otherwise returns an array of user info.
	 * @return void|array Displays a welcome message or returns an array of user data depending on output_method.
	 */
	function ssi_welcome($output_method = 'echo')
	{
		return SMF\ServerSideIncludes::welcome($output_method);
	}

	/**
	 * Display a menu bar, like is displayed at the top of the forum.
	 *
	 * @param string $output_method The output method. If 'echo', will display the menu, otherwise returns an array of menu data.
	 * @return ?array Displays the menu or returns an array of menu data depending on output_method.
	 */
	function ssi_menubar($output_method = 'echo')
	{
		return SMF\ServerSideIncludes::menubar($output_method);
	}

	/**
	 * Show a logout link.
	 *
	 * @param string $redirect_to A URL to redirect the user to after they log out.
	 * @param string $output_method The output method. If 'echo', shows a logout link, otherwise returns the HTML for it.
	 * @return string|bool|null Displays a logout link or returns its HTML depending on output_method.
	 */
	function ssi_logout($redirect_to = '', $output_method = 'echo')
	{
		return SMF\ServerSideIncludes::logout($redirect_to, $output_method);
	}

	/**
	 * Recent post list:   [board] Subject by Poster    Date
	 *
	 * @param int $num_recent How many recent posts to display
	 * @param null|array $exclude_boards If set, doesn't show posts from the specified boards
	 * @param null|array $include_boards If set, only includes posts from the specified boards
	 * @param string $output_method The output method. If 'echo', displays the posts, otherwise returns an array of information about them.
	 * @param bool $limit_body Whether or not to only show the first 384 characters of each post
	 * @return ?array Displays a list of recent posts or returns an array of information about them depending on output_method.
	 */
	function ssi_recentPosts(
		int $num_recent = 8,
		?array $exclude_boards = null,
		?array $include_boards = null,
		string $output_method = 'echo',
		bool $limit_body = true,
	): ?array {
		return SMF\ServerSideIncludes::recentPosts(
			$num_recent,
			$exclude_boards,
			$include_boards,
			$output_method,
			$limit_body,
		);
	}

	/**
	 * Fetches one or more posts by ID.
	 *
	 * @param int[] $post_ids An array containing the IDs of the posts to show
	 * @param bool $override_permissions Whether to ignore permissions. If true, will show posts even if the user doesn't have permission to see them.
	 * @param string $output_method The output method. If 'echo', displays the posts, otherwise returns an array of info about them
	 * @return ?array Displays the specified posts or returns an array of info about them, depending on output_method.
	 */
	function ssi_fetchPosts(
		array $post_ids = [],
		bool $override_permissions = false,
		string $output_method = 'echo',
	): ?array {
		return SMF\ServerSideIncludes::fetchPosts($post_ids, $override_permissions, $output_method);
	}

	/**
	 * This handles actually pulling post info. Called from other functions to eliminate duplication.
	 *
	 * @param string $query_where The WHERE clause for the query
	 * @param array $query_where_params An array of parameters for the WHERE clause
	 * @param int $query_limit The maximum number of rows to return
	 * @param string $query_order The ORDER BY clause for the query
	 * @param string $output_method The output method. If 'echo', displays the posts, otherwise returns an array of info about them.
	 * @param bool $limit_body If true, will only show the first 384 characters of the post rather than all of it
	 * @param bool $override_permissions Whether or not to ignore permissions. If true, will show all posts regardless of whether the user can actually see them
	 * @return ?array Displays the posts or returns an array of info about them, depending on output_method
	 */
	function ssi_queryPosts(
		string $query_where = '',
		array $query_where_params = [],
		int $query_limit = 10,
		string $query_order = 'm.id_msg DESC',
		string $output_method = 'echo',
		bool $limit_body = false,
		bool $override_permissions = false,
	): ?array {
		return SMF\ServerSideIncludes::queryPosts(
			$query_where,
			$query_where_params,
			$query_limit,
			$query_order,
			$output_method,
			$limit_body,
			$override_permissions,
		);
	}

	/**
	 * Recent topic list:   [board] Subject by Poster   Date
	 *
	 * @param int $num_recent How many recent topics to show
	 * @param null|array $exclude_boards If set, exclude topics from the specified board(s)
	 * @param null|array $include_boards If set, only include topics from the specified board(s)
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them
	 * @return void|array Either displays a list of topics or returns an array of info about them, depending on output_method.
	 */
	function ssi_recentTopics(
		int $num_recent = 8,
		?array $exclude_boards = null,
		?array $include_boards = null,
		string $output_method = 'echo',
	): ?array {
		return SMF\ServerSideIncludes::recentTopics($num_recent, $exclude_boards, $include_boards, $output_method);
	}

	/**
	 * Shows a list of top posters
	 *
	 * @param int $topNumber How many top posters to list
	 * @param string $output_method The output method. If 'echo', will display a list of users, otherwise returns an array of info about them.
	 * @return ?array Either displays a list of users or returns an array of info about them, depending on output_method.
	 */
	function ssi_topPoster(int $topNumber = 1, string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::topPoster($topNumber, $output_method);
	}

	/**
	 * Shows a list of top boards based on activity
	 *
	 * @param int $num_top How many boards to display
	 * @param string $output_method The output method. If 'echo', displays a list of boards, otherwise returns an array of info about them.
	 * @return ?array Displays a list of the top boards or returns an array of info about them, depending on output_method.
	 */
	function ssi_topBoards($num_top = 10, $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::topBoards($num_top, $output_method);
	}

	/**
	 * Shows a list of top topics based on views or replies
	 *
	 * @param string $type Can be either replies or views
	 * @param int $num_topics How many topics to display
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them.
	 * @return ?array Either displays a list of topics or returns an array of info about them, depending on output_method.
	 */
	function ssi_topTopics(string $type = 'replies', int $num_topics = 10, string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::topTopics($type, $num_topics, $output_method);
	}

	/**
	 * Top topics based on replies
	 *
	 * @param int $num_topics How many topics to show
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them
	 * @return ?array Either displays a list of top topics or returns an array of info about them, depending on output_method.
	 */
	function ssi_topTopicsReplies(int $num_topics = 10, string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::topTopicsReplies($num_topics, $output_method);
	}

	/**
	 * Top topics based on views
	 *
	 * @param int $num_topics How many topics to show
	 * @param string $output_method The output method. If 'echo', displays a list of topics, otherwise returns an array of info about them
	 * @return ?array Either displays a list of top topics or returns an array of info about them, depending on output_method.
	 */
	function ssi_topTopicsViews(int $num_topics = 10, string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::topTopicsViews($num_topics, $output_method);
	}

	/**
	 * Show a link to the latest member: Please welcome, Someone, our latest member.
	 *
	 * @param string $output_method The output method. If 'echo', returns a string with a link to the latest member's profile, otherwise returns an array of info about them.
	 * @return ?array Displays a "welcome" message for the latest member or returns an array of info about them, depending on output_method.
	 */
	function ssi_latestMember(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::latestMember($output_method);
	}

	/**
	 * Fetches a random member.
	 *
	 * @param string $random_type If 'day', only fetches a new random member once a day.
	 * @param string $output_method The output method. If 'echo', displays a link to the member's profile, otherwise returns an array of info about them.
	 * @return ?array Displays a link to a random member's profile or returns an array of info about them depending on output_method.
	 */
	function ssi_randomMember(string $random_type = '', string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::randomMember($random_type, $output_method);
	}

	/**
	 * Fetch specific members
	 *
	 * @param array $member_ids The IDs of the members to fetch
	 * @param string $output_method The output method. If 'echo', displays a list of links to the members' profiles, otherwise returns an array of info about them.
	 * @return ?array Displays links to the specified members' profiles or returns an array of info about them, depending on output_method.
	 */
	function ssi_fetchMember(array $member_ids = [], string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::fetchMember($member_ids, $output_method);
	}

	/**
	 * Get all members in the specified group
	 *
	 * @param int $group_id The ID of the group to get members from
	 * @param string $output_method The output method. If 'echo', returns a list of group members, otherwise returns an array of info about them.
	 * @return ?array Displays a list of group members or returns an array of info about them, depending on output_method.
	 */
	function ssi_fetchGroupMembers(?int $group_id = null, string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::fetchGroupMembers($group_id, $output_method);
	}

	/**
	 * Pulls info about members based on the specified parameters. Used by other
	 * functions to eliminate duplication.
	 *
	 * @param string $query_where The info for the WHERE clause of the query
	 * @param array $query_where_params The parameters for the WHERE clause
	 * @param string|int $query_limit The number of rows to return or an empty string to return all
	 * @param string $query_order The info for the ORDER BY clause of the query
	 * @param string $output_method The output method. If 'echo', displays a list of members, otherwise returns an array of info about them
	 * @return ?array Displays a list of members or returns an array of info about them, depending on output_method.
	 */
	function ssi_queryMembers(
		?string $query_where = null,
		array $query_where_params = [],
		string|int $query_limit = '',
		string $query_order = 'id_member DESC',
		string $output_method = 'echo',
	): ?array {
		return SMF\ServerSideIncludes::queryMembers(
			$query_where,
			$query_where_params,
			$query_limit,
			$query_order,
			$output_method,
		);
	}

	/**
	 * Show some basic stats:   Total This: XXXX, etc.
	 *
	 * @param string $output_method The output method. If 'echo', displays the stats, otherwise returns an array of info about them
	 * @return ?array Doesn't return anything if the user can't view stats. Otherwise either displays the stats or returns an array of info about them, depending on output_method.
	 */
	function ssi_boardStats(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::boardStats($output_method);
	}

	/**
	 * Shows a list of online users:  YY Guests, ZZ Users and then a list...
	 *
	 * @param string $output_method The output method. If 'echo', displays a list, otherwise returns an array of info about the online users.
	 * @return ?array Either displays a list of online users or returns an array of info about them, depending on output_method.
	 */
	function ssi_whosOnline(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::whosOnline($output_method);
	}

	/**
	 * Just like whosOnline except it also logs the online presence.
	 *
	 * @param string $output_method The output method. If 'echo', displays a list, otherwise returns an array of info about the online users.
	 * @return ?array Either displays a list of online users or returns an array of info about them, depending on output_method.
	 */
	function ssi_logOnline(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::logOnline($output_method);
	}

	/**
	 * Shows a login box
	 *
	 * @param string $redirect_to The URL to redirect the user to after they login
	 * @param string $output_method The output method. If 'echo' and the user is a guest, displays a login box, otherwise returns whether the user is a guest
	 * @return ?bool Either displays a login box or returns whether the user is a guest, depending on whether the user is logged in and output_method.
	 */
	function ssi_login($redirect_to = '', $output_method = 'echo'): ?bool
	{
		return SMF\ServerSideIncludes::login($redirect_to, $output_method);
	}

	/**
	 * Show the top poll based on votes
	 *
	 * @param string $output_method The output method. If 'echo', displays the poll, otherwise returns an array of info about it
	 * @return ?array Either shows the top poll or returns an array of info about it, depending on output_method.
	 */
	function ssi_topPoll(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::topPoll($output_method);
	}

	/**
	 * Shows the most recent poll
	 *
	 * @param bool $topPollInstead Whether to show the top poll (based on votes) instead of the most recent one
	 * @param string $output_method The output method. If 'echo', displays the poll, otherwise returns an array of info about it.
	 * @return ?array Either shows the poll or returns an array of info about it, depending on output_method.
	 */
	function ssi_recentPoll($topPollInstead = false, $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::recentPoll($topPollInstead, $output_method);
	}

	/**
	 * Shows the poll from the specified topic
	 *
	 * @param null|int $topic The topic to show the poll from. If null, $_REQUEST['ssi_topic'] will be used instead.
	 * @param string $output_method The output method. If 'echo', displays the poll, otherwise returns an array of info about it.
	 * @return ?array Either displays the poll or returns an array of info about it, depending on output_method.
	 */
	function ssi_showPoll(?int $topic = null, string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::showPoll($topic, $output_method);
	}

	/**
	 * Handles voting in a poll (done automatically)
	 */
	function ssi_pollVote()
	{
		return SMF\ServerSideIncludes::pollVote();
	}

	/**
	 * Shows a search box
	 *
	 * @param string $output_method The output method. If 'echo', displays a search box, otherwise returns the URL of the search page.
	 * @return ?string Displays a search box or returns the URL to the search page depending on output_method. If you don't have permission to search, the function won't return anything.
	 */
	function ssi_quickSearch(string $output_method = 'echo'): ?string
	{
		return SMF\ServerSideIncludes::quickSearch($output_method);
	}

	/**
	 * Show a random forum news item
	 *
	 * @param string $output_method The output method. If 'echo', shows the news item, otherwise returns it.
	 * @return ?string Shows or returns a random forum news item, depending on output_method.
	 */
	function ssi_news(string $output_method = 'echo'): ?string
	{
		return SMF\ServerSideIncludes::news($output_method);
	}

	/**
	 * Show today's birthdays.
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of users, otherwise returns an array of info about them.
	 * @return ?array Displays a list of users or returns an array of info about them depending on output_method.
	 */
	function ssi_todaysBirthdays(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::todaysBirthdays($output_method);
	}

	/**
	 * Shows today's holidays.
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of holidays, otherwise returns an array of info about them.
	 * @return ?array Displays a list of holidays or returns an array of info about them depending on output_method
	 */
	function ssi_todaysHolidays(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::todaysHolidays($output_method);
	}

	/**
	 * Shows today's events.
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of events, otherwise returns an array of info about them.
	 * @return ?array Displays a list of events or returns an array of info about them depending on output_method
	 */
	function ssi_todaysEvents(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::todaysEvents($output_method);
	}

	/**
	 * Shows today's calendar items (events, birthdays and holidays)
	 *
	 * @param string $output_method The output method. If 'echo', displays a list of calendar items, otherwise returns an array of info about them.
	 * @return array|string|null Displays a list of calendar items or returns an array of info about them depending on output_method
	 */
	function ssi_todaysCalendar(string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::todaysCalendar($output_method);
	}

	/**
	 * Show the latest news, with a template... by board.
	 *
	 * @param null|int $board The ID of the board to get the info from. Defaults to $board or $_GET['board'] if not set.
	 * @param null|int $limit How many items to show. Defaults to $_GET['limit'] or 5 if not set.
	 * @param null|int $start Start with the specified item. Defaults to $_GET['start'] or 0 if not set.
	 * @param null|int $length How many characters to show from each post. Defaults to $_GET['length'] or 0 (no limit) if not set.
	 * @param string $output_method The output method. If 'echo', displays the news items, otherwise returns an array of info about them.
	 * @return ?array Displays the news items or returns an array of info about them, depending on output_method.
	 */
	function ssi_boardNews(
		?int $board = null,
		?int $limit = null,
		?int $start = null,
		?int $length = null,
		string $output_method = 'echo',
	): ?array {
		return SMF\ServerSideIncludes::boardNews(
			$board,
			$limit,
			$start,
			$length,
			$output_method,
		);
	}

	/**
	 * Show the most recent events
	 *
	 * @param int $max_events The maximum number of events to show
	 * @param string $output_method The output method. If 'echo', displays the events, otherwise returns an array of info about them.
	 * @return ?array Displays the events or returns an array of info about them, depending on output_method.
	 */
	function ssi_recentEvents(int $max_events = 7, string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::recentEvents($max_events, $output_method);
	}

	/**
	 * Checks whether the specified password is correct for the specified user.
	 *
	 * @param int|string $id The ID or username of a user
	 * @param string $password The password to check
	 * @param bool $is_username If true, treats $id as a username rather than a user ID
	 * @return bool Whether or not the password is correct.
	 */
	function ssi_checkPassword(?int $id = null, ?string $password = null, bool $is_username = false): bool
	{
		return SMF\ServerSideIncludes::checkPassword($id, $password, $is_username);
	}

	/**
	 * Shows the most recent attachments that the user can see
	 *
	 * @param int $num_attachments How many to show
	 * @param array $attachment_ext Only shows attachments with the specified extensions ('jpg', 'gif', etc.) if set
	 * @param string $output_method The output method. If 'echo', displays a table with links/info, otherwise returns an array with information about the attachments
	 * @return ?array Displays a table of attachment info or returns an array containing info about the attachments, depending on output_method.
	 */
	function ssi_recentAttachments(int $num_attachments = 10, array $attachment_ext = [], string $output_method = 'echo'): ?array
	{
		return SMF\ServerSideIncludes::recentAttachments($num_attachments, $attachment_ext, $output_method);
	}

	/*******************
	 * Begin SMF\Session
	 *******************/

	/**
	 * Attempt to start the session, unless it already has been.
	 */
	function loadSession(): void
	{
		SMF\Session::load();
	}

	/**********************
	 * Begin SMF\TaskRunner
	 **********************/

	/**
	 * Calculate the next time the passed tasks should be triggered.
	 *
	 * @param string|array $tasks The ID of a single task or an array of tasks
	 * @param bool $forceUpdate Whether to force the tasks to run now
	 */
	function CalculateNextTrigger(string|array $tasks = [], bool $force_update = false): void
	{
		SMF\TaskRunner::calculateNextTrigger($tasks, $force_update);
	}

	/*****************
	 * Begin SMF\Theme
	 *****************/

	/**
	 * Load a theme, by ID.
	 *
	 * @param int $id The ID of the theme to load
	 * @param bool $initialize Whether or not to initialize a bunch of theme-related variables/settings
	 */
	function loadTheme(int $id = 0, bool $initialize = true)
	{
		return SMF\Theme::load($id, $initialize);
	}

	/**
	 * This loads the bare minimum data to allow us to load language files!
	 */
	function loadEssentialThemeData(): void
	{
		SMF\Theme::loadEssential();
	}

	/**
	 * Load a template - if the theme doesn't include it, use the default.
	 * What this function does:
	 *  - loads a template file with the name template_name from the current, default, or base theme.
	 *  - detects a wrong default theme directory and tries to work around it.
	 *
	 * @uses template_include() to include the file.
	 * @param string $template_name The name of the template to load
	 * @param array|string $style_sheets The name of a single stylesheet or an array of names of stylesheets to load
	 * @param bool $fatal If true, dies with an error message if the template cannot be found
	 * @return bool Whether or not the template was loaded
	 */
	function loadTemplate(string $template_name, string|array $style_sheets = [], bool $fatal = true): ?bool
	{
		return SMF\Theme::loadTemplate($template_name, $style_sheets, $fatal);
	}

	/**
	 * Load a sub-template.
	 * What it does:
	 * 	- loads the sub template specified by sub_template_name, which must be in an already-loaded template.
	 *  - if ?debug is in the query string, shows administrators a marker after every sub template
	 *	for debugging purposes.
	 *
	 * @todo get rid of reading $_REQUEST directly
	 *
	 * @param string $sub_template_name The name of the sub-template to load
	 * @param bool $fatal Whether to die with an error if the sub-template can't be loaded
	 */
	function loadSubTemplate(string $sub_template_name, bool $fatal = false)
	{
		SMF\Theme::loadSubTemplate($sub_template_name, $fatal);
	}

	/**
	 * Add a CSS file for output later
	 *
	 * @param string $filename The name of the file to load
	 * @param array $params An array of parameters
	 * Keys are the following:
	 * 	- ['external'] (true/false): define if the file is a externally located file. Needs to be set to true if you are loading an external file
	 * 	- ['default_theme'] (true/false): force use of default theme url
	 * 	- ['force_current'] (true/false): if this is false, we will attempt to load the file from the default theme if not found in the current theme
	 *  - ['validate'] (true/false): if true script will validate the local file exists
	 *  - ['rtl'] (string): additional file to load in RTL mode
	 *  - ['seed'] (true/false/string): if true or null, use cache stale, false do not, or used a supplied string
	 *  - ['minimize'] bool to add your file to the main minimized file. Useful when you have a file thats loaded everywhere and for everyone.
	 *  - ['order_pos'] int define the load order, when not define it's loaded in the middle, before index.css = -500, after index.css = 500, middle = 3000, end (i.e. after responsive.css) = 10000
	 *  - ['attributes'] array extra attributes to add to the element
	 * @param string $id An ID to stick on the end of the filename for caching purposes
	 */
	function loadCSSFile(string $filename, array $params = [], string $id = ''): void
	{
		SMF\Theme::loadCSSFile($filename, $params, $id);
	}

	/**
	 * Add a block of inline css code to be executed later
	 *
	 * - only use this if you have to, generally external css files are better, but for very small changes
	 *   or for scripts that require help from PHP/whatever, this can be useful.
	 * - all code added with this function is added to the same <style> tag so do make sure your css is valid!
	 *
	 * @param string $css Some css code
	 * @return ?bool Adds the CSS to the $context['css_header'] array or returns if no CSS is specified
	 */
	function addInlineCss(string $css): ?bool
	{
		return SMF\Theme::addInlineCss($css);
	}

	/**
	 * Add a Javascript file for output later
	 *
	 * @param string $fileName The name of the file to load
	 * @param array $params An array of parameter info
	 * Keys are the following:
	 * 	- ['external'] (true/false): define if the file is a externally located file. Needs to be set to true if you are loading an external file
	 * 	- ['default_theme'] (true/false): force use of default theme url
	 * 	- ['defer'] (true/false): define if the file should load in <head> or before the closing <html> tag
	 * 	- ['force_current'] (true/false): if this is false, we will attempt to load the file from the
	 *	default theme if not found in the current theme
	 *	- ['async'] (true/false): if the script should be loaded asynchronously (HTML5)
	 *  - ['validate'] (true/false): if true script will validate the local file exists
	 *  - ['seed'] (true/false/string): if true or null, use cache stale, false do not, or used a supplied string
	 *  - ['minimize'] bool to add your file to the main minimized file. Useful when you have a file thats loaded everywhere and for everyone.
	 *  - ['attributes'] array extra attributes to add to the element
	 *
	 * @param string $id An ID to stick on the end of the filename
	 */
	function loadJavaScriptFile(string $fileName, array $params = [], string $id = ''): void
	{
		SMF\Theme::loadJavaScriptFile($fileName, $params, $id);
	}

	/**
	 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
	 * Cleaner and easier (for modders) than to use the function below.
	 *
	 * @param string $key The key for this variable
	 * @param string $value The value
	 * @param bool $escape Whether or not to escape the value
	 */
	function addJavaScriptVar(string $key, mixed $value, bool $escape = false)
	{
		return SMF\Theme::addJavaScriptVar($key, $value, $escape);
	}

	/**
	 * Add a block of inline Javascript code to be executed later
	 *
	 * - only use this if you have to, generally external JS files are better, but for very small scripts
	 *   or for scripts that require help from PHP/whatever, this can be useful.
	 * - all code added with this function is added to the same <script> tag so do make sure your JS is clean!
	 *
	 * @param string $javascript Some JS code
	 * @param bool $defer Whether the script should load in <head> or before the closing <html> tag
	 * @return ?bool Adds the code to one of the $context['javascript_inline'] arrays or returns if no JS was specified
	 */
	function addInlineJavaScript(string $javascript, bool $defer = false): ?bool
	{
		return SMF\Theme::addInlineJavaScript($javascript, $defer);
	}

	/**
	 * Sets up the basic theme context stuff.
	 *
	 * @param bool $forceload Whether to load the theme even if it's already loaded
	 */
	function setupThemeContext(bool $forceload = false)
	{
		return SMF\Theme::setupContext($forceload);
	}

	/**
	 * Sets up all of the top menu buttons
	 * Saves them in the cache if it is available and on
	 * Places the results in $context
	 */
	function setupMenuContext(): void
	{
		SMF\Theme::setupMenuContext();
	}

	/**
	 * The header template
	 */
	function template_header(): void
	{
		SMF\Theme::template_header();
	}

	/**
	 * Show the copyright.
	 */
	function theme_copyright(): void
	{
		SMF\Theme::copyright();
	}

	/**
	 * The template footer
	 */
	function template_footer(): void
	{
		SMF\Theme::template_footer();
	}

	/**
	 * Output the Javascript files
	 * 	- tabbing in this function is to make the HTML source look good and proper
	 *  - if deferred is set function will output all JS set to load at page end
	 *
	 * @param bool $do_deferred If true will only output the deferred JS (the stuff that goes right before the closing body tag)
	 */
	function template_javascript(bool $do_deferred = false): void
	{
		SMF\Theme::template_javascript($do_deferred);
	}

	/**
	 * Output the CSS files
	 */
	function template_css(): void
	{
		SMF\Theme::template_css();
	}

	/**
	 * Get an array of previously defined files and adds them to our main minified files.
	 * Sets a one day cache to avoid re-creating a file on every request.
	 *
	 * @param array $data The files to minify.
	 * @param string $type either css or js.
	 * @return array Info about the minified file, or about the original files if the minify process failed.
	 */
	function custMinify(array $data, string $type): array
	{
		return SMF\Theme::custMinify($data, $type);
	}

	/**
	 * Clears out old minimized CSS and JavaScript files and ensures $modSettings['browser_cache'] is up to date
	 */
	function deleteAllMinified(): void
	{
		SMF\Theme::deleteAllMinified();
	}

	/**
	 * Set an option via javascript.
	 * - sets a theme option without outputting anything.
	 * - can be used with javascript, via a dummy image... (which doesn't require
	 * the page to reload.)
	 * - requires someone who is logged in.
	 * - accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
	 * - does not log access to the Who's Online log. (in index.php..)
	 */
	function SetJavaScript(): void
	{
		SMF\Actions\ThemeSetOption::call();
	}

	/**
	 * Possibly the simplest and best example of how to use the template system.
	 *  - allows the theme to take care of actions.
	 *  - happens if $settings['catch_action'] is set and action isn't found
	 *   in the action array.
	 *  - can use a template, layers, sub_template, filename, and/or function.
	 */
	function WrapAction(): void
	{
		SMF\Theme::wrapAction();
	}

	/**
	 * Choose a theme from a list.
	 * allows a user to pick a new theme with an interface.
	 * - uses the Themes template. (pick sub template.)
	 * - accessed with ?action=theme;sa=pick.
	 */
	function PickTheme(): void
	{
		SMF\Actions\ThemeChooser::call();
	}

	/****************
	 * Begin SMF\Time
	 ****************/

	/**
	 * Replacement for strftime() that is compatible with PHP 8.1+.
	 *
	 * This does not use the system's strftime library or locale setting,
	 * so results may vary in a few cases from the results of strftime():
	 *
	 *  - %a, %A, %b, %B, %p, %P: Output will use SMF's language strings
	 *    to localize these values. If SMF's language strings have not
	 *    been loaded, PHP's default English strings will be used.
	 *
	 *  - %c, %x, %X: Output will always use ISO format.
	 *
	 * @param string $format A strftime() format string.
	 * @param int|null $timestamp A Unix timestamp.
	 *     If null, defaults to the current time.
	 * @param string|null $tzid Time zone identifier.
	 *     If null, uses default time zone.
	 * @return string The formatted datetime string.
	 */
	function smf_strftime(string $format, ?int $timestamp = null, ?string $tzid = null): string
	{
		return SMF\Time::strftime($format, $timestamp, $tzid);
	}

	/**
	 * Replacement for gmstrftime() that is compatible with PHP 8.1+.
	 *
	 * Calls smf_strftime() with the $tzid parameter set to 'UTC'.
	 *
	 * @param string $format A strftime() format string.
	 * @param int|null $timestamp A Unix timestamp.
	 *     If null, defaults to the current time.
	 * @return string The formatted datetime string.
	 */
	function smf_gmstrftime(string $format, ?int $timestamp = null): string
	{
		return SMF\Time::gmstrftime($format, $timestamp);
	}

	/**
	 * Gets a version of a strftime() format that only shows the date or time components
	 *
	 * @param string $type Either 'date' or 'time'.
	 * @param string $format A strftime() format to process. Defaults to $user_info['time_format'].
	 * @return string A strftime() format string
	 */
	function get_date_or_time_format(string $type = '', string $format = '', ?bool $strftime = null): string
	{
		return SMF\Time::getDateOrTimeFormat($type, $format, $strftime);
	}

	/**
	 * Format a time to make it look purdy.
	 *
	 * - returns a pretty formatted version of time based on the user's format in $user_info['time_format'].
	 * - applies all necessary time offsets to the timestamp, unless offset_type is set.
	 * - if todayMod is set and show_today was not not specified or true, an
	 *   alternate format string is used to show the date with something to show it is "today" or "yesterday".
	 * - performs localization (more than just strftime would do alone.)
	 *
	 * @param int $log_time A timestamp
	 * @param bool|string $show_today Whether to show "Today"/"Yesterday" or just a date.
	 *     If a string is specified, that is used to temporarily override the date format.
	 * @param null|string $tzid Time zone to use when generating the formatted string.
	 *     If empty, the user's time zone will be used.
	 *     If set to 'forum', the value of $modSettings['default_timezone'] will be used.
	 *     If set to a valid time zone identifier, that will be used.
	 *     Otherwise, the value of date_default_timezone_get() will be used.
	 * @return string A formatted time string
	 */
	function timeformat(int $log_time, bool|string $show_today = true, ?string $tzid = null): string
	{
		// For backward compatibility, replace empty values with the user's time
		// zone and replace anything invalid with the forum's default time zone.
		$tzid = empty($tzid) ? SMF\User::getTimezone() : (($tzid === 'forum' || @timezone_open((string) $tzid) === false) ? SMF\Config::$modSettings['default_timezone'] : $tzid);

		$date = new SMF\Time('@' . $log_time, $tzid);

		return is_bool($show_today) ? $date->format(null, $show_today) : $date->format($show_today);
	}

	/**
	 * Helper function to convert date string to english
	 * so that date_parse can parse the date
	 *
	 * @param string $date A localized date string
	 * @return string English date string
	 */
	function convertDateToEnglish(string $date): string
	{
		return SMF\Time::convertToEnglish($date);
	}

	/**
	 * Deprecated function that formerly applied manual offsets to Unix timestamps
	 * in order to provide a fake version of time zone support on ancient versions
	 * of PHP. It now simply returns an unaltered timestamp.
	 *
	 * @deprecated since 2.1
	 * @param bool $use_user_offset This parameter is deprecated and nonfunctional
	 * @param int $timestamp A timestamp (null to use current time)
	 * @return int Seconds since the Unix epoch
	 */
	function forum_time(bool $use_user_offset = true, ?int $timestamp = null): int
	{
		return !isset($timestamp) ? time() : (int) $timestamp;
	}

	/********************
	 * Begin SMF\TimeZone
	 ********************/

	/**
	 * Get a list of time zones.
	 *
	 * @param string $when The date/time for which to calculate the time zone values.
	 *		May be a Unix timestamp or any string that strtotime() can understand.
	 *		Defaults to 'now'.
	 * @return array An array of time zone identifiers and label text.
	 */
	function smf_list_timezones(int|string $when = 'now'): array
	{
		return SMF\TimeZone::list($when);
	}

	/**
	 * Returns an array that instructs SMF how to map specific time zones
	 * (e.g. "America/Denver") onto the user-friendly "meta-zone" labels that
	 * most people think of as time zones (e.g. "Mountain Time").
	 *
	 * @param string $when The date/time used to determine fallback values.
	 *		May be a Unix timestamp or any string that strtotime() can understand.
	 *		Defaults to 'now'.
	 * @return array An array relating time zones to "meta-zones"
	 */
	function get_tzid_metazones(int|string $when = 'now'): array
	{
		return SMF\TimeZone::getTzidMetazones($when);
	}

	/**
	 * Returns an array of all the time zones in a country, ranked according
	 * to population and/or political significance.
	 *
	 * @param string $country_code The two-character ISO-3166 code for a country.
	 * @param string $when The date/time used to determine fallback values.
	 *		May be a Unix timestamp or any string that strtotime() can understand.
	 *		Defaults to 'now'.
	 * @return array An array relating time zones to "meta-zones"
	 */
	function get_sorted_tzids_for_country(string $country_code, int|string $when = 'now'): array
	{
		return SMF\TimeZone::getSortedTzidsForCountry($country_code, $when);
	}

	/**
	 * Checks a list of time zone identifiers to make sure they are all defined in
	 * the installed version of the time zone database, and returns an array of
	 * key-value substitution pairs.
	 *
	 * For defined time zone identifiers, the substitution value will be identical
	 * to the original value. For undefined ones, the substitute will be a time zone
	 * identifier that was equivalent to the missing one at the specified time, or
	 * an empty string if there was no equivalent at that time.
	 *
	 * Note: These fallbacks do not need to include every new time zone ever. They
	 * only need to cover any that are used in $tzid_metazones.
	 *
	 * To find the date & time when a new time zone comes into effect, check
	 * the TZDB changelog at https://data.iana.org/time-zones/tzdb/NEWS
	 *
	 * @param array $tzids The time zone identifiers to check.
	 * @param string $when The date/time used to determine substitute values.
	 *		May be a Unix timestamp or any string that strtotime() can understand.
	 *		Defaults to 'now'.
	 * @return array Substitute values for any missing time zone identifiers.
	 */
	function get_tzid_fallbacks(array $tzids, int|string $when = 'now'): array
	{
		return SMF\TimeZone::getTzidFallbacks($tzids, $when);
	}

	/**
	 * Validates a set of two-character ISO 3166-1 country codes.
	 *
	 * @param array|string $country_codes Array or CSV string of country codes.
	 * @param bool $as_csv If true, return CSV string instead of array.
	 * @return array|string Array or CSV string of valid country codes.
	 */
	function validate_iso_country_codes(array|string $country_codes, bool $as_csv = false): array|string
	{
		return SMF\TimeZone::validateIsoCountryCodes($country_codes, $as_csv);
	}

	/*****************
	 * Begin SMF\Topic
	 *****************/

	/**
	 * Locks a topic... either by way of a moderator or the topic starter.
	 * What this does:
	 *  - locks a topic, toggles between locked/unlocked/admin locked.
	 *  - only admins can unlock topics locked by other admins.
	 *  - requires the lock_own or lock_any permission.
	 *  - logs the action to the moderator log.
	 *  - returns to the topic after it is done.
	 *  - it is accessed via ?action=lock.
	 */
	function LockTopic(): void
	{
		SMF\Actions\TopicLock::call();
	}

	/**
	 * Sticky a topic.
	 * Can't be done by topic starters - that would be annoying!
	 * What this does:
	 *  - stickies a topic - toggles between sticky and normal.
	 *  - requires the make_sticky permission.
	 *  - adds an entry to the moderator log.
	 *  - when done, sends the user back to the topic.
	 *  - accessed via ?action=sticky.
	 */
	function Sticky(): void
	{
		SMF\Actions\TopicSticky::call();
	}

	/**
	 * Approve topics?
	 *
	 * @todo shouldn't this be in topic
	 *
	 * @param array $topics Array of topic ids
	 * @param bool $approve Whether to approve the topics. If false, unapproves them instead
	 * @return bool Whether the operation was successful
	 */
	function approveTopics(array $topics, bool $approve = true): bool
	{
		return SMF\Topic::approve($topics, $approve);
	}

	/**
	 * Moves one or more topics to a specific board. (doesn't check permissions.)
	 * Determines the source boards for the supplied topics
	 * Handles the moving of mark_read data
	 * Updates the posts count of the affected boards
	 *
	 * @param int|int[] $topics The ID of a single topic to move or an array containing the IDs of multiple topics to move
	 * @param int $toBoard The ID of the board to move the topics to
	 */
	function moveTopics(array|int $topics, int $toBoard)
	{
		return SMF\Topic::move($topics, $toBoard);
	}

	/**
	 * Removes the passed id_topic's. (permissions are NOT checked here!).
	 *
	 * @param array|int $topics The topics to remove (can be an id or an array of ids).
	 * @param bool $decreasePostCount Whether to decrease the users' post counts
	 * @param bool $ignoreRecycling Whether to ignore recycling board settings
	 * @param bool $updateBoardCount Whether to adjust topic counts for the boards
	 */
	function removeTopics(
		array|int $topics,
		bool $decreasePostCount = true,
		bool $ignoreRecycling = false,
		bool $updateBoardCount = true,
	) {
		return SMF\Topic::remove($topics, $decreasePostCount, $ignoreRecycling, $updateBoardCount);
	}

	/**
	 * Prepares an array of "likes" info for the topic specified by $topic
	 *
	 * @param int $topic The topic ID to fetch the info from.
	 * @return array An array of IDs of messages in the specified topic that the current user likes
	 */
	function prepareLikesContext(int $topic): array
	{
		return SMF\Topic::load($topic)->getLikedMsgs();
	}

	/***************
	 * Begin SMF\Url
	 ***************/

	/**
	 * Creates an optimized regex to match all known top level domains.
	 *
	 * The optimized regex is stored in $modSettings['tld_regex'].
	 *
	 * To update the stored version of the regex to use the latest list of valid
	 * TLDs from iana.org, set the $update parameter to true. Updating can take some
	 * time, based on network connectivity, so it should normally only be done by
	 * calling this function from a background or scheduled task.
	 *
	 * If $update is not true, but the regex is missing or invalid, the regex will
	 * be regenerated from a hard-coded list of TLDs. This regenerated regex will be
	 * overwritten on the next scheduled update.
	 *
	 * @param bool $update If true, fetch and process the latest official list of TLDs from iana.org.
	 */
	function set_tld_regex(bool $update = false): void
	{
		SMF\Url::setTldRegex($update);
	}

	/**
	 * A wrapper for `parse_url($url)` that can handle URLs with international
	 * characters (a.k.a. IRIs)
	 *
	 * @param string $iri The IRI to parse.
	 * @param int $component Optional flag for parse_url's second parameter.
	 * @return string|int|array|null|bool Same as parse_url(), but with unmangled Unicode.
	 */
	function parse_iri(string $iri, int $component = -1): string|int|array|null|bool
	{
		return SMF\Url::create($iri)->parse($component);
	}

	/**
	 * A wrapper for `filter_var($url, FILTER_VALIDATE_URL)` that can handle URLs
	 * with international characters (a.k.a. IRIs)
	 *
	 * @param string $iri The IRI to test.
	 * @param int $flags Optional flags to pass to filter_var()
	 * @return string|bool Either the original IRI, or false if the IRI was invalid.
	 */
	function validate_iri(string $iri, int $flags = 0): string|bool
	{
		$iri = SMF\Url::create($iri);
		$iri->validate();

		return (string) $iri === '' ? false : (string) $iri;
	}

	/**
	 * A wrapper for `filter_var($url, FILTER_SANITIZE_URL)` that can handle URLs
	 * with international characters (a.k.a. IRIs)
	 *
	 * Note: The returned value will still be an IRI, not a URL. To convert to URL,
	 * feed the result of this function to iri_to_url()
	 *
	 * @param string $iri The IRI to sanitize.
	 * @return string|bool The sanitized version of the IRI
	 */
	function sanitize_iri(string $iri): string|bool
	{
		$iri = SMF\Url::create($iri);
		$iri->sanitize();

		return (string) $iri === '' ? false : (string) $iri;
	}

	/**
	 * Performs Unicode normalization on IRIs.
	 *
	 * Internally calls sanitize_iri(), then performs Unicode normalization on the
	 * IRI as a whole, using NFKC normalization for the domain name (see RFC 3491)
	 * and NFC normalization for the rest.
	 *
	 * @param string $iri The IRI to normalize.
	 * @return string|bool The normalized version of the IRI.
	 */
	function normalize_iri(string $iri): string|bool
	{
		$iri = SMF\Url::create($iri);
		$iri->normalize();

		return (string) $iri === '' ? false : (string) $iri;
	}

	/**
	 * Converts a URL with international characters (an IRI) into a pure ASCII URL
	 *
	 * Uses Punycode to encode any non-ASCII characters in the domain name, and uses
	 * standard URL encoding on the rest.
	 *
	 * @param string $iri A IRI that may or may not contain non-ASCII characters.
	 * @return string|bool The URL version of the IRI.
	 */
	function iri_to_url(string $iri): string|bool
	{
		$iri = SMF\Url::create($iri);
		$iri->toAscii();

		return (string) $iri === '' ? false : (string) $iri;
	}

	/**
	 * Decodes a URL containing encoded international characters to UTF-8
	 *
	 * Decodes any Punycode encoded characters in the domain name, then uses
	 * standard URL decoding on the rest.
	 *
	 * @param string $url The pure ASCII version of a URL.
	 * @return string|bool The UTF-8 version of the URL.
	 */
	function url_to_iri(string $url): string|bool
	{
		$iri = SMF\Url::create($iri);
		$iri->toUtf8();

		return (string) $iri === '' ? false : (string) $iri;
	}

	/**
	 * Gets the appropriate URL to use for images (or whatever) when using SSL
	 *
	 * The returned URL may or may not be a proxied URL, depending on the situation.
	 * Mods can implement alternative proxies using the 'integrate_proxy' hook.
	 *
	 * @param string $url The original URL of the requested resource
	 * @return string The URL to use
	 */
	function get_proxied_url(string $url): SMF\Url
	{
		return (string) SMF\Url::create($url)->proxied();
	}

	/**
	 * Check if the passed url has an SSL certificate.
	 *
	 * Returns true if a cert was found & false if not.
	 *
	 * @param string $url to check, in $boardurl format (no trailing slash).
	 */
	function ssl_cert_found(string $url): bool
	{
		return SMF\Url::create($url)->hasSSL();
	}

	/**
	 * Check if the passed url has a redirect to https:// by querying headers.
	 *
	 * Returns true if a redirect was found & false if not.
	 * Note that when force_ssl = 2, SMF issues its own redirect...  So if this
	 * returns true, it may be caused by SMF, not necessarily an .htaccess redirect.
	 *
	 * @param string $url to check, in $boardurl format (no trailing slash).
	 */
	function https_redirect_active(string $url): bool
	{
		return SMF\Url::create($url)->redirectsToHttps();
	}

	/****************
	 * Begin SMF\User
	 ****************/

	/**
	 * Build query_wanna_see_board and query_see_board for a userid
	 *
	 * Returns array with keys query_wanna_see_board and query_see_board
	 *
	 * @param int $userid of the user
	 */
	function build_query_board(int $id): array
	{
		return SMF\User::buildQueryBoard($id);
	}

	/**
	 * Helper function to set an array of data for an user's avatar.
	 *
	 * Makes assumptions based on the data provided, the following keys are required:
	 * - avatar The raw "avatar" column in members table
	 * - email The user's email. Used to get the gravatar info
	 * - filename The attachment filename
	 *
	 * @param array $data An array of raw info
	 * @return array An array of avatar data
	 */
	function set_avatar_data(array $data = []): array
	{
		return SMF\User::setAvatarData($data);
	}

	/**
	 * Updates the columns in the members table.
	 * Assumes the data has been htmlspecialchar'd.
	 * this function should be used whenever member data needs to be
	 * updated in place of an UPDATE query.
	 *
	 * id_member is either an int or an array of ints to be updated.
	 *
	 * data is an associative array of the columns to be updated and their respective values.
	 * any string values updated should be quoted and slashed.
	 *
	 * the value of any column can be '+' or '-', which mean 'increment'
	 * and decrement, respectively.
	 *
	 * if the member's post number is updated, updates their post groups.
	 *
	 * @param mixed $members An array of member IDs, the ID of a single member, or null to update this for all members
	 * @param array $data The info to update for the members
	 */
	function updateMemberData($members, array $data): void
	{
		SMF\User::updateMemberData($members, $data);
	}

	/**
	 * Gets a member's selected time zone identifier
	 *
	 * @param int $id_member The member id to look up. If not provided, the current user's id will be used.
	 * @return string The time zone identifier string for the user's time zone.
	 */
	function getUserTimezone(?int $id_member = null): string
	{
		return SMF\User::getTimezone($id_member);
	}

	/**
	 * Delete one or more members.
	 * Requires profile_remove_own or profile_remove_any permission for
	 * respectively removing your own account or any account.
	 * Non-admins cannot delete admins.
	 * The function:
	 *   - changes author of messages, topics and polls to guest authors.
	 *   - removes all log entries concerning the deleted members, except the
	 * error logs, ban logs and moderation logs.
	 *   - removes these members' personal messages (only the inbox), avatars,
	 * ban entries, theme settings, moderator positions, poll and votes.
	 *   - updates member statistics afterwards.
	 *
	 * @param int|array $users The ID of a user or an array of user IDs
	 * @param bool $check_not_admin Whether to verify that the users aren't admins
	 */
	function deleteMembers(int|array $users, bool $check_not_admin = false): void
	{
		SMF\User::delete($users, $check_not_admin);
	}

	/**
	 * Checks whether a password meets the current forum rules
	 * - called when registering/choosing a password.
	 * - checks the password obeys the current forum settings for password strength.
	 * - if password checking is enabled, will check that none of the words in restrict_in appear in the password.
	 * - returns an error identifier if the password is invalid, or null.
	 *
	 * @param string $password The desired password
	 * @param string $username The username
	 * @param array $restrict_in An array of restricted strings that cannot be part of the password (email address, username, etc.)
	 * @return null|string Null if valid or a string indicating what the problem was
	 */
	function validatePassword(string $password, string $username, array $restrict_in = []): ?string
	{
		return SMF\User::validatePassword($password, $username, $restrict_in);
	}

	/**
	 * Checks a username obeys a load of rules
	 *
	 * @param int $memID The ID of the member
	 * @param string $username The username to validate
	 * @param bool $return_error Whether to return errors
	 * @param bool $check_reserved_name Whether to check this against the list of reserved names
	 * @return array|null Null if there are no errors, otherwise an array of errors if return_error is true
	 */
	function validateUsername(
		int $memID,
		string $username,
		bool $return_error = false,
		bool $check_reserved_name = true,
	): ?array {
		return SMF\User::validateUsername($memID, $username, $return_error, $check_reserved_name);
	}

	/**
	 * Check if a name is in the reserved words list.
	 * (name, current member id, name/username?.)
	 * - checks if name is a reserved name or username.
	 * - if is_name is false, the name is assumed to be a username.
	 * - the id_member variable is used to ignore duplicate matches with the
	 * current member.
	 *
	 * @param string $name The name to check
	 * @param int $current_id_member The ID of the current member (to avoid false positives with the current member)
	 * @param bool $is_name Whether we're checking against reserved names or just usernames
	 * @param bool $fatal Whether to die with a fatal error if the name is reserved
	 * @return bool|void False if name is not reserved, otherwise true if $fatal is false or dies with a fatal_lang_error if $fatal is true
	 */
	function isReservedName(string $name, int $current_id_member = 0, bool $is_name = true, bool $fatal = true): bool
	{
		return SMF\User::isReservedName($name, $current_id_member, $is_name, $fatal);
	}

	/**
	 * Checks if a given email address might be banned.
	 * Check if a given email is banned.
	 * Performs an immediate ban if the turns turns out positive.
	 *
	 * @param string $email The email to check
	 * @param string $restriction What type of restriction (cannot_post, cannot_register, etc.)
	 * @param string $error The error message to display if they are indeed banned
	 */
	function isBannedEmail(string $email, string $restriction, string $error): void
	{
		SMF\User::isBannedEmail($email, $restriction, $error);
	}

	/**
	 * Finds members by email address, username, or real name.
	 * - searches for members whose username, display name, or e-mail address match the given pattern of array names.
	 * - searches only buddies if buddies_only is set.
	 *
	 * @param array $names The names of members to search for
	 * @param bool $use_wildcards Whether to use wildcards. Accepts wildcards ? and * in the pattern if true
	 * @param bool $buddies_only Whether to only search for the user's buddies
	 * @param int $max The maximum number of results
	 * @return array An array containing information about the matching members
	 */
	function findMembers(
		array $names,
		bool $use_wildcards = false,
		bool $buddies_only = false,
		int $max = 500,
	): array {
		return SMF\User::find($names, $use_wildcards, $buddies_only, $max);
	}

	/**
	 * Retrieves a list of members that have a given permission
	 * (on a given board).
	 * If board_id is not null, a board permission is assumed.
	 * Takes different permission settings into account.
	 * Takes possible moderators (on board 'board_id') into account.
	 *
	 * @param string $permission The permission to check
	 * @param int $board_id If set, checks permission for that specific board
	 * @return array An array containing the IDs of the members having that permission
	 */
	function membersAllowedTo(string $permission, ?int $board_id = null): array
	{
		return SMF\User::membersAllowedTo($permission, $board_id);
	}

	/**
	 * Retrieves a list of membergroups that have the given permission, either on
	 * a given board or in general.
	 *
	 * If board_id is not null, a board permission is assumed.
	 * The function takes different permission settings into account.
	 *
	 * @param string $permission The permission to check
	 * @param int $board_id = null If set, checks permissions for the specified board
	 * @return array An array containing two arrays - 'allowed', which has which groups are allowed to do it and 'denied' which has the groups that are denied
	 */
	function groupsAllowedTo(
		string $permission,
		?int $board_id = null,
		bool $simple = true,
		?int $profile_id = null,
	): array {
		return SMF\User::groupsAllowedTo((array) $permission, $board_id, $simple, $profile_id);
	}

	/**
	 * Retrieves a list of membergroups with the given permissions.
	 *
	 * @param array $general_permissions
	 * @param array $board_permissions
	 * @param int   $profile_id
	 *
	 * @return array An array containing two arrays - 'allowed', which has which groups are allowed to do it and 'denied' which has the groups that are denied
	 */
	function getGroupsWithPermissions(
		array $general_permissions = [],
		array $board_permissions = [],
		int $profile_id = 1,
	): array {
		return SMF\User::getGroupsWithPermissions($general_permissions, $board_permissions, $profile_id);
	}

	/**
	 * Generate a random validation code.
	 *
	 * @return string A random validation code
	 */
	function generateValidationCode(): string
	{
		return SMF\User::generateValidationCode();
	}

	/**
	 * Log the spider presence online.
	 *
	 * @todo Different file?
	 */
	function logSpider(): void
	{
		SMF\User::logSpider();
	}

	/**
	 * Loads an array of users' data by ID or member_name.
	 *
	 * @param array|string $users An array of users by id or name or a single username/id
	 * @param bool $is_name Whether $users contains names
	 * @param string $set What kind of data to load (normal, profile, minimal)
	 * @return array The ids of the members loaded
	 */
	function loadMemberData($users = [], int $type = SMF\User::LOAD_BY_ID, ?string $dataset = null): array
	{
		$loaded = SMF\User::load($users, $type, $dataset);

		return array_map(fn($user) => $user->id, $loaded);
	}

	/**
	 * Load all the important user information.
	 * What it does:
	 * 	- sets up the $user_info array
	 * 	- assigns $user_info['query_wanna_see_board'] for what boards the user can see.
	 * 	- first checks for cookie or integration validation.
	 * 	- uses the current session if no integration function or cookie is found.
	 * 	- checks password length, if member is activated and the login span isn't over.
	 * 		- if validation fails for the user, $id_member is set to 0.
	 * 		- updates the last visit time when needed.
	 */
	function loadUserSettings(): void
	{
		SMF\User::load();
	}

	/**
	 * Load this user's permissions.
	 */
	function loadPermissions(): void
	{
		SMF\User::$me->loadPermissions();
	}

	/**
	 * Loads the user's basic values... meant for template/theme usage.
	 *
	 * @param int $id The ID of a user previously loaded by {@link loadMemberData()}
	 * @param bool $display_custom_fields Whether or not to display custom profile fields
	 * @throws Exception
	 * @return bool|array  False if the data wasn't loaded or the loaded data.
	 */
	function loadMemberContext(int $id, bool $display_custom_fields = false): bool|array
	{
		// The old procedural version of this function returned false if asked
		// to work on a guest. Since it is possible that old mods might rely on
		// that behaviour, we replicate it here.
		if (empty($id)) {
			return false;
		}

		// If the user's data is not already loaded, load it now.
		if (!isset(SMF\User::$loaded[$id])) {
			SMF\User::load((array) $id, SMF\User::LOAD_BY_ID, 'profile');
		}

		return SMF\User::$loaded[$id]->format($display_custom_fields);
	}

	/**
	 * Require a user who is logged in. (not a guest.)
	 * Checks if the user is currently a guest, and if so asks them to login with a message telling them why.
	 * Message is what to tell them when asking them to login.
	 *
	 * @param string $message The message to display to the guest
	 */
	function is_not_guest(string $message = ''): void
	{
		SMF\User::$me->kickIfGuest($message);
	}

	/**
	 * Do banning related stuff.  (ie. disallow access....)
	 * Checks if the user is banned, and if so dies with an error.
	 * Caches this information for optimization purposes.
	 *
	 * @param bool $forceCheck Whether to force a recheck
	 */
	function is_not_banned(bool $force_check = false): void
	{
		SMF\User::$me->kickIfBanned($force_check);
	}

	/**
	 * Fix permissions according to ban status.
	 * Applies any states of banning by removing permissions the user cannot have.
	 */
	function banPermissions(): void
	{
		SMF\User::$me->adjustPermissions();
	}

	/**
	 * Log a ban in the database.
	 * Log the current user in the ban logs.
	 * Increment the hit counters for the specified ban ID's (if any.)
	 *
	 * @param array $ban_ids The IDs of the bans
	 * @param string $email The email address associated with the user that triggered this hit
	 */
	function log_ban(array $ban_ids = [], ?string $email = null): void
	{
		SMF\User::$me->logBan($ban_ids, $email);
	}

	/**
	 * Check if the user is who he/she says he is
	 * Makes sure the user is who they claim to be by requiring a password to be typed in every hour.
	 * Is turned on and off by the securityDisable setting.
	 * Uses the adminLogin() function of Subs-Auth.php if they need to login, which saves all request (post and get) data.
	 *
	 * @param string $type What type of session this is
	 * @param string $force When true, require a password even if we normally wouldn't
	 * @return ?string Returns 'session_verify_fail' if verification failed
	 */
	function validateSession(string $type = 'admin', bool $force = false): ?string
	{
		return SMF\User::$me->validateSession($type, $force);
	}

	/**
	 * Make sure the user's correct session was passed, and they came from here.
	 * Checks the current session, verifying that the person is who he or she should be.
	 * Also checks the referrer to make sure they didn't get sent here.
	 * Depends on the disableCheckUA setting, which is usually missing.
	 * Will check GET, POST, or REQUEST depending on the passed type.
	 * Also optionally checks the referring action if passed. (note that the referring action must be by GET.)
	 *
	 * @param string $type The type of check (post, get, request)
	 * @param string $from_action The action this is coming from
	 * @param bool $is_fatal Whether to die with a fatal error if the check fails
	 * @return string The error message if is_fatal is false.
	 */
	function checkSession(string $type = 'post', string $from_action = '', bool $is_fatal = true): string
	{
		return SMF\User::$me->checkSession($type, $from_action, $is_fatal);
	}

	/**
	 * Check the user's permissions.
	 * checks whether the user is allowed to do permission. (ie. post_new.)
	 * If boards is specified, checks those boards instead of the current one.
	 * If any is true, will return true if the user has the permission on any of the specified boards
	 * Always returns true if the user is an administrator.
	 *
	 * @param string|array $permission A single permission to check or an array of permissions to check
	 * @param int|array $boards The ID of a board or an array of board IDs if we want to check board-level permissions
	 * @param bool $any Whether to check for permission on at least one board instead of all boards
	 * @return bool Whether the user has the specified permission
	 */
	function allowedTo(string|array $permission, int|array|null $boards = null, bool $any = false): bool
	{
		// You're never allowed to do something if your data hasn't been loaded yet!
		if (!isset(SMF\User::$me)) {
			return false;
		}

		return SMF\User::$me->allowedTo($permission, $boards, $any);
	}

	/**
	 * Fatal error if they cannot.
	 * Uses allowedTo() to check if the user is allowed to do permission.
	 * Checks the passed boards or current board for the permission.
	 * If $any is true, the user only needs permission on at least one of the boards to pass
	 * If they are not, it loads the Errors language file and shows an error using $txt['cannot_' . $permission].
	 * If they are a guest and cannot do it, this calls is_not_guest().
	 *
	 * @param string|array $permission A single permission to check or an array of permissions to check
	 * @param int|array $boards The ID of a single board or an array of board IDs if we're checking board-level permissions (null otherwise)
	 * @param bool $any Whether to check for permission on at least one board instead of all boards
	 */
	function isAllowedTo(string|array $permission, int|array|null $boards = null, bool $any = false): bool
	{
		// You're never allowed to do something if your data hasn't been loaded yet!
		if (!isset(SMF\User::$me)) {
			return false;
		}

		SMF\User::$me->isAllowedTo($permission, $boards, $any);

		// If we get here, the user is allowed.
		return true;
	}

	/**
	 * Return the boards a user has a certain (board) permission on. (array(0) if all.)
	 *  - returns a list of boards on which the user is allowed to do the specified permission.
	 *  - returns an array with only a 0 in it if the user has permission to do this on every board.
	 *  - returns an empty array if he or she cannot do this on any board.
	 * If check_access is true will also make sure the group has proper access to that board.
	 *
	 * @param string|array $permissions A single permission to check or an array of permissions to check
	 * @param bool $check_access Whether to check only the boards the user has access to
	 * @param bool $simple Whether to return a simple array of board IDs or one with permissions as the keys
	 * @return array An array of board IDs or an array containing 'permission' => 'board,board2,...' pairs
	 */
	function boardsAllowedTo(string|array $permissions, bool $check_access = true, bool $simple = true): array
	{
		// You're never allowed to do something if your data hasn't been loaded yet!
		if (!isset(SMF\User::$me)) {
			return false;
		}

		return SMF\User::$me->boardsAllowedTo($permissions, $check_access, $simple);
	}

	/*****************
	 * Begin SMF\Utils
	 *****************/

	/**
	 * Replaces invalid characters with a substitute.
	 *
	 * !!! Warning !!! Setting $substitute to '' in order to delete invalid
	 * characters from the string can create unexpected security problems. See
	 * https://www.unicode.org/reports/tr36/#Deletion_of_Noncharacters for an
	 * explanation.
	 *
	 * @param string $string The string to sanitize.
	 * @param int $level Controls filtering of invisible formatting characters.
	 *      0: Allow valid formatting characters. Use for sanitizing text in posts.
	 *      1: Allow necessary formatting characters. Use for sanitizing usernames.
	 *      2: Disallow all formatting characters. Use for internal comparisions
	 *         only, such as in the word censor, search contexts, etc.
	 *      Default: 0.
	 * @param string|null $substitute Replacement string for the invalid characters.
	 *      If not set, the Unicode replacement character (U+FFFD) will be used
	 *      (or a fallback like "?" if necessary).
	 * @return string The sanitized string.
	 */
	function sanitize_chars(string $string, int $level = 0, ?string $substitute = null): string
	{
		return SMF\Utils::sanitizeChars($string, $level, $substitute);
	}

	/**
	 * Normalizes space characters and line breaks.
	 *
	 * @param string $string The string to sanitize.
	 * @param bool $vspace If true, replaces all line breaks and vertical space
	 *      characters with "\n". Default: true.
	 * @param bool $hspace If true, replaces horizontal space characters with a
	 *      plain " " character. (Note: tabs are not replaced unless the
	 *      'replace_tabs' option is supplied.) Default: false.
	 * @param array $options An array of bool options. Possible values are:
	 *      - no_breaks: Vertical spaces are replaced by " " instead of "\n".
	 *      - replace_tabs: If true, tabs are are replaced by " " chars.
	 *      - collapse_hspace: If true, removes extra horizontal spaces.
	 * @return string The sanitized string.
	 */
	function normalize_spaces($string, $vspace = true, $hspace = false, $options = []): string
	{
		return SMF\Utils::normalizeSpaces($string, $vspace, $hspace, $options);
	}

	/**
	 * Adds html entities to the array/variable.  Uses two underscores to guard against overloading.
	 * What it does:
	 * - adds entities (&quot;, &lt;, &gt;) to the array or string var.
	 * - importantly, does not effect keys, only values.
	 * - calls itself recursively if necessary.
	 *
	 * @param array|string $var The string or array of strings to add entites to
	 * @param int $level Which level we're at within the array (if called recursively)
	 * @return array|string The string or array of strings with entities added
	 */
	function htmlspecialchars__recursive(array|string $var, int $flags = ENT_COMPAT, $encoding = 'UTF-8'): array|string
	{
		return SMF\Utils::htmlspecialcharsRecursive($var, $flags, $encoding);
	}

	/**
	 * Replaces special entities in strings with the real characters.
	 *
	 * Functionally equivalent to htmlspecialchars_decode(), except that this also
	 * replaces '&nbsp;' with a simple space character.
	 *
	 * @param string $string A string
	 * @return string The string without entities
	 */
	function un_htmlspecialchars(string $string, int $flags = ENT_QUOTES, $encoding = 'UTF-8'): string
	{
		return SMF\Utils::htmlspecialcharsDecode($string, $flags, $encoding);
	}

	/**
	 * Trim a string including the HTML space, character 160.  Uses two underscores to guard against overloading.
	 * What it does:
	 * - trims a string or an the var array using html characters as well.
	 * - does not effect keys, only values.
	 * - may call itself recursively if needed.
	 *
	 * @param array|string $var The string or array of strings to trim
	 * @param int $level = 0 How deep we're at within the array (if called recursively)
	 * @return array|string The trimmed string or array of trimmed strings
	 */
	function htmltrim__recursive(array|string $var): array|string
	{
		return SMF\Utils::htmlTrimRecursive($var);
	}

	/**
	 * Shorten a subject + internationalization concerns.
	 *
	 * - shortens a subject so that it is either shorter than length, or that length plus an ellipsis.
	 * - respects internationalization characters and entities as one character.
	 * - avoids trailing entities.
	 * - returns the shortened string.
	 *
	 * @param string $subject The subject
	 * @param int $len How many characters to limit it to
	 * @return string The shortened subject - either the entire subject (if it's <= $len) or the subject shortened to $len characters with "..." appended
	 */
	function shorten_subject(string $subject, int $len): string
	{
		return SMF\Utils::shorten($subject, $len);
	}

	/**
	 * Chops a string into words and prepares them to be inserted into (or searched from) the database.
	 *
	 * @param string $string The text to split into words
	 * @param int $max_length The maximum number of characters per word
	 * @param bool $encrypt Whether to encrypt the results
	 * @return array An array of ints or words depending on $encrypt
	 */
	function text2words(string $string, ?int $max_length = 20, bool $encrypt = false): array
	{
		if ($encrypt) {
			return SMF\Search\APIs\Custom::getWordNumbers($string, $max_length);
		}

		if (empty($max_length)) {
			return SMF\Utils::extractWords($string, 2);
		}

		return array_map(
			fn($word) => SMF\Utils::truncate($word, $max_length),
			SMF\Utils::extractWords($string, 2),
		);
	}

	/**
	 * Creates optimized regular expressions from arrays of strings.
	 *
	 * An optimized regex built using this function will be much faster than a
	 * simple regex built using `implode('|', $strings)` --- anywhere from several
	 * times to several orders of magnitude faster.
	 *
	 * However, the time required to build the optimized regex is approximately
	 * equal to the time it takes to execute the simple regex. Therefore, it is only
	 * worth calling this function if the resulting regex will be used more than
	 * once.
	 *
	 * Because PHP places an upper limit on the allowed length of a regex, very
	 * large arrays of $strings may not fit in a single regex. Normally, the excess
	 * strings will simply be dropped. However, if the $returnArray parameter is set
	 * to true, this function will build as many regexes as necessary to accommodate
	 * everything in $strings and return them in an array. You will need to iterate
	 * through all elements of the returned array in order to test all possible
	 * matches.
	 *
	 * @param array $strings An array of strings to make a regex for.
	 * @param string $delim An optional delimiter character to pass to preg_quote().
	 * @param bool $returnArray If true, returns an array of regexes.
	 * @return string|array One or more regular expressions to match any of the input strings.
	 */
	function build_regex(array $strings, ?string $delim = null, bool $return_array = false): string|array
	{
		return SMF\Utils::buildRegex($strings, $delim, $return_array);
	}

	/**
	 * Clean up the XML to make sure it doesn't contain invalid characters.
	 *
	 * See https://www.w3.org/TR/xml/#charsets
	 *
	 * @param string $string The string to clean
	 * @return string The cleaned string
	 */
	function cleanXml(string $string): string
	{
		return SMF\Utils::cleanXml($string);
	}

	/**
	 * Escapes (replaces) characters in strings to make them safe for use in JavaScript
	 *
	 * @param string $string The string to escape
	 * @param bool $as_json If true, escape as double-quoted string. Default false.
	 * @return string The escaped string
	 */
	function JavaScriptEscape(string $string, bool $as_json = false): string
	{
		return SMF\Utils::escapeJavaScript($string, $as_json);
	}

	/**
	 * Remove slashes recursively.  Uses two underscores to guard against overloading.
	 * What it does:
	 * - removes slashes, recursively, from the array or string var.
	 * - effects both keys and values of arrays.
	 * - calls itself recursively to handle arrays of arrays.
	 *
	 * @param array|string $var The string or array of strings to strip slashes from
	 * @param int $level = 0 What level we're at within the array (if called recursively)
	 * @return array|string The string or array of strings with slashes stripped
	 */
	function stripslashes__recursive($var, $level = 0): array|string
	{
		return SMF\Utils::stripslashesRecursive($var, $level);
	}

	/**
	 * Removes url stuff from the array/variable.  Uses two underscores to guard against overloading.
	 * What it does:
	 * - takes off url encoding (%20, etc.) from the array or string var.
	 * - importantly, does it to keys too!
	 * - calls itself recursively if there are any sub arrays.
	 *
	 * @param array|string $var The string or array of strings to decode
	 * @param int $level Which level we're at within the array (if called recursively)
	 * @return array|string The decoded string or array of decoded strings
	 */
	function urldecode__recursive(array|string $var, int $level): array|string
	{
		return SMF\Utils::urldecodeRecursive($var, $level);
	}

	/**
	 * Adds slashes to the array/variable.
	 * What it does:
	 * - returns the var, as an array or string, with escapes as required.
	 * - importantly escapes all keys and values!
	 * - calls itself recursively if necessary.
	 *
	 * @param array|string $var A string or array of strings to escape
	 * @return array|string The escaped string or array of escaped strings
	 */
	function escapestring__recursive(array|string $var): array|string
	{
		return SMF\Utils::escapestringRecursive($var);
	}

	/**
	 * Unescapes any array or variable.  Uses two underscores to guard against overloading.
	 * What it does:
	 * - unescapes, recursively, from the array or string var.
	 * - effects both keys and values of arrays.
	 * - calls itself recursively to handle arrays of arrays.
	 *
	 * @param array|string $var The string or array of strings to unescape
	 * @return array|string The unescaped string or array of unescaped strings
	 */
	function unescapestring__recursive(array|string $var): array|string
	{
		return SMF\Utils::escapestringRecursive($var);
	}

	/**
	 * Truncate an array to a specified length
	 *
	 * @param array $array The array to truncate
	 * @param int $max_length The upperbound on the length
	 * @param int $deep How levels in an multidimensional array should the function take into account.
	 * @return array The truncated array
	 */
	function truncate_array(array $array, int $max_length = 1900): array
	{
		return SMF\Utils::truncateArray($array, $max_length);
	}

	/**
	 * array_length Recursive
	 * @param array $array
	 * @param int $deep How many levels should the function
	 * @return int
	 */
	function array_length(array $array): int
	{
		return SMF\Utils::arrayLength($array);
	}

	/**
	 * Wrapper function for json_decode() with error handling.
	 *
	 * @param string $json The string to decode.
	 * @param bool $returnAsArray To return the decoded string as an array or an object, SMF only uses Arrays but to keep on compatibility with json_decode its set to false as default.
	 * @param bool $logIt To specify if the error will be logged if theres any.
	 * @return array Either an empty array or the decoded data as an array.
	 */
	function smf_json_decode(string $json, bool $associative = false, bool $should_log = true): mixed
	{
		return SMF\Utils::jsonDecode($json, $associative, 512, 0, $should_log);
	}

	/**
	 * Wrapper for _safe_serialize() that handles exceptions and multibyte encoding issues.
	 *
	 * @param mixed $value
	 * @return string
	 */
	function safe_serialize(mixed $value): string
	{
		return SMF\Utils::safeSerialize($value);
	}

	/**
	 * Wrapper for _safe_unserialize() that handles exceptions and multibyte encoding issue
	 *
	 * @param string $str
	 * @return mixed
	 */
	function safe_unserialize(string $str): mixed
	{
		return SMF\Utils::safeUnserialize($str);
	}

	/**
	 * Attempts to determine the MIME type of some data or a file.
	 *
	 * @param string $data The data to check, or the path or URL of a file to check.
	 * @param string $is_path If true, $data is a path or URL to a file.
	 * @return string|bool A MIME type, or false if we cannot determine it.
	 */
	function get_mime_type(string $data, bool $is_path = false): string|bool
	{
		return SMF\Utils::getMimeType($data, $is_path);
	}

	/**
	 * Checks whether a file or data has the expected MIME type.
	 *
	 * @param string $data The data to check, or the path or URL of a file to check.
	 * @param string $type_pattern A regex pattern to match the acceptable MIME types.
	 * @param string $is_path If true, $data is a path or URL to a file.
	 * @return int 1 if the detected MIME type matches the pattern, 0 if it doesn't, or 2 if we can't check.
	 */
	function check_mime_type(string $data, string $type_pattern, bool $is_path = false): int
	{
		return SMF\Utils::checkMimeType($data, $type_pattern, $is_path);
	}

	/**
	 * Tries different modes to make file/dirs writable. Wrapper function for chmod()
	 *
	 * @param string $file The file/dir full path.
	 * @param int $value Not needed, added for legacy reasons.
	 * @return bool  true if the file/dir is already writable or the function was able to make it writable, false if the function couldn't make the file/dir writable.
	 */
	function smf_chmod(string $path): bool
	{
		return SMF\Utils::makeWritable(($path));
	}

	/**
	 * Sends an appropriate HTTP status header based on a given status code
	 *
	 * @param int $code The status code
	 * @param string $status The string for the status. Set automatically if not provided.
	 */
	function send_http_status(int $code, string $status = ''): void
	{
		SMF\Utils::sendHttpStatus($code, $status);
	}

	/**
	 * Outputs a response.
	 * It assumes the data is already a string.
	 *
	 * @param string $data The data to print
	 * @param string $type The content type. Defaults to Json.
	 */
	function smf_serverResponse(string $data = '', $type = 'Content-Type: application/json'): string
	{
		return SMF\Utils::serverResponse($data, $type);
	}

	/**
	 * Make sure the browser doesn't come back and repost the form data.
	 * Should be used whenever anything is posted.
	 *
	 * @param string $setLocation The URL to redirect them to
	 * @param bool $refresh Whether to use a meta refresh instead
	 * @param bool $permanent Whether to send a 301 Moved Permanently instead of a 302 Moved Temporarily
	 */
	function redirectexit(string $setLocation = '', bool $refresh = false, bool $permanent = false): void
	{
		SMF\Utils::redirectexit($setLocation, $refresh, $permanent);
	}

	/**
	 * Ends execution.  Takes care of template loading and remembering the previous URL.
	 *
	 * @param bool $header Whether to do the header
	 * @param bool $do_footer Whether to do the footer
	 * @param bool $from_index Whether we're coming from the board index
	 * @param bool $from_fatal_error Whether we're coming from a fatal error
	 */
	function obExit(
		?bool $header = null,
		?bool $do_footer = null,
		bool $from_index = false,
		bool $from_fatal_error = false,
	): void {
		SMF\Utils::obExit($header, $do_footer, $from_index, $from_fatal_error);
	}

	/**
	 * Receives a string and tries to figure it out if its a method or a function.
	 * If a method is found, it looks for a "#" which indicates SMF should create a new instance of the given class.
	 * Checks the string/array for is_callable() and return false/fatal_lang_error is the given value results in a non callable string/array.
	 * Prepare and returns a callable depending on the type of method/function found.
	 *
	 * @param mixed $input The string containing a function name or a static call. The function can also accept a closure, object or a callable array (object/class, valid_callable)
	 * @param bool $return If true, the function will not call the function/method but instead will return the formatted string.
	 * @return string|array|bool Either a string or an array that contains a callable function name or an array with a class and method to call. False if the given string cannot produce a callable var.
	 */
	function call_helper(mixed $input, bool $return = false): mixed
	{
		$callable = SMF\Utils::getCallable($input);

		// Just return the callable if that's all we were asked to do.
		if ($return) {
			return $callable;
		}

		call_user_func($callable);
	}

	/**
	 * Decode numeric html entities to their ascii or UTF8 equivalent character.
	 *
	 * Callback function for preg_replace_callback in subs-members
	 * Does basic scan to ensure characters are inside a valid range
	 *
	 * @param array $matches An array of matches
	 * @return string A fixed string
	 */
	function replaceEntities__callback(array $matches): string
	{
		return strtr(
			htmlspecialchars(SMF\Utils::entityDecode($matches[1], true), ENT_QUOTES),
			[
				'&amp;' => '&#038;',
				'&quot;' => '&#034;',
				'&lt;' => '&#060;',
				'&gt;' => '&#062;',
			],
		);
	}

	/**
	 * Converts html entities to utf8 equivalents
	 *
	 * Callback function for preg_replace_callback
	 * Does basic checks to keep characters inside a viewable range.
	 *
	 * @param array $matches An array of matches
	 * @return string The fixed string
	 */
	function fixchar__callback(array $matches): string
	{
		return SMF\Utils::entityDecode($matches[0], true);
	}

	/**
	 * Strips out invalid html entities, replaces others with html style &#123; codes
	 *
	 * Callback function used of preg_replace_callback in smcFunc $ent_checks, for example
	 * strpos, strlen, substr etc
	 *
	 * @param array $matches An array of matches
	 * @return string The fixed string
	 */
	function entity_fix__callback(array $matches): string
	{
		return SMF\Utils::sanitizeEntities(SMF\Utils::entityFix($matches[1]));
	}

	/********************
	 * Begin SMF\Verifier
	 ********************/

	/**
	 * Create a anti-bot verification control?
	 *
	 * @param array &$options Options for the verification control
	 * @param bool $do_test Whether to check to see if the user entered the code correctly
	 * @return bool|array False if there's nothing to show, true if everything went well or an array containing error indicators if the test failed
	 */
	function create_control_verification(array &$options, bool $do_test = false): bool|array
	{
		return SMF\Verifier::create($options, $do_test);
	}

	/**********************************************************
	 * Begin deprecated functions that don't live anywhere else
	 **********************************************************/

	/**
	 * Microsoft uses their own character set Code Page 1252 (CP1252), which is
	 * a superset of ISO 8859-1, defining several characters between DEC 128 and
	 * 159 that are not normally displayable. This converts the popular ones
	 * that appear from a cut and paste from Windows.
	 *
	 * @deprecated 3.0
	 *
	 * @param string $string The string.
	 * @return string The sanitized string.
	 */
	function sanitizeMSCutPaste(string $string): string
	{
		if (empty($string)) {
			return $string;
		}

		// UTF-8 occurrences of MS special characters.
		$findchars_utf8 = [
			"\xe2\x80\x9a",	// single low-9 quotation mark, U+201A
			"\xe2\x80\x9e",	// double low-9 quotation mark, U+201E
			"\xe2\x80\xa6",	// horizontal ellipsis, U+2026
			"\xe2\x80\x98",	// left single curly quote, U+2018
			"\xe2\x80\x99",	// right single curly quote, U+2019
			"\xe2\x80\x9c",	// left double curly quote, U+201C
			"\xe2\x80\x9d",	// right double curly quote, U+201D
		];

		// windows 1252 / iso equivalents
		$findchars_iso = [
			chr(130),
			chr(132),
			chr(133),
			chr(145),
			chr(146),
			chr(147),
			chr(148),
		];

		// safe replacements
		$replacechars = [
			',',	// &sbquo;
			',,',	// &bdquo;
			'...',	// &hellip;
			"'",	// &lsquo;
			"'",	// &rsquo;
			'"',	// &ldquo;
			'"',	// &rdquo;
		];

		$encoding = (!empty(SMF\Utils::$context['utf8']) ? 'UTF-8' : (!empty(SMF\Config::$modSettings['global_character_set']) ? SMF\Config::$modSettings['global_character_set'] : (!empty(SMF\Lang::$txt['lang_character_set']) ? SMF\Lang::$txt['lang_character_set'] : 'UTF-8')));

		$string = str_replace($encoding === 'UTF-8' ? $findchars_utf8 : $findchars_iso, $replacechars, $string);

		return $string;
	}
}

/***************************
 * PHP version compatibility
 ***************************/

/*
 * Prevent fatal errors under PHP 8 when a disabled internal function is called.
 *
 * Before PHP 8, calling a disabled internal function merely generated a
 * warning that could be easily suppressed by the @ operator. But as of PHP 8
 * a disabled internal function is treated like it is undefined, which means
 * a fatal error will be thrown and execution will halt. SMF expects the old
 * behaviour, so these no-op polyfills make sure that is what happens.
 */
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
	// This is wrapped in a closure to keep the global namespace clean.
	call_user_func(function () {
		/*
		 * This array contains function names that meet the following conditions:
		 *
		 * 1. SMF assumes they are defined, even if disabled. Note that prior to
		 *    PHP 8, this was always true for internal functions.
		 *
		 * 2. Some hosts are known to disable them.
		 *
		 * 3. SMF can get by without them (as opposed to missing functions that
		 *    really SHOULD cause execution to halt).
		 */
		$optional_funcs = [
			'set_time_limit',
		];

		foreach ($optional_funcs as $func) {
			if (!function_exists($func)) {
				eval('function ' . $func . '() { trigger_error("' . $func . '() has been disabled", E_USER_WARNING); }');
			}
		}
	});
}

if (!function_exists('smf_crc32')) {
	/**
	 * Compatibility function.
	 * crc32 doesn't work as expected on 64-bit functions - make our own.
	 * https://php.net/crc32#79567
	 *
	 * @param string $number
	 * @return string The crc32 polynomial of $number
	 */
	function smf_crc32($number)
	{
		$crc = crc32($number);

		if ($crc & 0x80000000) {
			$crc ^= 0xffffffff;
			$crc += 1;
			$crc = -$crc;
		}

		return $crc;
	}
}

/*****************
 * Polyfills, etc.
 *****************/

if (!function_exists('idn_to_ascii')) {
	// This is wrapped in a closure to keep the global namespace clean.
	call_user_func(function () {
		/**
		 * IDNA_* constants used as flags for the idn_to_* functions.
		 */
		$idna_constants = [
			'IDNA_DEFAULT' => 0,
			'IDNA_ALLOW_UNASSIGNED' => 1,
			'IDNA_USE_STD3_RULES' => 2,
			'IDNA_CHECK_BIDI' => 4,
			'IDNA_CHECK_CONTEXTJ' => 8,
			'IDNA_NONTRANSITIONAL_TO_ASCII' => 16,
			'IDNA_NONTRANSITIONAL_TO_UNICODE' => 32,
			'INTL_IDNA_VARIANT_2003' => 0,
			'INTL_IDNA_VARIANT_UTS46' => 1,
		];

		foreach ($idna_constants as $name => $value) {
			if (!defined($name)) {
				define($name, $value);
			}
		}
	});

	/**
	 * Compatibility function.
	 *
	 * This is not a complete polyfill:
	 *
	 *  - $flags only supports IDNA_DEFAULT, IDNA_NONTRANSITIONAL_TO_ASCII,
	 *    and IDNA_USE_STD3_RULES.
	 *  - $variant is ignored, because INTL_IDNA_VARIANT_UTS46 is always used.
	 *  - $idna_info is ignored.
	 *
	 * @param string $domain The domain to convert, which must be UTF-8 encoded.
	 * @param int $flags A subset of possible IDNA_* flags.
	 * @param int $variant Ignored in this compatibility function.
	 * @param array|null $idna_info Ignored in this compatibility function.
	 * @return string|bool The domain name encoded in ASCII-compatible form, or false on failure.
	 */
	function idn_to_ascii($domain, $flags = 0, $variant = 1, &$idna_info = null)
	{
		static $Punycode;

		if (!is_object($Punycode)) {
			$Punycode = new SMF\Punycode();
		}

		if (method_exists($Punycode, 'useStd3')) {
			$Punycode->useStd3($flags === ($flags | IDNA_USE_STD3_RULES));
		}

		if (method_exists($Punycode, 'useNonTransitional')) {
			$Punycode->useNonTransitional($flags === ($flags | IDNA_NONTRANSITIONAL_TO_ASCII));
		}

		return $Punycode->encode($domain);
	}

	/**
	 * Compatibility function.
	 *
	 * This is not a complete polyfill:
	 *
	 *  - $flags only supports IDNA_DEFAULT, IDNA_NONTRANSITIONAL_TO_UNICODE,
	 *    and IDNA_USE_STD3_RULES.
	 *  - $variant is ignored, because INTL_IDNA_VARIANT_UTS46 is always used.
	 *  - $idna_info is ignored.
	 *
	 * @param string $domain Domain to convert, in an IDNA ASCII-compatible format.
	 * @param int $flags Ignored in this compatibility function.
	 * @param int $variant Ignored in this compatibility function.
	 * @param array|null $idna_info Ignored in this compatibility function.
	 * @return string|bool The domain name in Unicode, encoded in UTF-8, or false on failure.
	 */
	function idn_to_utf8($domain, $flags = 0, $variant = 1, &$idna_info = null)
	{
		static $Punycode;

		if (!is_object($Punycode)) {
			$Punycode = new SMF\Punycode();
		}

		$Punycode->useStd3($flags === ($flags | IDNA_USE_STD3_RULES));
		$Punycode->useNonTransitional($flags === ($flags | IDNA_NONTRANSITIONAL_TO_UNICODE));

		return $Punycode->decode($domain);
	}
}

if (!function_exists('array_is_list')) {
	function array_is_list(array $array): bool
	{
		return array_keys($array) === range(0, count($array) - 1);
	}
}

?>