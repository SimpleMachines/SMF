<?php

/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;
use SMF\Maintenance\Migration\MigrationBase;

class AdminInfoFiles extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Updating files that fetched from simplemachines.org';

	/*********************
	 * Internal properties
	 *********************/

	/****************
	 * Public methods
	 ****************/

	/**
	 * {@inheritDoc}
	 */
	public function isCandidate(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): bool
	{
		$this->query(
			'',
			'DELETE FROM {$db_prefix}admin_info_files
			WHERE filename IN ({array_string:old_files})
				AND path = {string:old_path}',
			[
				'old_files' => [
					'latest-packages.js',
					'latest-smileys.js',
					'latest-support.js',
					'latest-themes.js',
				],
				'old_path' => '/smf/',
			],
		);

		$this->handleTimeout();

		// Don't insert the info if it's already there...
		$file_check = $this->query(
			'',
			'SELECT id_file
			FROM {db_prefix}admin_info_files
			WHERE filename = {string:latest-versions}',
			[
				'latest-versions' => 'latest-versions.txt',
			],
		);

		if (Db::$db->num_rows($file_check) == 0) {
			Db::$db->insert(
				'',
				'{db_prefix}admin_info_files',
				[
					'filename' => 'string',
					'path' => 'string',
					'parameters' => 'string',
					'data' => 'string',
					'filetype' => 'string',
				],
				[
					'latest-versions.txt',
					'/smf/',
					'version=%3$s',
					'',
					'text/plain',
				],
				['id_file'],
			);
		}

		Db::$db->free_result($file_check);

		$this->handleTimeout();

		return true;
	}
}

?>