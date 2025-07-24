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

namespace SMF\Maintenance\Migration\v3_0;

use SMF\Config;
use SMF\Maintenance\Migration\MigrationBase;

class FileSystemHandler extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 *
	 */
	public string $name = 'Updating settings for File System Handler';

	/*********************
	 * Internal properties
	 *********************/

	private array $renames = [
		'package_server' => 'filesystem_server',
		'package_port' => 'filesystem_port',
		'package_username' => 'filesystem_username',
		'package_path' => 'filesystem_path',
	];

	/****************
	 * Public methods
	 ****************/

	/**
	 *
	 */
	public function isCandidate(): bool
	{
		return !empty(Config::$modSettings['package_server']);
	}

	/**
	 *
	 */
	public function execute(): bool
	{
		$newSettings = [];

		foreach ($this->renames as $oldKey => $newKey) {
			if (!isset(Config::$modSettings[$oldKey])) {
				continue;
			}

			$newSettings[$newKey] = Config::$modSettings[$oldKey];
			$newSettings[$oldKey] = null;
		}

		// Hold on, do we have a 'ssl://' or 'ftps://' in the server name?
		if (str_starts_with(Config::$modSettings['package_server'], 'ssl://') || str_starts_with(Config::$modSettings['package_server'], 'ftps://')) {
			$server_addr = preg_replace('~^((ft|htt)ps?|ssl)?://~i', '', Config::$modSettings['package_server']);
			$server_addr = strtr($server_addr, ['/' => '', ':' => '', '@' => '']);

			$newSettings['filesystem_server'] = $server_addr;
			$newSettings['filesystem_type'] = 'FtpSSL';
		}

		Config::updateModSettings($newSettings);

		return true;
	}
}
