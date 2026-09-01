<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 3
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v1_1;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Db\Schema;
use SMF\Graphics\Image;
use SMF\Maintenance\Migration\MigrationBase;

class Thumbnails extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating thumbnails';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$table = new Schema\v1_1\Attachments();
		$structure = $table->getCurrentStructure();

		// Post SMF 1.1 Beta 1.
		if (isset($structure['columns']['customAvatarDir'])) {
			$table->alterColumn($table->columns['attachmentType'], 'customAvatarDir');
		}

		$table->normalize();

		// Pre SMF 1.1.
		if (!isset($structure['columns']['attachmentType'])) {
			// Get a list of attachments currently stored in the database.
			$filenames = [];
			$encrypted_filenames = [];
			$ID_MSG = [];

			$request = $this->query(
				'SELECT ID_ATTACH, ID_MSG, filename
				FROM {db_prefix}attachments',
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				list($filename, $clean_name) = $this->getAttachmentFilePathAndName($row['filename'], (int) $row['ID_ATTACH']);

				$filenames[$row['ID_ATTACH']] = $clean_name;
				$encrypted_filenames[$row['ID_ATTACH']] = $filename;
				$ID_MSG[$row['ID_ATTACH']] = $row['ID_MSG'];
			}

			Db::$db->free_result($request);

			// Let's loop through the attachments
			if (
				is_dir(Config::$modSettings['attachmentUploadDir'])
				&& $dir = @opendir(Config::$modSettings['attachmentUploadDir'])
			) {
				while ($file = readdir($dir)) {
					if (substr($file, -6) == '_thumb') {
						// We found a thumbnail, now find the attachment it represents.
						$attach_realFilename = substr($file, 0, -6);

						if (\in_array($attach_realFilename, $filenames)) {
							$attach_id = array_search($attach_realFilename, $filenames);
							$attach_filename = $attach_realFilename;
						} elseif (\in_array($attach_realFilename, $encrypted_filenames)) {
							$attach_id = array_search($attach_realFilename, $encrypted_filenames);
							$attach_filename = $filenames[$attach_id];
						} else {
							continue;
						}

						// No need to register thumbs of non-existent attachments.
						if (
							!file_exists(Config::$modSettings['attachmentUploadDir'] . '/' . $attach_realFilename)
							|| \strlen($attach_filename) > 249
						) {
							continue;
						}

						// Determine the dimensions of the thumb.
						$thumbnail_image = new Image(Config::$modSettings['attachmentUploadDir'] . '/' . $file);

						$thumb_filename = $attach_filename . '_thumb';

						// Insert the thumbnail in the attachment database.
						$thumb_attach_id = Db::$db->insert(
							method: '',
							table: '{db_prefix}attachments',
							columns: [
								'ID_MSG' => 'int',
								'attachmentType' => 'int',
								'filename' => 'string-255',
								'size' => 'int',
								'width' => 'int',
								'height' => 'int',
							],
							data: [
								[
									$ID_MSG[$attach_id],
									3,
									$thumb_filename,
									$thumbnail_image->filesize,
									$thumbnail_image->width,
									$thumbnail_image->height,
								],
							],
							keys: ['ID_ICON'],
						);

						// Determine the dimensions of the original attachment.
						$orig_image = new Image(Config::$modSettings['attachmentUploadDir'] . '/' . $attach_realFilename);

						// Link the original attachment to its thumb.
						if (\is_int($orig_image->width) && \is_int($orig_image->height)) {
							$this->query(
								'UPDATE {db_prefix}attachments
								SET
									id_thumb = {int:thumb_id},
									width = {int:width},
									height = {int:height}
								WHERE ID_ATTACH = {int:attach_id}
								LIMIT 1',
								[
									'attach_id' => $attach_id,
									'thumb_id' => $thumb_attach_id,
									'width' => $orig_image->width,
									'height' => $orig_image->height,
								],
							);
						}

						// Since it's an attachment now, we might as well encrypt it.
						if (!empty(Config::$modSettings['attachmentEncryptFilenames'])) {
							@rename(
								Config::$modSettings['attachmentUploadDir'] . '/' . $file,
								Config::$modSettings['attachmentUploadDir'] . '/' . $thumb_attach_id . '_' . strtr($thumb_filename, '.', '_') . md5($thumb_filename),
							);
						}
					}
				}
				closedir($dir);
			}
		}

		$this->handleTimeout();

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Get the file path that would have been used to save to disk.
	 *
	 * @param string $filename
	 * @param int $attachment_id
	 * @return array
	 */
	private function getAttachmentFilePathAndName(string $filename, int $attachment_id): array
	{
		// This is not a modern or robust way to deal with non-ASCII characters,
		// but it's how things were done in the early days of SMF, so we need
		// to reproduce it here.
		$clean_name = strtr(
			$filename,
			"\x8a\x8e\x9a\x9e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xe0\xe1\xe2\xe3\xe4\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xff",
			'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy',
		);

		$clean_name = strtr(
			$clean_name,
			[
				'Þ' => 'TH',
				'þ' => 'th',
				'Ð' => 'DH',
				'ð' => 'dh',
				'ß' => 'ss',
				'Œ' => 'OE',
				'œ' => 'oe',
				'Æ' => 'AE',
				'æ' => 'ae',
				'µ' => 'u',
			],
		);

		$clean_name = preg_replace(
			['/\s/', '/[^\w_\.\-]/'],
			['_', ''],
			$clean_name,
		);

		$enc_name = $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);

		$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

		if (file_exists(Config::$modSettings['attachmentUploadDir'] . '/' . $enc_name)) {
			$filename = Config::$modSettings['attachmentUploadDir'] . '/' . $enc_name;
		} else {
			$filename = Config::$modSettings['attachmentUploadDir'] . '/' . $clean_name;
		}

		return [$filename, $clean_name];
	}
}
