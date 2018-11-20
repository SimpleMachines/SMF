/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Updating and creating indexes...
/******************************************************************************/

---# Updating indexes on "messages"...
---{
$request = upgrade_query("
	SHOW KEYS
	FROM {$db_prefix}messages");
$found = false;
while ($row = smf_mysql_fetch_assoc($request))
	$found |= $row['Key_name'] == 'ID_BOARD' && $row['Column_name'] == 'ID_MSG';
smf_mysql_free_result($request);

if (!$found)
	upgrade_query("
		ALTER TABLE {$db_prefix}messages
		DROP INDEX ID_BOARD");
---}
---#

---# Updating table indexes...
---{
$_GET['mess_ind'] = isset($_GET['mess_ind']) ? (int) $_GET['mess_ind'] : 0;
$step_progress['name'] = 'Updating table indexes';
$step_progress['current'] = $_GET['mess_ind'];
$custom_warning = 'On a very large board these indexes may take a few minutes to create.';

$index_changes = array(
	array(
		'table' => 'log_errors',
		'type' => 'index',
		'method' => 'add',
		'name' => 'ID_MEMBER',
		'target_columns' => array('ID_MEMBER'),
		'text' => 'ADD INDEX ID_MEMBER (ID_MEMBER)',
	),
	array(
		'table' => 'log_errors',
		'type' => 'index',
		'method' => 'add',
		'name' => 'IP',
		'target_columns' => array('IP'),
		'text' => 'ADD INDEX IP (IP(15))',
	),
	array(
		'table' => 'log_online',
		'type' => 'index',
		'method' => 'add',
		'name' => 'logTime',
		'target_columns' => array('logTime'),
		'text' => 'ADD INDEX logTime (logTime)',
	),
	array(
		'table' => 'log_online',
		'type' => 'index',
		'method' => 'remove',
		'name' => 'online',
		'target_columns' => array('online'),
		'text' => 'DROP INDEX online',
	),
	array(
		'table' => 'smileys',
		'type' => 'index',
		'method' => 'remove',
		'name' => 'smileyOrder',
		'target_columns' => array('smileyOrder'),
		'text' => 'DROP INDEX smileyOrder',
	),
	array(
		'table' => 'boards',
		'type' => 'index',
		'method' => 'add',
		'name' => 'ID_PARENT',
		'target_columns' => array('ID_PARENT'),
		'text' => 'ADD INDEX ID_PARENT (ID_PARENT)',
	),
	array(
		'table' => 'boards',
		'type' => 'index',
		'method' => 'remove',
		'name' => 'children',
		'target_columns' => array('children'),
		'text' => 'DROP INDEX children',
	),
	array(
		'table' => 'boards',
		'type' => 'index',
		'method' => 'remove',
		'name' => 'boardOrder',
		'target_columns' => array('boardOrder'),
		'text' => 'DROP INDEX boardOrder',
	),
	array(
		'table' => 'categories',
		'type' => 'index',
		'method' => 'remove',
		'name' => 'catOrder',
		'target_columns' => array('catOrder'),
		'text' => 'DROP INDEX catOrder',
	),
	array(
		'table' => 'messages',
		'type' => 'index',
		'method' => 'add',
		'name' => 'ID_TOPIC',
		'target_columns' => array('ID_TOPIC'),
		'text' => 'ADD INDEX ID_TOPIC (ID_TOPIC)',
	),
	array(
		'table' => 'messages',
		'type' => 'index',
		'method' => 'remove',
		'name' => 'ID_MEMBER',
		'target_columns' => array('ID_MEMBER'),
		'text' => 'DROP INDEX ID_MEMBER',
	),
	array(
		'table' => 'messages',
		'type' => 'index',
		'method' => 'add',
		'name' => 'ID_BOARD',
		'target_columns' => array('ID_BOARD', 'ID_MSG'),
		'text' => 'ADD UNIQUE ID_BOARD (ID_BOARD, ID_MSG)',
	),
	array(
		'table' => 'messages',
		'type' => 'index',
		'method' => 'add',
		'name' => 'ID_MEMBER',
		'target_columns' => array('ID_MEMBER', 'ID_MSG'),
		'text' => 'ADD UNIQUE ID_MEMBER (ID_MEMBER, ID_MSG)',
	),
	array(
		'table' => 'messages',
		'type' => 'index',
		'method' => 'add',
		'name' => 'showPosts',
		'target_columns' => array('ID_MEMBER', 'ID_BOARD'),
		'text' => 'ADD INDEX showPosts (ID_MEMBER, ID_BOARD)',
	),
);

$step_progress['total'] = count($index_changes);

// Now we loop through the changes and work out where the hell we are.
foreach ($index_changes as $ind => $change)
{
	// Already done it?
	if ($_GET['mess_ind'] > $ind)
		continue;

	// Make the index, with all the protection and all.
	protected_alter($change, $substep);

	// Store this for the next table.
	$_GET['mess_ind']++;
	$step_progress['current'] = $_GET['mess_ind'];
}

// Clean up.
unset($_GET['mess_ind']);
---}
---#

---# Reordering boards and categories...
ALTER TABLE {$db_prefix}categories
ORDER BY catOrder;

ALTER TABLE {$db_prefix}boards
ORDER BY boardOrder;
---#

---# Updating indexes and data on "smileys"...
ALTER TABLE {$db_prefix}smileys
CHANGE COLUMN smileyOrder smileyOrder smallint(5) unsigned NOT NULL default '0';

UPDATE {$db_prefix}smileys
SET filename = 'embarrassed.gif'
WHERE filename = 'embarassed.gif';
---#

---# Updating indexes on "log_boards"...
ALTER TABLE {$db_prefix}log_boards
DROP PRIMARY KEY,
ADD PRIMARY KEY (ID_MEMBER, ID_BOARD);
---#

---# Updating indexes on "log_mark_read"...
ALTER TABLE {$db_prefix}log_mark_read
DROP PRIMARY KEY,
ADD PRIMARY KEY (ID_MEMBER, ID_BOARD);
---#

---# Updating indexes on "themes"...
ALTER TABLE {$db_prefix}themes
DROP PRIMARY KEY,
ADD PRIMARY KEY (ID_THEME, ID_MEMBER, variable(30)),
ADD INDEX ID_MEMBER (ID_MEMBER);
---#

/******************************************************************************/
--- Reorganizing configuration settings...
/******************************************************************************/

---# Updating data in "settings"...
REPLACE INTO {$db_prefix}settings
	(variable, value)
SELECT 'totalMembers', COUNT(*)
FROM {$db_prefix}members;

UPDATE {$db_prefix}settings
SET variable = 'notify_new_registration'
WHERE variable = 'notify_on_new_registration'
LIMIT 1;

UPDATE IGNORE {$db_prefix}settings
SET variable = 'max_image_width'
WHERE variable = 'maxwidth'
LIMIT 1;

UPDATE IGNORE {$db_prefix}settings
SET variable = 'max_image_height'
WHERE variable = 'maxheight'
LIMIT 1;

UPDATE {$db_prefix}settings
SET value = IF(value = 'sendmail' OR value = '0', '0', '1')
WHERE variable = 'mail_type'
LIMIT 1;

UPDATE IGNORE {$db_prefix}settings
SET variable = 'search_method'
WHERE variable = 'search_match_complete_words'
LIMIT 1;

UPDATE IGNORE {$db_prefix}settings
SET variable = 'allow_disableAnnounce'
WHERE variable = 'notifyAnncmnts_UserDisable'
LIMIT 1;
---#

---# Adding new settings...
INSERT IGNORE INTO {$db_prefix}settings
	(variable, value)
VALUES ('edit_disable_time', '0'),
	('oldTopicDays', '120'),
	('cal_showeventsoncalendar', '1'),
	('cal_showbdaysoncalendar', '1'),
	('cal_showholidaysoncalendar', '1'),
	('allow_disableAnnounce', '1'),
	('attachmentThumbnails', '1'),
	('attachmentThumbWidth', '150'),
	('attachmentThumbHeight', '150'),
	('max_pm_recipients', '10');

---{
if (@$modSettings['smfVersion'] < '1.1')
{
	// Hopefully 90 days is enough?
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES ('disableHashTime', " . (time() + 7776000) . ")");
}

if (isset($modSettings['smfVersion']) && $modSettings['smfVersion'] <= '1.1 Beta 4')
{
	// Enable the buddy list for those used to it.
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES ('enable_buddylist', '1')");
}
---}
---#

---# Adding PM spam protection settings.
---{
if (empty($modSettings['pm_spam_settings']))
{
	if (isset($modSettings['max_pm_recipients']))
		$modSettings['pm_spam_settings'] = (int) $modSettings['max_pm_recipients'] . ',5,20';
	else
		$modSettings['pm_spam_settings'] = '10,5,20';

	upgrade_query("
		INSERT IGNORE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('pm_spam_settings', '$modSettings[pm_spam_settings]')");
}
upgrade_query("
	DELETE FROM {$db_prefix}settings
	WHERE variable = 'max_pm_recipients'");
---}
---#

---# Cleaning old values from "settings"...
DELETE FROM {$db_prefix}settings
WHERE variable IN ('modlog_enabled', 'localCookies', 'globalCookies', 'send_welcomeEmail', 'search_method', 'notify_new_registration', 'removeNestedQuotes', 'smiley_enable', 'smiley_sets_enable')
	AND value = '0';

DELETE FROM {$db_prefix}settings
WHERE variable IN ('allow_guestAccess', 'userLanguage', 'allow_editDisplayName', 'allow_hideOnline', 'allow_hideEmail', 'guest_hideContacts', 'titlesEnable', 'search_match_complete_words')
	AND value = '0';

DELETE FROM {$db_prefix}settings
WHERE variable IN ('cal_allowspan', 'hitStats', 'queryless_urls', 'disableHostnameLookup', 'messageIcons_enable', 'disallow_sendBody', 'censorWholeWord')
	AND value = '0';

DELETE FROM {$db_prefix}settings
WHERE variable IN (
	'totalMessag',
	'redirectMetaRefresh',
	'memberCount',
	'cal_today_u',
	'approve_registration',
	'registration_disabled',
	'requireRegistrationVerification',
	'returnToPost',
	'send_validation',
	'search_max_cached_results',
	'disableTemporaryTables',
	'search_cache_size',
	'enableReportToMod'
);
---#

---# Encoding SMTP password...
---{
// Can't do this more than once, we just can't...
if ((!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] <= '1.1 RC1') && empty($modSettings['dont_repeat_smtp']))
{
	if (!empty($modSettings['smtp_password']))
	{
		upgrade_query("
			UPDATE {$db_prefix}settings
			SET value = '" . base64_encode($modSettings['smtp_password']) . "'
			WHERE variable = 'smtp_password'");
	}
	// Don't let this run twice!
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('dont_repeat_smtp', '1')");
}

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

/******************************************************************************/
--- Cleaning up after old themes...
/******************************************************************************/

---# Checking for "classic" and removing it if necessary...
---{
// Do they have "classic" installed?
if (file_exists($GLOBALS['boarddir'] . '/Themes/classic'))
{
	$classic_dir = $GLOBALS['boarddir'] . '/Themes/classic';
	$theme_request = upgrade_query("
		SELECT ID_THEME
		FROM {$db_prefix}themes
		WHERE variable = 'theme_dir'
			AND value ='$classic_dir'");

	// Don't do anything if this theme is already uninstalled
	if (smf_mysql_num_rows($theme_request) == 1)
	{
		$id_theme = mysql_result($theme_request, 0);
		mysql_free_result($theme_request);

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
			WHERE ID_THEME = $id_theme");

		// Set any members or boards using this theme to the default
		upgrade_query("
			UPDATE {$db_prefix}members
			SET ID_THEME = 0
			WHERE ID_THEME = $id_theme");

		upgrade_query("
			UPDATE {$db_prefix}boards
			SET ID_THEME = 0
			WHERE ID_THEME = $id_theme");

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
--- Adding and updating member data...
/******************************************************************************/

---# Renaming personal message tables...
RENAME TABLE {$db_prefix}instant_messages
TO {$db_prefix}personal_messages;

RENAME TABLE {$db_prefix}im_recipients
TO {$db_prefix}pm_recipients;
---#

---# Updating indexes on "pm_recipients"...
ALTER TABLE {$db_prefix}pm_recipients
DROP INDEX ID_MEMBER,
ADD UNIQUE ID_MEMBER (ID_MEMBER, deleted, ID_PM);
---#

---# Updating columns on "pm_recipients"...
ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN labels varchar(60) NOT NULL default '-1';

ALTER TABLE {$db_prefix}pm_recipients
CHANGE COLUMN labels labels varchar(60) NOT NULL default '-1';

UPDATE {$db_prefix}pm_recipients
SET labels = '-1'
WHERE labels NOT RLIKE '[0-9,\-]' OR labels = '';
---#

---# Updating columns on "members"...
ALTER TABLE {$db_prefix}members
ADD COLUMN messageLabels text NOT NULL,
ADD COLUMN buddy_list tinytext NOT NULL,
ADD COLUMN notifySendBody tinyint(4) NOT NULL default '0',
ADD COLUMN notifyTypes tinyint(4) NOT NULL default '2',
CHANGE COLUMN im_ignore_list pm_ignore_list tinytext NOT NULL,
CHANGE COLUMN im_email_notify pm_email_notify tinyint(4) NOT NULL default '0';
---#

---# Updating columns on "members" - part 2...
ALTER TABLE {$db_prefix}members
CHANGE COLUMN secretAnswer secretAnswer varchar(64) NOT NULL default '';

ALTER TABLE {$db_prefix}members
ADD COLUMN memberIP2 tinytext NOT NULL;
---#

---# Updating member approval...
---{
// Although it *shouldn't* matter, best to do it just once to be sure.
if (@$modSettings['smfVersion'] < '1.1')
{
	upgrade_query("
		UPDATE {$db_prefix}members
		SET is_activated = 3
		WHERE validation_code = ''
			AND is_activated = 0");
}
---}
---#

/******************************************************************************/
--- Updating holidays and calendar...
/******************************************************************************/

---# Adding new holidays...
---{
$result = upgrade_query("
	SELECT ID_HOLIDAY
	FROM {$db_prefix}calendar_holidays
	WHERE YEAR(eventDate) > 2010
	LIMIT 1");
$do_it = smf_mysql_num_rows($result) == 0;
smf_mysql_free_result($result);

if ($do_it)
{
	upgrade_query("
		INSERT INTO {$db_prefix}calendar_holidays
			(title, eventDate)
		VALUES
			('Mother\\'s Day', '2011-05-08'),
			('Mother\\'s Day', '2012-05-13'),
			('Mother\\'s Day', '2013-05-12'),
			('Mother\\'s Day', '2014-05-11'),
			('Mother\\'s Day', '2015-05-10'),
			('Mother\\'s Day', '2016-05-08'),
			('Mother\\'s Day', '2017-05-14'),
			('Mother\\'s Day', '2018-05-13'),
			('Mother\\'s Day', '2019-05-12'),
			('Mother\\'s Day', '2020-05-10'),
			('Father\\'s Day', '2011-06-19'),
			('Father\\'s Day', '2012-06-17'),
			('Father\\'s Day', '2013-06-16'),
			('Father\\'s Day', '2014-06-15'),
			('Father\\'s Day', '2015-06-21'),
			('Father\\'s Day', '2016-06-19'),
			('Father\\'s Day', '2017-06-18'),
			('Father\\'s Day', '2018-06-17'),
			('Father\\'s Day', '2019-06-16'),
			('Father\\'s Day', '2020-06-21'),
			('Summer Solstice', '2011-06-21'),
			('Summer Solstice', '2012-06-20'),
			('Summer Solstice', '2013-06-21'),
			('Summer Solstice', '2014-06-21'),
			('Summer Solstice', '2015-06-21'),
			('Summer Solstice', '2016-06-20'),
			('Summer Solstice', '2017-06-20'),
			('Summer Solstice', '2018-06-21'),
			('Summer Solstice', '2019-06-21'),
			('Summer Solstice', '2020-06-20'),
			('Vernal Equinox', '2011-03-20'),
			('Vernal Equinox', '2012-03-20'),
			('Vernal Equinox', '2013-03-20'),
			('Vernal Equinox', '2014-03-20'),
			('Vernal Equinox', '2015-03-20'),
			('Vernal Equinox', '2016-03-19'),
			('Vernal Equinox', '2017-03-20'),
			('Vernal Equinox', '2018-03-20'),
			('Vernal Equinox', '2019-03-20'),
			('Vernal Equinox', '2020-03-19'),
			('Winter Solstice', '2011-12-22'),
			('Winter Solstice', '2012-12-21'),
			('Winter Solstice', '2013-12-21'),
			('Winter Solstice', '2014-12-21'),
			('Winter Solstice', '2015-12-21'),
			('Winter Solstice', '2016-12-21'),
			('Winter Solstice', '2017-12-21'),
			('Winter Solstice', '2018-12-21'),
			('Winter Solstice', '2019-12-21'),
			('Winter Solstice', '2020-12-21'),
			('Autumnal Equinox', '2011-09-23'),
			('Autumnal Equinox', '2012-09-22'),
			('Autumnal Equinox', '2013-09-22'),
			('Autumnal Equinox', '2014-09-22'),
			('Autumnal Equinox', '2015-09-23'),
			('Autumnal Equinox', '2016-09-22'),
			('Autumnal Equinox', '2017-09-22'),
			('Autumnal Equinox', '2018-09-22'),
			('Autumnal Equinox', '2019-09-23'),
			('Autumnal Equinox', '2020-09-22'),
			('Thanksgiving', '2011-11-24'),
			('Thanksgiving', '2012-11-22'),
			('Thanksgiving', '2013-11-21'),
			('Thanksgiving', '2014-11-20'),
			('Thanksgiving', '2015-11-26'),
			('Thanksgiving', '2016-11-24'),
			('Thanksgiving', '2017-11-23'),
			('Thanksgiving', '2018-11-22'),
			('Thanksgiving', '2019-11-21'),
			('Thanksgiving', '2020-11-26'),
			('Memorial Day', '2011-05-30'),
			('Memorial Day', '2012-05-28'),
			('Memorial Day', '2013-05-27'),
			('Memorial Day', '2014-05-26'),
			('Memorial Day', '2015-05-25'),
			('Memorial Day', '2016-05-30'),
			('Memorial Day', '2017-05-29'),
			('Memorial Day', '2018-05-28'),
			('Memorial Day', '2019-05-27'),
			('Memorial Day', '2020-05-25'),
			('Labor Day', '2011-09-05'),
			('Labor Day', '2012-09-03'),
			('Labor Day', '2013-09-09'),
			('Labor Day', '2014-09-08'),
			('Labor Day', '2015-09-07'),
			('Labor Day', '2016-09-05'),
			('Labor Day', '2017-09-04'),
			('Labor Day', '2018-09-03'),
			('Labor Day', '2019-09-09'),
			('Labor Day', '2020-09-07')");
}
---}
---#

---# Updating event start and end dates...
ALTER TABLE {$db_prefix}calendar
DROP INDEX eventDate;

ALTER TABLE {$db_prefix}calendar
CHANGE COLUMN eventDate startDate date NOT NULL default '0001-01-01';

ALTER TABLE {$db_prefix}calendar
CHANGE COLUMN startDate startDate date NOT NULL default '0001-01-01';

UPDATE {$db_prefix}calendar
SET startDate = '0001-01-01'
WHERE startDate = '0000-00-00';

ALTER TABLE {$db_prefix}calendar
ADD COLUMN endDate date NOT NULL default '0001-01-01';

ALTER TABLE {$db_prefix}calendar
CHANGE COLUMN endDate endDate date NOT NULL default '0001-01-01';

UPDATE {$db_prefix}calendar
SET endDate = startDate
WHERE endDate = '0001-01-01'
	OR endDate = '0000-00-00';

ALTER TABLE {$db_prefix}calendar
ADD INDEX startDate (startDate),
ADD INDEX endDate (endDate);

ALTER TABLE {$db_prefix}calendar
DROP INDEX ID_TOPIC;

ALTER TABLE {$db_prefix}calendar
ADD INDEX topic (ID_TOPIC, ID_MEMBER);

ALTER TABLE {$db_prefix}calendar_holidays
CHANGE COLUMN eventDate eventDate date NOT NULL default '0001-01-01';

UPDATE {$db_prefix}calendar_holidays
SET eventDate = '0001-01-01'
WHERE eventDate = '0000-00-00';

UPDATE {$db_prefix}calendar_holidays
SET eventDate = CONCAT('0004-', MONTH(eventDate), '-', DAYOFMONTH(eventDate))
WHERE YEAR(eventDate) = 0;
---#

---# Converting other date columns...
ALTER TABLE {$db_prefix}log_activity
CHANGE COLUMN startDate date date NOT NULL default '0001-01-01';

ALTER TABLE {$db_prefix}log_activity
CHANGE COLUMN date date date NOT NULL default '0001-01-01';

UPDATE {$db_prefix}log_activity
SET date = '0001-01-01'
WHERE date = '0000-00-00';

ALTER TABLE {$db_prefix}members
CHANGE COLUMN birthdate birthdate date NOT NULL default '0001-01-01';

UPDATE {$db_prefix}members
SET birthdate = '0001-01-01'
WHERE birthdate = '0000-00-00';

UPDATE {$db_prefix}members
SET birthdate = CONCAT('0004-', MONTH(birthdate), '-', DAYOFMONTH(birthdate))
WHERE YEAR(birthdate) = 0;
---#

/******************************************************************************/
--- Adding custom message icons...
/******************************************************************************/

---# Checking for an old table...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}message_icons");
$test = false;
while ($request && $row = smf_mysql_fetch_row($request))
	$test |= $row[0] == 'Name';
if ($request)
	smf_mysql_free_result($request);

if ($test)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}message_icons
		DROP PRIMARY KEY,
		CHANGE COLUMN id_icon id_icon smallint(5) unsigned NOT NULL auto_increment PRIMARY KEY,
		CHANGE COLUMN Name filename varchar(80) NOT NULL default '',
		CHANGE COLUMN Description title varchar(80) NOT NULL default '',
		CHANGE COLUMN ID_BOARD ID_BOARD mediumint(8) unsigned NOT NULL default '0',
		DROP INDEX id_icon,
		ADD COLUMN iconOrder smallint(5) unsigned NOT NULL default '0'");
}
---}
---#

---# Creating "message_icons"...
CREATE TABLE IF NOT EXISTS {$db_prefix}message_icons (
	id_icon smallint(5) unsigned NOT NULL auto_increment,
	title varchar(80) NOT NULL default '',
	filename varchar(80) NOT NULL default '',
	ID_BOARD mediumint(8) unsigned NOT NULL default 0,
	iconOrder smallint(5) unsigned NOT NULL default 0,
	PRIMARY KEY (id_icon),
	KEY ID_BOARD (ID_BOARD)
) ENGINE=MyISAM;
---#

---# Inserting "message_icons"...
---{
// We do not want to do this twice!
if (@$modSettings['smfVersion'] < '1.1')
{
	upgrade_query("
		INSERT INTO {$db_prefix}message_icons
			(filename, title, iconOrder)
		VALUES ('xx', 'Standard', '0'),
			('thumbup', 'Thumb Up', '1'),
			('thumbdown', 'Thumb Down', '2'),
			('exclamation', 'Exclamation point', '3'),
			('question', 'Question mark', '4'),
			('lamp', 'Lamp', '5'),
			('smiley', 'Smiley', '6'),
			('angry', 'Angry', '7'),
			('cheesy', 'Cheesy', '8'),
			('grin', 'Grin', '9'),
			('sad', 'Sad', '10'),
			('wink', 'Wink', '11')");
}
---}
---#

/******************************************************************************/
--- Adding package servers...
/******************************************************************************/

---# Creating "package_servers"...
CREATE TABLE IF NOT EXISTS {$db_prefix}package_servers (
	id_server smallint(5) unsigned NOT NULL auto_increment,
	name tinytext NOT NULL,
	url tinytext NOT NULL,
	PRIMARY KEY (id_server)
) ENGINE=MyISAM;
---#

---# Inserting "package_servers"...
INSERT IGNORE INTO {$db_prefix}package_servers
	(id_server, name, url)
VALUES
	(1, 'Simple Machines Third-party Mod Site', 'http://mods.simplemachines.org');
---#

/******************************************************************************/
--- Cleaning up database...
/******************************************************************************/

---# Updating flood control log...
ALTER IGNORE TABLE {$db_prefix}log_floodcontrol
CHANGE COLUMN ip ip char(16) NOT NULL default '                ';

ALTER TABLE {$db_prefix}log_floodcontrol
DROP INDEX logTime;
---#

---# Updating ip address storage...
ALTER IGNORE TABLE {$db_prefix}log_actions
CHANGE COLUMN IP ip char(16) NOT NULL default '                ';

ALTER IGNORE TABLE {$db_prefix}log_banned
CHANGE COLUMN IP ip char(16) NOT NULL default '                ';

ALTER IGNORE TABLE {$db_prefix}log_banned
DROP COLUMN ban_ids;

ALTER IGNORE TABLE {$db_prefix}log_errors
DROP INDEX IP,
CHANGE COLUMN IP ip char(16) NOT NULL default '                ',
ADD INDEX ip (ip(16));
---#

---# Converting "log_online"...
DROP TABLE IF EXISTS {$db_prefix}log_online;
CREATE TABLE {$db_prefix}log_online (
	session char(32) NOT NULL default '                                ',
	logTime timestamp /*!40102 NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP */,
	ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
	ip int(10) unsigned NOT NULL default '0',
	url text NOT NULL,
	PRIMARY KEY (session),
	KEY online (logTime, ID_MEMBER),
	KEY ID_MEMBER (ID_MEMBER)
) ENGINE=MyISAM;
---#

---# Updating poll column sizes...
ALTER TABLE {$db_prefix}polls
CHANGE COLUMN maxVotes maxVotes tinyint(3) unsigned NOT NULL default '1',
CHANGE COLUMN hideResults hideResults tinyint(3) unsigned NOT NULL default '0',
CHANGE COLUMN changeVote changeVote tinyint(3) unsigned NOT NULL default '0';

ALTER TABLE {$db_prefix}poll_choices
CHANGE COLUMN ID_CHOICE ID_CHOICE tinyint(3) unsigned NOT NULL default '0';

ALTER TABLE {$db_prefix}log_polls
CHANGE COLUMN ID_CHOICE ID_CHOICE tinyint(3) unsigned NOT NULL default '0';
---#

---# Updating attachments table...
ALTER TABLE {$db_prefix}attachments
DROP PRIMARY KEY,
CHANGE COLUMN ID_ATTACH ID_ATTACH int(10) unsigned NOT NULL auto_increment PRIMARY KEY;
---#

---# Updating boards and topics...
ALTER TABLE {$db_prefix}topics
CHANGE COLUMN numReplies numReplies int(10) unsigned NOT NULL default 0,
CHANGE COLUMN numViews numViews int(10) unsigned NOT NULL default 0;
---#

---# Updating members...
ALTER TABLE {$db_prefix}members
CHANGE COLUMN lastLogin lastLogin int(10) unsigned NOT NULL default 0;
---#

---# Recounting member pm totals (step 1)...
---{
$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}members");
list ($totalMembers) = smf_mysql_fetch_row($request);
smf_mysql_free_result($request);

$_GET['m'] = isset($_GET['m']) ? (int) $_GET['m'] : 0;

while ($_GET['m'] < $totalMembers)
{
	nextSubstep($substep);

	$mrequest = upgrade_query("
		SELECT mem.ID_MEMBER, COUNT(pmr.ID_PM) AS instantMessages_real, mem.instantMessages
		FROM {$db_prefix}members AS mem
			LEFT JOIN {$db_prefix}pm_recipients AS pmr ON (pmr.ID_MEMBER = mem.ID_MEMBER AND pmr.deleted = 0)
		WHERE mem.ID_MEMBER > $_GET[m]
			AND mem.ID_MEMBER <= $_GET[m] + 128
		GROUP BY mem.ID_MEMBER, mem.instantMessages
		HAVING instantMessages_real != instantMessages
		LIMIT 256");
	while ($row = smf_mysql_fetch_assoc($mrequest))
	{
		upgrade_query("
			UPDATE {$db_prefix}members
			SET instantMessages = $row[instantMessages_real]
			WHERE ID_MEMBER = $row[ID_MEMBER]
			LIMIT 1");
	}

	$_GET['m'] += 128;
}
unset($_GET['m']);
---}
---#

---# Recounting member pm totals (step 2)...
---{
$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}members");
list ($totalMembers) = smf_mysql_fetch_row($request);
smf_mysql_free_result($request);

$_GET['m'] = isset($_GET['m']) ? (int) $_GET['m'] : 0;

while ($_GET['m'] < $totalMembers)
{
	nextSubstep($substep);

	$mrequest = upgrade_query("
		SELECT mem.ID_MEMBER, COUNT(pmr.ID_PM) AS unreadMessages_real, mem.unreadMessages
		FROM {$db_prefix}members AS mem
			LEFT JOIN {$db_prefix}pm_recipients AS pmr ON (pmr.ID_MEMBER = mem.ID_MEMBER AND pmr.deleted = 0 AND pmr.is_read = 0)
		WHERE mem.ID_MEMBER > $_GET[m]
			AND mem.ID_MEMBER <= $_GET[m] + 128
		GROUP BY mem.ID_MEMBER, mem.unreadMessages
		HAVING unreadMessages_real != unreadMessages
		LIMIT 256");
	while ($row = smf_mysql_fetch_assoc($mrequest))
	{
		upgrade_query("
			UPDATE {$db_prefix}members
			SET unreadMessages = $row[unreadMessages_real]
			WHERE ID_MEMBER = $row[ID_MEMBER]
			LIMIT 1");
	}

	$_GET['m'] += 128;
}
unset($_GET['m']);
---}
---#

/******************************************************************************/
--- Converting avatar permissions...
/******************************************************************************/

---# Converting server stored setting...
---{
if (!empty($modSettings['avatar_allow_server_stored']))
{
	// Create permissions for existing membergroups.
	upgrade_query("
		INSERT INTO {$db_prefix}permissions
			(ID_GROUP, permission)
		SELECT IF(ID_GROUP = 1, 0, ID_GROUP), 'profile_server_avatar'
		FROM {$db_prefix}membergroups
		WHERE ID_GROUP != 3
			AND minPosts = -1");
}
---}
---#

---# Converting avatar upload setting...
---{
// Do the same, but for uploading avatars.
if (!empty($modSettings['avatar_allow_upload']))
{
	// Put in these permissions
	upgrade_query("
		INSERT INTO {$db_prefix}permissions
			(ID_GROUP, permission)
		SELECT IF(ID_GROUP = 1, 0, ID_GROUP), 'profile_upload_avatar'
		FROM {$db_prefix}membergroups
		WHERE ID_GROUP != 3
			AND minPosts = -1");
}
---}
---#

/******************************************************************************/
--- Adjusting uploadable avatars...
/******************************************************************************/

---# Updating attachments...
ALTER TABLE {$db_prefix}attachments
CHANGE COLUMN ID_MEMBER ID_MEMBER mediumint(8) unsigned NOT NULL default '0';
---#

---# Updating settings...
DELETE FROM {$db_prefix}settings
WHERE variable IN ('avatar_allow_external_url', 'avatar_check_size', 'avatar_allow_upload', 'avatar_allow_server_stored');
---#

/******************************************************************************/
--- Updating thumbnails...
/******************************************************************************/

---# Registering thumbs...
---{
// Checkout the current structure of the attachment table.
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}attachments");
$has_customAvatarDir_column = false;
$has_attachmentType_column = false;
while ($row = smf_mysql_fetch_assoc($request))
{
	$has_customAvatarDir_column |= $row['Field'] == 'customAvatarDir';
	$has_attachmentType_column |= $row['Field'] == 'attachmentType';
}
smf_mysql_free_result($request);

// Post SMF 1.1 Beta 1.
if ($has_customAvatarDir_column)
	$request = upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		CHANGE COLUMN customAvatarDir attachmentType tinyint(3) unsigned NOT NULL default '0'");
// Pre SMF 1.1.
elseif (!$has_attachmentType_column)
	$request = upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		ADD COLUMN attachmentType tinyint(3) unsigned NOT NULL default '0'");

if (!$has_attachmentType_column)
{
	$request = upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		ADD COLUMN id_thumb int(10) unsigned NOT NULL default '0' AFTER ID_ATTACH,
		ADD COLUMN width mediumint(8) unsigned NOT NULL default '0',
		ADD COLUMN height mediumint(8) unsigned NOT NULL default '0'");

	// Get a list of attachments currently stored in the database.
	$request = upgrade_query("
		SELECT ID_ATTACH, ID_MSG, filename
		FROM {$db_prefix}attachments");
	$filenames = array();
	$encrypted_filenames = array();
	$ID_MSG = array();
	while ($row = smf_mysql_fetch_assoc($request))
	{
		$clean_name = strtr($row['filename'], 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
		$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));
		$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
		$enc_name = $row['ID_ATTACH'] . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
		$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

		if (file_exists($modSettings['attachmentUploadDir'] . '/' . $enc_name))
			$filename = $enc_name;
		elseif (file_exists($modSettings['attachmentUploadDir'] . '/' . $clean_name))
			$filename = $clean_name;
		else
			$filename = $row['filename'];

		$filenames[$row['ID_ATTACH']] = $clean_name;
		$encrypted_filenames[$row['ID_ATTACH']] = $filename;
		$ID_MSG[$row['ID_ATTACH']] = $row['ID_MSG'];
	}
	smf_mysql_free_result($request);

	// Let's loop through the attachments
	if (is_dir($modSettings['attachmentUploadDir']) && $dir = @opendir($modSettings['attachmentUploadDir']))
	{
		while ($file = readdir($dir))
		{
			if (substr($file, -6) == '_thumb')
			{
				// We found a thumbnail, now find the attachment it represents.
				$attach_realFilename = substr($file, 0, -6);
				if (in_array($attach_realFilename, $filenames))
				{
					$attach_id = array_search($attach_realFilename, $filenames);
					$attach_filename = $attach_realFilename;
				}
				elseif (in_array($attach_realFilename, $encrypted_filenames))
				{
					$attach_id = array_search($attach_realFilename, $encrypted_filenames);
					$attach_filename = $filenames[$attach_id];
				}
				else
					continue;

				// No need to register thumbs of non-existent attachments.
				if (!file_exists($modSettings['attachmentUploadDir'] . '/' . $attach_realFilename) || strlen($attach_filename) > 249)
					continue;

				// Determine the dimensions of the thumb.
				list ($thumb_width, $thumb_height) = @getimagesize($modSettings['attachmentUploadDir'] . '/' . $file);
				$thumb_size = filesize($modSettings['attachmentUploadDir'] . '/' . $file);
				$thumb_filename = $attach_filename . '_thumb';

				// Insert the thumbnail in the attachment database.
				upgrade_query("
					INSERT INTO {$db_prefix}attachments
						(ID_MSG, attachmentType, filename, size, width, height)
					VALUES (" . $ID_MSG[$attach_id] . ", 3, '$thumb_filename', " . (int) $thumb_size . ', ' . (int) $thumb_width . ', ' . (int) $thumb_height . ')');
				$thumb_attach_id = smf_mysql_insert_id();

				// Determine the dimensions of the original attachment.
				$attach_width = $attach_height = 0;
				list ($attach_width, $attach_height) = @getimagesize($modSettings['attachmentUploadDir'] . '/' . $attach_realFilename);

				// Link the original attachment to its thumb.
				upgrade_query("
					UPDATE {$db_prefix}attachments
					SET
						id_thumb = $thumb_attach_id,
						width = " . (int) $attach_width . ",
						height = " . (int) $attach_height . "
					WHERE ID_ATTACH = $attach_id
					LIMIT 1");

				// Since it's an attachment now, we might as well encrypt it.
				if (!empty($modSettings['attachmentEncryptFilenames']))
					@rename($modSettings['attachmentUploadDir'] . '/' . $file, $modSettings['attachmentUploadDir'] . '/' . $thumb_attach_id . '_' . strtr($thumb_filename, '.', '_') . md5($thumb_filename));
			}
		}
		closedir($dir);
	}
}
---}
---#

---# Adding image dimensions...
---{
// Now add dimension to the images that have no thumb (yet).
$request = upgrade_query("
	SELECT ID_ATTACH, filename, attachmentType
	FROM {$db_prefix}attachments
	WHERE id_thumb = 0
		AND (RIGHT(filename, 4) IN ('.gif', '.jpg', '.png', '.bmp') OR RIGHT(filename, 5) = '.jpeg')
		AND width = 0
		AND height = 0");
while ($row = smf_mysql_fetch_assoc($request))
{
	if ($row['attachmentType'] == 1)
		$filename = $modSettings['custom_avatar_dir'] . '/' . $row['filename'];
	else
	{
		$clean_name = strtr($row['filename'], 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
		$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));
		$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
		$enc_name = $row['ID_ATTACH'] . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
		$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

		if (file_exists($modSettings['attachmentUploadDir'] . '/' . $enc_name))
			$filename = $modSettings['attachmentUploadDir'] . '/' . $enc_name;
		elseif (file_exists($modSettings['attachmentUploadDir'] . '/' . $clean_name))
			$filename = $modSettings['attachmentUploadDir'] . '/' . $clean_name;
		else
			$filename = $modSettings['attachmentUploadDir'] . '/' . $row['filename'];
	}

	$width = 0;
	$height = 0;
	list ($width, $height) = @getimagesize($filename);
	if (!empty($width) && !empty($height))
		upgrade_query("
			UPDATE {$db_prefix}attachments
			SET
				width = $width,
				height = $height
			WHERE ID_ATTACH = $row[ID_ATTACH]
			LIMIT 1");
}
smf_mysql_free_result($request);
---}
---#

/******************************************************************************/
--- Updating ban system...
/******************************************************************************/

---# Splitting ban table...
---{
// Checkout the current structure of the attachment table.
$request = upgrade_query("
	SHOW TABLES
	LIKE '{$db_prefix}banned'");
$upgradeBanTable = smf_mysql_num_rows($request) == 1;
smf_mysql_free_result($request);

if ($upgradeBanTable)
{
	upgrade_query("
		RENAME TABLE {$db_prefix}banned
		TO {$db_prefix}ban_groups");
	upgrade_query("
		ALTER TABLE {$db_prefix}ban_groups
		CHANGE COLUMN id_ban id_ban_group mediumint(8) unsigned NOT NULL auto_increment");

	upgrade_query("
		CREATE TABLE IF NOT EXISTS {$db_prefix}ban_items (
			id_ban mediumint(8) unsigned NOT NULL auto_increment,
			id_ban_group smallint(5) unsigned NOT NULL default '0',
			ip_low1 tinyint(3) unsigned NOT NULL default '0',
			ip_high1 tinyint(3) unsigned NOT NULL default '0',
			ip_low2 tinyint(3) unsigned NOT NULL default '0',
			ip_high2 tinyint(3) unsigned NOT NULL default '0',
			ip_low3 tinyint(3) unsigned NOT NULL default '0',
			ip_high3 tinyint(3) unsigned NOT NULL default '0',
			ip_low4 tinyint(3) unsigned NOT NULL default '0',
			ip_high4 tinyint(3) unsigned NOT NULL default '0',
			hostname tinytext NOT NULL,
			email_address tinytext NOT NULL,
			ID_MEMBER mediumint(8) unsigned NOT NULL default '0',
			hits mediumint(8) unsigned NOT NULL default '0',
			PRIMARY KEY (id_ban),
			KEY id_ban_group (id_ban_group)
		) ENGINE=MyISAM");

	upgrade_query("
		INSERT INTO {$db_prefix}ban_items
			(id_ban_group, ip_low1, ip_high1, ip_low2, ip_high2, ip_low3, ip_high3, ip_low4, ip_high4, hostname, email_address, ID_MEMBER)
		SELECT id_ban_group, ip_low1, ip_high1, ip_low2, ip_high2, ip_low3, ip_high3, ip_low4, ip_high4, hostname, email_address, ID_MEMBER
		FROM {$db_prefix}ban_groups");

	upgrade_query("
		ALTER TABLE {$db_prefix}ban_groups
		DROP COLUMN ban_type,
		DROP COLUMN ip_low1,
		DROP COLUMN ip_high1,
		DROP COLUMN ip_low2,
		DROP COLUMN ip_high2,
		DROP COLUMN ip_low3,
		DROP COLUMN ip_high3,
		DROP COLUMN ip_low4,
		DROP COLUMN ip_high4,
		DROP COLUMN hostname,
		DROP COLUMN email_address,
		DROP COLUMN ID_MEMBER,
		ADD COLUMN cannot_access tinyint(3) unsigned NOT NULL default '0' AFTER expire_time,
		ADD COLUMN cannot_register tinyint(3) unsigned NOT NULL default '0' AFTER cannot_access,
		ADD COLUMN cannot_post tinyint(3) unsigned NOT NULL default '0' AFTER cannot_register,
		ADD COLUMN cannot_login tinyint(3) unsigned NOT NULL default '0' AFTER cannot_post");

	// Generate names for existing bans.
	upgrade_query("
		ALTER TABLE {$db_prefix}ban_groups
		ADD COLUMN name varchar(20) NOT NULL default '' AFTER id_ban_group");

	$request = upgrade_query("
		SELECT id_ban_group, restriction_type
		FROM {$db_prefix}ban_groups
		ORDER BY ban_time ASC");
	$ban_names = array(
		'full_ban' => 1,
		'cannot_register' => 1,
		'cannot_post' => 1,
	);
	if ($request != false)
	{
		while ($row = smf_mysql_fetch_assoc($request))
			upgrade_query("
				UPDATE {$db_prefix}ban_groups
				SET name = '" . $row['restriction_type'] . '_' . str_pad($ban_names[$row['restriction_type']]++, 3, '0', STR_PAD_LEFT) . "'
				WHERE id_ban_group = $row[id_ban_group]");
		smf_mysql_free_result($request);
	}

	// Move each restriction type to its own column.
	upgrade_query("
		UPDATE {$db_prefix}ban_groups
		SET
			cannot_access = IF(restriction_type = 'full_ban', 1, 0),
			cannot_register = IF(restriction_type = 'cannot_register', 1, 0),
			cannot_post = IF(restriction_type = 'cannot_post', 1, 0)");
	upgrade_query("
		ALTER TABLE {$db_prefix}ban_groups
		DROP COLUMN restriction_type");

	// Make sure everybody's ban situation is re-evaluated.
	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = '" . time() . "'
		WHERE variable = 'banLastUpdated'");
}
---}
---#

---# Updating ban statistics...
---{
	$request = upgrade_query("
		SELECT mem.ID_MEMBER, mem.is_activated + 10 AS new_value
		FROM ({$db_prefix}ban_groups AS bg, {$db_prefix}ban_items AS bi, {$db_prefix}members AS mem)
		WHERE bg.id_ban_group = bi.id_ban_group
			AND bg.cannot_access = 1
			AND (bg.expire_time IS NULL OR bg.expire_time > " . time() . ")
			AND (mem.ID_MEMBER = bi.ID_MEMBER OR mem.emailAddress LIKE bi.email_address)
			AND mem.is_activated < 10");
	$updates = array();
	while ($row = smf_mysql_fetch_assoc($request))
		$updates[$row['new_value']][] = $row['ID_MEMBER'];
	smf_mysql_free_result($request);

	// Find members that are wrongfully marked as banned.
	$request = upgrade_query("
		SELECT mem.ID_MEMBER, mem.is_activated - 10 AS new_value
		FROM {$db_prefix}members AS mem
			LEFT JOIN {$db_prefix}ban_items AS bi ON (bi.ID_MEMBER = mem.ID_MEMBER OR mem.emailAddress LIKE bi.email_address)
			LEFT JOIN {$db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND bg.cannot_access = 1 AND (bg.expire_time IS NULL OR bg.expire_time > " . time() . "))
		WHERE (bi.id_ban IS NULL OR bg.id_ban_group IS NULL)
			AND mem.is_activated >= 10");
	while ($row = smf_mysql_fetch_assoc($request))
		$updates[$row['new_value']][] = $row['ID_MEMBER'];
	smf_mysql_free_result($request);

	if (!empty($updates))
		foreach ($updates as $newStatus => $members)
			upgrade_query("
				UPDATE {$db_prefix}members
				SET is_activated = $newStatus
				WHERE ID_MEMBER IN (" . implode(', ', $members) . ")
				LIMIT " . count($members));
---}
---#

/******************************************************************************/
--- Updating permissions...
/******************************************************************************/

---# Deleting some very old permissions...
DELETE FROM {$db_prefix}board_permissions
WHERE permission IN ('view_threads', 'poll_delete_own', 'poll_delete_any', 'profile_edit_own', 'profile_edit_any');
---#

---# Renaming permissions...
---{
// We *cannot* do this twice!
if (@$modSettings['smfVersion'] < '1.1')
{
	upgrade_query("
		UPDATE {$db_prefix}board_permissions
		SET
			permission = REPLACE(permission, 'remove_replies', 'delete_replies'),
			permission = REPLACE(permission, 'remove_own', 'delete2_own'),
			permission = REPLACE(permission, 'remove_any', 'delete2_any')");
	upgrade_query("
		UPDATE {$db_prefix}board_permissions
		SET
			permission = REPLACE(permission, 'delete_own', 'remove_own'),
			permission = REPLACE(permission, 'delete_any', 'remove_any')");
	upgrade_query("
		UPDATE {$db_prefix}board_permissions
		SET
			permission = REPLACE(permission, 'delete2_own', 'delete_own'),
			permission = REPLACE(permission, 'delete2_any', 'delete_any')");
}
---}
---#

---# Upgrading "deny"-permissions...
---{
if (!isset($modSettings['permission_enable_deny']))
{
	// Only disable if no deny permissions are used.
	$request = upgrade_query("
		SELECT permission
		FROM {$db_prefix}permissions
		WHERE addDeny = 0
		LIMIT 1");
	$disable_deny_permissions = smf_mysql_num_rows($request) == 0;
	smf_mysql_free_result($request);

	// Still wanna disable deny permissions? Check board permissions.
	if ($disable_deny_permissions)
	{
		$request = upgrade_query("
			SELECT permission
			FROM {$db_prefix}board_permissions
			WHERE addDeny = 0
			LIMIT 1");
		$disable_deny_permissions &= smf_mysql_num_rows($request) == 0;
		smf_mysql_free_result($request);
	}

	$request = upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES ('permission_enable_deny', '" . ($disable_deny_permissions ? '0' : '1') . "')");
}
---}
---#

---# Upgrading post based group permissions...
---{
if (!isset($modSettings['permission_enable_postgroups']))
{
	// Only disable if no post group permissions are used.
	$disable_postgroup_permissions = true;
	$request = upgrade_query("
		SELECT p.permission
		FROM ({$db_prefix}permissions AS p, {$db_prefix}membergroups AS mg)
		WHERE mg.ID_GROUP = p.ID_GROUP
			AND mg.minPosts != -1
		LIMIT 1");
	$disable_postgroup_permissions &= smf_mysql_num_rows($request) == 0;
	smf_mysql_free_result($request);

	// Still wanna disable postgroup permissions? Check board permissions.
	if ($disable_postgroup_permissions)
	{
		$request = upgrade_query("
			SELECT bp.permission
			FROM ({$db_prefix}board_permissions AS bp, {$db_prefix}membergroups AS mg)
			WHERE mg.ID_GROUP = bp.ID_GROUP
				AND mg.minPosts != -1
			LIMIT 1");
		$disable_postgroup_permissions &= smf_mysql_num_rows($request) == 0;
		smf_mysql_free_result($request);
	}

	$request = upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES ('permission_enable_postgroups', '" . ($disable_postgroup_permissions ? '0' : '1') . "')");
}
---}
---#

---# Upgrading by-board permissions...
ALTER TABLE {$db_prefix}boards
CHANGE COLUMN use_local_permissions permission_mode tinyint(4) unsigned NOT NULL default '0';

---{
if (!isset($modSettings['permission_enable_by_board']))
{
	// Enable by-board permissions if there's >= 1 local permission board.
	$request = upgrade_query("
		SELECT ID_BOARD
		FROM {$db_prefix}boards
		WHERE permission_mode = 1
		LIMIT 1");
	$enable_by_board = smf_mysql_num_rows($request) == 1 ? '1' : '0';
	smf_mysql_free_result($request);

	$request = upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES ('permission_enable_by_board', '$enable_by_board')");
}
---}
---#

---# Removing all guest deny permissions...
DELETE FROM {$db_prefix}permissions
WHERE ID_GROUP = -1
	AND addDeny = 0;

DELETE FROM {$db_prefix}board_permissions
WHERE ID_GROUP = -1
	AND addDeny = 0;
---#

---# Removing guest admin permissions (if any)...
DELETE FROM {$db_prefix}permissions
WHERE ID_GROUP = -1
	AND permission IN ('admin_forum', 'manage_boards', 'manage_attachments', 'manage_smileys', 'edit_news', 'moderate_forum', 'manage_membergroups', 'manage_permissions', 'manage_bans', 'send_mail');

DELETE FROM {$db_prefix}board_permissions
WHERE ID_GROUP = -1
	AND permission IN ('admin_forum', 'manage_boards', 'manage_attachments', 'manage_smileys', 'edit_news', 'moderate_forum', 'manage_membergroups', 'manage_permissions', 'manage_bans', 'send_mail');
---#

/******************************************************************************/
--- Updating search cache...
/******************************************************************************/

---# Creating search cache tables...
DROP TABLE IF EXISTS {$db_prefix}log_search_fulltext;
DROP TABLE IF EXISTS {$db_prefix}log_search_messages;
DROP TABLE IF EXISTS {$db_prefix}log_search_topics;
DROP TABLE IF EXISTS {$db_prefix}log_search;

CREATE TABLE IF NOT EXISTS {$db_prefix}log_search_messages (
  id_search tinyint(3) unsigned NOT NULL default '0',
  ID_MSG int(10) NOT NULL default '0',
  PRIMARY KEY (id_search, ID_MSG)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS {$db_prefix}log_search_topics (
  id_search tinyint(3) unsigned NOT NULL default '0',
  ID_TOPIC mediumint(9) NOT NULL default '0',
  PRIMARY KEY (id_search, ID_TOPIC)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS {$db_prefix}log_search_results (
  id_search tinyint(3) unsigned NOT NULL default '0',
  ID_TOPIC mediumint(8) unsigned NOT NULL default '0',
  ID_MSG int(10) unsigned NOT NULL default '0',
  relevance smallint(5) unsigned NOT NULL default '0',
  num_matches smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY (id_search, ID_TOPIC),
  KEY relevance (relevance)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS {$db_prefix}log_search_subjects (
  word varchar(20) NOT NULL default '',
  ID_TOPIC mediumint(8) unsigned NOT NULL default '0',
  PRIMARY KEY (word, ID_TOPIC),
  KEY ID_TOPIC (ID_TOPIC)
) ENGINE=MyISAM;
---#

---# Rebuilding fulltext index...
---{
$request = upgrade_query("
	SHOW KEYS
	FROM {$db_prefix}messages");
$found = false;
while ($row = smf_mysql_fetch_assoc($request))
	$found |= $row['Key_name'] == 'subject' && $row['Column_name'] == 'subject';
smf_mysql_free_result($request);
if ($found)
{
	$request = upgrade_query("
		ALTER TABLE {$db_prefix}messages
		DROP INDEX subject,
		DROP INDEX body,
		ADD FULLTEXT body (body)");
}
---}
---#

---# Indexing topic subjects...
---{
$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}log_search_subjects");
list ($numIndexedWords) = smf_mysql_fetch_row($request);
smf_mysql_free_result($request);
if ($numIndexedWords == 0 || isset($_GET['lt']))
{
	$request = upgrade_query("
		SELECT COUNT(*)
		FROM {$db_prefix}topics");
	list ($maxTopics) = smf_mysql_fetch_row($request);
	smf_mysql_free_result($request);

	$_GET['lt'] = isset($_GET['lt']) ? (int) $_GET['lt'] : 0;
	$step_progress['name'] = 'Indexing Topic Subjects';
	$step_progress['current'] = $_GET['lt'];
	$step_progress['total'] = $maxTopics;

	while ($_GET['lt'] <= $maxTopics)
	{
		$request = upgrade_query("
			SELECT t.ID_TOPIC, m.subject
			FROM ({$db_prefix}topics AS t, {$db_prefix}messages AS m)
			WHERE m.ID_MSG = t.ID_FIRST_MSG
			LIMIT $_GET[lt], 250");
		$inserts = array();
		while ($row = smf_mysql_fetch_assoc($request))
		{
			foreach (text2words($row['subject']) as $word)
				$inserts[] = "'" . smf_mysql_real_escape_string($word) . "', $row[ID_TOPIC]";
		}
		smf_mysql_free_result($request);

		if (!empty($inserts))
			upgrade_query("
				INSERT INTO {$db_prefix}log_search_subjects
					(word, ID_TOPIC)
				VALUES (" . implode('),
					(', array_unique($inserts)) . ")");

		$_GET['lt'] += 250;
		$step_progress['current'] = $_GET['lt'];
		nextSubstep($substep);
	}
	unset($_GET['lt']);
}
---}
---#

---# Converting settings...
---{
if (isset($modSettings['search_method']))
{
	if (!empty($modSettings['search_method']))
		$request = upgrade_query("
			INSERT INTO {$db_prefix}settings
				(variable, value)
			VALUES
				('search_match_words', '1')");

	if ($modSettings['search_method'] > 1)
		$request = upgrade_query("
			INSERT INTO {$db_prefix}settings
				(variable, value)
			VALUES
				('search_index', 'fulltext')");

	if ($modSettings['search_method'] == 3)
		$request = upgrade_query("
			INSERT INTO {$db_prefix}settings
				(variable, value)
			VALUES
				('search_force_index', '1')");

	$request = upgrade_query("
		DELETE FROM {$db_prefix}settings
		WHERE variable = 'search_method'");
}
---}
---#

/******************************************************************************/
--- Upgrading log system...
/******************************************************************************/

---# Creating log table indexes (this might take some time!)...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}log_topics");
$upgradeLogTable = false;
while ($request && $row = smf_mysql_fetch_row($request))
	$upgradeLogTable |= $row[0] == 'logTime';
if ($request !== false)
	smf_mysql_free_result($request);

if ($upgradeLogTable)
{
	$_GET['preprep_lt'] = isset($_GET['preprep_lt']) ? (int) $_GET['preprep_lt'] : 0;
	$step_progress['name'] = 'Creating index\'s for log table';
	$step_progress['current'] = $_GET['preprep_lt'];
	$custom_warning = 'On a very large board these index\'s may take a few minutes to create.';

	$log_additions = array(
		array(
			'table' => 'log_boards',
			'type' => 'index',
			'method' => 'add',
			'name' => 'logTime',
			'target_columns' => array('logTime'),
			'text' => 'ADD INDEX logTime (logTime)',
		),
		array(
			'table' => 'log_mark_read',
			'type' => 'index',
			'method' => 'add',
			'name' => 'logTime',
			'target_columns' => array('logTime'),
			'text' => 'ADD INDEX logTime (logTime)',
		),
		array(
			'table' => 'messages',
			'type' => 'index',
			'method' => 'add',
			'name' => 'modifiedTime',
			'target_columns' => array('modifiedTime'),
			'text' => 'ADD INDEX modifiedTime (modifiedTime)',
		),
	);

	$step_progress['total'] = count($log_additions);

	// Now we loop through the changes and work out where the hell we are.
	foreach ($log_additions as $ind => $change)
	{
		// Already done it?
		if ($_GET['preprep_lt'] > $ind)
			continue;

		// Make the index, with all the protection and all.
		protected_alter($change, $substep);

		// Store this for the next table.
		$_GET['preprep_lt']++;
		$step_progress['current'] = $_GET['preprep_lt'];
	}

	// Clean up.
	unset($_GET['preprep_lt']);
}
---}
---#

---# Preparing log table upgrade...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}log_topics");
$upgradeLogTable = false;
while ($request && $row = smf_mysql_fetch_row($request))
	$upgradeLogTable |= $row[0] == 'logTime';
if ($request !== false)
	smf_mysql_free_result($request);

if ($upgradeLogTable)
{
	$_GET['prep_lt'] = isset($_GET['prep_lt']) ? (int) $_GET['prep_lt'] : 0;
	$step_progress['name'] = 'Preparing log table update';
	$step_progress['current'] = $_GET['prep_lt'];
	$custom_warning = 'This step may take quite some time. During this time it may appear that nothing is happening while
		the databases MySQL tables are expanded. Please be patient.';

	// All these changes need to be made, they may take a while, so let's timeout neatly.
	$log_additions = array(
		array(
			'table' => 'log_topics',
			'type' => 'index',
			'method' => 'remove',
			'name' => 'ID_MEMBER',
			'target_columns' => array('ID_MEMBER'),
			'text' => 'DROP INDEX ID_MEMBER',
		),
		array(
			'table' => 'log_topics',
			'type' => 'index',
			'method' => 'change',
			'name' => 'PRIMARY',
			'target_columns' => array('ID_MEMBER', 'ID_TOPIC'),
			'text' => '
				DROP PRIMARY KEY,
				ADD PRIMARY KEY (ID_MEMBER, ID_TOPIC)',
		),
		array(
			'table' => 'log_topics',
			'type' => 'index',
			'method' => 'add',
			'name' => 'logTime',
			'target_columns' => array('logTime'),
			'text' => 'ADD INDEX logTime (logTime)',
		),
		array(
			'table' => 'log_boards',
			'type' => 'column',
			'method' => 'add',
			'name' => 'ID_MSG',
			'text' => 'ADD COLUMN ID_MSG mediumint(8) unsigned NOT NULL default \'0\'',
		),
		array(
			'table' => 'log_mark_read',
			'type' => 'column',
			'method' => 'add',
			'name' => 'ID_MSG',
			'text' => 'ADD COLUMN ID_MSG mediumint(8) unsigned NOT NULL default \'0\'',
		),
		array(
			'table' => 'log_topics',
			'type' => 'column',
			'method' => 'add',
			'name' => 'ID_MSG',
			'text' => 'ADD COLUMN ID_MSG mediumint(8) unsigned NOT NULL default \'0\'',
		),
		array(
			'table' => 'messages',
			'type' => 'column',
			'method' => 'add',
			'name' => 'ID_MSG_MODIFIED',
			'text' => 'ADD COLUMN ID_MSG_MODIFIED mediumint(8) unsigned NOT NULL default \'0\' AFTER ID_MEMBER',
		),
		array(
			'table' => 'boards',
			'type' => 'column',
			'method' => 'add',
			'name' => 'ID_MSG_UPDATED',
			'text' => 'ADD COLUMN ID_MSG_UPDATED mediumint(8) unsigned NOT NULL default \'0\' AFTER ID_LAST_MSG',
		),
		array(
			'table' => 'boards',
			'type' => 'index',
			'method' => 'add',
			'name' => 'ID_MSG_UPDATED',
			'target_columns' => array('ID_MSG_UPDATED'),
			'text' => 'ADD INDEX ID_MSG_UPDATED (ID_MSG_UPDATED)',
		),
	);
	$step_progress['total'] = count($log_additions);

	// Now we loop through the changes and work out where the hell we are.
	foreach ($log_additions as $ind => $change)
	{
		// Already done it?
		if ($_GET['prep_lt'] > $ind)
			continue;

		// Make the index, with all the protection and all.
		protected_alter($change, $substep);

		// Store this for the next table.
		$_GET['prep_lt']++;
		$step_progress['current'] = $_GET['prep_lt'];
	}

	// Clean up.
	unset($_GET['prep_lt']);
}
---}
---#

---# Converting log tables (this might take some time!)...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}log_topics");
$upgradeLogTable = false;
while ($request && $row = smf_mysql_fetch_row($request))
	$upgradeLogTable |= $row[0] == 'logTime';
if ($request !== false)
	smf_mysql_free_result($request);

if ($upgradeLogTable)
{
	$request = upgrade_query("
		SELECT MAX(ID_MSG)
		FROM {$db_prefix}messages");
	list($maxMsg) = smf_mysql_fetch_row($request);
	smf_mysql_free_result($request);

	if (empty($maxMsg))
		$maxMsg = 0;

	$_GET['m'] = isset($_GET['m']) ? (int) $_GET['m'] : 0;
	$step_progress['name'] = 'Converting Log Tables';
	$step_progress['current'] = $_GET['m'];
	$step_progress['total'] = $maxMsg;
	$custom_warning = 'This step is converting all your log tables and may take quite some time on a large forum (Several hours for a forum with ~500,000 messages).';

	// Only adjust the structure if this is the first message.
	if ($_GET['m'] === 0)
	{
		// By default a message is modified when it was written.
		upgrade_query("
			UPDATE {$db_prefix}messages
			SET ID_MSG_MODIFIED = ID_MSG");

		$request = upgrade_query("
			SELECT posterTime
			FROM {$db_prefix}messages
			WHERE ID_MSG = $maxMsg");
		list($maxPosterTime) = smf_mysql_fetch_row($request);
		smf_mysql_free_result($request);

		if (empty($maxPosterTime))
			$maxPosterTime = 0;

		upgrade_query("
			UPDATE {$db_prefix}log_boards
			SET ID_MSG = $maxMsg
			WHERE logTime >= $maxPosterTime");
		upgrade_query("
			UPDATE {$db_prefix}log_mark_read
			SET ID_MSG = $maxMsg
			WHERE logTime >= $maxPosterTime");
		upgrade_query("
			UPDATE {$db_prefix}log_topics
			SET ID_MSG = $maxMsg
			WHERE logTime >= $maxPosterTime");
		upgrade_query("
			UPDATE {$db_prefix}messages
			SET ID_MSG_MODIFIED = $maxMsg
			WHERE modifiedTime >= $maxPosterTime");

		// Timestamp 1 is where it all starts.
		$lower_limit = 1;
	}
	else
	{
		// Determine the lower limit.
		$request = upgrade_query("
			SELECT MAX(posterTime) + 1
			FROM {$db_prefix}messages
			WHERE ID_MSG < $_GET[m]");
		list($lower_limit) = smf_mysql_fetch_row($request);
		smf_mysql_free_result($request);

		if (empty($lower_limit))
			$lower_limit = 1;

		if (empty($maxPosterTime))
			$maxPosterTime = 1;
	}

	while ($_GET['m'] <= $maxMsg)
	{
		$condition = '';
		$lowest_limit = $lower_limit;
		$request = upgrade_query("
			SELECT MAX(ID_MSG) AS ID_MSG, posterTime
			FROM {$db_prefix}messages
			WHERE ID_MSG BETWEEN $_GET[m] AND " . ($_GET['m'] + 300) . "
			GROUP BY posterTime
			ORDER BY posterTime
			LIMIT 300");
		while ($row = smf_mysql_fetch_assoc($request))
		{
			if ($condition === '')
				$condition = "IF(logTime BETWEEN $lower_limit AND $row[posterTime], $row[ID_MSG], %else%)";
			else
				$condition = strtr($condition, array('%else%' => "IF(logTime <= $row[posterTime], $row[ID_MSG], %else%)"));

			$lower_limit = $row['posterTime'] + 1;
		}
		smf_mysql_free_result($request);

		if ($condition !== '')
		{
			$condition = strtr($condition, array('%else%' => '0'));
			$highest_limit = $lower_limit;

			upgrade_query("
				UPDATE {$db_prefix}log_boards
				SET ID_MSG = $condition
				WHERE logTime BETWEEN $lowest_limit AND $highest_limit
					AND ID_MSG = 0");
			upgrade_query("
				UPDATE {$db_prefix}log_mark_read
				SET ID_MSG = $condition
				WHERE logTime BETWEEN $lowest_limit AND $highest_limit
					AND ID_MSG = 0");
			upgrade_query("
				UPDATE {$db_prefix}log_topics
				SET ID_MSG = $condition
				WHERE logTime BETWEEN $lowest_limit AND $highest_limit
					AND ID_MSG = 0");
			upgrade_query("
				UPDATE {$db_prefix}messages
				SET ID_MSG_MODIFIED = " . strtr($condition, array('logTime' => 'modifiedTime')) . "
				WHERE modifiedTime BETWEEN $lowest_limit AND $highest_limit
					AND modifiedTime > 0");
		}

		$_GET['m'] += 300;
		nextSubstep($substep);
	}
	unset($_GET['m']);
}
---}
---#

---# Updating last message IDs for boards.
---{

$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}boards");
$upgradeBoardsTable = false;
while ($request && $row = smf_mysql_fetch_row($request))
	$upgradeBoardsTable |= $row[0] == 'lastUpdated';
if ($request !== false)
	smf_mysql_free_result($request);

if ($upgradeBoardsTable)
{
	$request = upgrade_query("
		SELECT MAX(ID_BOARD)
		FROM {$db_prefix}boards");
	list ($maxBoard) = smf_mysql_fetch_row($request);
	smf_mysql_free_result($request);

	$_GET['bdi'] = isset($_GET['bdi']) ? (int) $_GET['bdi'] : 0;
	$step_progress['name'] = 'Updating Last Board ID';
	$step_progress['current'] = $_GET['bdi'];
	$step_progress['total'] = $maxBoard;

	// OK, we need to get the last updated message.
	$request = upgrade_query("
		SELECT ID_BOARD, lastUpdated
		FROM {$db_prefix}boards");
	while ($row = smf_mysql_fetch_assoc($request))
	{
		// Done this?
		if ($row['ID_BOARD'] < $_GET['bdi'])
			continue;

		// Maybe we don't have any?
		if ($row['lastUpdated'] == 0)
			$ID_MSG = 0;
		// Otherwise need to query it?
		else
		{
			$request2 = upgrade_query("
				SELECT MIN(ID_MSG)
				FROM {$db_prefix}messages
				WHERE posterTime >= $row[lastUpdated]");
			list ($ID_MSG) = smf_mysql_fetch_row($request2);

			if (empty($ID_MSG))
				$ID_MSG = 0;
		}

		upgrade_query("
			UPDATE {$db_prefix}boards
			SET ID_MSG_UPDATED = $ID_MSG
			WHERE ID_BOARD = $row[ID_BOARD]");

		$_GET['bdi']++;
		$step_progress['current'] = $_GET['bdi'];
		nextSubstep($substep);
	}
	unset($_GET['bdi']);
}
---}
---#

---# Cleaning up old log indexes...
---{
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}log_topics");
$upgradeLogTable = false;
while ($request && $row = smf_mysql_fetch_row($request))
	$upgradeLogTable |= $row[0] == 'logTime';
if ($request !== false)
	smf_mysql_free_result($request);

if ($upgradeLogTable)
{
	$_GET['prep_lt'] = isset($_GET['prep_lt']) ? (int) $_GET['prep_lt'] : 0;
	$step_progress['name'] = 'Cleaning up old log table index\'s';
	$step_progress['current'] = $_GET['prep_lt'];
	$custom_warning = 'This step may take quite some time. During this time it may appear that nothing is happening while
		the databases MySQL tables are cleaned. Please be patient.';

	// Here we remove all the unused indexes
	$log_deletions = array(
		array(
			'table' => 'boards',
			'type' => 'index',
			'method' => 'remove',
			'name' => 'lastUpdated',
			'target_columns' => array('lastUpdated'),
			'text' => 'DROP INDEX lastUpdated',
		),
		array(
			'table' => 'messages',
			'type' => 'index',
			'method' => 'remove',
			'name' => 'posterTime',
			'target_columns' => array('posterTime'),
			'text' => 'DROP INDEX posterTime',
		),
		array(
			'table' => 'messages',
			'type' => 'index',
			'method' => 'remove',
			'name' => 'modifiedTime',
			'target_columns' => array('modifiedTime'),
			'text' => 'DROP INDEX modifiedTime',
		),
		array(
			'table' => 'log_topics',
			'type' => 'column',
			'method' => 'remove',
			'name' => 'logTime',
			'text' => 'DROP COLUMN logTime',
		),
		array(
			'table' => 'log_boards',
			'type' => 'column',
			'method' => 'remove',
			'name' => 'logTime',
			'text' => 'DROP COLUMN logTime',
		),
		array(
			'table' => 'log_mark_read',
			'type' => 'column',
			'method' => 'remove',
			'name' => 'logTime',
			'text' => 'DROP COLUMN logTime',
		),
		array(
			'table' => 'boards',
			'type' => 'column',
			'method' => 'remove',
			'name' => 'lastUpdated',
			'text' => 'DROP COLUMN lastUpdated',
		),
	);
	$step_progress['total'] = count($log_deletions);

	// Now we loop through the changes and work out where the hell we are.
	foreach ($log_deletions as $ind => $change)
	{
		// Already done it?
		if ($_GET['prep_lt'] > $ind)
			continue;

		// Make the index, with all the protection and all.
		protected_alter($change, $substep);

		// Store this for the next table.
		$_GET['prep_lt']++;
		$step_progress['current'] = $_GET['prep_lt'];
	}

	// Clean up.
	unset($_GET['prep_lt']);
	$step_progress = array();
}
---}
---#

/******************************************************************************/
--- Making SMF MySQL strict compatible...
/******************************************************************************/

---# Preparing messages table for strict upgrade
ALTER IGNORE TABLE {$db_prefix}messages
DROP INDEX ipIndex;
---#

---# Adjusting text fields
---#
---{
// Note we move on by one as there is no point ALTER'ing the same thing twice.
$_GET['strict_step'] = isset($_GET['strict_step']) ? (int) $_GET['strict_step'] + 1 : 0;
$step_progress['name'] = 'Adding MySQL strict compatibility';
$step_progress['current'] = $_GET['strict_step'];

// Take care with the body column from messages, just in case it's been enlarged by others.
$request = upgrade_query("
	SHOW COLUMNS
	FROM {$db_prefix}messages
	LIKE 'body'");
$body_row = smf_mysql_fetch_assoc($request);
smf_mysql_free_result($request);

$body_type = $body_row['Type'];

$textfield_updates = array(
	array(
		'table' => 'attachments',
		'column' => 'filename',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'ban_groups',
		'column' => 'reason',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'ban_items',
		'column' => 'hostname',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'ban_items',
		'column' => 'email_address',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'boards',
		'column' => 'name',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'boards',
		'column' => 'description',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'categories',
		'column' => 'name',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'log_actions',
		'column' => 'extra',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'log_banned',
		'column' => 'email',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'log_banned',
		'column' => 'email',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'log_errors',
		'column' => 'url',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'log_errors',
		'column' => 'message',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'log_online',
		'column' => 'url',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'membergroups',
		'column' => 'stars',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'lngfile',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'realName',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'buddy_list',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'pm_ignore_list',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'messageLabels',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'emailAddress',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'personalText',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'websiteTitle',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'websiteUrl',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'location',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'ICQ',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'MSN',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'signature',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'avatar',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'usertitle',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'memberIP',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'secretQuestion',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'members',
		'column' => 'additionalGroups',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'messages',
		'column' => 'subject',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'messages',
		'column' => 'posterName',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'messages',
		'column' => 'posterEmail',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'messages',
		'column' => 'posterIP',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'messages',
		'column' => 'modifiedName',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'messages',
		'column' => 'body',
		'type' => $body_type,
		'null_allowed' => false,
	),
	array(
		'table' => 'personal_messages',
		'column' => 'body',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'package_servers',
		'column' => 'name',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'personal_messages',
		'column' => 'fromName',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'personal_messages',
		'column' => 'subject',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'personal_messages',
		'column' => 'body',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'polls',
		'column' => 'question',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'polls',
		'column' => 'posterName',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'poll_choices',
		'column' => 'label',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'settings',
		'column' => 'variable',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'settings',
		'column' => 'value',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'sessions',
		'column' => 'data',
		'type' => 'text',
		'null_allowed' => false,
	),
	array(
		'table' => 'themes',
		'column' => 'variable',
		'type' => 'tinytext',
		'null_allowed' => false,
	),
	array(
		'table' => 'themes',
		'column' => 'value',
		'type' => 'text',
		'null_allowed' => false,
	),
);
$step_progress['total'] = count($textfield_updates);

foreach ($textfield_updates as $ind => $change)
{
	// Already done it?
	if ($_GET['strict_step'] > $ind)
		continue;

	// Make the index, with all the protection and all.
	textfield_alter($change, $substep);

	// Store this for the next table.
	$_GET['strict_step']++;
	$step_progress['current'] = $_GET['strict_step'];
}

$step_progress = array();
---}
---#

---# Replacing messages index.
ALTER TABLE {$db_prefix}messages
ADD INDEX ipIndex (posterIP(15), ID_TOPIC);
---#

---# Adding log_topics index.
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_topics
	ADD INDEX ID_TOPIC (ID_TOPIC)", true);
---}
---#

/******************************************************************************/
--- Adding more room for the buddy list
/******************************************************************************/

---# Updating the members table ...
ALTER TABLE {$db_prefix}members
CHANGE COLUMN buddy_list buddy_list text NOT NULL;
---#

/******************************************************************************/
--- Change some column types to accommodate more messages.
/******************************************************************************/

---# Expanding message column size.
---{
$_GET['msg_change'] = isset($_GET['msg_change']) ? (int) $_GET['msg_change'] : 0;
$step_progress['name'] = 'Expanding Message Capacity';
$step_progress['current'] = $_GET['msg_change'];

// The array holding all the changes.
$columnChanges = array(
	array(
		'table' => 'boards',
		'type' => 'column',
		'method' => 'change',
		'name' => 'ID_LAST_MSG',
		'text' => 'CHANGE ID_LAST_MSG ID_LAST_MSG int(10) unsigned NOT NULL default \'0\'',
	),
	array(
		'table' => 'boards',
		'type' => 'column',
		'method' => 'change',
		'name' => 'ID_MSG_UPDATED',
		'text' => 'CHANGE ID_MSG_UPDATED ID_MSG_UPDATED int(10) unsigned NOT NULL default \'0\'',
	),
	array(
		'table' => 'log_boards',
		'type' => 'column',
		'method' => 'change',
		'name' => 'ID_MSG',
		'text' => 'CHANGE ID_MSG ID_MSG int(10) unsigned NOT NULL default \'0\'',
	),
	array(
		'table' => 'log_mark_read',
		'type' => 'column',
		'method' => 'change',
		'name' => 'ID_MSG',
		'text' => 'CHANGE ID_MSG ID_MSG int(10) unsigned NOT NULL default \'0\'',
	),
	array(
		'table' => 'log_topics',
		'type' => 'column',
		'method' => 'change',
		'name' => 'ID_MSG',
		'text' => 'CHANGE ID_MSG ID_MSG int(10) unsigned NOT NULL default \'0\'',
	),
	array(
		'table' => 'messages',
		'type' => 'column',
		'method' => 'change',
		'name' => 'ID_MSG_MODIFIED',
		'text' => 'CHANGE ID_MSG_MODIFIED ID_MSG_MODIFIED int(10) unsigned NOT NULL default \'0\'',
	),
);

if (!empty($modSettings['search_custom_index_config']))
	$columnChanges[] = array(
		'table' => 'log_search_words',
		'type' => 'column',
		'method' => 'change',
		'name' => 'ID_MSG',
		'text' => 'CHANGE ID_MSG ID_MSG int(10) unsigned NOT NULL default \'0\'',
	);

$step_progress['total'] = count($columnChanges);

// Now we do all the changes...
foreach ($columnChanges as $index => $change)
{
	// Already done it?
	if ($_GET['msg_change'] > $ind)
		continue;

	// Now change the column at last.
	protected_alter($change, $substep);

	// Update where we are...
	$_GET['msg_change']++;
	$step_progress['current'] = $_GET['msg_change'];
}

// Clean up.
unset($_GET['msg_change']);
---}
---#

/******************************************************************************/
--- Final clean up...
/******************************************************************************/

---# Sorting the boards...
ALTER TABLE {$db_prefix}categories
ORDER BY catOrder;

ALTER TABLE {$db_prefix}boards
ORDER BY boardOrder;
---#

---# Removing upgrade loop protection...
DELETE FROM {$db_prefix}settings
WHERE variable IN ('dont_repeat_smtp', 'dont_repeat_theme');
---#