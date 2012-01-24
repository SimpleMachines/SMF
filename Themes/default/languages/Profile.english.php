<?php
// Version: 2.0; Profile

global $scripturl, $context;

$txt['no_profile_edit'] = 'You are not allowed to change this person\'s profile.';
$txt['website_title'] = 'Website title';
$txt['website_url'] = 'Website URL';
$txt['signature'] = 'Signature';
$txt['profile_posts'] = 'Posts';
$txt['change_profile'] = 'Change profile';
$txt['delete_user'] = 'Delete user';
$txt['current_status'] = 'Current Status:';
$txt['personal_text'] = 'Personal Text';
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
$txt['avatar_by_url'] = 'Specify your own avatar by URL. (e.g.: <em>http://www.mypage.com/mypic.gif</em>)';
$txt['my_own_pic'] = 'Specify avatar by URL';
$txt['date_format'] = 'The format here will be used to show dates throughout this forum.';
$txt['time_format'] = 'Time Format';
$txt['display_name_desc'] = 'This is the displayed name that people will see.';
$txt['personal_time_offset'] = 'Number of hours to +/- to make displayed time equal to your local time.';
$txt['dob'] = 'Birthdate';
$txt['dob_month'] = 'Month (MM)';
$txt['dob_day'] = 'Day (DD)';
$txt['dob_year'] = 'Year (YYYY)';
$txt['password_strength'] = 'For best security, you should use eight or more characters with a combination of letters, numbers, and symbols.';
$txt['include_website_url'] = 'This must be included if you specify a URL below.';
$txt['complete_url'] = 'This must be a complete URL.';
$txt['your_icq'] = 'This is your ICQ number.';
$txt['your_aim'] = 'This is your AOL Instant Messenger nickname.';
$txt['your_yim'] = 'This is your Yahoo! Instant Messenger nickname.';
$txt['sig_info'] = 'Signatures are displayed at the bottom of each post or personal message. BBCode and smileys may be used in your signature.';
$txt['max_sig_characters'] = 'Max characters: %1$d; characters remaining: ';
$txt['send_member_pm'] = 'Send this member a personal message';
$txt['hidden'] = 'hidden';
$txt['current_time'] = 'Current forum time';
$txt['digits_only'] = 'The \'number of posts\' box can only contain digits.';

$txt['language'] = 'Language';
$txt['avatar_too_big'] = 'Avatar image is too big, please resize it and try again (max';
$txt['invalid_registration'] = 'Invalid Date Registered value, valid example:';
$txt['msn_email_address'] = 'Your MSN messenger email address';
$txt['current_password'] = 'Current Password';
// Don't use entities in the below string, except the main ones. (lt, gt, quot.)
$txt['required_security_reasons'] = 'For security reasons, your current password is required to make changes to your account.';

$txt['timeoffset_autodetect'] = '(auto detect)';

$txt['secret_question'] = 'Secret Question';
$txt['secret_desc'] = 'To help retrieve your password, enter a question here with an answer that <strong>only</strong> you know.';
$txt['secret_desc2'] = 'Choose carefully, you wouldn\'t want someone guessing your answer!';
$txt['secret_answer'] = 'Answer';
$txt['secret_ask'] = 'Ask me my question';
$txt['cant_retrieve'] = 'You can\'t retrieve your password, but you can set a new one by following a link sent to you by email.  You also have the option of setting a new password by answering your secret question.';
$txt['incorrect_answer'] = 'Sorry, but you did not specify a valid combination of Secret Question and Answer in your profile.  Please click on the back button, and use the default method of obtaining your password.';
$txt['enter_new_password'] = 'Please enter the answer to your question, and the password you would like to use.  Your password will be changed to the one you select provided you answer the question correctly.';
$txt['password_success'] = 'Your password was changed successfully.<br />Click <a href="' . $scripturl . '?action=login">here</a> to login.';
$txt['secret_why_blank'] = 'why is this blank?';

$txt['authentication_reminder'] = 'Authentication Reminder';
$txt['password_reminder_desc'] = 'If you\'ve forgotten your login details, don\'t worry, they can be retrieved. To start this process please enter your username or email address below.';
$txt['authentication_options'] = 'Please select one of the two options below';
$txt['authentication_openid_email'] = 'Email me a reminder of my OpenID identity';
$txt['authentication_openid_secret'] = 'Answer my &quot;secret question&quot; to display my OpenID identity';
$txt['authentication_password_email'] = 'Email me a new password';
$txt['authentication_password_secret'] = 'Let me set a new password by answering my &quot;secret question&quot;';
$txt['openid_secret_reminder'] = 'Please enter your answer to the question below. If you get it correct your OpenID identity will be shown.';
$txt['reminder_openid_is'] = 'The OpenID identity associated with your account is:<br />&nbsp;&nbsp;&nbsp;&nbsp;<strong>%1$s</strong><br /><br />Please make a note of this for future reference.';
$txt['reminder_continue'] = 'Continue';

$txt['current_theme'] = 'Current Theme';
$txt['change'] = '(change)';
$txt['theme_preferences'] = 'Theme preferences';
$txt['theme_forum_default'] = 'Forum or Board Default';
$txt['theme_forum_default_desc'] = 'This is the default theme, which means your theme will change along with the administrator\'s settings and the board you are viewing.';

$txt['profileConfirm'] = 'Do you really want to delete this member?';

$txt['custom_title'] = 'Custom Title';

$txt['lastLoggedIn'] = 'Last Active';

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
$txt['notify_send_type_everything_own'] = 'Moderation only if I started the topic';
$txt['notify_send_type_only_replies'] = 'Only replies';
$txt['notify_send_type_nothing'] = 'Nothing at all';
$txt['notify_send_body'] = 'When sending notification of a reply to a topic, send the post in the email (but please don\'t reply to these emails.)';

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
$txt['deleteAccount_none'] = 'None';
$txt['deleteAccount_all_posts'] = 'All Posts';
$txt['deleteAccount_topics'] = 'Topics and Posts';
$txt['deleteAccount_confirm'] = 'Are you completely sure you want to delete this account?';
$txt['deleteAccount_approval'] = 'Please note that the forum moderators will have to approve this account\'s deletion before it will be removed.';

$txt['profile_of_username'] = 'Profile of %1$s';
$txt['profileInfo'] = 'Profile Info';
$txt['showPosts'] = 'Show Posts';
$txt['showPosts_help'] = 'This section allows you to view all posts made by this member. Note that you can only see posts made in areas you currently have access to.';
$txt['showMessages'] = 'Messages';
$txt['showTopics'] = 'Topics';
$txt['showAttachments'] = 'Attachments';
$txt['statPanel'] = 'Show Stats';
$txt['editBuddyIgnoreLists'] = 'Buddies/Ignore List';
$txt['editBuddies'] = 'Edit Buddies';
$txt['editIgnoreList'] = 'Edit Ignore List';
$txt['trackUser'] = 'Track User';
$txt['trackActivity'] = 'Activity';
$txt['trackIP'] = 'IP Address';

$txt['authentication'] = 'Authentication';
$txt['change_authentication'] = 'From this section you can change how you login to the forum. You may choose to either use an OpenID account for your authentication, or alternatively switch to use a username and password.';

$txt['profileEdit'] = 'Modify Profile';
$txt['account_info'] = 'These are your account settings. This page holds all critical information that identifies you on this forum. For security reasons, you will need to enter your (current) password to make changes to this information.';
$txt['forumProfile_info'] = 'You can change your personal information on this page. This information will be displayed throughout ' . $context['forum_name_html_safe'] . '. If you aren\'t comfortable with sharing some information, simply skip it - nothing here is required.';
$txt['theme'] = 'Look and Layout';
$txt['theme_info'] = 'This section allows you to customize the look and layout of the forum.';
$txt['notification'] = 'Notifications';
$txt['notification_info'] = 'SMF allows you to be notified of replies to posts, newly posted topics, and forum announcements. You can change those settings here, or oversee the topics and boards you are currently receiving notifications for.';
$txt['groupmembership'] = 'Group Membership';
$txt['groupMembership_info'] = 'In this section of your profile you can change which groups you belong to.';
$txt['ignoreboards'] = 'Ignore Boards Options';
$txt['ignoreboards_info'] = 'This page lets you ignore particular boards.  When a board is ignored, the new post indicator will not show up on the board index.  New posts will not show up using the "unread post" search link (when searching it will not look in those boards) however, ignored boards will still appear on the board index and upon entering will show which topics have new posts.  When using the "unread replies" link, new posts in an ignored board will still be shown.';
$txt['pmprefs'] = 'Personal Messaging';

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
$txt['most_recent_ip'] = 'Most recent IP address';
$txt['why_two_ip_address'] = 'Why are there two IP addresses listed?';
$txt['no_errors_from_ip'] = 'No error messages from the specified IP (range) found';
$txt['no_errors_from_user'] = 'No error messages from the specified user found';
$txt['no_members_from_ip'] = 'No members from the specified IP (range) found';
$txt['no_messages_from_ip'] = 'No messages from the specified IP (range) found';
$txt['none'] = 'None';
$txt['own_profile_confirm'] = 'Are you sure you want to delete your account?';
$txt['view_ips_by'] = 'View IPs used by';

$txt['avatar_will_upload'] = 'Upload an avatar';

$txt['activate_changed_email_title'] = 'Email Address Changed';
$txt['activate_changed_email_desc'] = 'You\'ve changed your email address. In order to validate this address you will receive an email. Click the link in that email to reactivate your account.';

// Use numeric entities in the below three strings.
$txt['no_reminder_email'] = 'Unable to send reminder email.';
$txt['send_email'] = 'Send an email to';
$txt['to_ask_password'] = 'to ask for your authentication details';

$txt['user_email'] = 'Username/Email';

// Use numeric entities in the below two strings.
$txt['reminder_subject'] = 'New password for ' . $context['forum_name'];
$txt['reminder_mail'] = 'This mail was sent because the \'forgot password\' function has been applied to your account. To set a new password, click the following link';
$txt['reminder_sent'] = 'A mail has been sent to your email address. Click the link in that mail to set a new password.';
$txt['reminder_openid_sent'] = 'Your current OpenID identity has been sent to your email address.';
$txt['reminder_set_password'] = 'Set Password';
$txt['reminder_password_set'] = 'Password successfully set';
$txt['reminder_error'] = '%1$s failed to answer their secret question correctly when attempting to change a forgotten password.';

$txt['registration_not_approved'] = 'Sorry, this account has not yet been approved. If you need to change your email address please click';
$txt['registration_not_activated'] = 'Sorry, this account has not yet been activated. If you need to resend the activation email please click';

$txt['primary_membergroup'] = 'Primary Membergroup';
$txt['additional_membergroups'] = 'Additional Membergroups';
$txt['additional_membergroups_show'] = '[ show additional groups ]';
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
$txt['no_new_reply_warning'] = 'Don\'t warn on new replies made while posting.';
$txt['posts_apply_ignore_list'] = 'Hide messages posted by members on my ignore list.';
$txt['recent_posts_at_top'] = 'Show most recent posts at the top.';
$txt['recent_pms_at_top'] = 'Show most recent personal messages at top.';
$txt['wysiwyg_default'] = 'Show WYSIWYG editor on post page by default.';

$txt['timeformat_default'] = '(Forum Default)';
$txt['timeformat_easy1'] = 'Month Day, Year, HH:MM:SS am/pm';
$txt['timeformat_easy2'] = 'Month Day, Year, HH:MM:SS (24 hour)';
$txt['timeformat_easy3'] = 'YYYY-MM-DD, HH:MM:SS';
$txt['timeformat_easy4'] = 'DD Month YYYY, HH:MM:SS';
$txt['timeformat_easy5'] = 'DD-MM-YYYY, HH:MM:SS';

$txt['poster'] = 'Poster';

$txt['board_desc_inside'] = 'Show board descriptions inside boards.';
$txt['show_children'] = 'Show child boards on every page inside boards, not just the first.';
$txt['use_sidebar_menu'] = 'Use sidebar menus instead of dropdown menus when possible.';
$txt['show_no_avatars'] = 'Don\'t show users\' avatars.';
$txt['show_no_signatures'] = 'Don\'t show users\' signatures.';
$txt['show_no_censored'] = 'Leave words uncensored.';
$txt['topics_per_page'] = 'Topics to display per page:';
$txt['messages_per_page'] = 'Messages to display per page:';
$txt['per_page_default'] = 'forum default';
$txt['calendar_start_day'] = 'First day of the week on the calendar';
$txt['display_quick_reply'] = 'Use quick reply on topic display: ';
$txt['display_quick_reply1'] = 'don\'t show at all';
$txt['display_quick_reply2'] = 'show, off by default';
$txt['display_quick_reply3'] = 'show, on by default';
$txt['display_quick_mod'] = 'Show quick-moderation as ';
$txt['display_quick_mod_none'] = 'don\'t show.';
$txt['display_quick_mod_check'] = 'checkboxes.';
$txt['display_quick_mod_image'] = 'icons.';

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
$txt['regular_members_desc'] = 'Every member of the forum is a member of this group.';
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
$txt['profile_updated_else'] = 'The profile for %1$s has been updated successfully.';

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
$txt['profile_viewwarning_no_warnings'] = 'No warnings have yet been issued.';
$txt['profile_viewwarning_desc'] = 'Below is a summary of all the warnings that have been issued by the forum moderation team.';
$txt['profile_viewwarning_previous_warnings'] = 'Previous Warnings';
$txt['profile_viewwarning_impact'] = 'Warning Impact';

$txt['subscriptions'] = 'Paid Subscriptions';

$txt['pm_settings_desc'] = 'From this page you can change a variety of personal messaging options, including how messages are displayed and who may send them to you.';
$txt['email_notify'] = 'Notify by email every time you receive a personal message:';
$txt['email_notify_never'] = 'Never';
$txt['email_notify_buddies'] = 'From Buddies Only';
$txt['email_notify_always'] = 'Always';

$txt['pm_receive_from'] = 'Receive personal messages from:';
$txt['pm_receive_from_everyone'] = 'All members';
$txt['pm_receive_from_ignore'] = 'All members, except those on my ignore list';
$txt['pm_receive_from_admins'] = 'Administrators only';
$txt['pm_receive_from_buddies'] = 'Buddies and Administrators only';

$txt['copy_to_outbox'] = 'Save a copy of each personal message in my sent items by default.';
$txt['popup_messages'] = 'Show a popup when I receive new messages.';
$txt['pm_remove_inbox_label'] = 'Remove the inbox label when applying another label';
$txt['pm_display_mode'] = 'Display personal messages';
$txt['pm_display_mode_all'] = 'All at once';
$txt['pm_display_mode_one'] = 'One at a time';
$txt['pm_display_mode_linked'] = 'As a conversation';
// Use entities in the below string.
$txt['pm_recommend_enable_outbox'] = 'To make the most of this setting we suggest you enable &quot;Save a copy of each Personal Message in my sent items by default&quot;\\n\\nThis will help ensure that the conversations flow better as you can see both sides of the conversation.';

$txt['tracking'] = 'Tracking';
$txt['tracking_description'] = 'This section allows you to review certain profile actions performed on this member\'s profile as well as track their IP address.';

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

?>