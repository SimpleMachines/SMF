<?php
// Version: 2.1; Admin

global $settings, $scripturl;

$txt['admin_boards'] = 'Boards';
$txt['admin_users'] = 'Members';
$txt['admin_newsletters'] = 'Newsletters';
$txt['admin_edit_news'] = 'News';
$txt['admin_groups'] = 'Membergroups';
$txt['admin_members'] = 'Manage Members';
$txt['admin_members_list'] = 'Below is a listing of all the members currently registered with your forum.';
$txt['admin_next'] = 'Next';
$txt['admin_censored_words'] = 'Censored Words';
$txt['admin_censored_where'] = 'Put the word to be censored on the left, and what to change it to on the right.';
$txt['admin_censored_desc'] = 'Due to the public nature of forums there may be some words that you wish to prohibit being posted by users of your forum. You can enter any words below that you wish to be censored whenever used by a member.<br />Clear a box to remove that word from the censor.';
$txt['admin_reserved_names'] = 'Reserved Names';
$txt['admin_template_edit'] = 'Edit your forum template';
$txt['admin_modifications'] = 'Modification Settings';
$txt['admin_security_moderation'] = 'Security and Moderation';
$txt['admin_server_settings'] = 'Server Settings';
$txt['admin_reserved_set'] = 'Set reserved names';
$txt['admin_reserved_line'] = 'One reserved word per line.';
$txt['admin_basic_settings'] = 'This page allows you to change the basic settings for your forum.  Be very careful with these settings, as they may render the forum dysfunctional.';
$txt['admin_maintain'] = 'Enable Maintenance Mode';
$txt['admin_title'] = 'Forum Title';
$txt['admin_url'] = 'Forum URL';
$txt['cookie_name'] = 'Cookie name';
$txt['admin_webmaster_email'] = 'Webmaster email address';
$txt['boarddir'] = 'SMF Directory';
$txt['sourcesdir'] = 'Sources Directory';
$txt['cachedir'] = 'Cache Directory';
$txt['admin_news'] = 'Enable News';
$txt['admin_guest_post'] = 'Enable guest posting';
$txt['admin_manage_members'] = 'Members';
$txt['admin_main'] = 'Main';
$txt['admin_config'] = 'Configuration';
$txt['admin_version_check'] = 'Detailed version check';
$txt['admin_smffile'] = 'SMF File';
$txt['admin_smfpackage'] = 'SMF Package';
$txt['admin_logoff'] = 'Admin End Session';
$txt['admin_maintenance'] = 'Maintenance';
$txt['admin_image_text'] = 'Show buttons as images instead of text';
$txt['admin_credits'] = 'Credits';
$txt['admin_agreement'] = 'Show and require agreement letter when registering';
$txt['admin_agreement_default'] = 'Default';
$txt['admin_agreement_select_language'] = 'Language to edit';
$txt['admin_agreement_select_language_change'] = 'Change';
$txt['admin_delete_members'] = 'Delete Selected Members';
$txt['admin_repair'] = 'Repair All boards and topics';
$txt['admin_main_welcome'] = 'This is your &quot;%1$s&quot;.  From here, you can edit settings, maintain your forum, view logs, install packages, manage themes, and many other things.<div style="margin-top: 1ex;">If you have any trouble, please look at the &quot;Support &amp; Credits&quot; page.  If the information there doesn\'t help you, feel free to <a href="http://www.simplemachines.org/community/index.php" target="_blank" class="new_win">look to us for help</a> with the problem.</div>You may also find answers to your questions or problems by clicking the <img src="' . $settings['images_url'] . '/helptopics.png" alt="%2$s" title="%3$s" /> symbols for more information on the related functions.';
$txt['admin_news_desc'] = 'Please place one news item per box. BBC tags, such as <span title="Are you bold?">[b]</span>, <span title="I tall icks!!">[i]</span> and <span title="Brackets are great, no?">[u]</span> are allowed in your news, as well as smileys. Clear a news item\'s text box to remove it.';
$txt['administrators'] = 'Forum Administrators';
$txt['admin_reserved_desc'] = 'Reserved names will keep members from registering certain usernames or using these words in their displayed names. Choose the options you wish to use from the bottom before submitting.';
$txt['admin_activation_email'] = 'Send activation email to new members upon registration';
$txt['admin_match_whole'] = 'Match whole name only. If unchecked, search within names.';
$txt['admin_match_case'] = 'Match case. If unchecked, search will be case insensitive.';
$txt['admin_check_user'] = 'Check username.';
$txt['admin_check_display'] = 'Check display name.';
$txt['admin_newsletter_send'] = 'You can email anyone from this page. The email addresses of the selected membergroups should appear below, but you may remove or add any email addresses you wish. Be sure that each address is separated in this fashion: \'address1; address2\'.';
$txt['admin_fader_delay'] = 'Fading delay between items for the news fader';
$txt['admin_bbc'] = 'Show BBC Buttons on Posting and PM Send Pages';
$txt['zero_for_no_limit'] = '(0 for no limit)';
$txt['zero_to_disable'] = '(0 to disable)';

$txt['admin_backup_fail'] = 'Failed to make backup of Settings.php - make sure Settings_bak.php exists and is writable.';
$txt['modSettings_info'] = 'Change or set options that control how this forum operates.';
$txt['database_server'] = 'Database Server';
$txt['database_user'] = 'Database Username';
$txt['database_password'] = 'Database Password';
$txt['database_name'] = 'Database Name';
$txt['registration_agreement'] = 'Registration Agreement';
$txt['registration_agreement_desc'] = 'This agreement is shown when a user registers an account on this forum and has to be accepted before users can continue registration.';
$txt['database_prefix'] = 'Database Tables Prefix';
$txt['errors_list'] = 'Listing of forum errors';
$txt['errors_found'] = 'The following errors are fouling up your forum';
$txt['errors_fix'] = 'Would you like to attempt to fix these errors?';
$txt['errors_do_recount'] = 'All errors have been fixed and a salvage area has been created. Please click the button below to recount some key statistics.';
$txt['errors_recount_now'] = 'Recount Statistics';
$txt['errors_fixing'] = 'Fixing forum errors';
$txt['errors_fixed'] = 'All errors fixed. Please check on any categories, boards, or topics created to decide what to do with them.';
$txt['attachments_avatars'] = 'Attachments and Avatars';
$txt['attachments_desc'] = 'From here you can administer the attached files on your system. You can delete attachments by size and by date from your system. Statistics on attachments are also displayed below.';
$txt['attachment_stats'] = 'File attachment statistics';
$txt['attachment_integrity_check'] = 'Attachment integrity check';
$txt['attachment_integrity_check_desc'] = 'This function will check the integrity and sizes of attachments and filenames listed in the database and, if necessary, fix errors it encounters.';
$txt['attachment_check_now'] = 'Run check now';
$txt['attachment_pruning'] = 'Attachment Pruning';
$txt['attachment_pruning_message'] = 'Message to add to post';
$txt['attachment_pruning_warning'] = 'Are you sure you want to delete these attachments?\\nThis cannot be undone!';
$txt['attachment_total'] = 'Total Attachments';
$txt['attachmentdir_size'] = 'Total size of attachment directory';
$txt['attachmentdir_size_current'] = 'Total size of current attachment directory';
$txt['attachment_space'] = 'Total space available in attachment directory';
$txt['attachment_space_current'] = 'Total space available in current attachment directory';
$txt['attachment_options'] = 'File attachment options';
$txt['attachment_log'] = 'Attachment log';
$txt['attachment_remove_old'] = 'Remove attachments older than';
$txt['attachment_remove_size'] = 'Remove attachments larger than';
$txt['attachment_name'] = 'Attachment name';
$txt['attachment_file_size'] = 'File Size';
$txt['attachmentdir_size_not_set'] = 'No maximum directory size is currently set';
$txt['attachment_delete_admin'] = '[attachment deleted by admin]';
$txt['live'] = 'Live from Simple Machines...';
$txt['remove_all'] = 'Clear Log';
$txt['approve_new_members'] = 'Admin must approve all new members';
$txt['agreement_not_writable'] = 'Warning - agreement.txt is not writable, any changes you make will NOT be saved.';

$txt['version_check_desc'] = 'This shows you the versions of your installation\'s files versus those of the latest version. If any of these files are out of date, you should download and upgrade to the latest version at <a href="http://www.simplemachines.org/" target="_blank" class="new_win">www.simplemachines.org</a>.';
$txt['version_check_more'] = '(more detailed)';

$txt['lfyi'] = 'You are unable to connect to simplemachines.org\'s latest news file.';

$txt['manage_calendar'] = 'Calendar';
$txt['manage_search'] = 'Search';

$txt['smileys_manage'] = 'Smileys and Message Icons';
$txt['smileys_manage_info'] = 'Install new smiley sets, add smileys to existing ones, or manage your message icons.';
$txt['package_info'] = 'Install new features or modify existing ones with this interface.';
$txt['theme_admin'] = 'Themes and Layout';
$txt['theme_admin_info'] = 'Setup and manage your themes, and set or reset theme options.';
$txt['registration_center'] = 'Registration';
$txt['member_center_info'] = 'View the member list, search for members, or manage account approvals and activations.';

$txt['viewmembers_name'] = 'Username (display name)';
$txt['viewmembers_online'] = 'Last Online';
$txt['viewmembers_today'] = 'Today';
$txt['viewmembers_day_ago'] = 'day ago';
$txt['viewmembers_days_ago'] = 'days ago';

$txt['display_name'] = 'Display name';
$txt['email_address'] = 'Email Address';
$txt['ip_address'] = 'IP address';
$txt['member_id'] = 'ID';

$txt['unknown'] = 'unknown';
$txt['security_wrong'] = 'Administration login attempt!' . "\n" . 'Referer: %1$s' . "\n" . 'User agent: %2$s' . "\n" . 'IP: %3$s';

$txt['email_preview_warning'] = 'The preview is not 100% accurate. In order to preserve the functionality of the page only the basic html tags are represented';
$txt['email_as_html'] = 'Send in HTML format.  (with this you can put normal HTML in the email.)';
$txt['email_parsed_html'] = 'Add &lt;br /&gt;s and &amp;nbsp;s to this message.';
$txt['email_variables'] = 'In this message you can use a few &quot;variables&quot;.  Click <a href="' . $scripturl . '?action=helpadmin;help=emailmembers" onclick="return reqOverlayDiv(this.href);" class="help">here</a> for more information.';
$txt['email_force'] = 'Send this to members even if they have chosen not to receive announcements.';
$txt['email_as_pms'] = 'Send this to these groups using personal messages.';
$txt['email_continue'] = 'Continue';
$txt['email_done'] = 'done.';

$txt['ban_title'] = 'Ban List';
$txt['ban_ip'] = 'IP banning: (e.g. 192.168.12.213 or 128.0.*.*) - one entry per line';
$txt['ban_email'] = 'Email banning: (e.g. badguy@somewhere.com) - one entry per line';
$txt['ban_username'] = 'User name banning: (e.g. l33tuser) - one entry per line';

$txt['ban_description'] = 'Here you can ban troublesome people either by IP, hostname, username, or email.';
$txt['ban_add_new'] = 'Add new ban';
$txt['ban_banned_entity'] = 'Banned entity';
$txt['ban_on_ip'] = 'Ban on IP (e.g. 192.168.10-20.*)';
$txt['ban_on_hostname'] = 'Ban on Hostname (e.g. *.mil)';
$txt['ban_on_email'] = 'Ban on Email Address (e.g. *@badsite.com)';
$txt['ban_on_username'] = 'Ban on Username';
$txt['ban_notes'] = 'Notes';
$txt['ban_restriction'] = 'Restriction';
$txt['ban_full_ban'] = 'Full ban';
$txt['ban_partial_ban'] = 'Partial ban';
$txt['ban_cannot_post'] = 'Cannot post';
$txt['ban_cannot_register'] = 'Cannot register';
$txt['ban_cannot_login'] = 'Cannot login';
$txt['ban_add'] = 'Add';
$txt['ban_edit_list'] = 'Ban List';
$txt['ban_type'] = 'Ban Type';
$txt['ban_days'] = 'day(s)';
$txt['ban_will_expire_within'] = 'Ban will expire after';
$txt['ban_added'] = 'Added';
$txt['ban_expires'] = 'Expires';
$txt['ban_hits'] = 'Hits';
$txt['ban_actions'] = 'Actions';
$txt['ban_expiration'] = 'Expiration';
$txt['ban_reason_desc'] = 'Reason for ban, to be displayed to banned member.';
$txt['ban_notes_desc'] = 'Notes that may assist other staff members.';
$txt['ban_remove_selected'] = 'Remove selected';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_remove_selected_confirm'] = 'Are you sure you want to remove the selected bans?';
$txt['ban_modify'] = 'Modify';
$txt['ban_name'] = 'Ban name';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_edit'] = 'Edit ban';
$txt['ban_add_notes'] = '<strong>Note</strong>: after creating the above ban, you can add additional entries that trigger the ban, like IP addresses, hostnames and email addresses.';
$txt['ban_expired'] = 'Expired / disabled';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_restriction_empty'] = 'No restriction selected.';

$txt['ban_triggers'] = 'Triggers';
$txt['ban_add_trigger'] = 'Add ban trigger';
$txt['ban_add_trigger_submit'] = 'Add';
$txt['ban_edit_trigger'] = 'Modify';
$txt['ban_edit_trigger_title'] = 'Edit ban trigger';
$txt['ban_edit_trigger_submit'] = 'Modify';
$txt['ban_remove_selected_triggers'] = 'Remove selected ban triggers';
$txt['ban_no_entries'] = 'There are currently no bans in effect.';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['ban_remove_selected_triggers_confirm'] = 'Are you sure you want to remove the selected ban triggers?';
$txt['ban_trigger_browse'] = 'Browse Ban Triggers';
$txt['ban_trigger_browse_description'] = 'This screen shows all banned entities grouped by IP address, hostname, email address and username.';

$txt['ban_log'] = 'Ban Log';
$txt['ban_log_description'] = 'The ban log shows all attempts to enter the forum by banned users (\'full ban\' and \'cannot register\' ban only).';
$txt['ban_log_no_entries'] = 'There are currently no ban log entries.';
$txt['ban_log_ip'] = 'IP';
$txt['ban_log_email'] = 'Email address';
$txt['ban_log_member'] = 'Member';
$txt['ban_log_date'] = 'Date';
$txt['ban_log_remove_all'] = 'Clear Log';
$txt['ban_log_remove_all_confirm'] = 'Are you sure you want to delete all ban log entries?';
$txt['ban_log_remove_selected'] = 'Remove selected';
$txt['ban_log_remove_selected_confirm'] = 'Are you sure you want to delete all selected ban log entries?';
$txt['ban_no_triggers'] = 'There are currently no ban triggers.';

$txt['settings_not_writable'] = 'These settings cannot be changed because Settings.php is read only.';

$txt['maintain_title'] = 'Forum Maintenance';
$txt['maintain_info'] = 'Optimize tables, make backups, check for errors, and prune boards with these tools.';
$txt['maintain_sub_database'] = 'Database';
$txt['maintain_sub_routine'] = 'Routine';
$txt['maintain_sub_members'] = 'Members';
$txt['maintain_sub_topics'] = 'Topics';
$txt['maintain_done'] = 'The maintenance task \'%1$s\' was executed successfully.';
$txt['maintain_no_errors'] = 'Congratulations, no errors were found.  Thanks for checking.';

$txt['maintain_tasks'] = 'Scheduled Tasks';
$txt['maintain_tasks_desc'] = 'Manage all the tasks scheduled by SMF.';

$txt['scheduled_log'] = 'Task Log';
$txt['scheduled_log_desc'] = 'Lists logs of the tasks that have be ran.';
$txt['admin_log'] = 'Administration Log';
$txt['admin_log_desc'] = 'Lists administrative tasks that have been performed by admins of your forum.';
$txt['moderation_log'] = 'Moderation Log';
$txt['moderation_log_desc'] = 'Lists moderation activities that have been performed by moderators on your forum.';
$txt['spider_log_desc'] = 'Review the entries related to search engine spider activity on your forum.';
$txt['pruning_log_desc'] = 'Use these tools to prune older entries in the various logs.';

$txt['mailqueue_title'] = 'Mail';

$txt['db_error_send'] = 'Send emails on database connection error';
$txt['db_persist'] = 'Use a persistent connection';
$txt['ssi_db_user'] = 'Database username to use in SSI mode';
$txt['ssi_db_passwd'] = 'Database password to use in SSI mode';

$txt['default_language'] = 'Default forum language';

$txt['maintenance_subject'] = 'Subject for display';
$txt['maintenance_message'] = 'Message for display';

$txt['errlog_desc'] = 'The error log tracks every error encountered by your forum.  To delete any errors from the database, mark the checkbox, and click the %1$s button at the bottom of the page.';
$txt['errlog_no_entries'] = 'There are currently no error log entries.';

$txt['theme_settings'] = 'Theme Settings';
$txt['theme_current_settings'] = 'Current Theme';

$txt['dvc_your'] = 'Your Version';
$txt['dvc_current'] = 'Current Version';
$txt['dvc_sources'] = 'Sources';
$txt['dvc_default'] = 'Default Templates';
$txt['dvc_templates'] = 'Current Templates';
$txt['dvc_languages'] = 'Language Files';

$txt['smileys_default_set_for_theme'] = 'Select default smiley set for this theme';
$txt['smileys_no_default'] = '(use global default smiley set)';

$txt['censor_test'] = 'Test censored words';
$txt['censor_test_save'] = 'Test';
$txt['censor_case'] = 'Ignore case when censoring';
$txt['censor_whole_words'] = 'Check only whole words';

$txt['admin_confirm_password'] = '(confirm)';
$txt['admin_incorrect_password'] = 'Incorrect Password';

$txt['date_format'] = '(YYYY-MM-DD)';
$txt['undefined_gender'] = 'Undefined';
$txt['age'] = 'User age';
$txt['activation_status'] = 'Activation Status';
$txt['activated'] = 'Activated';
$txt['not_activated'] = 'Not activated';
$txt['primary'] = 'Primary';
$txt['additional'] = 'Additional';
$txt['messenger_address'] = 'Messenger Address';
$txt['wild_cards_allowed'] = 'wildcard characters * and ? are allowed';
$txt['search_for'] = 'Search for';
$txt['search_match'] = 'Match';
$txt['member_part_of_these_membergroups'] = 'Member is part of these membergroups';
$txt['membergroups'] = 'Membergroups';
$txt['confirm_delete_members'] = 'Are you sure you want to delete the selected members?';

$txt['support_credits_title'] = 'Support and Credits';
$txt['support_credits_info'] = 'Links to support for common issues, and version information to give if you have problems.';
$txt['support_title'] = 'Support Information';
$txt['support_versions_current'] = 'Current SMF version';
$txt['support_versions_forum'] = 'Forum version';
$txt['support_versions_php'] = 'PHP version';
$txt['support_versions_db'] = '%1$s version';
$txt['support_versions_server'] = 'Server version';
$txt['support_versions_gd'] = 'GD version';
$txt['support_versions_imagick'] = 'Imagick version';
$txt['support_versions'] = 'Version Information';
$txt['support_resources'] = 'Support Resources';
$txt['support_resources_p1'] = 'Our <a href="%1$s">Online Manual</a> provides the main documentation for SMF. The SMF Online Manual has many documents to help answer support questions and explain <a href="%2$s">Features</a>, <a href="%3$s">Settings</a>, <a href="%4$s">Themes</a>, <a href="%5$s">Packages</a>, etc. The Online Manual documents each area of SMF thoroughly and should answer most questions quickly.';
$txt['support_resources_p2'] = 'If you can\'t find the answers to your questions in the Online Manual, you may want to search our <a href="%1$s">Support Community</a> or ask for assistance in either our <a href="%2$s">English</a> or one of our many <a href="%3$s">international support boards</a>. The SMF Support Community can be used for <a href="%4$s">support</a>, <a href="%5$s">customization</a>, and many other things such as discussing SMF, finding a host, and discussing administrative issues with other forum administrators.';

$txt['support_latest'] = 'Common support &amp; issues';
$txt['support_latest_fetch'] = 'Retrieving support information...';

$txt['edit_permissions_info'] = 'Change restrictions and available features, globally or in specific boards.';
$txt['membergroups_members'] = 'Regular Members';
$txt['membergroups_guests'] = 'Guests';
$txt['membergroups_guests_na'] = 'n/a';
$txt['membergroups_add_group'] = 'Add group';
$txt['membergroups_permissions'] = 'Permissions';

$txt['permitgroups_restrict'] = 'Restrictive';
$txt['permitgroups_standard'] = 'Standard';
$txt['permitgroups_moderator'] = 'Moderator';
$txt['permitgroups_maintenance'] = 'Maintenance';
$txt['permitgroups_inherit'] = 'Inherit';

$txt['confirm_delete_attachments_all'] = 'Are you sure you want to delete all attachments?';
$txt['confirm_delete_attachments'] = 'Are you sure you want to delete the selected attachments?';
$txt['attachment_manager_browse_files'] = 'Browse Files';
$txt['attachment_manager_repair'] = 'Maintain';
$txt['attachment_manager_avatars'] = 'Avatars';
$txt['attachment_manager_attachments'] = 'Attachments';
$txt['attachment_manager_thumbs'] = 'Thumbnails';
$txt['attachment_manager_last_active'] = 'Last Active';
$txt['attachment_manager_member'] = 'Member';
$txt['attachment_manager_avatars_older'] = 'Remove avatars from members not active for more than';
$txt['attachment_manager_total_avatars'] = 'Total Avatars';

$txt['attachment_manager_avatars_no_entries'] = 'There are currently no avatars.';
$txt['attachment_manager_attachments_no_entries'] = 'There are currently no attachments.';
$txt['attachment_manager_thumbs_no_entries'] = 'There are currently no thumbnails.';

$txt['attachment_manager_settings'] = 'Attachment Settings';
$txt['attachment_manager_avatar_settings'] = 'Avatar Settings';
$txt['attachment_manager_browse'] = 'Browse Files';
$txt['attachment_manager_maintenance'] = 'File Maintenance';
$txt['attachment_manager_save'] = 'Save';

$txt['attachmentEnable'] = 'Attachments mode';
$txt['attachmentEnable_deactivate'] = 'Disable attachments';
$txt['attachmentEnable_enable_all'] = 'Enable all attachments';
$txt['attachmentEnable_disable_new'] = 'Disable new attachments';
$txt['attachmentCheckExtensions'] = 'Check attachment\'s extension';
$txt['attachmentExtensions'] = 'Allowed attachment extensions';
$txt['attachmentRecodeLineEndings'] = 'Recode line endings in textual attachments';
$txt['attachmentShowImages'] = 'Display image attachments as pictures under post';
$txt['attachmentUploadDir'] = 'Attachments directory';
$txt['attachmentUploadDir_multiple_configure'] = 'Manage attachment directories';
$txt['attachmentDirSizeLimit'] = 'Max attachment folder space';
$txt['attachmentPostLimit'] = 'Max attachment size per post';
$txt['attachmentSizeLimit'] = 'Max size per attachment';
$txt['attachmentNumPerPostLimit'] = 'Max number of attachments per post';
$txt['attachment_img_enc_warning'] = 'Neither the GD module nor ImageMagick are currently installed. Image re-encoding is not possible.';
$txt['attachment_postsize_warning'] = 'The current php.ini setting \'post_max_size\' may not support this.';
$txt['attachment_filesize_warning'] = 'The current php.ini setting \'upload_max_filesize\' may not support this.';
$txt['attachment_image_reencode'] = 'Re-encode potentially dangerous image attachments';
$txt['attachment_image_reencode_note'] = '(requires GD module or ImageMagick)';
$txt['attachment_image_paranoid_warning'] = 'The extensive security checks can result in a large number of rejected attachments.';
$txt['attachment_image_paranoid'] = 'Perform extensive security checks on uploaded image attachments';
$txt['attachmentThumbnails'] = 'Resize images when showing under posts';
$txt['attachment_thumb_png'] = 'Save thumbnails as PNG';
$txt['attachment_thumb_memory'] = 'Adaptive thumbnail memory';
$txt['attachment_thumb_memory_note2'] = 'If the system can not get the memory no thumbnail will be created.';
$txt['attachment_thumb_memory_note1'] = 'Leave this unchecked to always attempt to create a thumbnail';
$txt['attachmentThumbWidth'] = 'Maximum width of thumbnails';
$txt['attachmentThumbHeight'] = 'Maximum height of thumbnails';
$txt['attachment_thumbnail_settings'] = 'Thumbnail Settings';
$txt['attachment_security_settings'] = 'Attachment security settings';

$txt['attach_dir_does_not_exist'] = 'Does Not Exist';
$txt['attach_dir_not_writable'] = 'Not Writable';
$txt['attach_dir_files_missing'] = 'Files Missing (<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=repair;%2$s=%1$s">Repair</a>)';
$txt['attach_dir_unused'] = 'Unused';
$txt['attach_dir_empty'] = 'Empty';
$txt['attach_dir_ok'] = 'OK';
$txt['attach_dir_basedir'] = 'Base directory';

$txt['attach_dir_desc'] = 'Create new directories or change the current directory below. Directories can be renamed as long as they do not contain a sub-directory. If the new directory is to be in the created within the forum directory structure. Just the directory name can be used. To remove a directory, blank the path input field. Directories can not be deleted if they contain either files or sub-directories (shown in brackets next to the file count).';
$txt['attach_dir_base_desc'] = 'You may use below to change the current base directory or create a new one. New base directories are also added to the Attachment Directory list. You may also designate an existing directory to be a base directory.';
$txt['attach_dir_save_problem'] = 'Oops, there seems to be a problem.';
$txt['attachments_no_create'] = 'Unable to create a new attachment directory. Please do so using a FTP client or your site file manager.';
$txt['attachments_no_write'] = 'This directory has been created but is not writable. Please attempt to do so using a FTP client or your site file manager.';
$txt['attach_dir_duplicate_msg'] = 'Unable to add. This directory already exists.';
$txt['attach_dir_exists_msg'] = 'Unable to move. A directory already exists at that path.';
$txt['attach_dir_base_dupe_msg'] = 'Unable to add. This base directory has already been created.';
$txt['attach_dir_base_no_create'] = 'Unable to create. Please verify the path input. Or create this directory using an FTP client or site file manager and re-try.';
$txt['attach_dir_no_rename'] = 'Unable to move or rename. Please verify that the path is correct or that this directory does not contain any sub-directories.';
$txt['attach_dir_no_delete'] = 'Is not empty and can not be deleted. Please do so using a FTP client or site file manager.';
$txt['attach_dir_no_remove'] = 'Still contains files or is a base directory and can not be deleted.';
$txt['attach_dir_is_current'] = 'Unable to remove while it is selected as the current directory.';
$txt['attach_dir_is_current_bd'] = 'Unable to remove while it is selected as the current base directory.';
$txt['attach_last_dir'] = 'Last active attachment directory';
$txt['attach_current_dir'] = 'Current attachment directory';
$txt['attach_current'] = 'Current';
$txt['attach_path_manage'] = 'Manage attachment paths';
$txt['attach_directories'] = 'Attachment Directories';
$txt['attach_paths'] = 'Attachment directory paths';
$txt['attach_base_paths'] = 'Base directory paths';
$txt['attach_path'] = 'Path';
$txt['attach_current_size'] = 'Size (KB)';
$txt['attach_num_files'] = 'Files';
$txt['attach_dir_status'] = 'Status';
$txt['attach_add_path'] = 'Add Path';
$txt['attach_path_current_bad'] = 'Invalid current attachment path.';
$txt['attachmentDirFileLimit'] = 'Maximum number of files per directory';

$txt['attach_base_paths'] = 'Base directory paths';
$txt['attach_num_dirs'] = 'Directories';
$txt['max_image_width'] = 'Max display width of posted or attached images';
$txt['max_image_height'] = 'Max display height of posted or attached images';

$txt['automanage_attachments'] = 'Choose the method for the management of the attachment directories';
$txt['attachments_normal'] = '(Manual) SMF default behaviour';
$txt['attachments_auto_years'] = '(Auto) Subdivide by years';
$txt['attachments_auto_months'] = '(Auto) Subdivide by years and months';
$txt['attachments_auto_days'] = '(Auto) Subdivide by years, months and days';
$txt['attachments_auto_16'] = '(Auto) 16 random folders';
$txt['attachments_auto_16x16'] = '(Auto) 16 random folders with 16 random sub-folders';
$txt['attachments_auto_space'] = '(Auto) When either directory space limit is reached';

$txt['use_subdirectories_for_attachments'] = 'Create new directories within a base directory';
$txt['use_subdirectories_for_attachments_note'] = 'Otherwise any new directories will be created within the forum\'s main directory.';
$txt['basedirectory_for_attachments'] = 'Set a base directory for attachments';
$txt['basedirectory_for_attachments_current'] = 'Current base directory';
$txt['basedirectory_for_attachments_warning'] = '<div class="smalltext">Please note that the directory is wrong. <br />(<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">Attempt to correct</a>)</div>';
$txt['attach_current_dir_warning'] = '<div class="smalltext">There seems to be a problem with this directory. <br />(<a href="' . $scripturl . '?action=admin;area=manageattachments;sa=attachpaths">Attempt to correct</a>)</div>';

$txt['mods_cat_avatars'] = 'Avatars';
$txt['avatar_directory'] = 'Avatars directory';
$txt['avatar_url'] = 'Avatars URL';
$txt['avatar_max_width_external'] = 'Maximum width of external avatar';
$txt['avatar_max_height_external'] = 'Maximum height of external avatar';
$txt['avatar_action_too_large'] = 'If the avatar is too large...';
$txt['option_refuse'] = 'Refuse it';
$txt['option_html_resize'] = 'Let the HTML resize it';
$txt['option_js_resize'] = 'Resize it with JavaScript';
$txt['option_download_and_resize'] = 'Download and resize it (requires GD module)';
$txt['avatar_max_width_upload'] = 'Maximum width of uploaded avatar';
$txt['avatar_max_height_upload'] = 'Maximum height of uploaded avatar';
$txt['avatar_resize_upload'] = 'Resize oversized large avatars';
$txt['avatar_resize_upload_note'] = '(requires GD module)';
$txt['avatar_download_png'] = 'Use PNG for resized avatars';
$txt['avatar_img_enc_warning'] = 'Neither the GD module nor ImageMagick are currently installed. Some avatar features are disabled.';
$txt['avatar_external'] = 'External avatars';
$txt['avatar_upload'] = 'Uploadable avatars';
$txt['avatar_server_stored'] = 'Server-stored avatars';
$txt['avatar_server_stored_groups'] = 'Membergroups allowed to select a server stored avatar';
$txt['avatar_upload_groups'] = 'Membergroups allowed to upload an avatar to the server';
$txt['avatar_external_url_groups'] = 'Membergroups allowed to select an external URL';
$txt['avatar_select_permission'] = 'Select permissions for each group';
$txt['avatar_download_external'] = 'Download avatar at given URL';
$txt['custom_avatar_enabled'] = 'Upload avatars to...';
$txt['option_attachment_dir'] = 'Attachment directory';
$txt['option_specified_dir'] = 'Specific directory...';
$txt['custom_avatar_dir'] = 'Upload directory';
$txt['custom_avatar_dir_desc'] = 'This should be a valid and writable directory, different than the server-stored directory.';
$txt['custom_avatar_url'] = 'Upload URL';
$txt['custom_avatar_check_empty'] = 'The custom avatar directory you have specified may be empty or invalid. Please ensure these settings are correct.';
$txt['avatar_reencode'] = 'Re-encode potentially dangerous avatars';
$txt['avatar_reencode_note'] = '(requires GD module)';
$txt['avatar_paranoid_warning'] = 'The extensive security checks can result in a large number of rejected avatars.';
$txt['avatar_paranoid'] = 'Perform extensive security checks on uploaded avatars';

$txt['repair_attachments'] = 'Maintain Attachments';
$txt['repair_attachments_complete'] = 'Maintenance Complete';
$txt['repair_attachments_complete_desc'] = 'All selected errors have now been corrected';
$txt['repair_attachments_no_errors'] = 'No errors were found';
$txt['repair_attachments_error_desc'] = 'The follow errors were found during maintenance. Check the box next to the errors you wish to fix and hit continue.';
$txt['repair_attachments_continue'] = 'Continue';
$txt['repair_attachments_cancel'] = 'Cancel';
$txt['attach_repair_missing_thumbnail_parent'] = '%1$d thumbnails are missing a parent attachment';
$txt['attach_repair_parent_missing_thumbnail'] = '%1$d parents are flagged as having thumbnails but don\'t';
$txt['attach_repair_file_missing_on_disk'] = '%1$d attachments/avatars have an entry but no longer exist on disk';
$txt['attach_repair_file_wrong_size'] = '%1$d attachments/avatars are being reported as the wrong filesize';
$txt['attach_repair_file_size_of_zero'] = '%1$d attachments/avatars have a size of zero on disk. (These will be deleted)';
$txt['attach_repair_attachment_no_msg'] = '%1$d attachments no longer have a message associated with them';
$txt['attach_repair_avatar_no_member'] = '%1$d avatars no longer have a member associated with them';
$txt['attach_repair_wrong_folder'] = '%1$d attachments are in the wrong folder';
$txt['attach_repair_files_without_attachment'] = '%1$d files do not have a corresponding entry in the database. (These will be deleted)';

$txt['news_title'] = 'News and Newsletters';
$txt['news_settings_desc'] = 'Here you can change the settings and permissions related to news and newsletters.';
$txt['news_settings_submit'] = 'Save';
$txt['news_mailing_desc'] = 'From this menu you can send messages to all members who\'ve registered and entered their email addresses. You may edit the distribution list, or send messages to all. Useful for important update/news information.';
$txt['news_error_no_news'] = 'Nothing written';
$txt['groups_edit_news'] = 'Groups allowed to edit news items';
$txt['groups_send_mail'] = 'Groups allowed to send out forum newsletters';
$txt['xmlnews_enable'] = 'Enable XML/RSS news';
$txt['xmlnews_maxlen'] = 'Maximum message length';
$txt['xmlnews_maxlen_note'] = '(0 to disable, bad idea.)';
$txt['rss_limit'] = 'RSS Limit';
$txt['rss_limit_note'] = 'Default number of items in a feed';
$txt['editnews_clickadd'] = 'Add another item';
$txt['editnews_remove_selected'] = 'Remove selected';
$txt['editnews_remove_confirm'] = 'Are you sure you want to delete the selected news items?';
$txt['censor_clickadd'] = 'Add another word';

$txt['layout_controls'] = 'Forum';
$txt['logs'] = 'Logs';
$txt['generate_reports'] = 'Reports';

$txt['update_available'] = 'Update Available';
$txt['update_message'] = 'You\'re using an outdated version of SMF, which contains some bugs which have since been fixed.
	It is recommended that you <a href="#" id="update-link">update your forum</a> to the latest version as soon as possible. It only takes a minute!';

$txt['manageposts'] = 'Posts and Topics';
$txt['manageposts_title'] = 'Manage Posts and Topics';
$txt['manageposts_description'] = 'Here you can manage all settings related to topics and posts.';

$txt['manageposts_seconds'] = 'seconds';
$txt['manageposts_minutes'] = 'minutes';
$txt['manageposts_characters'] = 'characters';
$txt['manageposts_days'] = 'days';
$txt['manageposts_posts'] = 'posts';
$txt['manageposts_topics'] = 'topics';

$txt['manageposts_settings'] = 'Post Settings';
$txt['manageposts_settings_description'] = 'Here you can set everything related to posts and posting.';
$txt['manageposts_settings_submit'] = 'Save';

$txt['manageposts_bbc_settings'] = 'Bulletin Board Code';
$txt['manageposts_bbc_settings_description'] = 'Bulletin board code can be used to add markup to forum messages. For example, to highlight the word \'house\' you can type [b]house[/b]. All Bulletin board code tags are surrounded by square brackets (\'[\' and \']\').';
$txt['manageposts_bbc_settings_title'] = 'Bulletin Board Code settings';
$txt['manageposts_bbc_settings_submit'] = 'Save';

$txt['manageposts_topic_settings'] = 'Topic Settings';
$txt['manageposts_topic_settings_description'] = 'Here you can set all settings involving topics.';
$txt['manageposts_topic_settings_submit'] = 'Save';

$txt['managedrafts_settings'] = 'Draft Settings';
$txt['managedrafts_settings_description'] = 'Here you can set all settings involving drafts.';
$txt['managedrafts_submit'] = 'Save';
$txt['manage_drafts'] = 'Drafts';
$txt['drafts_autosave'] = 'Enable the autosaving of posts as drafts';
$txt['drafts_autosave_frequency'] = 'How often should drafts be autosaved?';

$txt['removeNestedQuotes'] = 'Remove nested quotes when quoting';
$txt['enableEmbeddedFlash'] = 'Embed flash into posts';
$txt['enableEmbeddedFlash_warning'] = 'may be a security risk!';
$txt['enableSpellChecking'] = 'Enable spell checking';
$txt['enableSpellChecking_warning'] = 'this does not work on all servers.';
$txt['disable_wysiwyg'] = 'Disable WYSIWYG editor';
$txt['max_messageLength'] = 'Maximum allowed post size';
$txt['max_messageLength_zero'] = '0 for no max.';
$txt['convert_to_mediumtext'] = 'Your database is not setup to accept messages longer than 65535 characters. Please use the <a href="%1$s">database maintenance</a> page to convert the database and then came back to increase the maximum allowed post size.';
$txt['fixLongWords'] = 'Break up words with more letters than';
$txt['fixLongWords_zero'] = '0 to disable.';
$txt['fixLongWords_warning'] = 'this does not work on all servers.';
$txt['topicSummaryPosts'] = 'Posts to show on topic summary';
$txt['spamWaitTime'] = 'Time required between posts from the same IP';
$txt['edit_wait_time'] = 'Courtesy edit wait time';
$txt['edit_disable_time'] = 'Maximum time after posting to allow edit';
$txt['edit_disable_time_zero'] = '0 to disable';
$txt['preview_characters'] = 'Maximum length of last/first post preview';
$txt['preview_characters_units'] = 'characters';
$txt['preview_characters_zero'] = '0 to disable';

$txt['enableBBC'] = 'Enable bulletin board code (BBC)';
$txt['enablePostHTML'] = 'Enable <em>basic</em> HTML in posts';
$txt['autoLinkUrls'] = 'Automatically link posted URLs';
$txt['disabledBBC'] = 'Enabled BBC tags';
$txt['bbcTagsToUse'] = 'Enabled BBC tags';
$txt['bbcTagsToUse_select'] = 'Select the tags allowed to be used';
$txt['bbcTagsToUse_select_all'] = 'Select all tags';

$txt['enableStickyTopics'] = 'Enable sticky topics';
$txt['enableParticipation'] = 'Enable participation icons';
$txt['oldTopicDays'] = 'Time before topic is warned as old on reply';
$txt['oldTopicDays_zero'] = '0 to disable';
$txt['defaultMaxTopics'] = 'Number of topics per page in the message index';
$txt['defaultMaxMessages'] = 'Number of posts per page in a topic page';
$txt['disable_print_topic'] = 'Disable print topic feature';
$txt['hotTopicPosts'] = 'Number of posts for a hot topic';
$txt['hotTopicVeryPosts'] = 'Number of posts for a very hot topic';
$txt['enableAllMessages'] = 'Max topic size to show &quot;All&quot; posts';
$txt['enableAllMessages_zero'] = '0 to never show &quot;All&quot;';
$txt['disableCustomPerPage'] = 'Disable user defined topic/message count per page';
$txt['enablePreviousNext'] = 'Enable previous/next topic links';

$txt['not_done_title'] = 'Not done yet';
$txt['not_done_reason'] = 'To avoid overloading your server, the process has been temporarily paused.  It should automatically continue in a few seconds.  If it doesn\'t, please click continue below.';
$txt['not_done_continue'] = 'Continue';

$txt['general_settings'] = 'General';
$txt['database_paths_settings'] = 'Database and Paths';
$txt['cookies_sessions_settings'] = 'Cookies and Sessions';
$txt['caching_settings'] = 'Caching';
$txt['load_balancing_settings'] = 'Load Balancing';
$txt['phpinfo_settings'] = 'PHP Info';
$txt['phpinfo_localsettings'] = 'Local Settings';
$txt['phpinfo_defaultsettings'] = 'Default Settings';
$txt['phpinfo_itemsettings'] = 'Settings';

$txt['language_configuration'] = 'Languages';
$txt['language_description'] = 'This section allows you to edit languages installed on your forum, download new ones from the Simple Machines website. You may also edit language-related settings here.';
$txt['language_edit'] = 'Edit Languages';
$txt['language_add'] = 'Add Language';
$txt['language_settings'] = 'Settings';

$txt['advanced'] = 'Advanced';
$txt['simple'] = 'Simple';

$txt['admin_news_select_recipients'] = 'Please select who should receive a copy of the newsletter';
$txt['admin_news_select_group'] = 'Membergroups';
$txt['admin_news_select_group_desc'] = 'Select the groups to receive this newsletter.';
$txt['admin_news_select_members'] = 'Members';
$txt['admin_news_select_members_desc'] = 'Additional members to receive newsletter.';
$txt['admin_news_select_excluded_members'] = 'Excluded Members';
$txt['admin_news_select_excluded_members_desc'] = 'Members who should not receive newsletter.';
$txt['admin_news_select_excluded_groups'] = 'Excluded Groups';
$txt['admin_news_select_excluded_groups_desc'] = 'Select groups who should definitely not receive the newsletter.';
$txt['admin_news_select_email'] = 'Email Addresses';
$txt['admin_news_select_email_desc'] = 'A semi-colon separated list of email addresses which should be sent newsletter. (i.e. address1; address2)';
$txt['admin_news_select_override_notify'] = 'Override notification settings';
// Use entities in below.
$txt['admin_news_cannot_pm_emails_js'] = 'You cannot send a personal message to an email address. If you continue all entered email addresses will be ignored.\\n\\nAre you sure you wish to do this?';

$txt['mailqueue_browse'] = 'Browse Queue';
$txt['mailqueue_settings'] = 'Settings';

$txt['admin_search'] = 'Quick Search';
$txt['admin_search_type_internal'] = 'Task/Setting';
$txt['admin_search_type_member'] = 'Member';
$txt['admin_search_type_online'] = 'Online Manual';
$txt['admin_search_go'] = 'Go';
$txt['admin_search_results'] = 'Search Results';
$txt['admin_search_results_desc'] = 'Results for search: &quot;%1$s&quot;';
$txt['admin_search_results_again'] = 'Search again';
$txt['admin_search_results_none'] = 'No results found.';

$txt['admin_search_section_sections'] = 'Section';
$txt['admin_search_section_settings'] = 'Setting';

$txt['core_settings_title'] = 'Core Features';
$txt['mods_cat_features'] = 'General';
$txt['mods_cat_security_general'] = 'General';
$txt['antispam_title'] = 'Anti-Spam';
$txt['mods_cat_modifications_misc'] = 'Miscellaneous';
$txt['mods_cat_layout'] = 'Layout';
$txt['karma'] = 'Karma';
$txt['moderation_settings_short'] = 'Moderation';
$txt['signature_settings_short'] = 'Signatures';
$txt['custom_profile_shorttitle'] = 'Profile Fields';
$txt['pruning_title'] = 'Log Pruning';

$txt['core_settings_activation_message'] = 'The feature {core_feature} has been activated, click on the title to configure it';
$txt['core_settings_deactivation_message'] = 'The feature {core_feature} has been deactivated';
$txt['core_settings_generic_error'] = 'An unforeseen error occurred, please relod the page and try again';

$txt['boardsEdit'] = 'Modify Boards';
$txt['mboards_new_cat'] = 'Create new category';
$txt['manage_holidays'] = 'Manage Holidays';
$txt['calendar_settings'] = 'Calendar Settings';
$txt['search_weights'] = 'Weights';
$txt['search_method'] = 'Search Method';

$txt['smiley_sets'] = 'Smiley Sets';
$txt['smileys_add'] = 'Add Smiley';
$txt['smileys_edit'] = 'Edit Smileys';
$txt['smileys_set_order'] = 'Set Smiley order';
$txt['icons_edit_message_icons'] = 'Edit message icons';

$txt['membergroups_new_group'] = 'Add Membergroup';
$txt['membergroups_edit_groups'] = 'Edit Membergroups';
$txt['membergroups_edit_icons'] = 'Group Icons';
$txt['permissions_groups'] = 'General Permissions';
$txt['permissions_boards'] = 'Board Permissions';
$txt['permissions_profiles'] = 'Edit Profiles';
$txt['permissions_post_moderation'] = 'Post Moderation';

$txt['browse_packages'] = 'Browse Packages';
$txt['download_packages'] = 'Download Packages';
$txt['installed_packages'] = 'Installed Packages';
$txt['package_file_perms'] = 'File Permissions';
$txt['package_settings'] = 'Options';
$txt['themeadmin_admin_title'] = 'Manage and Install';
$txt['themeadmin_list_title'] = 'Theme Settings';
$txt['themeadmin_reset_title'] = 'Member Options';
$txt['themeadmin_edit_title'] = 'Modify Themes';
$txt['admin_browse_register_new'] = 'Register new member';

$txt['search_engines'] = 'Search Engines';
$txt['spiders'] = 'Spiders';
$txt['spider_logs'] = 'Spider Log';
$txt['spider_stats'] = 'Stats';

$txt['paid_subscriptions'] = 'Paid Subscriptions';
$txt['paid_subs_view'] = 'View Subscriptions';

$txt['hooks_title_list'] = 'Integration Hooks';
$txt['hooks_field_hook_name'] = 'Hook Name';
$txt['hooks_field_function_name'] = 'Function Name';
$txt['hooks_field_function'] = 'Function';
$txt['hooks_field_included_file'] = 'Included file';
$txt['hooks_field_file_name'] = 'File Name';
$txt['hooks_field_hook_exists'] = 'Status';
$txt['hooks_active'] = 'Exists';
$txt['hooks_disabled'] = 'Disabled';
$txt['hooks_missing'] = 'Not found';
$txt['hooks_no_hooks'] = 'There are currenty no hooks in the system.';
$txt['hooks_button_remove'] = 'Remove';
$txt['hooks_disable_instructions'] = 'Click on the status icon to enable or disable the hook';
$txt['hooks_disable_legend'] = 'Legend';
$txt['hooks_disable_legend_exists'] = 'the hook exists and is active';
$txt['hooks_disable_legend_disabled'] = 'the hook exists but has been disabled';
$txt['hooks_disable_legend_missing'] = 'the hook has not been found';
$txt['hooks_reset_filter'] = 'Reset filter';

$txt['board_perms_allow'] = 'Allow';
$txt['board_perms_ignore'] = 'Ignore';
$txt['board_perms_deny'] = 'Deny';
$txt['all_boards_in_cat'] = 'All boards in this category';

?>