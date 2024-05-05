/******************************************************************************/
--- Adding support for drafts
/******************************************************************************/
---# Creating draft table
CREATE TABLE IF NOT EXISTS {$db_prefix}user_drafts (
	id_draft INT UNSIGNED AUTO_INCREMENT,
	id_topic MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	id_board SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	id_reply INT UNSIGNED NOT NULL DEFAULT '0',
	type TINYINT NOT NULL DEFAULT '0',
	poster_time INT UNSIGNED NOT NULL DEFAULT '0',
	id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	subject VARCHAR(255) NOT NULL DEFAULT '',
	smileys_enabled TINYINT NOT NULL DEFAULT '1',
	body mediumtext NOT NULL,
	icon VARCHAR(16) NOT NULL DEFAULT 'xx',
	locked TINYINT NOT NULL DEFAULT '0',
	is_sticky TINYINT NOT NULL DEFAULT '0',
	to_list VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY id_draft(id_draft),
	UNIQUE idx_id_member (id_member, id_draft, type)
) ENGINE=MyISAM;
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
		$inserts[] = "($row[id_group], $row[id_board], 'post_draft', $row[add_deny])";
	}
	Db::$db->free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}board_permissions
				(id_group, id_board, permission, add_deny)
			VALUES
				" . implode(',', $inserts));

	// Next we find people who can send PMs, and assume they can save pm_drafts as well
	$request = upgrade_query("
		SELECT id_group, add_deny, permission
		FROM {$db_prefix}permissions
		WHERE permission = 'pm_send'");
	$inserts = array();
	while ($row = Db::$db->fetch_assoc($request))
	{
		$inserts[] = "($row[id_group], 'pm_draft', $row[add_deny])";
	}
	Db::$db->free_result($request);

	if (!empty($inserts))
		upgrade_query("
			INSERT IGNORE INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts));
}
---}
INSERT INTO {$db_prefix}settings
	(variable, value)
VALUES
	('drafts_autosave_enabled', '1'),
	('drafts_show_saved_enabled', '1'),
	('drafts_keep_days', '7');

INSERT INTO {$db_prefix}themes
	(id_member, id_theme, variable, value)
VALUES
	(-1, '1', 'drafts_show_saved_enabled', '1');
---#

/******************************************************************************/
--- Adding support for likes
/******************************************************************************/
---# Creating likes table.
CREATE TABLE IF NOT EXISTS {$db_prefix}user_likes (
	id_member MEDIUMINT UNSIGNED DEFAULT '0',
	content_type CHAR(6) DEFAULT '',
	content_id INT UNSIGNED DEFAULT '0',
	like_time INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (content_id, content_type, id_member),
	INDEX idx_content (content_id, content_type),
	INDEX idx_liker (id_member)
) ENGINE=MyISAM;
---#

---# Adding likes column to the messages table. (May take a while)
ALTER TABLE {$db_prefix}messages
ADD COLUMN likes SMALLINT UNSIGNED NOT NULL DEFAULT '0';
---#

/******************************************************************************/
--- Adding support for mentions
/******************************************************************************/
---# Creating mentions table
CREATE TABLE IF NOT EXISTS {$db_prefix}mentions (
	content_id INT DEFAULT '0',
	content_type VARCHAR(10) DEFAULT '',
	id_mentioned INT DEFAULT 0,
	id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
	`time` INT NOT NULL DEFAULT 0,
	PRIMARY KEY (content_id, content_type, id_mentioned),
	INDEX idx_content (content_id, content_type),
	INDEX idx_mentionee (id_member)
) ENGINE=MyISAM;
---#

/******************************************************************************/
--- Adding support for group-based board moderation
/******************************************************************************/
---# Creating moderator_groups table
CREATE TABLE IF NOT EXISTS {$db_prefix}moderator_groups (
	id_board SMALLINT UNSIGNED DEFAULT '0',
	id_group SMALLINT UNSIGNED DEFAULT '0',
	PRIMARY KEY (id_board, id_group)
) ENGINE=MyISAM;
---#

/******************************************************************************/
--- Cleaning up integration hooks
/******************************************************************************/
---# Deleting integration hooks
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
WHERE variable IN ('enableStickyTopics', 'guest_hideContacts', 'notify_new_registration', 'attachmentEncryptFilenames', 'hotTopicPosts', 'hotTopicVeryPosts', 'fixLongWords', 'admin_feature', 'log_ban_hits', 'topbottomEnable', 'simpleSearch', 'enableVBStyleLogin', 'admin_bbc', 'enable_unwatch', 'cache_memcached', 'cache_enable', 'cookie_no_auth_secret');
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
CREATE TABLE IF NOT EXISTS {$db_prefix}qanda (
	id_question SMALLINT UNSIGNED AUTO_INCREMENT,
	lngfile VARCHAR(255) NOT NULL DEFAULT '',
	question VARCHAR(255) NOT NULL DEFAULT '',
	answers TEXT NOT NULL,
	PRIMARY KEY (id_question),
	INDEX idx_lngfile (lngfile)
) ENGINE=MyISAM;
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
		$inserts[] = "($row[id_group], 'profile_password_own', $row[add_deny])";
	}

	Db::$db->free_result($request);

	if (!empty($inserts))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts)
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
		$inserts[] = "($row[id_group], 'profile_blurb_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_displayed_name_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_forum_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_website_own', $row[add_deny])";
		$inserts[] = "($row[id_group], 'profile_signature_own', $row[add_deny])";
	}

	Db::$db->free_result($request);

	if (!empty($inserts))
	{
		upgrade_query("
			INSERT INTO {$db_prefix}permissions
				(id_group, permission, add_deny)
			VALUES
				" . implode(',', $inserts)
			);
	}
---}
---#

/******************************************************************************/
--- Upgrading PM labels...
/******************************************************************************/
---# Adding pm_labels table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labels (
	id_label INT UNSIGNED AUTO_INCREMENT,
	id_member MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
	name VARCHAR(30) NOT NULL DEFAULT '',
	PRIMARY KEY (id_label)
) ENGINE=MyISAM;
---#

---# Adding pm_labeled_messages table...
CREATE TABLE IF NOT EXISTS {$db_prefix}pm_labeled_messages (
	id_label INT UNSIGNED NOT NULL DEFAULT '0',
	id_pm INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (id_label, id_pm)
) ENGINE=MyISAM;
---#

---# Adding "in_inbox" column to pm_recipients
ALTER TABLE {$db_prefix}pm_recipients
ADD COLUMN in_inbox TINYINT NOT NULL DEFAULT '1';
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
					WHERE FIND_IN_SET({int:minusone}, labels)
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
--- Adding support for edit reasons (May take a while)
/******************************************************************************/
---# Adding "modified_reason" column to messages (May take a while)
ALTER TABLE {$db_prefix}messages
ADD COLUMN modified_reason VARCHAR(255) NOT NULL DEFAULT '';
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
--- Adding mail queue settings
/******************************************************************************/
---# Adding DEFAULT settings for the mail queue
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
--- Adding gravatar settings
/******************************************************************************/
---# Adding DEFAULT gravatar settings
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
ALTER TABLE {$db_prefix}members ADD timezone VARCHAR(80) NOT NULL DEFAULT '';
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
DROP hide_email;
---#

---# Dropping the "email_address" column from log_reported_comments
ALTER TABLE {$db_prefix}log_reported_comments
DROP email_address;
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
DROP openid_uri;
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
CHANGE `url` `url` VARCHAR(1024) NOT NULL DEFAULT '';
---#

---# Changing url column in log_online from TEXT to VARCHAR(1024)
ALTER TABLE {$db_prefix}log_online
CHANGE `url` `url` VARCHAR(2048) NOT NULL DEFAULT '';
---#

/******************************************************************************/
--- Adding support for 2FA
/******************************************************************************/
---# Adding the secret column to members table
ALTER TABLE {$db_prefix}members
ADD tfa_secret VARCHAR(24) NOT NULL DEFAULT '';
---#

---# Adding the backup column to members table
ALTER TABLE {$db_prefix}members
ADD tfa_backup VARCHAR(64) NOT NULL DEFAULT '';
---#

---# Force 2FA per membergroup
ALTER TABLE {$db_prefix}membergroups
ADD COLUMN tfa_required TINYINT NOT NULL DEFAULT '0';
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
--- Remove redundant indexes
/******************************************************************************/
---# Duplicates to messages_current_topic
DROP INDEX idx_id_topic on {$db_prefix}messages;
DROP INDEX idx_topic on {$db_prefix}messages;
---#

---# Duplicate to topics_last_message_sticky and topics_board_news
DROP INDEX idx_id_board on {$db_prefix}topics;
---#

/******************************************************************************/
--- Update ban ip with ipv6 support
/******************************************************************************/
---# Add columns to ban_items
ALTER TABLE {$db_prefix}ban_items
ADD COLUMN ip_low varbinary(16),
ADD COLUMN ip_high varbinary(16);
---#

---# Convert data for ban_items
UPDATE IGNORE {$db_prefix}ban_items
SET ip_low =
	UNHEX(
		hex(
			INET_ATON(concat(ip_low1,'.',ip_low2,'.',ip_low3,'.',ip_low4))
		)
	),
ip_high =
	UNHEX(
		hex(
			INET_ATON(concat(ip_high1,'.',ip_high2,'.',ip_high3,'.',ip_high4))
		)
	)
where ip_low1 > 0;
---#

---# Create new index on ban_items
CREATE INDEX idx_ban_items_iplow_high ON {$db_prefix}ban_items(ip_low,ip_high);
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
--- Update log_action ip with ipv6 support without converting
/******************************************************************************/
---# Remove the old ip column
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_actions', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_actions DROP COLUMN ip;");
---}
---#

---# Add the new one
ALTER TABLE {$db_prefix}log_actions ADD COLUMN ip VARBINARY(16);
---#

/******************************************************************************/
--- Update log_banned ip with ipv6 support without converting
/******************************************************************************/
---# Delete old column log banned ip
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_banned', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_banned DROP COLUMN ip;");
---}
---#

---# Add the new log banned ip
ALTER TABLE {$db_prefix}log_banned ADD COLUMN ip VARBINARY(16);
---#

/******************************************************************************/
--- Update log_errors ip with ipv6 support
/******************************************************************************/
---# Delete old log errors ip column
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_errors', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_errors DROP COLUMN ip;");
---}
---#

---# Add the new ip columns to log errors
ALTER TABLE {$db_prefix}log_errors ADD COLUMN ip VARBINARY(16);
---#

---# Add the ip index for log errors
CREATE INDEX idx_ip ON {$db_prefix}log_errors (ip);
---#

/******************************************************************************/
--- Update members ip with ipv6 support
/******************************************************************************/
---# Rename old ip columns on members
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}members', 'member_ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
{
	upgrade_query("ALTER TABLE {$db_prefix}members CHANGE member_ip member_ip_old varchar(200);");
	upgrade_query("ALTER TABLE {$db_prefix}members CHANGE member_ip2 member_ip2_old varchar(200);");
}
---}
---#

---# Add the new ip columns to members
ALTER TABLE {$db_prefix}members
ADD COLUMN member_ip VARBINARY(16),
ADD COLUMN member_ip2 VARBINARY(16);
---#

---# Create an ip index for old ips
---{
$results = Db::$db->list_columns('{db_prefix}members');
if (in_array('member_ip_old', $results))
{
	upgrade_query("CREATE INDEX {$db_prefix}temp_old_ip ON {$db_prefix}members (member_ip_old);");
	upgrade_query("CREATE INDEX {$db_prefix}temp_old_ip2 ON {$db_prefix}members (member_ip2_old);");
}
---}
---#

---# Initialize new ip columns
---{
$results = Db::$db->list_columns('{db_prefix}members');
if (in_array('member_ip_old', $results))
{
	upgrade_query("UPDATE {$db_prefix}members SET member_ip = '', member_ip2 = '';");
}
---}
---#

---# Convert member ips
---{
MySQLConvertOldIp('members','member_ip_old','member_ip');
---}
---#

---# Convert member ips2
---{
MySQLConvertOldIp('members','member_ip2_old','member_ip2');
---}
---#

---# Remove the temporary ip indexes
DROP INDEX temp_old_ip on {$db_prefix}members;
DROP INDEX temp_old_ip2 on {$db_prefix}members;
---#

---# Remove the old member columns
ALTER TABLE {$db_prefix}members DROP COLUMN member_ip_old;
ALTER TABLE {$db_prefix}members DROP COLUMN member_ip2_old;
---#

/******************************************************************************/
--- Update messages poster_ip with ipv6 support (May take a while)
/******************************************************************************/
---# Rename old ip column on messages
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}messages', 'poster_ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}messages CHANGE poster_ip poster_ip_old varchar(255);");
---}
---#

---# Add the new ip column to messages
ALTER TABLE {$db_prefix}messages ADD COLUMN poster_ip VARBINARY(16);
---#

---# Create an ip index for old ips
---{
$doChange = true;
$results = Db::$db->list_columns('{db_prefix}messages');
if (!in_array('poster_ip_old', $results))
	$doChange = false;

if ($doChange)
	upgrade_query("CREATE INDEX {$db_prefix}temp_old_poster_ip ON {$db_prefix}messages (poster_ip_old);");
---}
---#

---# Initialize new ip column
---{
$results = Db::$db->list_columns('{db_prefix}messages');
if (in_array('poster_ip_old', $results))
{
	upgrade_query("UPDATE {$db_prefix}messages SET poster_ip = '';");
}
---}
---#

---# Convert ips on messages
---{
MySQLConvertOldIp('messages','poster_ip_old','poster_ip');
---}
---#

---# Remove the temporary ip indexes
DROP INDEX temp_old_poster_ip on {$db_prefix}messages;
---#

---# Drop old column to messages
ALTER TABLE {$db_prefix}messages DROP COLUMN poster_ip_old;
---#

---# Add the index again to messages poster ip topic
CREATE INDEX idx_ip_index ON {$db_prefix}messages (poster_ip, id_topic);
---#

---# Add the index again to messages poster ip msg
CREATE INDEX idx_related_ip ON {$db_prefix}messages (id_member, poster_ip, id_msg);
---#

/******************************************************************************/
--- Update log_floodcontrol ip with ipv6 support without converting
/******************************************************************************/
---# Prep floodcontrol
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_floodcontrol', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
{
	upgrade_query("TRUNCATE TABLE {$db_prefix}log_floodcontrol;");
	upgrade_query("ALTER TABLE {$db_prefix}log_floodcontrol DROP PRIMARY KEY;");
	upgrade_query("ALTER TABLE {$db_prefix}log_floodcontrol DROP COLUMN ip;");
}
---}
---#

---# Add the new floodcontrol ip column
ALTER TABLE {$db_prefix}log_floodcontrol ADD COLUMN ip VARBINARY(16);
---#

---# Modify log_type size
ALTER TABLE {$db_prefix}log_floodcontrol MODIFY log_type VARCHAR(30) NOT NULL DEFAULT 'post';
---#

---# Create primary key for floodcontrol
ALTER TABLE {$db_prefix}log_floodcontrol ADD PRIMARY KEY (ip,log_type);
---#

/******************************************************************************/
--- Update log_online ip with ipv6 support without converting
/******************************************************************************/
---# Delete the old ip column for log online
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_online', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_online DROP COLUMN ip;");
---}
---#

---# Add the new ip column for log online
ALTER TABLE {$db_prefix}log_online ADD COLUMN ip VARBINARY(16);
---#

/******************************************************************************/
--- Update log_reported_comments member_ip with ipv6 support without converting
/******************************************************************************/
---# Drop old ip column for reported comments
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}log_reported_comments', 'member_ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
	upgrade_query("ALTER TABLE {$db_prefix}log_reported_comments DROP COLUMN member_ip;");
---}
---#

---# Add the new ip column for reported comments
ALTER TABLE {$db_prefix}log_reported_comments ADD COLUMN member_ip VARBINARY(16);
---#

/******************************************************************************/
--- Update member_logins ip with ipv6 support without converting
/******************************************************************************/
---# Drop old ip columns for member logins
---{
$doChange = true;
$column_info = upgradeGetColumnInfo('{db_prefix}member_logins', 'ip');
if (stripos($column_info['type'], 'varbinary') !== false)
	$doChange = false;

if ($doChange)
{
	upgrade_query("ALTER TABLE {$db_prefix}member_logins DROP COLUMN ip;");
	upgrade_query("ALTER TABLE {$db_prefix}member_logins DROP COLUMN ip2;");
}
---}
---#

---# Add the new ip columns for member logins
ALTER TABLE {$db_prefix}member_logins ADD COLUMN ip VARBINARY(16);
ALTER TABLE {$db_prefix}member_logins ADD COLUMN ip2 VARBINARY(16);
---#

/******************************************************************************/
--- Renaming the "profile_other" permission...
/******************************************************************************/
---# Changing the "profile_other" permission to "profile_website"
UPDATE {$db_prefix}permissions SET permission = 'profile_website_own' WHERE permission = 'profile_other_own';
UPDATE {$db_prefix}permissions SET permission = 'profile_website_any' WHERE permission = 'profile_other_any';
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
ALTER TABLE {$db_prefix}members DROP COLUMN pm_email_notify;
---#

/******************************************************************************/
--- Adding support for start and end times on calendar events
/******************************************************************************/
---# Add start_time end_time, and timezone columns to calendar table
ALTER TABLE {$db_prefix}calendar
ADD COLUMN start_time time,
ADD COLUMN end_time time,
ADD COLUMN timezone VARCHAR(80);
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
ADD COLUMN location VARCHAR(255) NOT NULL DEFAULT '';
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
	('cal_week_numbers', '0');
---#

/******************************************************************************/
--- Cleaning up after old UTF-8 languages
/******************************************************************************/
---# Update the members' languages
UPDATE {$db_prefix}members
SET lngfile = REPLACE(lngfile, '-utf8', '');
---#

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