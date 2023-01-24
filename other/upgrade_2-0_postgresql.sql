/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding Open ID support.
/******************************************************************************/

---# Adding Open ID Association table...
CREATE TABLE IF NOT EXISTS {$db_prefix}openid_assoc (
	server_url text NOT NULL,
	handle varchar(255) NOT NULL,
	secret text NOT NULL,
	issued int NOT NULL,
	expires int NOT NULL,
	assoc_type varchar(64) NOT NULL,
	PRIMARY KEY (server_url, handle)
);
---#

/******************************************************************************/
--- Updating custom fields.
/******************************************************************************/

---# Adding search ability to custom fields.
---{
if (Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}custom_fields
		ADD COLUMN can_search smallint");

	upgrade_query("
		UPDATE {$db_prefix}custom_fields
		SET can_search = 0");

	upgrade_query("
		ALTER TABLE {$db_prefix}custom_fields
		ALTER COLUMN can_search SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}custom_fields
		ALTER COLUMN can_search SET default '0'");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}custom_fields
		ADD COLUMN can_search smallint NOT NULL default '0'");
}
---}
---#

---# Enhancing privacy settings for custom fields.
---{
if (isset(Config::$modSettings['smfVersion']) && Config::$modSettings['smfVersion'] <= '2.0 Beta 1')
{
upgrade_query("
	UPDATE {$db_prefix}custom_fields
	SET private = 2
	WHERE private = 1");
}
if (isset(Config::$modSettings['smfVersion']) && Config::$modSettings['smfVersion'] < '2.0 Beta 4')
{
upgrade_query("
	UPDATE {$db_prefix}custom_fields
	SET private = 3
	WHERE private = 2");
}
---}
---#

---# Changing default_values column to a larger field type...
ALTER TABLE {$db_prefix}custom_fields
ALTER COLUMN default_value TYPE varchar(255);
---#

---# Adding new custom fields columns.
ALTER TABLE {$db_prefix}custom_fields
ADD enclose text NOT NULL;

ALTER TABLE {$db_prefix}custom_fields
ADD placement smallint NOT NULL default '0';
---#

---# Fixing default value for the "show_profile" column
ALTER TABLE {$db_prefix}custom_fields
ALTER COLUMN show_profile SET DEFAULT 'forumprofile';

UPDATE {$db_prefix}custom_fields
SET show_profile='forumprofile' WHERE show_profile='forumProfile';
---#

/******************************************************************************/
--- Adding new board specific features.
/******************************************************************************/

---# Implementing board redirects.
---{
if (Config::$db_type == 'postgresql' && Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}boards
		ADD COLUMN redirect varchar(255)");

	upgrade_query("
		UPDATE {$db_prefix}boards
		SET redirect = ''");

	upgrade_query("
		ALTER TABLE {$db_prefix}boards
		ALTER COLUMN redirect SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}boards
		ALTER COLUMN redirect SET default ''");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}boards
		ADD COLUMN redirect varchar(255) NOT NULL DEFAULT ''");
}
---}
---#

/******************************************************************************/
--- Adding search engine tracking.
/******************************************************************************/

---# Creating spider sequence.
CREATE SEQUENCE {$db_prefix}spiders_seq;
---#

---# Creating spider table.
CREATE TABLE IF NOT EXISTS {$db_prefix}spiders (
	id_spider smallint NOT NULL default nextval('{$db_prefix}spiders_seq'),
	spider_name varchar(255) NOT NULL,
	user_agent varchar(255) NOT NULL,
	ip_info varchar(255) NOT NULL,
	PRIMARY KEY (id_spider)
);

INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (1, 'Google', 'googlebot', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (2, 'Yahoo!', 'slurp', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (3, 'Bing', 'bingbot', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (4, 'Google (Mobile)', 'Googlebot-Mobile', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (5, 'Google (Image)', 'Googlebot-Image', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (6, 'Google (AdSense)', 'Mediapartners-Google', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (7, 'Google (Adwords)', 'AdsBot-Google', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (8, 'Yahoo! (Mobile)', 'YahooSeeker/M1A1-R2D2', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (9, 'Yahoo! (Image)', 'Yahoo-MMCrawler', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (10, 'Bing (Preview)', 'BingPreview', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (11, 'Bing (Ads)', 'adidxbot', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (12, 'Bing (MSNBot)', 'msnbot', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (13, 'Bing (Media)', 'msnbot-media', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (14, 'Cuil', 'twiceler', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (15, 'Ask', 'Teoma', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (16, 'Baidu', 'Baiduspider', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (17, 'Gigablast', 'Gigabot', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (18, 'InternetArchive', 'ia_archiver-web.archive.org', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (19, 'Alexa', 'ia_archiver', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (20, 'Omgili', 'omgilibot', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (21, 'EntireWeb', 'Speedy Spider', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}spiders	(id_spider, spider_name, user_agent, ip_info) VALUES (22, 'Yandex', 'yandex', '') ON CONFLICT DO NOTHING;
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

---# Sequence for table log_spider_hits.
CREATE SEQUENCE {$db_prefix}log_spider_hits_seq;
---#

---# Creating spider hit tracking table.
CREATE TABLE IF NOT EXISTS {$db_prefix}log_spider_hits (
	id_hit int default nextval('{$db_prefix}log_spider_hits_seq'),
	id_spider smallint NOT NULL default '0',
	log_time int NOT NULL,
	url varchar(255) NOT NULL,
	processed smallint NOT NULL default '0'
);

CREATE INDEX {$db_prefix}log_spider_hits_id_spider ON {$db_prefix}log_spider_hits (id_spider);
CREATE INDEX {$db_prefix}log_spider_hits_log_time ON {$db_prefix}log_spider_hits (log_time);
CREATE INDEX {$db_prefix}log_spider_hits_processed ON {$db_prefix}log_spider_hits (processed);
---#

---# Creating spider statistic table.
CREATE TABLE IF NOT EXISTS {$db_prefix}log_spider_stats (
  id_spider smallint NOT NULL default '0',
  page_hits smallint NOT NULL default '0',
  last_seen int NOT NULL default '0',
  stat_date date NOT NULL default '0001-01-01',
  PRIMARY KEY (stat_date, id_spider)
);
---#

/******************************************************************************/
--- Adding new forum settings.
/******************************************************************************/

---# GDPR compliance settings.
---{
if (!isset(Config::$modSettings['requirePolicyAgreement']))
{
    upgrade_query("
        INSERT INTO {$db_prefix}settings
            (variable, value)
        VALUES ('requirePolicyAgreement', '0')");
}
---}
---#

---# Enable cache if upgrading from 2.0 beta 1 and lower.
---{
if (isset(Config::$modSettings['smfVersion']) && Config::$modSettings['smfVersion'] <= '2.0 Beta 1')
{
	$request = upgrade_query("
		SELECT value
		FROM {$db_prefix}settings
		WHERE variable = 'cache_enable'");
	list ($cache_enable) = Db::$db->fetch_row($request);

	// No cache before
	if (Db::$db->num_rows($request) == 0)
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

---# Ensuring forum width setting present...
---{
// Don't do this twice!
Db::$db->insert('ignore',
	'{db_prefix}themes',
	array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-255'),
	array(1, 'forum_width', '90%'),
	array('id_theme', 'variable')
);
---}
---#

/******************************************************************************/
--- Adding misc functionality.
/******************************************************************************/

---# Converting "log_online".
ALTER TABLE {$db_prefix}log_online DROP CONSTRAINT {$db_prefix}log_online_log_time;
ALTER TABLE {$db_prefix}log_online DROP CONSTRAINT {$db_prefix}log_online_id_member;
DROP TABLE {$db_prefix}log_online;
CREATE TABLE IF NOT EXISTS {$db_prefix}log_online (
  session varchar(32) NOT NULL default '',
  log_time int NOT NULL default '0',
  id_member int NOT NULL default '0',
  id_spider smallint NOT NULL default '0',
  ip bigint NOT NULL default '0',
  url text NOT NULL,
  PRIMARY KEY (session)
);
CREATE INDEX {$db_prefix}log_online_log_time ON {$db_prefix}log_online (log_time);
CREATE INDEX {$db_prefix}log_online_id_member ON {$db_prefix}log_online (id_member);
---#

---# Adding guest voting - part 1...
---{
if (Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ADD COLUMN guest_vote smallint");

	upgrade_query("
		UPDATE {$db_prefix}polls
		SET guest_vote = 0");

	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ALTER COLUMN guest_vote SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ALTER COLUMN guest_vote SET default '0'");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ADD COLUMN guest_vote smallint NOT NULL default '0'");
}
---}
---#

---# Adding guest voting - part 2...
DELETE FROM {$db_prefix}log_polls
WHERE id_member < 0;

ALTER TABLE {$db_prefix}log_polls DROP CONSTRAINT {$db_prefix}log_polls_pkey;

CREATE INDEX {$db_prefix}log_polls_id_poll ON {$db_prefix}log_polls (id_poll, id_member, id_choice);
---#

---# Adding admin log...
---{
if (Config::$db_type == 'postgresql' && Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}log_actions
		ADD COLUMN id_log smallint");

	upgrade_query("
		UPDATE {$db_prefix}log_actions
		SET id_log = 1");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_actions
		ALTER COLUMN id_log SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_actions
		ALTER COLUMN id_log SET default '1'");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}log_actions
		ADD COLUMN id_log smallint NOT NULL default '1'");
}
---}
---#

---# Adding search ability to custom fields.
---{
if (Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}members
		ADD COLUMN passwd_flood varchar(12)");

	upgrade_query("
		UPDATE {$db_prefix}members
		SET passwd_flood = ''");

	upgrade_query("
		ALTER TABLE {$db_prefix}members
		ALTER COLUMN passwd_flood SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}members
		ALTER COLUMN passwd_flood SET default ''");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}members
		ADD COLUMN passwd_flood varchar(12) NOT NULL default ''");
}
---}
---#

/******************************************************************************/
--- Adding weekly maintenance task.
/******************************************************************************/

---# Adding weekly maintenance task...
	INSERT INTO {$db_prefix}scheduled_tasks (next_time, time_offset, time_regularity, time_unit, disabled, task) VALUES (0, 0, 1, 'w', 0, 'weekly_maintenance') ON CONFLICT DO NOTHING;
---#

---# Setting the birthday email template if not set...
---{
if (!isset(Config::$modSettings['birthday_email']))
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
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('pruningOptions', '30,180,180,180,30') ON CONFLICT DO NOTHING;
---#

/******************************************************************************/
--- Updating mail queue functionality.
/******************************************************************************/

---# Adding private to mail queue...
---{
if (Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}mail_queue
		ADD COLUMN private smallint");

	upgrade_query("
		UPDATE {$db_prefix}mail_queue
		SET private = 0");

	upgrade_query("
		ALTER TABLE {$db_prefix}mail_queue
		ALTER COLUMN private SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}mail_queue
		ALTER COLUMN private SET default '0'");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}mail_queue
		ADD COLUMN private smallint NOT NULL default '0'");
}
---}
---#

/******************************************************************************/
--- Updating attachments.
/******************************************************************************/

---# Adding multiple attachment path functionality.
---{
if (Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		ADD COLUMN id_folder smallint");

	upgrade_query("
		UPDATE {$db_prefix}attachments
		SET id_folder = 1");

	upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		ALTER COLUMN id_folder SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		ALTER COLUMN id_folder SET default '1'");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		ADD COLUMN id_folder smallint NOT NULL default '1'");
}
---}
---#

---# Adding file hash.
---{
	upgrade_query("
		ALTER TABLE {$db_prefix}attachments
		ADD COLUMN file_hash varchar(40) NOT NULL default ''");
---}
---#

/******************************************************************************/
--- Adding restore topic from recycle.
/******************************************************************************/

---# Adding restore topic from recycle feature...
---{
if (Config::$db_type == 'postgresql' && Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ADD COLUMN id_previous_board smallint");
	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ADD COLUMN id_previous_topic int");

	upgrade_query("
		UPDATE {$db_prefix}topics
		SET
			id_previous_board = 0,
			id_previous_topic = 0");

	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ALTER COLUMN id_previous_board SET NOT NULL");
	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ALTER COLUMN id_previous_topic SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ALTER COLUMN id_previous_board SET default '0'");
	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ALTER COLUMN id_previous_topic SET default '0'");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ADD COLUMN id_previous_board smallint NOT NULL default '0'");
	upgrade_query("
		ALTER TABLE {$db_prefix}topics
		ADD COLUMN id_previous_topic int NOT NULL default '0'");
}
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
--- Providing more room for ignoring boards.
/******************************************************************************/

---# Changing ignore_boards column to a larger field type...
ALTER TABLE {$db_prefix}members
ALTER COLUMN ignore_boards TYPE text;
---#

/******************************************************************************/
--- Adding default values to a couple of columns in log_subscribed
/******************************************************************************/

---# Adding default value for pending_details column
ALTER TABLE {$db_prefix}log_subscribed
ALTER COLUMN pending_details
SET DEFAULT '';
---#

---# Adding default value for vendor_ref column
ALTER TABLE {$db_prefix}log_subscribed
ALTER COLUMN vendor_ref
SET DEFAULT '';
---#

/*****************************************************************************/
--- Fixing aim on members for longer nicks.
/*****************************************************************************/

---# Changing 'aim' to varchar to allow using email...
ALTER TABLE {$db_prefix}members
ALTER COLUMN aim TYPE varchar(255);

ALTER TABLE {$db_prefix}members
ALTER COLUMN aim SET DEFAULT '';
---#

/*****************************************************************************/
--- Fixing column types in log_errors
/*****************************************************************************/

---# Changing 'ip' from char to varchar
ALTER TABLE {$db_prefix}log_errors
ALTER COLUMN ip TYPE varchar(16);

ALTER TABLE {$db_prefix}log_errors
ALTER COLUMN ip SET DEFAULT '';
---#

---# Changing 'error_type' from char to varchar
ALTER TABLE {$db_prefix}log_errors
ALTER COLUMN error_type TYPE varchar(15);
---#

/******************************************************************************/
--- Allow for longer calendar event/holiday titles.
/******************************************************************************/

---# Changing event title column to a larger field type...
ALTER TABLE {$db_prefix}calendar
ALTER COLUMN title TYPE varchar(255);
---#

---# Changing holiday title column to a larger field type...
ALTER TABLE {$db_prefix}calendar_holidays
ALTER COLUMN title TYPE varchar(255);
---#

/******************************************************************************/
--- Providing more room for apf options.
/******************************************************************************/

---# Changing field_options column to a larger field type...
ALTER TABLE {$db_prefix}custom_fields
ALTER COLUMN field_options TYPE text;
---#

/******************************************************************************/
--- Adding extra columns to polls.
/******************************************************************************/

---# Adding reset poll timestamp and guest voters counter.
---{
if (Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ADD COLUMN reset_poll int");

	upgrade_query("
		UPDATE {$db_prefix}polls
		SET reset_poll = '0'
		WHERE reset_poll < 1");

	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ALTER COLUMN reset_poll SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ALTER COLUMN reset_poll SET default '0'");

	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ADD COLUMN num_guest_voters int");

	upgrade_query("
		UPDATE {$db_prefix}polls
		SET num_guest_voters = '0'
		WHERE num_guest_voters < 1");

	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ALTER COLUMN num_guest_voters SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ALTER COLUMN num_guest_voters SET default '0'");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ADD COLUMN reset_poll int NOT NULL default '0'");
	upgrade_query("
		ALTER TABLE {$db_prefix}polls
		ADD COLUMN num_guest_voters int NOT NULL default '0'");
}
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

while ($request && $row = Db::$db->fetch_assoc($request))
	upgrade_query("
		UPDATE {$db_prefix}polls
		SET num_guest_voters = ". $row['guest_voters']. "
		WHERE id_poll = " . $row['id_poll'] . "
			AND num_guest_voters = 0");
---}
---#

/*****************************************************************************/
--- Fixing a bug with the inet_aton() function.
/*****************************************************************************/

---# Changing inet_aton function to use bigint instead of int...
CREATE OR REPLACE FUNCTION INET_ATON(text) RETURNS bigint AS
  'SELECT
	CASE WHEN
		$1 !~ ''^[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?\.[0-9]?[0-9]?[0-9]?$'' THEN 0
	ELSE
		split_part($1, ''.'', 1)::int8 * (256 * 256 * 256) +
		split_part($1, ''.'', 2)::int8 * (256 * 256) +
		split_part($1, ''.'', 3)::int8 * 256 +
		split_part($1, ''.'', 4)::int8
	END AS result'
LANGUAGE 'sql';
---#

/*****************************************************************************/
--- Making additional changes to handle results from fixed inet_aton().
/*****************************************************************************/

---# Adding an IFNULL to handle 8-bit integers returned by inet_aton
CREATE OR REPLACE FUNCTION IFNULL(int8, int8) RETURNS int8 AS
  'SELECT COALESCE($1, $2) AS result'
LANGUAGE 'sql';
---#

---# Changing ip column in log_online to int8
ALTER TABLE {$db_prefix}log_online
ALTER COLUMN ip TYPE int8;
---#

/******************************************************************************/
--- Dropping unnecessary indexes...
/******************************************************************************/

---# Removing index on hits...
---{
Db::$db->remove_index(Config::$db_prefix . 'log_activity', Config::$db_prefix . 'log_activity_hits');
---}
---#


/******************************************************************************/
--- Adding new personal message setting.
/******************************************************************************/

---# Adding column that stores the PM receiving setting...
---{
	upgrade_query("
		ALTER TABLE {$db_prefix}members
		ADD COLUMN pm_receive_from smallint NOT NULL default '1'");
---}
---#

---# Enable the buddy and ignore lists if we have not done so thus far...
---{

// Don't do this if we've done this already.
if (empty(Config::$modSettings['dont_repeat_buddylists']))
{
	// Make sure the pm_receive_from column has the right default value - early adopters might have a '0' set here.
	upgrade_query("
		ALTER TABLE {$db_prefix}members
		ALTER COLUMN pm_receive_from SET DEFAULT '1'");

	// Update previous ignore lists if they're set to ignore all.
	upgrade_query("
		UPDATE {$db_prefix}members
		SET pm_receive_from = 3, pm_ignore_list = ''
		WHERE pm_ignore_list = '*'");

	// Enable buddy and ignore lists.
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('enable_buddylist', '1'),
		array('variable', 'value')
	);

	// Ignore posts made by ignored users by default, too.
	Db::$db->insert('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-255'),
		array(-1, 1, 'posts_apply_ignore_list', '1'),
		array('id_member', 'id_theme', 'variable', 'value')
	);

	// Make sure not to skip this step next time we run this.
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('dont_repeat_buddylists', '1'),
		array('variable', 'value')
	);
}

// And yet, and yet... We might have a small hiccup here...
if (!empty(Config::$modSettings['dont_repeat_buddylists']) && !isset(Config::$modSettings['enable_buddylist']))
{
	// Correct RC3 adopters setting here...
	if (isset(Config::$modSettings['enable_buddylists']))
	{
		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string-255', 'value' => 'string-255'),
			array('enable_buddylist', Config::$modSettings['enable_buddylists']),
			array('variable', 'value')
		);
	}
	else
	{
		// This should never happen :)
		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string-255', 'value' => 'string-255'),
			array('enable_buddylist', '1'),
			array('variable', 'value')
		);
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
if (!isset(Config::$modSettings['attachment_image_reencode']))
{
	// Enable image re-encoding by default.
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('attachment_image_reencode', '1'),
		array('variable', 'value')
	);
}
if (!isset(Config::$modSettings['attachment_image_paranoid']))
{
	// Disable draconic checks by default.
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('attachment_image_paranoid', '0'),
		array('variable', 'value')
	);
}
if (!isset(Config::$modSettings['avatar_reencode']))
{
	// Enable image re-encoding by default.
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('avatar_reencode', '1'),
		array('variable', 'value')
	);
}
if (!isset(Config::$modSettings['avatar_paranoid']))
{
	// Disable draconic checks by default.
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('avatar_paranoid', '0'),
		array('variable', 'value')
	);
}
---}
---#

---# Add other attachment settings...
---{
if (!isset(Config::$modSettings['attachment_thumb_png']))
{
	// Make image attachment thumbnail as PNG by default.
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('attachment_thumb_png', '1'),
		array('variable', 'value')
	);
}
---}
---#

/******************************************************************************/
--- Cleaning up after old themes...
/******************************************************************************/

---# Checking for "babylon" and removing it if necessary...
---{
// Do they have "babylon" installed?
if (file_exists($GLOBALS['boarddir'] . '/Themes/babylon'))
{
	$babylon_dir = $GLOBALS['boarddir'] . '/Themes/babylon';
	$theme_request = Db::$db->query('', '
		SELECT id_theme
		FROM {db_prefix}themes
		WHERE variable = {string:themedir}
			AND value = {string:babylondir}',
		array(
			'themedir' => 'theme_dir',
			'babylondir' => $babylon_dir,
		)
	);

	// Don't do anything if this theme is already uninstalled
	if (Db::$db->num_rows($theme_request) == 1)
	{
		$row = Db::$db->fetch_row($theme_request);
		$id_theme = $row[0];
		Db::$db->free_result($theme_request);
		unset($row);

		$known_themes = explode(',', Config::$modSettings['knownThemes']);

		// Remove this value...
		$known_themes = array_diff($known_themes, array($id_theme));

		// Change back to a string...
		$known_themes = implode(',', $known_themes);

		// Update the database
		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('knownThemes', $known_themes),
			array('variable')
		);

		// Delete any info about this theme
		upgrade_query("
			DELETE FROM {$db_prefix}themes
			WHERE id_theme = $id_theme");

		// Set any members or boards using this theme to the default
		upgrade_query("
			UPDATE {$db_prefix}members
			SET id_theme = 0
			WHERE id_theme = $id_theme");

		upgrade_query("
			UPDATE {$db_prefix}boards
			SET id_theme = 0
			WHERE id_theme = $id_theme");

		if (Config::$modSettings['theme_guests'] == $id_theme)
		{
			Db::$db->insert('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				array('theme_guests', 0),
				array('variable')
			);
		}
	}
}
---}
---#

/******************************************************************************/
--- Installing new smileys sets...
/******************************************************************************/

---# Installing new smiley sets...
---{
// Don't do this twice!
if (empty(Config::$modSettings['dont_repeat_smileys_20']) && empty(Config::$modSettings['installed_new_smiley_sets_20']))
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
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('installed_new_smiley_sets_20', '1'),
		array('variable')
	);
}
---}
---#

/*****************************************************************************/
--- Adding additional functions
/*****************************************************************************/

---# Adding instr()
---{
if (Db::$db->server_info < 8.2)
{
	$request = upgrade_query("
		SELECT type_udt_name
		FROM information_schema.routines
		WHERE routine_name = 'inet_aton'
	");

	// Assume there's only one such function called inet_aton()
	$return_type = Db::$db->fetch_assoc($request);

	// No point in dropping and recreating it if it's already what we want
	if ($return_type['type_udt_name'] != 'int4')
	{
		upgrade_query("
			DROP FUNCTION IF EXISTS INSTR(text, text)");
	}
}
else
{
	upgrade_query("
		DROP FUNCTION IF EXISTS INSTR(text, text)");
}
---}
CREATE OR REPLACE FUNCTION INSTR(text, text) RETURNS integer AS
  'SELECT POSITION($2 IN $1) AS result'
LANGUAGE 'sql';
---#

---# Adding date_format()
CREATE OR REPLACE FUNCTION DATE_FORMAT (timestamp, text) RETURNS text AS '
	SELECT
		REPLACE(
			REPLACE($2, ''%m'', to_char($1, ''MM'')),
			''%d'', to_char($1, ''DD'')) AS result'
LANGUAGE 'sql';
---#

---# Adding day()
CREATE OR REPLACE FUNCTION day(date) RETURNS integer AS
  'SELECT EXTRACT(DAY FROM DATE($1))::integer AS result'
LANGUAGE 'sql';
---#

---# Adding IFNULL(varying, varying)
CREATE OR REPLACE FUNCTION IFNULL (character varying, character varying) RETURNS character varying AS
  'SELECT COALESCE($1, $2) AS result'
LANGUAGE 'sql';
---#

---# Adding IFNULL(varying, bool)
CREATE OR REPLACE FUNCTION IFNULL(character varying, boolean) RETURNS character varying AS
  'SELECT COALESCE($1, CAST(CAST($2 AS int) AS varchar)) AS result'
LANGUAGE 'sql';
---#

---# Adding IFNULL(int, bool)
CREATE OR REPLACE FUNCTION IFNULL(int, boolean) RETURNS int AS
  'SELECT COALESCE($1, CAST($2 AS int)) AS result'
LANGUAGE 'sql';
---#

---# Adding bool_not_eq_int()
CREATE OR REPLACE FUNCTION bool_not_eq_int (boolean, integer) RETURNS boolean AS
  'SELECT CAST($1 AS integer) != $2 AS result'
LANGUAGE 'sql';
---#

---# Creating operator bool_not_eq_int()
---{
$result = upgrade_query("SELECT oprname FROM pg_operator WHERE oprcode='bool_not_eq_int'::regproc");
if(Db::$db->num_rows($result) == 0)
{
	upgrade_query("
		CREATE OPERATOR != (PROCEDURE = bool_not_eq_int, LEFTARG = boolean, RIGHTARG = integer)");
}
---}
---#

---# Recreating function FIND_IN_SET()
---{
if (Db::$db->server_info < 8.2)
{
	$query = upgrade_query("SELECT * FROM pg_proc WHERE proname = 'find_in_set' AND proargtypes = '25 25'");
	if (Db::$db->num_rows($query) != 0)
	{
		upgrade_query("DROP FUNCTION IF EXISTS FIND_IN_SET(text, text)");
	}

	$query = upgrade_query("SELECT * FROM pg_proc WHERE proname = 'find_in_set' AND proargtypes = '23 1043'");
	if (Db::$db->num_rows($query) != 0)
	{
		upgrade_query("DROP FUNCTION IF EXISTS FIND_IN_SET(integer, character varying)");
	}
}
else
{
	upgrade_query("DROP FUNCTION IF EXISTS FIND_IN_SET(text, text)");
	upgrade_query("DROP FUNCTION IF EXISTS FIND_IN_SET(integer, character varying)");
}
---}
CREATE OR REPLACE FUNCTION FIND_IN_SET(needle text, haystack text) RETURNS integer AS '
	SELECT i AS result
	FROM generate_series(1, array_upper(string_to_array($2,'',''), 1)) AS g(i)
	WHERE  (string_to_array($2,'',''))[i] = $1
		UNION ALL
	SELECT 0
	LIMIT 1'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION FIND_IN_SET(needle integer, haystack text) RETURNS integer AS '
	SELECT i AS result
	FROM generate_series(1, array_upper(string_to_array($2,'',''), 1)) AS g(i)
	WHERE  (string_to_array($2,'',''))[i] = CAST($1 AS text)
		UNION ALL
	SELECT 0
	LIMIT 1'
LANGUAGE 'sql';
---#

CREATE OR REPLACE FUNCTION DATE_FORMAT (timestamp, text) RETURNS text AS '
	SELECT
		REPLACE(
			REPLACE($2, ''%m'', to_char($1, ''MM'')),
		''%d'', to_char($1, ''DD'')) AS result'
LANGUAGE 'sql';

---# Updating TO_DAYS()
CREATE OR REPLACE FUNCTION TO_DAYS (timestamp) RETURNS integer AS
  'SELECT DATE_PART(''DAY'', $1 - ''0001-01-01bc'')::integer AS result'
LANGUAGE 'sql';
---#

/******************************************************************************/
--- Adding extra columns to reported post comments
/******************************************************************************/

---# Adding email address and member ip columns...
---{
if (Db::$db->server_info < 8.0)
{
	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ADD COLUMN email_address varchar(255)");

	upgrade_query("
		UPDATE {$db_prefix}log_reported_comments
		SET email_address = ''");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ALTER COLUMN email_address SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ALTER COLUMN email_address SET default ''");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ADD COLUMN member_ip varchar(255)");

	upgrade_query("
		UPDATE {$db_prefix}log_reported_comments
		SET member_ip = ''");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ALTER COLUMN member_ip SET NOT NULL");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ALTER COLUMN member_ip SET default ''");
}
else
{
	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ADD COLUMN email_address varchar(255) NOT NULL default ''");

	upgrade_query("
		ALTER TABLE {$db_prefix}log_reported_comments
		ADD COLUMN member_ip varchar(255) NOT NULL default ''");
}
---}
---#

/******************************************************************************/
--- Adjusting group types.
/******************************************************************************/

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
if (!isset(Config::$modSettings['cal_maxyear']) || Config::$modSettings['cal_maxyear'] < 2030)
{
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-255'),
		array('cal_maxyear', '2030'),
		array('variable', 'value')
	);
}
---}
---#