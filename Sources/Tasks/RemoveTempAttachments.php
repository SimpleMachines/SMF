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
	/**
	 * This executes the task.
	 *
	 * @return bool Always returns true.
	 */
	public function execute()
	{
		// We need to know where this thing is going.
		if (!empty(Config::$modSettings['currentAttachmentUploadDir'])) {
			if (!is_array(Config::$modSettings['attachmentUploadDir'])) {
				Config::$modSettings['attachmentUploadDir'] = Utils::jsonDecode(Config::$modSettings['attachmentUploadDir'], true);
			}

			// Just use the current path for temp files.
			$attach_dirs = Config::$modSettings['attachmentUploadDir'];
		} else {
			$attach_dirs = [Config::$modSettings['attachmentUploadDir']];
		}

		foreach ($attach_dirs as $attach_dir) {
			$dir = @opendir($attach_dir);

			if (!$dir) {
				Theme::loadEssential();
				Lang::load('Post');

				Utils::$context['scheduled_errors']['remove_temp_attachments'][] = Lang::$txt['cant_access_upload_path'] . ' (' . $attach_dir . ')';

				ErrorHandler::log(Lang::$txt['cant_access_upload_path'] . ' (' . $attach_dir . ')', 'critical');

				return true;
			}

			while ($file = readdir($dir)) {
				if ($file == '.' || $file == '..') {
					continue;
				}

				if (strpos($file, 'post_tmp_') !== false) {
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
}

?>