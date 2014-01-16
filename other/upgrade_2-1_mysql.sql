/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Adding login history...
CREATE TABLE IF NOT EXISTS {$db_prefix}member_logins (
	id_login int(10) NOT NULL auto_increment,
	id_member mediumint(8) NOT NULL,
	time int(10) NOT NULL,
	ip varchar(255) NOT NULL default '',
	ip2 varchar(255) NOT NULL default '',
	PRIMARY KEY id_login(id_login),
	KEY id_member (id_member),
	KEY time (time)
) ENGINE=MyISAM{$db_collation};
---#

---# Copying the current package backup setting...
---{
if (!isset($modSettings['package_make_full_backups']) && isset($modSettings['package_make_backups']))
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('package_make_full_backups', '" . $modSettings['package_make_backups'] . "')");
---}
---#

---# Copying the current "allow users to disable word censor" setting...
---{
if (!isset($modSettings['allow_no_censored']))
{
	$request = upgrade_query("
		SELECT value
		FROM {$db_prefix}themes
		WHERE variable='allow_no_censored'
		AND id_theme = 1 OR id_theme = '$modSettings[theme_default]'
	");

	// Is it set for either "default" or the one they've set as default?
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['value'] == 1)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}settings
				VALUES ('allow_no_censored', 1)
			");

			// Don't do this twice...
			break;
		}
	}
}
---}
---#

---# Adding new "topic_move_any" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('topic_move_any', '1');
---#

/******************************************************************************/
--- Updating legacy attachments...
/******************************************************************************/

---# Converting legacy attachments.
---{
$request = upgrade_query("
	SELECT MAX(id_attach)
	FROM {$db_prefix}attachments");
list ($step_progress['total']) = $smcFunc['db_fetch_row']($request);
$smcFunc['db_free_result']($request);

$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
$step_progress['name'] = 'Converting legacy attachments';
$step_progress['current'] = $_GET['a'];

// We may be using multiple attachment directories.
if (!empty($modSettings['currentAttachmentUploadDir']) && !is_array($modSettings['attachmentUploadDir']))
	$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

$is_done = false;
while (!$is_done)
{
	nextSubStep($substep);
	$fileHash = '';

	$request = upgrade_query("
		SELECT id_attach, id_folder, filename, file_hash
		FROM {$db_prefix}attachments
		WHERE attachment_type != 1
		LIMIT $_GET[a], 100");

	// Finished?
	if ($smcFunc['db_num_rows']($request) == 0)
		$is_done = true;

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// The current folder.
		$currentFolder = !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['attachmentUploadDir'][$row['id_folder']] : $modSettings['attachmentUploadDir'];

		// Old School?
		if (empty($row['file_hash']))
		{
			// Remove international characters (windows-1252)
			// These lines should never be needed again. Still, behave.
			if (empty($db_character_set) || $db_character_set != 'utf8')
			{
				$row['filename'] = strtr($row['filename'],
					"\x8a\x8e\x9a\x9e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xe0\xe1\xe2\xe3\xe4\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xff",
					'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
				$row['filename'] = strtr($row['filename'], array("\xde" => 'TH', "\xfe" =>
					'th', "\xd0" => 'DH', "\xf0" => 'dh', "\xdf" => 'ss', "\x8c" => 'OE',
					"\x9c" => 'oe', "\xc6" => 'AE', "\xe6" => 'ae', "\xb5" => 'u'));
			}
			// Sorry, no spaces, dots, or anything else but letters allowed.
			$row['filename'] = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $row['filename']);

			// Create a nice hash.
			$fileHash = sha1(md5($row['filename'] . time()) . mt_rand());

			// The old file, we need to know if the filename was encrypted or not.
			if (file_exists($currentFolder . '/' . $row['id_attach']. '_' . strtr($row['filename'], '.', '_') . md5($row['filename'])))
				$oldFile = $currentFolder . '/' . $row['id_attach']. '_' . strtr($row['filename'], '.', '_') . md5($row['filename']);

			else if (file_exists($currentFolder . '/' . $row['filename']));
				$oldFile = $currentFolder . '/' . $row['filename'];

			// Build the new file.
			$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $fileHash .'.dat';
		}

		// Just rename the file.
		else
		{
			$oldFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'];
			$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'] .'.dat';
		}

		// And we try to move it.
		rename($oldFile, $newFile);

		// Only update this if it was successful and the file was using the old system.
		if (empty($row['file_hash']) && !empty($fileHash) && file_exists($newFile) && !file_exists($oldFile))
			upgrade_query("
				UPDATE {$db_prefix}attachments
				SET file_hash = '$fileHash'
				WHERE id_attach = $row[id_attach]");
	}
	$smcFunc['db_free_result']($request);

	$_GET['a'] += 100;
	$step_progress['current'] = $_GET['a'];
}

unset($_GET['a']);
---}
---#

/******************************************************************************/
--- Adding support for IPv6...
/******************************************************************************/

---# Adding new columns to ban items...
ALTER TABLE {$db_prefix}ban_items
ADD COLUMN ip_low5 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high5 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_low6 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high6 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_low7 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high7 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_low8 smallint(255) unsigned NOT NULL DEFAULT '0',
ADD COLUMN ip_high8 smallint(255) unsigned NOT NULL DEFAULT '0';
---#

---# Changing existing columns to ban items...
ALTER TABLE {$db_prefix}ban_items
CHANGE ip_low1 ip_low1 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high1 ip_high1 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_low2 ip_low2 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high2 ip_high2 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_low3 ip_low3 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high3 ip_high3 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_low4 ip_low4 smallint(255) unsigned NOT NULL DEFAULT '0',
CHANGE ip_high4 ip_high4 smallint(255) unsigned NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding support for logging who fulfils a group request.
/******************************************************************************/

---# Adding new columns to log_group_requests
ALTER TABLE {$db_prefix}log_group_requests
ADD COLUMN status tinyint(3) unsigned NOT NULL default '0',
ADD COLUMN id_member_acted mediumint(8) unsigned NOT NULL default '0',
ADD COLUMN member_name_acted varchar(255) NOT NULL default '',
ADD COLUMN time_acted int(10) unsigned NOT NULL default '0',
ADD COLUMN act_reason text NOT NULL;
---#

---# Adjusting the indexes for log_group_requests
ALTER TABLE {$db_prefix}log_group_requests
DROP INDEX `id_member`,
ADD INDEX `id_member` (`id_member`, `id_group`);
---#

/******************************************************************************/
--- Adding support for <credits> tag in package manager
/******************************************************************************/
---# Adding new columns to log_packages ..
ALTER TABLE {$db_prefix}log_packages
ADD COLUMN credits varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding more space for session ids
/******************************************************************************/
---# Altering the session_id columns...
ALTER TABLE {$db_prefix}log_online
CHANGE `session` `session` varchar(64) NOT NULL DEFAULT '';

ALTER TABLE {$db_prefix}log_errors
CHANGE `session` `session` char(64) NOT NULL default '                                                                ';

ALTER TABLE {$db_prefix}sessions
CHANGE `session_id` `session_id` char(64) NOT NULL;
---#

/******************************************************************************/
--- Adding support for MOVED topics enhancements
/******************************************************************************/
---# Adding new columns to topics ..
ALTER TABLE {$db_prefix}topics
ADD COLUMN redirect_expires int(10) unsigned NOT NULL default '0',
ADD COLUMN id_redirect_topic mediumint(8) unsigned NOT NULL default '0';
---#

/******************************************************************************/
--- Adding new scheduled tasks
/******************************************************************************/
---# Adding new scheduled tasks
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 120, 1, 'd', 0, 'remove_temp_attachments');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 180, 1, 'd', 0, 'remove_topic_redirect');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task)
VALUES
	(0, 240, 1, 'd', 0, 'remove_old_drafts');
---#

/******************************************************************************/
---- Adding background tasks support
/******************************************************************************/
---# Adding the new table
CREATE TABLE IF NOT EXISTS {$db_prefix}background_tasks (
  id_task int(10) unsigned NOT NULL auto_increment,
  task_file varchar(255) NOT NULL default '',
  task_class varchar(255) NOT NULL default '',
  task_data mediumtext NOT NULL,
  claimed_time int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (id_task)
) ENGINE=MyISAM;
---#

/******************************************************************************/
---- Replacing MSN with Skype
/******************************************************************************/
---# Modifying the "msn" column...
ALTER TABLE {$db_prefix}members
CHANGE msn skype varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}boards
ADD COLUMN deny_member_groups varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Updating board access rules
/******************************************************************************/
---# Updating board access rules
---{
$member_groups = array(
	'allowed' => array(),
	'denied' => array(),
);

$request = $smcFunc['db_query']('', '
	SELECT id_group, add_deny
	FROM {db_prefix}permissions
	WHERE permission = {string:permission}',
	array(
		'permission' => 'manage_boards',
	)
);
while ($row = $smcFunc['db_fetch_assoc']($request))
	$member_groups[$row['add_deny'] === '1' ? 'allowed' : 'denied'][] = $row['id_group'];
$smcFunc['db_free_result']($request);

$member_groups = array_diff($member_groups['allowed'], $member_groups['denied']);

if (!empty($member_groups))
{
	$count = count($member_groups);
	$changes = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_board, member_groups
		FROM {db_prefix}boards');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$current_groups = explode(',', $row['member_groups']);
		if (count(array_intersect($current_groups, $member_groups)) != $count)
		{
			$new_groups = array_unique(array_merge($current_groups, $member_groups));
			$changes[$row['id_board']] = implode(',', $new_groups);
		}
	}
	$smcFunc['db_free_result']($request);

	if (!empty($changes))
	{
		foreach ($changes as $id_board => $member_groups)
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET member_groups = {string:member_groups}
					WHERE id_board = {int:id_board}',
				array(
					'member_groups' => $member_groups,
					'id_board' => $id_board,
				)
			);
	}
}
---}
---#

/******************************************************************************/
--- Adding support for category descriptions
/******************************************************************************/
---# Adding new columns to categories...
ALTER TABLE {$db_prefix}categories
ADD COLUMN description text NOT NULL;
---#

/******************************************************************************/
--- Adding support for alerts
/******************************************************************************/
---# Adding the count to the members table...
ALTER TABLE {$db_prefix}members
ADD COLUMN alerts int(10) unsigned NOT NULL default '0';
---#

---# Adding the new table for alerts.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_alerts (
  id_alert int(10) unsigned NOT NULL auto_increment,
  alert_time int(10) unsigned NOT NULL default '0',
  id_member mediumint(10) unsigned NOT NULL default '0',
  id_member_started mediumint(10) unsigned NOT NULL default '0',
  member_name varchar(255) NOT NULL default '',
  content_type varchar(255) NOT NULL default '',
  content_id int(10) unsigned NOT NULL default '0',
  content_action varchar(255) NOT NULL default '',
  is_read tinyint(3) unsigned NOT NULL default '0',
  extra text NOT NULL,
  PRIMARY KEY (id_alert),
  KEY id_member (id_member),
  KEY alert_time (alert_time)
) ENGINE=MyISAM;
---#

---# Adding alert preferences.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_alerts_prefs (
  id_member mediumint(8) unsigned NOT NULL default '0',
  alert_pref varchar(32) NOT NULL default '',
  alert_value tinyint(3) NOT NULL default '0',
  PRIMARY KEY (id_member, alert_pref)
) ENGINE=MyISAM;

INSERT INTO {$db_prefix}user_alerts_prefs
	(id_member, alert_pref, alert_value)
VALUES (0, 'member_group_request', 1),
	(0, 'member_register', 1),
	(0, 'msg_like', 1),
	(0, 'msg_report', 1),
	(0, 'msg_report_reply', 1);
---#

/******************************************************************************/
--- Adding support for topic unwatch
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}log_topics
ADD COLUMN unwatched tinyint(3) NOT NULL DEFAULT '0';

UPDATE {$db_prefix}log_topics
SET unwatched = 0;

INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('enable_unwatch', 0);
---#

---# Fixing column name change...
ALTER TABLE {$db_prefix}log_topics
CHANGE COLUMN disregarded unwatched tinyint(3) NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Fixing mail queue for long messages
/******************************************************************************/
---# Altering mil_queue table...
ALTER TABLE {$db_prefix}mail_queue
CHANGE body body mediumtext NOT NULL;
---#

/******************************************************************************/
--- Name changes
/******************************************************************************/
---# Altering the membergroup stars to icons
ALTER TABLE {$db_prefix}membergroups
CHANGE `stars` `icons` varchar(255) NOT NULL DEFAULT '';
---#

---# Renaming default theme...
UPDATE {$db_prefix}themes
SET value = 'SMF Default Theme - Curve2'
WHERE value LIKE 'SMF Default Theme%';
---#

---# Adding the enableThemes setting.
INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('enableThemes', '1');
---#

---# Setting "default" as the default...
UPDATE {$db_prefix}settings
SET value = '1'
WHERE variable = 'theme_guests';

UPDATE {$db_prefix}boards
SET id_theme = 0;

UPDATE {$db_prefix}members
SET id_theme = 0;
---#

/******************************************************************************/
--- Cleaning up after old themes...
/******************************************************************************/
---# Checking for "core" and removing it if necessary...
---{
// Do they have "core" installed?
if (file_exists($GLOBALS['boarddir'] . '/Themes/core'))
{
	$core_dir = $GLOBALS['boarddir'] . '/Themes/core';
	$theme_request = upgrade_query("
		SELECT id_theme
		FROM {$db_prefix}themes
		WHERE variable = 'theme_dir'
			AND value ='$core_dir'");

	// Don't do anything if this theme is already uninstalled
	if ($smcFunc['db_num_rows']($theme_request) == 1)
	{
		// Only one row, so no loop needed
		$row = $smcFunc['db_fetch_array']($theme_request);
		$id_theme = $row[0];
		$smcFunc['db_free_result']($theme_request);

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
	}
}
---}
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating draft table
CREATE TABLE IF NOT EXISTS {$db_prefix}user_drafts (
  id_draft int(10) unsigned NOT NULL auto_increment,
  id_topic mediumint(8) unsigned NOT NULL default '0',
  id_board smallint(5) unsigned NOT NULL default '0',
  id_reply int(10) unsigned NOT NULL default '0',
  type tinyint(4) NOT NULL default '0',
  poster_time int(10) unsigned NOT NULL default '0',
  id_member mediumint(8) unsigned NOT NULL default '0',
  subject varchar(255) NOT NULL default '',
  smileys_enabled tinyint(4) NOT NULL default '1',
  body mediumtext NOT NULL,
  icon varchar(16) NOT NULL default 'xx',
  locked tinyint(4) NOT NULL default '0',
  is_sticky tinyint(4) NOT NULL default '0',
  to_list varchar(255) NOT NULL default '',
  PRIMARY KEY id_draft(id_draft),
  UNIQUE id_member (id_member, id_draft, type)
) ENGINE=MyISAM{$db_collation};
---#

---# Adding draft permissions...
---{
// We cannot do this twice
if (@$modSettings['smfVersion'] < '2.1')
{
	// Anyone who can currently post unapproved topics we assume can create drafts as well ...
	$request = upgrade_query("
		SELECT id_group, id_board, add_deny, permission
		FROM {$db_prefix}board_permissions
		WHERE permission = 'post_unapproved_topics'");
	$inserts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], $row[id_board], 'post_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], $row[id_board], 'post_autosave_draft', $row[add_deny])";
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}board_permissions
				(id_group, id_board, permission, add_deny)
			VALUES
				" . implode(',', $inserts));

	// Next we find people who can send PMs, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], 'pm_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], 'pm_autosave_draft', $row[add_deny])";
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
}
---}
INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('drafts_autosave_enabled', '1'),
	('drafts_show_saved_enabled', '1'),
	('drafts_keep_days', '7');

INSERT INTO {$db_prefix}themes
	(id_theme, variable, value)
VALUES
	('1', 'drafts_autosave_enabled', '1'),
	('1', 'drafts_show_saved_enabled', '1');
---#

/******************************************************************************/
--- Adding support for likes
/******************************************************************************/
---# Creating likes table.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_likes (
  id_member mediumint(8) unsigned NOT NULL default '0',
  content_type char(6) default '',
  content_id int(10) unsigned NOT NULL default '0',
  like_time int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (content_id, content_type, id_member),
  INDEX content (content_id, content_type),
  INDEX liker (id_member)
) ENGINE=MyISAM;
---#

---# Adding count to the messages table.
ALTER TABLE {$db_prefix}messages
ADD COLUMN likes smallint(5) unsigned NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding support for group-based board moderation
/******************************************************************************/
---# Creating moderator_groups table
CREATE TABLE IF NOT EXISTS {$db_prefix}moderator_groups (
  id_board smallint(5) unsigned NOT NULL default '0',
  id_group smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY (id_board, id_group)
) ENGINE=MyISAM{$db_collation};
---#

/******************************************************************************/
--- Cleaning up integration hooks
/******************************************************************************/
---# Deleting integration hooks
DELETE FROM {$db_prefix}settings
WHERE variable LIKE 'integrate_%';
---#

/******************************************************************************/
--- Cleaning up old settings
/******************************************************************************/
---# Updating the default time format
---{
if (!empty($modSettings['time_format']))
{
	// First, use the shortened form of the month in the date.
	$time_format = str_replace('%B', '%b', $modSettings['time_format']);

	// Second, shorten the time to stop including seconds.
	$time_format = str_replace(':%S', '', $time_format);

	// Then, update the database.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}settings
		SET value = {string:new_format}
		WHERE variable = {literal:time_format}',
		array(
			'new_format' => $time_format,
		)
	);
}
---}
---#

---# Fixing a deprecated option.
UPDATE {$db_prefix}settings
SET value = 'option_css_resize'
WHERE variable = 'avatar_action_too_large'
	AND (value = 'option_html_resize' OR value = 'option_js_resize');
---#

---# Cleaning up old settings.
DELETE FROM {$db_prefix}settings
WHERE variable IN ('enableStickyTopics', 'guest_hideContacts', 'notify_new_registration', 'attachmentEncryptFilenames', 'hotTopicPosts', 'hotTopicVeryPosts', 'fixLongWords');
---#

---# Cleaning up old theme settings.
DELETE FROM {$db_prefix}themes
WHERE variable IN ('show_board_desc', 'no_new_reply_warning', 'display_quick_reply');
---#

/******************************************************************************/
--- Removing old Simple Machines files we do not need to fetch any more
/******************************************************************************/
---# We no longer call on the latest packages list.
DELETE FROM {$db_prefix}admin_info_files
WHERE filename IN ('latest-packages.js', 'latest-support.js', 'latest-themes.js')
	AND path = '/smf/';
---#

/******************************************************************************/
--- Upgrading "verification questions" feature
/******************************************************************************/
---# Creating qanda table
CREATE TABLE IF NOT EXISTS {$db_prefix}qanda (
  id_question smallint(5) unsigned NOT NULL auto_increment,
  lngfile varchar(255) NOT NULL default '',
  question varchar(255) NOT NULL default '',
  answers text NOT NULL,
  PRIMARY KEY (id_question),
  KEY lngfile (lngfile)
) ENGINE=MyISAM{$db_collation};
---#

---# Moving questions and answers to the new table
---{
$questions = array();
$get_questions = upgrade_query("
	SELECT body AS question, recipient_name AS answer
	FROM {$db_prefix}log_comments
	WHERE comment_type = 'ver_test'");

	while ($row = $smcFunc['db_fetch_assoc']($get_questions))
		$questions[] = array($language, $row['question'], serialize(array($row['answer'])));

	$smcFunc['db_free_result']($get_questions);

	if (!empty($questions))
	{
		$smcFunc['db_insert']('',
			'{db_prefix}qanda',
			array('lngfile' => 'string', 'question' => 'string', 'answers' => 'string'),
			$questions,
			array('id_question')
		);

		// Delete the questions from log_comments now
		upgrade_query("
			DELETE FROM {$db_prefix}log_comments
			WHERE comment_type = 'ver_test'
		");
	}
---}
---#

/******************************************************************************/
--- Marking packages as uninstalled...
/******************************************************************************/
---# Updating log_packages
UPDATE {$db_prefix}log_packages
SET install_state = 0;
---#

/******************************************************************************/
--- Updating profile permissions...
/******************************************************************************/
---# Removing the old "view your own profile" permission
DELETE FROM {$db_prefix}permissions
WHERE permission = 'profile_view_own';
---#

---# Updating the old "view any profile" permission
UPDATE {$db_prefix}permissions
SET permission = 'profile_view'
WHERE permission = 'profile_view_any';
---#

---# Removing the old notification permissions
DELETE FROM {$db_prefix}board_permissions
WHERE permission = 'mark_notify' OR permission = 'mark_any_notify';
---#

---# Adding "profile_password_own"
---{
$inserts = array();

$request = upgrade_query("
	SELECT id_group, add_deny
	FROM {$db_prefix}permissions
	WHERE permission = 'profile_identity_own'");

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], 'profile_password_own', $row[add_deny])";
	}

	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts)
		);
	}
---}
---#

---# Adding other profile permissions
---{
$inserts = array();

$request = upgrade_query("
	SELECT id_group, add_deny
	FROM {$db_prefix}permissions
	WHERE permission = 'profile_extra_own'");

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], 'profile_blurb_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_displayed_name_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_forum_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_other_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_signature_own', $row[add_deny])";
	}

	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts)
			);
	}
---}
---#

/******************************************************************************/
--- Upgrading PM labels...
/******************************************************************************/
---# Adding pm_labels table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labels (
  id_label int(10) unsigned NOT NULL auto_increment,
  id_member mediumint(8) unsigned NOT NULL default '0',
  name varchar(30) NOT NULL default '',
  PRIMARY KEY (id_label)
) ENGINE=MyISAM;
---#

---# Adding pm_labeled_messages table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labeled_messages (
  id_label int(10) unsigned NOT NULL default '0',
  id_pm int(10) unsigned NOT NULL default '0',
  PRIMARY KEY (id_label, id_pm)
) ENGINE=MyISAM;
---#

---# Adding "in_inbox" column to pm_recipients
ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN in_inbox tinyint(3) NOT NULL default '1';
---#

---# Moving label info to new tables and updating rules...
---{
	// First see if we still have a message_labels column
	$results = $smcFunc['db_list_columns']('{db_prefix}members');
	if (in_array('message_labels', $results))
	{
		// They've still got it, so pull the label info
		$get_labels = $smcFunc['db_query']('', '
			SELECT id_member, message_labels
			FROM {db_prefix}members
			WHERE message_labels != {string:blank}',
			array(
				'blank' => '',
			)
		);

		$inserts = array();
		$label_info = array();
		while ($row = $smcFunc['db_fetch_assoc']($get_labels))
		{
			// Stick this in an array
			$labels = explode(',', $row['message_labels']);

			// Build some inserts
			foreach ($labels AS $index => $label)
			{
				// Keep track of the index of this label - we'll need that in a bit...
				$label_info[$row['id_member']][$label] = $index;
				$inserts[] = array($row['id_member'], $label);
			}
		}

		$smcFunc['db_free_result']($get_labels);

		if (!empty($inserts))
		{
			$smcFunc['db_insert']('', '{db_prefix}pm_labels', array('id_member' => 'int', 'name' => 'string-30'), $inserts, array());

			// Clear this out for our next query below
			$inserts = array();
		}

		// This is the easy part - update the inbox stuff
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET in_inbox = {int:in_inbox}
			WHERE FIND_IN_SET({int:minusone}, labels)',
			array(
				'in_inbox' => 1,
				'minusone' => -1,
			)
		);

		// Now we go pull the new IDs for each label
		$get_new_label_ids = $smcFunc['db_query']('', '
			SELECT *
			FROM {db_prefix}pm_labels',
			array(
			)
		);

		$label_info_2 = array();
		while ($label_row = $smcFunc['db_fetch_assoc']($get_new_label_ids))
		{
			// Map the old index values to the new ID values...
			$old_index = $label_info[$row['id_member']][$row['label_name']];
			$label_info_2[$row['id_member']][$old_index] = $row['id_label'];
		}

		$smcFunc['db_free_result']($get_new_label_ids);

		// Pull label info from pm_recipients
		// Ignore any that are only in the inbox
		$get_pm_labels = $smcFunc['db_query']('', '
			SELECT id_pm, id_member, labels
			FROM {db_prefix}pm_recipients
			WHERE deleted = {int:not_deleted}
				AND labels != {string:minus_one}',
			array(
				'not_deleted' => 0,
				'minus_one' => -1,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($get_pm_labels))
		{
			$labels = explode(',', $row['labels']);

			foreach ($labels as $a_label)
			{
				if ($a_label == '-1')
					continue;

				$new_label_info = $label_info_2[$row['id_member']][$a_label];
				$inserts[] = array($row['id_pm'], $new_label_info);
			}
		}

		$smcFunc['db_free_result']($get_pm_labels);

		// Insert the new data
		if (!empty($inserts))
		{
			$smcFunc['db_insert']('', '{db_prefix}pm_labeled_messages', array('id_pm' => 'int', 'id_label' => 'int'), $inserts, array());
		}

		// Final step of this ridiculously massive process
		$get_pm_rules = $smcFunc['db_query']('', '
			SELECT id_member, id_rule, actions
			FROM {db_prefix}pm_rules',
			array(
			)
		);

		// Go through the rules, unserialize the actions, then figure out if there's anything we can use
		while ($row = $smcFunc['db_fetch_assoc']($get_pm_rules))
		{
			// Turn this into an array...
			$actions = unserialize($row['actions']);

			// Loop through the actions and see if we're applying a label anywhere
			foreach ($actions as $index => $action)
			{
				if ($action['t'] == 'lab')
				{
					// Update the value of this label...
					$actions[$index]['v'] = $label_info_2[$row['id_member']][$action['v']];
				}
			}

			// Put this back into a string
			$actions = serialize($actions);

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}pm_rules
				SET actions = {string:actions}
				WHERE id_rule = {int:id_rule}',
				array(
					'actions' => $actions,
					'id_rule' => $row['id_rule'],
				)
			);
		}

		$smcFunc['db_free_result']($get_pm_rules);

		// Lastly, we drop the old columns
		$smcFunc['db_remove_column']('{db_prefix}members', 'message_labels');
		$smcFunc['db_remove_column']('{db_prefix}pm_recipients', 'labels');
	}
---}
---#

/******************************************************************************/
--- Adding support for edit reasons
/******************************************************************************/
---# Adding "modified_reason" column to messages
ALTER TABLE {$db_prefix}messages
ADD COLUMN modified_reason varchar(255) NOT NULL;
---#

/******************************************************************************/
--- Cleaning up guest permissions
/******************************************************************************/
---# Removing permissions guests can no longer have...
---{
	$illegal_board_permissions = array(
		'announce_topic',
		'delete_any',
		'lock_any',
		'make_sticky',
		'merge_any',
		'modify_any',
		'modify_replies',
		'move_any',
		'poll_add_any',
		'poll_edit_any',
		'poll_lock_any',
		'poll_remove_any',
		'remove_any',
		'report_any',
		'split_any'
	);

	$illegal_permissions = array('calendar_edit_any', 'moderate_board', 'moderate_forum', 'send_email_to_members');

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group = {int:guests}
		AND permission IN ({array_string:illegal_board_perms})',
		array(
			'guests' => -1,
			'illegal_board_perms' => $illegal_board_permissions,
		)
	);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group = {int:guests}
		AND permission IN ({array_string:illegal_perms})',
		array(
			'guests' => -1,
			'illegal_perms' => $illegal_permissions,
		)
	);
---}
---#

/******************************************************************************/
--- Adding mail queue settings
/******************************************************************************/
---#
---{
	if (empty($modSettings['mail_limit']))
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string-255', 'value' => 'string'),
			array(
				array('mail_limit', '5'),
				array('mail_quantity', '5'),
			),
			array('variable')
		);
	}
---}
---#