<?php
// Version: 2.1 RC1; ManagePermissions

$txt['permissions_title'] = 'Manage Permissions';
$txt['permissions_modify'] = 'Modify';
$txt['permissions_view'] = 'View';
$txt['permissions_allowed'] = 'Allowed';
$txt['permissions_denied'] = 'Denied';
$txt['permission_cannot_edit'] = '<strong>Note:</strong> You cannot edit this permission profile as it is a predefined profile included within the forum software by default. If you wish to change the permissions of this profile you must first create a duplicate profile. You can carry out this task by clicking <a href="%1$s">here</a>.';

$txt['permissions_for_profile'] = 'Permissions for Profile';
$txt['permissions_boards_desc'] = 'The list below shows which set of permissions has been assigned to each board on your forum. You may edit the assigned permission profile by either clicking the board name or select &quot;edit all&quot; from the bottom of the page. To edit the profile itself simply click the profile name.';
$txt['permissions_board_all'] = 'Edit All';
$txt['permission_profile'] = 'Permission Profile';
$txt['permission_profile_desc'] = 'Which <a href="%1$s">permission set</a> the board should use.';
$txt['permission_profile_inherit'] = 'Inherit from parent board';

$txt['permissions_profile'] = 'Profile';
$txt['permissions_profiles_desc'] = 'Permission profiles are assigned to individual boards to allow you to easily manage your security settings. From this area you can create, edit and delete permission profiles.';
$txt['permissions_profiles_change_for_board'] = 'Edit Permission Profile For: &quot;%1$s&quot;';
$txt['permissions_profile_default'] = 'Default';
$txt['permissions_profile_no_polls'] = 'No Polls';
$txt['permissions_profile_reply_only'] = 'Reply Only';
$txt['permissions_profile_read_only'] = 'Read Only';

$txt['permissions_profile_rename'] = 'Rename all';
$txt['permissions_profile_edit'] = 'Edit Profiles';
$txt['permissions_profile_new'] = 'New Profile';
$txt['permissions_profile_new_create'] = 'Create';
$txt['permissions_profile_name'] = 'Profile Name';
$txt['permissions_profile_used_by'] = 'Used By';
$txt['permissions_profile_used_by_one'] = '1 Board';
$txt['permissions_profile_used_by_many'] = '%1$d Boards';
$txt['permissions_profile_used_by_none'] = 'No Boards';
$txt['permissions_profile_do_edit'] = 'Edit';
$txt['permissions_profile_do_delete'] = 'Delete';

$txt['permissionname_profile_signature'] = 'Edit signature';
$txt['permissionhelp_profile_signature'] = 'Allow the member to edit the signature field in their profile';
$txt['permissionname_profile_signature_own'] = 'Own signature';
$txt['permissionname_profile_signature_any'] = 'Any signature';
$txt['permissionname_profile_forum'] = 'Allow Forum Profile edits';
$txt['permissionhelp_profile_forum'] = 'This option will allow a member to edit their Forum Profile';
$txt['permissionname_profile_forum_own'] = 'Own profile';
$txt['permissionname_profile_forum_any'] = 'Any profile';
$txt['permissionname_profile_website'] = 'Edit website';
$txt['permissionhelp_profile_website'] = 'Allow the member to edit the website fields in their profile';
$txt['permissionname_profile_website_own'] = 'Own profile';
$txt['permissionname_profile_website_any'] = 'Any profile';
$txt['permissionname_profile_blurb'] = 'Edit personal text';
$txt['permissionhelp_profile_blurb'] = 'Allow the member to edit the personal text field in their profile';
$txt['permissionname_profile_blurb_own'] = 'Own profile';
$txt['permissionname_profile_blurb_any'] = 'Any profile';
$txt['permissions_profile_copy_from'] = 'Copy Permissions from';

$txt['permissions_includes_inherited'] = 'Inherited Groups';

$txt['permissions_all'] = 'all';
$txt['permissions_none'] = 'none';
$txt['permissions_set_permissions'] = 'Set permissions';

$txt['permissions_advanced_options'] = 'Advanced Options';
$txt['permissions_with_selection'] = 'With selection';
$txt['permissions_apply_pre_defined'] = 'Apply pre-defined permission set';
$txt['permissions_select_pre_defined'] = 'Select a pre-defined profile';
$txt['permissions_copy_from_board'] = 'Copy permissions from this board';
$txt['permissions_select_board'] = 'Select a board';
$txt['permissions_like_group'] = 'Set permissions like this group';
$txt['permissions_select_membergroup'] = 'Select a membergroup';
$txt['permissions_add'] = 'Add permission';
$txt['permissions_remove'] = 'Clear permission';
$txt['permissions_deny'] = 'Deny permission';
$txt['permissions_select_permission'] = 'Select a permission';

// All of the following block of strings should not use entities, instead use \\" for &quot; etc.
$txt['permissions_only_one_option'] = 'You can only select one action to modify the permissions';
$txt['permissions_no_action'] = 'No action selected';
$txt['permissions_deny_dangerous'] = 'You are about to deny one or more permissions.\\nThis can be dangerous and cause unexpected results if you haven\'t made sure no one is \\"accidentally\\" in the group or groups you are denying permissions to.\\n\\nAre you sure you want to continue?';

$txt['permissions_modify_group'] = 'Modify Group';
$txt['permissions_general'] = 'General Permissions';
$txt['permissions_board'] = 'Default Board Profile Permissions';
$txt['permissions_board_desc'] = '<strong>Note</strong>: changing these board permissions will affect all boards currently assigned the &quot;Default&quot; permissions profile. Boards not using the &quot;Default&quot; profile will not be affected by changes to this page.';
$txt['permissions_commit'] = 'Save changes';
$txt['permissions_on'] = 'in profile';
$txt['permissions_local_for'] = 'Permissions for group';
$txt['permissions_option_own'] = 'Own';
$txt['permissions_option_any'] = 'Any';
$txt['permissions_option_on'] = 'A';
$txt['permissions_option_off'] = 'X';
$txt['permissions_option_deny'] = 'D';
$txt['permissions_option_desc'] = 'For each permission you can pick either \'Allow\' (A), \'Disallow\' (X), or <span class="red">\'Deny\' (D)</span>.<br><br>Remember that if you deny a permission, any member - whether moderator or otherwise - that is in that group will be denied that as well.<br>For this reason, you should use deny carefully, only when <strong>necessary</strong>. Disallow, on the other hand, denies unless otherwise granted.';

$txt['permissiongroup_general'] = 'General';
$txt['permissionname_view_stats'] = 'View forum statistics';
$txt['permissionhelp_view_stats'] = 'The forum statistics is a page summarizing all statistics of the forum, like member count, daily number of posts, and several top 10 statistics. Enabling this permission adds a link to the bottom of the board index (\'[More Stats]\').';
$txt['permissionname_view_mlist'] = 'View the memberlist and groups';
$txt['permissionhelp_view_mlist'] = 'The memberlist shows all members that have registered on your forum. The list can be sorted and searched. The memberlist is linked from both the boardindex and the stats page, by clicking on the number of members. It also applies to the groups page which is a mini memberlist of people in that group.';
$txt['permissionname_who_view'] = 'View Who\'s Online page';
$txt['permissionhelp_who_view'] = 'Who\'s online shows all members that are currently online and what they are doing at that moment. This permission will only work if you also have enabled it in \'Features and Options\'. You can access the \'Who\'s Online\' screen by clicking the link in the \'Users Online\' section of the board index. Even if this is denied, members will still be able to see who\'s online, just not where they are.';
$txt['permissionname_search_posts'] = 'Search for posts and topics';
$txt['permissionhelp_search_posts'] = 'The Search permission allows the user to search all boards he or she is allowed to access. When the search permission is enabled, a \'Search\' button will be added to the forum button bar.';

$txt['permissiongroup_pm'] = 'Personal Messaging';
$txt['permissionname_pm_read'] = 'Read personal messages';
$txt['permissionhelp_pm_read'] = 'This permission allows users to access the Personal Messages section and read their Personal Messages. Without this permission a user is unable to send Personal Messages.';
$txt['permissionname_pm_send'] = 'Send personal messages';
$txt['permissionhelp_pm_send'] = 'Send personal messages to other registered members. Requires the \'Read personal messages\' permission.';

$txt['permissiongroup_calendar'] = 'Calendar';
$txt['permissionname_calendar_view'] = 'View the calendar';
$txt['permissionhelp_calendar_view'] = 'The calendar shows for each month the birthdays, events and holidays. This permission allows access to this calendar. When this permission is enabled, a button will be added to the top button bar and a list will be shown at the bottom of the board index with current and upcoming birthdays, events and holidays. The calendar needs be enabled from \'Configuration - Core Features\'.';
$txt['permissionname_calendar_post'] = 'Create events in the calendar';
$txt['permissionhelp_calendar_post'] = 'An Event is a topic linked to a certain date or date range. Creating events can be done from the calendar. An event can only be created if the user that creates the event is allowed to post new topics.';
$txt['permissionname_calendar_edit'] = 'Edit events in the calendar';
$txt['permissionhelp_calendar_edit'] = 'An Event is a topic linked to a certain date or date range. The event can be edited by clicking the red asterisk (*) next to the event in the calendar view. In order to be able to edit an event, a user must have sufficient permissions to edit the first message of the topic that is linked to the event.';
$txt['permissionname_calendar_edit_own'] = 'Own events';
$txt['permissionname_calendar_edit_any'] = 'Any events';

$txt['permissiongroup_maintenance'] = 'Forum administration';
$txt['permissionname_admin_forum'] = 'Administrate forum and database';
$txt['permissionhelp_admin_forum'] = 'This permission allows a user to:<ul class="normallist"><li>change forum, database and theme settings</li><li>manage packages</li><li>use the forum and database maintenance tools</li><li>view the error and mod logs</li></ul> Use this permission with caution, as it is very powerful.';
$txt['permissionname_manage_boards'] = 'Manage boards and categories';
$txt['permissionhelp_manage_boards'] = 'This permission allows creation, editing and removal of boards and categories.';
$txt['permissionname_manage_attachments'] = 'Manage attachments and avatars';
$txt['permissionhelp_manage_attachments'] = 'This permission allows access to the attachment center, where all forum attachments and avatars are listed and can be removed.';
$txt['permissionname_manage_smileys'] = 'Manage smileys and message icons';
$txt['permissionhelp_manage_smileys'] = 'This allows access to the smiley center. In the smiley center you can add, edit and remove smileys and smiley sets. If you\'ve enabled customized message icons you are also able to add and edit message icons with this permission.';
$txt['permissionname_edit_news'] = 'Edit news';
$txt['permissionhelp_edit_news'] = 'The news function allows a random news line to appear on each screen. In order to use the news function, enabled it in the forum settings.';
$txt['permissionname_access_mod_center'] = 'Access the moderation center';
$txt['permissionhelp_access_mod_center'] = 'With this permission any members of this group can access the moderation center from where they will have access to functionality to ease moderation. Note that this does not in itself grant any moderation privileges.';

$txt['permissiongroup_member_admin'] = 'Member administration';
$txt['permissionname_moderate_forum'] = 'Moderate forum members';
$txt['permissionhelp_moderate_forum'] = 'This permission includes all important member moderation functions:<ul class="normallist"><li>access to registration management</li><li>access to the view/delete members screen</li><li>extensive profile info, including track IP/user and (hidden) online status</li><li>activate accounts</li><li>get approval notifications and approve accounts</li><li>immune to ignore PM</li><li>several small things</li></ul>';
$txt['permissionname_manage_membergroups'] = 'Manage and assign membergroups';
$txt['permissionhelp_manage_membergroups'] = 'This permission allows a user to edit membergroups and assign membergroups to other members.';
$txt['permissionname_manage_permissions'] = 'Manage permissions';
$txt['permissionhelp_manage_permissions'] = 'This permission allows a user to edit all permissions of a membergroup, globally or for individual boards.';
$txt['permissionname_manage_bans'] = 'Manage ban list';
$txt['permissionhelp_manage_bans'] = 'This permission allows a user to add or remove usernames, IP addresses, hostnames and email addresses to a list of banned users. It also allows a user to view and remove log entries of banned users that attempted to login.';
$txt['permissionname_send_mail'] = 'Send a forum email to members';
$txt['permissionhelp_send_mail'] = 'Mass mail all forum members, or just a few membergroups by email or personal message (the latter requires \'Send Personal Message\' permission).';
$txt['permissionname_issue_warning'] = 'Issue warnings to members';
$txt['permissionhelp_issue_warning'] = 'Issue a warning to members of the forum and change that members\' warning level. Requires the warning system to be enabled.';

$txt['permissiongroup_profile'] = 'Member Profiles';
$txt['permissionname_profile_view'] = 'View other members\' profile summary and stats pages';
$txt['permissionhelp_profile_view'] = 'This permission allows users clicking on a username to see a summary of other users\' profile settings, some statistics and their posts.';
$txt['permissionname_profile_extra'] = 'Edit additional profile settings';
$txt['permissionhelp_profile_extra'] = 'Additional profile settings include settings for avatars, theme preferences, notifications and Personal Messages.';
$txt['permissionname_profile_extra_own'] = 'Own profile';
$txt['permissionname_profile_extra_any'] = 'Any profile';
$txt['permissionname_profile_title'] = 'Edit custom title';
$txt['permissionhelp_profile_title'] = 'The custom title is shown on the topic display page, under the profile of each user that has a custom title.';
$txt['permissionname_profile_title_own'] = 'Own profile';
$txt['permissionname_profile_title_any'] = 'Any profile';
$txt['permissionname_profile_server_avatar'] = 'Select an avatar from the server';
$txt['permissionhelp_profile_server_avatar'] = 'If enabled this will allow a user to select an avatar from the avatar collections installed on the server.';
$txt['permissionname_profile_upload_avatar'] = 'Upload an avatar to the server';
$txt['permissionhelp_profile_upload_avatar'] = 'This permission will allow a user to upload their personal avatar to the server.';
$txt['permissionname_profile_remote_avatar'] = 'Choose a remotely stored avatar';
$txt['permissionhelp_profile_remote_avatar'] = 'Because avatars might influence the page creation time negatively, it is possible to disallow certain membergroups to use avatars from external servers.';

$txt['permissiongroup_profile_account'] = 'Member Accounts';
$txt['permissionname_profile_identity'] = 'Edit account settings';
$txt['permissionhelp_profile_identity'] = 'Account settings are the basic settings of a profile, like password, email address, membergroup and preferred language.';
$txt['permissionname_profile_identity_own'] = 'Own profile';
$txt['permissionname_profile_identity_any'] = 'Any profile';
$txt['permissionname_profile_displayed_name'] = 'Edit displayed name';
$txt['permissionhelp_profile_displayed_name'] = 'Allow the member to edit the displayed name field in their profile';
$txt['permissionname_profile_displayed_name_own'] = 'Own displayed name';
$txt['permissionname_profile_displayed_name_any'] = 'Any displayed name';
$txt['permissionname_profile_password'] = 'Change password';
$txt['permissionhelp_profile_password'] = 'Allow the member to change the password or the secret question fields';
$txt['permissionname_profile_password_own'] = 'Own profile';
$txt['permissionname_profile_password_any'] = 'Any profile';
$txt['permissionname_profile_remove'] = 'Delete account';
$txt['permissionhelp_profile_remove'] = 'This permission allows a user to delete his account, when set to \'Own Account\'.';
$txt['permissionname_profile_remove_own'] = 'Own account';
$txt['permissionname_profile_remove_any'] = 'Any account';
$txt['permissionname_view_warning'] = 'View warning status';
$txt['permissionname_view_warning_own'] = 'Own account';
$txt['permissionname_view_warning_any'] = 'Any account';
$txt['permissionhelp_view_warning'] = 'Allows users to view their own warning status and history (\'Own account\') or that of any user (\'Any account\')';

$txt['permissionname_report_user'] = 'Report users\' profiles';
$txt['permissionhelp_report_user'] = 'This permission will allow members to report other users\' profiles to the admins to alert them of spam or other inappropriate content in the profile.';

$txt['permissiongroup_general_board'] = 'General';
$txt['permissionname_moderate_board'] = 'Moderate board';
$txt['permissionhelp_moderate_board'] = 'The moderate board permission adds a few small permissions that make a moderator a real moderator. Permissions include replying to locked topics, changing the poll expire time and viewing poll results.';

$txt['permissiongroup_topic'] = 'Topics';
$txt['permissionname_post_new'] = 'Post new topics';
$txt['permissionhelp_post_new'] = 'This permission allows users to post new topics. It doesn\'t allow to post replies to topics.';
$txt['permissionname_merge_any'] = 'Merge any topic';
$txt['permissionhelp_merge_any'] = 'Merge two or more topic into one. The order of messages within the merged topic will be based on the time the messages were created. A user can only merge topics on those boards a user is allowed to merge. In order to merge multiple topics at once, a user has to enable quickmoderation in their profile settings.';
$txt['permissionname_split_any'] = 'Split any topic';
$txt['permissionhelp_split_any'] = 'Split a topic into two separate topics.';
$txt['permissionname_make_sticky'] = 'Make topics sticky';
$txt['permissionhelp_make_sticky'] = 'Sticky topics are topics that always remain on top of a board. They can be useful for announcements or other important messages.';
$txt['permissionname_move'] = 'Move topic';
$txt['permissionhelp_move'] = 'Move a topic from one board to the other. Users can only select target boards they are allowed to access.';
$txt['permissionname_move_own'] = 'Own topic';
$txt['permissionname_move_any'] = 'Any topic';
$txt['permissionname_lock'] = 'Lock topics';
$txt['permissionhelp_lock'] = 'This permission allows a
user to lock a topic. This can be done in order to make sure no one can reply to a topic. Only users with a \'Moderate board\' permission can still post in locked topics.';
$txt['permissionname_lock_own'] = 'Own topic';
$txt['permissionname_lock_any'] = 'Any topic';
$txt['permissionname_remove'] = 'Remove topics';
$txt['permissionhelp_remove'] = 'Delete topics as a whole. Note that this permission doesn\'t allow to delete specific messages within the topic!';
$txt['permissionname_remove_own'] = 'Own topic';
$txt['permissionname_remove_any'] = 'Any topics';
$txt['permissionname_post_reply'] = 'Post replies to topics';
$txt['permissionhelp_post_reply'] = 'This permission allows replying to topics.';
$txt['permissionname_post_reply_own'] = 'Own topic';
$txt['permissionname_post_reply_any'] = 'Any topic';
$txt['permissionname_modify_replies'] = 'Modify replies to own topics';
$txt['permissionhelp_modify_replies'] = 'This permission allows a user that started a topic to modify all replies to their topic.';
$txt['permissionname_delete_replies'] = 'Delete replies to own topics';
$txt['permissionhelp_delete_replies'] = 'This permission allows a user that started a topic to remove all replies to their topic.';
$txt['permissionname_announce_topic'] = 'Announce topic';
$txt['permissionhelp_announce_topic'] = 'This allows a user to send an announcement e-mail about a topic to all members or to a few membergroups.';

$txt['permissiongroup_post'] = 'Posts';
$txt['permissionname_delete'] = 'Delete posts';
$txt['permissionhelp_delete'] = 'Remove posts. This does not allow a user to delete the first post of a topic.';
$txt['permissionname_delete_own'] = 'Own post';
$txt['permissionname_delete_any'] = 'Any post';
$txt['permissionname_modify'] = 'Modify posts';
$txt['permissionhelp_modify'] = 'Edit posts';
$txt['permissionname_modify_own'] = 'Own post';
$txt['permissionname_modify_any'] = 'Any post';
$txt['permissionname_report_any'] = 'Report posts to the moderators';
$txt['permissionhelp_report_any'] = 'This permission adds a link to each message, allowing a user to report a post to a moderator. On reporting, all moderators on that board will receive an email with a link to the reported post and a description of the problem (as given by the reporting user).';

$txt['permissiongroup_likes'] = 'Likes';
$txt['permissionname_likes_like'] = 'Can like any content';
$txt['permissionhelp_likes_like'] = 'This permission allows a user to like any content. Users aren\'t allowed to like their own content.';

$txt['permissiongroup_mentions'] = 'Mentions';
$txt['permissionname_mention'] = 'Mention others via @name';
$txt['permissionhelp_mention'] = 'This permission allows a user to mention other users by @name. For example, user Jack could be mentioned using @Jack by a user when given this permission.';

$txt['permissiongroup_poll'] = 'Polls';
$txt['permissionname_poll_view'] = 'View polls';
$txt['permissionhelp_poll_view'] = 'This permission allows a user to view a poll. Without this permission, the user will only see the topic.';
$txt['permissionname_poll_vote'] = 'Vote in polls';
$txt['permissionhelp_poll_vote'] = 'This permission allows a user to cast one vote. <br><br><strong>Note on guest voting:</strong> SMF uses cookies to attempt to prevent guests from voting multiple times. However, it should be noted that nothing can be done to completely prevent guests from voting twice, and as such, results may not be reliable. Also note that guest voting must be enabled on a per-poll basis.';
$txt['permissionname_poll_post'] = 'Post polls';
$txt['permissionhelp_poll_post'] = 'This permission allows a user to post a new poll. The user needs to have the \'Post new topics\' permission.';
$txt['permissionname_poll_add'] = 'Add poll to topics';
$txt['permissionhelp_poll_add'] = 'Add poll to topics allows a user to add a poll after the topic has been created. This permission requires sufficient rights to edit the first post of a topic.';
$txt['permissionname_poll_add_own'] = 'Own topics';
$txt['permissionname_poll_add_any'] = 'Any topics';
$txt['permissionname_poll_edit'] = 'Edit polls';
$txt['permissionhelp_poll_edit'] = 'This permission allows a user to edit the options of a poll and to reset the poll. In order to edit the maximum number of votes and the expiration time, a user needs to have the \'Moderate board\' permission.';
$txt['permissionname_poll_edit_own'] = 'Own poll';
$txt['permissionname_poll_edit_any'] = 'Any poll';
$txt['permissionname_poll_lock'] = 'Lock polls';
$txt['permissionhelp_poll_lock'] = 'Locking polls prevents the poll from accepting any more votes.';
$txt['permissionname_poll_lock_own'] = 'Own poll';
$txt['permissionname_poll_lock_any'] = 'Any poll';
$txt['permissionname_poll_remove'] = 'Remove polls';
$txt['permissionhelp_poll_remove'] = 'This permission allows removal of polls.';
$txt['permissionname_poll_remove_own'] = 'Own poll';
$txt['permissionname_poll_remove_any'] = 'Any poll';

$txt['permissionname_post_draft'] = 'Save drafts of new posts';
$txt['permissionhelp_post_draft'] = 'This permission allows users to save drafts of their posts so they can complete them later.';
$txt['permissionname_pm_draft'] = 'Save drafts of personal messages';
$txt['permissionhelp_pm_draft'] = 'This permission allows users to save drafts of their personal messages so they can complete them later.';

$txt['permissiongroup_approval'] = 'Post Moderation';
$txt['permissionname_approve_posts'] = 'Approve items awaiting moderation';
$txt['permissionhelp_approve_posts'] = 'This permission allows a user to approve all unapproved items on a board.';
$txt['permissionname_post_unapproved_replies'] = 'Post replies to topics, but hide until approved';
$txt['permissionhelp_post_unapproved_replies'] = 'This permission allows a user to post replies to a topic. The replies will not be shown until approved by a moderator.';
$txt['permissionname_post_unapproved_replies_own'] = 'Own topic';
$txt['permissionname_post_unapproved_replies_any'] = 'Any topic';
$txt['permissionname_post_unapproved_topics'] = 'Post new topics, but hide until approved';
$txt['permissionhelp_post_unapproved_topics'] = 'This permission allows a user to post a new topic which will require approval before being shown.';
$txt['permissionname_post_unapproved_attachments'] = 'Post attachments, but hide until approved';
$txt['permissionhelp_post_unapproved_attachments'] = 'This permission allows a user to attach files to their posts. The attached files will then require approval before being shown to other users.';

$txt['permissiongroup_attachment'] = 'Attachments';
$txt['permissionname_view_attachments'] = 'View attachments';
$txt['permissionhelp_view_attachments'] = 'Attachments are files that are attached to posted messages. This feature can be enabled and configured in \'Attachments and avatars\'. Since attachments are not directly accessed, you can protect them from being downloaded by users that don\'t have this permission.';
$txt['permissionname_post_attachment'] = 'Post attachments';
$txt['permissionhelp_post_attachment'] = 'Attachments are files that are attached to posted messages. One message can contain multiple attachments.';

$txt['permissionicon'] = '';

$txt['permission_settings_title'] = 'Permission Settings';
$txt['groups_manage_permissions'] = 'Membergroups allowed to manage permissions';
$txt['permission_settings_submit'] = 'Save';
$txt['permission_settings_enable_deny'] = 'Enable the option to deny permissions';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_deny_warning'] = 'Turning off this option will update \\\'Deny\\\'-permissions to \\\'Disallow\\\'.';
$txt['permission_by_board_desc'] = 'Here you can set which permissions profile a board uses. You can create new permission profiles from the &quot;Edit Profiles&quot; menu.';
$txt['permission_settings_desc'] = 'Here you can set who has permission to change permissions, as well as how sophisticated the permission system should be.';
$txt['permission_settings_enable_postgroups'] = 'Enable permissions for post count based groups';
// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['permission_disable_postgroups_warning'] = 'Disabling this setting will remove permissions currently set to post count based groups.';

$txt['permissions_post_moderation_desc'] = 'From this page, you can configure the ability to hold users\' posts before being visible to regular forum members, including which group or groups of users can approve them. Users whose posts are held for approval will still be able to see their posts, as well as replies from approvers, e.g. moderator feedback about making a post appropriate.';
$txt['permissions_post_moderation_enable'] = 'Enable Post Moderation';
$txt['permissions_post_moderation_deny_note'] = 'Note that while you have advanced permissions enabled you cannot apply the &quot;deny&quot; permission from this page. Please edit the permissions directly if you wish to apply a deny permission.';
$txt['permissions_post_moderation_select'] = 'Select Profile';
$txt['permissions_post_moderation_new_topics'] = 'New Topics';
$txt['permissions_post_moderation_replies_own'] = 'Own Replies';
$txt['permissions_post_moderation_replies_any'] = 'Any Replies';
$txt['permissions_post_moderation_attachments'] = 'Attachments';
$txt['permissions_post_moderation_legend'] = 'Legend';
$txt['permissions_post_moderation_allow'] = 'Can create';
$txt['permissions_post_moderation_moderate'] = 'Can create but requires approval';
$txt['permissions_post_moderation_disallow'] = 'Cannot create';
$txt['permissions_post_moderation_group'] = 'Group';

$txt['auto_approve_topics'] = 'Post new topics, without requiring approval';
$txt['auto_approve_replies'] = 'Post replies to topics, without requiring approval';
$txt['auto_approve_attachments'] = 'Post attachments, without requiring approval';

?>