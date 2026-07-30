<?php

/**
 * Real attachments: rows in the table and matching files on disk.
 *
 * Populate.php posts nothing but text, so a baseline built from it has an empty
 * attachments table and an empty attachments directory. SMF 3.0 has several
 * migrations that only do anything when there is something attached --
 * LegacyAttachments looks for rows still using the pre-2.1 naming,
 * AttachmentSizes fills in width and height, AttachmentDirectory rewrites how
 * the upload path is stored -- and all of them would be silent no-ops.
 *
 * The files are generated here rather than committed: a 1x1 PNG and a short
 * text file are enough for the migrations to have something to inspect, and the
 * whole set stays under a kilobyte in the artifact.
 *
 * Three kinds of row, because SMF distinguishes them by attachment_type and by
 * whether a thumbnail exists:
 *
 *   0  a normal attachment on a post
 *   3  a thumbnail, pointed at by its parent's id_thumb
 *   1  an avatar, which lives in custom_avatar rather than attachments
 *
 * Exercises: LegacyAttachments, AttachmentSizes, AttachmentDirectory.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

$baseline_name = '35-attachments';

if (baseline_applied($baseline_name) && empty($baseline_force))
{
	baseline_say($baseline_name . ': skipped');
}
else
{
	global $smcFunc, $modSettings, $boarddir;

	$attach_dir = $boarddir . '/attachments';
	$avatar_dir = $boarddir . '/custom_avatar';

	if (!is_dir($attach_dir))
		mkdir($attach_dir, 0777, true);

	// A 1x1 transparent PNG, the smallest thing that is still genuinely an
	// image as far as getimagesize() is concerned.
	$png = base64_decode(
		'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk' .
		'YPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
	);

	$text = "This attachment exists so the upgrade has a file to migrate.\n";

	$members = baseline_member_ids(4);
	$made = 0;

	// Hang them off the first few messages, so they belong to real posts.
	$request = $smcFunc['db_query']('', '
		SELECT id_msg, id_member
		FROM {db_prefix}messages
		ORDER BY id_msg
		LIMIT {int:limit}',
		array('limit' => 3)
	);

	$targets = array();

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$targets[] = $row;

	$smcFunc['db_free_result']($request);

	foreach ($targets as $index => $target)
	{
		$is_image = $index < 2;
		$name = $is_image ? 'baseline-' . ($index + 1) . '.png' : 'baseline-notes.txt';
		$body = $is_image ? $png : $text;
		$ext = $is_image ? 'png' : 'txt';
		$mime = $is_image ? 'image/png' : 'text/plain';

		$hash = sha1(md5($name . $index) . $index);

		$id_attach = $smcFunc['db_insert']('',
			'{db_prefix}attachments',
			array(
				'id_thumb' => 'int', 'id_msg' => 'int', 'id_member' => 'int', 'id_folder' => 'int',
				'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40',
				'fileext' => 'string-8', 'size' => 'int', 'downloads' => 'int',
				'width' => 'int', 'height' => 'int', 'mime_type' => 'string-128', 'approved' => 'int',
			),
			array(
				0, (int) $target['id_msg'], (int) $target['id_member'], 1,
				0, $name, $hash,
				$ext, strlen($body), $index * 3,
				$is_image ? 1 : 0, $is_image ? 1 : 0, $mime, 1,
			),
			array('id_attach'),
			1
		);

		// The on-disk name is <id>_<hash>.dat -- always .dat, whatever the file
		// really is, which is how SMF stops the web server executing uploads.
		file_put_contents($attach_dir . '/' . $id_attach . '_' . $hash . '.dat', $body);
		$made++;

		// The first image also gets a thumbnail, which is a second attachment
		// row of type 3 that the parent points at.
		if ($index !== 0)
			continue;

		$thumb_name = $name . '_thumb';
		$thumb_hash = sha1(md5($thumb_name) . $index);

		$id_thumb = $smcFunc['db_insert']('',
			'{db_prefix}attachments',
			array(
				'id_thumb' => 'int', 'id_msg' => 'int', 'id_member' => 'int', 'id_folder' => 'int',
				'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40',
				'fileext' => 'string-8', 'size' => 'int', 'downloads' => 'int',
				'width' => 'int', 'height' => 'int', 'mime_type' => 'string-128', 'approved' => 'int',
			),
			array(
				0, (int) $target['id_msg'], (int) $target['id_member'], 1,
				3, $thumb_name, $thumb_hash,
				'png', strlen($png), 0,
				1, 1, 'image/png', 1,
			),
			array('id_attach'),
			1
		);

		file_put_contents($attach_dir . '/' . $id_thumb . '_' . $thumb_hash . '.dat', $png);
		$made++;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}attachments
			SET id_thumb = {int:id_thumb}
			WHERE id_attach = {int:id_attach}',
			array('id_thumb' => $id_thumb, 'id_attach' => $id_attach)
		);
	}

	// An avatar. attachment_type 1 means it lives in custom_avatar/ under its
	// plain filename, not in attachments/ under a hash -- a second layout the
	// migrations have to cope with.
	if (is_dir($avatar_dir))
	{
		$avatar_member = $members[1];
		$avatar_name = 'avatar_' . $avatar_member . '.png';

		$id_avatar = $smcFunc['db_insert']('',
			'{db_prefix}attachments',
			array(
				'id_thumb' => 'int', 'id_msg' => 'int', 'id_member' => 'int', 'id_folder' => 'int',
				'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40',
				'fileext' => 'string-8', 'size' => 'int', 'downloads' => 'int',
				'width' => 'int', 'height' => 'int', 'mime_type' => 'string-128', 'approved' => 'int',
			),
			array(
				0, 0, $avatar_member, 1,
				1, $avatar_name, '',
				'png', strlen($png), 0,
				1, 1, 'image/png', 1,
			),
			array('id_attach'),
			1
		);

		file_put_contents($avatar_dir . '/' . $avatar_name, $png);
		$made++;

		// A member with an uploaded avatar has an empty avatar column: SMF finds
		// the file through the attachments table instead. Getting this wrong
		// would make the baseline describe a state the software never produces.
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET avatar = {string:avatar}
			WHERE id_member = {int:id_member}',
			array('avatar' => '', 'id_member' => $avatar_member)
		);

		unset($id_avatar);
	}

	baseline_say(sprintf('%s: %d attachment(s) on disk and in the table', $baseline_name, $made));

	baseline_mark_applied($baseline_name);
}
