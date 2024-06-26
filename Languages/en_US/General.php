<?php

// Version: 3.0 Alpha 2; General

// Native name, please use full HTML entities to write your language's name.
$txt['native_name'] = 'English (US)';

// Locale (strftime, basename). For more information see:
//   - https://php.net/function.setlocale
$txt['lang_locale'] = 'en_US';
$txt['lang_dictionary'] = 'en';
//https://developers.google.com/recaptcha/docs/language
$txt['lang_recaptcha'] = 'en';

// Ensure you remember to use uppercase for character set strings.
$txt['lang_character_set'] = 'UTF-8';
// Character set right to left?  0 = ltr; 1 = rtl
$txt['lang_rtl'] = '0';

// Punctuation mark used to separate decimals from whole numbers. For example, '.' in '12,345.67'. HTML entities (e.g. '&nbsp;') are supported in this string.
$txt['decimal_separator'] = '.';
// Punctuation mark used to group digits in large whole numbers. For example, ',' in '12,345.67'. If your language does not group digits in large whole numbers, enter 'NULL'. HTML entities (e.g. '&nbsp;') are supported in this string.
$txt['digit_group_separator'] = ',';
// Percent format. '{0}' will be replaced by the numerical value. HTML entities (e.g. '&nbsp;') are supported in this string.
$txt['percent_format'] = '{0}%';
// Currency format. '{0}' will be replaced by the numerical value. '¤' will be replaced by the relevant currency symbol. HTML entities (e.g. '&nbsp;') are supported in this string.
$txt['currency_format'] = '¤{0}';

// Ordinal numbers.
$txt['ordinal'] = '{0, selectordinal,
	one {#st}
	two {#nd}
	few {#rd}
	other {#th}
}';
// Ordinal numbers, but spelling out values less than 10.
$txt['ordinal_spellout'] = '{0, selectordinal,
	=1 {first}
	=2 {second}
	=3 {third}
	=4 {fourth}
	=5 {fifth}
	=6 {sixth}
	=7 {seventh}
	=8 {eighth}
	=9 {ninth}
	one {#st}
	two {#nd}
	few {#rd}
	other {#th}
}';
// Interprets ordinal numbers as counting from the end. For example, "2" becomes "2nd to last". Note that some languages need to change the offset value from "offset:0" to "offset:1", but CrowdIn does not allow translators to do that. To work around this limitation, translators can set the value of the "ordinal_last_offset" string to "1". Then SMF will replace "offset:0" with "offset:1" in this string at runtime.
$txt['ordinal_last'] = '{0, selectordinal, offset:0
	=1 {last}
	one {#st to last}
	two {#nd to last}
	few {#rd to last}
	other {#th to last}
}';
// Interprets ordinal numbers as counting from the end, but spelling out values less than 10. For example, "2" becomes "second to last", but "22" becomes "22nd to last". Note that some languages need to change the offset value from "offset:0" to "offset:1", but CrowdIn does not allow translators to do that. To work around this limitation, translators can set the value of the "ordinal_last_offset" string to "1". Then SMF will replace "offset:0" with "offset:1" in this string at runtime.
$txt['ordinal_spellout_last'] = '{0, selectordinal, offset:0
	=1 {last}
	=2 {second to last}
	=3 {third to last}
	=4 {fourth to last}
	=5 {fifth to last}
	=6 {sixth to last}
	=7 {seventh to last}
	=8 {eighth to last}
	=9 {ninth to last}
	one {#st to last}
	two {#nd to last}
	few {#rd to last}
	other {#th to last}
}';
// Offset to apply when formatting "ordinal_last" and "ordinal_spellout_last" values. This is a workaround for a CrowdIn limitation that won't let translators change the offset value in those strings. For example, setting this to "1" will cause SMF to change "offset:0" to "offset:1" in those strings at runtime.
$txt['ordinal_last_offset'] = '0';

// Formats for time units.
$txt['number_of_years'] = '{0, plural,
	one {# year}
	other {# years}
}';
$txt['number_of_months'] = '{0, plural,
	one {# month}
	other {# months}
}';
$txt['number_of_weeks'] = '{0, plural,
	one {# week}
	other {# weeks}
}';
$txt['number_of_days'] = '{0, plural,
	one {# day}
	other {# days}
}';
$txt['number_of_hours'] = '{0, plural,
	one {# hour}
	other {# hours}
}';
$txt['number_of_minutes'] = '{0, plural,
	one {# minute}
	other {# minutes}
}';
$txt['number_of_seconds'] = '{0, plural,
	one {# second}
	other {# seconds}
}';

$txt['days_title'] = 'Days';
$txt['days'] = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$txt['days_short'] = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$txt['months_title'] = 'Months';
// Months must start with 1 => 'January' (or translated, of course).
$txt['months'] = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
// Months must start with 1 => 'January' (or translated, of course).
$txt['months_titles'] = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
// Months must start with 1 => 'Jan' (or translated, of course).
$txt['months_short'] = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
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

// Short form of minutes
$txt['minutes_short'] = 'mins';
// Short form of hour
$txt['hour_short'] = 'hr';
// Short form of hours
$txt['hours_short'] = 'hrs';

$txt['admin'] = 'Admin';
$txt['moderate'] = 'Moderate';

$txt['save'] = 'Save';
$txt['reset'] = 'Reset';
$txt['upload'] = 'Upload';
$txt['upload_all'] = 'Upload all';
$txt['processing'] = 'Processing...';

$txt['modify'] = 'Modify';
$txt['forum_index'] = '{forum_name} - Index';
$txt['board_name'] = 'Board name';
$txt['posts'] = 'Posts';

$txt['member'] = 'Member';
$txt['members'] = 'Members';
$txt['member_plural'] = '{0, plural,
	one {member}
	other {members}
}';
$txt['number_of_members'] = '{0, plural,
	one {# member}
	other {# members}
}';

$txt['number_of_posts'] = '{0, plural,
	one {# post}
	other {# posts}
}';
$txt['number_of_topics'] = '{0, plural,
	one {# topic}
	other {# topics}
}';
$txt['number_of_replies'] = '{0, plural,
	one {# reply}
	other {# replies}
}';
$txt['number_of_views'] = '{0, plural,
	one {# view}
	other {# views}
}';

$txt['member_postcount'] = 'Posts';
$txt['member_postcount_num'] = 'Posts: {0, number, integer}';
$txt['no_subject'] = '(No subject)';
$txt['view_profile'] = 'View profile';
$txt['guest_title'] = 'Guest';
$txt['author'] = 'Author';
$txt['on'] = 'on';
$txt['remove'] = 'Remove';
$txt['start_new_topic'] = 'Start new topic';

$txt['login'] = 'Log in';
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

$txt['choose_pass'] = 'Choose Password';
$txt['verify_pass'] = 'Verify Password';
$txt['notify_announcements'] = 'Allow the administrators to send me important news by email';

$txt['position'] = 'Position';

// argument(s): username
$txt['view_profile_of_username'] = 'View the profile of {name}';
$txt['total'] = 'Total';
$txt['website'] = 'Website';
$txt['register'] = 'Sign up';
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

$txt['page_title_number'] = '{title} - Page {pagenum, number, integer}';

$txt['lock_unlock'] = 'Lock/Unlock Topic';
$txt['post'] = 'Post';
$txt['error_occured'] = 'An error has occurred';
$txt['at'] = 'at';
$txt['by'] = 'by';
$txt['logout'] = 'Log out';
$txt['started_by'] = 'Started by';
$txt['started_by_member'] = 'Started by {member}';
$txt['started_by_member_in'] = 'Started by <strong>{member}</strong> in <em>{board}</em>';
$txt['started_by_member_time'] = 'Started by {member}, {time}';
$txt['replies'] = 'Replies';
$txt['last_post'] = 'Last post';
$txt['first_post'] = 'First post';
$txt['last_poster'] = 'Last post by';
$txt['last_post_member_date'] = 'Last post by {member} {relative, select,
	today {{date}}
	yesterday {{date}}
	other {on {date}}
}';
$txt['last_post_message'] = '<strong>Last post: </strong>{time} <span class="postby">{post_link} by {member_link}</span>';
$txt['last_post_topic'] = '{post_link}<br>by {member_link}';
$txt['last_post_updated'] = '{time}<br>by {member_link}';
$txt['post_by_member'] = '<strong>{subject}</strong> by <strong>{poster}</strong><br>';
$txt['boardindex_total_posts'] = '{posts, plural,
	one {# post}
	other {# posts}
} {topics, plural,
	one {in # topic}
	other {in # topics}
} {members, plural,
	one {by # member}
	other {by # members}
}';
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
$txt['watching_topic'] = 'Topic you are watching';
$txt['watching_this_topic'] = 'You are watching this topic, and will receive notifications about it.';
$txt['notify'] = 'Notify';
$txt['unnotify'] = 'Unnotify';

// Use numeric entities in the below string.
$txt['regards_team'] = 'Regards,
The {forum_name} Team.';

$txt['notify_replies'] = 'Notify of replies';
$txt['move_topic'] = 'Move Topic';
$txt['move_to'] = 'Move to';
$txt['pages'] = 'Pages';
$txt['users_active'] = 'Users active {minutes, plural,
	one {in the past # minute}
	other {in the past # minutes}
}: {list}';
$txt['personal_messages'] = 'Personal Messages';
$txt['reply_quote'] = 'Reply with quote';
$txt['reply'] = 'Reply';
$txt['reply_noun'] = 'Reply';
$txt['reply_number'] = 'Reply #{0, number} - ';
$txt['approve'] = 'Approve';
$txt['unapprove'] = 'Unapprove';
$txt['approve_all'] = 'approve all';
$txt['issue_warning'] = 'Issue Warning';
$txt['awaiting_approval'] = 'Awaiting approval';
$txt['attach_awaiting_approve'] = 'Attachments awaiting approval';
$txt['post_awaiting_approval'] = 'This message is awaiting approval by a moderator.';
$txt['there_are_unapproved_topics'] = 'There are {topics, plural,
	one {# topic}
	other {# topics}
} and {posts, plural,
	one {# post}
	other {# posts}
} awaiting approval in this board. Click <a href="{url}">here</a> to view them all.';
$txt['send_message'] = 'Send message';

$txt['msg_alert'] = '{total, plural,
	=0 {you don’t have any messages}
	one {you have <a href="{url}"># message</a> {unread, plural,
		=0 {}
		one {, # is new}
		other {, # are new}
	}}
	other {you have <a href="{url}"># messages</a> {unread, plural,
		=0 {}
		one {, # is new}
		other {, # are new}
	}}
}';
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
$txt['welcome_newest_member'] = 'Please welcome {member_link}, our newest member.';
$txt['admin_center'] = 'Administration Center';
$txt['last_edit_by'] = '<span class="lastedit">Last Edit</span>: {time} by {member}';
$txt['last_edit_reason'] = '<span id="reason" class="lastedit">Reason</span>: {reason}';
$txt['notify_deactivate'] = 'Would you like to deactivate notification on this topic?';
$txt['modified_time'] = 'Last edited';
$txt['modified_by'] = 'Edited by';

$txt['recent_posts'] = 'Recent posts';

$txt['location'] = 'Location';
$txt['location_desc'] = 'Geographic location.';
$txt['gender'] = 'Gender';
$txt['gender_0'] = 'None';
$txt['gender_1'] = 'Male';
$txt['gender_2'] = 'Female';
$txt['gender_desc'] = 'Your gender.';
$txt['icq'] = 'ICQ';
$txt['icq_desc'] = 'This is your ICQ number.';
$txt['skype'] = 'Skype';
$txt['skype_desc'] = 'Your Skype username';
$txt['personal_text'] = 'Personal text';
$txt['date_registered'] = 'Date registered';

$txt['recent_view'] = 'View the most recent posts on the forum.';
$txt['recent_updated'] = 'is the most recently updated topic';
$txt['is_recent_updated'] = '{link} is the most recently updated topic';

$txt['male'] = 'Male';
$txt['female'] = 'Female';

$txt['error_invalid_characters_username'] = 'Invalid character used in Username.';

$txt['welcome_guest'] = 'Welcome to <strong>{forum_name}</strong>. Please <a href="{login_url}" onclick="{onclick}">log in</a>.';
$txt['welcome_guest_register'] = 'Welcome to <strong>{forum_name}</strong>. Please <a href="{login_url}" onclick="{onclick}">log in</a> or <a href="{register_url}">sign up</a>.';
$txt['welcome_guest_activate'] = '<a href="{scripturl}?action=activate">Did you miss your activation email?</a>';
$txt['register_prompt'] = 'Don’t have an account? <a href="{scripturl}?action=signup">Sign up</a>.';
$txt['welcome_to_forum'] = 'Welcome to <strong>{forum_name}</strong>.';

// @todo the following to sprintf
$txt['hello_member'] = 'Hey,';
// Use numeric entities in the below string.
$txt['hello_guest'] = 'Welcome,';

$txt['hello_user'] = 'Hello, {name}.';
$txt['select_destination'] = 'Please select a destination';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['posted_by'] = 'Posted by';
$txt['posted_by_member_time'] = 'Posted by {member}, {time}';

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
$txt['moderators_list'] = '{num, plural,
	one {Moderator}
	other {Moderators}
}: {list}';

$txt['views'] = 'Views';
$txt['new'] = 'New';

$txt['view_all_members'] = 'View all Members';
$txt['view'] = 'View';

$txt['viewing_members'] = 'Viewing Members {0,number,integer} to {1,number,integer}';
$txt['of_total_members'] = '{0, plural,
	one {of # total members}
	other {of # total members}
}';

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
$txt['new_posts_stats'] = 'New Posts ({topics, plural,
	one {# topic}
	other {# topics}
}, {posts, plural,
	one {# post}
	other {# posts}
})';
$txt['old_posts_stats'] = 'No New Posts ({topics, plural,
	one {# topic}
	other {# topics}
}, {posts, plural,
	one {# post}
	other {# posts}
})';
$txt['redirect_board'] = 'Redirect Board';
$txt['number_of_redirects'] = '{0, plural,
	one {# redirect}
	other {# redirects}
}';

$txt['sendtopic_send'] = 'Send';
$txt['report_sent'] = 'Your report has been sent successfully.';
$txt['post_becomes_unapproved'] = 'Your message was not approved because it was posted in an unapproved topic. Once the topic is approved your message will be approved too.';

$txt['time_offset'] = 'Time Offset';
$txt['or'] = 'or';

$txt['no_matches'] = 'Sorry, no matches were found';

$txt['notification'] = 'Notification';

$txt['your_ban'] = 'Sorry {name}, you are banned from using this forum!';
$txt['your_ban_expires'] = 'This ban is set to expire {datetime}.';
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
$txt['always_logged_in'] = 'Forever';

$txt['preview'] = 'Preview';

$txt['logged'] = 'Logged';
$txt['show_ip'] = 'Show IP address';
// Use numeric entities in the below string.
$txt['ip'] = 'IP';
$txt['url'] = 'URL';
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
$txt['maintenance_page'] = 'You can turn off Maintenance Mode from the <a href="{url}">Server Settings</a> area.';

$txt['number_of_times_read'] = '{0, plural,
	one {Read # time}
	other {Read # times}
}';

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
$txt['info_center_title'] = '{forum_name} - Info Center';

$txt['watch'] = 'Watch';
$txt['unwatch'] = 'Stop watching';

$txt['check_all'] = 'Select all';

// Use numeric entities in the below string.
$txt['database_error'] = 'Database Error';
$txt['try_again'] = 'Please try again. If you come back to this error screen, report the error to an administrator.';
$txt['file'] = 'File';
$txt['line'] = 'Line';
// Use numeric entities in the below string.
$txt['tried_to_repair'] = 'SMF has detected and automatically tried to repair an error in your database. If you continue to have problems, or continue to receive these emails, please contact your host.';
$txt['template_parse_error'] = 'Template Parse Error!';
$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system. This problem should only be temporary, so please come back later and try again. If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
// argument(s): filename, Config::$boardurl, Config::$scripturl
$txt['template_parse_error_details'] = 'There was a problem loading the <pre><strong>{filename}</strong></pre> template or language file. Please check the syntax and try again - remember, single quotes (<pre>&apos;</pre>) often have to be escaped with a slash (<pre>\\</pre>). To see more specific error information from PHP, try <a href="{boardurl}{filename}">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="{scripturl}?theme=1">use the default theme</a>.';
$txt['template_parse_errmsg'] = 'Unfortunately more information is not available at this time as to exactly what is wrong.';

$txt['today'] = '<strong>Today</strong> at ';
$txt['yesterday'] = '<strong>Yesterday</strong> at ';
$txt['new_poll'] = 'New poll';
$txt['poll_question'] = 'Question';
$txt['poll_vote'] = 'Submit Vote';
$txt['poll_total_voters'] = 'Total Members Voted: <strong>{0, number, integer}</strong>';
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
$txt['moved'] = 'MOVED: {subject}';
$txt['move_why'] = 'Please enter a brief description as to<br>why this topic is being moved.';
$txt['board'] = 'Board';
$txt['in'] = 'in';
$txt['topic_in_board'] = '{topic_link}<br><span class="smalltext"><em>in {board_link}</em></span>';
$txt['sticky_topic'] = 'Sticky Topic';

$txt['delete'] = 'Delete';
$txt['no_change'] = 'No change';

$txt['your_pms'] = 'Your Personal Messages';

$txt['kilobyte'] = 'KB';
$txt['megabyte'] = 'MB';
$txt['size_kilobyte'] = '{0, number} KB';
$txt['size_megabyte'] = '{0, number} MB';

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
$txt['merge_desc'] = 'This function will merge the messages of two topics into one topic. The messages will be sorted according to the time of posting. Therefore, the earliest posted message will be the first message of the merged topic.';

$txt['set_sticky'] = 'Set topic sticky';
$txt['set_nonsticky'] = 'Set topic non-sticky';
$txt['set_lock'] = 'Lock topic';
$txt['set_unlock'] = 'Unlock topic';

$txt['search_advanced'] = 'Advanced search';

$txt['security_risk'] = 'MAJOR SECURITY RISK:';
$txt['not_removed'] = 'You have not removed ';
$txt['not_removed_extra'] = '{backup_filename} is a backup of {filename} that was not generated by SMF. It can be accessed directly and used to gain unauthorized access to your forum. You should delete it immediately.';
$txt['generic_warning'] = 'Warning';
$txt['agreement_missing'] = 'You are requiring new users to accept a registration agreement; however, the file (agreement.txt) does not exist.';
$txt['policy_agreement_missing'] = 'You are requiring new users to accept a privacy policy; however, the privacy policy is empty.';
$txt['auth_secret_missing'] = 'Unable to set authentication secret in Settings.php. This weakens the security of your forum and puts it at risk for attacks. Check the file permissions on Settings.php to make sure SMF can write to the file.';

$txt['cache_writable'] = 'The cache directory is not writable - this will adversely affect the performance of your forum.';

$txt['page_created_full'] = '{0, plural,
	one {Page created in {0, number, :: .000} second}
	other {Page created in {0, number, :: .000} seconds}
} {1, plural,
	one {with # query.}
	other {with # queries.}
}';

$txt['report_to_mod_func'] = 'Use this function to inform the moderators and administrators of an abusive or problematic message.';
$txt['report_profile_func'] = 'Use this function to inform the administrators of abusive profile content, such as spam or inappropriate images.';

$txt['online'] = 'Online';
$txt['member_is_online'] = '{name} is online';
$txt['offline'] = 'Offline';
$txt['member_is_offline'] = '{name} is offline';
$txt['pm_online'] = 'Personal Message (Online)';
$txt['pm_offline'] = 'Personal Message (Offline)';
$txt['status'] = 'Status';

$txt['go_up'] = 'Go Up';
$txt['go_down'] = 'Go Down';

// argument(s): SMF_FULL_VERSION, SMF_SOFTWARE_YEAR, Config::$scripturl
$forum_copyright = '<a href="{scripturl}?action=credits" title="License" target="_blank" rel="noopener">{version} &copy; {year}</a>, <a href="https://www.simplemachines.org" title="Simple Machines" target="_blank" rel="noopener">Simple Machines</a>';

$txt['movetopic_change_subject'] = 'Change the topic’s subject';
$txt['movetopic_new_subject'] = 'New subject';
$txt['movetopic_change_all_subjects'] = 'Change every message’s subject';
$txt['move_topic_unapproved_js'] = 'Warning! This topic has not yet been approved.\\n\\nIt is not recommended that you create a redirection topic unless you intend to approve the post immediately following the move.';
$txt['movetopic_auto_board'] = '[BOARD]';
$txt['movetopic_auto_topic'] = '[TOPIC LINK]';
$txt['movetopic_default'] = 'This topic has been moved to {board_link}.

{topic_link}';

$txt['movetopic_redirect'] = 'Redirect to the moved topic';

$txt['post_redirection'] = 'Post a redirection topic';
$txt['redirect_topic_expires'] = 'Automatically remove the redirection topic';
$txt['mergetopic_redirect'] = 'Redirect to the merged topic';
$txt['merge_topic_unapproved_js'] = 'Warning! This topic has not yet been approved.\\n\\nIt is not recommended that you create a redirection topic unless you intend to approve the post immediately following the merge.';

$txt['theme_template_error'] = '{type, select,
	sub {Unable to load the {template_name} sub-template.}
	other {Unable to load the {template_name} template file.}
}';
$txt['theme_language_error'] = 'Unable to load the {filename} language file.';

$txt['sub_boards'] = 'Sub-Boards';
$txt['sub_boards_list'] = '<strong id="{id}">{num, plural,
	one {Sub-Board}
	other {Sub-Boards}
}</strong> {list}';
$txt['restricted_board'] = 'Restricted Board';

$txt['smtp_no_connect'] = 'Could not connect to SMTP host: {error_number}: {error_message}';
$txt['smtp_port_ssl'] = 'SMTP port setting incorrect; it should be 465 for SSL servers. Hostname may need ssl:// prefix.';
$txt['smtp_bad_response'] = 'Could not get mail server response codes';
$txt['smtp_error'] = 'Ran into problems sending mail. Error: {0}';
$txt['mail_send_unable'] = 'Unable to send mail to the email address {0}';

$txt['mlist_search'] = 'Search for Members';
$txt['mlist_search_again'] = 'Search again';
$txt['mlist_search_filter'] = 'Search options';
$txt['mlist_search_email'] = 'Search by email address';
$txt['mlist_search_messenger'] = 'Search by messenger nickname';
$txt['mlist_search_group'] = 'Search by position';
$txt['mlist_search_name'] = 'Search by name';
$txt['mlist_search_website'] = 'Search by website';
$txt['mlist_search_results'] = 'Search results for';
$txt['mlist_search_by'] = 'Search by {field}';
$txt['mlist_menu_view'] = 'View the memberlist';

$txt['attach_downloaded'] = '{0, plural,
	one {downloaded # time}
	other {downloaded # times}
}';
$txt['attach_viewed'] = '{0, plural,
	one {viewed # time}
	other {viewed # times}
}';

$txt['settings'] = 'Settings';
$txt['never'] = 'Never';
$txt['more'] = 'more';
$txt['etc'] = 'etc.';
$txt['unknown'] = 'unknown';

$txt['hostname'] = 'Hostname';
$txt['you_are_post_banned'] = 'Sorry {name}, you are banned from posting and sending personal messages on this forum.';
$txt['ban_reason'] = 'Reason';
$txt['select_item_check'] = 'Please select at least one item in the list';

$txt['tables_optimized'] = 'Database tables optimized';

$txt['add_poll'] = 'Add poll';
$txt['poll_options_limit'] = '{0, plural,
	one {You may only select up to # option.}
	other {You may only select up to # options.}
}';
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
$txt['quick_reply_warning'] = 'Warning! This topic is currently locked, only admins and moderators can reply.';
$txt['wait_for_approval'] = 'Note: this post will not display until it has been approved by a moderator.';

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
$txt['likes_count'] = '{num, plural,
	one {<a href="{url}"># person</a> likes this.}
	other {<a href="{url}"># people</a> like this.}
}';
$txt['you_likes_count'] = '{num, plural,
	=0 {You like this.}
	one {You and <a href="{url}"># other person</a> like this.}
	other {You and <a href="{url}"># other people</a> like this.}
}';

$txt['report_to_mod'] = 'Report to moderator';
$txt['report_profile'] = 'Report profile of {member_name}';

$txt['unread_topics_visit'] = 'Recent Unread Topics';
// argument(s): scripturl
$txt['unread_topics_visit_none'] = 'No unread topics found since your last visit. <a href="{scripturl}?action=unread;all">Click here to try all unread topics</a>.';
$txt['updated_topics_visit_none'] = 'No updated topics found since your last visit.';
$txt['unread_topics_all'] = 'All Unread Topics';
$txt['unread_replies'] = 'Updated Topics';

$txt['who_title'] = 'Who’s Online';
$txt['who_and'] = ' and ';
$txt['who_viewing_topic'] = '{num_viewing, plural,
	one {{list_of_viewers} is viewing this topic.}
	other {{list_of_viewers} are viewing this topic.}
}';
$txt['who_viewing_board'] = '{num_viewing, plural,
	one {{list_of_viewers} is viewing this board.}
	other {{list_of_viewers} are viewing this board.}
}';
$txt['who_member'] = 'Member';

// No longer used by default theme, but for backwards compat
$txt['powered_by_php'] = 'Powered by PHP';
$txt['powered_by_mysql'] = 'Powered by MySQL';
$txt['valid_css'] = 'Valid CSS';

$txt['rss'] = 'RSS';
$txt['atom'] = 'Atom';
$txt['html'] = 'HTML';

$txt['guest'] = 'Guest';
$txt['guests'] = 'Guests';
$txt['guest_plural'] = '{0, plural,
	one {guest}
	other {guests}
}';
$txt['number_of_guests'] = '{0, plural,
	one {# guest}
	other {# guests}
}';

$txt['user'] = 'User';
$txt['users'] = 'Users';
$txt['user_plural'] = '{0, plural,
	one {user}
	other {users}
}';
$txt['number_of_users'] = '{0, plural,
	one {# user}
	other {# users}
}';

$txt['buddy'] = 'Buddy';
$txt['buddies'] = 'Buddies';
$txt['buddy_plural'] = '{0, plural, {
	one {buddy}
	other {buddies}
}';
$txt['number_of_buddy'] = '{0, plural, {
	one {# buddy}
	other {# buddies}
}';

$txt['hidden'] = 'Hidden';
$txt['hidden_plural'] = '{0, plural, {
	one {hidden}
	other {hidden}
}';
$txt['number_of_hidden_members'] = '{0, plural, {
	one {# hidden}
	other {# hidden}
}';

$txt['most_online_ever'] = 'Most Online Ever';
$txt['most_online_today'] = 'Most Online Today';

$txt['merge_select_target_board'] = 'Select the target board of the merged topic';
$txt['merge_select_poll'] = 'Select which poll the merged topic should have';
$txt['merge_topic_list'] = 'Select topics to be merged';
$txt['merge_select_subject'] = 'Select subject of merged topic';
$txt['merge_custom_subject'] = 'Custom subject...';
$txt['merge_include_notifications'] = 'Include notifications?';
$txt['merge_check'] = 'Merge?';
$txt['merge_no_poll'] = 'No poll';
$txt['merge_why'] = 'Please enter a brief description as to why these topics are being merged.';
$txt['merged_subject'] = '[MERGED] {subject}';
$txt['mergetopic_default'] = 'This topic has been merged into {topic_link}.';

$txt['response_prefix'] = 'Re: ';
$txt['current_icon'] = 'Current Icon';
$txt['message_icon'] = 'Message Icon';

$txt['smileys_current'] = 'Current Smiley Set';
$txt['smileys_none'] = 'No Smileys';
$txt['smileys_forum_board_default'] = 'Forum/Board Default';

$txt['search_results'] = 'Search Results';
$txt['search_results_for'] = 'Search Results for {params}';
$txt['search_no_results'] = 'Sorry, no matches were found';

$txt['total_time_logged_d'] = 'd ';
$txt['total_time_logged_h'] = 'h ';
$txt['total_time_logged_m'] = 'm';

$txt['approve_members_waiting'] = 'Member Approvals';

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
$txt['show_personal_messages'] = '{num, plural,
	one {You have <strong>#</strong> unread personal message in your inbox.}
	other {You have <strong>#</strong> unread personal messages in your inbox.}
}<br><br><a href="{url}">Go to your inbox</a>';

$txt['help_popup'] = 'A little lost? Let me explain:';

$txt['previous_next_back'] = 'Previous topic';
$txt['previous_next_forward'] = 'Next topic';

$txt['mark_unread'] = 'Mark unread';

$txt['ssi_not_direct'] = 'Please do not access SSI.php by URL directly; you may want to use the path ({path}) or add ?ssi_function=something.';
$txt['ssi_session_broken'] = 'SSI.php was unable to load a session! This may cause problems with logout and other functions - please make sure SSI.php is included before *anything* else in all your scripts!';

// Escape any single quotes in here twice.. 'it\'s' -> 'it\\\'s'.
$txt['preview_title'] = 'Preview post';
$txt['preview_subject'] = 'Preview of {subject}';
$txt['preview_fetch'] = 'Fetching preview...';
$txt['preview_new'] = 'New message';
$txt['pm_error_while_submitting'] = 'The following error or errors occurred while sending this personal message:';
$txt['error_while_submitting'] = 'The message has the following error or errors that must be corrected before continuing:';
$txt['error_old_topic'] = 'Warning: {0, plural,
	one {this topic has not been posted in for at least # day.}
	other {this topic has not been posted in for at least # days.}
}<br>Unless you are sure you want to reply, please consider starting a new topic.';

$txt['split_selected_posts'] = 'Selected posts ({reset_link})';
$txt['split_selected_posts_desc'] = 'The posts below will form a new topic after splitting.';
$txt['split_reset_selection'] = 'reset selection';

$txt['modify_cancel'] = 'Cancel';
$txt['modify_cancel_all'] = 'Cancel All';
$txt['mark_read_short'] = 'Mark Read';

$txt['alerts'] = 'Alerts';
$txt['alerts_member'] = 'Alerts for {member}';

$txt['pm_short'] = 'My Messages';
$txt['pm_menu_read'] = 'Read your messages';
$txt['pm_menu_send'] = 'Send a message';

$txt['unapproved_posts'] = 'Unapproved Posts ({unapproved_topics, plural,
	one {# topic}
	other {# topics}
}, {unapproved_posts, plural,
	one {# post}
	other {# posts}
})';

$txt['ajax_in_progress'] = 'Loading...';

$txt['mod_reports_waiting'] = 'Reported Posts';

$txt['view_unread_category'] = 'Unread Posts';
$txt['new_posts_in_category'] = 'Click to see the new posts in {cat_name}';
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
$txt['activate_changed_email_desc'] = 'You have changed your email address. In order to validate this address you will receive an email. Click the link in that email to reactivate your account.';
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
$txt['spider_plural'] = '{0, plural,
	one {spider}
	other {spiders}
}';
$txt['number_of_spiders'] = '{0, plural,
	one {# spider}
	other {# spiders}
}';

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

// Debug related - when Config::$db_show_debug is true.
$txt['debug_templates'] = 'Templates: {num, number, integer} {additional_info}';
$txt['debug_subtemplates'] = 'Sub templates: {num, number, integer} {additional_info}';
$txt['debug_language_files'] = 'Language files: {num, number, integer} {additional_info}';
$txt['debug_stylesheets'] = 'Style sheets: {num, number, integer} {additional_info}';
$txt['debug_files_included'] = 'Files included: {num, number, integer} - {size, number, integer} KB {additional_info}';
$txt['debug_memory_use'] = 'Memory used: {size, number, integer} KB';
$txt['debug_show'] = 'show';
$txt['debug_cache_hits'] = 'Cache hits: {num, number, integer} - {seconds_bytes_total} {additional_info}';
$txt['debug_cache_misses'] = 'Cache misses: {num, number, integer} {additional_info}';
$txt['debug_cache_seconds_bytes'] = '{seconds, number, integer}s - {bytes, plural,
	one {# byte}
	other {# bytes}
}';
$txt['debug_cache_seconds_bytes_total'] = '{seconds, number, integer}s for {bytes, plural,
	one {# byte}
	other {# bytes}
}';
$txt['debug_queries_used'] = '{0, plural,
	one {# query used}
	other {# queries used}
}.';
$txt['debug_queries_used_and_warnings'] = '{0, plural,
	one {# query used}
	other {# queries used}
}, {1, plural,
	one {with # warning}
	other {with # warnings}
}.';
$txt['debug_query_in_line'] = 'in {file} on line {line}, ';
$txt['debug_query_which_took'] = '{0, plural,
	one {which took {0, number, ::precision-unlimited} second}
	other {which took {0, number, ::precision-unlimited} seconds}
}.';
$txt['debug_query_which_took_at'] = '{0, plural,
	one {which took {0, number, ::precision-unlimited} second}
	other {which took {0, number, ::precision-unlimited} seconds}
} at {1, number} into request.';
$txt['debug_show_queries'] = '[Show Queries]';
$txt['debug_hide_queries'] = '[Hide Queries]';
$txt['debug_tokens'] = 'Tokens: {additional_info}';
$txt['debug_browser'] = 'Browser ID: {browser_body_id} {additional_info}';
$txt['debug_hooks'] = 'Hooks called: {num, number, integer} {additional_info}';
$txt['are_sure_mark_read'] = 'Are you sure you want to mark messages as read?';

// Inline attachments messages.
$txt['attachments_not_enable'] = 'Attachments are disabled';
$txt['attachments_no_data_loaded'] = 'Not a valid attachment ID.';
$txt['attachments_not_allowed_to_see'] = 'You cannot view this attachment.';
$txt['attachments_no_msg_associated'] = 'No message is associated with this attachment.';
$txt['attachments_unapproved'] = 'Attachment is awaiting approval.';

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

$txt['notify_board_prompt'] = 'Do you want a notification email when someone posts a new topic in this board?';
$txt['notify_board_subscribed'] = '{email} has been subscribed to new topic notifications for this board.';
$txt['notify_board_unsubscribed'] = '{email} has been unsubscribed from new topic notifications for this board.';

$txt['notify_topic_prompt'] = 'Do you want a notification email if someone replies to this topic?';
$txt['notify_topic_subscribed'] = '{email} has been subscribed to new reply notifications for this topic.';
$txt['notify_topic_unsubscribed'] = '{email} has been unsubscribed from new reply notifications for this topic.';

$txt['notify_announcements_prompt'] = 'Do you want to receive forum newsletters, announcements and important notifications by email?';
$txt['notify_announcements_subscribed'] = '{email} has been subscribed to forum newsletters, announcements and important notifications.';
$txt['notify_announcements_unsubscribed'] = '{email} has been unsubscribed from forum newsletters, announcements and important notifications.';

$txt['unsubscribe_announcements_plain'] = 'To unsubscribe from forum newsletters, announcements and important notifications, follow this link: {url}';
$txt['unsubscribe_announcements_html'] = '<span style="font-size:small"><a href="{url}">Unsubscribe</a> from forum newsletters, announcements and important notifications.</span>';
$txt['unsubscribe_announcements_manual'] = 'To unsubscribe from forum newsletters, announcements and important notifications, contact us at {email} with your request.';

// Mobile Actions
$txt['mobile_action'] = 'User actions';
$txt['mobile_moderation'] = 'Moderation';
$txt['mobile_user_menu'] = 'Main Menu';
$txt['mobile_generic_menu'] = '{label} Menu';

// Punctuation mark that is normally used to separate list items in a sentence.
$txt['sentence_list_separator'] = ',';

// Formats for lists in a sentence (e.g. "Alice, Bob, and Charlie"). The options "1", "start", "middle", "end", and "other" are required for all languages. Some languages may also need additional numerical options, which allow special handling of a specific number of list items. The "start", "middle", and "end" options are used for constructing any list that is not covered by one of the numerical options. The "other" option is intentionally empty.
$txt['sentence_list_pattern']['and'] = '{list_pattern_part, select,
	1 {{0}}
	2 {{0} and {1}}
	start {{0}, {1}}
	middle {{0}, {1}}
	end {{0}, and {1}}
	other {}
}';
$txt['sentence_list_pattern']['or'] = '{list_pattern_part, select,
	1 {{0}}
	2 {{0} or {1}}
	start {{0}, {1}}
	middle {{0}, {1}}
	end {{0}, or {1}}
	other {}
}';
$txt['sentence_list_pattern']['xor'] = '{list_pattern_part, select,
	1 {{0}}
	2 {either {0} or {1}}
	start {either {0}, {1}}
	middle {{0}, {1}}
	end {{0}, or {1}}
	other {}
}';
// Alternative formats for lists in a sentence. These are used when the list items contain the punctuation mark that normally separates items in the list (e.g. "London, England; Paris, France; and Tokyo, Japan").
$txt['sentence_list_pattern']['and_alt'] = '{list_pattern_part, select,
	1 {{0}}
	2 {{0}, and {1}}
	start {{0}; {1}}
	middle {{0}; {1}}
	end {{0}; and {1}}
	other {}
}';
$txt['sentence_list_pattern']['or_alt'] = '{list_pattern_part, select,
	1 {{0}}
	2 {{0}, or {1}}
	start {{0}; {1}}
	middle {{0}; {1}}
	end {{0}; or {1}}
	other {}
}';
$txt['sentence_list_pattern']['xor_alt'] = '{list_pattern_part, select,
	1 {{0}}
	2 {either {0}, or {1}}
	start {either {0}; {1}}
	middle {{0}; {1}}
	end {{0}; or {1}}
	other {}
}';

?>