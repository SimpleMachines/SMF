<?php

// Version: 3.0 Alpha 1; ManageMaintenance

$txt['repair_zero_ids'] = 'Found topics and/or messages with topic or message IDs of 0.';
$txt['repair_missing_topics'] = 'Message #{0, number, integer} is in non-existent topic #{1, number, integer}.';
$txt['repair_missing_messages'] = 'Topic #{0, number, integer} contains no (actual) messages.';
$txt['repair_topic_wrong_first_id'] = 'Topic #{0, number, integer} has the first message ID {1, number, integer}, which is incorrect.';
$txt['repair_topic_wrong_last_id'] = 'Topic #{0, number, integer} has the last message ID {1, number, integer}, which is incorrect.';
$txt['repair_topic_wrong_replies'] = 'Topic #{0} has {1, plural,
	one {# reply}
	other {# replies}
}, which is incorrect.';

$txt['repair_topic_wrong_unapproved_number'] = 'Topic #{0} has {1, plural,
	one {# unapproved post}
	other {# unapproved posts}
}, which is incorrect.';
$txt['repair_topic_wrong_approval'] = 'Topic #{0, number, integer} has the wrong approval flag set.';
$txt['repair_missing_boards'] = 'Topic #{0, number, integer} is in board #{1, number, integer}, which is missing.';
$txt['repair_missing_categories'] = 'Board #{0, number, integer} is in category #{1, number, integer}, which is missing.';
$txt['repair_missing_posters'] = 'Message #{0, number, integer} was posted by member #{1, number, integer}, who is now missing.';
$txt['repair_missing_parents'] = 'Board #{0, number, integer} is a sub-board of board #{1, number, integer}, which is missing.';
$txt['repair_missing_polls'] = 'Topic #{0, number, integer} is tied to non-existent poll #{1, number, integer}.';
$txt['repair_polls_missing_topics'] = 'Poll #{0, number, integer} is tied to non-existent topic #{1, number, integer}.';
$txt['repair_poll_options_missing_poll'] = 'Poll #{0} does not exist, but has {1, plural,
	one {# voting option}
	other {# voting options}
}.';
$txt['repair_missing_calendar_topics'] = 'Event #{0, number, integer} is tied to topic #{1, number, integer}, which is missing.';
$txt['repair_missing_log_topics'] = 'Topic #{0, number, integer} is marked as read for one or more people, but does not exist.';
$txt['repair_missing_log_topics_members'] = 'Member #{0, number, integer} has marked one or more topics as read, but does not exist.';
$txt['repair_missing_log_boards'] = 'Board #{0, number, integer} is marked as read for one or more people, but does not exist.';
$txt['repair_missing_log_boards_members'] = 'Member #{0, number, integer} has marked one or more boards as read, but does not exist.';
$txt['repair_missing_log_mark_read'] = 'Board #{0, number, integer} is marked as read for one or more people, but does not exist.';
$txt['repair_missing_log_mark_read_members'] = 'Member #{0, number, integer} has marked one or more boards as read, but does not exist.';
$txt['repair_missing_pms'] = 'Personal message #{0, number, integer} has been sent to one or more people, but does not exist.';
$txt['repair_missing_recipients'] = 'Member #{0, number, integer} has received one or more personal messages, but does not exist.';
$txt['repair_missing_senders'] = 'Personal message #{0, number, integer} was sent by member #{1, number, integer}, who does not exist.';
$txt['repair_missing_notify_members'] = 'Notifications have been requested by member #{0, number, integer}, who does not exist.';
$txt['repair_missing_cached_subject'] = 'The subject of topic #{0, number, integer} is currently not stored in the subject cache.';
$txt['repair_missing_topic_for_cache'] = 'Cached word "{0}" is linked to a non-existent topic.';
$txt['repair_missing_log_poll_member'] = 'Poll #{0, number, integer} has been given a vote from member #{1, number, integer} , who is now missing.';
$txt['repair_missing_log_poll_vote'] = 'A vote was cast by member #{0, number, integer} on a non-existent poll #{1, number, integer}.';
$txt['repair_missing_thumbnail_parent'] = 'A thumbnail exists called {0}, but it doesn\'t have a parent.';
$txt['repair_report_missing_comments'] = 'Report #{0, number, integer} of topic: "{1}" has no comments.';
$txt['repair_comments_missing_report'] = 'Report comment #{0, number, integer} submitted by {1} has no related report.';
$txt['repair_group_request_missing_member'] = 'A group request still exists for deleted member #{0, number, integer}.';
$txt['repair_group_request_missing_group'] = 'A group request still exists for deleted group #{0, number, integer}.';

$txt['repair_currently_checking'] = 'Checking: "{0}"';
$txt['repair_currently_fixing'] = 'Fixing: "{0}"';
$txt['repair_operation_zero_topics'] = 'Topics with id_topic incorrectly set to zero';
$txt['repair_operation_zero_messages'] = 'Messages with id_msg incorrectly set to zero';
$txt['repair_operation_missing_topics'] = 'Messages missing topic entries';
$txt['repair_operation_missing_messages'] = 'Topics without any messages';
$txt['repair_operation_stats_topics'] = 'Topics with incorrect first or last message entries';
$txt['repair_operation_stats_topics2'] = 'Topics with the wrong number of replies';
$txt['repair_operation_stats_topics3'] = 'Topics with the wrong unapproved post count';
$txt['repair_operation_missing_boards'] = 'Topics in a non-existent board';
$txt['repair_operation_missing_categories'] = 'Boards in a non-existent category';
$txt['repair_operation_missing_posters'] = 'Messages linked to non-existent members';
$txt['repair_operation_missing_parents'] = 'Sub-boards with non-existent parents';
$txt['repair_operation_missing_polls'] = 'Topics linked to non-existent polls';
$txt['repair_operation_missing_calendar_topics'] = 'Events linked to non-existent topics';
$txt['repair_operation_missing_log_topics'] = 'Topic logs linked to non-existent topics';
$txt['repair_operation_missing_log_topics_members'] = 'Topic logs linked to non-existent members';
$txt['repair_operation_missing_log_boards'] = 'Board logs linked to non-existent boards';
$txt['repair_operation_missing_log_boards_members'] = 'Board logs linked to non-existent members';
$txt['repair_operation_missing_log_mark_read'] = 'Mark read data linked to non-existent boards';
$txt['repair_operation_missing_log_mark_read_members'] = 'Mark read data linked to non-existent members';
$txt['repair_operation_missing_pms'] = 'PM recipients missing the master personal message';
$txt['repair_operation_missing_recipients'] = 'PM recipients linked to a non-existent member';
$txt['repair_operation_missing_senders'] = 'Personal messages linked to a non-existent member';
$txt['repair_operation_missing_notify_members'] = 'Notification logs linked to a non-existent member';
$txt['repair_operation_missing_cached_subject'] = 'Topics missing their search cache entries';
$txt['repair_operation_missing_topic_for_cache'] = 'Search cache entries linked to non-existent topic';
$txt['repair_operation_missing_member_vote'] = 'Poll votes linked to non-existent members';
$txt['repair_operation_missing_log_poll_vote'] = 'Poll votes linked to non-existent poll';
$txt['repair_operation_report_missing_comments'] = 'Topic reports without a comment';
$txt['repair_operation_comments_missing_report'] = 'Report comments missing the topic report';
$txt['repair_operation_group_request_missing_member'] = 'Group requests missing the requesting member';
$txt['repair_operation_group_request_missing_group'] = 'Group requests for a non-existent group';

$txt['salvaged_category_name'] = 'Salvage Area';
$txt['salvaged_category_error'] = 'Unable to create Salvage Area category!';
$txt['salvaged_category_description'] = 'Boards created for the salvaged messages';
$txt['salvaged_board_name'] = 'Salvaged Topics';
$txt['salvaged_board_description'] = 'Topics created for messages with non-existent topics';
$txt['salvaged_board_error'] = 'Unable to create Salvaged Topics board!';
$txt['salvaged_poll_topic_name'] = 'Salvaged Poll';
$txt['salvaged_poll_message_body'] = 'This poll was found without a topic.';
$txt['salvaged_poll_question'] = 'This poll was found without a question.';

$txt['database_optimize'] = 'Optimize Database';
$txt['database_numb_tables'] = '{0, plural,
	one {Your database contains # table.}
	other {Your database contains # tables.}
}';
$txt['database_optimize_attempt'] = 'Attempting to optimize your database...';
$txt['database_optimizing'] = 'Optimizing {0}... {1} KB optimized.';
$txt['database_already_optimized'] = 'All of the tables were already optimized.';
$txt['database_opimize_unneeded'] = 'It wasn\'t necessary to optimize any tables.';
$txt['database_optimized'] = ' table(s) optimized.';
$txt['database_no_id'] = 'has a non-existent member ID';

$txt['apply_filter'] = 'Apply Filter';
$txt['apply_filter_type'] = 'Apply Filter: {type}';
$txt['applying_filter'] = '<strong>Applying Filter:</strong> {type} {value}';
$txt['filter_only_member'] = 'Only show the error messages of this member';
$txt['filter_only_ip'] = 'Only show the error messages of this IP address';
$txt['filter_only_session'] = 'Only show the error messages of this session';
$txt['filter_only_url'] = 'Only show the error messages of this URL';
$txt['filter_only_message'] = 'Only show the errors with the same message';
$txt['session'] = 'Session';
$txt['error'] = 'Error';
$txt['error_url'] = 'URL of page causing the error';
$txt['error_message'] = 'Error message';
$txt['error_file'] = 'File';
$txt['error_line'] = 'Line';
$txt['error_file_and_line'] = '{file} (Line {line, number, integer})';
$txt['clear_filter'] = 'Clear filter';
$txt['remove_selection'] = 'Remove selection';
$txt['remove_filtered_results'] = 'Remove all filtered results';
$txt['sure_about_errorlog_remove'] = 'Are you sure you want to completely clear the error log?';
$txt['remove_selection_confirm'] = 'Are you sure you want to delete the selected entries?';
$txt['remove_filtered_results_confirm'] = 'Are you sure you want to delete the filtered entries?';
$txt['reverse_direction'] = 'Reverse chronological order of list';
$txt['error_type'] = 'Type of error';
$txt['error_type_name'] = 'Type of error: {type}';
$txt['filter_only_type'] = 'Only show the errors of this type';
$txt['filter_only_file'] = 'Only show the errors from this file';
$txt['apply_filter_of_type'] = 'Apply filter of type: {list}';
$txt['backtrace_title'] = 'Backtrace information';
// argument(s): error message, function, filename, line nr, filehash, Config::$scripturl
$txt['backtrace_info'] = '<b>#{0, number, integer}</b>: {1}()<br>Called from <a href="{5}?action=admin;area=logs;sa=errorlog;file={4};line={3, number, integer}" onclick="return reqWin(this.href, 600, 480, false);">{2} on line {3, number, integer}</a>';
$txt['backtrace_info_internal_function'] = '<b>#{0, number, integer}</b>: {1}()<br>Called from [internal function]';

$txt['errortype_all'] = 'All errors';
$txt['errortype_general'] = 'General';
$txt['errortype_general_desc'] = 'General errors that have not been categorized into another type';
$txt['errortype_critical'] = 'Critical';
$txt['errortype_critical_desc'] = 'Critical errors. These should be taken care of as quickly as possible. Ignoring these errors can result in your forum failing and possibly security issues';
$txt['errortype_database'] = 'Database';
$txt['errortype_database_desc'] = 'Errors caused by faulty queries. These should be looked at and reported to the SMF team.';
$txt['errortype_undefined_vars'] = 'Undefined';
$txt['errortype_undefined_vars_desc'] = 'Errors caused by the use of undefined variables, indexes, or offsets.';
$txt['errortype_ban'] = 'Bans';
$txt['errortype_ban_desc'] = 'A log of banned users trying to access your forum.';
$txt['errortype_template'] = 'Template';
$txt['errortype_template_desc'] = 'Errors related to the loading of templates.';
$txt['errortype_user'] = 'User';
$txt['errortype_user_desc'] = 'Errors resulting from user errors. Includes failed passwords, trying to login when banned, and trying to do an action for which they do not have permission.';
$txt['errortype_cron'] = 'Cron';
$txt['errortype_cron_desc'] = 'Errors resulting from background tasks.';
$txt['errortype_paidsubs'] = 'Paid Subs';
$txt['errortype_paidsubs_desc'] = 'Errors resulting from paid subscriptions, which can include notification of payment failures.';
$txt['errortype_backup'] = 'Backups';
$txt['errortype_backup_desc'] = 'Errors resulting from backing up files, which are usually messages explaining why the procedure failed.';
$txt['errortype_login'] = 'Logins';
$txt['errortype_login_desc'] = 'Errors caused by failed login attempts or brute force attempts.';

$txt['maintain_recount'] = 'Recount all forum totals and statistics';
$txt['maintain_recount_info'] = 'Should the total replies of a topic or the number of PMs in your inbox be incorrect: this function will recount all saved counts and statistics for you.';
$txt['maintain_repair'] = 'Find and repair any errors';
$txt['maintain_repair_info'] = 'Try to find and fix any errors that may prevent posts or topics from showinng up or being searchable. This should be run afer a forum conversion.';
$txt['maintain_logs'] = 'Empty out unimportant logs';
$txt['maintain_logs_info'] = 'This function will empty out all unimportant logs, xuch as the error log. This should be avoided unless something\'s wrong, but it doesn\'t hurt anything.';
$txt['maintain_cleancache'] = 'Empty SMF\'s cache';
$txt['maintain_cleancache_info'] = 'Empty out the cache should you need it to be cleared.';
$txt['maintain_optimize'] = 'Optimize all tables';
$txt['maintain_optimize_info'] = 'This task allows you to optimize all tables. This will get rid of overhead, effectively making the tables smaller in size and your forum faster.';
$txt['maintain_version'] = 'Check all files against current versions';
$txt['maintain_version_info'] = 'Runs a detailed version check of all forum files against the official list of latest versions  and displays the results.';
$txt['maintain_rebuild_settings'] = 'Rebuild Settings.php';
$txt['maintain_rebuild_settings_info'] = 'This task reconstructs your Settings.php file. It does not change the values stored in the file. Instead, it cleans up and reformats your Settings.php file to a pristine version.';
$txt['maintain_run_now'] = 'Run task now';
$txt['maintain_return'] = 'Back to Forum Maintenance';

$txt['maintain_backup'] = 'Backup Database';
$txt['maintain_backup_info'] = 'Download a backup copy of your forums database in case of emergency.';
$txt['maintain_backup_struct'] = 'Save the table structure.';
$txt['maintain_backup_data'] = 'Save the table data (the important stuff).';
$txt['maintain_backup_gz'] = 'Compress the file with gzip.';
$txt['maintain_backup_save'] = 'Download';

$txt['maintain_old'] = 'Remove old posts';
// The argument for this string is an HTML input element.
$txt['maintain_old_since_days'] = 'Remove all topics not posted in for {input_number} days, which are:';
$txt['maintain_old_nothing_else'] = 'Any sort of topic.';
$txt['maintain_old_are_moved'] = 'Moved/merged topic notices.';
$txt['maintain_old_are_locked'] = 'Locked.';
$txt['maintain_old_are_not_stickied'] = 'But don\'t count stickied topics.';
$txt['maintain_old_all'] = 'All boards (click to select specific boards)';
$txt['maintain_old_choose'] = 'Specific boards (click to select all)';
$txt['maintain_old_remove'] = 'Remove now';
$txt['maintain_old_confirm'] = 'Are you really sure you want to delete old posts now?-n-This cannot be undone!';

$txt['maintain_old_drafts'] = 'Remove old drafts';
// The argument for this string is an HTML input element.
$txt['maintain_old_drafts_days'] = 'Remove all drafts older than {input_number} days.';
$txt['maintain_old_drafts_confirm'] = 'Are you really sure you want to delete old drafts now?-n-This cannot be undone!';
$txt['maintain_members'] = 'Remove Inactive Members';
$txt['maintain_members_ungrouped'] = 'Ungrouped Members <span class="smalltext">(Members with no assigned groups)</span>';
// The arguments for this string are HTML input elements.
$txt['maintain_members_since'] = 'Remove all members who have not {input_condition} for {input_number} days.';
$txt['maintain_members_activated'] = 'activated their account';
$txt['maintain_members_logged_in'] = 'logged in';
$txt['maintain_members_all'] = 'All Membergroups';
$txt['maintain_members_choose'] = 'Selected Groups';
$txt['maintain_members_confirm'] = 'Are you sure you really want to delete these member accounts?-n-This cannot be undone!';

$txt['text_title'] = 'Convert to TEXT';
$txt['mediumtext_title'] = 'Convert to MEDIUMTEXT';
$txt['mediumtext_info'] = 'The default messages table can contain posts up to a size of 65535 characters, in order be able to store bigger texts the column must be converted to "MEDIUMTEXT". It is also possible to revert the column back to TEXT (that operation would reduce the space occupied), but <strong>only if</strong> none of the posts in your database exceed the size of 65535 characters. This condition will be verified before the conversion.';
$txt['body_checking_introduction'] = 'This function will convert the column of your database that contains the text of the messages into a "TEXT" format (currently is "MEDIUMTEXT"). This operation will allow to slightly reduce the amount of space occupied by each message (1 byte per message). If any message stored into the database is longer than 65535 characters it will be truncated and part of the text will be lost.';
$txt['exceeding_messages'] = 'The following messages are longer than 65535 characters and will be truncated by the process:';
$txt['exceeding_messages_morethan'] = '{0, plural,
	one {... and # more message.}
	other {... and # more messages.}
}';
$txt['convert_to_text'] = 'No messages are longer than 65535 characters. You can safely proceed with the conversion without losing any part of the text.';
$txt['convert_to_suggest_text'] = 'The messages body column in your database is currently set as MEDIUMTEXT, but the maximum allowed length set for the messages is lower than 65535 characters. You may free some space converting the column to TEXT.';

$txt['maintain_convertentities'] = 'Convert HTML-entities to UTF-8 characters';
$txt['maintain_convertentities_only_utf8'] = 'The database needs to be in UTF-8 format before HTML-entities can be converted to UTF-8';
$txt['maintain_convertentities_info'] = 'This function will convert all characters that are stored in the database as HTML-entities to UTF-8 characters. This is especially useful when you have just converted your forum from a character set like ISO-8859-1 while non-latin characters were used on the forum. The browser then sends all characters as HTML-entities. For example, the HTML-entity &amp;#945; represents the greek letter &#945; (alpha). Converting entities to UTF-8 will improve searching and sorting of text and reduce storage size.';
$txt['maintain_convertentities_proceed'] = 'Proceed';

// Move topics out.
$txt['move_topics_maintenance'] = 'Move Topics';
$txt['move_topics_select_board'] = 'Select Board';
// The arguments for this string are HTML input elements.
$txt['move_topics_from'] = 'Move topics from {old} to {new}';
$txt['move_topics_now'] = 'Move now';
$txt['move_topics_confirm'] = 'Are you sure you want to move ALL the topics from &quot;%board_from%&quot; to &quot;%board_to%&quot;?';
// The argument for this string is an HTML input element.
$txt['move_topics_older_than'] = 'Move topics not posted in for {input_number} days.';
$txt['move_type_sticky'] = 'Sticky topics';
$txt['move_type_locked'] = 'Locked topics';
$txt['move_zero_all'] = 'Enter 0 to move all topics';

$txt['maintain_reattribute_posts'] = 'Reattribute User Posts';
$txt['reattribute_guest_posts'] = 'Attribute posts made with';
$txt['reattribute_email'] = 'Email address of';
$txt['reattribute_username'] = 'Username of';
$txt['reattribute_current_member'] = 'Attribute posts to member';
$txt['reattribute_increase_posts'] = 'Add posts to users post count';
$txt['reattribute'] = 'Reattribute';
// Don't use entities in the below string.
$txt['reattribute_confirm'] = 'Are you sure you want to attribute all guest posts with %type% of "%find%" to member "%member_to%"?';
$txt['reattribute_confirm_username'] = 'a username';
$txt['reattribute_confirm_email'] = 'an email address';
$txt['reattribute_cannot_find_member'] = 'Could not find member to attribute posts to.';

$txt['maintain_recountposts'] = 'Recount User Posts';
$txt['maintain_recountposts_info'] = 'Run this maintenance task to update your users total post count. It will recount all (countable) posts made by each user and then update their profile post count totals';

$txt['safe_mode_enabled'] = '<a href="https://php.net/manual/en/features.safe-mode.php">safe_mode</a> is enabled on your server!<br>The backup done with this tool cannot be considered reliable!';
$txt['use_external_tool'] = 'Please consider using an external tool to backup your database, any backup created with this utility cannot be considered 100% reliable.';
$txt['zipped_file'] = 'If you want you can create a compressed (zipped) backup.';
$txt['plain_text'] = 'The best method to backup your database is to create a plain text file, a compressed package may not be completely reliable.';
$txt['enable_maintenance1'] = 'Due to the size of your forum, it is recommended to place your forum in "maintenance mode" before you start the backup.';
$txt['enable_maintenance2'] = 'To proceed, due to the size of your forum, please place your forum in "maintenance mode".';

?>