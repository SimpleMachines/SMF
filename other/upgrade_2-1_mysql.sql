/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Adding login history...
CREATE TABLE IF NOT EXISTS {$db_prefix}member_logins (
	id_login INT(10) AUTO_INCREMENT,
	id_member MEDIUMINT(8) NOT NULL,
	time INT(10) NOT NULL,
	ip VARCHAR(255) NOT NULL DEFAULT '',
	ip2 VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY id_login(id_login),
	INDEX idx_id_member (id_member),
	INDEX idx_time (time)
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
if (!empty($modSettings['currentAttachmentUploadDir']) && !is_array($modSettings['attachmentUploadDir']))
	$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

$is_done = false;
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

/******************************************************************************/
--- Adding support for IPv6...
/******************************************************************************/

---# Adding new columns to ban items...
ALTER TABLE {$db_prefix}ban_items
ADD COLUMN ip_low5 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN ip_high5 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN ip_low6 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN ip_high6 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN ip_low7 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN ip_high7 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN ip_low8 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN ip_high8 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0';
---#

---# Changing existing columns to ban items...
ALTER TABLE {$db_prefix}ban_items
CHANGE ip_low1 ip_low1 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
CHANGE ip_high1 ip_high1 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
CHANGE ip_low2 ip_low2 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
CHANGE ip_high2 ip_high2 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
CHANGE ip_low3 ip_low3 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
CHANGE ip_high3 ip_high3 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
CHANGE ip_low4 ip_low4 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0',
CHANGE ip_high4 ip_high4 SMALLINT(255) UNSIGNED NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding support for logging who fulfils a group request.
/******************************************************************************/

---# Adding new columns to log_group_requests
ALTER TABLE {$db_prefix}log_group_requests
ADD COLUMN status TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN id_member_acted MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
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
ADD COLUMN credits VARCHAR(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding more space for session ids
/******************************************************************************/
---# Altering the session_id columns...
ALTER TABLE {$db_prefix}log_online
CHANGE `session` `session` VARCHAR(64) NOT NULL DEFAULT '';

ALTER TABLE {$db_prefix}log_errors
CHANGE `session` `session` CHAR(64) NOT NULL DEFAULT '                                                                ';

ALTER TABLE {$db_prefix}sessions
CHANGE `session_id` `session_id` CHAR(64) NOT NULL;
---#

/******************************************************************************/
--- Adding support for MOVED topics enhancements
/******************************************************************************/
---# Adding new columns to topics ..
ALTER TABLE {$db_prefix}topics
ADD COLUMN redirect_expires INT(10) UNSIGNED NOT NULL DEFAULT '0',
ADD COLUMN id_redirect_topic MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0';
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
	id_member MEDIUMINT(10) UNSIGNED NOT NULL DEFAULT '0',
	id_member_started MEDIUMINT(10) UNSIGNED NOT NULL DEFAULT '0',
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
	id_member MEDIUMINT(8) UNSIGNED DEFAULT '0',
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
	(0, 'announcements', 2),
	(0, 'member_report', 3),
	(0, 'unapproved_post', 1),
	(0, 'buddy_request', 1),
	(0, 'warn_any', 1),
	(0, 'request_group', 1);
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
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}log_topics
ADD COLUMN unwatched TINYINT(3) NOT NULL DEFAULT '0';

UPDATE {$db_prefix}log_topics
SET unwatched = 0;
---#

---# Fixing column name change...
ALTER TABLE {$db_prefix}log_topics
CHANGE COLUMN disregarded unwatched TINYINT(3) NOT NULL DEFAULT '0';
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
		// Only one row, so no loop needed
		$row = $smcFunc['db_fetch_row']($theme_request);
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
('cust_aolins', 'AOL Instant Messenger', 'This is your AOL Instant Messenger nickname.', 'text', 50, '', 1, 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="aim" href="aim:goim?screenname={INPUT}&message=Hello!+Are+you+there?" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/aim.png" alt="AIM - {INPUT}"></a>', 1),
('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 2, 'regex~[1-9][0-9]{4,9}~i', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="{DEFAULT_IMAGES_URL}/icq.png" alt="ICQ - {INPUT}"></a>', 1),
('cust_skype', 'Skype', 'Your Skype name', 'text', 32, '', 3, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ', 1),
('cust_yahoo', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 4, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="//edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="{IMAGES_URL}/yahoo.png" alt="Yahoo! Messenger - {INPUT}"></a>', 1),
('cust_loca', 'Location', 'Geographic location.', 'text', 50, '', 5, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0),
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

		if (!empty($row['gender']) && isset($genderTypes[INTval($row['gender'])]))
			$inserts[] = array($row['id_member'], -1, 'cust_gender', $genderTypes[INTval($row['gender'])]);
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

		$smcFunc['db_insert']('',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('displayFields', serialize($fields)),
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
	id_topic MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
	id_board SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
	id_reply INT(10) UNSIGNED NOT NULL DEFAULT '0',
	type TINYINT(4) NOT NULL DEFAULT '0',
	poster_time INT(10) UNSIGNED NOT NULL DEFAULT '0',
	id_member MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
	subject VARCHAR(255) NOT NULL DEFAULT '',
	smileys_enabled TINYINT(4) NOT NULL DEFAULT '1',
	body mediumtext NOT NULL,
	icon VARCHAR(16) NOT NULL DEFAULT 'xx',
	locked TINYINT(4) NOT NULL DEFAULT '0',
	is_sticky TINYINT(4) NOT NULL DEFAULT '0',
	to_list VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY id_draft(id_draft),
	INDEX idx_id_member (id_member, id_draft, type)
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
	id_member MEDIUMINT(8) UNSIGNED DEFAULT '0',
	content_type CHAR(6) DEFAULT '',
	content_id INT(10) UNSIGNED DEFAULT '0',
	like_time INT(10) UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (content_id, content_type, id_member),
	INDEX idx_content (content_id, content_type),
	INDEX idx_liker (id_member)
) ENGINE=MyISAM;
---#

---# Adding count to the messages table.
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
	id_member INT NOT NULL DEFAULT 0,
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
	id_label INT(10) UNSIGNED AUTO_INCREMENT,
	id_member MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
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
--- Adding support for edit reasons
/******************************************************************************/
---# Adding "modified_reason" column to messages
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
CHANGE `url` `url` VARCHAR(1024) NOT NULL DEFAULT '';

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

---# Force 2FA per membergroup?
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

---# Converting database to UTF-8. This may take a while...
---{
	// First make sure they aren't already on UTF-8 before we go anywhere...
	if ($db_character_set === 'utf8' && !empty($modSettings['global_character_set']) && $modSettings['global_character_set'] === 'UTF-8')
	{
		echo 'Database already UTF-8. Skipping.';
	}
	else
	{
		// The character sets used in SMF's language files with their db equivalent.
		$charsets = array(
			// Armenian
			'armscii8' => 'armscii8',
			// Chinese-traditional.
			'big5' => 'big5',
			// Chinese-simplified.
			'gbk' => 'gbk',
			// West European.
			'ISO-8859-1' => 'latin1',
			// Romanian.
			'ISO-8859-2' => 'latin2',
			// Turkish.
			'ISO-8859-9' => 'latin5',
			// Latvian
			'ISO-8859-13' => 'latin7',
			// West European with Euro sign.
			'ISO-8859-15' => 'latin9',
			// Thai.
			'tis-620' => 'tis620',
			// Persian, Chinese, etc.
			'UTF-8' => 'utf8',
			// Russian.
			'windows-1251' => 'cp1251',
			// Greek.
			'windows-1253' => 'utf8',
			// Hebrew.
			'windows-1255' => 'utf8',
			// Arabic.
			'windows-1256' => 'cp1256',
		);

		// Get a list of character sets supported by your MySQL server.
		$request = $smcFunc['db_query']('', '
			SHOW CHARACTER SET',
			array(
			)
		);
		$db_charsets = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$db_charsets[] = $row['Charset'];

		$smcFunc['db_free_result']($request);

		// Character sets supported by both MySQL and SMF's language files.
		$charsets = array_intersect($charsets, $db_charsets);

		// Use the messages.body column as indicator for the database charset.
		$request = $smcFunc['db_query']('', '
			SHOW FULL COLUMNS
			FROM {db_prefix}messages
			LIKE {string:body_like}',
			array(
				'body_like' => 'body',
			)
		);
		$column_info = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// A collation looks like latin1_swedish. We only need the character set.
		list($context['database_charset']) = explode('_', $column_info['Collation']);
 		$context['database_charset'] = in_array($context['database_charset'], $charsets) ? array_search($context['database_charset'], $charsets) : $context['database_charset'];

  	// Detect whether a fulltext index is set.
	  $request = $smcFunc['db_query']('', '
 			SHOW INDEX
	  	FROM {db_prefix}messages',
	  	array(
	  	)
  	);

		$context['dropping_index'] = false;

    // If there's a fulltext index, we need to drop it first...
  	if ($request !== false || $smcFunc['db_num_rows']($request) != 0)
	  {
		  while ($row = $smcFunc['db_fetch_assoc']($request))
			  if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT'))
				  $context['fulltext_index'][] = $row['Key_name'];
  		$smcFunc['db_free_result']($request);

	  	if (is_array($context['fulltext_index']))
		  	$context['fulltext_index'] = array_unique($context['fulltext_index']);
	  }

		// Drop it and make a note...
		if (!empty($context['fulltext_index']))
		{
			$context['dropping_index'] = true;

			$smcFunc['db_query']('', '
  			ALTER TABLE {db_prefix}messages
	  		DROP INDEX ' . implode(',
		  	DROP INDEX ', $context['fulltext_index']),
		  	array(
			  	'db_error_skip' => true,
			  )
		  );

			// Update the settings table
			$smcFunc['db_insert']('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				array('db_search_index', ''),
				array('variable')
		}

		// Figure out what charset we should be converting from...
		$lang_charsets = array(
			'arabic' => 'windows-1256',
			'armenian_east' => 'armscii-8',
			'armenian_west' => 'armscii-8',
			'azerbaijani_latin' => 'ISO-8859-9',
			'bangla' => 'UTF-8',
			'belarusian' => 'ISO-8859-5',
			'bulgarian' => 'windows-1251',
			'cambodian' => 'UTF-8',
			'chinese_simplified' => 'gbk',
			'chinese_traditional' => 'big5',
			'croation' => 'ISO-8859-2',
			'czech' => 'ISO-8859-2',
			'czech_informal' => 'ISO-8859-2',
			'english_pirate' => 'UTF-8',
			'esperanto' => 'ISO-8859-3',
			'estonian' => 'ISO-8859-15',
			'filipino_tagalog' => 'UTF-8',
			'filipino_vasayan' => 'UTF-8',
			'georgian' => 'UTF-8',
			'greek' => 'ISO-8859-3',
			'hebrew' => 'windows-1255',
			'hungarian' => 'ISO-8859-2',
			'irish' => 'UTF-8',
			'japanese' => 'UTF-8',
			'khmer' => 'UTF-8',
			'korean' => 'UTF-8',
			'kurdish_kurmanji' => 'ISO-8859-9',
			'kurdish_sorani' => 'windows-1256',
			'lao' => 'tis-620',
			'latvian' => 'ISO-8859-13',
			'lithuanian' => 'ISO-8859-4',
			'macedonian' => 'UTF-8',
			'malayalam' => 'UTF-8',
			'mongolian' => 'UTF-8',
			'nepali' => 'UTF-8',
			'persian' => 'UTF-8',
			'polish' => 'ISO-8859-2',
			'romanian' => 'ISO-8859-2',
			'russian' => 'windows-1252',
			'sakha' => 'UTF-8',
			'serbian_cyrillic' => 'ISO-8859-5',
			'serbian_latin' => 'ISO-8859-2',
			'sinhala' => 'UTF-8',
			'slovak' => 'ISO-8859-2',
			'slovenian' => 'ISO-8859-2',
			'telugu' => 'UTF-8',
			'thai' => 'tis-620',
			'turkish' => 'ISO-8859-9',
			'turkmen' => 'ISO-8859-9',
			'ukranian' => 'windows-1251',
			'urdu' => 'UTF-8',
			'uzbek_cyrillic' => 'ISO-8859-5',
			'uzbek_latin' => 'ISO-8859-5',
			'vietnamese' => 'UTF-8',
			'yoruba' => 'UTF-8'
		);

		// Default to ISO-8859-1 unless we detected another supported charset
		$context['charset_detected'] = (isset($lang_charsets[$language]) && isset($charsets[strtr(strtolower($context['charset_detected']), array('utf' => 'UTF', 'iso' => 'ISO')))) ? $lang_charsets[$language] : 'ISO-8859-1';

		$context['charset_list'] = array_keys($charsets);

		// Translation table for the character sets not native for MySQL.
		$translation_tables = array(
			'windows-1255' => array(
				'0x81' => '\'\'',		'0x8A' => '\'\'',		'0x8C' => '\'\'',
				'0x8D' => '\'\'',		'0x8E' => '\'\'',		'0x8F' => '\'\'',
				'0x90' => '\'\'',		'0x9A' => '\'\'',		'0x9C' => '\'\'',
				'0x9D' => '\'\'',		'0x9E' => '\'\'',		'0x9F' => '\'\'',
				'0xCA' => '\'\'',		'0xD9' => '\'\'',		'0xDA' => '\'\'',
				'0xDB' => '\'\'',		'0xDC' => '\'\'',		'0xDD' => '\'\'',
				'0xDE' => '\'\'',		'0xDF' => '\'\'',		'0xFB' => '\'\'',
				'0xFC' => '\'\'',		'0xFF' => '\'\'',		'0xC2' => '0xFF',
				'0x80' => '0xFC',		'0xE2' => '0xFB',		'0xA0' => '0xC2A0',
				'0xA1' => '0xC2A1',		'0xA2' => '0xC2A2',		'0xA3' => '0xC2A3',
				'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
				'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
				'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
				'0xAF' => '0xC2AF',		'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',
				'0xB2' => '0xC2B2',		'0xB3' => '0xC2B3',		'0xB4' => '0xC2B4',
				'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
				'0xB8' => '0xC2B8',		'0xB9' => '0xC2B9',		'0xBB' => '0xC2BB',
				'0xBC' => '0xC2BC',		'0xBD' => '0xC2BD',		'0xBE' => '0xC2BE',
				'0xBF' => '0xC2BF',		'0xD7' => '0xD7B3',		'0xD1' => '0xD781',
				'0xD4' => '0xD7B0',		'0xD5' => '0xD7B1',		'0xD6' => '0xD7B2',
				'0xE0' => '0xD790',		'0xEA' => '0xD79A',		'0xEC' => '0xD79C',
				'0xED' => '0xD79D',		'0xEE' => '0xD79E',		'0xEF' => '0xD79F',
				'0xF0' => '0xD7A0',		'0xF1' => '0xD7A1',		'0xF2' => '0xD7A2',
				'0xF3' => '0xD7A3',		'0xF5' => '0xD7A5',		'0xF6' => '0xD7A6',
				'0xF7' => '0xD7A7',		'0xF8' => '0xD7A8',		'0xF9' => '0xD7A9',
				'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
				'0x86' => '0xE280A0',	'0x87' => '0xE280A1',	'0x89' => '0xE280B0',
				'0x8B' => '0xE280B9',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
				'0x95' => '0xE280A2',	'0x97' => '0xE28094',	'0x99' => '0xE284A2',
				'0xC0' => '0xD6B0',		'0xC1' => '0xD6B1',		'0xC3' => '0xD6B3',
				'0xC4' => '0xD6B4',		'0xC5' => '0xD6B5',		'0xC6' => '0xD6B6',
				'0xC7' => '0xD6B7',		'0xC8' => '0xD6B8',		'0xC9' => '0xD6B9',
				'0xCB' => '0xD6BB',		'0xCC' => '0xD6BC',		'0xCD' => '0xD6BD',
				'0xCE' => '0xD6BE',		'0xCF' => '0xD6BF',		'0xD0' => '0xD780',
				'0xD2' => '0xD782',		'0xE3' => '0xD793',		'0xE4' => '0xD794',
				'0xE5' => '0xD795',		'0xE7' => '0xD797',		'0xE9' => '0xD799',
				'0xFD' => '0xE2808E',	'0xFE' => '0xE2808F',	'0x92' => '0xE28099',
				'0x83' => '0xC692',		'0xD3' => '0xD783',		'0x88' => '0xCB86',
				'0x98' => '0xCB9C',		'0x91' => '0xE28098',	'0x96' => '0xE28093',
				'0xBA' => '0xC3B7',		'0x9B' => '0xE280BA',	'0xAA' => '0xC397',
				'0xA4' => '0xE282AA',	'0xE1' => '0xD791',		'0xE6' => '0xD796',
				'0xE8' => '0xD798',		'0xEB' => '0xD79B',		'0xF4' => '0xD7A4',
				'0xFA' => '0xD7AA',		'0xFF' => '0xD6B2',		'0xFC' => '0xE282AC',
				'0xFB' => '0xD792',
			),
			'windows-1253' => array(
				'0x81' => '\'\'',			'0x88' => '\'\'',			'0x8A' => '\'\'',
				'0x8C' => '\'\'',			'0x8D' => '\'\'',			'0x8E' => '\'\'',
				'0x8F' => '\'\'',			'0x90' => '\'\'',			'0x98' => '\'\'',
				'0x9A' => '\'\'',			'0x9C' => '\'\'',			'0x9D' => '\'\'',
				'0x9E' => '\'\'',			'0x9F' => '\'\'',			'0xAA' => '\'\'',
				'0xD2' => '\'\'',			'0xFF' => '\'\'',			'0xCE' => '0xCE9E',
				'0xB8' => '0xCE88',		'0xBA' => '0xCE8A',		'0xBC' => '0xCE8C',
				'0xBE' => '0xCE8E',		'0xBF' => '0xCE8F',		'0xC0' => '0xCE90',
				'0xC8' => '0xCE98',		'0xCA' => '0xCE9A',		'0xCC' => '0xCE9C',
				'0xCD' => '0xCE9D',		'0xCF' => '0xCE9F',		'0xDA' => '0xCEAA',
				'0xE8' => '0xCEB8',		'0xEA' => '0xCEBA',		'0xEC' => '0xCEBC',
				'0xEE' => '0xCEBE',		'0xEF' => '0xCEBF',		'0xC2' => '0xFF',
				'0xBD' => '0xC2BD',		'0xED' => '0xCEBD',		'0xB2' => '0xC2B2',
				'0xA0' => '0xC2A0',		'0xA3' => '0xC2A3',		'0xA4' => '0xC2A4',
				'0xA5' => '0xC2A5',		'0xA6' => '0xC2A6',		'0xA7' => '0xC2A7',
				'0xA8' => '0xC2A8',		'0xA9' => '0xC2A9',		'0xAB' => '0xC2AB',
				'0xAC' => '0xC2AC',		'0xAD' => '0xC2AD',		'0xAE' => '0xC2AE',
				'0xB0' => '0xC2B0',		'0xB1' => '0xC2B1',		'0xB3' => '0xC2B3',
				'0xB5' => '0xC2B5',		'0xB6' => '0xC2B6',		'0xB7' => '0xC2B7',
				'0xBB' => '0xC2BB',		'0xE2' => '0xCEB2',		'0x80' => '0xD2',
				'0x82' => '0xE2809A',	'0x84' => '0xE2809E',	'0x85' => '0xE280A6',
				'0x86' => '0xE280A0',	'0xA1' => '0xCE85',		'0xA2' => '0xCE86',
				'0x87' => '0xE280A1',	'0x89' => '0xE280B0',	'0xB9' => '0xCE89',
				'0x8B' => '0xE280B9',	'0x91' => '0xE28098',	'0x99' => '0xE284A2',
				'0x92' => '0xE28099',	'0x93' => '0xE2809C',	'0x94' => '0xE2809D',
				'0x95' => '0xE280A2',	'0x96' => '0xE28093',	'0x97' => '0xE28094',
				'0x9B' => '0xE280BA',	'0xAF' => '0xE28095',	'0xB4' => '0xCE84',
				'0xC1' => '0xCE91',		'0xC3' => '0xCE93',		'0xC4' => '0xCE94',
				'0xC5' => '0xCE95',		'0xC6' => '0xCE96',		'0x83' => '0xC692',
				'0xC7' => '0xCE97',		'0xC9' => '0xCE99',		'0xCB' => '0xCE9B',
				'0xD0' => '0xCEA0',		'0xD1' => '0xCEA1',		'0xD3' => '0xCEA3',
				'0xD4' => '0xCEA4',		'0xD5' => '0xCEA5',		'0xD6' => '0xCEA6',
				'0xD7' => '0xCEA7',		'0xD8' => '0xCEA8',		'0xD9' => '0xCEA9',
				'0xDB' => '0xCEAB',		'0xDC' => '0xCEAC',		'0xDD' => '0xCEAD',
				'0xDE' => '0xCEAE',		'0xDF' => '0xCEAF',		'0xE0' => '0xCEB0',
				'0xE1' => '0xCEB1',		'0xE3' => '0xCEB3',		'0xE4' => '0xCEB4',
				'0xE5' => '0xCEB5',		'0xE6' => '0xCEB6',		'0xE7' => '0xCEB7',
				'0xE9' => '0xCEB9',		'0xEB' => '0xCEBB',		'0xF0' => '0xCF80',
				'0xF1' => '0xCF81',		'0xF2' => '0xCF82',		'0xF3' => '0xCF83',
				'0xF4' => '0xCF84',		'0xF5' => '0xCF85',		'0xF6' => '0xCF86',
				'0xF7' => '0xCF87',		'0xF8' => '0xCF88',		'0xF9' => '0xCF89',
				'0xFA' => '0xCF8A',		'0xFB' => '0xCF8B',		'0xFC' => '0xCF8C',
				'0xFD' => '0xCF8D',		'0xFE' => '0xCF8E',		'0xFF' => '0xCE92',
				'0xD2' => '0xE282AC',
			),
		);

		// Make some preparations.
		if (isset($translation_tables[$context['detected_charset']]))
		{
			$replace = '%field%';

			// Build a huge REPLACE statement...
			foreach ($translation_tables[$_POST['src_charset']] as $from => $to)
				$replace = 'REPLACE(' . $replace . ', ' . $from . ', ' . $to . ')';
		}

		// Grab a list of tables.
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) === 1)
			$queryTables = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				FROM `' . strtr($match[1], array('`' => '')) . '`
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $match[2]) . '%',
				)
			);
		else
			$queryTables = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . '%',
				)
		);

		while ($table_info = $smcFunc['db_fetch_assoc']($queryTables))
		{
			// Just to make sure it doesn't time out.
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$table_charsets = array();

			// Loop through each column.
			$queryColumns = $smcFunc['db_query']('', '
				SHOW FULL COLUMNS
				FROM ' . $table_info['Name'],
				array(
				)
			);
			while ($column_info = $smcFunc['db_fetch_assoc']($queryColumns))
			{
				// Only text'ish columns have a character set and need converting.
				if (strpos($column_info['Type'], 'text') !== false || strpos($column_info['Type'], 'char') !== false)
				{
					$collation = empty($column_info['Collation']) || $column_info['Collation'] === 'NULL' ? $table_info['Collation'] : $column_info['Collation'];
					if (!empty($collation) && $collation !== 'NULL')
					{
						list($charset) = explode('_', $collation);

						if (!isset($table_charsets[$charset]))
							$table_charsets[$charset] = array();

						$table_charsets[$charset][] = $column_info;
					}
				}
			}
			$smcFunc['db_free_result']($queryColumns);

			// Only change the column if the data doesn't match the current charset.
			if ((count($table_charsets) === 1 && key($table_charsets) !== $charsets[$_POST['src_charset']]) || count($table_charsets) > 1)
			{
				$updates_blob = '';
				$updates_text = '';
				foreach ($table_charsets as $charset => $columns)
				{
					if ($charset !== $charsets[$_POST['src_charset']])
					{
						foreach ($columns as $column)
						{
							$updates_blob .= '
								CHANGE COLUMN `' . $column['Field'] . '` `' . $column['Field'] . '` ' . strtr($column['Type'], array('text' => 'blob', 'char' => 'binary')) . ($column['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
							$updates_text .= '
								CHANGE COLUMN `' . $column['Field'] . '` `' . $column['Field'] . '` ' . $column['Type'] . ' CHARACTER SET ' . $charsets[$_POST['src_charset']] . ($column['Null'] === 'YES' ? '' : ' NOT NULL') . (strpos($column['Type'], 'char') === false ? '' : ' default \'' . $column['Default'] . '\'') . ',';
						}
					}
				}

				// Change the columns to binary form.
				$smcFunc['db_query']('', '
					ALTER TABLE {raw:table_name}{raw:updates_blob}',
					array(
						'table_name' => $table_info['Name'],
						'updates_blob' => substr($updates_blob, 0, -1),
					)
				);

				// Convert the character set if MySQL has no native support for it.
				if (isset($translation_tables[$_POST['src_charset']]))
				{
					$update = '';
					foreach ($table_charsets as $charset => $columns)
						foreach ($columns as $column)
							$update .= '
								' . $column['Field'] . ' = ' . strtr($replace, array('%field%' => $column['Field'])) . ',';

					$smcFunc['db_query']('', '
						UPDATE {raw:table_name}
						SET {raw:updates}',
						array(
							'table_name' => $table_info['Name'],
							'updates' => substr($update, 0, -1),
						)
					);
				}

				// Change the columns back, but with the proper character set.
				$smcFunc['db_query']('', '
					ALTER TABLE {raw:table_name}{raw:updates_text}',
					array(
						'table_name' => $table_info['Name'],
						'updates_text' => substr($updates_text, 0, -1),
					)
				);
			}

			// Now do the actual conversion (if still needed).
			if ($charsets[$_POST['src_charset']] !== 'utf8')
				$smcFunc['db_query']('', '
					ALTER TABLE {raw:table_name}
					CONVERT TO CHARACTER SET utf8',
					array(
						'table_name' => $table_info['Name'],
					)
				);
		}
		$smcFunc['db_free_result']($queryTables);

		$prev_charset = empty($translation_tables[$context['charset_detected']])) ? $charsets[$context['charset_detected']] : $translation_tables[$context['charset_detected']];

		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array(array('global_character_set', 'UTF-8'), array('previousCharacterSet', $prev_charset)),
			array('variable')
		);

		// Store it in Settings.php too because it's needed before db connection.
		// Hopefully this works...
		require_once($sourcedir . '/Subs-Admin.php');
		updateSettingsFile(array('db_character_set' => '\'utf8\''));

		// The conversion might have messed up some serialized strings. Fix them!
		$request = $smcFunc['db_query']('', '
			SELECT id_action, extra
			FROM {db_prefix}log_actions
			WHERE action IN ({string:remove}, {string:delete})',
			array(
				'remove' => 'remove',
				'delete' => 'delete',
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (@unserialize($row['extra']) === false && preg_match('~^(a:3:{s:5:"topic";i:\d+;s:7:"subject";s:)(\d+):"(.+)"(;s:6:"member";s:5:"\d+";})$~', $row['extra'], $matches) === 1)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}log_actions
					SET extra = {string:extra}
					WHERE id_action = {int:current_action}',
					array(
						'current_action' => $row['id_action'],
						'extra' => $matches[1] . strlen($matches[3]) . ':"' . $matches[3] . '"' . $matches[4],
					)
				);
		}
		$smcFunc['db_free_result']($request);

		if ($context['dropping_index'])
			echo "\nYour fulltext search index was dropped to facilitate the conversion. You will need to recreate it.";
	}
}
---}
---#