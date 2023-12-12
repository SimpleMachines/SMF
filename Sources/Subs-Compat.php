<?php

/**
 * This file provides compatibility functions and code for older versions of
 * SMF and PHP, such as missing extensions or 64-bit vs 32-bit systems.
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
use SMF\Actions;
use SMF\Cache;
use SMF\Db;
use SMF\Graphics\Image;
use SMF\PackageManager;
use SMF\PersonalMessage;
use SMF\Punycode;
use SMF\Search;
use SMF\Unicode\Utf8String;
use SMF\WebFetch\WebFetchApi;

if (!defined('SMF')) {
	die('No direct access...');
}

/*********************************************
 * SMF\Config::$backward_compatibility support
 *********************************************/


if (!empty(SMF\Config::$backward_compatibility)) {
	/*
	 * In SMF 2.x, there was a file named Subs.php that was always loaded early in
	 * the startup process and that contained many utility functions. Everything
	 * else assumed that those functions were available. Subs.php went the way of
	 * the dodo in SMF 3.0 after all its functions were migrated elsewhere, but
	 * mods that rely on backward compatibilty support will still expect all those
	 * functions to be available. So if backward compatibilty support is enabled,
	 * we need to load a bunch of classes in order to make them available.
	 */
	class_exists('SMF\\Attachment');
	class_exists('SMF\\BBCodeParser');
	class_exists('SMF\\Logging');
	class_exists('SMF\\PageIndex');
	class_exists('SMF\\Theme');
	class_exists('SMF\\Time');
	class_exists('SMF\\TimeZone');
	class_exists('SMF\\Topic');
	class_exists('SMF\\Url');
	class_exists('SMF\\User');
	class_exists('SMF\\WebFetch\\WebFetchApi');

	function sanitize_chars(string $string, int $level = 0, ?string $substitute = null): string
	{
		return SMF\Utils::sanitizeChars($string, $level, $substitute);
	}
	function Activate()
	{
		return Actions\Activate::call();
	}
	/**
	 * Begin
	 * Sources\Admin\ACP
	 * */
	function AdminMain()
	{
		return Actions\Admin\ACP::call();
	}
	function prepareDBSettingContext(&$config_vars): void
	{
		Actions\Admin\ACP::prepareDBSettingContext($config_vars);
	}
	function saveSettings(&$config_vars): void
	{
		Actions\Admin\ACP::saveSettings($config_vars);
	}
	function saveDbSettings(&$config_vars): void
	{
		Actions\Admin\ACP::saveDBSettings($config_vars);
	}
	function getServerVersions(array $checkFor): array
	{
		return Actions\Admin\ACP::getServerVersions($checkFor);
	}
	function getFileVersions(array &$versionOptions): array
	{
		return Actions\Admin\ACP::getFileVersions($versionOptions);
	}
	function updateAdminPreferences(): void
	{
		Actions\Admin\ACP::updateAdminPreferences();
	}
	function emailAdmins(string $template, array $replacements = [], array $additional_recipients = []): void
	{
		Actions\Admin\ACP::emailAdmins($template, $replacements, $additional_recipients);
	}
	function adminLogin(string $type = 'admin'): void
	{
		Actions\Admin\ACP::adminLogin($type);
	}
	/**
	 * End
	 * Actions\Admin\ACP
	 *
	 * Begin
	 * Actions\Admin\AntiSpam
	 */
	function ModifyAntispamSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\AntiSpam::modifyAntispamSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\AntiSpam
	 *
	 * Begin
	 * Actions\Admin\Attachments
	 */
	function ManageAttachments()
	{
		return Actions\Admin\Attachments::call();
	}
	function attachDirStatus(string $dir, int $expected_files): array
	{
		return Actions\Admin\Attachments::attachDirStatus($dir, $expected_files);
	}
	function ManageAttachmentSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Attachments::manageAttachmentSettings($return_config);
	}
	function ManageAvatarSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Attachments::manageAvatarSettings($return_config);
	}
	function BrowseFiles(): void
	{
		Actions\Admin\Attachments::subActionProvider('browse', false);
	}
	function MaintainFiles(): void
	{
		Actions\Admin\Attachments::maintainFiles();
	}
	function RemoveAttachment(): void
	{
		Actions\Admin\Attachments::removeAttachment();
	}
	function RemoveAttachmentByAge(): void
	{
		Actions\Admin\Attachments::removeAttachmentByAge();
	}
	function RemoveAttachmentBySize(): void
	{
		Actions\Admin\Attachments::removeAttachmentBySize();
	}
	function RemoveAllAttachments(): void
	{
		Actions\Admin\Attachments::removeAllAttachments();
	}
	function RepairAttachments(): void
	{
		Actions\Admin\Attachments::repairAttachments();
	}
	function ManageAttachmentPaths(): void
	{
		Actions\Admin\Attachments::manageAttachmentPaths();
	}
	function TransferAttachments(): void
	{
		Actions\Admin\Attachments::transferAttachments();
	}
	/**
	 * End
	 * Actions\Admin\Attachments
	 *
	 * Begin
	 * Actions\Admin\Bans
	 */
	function Ban(): void
	{
		Actions\Admin\Bans::call();
	}
	function updateBanMembers(): void
	{
		Actions\Admin\Bans::updateBanMembers();
	}
	function BanList(): void
	{
		Actions\Admin\Bans::banList();
	}
	function BanEdit(): void
	{
		Actions\Admin\Bans::banEdit();
	}
	function BanBrowseTriggers(): void
	{
		Actions\Admin\Bans::banBrowseTriggers();
	}
	function BanEditTrigger(): void
	{
		Actions\Admin\Bans::banEditTrigger();
	}
	function BanLog(): void
	{
		Actions\Admin\Bans::banLog();
	}
	/**
	 * End
	 * Actions\Admin\Bans
	 *
	 * Begin
	 * Actions\Admin\Boards
	 */
	function ManageBoards(): void
	{
		Actions\Admin\Boards::call();
	}
	function EditBoardSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Boards::editBoardSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Boards
	 *
	 * Begin
	 * Actions\Admin\Calendar
	 */
	function ManageCalendar(): void
	{
		Actions\Admin\Calendar::call();
	}
	function ModifyHolidays(): void
	{
		Actions\Admin\Calendar::modifyHolidays();
	}
	function EditHoliday(): void
	{
		Actions\Admin\Calendar::editHoliday();
	}
	/**
	 * End
	 * Actions\Admin\Calendar
	 *
	 * Begin
	 * Actions\Admin\EndSession
	 */
	function AdminEndSession(): void
	{
		Actions\Admin\EndSession::call();
	}
	/**
	 * End
	 * Actions\Admin\EndSession
	 *
	 * Begin
	 * Actions\Admin\ErrorLog
	 */
	function ViewErrorLog(): void
	{
		Actions\Admin\ErrorLog::call();
	}
	/**
	 * End
	 * Actions\Admin\ErrorLog
	 *
	 * Begin
	 * Actions\Admin\Features
	 */
	function ModifyFeatureSettings(): void
	{
		Actions\Admin\Features::call();
	}
	function ModifyBasicSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Features::modifyBasicSettings($return_config);
	}
	function ModifyBBCSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Features::modifyBBCSettings($return_config);
	}
	function ModifyLayoutSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Features::modifyLayoutSettings($return_config);
	}
	function ModifySignatureSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Features::modifySignatureSettings($return_config);
	}
	function ShowCustomProfiles(): void
	{
		Actions\Admin\Features::showCustomProfiles();
	}
	function EditCustomProfiles(): void
	{
		Actions\Admin\Features::editCustomProfiles();
	}
	function ModifyLikesSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Features::modifyLikesSettings($return_config);
	}
	function ModifyMentionsSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Features::modifyMentionsSettings($return_config);
	}
	function ModifyAlertsSettings(): void
	{
		Actions\Admin\Features::modifyAlertsSettings();
	}
	/**
	 * End
	 * Actions\Admin\Features
	 *
	 * Begin
	 * Actions\Admin\Find
	 */
	function AdminSearch(): void
	{
		Actions\Admin\Find::call();
	}
	/**
	 * End
	 * Actions\Admin\Find
	 *
	 * Begin
	 * Actions\Admin\Home
	 */
	function AdminHome(): void
	{
		Actions\Admin\Home::call();
	}
	/**
	 * End
	 * Actions\Admin\Home
	 *
	 * Begin
	 * Actions\Admin\Languages
	 */
	function ManageLanguages(): void
	{
		Actions\Admin\Languages::call();
	}
	function ModifyLanguages(): void
	{
		Actions\Admin\Languages::modifyLanguages();
	}
	function AddLanguage(): void
	{
		Actions\Admin\Languages::addLanguage();
	}
	function ModifyLanguageSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Languages::modifyLanguageSettings($return_config);
	}
	function DownloadLanguage(): void
	{
		Actions\Admin\Languages::downloadLanguage();
	}
	function ModifyLanguage(): void
	{
		Actions\Admin\Languages::modifyLanguage();
	}
	/**
	 * End
	 * Actions\Admin\Languages
	 *
	 * Begin
	 * Actions\Admin\Logs
	 */
	function AdminLogs(bool $return_config = false): ?array
	{
		return Actions\Admin\Logs::adminLogs($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Logs
	 *
	 * Begin
	 * Actions\Admin\Mail
	 */
	function ManageMail(): void
	{
		Actions\Admin\Mail::call();
	}
	function timeSince(int $time_diff): string
	{
		return Actions\Admin\Mail::timeSince($time_diff);
	}
	function BrowseMailQueue(): void
	{
		Actions\Admin\Mail::browseMailQueue();
	}
	function ClearMailQueue(): void
	{
		Actions\Admin\Mail::clearMailQueue();
	}
	function ModifyMailSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Mail::modifyMailSettings($return_config);
	}
	function TestMailSend(): void
	{
		Actions\Admin\Mail::testMailSend();
	}
	/**
	 * End
	 * Actions\Admin\Mail
	 *
	 * Begin
	 * Actions\Admin\Maintenance
	 */
	function ManageMaintenance(): void
	{
		Actions\Admin\Maintenance::call();
	}
	function getIntegrationHooksData(
		int $start,
		int $per_page,
		string $sort,
		array $filtered_hooks,
		string $normalized_boarddir,
		string $normalized_sourcedir,
	): array {
		return Actions\Admin\Maintenance::getIntegrationHooksData(
			$start,
			$per_page,
			$sort,
			$filtered_hooks,
			$normalized_boarddir,
			$normalized_sourcedir,
		);
	}
	function reattributePosts(
		int $memID,
		?string $email = null,
		?string $membername = null,
		bool $post_count = false,
	): array {
		return Actions\Admin\Maintenance::reattributePosts($memID, $email, $membername, $post_count);
	}
	function MaintainRoutine(): void
	{
		Actions\Admin\Maintenance::maintainRoutine();
	}
	function MaintainDatabase(): void
	{
		Actions\Admin\Maintenance::maintainDatabase();
	}
	function MaintainMembers(): void
	{
		Actions\Admin\Maintenance::maintainMembers();
	}
	function MaintainTopics(): void
	{
		Actions\Admin\Maintenance::maintainTopics();
	}
	function VersionDetail(): void
	{
		Actions\Admin\Maintenance::versionDetail();
	}
	function MaintainFindFixErrors(): void
	{
		Actions\Admin\Maintenance::maintainFindFixErrors();
	}
	function AdminBoardRecount(): void
	{
		Actions\Admin\Maintenance::adminBoardRecount();
	}
	function RebuildSettingsFile(): void
	{
		Actions\Admin\Maintenance::rebuildSettingsFile();
	}
	function MaintainEmptyUnimportantLogs(): void
	{
		Actions\Admin\Maintenance::maintainEmptyUnimportantLogs();
	}
	function MaintainCleanCache(): void
	{
		Actions\Admin\Maintenance::maintainCleanCache();
	}
	function OptimizeTables(): void
	{
		Actions\Admin\Maintenance::optimizeTables();
	}
	function ConvertEntities(): void
	{
		Actions\Admin\Maintenance::convertEntities();
	}
	function ConvertMsgBody(): void
	{
		Actions\Admin\Maintenance::convertMsgBody();
	}
	function MaintainReattributePosts(): void
	{
		Actions\Admin\Maintenance::maintainReattributePosts();
	}
	function MaintainPurgeInactiveMembers(): void
	{
		Actions\Admin\Maintenance::maintainPurgeInactiveMembers();
	}
	function MaintainRecountPosts(): void
	{
		Actions\Admin\Maintenance::maintainRecountPosts();
	}
	function MaintainMassMoveTopics(): void
	{
		Actions\Admin\Maintenance::maintainMassMoveTopics();
	}
	function MaintainRemoveOldPosts(): void
	{
		Actions\Admin\Maintenance::maintainRemoveOldPosts();
	}
	function MaintainRemoveOldDrafts(): void
	{
		Actions\Admin\Maintenance::maintainRemoveOldDrafts();
	}
	/**
	 * End
	 * Actions\Admin\Maintainence
	 *
	 * Begin
	 * Actions\Admin\Membergroups
	 */
	function ModifyMembergroups(): void
	{
		Actions\Admin\Membergroups::call();
	}
	function AddMemberGroup(): void
	{
		Actions\Admin\Membergroups::AddMembergroup();
	}
	function DeleteMembergroup(): void
	{
		Actions\Admin\Membergroups::DeleteMembergroup();
	}
	function EditMembergroup(): void
	{
		Actions\Admin\Membergroups::EditMembergroup();
	}
	function MembergroupIndex(): void
	{
		Actions\Admin\Membergroups::MembergroupIndex();
	}
	function ModifyMembergroupsettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Membergroups::ModifyMembergroupsettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Membergoups
	 *
	 * Begin
	 * Actions\Admin\Members
	 */
	function ViewMembers(): void
	{
		Actions\Admin\Members::call();
	}
	function ViewMemberlist(): void
	{
		Actions\Admin\Members::viewMemberlist();
	}
	function AdminApprove(): void
	{
		Actions\Admin\Members::adminApprove();
	}
	function MembersAwaitingActivation(): void
	{
		Actions\Admin\Members::membersAwaitingActivation();
	}
	function SearchMembers(): void
	{
		Actions\Admin\Members::searchMembers();
	}
	/**
	 * End
	 * Actions\Admin\Members
	 *
	 * Begin
	 * Actions\Admin\Mods
	 */
	function ModifyModSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Mods::modifyModSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Mods
	 *
	 * Begin
	 * Actions\Admin\News
	 */
	function ManageNews(): void
	{
		Actions\Admin\News::call();
	}
	function prepareMailingForPreview(): void
	{
		Actions\Admin\News::prepareMailingForPreview();
	}
	function EditNews(): void
	{
		Actions\Admin\News::editNews();
	}
	function SelectMailingMembers(): void
	{
		Actions\Admin\News::selectMailingMembers();
	}
	function ComposeMailing(): void
	{
		Actions\Admin\News::composeMailing();
	}
	function SendMailing(): void
	{
		Actions\Admin\News::sendMailing();
	}
	function ModifyNewsSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\News::modifyNewsSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\News
	 *
	 * Begin
	 * Actions\Admin\Permissions
	 */
	function ModifyPermissions(): void
	{
		Actions\Admin\Permissions::call();
	}
	function getPermissions(): array
	{
		return Actions\Admin\Permissions::getPermissions();
	}
	function setPermissionLevel(string $level, int $group, string|int $profile = 'null'): void
	{
		Actions\Admin\Permissions::setPermissionLevel($level, $group, $profile);
	}
	function init_inline_permissions(array $permissions, array $excluded_groups = []): void
	{
		Actions\Admin\Permissions::init_inline_permissions($permissions, $excluded_groups);
	}
	function theme_inline_permissions(string $permission): void
	{
		Actions\Admin\Permissions::theme_inline_permissions($permission);
	}
	function save_inline_permissions(array $permissions): void
	{
		Actions\Admin\Permissions::save_inline_permissions($permissions);
	}
	function loadPermissionProfiles(): void
	{
		Actions\Admin\Permissions::loadPermissionProfiles();
	}
	function updateChildPermissions(int|array|null $parents = null, ?int $profile = null)
	{
		return Actions\Admin\Permissions::updateChildPermissions($parents, $profile);
	}
	function loadIllegalPermissions(): array
	{
		return Actions\Admin\Permissions::loadIllegalPermissions();
	}
	function buildHidden(): void
	{
		Actions\Admin\Permissions::buildHidden();
	}
	function PermissionIndex(): void
	{
		Actions\Admin\Permissions::permissionIndex();
	}
	function PermissionsByBoard(): void
	{
		Actions\Admin\Permissions::permissionByBoard();
	}
	function ModifyMembergroup(): void
	{
		Actions\Admin\Permissions::modifyMembergroup();
	}
	function ModifyMembergroup2(): void
	{
		Actions\Admin\Permissions::modifyMembergroup2();
	}
	function SetQuickGroups(): void
	{
		Actions\Admin\Permissions::setQuickGroups();
	}
	function ModifyPostModeration(): void
	{
		Actions\Admin\Permissions::modifyPostModeration();
	}
	function EditPermissionProfiles(): void
	{
		Actions\Admin\Permissions::editPermissionProfiles();
	}
	function GeneralPermissionSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Permissions::generalPermissionSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Permissions
	 *
	 * Begin
	 * Actions\Admin\Post
	 */
	function ManagePostSettings(): void
	{
		Actions\Admin\Posts::call();
	}
	function ModifyPostSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Posts::modifyPostSettings($return_config);
	}
	function ModifyTopicSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Posts::modifyTopicSettings($return_config);
	}
	function ModifyDraftSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Posts::modifyDraftSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Posts
	 *
	 * Begin
	 * Actions\Admin\Registration
	 */
	function RegCenter(): void
	{
		Actions\Admin\Registration::call();
	}
	function AdminRegister(): void
	{
		Actions\Admin\Registration::adminRegister();
	}
	function EditAgreement(): void
	{
		Actions\Admin\Registration::editAgreement();
	}
	function EditPrivacyPolicy(): void
	{
		Actions\Admin\Registration::editPrivacyPolicy();
	}
	function SetReserved(): void
	{
		Actions\Admin\Registration::setReserved();
	}
	function ModifyRegistrationSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Registration::modifyRegistrationSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Registration
	 *
	 * Begin
	 * Actions\Admin\RepairBoards
	 */
	function RepairBoards(): void
	{
		Actions\Admin\RepairBoards::call();
	}
	/**
	 * End
	 * Actions\Admin\RepairBoards
	 *
	 * Begin
	 * Actions\Admin\Reports
	 */
	function ReportsMain(): void
	{
		Actions\Admin\Reports::call();
	}
	function BoardReport(): void
	{
		Actions\Admin\Reports::boardReport();
	}
	function BoardPermissionsReport(): void
	{
		Actions\Admin\Reports::boardPermissionsReport();
	}
	function MemberGroupsReport(): void
	{
		Actions\Admin\Reports::memberGroupsReport();
	}
	function GroupPermissionsReport(): void
	{
		Actions\Admin\Reports::groupPermissionsReport();
	}
	function StaffReport(): void
	{
		Actions\Admin\Reports::staffReport();
	}
	/**
	 * End
	 * Actions\Admin\Reports
	 *
	 * Begin
	 * Actions\Admin\Search
	 */
	function ManageSearch(): void
	{
		Actions\Admin\Search::call();
	}
	function EditWeights(): void
	{
		Actions\Admin\Search::editWeights();
	}
	function EditSearchMethod(): void
	{
		Actions\Admin\Search::editSearchMethod();
	}
	function CreateMessageIndex(): void
	{
		Actions\Admin\Search::createMessageIndex();
	}
	/**
	 * End
	 * Actions\Admin\Search
	 *
	 * Begin
	 * Actions\Admin\SearchEngines
	 */
	function SearchEngines(): void
	{
		Actions\Admin\SearchEngines::call();
	}
	function consolidateSpiderStats(): void
	{
		Actions\Admin\SearchEngines::consolidateSpiderStats();
	}
	function recacheSpiderNames(): void
	{
		Actions\Admin\SearchEngines::recacheSpiderNames();
	}
	function SpiderStats(): void
	{
		Actions\Admin\SearchEngines::spiderStats();
	}
	function SpiderLogs(): void
	{
		Actions\Admin\SearchEngines::spiderLogs();
	}
	function ViewSpiders(): void
	{
		Actions\Admin\SearchEngines::viewSpiders();
	}
	function ManageSearchEngineSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\SearchEngines::manageSearchEngineSettings($return_config);
	}
	function EditSpider(): void
	{
		Actions\Admin\SearchEngines::editSpider();
	}
	/**
	 * End
	 * Actions\Admin\SearchEngine
	 *
	 * Begin
	 * Actions\Admin\Server
	 */
	function ModifySettings(): void
	{
		Actions\Admin\Server::call();
	}
	function getLoadAverageDisabled(): bool
	{
		return Actions\Admin\Server::getLoadAverageDisabled();
	}
	function prepareServerSettingsContext(&$config_vars)
	{
		Actions\Admin\Server::prepareServerSettingsContext($config_vars);
	}
	function checkSettingsFileWriteSafe(): bool
	{
		return Actions\Admin\Server::checkSettingsFileWriteSafe();
	}
	function ModifyGeneralSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Server::modifyGeneralSettings($return_config);
	}
	function ModifyDatabaseSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Server::modifyDatabaseSettings($return_config);
	}
	function ModifyCookieSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Server::modifyCookieSettings($return_config);
	}
	function ModifyGeneralSecuritySettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Server::modifyGeneralSecuritySettings($return_config);
	}
	function ModifyCacheSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Server::modifyCacheSettings($return_config);
	}
	function ModifyExportSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Server::modifyExportSettings($return_config);
	}
	function ModifyLoadBalancingSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Server::modifyLoadBalancingSettings($return_config);
	}
	function ShowPHPinfoSettings(): void
	{
		Actions\Admin\Server::showPHPinfoSettings();
	}
	/**
	 * End
	 * Actions\Admin\Server
	 *
	 * Begin
	 * Actions\Admin\Smileys
	 */
	function ManageSmileys(): void
	{
		Actions\Admin\Smileys::call();
	}
	function AddSmiley(): void
	{
		Actions\Admin\Smileys::addSmiley();
	}
	function EditSmileys(): void
	{
		Actions\Admin\Smileys::editSmileys();
	}
	function EditSmileyOrder(): void
	{
		Actions\Admin\Smileys::editSmileyOrder();
	}
	function InstallSmileySet(): void
	{
		Actions\Admin\Smileys::installSmileySet();
	}
	function EditMessageIcons(): void
	{
		Actions\Admin\Smileys::editMessageIcons();
	}
	/**
	 * End
	 * Actions\Admin\Smileys
	 *
	 * Begin
	 * Actions\Admin\Subscriptions
	 */
	function ManagePaidSubscriptions(): void
	{
		Actions\Admin\Subscriptions::call();
	}
	function loadSubscriptions(): array
	{
		return Actions\Admin\Subscriptions::getSubs();
	}
	function addSubscription(
		int $id_subscribe,
		int $id_member,
		int|string $renewal = 0,
		int $forceStartTime = 0,
		int $forceEndTime = 0
	): void {
		Actions\Admin\Subscriptions::add($id_subscribe, $id_member, $renewal, $forceStartTime, $forceEndTime);
	}
	function removeSubscription(int $id_subscribe, int $id_member, bool $delete = false): void
	{
		Actions\Admin\Subscriptions::remove($id_subscribe, $id_member, $delete);
	}
	function reapplySubscriptions(array $users): void
	{
		Actions\Admin\Subscriptions::reapply($users);
	}
	function loadPaymentGateways(): array
	{
		return Actions\Admin\Subscriptions::loadPaymentGateways();
	}
	function ViewSubscriptions(): void
	{
		Actions\Admin\Subscriptions::viewSubscriptions();
	}
	function ViewSubscribedUsers(): void
	{
		Actions\Admin\Subscriptions::viewSubscribedUsers();
	}
	function ModifySubscription(): void
	{
		Actions\Admin\Subscriptions::modifySubscription();
	}
	function ModifyUserSubscription(): void
	{
		Actions\Admin\Subscriptions::modifyUserSubscription();
	}
	function ModifySubscriptionSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Subscriptions::modifySubscriptionSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Subscriptions
	 *
	 * Begin
	 * Actions\Admin\Task
	 */
	function ManageScheduledTasks(): void
	{
		Actions\Admin\Tasks::call();
	}
	function ScheduledTasks(): void
	{
		Actions\Admin\Tasks::scheduledTasks();
	}
	function EditTask(): void
	{
		Actions\Admin\Tasks::editTask();
	}
	function TaskLog(): void
	{
		Actions\Admin\Tasks::taskLog();
	}
	function TaskSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Tasks::taskSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Tasks
	 *
	 * Begin
	 * Actions\Admin\Themes
	 */
	function ThemesMain(): void
	{
		Actions\Admin\Themes::call();
	}
	function ThemeAdmin(): void
	{
		Actions\Admin\Themes::themeAdmin();
	}
	function ThemeList(): void
	{
		Actions\Admin\Themes::themeList();
	}
	function SetThemeOptions(): void
	{
		Actions\Admin\Themes::setThemeOptions();
	}
	function RemoveTheme(): void
	{
		Actions\Admin\Themes::removeTheme();
	}
	function EnableTheme(): void
	{
		Actions\Admin\Themes::enableTheme();
	}
	function ThemeInstall(): void
	{
		Actions\Admin\Themes::themeInstall();
	}
	function EditTheme(): void
	{
		Actions\Admin\Themes::editTheme();
	}
	function CopyTemplate(): void
	{
		Actions\Admin\Themes::copyTemplate();
	}
	/**
	 * End
	 * Actions\Admin\Themes
	 *
	 * Begin
	 * Actions\Admin\Warnings
	 */
	function ModifyWarningSettings(bool $return_config = false): ?array
	{
		return Actions\Admin\Warnings::modifyWarningSettings($return_config);
	}
	/**
	 * End
	 * Actions\Admin\Warnings
	 *
	 * Begin
	 * Actions\Moderation\EndSession
	 */
	function ModEndSession(): void
	{
		Actions\Moderation\EndSession::call();
	}
	/**
	 * End
	 * Actions\Moderation\EndSession
	 *
	 * Begin
	 * Actions\Moderation\Home
	 */
	function ModerationHome(): void
	{
		Actions\Moderation\Home::call();
	}
	/**
	 * End
	 * Actions\Moderation\Home
	 *
	 * Begin
	 * Actions\Moderation\Logs
	 */
	function ViewModlog(): void
	{
		Actions\Moderation\Logs::call();
	}
	/**
	 * End
	 * Actions\Moderation\Logs
	 *
	 * Begin
	 * Actions\Moderation\Main
	 */
	function checkAccessPermissions(): void
	{
		Actions\Moderation\Main::checkAccessPermissions();
	}
	function ModerationMail(bool $dont_call = false): void
	{
		Actions\Moderation\Main::ModerationMain($dont_call);
	}
	/**
	 * End
	 * Actions\Moderation\Main
	 *
	 * Begin
	 * Actions\Moderation\Posts
	 */
	function PostModerationMain(): void
	{
		Actions\Moderation\Posts::call();
	}
	function approveAllData(): void
	{
		Actions\Moderation\Posts::approveAllData();
	}
	function UnapprovedPosts(): void
	{
		Actions\Moderation\Posts::unapprovedPosts();
	}
	function UnapprovedAttachments(): void
	{
		Actions\Moderation\Posts::unapprovedAttachments();
	}
	function ApproveMessage(): void
	{
		Actions\Moderation\Posts::approveMessage();
	}
	/**
	 * End
	 * Actions\Moderation\Posts
	 *
	 * Begin
	 * Actions\Moderation\ReportedContent
	 */
	function ReportedContent(): void
	{
		Actions\Moderation\ReportedContent::call();
	}
	function recountOpenReports(string $type): int
	{
		return Actions\Moderation\ReportedContent::recountOpenReports($type);
	}
	function ShowReports(): void
	{
		Actions\Moderation\ReportedContent::showReports();
	}
	function ShowClosedReports(): void
	{
		Actions\Moderation\ReportedContent::showClosedReports();
	}
	function ReportDetails(): void
	{
		Actions\Moderation\ReportedContent::reportDetails();
	}
	function HandleReport(): void
	{
		Actions\Moderation\ReportedContent::handleReport();
	}
	function HandleComment(): void
	{
		Actions\Moderation\ReportedContent::handleComment();
	}
	function EditComment(): void
	{
		Actions\Moderation\ReportedContent::editComment();
	}
	/**
	 * End
	 * Actions\Moderation\ReportedContent
	 *
	 * Begin
	 * Actions\Moderation\ShowNotice
	 */
	function ShowNotice(): void
	{
		Actions\Moderation\ShowNotice::call();
	}
	/**
	 * End
	 * Actions\Moderation\ShowNotice
	 *
	 * Begin
	 * Actions\Moderation\Warnings
	 */
	function ViewWarnings(): void
	{
		Actions\Moderation\Warnings::call();
	}
	function ViewWarningLog(): void
	{
		Actions\Moderation\Warnings::ViewWarningLog();
	}
	function ViewWarningTemplates(): void
	{
		Actions\Moderation\Warnings::ViewWarningTemplates();
	}
	function ModifyWarningTemplate(): void
	{
		Actions\Moderation\Warnings::ModifyWarningTemplate();
	}
	/**
	 * End
	 * Actions\Moderation\Warnings
	 *
	 * Begin
	 * Actions\Moderation\WatchedUsers
	 */
	function ViewWatchedUsers(): void
	{
		Actions\Moderation\WatchedUsers::call();
	}
	/**
	 * End
	 * Actions\Moderation\WatchedUsers
	 *
	 * Begin
	 * Actions\Profile\Account
	 */
	function account(): void
	{
		Actions\Profile\Account::call();
	}
	/**
	 * End
	 * Actions\Profile\Account
	 *
	 * Begin
	 * Actions\Profile\Activate
	 */
	function activateAccount(): void
	{
		Actions\Profile\Activate::call();
	}
	/**
	 * End
	 * Actions\Profile\Activate
	 *
	 * Begin
	 * Actions\Profile\AlertsPopup
	 */
	function alerts_popup(): void
	{
		Actions\Profile\AlertsPopup::call();
	}
	/**
	 * End
	 * Actions\Profile\AlertsPopup
	 *
	 * Begin
	 * Actions\Profile\BuddyIgnoreLists
	 */
	function editBuddyIgnoreLists(): void
	{
		Actions\Profile\BuddyIgnoreLists::call();
	}
	function editBuddies(int $memID): void
	{
		Actions\Profile\BuddyIgnoreLists::editBuddies($memID);
	}
	function editIgnoreList(int $memID): void
	{
		Actions\Profile\BuddyIgnoreLists::editIgnoreList($memID);
	}
	/**
	 * End
	 * Actions\Profile\BuddyIgnoreLists
	 *
	 * Begin
	 * Actions\Profile\Delete
	 */
	function deleteAccount(): void
	{
		Actions\Profile\Delete::call();
	}
	function deleteAccount2(int $memID): void
	{
		Actions\Profile\Delete::deleteAccount2($memID);
	}
	/**
	 * End
	 * Actions\Profile\Delete
	 *
	 * Begin
	 * Actions\Profile\Export
	 */
	function export_profile_data(): void
	{
		Actions\Profile\Export::call();
	}
	function create_export_dir(string $fallback = ''): string|bool
	{
		return Actions\Profile\Export::createDir($fallback);
	}
	function get_export_formats(): array
	{
		return Actions\Profile\Export::getFormats();
	}
	/**
	 * End
	 * Actions\Profile\Export
	 *
	 * Begin
	 * Actions\Profile\ExportAttachment
	 */
	function export_attachment(): void
	{
		Actions\Profile\ExportAttachment::call();
	}
	/**
	 * End
	 * Actions\Profile\ExportAttachment
	 *
	 * Begin
	 * Actions\Profile\ExportDownload
	 */
	function download_export_file(): void
	{
		Actions\Profile\ExportDownload::call();
	}
	/**
	 * End
	 * Actions\Profile\ExportDownload
	 *
	 * Begin
	 * Actions\Profile\ForumProfile
	 */
	function forumProfile(): void
	{
		Actions\Profile\ForumProfile::call();
	}
	/**
	 * End
	 * Actions\Profile\ForumProfile
	 *
	 * Begin
	 * Actions\Profile\GroupMembership
	 */
	function groupMembership(): void
	{
		Actions\Profile\GroupMembership::call();
	}
	function groupMembership2(int $memID): string
	{
		return Actions\Profile\GroupMembership::groupMembership2($memID);
	}
	/**
	 * End
	 * Actions\Profile\GroupMembership
	 *
	 * Begin
	 * Actions\Profile\IgnoreBoards
	 */
	function ignoreboards(): void
	{
		Actions\Profile\IgnoreBoards::call();
	}
	/**
	 * End
	 * Actions\Profile\IgnoreBoards
	 *
	 * Begin
	 * Actions\Profile\IssueWarning
	 */
	function issueWarning(int $memID): void
	{
		Actions\Profile\IssueWarning::issueWarning($memID);
	}
	/**
	 * End
	 * Actions\Profile\IssueWarning
	 *
	 * Begin
	 * Actions\Profile\Main
	 */
	function ModifyProfile(): void
	{
		Actions\Profile\Main::call();
	}
	/**
	 * End
	 * Actions\Profile\Main
	 *
	 * Begin
	 * Actions\Profile\Notification
	 */
	function notification(): void
	{
		Actions\Profile\Notification::call();
	}
	function alert_configuration(int $memID, bool $defaultSettings = false): void
	{
		Actions\Profile\Notification::alert_configuration($memID, $defaultSettings);
	}
	function alert_markread(int $memID):  void
	{
		Actions\Profile\Notification::alert_markread($memID);
	}
	function alert_notifications_topics(int $memID): void
	{
		Actions\Profile\Notification::alert_notifications_topics($memID);
	}
	function alert_notifications_boards(int $memID): void
	{
		Actions\Profile\Notification::alert_notifications_boards($memID);
	}
	function makeNotificationChanges(int $memID): void
	{
		Actions\Profile\Notification::makeNotificationChanges($memID);
	}
	/**
	 * End
	 * Actions\Profile\Notification
	 *
	 * Begin
	 * Actions\Profile\PaidSubs
	 */
	function subscriptions(): void
	{
		Actions\Profile\PaidSubs::call();
	}
	/**
	 * End
	 * Actions\Profile\PaidSubs
	 *
	 * Begin
	 * Actions\Profile\Popup
	 */
	function profile_popup(): void
	{
		Actions\Profile\Popup::call();
	}
	/**
	 * End
	 * Actions\Profile\Popup
	 *
	 * Begin
	 * Actions\Profile\ShowAlerts
	 */
	function showAlerts(int $memID): void
	{
		Actions\Profile\ShowAlerts::showAlerts($memID);
	}
	/**
	 * End
	 * Actions\Profile\ShowAlerts
	 *
	 * Begin
	 * Actions\Profile\ShowPermissions
	 */
	function showPermissions(int $memID): void
	{
		Actions\Profile\ShowPermissions::showPermissions($memID);
	}
	/**
	 * End
	 * Actions\Profile\ShowPermissions
	 *
	 * Begin
	 * Actions\Profile\ShowPost
	 */
	function showPosts(int $memID): void
	{
		Actions\Profile\ShowPosts::showPosts($memID);
	}
	function showUnwatched(int $memID): void
	{
		Actions\Profile\ShowPosts::showUnwatched($memID);
	}
	function showAttachments(int $memID): void
	{
		Actions\Profile\ShowPosts::showAttachments($memID);
	}
	/**
	 * End
	 * Actions\Profile\ShowPosts
	 *
	 * Begin
	 * Actions\Profile\StatPanel
	 */
	function statPanel(int $memID): void
	{
		Actions\Profile\StatPanel::statPanel($memID);
	}
	/**
	 * End
	 * Actions\Profile\StatPanel
	 *
	 * Begin
	 * Actions\Profile\Summary
	 */
	function summary(int $memID): void
	{
		Actions\Profile\Summary::summary($memID);
	}
	/**
	 * End
	 * Actions\Profile\Summary
	 *
	 * Begin
	 * Actions\Profile\TFADisable
	 */
	function tfadisable(): void
	{
		Actions\Profile\TFADisable::call();
	}
	/**
	 * End
	 * Actions\Profile\TFDADisable
	 *
	 * Begin
	 * Actions\Profile\TFASetup
	 */
	function tfasetup(): void
	{
		Actions\Profile\TFASetup::call();
	}
	/**
	 * End
	 * Actions\Profile\TFASetup
	 *
	 * Begin
	 * Actions\Profile\ThemeOptions
	 */
	function theme(): void
	{
		Actions\Profile\ThemeOptions::call();
	}
	/**
	 * End
	 * Actions\Profile\ThemeOptions
	 *
	 * Begin
	 * Actions\Profile\Tracking
	 */
	function tracking(): void
	{
		Actions\Profile\Tracking::call();
	}
	function trackActivity(int $memID): void
	{
		Actions\Profile\Tracking::trackActivity($memID);
	}
	function trackEdits(int $memID): void
	{
		Actions\Profile\Tracking::trackEdits($memID);
	}
	function trackGroupReq(int $memID): void
	{
		Actions\Profile\Tracking::trackGroupReq($memID);
	}
	function TrackLogins(int $memID): void
	{
		Actions\Profile\Tracking::trackLogins($memID);
	}
	/**
	 * End
	 * Actions\Profile\Tracking
	 *
	 * Begin
	 * Actions\Profile\ViewWarning
	 */
	function viewWarning(int $memID): void
	{
		Actions\Profile\ViewWarning::viewWarning($memID);
	}
	/**
	 * End
	 * Actions\Profile\ViewWarning
	 *
	 * Begin
	 * Actions\Agreement
	 */
	function Agreement(): void
	{
		Actions\Agreement::call();
	}
	function canRequireAgreement(): bool
	{
		return Actions\Agreement::canRequireAgreement();
	}
	function canRequirePrivacyPolicy(): bool
	{
		return Actions\Agreement::canRequirePrivacyPolicy();
	}
	/**
	 * End
	 * Actions\Agreement
	 *
	 * Begin
	 * Actions\AgreementAccept
	 */
	function AcceptAgreement(): void
	{
		Actions\AgreementAccept::call();
	}
	/**
	 * End
	 * Actions\AgreementAccept
	 *
	 * Begin
	 * Actions\Announce
	 */
	function AnnounceTopic(): void
	{
		Actions\Announce::call();
	}
	function AnnouncementSelectMembergroup(): void
	{
		Actions\Announce::selectGroup();
	}
	function AnnouncementSend(): void
	{
		Actions\Announce::announcementSend();
	}
	/**
	 * End
	 * Actions\Announce
	 *
	 * Begin
	 * Actions\AttachmentApprove
	 */
	function ApproveAttach(): void
	{
		Actions\AttachmentApprove::call();
	}
	/**
	 * End
	 * Actions\AttachmentApprove
	 *
	 * Begin
	 * Actions\AttachmentDownload
	 */
	function showAttachment(): void
	{
		Actions\AttachmentDownload::call();
	}
	/**
	 * End
	 * Actions\AttachementDownload
	 *
	 * Begin
	 * Actions\AutoSuggest
	 */
	function AutoSuggestHandler(?string $suggest_type = null): ?bool
	{
		return Actions\AutoSuggest::AutoSuggestHandler($suggest_type);
	}
	function AutoSuggest_Search_Member(): void
	{
		Actions\AutoSuggest::AutoSuggest_Search_Member();
	}
	function AutoSuggest_Search_MemberGroups(): void
	{
		Actions\AutoSuggest::AutoSuggest_Search_MemberGroups();
	}
	function AutoSuggest_Search_SMFVersions(): void
	{
		Actions\AutoSuggest::AutoSuggest_Search_SMFVersions();
	}
	/**
	 * End
	 * Actions\AutoSuggest
	 *
	 * Begin
	 * Actions\BoardIndex
	 */
	function BoardIndex(): Actions\BoardIndex
	{
		return Actions\BoardIndex::load();
	}
	function call(): void
	{
		Actions\BoardIndex::call();
	}
	function getBoardIndex(array $board_index_options): array
	{
		return Actions\BoardIndex::get($board_index_options);
	}
	/**
	 * End
	 * Actions\BoardIndex
	 *
	 * Begin
	 * Actions\BuddyListToggle
	 */
	function BuddyListToggle(): void
	{
		Actions\BuddyListToggle::call();
	}
	/**
	 * End
	 * Actions\BuddyListToggle
	 *
	 * Begin
	 * Actions\Calendar
	 */
	function CalendarMain(): void
	{
		Actions\Calendar::call();
	}
	function iCalDownload(): void
	{
		Actions\Calendar::iCalDownload();
	}
	function CalendarPost(): void
	{
		Actions\Calendar::CalendarPost();
	}
	function getBirthdayRange(string $low_date, string $high_date): array
	{
		return Actions\Calendar::getBirthdayRange($low_date, $high_date);
	}
	function getEventRange(string $low_date, string $high_date, bool $use_permissions = true): array
	{
		return Actions\Calendar::getEventRange($low_date, $high_date, $use_permissions);
	}
	function getHolidayRange(string $low_date, string $high_date): array
	{
		return Actions\Calendar::getHolidayRange($low_date, $high_date);
	}
	function canLinkEvent(): void
	{
		Actions\Calendar::canLinkEvent();
	}
	function getTodayInfo(): array
	{
		return Actions\Calendar::getTodayInfo();
	}
	function getCalendarGrid(
		string $selected_date,
		array $calendarOptions,
		bool $is_previous = false,
		bool $has_picker = true
	): array {
		return Actions\Calendar::getCalendarGrid(
			$selected_date,
			$calendarOptions,
			$is_previous,
			$has_picker
		);
	}
	function getCalendarWeek(string $selected_date, array $calendarOptions): array
	{
		return Actions\Calendar::getCalendarWeek($selected_date, $calendarOptions);
	}
	function getCalendarList(string $start_date, string $end_date, array $calendarOptions): array
	{
		return Actions\Calendar::getCalendarList($start_date, $end_date, $calendarOptions);
	}
	function loadDatePicker(string $selector = 'input.date_input', string $date_format = ''): void
	{
		Actions\Calendar::loadDatePicker($selector, $date_format);
	}
	function loadTimePicker(string $selector = 'input.time_input', string $time_format = ''): void
	{
		Actions\Calendar::loadTimePicker($selector, $time_format);
	}
	function loadDatePair(string $container, string $date_class = '', string $time_class = ''): void
	{
		Actions\Calendar::loadDatePair($container, $date_class, $time_class);
	}
	function cache_getOffsetIndependentEvents(array $eventOptions): array
	{
		return Actions\Calendar::cache_getOffsetIndependentEvents($eventOptions);
	}
	function cache_getRecentEvents(array $eventOptions): array
	{
		return Actions\Calendar::cache_getRecentEvents($eventOptions);
	}
	function validateEventPost(): void
	{
		Actions\Calendar::validateEventPost();
	}
	function getEventPoster(int $event_id): int|bool
	{
		return Actions\Calendar::getEventPoster($event_id);
	}
	function removeHolidays(array $holiday_ids): void
	{
		Actions\Calendar::removeHolidays($holiday_ids);
	}
	function convertDateToEnglish(string $date): string
	{
		return Actions\Calendar::convertDateToEnglish($date);
	}
	/**
	 * End
	 * Actions\Calendar
	 *
	 * Begin
	 * Actions\CoppaForm
	 */
	function CoppaForm(): void
	{
		Actions\CoppaForm::call();
	}
	/**
	 * End
	 * Actions\CoppaForm
	 *
	 * Begin
	 * Actions\Credits
	 */
	function Credits(bool $in_admin = false): void
	{
		Actions\Credits::call($in_admin);
	}
	/**
	 * End
	 * Actions\Credits
	 *
	 * Begin
	 * Actions\Display
	 */
	function Display(): void
	{
		Actions\Display::call();
	}
	/**
	 * End
	 * Actions\Display
	 *
	 * Begin
	 * Actions\DisplayAdminFile
	 */
	function DisplayAdminFile(): void
	{
		Actions\DisplayAdminFile::call();
	}
	/**
	 * End
	 * Actions\DisplayAdminFile
	 *
	 * Begin
	 * Actions\Feed
	 */
	function ShowXmlFeed(): void
	{
		Actions\Feed::call();
	}
	function buildXmlFeed(string $format, array $data, array $metadata, string $subaction): array
	{
		return Actions\Feed::build($format, $data, $metadata, $subaction);
	}
	function cdata_parse(string $data, string $ns = '', bool $force = false): string
	{
		return Actions\Feed::cdataParse($data, $ns, $force);
	}
	/**
	 * End
	 * Actions\Feed
	 *
	 * Begin
	 * Actions\FindMember
	 * @deprecated
	 */
	function JSMembers(): void
	{
		Actions\FindMember::call();
	}
	/**
	 * End
	 * Actions\FindMember
	 *
	 * Begin
	 * Actions\Groups
	 */
	function Groups(): void
	{
		Actions\Groups::call();
	}
	function listMembergroupMembers_Href(array &$members, int $membergroup, int $limit = null): bool
	{
		return Actions\Groups::listMembergroupMembers_Href($members, $membergroup, $limit);
	}
	function GroupList(): void
	{
		Actions\Groups::GroupList();
	}
	function MembergroupMembers(): void
	{
		Actions\Groups::MembergroupMembers();
	}
	function GroupRequests(): void
	{
		Actions\Groups::GroupRequests();
	}
	/**
	 * End
	 * Actions\Groups
	 *
	 * Begin
	 * Actions\Help
	 */
	function ShowHelp(): void
	{
		Actions\Help::call();
	}
	function HelpIndex(): void
	{
		Actions\Help::HelpIndex();
	}
	/**
	 * End
	 * Actions\Help
	 *
	 * Begin
	 * Actions\HelpAdmin
	 */
	function ShowAdminHelp(): void
	{
		Actions\HelpAdmin::call();
	}
	/**
	 * End
	 * Actions\HelpAdmin
	 *
	 * Begin
	 * Actions\JavaScriptModify
	 */
	function JavaScriptModify(): void
	{
		Actions\JavaScriptModify::call();
	}
	/**
	 * End
	 * Actions\JavaScriptModify
	 *
	 * Begin
	 * Actions\Login
	 */
	function Login(): void
	{
		Actions\Login::call();
	}
	/**
	 * End
	 * Actions\Login
	 *
	 * Begin
	 * Actions\Login2
	 */
	function Login2(): void
	{
		Actions\Login2::call();
	}
	function checkAjax(): void
	{
		Actions\Login2::checkAjax();
	}
	function validatePasswordFlood(
		int $id_member,
		string $member_name,
		bool|string $password_flood_value = false,
		bool $was_correct = false,
		bool $tfa = false
	): void {
		Actions\Login2::validatePasswordFlood(
			$id_member,
			$member_name,
			$password_flood_value,
			$was_correct,
			$tfa
		);
	}
	/**
	 * End
	 * Actions\Login2
	 *
	 * Begin
	 * Actions\LoginTFA
	 */
	function LoginTFA(): void
	{
		Actions\LoginTFA::call();
	}
	/**
	 * End
	 * Actions\LoginTFA
	 *
	 * Begin
	 * Actions\Logout
	 */
	function Logout(): void
	{
		Actions\Logout::call();
	}
	/**
	 * End
	 * Actions\Logout
	 *
	 * Begin
	 * Actions\Memberlist
	 */
	function Memberlist(): void
	{
		Actions\Memberlist::call();
	}
	function MLAll(): void
	{
		Actions\Memberlist::MLAll();
	}
	function MLSearch(): void
	{
		Actions\Memberlist::MLSearch();
	}
	function printMemberListRows($request): void
	{
		Actions\Memberlist::printRows($request);
	}
	function getCustFieldsMList(): array
	{
		return Actions\Memberlist::getCustFields();
	}
	/**
	 * End
	 * Actions\Memberlist
	 *
	 * Begin
	 * Actions\MessageIndex
	 */
	function MessageIndex(): void
	{
		Actions\MessageIndex::call();
	}
	function getBoardList(array $boardListOptions = []): array
	{
		return Actions\MessageIndex::getBoardList($boardListOptions);
	}
	function buildTopicContext(array $row): void
	{
		Actions\MessageIndex::buildTopicContext($row);
	}
	/**
	 * End
	 * Actions\MessageIndex
	 *
	 * Begin
	 * Actions\MsgDelete
	 */
	function DeleteMessage(): void
	{
		Actions\MsgDelete::call();
	}
	/**
	 * End
	 * Actions\MsgDelete
	 *
	 * Begin
	 * Actions\Notify
	 */
	function getNotifyPrefs(int|array $members, string|array $prefs = '', bool $process_defaults = false): array
	{
		return Actions\Notify::getNotifyPrefs($members, $prefs, $process_defaults);
	}
	function setNotifyPrefs(int $memID, array $prefs = [])
	{
		return Actions\Notify::setNotifyPrefs($memID, $prefs);
	}
	function deleteNotifyPrefs(int $memID, array $prefs)
	{
		return Actions\Notify::deleteNotifyPrefs($memID, $prefs);
	}
	function getMemberWithToken(string $type): array
	{
		return Actions\Notify::getMemberWithToken($type);
	}
	function createUnsubscribeToken(int $memID, string $email, string $type = '', int $itemID = 0): string
	{
		return Actions\Notify::createUnsubscribeToken($memID, $email, $type, $itemID);
	}
	/**
	 * End
	 * Actions\Notify
	 *
	 * Begin
	 * Actions\NotifyAnnouncements
	 */
	function AnnouncementsNotify(): void
	{
		Actions\NotifyAnnouncements::call();
	}
	/**
	 * End
	 * Actions\NotifyAnnouncements
	 *
	 * Begin\NotifyBoard
	 */
	function BoardNotify(): void
	{
		Actions\NotifyBoard::call();
	}
	/**
	 * End
	 * Actions\NotifyBoard
	 *
	 * Begin
	 * Actions\NotifyTopic
	 */
	function TopicNotify(): void
	{
		Actions\NotifyTopic::call();
	}
	/**
	 * End
	 * Actions\NotifyTopic
	 *
	 * Begin
	 * Actions\PersonalMessage
	 */
	function MessageMain(): void
	{
		Actions\PersonalMessage::call();
	}
	function MessageFolder(): void
	{
		Actions\PersonalMessage::messageFolder();
	}
	function MessagePopup(): void
	{
		Actions\PersonalMessage::messagePopup();
	}
	function ManageLabels(): void
	{
		Actions\PersonalMessage::manageLabels();
	}
	function ManageRules(): void
	{
		Actions\PersonalMessage::manageRules();
	}
	function MessageActionsApply(): void
	{
		Actions\PersonalMessage::messageActionsApply();
	}
	function MessagePrune()
	{
		Actions\PersonalMessage::messagePrune();
	}
	function MessageKillAll(): void
	{
		Actions\PersonalMessage::messageKillAll();
	}
	function ReportMessage(): void
	{
		Actions\PersonalMessage::messageSearch();
	}
	function MessageSearch(): void
	{
		Actions\PersonalMessage::messageSearch();
	}
	function MessageSearch2(): void
	{
		Actions\PersonalMessage::messageSearch2();
	}
	function MessagePost(): void
	{
		Actions\PersonalMessage::messagePost();
	}
	function MessagePost2(): void
	{
		Actions\PersonalMessage::messagePost2();
	}
	function MessageSettings(): void
	{
		Actions\PersonalMessage::messageSettings();
	}
	function MessageDrafts(): void
	{
		Actions\PersonalMessage::messageDrafts();
	}
	/**
	 * End
	 * Actions\PersonalMessage
	 *
	 * Begin
	 * Actions\Post
	 */
	function Post(): void
	{
		Actions\Post::call();
	}
	/**
	 * End
	 * Actions\Post
	 *
	 * Begin
	 * Actions\Post2
	 */
	function Post2(): void
	{
		Actions\Post2::call();
	}
	/**
	 * End
	 * Actions\Post2
	 *
	 * Begin
	 * Actions\QuickModeration
	 */
	function QuickModeration(): void
	{
		Actions\QuickModeration::call();
	}
	/**
	 * End
	 * Actions\QuickModeration
	 *
	 * Begin
	 * Actions\QuickModerationInTopic
	 */
	function QuickInTopicModeration(): void
	{
		Actions\QuickModerationInTopic::call();
	}
	/**
	 * End
	 * Actions\QuickModerationInTopic
	 *
	 * Begin
	 * Actions\QuoteFast
	 */
	function QuoteFast(): void
	{
		Actions\QuoteFast::call();
	}
	/**
	 * End
	 * Actions\QuoteFast
	 *
	 * Begin
	 * Actions\Recent
	 */
	function RecentPosts(): void
	{
		Actions\Recent::call();
	}
	function getLastPost(): array
	{
		return Actions\Recent::getLastPost();
	}
	/**
	 * End
	 * Actions\Recent
	 *
	 * Begin
	 * Actions\Register
	 */
	function Register(array $reg_errors = []): void
	{
		Actions\Register::register($reg_errors);
	}
	/**
	 * End
	 * Actions\Register
	 *
	 * Begin
	 * Actions\Register2
	 */
	function Register2(): void
	{
		Actions\Register2::call();
	}
	function registerMember(array &$reg_options, bool $return_errors = false): int|array
	{
		return Actions\Register2::registerMember($reg_options, $return_errors);
	}
	/**
	 * End
	 * Actions\Register2
	 *
	 * Begin
	 * Actions\Reminder
	 */
	function RemindMe(): void
	{
		Actions\Reminder::call();
	}
	/**
	 * End
	 * Actions\Reminder
	 *
	 * Begin
	 * Actions\ReportToMod
	 */
	function ReportToModerator(): void
	{
		Actions\ReportToMod::call();
	}
	function ReportToModerator2(): void
	{
		Actions\ReportToMod::ReportToModerator2();
	}
	function reportPost($msg, $reason): void
	{
		Actions\ReportToMod::reportPost($msg, $reason);
	}
	function reportUser($id_member, $reason): void
	{
		Actions\ReportToMod::reportUser($id_member, $reason);
	}
	/**
	 * End
	 * Actions\ReportToMod
	 *
	 * Begin
	 * Actions\RequestMembers
	 */
	function RequestMembers(): void
	{
		Actions\RequestMembers::call();
	}
	/**
	 * End
	 * Actions\RequestMembers
	 *
	 * Begin
	 * Actions\Search
	 */
	function PlushSearch1(): void
	{
		Actions\Search::call();
	}
	/**
	 * End
	 * Actions\Search
	 *
	 * Begin
	 * Actions\Search2
	 */
	function PlushSearch2(): void
	{
		Actions\Search2::call();
	}
	/**
	 * End
	 * Actions\Search2
	 *
	 * Begin
	 * Actions\SendActivation
	 */
	function SendActivation(): void
	{
		Actions\SendActivation::call();
	}
	/**
	 * End
	 * Actions\SendActivation
	 *
	 * Begin
	 * Actions\SmStats
	 */
	function SMStats(): void
	{
		Actions\SmStats::call();
	}
	/**
	 * End
	 * Actions\SmStats
	 *
	 * Begin
	 * Actions\Stats
	 */
	function DisplayStats(): void
	{
		Actions\Stats::call();
	}
	/**
	 * End
	 * Actions\Stats
	 *
	 * Begin
	 * Actions\TopicMerge
	 */
	function MergeTopics(): void
	{
		Actions\TopicMerge::call();
	}
	function MergeIndex(): void
	{
		Actions\TopicMerge::mergeIndex();
	}
	function MergeExecute(array $topics = []): void
	{
		Actions\TopicMerge::mergeExecute($topics);
	}
	function MergeDone(): void
	{
		Actions\TopicMerge::mergeDone();
	}
	/**
	 * End
	 * Actions\TopicMerge
	 *
	 * Begin
	 * Actions\TopicMove
	 */
	function MoveTopic(): void
	{
		Actions\TopicMove::call();
	}
	/**
	 * End
	 * Actions\TopicMove
	 *
	 * Begin
	 * Actions\TopicMove2
	 */
	function MoveTopic2(): void
	{
		Actions\TopicMove2::call();
	}
	function moveTopicConcurrence()
	{
		Actions\TopicMove2::moveTopicConcurrence();
	}
	/**
	 * End
	 * Actions\TopicMove2
	 *
	 * Begin
	 * Actions\TopicPrint
	 */
	function PrintTopic(): void
	{
		Actions\TopicPrint::call();
	}
	/**
	 * End
	 * Actions\TopicPrint
	 *
	 * Begin
	 * Actions\TopicRemove
	 */
	function RemoveTopic2(): void
	{
		Actions\TopicRemove::call();
	}
	function removeDeleteConcurrence(): bool
	{
		return Actions\TopicRemove::removeDeleteConcurrence();
	}
	function RemoveOldTopics2()
	{
		Actions\TopicRemove::old();
	}
	/**
	 * End
	 * Actions\TopicRemove
	 *
	 * Begin
	 * Actions\TopicRestore
	 */
	function RestoreTopic(): void
	{
		Actions\TopicRestore::call();
	}
	/**
	 * End
	 * Actions\TopicRestore
	 *
	 * Begin
	 * Actions\TopicSplit
	 */
	function SplitTopics(): void
	{
		Actions\TopicSplit::call();
	}
	function splitTopic(int $split1_ID_TOPIC, array $splitMessages, string $new_subject): int
	{
		return Actions\TopicSplit::splitTopic($split1_ID_TOPIC, $splitMessages, $new_subject);
	}
	function SplitIndex(): void
	{
		Actions\TopicSplit::splitIndex();
	}
	function SplitExecute(): void
	{
		Actions\TopicSplit::splitExecute();
	}
	function SplitSelectTopics(): void
	{
		Actions\TopicSplit::splitSelectTopics();
	}
	function SplitSelectionExecute(): void
	{
		// todo: fix function name
		Actions\TopicSplit::SplitSelectionExecute();
	}
	/**
	 * End
	 * Actions\TopicSplit
	 *
	 * Begin
	 * Actions\TrackIP
	 */
	function TrackIP(int $memID = 0): void
	{
		Actions\TrackIP::trackIP($memID);
	}
	/**
	 * End
	 * Actions\TrackIP
	 *
	 * Begin
	 * Actions\Unread
	 */
	function UnreadTopics(): void
	{
		Actions\Unread::call();
	}
	/**
	 * End
	 * Actions\Unread
	 *
	 * Begin
	 * Actions\VerificationCode
	 */
	function VerificationCode(): void
	{
		Actions\VerificationCode::call();
	}
	/**
	 * End
	 * Actions\VerificationCode
	 *
	 * Begin
	 * Actions\ViewQUery
	 */
	function ViewQuery(): void
	{
		Actions\ViewQuery::call();
	}
	/**
	 * End
	 * Actions\ViewQUery
	 *
	 * Begin
	 * Actions\Who
	 */
	function Who(): void
	{
		Actions\Who::call();
	}
	function determineActions(string|array $urls, string|bool $preferred_prefix = false): array
	{
		return Actions\Who::determineActions($urls, $preferred_prefix);
	}
	/**
	 * End
	 * Actions\Who
	 *
	 * Begin
	 * Actions\XmlHttp
	 */
	function XMLhttpMain(): void
	{
		Actions\XmlHttp::call();
	}
	function GetJumpTo(): void
	{
		Actions\XmlHttp::GetJumpTo();
	}
	function ListMessageIcons(): void
	{
		Actions\XmlHttp::ListMessageIcons();
	}
	function RetrievePreview(): void
	{
		Actions\XmlHttp::RetrievePreview();
	}
	/**
	 * End
	 * Actions\XmlHttp
	 * End Actions\*
	 *
	 * Begin
	 * Cache\CacheApi
	 */
	function loadCacheAccelerator(string $overrideCache = '', bool $fallbackSMF = true): cache\CacheApi|false
	{
		return Cache\CacheApi::load($overrideCache, $fallbackSMF);
	}
	function loadCacheAPIs(): array
	{
		return Cache\CacheApi::detect();
	}
	function clean_cache(string $type = ''): void
	{
		Cache\CacheApi::clean($type);
	}
	function cache_quick_get(string $key, string $file, string $function, array $params, int $level = 1): string
	{
		return Cache\CacheApi::quickGet($key, $file, $function, $params, $level);
	}
	function cache_put_data(string $key, mixed $value, int $ttl = 120): void
	{
		Cache\CacheApi::put($key, $value, $ttl);
	}
	function cache_get_data(string $key, int $ttl = 120): mixed
	{
		return Cache\CacheApi::get($key, $ttl);
	}
	/**
	 * End
	 * Cache\CacheApi
	 *
	 * Begin
	 * Db\DatabaseApi
	 */
	function loadDatabase(array $options = []): Db\DatabaseApi
	{
		return Db\DatabaseApi::load((array) $options);
	}
	function db_extend()
	{
		Db\DatabaseApi::extend();
	}
	/**
	 * End
	 * Db\DatabaseApi
	 *
	 * Begin
	 * Graphics\Image
	 */
	function getImageTypes(): array
	{
		return Image::getImageTypes();
	}
	function getSupportedFormats(): array
	{
		return Image::getSupportedFormats();
	}
	function imageMemoryCheck(array $sizes): bool
	{
		return Image::checkMemory($sizes);
	}
	function url_image_size(string $url): array|false
	{
		return Image::getSizeExternal($url);
	}
	function gif_outputAsPng($gif, $lpszFileName, $background_color = -1): bool
	{
		return Image::gifOutputAsPng($gif, $lpszFileName, $background_color);
	}
	function getSvgSize(string $filepath): array
	{
		return Image::getSvgSize($filepath);
	}
	function createThumbnail(string $source, int $max_width, int $max_height): bool
	{
		return Image::makeThumbnail($source, $max_width, $max_height);
	}
	function reencodeImage(string $source, int $preferred_type = 0): bool
	{
		return Image::reencodeImage($source, $preferred_type);
	}
	function checkImageContents(string $source, bool $extensive = false): bool
	{
		return Image::checkImageContents($source, $extensive);
	}
	function checkSvgContents(string $source): bool
	{
		return Image::checkSvgContents($source);
	}
	function resizeImageFile(
		string $source,
		string $destination,
		int $max_width,
		int $max_height,
		int $preferred_type = 0
	): bool {
		return Image::resizeImageFile(
			$source,
			$destination,
			$max_width,
			$max_height,
			$preferred_type
		);
	}
	function resizeImage(
		string $source,
		string $destination,
		int $src_width,
		int $src_height,
		int $max_width,
		int $max_height,
		int $preferred_type = 0
	): bool {
		return Image::resizeImage(
			$source,
			$destination,
			$src_width,
			$src_height,
			$max_width,
			$max_height,
			$preferred_type
		);
	}
	/**
	 * End
	 * Graphics\Image
	 *
	 * Begin
	 * Packagemanager\SubsPackage
	 */
	function read_tgz_file(
		string $gzfilename,
		?string $destination,
		bool $single_file = false,
		bool $overwrite = false,
		?array $files_to_extract = null
	): array|bool {
		return PackageManager\SubsPackage::read_tgz_file(
			$gzfilename,
			(string) $destination ?? null,
			$single_file,
			$overwrite,
			$files_to_extract
		);
	}
	function read_tgz_data(
		string $data,
		?string $destination,
		bool $single_file = false,
		bool $overwrite = false,
		?array $files_to_extract = null
	): array|bool {
		return PackageManager\SubsPackage::read_tgz_data(
			$data,
			$destination,
			$single_file,
			$overwrite,
			$files_to_extract
		);
	}
	function read_zip_data(
		string $data,
		string $destination,
		bool $single_file = false,
		bool $overwrite = false,
		array $files_to_extract = null
	): mixed {
		return PackageManager\SubsPackage::read_zip_data(
			$data,
			$destination,
			$single_file,
			$overwrite,
			$files_to_extract
		);
	}
	function url_exists(string $url): bool
	{
		return PackageManager\SubsPackage::url_exists($url);
	}
	function loadInstalledPackages(): array
	{
		return PackageManager\SubsPackage::loadInstalledPackages();
	}
	function getPackageInfo(string $gzfilename): array|string
	{
		return PackageManager\SubsPackage::getPackageInfo($gzfilename);
	}
	function create_chmod_control(
		array $chmodFiles = [],
		array $chmodOptions = [],
		bool $restore_write_status = false
	): array {
		return PackageManager\SubsPackage::create_chmod_control($chmodFiles, $chmodOptions, $restore_write_status);
	}
	function list_restoreFiles(mixed $dummy1, mixed $dummy2, mixed $dummy3, bool $do_change): array
	{
		return PackageManager\SubsPackage::list_restoreFiles($dummy1, $dummy2, $dummy3, $do_change);
	}
	function packageRequireFTP(string $destination_url, ?array $files = null,bool  $return = false): array
	{
		return PackageManager\SubsPackage::packageRequireFTP($destination_url, $files, $return);
	}
	function parsePackageInfo(
		PackageManager\XmlArray &$packageXML,
		bool $testing_only = true,
		string $method = 'install',
		string $previous_version = ''
	): array {
		return PackageManager\SubsPackage::parsePackageInfo(
			$packageXML,
			$testing_only,
			$method,
			$previous_version
		);
	}
	function matchHighestPackageVersion(string $versions, bool $reset, string $the_version): string|bool
	{
		return PackageManager\SubsPackage::matchHighestPackageVersion($versions, $reset, $the_version);
	}
	function matchPackageVersion(string $version, string $versions): bool
	{
		return PackageManager\SubsPackage::matchPackageVersion($version, $versions);
	}
	function compareVersions(string $version1, string $version2): int
	{
		return PackageManager\SubsPackage::compareVersions($version1, $version2);
	}
	function parse_path(string $path): string
	{
		return PackageManager\SubsPackage::parse_path($path);
	}
	function deltree(string $dir, bool $delete_dir = true): void
	{
		PackageManager\SubsPackage::deltree($dir, $delete_dir);
	}
	function mktree(string $strPath, int $mode): bool
	{
		return PackageManager\SubsPackage::mktree($strPath, $mode);
	}
	function copytree(string $source, string $destination): void
	{
		PackageManager\SubsPackage::copytree($source, $destination);
	}
	function listtree(string $path, string $sub_path = ''): array
	{
		return PackageManager\SubsPackage::listtree($path, $sub_path);
	}
	function parseModification(string $file, bool $testing = true, bool $undo = false, array $theme_paths = []): array
	{
		return PackageManager\SubsPackage::parseModification($file, $testing, $undo, $theme_paths);
	}
	function parseBoardMod(string $file, bool $testing = true, bool $undo = false, array $theme_paths = []): array
	{
		return PackageManager\SubsPackage::parseBoardMod($file, $testing, $undo, $theme_paths);
	}
	function package_get_contents(string $filename): string
	{
		return PackageManager\SubsPackage::package_get_contents($filename);
	}
	function package_put_contents(string $filename, string $data, bool $testing = false): int
	{
		return PackageManager\SubsPackage::package_put_contents($filename, $data, $testing);
	}
	function package_flush_cache(bool $trash = false): void
	{
		PackageManager\SubsPackage::package_flush_cache($trash);
	}
	function package_chmod(string $filename, string $perm_state = 'writable', bool $track_change = false): bool
	{
		return PackageManager\SubsPackage::package_chmod($filename, $perm_state, $track_change);
	}
	function package_crypt(#[\SensitiveParameter] string $pass): string
	{
		return PackageManager\SubsPackage::package_crypt($pass);
	}
	function package_unique_filename(string $dir, string $filename, string $ext): string
	{
		return PackageManager\SubsPackage::package_unique_filename($dir, $filename, $ext);
	}
	function package_create_backup(string $id = 'backup'): bool
	{
		return PackageManager\SubsPackage::package_create_backup($id);
	}
	function package_validate_installtest(array $package): array
	{
		return PackageManager\SubsPackage::package_validate_installtest($package);
	}
	function package_validate(array $packages): array
	{
		return PackageManager\SubsPackage::package_validate($packages);
	}
	function package_validate_send(array $sendData): array
	{
		return PackageManager\SubsPackage::package_validate_send($sendData);
	}
	/**
	 * End
	 * PackageManager\SubsPackage
	 *
	 * Begin
	 * PersonalMessage\DraftPM
	 */
	function showInEditor(int $member_id, $reply_to = false): bool
	{
		return PersonalMessage\DraftPM::showInEditor($member_id, $reply_to);
	}
	function showInProfile(int $memID = -1): void
	{
		PersonalMessage\DraftPM::showInProfile((int) $memID);
	}
	/**
	 * End
	 * PersonalMessage\DraftPM
	 *
	 * Begin
	 * PersonalMessage\PM
	 */
	function old(int $time): array
	{
		return PersonalMessage\PM::old($time);
	}
	function compose(): void
	{
		PersonalMessage\PM::compose();
	}
	function compose2(): bool
	{
		return PersonalMessage\PM::compose2();
	}
	function sendpm(
		array $recipients,
		string $subject,
		string $message,
		bool $store_outbox = false,
		array $from = null,
		int $pm_head = 0
	): array {
		return PersonalMessage\PM::send($recipients, $subject, $message, $store_outbox, $from ?? null, $pm_head);
	}
	function deleteMessages(?array $personal_messages, ?string $folder = null, array|int|null $owner = null): void
	{
		PersonalMessage\PM::delete($personal_messages, $folder, $owner);
	}
	function markMessages(?array $personal_messages = null, ?int $label = null, ?int $owner = null): void
	{
		PersonalMessage\PM::markRead($personal_messages, $label, $owner);
	}
	function getLatest(): int
	{
		return PersonalMessage\PM::getLatest();
	}
	function getRecent(string $sort = 'pm.id_pm', bool $descending = true, int $limit = 0, int $offset = 0): array
	{
		return PersonalMessage\PM::getRecent($sort, $descending, $limit, $offset);
	}
	function countSent(int $boundary = 0, bool $greater_than = false): int
	{
		return PersonalMessage\PM::countSent($boundary, $greater_than);
	}
	function messagePostError(array $error_types, array $named_recipients, array $recipient_ids = []): void
	{
		PersonalMessage\PM::reportErrors($error_types, $named_recipients, $recipient_ids);
	}
	function isAccessiblePM(int $pmID, string $folders = 'both'): bool
	{
		return PersonalMessage\PM::isAccessible($pmID, $folders);
	}
	/**
	 * End
	 * PersonalMessage\PM
	 *
	 * Begin
	 * PersonalMessage\Rule
	 */
	function loadRules(bool $reload = false): array
	{
		return PersonalMessage\Rule::load($reload);
	}
	function applyRules(bool $all_messages = false): void
	{
		PersonalMessage\Rule::apply($all_messages);
	}
	function delete(array $ids): void
	{
		PersonalMessage\Rule::delete($ids);
	}
	function manage(): void
	{
		PersonalMessage\Rule::manage();
	}
	/**
	 * End
	 * PersonalMessage\Rule
	 *
	 * Begin
	 * Search\SearchApi
	 */
	function findSearchAPI(): Search\SearchApiInterface
	{
		return Search\SearchApi::load();
	}
	function loadSearchAPIs(): array
	{
		return Search\SearchApi::detect();
	}
	/**
	 * End
	 * Search\SearchApi
	 *
	 * Begin
	 * Search\SearchResult
	 */
	function highlight(string $text, array $words): string
	{
		return Search\SearchResult::highlight($text, $words);
	}
	/**
	 * End
	 * Search\SearchResult
	 *
	 * Begin
	 * Unicode\Utf8String
	 * @see SMF\BackwardCompatibility
	 */
	function utf8_decompose(array $chars, bool $compatibility = false): array
	{
		return Utf8String::decompose($chars, $compatibility);
	}
	function utf8_compose(array $chars): array
	{
		return Utf8String::compose($chars);
	}
	function utf8_strtolower(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_strtoupper(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_casefold(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_convert_case(string $string, string $case, bool $simple = false): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string, $case, $simple);
	}
	function utf8_normalize_d(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_normalize_kd(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_normalize_c(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_normalize_kc(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_normalize_kc_casefold(string $string): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string);
	}
	function utf8_is_normalized(string $string, string $form): bool
	{
		return Utf8String::utf8StringFactory(__FUNCTION__, $string, null, false, $form);
	}
	function utf8_sanitize_invisibles(string $string, int $level, string $substitute): string
	{
		return (string) Utf8String::utf8StringFactory(__FUNCTION__, $string, null, false, null, $level, $substitute);
	}
	/**
	 * End
	 * Unicode\Utf8String
	 *
	 * Begin
	 * WebFetch\WebFetchApi
	 */
	function fetch_web_data(string $url, string|array $post_data = [], bool $keep_alive = false): string|false
	{
		return WebFetchApi::fetch($url, $post_data, $keep_alive);
	}
	/**
	 * End
	 * WebFetch\WebFetchApi
	 *
	 * Begin
	 * SMF\Alert
	 */
	function fetch_alerts(
		int $memID,
		bool|array $to_fetch = false,
		int $limit = 0,
		int $offset = 0,
		bool $with_avatar = false,
		bool $show_links = false
	): array {
		return SMF\Alert::fetch($memID, $to_fetch, $limit, $offset, $with_avatar, $show_links);
	}
	function alert_count(int $memID, bool $unread = false): int
	{
		return SMF\Alert::count($memID, $unread);
	}
	function alert_mark(array|int $members, array|int $to_mark, bool $read): void
	{
		SMF\Alert::mark($members, $to_mark, $read);
	}
	function alert_delete(int|array $ids, int|array $members = []): void
	{
		SMF\Alert::delete($ids, $members);
	}
	function alert_purge(int $memID = 0, int $before = 0): void
	{
		SMF\Alert::purge($memID, $before);
	}
	/**
	 * End
	 * SMF\Alert
	 *
	 * Begin
	 * SMF\Attachment
	 */
	function automanage_attachments_check_directory(): ?bool
	{
		return SMF\Attachment::automanageCheckDirectory();
	}
	function automanage_attachments_create_directory(string $updir): bool
	{
		return SMF\Attachment::automanageCreateDirectory($updir);
	}
	function automanage_attachments_by_space(): ?bool
	{
		return SMF\Attachment::automanageBySpace();
	}
	function processAttachments(): void
	{
		SMF\Attachment::process();
	}
	function attachmentChecks(int $attachID): bool
	{
		return SMF\Attachment::check($attachID);
	}
	function createAttachment(&$attachmentOptions): bool
	{
		return SMF\Attachment::create($attachmentOptions);
	}
	function assignAttachments(array $attachIDs = [], int $msgID = 0): bool
	{
		return SMF\Attachment::assign($attachIDs, $msgID);
	}
	function ApproveAttachments(array $attachments): bool
	{
		return SMF\Attachment::approve($attachments);
	}
	function removeAttachments(
		$condition,
		$query_type = '',
		$return_affected_messages = false,
		$autoThumbRemoval = true
	): ?array {
		return SMF\Attachment::remove(
			$condition,
			$query_type,
			$return_affected_messages,
			$autoThumbRemoval
		);
	}
	function parseAttachBBC(int $attachID = 0): array|string
	{
		return SMF\Attachment::parseAttachBBC($attachID);
	}
	function getAttachMsgInfo(int $attachID): SMF\Attachment|array
	{
		return SMF\Attachment::getAttachMsgInfo($attachID);
	}
	function loadAttachmentContext(int $id_msg, array $attachments): array
	{
		return SMF\Attachment::loadAttachmentContext($id_msg, $attachments);
	}
	function prepareAttachsByMsg(array $msgIDs): void
	{
		SMF\Attachment::prepareByMsg($msgIDs);
	}
	function createHash(string $input = ''): string
	{
		return SMF\Attachment::createHash($input);
	}
	function getFilePath(int $id): string
	{
		return SMF\Attachment::getFilePath($id);
	}
	function getAttachmentFilename(
		string $filename,
		int $attachment_id,
		string|null $dir = null,
		bool $new = false,
		string $file_hash = ''
	): string {
		return SMF\Attachment::getAttachmentFilename(
			$filename,
			$attachment_id,
			$dir,
			$new,
			$file_hash
		);
	}
	/**
	 * End
	 * SMF\Attachment
	 *
	 * Begin
	 * SMF\BBCodeParser
	 */
	function get_signature_allowed_bbc_tags(): array
	{
		return SMF\BBCodeParser::getSigTags();
	}
	function highlight_php_code(string $code): string
	{
		return SMF\BBCodeParser::highlightPhpCode($code);
	}
	function sanitizeMSCutPaste(string $string): string
	{
		return SMF\BBCodeParser::sanitizeMSCutPaste($string);
	}
	function parse_bbc(
		string|bool $message,
		bool $smileys = true,
		string $cache_id = '',
		array $parse_tags = []
	): string|array {
		return SMF\BBCodeParser::backcompatParseBbc(
			$message,
			$smileys,
			$cache_id,
			$parse_tags
		);
	}
	function parseSmileys(string &$message): void
	{
		SMF\BBCodeParser::backcompatParseSmileys($message);
	}
	/**
	 * End
	 * SMF\BBCodeParser
	 *
	 * Begin
	 * SMF\Board
	 */
	function loadBoard(array|int $ids = [], array $query_customizations = []): array
	{
		return SMF\Board::load($ids, $query_customizations);
	}
	function MarkRead(): void
	{
		SMF\Board::markRead();
	}
	function markBoardsRead(int|array $boards, bool $unread = false): void
	{
		SMF\Board::markBoardsRead($boards, $unread);
	}
	function getMsgMemberID(int $messageID): int
	{
		return SMF\Board::getMsgMemberID($messageID);
	}
	function modifyBoard(int $board_id, array &$boardOptions): void
	{
		SMF\Board::modify($board_id, $boardOptions);
	}
	function createBoard(array $boardOptions): int
	{
		return SMF\Board::create($boardOptions);
	}
	function deleteBoards(array $boards_to_remove, ?int $moveChildrenTo = null): void
	{
		SMF\Board::delete($boards_to_remove, $moveChildrenTo);
	}
	function reorderBoards(): void
	{
		SMF\Board::reorder();
	}
	function fixChildren(int $parent, int $newLevel, int $newParent): void
	{
		SMF\Board::fixChildren($parent, $newLevel, $newParent);
	}
	function sortBoards(array &$boards): void
	{
		SMF\Board::sort($boards);
	}
	function getBoardModerators(array $boards): array
	{
		return SMF\Board::getModerators($boards);
	}
	function getBoardModeratorGroups(array $boards): array
	{
		return SMF\Board::getModeratorGroups($boards);
	}
	function isChildOf(int $child, int $parent): bool
	{
		return SMF\Board::isChildOf($child, $parent);
	}
	function getBoardParents(int $id_parent): array
	{
		return SMF\Board::getParents($id_parent);
	}
	/**
	 * End
	 * SMF\Board
	 *
	 * Begin
	 * SMF\BrowserDetector
	 */
	function detectBrowser(): void
	{
		SMF\BrowserDetector::call();
	}
	function isBrowser(string $browser): bool
	{
		return SMF\BrowserDetector::isBrowser($browser);
	}
	/**
	 * End
	 * SMF\BrowserDetector
	 *
	 * Begin
	 * SMF\Category
	 */
	function modifyCategory(int $category_id, array $catOptions): void
	{
		SMF\Category::modify($category_id, $catOptions);
	}
	function createCategory(array $catOptions): int
	{
		return SMF\Category::create($catOptions);
	}
	function deleteCategories(array $categories, ?int $moveBoardsTo = null): void
	{
		SMF\Category::delete($categories, $moveBoardsTo);
	}
	function sortCategories(array &$categories): void
	{
		SMF\Category::sort($categories);
	}
	function getTreeOrder(): array
	{
		return SMF\Category::getTreeOrder();
	}
	function getBoardTree(): void
	{
		SMF\Category::getTree();
	}
	function recursiveBoards(&$list, &$tree): void
	{
		SMF\Category::recursiveBoards($list, $tree);
	}
	/**
	 * End
	 * SMF\Category
	 *
	 * Begin
	 * SMF\Cookie
	 */
	function setLoginCookie(int $cookie_length, int $id, string $password = ''): void
	{
		SMF\Cookie::setLoginCookie($cookie_length, $id, $password);
	}
	function setTFACookie(int $cookie_length, int $id, string $secret): void
	{
		SMF\Cookie::setTFACookie($cookie, $id, $secret);
	}
	function url_parts(bool $local, bool $global): array
	{
		return SMF\Cookie::urlParts($local, $global);
	}
	function hash_salt(string $password, string $salt): string
	{
		return SMF\Cookie::encrypt($password, $salt);
	}
	function smf_setcookie(
		string $name,
		string $value = '',
		int $expires = 0,
		string $path = '',
		string $domain = '',
		?bool $secure = null,
		bool $httponly = true,
		?string $samesite = null
	): void {
		SMF\Cookie::setcookie(
			$name,
			$value,
			$expires,
			$path,
			$domain,
			$secure,
			$httponly,
			$samesite
		);
	}
	/**
	 * End
	 * SMF\Cookie
	 *
	 * Begin
	 * SMF\Draft
	 */
	function DeleteDraft(int|array $drafts, bool $check = true): bool
	{
		return SMF\Draft::delete($drafts, $check);
	}
	function ShowDrafts(int $member_id, int $topic = 0): bool
	{
		return SMF\Draft::showInEditor($member_id, $topic);
	}
	function showProfileDrafts(int $memID): void
	{
		SMF\Draft::showInProfile($memID);
	}
	/**
	 * End
	 * SMF\Draft
	 *
	 * Begin
	 * SMF\Editor
	 */
	function create_control_richedit(array $options): SMF\Editor
	{
		return SMF\Editor::load($options);
	}
	function getMessageIcons(int $board_id): array
	{
		return SMF\Editor::getMessageIcons($board_id);
	}
	/**
	 * End
	 * SMF\Editor
	 *
	 * Begin
	 * SMF\ErrorHandler
	 */
	function smf_error_handler(int $error_level, string $error_string, string $file, int $line): void
	{
		SMF\ErrorHandler::call($error_level, $error_string, $file, $line);
	}
	function log_error(
		string $error_message,
		string|bool $error_type = 'general',
		string $file = '',
		int $line = 0
	): string {
		return SMF\ErrorHandler::log(
			$error_message,
			$error_type,
			$file,
			$line
		);
	}
	function fatal_error(string $error, string|bool $log = 'general', int $status = 500): void
	{
		SMF\ErrorHandler::fatal($error, $log, $status);
	}
	function fatal_lang_error(string $error, string|bool $log = 'general', array $sprintf = [], int $status = 403)
	{
		SMF\ErrorHandler::fatalLang($error, $log, $sprintf, $status);
	}
	function display_maintenance_message(): void
	{
		SMF\ErrorHandler::displayMaintenanceMessage();
	}
	function display_db_error(): void
	{
		SMF\ErrorHandler::displayDbError();
	}
	function display_loadavg_error(): void
	{
		SMF\ErrorHandler::displayLoadAvgError();
	}
	/**
	 * End
	 * SMF\ErrorHandler
	 *
	 * Begin
	 * SMF\Event
	 */
	function insertEvent(array $eventOptions): void
	{
		SMF\Event::create($eventOptions);
	}
	function modifyEvent(int $id, array &$eventOptions): void
	{
		SMF\Event::modify($id, $eventOptions);
	}
	function removeEvent(int $id): void
	{
		SMF\Event::remove($id);
	}
	/**
	 * End
	 * SMF\Event
	 *
	 * Begin
	 * SMF\Group
	 */
	function loadSimple(
		int $include = self::LOAD_NORMAL,
		array $exclude = [self::GUEST, self::REGULAR, self::MOD]
	): array {
		return SMF\Group::loadSimple($include, $exclude);
	}
	function loadAssignable(): array
	{
		return SMF\Group::loadAssignable();
	}
	function loadPermissionsBatch(array $group_ids, ?int $profile = null, bool $reload = false): array
	{
		return SMF\Group::loadPermissionsBatch($group_ids, $profile, $reload);
	}
	function countPermissionsBatch(array $group_ids, ?int $profile = null): array
	{
		return SMF\Group::countPermissionsBatch($group_ids, $profile);
	}
	function getPostGroups(): array
	{
		return SMF\Group::getPostGroups();
	}
	function getUnassignable(): array
	{
		return SMF\Group::getUnassignable();
	}
	function cache_getMembergroupList(): array
	{
		return SMF\Group::getCachedList();
	}
	/**
	 * End
	 * SMF\Group
	 *
	 * Begin
	 * SMF\IntegrationHook
	 */
	function call_integration_hook(string $name, array $parameters = []): array
	{
		return SMF\IntegrationHook::call($name, $parameters);
	}
	function add_integration_function(
		string $name,
		string $function,
		bool $permanent = true,
		string $file = '',
		bool $object = false
	): void {
		SMF\IntegrationHook::add(
			$name,
			$function,
			$permanent,
			$file,
			$object
		);
	}
	function remove_integration_function(
		string $name,
		string $function,
		bool $permanent = true,
		string $file = '',
		bool $object = false
	): void {
		SMF\IntegrationHook::remove(
			$name,
			$function,
			$permanent,
			$file,
			$object
		);
	}
	/**
	 * End
	 * SMF\IntegrationHook
	 *
	 * Begin
	 * SMF\IP
	 */
	function ip2range(string $addr): array
	{
		return SMF\IP::ip2range($addr);
	}
	function range2ip(string $low, string $high): string
	{
		return SMF\IP::range2ip($low, $high);
	}
	function isValidIP(string $ip): bool
	{
		return SMF\IP::ipCheckFactory(__FUNCTION__, $ip);
	}
	function isValidIPv6(string $ip): bool
	{
		return SMF\IP::ipCheckFactory(__FUNCTION__, $ip);
	}
	function host_from_ip(string $ip): string
	{
		return SMF\IP::ipCheckFactory(__FUNCTION__, $ip);
	}
	function inet_ptod(string $ip): string|bool
	{
		return SMF\IP::ipCheckFactory(__FUNCTION__, $ip);
	}
	function inet_dtop(string $ip): SMF\IP
	{
		return SMF\IP::ipCheckFactory(__FUNCTION__, $ip);
	}
	function expandIPv6(string $ip, bool $return_bool_if_invalid = true): string|false
	{
		return SMF\IP::ipCheckFactory(__FUNCTION__, $ip, $return_bool_if_invalid);
	}
	/**
	 * End
	 * SMF\IP
	 *
	 * Begin
	 * SMF\ItemList
	 */
	function createList(array $options): SMF\ItemList
	{
		return SMF\ItemList::load($options);
	}
	/**
	 * End
	 * SMF\ItemList
	 *
	 * Begin
	 * SMF\Lang
	 */
	function loadLanguage(
		string $template_name,
		string $lang = '',
		bool $fatal = true,
		bool $force_reload = false
	): string {
		return SMF\Lang::load($template_name, $lang, $fatal, $force_reload);
	}
	function getLanguages(bool $use_cache = true): array
	{
		return SMF\Lang::get($use_cache);
	}
	function censorText(&$text, bool $force = false): string
	{
		return SMF\Lang::censorText($text, $force);
	}
	function tokenTxtReplace(string $string = ''): string
	{
		return SMF\Lang::tokenTxtReplace($string);
	}
	function sentence_list(array $list): string
	{
		return SMF\Lang::sentenceList($list);
	}
	function comma_format(int|float $number, ?int $decimals = null): string
	{
		return SMF\Lang::numberFormat($number, $decimals);
	}
	/**
	 * End
	 * SMF\Lang
	 *
	 * Begin
	 * SMF\Logging
	 */
	function writeLog(bool $force = false): void
	{
		SMF\Logging::writeLog($force);
	}
	function logAction($action, array $extra = [], $log_type = 'moderate'): int
	{
		return SMF\Logging::logAction($action, $extra, $log_type);
	}
	function logActions(array $logs): int
	{
		return SMF\Logging::logActions($logs);
	}
	function updateStats(string $type, mixed $parameter1 = null, mixed $parameter2 = null): void
	{
		SMF\Logging::updateStats($type, $parameter1, $parameter2);
	}
	function trackStats(array $stats = []): bool
	{
		return SMF\Logging::trackStats($stats);
	}
	function trackStatsUsersOnline(int $total_users_online): void
	{
		SMF\Logging::trackStatsUsersOnline($total_users_online);
	}
	function getMembersOnlineStats(array $membersOnlineOptions): array
	{
		return SMF\Logging::getMembersOnlineStats($membersOnlineOptions);
	}
	function displayDebug(): void
	{
		SMF\Logging::displayDebug();
	}
	/**
	 * End
	 * SMF\Logging
	 *
	 * Begin
	 * SMF\Mail
	 */
	function sendMail(
		array $to,
		string $subject,
		string $message,
		string $from = null,
		string $message_id = null,
		bool $send_html = false,
		int $priority = 3,
		bool $hotmail_fix = null,
		bool $is_private = false
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
			$is_private
		);
	}
	function AddMailQueue(
		bool $flush = false,
		array $to_array = [],
		string $subject = '',
		string $message = '',
		string $headers = '',
		bool $send_html = false,
		int $priority = 3,
		bool $is_private = false
	): bool {
		return SMF\Mail::addToQueue(
			$flush,
			$to_array,
			$subject,
			$message,
			$headers,
			$send_html,
			$priority,
			$is_private
		);
	}
	function reduceQueue(bool|int $number = false, bool $override_limit = false, bool $force_send = false): bool
	{
		return SMF\Mail::reduceQueue($number, $override_limit, $force_send);
	}
	function mimespecialchars(
		string $string,
		bool $with_charset = true,
		bool $hotmail_fix = false,
		string $line_break = "\r\n",
		string $custom_charset = null
	): array {
		return SMF\Mail::mimespecialchars(
			$string,
			$with_charset,
			$hotmail_fix,
			$line_break,
			$custom_charset
		);
	}
	function smtp_mail(array $mail_to_array, string $subject, string $message, string $headers): bool
	{
		return SMF\Mail::sendSmtp($mail_to_array, $subject, $message, $headers);
	}
	function serverParse(string $message, $socket, string $code, string &$response = null): bool
	{
		return SMF\Mail::serverParse($message, $socket, $code, $response);
	}
	function sendNotifications(array $topics, string $type, array $exclude = [], array $members_only = [])
	{
		return SMF\Mail::sendNotifications($topics, $type, $exclude, $members_only);
	}
	function adminNotify(string $type, int $memberID, string $member_name = null): void
	{
		SMF\Mail::adminNotify($type, $memberID, $member_name);
	}
	function loadEmailTemplate(
		string $template,
		array $replacements = [],
		string $lang = '',
		bool $loadLang = true
	): array {
		return SMF\Mail::loadEmailTemplate($template, $replacements, $lang, $loadLang);
	}
	/**
	 * End
	 * SMF\Mail
	 *
	 * Begin
	 * SMF\Menu
	 */
	function createMenu(array $data, array $options = []): array|false
	{
		return SMF\Menu::create($data, $options);
	}
	function destroyMenu(int|string $id = 'last'): void
	{
		SMF\Menu::destroy($id);
	}
	/**
	 * End
	 * SMF\Menu
	 *
	 * Begin
	 * SMF\Msg
	 */
	function preparsecode(string &$message, bool $previewing = false): void
	{
		SMF\Msg::preparsecode($message, $previewing);
	}
	function un_preparsecode(string $message): string
	{
		return SMF\Msg::un_preparsecode($message);
	}
	function fixTags(string &$message): void
	{
		SMF\Msg::fixTags($message);
	}
	function fixTag(
		string &$message,
		string $myTag,
		array $protocols,
		bool $embeddedUrl = false,
		bool $hasEqualSign = false,
		bool $hasExtra = false
	): void {
		SMF\Msg::fixTag(
			$message,
			$myTag,
			$protocols,
			$embeddedUrl,
			$hasEqualSign,
			$hasExtra
		);
	}
	function SpellCheck(): void
	{
		SMF\Msg::spellCheck();
	}
	function createPost(array &$msgOptions, array &$topicOptions, array &$posterOptions): bool
	{
		return SMF\Msg::create($msgOptions, $topicOptions, $posterOptions);
	}
	function modifyPost(array &$msgOptions, array &$topicOptions, array &$posterOptions): bool
	{
		return SMF\Msg::modify($msgOptions, $topicOptions, $posterOptions);
	}
	function approvePosts(array $msgs, bool $approve = true, bool $notify = true): bool
	{
		return SMF\Msg::approve($msgs, $approve, $notify);
	}
	function clearApprovalAlerts(array $content_ids, string $content_action): void
	{
		SMF\Msg::clearApprovalAlerts($content_ids, $content_action);
	}
	function updateLastMessages(array $setboards, int $id_msg = 0): ?bool
	{
		return SMF\Msg::updateLastMessages($setboards, $id_msg);
	}
	function removeMessage(int $message, bool $decreasePostCount = true): bool
	{
		return SMF\Msg::remove($message, $decreasePostCount);
	}
	/** @see SMF\Msg::spell_init() */
	function spell_init()
	{
		return SMF\Msg::spell_init();
	}
	/**
	 * @param \PSpell\Dictionary|EnchantDictionary
	 * @param string $word
	 */
	function spell_check($dict, $word): bool
	{
		return SMF\Msg::spell_check($dict, $word);
	}
	/**
	 * @param \PSpell\Dictionary|EnchantDictionary
	 * @param string $word
	 */
	function spell_suggest($dict, $word): array
	{
		return SMF\Msg::spell_suggest($dict, $word);
	}
	/**
	 * End
	 * SMF\Msg
	 *
	 * Begin
	 * SMF\PageIndex
	 */
	function constructPageIndex(
		string $base_url,
		int &$start,
		int $max_value,
		int $num_per_page,
		bool $short_format = false,
		bool $show_prevnext = true
	): SMF\PageIndex {
		return SMF\PageIndex::load(
			$base_url,
			$start,
			$max_value,
			$num_per_page,
			$short_format,
			$show_prevnext
		);
	}
	/**
	 * End
	 * SMF\PageIndex
	 *
	 * Begin
	 * SMF\Poll
	 */
	function checkRemovePermission(SMF\Poll $poll): bool
	{
		return SMF\Poll::checkRemovePermission($poll);
	}
	function Vote(): void
	{
		SMF\Poll::vote();
	}
	function LockVoting(): void
	{
		SMF\Poll::lock();
	}
	function EditPoll(): void
	{
		SMF\Poll::edit();
	}
	function EditPoll2(): void
	{
		SMF\Poll::edit2();
	}
	function RemovePoll(): void
	{
		SMF\Poll::remove();
	}
	/**
	 * End
	 * SMF\Poll
	 *
	 * Begin
	 * SMF\Profile
	 */
	function loadCustomFieldDefinitions(): void
	{
		SMF\Profile::loadCustomFieldDefinitions();
	}
	function validateSignature(string &$value): bool|string
	{
		return SMF\Profile::validateSignature($value);
	}
	function profileLoadGroups(?int $id = null): bool
	{
		return SMF\Profile::profileProvider(calledFunction: __FUNCTION__, id: $id);
	}
	function loadProfileFields($force_reload = false, ?int $id = null): void
	{
		return SMF\Profile::profileProvider(calledFunction: __FUNCTION__, id: $id, force_reload: $force_reload);
	}
	function loadCustomFields(int $id, string $area = 'summary'): void
	{
		SMF\Profile::profileProvider(calledFunction: __FUNCTION__, id: $id, area: $area);
	}
	function loadThemeOptions(int $id, bool $defaultSettings = false): void
	{
		SMF\Profile::profileProvider(calledFunction: __FUNCTION__, id: $id, defaultSettings: $defaultSettings);
	}
	function setupProfileContext(array $fields, int $id): void
	{
		SMF\Profile::profileProvider(calledFunction: __FUNCTION__, fields: $fields, id: $id);
	}
	function makeCustomFieldChanges($id, $area, $sanitize = true, $return_errors = false): ?array
	{
		return SMF\Profile::profileProvider(
			calledFunction: __FUNCTION__,
			id: $id,
			area: $area,
			sanitize: $sanitize,
			return_errors: $return_errors
		);
	}
	function makeThemeChanges(int $id, int $id_theme): void
	{
		SMF\Profile::profileProvider(calledFunction: __FUNCTION__, id: $id, id_theme: $id_theme);
	}
	/**
	 * End
	 * SMF\Profile
	 *
	 * Begin
	 * SMF\QueryString
	 */
	function cleanRequest(): void
	{
		SMF\QueryString::cleanRequest();
	}
	function is_filtered_request(array $value_list, string $var): bool
	{
		return SMF\QueryString::isFilteredRequest($value_list, $var);
	}
	function ob_sessrewrite(string $buffer): string
	{
		return SMF\QueryString::ob_sessrewrite($buffer);
	}
	function matchIPtoCIDR(string $ip_address, string $cidr_address): bool
	{
		return SMF\QueryString::matchIPtoCIDR($ip_address, $cidr_address);
	}
	/**
	 * End
	 * SMF\QueryString
	 *
	 * Begin
	 * SMF\Security
	 */

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
			$Punycode = new Punycode();
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
			$Punycode = new Punycode();
		}

		$Punycode->useStd3($flags === ($flags | IDNA_USE_STD3_RULES));
		$Punycode->useNonTransitional($flags === ($flags | IDNA_NONTRANSITIONAL_TO_UNICODE));

		return $Punycode->decode($domain);
	}
}

?>