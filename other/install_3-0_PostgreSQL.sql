#### ATTENTION: You do not need to run or use this file!  The install.php script does everything for you!
#### Install script for PostgreSQL 8.0.1

#
# Create PostgreSQL functions.
# Some taken from http://www.xach.com/aolserver/mysql-functions.sql and http://pgfoundry.org/projects/mysqlcompat/.
# IP Regex in inet_aton from https://www.mkyong.com/database/regular-expression-in-postgresql/.

CREATE OR REPLACE FUNCTION FROM_UNIXTIME(bigint) RETURNS timestamp AS
	'SELECT timestamp ''epoch'' + $1 * interval ''1 second'' AS result'
LANGUAGE 'sql';

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

CREATE OR REPLACE FUNCTION FIND_IN_SET(needle smallint, haystack text) RETURNS integer AS '
	SELECT i AS result
	FROM generate_series(1, array_upper(string_to_array($2,'',''), 1)) AS g(i)
	WHERE  (string_to_array($2,'',''))[i] = CAST($1 AS text)
		UNION ALL
	SELECT 0
	LIMIT 1'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION add_num_text (text, integer) RETURNS text AS
	'SELECT CAST ((CAST($1 AS integer) + $2) AS text) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION YEAR (timestamp) RETURNS integer AS
	'SELECT CAST (EXTRACT(YEAR FROM $1) AS integer) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION MONTH (timestamp) RETURNS integer AS
	'SELECT CAST (EXTRACT(MONTH FROM $1) AS integer) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION MONTH (bigint) RETURNS integer AS
	'SELECT CAST (EXTRACT(MONTH FROM TO_TIMESTAMP($1)) AS integer) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION day(date) RETURNS integer AS
	'SELECT EXTRACT(DAY FROM DATE($1))::integer AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION DAYOFMONTH (timestamp) RETURNS integer AS
	'SELECT CAST (EXTRACT(DAY FROM $1) AS integer) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION DAYOFMONTH (bigint) RETURNS integer AS
	'SELECT CAST (EXTRACT(DAY FROM TO_TIMESTAMP($1)) AS integer) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION HOUR (timestamp) RETURNS integer AS
	'SELECT CAST (EXTRACT(HOUR FROM $1) AS integer) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION DATE_FORMAT (timestamp, text) RETURNS text AS '
	SELECT
	REPLACE(
		REPLACE($2, ''%m'', to_char($1, ''MM'')),
		''%d'', to_char($1, ''DD'')) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION TO_DAYS (timestamp) RETURNS integer AS
	'SELECT DATE_PART(''DAY'', $1 - ''0001-01-01bc'')::integer AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION INSTR (text, text) RETURNS integer AS
	'SELECT POSITION($2 in $1) AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION bool_not_eq_int (boolean, integer) RETURNS boolean AS
	'SELECT CAST($1 AS integer) != $2 AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION indexable_month_day(date) RETURNS TEXT as '
		SELECT to_char($1, ''MM-DD'');'
LANGUAGE 'sql' IMMUTABLE STRICT;

#
# Create PostgreSQL operators.
#

CREATE OPERATOR + (PROCEDURE = add_num_text, LEFTARG = text, RIGHTARG = integer);
CREATE OPERATOR != (PROCEDURE = bool_not_eq_int, LEFTARG = boolean, RIGHTARG = integer);

#
# Sequence for table `admin_info_files`
#

CREATE SEQUENCE {$db_prefix}admin_info_files_seq START WITH 8;

#
# Table structure for table `admin_info_files`
#

CREATE TABLE {$db_prefix}admin_info_files (
	id_file smallint DEFAULT nextval('{$db_prefix}admin_info_files_seq'),
	filename varchar(255) NOT NULL DEFAULT '',
	path varchar(255) NOT NULL DEFAULT '',
	parameters varchar(255) NOT NULL DEFAULT '',
	data text NOT NULL,
	filetype varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id_file)
);

#
# Indexes for table `admin_info_files`
#

CREATE INDEX {$db_prefix}admin_info_files_filename ON {$db_prefix}admin_info_files (filename varchar_pattern_ops);

#
# Table structure for table `approval_queue`
#

CREATE TABLE {$db_prefix}approval_queue (
	id_msg bigint NOT NULL DEFAULT '0',
	id_attach bigint NOT NULL DEFAULT '0',
	id_event smallint NOT NULL DEFAULT '0'
);

#
# Sequence for table `attachments`
#

CREATE SEQUENCE {$db_prefix}attachments_seq;

#
# Table structure for table `attachments`
#

CREATE TABLE {$db_prefix}attachments (
	id_attach bigint DEFAULT nextval('{$db_prefix}attachments_seq'),
	id_thumb bigint NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	id_folder smallint NOT NULL DEFAULT '1',
	attachment_type smallint NOT NULL DEFAULT '0',
	filename varchar(255) NOT NULL DEFAULT '',
	file_hash varchar(40) NOT NULL DEFAULT '',
	fileext varchar(8) NOT NULL DEFAULT '',
	size int NOT NULL DEFAULT '0',
	downloads int NOT NULL DEFAULT '0',
	width int NOT NULL DEFAULT '0',
	height int NOT NULL DEFAULT '0',
	mime_type varchar(128) NOT NULL DEFAULT '',
	approved smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (id_attach)
);

#
# Indexes for table `attachments`
#

CREATE UNIQUE INDEX {$db_prefix}attachments_id_member ON {$db_prefix}attachments (id_member, id_attach);
CREATE INDEX {$db_prefix}attachments_id_msg ON {$db_prefix}attachments (id_msg);
CREATE INDEX {$db_prefix}attachments_attachment_type ON {$db_prefix}attachments (attachment_type);
CREATE INDEX {$db_prefix}attachments_id_thumb ON {$db_prefix}attachments (id_thumb);

#
# Sequence for table `background_tasks`
#

CREATE SEQUENCE {$db_prefix}background_tasks_seq;

#
# Table structure for table `background_tasks`
#

CREATE TABLE {$db_prefix}background_tasks (
	id_task bigint DEFAULT nextval('{$db_prefix}background_tasks_seq'),
	task_file varchar(255) NOT NULL DEFAULT '',
	task_class varchar(255) NOT NULL DEFAULT '',
	task_data text NOT NULL,
	claimed_time int NOT NULL DEFAULT '0',
	PRIMARY KEY (id_task)
);

#
# Sequence for table `ban_groups`
#

CREATE SEQUENCE {$db_prefix}ban_groups_seq;

#
# Table structure for table `ban_groups`
#

CREATE TABLE {$db_prefix}ban_groups (
	id_ban_group int DEFAULT nextval('{$db_prefix}ban_groups_seq'),
	name varchar(20) NOT NULL DEFAULT '',
	ban_time bigint NOT NULL DEFAULT '0',
	expire_time bigint,
	cannot_access smallint NOT NULL DEFAULT '0',
	cannot_register smallint NOT NULL DEFAULT '0',
	cannot_post smallint NOT NULL DEFAULT '0',
	cannot_login smallint NOT NULL DEFAULT '0',
	reason varchar(255) NOT NULL,
	notes text NOT NULL,
	PRIMARY KEY (id_ban_group)
);

#
# Sequence for table `ban_items`
#

CREATE SEQUENCE {$db_prefix}ban_items_seq;

#
# Table structure for table `ban_items`
#

CREATE TABLE {$db_prefix}ban_items (
	id_ban int DEFAULT nextval('{$db_prefix}ban_items_seq'),
	id_ban_group smallint NOT NULL DEFAULT '0',
	ip_low inet,
	ip_high inet,
	hostname varchar(255) NOT NULL DEFAULT '',
	email_address varchar(255) NOT NULL DEFAULT '',
	id_member int NOT NULL DEFAULT '0',
	hits bigint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_ban)
);

#
# Indexes for table `ban_items`
#

CREATE INDEX {$db_prefix}ban_items_id_ban_group ON {$db_prefix}ban_items (id_ban_group);
CREATE INDEX {$db_prefix}ban_items_id_ban_ip ON {$db_prefix}ban_items (ip_low,ip_high);

#
# Table structure for table `board_permissions`
#

CREATE TABLE {$db_prefix}board_permissions (
	id_group smallint NOT NULL DEFAULT '0',
	id_profile smallint NOT NULL DEFAULT '0',
	permission varchar(30) NOT NULL DEFAULT '',
	add_deny smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (id_group, id_profile, permission)
);

#
# Sequence for table `boards`
#

CREATE SEQUENCE {$db_prefix}boards_seq START WITH 2;

#
# Table structure for table `boards`
#

CREATE TABLE {$db_prefix}boards (
	id_board smallint DEFAULT nextval('{$db_prefix}boards_seq'),
	id_cat smallint NOT NULL DEFAULT '0',
	child_level smallint NOT NULL DEFAULT '0',
	id_parent smallint NOT NULL DEFAULT '0',
	board_order smallint NOT NULL DEFAULT '0',
	id_last_msg bigint NOT NULL DEFAULT '0',
	id_msg_updated bigint NOT NULL DEFAULT '0',
	member_groups varchar(255) NOT NULL DEFAULT '-1,0',
	id_profile smallint NOT NULL DEFAULT '1',
	name varchar(255) NOT NULL DEFAULT '',
	description text NOT NULL,
	num_topics int NOT NULL DEFAULT '0',
	num_posts int NOT NULL DEFAULT '0',
	count_posts smallint NOT NULL DEFAULT '0',
	id_theme smallint NOT NULL DEFAULT '0',
	override_theme smallint NOT NULL DEFAULT '0',
	unapproved_posts smallint NOT NULL DEFAULT '0',
	unapproved_topics smallint NOT NULL DEFAULT '0',
	redirect varchar(255) NOT NULL DEFAULT '',
	deny_member_groups varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id_board)
);

#
# Indexes for table `ban_items`
#

CREATE UNIQUE INDEX {$db_prefix}boards_categories ON {$db_prefix}boards (id_cat, id_board);
CREATE INDEX {$db_prefix}boards_id_parent ON {$db_prefix}boards (id_parent);
CREATE INDEX {$db_prefix}boards_id_msg_updated ON {$db_prefix}boards (id_msg_updated);
CREATE INDEX {$db_prefix}boards_member_groups ON {$db_prefix}boards (member_groups varchar_pattern_ops);

#
# Table structure for table `board_permissions_view`
#

CREATE TABLE {$db_prefix}board_permissions_view
(
		id_group smallint NOT NULL DEFAULT '0',
		id_board smallint NOT NULL,
		deny smallint NOT NULL,
		PRIMARY KEY (id_group, id_board, deny)
);

#
# Sequence for table `calendar`
#

CREATE SEQUENCE {$db_prefix}calendar_seq;

#
# Table structure for table `calendar`
#

CREATE TABLE {$db_prefix}calendar (
	id_event smallint DEFAULT nextval('{$db_prefix}calendar_seq'),
	id_board smallint NOT NULL DEFAULT '0',
	id_topic int NOT NULL DEFAULT '0',
	title varchar(255) NOT NULL DEFAULT '',
	id_member int NOT NULL DEFAULT '0',
	start_date date NOT NULL DEFAULT '1004-01-01',
	end_date date NOT NULL DEFAULT '1004-01-01',
	start_time time,
	end_time time,
	timezone varchar(80),
	location varchar(255) NOT NULL DEFAULT '',
	duration varchar(32) NOT NULL DEFAULT '',
	rrule varchar(1024) NOT NULL DEFAULT 'FREQ=YEARLY;COUNT=1',
	rdates text NOT NULL,
	exdates text NOT NULL,
	adjustments jsonb DEFAULT NULL,
	sequence smallint NOT NULL DEFAULT '0',
	uid VARCHAR(255) NOT NULL DEFAULT '',
	type smallint NOT NULL DEFAULT '0',
	enabled smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (id_event)
);

#
# Indexes for table `calendar`
#

CREATE INDEX {$db_prefix}calendar_start_date ON {$db_prefix}calendar (start_date);
CREATE INDEX {$db_prefix}calendar_end_date ON {$db_prefix}calendar (end_date);
CREATE INDEX {$db_prefix}calendar_topic ON {$db_prefix}calendar (id_topic, id_member);

#
# Sequence for table `categories`
#

CREATE SEQUENCE {$db_prefix}categories_seq START WITH 2;

#
# Table structure for table `categories`
#

CREATE TABLE {$db_prefix}categories (
	id_cat smallint DEFAULT nextval('{$db_prefix}categories_seq'),
	cat_order smallint NOT NULL DEFAULT '0',
	name varchar(255) NOT NULL DEFAULT '',
	description text NOT NULL,
	can_collapse smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (id_cat)
);

#
# Sequence for table `custom_fields`
#

CREATE SEQUENCE {$db_prefix}custom_fields_seq;

#
# Table structure for table `custom_fields`
#

CREATE TABLE {$db_prefix}custom_fields (
	id_field smallint DEFAULT nextval('{$db_prefix}custom_fields_seq'),
	col_name varchar(12) NOT NULL DEFAULT '',
	field_name varchar(40) NOT NULL DEFAULT '',
	field_desc varchar(255) NOT NULL DEFAULT '',
	field_type varchar(8) NOT NULL DEFAULT 'text',
	field_length smallint NOT NULL DEFAULT '255',
	field_options text NOT NULL,
	field_order smallint NOT NULL DEFAULT '0',
	mask varchar(255) NOT NULL DEFAULT '',
	show_reg smallint NOT NULL DEFAULT '0',
	show_display smallint NOT NULL DEFAULT '0',
	show_mlist smallint NOT NULL DEFAULT '0',
	show_profile varchar(20) NOT NULL DEFAULT 'forumprofile',
	private smallint NOT NULL DEFAULT '0',
	active smallint NOT NULL DEFAULT '1',
	bbc smallint NOT NULL DEFAULT '0',
	can_search smallint NOT NULL DEFAULT '0',
	default_value varchar(255) NOT NULL DEFAULT '',
	enclose text NOT NULL,
	placement smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_field)
);

#
# Indexes for table `custom_fields`
#

CREATE UNIQUE INDEX {$db_prefix}custom_fields_col_name ON {$db_prefix}custom_fields (col_name);

#
# Table structure for table `group_moderators`
#

CREATE TABLE {$db_prefix}group_moderators (
	id_group smallint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	PRIMARY KEY (id_group, id_member)
);

#
# Sequence for table `log_actions`
#

CREATE SEQUENCE {$db_prefix}log_actions_seq;

#
# Table structure for table `log_actions`
#

CREATE TABLE {$db_prefix}log_actions (
	id_action bigint DEFAULT nextval('{$db_prefix}log_actions_seq'),
	id_log smallint NOT NULL DEFAULT '1',
	log_time bigint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	ip inet,
	action varchar(30) NOT NULL DEFAULT '',
	id_board smallint NOT NULL DEFAULT '0',
	id_topic int NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	extra text NOT NULL,
	PRIMARY KEY (id_action)
);

#
# Indexes for table `log_actions`
#

CREATE INDEX {$db_prefix}log_actions_log_time ON {$db_prefix}log_actions (log_time);
CREATE INDEX {$db_prefix}log_actions_id_member ON {$db_prefix}log_actions (id_member);
CREATE INDEX {$db_prefix}log_actions_id_board ON {$db_prefix}log_actions (id_board);
CREATE INDEX {$db_prefix}log_actions_id_msg ON {$db_prefix}log_actions (id_msg);
CREATE INDEX {$db_prefix}log_actions_id_log ON {$db_prefix}log_actions (id_log);
CREATE INDEX {$db_prefix}log_actions_id_topic_id_log ON {$db_prefix}log_actions (id_topic, id_log);

#
# Table structure for table `log_activity`
#

CREATE TABLE {$db_prefix}log_activity (
	date date NOT NULL,
	hits int NOT NULL DEFAULT '0',
	topics smallint NOT NULL DEFAULT '0',
	posts smallint NOT NULL DEFAULT '0',
	registers smallint NOT NULL DEFAULT '0',
	most_on smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (date)
);

#
# Sequence for table `log_banned`
#

CREATE SEQUENCE {$db_prefix}log_banned_seq;

#
# Table structure for table `log_banned`
#

CREATE TABLE {$db_prefix}log_banned (
	id_ban_log int DEFAULT nextval('{$db_prefix}log_banned_seq'),
	id_member int NOT NULL DEFAULT '0',
	ip inet,
	email varchar(255) NOT NULL DEFAULT '',
	log_time bigint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_ban_log)
);

#
# Indexes for table `log_banned`
#

CREATE INDEX {$db_prefix}log_banned_log_time ON {$db_prefix}log_banned (log_time);

#
# Table structure for table `log_boards`
#

CREATE TABLE {$db_prefix}log_boards (
	id_member int NOT NULL DEFAULT '0',
	id_board smallint NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_member, id_board)
);

#
# Sequence for table `log_comments`
#

CREATE SEQUENCE {$db_prefix}log_comments_seq;

#
# Table structure for table `log_comments`
#

CREATE TABLE {$db_prefix}log_comments (
	id_comment int DEFAULT nextval('{$db_prefix}log_comments_seq'),
	id_member int NOT NULL DEFAULT '0',
	member_name varchar(80) NOT NULL DEFAULT '',
	comment_type varchar(8) NOT NULL DEFAULT 'warning',
	id_recipient int NOT NULL DEFAULT '0',
	recipient_name varchar(255) NOT NULL DEFAULT '',
	log_time bigint NOT NULL DEFAULT '0',
	id_notice int NOT NULL DEFAULT '0',
	counter smallint NOT NULL DEFAULT '0',
	body text NOT NULL,
	PRIMARY KEY (id_comment)
);

#
# Indexes for table `log_comments`
#

CREATE INDEX {$db_prefix}log_comments_id_recipient ON {$db_prefix}log_comments (id_recipient);
CREATE INDEX {$db_prefix}log_comments_log_time ON {$db_prefix}log_comments (log_time);
CREATE INDEX {$db_prefix}log_comments_comment_type ON {$db_prefix}log_comments (comment_type varchar_pattern_ops);

#
# Table structure for table `log_digest`
#

CREATE TABLE {$db_prefix}log_digest (
	id_topic int NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	note_type varchar(10) NOT NULL DEFAULT 'post',
	daily smallint NOT NULL DEFAULT '0',
	exclude int NOT NULL DEFAULT '0'
);

#
# Sequence for table `log_errors`
#

CREATE SEQUENCE {$db_prefix}log_errors_seq;

#
# Table structure for table `log_errors`
#

CREATE TABLE {$db_prefix}log_errors (
	id_error int DEFAULT nextval('{$db_prefix}log_errors_seq'),
	log_time bigint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	ip inet,
	url text NOT NULL,
	message text NOT NULL,
	session varchar(128) NOT NULL DEFAULT '                                                                ',
	error_type varchar(15) NOT NULL DEFAULT 'general',
	file varchar(255) NOT NULL DEFAULT '',
	line int NOT NULL DEFAULT '0',
	backtrace text NOT NULL DEFAULT '',
	PRIMARY KEY (id_error)
);

#
# Indexes for table `log_errors`
#

CREATE INDEX {$db_prefix}log_errors_log_time ON {$db_prefix}log_errors (log_time);
CREATE INDEX {$db_prefix}log_errors_id_member ON {$db_prefix}log_errors (id_member);
CREATE INDEX {$db_prefix}log_errors_ip ON {$db_prefix}log_errors (ip);

#
# Table structure for table `log_floodcontrol`
#

CREATE UNLOGGED TABLE {$db_prefix}log_floodcontrol (
	ip inet,
	log_time bigint NOT NULL DEFAULT '0',
	log_type varchar(30) NOT NULL DEFAULT 'post',
	PRIMARY KEY (ip, log_type)
);

#
# Sequence for table `log_group_requests`
#

CREATE SEQUENCE {$db_prefix}log_group_requests_seq;

#
# Table structure for table `log_group_requests`
#

CREATE TABLE {$db_prefix}log_group_requests (
	id_request int DEFAULT nextval('{$db_prefix}log_group_requests_seq'),
	id_member int NOT NULL DEFAULT '0',
	id_group smallint NOT NULL DEFAULT '0',
	time_applied bigint NOT NULL DEFAULT '0',
	reason text NOT NULL,
	status smallint NOT NULL DEFAULT '0',
	id_member_acted int NOT NULL DEFAULT '0',
	member_name_acted varchar(255) NOT NULL DEFAULT '',
	time_acted bigint NOT NULL DEFAULT '0',
	act_reason text NOT NULL,
	PRIMARY KEY (id_request)
);

#
# Indexes for table `log_group_requests`
#

CREATE INDEX {$db_prefix}log_group_requests_id_member ON {$db_prefix}log_group_requests (id_member, id_group);

#
# Table structure for table `log_mark_read`
#

CREATE TABLE {$db_prefix}log_mark_read (
	id_member int NOT NULL DEFAULT '0',
	id_board smallint NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_member, id_board)
);

#
# Sequence for table `log_member_notices`
#

CREATE SEQUENCE {$db_prefix}log_member_notices_seq;

#
# Table structure for table `log_member_notices`
#

CREATE TABLE {$db_prefix}log_member_notices (
	id_notice int DEFAULT nextval('{$db_prefix}log_member_notices_seq'),
	subject varchar(255) NOT NULL DEFAULT '',
	body text NOT NULL,
	PRIMARY KEY (id_notice)
);

#
# Table structure for table `log_notify`
#

CREATE TABLE {$db_prefix}log_notify (
	id_member int NOT NULL DEFAULT '0',
	id_topic int NOT NULL DEFAULT '0',
	id_board smallint NOT NULL DEFAULT '0',
	sent smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_member, id_topic, id_board)
);

#
# Indexes for table `log_notify`
#

CREATE INDEX {$db_prefix}log_notify_id_topic ON {$db_prefix}log_notify (id_topic, id_member);
CREATE INDEX {$db_prefix}log_notify_id_board ON {$db_prefix}log_notify (id_board);

#
# Table structure for table `log_online`
#

CREATE UNLOGGED TABLE {$db_prefix}log_online (
	session varchar(128) NOT NULL DEFAULT '',
	log_time bigint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	id_spider smallint NOT NULL DEFAULT '0',
	ip inet,
	url varchar(2048) NOT NULL DEFAULT '',
	PRIMARY KEY (session)
);

#
# Indexes for table `log_online`
#

CREATE INDEX {$db_prefix}log_online_log_time ON {$db_prefix}log_online (log_time);
CREATE INDEX {$db_prefix}log_online_id_member ON {$db_prefix}log_online (id_member);

#
# Sequence for table `log_packages`
#

CREATE SEQUENCE {$db_prefix}log_packages_seq;

#
# Table structure for table `log_packages`
#

CREATE TABLE {$db_prefix}log_packages (
	id_install int DEFAULT nextval('{$db_prefix}log_packages_seq'),
	filename varchar(255) NOT NULL DEFAULT '',
	package_id varchar(255) NOT NULL DEFAULT '',
	name varchar(255) NOT NULL DEFAULT '',
	version varchar(255) NOT NULL DEFAULT '',
	id_member_installed int NOT NULL DEFAULT '0',
	member_installed varchar(255) NOT NULL,
	time_installed int NOT NULL DEFAULT '0',
	id_member_removed int NOT NULL DEFAULT '0',
	member_removed varchar(255) NOT NULL,
	time_removed int NOT NULL DEFAULT '0',
	install_state smallint NOT NULL DEFAULT '1',
	failed_steps text NOT NULL,
	themes_installed varchar(255) NOT NULL DEFAULT '',
	db_changes text NOT NULL,
	credits text NOT NULL,
	sha256_hash TEXT,
	smf_version varchar(5) NOT NULL DEFAULT '',
	PRIMARY KEY (id_install)
);

#
# Indexes for table `log_packages`
#

CREATE INDEX {$db_prefix}log_packages_filename ON {$db_prefix}log_packages (filename varchar_pattern_ops);

#
# Table structure for table `log_polls`
#

CREATE TABLE {$db_prefix}log_polls (
	id_poll int NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	id_choice smallint NOT NULL DEFAULT '0'
);

#
# Indexes for table `log_polls`
#

CREATE INDEX {$db_prefix}log_polls_id_poll ON {$db_prefix}log_polls (id_poll, id_member, id_choice);

#
# Sequence for table `log_reported`
#

CREATE SEQUENCE {$db_prefix}log_reported_seq;

#
# Table structure for table `log_reported`
#

CREATE TABLE {$db_prefix}log_reported (
	id_report int DEFAULT nextval('{$db_prefix}log_reported_seq'),
	id_msg bigint NOT NULL DEFAULT '0',
	id_topic int NOT NULL DEFAULT '0',
	id_board smallint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	membername varchar(255) NOT NULL DEFAULT '',
	subject varchar(255) NOT NULL DEFAULT '',
	body text NOT NULL,
	time_started int NOT NULL DEFAULT '0',
	time_updated int NOT NULL DEFAULT '0',
	num_reports int NOT NULL DEFAULT '0',
	closed smallint NOT NULL DEFAULT '0',
	ignore_all smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_report)
);

#
# Indexes for table `log_reported`
#

CREATE INDEX {$db_prefix}log_reported_id_member ON {$db_prefix}log_reported (id_member);
CREATE INDEX {$db_prefix}log_reported_id_topic ON {$db_prefix}log_reported (id_topic);
CREATE INDEX {$db_prefix}log_reported_closed ON {$db_prefix}log_reported (closed);
CREATE INDEX {$db_prefix}log_reported_time_started ON {$db_prefix}log_reported (time_started);
CREATE INDEX {$db_prefix}log_reported_id_msg ON {$db_prefix}log_reported (id_msg);

#
# Sequence for table `log_reported_comments`
#

CREATE SEQUENCE {$db_prefix}log_reported_comments_seq;

#
# Table structure for table `log_reported_comments`
#

CREATE TABLE {$db_prefix}log_reported_comments (
	id_comment int DEFAULT nextval('{$db_prefix}log_reported_comments_seq'),
	id_report int NOT NULL DEFAULT '0',
	id_member int NOT NULL,
	membername varchar(255) NOT NULL DEFAULT '',
	member_ip inet,
	comment varchar(255) NOT NULL DEFAULT '',
	time_sent int NOT NULL,
	PRIMARY KEY (id_comment)
);

#
# Indexes for table `log_reported_comments`
#

CREATE INDEX {$db_prefix}log_reported_comments_id_report ON {$db_prefix}log_reported_comments (id_report);
CREATE INDEX {$db_prefix}log_reported_comments_id_member ON {$db_prefix}log_reported_comments (id_member);
CREATE INDEX {$db_prefix}log_reported_comments_time_sent ON {$db_prefix}log_reported_comments (time_sent);

#
# Sequence for table `log_scheduled_tasks`
#

CREATE SEQUENCE {$db_prefix}log_scheduled_tasks_seq;

#
# Table structure for table `log_scheduled_tasks`
#

CREATE TABLE {$db_prefix}log_scheduled_tasks (
	id_log int DEFAULT nextval('{$db_prefix}log_scheduled_tasks_seq'),
	id_task smallint NOT NULL DEFAULT '0',
	time_run int NOT NULL DEFAULT '0',
	time_taken float NOT NULL DEFAULT '0',
	PRIMARY KEY (id_log)
);

#
# Table structure for table `log_search_messages`
#

CREATE TABLE {$db_prefix}log_search_messages (
	id_search smallint NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_search, id_msg)
);

#
# Table structure for table `log_search_results`
#

CREATE TABLE {$db_prefix}log_search_results (
	id_search smallint NOT NULL DEFAULT '0',
	id_topic int NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	relevance smallint NOT NULL DEFAULT '0',
	num_matches smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_search, id_topic)
);

#
# Table structure for table `log_search_subjects`
#

CREATE TABLE {$db_prefix}log_search_subjects (
	word varchar(20) NOT NULL DEFAULT '',
	id_topic int NOT NULL DEFAULT '0',
	PRIMARY KEY (word, id_topic)
);

#
# Indexes for table `log_search_subjects`
#

CREATE INDEX {$db_prefix}log_search_subjects_id_topic ON {$db_prefix}log_search_subjects (id_topic);

#
# Table structure for table `log_search_topics`
#

CREATE TABLE {$db_prefix}log_search_topics (
	id_search smallint NOT NULL DEFAULT '0',
	id_topic int NOT NULL DEFAULT '0',
	PRIMARY KEY (id_search, id_topic)
);

#
# Sequence for table `log_spider_hits`
#

CREATE SEQUENCE {$db_prefix}log_spider_hits_seq;

#
# Table structure for table `log_spider_hits`
#

CREATE TABLE {$db_prefix}log_spider_hits (
	id_hit bigint DEFAULT nextval('{$db_prefix}log_spider_hits_seq'),
	id_spider smallint NOT NULL DEFAULT '0',
	log_time bigint NOT NULL DEFAULT '0',
	url varchar(1024) NOT NULL DEFAULT '',
	processed smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_hit)
);

#
# Indexes for table `log_spider_hits`
#

CREATE INDEX {$db_prefix}log_spider_hits_id_spider ON {$db_prefix}log_spider_hits (id_spider);
CREATE INDEX {$db_prefix}log_spider_hits_log_time ON {$db_prefix}log_spider_hits (log_time);
CREATE INDEX {$db_prefix}log_spider_hits_processed ON {$db_prefix}log_spider_hits (processed);

#
# Table structure for table `log_spider_stats`
#

CREATE TABLE {$db_prefix}log_spider_stats (
	id_spider smallint NOT NULL DEFAULT '0',
	page_hits int NOT NULL DEFAULT '0',
	last_seen bigint NOT NULL DEFAULT '0',
	stat_date date NOT NULL DEFAULT '1004-01-01',
	PRIMARY KEY (stat_date, id_spider)
);

#
# Sequence for table `log_subscribed`
#

CREATE SEQUENCE {$db_prefix}log_subscribed_seq;

#
# Table structure for table `log_subscribed`
#

CREATE TABLE {$db_prefix}log_subscribed (
	id_sublog bigint DEFAULT nextval('{$db_prefix}log_subscribed_seq'),
	id_subscribe smallint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	old_id_group int NOT NULL DEFAULT '0',
	start_time int NOT NULL DEFAULT '0',
	end_time int NOT NULL DEFAULT '0',
	payments_pending smallint NOT NULL DEFAULT '0',
	status smallint NOT NULL DEFAULT '0',
	pending_details text NOT NULL,
	reminder_sent smallint NOT NULL DEFAULT '0',
	vendor_ref varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id_sublog)
);

#
# Indexes for table `log_subscribed`
#

CREATE INDEX {$db_prefix}log_subscribed_id_subscribe ON {$db_prefix}log_subscribed (id_subscribe, id_member);
CREATE INDEX {$db_prefix}log_subscribed_end_time ON {$db_prefix}log_subscribed (end_time);
CREATE INDEX {$db_prefix}log_subscribed_reminder_sent ON {$db_prefix}log_subscribed (reminder_sent);
CREATE INDEX {$db_prefix}log_subscribed_payments_pending ON {$db_prefix}log_subscribed (payments_pending);
CREATE INDEX {$db_prefix}log_subscribed_status ON {$db_prefix}log_subscribed (status);
CREATE INDEX {$db_prefix}log_subscribed_id_member ON {$db_prefix}log_subscribed (id_member);

#
# Table structure for table `log_topics`
#

CREATE TABLE {$db_prefix}log_topics (
	id_member int NOT NULL DEFAULT '0',
	id_topic int NOT NULL DEFAULT '0',
	id_msg bigint NOT NULL DEFAULT '0',
	unwatched int NOT NULL DEFAULT '0',
	PRIMARY KEY (id_member, id_topic)
);

#
# Indexes for table `log_topics`
#

CREATE INDEX {$db_prefix}log_topics_id_topic ON {$db_prefix}log_topics (id_topic);

#
# Sequence for table `mail_queue`
#

CREATE SEQUENCE {$db_prefix}mail_queue_seq;

#
# Table structure for table `mail_queue`
#

CREATE TABLE {$db_prefix}mail_queue (
	id_mail bigint DEFAULT nextval('{$db_prefix}mail_queue_seq'),
	time_sent int NOT NULL DEFAULT '0',
	recipient varchar(255) NOT NULL DEFAULT '',
	body text NOT NULL,
	subject varchar(255) NOT NULL DEFAULT '',
	headers text NOT NULL,
	send_html smallint NOT NULL DEFAULT '0',
	priority smallint NOT NULL DEFAULT '1',
	private smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_mail)
);

#
# Indexes for table `mail_queue`
#

CREATE INDEX {$db_prefix}mail_queue_time_sent ON {$db_prefix}mail_queue (time_sent);
CREATE INDEX {$db_prefix}mail_queue_mail_priority ON {$db_prefix}mail_queue (priority, id_mail);

#
# Sequence for table `membergroups`
#

CREATE SEQUENCE {$db_prefix}membergroups_seq START WITH 9;

#
# Table structure for table `membergroups`
#

CREATE TABLE {$db_prefix}membergroups (
	id_group smallint DEFAULT nextval('{$db_prefix}membergroups_seq'),
	group_name varchar(80) NOT NULL DEFAULT '',
	description text NOT NULL,
	online_color varchar(20) NOT NULL DEFAULT '',
	min_posts int NOT NULL DEFAULT '-1',
	max_messages smallint NOT NULL DEFAULT '0',
	icons varchar(255) NOT NULL DEFAULT '',
	group_type smallint NOT NULL DEFAULT '0',
	hidden smallint NOT NULL DEFAULT '0',
	id_parent smallint NOT NULL DEFAULT '-2',
	tfa_required smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_group)
);

#
# Indexes for table `membergroups`
#

CREATE INDEX {$db_prefix}membergroups_min_posts ON {$db_prefix}membergroups (min_posts);

#
# Sequence for table `members`
#

CREATE SEQUENCE {$db_prefix}members_seq;

#
# Table structure for table `members`
#

CREATE TABLE {$db_prefix}members (
	id_member int DEFAULT nextval('{$db_prefix}members_seq'),
	member_name varchar(80) NOT NULL DEFAULT '',
	date_registered bigint NOT NULL DEFAULT '0',
	posts int NOT NULL DEFAULT '0',
	id_group smallint NOT NULL DEFAULT '0',
	lngfile varchar(255) NOT NULL DEFAULT '',
	last_login bigint NOT NULL DEFAULT '0',
	real_name varchar(255) NOT NULL  DEFAULT '',
	instant_messages smallint NOT NULL DEFAULT 0,
	unread_messages smallint NOT NULL DEFAULT 0,
	new_pm smallint NOT NULL DEFAULT '0',
	alerts bigint NOT NULL DEFAULT '0',
	buddy_list text NOT NULL,
	pm_ignore_list TEXT NULL,
	pm_prefs int NOT NULL DEFAULT '0',
	mod_prefs varchar(20) NOT NULL DEFAULT '',
	passwd varchar(64) NOT NULL DEFAULT '',
	email_address varchar(255) NOT NULL DEFAULT '',
	personal_text varchar(255) NOT NULL DEFAULT '',
	birthdate date NOT NULL DEFAULT '1004-01-01',
	website_title varchar(255) NOT NULL DEFAULT '',
	website_url varchar(255) NOT NULL DEFAULT '',
	show_online smallint NOT NULL DEFAULT '1',
	time_format varchar(80) NOT NULL DEFAULT '',
	signature text NOT NULL,
	time_offset float NOT NULL DEFAULT '0',
	avatar varchar(255) NOT NULL DEFAULT '',
	usertitle varchar(255) NOT NULL DEFAULT '',
	member_ip inet,
	member_ip2 inet,
	secret_question varchar(255) NOT NULL DEFAULT '',
	secret_answer varchar(64) NOT NULL DEFAULT '',
	id_theme smallint NOT NULL DEFAULT '0',
	is_activated smallint NOT NULL DEFAULT '1',
	validation_code varchar(10) NOT NULL DEFAULT '',
	id_msg_last_visit int NOT NULL DEFAULT '0',
	additional_groups varchar(255) NOT NULL DEFAULT '',
	smiley_set varchar(48) NOT NULL DEFAULT '',
	id_post_group smallint NOT NULL DEFAULT '0',
	total_time_logged_in bigint NOT NULL DEFAULT '0',
	password_salt varchar(255) NOT NULL DEFAULT '',
	ignore_boards text NOT NULL,
	warning smallint NOT NULL DEFAULT '0',
	passwd_flood varchar(12) NOT NULL DEFAULT '',
	pm_receive_from smallint NOT NULL DEFAULT '1',
	timezone varchar(80) NOT NULL DEFAULT '',
	tfa_secret varchar(24) NOT NULL DEFAULT '',
	tfa_backup varchar(64) NOT NULL DEFAULT '',
	spoofdetector_name VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id_member)
);

#
# Indexes for table `members`
#

CREATE INDEX {$db_prefix}members_member_name ON {$db_prefix}members (member_name varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_real_name ON {$db_prefix}members (real_name varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_email_address ON {$db_prefix}members (email_address varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_date_registered ON {$db_prefix}members (date_registered);
CREATE INDEX {$db_prefix}members_id_group ON {$db_prefix}members (id_group);
CREATE INDEX {$db_prefix}members_birthdate ON {$db_prefix}members (birthdate);
CREATE INDEX {$db_prefix}members_birthdate2 ON {$db_prefix}members (indexable_month_day(birthdate));
CREATE INDEX {$db_prefix}members_posts ON {$db_prefix}members (posts);
CREATE INDEX {$db_prefix}members_last_login ON {$db_prefix}members (last_login);
CREATE INDEX {$db_prefix}members_lngfile ON {$db_prefix}members (lngfile varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_id_post_group ON {$db_prefix}members (id_post_group);
CREATE INDEX {$db_prefix}members_warning ON {$db_prefix}members (warning);
CREATE INDEX {$db_prefix}members_total_time_logged_in ON {$db_prefix}members (total_time_logged_in);
CREATE INDEX {$db_prefix}members_id_theme ON {$db_prefix}members (id_theme);
CREATE INDEX {$db_prefix}members_member_name_low ON {$db_prefix}members (LOWER(member_name) varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_real_name_low ON {$db_prefix}members (LOWER(real_name) varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_active_real_name ON {$db_prefix}members (is_activated, real_name);
CREATE INDEX {$db_prefix}idx_spoofdetector_name ON {$db_prefix}members (spoofdetector_name);
CREATE INDEX {$db_prefix}idx_spoofdetector_name_id ON {$db_prefix}members (spoofdetector_name, id_member);

#
# Sequence for table `member_logins`
#

CREATE SEQUENCE {$db_prefix}member_logins_seq;

#
# Table structure for table `member_logins`
#

CREATE TABLE {$db_prefix}member_logins (
	id_login int DEFAULT nextval('{$db_prefix}member_logins_seq'),
	id_member int NOT NULL DEFAULT '0',
	time int NOT NULL DEFAULT '0',
	ip inet,
	ip2 inet,
	PRIMARY KEY (id_login)
);

#
# Indexes for table `member_logins`
#
CREATE INDEX {$db_prefix}member_logins_id_member ON {$db_prefix}member_logins (id_member);
CREATE INDEX {$db_prefix}member_logins_time ON {$db_prefix}member_logins (time);


#
# Sequence for table `message_icons`
#

CREATE SEQUENCE {$db_prefix}message_icons_seq;

#
# Table structure for table `message_icons`
#

CREATE TABLE {$db_prefix}message_icons (
	id_icon smallint DEFAULT nextval('{$db_prefix}message_icons_seq'),
	title varchar(80) NOT NULL DEFAULT '',
	filename varchar(80) NOT NULL DEFAULT '',
	id_board smallint NOT NULL DEFAULT '0',
	icon_order smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_icon)
);

#
# Indexes for table `message_icons`
#

CREATE INDEX {$db_prefix}message_icons_id_board ON {$db_prefix}message_icons (id_board);

#
# Sequence for table `messages`
#

CREATE SEQUENCE {$db_prefix}messages_seq START WITH 2;

#
# Table structure for table `messages`
#

CREATE TABLE {$db_prefix}messages (
	id_msg bigint DEFAULT nextval('{$db_prefix}messages_seq'),
	id_topic int NOT NULL DEFAULT '0',
	id_board smallint NOT NULL DEFAULT '0',
	poster_time bigint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	id_msg_modified int NOT NULL DEFAULT '0',
	subject varchar(255) NOT NULL DEFAULT '',
	poster_name varchar(255) NOT NULL DEFAULT '',
	poster_email varchar(255) NOT NULL DEFAULT '',
	poster_ip inet,
	smileys_enabled smallint NOT NULL DEFAULT '1',
	modified_time int NOT NULL DEFAULT '0',
	modified_name varchar(255) NOT NULL,
	modified_reason varchar(255) NOT NULL DEFAULT '',
	body text NOT NULL,
	icon varchar(16) NOT NULL DEFAULT 'xx',
	approved smallint NOT NULL DEFAULT '1',
	likes smallint NOT NULL DEFAULT '0',
	version varchar(5) NOT NULL DEFAULT '',
	PRIMARY KEY (id_msg)
);

#
# Indexes for table `messages`
#

CREATE UNIQUE INDEX {$db_prefix}messages_id_board ON {$db_prefix}messages (id_board, id_msg, approved);
CREATE UNIQUE INDEX {$db_prefix}messages_id_member ON {$db_prefix}messages (id_member, id_msg);
CREATE INDEX {$db_prefix}messages_ip_index ON {$db_prefix}messages (poster_ip, id_topic);
CREATE INDEX {$db_prefix}messages_participation ON {$db_prefix}messages (id_member, id_topic);
CREATE INDEX {$db_prefix}messages_show_posts ON {$db_prefix}messages (id_member, id_board);
CREATE INDEX {$db_prefix}messages_id_member_msg ON {$db_prefix}messages (id_member, approved, id_msg);
CREATE INDEX {$db_prefix}messages_current_topic ON {$db_prefix}messages (id_topic, id_msg, id_member, approved);
CREATE INDEX {$db_prefix}messages_related_ip ON {$db_prefix}messages (id_member, poster_ip, id_msg);
CREATE INDEX {$db_prefix}messages_likes ON {$db_prefix}messages (likes);
#
# Table structure for table `moderators`
#

CREATE TABLE {$db_prefix}moderators (
	id_board smallint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	PRIMARY KEY (id_board, id_member)
);

#
# Table structure for table `moderator_groups`
#

CREATE TABLE {$db_prefix}moderator_groups (
	id_board smallint NOT NULL DEFAULT '0',
	id_group smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_board, id_group)
);

#
# Sequence for table `package_servers`
#

CREATE SEQUENCE {$db_prefix}package_servers_seq;

#
# Table structure for table `package_servers`
#

CREATE TABLE {$db_prefix}package_servers (
	id_server smallint DEFAULT nextval('{$db_prefix}package_servers_seq'),
	name varchar(255) NOT NULL DEFAULT '',
	url varchar(255) NOT NULL DEFAULT '',
	validation_url varchar(255) NOT NULL DEFAULT '',
	extra text,
	PRIMARY KEY (id_server)
);


#
# Sequence for table `permission_profiles`
#

CREATE SEQUENCE {$db_prefix}permission_profiles_seq START WITH 5;

#
# Table structure for table `permission_profiles`
#

CREATE TABLE {$db_prefix}permission_profiles (
	id_profile smallint DEFAULT nextval('{$db_prefix}permission_profiles_seq'),
	profile_name varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id_profile)
);

#
# Table structure for table `permissions`
#

CREATE TABLE {$db_prefix}permissions (
	id_group smallint NOT NULL DEFAULT '0',
	permission varchar(30) NOT NULL DEFAULT '',
	add_deny smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (id_group, permission)
);

#
# Sequence for table `personal_messages`
#

CREATE SEQUENCE {$db_prefix}personal_messages_seq;

#
# Table structure for table `personal_messages`
#

CREATE TABLE {$db_prefix}personal_messages (
	id_pm bigint DEFAULT nextval('{$db_prefix}personal_messages_seq'),
	id_pm_head bigint NOT NULL DEFAULT '0',
	id_member_from int NOT NULL DEFAULT '0',
	deleted_by_sender smallint NOT NULL DEFAULT '0',
	from_name varchar(255) NOT NULL,
	msgtime bigint NOT NULL DEFAULT '0',
	subject varchar(255) NOT NULL DEFAULT '',
	body text NOT NULL,
	version varchar(5) NOT NULL DEFAULT '',
	PRIMARY KEY (id_pm)
);

#
# Indexes for table `personal_messages`
#

CREATE INDEX {$db_prefix}personal_messages_id_member ON {$db_prefix}personal_messages (id_member_from, deleted_by_sender);
CREATE INDEX {$db_prefix}personal_messages_msgtime ON {$db_prefix}personal_messages (msgtime);
CREATE INDEX {$db_prefix}personal_messages_id_pm_head ON {$db_prefix}personal_messages (id_pm_head);

#
# Sequence for table `pm_labels`
#

CREATE SEQUENCE {$db_prefix}pm_labels_seq;

#
# Table structure for table `pm_labels`
#

CREATE TABLE {$db_prefix}pm_labels (
	id_label bigint NOT NULL DEFAULT nextval('{$db_prefix}pm_labels_seq'),
	id_member int NOT NULL DEFAULT '0',
	name varchar(30) NOT NULL DEFAULT '',
	PRIMARY KEY (id_label)
);

#
# Table structure for table `pm_labeled_messages`
#

CREATE TABLE {$db_prefix}pm_labeled_messages (
	id_label bigint NOT NULL DEFAULT '0',
	id_pm bigint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_label, id_pm)
);

#
# Table structure for table `pm_recipients`
#

CREATE TABLE {$db_prefix}pm_recipients (
	id_pm bigint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	bcc smallint NOT NULL DEFAULT '0',
	is_read smallint NOT NULL DEFAULT '0',
	is_new smallint NOT NULL DEFAULT '0',
	deleted smallint NOT NULL DEFAULT '0',
	in_inbox smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (id_pm, id_member)
);

#
# Indexes for table `pm_recipients`
#

CREATE UNIQUE INDEX {$db_prefix}pm_recipients_id_member ON {$db_prefix}pm_recipients (id_member, deleted, id_pm);

#
# Sequence for table `pm_rules`
#

CREATE SEQUENCE {$db_prefix}pm_rules_seq;

#
# Table structure for table `pm_rules`
#

CREATE TABLE {$db_prefix}pm_rules (
	id_rule bigint DEFAULT nextval('{$db_prefix}pm_rules_seq'),
	id_member int NOT NULL DEFAULT '0',
	rule_name varchar(60) NOT NULL,
	criteria text NOT NULL,
	actions text NOT NULL,
	delete_pm smallint NOT NULL DEFAULT '0',
	is_or smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_rule)
);

#
# Indexes for table `pm_rules`
#

CREATE INDEX {$db_prefix}pm_rules_id_member ON {$db_prefix}pm_rules (id_member);
CREATE INDEX {$db_prefix}pm_rules_delete_pm ON {$db_prefix}pm_rules (delete_pm);

#
# Sequence for table `polls`
#

CREATE SEQUENCE {$db_prefix}polls_seq;

#
# Table structure for table `polls`
#

CREATE TABLE {$db_prefix}polls (
	id_poll int DEFAULT nextval('{$db_prefix}polls_seq'),
	question varchar(255) NOT NULL DEFAULT '',
	voting_locked smallint NOT NULL DEFAULT '0',
	max_votes smallint NOT NULL DEFAULT '1',
	expire_time int NOT NULL DEFAULT '0',
	hide_results smallint NOT NULL DEFAULT '0',
	change_vote smallint NOT NULL DEFAULT '0',
	guest_vote smallint NOT NULL DEFAULT '0',
	num_guest_voters int NOT NULL DEFAULT '0',
	reset_poll int NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	poster_name varchar(255) NOT NULL,
	PRIMARY KEY (id_poll)
);

#
# Table structure for table `poll_choices`
#

CREATE TABLE {$db_prefix}poll_choices (
	id_poll int NOT NULL DEFAULT '0',
	id_choice smallint NOT NULL DEFAULT '0',
	label varchar(255) NOT NULL  DEFAULT '',
	votes smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_poll, id_choice)
);

#
# Sequence for table `qanda`
#

CREATE SEQUENCE {$db_prefix}qanda_seq;

#
# Table structure for table `qanda`
#

CREATE TABLE {$db_prefix}qanda (
	id_question smallint DEFAULT nextval('{$db_prefix}qanda_seq'),
	lngfile varchar(255) NOT NULL DEFAULT '',
	question varchar(255) NOT NULL DEFAULT '',
	answers text NOT NULL,
	PRIMARY KEY (id_question)
);

#
# Indexes for table `qanda`
#

CREATE INDEX {$db_prefix}qanda_lngfile ON {$db_prefix}qanda (lngfile varchar_pattern_ops);

#
# Sequence for table `scheduled_tasks`
#

CREATE SEQUENCE {$db_prefix}scheduled_tasks_seq START WITH 14;

#
# Table structure for table `scheduled_tasks`
#

CREATE TABLE {$db_prefix}scheduled_tasks (
	id_task smallint DEFAULT nextval('{$db_prefix}scheduled_tasks_seq'),
	next_time int NOT NULL DEFAULT '0',
	time_offset int NOT NULL DEFAULT '0',
	time_regularity smallint NOT NULL DEFAULT '0',
	time_unit varchar(1) NOT NULL DEFAULT 'h',
	disabled smallint NOT NULL DEFAULT '0',
	task varchar(24) NOT NULL DEFAULT '',
	callable varchar(60) NOT NULL DEFAULT '',
	PRIMARY KEY (id_task)
);

#
# Indexes for table `scheduled_tasks`
#

CREATE INDEX {$db_prefix}scheduled_tasks_next_time ON {$db_prefix}scheduled_tasks (next_time);
CREATE INDEX {$db_prefix}scheduled_tasks_disabled ON {$db_prefix}scheduled_tasks (disabled);
CREATE UNIQUE INDEX {$db_prefix}scheduled_tasks_task ON {$db_prefix}scheduled_tasks (task varchar_pattern_ops);

#
# Table structure for table `settings`
#

CREATE TABLE {$db_prefix}settings (
	variable varchar(255) NOT NULL DEFAULT '',
	value text NOT NULL,
	PRIMARY KEY (variable)
);

#
# Table structure for table `sessions`
#

CREATE UNLOGGED TABLE {$db_prefix}sessions (
	session_id varchar(128) NOT NULL DEFAULT '',
	last_update bigint NOT NULL DEFAULT '0',
	data text NOT NULL,
	PRIMARY KEY (session_id)
);

#
# Sequence for table `smileys`
#

CREATE SEQUENCE {$db_prefix}smileys_seq;

#
# Table structure for table `smileys`
#

CREATE TABLE {$db_prefix}smileys (
	id_smiley smallint DEFAULT nextval('{$db_prefix}smileys_seq'),
	code varchar(30) NOT NULL DEFAULT '',
	description varchar(80) NOT NULL DEFAULT '',
	smiley_row smallint NOT NULL DEFAULT '0',
	smiley_order smallint NOT NULL DEFAULT '0',
	hidden smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_smiley)
);

#
# Table structure for table `smiley_files`
#

CREATE TABLE {$db_prefix}smiley_files
(
	id_smiley SMALLINT NOT NULL DEFAULT '0',
	smiley_set VARCHAR(48) NOT NULL DEFAULT '',
	filename VARCHAR(48) NOT NULL DEFAULT '',
	PRIMARY KEY (id_smiley, smiley_set)
);

#
# Sequence for table `spiders`
#

CREATE SEQUENCE {$db_prefix}spiders_seq;

#
# Table structure for table `spiders`
#

CREATE TABLE {$db_prefix}spiders (
	id_spider smallint NOT NULL DEFAULT nextval('{$db_prefix}spiders_seq'),
	spider_name varchar(255) NOT NULL DEFAULT '',
	user_agent varchar(255) NOT NULL DEFAULT '',
	ip_info varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id_spider)
);

#
# Sequence for table `subscriptions`
#

CREATE SEQUENCE {$db_prefix}subscriptions_seq;

#
# Table structure for table `subscriptions`
#

CREATE TABLE {$db_prefix}subscriptions(
	id_subscribe int NOT NULL DEFAULT nextval('{$db_prefix}subscriptions_seq'),
	name varchar(60) NOT NULL DEFAULT '',
	description varchar(255) NOT NULL DEFAULT '',
	cost text NOT NULL,
	length varchar(6) NOT NULL DEFAULT '',
	id_group int NOT NULL DEFAULT '0',
	add_groups varchar(40) NOT NULL DEFAULT '',
	active smallint NOT NULL DEFAULT '1',
	repeatable smallint NOT NULL DEFAULT '0',
	allow_partial smallint NOT NULL DEFAULT '0',
	reminder smallint NOT NULL DEFAULT '0',
	email_complete text NOT NULL,
	PRIMARY KEY (id_subscribe)
);

#
# Indexes for table `subscriptions`
#

CREATE INDEX {$db_prefix}subscriptions_active ON {$db_prefix}subscriptions (active);

#
# Table structure for table `themes`
#

CREATE TABLE {$db_prefix}themes (
	id_member int DEFAULT '0',
	id_theme smallint  DEFAULT '1',
	variable varchar(255) DEFAULT '',
	value text NOT NULL,
	PRIMARY KEY (id_theme, id_member, variable)
);

#
# Indexes for table `themes`
#

CREATE INDEX {$db_prefix}themes_id_member ON {$db_prefix}themes (id_member);

#
# Sequence for table `topics`
#

CREATE SEQUENCE {$db_prefix}topics_seq START WITH 2;

#
# Table structure for table `topics`
#

CREATE TABLE {$db_prefix}topics (
	id_topic int DEFAULT nextval('{$db_prefix}topics_seq'),
	is_sticky smallint NOT NULL DEFAULT '0',
	id_board smallint NOT NULL DEFAULT '0',
	id_first_msg int NOT NULL DEFAULT '0',
	id_last_msg bigint NOT NULL DEFAULT '0',
	id_member_started int NOT NULL DEFAULT '0',
	id_member_updated int NOT NULL DEFAULT '0',
	id_poll int NOT NULL DEFAULT '0',
	id_previous_board smallint NOT NULL DEFAULT '0',
	id_previous_topic int NOT NULL DEFAULT '0',
	num_replies bigint NOT NULL DEFAULT '0',
	num_views bigint NOT NULL DEFAULT '0',
	locked smallint NOT NULL DEFAULT '0',
	redirect_expires int NOT NULL DEFAULT '0',
	id_redirect_topic bigint NOT NULL DEFAULT '0',
	unapproved_posts smallint NOT NULL DEFAULT '0',
	approved smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (id_topic)
);

#
# Indexes for table `topics`
#

CREATE UNIQUE INDEX {$db_prefix}topics_last_message ON {$db_prefix}topics (id_last_msg, id_board);
CREATE UNIQUE INDEX {$db_prefix}topics_first_message ON {$db_prefix}topics (id_first_msg, id_board);
CREATE UNIQUE INDEX {$db_prefix}topics_poll ON {$db_prefix}topics (id_poll, id_topic);
CREATE INDEX {$db_prefix}topics_is_sticky ON {$db_prefix}topics (is_sticky);
CREATE INDEX {$db_prefix}topics_approved ON {$db_prefix}topics (approved);
CREATE INDEX {$db_prefix}topics_member_started ON {$db_prefix}topics (id_member_started, id_board);
CREATE INDEX {$db_prefix}topics_last_message_sticky ON {$db_prefix}topics (id_board, is_sticky, id_last_msg);
CREATE INDEX {$db_prefix}topics_board_news ON {$db_prefix}topics (id_board, id_first_msg);

#
# Sequence for table `user_alerts`
#

CREATE SEQUENCE {$db_prefix}user_alerts_seq;

#
# Table structure for table `user_alerts`
#

CREATE TABLE {$db_prefix}user_alerts (
	id_alert bigint DEFAULT nextval('{$db_prefix}user_alerts_seq'),
	alert_time bigint NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	id_member_started bigint NOT NULL DEFAULT '0',
	member_name varchar(255) NOT NULL DEFAULT '',
	content_type varchar(255) NOT NULL DEFAULT '',
	content_id bigint NOT NULL DEFAULT '0',
	content_action varchar(255) NOT NULL DEFAULT '',
	is_read bigint NOT NULL DEFAULT '0',
	extra text NOT NULL,
	PRIMARY KEY (id_alert)
);

#
# Indexes for table `user_alerts`
#

CREATE INDEX {$db_prefix}user_alerts_id_member ON {$db_prefix}user_alerts (id_member);
CREATE INDEX {$db_prefix}user_alerts_alert_time ON {$db_prefix}user_alerts (alert_time);

#
# Table structure for table `user_alerts_prefs`
#

CREATE TABLE {$db_prefix}user_alerts_prefs (
	id_member int NOT NULL DEFAULT '0',
	alert_pref varchar(32) NOT NULL DEFAULT '',
	alert_value smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_member, alert_pref)
);

#
# Sequence for table `user_drafts`
#

CREATE SEQUENCE {$db_prefix}user_drafts_seq;

#
# Table structure for table `user_drafts`
#

CREATE TABLE {$db_prefix}user_drafts (
	id_draft bigint DEFAULT nextval('{$db_prefix}user_drafts_seq'),
	id_topic int NOT NULL DEFAULT '0',
	id_board smallint NOT NULL DEFAULT '0',
	id_reply bigint NOT NULL DEFAULT '0',
	type smallint NOT NULL DEFAULT '0',
	poster_time int NOT NULL DEFAULT '0',
	id_member int NOT NULL DEFAULT '0',
	subject varchar(255) NOT NULL DEFAULT '',
	smileys_enabled smallint NOT NULL DEFAULT '1',
	body text NOT NULL,
	icon varchar(16) NOT NULL DEFAULT 'xx',
	locked smallint NOT NULL DEFAULT '0',
	is_sticky smallint NOT NULL DEFAULT '0',
	to_list varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (id_draft)
);

#
# Indexes for table `user_drafts`
#

CREATE UNIQUE INDEX {$db_prefix}user_drafts_id_member ON {$db_prefix}user_drafts (id_member, id_draft, type);

#
# Table structure for table `user_likes`
#

CREATE TABLE {$db_prefix}user_likes (
	id_member int NOT NULL DEFAULT '0',
	content_type char(6) DEFAULT '',
	content_id int NOT NULL DEFAULT '0',
	like_time int NOT NULL DEFAULT '0',
	PRIMARY KEY (content_id, content_type, id_member)
);

#
# Indexes for table `user_likes`
#

CREATE INDEX {$db_prefix}user_likes_content ON {$db_prefix}user_likes (content_id, content_type);
CREATE INDEX {$db_prefix}user_likes_liker ON {$db_prefix}user_likes (id_member);

#
# Table structure for `mentions`
#
CREATE TABLE {$db_prefix}mentions (
	content_id int DEFAULT '0',
	content_type varchar(10) DEFAULT '',
	id_mentioned int DEFAULT 0,
	id_member int NOT NULL DEFAULT 0,
	time int NOT NULL DEFAULT 0,
	PRIMARY KEY (content_id, content_type, id_mentioned)
);

#
# Indexes for table `mentions`
#
CREATE INDEX {$db_prefix}mentions_content ON {$db_prefix}mentions (content_id, content_type);
CREATE INDEX {$db_prefix}mentions_mentionee ON {$db_prefix}mentions (id_member);

#
# Yay for transactions...
#
BEGIN;

#
# Dumping data for table `admin_info_files`
#

INSERT INTO {$db_prefix}admin_info_files
	(id_file, filename, path, parameters, data, filetype)
VALUES
	(1, 'current-version.js', '/smf/', 'version=%3$s', '', 'text/javascript'),
	(2, 'detailed-version.js', '/smf/', 'language=%1$s&version=%3$s', '', 'text/javascript'),
	(3, 'latest-news.js', '/smf/', 'language=%1$s&format=%2$s', '', 'text/javascript'),
	(4, 'latest-versions.txt', '/smf/', 'version=%3$s', '', 'text/plain');
# --------------------------------------------------------

#
# Dumping data for table `board_permissions`
#

INSERT INTO {$db_prefix}board_permissions
	(id_group, id_profile, permission)
VALUES (-1, 1, 'poll_view'),
	(0, 1, 'remove_own'),
	(0, 1, 'lock_own'),
	(0, 1, 'modify_own'),
	(0, 1, 'poll_add_own'),
	(0, 1, 'poll_edit_own'),
	(0, 1, 'poll_lock_own'),
	(0, 1, 'poll_post'),
	(0, 1, 'poll_view'),
	(0, 1, 'poll_vote'),
	(0, 1, 'post_attachment'),
	(0, 1, 'post_new'),
	(0, 1, 'post_draft'),
	(0, 1, 'post_reply_any'),
	(0, 1, 'post_reply_own'),
	(0, 1, 'post_unapproved_topics'),
	(0, 1, 'post_unapproved_replies_any'),
	(0, 1, 'post_unapproved_replies_own'),
	(0, 1, 'post_unapproved_attachments'),
	(0, 1, 'delete_own'),
	(0, 1, 'report_any'),
	(0, 1, 'view_attachments'),
	(2, 1, 'moderate_board'),
	(2, 1, 'post_new'),
	(2, 1, 'post_draft'),
	(2, 1, 'post_reply_own'),
	(2, 1, 'post_reply_any'),
	(2, 1, 'post_unapproved_topics'),
	(2, 1, 'post_unapproved_replies_any'),
	(2, 1, 'post_unapproved_replies_own'),
	(2, 1, 'post_unapproved_attachments'),
	(2, 1, 'poll_post'),
	(2, 1, 'poll_add_any'),
	(2, 1, 'poll_remove_any'),
	(2, 1, 'poll_view'),
	(2, 1, 'poll_vote'),
	(2, 1, 'poll_lock_any'),
	(2, 1, 'poll_edit_any'),
	(2, 1, 'report_any'),
	(2, 1, 'lock_own'),
	(2, 1, 'delete_own'),
	(2, 1, 'modify_own'),
	(2, 1, 'make_sticky'),
	(2, 1, 'lock_any'),
	(2, 1, 'remove_any'),
	(2, 1, 'move_any'),
	(2, 1, 'merge_any'),
	(2, 1, 'split_any'),
	(2, 1, 'delete_any'),
	(2, 1, 'modify_any'),
	(2, 1, 'approve_posts'),
	(2, 1, 'post_attachment'),
	(2, 1, 'view_attachments'),
	(3, 1, 'moderate_board'),
	(3, 1, 'post_new'),
	(3, 1, 'post_draft'),
	(3, 1, 'post_reply_own'),
	(3, 1, 'post_reply_any'),
	(3, 1, 'post_unapproved_topics'),
	(3, 1, 'post_unapproved_replies_any'),
	(3, 1, 'post_unapproved_replies_own'),
	(3, 1, 'post_unapproved_attachments'),
	(3, 1, 'poll_post'),
	(3, 1, 'poll_add_any'),
	(3, 1, 'poll_remove_any'),
	(3, 1, 'poll_view'),
	(3, 1, 'poll_vote'),
	(3, 1, 'poll_lock_any'),
	(3, 1, 'poll_edit_any'),
	(3, 1, 'report_any'),
	(3, 1, 'lock_own'),
	(3, 1, 'delete_own'),
	(3, 1, 'modify_own'),
	(3, 1, 'make_sticky'),
	(3, 1, 'lock_any'),
	(3, 1, 'remove_any'),
	(3, 1, 'move_any'),
	(3, 1, 'merge_any'),
	(3, 1, 'split_any'),
	(3, 1, 'delete_any'),
	(3, 1, 'modify_any'),
	(3, 1, 'approve_posts'),
	(3, 1, 'post_attachment'),
	(3, 1, 'view_attachments'),
	(-1, 2, 'poll_view'),
	(0, 2, 'remove_own'),
	(0, 2, 'lock_own'),
	(0, 2, 'modify_own'),
	(0, 2, 'poll_view'),
	(0, 2, 'poll_vote'),
	(0, 2, 'post_attachment'),
	(0, 2, 'post_new'),
	(0, 2, 'post_draft'),
	(0, 2, 'post_reply_any'),
	(0, 2, 'post_reply_own'),
	(0, 2, 'post_unapproved_topics'),
	(0, 2, 'post_unapproved_replies_any'),
	(0, 2, 'post_unapproved_replies_own'),
	(0, 2, 'post_unapproved_attachments'),
	(0, 2, 'delete_own'),
	(0, 2, 'report_any'),
	(0, 2, 'view_attachments'),
	(2, 2, 'moderate_board'),
	(2, 2, 'post_new'),
	(2, 2, 'post_draft'),
	(2, 2, 'post_reply_own'),
	(2, 2, 'post_reply_any'),
	(2, 2, 'post_unapproved_topics'),
	(2, 2, 'post_unapproved_replies_any'),
	(2, 2, 'post_unapproved_replies_own'),
	(2, 2, 'post_unapproved_attachments'),
	(2, 2, 'poll_post'),
	(2, 2, 'poll_add_any'),
	(2, 2, 'poll_remove_any'),
	(2, 2, 'poll_view'),
	(2, 2, 'poll_vote'),
	(2, 2, 'poll_lock_any'),
	(2, 2, 'poll_edit_any'),
	(2, 2, 'report_any'),
	(2, 2, 'lock_own'),
	(2, 2, 'delete_own'),
	(2, 2, 'modify_own'),
	(2, 2, 'make_sticky'),
	(2, 2, 'lock_any'),
	(2, 2, 'remove_any'),
	(2, 2, 'move_any'),
	(2, 2, 'merge_any'),
	(2, 2, 'split_any'),
	(2, 2, 'delete_any'),
	(2, 2, 'modify_any'),
	(2, 2, 'approve_posts'),
	(2, 2, 'post_attachment'),
	(2, 2, 'view_attachments'),
	(3, 2, 'moderate_board'),
	(3, 2, 'post_new'),
	(3, 2, 'post_draft'),
	(3, 2, 'post_reply_own'),
	(3, 2, 'post_reply_any'),
	(3, 2, 'post_unapproved_topics'),
	(3, 2, 'post_unapproved_replies_any'),
	(3, 2, 'post_unapproved_replies_own'),
	(3, 2, 'post_unapproved_attachments'),
	(3, 2, 'poll_post'),
	(3, 2, 'poll_add_any'),
	(3, 2, 'poll_remove_any'),
	(3, 2, 'poll_view'),
	(3, 2, 'poll_vote'),
	(3, 2, 'poll_lock_any'),
	(3, 2, 'poll_edit_any'),
	(3, 2, 'report_any'),
	(3, 2, 'lock_own'),
	(3, 2, 'delete_own'),
	(3, 2, 'modify_own'),
	(3, 2, 'make_sticky'),
	(3, 2, 'lock_any'),
	(3, 2, 'remove_any'),
	(3, 2, 'move_any'),
	(3, 2, 'merge_any'),
	(3, 2, 'split_any'),
	(3, 2, 'delete_any'),
	(3, 2, 'modify_any'),
	(3, 2, 'approve_posts'),
	(3, 2, 'post_attachment'),
	(3, 2, 'view_attachments'),
	(-1, 3, 'poll_view'),
	(0, 3, 'remove_own'),
	(0, 3, 'lock_own'),
	(0, 3, 'modify_own'),
	(0, 3, 'poll_view'),
	(0, 3, 'poll_vote'),
	(0, 3, 'post_attachment'),
	(0, 3, 'post_reply_any'),
	(0, 3, 'post_reply_own'),
	(0, 3, 'post_unapproved_replies_any'),
	(0, 3, 'post_unapproved_replies_own'),
	(0, 3, 'post_unapproved_attachments'),
	(0, 3, 'delete_own'),
	(0, 3, 'report_any'),
	(0, 3, 'view_attachments'),
	(2, 3, 'moderate_board'),
	(2, 3, 'post_new'),
	(2, 3, 'post_draft'),
	(2, 3, 'post_reply_own'),
	(2, 3, 'post_reply_any'),
	(2, 3, 'post_unapproved_topics'),
	(2, 3, 'post_unapproved_replies_any'),
	(2, 3, 'post_unapproved_replies_own'),
	(2, 3, 'post_unapproved_attachments'),
	(2, 3, 'poll_post'),
	(2, 3, 'poll_add_any'),
	(2, 3, 'poll_remove_any'),
	(2, 3, 'poll_view'),
	(2, 3, 'poll_vote'),
	(2, 3, 'poll_lock_any'),
	(2, 3, 'poll_edit_any'),
	(2, 3, 'report_any'),
	(2, 3, 'lock_own'),
	(2, 3, 'delete_own'),
	(2, 3, 'modify_own'),
	(2, 3, 'make_sticky'),
	(2, 3, 'lock_any'),
	(2, 3, 'remove_any'),
	(2, 3, 'move_any'),
	(2, 3, 'merge_any'),
	(2, 3, 'split_any'),
	(2, 3, 'delete_any'),
	(2, 3, 'modify_any'),
	(2, 3, 'approve_posts'),
	(2, 3, 'post_attachment'),
	(2, 3, 'view_attachments'),
	(3, 3, 'moderate_board'),
	(3, 3, 'post_new'),
	(3, 3, 'post_draft'),
	(3, 3, 'post_reply_own'),
	(3, 3, 'post_reply_any'),
	(3, 3, 'post_unapproved_topics'),
	(3, 3, 'post_unapproved_replies_any'),
	(3, 3, 'post_unapproved_replies_own'),
	(3, 3, 'post_unapproved_attachments'),
	(3, 3, 'poll_post'),
	(3, 3, 'poll_add_any'),
	(3, 3, 'poll_remove_any'),
	(3, 3, 'poll_view'),
	(3, 3, 'poll_vote'),
	(3, 3, 'poll_lock_any'),
	(3, 3, 'poll_edit_any'),
	(3, 3, 'report_any'),
	(3, 3, 'lock_own'),
	(3, 3, 'delete_own'),
	(3, 3, 'modify_own'),
	(3, 3, 'make_sticky'),
	(3, 3, 'lock_any'),
	(3, 3, 'remove_any'),
	(3, 3, 'move_any'),
	(3, 3, 'merge_any'),
	(3, 3, 'split_any'),
	(3, 3, 'delete_any'),
	(3, 3, 'modify_any'),
	(3, 3, 'approve_posts'),
	(3, 3, 'post_attachment'),
	(3, 3, 'view_attachments'),
	(-1, 4, 'poll_view'),
	(0, 4, 'poll_view'),
	(0, 4, 'poll_vote'),
	(0, 4, 'report_any'),
	(0, 4, 'view_attachments'),
	(2, 4, 'moderate_board'),
	(2, 4, 'post_new'),
	(2, 4, 'post_draft'),
	(2, 4, 'post_reply_own'),
	(2, 4, 'post_reply_any'),
	(2, 4, 'post_unapproved_topics'),
	(2, 4, 'post_unapproved_replies_any'),
	(2, 4, 'post_unapproved_replies_own'),
	(2, 4, 'post_unapproved_attachments'),
	(2, 4, 'poll_post'),
	(2, 4, 'poll_add_any'),
	(2, 4, 'poll_remove_any'),
	(2, 4, 'poll_view'),
	(2, 4, 'poll_vote'),
	(2, 4, 'poll_lock_any'),
	(2, 4, 'poll_edit_any'),
	(2, 4, 'report_any'),
	(2, 4, 'lock_own'),
	(2, 4, 'delete_own'),
	(2, 4, 'modify_own'),
	(2, 4, 'make_sticky'),
	(2, 4, 'lock_any'),
	(2, 4, 'remove_any'),
	(2, 4, 'move_any'),
	(2, 4, 'merge_any'),
	(2, 4, 'split_any'),
	(2, 4, 'delete_any'),
	(2, 4, 'modify_any'),
	(2, 4, 'approve_posts'),
	(2, 4, 'post_attachment'),
	(2, 4, 'view_attachments'),
	(3, 4, 'moderate_board'),
	(3, 4, 'post_new'),
	(3, 4, 'post_draft'),
	(3, 4, 'post_reply_own'),
	(3, 4, 'post_reply_any'),
	(3, 4, 'post_unapproved_topics'),
	(3, 4, 'post_unapproved_replies_any'),
	(3, 4, 'post_unapproved_replies_own'),
	(3, 4, 'post_unapproved_attachments'),
	(3, 4, 'poll_post'),
	(3, 4, 'poll_add_any'),
	(3, 4, 'poll_remove_any'),
	(3, 4, 'poll_view'),
	(3, 4, 'poll_vote'),
	(3, 4, 'poll_lock_any'),
	(3, 4, 'poll_edit_any'),
	(3, 4, 'report_any'),
	(3, 4, 'lock_own'),
	(3, 4, 'delete_own'),
	(3, 4, 'modify_own'),
	(3, 4, 'make_sticky'),
	(3, 4, 'lock_any'),
	(3, 4, 'remove_any'),
	(3, 4, 'move_any'),
	(3, 4, 'merge_any'),
	(3, 4, 'split_any'),
	(3, 4, 'delete_any'),
	(3, 4, 'modify_any'),
	(3, 4, 'approve_posts'),
	(3, 4, 'post_attachment'),
	(3, 4, 'view_attachments');
# --------------------------------------------------------

#
# Dumping data for table `boards`
#

INSERT INTO {$db_prefix}boards
	(id_board, id_cat, board_order, id_last_msg, id_msg_updated, name, description, num_topics, num_posts, member_groups)
VALUES (1, 1, 1, 1, 1, '{$default_board_name}', '{$default_board_description}', 1, 1, '-1,0,2');
# --------------------------------------------------------

#
# Dumping data for table `board_permissions_view`
#

INSERT INTO {$db_prefix}board_permissions_view
	(id_group, id_board, deny)
VALUES (-1,1,0), (0,1,0), (2,1,0);
# --------------------------------------------------------

#
# Dumping data for table `calendar`
#

INSERT INTO {$db_prefix}calendar
	(title, start_date, end_date, start_time, timezone, location, duration, rrule, rdates, type, enabled)
VALUES
	('April Fools\' Day', '2000-04-01', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Christmas', '2000-12-25', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Cinco de Mayo', '2000-05-05', '9999-12-31', NULL, NULL, 'Mexico, USA', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('D-Day', '2000-06-06', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Easter', '2000-04-23', '9999-12-31', NULL, NULL, '', 'P1D', 'EASTER_W', '', 1, 1),
	('Earth Day', '2000-04-22', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Father\'s Day', '2000-06-19', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY;BYMONTH=6;BYDAY=3SU', '', 1, 1),
	('Flag Day', '2000-06-14', '9999-12-31', NULL, NULL, 'USA', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Good Friday', '2000-04-21', '9999-12-31', NULL, NULL, '', 'P1D', 'EASTER_W-P2D', '', 1, 1),
	('Groundhog Day', '2000-02-02', '9999-12-31', NULL, NULL, 'Canada, USA', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Halloween', '2000-10-31', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Independence Day', '2000-07-04', '9999-12-31', NULL, NULL, 'USA', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Labor Day', '2000-09-03', '9999-12-31', NULL, NULL, 'USA', 'P1D', 'FREQ=YEARLY;BYMONTH=9;BYDAY=1MO', '', 1, 1),
	('Labour Day', '2000-09-03', '9999-12-31', NULL, NULL, 'Canada', 'P1D', 'FREQ=YEARLY;BYMONTH=9;BYDAY=1MO', '', 1, 1),
	('Memorial Day', '2000-05-31', '9999-12-31', NULL, NULL, 'USA', 'P1D', 'FREQ=YEARLY;BYMONTH=5;BYDAY=-1MO', '', 1, 1),
	('Mother\'s Day', '2000-05-08', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY;BYMONTH=5;BYDAY=2SU', '', 1, 1),
	('New Year\'s Day', '2000-01-01', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Remembrance Day', '2000-11-11', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('St. Patrick\'s Day', '2000-03-17', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Thanksgiving', '2000-11-26', '9999-12-31', NULL, NULL, 'USA', 'P1D', 'FREQ=YEARLY;BYMONTH=11;BYDAY=4TH', '', 1, 1),
	('United Nations Day', '2000-10-24', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Valentine\'s Day', '2000-02-14', '9999-12-31', NULL, NULL, '', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Veterans Day', '2000-11-11', '9999-12-31', NULL, NULL, 'USA', 'P1D', 'FREQ=YEARLY', '', 1, 1),
	('Vernal Equinox', '2000-03-20', '9999-12-31', '07:30:00', 'UTC', '', 'PT1M', 'FREQ=YEARLY;COUNT=1', '20000320T073000Z,20010320T131900Z,20020320T190800Z,20030321T005800Z,20040320T064700Z,20050320T123600Z,20060320T182500Z,20070321T001400Z,20080320T060400Z,20090320T115300Z,20100320T174200Z,20110320T233100Z,20120320T052000Z,20130320T111000Z,20140320T165900Z,20150320T224800Z,20160320T043700Z,20170320T102600Z,20180320T161600Z,20190320T220500Z,20200320T035400Z,20210320T094300Z,20220320T153200Z,20230320T212200Z,20240320T031100Z,20250320T090000Z,20260320T144900Z,20270320T203800Z,20280320T022800Z,20290320T081700Z,20300320T140600Z,20310320T195500Z,20320320T014400Z,20330320T073400Z,20340320T132300Z,20350320T191200Z,20360320T010100Z,20370320T065000Z,20380320T124000Z,20390320T182900Z,20400320T001800Z,20410320T060700Z,20420320T115600Z,20430320T174600Z,20440319T233500Z,20450320T052400Z,20460320T111300Z,20470320T170200Z,20480319T225200Z,20490320T044100Z,20500320T103000Z,20510320T161900Z,20520319T220800Z,20530320T035800Z,20540320T094700Z,20550320T153600Z,20560319T212500Z,20570320T031400Z,20580320T090400Z,20590320T145300Z,20600319T204200Z,20610320T023100Z,20620320T082000Z,20630320T141000Z,20640319T195900Z,20650320T014800Z,20660320T073700Z,20670320T132600Z,20680319T191600Z,20690320T010500Z,20700320T065400Z,20710320T124300Z,20720319T183200Z,20730320T002200Z,20740320T061100Z,20750320T120000Z,20760319T174900Z,20770319T233800Z,20780320T052800Z,20790320T111700Z,20800319T170600Z,20810319T225500Z,20820320T044400Z,20830320T103400Z,20840319T162300Z,20850319T221200Z,20860320T040100Z,20870320T095000Z,20880319T154000Z,20890319T212900Z,20900320T031800Z,20910320T090700Z,20920319T145600Z,20930319T204600Z,20940320T023500Z,20950320T082400Z,20960319T141300Z,20970319T200200Z,20980320T015200Z,20990320T074100Z', 1, 1),
	('Summer Solstice', '2000-06-21', '9999-12-31', '01:44:00', 'UTC', '', 'PT1M', 'FREQ=YEARLY;COUNT=1', '20000621T014400Z,20010621T073200Z,20020621T132000Z,20030621T190800Z,20040621T005600Z,20050621T064400Z,20060621T123200Z,20070621T182100Z,20080621T000900Z,20090621T055700Z,20100621T114500Z,20110621T173300Z,20120620T232100Z,20130621T050900Z,20140621T105700Z,20150621T164600Z,20160620T223400Z,20170621T042200Z,20180621T101000Z,20190621T155800Z,20200620T214600Z,20210621T033400Z,20220621T092300Z,20230621T151100Z,20240620T205900Z,20250621T024700Z,20260621T083500Z,20270621T142300Z,20280620T201100Z,20290621T015900Z,20300621T074800Z,20310621T133600Z,20320620T192400Z,20330621T011200Z,20340621T070000Z,20350621T124800Z,20360620T183600Z,20370621T002400Z,20380621T061300Z,20390621T120100Z,20400620T174900Z,20410620T233700Z,20420621T052500Z,20430621T111300Z,20440620T170100Z,20450620T224900Z,20460621T043700Z,20470621T102600Z,20480620T161400Z,20490620T220200Z,20500621T035000Z,20510621T093800Z,20520620T152600Z,20530620T211400Z,20540621T030200Z,20550621T085100Z,20560620T143900Z,20570620T202700Z,20580621T021500Z,20590621T080300Z,20600620T135100Z,20610620T193900Z,20620621T012700Z,20630621T071600Z,20640620T130400Z,20650620T185200Z,20660621T004000Z,20670621T062800Z,20680620T121600Z,20690620T180400Z,20700620T235200Z,20710621T054100Z,20720620T112900Z,20730620T171700Z,20740620T230500Z,20750621T045300Z,20760620T104100Z,20770620T162900Z,20780620T221700Z,20790621T040500Z,20800620T095400Z,20810620T154200Z,20820620T213000Z,20830621T031800Z,20840620T090600Z,20850620T145400Z,20860620T204200Z,20870621T023000Z,20880620T081900Z,20890620T140700Z,20900620T195500Z,20910621T014300Z,20920620T073100Z,20930620T131900Z,20940620T190700Z,20950621T005500Z,20960620T064300Z,20970620T123200Z,20980620T182000Z,20990621T000800Z', 1, 1),
	('Autumnal Equinox', '2000-09-22', '9999-12-31', '17:16:00', 'UTC', '', 'PT1M', 'FREQ=YEARLY;COUNT=1', '20000922T171600Z,20010922T230500Z,20020923T045400Z,20030923T104200Z,20040922T163100Z,20050922T222000Z,20060923T040800Z,20070923T095700Z,20080922T154600Z,20090922T213400Z,20100923T032300Z,20110923T091200Z,20120922T150100Z,20130922T204900Z,20140923T023800Z,20150923T082700Z,20160922T141500Z,20170922T200400Z,20180923T015300Z,20190923T074100Z,20200922T133000Z,20210922T191900Z,20220923T010700Z,20230923T065600Z,20240922T124500Z,20250922T183300Z,20260923T002200Z,20270923T061100Z,20280922T115900Z,20290922T174800Z,20300922T233700Z,20310923T052600Z,20320922T111400Z,20330922T170300Z,20340922T225200Z,20350923T044000Z,20360922T102900Z,20370922T161800Z,20380922T220600Z,20390923T035500Z,20400922T094400Z,20410922T153200Z,20420922T212100Z,20430923T031000Z,20440922T085800Z,20450922T144700Z,20460922T203600Z,20470923T022400Z,20480922T081300Z,20490922T140200Z,20500922T195000Z,20510923T013900Z,20520922T072800Z,20530922T131600Z,20540922T190500Z,20550923T005400Z,20560922T064200Z,20570922T123100Z,20580922T182000Z,20590923T000800Z,20600922T055700Z,20610922T114600Z,20620922T173400Z,20630922T232300Z,20640922T051200Z,20650922T110000Z,20660922T164900Z,20670922T223800Z,20680922T042600Z,20690922T101500Z,20700922T160400Z,20710922T215200Z,20720922T034100Z,20730922T093000Z,20740922T151800Z,20750922T210700Z,20760922T025600Z,20770922T084400Z,20780922T143300Z,20790922T202200Z,20800922T021000Z,20810922T075900Z,20820922T134800Z,20830922T193600Z,20840922T012500Z,20850922T071400Z,20860922T130200Z,20870922T185100Z,20880922T003900Z,20890922T062800Z,20900922T121700Z,20910922T180500Z,20920921T235400Z,20930922T054300Z,20940922T113100Z,20950922T172000Z,20960921T230900Z,20970922T045700Z,20980922T104600Z,20990922T163500Z', 1, 1),
	('Winter Solstice', '2000-12-21', '9999-12-31', '13:27:00', 'UTC', '', 'PT1M', 'FREQ=YEARLY;COUNT=1', '20001221T132700Z,20011221T191600Z,20021222T010600Z,20031222T065600Z,20041221T124600Z,20051221T183500Z,20061222T002500Z,20071222T061500Z,20081221T120400Z,20091221T175400Z,20101221T234400Z,20111222T053400Z,20121221T112300Z,20131221T171300Z,20141221T230300Z,20151222T045300Z,20161221T104200Z,20171221T163200Z,20181221T222200Z,20191222T041100Z,20201221T100100Z,20211221T155100Z,20221221T214100Z,20231222T033000Z,20241221T092000Z,20251221T151000Z,20261221T205900Z,20271222T024900Z,20281221T083900Z,20291221T142900Z,20301221T201800Z,20311222T020800Z,20321221T075800Z,20331221T134800Z,20341221T193700Z,20351222T012700Z,20361221T071700Z,20371221T130600Z,20381221T185600Z,20391222T004600Z,20401221T063600Z,20411221T122500Z,20421221T181500Z,20431222T000500Z,20441221T055400Z,20451221T114400Z,20461221T173400Z,20471221T232400Z,20481221T051300Z,20491221T110300Z,20501221T165300Z,20511221T224200Z,20521221T043200Z,20531221T102200Z,20541221T161200Z,20551221T220100Z,20561221T035100Z,20571221T094100Z,20581221T153000Z,20591221T212000Z,20601221T031000Z,20611221T090000Z,20621221T144900Z,20631221T203900Z,20641221T022900Z,20651221T081800Z,20661221T140800Z,20671221T195800Z,20681221T014700Z,20691221T073700Z,20701221T132700Z,20711221T191700Z,20721221T010600Z,20731221T065600Z,20741221T124600Z,20751221T183500Z,20761221T002500Z,20771221T061500Z,20781221T120500Z,20791221T175400Z,20801220T234400Z,20811221T053400Z,20821221T112300Z,20831221T171300Z,20841220T230300Z,20851221T045200Z,20861221T104200Z,20871221T163200Z,20881220T222200Z,20891221T041100Z,20901221T100100Z,20911221T155100Z,20921220T214000Z,20931221T033000Z,20941221T092000Z,20951221T150900Z,20961220T205900Z,20971221T024900Z,20981221T083900Z,20991221T142800Z', 1, 1);
# --------------------------------------------------------

#
# Dumping data for table `categories`
#

INSERT INTO {$db_prefix}categories
VALUES (1, 0, '{$default_category_name}', '', 1);
# --------------------------------------------------------

#
# Dumping data for table `custom_fields`
#

INSERT INTO {$db_prefix}custom_fields
	(col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement)
VALUES ('cust_skype', '{skype}', '{skype_desc}', 'text', 32, '', 2, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ', 1),
	('cust_loca', '{location}', '{location_desc}', 'text', 50, '', 4, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0),
	('cust_gender', '{gender}', '{gender_desc}', 'radio', 255, '{gender_0},{gender_1},{gender_2}', 5, 'nohtml', 1, 1, 0, 'forumprofile', 0, 1, 0, 0, '{gender_0}', '<span class=" main_icons gender_{KEY}" title="{INPUT}"></span>', 1);

# --------------------------------------------------------

#
# Dumping data for table `membergroups`
#

INSERT INTO {$db_prefix}membergroups
	(id_group, group_name, description, online_color, min_posts, icons, group_type)
VALUES (1, '{$default_administrator_group}', '', '#FF0000', -1, '5#iconadmin.png', 1),
	(2, '{$default_global_moderator_group}', '', '#0000FF', -1, '5#icongmod.png', 0),
	(3, '{$default_moderator_group}', '', '', -1, '5#iconmod.png', 0),
	(4, '{$default_newbie_group}', '', '', 0, '1#icon.png', 0),
	(5, '{$default_junior_group}', '', '', 50, '2#icon.png', 0),
	(6, '{$default_full_group}', '', '', 100, '3#icon.png', 0),
	(7, '{$default_senior_group}', '', '', 250, '4#icon.png', 0),
	(8, '{$default_hero_group}', '', '', 500, '5#icon.png', 0);
# --------------------------------------------------------

#
# Dumping data for table `message_icons`
#

# // !!! i18n
INSERT INTO {$db_prefix}message_icons
	(filename, title, icon_order)
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
	('wink', 'Wink', '11'),
	('poll', 'Poll', '12');
# --------------------------------------------------------

#
# Dumping data for table `messages`
#

INSERT INTO {$db_prefix}messages
	(id_msg, id_msg_modified, id_topic, id_board, poster_time, subject, poster_name, poster_email, modified_name, body, icon)
VALUES (1, 1, 1, 1, {$current_time}, '{$default_topic_subject}', 'Simple Machines', 'info@simplemachines.org', '', '{$default_topic_message}', 'xx');
# --------------------------------------------------------

#
# Dumping data for table `package_servers`
#

INSERT INTO {$db_prefix}package_servers
	(name, url, validation_url)
VALUES ('Simple Machines Third-party Mod Site', 'https://custom.simplemachines.org/packages/mods', 'https://custom.simplemachines.org/api.php?action=validate;version=v1;smf_version={SMF_VERSION}'),
		('Simple Machines Downloads Site', 'https://download.simplemachines.org/browse.php?api=v1;smf_version={SMF_VERSION}', 'https://download.simplemachines.org/validate.php?api=v1;smf_version={SMF_VERSION}');
# --------------------------------------------------------

#
# Dumping data for table `permission_profiles`
#

INSERT INTO {$db_prefix}permission_profiles
	(id_profile, profile_name)
VALUES (1, 'default'), (2, 'no_polls'), (3, 'reply_only'), (4, 'read_only');
# --------------------------------------------------------

#
# Dumping data for table `permissions`
#

INSERT INTO {$db_prefix}permissions
	(id_group, permission)
VALUES (-1, 'search_posts'),
	(-1, 'calendar_view'),
	(-1, 'view_stats'),
	(0, 'view_mlist'),
	(0, 'search_posts'),
	(0, 'profile_view'),
	(0, 'pm_read'),
	(0, 'pm_send'),
	(0, 'pm_draft'),
	(0, 'calendar_view'),
	(0, 'view_stats'),
	(0, 'who_view'),
	(0, 'profile_identity_own'),
	(0, 'profile_password_own'),
	(0, 'profile_blurb_own'),
	(0, 'profile_displayed_name_own'),
	(0, 'profile_signature_own'),
	(0, 'profile_website_own'),
	(0, 'profile_forum_own'),
	(0, 'profile_extra_own'),
	(0, 'profile_remove_own'),
	(0, 'profile_server_avatar'),
	(0, 'profile_upload_avatar'),
	(0, 'profile_remote_avatar'),
	(0, 'send_email_to_members'),
	(2, 'view_mlist'),
	(2, 'search_posts'),
	(2, 'profile_view'),
	(2, 'pm_read'),
	(2, 'pm_send'),
	(2, 'pm_draft'),
	(2, 'calendar_view'),
	(2, 'view_stats'),
	(2, 'who_view'),
	(2, 'profile_identity_own'),
	(2, 'profile_password_own'),
	(2, 'profile_blurb_own'),
	(2, 'profile_displayed_name_own'),
	(2, 'profile_signature_own'),
	(2, 'profile_website_own'),
	(2, 'profile_forum_own'),
	(2, 'profile_extra_own'),
	(2, 'profile_remove_own'),
	(2, 'profile_server_avatar'),
	(2, 'profile_upload_avatar'),
	(2, 'profile_remote_avatar'),
	(2, 'send_email_to_members'),
	(2, 'profile_title_own'),
	(2, 'calendar_post'),
	(2, 'calendar_edit_any'),
	(2, 'access_mod_center');
# --------------------------------------------------------

#
# Dumping data for table `scheduled_tasks`
#

INSERT INTO {$db_prefix}scheduled_tasks
	(id_task, next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(3, 0, 60, 1, 'd', 0, 'daily_maintenance', ''),
	(5, 0, 0, 1, 'd', 0, 'daily_digest', ''),
	(6, 0, 0, 1, 'w', 0, 'weekly_digest', ''),
	(7, 0, {$sched_task_offset}, 1, 'd', 0, 'fetchSMfiles', ''),
	(8, 0, 0, 1, 'd', 1, 'birthdayemails', ''),
	(9, 0, 0, 1, 'w', 0, 'weekly_maintenance', ''),
	(10, 0, 120, 1, 'd', 1, 'paid_subscriptions', ''),
	(11, 0, 120, 1, 'd', 0, 'remove_temp_attachments', ''),
	(12, 0, 180, 1, 'd', 0, 'remove_topic_redirect', ''),
	(13, 0, 240, 1, 'd', 0, 'remove_old_drafts', ''),
	(14, 0, 0, 1, 'w', 1, 'prune_log_topics', '');

# --------------------------------------------------------

#
# Dumping data for table `settings`
#

INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES ('smfVersion', '{$smf_version}'),
	('news', '{$default_news}'),
	('compactTopicPagesContiguous', '5'),
	('compactTopicPagesEnable', '1'),
	('todayMod', '1'),
	('enablePreviousNext', '1'),
	('pollMode', '1'),
	('enableCompressedOutput', '{$enableCompressedOutput}'),
	('attachmentSizeLimit', '128'),
	('attachmentPostLimit', '192'),
	('attachmentNumPerPostLimit', '4'),
	('attachmentDirSizeLimit', '10240'),
	('attachmentDirFileLimit', '1000'),
	('attachmentUploadDir', '{$attachdir}'),
	('attachmentExtensions', 'doc,gif,jpg,mpg,pdf,png,txt,zip'),
	('attachmentCheckExtensions', '0'),
	('attachmentShowImages', '1'),
	('attachmentEnable', '1'),
	('attachmentThumbnails', '1'),
	('attachmentThumbWidth', '150'),
	('attachmentThumbHeight', '150'),
	('use_subdirectories_for_attachments', '1'),
	('currentAttachmentUploadDir', 1),
	('censorIgnoreCase', '1'),
	('spoofdetector_censor', '1'),
	('mostOnline', '1'),
	('mostOnlineToday', '1'),
	('mostDate', {$current_time}),
	('trackStats', '1'),
	('userLanguage', '1'),
	('titlesEnable', '1'),
	('topicSummaryPosts', '15'),
	('enableErrorLogging', '1'),
	('max_image_width', '0'),
	('max_image_height', '0'),
	('onlineEnable', '0'),
	('boardindex_max_depth', '5'),
	('cal_enabled', '0'),
	('cal_showInTopic', '1'),
	('cal_maxyear', '2040'),
	('cal_minyear', '2018'),
	('cal_daysaslink', '0'),
	('cal_defaultboard', ''),
	('cal_showholidays', '1'),
	('cal_showbdays', '1'),
	('cal_showevents', '1'),
	('cal_maxspan', '0'),
	('cal_disable_prev_next', '0'),
	('cal_display_type', '0'),
	('cal_week_links', '2'),
	('cal_prev_next_links', '1'),
	('cal_short_days', '0'),
	('cal_short_months', '0'),
	('smtp_host', ''),
	('smtp_port', '25'),
	('smtp_username', ''),
	('smtp_password', ''),
	('mail_type', '0'),
	('timeLoadPageEnable', '0'),
	('totalMembers', '0'),
	('totalTopics', '1'),
	('totalMessages', '1'),
	('censor_vulgar', ''),
	('censor_proper', ''),
	('enablePostHTML', '0'),
	('theme_allow', '1'),
	('theme_default', '1'),
	('theme_guests', '1'),
	('xmlnews_enable', '1'),
	('xmlnews_maxlen', '255'),
	('registration_method', '{$registration_method}'),
	('send_validation_onChange', '0'),
	('send_welcomeEmail', '1'),
	('allow_editDisplayName', '1'),
	('allow_hideOnline', '1'),
	('spamWaitTime', '5'),
	('pm_spam_settings', '10,5,20'),
	('reserveWord', '0'),
	('reserveCase', '1'),
	('reserveUser', '1'),
	('reserveName', '1'),
	('reserveNames', E'{$default_reserved_names}'),
	('autoLinkUrls', '1'),
	('banLastUpdated', '0'),
	('smileys_dir', '{$boarddir}/Smileys'),
	('smileys_url', '{$boardurl}/Smileys'),
	('custom_avatar_dir', '{$boarddir}/custom_avatar'),
	('custom_avatar_url', '{$boardurl}/custom_avatar'),
	('avatar_directory', '{$boarddir}/avatars'),
	('avatar_url', '{$boardurl}/avatars'),
	('avatar_max_height_external', '65'),
	('avatar_max_width_external', '65'),
	('avatar_action_too_large', 'option_css_resize'),
	('avatar_max_height_upload', '65'),
	('avatar_max_width_upload', '65'),
	('avatar_resize_upload', '1'),
	('avatar_download_png', '1'),
	('failed_login_threshold', '3'),
	('oldTopicDays', '120'),
	('edit_wait_time', '90'),
	('edit_disable_time', '0'),
	('autoFixDatabase', '1'),
	('allow_guestAccess', '1'),
	('time_format', '{$default_time_format}'),
	('number_format', '1234.00'),
	('enableBBC', '1'),
	('max_messageLength', '20000'),
	('signature_settings', '1,300,0,0,0,0,0,0:'),
	('defaultMaxMessages', '15'),
	('defaultMaxTopics', '20'),
	('defaultMaxMembers', '30'),
	('enableParticipation', '1'),
	('recycle_enable', '0'),
	('recycle_board', '0'),
	('maxMsgID', '1'),
	('enableAllMessages', '0'),
	('knownThemes', '1'),
	('enableThemes', '1'),
	('who_enabled', '1'),
	('cookieTime', '3153600'),
	('lastActive', '15'),
	('smiley_sets_known', 'fugue,alienine'),
	('smiley_sets_names', '{$default_fugue_smileyset_name}'||E'\n'||'{$default_alienine_smileyset_name}'),
	('smiley_sets_default', 'fugue'),
	('cal_days_for_index', '7'),
	('requireAgreement', '1'),
	('requirePolicyAgreement', '0'),
	('unapprovedMembers', '0'),
	('default_personal_text', ''),
	('package_make_backups', '1'),
	('databaseSession_enable', '{$databaseSession_enable}'),
	('databaseSession_loose', '1'),
	('databaseSession_lifetime', '2880'),
	('search_cache_size', '50'),
	('search_results_per_page', '30'),
	('search_weight_frequency', '30'),
	('search_weight_age', '25'),
	('search_weight_length', '20'),
	('search_weight_subject', '15'),
	('search_weight_first_message', '10'),
	('search_max_results', '1200'),
	('search_floodcontrol_time', '5'),
	('permission_enable_deny', '0'),
	('permission_enable_postgroups', '0'),
	('mail_next_send', '0'),
	('mail_recent', '0000000000|0'),
	('settings_updated', '0'),
	('next_task_time', '1'),
	('warning_settings', '1,20,0'),
	('warning_watch', '10'),
	('warning_moderate', '35'),
	('warning_mute', '60'),
	('last_mod_report_action', '0'),
	('pruningOptions', '30,180,180,180,30,0'),
	('mark_read_beyond', '90'),
	('mark_read_delete_beyond', '365'),
	('mark_read_max_users', '500'),
	('modlog_enabled', '1'),
	('adminlog_enabled', '1'),
	('reg_verification', '1'),
	('visual_verification_type', '3'),
	('enable_buddylist', '1'),
	('birthday_email', 'happy_birthday'),
	('dont_repeat_theme_core', '1'),
	('dont_repeat_smileys_20', '1'),
	('dont_repeat_buddylists', '1'),
	('attachment_image_reencode', '1'),
	('attachment_image_paranoid', '0'),
	('attachment_thumb_png', '1'),
	('avatar_reencode', '1'),
	('avatar_paranoid', '0'),
	('drafts_post_enabled', '1'),
	('drafts_pm_enabled', '1'),
	('drafts_autosave_enabled', '1'),
	('drafts_show_saved_enabled', '1'),
	('drafts_keep_days', '7'),
	('topic_move_any', '0'),
	('mail_limit', '5'),
	('mail_quantity', '5'),
	('additional_options_collapsable', '1'),
	('show_modify', '1'),
	('show_user_images', '1'),
	('show_blurb', '1'),
	('show_profile_buttons', '1'),
	('enable_ajax_alerts', '1'),
	('alerts_auto_purge', '30'),
	('gravatarEnabled', '1'),
	('gravatarOverride', '0'),
	('gravatarAllowExtraEmail', '1'),
	('gravatarMaxRating', 'PG'),
	('defaultMaxListItems', '15'),
	('loginHistoryDays', '30'),
	('httponlyCookies', '1'),
	('samesiteCookies', 'lax'),
	('tfa_mode', '1'),
	('export_dir', '{$boarddir}/exports'),
	('export_expiry', '7'),
	('export_min_diskspace_pct', '5'),
	('export_rate', '250'),
	('allow_expire_redirect', '1'),
	('json_done', '1'),
	('attachments_21_done', '1'),
	('displayFields', '[{"col_name":"cust_icq","title":"ICQ","type":"text","order":"1","bbc":"0","placement":"1","enclose":"<a class=\"icq\" href=\"\/\/www.icq.com\/people\/{INPUT}\" target=\"_blank\" title=\"ICQ - {INPUT}\"><img src=\"{DEFAULT_IMAGES_URL}\/icq.png\" alt=\"ICQ - {INPUT}\"><\/a>","mlist":"0"},{"col_name":"cust_skype","title":"Skype","type":"text","order":"2","bbc":"0","placement":"1","enclose":"<a href=\"skype:{INPUT}?call\"><img src=\"{DEFAULT_IMAGES_URL}\/skype.png\" alt=\"{INPUT}\" title=\"{INPUT}\" \/><\/a> ","mlist":"0"},{"col_name":"cust_loca","title":"Location","type":"text","order":"4","bbc":"0","placement":"0","enclose":"","mlist":"0"},{"col_name":"cust_gender","title":"Gender","type":"radio","order":"5","bbc":"0","placement":"1","enclose":"<span class=\" main_icons gender_{KEY}\" title=\"{INPUT}\"><\/span>","mlist":"0","options":["None","Male","Female"]}]'),
	('minimize_files', '1'),
	('securityDisable_moderate', '1');
# --------------------------------------------------------

#
# Dumping data for table `smileys`
#

INSERT INTO {$db_prefix}smileys
	(code, description, smiley_order, hidden)
VALUES (':)', '{$default_smiley_smiley}', 0, 0),
	(';)', '{$default_wink_smiley}', 1, 0),
	(':D', '{$default_cheesy_smiley}', 2, 0),
	(';D', '{$default_grin_smiley}', 3, 0),
	('>:(', '{$default_angry_smiley}', 4, 0),
	(':(', '{$default_sad_smiley}', 5, 0),
	(':o', '{$default_shocked_smiley}', 6, 0),
	('8)', '{$default_cool_smiley}', 7, 0),
	('???', '{$default_huh_smiley}', 8, 0),
	('::)', '{$default_roll_eyes_smiley}', 9, 0),
	(':P', '{$default_tongue_smiley}', 10, 0),
	(':-[', '{$default_embarrassed_smiley}', 11, 0),
	(':-X', '{$default_lips_sealed_smiley}', 12, 0),
	(':-\', '{$default_undecided_smiley}', 13, 0),
	(':-*', '{$default_kiss_smiley}', 14, 0),
	(':''(', '{$default_cry_smiley}', 15, 0),
	('>:D', '{$default_evil_smiley}', 16, 1),
	('^-^', '{$default_azn_smiley}', 17, 1),
	('O0', '{$default_afro_smiley}', 18, 1),
	(':))', '{$default_laugh_smiley}', 19, 1),
	('C:-)', '{$default_police_smiley}', 20, 1),
	('O:-)', '{$default_angel_smiley}', 21, 1);
# --------------------------------------------------------

#
# Dumping data for table `spiders`
#

INSERT INTO {$db_prefix}spiders
	(spider_name, user_agent, ip_info)
VALUES ('Google', 'googlebot', ''),
	('Yahoo!', 'slurp', ''),
	('Bing', 'bingbot', ''),
	('Google (Mobile)', 'Googlebot-Mobile', ''),
	('Google (Image)', 'Googlebot-Image', ''),
	('Google (AdSense)', 'Mediapartners-Google', ''),
	('Google (Adwords)', 'AdsBot-Google', ''),
	('Yahoo! (Mobile)', 'YahooSeeker/M1A1-R2D2', ''),
	('Yahoo! (Image)', 'Yahoo-MMCrawler', ''),
	('Bing (Preview)', 'BingPreview', ''),
	('Bing (Ads)', 'adidxbot', ''),
	('Bing (MSNBot)', 'msnbot', ''),
	('Bing (Media)', 'msnbot-media', ''),
	('Cuil', 'twiceler', ''),
	('Ask', 'Teoma', ''),
	('Baidu', 'Baiduspider', ''),
	('Gigablast', 'Gigabot', ''),
	('InternetArchive', 'ia_archiver-web.archive.org', ''),
	('Alexa', 'ia_archiver', ''),
	('Omgili', 'omgilibot', ''),
	('EntireWeb', 'Speedy Spider', ''),
	('Yandex', 'yandex', '');
#---------------------------------------------------------

#
# Dumping data for table `themes`
#

INSERT INTO {$db_prefix}themes
	(id_theme, variable, value)
VALUES (1, 'name', '{$default_theme_name}'),
	(1, 'theme_url', '{$boardurl}/Themes/default'),
	(1, 'images_url', '{$boardurl}/Themes/default/images'),
	(1, 'theme_dir', '{$boarddir}/Themes/default'),
	(1, 'show_latest_member', '1'),
	(1, 'show_newsfader', '0'),
	(1, 'number_recent_posts', '0'),
	(1, 'show_stats_index', '1'),
	(1, 'newsfader_time', '3000'),
	(1, 'use_image_buttons', '1'),
	(1, 'enable_news', '1');

INSERT INTO {$db_prefix}themes
	(id_member, id_theme, variable, value)
VALUES (-1, 1, 'posts_apply_ignore_list', '1'),
	(-1, 1, 'drafts_show_saved_enabled', '1'),
	(-1, 1, 'return_to_post', '1');
# --------------------------------------------------------

#
# Dumping data for table `topics`
#

INSERT INTO {$db_prefix}topics
	(id_topic, id_board, id_first_msg, id_last_msg, id_member_started, id_member_updated)
VALUES (1, 1, 1, 1, 0, 0);
# --------------------------------------------------------

#
# Dumping data for table `user_alerts_prefs`
#

INSERT INTO {$db_prefix}user_alerts_prefs
	(id_member, alert_pref, alert_value)
VALUES (0, 'alert_timeout', 10),
	(0, 'announcements', 0),
	(0, 'birthday', 2),
	(0, 'board_notify', 1),
	(0, 'buddy_request', 1),
	(0, 'groupr_approved', 3),
	(0, 'groupr_rejected', 3),
	(0, 'member_group_request', 1),
	(0, 'member_register', 1),
	(0, 'member_report', 3),
	(0, 'member_report_reply', 3),
	(0, 'msg_auto_notify', 0),
	(0, 'msg_like', 1),
	(0, 'msg_mention', 1),
	(0, 'msg_notify_pref', 1),
	(0, 'msg_notify_type', 1),
	(0, 'msg_quote', 1),
	(0, 'msg_receive_body', 0),
	(0, 'msg_report', 1),
	(0, 'msg_report_reply', 1),
	(0, 'pm_new', 1),
	(0, 'pm_notify', 1),
	(0, 'pm_reply', 1),
	(0, 'request_group', 1),
	(0, 'topic_notify', 1),
	(0, 'unapproved_attachment', 1),
	(0, 'unapproved_reply', 3),
	(0, 'unapproved_post', 1),
	(0, 'warn_any', 1);
# --------------------------------------------------------

#
# Now we push all this through...
#
COMMIT;