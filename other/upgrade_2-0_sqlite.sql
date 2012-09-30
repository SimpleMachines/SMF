/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Updating custom fields.
/******************************************************************************/

---# Adding search ability to custom fields.
---{
$smcFunc['db_alter_table']('{db_prefix}custom_fields', array(
	'add' => array(
		'can_search' => array(
			'name' => 'can_search',
			'null' => false,
			'default' => 0,
			'type' => 'smallint',
			'size' => 255,
			'auto' => false,
		),
	),
));

if (isset($modSettings['smfVersion']) && $modSettings['smfVersion'] < '2.0 Beta 4')
{
upgrade_query("
	UPDATE {$db_prefix}custom_fields
	SET private = 3
	WHERE private = 2");
}
---}
---#

---# Adding new custom fields columns.
---{
$smcFunc['db_alter_table']('{db_prefix}custom_fields', array(
	'add' => array(
		'enclose' => array(
			'name' => 'enclose',
			'null' => false,
			'default' => '',
			'type' => 'text',
			'auto' => false,
		),
	)
));

$smcFunc['db_alter_table']('{db_prefix}custom_fields', array(
	'add' => array(
		'placement' => array(
			'name' => 'placement',
			'null' => false,
			'default' => '',
			'type' => 'smallint',
			'auto' => false,
		),
	)
));
---}
---#

---# Fixing default value for the "show_profile" column
---{
$smcFunc['db_alter_table']('{db_prefix}custom_fields', array(
	'change' => array(
		'show_profile' => array(
			'name' => 'show_profile',
			'null' => false,
			'default' => 'forumprofile',
			'type' => 'varchar(20)',
			'auto' => false,
		),
	)
));
---}
---#

ALTER TABLE {$db_prefix}custom_fields
ALTER COLUMN show_profile DEFAULT 'forumprofile';
---#

/******************************************************************************/
--- Adding search engine tracking.
/******************************************************************************/

---# Creating spider table.
CREATE TABLE {$db_prefix}spiders (
	id_spider integer primary key,
	spider_name varchar(255) NOT NULL,
	user_agent varchar(255) NOT NULL,
	ip_info varchar(255) NOT NULL
);
---#

---# Inserting the search engines.
---{
$smcFunc['db_insert']('ignore',
	'{db_prefix}spiders',
	array('id_spider' => 'int', 'spider_name' => 'string-255', 'user_agent' => 'string-255', 'ip_info' => 'string-255'),
	array(
		array(1, 'Google', 'googlebot', ''),
		array(2, 'Yahoo!', 'slurp', ''),
		array(3, 'MSN', 'msnbot', ''),
		array(4, 'Google (Mobile)', 'Googlebot-Mobile', ''),
		array(5, 'Google (Image)', 'Googlebot-Image', ''),
		array(6, 'Google (AdSense)', 'Mediapartners-Google', ''),
		array(7, 'Google (Adwords)', 'AdsBot-Google', ''),
		array(8, 'Yahoo! (Mobile)', 'YahooSeeker/M1A1-R2D2', ''),
		array(9, 'Yahoo! (Image)', 'Yahoo-MMCrawler', ''),
		array(10, 'MSN (Mobile)', 'MSNBOT_Mobile', ''),
		array(11, 'MSN (Media)', 'msnbot-media', ''),
		array(12, 'Cuil', 'twiceler', ''),
		array(13, 'Ask', 'Teoma', ''),
		array(14, 'Baidu', 'Baiduspider', ''),
		array(15, 'Gigablast', 'Gigabot', ''),
		array(16, 'InternetArchive', 'ia_archiver-web.archive.org', ''),
		array(17, 'Alexa', 'ia_archiver', ''),
		array(18, 'Omgili', 'omgilibot', ''),
		array(19, 'EntireWeb', 'Speedy Spider', '')
	),
	array('user_agent')
);
---}
---#

---# Removing a spider.
---{
	upgrade_query("
		DELETE FROM {$db_prefix}spiders
		WHERE user_agent = 'yahoo'
			AND spider_name = 'Yahoo! (Publisher)'
	");
---}
---#

---# Creating spider hit tracking table.
CREATE TABLE {$db_prefix}log_spider_hits (
	id_spider integer NOT NULL default '0',
	session varchar(32) NOT NULL default '',
	log_time int NOT NULL,
	url varchar(255) NOT NULL,
	processed smallint NOT NULL default '0'
);

CREATE INDEX {$db_prefix}log_spider_hits_id_spider ON {$db_prefix}log_spider_hits (id_spider);
CREATE INDEX {$db_prefix}log_spider_hits_log_time ON {$db_prefix}log_spider_hits (log_time);
CREATE INDEX {$db_prefix}log_spider_hits_processed ON {$db_prefix}log_spider_hits (processed);
---#

---# Creating spider statistic table.
CREATE TABLE {$db_prefix}log_spider_stats (
	id_spider integer NOT NULL default '0',
	unique_visits smallint NOT NULL default '0',
	page_hits smallint NOT NULL default '0',
	last_seen int NOT NULL default '0',
	stat_date date NOT NULL default '0001-01-01',
	PRIMARY KEY (stat_date, id_spider)
);
---#

/******************************************************************************/
--- Adding new forum settings.
/******************************************************************************/

---# Enable cache if upgrading from 2.0 beta 1 and lower.
---{
if (isset($modSettings['smfVersion']) && $modSettings['smfVersion'] <= '2.0 Beta 1')
{
	$request = upgrade_query("
		SELECT value
		FROM {$db_prefix}settings
		WHERE variable = 'cache_enable'");
	list ($cache_enable) = $smcFunc['db_fetch_row']($request);

	// No cache before
	if ($smcFunc['db_num_rows']($request) == 0)
		upgrade_query("
			INSERT INTO {$db_prefix}settings
				(variable, value)
			VALUES ('cache_enable', '1')");
	elseif (empty($cache_enable))
		upgrade_query("
			UPDATE {$db_prefix}settings
			SET value = '1'
			WHERE variable = 'cache_enable'");
}
---}
---#

---# Adding advanced password brute force protection to "members" table...
---{
$smcFunc['db_alter_table']('{db_prefix}members', array(
	'add' => array(
		'passwd_flood' => array(
			'name' => 'passwd_flood',
			'null' => false,
			'default' => '',
			'type' => 'varchar',
			'size' => 12,
			'auto' => false,
		),
	)
));
---}
---#

---# Ensuring forum width setting present...
---{
// Don't do this twice!
$smcFunc['db_insert']('ignore',
	'{db_prefix}themes',
	array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-255'),
	array(1, 'forum_width', '90%'),
	array('id_theme', 'variable', 'value')
);
---}
---#

/******************************************************************************/
--- Adding weekly maintenance task.
/******************************************************************************/

---# Adding weekly maintenance task...
---{
$smcFunc['db_insert']('ignore',
	'{db_prefix}scheduled_tasks',
	array(
		'next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int',
		'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string',
	),
	array(
		0, 0, 1, 'w', 0, 'weekly_maintenance',
	),
	array('task')
);
---}
---#

---# Setting the birthday email template if not set...
---{
if (!isset($modSettings['birthday_email']))
{
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('birthday_email', 'happy_birthday')");
}
---}
---#

/******************************************************************************/
--- Adding log pruning.
/******************************************************************************/

---# Adding pruning option...
---{
$smcFunc['db_insert']('ignore',
	'{db_prefix}settings',
	array('variable' => 'string-255', 'value' => 'string-65534'),
	array('pruningOptions', '30,180,180,180,30'),
	array('variable')
);
---}
---#

/******************************************************************************/
--- Updating mail queue functionality.
/******************************************************************************/

---# Adding type to mail queue...
---{
$smcFunc['db_alter_table']('{db_prefix}mail_queue', array(
	'add' => array(
		'private' => array(
			'name' => 'private',
			'null' => false,
			'default' => 0,
			'type' => 'smallint',
			'size' => 1,
			'auto' => false,
		),
	)
));
---}
---#

/******************************************************************************/
--- Updating attachments.
/******************************************************************************/

---# Adding multiple attachment path functionality.
---{
$smcFunc['db_alter_table']('{db_prefix}attachments', array(
	'add' => array(
		'id_folder' => array(
			'name' => 'id_folder',
			'null' => false,
			'default' => 1,
			'type' => 'smallint',
			'size' => 255,
			'auto' => false,
		),
	)
));
---}
---#

---# Adding file hash.
---{
$smcFunc['db_alter_table']('{db_prefix}attachments', array(
	'add' => array(
		'file_hash' => array(
			'name' => 'file_hash',
			'null' => false,
			'default' => '',
			'type' => 'varchar',
			'size' => 40,
			'auto' => false,
		),
	)
));
---}
---#

/******************************************************************************/
--- Providing more room for apf options.
/******************************************************************************/

---# Changing field_options column to a larger field type...
---{
$smcFunc['db_alter_table']('{db_prefix}custom_fields', array(
	'change' => array(
		'aim' => array(
			'name' => 'field_options',
			'null' => false,
			'type' => 'text',
			'default' => ''
		)
	)
));
---}
---#

/******************************************************************************/
--- Adding extra columns to polls.
/******************************************************************************/

---# Adding reset poll timestamp and guest voters counter...
---{
$smcFunc['db_alter_table']('{db_prefix}polls', array(
	'add' => array(
		'reset_poll' => array(
			'name' => 'reset_poll',
			'null' => false,
			'default' => 0,
			'type' => 'int',
			'size' => 10,
			'auto' => false,
		),
		'num_guest_voters' => array(
			'name' => 'num_guest_voters',
			'null' => false,
			'default' => 0,
			'type' => 'int',
			'size' => 10,
			'auto' => false,
		),
	)
));
---}
---#

---# Fixing guest voter tallys on existing polls...
---{
$request = upgrade_query("
	SELECT p.id_poll, count(lp.id_member) as guest_voters
	FROM {$db_prefix}polls AS p
		LEFT JOIN {$db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll AND lp.id_member = 0)
	WHERE lp.id_member = 0
		AND p.num_guest_voters = 0
	GROUP BY p.id_poll");

while ($request && $row = $smcFunc['db_fetch_assoc']($request))
	upgrade_query("
		UPDATE {$db_prefix}polls
		SET num_guest_voters = ". $row['guest_voters']. "
		WHERE id_poll = " . $row['id_poll'] . "
			AND num_guest_voters = 0");
---}
---#

/******************************************************************************/
--- Adding restore topic from recycle.
/******************************************************************************/

---# Adding restore from recycle feature...
---{
$smcFunc['db_alter_table']('{db_prefix}topics', array(
	'add' => array(
		'id_previous_board' => array(
			'name' => 'id_previous_board',
			'null' => false,
			'default' => 0,
			'type' => 'smallint',
			'auto' => false,
		),
		'id_previous_topic' => array(
			'name' => 'id_previous_topic',
			'null' => false,
			'default' => 0,
			'type' => 'int',
			'auto' => false,
		),
	)
));
---}
---#

/******************************************************************************/
--- Fixing aim on members for longer nicks.
/******************************************************************************/

---# Changing 'aim' to varchar to allow using email...
---{
$smcFunc['db_alter_table']('{db_prefix}members', array(
	'change' => array(
		'aim' => array(
			'name' => 'aim',
			'null' => false,
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		)
	)
));
---}
---#

/******************************************************************************/
--- Allow for longer calendar event/holiday titles.
/******************************************************************************/

---# Changing event title column to a larger field type...
---{
$smcFunc['db_alter_table']('{db_prefix}calendar', array(
	'change' => array(
		'title' => array(
			'name' => 'title',
			'null' => false,
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		)
	)
));
---}
---#

---# Changing holiday title column to a larger field type...
---{
$smcFunc['db_alter_table']('{db_prefix}calendar_holidays', array(
	'change' => array(
		'title' => array(
			'name' => 'title',
			'null' => false,
			'type' => 'varchar',
			'size' => 255,
			'default' => ''
		)
	)
));
---}
---#

/******************************************************************************/
--- Making changes to the package manager.
/******************************************************************************/

---# Changing URL to SMF package server...
UPDATE {$db_prefix}package_servers
SET url = 'http://custom.simplemachines.org/packages/mods'
WHERE url = 'http://mods.simplemachines.org';
---#

/******************************************************************************/
--- Adding new indexes to the topics table.
/******************************************************************************/

---# Adding index member_started...
CREATE INDEX {$db_prefix}topics_member_started ON {$db_prefix}topics (id_member_started, id_board);
---#

---# Adding index last_message_sticky...
CREATE INDEX {$db_prefix}topics_last_message_sticky ON {$db_prefix}topics (id_board, is_sticky, id_last_msg);
---#

---# Adding index board_news...
CREATE INDEX {$db_prefix}topics_board_news ON {$db_prefix}topics (id_board, id_first_msg);
---#

/******************************************************************************/
--- Adding new indexes to members table.
/******************************************************************************/

---# Adding index on total_time_logged_in...
CREATE INDEX {$db_prefix}members_total_time_logged_in ON {$db_prefix}members (total_time_logged_in);
---#

---# Adding index on id_theme...
CREATE INDEX {$db_prefix}members_id_theme ON {$db_prefix}members (id_theme);
---#

---# Adding index on real_name...
CREATE INDEX {$db_prefix}members_real_name ON {$db_prefix}members (real_name);
---#

/******************************************************************************/
--- Adding new indexes to messages table.
/******************************************************************************/

---# Adding index id_member_msg...
CREATE INDEX {$db_prefix}messages_id_member_msg ON {$db_prefix}messages (id_member, approved, id_msg);
---#

---# Adding index current_topic...
CREATE INDEX {$db_prefix}messages_current_topic ON {$db_prefix}messages (id_topic, id_msg, id_member, approved);
---#

---# Adding index related_ip...
CREATE INDEX {$db_prefix}messages_related_ip ON {$db_prefix}messages (id_member, poster_ip, id_msg);
---#

/******************************************************************************/
--- Adding new indexes to attachments table.
/******************************************************************************/

---# Adding index on attachment_type...
CREATE INDEX {$db_prefix}attachments_attachment_type ON {$db_prefix}attachments (attachment_type);
---#

/******************************************************************************/
--- Dropping unnecessary indexes...
/******************************************************************************/

---# Removing index on hits...
---{
$smcFunc['db_remove_index']($db_prefix . 'log_activity', $db_prefix . 'log_activity_hits');
---}
---#

/******************************************************************************/
--- Adding new personal message setting.
/******************************************************************************/

---# Adding column that stores the PM receiving setting...
---{
$smcFunc['db_alter_table']('{db_prefix}members', array(
	'add' => array(
		'pm_receive_from' => array(
			'name' => 'pm_receive_from',
			'null' => false,
			'type' => 'tinyint',
			'size' => 4,
			'default' => '1'
		)
	)
));
---}
---#

---# Enable the buddy and ignore lists if we have not done so thus far...
---{

// Don't do this if we've done this already.
if (empty($modSettings['dont_repeat_buddylists']))
{
	// Make sure the pm_receive_from column has the right default value - early adoptors might have a '0' set here.
	$smcFunc['db_alter_table']('{db_prefix}members', array(
		'change' => array(
			'pm_receive_from' => array(
				'name' => 'pm_receive_from',
				'null' => false,
				'type' => 'tinyint',
				'size' => 4,
				'default' => '1'
			)
		)
	));

	// Update previous ignore lists if they're set to ignore all.
	upgrade_query("
		UPDATE {$db_prefix}members
		SET pm_receive_from = 3, pm_ignore_list = ''
		WHERE pm_ignore_list = '*'");

	// Enable buddy and ignore lists.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('enable_buddylist', '1')");

	// Ignore posts made by ignored users by default, too.
	upgrade_query("
		REPLACE INTO {$db_prefix}themes
			(id_member, id_theme, variable, value)
		VALUES
			(-1, 1, 'posts_apply_ignore_list', '1')");

	// Make sure not to skip this step next time we run this.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('dont_repeat_buddylists', '1')");
}

// And yet, and yet... We might have a small hiccup here...
if (!empty($modSettings['dont_repeat_buddylists']) && !isset($modSettings['enable_buddylist']))
{
	// Correct RC3 adopters setting here...
	if (isset($modSettings['enable_buddylists']))
	{
		upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('enable_buddylist', '". $modSettings['enable_buddylists']. "')");
	}
	else
	{
		// This should never happen :)
		upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('enable_buddylist', '1')");
	}
}
---}
---#

/******************************************************************************/
--- Adding settings for attachments and avatars.
/******************************************************************************/

---# Add new security settings for attachments and avatars...
---{

// Don't do this if we've done this already.
if (!isset($modSettings['attachment_image_reencode']))
{
	// Enable image re-encoding by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('attachment_image_reencode', '1')");
}
if (!isset($modSettings['attachment_image_paranoid']))
{
	// Disable draconic checks by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('attachment_image_paranoid', '0')");
}
if (!isset($modSettings['avatar_reencode']))
{
	// Enable image re-encoding by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('avatar_reencode', '1')");
}
if (!isset($modSettings['avatar_paranoid']))
{
	// Disable draconic checks by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('avatar_paranoid', '0')");
}

---}
---#

---# Add other attachment settings...
---{
if (!isset($modSettings['attachment_thumb_png']))
{
	// Make image attachment thumbnail as PNG by default.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('attachment_thumb_png', '1')");
}

---}
---#

/******************************************************************************/
--- Installing new default theme...
/******************************************************************************/

---# Installing theme settings...
---{
// This is Grudge's secret "I'm not a developer" theme install code - keep this quiet ;)

// Firstly, I'm going out of my way to not do this twice!
if ((!isset($modSettings['smfVersion']) || $modSettings['smfVersion'] <= '2.0 RC2') && empty($modSettings['dont_repeat_theme_core']))
{
	// Check it's not already here, just in case.
	$theme_request = upgrade_query("
		SELECT id_theme
		FROM {$db_prefix}themes
		WHERE variable = 'theme_dir'
			AND value LIKE '%core'");
	// Only do the upgrade if it doesn't find the theme already.
	if ($smcFunc['db_num_rows']($theme_request) == 0)
	{
		// Try to get some settings from the current default theme.
		$request = upgrade_query("
			SELECT t1.value AS theme_dir, t2.value AS theme_url, t3.value AS images_url
			FROM {$db_prefix}themes AS t1, {$db_prefix}themes AS t2, {$db_prefix}themes AS t3
			WHERE t1.id_theme = 1
				AND t1.id_member = 0
				AND t1.variable = 'theme_dir'
				AND t2.id_theme = 1
				AND t2.id_member = 0
				AND t2.variable = 'theme_url'
				AND t3.id_theme = 1
				AND t3.id_member = 0
				AND t3.variable = 'images_url'
			LIMIT 1");
		if ($smcFunc['db_num_rows']($request) != 0)
		{
			$curve = $smcFunc['db_fetch_assoc']($request);

			if (substr_count($curve['theme_dir'], 'default') === 1)
				$core['theme_dir'] = strtr($curve['theme_dir'], array('default' => 'core'));
			if (substr_count($curve['theme_url'], 'default') === 1)
				$core['theme_url'] = strtr($curve['theme_url'], array('default' => 'core'));
			if (substr_count($curve['images_url'], 'default') === 1)
				$core['images_url'] = strtr($curve['images_url'], array('default' => 'core'));
		}
		$smcFunc['db_free_result']($request);

		if (!isset($core['theme_dir']))
			$core['theme_dir'] = addslashes($GLOBALS['boarddir']) . '/Themes/core';
		if (!isset($core['theme_url']))
			$core['theme_url'] = $GLOBALS['boardurl'] . '/Themes/core';
		if (!isset($core['images_url']))
			$core['images_url'] = $GLOBALS['boardurl'] . '/Themes/core/images';

		// Get an available id_theme first...
		$request = upgrade_query("
			SELECT MAX(id_theme) + 1
			FROM {$db_prefix}themes");
		list ($id_core_theme) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// Insert the core theme into the tables.
		$smcFunc['db_insert']('ignore',
			'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-255'),
				array(
					array(0, $id_core_theme, 'name', 'Core Theme'),
					array(0, $id_core_theme, 'theme_url', $core['theme_url']),
					array(0, $id_core_theme, 'images_url', $core['images_url']),
					array(0, $id_core_theme, 'theme_dir', $core['theme_dir'])
				),
				array()
		);

		// Update the name of the default theme in the database.
		upgrade_query("
			UPDATE {$db_prefix}themes
			SET value = 'SMF Default Theme - Curve'
			WHERE id_theme = 1
				AND variable = 'name'");

		$newSettings = array();
		// Now that we have the old theme details - switch anyone who used the default to it (Make sense?!)
		if (!empty($modSettings['theme_default']) && $modSettings['theme_default'] == 1)
			$newSettings[] = "('theme_default', $id_core_theme)";
		// Did guests use to use the default?
		if (!empty($modSettings['theme_guests']) && $modSettings['theme_guests'] == 1)
			$newSettings[] = "('theme_guests', $id_core_theme)";

		// If known themes aren't set, let's just pick all themes available.
		if (empty($modSettings['knownThemes']))
		{
			$request = upgrade_query("
				SELECT DISTINCT id_theme
				FROM {$db_prefix}themes");
			$themes = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$themes[] = $row['id_theme'];
			$modSettings['knownThemes'] = implode(',', $themes);
			upgrade_query("
				UPDATE {$db_prefix}settings
				SET value = '$modSettings[knownThemes]'
				WHERE variable = 'knownThemes'");
		}

		// Known themes.
		$allThemes = explode(',', $modSettings['knownThemes']);
		$allThemes[] = $id_core_theme;
		$newSettings[] = "('knownThemes', '" . implode(',', $allThemes) . "')";

		upgrade_query("
			REPLACE INTO {$db_prefix}settings
				(variable, value)
			VALUES
				" . implode(', ', $newSettings));

		// What about members?
		upgrade_query("
			UPDATE {$db_prefix}members
			SET id_theme = $id_core_theme
			WHERE id_theme = 1");

		// Boards?
		upgrade_query("
			UPDATE {$db_prefix}boards
			SET id_theme = $id_core_theme
			WHERE id_theme = 1");

		// The other themes used to use core as their base theme.
		if (isset($core['theme_dir']) && isset($core['theme_url']))
		{
			$coreBasedThemes = array_diff($allThemes, array(1));

			// Exclude the themes that already have a base_theme_dir.
			$request = upgrade_query("
				SELECT DISTINCT id_theme
				FROM {$db_prefix}themes
				WHERE variable = 'base_theme_dir'");
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$coreBasedThemes = array_diff($coreBasedThemes, array($row['id_theme']));
			$smcFunc['db_free_result']($request);

			// Only base themes if there are templates that need a fall-back.
			$insertRows = array();
			$request = upgrade_query("
				SELECT id_theme, value AS theme_dir
				FROM {$db_prefix}themes
				WHERE id_theme IN (" . implode(', ', $coreBasedThemes) . ")
					AND id_member = 0
					AND variable = 'theme_dir'");
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if (!file_exists($row['theme_dir'] . '/BoardIndex.template.php') || !file_exists($row['theme_dir'] . '/Display.template.php') || !file_exists($row['theme_dir'] . '/index.template.php') || !file_exists($row['theme_dir'] . '/MessageIndex.template.php') || !file_exists($row['theme_dir'] . '/Settings.template.php'))
				{
					$insertRows[] = "(0, $row[id_theme], 'base_theme_dir', '" . addslashes($core['theme_dir']) . "')";
					$insertRows[] = "(0, $row[id_theme], 'base_theme_url', '" . addslashes($core['theme_url']) . "')";
				}
			}
			$smcFunc['db_free_result']($request);

			if (!empty($insertRows))
				upgrade_query("
					INSERT IGNORE INTO {$db_prefix}themes
						(id_member, id_theme, variable, value)
					VALUES
						" . implode(',
						', $insertRows));
		}
	}
	$smcFunc['db_free_result']($theme_request);

	// This ain't running twice either - not with the risk of log_tables timing us all out!
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('dont_repeat_theme_core', '1')");
}

---}
---#

/******************************************************************************/
--- Installing new smileys sets...
/******************************************************************************/

---# Installing new smiley sets...
---{
// Don't do this twice!
if (empty($modSettings['installed_new_smiley_sets_20']))
{
	// First, the entries.
	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = CONCAT(value, ',aaron,akyhne')
		WHERE variable = 'smiley_sets_known'");

	// Second, the names.
	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = CONCAT(value, '\nAaron\nAkyhne')
		WHERE variable = 'smiley_sets_names'");

	// This ain't running twice either.
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('installed_new_smiley_sets_20', '1')");
}
---}
---#

/******************************************************************************/
--- Adding extra columns to reported post comments
/******************************************************************************/

---# Adding email address and member ip columns...
---{
$smcFunc['db_alter_table']('{db_prefix}log_reported_comments', array(
	'add' => array(
		'email_address' => array(
			'name' => 'email_address',
			'null' => false,
			'default' => '',
			'type' => 'varchar',
			'size' => 255,
			'auto' => false,
		),
		'member_ip' => array(
			'name' => 'member_ip',
			'null' => false,
			'default' => '',
			'type' => 'varchar',
			'size' => 255,
			'auto' => false,
		),
	)
));
---}
---#

/******************************************************************************/
--- Adjusting group types.
/******************************************************************************/

---# Fixing the group types.
---{
// Get the admin group type.
$request = upgrade_query("
	SELECT group_type
	FROM {$db_prefix}membergroups
	WHERE id_group = 1
	LIMIT 1");
list ($admin_group_type) = mysql_fetch_row($request);
mysql_free_result($request);

// Not protected means we haven't updated yet!
if ($admin_group_type != 1)
{
	// Increase by one.
	upgrade_query("
		UPDATE {$db_prefix}membergroups
		SET group_type = group_type + 1
		WHERE group_type > 0");
}
---}
---#

---# Changing the group type for Administrator group.
UPDATE {$db_prefix}membergroups
SET group_type = 1
WHERE id_group = 1;
---#

/******************************************************************************/
--- Adjusting calendar maximum year.
/******************************************************************************/

---# Adjusting calendar maximum year.
---{
if (!isset($modSettings['cal_maxyear']) || $modSettings['cal_maxyear'] == '2010')
{
	upgrade_query("
		REPLACE INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('cal_maxyear', '2020')");
}
---}
---#