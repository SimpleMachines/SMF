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
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}boards
ADD COLUMN deny_member_groups varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for topic disregard
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}log_topics
ADD COLUMN disregarded tinyint(3) NOT NULL DEFAULT '0';

INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('enable_disregard', 0);
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
  outbox tinyint(4) NOT NULL default '0',
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
	while ($row = mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], $row[id_board], 'post_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], $row[id_board], 'post_autosave_draft', $row[add_deny])";
	}
	mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}board_permissions
				(id_group, id_board, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
				
	// Next we find people who can send PM's, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], 'pm_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], 'pm_autosave_draft', $row[add_deny])";
	}
	mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
}
---}
---#
