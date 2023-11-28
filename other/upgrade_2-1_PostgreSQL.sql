/* ATTENTION: You don't need to run or use this file! The upgrade.php script does everything for you! */

/******************************************************************************/
--- Removing karma
/******************************************************************************/

---# Removing all karma data, if selected
---{
if (!empty($upcontext['delete_karma']))
{
	// Delete old settings vars.
	Db::$db->query('', '
		DELETE FROM {db_prefix}settings
		WHERE variable IN ({array_string:karma_vars})',
		array(
			'karma_vars' => array('karmaMode', 'karmaTimeRestrictAdmins', 'karmaWaitTime', 'karmaMinPosts', 'karmaLabel', 'karmaSmiteLabel', 'karmaApplaudLabel'),
		)
	);

    $member_columns = Db::$db->list_columns('{db_prefix}members');

	// Cleaning up old karma member settings.
	if (in_array('karma_good', $member_columns))
		Db::$db->query('', '
			ALTER TABLE {db_prefix}members
			DROP karma_good',
			array()
		);

	// Does karma bad was enable?
	if (in_array('karma_bad', $member_columns))
		Db::$db->query('', '
			ALTER TABLE {db_prefix}members
			DROP karma_bad',
			array()
		);

	// Cleaning up old karma permissions.
	Db::$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE permission = {string:karma_vars}',
		array(
			'karma_vars' => 'karma_edit',
		)
	);

	// Cleaning up old log_karma table
	Db::$db->query('', '
		DROP TABLE IF EXISTS {db_prefix}log_karma',
		array()
	);
}
---}
---#

/******************************************************************************/
--- Emptying error log
/******************************************************************************/

---# Emptying error log, if selected
---{
if (!empty($upcontext['empty_error']))
{
	Db::$db->query('truncate_table', '
		TRUNCATE {db_prefix}log_errors',
		array(
		)
	);
}
---}
---#

/******************************************************************************/
--- Fixing sequences
/******************************************************************************/

---# Updating old sequences
---{
	$sequences = array(
		'admin_info_files_seq' => array(
			'table' => 'admin_info_files',
			'field' => 'id_file',
		),
		'attachments_seq' => array(
			'table' => 'attachments',
			'field' => 'id_attach',
		),
		'ban_groups_seq' => array(
			'table' => 'ban_groups',
			'field' => 'id_ban_group',
		),
		'ban_items_seq' => array(
			'table' => 'ban_items',
			'field' => 'id_ban',
		),
		'boards_seq' => array(
			'table' => 'boards',
			'field' => 'id_board',
		),
		'calendar_seq' => array(
			'table' => 'calendar',
			'field' => 'id_event',
		),
		'calendar_holidays_seq' => array(
			'table' => 'calendar_holidays',
			'field' => 'id_holiday',
		),
		'categories_seq' => array(
			'table' => 'categories',
			'field' => 'id_cat',
		),
		'custom_fields_seq' => array(
			'table' => 'custom_fields',
			'field' => 'id_field',
		),
		'log_actions_seq' => array(
			'table' => 'log_actions',
			'field' => 'id_action',
		),
		'log_banned_seq' => array(
			'table' => 'log_banned',
			'field' => 'id_ban_log',
		),
		'log_comments_seq' => array(
			'table' => 'log_comments',
			'field' => 'id_comment',
		),
		'log_errors_seq' => array(
			'table' => 'log_errors',
			'field' => 'id_error',
		),
		'log_group_requests_seq' => array(
			'table' => 'log_group_requests',
			'field' => 'id_request',
		),
		'log_member_notices_seq' => array(
			'table' => 'log_member_notices',
			'field' => 'id_notice',
		),
		'log_packages_seq' => array(
			'table' => 'log_packages',
			'field' => 'id_install',
		),
		'log_reported_seq' => array(
			'table' => 'log_reported',
			'field' => 'id_report',
		),
		'log_reported_comments_seq' => array(
			'table' => 'log_reported_comments',
			'field' => 'id_comment',
		),
		'log_scheduled_tasks_seq' => array(
			'table' => 'log_scheduled_tasks',
			'field' => 'id_log',
		),
		'log_spider_hits_seq' => array(
			'table' => 'log_spider_hits',
			'field' => 'id_hit',
		),
		'log_subscribed_seq' => array(
			'table' => 'log_subscribed',
			'field' => 'id_sublog',
		),
		'mail_queue_seq' => array(
			'table' => 'mail_queue',
			'field' => 'id_mail',
		),
		'membergroups_seq' => array(
			'table' => 'membergroups',
			'field' => 'id_group',
		),
		'members_seq' => array(
			'table' => 'members',
			'field' => 'id_member',
		),
		'message_icons_seq' => array(
			'table' => 'message_icons',
			'field' => 'id_icon',
		),
		'messages_seq' => array(
			'table' => 'messages',
			'field' => 'id_msg',
		),
		'package_servers_seq' => array(
			'table' => 'package_servers',
			'field' => 'id_server',
		),
		'permission_profiles_seq' => array(
			'table' => 'permission_profiles',
			'field' => 'id_profile',
		),
		'personal_messages_seq' => array(
			'table' => 'personal_messages',
			'field' => 'id_pm',
		),
		'pm_rules_seq' => array(
			'table' => 'pm_rules',
			'field' => 'id_rule',
		),
		'polls_seq' => array(
			'table' => 'polls',
			'field' => 'id_poll',
		),
		'scheduled_tasks_seq' => array(
			'table' => 'scheduled_tasks',
			'field' => 'id_task',
		),
		'smileys_seq' => array(
			'table' => 'smileys',
			'field' => 'id_smiley',
		),
		'spiders_seq' => array(
			'table' => 'spiders',
			'field' => 'id_spider',
		),
		'subscriptions_seq' => array(
			'table' => 'subscriptions',
			'field' => 'id_subscribe',
		),
		'topics_seq' => array(
			'table' => 'topics',
			'field' => 'id_topic',
		),
	);

	foreach ($sequences as $key => $value)
	{
		upgrade_query("
			SELECT setval('{$db_prefix}" . $key . "', (SELECT COALESCE(MAX(" . $value['field'] ."),1) FROM {$db_prefix}" . $value['table'] ."))
		");
	}
---}
---#

/******************************************************************************/
--- add find_in_set function
/******************************************************************************/
---# add find_in_set function
---{
	upgrade_query("
CREATE OR REPLACE FUNCTION FIND_IN_SET(needle text, haystack text) RETURNS integer AS '
	SELECT i AS result
	FROM generate_series(1, array_upper(string_to_array($2,'',''), 1)) AS g(i)
	WHERE  (string_to_array($2,'',''))[i] = $1
		UNION ALL
	SELECT 0
	LIMIT 1'
LANGUAGE 'sql';
");
---}
---#

/******************************************************************************/
--- Fixing dates...
/******************************************************************************/
---# Updating old values
UPDATE {$db_prefix}calendar
SET start_date = concat_ws('-', CASE WHEN EXTRACT(YEAR FROM start_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM start_date), EXTRACT(DAY FROM start_date))::date
WHERE EXTRACT(YEAR FROM start_date) < 1004;

UPDATE {$db_prefix}calendar
SET end_date = concat_ws('-', CASE WHEN EXTRACT(YEAR FROM end_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM end_date), EXTRACT(DAY FROM end_date))::date
WHERE EXTRACT(YEAR FROM end_date) < 1004;

UPDATE {$db_prefix}calendar_holidays
SET event_date = concat_ws('-', CASE WHEN EXTRACT(YEAR FROM event_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM event_date), EXTRACT(DAY FROM event_date))::date
WHERE EXTRACT(YEAR FROM event_date) < 1004;

UPDATE {$db_prefix}log_spider_stats
SET stat_date = concat_ws('-', CASE WHEN EXTRACT(YEAR FROM stat_date) < 1004 THEN 1004 END, EXTRACT(MONTH FROM stat_date), EXTRACT(DAY FROM stat_date))::date
WHERE EXTRACT(YEAR FROM stat_date) < 1004;

ALTER TABLE {$db_prefix}log_spider_stats
ALTER stat_date SET DEFAULT '1004-01-01';

UPDATE {$db_prefix}members
SET birthdate = concat_ws('-', CASE WHEN EXTRACT(YEAR FROM birthdate) < 1004 THEN 1004 END, CASE WHEN EXTRACT(MONTH FROM birthdate) < 1 THEN 1 ELSE EXTRACT(MONTH FROM birthdate) END, CASE WHEN EXTRACT(DAY FROM birthdate) < 1 THEN 1 ELSE EXTRACT(DAY FROM birthdate) END)::date
WHERE EXTRACT(YEAR FROM birthdate) < 1004 OR EXTRACT(MONTH FROM birthdate) < 1 OR EXTRACT(DAY FROM birthdate) < 1;
---#

---# Changing default values
ALTER TABLE {$db_prefix}calendar ALTER COLUMN start_date SET DEFAULT '1004-01-01'::date;
ALTER TABLE {$db_prefix}calendar ALTER COLUMN end_date SET DEFAULT '1004-01-01'::date;
ALTER TABLE {$db_prefix}calendar_holidays ALTER COLUMN event_date SET DEFAULT '1004-01-01'::date;
ALTER TABLE {$db_prefix}log_spider_stats ALTER COLUMN stat_date SET DEFAULT '1004-01-01'::date;
ALTER TABLE {$db_prefix}members ALTER COLUMN birthdate SET DEFAULT '1004-01-01'::date;
ALTER TABLE {$db_prefix}log_activity ALTER COLUMN date DROP DEFAULT;
---#

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Creating login history sequence.
CREATE SEQUENCE IF NOT EXISTS {$db_prefix}member_logins_seq;
---#

---# Creating login history table.
CREATE TABLE IF NOT EXISTS {$db_prefix}member_logins (
	id_login int DEFAULT nextval('{$db_prefix}member_logins_seq'),
	id_member int NOT NULL DEFAULT '0',
	time int NOT NULL DEFAULT '0',
	ip inet,
	ip2 inet,
	PRIMARY KEY (id_login)
);

DROP INDEX IF EXISTS {$db_prefix}member_logins_id_member;
DROP INDEX IF EXISTS {$db_prefix}member_logins_time;

CREATE INDEX {$db_prefix}member_logins_id_member ON {$db_prefix}member_logins (id_member);
CREATE INDEX {$db_prefix}member_logins_time ON {$db_prefix}member_logins (time);
---#

---# Copying the current package backup setting...
---{
if (!isset(Config::$modSettings['package_make_full_backups']) && isset(Config::$modSettings['package_make_backups']))
	upgrade_query("
		INSERT INTO {$db_prefix}settings
			(variable, value)
		VALUES
			('package_make_full_backups', '" . Config::$modSettings['package_make_backups'] . "')");
---}
---#

---# Copying the current "allow users to disable word censor" setting...
---{
if (!isset(Config::$modSettings['allow_no_censored']))
{
	$request = upgrade_query("
		SELECT value
		FROM {$db_prefix}themes
		WHERE variable='allow_no_censored'
		AND id_theme = 1 OR id_theme = 'Config::$modSettings[theme_default]'
	");

	// Is it set for either "default" or the one they've set as default?
	while ($row = Db::$db->fetch_assoc($request))
	{
		if ($row['value'] == 1)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}settings
				VALUES ('allow_no_censored', 1)
			");

			// Don't do this twice...
			break;
		}
	}
}
---}
---#

---# Converting collapsed categories...
---{
// We cannot do this twice
if (version_compare(trim(strtolower(@Config::$modSettings['smfVersion'])), '2.1.foo', '<'))
{
	$request = Db::$db->query('', '
		SELECT id_member, id_cat
		FROM {db_prefix}collapsed_categories');

	$inserts = array();
	while ($row = Db::$db->fetch_assoc($request))
		$inserts[] = array($row['id_member'], 1, 'collapse_category_' . $row['id_cat'], $row['id_cat']);
	Db::$db->free_result($request);

	if (!empty($inserts))
		Db::$db->insert('replace',
			'{db_prefix}themes',
			array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
			$inserts,
			array('id_theme', 'id_member', 'variable')
		);
}
---}
---#

---# Parsing board descriptions and names
---{
if (version_compare(trim(strtolower(@Config::$modSettings['smfVersion'])), '2.1.foo', '<'))
{
    $request = Db::$db->query('', '
        SELECT name, description, id_board
        FROM {db_prefix}boards');

    $inserts = array();

    Db::$db->free_result($request);

    while ($row = Db::$db->fetch_assoc($request))
    {
        $inserts[] = array(
            'name' => Utils::htmlspecialchars(strip_tags(SMF\BBCodeParser::load()->unparse($row['name']))),
            'description' => Utils::htmlspecialchars(strip_tags(SMF\BBCodeParser::load()->unparse($row['description']))),
            'id' => $row['id'],
        );
    }

    if (!empty($inserts))
    {
        foreach ($inserts as $insert)
        {
            Db::$db->query('', '
                UPDATE {db_prefix}boards
                SET name = {string:name}, description = {string:description}
                WHERE id = {int:id}',
                $insert
            );
        }
    }
}
---}
---#

---# Dropping "collapsed_categories"
DROP TABLE IF EXISTS {$db_prefix}collapsed_categories;
---#

---# Adding new "topic_move_any" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('topic_move_any', '1') ON CONFLICT DO NOTHING;
---#

---# Adding new "enable_ajax_alerts" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('enable_ajax_alerts', '1') ON CONFLICT DO NOTHING;
---#

---# Adding new "alerts_auto_purge" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('alerts_auto_purge', '30') ON CONFLICT DO NOTHING;
---#

---# Adding new "minimize_files" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('minimize_files', '1') ON CONFLICT DO NOTHING;
---#

---# Collapse object
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('additional_options_collapsable', '1') ON CONFLICT DO NOTHING;
---#

---# Adding new "defaultMaxListItems" setting
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('defaultMaxListItems', '15') ON CONFLICT DO NOTHING;
---#

---# Adding new "loginHistoryDays" setting
---{
	if (!isset(Config::$modSettings['loginHistoryDays']))
		Db::$db->insert('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('loginHistoryDays', '30'),
			array('variable')
		);
---}
---#

---# Enable some settings we ripped from Theme settings
---{
	$ripped_settings = array('show_modify', 'show_user_images', 'show_blurb', 'show_profile_buttons', 'subject_toggle', 'hide_post_group');

	$request = Db::$db->query('', '
		SELECT variable, value
		FROM {db_prefix}themes
		WHERE variable IN({array_string:ripped_settings})
			AND id_member = 0
			AND id_theme = 1',
		array(
			'ripped_settings' => $ripped_settings,
		)
	);

	$inserts = array();
	while ($row = Db::$db->fetch_assoc($request))
		$inserts[] = array($row['variable'], $row['value']);

	Db::$db->free_result($request);
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		$inserts,
		array('variable')
	);
---}
---#

---# Disable Moderation Center Security if it doesn't exist
---{
	if (!isset(Config::$modSettings['securityDisable_moderate']))
		Db::$db->insert('ignore',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('securityDisable_moderate', '1'),
			array('variable')
		);
---}
---#

---# Adding new profile data export settings
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('export_dir', '{$boarddir}/exports') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('export_expiry', '7') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('export_min_diskspace_pct', '5') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('export_rate', '250') ON CONFLICT DO NOTHING;
---#

---# Adding settings for marking boards as read
---{
	if (!isset(Config::$modSettings['mark_read_beyond']))
		Db::$db->insert('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('mark_read_beyond', '90'),
			array()
		);
	if (!isset(Config::$modSettings['mark_read_delete_beyond']))
		Db::$db->insert('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('mark_read_delete_beyond', '365'),
			array()
		);
	if (!isset(Config::$modSettings['mark_read_max_users']))
		Db::$db->insert('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('mark_read_max_users', '500'),
			array()
		);
---}
---#

/******************************************************************************/
--- Updating legacy attachments...
/******************************************************************************/

---# Adding more space to the mime_type column.
ALTER TABLE {$db_prefix}attachments
	ALTER COLUMN mime_type TYPE VARCHAR(128);
---#

---# Converting legacy attachments.
---{

// Need to know a few things first.
$custom_av_dir = !empty(Config::$modSettings['custom_avatar_dir']) ? Config::$modSettings['custom_avatar_dir'] : Config::boarddir .'/custom_avatar';

// This little fellow has to cooperate...
if (!is_writable($custom_av_dir))
{
	// Try 755 and 775 first since 777 doesn't always work and could be a risk...
	$chmod_values = array(0755, 0775, 0777);

	foreach($chmod_values as $val)
	{
		// If it's writable, break out of the loop
		if (is_writable($custom_av_dir))
			break;
		else
			@chmod($custom_av_dir, $val);
	}
}

// If we already are using a custom dir, delete the predefined one.
if (realpath($custom_av_dir) != realpath(Config::boarddir .'/custom_avatar'))
{
	// Borrow custom_avatars index.php file.
	if (!file_exists($custom_av_dir . '/index.php'))
		@rename(Config::boarddir .'/custom_avatar/index.php', $custom_av_dir .'/index.php');
	else
		@unlink(Config::boarddir . '/custom_avatar/index.php');

	// Borrow blank.png as well
	if (!file_exists($custom_av_dir . '/blank.png'))
		@rename(Config::boarddir . '/custom_avatar/blank.png', $custom_av_dir . '/blank.png');
	else
		@unlink(Config::boarddir . '/custom_avatar/blank.png');

	// Attempt to delete the directory.
	@rmdir(Config::boarddir .'/custom_avatar');
}

$request = upgrade_query("
	SELECT COUNT(*)
	FROM {$db_prefix}attachments
	WHERE attachment_type != 1");
list ($step_progress['total']) = Db::$db->fetch_row($request);
Db::$db->free_result($request);

$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
$step_progress['name'] = 'Converting legacy attachments';
$step_progress['current'] = $_GET['a'];

// We may be using multiple attachment directories.
if (!empty(Config::$modSettings['currentAttachmentUploadDir']) && !is_array(Config::$modSettings['attachmentUploadDir']) && empty(Config::$modSettings['json_done']))
	Config::$modSettings['attachmentUploadDir'] = @unserialize(Config::$modSettings['attachmentUploadDir']);

// No need to do this if we already did it previously...
if (empty(Config::$modSettings['attachments_21_done']))
  $is_done = false;
else
  $is_done = true;

while (!$is_done)
{
	nextSubStep($substep);

	$request = upgrade_query("
		SELECT id_attach, id_member, id_folder, filename, file_hash, mime_type
		FROM {$db_prefix}attachments
		WHERE attachment_type != 1
		ORDER BY id_attach
		LIMIT $_GET[a], 100");

	// Finished?
	if (Db::$db->num_rows($request) == 0)
		$is_done = true;

	while ($row = Db::$db->fetch_assoc($request))
	{
		// The current folder.
		$currentFolder = !empty(Config::$modSettings['currentAttachmentUploadDir']) ? Config::$modSettings['attachmentUploadDir'][$row['id_folder']] : Config::$modSettings['attachmentUploadDir'];

		$fileHash = '';

		// Old School?
		if (empty($row['file_hash']))
		{
			// Remove international characters (windows-1252)
			// These lines should never be needed again. Still, behave.
			if (empty(Config::$db_character_set) || Config::$db_character_set != 'utf8')
			{
				$row['filename'] = strtr($row['filename'],
					"\x8a\x8e\x9a\x9e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xe0\xe1\xe2\xe3\xe4\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xff",
					'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
				$row['filename'] = strtr($row['filename'], array("\xde" => 'TH', "\xfe" =>
					'th', "\xd0" => 'DH', "\xf0" => 'dh', "\xdf" => 'ss', "\x8c" => 'OE',
					"\x9c" => 'oe', "\xc6" => 'AE', "\xe6" => 'ae', "\xb5" => 'u'));
			}
			// Sorry, no spaces, dots, or anything else but letters allowed.
			$row['filename'] = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $row['filename']);

			// Create a nice hash.
			$fileHash = hash_hmac('sha1', $row['filename'] . time(), Config::$image_proxy_secret);

			// Iterate through the possible attachment names until we find the one that exists
			$oldFile = $currentFolder . '/' . $row['id_attach']. '_' . strtr($row['filename'], '.', '_') . md5($row['filename']);
			if (!file_exists($oldFile))
			{
				$oldFile = $currentFolder . '/' . $row['filename'];
				if (!file_exists($oldFile)) $oldFile = false;
			}

			// Build the new file.
			$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $fileHash .'.dat';
		}

		// Just rename the file.
		else
		{
			$oldFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'];
			$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'] .'.dat';

			// Make sure it exists...
			if (!file_exists($oldFile))
				$oldFile = false;
		}

		if (!$oldFile)
		{
			// Existing attachment could not be found. Just skip it...
			continue;
		}

		// Check if the av is an attachment
		if ($row['id_member'] != 0)
		{
			if (rename($oldFile, $custom_av_dir . '/' . $row['filename']))
			{
				upgrade_query("
					UPDATE {$db_prefix}attachments
					SET file_hash = '', attachment_type = 1
					WHERE id_attach = $row[id_attach]");
				$_GET['a'] -= 1;
			}
		}
		// Just a regular attachment.
		else
		{
			rename($oldFile, $newFile);
		}

		// Only update this if it was successful and the file was using the old system.
		if (empty($row['file_hash']) && !empty($fileHash) && file_exists($newFile) && !file_exists($oldFile))
			upgrade_query("
				UPDATE {$db_prefix}attachments
				SET file_hash = '$fileHash'
				WHERE id_attach = $row[id_attach]");

		// While we're here, do we need to update the mime_type?
		if (empty($row['mime_type']) && file_exists($newFile))
		{
			$size = @getimagesize($newFile);
			if (!empty($size['mime']))
				Db::$db->query('', '
					UPDATE {db_prefix}attachments
					SET mime_type = {string:mime_type}
					WHERE id_attach = {int:id_attach}',
					array(
						'id_attach' => $row['id_attach'],
						'mime_type' => substr($size['mime'], 0, 20),
					)
				);
		}
	}
	Db::$db->free_result($request);

	$_GET['a'] += 100;
	$step_progress['current'] = $_GET['a'];
}

unset($_GET['a']);
---}
---#

---# Note attachment conversion complete
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('attachments_21_done', '1') ON CONFLICT DO NOTHING;
---#

---# Fixing invalid sizes on attachments
---{
$attachs = array();
// If id_member = 0, then it's not an avatar
// If attachment_type = 0, then it's also not a thumbnail
// Theory says there shouldn't be *that* many of these
$request = Db::$db->query('', '
	SELECT id_attach, mime_type, width, height
	FROM {db_prefix}attachments
	WHERE id_member = 0
		AND attachment_type = 0'
);
while ($row = Db::$db->fetch_assoc($request))
{
	if (($row['width'] > 0 || $row['height'] > 0) && strpos($row['mime_type'], 'image') !== 0)
		$attachs[] = $row['id_attach'];
}
Db::$db->free_result($request);

if (!empty($attachs))
	Db::$db->query('', '
		UPDATE {db_prefix}attachments
		SET width = 0,
			height = 0
		WHERE id_attach IN ({array_int:attachs})',
		array(
			'attachs' => $attachs,
		)
	);
---}
---#

---# Fixing attachment directory setting...
---{
// If it's a directory or an array, ensure it is stored as a serialized string (prep for later serial_to_json conversion)
// Also ensure currentAttachmentUploadDir is set even for single directories
// Make sure to do it in memory and in db...
if (empty(Config::$modSettings['json_done']))
{
	if (!is_array(Config::$modSettings['attachmentUploadDir']) && is_dir(Config::$modSettings['attachmentUploadDir']))
	{
		Config::$modSettings['attachmentUploadDir'] = serialize(array(1 => Config::$modSettings['attachmentUploadDir']));
		Db::$db->query('', '
			UPDATE {db_prefix}settings
			SET value = {string:attach_dir}
			WHERE variable = {string:uploadDir}',
			array(
				'attach_dir' => Config::$modSettings['attachmentUploadDir'],
				'uploadDir' => 'attachmentUploadDir'
			)
		);
		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('currentAttachmentUploadDir', '1'),
			array('variable')
		);
	}
	elseif (is_array(Config::$modSettings['attachmentUploadDir']))
	{
		Config::$modSettings['attachmentUploadDir'] = serialize(Config::$modSettings['attachmentUploadDir']);
		Db::$db->query('', '
			UPDATE {db_prefix}settings
			SET value = {string:attach_dir}
			WHERE variable = {string:uploadDir}',
			array(
				'attach_dir' => Config::$modSettings['attachmentUploadDir'],
				'uploadDir' => 'attachmentUploadDir'
			)
		);
		// Assume currentAttachmentUploadDir is already set
	}
}
---}
---#

/******************************************************************************/
--- Adding support for logging who fulfils a group request.
/******************************************************************************/

---# Adding new columns to log_group_requests
ALTER TABLE {$db_prefix}log_group_requests
ADD COLUMN IF NOT EXISTS status smallint NOT NULL default '0',
ADD COLUMN IF NOT EXISTS id_member_acted int NOT NULL default '0',
ADD COLUMN IF NOT EXISTS member_name_acted varchar(255) NOT NULL default '',
ADD COLUMN IF NOT EXISTS time_acted int NOT NULL default '0',
ADD COLUMN IF NOT EXISTS act_reason text NOT NULL default '';
---#

---# Adding new columns to log_group_requests - drop defaults now that existing rows have been set
ALTER TABLE {$db_prefix}log_group_requests
ALTER COLUMN act_reason DROP DEFAULT;
---#

---# Adjusting the indexes for log_group_requests
DROP INDEX IF EXISTS {$db_prefix}log_group_requests_id_member;
CREATE INDEX {$db_prefix}log_group_requests_id_member ON {$db_prefix}log_group_requests (id_member, id_group);
---#

/******************************************************************************/
--- Package Manager New Features
/******************************************************************************/
---# Adding support for <credits> tag in package manager
ALTER TABLE {$db_prefix}log_packages
ADD COLUMN IF NOT EXISTS credits TEXT NOT NULL default '';
---#

---# Adding support for <credits> - drop default now that existing rows have been set
ALTER TABLE {$db_prefix}log_packages
ALTER COLUMN credits DROP DEFAULT;
---#

---# Adding support for package hashes
ALTER TABLE {$db_prefix}log_packages
ADD COLUMN IF NOT EXISTS sha256_hash TEXT;
---#

---# Adding support for validation servers
ALTER TABLE {$db_prefix}package_servers
ADD COLUMN IF NOT EXISTS validation_url VARCHAR(255) DEFAULT '',
ADD COLUMN IF NOT EXISTS extra TEXT;
---#

---# Add Package Validation to Downloads Site
---{
	$request = Db::$db->query('', '
		SELECT id_server
		FROM {db_prefix}package_servers
		WHERE url LIKE {string:downloads_site}',
		array(
			'downloads_site' => 'https://download.simplemachines.org%',
		)
	);

	if (Db::$db->num_rows($request) != 0)
		list($downloads_server) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	if (empty($downloads_server))
		Db::$db->insert('',
			'{db_prefix}package_servers',
			array('name' => 'string', 'url' => 'string', 'validation_url' => 'string'),
			array('Simple Machines Download Site', 'https://download.simplemachines.org/browse.php?api=v1;smf_version={SMF_VERSION}', 'https://download.simplemachines.org/validate.php?api=v1;smf_version={SMF_VERSION}'),
			array('id_server')
		);
---}
---#

---# Ensure The Simple Machines Customize Site is https
UPDATE {$db_prefix}package_servers
SET url = 'https://custom.simplemachines.org/packages/mods'
WHERE url = 'http://custom.simplemachines.org/packages/mods';
---#

---# Add validation to Simple Machines Customize Site
UPDATE {$db_prefix}package_servers
SET validation_url = 'https://custom.simplemachines.org/api.php?action=validate;version=v1;smf_version={SMF_VERSION}'
WHERE url = 'https://custom.simplemachines.org/packages/mods';
---#

/******************************************************************************/
--- Adding more space for session ids
/******************************************************************************/
---# Altering the session_id columns...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session type varchar(128);

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session type varchar(128);

	ALTER TABLE {$db_prefix}sessions
	ALTER COLUMN session_id type varchar(128);");

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
ALTER TABLE {$db_prefix}topics
ADD COLUMN IF NOT EXISTS redirect_expires int NOT NULL DEFAULT '0';

ALTER TABLE {$db_prefix}topics
ADD COLUMN IF NOT EXISTS id_redirect_topic int NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding new scheduled tasks
/******************************************************************************/
---# Adding a new column "callable" to scheduled_tasks table
ALTER TABLE {$db_prefix}scheduled_tasks
ADD COLUMN IF NOT EXISTS callable varchar(60) NOT NULL default '';
---#

---# Adding new scheduled tasks
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(0, 120, 1, 'd', 0, 'remove_temp_attachments', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(0, 180, 1, 'd', 0, 'remove_topic_redirect', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(0, 240, 1, 'd', 0, 'remove_old_drafts', '') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}scheduled_tasks
	(next_time, time_offset, time_regularity, time_unit, disabled, task, callable)
VALUES
	(0, 0, 1, 'w', 1, 'prune_log_topics', '') ON CONFLICT DO NOTHING;
---#

---# Adding a new task-related setting...
---{
	if (!isset(Config::$modSettings['allow_expire_redirect']))
	{
		$get_info = Db::$db->query('', '
			SELECT disabled
			FROM {db_prefix}scheduled_tasks
			WHERE task = {string:remove_redirect}',
			array(
				'remove_redirect' => 'remove_topic_redirect'
			)
		);

		list($task_disabled) = Db::$db->fetch_row($get_info);
		Db::$db->free_result($get_info);

		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('allow_expire_redirect', !$task_disabled),
			array('variable')
		);
	}
---}
---#

---# Remove old tasks added by modifications...
---{
	$vanilla_tasks = array(
		'birthdayemails',
		'daily_digest',
		'daily_maintenance',
		'fetchSMfiles',
		'paid_subscriptions',
		'remove_temp_attachments',
		'remove_topic_redirect',
		'remove_old_drafts',
		'prune_log_topics',
		'weekly_digest',
		'weekly_maintenance');

	Db::$db->query('', '
		DELETE FROM {db_prefix}scheduled_tasks
		WHERE task NOT IN ({array_string:keep_tasks});',
		array(
			'keep_tasks' => $vanilla_tasks
		)
	);
---}
---#

/******************************************************************************/
---- Adding background tasks support
/******************************************************************************/
---# Adding the sequence
CREATE SEQUENCE IF NOT EXISTS {$db_prefix}background_tasks_seq;
---#

---# Adding the table
CREATE TABLE IF NOT EXISTS {$db_prefix}background_tasks (
	id_task bigint DEFAULT nextval('{$db_prefix}background_tasks_seq'),
	task_file varchar(255) NOT NULL DEFAULT '',
	task_class varchar(255) NOT NULL DEFAULT '',
	task_data text NOT NULL,
	claimed_time int NOT NULL DEFAULT '0',
	PRIMARY KEY (id_task)
);
---#

/******************************************************************************/
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
ALTER TABLE {$db_prefix}boards
ADD COLUMN IF NOT EXISTS deny_member_groups varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding setting for max depth of sub-boards to check for new posts, etc.
/******************************************************************************/
---# Adding the boardindex_max_depth setting.
INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('boardindex_max_depth', '1') ON CONFLICT DO NOTHING;
---#

/******************************************************************************/
--- Removing manage_boards permission from anyone who shouldn't have it
/******************************************************************************/
---# Removing manage_boards permission
---{
if (version_compare(trim(strtolower(@Config::$modSettings['smfVersion'])), '2.1.foo', '<'))
{
	$board_managers = array();

	$request = Db::$db->query('', '
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:permission}',
		array(
			'permission' => 'manage_boards',
		)
	);
	if (Db::$db->num_rows($request) != 0)
	{
		while ($row = Db::$db->fetch_assoc($request))
			$board_managers[$row['id_group']] = 0;
	}
	Db::$db->free_result($request);

	$request = Db::$db->query('', '
		SELECT member_groups
		FROM {db_prefix}boards',
		array()
	);
	$num_boards = Db::$db->num_rows($request);
	while ($row = Db::$db->fetch_assoc($request))
	{
		$groups = explode(',', $row['member_groups']);
		foreach ($groups as $group)
			if (array_key_exists($group, $board_managers))
				++$board_managers[$group];
	}
	Db::$db->free_result($request);

	$ex_board_managers = array();
	foreach ($board_managers as $id_group => $board_count)
		if ($board_count < $num_boards)
			$ex_board_managers[] = $id_group;

	if (!empty($ex_board_managers))
	{
		Db::$db->query('', '
			DELETE FROM {db_prefix}permissions
			WHERE permission = {string:permission}
				AND id_group IN ({array_int:ex_board_managers})',
			array(
				'permission' => 'manage_boards',
				'ex_board_managers' => $ex_board_managers,
			)
		);
	}
}
---}
---#

/******************************************************************************/
--- Adding support for category descriptions
/******************************************************************************/
---# Adding new columns to categories...
ALTER TABLE {$db_prefix}categories
ADD COLUMN IF NOT EXISTS description text;


UPDATE {$db_prefix}categories
SET description = '';

ALTER TABLE {$db_prefix}categories
ALTER COLUMN description SET NOT NULL;
---#

/******************************************************************************/
--- Adding support for alerts
/******************************************************************************/
---# Adding the count to the members table...
ALTER TABLE {$db_prefix}members
ADD COLUMN IF NOT EXISTS alerts int NOT NULL default '0';
---#

---# Adding the new table for alerts.
CREATE SEQUENCE IF NOT EXISTS {$db_prefix}user_alerts_seq;

CREATE TABLE IF NOT EXISTS {$db_prefix}user_alerts (
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

DROP INDEX IF EXISTS {$db_prefix}user_alerts_id_member;
DROP INDEX IF EXISTS {$db_prefix}user_alerts_alert_time;

CREATE INDEX {$db_prefix}user_alerts_id_member ON {$db_prefix}user_alerts (id_member);
CREATE INDEX {$db_prefix}user_alerts_alert_time ON {$db_prefix}user_alerts (alert_time);
---#

---# Adding alert preferences.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_alerts_prefs (
	id_member int NOT NULL DEFAULT '0',
	alert_pref varchar(32) NOT NULL DEFAULT '',
	alert_value smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_member, alert_pref)
);

INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'alert_timeout', 10) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'announcements', 0) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'birthday', 2) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'board_notify', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'buddy_request', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'groupr_approved', 3) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'groupr_rejected', 3) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_group_request', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_register', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_report', 3) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'member_report_reply', 3) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_auto_notify', 0) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_like', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_mention', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_notify_pref', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_notify_type', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_quote', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_receive_body', 0) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_report', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'msg_report_reply', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'pm_new', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'pm_notify', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'pm_reply', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'request_group', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'topic_notify', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'unapproved_attachment', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'unapproved_reply', 3) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'unapproved_post', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}user_alerts_prefs (id_member, alert_pref, alert_value) VALUES (0, 'warn_any', 1) ON CONFLICT DO NOTHING;

---#

---# Upgrading post notification settings
---{
// First see if we still have a notify_regularity column
$results = Db::$db->list_columns('{db_prefix}members');
if (in_array('notify_regularity', $results))
{
	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Upgrading post notification settings';
	$step_progress['current'] = $_GET['a'];

	$limit = 10000;
	$is_done = false;

	$request = Db::$db->query('', 'SELECT COUNT(*) FROM {db_prefix}members');
	list($maxMembers) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	while (!$is_done)
	{
		nextSubStep($substep);
		$inserts = array();

		// Skip errors here so we don't croak if the columns don't exist...
		$request = Db::$db->query('', '
			SELECT id_member, notify_regularity, notify_send_body, notify_types, notify_announcements
			FROM {db_prefix}members
			ORDER BY id_member
			LIMIT {int:start}, {int:limit}',
			array(
				'db_error_skip' => true,
				'start' => $_GET['a'],
				'limit' => $limit,
			)
		);
		if (Db::$db->num_rows($request) != 0)
		{
			while ($row = Db::$db->fetch_assoc($request))
			{
				$inserts[] = array($row['id_member'], 'msg_receive_body', !empty($row['notify_send_body']) ? 1 : 0);
				$inserts[] = array($row['id_member'], 'msg_notify_pref', intval($row['notify_regularity']) + 1);
				$inserts[] = array($row['id_member'], 'msg_notify_type', $row['notify_types']);
				$inserts[] = array($row['id_member'], 'announcements', !empty($row['notify_announcements']) ? 1 : 0);
			}
			Db::$db->free_result($request);
		}

		Db::$db->insert('ignore',
			'{db_prefix}user_alerts_prefs',
			array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'),
			$inserts,
			array('id_member', 'alert_pref')
		);

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];

		if ($step_progress['current'] >= $maxMembers)
			$is_done = true;
	}
	unset($_GET['a']);
}
---}
---#

---# Dropping old notification fields from the members table
ALTER TABLE {$db_prefix}members
	DROP IF EXISTS notify_send_body,
	DROP IF EXISTS notify_types,
	DROP IF EXISTS notify_regularity,
	DROP IF EXISTS notify_announcements;
---#

---# Upgrading auto notify setting
---{
$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
$step_progress['name'] = 'Upgrading auto notify setting';
$step_progress['current'] = $_GET['a'];

$limit = 10000;
$is_done = false;

$request = $smcFunc['db_query']('', '
	SELECT COUNT(*)
	FROM {db_prefix}themes
	WHERE variable = {string:auto_notify}',
	array(
		'auto_notify' => 'auto_notify',
	)
);
list($maxMembers) = $smcFunc['db_fetch_row']($request);
$smcFunc['db_free_result']($request);

while (!$is_done)
{
	nextSubStep($substep);
	$inserts = array();

	// This setting is stored over in the themes table in 2.0...
	$request = $smcFunc['db_query']('', '
		SELECT id_member, value
		FROM {db_prefix}themes
		WHERE variable = {string:auto_notify}
		ORDER BY id_member
		LIMIT {int:start}, {int:limit}',
		array(
			'auto_notify' => 'auto_notify',
			'start' => $_GET['a'],
			'limit' => $limit,
		)
	);
	if ($smcFunc['db_num_rows']($request) != 0)
	{
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$inserts[] = array($row['id_member'], 'msg_auto_notify', !empty($row['value']) ? 1 : 0);
		}
		$smcFunc['db_free_result']($request);
	}

	$smcFunc['db_insert']('ignore',
		'{db_prefix}user_alerts_prefs',
		array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'),
		$inserts,
		array('id_member', 'alert_pref')
	);

	$_GET['a'] += $limit;
	$step_progress['current'] = $_GET['a'];

	if ($step_progress['current'] >= $maxMembers)
		$is_done = true;
}
unset($_GET['a']);
---}
---#

---# Dropping old auto notify settings from the themes table
DELETE FROM {$db_prefix}themes
	WHERE variable = 'auto_notify';
---#

---# Creating alert prefs for watched topics
---{
	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Creating alert preferences for watched topics';
	$step_progress['current'] = $_GET['a'];

	$limit = 10000;
	$is_done = false;

	$request = Db::$db->query('', 'SELECT COUNT(*) FROM {db_prefix}log_notify WHERE id_member <> 0 AND id_topic <> 0');
	list($maxTopics) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	while (!$is_done)
	{
		nextSubStep($substep);
		$inserts = array();

		$request = Db::$db->query('', '
			SELECT id_member, (\'topic_notify_\' || id_topic) as alert_pref, 1 as alert_value
			FROM {db_prefix}log_notify
			WHERE id_member <> 0 AND id_topic <> 0
			LIMIT {int:start}, {int:limit}',
			array(
				'db_error_skip' => true,
				'start' => $_GET['a'],
				'limit' => $limit,
			)
		);
		if (Db::$db->num_rows($request) != 0)
		{
			$inserts = Db::$db->fetch_all($request);
		}
		Db::$db->free_result($request);

		Db::$db->insert('ignore',
			'{db_prefix}user_alerts_prefs',
			array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'),
			$inserts,
			array('id_member', 'alert_pref')
		);

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];

		if ($step_progress['current'] >= $maxTopics)
			$is_done = true;
	}
	unset($_GET['a']);
---}
---#

---# Creating alert prefs for watched boards
---{
	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Creating alert preferences for watched boards';
	$step_progress['current'] = $_GET['a'];

	$limit = 10000;
	$is_done = false;

	$request = Db::$db->query('', 'SELECT COUNT(*) FROM {db_prefix}log_notify WHERE id_member <> 0 AND id_board <> 0');
	list($maxBoards) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	while (!$is_done)
	{
		nextSubStep($substep);
		$inserts = array();

		$request = Db::$db->query('', '
			SELECT id_member, (\'board_notify_\' || id_board) as alert_pref, 1 as alert_value
			FROM {db_prefix}log_notify
			WHERE id_member <> 0 AND id_board <> 0
			LIMIT {int:start}, {int:limit}',
			array(
				'db_error_skip' => true,
				'start' => $_GET['a'],
				'limit' => $limit,
			)
		);
		if (Db::$db->num_rows($request) != 0)
		{
			$inserts = Db::$db->fetch_all($request);
		}
		Db::$db->free_result($request);

		Db::$db->insert('ignore',
			'{db_prefix}user_alerts_prefs',
			array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'),
			$inserts,
			array('id_member', 'alert_pref')
		);

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];

		if ($step_progress['current'] >= $maxBoards)
			$is_done = true;
	}
	unset($_GET['a']);
---}
---#

---# Updating obsolete alerts from before RC3
UPDATE {$db_prefix}user_alerts
SET content_type = 'member', content_id = id_member_started
WHERE content_type = 'buddy';

UPDATE {$db_prefix}user_alerts
SET content_type = 'member'
WHERE content_type = 'profile';

UPDATE {$db_prefix}user_alerts
SET content_id = id_member_started
WHERE content_type = 'member' AND content_action LIKE 'register_%';

UPDATE {$db_prefix}user_alerts
SET content_type = 'topic', content_action = 'unapproved_topic'
WHERE content_type = 'unapproved' AND content_action = 'topic';

UPDATE {$db_prefix}user_alerts
SET content_type = 'topic', content_action = 'unapproved_reply'
WHERE content_type = 'unapproved' AND content_action = 'reply';

UPDATE {$db_prefix}user_alerts
SET content_type = 'topic', content_action = 'unapproved_post'
WHERE content_type = 'unapproved' AND content_action = 'post';

UPDATE {$db_prefix}user_alerts
SET content_type = 'msg', content_action = 'unapproved_attachment', content_id = f.id_msg
FROM {$db_prefix}attachments AS f
WHERE content_type = 'unapproved' AND content_action = 'attachment' AND f.id_attach = content_id;
---#

/******************************************************************************/
--- Adding support for topic unwatch
/******************************************************************************/
---# Adding new column to log_topics...
ALTER TABLE {$db_prefix}log_topics
ADD COLUMN IF NOT EXISTS unwatched int NOT NULL DEFAULT 0;
---#

---# Fixing column name change...
ALTER TABLE {$db_prefix}log_topics
DROP COLUMN IF EXISTS disregarded;
---#

/******************************************************************************/
--- Name changes
/******************************************************************************/
---# Altering the membergroup stars to icons
ALTER TABLE {$db_prefix}membergroups
RENAME stars TO icons;
---#

---# set default membergroup icons
ALTER TABLE {$db_prefix}membergroups
ALTER icons SET DEFAULT '';
---#

---# Renaming default theme...
UPDATE {$db_prefix}themes
SET value = 'SMF Default Theme - Curve2'
WHERE value LIKE 'SMF Default Theme%';
---#

---# Fader time update
UPDATE {$db_prefix}themes
SET value = '3000'
WHERE variable = 'newsfader_time';
---#

---# Adding the enableThemes setting.
INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('enableThemes', '1') ON CONFLICT DO NOTHING;
---#

---# Setting "default" as the default...
UPDATE {$db_prefix}settings
SET value = '1'
WHERE variable = 'theme_guests';

UPDATE {$db_prefix}boards
SET id_theme = 0;

UPDATE {$db_prefix}members
SET id_theme = 0;
---#

/******************************************************************************/
--- Membergroup icons changes
/******************************************************************************/
---# Check the current saved names for icons and change them to the new name.
---{
$request = Db::$db->query('', '
	SELECT icons
	FROM {db_prefix}membergroups
	WHERE icons != {string:blank}',
	array(
		'blank' => '',
	)
);
$toMove = array();
$toChange = array();
while ($row = Db::$db->fetch_assoc($request))
{
	if (strpos($row['icons'], 'star.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('star.gif', 'icon.png', $row['icons']),
		);

	elseif (strpos($row['icons'], 'starmod.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('starmod.gif', 'iconmod.png', $row['icons']),
		);

	elseif (strpos($row['icons'], 'stargmod.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('stargmod.gif', 'icongmod.png', $row['icons']),
		);

	elseif (strpos($row['icons'], 'staradmin.gif') !== false)
		$toChange[] = array(
			'old' => $row['icons'],
			'new' => str_replace('staradmin.gif', 'iconadmin.png', $row['icons']),
		);

	else
		$toMove[] = $row['icons'];
}
Db::$db->free_result($request);

foreach ($toChange as $change)
	Db::$db->query('', '
		UPDATE {db_prefix}membergroups
		SET icons = {string:new}
		WHERE icons = {string:old}',
		array(
			'new' => $change['new'],
			'old' => $change['old'],
		)
	);

// Attempt to move any custom uploaded icons.
foreach ($toMove as $move)
{
	// Get the actual image.
	$image = explode('#', $move);
	$image = $image[1];

	// PHP wont suppress errors when running things from shell, so make sure it exists first...
	if (file_exists(Config::$modSettings['theme_dir'] . '/images/' . $image))
		@rename(Config::$modSettings['theme_dir'] . '/images/' . $image, Config::$modSettings['theme_dir'] . '/images/membericons/'. $image);
}
---}
---#

/******************************************************************************/
--- Cleaning up after old themes...
/******************************************************************************/
---# Clean up settings for unused themes
---{
// Fetch list of theme directories
$request = Db::$db->query('', '
	SELECT id_theme, variable, value
	FROM {db_prefix}themes
	WHERE variable = {string:theme_dir}
		AND id_theme != {int:default_theme};',
	array(
		'default_theme' => 1,
		'theme_dir' => 'theme_dir',
	)
);
// Check which themes exist in the filesystem & save off their IDs
// Dont delete default theme(start with 1 in the array), & make sure to delete old core theme
$known_themes = array('1');
$core_dir = Config::boarddir . '/Themes/core';
while ($row = Db::$db->fetch_assoc($request))	{
	if ($row['value'] != $core_dir && is_dir($row['value'])) {
		$known_themes[] = $row['id_theme'];
	}
}
// Cleanup unused theme settings
Db::$db->query('', '
	DELETE FROM {db_prefix}themes
	WHERE id_theme NOT IN ({array_int:known_themes});',
	array(
		'known_themes' => $known_themes,
	)
);
// Set knownThemes
$known_themes = implode(',', $known_themes);
Db::$db->query('', '
	UPDATE {db_prefix}settings
	SET value = {string:known_themes}
	WHERE variable = {string:known_theme_str};',
	array(
		'known_theme_str' => 'knownThemes',
		'known_themes' => $known_themes,
	)
);
---}
---#

/******************************************************************************/
--- Messenger fields
/******************************************************************************/
---# Adding new field_order column...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN IF NOT EXISTS field_order smallint NOT NULL default '0';
---#

---# Adding new show_mlist column...
ALTER TABLE {$db_prefix}custom_fields
ADD COLUMN IF NOT EXISTS show_mlist smallint NOT NULL default '0';
---#

---# Insert fields
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_icq', '{icq}', '{icq_desc}', 'text', 12, '', 1, 'regex~[1-9][0-9]{4,9}~i', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a class="icq" href="//www.icq.com/people/{INPUT}" target="_blank" rel="noopener" title="ICQ - {INPUT}"><img src="{DEFAULT_IMAGES_URL}/icq.png" alt="ICQ - {INPUT}"></a>', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_skype', '{skype}', '{skype_desc}', 'text', 32, '', 2, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '<a href="skype:{INPUT}?call"><img src="{DEFAULT_IMAGES_URL}/skype.png" alt="{INPUT}" title="{INPUT}" /></a> ', 1) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_loca', '{location}', '{location_desc}', 'text', 50, '', 4, 'nohtml', 0, 1, 0, 'forumprofile', 0, 1, 0, 0, '', '', 0) ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}custom_fields (col_name, field_name, field_desc, field_type, field_length, field_options, field_order, mask, show_reg, show_display, show_mlist, show_profile, private, active, bbc, can_search, default_value, enclose, placement) VALUES
('cust_gender', '{gender}', '{gender_desc}', 'radio', 255, '{gender_0},{gender_1},{gender_2}', 5, 'nohtml', 1, 1, 0, 'forumprofile', 0, 1, 0, 0, '{gender_0}', '<span class=" main_icons gender_{KEY}" title="{INPUT}"></span>', 1) ON CONFLICT DO NOTHING;
---#

---# Add an order value to each existing cust profile field.
---{
	$ocf = Db::$db->query('', '
		SELECT id_field
		FROM {db_prefix}custom_fields
		WHERE field_order = 0');

		// We start counting from 5 because we already have the first 5 fields.
		$fields_count = 5;

		while ($row = Db::$db->fetch_assoc($ocf))
		{
			++$fields_count;

			Db::$db->query('', '
				UPDATE {db_prefix}custom_fields
				SET field_order = {int:field_count}
				WHERE id_field = {int:id_field}',
				array(
					'field_count' => $fields_count,
					'id_field' => $row['id_field'],
				)
			);
		}
		Db::$db->free_result($ocf);
---}
---#

---# Converting member values...
---{
// We cannot do this twice
// See which columns we have
$results = Db::$db->list_columns('{db_prefix}members');
$possible_columns = array('icq', 'msn', 'location', 'gender');

// Find values that are in both arrays
$select_columns = array_intersect($possible_columns, $results);

if (!empty($select_columns))
{
	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Converting member values';
	$step_progress['current'] = $_GET['a'];

	$request = Db::$db->query('', 'SELECT COUNT(*) FROM {db_prefix}members');
	list($maxMembers) = Db::$db->fetch_row($request);

	$limit = 10000;
	$is_done = false;

	while (!$is_done)
	{
		nextSubStep($substep);
		$inserts = array();

		$request = Db::$db->query('', '
			SELECT id_member, '. implode(',', $select_columns) .'
			FROM {db_prefix}members
			ORDER BY id_member
			LIMIT {int:start}, {int:limit}',
			array(
				'start' => $_GET['a'],
				'limit' => $limit,
		));

		while ($row = Db::$db->fetch_assoc($request))
		{
			if (!empty($row['icq']))
				$inserts[] = array($row['id_member'], 1, 'cust_icq', $row['icq']);

			if (!empty($row['msn']))
				$inserts[] = array($row['id_member'], 1, 'cust_skype', $row['msn']);

			if (!empty($row['location']))
				$inserts[] = array($row['id_member'], 1, 'cust_loca', $row['location']);

			if (!empty($row['gender']))
				$inserts[] = array($row['id_member'], 1, 'cust_gender', '{gender_' . intval($row['gender']) . '}');
		}
		Db::$db->free_result($request);

		if (!empty($inserts))
			Db::$db->insert('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string', 'value' => 'string'),
				$inserts,
				array('id_theme', 'id_member', 'variable')
			);

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];

		if ($step_progress['current'] >= $maxMembers)
			$is_done = true;
	}
}
unset($_GET['a']);
---}
---#

---# Dropping old fields
ALTER TABLE {$db_prefix}members
	DROP IF EXISTS icq,
	DROP IF EXISTS aim,
	DROP IF EXISTS yim,
	DROP IF EXISTS msn,
	DROP IF EXISTS location,
	DROP IF EXISTS gender;
---#

---# Create the displayFields setting
---{
	if (empty(Config::$modSettings['displayFields']))
	{
		$request = Db::$db->query('', '
			SELECT col_name, field_name, field_type, field_order, bbc, enclose, placement, show_mlist
			FROM {db_prefix}custom_fields',
			array()
		);

		$fields = array();
		while ($row = Db::$db->fetch_assoc($request))
		{
			$fields[] = array(
				'col_name' => strtr($row['col_name'], array('|' => '', ';' => '')),
				'title' => strtr($row['field_name'], array('|' => '', ';' => '')),
				'type' => $row['field_type'],
				'order' => $row['field_order'],
				'bbc' => $row['bbc'] ? '1' : '0',
				'placement' => !empty($row['placement']) ? $row['placement'] : '0',
				'enclose' => !empty($row['enclose']) ? $row['enclose'] : '',
				'mlist' => $row['show_mlist'],
			);
		}

		Db::$db->free_result($request);

		Db::$db->insert('',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('displayFields', json_encode($fields)),
			array('variable')
		);
	}
---}
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating drafts table.
CREATE SEQUENCE IF NOT EXISTS {$db_prefix}user_drafts_seq;

CREATE TABLE IF NOT EXISTS {$db_prefix}user_drafts (
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
CREATE UNIQUE INDEX IF NOT EXISTS {$db_prefix}user_drafts_id_member ON {$db_prefix}user_drafts (id_member, id_draft, type);
---#

---# Adding draft permissions...
---{
// We cannot do this twice
if (version_compare(trim(strtolower(@Config::$modSettings['smfVersion'])), '2.1.foo', '<'))
{
	// Anyone who can currently post unapproved topics we assume can create drafts as well ...
	$request = upgrade_query("
		SELECT id_group, id_board, add_deny, permission
		FROM {$db_prefix}board_permissions
		WHERE permission = 'post_unapproved_topics'");
	$inserts = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$inserts[] = array($row['id_group'], $row['id_board'], 'post_draft', $row['add_deny']);
	}
	Db::$db->free_result($request);

	if (!empty($inserts))
	{
		Db::$db->insert('replace',
			'{$db_prefix}board_permissions',
			array('id_group' => 'int', 'id_board' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'id_profile', 'permission')
		);
	}

	// Next we find people who can send PMs, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$inserts[] = array($row['id_group'], 'pm_draft', $row['add_deny']);
	}
	Db::$db->free_result($request);

	if (!empty($inserts))
	{
		Db::$db->insert('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'add_deny' => 'int', 'permission' => 'string'),
			$inserts,
			array('id_group', 'permission')
		);
	}
}
---}
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_autosave_enabled', '1') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_show_saved_enabled', '1') ON CONFLICT DO NOTHING;
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_keep_days', '7') ON CONFLICT DO NOTHING;

INSERT INTO {$db_prefix}themes (id_member, id_theme, variable, value) VALUES (-1, '1', 'drafts_show_saved_enabled', '1') ON CONFLICT DO NOTHING;
---#

/******************************************************************************/
--- Adding support for likes
/******************************************************************************/
---# Creating likes table.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_likes (
	id_member int NOT NULL DEFAULT '0',
	content_type char(6) DEFAULT '',
	content_id int NOT NULL DEFAULT '0',
	like_time int NOT NULL DEFAULT '0',
	PRIMARY KEY (content_id, content_type, id_member)
);

DROP INDEX IF EXISTS {$db_prefix}user_likes_content;
DROP INDEX IF EXISTS {$db_prefix}user_likes_liker;

CREATE INDEX {$db_prefix}user_likes_content ON {$db_prefix}user_likes (content_id, content_type);
CREATE INDEX {$db_prefix}user_likes_liker ON {$db_prefix}user_likes (id_member);
---#

---# Adding likes column to the messages table. (May take a while)
ALTER TABLE {$db_prefix}messages
ADD COLUMN IF NOT EXISTS likes smallint NOT NULL default '0';
---#

/******************************************************************************/
--- Adding support for mentions
/******************************************************************************/
---# Creating mentions table
CREATE TABLE IF NOT EXISTS  {$db_prefix}mentions (
	content_id int DEFAULT '0',
	content_type varchar(10) DEFAULT '',
	id_mentioned int DEFAULT 0,
	id_member int NOT NULL DEFAULT 0,
	time int NOT NULL DEFAULT 0,
	PRIMARY KEY (content_id, content_type, id_mentioned)
);

DROP INDEX IF EXISTS {$db_prefix}mentions_content;
DROP INDEX IF EXISTS {$db_prefix}mentions_mentionee;

CREATE INDEX {$db_prefix}mentions_content ON {$db_prefix}mentions (content_id, content_type);
CREATE INDEX {$db_prefix}mentions_mentionee ON {$db_prefix}mentions (id_member);
---#

/******************************************************************************/
--- Adding support for group-based board moderation
/******************************************************************************/
---# Creating moderator_groups table
CREATE TABLE IF NOT EXISTS {$db_prefix}moderator_groups (
	id_board smallint NOT NULL DEFAULT '0',
	id_group smallint NOT NULL DEFAULT '0',
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

/******************************************************************************/
--- Cleaning up old settings
/******************************************************************************/
---# Fixing a deprecated option.
UPDATE {$db_prefix}settings
SET value = 'option_css_resize'
WHERE variable = 'avatar_action_too_large'
	AND (value = 'option_html_resize' OR value = 'option_js_resize');
---#

---# Cleaning up the old Core Features page.
---{
	// First get the original value
	$request = Db::$db->query('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {literal:admin_features}'
	);
	if (Db::$db->num_rows($request) > 0 && $row = Db::$db->fetch_assoc($request))
	{
		// Some of these *should* already be set but you never know.
		$new_settings = array();
		$admin_features = explode(',', $row['value']);

		// cd = calendar, should also have set cal_enabled already
		// cp = custom profile fields, which already has several fields that cover tracking
		// k = karma, should also have set karmaMode already
		// ps = paid subs, should also have set paid_enabled already
		// rg = reports generation, which is now permanently on
		// sp = spider tracking, should also have set spider_mode already
		// w = warning system, which will be covered with warning_settings

		// The rest we have to deal with manually.
		// Moderation log - modlog_enabled itself should be set but we have others now
		if (in_array('ml', $admin_features))
		{
			$new_settings[] = array('adminlog_enabled', '1');
			$new_settings[] = array('userlog_enabled', '1');
		}

		// Post moderation
		if (in_array('pm', $admin_features))
		{
			$new_settings[] = array('postmod_active', '1');
		}

		// And now actually apply it.
		if (!empty($new_settings))
		{
			Db::$db->insert('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				$new_settings,
				array('variable')
			);
		}
	}
	Db::$db->free_result($request);
---}
---#

---# Cleaning up old settings.
DELETE FROM {$db_prefix}settings
WHERE variable IN ('enableStickyTopics', 'guest_hideContacts', 'notify_new_registration', 'attachmentEncryptFilenames', 'hotTopicPosts', 'hotTopicVeryPosts', 'fixLongWords', 'admin_features', 'log_ban_hits', 'topbottomEnable', 'simpleSearch', 'enableVBStyleLogin', 'admin_bbc', 'enable_unwatch', 'cache_memcached', 'cache_enable', 'cookie_no_auth_secret');
---#

---# Cleaning up old theme settings.
DELETE FROM {$db_prefix}themes
WHERE variable IN ('show_board_desc', 'display_quick_reply', 'show_mark_read', 'show_member_bar', 'linktree_link', 'show_bbc', 'additional_options_collapsable', 'subject_toggle', 'show_modify', 'show_profile_buttons', 'show_user_images', 'show_blurb', 'show_gender', 'hide_post_group', 'drafts_autosave_enabled', 'forum_width');
---#

---# Update the SM Stat collection.
---{
	// First get the original value
	$request = Db::$db->query('', '
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {literal:allow_sm_stats}'
	);
	if (Db::$db->num_rows($request) > 0 && $row = Db::$db->fetch_assoc($request))
	{
		if (!empty($row['value']))
		{
			Db::$db->insert('replace',
				'{db_prefix}settings',
				array('variable' => 'string', 'value' => 'string'),
				array(
					array('sm_stats_key', $row['value']),
					array('enable_sm_stats', '1'),
				),
				array('variable')
			);

			Db::$db->query('', '
				DELETE FROM {db_prefix}settings
				WHERE variable = {literal:allow_sm_stats}');
		}
	}
	Db::$db->free_result($request);
---}
---#

---# Adding new "httponlyCookies" setting
---{
	if (!isset(Config::$modSettings['httponlyCookies']))
		Db::$db->insert('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('httponlyCookies', '1'),
			array()
		);
---}
---#

---# Adding new "samesiteCookies" setting
---{
	if (!isset(Config::$modSettings['samesiteCookies']))
		Db::$db->insert('insert',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('samesiteCookies', 'lax'),
			array()
		);
---}
---#

---# Calculate appropriate hash cost
---{
	Db::$db->insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string', 'value' => 'string'),
		array('bcrypt_hash_cost', Security::hashBenchmark()),
		array('variable')
	);
---}

/******************************************************************************/
--- Updating files that fetched from simplemachines.org
/******************************************************************************/
---# We no longer call on several files.
DELETE FROM {$db_prefix}admin_info_files
WHERE filename IN ('latest-packages.js', 'latest-smileys.js', 'latest-support.js', 'latest-themes.js')
	AND path = '/smf/';
---#

---# But we do need new files.
---{
// Don't insert the info if it's already there...
$file_check = Db::$db->query('', '
	SELECT id_file
	FROM {db_prefix}admin_info_files
	WHERE filename = {string:latest-versions}',
	array(
		'latest-versions' => 'latest-versions.txt',
	)
);

if (Db::$db->num_rows($file_check) == 0)
{
	Db::$db->insert('',
		'{db_prefix}admin_info_files',
		array('filename' => 'string', 'path' => 'string', 'parameters' => 'string', 'data' => 'string', 'filetype' => 'string'),
		array('latest-versions.txt', '/smf/', 'version=%3$s', '', 'text/plain'),
		array('id_file')
	);
}

Db::$db->free_result($file_check);
---}
---#

/******************************************************************************/
--- Upgrading "verification questions" feature
/******************************************************************************/
---# Creating qanda table
CREATE SEQUENCE IF NOT EXISTS {$db_prefix}qanda_seq;

CREATE TABLE IF NOT EXISTS {$db_prefix}qanda (
	id_question smallint DEFAULT nextval('{$db_prefix}qanda_seq'),
	lngfile varchar(255) NOT NULL DEFAULT '',
	question varchar(255) NOT NULL DEFAULT '',
	answers text NOT NULL,
	PRIMARY KEY (id_question)
);
---#

---# Create index on qanda
DROP INDEX IF EXISTS {$db_prefix}qanda_lngfile;
CREATE INDEX {$db_prefix}qanda_lngfile ON {$db_prefix}qanda (lngfile varchar_pattern_ops);
---#

---# Moving questions and answers to the new table
---{
	$questions = array();

	$get_questions = upgrade_query("
		SELECT body AS question, recipient_name AS answer
		FROM {$db_prefix}log_comments
		WHERE comment_type = 'ver_test'");

	while ($row = Db::$db->fetch_assoc($get_questions))
		$questions[] = array($upcontext['language'], $row['question'], serialize(array($row['answer'])));

	Db::$db->free_result($get_questions);

	if (!empty($questions))
	{
		Db::$db->insert('',
			'{db_prefix}qanda',
			array('lngfile' => 'string', 'question' => 'string', 'answers' => 'string'),
			$questions,
			array('id_question')
		);

		// Delete the questions from log_comments now
		upgrade_query("
			DELETE FROM {$db_prefix}log_comments
			WHERE comment_type = 'ver_test'
		");
	}
---}
---#

/******************************************************************************/
--- Marking packages as uninstalled...
/******************************************************************************/
---# Updating log_packages
UPDATE {$db_prefix}log_packages
SET install_state = 0;
---#

/******************************************************************************/
--- Updating profile permissions...
/******************************************************************************/
---# Removing the old "view your own profile" permission
DELETE FROM {$db_prefix}permissions
WHERE permission = 'profile_view_own';
---#

---# Updating the old "view any profile" permission
UPDATE {$db_prefix}permissions
SET permission = 'profile_view'
WHERE permission = 'profile_view_any';
---#

---# Removing the old notification permissions
DELETE FROM {$db_prefix}board_permissions
WHERE permission = 'mark_notify' OR permission = 'mark_any_notify';
---#

---# Removing the send-topic permission
DELETE FROM {$db_prefix}board_permissions
WHERE permission = 'send_topic';
---#

---# Removing the draft "autosave" permissions
DELETE FROM {$db_prefix}permissions
WHERE permission = 'post_autosave_draft' OR permission = 'pm_autosave_draft';

DELETE FROM {$db_prefix}board_permissions
WHERE permission = 'post_autosave_draft';
---#

---# Adding "profile_password_own"
---{
$inserts = array();

$request = upgrade_query("
	SELECT id_group, add_deny
	FROM {$db_prefix}permissions
	WHERE permission = 'profile_identity_own'");

	while ($row = Db::$db->fetch_assoc($request))
	{
		$inserts[] = array($row['id_group'], 'profile_password_own', $row['add_deny']);
	}

	Db::$db->free_result($request);

	if (!empty($inserts))
	{
		Db::$db->insert('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'permission')
		);
	}
---}
---#

---# Adding "view_warning_own" and "view_warning_any" permissions
---{
if (isset(Config::$modSettings['warning_show']))
{
	$can_view_warning_own = array();
	$can_view_warning_any = array();

	if (Config::$modSettings['warning_show'] >= 1)
	{
		$can_view_warning_own[] = 0;

		$request = Db::$db->query('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE min_posts = {int:not_post_based}',
			array(
				'not_post_based' => -1,
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (in_array($row['id_group'], array(1, 3)))
				continue;

			$can_view_warning_own[] = $row['id_group'];
		}
		Db::$db->free_result($request);
	}

	if (Config::$modSettings['warning_show'] > 1)
		$can_view_warning_any = $can_view_warning_own;
	else
	{
		$request = Db::$db->query('', '
			SELECT id_group, add_deny
			FROM {db_prefix}permissions
			WHERE permission = {string:perm}',
			array(
				'perm' => 'issue_warning',
			)
		);
		while ($row = Db::$db->fetch_assoc($request))
		{
			if (in_array($row['id_group'], array(-1, 1, 3)) || $row['add_deny'] != 1)
				continue;

			$can_view_warning_any[] = $row['id_group'];
		}
		Db::$db->free_result($request);
	}

	$inserts = array();

	foreach ($can_view_warning_own as $id_group)
		$inserts[] = array($id_group, 'view_warning_own', 1);

	foreach ($can_view_warning_any as $id_group)
		$inserts[] = array($id_group, 'view_warning_any', 1);

	if (!empty($inserts))
	{
		Db::$db->insert('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'permission')
		);
	}

	Db::$db->query('', '
		DELETE FROM {db_prefix}settings
		WHERE variable = {string:warning_show}',
		array(
			'warning_show' => 'warning_show',
		)
	);
}
---}
---#

---# Adding other profile permissions
---{
$inserts = array();

$request = upgrade_query("
	SELECT id_group, add_deny
	FROM {$db_prefix}permissions
	WHERE permission = 'profile_extra_own'");

	while ($row = Db::$db->fetch_assoc($request))
	{
		$inserts[] = array($row['id_group'], 'profile_blurb_own', $row['add_deny']);
		$inserts[] = array($row['id_group'], 'profile_displayed_name_own', $row['add_deny']);
		$inserts[] = array($row['id_group'], 'profile_forum_own', $row['add_deny']);
		$inserts[] = array($row['id_group'], 'profile_website_own', $row['add_deny']);
		$inserts[] = array($row['id_group'], 'profile_signature_own', $row['add_deny']);
	}

	Db::$db->free_result($request);

	if (!empty($inserts))
	{
		Db::$db->insert('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$inserts,
			array('id_group', 'permission')
		);
	}
---}
---#

/******************************************************************************/
--- Upgrading PM labels...
/******************************************************************************/
---# Creating pm_labels sequence...
CREATE SEQUENCE IF NOT EXISTS {$db_prefix}pm_labels_seq;
---#

---# Adding pm_labels table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labels (
	id_label bigint NOT NULL DEFAULT nextval('{$db_prefix}pm_labels_seq'),
	id_member int NOT NULL DEFAULT '0',
	name varchar(30) NOT NULL DEFAULT '',
	PRIMARY KEY (id_label)
);
---#

---# Adding pm_labeled_messages table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labeled_messages (
	id_label bigint NOT NULL DEFAULT '0',
	id_pm bigint NOT NULL DEFAULT '0',
	PRIMARY KEY (id_label, id_pm)
);
---#

---# Adding "in_inbox" column to pm_recipients
ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN IF NOT EXISTS in_inbox smallint NOT NULL default '1';
---#

---# Moving label info to new tables and updating rules (May be slow!!!)
---{
	// First see if we still have a message_labels column
	$results = Db::$db->list_columns('{db_prefix}members');
	if (in_array('message_labels', $results))
	{
		$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
		$step_progress['name'] = 'Moving pm labels';
		$step_progress['current'] = $_GET['a'];

		$request = Db::$db->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE message_labels != {string:blank}',
			array(
				'blank' => '',
			)
		);
		list($maxMembers) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		if ($maxMembers > 0)
		{
			$limit = 5000;
			$is_done = false;

			while (!$is_done)
			{
				nextSubStep($substep);
				$inserts = array();

				// Pull the label info
				$get_labels = Db::$db->query('', '
					SELECT id_member, message_labels
					FROM {db_prefix}members
					WHERE message_labels != {string:blank}
					ORDER BY id_member
					LIMIT {int:start}, {int:limit}',
					array(
						'blank' => '',
						'start' => $_GET['a'],
						'limit' => $limit,
					)
				);

				$label_info = array();
				$member_list = array();
				while ($row = Db::$db->fetch_assoc($get_labels))
				{
					$member_list[] = $row['id_member'];

					// Stick this in an array
					$labels = explode(',', $row['message_labels']);

					// Build some inserts
					foreach ($labels AS $index => $label)
					{
						// Keep track of the index of this label - we'll need that in a bit...
						$label_info[$row['id_member']][$label] = $index;
					}
				}

				Db::$db->free_result($get_labels);

				foreach ($label_info AS $id_member => $labels)
				{
					foreach ($labels as $label => $index)
					{
						$inserts[] = array($id_member, $label);
					}
				}

				if (!empty($inserts))
				{
					Db::$db->insert('', '{db_prefix}pm_labels', array('id_member' => 'int', 'name' => 'string-30'), $inserts, array());

					// Clear this out for our next query below
					$inserts = array();
				}

				// This is the easy part - update the inbox stuff
				Db::$db->query('', '
					UPDATE {db_prefix}pm_recipients
					SET in_inbox = {int:in_inbox}
					WHERE FIND_IN_SET({int:minusone}, labels) > 0
						AND id_member IN ({array_int:member_list})',
					array(
						'in_inbox' => 1,
						'minusone' => -1,
						'member_list' => $member_list,
					)
				);

				// Now we go pull the new IDs for each label
				$get_new_label_ids = Db::$db->query('', '
					SELECT *
					FROM {db_prefix}pm_labels
					WHERE id_member IN ({array_int:member_list})',
					array(
						'member_list' => $member_list,
					)
				);

				$label_info_2 = array();
				while ($label_row = Db::$db->fetch_assoc($get_new_label_ids))
				{
					// Map the old index values to the new ID values...
					$old_index = $label_info[$label_row['id_member']][$label_row['name']];
					$label_info_2[$label_row['id_member']][$old_index] = $label_row['id_label'];
				}

				Db::$db->free_result($get_new_label_ids);

				// Pull label info from pm_recipients
				// Ignore any that are only in the inbox
				$get_pm_labels = Db::$db->query('', '
					SELECT id_pm, id_member, labels
					FROM {db_prefix}pm_recipients
					WHERE deleted = {int:not_deleted}
						AND labels != {string:minus_one}
						AND id_member IN ({array_int:member_list})',
					array(
						'not_deleted' => 0,
						'minus_one' => -1,
						'member_list' => $member_list,
					)
				);

				while ($row = Db::$db->fetch_assoc($get_pm_labels))
				{
					$labels = explode(',', $row['labels']);

					foreach ($labels as $a_label)
					{
						if ($a_label == '-1')
							continue;

						$new_label_info = $label_info_2[$row['id_member']][$a_label];
						$inserts[] = array($row['id_pm'], $new_label_info);
					}
				}

				Db::$db->free_result($get_pm_labels);

				// Insert the new data
				if (!empty($inserts))
				{
					Db::$db->insert('', '{db_prefix}pm_labeled_messages', array('id_pm' => 'int', 'id_label' => 'int'), $inserts, array());
				}

				// Final step of this ridiculously massive process
				$get_pm_rules = Db::$db->query('', '
					SELECT id_member, id_rule, actions
					FROM {db_prefix}pm_rules
					WHERE id_member IN ({array_int:member_list})',
					array(
						'member_list' => $member_list,
					)
				);

				// Go through the rules, unserialize the actions, then figure out if there's anything we can use
				while ($row = Db::$db->fetch_assoc($get_pm_rules))
				{
					$updated = false;

					// Turn this into an array...
					$actions = unserialize($row['actions']);

					// Loop through the actions and see if we're applying a label anywhere
					foreach ($actions as $index => $action)
					{
						if ($action['t'] == 'lab')
						{
							// Update the value of this label...
							$actions[$index]['v'] = $label_info_2[$row['id_member']][$action['v']];
							$updated = true;
						}
					}

					if ($updated)
					{
						// Put this back into a string
						$actions = serialize($actions);

						Db::$db->query('', '
							UPDATE {db_prefix}pm_rules
							SET actions = {string:actions}
							WHERE id_rule = {int:id_rule}',
							array(
								'actions' => $actions,
								'id_rule' => $row['id_rule'],
							)
						);
					}
				}

				// Remove processed pm labels, to avoid duplicated data if upgrader is restarted.
				Db::$db->query('', '
					UPDATE {db_prefix}members
					SET message_labels = {string:blank}
					WHERE id_member IN ({array_int:member_list})',
					array(
						'blank' => '',
						'member_list' => $member_list,
					)
				);

				Db::$db->free_result($get_pm_rules);

				$_GET['a'] += $limit;
				$step_progress['current'] = $_GET['a'];

				if ($step_progress['current'] >= $maxMembers)
					$is_done = true;
			}

			// Lastly, we drop the old columns
			Db::$db->remove_column('{db_prefix}members', 'message_labels');
			Db::$db->remove_column('{db_prefix}pm_recipients', 'labels');
		}
	}
	unset($_GET['a']);
---}
---#

/******************************************************************************/
--- Adding support for edit reasons
/******************************************************************************/
---# Adding "modified_reason" column to messages
ALTER TABLE {$db_prefix}messages
ADD COLUMN IF NOT EXISTS modified_reason varchar(255) NOT NULL default '';
---#

/******************************************************************************/
--- Cleaning up guest permissions
/******************************************************************************/
---# Removing permissions guests can no longer have...
---{
	$illegal_board_permissions = array(
		'announce_topic',
		'delete_any',
		'lock_any',
		'make_sticky',
		'merge_any',
		'modify_any',
		'modify_replies',
		'move_any',
		'poll_add_any',
		'poll_edit_any',
		'poll_lock_any',
		'poll_remove_any',
		'remove_any',
		'report_any',
		'split_any'
	);

	$illegal_permissions = array('calendar_edit_any', 'moderate_board', 'moderate_forum', 'send_email_to_members');

	Db::$db->query('', '
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group = {int:guests}
			AND permission IN ({array_string:illegal_board_perms})',
		array(
			'guests' => -1,
			'illegal_board_perms' => $illegal_board_permissions,
		)
	);

	Db::$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE id_group = {int:guests}
			AND permission IN ({array_string:illegal_perms})',
		array(
			'guests' => -1,
			'illegal_perms' => $illegal_permissions,
		)
	);
---}
---#

/******************************************************************************/
--- Adding gravatar settings
/******************************************************************************/
---# Adding default gravatar settings
---{
	if (empty(Config::$modSettings['gravatarEnabled']))
	{
		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string-255', 'value' => 'string'),
			array(
				array('gravatarEnabled', '1'),
				array('gravatarOverride', '0'),
				array('gravatarAllowExtraEmail', '1'),
				array('gravatarMaxRating', 'PG'),
			),
			array('variable')
		);
	}
---}
---#

/******************************************************************************/
--- Adding timezone support
/******************************************************************************/
---# Adding the "timezone" column to the members table
ALTER TABLE {$db_prefix}members ADD IF NOT EXISTS timezone VARCHAR(80) NOT NULL DEFAULT '';
---#

---# Converting time offset to timezone
---{
	if (!empty(Config::$modSettings['time_offset']))
	{
		Config::$modSettings['default_timezone'] = empty(Config::$modSettings['default_timezone']) || !in_array(Config::$modSettings['default_timezone'], timezone_identifiers_list(DateTimeZone::ALL_WITH_BC)) ? 'UTC' : Config::$modSettings['default_timezone'];

		$now = date_create('now', timezone_open(Config::$modSettings['default_timezone']));

		if (($new_tzid = timezone_name_from_abbr('', date_offset_get($now) + Config::$modSettings['time_offset'] * 3600, date_format($now, 'I'))) !== false)
		{
			Db::$db->insert('replace',
				'{db_prefix}settings',
				array('variable' => 'string-255', 'value' => 'string'),
				array(
					array('default_timezone', $new_tzid),
				),
				array('variable')
			);

			Config::$modSettings['default_timezone'] = $new_tzid;
		}

		Db::$db->query('', '
			DELETE FROM {db_prefix}settings
			WHERE variable = {literal:time_offset}',
			array()
		);
	}
---}
---#

/******************************************************************************/
--- Adding mail queue settings
/******************************************************************************/
---# Adding default settings for the mail queue
---{
	if (empty(Config::$modSettings['mail_limit']))
	{
		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string-255', 'value' => 'string'),
			array(
				array('mail_limit', '5'),
				array('mail_quantity', '5'),
			),
			array('variable')
		);
	}
---}
---#

/******************************************************************************/
--- Cleaning up old email settings
/******************************************************************************/
---# Removing the "send_email_to_members" permission
---{
	Db::$db->query('', '
		DELETE FROM {db_prefix}permissions
		WHERE permission = {literal:send_email_to_members}',
		array()
	);
---}
---#

---# Dropping the "hide_email" column from the members table
ALTER TABLE {$db_prefix}members
DROP IF EXISTS hide_email;
---#

---# Dropping the "email_address" column from log_reported_comments
ALTER TABLE {$db_prefix}log_reported_comments
DROP IF EXISTS email_address;
---#

/******************************************************************************/
--- Deleting the "Auto Optimize" task
/******************************************************************************/
---# Removing the task and associated data
DELETE FROM {$db_prefix}scheduled_tasks
WHERE id_task = '2';

DELETE FROM {$db_prefix}log_scheduled_tasks
WHERE id_task = '2';

DELETE FROM {$db_prefix}settings
WHERE variable = 'autoOptMaxOnline';
---#

/******************************************************************************/
--- Removing OpenID-related things...
/******************************************************************************/
---# Removing the openid_uri column in the members table
ALTER TABLE {$db_prefix}members
DROP IF EXISTS openid_uri;
---#

---# Dropping the openid_assoc table
DROP TABLE IF EXISTS {$db_prefix}openid_assoc;
---#

---# Removing related settings
DELETE FROM {$db_prefix}settings
WHERE variable='enableOpenID' OR variable='dh_keys';
---#

/******************************************************************************/
--- Fixing the url column in the log_spider_hits and log_online tables
/******************************************************************************/
---# Changing url column size in log_spider_hits from 255 to 1024
ALTER TABLE {$db_prefix}log_spider_hits
ALTER url TYPE varchar(1024);

ALTER TABLE {$db_prefix}log_spider_hits
ALTER url SET DEFAULT '';
---#

---# Changing url column in log_online from text to varchar(1024)
ALTER TABLE {$db_prefix}log_online
ALTER url TYPE varchar(2048);
---#

/******************************************************************************/
--- Adding support for 2FA
/******************************************************************************/
---# Adding the secret column to members table
ALTER TABLE {$db_prefix}members
ADD COLUMN IF NOT EXISTS tfa_secret VARCHAR(24) NOT NULL DEFAULT '';
---#

---# Adding the backup column to members tab
ALTER TABLE {$db_prefix}members
ADD COLUMN IF NOT EXISTS tfa_backup VARCHAR(64) NOT NULL DEFAULT '';
---#

---# Force 2FA per membergroup
ALTER TABLE {$db_prefix}membergroups
ADD COLUMN IF NOT EXISTS tfa_required smallint NOT NULL default '0';
---#

---# Add tfa_mode setting
---{
	if (!isset(Config::$modSettings['tfa_mode']))
		Db::$db->insert('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string'),
			array('tfa_mode', '1'),
			array('variable')
		);
---}
---#

/******************************************************************************/
--- optimization of members
/******************************************************************************/
---# DROP INDEX to members
DROP INDEX IF EXISTS {$db_prefix}members_member_name_low;
DROP INDEX IF EXISTS {$db_prefix}members_real_name_low;
DROP INDEX IF EXISTS {$db_prefix}members_active_real_name;
---#

---# ADD INDEX to members
CREATE INDEX {$db_prefix}members_member_name_low ON {$db_prefix}members (LOWER(member_name) varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_real_name_low ON {$db_prefix}members (LOWER(real_name) varchar_pattern_ops);
CREATE INDEX {$db_prefix}members_active_real_name ON {$db_prefix}members (is_activated, real_name);
---#

/******************************************************************************/
--- UNLOGGED Table PG 9.1+
/******************************************************************************/
---# update table
---{
$result = Db::$db->query('', '
	SHOW server_version_num'
);
if ($result !== false)
{
	while ($row = Db::$db->fetch_assoc($result))
		$pg_version = $row['server_version_num'];
	Db::$db->free_result($result);
}

if(isset($pg_version))
{
	$tables = array('log_online','log_floodcontrol','sessions');
	foreach($tables as $tab)
	{
		if($pg_version >= 90500)
			upgrade_query("ALTER TABLE {$db_prefix}".$tab." SET UNLOGGED;");
		ELSE
			upgrade_query("
			alter table {$db_prefix}".$tab." rename to old_{$db_prefix}".$tab.";

			do
			$$
			declare r record;
			begin
				for r in select * from pg_constraint where conrelid='old_{$db_prefix}".$tab."'::regclass loop
					execute format('alter table old_{$db_prefix}".$tab." rename constraint %I to %I', r.conname, 'old_' || r.conname);
				end loop;
				for r in select * from pg_indexes where tablename='old_{$db_prefix}".$tab."' and indexname !~ '^old_' loop
					execute format('alter index %I rename to %I', r.indexname, 'old_' || r.indexname);
				end loop;
			end;
			$$;

			create unlogged table {$db_prefix}".$tab." (like old_{$db_prefix}".$tab." including all);

			insert into {$db_prefix}".$tab." select * from old_{$db_prefix}".$tab.";

			drop table old_{$db_prefix}".$tab.";"
			);
	}

}
---}
---#

/******************************************************************************/
--- remove redundant index
/******************************************************************************/
---# duplicate to messages_current_topic
DROP INDEX IF EXISTS {$db_prefix}messages_id_topic;
DROP INDEX IF EXISTS {$db_prefix}messages_topic;
---#

---# duplicate to topics_last_message_sticky and topics_board_news
DROP INDEX IF EXISTS {$db_prefix}topics_id_board;
---#

/******************************************************************************/
--- update ban ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$table_columns = Db::$db->list_columns('{db_prefix}ban_items');
$upcontext['skip_db_substeps'] = in_array('ip_low', $table_columns);
---}
---#

---# add columns
ALTER TABLE {$db_prefix}ban_items ADD COLUMN IF NOT EXISTS ip_low inet;
ALTER TABLE {$db_prefix}ban_items ADD COLUMN IF NOT EXISTS ip_high inet;
---#

---# convert data
UPDATE {$db_prefix}ban_items
SET ip_low = (ip_low1||'.'||ip_low2||'.'||ip_low3||'.'||ip_low4)::inet,
	ip_high = (ip_high1||'.'||ip_high2||'.'||ip_high3||'.'||ip_high4)::inet
WHERE ip_low1 > 0;
---#

---# index
DROP INDEX IF EXISTS {$db_prefix}ban_items_id_ban_ip;
CREATE INDEX {$db_prefix}ban_items_id_ban_ip ON {$db_prefix}ban_items (ip_low,ip_high);
---#

---# Dropping columns from ban_items
ALTER TABLE {$db_prefix}ban_items
DROP ip_low1,
DROP ip_low2,
DROP ip_low3,
DROP ip_low4,
DROP ip_high1,
DROP ip_high2,
DROP ip_high3,
DROP ip_high4;
---#

/******************************************************************************/
--- helper function for ip convert
/******************************************************************************/
---# the function migrate_inet
---{
upgrade_query("
	CREATE OR REPLACE FUNCTION migrate_inet(val IN anyelement) RETURNS inet
	AS
	$$
	BEGIN
	   RETURN (trim(val))::inet;
	EXCEPTION
	   WHEN OTHERS THEN RETURN NULL;
	END;
	$$ LANGUAGE plpgsql;"
);
---}
---#

/******************************************************************************/
--- update log_action ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}log_actions', 'ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# convert column
ALTER TABLE {$db_prefix}log_actions
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

/******************************************************************************/
--- update log_banned ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}log_banned', 'ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# convert old column
ALTER TABLE {$db_prefix}log_banned
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

/******************************************************************************/
--- update log_errors members ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}log_errors', 'ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# convert old columns
ALTER TABLE {$db_prefix}log_errors
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

/******************************************************************************/
--- update log_errors members ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}members', 'member_ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---#
ALTER TABLE {$db_prefix}members
	ALTER member_ip DROP not null,
	ALTER member_ip DROP default,
	ALTER member_ip TYPE inet USING migrate_inet(member_ip);
ALTER TABLE {$db_prefix}members
	ALTER member_ip2 DROP not null,
	ALTER member_ip2 DROP default,
	ALTER member_ip2 TYPE inet USING migrate_inet(member_ip2);
---#

/******************************************************************************/
--- update messages poster_ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}messages', 'poster_ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# convert old column
ALTER TABLE {$db_prefix}messages
	ALTER poster_ip DROP not null,
	ALTER poster_ip DROP default,
	ALTER poster_ip TYPE inet USING migrate_inet(poster_ip);
---#

/******************************************************************************/
--- update log_floodcontrol ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}log_floodcontrol', 'ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# drop pk
TRUNCATE TABLE {$db_prefix}log_floodcontrol;
ALTER TABLE {$db_prefix}log_floodcontrol DROP CONSTRAINT {$db_prefix}log_floodcontrol_pkey;
---#

---# convert old column
ALTER TABLE {$db_prefix}log_floodcontrol
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

---# Modify log_type size
ALTER TABLE {$db_prefix}log_floodcontrol ALTER COLUMN log_type TYPE varchar(30);
---#

---# add pk
ALTER TABLE {$db_prefix}log_floodcontrol
  ADD CONSTRAINT {$db_prefix}log_floodcontrol_pkey PRIMARY KEY(ip, log_type);
---#

/******************************************************************************/
--- update log_online ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}log_online', 'ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# convert old columns
ALTER TABLE {$db_prefix}log_online
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
---#

/******************************************************************************/
--- update log_reported_comments member_ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}log_reported_comments', 'member_ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# convert old columns
ALTER TABLE {$db_prefix}log_reported_comments
	ALTER member_ip DROP not null,
	ALTER member_ip DROP default,
	ALTER member_ip TYPE inet USING migrate_inet(member_ip);
---#

/******************************************************************************/
--- update member_logins ip with ipv6 support
/******************************************************************************/
---# upgrade check
---{
$column_info = upgradeGetColumnInfo('{db_prefix}member_logins', 'ip');
if (stripos($column_info['type'], 'inet') !== false)
	$upcontext['skip_db_substeps'] = true;
---}
---#

---# convert old columns
ALTER TABLE {$db_prefix}member_logins
	ALTER ip DROP not null,
	ALTER ip DROP default,
	ALTER ip TYPE inet USING migrate_inet(ip);
ALTER TABLE {$db_prefix}member_logins
	ALTER ip2 DROP not null,
	ALTER ip2 DROP default,
	ALTER ip2 TYPE inet USING migrate_inet(ip2);
---#

/******************************************************************************/
--- Renaming the "profile_other" permission...
/******************************************************************************/
---# Changing the "profile_other" permission to "profile_website"
UPDATE {$db_prefix}permissions SET permission = 'profile_website_own' WHERE permission = 'profile_other_own';
UPDATE {$db_prefix}permissions SET permission = 'profile_website_any' WHERE permission = 'profile_other_any';
---#

/******************************************************************************/
--- Adding support for start and end times on calendar events
/******************************************************************************/
---# Add start_time end_time, and timezone columns to calendar table
ALTER TABLE {$db_prefix}calendar
ADD COLUMN IF NOT EXISTS start_time time,
ADD COLUMN IF NOT EXISTS end_time time,
ADD COLUMN IF NOT EXISTS timezone VARCHAR(80);
---#

---# Update cal_maxspan and drop obsolete cal_allowspan setting
---{
	if (!isset(Config::$modSettings['cal_allowspan']))
		$cal_maxspan = 0;
	elseif (Config::$modSettings['cal_allowspan'] == false)
		$cal_maxspan = 1;
	else
		$cal_maxspan = (Config::$modSettings['cal_maxspan'] > 1) ? Config::$modSettings['cal_maxspan'] : 0;

	upgrade_query("
		UPDATE {$db_prefix}settings
		SET value = '$cal_maxspan'
		WHERE variable = 'cal_maxspan'");

	if (isset(Config::$modSettings['cal_allowspan']))
		upgrade_query("
			DELETE FROM {$db_prefix}settings
			WHERE variable = 'cal_allowspan'");
---}
---#

/******************************************************************************/
--- Adding location support for calendar events
/******************************************************************************/
---# Add location column to calendar table
ALTER TABLE {$db_prefix}calendar
ADD COLUMN IF NOT EXISTS location VARCHAR(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Updating various calendar settings
/******************************************************************************/
---# Update the max year for the calendar
UPDATE {$db_prefix}settings
SET value = '2030'
WHERE variable = 'cal_maxyear';
---#

---# Adding various calendar settings
INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('cal_disable_prev_next', '0'),
	('cal_week_links', '2'),
	('cal_prev_next_links', '1'),
	('cal_short_days', '0'),
	('cal_short_months', '0'),
	('cal_week_numbers', '0') ON CONFLICT DO NOTHING;
---#

/******************************************************************************/
--- Update index for like search
/******************************************************************************/
---# Change index for table log_packages
DROP INDEX IF EXISTS {$db_prefix}log_packages_filename;
CREATE INDEX {$db_prefix}log_packages_filename ON {$db_prefix}log_packages (filename varchar_pattern_ops);
---#

---# Change index for table members
DROP INDEX IF EXISTS {$db_prefix}members_email_address;
CREATE INDEX {$db_prefix}members_email_address ON {$db_prefix}members (email_address varchar_pattern_ops);
DROP INDEX IF EXISTS {$db_prefix}members_lngfile;
CREATE INDEX {$db_prefix}members_lngfile ON {$db_prefix}members (lngfile varchar_pattern_ops);
DROP INDEX IF EXISTS {$db_prefix}members_member_name;
CREATE INDEX {$db_prefix}members_member_name ON {$db_prefix}members (member_name varchar_pattern_ops);
DROP INDEX IF EXISTS {$db_prefix}members_real_name;
CREATE INDEX {$db_prefix}members_real_name ON {$db_prefix}members (real_name varchar_pattern_ops);
---#

---# Change index for table scheduled_tasks
DROP INDEX IF EXISTS {$db_prefix}scheduled_tasks_task;
CREATE UNIQUE INDEX {$db_prefix}scheduled_tasks_task ON {$db_prefix}scheduled_tasks (task varchar_pattern_ops);
---#

---# Change index for table admin_info_files
DROP INDEX IF EXISTS {$db_prefix}admin_info_files_filename;
CREATE INDEX {$db_prefix}admin_info_files_filename ON {$db_prefix}admin_info_files (filename varchar_pattern_ops);
---#

---# Change index for table boards
DROP INDEX IF EXISTS {$db_prefix}boards_member_groups;
CREATE INDEX {$db_prefix}boards_member_groups ON {$db_prefix}boards (member_groups varchar_pattern_ops);
---#

---# Change index for table log_comments
DROP INDEX IF EXISTS {$db_prefix}log_comments_comment_type;
CREATE INDEX {$db_prefix}log_comments_comment_type ON {$db_prefix}log_comments (comment_type varchar_pattern_ops);
---#

/******************************************************************************/
--- Migrating pm notification settings
/******************************************************************************/
---# Upgrading pm notification settings
---{
// First see if we still have a pm_email_notify column
$results = Db::$db->list_columns('{db_prefix}members');
if (in_array('pm_email_notify', $results))
{
	$_GET['a'] = isset($_GET['a']) ? (int) $_GET['a'] : 0;
	$step_progress['name'] = 'Upgrading pm notification settings';
	$step_progress['current'] = $_GET['a'];

	$limit = 10000;
	$is_done = false;

	$request = Db::$db->query('', 'SELECT COUNT(*) FROM {db_prefix}members');
	list($maxMembers) = Db::$db->fetch_row($request);
	Db::$db->free_result($request);

	while (!$is_done)
	{
		nextSubStep($substep);
		$inserts = array();

		// Skip errors here so we don't croak if the columns don't exist...
		$request = Db::$db->query('', '
			SELECT id_member, pm_email_notify
			FROM {db_prefix}members
			ORDER BY id_member
			LIMIT {int:start}, {int:limit}',
			array(
				'db_error_skip' => true,
				'start' => $_GET['a'],
				'limit' => $limit,
			)
		);
		if (Db::$db->num_rows($request) != 0)
		{
			while ($row = Db::$db->fetch_assoc($request))
			{
				$inserts[] = array($row['id_member'], 'pm_new', !empty($row['pm_email_notify']) ? 2 : 0);
				$inserts[] = array($row['id_member'], 'pm_notify', $row['pm_email_notify'] == 2 ? 2 : 1);
			}
			Db::$db->free_result($request);
		}

		Db::$db->insert('ignore',
			'{db_prefix}user_alerts_prefs',
			array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'string'),
			$inserts,
			array('id_member', 'alert_pref')
		);

		$_GET['a'] += $limit;
		$step_progress['current'] = $_GET['a'];

		if ($step_progress['current'] >= $maxMembers)
			$is_done = true;
	}
	unset($_GET['a']);
}
---}
---#

---# drop column pm_email_notify on table members
ALTER TABLE {$db_prefix}members DROP COLUMN IF EXISTS pm_email_notify;
---#

/******************************************************************************/
--- Cleaning up after old UTF-8 languages
/******************************************************************************/
---# Update the members' languages
UPDATE {$db_prefix}members
SET lngfile = REPLACE(lngfile, '-utf8', '');
---#

/******************************************************************************/
--- Create index for birthday calendar query
/******************************************************************************/
---# Create help function for index
---{
upgrade_query("
	CREATE OR REPLACE FUNCTION indexable_month_day(date) RETURNS TEXT as '
	SELECT to_char($1, ''MM-DD'');'
	LANGUAGE 'sql' IMMUTABLE STRICT;"
);
---}
---#

---# Create index members_birthdate2
DROP INDEX IF EXISTS {$db_prefix}members_birthdate2;
CREATE INDEX {$db_prefix}members_birthdate2 ON {$db_prefix}members (indexable_month_day(birthdate));
---#

/******************************************************************************/
--- Create index for messages likes
/******************************************************************************/
---# Add Index for messages likes
DROP INDEX IF EXISTS {$db_prefix}messages_likes;
CREATE INDEX {$db_prefix}messages_likes ON {$db_prefix}messages (likes);
---#

/******************************************************************************/
--- Create index for messages board, msg, approved
/******************************************************************************/
---# Remove old approved index
DROP INDEX IF EXISTS {$db_prefix}messages_approved;
---#

---# Add Index for messages board, msg, approved
DROP INDEX IF EXISTS {$db_prefix}messages_id_board;
CREATE UNIQUE INDEX {$db_prefix}messages_id_board ON {$db_prefix}messages (id_board, id_msg, approved);
---#

/******************************************************************************/
--- Update smileys
/******************************************************************************/
---# Adding the new `smiley_files` table
CREATE TABLE IF NOT EXISTS {$db_prefix}smiley_files
(
	id_smiley smallint NOT NULL DEFAULT '0',
	smiley_set varchar(48) NOT NULL DEFAULT '',
	filename varchar(48) NOT NULL DEFAULT '',
	PRIMARY KEY (id_smiley, smiley_set)
);
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
				DROP COLUMN IF EXISTS filename;");
	}
}

// Set new default if the old one doesnt exist
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
ADD COLUMN IF NOT EXISTS backtrace text NOT NULL default '';
---#

/******************************************************************************/
--- Update permissions system board_permissions_view
/******************************************************************************/
---# Create table board_permissions_view
CREATE TABLE IF NOT EXISTS {$db_prefix}board_permissions_view
(
	id_group smallint NOT NULL DEFAULT '0',
	id_board smallint NOT NULL,
	deny smallint NOT NULL,
	PRIMARY KEY (id_group, id_board, deny)
);

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
--- Correct schema diff
/******************************************************************************/
---# log_subscribed
ALTER TABLE {$db_prefix}log_subscribed
ALTER pending_details DROP DEFAULT;
---#

---# mail_queue
ALTER TABLE {$db_prefix}mail_queue
ALTER recipient SET DEFAULT '';

ALTER TABLE {$db_prefix}mail_queue
ALTER subject SET DEFAULT '';
---#

---# members
ALTER TABLE {$db_prefix}members
ALTER lngfile SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER real_name SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER pm_ignore_list SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER pm_ignore_list TYPE TEXT,
ALTER pm_ignore_list DROP NOT NULL,
ALTER pm_ignore_list DROP DEFAULT;

ALTER TABLE {$db_prefix}members
ALTER email_address SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER personal_text SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER website_title SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER website_url SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER avatar SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER usertitle SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER secret_question SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER additional_groups SET DEFAULT '';

ALTER TABLE {$db_prefix}members
ALTER COLUMN password_salt TYPE varchar(255);
---#

---# messages
ALTER TABLE {$db_prefix}messages
ALTER subject SET DEFAULT '';

ALTER TABLE {$db_prefix}messages
ALTER poster_name SET DEFAULT '';

ALTER TABLE {$db_prefix}messages
ALTER poster_email SET DEFAULT '';
---#

---# package_servers
ALTER TABLE {$db_prefix}package_servers
ALTER name SET DEFAULT '';

ALTER TABLE {$db_prefix}package_servers
ALTER url SET DEFAULT '';
---#

---# permission_profiles
ALTER TABLE {$db_prefix}permission_profiles
ALTER profile_name SET DEFAULT '';
---#

---# personal_messages
ALTER TABLE {$db_prefix}personal_messages
ALTER subject SET DEFAULT '';
---#

---# polls
ALTER TABLE {$db_prefix}polls
ALTER question SET DEFAULT '';
---#

---# poll_choices
ALTER TABLE {$db_prefix}poll_choices
ALTER label SET DEFAULT '';
---#

---# settings
ALTER TABLE {$db_prefix}settings
ALTER variable SET DEFAULT '';
---#

---# sessions
ALTER TABLE {$db_prefix}sessions
ALTER session_id SET DEFAULT '';

ALTER TABLE {$db_prefix}sessions
ALTER last_update SET DEFAULT 0;
---#

---# spiders
ALTER TABLE {$db_prefix}spiders
ALTER spider_name SET DEFAULT '';

ALTER TABLE {$db_prefix}spiders
ALTER user_agent SET DEFAULT '';

ALTER TABLE {$db_prefix}spiders
ALTER ip_info SET DEFAULT '';
---#

---# subscriptions
ALTER TABLE {$db_prefix}subscriptions
ALTER id_subscribe TYPE int;

ALTER TABLE {$db_prefix}subscriptions
ALTER name SET DEFAULT '';

ALTER TABLE {$db_prefix}subscriptions
ALTER description SET DEFAULT '';

ALTER TABLE {$db_prefix}subscriptions
ALTER length SET DEFAULT '';

ALTER TABLE {$db_prefix}subscriptions
ALTER add_groups SET DEFAULT '';
---#

---# themes
ALTER TABLE {$db_prefix}themes
ALTER variable SET DEFAULT '';
---#

---# admin_info_files
ALTER TABLE {$db_prefix}admin_info_files
ALTER filename SET DEFAULT '';

ALTER TABLE {$db_prefix}admin_info_files
ALTER path SET DEFAULT '';

ALTER TABLE {$db_prefix}admin_info_files
ALTER parameters SET DEFAULT '';

ALTER TABLE {$db_prefix}admin_info_files
ALTER filetype SET DEFAULT '';
---#

---# attachments
ALTER TABLE {$db_prefix}attachments
ALTER filename SET DEFAULT '';
---#

---# ban_items
ALTER TABLE {$db_prefix}ban_items
ALTER hostname SET DEFAULT '';

ALTER TABLE {$db_prefix}ban_items
ALTER email_address SET DEFAULT '';
---#

---# boards
ALTER TABLE {$db_prefix}boards
ALTER name SET DEFAULT '';
---#

---# categories
ALTER TABLE {$db_prefix}categories
ALTER name SET DEFAULT '';
---#

---# custom_fields
ALTER TABLE {$db_prefix}custom_fields
ALTER field_desc SET DEFAULT '';

ALTER TABLE {$db_prefix}custom_fields
ALTER mask SET DEFAULT '';

ALTER TABLE {$db_prefix}custom_fields
ALTER default_value SET DEFAULT '';
---#

---# log_banned
ALTER TABLE {$db_prefix}log_banned
ALTER email SET DEFAULT '';
---#

---# log_comments
ALTER TABLE {$db_prefix}log_comments
ALTER recipient_name SET DEFAULT '';
---#

---# log_digest
ALTER TABLE {$db_prefix}log_digest
ALTER id_topic SET DEFAULT 0;

ALTER TABLE {$db_prefix}log_digest
ALTER id_msg SET DEFAULT 0;
---#

---# log_errors
ALTER TABLE {$db_prefix}log_errors
ALTER file SET DEFAULT '';
---#

---# log_member_notices
ALTER TABLE {$db_prefix}log_member_notices
ALTER subject SET DEFAULT '';
---#

---# log_online
ALTER TABLE {$db_prefix}log_online
ALTER url SET DEFAULT '';
---#

---# log_packages
ALTER TABLE {$db_prefix}log_packages
ALTER filename SET DEFAULT '';

ALTER TABLE {$db_prefix}log_packages
ALTER package_id SET DEFAULT '';

ALTER TABLE {$db_prefix}log_packages
ALTER name SET DEFAULT '';

ALTER TABLE {$db_prefix}log_packages
ALTER version SET DEFAULT '';

ALTER TABLE {$db_prefix}log_packages
ALTER themes_installed SET DEFAULT '';
---#

---# log_reported
ALTER TABLE {$db_prefix}log_reported
ALTER membername SET DEFAULT '';

ALTER TABLE {$db_prefix}log_reported
ALTER subject SET DEFAULT '';
---#

---# log_reported_comments
ALTER TABLE {$db_prefix}log_reported_comments
ALTER membername SET DEFAULT '';

ALTER TABLE {$db_prefix}log_reported_comments
ALTER comment SET DEFAULT '';
---#

---# log_actions
DROP INDEX IF EXISTS {$db_prefix}log_actions_id_topic_id_log;
CREATE INDEX {$db_prefix}log_actions_id_topic_id_log ON {$db_prefix}log_actions (id_topic, id_log);
---#

/******************************************************************************/
--- FROM_UNIXTIME fix
/******************************************************************************/
---# Drop the old int version
DROP FUNCTION IF EXISTS FROM_UNIXTIME(int);
---#

---# Add FROM_UNIXTIME for bigint
CREATE OR REPLACE FUNCTION FROM_UNIXTIME(bigint) RETURNS timestamp AS
	'SELECT timestamp ''epoch'' + $1 * interval ''1 second'' AS result'
LANGUAGE 'sql';
---#

/******************************************************************************/
--- bigint versions of date functions
/******************************************************************************/
---# MONTH(bigint)
CREATE OR REPLACE FUNCTION MONTH (bigint) RETURNS integer AS
	'SELECT CAST (EXTRACT(MONTH FROM TO_TIMESTAMP($1)) AS integer) AS result'
LANGUAGE 'sql';
---#

---# DAYOFMONTH(bigint)
CREATE OR REPLACE FUNCTION DAYOFMONTH (bigint) RETURNS integer AS
	'SELECT CAST (EXTRACT(DAY FROM TO_TIMESTAMP($1)) AS integer) AS result'
LANGUAGE 'sql';
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
DROP INDEX IF EXISTS {$db_prefix}attachments_id_thumb;
CREATE INDEX {$db_prefix}attachments_id_thumb ON {$db_prefix}attachments (id_thumb);
---#

/******************************************************************************/
--- Update log_spider_stats
/******************************************************************************/
---# Allow for hyper aggressive crawlers
ALTER TABLE {$db_prefix}log_spider_stats ALTER COLUMN page_hits TYPE INT;
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