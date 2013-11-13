/* ATTENTION: You don't need to run or use this file!  The upgrade.php script does everything for you! */

/******************************************************************************/
--- Adding new settings...
/******************************************************************************/

---# Creating login history sequence.
CREATE SEQUENCE {$db_prefix}member_logins_seq;
---#

---# Creating login history table.
CREATE TABLE {$db_prefix}member_logins (
	id_login int NOT NULL default nextval('{$db_prefix}member_logins_seq'),
	id_member mediumint NOT NULL,
	time int NOT NULL,
	ip varchar(255) NOT NULL default '',
	ip2 varchar(255) NOT NULL default '',
	PRIMARY KEY (id_login)
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

---# Copying the current "allow users to disable word censor" setting...
---{
if (!isset($modSettings['allow_no_censored']))
{
	$request = upgrade_query("
		SELECT value
		FROM {$db_prefix}themes
		WHERE variable='allow_no_censored'
		AND id_theme = 1 OR id_theme = '$modSettings[theme_default]'
	");
	
	// Is it set for either "default" or the one they've set as default?
	while ($row = mysql_fetch_assoc($request))
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
ADD COLUMN ip_low5 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high5 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low6 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high6 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low7 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high7 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_low8 smallint NOT NULL DEFAULT '0',
ADD COLUMN ip_high8 smallint NOT NULL DEFAULT '0';
---#

---# Changing existing columns to ban items...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 type smallint,
	ALTER COLUMN ip_high1 type smallint,
	ALTER COLUMN ip_low2 type smallint,
	ALTER COLUMN ip_high2 type smallint,
	ALTER COLUMN ip_low3 type smallint,
	ALTER COLUMN ip_high3 type smallint,
	ALTER COLUMN ip_low4 type smallint,
	ALTER COLUMN ip_high4 type smallint;"
);

upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 SET DEFAULT '0',
	ALTER COLUMN ip_high1 SET DEFAULT '0',
	ALTER COLUMN ip_low2 SET DEFAULT '0',
	ALTER COLUMN ip_high2 SET DEFAULT '0',
	ALTER COLUMN ip_low3 SET DEFAULT '0',
	ALTER COLUMN ip_high3 SET DEFAULT '0',
	ALTER COLUMN ip_low4 SET DEFAULT '0',
	ALTER COLUMN ip_high4 SET DEFAULT '0';"
);

upgrade_query("
	ALTER TABLE {$db_prefix}ban_items
	ALTER COLUMN ip_low1 SET NOT NULL,
	ALTER COLUMN ip_high1 SET NOT NULL,
	ALTER COLUMN ip_low2 SET NOT NULL,
	ALTER COLUMN ip_high2 SET NOT NULL,
	ALTER COLUMN ip_low3 SET NOT NULL,
	ALTER COLUMN ip_high3 SET NOT NULL,
	ALTER COLUMN ip_low4 SET NOT NULL,
	ALTER COLUMN ip_high4 SET NOT NULL;"
);
---}
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
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_online
	ALTER COLUMN session type varchar(64);

	ALTER TABLE {$db_prefix}log_errors
	ALTER COLUMN session type char(64);

	ALTER TABLE {$db_prefix}sessions
	ALTER COLUMN session_id type char(64);");

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
---{
upgrade_query("
	ALTER TABLE {$db_prefix}topics
	ADD COLUMN redirect_expires int NOT NULL DEFAULT '0'");
upgrade_query("
	ALTER TABLE {$db_prefix}topics
	ADD COLUMN id_redirect_topic int NOT NULL DEFAULT '0'");
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
---- Replacing MSN with Skype
/******************************************************************************/
---# Modifying the "msn" column...
ALTER TABLE {$db_prefix}members
CHANGE msn skype varchar(255) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for deny boards access
/******************************************************************************/
---# Adding new columns to boards...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}boards
	ADD COLUMN deny_member_groups varchar(255) NOT NULL DEFAULT ''");
---}
---#

/******************************************************************************/
--- Adding support for topic unwatch
/******************************************************************************/
---# Adding new columns to log_topics...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_topics
	ADD COLUMN unwatched int NOT NULL DEFAULT '0'");

UPDATE {$db_prefix}log_topics
SET unwatched = 0;

INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('enable_unwatch', 0);
---}
---#

---# Fixing column name change...
---{
upgrade_query("
	ALTER TABLE {$db_prefix}log_topics
	CHANGE COLUMN disregarded unwatched tinyint(3) NOT NULL DEFAULT '0';");
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

---# Renaming default theme...
UPDATE {$db_prefix}themes
SET value = 'SMF Default Theme - Curve2'
WHERE value LIKE 'SMF Default Theme%';
---#

/******************************************************************************/
--- Cleaning up after old themes...
/******************************************************************************/
---# Checking for "core" and removing it if necessary...
---{
// Do they have "core" installed?
if (file_exists($GLOBALS['boarddir'] . '/Themes/core'))
{
	$core_dir = $GLOBALS['boarddir'] . '/Themes/core';
	$theme_request = upgrade_query("
		SELECT id_theme
		FROM {$db_prefix}themes
		WHERE variable = 'theme_dir'
			AND value ='$core_dir'");

	// Don't do anything if this theme is already uninstalled
	if ($smcFunc['db_num_rows']($theme_request) == 1)
	{
		list($id_theme) = $smcFunc['db_fetch_row']($theme_request, 0);
		$smcFunc['db_free_result']($theme_request);

		$known_themes = explode(', ', $modSettings['knownThemes']);

		// Remove this value...
		$known_themes = array_diff($known_themes, array($id_theme));

		// Change back to a string...
		$known_themes = implode(', ', $known_themes);

		// Update the database
		upgrade_query("
			UPDATE {$db_prefix}settings
			SET value = '$known_themes'
			WHERE variable = 'knownThemes'");

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

		if ($modSettings['theme_guests'] == $id_theme)
		{
			upgrade_query("
				UPDATE {$db_prefix}settings
				SET value = 0
				WHERE variable = 'theme_guests'");
		}
	}
}
---}
---#

/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating drafts table.
CREATE TABLE {$db_prefix}user_drafts (
	id_draft int NOT NULL auto_increment,
	id_topic int NOT NULL default '0',
	id_board smallint NOT NULL default '0',
	id_reply int NOT NULL default '0',
	type smallint NOT NULL default '0',
	poster_time int NOT NULL default '0',
	id_member int NOT NULL default '0',
	subject varchar(255) NOT NULL default '',
	smileys_enabled smallint NOT NULL default '1',
	body text NOT NULL,
	icon varchar(16) NOT NULL default 'xx',
	locked smallint NOT NULL default '0',
	is_sticky smallint NOT NULL default '0',
	to_list varchar(255) NOT NULL default '',
	PRIMARY KEY (id_draft)
);
CREATE UNIQUE INDEX {$db_prefix}user_drafts_id_member ON {$db_prefix}user_drafts (id_member, id_draft, type);
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
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], $row[id_board], 'post_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], $row[id_board], 'post_autosave_draft', $row[add_deny])";
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		foreach ($inserts AS $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}board_permissions
					(id_group, id_board, permission, add_deny)
				VALUES
					" . $insert);
		}
	}

	// Next we find people who can send PMs, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], 'pm_draft', $row[add_deny])";
		$inserts[] = "($row[id_group], 'pm_autosave_draft', $row[add_deny])";
	}
	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		foreach ($inserts AS $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}permissions
					(id_group, permission, add_deny)
				VALUES
					" . $insert);
		}
	}
}
---}
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_autosave_enabled', '1');
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_show_saved_enabled', '1');
INSERT INTO {$db_prefix}settings (variable, value) VALUES ('drafts_keep_days', '7');
INSERT INTO {$db_prefix}themes (id_theme, variable, value) VALUES ('1', 'drafts_autosave_enabled', '1');
INSERT INTO {$db_prefix}themes (id_theme, variable, value) VALUES ('1', 'drafts_show_saved_enabled', '1');
---#

/******************************************************************************/
--- Adding support for likes
/******************************************************************************/
---# Creating likes table.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_likes (
  id_member int NOT NULL default '0',
  content_type char(6) default '',
  content_id int NOT NULL default '0',
  like_time int NOT NULL default '0',
  PRIMARY KEY (content_id, content_type, id_member)
);

CREATE INDEX {$db_prefix}user_likes_content ON {$db_prefix}user_likes (content_id, content_type);
CREATE INDEX {$db_prefix}user_likes_liker ON {$db_prefix}user_likes (id_member);
---#

---# Adding count to the messages table.
ALTER TABLE {$db_prefix}messages
ADD COLUMN likes smallint NOT NULL default '0';
---#

/******************************************************************************/
--- Adding support for group-based board moderation
/******************************************************************************/
---# Creating moderator_groups table
CREATE TABLE IF NOT EXISTS {$db_prefix}moderator_groups (
  id_board smallint NOT NULL default '0',
  id_group smallint NOT NULL default '0',
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
---# Showing contact details to guests should never happen.
DELETE FROM {$db_prefix}settings
WHERE variable IN ('enableStickyTopics', 'guest_hideContacts');
---#

/******************************************************************************/
--- Removing old Simple Machines files we do not need to fetch any more
/******************************************************************************/
---# We no longer call on the latest packages list.
DELETE FROM {$db_prefix}admin_info_files
WHERE filename = 'latest-packages.js'
	AND path = '/smf/';
---#

/******************************************************************************/
--- Upgrading "verification questions" feature
/******************************************************************************/
---# Creating qanda table
CREATE TABLE {$db_prefix}qanda (
  id_question smallint(5) unsigned NOT NULL auto_increment,
  lngfile varchar(255) NOT NULL default '',
  question varchar(255) NOT NULL default '',
  answers text NOT NULL,
  PRIMARY KEY (id_question),
  KEY lngfile (lngfile)
);
---#

---# Moving questions and answers to the new table
---{
	$questions = array();

	$get_questions = upgrade_query("
		SELECT body AS question, recipient_name AS answer
		FROM {$db_prefix}log_comments
		WHERE comment_type = 'ver_test'");

	while ($row = $smcFunc['db_fetch_assoc']($get_questions))
	{
		$questions[] = "($language, $row[question], serialize(array($row[answer])))";
	}

	$smcFunc['db_free_result']($get_questions);

	if (!empty($questions))
	{
		foreach ($questions as $question)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}qanda
					(lngfile, question, answers)
				VALUES
					" . $question);
		}

		// Delete the questions from log_comments now
		upgrade_query("
			DELETE FROM {$db_prefix}log_comments
			WHERE comment_type = 'ver_test'
		");
	}
---}
---#

/******************************************************************************/
--- Fixing log_online table
/******************************************************************************/
---# Changing ip to bigint
ALTER TABLE {$db_prefix}log_online ALTER ip TYPE bigint;
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

---# Adding "profile_password_own"
---{
$inserts = array();

$request = upgrade_query("
	SELECT id_group, add_deny
	FROM {$db_prefix}permissions
	WHERE permission = 'profile_identity_own'");
	
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], 'profile_password_own', $row[add_deny])";
	}

	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		foreach ($inserts as $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}permissions
					(id_group, permission, add_deny)
				VALUES
					" . $insert);
		}
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
	
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$inserts[] = "($row[id_group], 'profile_blurb_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_displayed_name_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_forum_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_other_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_signature_own', $row[add_deny])";
	}

	$smcFunc['db_free_result']($request);

	if (!empty($inserts))
	{
		foreach ($inserts as $insert)
		{
			upgrade_query("
				INSERT INTO {$db_prefix}permissions
					(id_group, permission, add_deny)
				VALUES
					" . $insert
			);
		}
	}
---}
---#

/******************************************************************************/
--- Upgrading PM labels...
/******************************************************************************/
---# Creating pm_labels sequence...
CREATE SEQUENCE {$db_prefix}pm_labels_sequence;
---#

---# Adding pm_labels table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labels (
  id_label int NOT NULL default nextval('{$db_prefix}pm_labels_seq'),
  id_member int NOT NULL default '0',
  name varchar(30) NOT NULL default '',
  PRIMARY KEY (id_label)
);
---#

---# Adding pm_labeled_messages table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labeled_messages (
  id_label int NOT NULL default '0',
  id_pm int NOT NULL default '0',
  PRIMARY KEY (id_label, id_pm)
);
---#

---# Adding "in_inbox" column to pm_recipients
ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN in_inbox smallint NOT NULL default '1';
---#

---# Moving label info to new tables and updating rules...
---{
	// First see if we still have a message_labels column
	$results = $smcFunc['db_list_columns']('{db_prefix}members', false);
	if (in_array('message_labels', $results))
	{
		// They've still got it, so pull the label info
		$get_labels = $smcFunc['db_query']('', '
			SELECT id_member, message_labels
			FROM {db_prefix}members
			WHERE message_labels != {string:blank}',
			array(
				'blank' => '',
			)
		);

		$inserts = array();
		$label_info = array();
		while ($row = $smcFunc['db_fetch_assoc']($get_labels))
		{
			// Stick this in an array
			$labels = explode(',', $row['message_labels']);

			// Build some inserts
			foreach($labels AS $index => $label)
			{
				// Keep track of the index of this label - we'll need that in a bit...
				$label_info[$row['id_member']][$label] = $index;
				$inserts[] = "($row[id_member], $label)";
			}
		}
		
		$smcFunc['db_free_result']($get_labels);

		if (!empty($inserts))
		{
			$smcFunc['db_insert']('', '{db_prefix}pm_labels', array('id_member' => 'int', 'name' => 'string-30'), $inserts, array());
		}

		// This is the easy part - update the inbox stuff
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pm_recipients
			SET in_inbox = {int:in_inbox}
			WHERE FIND_IN_SET({int:minus_one}, labels)',
			array(
				'in_inbox' => 1,
				'minus_one' => -1,
			)
		);

		// Now we go pull the new IDs for each label
		$get_new_label_ids = $smcFunc['db_query']('', '
			SELECT *
			FROM {db_prefix}pm_labels',
			array(
			)
		);

		$label_info_2 = array();
		while ($label_row = $smcFunc['db_query']($get_new_label_ids))
		{
			// Map the old index values to the new ID values...
			$old_index = $label_info[$row['id_member']][$row['label_name']];
			$label_info_2[$row['id_member']][$old_index] = $row['id_label'];
		}

		$smcFunc['db_free_result']($get_new_label_ids);

		// Pull label info from pm_recipients
		// Ignore any that are only in the inbox
		$get_pm_labels = $smcFunc['db_query']('', '
			SELECT id_pm, id_member, labels
			FROM {db_prefix}pm_recipients
			WHERE deleted = {int:not_deleted}
				AND labels != {int:minus_one}',
			array(
				'not_deleted' => 0,
				'minus_one' => -1,
			)
		);

		while ($row = $smcFunc['db_fetch_assoc']($get_pm_labels))
		{
			$labels = explode(',', $row['labels']);

			foreach($labels as $a_label)
			{
				if ($a_label == '-1')
					continue;

				$new_label_info = $label_info_2[$row['id_member']][$a_label];
				$inserts[] = array($row['id_pm'], $new_label_info); 
			}
		}

		$smcFunc['db_free_result']($get_pm_labels);

		// Insert the new data
		$smcFunc['db_insert']('', '{db_prefix}pm_labeled_messages', array('id_pm' => 'int', 'id_label' => 'int'), $inserts, array());

		// Final step of this ridiculously massive process
		$get_pm_rules = $smcFunc['db_query']('', '
			SELECT id_member, id_rule, actions
			FROM {db_prefix}pm_rules',
			array(
			),
		);

		// Go through the rules, unserialize the actions, then figure out if there's anything we can use
		while ($row = $smcFunc['db_fetch_assoc']($get_pm_rules))
		{
			// Turn this into an array...
			$actions = unserialize($row['actions']);

			// Loop through the actions and see if we're applying a label anywhere
			foreach ($actions as $index => $action)
			{
				if ($action['t'] == 'lab')
				{
					// Update the value of this label...
					$actions[$index]['v'] = $label_info_2[$row['id_member']][$action['v']];
				}
			}

			// Put this back into a string
			$actions = serialize($actions);

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}rules
				SET actions = {string:actions}
				WHERE id_rule = {int:id_rule}',
				array(
					'actions' => $actions,
					'id_rule' => $row['id_rule'],
				)	
			);
		}

		$smcFunc['db_free_result']($get_pm_rules);

		// Lastly, we drop the old columns
		$smcFunc['db_remove_column']('{db_prefix}members', 'message_labels');
		$smcFunc['db_remove_column']('{db_prefix}pm_recipients', 'labels');
	}
}

/******************************************************************************/
--- Adding support for edit reasons
/******************************************************************************/
---# Adding "modified_reason" column to messages
ALTER TABLE {$db_prefix}messages
ADD COLUMN modified_reason varchar(255) NOT NULL default '';
---#