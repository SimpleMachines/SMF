#### ATTENTION: You do not need to run or use this file!  The install.php script does everything for you!
#### Install script for PostgreSQL 8.0.1

#
# Create PostgreSQL functions.
# Some taken from http://www.xach.com/aolserver/mysql-functions.sql and http://pgfoundry.org/projects/mysqlcompat/.
# IP Regex in inet_aton from https://www.mkyong.com/database/regular-expression-in-postgresql/.

CREATE OR REPLACE FUNCTION FROM_UNIXTIME(integer) RETURNS timestamp AS
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

CREATE OR REPLACE FUNCTION day(date) RETURNS integer AS
  'SELECT EXTRACT(DAY FROM DATE($1))::integer AS result'
LANGUAGE 'sql';

CREATE OR REPLACE FUNCTION DAYOFMONTH (timestamp) RETURNS integer AS
  'SELECT CAST (EXTRACT(DAY FROM $1) AS integer) AS result'
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
  start_date date NOT NULL DEFAULT '1004-01-01',
  end_date date NOT NULL DEFAULT '1004-01-01',
  id_board smallint NOT NULL DEFAULT '0',
  id_topic int NOT NULL DEFAULT '0',
  title varchar(255) NOT NULL DEFAULT '',
  id_member int NOT NULL DEFAULT '0',
  start_time time,
  end_time time,
  timezone varchar(80),
  location VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id_event)
);

#
# Indexes for table `calendar`
#

CREATE INDEX {$db_prefix}calendar_start_date ON {$db_prefix}calendar (start_date);
CREATE INDEX {$db_prefix}calendar_end_date ON {$db_prefix}calendar (end_date);
CREATE INDEX {$db_prefix}calendar_topic ON {$db_prefix}calendar (id_topic, id_member);

#
# Sequence for table `calendar_holidays`
#

CREATE SEQUENCE {$db_prefix}calendar_holidays_seq;

#
# Table structure for table `calendar_holidays`
#

CREATE TABLE {$db_prefix}calendar_holidays (
  id_holiday smallint DEFAULT nextval('{$db_prefix}calendar_holidays_seq'),
  event_date date NOT NULL DEFAULT '1004-01-01',
  title varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id_holiday)
);

#
# Indexes for table `calendar_holidays`
#

CREATE INDEX {$db_prefix}calendar_holidays_event_date ON {$db_prefix}calendar_holidays (event_date);

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
  date date NOT NULL DEFAULT '1004-01-01',
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
  log_type varchar(8) NOT NULL DEFAULT 'post',
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
  page_hits smallint NOT NULL DEFAULT '0',
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
  pm_ignore_list varchar(255) NOT NULL DEFAULT '',
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
  timezone varchar(80) NOT NULL DEFAULT 'UTC',
  tfa_secret varchar(24) NOT NULL DEFAULT '',
  tfa_backup varchar(64) NOT NULL DEFAULT '',
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
  PRIMARY KEY (id_msg)
);

#
# Indexes for table `messages`
#

CREATE UNIQUE INDEX {$db_prefix}messages_id_board ON {$db_prefix}messages (id_board, id_msg);
CREATE UNIQUE INDEX {$db_prefix}messages_id_member ON {$db_prefix}messages (id_member, id_msg);
CREATE INDEX {$db_prefix}messages_approved ON {$db_prefix}messages (approved);
CREATE INDEX {$db_prefix}messages_ip_index ON {$db_prefix}messages (poster_ip, id_topic);
CREATE INDEX {$db_prefix}messages_participation ON {$db_prefix}messages (id_member, id_topic);
CREATE INDEX {$db_prefix}messages_show_posts ON {$db_prefix}messages (id_member, id_board);
CREATE INDEX {$db_prefix}messages_id_member_msg ON {$db_prefix}messages (id_member, approved, id_msg);
CREATE INDEX {$db_prefix}messages_current_topic ON {$db_prefix}messages (id_topic, id_msg, id_member, approved);
CREATE INDEX {$db_prefix}messages_related_ip ON {$db_prefix}messages (id_member, poster_ip, id_msg);
CREATE INDEX {$db_prefix}messages_likes ON {$db_prefix}messages (likes DESC);
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
  filename varchar(48) NOT NULL DEFAULT '',
  description varchar(80) NOT NULL DEFAULT '',
  smiley_row smallint NOT NULL DEFAULT '0',
  smiley_order smallint NOT NULL DEFAULT '0',
  hidden smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (id_smiley)
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
# Dumping data for table `calendar_holidays`
#

INSERT INTO {$db_prefix}calendar_holidays
	(title, event_date)
VALUES ('New Year''s', '0004-01-01'),
	('Christmas', '0004-12-25'),
	('Valentine''s Day', '0004-02-14'),
	('St. Patrick''s Day', '0004-03-17'),
	('April Fools', '0004-04-01'),
	('Earth Day', '0004-04-22'),
	('United Nations Day', '0004-10-24'),
	('Halloween', '0004-10-31'),
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
	('Father''s Day', '2008-06-15'),
	('Father''s Day', '2009-06-21'),
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
	('Vernal Equinox', '2010-03-20'),
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
	('Winter Solstice', '2010-12-21'),
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
	('Autumnal Equinox', '2010-09-22'),
	('Autumnal Equinox', '2011-09-23'),
	('Autumnal Equinox', '2012-09-22'),
	('Autumnal Equinox', '2013-09-22'),
	('Autumnal Equinox', '2014-09-22'),
	('Autumnal Equinox', '2015-09-23'),
	('Autumnal Equinox', '2016-09-22'),
	('Autumnal Equinox', '2017-09-22'),
	('Autumnal Equinox', '2018-09-22'),
	('Autumnal Equinox', '2019-09-23'),
	('Autumnal Equinox', '2020-09-22');

INSERT INTO {$db_prefix}calendar_holidays
	(title, event_date)
VALUES ('Independence Day', '0004-07-04'),
	('Cinco de Mayo', '0004-05-05'),
	('Flag Day', '0004-06-14'),
	('Veterans Day', '0004-11-11'),
	('Groundhog Day', '0004-02-02'),
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
	('D-Day', '0004-06-06');
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
VALUES ('cust_icq', 'ICQ', 'This is your ICQ number.', 'text', 12, '', 1, 'regex~[1-9][0-9]{4,9}~i', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" rel="noopener" title="ICQ - {INPUT}"><img src="{DEFAULT_IMAGES_URL}/icq.png" alt="ICQ - {INPUT}"></a>', 1),
	('cust_skype', 'Skype', 'Your Skype name', 'text', 32, '', 2, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ', 1),
	('cust_yahoo', 'Yahoo! Messenger', 'This is your Yahoo! Instant Messenger nickname.', 'text', 50, '', 3, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="yim" href="edit.yahoo.com/config/send_webmesg?.target={INPUT}" target="_blank" rel="noopener" title="Yahoo! Messenger - {INPUT}"><img src="{IMAGES_URL}/yahoo.png" alt="Yahoo! Messenger - {INPUT}"></a>', 1),
	('cust_loca', 'Location', 'Geographic location.', 'text', 50, '', 4, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0),
	('cust_gender', 'Gender', 'Your gender.', 'radio', 255, 'None,Male,Female', 5, 'nohtml', 1, 1, 0, 'forumprofile', 0, 1, 0, 0, 'None', '<span class=" generic_icons gender_{KEY}" title="{INPUT}"></span>', 1);

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
	(name, url)
VALUES ('Simple Machines Third-party Mod Site', 'https://custom.simplemachines.org/packages/mods');
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
	(13, 0, 240, 1, 'd', 0, 'remove_old_drafts', '');

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
	('mostOnline', '1'),
	('mostOnlineToday', '1'),
	('mostDate', {$current_time}),
	('allow_disableAnnounce', '1'),
	('trackStats', '1'),
	('userLanguage', '1'),
	('titlesEnable', '1'),
	('topicSummaryPosts', '15'),
	('enableErrorLogging', '1'),
	('log_ban_hits', '1'),
	('max_image_width', '0'),
	('max_image_height', '0'),
	('onlineEnable', '0'),
	('boardindex_max_depth', '5'),
	('cal_enabled', '0'),
	('cal_showInTopic', '1'),
	('cal_maxyear', '2030'),
	('cal_minyear', '2008'),
	('cal_daysaslink', '0'),
	('cal_defaultboard', ''),
	('cal_showholidays', '1'),
	('cal_showbdays', '1'),
	('cal_showevents', '1'),
	('cal_maxspan', '0'),
	('cal_highlight_events', '3'),
	('cal_highlight_holidays', '3'),
	('cal_highlight_birthdays', '3'),
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
	('reserveNames', '{$default_reserved_names}'),
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
	('time_offset', '0'),
	('cookieTime', '60'),
	('lastActive', '15'),
	('smiley_sets_known', 'fugue,alienine'),
	('smiley_sets_exts', '.png,.png'),
	('smiley_sets_names', '{$default_fugue_smileyset_name}\n{$default_alienine_smileyset_name}'),
	('smiley_sets_default', 'fugue'),
	('cal_days_for_index', '7'),
	('requireAgreement', '1'),
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
	('modlog_enabled', '1'),
	('adminlog_enabled', '1'),
	('cache_enable', '1'),
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
	('browser_cache', '?beta21'),
	('mail_limit', '5'),
	('mail_quantity', '5'),
	('additional_options_collapsable', '1'),
	('show_modify', '1'),
	('show_user_images', '1'),
	('show_blurb', '1'),
	('show_profile_buttons', '1'),
	('enable_ajax_alerts', '1'),
	('gravatarEnabled', '1'),
	('gravatarOverride', '0'),
	('gravatarAllowExtraEmail', '1'),
	('gravatarMaxRating', 'PG'),
	('defaultMaxListItems', '15'),
	('loginHistoryDays', '30'),
	('httponlyCookies', '1'),
	('tfa_mode', '1'),
	('allow_expire_redirect', '1'),
	('json_done', '1'),
	('displayFields', '[{"col_name":"cust_icq","title":"ICQ","type":"text","order":"1","bbc":"0","placement":"1","enclose":"<a class=\\"icq\\" href=\\"\\/\\/www.icq.com\\/people\\/{INPUT}\\" target=\\"_blank\\" title=\\"ICQ - {INPUT}\\"><img src=\\"{DEFAULT_IMAGES_URL}\\/icq.png\\" alt=\\"ICQ - {INPUT}\\"><\\/a>","mlist":"0"},{"col_name":"cust_skype","title":"Skype","type":"text","order":"2","bbc":"0","placement":"1","enclose":"<a href=\\"skype:{INPUT}?call\\"><img src=\\"{DEFAULT_IMAGES_URL}\\/skype.png\\" alt=\\"{INPUT}\\" title=\\"{INPUT}\\" \\/><\\/a> ","mlist":"0"},{"col_name":"cust_yahoo","title":"Yahoo! Messenger","type":"text","order":"3","bbc":"0","placement":"1","enclose":"<a class=\\"yim\\" href=\\"\\/\\/edit.yahoo.com\\/config\\/send_webmesg?.target={INPUT}\\" target=\\"_blank\\" title=\\"Yahoo! Messenger - {INPUT}\\"><img src=\\"{IMAGES_URL}\\/yahoo.png\\" alt=\\"Yahoo! Messenger - {INPUT}\\"><\\/a>","mlist":"0"},{"col_name":"cust_loca","title":"Location","type":"text","order":"4","bbc":"0","placement":"0","enclose":"","mlist":"0"},{"col_name":"cust_gender","title":"Gender","type":"radio","order":"5","bbc":"0","placement":"1","enclose":"<span class=\\" generic_icons gender_{KEY}\\" title=\\"{INPUT}\\"><\\/span>","mlist":"0",,"options":["None","Male","Female"]}]'),
	('minimize_files', '1'),
	('securityDisable_moderate', '1');
# --------------------------------------------------------

#
# Dumping data for table `smileys`
#

INSERT INTO {$db_prefix}smileys
	(code, filename, description, smiley_order, hidden)
VALUES (':)', 'smiley', '{$default_smiley_smiley}', 0, 0),
	(';)', 'wink', '{$default_wink_smiley}', 1, 0),
	(':D', 'cheesy', '{$default_cheesy_smiley}', 2, 0),
	(';D', 'grin', '{$default_grin_smiley}', 3, 0),
	('>:(', 'angry', '{$default_angry_smiley}', 4, 0),
	(':(', 'sad', '{$default_sad_smiley}', 5, 0),
	(':o', 'shocked', '{$default_shocked_smiley}', 6, 0),
	('8)', 'cool', '{$default_cool_smiley}', 7, 0),
	('???', 'huh', '{$default_huh_smiley}', 8, 0),
	('::)', 'rolleyes', '{$default_roll_eyes_smiley}', 9, 0),
	(':P', 'tongue', '{$default_tongue_smiley}', 10, 0),
	(':-[', 'embarrassed', '{$default_embarrassed_smiley}', 11, 0),
	(':-X', 'lipsrsealed', '{$default_lips_sealed_smiley}', 12, 0),
	(':-\\', 'undecided', '{$default_undecided_smiley}', 13, 0),
	(':-*', 'kiss', '{$default_kiss_smiley}', 14, 0),
	(':''(', 'cry', '{$default_cry_smiley}', 15, 0),
	('>:D', 'evil', '{$default_evil_smiley}', 16, 1),
	('^-^', 'azn', '{$default_azn_smiley}', 17, 1),
	('O0', 'afro', '{$default_afro_smiley}', 18, 1),
	(':))', 'laugh', '{$default_laugh_smiley}', 19, 1),
	('C:-)', 'police', '{$default_police_smiley}', 20, 1),
	('O:-)', 'angel', '{$default_angel_smiley}', 21, 1);
# --------------------------------------------------------

#
# Dumping data for table `spiders`
#

INSERT INTO {$db_prefix}spiders
	(spider_name, user_agent, ip_info)
VALUES ('Google', 'googlebot', ''),
	('Yahoo!', 'slurp', ''),
	('MSN', 'msnbot', ''),
	('Google (Mobile)', 'Googlebot-Mobile', ''),
	('Google (Image)', 'Googlebot-Image', ''),
	('Google (AdSense)', 'Mediapartners-Google', ''),
	('Google (Adwords)', 'AdsBot-Google', ''),
	('Yahoo! (Mobile)', 'YahooSeeker/M1A1-R2D2', ''),
	('Yahoo! (Image)', 'Yahoo-MMCrawler', ''),
	('MSN (Mobile)', 'MSNBOT_Mobile', ''),
	('MSN (Media)', 'msnbot-media', ''),
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
	(1, 'enable_news', '1'),
	(1, 'drafts_show_saved_enabled', '1');

INSERT INTO {$db_prefix}themes
	(id_member, id_theme, variable, value)
VALUES (-1, 1, 'posts_apply_ignore_list', '1'),
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
VALUES (0, 'member_group_request', 1),
	(0, 'member_register', 1),
	(0, 'msg_like', 1),
	(0, 'msg_report', 1),
	(0, 'msg_report_reply', 1),
	(0, 'unapproved_attachment', 1),
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
	(0, 'announcements', 0),
	(0, 'member_report', 3),
	(0, 'unapproved_post', 1),
	(0, 'buddy_request', 1),
	(0, 'warn_any', 1),
	(0, 'request_group', 1);
# --------------------------------------------------------

#
# Now we push all this through...
#
COMMIT;
