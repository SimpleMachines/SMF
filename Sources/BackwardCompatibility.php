<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

namespace SMF;

function sanitize_chars(string $string, int $level = 0, ?string $substitute = null): string
{
	return Utils::sanitizeChars($string, $level, $substitute);
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
function ModifyAntispamSettings(bool $return_config = false)
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
function list_getFiles(int $start, int $items_per_page, string $sort, string $browse_type): array
{
	return Actions\Admin\Attachments::list_getFiles($start, $items_per_page, $sort, $browse_type);
}
function list_getNumFiles(string $browse_type): int
{
	return Actions\Admin\Attachments::list_getNumFiles($browse_type);
}
function list_getAttachDirs(): array
{
	return Actions\Admin\Attachments::list_getAttachDirs();
}
function list_getBaseDirs(): array
{
	return Actions\Admin\Attachments::list_getBaseDirs();
}
function attachDirStatus(string $dir, int $expected_files): array
{
	return Actions\Admin\Attachments::attachDirStatus($dir, $expected_files);
}
function ManageAttachmentSettings(bool $return_config = false)
{
	return Actions\Admin\Attachments::manageAttachmentSettings($return_config);
}
function ManageAvatarSettings(bool $return_config = false)
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
function list_getBans(int $start, int $items_per_page, string $sort): array
{
	return Actions\Admin\Bans::list_getBans($start, $items_per_page, $sort);
}
function list_getNumBans(): int
{
	return Actions\Admin\Bans::list_getNumBans();
}
function list_getBanItems(int $start = 0, int $items_per_page = 0, int $sort = 0, int $ban_group_id = 0): array
{
	return Actions\Admin\Bans::list_getBanItems($start, $items_per_page, $sort, $ban_group_id);
}
function list_getNumBanItems(): int
{
	return Actions\Admin\Bans::list_getNumBanItems();
}
function list_getBanTriggers(int $start, int $items_per_page, string $sort, string $trigger_type): array
{
	return Actions\Admin\Bans::list_getBanTriggers($start, $items_per_page, $sort, $trigger_type);
}
function list_getNumBanTriggers(string $trigger_type): int
{
	return Actions\Admin\Bans::list_getNumBanTriggers($trigger_type);
}
function list_getBanLogEntries(int $start, int $items_per_page, string $sort): array
{
	return Actions\Admin\Bans::list_getBanLogEntries($start, $items_per_page, $sort);
}
function list_getNumBanLogEntries(): int
{
	return Actions\Admin\Bans::list_getNumBanLogEntries();
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
 * Begin Actions\Admin\Boards
 */
function ManageBoards(): void
{
	Actions\Admin\Boards::call();
}
function EditBoardSettings(bool $return_config = false)
{

}

?>