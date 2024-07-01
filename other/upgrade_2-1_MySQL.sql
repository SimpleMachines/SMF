/******************************************************************************/
--- Create index for messages likes
/******************************************************************************/
---# Add Index for messages likes
DROP INDEX idx_likes ON {$db_prefix}messages;
CREATE INDEX idx_likes ON {$db_prefix}messages (likes);
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
MODIFY COLUMN id_msg INT UNSIGNED NOT NULL DEFAULT '0';
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
MODIFY COLUMN pm_ignore_list TEXT NULL;
---#

---# Updating password_salt
ALTER TABLE {$db_prefix}members
MODIFY COLUMN password_salt VARCHAR(255) NOT NULL DEFAULT '';
---#

---# Updating member_logins id_member
ALTER TABLE {$db_prefix}member_logins
MODIFY COLUMN id_member MEDIUMINT NOT NULL DEFAULT '0';
---#

---# Updating member_logins time
ALTER TABLE {$db_prefix}member_logins
MODIFY COLUMN time INT NOT NULL DEFAULT '0';
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

---# Updating members active_real_name (drop)
ALTER TABLE {$db_prefix}members
DROP INDEX idx_active_real_name;
---#

---# Updating members active_real_name (add)
ALTER TABLE {$db_prefix}members
ADD INDEX idx_active_real_name (is_activated, real_name);
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

---# Updating messages drop approved ix
ALTER TABLE {$db_prefix}messages
DROP INDEX approved;
---#

---# Updating messages drop approved ix alt name
ALTER TABLE {$db_prefix}messages
DROP INDEX idx_approved;
---#

---# Updating messages drop id_board ix
ALTER TABLE {$db_prefix}messages
DROP INDEX id_board;
---#

---# Updating messages drop id_board ix alt name
ALTER TABLE {$db_prefix}messages
DROP INDEX idx_id_board;
---#

---# Updating messages add new id_board ix
ALTER TABLE {$db_prefix}messages
ADD UNIQUE INDEX idx_id_board (id_board, id_msg, approved);
---#

---# Updating topics drop old id_board ix
ALTER TABLE {$db_prefix}topics
DROP INDEX id_board;
---#

/******************************************************************************/
--- Update smileys
/******************************************************************************/
---# Adding the new `smiley_files` table
CREATE TABLE IF NOT EXISTS {$db_prefix}smiley_files
(
	id_smiley SMALLINT NOT NULL DEFAULT '0',
	smiley_set VARCHAR(48) NOT NULL DEFAULT '',
	filename VARCHAR(48) NOT NULL DEFAULT '',
	PRIMARY KEY (id_smiley, smiley_set)
) ENGINE=MyISAM;
---#

---# Cleaning up unused smiley sets and adding the lovely new ones
---{
// Start with the prior values...
$dirs = explode(',', Config::$modSettings['smiley_sets_known']);
$setnames = explode("\n", Config::$modSettings['smiley_sets_names']);

// Build combined pairs of folders and names
$combined = array();
foreach ($dirs AS $ix => $dir)
{
	if (!empty($setnames[$ix]))
		$combined[$dir] = array($setnames[$ix], '');
}

// Add our lovely new 2.1 smiley sets if not already there
$combined['fugue'] = array(Lang::$txt['default_fugue_smileyset_name'], 'png');
$combined['alienine'] = array(Lang::$txt['default_alienine_smileyset_name'], 'png');

// Add/fix our 2.0 sets (to correct past problems where these got corrupted)
$combined['default'] = array(Lang::$txt['default_legacy_smileyset_name'], 'gif');
$combined['aaron'] = array(Lang::$txt['default_aaron_smileyset_name'], 'gif');
$combined['akyhne'] = array(Lang::$txt['default_akyhne_smileyset_name'], 'gif');

// Confirm they exist in the filesystem
$filtered = array();
foreach ($combined as $dir => $attrs)
{
	if (is_dir(Config::$modSettings['smileys_dir'] . '/' . $dir . '/'))
		$filtered[$dir] = $attrs[0];
}

// Update the Settings Table...
upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = '" . Db::$db->escape_string(implode(',', array_keys($filtered))) . "'
	WHERE variable = 'smiley_sets_known'");

upgrade_query("
	UPDATE {$db_prefix}settings
	SET value = '" . Db::$db->escape_string(implode("\n", $filtered)) . "'
	WHERE variable = 'smiley_sets_names'");

// Populate the smiley_files table
$smileys_columns = Db::$db->list_columns('{db_prefix}smileys');
if (in_array('filename', $smileys_columns))
{
	$inserts = array();

	$request = upgrade_query("
		SELECT id_smiley, filename
		FROM {$db_prefix}smileys");
	while ($row = Db::$db->fetch_assoc($request))
	{
		$pathinfo = pathinfo($row['filename']);

		foreach ($filtered as $set => $dummy)
		{
			$ext = $pathinfo['extension'];

			// If we have a default extension for this set, check if we can switch to it.
			if (isset($combined[$set]) && !empty($combined[$set][1]))
			{
				if (file_exists(Config::$modSettings['smileys_dir'] . '/' . $set . '/' . $pathinfo['filename'] . '.' . $combined[$set][1]))
					$ext = $combined[$set][1];
			}
			// In a custom set and no extension specified? Ugh...
			elseif (empty($ext))
			{
				// Any files matching this name?
				$found = glob(Config::$modSettings['smileys_dir'] . '/' . $set . '/' . $pathinfo['filename'] . '.*');
				$ext = !empty($found) ? pathinfo($found[0], PATHINFO_EXTENSION) : 'gif';
			}

			$inserts[] = array($row['id_smiley'], $set, $pathinfo['filename'] . '.' . $ext);
		}
	}
	Db::$db->free_result($request);

	if (!empty($inserts))
	{
		Db::$db->insert('ignore',
			'{db_prefix}smiley_files',
			array('id_smiley' => 'int', 'smiley_set' => 'string-48', 'filename' => 'string-48'),
			$inserts,
			array('id_smiley', 'smiley_set')
		);

		// Unless something went horrifically wrong, drop the defunct column
		if (count($inserts) == Db::$db->affected_rows())
			upgrade_query("
				ALTER TABLE {$db_prefix}smileys
				DROP COLUMN filename;");
	}
}

// Set new default if the old one doesn't exist
// If fugue exists, use that.  Otherwise, what the heck, just grab the first one...
if (!array_key_exists(Config::$modSettings['smiley_sets_default'], $filtered))
{
	if (array_key_exists('fugue', $filtered))
		$newdefault = 'fugue';
	elseif (!empty($filtered))
		$newdefault = reset(array_keys($filtered));
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
--- Update permissions system board_permissions_view
/******************************************************************************/
---# Create table board_permissions_view
CREATE TABLE IF NOT EXISTS {$db_prefix}board_permissions_view
(
	id_group SMALLINT NOT NULL DEFAULT '0',
	id_board SMALLINT UNSIGNED NOT NULL,
	deny smallint NOT NULL,
	PRIMARY KEY (id_group, id_board, deny)
) ENGINE=MyISAM;

---# upgrade check
---{
	// if one of source col is missing skip this step
$table_columns = Db::$db->list_columns('{db_prefix}membergroups');
$table_columns2 = Db::$db->list_columns('{db_prefix}boards');
$upcontext['skip_db_substeps'] = !in_array('id_group', $table_columns) || !in_array('member_groups', $table_columns2) || !in_array('deny_member_groups', $table_columns2);
---}
---#

---#
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

/******************************************************************************/
--- Update holidays
/******************************************************************************/
---# Delete all the dates
DELETE FROM {$db_prefix}calendar_holidays WHERE title in
('Mother''s Day','Father''s Day', 'Summer Solstice', 'Vernal Equinox', 'Winter Solstice', 'Autumnal Equinox',
	'Thanksgiving', 'Memorial Day', 'Labor Day', 'New Year''s', 'Christmas', 'Valentine''s Day', 'St. Patrick''s Day',
	'April Fools', 'Earth Day', 'United Nations Day', 'Halloween', 'Independence Day', 'Cinco de Mayo', 'Flag Day',
	'Veterans Day', 'Groundhog Day', 'D-Day');
---#

---# Insert the updated dates
INSERT INTO {$db_prefix}calendar_holidays
	(title, event_date)
VALUES ('New Year''s', '1004-01-01'),
	('Christmas', '1004-12-25'),
	('Valentine''s Day', '1004-02-14'),
	('St. Patrick''s Day', '1004-03-17'),
	('April Fools', '1004-04-01'),
	('Earth Day', '1004-04-22'),
	('United Nations Day', '1004-10-24'),
	('Halloween', '1004-10-31'),
	('Mother''s Day', '2010-05-09'),
	('Mother''s Day', '2011-05-08'),
	('Mother''s Day', '2012-05-13'),
	('Mother''s Day', '2013-05-12'),
	('Mother''s Day', '2014-05-11'),
	('Mother''s Day', '2015-05-10'),
	('Mother''s Day', '2016-05-08'),
	('Mother''s Day', '2017-05-14'),
	('Mother''s Day', '2018-05-13'),
	('Mother''s Day', '2019-05-12'),
	('Mother''s Day', '2020-05-10'),
	('Mother''s Day', '2021-05-09'),
	('Mother''s Day', '2022-05-08'),
	('Mother''s Day', '2023-05-14'),
	('Mother''s Day', '2024-05-12'),
	('Mother''s Day', '2025-05-11'),
	('Mother''s Day', '2026-05-10'),
	('Mother''s Day', '2027-05-09'),
	('Mother''s Day', '2028-05-14'),
	('Mother''s Day', '2029-05-13'),
	('Mother''s Day', '2030-05-12'),
	('Father''s Day', '2010-06-20'),
	('Father''s Day', '2011-06-19'),
	('Father''s Day', '2012-06-17'),
	('Father''s Day', '2013-06-16'),
	('Father''s Day', '2014-06-15'),
	('Father''s Day', '2015-06-21'),
	('Father''s Day', '2016-06-19'),
	('Father''s Day', '2017-06-18'),
	('Father''s Day', '2018-06-17'),
	('Father''s Day', '2019-06-16'),
	('Father''s Day', '2020-06-21'),
	('Father''s Day', '2021-06-20'),
	('Father''s Day', '2022-06-19'),
	('Father''s Day', '2023-06-18'),
	('Father''s Day', '2024-06-16'),
	('Father''s Day', '2025-06-15'),
	('Father''s Day', '2026-06-21'),
	('Father''s Day', '2027-06-20'),
	('Father''s Day', '2028-06-18'),
	('Father''s Day', '2029-06-17'),
	('Father''s Day', '2030-06-16'),
	('Summer Solstice', '2010-06-21'),
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
	('Summer Solstice', '2021-06-21'),
	('Summer Solstice', '2022-06-21'),
	('Summer Solstice', '2023-06-21'),
	('Summer Solstice', '2024-06-20'),
	('Summer Solstice', '2025-06-21'),
	('Summer Solstice', '2026-06-21'),
	('Summer Solstice', '2027-06-21'),
	('Summer Solstice', '2028-06-20'),
	('Summer Solstice', '2029-06-21'),
	('Summer Solstice', '2030-06-21'),
	('Vernal Equinox', '2010-03-20'),
	('Vernal Equinox', '2011-03-20'),
	('Vernal Equinox', '2012-03-20'),
	('Vernal Equinox', '2013-03-20'),
	('Vernal Equinox', '2014-03-20'),
	('Vernal Equinox', '2015-03-20'),
	('Vernal Equinox', '2016-03-20'),
	('Vernal Equinox', '2017-03-20'),
	('Vernal Equinox', '2018-03-20'),
	('Vernal Equinox', '2019-03-20'),
	('Vernal Equinox', '2020-03-20'),
	('Vernal Equinox', '2021-03-20'),
	('Vernal Equinox', '2022-03-20'),
	('Vernal Equinox', '2023-03-20'),
	('Vernal Equinox', '2024-03-20'),
	('Vernal Equinox', '2025-03-20'),
	('Vernal Equinox', '2026-03-20'),
	('Vernal Equinox', '2027-03-20'),
	('Vernal Equinox', '2028-03-20'),
	('Vernal Equinox', '2029-03-20'),
	('Vernal Equinox', '2030-03-20'),
	('Winter Solstice', '2010-12-21'),
	('Winter Solstice', '2011-12-22'),
	('Winter Solstice', '2012-12-21'),
	('Winter Solstice', '2013-12-21'),
	('Winter Solstice', '2014-12-21'),
	('Winter Solstice', '2015-12-22'),
	('Winter Solstice', '2016-12-21'),
	('Winter Solstice', '2017-12-21'),
	('Winter Solstice', '2018-12-21'),
	('Winter Solstice', '2019-12-22'),
	('Winter Solstice', '2020-12-21'),
	('Winter Solstice', '2021-12-21'),
	('Winter Solstice', '2022-12-21'),
	('Winter Solstice', '2023-12-22'),
	('Winter Solstice', '2024-12-21'),
	('Winter Solstice', '2025-12-21'),
	('Winter Solstice', '2026-12-21'),
	('Winter Solstice', '2027-12-22'),
	('Winter Solstice', '2028-12-21'),
	('Winter Solstice', '2029-12-21'),
	('Winter Solstice', '2030-12-21'),
	('Autumnal Equinox', '2010-09-23'),
	('Autumnal Equinox', '2011-09-23'),
	('Autumnal Equinox', '2012-09-22'),
	('Autumnal Equinox', '2013-09-22'),
	('Autumnal Equinox', '2014-09-23'),
	('Autumnal Equinox', '2015-09-23'),
	('Autumnal Equinox', '2016-09-22'),
	('Autumnal Equinox', '2017-09-22'),
	('Autumnal Equinox', '2018-09-23'),
	('Autumnal Equinox', '2019-09-23'),
	('Autumnal Equinox', '2020-09-22'),
	('Autumnal Equinox', '2021-09-22'),
	('Autumnal Equinox', '2022-09-23'),
	('Autumnal Equinox', '2023-09-23'),
	('Autumnal Equinox', '2024-09-22'),
	('Autumnal Equinox', '2025-09-22'),
	('Autumnal Equinox', '2026-09-23'),
	('Autumnal Equinox', '2027-09-23'),
	('Autumnal Equinox', '2028-09-22'),
	('Autumnal Equinox', '2029-09-22'),
	('Autumnal Equinox', '2030-09-22');

INSERT INTO {$db_prefix}calendar_holidays
	(title, event_date)
VALUES ('Independence Day', '1004-07-04'),
	('Cinco de Mayo', '1004-05-05'),
	('Flag Day', '1004-06-14'),
	('Veterans Day', '1004-11-11'),
	('Groundhog Day', '1004-02-02'),
	('Thanksgiving', '2010-11-25'),
	('Thanksgiving', '2011-11-24'),
	('Thanksgiving', '2012-11-22'),
	('Thanksgiving', '2013-11-28'),
	('Thanksgiving', '2014-11-27'),
	('Thanksgiving', '2015-11-26'),
	('Thanksgiving', '2016-11-24'),
	('Thanksgiving', '2017-11-23'),
	('Thanksgiving', '2018-11-22'),
	('Thanksgiving', '2019-11-28'),
	('Thanksgiving', '2020-11-26'),
	('Thanksgiving', '2021-11-25'),
	('Thanksgiving', '2022-11-24'),
	('Thanksgiving', '2023-11-23'),
	('Thanksgiving', '2024-11-28'),
	('Thanksgiving', '2025-11-27'),
	('Thanksgiving', '2026-11-26'),
	('Thanksgiving', '2027-11-25'),
	('Thanksgiving', '2028-11-23'),
	('Thanksgiving', '2029-11-22'),
	('Thanksgiving', '2030-11-28'),
	('Memorial Day', '2010-05-31'),
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
	('Memorial Day', '2021-05-31'),
	('Memorial Day', '2022-05-30'),
	('Memorial Day', '2023-05-29'),
	('Memorial Day', '2024-05-27'),
	('Memorial Day', '2025-05-26'),
	('Memorial Day', '2026-05-25'),
	('Memorial Day', '2027-05-31'),
	('Memorial Day', '2028-05-29'),
	('Memorial Day', '2029-05-28'),
	('Memorial Day', '2030-05-27'),
	('Labor Day', '2010-09-06'),
	('Labor Day', '2011-09-05'),
	('Labor Day', '2012-09-03'),
	('Labor Day', '2013-09-02'),
	('Labor Day', '2014-09-01'),
	('Labor Day', '2015-09-07'),
	('Labor Day', '2016-09-05'),
	('Labor Day', '2017-09-04'),
	('Labor Day', '2018-09-03'),
	('Labor Day', '2019-09-02'),
	('Labor Day', '2020-09-07'),
	('Labor Day', '2021-09-06'),
	('Labor Day', '2022-09-05'),
	('Labor Day', '2023-09-04'),
	('Labor Day', '2024-09-02'),
	('Labor Day', '2025-09-01'),
	('Labor Day', '2026-09-07'),
	('Labor Day', '2027-09-06'),
	('Labor Day', '2028-09-04'),
	('Labor Day', '2029-09-03'),
	('Labor Day', '2030-09-02'),
	('D-Day', '1004-06-06');
---#

/******************************************************************************/
--- Add Attachments index
/******************************************************************************/
---# Create new index on Attachments
CREATE INDEX idx_id_thumb ON {$db_prefix}attachments (id_thumb);
---#

/******************************************************************************/
--- Fix mods columns
/******************************************************************************/
---# make members mod col nullable
---{
$request = upgrade_query("
		SELECT COLUMN_NAME, COLUMN_TYPE
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = '" . Config::$db_name . "' AND  TABLE_NAME = '" . Config::$db_prefix . "members' AND
			COLUMN_DEFAULT IS NULL AND COLUMN_KEY <> 'PRI' AND IS_NULLABLE = 'NO' AND
			COLUMN_NAME NOT IN ('buddy_list', 'signature', 'ignore_boards')
	");


while ($row = Db::$db->fetch_assoc($request))
{
		upgrade_query("
			ALTER TABLE {$db_prefix}members
			MODIFY " . $row['COLUMN_NAME'] . " " . $row['COLUMN_TYPE'] . " NULL
		");
}
---}
---#

---# make boards mod col nullable
---{
$request = upgrade_query("
		SELECT COLUMN_NAME, COLUMN_TYPE
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = '" . Config::$db_name . "' AND  TABLE_NAME = '" . Config::$db_prefix . "boards' AND
			COLUMN_DEFAULT IS NULL AND COLUMN_KEY <> 'PRI' AND IS_NULLABLE = 'NO' AND
			COLUMN_NAME NOT IN ('description')
	");


while ($row = Db::$db->fetch_assoc($request))
{
		upgrade_query("
			ALTER TABLE {$db_prefix}boards
			MODIFY " . $row['COLUMN_NAME'] . " " . $row['COLUMN_TYPE'] . " NULL
		");
}
---}
---#

---# make topics mod col nullable
---{
$request = upgrade_query("
		SELECT COLUMN_NAME, COLUMN_TYPE
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = '" . Config::$db_name . "' AND  TABLE_NAME = '" . Config::$db_prefix . "topics' AND
			COLUMN_DEFAULT IS NULL AND COLUMN_KEY <> 'PRI' AND IS_NULLABLE = 'NO'
	");


while ($row = Db::$db->fetch_assoc($request))
{
		upgrade_query("
			ALTER TABLE {$db_prefix}topics
			MODIFY " . $row['COLUMN_NAME'] . " " . $row['COLUMN_TYPE'] . " NULL
		");
}
---}
---#

/******************************************************************************/
--- Update log_spider_stats
/******************************************************************************/
---# Allow for hyper aggressive crawlers
ALTER TABLE {$db_prefix}log_spider_stats CHANGE page_hits page_hits INT NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Update policy & agreement settings
/******************************************************************************/
---# Strip -utf8 from policy settings
---{
$utf8_policy_settings = array();
foreach(Config::$modSettings AS $k => $v)
{
	if ((substr($k, 0, 7) === 'policy_') && (substr($k, -5) === '-utf8'))
		$utf8_policy_settings[$k] = $v;
}
$adds = array();
$deletes = array();
foreach($utf8_policy_settings AS $var => $val)
{
	// Note this works on the policy_updated_ strings as well...
	$language = substr($var, 7, strlen($var) - 12);
	if (!array_key_exists('policy_' . $language, Config::$modSettings))
	{
		$adds[] =  '(\'policy_' . $language . '\', \'' . Db::$db->escape_string($val) . '\')';
		$deletes[] = '\'' . $var . '\'';
	}
}
if (!empty($adds))
{
	upgrade_query("
		INSERT INTO {$db_prefix}settings (variable, value)
			VALUES " . implode(', ', $adds)
	);
}
if (!empty($deletes))
{
	upgrade_query("
		DELETE FROM {$db_prefix}settings
			WHERE variable IN (" . implode(', ', $deletes) . ")
	");
}

---}
---#

---# Strip -utf8 from agreement file names
---{
$files = glob(Config::$boarddir . '/agreement.*-utf8.txt');
foreach($files AS $filename)
{
	$newfile = substr($filename, 0, strlen($filename) - 9) . '.txt';
	// Do not overwrite existing files
	if (!file_exists($newfile))
		@rename($filename, $newfile);
}

---}
---#

---# Fix missing values in log_actions
---{
	$current_substep = !isset($_GET['substep']) ? 0 : (int) $_GET['substep'];

	// Setup progress bar
	if (!isset($_GET['total_fixes']) || !isset($_GET['a']) || !isset($_GET['last_action_id']))
	{
		$request = Db::$db->query('', '
			SELECT COUNT(*)
				FROM {db_prefix}log_actions
				WHERE id_member = {int:blank_id}
				AND action IN ({array_string:target_actions})',
			array(
				'blank_id' => 0,
				'target_actions' => array('policy_accepted', 'agreement_accepted'),
			)
		);
		list ($step_progress['total']) = Db::$db->fetch_row($request);
		$_GET['total_fixes'] = $step_progress['total'];
		Db::$db->free_result($request);

		$_GET['a'] = 0;
		$_GET['last_action_id'] = 0;
	}

	$step_progress['name'] = 'Fixing missing IDs in log_actions';
	$step_progress['current'] = $_GET['a'];
	$step_progress['total'] = $_GET['total_fixes'];

	// Main process loop
	$limit = 10000;
	$is_done = false;
	while (!$is_done)
	{
		// Keep looping at the current step.
		nextSubstep($current_substep);

		$extras = array();
		$request = Db::$db->query('', '
			SELECT id_action, extra
				FROM {db_prefix}log_actions
				WHERE id_member = {int:blank_id}
				AND action IN ({array_string:target_actions})
				AND id_action >  {int:last}
				ORDER BY id_action
				LIMIT {int:limit}',
			array(
				'blank_id' => 0,
				'target_actions' => array('policy_accepted', 'agreement_accepted'),
				'last' => $_GET['last_action_id'],
				'limit' => $limit,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
			$extras[$row['id_action']] = $row['extra'];
		Db::$db->free_result($request);

		if (empty($extras))
			$is_done = true;
		else
			$_GET['last_action_id'] = max(array_keys($extras));

		foreach ($extras AS $id => $extra_ser)
		{
			$extra = upgrade_unserialize($extra_ser);
			if ($extra === false)
				continue;

			if (!empty($extra['applicator']))
			{
				$request = Db::$db->query('', '
					UPDATE {db_prefix}log_actions
						SET id_member = {int:id_member}
						WHERE id_action = {int:id_action}',
					array(
						'id_member' => $extra['applicator'],
						'id_action' => $id,
					)
				);
			}
		}
		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];
	}

	$step_progress = array();
	unset($_GET['a']);
	unset($_GET['last_action_id']);
	unset($_GET['total_fixes']);
---}
---#