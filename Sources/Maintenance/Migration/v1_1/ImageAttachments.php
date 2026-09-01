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
use SMF\Graphics\Image;
use SMF\Maintenance\Migration\MigrationBase;

class ImageAttachments extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Adding image dimensions';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT ID_ATTACH, filename, attachmentType
			FROM {db_prefix}attachments
			WHERE id_thumb = 0
				AND (
					RIGHT(filename, 4) IN ({array_string:ext4})
					OR RIGHT(filename, 5) IN ({array_string:ext5})
				)
				AND width = 0
				AND height = 0',
			[
				'ext4' => ['.gif', '.jpg', '.png', '.bmp'],
				'ext5' => ['.jpeg'],
			],
		);

		while ($row = Db::$db->fetch_assoc($request)) {
			if ($row['attachmentType'] == 1) {
				$filename = Config::$modSettings['custom_avatar_dir'] . '/' . $row['filename'];
			} else {
				$filename = $this->getAttachmentFilePath($row['filename'], (int) $row['ID_ATTACH']);
			}

			if (!file_exists($filename) || \strlen(basename($filename)) > 249) {
				continue;
			}

			$image = new Image($filename);

			if (!empty($image->width) && !empty($image->height)) {
				$this->query(
					'UPDATE {db_prefix}attachments
					SET
						width = {int:width},
						height = {int:height}
					WHERE ID_ATTACH = {int:id}
					LIMIT 1',
					[
						'id' => $row['ID_ATTACH'],
						'width' -> $image->width,
						'height' -> $image->height,
					],
				);
			}
		}

		Db::$db->free_result($request);

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
	 * @return string
	 */
	private function getAttachmentFilePath(string $filename, int $attachment_id): array
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
		} elseif (file_exists(Config::$modSettings['attachmentUploadDir'] . '/' . $clean_name)) {
			$filename = Config::$modSettings['attachmentUploadDir'] . '/' . $clean_name;
		} else {
			$filename = Config::$modSettings['attachmentUploadDir'] . '/' . $row['filename'];
		}

		return $filename;
	}
}
