<?php
// Version: 2.1 RC2; ManageScheduledTasks

$txt['scheduled_tasks_title'] = 'Scheduled Tasks';
$txt['scheduled_tasks_header'] = 'All Scheduled Tasks';
$txt['scheduled_tasks_name'] = 'Task Name';
$txt['scheduled_tasks_next_time'] = 'Next Due';
$txt['scheduled_tasks_regularity'] = 'Regularity';
$txt['scheduled_tasks_enabled'] = 'Enabled';
$txt['scheduled_tasks_run_now'] = 'Run now';
$txt['scheduled_tasks_save_changes'] = 'Save Changes';
$txt['scheduled_tasks_time_offset'] = '<strong>Note:</strong> All times given below are <em>server time</em> and do not take into account any time offsets set up within SMF.';
$txt['scheduled_tasks_were_run'] = 'All selected tasks were completed';
$txt['scheduled_tasks_were_run_errors'] = 'All selected tasks were completed but some had errors:';

$txt['scheduled_tasks_na'] = 'N/A';
$txt['scheduled_task_auto_optimize'] = 'Optimize Database';
$txt['scheduled_task_desc_auto_optimize'] = 'Optimize the database to resolve fragmentation issues.';
$txt['scheduled_task_daily_maintenance'] = 'Daily Maintenance';
$txt['scheduled_task_desc_daily_maintenance'] = 'Runs essential daily maintenance on the forum - should not be disabled.';
$txt['scheduled_task_daily_digest'] = 'Daily Notification summary';
$txt['scheduled_task_desc_daily_digest'] = 'Emails out the daily digest for notification subscribers.';
$txt['scheduled_task_weekly_digest'] = 'Weekly Notification summary';
$txt['scheduled_task_desc_weekly_digest'] = 'Emails out the weekly digest for notification subscribers.';
$txt['scheduled_task_fetchSMfiles'] = 'Fetch Simple Machines files';
$txt['scheduled_task_desc_fetchSMfiles'] = 'Retrieves javascript files containing notifications of updates and other information.';
$txt['scheduled_task_birthdayemails'] = 'Send Birthday emails';
$txt['scheduled_task_desc_birthdayemails'] = 'Sends out emails wishing members a happy birthday.';
$txt['scheduled_task_weekly_maintenance'] = 'Weekly Maintenance';
$txt['scheduled_task_desc_weekly_maintenance'] = 'Runs essential weekly maintenance on the forum - should not be disabled.';
$txt['scheduled_task_paid_subscriptions'] = 'Paid Subscription Checks';
$txt['scheduled_task_desc_paid_subscriptions'] = 'Sends out any necessary paid subscription reminders and removes expired member subscriptions.';
$txt['scheduled_task_remove_topic_redirect'] = 'Remove MOVED: redirection topics';
$txt['scheduled_task_desc_remove_topic_redirect'] = 'Deletes "MOVED:" topic notifications as specified when the moved notice was created.';
$txt['scheduled_task_remove_temp_attachments'] = 'Remove temporary attachment files';
$txt['scheduled_task_desc_remove_temp_attachments'] = 'Deletes temporary files created while attaching a file to a post that for any reason weren\'t renamed or deleted before.';

$txt['scheduled_task_reg_starting'] = 'Starting at %1$s';
$txt['scheduled_task_reg_repeating'] = 'repeating every %1$d %2$s';
$txt['scheduled_task_reg_unit_m'] = 'minute(s)';
$txt['scheduled_task_reg_unit_h'] = 'hour(s)';
$txt['scheduled_task_reg_unit_d'] = 'day(s)';
$txt['scheduled_task_reg_unit_w'] = 'week(s)';

$txt['scheduled_task_edit'] = 'Edit Scheduled Task';
$txt['scheduled_task_edit_repeat'] = 'Repeat task every';
$txt['scheduled_task_edit_interval'] = 'Interval';
$txt['scheduled_task_edit_start_time'] = 'Start time';
$txt['scheduled_task_edit_start_time_desc'] = 'Time the first instance of the day should start (hours:minutes)';
$txt['scheduled_task_time_offset'] = 'Note the start time should be the offset against the current server time. Current server time is: %1$s';

$txt['scheduled_view_log'] = 'View Log';
$txt['scheduled_log_empty'] = 'There are currently no task log entries.';
$txt['scheduled_log_time_run'] = 'Time Run';
$txt['scheduled_log_time_taken'] = 'Time taken';
$txt['scheduled_log_time_taken_seconds'] = '%1$d seconds';
$txt['scheduled_log_empty_log'] = 'Clear Log';
$txt['scheduled_log_empty_log_confirm'] = 'Are you sure you want to completely clear the log?';

$txt['scheduled_task_remove_old_drafts'] = 'Remove old drafts';
$txt['scheduled_task_desc_remove_old_drafts'] = 'Deletes drafts older than the number of days defined in the draft settings in the admin panel.';

$txt['cron_is_real_cron'] = 'Disable JavaScript-based method of running scheduled tasks.';
$txt['cron_is_real_cron_desc'] = '<strong>Do not check this box</strong> unless you are <strong><u>sure</u></strong> that you have configured another method to tell your server to run SMF\'s cron.php on a regular basis.';
$txt['cron_not_working'] = 'No scheduled tasks have been run in the last 24 hours. Re-enabling JavaScript-based method of running scheduled tasks.';

?>