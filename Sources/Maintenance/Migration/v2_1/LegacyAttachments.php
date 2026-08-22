<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2026 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 4
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;

class LegacyAttachments extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting legacy attachments';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return empty(Config::$modSettings['attachments_21_done']);
	}

	/**
	 *
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

		$is_done = false;

		while (!$is_done) {
			$this->handleTimeout($start);

			$request = $this->query(
				'SELECT id_attach, id_member, id_folder, filename, file_hash, mime_type
				FROM {db_prefix}attachments
				WHERE attachment_type != 1
				ORDER BY id_attach
				LIMIT 100
				OFFSET {int:start}',
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
				$current_folder = Sapi::canonicalPath(Config::$modSettings['attachmentUploadDir'][$row['id_folder']]);

				$file_hash = '';

				// Old School?
				if (empty($row['file_hash'])) {
					// Remove international characters (windows-1252)
					// These lines should never be needed again. Still, behave.
					if (empty(Config::$db_character_set) || Config::$db_character_set != 'utf8') {
						$row['filename'] = strtr(
							$row['filename'],
							[
								"\x8a" => 'S',	"\x8c" => 'OE',	"\x8e" => 'Z',
								"\x9a" => 's',	"\x9c" => 'oe',	"\x9e" => 'z',
								"\x9f" => 'Y',	"\xb5" => 'u',	"\xc0" => 'A',
								"\xc1" => 'A',	"\xc2" => 'A',	"\xc3" => 'A',
								"\xc4" => 'A',	"\xc5" => 'A',	"\xc6" => 'AE',
								"\xc7" => 'C',	"\xc8" => 'E',	"\xc9" => 'E',
								"\xca" => 'E',	"\xcb" => 'E',	"\xcc" => 'I',
								"\xcd" => 'I',	"\xce" => 'I',	"\xcf" => 'I',
								"\xd0" => 'DH',	"\xd1" => 'N',	"\xd2" => 'O',
								"\xd3" => 'O',	"\xd4" => 'O',	"\xd5" => 'O',
								"\xd6" => 'O',	"\xd8" => 'O',	"\xd9" => 'U',
								"\xda" => 'U',	"\xdb" => 'U',	"\xdc" => 'U',
								"\xdd" => 'Y',	"\xde" => 'TH',	"\xdf" => 'ss',
								"\xe0" => 'a',	"\xe1" => 'a',	"\xe2" => 'a',
								"\xe3" => 'a',	"\xe4" => 'a',	"\xe5" => 'a',
								"\xe6" => 'ae',	"\xe7" => 'c',	"\xe8" => 'e',
								"\xe9" => 'e',	"\xea" => 'e',	"\xeb" => 'e',
								"\xec" => 'i',	"\xed" => 'i',	"\xee" => 'i',
								"\xef" => 'i',	"\xf0" => 'dh',	"\xf1" => 'n',
								"\xf2" => 'o',	"\xf3" => 'o',	"\xf4" => 'o',
								"\xf5" => 'o',	"\xf6" => 'o',	"\xf8" => 'o',
								"\xf9" => 'u',	"\xfa" => 'u',	"\xfb" => 'u',
								"\xfc" => 'u',	"\xfd" => 'y',	"\xfe" => 'th',
								"\xff" => 'y',
							],
						);
					}

					// Sorry, no spaces, dots, or anything else but letters allowed.
					$row['filename'] = preg_replace(
						[
							'/\s/',
							'/[^\w\.\-]/',
						],
						[
							'_',
							'',
						],
						$row['filename'],
					);

					// Create a nice hash.
					$file_hash = hash_hmac('sha1', $row['filename'] . time(), Config::$image_proxy_secret);

					// Iterate through the possible attachment names until we find the one that exists
					$old_file = Sapi::canonicalPath($current_folder . '/' . $row['id_attach'] . '_' . strtr($row['filename'], '.', '_') . md5($row['filename']));

					if (!file_exists($old_file)) {
						$old_file = Sapi::canonicalPath($current_folder . '/' . $row['filename']);

						if (!file_exists($old_file)) {
							$old_file = false;
						}
					}

					// Build the new file.
					$new_file = Sapi::canonicalPath($current_folder . '/' . $row['id_attach'] . '_' . $file_hash . '.dat');
				}
				// Just rename the file.
				else {
					$old_file = Sapi::canonicalPath($current_folder . '/' . $row['id_attach'] . '_' . $row['file_hash']);
					$new_file = Sapi::canonicalPath($current_folder . '/' . $row['id_attach'] . '_' . $row['file_hash'] . '.dat');

					// Make sure it exists...
					if (!file_exists($old_file)) {
						$old_file = false;
					}
				}

				if (!$old_file) {
					// Existing attachment could not be found. Just skip it...
					continue;
				}

				// Check if the av is an attachment
				if ($row['id_member'] != 0) {
					if (rename($old_file, $custom_av_dir . '/' . $row['filename'])) {
						$this->query(
							'UPDATE {db_prefix}attachments
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
					rename($old_file, $new_file);
				}

				// Only update this if it was successful and the file was using the old system.
				if (
					empty($row['file_hash'])
					&& !empty($file_hash)
					&& file_exists($new_file)
					&& !file_exists($old_file)
				) {
					$this->query(
						'UPDATE {db_prefix}attachments
						SET file_hash = {string:file_hash}
						WHERE id_attach = {int:atach_id}',
						[
							'file_hash' => $file_hash,
							'attach_id' => $row['id_attach'],
						],
					);
				}

				// While we're here, do we need to update the mime_type?
				if (empty($row['mime_type']) && file_exists($new_file)) {
					$mime_type = Utils::getMimeType($new_file, is_path: true);

					if (!empty($mime_type)) {
						$this->query(
							'UPDATE {db_prefix}attachments
							SET mime_type = {string:mime_type}
							WHERE id_attach = {int:id_attach}',
							[
								'id_attach' => $row['id_attach'],
								'mime_type' => $mime_type,
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
		Utils::makeWritable($custom_av_dir);

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
		$request = $this->query(
			'SELECT COUNT(*)
			FROM {db_prefix}attachments
			WHERE attachment_type != 1',
		);

		list($total_attachments) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		return (int) $total_attachments;
	}
}
