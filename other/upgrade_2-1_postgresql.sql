/* ATTENTION: You don't need to run or use this file! The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Creating login history sequence.
CREATE SEQUENCE {$db_prefix}member_logins_seq;
---#

---# Creating login history table.
CREATE TABLE {$db_prefix}member_logins (
	id_login int NOT NULL default nextval('{$db_prefix}member_logins_seq'),
	id_member int NOT NULL,
	time int NOT NULL,
	ip varchar(255) NOT NULL default '',
	ip2 varchar(255) NOT NULL default '',
	PRIMARY KEY (id_login)
);
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

---# Converting collapsed categories...
---{
// We cannot do this twice
if (@$modSettings['smfVersion'] < '2.1')
{
	$request = $smcFunc['db_query']('', '
		SELECT id_member, id_cat
		FROM {db_prefix}collapsed_categories');

	$inserts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$inserts[] = array($row['id_member'], 1, 'collapse_category_' . $row['id_cat'], $row['id_cat']);
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
		$smcFunc['db_insert']('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			$inserts,
			array('id_theme', 'id_member', 'variable')
		);
}
---}
---#

---# Dropping "collapsed_categories"
DROP TABLE IF EXISTS {$db_prefix}collapsed_categories;
---#

---# Adding new "topic_move_any" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('topic_move_any', '1');
---#

---# Adding new "browser_cache" setting
---{
	$smcFunc['db_insert']('replace',
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		array('browser_cache', '?beta21'),
		array('variable')
	);
---}
---#

---# Adding new "enable_ajax_alerts" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('enable_ajax_alerts', '1');
---#

---# Adding new "minimize_files" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('minimize_files', '1');
---#

---# Collapse object
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('additional_options_collapsable', '1');
---#

---# Adding new "defaultMaxListItems" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('defaultMaxListItems', '15');
---#

---# Adding new "loginHistoryDays" setting
---{
	if (!isset($modSettings['loginHistoryDays']))
		$smcFunc['db_insert']('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('loginHistoryDays', '30'),
			array()
		);
---}
---#

---# Enable some settings we ripped from Theme settings
---{
	$ripped_settings = array('show_modify', 'show_user_images', 'show_blurb', 'show_profile_buttons', 'subject_toggle', 'hide_post_group');

	$request = $smcFunc['db_query']('', '
		SELECT variable, value
		FROM {db_prefix}themes
		WHERE variable IN({array_string:ripped_settings})
			AND id_member = 0
			AND id_theme = 1',
	array(
		'ripped_settings' => $ripped_settings,
	));

	$inserts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$inserts[] = array($row['variable'], $row['value']);

	$smcFunc['db_free_result']($request);
	$smcFunc['db_insert']('replace',
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		$inserts,
		array('id_theme', 'id_member', 'variable')
	);
---}
---#

/******************************************************************************/
--- Updating legacy attachments...
/******************************************************************************/

---# Converting legacy attachments.
---{

// Need to know a few things first.
$custom_av_dir = !empty($modSettings['custom_avatar_dir']) ? $modSettings['custom_avatar_dir'] : $GLOBALS['boarddir'] .'/custom_avatar';

// This little fellow has to cooperate...
if (!is_writable($custom_av_dir))
{
	// Try 755 and 775 first since 777 doesn't always work and could be a risk...
	$chmod_values = array(0755, 0775, 0777);

	foreach($chmod_values as $val)
	{
		// If it's writable, break out of the loop
		if (is_writable($custom_av_dir))
			break;
		else
			@chmod($custom_av_dir, $val);
	}
}

// If we already are using a custom dir, delete the predefined one.
if ($custom_av_dir != $GLOBALS['boarddir'] .'/custom_avatar')
{
	// Borrow custom_avatars index.php file.
	if (!file_exists($custom_av_dir . '/index.php'))
		@rename($GLOBALS['boarddir'] .'/custom_avatar/index.php', $custom_av_dir .'/index.php');
	else
		@unlink($GLOBALS['boarddir'] . '/custom_avatar/index.php');

	// Borrow blank.png as well
	if (!file_exists($custom_av_dir . '/blank.png'))
		@rename($GLOBALS['boarddir'] . '/custom_avatar/blank.png', $custom_av_dir . '/blank.png');
	else
		@unlink($GLOBALS['boarddir'] . '/custom_avatar/blank.png');

	// Attempt to delete the directory.
	@rmdir($GLOBALS['boarddir'] .'/custom_avatar');
}

$request = upgrade_query("
	SELECT MAX(id_attach)
	FROM {$db_prefix}attachments");
list ($step_progress['total']) = $smcFunc['db_fetch_row']($request);
$smcFunc['db_free_result']($request);

$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
$step_progress['name'] = 'Converting legacy attachments';
$step_progress['current'] = $_GET['a'];

// We may be using multiple attachment directories.
if (!empty($modSettings['currentAttachmentUploadDir']) && !is_array($modSettings['attachmentUploadDir']) && empty($modSettings['json_done']))
	$modSettings['attachmentUploadDir'] = @unserialize($modSettings['attachmentUploadDir']);

// No need to do this if we already did it previously...
if (empty($modSettings['json_done']))
  $is_done = false;
else
  $is_done = true;

while (!$is_done)
{
	nextSubStep($substep);

	$request = upgrade_query("
		SELECT id_attach, id_member, id_folder, filename, file_hash, mime_type
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

		$fileHash = '';

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

			// Iterate through the possible attachment names until we find the one that exists
			$oldFile = $currentFolder . '/' . $row['id_attach']. '_' . strtr($row['filename'], '.', '_') . md5($row['filename']);
			if (!file_exists($oldFile))
			{
				$oldFile = $currentFolder . '/' . $row['filename'];
				if (!file_exists($oldFile)) $oldFile = false;
			}

			// Build the new file.
			$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $fileHash .'.dat';
		}

		// Just rename the file.
		else
		{
			$oldFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'];
			$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'] .'.dat';

			// Make sure it exists...
			if (!file_exists($oldFile))
				$oldFile = false;
		}

		if (!$oldFile)
		{
			// Existing attachment could not be found. Just skip it...
			continue;
		}

		// Check if the av is an attachment
		if ($row['id_member'] != 0)
		{
			if (rename($oldFile, $custom_av_dir . '/' . $row['filename']))
				upgrade_query("
					UPDATE {$db_prefix}attachments
					SET file_hash = '', attachment_type = 1
					WHERE id_attach = $row[id_attach]");
		}
		// Just a regular attachment.
		else
		{
			rename($oldFile, $newFile);
		}

		// Only update this if it was successful and the file was using the old system.
		if (empty($row['file_hash']) && !empty($fileHash) && file_exists($newFile) && !file_exists($oldFile))
			upgrade_query("
				UPDATE {$db_prefix}attachments
				SET file_hash = '$fileHash'
				WHERE id_attach = $row[id_attach]");

		// While we're here, do we need to update the mime_type?
		if (empty($row['mime_type']) && file_exists($newFile))
		{
			$size = @getimagesize($newFile);
			if (!empty($size['mime']))
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}attachments
					SET mime_type = {string:mime_type}
					WHERE id_attach = {int:id_attach}',
					array(
						'id_attach' => $row['id_attach'],
						'mime_type' => substr($size['mime'], 0, 20),
					)
				);
		}
	}
	$smcFunc['db_free_result']($request);

	$_GET['a'] += 100;
	$step_progress['current'] = $_GET['a'];
}

unset($_GET['a']);
---}
---#

---# Fixing invalid sizes on attachments
---{
$attachs = array();
// If id_member = 0, then it's not an avatar
// If attachment_type = 0, then it's also not a thumbnail
// Theory says there shouldn't be *that* many of these
$request = $smcFunc['db_query']('', '
	SELECT id_attach, mime_type, width, height
	FROM {db_prefix}attachments
	WHERE id_member = 0
		AND attachment_type = 0');
while ($row = $smcFunc['db_fetch_assoc']($request))
{
	if (($row['width'] > 0 || $row['height'] > 0) && strpos($row['mime_type'], 'image') !== 0)
		$attachs[] = $row['id_attach'];
}
$smcFunc['db_free_result']($request);

if (!empty($attachs))
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}attachments
		SET width = 0,
			height = 0
		WHERE id_attach IN ({array_int:attachs})',
		array(
			'attachs' => $attachs,
		)
	);
---}
---#

---# Fixing attachment directory setting...
---{
if (!is_array($modSettings['attachmentUploadDir']) && is_dir($modSettings['attachmentUploadDir']))
{
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}settings
		SET value = {string:attach_dir}
		WHERE variable = {string:uploadDir}',
		array(
			'attach_dir' => json_encode(array(1 => $modSettings['attachmentUploadDir'])),
			'uploadDir' => 'attachmentUploadDir'
		)
	);
	$smcFunc['db_insert']('replace',
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		array('currentAttachmentUploadDir', '1'),
		array('variable')
	);
}
elseif (empty($modSettings['json_done']))
{
	// Serialized maybe?
	$array = is_array($modSettings['attachmentUploadDir']) ? $modSettings['attachmentUploadDir'] : @unserialize($modSettings['attachmentUploadDir']);
	if ($array !== false)
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}settings
			SET value = {string:attach_dir}
			WHERE variable = {string:uploadDir}',
			array(
				'attach_dir' => json_encode($array),
				'uploadDir' => 'attachmentUploadDir'
			)
		);

		// Assume currentAttachmentUploadDir is already set
	}
}
---}
---#

/******************************************************************************/
--- Adding support for logging who fulfils a group request.
/******************************************************************************/

---# Adding new columns to log_group_requests
ALTER TABLE {$db_prefix}log_group_requests
ADD COLUMN status smallint NOT NULL default '0',
ADD COLUMN id_member_acted int NOT NULL default '0',
ADD COLUMN member_name_acted varchar(255) NOT NULL default '',
ADD COLUMN time_acted int NOT NULL default '0',
ADD COLUMN act_reason text NOT NULL;
---#

---# Adjusting the indexes for log_group_requests
DROP INDEX {$db_prefix}log_group_requests_id_member;
CREATE INDEX {$db_prefix}log_group_requests_id_member ON {$db_prefix}log_group_requests (id_member, id_group);
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
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session type varchar(64);

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session type char(64);

	ALTER TABLE {$db_prefix}sessions
	ALTER COLUMN session_id type char(64);");

upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session SET DEFAULT '';

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session SET default '                                                                ';");
upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session SET NOT NULL;

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session SET NOT NULL;

	ALTER TABLE {$db_prefix}sessions
	ALTER COLUMN session_id SET NOT NULL;");
---}
---#

/******************************************************************************/
--- Adding support for MOVED topics enhancements
/******************************************************************************/
---# Adding new columns to topics table
---{
upgrade_query("
	ALTER TABLE {$db_prefix}topics
	ADD COLUMN redirect_expires int NOT NULL DEFAULT '0'");
upgrade_query("
	ALTER TABLE {$db_prefix}topics
	ADD COLUMN id_redirect_topic int NOT NULL DEFAULT '0'");
---}
---#

/******************************************************************************/
--- Adding new scheduled tasks
/******************************************************************************/
---# Adding a new column "callable" to scheduled_tasks table
---{
upgrade_query("
	ALTER TABLE {$db_prefix}scheduled_tasks
	ADD COLUMN callable varchar(60) NOT NULL default ''");
---}
---#

---# Adding new scheduled tasks
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(0, 120, 1, 'd', 0, 'remove_temp_attachments', '');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(0, 180, 1, 'd', 0, 'remove_topic_redirect', '');
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(0, 240, 1, 'd', 0, 'remove_old_drafts', '');
---#

---# Adding a new task-related setting...
---{
	if (!isset($modSettings['allow_expire_redirect']))
	{
		$get_info = $smcFunc['db_query']('', '
			SELECT disabled
			FROM {db_prefix}scheduled_tasks
			WHERE task = {string:remove_redirect}',
			array(
				'remove_redirect' => 'remove_topic_redirect'
			)
		);

		list($task_disabled) = $smcFunc['db_fetch_assoc']($get_info);
		$smcFunc['db_free_result']($get_info);

		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('allow_expire_redirect', !$task_disabled),
			array('variable')
		);
	}
---}
---#

/******************************************************************************/
---- Adding background tasks support
/******************************************************************************/
---# Adding the sequence
CREATE SEQUENCE {$db_prefix}background_tasks_seq;
---#

---# Adding the table
CREATE TABLE {$db_prefix}background_tasks (
	id_task int default nextval('{$db_prefix}background_tasks_seq'),
	task_file varchar(255) NOT NULL default '',
	task_class varchar(255) NOT NULL default '',
	task_data text NOT NULL,
	claimed_time int NOT NULL default '0',
	PRIMARY KEY (id_task)
);
---#

/******************************************************************************/
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}boards
	ADD COLUMN deny_member_groups varchar(255) NOT NULL DEFAULT ''");
---}
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
---{
// Sadly, PostgreSQL whines if we add a NOT NULL column without a default value to an existing table...
upgrade_query("
	ALTER TABLE {$db_prefix}categories
	ADD COLUMN description text");

upgrade_query("
	UPDATE {$db_prefix}categories
	SET description = ''");

upgrade_query("
	ALTER TABLE {$db_prefix}categories
	ALTER COLUMN description SET NOT NULL");
---}
---#

/******************************************************************************/
--- Adding support for alerts
/******************************************************************************/
---# Adding the count to the members table...
ALTER TABLE {$db_prefix}members
ADD COLUMN alerts int NOT NULL default '0';
---#

---# Adding the new table for alerts.
CREATE SEQUENCE {$db_prefix}user_alerts_seq;

CREATE TABLE {$db_prefix}user_alerts (
	id_alert int default nextval('{$db_prefix}user_alerts_seq'),
	alert_time int NOT NULL default '0',
	id_member int NOT NULL default '0',
	id_member_started int NOT NULL default '0',
	member_name varchar(255) NOT NULL default '',
	content_type varchar(255) NOT NULL default '',
	content_id int NOT NULL default '0',
	content_action varchar(255) NOT NULL default '',
	is_read int NOT NULL default '0',
	extra text NOT NULL,
	PRIMARY KEY (id_alert)
);

CREATE INDEX {$db_prefix}user_alerts_id_member ON {$db_prefix}user_alerts (id_member);
CREATE INDEX {$db_prefix}user_alerts_alert_time ON {$db_prefix}user_alerts (alert_time);
---#

---# Adding alert preferences.
CREATE TABLE {$db_prefix}user_alerts_prefs (
	id_member int NOT NULL default '0',
	alert_pref varchar(32) NOT NULL default '',
	alert_value smallint NOT NULL default '0',
	PRIMARY KEY (id_member, alert_pref)
);

INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_group_request', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_register', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_like', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_report', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_report_reply', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'unapproved_reply', 3);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'topic_notify', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'board_notify', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_mention', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_quote', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'pm_new', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'pm_reply', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'groupr_approved', 3);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'groupr_rejected', 3);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'birthday', 2);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'announcements', 2);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_report_reply', 3);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_report', 3);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'unapproved_post', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'buddy_request', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'warn_any', 1);
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'request_group', 1);
---#

---# Upgrading post notification settings
---{
	// Skip errors here so we don't croak if the columns don't exist...
	$existing_notify = $smcFunc['db_query']('', '
		SELECT id_member, notify_regularity, notify_send_body, notify_types
		FROM {db_prefix}members',
		array(
			'db_error_skip' => true,
		)
	);
	if (!empty($existing_notify))
	{
		while ($row = $smcFunc['db_fetch_assoc']($existing_notify))
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}user_alerts_prefs',
				array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'),
				array(
					array($row['id_member'], 'msg_receive_body', !empty($row['notify_send_body']) ? 1 : 0),
					array($row['id_member'], 'msg_notify_pref', $row['notify_regularity']),
					array($row['id_member'], 'msg_notify_type', $row['notify_types']),
				),
				array('id_member', 'alert_pref')
			);
		}
		$smcFunc['db_free_result']($existing_notify);
	}
---}
---#

---# Dropping old notification fields from the members table
ALTER TABLE {$db_prefix}members
	DROP notify_send_body,
	DROP notify_types,
	DROP notify_regularity,
	DROP notify_announcements;
---#

/******************************************************************************/
--- Adding support for topic unwatch
/******************************************************************************/
---# Adding new columns to log_topics...
ALTER TABLE {$db_prefix}log_topics
ADD COLUMN unwatched int NOT NULL DEFAULT '0';

UPDATE {$db_prefix}log_topics
SET unwatched = 0;
---#

---# Fixing column name change...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_topics
	RENAME disregarded TO unwatched");
---}
---#

/******************************************************************************/
--- Name changes
/******************************************************************************/
---# Altering the membergroup stars to icons
---{
upgrade_query("
	ALTER TABLE {$db_prefix}membergroups
	RENAME stars TO icons");
---}
---#

---# Renaming default theme...
UPDATE {$db_prefix}themes
SET value = 'SMF Default Theme - Curve2'
WHERE value LIKE 'SMF Default Theme%';
---#

---# Fader time update
UPDATE {$db_prefix}themes
SET value = '3000'
WHERE variable = 'newsfader_time';
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
--- Membergroup icons changes
/******************************************************************************/
---# Check the current saved names for icons and change them to the new name.
---{
$request = $smcFunc['db_query']('', '
	SELECT icons
	FROM {db_prefix}membergroups
	WHERE icons != {string:blank}',
	array(
		'blank' => '',
	)
);
$toMove = array();
$toChange = array();
while ($row = $smcFunc['db_fetch_assoc']($request))
{
	if (strpos($row['icons'], 'star.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('star.gif', 'icon.png', $row['icons']),
		);

	elseif (strpos($row['icons'], 'starmod.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('starmod.gif', 'iconmod.png', $row['icons']),
		);

	elseif (strpos($row['icons'], 'stargmod.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('stargmod.gif', 'icongmod.png', $row['icons']),
		);

	elseif (strpos($row['icons'], 'staradmin.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('staradmin.gif', 'iconadmin.png', $row['icons']),
		);

	else
		$toMove[] = $row['icons'];
}
$smcFunc['db_free_result']($request);

foreach ($toChange as $change)
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}membergroups
		SET icons = {string:new}
		WHERE icons = {string:old}',
		array(
			'new' => $change['new'],
			'old' => $change['old'],
		)
	);

// Attempt to move any custom uploaded icons.
foreach ($toMove as $move)
{
	// Get the actual image.
	$image = explode('#', $move);
	$image = $image[1];

	// PHP won't suppress errors when running things from shell, so make sure it exists first...
	if (file_exists($modSettings['theme_dir'] . '/images/' . $image))
		@rename($modSettings['theme_dir'] . '/images/' . $image, $modSettings['theme_dir'] . '/images/membericons/'. $image);
}
---}
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
		list($id_theme) = $smcFunc['db_fetch_row']($theme_request, 0);
		$smcFunc['db_free_result']($theme_request);

		$known_themes = explode(', ', $modSettings['knownThemes']);

		// Remove this value...
		$known_themes = array_diff($known_themes, array($id_theme));

		// Change back to a string...
		$known_themes = implode(', ', $known_themes);

		// Update the database
		upgrade_query("
			UPDATE {$db_prefix}settings
			SET value = '$known_themes'
			WHERE variable = 'knownThemes'");

		// Delete any info about this theme
		upgrade_query("
			DELETE FROM {$db_prefix}themes
			WHERE id_theme = $id_theme");
	}
}
---}
---#

/******************************************************************************/
--- Messenger fields
/******************************************************************************/
---# Adding new field_order column...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN field_order smallint NOT NULL default '0';
---#

---# Adding new show_mlist column...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN show_mlist smallint NOT NULL default '0';
---#

---# Insert fields
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_aolins', 'AOL Instant Messenger', 'This is your AOL Instant Messenger nickname.', 'text', 50, '', 1, 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="aim" href="aim:goim?screenname={INPUT}&message=Hello!+Are+you+there?" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/aim.png" alt="AIM - {INPUT}"></a>', 1);
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 2, 'regex~[1-9][0-9]{4,9}~i', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="{DEFAULT_IMAGES_URL}/icq.png" alt="ICQ - {INPUT}"></a>', 1);
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_skype', 'Skype', 'Your Skype name', 'text', 32, '', 3, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ', 1);
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_yahoo', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 4, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="//edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="{IMAGES_URL}/yahoo.png" alt="Yahoo! Messenger - {INPUT}"></a>', 1);
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_loca', 'Location', 'Geographic location.', 'text', 50, '', 5, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0);
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_gender', 'Gender', 'Your gender.', 'radio', 255, 'Disabled,Male,Female', 6, 'nohtml', 1, 1, 0, 'forumprofile', 0, 1, 0, 0, 'Disabled', '<span class=" generic_icons gender_{INPUT}" title="{INPUT}"></span>', 1);
---#

---# Add an order value to each existing cust profile field.
---{
	$ocf = $smcFunc['db_query']('', '
		SELECT id_field
		FROM {db_prefix}custom_fields
		WHERE field_order = 0');

		// We start counting from 6 because we already have the first 6 fields.
		$fields_count = 6;

		while ($row = $smcFunc['db_fetch_assoc']($ocf))
		{
			++$fields_count;

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}custom_fields
				SET field_order = {int:field_count}
				WHERE id_field = {int:id_field}',
				array(
					'field_count' => $fields_count,
					'id_field' => $row['id_field'],
				)
			);
		}
		$smcFunc['db_free_result']($ocf);
---}
---#

---# Converting member values...
---{
// We cannot do this twice
// See which columns we have
$results = $smcFunc['db_list_columns']('{db_prefix}members');
$possible_columns = array('aim', 'icq', 'msn', 'yim', 'location', 'gender');

// Find values that are in both arrays
$select_columns = array_intersect($possible_columns, $results);

if (!empty($select_columns))
{
	$request = $smcFunc['db_query']('', '
		SELECT id_member, '. implode(',', $select_columns) .'
		FROM {db_prefix}members');

	$inserts = array();
	$genderTypes = array(1 => 'Male', 2 => 'Female');
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!empty($row['aim']))
			$inserts[] = array($row['id_member'], -1, 'cust_aolins', $row['aim']);

		if (!empty($row['icq']))
			$inserts[] = array($row['id_member'], -1, 'cust_icq', $row['icq']);

		if (!empty($row['msn']))
			$inserts[] = array($row['id_member'], -1, 'cust_skyp', $row['msn']);

		if (!empty($row['yim']))
			$inserts[] = array($row['id_member'], -1, 'cust_yim', $row['yim']);

		if (!empty($row['location']))
			$inserts[] = array($row['id_member'], -1, 'cust_loca', $row['location']);

		if (!empty($row['gender']) && isset($genderTypes[intval($row['gender'])]))
			$inserts[] = array($row['id_member'], -1, 'cust_gender', $genderTypes[intval($row['gender'])]);
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
		$smcFunc['db_insert']('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			$inserts,
			array('id_theme', 'id_member', 'variable')
		);
}
---}
---#
---# Dropping old fields
ALTER TABLE {$db_prefix}members
	DROP icq,
	DROP aim,
	DROP yim,
	DROP msn,
	DROP location,
	DROP gender;
---#

---# Create the displayFields setting
---{
	if (empty($modSettings['displayFields']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT col_name, field_name, field_type, field_order, bbc, enclose, placement, show_mlist
			FROM {db_prefix}custom_fields',
			array()
		);

		$fields = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$fields[] = array(
				'col_name' => strtr($row['col_name'], array('|' => '', ';' => '')),
				'title' => strtr($row['field_name'], array('|' => '', ';' => '')),
				'type' => $row['field_type'],
				'order' => $row['field_order'],
				'bbc' => $row['bbc'] ? '1' : '0',
				'placement' => !empty($row['placement']) ? $row['placement'] : '0',
				'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
				'mlist' => $row['show_mlist'],
			);
		}

		$smcFunc['db_free_result']($request);

		$smcFunc['db_insert']('',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('displayFields', json_encode($fields)),
			array('id_theme', 'id_member', 'variable')
		);
	}
---}
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating drafts table.
CREATE SEQUENCE {$db_prefix}user_drafts_seq;

CREATE TABLE {$db_prefix}user_drafts (
	id_draft int NOT NULL default nextval('{$db_prefix}user_drafts_seq'),
	id_topic int NOT NULL default '0',
	id_board smallint NOT NULL default '0',
	id_reply int NOT NULL default '0',
	type smallint NOT NULL default '0',
	poster_time int NOT NULL default '0',
	id_member int NOT NULL default '0',
	subject varchar(255) NOT NULL default '',
	smileys_enabled smallint NOT NULL default '1',
	body text NOT NULL,
	icon varchar(16) NOT NULL default 'xx',
	locked smallint NOT NULL default '0',
	is_sticky smallint NOT NULL default '0',
	to_list varchar(255) NOT NULL default '',
	PRIMARY KEY (id_draft)
);
CREATE UNIQUE INDEX {$db_prefix}user_drafts_id_member ON {$db_prefix}user_drafts (id_member, id_draft, type);
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
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		foreach ($inserts AS $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}board_permissions
					(id_group, id_board, permission, add_deny)
				VALUES
					" . $insert);
		}
	}

	// Next we find people who can send PMs, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], 'pm_draft', $row[add_deny])";
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		foreach ($inserts AS $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}permissions
					(id_group, permission, add_deny)
				VALUES
					" . $insert);
		}
	}
}
---}
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_autosave_enabled', '1');
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_show_saved_enabled', '1');
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_keep_days', '7');
INSERT INTO {$db_prefix}themes (id_theme, variable, value) VALUES ('1', 'drafts_show_saved_enabled', '1');
---#

/******************************************************************************/
--- Adding support for likes
/******************************************************************************/
---# Creating likes table.
CREATE TABLE {$db_prefix}user_likes (
	id_member int NOT NULL default '0',
	content_type char(6) default '',
	content_id int NOT NULL default '0',
	like_time int NOT NULL default '0',
	PRIMARY KEY (content_id, content_type, id_member)
);

CREATE INDEX {$db_prefix}user_likes_content ON {$db_prefix}user_likes (content_id, content_type);
CREATE INDEX {$db_prefix}user_likes_liker ON {$db_prefix}user_likes (id_member);
---#

---# Adding count to the messages table.
ALTER TABLE {$db_prefix}messages
ADD COLUMN likes smallint NOT NULL default '0';
---#

/******************************************************************************/
--- Adding support for mentions
/******************************************************************************/
---# Creating mentions table
CREATE TABLE  {$db_prefix}mentions (
	content_id int NOT NULL default '0',
	content_type varchar(10) default '',
	id_mentioned int NOT NULL default 0,
	id_member int NOT NULL default 0,
	time int NOT NULL default 0,
	PRIMARY KEY (content_id, content_type, id_mentioned)
);

CREATE INDEX {$db_prefix}mentions_content ON {$db_prefix}mentions (content_id, content_type);
CREATE INDEX {$db_prefix}mentions_mentionee ON {$db_prefix}mentions (id_member);
---#

/******************************************************************************/
--- Adding support for group-based board moderation
/******************************************************************************/
---# Creating moderator_groups table
CREATE TABLE {$db_prefix}moderator_groups (
	id_board smallint NOT NULL default '0',
	id_group smallint NOT NULL default '0',
	PRIMARY KEY (id_board, id_group)
);
---#

/******************************************************************************/
--- Cleaning up integration hooks
/******************************************************************************/
---#
DELETE FROM {$db_prefix}settings
WHERE variable LIKE 'integrate_%';
---#

/******************************************************************************/
--- Cleaning up old settings
/******************************************************************************/
---# Fixing a deprecated option.
UPDATE {$db_prefix}settings
SET value = 'option_css_resize'
WHERE variable = 'avatar_action_too_large'
	AND (value = 'option_html_resize' OR value = 'option_js_resize');
---#

---# Cleaning up the old Core Features page.
---{
	// First get the original value
	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {literal:admin_features}');
	if ($smcFunc['db_num_rows']($request) > 0 && $row = $smcFunc['db_fetch_assoc']($request))
	{
		// Some of these *should* already be set but you never know.
		$new_settings = array();
		$admin_features = explode(',', $row['value']);

		// cd = calendar, should also have set cal_enabled already
		// cp = custom profile fields, which already has several fields that cover tracking
		// k = karma, should also have set karmaMode already
		// ps = paid subs, should also have set paid_enabled already
		// rg = reports generation, which is now permanently on
		// sp = spider tracking, should also have set spider_mode already
		// w = warning system, which will be covered with warning_settings

		// The rest we have to deal with manually.
		// Moderation log - modlog_enabled itself should be set but we have others now
		if (in_array('ml', $admin_features))
		{
			$new_settings[] = array('adminlog_enabled', '1');
			$new_settings[] = array('userlog_enabled', '1');
		}

		// Post moderation
		if (in_array('pm', $admin_features))
		{
			$new_settings[] = array('postmod_active', '1');
		}

		// And now actually apply it.
		if (!empty($new_settings))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				$new_settings,
				array('variable')
			);
		}
	}
	$smcFunc['db_free_result']($request);
---}
---#

---# Cleaning up old settings.
DELETE FROM {$db_prefix}settings
WHERE variable IN ('enableStickyTopics', 'guest_hideContacts', 'notify_new_registration', 'attachmentEncryptFilenames', 'hotTopicPosts', 'hotTopicVeryPosts', 'fixLongWords', 'admin_features', 'topbottomEnable', 'simpleSearch', 'enableVBStyleLogin', 'admin_bbc', 'enable_unwatch');
---#

---# Cleaning up old theme settings.
DELETE FROM {$db_prefix}themes
WHERE variable IN ('show_board_desc', 'no_new_reply_warning', 'display_quick_reply', 'show_mark_read', 'show_member_bar', 'linktree_link', 'show_bbc', 'additional_options_collapsable', 'subject_toggle', 'show_modify', 'show_profile_buttons', 'show_user_images', 'show_blurb', 'show_gender', 'hide_post_group', 'drafts_autosave_enabled', 'forum_width');
---#

---# Adding new "httponlyCookies" setting
---{
	if (!isset($modSettings['httponlyCookies']))
		$smcFunc['db_insert']('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('httponlyCookies', '1'),
			array()
		);
---}
---#

---# Calculate appropriate hash cost
---{
	$smcFunc['db_insert']('replace',
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		array('bcrypt_hash_cost', hash_benchmark()),
		array('variable')
	);
---}

/******************************************************************************/
--- Updating files that fetched from simplemachines.org
/******************************************************************************/
---# We no longer call on several files.
DELETE FROM {$db_prefix}admin_info_files
WHERE filename IN ('latest-packages.js', 'latest-smileys.js', 'latest-support.js', 'latest-themes.js')
	AND path = '/smf/';
---#

---# But we do need new files.
---{
// Don't insert the info if it's already there...
$file_check = $smcFunc['db_query']('', '
	SELECT id_file
	FROM {db_prefix}admin_info_files
	WHERE filename = {string:latest-versions}',
	array(
		'latest-versions' => 'latest-versions.txt',
	)
);

if ($smcFunc['db_num_rows']($file_check) == 0)
{
	$smcFunc['db_insert']('',
		'{db_prefix}admin_info_files',
		array('filename' => 'string', 'path' => 'string', 'parameters' => 'string', 'data' => 'string', 'filetype' => 'string'),
		array('latest-versions.txt', '/smf/', 'version=%3$s', '', 'text/plain'),
		array('id_file')
	);
}

$smcFunc['db_free_result']($file_check);
---}
---#

/******************************************************************************/
--- Upgrading "verification questions" feature
/******************************************************************************/
---# Creating qanda table
CREATE SEQUENCE {$db_prefix}qanda_seq;

CREATE TABLE {$db_prefix}qanda (
	id_question smallint NOT NULL default nextval('{$db_prefix}qanda_seq'),
	lngfile varchar(255) NOT NULL default '',
	question varchar(255) NOT NULL default '',
	answers text NOT NULL,
	PRIMARY KEY (id_question),
	KEY lngfile (lngfile)
);
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
--- Fixing log_online table
/******************************************************************************/
---# Changing ip to bigint
ALTER TABLE {$db_prefix}log_online ALTER ip TYPE bigint;
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

---# Removing the send-topic permission
DELETE FROM {$db_prefix}board_permissions
WHERE permission = 'send_topic';
---#

---# Removing the draft "autosave" permissions
DELETE FROM {$db_prefix}permissions
WHERE permission = 'post_autosave_draft' OR permission = 'pm_autosave_draft';

DELETE FROM {$db_prefix}board_permissions
WHERE permission = 'post_autosave_draft';
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
		foreach ($inserts as $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}permissions
					(id_group, permission, add_deny)
				VALUES
					" . $insert);
		}
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
		$inserts[] = "($row[id_group], 'profile_website_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_signature_own', $row[add_deny])";
	}

	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		foreach ($inserts as $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}permissions
					(id_group, permission, add_deny)
				VALUES
					" . $insert
			);
		}
	}
---}
---#

/******************************************************************************/
--- Upgrading PM labels...
/******************************************************************************/
---# Creating pm_labels sequence...
CREATE SEQUENCE {$db_prefix}pm_labels_seq;
---#

---# Adding pm_labels table...
CREATE TABLE {$db_prefix}pm_labels (
	id_label int NOT NULL default nextval('{$db_prefix}pm_labels_seq'),
	id_member int NOT NULL default '0',
	name varchar(30) NOT NULL default '',
	PRIMARY KEY (id_label)
);
---#

---# Adding pm_labeled_messages table...
CREATE TABLE {$db_prefix}pm_labeled_messages (
	id_label int NOT NULL default '0',
	id_pm int NOT NULL default '0',
	PRIMARY KEY (id_label, id_pm)
);
---#

---# Adding "in_inbox" column to pm_recipients
ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN in_inbox smallint NOT NULL default '1';
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
			}
		}

		$smcFunc['db_free_result']($get_labels);

		foreach ($label_info AS $id_member => $labels)
		{
			foreach ($labels as $label => $index)
			{
				$inserts[] = array($id_member, $label);
			}
		}

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
			$old_index = $label_info[$label_row['id_member']][$label_row['name']];
			$label_info_2[$label_row['id_member']][$old_index] = $label_row['id_label'];
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
ADD COLUMN modified_reason varchar(255) NOT NULL default '';
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
--- Adding gravatar settings
/******************************************************************************/
---# Adding default gravatar settings
---{
	if (empty($modSettings['gravatarEnabled']))
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string-255', 'value' => 'string'),
			array(
				array('gravatarEnabled', '1'),
				array('gravatarOverride', '0'),
				array('gravatarAllowExtraEmail', '1'),
				array('gravatarMaxRating', 'PG'),
			),
			array('variable')
		);
	}
---}
---#

/******************************************************************************/
--- Adding timezone support
/******************************************************************************/
---# Adding the "timezone" column to the members table
ALTER TABLE {$db_prefix}members ADD timezone VARCHAR(80) NOT NULL DEFAULT 'UTC';
---#

/******************************************************************************/
--- Adding mail queue settings
/******************************************************************************/
---# Adding default settings for the mail queue
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

/******************************************************************************/
--- Cleaning up old email settings
/******************************************************************************/
---# Removing the "send_email_to_members" permission
---{
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}permissions
		WHERE permission = {literal:send_email_to_members}',
		array()
	);
---}
---#

---# Dropping the "hide_email" column from the members table
ALTER TABLE {$db_prefix}members
DROP hide_email;
---#

---# Dropping the "email_address" column from log_reported_comments
ALTER TABLE {$db_prefix}log_reported_comments
DROP email_address;
---#

/******************************************************************************/
--- Deleting the "Auto Optimize" task
/******************************************************************************/
---# Removing the task and associated data
DELETE FROM {$db_prefix}scheduled_tasks
WHERE id_task = '2';

DELETE FROM {$db_prefix}log_scheduled_tasks
WHERE id_task = '2';

DELETE FROM {$db_prefix}settings
WHERE variable = 'autoOptMaxOnline';
---#

/******************************************************************************/
--- Removing OpenID-related things...
/******************************************************************************/
---# Removing the openid_uri column in the members table
ALTER TABLE {$db_prefix}members
DROP openid_uri;
---#

---# Dropping the openid_assoc table
DROP TABLE IF EXISTS {$db_prefix}openid_assoc;
---#

---# Removing related settings
DELETE FROM {$db_prefix}settings
WHERE variable='enableOpenID' OR variable='dh_keys';
---#

/******************************************************************************/
--- Fixing the url column in the log_spider_hits and log_online tables
/******************************************************************************/
---# Changing url column size in log_spider_hits from 255 to 1024
ALTER TABLE {$db_prefix}log_spider_hits
ALTER url TYPE varchar(1024);
---#

---# Changing url column in log_online from text to varchar(1024)
ALTER TABLE {$db_prefix}log_online
ALTER url TYPE varchar(1024);
---#

/******************************************************************************/
--- Adding support for 2FA
/******************************************************************************/
---# Adding the secret column to members table
ALTER TABLE {$db_prefix}members
ADD COLUMN tfa_secret VARCHAR(24) NOT NULL DEFAULT '';
---#

---# Adding the backup column to members tab
ALTER TABLE {$db_prefix}members
ADD COLUMN tfa_backup VARCHAR(64) NOT NULL DEFAULT '';
---#

---# Force 2FA per membergroup?
ALTER TABLE {$db_prefix}membergroups
ADD COLUMN tfa_required smallint NOT NULL default '0';
---#

---# Add tfa_mode setting
---{
	if (!isset($modSettings['tfa_mode']))
		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('tfa_mode', '1'),
			array('variable')
		);
---}
---#

/******************************************************************************/
--- Converting old bbcodes
/******************************************************************************/
---# Replacing [br] with &lt;br&gt;
UPDATE {$db_prefix}messages SET body = REPLACE(body, '[br]', '<br>') WHERE body LIKE '%[br]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(body, '[br]', '<br>') WHERE body LIKE '%[br]%';
---#

---# Replacing [acronym] with [abbr]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[acronym=', '[abbr='), '[/acronym]', '[/abbr]') WHERE body LIKE '%[acronym=%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[acronym=', '[abbr='), '[/acronym]', '[/abbr]') WHERE body LIKE '%[acronym=%';
---#

---# Replacing [tt] with [font=monospace]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[tt]', '[font=monospace]'), '[/tt]', '[/font]') WHERE body LIKE '%[tt]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[tt]', '[font=monospace]'), '[/tt]', '[/font]') WHERE body LIKE '%[tt]%';
---#

---# Replacing [bdo=ltr] with [ltr]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[bdo=ltr]', '[ltr]'), '[/bdo]', '[/ltr]') WHERE body LIKE '%[bdo=ltr]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[bdo=ltr]', '[ltr]'), '[/bdo]', '[/ltr]') WHERE body LIKE '%[bdo=ltr]%';
---#

---# Replacing [bdo=rtl] with [rtl]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[bdo=rtl]', '[rtl]'), '[/bdo]', '[/rtl]') WHERE body LIKE '%[bdo=rtl]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[bdo=rtl]', '[rtl]'), '[/bdo]', '[/rtl]') WHERE body LIKE '%[bdo=rtl]%';
---#

---# Replacing [black] with [color=black]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[black]', '[color=black]'), '[/black]', '[/color]') WHERE body LIKE '%[black]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[black]', '[color=black]'), '[/black]', '[/color]') WHERE body LIKE '%[black]%';
---#

---# Replacing [white] with [color=white]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[white]', '[color=white]'), '[/white]', '[/color]') WHERE body LIKE '%[white]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[white]', '[color=white]'), '[/white]', '[/color]') WHERE body LIKE '%[white]%';
---#

---# Replacing [red] with [color=red]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[red]', '[color=red]'), '[/red]', '[/color]') WHERE body LIKE '%[red]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[red]', '[color=red]'), '[/red]', '[/color]') WHERE body LIKE '%[red]%';
---#

---# Replacing [green] with [color=green]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[green]', '[color=green]'), '[/green]', '[/color]') WHERE body LIKE '%[green]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[green]', '[color=green]'), '[/green]', '[/color]') WHERE body LIKE '%[green]%';
---#

---# Replacing [blue] with [color=blue]
UPDATE {$db_prefix}messages SET body = REPLACE(REPLACE(body, '[blue]', '[color=blue]'), '[/blue]', '[/color]') WHERE body LIKE '%[blue]%';
UPDATE {$db_prefix}personal_messages SET body = REPLACE(REPLACE(body, '[blue]', '[color=blue]'), '[/blue]', '[/color]') WHERE body LIKE '%[blue]%';
---#

/******************************************************************************/
--- optimization of members
/******************************************************************************/

---# ADD INDEX to members
CREATE INDEX {$db_prefix}members_member_name_low ON {$db_prefix}members (LOWER(member_name));
CREATE INDEX {$db_prefix}members_real_name_low ON {$db_prefix}members (LOWER(real_name));
---#

/******************************************************************************/
--- UNLOGGED Table PG 9.1+
/******************************************************************************/
---# update table
---{
$result = $smcFunc['db_query']('', '
	SHOW server_version_num'
);
if ($result !== false)
{
	while ($row = $smcFunc['db_fetch_assoc']($result))
		$pg_version = $row['server_version_num'];
	$smcFunc['db_free_result']($result);
}

if(isset($pg_version))
{
	$tables = array('log_online','log_floodcontrol','sessions');
	foreach($tables as $tab)
	{
		if($pg_version >= 90500)
			upgrade_query("ALTER TABLE {$db_prefix}".$tab." SET UNLOGGED;");
		ELSE
			upgrade_query("
			alter table {$db_prefix}".$tab." rename to old_{$db_prefix}".$tab.";

			do
			$$
			declare r record;
			begin
				for r in select * from pg_constraint where conrelid='old_{$db_prefix}".$tab."'::regclass loop
					execute format('alter table old_{$db_prefix}".$tab." rename constraint %I to %I', r.conname, 'old_' || r.conname);
				end loop;
				for r in select * from pg_indexes where tablename='old_{$db_prefix}".$tab."' and indexname !~ '^old_' loop
					execute format('alter index %I rename to %I', r.indexname, 'old_' || r.indexname);
				end loop;
			end;
			$$;

			create unlogged table {$db_prefix}".$tab." (like old_{$db_prefix}".$tab." including all);

			insert into {$db_prefix}".$tab." select * from old_{$db_prefix}".$tab.";

			drop table old_{$db_prefix}".$tab.";"
			);
	}

}
---}
---#

/******************************************************************************/
--- remove redundant index
/******************************************************************************/

---# duplicate to messages_current_topic
DROP INDEX IF EXISTS {$db_prefix}messages_id_topic;
DROP INDEX IF EXISTS {$db_prefix}messages_topic;
---#

---# duplicate to topics_last_message_sticky and topics_board_news
DROP INDEX IF EXISTS {$db_prefix}topics_id_board;
---#

/******************************************************************************/
--- update ban ip with ipv6 support
/******************************************************************************/
---# add columns
ALTER TABLE {$db_prefix}ban_items ADD COLUMN ip_low inet;
ALTER TABLE {$db_prefix}ban_items ADD COLUMN ip_high inet;
---#

---# convert data
UPDATE {$db_prefix}ban_items
SET ip_low = (ip_low1||'.'||ip_low2||'.'||ip_low3||'.'||ip_low4)::inet,
	ip_high = (ip_high1||'.'||ip_high2||'.'||ip_high3||'.'||ip_high4)::inet
WHERE ip_low1 > 0;
---#

---# index
CREATE INDEX {$db_prefix}ban_items_id_ban_ip ON {$db_prefix}ban_items (ip_low,ip_high);
---#

/******************************************************************************/
--- helper function for ip convert
/******************************************************************************/
---# the function migrate_inet
CREATE OR REPLACE FUNCTION migrate_inet(val IN anyelement) RETURNS inet
AS
$$
BEGIN
   RETURN (trim(val))::inet;
EXCEPTION
   WHEN OTHERS THEN RETURN NULL;
END;
$$ LANGUAGE plpgsql;
---#

/******************************************************************************/
--- update log_action ip with ipv6 support
/******************************************************************************/
---# convert column
ALTER TABLE {$db_prefix}log_actions
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

/******************************************************************************/
--- update log_banned ip with ipv6 support
/******************************************************************************/
---# convert old column
ALTER TABLE {$db_prefix}log_banned
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

/******************************************************************************/
--- update log_errors members ip with ipv6 support
/******************************************************************************/
---# convert old columns
ALTER TABLE {$db_prefix}log_errors
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
ALTER TABLE {$db_prefix}members
	ALTER member_ip DROP not null,
	ALTER member_ip DROP default,
	ALTER member_ip TYPE inet USING migrate_inet(member_ip);
ALTER TABLE {$db_prefix}members
	ALTER member_ip2 DROP not null,
	ALTER member_ip2 DROP default,
	ALTER member_ip2 TYPE inet USING migrate_inet(member_ip2);
---#

/******************************************************************************/
--- update messages poster_ip with ipv6 support
/******************************************************************************/
---# convert old column
ALTER TABLE {$db_prefix}messages
	ALTER poster_ip DROP not null,
	ALTER poster_ip DROP default,
	ALTER poster_ip TYPE inet USING migrate_inet(poster_ip);
---#

/******************************************************************************/
--- update log_floodcontrol ip with ipv6 support
/******************************************************************************/
---# drop pk
TRUNCATE TABLE {$db_prefix}log_floodcontrol;
ALTER TABLE {$db_prefix}log_floodcontrol DROP CONSTRAINT {$db_prefix}log_floodcontrol_pkey;
---#

---# convert old column
ALTER TABLE {$db_prefix}log_floodcontrol
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

---# add pk
ALTER TABLE {$db_prefix}log_floodcontrol
  ADD CONSTRAINT {$db_prefix}log_floodcontrol_pkey PRIMARY KEY(ip, log_type);
---#

/******************************************************************************/
--- update log_online ip with ipv6 support
/******************************************************************************/
---# convert old columns
ALTER TABLE {$db_prefix}log_online
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

/******************************************************************************/
--- update log_reported_comments member_ip with ipv6 support
/******************************************************************************/
---# convert old columns
ALTER TABLE {$db_prefix}log_reported_comments
	ALTER member_ip DROP not null,
	ALTER member_ip DROP default,
	ALTER member_ip TYPE inet USING migrate_inet(member_ip);
---#

/******************************************************************************/
--- update member_logins ip with ipv6 support
/******************************************************************************/
---# convert old columns
ALTER TABLE {$db_prefix}member_logins
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
ALTER TABLE {$db_prefix}member_logins
	ALTER ip2 DROP not null,
	ALTER ip2 DROP default,
	ALTER ip2 TYPE inet USING migrate_inet(ip2);
---#

/******************************************************************************/
--- Renaming the "profile_other" permission...
/******************************************************************************/
---# Changing the "profile_other" permission to "profile_website"
UPDATE {$db_prefix}permissions SET permission = 'profile_website_own' WHERE permission = 'profile_other_own';
UPDATE {$db_prefix}permissions SET permission = 'profile_website_any' WHERE permission = 'profile_other_any';
---#