/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Creating login history sequence.
CREATE SEQUENCE {$db_prefix}member_logins_seq;
---#

---# Creating login history table.
CREATE TABLE {$db_prefix}member_logins (
	id_login int NOT NULL default nextval('{$db_prefix}member_logins_seq'),
	id_member mediumint NOT NULL,
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

	$request = upgrade_query("
		SELECT id_attach, id_folder, filename, file_hash
		FROM {$db_prefix}attachments
		WHERE file_hash = ''
		LIMIT $_GET[a], 100");

	// Finished?
	if ($smcFunc['db_num_rows']($request) == 0)
		$is_done = true;

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// The current folder.
		$current_folder = !empty($modSettings['currentAttachmentUploadDir']) ? $modSettings['attachmentUploadDir'][$row['id_folder']] : $modSettings['attachmentUploadDir'];

		// The old location of the file.
		$old_location = getLegacyAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder']);

		// The new file name.
		$file_hash = getAttachmentFilename($row['filename'], $row['id_attach'], $row['id_folder'], true);

		// And we try to move it.
		rename($old_location, $current_folder . '/' . $row['id_attach'] . '_' . $file_hash);

		// Only update thif if it was successful.
		if (file_exists($current_folder . '/' . $row['id_attach'] . '_' . $file_hash) && !file_exists($old_location))
			upgrade_query("
				UPDATE {$db_prefix}attachments
				SET file_hash = '$file_hash'
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
ADD COLUMN ip_low5 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high5 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low6 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high6 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low7 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high7 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low8 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high8 smallint NOT NULL DEFAULT '0';
---#

---# Changing existing columns to ban items...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 type smallint,
	ALTER COLUMN ip_high1 type smallint,
	ALTER COLUMN ip_low2 type smallint,
	ALTER COLUMN ip_high2 type smallint,
	ALTER COLUMN ip_low3 type smallint,
	ALTER COLUMN ip_high3 type smallint,
	ALTER COLUMN ip_low4 type smallint,
	ALTER COLUMN ip_high4 type smallint;"
);

upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 SET DEFAULT '0',
	ALTER COLUMN ip_high1 SET DEFAULT '0',
	ALTER COLUMN ip_low2 SET DEFAULT '0',
	ALTER COLUMN ip_high2 SET DEFAULT '0',
	ALTER COLUMN ip_low3 SET DEFAULT '0',
	ALTER COLUMN ip_high3 SET DEFAULT '0',
	ALTER COLUMN ip_low4 SET DEFAULT '0',
	ALTER COLUMN ip_high4 SET DEFAULT '0';"
);

upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 SET NOT NULL,
	ALTER COLUMN ip_high1 SET NOT NULL,
	ALTER COLUMN ip_low2 SET NOT NULL,
	ALTER COLUMN ip_high2 SET NOT NULL,
	ALTER COLUMN ip_low3 SET NOT NULL,
	ALTER COLUMN ip_high3 SET NOT NULL,
	ALTER COLUMN ip_low4 SET NOT NULL,
	ALTER COLUMN ip_high4 SET NOT NULL;"
);
---}
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
---{
upgrade_query("
	ALTER TABLE {$db_prefix}boards
	ADD COLUMN deny_member_groups varchar(255) NOT NULL DEFAULT ''");
---}
---#

/******************************************************************************/
--- Adding support for topic disregard
/******************************************************************************/
---# Adding new columns to log_topics...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_topics
	ADD COLUMN disregarded int NOT NULL DEFAULT '0'");

UPDATE {$db_prefix}log_topics
SET disregarded = 0;

INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('enable_disregard', 0);
---}
---#

/******************************************************************************/
--- Name changes
/******************************************************************************/
---# Altering the membergroup stars to icons
---{
upgrade_query("
	ALTER TABLE {$db_prefix}membergroups
	CHANGE `stars` `icons` varchar(255) NOT NULL DEFAULT ''");
---}
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating drafts table.
CREATE TABLE {$db_prefix}user_drafts (
	id_draft int unsigned NOT NULL auto_increment,
	id_topic int unsigned NOT NULL default '0',
	id_board smallint unsigned NOT NULL default '0',
	id_reply int unsigned NOT NULL default '0',
	type smallint NOT NULL default '0',
	poster_time int unsigned NOT NULL default '0',
	id_member int unsigned NOT NULL default '0',
	subject varchar(255) NOT NULL default '',
	smileys_enabled smallint NOT NULL default '1',
	body text NOT NULL,
	icon varchar(16) NOT NULL default 'xx',
	locked smallint NOT NULL default '0',
	is_sticky smallint NOT NULL default '0',
	to_list varchar(255) NOT NULL default '',
	outbox smallint NOT NULL default '0',
	PRIMARY KEY (id_draft)
);
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
		$inserts[] = "($row[id_group], 'pm_autosave_draft', $row[add_deny])";
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
INSERT INTO {$db_prefix}themes (id_theme, variable, value) VALUES ('1', 'drafts_autosave_enabled', '1');
INSERT INTO {$db_prefix}themes (id_theme, variable, value) VALUES ('1', 'drafts_show_saved_enabled', '1');
---#

/******************************************************************************/
--- Adding support for group-based board moderation
/******************************************************************************/
---# Creating moderator_groups table
CREATE TABLE IF NOT EXISTS {$db_prefix}moderator_groups (
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