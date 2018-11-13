/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Fixing dates...
/******************************************************************************/

---# Updating old values
UPDATE {$db_prefix}calendar
SET start_date = DATE(CONCAT(1004, '-', MONTH(start_date), '-', DAY(start_date)))
WHERE YEAR(start_date) < 1004;

UPDATE {$db_prefix}calendar
SET end_date = DATE(CONCAT(1004, '-', MONTH(end_date), '-', DAY(end_date)))
WHERE YEAR(end_date) < 1004;

UPDATE {$db_prefix}calendar_holidays
SET event_date = DATE(CONCAT(1004, '-', MONTH(event_date), '-', DAY(event_date)))
WHERE YEAR(event_date) < 1004;

UPDATE {$db_prefix}log_spider_stats
SET stat_date = DATE(CONCAT(1004, '-', MONTH(stat_date), '-', DAY(stat_date)))
WHERE YEAR(stat_date) < 1004;

UPDATE {$db_prefix}members
SET birthdate = DATE(CONCAT(IF(YEAR(birthdate) < 1004, 1004, YEAR(birthdate)), '-', IF(MONTH(birthdate) < 1, 1, MONTH(birthdate)), '-', IF(DAY(birthdate) < 1, 1, DAY(birthdate))))
WHERE YEAR(birthdate) < 1004;
---#

---# Changing default values
ALTER TABLE {$db_prefix}calendar CHANGE start_date start_date date NOT NULL DEFAULT '1004-01-01';
ALTER TABLE {$db_prefix}calendar CHANGE end_date end_date date NOT NULL DEFAULT '1004-01-01';
ALTER TABLE {$db_prefix}calendar_holidays CHANGE event_date event_date date NOT NULL DEFAULT '1004-01-01';
ALTER TABLE {$db_prefix}log_spider_stats CHANGE stat_date stat_date date NOT NULL DEFAULT '1004-01-01';
ALTER TABLE {$db_prefix}members CHANGE birthdate birthdate date NOT NULL DEFAULT '1004-01-01';
---#

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Adding login history...
CREATE TABLE IF NOT EXISTS {$db_prefix}member_logins (
	id_login INT(10) AUTO_INCREMENT,
	id_member MEDIUMINT NOT NULL DEFAULT '0',
	time INT(10) NOT NULL DEFAULT '0',
	ip VARBINARY(16),
	ip2 VARBINARY(16),
	PRIMARY KEY id_login(id_login),
	INDEX idx_id_member (id_member),
	INDEX idx_time (time)
) ENGINE=MyISAM;
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

---# Adding new "DEFAULTMaxListItems" setting
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

---# Disable Moderation Center Security if it doesn't exist
---{
	if (!isset($modSettings['securityDisable_moderate']))
		$smcFunc['db_insert']('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('securityDisable_moderate', '1'),
			array()
		);
---}
---#

/******************************************************************************/
--- Updating legacy attachments...
/******************************************************************************/

---# Adding more space to the mime_type column.
ALTER TABLE {$db_prefix}attachments
CHANGE `mime_type` `mime_type` VARCHAR(128) NOT NULL DEFAULT '';
---#

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
	SELECT COUNT(*)
	FROM {$db_prefix}attachments
	WHERE attachment_type != 1");
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
ADD COLUMN status TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN id_member_acted MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN member_name_acted VARCHAR(255) NOT NULL DEFAULT '',
ADD COLUMN time_acted INT(10) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN act_reason TEXT NOT NULL;
---#

---# Adjusting the indexes for log_group_requests
ALTER TABLE {$db_prefix}log_group_requests
DROP INDEX `id_member`,
ADD INDEX `idx_id_member` (`id_member`, `id_group`);
---#

/******************************************************************************/
--- Adding support for <credits> tag in package manager
/******************************************************************************/
---# Adding new columns to log_packages ..
ALTER TABLE {$db_prefix}log_packages
ADD COLUMN credits TEXT NOT NULL;
---#

/******************************************************************************/
--- Adding more space for session ids
/******************************************************************************/
---# Altering the session_id columns...
ALTER TABLE {$db_prefix}log_online
CHANGE `session` `session` VARCHAR(128) NOT NULL DEFAULT '';

ALTER TABLE {$db_prefix}log_errors
CHANGE `session` `session` VARCHAR(128) NOT NULL DEFAULT '                                                                ';

ALTER TABLE {$db_prefix}sessions
CHANGE `session_id` `session_id` VARCHAR(128) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for MOVED topics enhancements
/******************************************************************************/
---# Adding new columns to topics ..
ALTER TABLE {$db_prefix}topics
ADD COLUMN redirect_expires INT(10) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN id_redirect_topic MEDIUMINT UNSIGNED NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding new scheduled tasks
/******************************************************************************/
---# Adding a new column "callable" to scheduled_tasks table
ALTER TABLE {$db_prefix}scheduled_tasks
ADD COLUMN callable VARCHAR(60) NOT NULL DEFAULT '';
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

---# Remove old tasks added by modifications...
---{
	$vanilla_tasks = array(
		'birthdayemails',
		'daily_digest',
		'daily_maintenance',
		'fetchSMfiles',
		'paid_subscriptions',
		'remove_temp_attachments',
		'remove_topic_redirect',
		'remove_old_drafts',
		'weekly_digest',
		'weekly_maintenance');

	$smcFunc['db_query']('',
		'DELETE FROM {db_prefix}scheduled_tasks
			WHERE task NOT IN ({array_string:keep_tasks});',
		array(
			'keep_tasks' => $vanilla_tasks
		)
	);
---}
---#

/******************************************************************************/
---- Adding background tasks support
/******************************************************************************/
---# Adding the new table
CREATE TABLE IF NOT EXISTS {$db_prefix}background_tasks (
	id_task INT(10) UNSIGNED AUTO_INCREMENT,
	task_file VARCHAR(255) NOT NULL DEFAULT '',
	task_class VARCHAR(255) NOT NULL DEFAULT '',
	task_data mediumtext NOT NULL,
	claimed_time INT(10) UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (id_task)
) ENGINE=MyISAM;
---#

/******************************************************************************/
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}boards
ADD COLUMN deny_member_groups VARCHAR(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding setting for max depth of sub-boards to check for new posts, etc.
/******************************************************************************/
---# Adding the boardindex_max_depth setting.
INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('boardindex_max_depth', '1');
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
ADD COLUMN description TEXT NOT NULL;
---#

/******************************************************************************/
--- Adding support for alerts
/******************************************************************************/
---# Adding the count to the members table...
ALTER TABLE {$db_prefix}members
ADD COLUMN alerts INT(10) UNSIGNED NOT NULL DEFAULT '0';
---#

---# Adding the new table for alerts.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_alerts (
	id_alert INT(10) UNSIGNED AUTO_INCREMENT,
	alert_time INT(10) UNSIGNED NOT NULL DEFAULT '0',
	id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	id_member_started MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	member_name VARCHAR(255) NOT NULL DEFAULT '',
	content_type VARCHAR(255) NOT NULL DEFAULT '',
	content_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
	content_action VARCHAR(255) NOT NULL DEFAULT '',
	is_read INT(10) UNSIGNED NOT NULL DEFAULT '0',
	extra TEXT NOT NULL,
	PRIMARY KEY (id_alert),
	INDEX idx_id_member (id_member),
	INDEX idx_alert_time (alert_time)
) ENGINE=MyISAM;
---#

---# Adding alert preferences.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_alerts_prefs (
	id_member MEDIUMINT UNSIGNED DEFAULT '0',
	alert_pref VARCHAR(32) DEFAULT '',
	alert_value TINYINT(3) NOT NULL DEFAULT '0',
	PRIMARY KEY (id_member, alert_pref)
) ENGINE=MyISAM;

INSERT INTO {$db_prefix}user_alerts_prefs
	(id_member, alert_pref, alert_value)
VALUES (0, 'member_group_request', 1),
	(0, 'member_register', 1),
	(0, 'msg_like', 1),
	(0, 'msg_report', 1),
	(0, 'msg_report_reply', 1),
	(0, 'unapproved_reply', 3),
	(0, 'topic_notify', 1),
	(0, 'board_notify', 1),
	(0, 'msg_mention', 1),
	(0, 'msg_quote', 1),
	(0, 'pm_new', 1),
	(0, 'pm_reply', 1),
	(0, 'groupr_approved', 3),
	(0, 'groupr_rejected', 3),
	(0, 'member_report_reply', 3),
	(0, 'birthday', 2),
	(0, 'announcements', 0),
	(0, 'member_report', 3),
	(0, 'unapproved_attachment', 1),
	(0, 'unapproved_post', 1),
	(0, 'buddy_request', 1),
	(0, 'warn_any', 1),
	(0, 'request_group', 1);
---#

---# Upgrading post notification settings
---{
// First see if we still have a notify_regularity column
$results = $smcFunc['db_list_columns']('{db_prefix}members');
if (in_array('notify_regularity', $results))
{
	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Upgrading post notification settings';
	$step_progress['current'] = $_GET['a'];

	$limit = 100000;
	$is_done = false;

	$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}members');
	list($maxMembers) = $smcFunc['db_fetch_row']($request);

	while (!$is_done)
	{
		nextSubStep($substep);
		$inserts = array();

		// Skip errors here so we don't croak if the columns don't exist...
		$request = $smcFunc['db_query']('', '
			SELECT id_member, notify_regularity, notify_send_body, notify_types
			FROM {db_prefix}members
			LIMIT {int:start}, {int:limit}',
			array(
				'db_error_skip' => true,
				'start' => $_GET['a'],
				'limit' => $limit,
			)
		);
		if ($smcFunc['db_num_rows']($request) != 0)
		{
			while ($row = $smcFunc['db_fetch_assoc']($existing_notify))
			{
				$inserts[] = array($row['id_member'], 'msg_receive_body', !empty($row['notify_send_body']) ? 1 : 0);
				$inserts[] = array($row['id_member'], 'msg_notify_pref', $row['notify_regularity']);
				$inserts[] = array($row['id_member'], 'msg_notify_type', $row['notify_types']);
			}
			$smcFunc['db_free_result']($existing_notify);
		}

		$smcFunc['db_insert']('ignore',
			'{db_prefix}user_alerts_prefs',
			array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'),
			$inserts,
			array('id_member', 'alert_pref')
		);

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];

		if ($step_progress['current'] >= $maxMembers)
			$is_done = true;
	}
	unset($_GET['a']);
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
---# Adding new column to log_topics...
ALTER TABLE {$db_prefix}log_topics
ADD COLUMN unwatched TINYINT NOT NULL DEFAULT '0';
---#

---# Initializing new column in log_topics...
UPDATE {$db_prefix}log_topics
SET unwatched = 0;
---#

---# Fixing column name change...
ALTER TABLE {$db_prefix}log_topics
DROP COLUMN disregarded;
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
CHANGE `stars` `icons` VARCHAR(255) NOT NULL DEFAULT '';
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

---# Update the max year for the calendar
UPDATE {$db_prefix}settings
SET value = '2030'
WHERE variable = 'cal_maxyear';
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
---# Clean up settings for unused themes
---{
// Fetch list of theme directories
$request = $smcFunc['db_query']('', '
	SELECT id_theme, variable, value
	  FROM {db_prefix}themes
	WHERE variable = {string:theme_dir}
	  AND id_theme != {int:default_theme};',
	array(
		'default_theme' => 1,
		'theme_dir' => 'theme_dir',
	)
);
// Check which themes exist in the filesystem & save off their IDs
// Dont delete default theme(start with 1 in the array), & make sure to delete old core theme
$known_themes = array('1');
$core_dir = $GLOBALS['boarddir'] . '/Themes/core';
while ($row = $smcFunc['db_fetch_assoc']($request))	{
	if ($row['value'] != $core_dir && is_dir($row['value'])) {
		$known_themes[] = $row['id_theme'];
	}
}
// Cleanup unused theme settings
$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}themes
	WHERE id_theme NOT IN ({array_int:known_themes});',
	array(
		'known_themes' => $known_themes,
	)
);
// Set knownThemes
$known_themes = implode(',', $known_themes);
$smcFunc['db_query']('', '
	UPDATE {db_prefix}settings
	SET value = {string:known_themes}
	WHERE variable = {string:known_theme_str};',
	array(
		'known_theme_str' => 'knownThemes',
		'known_themes' => $known_themes,
	)
);
---}
---#

/******************************************************************************/
--- Messenger fields
/******************************************************************************/
---# Adding new field_order column...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN field_order SMALLINT NOT NULL DEFAULT '0';
---#

---# Adding new show_mlist column...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN show_mlist SMALLINT NOT NULL DEFAULT '0';
---#

---# Insert fields
INSERT INTO `{$db_prefix}custom_fields` (`col_name`, `field_name`, `field_desc`, `field_type`, `field_length`, `field_options`, `field_order`, `mask`, `show_reg`, `show_display`, `show_mlist`, `show_profile`, `private`, `active`, `bbc`, `can_search`, `default_value`, `enclose`, `placement`) VALUES
('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 1, 'regex~[1-9][0-9]{4,9}~i', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" rel="noopener" title="ICQ - {INPUT}"><img src="{DEFAULT_IMAGES_URL}/icq.png" alt="ICQ - {INPUT}"></a>', 1),
('cust_skype', 'Skype', 'Your Skype name', 'text', 32, '', 2, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ', 1),
('cust_yahoo', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 3, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="//edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" rel="noopener" title="Yahoo! Messenger - {INPUT}"><img src="{IMAGES_URL}/yahoo.png" alt="Yahoo! Messenger - {INPUT}"></a>', 1),
('cust_loca', 'Location', 'Geographic location.', 'text', 50, '', 4, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0),
('cust_gender', 'Gender', 'Your gender.', 'radio', 255, 'None,Male,Female', 5, 'nohtml', 1, 1, 0, 'forumprofile', 0, 1, 0, 0, 'None', '<span class=" generic_icons gender_{KEY}" title="{INPUT}"></span>', 1);
---#

---# Add an order value to each existing cust profile field.
---{
	$ocf = $smcFunc['db_query']('', '
		SELECT id_field
		FROM {db_prefix}custom_fields
		WHERE field_order = 0');

		// We start counting from 5 because we already have the first 5 fields.
		$fields_count = 5;

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
$possible_columns = array('icq', 'msn', 'yim', 'location', 'gender');

// Find values that are in both arrays
$select_columns = array_intersect($possible_columns, $results);

if (!empty($select_columns))
{
	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Converting member values';
	$step_progress['current'] = $_GET['a'];

	$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}members');
	list($maxMembers) = $smcFunc['db_fetch_row']($request);

	$limit = 10000;
	$is_done = false;

	while (!$is_done)
	{
		nextSubStep($substep);
		$inserts = array();

		$request = $smcFunc['db_query']('', '
			SELECT id_member, '. implode(',', $select_columns) .'
			FROM {db_prefix}members
			LIMIT {int:start}, {int:limit}',
			array(
				'start' => $_GET['a'],
				'limit' => $limit,
		));

		$genderTypes = array(1 => 'Male', 2 => 'Female');
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!empty($row['icq']))
				$inserts[] = array($row['id_member'], 1, 'cust_icq', $row['icq']);

			if (!empty($row['msn']))
				$inserts[] = array($row['id_member'], 1, 'cust_skype', $row['msn']);

			if (!empty($row['yim']))
				$inserts[] = array($row['id_member'], 1, 'cust_yahoo', $row['yim']);

			if (!empty($row['location']))
				$inserts[] = array($row['id_member'], 1, 'cust_loca', $row['location']);

			if (!empty($row['gender']) && isset($genderTypes[INTval($row['gender'])]))
				$inserts[] = array($row['id_member'], 1, 'cust_gender', $genderTypes[INTval($row['gender'])]);
		}
		$smcFunc['db_free_result']($request);

		if (!empty($inserts))
			$smcFunc['db_insert']('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
				$inserts,
				array('id_theme', 'id_member', 'variable')
			);

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];

		if ($step_progress['current'] >= $maxMembers)
			$is_done = true;
	}
}
unset($_GET['a']);
---}
---#

---# Dropping old fields
ALTER TABLE `{$db_prefix}members`
	DROP `icq`,
	DROP `aim`,
	DROP `yim`,
	DROP `msn`,
	DROP `location`,
	DROP `gender`;
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

		$smcFunc['db_insert']('replace',
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
---# Creating draft table
CREATE TABLE IF NOT EXISTS {$db_prefix}user_drafts (
	id_draft INT(10) UNSIGNED AUTO_INCREMENT,
	id_topic MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	id_board SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
	id_reply INT(10) UNSIGNED NOT NULL DEFAULT '0',
	type TINYINT(4) NOT NULL DEFAULT '0',
	poster_time INT(10) UNSIGNED NOT NULL DEFAULT '0',
	id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	subject VARCHAR(255) NOT NULL DEFAULT '',
	smileys_enabled TINYINT(4) NOT NULL DEFAULT '1',
	body mediumtext NOT NULL,
	icon VARCHAR(16) NOT NULL DEFAULT 'xx',
	locked TINYINT(4) NOT NULL DEFAULT '0',
	is_sticky TINYINT(4) NOT NULL DEFAULT '0',
	to_list VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY id_draft(id_draft),
	UNIQUE idx_id_member (id_member, id_draft, type)
) ENGINE=MyISAM;
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
	('1', 'drafts_show_saved_enabled', '1');
---#

/******************************************************************************/
--- Adding support for likes
/******************************************************************************/
---# Creating likes table.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_likes (
	id_member MEDIUMINT UNSIGNED DEFAULT '0',
	content_type CHAR(6) DEFAULT '',
	content_id INT(10) UNSIGNED DEFAULT '0',
	like_time INT(10) UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (content_id, content_type, id_member),
	INDEX idx_content (content_id, content_type),
	INDEX idx_liker (id_member)
) ENGINE=MyISAM;
---#

---# Adding count to the messages table. (May take a while)
ALTER TABLE {$db_prefix}messages
ADD COLUMN likes SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding support for mentions
/******************************************************************************/
---# Creating mentions table
CREATE TABLE IF NOT EXISTS {$db_prefix}mentions (
	content_id INT DEFAULT '0',
	content_type VARCHAR(10) DEFAULT '',
	id_mentioned INT DEFAULT 0,
	id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
	`time` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (content_id, content_type, id_mentioned),
	INDEX idx_content (content_id, content_type),
	INDEX idx_mentionee (id_member)
) ENGINE=MyISAM;
---#

/******************************************************************************/
--- Adding support for group-based board moderation
/******************************************************************************/
---# Creating moderator_groups table
CREATE TABLE IF NOT EXISTS {$db_prefix}moderator_groups (
	id_board SMALLINT(5) UNSIGNED DEFAULT '0',
	id_group SMALLINT(5) UNSIGNED DEFAULT '0',
	PRIMARY KEY (id_board, id_group)
) ENGINE=MyISAM;
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

---# Update the SM Stat collection.
---{
	// First get the original value
	$request = $smcFunc['db_query']('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {literal:allow_sm_stats}');
	if ($smcFunc['db_num_rows']($request) > 0 && $row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!empty($row['value']))
		{
			$smcFunc['db_insert']('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				array(
					array('sm_stats_key', $row['value']),
					array('enable_sm_stats', '1'),
				),
				array('variable')
			);

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}settings
				WHERE variable = {literal:allow_sm_stats}');
		}
	}
	$smcFunc['db_free_result']($request);
---}
---#

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
CREATE TABLE IF NOT EXISTS {$db_prefix}qanda (
	id_question SMALLINT(5) UNSIGNED AUTO_INCREMENT,
	lngfile VARCHAR(255) NOT NULL DEFAULT '',
	question VARCHAR(255) NOT NULL DEFAULT '',
	answers TEXT NOT NULL,
	PRIMARY KEY (id_question),
	INDEX idx_lngfile (lngfile)
) ENGINE=MyISAM;
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
		$inserts[] = "($row[id_group], 'profile_website_own', $row[add_deny])";
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
	id_label INT(10) UNSIGNED AUTO_INCREMENT,
	id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	name VARCHAR(30) NOT NULL DEFAULT '',
	PRIMARY KEY (id_label)
) ENGINE=MyISAM;
---#

---# Adding pm_labeled_messages table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labeled_messages (
	id_label INT(10) UNSIGNED NOT NULL DEFAULT '0',
	id_pm INT(10) UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (id_label, id_pm)
) ENGINE=MyISAM;
---#

---# Adding "in_inbox" column to pm_recipients
ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN in_inbox TINYINT(3) NOT NULL DEFAULT '1';
---#

---# Moving label info to new tables and updating rules (May be slow!!!)
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
			// Turn this INTo an array...
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
--- Adding support for edit reasons (May take a while)
/******************************************************************************/
---# Adding "modified_reason" column to messages (May take a while)
ALTER TABLE {$db_prefix}messages
ADD COLUMN modified_reason VARCHAR(255) NOT NULL DEFAULT '';
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
---# Adding DEFAULT settings for the mail queue
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
--- Adding gravatar settings
/******************************************************************************/
---# Adding DEFAULT gravatar settings
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
CHANGE `url` `url` VARCHAR(1024) NOT NULL DEFAULT '';
---#

---# Changing url column in log_online from TEXT to VARCHAR(1024)
ALTER TABLE {$db_prefix}log_online
CHANGE `url` `url` VARCHAR(2048) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for 2FA
/******************************************************************************/
---# Adding the secret column to members table
ALTER TABLE {$db_prefix}members
ADD tfa_secret VARCHAR(24) NOT NULL DEFAULT '';
---#

---# Adding the backup column to members table
ALTER TABLE {$db_prefix}members
ADD tfa_backup VARCHAR(64) NOT NULL DEFAULT '';
---#

---# Force 2FA per membergroup
ALTER TABLE {$db_prefix}membergroups
ADD COLUMN tfa_required TINYINT(3) NOT NULL DEFAULT '0';
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
--- Remove redundant indexes
/******************************************************************************/
---# Duplicates to messages_current_topic
DROP INDEX idx_id_topic on {$db_prefix}messages;
DROP INDEX idx_topic on {$db_prefix}messages;
---#

---# Duplicate to topics_last_message_sticky and topics_board_news
DROP INDEX idx_id_board on {$db_prefix}topics;
---#

/******************************************************************************/
--- Update ban ip with ipv6 support
/******************************************************************************/
---# Add columns to ban_items
ALTER TABLE {$db_prefix}ban_items
ADD COLUMN ip_low varbinary(16),
ADD COLUMN ip_high varbinary(16);
---#

---# Convert data for ban_items
UPDATE IGNORE {$db_prefix}ban_items
SET ip_low =
    UNHEX(
        hex(
            INET_ATON(concat(ip_low1,'.',ip_low2,'.',ip_low3,'.',ip_low4))
        )
    ),
ip_high =
    UNHEX(
        hex(
            INET_ATON(concat(ip_high1,'.',ip_high2,'.',ip_high3,'.',ip_high4))
        )
    )
where ip_low1 > 0;
---#

---# Create new index on ban_items
CREATE INDEX idx_ban_items_iplow_high ON {$db_prefix}ban_items(ip_low,ip_high);
---#

---# Dropping columns from ban_items
ALTER TABLE {$db_prefix}ban_items
DROP ip_low1,
DROP ip_low2,
DROP ip_low3,
DROP ip_low4,
DROP ip_high1,
DROP ip_high2,
DROP ip_high3,
DROP ip_high4;
---#

/******************************************************************************/
--- Update log_action ip with ipv6 support without converting
/******************************************************************************/
---# Remove the old ip column
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_actions', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_actions DROP COLUMN ip;");
---}
---#

---# Add the new one
ALTER TABLE {$db_prefix}log_actions ADD COLUMN ip VARBINARY(16);
---#

/******************************************************************************/
--- Update log_banned ip with ipv6 support without converting
/******************************************************************************/
---# Delete old column log banned ip
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_banned', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_banned DROP COLUMN ip;");
---}
---#

---# Add the new log banned ip
ALTER TABLE {$db_prefix}log_banned ADD COLUMN ip VARBINARY(16);
---#

/******************************************************************************/
--- Update log_errors ip with ipv6 support
/******************************************************************************/
---# Delete old log errors ip column
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_errors', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_errors DROP COLUMN ip;");
---}
---#

---# Add the new ip columns to log errors
ALTER TABLE {$db_prefix}log_errors ADD COLUMN ip VARBINARY(16);
---#

---# Add the ip index for log errors
CREATE INDEX idx_ip ON {$db_prefix}log_errors (ip);
---#

/******************************************************************************/
--- Update members ip with ipv6 support
/******************************************************************************/
---# Rename old ip columns on members
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}members', 'member_ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
{
	upgrade_query("ALTER TABLE {$db_prefix}members CHANGE member_ip member_ip_old varchar(200);");
	upgrade_query("ALTER TABLE {$db_prefix}members CHANGE member_ip2 member_ip2_old varchar(200);");
}
---}
---#

---# Add the new ip columns to members
ALTER TABLE {$db_prefix}members
ADD COLUMN member_ip VARBINARY(16),
ADD COLUMN member_ip2 VARBINARY(16);
---#

---# Create an ip index for old ips
---{
$results = $smcFunc['db_list_columns']('{db_prefix}members');
if (in_array('member_ip_old', $results))
{
	upgrade_query("CREATE INDEX {$db_prefix}temp_old_ip ON {$db_prefix}members (member_ip_old);");
	upgrade_query("CREATE INDEX {$db_prefix}temp_old_ip2 ON {$db_prefix}members (member_ip2_old);");
}
---}
---#

---# Convert member ips
---{
MySQLConvertOldIp('members','member_ip_old','member_ip');
---}
---#

---# Convert member ips2
---{
MySQLConvertOldIp('members','member_ip2_old','member_ip2');
---}
---#

---# Remove the temporary ip indexes
DROP INDEX temp_old_ip on {$db_prefix}members;
DROP INDEX temp_old_ip2 on {$db_prefix}members;
---#

---# Remove the old member columns
ALTER TABLE {$db_prefix}members DROP COLUMN member_ip_old;
ALTER TABLE {$db_prefix}members DROP COLUMN member_ip2_old;
---#

/******************************************************************************/
--- Update messages poster_ip with ipv6 support (May take a while)
/******************************************************************************/
---# Rename old ip column on messages
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}messages', 'poster_ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}messages CHANGE poster_ip poster_ip_old varchar(200);");
---}
---#

---# Add the new ip column to messages
ALTER TABLE {$db_prefix}messages ADD COLUMN poster_ip VARBINARY(16);
---#

---# Create an ip index for old ips
---{
$doChange = true;
$results = $smcFunc['db_list_columns']('{db_prefix}members');
if (!in_array('member_ip_old', $results))
	$doChange = false;

if ($doChange)
	upgrade_query("CREATE INDEX {$db_prefix}temp_old_poster_ip ON {$db_prefix}messages (poster_ip_old);");
---}
---#

---# Convert ips on messages
---{
MySQLConvertOldIp('messages','poster_ip_old','poster_ip');
---}
---#

---# Remove the temporary ip indexes
DROP INDEX temp_old_poster_ip on {$db_prefix}messages;
---#

---# Drop old column to messages
ALTER TABLE {$db_prefix}messages DROP COLUMN poster_ip_old;
---#

---# Add the index again to messages poster ip topic
CREATE INDEX idx_ip_index ON {$db_prefix}messages (poster_ip, id_topic);
---#

---# Add the index again to messages poster ip msg
CREATE INDEX idx_related_ip ON {$db_prefix}messages (id_member, poster_ip, id_msg);
---#

/******************************************************************************/
--- Update log_floodcontrol ip with ipv6 support without converting
/******************************************************************************/
---# Prep floodcontrol
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_floodcontrol', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
{
	upgrade_query("TRUNCATE TABLE {$db_prefix}log_floodcontrol;");
	upgrade_query("ALTER TABLE {$db_prefix}log_floodcontrol DROP PRIMARY KEY;");
	upgrade_query("ALTER TABLE {$db_prefix}log_floodcontrol DROP COLUMN ip;");
}
---}
---#

---# Add the new floodcontrol ip column
ALTER TABLE {$db_prefix}log_floodcontrol ADD COLUMN ip VARBINARY(16);
---#

---# Create primary key for floodcontrol
ALTER TABLE {$db_prefix}log_floodcontrol ADD PRIMARY KEY (ip,log_type);
---#

/******************************************************************************/
--- Update log_online ip with ipv6 support without converting
/******************************************************************************/
---# Delete the old ip column for log online
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_online', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_online DROP COLUMN ip;");
---}
---#

---# Add the new ip column for log online
ALTER TABLE {$db_prefix}log_online ADD COLUMN ip VARBINARY(16);
---#

/******************************************************************************/
--- Update log_reported_comments member_ip with ipv6 support without converting
/******************************************************************************/
---# Drop old ip column for reported comments
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_reported_comments', 'member_ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_reported_comments DROP COLUMN member_ip;");
---}
---#

---# Add the new ip column for reported comments
ALTER TABLE {$db_prefix}log_reported_comments ADD COLUMN member_ip VARBINARY(16);
---#

/******************************************************************************/
--- Update member_logins ip with ipv6 support without converting
/******************************************************************************/
---# Drop old ip columns for member logins
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}member_logins', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
{
	upgrade_query("ALTER TABLE {$db_prefix}member_logins DROP COLUMN ip;");
	upgrade_query("ALTER TABLE {$db_prefix}member_logins DROP COLUMN ip2;");
}
---}
---#

---# Add the new ip columns for member logins
ALTER TABLE {$db_prefix}member_logins ADD COLUMN ip VARBINARY(16);
ALTER TABLE {$db_prefix}member_logins ADD COLUMN ip2 VARBINARY(16);
---#

/******************************************************************************/
--- Update log_online ip with ipv6 support without converting
/******************************************************************************/
---# Delete old column log banned ip
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_online', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_online DROP COLUMN ip;");
---}
---#

---# Add the new log banned ip
ALTER TABLE {$db_prefix}log_online ADD COLUMN ip VARBINARY(16);
---#

/******************************************************************************/
--- Renaming the "profile_other" permission...
/******************************************************************************/
---# Changing the "profile_other" permission to "profile_website"
UPDATE {$db_prefix}permissions SET permission = 'profile_website_own' WHERE permission = 'profile_other_own';
UPDATE {$db_prefix}permissions SET permission = 'profile_website_any' WHERE permission = 'profile_other_any';
---#

/******************************************************************************/
--- drop col pm_email_notify from members
/******************************************************************************/
---# drop column pm_email_notify on table members
ALTER TABLE {$db_prefix}members DROP COLUMN pm_email_notify;
---#

/******************************************************************************/
--- Adding support for start and end times on calendar events
/******************************************************************************/
---# Add start_time end_time, and timezone columns to calendar table
ALTER TABLE {$db_prefix}calendar
ADD COLUMN start_time time,
ADD COLUMN end_time time,
ADD COLUMN timezone VARCHAR(80);
---#

---# Update cal_maxspan and drop obsolete cal_allowspan setting
---{
	if (!isset($modSettings['cal_allowspan']))
		$cal_maxspan = 0;
	elseif ($modSettings['cal_allowspan'] == false)
		$cal_maxspan = 1;
	else
		$cal_maxspan = ($modSettings['cal_maxspan'] > 1) ? $modSettings['cal_maxspan'] : 0;

	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = '$cal_maxspan'
		WHERE variable = 'cal_maxspan'");

	if (isset($modSettings['cal_allowspan']))
		upgrade_query("
			DELETE FROM {$db_prefix}settings
			WHERE variable = 'cal_allowspan'");
---}
---#

/******************************************************************************/
--- Adding location support for calendar events
/******************************************************************************/
---# Add location column to calendar table
ALTER TABLE {$db_prefix}calendar
ADD COLUMN location VARCHAR(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Cleaning up after old UTF-8 languages
/******************************************************************************/
---# Update the members' languages
UPDATE {$db_prefix}members
SET lngfile = REPLACE(lngfile, '-utf8', '');
---#

/******************************************************************************/
--- Create index for messages likes
/******************************************************************************/
---# Add Index for messages likes
CREATE INDEX idx_likes ON {$db_prefix}messages (likes DESC);
---#

/******************************************************************************/
--- Aligning legacy column data
/******************************************************************************/
---# Updating board_permissions
ALTER TABLE {$db_prefix}board_permissions
MODIFY COLUMN id_profile SMALLINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating log_digest id_topic
ALTER TABLE {$db_prefix}log_digest
MODIFY COLUMN id_topic MEDIUMINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating log_digest id_msg
ALTER TABLE {$db_prefix}log_digest
MODIFY COLUMN id_msg INT(10) UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating log_reported
ALTER TABLE {$db_prefix}log_reported
MODIFY COLUMN body MEDIUMTEXT NOT NULL;
---#

---# Updating log_spider_hits
ALTER TABLE {$db_prefix}log_spider_hits
MODIFY COLUMN processed TINYINT NOT NULL DEFAULT '0';
---#

---# Updating members new_pm
ALTER TABLE {$db_prefix}members
MODIFY COLUMN new_pm TINYINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating members pm_ignore_list
ALTER TABLE {$db_prefix}members
MODIFY COLUMN pm_ignore_list VARCHAR(255) NOT NULL DEFAULT '';
---#

---# Updating member_logins id_member
ALTER TABLE {$db_prefix}member_logins
MODIFY COLUMN id_member MEDIUMINT NOT NULL DEFAULT '0';
---#

---# Updating member_logins time
ALTER TABLE {$db_prefix}member_logins
MODIFY COLUMN time INT(10) NOT NULL DEFAULT '0';
---#

---# Updating pm_recipients is_new
ALTER TABLE {$db_prefix}pm_recipients
MODIFY COLUMN is_new TINYINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating pm_rules id_member
ALTER TABLE {$db_prefix}pm_rules
MODIFY COLUMN id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating polls guest_vote
ALTER TABLE {$db_prefix}polls
MODIFY COLUMN guest_vote TINYINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating polls id_member
ALTER TABLE {$db_prefix}polls
MODIFY COLUMN id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT '0';
---#

---# Updating sessions last_update
ALTER TABLE {$db_prefix}sessions
MODIFY COLUMN last_update INT UNSIGNED NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Clean up indexes
/******************************************************************************/
---# Updating log_actions
ALTER TABLE {$db_prefix}log_actions
ADD INDEX id_topic_id_log (id_topic, id_log);
---#

---# Updating log_activity mostOn
ALTER TABLE {$db_prefix}log_activity
DROP INDEX mostOn;
---#

---# Updating log_activity most_on
ALTER TABLE {$db_prefix}log_activity
DROP INDEX most_on;
---#

---# Updating log_subscribed
ALTER TABLE {$db_prefix}log_subscribed
ADD INDEX status (status);
---#

---# Updating members email_address
ALTER TABLE {$db_prefix}members
ADD INDEX email_address (email_address);
---#

---# Updating members drop memberName
ALTER TABLE {$db_prefix}members
DROP INDEX memberName;
---#

---# Updating messages drop old ipIndex
ALTER TABLE {$db_prefix}messages
DROP INDEX ipIndex;
---#

---# Updating messages drop old ip_index
ALTER TABLE {$db_prefix}messages
DROP INDEX ip_index;
---#

---# Updating messages drop old related_ip
ALTER TABLE {$db_prefix}messages
DROP INDEX related_ip;
---#

---# Updating messages drop old topic ix
ALTER TABLE {$db_prefix}messages
DROP INDEX topic;
---#

---# Updating messages drop another old topic ix
ALTER TABLE {$db_prefix}messages
DROP INDEX id_topic;
---#

---# Updating topics drop old id_board ix
ALTER TABLE {$db_prefix}topics
DROP INDEX id_board;
---#

/******************************************************************************/
--- Update smileys
/******************************************************************************/
---# Remove hardcoded gif extensions
UPDATE {$db_prefix}smileys
SET filename = REPLACE(filename, '.gif', '')
WHERE
	code IN (':)',';)',':D',';D','>:(',':(',':o','8)','???','::)',':P',':-[',':-X',':-\\',':-*',':''(','>:D','^-^','O0',':))','C:-)','O:-)') AND
	filename LIKE '%.gif';
---#

---# Remove hardcoded png extensions
UPDATE {$db_prefix}smileys
SET filename = REPLACE(filename, '.png', '')
WHERE
	code IN (':)',';)',':D',';D','>:(',':(',':o','8)','???','::)',':P',':-[',':-X',':-\\',':-*',':''(','>:D','^-^','O0',':))','C:-)','O:-)') AND
	filename LIKE '%.png';
---#

---# Adding new extensions setting...
---{
if (!isset($modSettings['smiley_sets_exts']))
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('smiley_sets_exts', '')");
---}
---#

---# Cleaning up unused smiley sets and adding the lovely new ones
---{
// Start with the prior values...
$dirs = explode(',', $modSettings['smiley_sets_known']);
$setexts = empty($modSettings['smiley_sets_exts']) ? array() : explode(',', $modSettings['smiley_sets_exts']);
$setnames = explode("\n", $modSettings['smiley_sets_names']);

// Build combined pairs of folders and names; bypass default which is not used anymore
// If extensions not provided, assume its an old 2.0 one, i.e., a .gif
$combined = array();
foreach ($dirs AS $ix => $dir)
	if (!empty($setnames[$ix]) && $dir != 'default')
	{
		$combined[$dir] = array($setnames[$ix], empty($setexts[$ix]) ? '.gif' : $setexts[$ix]);
	}

// Add our lovely new 2.1 smiley sets if not already there
$combined['fugue'] = array($txt['default_fugue_smileyset_name'], '.png');
$combined['alienine'] = array($txt['default_alienine_smileyset_name'], '.png');

// Add/fix our 2.0 sets (to correct past problems where these got corrupted)
$combined['aaron'] = array($txt['default_aaron_smileyset_name'], '.gif');
$combined['akyhne'] = array($txt['default_akyhne_smileyset_name'], '.gif');

// Confirm they exist in the filesystem
$filtered = array();
foreach ($combined AS $dir => $attrs)
	if (is_dir($modSettings['smileys_dir'] . '/' . $dir . '/'))
		$filtered[$dir] = $attrs;

// Update the Settings Table...
upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = '" . $smcFunc['db_escape_string'](implode(',', array_keys($filtered))) . "'
	WHERE variable = 'smiley_sets_known'");

upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = '" . $smcFunc['db_escape_string'](implode(',', array_column($filtered, 1))) . "'
	WHERE variable = 'smiley_sets_exts'");

upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = '" . $smcFunc['db_escape_string'](implode("\n", array_column($filtered, 0))) . "'
	WHERE variable = 'smiley_sets_names'");

// Set new default if the old one doesnt exist
// If fugue exists, use that.  Otherwise, what the heck, just grab the first one...
if (!array_key_exists($modSettings['smiley_sets_default'], $filtered))
{
	if (array_key_exists('fugue', $filtered))
		$newdefault = 'fugue';
	elseif (!empty($filtered))
		$newdefault = array_keys($filtered)[0];
	else
		$newdefault = '';
	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = '" . $newdefault . "'
		WHERE variable = 'smiley_sets_default'");
}

---}
---#

/******************************************************************************/
--- Add backtrace to log_error
/******************************************************************************/
---# add backtrace column
ALTER TABLE {$db_prefix}log_errors
ADD COLUMN backtrace varchar(10000) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Update permissions system
/******************************************************************************/
---# Create table board_permissions_view
CREATE TABLE IF NOT EXISTS {$db_prefix}board_permissions_view
(
    id_group SMALLINT NOT NULL DEFAULT '0',
    id_board SMALLINT UNSIGNED NOT NULL,
    deny smallint NOT NULL,
    PRIMARY KEY (id_group, id_board, deny)
) ENGINE=MyISAM;

TRUNCATE {$db_prefix}board_permissions_view;
---#

---# Update board_permissions_view table with membergroups
INSERT INTO {$db_prefix}board_permissions_view (id_board, id_group, deny) SELECT id_board, mg.id_group,0
FROM {$db_prefix}boards b
JOIN {$db_prefix}membergroups mg ON (FIND_IN_SET(mg.id_group, b.member_groups) != 0);
---#

---# Update board_permissions_view table with -1
INSERT INTO {$db_prefix}board_permissions_view (id_board, id_group, deny) SELECT id_board, -1, 0
FROM {$db_prefix}boards b
where (FIND_IN_SET(-1, b.member_groups) != 0);
---#

---# Update board_permissions_view table with 0
INSERT INTO {$db_prefix}board_permissions_view (id_board, id_group, deny) SELECT id_board, 0, 0
FROM {$db_prefix}boards b
where (FIND_IN_SET(0, b.member_groups) != 0);
---#

---# Update deny board_permissions_view table with membergroups
INSERT INTO {$db_prefix}board_permissions_view (id_board, id_group, deny) SELECT id_board, mg.id_group, 1
FROM {$db_prefix}boards b
JOIN {$db_prefix}membergroups mg ON (FIND_IN_SET(mg.id_group, b.deny_member_groups) != 0);
---#

---# Update deny board_permissions_view table with -1
INSERT INTO {$db_prefix}board_permissions_view (id_board, id_group, deny) SELECT id_board, -1, 1
FROM {$db_prefix}boards b
where (FIND_IN_SET(-1, b.deny_member_groups) != 0);
---#

---# Update deny board_permissions_view table with 0
INSERT INTO {$db_prefix}board_permissions_view (id_board, id_group, deny) SELECT id_board, 0, 1
FROM {$db_prefix}boards b
where (FIND_IN_SET(0, b.deny_member_groups) != 0);
---#

