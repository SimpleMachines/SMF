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

namespace SMF\Maintenance\Migration\v2_0;

use SMF\Config;
use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Utils;

class Attachments2 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Calculating attachment MIME Types';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var int
	 *
	 * Maximum number of items to process at once.
	 */
	private int $limit = 100;

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return version_compare(
			str_replace(' ', '.', strtolower(Config::$modSettings['smfVersion'] ?? '0.0.dev.0')),
			'2.0',
			'<',
		);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$request = $this->query(
			'SELECT MAX(id_attach)
			FROM {db_prefix}attachments',
			[],
		);

		list($max) = Db::$db->fetch_row($request);

		Db::$db->free_result($request);

		$is_done = false;

		while (!$is_done) {
			$start = Maintenance::getCurrentStart();
			$this->handleTimeout();

			$request = $this->query(
				'SELECT id_attach, filename, fileext
				FROM {db_prefix}attachments
		 		WHERE fileext != {string:empty}
		 			AND mime_type = {string:empty}
		 			AND id_attach >= {int:start}
		 		ORDER BY id_attach
		 		LIMIT {int:limit}',
				[
					'empty' => '',
					'start' => $start,
					'limit' => $this->limit,
				],
			);

			while ($row = Db::$db->fetch_assoc($request)) {
				$is_done = $row['id_attach'] == $max;
				Maintenance::setCurrentStart($row['id_attach'] + 1);

				$filepath = $this->getAttachmentFilePath($row['filename'], (int) $row['id_attach']);

				if (!file_exists($filepath)) {
					continue;
				}

				$mime_type = Utils::getMimeType($filepath, true);

				if (!empty($mime_type)) {
					$updates[$mime_type][] = $row['id_attach'];
				}
			}

			Db::$db->free_result($request);

			if (!empty($updates)) {
				foreach ($updates as $mime_type => $ids) {
					$this->query(
						'UPDATE {db_prefix}attachments
						SET mime_type = {string:mime_type}
						WHERE id_attach IN ({array_int:ids})',
						[
							'mime_type' => $mime_type,
							'ids' => $ids,
						],
					);
				}
			}
		}

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
	 * @return string Path to the attachment file on disk.
	 */
	private function getAttachmentFilePath(string $filename, int $attachment_id): string
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

		if ($attachment_id == false) {
			return $clean_name;
		}

		if (file_exists(Config::$modSettings['attachmentUploadDir'] . '/' . $enc_name)) {
			$filename = Config::$modSettings['attachmentUploadDir'] . '/' . $enc_name;
		} else {
			$filename = Config::$modSettings['attachmentUploadDir'] . '/' . $clean_name;
		}

		return $filename;
	}
}
