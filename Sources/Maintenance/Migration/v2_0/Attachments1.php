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

use SMF\Db\Schema;
use SMF\Maintenance\Migration\MigrationBase;

class Attachments1 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating attachment data';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		// Ensure the table exists and is structured correctly.
		$table = new Schema\v2_0\Attachments();
		$table->normalize();

		// Populate the attachment extension.
		$this->query(
			'UPDATE {db_prefix}attachments
			SET fileext = LOWER(SUBSTRING(filename, 1 - (INSTR(REVERSE(filename), ' . '))))
			WHERE fileext = {string:empty}
				AND INSTR(filename, {string:dot})
				AND INSTR(REVERSE(filename), {string:dot}) < 10
				AND attachment_type != 3',
			[
				'empty' => '',
				'dot' => '.',
			],
		);

		// Updating thumbnail attachments JPG.
		$this->query(
			'UPDATE {db_prefix}attachments
			SET fileext = {string:ext}
			WHERE attachment_type = 3
				AND fileext = {string:empty}
				AND RIGHT(filename, 9) = {string:old}',
			[
				'empty' => '',
				'ext' => 'jpg',
				'old' => 'JPG_thumb',
			],
		);

		// Updating thumbnail attachments PNG.
		$this->query(
			'UPDATE {db_prefix}attachments
			SET fileext = {string:ext}
			WHERE attachment_type = 3
				AND fileext = {string:empty}
				AND RIGHT(filename, 9) = {string:old}',
			[
				'empty' => '',
				'ext' => 'png',
				'old' => 'PNG_thumb',
			],
		);

		$this->handleTimeout();

		return true;
	}
}
