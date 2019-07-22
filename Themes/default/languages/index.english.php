<?php
// Version: 2.1 RC2; index

global $forum_copyright, $webmaster_email, $scripturl, $context, $boardurl;

// Native name, please use full HTML entities to write your language's name.
$txt['native_name'] = 'English';

// Locale (strftime, pspell_new) and spelling. (pspell_new, can be left as '' normally.)
// For more information see:
//   - https://php.net/function.pspell-new
//   - https://php.net/function.setlocale
// Again, SPELLING SHOULD BE '' 99% OF THE TIME!!  Please read this!
$txt['lang_locale'] = 'en_US';
$txt['lang_dictionary'] = 'en';
$txt['lang_spelling'] = 'american';
//https://developers.google.com/recaptcha/docs/language
$txt['lang_recaptcha'] = 'en';

// Ensure you remember to use uppercase for character set strings.
$txt['lang_character_set'] = 'UTF-8';
// Character set and right to left?
$txt['lang_rtl'] = false;
// Number format.
$txt['number_format'] = '1,234.00';

$txt['days_title'] = 'Days';
$txt['days'] = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
$txt['days_short'] = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
// Months must start with 1 => 'January'. (or translated, of course.)
$txt['months_title'] = 'Months';
$txt['months'] = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
$txt['months_titles'] = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
$txt['months_short'] = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
$txt['prev_month'] = 'Previous month';
$txt['next_month'] = 'Next month';
$txt['start'] = 'Start';
$txt['end'] = 'End';
$txt['starts'] = 'Starts';
$txt['ends'] = 'Ends';
$txt['none'] = 'None';

$txt['minutes_label'] = 'Minutes';
$txt['hours_label'] = 'Hours';
$txt['years_title'] = 'Years';

$txt['time_am'] = 'am';
$txt['time_pm'] = 'pm';

$txt['admin'] = 'Admin';
$txt['moderate'] = 'Moderate';

$txt['save'] = 'Save';
$txt['reset'] = 'Reset';
$txt['upload'] = 'Upload';
$txt['upload_all'] = 'Upload all';
$txt['processing'] = 'Processing...';

$txt['modify'] = 'Modify';
$txt['forum_index'] = '%1$s - Index';
$txt['members'] = 'Members';
$txt['board_name'] = 'Board name';
$txt['posts'] = 'Posts';

$txt['member_postcount'] = 'Posts';
$txt['no_subject'] = '(No subject)';
$txt['view_profile'] = 'View profile';
$txt['guest_title'] = 'Guest';
$txt['author'] = 'Author';
$txt['on'] = 'on';
$txt['remove'] = 'Remove';
$txt['start_new_topic'] = 'Start new topic';

$txt['login'] = 'Login';
// Use numeric entities in the below string.
$txt['username'] = 'Username';
$txt['password'] = 'Password';

$txt['username_no_exist'] = 'That username does not exist.';
$txt['no_user_with_email'] = 'There are no usernames associated with that email.';

$txt['board_moderator'] = 'Board Moderator';
$txt['remove_topic'] = 'Remove topic';
$txt['topics'] = 'Topics';
$txt['modify_msg'] = 'Modify message';
$txt['name'] = 'Name';
$txt['email'] = 'Email';
$txt['user_email_address'] = 'Email address';
$txt['subject'] = 'Subject';
$txt['message'] = 'Message';
$txt['redirects'] = 'Redirects';
$txt['quick_modify'] = 'Modify inline';
$txt['quick_modify_message'] = 'You have successfully modified this message.';
$txt['reason_for_edit'] = 'Reason for editing';

$txt['choose_pass'] = 'Choose password';
$txt['verify_pass'] = 'Verify password';
$txt['notify_announcements'] = 'Allow the administrators to send me important news by email';

$txt['position'] = 'Position';

$txt['profile_of'] = 'View the profile of';
$txt['total'] = 'Total';
$txt['website'] = 'Website';
$txt['register'] = 'Sign Up';
$txt['warning_status'] = 'Warning status';
$txt['user_warn_watch'] = 'User is on moderator watch list';
$txt['user_warn_moderate'] = 'User posts join approval queue';
$txt['user_warn_mute'] = 'User is banned from posting';
$txt['warn_watch'] = 'Watched';
$txt['warn_moderate'] = 'Moderated';
$txt['warn_mute'] = 'Muted';

$txt['message_index'] = 'Message Index';
$txt['news'] = 'News';
$txt['home'] = 'Home';
$txt['page'] = 'Page';
$txt['prev'] = 'Previous page';
$txt['next'] = 'Next page';

$txt['lock_unlock'] = 'Lock/Unlock Topic';
$txt['post'] = 'Post';
$txt['error_occured'] = 'An error has occurred';
$txt['at'] = 'at';
$txt['by'] = 'by';
$txt['logout'] = 'Logout';
$txt['started_by'] = 'Started by';
$txt['topic_started_by'] = 'Started by <strong>%1$s</strong> in <em>%2$s</em>';
$txt['replies'] = 'Replies';
$txt['last_post'] = 'Last post';
$txt['first_post'] = 'First post';
$txt['last_poster'] = 'Last post by';
$txt['last_post_message'] = '<strong>Last post: </strong>%3$s <span class="postby">%2$s by %1$s</span>';
$txt['last_post_topic'] = '%1$s<br>by %2$s';
$txt['post_by_member'] = '<strong>%1$s</strong> by <strong>%2$s</strong><br>';
$txt['boardindex_total_posts'] = '%1$s Posts in %2$s Topics by %3$s Members';
$txt['show'] = 'Show';
$txt['hide'] = 'Hide';

$txt['admin_login'] = 'Administration Login';
// Use numeric entities in the below string.
$txt['topic'] = 'Topic';
$txt['help'] = 'Help';
$txt['terms_and_rules'] = 'Terms and Rules';
$txt['watch_board'] = 'Watch this Board';
$txt['unwatch_board'] = 'Stop watching Board';
$txt['watch_topic'] = 'Watch this Topic';
$txt['unwatch_topic'] = 'Stop watching Topic';
$txt['watching_this_topic'] = 'You are watching this topic, and will receive notifications about it.';
$txt['notify'] = 'Notify';
$txt['unnotify'] = 'Unnotify';
$txt['notify_request'] = 'Do you want a notification email if someone replies to this topic?';
// Use numeric entities in the below string.
$txt['regards_team'] = "Regards,\nThe " . $context['forum_name'] . ' Team.';
$txt['notify_replies'] = 'Notify of replies';
$txt['move_topic'] = 'Move Topic';
$txt['move_to'] = 'Move to';
$txt['pages'] = 'Pages';
$txt['users_active'] = 'Users active in past %1$d minutes';
$txt['personal_messages'] = 'Personal Messages';
$txt['reply_quote'] = 'Reply with quote';
$txt['reply'] = 'Reply';
$txt['reply_noun'] = 'Reply';
$txt['reply_number'] = 'Reply #%1$s';
$txt['approve'] = 'Approve';
$txt['unapprove'] = 'Unapprove';
$txt['approve_all'] = 'approve all';
$txt['issue_warning'] = 'Issue Warning';
$txt['awaiting_approval'] = 'Awaiting approval';
$txt['attach_awaiting_approve'] = 'Attachments awaiting approval';
$txt['post_awaiting_approval'] = 'This message is awaiting approval by a moderator.';
$txt['there_are_unapproved_topics'] = 'There are %1$s topics and %2$s posts awaiting approval in this board. Click <a href="%3$s">here</a> to view them all.';
$txt['send_message'] = 'Send message';

$txt['msg_alert_no_messages'] = 'you don\'t have any message';
$txt['msg_alert_one_message'] = 'you have <a href="%1$s">1 message</a>';
$txt['msg_alert_many_message'] = 'you have <a href="%1$s">%2$d messages</a>';
$txt['msg_alert_one_new'] = '1 is new';
$txt['msg_alert_many_new'] = '%1$d are new';
$txt['new_alert'] = 'New alert';
$txt['remove_message'] = 'Remove this post';
$txt['remove_message_question'] = 'Remove this post?';

$txt['topic_alert_none'] = 'No messages...';
$txt['pm_alert_none'] = 'No messages...';
$txt['no_messages'] = 'No messages';

$txt['online_users'] = 'Users Online';
$txt['jump_to'] = 'Jump to';
$txt['go'] = 'Go';
$txt['are_sure_remove_topic'] = 'Are you sure you want to remove this topic?';
$txt['yes'] = 'Yes';
$txt['no'] = 'No';

$txt['search_end_results'] = 'End of results';
$txt['search_on'] = 'on';

$txt['search'] = 'Search';
$txt['all'] = 'All';
$txt['search_entireforum'] = 'Entire forum';
$txt['search_thisboard'] = 'This board';
$txt['search_thistopic'] = 'This topic';
$txt['search_members'] = 'Members';

$txt['back'] = 'Back';
$txt['continue'] = 'Continue';
$txt['password_reminder'] = 'Password reminder';
$txt['topic_started'] = 'Topic started by';
$txt['title'] = 'Title';
$txt['post_by'] = 'Post by';
$txt['memberlist_searchable'] = 'Searchable list of all registered members.';
$txt['welcome_newest_member'] = 'Please welcome %1$s, our newest member.';
$txt['admin_center'] = 'Administration Center';
$txt['last_edit_by'] = '<span class="lastedit">Last Edit</span>: %1$s by %2$s';
$txt['last_edit_reason'] = '<span id="reason" class="lastedit">Reason</span>: %1$s';
$txt['notify_deactivate'] = 'Would you like to deactivate notification on this topic?';

$txt['recent_posts'] = 'Recent posts';

$txt['location'] = 'Location';
$txt['gender'] = 'Gender';
$txt['personal_text'] = 'Personal text';
$txt['date_registered'] = 'Date registered';

$txt['recent_view'] = 'View the most recent posts on the forum.';
$txt['recent_updated'] = 'is the most recently updated topic';
$txt['is_recent_updated'] = '%1$s is the most recently updated topic';

$txt['male'] = 'Male';
$txt['female'] = 'Female';

$txt['error_invalid_characters_username'] = 'Invalid character used in Username.';

$txt['welcome_guest'] = 'Welcome, <strong>%1$s</strong>. Please <a href="%3$s" onclick="%4$s">login</a>.';

//$txt['welcome_guest_register'] = 'Welcome, <strong>%1$s</strong>. Please <a href="' . $scripturl . '?action=login">login</a> or <a href="' . $scripturl . '?action=register">register</a>.';
$txt['welcome_guest_register'] = 'Welcome to <strong>%2$s</strong>. Please <a href="%3$s" onclick="%4$s">login</a> or <a href="%5$s">sign up</a>.';

$txt['please_login'] = 'Please <a href="' . $scripturl . '?action=login">login</a>.';
$txt['login_or_register'] = 'Please <a href="' . $scripturl . '?action=login">login</a> or <a href="' . $scripturl . '?action=signup">signup</a>.';
$txt['welcome_guest_activate'] = '<br>Did you miss your <a href="' . $scripturl . '?action=activate">activation email</a>?';
// @todo the following to sprintf
$txt['hello_member'] = 'Hey,';
// Use numeric entities in the below string.
$txt['hello_guest'] = 'Welcome,';
$txt['welmsg_hey'] = 'Hey,';
$txt['welmsg_welcome'] = 'Welcome,';
$txt['welmsg_please'] = 'Please';
$txt['select_destination'] = 'Please select a destination';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['posted_by'] = 'Posted by';

$txt['icon_smiley'] = 'Smiley';
$txt['icon_angry'] = 'Angry';
$txt['icon_cheesy'] = 'Cheesy';
$txt['icon_laugh'] = 'Laugh';
$txt['icon_sad'] = 'Sad';
$txt['icon_wink'] = 'Wink';
$txt['icon_grin'] = 'Grin';
$txt['icon_shocked'] = 'Shocked';
$txt['icon_cool'] = 'Cool';
$txt['icon_huh'] = 'Huh';
$txt['icon_rolleyes'] = 'Roll Eyes';
$txt['icon_tongue'] = 'Tongue';
$txt['icon_embarrassed'] = 'Embarrassed';
$txt['icon_lips'] = 'Lips sealed';
$txt['icon_undecided'] = 'Undecided';
$txt['icon_kiss'] = 'Kiss';
$txt['icon_cry'] = 'Cry';

$txt['moderator'] = 'Moderator';
$txt['moderators'] = 'Moderators';

$txt['mark_board_read'] = 'Mark Topics as Read for this Board';
$txt['views'] = 'Views';
$txt['new'] = 'New';

$txt['view_all_members'] = 'View all Members';
$txt['view'] = 'View';

$txt['viewing_members'] = 'Viewing Members %1$s to %2$s';
$txt['of_total_members'] = 'of %1$s total members';

$txt['forgot_your_password'] = 'Forgot your password?';

$txt['date'] = 'Date';
// Use numeric entities in the below string.
$txt['from'] = 'From';
$txt['check_new_messages'] = 'Check for new messages';
$txt['to'] = 'To';

$txt['board_topics'] = 'Topics';
$txt['members_title'] = 'Members';
$txt['members_list'] = 'Members List';
$txt['new_posts'] = 'New Posts';
$txt['old_posts'] = 'No New Posts';
$txt['redirect_board'] = 'Redirect Board';

$txt['sendtopic_send'] = 'Send';
$txt['report_sent'] = 'Your report has been sent successfully.';
$txt['post_becomes_unapproved'] = 'Your message was not approved because it was posted in an unapproved topic. Once the topic is approved your message will be approved too.';

$txt['time_offset'] = 'Time Offset';
$txt['or'] = 'or';

$txt['no_matches'] = 'Sorry, no matches were found';

$txt['notification'] = 'Notification';

$txt['your_ban'] = 'Sorry %1$s, you are banned from using this forum!';
$txt['your_ban_expires'] = 'This ban is set to expire %1$s.';
$txt['your_ban_expires_never'] = 'This ban is not set to expire.';
$txt['ban_continue_browse'] = 'You may continue to browse the forum as a guest.';

$txt['mark_as_read'] = 'Mark ALL messages as read';

$txt['locked_topic'] = 'Locked Topic';
$txt['normal_topic'] = 'Normal Topic';
$txt['participation_caption'] = 'Topic you have posted in';
$txt['moved_topic'] = 'Moved Topic';

$txt['go_caps'] = 'GO';

$txt['print'] = 'Print';
$txt['profile'] = 'Profile';
$txt['topic_summary'] = 'Topic summary';
$txt['not_applicable'] = 'N/A';
$txt['name_in_use'] = 'This name is already in use by another member.';

$txt['total_members'] = 'Total Members';
$txt['total_posts'] = 'Total Posts';
$txt['total_topics'] = 'Total Topics';

$txt['time_logged_in'] = 'Time to stay logged in';

$txt['preview'] = 'Preview';
$txt['always_logged_in'] = 'Always stay logged in';

$txt['logged'] = 'Logged';
// Use numeric entities in the below string.
$txt['ip'] = 'IP';

$txt['www'] = 'WWW';

$txt['hours'] = 'hours';
$txt['minutes'] = 'minutes';
$txt['seconds'] = 'seconds';

// Used upper case in Paid subscriptions management
$txt['hour'] = 'Hour';
$txt['days_word'] = 'days';

$txt['search_for'] = 'Search for';
$txt['search_match'] = 'Match';

$txt['forum_in_maintenance'] = 'Your forum is in Maintenance Mode. Only administrators can currently log in.';
$txt['maintenance_page'] = 'You can turn off Maintenance Mode from the <a href="%1$s">Server Settings</a> area.';

$txt['read_one_time'] = 'Read 1 time';
$txt['read_many_times'] = 'Read %1$d times';

$txt['forum_stats'] = 'Forum Stats';
$txt['latest_member'] = 'Latest Member';
$txt['total_cats'] = 'Total Categories';
$txt['latest_post'] = 'Latest Post';

$txt['total_boards'] = 'Total Boards';

$txt['print_page'] = 'Print Page';
$txt['print_page_text'] = 'Text only';
$txt['print_page_images'] = 'Text with Images';

$txt['valid_email'] = 'This must be a valid email address.';

$txt['geek'] = 'I am a geek!!';
$txt['info_center_title'] = '%1$s - Info Center';

$txt['watch'] = 'Watch';
$txt['unwatch'] = 'Stop watching';

$txt['check_all'] = 'Check all';

// Use numeric entities in the below string.
$txt['database_error'] = 'Database Error';
$txt['try_again'] = 'Please try again. If you come back to this error screen, report the error to an administrator.';
$txt['file'] = 'File';
$txt['line'] = 'Line';
// Use numeric entities in the below string.
$txt['tried_to_repair'] = 'SMF has detected and automatically tried to repair an error in your database. If you continue to have problems, or continue to receive these emails, please contact your host.';
$txt['database_error_versions'] = '<strong>Note:</strong> It appears that your database <em>may</em> require an upgrade. Your forum\'s files are currently at version %1$s, while your database is at version %2$s. The above error might possibly go away if you execute the latest version of upgrade.php.';
$txt['template_parse_error'] = 'Template Parse Error!';
$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system. This problem should only be temporary, so please come back later and try again. If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
$txt['template_parse_error_details'] = 'There was a problem loading the <pre><strong>%1$s</strong></pre> template or language file. Please check the syntax and try again - remember, single quotes (<pre>\'</pre>) often have to be escaped with a slash (<pre>\\</pre>). To see more specific error information from PHP, try <a href="' . $boardurl . '%1$s">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="' . $scripturl . '?theme=1">use the default theme</a>.';
$txt['template_parse_errmsg'] = 'Unfortunately more information is not available at this time as to exactly what is wrong.';

$txt['today'] = '<strong>Today</strong> at ';
$txt['yesterday'] = '<strong>Yesterday</strong> at ';
$txt['new_poll'] = 'New poll';
$txt['poll_question'] = 'Question';
$txt['poll_vote'] = 'Submit Vote';
$txt['poll_total_voters'] = 'Total Members Voted';
$txt['poll_results'] = 'View results';
$txt['poll_lock'] = 'Lock Voting';
$txt['poll_unlock'] = 'Unlock Voting';
$txt['poll_edit'] = 'Edit Poll';
$txt['poll'] = 'Poll';
$txt['one_hour'] = '1 Hour';
$txt['one_day'] = '1 Day';
$txt['one_week'] = '1 Week';
$txt['two_weeks'] = '2 Weeks';
$txt['one_month'] = '1 Month';
$txt['two_months'] = '2 Months';
$txt['forever'] = 'Forever';
$txt['quick_login_dec'] = 'Login with username, password and session length';
$txt['moved'] = 'MOVED';
$txt['move_why'] = 'Please enter a brief description as to<br>why this topic is being moved.';
$txt['board'] = 'Board';
$txt['in'] = 'in';
$txt['sticky_topic'] = 'Sticky Topic';

$txt['delete'] = 'Delete';
$txt['no_change'] = 'No change';

$txt['your_pms'] = 'Your Personal Messages';

$txt['kilobyte'] = 'KB';
$txt['megabyte'] = 'MB';

$txt['more_stats'] = '[More Stats]';

// Use numeric entities in the below three strings.
$txt['code'] = 'Code';
$txt['code_select'] = 'Select';
$txt['code_expand'] = 'Expand';
$txt['code_shrink'] = 'Shrink';
$txt['quote_from'] = 'Quote from';
$txt['quote'] = 'Quote';
$txt['quote_action'] = 'Quote';
$txt['quote_selected_action'] = 'Quote selected text';
$txt['fulledit'] = 'Full&nbsp;edit';
$txt['edit'] = 'Edit';
$txt['quick_edit'] = 'Quick Edit';
$txt['post_options'] = 'More...';

$txt['merge_to_topic_id'] = 'ID of target topic';
$txt['split'] = 'Split Topic';
$txt['merge'] = 'Merge Topics';
$txt['target_id'] = 'Select target by topic ID';
$txt['target_below'] = 'Select target from the list below';
$txt['subject_new_topic'] = 'Subject For New Topic';
$txt['split_this_post'] = 'Only split this post.';
$txt['split_after_and_this_post'] = 'Split topic after and including this post.';
$txt['select_split_posts'] = 'Select posts to split.';
$txt['new_topic'] = 'New Topic';
$txt['split_successful'] = 'Topic successfully split into two topics.';
$txt['origin_topic'] = 'Original Topic';
$txt['please_select_split'] = 'Please select which posts you wish to split.';
$txt['merge_successful'] = 'Topics successfully merged.';
$txt['new_merged_topic'] = 'Newly Merged Topic';
$txt['topic_to_merge'] = 'Topic to be merged';
$txt['target_board'] = 'Target board';
$txt['target_topic'] = 'Target topic';
$txt['merge_confirm'] = 'Are you sure you want to merge';
$txt['with'] = 'with';
$txt['merge_desc'] = 'This function will merge the messages of two topics into one topic. The messages will be sorted according to the time of posting. Therefore the earliest posted message will be the first message of the merged topic.';

$txt['set_sticky'] = 'Set topic sticky';
$txt['set_nonsticky'] = 'Set topic non-sticky';
$txt['set_lock'] = 'Lock topic';
$txt['set_unlock'] = 'Unlock topic';

$txt['search_advanced'] = 'Advanced search';

$txt['security_risk'] = 'MAJOR SECURITY RISK:';
$txt['not_removed'] = 'You have not removed ';
$txt['not_removed_extra'] = '%1$s is a backup of %2$s that was not generated by SMF. It can be accessed directly and used to gain unauthorized access to your forum. You should delete it immediately.';
$txt['generic_warning'] = 'Warning';
$txt['agreement_missing'] = 'You are requiring new users to accept a registration agreement, however the file (agreement.txt) doesn\'t exist.';

$txt['cache_writable'] = 'The cache directory is not writable - this will adversely affect the performance of your forum.';

$txt['page_created_full'] = 'Page created in %1$.3f seconds with %2$d queries.';

$txt['report_to_mod_func'] = 'Use this function to inform the moderators and administrators of an abusive or problematic message.';
$txt['report_profile_func'] = 'Use this function to inform the administrators of abusive profile content, such as spam or inappropriate images.';

$txt['online'] = 'Online';
$txt['member_is_online'] = '%1$s is online';
$txt['offline'] = 'Offline';
$txt['member_is_offline'] = '%1$s is offline';
$txt['pm_online'] = 'Personal Message (Online)';
$txt['pm_offline'] = 'Personal Message (Offline)';
$txt['status'] = 'Status';

$txt['go_up'] = 'Go Up';
$txt['go_down'] = 'Go Down';

$forum_copyright = '<a href="' . $scripturl . '?action=credits" title="License" target="_blank" rel="noopener">%1$s &copy; %2$s</a>, <a href="http://www.simplemachines.org" title="Simple Machines" target="_blank" rel="noopener">Simple Machines</a>';

$txt['birthdays'] = 'Birthdays:';
$txt['events'] = 'Events:';
$txt['birthdays_upcoming'] = 'Upcoming Birthdays:';
$txt['events_upcoming'] = 'Upcoming Events:';
// Prompt for holidays in the calendar, leave blank to just display the holiday's name.
$txt['calendar_prompt'] = 'Holidays:';
$txt['calendar_month'] = 'Month';
$txt['calendar_year'] = 'Year';
$txt['calendar_day'] = 'Day';
$txt['calendar_event_title'] = 'Event Title';
$txt['calendar_event_options'] = 'Event Options';
$txt['calendar_post_in'] = 'Post in';
$txt['calendar_edit'] = 'Edit Event';
$txt['calendar_export'] = 'Export Event';
$txt['calendar_view_week'] = 'View Week';
$txt['event_delete_confirm'] = 'Delete this event?';
$txt['event_delete'] = 'Delete Event';
$txt['calendar_post_event'] = 'Post Event';
$txt['calendar'] = 'Calendar';
$txt['calendar_link'] = 'Link to Calendar';
$txt['calendar_upcoming'] = 'Upcoming Calendar';
$txt['calendar_today'] = 'Today\'s Calendar';
$txt['calendar_week'] = 'Week';
$txt['calendar_week_title'] = 'Week %1$d of %2$d';
// %1$s is the month, %2$s is the day, %3$s is the year. Change to suit your language.
$txt['calendar_week_beginning'] = 'Week beginning %1$s %2$s, %3$s';
$txt['calendar_numb_days'] = 'Number of Days';
$txt['calendar_how_edit'] = 'how do you edit these events?';
$txt['calendar_link_event'] = 'Link Event To Post';
$txt['calendar_confirm_delete'] = 'Are you sure you want to delete this event?';
$txt['calendar_linked_events'] = 'Linked Events';
$txt['calendar_click_all'] = 'click to see all %1$s';
$txt['calendar_allday'] = 'All day';
$txt['calendar_timezone'] = 'Time zone';
$txt['calendar_list'] = 'List';
$txt['calendar_empty'] = 'There are no events to display.';

$txt['movetopic_change_subject'] = 'Change the topic\'s subject';
$txt['movetopic_new_subject'] = 'New subject';
$txt['movetopic_change_all_subjects'] = 'Change every message\'s subject';
$txt['move_topic_unapproved_js'] = 'Warning! This topic has not yet been approved.\\n\\nIt is not recommended that you create a redirection topic unless you intend to approve the post immediately following the move.';
$txt['movetopic_auto_board'] = '[BOARD]';
$txt['movetopic_auto_topic'] = '[TOPIC LINK]';
$txt['movetopic_default'] = 'This topic has been moved to ' . $txt['movetopic_auto_board'] . ".\n\n" . $txt['movetopic_auto_topic'];
$txt['movetopic_redirect'] = 'Redirect to the moved topic';

$txt['post_redirection'] = 'Post a redirection topic';
$txt['redirect_topic_expires'] = 'Automatically remove the redirection topic';
$txt['mergetopic_redirect'] = 'Redirect to the merged topic';
$txt['merge_topic_unapproved_js'] = 'Warning! This topic has not yet been approved.\\n\\nIt is not recommended that you create a redirection topic unless you intend to approve the post immediately following the merge.';

$txt['theme_template_error'] = 'Unable to load the \'%1$s\' template.';
$txt['theme_language_error'] = 'Unable to load the \'%1$s\' language file.';

$txt['sub_boards'] = 'Sub-Boards';
$txt['restricted_board'] = 'Restricted Board';

$txt['smtp_no_connect'] = 'Could not connect to SMTP host';
$txt['smtp_port_ssl'] = 'SMTP port setting incorrect; it should be 465 for SSL servers. Hostname may need ssl:// prefix.';
$txt['smtp_bad_response'] = 'Couldn\'t get mail server response codes';
$txt['smtp_error'] = 'Ran into problems sending Mail. Error: ';
$txt['mail_send_unable'] = 'Unable to send mail to the email address \'%1$s\'';

$txt['mlist_search'] = 'Search for Members';
$txt['mlist_search_again'] = 'Search again';
$txt['mlist_search_filter'] = 'Search options';
$txt['mlist_search_email'] = 'Search by email address';
$txt['mlist_search_messenger'] = 'Search by messenger nickname';
$txt['mlist_search_group'] = 'Search by position';
$txt['mlist_search_name'] = 'Search by name';
$txt['mlist_search_website'] = 'Search by website';
$txt['mlist_search_results'] = 'Search results for';
$txt['mlist_search_by'] = 'Search by %1$s';
$txt['mlist_menu_view'] = 'View the memberlist';

$txt['attach_downloaded'] = 'downloaded %1$d times';
$txt['attach_viewed'] = 'viewed %1$d times';

$txt['settings'] = 'Settings';
$txt['never'] = 'Never';
$txt['more'] = 'more';
$txt['etc'] = 'etc.';

$txt['hostname'] = 'Hostname';
$txt['you_are_post_banned'] = 'Sorry %1$s, you are banned from posting and sending personal messages on this forum.';
$txt['ban_reason'] = 'Reason';
$txt['select_item_check'] = 'Please select at least one item in the list';

$txt['tables_optimized'] = 'Database tables optimized';

$txt['add_poll'] = 'Add poll';
$txt['poll_options_limit'] = 'You may only select up to %1$s options.';
$txt['poll_remove'] = 'Remove Poll';
$txt['poll_remove_warn'] = 'Are you sure you want to remove this poll from the topic?';
$txt['poll_results_expire'] = 'Results will be shown when voting has closed';
$txt['poll_expires_on'] = 'Voting closes';
$txt['poll_expired_on'] = 'Voting closed';
$txt['poll_change_vote'] = 'Remove Vote';
$txt['poll_return_vote'] = 'Voting options';
$txt['poll_cannot_see'] = 'You cannot see the results of this poll at the moment.';

$txt['quick_mod_approve'] = 'Approve selected';
$txt['quick_mod_remove'] = 'Remove selected';
$txt['quick_mod_lock'] = 'Lock/Unlock selected';
$txt['quick_mod_sticky'] = 'Sticky/Unsticky selected';
$txt['quick_mod_move'] = 'Move selected to';
$txt['quick_mod_merge'] = 'Merge selected';
$txt['quick_mod_markread'] = 'Mark selected read';
$txt['quick_mod_markunread'] = 'Mark selected unread';
$txt['quick_mod_selected'] = 'With the selected options do';
$txt['quick_mod_go'] = 'Go';
$txt['quickmod_confirm'] = 'Are you sure you want to do this?';

$txt['spell_check'] = 'Spell Check';

$txt['quick_reply'] = 'Quick Reply';
$txt['quick_reply_desc'] = 'With <em>Quick-Reply</em> you can write a post when viewing a topic without loading a new page. You can still use bulletin board code and smileys as you would in a normal post.';
$txt['quick_reply_warning'] = 'Warning! This topic is currently locked, only admins and moderators can reply.';
$txt['quick_reply_verification'] = 'After submitting your post you will be directed to the regular post page to verify your post %1$s.';
$txt['quick_reply_verification_guests'] = '(required for all guests)';
$txt['quick_reply_verification_posts'] = '(required for all users with less than %1$d posts)';
$txt['wait_for_approval'] = 'Note: this post will not display until it\'s been approved by a moderator.';

$txt['notification_enable_board'] = 'Are you sure you wish to enable notification of new topics for this board?';
$txt['notification_disable_board'] = 'Are you sure you wish to disable notification of new topics for this board?';
$txt['notification_enable_topic'] = 'Are you sure you wish to enable notification of new replies for this topic?';
$txt['notification_disable_topic'] = 'Are you sure you wish to disable notification of new replies for this topic?';

// Mentions
$txt['mentions'] = 'Mentions';

// Likes
$txt['likes'] = 'Likes';
$txt['like'] = 'Like';
$txt['unlike'] = 'Unlike';
$txt['like_success'] = 'Your content was successfully liked.';
$txt['like_delete'] = 'Your content was successfully deleted.';
$txt['like_insert'] = 'Your content was successfully inserted.';
$txt['like_error'] = 'There was an error with your request.';
$txt['like_disable'] = 'Likes feature is disabled.';
$txt['not_valid_like_type'] = 'The liked type is not a valid type.';
// Translators, if you need to make more strings to suit your language, e.g. $txt['likes_2'] = 'Two people like this', please do so.
$txt['likes_1'] = '<a href="%1$s">%2$s person</a> likes this.';
$txt['likes_n'] = '<a href="%1$s">%2$s people</a> like this.';
$txt['you_likes_0'] = 'You like this.';
$txt['you_likes_1'] = 'You and <a href="%1$s">%2$s other person</a> like this.';
$txt['you_likes_n'] = 'You and <a href="%1$s">%2$s other people</a> like this.';

$txt['report_to_mod'] = 'Report to moderator';
$txt['report_profile'] = 'Report profile of %1$s';

$txt['unread_topics_visit'] = 'Recent Unread Topics';
$txt['unread_topics_visit_none'] = 'No unread topics found since your last visit. <a href="' . $scripturl . '?action=unread;all">Click here to try all unread topics</a>.';
$txt['updated_topics_visit_none'] = 'No updated topics found since your last visit. <a href="' . $scripturl . '?action=unread;all">Click here to try all unread topics</a>.';
$txt['unread_topics_all'] = 'All Unread Topics';
$txt['unread_replies'] = 'Updated Topics';

$txt['who_title'] = 'Who\'s Online';
$txt['who_and'] = ' and ';
$txt['who_viewing_topic'] = ' are viewing this topic.';
$txt['who_viewing_board'] = ' are viewing this board.';
$txt['who_member'] = 'Member';

// No longer used by default theme, but for backwards compat
$txt['powered_by_php'] = 'Powered by PHP';
$txt['powered_by_mysql'] = 'Powered by MySQL';
$txt['valid_css'] = 'Valid CSS';

// Footer strings, no longer used
$txt['valid_html'] = 'Valid HTML 4.01';
$txt['valid_xhtml'] = 'Valid XHTML 1.0';
$txt['wap2'] = 'WAP2';
$txt['rss'] = 'RSS';
$txt['atom'] = 'Atom';
$txt['xhtml'] = 'XHTML';
$txt['html'] = 'HTML';

$txt['guest'] = 'Guest';
$txt['guests'] = 'Guests';
$txt['user'] = 'User';
$txt['users'] = 'Users';
$txt['hidden'] = 'Hidden';

// Plural form of hidden for languages other than English
$txt['hidden_s'] = 'Hidden';
$txt['buddy'] = 'Buddy';
$txt['buddies'] = 'Buddies';
$txt['most_online_ever'] = 'Most Online Ever';
$txt['most_online_today'] = 'Most Online Today';

$txt['merge_select_target_board'] = 'Select the target board of the merged topic';
$txt['merge_select_poll'] = 'Select which poll the merged topic should have';
$txt['merge_topic_list'] = 'Select topics to be merged';
$txt['merge_select_subject'] = 'Select subject of merged topic';
$txt['merge_custom_subject'] = 'Custom subject';
$txt['merge_include_notifications'] = 'Include notifications?';
$txt['merge_check'] = 'Merge?';
$txt['merge_no_poll'] = 'No poll';
$txt['merge_why'] = 'Please enter a brief description as to why these topics are being merged.';
$txt['merged_subject'] = '[MERGED] %1$s';
$txt['mergetopic_default'] = 'This topic has been merged into ' . $txt['movetopic_auto_topic'] . '.';

$txt['response_prefix'] = 'Re: ';
$txt['current_icon'] = 'Current Icon';
$txt['message_icon'] = 'Message Icon';

$txt['smileys_current'] = 'Current Smiley Set';
$txt['smileys_none'] = 'No Smileys';
$txt['smileys_forum_board_default'] = 'Forum/Board Default';

$txt['search_results'] = 'Search Results';
$txt['search_no_results'] = 'Sorry, no matches were found';

$txt['total_time_logged_days'] = ' days, ';
$txt['total_time_logged_hours'] = ' hours and ';
$txt['total_time_logged_minutes'] = ' minutes.';
$txt['total_time_logged_d'] = 'd ';
$txt['total_time_logged_h'] = 'h ';
$txt['total_time_logged_m'] = 'm';

$txt['approve_members_waiting'] = 'Member Approvals';

$txt['notifyboard_turnon'] = 'Do you want a notification email when someone posts a new topic in this board?';
$txt['notifyboard_turnoff'] = 'Are you sure you do not want to receive new topic notifications for this board?';

$txt['activate_code'] = 'Your activation code is';

$txt['find_members'] = 'Find Members';
$txt['find_username'] = 'Name, username, or email address';
$txt['find_buddies'] = 'Show Buddies Only?';
$txt['find_wildcards'] = 'Allowed Wildcards: *, ?';
$txt['find_no_results'] = 'No results found';
$txt['find_results'] = 'Results';
$txt['find_close'] = 'Close';

$txt['unread_since_visit'] = 'Show unread posts since last visit.';
$txt['show_unread_replies'] = 'Show new replies to your posts.';

$txt['change_color'] = 'Change color';

$txt['quickmod_delete_selected'] = 'Remove selected';
$txt['quickmod_split_selected'] = 'Split selected';

$txt['show_personal_messages_heading'] = 'New messages';
$txt['show_personal_messages'] = 'You have <strong>%1$s</strong> unread personal messages in your inbox.<br><br><a href="%2$s">Go to your inbox</a>';

$txt['help_popup'] = 'A little lost? Let me explain:';

$txt['previous_next_back'] = 'Previous topic';
$txt['previous_next_forward'] = 'Next topic';

$txt['mark_unread'] = 'Mark unread';

$txt['ssi_not_direct'] = 'Please don\'t access SSI.php by URL directly; you may want to use the path (%1$s) or add ?ssi_function=something.';
$txt['ssi_session_broken'] = 'SSI.php was unable to load a session! This may cause problems with logout and other functions - please make sure SSI.php is included before *anything* else in all your scripts!';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['preview_title'] = 'Preview post';
$txt['preview_fetch'] = 'Fetching preview...';
$txt['preview_new'] = 'New message';
$txt['pm_error_while_submitting'] = 'The following error or errors occurred while sending this personal message:';
$txt['error_while_submitting'] = 'The message has the following error or errors that must be corrected before continuing:';
$txt['error_old_topic'] = 'Warning: this topic has not been posted in for at least %1$d days.<br>Unless you\'re sure you want to reply, please consider starting a new topic.';

$txt['split_selected_posts'] = 'Selected posts';
$txt['split_selected_posts_desc'] = 'The posts below will form a new topic after splitting.';
$txt['split_reset_selection'] = 'reset selection';

$txt['modify_cancel'] = 'Cancel';
$txt['modify_cancel_all'] = 'Cancel All';
$txt['mark_read_short'] = 'Mark Read';

$txt['alerts'] = 'Alerts';

$txt['pm_short'] = 'My Messages';
$txt['pm_menu_read'] = 'Read your messages';
$txt['pm_menu_send'] = 'Send a message';

$txt['unapproved_posts'] = 'Unapproved Posts (Topics: %1$d, Posts: %2$d)';

$txt['ajax_in_progress'] = 'Loading...';

$txt['mod_reports_waiting'] = 'Reported Posts';

$txt['view_unread_category'] = 'Unread Posts';
$txt['new_posts_in_category'] = 'Click to see the new posts in %1$s';
$txt['verification'] = 'Verification';
$txt['visual_verification_hidden'] = 'Please leave this box empty';
$txt['visual_verification_description'] = 'Type the letters shown in the picture';
$txt['visual_verification_sound'] = 'Listen to the letters';
$txt['visual_verification_request_new'] = 'Request another image';

// Sub menu labels
$txt['summary'] = 'Summary';
$txt['account'] = 'Account Settings';
$txt['theme'] = 'Look and Layout';
$txt['forumprofile'] = 'Forum Profile';
$txt['activate_changed_email_title'] = 'Email Address Changed';
$txt['activate_changed_email_desc'] = 'You\'ve changed your email address. In order to validate this address you will receive an email. Click the link in that email to reactivate your account.';
$txt['modSettings_title'] = 'Features and Options';
$txt['package'] = 'Package Manager';
$txt['errorlog'] = 'Error Log';
$txt['edit_permissions'] = 'Permissions';
$txt['mc_unapproved_attachments'] = 'Unapproved Attachments';
$txt['mc_unapproved_poststopics'] = 'Unapproved Posts and Topics';
$txt['mc_reported_posts'] = 'Reported Posts';
$txt['mc_reported_members'] = 'Reported Members';
$txt['modlog_view'] = 'Moderation Log';
$txt['calendar_menu'] = 'View Calendar';

// @todo Send email strings - should move?
$txt['send_email'] = 'Send Email';

$txt['ignoring_user'] = 'You are ignoring this user.';
$txt['show_ignore_user_post'] = 'Show me the post.';

$txt['spider'] = 'Spider';
$txt['spiders'] = 'Spiders';

$txt['downloads'] = 'Downloads';
$txt['filesize'] = 'Filesize';

// Restore topic
$txt['restore_topic'] = 'Restore Topic';
$txt['restore_message'] = 'Restore';
$txt['quick_mod_restore'] = 'Restore Selected';

// Editor prompt.
$txt['prompt_text_email'] = 'Please enter the email address.';
$txt['prompt_text_ftp'] = 'Please enter the FTP address.';
$txt['prompt_text_url'] = 'Please enter the URL you wish to link to.';
$txt['prompt_text_img'] = 'Enter image location';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['autosuggest_delete_item'] = 'Delete Item';

// Debug related - when $db_show_debug is true.
$txt['debug_templates'] = 'Templates: ';
$txt['debug_subtemplates'] = 'Sub templates: ';
$txt['debug_language_files'] = 'Language files: ';
$txt['debug_stylesheets'] = 'Style sheets: ';
$txt['debug_files_included'] = 'Files included: ';
$txt['debug_memory_use'] = 'Memory used: ';
$txt['debug_kb'] = 'KB.';
$txt['debug_show'] = 'show';
$txt['debug_cache_hits'] = 'Cache hits: ';
$txt['debug_cache_misses'] = 'Cache misses: ';
$txt['debug_cache_seconds_bytes'] = '%1$ss - %2$s bytes';
$txt['debug_cache_seconds_bytes_total'] = '%1$ss for %2$s bytes';
$txt['debug_queries_used'] = 'Queries used: %1$d.';
$txt['debug_queries_used_and_warnings'] = 'Queries used: %1$d, %2$d warnings.';
$txt['debug_query_in_line'] = 'in <em>%1$s</em> line <em>%2$s</em>, ';
$txt['debug_query_which_took'] = 'which took %1$s seconds.';
$txt['debug_query_which_took_at'] = 'which took %1$s seconds at %2$s into request.';
$txt['debug_show_queries'] = '[Show Queries]';
$txt['debug_hide_queries'] = '[Hide Queries]';
$txt['debug_tokens'] = 'Tokens: ';
$txt['debug_browser'] = 'Browser ID: ';
$txt['debug_hooks'] = 'Hooks called: ';
$txt['debug_instances'] = 'Instances created: ';
$txt['are_sure_mark_read'] = 'Are you sure you want to mark messages as read?';

// Inline attachments messages.
$txt['attachments_not_enable'] = 'Attachments are disabled';
$txt['attachments_no_data_loaded'] = 'Not a valid attachment ID.';
$txt['attachments_not_allowed_to_see'] = 'You cannot see attachments on this board.';
$txt['attachments_no_msg_associated'] = 'No message is associated with this attachment.';

// Accessibility
$txt['hide_category'] = 'Hide Category';
$txt['show_category'] = 'Show Category';
$txt['hide_infocenter'] = 'Hide Info Center';
$txt['show_infocenter'] = 'Show Info Center';

// Notification post control
$txt['notify_topic_0'] = 'Not Following';
$txt['notify_topic_1'] = 'No Alerts or Emails';
$txt['notify_topic_2'] = 'Receive Alerts';
$txt['notify_topic_3'] = 'Receive Emails and Alerts';
$txt['notify_topic_0_desc'] = 'You will not receive any emails or alerts for this topic and it will also not show up in your unread replies and topics list. You will still receive @mentions for this topic.';
$txt['notify_topic_1_desc'] = 'You will not receive any emails or alerts but only @mentions by other members.';
$txt['notify_topic_2_desc'] = 'You will receive alerts for this topic.';
$txt['notify_topic_3_desc'] = 'You will receive both alerts and e-mails for this topic.';
$txt['notify_board_1'] = 'No Alerts or Emails';
$txt['notify_board_2'] = 'Receive Alerts';
$txt['notify_board_3'] = 'Receive Emails and Alerts';
$txt['notify_board_1_desc'] = 'You will not receive any emails or alerts for new topics';
$txt['notify_board_2_desc'] = 'You will receive alerts for this board.';
$txt['notify_board_3_desc'] = 'You will receive both alerts and e-mails for this board.';

// Mobile Actions
$txt['mobile_action'] = 'User actions';
$txt['mobile_moderation'] = 'Moderation';
$txt['mobile_user_menu'] = 'Mobile Main Menu';

// Formats for lists in a sentence (e.g. "Alice, Bob, and Charlie")
// Examples:
// 	$txt['sentence_list_format'][2] specifies a format for a list with two items
// 	$txt['sentence_list_format']['n'] specifies the default format
// Notes on placeholders:
// 	{1} = first item in the list, {2} = second item, etc.
// 	{-1} = last item in the list, {-2} = second last item, etc.
// 	{series} = concatenated string of the rest of the items in the list
$txt['sentence_list_format'][1] = '{1}';
$txt['sentence_list_format'][2] = '{1} and {-1}';
$txt['sentence_list_format'][3] = '{series}, and {-1}';
$txt['sentence_list_format'][4] = '{series}, and {-1}';
$txt['sentence_list_format'][5] = '{series}, and {-1}';
$txt['sentence_list_format']['n'] = '{series}, and {-1}';
// Separators used to build lists in a sentence
$txt['sentence_list_separator'] = ', ';
$txt['sentence_list_separator_alt'] = '; ';

?>