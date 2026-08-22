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
use SMF\Maintenance\Maintenance;
use SMF\Maintenance\Migration\MigrationBase;
use SMF\Utils;

class AttachmentDirectory extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Fixing attachment directory setting';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Is it a simple file path?
		if (
			!\is_array(Config::$modSettings['attachmentUploadDir'])
			&& is_dir(Config::$modSettings['attachmentUploadDir'])
		) {
			return $this->update([1 => Config::$modSettings['attachmentUploadDir']]);
		}

		// Is it an array of file paths?
		if (\is_array(Config::$modSettings['attachmentUploadDir'])) {
			return $this->update(Config::$modSettings['attachmentUploadDir']);
		}

		// Is it a serialized string?
		if (
			\is_array(
				@Utils::safeUnserialize(
					Config::$modSettings['attachmentUploadDir'],
				),
			)
		) {
			return $this->update(
				Utils::safeUnserialize(
					Config::$modSettings['attachmentUploadDir'],
				),
			);
		}

		// Is it a JSON string?
		if (
			\is_array(
				@Utils::jsonDecode(
					Config::$modSettings['attachmentUploadDir'],
					associative: true,
					should_log: false,
				),
			)
		) {
			return $this->update(
				Utils::jsonDecode(
					Config::$modSettings['attachmentUploadDir'],
					associative: true,
					should_log: false,
				),
			);
		}

		// If all else failed, fall back to the default.
		return $this->update([1 => Config::$boarddir . DIRECTORY_SEPARATOR . 'attachments']);
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * Updates
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private function update(array $attach_dirs): bool
	{
		$current_attach_dir = Config::$modSettings['currentAttachmentUploadDir'] ?? array_key_first($attach_dirs);

		Maintenance::$tool->updateModSettings([
			'attachmentUploadDir' => Utils::jsonEncode($attach_dirs),
			'currentAttachmentUploadDir' => $current_attach_dir,
		]);

		return true;
	}
}
