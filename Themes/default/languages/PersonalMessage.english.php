<?php
// Version: 2.0; PersonalMessage

global $context;

$txt['pm_inbox'] = 'Personal Messages Index';
$txt['send_message'] = 'Send message';
$txt['pm_add'] = 'Add';
$txt['make_bcc'] = 'Add BCC';
$txt['pm_to'] = 'To';
$txt['pm_bcc'] = 'Bcc';
$txt['inbox'] = 'Inbox';
$txt['conversation'] = 'Conversation';
$txt['messages'] = 'Messages';
$txt['sent_items'] = 'Sent Items';
$txt['new_message'] = 'New Message';
$txt['delete_message'] = 'Delete Messages';
// Don't translate "PMBOX" in this string.
$txt['delete_all'] = 'Delete all messages in your PMBOX';
$txt['delete_all_confirm'] = 'Are you sure you want to delete all messages?';
$txt['recipient'] = 'Recipient';
// Don't translate the word "SUBJECT" here, as it is used to format the message - use numeric entities as well.
$txt['new_pm_subject'] = 'New Personal Message: SUBJECT';
// Don't translate SENDER or MESSAGE in this language string; they are replaced with the corresponding text - use numeric entities too.
$txt['pm_email'] = 'You have just been sent a personal message by SENDER on ' . $context['forum_name'] . '.' . "\n\n" . 'IMPORTANT: Remember, this is just a notification. Please do not reply to this email.' . "\n\n" . 'The message they sent you was:' . "\n\n" . 'MESSAGE';
$txt['pm_multiple'] = '(multiple recipients: \'name1, name2\')';
// Use numeric entities in the below string.
$txt['instant_reply'] = 'Reply to this Personal Message here:';

$txt['delete_selected_confirm'] = 'Are you sure you want to delete all selected personal messages?';

$txt['sent_to'] = 'Sent to';
$txt['reply_to_all'] = 'Reply to All';
$txt['delete_conversation'] = 'Delete Conversation';

$txt['pm_capacity'] = 'Capacity';
$txt['pm_currently_using'] = '%1$s messages, %2$s%% full.';
$txt['pm_sent'] = 'Your message has been sent successfully.';

$txt['pm_error_user_not_found'] = 'Unable to find member \'%1$s\'.';
$txt['pm_error_ignored_by_user'] = 'User \'%1$s\' has blocked your personal message.';
$txt['pm_error_data_limit_reached'] = 'PM could not be sent to \'%1$s\' as their inbox is full!';
$txt['pm_error_user_cannot_read'] = 'User \'%1$s\' can not receive personal messages.';
$txt['pm_successfully_sent'] = 'PM successfully sent to \'%1$s\'.';
$txt['pm_send_report'] = 'Send report';
$txt['pm_save_outbox'] = 'Save a copy in my outbox';
$txt['pm_undisclosed_recipients'] = 'Undisclosed recipients';
$txt['pm_too_many_recipients'] = 'You may not send personal messages to more than %1$d recipient(s) at once.';

$txt['pm_read'] = 'Read';
$txt['pm_replied'] = 'Replied To';

// Message Pruning.
$txt['pm_prune'] = 'Prune Messages';
$txt['pm_prune_desc1'] = 'Delete all personal messages older than';
$txt['pm_prune_desc2'] = 'days.';
$txt['pm_prune_warning'] = 'Are you sure you wish to prune your personal messages?';

// Actions Drop Down.
$txt['pm_actions_title'] = 'Further Actions';
$txt['pm_actions_delete_selected'] = 'Delete Selected';
$txt['pm_actions_filter_by_label'] = 'Filter By Label';
$txt['pm_actions_go'] = 'Go';

// Manage Labels Screen.
$txt['pm_apply'] = 'Apply';
$txt['pm_manage_labels'] = 'Manage Labels';
$txt['pm_labels_delete'] = 'Are you sure you wish to delete the selected labels?';
$txt['pm_labels_desc'] = 'From here you can add, edit and delete the labels used in your personal message center.';
$txt['pm_label_add_new'] = 'Add New Label';
$txt['pm_label_name'] = 'Label Name';
$txt['pm_labels_no_exist'] = 'You currently have no labels setup!';

// Labeling Drop Down.
$txt['pm_current_label'] = 'Label';
$txt['pm_msg_label_title'] = 'Label Message';
$txt['pm_msg_label_apply'] = 'Add Label';
$txt['pm_msg_label_remove'] = 'Remove Label';
$txt['pm_msg_label_inbox'] = 'Inbox';
$txt['pm_sel_label_title'] = 'Label Selected';

// Sidebar Headings.
$txt['pm_labels'] = 'Labels';
$txt['pm_messages'] = 'Messages';
$txt['pm_actions'] = 'Actions';
$txt['pm_preferences'] = 'Preferences';

$txt['pm_is_replied_to'] = 'You have forwarded or responded to this message.';

// Reporting messages.
$txt['pm_report_to_admin'] = 'Report To Admin';
$txt['pm_report_title'] = 'Report Personal Message';
$txt['pm_report_desc'] = 'From this page you can report the personal message you received to the admin team of the forum. Please be sure to include a description of why you are reporting the message, as this will be sent along with the contents of the original message.';
$txt['pm_report_admins'] = 'Administrator to send report to';
$txt['pm_report_all_admins'] = 'Send to all forum administrators';
$txt['pm_report_reason'] = 'Reason why you are reporting this message';
$txt['pm_report_message'] = 'Report Message';

// Important - The following strings should use numeric entities.
$txt['pm_report_pm_subject'] = '[REPORT] ';
// In the below string, do not translate "{REPORTER}" or "{SENDER}".
$txt['pm_report_pm_user_sent'] = '{REPORTER} has reported the below personal message, sent by {SENDER}, for the following reason:';
$txt['pm_report_pm_other_recipients'] = 'Other recipients of the message include:';
$txt['pm_report_pm_hidden'] = '%1$d hidden recipient(s)';
$txt['pm_report_pm_unedited_below'] = 'Below are the original contents of the personal message which was reported:';
$txt['pm_report_pm_sent'] = 'Sent:';

$txt['pm_report_done'] = 'Thank you for submitting this report. You should hear back from the admin team shortly.';
$txt['pm_report_return'] = 'Return to the inbox';

$txt['pm_search_title'] = 'Search Personal Messages';
$txt['pm_search_bar_title'] = 'Search Messages';
$txt['pm_search_text'] = 'Search for';
$txt['pm_search_go'] = 'Search';
$txt['pm_search_advanced'] = 'Advanced search';
$txt['pm_search_user'] = 'By user';
$txt['pm_search_match_all'] = 'Match all words';
$txt['pm_search_match_any'] = 'Match any words';
$txt['pm_search_options'] = 'Options';
$txt['pm_search_post_age'] = 'Message age';
$txt['pm_search_show_complete'] = 'Show full message in results.';
$txt['pm_search_subject_only'] = 'Search by subject and author only.';
$txt['pm_search_between'] = 'between';
$txt['pm_search_between_and'] = 'and';
$txt['pm_search_between_days'] = 'days';
$txt['pm_search_order'] = 'Search order';
$txt['pm_search_choose_label'] = 'Choose labels to search by, or search all';

$txt['pm_search_results'] = 'Search Results';
$txt['pm_search_none_found'] = 'No Messages Found';

$txt['pm_search_orderby_relevant_first'] = 'Most relevant first';
$txt['pm_search_orderby_recent_first'] = 'Most recent first';
$txt['pm_search_orderby_old_first'] = 'Oldest first';

$txt['pm_visual_verification_label'] = 'Verification';
$txt['pm_visual_verification_desc'] = 'Please enter the code in the image above to send this pm.';

$txt['pm_settings'] = 'Change Settings';
$txt['pm_change_view'] = 'Change View';

$txt['pm_manage_rules'] = 'Manage Rules';
$txt['pm_manage_rules_desc'] = 'Message rules allow you to automatically sort incoming messages dependant on a set of criteria you define. Below are all the rules you currently have setup. To edit a rule simply click the rule name.';
$txt['pm_rules_none'] = 'You have not yet setup any message rules.';
$txt['pm_rule_title'] = 'Rule';
$txt['pm_add_rule'] = 'Add New Rule';
$txt['pm_apply_rules'] = 'Apply Rules Now';
// Use entities in the below string.
$txt['pm_js_apply_rules_confirm'] = 'Are you sure you wish to apply the current rules to all personal messages?';
$txt['pm_edit_rule'] = 'Edit Rule';
$txt['pm_rule_save'] = 'Save Rule';
$txt['pm_delete_selected_rule'] = 'Delete Selected Rules';
// Use entities in the below string.
$txt['pm_js_delete_rule_confirm'] = 'Are you sure you wish to delete the selected rules?';
$txt['pm_rule_name'] = 'Name';
$txt['pm_rule_name_desc'] = 'Name to remember this rule by';
$txt['pm_rule_name_default'] = '[NAME]';
$txt['pm_rule_description'] = 'Description';
$txt['pm_rule_not_defined'] = 'Add some criteria to begin building this rule description.';
$txt['pm_rule_js_disabled'] = '<span class="alert"><strong>Note:</strong> You appear to have javascript disabled. We highly recommend you enable javascript to use this feature.</span>';
$txt['pm_rule_criteria'] = 'Criteria';
$txt['pm_rule_criteria_add'] = 'Add Criteria';
$txt['pm_rule_criteria_pick'] = 'Choose Criteria';
$txt['pm_rule_mid'] = 'Sender Name';
$txt['pm_rule_gid'] = 'Sender\'s Group';
$txt['pm_rule_sub'] = 'Message Subject Contains';
$txt['pm_rule_msg'] = 'Message Body Contains';
$txt['pm_rule_bud'] = 'Sender is Buddy';
$txt['pm_rule_sel_group'] = 'Select Group';
$txt['pm_rule_logic'] = 'When Checking Criteria';
$txt['pm_rule_logic_and'] = 'All criteria must be met';
$txt['pm_rule_logic_or'] = 'Any criteria can be met';
$txt['pm_rule_actions'] = 'Actions';
$txt['pm_rule_sel_action'] = 'Select an Action';
$txt['pm_rule_add_action'] = 'Add Action';
$txt['pm_rule_label'] = 'Label message with';
$txt['pm_rule_sel_label'] = 'Select Label';
$txt['pm_rule_delete'] = 'Delete Message';
$txt['pm_rule_no_name'] = 'You forgot to enter a name for the rule.';
$txt['pm_rule_no_criteria'] = 'A rule must have at least one criteria and one action set.';
$txt['pm_rule_too_complex'] = 'The rule you are creating is too long for SMF to store. Try breaking it up into smaller rules.';

$txt['pm_readable_and'] = '<em>and</em>';
$txt['pm_readable_or'] = '<em>or</em>';
$txt['pm_readable_start'] = 'If ';
$txt['pm_readable_end'] = '.';
$txt['pm_readable_member'] = 'message is from &quot;{MEMBER}&quot;';
$txt['pm_readable_group'] = 'sender is from the &quot;{GROUP}&quot; group';
$txt['pm_readable_subject'] = 'message subject contains &quot;{SUBJECT}&quot;';
$txt['pm_readable_body'] = 'message body contains &quot;{BODY}&quot;';
$txt['pm_readable_buddy'] = 'sender is a buddy';
$txt['pm_readable_label'] = 'apply label &quot;{LABEL}&quot;';
$txt['pm_readable_delete'] = 'delete the message';
$txt['pm_readable_then'] = '<strong>then</strong>';

?>