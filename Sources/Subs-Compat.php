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
use SMF\Punycode;

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
	class_exists('SMF\\Graphics\\Image');
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
		Actions\Admin\Attachments::browseFiles();
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