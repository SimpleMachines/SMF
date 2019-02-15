/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Changing column names.
/******************************************************************************/

---# Renaming table columns.
---{
// The array holding all the changes.
$nameChanges = array(
	'admin_info_files' => array(
		'ID_FILE' => 'ID_FILE id_file tinyint(4) unsigned NOT NULL auto_increment',
	),
	'approval_queue' => array(
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_ATTACH' => 'ID_ATTACH id_attach int(10) unsigned NOT NULL default \'0\'',
		'ID_EVENT' => 'ID_EVENT id_event smallint(5) unsigned NOT NULL default \'0\'',
		'attachmentType' => 'attachmentType attachment_type tinyint(3) unsigned NOT NULL default \'0\'',
	),
	'attachments' => array(
		'ID_ATTACH' => 'ID_ATTACH id_attach int(10) unsigned NOT NULL auto_increment',
		'ID_THUMB' => 'ID_THUMB id_thumb int(10) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'attachmentType' => 'attachmentType attachment_type tinyint(3) unsigned NOT NULL default \'0\'',
	),
	'ban_groups' => array(
		'ID_BAN_GROUP' => 'ID_BAN_GROUP id_ban_group mediumint(8) unsigned NOT NULL auto_increment',
	),
	'ban_items' => array(
		'ID_BAN' => 'ID_BAN id_ban mediumint(8) unsigned NOT NULL auto_increment',
		'ID_BAN_GROUP' => 'ID_BAN_GROUP id_ban_group smallint(5) unsigned NOT NULL default \'0\'',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'board_permissions' => array(
		'ID_GROUP' => 'ID_GROUP id_group smallint(5) NOT NULL default \'0\'',
		'ID_PROFILE' => 'ID_PROFILE id_profile smallint(5) NOT NULL default \'0\'',
		'addDeny' => 'addDeny add_deny tinyint(4) NOT NULL default \'1\'',
	),
	'boards' => array(
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL auto_increment',
		'ID_CAT' => 'ID_CAT id_cat tinyint(4) unsigned NOT NULL default \'0\'',
		'childLevel' => 'childLevel child_level tinyint(4) unsigned NOT NULL default \'0\'',
		'ID_PARENT' => 'ID_PARENT id_parent smallint(5) unsigned NOT NULL default \'0\'',
		'boardOrder' => 'boardOrder board_order smallint(5) NOT NULL default \'0\'',
		'ID_LAST_MSG' => 'ID_LAST_MSG id_last_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_MSG_UPDATED' => 'ID_MSG_UPDATED id_msg_updated int(10) unsigned NOT NULL default \'0\'',
		'memberGroups' => 'memberGroups member_groups varchar(255) NOT NULL default \'-1,0\'',
		'ID_PROFILE' => 'ID_PROFILE id_profile smallint(5) unsigned NOT NULL default \'1\'',
		'numTopics' => 'numTopics num_topics mediumint(8) unsigned NOT NULL default \'0\'',
		'numPosts' => 'numPosts num_posts mediumint(8) unsigned NOT NULL default \'0\'',
		'countPosts' => 'countPosts count_posts tinyint(4) NOT NULL default \'0\'',
		'ID_THEME' => 'ID_THEME id_theme tinyint(4) unsigned NOT NULL default \'0\'',
		'unapprovedPosts' => 'unapprovedPosts unapproved_posts smallint(5) NOT NULL default \'0\'',
		'unapprovedTopics' => 'unapprovedTopics unapproved_topics smallint(5) NOT NULL default \'0\'',
	),
	'calendar' => array(
		'ID_EVENT' => 'ID_EVENT id_event smallint(5) unsigned NOT NULL auto_increment',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
		'startDate' => 'startDate start_date date NOT NULL default \'0001-01-01\'',
		'endDate' => 'endDate end_date date NOT NULL default \'0001-01-01\'',
	),
	'calendar_holidays' => array(
		'ID_HOLIDAY' => 'ID_HOLIDAY id_holiday smallint(5) unsigned NOT NULL auto_increment',
		'eventDate' => 'eventDate event_date date NOT NULL default \'0001-01-01\'',
	),
	'categories' => array(
		'ID_CAT' => 'ID_CAT id_cat tinyint(4) unsigned NOT NULL auto_increment',
		'catOrder' => 'catOrder cat_order tinyint(4) NOT NULL default \'0\'',
		'canCollapse' => 'canCollapse can_collapse tinyint(1) NOT NULL default \'1\'',
	),
	'custom_fields' => array(
		'ID_FIELD' => 'ID_FIELD id_field smallint(5) NOT NULL auto_increment',
		'colName' => 'colName col_name varchar(12) NOT NULL default \'\'',
		'fieldName' => 'fieldName field_name varchar(40) NOT NULL default \'\'',
		'fieldDesc' => 'fieldDesc field_desc varchar(255) NOT NULL default \'\'',
		'fieldType' => 'fieldType field_type varchar(8) NOT NULL default \'text\'',
		'fieldLength' => 'fieldLength field_length smallint(5) NOT NULL default \'255\'',
		'fieldOptions' => 'fieldOptions field_options text NOT NULL',
		'showReg' => 'showReg show_reg tinyint(3) NOT NULL default \'0\'',
		'showDisplay' => 'showDisplay show_display tinyint(3) NOT NULL default \'0\'',
		'showProfile' => 'showProfile show_profile varchar(20) NOT NULL default \'forumprofile\'',
		'defaultValue' => 'defaultValue default_value varchar(8) NOT NULL default \'0\'',
	),
	'group_moderators' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_GROUP' => 'ID_GROUP id_group smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_actions' => array(
		'ID_ACTION' => 'ID_ACTION id_action int(10) unsigned NOT NULL auto_increment',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'logTime' => 'logTime log_time int(10) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_activity' => array(
		'mostOn' => 'mostOn most_on smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_banned' => array(
		'ID_BAN_LOG' => 'ID_BAN_LOG id_ban_log mediumint(8) unsigned NOT NULL auto_increment',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'logTime' => 'logTime log_time int(10) unsigned NOT NULL default \'0\'',
	),
	'log_boards' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_digest' => array(
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
	),
	'log_errors' => array(
		'ID_ERROR' => 'ID_ERROR id_error mediumint(8) unsigned NOT NULL auto_increment',
		'logTime' => 'logTime log_time int(10) unsigned NOT NULL default \'0\'',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'errorType' => 'errorType error_type char(15) NOT NULL default \'general\'',
	),
	'log_floodcontrol' => array(
		'logTime' => 'logTime log_time int(10) unsigned NOT NULL default \'0\'',
	),
	'log_group_requests' => array(
		'ID_REQUEST' => 'ID_REQUEST id_request mediumint(8) unsigned NOT NULL auto_increment',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_GROUP' => 'ID_GROUP id_group smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_karma' => array(
		'ID_TARGET' => 'ID_TARGET id_target mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_EXECUTOR' => 'ID_EXECUTOR id_executor mediumint(8) unsigned NOT NULL default \'0\'',
		'logTime' => 'logTime log_time int(10) unsigned NOT NULL default \'0\'',
	),
	'log_mark_read' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_notify' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_packages' => array(
		'ID_INSTALL' => 'ID_INSTALL id_install int(10) NOT NULL auto_increment',
		'ID_MEMBER_INSTALLED' => 'ID_MEMBER_INSTALLED id_member_installed mediumint(8) NOT NULL default \'0\'',
		'ID_MEMBER_REMOVED' => 'ID_MEMBER_REMOVED id_member_removed mediumint(8) NOT NULL default \'0\'',
	),
	'log_polls' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_CHOICE' => 'ID_CHOICE id_choice tinyint(3) unsigned NOT NULL default \'0\'',
		'ID_POLL' => 'ID_POLL id_poll mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'log_reported' => array(
		'ID_REPORT' => 'ID_REPORT id_report mediumint(8) unsigned NOT NULL auto_increment',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
	),
	'log_reported_comments' => array(
		'ID_COMMENT' => 'ID_COMMENT id_comment mediumint(8) unsigned NOT NULL auto_increment',
		'ID_REPORT' => 'ID_REPORT id_report mediumint(8) NOT NULL default \'0\'',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'log_scheduled_tasks' => array(
		'ID_LOG' => 'ID_LOG id_log mediumint(8) NOT NULL auto_increment',
		'ID_TASK' => 'ID_TASK id_task smallint(5) NOT NULL default \'0\'',
		'timeRun' => 'timeRun time_run int(10) NOT NULL default \'0\'',
		'timeTaken' => 'timeTaken time_taken float NOT NULL default \'0\'',
	),
	'log_search_messages' => array(
		'ID_SEARCH' => 'ID_SEARCH id_search tinyint(3) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
	),
	'log_search_results' => array(
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_SEARCH' => 'ID_SEARCH id_search tinyint(3) unsigned NOT NULL default \'0\'',
	),
	'log_search_subjects' => array(
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'log_search_topics' => array(
		'ID_SEARCH' => 'ID_SEARCH id_search tinyint(3) unsigned NOT NULL default \'0\'',
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'log_subscribed' => array(
		'ID_SUBLOG' => 'ID_SUBLOG id_sublog int(10) unsigned NOT NULL auto_increment',
		'ID_SUBSCRIBE' => 'ID_SUBSCRIBE id_subscribe mediumint(8) unsigned NOT NULL default \'0\'',
		'OLD_ID_GROUP' => 'OLD_ID_GROUP old_id_group smallint(5) NOT NULL default \'0\'',
		'startTime' => 'startTime start_time int(10) NOT NULL default \'0\'',
		'endTime' => 'endTime end_time int(10) NOT NULL default \'0\'',
	),
	'log_topics' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'mail_queue' => array(
		'ID_MAIL' => 'ID_MAIL id_mail int(10) unsigned NOT NULL auto_increment',
	),
	'members' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL auto_increment',
		'memberName' => 'memberName member_name varchar(80) NOT NULL default \'\'',
		'dateRegistered' => 'dateRegistered date_registered int(10) unsigned NOT NULL default \'0\'',
		'ID_GROUP' => 'ID_GROUP id_group smallint(5) unsigned NOT NULL default \'0\'',
		'lastLogin' => 'lastLogin last_login int(10) unsigned NOT NULL default \'0\'',
		'realName' => 'realName real_name varchar(255) NOT NULL default \'\'',
		'instantMessages' => 'instantMessages instant_messages smallint(5) NOT NULL default \'0\'',
		'unreadMessages' => 'unreadMessages unread_messages smallint(5) NOT NULL default \'0\'',
		'messageLabels' => 'messageLabels message_labels text NOT NULL',
		'emailAddress' => 'emailAddress email_address varchar(255) NOT NULL default \'\'',
		'personalText' => 'personalText personal_text varchar(255) NOT NULL default \'\'',
		'websiteTitle' => 'websiteTitle website_title varchar(255) NOT NULL default \'\'',
		'websiteUrl' => 'websiteUrl website_url varchar(255) NOT NULL default \'\'',
		'ICQ' => 'ICQ icq varchar(255) NOT NULL default \'\'',
		'AIM' => 'AIM aim varchar(255) NOT NULL default \'\'',
		'YIM' => 'YIM yim varchar(32) NOT NULL default \'\'',
		'MSN' => 'MSN msn varchar(255) NOT NULL default \'\'',
		'hideEmail' => 'hideEmail hide_email tinyint(4) NOT NULL default \'0\'',
		'showOnline' => 'showOnline show_online tinyint(4) NOT NULL default \'1\'',
		'timeFormat' => 'timeFormat time_format varchar(80) NOT NULL default \'\'',
		'timeOffset' => 'timeOffset time_offset float NOT NULL default \'0\'',
		'karmaBad' => 'karmaBad karma_bad smallint(5) unsigned NOT NULL default \'0\'',
		'karmaGood' => 'karmaGood karma_good smallint(5) unsigned NOT NULL default \'0\'',
		'notifyAnnouncements' => 'notifyAnnouncements notify_announcements tinyint(4) NOT NULL default \'1\'',
		'notifyRegularity' => 'notifyRegularity notify_regularity tinyint(4) NOT NULL default \'1\'',
		'notifySendBody' => 'notifySendBody notify_send_body tinyint(4) NOT NULL default \'0\'',
		'notifyTypes' => 'notifyTypes notify_types tinyint(4) NOT NULL default \'2\'',
		'memberIP' => 'memberIP member_ip varchar(255) NOT NULL default \'\'',
		'secretQuestion' => 'secretQuestion secret_question varchar(255) NOT NULL default \'\'',
		'secretAnswer' => 'secretAnswer secret_answer varchar(64) NOT NULL default \'\'',
		'ID_THEME' => 'ID_THEME id_theme tinyint(4) unsigned NOT NULL default \'0\'',
		'ID_MSG_LAST_VISIT' => 'ID_MSG_LAST_VISIT id_msg_last_visit int(10) unsigned NOT NULL default \'0\'',
		'additionalGroups' => 'additionalGroups additional_groups varchar(255) NOT NULL default \'\'',
		'smileySet' => 'smileySet smiley_set varchar(48) NOT NULL default \'\'',
		'ID_POST_GROUP' => 'ID_POST_GROUP id_post_group smallint(5) unsigned NOT NULL default \'0\'',
		'totalTimeLoggedIn' => 'totalTimeLoggedIn total_time_logged_in int(10) unsigned NOT NULL default \'0\'',
		'passwordSalt' => 'passwordSalt password_salt varchar(255) NOT NULL default \'\'',
		'ignoreBoards' => 'ignoreBoards ignore_boards text NOT NULL',
		'memberIP2' => 'memberIP2 member_ip2 varchar(255) NOT NULL default \'\'',
	),
	'messages' => array(
		'ID_MSG' => 'ID_MSG id_msg int(10) unsigned NOT NULL auto_increment',
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
		'posterTime' => 'posterTime poster_time int(10) unsigned NOT NULL default \'0\'',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MSG_MODIFIED' => 'ID_MSG_MODIFIED id_msg_modified int(10) unsigned NOT NULL default \'0\'',
		'posterName' => 'posterName poster_name varchar(255) NOT NULL default \'\'',
		'posterEmail' => 'posterEmail poster_email varchar(255) NOT NULL default \'\'',
		'posterIP' => 'posterIP poster_ip varchar(255) NOT NULL default \'\'',
		'smileysEnabled' => 'smileysEnabled smileys_enabled tinyint(4) NOT NULL default \'1\'',
		'modifiedTime' => 'modifiedTime modified_time int(10) unsigned NOT NULL default \'0\'',
		'modifiedName' => 'modifiedName modified_name varchar(255) NOT NULL default \'\'',
	),
	'membergroups' => array(
		'ID_GROUP' => 'ID_GROUP id_group smallint(5) unsigned NOT NULL auto_increment',
		'ID_PARENT' => 'ID_PARENT id_parent smallint(5) NOT NULL default \'-2\'',
		'groupName' => 'groupName group_name varchar(80) NOT NULL default \'\'',
		'onlineColor' => 'onlineColor online_color varchar(20) NOT NULL default \'\'',
		'minPosts' => 'minPosts min_posts mediumint(9) NOT NULL default \'-1\'',
		'maxMessages' => 'maxMessages max_messages smallint(5) unsigned NOT NULL default \'0\'',
		'groupType' => 'groupType group_type tinyint(3) NOT NULL default \'0\'',
	),
	'message_icons' => array(
		'ID_ICON' => 'ID_ICON id_icon smallint(5) unsigned NOT NULL auto_increment',
		'iconOrder' => 'iconOrder icon_order smallint(5) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
	),
	'moderators' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
	),
	'package_servers' => array(
		'ID_SERVER' => 'ID_SERVER id_server smallint(5) unsigned NOT NULL auto_increment',
	),
	'personal_messages' => array(
		'ID_PM' => 'ID_PM id_pm int(10) unsigned NOT NULL auto_increment',
		'ID_MEMBER_FROM' => 'ID_MEMBER_FROM id_member_from mediumint(8) unsigned NOT NULL default \'0\'',
		'deletedBySender' => 'deletedBySender deleted_by_sender tinyint(3) unsigned NOT NULL default \'0\'',
		'fromName' => 'fromName from_name varchar(255) NOT NULL default \'\'',
	),
	'permission_profiles' => array(
		'ID_PROFILE' => 'ID_PROFILE id_profile smallint(5) NOT NULL auto_increment',
	),
	'permissions' => array(
		'ID_GROUP' => 'ID_GROUP id_group smallint(5) NOT NULL default \'0\'',
		'addDeny' => 'addDeny add_deny tinyint(4) NOT NULL default \'1\'',
	),
	'pm_recipients' => array(
		'ID_PM' => 'ID_PM id_pm int(10) unsigned NOT NULL default \'0\'',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'polls' => array(
		'ID_POLL' => 'ID_POLL id_poll mediumint(8) unsigned NOT NULL auto_increment',
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) unsigned NOT NULL default \'0\'',
		'votingLocked' => 'votingLocked voting_locked tinyint(1) NOT NULL default \'0\'',
		'maxVotes' => 'maxVotes max_votes tinyint(3) unsigned NOT NULL default \'1\'',
		'expireTime' => 'expireTime expire_time int(10) unsigned NOT NULL default \'0\'',
		'hideResults' => 'hideResults hide_results tinyint(3) unsigned NOT NULL default \'0\'',
		'changeVote' => 'changeVote change_vote tinyint(3) unsigned NOT NULL default \'0\'',
		'posterName' => 'posterName poster_name varchar(255) NOT NULL default \'\'',
	),
	'poll_choices' => array(
		'ID_CHOICE' => 'ID_CHOICE id_choice tinyint(3) unsigned NOT NULL default \'0\'',
		'ID_POLL' => 'ID_POLL id_poll mediumint(8) unsigned NOT NULL default \'0\'',
	),
	'scheduled_tasks' => array(
		'ID_TASK' => 'ID_TASK id_task smallint(5) NOT NULL auto_increment',
		'nextTime' => 'nextTime next_time int(10) NOT NULL default \'0\'',
		'timeRegularity' => 'timeRegularity time_regularity smallint(5) NOT NULL default \'0\'',
		'timeOffset' => 'timeOffset time_offset int(10) NOT NULL default \'0\'',
		'timeUnit' => 'timeUnit time_unit varchar(1) NOT NULL default \'h\'',
	),
	'smileys' => array(
		'ID_SMILEY' => 'ID_SMILEY id_smiley smallint(5) unsigned NOT NULL auto_increment',
		'smileyRow' => 'smileyRow smiley_row tinyint(4) unsigned NOT NULL default \'0\'',
		'smileyOrder' => 'smileyOrder smiley_order smallint(5) unsigned NOT NULL default \'0\'',
	),
	'subscriptions' => array(
		'ID_SUBSCRIBE' => 'ID_SUBSCRIBE id_subscribe mediumint(8) unsigned NOT NULL auto_increment',
		'ID_GROUP' => 'ID_GROUP id_group smallint(5) NOT NULL default \'0\'',
		'addGroups' => 'addGroups add_groups varchar(40) NOT NULL default \'\'',
		'allowPartial' => 'allowPartial allow_partial tinyint(3) NOT NULL default \'0\'',
	),
	'themes' => array(
		'ID_MEMBER' => 'ID_MEMBER id_member mediumint(8) NOT NULL default \'0\'',
		'ID_THEME' => 'ID_THEME id_theme tinyint(4) unsigned NOT NULL default \'1\'',
	),
	'topics' => array(
		'ID_TOPIC' => 'ID_TOPIC id_topic mediumint(8) unsigned NOT NULL auto_increment',
		'isSticky' => 'isSticky is_sticky tinyint(4) NOT NULL default \'0\'',
		'ID_BOARD' => 'ID_BOARD id_board smallint(5) unsigned NOT NULL default \'0\'',
		'ID_FIRST_MSG' => 'ID_FIRST_MSG id_first_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_LAST_MSG' => 'ID_LAST_MSG id_last_msg int(10) unsigned NOT NULL default \'0\'',
		'ID_MEMBER_STARTED' => 'ID_MEMBER_STARTED id_member_started mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_MEMBER_UPDATED' => 'ID_MEMBER_UPDATED id_member_updated mediumint(8) unsigned NOT NULL default \'0\'',
		'ID_POLL' => 'ID_POLL id_poll mediumint(8) unsigned NOT NULL default \'0\'',
		'numReplies' => 'numReplies num_replies int(10) unsigned NOT NULL default \'0\'',
		'numViews' => 'numViews num_views int(10) unsigned NOT NULL default \'0\'',
		'unapprovedPosts' => 'unapprovedPosts unapproved_posts smallint(5) NOT NULL default \'0\'',
	),
);

$_GET['ren_col'] = isset($_GET['ren_col']) ? (int) $_GET['ren_col'] : 0;
$step_progress['name'] = 'Renaming columns';
$step_progress['current'] = $_GET['ren_col'];
$step_progress['total'] = count($nameChanges);

$count = 0;
// Now do every table...
foreach ($nameChanges as $table_name => $table)
{
	// Already done this?
	$count++;
	if ($_GET['ren_col'] > $count)
		continue;
	$_GET['ren_col'] = $count;

	// Check the table exists!
	$request = upgrade_query("
		SHOW TABLES
		LIKE '{$db_prefix}$table_name'");
	if (smf_mysql_num_rows($request) == 0)
	{
		smf_mysql_free_result($request);
		continue;
	}
	smf_mysql_free_result($request);

	// Check each column!
	$actualChanges = array();
	foreach ($table as $colname => $coldef)
	{
		$change = array(
			'table' => $table_name,
			'name' => $colname,
			'type' => 'column',
			'method' => 'change_remove',
			'text' => 'CHANGE ' . $coldef,
		);

		// Check if this change may need a special edit.
		checkChange($change);

		if (protected_alter($change, $substep, true) == false)
			$actualChanges[] = ' CHANGE COLUMN ' . $coldef;
	}

	// Do the query - if it needs doing.
	if (!empty($actualChanges))
	{
		$change = array(
			'table' => $table_name,
			'name' => 'na',
			'type' => 'table',
			'method' => 'full_change',
			'text' => implode(', ', $actualChanges),
		);

		// Here we go - hold on!
		protected_alter($change, $substep);
	}

	// Update where we are!
	$step_progress['current'] = $_GET['ren_col'];
}

// All done!
unset($_GET['ren_col']);
---}
---#

---# Converting "log_online".
DROP TABLE IF EXISTS {$db_prefix}log_online;
CREATE TABLE {$db_prefix}log_online (
	session varchar(32) NOT NULL default '',
	log_time int(10) NOT NULL default '0',
	id_member mediumint(8) unsigned NOT NULL default '0',
	id_spider smallint(5) unsigned NOT NULL default '0',
	ip int(10) unsigned NOT NULL default '0',
	url text NOT NULL,
	PRIMARY KEY (session),
	KEY log_time (log_time),
	KEY id_member (id_member)
) ENGINE=MyISAM{$db_collation};
---#

/******************************************************************************/
--- Adding new board specific features.
/******************************************************************************/

---# Implementing board redirects.
ALTER TABLE {$db_prefix}boards
ADD COLUMN redirect varchar(255) NOT NULL default '';
---#

/******************************************************************************/
--- Adding search engine tracking.
/******************************************************************************/

---# Creating spider table.
CREATE TABLE IF NOT EXISTS {$db_prefix}spiders (
	id_spider smallint(5) unsigned NOT NULL auto_increment,
	spider_name varchar(255) NOT NULL default '',
	user_agent varchar(255) NOT NULL default '',
	ip_info varchar(255) NOT NULL default '',
	PRIMARY KEY id_spider(id_spider)
) ENGINE=MyISAM{$db_collation};

INSERT IGNORE INTO {$db_prefix}spiders
	(id_spider, spider_name, user_agent, ip_info)
VALUES
	(1, 'Google', 'googlebot', ''),
	(2, 'Yahoo!', 'slurp', ''),
	(3, 'Bing', 'bingbot', ''),
	(4, 'Google (Mobile)', 'Googlebot-Mobile', ''),
	(5, 'Google (Image)', 'Googlebot-Image', ''),
	(6, 'Google (AdSense)', 'Mediapartners-Google', ''),
	(7, 'Google (Adwords)', 'AdsBot-Google', ''),
	(8, 'Yahoo! (Mobile)', 'YahooSeeker/M1A1-R2D2', ''),
	(9, 'Yahoo! (Image)', 'Yahoo-MMCrawler', ''),
	(10, 'Bing (Preview)', 'BingPreview', ''),
	(11, 'Bing (Ads)', 'adidxbot', ''),
	(12, 'Bing (MSNBot)', 'msnbot', ''),
	(13, 'Bing (Media)', 'msnbot-media', ''),
	(14, 'Cuil', 'twiceler', ''),
	(15, 'Ask', 'Teoma', ''),
	(16, 'Baidu', 'Baiduspider', ''),
	(17, 'Gigablast', 'Gigabot', ''),
	(18, 'InternetArchive', 'ia_archiver-web.archive.org', ''),
	(19, 'Alexa', 'ia_archiver', ''),
	(20, 'Omgili', 'omgilibot', ''),
	(21, 'EntireWeb', 'Speedy Spider', ''),
	(22, 'Yandex', 'yandex', '');
---#

---# Removing a spider.
---{
	upgrade_query("
		DELETE FROM {$db_prefix}spiders
		WHERE user_agent = 'yahoo'
			AND spider_name = 'Yahoo! (Publisher)'
	");
---}
---#

---# Creating spider hit tracking table.
CREATE TABLE IF NOT EXISTS {$db_prefix}log_spider_hits (
	id_hit int(10) unsigned NOT NULL auto_increment,
	id_spider smallint(5) unsigned NOT NULL default '0',
	log_time int(10) unsigned NOT NULL default '0',
	url varchar(255) NOT NULL default '',
	processed tinyint(3) unsigned NOT NULL default '0',
	PRIMARY KEY (id_hit),
	KEY id_spider(id_spider),
	KEY log_time(log_time),
	KEY processed (processed)
) ENGINE=MyISAM{$db_collation};
---#

---# Making some changes to spider hit table...
ALTER TABLE {$db_prefix}log_spider_hits
ADD COLUMN id_hit int(10) unsigned NOT NULL auto_increment,
ADD PRIMARY KEY (id_hit);
---#

---# Creating spider statistic table.
CREATE TABLE IF NOT EXISTS {$db_prefix}log_spider_stats (
	id_spider smallint(5) unsigned NOT NULL default '0',
	page_hits smallint(5) unsigned NOT NULL default '0',
	last_seen int(10) unsigned NOT NULL default '0',
	stat_date date NOT NULL default '0001-01-01',
	PRIMARY KEY (stat_date, id_spider)
) ENGINE=MyISAM{$db_collation};
---#

/******************************************************************************/
--- Adding new forum settings.
/******************************************************************************/

---# Resetting settings_updated.
REPLACE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('settings_updated', '0'),
	('last_mod_report_action', '0'),
	('search_floodcontrol_time', '5'),
	('next_task_time', UNIX_TIMESTAMP());
---#

---# Changing stats settings.
---{
$request = upgrade_query("
	SELECT value
	FROM {$db_prefix}themes
	WHERE variable = 'show_sp1_info'");
if (smf_mysql_num_rows($request) != 0)
{
	upgrade_query("
		DELETE FROM {$db_prefix}themes
		WHERE variable = 'show_stats_index'");

	upgrade_query("
		UPDATE {$db_prefix}themes
		SET variable = 'show_stats_index'
		WHERE variable = 'show_sp1_info'");
}
upgrade_query("
	DELETE FROM {$db_prefix}themes
	WHERE variable = 'show_sp1_info'");
---}
---#

---# Enable cache if upgrading from 1.1 and lower.
---{
if (isset($modSettings['smfVersion']) && $modSettings['smfVersion'] <= '2.0 Beta 1')
{
	$request = upgrade_query("
		SELECT value
		FROM {$db_prefix}settings
		WHERE variable = 'cache_enable'");
	list ($cache_enable) = $smcFunc['db_fetch_row']($request);

	// No cache before 1.1.
	if ($smcFunc['db_num_rows']($request) == 0)
		upgrade_query("
			INSERT INTO {$db_prefix}settings
				(variable, value)
			VALUES ('cache_enable', '1')");
	elseif (empty($cache_enable))
		upgrade_query("
			UPDATE {$db_prefix}settings
			SET value = '1'
			WHERE variable = 'cache_enable'");
}
---}
---#

---# Changing visual verification setting.
---{
$request = upgrade_query("
	SELECT value
	FROM {$db_prefix}settings
	WHERE variable = 'disable_visual_verification'");
if (smf_mysql_num_rows($request) != 0)
{
	list ($oldValue) = smf_mysql_fetch_row($request);
	if ($oldValue != 0)
	{
		// We have changed the medium setting from SMF 1.1.2.
		if ($oldValue == 4)
			$oldValue = 5;

		upgrade_query("
			UPDATE {$db_prefix}settings
			SET variable = 'visual_verification_type', value = $oldValue
			WHERE variable = 'disable_visual_verification'");
	}
}
upgrade_query("
	DELETE FROM {$db_prefix}settings
	WHERE variable = 'disable_visual_verification'");
---}
---#

---# Changing visual verification setting, again.
---{
$request = upgrade_query("
	SELECT value
	FROM {$db_prefix}settings
	WHERE variable = 'reg_verification'");
if (smf_mysql_num_rows($request) == 0)
{
	// Upgrade visual verification again!
	if (!empty($modSettings['visual_verification_type']))
	{
		upgrade_query("
			UPDATE {$db_prefix}settings
			SET value = value - 1
			WHERE variable = 'visual_verification_type'");
		$modSettings['visual_verification_type']--;
	}
	// Never set?
	elseif (!isset($modSettings['visual_verification_type']))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}settings
				(variable, value)
			VALUES
				('visual_verification_type', '3')");
		$modSettings['visual_verification_type'] = 3;
	}

	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('reg_verification', '" . (!empty($modSettings['visual_verification_type']) ? 1 : 0) . "')");
}
---}
---#

---# Changing default personal text setting.
UPDATE {$db_prefix}settings
SET variable = 'default_personal_text'
WHERE variable = 'default_personalText';

DELETE FROM {$db_prefix}settings
WHERE variable = 'default_personalText';
---#

---# Removing allow hide email setting.
DELETE FROM {$db_prefix}settings
WHERE variable = 'allow_hideEmail'
	OR variable = 'allow_hide_email';
---#

---# Ensuring stats index setting present...
INSERT IGNORE INTO {$db_prefix}themes
	(id_theme, variable, value)
VALUES
	(1, 'show_stats_index', '0');
---#

---# Ensuring forum width setting present...
INSERT IGNORE INTO {$db_prefix}themes
	(id_theme, variable, value)
VALUES
	(1, 'forum_width', '90%');
---#

---# Replacing old calendar settings...
---{
// Only try it if one of the "new" settings doesn't yet exist.
if (!isset($modSettings['cal_showholidays']) || !isset($modSettings['cal_showbdays']) || !isset($modSettings['cal_showevents']))
{
	// Default to just the calendar setting.
	$modSettings['cal_showholidays'] = empty($modSettings['cal_showholidaysoncalendar']) ? 0 : 1;
	$modSettings['cal_showbdays'] = empty($modSettings['cal_showbdaysoncalendar']) ? 0 : 1;
	$modSettings['cal_showevents'] = empty($modSettings['cal_showeventsoncalendar']) ? 0 : 1;

	// Then take into account board index.
	if (!empty($modSettings['cal_showholidaysonindex']))
		$modSettings['cal_showholidays'] = $modSettings['cal_showholidays'] === 1 ? 2 : 3;
	if (!empty($modSettings['cal_showbdaysonindex']))
		$modSettings['cal_showbdays'] = $modSettings['cal_showbdays'] === 1 ? 2 : 3;
	if (!empty($modSettings['cal_showeventsonindex']))
		$modSettings['cal_showevents'] = $modSettings['cal_showevents'] === 1 ? 2 : 3;

	// Actually save the settings.
	upgrade_query("
		INSERT IGNORE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('cal_showholidays', $modSettings[cal_showholidays]),
			('cal_showbdays', $modSettings[cal_showbdays]),
			('cal_showevents', $modSettings[cal_showevents])");
}

---}
---#

---# Deleting old calendar settings...
	DELETE FROM {$db_prefix}settings
	WHERE VARIABLE IN ('cal_showholidaysonindex', 'cal_showbdaysonindex', 'cal_showeventsonindex',
		'cal_showholidaysoncalendar', 'cal_showbdaysoncalendar', 'cal_showeventsoncalendar',
		'cal_holidaycolor', 'cal_bdaycolor', 'cal_eventcolor');
---#

---# Adjusting calendar maximum year...
---{
if (!isset($modSettings['cal_maxyear']) || $modSettings['cal_maxyear'] < 2030)
{
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('cal_maxyear', '2030')");
}
---}
---#

---# Adding advanced signature settings...
---{
if (empty($modSettings['signature_settings']))
{
	if (isset($modSettings['max_signatureLength']))
		$modSettings['signature_settings'] = '1,' . $modSettings['max_signatureLength'] . ',0,0,0,0,0,0:';
	else
		$modSettings['signature_settings'] = '1,300,0,0,0,0,0,0:';

	upgrade_query("
		INSERT IGNORE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('signature_settings', '$modSettings[signature_settings]')");

	upgrade_query("
		DELETE FROM {$db_prefix}settings
		WHERE variable = 'max_signatureLength'");
}
---}
---#

---# Updating spam protection settings.
---{
if (empty($modSettings['pm_spam_settings']))
{
	if (isset($modSettings['max_pm_recipients']))
		$modSettings['pm_spam_settings'] = $modSettings['max_pm_recipients'] . ',5,20';
	else
		$modSettings['pm_spam_settings'] = '10,5,20';
}
elseif (substr_count($modSettings['pm_spam_settings'], ',') == 1)
{
	$modSettings['pm_spam_settings'] .= ',20';
}

upgrade_query("
	INSERT IGNORE INTO {$db_prefix}settings
		(variable, value)
	VALUES
		('pm_spam_settings', '$modSettings[pm_spam_settings]')");

upgrade_query("
	DELETE FROM {$db_prefix}settings
	WHERE variable = 'max_pm_recipients'");
---}
---#

---# Adjusting timezone settings...
---{
	if (!isset($modSettings['default_timezone']) && function_exists('date_default_timezone_set'))
	{
		$server_offset = mktime(0, 0, 0, 1, 1, 1970);
		$timezone_id = 'Etc/GMT' . ($server_offset > 0 ? '+' : '') . ($server_offset / 3600);
		if (date_default_timezone_set($timezone_id))
			upgrade_query("
				REPLACE INTO {$db_prefix}settings
					(variable, value)
				VALUES
					('default_timezone', '$timezone_id')");
	}
---}
---#

---# Checking theme layers are correct for default themes.
---{
$request = upgrade_query("
	SELECT id_theme, value, variable
	FROM {$db_prefix}themes
	WHERE variable = 'theme_layers'
		OR variable = 'theme_dir'");
$themeLayerChanges = array();
while ($row = smf_mysql_fetch_assoc($request))
{
	$themeLayerChanges[$row['id_theme']][$row['variable']] = $row['value'];
}
smf_mysql_free_result($request);

foreach ($themeLayerChanges as $id_theme => $data)
{
	// Has to be a SMF provided theme and have custom layers defined.
	if (!isset($data['theme_layers']) || !isset($data['theme_dir']) || !in_array(substr($data['theme_dir'], -7), array('default', 'babylon', 'classic')))
		continue;

	$layers = explode(',', $data['theme_layers']);
	foreach ($layers as $k => $v)
		if ($v == 'main')
		{
			$layers[$k] = 'html,body';
			upgrade_query("
				UPDATE {$db_prefix}themes
				SET value = '" . implode(',', $layers) . "'
				WHERE id_theme = $id_theme
					AND variable = 'theme_layers'");
			break;
		}
}
---}
---#

---# Adding index to log_notify table...
ALTER TABLE {$db_prefix}log_notify
ADD INDEX id_topic (id_topic, id_member);
---#

/******************************************************************************/
--- Adding custom profile fields.
/******************************************************************************/

---# Creating "custom_fields" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}custom_fields (
	id_field smallint(5) NOT NULL auto_increment,
	col_name varchar(12) NOT NULL default '',
	field_name varchar(40) NOT NULL default '',
	field_desc varchar(255) NOT NULL default '',
	field_type varchar(8) NOT NULL default 'text',
	field_length smallint(5) NOT NULL default '255',
	field_options text NOT NULL,
	mask varchar(255) NOT NULL default '',
	show_reg tinyint(3) NOT NULL default '0',
	show_display tinyint(3) NOT NULL default '0',
	show_profile varchar(20) NOT NULL default 'forumprofile',
	private tinyint(3) NOT NULL default '0',
	active tinyint(3) NOT NULL default '1',
	bbc tinyint(3) NOT NULL default '0',
	default_value varchar(255) NOT NULL default '',
	PRIMARY KEY (id_field),
	UNIQUE col_name (col_name)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding search ability to custom fields.
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN can_search tinyint(3) NOT NULL default '0' AFTER bbc;
---#

---# Fixing default value field length.
ALTER TABLE {$db_prefix}custom_fields
CHANGE COLUMN default_value default_value varchar(255) NOT NULL default '';
---#

---# Enhancing privacy settings for custom fields.
---{
if (isset($modSettings['smfVersion']) && $modSettings['smfVersion'] <= '2.0 Beta 1')
{
upgrade_query("
	UPDATE {$db_prefix}custom_fields
	SET private = 2
	WHERE private = 1");
}
if (isset($modSettings['smfVersion']) && $modSettings['smfVersion'] < '2.0 Beta 4')
{
upgrade_query("
	UPDATE {$db_prefix}custom_fields
	SET private = 3
	WHERE private = 2");
}
---}
---#

---# Checking display fields setup correctly..
---{
if (isset($modSettings['smfVersion']) && $modSettings['smfVersion'] <= '2.0 Beta 1' && isset($modSettings['displayFields']) && @unserialize($modSettings['displayFields']) == false)
{
$request = upgrade_query("
	SELECT col_name, field_name, bbc
	FROM {$db_prefix}custom_fields
	WHERE show_display = 1
		AND active = 1
		AND private != 2");
$fields = array();
while ($row = smf_mysql_fetch_assoc($request))
{
	$fields[] = array(
		'c' => strtr($row['col_name'], array('|' => '', ';' => '')),
		'f' => strtr($row['field_name'], array('|' => '', ';' => '')),
		'b' => ($row['bbc'] ? '1' : '0')
	);
}
smf_mysql_free_result($request);

upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = '" . smf_mysql_real_escape_string(serialize($fields)) . "'
	WHERE variable = 'displayFields'");
}
---}
---#

---# Adding new custom fields columns.
ALTER TABLE {$db_prefix}custom_fields
ADD enclose text NOT NULL;

ALTER TABLE {$db_prefix}custom_fields
ADD placement tinyint(3) NOT NULL default '0';
---#

/******************************************************************************/
--- Adding email digest functionality.
/******************************************************************************/

---# Creating "log_digest" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_digest (
	id_topic mediumint(8) unsigned NOT NULL default '0',
	id_msg int(10) unsigned NOT NULL default '0',
	note_type varchar(10) NOT NULL default 'post',
	daily tinyint(3) unsigned NOT NULL default '0',
	exclude mediumint(8) unsigned NOT NULL default '0'
) ENGINE=MyISAM{$db_collation};
---#

---# Adding digest option to "members" table...
ALTER TABLE {$db_prefix}members
CHANGE COLUMN notifyOnce notify_regularity tinyint(4) unsigned NOT NULL default '1';
---#

/******************************************************************************/
--- Making changes to the package manager.
/******************************************************************************/

---# Creating "log_packages" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_packages (
	id_install int(10) NOT NULL auto_increment,
	filename varchar(255) NOT NULL default '',
	package_id varchar(255) NOT NULL default '',
	name varchar(255) NOT NULL default '',
	version varchar(255) NOT NULL default '',
	id_member_installed mediumint(8) NOT NULL default '0',
	member_installed varchar(255) NOT NULL default '',
	time_installed int(10) NOT NULL default '0',
	id_member_removed mediumint(8) NOT NULL default '0',
	member_removed varchar(255) NOT NULL default '',
	time_removed int(10) NOT NULL default '0',
	install_state tinyint(3) NOT NULL default '1',
	failed_steps text NOT NULL,
	themes_installed varchar(255) NOT NULL default '',
	db_changes text NOT NULL,
	PRIMARY KEY (id_install),
	KEY filename (filename(15))
) ENGINE=MyISAM{$db_collation};
---#

---# Adding extra "log_packages" columns...
ALTER TABLE {$db_prefix}log_packages
ADD db_changes text NOT NULL AFTER themes_installed;
---#

---# Changing URL to SMF package server...
UPDATE {$db_prefix}package_servers
SET url = 'http://custom.simplemachines.org/packages/mods'
WHERE url = 'http://mods.simplemachines.org';
---#

/******************************************************************************/
--- Creating mail queue functionality.
/******************************************************************************/

---# Creating "mail_queue" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}mail_queue (
	id_mail int(10) unsigned NOT NULL auto_increment,
	time_sent int(10) NOT NULL default '0',
	recipient varchar(255) NOT NULL default '',
	body text NOT NULL,
	subject varchar(255) NOT NULL default '',
	headers text NOT NULL,
	send_html tinyint(3) NOT NULL default '0',
	priority tinyint(3) NOT NULL default '1',
	PRIMARY KEY (id_mail),
	KEY time_sent (time_sent),
	KEY mail_priority (priority, id_mail)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding new mail queue settings...
---{
if (!isset($modSettings['mail_next_send']))
{
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('mail_next_send', '0'),
			('mail_recent', '0000000000|0')");
}
---}
---#

---# Change mail queue indexes...
ALTER TABLE {$db_prefix}mail_queue
DROP INDEX priority;

ALTER TABLE {$db_prefix}mail_queue
ADD INDEX mail_priority (priority, id_mail);
---#

---# Adding type to mail queue...
ALTER TABLE {$db_prefix}mail_queue
ADD private tinyint(1) NOT NULL default '0';
---#

/******************************************************************************/
--- Creating moderation center tables.
/******************************************************************************/

---# Creating "log_reported" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_reported (
	id_report mediumint(8) unsigned NOT NULL auto_increment,
	id_msg int(10) unsigned NOT NULL default '0',
	id_topic mediumint(8) unsigned NOT NULL default '0',
	id_board smallint(5) unsigned NOT NULL default '0',
	id_member mediumint(8) unsigned NOT NULL default '0',
	membername varchar(255) NOT NULL default '',
	subject varchar(255) NOT NULL default '',
	body text NOT NULL,
	time_started int(10) NOT NULL default '0',
	time_updated int(10) NOT NULL default '0',
	num_reports mediumint(6) NOT NULL default '0',
	closed tinyint(3) NOT NULL default '0',
	ignore_all tinyint(3) NOT NULL default '0',
	PRIMARY KEY (id_report),
	KEY id_member (id_member),
	KEY id_topic (id_topic),
	KEY closed (closed),
	KEY time_started (time_started),
	KEY id_msg (id_msg)
) ENGINE=MyISAM{$db_collation};
---#

---# Creating "log_reported_comments" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_reported_comments (
	id_comment mediumint(8) unsigned NOT NULL auto_increment,
	id_report mediumint(8) NOT NULL default '0',
	id_member mediumint(8) NOT NULL,
	membername varchar(255) NOT NULL default '',
	comment varchar(255) NOT NULL default '',
	time_sent int(10) NOT NULL,
	PRIMARY KEY (id_comment),
	KEY id_report (id_report),
	KEY id_member (id_member),
	KEY time_sent (time_sent)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding moderator center permissions...
---{
// Don't do this twice!
if (@$modSettings['smfVersion'] < '2.0')
{
	// Try find people who probably should see the moderation center.
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'calendar_edit_any'");
	$inserts = array();
	while ($row = smf_mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], 'access_mod_center', $row[add_deny])";
	}
	smf_mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
}
---}
---#

---# Adding moderation center preferences...
ALTER TABLE {$db_prefix}members
ADD mod_prefs varchar(20) NOT NULL default '';
---#

/******************************************************************************/
--- Adding user warnings.
/******************************************************************************/

---# Creating member notices table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_member_notices (
	id_notice mediumint(8) unsigned NOT NULL auto_increment,
	subject varchar(255) NOT NULL default '',
	body text NOT NULL,
	PRIMARY KEY (id_notice)
) ENGINE=MyISAM{$db_collation};
---#

---# Creating comments table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_comments (
	id_comment mediumint(8) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default '0',
	member_name varchar(80) NOT NULL default '',
	comment_type varchar(8) NOT NULL default 'warning',
	id_recipient mediumint(8) unsigned NOT NULL default '0',
	recipient_name varchar(255) NOT NULL default '',
	log_time int(10) NOT NULL default '0',
	id_notice mediumint(8) unsigned NOT NULL default '0',
	counter tinyint(3) NOT NULL default '0',
	body text NOT NULL,
	PRIMARY KEY (id_comment),
	KEY id_recipient (id_recipient),
	KEY log_time (log_time),
	KEY comment_type (comment_type(8))
) ENGINE=MyISAM{$db_collation};
---#

---# Adding user warning column...
ALTER TABLE {$db_prefix}members
ADD warning tinyint(4) NOT NULL default '0';

ALTER TABLE {$db_prefix}members
ADD INDEX warning (warning);
---#

---# Ensuring warning settings are present...
---{
// Only do this if not already done.
if (empty($modSettings['warning_settings']))
{
	upgrade_query("
		INSERT IGNORE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('warning_settings', '1,20,0'),
			('warning_watch', '10'),
			('warning_moderate', '35'),
			('warning_mute', '60')");
}
---}
---#

/******************************************************************************/
--- Enhancing membergroups.
/******************************************************************************/

---# Creating "log_group_requests" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_group_requests (
	id_request mediumint(8) unsigned NOT NULL auto_increment,
	id_member mediumint(8) unsigned NOT NULL default '0',
	id_group smallint(5) unsigned NOT NULL default '0',
	time_applied int(10) unsigned NOT NULL default '0',
	reason text NOT NULL,
	PRIMARY KEY (id_request),
	UNIQUE id_member (id_member, id_group)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding new membergroup table columns...
ALTER TABLE {$db_prefix}membergroups
ADD description text NOT NULL AFTER group_name;

ALTER TABLE {$db_prefix}membergroups
ADD group_type tinyint(3) NOT NULL default '0';

ALTER TABLE {$db_prefix}membergroups
ADD hidden tinyint(3) NOT NULL default '0';
---#

---# Creating "group_moderators" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}group_moderators (
	id_group smallint(5) unsigned NOT NULL default '0',
	id_member mediumint(8) unsigned NOT NULL default '0',
	PRIMARY KEY (id_group, id_member)
) ENGINE=MyISAM{$db_collation};
---#

/******************************************************************************/
--- Updating attachment data...
/******************************************************************************/

---# Altering attachment table.
ALTER TABLE {$db_prefix}attachments
ADD COLUMN fileext varchar(8) NOT NULL default '',
ADD COLUMN mime_type varchar(20) NOT NULL default '';

ALTER TABLE {$db_prefix}attachments
ADD COLUMN id_folder tinyint(3) NOT NULL default '1';
---#

---# Adding file hash.
ALTER TABLE {$db_prefix}attachments
ADD COLUMN file_hash varchar(40) NOT NULL default '';
---#

---# Populate the attachment extension.
UPDATE {$db_prefix}attachments
SET fileext = LOWER(SUBSTRING(filename, 1 - (INSTR(REVERSE(filename), '.'))))
WHERE fileext = ''
	AND INSTR(filename, '.')
	AND attachment_type != 3;
---#

---# Updating thumbnail attachments JPG.
UPDATE {$db_prefix}attachments
SET fileext = 'jpg'
WHERE attachment_type = 3
	AND fileext = ''
	AND RIGHT(filename, 9) = 'JPG_thumb';
---#

---# Updating thumbnail attachments PNG.
UPDATE {$db_prefix}attachments
SET fileext = 'png'
WHERE attachment_type = 3
	AND fileext = ''
	AND RIGHT(filename, 9) = 'PNG_thumb';
---#

---# Calculating attachment mime types.
---{
// Don't ever bother doing this twice.
if (@$modSettings['smfVersion'] < '2.0' || @$modSettings['smfVersion'] === '2.0 a')
{
	$request = upgrade_query("
		SELECT MAX(id_attach)
		FROM {$db_prefix}attachments");
	list ($step_progress['total']) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Calculating MIME Types';
	$step_progress['current'] = $_GET['a'];

	if (!function_exists('getAttachmentFilename'))
	{
		function getAttachmentFilename($filename, $attachment_id)
		{
			global $modSettings;

			$clean_name = strtr($filename, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
			$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));
			$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
			$enc_name = $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
			$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

			if ($attachment_id == false)
				return $clean_name;

			if (file_exists($modSettings['attachmentUploadDir'] . '/' . $enc_name))
				$filename = $modSettings['attachmentUploadDir'] . '/' . $enc_name;
			else
				$filename = $modSettings['attachmentUploadDir'] . '/' . $clean_name;

			return $filename;
		}
	}

	$ext_updates = array();

	// What headers are valid results for getimagesize?
	$validTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff',
	);

	$is_done = false;
	while (!$is_done)
	{
		nextSubStep($substep);

		$request = upgrade_query("
			SELECT id_attach, filename, fileext
			FROM {$db_prefix}attachments
			WHERE fileext != ''
				AND mime_type = ''
			LIMIT $_GET[a], 100");
		// Finished?
		if ($smcFunc['db_num_rows']($request) == 0)
			$is_done = true;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$filename = getAttachmentFilename($row['filename'], $row['id_attach']);
			if (!file_exists($filename))
				continue;

			// Is it an image?
			$size = @getimagesize($filename);
			// Nothing valid?
			if (empty($size) || empty($size[0]))
				continue;
			// Got the mime?
			elseif (!empty($size['mime']))
				$mime = $size['mime'];
			// Otherwise is it valid?
			elseif (!isset($validTypes[$size[2]]))
				continue;
			else
				$mime = 'image/' . $validTypes[$size[2]];

			// Let's try keep updates to a minimum.
			if (!isset($ext_updates[$row['fileext'] . $size['mime']]))
				$ext_updates[$row['fileext'] . $size['mime']] = array(
					'fileext' => $row['fileext'],
					'mime' => $mime,
					'files' => array(),
				);
			$ext_updates[$row['fileext'] . $size['mime']]['files'][] = $row['id_attach'];
		}
		$smcFunc['db_free_result']($request);

		// Do the updates?
		foreach ($ext_updates as $key => $update)
		{
			upgrade_query("
				UPDATE {$db_prefix}attachments
				SET mime_type = '$update[mime]'
				WHERE id_attach IN (" . implode(',', $update['files']) . ")");

			// Remove it.
			unset($ext_updates[$key]);
		}

		$_GET['a'] += 100;
		$step_progress['current'] = $_GET['a'];
	}

	unset($_GET['a']);
}
---}
---#

/******************************************************************************/
--- Adding Post Moderation.
/******************************************************************************/

---# Creating "approval_queue" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}approval_queue (
	id_msg int(10) unsigned NOT NULL default '0',
	id_attach int(10) unsigned NOT NULL default '0',
	id_event smallint(5) unsigned NOT NULL default '0'
) ENGINE=MyISAM{$db_collation};
---#

---# Adding approved column to attachments table...
ALTER TABLE {$db_prefix}attachments
ADD approved tinyint(3) NOT NULL default '1';
---#

---# Adding approved column to messages table...
ALTER TABLE {$db_prefix}messages
ADD approved tinyint(3) NOT NULL default '1';

ALTER TABLE {$db_prefix}messages
ADD INDEX approved (approved);
---#

---# Adding unapproved count column to topics table...
ALTER TABLE {$db_prefix}topics
ADD unapproved_posts smallint(5) NOT NULL default '0';
---#

---# Adding approved column to topics table...
ALTER TABLE {$db_prefix}topics
ADD approved tinyint(3) NOT NULL default '1',
ADD INDEX approved (approved);
---#

---# Adding approved columns to boards table...
ALTER TABLE {$db_prefix}boards
ADD unapproved_posts smallint(5) NOT NULL default '0',
ADD unapproved_topics smallint(5) NOT NULL default '0';
---#

---# Adding post moderation permissions...
---{
// We *cannot* do this twice!
if (@$modSettings['smfVersion'] < '2.0')
{
	// Anyone who can currently edit posts we assume can approve them...
	$request = upgrade_query("
		SELECT id_group, id_board, add_deny, permission
		FROM {$db_prefix}board_permissions
		WHERE permission = 'modify_any'");
	$inserts = array();
	while ($row = smf_mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], $row[id_board], 'approve_posts', $row[add_deny])";
	}
	smf_mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}board_permissions
				(id_group, id_board, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
}
---}
---#

/******************************************************************************/
--- Upgrading the error log.
/******************************************************************************/

---# Adding columns to log_errors table...
ALTER TABLE {$db_prefix}log_errors
ADD error_type char(15) NOT NULL default 'general';
ALTER TABLE {$db_prefix}log_errors
ADD file varchar(255) NOT NULL default '',
ADD line mediumint(8) unsigned NOT NULL default '0';
---#

---# Updating error log table...
---{
$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}log_errors");
list($totalActions) = smf_mysql_fetch_row($request);
smf_mysql_free_result($request);

$_GET['m'] = !empty($_GET['m']) ? (int) $_GET['m'] : '0';
$step_progress['total'] = $totalActions;
$step_progress['current'] = $_GET['m'];

while ($_GET['m'] < $totalActions)
{
	nextSubStep($substep);

	$request = upgrade_query("
		SELECT id_error, message, file, line
		FROM {$db_prefix}log_errors
		LIMIT $_GET[m], 500");
	while($row = smf_mysql_fetch_assoc($request))
	{
		preg_match('~<br />(%1\$s: )?([\w\. \\\\/\-_:]+)<br />(%2\$s: )?([\d]+)~', $row['message'], $matches);
		if (!empty($matches[2]) && !empty($matches[4]) && empty($row['file']) && empty($row['line']))
		{
			$row['file'] = addslashes(str_replace('\\', '/', $matches[2]));
			$row['line'] = (int) $matches[4];
			$row['message'] = addslashes(preg_replace('~<br />(%1\$s: )?([\w\. \\\\/\-_:]+)<br />(%2\$s: )?([\d]+)~', '', $row['message']));
		}
		else
			continue;

		upgrade_query("
			UPDATE {$db_prefix}log_errors
			SET file = SUBSTRING('$row[file]', 1, 255),
				line = $row[line],
				message = SUBSTRING('$row[message]', 1, 65535)
			WHERE id_error = $row[id_error]
			LIMIT 1");
	}

	$_GET['m'] += 500;
	$step_progress['current'] = $_GET['m'];
}
unset($_GET['m']);
---}
---#

/******************************************************************************/
--- Adding Scheduled Tasks Data.
/******************************************************************************/

---# Creating Scheduled Task Table...
CREATE TABLE IF NOT EXISTS {$db_prefix}scheduled_tasks (
	id_task smallint(5) NOT NULL auto_increment,
	next_time int(10) NOT NULL default '0',
	time_offset int(10) NOT NULL default '0',
	time_regularity smallint(5) NOT NULL default '0',
	time_unit varchar(1) NOT NULL default 'h',
	disabled tinyint(3) NOT NULL default '0',
	task varchar(24) NOT NULL default '',
	PRIMARY KEY (id_task),
	KEY next_time (next_time),
	KEY disabled (disabled),
	UNIQUE task (task)
) ENGINE=MyISAM{$db_collation};
---#

---# Populating Scheduled Task Table...
INSERT IGNORE INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 0, 2, 'h', 0, 'approval_notification'),
	(0, 60, 1, 'd', 0, 'daily_maintenance'),
	(0, 0, 1, 'd', 0, 'daily_digest'),
	(0, 0, 1, 'w', 0, 'weekly_digest'),
	(0, 0, 1, 'd', 1, 'birthdayemails'),
	(0, 120, 1, 'd', 0, 'paid_subscriptions');
---#

---# Adding the simple machines scheduled task.
---{
// Randomise the time.
$randomTime = 82800 + mt_rand(0, 86399);
upgrade_query("
	INSERT IGNORE INTO {$db_prefix}scheduled_tasks
		(next_time, time_offset, time_regularity, time_unit, disabled, task)
	VALUES
		(0, {$randomTime}, 1, 'd', 0, 'fetchSMfiles')");
---}
---#

---# Deleting old scheduled task items...
DELETE FROM {$db_prefix}scheduled_tasks
WHERE task = 'clean_cache';
---#

---# Moving auto optimise settings to scheduled task...
---{
if (!isset($modSettings['next_task_time']) && isset($modSettings['autoOptLastOpt']))
{
	// Try move over the regularity...
	if (isset($modSettings['autoOptDatabase']))
	{
		$disabled = empty($modSettings['autoOptDatabase']) ? 1 : 0;
		$regularity = $disabled ? 7 : $modSettings['autoOptDatabase'];
		$next_time = $modSettings['autoOptLastOpt'] + 3600 * 24 * $modSettings['autoOptDatabase'];

		// Update the task accordingly.
		upgrade_query("
			UPDATE {$db_prefix}scheduled_tasks
			SET disabled = $disabled, time_regularity = $regularity, next_time = $next_time
			WHERE task = 'auto_optimize'");
	}

	// Delete the old settings!
	upgrade_query("
		DELETE FROM {$db_prefix}settings
		WHERE VARIABLE IN ('autoOptLastOpt', 'autoOptDatabase')");
}
---}
---#

---# Creating Scheduled Task Log Table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_scheduled_tasks (
	id_log mediumint(8) NOT NULL auto_increment,
	id_task smallint(5) NOT NULL default '0',
	time_run int(10) NOT NULL default '0',
	time_taken float NOT NULL default '0',
	PRIMARY KEY (id_log)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding new scheduled task setting...
---{
if (!isset($modSettings['next_task_time']))
{
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('next_task_time', '0')");
}
---}
---#

---# Setting the birthday email template if not set...
---{
if (!isset($modSettings['birthday_email']))
{
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('birthday_email', 'happy_birthday')");
}
---}
---#

/******************************************************************************/
--- Adding permission profiles for boards.
/******************************************************************************/

---# Creating "permission_profiles" table...
CREATE TABLE IF NOT EXISTS {$db_prefix}permission_profiles (
	id_profile smallint(5) NOT NULL auto_increment,
	profile_name varchar(255) NOT NULL default '',
	PRIMARY KEY (id_profile)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding profile columns to boards table...
ALTER TABLE {$db_prefix}boards
ADD id_profile smallint(5) unsigned NOT NULL default '1' AFTER member_groups;
---#

---# Adding profile columns to board permission table...
ALTER TABLE {$db_prefix}board_permissions
ADD id_profile smallint(5) unsigned NOT NULL default '1' AFTER id_group;

ALTER TABLE {$db_prefix}board_permissions
DROP PRIMARY KEY;

ALTER TABLE {$db_prefix}board_permissions
ADD PRIMARY KEY (id_group, id_profile, permission);
---#

---# Cleaning up some 2.0 Beta 1 permission profile bits...
---{
$request = upgrade_query("
	SELECT id_profile
	FROM {$db_prefix}permission_profiles
	WHERE profile_name = ''");
$profiles = array();
while ($row = smf_mysql_fetch_assoc($request))
	$profiles[] = $row['id_profile'];
smf_mysql_free_result($request);

if (!empty($profiles))
{
	$request = upgrade_query("
		SELECT id_profile, name
		FROM {$db_prefix}boards
		WHERE id_profile IN (" . implode(',', $profiles) . ")");
	$done_ids = array();
	while ($row = smf_mysql_fetch_assoc($request))
	{
		if (isset($done_ids[$row['id_profile']]))
			continue;
		$done_ids[$row['id_profile']] = true;

		$row['name'] = smf_mysql_real_escape_string($row['name']);

		upgrade_query("
			UPDATE {$db_prefix}permission_profiles
			SET profile_name = '$row[name]'
			WHERE id_profile = $row[id_profile]");
	}
	smf_mysql_free_result($request);
}
---}
---#

---# Migrating old board profiles to profile system
---{

// Doing this twice would be awful!
$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}permission_profiles");
list ($profileCount) = smf_mysql_fetch_row($request);
smf_mysql_free_result($request);

if ($profileCount == 0)
{
	// Everything starts off invalid.
	upgrade_query("
		UPDATE {$db_prefix}board_permissions
		SET id_profile = 0");

	// Insert a boat load of default profile permissions.
	upgrade_query("
		INSERT INTO {$db_prefix}permission_profiles
			(id_profile, profile_name)
		VALUES
			(1, 'default'),
			(2, 'no_polls'),
			(3, 'reply_only'),
			(4, 'read_only')");

	// Update the default permissions, this is easy!
	upgrade_query("
		UPDATE {$db_prefix}board_permissions
		SET id_profile = 1
		WHERE id_board = 0");

	// Load all the other permissions
	$request = upgrade_query("
		SELECT id_board, id_group, permission, add_deny
		FROM {$db_prefix}board_permissions
		WHERE id_profile = 0");
	$all_perms = array();
	while ($row = smf_mysql_fetch_assoc($request))
		$all_perms[$row['id_board']][$row['id_group']][$row['permission']] = $row['add_deny'];
	smf_mysql_free_result($request);

	// Now we have the profile profiles for this installation. We now need to go through each board and work out what the permission profile should be!
	$request = upgrade_query("
		SELECT id_board, name, permission_mode
		FROM {$db_prefix}boards");
	$board_updates = array();
	while ($row = smf_mysql_fetch_assoc($request))
	{
		$row['name'] = addslashes($row['name']);

		// Is it a truely local permission board? If so this is a new profile!
		if ($row['permission_mode'] == 1)
		{
			// I know we could cache this, but I think we need to be practical - this is slow but guaranteed to work.
			upgrade_query("
				INSERT INTO {$db_prefix}permission_profiles
					(profile_name)
				VALUES
					('$row[name]')");
			$board_updates[smf_mysql_insert_id()][] = $row['id_board'];
		}
		// Otherwise, dear god, this is an old school "simple" permission...
		elseif ($row['permission_mode'] > 1 && $row['permission_mode'] < 5)
		{
			$board_updates[$row['permission_mode']][] = $row['id_board'];
		}
		// Otherwise this is easy. It becomes default.
		else
			$board_updates[1][] = $row['id_board'];
	}
	smf_mysql_free_result($request);

	// Update the board tables.
	foreach ($board_updates as $profile => $boards)
	{
		if (empty($boards))
			continue;

		$boards = implode(',', $boards);

		upgrade_query("
			UPDATE {$db_prefix}boards
			SET id_profile = $profile
			WHERE id_board IN ($boards)");

		// If it's a custom profile then update this too.
		if ($profile > 4)
			upgrade_query("
				UPDATE {$db_prefix}board_permissions
				SET id_profile = $profile
				WHERE id_board IN ($boards)
					AND id_profile = 0");
	}

	// Just in case we have any random permissions that didn't have boards.
	upgrade_query("
		DELETE FROM {$db_prefix}board_permissions
		WHERE id_profile = 0");
}
---}
---#

---# Removing old board permissions column...
ALTER TABLE {$db_prefix}board_permissions
DROP COLUMN id_board;
---#

---# Check the predefined profiles all have the right permissions.
---{
// What are all the permissions people can have.
$mod_permissions = array(
	'moderate_board', 'post_new', 'post_reply_own', 'post_reply_any', 'poll_post', 'poll_add_any',
	'poll_remove_any', 'poll_view', 'poll_vote', 'poll_lock_any', 'poll_edit_any', 'report_any',
	'lock_own', 'send_topic', 'mark_any_notify', 'mark_notify', 'delete_own', 'modify_own', 'make_sticky',
	'lock_any', 'remove_any', 'move_any', 'merge_any', 'split_any', 'delete_any', 'modify_any', 'approve_posts',
	'post_attachment', 'view_attachments', 'post_unapproved_replies_any', 'post_unapproved_replies_own',
	'post_unapproved_attachments', 'post_unapproved_topics',
);

$no_poll_reg = array(
	'post_new', 'post_reply_own', 'post_reply_any', 'poll_view', 'poll_vote', 'report_any',
	'lock_own', 'send_topic', 'mark_any_notify', 'mark_notify', 'delete_own', 'modify_own',
	'post_attachment', 'view_attachments', 'remove_own', 'post_unapproved_replies_any', 'post_unapproved_replies_own',
	'post_unapproved_attachments', 'post_unapproved_topics',
);

$reply_only_reg = array(
	'post_reply_own', 'post_reply_any', 'poll_view', 'poll_vote', 'report_any',
	'lock_own', 'send_topic', 'mark_any_notify', 'mark_notify', 'delete_own', 'modify_own',
	'post_attachment', 'view_attachments', 'remove_own', 'post_unapproved_replies_any', 'post_unapproved_replies_own',
	'post_unapproved_attachments',
);

$read_only_reg = array(
	'poll_view', 'poll_vote', 'report_any', 'send_topic', 'mark_any_notify', 'mark_notify', 'view_attachments',
);

// Clear all the current predefined profiles.
upgrade_query("
	DELETE FROM {$db_prefix}board_permissions
	WHERE id_profile IN (2,3,4)");

// Get all the membergroups - cheating to use the fact id_group = 1 exists to get a group of 0.
$request = upgrade_query("
	SELECT IF(id_group = 1, 0, id_group) AS id_group
	FROM {$db_prefix}membergroups
	WHERE id_group != 0
		AND min_posts = -1");
$inserts = array();
while ($row = smf_mysql_fetch_assoc($request))
{
	if ($row['id_group'] == 2 || $row['id_group'] == 3)
	{
		foreach ($mod_permissions as $permission)
		{
			$inserts[] = "($row[id_group], 2, '$permission')";
			$inserts[] = "($row[id_group], 3, '$permission')";
			$inserts[] = "($row[id_group], 4, '$permission')";
		}
	}
	else
	{
		foreach ($no_poll_reg as $permission)
			$inserts[] = "($row[id_group], 2, '$permission')";
		foreach ($reply_only_reg as $permission)
			$inserts[] = "($row[id_group], 3, '$permission')";
		foreach ($read_only_reg as $permission)
			$inserts[] = "($row[id_group], 4, '$permission')";
	}
}
smf_mysql_free_result($request);

upgrade_query("
	INSERT INTO {$db_prefix}board_permissions
		(id_group, id_profile, permission)
	VALUES (-1, 2, 'poll_view'),
		(-1, 3, 'poll_view'),
		(-1, 4, 'poll_view'),
		" . implode(', ', $inserts));

---}
---#

---# Adding inherited permissions...
ALTER TABLE {$db_prefix}membergroups
ADD id_parent smallint(5) NOT NULL default '-2';
---#

---# Make sure admins and moderators don't inherit...
UPDATE {$db_prefix}membergroups
SET id_parent = -2
WHERE id_group = 1
	OR id_group = 3;
---#

---# Deleting old permission settings...
DELETE FROM {$db_prefix}settings
WHERE VARIABLE IN ('permission_enable_by_board', 'autoOptDatabase');
---#

---# Removing old permission_mode column...
ALTER TABLE {$db_prefix}boards
DROP COLUMN permission_mode;
---#

/******************************************************************************/
--- Adding Some Additional Functionality.
/******************************************************************************/

---# Adding column to hold the boards being ignored ...
ALTER TABLE {$db_prefix}members
ADD ignore_boards text NOT NULL;
---#

---# Purge flood control ...
DELETE FROM {$db_prefix}log_floodcontrol;
---#

---# Adding advanced flood control ...
ALTER TABLE {$db_prefix}log_floodcontrol
ADD log_type varchar(8) NOT NULL default 'post';
---#

---# Sorting out flood control keys ...
ALTER TABLE {$db_prefix}log_floodcontrol
DROP PRIMARY KEY,
ADD PRIMARY KEY (ip(16), log_type(8));
---#

---# Adding guest voting ...
ALTER TABLE {$db_prefix}polls
ADD guest_vote tinyint(3) NOT NULL default '0';

DELETE FROM {$db_prefix}log_polls
WHERE id_member < 0;

ALTER TABLE {$db_prefix}log_polls
DROP PRIMARY KEY;

ALTER TABLE {$db_prefix}log_polls
ADD INDEX id_poll (id_poll, id_member, id_choice);
---#

---# Implementing admin feature toggles.
---{
if (!isset($modSettings['admin_features']))
{
	// Work out what they used to have enabled.
	$enabled_features = array('rg');
	if (!empty($modSettings['cal_enabled']))
		$enabled_features[] = 'cd';
	if (!empty($modSettings['karmaMode']))
		$enabled_features[] = 'k';
	if (!empty($modSettings['modlog_enabled']))
		$enabled_features[] = 'ml';
	if (!empty($modSettings['paid_enabled']))
		$enabled_features[] = 'ps';

	$enabled_features = implode(',', $enabled_features);

	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('admin_features', '$enabled_features')");
}
---}
---#

---# Adding advanced password brute force protection to "members" table...
ALTER TABLE {$db_prefix}members
ADD passwd_flood varchar(12) NOT NULL default '';
---#

/******************************************************************************/
--- Adding some columns to moderation log
/******************************************************************************/
---# Add the columns and the keys to log_actions ...
ALTER TABLE {$db_prefix}log_actions
ADD id_board smallint(5) unsigned NOT NULL default '0',
ADD id_topic mediumint(8) unsigned NOT NULL default '0',
ADD id_msg int(10) unsigned NOT NULL default '0',
ADD KEY id_board (id_board),
ADD KEY id_msg (id_msg);
---#

---# Add the user log...
ALTER TABLE {$db_prefix}log_actions
ADD id_log tinyint(3) unsigned NOT NULL default '1',
ADD KEY id_log (id_log);
---#

---# Update the information already in log_actions
---{
$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}log_actions");
list($totalActions) = smf_mysql_fetch_row($request);
smf_mysql_free_result($request);

$_GET['m'] = !empty($_GET['m']) ? (int) $_GET['m'] : '0';
$step_progress['total'] = $totalActions;
$step_progress['current'] = $_GET['m'];

while ($_GET['m'] < $totalActions)
{
	nextSubStep($substep);

	$mrequest = upgrade_query("
		SELECT id_action, extra, id_board, id_topic, id_msg
		FROM {$db_prefix}log_actions
		LIMIT $_GET[m], 500");

	while ($row = smf_mysql_fetch_assoc($mrequest))
	{
		if (!empty($row['id_board']) || !empty($row['id_topic']) || !empty($row['id_msg']))
			continue;
		$row['extra'] = @unserialize($row['extra']);
		// Corrupt?
		$row['extra'] = is_array($row['extra']) ? $row['extra'] : array();
		if (!empty($row['extra']['board']))
		{
			$board_id = (int) $row['extra']['board'];
			unset($row['extra']['board']);
		}
		else
			$board_id = '0';
		if (!empty($row['extra']['board_to']) && empty($board_id))
		{
			$board_id = (int) $row['extra']['board_to'];
			unset($row['extra']['board_to']);
		}

		if (!empty($row['extra']['topic']))
		{
			$topic_id = (int) $row['extra']['topic'];
			unset($row['extra']['topic']);
			if (empty($board_id))
			{
				$trequest = upgrade_query("
					SELECT id_board
					FROM {$db_prefix}topics
					WHERE id_topic=$topic_id
					LIMIT 1");
				if (smf_mysql_num_rows($trequest))
					list($board_id) = smf_mysql_fetch_row($trequest);
				smf_mysql_free_result($trequest);
			}
		}
		else
			$topic_id = '0';

		if(!empty($row['extra']['message']))
		{
			$msg_id = (int) $row['extra']['message'];
			unset($row['extra']['message']);
			if (empty($topic_id) || empty($board_id))
			{
				$trequest = upgrade_query("
					SELECT id_board, id_topic
					FROM {$db_prefix}messages
					WHERE id_msg=$msg_id
					LIMIT 1");
				if (smf_mysql_num_rows($trequest))
					list($board_id, $topic_id) = smf_mysql_fetch_row($trequest);
				smf_mysql_free_result($trequest);
			}
		}
		else
			$msg_id = '0';
		$row['extra'] = addslashes(serialize($row['extra']));
		upgrade_query("UPDATE {$db_prefix}log_actions SET id_board=$board_id, id_topic=$topic_id, id_msg=$msg_id, extra='$row[extra]' WHERE id_action=$row[id_action]");
	}
	$_GET['m'] += 500;
	$step_progress['current'] = $_GET['m'];
}
unset($_GET['m']);
---}
---#

/******************************************************************************/
--- Create a repository for the javascript files from Simple Machines...
/******************************************************************************/

---# Creating repository table ...
CREATE TABLE IF NOT EXISTS {$db_prefix}admin_info_files (
  id_file tinyint(4) unsigned NOT NULL auto_increment,
  filename varchar(255) NOT NULL default '',
  path varchar(255) NOT NULL default '',
  parameters varchar(255) NOT NULL default '',
  data text NOT NULL,
  filetype varchar(255) NOT NULL default '',
  PRIMARY KEY (id_file),
  KEY filename (filename(30))
) ENGINE=MyISAM{$db_collation};
---#

---# Add in the files to get from Simple Machines...
INSERT IGNORE INTO {$db_prefix}admin_info_files
	(id_file, filename, path, parameters)
VALUES
	(1, 'current-version.js', '/smf/', 'version=%3$s'),
	(2, 'detailed-version.js', '/smf/', 'language=%1$s&version=%3$s'),
	(3, 'latest-news.js', '/smf/', 'language=%1$s&format=%2$s'),
	(4, 'latest-packages.js', '/smf/', 'language=%1$s&version=%3$s'),
	(5, 'latest-smileys.js', '/smf/', 'language=%1$s&version=%3$s'),
	(6, 'latest-themes.js', '/smf/', 'language=%1$s&version=%3$s');
---#

---# Ensure that the table has the filetype column
ALTER TABLE {$db_prefix}admin_info_files
ADD filetype varchar(255) NOT NULL default '';
---#

---# Set the filetype for the files
UPDATE {$db_prefix}admin_info_files
SET filetype='text/javascript'
WHERE id_file IN (1,2,3,4,5,6,7);
---#

---# Ensure that the files from Simple Machines get updated
UPDATE {$db_prefix}scheduled_tasks
SET next_time = UNIX_TIMESTAMP()
WHERE id_task = 7
LIMIT 1;
---#

/******************************************************************************/
--- Adding new personal messaging functionality.
/******************************************************************************/

---# Adding personal message rules table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_rules (
	id_rule int(10) unsigned NOT NULL auto_increment,
	id_member int(10) unsigned NOT NULL default '0',
	rule_name varchar(60) NOT NULL,
	criteria text NOT NULL,
	actions text NOT NULL,
	delete_pm tinyint(3) unsigned NOT NULL default '0',
	is_or tinyint(3) unsigned NOT NULL default '0',
	PRIMARY KEY (id_rule),
	KEY id_member (id_member),
	KEY delete_pm (delete_pm)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding new message status columns...
ALTER TABLE {$db_prefix}members
ADD COLUMN new_pm tinyint(3) NOT NULL default '0';

ALTER TABLE {$db_prefix}members
ADD COLUMN pm_prefs mediumint(8) NOT NULL default '0';

ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN is_new tinyint(3) NOT NULL default '0';
---#

---# Set the new status to be correct....
---{
// Don't do this twice!
if (@$modSettings['smfVersion'] < '2.0')
{
	// Set all unread messages as new.
	upgrade_query("
		UPDATE {$db_prefix}pm_recipients
		SET is_new = 1
		WHERE is_read = 0");

	// Also set members to have a new pm if they have any unread.
	upgrade_query("
		UPDATE {$db_prefix}members
		SET new_pm = 1
		WHERE unread_messages > 0");
}
---}
---#

---# Adding personal message tracking column...
ALTER TABLE {$db_prefix}personal_messages
ADD id_pm_head int(10) unsigned default '0' NOT NULL AFTER id_pm,
ADD INDEX id_pm_head (id_pm_head);
---#

---# Adding personal message tracking column...
UPDATE {$db_prefix}personal_messages
SET id_pm_head = id_pm
WHERE id_pm_head = 0;
---#

/******************************************************************************/
--- Adding Open ID support.
/******************************************************************************/

---# Adding Open ID Association table...
CREATE TABLE IF NOT EXISTS {$db_prefix}openid_assoc (
	server_url text NOT NULL,
	handle varchar(255) NOT NULL default '',
	secret text NOT NULL,
	issued int(10) NOT NULL default '0',
	expires int(10) NOT NULL default '0',
	assoc_type varchar(64) NOT NULL,
	PRIMARY KEY (server_url(125), handle(125)),
	KEY expires (expires)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding column to hold Open ID URL...
ALTER TABLE {$db_prefix}members
ADD openid_uri text NOT NULL;
---#

/******************************************************************************/
--- Adding paid subscriptions.
/******************************************************************************/

---# Creating subscriptions table...
CREATE TABLE IF NOT EXISTS {$db_prefix}subscriptions(
	id_subscribe mediumint(8) unsigned NOT NULL auto_increment,
	name varchar(60) NOT NULL default '',
	description varchar(255) NOT NULL default '',
	cost text NOT NULL,
	length varchar(6) NOT NULL default '',
	id_group smallint(5) NOT NULL default '0',
	add_groups varchar(40) NOT NULL default '',
	active tinyint(3) NOT NULL default '1',
	repeatable tinyint(3) NOT NULL default '0',
	allow_partial tinyint(3) NOT NULL default '0',
	reminder tinyint(3) NOT NULL default '0',
	email_complete text NOT NULL,
	PRIMARY KEY (id_subscribe),
	KEY active (active)
) ENGINE=MyISAM{$db_collation};
---#

---# Creating log_subscribed table...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_subscribed(
	id_sublog int(10) unsigned NOT NULL auto_increment,
	id_subscribe mediumint(8) unsigned NOT NULL default '0',
	id_member int(10) NOT NULL default '0',
	old_id_group smallint(5) NOT NULL default '0',
	start_time int(10) NOT NULL default '0',
	end_time int(10) NOT NULL default '0',
	status tinyint(3) NOT NULL default '0',
	payments_pending tinyint(3) NOT NULL default '0',
	pending_details text NOT NULL,
	reminder_sent tinyint(3) NOT NULL default '0',
	vendor_ref varchar(255) NOT NULL default '',
	PRIMARY KEY (id_sublog),
	UNIQUE KEY id_subscribe (id_subscribe, id_member),
	KEY end_time (end_time),
	KEY reminder_sent (reminder_sent),
	KEY payments_pending (payments_pending),
	KEY id_member (id_member)
) ENGINE=MyISAM{$db_collation};
---#

---# Clean up any pre-2.0 mod settings.
UPDATE {$db_prefix}settings
SET variable = 'paid_currency_code'
WHERE variable = 'currency_code';

UPDATE {$db_prefix}settings
SET variable = 'paid_currency_symbol'
WHERE variable = 'currency_symbol';

DELETE FROM {$db_prefix}settings
WHERE variable = 'currency_code'
	OR variable = 'currency_symbol';
---#

---# Clean up any pre-2.0 mod settings (part 2).
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}subscriptions");
$new_cols = array('repeatable', 'reminder', 'email_complete', 'allow_partial');
$new_cols = array_flip($new_cols);
while ($request && $row = smf_mysql_fetch_row($request))
{
	$row[0] = strtolower($row[0]);
	if (isset($new_cols[$row[0]]))
		unset($new_cols[$row[0]]);
}
if ($request)
	smf_mysql_free_result($request);

if (isset($new_cols['repeatable']))
	upgrade_query("
		ALTER TABLE {$db_prefix}subscriptions
		ADD COLUMN Repeatable tinyint(3) NOT NULL default '0'");
if (isset($new_cols['reminder']))
	upgrade_query("
		ALTER TABLE {$db_prefix}subscriptions
		ADD COLUMN reminder tinyint(3) NOT NULL default '0'");
if (isset($new_cols['email_complete']))
	upgrade_query("
		ALTER TABLE {$db_prefix}subscriptions
		ADD COLUMN email_complete text NOT NULL");
if (isset($new_cols['allowpartial']))
	upgrade_query("
		ALTER TABLE {$db_prefix}subscriptions
		ADD COLUMN allow_partial tinyint(3) NOT NULL default '0'");

$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}log_subscribed");
$new_cols = array('reminder_sent', 'vendor_ref', 'payments_pending', 'pending_details');
$new_cols = array_flip($new_cols);
while ($request && $row = smf_mysql_fetch_row($request))
{
	if (isset($new_cols[$row[0]]))
		unset($new_cols[$row[0]]);
}
if ($request)
	smf_mysql_free_result($request);

if (isset($new_cols['reminder_sent']))
	upgrade_query("
		ALTER TABLE {$db_prefix}log_subscribed
		ADD COLUMN reminder_sent tinyint(3) NOT NULL default '0'");
if (isset($new_cols['vendor_ref']))
	upgrade_query("
		ALTER TABLE {$db_prefix}log_subscribed
		ADD COLUMN vendor_ref varchar(255) NOT NULL default ''");
if (isset($new_cols['payments_pending']))
	upgrade_query("
		ALTER TABLE {$db_prefix}log_subscribed
		ADD COLUMN payments_pending tinyint(3) NOT NULL default '0'");
if (isset($new_cols['pending_details']))
{
	upgrade_query("
		UPDATE {$db_prefix}log_subscribed
		SET status = 0
		WHERE status = 1");
	upgrade_query("
		UPDATE {$db_prefix}log_subscribed
		SET status = 1
		WHERE status = 2");
	upgrade_query("
		ALTER TABLE {$db_prefix}log_subscribed
		ADD COLUMN pending_details text NOT NULL");
}
---}
---#

---# Confirming paid subscription keys are in place ...
ALTER TABLE {$db_prefix}log_subscribed
ADD KEY reminder_sent (reminder_sent),
ADD KEY end_time (end_time),
ADD KEY payments_pending (payments_pending),
ADD KEY status (status);
---#

/******************************************************************************/
--- Adding weekly maintenance task.
/******************************************************************************/

---# Adding scheduled task...
INSERT IGNORE INTO {$db_prefix}scheduled_tasks (next_time, time_offset, time_regularity, time_unit, disabled, task) VALUES (0, 0, 1, 'w', 0, 'weekly_maintenance');
---#

/******************************************************************************/
--- Adding log pruning.
/******************************************************************************/

---# Adding pruning option...
INSERT IGNORE INTO {$db_prefix}settings (variable, value) VALUES ('pruningOptions', '30,180,180,180,30,0');
---#

/******************************************************************************/
--- Adding restore topic from recycle.
/******************************************************************************/

---# Adding restore from recycle feature...
ALTER TABLE {$db_prefix}topics
ADD COLUMN id_previous_board smallint(5) NOT NULL default '0',
ADD COLUMN id_previous_topic mediumint(8) NOT NULL default '0';
---#

/******************************************************************************/
--- Providing more room for apf options.
/******************************************************************************/

---# Changing field_options column to a larger field type...
ALTER TABLE {$db_prefix}custom_fields
CHANGE field_options field_options text NOT NULL;
---#

/******************************************************************************/
--- Providing more room for ignoring boards.
/******************************************************************************/

---# Changing ignore_boards column to a larger field type...
ALTER TABLE {$db_prefix}members
CHANGE ignore_boards ignore_boards text NOT NULL;
---#

/******************************************************************************/
--- Allow for longer calendar event/holiday titles.
/******************************************************************************/

---# Changing event title column to a larger field type...
ALTER TABLE {$db_prefix}calendar
CHANGE title title varchar(255) NOT NULL default '';
---#

---# Changing holidays title column to a larger field type...
ALTER TABLE {$db_prefix}calendar_holidays
CHANGE title title varchar(255) NOT NULL default '';
---#

/******************************************************************************/
--- Adding extra columns to polls.
/******************************************************************************/

---# Adding reset poll timestamp and guest voters counter...
ALTER TABLE {$db_prefix}polls
ADD COLUMN reset_poll int(10) unsigned NOT NULL default '0' AFTER guest_vote,
ADD COLUMN num_guest_voters int(10) unsigned NOT NULL default '0' AFTER guest_vote;
---#

---# Fixing guest voter tallys on existing polls...
---{
$request = upgrade_query("
	SELECT p.id_poll, count(lp.id_member) as guest_voters
	FROM {$db_prefix}polls AS p
		LEFT JOIN {$db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member = 0)
	WHERE lp.id_member = 0
		AND p.num_guest_voters = 0
	GROUP BY p.id_poll");

while ($request && $row = $smcFunc['db_fetch_assoc']($request))
	upgrade_query("
		UPDATE {$db_prefix}polls
		SET num_guest_voters = ". $row['guest_voters']. "
		WHERE id_poll = " . $row['id_poll'] . "
			AND num_guest_voters = 0");
---}
---#

/******************************************************************************/
--- Changing all tinytext columns to varchar(255).
/******************************************************************************/

---# Changing all tinytext columns to varchar(255)...
---{
// The array holding all the changes.
$nameChanges = array(
	'admin_info_files' => array(
		'filename' => 'filename filename varchar(255) NOT NULL default \'\'',
		'path' => 'path path varchar(255) NOT NULL default \'\'',
		'parameters' => 'parameters parameters varchar(255) NOT NULL default \'\'',
		'filetype' => 'filetype filetype varchar(255) NOT NULL default \'\'',
	),
	'attachments' => array(
		'filename' => 'filename filename varchar(255) NOT NULL default \'\'',
	),
	'ban_groups' => array(
		'reason' => 'reason reason varchar(255) NOT NULL default \'\'',
	),
	'ban_items' => array(
		'hostname' => 'hostname hostname varchar(255) NOT NULL default \'\'',
		'email_address' => 'email_address email_address varchar(255) NOT NULL default \'\'',
	),
	'boards' => array(
		'name' => 'name name varchar(255) NOT NULL default \'\'',
	),
	'categories' => array(
		'name' => 'name name varchar(255) NOT NULL default \'\'',
	),
	'custom_fields' => array(
		'field_desc' => 'field_desc field_desc varchar(255) NOT NULL default \'\'',
		'mask' => 'mask mask varchar(255) NOT NULL default \'\'',
		'default_value' => 'default_value default_value varchar(255) NOT NULL default \'\'',
	),
	'log_banned' => array(
		'email' => 'email email varchar(255) NOT NULL default \'\'',
	),
	'log_comments' => array(
		'recipient_name' => 'recipient_name recipient_name varchar(255) NOT NULL default \'\'',
	),
	'log_errors' => array(
		'file' => 'file file varchar(255) NOT NULL default \'\'',
	),
	'log_member_notices' => array(
		'subject' => 'subject subject varchar(255) NOT NULL default \'\'',
	),
	'log_packages' => array(
		'filename' => 'filename filename varchar(255) NOT NULL default \'\'',
		'package_id' => 'package_id package_id varchar(255) NOT NULL default \'\'',
		'name' => 'name name varchar(255) NOT NULL default \'\'',
		'version' => 'version version varchar(255) NOT NULL default \'\'',
		'member_installed' => 'member_installed member_installed varchar(255) NOT NULL default \'\'',
		'member_removed' => 'member_removed member_removed varchar(255) NOT NULL default \'\'',
		'themes_installed' => 'themes_installed themes_installed varchar(255) NOT NULL default \'\'',
	),
	'log_reported' => array(
		'membername' => 'membername membername varchar(255) NOT NULL default \'\'',
		'subject' => 'subject subject varchar(255) NOT NULL default \'\'',
	),
	'log_reported_comments' => array(
		'membername' => 'membername membername varchar(255) NOT NULL default \'\'',
		'comment' => 'comment comment varchar(255) NOT NULL default \'\'',
	),
	'log_spider_hits' => array(
		'url' => 'url url varchar(255) NOT NULL default \'\'',
	),
	'log_subscribed' => array(
		'vendor_ref' => 'vendor_ref vendor_ref varchar(255) NOT NULL default \'\'',
	),
	'mail_queue' => array(
		'recipient' => 'recipient recipient varchar(255) NOT NULL default \'\'',
		'subject' => 'subject subject varchar(255) NOT NULL default \'\'',
	),
	'membergroups' => array(
		'stars' => 'stars stars varchar(255) NOT NULL default \'\'',
	),
	'members' => array(
		'lngfile' => 'lngfile lngfile varchar(255) NOT NULL default \'\'',
		'real_name' => 'real_name real_name varchar(255) NOT NULL default \'\'',
		'pm_ignore_list' => 'pm_ignore_list pm_ignore_list varchar(255) NOT NULL default \'\'',
		'email_address' => 'email_address email_address varchar(255) NOT NULL default \'\'',
		'personal_text' => 'personal_text personal_text varchar(255) NOT NULL default \'\'',
		'website_title' => 'website_title website_title varchar(255) NOT NULL default \'\'',
		'website_url' => 'website_url website_url varchar(255) NOT NULL default \'\'',
		'location' => 'location location varchar(255) NOT NULL default \'\'',
		'icq' => 'icq icq varchar(255) NOT NULL default \'\'',
		'aim' => 'aim aim varchar(255) NOT NULL default \'\'',
		'msn' => 'msn msn varchar(255) NOT NULL default \'\'',
		'avatar' => 'avatar avatar varchar(255) NOT NULL default \'\'',
		'usertitle' => 'usertitle usertitle varchar(255) NOT NULL default \'\'',
		'member_ip' => 'member_ip member_ip varchar(255) NOT NULL default \'\'',
		'member_ip2' => 'member_ip2 member_ip2 varchar(255) NOT NULL default \'\'',
		'secret_question' => 'secret_question secret_question varchar(255) NOT NULL default \'\'',
		'additional_groups' => 'additional_groups additional_groups varchar(255) NOT NULL default \'\'',
	),
	'messages' => array(
		'subject' => 'subject subject varchar(255) NOT NULL default \'\'',
		'poster_name' => 'poster_name poster_name varchar(255) NOT NULL default \'\'',
		'poster_email' => 'poster_email poster_email varchar(255) NOT NULL default \'\'',
		'poster_ip' => 'poster_ip poster_ip varchar(255) NOT NULL default \'\'',
		'modified_name' => 'modified_name modified_name varchar(255) NOT NULL default \'\'',
	),
	'openid_assoc' => array(
		'handle' => 'handle handle varchar(255) NOT NULL default \'\'',
	),
	'package_servers' => array(
		'name' => 'name name varchar(255) NOT NULL default \'\'',
		'url' => 'url url varchar(255) NOT NULL default \'\'',
	),
	'permission_profiles' => array(
		'profile_name' => 'profile_name profile_name varchar(255) NOT NULL default \'\'',
	),
	'personal_messages' => array(
		'from_name' => 'from_name from_name varchar(255) NOT NULL default \'\'',
		'subject' => 'subject subject varchar(255) NOT NULL default \'\'',
	),
	'polls' => array(
		'question' => 'question question varchar(255) NOT NULL default \'\'',
		'poster_name' => 'poster_name poster_name varchar(255) NOT NULL default \'\'',
	),
	'poll_choices' => array(
		'label' => 'label label varchar(255) NOT NULL default \'\'',
	),
	'settings' => array(
		'variable' => 'variable variable varchar(255) NOT NULL default \'\'',
	),
	'spiders' => array(
		'spider_name' => 'spider_name spider_name varchar(255) NOT NULL default \'\'',
		'user_agent' => 'user_agent user_agent varchar(255) NOT NULL default \'\'',
		'ip_info' => 'ip_info ip_info varchar(255) NOT NULL default \'\'',
	),
	'subscriptions' => array(
		'description' => 'description description varchar(255) NOT NULL default \'\'',
	),
	'themes' => array(
		'variable' => 'variable variable varchar(255) NOT NULL default \'\'',
	),
);

$_GET['ren_col'] = isset($_GET['ren_col']) ? (int) $_GET['ren_col'] : 0;
$step_progress['name'] = 'Changing tinytext columns to varchar(255)';
$step_progress['current'] = $_GET['ren_col'];
$step_progress['total'] = count($nameChanges);

$count = 0;
// Now do every table...
foreach ($nameChanges as $table_name => $table)
{
	// Already done this?
	$count++;
	if ($_GET['ren_col'] > $count)
		continue;
	$_GET['ren_col'] = $count;

	// Check the table exists!
	$request = upgrade_query("
		SHOW TABLES
		LIKE '{$db_prefix}$table_name'");
	if (smf_mysql_num_rows($request) == 0)
	{
		smf_mysql_free_result($request);
		continue;
	}
	smf_mysql_free_result($request);

	// Converting is intensive, so make damn sure that we need to do it.
	$request = upgrade_query("
		SHOW FIELDS
		FROM `{$db_prefix}$table_name`");
	$tinytextColumns = array();
	while($row = smf_mysql_fetch_assoc($request))
	{
		// Tinytext detected so store column name.
		if ($row['Type'] == 'tinytext')
			$tinytextColumns[$row['Field']] = $row['Field'];
	}
	smf_mysql_free_result($request);

	// Check each column!
	$actualChanges = array();
	foreach ($table as $colname => $coldef)
	{
		// Column was not detected as tinytext so skip it
		// Either it was already converted or was changed eg text (so do not break it)
		if (!isset($tinytextColumns[$colname]))
			continue;

		$change = array(
			'table' => $table_name,
			'name' => $colname,
			'type' => 'column',
			'method' => 'change_remove',
			'text' => 'CHANGE ' . $coldef,
		);
		if (protected_alter($change, $substep, true) == false)
			$actualChanges[] = ' CHANGE COLUMN ' . $coldef;
	}

	// Do the query - if it needs doing.
	if (!empty($actualChanges))
	{
		$change = array(
			'table' => $table_name,
			'name' => 'na',
			'type' => 'table',
			'method' => 'full_change',
			'text' => implode(', ', $actualChanges),
		);

		// Here we go - hold on!
		protected_alter($change, $substep);
	}

	// Update where we are!
	$step_progress['current'] = $_GET['ren_col'];
}

// All done!
unset($_GET['ren_col']);
---}
---#

/******************************************************************************/
--- Adding new personal message setting.
/******************************************************************************/

---# Adding column that stores the PM receiving setting...
ALTER TABLE {$db_prefix}members
ADD COLUMN pm_receive_from tinyint(4) unsigned NOT NULL default '1';
---#

---# Enable the buddy and ignore lists if we have not done so thus far...
---{

// Don't do this if we've done this already.
if (empty($modSettings['dont_repeat_buddylists']))
{
	// Make sure the pm_receive_from column has the right default value - early adopters might have a '0' set here.
	upgrade_query("
		ALTER TABLE {$db_prefix}members
		CHANGE pm_receive_from pm_receive_from tinyint(3) unsigned NOT NULL default '1'");

	// Update previous ignore lists if they're set to ignore all.
	upgrade_query("
		UPDATE {$db_prefix}members
		SET pm_receive_from = 3, pm_ignore_list = ''
		WHERE pm_ignore_list = '*'");

	// Ignore posts made by ignored users by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}themes
			(id_member, id_theme, variable, value)
		VALUES
			(-1, 1, 'posts_apply_ignore_list', '1')");

	// Enable buddy and ignore lists, and make sure not to skip this step next time we run this.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('enable_buddylist', '1'),
			('dont_repeat_buddylists', '1')");
}

// And yet, and yet... We might have a small hiccup here...
if (!empty($modSettings['dont_repeat_buddylists']) && !isset($modSettings['enable_buddylist']))
{
	// Correct RC3 adopters setting here...
	if (isset($modSettings['enable_buddylists']))
	{
		upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('enable_buddylist', '" . $modSettings['enable_buddylists'] . "')");
	}
	else
	{
		// This should never happen :)
		upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('enable_buddylist', '1')");
	}
}

---}
---#

/******************************************************************************/
--- Adding settings for attachments and avatars.
/******************************************************************************/

---# Add new security settings for attachments and avatars...
---{

// Don't do this if we've done this already.
if (!isset($modSettings['attachment_image_reencode']))
{
	// Enable image re-encoding by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('attachment_image_reencode', '1')");
}
if (!isset($modSettings['attachment_image_paranoid']))
{
	// Disable draconic checks by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('attachment_image_paranoid', '0')");
}
if (!isset($modSettings['avatar_reencode']))
{
	// Enable image re-encoding by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('avatar_reencode', '1')");
}
if (!isset($modSettings['avatar_paranoid']))
{
	// Disable draconic checks by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('avatar_paranoid', '0')");
}

---}
---#

---# Add other attachment settings...
---{
if (!isset($modSettings['attachment_thumb_png']))
{
	// Make image attachment thumbnail as PNG by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('attachment_thumb_png', '1')");
}

---}
---#

/******************************************************************************/
--- Cleaning up after old themes...
/******************************************************************************/

---# Checking for "babylon" and removing it if necessary...
---{
// Do they have "babylon" installed?
if (file_exists($GLOBALS['boarddir'] . '/Themes/babylon'))
{
	$babylon_dir = $GLOBALS['boarddir'] . '/Themes/babylon';
	$theme_request = upgrade_query("
		SELECT ID_THEME
		FROM {$db_prefix}themes
		WHERE variable = 'theme_dir'
			AND value ='$babylon_dir'");

	// Don't do anything if this theme is already uninstalled
	if (smf_mysql_num_rows($theme_request) == 1)
	{
		$row = smf_mysql_fetch_row($theme_request);
		$id_theme = $row[0];
		smf_mysql_free_result($theme_request);
		unset($row);

		$known_themes = explode(', ', $modSettings['knownThemes']);

		// Remove this value...
		$known_themes = array_diff($known_themes, array($id_theme));

		// Change back to a string...
		$known_themes = implode(', ', $known_themes);

		// Update the database
		upgrade_query("
			REPLACE INTO {$db_prefix}settings (variable, value)
			VALUES ('knownThemes', '$known_themes')");

		// Delete any info about this theme
		upgrade_query("
			DELETE FROM {$db_prefix}themes
			WHERE id_theme = $id_theme");

		// Set any members or boards using this theme to the default
		upgrade_query("
			UPDATE {$db_prefix}members
			SET id_theme = 0
			WHERE id_theme = $id_theme");

		upgrade_query("
			UPDATE {$db_prefix}boards
			SET id_theme = 0
			WHERE id_theme = $id_theme");

		if ($modSettings['theme_guests'] == $id_theme)
		{
			upgrade_query("
				REPLACE INTO {$db_prefix}settings
				(variable, value)
				VALUES('theme_guests', 0)");
		}
	}
}
---}
---#

/******************************************************************************/
--- Installing new smileys sets...
/******************************************************************************/

---# Installing new smiley sets...
---{
// Don't do this twice!
if (empty($modSettings['dont_repeat_smileys_20']) && empty($modSettings['installed_new_smiley_sets_20']))
{
	// First, the entries.
	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = CONCAT(value, ',aaron,akyhne')
		WHERE variable = 'smiley_sets_known'");

	// Second, the names.
	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = CONCAT(value, '\nAaron\nAkyhne')
		WHERE variable = 'smiley_sets_names'");

	// This ain't running twice either.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('installed_new_smiley_sets_20', '1')");
}
---}
---#

/******************************************************************************/
--- Adding new indexes to the topics table.
/******************************************************************************/

---# Adding index member_started...
ALTER TABLE {$db_prefix}topics
ADD INDEX member_started (id_member_started, id_board);
---#

---# Adding index last_message_sticky...
ALTER TABLE {$db_prefix}topics
ADD INDEX last_message_sticky (id_board, is_sticky, id_last_msg);
---#

---# Adding index board_news...
ALTER TABLE {$db_prefix}topics
ADD INDEX board_news (id_board, id_first_msg);
---#

/******************************************************************************/
--- Adding new indexes to members table.
/******************************************************************************/

---# Adding index on total_time_logged_in...
ALTER TABLE {$db_prefix}members
ADD INDEX total_time_logged_in (total_time_logged_in);
---#

---# Adding index on id_theme...
ALTER TABLE {$db_prefix}members
ADD INDEX id_theme (id_theme);
---#

---# Dropping index on real_name(30) ...
---{
// Detect existing index with limited length
$request = upgrade_query("
	SHOW INDEXES
	FROM {$db_prefix}members"
);

// Drop the existing index before we recreate it.
while ($row = smf_mysql_fetch_assoc($request))
{
	if ($row['Key_name'] === 'real_name' && $row['Sub_part'] == 30)
	{
		upgrade_query("
			ALTER TABLE {$db_prefix}members
			DROP INDEX real_name"
		);
		break;
	}
}

smf_mysql_free_result($request);
---}
---#

---# Adding index on real_name...
ALTER TABLE {$db_prefix}members
ADD INDEX real_name (real_name);
---#

---# Dropping index member_name(30)...
---{
// Detect existing index with limited length
$request = upgrade_query("
	SHOW INDEXES
	FROM {$db_prefix}members"
);

// Drop the existing index before we recreate it.
while ($row = smf_mysql_fetch_assoc($request))
{
	if ($row['Key_name'] === 'member_name' && $row['Sub_part'] == 30)
	{
		upgrade_query("
			ALTER TABLE {$db_prefix}members
			DROP INDEX member_name"
		);
		break;
	}
}

smf_mysql_free_result($request);

---}
---#

---# Adding index on member_name...
ALTER TABLE {$db_prefix}members
ADD INDEX member_name (member_name);
---#

/******************************************************************************/
--- Adding new indexes to messages table.
/******************************************************************************/

---# Adding index id_member_msg...
ALTER TABLE {$db_prefix}messages
ADD INDEX id_member_msg (id_member, approved, id_msg);
---#

---# Adding index current_topic...
ALTER TABLE {$db_prefix}messages
ADD INDEX current_topic (id_topic, id_msg, id_member, approved);
---#

---# Adding index related_ip...
ALTER TABLE {$db_prefix}messages
ADD INDEX related_ip (id_member, poster_ip, id_msg);
---#

/******************************************************************************/
--- Adding new indexes to attachments table.
/******************************************************************************/

---# Adding index on attachment_type...
ALTER TABLE {$db_prefix}attachments
ADD INDEX attachment_type (attachment_type);
---#

/******************************************************************************/
--- Dropping unnecessary indexes...
/******************************************************************************/

---# Removing index on hits...
ALTER TABLE {$db_prefix}log_activity
DROP INDEX hits;
---#

/******************************************************************************/
--- Adding extra columns to reported post comments
/******************************************************************************/

---# Adding email address and member ip columns...
ALTER TABLE {$db_prefix}log_reported_comments
ADD COLUMN member_ip varchar(255) NOT NULL default '' AFTER membername,
ADD COLUMN email_address varchar(255) NOT NULL default '' AFTER membername;
---#

/******************************************************************************/
--- Adjusting group types.
/******************************************************************************/

---# Fixing the group types.
---{
// Get the admin group type.
$request = upgrade_query("
	SELECT group_type
	FROM {$db_prefix}membergroups
	WHERE id_group = 1
	LIMIT 1");
list ($admin_group_type) = smf_mysql_fetch_row($request);
smf_mysql_free_result($request);

// Not protected means we haven't updated yet!
if ($admin_group_type != 1)
{
	// Increase by one.
	upgrade_query("
		UPDATE {$db_prefix}membergroups
		SET group_type = group_type + 1
		WHERE group_type > 0");
}
---}
---#

---# Changing the group type for Administrator group.
UPDATE {$db_prefix}membergroups
SET group_type = 1
WHERE id_group = 1;
---#
