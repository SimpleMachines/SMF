<?php
// Version: 2.0; Modlog

global $scripturl;

$txt['modlog_date'] = 'Date';
$txt['modlog_member'] = 'Member';
$txt['modlog_position'] = 'Position';
$txt['modlog_action'] = 'Action';
$txt['modlog_ip'] = 'IP';
$txt['modlog_search_result'] = 'Search Results';
$txt['modlog_total_entries'] = 'Total Entries';
$txt['modlog_ac_approve_topic'] = 'Approved topic &quot;{topic}&quot; by &quot;{member}&quot;';
$txt['modlog_ac_approve'] = 'Approved message &quot;{subject}&quot; in &quot;{topic}&quot; by &quot;{member}&quot;';
$txt['modlog_ac_lock'] = 'Locked &quot;{topic}&quot;';
$txt['modlog_ac_warning'] = 'Warned {member} for &quot;{message}&quot;';
$txt['modlog_ac_unlock'] = 'Unlocked &quot;{topic}&quot;';
$txt['modlog_ac_sticky'] = 'Stickied &quot;{topic}&quot;';
$txt['modlog_ac_unsticky'] = 'Un-Stickied &quot;{topic}&quot;';
$txt['modlog_ac_delete'] = 'Deleted &quot;{subject}&quot; by &quot;{member}&quot; from &quot;{topic}&quot;';
$txt['modlog_ac_delete_member'] = 'Deleted member &quot;{name}&quot;';
$txt['modlog_ac_remove'] = 'Removed topic &quot;{topic}&quot; from &quot;{board}&quot;';
$txt['modlog_ac_modify'] = 'Edited &quot;{message}&quot; by &quot;{member}&quot;';
$txt['modlog_ac_merge'] = 'Merged topics to create &quot;{topic}&quot;';
$txt['modlog_ac_split'] = 'Split &quot;{topic}&quot; to create &quot;{new_topic}&quot;';
$txt['modlog_ac_move'] = 'Moved &quot;{topic}&quot; from &quot;{board_from}&quot; to &quot;{board_to}&quot;';
$txt['modlog_ac_profile'] = 'Edit the profile of &quot;{member}&quot;';
$txt['modlog_ac_pruned'] = 'Pruned some posts older than {days} days';
$txt['modlog_ac_news'] = 'Edited the news';
$txt['modlog_enter_comment'] = 'Enter Moderation Comment';
$txt['modlog_moderation_log'] = 'Moderation Log';
$txt['modlog_moderation_log_desc'] = 'Below is a list of all the moderation actions that have been carried out by moderators of the forum.<br /><strong>Please note:</strong> Entries cannot be removed from this log until they are at least twenty-four hours old.';
$txt['modlog_no_entries_found'] = 'There are currently no moderation log entries.';
$txt['modlog_remove'] = 'Remove';
$txt['modlog_removeall'] = 'Remove All';
$txt['modlog_go'] = 'Go';
$txt['modlog_add'] = 'Add';
$txt['modlog_search'] = 'Quick Search';
$txt['modlog_by'] = 'By';
$txt['modlog_id'] = '<em>Deleted - ID:%1$d</em>';

$txt['modlog_ac_add_warn_template'] = 'Added warning template: &quot;{template}&quot;';
$txt['modlog_ac_modify_warn_template'] = 'Edited the warning template: &quot;{template}&quot;';
$txt['modlog_ac_delete_warn_template'] = 'Deleted the warning template: &quot;{template}&quot;';

$txt['modlog_ac_ban'] = 'Added ban triggers:';
$txt['modlog_ac_ban_trigger_member'] = ' <em>Member:</em> {member}';
$txt['modlog_ac_ban_trigger_email'] = ' <em>Email:</em> {email}';
$txt['modlog_ac_ban_trigger_ip_range'] = ' <em>IP:</em> {ip_range}';
$txt['modlog_ac_ban_trigger_hostname'] = ' <em>Hostname:</em> {hostname}';

$txt['modlog_admin_log'] = 'Administration Log';
$txt['modlog_admin_log_desc'] = 'Below is a list of administration actions which have been logged on your forum.<br /><strong>Please note:</strong> Entries cannot be removed from this log until they are at least twenty-four hours old.';
$txt['modlog_admin_log_no_entries_found'] = 'There are currently no administration log entries.';

// Admin type strings.
$txt['modlog_ac_upgrade'] = 'Upgraded the forum to version {version}';
$txt['modlog_ac_install'] = 'Installed version {version}';
$txt['modlog_ac_add_board'] = 'Added a new board: &quot;{board}&quot;';
$txt['modlog_ac_edit_board'] = 'Edited the &quot;{board}&quot; board';
$txt['modlog_ac_delete_board'] = 'Deleted the &quot;{boardname}&quot; board';
$txt['modlog_ac_add_cat'] = 'Added a new category, &quot;{catname}&quot;';
$txt['modlog_ac_edit_cat'] = 'Edited the &quot;{catname}&quot; category';
$txt['modlog_ac_delete_cat'] = 'Deleted the &quot;{catname}&quot; category';

$txt['modlog_ac_delete_group'] = 'Deleted the &quot;{group}&quot; group';
$txt['modlog_ac_add_group'] = 'Added the &quot;{group}&quot; group';
$txt['modlog_ac_edited_group'] = 'Edited the &quot;{group}&quot; group';
$txt['modlog_ac_added_to_group'] = 'Added &quot;{member}&quot; to the &quot;{group}&quot; group';
$txt['modlog_ac_removed_from_group'] = 'Removed &quot;{member}&quot; from the &quot;{group}&quot; group';
$txt['modlog_ac_removed_all_groups'] = 'Removed &quot;{member}&quot; from all groups';

$txt['modlog_ac_remind_member'] = 'Sent out a reminder to &quot;{member}&quot; to activate their account';
$txt['modlog_ac_approve_member'] = 'Approved/Activated the account of &quot;{member}&quot;';
$txt['modlog_ac_newsletter'] = 'Sent Newsletter';

$txt['modlog_ac_install_package'] = 'Installed new package: &quot;{package}&quot;, version {version}';
$txt['modlog_ac_upgrade_package'] = 'Upgraded package: &quot;{package}&quot; to version {version}';
$txt['modlog_ac_uninstall_package'] = 'Uninstalled package: &quot;{package}&quot;, version {version}';

// Restore topic.
$txt['modlog_ac_restore_topic'] = 'Restored topic &quot;{topic}&quot; from &quot;{board}&quot; to &quot;{board_to}&quot;';
$txt['modlog_ac_restore_posts'] = 'Restored posts from &quot;{subject}&quot; to the topic &quot;{topic}&quot; in the &quot;{board}&quot; board.';

$txt['modlog_parameter_guest'] = '<em>Guest</em>';

?>