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

declare(strict_types=1);

namespace SMF\Maintenance\Migration\v2_1;

use SMF\Db\DatabaseApi as Db;

class Migration1011 extends MigrationBase
{
	/*******************
	 * Public properties
	 *******************/

	/**
	 * {@inheritDoc}
	 */
	public string $name = 'Adding support for validation servers';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 *
	 */
	protected array $newColumns = ['validation_url', 'extra'];

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
		$PackageServersTable = new \SMF\Db\Schema\v3_0\PackageServers();

		$existing_columns = Db::$db->list_columns('{db_prefix}' . $PackageServersTable->name);

		foreach ($PackageServersTable->columns as $column) {
			// Column exists, don't need to do this.
			if (in_array($column->name, $this->newColumns) && in_array($column->name, $existing_columns)) {
				continue;
			}

			$column->add('{db_prefix}' . $PackageServersTable->name);
		}

		$request = Db::$db->query(
			'',
			'
			SELECT id_server
			FROM {db_prefix}{raw:table_name}
			WHERE url LIKE {string:downloads_site}',
			[
				'table_name' => $PackageServersTable->name,
				'downloads_site' => 'https://download.simplemachines.org%',
			],
		);

		if (Db::$db->num_rows($request) != 0) {
			list($downloads_server) = Db::$db->fetch_row($request);
		}
		Db::$db->free_result($request);

		if (empty($downloads_server)) {
			Db::$db->insert(
				'',
				'{db_prefix}' . $PackageServersTable->name,
				['name' => 'string', 'url' => 'string', 'validation_url' => 'string'],
				['Simple Machines Download Site', 'https://download.simplemachines.org/browse.php?api=v1;smf_version={SMF_VERSION}', 'https://download.simplemachines.org/validate.php?api=v1;smf_version={SMF_VERSION}'],
				['id_server'],
			);
		}

		// Ensure The Simple Machines Customize Site is https
		Db::$db->query(
			'',
			'
			UPDATE {$db_prefix}{raw:table_name}
			SET url = {string:current_url}
			WHERE url = {string:old_url}',
			[
				'table_name' => $PackageServersTable->name,
				'old_url' => 'http://custom.simplemachines.org/packages/mods',
				'current_url' => 'https://custom.simplemachines.org/packages/mods',
			],
		);

		// Add validation to Simple Machines Customize Site
		Db::$db->query(
			'',
			'
			UPDATE {$db_prefix}{raw:table_name}
			SET url = {string:validation_url}
			WHERE url = {string:custom_site}',
			[
				'table_name' => $PackageServersTable->name,
				'validation_url' => 'https://custom.simplemachines.org/api.php?action=validate;version=v1;smf_version={SMF_VERSION}',
				'custom_site' => 'https://custom.simplemachines.org/packages/mods',
			],
		);

		return true;
	}
}

?>