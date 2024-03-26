<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 1
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class LegacyAttachments extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Converting legacy attachments';

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return empty(Config::$modSettings['attachments_21_done']);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$start = Maintenance::getCurrentStart();

		// Only do this once.
		if ($start === 0) {
			Db::$db->change_column(
				'{db_prefix}attachments',
				'mime_type',
				[
					'type' => 'VARCHAR',
					'size' => 128,
					'not_null' => true,
					'default' => '',
				],
			);

		}

		$custom_av_dir = $this->checkCustomAvatarDirectory();
		Maintenance::$total_items = $this->getTotalAttachments();

		// We may be using multiple attachment directories.
		if (!empty(Config::$modSettings['currentAttachmentUploadDir']) && !is_array(Config::$modSettings['attachmentUploadDir']) && empty(Config::$modSettings['json_done'])) {
			Config::$modSettings['attachmentUploadDir'] = @unserialize(Config::$modSettings['attachmentUploadDir']);
		}


		$is_done = false;

		while (!$is_done) {
			$this->handleTimeout($start);

			$request = $this->query(
				'',
				'
				SELECT id_attach, id_member, id_folder, filename, file_hash, mime_type
				FROM {db_prefix}attachments
				WHERE attachment_type != 1
				ORDER BY id_attach
				LIMIT {int:start}, 100',
				[
					'start' => $start,
				],
			);

			// Finished?
			if (Db::$db->num_rows($request) == 0) {
				$is_done = true;
			}

			while ($row = Db::$db->fetch_assoc($request)) {
				// The current folder.
				$currentFolder = !empty(Config::$modSettings['currentAttachmentUploadDir']) ? Config::$modSettings['attachmentUploadDir'][$row['id_folder']] : Config::$modSettings['attachmentUploadDir'];

				$fileHash = '';

				// Old School?
				if (empty($row['file_hash'])) {
					// Remove international characters (windows-1252)
					// These lines should never be needed again. Still, behave.
					if (empty(Config::$db_character_set) || Config::$db_character_set != 'utf8') {
						$row['filename'] = strtr(
							$row['filename'],
							"\x8a\x8e\x9a\x9e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xe0\xe1\xe2\xe3\xe4\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xff",
							'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy',
						);
						$row['filename'] = strtr($row['filename'], ["\xde" => 'TH', "\xfe" =>
							'th', "\xd0" => 'DH', "\xf0" => 'dh', "\xdf" => 'ss', "\x8c" => 'OE',
							"\x9c" => 'oe', "\xc6" => 'AE', "\xe6" => 'ae', "\xb5" => 'u']);
					}
					// Sorry, no spaces, dots, or anything else but letters allowed.
					$row['filename'] = preg_replace(['/\s/', '/[^\w_\.\-]/'], ['_', ''], $row['filename']);

					// Create a nice hash.
					$fileHash = hash_hmac('sha1', $row['filename'] . time(), Config::$image_proxy_secret);

					// Iterate through the possible attachment names until we find the one that exists
					$oldFile = $currentFolder . '/' . $row['id_attach'] . '_' . strtr($row['filename'], '.', '_') . md5($row['filename']);

					if (!file_exists($oldFile)) {
						$oldFile = $currentFolder . '/' . $row['filename'];

						if (!file_exists($oldFile)) {
						$oldFile = false;
						}
					}

					// Build the new file.
					$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $fileHash . '.dat';
				}
				// Just rename the file.
				else {
					$oldFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'];
					$newFile = $currentFolder . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.dat';

					// Make sure it exists...
					if (!file_exists($oldFile)) {
						$oldFile = false;
					}
				}

				if (!$oldFile) {
					// Existing attachment could not be found. Just skip it...
					continue;
				}

				// Check if the av is an attachment
				if ($row['id_member'] != 0) {
					if (rename($oldFile, $custom_av_dir . '/' . $row['filename'])) {
						$this->query(
							'',
							'
							UPDATE {db_prefix}attachments
							SET file_hash = {empty}, attachment_type = 1
							WHERE id_attach = {int:attach_id}',
							[
								'attach_id' => $row['id_attach'],
							],
						);
						$start--;
					}
				}
				// Just a regular attachment.
				else {
					rename($oldFile, $newFile);
				}

				// Only update this if it was successful and the file was using the old system.
				if (empty($row['file_hash']) && !empty($fileHash) && file_exists($newFile) && !file_exists($oldFile)) {
					$this->query(
						'',
						'
						UPDATE {db_prefix}attachments
						SET file_hash = {string:file_hash}
						WHERE id_attach = {int:atach_id}',
						[
							'file_hash' => $fileHash,
							'attach_id' => $row['id_attach'],
						],
					);
				}

				// While we're here, do we need to update the mime_type?
				if (empty($row['mime_type']) && file_exists($newFile)) {
					$size = @getimagesize($newFile);

					if (!empty($size['mime'])) {
						Db::$db->query(
							'',
							'
							UPDATE {db_prefix}attachments
							SET mime_type = {string:mime_type}
							WHERE id_attach = {int:id_attach}',
							[
								'id_attach' => $row['id_attach'],
								'mime_type' => substr($size['mime'], 0, 20),
							],
						);
					}
				}
			}
			Db::$db->free_result($request);

			$start += 100;
			Maintenance::setCurrentStart($start);
		}

		Config::updateModSettings(['attachments_21_done' => 1]);

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 *
	 */
	protected function checkCustomAvatarDirectory(): string
	{
		// Need to know a few things first.
		$custom_av_dir = !empty(Config::$modSettings['custom_avatar_dir']) ? Config::$modSettings['custom_avatar_dir'] : Config::$boarddir . '/custom_avatar';

		// This little fellow has to cooperate...
		if (!is_writable($custom_av_dir)) {
			// Try 755 and 775 first since 777 doesn't always work and could be a risk...
			$chmod_values = [0755, 0775, 0777];

			foreach($chmod_values as $val) {
				// If it's writable, break out of the loop
				if (is_writable($custom_av_dir)) {
					break;
				}

					@chmod($custom_av_dir, $val);
			}
		}

		// If we already are using a custom dir, delete the predefined one.
		if (realpath($custom_av_dir) != realpath(Config::$boarddir . '/custom_avatar')) {
			// Borrow custom_avatars index.php file.
			if (!file_exists($custom_av_dir . '/index.php')) {
				@rename(Config::$boarddir . '/custom_avatar/index.php', $custom_av_dir . '/index.php');
			} else {
				@unlink(Config::$boarddir . '/custom_avatar/index.php');
			}

			// Borrow blank.png as well
			if (!file_exists($custom_av_dir . '/blank.png')) {
				@rename(Config::$boarddir . '/custom_avatar/blank.png', $custom_av_dir . '/blank.png');
			} else {
				@unlink(Config::$boarddir . '/custom_avatar/blank.png');
			}

			// Attempt to delete the directory.
			@rmdir(Config::$boarddir . '/custom_avatar');
		}

		return $custom_av_dir;
	}

	/**
	 *
	 */
	protected function getTotalAttachments(): int
	{
		$request = $this->query('', '
			SELECT COUNT(*)
			FROM {db_prefix}attachments
			WHERE attachment_type != 1');
		list($total_attachments) = Db::$db->fetch_row($request);
		Db::$db->free_result($request);

		return (int) $total_attachments;
	}
}

?>