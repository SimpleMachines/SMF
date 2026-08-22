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

namespace SMF\Tasks;

use SMF\Config;
use SMF\ErrorHandler;
use SMF\Lang;
use SMF\Theme;
use SMF\Utils;

/**
 * Removes old, unposted attachments from the attachment upload directory.
 */
class RemoveTempAttachments extends ScheduledTask
{
	/****************
	 * Public methods
	 ****************/

	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 * @todo PHP 8.2: This can be changed to return type: true.
	 */
	public function execute(): bool
	{
		// We need to know where this thing is going.
		if (!isset(Config::$modSettings['attachmentUploadDir'])) {
			$this->error(null);

			return true;
		}

		// Is it a simple file path?
		if (
			!\is_array(Config::$modSettings['attachmentUploadDir'])
			&& is_dir(Config::$modSettings['attachmentUploadDir'])
		) {
			$attach_dirs = [1 => Config::$modSettings['attachmentUploadDir']];
		}
		// Is it an array of file paths?
		elseif (\is_array(Config::$modSettings['attachmentUploadDir'])) {
			$attach_dirs = Config::$modSettings['attachmentUploadDir'];
		}
		// Is it a JSON string?
		elseif (
			\is_array(
				@Utils::jsonDecode(
					Config::$modSettings['attachmentUploadDir'],
					associative: true,
					should_log: false,
				),
			)
		) {
			$attach_dirs = Utils::jsonDecode(
				Config::$modSettings['attachmentUploadDir'],
				associative: true,
				should_log: false,
			);
		}
		// Is it a serialized string?
		elseif (
			\is_array(
				@Utils::safeUnserialize(
					Config::$modSettings['attachmentUploadDir'],
				),
			)
		) {
			$attach_dirs = Utils::safeUnserialize(Config::$modSettings['attachmentUploadDir']);
		}
		// Invalid.
		else {
			$this->error(Config::$modSettings['attachmentUploadDir']);

			return true;
		}

		// Now that we have all our attachment directories, clean them.
		foreach ($attach_dirs as $attach_dir) {
			$dir = @opendir($attach_dir);

			if (!$dir) {
				$this->error($attach_dir);

				continue;
			}

			while ($file = readdir($dir)) {
				if ($file == '.' || $file == '..') {
					continue;
				}

				if (str_contains($file, 'post_tmp_')) {
					// Temp file is more than 5 hours old!
					if (filemtime($attach_dir . '/' . $file) < time() - 18000) {
						@unlink($attach_dir . '/' . $file);
					}
				}
			}

			closedir($dir);
		}

		return true;
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * undocumented method
	 *
	 * @param ?string $attach_dir
	 */
	private function error(?string $attach_dir): void
	{
		Theme::loadEssential();

		if ($attach_dir === null) {
			$error_message = Lang::getTxt(
				'attach_directory_admin_warning',
				['attach_dir' => 'null'],
				file: 'Post',
			);
		} else {
			$error_message = Lang::getTxt(
				'cant_access_upload_path',
				['path' => $attach_dir],
				file: 'Post',
			);
		}

		Utils::$context['scheduled_errors']['remove_temp_attachments'][] = $error_message;

		ErrorHandler::log($error_message, 'critical');
	}
}
