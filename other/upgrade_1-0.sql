/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Creating new tables and inserting default data...
/******************************************************************************/

---# Creating "themes"...
CREATE TABLE IF NOT EXISTS {$db_prefix}themes (
	ID_MEMBER mediumint(8) NOT NULL default '0',
	ID_THEME tinyint(4) unsigned NOT NULL default '1',
	variable tinytext NOT NULL default '',
	value text NOT NULL default '',
	PRIMARY KEY (ID_MEMBER, ID_THEME, variable(30))
) ENGINE=MyISAM;

ALTER TABLE {$db_prefix}themes
CHANGE COLUMN ID_MEMBER ID_MEMBER mediumint(8) NOT NULL default '0';

ALTER TABLE {$db_prefix}themes
CHANGE COLUMN value value text NOT NULL default '';

INSERT IGNORE INTO {$db_prefix}themes
	(ID_MEMBER, ID_THEME, variable, value)
VALUES (0, 1, 'name', 'SMF Default Theme'),
	(0, 1, 'theme_url', '{$boardurl}/Themes/default'),
	(0, 1, 'images_url', '{$boardurl}/Themes/default/images'),
	(0, 1, 'theme_dir', '{$sboarddir}/Themes/default'),
	(0, 1, 'allow_no_censored', '0'),
	(0, 1, 'additional_options_collapsable', '1'),
	(0, 2, 'name', 'Classic YaBB SE Theme'),
	(0, 2, 'theme_url', '{$boardurl}/Themes/classic'),
	(0, 2, 'images_url', '{$boardurl}/Themes/classic/images'),
	(0, 2, 'theme_dir', '{$sboarddir}/Themes/classic');
---#

---# Creating "collapsed_categories"...
CREATE TABLE IF NOT EXISTS {$db_prefix}collapsed_categories (
	ID_CAT tinyint(4) unsigned NOT NULL default '0',
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_CAT, ID_MEMBER)
) ENGINE=MyISAM;
---#

---# Creating and verifying "permissions"...
CREATE TABLE IF NOT EXISTS {$db_prefix}permissions (
	ID_GROUP smallint(6) NOT NULL default '0',
	permission varchar(30) NOT NULL default '',
	addDeny tinyint(4) NOT NULL default '1',
	PRIMARY KEY (ID_GROUP, permission)
) ENGINE=MyISAM;

ALTER TABLE {$db_prefix}permissions
ADD addDeny tinyint(4) NOT NULL default '1';
ALTER TABLE {$db_prefix}permissions
CHANGE COLUMN permission permission varchar(30) NOT NULL default '';

UPDATE IGNORE {$db_prefix}permissions
SET
	permission = REPLACE(permission, 'profile_own_identity', 'profile_identity_own'),
	permission = REPLACE(permission, 'profile_any_identity', 'profile_identity_any'),
	permission = REPLACE(permission, 'profile_own_extra', 'profile_extra_own'),
	permission = REPLACE(permission, 'profile_any_extra', 'profile_extra_any'),
	permission = REPLACE(permission, 'profile_own_title', 'profile_title_own'),
	permission = REPLACE(permission, 'profile_any_title', 'profile_title_any'),
	permission = REPLACE(permission, 'im_read', 'pm_read'),
	permission = REPLACE(permission, 'im_send', 'pm_send');
---#

---# Inserting data into "permissions"...
INSERT INTO {$db_prefix}permissions
	(ID_GROUP, permission)
VALUES (-1, 'search_posts'), (-1, 'calendar_view'), (-1, 'view_stats'), (-1, 'profile_view_any'),
	(2, 'calendar_post'), (2, 'calendar_edit_any'), (2, 'calendar_edit_own');
---#

---# Creating and verifying "board_permissions"...
CREATE TABLE IF NOT EXISTS {$db_prefix}board_permissions (
	ID_GROUP smallint(6) NOT NULL default '0',
	ID_BOARD smallint(5) unsigned NOT NULL default '0',
	permission varchar(30) NOT NULL default '',
	addDeny tinyint(4) NOT NULL default '1',
	PRIMARY KEY (ID_GROUP, ID_BOARD, permission)
) ENGINE=MyISAM;

ALTER TABLE {$db_prefix}board_permissions
ADD addDeny tinyint(4) NOT NULL default '1';
ALTER TABLE {$db_prefix}board_permissions
CHANGE COLUMN permission permission varchar(30) NOT NULL default '';
---#

---# Inserting data into "board_permissions"...
INSERT INTO {$db_prefix}board_permissions
	(ID_GROUP, ID_BOARD, permission)
VALUES (-1, 0, 'poll_view'), (3, 0, 'make_sticky'), (3, 0, 'lock_any'),
	(3, 0, 'remove_any'), (3, 0, 'move_any'), (3, 0, 'merge_any'), (3, 0, 'split_any'),
	(3, 0, 'delete_any'), (3, 0, 'modify_any'), (2, 0, 'make_sticky'), (2, 0, 'lock_any'),
	(2, 0, 'remove_any'), (2, 0, 'move_any'), (2, 0, 'merge_any'), (2, 0, 'split_any'),
	(2, 0, 'delete_any'), (2, 0, 'modify_any'), (2, 0, 'poll_lock_any'), (2, 0, 'poll_lock_any'),
	(2, 0, 'poll_add_any'), (2, 0, 'poll_remove_any'), (2, 0, 'poll_remove_any');
INSERT IGNORE INTO {$db_prefix}board_permissions
	(ID_GROUP, ID_BOARD, permission)
VALUES (3, 0, 'moderate_board'), (2, 0, 'moderate_board');
---#

---# Creating "moderators"...
CREATE TABLE IF NOT EXISTS {$db_prefix}moderators (
	ID_BOARD smallint(5) unsigned NOT NULL default '0',
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_BOARD, ID_MEMBER)
) ENGINE=MyISAM;
---#

---# Creating "attachments"...
CREATE TABLE IF NOT EXISTS {$db_prefix}attachments (
	ID_ATTACH int(11) unsigned NOT NULL auto_increment,
	ID_MSG int(10) unsigned NOT NULL default '0',
	ID_MEMBER int(10) unsigned NOT NULL default '0',
	filename tinytext NOT NULL default '',
	size int(10) unsigned NOT NULL default '0',
	downloads mediumint(8) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_ATTACH),
	UNIQUE ID_MEMBER (ID_MEMBER, ID_ATTACH),
	KEY ID_MSG (ID_MSG)
) ENGINE=MyISAM;
---#

---# Creating "log_notify"...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_notify (
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	ID_TOPIC mediumint(8) unsigned NOT NULL default '0',
	ID_BOARD smallint(5) unsigned NOT NULL default '0',
	sent tinyint(1) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_MEMBER, ID_TOPIC, ID_BOARD)
) ENGINE=MyISAM;
---#

---# Creating "log_polls"...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_polls (
	ID_POLL mediumint(8) unsigned NOT NULL default '0',
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	ID_CHOICE tinyint(4) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_POLL, ID_MEMBER, ID_CHOICE)
) ENGINE=MyISAM;
---#

---# Creating "log_actions"...
CREATE TABLE IF NOT EXISTS {$db_prefix}log_actions (
	ID_ACTION int(10) unsigned NOT NULL auto_increment,
	logTime int(10) unsigned NOT NULL default '0',
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	IP tinytext NOT NULL default '',
	action varchar(30) NOT NULL default '',
	extra text NOT NULL default '',
	PRIMARY KEY (ID_ACTION),
	KEY logTime (logTime),
	KEY ID_MEMBER (ID_MEMBER)
) ENGINE=MyISAM;
---#

---# Creating "poll_choices"...
CREATE TABLE IF NOT EXISTS {$db_prefix}poll_choices (
	ID_POLL mediumint(8) unsigned NOT NULL default '0',
	ID_CHOICE tinyint(4) unsigned NOT NULL default '0',
	label tinytext NOT NULL default '',
	votes smallint(5) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_POLL, ID_CHOICE)
) ENGINE=MyISAM;
---#

---# Creating "smileys"...
CREATE TABLE IF NOT EXISTS {$db_prefix}smileys (
	id_smiley smallint(5) unsigned NOT NULL auto_increment,
	code varchar(30) NOT NULL default '',
	filename varchar(48) NOT NULL default '',
	description varchar(80) NOT NULL default '',
	smileyRow tinyint(4) unsigned NOT NULL default '0',
	smileyOrder tinyint(4) unsigned NOT NULL default '0',
	hidden tinyint(4) unsigned NOT NULL default '0',
	PRIMARY KEY (id_smiley),
	KEY smileyOrder (smileyOrder)
) ENGINE=MyISAM;
---#

---# Loading default smileys...
INSERT IGNORE INTO {$db_prefix}smileys
	(id_smiley, code, filename, description, smileyOrder, hidden)
VALUES (1, ':)', 'smiley.gif', 'Smiley', 0, 0),
	(2, ';)', 'wink.gif', 'Wink', 1, 0),
	(3, ':D', 'cheesy.gif', 'Cheesy', 2, 0),
	(4, ';D', 'grin.gif', 'Grin', 3, 0),
	(5, '>:(', 'angry.gif', 'Angry', 4, 0),
	(6, ':(', 'sad.gif', 'Sad', 5, 0),
	(7, ':o', 'shocked.gif', 'Shocked', 6, 0),
	(8, '8)', 'cool.gif', 'Cool', 7, 0),
	(9, '???', 'huh.gif', 'Huh', 8, 0),
	(10, '::)', 'rolleyes.gif', 'Roll Eyes', 9, 0),
	(11, ':P', 'tongue.gif', 'Tongue', 10, 0),
	(12, ':-[', 'embarassed.gif', 'Embarrassed', 11, 0),
	(13, ':-X', 'lipsrsealed.gif', 'Lips Sealed', 12, 0),
	(14, ':-\\', 'undecided.gif', 'Undecided', 13, 0),
	(15, ':-*', 'kiss.gif', 'Kiss', 14, 0),
	(16, ':\'(', 'cry.gif', 'Cry', 15, 0),
	(17, '>:D', 'evil.gif', 'Evil', 16, 1),
	(18, '^-^', 'azn.gif', 'Azn', 17, 1),
	(19, 'O0', 'afro.gif', 'Afro', 18, 1);
---#

---# Dropping "log_search" and recreating it...
DROP TABLE IF EXISTS {$db_prefix}log_search;
CREATE TABLE {$db_prefix}log_search (
	ID_SEARCH tinyint(3) unsigned NOT NULL default '0',
	ID_TOPIC mediumint(8) unsigned NOT NULL default '0',
	ID_MSG int(10) unsigned NOT NULL default '0',
	relevance smallint(5) unsigned NOT NULL default '0',
	num_matches smallint(5) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_SEARCH, ID_TOPIC)
) ENGINE=MyISAM;
---#

---# Dropping "sessions" and recreating it...
DROP TABLE IF EXISTS {$db_prefix}sessions;
CREATE TABLE {$db_prefix}sessions (
	session_id char(32) NOT NULL,
	last_update int(10) unsigned NOT NULL,
	data text NOT NULL,
	PRIMARY KEY (session_id)
) ENGINE=MyISAM;
---#

---# Verifying "settings"...
ALTER IGNORE TABLE {$db_prefix}settings
DROP PRIMARY KEY,
ADD PRIMARY KEY (variable(30));
---#

/******************************************************************************/
--- Converting activity logs...
/******************************************************************************/

---# Converting "log_online"...
DROP TABLE IF EXISTS {$db_prefix}log_online;
CREATE TABLE {$db_prefix}log_online (
	session char(32) NOT NULL default '                                ',
	logTime timestamp,
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	ip int(11) unsigned NOT NULL default '0',
	url text NOT NULL default '',
	PRIMARY KEY (session),
	KEY online (logTime, ID_MEMBER),
	KEY ID_MEMBER (ID_MEMBER)
) ENGINE=MyISAM;
---#

---# Converting "log_floodcontrol"...
DROP TABLE IF EXISTS {$db_prefix}log_floodcontrol;
CREATE TABLE {$db_prefix}log_floodcontrol (
	ip tinytext NOT NULL default '',
	logTime int(10) unsigned NOT NULL default '0',
	PRIMARY KEY (ip(16)),
	KEY logTime (logTime)
) ENGINE=MyISAM;
---#

---# Converting "log_karma"...
DROP TABLE IF EXISTS {$db_prefix}log_karma;
CREATE TABLE {$db_prefix}log_karma (
	ID_TARGET mediumint(8) unsigned NOT NULL default '0',
	ID_EXECUTOR mediumint(8) unsigned NOT NULL default '0',
	logTime int(10) unsigned NOT NULL default '0',
	action tinyint(4) NOT NULL default '0',
	PRIMARY KEY (ID_TARGET, ID_EXECUTOR),
	KEY logTime (logTime)
) ENGINE=MyISAM;
---#

---# Retiring "log_clicks"...
DROP TABLE IF EXISTS {$db_prefix}log_clicks;
---#

---# Converting "log_notify"...
INSERT INTO {$db_prefix}log_notify
SELECT ID_MEMBER, ID_TOPIC, 0, notificationSent
FROM {$db_prefix}log_topics
WHERE notificationSent != 0;

ALTER TABLE {$db_prefix}log_topics
DROP notificationSent;
---#

---# Converting "log_errors"...
ALTER TABLE {$db_prefix}log_errors
CHANGE COLUMN ID_ERROR ID_ERROR mediumint(8) unsigned NOT NULL auto_increment,
ADD session char(32) NOT NULL default '                                ';
---#

---# Converting "log_boards"...
---{
$request = upgrade_query("
	SELECT lmr.ID_BOARD, lmr.ID_MEMBER, lmr.logTime
	FROM {$db_prefix}log_mark_read AS lmr
		LEFT JOIN {$db_prefix}log_boards AS lb ON (lb.ID_BOARD = lmr.ID_BOARD AND lb.ID_MEMBER = lmr.ID_MEMBER)
	WHERE lb.logTime < lmr.logTime");
$replaceRows = '';
while ($row = mysql_fetch_assoc($request))
	$replaceRows .= "($row[ID_BOARD], $row[ID_MEMBER], $row[logTime]),";
mysql_free_result($request);
if (!empty($replaceRows))
{
	$replaceRows = substr($replaceRows, 0, -1);

	upgrade_query("
		REPLACE INTO {$db_prefix}log_boards
			(ID_BOARD, ID_MEMBER, logTime)
		VALUES $replaceRows");
}
---}
---#

---# Converting "log_activity"...
ALTER TABLE {$db_prefix}log_activity
ADD date date NOT NULL default '0001-01-01';

ALTER TABLE {$db_prefix}log_activity
DROP PRIMARY KEY;

UPDATE IGNORE {$db_prefix}log_activity
SET date = year * 10000 + month * 100 + day;

ALTER TABLE {$db_prefix}log_activity
DROP day,
DROP month,
DROP year;

ALTER TABLE {$db_prefix}log_activity
ADD INDEX hits (hits);
ALTER TABLE {$db_prefix}log_activity
ADD PRIMARY KEY (date);

ALTER TABLE {$db_prefix}log_activity
CHANGE COLUMN hits hits mediumint(8) unsigned NOT NULL default '0',
CHANGE COLUMN topics topics smallint(5) unsigned NOT NULL default '0',
CHANGE COLUMN posts posts smallint(5) unsigned NOT NULL default '0',
CHANGE COLUMN registers registers smallint(5) unsigned NOT NULL default '0',
CHANGE COLUMN most_on most_on smallint(5) unsigned NOT NULL default '0';
---#

/******************************************************************************/
--- Converting Boards and Categories...
/******************************************************************************/

---# Adding new columns to "boards"...
ALTER TABLE {$db_prefix}boards
CHANGE COLUMN count countPosts tinyint(4) NOT NULL default '0',
ADD lastUpdated int(11) unsigned NOT NULL default '0',
ADD ID_PARENT smallint(5) unsigned NOT NULL default '0',
ADD ID_LAST_MSG int(10) unsigned NOT NULL default '0',
ADD childLevel tinyint(4) unsigned NOT NULL default '0';
---#

---# Updating the structure of "boards"...
ALTER TABLE {$db_prefix}boards
CHANGE COLUMN boardOrder boardOrder smallint(5) NOT NULL default '0';

ALTER TABLE {$db_prefix}boards
DROP isAnnouncement;
ALTER TABLE {$db_prefix}boards
ADD ID_THEME tinyint(4) unsigned NOT NULL default '0';
ALTER TABLE {$db_prefix}boards
ADD use_local_permissions tinyint(4) unsigned NOT NULL default '0';
ALTER TABLE {$db_prefix}boards
ADD override_theme tinyint(4) unsigned NOT NULL default '0';
---#

---# Reindexing "boards" (part 1)...
ALTER TABLE {$db_prefix}boards
DROP INDEX ID_CAT,
DROP ID_LAST_TOPIC;
ALTER TABLE {$db_prefix}boards
DROP INDEX memberGroups;
---#

---# Reindexing "boards" (part 2)...
ALTER TABLE {$db_prefix}boards
ADD INDEX lastUpdated (lastUpdated),
ADD INDEX memberGroups (memberGroups(48)),
ADD UNIQUE INDEX categories (ID_CAT, ID_BOARD);
---#

---# Updating the column sizes on "boards"...
ALTER TABLE {$db_prefix}boards
DROP PRIMARY KEY,
CHANGE COLUMN ID_CAT ID_CAT tinyint(4) unsigned NOT NULL default '0',
CHANGE COLUMN numTopics numTopics mediumint(8) unsigned NOT NULL default '0',
CHANGE COLUMN numPosts numPosts mediumint(8) unsigned NOT NULL default '0',
CHANGE COLUMN description description text NOT NULL default '',
CHANGE COLUMN ID_BOARD ID_BOARD smallint(5) unsigned NOT NULL auto_increment PRIMARY KEY;
---#

---# Updating access permissions...
---{
$member_groups = getMemberGroups();

$result = upgrade_query("
	ALTER TABLE {$db_prefix}boards
	ADD memberGroups varchar(128) NOT NULL default '-1,0'");
if ($result !== false)
{
	$result = upgrade_query("
		SELECT TRIM(memberGroups) AS memberGroups, ID_CAT
		FROM {$db_prefix}categories");
	while ($row = mysql_fetch_assoc($result))
	{
		if (trim($row['memberGroups']) == '')
			$groups = '-1,0,2';
		else
		{
			$memberGroups = array_unique(explode(',', $row['memberGroups']));
			$groups = array(2);
			foreach ($memberGroups as $k => $check)
			{
				$memberGroups[$k] = trim($memberGroups[$k]);
				if ($memberGroups[$k] == '' || !isset($member_groups[$memberGroups[$k]]) || $member_groups[$memberGroups[$k]] == 8)
					continue;

				$groups[] = $member_groups[$memberGroups[$k]];
			}

			$groups = implode(',', array_unique($groups));
		}

		upgrade_query("
			UPDATE {$db_prefix}boards
			SET memberGroups = '$groups', lastUpdated = " . time() . "
			WHERE ID_CAT = $row[ID_CAT]");
	}
}
---}

ALTER TABLE {$db_prefix}categories
DROP memberGroups;

ALTER TABLE {$db_prefix}boards
CHANGE COLUMN memberGroups memberGroups varchar(128) NOT NULL default '-1,0';
---#

---# Converting "categories"...
ALTER TABLE {$db_prefix}categories
DROP PRIMARY KEY,
ADD canCollapse tinyint(1) NOT NULL default '1',
CHANGE COLUMN ID_CAT ID_CAT tinyint(4) unsigned NOT NULL auto_increment PRIMARY KEY;
---#

---# Converting announcement permissions...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}boards
	LIKE 'notifyAnnouncements'");
if (mysql_num_rows($request) > 0)
{
	$conversions = array(
		'moderate_forum' => array('manage_membergroups', 'manage_bans'),
		'admin_forum' => array('manage_permissions'),
		'edit_forum' => array('manage_boards', 'manage_smileys', 'manage_attachments'),
	);
	foreach ($conversions as $original_permission => $new_permissions)
	{
		$setString = '';
		$result = upgrade_query("
			SELECT ID_GROUP, addDeny
			FROM {$db_prefix}permissions
			WHERE permission = '$original_permission'");
		while ($row = mysql_fetch_assoc($result))
			$setString .= "
				('" . implode("', $row[ID_GROUP], $row[addDeny]),
				('", $new_permissions) . "', $row[ID_GROUP], $row[addDeny]),";
		mysql_free_result($result);

		if ($setString != '')
			upgrade_query("
				INSERT IGNORE INTO {$db_prefix}permissions
					(permission, ID_GROUP, addDeny)
				VALUES" . substr($setString, 0, -1));
	}
}
mysql_free_result($request);
---}

DELETE FROM {$db_prefix}permissions
WHERE permission = 'edit_forum';

ALTER TABLE {$db_prefix}boards
DROP COLUMN notifyAnnouncements;
---#

---# Converting board statistics...
---{
$result = upgrade_query("
	SELECT MAX(m.ID_MSG) AS ID_LAST_MSG, t.ID_BOARD
	FROM ({$db_prefix}messages AS m, {$db_prefix}topics AS t)
	WHERE m.ID_MSG = t.ID_LAST_MSG
	GROUP BY t.ID_BOARD");
$last_msgs = array();
while ($row = mysql_fetch_assoc($result))
	$last_msgs[] = $row['ID_LAST_MSG'];
mysql_free_result($result);

if (!empty($last_msgs))
{
	$result = upgrade_query("
		SELECT m.ID_MSG, m.posterTime, t.ID_BOARD
		FROM ({$db_prefix}messages AS m, {$db_prefix}topics AS t)
		WHERE t.ID_TOPIC = m.ID_TOPIC
			AND m.ID_MSG IN (" . implode(',', $last_msgs) . ")
		LIMIT " . count($last_msgs));
	while ($row = mysql_fetch_assoc($result))
	{
		upgrade_query("
			UPDATE {$db_prefix}boards
			SET ID_LAST_MSG = $row[ID_MSG], lastUpdated = " . (int) $row['posterTime'] . "
			WHERE ID_BOARD = $row[ID_BOARD]
			LIMIT 1");
	}
	mysql_free_result($result);
}
---}
---#

---# Converting "moderators"...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}boards
	LIKE 'moderators'");
$do_moderators = mysql_num_rows($request) > 0;
mysql_free_result($request);

if ($do_moderators)
{
	$result = upgrade_query("
		SELECT TRIM(moderators) AS moderators, ID_BOARD
		FROM {$db_prefix}boards
		WHERE TRIM(moderators) != ''");
	while ($row = mysql_fetch_assoc($result))
	{
		$moderators = array_unique(explode(',', $row['moderators']));
		foreach ($moderators as $k => $dummy)
		{
			$moderators[$k] = addslashes(trim($moderators[$k]));
			if ($moderators[$k] == '')
				unset($moderators[$k]);
		}

		if (!empty($moderators))
		{
			upgrade_query("
				INSERT IGNORE INTO {$db_prefix}moderators
					(ID_BOARD, ID_MEMBER)
				SELECT $row[ID_BOARD], ID_MEMBER
				FROM {$db_prefix}members
				WHERE memberName IN ('" . implode("', '", $moderators) . "')
				LIMIT " . count($moderators));
		}
	}
}
---}

ALTER TABLE {$db_prefix}boards
DROP moderators;
---#

---# Updating board order...
---{
$request = upgrade_query("
	SELECT c.ID_CAT, c.catOrder, b.ID_BOARD, b.boardOrder
	FROM {$db_prefix}categories AS c
		LEFT JOIN {$db_prefix}boards AS b ON (b.ID_CAT = c.ID_CAT)
	ORDER BY c.catOrder, b.childLevel, b.boardOrder, b.ID_BOARD");
$catOrder = -1;
$boardOrder = -1;
$curCat = -1;
while ($row = mysql_fetch_assoc($request))
{
	if ($curCat != $row['ID_CAT'])
	{
		$curCat = $row['ID_CAT'];
		if (++$catOrder != $row['catOrder'])
			upgrade_query("
				UPDATE {$db_prefix}categories
				SET catOrder = $catOrder
				WHERE ID_CAT = $row[ID_CAT]
				LIMIT 1");
	}
	if (!empty($row['ID_BOARD']) && ++$boardOrder != $row['boardOrder'])
		upgrade_query("
			UPDATE {$db_prefix}boards
			SET boardOrder = $boardOrder
			WHERE ID_BOARD = $row[ID_BOARD]
			LIMIT 1");
}
mysql_free_result($request);
---}
---#

---# Fixing possible issues with board access (part 1)...
---{
if (empty($modSettings['smfVersion']) || (substr($modSettings['smfVersion'], 0, 9) == '1.0 Beta ' && $modSettings['smfVersion'][9] <= 5))
{
	$all_groups = array();
	$result = upgrade_query("
		SELECT ID_GROUP
		FROM {$db_prefix}membergroups");
	while ($row = mysql_fetch_assoc($result))
		$all_groups[] = $row['ID_GROUP'];
	mysql_free_result($result);

	$result = upgrade_query("
		SELECT ID_BOARD, memberGroups
		FROM {$db_prefix}boards
		WHERE FIND_IN_SET(0, memberGroups)");
	while ($row = mysql_fetch_assoc($result))
	{
		upgrade_query("
			UPDATE {$db_prefix}boards
			SET memberGroups = '" . implode(',', array_unique(array_merge(explode(',', $row['memberGroups']), $all_groups))) . "'
			WHERE ID_BOARD = $row[ID_BOARD]
			LIMIT 1");
	}
	mysql_free_result($result);
}
---}
---#

---# Fixing possible issues with board access. (part 2)..
UPDATE {$db_prefix}boards
SET memberGroups = SUBSTRING(memberGroups, 2)
WHERE SUBSTRING(memberGroups, 1, 1) = ',';

UPDATE {$db_prefix}boards
SET memberGroups = SUBSTRING(memberGroups, 1, LENGTH(memberGroups) - 1)
WHERE SUBSTRING(memberGroups, LENGTH(memberGroups)) = ',';

UPDATE {$db_prefix}boards
SET memberGroups = REPLACE(',,', ',', REPLACE(',,', ',', memberGroups))
WHERE LOCATE(',,', memberGroups);
---#

/******************************************************************************/
--- Converting attachments, topics, and messages...
/******************************************************************************/

---# Converting "attachments"...
INSERT INTO {$db_prefix}attachments
	(ID_MSG, filename, size)
SELECT ID_MSG, SUBSTRING(attachmentFilename, 1, 255), attachmentSize
FROM {$db_prefix}messages
WHERE attachmentFilename IS NOT NULL
	AND attachmentFilename != '';

ALTER TABLE {$db_prefix}messages
DROP attachmentSize,
DROP attachmentFilename;
---#

---# Updating "attachments"...
ALTER TABLE {$db_prefix}attachments
DROP INDEX ID_MEMBER,
ADD UNIQUE ID_MEMBER (ID_MEMBER, ID_ATTACH);

ALTER TABLE {$db_prefix}attachments
CHANGE COLUMN size size int(10) unsigned NOT NULL default '0';
---#

---# Updating columns on "messages" (part 1)...
ALTER TABLE {$db_prefix}messages
DROP PRIMARY KEY,
CHANGE COLUMN ID_MSG ID_MSG int(10) unsigned NOT NULL auto_increment PRIMARY KEY;
---#

---# Updating columns on "messages" (part 2)...
ALTER TABLE {$db_prefix}messages
CHANGE COLUMN ID_TOPIC ID_TOPIC mediumint(8) unsigned NOT NULL default '0';
ALTER TABLE {$db_prefix}messages
CHANGE COLUMN smiliesEnabled smileysEnabled tinyint(4) NOT NULL default '1';
---#

---# Updating columns on "messages" (part 3)...
ALTER TABLE {$db_prefix}messages
CHANGE COLUMN posterTime posterTime int(10) unsigned NOT NULL default '0',
CHANGE COLUMN modifiedTime modifiedTime int(10) unsigned NOT NULL default '0';

ALTER TABLE {$db_prefix}messages
ADD INDEX participation (ID_MEMBER, ID_TOPIC);
ALTER TABLE {$db_prefix}messages
ADD INDEX ipIndex (posterIP(15), ID_TOPIC);
---#

---# Updating columns on "messages" (part 4)...
ALTER TABLE {$db_prefix}messages
CHANGE COLUMN ID_MEMBER ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
CHANGE COLUMN icon icon varchar(16) NOT NULL default 'xx';

ALTER TABLE {$db_prefix}messages
ADD INDEX ID_MEMBER (ID_MEMBER);
ALTER TABLE {$db_prefix}messages
ADD UNIQUE INDEX topic (ID_TOPIC, ID_MSG);
---#

---# Updating columns on "messages" (part 5)...
ALTER TABLE {$db_prefix}messages
ADD COLUMN ID_BOARD smallint(5) unsigned NOT NULL default '0';
---#

---# Updating data in "messages"...
---{
while (true)
{
	nextSubstep($substep);

	$request = upgrade_query("
		SELECT DISTINCT t.ID_BOARD, t.ID_TOPIC
		FROM ({$db_prefix}messages AS m, {$db_prefix}topics AS t)
		WHERE t.ID_TOPIC = m.ID_TOPIC
			AND m.ID_BOARD = 0
		LIMIT 1400");
	$boards = array();
	while ($row = mysql_fetch_assoc($request))
		$boards[$row['ID_BOARD']][] = $row['ID_TOPIC'];

	foreach ($boards as $board => $topics)
		upgrade_query("
			UPDATE {$db_prefix}messages
			SET ID_BOARD = $board
			WHERE ID_TOPIC IN (" . implode(', ', $topics) . ')');

	if (mysql_num_rows($request) < 1400)
		break;

	mysql_free_result($request);
}
---}
---#

---# Cleaning up "messages"...
ALTER TABLE {$db_prefix}messages
ADD INDEX ID_BOARD (ID_BOARD);

ALTER TABLE {$db_prefix}messages
DROP INDEX posterTime_2;
ALTER TABLE {$db_prefix}messages
DROP INDEX posterTime_3;

ALTER TABLE {$db_prefix}messages
DROP INDEX ID_MEMBER_2;
ALTER TABLE {$db_prefix}messages
DROP INDEX ID_MEMBER_3;
---#

---# Updating indexes on "topics" (part 1)...
ALTER TABLE {$db_prefix}topics
DROP INDEX ID_FIRST_MSG;

ALTER TABLE {$db_prefix}topics
DROP INDEX ID_LAST_MSG;

ALTER TABLE {$db_prefix}topics
ADD INDEX isSticky (isSticky);
---#

---# Updating indexes on "topics" (part 2)...
ALTER IGNORE TABLE {$db_prefix}topics
ADD UNIQUE INDEX lastMessage (ID_LAST_MSG, ID_BOARD),
ADD UNIQUE INDEX firstMessage (ID_FIRST_MSG, ID_BOARD),
ADD UNIQUE INDEX poll (ID_POLL, ID_TOPIC);
---#

---# Updating columns on "topics" (part 1)...
ALTER TABLE {$db_prefix}topics
DROP PRIMARY KEY,
CHANGE COLUMN ID_TOPIC ID_TOPIC mediumint(8) unsigned NOT NULL auto_increment PRIMARY KEY,
CHANGE COLUMN ID_BOARD ID_BOARD smallint(5) unsigned NOT NULL default '0';
---#

---# Updating columns on "topics" (part 2)...
ALTER TABLE {$db_prefix}topics
CHANGE COLUMN ID_MEMBER_STARTED ID_MEMBER_STARTED mediumint(8) unsigned NOT NULL default '0',
CHANGE COLUMN ID_MEMBER_UPDATED ID_MEMBER_UPDATED mediumint(8) unsigned NOT NULL default '0';
---#

---# Updating columns on "topics" (part 3)...
ALTER TABLE {$db_prefix}topics
CHANGE COLUMN ID_FIRST_MSG ID_FIRST_MSG int(10) unsigned NOT NULL default '0',
CHANGE COLUMN ID_LAST_MSG ID_LAST_MSG int(10) unsigned NOT NULL default '0';
---#

---# Updating columns on "topics" (part 4)...
ALTER TABLE {$db_prefix}topics
CHANGE COLUMN ID_POLL ID_POLL mediumint(8) unsigned NOT NULL default '0';
---#

/******************************************************************************/
--- Converting members and personal messages...
/******************************************************************************/

---# Updating data in "members" (part 1)...
UPDATE IGNORE {$db_prefix}members
SET im_ignore_list = '*'
WHERE im_ignore_list RLIKE '([\n,]|^)[*]([\n,]|$)';
---#

---# Updating data in "members" (part 2)...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}members
	LIKE 'im_ignore_list'");
$do_it = mysql_num_rows($request) != 0;
mysql_free_result($request);

while ($do_it)
{
	nextSubstep($substep);

	$request = upgrade_query("
		SELECT ID_MEMBER, im_ignore_list
		FROM {$db_prefix}members
		WHERE im_ignore_list RLIKE '[a-z]'
		LIMIT 512");
	while ($row = mysql_fetch_assoc($request))
	{
		$request2 = upgrade_query("
			SELECT ID_MEMBER
			FROM {$db_prefix}members
			WHERE FIND_IN_SET(memberName, '" . addslashes($row['im_ignore_list']) . "')");
		$im_ignore_list = '';
		while ($row2 = mysql_fetch_assoc($request2))
			$im_ignore_list .= ',' . $row2['ID_MEMBER'];
		mysql_free_result($request2);

		upgrade_query("
			UPDATE {$db_prefix}members
			SET im_ignore_list = '" . substr($im_ignore_list, 1) . "'
			WHERE ID_MEMBER = $row[ID_MEMBER]
			LIMIT 1");
	}
	if (mysql_num_rows($request) < 512)
		break;
	mysql_free_result($request);
}
---}
---#

---# Updating data in "members" (part 3)...
UPDATE {$db_prefix}members
SET realName = memberName
WHERE IFNULL(realName, '') = '';
---#

---# Updating data in "members" (part 4)...
UPDATE {$db_prefix}members
SET lngfile = REPLACE(lngfile, '.lng', '')
WHERE lngfile LIKE '%.lng';
---#

---# Cleaning up "members"...
ALTER TABLE {$db_prefix}members
DROP INDEX memberID;
ALTER TABLE {$db_prefix}members
DROP INDEX memberID_2;
---#

---# Adding new columns to "members"...
ALTER TABLE {$db_prefix}members
DROP PRIMARY KEY,
CHANGE COLUMN ID_MEMBER ID_MEMBER mediumint(8) unsigned NOT NULL auto_increment PRIMARY KEY,
ADD instantMessages smallint(5) NOT NULL default 0,
ADD unreadMessages smallint(5) NOT NULL default 0,
ADD ID_THEME tinyint(4) unsigned NOT NULL default 0,
ADD ID_GROUP smallint(5) unsigned NOT NULL default 0,
ADD is_activated tinyint(3) unsigned NOT NULL default '1',
ADD validation_code varchar(10) NOT NULL default '',
ADD ID_MSG_LAST_VISIT int(10) unsigned NOT NULL default '0',
ADD additionalGroups tinytext NOT NULL default '';
---#

---# Updating columns on "members"...
ALTER TABLE {$db_prefix}members
CHANGE COLUMN ID_THEME ID_THEME tinyint(4) unsigned NOT NULL default 0;
ALTER TABLE {$db_prefix}members
ADD showOnline tinyint(4) NOT NULL default '1';
ALTER TABLE {$db_prefix}members
ADD smileySet varchar(48) NOT NULL default '';
ALTER TABLE {$db_prefix}members
ADD totalTimeLoggedIn int(10) unsigned NOT NULL default '0';
ALTER TABLE {$db_prefix}members
ADD passwordSalt varchar(5) NOT NULL default '';
---#

---# Updating data in "members" (part 5)...
UPDATE {$db_prefix}members
SET gender = CASE gender
	WHEN '0' THEN 0
	WHEN 'Male' THEN 1
	WHEN 'Female' THEN 2
	ELSE 0 END, secretAnswer = IF(secretAnswer = '', '', MD5(secretAnswer))
WHERE gender NOT IN ('0', '1', '2');
---#

---# Updating data in "members" (part 6)...
---{
$member_groups = getMemberGroups();

foreach ($member_groups as $name => $id)
{
	upgrade_query("
		UPDATE IGNORE {$db_prefix}members
		SET ID_GROUP = $id
		WHERE memberGroup = '" . addslashes($name) . "'");

	nextSubstep($substep);
}
---}
UPDATE IGNORE {$db_prefix}members
SET ID_GROUP = 1
WHERE memberGroup = 'Administrator';
UPDATE IGNORE {$db_prefix}members
SET ID_GROUP = 2
WHERE memberGroup = 'Global Moderator';

ALTER TABLE {$db_prefix}members
DROP memberGroup;
---#

---# Changing column sizes on "members" (part 1)...
ALTER TABLE {$db_prefix}members
CHANGE COLUMN timeOffset timeOffset float NOT NULL default '0',
CHANGE COLUMN posts posts mediumint(8) unsigned NOT NULL default '0',
CHANGE COLUMN timeFormat timeFormat varchar(80) NOT NULL default '',
CHANGE COLUMN lastLogin lastLogin int(11) NOT NULL default '0',
CHANGE COLUMN karmaBad karmaBad smallint(5) unsigned NOT NULL default '0',
CHANGE COLUMN karmaGood karmaGood smallint(5) unsigned NOT NULL default '0',
CHANGE COLUMN gender gender tinyint(4) unsigned NOT NULL default '0',
CHANGE COLUMN hideEmail hideEmail tinyint(4) NOT NULL default '0';
---#

---# Changing column sizes on "members" (part 2)...
ALTER TABLE {$db_prefix}members
DROP INDEX realName;

ALTER TABLE {$db_prefix}members
CHANGE COLUMN AIM AIM varchar(16) NOT NULL default '',
CHANGE COLUMN YIM YIM varchar(32) NOT NULL default '',
CHANGE COLUMN ICQ ICQ tinytext NOT NULL default '',
CHANGE COLUMN realName realName tinytext NOT NULL default '',
CHANGE COLUMN emailAddress emailAddress tinytext NOT NULL default '',
CHANGE COLUMN dateRegistered dateRegistered int(10) unsigned NOT NULL default '0',
CHANGE COLUMN passwd passwd varchar(64) NOT NULL default '',
CHANGE COLUMN personalText personalText tinytext NOT NULL default '',
CHANGE COLUMN websiteTitle websiteTitle tinytext NOT NULL default '';
---#

---# Changing column sizes on "members" (part 3)...
ALTER TABLE {$db_prefix}members
DROP INDEX lngfile;

ALTER TABLE {$db_prefix}members
CHANGE COLUMN websiteUrl websiteUrl tinytext NOT NULL default '',
CHANGE COLUMN location location tinytext NOT NULL default '',
CHANGE COLUMN avatar avatar tinytext NOT NULL default '',
CHANGE COLUMN im_ignore_list im_ignore_list tinytext NOT NULL default '',
CHANGE COLUMN usertitle usertitle tinytext NOT NULL default '',
CHANGE COLUMN lngfile lngfile tinytext NOT NULL default '',
CHANGE COLUMN MSN MSN tinytext NOT NULL default '',
CHANGE COLUMN memberIP memberIP tinytext NOT NULL default '',
ADD INDEX lngfile (lngfile(24));
---#

---# Updating keys on "members"...
ALTER TABLE {$db_prefix}members
ADD INDEX ID_GROUP (ID_GROUP),
ADD INDEX birthdate (birthdate),
ADD INDEX lngfile (lngfile(30));
---#

---# Converting member statistics...
REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'latestMember', ID_MEMBER
FROM {$db_prefix}members
ORDER BY ID_MEMBER DESC
LIMIT 1;

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'latestRealName', IFNULL(realName, memberName)
FROM {$db_prefix}members
ORDER BY ID_MEMBER DESC
LIMIT 1;

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'maxMsgID', ID_MSG
FROM {$db_prefix}messages
ORDER BY ID_MSG DESC
LIMIT 1;
---#

---# Adding new columns to "instant_messages"...
ALTER IGNORE TABLE {$db_prefix}instant_messages
ADD COLUMN deletedBySender tinyint(3) unsigned NOT NULL default '0' AFTER ID_MEMBER_FROM;
---#

---# Changing column sizes on "instant_messages" (part 1)...
ALTER TABLE {$db_prefix}instant_messages
CHANGE COLUMN ID_MEMBER_FROM ID_MEMBER_FROM mediumint(8) unsigned NOT NULL default 0,
CHANGE COLUMN msgtime msgtime int(10) unsigned NOT NULL default '0',
CHANGE COLUMN subject subject tinytext NOT NULL;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX fromName,
DROP INDEX ID_MEMBER_FROM;
---#

---# Changing column sizes on "instant_messages" (part 2)...
ALTER TABLE {$db_prefix}instant_messages
DROP PRIMARY KEY,
CHANGE COLUMN ID_IM ID_PM int(10) unsigned NOT NULL auto_increment PRIMARY KEY;
ALTER TABLE {$db_prefix}instant_messages
ADD INDEX msgtime (msgtime);
---#

---# Cleaning up "instant_messages"...
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_FROM_2;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_FROM_3;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_FROM_4;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_FROM_5;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_TO_2;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_TO_3;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_TO_4;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_TO_5;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX deletedBy_2;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX deletedBy_3;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX deletedBy_4;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX deletedBy_5;
---#

---# Creating "im_recipients"...
CREATE TABLE IF NOT EXISTS {$db_prefix}im_recipients (
	ID_PM int(10) unsigned NOT NULL default '0',
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	bcc tinyint(3) unsigned NOT NULL default '0',
	is_read tinyint(3) unsigned NOT NULL default '0',
	deleted tinyint(3) unsigned NOT NULL default '0',
	PRIMARY KEY (ID_PM, ID_MEMBER),
	KEY ID_MEMBER (ID_MEMBER, deleted)
) ENGINE=MyISAM;
---#

---# Updating "im_recipients"...
ALTER TABLE {$db_prefix}im_recipients
DROP PRIMARY KEY,
CHANGE COLUMN ID_IM ID_PM int(10) unsigned NOT NULL default '0',
ADD PRIMARY KEY (ID_PM, ID_MEMBER);
---#

---# Updating data in "instant_messages" (part 1)...
---{
$request = mysql_query("
	SHOW COLUMNS
	FROM {$db_prefix}instant_messages
	LIKE 'readBy'");
$do_it = $request !== false;

if ($do_it)
{
	$adv_im = mysql_num_rows($request) == 0;
	mysql_free_result($request);

	mysql_query("
		INSERT IGNORE INTO {$db_prefix}im_recipients
			(ID_PM, ID_MEMBER, bcc, is_read, deleted)
		SELECT ID_PM, ID_MEMBER_TO, 0, IF(" . (!$adv_im ? 'readBy' : 'alerted') . " != 0, 1, 0), IF(deletedBy = '1', 1, 0)
		FROM {$db_prefix}instant_messages");
}
---}

UPDATE IGNORE {$db_prefix}instant_messages
SET deletedBySender = 1
WHERE deletedBy = 0;
---#

---# Updating data in "instant_messages" (part 2)...
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX ID_MEMBER_TO;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX deletedBy;
ALTER TABLE {$db_prefix}instant_messages
DROP INDEX readBy;

ALTER TABLE {$db_prefix}instant_messages
DROP COLUMN ID_MEMBER_TO,
DROP COLUMN deletedBy,
DROP COLUMN toName,
DROP COLUMN readBy;

ALTER TABLE {$db_prefix}instant_messages
ADD INDEX ID_MEMBER (ID_MEMBER_FROM, deletedBySender);
---#

---# Recounting personal message totals...
---{
$request = mysql_query("
	SHOW CREATE TABLE {$db_prefix}instant_messages");
$do_it = $request !== false;
@mysql_free_result($request);

$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}members");
list ($totalMembers) = mysql_fetch_row($request);
mysql_free_result($request);

$_GET['m'] = (int) @$_GET['m'];

while ($_GET['m'] < $totalMembers && $do_it)
{
	nextSubstep($substep);

	$mrequest = upgrade_query("
		SELECT mem.ID_MEMBER, COUNT(pmr.ID_PM) AS instantMessages_real, mem.instantMessages
		FROM {$db_prefix}members AS mem
			LEFT JOIN {$db_prefix}im_recipients AS pmr ON (pmr.ID_MEMBER = mem.ID_MEMBER AND pmr.deleted = 0)
		WHERE mem.ID_MEMBER > $_GET[m]
			AND mem.ID_MEMBER <= $_GET[m] + 512
		GROUP BY mem.ID_MEMBER
		HAVING instantMessages_real != instantMessages
		LIMIT 512");
	while ($row = mysql_fetch_assoc($mrequest))
	{
		upgrade_query("
			UPDATE {$db_prefix}members
			SET instantMessages = $row[instantMessages_real]
			WHERE ID_MEMBER = $row[ID_MEMBER]
			LIMIT 1");
	}

	$_GET['m'] += 512;
}
unset($_GET['m']);
---}
---{
$request = mysql_query("
	SHOW CREATE TABLE {$db_prefix}instant_messages");
$do_it = $request !== false;
@mysql_free_result($request);

$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}members");
list ($totalMembers) = mysql_fetch_row($request);
mysql_free_result($request);

$_GET['m'] = (int) @$_GET['m'];

while ($_GET['m'] < $totalMembers && $do_it)
{
	nextSubstep($substep);

	$mrequest = upgrade_query("
		SELECT mem.ID_MEMBER, COUNT(pmr.ID_PM) AS unreadMessages_real, mem.unreadMessages
		FROM {$db_prefix}members AS mem
			LEFT JOIN {$db_prefix}im_recipients AS pmr ON (pmr.ID_MEMBER = mem.ID_MEMBER AND pmr.deleted = 0 AND pmr.is_read = 0)
		WHERE mem.ID_MEMBER > $_GET[m]
			AND mem.ID_MEMBER <= $_GET[m] + 512
		GROUP BY mem.ID_MEMBER
		HAVING unreadMessages_real != unreadMessages
		LIMIT 512");
	while ($row = mysql_fetch_assoc($mrequest))
	{
		upgrade_query("
			UPDATE {$db_prefix}members
			SET unreadMessages = $row[unreadMessages_real]
			WHERE ID_MEMBER = $row[ID_MEMBER]
			LIMIT 1");
	}

	$_GET['m'] += 512;
}
unset($_GET['m']);
---}
---#

---# Converting "membergroups"...
---{
global $JrPostNum, $FullPostNum, $SrPostNum, $GodPostNum;

$result = mysql_query("
	SELECT minPosts
	FROM {$db_prefix}membergroups
	LIMIT 1");
if ($result === false)
{
	upgrade_query("
		RENAME TABLE {$db_prefix}membergroups TO {$db_prefix}old_membergroups");

	upgrade_query("
		CREATE TABLE {$db_prefix}membergroups (
			ID_GROUP smallint(5) unsigned NOT NULL auto_increment,
			groupName varchar(80) NOT NULL default '',
			onlineColor varchar(20) NOT NULL default '',
			minPosts mediumint(9) NOT NULL default '-1',
			maxMessages smallint(5) unsigned NOT NULL default '0',
			stars tinytext NOT NULL default '',
			PRIMARY KEY (ID_GROUP),
			KEY minPosts (minPosts)
		) ENGINE=MyISAM");

	upgrade_query("
		INSERT INTO {$db_prefix}membergroups
			(ID_GROUP, groupName, onlineColor, minPosts, stars)
		SELECT ID_GROUP, membergroup, '#FF0000', -1, '5#staradmin.gif'
		FROM {$db_prefix}old_membergroups
		WHERE ID_GROUP = 1");

	upgrade_query("
		INSERT INTO {$db_prefix}membergroups
			(ID_GROUP, groupName, onlineColor, minPosts, stars)
		SELECT 2, membergroup, '#0000FF', -1, '5#stargmod.gif'
		FROM {$db_prefix}old_membergroups
		WHERE ID_GROUP = 8");

	upgrade_query("
		INSERT INTO {$db_prefix}membergroups
			(ID_GROUP, groupName, onlineColor, minPosts, stars)
		SELECT 3, membergroup, '', -1, '5#starmod.gif'
		FROM {$db_prefix}old_membergroups
		WHERE ID_GROUP = 2");

	upgrade_query("
		INSERT INTO {$db_prefix}membergroups
			(ID_GROUP, groupName, onlineColor, minPosts, stars)
		SELECT
			ID_GROUP + 1, membergroup, '', CASE ID_GROUP
				WHEN 3 THEN 0
				WHEN 4 THEN '$JrPostNum'
				WHEN 5 THEN '$FullPostNum'
				WHEN 6 THEN '$SrPostNum'
				WHEN 7 THEN '$GodPostNum'
			END, CONCAT(ID_GROUP - 2, '#star.gif')
		FROM {$db_prefix}old_membergroups
		WHERE ID_GROUP IN (3, 4, 5, 6, 7)");

	upgrade_query("
		INSERT INTO {$db_prefix}membergroups
			(ID_GROUP, groupName, onlineColor, minPosts, stars)
		SELECT ID_GROUP, membergroup, '', -1, ''
		FROM {$db_prefix}old_membergroups
		WHERE ID_GROUP > 8");

	upgrade_query("
		DROP TABLE IF EXISTS {$db_prefix}old_membergroups");

	$permissions = array(
		'view_mlist',
		'search_posts',
		'profile_view_own',
		'profile_view_any',
		'pm_read',
		'pm_send',
		'calendar_view',
		'view_stats',
		'who_view',
		'profile_identity_own',
		'profile_extra_own',
		'profile_remote_avatar',
		'profile_remove_own',
	);

	foreach ($permissions as $perm)
		upgrade_query("
			INSERT INTO {$db_prefix}permissions
				(ID_GROUP, permission)
			SELECT IF(ID_GROUP = 1, 0, ID_GROUP), '$perm'
			FROM {$db_prefix}membergroups
			WHERE ID_GROUP != 3
				AND minPosts = -1");

	$board_permissions = array(
		'remove_own',
		'lock_own',
		'mark_any_notify',
		'mark_notify',
		'modify_own',
		'poll_add_own',
		'poll_edit_own',
		'poll_lock_own',
		'poll_post',
		'poll_view',
		'poll_vote',
		'post_attachment',
		'post_new',
		'post_reply_any',
		'post_reply_own',
		'delete_own',
		'report_any',
		'send_topic',
		'view_attachments',
	);

	foreach ($board_permissions as $perm)
		upgrade_query("
			INSERT INTO {$db_prefix}board_permissions
				(ID_GROUP, permission)
			SELECT IF(ID_GROUP = 1, 0, ID_GROUP), '$perm'
			FROM {$db_prefix}membergroups
			WHERE minPosts = -1");
}
---}
---#

---# Converting "reserved_names"...
---{
$request = mysql_query("
	SELECT setting, value
	FROM {$db_prefix}reserved_names");
if ($request !== false)
{
	$words = array();
	$match_settings = array();
	while ($row = mysql_fetch_assoc($request))
	{
		if (substr($row['setting'], 0, 5) == 'match')
			$match_settings[$row['setting']] = $row['value'];
		else
			$words[] = $row['value'];
	}
	mysql_free_result($request);

	upgrade_query("
		INSERT IGNORE INTO {$db_prefix}settings
		VALUES ('reserveWord', '" . (int) @$match_settings['matchword'] . "'),
			('reserveCase', '" . (int) @$match_settings['matchcase'] . "'),
			('reserveUser', '" . (int) @$match_settings['matchuser'] . "'),
			('reserveName', '" . (int) @$match_settings['matchname'] . "'),
			('reserveNames', '" . implode("\n", $words) . "')");

	upgrade_query("
		DROP TABLE {$db_prefix}reserved_names");
}
---}
---#

---# Converting member's groups...
ALTER TABLE {$db_prefix}members
ADD COLUMN ID_POST_GROUP smallint(5) unsigned NOT NULL default '0',
ADD INDEX ID_POST_GROUP (ID_POST_GROUP);

---{
$request = upgrade_query("
	SELECT ID_GROUP, minPosts
	FROM {$db_prefix}membergroups
	WHERE minPosts != -1
	ORDER BY minPosts DESC");
$post_groups = array();
while ($row = mysql_fetch_assoc($request))
	$post_groups[$row['minPosts']] = $row['ID_GROUP'];
mysql_free_result($request);

$request = upgrade_query("
	SELECT ID_MEMBER, posts
	FROM {$db_prefix}members");
$mg_updates = array();
while ($row = mysql_fetch_assoc($request))
{
	$group = 4;
	foreach ($post_groups as $min_posts => $group_id)
		if ($row['posts'] > $min_posts)
		{
			$group = $group_id;
			break;
		}

	$mg_updates[$group][] = $row['ID_MEMBER'];
}
mysql_free_result($request);

foreach ($mg_updates as $group_to => $update_members)
	upgrade_query("
		UPDATE {$db_prefix}members
		SET ID_POST_GROUP = $group_to
		WHERE ID_MEMBER IN (" . implode(', ', $update_members) . ")
		LIMIT " . count($update_members));
---}
---#

/******************************************************************************/
--- Converting the calendar, notifications, and miscellaneous...
/******************************************************************************/

---# Converting censored words...
---{
if (!isset($modSettings['censor_vulgar']) || !isset($modSettings['censor_proper']))
{
	$request = upgrade_query("
		SELECT vulgar, proper
		FROM {$db_prefix}censor");
	$censor_vulgar = array();
	$censor_proper = array();
	while ($row = mysql_fetch_row($request))
	{
		$censor_vulgar[] = trim($row[0]);
		$censor_proper[] = trim($row[1]);
	}
	mysql_free_result($request);

	$modSettings['censor_vulgar'] = addslashes(implode("\n", $censor_vulgar));
	$modSettings['censor_proper'] = addslashes(implode("\n", $censor_proper));

	upgrade_query("
		INSERT IGNORE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('censor_vulgar', '$modSettings[censor_vulgar]'),
			('censor_proper', '$modSettings[censor_proper]')");

	upgrade_query("
		DROP TABLE IF EXISTS {$db_prefix}censor");
}
---}
---#

---# Converting topic notifications...
---{
$result = mysql_query("
	SELECT COUNT(*)
	FROM {$db_prefix}topics
	WHERE notifies != ''");
if ($result !== false)
{
	list ($numNotifies) = mysql_fetch_row($result);
	mysql_free_result($result);

	$_GET['t'] = (int) @$_GET['t'];

	while ($_GET['t'] < $numNotifies)
	{
		nextSubstep($substep);

		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}log_notify
				(ID_MEMBER, ID_TOPIC)
			SELECT mem.ID_MEMBER, t.ID_TOPIC
			FROM ({$db_prefix}topics AS t, {$db_prefix}members AS mem)
			WHERE FIND_IN_SET(mem.ID_MEMBER, t.notifies)
				AND t.notifies != ''
			LIMIT $_GET[t], 512");

		$_GET['t'] += 512;
	}
	unset($_GET['t']);
}
---}

ALTER TABLE {$db_prefix}topics
DROP notifies;
---#

---# Converting "banned"...
---{
$request = mysql_query("
	SELECT type, value
	FROM {$db_prefix}banned
	WHERE type = 'ip'");
if ($request !== false)
{
	$insertEntries = array();
	while ($row = mysql_fetch_assoc($request))
	{
		if (preg_match('~^\d{1,3}\.(\d{1,3}|\*)\.(\d{1,3}|\*)\.(\d{1,3}|\*)$~', $row['value']) == 0)
			continue;

		$ip_parts = ip2range($row['value']);
		$insertEntries[] = "('ip_ban', {$ip_parts[0]['low']}, {$ip_parts[0]['high']}, {$ip_parts[1]['low']}, {$ip_parts[1]['high']}, {$ip_parts[2]['low']}, {$ip_parts[2]['high']}, {$ip_parts[3]['low']}, {$ip_parts[3]['high']}, '', '', 0, " . time() . ", NULL, 'full_ban', '', 'Imported from YaBB SE')";
	}
	mysql_free_result($request);

	upgrade_query("
		CREATE TABLE IF NOT EXISTS {$db_prefix}banned2 (
			id_ban mediumint(8) unsigned NOT NULL auto_increment,
			ban_type varchar(30) NOT NULL default '',
			ip_low1 tinyint(3) unsigned NOT NULL default '0',
			ip_high1 tinyint(3) unsigned NOT NULL default '0',
			ip_low2 tinyint(3) unsigned NOT NULL default '0',
			ip_high2 tinyint(3) unsigned NOT NULL default '0',
			ip_low3 tinyint(3) unsigned NOT NULL default '0',
			ip_high3 tinyint(3) unsigned NOT NULL default '0',
			ip_low4 tinyint(3) unsigned NOT NULL default '0',
			ip_high4 tinyint(3) unsigned NOT NULL default '0',
			hostname tinytext NOT NULL default '',
			email_address tinytext NOT NULL default '',
			ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
			ban_time int(10) unsigned NOT NULL default '0',
			expire_time int(10) unsigned,
			restriction_type varchar(30) NOT NULL default '',
			reason tinytext NOT NULL default '',
			notes text NOT NULL default '',
			PRIMARY KEY (id_ban)
		) ENGINE=MyISAM");

	upgrade_query("
		INSERT INTO {$db_prefix}banned2
			(ban_type, ip_low1, ip_high1, ip_low2, ip_high2, ip_low3, ip_high3, ip_low4, ip_high4, hostname, email_address, ID_MEMBER, ban_time, expire_time, restriction_type, reason, notes)
		SELECT 'email_ban', 0, 0, 0, 0, 0, 0, 0, 0, '', value, 0, " . time() . ", NULL, 'full_ban', '', 'Imported from YaBB SE'
		FROM {$db_prefix}banned
		WHERE type = 'email'");

	upgrade_query("
		INSERT INTO {$db_prefix}banned2
			(ban_type, ip_low1, ip_high1, ip_low2, ip_high2, ip_low3, ip_high3, ip_low4, ip_high4, hostname, email_address, ID_MEMBER, ban_time, expire_time, restriction_type, reason, notes)
		SELECT 'user_ban', 0, 0, 0, 0, 0, 0, 0, 0, '', '', mem.ID_MEMBER, " . time() . ", NULL, 'full_ban', '', 'Imported from YaBB SE'
		FROM ({$db_prefix}banned AS ban, {$db_prefix}members AS mem)
		WHERE ban.type = 'username'
			AND mem.memberName = ban.value");

	upgrade_query("
		DROP TABLE {$db_prefix}banned");
	upgrade_query("
		RENAME TABLE {$db_prefix}banned2 TO {$db_prefix}banned");

	if (!empty($insertEntries))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}banned
				(ban_type, ip_low1, ip_high1, ip_low2, ip_high2, ip_low3, ip_high3, ip_low4, ip_high4, hostname, email_address, ID_MEMBER, ban_time, expire_time, restriction_type, reason, notes)
			VALUES " . implode(',', $insertEntries));
	}
}
---}
---#

---# Updating "log_banned"...
ALTER TABLE {$db_prefix}log_banned
CHANGE COLUMN logTime logTime int(10) unsigned NOT NULL default '0';
ALTER TABLE {$db_prefix}log_banned
ADD COLUMN id_ban_log mediumint(8) unsigned NOT NULL auto_increment PRIMARY KEY FIRST,
ADD COLUMN ID_MEMBER mediumint(8) unsigned NOT NULL default '0' AFTER id_ban_log,
ADD INDEX logTime (logTime);
---#

---# Updating columns on "calendar"...
ALTER TABLE {$db_prefix}calendar
DROP PRIMARY KEY,
CHANGE COLUMN id ID_EVENT smallint(5) unsigned NOT NULL auto_increment PRIMARY KEY,
CHANGE COLUMN id_board ID_BOARD smallint(5) unsigned NOT NULL default '0',
CHANGE COLUMN id_topic ID_TOPIC mediumint(8) unsigned NOT NULL default '0',
CHANGE COLUMN id_member ID_MEMBER mediumint(8) unsigned NOT NULL default '0';
ALTER TABLE {$db_prefix}calendar
CHANGE COLUMN title title varchar(48) NOT NULL default '';

ALTER TABLE {$db_prefix}calendar
ADD eventDate date NOT NULL default '0000-00-00';
---#

---# Updating indexes on "calendar"...
ALTER TABLE {$db_prefix}calendar
DROP INDEX idx_year_month;
ALTER TABLE {$db_prefix}calendar
DROP INDEX year;
---#

---# Updating data in "calendar"...
UPDATE IGNORE {$db_prefix}calendar
SET eventDate = CONCAT(year, '-', month + 1, '-', day);

ALTER TABLE {$db_prefix}calendar
DROP year,
DROP month,
DROP day,
ADD INDEX eventDate (eventDate);
---#

---# Updating structure on "calendar_holidays"...
CREATE TABLE IF NOT EXISTS {$db_prefix}calendar_holidays (
	ID_HOLIDAY smallint(5) unsigned NOT NULL auto_increment,
	eventDate date NOT NULL default '0000-00-00',
	title varchar(30) NOT NULL default '',
	PRIMARY KEY (ID_HOLIDAY),
	KEY eventDate (eventDate)
) ENGINE=MyISAM;
---#

---# Updating data in "calendar_holidays"...
---{
$result = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}calendar_holidays");
list ($size) = mysql_fetch_row($result);
mysql_free_result($result);

if (empty($size))
{
	upgrade_query("
		INSERT INTO {$db_prefix}calendar_holidays
			(eventDate, title)
		SELECT IF(year IS NULL, CONCAT('0000-', month, '-', day), CONCAT(year, '-', month, '-', day)), title
		FROM {$db_prefix}calendar_holiday");

	upgrade_query("
		INSERT INTO {$db_prefix}calendar_holidays
			(eventDate, title)
		VALUES ('0000-06-06', 'D-Day')");
}
---}

UPDATE {$db_prefix}calendar_holidays
SET title = 'New Year\'s'
WHERE title = 'New Years';
---#

/******************************************************************************/
--- Converting polls and choices...
/******************************************************************************/

---# Converting data to "poll_choices"...
INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 0, option1, votes1
FROM {$db_prefix}polls;

INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 1, option2, votes2
FROM {$db_prefix}polls;

INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 2, option3, votes3
FROM {$db_prefix}polls;

INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 3, option4, votes4
FROM {$db_prefix}polls;

INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 4, option5, votes5
FROM {$db_prefix}polls;

INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 5, option6, votes6
FROM {$db_prefix}polls;

INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 6, option7, votes7
FROM {$db_prefix}polls;

INSERT INTO {$db_prefix}poll_choices
	(ID_POLL, ID_CHOICE, label, votes)
SELECT ID_POLL, 7, option8, votes8
FROM {$db_prefix}polls;
---#

---# Converting data to "log_polls"...
---{
$query = mysql_query("
	SELECT ID_POLL, votedMemberIDs
	FROM {$db_prefix}polls");
if ($query !== false)
{
	$setStringLog = '';
	while ($row = mysql_fetch_assoc($query))
	{
		$members = explode(',', $row['votedMemberIDs']);
		foreach ($members as $member)
			if (is_numeric($member) && !empty($member))
				$setStringLog .= "
				($row[ID_POLL], $member, 0),";
	}

	if (!empty($setStringLog))
	{
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}log_polls
				(ID_POLL, ID_MEMBER, ID_CHOICE)
			VALUES " . substr($setStringLog, 0, -1));
	}
}
---}
---#

---# Updating "polls"...
ALTER TABLE {$db_prefix}polls
DROP option1, DROP option2, DROP option3, DROP option4, DROP option5, DROP option6, DROP option7, DROP option8,
DROP votes1, DROP votes2, DROP votes3, DROP votes4, DROP votes5, DROP votes6, DROP votes7, DROP votes8,
DROP votedMemberIDs,
DROP PRIMARY KEY,
CHANGE COLUMN ID_POLL ID_POLL mediumint(8) unsigned NOT NULL auto_increment PRIMARY KEY,
CHANGE COLUMN votingLocked votingLocked tinyint(1) NOT NULL default '0',
CHANGE COLUMN question question tinytext NOT NULL default '',
ADD maxVotes tinyint(4) unsigned NOT NULL default '1',
ADD expireTime int(10) unsigned NOT NULL default '0',
ADD hideResults tinyint(4) unsigned NOT NULL default '0';

ALTER TABLE {$db_prefix}polls
ADD ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
ADD posterName tinytext NOT NULL default '';
ALTER TABLE {$db_prefix}polls
ADD changeVote tinyint(4) unsigned NOT NULL default '0';
---#

---# Updating data in "polls"...
---{
$result = upgrade_query("
	SELECT p.ID_POLL, t.ID_MEMBER_STARTED
	FROM ({$db_prefix}topics AS t, {$db_prefix}messages AS m, {$db_prefix}polls AS p)
	WHERE m.ID_MSG = t.ID_FIRST_MSG
		AND p.ID_POLL = t.ID_POLL
		AND p.ID_MEMBER = 0
		AND t.ID_MEMBER_STARTED != 0");
while ($row = mysql_fetch_assoc($result))
{
	upgrade_query("
		UPDATE {$db_prefix}polls
		SET ID_MEMBER = $row[ID_MEMBER_STARTED]
		WHERE ID_POLL = $row[ID_POLL]
		LIMIT 1");
}
mysql_free_result($result);
---}
---#

/******************************************************************************/
--- Converting settings...
/******************************************************************************/

---# Updating news...
---{
if (!isset($modSettings['smfVersion']))
{
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('news', SUBSTRING('" . htmlspecialchars(stripslashes($modSettings['news']), ENT_QUOTES) . "', 1, 65534))");
}
---}
---#

---# Updating "themes"...
---{
convertSettingsToTheme();

$insertRows = '';
$request = upgrade_query("
	SELECT ID_THEME, IF(value = '2', 5, value) AS value
	FROM {$db_prefix}themes
	WHERE variable = 'display_recent_bar'");
while ($row = mysql_fetch_assoc($request))
	$insertRows .= "($row[ID_THEME], 'number_recent_posts', '$row[value]'),";
mysql_free_result($request);
if (!empty($insertRows))
{
	$insertRows = substr($insertRows, 0, -1);
	upgrade_query("
		INSERT IGNORE INTO {$db_prefix}themes
			(ID_THEME, variable, value)
		VALUES $insertRows");
}
---}
---#

---# Updating "settings"...
ALTER TABLE {$db_prefix}settings
DROP INDEX variable;

UPDATE IGNORE {$db_prefix}settings
SET variable = 'guest_hideContacts'
WHERE variable = 'guest_hideEmail'
LIMIT 1;
---#

---# Adding new settings (part 1)...
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('news', ''),
	('compactTopicPagesContiguous', '5'),
	('compactTopicPagesEnable', '1'),
	('enableStickyTopics', '1'),
	('todayMod', '1'),
	('karmaMode', '0'),
	('karmaTimeRestrictAdmins', '1'),
	('enablePreviousNext', '1'),
	('pollMode', '1'),
	('enableVBStyleLogin', '1'),
	('enableCompressedOutput', '1'),
	('karmaWaitTime', '1'),
	('karmaMinPosts', '0'),
	('karmaLabel', 'Karma:'),
	('karmaSmiteLabel', '[smite]'),
	('karmaApplaudLabel', '[applaud]'),
	('attachmentSizeLimit', '128'),
	('attachmentPostLimit', '192'),
	('attachmentNumPerPostLimit', '4'),
	('attachmentDirSizeLimit', '10240'),
	('attachmentUploadDir', '{$sboarddir}/attachments'),
	('attachmentExtensions', 'txt,doc,pdf,jpg,gif,mpg,png'),
	('attachmentCheckExtensions', '1'),
	('attachmentShowImages', '1'),
	('attachmentEnable', '1'),
	('attachmentEncryptFilenames', '1'),
	('censorIgnoreCase', '1'),
	('mostOnline', '1');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('mostOnlineToday', '1'),
	('mostDate', UNIX_TIMESTAMP()),
	('trackStats', '1'),
	('userLanguage', '1'),
	('titlesEnable', '1'),
	('topicSummaryPosts', '15'),
	('enableErrorLogging', '1'),
	('onlineEnable', '0'),
	('cal_holidaycolor', '000080'),
	('cal_bdaycolor', '920AC4'),
	('cal_eventcolor', '078907'),
	('cal_enabled', '0'),
	('cal_maxyear', '2010'),
	('cal_minyear', '2002'),
	('cal_daysaslink', '0'),
	('cal_defaultboard', ''),
	('cal_showeventsonindex', '0'),
	('cal_showbdaysonindex', '0'),
	('cal_showholidaysonindex', '0'),
	('cal_showweeknum', '0'),
	('cal_maxspan', '7'),
	('smtp_host', ''),
	('smtp_username', ''),
	('smtp_password', ''),
	('mail_type', '0'),
	('timeLoadPageEnable', '0'),
	('totalTopics', '1'),
	('totalMessages', '1'),
	('simpleSearch', '0'),
	('censor_vulgar', ''),
	('censor_proper', ''),
	('mostOnlineToday', '1'),
	('enablePostHTML', '0'),
	('theme_allow', '1'),
	('theme_default', '1'),
	('theme_guests', '1'),
	('enableEmbeddedFlash', '0'),
	('xmlnews_enable', '1'),
	('xmlnews_maxlen', '255'),
	('hotTopicPosts', '15'),
	('hotTopicVeryPosts', '25'),
	('allow_editDisplayName', '1'),
	('number_format', '1234.00'),
	('attachmentEncryptFilenames', '1'),
	('autoLinkUrls', '1');
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('avatar_allow_server_stored', '1'),
	('avatar_check_size', '0'),
	('avatar_action_too_large', 'option_user_resize'),
	('avatar_resize_upload', '1'),
	('avatar_download_png', '1'),
	('failed_login_threshold', '3'),
	('edit_wait_time', '90'),
	('autoFixDatabase', '1'),
	('autoOptDatabase', '7'),
	('autoOptMaxOnline', '0'),
	('autoOptLastOpt', '0'),
	('enableParticipation', '1'),
	('recycle_enable', '0'),
	('recycle_board', '0'),
	('banLastUpdated', '0'),
	('enableAllMessages', '0'),
	('fixLongWords', '0'),
	('knownThemes', '1,2'),
	('who_enabled', '1'),
	('lastActive', '15'),
	('allow_hideOnline', '1'),
	('guest_hideContacts', '0');
---#

---# Adding new settings (part 2)...
---{
upgrade_query("
	INSERT IGNORE INTO {$db_prefix}settings
		(variable, value)
	VALUES
		('registration_method', '" . (!empty($modSettings['registration_disabled']) ? 3 : (!empty($modSettings['approve_registration']) ? 2 : (!empty($GLOBALS['emailpassword']) || !empty($modSettings['send_validation']) ? 1 : 0))) . "'),
		('send_validation_onChange', '" . @$GLOBALS['emailnewpass'] . "'),
		('send_welcomeEmail', '" . @$GLOBALS['emailwelcome'] . "'),
		('allow_hideEmail', '" . @$GLOBALS['allow_hide_email'] . "'),
		('allow_guestAccess', '" . @$GLOBALS['guestaccess'] . "'),
		('time_format', '" . (!empty($GLOBALS['timeformatstring']) ? $GLOBALS['timeformatstring'] : '%B %d, %Y, %I:%M:%S %p') . "'),
		('enableBBC', '" . (!isset($GLOBALS['enable_ubbc']) ? 1 : $GLOBALS['enable_ubbc']) . "'),
		('max_messageLength', '" . (empty($GLOBALS['MaxMessLen']) ? 10000 : $GLOBALS['MaxMessLen']) . "'),
		('max_signatureLength', '" . @$GLOBALS['MaxSigLen'] . "'),
		('spamWaitTime', '" . @$GLOBALS['timeout'] . "'),
		('avatar_directory', '" . (isset($GLOBALS['facesdir']) ? fixRelativePath($GLOBALS['facesdir']) : fixRelativePath('./avatars')) . "'),
		('avatar_url', '" . @$GLOBALS['facesurl'] . "'),
		('avatar_max_height_external', '" . @$GLOBALS['userpic_height'] . "'),
		('avatar_max_width_external', '" . @$GLOBALS['userpic_width'] . "'),
		('avatar_max_height_upload', '" . @$GLOBALS['userpic_height'] . "'),
		('avatar_max_width_upload', '" . @$GLOBALS['userpic_width'] . "'),
		('defaultMaxMessages', '" . (empty($GLOBALS['maxmessagedisplay']) ? 15 : $GLOBALS['maxmessagedisplay']) . "'),
		('defaultMaxTopics', '" . (empty($GLOBALS['maxdisplay']) ? 20 : $GLOBALS['maxdisplay']) . "'),
		('defaultMaxMembers', '" . (empty($GLOBALS['MembersPerPage']) ? 20 : $GLOBALS['MembersPerPage']) . "'),
		('time_offset', '" . @$GLOBALS['timeoffset'] . "'),
		('cookieTime', '" . (empty($GLOBALS['Cookie_Length']) ? 60 : $GLOBALS['Cookie_Length']) . "'),
		('requireAgreement', '" . @$GLOBALS['RegAgree'] . "')");
---}

INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES
	('smileys_dir', '{$sboarddir}/Smileys'),
	('smileys_url', '{$boardurl}/Smileys'),
	('smiley_sets_known', 'default,classic'),
	('smiley_sets_names', 'Default\nClassic'),
	('smiley_sets_default', 'default'),
	('censorIgnoreCase', '1'),
	('cal_days_for_index', '7'),
	('unapprovedMembers', '0'),
	('default_personalText', ''),
	('attachmentPostLimit', '192'),
	('attachmentNumPerPostLimit', '4'),
	('package_make_backups', '1'),
	('databaseSession_loose', '1'),
	('databaseSession_lifetime', '2880'),
	('smtp_port', '25'),
	('search_cache_size', '50'),
	('search_results_per_page', '30'),
	('search_weight_frequency', '30'),
	('search_weight_age', '25'),
	('search_weight_length', '20'),
	('search_weight_subject', '15'),
	('search_weight_first_message', '10');

DELETE FROM {$db_prefix}settings
WHERE variable = 'agreement'
LIMIT 1;
---#

---# Converting settings to options...
---{
convertSettingsToOptions();
---}
---#

---# Updating statistics...
REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'latestMember', ID_MEMBER
FROM {$db_prefix}members
ORDER BY ID_MEMBER DESC
LIMIT 1;

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'latestRealName', IFNULL(realName, memberName)
FROM {$db_prefix}members
ORDER BY ID_MEMBER DESC
LIMIT 1;

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'maxMsgID', ID_MSG
FROM {$db_prefix}messages
ORDER BY ID_MSG DESC
LIMIT 1;

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'totalMembers', COUNT(*)
FROM {$db_prefix}members;

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'unapprovedMembers', COUNT(*)
FROM {$db_prefix}members
WHERE is_activated = 0
	AND validation_code = '';

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'totalMessages', COUNT(*)
FROM {$db_prefix}messages;

REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'totalTopics', COUNT(*)
FROM {$db_prefix}topics;

REPLACE INTO {$db_prefix}settings
	(variable, value)
VALUES ('cal_today_updated', '00000000');
---#