/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Adding login history...
CREATE TABLE IF NOT EXISTS {$db_prefix}member_logins (
	id_login integer NOT NULL auto_increment,
	id_member integer NOT NULL,
	time integer NOT NULL,
	ip varchar(255) NOT NULL default '',
	ip2 varchar(255) NOT NULL default '',
	PRIMARY KEY id_login(id_login)
	KEY id_member (id_member)
	KEY time (time)
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

---# Adding back proper support for UTF8
---{
global $sourcedir;
require_once($sourcedir . '/Subs-Admin.php');
updateSettingsFile(array('db_character_set' => 'utf8'));

upgrade_query("
	INSERT INTO {$db_prefix}settings
		(variable, value)
	VALUES
		('global_character_set', 'UTF-8')");
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
--- Adding new columns for MOVED topic updates
/******************************************************************************/
---# Adding new custom fields columns.
---{
$smcFunc['db_alter_table']('{db_prefix}topics', array(
	'add' => array(
		'redirect_expires' => array(
			'name' => 'redirect_expires',
			'null' => false,
			'default' => '0',
			'type' => 'int',
			'auto' => false,
		),
	)
));
$smcFunc['db_alter_table']('{db_prefix}topics', array(
	'add' => array(
		'id_redirect_topic' => array(
			'name' => 'id_redirect_topic',
			'null' => false,
			'default' => '0',
			'type' => 'int',
			'auto' => false,
		),
	)
));
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
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
---{
$smcFunc['db_alter_table']('{db_prefix}boards', array(
	'add' => array(
		'deny_member_groups' => array(
			'name' => 'deny_member_groups',
			'null' => false,
			'default' => '',
			'type' => varchar,
			'size' => 255,
			'auto' => false,
		),
	)
));
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
--- Messager fields
/******************************************************************************/
---# Insert fields
INSERT INTO `{$db_prefix}custom_fields` (`col_name`, `field_name`, `field_desc`, `field_type`, `field_length`, `field_options`, `mask`, `show_reg`, `show_display`, `show_profile`, `private`, `active`, `bbc`, `can_search`, `default_value`, `enclose`, `placement`) VALUES
('cust_aolins', 'AOL Instant Messenger', 'This is your AOL Instant Messenger nickname.', 'text', 50, '', 'regex~[a-z][0-9a-z.-]{1,31}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="aim" href="aim:goim?screenname={INPUT}&message=Hello!+Are+you+there?" target="_blank" title="AIM - {INPUT}"><img src="{IMAGES_URL}/fields/aim.gif" alt="AIM - {INPUT}"></a>', 1),
('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 'regex~[1-9][0-9]{4,9}~i', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="http://www.icq.com/whitepages/about_me.php?uin={INPUT}" target="_blank" title="ICQ - {INPUT}"><img src="http://status.icq.com/online.gif?img=5&icq={INPUT}" alt="ICQ - {INPUT}" width="18" height="18"></a>', 1),
('cust_msn', 'MSN/Live', 'Your Live Messenger email address', 'text', 50, '', 'email', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="msn" href="http://members.msn.com/{INPUT}" target="_blank" title="Live - {INPUT}"><img src="{IMAGES_URL}/fields/msntalk.gif" alt="Live - {INPUT}"></a>', 1),
('cust_yahoo', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 'email', 0, 1, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="http://edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" title="Yahoo! Messenger - {INPUT}"><img src="http://opi.yahoo.com/online?m=g&t=0&u={INPUT}" alt="Yahoo! Messenger - {INPUT}"></a>', 1);
---#

---# Converting member values...
---{
// We cannot do this twice
if (@$modSettings['smfVersion'] < '2.2')
{
	// Anyone who can currently post unapproved topics we assume can create drafts as well ...
	$request = upgrade_query("
		SELECT id_member, aim, icq, msn, yim
		FROM {$db_prefix}members");
	$inserts = array();
	while ($row = mysql_fetch_assoc($request))
	{
		$inserts[] = "($row[id_member], -1, 'cust_aolins', $row[aim])";
		$inserts[] = "($row[id_member], -1, 'cust_icq', $row[icq])";
		$inserts[] = "($row[id_member], -1, 'cust_msn', $row[msn])";
		$inserts[] = "($row[id_member], -1, 'cust_yahoo', $row[yim])";
	}
	mysql_free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT INTO {$db_prefix}themes
				(id_member, id_theme, variable, value)
			VALUES
				" . implode(',', $inserts));
}
---}
---#

---# Dropping old fields
ALTER TABLE `s{$db_prefix}members`
  DROP `icq`,
  DROP `aim`,
  DROP `yim`,
  DROP `msn`;
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
