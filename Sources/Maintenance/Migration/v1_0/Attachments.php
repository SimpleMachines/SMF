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

namespace SMF\Maintenance\Migration\v1_0;

use SMF\Maintenance\Migration\MigrationBase;

class Attachments extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Converting attachments';

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function execute(): bool
	{
		$this->query(
			'INSERT INTO {db_prefix}attachments
				(ID_MSG, filename, size)
			SELECT ID_MSG, SUBSTRING(attachmentFilename, 1, 255), attachmentSize
			FROM {db_prefix}messages
			WHERE attachmentFilename IS NOT NULL
				AND attachmentFilename != {empty}',
		);

		$this->query(
			'ALTER TABLE {db_prefix}messages
			DROP attachmentSize,
			DROP attachmentFilename',
		);

		$this->query(
			'ALTER TABLE {db_prefix}attachments
			DROP INDEX ID_MEMBER,
			ADD UNIQUE ID_MEMBER (ID_MEMBER, ID_ATTACH)',
		);

		$this->query(
			'ALTER TABLE {db_prefix}attachments
			CHANGE COLUMN size size int(10) unsigned NOT NULL default {literal:0}',
		);

		$this->handleTimeout();

		return true;
	}
}
