<?php
// Version: 2.1 RC2; Errors

global $scripturl, $modSettings;

$txt['no_access'] = 'You are not allowed to access this section';
$txt['not_found'] = 'Sorry, this section isn\'t available at this time.';

$txt['mods_only'] = 'Only Moderators can use the direct remove function, please remove this message through the modify feature.';
$txt['no_name'] = 'You didn\'t fill the name field out. It is required.';
$txt['no_email'] = 'You didn\'t fill the email field out. It is required.';
$txt['topic_locked'] = 'This topic is locked, you are not allowed to post or modify messages.';
$txt['no_password'] = 'Password field empty';
$txt['already_a_user'] = 'The username you tried to use already exists.';
$txt['cant_move'] = 'You are not allowed to move topics.';
$txt['login_to_post'] = 'To post you must be logged in. If you don\'t have an account yet, please <a href="' . $scripturl . '?action=signup">sign up</a>.';
$txt['passwords_dont_match'] = 'Passwords aren\'t the same.';
$txt['register_to_use'] = 'Sorry, you must sign up before using this feature.';
$txt['password_invalid_character'] = 'Invalid character used in password.';
$txt['name_invalid_character'] = 'Invalid character used in name.';
$txt['email_invalid_character'] = 'Invalid character used in email.';
$txt['username_reserved'] = 'The username you tried to use contains the reserved name \'%1$s\'. Please try another username.';
$txt['numbers_one_to_nine'] = 'This field only accepts numbers from 0-9';
$txt['not_a_user'] = 'The user whose profile you are trying to view does not exist.';
$txt['not_a_topic'] = 'This topic doesn\'t exist on this board.';
$txt['email_in_use'] = 'That email address (%1$s) is already being used by a registered member. If you feel this is a mistake, go to the login page and use the password reminder with that address.';
$txt['attachments_no_write'] = 'The attachments directory is not writable';
$txt['attachment_not_created'] = 'The attachment could not be created';

$txt['didnt_select_vote'] = 'You didn\'t select a vote option.';
$txt['poll_error'] = 'Either that poll doesn\'t exist, the poll has been locked, or you tried to vote twice.';
$txt['members_only'] = 'This option is only available to registered members.';
$txt['locked_by_admin'] = 'This was locked by an administrator. You cannot unlock it.';
$txt['feature_disabled'] = 'Sorry, this feature is disabled.';
$txt['feature_no_exists'] = 'Sorry, this feature doesn\'t exist.';
$txt['couldnt_connect'] = 'Could not connect to server or could not find file';
$txt['no_board'] = 'The board you specified doesn\'t exist';
$txt['no_message'] = 'The message is no longer available';
$txt['cant_split'] = 'You are not allowed to split topics';
$txt['cant_merge'] = 'You are not allowed to merge topics';
$txt['no_topic_id'] = 'You specified an invalid topic ID.';
$txt['split_first_post'] = 'The first post of a topic cannot be split.';
$txt['topic_one_post'] = 'This topic only contains one message and cannot be split.';
$txt['no_posts_selected'] = 'No messages selected';
$txt['selected_all_posts'] = 'Unable to split. You have selected every message.';
$txt['cant_find_messages'] = 'Unable to find messages';
$txt['cant_find_user_email'] = 'Unable to find user\'s email address.';
$txt['cant_insert_topic'] = 'Unable to insert topic';
$txt['already_a_mod'] = 'You have chosen a username of an already existing moderator. Please choose another username';
$txt['session_timeout'] = 'Your session timed out while posting. Please go back and try again.';
$txt['session_verify_fail'] = 'Session verification failed. Please try logging out and back in again, and then try again.';
$txt['verify_url_fail'] = 'Unable to verify referring URL. Please go back and try again.';
$txt['token_verify_fail'] = 'Token verification failed. Please go back and try again.';
$txt['guest_vote_disabled'] = 'Guests cannot vote in this poll.';

$txt['cannot_like_content'] = 'You are not able to like that content.';
$txt['cannot_view_likes'] = 'You are not able to view who liked that content.';
$txt['cannot_access_mod_center'] = 'You do not have permission to access the moderation center.';
$txt['cannot_admin_forum'] = 'You are not allowed to administrate this forum.';
$txt['cannot_announce_topic'] = 'You are not allowed to announce topics on this board.';
$txt['cannot_approve_posts'] = 'You do not have permission to approve items.';
$txt['cannot_post_unapproved_attachments'] = 'You do not have permission to post unapproved attachments.';
$txt['cannot_post_unapproved_topics'] = 'You do not have permission to post unapproved topics.';
$txt['cannot_post_unapproved_replies_own'] = 'You do not have permission to post unapproved replies to your topics.';
$txt['cannot_post_unapproved_replies_any'] = 'You do not have permission to post unapproved replies to other users\' topics.';
$txt['cannot_calendar_edit_any'] = 'You cannot edit calendar events.';
$txt['cannot_calendar_edit_own'] = 'You don\'t have the privileges necessary to edit your own events.';
$txt['cannot_calendar_post'] = 'Event posting isn\'t allowed - sorry.';
$txt['cannot_calendar_view'] = 'Sorry, but you are not allowed to view the calendar.';
$txt['cannot_remove_any'] = 'Sorry, but you don\'t have the privilege to remove this topic. Check to make sure this topic wasn\'t just moved to another board.';
$txt['cannot_remove_own'] = 'You cannot delete your own topics in this board. Check to make sure this topic wasn\'t just moved to another board.';
$txt['cannot_edit_news'] = 'You are not allowed to edit news items on this forum.';
$txt['cannot_pm_read'] = 'Sorry, you can\'t read your personal messages.';
$txt['cannot_pm_send'] = 'You are not allowed to send personal messages.';
$txt['cannot_lock_any'] = 'You are not allowed to lock this topic.';
$txt['cannot_lock_own'] = 'Apologies, but you cannot lock your own topics here.';
$txt['cannot_make_sticky'] = 'You don\'t have permission to sticky this topic.';
$txt['cannot_manage_attachments'] = 'You\'re not allowed to manage attachments or avatars.';
$txt['cannot_manage_bans'] = 'You\'re not allowed to change the list of bans.';
$txt['cannot_manage_boards'] = 'You are not allowed to manage boards and categories.';
$txt['cannot_manage_membergroups'] = 'You don\'t have permission to modify or assign membergroups.';
$txt['cannot_manage_permissions'] = 'You don\'t have permission to manage permissions.';
$txt['cannot_manage_smileys'] = 'You\'re not allowed to manage smileys and message icons.';
$txt['cannot_merge_any'] = 'You aren\'t allowed to merge topics on one of the selected board(s).';
$txt['cannot_merge_redirect'] = 'One or more of the topics you have selected is a redirect topic and cannot be merged.';
$txt['cannot_moderate_forum'] = 'You are not allowed to moderate this forum.';
$txt['cannot_moderate_board'] = 'You are not allowed to moderate this board.';
$txt['cannot_modify_any'] = 'You aren\'t allowed to modify posts.';
$txt['cannot_modify_own'] = 'Sorry, but you aren\'t allowed to edit your own posts.';
$txt['cannot_modify_replies'] = 'Even though this post is a reply to your topic, you cannot edit it.';
$txt['cannot_move_own'] = 'You are not allowed to move your own topics in this board.';
$txt['cannot_move_any'] = 'You are not allowed to move topics in this board.';
$txt['cannot_poll_add_own'] = 'Sorry, you aren\'t allowed to add polls to your own topics in this board.';
$txt['cannot_poll_add_any'] = 'You don\'t have the access to add polls to this topic.';
$txt['cannot_poll_edit_own'] = 'You cannot edit this poll, even though it is your own.';
$txt['cannot_poll_edit_any'] = 'You have been denied access to editing polls in this board.';
$txt['cannot_poll_lock_own'] = 'You are not allowed to lock your own polls in this board.';
$txt['cannot_poll_lock_any'] = 'Sorry, but you aren\'t allowed to lock polls.';
$txt['cannot_poll_post'] = 'You aren\'t allowed to post polls in the current board.';
$txt['cannot_poll_remove_own'] = 'You are not permitted to remove this poll from your topic.';
$txt['cannot_poll_remove_any'] = 'You cannot remove polls on this board.';
$txt['cannot_poll_view'] = 'You are not allowed to view polls in this board.';
$txt['cannot_poll_vote'] = 'Sorry, but you cannot vote in polls in this board.';
$txt['cannot_post_attachment'] = 'You don\'t have permission to post attachments here.';
$txt['cannot_post_new'] = 'Sorry, you cannot post new topics in this board.';
$txt['cannot_post_reply_any'] = 'You are not permitted to post replies to topics on this board.';
$txt['cannot_post_reply_own'] = 'You are not allowed to post replies, even to your own topics, in this board.';
$txt['cannot_post_redirect'] = 'You cannot post in redirection boards.';
$txt['cannot_profile_remove_own'] = 'Sorry, but you aren\'t allowed to delete your own account.';
$txt['cannot_profile_remove_any'] = 'You don\'t have the appropriate permissions to remove accounts.';
$txt['cannot_profile_extra_any'] = 'You are not permitted to modify profile settings.';
$txt['cannot_profile_identity_any'] = 'You aren\'t allowed to edit account settings.';
$txt['cannot_profile_title_any'] = 'You cannot edit custom titles.';
$txt['cannot_profile_extra_own'] = 'Sorry, but you don\'t have the necessary permissions to edit your profile data.';
$txt['cannot_profile_identity_own'] = 'You can\'t change your identity at the moment.';
$txt['cannot_profile_title_own'] = 'You are not allowed to change your custom title.';
$txt['cannot_profile_server_avatar'] = 'You are not permitted to use a server stored avatar.';
$txt['cannot_profile_upload_avatar'] = 'You do not have permission to upload an avatar.';
$txt['cannot_profile_remote_avatar'] = 'You don\'t have the privilege of using a remote avatar.';
$txt['cannot_profile_view'] = 'Many apologies, but you can\'t view profiles.';
$txt['cannot_delete_own'] = 'You are not, on this board, allowed to delete your own posts.';
$txt['cannot_delete_replies'] = 'Sorry, but you cannot remove these posts, even though they are replies to your topic.';
$txt['cannot_delete_any'] = 'Deleting posts in this board is not allowed.';
$txt['cannot_report_any'] = 'You are not allowed to report posts in this board.';
$txt['cannot_search_posts'] = 'You are not allowed to search for posts in this forum.';
$txt['cannot_send_mail'] = 'You don\'t have the privilege of sending out emails to everyone.';
$txt['cannot_issue_warning'] = 'Sorry, you do not have permission to issue warnings to members.';
$txt['cannot_send_email_to_members'] = 'Sorry, but the administrator has disallowed sending emails on this board.';
$txt['cannot_split_any'] = 'Splitting topics is not allowed in this board.';
$txt['cannot_view_attachments'] = 'It seems that you are not allowed to download or view attachments on this board.';
$txt['cannot_view_mlist'] = 'You can\'t view the memberlist because you don\'t have permission to do so.';
$txt['cannot_view_stats'] = 'You aren\'t allowed to view the forum statistics.';
$txt['cannot_who_view'] = 'Sorry, you don\'t have the proper permissions to view the Who\'s Online list.';

$txt['no_theme'] = 'That theme does not exist.';
$txt['theme_dir_wrong'] = 'The default theme\'s directory is wrong, please correct it by clicking this text.';
$txt['registration_disabled'] = 'Sorry, registration is currently disabled.';
$txt['registration_agreement_missing'] = 'The registration agreement file, agreement.txt, is either missing or empty. Registrations have been disabled until this is fixed';
$txt['registration_no_secret_question'] = 'Sorry, there is no secret question set for this member.';
$txt['poll_range_error'] = 'Sorry, the poll must run for more than 0 days.';
$txt['delFirstPost'] = 'You are not allowed to delete the first post in a topic.<p>If you want to delete this topic, click on the Remove Topic link, or ask a moderator/administrator to do it for you.</p>';
$txt['parent_error'] = 'Unable to create board!';
$txt['login_cookie_error'] = 'You were unable to login. Please check your cookie settings.';
$txt['login_ssl_required'] = 'You can only login via HTTPS';
$txt['register_ssl_required'] = 'You can only register via HTTPS';
$txt['incorrect_answer'] = 'Sorry, but you did not answer your question correctly. Please click back to try again, or click back twice to use the default method of obtaining your password.';
$txt['no_mods'] = 'No moderators found!';
$txt['parent_not_found'] = 'Board structure corrupt: unable to find parent board';
$txt['modify_post_time_passed'] = 'You may not modify this post, as the time limit for edits has passed.';

$txt['calendar_off'] = 'You cannot access the calendar right now because it is disabled.';
$txt['calendar_export_off'] = 'You cannot export calendar events because that feature is currently disabled.';
$txt['invalid_month'] = 'Invalid month value.';
$txt['invalid_year'] = 'Invalid year value.';
$txt['invalid_day'] = 'Invalid day value.';
$txt['event_month_missing'] = 'Event month is missing.';
$txt['event_year_missing'] = 'Event year is missing.';
$txt['event_day_missing'] = 'Event day is missing.';
$txt['event_title_missing'] = 'Event title is missing.';
$txt['invalid_date'] = 'Invalid date.';
$txt['no_event_title'] = 'No event title was entered.';
$txt['missing_event_id'] = 'Missing event ID.';
$txt['cant_edit_event'] = 'You do not have permission to edit this event.';
$txt['missing_board_id'] = 'Board ID is missing.';
$txt['missing_topic_id'] = 'Topic ID is missing.';
$txt['topic_doesnt_exist'] = 'Topic doesn\'t exist.';
$txt['not_your_topic'] = 'You are not the owner of this topic.';
$txt['board_doesnt_exist'] = 'The board does not exist.';
$txt['invalid_days_numb'] = 'Invalid number of days to span.';

$txt['moveto_noboards'] = 'There are no boards to move this topic to!';
$txt['topic_already_moved'] = 'This topic %1$s has been moved to the board %2$s, please check its new location before moving it again.';

$txt['already_activated'] = 'Your account has already been activated.';
$txt['still_awaiting_approval'] = 'Your account is still awaiting admin approval.';

$txt['invalid_email'] = 'Invalid email address / email address range.<br>Example of a valid email address: evil.user@badsite.com.<br>Example of a valid email address range: *@*.badsite.com';
$txt['invalid_expiration_date'] = 'Expiration date is not valid';
$txt['invalid_hostname'] = 'Invalid host name / host name range.<br>Example of a valid host name: proxy4.badhost.com<br>Example of a valid host name range: *.badhost.com';
$txt['invalid_ip'] = 'Invalid IP / IP range.<br>Example of a valid IP address: 127.0.0.1<br>Example of a valid IP range: 127.0.0-20.*';
$txt['invalid_tracking_ip'] = 'Invalid IP / IP range.<br>Example of a valid IP address: 127.0.0.1<br>Example of a valid IP range: 127.0.0.*';
$txt['invalid_username'] = 'Member name not found';
$txt['no_user_selected'] = 'Member not found';
$txt['no_ban_admin'] = 'You may not ban an admin - you must demote them first!';
$txt['no_bantype_selected'] = 'No ban type was selected';
$txt['ban_not_found'] = 'Ban not found';
$txt['ban_unknown_restriction_type'] = 'Restriction type unknown';
$txt['ban_name_empty'] = 'The name of the ban was left empty';
$txt['ban_id_empty'] = 'Ban id not found';
$txt['ban_no_triggers'] = 'No ban triggers specified';
$txt['ban_ban_item_empty'] = 'Ban trigger not found';
$txt['impossible_insert_new_bangroup'] = 'An error occurred while inserting the new ban';

$txt['ban_name_exists'] = 'The name of this ban already exists. Please choose a different name.';
$txt['ban_trigger_already_exists'] = 'This ban trigger (%1$s) already exists in %2$s.';

$txt['recycle_no_valid_board'] = 'No valid board selected for recycled topics';
$txt['post_already_deleted'] = 'The topic or message has already been moved to the recycle board. Are you sure you want to delete it completely?<br>If so follow <a href="%1$s">this link</a>';

$txt['login_threshold_fail'] = 'Sorry, you are out of login chances. Please come back and try again later.';
$txt['login_threshold_brute_fail'] = 'Sorry, but you\'ve reached your login attempts threshold for account %1$s. Please wait 30 seconds and try again later.';

$txt['who_off'] = 'You cannot access Who\'s Online right now because it is disabled.';

$txt['merge_create_topic_failed'] = 'Error creating a new topic.';
$txt['merge_need_more_topics'] = 'Merge topics require at least two topics to merge.';

$txt['post_WaitTime_broken'] = 'The last posting from your IP was less than %1$d seconds ago. Please try again later.';
$txt['register_WaitTime_broken'] = 'You already registered just %1$d seconds ago!';
$txt['login_WaitTime_broken'] = 'You will have to wait about %1$d seconds to login again, sorry.';
$txt['pm_WaitTime_broken'] = 'The last personal message from your IP was less than %1$d seconds ago. Please try again later.';
$txt['reporttm_WaitTime_broken'] = 'The last topic report from your IP was less than %1$d seconds ago. Please try again later.';
$txt['sendmail_WaitTime_broken'] = 'The last email sent from your IP was less than %1$d seconds ago. Please try again later.';
$txt['search_WaitTime_broken'] = 'Your last search was less than %1$d seconds ago. Please try again later.';
$txt['remind_WaitTime_broken'] = 'Your last reminder was less than %1$d seconds ago. Please try again later.';

$txt['email_missing_data'] = 'You must enter something in both the subject and message boxes.';

$txt['topic_gone'] = 'The topic or board you are looking for appears to be either missing or off limits to you.';
$txt['theme_edit_missing'] = 'The file you are trying to edit cannot be found.';

$txt['no_dump_database'] = 'Only administrators can make database backups.';
$txt['pm_not_yours'] = 'The personal message you are trying to quote is not your own or does not exist, please go back and try again.';
$txt['mangled_post'] = 'Mangled form data - please go back and try again.';
$txt['too_many_groups'] = 'Sorry, you selected too many groups, please remove some from your selection.';
$txt['post_upload_error'] = 'The post data is missing. This error can be caused by trying to submit a file larger than allowed by the server. Please contact your administrator if this problem continues.';
$txt['quoted_post_deleted'] = 'The post you are trying to quote either does not exist, was deleted, or is no longer viewable by you.';
$txt['pm_too_many_per_hour'] = 'You have exceeded the limit of %1$d personal messages per hour.';

$txt['register_only_once'] = 'Sorry, but you are not allowed to register multiple accounts at the same time from the same computer.';
$txt['admin_setting_coppa_require_contact'] = 'You must enter either a postal or fax contact if parent/guardian approval is required.';

$txt['error_long_name'] = 'The name you tried to use was too long.';
$txt['error_no_name'] = 'No name was provided.';
$txt['error_bad_name'] = 'The name you submitted cannot be used, because it is, or contains, a reserved name.';
$txt['error_no_email'] = 'No email address was provided.';
$txt['error_bad_email'] = 'An invalid email address was given.';
$txt['error_no_event'] = 'No event name has been given.';
$txt['error_no_subject'] = 'No subject was filled in.';
$txt['error_no_question'] = 'No question was filled in for this poll.';
$txt['error_no_message'] = 'The message body was left empty.';
$txt['error_long_message'] = 'The message exceeds the maximum allowed length (%1$d characters).';
$txt['error_no_comment'] = 'The comment field was left empty.';
// duplicate of post_too_long in Post.{language}.php
$txt['error_post_too_long'] = 'Your message is too long. Please go back and shorten it, then try again.';
$txt['error_session_timeout'] = 'Your session timed out while posting. Please try to re-submit your message.';
$txt['error_no_to'] = 'No recipients specified.';
$txt['error_bad_to'] = 'One or more \'to\'-recipients could not be found.';
$txt['error_bad_bcc'] = 'One or more \'bcc\'-recipients could not be found.';
$txt['error_form_already_submitted'] = 'You have already submitted this post!  You might have accidentally double clicked or tried to refresh the page.';
$txt['error_poll_few'] = 'You must have at least two choices!';
$txt['error_poll_many'] = 'You must have no more than 256 choices.';
$txt['error_need_qr_verification'] = 'Please complete the verification section below to complete your post.';
$txt['error_wrong_verification_code'] = 'The letters you typed do not match the letters that were shown in the picture.';
$txt['error_wrong_verification_recaptcha'] = 'Verification failed, invalid captcha value.';
$txt['error_wrong_verification_answer'] = 'You did not answer the verification questions correctly.';
$txt['error_need_verification_code'] = 'Please enter the verification code below to continue to the results.';
$txt['error_bad_file'] = 'Sorry but the file specified could not be opened: %1$s';
$txt['error_bad_line'] = 'The line you specified is invalid.';
$txt['error_draft_not_saved'] = 'There was an error saving the draft';
$txt['error_topic_locked_already'] = 'This topic has already been locked by another moderator action.';
$txt['error_topic_unlocked_already'] = 'This topic has already been unlocked by another moderator action.';
$txt['error_topic_sticky_already'] = 'This topic has already been stickied by another moderator action.';
$txt['error_topic_nonsticky_already'] = 'This topic has already been unstickied by another moderator action.';

$txt['smiley_not_found'] = 'Smiley not found.';
$txt['smiley_has_no_code'] = 'No code for this smiley was given.';
$txt['smiley_has_no_filename'] = 'No filename for this smiley was given.';
$txt['smiley_not_unique'] = 'A smiley with that code already exists.';
$txt['smiley_set_already_exists'] = 'A smiley set with that URL already exists';
$txt['smiley_set_not_found'] = 'Smiley set not found';
$txt['smiley_set_dir_not_found'] = 'The directory of the smiley set %1$s is either invalid or cannot be accessed';
$txt['smiley_set_path_already_used'] = 'The URL of the smiley set is already being used by another smiley set.';
$txt['smiley_set_unable_to_import'] = 'Unable to import smiley set. Either the directory is invalid or cannot be accessed.';

$txt['smileys_upload_error'] = 'Failed to upload file.';
$txt['smileys_upload_error_blank'] = 'All smiley sets must have an image.';
$txt['smileys_upload_error_name'] = 'All smileys must have the same filename.';
$txt['smileys_upload_error_illegal'] = 'Illegal image type.';

$txt['search_invalid_weights'] = 'Search weights are not properly configured. At least one weight should be configured to be non-zero. Please report this error to an administrator.';
$txt['unable_to_create_temporary'] = 'The search function was unable to create temporary tables. Please try again.';

$txt['package_no_file'] = 'Unable to find package file!';
$txt['packageget_unable'] = 'Unable to connect to the server. Please try using <a href="%1$s" target="_blank" rel="noopener">this URL</a> instead.';
$txt['not_on_simplemachines'] = 'Sorry, packages can only be downloaded like this from the simplemachines.org server.';
$txt['package_cant_uninstall'] = 'This package was either never installed or was already uninstalled - you cannot uninstall it now.';
$txt['package_cant_download'] = 'You cannot download or install new packages because the Packages directory, or one of the files in it, is not writable!';
$txt['package_upload_error_nofile'] = 'You did not select a package to upload.';
$txt['package_upload_error_failed'] = 'Could not upload package, please check directory permissions!';
$txt['package_upload_error_exists'] = 'The file you are uploading already exists on the server. Please delete it first then try again.';
$txt['package_upload_error_supports'] = 'The package manager currently allows only these file types: %1$s.';
$txt['package_upload_error_broken'] = 'Package upload failed due to the following error:<br>&quot;%1$s&quot;';
$txt['package_theme_upload_error_broken'] = 'Theme upload failed due to the following error:<br>&quot;%1$s&quot;';

$txt['package_get_error_not_found'] = 'The package you are trying to install cannot be located. You may want to manually upload the package to your Packages directory.';
$txt['package_get_error_missing_xml'] = 'The package you are attempting to install is missing the package-info.xml that must be in the root package directory.';
$txt['package_get_error_is_zero'] = 'Although the package was downloaded to the server it appears to be empty. Please check the Packages directory and the &quot;temp&quot; sub-directory are both writable. If you continue to experience this problem you should try extracting the package on your PC and uploading the extracted files into a subdirectory in your Packages directory and try again. For example, if the package was called shout.tar.gz you should:<br>1) Download the package to your local PC and extract it into files.<br>2) Using an FTP client create a new directory in your &quot;Packages&quot; folder, in this example you may call it "shout".<br>3) Upload all the files from the extracted package to this directory.<br>4) Go back to the package manager browse page and the package will be automatically found by SMF.';
$txt['package_get_error_packageinfo_corrupt'] = 'SMF was unable to find any valid information within the package-info.xml file included within the Package. There may be an error with the modification, or the package may be corrupt.';
$txt['package_get_error_is_theme'] = 'You cannot install a Theme from this section, please use the <a href="{MANAGETHEMEURL}">Themes and Layout</a> management page to upload it';
$txt['package_get_error_is_mod'] = 'You cannot install a mod from this section, please use the <a href="{MANAGEMODURL}">Package manager</a> page to upload it';
$txt['package_get_error_theme_not_compatible'] = 'Your theme does not show it has compatibility with %1$s. Please contact the theme author.';
$txt['package_get_error_theme_no_based_on_found'] = 'The theme you are trying to install depends on another theme: %1$s. You need to install that theme first.';
$txt['package_get_error_theme_no_new_version'] = 'The theme you are trying to install is already installed or is an outdated version of it. The version already installed is: %2$s and the version you\'re trying to install is: %1$s.';

$txt['no_membergroup_selected'] = 'No membergroup selected';
$txt['membergroup_does_not_exist'] = 'The membergroup does not exist or is invalid.';

$txt['at_least_one_admin'] = 'There must be at least one administrator on a forum!';

$txt['error_functionality_not_windows'] = 'Sorry, this functionality is currently not available for servers running Windows.';

// Don't use entities in the below string.
$txt['attachment_not_found'] = 'Attachment not found';

$txt['error_no_boards_selected'] = 'No valid boards were selected.';
$txt['error_no_boards_available'] = 'Sorry, there are no boards available to you at this time.';
$txt['error_invalid_search_string'] = 'Did you forget to put something to search for?';
$txt['error_invalid_search_string_blacklist'] = 'Your search query contained too many trivial words. Please try again with a different query.';
$txt['error_search_string_small_words'] = 'Each word must be at least two characters long.';
$txt['error_query_not_specific_enough'] = 'Your search query did not return any matches.';
$txt['error_no_messages_in_time_frame'] = 'No posts found in selected time frame.';
$txt['error_no_labels_selected'] = 'No labels were selected.';
$txt['error_no_search_daemon'] = 'Unable to access the search daemon';

$txt['profile_errors_occurred'] = 'The following errors occurred when trying to save your profile';
$txt['profile_error_bad_offset'] = 'The time offset is out of range';
$txt['profile_error_bad_timezone'] = 'The timezone specified is invalid';
$txt['profile_error_no_name'] = 'The name field was left blank';
$txt['profile_error_digits_only'] = 'The \'number of posts\' box can only contain digits.';
$txt['profile_error_name_taken'] = 'The selected username/display name has already been taken';
$txt['profile_error_name_too_long'] = 'The selected name is too long. It should be no greater than 60 characters in length';
$txt['profile_error_no_email'] = 'The email field was left blank';
$txt['profile_error_bad_email'] = 'You have not entered a valid email address';
$txt['profile_error_email_taken'] = 'Another user is already registered with that email address';
$txt['profile_error_no_password'] = 'You did not enter your password';
$txt['profile_error_bad_new_password'] = 'The new passwords you entered do not match';
$txt['profile_error_bad_password'] = 'The password you entered was not correct';
$txt['profile_error_bad_avatar'] = 'The avatar you have selected is not a valid image';
$txt['profile_error_bad_avatar_invalid_url'] = 'The URL you specified is invalid, please check it.';
$txt['profile_error_bad_avatar_too_large'] = 'The image you are trying to use surpasses the max width/height settings, please use a smaller one.';
$txt['profile_error_bad_avatar_fail_reencode'] = 'The image you uploaded was corrupted and the attempt to recover it failed.';
$txt['profile_error_password_short'] = 'Your password must be at least ' . (empty($modSettings['password_strength']) ? 4 : 8) . ' characters long.';
$txt['profile_error_password_restricted_words'] = 'Your password must not contain your username, email address or other commonly used words.';
$txt['profile_error_password_chars'] = 'Your password must contain a mix of upper and lower case letters, as well as digits.';
$txt['profile_error_already_requested_group'] = 'You already have an outstanding request for this group!';
$txt['profile_error_signature_not_yet_saved'] = 'The signature has not been saved.';
$txt['profile_error_personal_text_too_long'] = 'The personal text is too long.';
$txt['profile_error_user_title_too_long'] = 'The custom title is too long.';
$txt['profile_error_custom_field_mail_fail'] = 'The mail validation check returned an error, you need to enter an email in a valid format (user@domain).';
$txt['profile_error_custom_field_regex_fail'] = 'The regex verification returned an error. If you are unsure about what to type here, please contact the forum administrator.';
$txt['profile_error_custom_field_nohtml_fail'] = 'HTML tags are not allowed.';

// Registration form.
$txt['under_age_registration_prohibited'] = 'Sorry, but users under the age of %1$d are not allowed to register on this forum.';
$txt['error_too_quickly'] = 'You went through registration a bit too quickly, faster than should normally be possible. Please give it a moment and try again.';
$txt['mysql_error_space'] = ' - check database storage space or contact the server administrator.';

$txt['icon_not_found'] = 'The icon image could not be found in the default theme - please ensure the image has been uploaded and try again.';
$txt['icon_after_itself'] = 'The icon cannot be positioned after itself.';
$txt['icon_name_too_long'] = 'Icon filenames cannot be more than 16 characters long';

$txt['name_censored'] = 'Sorry, the name you tried to use, %1$s, contains words which have been censored. Please try another name.';

$txt['poll_already_exists'] = 'A topic can only have one poll associated with it.';
$txt['poll_not_found'] = 'There is no poll associated with this topic!';

$txt['error_while_adding_poll'] = 'The following error or errors occurred while adding this poll';
$txt['error_while_editing_poll'] = 'The following error or errors occurred while editing this poll';

$txt['loadavg_search_disabled'] = 'Due to high stress on the server, the search function has been automatically and temporarily disabled. Please try again in a short while.';
$txt['loadavg_generic_disabled'] = 'Sorry, because of high stress on the server, this feature is currently unavailable.';
$txt['loadavg_allunread_disabled'] = 'The server\'s resources are temporarily under too high a demand to find all the topics you have not read.';
$txt['loadavg_unreadreplies_disabled'] = 'The server is currently under high stress. Please try again shortly.';
$txt['loadavg_show_posts_disabled'] = 'Please try again later. This member\'s posts are not currently available due to high load on the server.';
$txt['loadavg_unread_disabled'] = 'The server\'s resources are temporarily under too high a demand to list out the topics you have not read.';
$txt['loadavg_userstats_disabled'] = 'This member\'s statistics are not currently available due to high load on the server. Please try again later.';

$txt['cannot_edit_permissions_inherited'] = 'You cannot edit inherited permissions directly, you must either edit the parent group or edit the membergroup inheritance.';

$txt['mc_no_modreport_specified'] = 'You need to specify which report you wish to view.';
$txt['mc_no_modreport_found'] = 'The specified report either does not exist or is off limits to you';

$txt['st_cannot_retrieve_file'] = 'Could not retrieve the file %1$s.';
$txt['admin_file_not_found'] = 'Could not load the requested file: %1$s.';

$txt['themes_none_selectable'] = 'At least one theme must be selectable.';
$txt['themes_default_selectable'] = 'The overall forum default theme must be a selectable theme.';
$txt['ignoreboards_disallowed'] = 'The option to ignore boards has not been enabled.';

$txt['mboards_delete_error'] = 'No category selected.';
$txt['mboards_delete_board_error'] = 'No board selected.';

$txt['mboards_parent_own_child_error'] = 'You cannot make a board into a sub-board of itself.';
$txt['mboards_board_own_child_error'] = 'You cannot make a board into its own sub-board.';

$txt['smileys_upload_error_notwritable'] = 'The following smiley directories are not writable: %1$s';
$txt['smileys_upload_error_types'] = 'Smiley images can only have the following extensions: %1$s.';

$txt['change_email_success'] = 'Your email address has been changed, and a new activation email has been sent to it.';
$txt['resend_email_success'] = 'A new activation email has successfully been sent.';

$txt['custom_option_need_name'] = 'The profile option must have a name.';
$txt['custom_option_not_unique'] = 'Field name is not unique.';
$txt['custom_option_regex_error'] = 'The regex you entered is not valid';

$txt['warning_no_reason'] = 'You must enter a reason for altering the warning level of a member';
$txt['warning_notify_blank'] = 'You selected to notify the user but did not fill in the subject/message fields';

$txt['cannot_connect_doc_site'] = 'Could not connect to the Simple Machines Online Manual. Please check that your server configuration allows external internet connections and try again later.';

$txt['movetopic_no_reason'] = 'You must enter a reason for moving the topic, or uncheck the option to \'post a redirection topic\'.';

$txt['error_custom_field_too_long'] = 'The &quot;%1$s&quot; field cannot be greater than %2$d characters in length.';
$txt['error_custom_field_invalid_email'] = 'The &quot;%1$s&quot; field must be a valid email address.';
$txt['error_custom_field_not_number'] = 'The &quot;%1$s&quot; field must be numeric.';
$txt['error_custom_field_inproper_format'] = 'The &quot;%1$s&quot; field is an invalid format.';
$txt['error_custom_field_empty'] = 'The &quot;%1$s&quot; field cannot be left blank.';

$txt['email_no_template'] = 'The email template &quot;%1$s&quot; could not be found.';

$txt['search_api_missing'] = 'The search API could not be found. Please contact the admin to check they have uploaded the correct files.';
$txt['search_api_not_compatible'] = 'The selected search API the forum is using is out of date - falling back to standard search. Please check file %1$s.';

// Handling hook calls
$txt['hook_fail_loading_file'] = 'Hook call: The file at path: %s could not be loaded.';
$txt['hook_fail_call_to'] = 'Hook call: function "%1$s" in file %2$s could not be called.';

$txt['file_not_created'] = 'The file at "%1$s" could not be created. Please make sure the parent directory has the appropriate permissions.';
$txt['file_minimize_fail'] = 'The file "%1$s" could not be located on the current or the default theme and, therefore, it was not included in the minified file.';
$txt['unlink_minimized_fail'] = 'The following files could not be deleted. Please check the file permissions of the files themselves and the parent directory.<br>%1$s';

// SubActions failed attempt.
$txt['sub_action_fail'] = 'The callable %s could not be called.';

// Restore topic/posts
$txt['cannot_restore_first_post'] = 'You cannot restore the first post in a topic.';
$txt['parent_topic_missing'] = 'The parent topic of the post you are trying to restore has been deleted.';
$txt['restored_disabled'] = 'The restoration of topics has been disabled.';
$txt['restore_not_found'] = 'The following messages could not be restored; the original topic may have been removed:<ul style="margin-top: 0px;">%1$s</ul>You will need to move these manually.';

$txt['error_invalid_dir'] = 'The directory you entered is invalid.';

// json errors.
$txt['json_JSON_ERROR_DEPTH'] = 'JSON decode error: The maximum stack depth has been exceeded';
$txt['json_JSON_ERROR_STATE_MISMATCH'] = 'JSON decode error: Invalid or malformed JSON';
$txt['json_JSON_ERROR_CTRL_CHAR'] = 'JSON decode error: Control character error, possibly incorrectly encoded';
$txt['json_JSON_ERROR_SYNTAX'] = 'JSON decode error: Syntax error, malformed JSON';
$txt['json_JSON_ERROR_UTF8'] = 'JSON decode error: Malformed UTF-8 characters, possibly incorrectly encoded';
$txt['json_JSON_ERROR_RECURSION'] = 'JSON decode error: One or more recursive references in the value to be encoded';
$txt['json_JSON_ERROR_INF_OR_NAN'] = 'JSON decode error: One or more NAN or INF values in the value to be encoded';
$txt['json_JSON_ERROR_UNSUPPORTED_TYPE'] = 'JSON decode error: A value of a type that cannot be encoded was given';
$txt['json_unknown'] = 'Unknown error';

?>