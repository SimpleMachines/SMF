<?php
// Version: 2.1 Beta 1; Profile

global $scripturl, $context;

// Some of the things from the popup need their own descriptions
$txt['popup_summary'] = 'My Profile';
$txt['popup_showposts'] = 'My Posts';
$txt['popup_ignore'] = 'Ignore People';

$txt['no_profile_edit'] = 'You are not allowed to change this person\'s profile.';
$txt['website_title'] = 'Website title';
$txt['website_url'] = 'Website URL';
$txt['signature'] = 'Signature';
$txt['profile_posts'] = 'Posts';
$txt['change_profile'] = 'Change profile';
$txt['preview_signature'] = 'Preview signature';
$txt['current_signature'] = 'Current signature';
$txt['signature_preview'] = 'Signature preview';
$txt['delete_user'] = 'Delete user';
$txt['current_status'] = 'Current Status:';
$txt['personal_picture'] = 'Personalized Picture';
$txt['no_avatar'] = 'No avatar';
$txt['choose_avatar_gallery'] = 'Choose avatar from gallery';
$txt['picture_text'] = 'Picture/Text';
$txt['reset_form'] = 'Reset Form';
$txt['preferred_language'] = 'Preferred Language';
$txt['age'] = 'Age';
$txt['no_pic'] = '(no pic)';
$txt['latest_posts'] = 'Latest posts of: ';
$txt['additional_info'] = 'Additional Information';
$txt['avatar_by_url'] = 'Specify your own avatar by URL. (e.g.: <em>http://www.mypage.com/mypic.png</em>)';
$txt['my_own_pic'] = 'Specify avatar by URL';
$txt['use_gravatar'] = 'Use my Gravatar';
$txt['gravatar_alternateEmail'] = 'Normally, the Gravatar used will be based on your regular email address but if you wish to use the Gravatar from a different email account to your regular forum account (say, the Gravatar from your blog\'s email account), you can enter that email address here.';
$txt['gravatar_noAlternateEmail'] = 'The Gravatar displayed will be the one based on your account\'s email address.';
$txt['date_format'] = 'The format here will be used to show dates throughout this forum.';
$txt['time_format'] = 'Time Format';
$txt['timezone'] = 'Timezone';
$txt['display_name_desc'] = 'This is the displayed name that people will see.';
$txt['personal_time_offset'] = 'Number of hours to +/- to make displayed time equal to your local time.';
$txt['dob'] = 'Birthdate';
$txt['dob_month'] = 'Month (MM)';
$txt['dob_day'] = 'Day (DD)';
$txt['dob_year'] = 'Year (YYYY)';
$txt['password_strength'] = 'For best security, you should use eight or more characters with a combination of letters, numbers, and symbols.';
$txt['include_website_url'] = 'This must be included if you specify a URL below.';
$txt['complete_url'] = 'This must be a complete URL.';
$txt['sig_info'] = 'Signatures are displayed at the bottom of each post or personal message. BBCode and smileys may be used in your signature.';
$txt['no_signature_set'] = 'No signature set.';
$txt['no_signature_preview'] = 'No signature to preview.';
$txt['max_sig_characters'] = 'Max characters: %1$d; characters remaining: ';
$txt['send_member_pm'] = 'Send this member a personal message';
$txt['hidden'] = 'hidden';
$txt['current_time'] = 'Current forum time';

$txt['skype_username'] = 'Your Skype Username.';

$txt['language'] = 'Language';
$txt['avatar_too_big'] = 'Avatar image is too big, please resize it and try again (max';
$txt['invalid_registration'] = 'Invalid Date Registered value, valid example:';
$txt['current_password'] = 'Current Password';
// Don't use entities in the below string, except the main ones. (lt, gt, quot.)
$txt['required_security_reasons'] = 'For security reasons, your current password is required to make changes to your account.';
$txt['email_change_logout'] = 'Since you decided to change your email, you will need to reactivate your account. You will now be logged out.';

$txt['timeoffset_autodetect'] = 'auto detect';

$txt['secret_question'] = 'Secret Question';
$txt['secret_desc'] = 'To help retrieve your password, enter a question here with an answer that <strong>only</strong> you know.';
$txt['secret_desc2'] = 'Choose carefully, you wouldn\'t want someone guessing your answer!';
$txt['secret_answer'] = 'Answer';
$txt['secret_ask'] = 'Ask me my question';
$txt['cant_retrieve'] = 'You can\'t retrieve your password, but you can set a new one by following a link sent to you by email. You also have the option of setting a new password by answering your secret question.';
$txt['incorrect_answer'] = 'Sorry, but you did not specify a valid combination of Secret Question and Answer in your profile. Please click on the back button, and use the default method of obtaining your password.';
$txt['enter_new_password'] = 'Please enter the answer to your question, and the password you would like to use. Your password will be changed to the one you select provided you answer the question correctly.';
$txt['password_success'] = 'Your password was changed successfully.<br>Click <a href="' . $scripturl . '?action=login">here</a> to login.';
$txt['secret_why_blank'] = 'why is this blank?';

$txt['authentication_reminder'] = 'Authentication Reminder';
$txt['password_reminder_desc'] = 'If you\'ve forgotten your login details, don\'t worry, they can be retrieved. To start this process please enter your username or email address below.';
$txt['authentication_password_email'] = 'Email me a new password';
$txt['authentication_password_secret'] = 'Let me set a new password by answering my &quot;secret question&quot;';
$txt['reminder_continue'] = 'Continue';

$txt['current_theme'] = 'Current Theme';
$txt['change'] = 'change';
$txt['theme_preferences'] = 'Theme preferences';
$txt['theme_forum_default'] = 'Forum or Board Default';
$txt['theme_forum_default_desc'] = 'This is the default theme, which means your theme will change along with the administrator\'s settings and the board you are viewing.';

$txt['profileConfirm'] = 'Do you really want to delete this member?';

$txt['custom_title'] = 'Custom Title';

$txt['lastLoggedIn'] = 'Last Active';

$txt['alert_prefs'] = 'Notification Preferences';
$txt['alert_prefs_desc'] = 'This page will allow you to configure when and how you get notified about new content.';
$txt['watched_topics'] = 'Watched Topics';
$txt['watched_topics_desc'] = 'This page lets you review which topics you are watching; when topics that you are watching have been replied to, you can be notified.';
$txt['watched_boards'] = 'Watched Boards';
$txt['watched_boards_desc'] = 'This page lets you review which boards you are watching; when boards that you are watching have new topics, you can be notified.';

$txt['notification_general'] = 'General Settings';
$txt['notify_settings'] = 'Notification Settings:';
$txt['notify_save'] = 'Save settings';
$txt['notify_important_email'] = 'Receive forum newsletters, announcements and important notifications by email.';
$txt['notify_regularity'] = 'For topics and boards I\'ve requested notification on, notify me';
$txt['notify_regularity_instant'] = 'Instantly';
$txt['notify_regularity_first_only'] = 'Instantly - but only for the first unread reply';
$txt['notify_regularity_daily'] = 'Daily';
$txt['notify_regularity_weekly'] = 'Weekly';
$txt['auto_notify'] = 'Turn notification on when you post or reply to a topic.';
$txt['notify_send_types'] = 'For topics and boards I\'ve requested notification on, notify me of';
$txt['notify_send_type_everything'] = 'Replies and moderation';
$txt['notify_send_type_everything_own'] = 'Moderation only if I started the topic and am following it';
$txt['notify_send_type_only_replies'] = 'Only replies';
$txt['notify_send_type_nothing'] = 'Nothing at all';
$txt['notify_send_body'] = 'When sending notification of a reply to a topic, send the post in the email (but please don\'t reply to these emails.)';
$txt['notify_alert_timeout'] = 'Timeout for Alert desktop notifications';

$txt['notify_what_how'] = 'Alert Preferences';
$txt['receive_alert'] = 'Receive alert';
$txt['receive_mail'] = 'Receive email';
$txt['alert_group_board'] = 'Boards and Topics';
$txt['alert_group_msg'] = 'Posts';
$txt['alert_opt_pm_notify'] = 'If enabled, e-mail alerts for:';
$txt['alert_opt_msg_notify_type'] = 'Notify me of:';
$txt['alert_opt_msg_auto_notify'] = 'Follow topics I create and reply to';
$txt['alert_opt_msg_receive_body'] = 'Receive message body in e-mails';
$txt['alert_opt_msg_notify_pref'] = 'How frequently to tell me:';
$txt['alert_opt_msg_notify_pref_nothing'] = 'Nothing, just make a note of it';
$txt['alert_opt_msg_notify_pref_instant'] = 'Straight away';
$txt['alert_opt_msg_notify_pref_first'] = 'Straight away (but only for the first unread message)';
$txt['alert_opt_msg_notify_pref_daily'] = 'Send me a daily email digest';
$txt['alert_opt_msg_notify_pref_weekly'] = 'Send me a weekly email digest';
$txt['alert_topic_notify'] = 'When a topic I follow gets a reply, I normally want to know via...';
$txt['alert_board_notify'] = 'When a board I follow gets a topic, I normally want to know via...';
$txt['alert_msg_mention'] = 'When my @name is mentioned in a post';
$txt['alert_msg_quote'] = 'When a post of mine is quoted (when I\'m not already watching that topic)';
$txt['alert_msg_like'] = 'When a message of mine is liked';
$txt['alert_group_pm'] = 'Personal Messages';
$txt['alert_pm_new'] = 'When I receive a new personal message';
$txt['alert_pm_reply'] = 'When a personal message I sent gets replied to';
$txt['alert_group_moderation'] = 'Moderation';
$txt['alert_unapproved_topic'] = 'When an unapproved topic is posted';
$txt['alert_unapproved_reply'] = 'When an reply is made to my unapproved topic';
$txt['alert_msg_report'] = 'When a message is reported';
$txt['alert_msg_report_reply'] = 'When a post report I\'ve replied to gets replied to';
$txt['alert_group_members'] = 'Members';
$txt['alert_member_register'] = 'When a new person registers';
$txt['alert_warn_any'] = 'When other members receive a warning';
$txt['alert_group_calendar'] = 'Calendar';
$txt['alert_event_new'] = 'When a new event goes into the calendar';
$txt['alert_request_group'] = 'When someone requests to join a group I moderate';
$txt['alert_member_report'] = 'When another member\'s profile is reported';
$txt['alert_member_report_reply'] = 'When a member report I\'ve replied to gets replied to';
$txt['alert_group_paidsubs'] = 'Paid Subscriptions';
$txt['alert_paidsubs_expiring'] = 'When your Paid Subscriptions are about to expire';
$txt['toggle_all'] = 'toggle all';


$txt['notifications_topics'] = 'Current Topic Notifications';
$txt['notifications_topics_list'] = 'You are being notified of replies to the following topics';
$txt['notifications_topics_none'] = 'You are not currently receiving any notifications from topics.';
$txt['notifications_topics_howto'] = 'To receive notifications from a topic, click the &quot;notify&quot; button while viewing it.';
$txt['notifications_boards'] = 'Current Board Notifications';
$txt['notifications_boards_list'] = 'You are being notified of new topics posted in the following boards';
$txt['notifications_boards_none'] = 'You aren\'t receiving notifications on any boards right now.';
$txt['notifications_boards_howto'] = 'To request notifications from a specific board, click the &quot;notify&quot; button in the index of that board.';
$txt['notifications_update'] = 'Unnotify';

$txt['statPanel_showStats'] = 'User statistics for: ';
$txt['statPanel_users_votes'] = 'Number of Votes Cast';
$txt['statPanel_users_polls'] = 'Number of Polls Created';
$txt['statPanel_total_time_online'] = 'Total Time Spent Online';
$txt['statPanel_noPosts'] = 'No posts to speak of!';
$txt['statPanel_generalStats'] = 'General Statistics';
$txt['statPanel_posts'] = 'posts';
$txt['statPanel_topics'] = 'topics';
$txt['statPanel_total_posts'] = 'Total Posts';
$txt['statPanel_total_topics'] = 'Total Topics Started';
$txt['statPanel_votes'] = 'votes';
$txt['statPanel_polls'] = 'polls';
$txt['statPanel_topBoards'] = 'Most Popular Boards By Posts';
$txt['statPanel_topBoards_posts'] = '%1$d posts of the board\'s %2$d posts (%3$01.2f%%)';
$txt['statPanel_topBoards_memberposts'] = '%1$d posts of the member\'s %2$d posts (%3$01.2f%%)';
$txt['statPanel_topBoardsActivity'] = 'Most Popular Boards By Activity';
$txt['statPanel_activityTime'] = 'Posting Activity By Time';
$txt['statPanel_activityTime_posts'] = '%1$d posts (%2$d%%)';
$txt['statPanel_timeOfDay'] = 'Time of Day';

$txt['deleteAccount_warning'] = 'Warning - These actions are irreversible!';
$txt['deleteAccount_desc'] = 'From this page you can delete this user\'s account and posts.';
$txt['deleteAccount_member'] = 'Delete this member\'s account';
$txt['deleteAccount_posts'] = 'Remove posts made by this member';
$txt['deleteAccount_all_posts'] = 'Replies to Topics';
$txt['deleteAccount_topics'] = 'Topics and Posts';
$txt['deleteAccount_votes'] = 'Remove poll votes made by this member';
$txt['deleteAccount_confirm'] = 'Are you completely sure you want to delete this account?';
$txt['deleteAccount_approval'] = 'Please note that the forum moderators will have to approve this account\'s deletion before it will be removed.';

$txt['profile_of_username'] = 'Profile of %1$s';
$txt['profileInfo'] = 'Profile Info';
$txt['showPosts'] = 'Show Posts';
$txt['showPosts_help'] = 'This section allows you to view all posts made by this member. Note that you can only see posts made in areas you currently have access to.';
$txt['showMessages'] = 'Messages';
$txt['showTopics'] = 'Topics';
$txt['showUnwatched'] = 'Unwatched topics';
$txt['showAttachments'] = 'Attachments';
$txt['viewWarning_help'] = 'This section allows you to view all warnings issued to this member.';
$txt['statPanel'] = 'Show Stats';
$txt['editBuddyIgnoreLists'] = 'Buddies/Ignore List';
$txt['could_not_add_person'] = 'You could not add that person to your list';
$txt['could_not_remove_person'] = 'You could not remove that person from your list';
$txt['editBuddies'] = 'Edit Buddies';
$txt['editIgnoreList'] = 'Edit Ignore List';
$txt['trackUser'] = 'Track User';
$txt['trackActivity'] = 'Activity';
$txt['trackIP'] = 'IP Address';
$txt['trackLogins'] = 'Logins';

$txt['account_info'] = 'These are your account settings. This page holds all critical information that identifies you on this forum. For security reasons, you will need to enter your (current) password to make changes to this information.';
$txt['forumProfile_info'] = 'You can change your personal information on this page. This information will be displayed throughout ' . $context['forum_name_html_safe'] . '. If you aren\'t comfortable with sharing some information, simply skip it - nothing here is required.';
$txt['theme_info'] = 'This section allows you to customize the look and layout of the forum.';
$txt['notification'] = 'Notifications';
$txt['notification_info'] = 'SMF allows you to be notified of replies to posts, newly posted topics, and forum announcements. You can change those settings here, or oversee the topics and boards you are currently receiving notifications for.';
$txt['groupmembership'] = 'Group Membership';
$txt['groupMembership_info'] = 'In this section of your profile you can change which groups you belong to.';
$txt['ignoreboards'] = 'Ignore Boards';
$txt['ignoreboards_info'] = 'This page lets you ignore particular boards. When a board is ignored, the new post indicator will not show up on the board index. New posts will not show up using the "unread post" search link (when searching it will not look in those boards) however, ignored boards will still appear on the board index and upon entering will show which topics have new posts. When using the "unread replies" link, new posts in an ignored board will still be shown.';
$txt['alerts_show'] = 'Show Alerts';

$txt['profileAction'] = 'Actions';
$txt['deleteAccount'] = 'Delete this account';
$txt['profileSendIm'] = 'Send personal message';
$txt['profile_sendpm_short'] = 'Send PM';

$txt['profileBanUser'] = 'Ban this user';

$txt['display_name'] = 'Display name';
$txt['enter_ip'] = 'Enter IP (range)';
$txt['errors_by'] = 'Error messages by';
$txt['errors_desc'] = 'Below is a list of all the recent errors that this user has generated/experienced.';
$txt['errors_from_ip'] = 'Error messages from IP (range)';
$txt['errors_from_ip_desc'] = 'Below is a list of all recent error messages generated by this IP (range).';
$txt['ip_address'] = 'IP address';
$txt['ips_in_errors'] = 'IPs used in error messages';
$txt['ips_in_messages'] = 'IPs used in recent posts';
$txt['members_from_ip'] = 'Members from IP (range)';
$txt['members_in_range'] = 'Members possibly in the same range';
$txt['messages_from_ip'] = 'Messages posted from IP (range)';
$txt['messages_from_ip_desc'] = 'Below is a list of all messages posted from this IP (range).';
$txt['trackLogins_desc'] = 'Below is a list of all times this account was logged into.';
$txt['most_recent_ip'] = 'Most recent IP address';
$txt['why_two_ip_address'] = 'Why are there two IP addresses listed?';
$txt['no_errors_from_ip'] = 'No error messages from the specified IP (range) found';
$txt['no_errors_from_user'] = 'No error messages from the specified user found';
$txt['no_members_from_ip'] = 'No members from the specified IP (range) found';
$txt['no_messages_from_ip'] = 'No messages from the specified IP (range) found';
$txt['trackLogins_none_found'] = 'No recent logins were found';
$txt['none'] = 'None';
$txt['own_profile_confirm'] = 'Are you sure you want to delete your account?';
$txt['view_ips_by'] = 'View IPs used by';

$txt['avatar_will_upload'] = 'Upload an avatar';
$txt['avatar_max_size_wh'] = 'Max size: %1$spx by %2$spx';
$txt['avatar_max_size_w'] = 'Max size: %1$spx wide';
$txt['avatar_max_size_h'] = 'Max size: %2$spx high';

// Use numeric entities in the below three strings.
$txt['no_reminder_email'] = 'Unable to send reminder email.';
$txt['send_email'] = 'Send an email to';
$txt['to_ask_password'] = 'to ask for your authentication details';

$txt['user_email'] = 'Username/Email';

// Use numeric entities in the below two strings.
$txt['reminder_subject'] = 'New password for ' . $context['forum_name'];
$txt['reminder_mail'] = 'This mail was sent because the \'forgot password\' function has been applied to your account. To set a new password, click the following link';
$txt['reminder_sent'] = 'A mail has been sent to your email address. Click the link in that mail to set a new password.';
$txt['reminder_set_password'] = 'Set Password';
$txt['reminder_password_set'] = 'Password successfully set';
$txt['reminder_error'] = '%1$s failed to answer their secret question correctly when attempting to change a forgotten password.';

$txt['registration_not_approved'] = 'Sorry, this account has not yet been approved. If you need to change your email address please click <a href="%1$s">here</a>.';
$txt['registration_not_activated'] = 'Sorry, this account has not yet been activated. If you need to resend the activation email please click <a href="%1$s">here</a>';

$txt['primary_membergroup'] = 'Primary Membergroup';
$txt['additional_membergroups'] = 'Additional Membergroups';
$txt['additional_membergroups_show'] = 'show additional groups';
$txt['no_primary_membergroup'] = '(no primary membergroup)';
$txt['deadmin_confirm'] = 'Are you sure you wish to irrevocably remove your admin status?';

$txt['account_activate_method_2'] = 'Account requires reactivation after email change';
$txt['account_activate_method_3'] = 'Account is not approved';
$txt['account_activate_method_4'] = 'Account is awaiting approval for deletion';
$txt['account_activate_method_5'] = 'Account is an &quot;under age&quot; account awaiting approval';
$txt['account_not_activated'] = 'Account is currently not activated';
$txt['account_activate'] = 'activate';
$txt['account_approve'] = 'approve';
$txt['user_is_banned'] = 'User is currently banned';
$txt['view_ban'] = 'View';
$txt['user_banned_by_following'] = 'This user is currently affected by the following bans';
$txt['user_cannot_due_to'] = 'User cannot %1$s as a result of ban: &quot;%2$s&quot;';
$txt['ban_type_post'] = 'post';
$txt['ban_type_register'] = 'register';
$txt['ban_type_login'] = 'login';
$txt['ban_type_access'] = 'access forum';

$txt['show_online'] = 'Show others my online status';

$txt['return_to_post'] = 'Return to topics after posting by default.';
$txt['posts_apply_ignore_list'] = 'Hide messages posted by members on my ignore list.';
$txt['recent_posts_at_top'] = 'Show most recent posts at the top in topic view.';
$txt['recent_pms_at_top'] = 'Show most recent personal messages at top.';
$txt['wysiwyg_default'] = 'Show WYSIWYG editor on post page by default.';

$txt['timeformat_default'] = '(Forum Default)';
$txt['timeformat_easy1'] = 'Month Day, Year, HH:MM:SS am/pm';
$txt['timeformat_easy2'] = 'Month Day, Year, HH:MM:SS (24 hour)';
$txt['timeformat_easy3'] = 'YYYY-MM-DD, HH:MM:SS';
$txt['timeformat_easy4'] = 'DD Month YYYY, HH:MM:SS';
$txt['timeformat_easy5'] = 'DD-MM-YYYY, HH:MM:SS';

$txt['poster'] = 'Poster';

$txt['show_children'] = 'Show sub-boards on every page inside boards, not just the first.';
$txt['show_no_avatars'] = 'Don\'t show users\' avatars.';
$txt['show_no_signatures'] = 'Don\'t show users\' signatures.';
$txt['show_no_censored'] = 'Leave words uncensored.';
$txt['topics_per_page'] = 'Topics to display per page:';
$txt['messages_per_page'] = 'Messages to display per page:';
$txt['per_page_default'] = 'forum default';
$txt['calendar_start_day'] = 'First day of the week on the calendar';
$txt['use_editor_quick_reply'] = 'Use full editor in Quick Reply';
$txt['display_quick_mod'] = 'Show quick-moderation as';
$txt['display_quick_mod_none'] = 'don\'t show';
$txt['display_quick_mod_check'] = 'checkboxes';
$txt['display_quick_mod_image'] = 'icons';

$txt['whois_title'] = 'Look up IP on a regional whois-server';
$txt['whois_afrinic'] = 'AfriNIC (Africa)';
$txt['whois_apnic'] = 'APNIC (Asia Pacific region)';
$txt['whois_arin'] = 'ARIN (North America, a portion of the Caribbean and sub-Saharan Africa)';
$txt['whois_lacnic'] = 'LACNIC (Latin American and Caribbean region)';
$txt['whois_ripe'] = 'RIPE (Europe, the Middle East and parts of Africa and Asia)';

$txt['moderator_why_missing'] = 'why isn\'t moderator here?';
$txt['username_change'] = 'change';
$txt['username_warning'] = 'To change this member\'s username, the forum must also reset their password, which will be emailed to the member with their new username.';

$txt['show_member_posts'] = 'View Member Posts';
$txt['show_member_topics'] = 'View Member Topics';
$txt['show_member_attachments'] = 'View Member Attachments';
$txt['show_posts_none'] = 'No posts have been posted yet.';
$txt['show_topics_none'] = 'No topics have been posted yet.';
$txt['unwatched_topics_none'] = 'You don\'t have any topic in the unwatched list.';
$txt['show_attachments_none'] = 'No attachments have been posted yet.';
$txt['show_attach_filename'] = 'Filename';
$txt['show_attach_downloads'] = 'Downloads';
$txt['show_attach_posted'] = 'Posted';

$txt['showPermissions'] = 'Show Permissions';
$txt['showPermissions_status'] = 'Permission status';
$txt['showPermissions_help'] = 'This section allows you to view all permissions for this member (denied permissions are <del>struck out</del>).';
$txt['showPermissions_given'] = 'Given by';
$txt['showPermissions_denied'] = 'Denied by';
$txt['showPermissions_permission'] = 'Permission (denied permissions are <del>struck out</del>)';
$txt['showPermissions_none_general'] = 'This member has no general permissions set.';
$txt['showPermissions_none_board'] = 'This member has no board specific permissions set.';
$txt['showPermissions_all'] = 'As an administrator, this member has all possible permissions.';
$txt['showPermissions_select'] = 'Board specific permissions for';
$txt['showPermissions_general'] = 'General Permissions';
$txt['showPermissions_global'] = 'All boards';
$txt['showPermissions_restricted_boards'] = 'Restricted boards';
$txt['showPermissions_restricted_boards_desc'] = 'The following boards are not accessible by this user';

$txt['local_time'] = 'Local Time';
$txt['posts_per_day'] = 'per day';

$txt['buddy_ignore_desc'] = 'This area allows you to maintain your buddy and ignore lists for this forum. Adding members to these lists will, amongst other things, help control mail and PM traffic, depending on your preferences.';

$txt['buddy_add'] = 'Add To Buddy List';
$txt['buddy_remove'] = 'Remove From Buddy List';
$txt['buddy_add_button'] = 'Add';
$txt['no_buddies'] = 'Your buddy list is currently empty';

$txt['ignore_add'] = 'Add To Ignore List';
$txt['ignore_remove'] = 'Remove From Ignore List';
$txt['ignore_add_button'] = 'Add';
$txt['no_ignore'] = 'Your ignore list is currently empty';

$txt['regular_members'] = 'Registered Members';
$txt['regular_members_desc'] = 'Every member of the forum, without a different badge or title, is a member of this group.';
$txt['group_membership_msg_free'] = 'Your group membership was successfully updated.';
$txt['group_membership_msg_request'] = 'Your request has been submitted, please be patient while the request is considered.';
$txt['group_membership_msg_primary'] = 'Your primary group has been updated';
$txt['current_membergroups'] = 'Current Membergroups';
$txt['available_groups'] = 'Available Groups';
$txt['join_group'] = 'Join Group';
$txt['leave_group'] = 'Leave Group';
$txt['request_group'] = 'Request Membership';
$txt['approval_pending'] = 'Approval Pending';
$txt['make_primary'] = 'Make Primary Group';

$txt['request_group_membership'] = 'Request Group Membership';
$txt['request_group_membership_desc'] = 'Before you can join this group your membership must be approved by the moderator. Please give a reason for joining this group';
$txt['submit_request'] = 'Submit Request';

$txt['profile_updated_own'] = 'Your profile has been updated successfully.';
$txt['profile_updated_else'] = 'The profile of %1$s has been updated successfully.';

$txt['profile_error_signature_max_length'] = 'Your signature cannot be greater than %1$d characters';
$txt['profile_error_signature_max_lines'] = 'Your signature cannot span more than %1$d lines';
$txt['profile_error_signature_max_image_size'] = 'Images in your signature must be no greater than %1$dx%2$d pixels';
$txt['profile_error_signature_max_image_width'] = 'Images in your signature must be no wider than %1$d pixels';
$txt['profile_error_signature_max_image_height'] = 'Images in your signature must be no higher than %1$d pixels';
$txt['profile_error_signature_max_image_count'] = 'You cannot have more than %1$d images in your signature';
$txt['profile_error_signature_max_font_size'] = 'Text in your signature must be smaller than %1$s in size';
$txt['profile_error_signature_allow_smileys'] = 'You are not allowed to use any smileys within your signature';
$txt['profile_error_signature_max_smileys'] = 'You are not allowed to use more than %1$d smileys within your signature';
$txt['profile_error_signature_disabled_bbc'] = 'The following BBC is not allowed within your signature: %1$s';

$txt['profile_view_warnings'] = 'View Warnings';
$txt['profile_issue_warning'] = 'Issue a Warning';
$txt['profile_warning_level'] = 'Warning Level';
$txt['profile_warning_desc'] = 'From this section you can adjust the user\'s warning level and issue them with a written warning if necessary. You can also track their warning history and view the effects of their current warning level as determined by the administrator.';
$txt['profile_warning_name'] = 'Member Name';
$txt['profile_warning_impact'] = 'Result';
$txt['profile_warning_reason'] = 'Reason for Warning';
$txt['profile_warning_reason_desc'] = 'This is required and will be logged.';
$txt['profile_warning_effect_none'] = 'None.';
$txt['profile_warning_effect_watch'] = 'User will be added to moderator watch list.';
$txt['profile_warning_effect_own_watched'] = 'You are on the moderator watch list.';
$txt['profile_warning_is_watch'] = 'being watched';
$txt['profile_warning_effect_moderation'] = 'All users posts will be moderated.';
$txt['profile_warning_effect_own_moderated'] = 'All your posts will be moderated.';
$txt['profile_warning_is_moderation'] = 'posts are moderated';
$txt['profile_warning_effect_mute'] = 'User will not be able to post.';
$txt['profile_warning_effect_own_muted'] = 'You will not be able to post.';
$txt['profile_warning_is_muted'] = 'cannot post';
$txt['profile_warning_effect_text'] = 'Level >= %1$d: %2$s';
$txt['profile_warning_notify'] = 'Send a Notification';
$txt['profile_warning_notify_template'] = 'Select template:';
$txt['profile_warning_notify_subject'] = 'Notification Subject';
$txt['profile_warning_notify_body'] = 'Notification Message';
$txt['profile_warning_notify_template_subject'] = 'You have received a warning';
// Use numeric entities in below string.
$txt['profile_warning_notify_template_outline'] = '{MEMBER},' . "\n\n" . 'You have received a warning for %1$s. Please cease these activities and abide by the forum rules otherwise we will take further action.' . "\n\n" . '{REGARDS}';
$txt['profile_warning_notify_template_outline_post'] = '{MEMBER},' . "\n\n" . 'You have received a warning for %1$s in regards to the message:' . "\n" . '{MESSAGE}.' . "\n\n" . 'Please cease these activities and abide by the forum rules otherwise we will take further action.' . "\n\n" . '{REGARDS}';
$txt['profile_warning_notify_for_spamming'] = 'spamming';
$txt['profile_warning_notify_title_spamming'] = 'Spamming';
$txt['profile_warning_notify_for_offence'] = 'posting offensive material';
$txt['profile_warning_notify_title_offence'] = 'Posting Offensive Material';
$txt['profile_warning_notify_for_insulting'] = 'insulting other users and/or staff members';
$txt['profile_warning_notify_title_insulting'] = 'Insulting Users/Staff';
$txt['profile_warning_issue'] = 'Issue Warning';
$txt['profile_warning_max'] = '(Max 100)';
$txt['profile_warning_limit_attribute'] = 'Note you can not adjust this user\'s level by more than %1$d%% in a 24 hour period.';
$txt['profile_warning_errors_occured'] = 'Warning has not been sent due to following errors';
$txt['profile_warning_success'] = 'Warning Successfully Issued';
$txt['profile_warning_new_template'] = 'New Template';

$txt['profile_warning_previous'] = 'Previous Warnings';
$txt['profile_warning_previous_none'] = 'This user has not received any previous warnings.';
$txt['profile_warning_previous_issued'] = 'Issued By';
$txt['profile_warning_previous_time'] = 'Time';
$txt['profile_warning_previous_level'] = 'Points';
$txt['profile_warning_previous_reason'] = 'Reason';
$txt['profile_warning_previous_notice'] = 'View Notice Sent to Member';

$txt['viewwarning'] = 'View Warnings';
$txt['profile_viewwarning_for_user'] = 'Warnings for %1$s';
$txt['profile_viewwarning_no_warnings'] = 'No warnings have been issued.';
$txt['profile_viewwarning_desc'] = 'Below is a summary of all the warnings that have been issued by the forum moderation team.';
$txt['profile_viewwarning_previous_warnings'] = 'Previous Warnings';
$txt['profile_viewwarning_impact'] = 'Warning Impact';

$txt['subscriptions'] = 'Paid Subscriptions';

$txt['pm_settings_desc'] = 'From this page you can change a variety of personal messaging options, including how messages are displayed and who may send them to you.';
$txt['email_notify'] = 'Notify by email every time you receive a personal message:';
$txt['email_notify_buddies'] = 'Buddies Only';
$txt['email_notify_all'] = 'All members';

$txt['pm_receive_from'] = 'Receive personal messages from:';
$txt['pm_receive_from_everyone'] = 'All members';
$txt['pm_receive_from_ignore'] = 'All members, except those on my ignore list';
$txt['pm_receive_from_admins'] = 'Administrators only';
$txt['pm_receive_from_buddies'] = 'Buddies and Administrators only';

$txt['popup_messages'] = 'Show a popup when I receive new messages.';
$txt['pm_remove_inbox_label'] = 'Remove the inbox label when applying another label';
$txt['pm_display_mode'] = 'Display personal messages';
$txt['pm_display_mode_all'] = 'All at once';
$txt['pm_display_mode_one'] = 'One at a time';
$txt['pm_display_mode_linked'] = 'As a conversation';

$txt['tracking'] = 'Tracking';
$txt['tracking_description'] = 'This section allows you to review certain profile actions performed on this member\'s profile as well as track their IP address and login history.';

$txt['trackEdits'] = 'Profile Edits';
$txt['trackEdit_deleted_member'] = 'Deleted Member';
$txt['trackEdit_no_edits'] = 'No edits have so far been recorded for this member.';
$txt['trackEdit_action'] = 'Field';
$txt['trackEdit_before'] = 'Value Before';
$txt['trackEdit_after'] = 'Value After';
$txt['trackEdit_applicator'] = 'Changed By';

$txt['trackEdit_action_real_name'] = 'Member Name';
$txt['trackEdit_action_usertitle'] = 'Custom Title';
$txt['trackEdit_action_member_name'] = 'Username';
$txt['trackEdit_action_email_address'] = 'Email Address';
$txt['trackEdit_action_id_group'] = 'Primary Membergroup';
$txt['trackEdit_action_additional_groups'] = 'Additional Membergroups';

$txt['trackGroupRequests'] = 'Group Requests';
$txt['trackGroupRequests_title'] = 'Group Requests for %1$s';
$txt['requested_group'] = 'Requested Group';
$txt['requested_group_reason'] = 'Reason Given';
$txt['requested_group_time'] = 'Date';
$txt['requested_group_outcome'] = 'Outcome';
$txt['requested_none'] = 'There are no requests made by this user.';
$txt['outcome_pending'] = 'Open';
$txt['outcome_approved'] = 'Approved by %1$s on %2$s';
$txt['outcome_refused'] = 'Refused by %1$s on %2$s';
$txt['outcome_refused_reason'] = 'Refused by %1$s on %2$s, reason given: %3$s';

$txt['report_profile'] = 'Report This Member';
$txt['notification_remove_pref'] = 'Use default preference';

$txt['tfa_profile_label'] = 'Two-Factor Authentication';
$txt['tfa_profile_desc'] = 'TFA allows you to have a secondary layer of security by assigning a dedicated device without which no one would be able to log into your account even if they have your username and password';
$txt['tfa_profile_enable'] = 'Enable Two-Factor Authentication';
$txt['tfa_profile_enabled'] = 'Two-Factor Authentication is enabled. <a href="%s">Disable</a>';
$txt['tfa_profile_disabled'] = 'Two-Factor Authentication is disabled';
$txt['tfa_title'] = 'Enable Two-Factor Authentication via compatible application';
$txt['tfa_desc'] = 'In order to have Two-Factor Authentication, you would need a compatible app such as Google Authenticator on your device. Once you have enabled 2FA for your account, you will be required to enter a code on login via the paired device alongside your username and password in order to successfully login. After you have enabled 2FA, a backup code will be provided should you lose your paired device.';
$txt['tfa_forced_desc'] = 'Administrator has forced 2FA to be enabled on all accounts, please enable 2FA here in order to resume';
$txt['tfa_step1'] = '1. Enter your current password';
$txt['tfa_step2'] = '2. Enter the secret';
$txt['tfa_step2_desc'] = 'In order to setup the app, either scan the QR code on the right side or enter the following code manually: ';
$txt['tfa_step3'] = '3. Enter the code generated by the app';
$txt['tfa_enable'] = 'Enable';
$txt['tfa_pass_invalid'] = 'Entered password is invalid, please try again';
$txt['tfa_code_invalid'] = 'Entered code is invalid, please try again';
$txt['tfa_backup_invalid'] = 'Entered backup code is invalid, please try again';
$txt['tfa_backup_title'] = 'Save this Two-Factor Authentication Backup code somewhere safe!';
$txt['tfa_backup_desc'] = 'If you lose your device, this code can be used to login again. You will not be able to see this code again, please save it somewhere secure now';
$txt['tfa_backup_used_desc'] = 'Your backup code has been successfully entered and 2FA details have been reset, if you wish to use 2FA again you need to enable it from here';
$txt['tfa_login_desc'] = 'Enter code generated by authenticating application from your paired device below';
$txt['tfa_backup'] = 'Or use backup code';
$txt['tfa_code'] = 'Code';
$txt['tfa_backup_code'] = 'Backup code';
$txt['tfa_backup_desc'] = 'In case you have lost your device or authentication app, you can use the backup code provided to you when 2FA was setup. In case you have lost that as well, please contact the administrator';
$txt['tfa_wait'] = 'Please wait for about 2 minutes before attempting to log in via 2FA again';
?>
